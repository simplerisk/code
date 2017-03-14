<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/assessments.php'));

/****************************
* FUNCTION: VIEW TOP TABLE *
****************************/
function view_top_table($id, $calculated_risk, $subject, $status, $show_details = false)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$subject = try_decrypt($subject);


	        echo "<div class=\"score--wrapper\">";
			    echo "<div class=\"score \" style=\"background-color: ". $escaper->escapeHtml(get_risk_color($calculated_risk)) ."\">";
			        echo "<span>".$escaper->escapeHtml($calculated_risk)."</span>".$escaper->escapeHtml(get_risk_level_name($calculated_risk));
                echo "</div>";
			echo "</div>";

	        echo "<div class=\"details--wrapper\">";
                
                echo "<div class=\"row-fluid\">";
	                echo "<div class=\"span3\"><label>ID #: <span class=\"large-text risk-id\">".$escaper->escapeHtml($id)."</span></label></div>";
	                echo "<div class=\"span5\"><label>Status: <span class=\"large-text status-text\">".$escaper->escapeHtml($status)."</span></label></div>";
	                echo "<div class=\"span4\">";

							echo "<div class=\"btn-group pull-right\">\n";
								echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">". $escaper->escapeHtml($lang['RiskActions']) ."<span class=\"caret\"></span></a>\n";
								echo "<ul class=\"dropdown-menu\">\n";

								// If the risk is closed, offer to reopen
								if ($status == "Closed") { echo "<li><a class='reopen-risk' href=\"reopen.php?id=".$escaper->escapeHtml($id)."\">". $escaper->escapeHtml($lang['ReopenRisk']) ."</a></li>\n"; }
								// Otherwise, offer to close
								else{
									// If the user has permission to close risks
									if (isset($_SESSION["close_risks"]) && $_SESSION["close_risks"] == 1) { echo "<li><a class='close-risk' href=\"close.php?id=".$escaper->escapeHtml($id)."\">". $escaper->escapeHtml($lang['CloseRisk']) ."</a></li>\n";							}
								}

								echo "<li><a class='edit-risk' href=\"view.php?action=editdetail&id=" . $escaper->escapeHtml($id) . "\">". $escaper->escapeHtml($lang['EditRisk']) ."</a></li>\n";
								echo "<li><a class='edit-mitigation' href=\"mitigate.php?id=" . $escaper->escapeHtml($id) . "\">". $escaper->escapeHtml($lang['PlanAMitigation']) ."</a></li>\n";
								echo "<li><a class='perform-review' href=\"mgmt_review.php?id=" . $escaper->escapeHtml($id) . "\">". $escaper->escapeHtml($lang['PerformAReview']) ."</a></li>\n";
								echo "<li><a class='change-status' href=\"status.php?id=" . $escaper->escapeHtml($id) . "\">" . $escaper->escapeHtml($lang['ChangeStatus']) . "</a></li>\n";
								//echo "<li><a href=\"comment.php?id=" . $escaper->escapeHtml($id) . "\">". $escaper->escapeHtml($lang['AddAComment']) ."</a></li>\n";
								echo "<li><a href=\"#comment\" class='add-comment-menu'>". $escaper->escapeHtml($lang['AddAComment']) ."</a></li>\n";
								echo "<li><a class='printable-veiw' href=\"print_view.php?id=" . $escaper->escapeHtml($id) . "\" target=\"_blank\">". $escaper->escapeHtml($lang['PrintableView']) ."</a></li>\n";
								echo "</ul>\n";
							echo "</div>\n";


					echo "</div>";
                echo "</div>";

                echo "<div class=\"row-fluid border-top\">";
	                echo "<div class=\"span12\"><div id=\"static-subject\" class='static-subject'><label>Subject : <span class=\"large-text\">".$escaper->escapeHtml($subject)."</span> <div id=\"edit-subject\" class='edit-subject-btn' style=\"display:inline;margin:0 0 0 10px; font-size:20px;\"><i class=\"fa fa-pencil-square-o\" aria-hidden=\"true\"></i></div></label></div>";
	                    echo "<form name=\"details\" method=\"post\" action=\"\">";
	                        echo "<div class=\"edit-subject row-fluid\">";
	                            echo "<div class=\"span9\">";
	                                echo "<input type=\"text\" name=\"subject\" value=\"".$escaper->escapeHtml($subject)."\" style=\"max-width:none;\"/>";
	                                echo "<input type=\"hidden\" name=\"riskid\" value=\"".$escaper->escapeHtml($id)."\"/>";
	                            echo "</div>";
	                            echo "<div class=\"span3\">";
	                                echo "<a href=\"/management/view.php?id=" . $escaper->escapeHtml($id) . "&type=0\" class=\"btn cancel-edit-subject \" style=\"margin:0 5px 0 0;\" >Cancel</a>";
			                        echo "<button type=\"submit\" name=\"update_subject\" class=\"btn btn-danger\">Save</button>";
			                    echo "</div>
                                </div>";
			            echo "</form>";
	                  echo "</div>";
	            echo "</div>";
			echo "</div>";

}

/**********************************
* FUNCTION: VIEW PRINT TOP TABLE *
**********************************/
function view_print_top_table($id, $calculated_risk, $subject, $status)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$subject = try_decrypt($subject);

	echo "<table width=\"100%\" cellpadding=\"10\" cellspacing=\"0\" style=\"border:none;\">\n";
	echo "<tr>\n";
	echo "<td width=\"100\" valign=\"middle\" halign=\"center\">\n";

	echo "<table width=\"100\" height=\"100\" border=\"10\" class=\"" . $escaper->escapeHtml(get_risk_color($calculated_risk)) . "\">\n";
	echo "<tr>\n";
	echo "<td valign=\"middle\" halign=\"center\">\n";
	echo "<center>\n";
	echo "<font size=\"72\">" . $escaper->escapeHtml($calculated_risk) . "</font><br />\n";
	echo "(". $escaper->escapeHtml(get_risk_level_name($calculated_risk)) . ")\n";
	echo "</center>\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	echo "</td>\n";
	echo "<td valign=\"left\" halign=\"center\">\n";

	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";
	echo "<tr>\n";
	echo "<td width=\"100\"><h4>". $escaper->escapeHtml($lang['RiskId']) .":</h4></td>\n";
	echo "<td><h4>" . $escaper->escapeHtml($id) . "</h4></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td width=\"100\"><h4>". $escaper->escapeHtml($lang['Subject']) .":</h4></td>\n";
	echo "<td><h4>" . $escaper->escapeHtml($subject) . "</h4></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td width=\"100\"><h4>". $escaper->escapeHtml($lang['Status']) .":</h4></td>\n";
	echo "<td><h4>" . $escaper->escapeHtml($status) . "</h4></td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	echo "</td>\n";
	echo "</table>\n";
}

/*******************************
* FUNCTION: VIEW RISK DETAILS *
*******************************/
function view_risk_details($id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$subject = try_decrypt($subject);
	$assessment = try_decrypt($assessment);
	$notes = try_decrypt($notes);

	//echo "<h4>". $escaper->escapeHtml($lang['Details']) ."</h4>\n";
        // If the page is the view.php page
	if (basename($_SERVER['PHP_SELF']) == "view.php")
	{
		// Give the option to edit the risk details
		echo "<div class=\"tabs--action\">";
		echo "<button type=\"submit\" name=\"edit_details\" class=\"btn\">". $escaper->escapeHtml($lang['EditDetails']) ."</button>\n";
		echo "</div>\n";
	}

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5\">\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['SubmissionDate']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"submission_date\"  size=\"50\" value=\"" . $escaper->escapeHtml($submission_date) . "\" title=\"" . $escaper->escapeHtml($submission_date) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Category']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"category\" id=\"category\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['SiteLocation']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"location\" id=\"location\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("location", $location)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("location", $location)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"wrap-text span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['ExternalReferenceId']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo " <input style=\"cursor: default;\" type=\"text\" name=\"reference_id\" id=\"reference_id\" size=\"20\" value=\"" . $escaper->escapeHtml($reference_id) . "\" title=\"" . $escaper->escapeHtml($reference_id) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['ControlRegulation']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"regulation\" id=\"regulation\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("regulation", $regulation)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("regulation", $regulation)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['ControlNumber']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo " <input style=\"cursor: default;\" type=\"text\" name=\"control_number\" id=\"control_number\" size=\"20\" value=\"" . $escaper->escapeHtml($control_number) . "\" title=\"" . $escaper->escapeHtml($control_number) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['AffectedAssets']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<textarea style=\"cursor: default;\" type=\"text\" name=\"assets\" class=\"assets\" id=\"assets\" size=\"50\" cols=\"50\" rows=\"3\" title=\"" . $escaper->escapeHtml(get_list_of_assets($id, false)) . "\" disabled=\"disabled\">". $escaper->escapeHtml(get_list_of_assets($id, false)) ."</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Technology']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"technology\" id=\"technology\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("technology", $technology)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("technology", $technology)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Team']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"team\" id=\"team\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("team", $team)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("team", $team)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Owner']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"owner\" id=\"owner\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['OwnersManager']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"manager\" id=\"manager\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";

  echo "<div class=\"span5\">\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['SubmittedBy']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"submitted_by\" id=\"submitted_by\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $submitted_by)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $submitted_by)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['RiskSource']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"source\" id=\"source\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("source", $source)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("source", $source)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['RiskScoringMethod']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"scoringMethod\" id=\"scoringMethod\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("scoring_methods", $scoring_method)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("scoring_methods", $scoring_method)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
    if($scoring_method == "1"){
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span5 text-right\">\n";
        echo $escaper->escapeHtml($lang['CurrentLikelihood']) .": \n";
        echo "</div>\n";
        echo "<div class=\"span7\">\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"currentLikelihood\" id=\"currentLikelihood\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("likelihood", $CLASSIC_likelihood)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("likelihood", $CLASSIC_likelihood)) . "\" disabled=\"disabled\" />\n";
        echo "</div>\n";
        echo "</div>\n";
            echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span5 text-right\">\n";
        echo $escaper->escapeHtml($lang['CurrentImpact']) .": \n";
        echo "</div>\n";
        echo "<div class=\"span7\">\n";
        echo "<input style=\"cursor: default;\" type=\"text\" name=\"currentLikelihood\" id=\"currentLikelihood\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("impact", $CLASSIC_impact)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("impact", $CLASSIC_impact)) . "\" disabled=\"disabled\" />\n";
        echo "</div>\n";
        echo "</div>\n";
    }
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['RiskAssessment']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<textarea style=\"cursor: default;\" name=\"assessment\" cols=\"50\" rows=\"3\" id=\"assessment\" title=\"" . $escaper->escapeHtml($assessment) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($assessment) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['AdditionalNotes']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<textarea style=\"cursor: default;\" name=\"notes\" cols=\"50\" rows=\"3\" id=\"notes\" title=\"" . $escaper->escapeHtml($notes) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($notes) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"wrap-text span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['SupportingDocumentation']) . ": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	supporting_documentation($id, "view", 1);
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "</div>\n";
}

/*************************************
* FUNCTION: VIEW PRINT RISK DETAILS *
*************************************/
function view_print_risk_details($id, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$subject = try_decrypt($subject);
	$assessment = try_decrypt($assessment);
	$notes = try_decrypt($notes);

	echo "<h4>" . $escaper->escapeHtml($lang['Details']) . "</h4>\n";
	echo "<table border=\"1\" width=\"100%\" cellspacing=\"10\" cellpadding=\"10\">\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['SubmissionDate']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($submission_date) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Subject']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($subject) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['ExternalReferenceId']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($reference_id) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['ControlRegulation']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("regulation", $regulation)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['ControlNumber']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($control_number) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['SiteLocation']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("location", $location)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Category']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Team']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("team", $team)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Technology']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("technology", $technology)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Owner']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['OwnersManager']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['RiskAssessment']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($assessment) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['AdditionalNotes']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($notes) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['AffectedAssets']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_list_of_assets($id, false)) . "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
}


function risk_score_method_html($scoring_method="1", $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamagePotential="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkillLevel="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPEaseOfDiscovery="10", $OWASPEaseOfExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $custom=""){
    global $escaper;
    global $lang;
    
    $html = "
        <div class='row-fluid' >
            <div class='span5 text-right'>". $escaper->escapeHtml($lang['RiskScoringMethod']) .":</div>
            <div class='span7'>"
            .create_dropdown("scoring_methods", $scoring_method, "scoring_method", false, false, true).
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
                <div class='span7'>". create_dropdown('likelihood', $CLASSIC_likelihood, NULL, true, false, true) ."</div>
            </div>
            <div class='row-fluid'>
                <div class='span5 text-right'>". $escaper->escapeHtml($lang['CurrentImpact']) .":</div>
                <div class='span7'>". create_dropdown('impact', $CLASSIC_impact, NULL, true, false, true) ."</div>
            </div>
        </div>
        <div id='cvss' style='display: ". ($scoring_method == 2 ? "block" : "none") .";' class='cvss-holder'>
            <table width='100%'>
                <tr>
                    <td width='197px'>&nbsp;</td>
                    <td><p><input type='button' name='cvssSubmit' id='cvssSubmit' value='Score Using CVSS' /></p></td>
                </tr>
            </table>
            <input type='hidden' name='AccessVector' id='AccessVector' value='{$AccessVector}' />
            <input type='hidden' name='AccessComplexity' id='AccessComplexity' value='{$AccessComplexity}' />
            <input type='hidden' name='Authentication' id='Authentication' value='{$Authentication}' />
            <input type='hidden' name='ConfImpact' id='ConfImpact' value='{$ConfImpact}' />
            <input type='hidden' name='IntegImpact' id='IntegImpact' value='{$IntegImpact}' />
            <input type='hidden' name='AvailImpact' id='AvailImpact' value='{$AvailImpact}' />
            <input type='hidden' name='Exploitability' id='Exploitability' value='{$Exploitability}' />
            <input type='hidden' name='RemediationLevel' id='RemediationLevel' value='{$RemediationLevel}' />
            <input type='hidden' name='ReportConfidence' id='ReportConfidence' value='{$ReportConfidence}' />
            <input type='hidden' name='CollateralDamagePotential' id='CollateralDamagePotential' value='{$CollateralDamagePotential}' />
            <input type='hidden' name='TargetDistribution' id='TargetDistribution' value='{$TargetDistribution}' />
            <input type='hidden' name='ConfidentialityRequirement' id='ConfidentialityRequirement' value='{$ConfidentialityRequirement}' />
            <input type='hidden' name='IntegrityRequirement' id='IntegrityRequirement' value='{$IntegrityRequirement}' />
            <input type='hidden' name='AvailabilityRequirement' id='AvailabilityRequirement' value='{$AvailabilityRequirement}' />
        </div>
        <div id='dread' style='display: ". ($scoring_method == 3 ? "block" : "none") .";' class='dread-holder'>
            <table width='100%'>
                <tr>
                    <td width='197px'>&nbsp;</td>
                    <td><p><input type='button' name='dreadSubmit' id='dreadSubmit' value='Score Using DREAD' onclick='javascript: popupdread();' /></p></td>
                </tr>
            </table>
            <input type='hidden' name='DREADDamage' id='DREADDamage' value='{$DREADDamagePotential}' />
            <input type='hidden' name='DREADReproducibility' id='DREADReproducibility' value='{$DREADReproducibility}' />
            <input type='hidden' name='DREADExploitability' id='DREADExploitability' value='{$DREADExploitability}' />
            <input type='hidden' name='DREADAffectedUsers' id='DREADAffectedUsers' value='{$DREADAffectedUsers}' />
            <input type='hidden' name='DREADDiscoverability' id='DREADDiscoverability' value='{$DREADDiscoverability}' />
        </div>
        <div id='owasp' style='display: ". ($scoring_method == 4 ? "block" : "none") .";' class='owasp-holder'>
            <table width='100%'>
                <tr>
                    <td width='197px'>&nbsp;</td>
                    <td><p><input type='button' name='owaspSubmit' id='owaspSubmit' value='Score Using OWASP' onclick='javascript: popupowasp();' /></p></td>
                </tr>
            </table>
            <input type='hidden' name='OWASPSkillLevel' id='OWASPSkillLevel' value='{$OWASPSkillLevel}' />
            <input type='hidden' name='OWASPMotive' id='OWASPMotive' value='{$OWASPMotive}' />
            <input type='hidden' name='OWASPOpportunity' id='OWASPOpportunity' value='{$OWASPOpportunity}' />
            <input type='hidden' name='OWASPSize' id='OWASPSize' value='{$OWASPSize}' />
            <input type='hidden' name='OWASPEaseOfDiscovery' id='OWASPEaseOfDiscovery' value='{$OWASPEaseOfDiscovery}' />
            <input type='hidden' name='OWASPEaseOfExploit' id='OWASPEaseOfExploit' value='{$OWASPEaseOfExploit}' />
            <input type='hidden' name='OWASPAwareness' id='OWASPAwareness' value='{$OWASPAwareness}' />
            <input type='hidden' name='OWASPIntrusionDetection' id='OWASPIntrusionDetection' value='{$OWASPIntrusionDetection}' />
            <input type='hidden' name='OWASPLossOfConfidentiality' id='OWASPLossOfConfidentiality' value='{$OWASPLossOfConfidentiality}' />
            <input type='hidden' name='OWASPLossOfIntegrity' id='OWASPLossOfIntegrity' value='{$OWASPLossOfIntegrity}' />
            <input type='hidden' name='OWASPLossOfAvailability' id='OWASPLossOfAvailability' value='{$OWASPLossOfAvailability}' />
            <input type='hidden' name='OWASPLossOfAccountability' id='OWASPLossOfAccountability' value='{$OWASPLossOfAccountability}' />
            <input type='hidden' name='OWASPFinancialDamage' id='OWASPFinancialDamage' value='{$OWASPFinancialDamage}' />
            <input type='hidden' name='OWASPReputationDamage' id='OWASPReputationDamage' value='{$OWASPReputationDamage}' />
            <input type='hidden' name='OWASPNonCompliance' id='OWASPNonCompliance' value='{$OWASPNonCompliance}' />
            <input type='hidden' name='OWASPPrivacyViolation' id='OWASPPrivacyViolation' value='{$OWASPPrivacyViolation}' />
        </div>
        <div id='custom' style='display: ". ($scoring_method == 5 ? "block" : "none") .";' class='custom-holder'>
            <div class='row-fluid'>
                <div class='span5 text-right'>
                    ". $escaper->escapeHtml($lang['CustomValue']) .":
                </div>
                <div class='span7'>
                    <input type='text' name='Custom' id='Custom' value='{$custom}' /> 
                    <small>(Must be a numeric value between 0 and 10)</small>
                </div>
            </div>
        </div>
    
    ";
    
    echo $html;
}

