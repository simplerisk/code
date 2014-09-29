<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/****************************
 * FUNCTION: VIEW TOP TABLE *
 ****************************/
function view_top_table($id, $calculated_risk, $subject, $status, $show_details = false)
{
	global $lang;
	
	echo "<table width=\"100%\" cellpadding=\"10\" cellspacing=\"0\" style=\"border:none;\">\n";
        echo "<tr>\n";
        echo "<td width=\"100\" valign=\"middle\" halign=\"center\">\n";

        echo "<table width=\"100\" height=\"100\" border=\"10\" class=" . get_risk_color($calculated_risk) . ">\n";
        echo "<tr>\n";
        echo "<td valign=\"middle\" halign=\"center\">\n";
        echo "<center>\n";
	echo "<font size=\"72\">" . $calculated_risk . "</font><br />\n";
        echo "(". get_risk_level_name($calculated_risk) . ")\n";
        echo "</center>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";

        echo "</td>\n";
        echo "<td valign=\"left\" halign=\"center\">\n";

	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";
        echo "<tr>\n";
        echo "<td width=\"100\"><h4>". $lang['RiskId'] .":</h4></td>\n";
	echo "<td><h4>" . $id . "</h4></td>\n";
	echo "</tr>\n";
        echo "<tr>\n";
        echo "<td width=\"100\"><h4>". $lang['Subject'] .":</h4></td>\n";
	echo "<td><h4>" . htmlentities(stripslashes($subject), ENT_QUOTES, 'UTF-8', false) . "</h4></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td width=\"100\"><h4>". $lang['Status'] .":</h4></td>\n";
	echo "<td><h4>" . $status . "</h4></td>\n";
	echo "</tr>\n";
	echo "</table>\n";

        echo "</td>\n";
        echo "<td valign=\"top\">\n";
        echo "<div class=\"btn-group pull-right\">\n";
        echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">". $lang['RiskActions'] ."<span class=\"caret\"></span></a>\n";
        echo "<ul class=\"dropdown-menu\">\n";

        // If the risk is closed, offer to reopen
        if ($status == "Closed")
        {
        	echo "<li><a href=\"index.php?module=1&page=9&id=".$id."\">". $lang['ReopenRisk'] ."</a></li>\n";
        }
        // Otherwise, offer to close
        else
        {
        	// If the user has permission to close risks
                if (isset($_SESSION["close_risks"]) && $_SESSION["close_risks"] == 1)
                {
                	echo "<li><a href=\"index.php?module=1&page=8&id=".$id."\">". $lang['CloseRisk'] ."</a></li>\n";
                }
        }

	echo "<li><a href=\"index.php?module=1&page=5&id=" . $id . "\">". $lang['EditRisk'] ."</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=7&id=".$id."\">". $lang['PlanAMitigation'] ."</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=6&id=" . $id . "\">". $lang['PerformAReview'] ."</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=10&id=" . $id . "\">". $lang['AddAComment'] ."</a></li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        echo "</td>\n";
        echo "</tr>\n";

	// If we want to show the details
	if ($show_details)
	{
		echo "<tr>\n";
		echo "<td colspan=\"3\">\n";
		echo "<a href=\"#\" id=\"show\" onclick=\"javascript: showScoreDetails();\">". $lang['ShowRiskScoringDetails'] ."</a>\n";
        	echo "<a href=\"#\" id=\"hide\" style=\"display: none;\" onclick=\"javascript: hideScoreDetails();\">". $lang['HideRiskScoringDetails'] ."</a>\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

        echo "</table>\n";
}

/*******************************
 * FUNCTION: VIEW RISK DETAILS *
 *******************************/
function view_risk_details($submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes)
{
	global $lang;
	
	echo "<h4>". $lang['Details'] ."</h4>\n";
        echo $lang['SubmissionDate'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"submission_date\" id=\"submission_date\" size=\"50\" value=\"" . $submission_date . "\" title=\"" . $submission_date . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['Subject'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"subject\" id=\"subject\" size=\"50\" value=\"" . htmlentities(stripslashes($subject), ENT_QUOTES, 'UTF-8', false) . "\" title=\"" . htmlentities($subject, ENT_QUOTES, 'UTF-8', false) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['ExternalReferenceId'] .": \n";
        echo "<br />\n";
        echo " <input style=\"cursor: default;\" type=\"text\" name=\"reference_id\" id=\"reference_id\" size=\"20\" value=\"" . htmlentities(stripslashes($reference_id), ENT_QUOTES, 'UTF-8', false) . "\" title=\"" . htmlentities($reference_id, ENT_QUOTES, 'UTF-8', false) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['ControlRegulation'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"regulation\" id=\"regulation\" size=\"50\" value=\"" . get_name_by_value("regulation", $regulation) . "\" title=\"" . get_name_by_value("regulation", $regulation) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['ControlNumber'] .": \n";
        echo "<br />\n";
        echo " <input style=\"cursor: default;\" type=\"text\" name=\"control_number\" id=\"control_number\" size=\"20\" value=\"" . htmlentities(stripslashes($control_number), ENT_QUOTES, 'UTF-8', false) . "\" title=\"" . htmlentities(stripslashes($control_number), ENT_QUOTES, 'UTF-8', false) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['SiteLocation'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"location\" id=\"location\" size=\"50\" value=\"" . get_name_by_value("location", $location) . "\" title=\"" . get_name_by_value("location", $location) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['Category'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"category\" id=\"category\" size=\"50\" value=\"" . get_name_by_value("category", $category) . "\" title=\"" . get_name_by_value("category", $category) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['Team'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"team\" id=\"team\" size=\"50\" value=\"" . get_name_by_value("team", $team) . "\" title=\"" . get_name_by_value("team", $team) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['Technology'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"technology\" id=\"technology\" size=\"50\" value=\"" . get_name_by_value("technology", $technology) . "\" title=\"" . get_name_by_value("technology", $technology) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['Owner'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"owner\" id=\"owner\" size=\"50\" value=\"" . get_name_by_value("user", $owner) . "\" title=\"" . get_name_by_value("user", $owner) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['OwnersManager'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"manager\" id=\"manager\" size=\"50\" value=\"" . get_name_by_value("user", $manager) . "\" title=\"" . get_name_by_value("user", $manager) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['RiskAssessment'] .": \n";
	echo "<br />\n";
        echo "<textarea style=\"cursor: default;\" name=\"assessment\" cols=\"50\" rows=\"3\" id=\"assessment\" title=\"" . htmlentities(stripslashes($assessment), ENT_QUOTES, 'UTF-8', false) . "\" disabled=\"disabled\">" . htmlentities($assessment, ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
	echo "<br />\n";
        echo $lang['AdditionalNotes'] .": \n";
	echo "<br />\n";
        echo "<textarea style=\"cursor: default;\" name=\"notes\" cols=\"50\" rows=\"3\" id=\"notes\" title=\"" . htmlentities(stripslashes($notes), ENT_QUOTES, 'UTF-8', false) . "\" disabled=\"disabled\">" . htmlentities($notes, ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";

	// If the page is the view.php page
	if (basename($_SERVER['PHP_SELF']) == "index.php")
	{
		// Give the option to edit the risk details
        	echo "<div class=\"form-actions\">\n";
        	echo "<button type=\"submit\" name=\"edit_details\" class=\"btn btn-primary\">". $lang['EditDetails'] ."</button>\n";
        	echo "</div>\n";
	}
}

/*******************************
 * FUNCTION: EDIT RISK DETAILS *
 *******************************/
function edit_risk_details($submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $assessment, $notes)
{
	global $lang;
	
	echo "<h4>". $lang['Details'] ."</h4>\n";
        echo $lang['SubmissionDate'] .": \n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"submission_date\" id=\"submission_date\" size=\"50\" value=\"" . $submission_date . "\" title=\"" . $submission_date . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['Subject'] .": \n";
	echo "<br />\n";
	echo "<input type=\"text\" name=\"subject\" id=\"subject\" size=\"50\" value=\"" . htmlentities(stripslashes($subject), ENT_QUOTES, 'UTF-8', false) . "\" />\n";
        echo "<br />\n";
        echo $lang['ExternalReferenceId'] .": <input type=\"text\" name=\"reference_id\" id=\"reference_id\" size=\"20\" value=\"" . htmlentities(stripslashes($reference_id), ENT_QUOTES, 'UTF-8', false) . "\" />\n";
        echo "<br />\n";
        echo $lang['ControlRegulation'] .": \n";
	echo "<br />\n";
        create_dropdown("regulation", $regulation);
        echo "<br />\n";
        echo $lang['ControlNumber'] .": <input type=\"text\" name=\"control_number\" id=\"control_number\" size=\"20\" value=\"" . htmlentities(stripslashes($control_number), ENT_QUOTES, 'UTF-8', false) . "\" />\n";
        echo "<br />\n";
        echo $lang['SiteLocation'] .": \n";
        echo "<br />\n";
        create_dropdown("location", $location);
        echo "<br />\n";
        echo $lang['Category'] .": \n";
        echo "<br />\n";
        create_dropdown("category", $category);
        echo "<br />\n";
        echo $lang['Team'] .": \n";
        echo "<br />\n";
        create_dropdown("team", $team);
        echo "<br />\n";
        echo $lang['Technology'] .": \n";
        echo "<br />\n";
        create_dropdown("technology", $technology);
        echo "<br />\n";
        echo $lang['Owner'] .": \n";
        echo "<br />\n";
        create_dropdown("user", $owner, "owner");
        echo "<br />\n";
        echo $lang['OwnersManager'] .": \n";
        echo "<br />\n";
        create_dropdown("user", $manager, "manager");
        echo "<br />\n";
        echo $lang['RiskAssessment'] .": \n";
        echo "<br />\n";
        echo "<textarea name=\"assessment\" cols=\"50\" rows=\"3\" id=\"assessment\">" . htmlentities(stripslashes($assessment), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
	echo "<br />\n";
        echo $lang['AdditionalNotes'] .": \n";
        echo "<br />\n";
        echo "<textarea name=\"notes\" cols=\"50\" rows=\"3\" id=\"notes\">" . htmlentities(stripslashes($notes), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"update_details\" class=\"btn btn-primary\">". $lang['Update'] ."</button>\n";
        echo "</div>\n";
}

/*************************************
 * FUNCTION: VIEW MITIGATION DETAILS *
 *************************************/
function view_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
{
	global $lang;
	
        echo "<h4>". $lang['Mitigation'] ."</h4>\n";
        echo $lang['MitigationDate'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_date\" id=\"mitigation_date\" size=\"50\" value=\"" . $mitigation_date . "\" title=\"" . $mitigation_date . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['PlanningStrategy'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"planning_strategy\" id=\"planning_strategy\" size=\"50\" value=\"" . get_name_by_value("planning_strategy", $planning_strategy) . "\" title=\"" . get_name_by_value("planning_strategy", $planning_strategy) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['MitigationEffort'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_effort\" id=\"mitigation_effort\" size=\"50\" value=\"" . get_name_by_value("mitigation_effort", $mitigation_effort) . "\" title=\"" . get_name_by_value("mitigation_effort", $mitigation_effort) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['CurrentSolution'] .": \n";
        echo "<br />\n";
        echo "<textarea style=\"cursor: default;\" name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\" title=\"" . htmlentities(stripslashes($current_solution), ENT_QUOTES, 'UTF-8', false) . "\" disabled=\"disabled\">" . htmlentities(stripslashes($current_solution), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
        echo "<br />\n";
        echo $lang['SecurityRequirements'] .": \n";
        echo "<br />\n";
        echo "<textarea style=\"cursor: default;\" name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\" title=\"" . htmlentities(stripslashes($security_requirements), ENT_QUOTES, 'UTF-8', false) . "\" disabled=\"disabled\">" . htmlentities(stripslashes($security_requirements), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
        echo "<br />\n";
        echo $lang['SecurityRecommendations'] .": \n";
        echo "<br />\n";
        echo "<textarea style=\"cursor: default;\" name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\" title=\"" . htmlentities(stripslashes($security_recommendations), ENT_QUOTES, 'UTF-8', false) . "\" disabled=\"disabled\">" . htmlentities(stripslashes($security_recommendations), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";

        // If the page is the view.php page
        if (basename($_SERVER['PHP_SELF']) == "index.php")
        {
                // Give the option to edit the mitigation details
	        echo "<div class=\"form-actions\">\n";
        	echo "<button type=\"submit\" name=\"edit_mitigation\" class=\"btn btn-primary\">". $lang['EditMitigation'] ."</button>\n";
        	echo "</div>\n";
        }
}

/*************************************
 * FUNCTION: EDIT MITIGATION DETAILS *
 *************************************/
function edit_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
{
	global $lang;
	
	echo "<h4>". $lang['Mitigation'] ."</h4>\n";
        echo $lang['MitigationDate'] .": \n";
	echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_date\" id=\"mitigation_date\" size=\"50\" value=\"" . $mitigation_date . "\" title=\"" . $mitigation_date . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['PlanningStrategy'] .": \n";
        echo "<br />\n";
        create_dropdown("planning_strategy", $planning_strategy);
        echo "<br />\n";
        echo $lang['MitigationEffort'] .": \n";
        echo "<br />\n";
        create_dropdown("mitigation_effort", $mitigation_effort);
        echo "<br />\n";
        echo $lang['CurrentSolution'] .": \n";
        echo "<br />\n";
        echo "<textarea name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\">" . htmlentities(stripslashes($current_solution), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
	echo "<br />\n";
        echo $lang['SecurityRequirements'] .": \n";
        echo "<br />\n";
        echo "<textarea name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\">" . htmlentities(stripslashes($security_requirements), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
	echo "<br />\n";
        echo $lang['SecurityRecommendations'] .": \n";
        echo "<br />\n";
        echo "<textarea name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\">" . htmlentities(stripslashes($security_recommendations), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"update_mitigation\" class=\"btn btn-primary\">". $lang['Update'] ."</button>\n";
        echo "</div>\n";
}

/*********************************
 * FUNCTION: view_review_details *
 *********************************/
function view_review_details($id, $review_date, $reviewer, $review, $next_step, $next_review, $comments)
{
	global $lang;
	
	echo "<h4>". $lang['LastReview'] ."</h4>\n";
        echo $lang['ReviewDate'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"review_date\" id=\"review_date\" size=\"50\" value=\"" . $review_date . "\" title=\"" . $review_date . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['Reviewer'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"reviewer\" id=\"reviewer\" size=\"50\" value=\"" . get_name_by_value("user", $reviewer) . "\" title=\"" . get_name_by_value("user", $reviewer) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['Review'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"review\" id=\"review\" size=\"50\" value=\"" . get_name_by_value("review", $review) . "\" title=\"" . get_name_by_value("review", $review) . "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['NextStep'] .": \n";
        echo "<br />\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"next_step\" id=\"next_step\" size=\"50\" value=\"" . get_name_by_value("next_step", $next_step) . "\" title=\"" . get_name_by_value("next_step", $next_step) . "\" disabled=\"disabled\" />\n";
	echo "<br />\n";
	echo $lang['NextReviewDate'] .": \n";
	echo "<br />\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"next_review\" id=\"next_review\" size=\"50\" value=\"" . $next_review . "\" title=\"" . $next_review. "\" disabled=\"disabled\" />\n";
        echo "<br />\n";
        echo $lang['Comments'] .": \n";
        echo "<br />\n";
        echo "<textarea style=\"cursor: default;\" name=\"comments\" cols=\"50\" rows=\"3\" id=\"comments\" title=\"" . htmlentities(stripslashes($comments), ENT_QUOTES, 'UTF-8', false) . "\" disabled=\"disabled\">" . htmlentities(stripslashes($comments), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
        echo "<p><a href=\"index.php?module=1&page=11&id=".$id."\">". $lang['ViewAllReviews'] ."</a></p>";
}

/****************************************
 * FUNCTION: edit_mitigation_submission *
 ****************************************/
function edit_mitigation_submission($planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
{
	global $lang;
	
	echo "<h4>". $lang['SubmitRiskMitigation'] ."</h4>\n";
        echo "<form name=\"submit_mitigation\" method=\"post\" action=\"\">\n";
	
        echo $lang['PlanningStrategy'] .": \n";
        echo "<br />\n";
	create_dropdown("planning_strategy", $planning_strategy, NULL, true);
        echo "<br />\n";
        echo $lang['MitigationEffort'] .": \n";
        echo "<br />\n";
	create_dropdown("mitigation_effort", $mitigation_effort, NULL, true);
        echo "<br />\n";
        echo $lang['CurrentSolution'] .": \n";
        echo "<br />\n";
        echo "<textarea name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\">" . htmlentities(stripslashes($current_solution), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
        echo "<br />\n";
        echo $lang['SecurityRequirements'] .": \n";
        echo "<br />\n";
        echo "<textarea name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\">" . htmlentities(stripslashes($security_requirements), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
        echo "<br />\n";
        echo $lang['SecurityRecommendations'] .": \n";
        echo "<br />\n";
        echo "<textarea name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\">" . htmlentities(stripslashes($security_recommendations), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
        echo "<br />\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">". $lang['Submit'] ."</button>\n";
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
	
	echo "<h4>". $lang['SubmitManagementReview'] ."</h4>\n";
        echo "<form name=\"submit_management_review\" method=\"post\" action=\"\">\n";
        echo $lang['Review'] .": \n";
        echo "<br />\n";
	create_dropdown("review", $review, NULL, true);
        echo "<br />\n";
        echo $lang['NextStep'] .": \n";
        echo "<br />\n";
	create_dropdown("next_step", $next_step, NULL, true);
	echo "<br />\n";
        echo $lang['Comments'] .": \n";
        echo "<br />\n";
        echo "<textarea name=\"comments\" cols=\"50\" rows=\"3\" id=\"comments\">" . htmlentities(stripslashes($comments), ENT_QUOTES, 'UTF-8', false) . "</textarea>\n";
	echo "<br />\n";
	echo $lang['BasedOnTheCurrentRiskScore'] . $next_review . "<br />\n";
	echo $lang['WouldYouLikeToUseADifferentDate'] . "&nbsp;<input type=\"radio\" name=\"custom_date\" value=\"no\" onclick=\"hideNextReview()\" checked />&nbsp" . $lang['No'] . "&nbsp;<input type=\"radio\" name=\"custom_date\" value=\"yes\" onclick=\"showNextReview()\" />&nbsp" . $lang['Yes'] . "<br />\n";
	echo "<div id=\"nextreview\" style=\"display:none;\">\n";
	echo "<br />\n";
	echo $lang['NextReviewDate'] .": \n";
	echo "<br />\n";
	echo "<input type=\"text\" name=\"next_review\" value=\"" . $next_review . "\" />\n";
	echo "<br />\n";
	echo "</div>\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">". $lang['Submit'] ."</button>\n";
        echo "<input class=\"btn\" value=\"". $lang['Reset'] ."\" type=\"reset\">\n";
        echo "</div>\n";
        echo "</form>\n";
}

/********************************
 * FUNCTION: edit_classic_score *
 ********************************/
function edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact)
{
	echo "<h4>Update Classic Score</h4>\n";
	echo "<form name=\"update_classic\" method=\"post\" action=\"\">\n";
        echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

        echo "<tr>\n";
        echo "<td width=\"150\" height=\"10\">Current Likelihood:</td>\n";
	echo "<td width=\"125\">\n";
        create_dropdown("likelihood", $CLASSIC_likelihood, NULL, false);
	echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('likelihoodHelp');\"></td>\n";
        echo "<td rowspan=\"3\" style=\"vertical-align:top;\">\n";
        view_classic_help();
        echo "</td>\n";
	echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\" height=\"10\">Current Impact:</td>\n";
        echo "<td width=\"125\">\n";
        create_dropdown("impact", $CLASSIC_impact, NULL, false);
        echo "</td>\n";
	echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('impactHelp');\"></td>\n";
        echo "</tr>\n";

	echo "<tr><td colspan=\"3\">&nbsp;</td></tr>\n";

	echo "</table>\n";

        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"update_classic\" class=\"btn btn-primary\">Update</button>\n";
        echo "</div>\n";
        echo "</form>\n";
}

/*****************************
 * FUNCTION: edit_cvss_score *
 *****************************/
function edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement)
{
        echo "<h4>Update CVSS Score</h4>\n";
        echo "<form name=\"update_cvss\" method=\"post\" action=\"\">\n";
	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
        echo "<td colspan=\"4\"><b><u>Base Score Metrics</u></b></td>\n";
        echo "<td rowspan=\"19\" style=\"vertical-align:top;\">\n";
        view_cvss_help();
        echo "</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"200\">Attack Vector:</td>\n";
        echo "<td width=\"125\">\n";
        create_cvss_dropdown("AccessVector", $AccessVector, false);
        echo "</td>\n";
	echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AccessVectorHelp');\"></td>\n";
	echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Attack Complexity:</td>\n";
        echo "<td>\n";
        create_cvss_dropdown("AccessComplexity", $AccessComplexity, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AccessComplexityHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Authentication:</td>\n";
        echo "<td>\n";
        create_cvss_dropdown("Authentication", $Authentication, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AuthenticationHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Confidentiality Impact:</td>\n";
        echo "<td>\n";
        create_cvss_dropdown("ConfImpact", $ConfImpact, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ConfImpactHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Integrity Impact:</td>\n";
        echo "<td>\n";
        create_cvss_dropdown("IntegImpact", $IntegImpact, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('IntegImpactHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Availability Impact:</td>\n";
        echo "<td>\n";
        create_cvss_dropdown("AvailImpact", $AvailImpact, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AvailImpactHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\">&nbsp;</td>\n";
        echo "</tr>\n";

	echo "<tr>\n";
        echo "<td colspan=\"4\"><b><u>Temporal Score Metrics</u></b></td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Exploitability:</td>\n";
        echo "<td>\n";
        create_cvss_dropdown("Exploitability", $Exploitability, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ExploitabilityHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Remediation Level:</td>\n";
        echo "<td>\n";
        create_cvss_dropdown("RemediationLevel", $RemediationLevel, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('RemediationLevelHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Report Confidence:</td>\n";
        echo "<td>\n";
        create_cvss_dropdown("ReportConfidence", $ReportConfidence, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ReportConfidenceHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\">&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\"><b><u>Environmental Score Metrics</u></b></td>\n";
	echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Collateral Damage Potential:</td>\n";
        echo "<td>\n";
        create_cvss_dropdown("CollateralDamagePotential", $CollateralDamagePotential, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('CollateralDamagePotentialHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Target Distribution:</td>\n";
        echo "<td>\n";
	create_cvss_dropdown("TargetDistribution", $TargetDistribution, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('TargetDistributionHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Confidentiality Requirement:</td>\n";
        echo "<td>\n";
	create_cvss_dropdown("ConfidentialityRequirement", $ConfidentialityRequirement, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ConfidentialityRequirementHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Integrity Requirement:</td>\n";
        echo "<td>\n";
	create_cvss_dropdown("IntegrityRequirement", $IntegrityRequirement, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('IntegrityRequirementHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Availability Requirement:</td>\n";
        echo "<td>\n";
	create_cvss_dropdown("AvailabilityRequirement", $AvailabilityRequirement, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AvailabilityRequirementHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

	echo "</table>\n";

        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"update_cvss\" class=\"btn btn-primary\">Update</button>\n";
        echo "</div>\n";
        echo "</form>\n";
}

/******************************
 * FUNCTION: edit_dread_score *
 ******************************/
function edit_dread_score($DamagePotential, $Reproducibility, $Exploitability, $AffectedUsers, $Discoverability)
{
        echo "<h4>Update DREAD Score</h4>\n";
        echo "<form name=\"update_dread\" method=\"post\" action=\"\">\n";
        echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Damage Potential:</td>\n";
        echo "<td width=\"75\">\n";
	create_numeric_dropdown("DamagePotential", $DamagePotential, false);
        echo "</td>\n";
	echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('DamagePotentialHelp');\"></td>\n";
	echo "<td rowspan=\"5\" style=\"vertical-align:top;\">\n";
	view_dread_help();
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
        echo "<td width=\"150\">Reproducibility:</td>\n";
        echo "<td width=\"75\">\n";
	create_numeric_dropdown("Reproducibility", $Reproducibility, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ReproducibilityHelp');\"></td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Exploitability:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("Exploitability", $Exploitability, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ExploitabilityHelp');\"></td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Affected Users:</td>\n";
        echo "<td width=\"75\">\n";
	create_numeric_dropdown("AffectedUsers", $AffectedUsers, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AffectedUsersHelp');\"></td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Discoverability:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("Discoverability", $Discoverability, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('DiscoverabilityHelp');\"></td>\n";
        echo "</tr>\n";

        echo "</table>\n";

        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"update_dread\" class=\"btn btn-primary\">Update</button>\n";
        echo "</div>\n";
        echo "</form>\n";
}

/******************************
 * FUNCTION: edit_owasp_score *
 ******************************/
function edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation)
{
	echo "<h4>Update OWASP Score</h4>\n";
        echo "<form name=\"update_owasp\" method=\"post\" action=\"\">\n";
        echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\"><b><u>Threat Agent Factors</u></b></td>\n";
        echo "<td rowspan=\"20\" style=\"vertical-align:top;\">\n";
        view_owasp_help();
        echo "</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Skill Level:</td>\n";
        echo "<td width=\"75\">\n";
	create_numeric_dropdown("SkillLevel", $OWASPSkillLevel, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('SkillLevelHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Motive:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("Motive", $OWASPMotive, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('MotiveHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Opportunity:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("Opportunity", $OWASPOpportunity, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('OpportunityHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Size:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("Size", $OWASPSize, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('SizeHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\">&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\"><b><u>Vulnerability Factors</u></b></td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Ease of Discovery:</td>\n";
        echo "<td width=\"75\">\n";
	create_numeric_dropdown("EaseOfDiscovery", $OWASPEaseOfDiscovery, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('EaseOfDiscoveryHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Ease of Exploit:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("EaseOfExploit", $OWASPEaseOfExploit, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('EaseOfExploitHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Awareness:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("Awareness", $OWASPAwareness, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AwarenessHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Intrusion Detection:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("IntrusionDetection", $OWASPIntrusionDetection, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('IntrusionDetectionHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\">&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\"><b><u>Technical Impact</u></b></td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Loss of Confidentiality:</td>\n";
        echo "<td width=\"75\">\n";
	create_numeric_dropdown("LossOfConfidentiality", $OWASPLossOfConfidentiality, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('EaseOfDiscoveryHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Loss of Integrity:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("LossOfIntegrity", $OWASPLossOfIntegrity, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('LossOfIntegrityHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Loss of Availaibility:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("LossOfAvailability", $OWASPLossOfAvailability, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('LossOfAvailabilityHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Loss of Accountability:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("LossOfAccountability", $OWASPLossOfAccountability, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('LossOfAccountabilityHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\">&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\"><b><u>Business Impact</u></b></td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Financial Damage:</td>\n";
        echo "<td width=\"75\">\n";
	create_numeric_dropdown("FinancialDamage", $OWASPFinancialDamage, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('EaseOfDiscoveryHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">ReputationDamage:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("ReputationDamage", $OWASPReputationDamage, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ReputationDamageHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Non-Compliance:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("NonCompliance", $OWASPNonCompliance, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('NonComplianceHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Privacy Violation:</td>\n";
        echo "<td width=\"75\">\n";
        create_numeric_dropdown("PrivacyViolation", $OWASPPrivacyViolation, false);
        echo "</td>\n";
        echo "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('PrivacyViolationHelp');\"></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "</table>\n";

        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"update_owasp\" class=\"btn btn-primary\">Update</button>\n";
        echo "</div>\n";
        echo "</form>\n";
}

/*******************************
 * FUNCTION: edit_custom_score *
 *******************************/
function edit_custom_score($custom)
{
        echo "<h4>Update Custom Score</h4>\n";
        echo "<form name=\"update_custom\" method=\"post\" action=\"\">\n";
        echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

        echo "<tr>\n";
        echo "<td width=\"165\" height=\"10\">Manually Entered Value:</td>\n";
        echo "<td width=\"60\"><input type=\"text\" name=\"Custom\" id=\"Custom\" style=\"width:30px;\" value=\"" . $custom . "\"></td>\n";
	echo "<td>(Must be a numeric value between 0 and 10)</td>\n";
        echo "</tr>\n";

        echo "</table>\n";

        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"update_custom\" class=\"btn btn-primary\">Update</button>\n";
        echo "</div>\n";
        echo "</form>\n";
}

/***********************************
 * FUNCTION: CLASSIC SCORING TABLE *
 ***********************************/
function classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact)
{
	global $lang;
	
        echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

        echo "<tr>\n";
        echo "<td colspan=\"3\"><h4>". $lang['ClassicRiskScoring'] ."</h4></td>\n";
        echo "<td colspan=\"1\" style=\"vertical-align:top;\">\n";
        echo "<div class=\"btn-group pull-right\">\n";
        echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">". $lang['RiskScoringActions'] ."<span class=\"caret\"></span></a>\n";
        echo "<ul class=\"dropdown-menu\">\n";
        echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">". $lang['UpdateClassicScore'] ."</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=2\">". $lang['ScoreBy'] ." CVSS</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=3\">". $lang['ScoreBy'] ." DREAD</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=4\">". $lang['ScoreBy'] ." OWASP</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=5\">". $lang['ScoreBy'] ." Custom</a></li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        echo "</td>\n";
        echo "</tr>\n";


        echo "<tr>\n";
        echo "<td width=\"90\">". $lang['Likelihood'] .":</td>\n";
        echo "<td width=\"25\">[ " . $CLASSIC_likelihood . " ]</td>\n";
        echo "<td>" . get_name_by_value("likelihood", $CLASSIC_likelihood) . "</td>\n";
	echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"90\">". $lang['Impact'] .":</td>\n";
        echo "<td width=\"25\">[ " . $CLASSIC_impact . " ]</td>\n";
        echo "<td>" . get_name_by_value("impact", $CLASSIC_impact) . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr><td colspan=\"4\">&nbsp;</td></tr>\n";

        if (get_setting("risk_model") == 1)
        {
        	echo "<tr>\n";
        	echo "<td colspan=\"3\"><b>". $lang['RISKClassicExp1'] ." x ( 10 / 35 ) = " . $calculated_risk . "</b></td>\n";
        	echo "</tr>\n";
        }
        else if (get_setting("risk_model") == 2)
        {
                echo "<tr>\n";
                echo "<td colspan=\"3\"><b>". $lang['RISKClassicExp2'] ." x ( 10 / 30 ) = " . $calculated_risk . "</b></td>\n";
                echo "</tr>\n";
        }
        else if (get_setting("risk_model") == 3)
        {
                echo "<tr>\n";
                echo "<td colspan=\"3\"><b>". $lang['RISKClassicExp3'] ." x ( 10 / 25 ) = " . $calculated_risk . "</b></td>\n";
                echo "</tr>\n";
        }
        else if (get_setting("risk_model") == 4)
        {
                echo "<tr>\n";
                echo "<td colspan=\"3\"><b>". $lang['RISKClassicExp4'] ." x ( 10 / 30 ) = " . $calculated_risk . "</b></td>\n";
                echo "</tr>\n";
        }
        else if (get_setting("risk_model") == 5)
        {
                echo "<tr>\n";
                echo "<td colspan=\"3\"><b>". $lang['RISKClassicExp5'] ." x ( 10 / 35 ) = " . $calculated_risk . "</b></td>\n";
                echo "</tr>\n";
        }

        echo "</table>\n";
}

/********************************
 * FUNCTION: CVSS SCORING TABLE *
 ********************************/
function cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement)
{
        echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\"><h4>CVSS Risk Scoring</h4></td>\n";
        echo "<td colspan=\"3\" style=\"vertical-align:top;\">\n";
        echo "<div class=\"btn-group pull-right\">\n";
        echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">Risk Scoring Actions<span class=\"caret\"></span></a>\n";
        echo "<ul class=\"dropdown-menu\">\n";
        echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">Update CVSS Score</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=1\">Score by Classic</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=3\">Score by DREAD</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=4\">Score by OWASP</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=5\">Score by Custom</a></li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        echo "</td>\n";
        echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"7\">Base Vector: AV:" . $AccessVector . "/AC:" . $AccessComplexity . "/Au:" . $Authentication . "/C:" . $ConfImpact . "/I:" . $IntegImpact . "/A:" . $AvailImpact . "</td>\n";
	echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"7\">Temporal Vector: E:" . $Exploitability . "/RL:" . $RemediationLevel . "/RC:" . $ReportConfidence . "</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"7\">Environmental Vector: CDP:" . $CollateralDamagePotential . "/TD:" . $TargetDistribution . "/CR:" . $ConfidentialityRequirement . "/IR:" . $IntegrityRequirement . "/AR:" . $AvailabilityRequirement . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

	echo "<tr><td colspan=\"8\">&nbsp;</td></tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"2\"><b><u>Base Score Metrics</u></b></td>\n";
	echo "<td colspan=\"2\"><b><u>Temporal Score Metrics</u></b></td>\n";
        echo "<td colspan=\"2\"><b><u>Environmental Score Metrics</u></b></td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Attack Vector:</td>\n";
	echo "<td width=\"100\">" . get_cvss_name("AccessVector", $AccessVector) . "</td>\n";
        echo "<td width=\"150\">Exploitability:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("Exploitability", $Exploitability) . "</td>\n";
        echo "<td width=\"200\">Collateral Damage Potential:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("CollateralDamagePotential", $CollateralDamagePotential) . "</td>\n";
	echo "<td>&nbsp</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Attack Complexity:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("AccessComplexity", $AccessComplexity) . "</td>\n";
        echo "<td width=\"150\">Remediation Level:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("RemediationLevel", $RemediationLevel) . "</td>\n";
        echo "<td width=\"200\">Target Distribution:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("TargetDistribution", $TargetDistribution) . "</td>\n";
        echo "<td>&nbsp</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Authentication:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("Authentication", $Authentication) . "</td>\n";
        echo "<td width=\"150\">Report Confidence:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("ReportConfidence", $ReportConfidence) . "</td>\n";
        echo "<td width=\"200\">Confidentiality Requirement:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("ConfidentialityRequirement", $ConfidentialityRequirement) . "</td>\n";
        echo "<td>&nbsp</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Confidentiality Impact:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("ConfImpact", $ConfImpact) . "</td>\n";
        echo "<td width=\"150\">&nbsp;</td>\n";
	echo "<td width=\"100\">&nbsp</td>\n";
        echo "<td width=\"200\">Integrity Requirement:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("IntegrityRequirement", $IntegrityRequirement) . "</td>\n";
        echo "<td>&nbsp</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Integrity Impact:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("IntegImpact", $IntegImpact) . "</td>\n";
        echo "<td width=\"150\">&nbsp;</td>\n";
        echo "<td width=\"100\">&nbsp</td>\n";
        echo "<td width=\"200\">Availability Requirement:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("AvailabilityRequirement", $AvailabilityRequirement) . "</td>\n";
        echo "<td>&nbsp</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Availability Impact:</td>\n";
        echo "<td width=\"100\">" . get_cvss_name("AvailImpact", $AvailImpact) . "</td>\n";
        echo "<td width=\"150\">&nbsp;</td>\n";
        echo "<td width=\"100\">&nbsp</td>\n";
        echo "<td width=\"200\">&nbsp;</td>\n";
        echo "<td width=\"100\">&nbsp</td>\n";
        echo "<td>&nbsp</td>\n";
        echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"7\">&nbsp;</td>\n";
	echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"7\">Full details of CVSS Version 2.0 scoring can be found <a href=\"http://www.first.org/cvss/cvss-guide.html\" target=\"_blank\">here</a>.</td>\n";
        echo "</tr>\n";

        echo "</table>\n";
}

/*********************************
 * FUNCTION: DREAD SCORING TABLE *
 *********************************/
function dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability)
{
        echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

        echo "<tr>\n";
        echo "<td colspan=\"2\"><h4>DREAD Risk Scoring</h4></td>\n";
        echo "<td colspan=\"1\" style=\"vertical-align:top;\">\n";
        echo "<div class=\"btn-group pull-right\">\n";
        echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">Risk Scoring Actions<span class=\"caret\"></span></a>\n";
        echo "<ul class=\"dropdown-menu\">\n";
        echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">Update DREAD Score</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=1\">Score by Classic</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=2\">Score by CVSS</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=4\">Score by OWASP</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=5\">Score by Custom</a></li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        echo "</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Damage Potential:</td>\n";
        echo "<td>" . $DREADDamagePotential . "</td>\n";
	echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Reproducibility:</td>\n";
        echo "<td>" . $DREADReproducibility . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Exploitability:</td>\n";
        echo "<td>" . $DREADExploitability . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Affected Users:</td>\n";
        echo "<td>" . $DREADAffectedUsers . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"150\">Discoverability:</td>\n";
        echo "<td>" . $DREADDiscoverability . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"3\">&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"3\"><b>RISK = ( " . $DREADDamagePotential . " + " . $DREADReproducibility . " + " . $DREADExploitability . " + " . $DREADAffectedUsers . " + " . $DREADDiscoverability . " ) / 5 = " . $calculated_risk . "</b></td>\n";
        echo "</tr>\n";

        echo "</table>\n";
}

/*********************************
 * FUNCTION: OWASP SCORING TABLE *
 *********************************/
function owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation)
{
        echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\"><h4>OWASP Risk Scoring</h4></td>\n";
        echo "<td colspan=\"5\" style=\"vertical-align:top;\">\n";
        echo "<div class=\"btn-group pull-right\">\n";
        echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">Risk Scoring Actions<span class=\"caret\"></span></a>\n";
        echo "<ul class=\"dropdown-menu\">\n";
        echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">Update OWASP Score</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=1\">Score by Classic</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=2\">Score by CVSS</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=3\">Score by DREAD</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=5\">Score by Custom</a></li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        echo "</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"2\"><b><u>Threat Agent Factors</u></b></td>\n";
        echo "<td colspan=\"2\"><b><u>Vulnerability Factors</u></b></td>\n";
        echo "<td colspan=\"2\"><b><u>Technical Impact</u></b></td>\n";
        echo "<td colspan=\"2\"><b><u>Business Impact</u></b></td>\n";
	echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Skill Level:</td>\n";
        echo "<td width=\"50\">" . $OWASPSkillLevel . "</td>\n";
        echo "<td width=\"175\">Ease of Discovery:</td>\n";
        echo "<td width=\"50\">" . $OWASPEaseOfDiscovery . "</td>\n";
        echo "<td width=\"175\">Loss of Confidentiality:</td>\n";
        echo "<td width=\"50\">" . $OWASPLossOfConfidentiality . "</td>\n";
        echo "<td width=\"175\">Financial Damage:</td>\n";
        echo "<td width=\"50\">" . $OWASPFinancialDamage . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"125\">Motive:</td>\n";
        echo "<td width=\"10\">" . $OWASPMotive . "</td>\n";
        echo "<td width=\"125\">Ease of Exploit:</td>\n";
        echo "<td width=\"10\">" . $OWASPEaseOfExploit . "</td>\n";
        echo "<td width=\"125\">Loss of Integrity:</td>\n";
        echo "<td width=\"10\">" . $OWASPLossOfIntegrity . "</td>\n";
        echo "<td width=\"125\">Reputation Damage:</td>\n";
        echo "<td width=\"10\">" . $OWASPReputationDamage . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"125\">Opportunity:</td>\n";
        echo "<td width=\"10\">" . $OWASPOpportunity . "</td>\n";
        echo "<td width=\"125\">Awareness:</td>\n";
        echo "<td width=\"10\">" . $OWASPAwareness . "</td>\n";
        echo "<td width=\"125\">Loss Of Availability:</td>\n";
        echo "<td width=\"10\">" . $OWASPLossOfAvailability . "</td>\n";
        echo "<td width=\"125\">Non-Compliance:</td>\n";
        echo "<td width=\"10\">" . $OWASPNonCompliance . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"125\">Size:</td>\n";
        echo "<td width=\"10\">" . $OWASPSize . "</td>\n";
        echo "<td width=\"125\">Intrusion Detection:</td>\n";
        echo "<td width=\"10\">" . $OWASPIntrusionDetection . "</td>\n";
        echo "<td width=\"125\">Loss of Accountability:</td>\n";
        echo "<td width=\"10\">" . $OWASPLossOfAccountability . "</td>\n";
        echo "<td width=\"125\">Privacy Violation:</td>\n";
        echo "<td width=\"10\">" . $OWASPPrivacyViolation . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"9\">&nbsp;</td>\n";
        echo "<tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\"><b><u>Likelihood</u></b></td>\n";
        echo "<td colspan=\"4\"><b><u>Impact</u></b></td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\">Threat Agent Factors = ( " . $OWASPSkillLevel . " + " . $OWASPMotive . " + " . $OWASPOpportunity . " + " . $OWASPSize . " ) / 4</td>\n";
        echo "<td colspan=\"4\">Technical Impact = ( " . $OWASPLossOfConfidentiality . " + " . $OWASPLossOfIntegrity . " + " . $OWASPLossOfAvailability . " + " . $OWASPLossOfAccountability . " ) / 4</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"4\">Vulnerability Factors = ( " . $OWASPEaseOfDiscovery . " + " . $OWASPEaseOfExploit . " + " . $OWASPAwareness . " + " . $OWASPIntrusionDetection . " ) / 4</td>\n";
        echo "<td colspan=\"4\">Business Impact = ( " . $OWASPFinancialDamage . " + " . $OWASPReputationDamage . " + " . $OWASPNonCompliance . " + " . $OWASPPrivacyViolation . " ) / 4</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"9\">&nbsp;</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td colspan=\"9\">Full details of the OWASP Risk Rating Methodology can be found <a href=\"https://www.owasp.org/index.php/OWASP_Risk_Rating_Methodology\" target=\"_blank\">here</a>.</td>\n";
        echo "</tr>\n";

        echo "</table>\n";
}

/**********************************
 * FUNCTION: CUSTOM SCORING TABLE *
 **********************************/
function custom_scoring_table($id, $custom)
{
        echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

        echo "<tr>\n";
        echo "<td colspan=\"2\"><h4>Custom Risk Scoring</h4></td>\n";
        echo "<td colspan=\"1\" style=\"vertical-align:top;\">\n";
        echo "<div class=\"btn-group pull-right\">\n";
        echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">Risk Scoring Actions<span class=\"caret\"></span></a>\n";
        echo "<ul class=\"dropdown-menu\">\n";
        echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">Update Custom Score</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=1\">Score by Classic</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=2\">Score by CVSS</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=3\">Score by DREAD</a></li>\n";
        echo "<li><a href=\"index.php?module=1&page=5&id=".$id."&scoring_method=4\">Score by OWASP</a></li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        echo "</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<td width=\"175\">Manually Entered Value:</td>\n";
        echo "<td width=\"10\">" . $custom . "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<tr>\n";

        echo "</table>\n";
}

/*******************************
 * FUNCTION: VIEW CLASSIC HELP *
 *******************************/
function view_classic_help()
{
        echo "<div id=\"divHelp\" style=\"width:100%;overflow:auto\"></div>\n";

        echo "<div id=\"likelihoodHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">\n";
        echo "<p><b>Remote:</b> May only occur in exceptional circumstances.</p>\n";
	echo "<p><b>Unlikely:</b> Expected to occur in a few circumstances.</p>\n";
	echo "<p><b>Credible:</b> Expected to occur in some circumstances.</p>\n";
	echo "<p><b>Likely:</b> Expected to occur in many circumstances.</p>\n";
	echo "<p><b>Almost Certain:</b> Expected to occur frequently and in most circumstances.</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"impactHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">\n";
	echo "<p><b>Insignificant:</b> No impact on service, no impact on reputation, complaint unlikely, or litigation risk remote.</p>\n";
	echo "<p><b>Minor:</b> Slight impact on service, slight impact on reputation, complaint possible, or litigation possible.</p>\n";
	echo "<p><b>Moderate:</b> Some service disruption, potential for adverse publicity (avoidable with careful handling), complaint probable, or litigation probably.</p>\n";
	echo "<p><b>Major:</b> Service disrupted, adverse publicity not avoidable (local media), complaint probably, or litigation probable.</p>\n";
	echo "<p><b>Extreme/Catastrophic:</b> Service interrupted for significant time, major adverse publicity not avoidable (national media), major litigation expected, resignation of senior management and board, or loss of benficiary confidence.</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<script language=\"javascript\">\n";
        echo "function showHelp(divId) {\n";
        echo "getRef(\"divHelp\").innerHTML=getRef(divId).innerHTML;\n";
        echo "}\n";
        echo "function hideHelp() {\n";
        echo "getRef(\"divHelp\").innerHTML=\"\";\n";
        echo "}\n";
        echo "</script>\n";
}

/*****************************
 * FUNCTION: VIEW OWASP HELP *
 *****************************/
function view_owasp_help()
{
        echo "<div id=\"divHelp\" style=\"width:100%;overflow:auto\"></div>\n";

        echo "<div id=\"SkillLevelHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How technically skilled is this group of threat agents?</b></p>\n";
        echo "<p>1 = Security Penetration Skills</p>\n";
        echo "<p>4 = Network and Programming Skills</p>\n";
        echo "<p>6 = Advanced Computer User</p>\n";
        echo "<p>7 = Some Technical Skills</p>\n";
        echo "<p>9 = No Technical Skills</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"MotiveHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How motivated is this group of threat agents to find and exploit this vulnerability?</b></p>\n";
        echo "<p>1 = Low or No Reward</p>\n";
        echo "<p>4 = Possible Reward</p>\n";
        echo "<p>9 = High Reward</p>\n";
        echo "</td>\n";           
        echo "</tr>\n";         
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"OpportunityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>What resources and opportunity are required for this group of threat agents to find and exploit this vulnerability?</b></p>\n";
        echo "<p>0 = Full Access or Expensive Resources Required</p>\n";
        echo "<p>4 = Special Access or Resources Required</p>\n";
        echo "<p>7 = Some Access or Resources Required</p>\n";
        echo "<p>9 = No Access or Resources Required</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"SizeHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How large is this group of threat agents?</b></p>\n";
        echo "<p>2 = Developers</p>\n";
        echo "<p>2 = System Administrators</p>\n";
        echo "<p>4 = Intranet Users</p>\n";
        echo "<p>5 = Partners</p>\n";
        echo "<p>6 = Authenticated Users</p>\n";
        echo "<p>9 = Anonymous Internet Users</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"EaseOfDiscoveryHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How easy is it for this group of threat agents to discover this vulnerability?</b></p>\n";
        echo "<p>1 = Practically Impossible</p>\n";
        echo "<p>3 = Difficult</p>\n";
        echo "<p>7 = Easy</p>\n";
        echo "<p>9 = Automated Tools Available</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"EaseOfExploitHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How easy is it for this group of threat agents to actually exploit this vulnerability?</b></p>\n";
        echo "<p>1 = Theoretical</p>\n";
        echo "<p>3 = Difficult</p>\n";
        echo "<p>5 = Easy</p>\n";
        echo "<p>9 = Automated Tools Available</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"AwarenessHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How well known is this vulnerability to this group of threat agents?</b></p>\n";
        echo "<p>1 = Unknown</p>\n";
        echo "<p>4 = Hidden</p>\n";
        echo "<p>6 = Obvious</p>\n";
        echo "<p>9 = Public Knowledge</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"IntrusionDetectionHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How likely is an exploit to be detected?</b></p>\n";
        echo "<p>1 = Active Detection in Application</p>\n";
        echo "<p>3 = Logged and Reviewed</p>\n";
        echo "<p>8 = Logged Without Review</p>\n";
        echo "<p>9 = Not Logged</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"LossOfConfidentialityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How much data could be disclosed and how sensitive is it?</b></p>\n";
        echo "<p>2 = Minimal Non-Sensitive Data Disclosed</p>\n";
        echo "<p>6 = Minimal Critical Data Disclosed</p>\n";
        echo "<p>7 = Extensive Critical Data Disclosed</p>\n";
        echo "<p>9 = All Data Disclosed</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"LossOfIntegrityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How much data could be corrupted and how damaged is it?</b></p>\n";
        echo "<p>1 = Minimal Slightly Corrupt Data</p>\n";
        echo "<p>3 = Minimal Seriously Corrupt Data</p>\n";
        echo "<p>5 = Extensive Slightly Corrupt Data</p>\n";
        echo "<p>7 = Extensive Seriously Corrupt Data</p>\n";
        echo "<p>9 = All Data Totally Corrupt</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"LossOfAvailabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How much service could be lost and how vital is it?</b></p>\n";
        echo "<p>1 = Minimal Secondary Services Interrupted</p>\n";
        echo "<p>5 = Minimal Primary Services Interrupted</p>\n";
        echo "<p>5 = Extensive Secondary Services Interrupted</p>\n";
        echo "<p>7 = Extensive Primary Services Interrupted</p>\n";
        echo "<p>9 = All Services Completely Lost</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"LossOfAccountabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>Are the threat agents' actions traceable to an individual?</b></p>\n";
        echo "<p>1 = Fully Traceable</p>\n";
        echo "<p>7 = Possibly Traceable</p>\n";
        echo "<p>9 = Completely Anonymous</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"FinancialDamageHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How much financial damage will result from an exploit?</b></p>\n";
        echo "<p>1 = Less than the Cost to Fix the Vulnerability</p>\n";
        echo "<p>3 = Minor Effect on Annual Profit</p>\n";
        echo "<p>7 = Significant Effect on Annual Profit</p>\n";
        echo "<p>9 = Bankruptcy</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"ReputationDamageHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>Would an exploit result in reputation damage that would harm the business?</b></p>\n";
        echo "<p>1 = Minimal Damage</p>\n";
        echo "<p>4 = Loss of Major Accounts</p>\n";
        echo "<p>5 = Loss of Goodwill</p>\n";
        echo "<p>9 = Brand Damage</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"NonComplianceHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How much exposure does non-compliance introduce?</b></p>\n";
        echo "<p>2 = Minor Violation</p>\n";
        echo "<p>5 = Clear Violation</p>\n";
        echo "<p>7 = High Profile Violation</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"PrivacyViolationHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";          
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How much personally identifiable information could be disclosed?</b></p>\n";
        echo "<p>3 = One Individual</p>\n";
        echo "<p>5 = Hundreds of People</p>\n";
        echo "<p>7 = Thousands of People</p>\n";
        echo "<p>9 = Millions of People</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<script language=\"javascript\">\n";
        echo "function showHelp(divId) {\n";
        echo "getRef(\"divHelp\").innerHTML=getRef(divId).innerHTML;\n";
        echo "}\n";
        echo "function hideHelp() {\n";
        echo "getRef(\"divHelp\").innerHTML=\"\";\n";
        echo "}\n";
        echo "</script>\n";
}

/*****************************
 * FUNCTION: VIEW CVSS HELP *
 *****************************/
function view_cvss_help()
{
        echo "<div id=\"divHelp\" style=\"width:100%;overflow:auto\"></div>\n";

        echo "<div id=\"AccessVectorHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
	echo "<td class=\"cal-head\"><b>Local</b></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
        echo "<td class=\"cal-text\">A vulnerability exploitable with only local access requires the attacker to have either physical access to the vulnerable system or a local (shell) account.  Examples of locally exploitable vulnerabilities are peripheral attacks such as Firewire/USB DMA attacks, and local privilege escalations (e.g., sudo).</td>\n";
	echo "</tr>\n";
	echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Adjacent Network</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">A vulnerability exploitable with adjacent network access requires the attacker to have access to either the broadcast or collision domain of the vulnerable software.  Examples of local networks include local IP subnet, Bluetooth, IEEE 802.11, and local Ethernet segment.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Network</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">A vulnerability exploitable with network access means the vulnerable software is bound to the network stack and the attacker does not require local network access or local access.  Such a vulnerability is often termed \"remotely exploitable\".  An example of a network attack is an RPC buffer overflow.</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"AccessComplexityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>High</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Specialized access conditions exist. For example:<ul><li>In most configurations, the attacking party must already have elevated privileges or spoof additional systems in addition to the attacking system (e.g., DNS hijacking).</li><li>The attack depends on social engineering methods that would be easily detected by knowledgeable people. For example, the victim must perform several suspicious or atypical actions.</li><li>The vulnerable configuration is seen very rarely in practice.</li><li>If a race condition exists, the window is very narrow.</li></ul></td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Medium</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">The access conditions are somewhat specialized; the following are examples:<ul><li>The attacking party is limited to a group of systems or users at some level of authorization, possibly untrusted.</li><li>Some information must be gathered before a successful attack can be launched.</li><li>The affected configuration is non-default, and is not commonly configured (e.g., a vulnerability present when a server performs user account authentication via a specific scheme, but not present for another authentication scheme).</li><li>The attack requires a small amount of social engineering that might occasionally fool cautious users (e.g., phishing attacks that modify a web browsers status bar to show a false link, having to be on someones buddy list before sending an IM exploit).</li></ul></td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Low</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Specialized access conditions or extenuating circumstances do not exist. The following are examples:<ul><li>The affected product typically requires access to a wide range of systems and users, possibly anonymous and untrusted (e.g., Internet-facing web or mail server).</li><li>The affected configuration is default or ubiquitous.</li><li>The attack can be performed manually and requires little skill or additional information gathering.</li><li>The race condition is a lazy one (i.e., it is technically a race but easily winnable).</li></ul></td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"AuthenticationHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>None</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Authentication is not required to exploit the vulnerability.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Single Instance</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">The vulnerability requires an attacker to be logged into the system (such as at a command line or via a desktop session or web interface).</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Multiple Instances</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";        
        echo "<td class=\"cal-text\">Exploiting the vulnerability requires that the attacker authenticate two or more times, even if the same credentials are used each time. An example is an attacker authenticating to an operating system in addition to providing credentials to access an application hosted on that system.</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"ConfImpactHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>None</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is no impact to the confidentiality of the system.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Partial</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is considerable informational disclosure. Access to some system files is possible, but the attacker does not have control over what is obtained, or the scope of the loss is constrained. An example is a vulnerability that divulges only certain tables in a database.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Complete</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is total information disclosure, resulting in all system files being revealed. The attacker is able to read all of the system's data (memory, files, etc.)</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"IntegImpactHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>None</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";        
        echo "<td class=\"cal-text\">There is no impact to the integrity of the system.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Partial</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Modification of some system files or information is possible, but the attacker does not have control over what can be modified, or the scope of what the attacker can affect is limited. For example, system or application files may be overwritten or modified, but either the attacker has no control over which files are affected or the attacker can modify files within only a limited context or scope.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Complete</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is a total compromise of system integrity. There is a complete loss of system protection,resulting in the entire system being compromised. The attacker is able to modify any files on the target system.</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"AvailImpactHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>None</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is no impact to the availability of the system.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Partial</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is reduced performance or interruptions in resource availability. An example is a network-based flood attack that permits a limited number of successful connections to an Internet service.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Complete</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is a total shutdown of the affected resource. The attacker can render the resource completely unavailable.</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"ExploitabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Unproven that exploit exists</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">No exploit code is available, or an exploit is entirely theoretical.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Proof of concept code</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Proof-of-concept exploit code or an attack demonstration that is not practical for most systems is available. The code or technique is not functional in all situations and may require substantial modification by a skilled attacker.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Functional exploit exists</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Functional exploit code is available. The code works in most situations where the vulnerability exists.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Widespread</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Either the vulnerability is exploitable by functional mobile autonomous code, or no exploit is required (manual trigger) and details are widely available. The code works in every situation, or is actively being delivered via a mobile autonomous agent (such as a worm or virus).</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"RemediationLevelHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Official Fix</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">A complete vendor solution is available. Either the vendor has issued an official patch, or an upgrade is available.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Temporary Fix</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is an official but temporary fix available. This includes instances where the vendor issues a temporary hotfix, tool, or workaround.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Workaround</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is an unofficial, non-vendor solution available. In some cases, users of the affected technology will create a patch of their own or provide steps to work around or otherwise mitigate the vulnerability.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Unavailable</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is either no solution available or it is impossible to apply.</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"ReportConfidenceHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Not Confirmed</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is a single unconfirmed source or possibly multiple conflicting reports. There is little confidence in the validity of the reports. An example is a rumor that surfaces from the hacker underground.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Uncorroborated</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There are multiple non-official sources, possibly including independent security companies or research organizations. At this point there may be conflicting technical details or some other lingering ambiguity.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Confirmed</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">The vulnerability has been acknowledged by the vendor or author of the affected technology. The vulnerability may also be ?Confirmed? when its existence is confirmed from an external event such as publication of functional or proof-of-concept exploit code or widespread exploitation.</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"CollateralDamagePotentialHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>None</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">There is no potential for loss of life, physical assets, productivity or revenue.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Low</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">A successful exploit of this vulnerability may result in slight physical or property damage. Or, there may be a slight loss of revenue or productivity to the organization.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Low-Medium</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">A successful exploit of this vulnerability may result in moderate physical or property damage. Or, there may be a moderate loss of revenue or productivity to the organization.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Medium-High</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">A successful exploit of this vulnerability may result in significant physical or property damage or loss. Or, there may be a significant loss of revenue or productivity.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>High</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">A successful exploit of this vulnerability may result in catastrophic physical or property damage and loss. Or, there may be a catastrophic loss of revenue or productivity.</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"TargetDistributionHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>None</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">No target systems exist, or targets are so highly specialized that they only exist in a laboratory setting. Effectively 0% of the environment is at risk.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Low</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Targets exist inside the environment, but on a small scale. Between 1% - 25% of the total environment is at risk.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Medium</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Targets exist inside the environment, but on a medium scale. Between 26% - 75% of the total environment is at risk.</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>High</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Targets exist inside the environment on a considerable scale. Between 76% - 100% of the total environment is considered at risk.</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"ConfidentialityRequirementHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Low</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Loss of confidentiality is likely to have only a limited adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Medium</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Loss of confidentiality is likely to have a serious adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>High</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Loss of confidentiality is likely to have a catastrophic adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"IntegrityRequirementHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Low</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Loss of integrity is likely to have only a limited adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Medium</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Loss of integrity is likely to have a serious adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>High</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Loss of integrity is likely to have a catastrophic adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"AvailabilityRequirementHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Low</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Loss of availability is likely to have only a limited adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>Medium</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Loss of availability is likely to have a serious adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
        echo "</tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-head\"><b>High</b></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">Loss of availability is likely to have a catastrophic adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<script language=\"javascript\">\n";
        echo "function showHelp(divId) {\n";
        echo "getRef(\"divHelp\").innerHTML=getRef(divId).innerHTML;\n";
        echo "}\n";
        echo "function hideHelp() {\n";
        echo "getRef(\"divHelp\").innerHTML=\"\";\n";
        echo "}\n";
        echo "</script>\n";
}

/*****************************
 * FUNCTION: VIEW DREAD HELP *
 *****************************/
function view_dread_help()
{
	echo "<div id=\"divHelp\" style=\"width:100%;overflow:auto\"></div>\n";

        echo "<div id=\"DamagePotentialHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>If a threat exploit occurs, how much damage will be caused?</b></p>\n";
        echo "<p>0 = Nothing</p>\n";
        echo "<p>5 = Individual user data is compromised or affected.</p>\n";
        echo "<p>10 = Complete system or data destruction</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"ReproducibilityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How easy is it to reproduce the threat exploit?</b></p>\n";
        echo "<p>0 = Very hard or impossible, even for administrators of the application.</p>\n";
        echo "<p>5 = One or two steps required, may need to be an authorized user.</p>\n";
        echo "<p>10 = Just a web browser and the address bar is sufficient, without authentication.</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"ExploitabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>What is needed to exploit this threat?</b></p>\n";
        echo "<p>0 = Advanced programming and networking knowledge, with custom or advanced attack tools.</p>\n";
        echo "<p>5 = Malware exists on the Internet, or an exploit is easily performed, using available attack tools.</p>\n";
        echo "<p>10 = Just a web browser</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"AffectedUsersHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How many users will be affected?</b></p>\n";
        echo "<p>0 = None</p>\n";
        echo "<p>5 = Some users, but not all</p>\n";
        echo "<p>10 = All users</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<div id=\"DiscoverabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<td class=\"cal-text\">\n";
        echo "<br /><p><b>How easy is it to discover this threat?</b></p>\n";
        echo "<p>0 = Very hard to impossible; requires source code or administrative access.</p>\n";
        echo "<p>5 = Can figure it out by guessing or by monitoring network traces.</p>\n";
        echo "<p>9 = Details of faults like this are already in the public domain and can be easily discovered using a search engine.</p>\n";
        echo "<p>10 = The information is visible in the web browser address bar or in a form.</p>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        echo "<script language=\"javascript\">\n";
        echo "function showHelp(divId) {\n";
        echo "getRef(\"divHelp\").innerHTML=getRef(divId).innerHTML;\n";
        echo "}\n";
        echo "function hideHelp() {\n";
        echo "getRef(\"divHelp\").innerHTML=\"\";\n";
        echo "}\n";
        echo "</script>\n";
}