/*******************************
* FUNCTION: EDIT RISK DETAILS *
*******************************/
function edit_risk_details($id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$subject = try_decrypt($subject);
	$assessment = try_decrypt($assessment);
	$notes = try_decrypt($notes);

	echo "<h4>". $escaper->escapeHtml($lang['Details']) ."</h4>\n";
        echo "<div class=\"tabs--action\">";
    echo "<a href='/management/view.php?id=$id&type=0' id=\"cancel_disable\" class=\"btn cancel-edit-details on-edit\">". $escaper->escapeHtml($lang['Cancel']) ."</a>\n";
	echo "<button type=\"submit\" name=\"update_details\" class=\"btn btn-danger save-details on-edit\">". $escaper->escapeHtml($lang['SaveDetails']) ."</button>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5\">\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['SubmissionDate']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"submission_date\"  size=\"50\" value=\"" . $escaper->escapeHtml($submission_date) . "\" title=\"" . $escaper->escapeHtml($submission_date) . "\" class=\"datepicker\" />\n"; 
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Category']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	create_dropdown("category", $category);
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['SiteLocation']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	create_dropdown("location", $location);
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"wrap-text span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['ExternalReferenceId']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input type=\"text\" name=\"reference_id\" id=\"reference_id\" class=\"active-textfield\" size=\"20\" value=\"" . $escaper->escapeHtml($reference_id) . "\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['ControlRegulation']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	create_dropdown("regulation", $regulation);
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['ControlNumber']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input type=\"text\" class=\"active-textfield\" name=\"control_number\" id=\"control_number\" size=\"20\" value=\"" . $escaper->escapeHtml($control_number) . "\" />\n";
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\" id=\"AffectedAssetsTitle\">\n";
	echo $escaper->escapeHtml($lang['AffectedAssets']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
        echo "<textarea name=\"assets\" class=\"active-textfield assets\" cols=\"50\" rows=\"3\" id=\"assets\">".$escaper->escapeHtml(get_list_of_assets($id)) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Technology']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	create_dropdown("technology", $technology);
	echo "</div>\n";
	echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Team']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	create_dropdown("team", $team);
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Owner']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	create_dropdown("user", $owner, "owner");
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['OwnersManager']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	create_dropdown("user", $manager, "manager");
	echo "</div>\n";
	echo "</div>\n";
        echo "</div>\n";


        echo "<div class=\"span5\">\n";
        
//        echo "<div class=\"row-fluid\">\n";
//	echo "<div class=\"span5 text-right\">\n";
//	echo $escaper->escapeHtml($lang['RiskScoringMethod']) .": \n";
//	echo "</div>\n";
//	echo "<div class=\"span7\">\n";
//        create_dropdown("scoring_methods", $scoring_method, "scoring_methods");
//	//echo "<input style=\"cursor: default;\" type=\"text\" name=\"scoringMethod\" id=\"scoringMethod\" size=\"50\" value=\"" . $escaper->escapeHtml($scoring_method) . "\" title=\"" . $escaper->escapeHtml($scoring_method) . "\" disabled=\"disabled\" />\n";
//	echo "</div>\n";
//	echo "</div>\n";
//        echo "<div class=\"row-fluid\">\n";
//	echo "<div class=\"span5 text-right\">\n";
//	echo $escaper->escapeHtml($lang['CurrentLikelihood']) .": \n";
//	echo "</div>\n";
//	echo "<div class=\"span7\">\n";
//        create_dropdown("likelihood", $CLASSIC_likelihood, "currentLikelihood");
//	//echo "<input style=\"cursor: default;\" type=\"text\" name=\"currentLikelihood\" id=\"currentLikelihood\" size=\"50\" value=\"" . $escaper->escapeHtml($CLASSIC_likelihood) . "\" title=\"" . $escaper->escapeHtml($CLASSIC_likelihood) . "\" disabled=\"disabled\" />\n";
//	echo "</div>\n";
//	echo "</div>\n";
//        echo "<div class=\"row-fluid\">\n";
//	echo "<div class=\"span5 text-right\">\n";
//	echo $escaper->escapeHtml($lang['CurrentImpact']) .": \n";
//	echo "</div>\n";
//	echo "<div class=\"span7\">\n";
//        create_dropdown("impact", $CLASSIC_impact, "CLASSIC_impact");
//	//echo "<input style=\"cursor: default;\" type=\"text\" name=\"CLASSIC_impact\" id=\"CLASSIC_impact\" size=\"50\" value=\"" . $escaper->escapeHtml($CLASSIC_impact) . "\" title=\"" . $escaper->escapeHtml($CLASSIC_impact) . "\" disabled=\"disabled\" />\n";
//	echo "</div>\n";
//	echo "</div>\n";


	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['RiskSource']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	create_dropdown("source", $source);
	echo "</div>\n";
	echo "</div>\n";

        risk_score_method_html($scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\" id=\"RiskAssessmentTitle\">\n";
	echo $escaper->escapeHtml($lang['RiskAssessment']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<textarea class=\"active-textfield\" name=\"assessment\" cols=\"50\" rows=\"3\" id=\"assessment\">" . $escaper->escapeHtml($assessment) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\" id=\"NotesTitle\">\n";
	echo $escaper->escapeHtml($lang['AdditionalNotes']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<textarea name=\"notes\" class=\"active-textfield\" cols=\"50\" rows=\"3\" id=\"notes\">" . $escaper->escapeHtml($notes) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"wrap-text span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['SupportingDocumentation']) . ": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	supporting_documentation($id, "edit", 1);
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";

}

/*************************************
* FUNCTION: VIEW MITIGATION DETAILS *
*************************************/
function view_mitigation_details($risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$current_solution = try_decrypt($current_solution);
	$security_requirements = try_decrypt($security_requirements);
	$security_recommendations = try_decrypt($security_recommendations);
        if (basename($_SERVER['PHP_SELF']) == "view.php")
	{
		// Give the option to edit the mitigation details
		echo "<div class=\"tabs--action\">";
		echo "<button type=\"submit\" name=\"edit_mitigation\" class=\"btn\">". $escaper->escapeHtml($lang['EditMitigation']) ."</button>\n";
		echo "</div>\n";
	}
	echo "<h4>". $escaper->escapeHtml($lang['Mitigation']) ."</h4>\n";
        // If the page is the view.php page

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5\">\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['MitigationDate']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_date\" id=\"mitigation_date\" size=\"50\" value=\"" . $escaper->escapeHtml($mitigation_date) . "\" title=\"" . $escaper->escapeHtml($mitigation_date) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['MitigationPlanning']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"planning_date\" id=\"planning_date\" size=\"50\" value=\"" . $escaper->escapeHtml($planning_date) . "\" title=\"" . $escaper->escapeHtml($planning_date) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['PlanningStrategy']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"planning_strategy\" id=\"planning_strategy\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['MitigationEffort']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_effort\" id=\"mitigation_effort\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['MitigationCost']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_cost\" id=\"mitigation_cost\" size=\"50\" value=\"" . $escaper->escapeHtml(get_asset_value_by_id($mitigation_cost)) . "\" title=\"" . $escaper->escapeHtml(get_asset_value_by_id($mitigation_cost)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['MitigationOwner']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_owner\" id=\"mitigation_owner\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $mitigation_owner)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['MitigationTeam']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_team\" id=\"mitigation_team\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("team", $mitigation_team)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"span5\">\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\" id=\"CurrentSolutionTitle\">\n";
	echo $escaper->escapeHtml($lang['CurrentSolution']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<textarea style=\"cursor: default;\" name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\" title=\"" . $escaper->escapeHtml($current_solution) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($current_solution) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\" id=\"t_a_title\">\n";
	echo $escaper->escapeHtml($lang['SecurityRequirements']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<textarea style=\"cursor: default;\" name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\" title=\"" . $escaper->escapeHtml($security_requirements) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($security_requirements) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\" id=\"t_a_title\">\n";
	echo $escaper->escapeHtml($lang['SecurityRecommendations']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<textarea style=\"cursor: default;\" name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\" title=\"" . $escaper->escapeHtml($security_recommendations) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($security_recommendations) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"wrap-text span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['SupportingDocumentation']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	supporting_documentation($risk_id, "view", 2);
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";


}

/*******************************************
* FUNCTION: VIEW PRINT MITIGATION DETAILS *
*******************************************/
function view_print_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$current_solution = try_decrypt($current_solution);
	$security_requirements = try_decrypt($security_requirements);
	$security_recommendations = try_decrypt($security_recommendations);

	echo "<h4>". $escaper->escapeHtml($lang['Mitigation']) ."</h4>\n";
	echo "<table border=\"1\" width=\"100%\" cellspacing=\"10\" cellpadding=\"10\">\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['MitigationDate']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($mitigation_date) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['PlanningStrategy']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['MitigationEffort']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['CurrentSolution']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($current_solution) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['SecurityRequirements']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($security_requirements) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['SecurityRecommendations']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($security_recommendations) . "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
}

/*************************************
* FUNCTION: EDIT MITIGATION DETAILS *
*************************************/
function edit_mitigation_details($risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date)
{
	global $lang;
	global $escaper;
	// Decrypt fields
	$current_solution = try_decrypt($current_solution);
	$security_requirements = try_decrypt($security_requirements);
	$security_recommendations = try_decrypt($security_recommendations);

	echo "
        <h4>". $escaper->escapeHtml($lang['Mitigation']) ."</h4>
        <div class=\"tabs--action\">
            <a href='/management/view.php?id=$risk_id&type=1' id=\"cancel_disable\" class=\"btn cancel-edit-mitigation\">". $escaper->escapeHtml($lang['Cancel']) ."</a>
	        <button type=\"submit\" name=\"update_mitigation\" class=\"btn btn-danger\">". $escaper->escapeHtml($lang['SaveMitigation']) ."</button>
	    </div>
	    <div class=\"row-fluid\">
	        <div class=\"span5\">
                <div class=\"row-fluid\">
                    <div class=\"span5 text-right\">"
                        .$escaper->escapeHtml($lang['MitigationDate']) .": \n
                    </div>
                    <div class=\"span7\">
                        <input style=\"cursor: default;\" type=\"text\" name=\"mitigation_date\" id=\"mitigation_date\" size=\"50\" value=\"" . $escaper->escapeHtml($mitigation_date) . "\" title=\"" . $escaper->escapeHtml($mitigation_date) . "\" disabled=\"disabled\" />
                    </div>
                </div>
	            <div class=\"row-fluid\">
	                <div class=\"span5 text-right\">"
	                    .$escaper->escapeHtml($lang['MitigationPlanning']) .": \n
	                </div>
	                <div class=\"span7\">
	                    <input type=\"text\" name=\"planning_date\"  size=\"50\" value=\"" . $escaper->escapeHtml($planning_date) . "\" class='datepicker active-textfield' />
	                </div>
	            </div>
	            <input type=\"hidden\" name=\"tab_type\" value=\"1\" />
	            <div class=\"row-fluid\">
	                <div class=\"span5 text-right\">"
	                    .$escaper->escapeHtml($lang['PlanningStrategy']) .": \n
	                </div>
	                <div class=\"span7\">";
	                    create_dropdown("planning_strategy", $planning_strategy);
	            echo "</div>
	            </div>
	            <div class=\"row-fluid\">
	                <div class=\"span5 text-right\">"
	                    .$escaper->escapeHtml($lang['MitigationEffort']) .": 
	                </div>
	                <div class=\"span7\">";
	                    create_dropdown("mitigation_effort", $mitigation_effort);
	            echo "</div>
	            </div>
	            <div class=\"row-fluid\">
	                <div class=\"span5 text-right\">"
	                    .$escaper->escapeHtml($lang['MitigationCost']) .": 
	                </div>
	                <div class=\"span7\">";
	                    echo create_asset_valuation_dropdown("mitigation_cost", $mitigation_cost);
	            echo "</div>
	            </div>
	            <div class=\"row-fluid\">
	                <div class=\"span5 text-right\">"
	                    .$escaper->escapeHtml($lang['MitigationOwner']) .": 
	                </div>
	                <div class=\"span7\">";
	                    create_dropdown("user", $mitigation_owner, "mitigation_owner", true);
	            echo "</div>
	            </div>
	            <div class=\"row-fluid\">
	                <div class=\"span5 text-right\">"
	                    .$escaper->escapeHtml($lang['MitigationTeam']) .": 
	                </div>
	                <div class=\"span7\">";
	                    create_dropdown("team", $mitigation_team, "mitigation_team", true);
	                echo "</div>
	            </div>
	        </div>
	        <div class=\"span5\">
	            <div class=\"row-fluid\">
	                <div class=\"span5 text-right\" id=\"CurrentSolutionTitle\">"
	                    .$escaper->escapeHtml($lang['CurrentSolution']) .": 
	                </div>
	                <div class=\"span7\">
	                    <textarea  class=\"active-textfield\" name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\" tabindex=\"1\">" . $escaper->escapeHtml($current_solution) . "</textarea>
	                </div>
	            </div>
	            <div class=\"row-fluid\">
	                <div class=\"span5 text-right\" id=\"SecurityRequirementsTitle\">"
	                    .$escaper->escapeHtml($lang['SecurityRequirements']) .": 
	                </div>
	                <div class=\"span7\">
	                    <textarea class=\"active-textfield\" name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\" tabindex=\"1\">" . $escaper->escapeHtml($security_requirements) . "</textarea>
	                </div>
	            </div>
	            <div class=\"row-fluid\">
	                <div class=\"span5 text-right\" id=\"SecurityRecommendationsTitle\">"
	                    .$escaper->escapeHtml($lang['SecurityRecommendations']) .": 
	                </div>
	                <div class=\"span7\">
	                    <textarea class=\"active-textfield\" name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\" tabindex=\"1\">" . $escaper->escapeHtml($security_recommendations) . "</textarea>
	                </div>
	            </div>
	            <div class=\"row-fluid\">
	                <div class=\"wrap-text span5 text-right\">"
	                    .$escaper->escapeHtml($lang['SupportingDocumentation']) . ": 
	                </div>
	                <div class=\"span7\">";
	                    supporting_documentation($risk_id, "edit", 2);
	                echo "</div>
	            </div>
	        </div>
	    </div>
    ";

}

/*********************************
* FUNCTION: view_review_details *
*********************************/
function view_review_details($id, $review_date, $reviewer, $review, $next_step, $next_review, $comments)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$comments = try_decrypt($comments);

	echo "<div class=\"tabs--action\">";
	echo "<button type=\"submit\" name=\"view_all_reviews\" class=\"btn all_reviews_btn\">" . $escaper->escapeHtml($lang['ViewAllReviews']) . "</button>\n";
	echo "<input type=\"hidden\" id=\"lang_last_review\" value=\"" . $escaper->escapeHtml($lang['LastReview']) . "\" />\n";
	echo "<input type=\"hidden\" id=\"lang_all_reviews\" value=\"" . $escaper->escapeHtml($lang['ViewAllReviews']) . "\" />\n";
	echo "</div>\n";


	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span7\">\n";
	
	echo "<div class=\"current_review\">\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo "<u>".$escaper->escapeHtml($lang['LastReview'])."</u>";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['ReviewDate']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"review_date\" id=\"review_date\" size=\"100\" value=\"" . $escaper->escapeHtml($review_date) . "\" title=\"" . $escaper->escapeHtml($review_date) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Reviewer']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"reviewer\" id=\"reviewer\" size=\"100\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $reviewer)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $reviewer)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Review']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"review\" id=\"review\" size=\"100\" value=\"" . $escaper->escapeHtml(get_name_by_value("review", $review)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("review", $review)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['NextStep']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"next_step\" id=\"next_step\" size=\"100\" value=\"" . $escaper->escapeHtml(get_name_by_value("next_step", $next_step)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("next_step", $next_step)) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['NextReviewDate']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<input style=\"cursor: default;\" type=\"text\" name=\"next_review\" id=\"next_review\" size=\"100\" value=\"" . $escaper->escapeHtml($next_review) . "\" title=\"" . $escaper->escapeHtml($next_review) . "\" disabled=\"disabled\" />\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Comments']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	echo "<textarea style=\"cursor: default;\" name=\"comments\" cols=\"100\" rows=\"3\" id=\"comments\" title=\"" . $escaper->escapeHtml($comments) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($comments) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "</div>\n";

	echo "</div>\n";

        echo "<div class=\"all_reviews\" style=\"display:none\">\n";

        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span5 text-right\">\n";
        echo "<u>".$escaper->escapeHtml($lang['ReviewHistory'])."</u>";
        echo "</div>\n";
        echo "<div class=\"span7\">\n";
        echo "</div>\n";
        echo "</div>\n";

	get_reviews($id);

	echo "</div>\n";

	echo "</div>\n";
	echo "</div>\n";
}

/***************************************
* FUNCTION: VIEW PRINT REVIEW DETAILS *
***************************************/
function view_print_review_details($id, $review_date, $reviewer, $review, $next_step, $next_review, $comments)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$comments = try_decrypt($comments);

	echo "<h4>". $escaper->escapeHtml($lang['LastReview']) ."</h4>\n";
	echo "<table border=\"1\" width=\"100%\" cellspacing=\"10\" cellpadding=\"10\">\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['ReviewDate']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($review_date) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Reviewer']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("user", $reviewer)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Review']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("review", $review)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['NextStep']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("next_step", $next_step)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['NextReviewDate']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($next_review) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Comments']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($comments) . "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
}

/****************************************
* FUNCTION: edit_mitigation_submission *
****************************************/
function edit_mitigation_submission($planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $id=0)
{
	global $lang;
	global $escaper;

	// Decrypt fields
	$current_solution = try_decrypt($current_solution);
	$security_requirements = try_decrypt($security_requirements);
	$security_recommendations = try_decrypt($security_recommendations);

	echo "<h4>". $escaper->escapeHtml($lang['SubmitRiskMitigation']) ."</h4>\n";
	echo "<form name=\"submit_mitigation\" id=\"submit_mitigation\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";

	echo "<div class=\"tabs--action\">";
	echo "<input class=\"btn\" id=\"cancel_disable\" value=\"Cancel\" type=\"reset\">\n";
	echo "<button type=\"submit\" id=\"save_mitigation\"    name=\"submit\" class=\"btn btn-danger\">Submit Mitigation</button>\n";
	echo "</div>";

	echo '<div class="row-fluid"><div class="span6"><div class="row-fluid">';
	echo '<div class="span5">';
	echo $escaper->escapeHtml($lang['PlanningStrategy']) .":</div>";
	echo '<div class="span7">';
	create_dropdown("planning_strategy", $planning_strategy, NULL, true);
	echo '</div></div><div class="row-fluid">';
    echo '<div class="span5">';
    echo $escaper->escapeHtml($lang['MitigationPlanning']) .":</div>";
    echo '<div class="span7">';
    echo "<input type=\"text\" name=\"planning_date\" id=\"planning_date\" size=\"50\" value=\"" . $escaper->escapeHtml($planning_date) . "\" class='datepicker active-textfield' />";
    echo '</div></div><div class="row-fluid">';
	echo '<div class="span5">';
	echo $escaper->escapeHtml($lang['MitigationEffort']) .":</div>";
	echo '<div class="span7">';
	create_dropdown("mitigation_effort", $mitigation_effort, NULL, true);
	echo '</div></div><div class="row-fluid">';
	echo '<div class="span5">';
	echo $escaper->escapeHtml($lang['MitigationCost']) .":</div>";
	echo '<div class="span7">';
	create_asset_valuation_dropdown("mitigation_cost", $mitigation_cost);
	echo '</div></div><div class="row-fluid">';
	echo '<div class="span5">';
	echo $escaper->escapeHtml($lang['MitigationOwner']) . ":</div>";
	echo '<div class="span7">';
	create_dropdown("user", $mitigation_owner, "mitigation_owner", true);
	echo "</div></div>";
	echo '<div class="row-fluid"><div class="span5">';
	echo $escaper->escapeHtml($lang['MitigationTeam']) . ":</div>";
	echo '<div class="span7">';
	create_dropdown("team", $mitigation_team, "mitigation_team", true);
	echo "</div></div></div>";
	echo '<div class="span6"><div class="row-fluid"><div class="span5" id="CurrentSolutionTitle">';
	echo $escaper->escapeHtml($lang['CurrentSolution']) .":</div>";
	echo '<div class="span7">';
	echo "<textarea name=\"current_solution\" id=\"active-textfield\" cols=\"50\" rows=\"3\" tabindex=\"1\">" . $escaper->escapeHtml($current_solution) . "</textarea></div>";
	echo '</div><div class="row-fluid"><div class="span5" id="SecurityRequirementsTitle">';
	echo $escaper->escapeHtml($lang['SecurityRequirements']) .":</div>";
	echo '<div class="span7">';
	echo "<textarea name=\"security_requirements\" id=\"active-textfield\" cols=\"50\" rows=\"3\" tabindex=\"1\">" . $escaper->escapeHtml($security_requirements) . "</textarea></div>";
	echo '</div><div class="row-fluid"><div class="span5" id="SecurityRecommendationsTitle">';
	echo $escaper->escapeHtml($lang['SecurityRecommendations']) .":</div>";
	echo '<div class="span7">';
	echo "<textarea name=\"security_recommendations\" id=\"active-textfield\" cols=\"50\" rows=\"3\" tabindex=\"1\">" . $escaper->escapeHtml($security_recommendations) . "</textarea></div>";
	echo '</div><div class="row-fluid"><div class="span5">';
	echo $escaper->escapeHtml($lang['SupportingDocumentation']) .":</div>";
	echo '<div class="span7">';
    supporting_documentation($id, "edit", 2);

	echo "</div>";
	echo '</div></div></div>';
	echo "<div class=\"form-actions\">\n";
	echo "</div>\n";
	echo "</form>\n";
        
}

/************************************
* FUNCTION: edit_review_submission *
************************************/
function edit_review_submission($id, $review, $next_step, $next_review, $comments, $default_next_review)
{
    global $lang;
	global $escaper;

    $default_next_review = date("m/d/Y", strtotime($default_next_review));

	// Decrypt fields
	$comments = try_decrypt($comments);

	echo "<h4>". $escaper->escapeHtml($lang['SubmitManagementReview']) ."</h4>\n";
	echo "<form name=\"submit_management_review\" method=\"post\" action=\"\">\n";

	echo "<div class=\"tabs--action\">";
//	echo "<input id=\"cancel_disable\" class=\"btn cancel-edit-review \" value=\"". $escaper->escapeHtml($lang['Cancel']) ."\" type=\"reset\">\n";
	echo "<a href=\"view.php?id={$id}&type=2\" id=\"cancel_disable\" class=\"btn cancel-edit-review\" disabled=\"disabled\">Cancel</a>&nbsp;\n";
	echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-danger save-review\">". $escaper->escapeHtml($lang['SubmitReview']) ."</button>\n";
	echo "</div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5\">\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['ReviewDate']) .":</div>\n";
	echo "<div class=\"span7 reviewdate\">\n";
	echo date("m/d/Y");
	echo "</div></div>\n";

	echo "<div class=\"row-fluid\">\n";

	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Reviewer']) .":</div>";
	echo "<div class=\"span7 reviewername\">\n";
	echo $escaper->escapeHtml($_SESSION['name']);
	echo "</div></div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['Review']) .":</div>";
	echo "<div class=\"span7\">\n";
	create_dropdown("review", $review, NULL, true);
	echo "</div></div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\">\n";
	echo $escaper->escapeHtml($lang['NextStep']) .":</div>";
	echo "<div class=\"span7\">\n";
	create_dropdown("next_step", $next_step, NULL, true);
	echo "</div></div>\n";

	echo "<div class=\"row-fluid\">\n";
	echo "<div class=\"span5 text-right\" id=\"CommentsTitle\">\n";;
	echo $escaper->escapeHtml($lang['Comments']) .":</div>";
	echo "<div class=\"span7\">\n";
	echo "<textarea name=\"comments\" cols=\"50\" rows=\"3\" id=\"comments\">" . $escaper->escapeHtml($comments) . "</textarea>\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "</div>\n";


	echo "<div class=\"span5\">\n";
	echo "<div class=\"row-fluid\">\n";

	echo "<div class=\"span3 text-left\"> </div>";
	echo "<div class=\"span9 text-left\">";

	echo '<strong class="small-text">'.$escaper->escapeHtml($lang['BasedOnTheCurrentRiskScore']) . $escaper->escapeHtml($default_next_review) . "<br />\n";
	echo $escaper->escapeHtml($lang['WouldYouLikeToUseADifferentDate']).'</strong>';

	echo "<div class=\"clearfix radio-buttons-holder radio-padded-top-bottom\">";
        echo "<div class=\"pull-left active-textfield\"><input type=\"radio\" name=\"custom_date\" value=\"no\" onclick=\"hideNextReview()\" id=\"no\" class=\"hidden-radio\" checked /> <label for=\"no\">".$escaper->escapeHtml($lang['No'])."</label></div>";
	echo "<div class=\"pull-left radio-padded-right\"><input type=\"radio\" name=\"custom_date\" value=\"yes\" onclick=\"showNextReview()\" id=\"yes\" class=\"hidden-radio\" /><label for=\"yes\">".$escaper->escapeHtml($lang['Yes'])."</label></div>";
	echo "</div> </div>";

	echo "<div id=\"nextreview\" class=\"nextreview\" style=\"display:none;\">\n";
	echo "<div class=\"span3 text-left\">\n";
	echo $escaper->escapeHtml($lang['NextReviewDate']) .": \n";
	echo "</div>\n";
	echo "<div class=\"span7\">\n";
	//echo "<input type=\"date\" name=\"next_review\" value=\"" . $escaper->escapeHtml($next_review) . "\" />\n";
	echo "<input type=\"text\" class=\"datepicker active-textfield\" name=\"next_review\" id=\"nextreviewvalue\" value=\"" . $escaper->escapeHtml($default_next_review) . "\" />\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";

	echo "</form>\n";
    
    return;
}

/********************************
* FUNCTION: edit_classic_score *
********************************/
function edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact)
{
	global $lang;
	global $escaper;

	echo "<h4>" . $escaper->escapeHtml($lang['UpdateClassicScore']) . "</h4>\n";
	echo "<form name=\"update_classic\" method=\"post\" action=\"\">\n";
	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

//  echo "<div class=\"tabs--action\">";
//	echo "<button type=\"submit\" name=\"update_classic\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
//	echo "</div>\n";

	echo "<tr>\n";
	echo "<td width=\"180\" height=\"10\">" . $escaper->escapeHtml($lang['CurrentLikelihood']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_dropdown("likelihood", $CLASSIC_likelihood, NULL, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
					<a href=\"#\" onClick=\"javascript:showHelp('likelihoodHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td rowspan=\"3\" style=\"vertical-align:top;\">\n";
	view_classic_help();
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\" height=\"10\">" . $escaper->escapeHtml($lang['CurrentImpact']) . ":</td>\n";
	echo "<td width=\"125\">\n";
	create_dropdown("impact", $CLASSIC_impact, NULL, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
					<a href=\"#\" onClick=\"javascript:showHelp('impactHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "</tr>\n";

	echo "<tr><td colspan=\"3\">&nbsp;</td></tr>\n";

	echo "</table>\n";
	echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"update_classic\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
    echo "</div>\n";

	echo "</form>\n";
}

/*****************************
* FUNCTION: edit_cvss_score *
*****************************/
function edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement)
{
	global $lang;
	global $escaper;

	echo "<h4>" . $escaper->escapeHtml($lang['UpdateCVSSScore']) . "</h4>\n";
	echo "<form name=\"update_cvss\" method=\"post\" action=\"\">\n";
	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['BaseVector']) . "</b></td>\n";
	echo "<td rowspan=\"19\" style=\"vertical-align:top;\">\n";
	view_cvss_help();
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"200\">" . $escaper->escapeHtml($lang['AttackVector']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_cvss_dropdown("AccessVector", $AccessVector, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('AccessVectorHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['AttackComplexity']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("AccessComplexity", $AccessComplexity, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
	 	<a href=\"#\" onClick=\"javascript:showHelp('AccessComplexityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['Authentication']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("Authentication", $Authentication, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
	 				<a href=\"#\" onClick=\"javascript:showHelp('AuthenticationHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['ConfidentialityImpact']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("ConfImpact", $ConfImpact, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('ConfImpactHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['IntegrityImpact']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("IntegImpact", $IntegImpact, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
			<a href=\"#\" onClick=\"javascript:showHelp('IntegImpactHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['AvailabilityImpact']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("AvailImpact", $AvailImpact, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('AvailImpactHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\">&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['TemporalScoreMetrics']) . "</b></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("Exploitability", $Exploitability, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
	 	<a href=\"#\" onClick=\"javascript:showHelp('ExploitabilityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['RemediationLevel']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("RemediationLevel", $RemediationLevel, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
	 			<a href=\"#\" onClick=\"javascript:showHelp('RemediationLevelHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['ReportConfidence']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("ReportConfidence", $ReportConfidence, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
			 	<a href=\"#\" onClick=\"javascript:showHelp('ReportConfidenceHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\">&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['EnvironmentalScoreMetrics']) . "</b></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['CollateralDamagePotential']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("CollateralDamagePotential", $CollateralDamagePotential, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('CollateralDamagePotentialHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['TargetDistribution']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("TargetDistribution", $TargetDistribution, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('TargetDistributionHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['ConfidentialityRequirement']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("ConfidentialityRequirement", $ConfidentialityRequirement, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
					<a href=\"#\" onClick=\"javascript:showHelp('ConfidentialityRequirementHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['IntegrityRequirement']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("IntegrityRequirement", $IntegrityRequirement, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('IntegrityRequirementHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['AvailabilityRequirement']) . ":</td>\n";
	echo "<td>\n";
	create_cvss_dropdown("AvailabilityRequirement", $AvailabilityRequirement, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('AvailabilityRequirementHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	echo "<div class=\"form-actions\">\n";
	echo "<button type=\"submit\" name=\"update_cvss\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
	echo "</div>\n";
	echo "</form>\n";
}

/******************************
* FUNCTION: edit_dread_score *
******************************/
function edit_dread_score($DamagePotential, $Reproducibility, $Exploitability, $AffectedUsers, $Discoverability)
{
	global $lang;
	global $escaper;

	echo "<h4>" . $escaper->escapeHtml($lang['UpdateDREADScore']) . "</h4>\n";
	echo "<form name=\"update_dread\" method=\"post\" action=\"\">\n";
	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['DamagePotential']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("DamagePotential", $DamagePotential, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
		<a href=\"#\" onClick=\"javascript:showHelp('DamagePotentialHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "<td rowspan=\"5\" style=\"vertical-align:top;\">\n";
	view_dread_help();
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['Reproducibility']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("Reproducibility", $Reproducibility, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
			<a href=\"#\" onClick=\"javascript:showHelp('ReproducibilityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("Exploitability", $Exploitability, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
			<a href=\"#\" onClick=\"javascript:showHelp('ExploitabilityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['AffectedUsers']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("AffectedUsers", $AffectedUsers, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
		<a href=\"#\" onClick=\"javascript:showHelp('AffectedUsersHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['Discoverability']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("Discoverability", $Discoverability, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
			<a href=\"#\" onClick=\"javascript:showHelp('DiscoverabilityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	echo "<div class=\"form-actions\">\n";
	echo "<button type=\"submit\" name=\"update_dread\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
	echo "</div>\n";
	echo "</form>\n";
}

/******************************
* FUNCTION: edit_owasp_score *
******************************/
function edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation)
{
	global $lang;
	global $escaper;

	echo "<h4>" . $escaper->escapeHtml($lang['UpdateOWASPScore']) . "</h4>\n";
	echo "<form name=\"update_owasp\" method=\"post\" action=\"\">\n";
	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['ThreatAgentFactors']) . "</b></td>\n";
	echo "<td rowspan=\"20\" style=\"vertical-align:top;\">\n";
	view_owasp_help();
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['SkillLevel']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("SkillLevel", $OWASPSkillLevel, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
		<a href=\"#\" onClick=\"javascript:showHelp('SkillLevelHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['Motive']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("Motive", $OWASPMotive, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
		<a href=\"#\" onClick=\"javascript:showHelp('MotiveHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['Opportunity']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("Opportunity", $OWASPOpportunity, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
		<a href=\"#\" onClick=\"javascript:showHelp('OpportunityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['Size']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("Size", $OWASPSize, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
		<a href=\"#\" onClick=\"javascript:showHelp('SizeHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\">&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['VulnerabilityFactors']) . "</b></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['EaseOfDiscovery']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("EaseOfDiscovery", $OWASPEaseOfDiscovery, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
			<a href=\"#\" onClick=\"javascript:showHelp('EaseOfDiscoveryHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['EaseOfExploit']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("EaseOfExploit", $OWASPEaseOfExploit, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
			<a href=\"#\" onClick=\"javascript:showHelp('EaseOfExploitHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['Awareness']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("Awareness", $OWASPAwareness, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('AwarenessHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['IntrusionDetection']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("IntrusionDetection", $OWASPIntrusionDetection, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
			<a href=\"#\" onClick=\"javascript:showHelp('IntrusionDetectionHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
			</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\">&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['TechnicalImpact']) . "</b></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['LossOfConfidentiality']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("LossOfConfidentiality", $OWASPLossOfConfidentiality, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('LossOfConfidentialityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['LossOfIntegrity']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("LossOfIntegrity", $OWASPLossOfIntegrity, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('LossOfIntegrityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['LossOfAvailability']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("LossOfAvailability", $OWASPLossOfAvailability, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
		<a href=\"#\" onClick=\"javascript:showHelp('LossOfAvailabilityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
		</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['LossOfAccountability']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("LossOfAccountability", $OWASPLossOfAccountability, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('LossOfAccountabilityHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\">&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['BusinessImpact']) . "</b></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['FinancialDamage']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("FinancialDamage", $OWASPFinancialDamage, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('FinancialDamageHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['ReputationDamage']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("ReputationDamage", $OWASPReputationDamage, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('ReputationDamageHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['NonCompliance']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("NonCompliance", $OWASPNonCompliance, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('NonComplianceHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['PrivacyViolation']) . ":</td>\n";
	echo "<td width=\"200\">\n";
	create_numeric_dropdown("PrivacyViolation", $OWASPPrivacyViolation, false);
	echo "</td>\n";
	echo "<td width=\"50\" class=\"vtop\">
				<a href=\"#\" onClick=\"javascript:showHelp('PrivacyViolationHelp');\" class=\"score--help\"><i class=\"fa fa-question\"></i></a>
				</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	echo "<div class=\"form-actions\">\n";
	echo "<button type=\"submit\" name=\"update_owasp\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
	echo "</div>\n";
	echo "</form>\n";
}

/*******************************
* FUNCTION: edit_custom_score *
*******************************/
function edit_custom_score($custom)
{
	global $lang;
	global $escaper;

	echo "<h4>" . $escaper->escapeHtml($lang['UpdateCustomScore']) . "</h4>\n";
	echo "<form name=\"update_custom\" method=\"post\" action=\"\">\n";
	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
	echo "<td width=\"165\" height=\"10\">" . $escaper->escapeHtml($lang['ManuallyEnteredValue']) . ":</td>\n";
	echo "<td width=\"60\"><input type=\"text\" name=\"Custom\" id=\"Custom\" style=\"width:30px;\" value=\"" . $escaper->escapeHtml($custom) . "\"></td>\n";
	echo "<td>(Must be a numeric value between 0 and 10)</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	echo "<div class=\"form-actions\">\n";
	echo "<button type=\"submit\" name=\"update_custom\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
	echo "</div>\n";
	echo "</form>\n";
}

/***********************************
* FUNCTION: CLASSIC SCORING TABLE *
***********************************/
function classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact,$type=0)
{
	global $lang;
	global $escaper;

	echo "<table class=\"score--table\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
	echo "<td colspan=\"3\"><h4>". $escaper->escapeHtml($lang['ClassicRiskScoring']) ."</h4></td>\n";
	echo "<td colspan=\"1\" style=\"vertical-align:top;\">\n";
	echo "<div class=\"btn-group pull-right sorting-buttons\">\n";
	echo "<a class=\"btn updateScore\" href=\"#\" onclick=\"javascript:updateScore()\">". $escaper->escapeHtml($lang['UpdateClassicScore']) ."</a>\n";
	echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">". $escaper->escapeHtml($lang['RiskScoringActions']) ."<span class=\"caret\"></span></a>\n";
	echo "<ul class=\"dropdown-menu\">\n";
	//echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">". $escaper->escapeHtml($lang['UpdateClassicScore']) ."</a></li>\n";
	echo "<li><a class='score-action' data-method='2' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=2\">". $escaper->escapeHtml($lang['ScoreByCVSS']) ."</a></li>\n";
	echo "<li><a class='score-action' data-method='3' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=3\">". $escaper->escapeHtml($lang['ScoreByDREAD']) ."</a></li>\n";
	echo "<li><a class='score-action' data-method='4' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=4\">". $escaper->escapeHtml($lang['ScoreByOWASP']) ."</a></li>\n";
	echo "<li><a class='score-action' data-method='5' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=5\">". $escaper->escapeHtml($lang['ScoreByCustom']) ."</a></li>\n";
	echo "</ul>\n";
	echo "</div>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"180\">". $escaper->escapeHtml($lang['Likelihood']) .":</td>\n";
	echo "<td width=\"30\">[ " . $escaper->escapeHtml($CLASSIC_likelihood) . " ]</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("likelihood", $CLASSIC_likelihood)) . "</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"180\">". $escaper->escapeHtml($lang['Impact']) .":</td>\n";
	echo "<td width=\"30\">[ " . $escaper->escapeHtml($CLASSIC_impact) . " ]</td>\n";
	echo "<td>" . $escaper->escapeHtml(get_name_by_value("impact", $CLASSIC_impact)) . "</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr><td colspan=\"4\">&nbsp;</td></tr>\n";

	if (get_setting("risk_model") == 1)
	{
		echo "<tr>\n";
		echo "<td colspan=\"4\"><b>". $escaper->escapeHtml($lang['RISKClassicExp1']) ." x ( 10 / 35 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
		echo "</tr>\n";
	}
	else if (get_setting("risk_model") == 2)
	{
		echo "<tr>\n";
		echo "<td colspan=\"4\"><b>". $escaper->escapeHtml($lang['RISKClassicExp2']) ." x ( 10 / 30 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
		echo "</tr>\n";
	}
	else if (get_setting("risk_model") == 3)
	{
		echo "<tr>\n";
		echo "<td colspan=\"4\"><b>". $escaper->escapeHtml($lang['RISKClassicExp3']) ." x ( 10 / 25 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
		echo "</tr>\n";
	}
	else if (get_setting("risk_model") == 4)
	{
		echo "<tr>\n";
		echo "<td colspan=\"4\"><b>". $escaper->escapeHtml($lang['RISKClassicExp4']) ." x ( 10 / 30 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
		echo "</tr>\n";
	}
	else if (get_setting("risk_model") == 5)
	{
		echo "<tr>\n";
		echo "<td colspan=\"4\"><b>". $escaper->escapeHtml($lang['RISKClassicExp5']) ." x ( 10 / 35 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
		echo "</tr>\n";
	}

	echo "</table>\n";
}

/********************************
* FUNCTION: CVSS SCORING TABLE *
********************************/
function cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement,$type=0)
{
	global $lang;
	global $escaper;

	echo "<table width=\"100%\" class=\" \" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><h4>" . $escaper->escapeHtml($lang['CVSSRiskScoring']) . "</h4></td>\n";
	echo "<td colspan=\"3\" style=\"vertical-align:top;\">\n";
	echo "<div class=\"btn-group pull-right sorting-buttons\">\n";
	echo "<a class=\"btn updateScore\" href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateCVSSScore']) . "</a>\n";
	echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($lang['RiskScoringActions']) . "<span class=\"caret\"></span></a>\n";
	echo "<ul class=\"dropdown-menu\">\n";
	//echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateCVSSScore']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='1' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=1\">" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='3' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=3\">" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='4' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=4\">" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='5' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=5\">" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>\n";
	echo "</ul>\n";
	echo "</div>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"7\">" . $escaper->escapeHtml($lang['BaseVector']) . ": AV:" . $escaper->escapeHtml($AccessVector) . "/AC:" . $escaper->escapeHtml($AccessComplexity) . "/Au:" . $escaper->escapeHtml($Authentication) . "/C:" . $escaper->escapeHtml($ConfImpact) . "/I:" . $escaper->escapeHtml($IntegImpact) . "/A:" . $escaper->escapeHtml($AvailImpact) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"7\">" . $escaper->escapeHtml($lang['TemporalVector']) . ": E:" . $escaper->escapeHtml($Exploitability) . "/RL:" . $escaper->escapeHtml($RemediationLevel) . "/RC:" . $escaper->escapeHtml($ReportConfidence) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"7\">" . $escaper->escapeHtml($lang['EnvironmentalVector']) . ": CDP:" . $escaper->escapeHtml($CollateralDamagePotential) . "/TD:" . $escaper->escapeHtml($TargetDistribution) . "/CR:" . $escaper->escapeHtml($ConfidentialityRequirement) . "/IR:" . $escaper->escapeHtml($IntegrityRequirement) . "/AR:" . $escaper->escapeHtml($AvailabilityRequirement) . "</td>\n";
	echo "</tr>\n";

	echo "<tr><td colspan=\"8\">&nbsp;</td></tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"2\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['BaseScoreMetrics']) . "</b></td>\n";
	echo "<td colspan=\"2\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['TemporalScoreMetrics']) . "</b></td>\n";
	echo "<td colspan=\"2\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['EnvironmentalScoreMetrics']) . "</b></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['AttackVector']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("AccessVector", $AccessVector)) . "</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("Exploitability", $Exploitability)) . "</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['CollateralDamagePotential']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("CollateralDamagePotential", $CollateralDamagePotential)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['AttackComplexity']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("AccessComplexity", $AccessComplexity)) . "</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['RemediationLevel']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("RemediationLevel", $RemediationLevel)) . "</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['TargetDistribution']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("TargetDistribution", $TargetDistribution)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['Authentication']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("Authentication", $Authentication)) . "</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['ReportConfidence']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("ReportConfidence", $ReportConfidence)) . "</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['ConfidentialityRequirement']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("ConfidentialityRequirement", $ConfidentialityRequirement)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['ConfidentialityImpact']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("ConfImpact", $ConfImpact)) . "</td>\n";
	echo "<td width=\"20%\">&nbsp;</td>\n";
	echo "<td width=\"10%\">&nbsp</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['IntegrityRequirement']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("IntegrityRequirement", $IntegrityRequirement)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"20%\">". $escaper->escapeHtml($lang['IntegrityImpact']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("IntegImpact", $IntegImpact)) . "</td>\n";
	echo "<td width=\"20%\">&nbsp;</td>\n";
	echo "<td width=\"10%\">&nbsp</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['AvailabilityRequirement']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("AvailabilityRequirement", $AvailabilityRequirement)) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['AvailabilityImpact']) . ":</td>\n";
	echo "<td width=\"10%\">" . $escaper->escapeHtml(get_cvss_name("AvailImpact", $AvailImpact)) . "</td>\n";
	echo "<td width=\"20%\">&nbsp;</td>\n";
	echo "<td width=\"10%\">&nbsp</td>\n";
	echo "<td width=\"20%\">&nbsp;</td>\n";
	echo "<td width=\"10%\">&nbsp</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"7\">&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"7\"><strong>Full details of CVSS Version 2.0 scoring can be found <a href=\"https://www.first.org/cvss/v2/guide\" target=\"_blank\">here</a>.</strong></td>\n";
	echo "</tr>\n";

	echo "</table>\n";
}

/*********************************
* FUNCTION: DREAD SCORING TABLE *
*********************************/
function dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability,$type=0)
{
	global $lang;
	global $escaper;

	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
	echo "<td colspan=\"2\"><h4>" . $escaper->escapeHtml($lang['DREADRiskScoring']) . "</h4></td>\n";
	echo "<td colspan=\"1\" style=\"vertical-align:top;\">\n";
	echo "<div class=\"btn-group pull-right sorting-buttons\">\n";
	echo "<a class=\"btn updateScore\" href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateDREADScore']) . "</a>\n";
	echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($lang['RiskScoringActions']) . "<span class=\"caret\"></span></a>\n";
	echo "<ul class=\"dropdown-menu\">\n";
	//echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateDREADScore']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='1' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=1\">" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='2' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=2\">" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='4' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=4\">" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='5' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=5\">" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>\n";
	echo "</ul>\n";
	echo "</div>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"3\">&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['DamagePotential']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($DREADDamagePotential) . "</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['Reproducibility']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($DREADReproducibility) . "</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($DREADExploitability) . "</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['AffectedUsers']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($DREADAffectedUsers) . "</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"150\">" . $escaper->escapeHtml($lang['Discoverability']) . ":</td>\n";
	echo "<td>" . $escaper->escapeHtml($DREADDiscoverability) . "</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"3\">&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"3\"><b>RISK = ( " . $escaper->escapeHtml($DREADDamagePotential) . " + " . $escaper->escapeHtml($DREADReproducibility) . " + " . $escaper->escapeHtml($DREADExploitability) . " + " . $escaper->escapeHtml($DREADAffectedUsers) . " + " . $escaper->escapeHtml($DREADDiscoverability) . " ) / 5 = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
	echo "</tr>\n";

	echo "</table>\n";
}

/*********************************
* FUNCTION: OWASP SCORING TABLE *
*********************************/
function owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation,$type=0)
{
	global $lang;
	global $escaper;

	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><h4>" . $escaper->escapeHtml($lang['OWASPRiskScoring']) . "</h4></td>\n";
	echo "<td colspan=\"5\" style=\"vertical-align:top;\">\n";
	echo "<div class=\"btn-group pull-right sorting-buttons\">\n";
	echo "<a class=\"btn updateScore\" href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateOWASPScore']) . "</a>\n";
	echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($lang['RiskScoringActions']) . "<span class=\"caret\"></span></a>\n";
	echo "<ul class=\"dropdown-menu\">\n";
	//echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateOWASPScore']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='1' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=1\">" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='2' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=2\">" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='3' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=3\">" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='5' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=5\">" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>\n";
	echo "</ul>\n";
	echo "</div>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"2\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['ThreatAgentFactors']) . "</b></td>\n";
	echo "<td colspan=\"2\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['VulnerabilityFactors']) . "</b></td>\n";
	echo "<td colspan=\"2\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['TechnicalImpact']) . "</b></td>\n";
	echo "<td colspan=\"2\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['BusinessImpact']) . "</b></td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['SkillLevel']) . ":</td>\n";
	echo "<td width=\"5%\" class=\"vtop\">" . $escaper->escapeHtml($OWASPSkillLevel) . "</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['EaseOfDiscovery']) . ":</td>\n";
	echo "<td width=\"5%\" class=\"vtop\">" . $escaper->escapeHtml($OWASPEaseOfDiscovery) . "</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['LossOfConfidentiality']) . ":</td>\n";
	echo "<td width=\"5%\" class=\"vtop\">" . $escaper->escapeHtml($OWASPLossOfConfidentiality) . "</td>\n";
	echo "<td width=\"20%\">" . $escaper->escapeHtml($lang['FinancialDamage']) . ":</td>\n";
	echo "<td width=\"5%\" class=\"vtop\">" . $escaper->escapeHtml($OWASPFinancialDamage) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['Motive']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPMotive) . "</td>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['EaseOfExploit']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPEaseOfExploit) . "</td>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['LossOfIntegrity']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPLossOfIntegrity) . "</td>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['ReputationDamage']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPReputationDamage) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['Opportunity']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPOpportunity) . "</td>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['Awareness']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPAwareness) . "</td>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['LossOfAvailability']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPLossOfAvailability) . "</td>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['NonCompliance']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPNonCompliance) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['Size']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPSize) . "</td>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['IntrusionDetection']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPIntrusionDetection) . "</td>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['LossOfAccountability']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPLossOfAccountability) . "</td>\n";
	echo "<td width=\"125\">" . $escaper->escapeHtml($lang['PrivacyViolation']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($OWASPPrivacyViolation) . "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"9\">&nbsp;</td>\n";
	echo "<tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['Likelihood']) . "</b></td>\n";
	echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['Impact']) . "</b></td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "<tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\">" . $escaper->escapeHtml($lang['ThreatAgentFactors']) . " = ( " . $escaper->escapeHtml($OWASPSkillLevel) . " + " . $escaper->escapeHtml($OWASPMotive) . " + " . $escaper->escapeHtml($OWASPOpportunity) . " + " . $escaper->escapeHtml($OWASPSize) . " ) / 4</td>\n";
	echo "<td colspan=\"4\">" . $escaper->escapeHtml($lang['TechnicalImpact']) . " = ( " . $escaper->escapeHtml($OWASPLossOfConfidentiality) . " + " . $escaper->escapeHtml($OWASPLossOfIntegrity) . " + " . $escaper->escapeHtml($OWASPLossOfAvailability) . " + " . $escaper->escapeHtml($OWASPLossOfAccountability) . " ) / 4</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "<tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"4\">" . $escaper->escapeHtml($lang['VulnerabilityFactors']) . " = ( " . $escaper->escapeHtml($OWASPEaseOfDiscovery) . " + " . $escaper->escapeHtml($OWASPEaseOfExploit) . " + " . $escaper->escapeHtml($OWASPAwareness) . " + " . $escaper->escapeHtml($OWASPIntrusionDetection) . " ) / 4</td>\n";
	echo "<td colspan=\"4\">" . $escaper->escapeHtml($lang['BusinessImpact']) . " = ( " . $escaper->escapeHtml($OWASPFinancialDamage) . " + " . $escaper->escapeHtml($OWASPReputationDamage) . " + " . $escaper->escapeHtml($OWASPNonCompliance) . " + " . $escaper->escapeHtml($OWASPPrivacyViolation) . " ) / 4</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "<tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"9\">&nbsp;</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td colspan=\"9\"><strong>Full details of the OWASP Risk Rating Methodology can be found <a href=\"https://www.owasp.org/index.php/OWASP_Risk_Rating_Methodology\" target=\"_blank\">here</a>.</strong></td>\n";
	echo "</tr>\n";

	echo "</table>\n";
}

/**********************************
* FUNCTION: CUSTOM SCORING TABLE *
**********************************/
function custom_scoring_table($id, $custom,$type=0)
{
	global $lang;
	global $escaper;

	echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

	echo "<tr>\n";
	echo "<td colspan=\"2\"><h4>" . $escaper->escapeHtml($lang['CustomRiskScoring']) . "</h4></td>\n";
	echo "<td colspan=\"1\" style=\"vertical-align:top;\">\n";
	echo "<div class=\"btn-group pull-right sorting-buttons\">\n";
	echo "<a class=\"btn updateScore\" href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateCustomScore']) . "</a>\n";
	echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($lang['RiskScoringActions']) . "<span class=\"caret\"></span></a>\n";
	echo "<ul class=\"dropdown-menu\">\n";
	//echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateCustomScore']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='1' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=1\">" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='2' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=2\">" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='3' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=3\">" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>\n";
	echo "<li><a class='score-action' data-method='4' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=4\">" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>\n";
	echo "</ul>\n";
	echo "</div>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width=\"175\">" . $escaper->escapeHtml($lang['ManuallyEnteredValue']) . ":</td>\n";
	echo "<td width=\"10\">" . $escaper->escapeHtml($custom) . "</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "<tr>\n";

	echo "</table>\n";
}

/*******************************
* FUNCTION: VIEW CLASSIC HELP *
*******************************/
function view_classic_help()
{
	global $escaper;

	// Get the arrray of likelihood values
	$likelihood = get_table("likelihood");

	// Get the array of impact values
	$impact = get_table("impact");

	echo "<div id=\"divHelp\" style=\"width:100%;overflow:auto\"></div>\n";

	echo "<div id=\"likelihoodHelp\"  style=\"display:none; visibility:hidden\">\n";
	echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
	echo "<tr>\n";
	echo "<td class=\"cal-text\">\n";
	echo "<p><b>" . $escaper->escapeHtml($likelihood[0]['name']) . ":</b> May only occur in exceptional circumstances.</p>\n";
	echo "<p><b>" . $escaper->escapeHtml($likelihood[1]['name']) . ":</b> Expected to occur in a few circumstances.</p>\n";
	echo "<p><b>" . $escaper->escapeHtml($likelihood[2]['name']) . ":</b> Expected to occur in some circumstances.</p>\n";
	echo "<p><b>" . $escaper->escapeHtml($likelihood[3]['name']) . ":</b> Expected to occur in many circumstances.</p>\n";
	echo "<p><b>" . $escaper->escapeHtml($likelihood[4]['name']) . ":</b> Expected to occur frequently and in most circumstances.</p>\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</div>\n";

	echo "<div id=\"impactHelp\"  style=\"display:none; visibility:hidden\">\n";
	echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
	echo "<tr>\n";
	echo "<td class=\"cal-text\">\n";
	echo "<p><b>" . $escaper->escapeHtml($impact[0]['name']) . ":</b> No impact on service, no impact on reputation, complaint unlikely, or litigation risk remote.</p>\n";
	echo "<p><b>" . $escaper->escapeHtml($impact[1]['name']) . ":</b> Slight impact on service, slight impact on reputation, complaint possible, or litigation possible.</p>\n";
	echo "<p><b>" . $escaper->escapeHtml($impact[2]['name']) . ":</b> Some service disruption, potential for adverse publicity (avoidable with careful handling), complaint probable, or litigation probably.</p>\n";
	echo "<p><b>" . $escaper->escapeHtml($impact[3]['name']) . ":</b> Service disrupted, adverse publicity not avoidable (local media), complaint probably, or litigation probable.</p>\n";
	echo "<p><b>" . $escaper->escapeHtml($impact[4]['name']) . ":</b> Service interrupted for significant time, major adverse publicity not avoidable (national media), major litigation expected, resignation of senior management and board, or loss of benficiary confidence.</p>\n";
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
			echo "<p>1 = No Technical Skills</p>\n";
			echo "<p>3 = Some Technical Skills</p>\n";
			echo "<p>5 = Advanced Computer User</p>\n";
			echo "<p>6 = Network and Programming Skills</p>\n";
			echo "<p>9 = Security Penetration Skills</p>\n";
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
			echo "<p>6 = Extensive Non-Sensitive Data Disclosed</p>\n";
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
					echo "<td class=\"cal-head no-border\"><b>Local</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>High</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>None</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>None</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>None</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>None</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>Unproven that exploit exists</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>Official Fix</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>Not Confirmed</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>None</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>None</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>Low</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>Low</b></td>\n";
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
					echo "<td class=\"cal-head no-border\"><b>Low</b></td>\n";
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

								/***************************
								* FUNCTION: VIEW TOP MENU *
								***************************/
								function view_top_menu($active)
								{
									global $lang;
									global $escaper;

									// If the page is in the root directory
									if ($active == "Home")
									{
									echo "<header class=\"l-header\">\n";
									echo "<div class=\"navbar\">\n";
									echo "<div class=\"navbar-inner\">\n";
									echo "<div class=\"container-fluid\">\n";
                                                                                echo "<a class=\"brand\" href=\"https://www.simplerisk.it/\"><img src='images/logo@2x.png' alt='SimpleRisk Logo' /></a>\n";
									echo "<div class=\"navbar-content\">\n";
									echo "<ul class=\"nav\">\n";
										// echo "<li class=\"active\">\n";
										// echo "<a href=\"index.php\">" . $escaper->escapeHtml($lang['Home']) . "</a>\n";
										// echo "</li>\n";
										// echo "<li>\n";
										// echo "<a href=\"management/index.php\">" . $escaper->escapeHtml($lang['RiskManagement']) . "</a>\n";
										// echo "</li>\n";

										// If the user has asset management permissions
										if (isset($_SESSION["asset"]) && $_SESSION["asset"] == "1")
										{
											//echo ($active == "AssetManagement" ? "<li class=\"active\">\n" : "<li>\n");
											//echo "<a href=\"assets/index.php\">" . $escaper->escapeHtml($lang['AssetManagement']) . "</a>\n";
											//echo "</li>\n";
										}

										// If the user has assessments permissions
										if (isset($_SESSION["assessments"]) && $_SESSION["assessments"] == "1")
										{
											//echo ($active == "Assessments" ? "<li class=\"active\">\n" : "<li>\n");
											//echo "<a href=\"assessments/index.php\">" . $escaper->escapeHtml($lang['Assessments']) . "</a>\n";
											//echo "</li>\n";
										}

										// echo "<li>\n";
										// echo "<a href=\"reports/index.php\">" . $escaper->escapeHtml($lang['Reporting']) . "</a>\n";
										// echo "</li>\n";

										// If the user is logged in as an administrator
										if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
										{
											//echo ($active == "Configure" ? "<li class=\"active\">\n" : "<li>\n");
											//echo "<a href=\"admin/index.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>\n";
											//echo "</li>\n";
										}

										echo "</ul>\n";
										echo "</div>\n";

										// If the user is logged in
										if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted")
										{
											// Show the user profile menu
											echo "<div class=\"btn-group pull-right\">\n";
											echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($_SESSION['name']) . "<span class=\"caret\"></span></a>\n";
											echo "<ul class=\"dropdown-menu\">\n";
											echo "<li>\n";
											echo "<a href=\"account/profile.php\">". $escaper->escapeHtml($lang['MyProfile']) ."</a>\n";
											echo "</li>\n";
											echo "<li>\n";
											echo "<a href=\"logout.php\">". $escaper->escapeHtml($lang['Logout']) ."</a>\n";
											echo "</li>\n";
											echo "</ul>\n";
											echo "</div>\n";

										}
									}
									// If the page is in another sub-directory
									else
									{
                                                                                echo "<header class=\"l-header\">\n";
                                                                                echo "<div class=\"navbar\">\n";
                                                                                echo "<div class=\"navbar-inner\">\n";
                                                                                echo "<div class=\"container-fluid\">\n";
                                                                                echo "<a class=\"brand\" href=\"https://www.simplerisk.it/\"><img src='../images/logo@2x.png' alt='SimpleRisk Logo' /></a>\n";
                                                                                echo "<div class=\"navbar-content\">\n";
                                                                                echo "<ul class=\"nav\">\n";
										// echo ($active == "Home" ? "<li class=\"active\">\n" : "<li>\n");
										// echo "<a href=\"../index.php\">" . $escaper->escapeHtml($lang['Home']) . "</a>\n";
										// echo "</li>\n";

										echo ($active == "RiskManagement" ? "<li class=\"active\">\n" : "<li>\n");
										echo "<a href=\"../management/index.php\">" . $escaper->escapeHtml($lang['RiskManagement']) . "</a>\n";
										echo "</li>\n";

										// If the user has asset management permissions
										if (isset($_SESSION["asset"]) && $_SESSION["asset"] == "1")
										{
											echo ($active == "AssetManagement" ? "<li class=\"active\">\n" : "<li>\n");
											echo "<a href=\"../assets/index.php\">" . $escaper->escapeHtml($lang['AssetManagement']) . "</a>\n";
											echo "</li>\n";
										}

										// If the user has assessments permissions
										if (isset($_SESSION["assessments"]) && $_SESSION["assessments"] == "1")
										{
											echo ($active == "Assessments" ? "<li class=\"active\">\n" : "<li>\n");
											echo "<a href=\"../assessments/index.php\">" . $escaper->escapeHtml($lang['Assessments']) . "</a>\n";
											echo "</li>\n";
										}

										echo ($active == "Reporting" ? "<li class=\"active\">\n" : "<li>\n");
										echo "<a href=\"../reports/index.php\">" . $escaper->escapeHtml($lang['Reporting']) . "</a>\n";
										echo "</li>\n";

										// If the user is logged in as an administrator
										if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
										{
											echo ($active == "Configure" ? "<li class=\"active\">\n" : "<li>\n");
											echo "<a href=\"../admin/index.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>\n";
											echo "</li>\n";
										}

										echo "</ul>\n";
										echo "</div>\n";


										if ($active != "Home"){
                                            echo "<div class=\"pull-right search-risks\">\n";
                                            echo "<a id=\"show-search-pop\"><i class=\"fa fa-search\"></i></a>";
                                            echo "<div class=\"search-popup\">";
                                            echo "<form name=\"search\" action=\"../management/view.php\" method=\"get\">\n";
                                            echo "<span class=\"search--wrapper\">";
                                            echo "<input type=\"text\" size=\"6\" name=\"id\" placeholder=\"ID#\" onClick=\"this.setSelectionRange(0, this.value.length)\" />\n";
                                            echo "<a href=\"javascript:document.search.submit()\"><i class=\"fa fa-search\"></i></a>\n";
                                            echo "</span>";
                                            echo "</form>\n";
                                            echo "</div>";
                                            echo "</div>\n";

                                            echo "<script type=\"text/javascript\">\n";
                                            echo "$(document).click(function() {\n";
                                            echo "$(\".search-popup\").hide();";
                                              echo "});\n";
                                            echo "$(\"#show-search-pop, .search-popup\").click(function(event) {\n";
                                            echo "event.stopPropagation();\n";
                                            echo "event.preventDefault()\n";    
                                            echo "$(\".search-popup\").show();\n";
                                            echo "$(\".search-popup .search--wrapper input[type=text]\").focus();\n";
                                            echo "});\n";
                                            echo "setTimeout(function(){\n";
                                            echo "$(\"#alert\").hide();\n";
                                            echo "}, 5000);\n";
                                            echo "</script>\n";
                                        }


										// If the user is logged in
										if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted")
										{
											// Show the user profile menu
											echo "<div class=\"pull-right user--info\">\n";
											echo "<a class=\"dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($_SESSION['name']) . "<span class=\"caret\"></span></a>\n";
											echo "<ul class=\"dropdown-menu\">\n";
											echo "<li>\n";
											echo "<a href=\"../account/profile.php\">". $escaper->escapeHtml($lang['MyProfile']) ."</a>\n";
											echo "</li>\n";
											echo "<li>\n";
											echo "<a href=\"../logout.php\">". $escaper->escapeHtml($lang['Logout']) ."</a>\n";
											echo "</li>\n";
											echo "</ul>\n";
											echo "</div>\n";
										}
									}

									echo "</div>\n";
									echo "</div>\n";
									echo "</div>\n";
									echo "</header>\n";
								}

								/***************************************
								* FUNCTION: VIEW RISK MANAGEMENT MENU *
								***************************************/
								function view_risk_management_menu($active)
								{
									global $lang;
									global $escaper;

									echo "<ul class=\"nav nav-pills nav-stacked aside--nav \">\n";
									echo ($active == "SubmitYourRisks" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"index.php\"> <span>1</span> " . $escaper->escapeHtml($lang['SubmitYourRisks']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "PlanYourMitigations" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"plan_mitigations.php\"> <span>2</span> " . $escaper->escapeHtml($lang['PlanYourMitigations']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "PerformManagementReviews" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"management_review.php\"> <span>3</span> " . $escaper->escapeHtml($lang['PerformManagementReviews']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "PrioritizeForProjectPlanning" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"prioritize_planning.php\"> <span>4</span> " . $escaper->escapeHtml($lang['PrioritizeForProjectPlanning']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "ReviewRisksRegularly" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"review_risks.php\"> <span>5</span> " . $escaper->escapeHtml($lang['ReviewRisksRegularly']) . "</a>\n";
									echo "</li>\n";
									echo "</ul>\n";
								}

								/***************************************
								* FUNCTION: VIEW ASSET MANAGEMENT MENU *
								***************************************/
								function view_asset_management_menu($active)
								{
									global $lang;
									global $escaper;

									echo "<ul class=\"nav nav-pills nav-stacked aside--nav \">\n";
									echo ($active == "AutomatedDiscovery" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"index.php\"> <span>1</span>" . $escaper->escapeHtml($lang['AutomatedDiscovery']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AddDeleteAssets" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"adddeleteassets.php\"> <span>2</span>" . $escaper->escapeHtml($lang['AddDeleteAssets']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "EditAssets" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"edit.php\"> <span>3</span> " . $escaper->escapeHtml($lang['EditAssets']) . "</a>\n";
									echo "</li>\n";
									echo "</ul>\n";
								}

								/***********************************
								* FUNCTION: VIEW ASSESSMENTS MENU *
								***********************************/
								function view_assessments_menu($active)
								{
									global $lang;
									global $escaper;

									echo "<ul class=\"nav nav-pills nav-stacked aside--nav \">\n";
									echo ($active == "AvailableAssessments" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"index.php\"><span>1</span> " . $escaper->escapeHtml($lang['AvailableAssessments']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "PendingRisks" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"risks.php\"> <span>2</span>" .  $escaper->escapeHtml($lang['PendingRisks']) . "</a>\n";
									echo "</li>\n";

									// If the assessments extra is installed
									if (assessments_extra())
									{
										// Include the assessments extra
										require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

										// Display the assessments extra menu
										view_assessments_extra_menu($active);
									}

									echo "</ul>\n";
								}

								/*********************************
								* FUNCTION: VIEW REPORTING MENU *
								*********************************/
								function view_reporting_menu($active)
								{
									global $lang;
									global $escaper;

									echo "<ul class=\"nav nav-pills nav-stacked aside--nav \">\n";
									echo ($active == "Overview" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"index.php\">" . $escaper->escapeHtml($lang['Overview']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "RiskDashboard" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"dashboard.php\">" . $escaper->escapeHtml($lang['RiskDashboard']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "RiskTrend" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"trend.php\">" . $escaper->escapeHtml($lang['RiskTrend']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "DynamicRiskReport" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"dynamic_risk_report.php\">" . $escaper->escapeHtml($lang['DynamicRiskReport']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "RiskAdvice" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"riskadvice.php\">" . $escaper->escapeHtml($lang['RiskAdvice']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "RisksAndAssets" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"risks_and_assets.php\">" . $escaper->escapeHtml($lang['RisksAndAssets']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AllOpenRisksAssignedToMeByRiskLevel" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"my_open.php\">" . $escaper->escapeHtml($lang['AllOpenRisksAssignedToMeByRiskLevel']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AllOpenRisksNeedingReview" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"review_needed.php\">" . $escaper->escapeHtml($lang['AllOpenRisksNeedingReview']) . "</a>\n";
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

									// Obsolete Reports
									echo "<li id=\"obsolete_menu\"><a href=\"#\" onclick=\"javascript:showObsolete()\">" . $escaper->escapeHtml($lang['ObsoleteReports']) . "</a></li>\n";
									echo ($active == "AllOpenRisksByRiskLevel" ? "<li class=\"active obsolete\" style=\"display:none;\">\n" : "<li class=\"obsolete\" style=\"display:none;\">\n");
									echo "<a href=\"open.php\">" . $escaper->escapeHtml($lang['AllOpenRisksByRiskLevel']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AllClosedRisksByRiskLevel" ? "<li class=\"active obsolete\" style=\"display:none;\">\n" : "<li class=\"obsolete\" style=\"display:none;\">\n");
									echo "<a href=\"closed.php\">" . $escaper->escapeHtml($lang['AllClosedRisksByRiskLevel']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AllOpenRisksByTeam" ? "<li class=\"active obsolete\" style=\"display:none;\">\n" : "<li class=\"obsolete\" style=\"display:none;\">\n");
									echo "<a href=\"teams.php\">" . $escaper->escapeHtml($lang['AllOpenRisksByTeam']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AllOpenRisksByTechnology" ? "<li class=\"active obsolete\" style=\"display:none;\">\n" : "<li class=\"obsolete\" style=\"display:none;\">\n");
									echo "<a href=\"technologies.php\">" . $escaper->escapeHtml($lang['AllOpenRisksByTechnology']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AllOpenRisksByScoringMethod" ? "<li class=\"active osbolete\" style=\"display:none;\">\n" : "<li class=\"obsolete\" style=\"display:none;\">\n");
									echo "<a href=\"risk_scoring.php\">" . $escaper->escapeHtml($lang['AllOpenRisksByScoringMethod']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AllOpenRisksConsideredForProjectsByRiskLevel" ? "<li class=\"active osbolete\" style=\"display:none;\">\n" : "<li class=\"obsolete\" style=\"display:none;\">\n");
									echo "<a href=\"projects.php\">" . $escaper->escapeHtml($lang['AllOpenRisksConsideredForProjectsByRiskLevel']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AllOpenRisksAcceptedUntilNextReviewByRiskLevel" ? "<li class=\"active osbolete\" style=\"display:none;\">\n" : "<li class=\"obsolete\" style=\"display:none;\">\n");
									echo "<a href=\"next_review.php\">" . $escaper->escapeHtml($lang['AllOpenRisksAcceptedUntilNextReviewByRiskLevel']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AllOpenRisksToSubmitAsAProductionIssueByRiskLevel" ? "<li class=\"active osbolete\" style=\"display:none;\">\n" : "<li class=\"obsolete\" style=\"display:none;\">\n");
									echo "<a href=\"production_issues.php\">" . $escaper->escapeHtml($lang['AllOpenRisksToSubmitAsAProductionIssueByRiskLevel']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "ProjectsAndRisksAssigned" ? "<li class=\"active obsolete\" style=\"display:none;\">\n" : "<li class=\"obsolete\" style=\"display:none;\">\n");
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

									echo "<ul class=\"nav nav-pills nav-stacked aside--nav \">\n";
									echo ($active == "ConfigureRiskFormula" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"index.php\">" . $escaper->escapeHtml($lang['ConfigureRiskFormula']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "ConfigureReviewSettings" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"review_settings.php\">" . $escaper->escapeHtml($lang['ConfigureReviewSettings']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AddAndRemoveValues" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"add_remove_values.php\">" . $escaper->escapeHtml($lang['AddAndRemoveValues']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "UserManagement" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"user_management.php\">" . $escaper->escapeHtml($lang['UserManagement']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "RedefineNamingConventions" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"custom_names.php\">" . $escaper->escapeHtml($lang['RedefineNamingConventions']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AssetValuation" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"assetvaluation.php\">" . $escaper->escapeHtml($lang['AssetValuation']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "FileUploadSettings" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"uploads.php\">" . $escaper->escapeHtml($lang['FileUploadSettings']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "MailSettings" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"mail_settings.php\">" . $escaper->escapeHtml($lang['MailSettings']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "DeleteRisks" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"delete_risks.php\">" . $escaper->escapeHtml($lang['DeleteRisks']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "AuditTrail" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"audit_trail.php\">" . $escaper->escapeHtml($lang['AuditTrail']) . "</a>\n";
									echo "</li>\n";

									// If the Import/Export Extra is enabled
									if (import_export_extra())
									{
										echo ($active == "ImportExport" ? "<li class=\"active\">\n" : "<li>\n");
										echo "<a href=\"importexport.php\">" . $escaper->escapeHtml($lang['Import']) . "/" . $escaper->escapeHtml($lang['Export']) . "</a>\n";
										echo "</li>\n";
									}

									// If the Assessments Extra is enabled
									if (assessments_extra())
									{
										echo ($active == "ActiveAssessments" ? "<li class=\"active\">\n" : "<li>\n");
										echo "<a href=\"active_assessments.php\">" . $escaper->escapeHtml($lang['ActiveAssessments']) . "</a>\n";
										echo "</li>\n";
									}

									echo ($active == "Extras" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"extras.php\">" . $escaper->escapeHtml($lang['Extras']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "Announcements" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"announcements.php\">" . $escaper->escapeHtml($lang['Announcements']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "Register" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"register.php\">" . $escaper->escapeHtml($lang['Register']) . " &amp; " . $escaper->escapeHtml($lang['Upgrade']) . "</a>\n";
									echo "</li>\n";
									echo ($active == "About" ? "<li class=\"active\">\n" : "<li>\n");
									echo "<a href=\"about.php\">" . $escaper->escapeHtml($lang['About']) . "</a>\n";
									echo "</li>\n";
									echo "</ul>\n";
								}

								/**********************************************
								* FUNCTION: VIEW RISKS AND ASSETS SELECTIONS *
								**********************************************/
								function view_risks_and_assets_selections($report)
								{
									global $lang;
									global $escaper;

									echo "<form name=\"select_report\" method=\"post\" action=\"\">\n";
									echo "<div class=\"row-fluid\">\n";
									echo "<div class=\"span12\">\n";
									echo "<a href=\"javascript:;\" onclick=\"javascript: closeSearchBox()\"><img src=\"../images/X-100.png\" width=\"10\" height=\"10\" align=\"right\" /></a>\n";

									// Report Selection
									echo "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
									echo "<tr>\n";
									echo "<td>" . $escaper->escapeHtml($lang['Report']) . ":&nbsp;</td>\n";
									echo "<td>\n";
									echo "<select id=\"report\" name=\"report\" onchange=\"javascript: submit()\">\n";
									echo "<option value=\"0\"" . ($report == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['RisksByAsset']) . "</option>\n";
									echo "<option value=\"1\"" . ($report == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['AssetsByRisk']) . "</option>\n";
									echo "</select>\n";
									echo "</td>\n";
									echo "</tr>\n";
									echo "</table>\n";

									echo "</div>\n";
									echo "</div>\n";
									echo "</form>\n";

								}

								/******************************************
								* FUNCTION: VIEW GET RISKS BY SELECTIONS *
								******************************************/
								function view_get_risks_by_selections($status=0, $group=0, $sort=0, $id=true, $risk_status=false, $subject=true, $reference_id=false, $regulation=false, $control_number=false, $location=false, $source=false, $category=false, $team=false, $technology=false, $owner=false, $manager=false, $submitted_by=false, $scoring_method=false, $calculated_risk=true, $submission_date=true, $review_date=false, $project=false, $mitigation_planned=true, $management_review=true, $days_open=false, $next_review_date=false, $next_step=false, $affected_assets=false, $planning_strategy=false, $mitigation_effort=false, $mitigation_cost=false, $mitigation_owner=false, $mitigation_team=false, $risk_assessment=false, $additional_notes=false, $current_solution=false, $security_recommendations=false, $security_requirements=false)
								{
									global $lang;
									global $escaper;

									echo "<form name=\"get_risks_by\" method=\"post\" action=\"dynamic_risk_report.php\">\n";
									echo "<div class=\"row-fluid\">\n";
									echo "<div class=\"span12\">\n";
									echo "<a href=\"javascript:;\" onclick=\"javascript: closeSearchBox()\"><img src=\"../images/X-100.png\" width=\"10\" height=\"10\" align=\"right\" /></a>\n";
									echo "</div>\n";
									echo "</div>\n";
									echo "<div class=\"row-fluid\">\n";

									// Risk Status Selection
									echo "<div class=\"span4\">\n";
									echo "<div class=\"well\">\n";
									echo "<h4>" . $escaper->escapeHtml($lang['Status']) . ":</h4>\n";
									echo "<select id=\"status\" name=\"status\" onchange=\"javascript: submit()\">\n";
									echo "<option value=\"0\"" . ($status == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['OpenRisks']) . "</option>\n";
									echo "<option value=\"1\"" . ($status == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ClosedRisks']) . "</option>\n";
									echo "<option value=\"2\"" . ($status == 2 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['AllRisks']) . "</option>\n";
									echo "</select>\n";
									echo "</div>\n";
									echo "</div>\n";

									// Group By Selection
									echo "<div class=\"span4\">\n";
									echo "<div class=\"well\">\n";
									echo "<h4>" . $escaper->escapeHtml($lang['GroupBy']) . ":</h4>\n";
									echo "<select id=\"group\" name=\"group\" onchange=\"javascript: submit()\">\n";
									echo "<option value=\"0\"" . ($group == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['None']) . "</option>\n";
									echo "<option value=\"5\"" . ($group == 5 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Category']) . "</option>\n";
									echo "<option value=\"11\"" . ($group == 11 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ControlRegulation']) . "</option>\n";
									echo "<option value=\"14\"" . ($group == 14 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['MonthSubmitted']) . "</option>\n";
									echo "<option value=\"13\"" . ($group == 13 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['NextStep']) . "</option>\n";
									echo "<option value=\"8\"" . ($group == 8 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Owner']) . "</option>\n";
									echo "<option value=\"9\"" . ($group == 9 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['OwnersManager']) . "</option>\n";
									echo "<option value=\"12\"" . ($group == 12 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Project']) . "</option>\n";
									echo "<option value=\"1\"" . ($group == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['RiskLevel']) . "</option>\n";
									echo "<option value=\"10\"" . ($group == 10 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['RiskScoringMethod']) . "</option>\n";
									echo "<option value=\"4\"" . ($group == 4 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['RiskSource']) . "</option>\n";
									echo "<option value=\"2\"" . ($group == 2 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Status']) . "</option>\n";
									echo "<option value=\"3\"" . ($group == 3 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['SiteLocation']) . "</option>\n";
									echo "<option value=\"6\"" . ($group == 6 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Team']) . "</option>\n";
									echo "<option value=\"7\"" . ($group == 7 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Technology']) . "</option>\n";
									echo "</select>\n";
									echo "</div>\n";
									echo "</div>\n";

									// Sort By Selection
									echo "<div class=\"span4\">\n";
									echo "<div class=\"well\">\n";
									echo "<h4>" . $escaper->escapeHtml($lang['SortBy']) . ":</h4>\n";
									echo "<select id=\"sort\" name=\"sort\" onchange=\"javascript: submit()\">\n";
									echo "<option value=\"0\"" . ($sort == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['CalculatedRisk']) . "</option>\n";
									echo "<option value=\"1\"" . ($sort == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ID']) . "</option>\n";
									echo "<option value=\"2\"" . ($sort == 2 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Subject']) . "</option>\n";
									echo "</select>\n";
									echo "</div>\n";
									echo "</div>\n";

									echo "</div>\n";

									echo "<div class=\"row-fluid\">\n";

									echo "<div class=\"span8\">\n";
									echo "<div class=\"well\">\n";
									echo "<div class=\"row-fluid\">\n";
									echo "<h4>" . $escaper->escapeHtml($lang['RiskColumns']) . ":</h4>\n";
									echo "<div class=\"span4\">\n";
									echo "<table border=\"0\">\n";
									echo "<tr><td><input class=\"hidden-checkbox\" type=\"checkbox\" name=\"id\" id=\"checkbox_id\"" . ($id == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_id()\" /> <label for=\"checkbox_id\">". $escaper->escapeHtml($lang['ID']) ."</label> </td></tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"risk_status\" id=\"checkbox_risk_status\"" . ($risk_status == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_status()\" />
									<label for=\"checkbox_risk_status\">". $escaper->escapeHtml($lang['Status']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"subject\" id=\"checkbox_subject\"" . ($subject == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_subject()\" />
									<label for=\"checkbox_subject\">". $escaper->escapeHtml($lang['Subject']) ."</label>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"reference_id\" id=\"checkbox_reference_id\"" . ($reference_id == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_reference_id()\" />
									<label for=\"checkbox_reference_id\">". $escaper->escapeHtml($lang['ExternalReferenceId']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"regulation\" id=\"checkbox_regulation\"" . ($regulation == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_regulation()\" />
									<label for=\"checkbox_regulation\">". $escaper->escapeHtml($lang['ControlRegulation']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"control_number\" id=\"checkbox_control_number\"" . ($control_number == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_control_number()\" />
									<label for=\"checkbox_control_number\">". $escaper->escapeHtml($lang['ControlNumber']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"location\" id=\"checkbox_location\"" . ($location == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_location()\" />
									<label for=\"checkbox_location\">". $escaper->escapeHtml($lang['SiteLocation']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"source\" id=\"checkbox_source\"" . ($source == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_source()\" />
									<label for=\"checkbox_source\">". $escaper->escapeHtml($lang['RiskSource']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"category\" id=\"checkbox_category\"" . ($category == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_category()\" />
									<label for=\"checkbox_category\">". $escaper->escapeHtml($lang['Category']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"team\" id=\"checkbox_team\"" . ($team == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_team()\" />
									<label for=\"checkbox_team\">". $escaper->escapeHtml($lang['Team']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"technology\" id=\"checkbox_technology\"" . ($technology == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_technology()\" />
									<label for=\"checkbox_technology\">". $escaper->escapeHtml($lang['Technology']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"owner\" id=\"checkbox_owner\"" . ($owner == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_owner()\" />
									<label for=\"checkbox_owner\">". $escaper->escapeHtml($lang['Owner']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"manager\" id=\"checkbox_manager\"" . ($manager == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_manager()\" />
									<label for=\"checkbox_manager\">". $escaper->escapeHtml($lang['OwnersManager']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"submitted_by\" id=\"checkbox_submitted_by\"" . ($submitted_by == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_submitted_by()\" />
									<label for=\"checkbox_submitted_by\">". $escaper->escapeHtml($lang['SubmittedBy']) ."</label>
									</td>
									</tr>\n";
									echo "</table>\n";
									echo "</div>\n";
									echo "<div class=\"span4\">\n";
									echo "<table border=\"0\">\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"scoring_method\" id=\"checkbox_scoring_method\"" . ($scoring_method == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_scoring_method()\" />
									<label for=\"checkbox_scoring_method\">". $escaper->escapeHtml($lang['RiskScoringMethod']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"calculated_risk\" id=\"checkbox_calculated_risk\"" . ($calculated_risk == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_calculated_risk()\" />
									<label for=\"checkbox_calculated_risk\">". $escaper->escapeHtml($lang['CalculatedRisk']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"submission_date\" id=\"checkbox_submission_date\"" . ($submission_date == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_submission_date()\" />
									<label for=\"checkbox_submission_date\">". $escaper->escapeHtml($lang['SubmissionDate']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"project\" id=\"checkbox_project\"" . ($project == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_project()\" />
									<label for=\"checkbox_project\">". $escaper->escapeHtml($lang['Project']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"days_open\" id=\"checkbox_days_open\"" . ($days_open == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_days_open()\" />
									<label for=\"checkbox_days_open\">". $escaper->escapeHtml($lang['DaysOpen']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"affected_assets\" id=\"AffectedAssets\"" . ($affected_assets == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_affected_assets()\" />
									<label for=\"AffectedAssets\">". $escaper->escapeHtml($lang['AffectedAssets']) ."</label>
									</td>
									</tr>\n";
                                    echo "<tr>
                                    <td>
                                    <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"risk_assessment\" id=\"RiskAssessment\"" . ($risk_assessment == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_risk_assessment()\" />
                                    <label for=\"RiskAssessment\">". $escaper->escapeHtml($lang['RiskAssessment']) ."</label>
                                    </td>
                                    </tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"additional_notes\" id=\"AdditionalNotes\"" . ($additional_notes == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_additional_notes()\" />
									<label for=\"AdditionalNotes\">". $escaper->escapeHtml($lang['AdditionalNotes']) ."</label>
									</td>
									</tr>\n";
									echo "</table>\n";
									echo "</div>\n";
									echo "</div>\n";
									echo "</div>\n";
									echo "</div>\n";

									echo "<div class=\"span4\">\n";
									echo "<div class=\"well\">\n";
									echo "<div class=\"row-fluid\">\n";
									echo "<h4>" . $escaper->escapeHtml($lang['MitigationColumns']) . ":</h4>\n";
									echo "<table border=\"0\">\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"mitigation_planned\" id=\"checkbox_mitigation_planned\"" . ($mitigation_planned == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_mitigation_planned()\" />
									<label for=\"checkbox_mitigation_planned\">". $escaper->escapeHtml($lang['MitigationPlanned']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"planning_strategy\" id=\"checkbox_planning_strategy\"" . ($planning_strategy == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_planning_strategy()\" />
									<label for=\"checkbox_planning_strategy\">". $escaper->escapeHtml($lang['PlanningStrategy']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"mitigation_effort\" id=\"checkbox_mitigation_effort\"" . ($mitigation_effort == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_mitigation_effort()\" />
									<label for=\"checkbox_mitigation_effort\">". $escaper->escapeHtml($lang['MitigationEffort']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"mitigation_cost\" id=\"checkbox_mitigation_cost\"" . ($mitigation_cost == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_mitigation_cost()\" />
									<label for=\"checkbox_mitigation_cost\">". $escaper->escapeHtml($lang['MitigationCost']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"mitigation_owner\" id=\"checkbox_mitigation_owner\"" . ($mitigation_owner == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_mitigation_owner()\" />
									<label for=\"checkbox_mitigation_owner\">". $escaper->escapeHtml($lang['MitigationOwner']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"mitigation_team\" id=\"checkbox_mitigation_team\"" . ($mitigation_team == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_mitigation_team()\" />
									<label for=\"checkbox_mitigation_team\">". $escaper->escapeHtml($lang['MitigationTeam']) ."</label>
									</td>
									</tr>\n";
                                    echo "<tr>
                                    <td>
                                    <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"current_solution\" id=\"CurrentSolution\"" . ($current_solution == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_current_solution()\" />
                                    <label for=\"CurrentSolution\">". $escaper->escapeHtml($lang['CurrentSolution']) ."</label>
                                    </td>
                                    </tr>\n";
                                    echo "<tr>
                                    <td>
                                    <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"security_recommendations\" id=\"SecurityRecommendations\"" . ($security_recommendations == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_security_recommendations()\" />
                                    <label for=\"SecurityRecommendations\">". $escaper->escapeHtml($lang['SecurityRecommendations']) ."</label>
                                    </td>
                                    </tr>\n";
                                    echo "<tr>
                                    <td>
                                    <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"security_requirements\" id=\"SecurityRequirements\"" . ($security_requirements == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_security_requirements()\" />
                                    <label for=\"SecurityRequirements\">". $escaper->escapeHtml($lang['SecurityRequirements']) ."</label>
                                    </td>
                                    </tr>\n";
									echo "</table>\n";
									echo "</div>\n";
									echo "</div>\n";
									echo "</div>\n";

									echo "<div class=\"span4\">\n";
									echo "<div class=\"well\">\n";
									echo "<div class=\"row-fluid\">\n";
									echo "<h4>" . $escaper->escapeHtml($lang['ReviewColumns']) . ":</h4>\n";
									echo "<table border=\"0\">\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"management_review\" id=\"checkbox_management_review\"" . ($management_review == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_management_review()\" />
									<label for=\"checkbox_management_review\">". $escaper->escapeHtml($lang['ManagementReview']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"review_date\" id=\"checkbox_review_date\"" . ($review_date == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_review_date()\" />
									<label for=\"checkbox_review_date\">". $escaper->escapeHtml($lang['ReviewDate']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"next_review_date\" id=\"checkbox_next_review_date\"" . ($next_review_date == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_next_review_date()\" />
									<label for=\"checkbox_next_review_date\">". $escaper->escapeHtml($lang['NextReviewDate']) ."</label>
									</td>
									</tr>\n";
									echo "<tr>
									<td>
									<input class=\"hidden-checkbox\" type=\"checkbox\" name=\"next_step\" id=\"checkbox_next_step\"" . ($next_step == true ? " checked=\"yes\"" : "") . " onchange=\"javascript: check_next_step()\" />
									<label for=\"checkbox_next_step\">". $escaper->escapeHtml($lang['NextStep']) ."</label>
									</td>
									</tr>\n";
									echo "</table>\n";
									echo "</div>\n";
									echo "</div>\n";
									echo "</div>\n";

									echo "</div>\n";
									echo "</form>\n";
								}

								/************************************************
								* FUNCTION: DISPLAY SIMPLE AUTOCOMPLETE SCRIPT *
								************************************************/
								function display_simple_autocomplete_script($assets)
								{
									global $escaper;

									echo "<script>\n";
									echo "  $(function() {\n";
										echo "    var availableAssets = [\n";

										// For each asset
										foreach ($assets as $asset)
										{
											// Display the asset name as an available asset
											echo "      \"" . $escaper->escapeHtml($asset['name']) . "\",\n";
										}

										echo "    ];\n";
										echo "    function split( val ) {\n";
										echo "      return val.split( /,\s*/ );\n";
										echo "    }\n";
										echo "    function extractLast( term ) {\n";
											echo "      return split( term ).pop();\n";
											echo "    }\n";
											echo "    $( \"#asset_name\" )\n";
											echo "      // don't navigate away from the field on tab when selecting an item\n";
											echo "      .bind( \"keydown\", function( event ) {\n";
												echo "        if ( event.keyCode === $.ui.keyCode.TAB && $( this ).autocomplete( \"instance\" ).menu.active ) {\n";
													echo "          event.preventDefault();\n";
													echo "        }\n";
													echo "      })\n";
													echo "      .autocomplete({\n";
														echo "        minLength: 0,\n";
														echo "        source: function( request, response ) {\n";
															echo "        // delegate back to autocomplete, but extract the last term\n";
															echo "        response( $.ui.autocomplete.filter(\n";
															echo "        availableAssets, extractLast( request.term ) ) );\n";
															echo "      },\n";
															echo "      select: function( event, ui ) {\n";
																echo "        var terms = split( this.value );\n";
																echo "        // remove the current input\n";
																echo "        terms.pop();\n";
																echo "        // add the selected item\n";
																echo "        terms.push( ui.item.value );\n";
																echo "        return false;\n";
																echo "      }\n";
																echo "    });\n";
																echo "  });\n";
																echo "</script>\n";
															}

															/***********************************************
															* FUNCTION: DISPLAY ASSET AUTOCOMPLETE SCRIPT *
															***********************************************/
															function display_asset_autocomplete_script($assets)
															{
																global $escaper;

																echo "<script>\n";
																echo "  $(function() {\n";
																	echo "    availableAssets = [\n";

																	// For each asset
																	foreach ($assets as $asset)
																	{
																		// Display the asset name as an available asset
																		echo "      \"" . $escaper->escapeHtml($asset['name']) . "\",\n";
																	}

																	echo "    ];\n";
																	echo "    split = function( val ) {\n";
																	echo "      return val.split( /,\s*/ );\n";
																	echo "    }\n";
																	echo "    extractLast = function ( term ) {\n";
																		echo "      return split( term ).pop();\n";
																		echo "    }\n";
																		echo "    $( \"#assets\" )\n";
																		echo "      // don't navigate away from the field on tab when selecting an item\n";
																		echo "      .bind( \"keydown\", function( event ) {\n";
																			echo "        if ( event.keyCode === $.ui.keyCode.TAB && $( this ).autocomplete( \"instance\" ).menu.active ) {\n";
																				echo "          event.preventDefault();\n";
																				echo "        }\n";
																				echo "      })\n";
																				echo "      .autocomplete({\n";
																					echo "        minLength: 0,\n";
																					echo "        source: function( request, response ) {\n";
																						echo "        // delegate back to autocomplete, but extract the last term\n";
																						echo "        response( $.ui.autocomplete.filter(\n";
																						echo "        availableAssets, extractLast( request.term ) ) );\n";
																						echo "      },\n";
																						echo "      focus: function() {\n";
																							echo "        // prevent value inserted on focus\n";
																							echo "        return false;\n";
																							echo "      },\n";
																							echo "      select: function( event, ui ) {\n";
																								echo "        var terms = split( this.value );\n";
																								echo "        // remove the current input\n";
																								echo "        terms.pop();\n";
																								echo "        // add the selected item\n";
																								echo "        terms.push( ui.item.value );\n";
																								echo "        // add placeholder to get the comma-and-space at the end\n";
																								echo "        terms.push( \"\" );\n";
																								echo "        this.value = terms.join( \", \" );\n";
																								echo "        return false;\n";
																								echo "      }\n";
																								echo "    });\n";
																								echo "  });\n";
																								echo "</script>\n";
																							}

																							/*********************************************
																							* FUNCTION: DISPLAY REGISTRATION TABLE EDIT *
																							*********************************************/
																							function display_registration_table_edit($name="", $company="", $title="", $phone="", $email="")
																							{
																								global $escaper;
																								global $lang;

																								echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['FullName']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"text\" name=\"name\" id=\"name\" value=\"" . $escaper->escapeHtml($name) . "\" /></td>\n";
																								echo "  </tr>\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['Company']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"text\" name=\"company\" id=\"company\" value=\"" . $escaper->escapeHtml($company) . "\" /></td>\n";
																								echo "  </tr>\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['JobTitle']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"text\" name=\"title\" id=\"title\" value=\"" . $escaper->escapeHtml($title) . "\" /></td>\n";
																								echo "  </tr>\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['Phone']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"tel\" name=\"phone\" id=\"phone\" value=\"" . $escaper->escapeHtml($phone) . "\" /></td>\n";
																								echo "  </tr>\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['EmailAddress']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"email\" name=\"email\" id=\"email\" value=\"" . $escaper->escapeHtml($email) . "\" /></td>\n";
																								echo "  </tr>\n";
																								echo "</table>\n";
																								echo "<div class=\"form-actions\">\n";
																								echo "  <button type=\"submit\" name=\"register\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Register']) . "</button>\n";
																								echo "</div>\n";
																							}

																							/****************************************
																							* FUNCTION: DISPLAY REGISTRATION TABLE *
																							****************************************/
																							function display_registration_table($name="", $company="", $title="", $phone="", $email="")
																							{
																								global $escaper;
																								global $lang;

																								echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['FullName']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"text\" name=\"name\" id=\"name\" value=\"" . $escaper->escapeHtml($name) . "\" title=\"" . $escaper->escapeHtml($name) . "\" disabled=\"disabled\" /></td>\n";
																								echo "  </tr>\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['Company']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"text\" name=\"company\" id=\"company\" value=\"" . $escaper->escapeHtml($company) . "\" title=\"" . $escaper->escapeHtml($company) . "\" disabled=\"disabled\" /></td>\n";
																								echo "  </tr>\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['JobTitle']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"text\" name=\"title\" id=\"title\" value=\"" . $escaper->escapeHtml($title) . "\" title=\"" . $escaper->escapeHtml($title) . "\" disabled=\"disabled\" /></td>\n";
																								echo "  </tr>\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['Phone']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"tel\" name=\"phone\" id=\"phone\" value=\"" . $escaper->escapeHtml($phone) . "\" title=\"" . $escaper->escapeHtml($phone) . "\" disabled=\"disabled\" /></td>\n";
																								echo "  </tr>\n";
																								echo "  <tr>\n";
																								echo "    <td>" . $escaper->escapeHtml($lang['EmailAddress']) . ":&nbsp;</td>\n";
																								echo "    <td><input type=\"email\" name=\"email\" id=\"email\" value=\"" . $escaper->escapeHtml($email) . "\" title=\"" . $escaper->escapeHtml($email) . "\" disabled=\"disabled\" /></td>\n";
																								echo "  </tr>\n";
																								echo "</table>\n";
																								echo "<div class=\"form-actions\">\n";
																								echo "  <button type=\"submit\" name=\"update\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
																								echo "</div>\n";
																							}

																							/*****************************
																							* FUNCTION: DISPLAY UPGRADE *
																							*****************************/
																							function display_upgrade()
																							{
																								// If the upgrade extra exists
																								if (file_exists(realpath(__DIR__ . '/../extras/upgrade/index.php')))
																								{
																									// Require the upgrade extra file
																									require_once(realpath(__DIR__ . '/../extras/upgrade/index.php'));

																									display_upgrades();
																								}
																								// The ugprade does not exist
																								else
																								{
																									echo "There are issues obtaining the upgrade extra.  Check the error log for more information.<br />\n";
																								}
																							}

																							/*********************************
																							* FUNCTION: DISPLAY ASSESSMENTS *
																							*********************************/
																							function display_assessment_links()
																							{
																								global $escaper;

																								// Get the assessments
																								$assessments = get_assessment_names();

																								// Start the list
																								echo "<ul class=\"nav nav-pills nav-stacked \">\n";

																								// For each entry in the assessments array
																								foreach ($assessments as $assessment)
																								{
																									// Get the assessment values
																									$assessment_name = $assessment['name'];
																									$assessment_id = (int)$assessment['id'];

																									// Display the assessment
																									echo "<li style=\"text-align:center\"><a href=\"index.php?action=view&assessment_id=" . $escaper->escapeHtml($assessment_id) . "\">" . $escaper->escapeHTML($assessment_name) . "</a></li>\n";
																								}

																								// End the list
																								echo "</ul>\n";
																							}

																							/*******************************************
																							* FUNCTION: DISPLAY ADD DELETE ROW SCRIPT *
																							*******************************************/
																							function display_add_delete_row_script()
																							{
																								echo "<script language=\"javascript\">\n";
																								echo "function addRow(tableID) {\n";
																									echo "var table = document.getElementById(tableID);\n";
																									echo "var rowCount = table.rows.length;\n";
																									echo "var row = table.insertRow(rowCount);\n";
																									echo "var colCount = table.rows[1].cells.length;\n";
																									echo "for(var i=0; i<colCount; i++) {\n";
																										echo "var newcell = row.insertCell(i);\n";
																										echo "newcell.innerHTML = table.rows[1].cells[i].innerHTML;\n";
																										echo "switch(newcell.childNodes[0].type) {\n";
																											echo "case \"text\":\n";
																											echo "newcell.childNodes[0].value = \"\";\n";
																											echo "break;\n";
																											echo "case \"checkbox\":\n";
																											echo "newcell.childNodes[0].checked = false;\n";
																											echo "break;\n";
																											echo "case \"select-one\":\n";
																											echo "newcell.childNodes[0].selectedIndex = 0;\n";
																											echo "break;\n";
																											echo "}\n";
																											echo "}\n";
																											echo "}\n";
																											echo "function deleteRow(tableID) {\n";
																												echo "try {\n";
																													echo "var table = document.getElementById(tableID);\n";
																													echo "var rowCount = table.rows.length;\n";
																													echo "if (rowCount > 3) {\n";
																														echo "table.deleteRow(rowCount-1);\n";
																														echo "}\n";
																														echo "else {\n";
																															echo "alert(\"Cannot delete all the rows.\");\n";
																															echo "}\n";
																															echo "}catch(e) {\n";
																																echo "alert(e);\n";
																																echo "}\n";
																																echo "}\n";
																																echo "</script>\n";
																															}

																															/***********************************************
																															* FUNCTION: DISPLAY VIEW ASSESSMENT QUESTIONS *
																															***********************************************/
																															function display_view_assessment_questions($assessment_id = NULL)
																															{
																																global $escaper;
																																global $lang;

																																echo "<div class=\"row-fluid\">\n";
																																echo "<div class=\"span12\">\n";
																																echo "<div class=\"hero-unit\">\n";
																																echo "<form name=\"submit_assessment\" method=\"post\" action=\"\">\n";

																																// If the assessment id was sent by get
																																if (isset($_GET['assessment_id']))
																																{
																																	// Set the assessment id
																																	$assessment_id = $_GET['assessment_id'];
																																}
																																// If the assessment id was sent by post
																																else if (isset($_POST['assessment_id']))
																																{
																																	// Set the assessment id
																																	$assessment_id = $_POST['assessment_id'];
																																}

																																// Add a hidden value for the assessment id
																																echo "<input type=\"hidden\" name=\"assessment_id\" value=\"" . $escaper->escapeHtml($assessment_id) . "\" />\n";

																																// Add a hidden value for the action
																																echo "<input type=\"hidden\" name=\"action\" value=\"submit\" />\n";

																																// Get the assessment name
																																$assessment = get_assessment_names($assessment_id);
																																$assessment_name = $assessment['name'];
																																echo "<center><h3>" . $escaper->escapeHtml($assessment_name) . "</h3></center>\n";
																																echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
																																echo "<tr>\n";
																																echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetName']) . ":&nbsp;&nbsp;</th>\n";
																																echo "<th><input type=\"text\" name=\"asset\" /></th>\n";
																																echo "</tr>\n";
																																echo "</table>\n";
																																// Get the assessment
																																$assessment = get_assessment($assessment_id);

																																// Set a variable to track the current question
																																$current_question = "";

																																// For each row in the array
																																foreach ($assessment as $row)
																																{
																																	$question = $row['question'];
																																	$question_id = (int)$row['question_id'];

																																	// If the question is new
																																	if ($current_question != $question)
																																	{
																																		// If this is not the first question
																																		if ($current_question != "")
																																		{
																																			// End the previous answer table
																																			echo "</table>\n";
																																			echo "<div class=\"end-question\"></div>";

																																			// Display a horizontal rule
																																			// echo "<hr />\n";
																																		}

																																		// Set the current question to the question
																																		$current_question = $question;

																																		// Display the question
																																		echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
																																		echo "<tr>\n";
																																		echo "<th align=\"left\"><div class=\"question\">" . $escaper->escapeHtml($question) . "<div></th>\n";
																																		echo "</tr>\n";
																																		echo "</table>\n";

																																		// Display the answers
																																		echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
																																	}

																																	// Set the answer values
																																	$answer = $row['answer'];
																																	$answer_id = (int)$row['answer_id'];

																																	// Display the answer
																																	echo "<tr>\n";
																																	echo "<td><input class=\"hidden-radio\" id=\"".$answer_id."\" type=\"radio\" name=\"" . $question_id . "\" value=\"" . $answer_id . "\" /><label for=\"".$answer_id."\">".$escaper->escapeHtml($answer)."</label> </td>\n";
																																	echo "</tr>\n";
																																}

																																// End the table
																																echo "</table>\n";


																																echo "<div class=\"form-actions\"><input type=\"submit\" name=\"submit_assessment\" value=\"" . $escaper->escapeHtml($lang['Submit']) . "\" /></div>\n";
																																echo "</form>\n";
																																echo "</div>\n";
																																echo "</div>\n";
																																echo "</div>\n";
																															}

																															/***********************************
																															* FUNCTION: DISPLAY PENDING RISKS *
																															***********************************/
																															function display_pending_risks()
																															{
																																global $escaper;
																																global $lang;

																																echo "<div class=\"row-fluid\">\n";
																																echo "<div class=\"span12\">\n";

																																// Get the pending risks
																																$risks = get_pending_risks();

																																// For each pending risk
																																foreach($risks as $risk)
																																{
																																	// Get the assessment name
																																	$assessment = get_assessment_names($risk['assessment_id']);

																																	echo "<div class=\"hero-unit\">\n";
																																	echo "<form name=\"submit_risk\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
																																	echo "<input type=\"hidden\" name=\"assessment_id\" value=\"" . $escaper->escapeHtml($risk['assessment_id']) . "\" />\n";
																																	echo "<input type=\"hidden\" name=\"pending_risk_id\" value=\"" . $escaper->escapeHtml($risk['id']) . "\" />\n";
																																	echo "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
																																	echo "<tr>\n";
																																	echo "<td style=\"white-space: nowrap;\">".$lang['SubmissionDate'] . ":&nbsp;&nbsp;</td>\n";
																																	echo "<td width=\"99%\"><input type=\"text\"  style=\"width: 97%;\" name=\"submission_date\" value=\"" . $escaper->escapeHtml($risk['submission_date']) . "\" /></td>\n";
																																	echo "</tr>\n";
																																	echo "<tr>\n";
																																	echo "<td style=\"white-space: nowrap;\">".$lang['Subject'] . ":&nbsp;&nbsp;</td>\n";
																																	echo "<td width=\"99%\"><input type=\"text\" style=\"width: 97%;\" name=\"subject\" value=\"" . $escaper->escapeHtml($risk['subject']) . "\" /></td>\n";
																																	echo "</tr>\n";
																																	echo "<tr>\n";
																																	echo "<td style=\"white-space: nowrap;\">".$lang['RiskScore'] . ":&nbsp;&nbsp;</td>\n";
																																	echo "<td width=\"99%\"><input type=\"number\" style=\"width: 97%;\" min=\"0\" max=\"10\" name=\"risk_score\" value=\"" . $escaper->escapeHtml($risk['score']) . "\" /></td>\n";
																																	echo "</tr>\n";
																																	echo "<tr>\n";
																																	echo "<td style=\"white-space: nowrap;\">".$lang['Owner'] . ":&nbsp;&nbsp;</td>\n";
																																	echo "<td width=\"99%\">\n";
																																	create_dropdown("user", $risk['owner'], "owner");
																																	echo "</td>\n";
																																	echo "</tr>\n";
																																	echo "<tr>\n";
																																	echo "<td style=\"white-space: nowrap;\">".$lang['AssetName'] . ":&nbsp;&nbsp;</td>\n";
																																	echo "<td width=\"99%\"><input type=\"text\" style=\"width: 97%;\" name=\"asset\" value=\"" . $escaper->escapeHtml($risk['asset']) . "\" /></td>\n";
																																	echo "</tr>\n";
																																	echo "<tr>\n";
																																	echo "<td style=\"white-space: nowrap;\">".$lang['AdditionalNotes'] . ":&nbsp;&nbsp;</td>\n";
																																	echo "<td width=\"99%\"><textarea name=\"note\" style=\"width: 97%;\" cols=\"50\" rows=\"3\" id=\"note\">Risk created using the &quot;" . $escaper->escapeHtml($assessment['name']) . "&quot; assessment.</textarea></td>\n";
																																	echo "</tr>\n";
																																	echo "</table>\n";
																																	echo "<div class=\"form-actions\">\n";
																																	echo "<button type=\"submit\" name=\"add\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Add']) . "</button>\n";
																																	echo "<button type=\"submit\" name=\"delete\" class=\"btn\">" . $escaper->escapehtml($lang['Delete']) . "</button>\n";
																																	echo "</div>\n";
																																	echo "</form>\n";
																																	echo "</div>\n";
																																}

																																echo "</div>\n";
																																echo "</div>\n";
																																echo "</div>\n";
																															}

																															?>
