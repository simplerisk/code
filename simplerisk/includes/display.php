<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
use Ghunti\HighchartsPHP\Highchart;

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

require_once(realpath(__DIR__ . '/displayrisks.php'));

require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/assessments.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/governance.php'));

/****************************
* FUNCTION: VIEW SCORE HTML *
****************************/
function view_score_html($risk_id, $calculated_risk, $mitigation_percent)
{
    global $lang, $escaper;
    // Inherent Risk
    echo "<div class=\"score \" style=\"background-color: ". $escaper->escapeHtml(get_risk_color($calculated_risk)) ."\">";
        echo $escaper->escapeHtml($lang['InherentRisk'])."<span id=\"inherent_risk_score\">".$escaper->escapeHtml($calculated_risk)."</span>".$escaper->escapeHtml(get_risk_level_name($calculated_risk));
    echo "</div>";

    // Residual Risk
    if(!$calculated_risk || $calculated_risk == "0.0")
    {
        $residual_risk = "0.0";
    }
    else
    {
        $residual_risk = get_residual_risk($risk_id);
    }
    echo "<div class=\"score \" style=\"background-color: ". $escaper->escapeHtml(get_risk_color($residual_risk)) ."\">";
        echo $escaper->escapeHtml($lang['ResidualRisk'])."<span id=\"residual_risk_score\">".$escaper->escapeHtml($residual_risk)."</span>".$escaper->escapeHtml(get_risk_level_name($residual_risk));
    echo "</div>";
}

/****************************
* FUNCTION: VIEW TOP TABLE *
****************************/
function view_top_table($risk_id, $calculated_risk, $subject, $status, $show_details = false, $mitigation_percent = 0, $display_risk = true)
{
    global $lang, $escaper;

    // Decrypt fields
    $subject = try_decrypt($subject);

    echo "<div class=\"score--wrapper\">";
        view_score_html($risk_id, $calculated_risk, $mitigation_percent);
    echo "</div>";

    echo "<div class=\"details--wrapper\">";

        echo "<div class=\"row-fluid\">";
            echo "<div class=\"span3\"><label>" . $escaper->escapeHtml($lang['IDNumber']) . ": <span class=\"large-text risk-id\">".$escaper->escapeHtml($risk_id)."</span></label></div>";
            echo "<div class=\"span5\"><label>" . $escaper->escapeHtml($lang['Status']) . ": <span class=\"large-text status-text\">".$escaper->escapeHtml($status)."</span></label></div>";
            if($display_risk == true){
                echo "<div class=\"span4\">";

                    echo "<div class=\"btn-group pull-right\">\n";
                        echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">". $escaper->escapeHtml($lang['RiskActions']) ."<span class=\"caret\"></span></a>\n";
                        echo "<ul class=\"dropdown-menu\">\n";

                        // If the risk is closed, offer to reopen
                        if ($status == "Closed") { echo "<li><a class='reopen-risk' href=\"reopen.php?id=".$escaper->escapeHtml($risk_id)."\">". $escaper->escapeHtml($lang['ReopenRisk']) ."</a></li>\n"; }
                        // Otherwise, offer to close
                        else{
                            // If the user has permission to close risks
		            if (has_permission("close_risks"))
			    {
				    echo "<li><a class='close-risk' href=\"close.php?id=".$escaper->escapeHtml($risk_id)."\">". $escaper->escapeHtml($lang['CloseRisk']) ."</a></li>\n";
			    }
                        }

			// If the user has permission to modify risks
			if (has_permission("modify_risks"))
			{
                            echo "<li><a class='edit-risk' href=\"view.php?action=editdetail&id=" . $escaper->escapeHtml($risk_id) . "\">". $escaper->escapeHtml($lang['EditRisk']) ."</a></li>\n";
			}

			// If the user has permission to plan mitigations
			if (has_permission("plan_mitigations"))
			{
                            echo "<li><a class='edit-mitigation' href=\"#\">". $escaper->escapeHtml($lang['PlanAMitigation']) ."</a></li>\n";
			}

			// Check the review permissions for this risk id
			$approved = check_review_permission_by_risk_id($risk_id);

                        // If the user has permission to review the current level
			if ($approved)
                        {
		            echo "<li><a class='perform-review' href=\"#\">". $escaper->escapeHtml($lang['PerformAReview']) ."</a></li>\n";
                        }


			// If the user has permission to modify risks
                        if (has_permission("modify_risks"))
                        {
                            echo "<li><a class='change-status' href=\"#\">" . $escaper->escapeHtml($lang['ChangeStatus']) . "</a></li>\n";
			}

            // If the user has permission to comment on risk management
            if (has_permission("comment_risk_management"))
            {
                            echo "<li><a href=\"#comment\" class='add-comment-menu'>". $escaper->escapeHtml($lang['AddAComment']) ."</a></li>\n";
            }
                // If the user has permission to plan mitigations
                if (has_permission("plan_mitigations"))
                {
                    echo "<li><a class='mark-unmitigation' href=\"#\">". $escaper->escapeHtml($lang['ResetMitigations']) ."</a></li>\n";
                }

                // If the user has permission to plan mitigations
                if (has_permission("modify_risks"))
                {
                    echo "<li><a class='mark-unreview' href=\"#\">". $escaper->escapeHtml($lang['ResetReviews']) ."</a></li>\n";
                }

                        echo "<li><a class='printable-veiw' href=\"print_view.php?id=" . $escaper->escapeHtml($risk_id) . "\" target=\"_blank\">". $escaper->escapeHtml($lang['PrintableView']) ."</a></li>\n";
                        echo "</ul>\n";
                    echo "</div>\n";
                echo "</div>";
            }
        echo "</div>";

        echo "<div class=\"row-fluid border-top\">";
            echo "<div class=\"span12\">";
                echo "<div id=\"static-subject\" class='static-subject'><label>Subject : <span class=\"large-text\">".$escaper->escapeHtml($subject)."</span>";

	        // If we are displaying the risk and the user has modify risk permissions
                if($display_risk == true && has_permission("modify_risks")){
                    echo "<div id=\"edit-subject\" class='edit-subject-btn' style=\"display:inline;margin:0 0 0 10px; font-size:20px;\"><i class=\"fa fa-edit\" aria-hidden=\"true\"></i></div>";
                }
                echo "</label></div>";
            
                echo "<form name=\"details\" method=\"post\" action=\"\">";
                    echo "<div class=\"edit-subject row-fluid\">";
                        echo "<div class=\"span9\">";
                            echo "<input maxlength=\"" . (int)get_setting('maximum_risk_subject_length', 300) . "\" type=\"text\" name=\"subject\" value=\"".$escaper->escapeHtml($subject)."\" style=\"max-width:none;\"/>";
                            echo "<input type=\"hidden\" name=\"riskid\" value=\"".$escaper->escapeHtml($risk_id)."\"/>";
                        echo "</div>";
                        echo "<div class=\"span3\">";
                            echo "<a href=\"/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=0\" class=\"btn cancel-edit-subject \" style=\"margin:0 5px 0 0;\" >Cancel</a>";
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

        echo "<table width=\"100\" height=\"100\" cellpadding=\"0\" cellspacing=\"0\" border=\"1\" class=\"" . $escaper->escapeHtml(get_risk_color($calculated_risk)) . "\">\n";
        echo "<tr>\n";
            echo "<td valign=\"middle\" halign=\"center\">\n";
                echo "<center style=\"padding-top: 10px; padding-bottom: 10px; height: 120px; width: 120px; background-color: ". $escaper->escapeHtml(get_risk_color($calculated_risk)) ."\">".$escaper->escapeHtml($lang['InherentRisk'])."<br />\n";
                echo "<font size=\"6\">" . $escaper->escapeHtml($calculated_risk) . "</font><br />\n";
                echo "(". $escaper->escapeHtml(get_risk_level_name($calculated_risk)) . ")\n";
                echo "</center>\n";
            echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
    echo "</td>\n";

    $residual_risk = get_residual_risk($id);
    echo "<td width=\"100\" valign=\"middle\" halign=\"center\">\n";
        echo "<table width=\"100\" height=\"100\" cellpadding=\"0\" cellspacing=\"0\" border=\"1\" class=\"" . $escaper->escapeHtml(get_risk_color($calculated_risk)) . "\">\n";
        echo "<tr>\n";
            echo "<td valign=\"middle\" halign=\"center\">\n";
                echo "<center style=\"padding-top: 10px; padding-bottom: 10px; height: 120px; width: 120px; background-color: ". $escaper->escapeHtml(get_risk_color($residual_risk)) ."\">\n".$escaper->escapeHtml($lang['ResidualRisk'])."<br />\n";
                echo "<font size=\"6\">" . $escaper->escapeHtml($residual_risk) . "</font><br />\n";
                echo "(". $escaper->escapeHtml(get_risk_level_name($residual_risk)) . ")\n";
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
* FUNCTION: ADD RISK DETAILS *
*******************************/
function add_risk_details($template_group_id = ""){
    global $lang;
    global $escaper;
    echo "<form name=\"submit_risk\" method=\"post\" action=\"\" enctype=\"multipart/form-data\" id=\"risk-submit-form\">\n";
        // If customization extra is enabled
        if(customization_extra())
        {
            // Include the extra
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
            if(!$template_group_id) {
                $group = get_default_template_group("risk");
                $template_group_id = $group["id"];
            }
            echo "<input type='hidden' name='template_group_id' id='template_group_id' value='{$template_group_id}' />";
            echo "<div class=\"row-fluid padded-bottom subject-field\">\n";
                echo "<div class=\"span2 text-right\">".$escaper->escapeHtml($lang['Subject']).":</div>\n";
                echo "<div class=\"span8\"><input maxlength=\"" . (int)get_setting('maximum_risk_subject_length', 300) . "\" title=\"".$escaper->escapeHtml($lang['Subject'])."\" required name=\"subject\" id=\"subject\" class=\"form-control\" type=\"text\"></div>\n";
            echo "</div>\n";

            $active_fields = get_active_fields("risk", $template_group_id);

            // Top panel
            echo "<div class=\"row-fluid top-panel\">\n";
                display_main_detail_fields_by_panel_add('top', $active_fields, $template_group_id);
            echo "</div>\n";
            echo "<div class=\"row-fluid\">\n";
                // Left Panel
                echo "<div class=\"span5 left-panel\">\n";
                    display_main_detail_fields_by_panel_add('left', $active_fields, $template_group_id);
                    echo "&nbsp;";
                echo "</div>\n";

                // Right Panel
                echo "<div class=\"span5 right-panel\">\n";
                    display_main_detail_fields_by_panel_add('right', $active_fields, $template_group_id);
                    echo "&nbsp;";
                echo "</div>\n";
            echo "</div>\n";

            // Bottom panel
            echo "<div class=\"row-fluid bottom-panel\">\n";
                display_main_detail_fields_by_panel_add('bottom', $active_fields, $template_group_id);
                echo "&nbsp;";
            echo "</div>\n";
        }
        else
        {
            echo "<div class=\"row-fluid padded-bottom subject-field\">\n";
                echo "<div class=\"span2 text-right\">".$escaper->escapeHtml($lang['Subject']).":</div>\n";
                echo "<div class=\"span8\"><input maxlength=\"" . (int)get_setting('maximum_risk_subject_length', 300) . "\" title=\"".$escaper->escapeHtml($lang['Subject'])."\" required name=\"subject\" id=\"subject\" class=\"form-control\" type=\"text\"></div>\n";
            echo "</div>\n";
            display_risk_mapping_edit([], "top");
            display_threat_mapping_edit([], "top");
            echo "<div class=\"row-fluid\">\n";
                echo "<!-- first coulmn -->\n";
                echo "<div class=\"span5 left-panel\">\n";
                    display_category_edit('');

                    display_location_edit('');

                    display_external_reference_id_edit('');

                    display_control_regulation_edit('');

                    display_control_number_edit('');

                    display_affected_assets_edit('');

                    display_technology_edit('');

                    display_team_edit('');

                    display_additional_stakeholders_edit('');

                    display_owner_edit('');

                    display_owners_manager_edit('');

                echo "</div>\n";
                echo "<!-- first coulmn end -->\n";

                echo "<!-- second coulmn -->\n";
                echo "<div class=\"span5 right-panel\">\n";

                    display_risk_source_edit('');

                    risk_score_method_html();

                    display_risk_assessment_title_edit('');

                    display_additional_notes_edit('');

                    display_jira_issue_key_edit('');

                    display_supporting_documentation_add();

                echo "</div>\n";
                echo "<!-- second column end -->\n";

            echo "</div>\n";
            display_risk_tags_edit();
        }

        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span10\">\n";
                echo "<div class=\"actions risk-form-actions\">\n";
                    echo "<span>" . $escaper->escapeHtml($lang['NewRiskInstruction']). "</span>\n";
                    echo "<button type=\"button\" name=\"submit\" class=\"btn btn-primary pull-right save-risk-form\">".$escaper->escapeHtml($lang['SubmitRisk'])."</button>\n";
                    echo "<input class=\"btn pull-right\" value=\"".$escaper->escapeHtml($lang['ClearForm'])."\" type=\"reset\">\n";
                echo "</div>\n";
            echo "</div>\n";
        echo "</div>\n";

    echo "</form>\n";

    echo "<script>\n";
        echo "$(document).ready(function(){\n";
//            echo "$(\"#team\").multiselect(\"selectAll\", false);\n";
            // echo "$('#team').multiselect('updateButtonText');\n";
        echo "})\n";
    echo "</script>\n";

}

/*******************************
* FUNCTION: VIEW RISK DETAILS *
*******************************/
function view_risk_details($id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping, $template_group_id="")
{
    global $lang;
    global $escaper;

    // Decrypt fields
    $subject = try_decrypt($subject);
    $assessment = try_decrypt($assessment);
    $notes = try_decrypt($notes);

    //echo "<h4>". $escaper->escapeHtml($lang['Details']) ."</h4>\n";
        // If the page is the view.php page and the user has permission to modify risks
    if (basename($_SERVER['PHP_SELF']) == "view.php" && has_permission("modify_risks"))
    {
        // Give the option to edit the risk details
        echo "<div class=\"tabs--action\">";
        echo "<button type=\"submit\" name=\"edit_details\" class=\"btn\">". $escaper->escapeHtml($lang['EditDetails']) ."</button>\n";
        echo "</div>\n";
    }

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);

        // Top panel
        echo "<div class=\"row-fluid top-panel\">\n";
            display_main_detail_fields_by_panel_view('top', $active_fields, $id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "</div>";

        echo "<div class=\"row-fluid\">\n";
            // Left Panel
            echo "<div class=\"span5 left-panel\">\n";
                display_main_detail_fields_by_panel_view('left', $active_fields, $id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
                echo "&nbsp;";
            echo "</div>";

            // Right Panel
            echo "<div class=\"span5 right-panel\">\n";
                display_main_detail_fields_by_panel_view('right', $active_fields, $id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
                echo "&nbsp;";
            echo "</div>";
        echo "</div>";

        // Bottom panel
        echo "<div class=\"row-fluid bottom-panel\">\n";
            display_main_detail_fields_by_panel_view('bottom', $active_fields, $id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
            echo "&nbsp;";
        echo "</div>";
    }
    else
    {
        echo "<div class=\"row-fluid top-panel\">\n";
            display_risk_mapping_view($risk_catalog_mapping, "top");
            display_threat_mapping_view($threat_catalog_mapping, "top");
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
            // Left Panel
            echo "<div class=\"span5\">\n";
                echo "<div class=\"row-fluid left-panel\">\n";

                    display_submission_date_view($submission_date);

                    display_category_view($category);

                    display_site_location_view($location);

                    display_external_reference_id_view($reference_id);

                    display_control_regulation_view($regulation);

                    display_control_number_view($control_number);

                    display_affected_assets_view($id);

                    display_technology_view($technology);

                    display_team_view($team);

                    display_additional_stakeholders_view($additional_stakeholders);

                    display_owner_view($owner);

                    display_owner_manager_view($manager);

                echo "</div>\n";
            echo "</div>\n";

            echo "<div class=\"span5 right-panel\">\n";

                display_submitted_by_view($submitted_by);

                display_risk_source_view($source);

                display_risk_scoring_method_view($scoring_method, $CLASSIC_likelihood, $CLASSIC_impact);

                display_risk_assessment_view($assessment);

                display_additional_notes_view($notes);
                
                display_supporting_documentation_view($id, 1);

                display_jira_issue_key_view($jira_issue_key);

            echo "</div>\n";

        echo "</div>\n";

        echo "<div class=\"row-fluid bottom-panel\">\n";
            display_risk_tags_view($tags);
        echo "</div>\n";

    }

}

/*************************************
* FUNCTION: VIEW PRINT RISK DETAILS *
*************************************/
function view_print_risk_details($id, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $additional_stakeholders, $owner, $manager, $assessment, $notes, $tags)
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
    echo "<td>" . $escaper->escapeHtml(get_name_by_value("frameworks", $regulation)) . "</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['ControlNumber']) . ":</td>\n";
    echo "<td>" . $escaper->escapeHtml($control_number) . "</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['SiteLocation']) . ":</td>\n";
    echo "<td>" . $escaper->escapeHtml($location) . "</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Category']) . ":</td>\n";
    echo "<td>" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Team']) . ":</td>\n";
    echo "<td>" . $escaper->escapeHtml($team) . "</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Technology']) . ":</td>\n";
    echo "<td>" . $escaper->escapeHtml($technology) . "</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['AdditionalStakeholders']) . ":</td>\n";
    echo "<td>" . $escaper->escapeHtml($additional_stakeholders) . "</td>\n";
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
    echo "<td>" . $escaper->escapeHtml(implode(',', array_map(function($item) use ($escaper) {
                return $item['class'] === 'group' ? "[{$item['name']}]" : $item['name'];
            }, get_assets_and_asset_groups_of_type($id, 'risk')))) . "</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"200\"><b>" . $escaper->escapeHtml($lang['Tags']) . ":</td>\n";
    echo "<td>" . $escaper->escapeHtml($tags) . "</td>\n";
    echo "</tr>\n";
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $custom_values = getCustomFieldValuesByRiskId($id);
        
        $active_fields = get_active_fields();
        foreach($active_fields as $field){
             // Check if this field is custom field and details
            if($field['tab_index'] == 1 && $field['is_basic'] == 0)
            {
                display_custom_field_print($field, $custom_values);
            }
            
        }
    }

    echo "</table>\n";
}

/****************************************
* FUNCTION: VIEW PRINT RISK SCORE FORMS *
*****************************************/
function risk_score_method_html($panel_name="", $scoring_method="1", $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamagePotential="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkillLevel="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPEaseOfDiscovery="10", $OWASPEaseOfExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $custom=false, $ContributingLikelihood="", $ContributingImpacts=[]){
    global $escaper, $lang;
    
    if($custom === false){
        $custom = get_setting("default_risk_score");
    }
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }

    $html = "
        <div class='row-fluid' >
            <div class='{$span1} text-right'>". $escaper->escapeHtml($lang['RiskScoringMethod']) .":</div>
            <div class='{$span2}'>"
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
                <div class='{$span1} text-right'>". $escaper->escapeHtml($lang['CurrentLikelihood']) .":</div>
                <div class='{$span2}'>". create_dropdown('likelihood', $CLASSIC_likelihood, NULL, true, false, true) ."</div>
            </div>
            <div class='row-fluid'>
                <div class='{$span1} text-right'>". $escaper->escapeHtml($lang['CurrentImpact']) .":</div>
                <div class='{$span2}'>". create_dropdown('impact', $CLASSIC_impact, NULL, true, false, true) ."</div>
            </div>
        </div>
        <div id='cvss' style='display: ". ($scoring_method == 2 ? "block" : "none") .";' class='cvss-holder'>
            <table width='100%'>
                <tr>
                    <td width='41.7%'>&nbsp;</td>
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
                    <td width='41.7%'>&nbsp;</td>
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
                    <td width='41.7%'>&nbsp;</td>
                    <td><p><input type='button' name='owaspSubmit' id='owaspSubmit' value='Score Using OWASP'  /></p></td>
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
                <div class='{$span1} text-right'>
                    ". $escaper->escapeHtml($lang['CustomValue']) .":
                </div>
                <div class='{$span2}'>
                    <input type='number' min='0' step='0.1' max='10' name='Custom' id='Custom' value='{$custom}' />
                    <small>(Must be a numeric value between 0 and 10)</small>
                </div>
            </div>
        </div>
        <div id='contributing-risk' style='display: ". ($scoring_method == 6 ? "block" : "none") .";' class='contributing-risk-holder'>
            <table width='100%'>
                <tr>
                    <td width='41.7%'>&nbsp;</td>
                    <td><p><input type='button' name='contributingRiskSubmit' id='contributingRiskSubmit' value='". $escaper->escapeHtml($lang["ScoreUsingContributingRisk"]) ."' /></p></td>
                </tr>
            </table>
            <input type='hidden' name='ContributingLikelihood' id='contributing_likelihood' value='".($ContributingLikelihood ? $ContributingLikelihood : count(get_table("likelihood")))."' />";
            
            $max_impact_value = count(get_table("impact"));
            $contributing_risks = get_contributing_risks();
            foreach($contributing_risks as $contributing_risk){
                $html .= "<input type='hidden' class='contributing-impact' name='ContributingImpacts[{$contributing_risk['id']}]' id='contributing_impact_{$contributing_risk['id']}' value='". $escaper->escapeHtml(empty($ContributingImpacts[ $contributing_risk['id'] ]) ? $max_impact_value : $ContributingImpacts[ $contributing_risk['id'] ]) ."' />";
            }
            
            $html .= "
        </div>

    ";

    echo $html;
}

/*******************************
* FUNCTION: EDIT RISK DETAILS *
*******************************/
function edit_risk_details($id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping=[], $threat_catalog_mapping=[], $template_group_id="")
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


    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);

        // Top panel
        echo "<div class=\"row-fluid top-panel\">\n";
            display_main_detail_fields_by_panel_edit('top', $active_fields, $id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "</div>";

        echo "<div class=\"row-fluid\">\n";
            // Left Panel
            echo "<div class=\"span5 left-panel\">\n";
                display_main_detail_fields_by_panel_edit('left', $active_fields, $id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
                echo "&nbsp;";
            echo "</div>";

            // Right Panel
            echo "<div class=\"span5 right-panel\">\n";
                display_main_detail_fields_by_panel_edit('right', $active_fields, $id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
                echo "&nbsp;";
            echo "</div>";
        echo "</div>";

        // Bottom panel
        echo "<div class=\"row-fluid bottom-panel\">\n";
            display_main_detail_fields_by_panel_edit('bottom', $active_fields, $id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
            echo "&nbsp;";
        echo "</div>";
    }
    else
    {
        echo "<div class=\"row-fluid\">\n";
            display_risk_mapping_edit($risk_catalog_mapping, "top");

            display_threat_mapping_edit($threat_catalog_mapping, "top");
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span5 left-panel\">\n";

                display_submission_date_edit($submission_date);

                display_category_edit($category);

                display_location_edit($location);

                display_external_reference_id_edit($reference_id);

                display_control_regulation_edit($regulation);

                display_control_number_edit($control_number);

                display_affected_assets_edit($id);

                display_technology_edit($technology);

                display_team_edit($team);

                display_additional_stakeholders_edit($additional_stakeholders);

                display_owner_edit($owner);

                display_owners_manager_edit($manager);

            echo "</div>\n";


            echo "<div class=\"span5 right-panel\">\n";

                display_risk_source_edit($source);

                risk_score_method_html("", $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);

                display_risk_assessment_title_edit($assessment);

                display_additional_notes_edit($notes);
                
                display_supporting_documentation_edit($id, 1);

                display_jira_issue_key_edit($jira_issue_key);

            echo "</div>\n";
        echo "</div>\n";
        display_risk_tags_edit($tags);
    }


}

/*************************************
* FUNCTION: VIEW MITIGATION DETAILS *
*************************************/
function view_mitigation_details($risk_id, $mitigation_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $template_group_id="")
{
    global $lang;
    global $escaper;

    // Decrypt fields
    $current_solution = try_decrypt($current_solution);
    $security_requirements = try_decrypt($security_requirements);
    $security_recommendations = try_decrypt($security_recommendations);
    if (basename($_SERVER['PHP_SELF']) == "view.php" && has_permission("plan_mitigations"))
    {
        // Give the option to edit the mitigation details
        echo "<div class=\"tabs--action\">";
        echo "<button type=\"submit\" name=\"edit_mitigation\" class=\"btn\">". $escaper->escapeHtml($lang['EditMitigation']) ."</button>\n";
        echo "</div>\n";
    }
    echo "<h4>". $escaper->escapeHtml($lang['Mitigation']) ."</h4>\n";

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);

        echo "<div class=\"row-fluid\">\n";
            // Left Panel
            echo "<div class=\"span5 left-panel\">\n";
                display_main_mitigation_fields_by_panel_view('left', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
                echo "&nbsp;";
            echo "</div>";

            // Right Panel
            echo "<div class=\"span5 right-panel\">\n";
                display_main_mitigation_fields_by_panel_view('right', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
                echo "&nbsp;";
            echo "</div>";
        echo "</div>";

        // Bottom panel
        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span12 bottom-panel\">";
                display_main_mitigation_fields_by_panel_view('bottom', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
                echo "&nbsp;";
            echo "</div>";
        echo "</div>";
    }
    else
    {
        echo "<div class=\"row-fluid\">\n";
            // Left Panel
            echo "<div class=\"span5 left-panel\">\n";

                display_mitigation_submission_date_view($mitigation_date);

                display_mitigation_planning_date_view($planning_date);

                display_mitigation_planning_strategy_view($planning_strategy);

                display_mitigation_effort_view($mitigation_effort);

                display_mitigation_cost_view($mitigation_cost);

                display_mitigation_owner_view($mitigation_owner);

                display_mitigation_team_view($mitigation_team);

                display_mitigation_percent_view($mitigation_percent);

                display_accept_mitigation_view($risk_id);

            echo "</div>\n";

            // Right Panel
            echo "<div class=\"span5 right-panel\">\n";

                display_current_solution_view($current_solution);

                display_security_requirements_view($security_requirements);

                display_security_recommendations_view($security_recommendations);

                display_supporting_documentation_view($risk_id, 2);

            echo "</div>\n";
        echo "</div>\n";

        // Bottom panel
        echo "<div class=\"row-fluid \">\n";
            echo "<div class=\"span12 bottom-panel\">\n";
                print_mitigation_controls_table($mitigation_controls,$mitigation_id,"view");
            echo "</div>";
        echo "</div>";
    }

}

/*******************************************
* FUNCTION: VIEW PRINT MITIGATION DETAILS *
*******************************************/
function view_print_mitigation_details($id, $mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
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

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $custom_values = getCustomFieldValuesByRiskId($id);
        
        $active_fields = get_active_fields();
        foreach($active_fields as $field){
             // Check if this field is custom field and mitigation
            if($field['tab_index'] == 2 && $field['is_basic'] == 0)
            {
                display_custom_field_print($field, $custom_values);
            }
            
        }
    }

    echo "</table>\n";
}

/*******************************************
* FUNCTION: VIEW PRINT MITIGATION CONTROLS *
*******************************************/
function view_print_mitigation_controls($mitigation)
{
    global $lang;
    global $escaper;

    $control_ids = empty($mitigation[0]['mitigation_controls']) ? "" : $mitigation[0]['mitigation_controls'];
    $controls = get_framework_controls($control_ids);
    $html = "";
    foreach ($controls as $key=>$control)
    {
        $html .= "<div class='control-block item-block clearfix'>\n";
            $html .= "<div class='control-block--header clearfix' data-project=''>\n";

                $html .= "<br>\n";
                $html .= "<div class='control-block--row'>\n";
                $html .= "</div>\n";
            $html .= "</div>\n";
        $html .= "</div>\n";
    }

    echo "<h4>". $escaper->escapeHtml($lang['MitigationControls']) ."</h4>\n";
    echo "<table border=\"1\" width=\"100%\" cellspacing=\"10\" cellpadding=\"10\">\n";

    foreach ($controls as $key=>$control)
    {
        echo "<tr>\n";
            echo "<td>\n";
                    $html = "<table width='100%'>\n";
                        $html .= "<tr>\n";
                            $html .= "<td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlLongName'])."</strong>: </td>\n";
                            $html .= "<td colspan='5'>".$escaper->escapeHtml($control['long_name'])."</td>\n";
                        $html .= "</tr>\n";
                        $html .= "<tr>\n";
                            $html .= "<td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlShortName'])."</strong>: </td>\n";
                            $html .= "<td width='57%' colspan='3'>".$escaper->escapeHtml($control['short_name'])."</td>\n";
                            $html .= "<td width='13%' align='right' ><strong>".$escaper->escapeHtml($lang['ControlOwner'])."</strong>: </td>\n";
                            $html .= "<td width='17%'>".$escaper->escapeHtml($control['control_owner_name'])."</td>\n";
                        $html .= "</tr>\n";
                        $html .= "<tr>\n";
                            $html .= "<td  align='right'><strong>".$escaper->escapeHtml($lang['ControlClass'])."</strong>: </td>\n";
                            $html .= "<td>".$escaper->escapeHtml($control['control_class_name'])."</td>\n";
                            $html .= "<td  align='right'><strong>".$escaper->escapeHtml($lang['ControlPhase'])."</strong>: </td>\n";
                            $html .= "<td>".$escaper->escapeHtml($control['control_phase_name'])."</td>\n";
                            $html .= "<td  align='right'><strong>".$escaper->escapeHtml($lang['ControlNumber'])."</strong>: </td>\n";
                            $html .= "<td>".$escaper->escapeHtml($control['control_number'])."</td>\n";
                        $html .= "</tr>\n";
                        $html .= "<tr>\n";
                            $html .= "<td align='right'><strong>".$escaper->escapeHtml($lang['ControlPriority'])."</strong>: </td>\n";
                            $html .= "<td>".$escaper->escapeHtml($control['control_priority_name'])."</td>\n";
                            $html .= "<td width='200px' align='right'><strong>".$escaper->escapeHtml($lang['ControlFamily'])."</strong>: </td>\n";
                            $html .= "<td>".$escaper->escapeHtml($control['family_short_name'])."</td>\n";
                            $html .= "<td width='200px' align='right'><strong>".$escaper->escapeHtml($lang['MitigationPercent'])."</strong>: </td>\n";
                            $html .= "<td>".$escaper->escapeHtml($control['mitigation_percent'])."%</td>\n";
                        $html .= "</tr>\n";
                        $html .= "<tr>\n";
                            $html .= "<td align='right'><strong>".$escaper->escapeHtml($lang['Description'])."</strong>: </td>\n";
                            $html .= "<td colspan='5'>".$escaper->escapeHtml($control['description'])."</td>\n";
                        $html .= "</tr>\n";
                        $html .= "<tr>\n";
                            $html .= "<td align='right'><strong>".$escaper->escapeHtml($lang['SupplementalGuidance'])."</strong>: </td>\n";
                            $html .= "<td colspan='5'>".$escaper->escapeHtml($control['supplemental_guidance'])."</td>\n";
                        $html .= "</tr>\n";
                    $html .= "</table>\n";
                    $mapped_frameworks = get_mapping_control_frameworks($control['id']);
                    $html .= "<div class='well'>";
                        $html .= "<h5><span>".$escaper->escapeHtml($lang['MappedControlFrameworks'])."</span></h5>";
                        $html .= "<table width='100%' class='table table-bordered'>\n";
                            $html .= "<tr>\n";
                                $html .= "<th width='50%'>".$escaper->escapeHtml($lang['Framework'])."</th>\n";
                                $html .= "<th width='35%'>".$escaper->escapeHtml($lang['Control'])."</th>\n";
                            $html .= "</tr>\n";
                            foreach ($mapped_frameworks as $framework){
                                $html .= "<tr>\n";
                                    $html .= "<td>".$escaper->escapeHtml($framework['framework_name'])."</td>\n";
                                    $html .= "<td>".$escaper->escapeHtml($framework['reference_name'])."</td>\n";
                                $html .= "</tr>\n";
                            }
                        $html .= "</table>\n";
                    $html .= "</div>\n";
                    $validation = get_mitigation_to_controls($mitigation[0]['mitigation_id'],$control['id']);
                    $validation_mitigation_percent = ($validation["validation_mitigation_percent"] >= 0 && $validation["validation_mitigation_percent"] <= 100) ? $validation["validation_mitigation_percent"] : 0;
                    if($validation["validation_details"]|| $validation["validation_owner"] || $validation_mitigation_percent){
                        $html .= "<div class='well'>";
                            $html .= "<h5><span>".$escaper->escapeHtml($lang['ControlValidation'])."</span></h5>";
                            $html .= "<div class='row-fluid'>";
                                $html .= "<div class='span4'>
                                    ".$escaper->escapeHtml($lang['Details']).":<br>
                                    ".nl2br($escaper->escapeHtml($validation["validation_details"]))."
                                </div>";
                            $html .= "</div>";
                            $html .= "<div class='row-fluid'>";
                                $html .= "<div class='span4'>
                                    ".$escaper->escapeHtml($lang['Owner']).":<br>
                                    ".$escaper->escapeHtml(get_name_by_value("user", $validation["validation_owner"]))."
                                </div>";
                            $html .= "</div>";
                                $html .= "<div class='row-fluid'>";
                                $html .= "<div class='span4'>
                                    ".$escaper->escapeHtml($lang['MitigationPercent']).":<br>
                                    ".$validation_mitigation_percent." %
                                </div>";
                            $html .= "</div>\n";
                        $html .= "</div>\n";
                    }
                echo $html;
            echo "</td>\n";
        echo "</tr>\n";
    }

    echo "</table>\n";
}

/*************************************
* FUNCTION: EDIT MITIGATION DETAILS *
*************************************/
function edit_mitigation_details($risk_id, $mitigation_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $template_group_id="")
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
            <a href='/management/view.php?id={$risk_id}&type=1' id=\"cancel_disable\" class=\"btn cancel-edit-mitigation\">". $escaper->escapeHtml($lang['Cancel']) ."</a>
            <button type=\"submit\" name=\"update_mitigation\" class=\"btn btn-danger\">". $escaper->escapeHtml($lang['SaveMitigation']) ."</button>
        </div>
        ";

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);

        echo "<div class=\"row-fluid\">\n";
            // Left Panel
            echo "<div class=\"span5 left-panel\">\n";
                display_main_mitigation_fields_by_panel_edit('left', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
                echo "&nbsp;";
            echo "</div>";

            // Right Panel
            echo "<div class=\"span5 right-panel\">\n";
                display_main_mitigation_fields_by_panel_edit('right', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
                echo "&nbsp;";
            echo "</div>";
        echo "</div>";

        // Bottom panel
        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"row-fluid bottom-panel\">\n";
                display_main_mitigation_fields_by_panel_edit('bottom', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
                echo "&nbsp;";
            echo "</div>";
        echo "</div>";
    }
    else
    {
        echo "<div class=\"row-fluid\">";
            echo "<div class=\"span5 left-panel\">";
                echo "<input type=\"hidden\" name=\"tab_type\" value=\"1\" />\n";

                display_mitigation_submission_date_edit($mitigation_date);

                display_mitigation_planning_date_edit($planning_date);

                display_mitigation_planning_strategy_edit($planning_strategy);

                display_mitigation_effort_edit($mitigation_effort);

                display_mitigation_cost_edit($mitigation_cost);

                display_mitigation_owner_edit($mitigation_owner);

                display_mitigation_team_edit($mitigation_team);

                display_mitigation_percent_edit($mitigation_percent);

                display_mitigation_controls_edit($mitigation_controls);
            echo "</div>";

            echo "<div class=\"span5 right-panel\">";

                display_current_solution_edit($current_solution);

                display_security_requirements_edit($security_requirements);

                display_security_recommendations_edit($security_recommendations);

                display_supporting_documentation_edit($risk_id, 2);

            echo "</div>";
        echo "</div>";

        echo "<div class=\"row-fluid\">";
            echo "<div class=\"span12 bottom-panel\">";
                // Add controls table html
                print_mitigation_controls_table($mitigation_controls,$mitigation_id,"edit");

                // Add javascript code for mitigation controls
                display_mitigation_controls_script();
            echo "</div>";
        echo "</div>";
    }

}

/********************************************************************************************************************************
* FUNCTION: Display Mitigation Controls Dropdown                                                                                *
* Renders the mitigation controls dropdown. Can be used in multiple places due to its configurability.                          *
* selected_control_ids_string: the selected control ids as a comma separated string                                             *
* element_name: name of the rendered element                                                                                    *
* initialize: A boolean to sign whether the initialozation of the multiselect is required                                       *
* datatable_redraw: A boolean that tells the rendering logic whether the mitigation control details                             *
                    datatable is present on the page and needs logic to refresh it when the selection of the dropdown changed.  *
*********************************************************************************************************************************/
function mitigation_controls_dropdown($selected_control_ids_string = "", $element_name = "mitigation_controls[]", $initialize = true, $datatable_redraw = false)
{
    global $lang, $escaper;

    require_once(realpath(__DIR__ . '/governance.php'));
    $controls = get_framework_controls_dropdown_data();

    if ($controls && !empty($controls)) {
        $selected_control_ids = explode(",", $selected_control_ids_string);
        $eID = "mitigation_controls_".generate_token(10);
        echo  "<select id=\"".$eID."\" name=\"".$element_name."\" title=\"".$escaper->escapeHtml($lang['MitigationControls'])."\" class=\"mitigation_controls\" multiple=\"multiple\">";
            foreach($controls as $control){
                if(in_array($control['id'], $selected_control_ids)){
                    echo "<option value='".$control['id']."' selected title='".$escaper->escapeHtml($control['long_name'])."'>".$escaper->escapeHtml($control['short_name'])."</option>";
                }else{
                    echo "<option value='".$control['id']."' title='".$escaper->escapeHtml($control['long_name'])."'>".$escaper->escapeHtml($control['short_name'])."</option>";
                }
            }
        echo "</select>";
    
        if ($initialize) {
            echo "
                <script>
                    $(document).ready(function(){
                        $('#".$eID."').multiselect({
                            enableFiltering: true,
                            enableCaseInsensitiveFiltering: true,
                            buttonWidth: '100%',
                            maxHeight: 250,
                            dropUp: true,
                            filterPlaceholder: '".$escaper->escapeHtml($lang["SelectForMitigationControls"])."'" . ($datatable_redraw ? ",
                            onDropdownHide: function(){
                                var form = $('#{$eID}').parents('form');
                                var tableId = $(\".mitigation-controls-table-container\", form).data('tableid');
                                $(\"#\" + tableId).DataTable().draw();
                            }" : "") . "
                        });
                    })
                </script>
            ";
        }
    
    } else {
        echo "<span style='vertical-align: middle;'><b>{$escaper->escapeHtml($lang['NoControlsAvailable'])}</b></span>";
    }

}

/**********************************************
* FUNCTION: Display Mitigation Controls Table *
***********************************************/
function print_mitigation_controls_table($control_ids, $mitigation_id, $flag="view"){
    global $lang;
    global $escaper;
    $key = uniqid(rand());
    $tableID = "mitigation-controls-table".$key;

    echo "
        <br>
        <input type=\"hidden\" value=\"" . $escaper->escapeHtml($control_ids) . "\" class='active-textfield mitigation_control_ids' />
        <div class=\"row-fluid mitigation-controls-table-container hide\" data-tableid=\"{$tableID}\">
            <strong>Mitigation Controls</strong>
            <table id=\"{$tableID}\" width=\"100%\">
                <thead style='display:none;'>
                    <tr>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
        <script>
            var pageLength = 10;
            var mitigationControlDatatable = $('#{$tableID}').DataTable({
                scrollX: true,
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                paging: false,
                dom : 'flrti<\"#view-all.view-all\">p',
                ajax: {
                    url: BASE_URL + '/api/datatable/mitigation_controls',
                    data: function(d){
                        var form = $('#{$tableID}').parents('form');
                        d.flag = '{$flag}';
                        d.mitigation_id = '{$mitigation_id}';
                        if($('.mitigation_controls', form).length){
                            d.control_ids = $('.mitigation_controls', form).val().join(',');
                        }
                        else{
                            d.control_ids = $('.mitigation_control_ids', form).val();
                        }
                    },
                    complete: function(response){
                        if(Number(response.responseJSON.recordsTotal) > 0){
                            $('#{$tableID}').parents('.mitigation-controls-table-container').removeClass('hide');
                        }else{
                            $('#{$tableID}').parents('.mitigation-controls-table-container').addClass('hide');
                        }
                    }
                }
            });

            // Add paginate options
            mitigationControlDatatable.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })

            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  mitigationControlDatatable.settings();
                oSettings[0]._iDisplayLength = -1;
                mitigationControlDatatable.draw()
                $(this).addClass(\"current\");
            })

            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  mitigationControlDatatable.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    mitigationControlDatatable.draw()
                }

            })

        </script>
    ";
}

/*******************************************
* FUNCTION: Add Mitigation Controls Script *
********************************************/
function display_mitigation_controls_script(){
    echo "
        <script language=\"javascript\">
            $(document).ready(function(){
                /**
                * events in clicking Select Mitigating Controls
                */
                $('body').on('click', '.select-mitigating-controls', function(e){
                    e.preventDefault();
                    var form = $(this).parents('form');
                    var mitigation_controls = $(\"input[name=mitigation_controls]\", form).val();
                    var mitigation_controls_array = mitigation_controls.split(\",\");
                    mitigation_controls_array = mitigation_controls_array.map(function(value){ return parseInt(value); });

                    $(\".mitigating-controls-modal input[type=checkbox]\", form).each(function(){
                        if(mitigation_controls_array.indexOf(parseInt($(this).val())) > -1){
                            $(this).prop(\"checked\", true);
                        }
                    })
                    $(\".mitigating-controls-modal\", form).modal();
                })

                /**
                * events in clicking Add button on Mitigation Controls modal
                */
                $('body').on('click', '.mitigating-controls-modal [name=add_controls]', function(e){
                    e.preventDefault();
                    var form = $(this).parents('form');
                    var mitigation_controls_array = [];
                    var mitigation_control_names_array = [];
                    $(\".mitigating-controls-modal input[type=checkbox]\", form).each(function(){
                        if($(this).is(\":checked\")){
                            mitigation_controls_array.push(parseInt($(this).val()));
                            mitigation_control_names_array.push($(this).parent().find('.name').html());
                        }
                    })
                    var mitigation_controls = mitigation_controls_array.join(\",\");
                    var mitigation_control_names = mitigation_control_names_array.join(\", \");
                    $(\"input[name=mitigation_controls]\", form).val(mitigation_controls);
                    $(\".mitigation_control_names\", form).html(mitigation_control_names);
                    $(\".mitigating-controls-modal\", form).modal('hide');

                    var tableId = $(\".mitigation-controls-table-container\", form).data('tableid');
                    $(\"#\" + tableId).DataTable().draw();
                })
            })
        </script>
    ";
}

/*********************************
* FUNCTION: view_review_details *
*********************************/
function view_review_details($id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comment, $template_group_id="")
{
    global $lang;
    global $escaper;

    // Decrypt fields
    $comment = try_decrypt($comment);

    echo "<div class=\"tabs--action\">";
    echo "<button type=\"submit\" name=\"view_all_reviews\" class=\"btn all_reviews_btn\">" . $escaper->escapeHtml($lang['ViewAllReviews']) . "</button>\n";
    echo "<input type=\"hidden\" id=\"lang_last_review\" value=\"" . $escaper->escapeHtml($lang['LastReview']) . "\" />\n";
    echo "<input type=\"hidden\" id=\"lang_all_reviews\" value=\"" . $escaper->escapeHtml($lang['ViewAllReviews']) . "\" />\n";
    echo "</div>\n";

    echo "<div class=\"current_review\">\n";
        echo "<u>".$escaper->escapeHtml($lang['LastReview'])."</u>";

        // If customization extra is enabled
        if(customization_extra())
        {
            // Include the extra
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
            $template_group = get_custom_template_group_by_id($template_group_id);
            if(!$template_group_id || !$template_group) {
                $group = get_default_template_group("risk");
                $template_group_id = $group["id"];
            }
            $active_fields = get_active_fields("risk", $template_group_id);

            echo "<div class=\"row-fluid\">\n";
                // Left Panel
                echo "<div class=\"span5 left-panel\">\n";
                    display_main_review_fields_by_panel_view('left', $active_fields, $id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comment);
                    echo "&nbsp;";
                echo "</div>";

                // Right Panel
                echo "<div class=\"span5 right-panel\">\n";
                    display_main_review_fields_by_panel_view('right', $active_fields, $id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comment);
                    echo "&nbsp;";
                echo "</div>";
            echo "</div>";

            // Bottom panel
            echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 bottom-panel\">";
                    display_main_review_fields_by_panel_view('bottom', $active_fields, $id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comment);
                    echo "&nbsp;";
                echo "</div>";
            echo "</div>";
        }
        else
        {
            echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span5 left-panel\">\n";
                    display_review_date_view($review_date);

                    display_reviewer_view($reviewer);

                    display_review_view($review);

                    display_next_step_view($next_step, $id);

                    display_next_review_date_view($next_review);

                    display_comments_view($comment);
                echo "</div>";
            echo "</div>";
        }


    echo "</div>\n";

    echo "<div class=\"all_reviews\" style=\"display:none\">\n";

        echo "<u>".$escaper->escapeHtml($lang['ReviewHistory'])."</u>";

        get_reviews($id, $template_group_id);

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

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        // Get the management reviews for the risk
        $mgmt_reviews = get_review_by_id($id);
        
        $custom_values = getCustomFieldValuesByRiskId($id);
        
        $active_fields = get_active_fields();
        foreach($active_fields as $field){
             // Check if this field is custom field and review
            if($field['tab_index'] == 3 && $field['is_basic'] == 0)
            {
                display_custom_field_print($field, $custom_values, $mgmt_reviews[0]['id']);
            }
            
        }
    }

    echo "</table>\n";
}

/************************************
* FUNCTION: edit_review_submission *
************************************/
function edit_review_submission($id, $review_id, $review, $next_step, $next_review, $comment, $default_next_review, $template_group_id="")
{
    global $lang;
    global $escaper;

    // Decrypt fields
    $comment = try_decrypt($comment);

    echo "<h4>". $escaper->escapeHtml($lang['SubmitManagementReview']) ."</h4>\n";
    echo "<form name=\"submit_management_review\" method=\"post\" action=\"\">\n";

    echo "<div class=\"tabs--action\">";
//    echo "<input id=\"cancel_disable\" class=\"btn cancel-edit-review \" value=\"". $escaper->escapeHtml($lang['Cancel']) ."\" type=\"reset\">\n";
    echo "<a href=\"view.php?id={$id}&type=2\" id=\"cancel_disable\" class=\"btn cancel-edit-review\" disabled=\"disabled\">Cancel</a>&nbsp;\n";
    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-danger save-review\">". $escaper->escapeHtml($lang['SubmitReview']) ."</button>\n";
    echo "</div>\n";


    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);

        echo "<div class=\"row-fluid\">\n";
            // Left Panel
            echo "<div class=\"span5 left-panel\">\n";
                display_main_review_fields_by_panel_edit('left', $active_fields, $id, $review_id, $review, $next_step, $next_review, $comment, $default_next_review);
                echo "&nbsp;";
            echo "</div>";

            // Right Panel
            echo "<div class=\"span5 right-panel\">\n";
                display_main_review_fields_by_panel_edit('right', $active_fields, $id, $review_id, $review, $next_step, $next_review, $comment, $default_next_review);
                echo "&nbsp;";
            echo "</div>";
        echo "</div>";

        // Bottom panel
        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span12 bottom-panel\">";
                display_main_review_fields_by_panel_edit('bottom', $active_fields, $id, $review_id, $review, $next_step, $next_review, $comment, $default_next_review);
                echo "&nbsp;";
            echo "</div>";
        echo "</div>";
    }
    else
    {
        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span5 left-panel\">\n";

                display_review_date_edit();

                display_reviewer_name_edit();

                display_review_edit($review);

                display_next_step_edit($next_step);

                display_comments_edit($comment);

            echo "</div>\n";

            echo "<div class=\"span5 right-panel\">\n";
                display_set_next_review_date_edit($default_next_review);
            echo "</div>\n";
        echo "</div>\n";
    }

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
//    echo "<button type=\"submit\" name=\"update_classic\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
//    echo "</div>\n";

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
    echo "<td width=\"60\"><input type=\"number\" min='0' max='10' name=\"Custom\" id=\"Custom\" style=\"width:30px;\" value=\"" . $escaper->escapeHtml($custom) . "\" step='0.1' /></td>\n";
    echo "<td>(Must be a numeric value between 0 and 10)</td>\n";
    echo "</tr>\n";

    echo "</table>\n";

    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"update_custom\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
    echo "</div>\n";
    echo "</form>\n";
}

/*****************************************
* FUNCTION: edit_contributing_risk_score *
******************************************/
function edit_contributing_risk_score($ContributingLikelihood, $ContributingImpacts)
{
    global $lang;
    global $escaper;

    $max_likelihood = get_max_value("contributing_risks_likelihood");
    $ContributingLikelihood = $ContributingLikelihood ? $ContributingLikelihood : $max_likelihood;
    $contributing_risks = get_contributing_risks();
    
    echo "<h4>" . $escaper->escapeHtml($lang['UpdateContributingRiskScore']) . "</h4>\n";
    echo "<form name=\"update_contributing_risk\" method=\"post\" action=\"\">\n";
    echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    echo "<tr>\n";
    echo "<td colspan=\"4\">&nbsp;</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"175\">" . $escaper->escapeHtml($lang['ContributingLikelihood']) . ":</td>\n";
    echo "<td width=\"200\">\n";
    create_dropdown("contributing_risks_likelihood", $ContributingLikelihood, "ContributingLikelihood", false);
    echo "</td>\n";
    echo "<td colspan='2'>&nbsp;</td>\n";
    echo "</tr>\n";
    
    echo "<tr>\n";
    echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['ContributingRisk']) . "</b></td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td ><b>" . $escaper->escapeHtml($lang["Subject"]) . "</b></td>\n";
    echo "<td ><b>" . $escaper->escapeHtml($lang["Weight"]) . "</b></td>\n";
    echo "<td ><b>" . $escaper->escapeHtml($lang["Impact"]) . "</b></td>\n";
    echo "<td>&nbsp;</td>\n";
    echo "</tr>\n";

    foreach($contributing_risks as $contributing_risk){
        $impacts = get_impact_values_from_contributing_risks_id($contributing_risk['id']);
        $max_impact = max(array_column($impacts, 'value'));
        echo "<tr>\n";
        echo "<td >" . $escaper->escapeHtml($contributing_risk['subject']) . "</td>\n";
        echo "<td >" . $escaper->escapeHtml($contributing_risk['weight']) . "</td>\n";
        $impact = empty($ContributingImpacts[$contributing_risk["id"]]) ? $max_impact : $ContributingImpacts[$contributing_risk["id"]];
        echo "<td >\n";
        create_dropdown("", $impact, "ContributingImpacts[{$contributing_risk["id"]}]", false, false, false, "", "--", "", true, 0, $impacts);
        echo "</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";

    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"update_contributing_risk\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
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
    echo "<li><a class='score-action' data-method='6' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=6\">" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    echo "</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"180\">". $escaper->escapeHtml($lang['Likelihood']) .":</td>\n";
    echo "<td width=\"40\">[ " . $escaper->escapeHtml($CLASSIC_likelihood) . " ]</td>\n";
    echo "<td>" . $escaper->escapeHtml(get_name_by_value("likelihood", $CLASSIC_likelihood)) . "</td>\n";
    echo "<td>&nbsp;</td>\n";
    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td width=\"180\">". $escaper->escapeHtml($lang['Impact']) .":</td>\n";
    echo "<td width=\"40\">[ " . $escaper->escapeHtml($CLASSIC_impact) . " ]</td>\n";
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
    echo "<li><a class='score-action' data-method='6' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=6\">" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>\n";
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
    echo "<li><a class='score-action' data-method='6' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=6\">" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>\n";
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
    echo "<li><a class='score-action' data-method='6' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=6\">" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>\n";
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
    echo "<td colspan=\"9\"><strong>Full details of the OWASP Risk Rating Methodology can be found <a href=\"https://owasp.org/www-community/OWASP_Risk_Rating_Methodology\" target=\"_blank\">here</a>.</strong></td>\n";
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
    echo "<li><a class='score-action' data-method='6' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=6\">" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>\n";
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

/********************************************
* FUNCTION: CONTRIBUTING RISK SCORING TABLE *
*********************************************/
function contributing_risk_scoring_table($id, $calculated_risk, $Contributing_Likelihood, $Contributing_Impacts, $type=0)
{
    global $lang;
    global $escaper;

    $max_likelihood = get_max_value("contributing_risks_likelihood");
    $max_likelihood_name = get_name_by_value("contributing_risks_likelihood", $max_likelihood);
    $Contributing_Likelihood = $Contributing_Likelihood ? $Contributing_Likelihood : $max_likelihood;
    $Contributing_Likelihood_name = get_name_by_value("contributing_risks_likelihood", $Contributing_Likelihood);

    echo "
        <style> 
            table.risk_scores .header-row{
                color: #ffffff;
                background: #6f6f6f;
                line-height: 2em;
            }
            
        </style>";

    echo "<div class=\" pull-left\"><h4>" . $escaper->escapeHtml($lang['ContributingRiskScoring']) . "</h4></div>\n";
    echo "<div class=\"btn-group pull-right sorting-buttons\">\n";
    echo "<a class=\"btn updateScore\" href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateContributingRiskScore']) . "</a>\n";
    echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($lang['RiskScoringActions']) . "<span class=\"caret\"></span></a>\n";
    echo "<ul class=\"dropdown-menu\">\n";
    //echo "<li><a href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateOWASPScore']) . "</a></li>\n";
    echo "<li><a class='score-action' data-method='1' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=1\">" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>\n";
    echo "<li><a class='score-action' data-method='2' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=2\">" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>\n";
    echo "<li><a class='score-action' data-method='3' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=3\">" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>\n";
    echo "<li><a class='score-action' data-method='4' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=4\">" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>\n";
    echo "<li><a class='score-action' data-method='5' href=\"view.php?type=".$type."&id=". $escaper->escapeHtml($id) ."&scoring_method=5\">" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>\n";
    echo "</ul>\n";
    echo "</div>\n";

    echo "<table class=\"risk_scores\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"1\">\n";
    echo "<tr class=\"header-row\">\n";
    echo "<th colspan=\"2\" align=\"center\"><u>" . $escaper->escapeHtml($lang['ContributingLikelihood']) . "</u></th>\n";
    echo "</tr>\n";
    echo "<tr class=\"header-row\">\n";
    echo "<th align=\"center\" width=\"50%\">" . $escaper->escapeHtml($lang['Selected']) . "</th>\n";
    echo "<th align=\"center\" width=\"50%\">" . $escaper->escapeHtml($lang['MaximumValue']) . "</th>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td align=\"center\">[ ".$Contributing_Likelihood." ] ".$Contributing_Likelihood_name . "</td>\n";
    echo "<td align=\"center\">[ ".$max_likelihood." ] ".$max_likelihood_name."</td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    echo "<br>";

    echo "<table class=\"risk_scores\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"1\">\n";
    echo "<tr class=\"header-row\">\n";
    echo "<th colspan=\"4\" align=\"center\"><u>" . $escaper->escapeHtml($lang['ContributingImpact']) . "</u></td>\n";
    echo "</tr>\n";
    echo "<tr class=\"header-row\">\n";
    echo "<th align=\"center\" width=\"20%\">" . $escaper->escapeHtml($lang['Subject']) . "</th>\n";
    echo "<th align=\"center\" width=\"20%\">" . $escaper->escapeHtml($lang['Weight']) . "</th>\n";
    echo "<th align=\"center\" width=\"30%\">" . $escaper->escapeHtml($lang['Selected']) . "</th>\n";
    echo "<th align=\"center\" width=\"30%\">" . $escaper->escapeHtml($lang['MaximumValue']) . "</th>\n";
    echo "</tr>\n";

    $contributing_risks = get_contributing_risks();
    
    $contributing_likelihood_formula = "( ".$escaper->escapeHtml($Contributing_Likelihood)." X 5 / ".$escaper->escapeHtml($max_likelihood)." )";
    $contributing_impact_formula = array();
    
    foreach($contributing_risks as $index => $contributing_risk){
        $impacts = get_impact_values_from_contributing_risks_id($contributing_risk['id']);
        $impact_names = [];
        foreach($impacts as $row){
            $impact_names[$row['value']] = $row['name'];
        }
        $max_impact = max(array_column($impacts, 'value'));
        $max_impact_name = $impact_names[$max_impact];
        $impact = empty($Contributing_Impacts[$contributing_risk['id']]) ? $max_impact : $Contributing_Impacts[$contributing_risk['id']];
        $impact_name = $impact_names[$impact];
        echo "<tr>\n";
        echo "<td align=\"center\">". $escaper->escapeHtml($contributing_risk['subject']) ."</td>\n";
        echo "<td align=\"center\">". $escaper->escapeHtml($contributing_risk['weight']) ."</td>\n";
        echo "<td align=\"center\">[ " . $escaper->escapeHtml($impact) . " ] ".$escaper->escapeHtml($impact_name)."</td>\n";
        echo "<td align=\"center\">[ " . $escaper->escapeHtml($max_impact) . " ] ".$escaper->escapeHtml($max_impact_name)."</td>\n";
        echo "</tr>\n";
        $contributing_impact_formula[] = " ( ".$escaper->escapeHtml($contributing_risk['weight'])." X (".$escaper->escapeHtml($impact)." X 5 / ".$escaper->escapeHtml($max_impact)."))"; 
    }
    echo "</table>\n";
    echo "<br>";
    $risk_formula = $contributing_likelihood_formula . " +  (" . implode(" + ", $contributing_impact_formula) . " = ".$escaper->escapeHtml($calculated_risk);
    
    echo "<b>RISK = ".$risk_formula." </b>\n";
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
    global $lang, $escaper;

    echo "<script>\n";
    echo "var BASE_URL = '". (isset($_SESSION['base_url']) ? $escaper->escapeHtml($_SESSION['base_url']) : "") ."'; \n";
    echo "var field_required_lang = '". $escaper->escapeHtml($lang['FieldIsRequired']) ."'; \n";
    echo "</script>\n";

    echo "<div id=\"load\" style=\"display:none;\">".$escaper->escapeHtml($lang['SendingRequestPleaseWait'])."</div>";

    // If the page is in the root directory
    if ($active == "Home")
    {
        echo "<header class=\"l-header\">\n";
        echo "<div class=\"navbar\">\n";
        echo "<div class=\"navbar-inner\">\n";
        echo "<div class=\"container-fluid\">\n";
            echo "<a class=\"brand\" href=\"https://www.simplerisk.com/\"><img src='images/logo@2x.png' alt='SimpleRisk Logo' /></a>\n";
        echo "<div class=\"navbar-content\">\n";
        echo "<ul class=\"nav\">\n";
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
        if (isset($_SESSION["access"]) && $_SESSION["access"] == "1")
        {
            // Show the user profile menu
            echo "<div class=\"btn-group pull-right\">\n";
            echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($_SESSION['name']) . "<span class=\"caret\"></span></a>\n";
            echo "<ul class=\"dropdown-menu\">\n";
            echo "<li>\n";
            echo "<a href=\"account/profile.php\"><i class=\"fa fa-user\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['MyProfile']) ."</a>\n";
            echo "</li>\n";
            echo "<li>\n";
            echo "<a href=\"logout.php\"><i class=\"fa fa-sign-out-alt\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['Logout']) ."</a>\n";
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
        echo "<a class=\"brand\" href=\"https://www.simplerisk.com/\"><img src='../images/logo@2x.png' alt='SimpleRisk Logo' /></a>\n";
        echo "<div class=\"navbar-content\">\n";
        echo "<ul class=\"nav\">\n";
// echo ($active == "Home" ? "<li class=\"active\">\n" : "<li>\n");
// echo "<a href=\"../index.php\">" . $escaper->escapeHtml($lang['Home']) . "</a>\n";
// echo "</li>\n";

        // If the user has governance permissions
        if (check_permission("governance"))
        {
            echo ($active == "Governance" ? "<li class=\"active\">\n" : "<li>\n");
            echo "<a href=\"../governance/index.php\">" . $escaper->escapeHtml($lang['Governance']) . "</a>\n";
            echo "</li>\n";
        }

        // If the user has risk management permissions
        if (check_permission("riskmanagement"))
        {
            echo ($active == "RiskManagement" ? "<li class=\"active\">\n" : "<li>\n");
            echo "<a href=\"../management/index.php\">" . $escaper->escapeHtml($lang['RiskManagement']) . "</a>\n";
            echo "</li>\n";
        }

        // If the user has compliance permissions
        if (check_permission("compliance"))
        {
            echo ($active == "Compliance" ? "<li class=\"active\">\n" : "<li>\n");
            echo "<a href=\"../compliance/index.php\">" . $escaper->escapeHtml($lang['Compliance']) . "</a>\n";
            echo "</li>\n";
        }

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


        // If the user is logged in
        if (isset($_SESSION["access"]) && $_SESSION["access"] == "1")
        {
            // Show the user profile menu
            echo "<div class=\"pull-right user--info\">\n";
            echo "<a class=\"dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($_SESSION['name']) . "<span class=\"caret\"></span></a>\n";
            echo "<ul class=\"dropdown-menu\">\n";
            echo "<li>\n";
            echo "<a href=\"../account/profile.php\"><i class=\"fa fa-user\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['MyProfile']) ."</a>\n";
            echo "</li>\n";

            if (organizational_hierarchy_extra()) {

                require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));

                echo '
                    <script>
                        $(document).ready(function() {
                      		$(document).on("click", "li.dropdown-submenu.business-units a", function() {
			                	var $this = $(this);
			                	if (!$this.hasClass("selected")) {

			                		var business_unit_id = $this.data("id");
			                		$.ajax({
    			                        url: BASE_URL + "/api/organizational_hierarchy/business_unit/select?id=" + business_unit_id,
    			                        type: "GET",
    			                        success : function (response) {
    			                        	window.location.href = window.location.pathname + window.location.search;
    			                        	return false;
    			                        },
    			                		error: function(xhr,status,error) {
			                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
			                                    showAlertsFromArray(xhr.responseJSON.status_message);
			                                }
    			                        }
    			                    });
			                	}
			                    return false;
		                	});
                        });
                    </script>
                ';

                echo "
                    <li class='dropdown-submenu pull-left business-units'>
                        <a href='#'><i class=\"fa fa-briefcase\"></i>&nbsp&nbsp;" . $escaper->escapeHtml($lang['BusinessUnits']) . "</a>
                        <ul class='dropdown-menu'>
                            " . get_available_business_unit_menu_items() . "
                        </ul>
                    </li>
                ";
            }
            echo "<li>\n";
            echo "<a href=\"../logout.php\"><i class=\"fa fa-sign-out-alt\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['Logout']) ."</a>\n";
            echo "</li>\n";
            echo "</ul>\n";
            echo "</div>\n";
        }
        
        if ($active != "Home"){
            echo "<div class=\"pull-right help\">\n";
            echo "<a class=\"dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\"><img src=\"../images/helpicon-top.png\"><span class=\"caret\"></span></a>\n";
            echo "<ul class=\"dropdown-menu\">\n";
	    echo "<li>\n";
	    echo "<a href=\"https://help.simplerisk.com/index.php?page=" . get_request_uri() . "\" target=\"_blank\"><i class=\"fa fa-info-circle\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['AboutThisPage']) ."</a>\n";
	    echo "</li>\n";
            echo "<li>\n";
            echo "<a href=\"https://simplerisk.freshdesk.com/a/solutions/folders/6000228831\" target=\"_blank\"><i class=\"fa fa-video\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['HowToVideos']) ."</a>\n";
            echo "</li>\n";
            echo "<li>\n";
            echo "<a href=\"https://simplerisk.freshdesk.com/a/solutions/folders/6000168810\" target=\"_blank\"><i class=\"fa fa-question-circle\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['FAQs']) ."</a>\n";
            echo "</li>\n";
            echo "<li>\n";
            echo "<a href=\"https://github.com/simplerisk/documentation/raw/master/SimpleRisk%20Release%20Notes%20" . $escaper->escapeHtml(get_latest_app_version()) . ".pdf\" target=\"_blank\"><i class=\"fa fa-newspaper\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['WhatsNew']) ."</a>\n";
            echo "</li>\n";
            echo "<li>\n";
            echo "<a href=\"https://simplerisk.freshdesk.com/a/solutions/articles/6000190811\" target=\"_blank\"><i class=\"fa fa-map\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['Roadmap']) ."</a>\n";
            echo "</li>\n";
	    echo "<li>\n";
	    echo "<a href=\"https://simplerisk.freshdesk.com/support/solutions\" target=\"_blank\"><i class=\"fa fa-cloud\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['SupportPortal']) ."</a>\n";
	    echo "</li>\n";
	    echo "<li>\n";
	    echo "<a href=\"https://simplerisk.freshdesk.com/support/tickets/new\" target=\"_blank\"><i class=\"fa fa-ticket-alt\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['WebSupport']) ."</a>\n";
	    echo "</li>\n";
	    echo "<li>\n";
	    echo "<a href=\"mailto: support@simplerisk.com\"><i class=\"fa fa-envelope\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['EmailSupport']) ."</a>\n";
	    echo "</li>\n";

	    // If the user has support enabled
	    if (isset($_SESSION['support']) && $_SESSION['support'] == "true")
	    {
		    echo "<li>\n";
		    echo "<a href=\"https://www.simplerisk.com/schedule/support\" target=\"_blank\"><i class=\"fa fa-phone\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['PhoneSupport']) ."</a>\n";
		    echo "</li>\n";
	    }

            echo "</ul>\n";
            echo "</div>\n";

            if (!advanced_search_extra()) {
                echo "
                    <div class='pull-right search-risks'>
                        <a id='show-search-pop'><i class='fa fa-search'></i></a>
                        <div class='search-popup'>
                            <form name='search' action='../management/view.php' method='get'>
                                <span class='search--wrapper'>
                                    <input type='text' size='6' name='id' placeholder='ID#' onClick='this.setSelectionRange(0, this.value.length)' />
                                    <a href='javascript:document.search.submit()'><i class='fa fa-search'></i></a>
                                </span>
                            </form>
                        </div>
                    </div>

                    <script type='text/javascript'>
                        $(document).click(function() {
                            $('.search-popup').hide();
                        });

                        $('#show-search-pop, .search-popup').click(function(event) {
                            event.stopPropagation();
                            event.preventDefault();
                            $('.search-popup').show();
                            $('.search-popup .search--wrapper input[type=text]').focus();
                        });
                    </script>\n";
            } else {
                
                require_once(realpath(__DIR__ . '/../extras/advanced_search/index.php'));
                
                echo "
                    <div class='pull-right search-risks'>
                        <a id='show-search-pop'><i class='fa fa-search'></i></a>
                        <div class='search-popup'>
                            <form id='header-search-form' action='' method='GET' autocomplete='off'>
                                <span class='search--wrapper'>
                                    <input type='text' size='6' name='q' placeholder='" . $escaper->escapeHtml($lang['RiskSearch']) . "' onClick='this.setSelectionRange(0, this.value.length)' autocomplete='off'/>
                                    <a href='#'><i class='fa fa-search'></i></a>
                                </span>
                            </form>
                            <div id='result-template' style='display: none;'>
                                <div class='control-block item-block clearfix'>
                                    <div class='control-block--header clearfix'>
                                        <div class='control-block--row'>
                                            <table width='100%'>
                                                <tbody>
                                                    <tr>
                                                        <td colspan='2'><a class='result-url' href='!id!'>(!id!) !subject!</a></td>
                                                    </tr>
                                                    <tr>
                                                        <td width='13%'><strong class='category_field_name'>!category_field_name!</strong>:</td>
                                                        <td class='field_value'>!field_value!</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id='advanced-search-results-table-wrapper' style='display: none;'>
                                <table id='advanced-search-results-table' width='100%'>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                            <div id='advanced-search-no-results-message' style='display: none;'>" . $escaper->escapeHtml($lang['NoSearchResults']) . "</div>
                        </div>
                    </div>

                    <script type='text/javascript'>

                        $(document).click(function() {
                            $('.search-popup').hide();
                        });

                        $(document).on('click', '#show-search-pop, .search-popup, .result-url', function(event) {
                            if ($(this).hasClass('result-url')) {
                                event.stopPropagation();
                            }
                            else {
                                event.stopPropagation();
                                event.preventDefault();
                                $('.search-popup').show();
                                $('.search-popup .search--wrapper input[type=text]').focus();
                            }
                        });

                        $('.search--wrapper a').click(function(event) {
                            event.stopPropagation();
                            event.preventDefault();
                            $('#header-search-form').submit();
                        });

                        $('#header-search-form').submit(function(event) {
                            event.preventDefault();
                            
                            var q = $(this).find('input[name=\'q\']').val();

                            if (q.length >= 1) {

                                $.ajax({
                                    type: 'GET',
                                    url: BASE_URL + '/api/advanced_search?q=' + q,
                                    async: true,
                                    cache: false,
                                    contentType: false,
                                    processData: false,
                                    success: function(response){
                                        if(response.status_message) {
                                            showAlertsFromArray(response.status_message);
                                        }

                                        var data = response.data;

                                        if (data && data instanceof Array && data.length) {

                                            if (data[0]['category_field_name'] == 'id') {
                                                window.location.href = BASE_URL + '/management/view.php?id=' + data[0]['id'];
                                            } else {
                                                $('#advanced-search-no-results-message').hide();
                                                $('#advanced-search-results-table-wrapper').show();
                                                var results_table = $('#advanced-search-results-table tbody');
                                                results_table.html('');
                                                var length = data.length;
                                                for (var i = 0; i < length; i++) {
                                                    var item = data[i];

                                                    var template = $('#result-template').children().clone();

                                                    result_url = template.find('.result-url');
                                                    result_url.attr('href', BASE_URL + '/management/view.php?id=' + item['id']);
                                                    result_url.html('(' + item['id'] + ') ' + item['subject']);

                                                    category_field_name = template.find('.category_field_name');
                                                    category_field_name.html(item['category_field_name']);

                                                    field_value = template.find('.field_value');
                                                    field_value.html(item['field_value']);

                                                    template = $('<tr></tr>').append($('<td></td>').append(template));
                                                    results_table.append(template);
                                                }
                                            }
                                        } else {
                                            $('#advanced-search-results-table-wrapper').hide();
                                            $('#advanced-search-no-results-message').show();
                                        }
                                    },

                                    error: function(xhr,status,error) {
                                        if(!retryCSRF(xhr, this)) {
                                            if(xhr.responseJSON && xhr.responseJSON.status_message) {
                                                showAlertsFromArray(xhr.responseJSON.status_message);
                                            }
                                        }
                                    }
                                });
                            }

                            return false;
                        });
                    </script>";
            }
        }
    }

    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</header>\n";
}

/*********************************
* FUNCTION: VIEW GOVERNANCE MENU *
**********************************/
function view_governance_menu($active)
{
    global $lang;
    global $escaper;

    echo "<ul class=\"nav nav-pills nav-stacked aside--nav \">\n";
    echo ($active == "DefineControlFrameworks" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"index.php\"> <span>1</span> " . $escaper->escapeHtml($lang['DefineControlFrameworks']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "DocumentProgram" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"documentation.php\"> <span>2</span> " . $escaper->escapeHtml($lang['DocumentProgram']) . "</a>\n";
    echo "</li>\n";
    if (check_permission_exception('view')) {
        echo ($active == "DocumentExceptions" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"document_exceptions.php\"> <span>3</span> " . $escaper->escapeHtml($lang['DocumentExceptions']) . "</a>\n";
        echo "</li>\n";
    }
    echo "</ul>\n";
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

/*********************************
* FUNCTION: VIEW COMPLIANCE MENU *
**********************************/
function view_compliance_menu($active)
{
    global $lang;
    global $escaper;

    echo "<ul class=\"nav nav-pills nav-stacked aside--nav \">\n";
        echo ($active == "DefineTests" ? "<li class=\"active\">\n" : "<li>\n");
            echo "<a href=\"index.php\"> <span>1</span> " . $escaper->escapeHtml($lang['DefineTests']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "InitialAudits" ? "<li class=\"active\">\n" : "<li>\n");
            echo "<a href=\"audit_initiation.php\"> <span>2</span> " . $escaper->escapeHtml($lang['InitiateAudits']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "ActiveAudits" ? "<li class=\"active\">\n" : "<li>\n");
            echo "<a href=\"active_audits.php\"> <span>3</span> " . $escaper->escapeHtml($lang['ActiveAudits']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "PastAudits" ? "<li class=\"active\">\n" : "<li>\n");
            echo "<a href=\"past_audits.php\"> <span>4</span> " . $escaper->escapeHtml($lang['PastAudits']) . "</a>\n";
        echo "</li>\n";
    echo "</ul>\n";
}

/***************************************
* FUNCTION: VIEW ASSET MANAGEMENT MENU *
****************************************/
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
    $has_assets = has_assets();
    if ($has_assets) {
        echo ($active == "EditAssets" ? "<li id=\"EditAssets\" class=\"active\">\n" : "<li id=\"EditAssets\">\n");
        echo "<a href=\"edit.php\"> <span>3</span> " . $escaper->escapeHtml($lang['EditAssets']) . "</a>\n";
        echo "</li>\n";
    }
    echo ($active == "ManageAssetGroups" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"manage_asset_groups.php\"> <span>" . ($has_assets?"4":"3") . "</span>" . $escaper->escapeHtml($lang['ManageAssetGroups']) . "</a>\n";
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
    echo ($active == "SelfAssessments" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"index.php\"> " . $escaper->escapeHtml($lang['SelfAssessments']) . "</a>\n";
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
    echo ($active == "RisksAndIssues" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"risks_and_issues.php\">" . $escaper->escapeHtml($lang['RisksAndIssues']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "RiskAppetiteReport" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"risk_appetite.php\">" . $escaper->escapeHtml($lang['RiskAppetiteReport']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "RiskTrend" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"trend.php\">" . $escaper->escapeHtml($lang['RiskTrend']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "DynamicRiskReport" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"dynamic_risk_report.php\">" . $escaper->escapeHtml($lang['DynamicRiskReport']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "GraphicalRiskAnalysis" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"graphical_risk_analysis.php\">" . $escaper->escapeHtml($lang['GraphicalRiskAnalysis']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "ConnectivityVisualizer" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"connectivity_visualizer.php\">" . $escaper->escapeHtml($lang['ConnectivityVisualizer']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "RiskAverageOverTime" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"risk_average_baseline_metric.php\">" . $escaper->escapeHtml($lang['RiskAverageOverTime']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "LikelihoodImpact" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"likelihood_impact.php\">" . $escaper->escapeHtml($lang['LikelihoodImpact']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "RiskAdvice" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"riskadvice.php\">" . $escaper->escapeHtml($lang['RiskAdvice']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "RisksAndAssets" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"risks_and_assets.php\">" . $escaper->escapeHtml($lang['RisksAndAssets']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "RisksAndControls" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"risks_and_controls.php\">" . $escaper->escapeHtml($lang['RisksAndControls']) . "</a>\n";
    echo "</li>\n";    
    echo ($active == "AllOpenRisksAssignedToMeByRiskLevel" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"my_open.php\">" . $escaper->escapeHtml($lang['AllOpenRisksAssignedToMeByRiskLevel']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "AllOpenRisksNeedingReview" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"review_needed.php\">" . $escaper->escapeHtml($lang['AllOpenRisksNeedingReview']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "AllOpenRisksByTeam" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"risks_open_by_team.php?id=true&risk_status=true&subject=true&calculated_risk=true&submission_date=true&team=true&mitigation_planned=true&management_review=true&owner=true&manager=true\">" . $escaper->escapeHtml($lang['AllOpenRisksByTeamByLevel']) . "</a>\n";
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
    echo ($active == "CurrentRiskComments" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"recent_commented.php\">" . $escaper->escapeHtml($lang['CurrentRiskComments']) . "</a>\n";
    echo "</li>\n";

    // If User has permission for compliance menu, shows Audit Timeline report
    if(!empty($_SESSION['compliance']))
    {
        echo ($active == "AuditTimeline" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"audit_timeline.php\">" . $escaper->escapeHtml($lang['AuditTimeline']) . "</a>\n";
        echo "</li>\n";
    } 

    // If User has permission for governance menu, show Control Gap Analysis report
    if(!empty($_SESSION['governance']))
    {
        echo ($active == "ControlGapAnalysis" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"control_gap_analysis.php\">" . $escaper->escapeHtml($lang['ControlGapAnalysis']) . "</a>\n";
        echo "</li>\n";
    }

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
    if (getTypeOfColumn('mgmt_reviews', 'next_review') == 'varchar') {
        echo ($active == "FixReviewDates" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"fix_review_dates.php\"><i class=\"fa fa-exclamation-circle\" aria-hidden=\"true\" style=\"color: " . ($active == "FixReviewDates" ? "white": "#bd081c") . "; padding-right: 5px;\"></i>" . $escaper->escapeHtml($lang['FixReviewDates']) . "</a>\n";
        echo "</li>\n";
    }
    if (has_files_with_encoding_issues()) {
        echo ($active == "FixFileEncodingIssues" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"fix_upload_encoding_issues.php\"><i class=\"fa fa-exclamation-circle\" aria-hidden=\"true\" style=\"color: " . ($active == "FixFileEncodingIssues" ? "white": "#bd081c") . "; padding-right: 5px;\"></i>" . $escaper->escapeHtml($lang['FixFileEncodingIssues']) . "</a>\n";
        echo "</li>\n";
    }
    echo ($active == "Settings" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"index.php\">" . $escaper->escapeHtml($lang['Settings']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "Content" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"content.php\">" . $escaper->escapeHtml($lang['Content']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "RiskAndThreatCatalog" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"risk_catalog.php\">" . $escaper->escapeHtml($lang['RiskAndThreatCatalog']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "ConfigureRiskFormula" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"configure_risk_formula.php\">" . $escaper->escapeHtml($lang['ConfigureRiskFormula']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "ConfigureReviewSettings" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"review_settings.php\">" . $escaper->escapeHtml($lang['ConfigureReviewSettings']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "AddAndRemoveValues" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"add_remove_values.php\">" . $escaper->escapeHtml($lang['AddAndRemoveValues']) . "</a>\n";
    echo "</li>\n";
    if (organizational_hierarchy_extra()) {
        echo ($active == "OrganizationalHierarchy" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"organizational_hierarchy.php\">" . $escaper->escapeHtml($lang['OrganizationManagement']) . "</a>\n";
        echo "</li>\n";
    }
    echo ($active == "RoleManagement" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"role_management.php\">" . $escaper->escapeHtml($lang['RoleManagement']) . "</a>\n";
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
        echo "<a href=\"importexport.php\">" . $escaper->escapeHtml($lang['ImportExport']) . "</a>\n";
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
    echo "<a href=\"register.php\">" . $escaper->escapeHtml($lang['RegisterAndUpgrade']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "Health Check" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"health_check.php\">" . $escaper->escapeHtml($lang['HealthCheck']) ."</a>\n";
    echo "</li>\n";
    echo ($active == "About" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"about.php\">" . $escaper->escapeHtml($lang['About']) . "</a>\n";
    echo "</li>\n";
    echo "</ul>\n";
}

/**********************************************
* FUNCTION: VIEW RISKS AND ASSETS SELECTIONS *
**********************************************/
function view_risks_and_assets_selections($report, $sort_by, $asset_tags, $projects)
{
    global $lang, $escaper;

    echo "<form name=\"select_report\" method=\"POST\" action=\"\">\n";
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span12\">\n";
    echo "<a href=\"javascript:;\" onclick=\"javascript: closeSearchBox()\"><img src=\"../images/X-100.png\" width=\"10\" height=\"10\" align=\"right\" /></a>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span3\">";
    echo $escaper->escapeHtml($lang['Report']).": ";
    echo "<select id=\"report\" name=\"report\" onchange=\"javascript: submit()\">\n";
    echo "<option value=\"0\"" . ($report == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['RisksByAsset']) . "</option>\n";
    echo "<option value=\"1\"" . ($report == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['AssetsByRisk']) . "</option>\n";
    echo "</select>\n";
    echo "</div>\n";

    echo "<div class=\"span3\">";
    echo $escaper->escapeHtml($lang['AssetTags']).": ";
    create_multiple_dropdown("asset_tags", $asset_tags, NULL, NULL, true, $lang['Unassigned'], "-1");
    echo "</div>\n";

    echo "<div class=\"span3\">";
    echo $escaper->escapeHtml($lang['Project']).": ";
    create_multiple_dropdown("projects", $projects, NULL, NULL, true, $lang['Unassigned'], "-1");
    echo "</div>\n";

    if($report == 0){
        echo "<div class=\"span3\">";
        echo $escaper->escapeHtml($lang['SortBy']).": ";
        echo "<select id=\"sort_by\" name=\"sort_by\" onchange=\"javascript: submit()\">\n";
        echo "<option value=\"0\"" . ($sort_by == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['AssetName']) . "</option>\n";
        echo "<option value=\"1\"" . ($sort_by == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['AssetRisk']) . "</option>\n";
        echo "</select>\n";
        echo "</div>\n";
    }
    echo "</div>\n";
    echo "</form>\n";
    echo "<script>
            $('#asset_tags, #projects').multiselect({
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                enableFiltering: true,
                maxHeight: 250,
                buttonWidth: '100%',
                includeSelectAllOption: true,
                enableCaseInsensitiveFiltering: true,
                onDropdownHide: function(){
                    $('form[name=select_report]').submit();
                }
            });
        </script>";

}
/*********************************************
* FUNCTION: VIEW RISKS AND ISSUES SELECTIONS *
**********************************************/
function view_risks_and_issues_selections($risk_tags, $start_date="", $end_date="")
{
    global $lang, $escaper;
    $start_date  = $start_date ? $start_date : format_date(date('Y-m-d', strtotime('-30 days')));
    $end_date  = $end_date ? $end_date : format_date(date('Y-m-d'));

    echo "<form name=\"issues_report\" method=\"POST\" action=\"\">\n";
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span12\">\n";
    echo "<a href=\"javascript:;\" onclick=\"javascript: closeSearchBox()\"><img src=\"../images/X-100.png\" width=\"10\" height=\"10\" align=\"right\" /></a>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "<div class=\"row-fluid\">\n";

    echo "<div class=\"span3\">";
    echo $escaper->escapeHtml($lang['RiskTags']).": ";
    create_multiple_dropdown("risk_tags", $risk_tags, NULL, NULL, true, $lang['Unassigned'], "-1");
    echo "</div>\n";

    echo "<div class=\"span3\">";
    echo $escaper->escapeHtml($lang['StartDate']).": ";
    echo "<input type=\"text\" name=\"start_date\" value=\"".$start_date."\" class=\"form-control datepicker\">";
    echo "</div>\n";

    echo "<div class=\"span3\">";
    echo $escaper->escapeHtml($lang['EndDate']).": ";
    echo "<input type=\"text\" name=\"end_date\" value=\"".$end_date."\" class=\"form-control datepicker\">";
    echo "</div>\n";

    echo "</div>\n";
    echo "</form>\n";
    echo "<script>
            $(document).ready(function(){
                $('#risk_tags').multiselect({
                    allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                    enableFiltering: true,
                    maxHeight: 250,
                    buttonWidth: '100%',
                    includeSelectAllOption: true,
                    enableCaseInsensitiveFiltering: true,
                    onDropdownHide: function(){
                        $('form[name=issues_report]').submit();
                    }
                });
                $('.datepicker').datepicker();
                $('.datepicker').change(function(){
                    $('form[name=issues_report]').submit();
                });
            });
        </script>";
}

/******************************************
* FUNCTION: VIEW GET RISKS BY SELECTIONS *
******************************************/
function view_get_risks_by_selections($status=0, $group=0, $sort=0, $risk_columns=[], $mitigation_columns=[], $review_columns=[], $scoring_columns=[], $unassigned_columns=[], $risk_mapping_columns=[])
{
    global $lang, $escaper;
    
    $encoded_request_uri = get_encoded_request_uri();
    
    echo "<form id=\"get_risks_by\" name=\"get_risks_by\" method=\"post\" action=\"".$_SESSION['base_url'].$encoded_request_uri."\">\n";
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span12\">\n";
    echo "<a href=\"javascript:;\" onclick=\"javascript: closeSearchBox()\"><img src=\"../images/X-100.png\" width=\"10\" height=\"10\" align=\"right\" /></a>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "
            <div class=\"well\" id='group-selections-container'>
              <h4 class=\"collapsible--toggle clearfix\">
                  <span><i class=\"fa fa-caret-right\"></i>".$escaper->escapeHtml($lang['GroupAndFilteringSelections'])."</span>
              </h4>
              <div class=\"collapsible\" style=\"display: none;\">";
    echo "<div class=\"row-fluid\">\n";

    // Risk Status Selection
    echo "<div class=\"span3\">\n";
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
    echo "<div class=\"span3\">\n";
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
//    echo "<option value=\"3\"" . ($group == 3 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['SiteLocation']) . "</option>\n";
    echo "<option value=\"6\"" . ($group == 6 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Team']) . "</option>\n";
    echo "<option value=\"7\"" . ($group == 7 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Technology']) . "</option>\n";
    echo "</select>\n";
    echo "</div>\n";
    echo "</div>\n";

    // Sort By Selection
    echo "<div class=\"span3\">\n";
    echo "<div class=\"well\">\n";
    echo "<h4>" . $escaper->escapeHtml($lang['SortBy']) . ":</h4>\n";
    echo "<select id=\"sort\" name=\"sort\" onchange=\"javascript: submit()\">\n";
    echo "<option value=\"0\"" . ($sort == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['InherentRisk']) . "</option>\n";
    echo "<option value=\"1\"" . ($sort == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ID']) . "</option>\n";
    echo "<option value=\"2\"" . ($sort == 2 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Subject']) . "</option>\n";
    echo "<option value=\"3\"" . ($sort == 3 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ResidualRisk']) . "</option>\n";
    echo "</select>\n";
    echo "</div>\n";
    echo "</div>\n";

    echo "</div>\n";
    echo "</div></div>\n";

    // Risk columns
    echo display_risk_columns($risk_columns, $mitigation_columns, $review_columns, $scoring_columns, $unassigned_columns, $risk_mapping_columns);
    
    echo "</form>\n";
    echo "<script>
            $(document).ready(function(){
                \$('#group-selections-container').on('click', '.collapsible--toggle span', function(event) {
                    event.preventDefault();
                    \$(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
                    \$(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                });
            });
    </script>";


}

/*************************************************
* FUNCTION: DISPLAY SAVE DYNAMIC RISK SELECTIONS *
**************************************************/
function display_save_dynamic_risk_selections()
{
    global $lang, $escaper;
    
    $selection_id = !empty($_GET['selection']) ? (int)$_GET['selection'] : '';
    
    $options = get_dynamic_saved_selections($_SESSION['uid']);
    
    $private = $escaper->escapeHtml($lang['Private']);
    $public = $escaper->escapeHtml($lang['Public']);
    
    echo "
    <div class=\"well\" id='save-selections-container'>
        <h4 class=\"collapsible--toggle clearfix\">
            <span><i class=\"fa fa-caret-right\"></i>".$escaper->escapeHtml($lang['SaveSelections'])."</span>
        </h4>
        <div class=\"collapsible\" style=\"display: none;\">
            <form method='post' >
                <div class='row-fluid'>
                    <div class='span1'>".$escaper->escapeHtml($lang['SavedSelections']).":</div>
                    <div class='span7'>
                        <script>
                            $(document).ready(function(){
                                $('#saved_selections').selectize({
                                    options: [
                                        {class: '', value: '', name: '--'},";
    $selection = false;
    foreach($options as $option)
    {
        if ($selection_id == $option['value']) {
            $selection = $option;
        }
        echo "
                                        {class: '{$option['type']}', value: '{$option['value']}', name: '{$escaper->escapeHtml($option['name'])}'},";
    }

    echo "
                                    ],
                                    optgroups:
                                        [
                                           {value: 'private', label: '{$private}'},
                                           {value: 'public', label: '{$public}'},
                                        ]
                                    ,
                                    plugins: ['optgroup_columns'],
                                    optgroupField: 'class',
                                    labelField: 'name',
                                    searchField: ['name', 'class'],
                                    maxItems:1,
    ";
        
    if($selection_id) {
        echo "
                                    items: [{$selection_id}],";
    }
    
    echo "
                                    render: {
                                        optgroup_header: function (data) {
                                            return $('<div>', {class: 'optgroup-header'}).text(data.label);
                                        },
                                        option: function (data) {
                                            return $('<div>', {class: 'option'}).html(data.name);
                                        },
                                        item: function (data) {
                                            return $('<div>', {class: 'item'}).html('[' + (data.class == 'private' ? '$private' : '$public') + '] ' + data.name); 
                                        }
                                    }
                                });
                                $('#delete_saved_selection').click(function(e){
                                    e.preventDefault();
                                    confirm('{$escaper->escapeHtml($lang["AreYouSureYouWantToDeleteSelction"])}', 'delete_saved_selection()');
                                });
                            });

                            function delete_saved_selection()
                            {
                                var id = $('#saved_selections').val();
                                $.ajax({
                                    type: 'POST',
                                    url: BASE_URL + '/api/reports/delete-dynamic-selection',
                                    data:{
                                        id: id,
                                    },
                                    success: function(res){
                                        document.location.href = BASE_URL + '/reports/dynamic_risk_report.php';
                                    },
                                    error: function(xhr,status,error){
                                        if(!retryCSRF(xhr, this)){
                                            if(xhr.responseJSON && xhr.responseJSON.status_message) {
                                                showAlertsFromArray(xhr.responseJSON.status_message);
                                            }
                                        }
                                    }
                                });
                            }
                        </script>
    ";

    // Delete button
    if(!$selection || ($selection['user_id'] != $_SESSION['uid'] && !$_SESSION['admin'])){
        $style = "display: none;";
    }else{
        $style = "";
    }

    echo "
                        <select required id='saved_selections'></select>
                    </div>
                    <div class='span1'>
                        <button class='btn' id='delete_saved_selection' style='{$style}'>".$escaper->escapeHtml($lang['Delete'])."</button>
                    </div>
                </div>
            </form>
            <form method='post' id='save-selections-form'>
                <div class='row-fluid'>
                    <div class='span1'>".$escaper->escapeHtml($lang['Type']).":</div>
                    <div class='span2'>
                        <select required id='saved-selection-type' name='type' title='". $escaper->escapeHtml($lang['PleaseSelectTypeForSaving']) ."'>
                            <option value=''>--</option>
                            <option value='public'>{$public}</option>
                            <option value='private'>{$private}</option>
                        </select>
                    </div>
                    <div class='span1'>".$escaper->escapeHtml($lang['Name']).":</div>
                    <div class='span4'>
                        <input name='name' required type='text' placeholder='".$escaper->escapeHtml($lang['Name'])."' title='".$escaper->escapeHtml($lang['Name'])."' style='max-width: unset;'>
                    </div>
                    <div class='span2'><button class='btn' >{$escaper->escapeHtml($lang['Save'])}</button></div>
                </div>
            </form>
        </div>
    </div>";
    
    echo "<script>
            $(document).ready(function(){
                \$('#save-selections-container').on('click', '.collapsible--toggle span', function(event) {
                    event.preventDefault();
                    \$(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
                    \$(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                });
                
                $('#save-selections-form').submit(function(){
                    var self = $(this);
                    var type = $('#saved-selection-type', self).val();
                    var name = $('input[name=name]', self).val();
                    
                    var viewColumns = [];
                    var risk_columns = $('#risk_columns').val();
                    var mitigation_columns = $('#mitigation_columns').val();
                    var review_columns = $('#review_columns').val();
                    var scoring_columns = $('#scoring_columns').val();
                    var risk_mapping_columns = $('#risk_mapping_columns').val();
                    var selected_columns = risk_columns.concat(mitigation_columns, review_columns, scoring_columns, risk_mapping_columns);
                    var columnFilters = [];
                    $('.risk-datatable:first .dynamic-column-filter').each(function(i){
                        if($(this).val().length > 0){
                            var data_name = $(this).attr('data-name');
                            columnFilters.push([data_name,$(this).val()]);
                        }
                    });
                    var selectFilters = {status:0,group:0,sort:0};
                    selectFilters.status = $('#status').val();
                    selectFilters.group = $('#group').val();
                    selectFilters.sort = $('#sort').val();

                    var test = $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/reports/save-dynamic-selections',
                        data:{
                            type: type,
                            name: name,
                            columns: selected_columns,
                            selects: selectFilters,
                            columnFilters: columnFilters,
                        },
                        success: function(res){
                            var value = res.data.value;
                            if(value) {
                                var stz = $('#saved_selections')[0].selectize;
                                
                                var data = {
                                    'class':res.data.type,
                                    'value':value,
                                    'name':name 
                                };
                                stz.addOption(data);
                                stz.refreshOptions();

                                self[0].reset();
                            }
                            showAlertsFromArray(res.status_message);
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    
                    return false;
                })
                
                $('#saved_selections').change(function(){
                    var selection = $(this).val();
                    if(selection){
                        document.location.href = BASE_URL + '/reports/dynamic_risk_report.php?selection=' + selection;
                    } else {
                        document.location.href = BASE_URL + '/reports/dynamic_risk_report.php';
                    }
                    return true;
                })
            })
        </script>";
}

/*********************************
* FUNCTION: DISPLAY RISK COLUMNS *
**********************************/
function display_risk_columns($risk_columns=[], $mitigation_columns=[], $review_columns=[], $scoring_columns=[], $unassigned_columns=[], $risk_mapping_columns=[]){
    global $escaper, $lang;
    echo "
        <div class=\"well\" id='column-selections-container'>
          <h4 class=\"collapsible--toggle clearfix\">
              <span><i class=\"fa fa-caret-right\"></i>".$escaper->escapeHtml($lang['ColumnSelections'])."</span>
          </h4>
          <div class=\"collapsible\" style=\"display: none;\">";

    $risk_columns_option = [];
    $risk_columns_selected = [];
    $mitigation_columns_option = [];
    $mitigation_columns_selected = [];
    $review_columns_option = [];
    $review_columns_selected = [];
    $scoring_columns_option = [];
    $scoring_columns_selected = [];
    $unassigned_columns_option = [];
    $unassigned_columns_selected = [];
    $risk_mapping_columns_option = [];
    $risk_mapping_columns_selected = [];

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        foreach($risk_columns as $column=>$value){
            if(stripos($column, "custom_field_") !== false){
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $name = $custom_field['name'];
            } else {
                $name = get_label_by_risk_field_name($column);
            }
            $risk_columns_option[] = array('value'=>$column, 'name'=>$name);
            if($value == true) $risk_columns_selected[] = $column;
        }
        foreach($mitigation_columns as $column=>$value){
            if(stripos($column, "custom_field_") !== false){
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $name = $custom_field['name'];
            } else {
                $name = get_label_by_risk_field_name($column);
            }
            $mitigation_columns_option[] = array('value'=>$column, 'name'=>$name);
            if($value == true) $mitigation_columns_selected[] = $column;
        }
        foreach($review_columns as $column=>$value){
            if(stripos($column, "custom_field_") !== false){
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $name = $custom_field['name'];
            } else {
                $name = get_label_by_risk_field_name($column);
            }
            $review_columns_option[] = array('value'=>$column, 'name'=>$name);
            if($value == true) $review_columns_selected[] = $column;
        }
        foreach($scoring_columns as $column=>$value){
            $name = get_label_by_risk_field_name($column);
            $scoring_columns_option[] = array('value'=>$column, 'name'=>$name);
            if($value == true) $scoring_columns_selected[] = $column;
        }
        foreach($unassigned_columns as $column=>$value){
            if(stripos($column, "custom_field_") !== false){
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $name = $custom_field['name'];
            } else {
                $name = get_label_by_risk_field_name($column);
            }
            $unassigned_columns_option[] = array('value'=>$column, 'name'=>$name);
            if($value == true) $unassigned_columns_selected[] = $column;
        }
    } else {
        foreach($risk_columns as $column=>$value){
            $name = get_label_by_risk_field_name($column);
            $risk_columns_option[] = array('value'=>$column, 'name'=>$name);
            if($value == true) $risk_columns_selected[] = $column;
        }
        foreach($mitigation_columns as $column=>$value){
            $name = get_label_by_risk_field_name($column);
            $mitigation_columns_option[] = array('value'=>$column, 'name'=>$name);
            if($value == true) $mitigation_columns_selected[] = $column;
        }
        foreach($review_columns as $column=>$value){
            $name = get_label_by_risk_field_name($column);
            $review_columns_option[] = array('value'=>$column, 'name'=>$name);
            if($value == true) $review_columns_selected[] = $column;
        }
        foreach($scoring_columns as $column=>$value){
            $name = get_label_by_risk_field_name($column);
            $scoring_columns_option[] = array('value'=>$column, 'name'=>$name);
            if($value == true) $scoring_columns_selected[] = $column;
        }
    }
    foreach($risk_mapping_columns as $column=>$value){
        $name = get_label_by_risk_field_name($column);
        $risk_mapping_columns_option[] = array('value'=>$column, 'name'=>$name);
        if($value == true) $risk_mapping_columns_selected[] = $column;
    }
    echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span4\">\n";
            echo "<h4>" . $escaper->escapeHtml($lang['RiskColumns']) . ":</h4>\n";
            create_multiple_dropdown("", $risk_columns_selected, "risk_columns", $risk_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
            echo "</div>";
            echo "<div class=\"span4\">\n";
            echo "<h4>" . $escaper->escapeHtml($lang['MitigationColumns']) . ":</h4>\n";
            create_multiple_dropdown("", $mitigation_columns_selected, "mitigation_columns", $mitigation_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
            echo "</div>";
            echo "<div class=\"span4\">\n";
            echo "<h4>" . $escaper->escapeHtml($lang['ReviewColumns']) . ":</h4>\n";
            create_multiple_dropdown("", $review_columns_selected, "review_columns", $review_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
            echo "</div>";
        echo "</div>";
        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span4\">\n";
            echo "<h4>" . $escaper->escapeHtml($lang['RiskScoringColumns']) . ":</h4>\n";
            create_multiple_dropdown("", $scoring_columns_selected, "scoring_columns", $scoring_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
            echo "</div>";
        if(count($unassigned_columns_option)){
            echo "<div class=\"span4\">\n";
            echo "<h4>" . $escaper->escapeHtml($lang['UnassignedColumns']) . ":</h4>\n";
            create_multiple_dropdown("", $unassigned_columns_selected, "unassigned_columns", $unassigned_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
            echo "</div>";
        }
            echo "<div class=\"span4\">\n";
            echo "<h4>" . $escaper->escapeHtml($lang['RiskMappingColumns']) . ":</h4>\n";
            create_multiple_dropdown("", $risk_mapping_columns_selected, "risk_mapping_columns", $risk_mapping_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
            echo "</div>";
        echo "</div>";
    echo "</div>";
    echo "</div></div>\n";
    echo "<script>
            $(document).ready(function(){
                \$('#column-selections-container').on('click', '.collapsible--toggle span', function(event) {
                    event.preventDefault();
                    \$(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
                    \$(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                });
            });
        </script>";

}

/*************************************************
* FUNCTION: GET DYNAMIC NAMES BY MAIN FIELD NAME *
**************************************************/
function get_dynamic_names_by_main_field_name($field_name)
{
    global $lang, $escaper;
    
    $data = array(
        // risks
        'ExternalReferenceId' => 
            [
                'name' => "reference_id",
                'text' => $escaper->escapeHtml($lang['ExternalReferenceId']),
            ],
        'ControlRegulation' => 
            [
                'name' => "regulation",
                'text' => $escaper->escapeHtml($lang['ControlRegulation']),
            ],
        'ControlNumber' => 
            [
                'name' => "control_number",
                'text' => $escaper->escapeHtml($lang['ControlNumber']),
            ],
        'SiteLocation' => 
            [
                'name' => "location",
                'text' => $escaper->escapeHtml($lang['SiteLocation']),
            ],
        'RiskSource' => 
            [
                'name' => "source",
                'text' => $escaper->escapeHtml($lang['RiskSource']),
            ],
        'Category' => 
            [
                'name' => "category",
                'text' => $escaper->escapeHtml($lang['Category']),
            ],
        'Team' => 
            [
                'name' => "team",
                'text' => $escaper->escapeHtml($lang['Team']),
            ],
        'Technology' => 
            [
                'name' => "technology",
                'text' => $escaper->escapeHtml($lang['Technology']),
            ],
        'Owner' => 
            [
                'name' => "owner",
                'text' => $escaper->escapeHtml($lang['Owner']),
            ],
        'OwnersManager' => 
            [
                'name' => "manager",
                'text' => $escaper->escapeHtml($lang['OwnersManager']),
            ],
        'SubmittedBy' => 
            [
                'name' => "submitted_by",
                'text' => $escaper->escapeHtml($lang['SubmittedBy']),
            ],
        'RiskScoringMethod' => 
            [
                'name' => "scoring_method",
                'text' => $escaper->escapeHtml($lang['RiskScoringMethod']),
            ],
        'SubmissionDate' => 
            [
                'name' => "submission_date",
                'text' => $escaper->escapeHtml($lang['SubmissionDate']),
            ],
        'AffectedAssets' => 
            [
                'name' => "affected_assets",
                'text' => $escaper->escapeHtml($lang['AffectedAssets']),
            ],
        'RiskAssessment' => 
            [
                'name' => "risk_assessment",
                'text' => $escaper->escapeHtml($lang['RiskAssessment']),
            ],
        'AdditionalNotes' => 
            [
                'name' => "additional_notes",
                'text' => $escaper->escapeHtml($lang['AdditionalNotes']),
            ],
//        'SupportingDocumentation' => 
//            [
//                'name' => "submission_date",
//                'text' => $escaper->escapeHtml($lang['SubmissionDate']),
//            ],
        'AdditionalStakeholders' => 
            [
                'name' => "additional_stakeholders",
                'text' => $escaper->escapeHtml($lang['AdditionalStakeholders']),
            ],
        
        // mitigations
        'PlanningStrategy' => 
            [
                'name' => "planning_strategy",
                'text' => $escaper->escapeHtml($lang['PlanningStrategy']),
            ],
        'MitigationPlanning' => 
            [
                'name' => "planning_date",
                'text' => $escaper->escapeHtml($lang['MitigationPlanning']),
            ],
        'MitigationEffort' => 
            [
                'name' => "mitigation_effort",
                'text' => $escaper->escapeHtml($lang['MitigationEffort']),
            ],
        'MitigationCost' => 
            [
                'name' => "mitigation_cost",
                'text' => $escaper->escapeHtml($lang['MitigationCost']),
            ],
        'MitigationOwner' => 
            [
                'name' => "mitigation_owner",
                'text' => $escaper->escapeHtml($lang['MitigationOwner']),
            ],
        'MitigationPercent' => 
            [
                'name' => "mitigation_percent",
                'text' => $escaper->escapeHtml($lang['MitigationPercent']),
            ],
        'MitigationTeam' => 
            [
                'name' => "mitigation_team",
                'text' => $escaper->escapeHtml($lang['MitigationTeam']),
            ],
        'MitigationDate' => 
            [
                'name' => "mitigation_date",
                'text' => $escaper->escapeHtml($lang['MitigationDate']),
            ],
        'MitigationControls' => 
            [
                'name' => "mitigation_controls",
                'text' => $escaper->escapeHtml($lang['MitigationControls']),
            ],
//        'MitigationPercent' => 
//            [
//                'name' => "submission_date",
//                'text' => $escaper->escapeHtml($lang['SubmissionDate']),
//            ],
        'AcceptMitigation' => 
            [
                'name' => "mitigation_accepted",
                'text' => $escaper->escapeHtml($lang['MitigationAccepted']),
            ],
        'CurrentSolution' => 
            [
                'name' => "current_solution",
                'text' => $escaper->escapeHtml($lang['CurrentSolution']),
            ],
        'SecurityRecommendations' => 
            [
                'name' => "security_recommendations",
                'text' => $escaper->escapeHtml($lang['SecurityRecommendations']),
            ],
        'SecurityRequirements' => 
            [
                'name' => "security_requirements",
                'text' => $escaper->escapeHtml($lang['SecurityRequirements']),
            ],
//        'MitigationSupportingDocumentation' => 
//            [
//                'name' => "submission_date",
//                'text' => $escaper->escapeHtml($lang['SubmissionDate']),
//            ],
//        'MitigationControlsList' => 
//            [
//                'name' => "submission_date",
//                'text' => $escaper->escapeHtml($lang['SubmissionDate']),
//            ],
        
        // Review
        'ReviewDate' => 
            [
                'name' => "review_date",
                'text' => $escaper->escapeHtml($lang['ReviewDate']),
            ],
//        'Reviewer' => 
//            [
//                'name' => "submission_date",
//                'text' => $escaper->escapeHtml($lang['SubmissionDate']),
//            ],
//        'Review' => 
//            [
//                'name' => "submission_date",
//                'text' => $escaper->escapeHtml($lang['SubmissionDate']),
//            ],
        'NextReviewDate' => 
            [
                'name' => "next_review_date",
                'text' => $escaper->escapeHtml($lang['NextReviewDate']),
            ],
        'NextStep' => 
            [
                'name' => "next_step",
                'text' => $escaper->escapeHtml($lang['NextStep']),
            ],
        'Tags' =>
            [
                'name' => "risk_tags",
                'text' => $escaper->escapeHtml($lang['Tags']),
            ],
        'Comment' => 
            [
                'name' => "comments",
                'text' => $escaper->escapeHtml($lang['Comments']),
            ],
        'RiskMapping' => 
            [
                'name' => "risk_mapping",
                'text' => $escaper->escapeHtml($lang['RiskMapping']),
            ],
        'ThreatMapping' => 
            [
                'name' => "threat_mapping",
                'text' => $escaper->escapeHtml($lang['ThreatMapping']),
            ]
    );
    return isset($data[$field_name]) ? $data[$field_name] : "";
}

/************************************************
* FUNCTION: DISPLAY SIMPLE AUTOCOMPLETE SCRIPT *
************************************************/
function display_simple_autocomplete_script($assets)
{
    global $escaper;
    $asset_array = array();
    foreach($assets as $asset){
        $asset_array[] = remove_line_breaks($escaper->escapeHtml($asset['name']));
    }

    echo "<script>\n";
    echo "  $(function() {\n";
        echo "    var availableAssets = ". json_encode($asset_array) .";\n";
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
                            echo "      }\n";
                            echo "    })\n";

                            echo "    .focus(function(){\n";
                            echo "          var self = \$(this);\n";
                            echo "          window.setTimeout(function(){\n";
                            echo "              self.autocomplete(\"search\", \"\");\n";
                            echo "          }, 1000);\n";
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
        echo "if(jQuery.ui !== undefined){\n";
            echo "jQuery.ui.autocomplete.prototype._resizeMenu = function () {\n";
                echo "var ul = this.menu.element;\n";
                echo "ul.outerWidth(this.element.outerWidth());\n";
            echo "}\n";
        echo "}\n";

        echo "    availableAssets = [\n";

        // For each asset
        foreach ($assets as $asset)
        {
            // Display the asset name as an available asset
            echo "      \"" . remove_line_breaks($escaper->escapeJs(try_decrypt($asset['name']))) . "\",\n";
        }

        echo "    ];\n";
        echo "    split = function( val ) {\n";
        echo "          return val.split( /,\s*/ );\n";
        echo "    }\n";
        echo "    extractLast = function ( term ) {\n";
        echo "          return split( term ).pop();\n";
        echo "    }\n";

        echo "    function set_auto_complete(\$element){\n";
            echo "    \$element\n";
            echo "      // don't navigate away from the field on tab when selecting an item\n";
            echo "      .bind( \"keydown\", function( event ) {\n";
                echo "        if ( event.keyCode === $.ui.keyCode.TAB && $( this ).autocomplete( \"instance\" ).menu.active ) {\n";
                    echo "          event.preventDefault();\n";
                    echo "        }\n";
                    echo "      })\n";
            echo "      .autocomplete({\n";
                echo "          minLength: 0,\n";
                echo "          source: function( request, response ) {\n";
                    echo "          // delegate back to autocomplete, but extract the last term\n";
                    echo "          response( $.ui.autocomplete.filter(\n";
                    echo "          availableAssets, extractLast( request.term ) ) );\n";
                    echo "      },\n";
                    echo "      focus: function() { \n";
                    echo "          // prevent value inserted on focus\n";
                    echo "          return false;\n";
                    echo "      },\n";
                    echo "      select: function( event, ui ) {\n";
                        echo "        var terms = split( this.value );\n";
                        echo "        // remove the current input\n";
                        echo "        terms.pop();\n";
                        echo "        // add the selected item\n";
                        echo "        terms.push( ui.item.value );\n";
                        echo "        // add placeholder to get the comma-and-space at the end\n";
                        echo "        terms.push( \"\" );\n";
                        echo "        terms = terms.reverse().filter(function (e, i, arr) {\n";
                        echo "              return arr.indexOf(e, i+1) === -1;\n";
                        echo "        }).reverse();\n";
                        echo "        this.value = terms.join( \", \" );\n";
                        echo "        return false;\n";
                    echo "      }\n";
                echo "    })\n";
                echo "    .focus(function(){\n";
                echo "          var self = \$(this);\n";
                echo "          window.setTimeout(function(){\n";
                echo "              self.autocomplete(\"search\", \"\");\n";
                echo "          }, 1000);\n";
                echo "    });\n";
        echo "    }\n";

        echo "     $(document).ready(function(){ \n";
        echo "        set_auto_complete($( \"#assets, .assets\" )); \n";
        echo "     }) \n";

    echo "</script>\n";
}


/*********************************************
* FUNCTION: DISPLAY REGISTRATION TABLE EDIT *
*********************************************/
function display_registration_table_edit($name="", $company="", $title="", $phone="", $email="", $fname="", $lname="")
{
    global $escaper;
    global $lang;

    echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    echo "  <tr>\n";
    echo "    <td>" . $escaper->escapeHtml($lang['FirstName']) . ":&nbsp;</td>\n";
    echo "    <td><input type=\"text\" name=\"fname\" id=\"fname\" value=\"" . $escaper->escapeHtml($fname) . "\" /></td>\n";
    echo "  </tr>\n";
    echo "  <tr>\n";
    echo "    <td>" . $escaper->escapeHtml($lang['LastName']) . ":&nbsp;</td>\n";
    echo "    <td><input type=\"text\" name=\"lname\" id=\"lname\" value=\"" . $escaper->escapeHtml($lname) . "\" /></td>\n";
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
function display_registration_table($name="", $company="", $title="", $phone="", $email="", $fname="", $lname="")
{
    global $escaper;
    global $lang;

    echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    echo "  <tr>\n";
    echo "    <td>" . $escaper->escapeHtml($lang['FirstName']) . ":&nbsp;</td>\n";
    echo "    <td><input type=\"text\" name=\"fname\" id=\"fname\" value=\"" . $escaper->escapeHtml($fname) . "\" title=\"" . $escaper->escapeHtml($fname) . "\" disabled=\"disabled\" /></td>\n";
    echo "  </tr>\n";
    echo "  <tr>\n";
    echo "    <td>" . $escaper->escapeHtml($lang['LastName']) . ":&nbsp;</td>\n";
    echo "    <td><input type=\"text\" name=\"lname\" id=\"lname\" value=\"" . $escaper->escapeHtml($lname) . "\" title=\"" . $escaper->escapeHtml($lname) . "\" disabled=\"disabled\" /></td>\n";
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

/*************************************
* FUNCTION: DISPLAY SELF ASSESSMENTS *
**************************************/
function display_self_assessments()
{
    global $lang;
    global $escaper;

    // Get the assessments
    $assessments = get_assessment_names();

    // If the pending_risks tab is selected
    if ((isset($_GET['tab']) && $_GET['tab'] == "pending_risks") || (isset($_POST['tab']) && $_POST['tab'] == "pending_risks"))
    {
        $self_assessments_class = "";
        $self_assessment_style = "style=\"display: none\"";;
        $pending_risks_class = "class=\"active\"";
        $pending_risks_style = "";
    }
    // Display the self assessments tab
    else
    {
        $self_assessments_class = "class=\"active\"";
        $self_assessment_style = "";
        $pending_risks_class = "";
        $pending_risks_style = "style=\"display: none\"";;
    }

    echo "<div class='row-fluid'>\n";
    echo "
            <div class=\"span12\">
              <div class=\"wrap\">
                <ul class=\"tabs group\">
                  <li><a {$self_assessments_class} href=\"#/self_assessments\">" . $escaper->escapeHtml($lang['Assessments']) . "</a></li>
                  <li><a {$pending_risks_class} href=\"#/pending_risks\">" . $escaper->escapeHtml($lang['PendingRisks']) . "</a></li>
                </ul>
                <div id=\"content\">
                  <div id=\"self_assessments\" class=\"settings_tab\" {$self_assessment_style}>
    ";

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
    echo "        </div>\n";

    echo "        <div id=\"pending_risks\" class=\"settings_tab\" {$pending_risks_style}>\n";
    display_pending_risks();
    echo "        </div>\n";

    echo "      </div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</div>\n";
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

    // If the pending_risks tab is selected
    if ((isset($_GET['tab']) && $_GET['tab'] == "pending_risks") || (isset($_POST['tab']) && $_POST['tab'] == "pending_risks"))
    {
        $self_assessments_class = "";
        $self_assessment_style = "style=\"display: none\"";
        $pending_risks_class = "class=\"active\"";
        $pending_risks_style = "";
    }
    // Display the self assessments tab
    else
    {
        $self_assessments_class = "class=\"active\"";
        $self_assessment_style = "";
        $pending_risks_class = "";
        $pending_risks_style = "style=\"display: none\"";
    }

    echo "<div class='row-fluid'>\n";
    echo "
            <div class=\"span12\">
              <div class=\"wrap\">
                <ul class=\"tabs group\">
                  <li><a {$self_assessments_class} href=\"#/self_assessments\">" . $escaper->escapeHtml($lang['Assessments']) . "</a></li>
                  <li><a {$pending_risks_class} href=\"#/pending_risks\">" . $escaper->escapeHtml($lang['PendingRisks']) . "</a></li>
                </ul>
                <div id=\"content\">
                  <div id=\"self_assessments\" class=\"settings_tab\" {$self_assessment_style}>
    ";

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
    $show_autocomplete = get_setting("ASSESSMENT_ASSET_SHOW_AVAILABLE");

    if ($show_autocomplete)
        $AffectedAssetsWidgetPlaceholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);
    else
        $AffectedAssetsWidgetPlaceholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetNoDropdownPlaceholder']);

    echo "<center><h3>" . $escaper->escapeHtml($assessment_name) . "</h3></center>\n";
    echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    echo "<tr>\n";
    echo "<td align=\"left\" width=\"200px\"><h4>" . $escaper->escapeHtml($lang['AssetName']) . ":</h4></td>\n";
    echo "<td align=\"left\">
            <select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='$AffectedAssetsWidgetPlaceholder'></select>

            <script>
                var assets_and_asset_groups = [];

                $(document).ready(function(){";

    if ($show_autocomplete)
        echo "
                    $.ajax({
                        url: BASE_URL + '/api/asset-group/options',
                        type: 'GET',
                        dataType: 'json',
                        success: function(res) {
                            var data = res.data;
                            var len = data.length;
                            for (var i = 0; i < len; i++) {
                                var item = data[i];
                                if (item.class == 'group')
                                    item.id = '[' + item.name + ']';
                                else
                                    item.id = item.name;
                                
                                assets_and_asset_groups.push(item);
                            }";

    echo "
                            selectize_pending_risk_affected_assets_widget($('select.assets-asset-groups-select'), assets_and_asset_groups);";

    if ($show_autocomplete)
        echo "
                        }
                    });";

    echo "
                });
            </script>
          </td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    // Get the assessment
    $assessment = get_assessment($assessment_id);

    // Set a variable to track the current question
    $current_question = "";


    $rows = array();
    foreach ($assessment as $row)
    {
        $question_id = (int)$row['question_id'];
        if(!isset($rows[$question_id])){
            $question = $row['question'];
            $rows[$question_id] = array(
                'question' => $question,
                'answers' => array(),
            );
        }
        $rows[$question_id]['answers'][] = array(
            'id' => (int)$row['answer_id'],
            'answer' => $row['answer'],
        );
    }

    // For each row in the array
    foreach ($rows as $question_id => $row){
        // Display the question
        echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr>\n";
        echo "<th align=\"left\"><div class=\"question\">" . $escaper->escapeHtml($row['question']) . "<div></th>\n";
        echo "</tr>\n";
        echo "</table>\n";

        // Display the answers
        echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        foreach($row['answers'] as $answer){
            echo "<tr>\n";
            echo "<td><input class=\"hidden-radio\" id=\"". $escaper->escapeHtml($answer['id']) ."\" type=\"radio\" name=\"" . $escaper->escapeHtml($question_id) . "\" value=\"" . $escaper->escapeHtml($answer['id']) . "\" /><label for=\"". $escaper->escapeHtml($answer['id']) ."\">".$escaper->escapeHtml($answer['answer'])."</label> </td>\n";
            echo "</tr>\n";
        }

        echo "<tr>\n";
        echo "<td><textarea class=\"assessment-comment\" name=\"comment[" . $question_id . "]\" placeholder=\"".$lang['Comment']."\"></textarea></td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "<div class=\"end-question\"></div>";
    }

    echo "<div class=\"form-actions\"><input type=\"submit\" name=\"submit_assessment\" value=\"" . $escaper->escapeHtml($lang['Submit']) . "\" /></div>\n";
    echo "</form>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";

    echo "        </div>\n";

    echo "        <div id=\"pending_risks\" class=\"settings_tab\" {$pending_risks_style}>\n";
    display_pending_risks();
    echo "        </div>\n";

    echo "      </div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
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
    $affected_assets_placeholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);

    $maxlength = (int)get_setting('maximum_risk_subject_length', 300);
    $date_time_format = get_default_datetime_format('H:i:s');

    // For each pending risk
    foreach($risks as $risk)
    {
        // Get the assessment name
        $assessment = get_assessment_names($risk['assessment_id']);
        $submission_date = format_datetime($risk['submission_date'], '', 'H:i:s');

        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"submit_risk\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
        echo "<input type=\"hidden\" name=\"assessment_id\" value=\"" . $escaper->escapeHtml($risk['assessment_id']) . "\" />\n";
        echo "<input type=\"hidden\" name=\"pending_risk_id\" value=\"" . $escaper->escapeHtml($risk['id']) . "\" />\n";
        echo "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
        echo "<tr>\n";
        echo "<td style=\"white-space: nowrap;\">".$lang['SubmissionDate'] . ":&nbsp;&nbsp;</td>\n";
        echo "<td width=\"99%\"><input type=\"text\"  style=\"width: 97%;\" name=\"submission_date\" value=\"" . $escaper->escapeHtml($submission_date) . "\" /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"white-space: nowrap;\">".$lang['Subject'] . ":&nbsp;&nbsp;</td>\n";
        echo "<td width=\"99%\"><input maxlength='{$maxlength}' type=\"text\" style=\"width: 97%;\" name=\"subject\" value=\"" . $escaper->escapeHtml($risk['subject']) . "\" /></td>\n";
        echo "</tr>\n";
        if($risk['scoring_method'])
            display_score_html_from_pending_risk($risk['scoring_method'], $risk['Custom'], $risk['CLASSIC_likelihood'], $risk['CLASSIC_impact'], $risk['CVSS_AccessVector'], $risk['CVSS_AccessComplexity'], $risk['CVSS_Authentication'], $risk['CVSS_ConfImpact'], $risk['CVSS_IntegImpact'], $risk['CVSS_AvailImpact'], $risk['CVSS_Exploitability'], $risk['CVSS_RemediationLevel'], $risk['CVSS_ReportConfidence'], $risk['CVSS_CollateralDamagePotential'], $risk['CVSS_TargetDistribution'], $risk['CVSS_ConfidentialityRequirement'], $risk['CVSS_IntegrityRequirement'], $risk['CVSS_AvailabilityRequirement'], $risk['DREAD_DamagePotential'], $risk['DREAD_Reproducibility'], $risk['DREAD_Exploitability'], $risk['DREAD_AffectedUsers'], $risk['DREAD_Discoverability'], $risk['OWASP_SkillLevel'], $risk['OWASP_Motive'], $risk['OWASP_Opportunity'], $risk['OWASP_Size'], $risk['OWASP_EaseOfDiscovery'], $risk['OWASP_EaseOfExploit'], $risk['OWASP_Awareness'], $risk['OWASP_IntrusionDetection'], $risk['OWASP_LossOfConfidentiality'], $risk['OWASP_LossOfIntegrity'], $risk['OWASP_LossOfAvailability'], $risk['OWASP_LossOfAccountability'], $risk['OWASP_FinancialDamage'], $risk['OWASP_ReputationDamage'], $risk['OWASP_NonCompliance'], $risk['OWASP_PrivacyViolation']);
        else{
            display_score_html_from_pending_risk(5, $risk['Custom']);
        }
        echo "<tr>\n";
        echo "<td style=\"white-space: nowrap;\">".$lang['Owner'] . ":&nbsp;&nbsp;</td>\n";
        echo "<td width=\"99%\">\n";
        create_dropdown("enabled_users", $risk['owner'], "owner");
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"white-space: nowrap;\">".$lang['AffectedAssets'] . ":&nbsp;&nbsp;</td>\n";
        echo "<td width=\"99%\">
                <select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='$affected_assets_placeholder'>";

            if ($risk['affected_assets']){
            foreach(explode(',', $risk['affected_assets']) as $value) {

                $value = $name = trim($value);

                if (preg_match('/^\[(.+)\]$/', $name, $matches)) {
                    $name = trim($matches[1]);
                    $type = 'group';
                } else $type = 'asset';

                echo "<option value='" . $escaper->escapeHtml($value) . "' selected data-class='$type'>" . $escaper->escapeHtml($name) . "</option>";
            }
        }

        echo "</select></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"white-space: nowrap;\">".$lang['AdditionalNotes'] . ":&nbsp;&nbsp;</td>\n";
        echo "<td width=\"99%\"><textarea name=\"note\" style=\"width: 97%;\" cols=\"50\" rows=\"3\" id=\"note\">Risk created using the &quot;" . $escaper->escapeHtml($assessment['name']) . "&quot; assessment.\n".$escaper->escapeHtml($risk['comment'])."</textarea></td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "<div class=\"form-actions\">\n";
        if (isset($_SESSION["submit_risks"]) && $_SESSION["submit_risks"] == 1) {
            echo "<button type=\"submit\" name=\"add\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Add']) . "</button>\n";
            echo "<button type=\"submit\" name=\"delete\" class=\"btn\">" . $escaper->escapehtml($lang['Delete']) . "</button>\n";
        }
        echo "</div>\n";
        echo "</form>\n";
        echo "</div>\n";
    }

    //echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "
        <script>
            var assets_and_asset_groups = [];
            $(document).ready(function(){
                $.ajax({
                    url: BASE_URL + '/api/asset-group/options',
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        var data = res.data;
                        var len = data.length;
                        for (var i = 0; i < len; i++) {
                            var item = data[i];
                            if (item.class == 'group')
                                item.id = '[' + item.name + ']';
                            else
                                item.id = item.name;
                            
                            assets_and_asset_groups.push(item);
                        }

                        $('select.assets-asset-groups-select').each(function() {

                            // Need the .slice to force create a new array, 
                            // so we're not adding the items to the original
                            var combined_assets_and_asset_groups = assets_and_asset_groups.slice();
                            // Have to add the selected assets to the list of options,
                            // but only for THIS widget
                            $(this).find('option').each(function() {

                                combined_assets_and_asset_groups.push({
                                    id: $(this).val(),
                                    name: $(this).text(),
                                    class: $(this).data('class')
                                });
                            });

                            selectize_pending_risk_affected_assets_widget($(this), combined_assets_and_asset_groups);
                            
                            //Need it to make it as wide as the textbox below
                            $(this).parent().find('.selectize-control>div').css('width', '97%');

                        });
                    }
                });
                $('input[name=\"submission_date\"]').datetimepicker({
                  lazyInit: true,
                  format: '{$date_time_format}',
                  step: 5
                });
            });
        </script>
    ";
}

/******************************************
 * FUNCTION: RISK AVERAGE BASELINE METRIC *
 *****************************************/
function risk_average_baseline_metric($time = "day", $title = ""){
    global $lang;

    $chart = new Highchart();
    $chart->includeExtraScripts();

    // Set the timezone to the one configured for SimpleRisk
    $chart->chart->time->useUTC = false;
    $chart->chart->time->timezone = get_setting("default_timezone");

    $chart->chart->type = "arearange";
    $chart->chart->zoomType = "x";
    $chart->title->text = $title;
    $chart->xAxis->type = "datetime";
    $chart->xAxis->dateTimeLabelFormats = array(
        "day" => "%Y-%m-%d",
        "month" => "%b %Y",
    );
    $chart->yAxis->title->text = null;
    $chart->yAxis->min = 0;
    $chart->yAxis->max = 10;
    $chart->yAxis->gridLineWidth = 0;

    $risk_levels = get_risk_levels();
    $risk_levels = array_reverse($risk_levels);

    $chart->yAxis->plotBands = array();

    $to = 10;
    foreach($risk_levels as $risk_level){
        $chart->yAxis->plotBands[] = array(
            "color" => $risk_level['color'],
            "to" => $to,
            "from" => $risk_level['value'],
        );
        $to = $risk_level['value'];
    }

    $chart->tooltip = array(
        'crosshairs' => true,
        'shared' => true
    );
    $chart->legend->enabled = false;
    $chart->chart->renderTo = "risk_score_average";
    $chart->credits->enabled = false;
    $chart->plotOptions->series->marker->enabled = false;

    // These set the marker symbol when selected
    $chart->plotOptions->series->marker->symbol = "circle";
    $chart->plotOptions->series->marker->states->hover->enabled = true;
    $chart->plotOptions->series->marker->states->hover->fillColor = "white";
    $chart->plotOptions->series->marker->states->hover->lineColor = "black";
    $chart->plotOptions->series->marker->states->hover->lineWidth = "2";

    // Get the opened risks array by month
    $risk_scores = get_risks_score_averages($time);

        // If the opened risks array is empty
        if (empty($risk_scores))
        {
            $data[] = array($lang['NoDataAvailable'], 0);
        }
        // Otherwise
        else
        {
            $scoreSum = 0;
            $countSum = 0;

            foreach($risk_scores as $date => $risk_score){
                $scoreSum +=  $risk_score['score'];
                $opened = isset($risk_score['opened']) ? $risk_score['opened'] : 0;
                $closed = isset($risk_score['closed']) ? $risk_score['closed'] : 0;
                $countSum +=  $opened + $closed;

                // Create the data arrays
                $data[] = array(
                    'x' => strtotime($date) * 1000,
                    'y' => round($scoreSum / $countSum, 2),
                    'opened' => $opened,
                    'closed' => $closed,
                );
            }

        // Draw the open risks line
            $chart->series[] = array(
                'type' => "line",
                'name' => "Risk Score Average",
                'color' => "black",
                // 'lineWidth' => "2",
                'data' => $data
            );

        }

    $chart->printScripts();
    echo "<div id=\"risk_score_average\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("risk_score_average");

    if($time == "year"){
        $timeFormat = "%Y";
    }elseif($time == "month"){
        $timeFormat = "%B %Y";
    }else{
        $timeFormat = "%b %e, %Y";
    }

    echo "
        risk_score_average.update({
            tooltip: {
                formatter: function(){
                    var date = Highcharts.dateFormat('{$timeFormat}', this.x);
                    return date + '<br><span>". $lang['AverageRiskScore'] .": <b>'+ this.y +'</b></span>' + '<br><span>". $lang['RisksOpened'] .": <b>'+ this.points[0].point.opened +'</b></span>' + '<br><span>".$lang['RisksClosed'].": <b>'+ this.points[0].point.closed +'</b></span>';
                }
            }
        })
    ";
    echo "</script>\n";


}

/******************************************
 * FUNCTION: RISK FOR LIKELIHOOD AND IMPACT *
 *****************************************/
function report_likelihood_impact(){
    global $lang;
    global $escaper;

    echo '
        <style type="text/css">
          .highcharts-tooltip>span {
            max-height:100px;
            overflow-y: auto;
            min-width: 100px;
            padding-right: 20px;
          }
        </style>
    ';
    
    $chart = new Highchart();
    $chart->includeExtraScripts();
    $chart->title->text = "";
    $chart->chart->renderTo = "likelihood_impact_chart";
    $chart->chart->type = "scatter";
    $chart->chart->zoomType = "none";
    $chart->credits->enabled = false;

    $chart->xAxis->title = array(
        "text" => "Likelihood"
    );
    $chart->xAxis->tickInterval = 1;
    $chart->xAxis->min = 0;
    $chart->xAxis->max = get_likelihoods_count();
    $chart->xAxis->gridLineWidth = 1;

    $chart->yAxis->title = array(
        "text" => "Impact"
    );
    $chart->yAxis->tickInterval = 1;
    $chart->yAxis->min = 0;
    $chart->yAxis->max = get_impacts_count();
    $chart->legend->enabled = false;
    $chart->plotOptions = array(
        "scatter" => array(
            "marker" => array(
                "radius" => 5,
                "states" => array(
                    "hover" => array(
                        'enabled'=> true,
                        'lineColor'=> 'rgb(100, 100, 100)',
                    )
                ),
            ),
        ),
        "area" => array(
            "fillOpacity" => 1
        )
    );

    // Get classic risks
    $risks = get_risks(10);

    $data = array();
    $point_groups = [];
    
    // Make group for each points
    foreach($risks as $risk){
        $calculate_risk = $risk['calculated_risk'];
        
        if($calculate_risk == 10)
        {
            $x = get_likelihoods_count();
            $y = get_impacts_count();
        }
        else
        {
            $x = $risk['CLASSIC_likelihood'];
            $y = $risk['CLASSIC_impact'];
        }
        $risk_id = (int)$risk["id"] + 1000;
        if(isset($point_groups[$x."_".$y]))
        {
            $point_groups[$x."_".$y]["risk_ids"][] = $risk_id;
        }
        else
        {
            $point_groups[$x."_".$y] = array(
                "x" => $x,
                "y" => $y,
                "risk_ids" => array($risk_id)
            );
        }
    }
    
    // Make chart data from point groups
    foreach($point_groups as $point_group)
    {
        $data[] = array(
            'x'             => intval($point_group['x']),
            'y'             => intval($point_group['y']),
            'risk_ids'      => implode(",", $point_group['risk_ids']),
            'marker'    => array(
                'fillColor' => 'rgb(223, 83, 83)'
            ),
            'color'     => '<div style="width:100%; height:20px; border: solid 1px;border-color: #3f3f3f;"></div>'
        );
    }
    

    $series = [];
    
    for($likelihood=1; $likelihood<=get_likelihoods_count(); $likelihood++)
    {
        for($impact=1; $impact<=get_impacts_count(); $impact++)
        {
            $series[] = get_area_series_from_likelihood_impact($likelihood, $impact);
        }
    }
    
    $series[] = array(
        'type' => "scatter",
        'color' => "rgb(223, 83, 83)",
        'data' => $data,
        'enableMouseTracking' => true,
        'states' => [
            'hover' => [
                'enabled' => false
            ]
        ],
        'stickyTracking' => false,
    );

    $chart->series = $series;
    $chart->printScripts();
    echo "<div id=\"likelihood_impact_chart\" style=\"margin:auto;width:700px;height:700px \"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("likelihood_impact_chart");

    echo "
        likelihood_impact_chart.update({
            title: {
                text: '".$escaper->escapeHtml($lang['LikelihoodImpact'])."'
            },
            tooltip: {
                headerFormat: '',
                useHTML: true,
                style: {pointerEvents: 'auto'},
                hideDelay : 2500,
                formatter: function(){
                    var point = this.point;
                    var test = get_tooltip_html(point);
                    return test;
                }
            }
        })
    ";
    
    echo '
        function get_tooltip_html(point)
        {   
            
            var test = $.ajax({
                type: "POST",
                url: BASE_URL + "/api/likelihood_impact_chart/tooltip",
                async:false,
                data:{
                    "risk_ids": point.risk_ids,
                },
                success: function(response){
                     return response.data;
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this)){
                    }
                }
            });
            return test.responseJSON.data;
        };';

    echo "</script>\n";

}

/*************************************************************
 * FUNCTION: GET AREA DATA FROM LIKELIHOOD AND IMPACT VALUES *
 *************************************************************/
function get_area_series_from_likelihood_impact($likelihood, $impact)
{
    $likelihood = (int)$likelihood;
    $impact = (int)$impact;
    
    $risk_score = calculate_risk($impact, $likelihood);
    
    $data = array(
        [
            "x" => $likelihood-1,
            "y" => $impact-1,
            "risk" => calculate_risk($impact-1, $likelihood-1),
        ],
        [
            "x" => $likelihood-1,
            "y" => $impact,
            "risk" => calculate_risk($impact, $likelihood-1),
        ],
        [
            "x" => $likelihood,
            "y" => $impact,
            "risk" => calculate_risk($impact, $likelihood),
        ],
        [
            "x" => $likelihood,
            "y" => $impact-1,
            "risk" => calculate_risk($impact-1, $likelihood),
        ],
        [
            "x" => $likelihood-1,
            "y" => $impact-1,
        ],
    );
    $color = get_risk_color($risk_score);
    
    $area_series = array(
        'type'=> 'area',
        'color' => hex2rgba(convert_color_code($color)), 
        'data' => $data,
        'enableMouseTracking' => false,
        'states' => [
            'hover' => [
                'enabled' => false
            ]
        ],
        'stickyTracking' => false,
    );
    
    return $area_series;
}

/************************************
 * FUNCTION: GET DATA OF RISK LEVEL *
 ************************************/
function get_data_risk_level( $risk_level , $y , $x , $f){
    $data = array();
    $temp = array();
    
    $data[] = [
                'x' => 0,
                'y' => 0,
                'risk' => 0,
            ];

    for ($i=1; $i <= $x; $i++) { 
       
        for ($j=1; $j <= $y; $j++) {
            if ($f == 0) {
                if ( calculate_risk($j,$i) <= $risk_level ) {
                
                    $temp[] = [
                        'x' => $i,
                        'y' => $j,
                        'risk' => calculate_risk($j,$i)
                    ];

                }
            } else if ( $f == 1 ) {
                if ( calculate_risk($j,$i) < $risk_level ) {
                
                    $temp[] = [
                        'x' => $i,
                        'y' => $j,
                        'risk' => calculate_risk($j,$i)
                    ];

                }
            }
            
        }
    }

    $temp2 = [
        'x' => 0,
        'y' => 0,
    ];

    for ($i=1; $i <= $x; $i++) {

        $temp1 = [
            'x' => 0,
            'y' => 0,
            'risk' => 0
        ];

        for ($j=0; $j < sizeof($temp); $j++) { 
            if ( $temp[$j]['x'] == $i) {

                if ($temp[$j]['risk'] >= $temp1['risk'] ) {
                    $temp1['x'] = $temp[$j]['x'];
                    $temp1['y'] = $temp[$j]['y'];
                    $temp1['risk'] = $temp[$j]['risk'];
                }
            }
        }

        if ($temp1['y'] != $temp2['y']) {
            $data[] = [
                'x' => $temp2['x'],
                'y' => $temp1['y']
            ];
        }

        $data[] = $temp1;

        $temp2['x'] = $temp1['x'];
        $temp2['y'] = $temp1['y'];
        $temp2['risk'] = $temp1['risk'];
    }

    return $data;
}

/********************************************************
* FUNCTION: VIEW PRINT RISK SCORE FORMS IN PENDING RISK *
*********************************************************/
function display_score_html_from_pending_risk($scoring_method="5", $custom=false, $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamagePotential="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkillLevel="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPEaseOfDiscovery="10", $OWASPEaseOfExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $ContributingLikelihood="", $ContributingImpacts=[]){
    global $escaper;
    global $lang;

    if($custom === false){
        $custom = get_setting("default_risk_score");
    }

    if(!$scoring_method)
        $scoring_method = 5;

    $html = "
        <tbody class='risk-scoring-container'>
            <tr>
                <td style=\"white-space: nowrap;\">". $escaper->escapeHtml($lang['RiskScoringMethod']) .": &nbsp;</td>
                <td >
                    ".create_dropdown("scoring_methods", $scoring_method, "scoring_method[]", false, false, true)."
                </td>
            </tr>
            <tr id='classic' class='classic-holder' style='display:". ($scoring_method == 1 ? "table-row" : "none") ."'>
                <td style=\"white-space: nowrap;\">". $escaper->escapeHtml($lang['CurrentLikelihood']) .":</td>
                <td >". create_dropdown('likelihood', $CLASSIC_likelihood, 'likelihood[]', true, false, true) ."</td>
            </tr>
            <tr class='classic-holder' style='display:". ($scoring_method == 1 ? "table-row" : "none") ."'>
                <td style=\"white-space: nowrap;\">". $escaper->escapeHtml($lang['CurrentImpact']) .":</td>
                <td >". create_dropdown('impact', $CLASSIC_impact, 'impact[]', true, false, true) ."</td>
            </tr>
            <tr id='cvss' style='display: ". ($scoring_method == 2 ? "table-row" : "none") .";' class='cvss-holder'>
                <td >&nbsp;</td>
                <td>
                    <p><input type='button' name='cvssSubmit' id='cvssSubmit' value='Score Using CVSS' /></p>
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
                </td>
            </tr>
            <tr id='dread' style='display: ". ($scoring_method == 3 ? "table-row" : "none") .";' class='dread-holder'>
                <td >&nbsp;</td>
                <td>
                    <p><input type='button' name='dreadSubmit' id='dreadSubmit' value='Score Using DREAD' onclick='javascript: popupdread();' /></p>
                    <input type='hidden' name='DREADDamage[]' id='DREADDamage' value='{$DREADDamagePotential}' />
                    <input type='hidden' name='DREADReproducibility[]' id='DREADReproducibility' value='{$DREADReproducibility}' />
                    <input type='hidden' name='DREADExploitability[]' id='DREADExploitability' value='{$DREADExploitability}' />
                    <input type='hidden' name='DREADAffectedUsers[]' id='DREADAffectedUsers' value='{$DREADAffectedUsers}' />
                    <input type='hidden' name='DREADDiscoverability[]' id='DREADDiscoverability' value='{$DREADDiscoverability}' />
                </td>
            </tr>
            <tr id='owasp' style='display: ". ($scoring_method == 4 ? "table-row" : "none") .";' class='owasp-holder'>
                <td >&nbsp;</td>
                <td>
                    <p><input type='button' name='owaspSubmit' id='owaspSubmit' value='Score Using OWASP'  /></p>
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
                </td>
            </tr>
            <tr id='custom' style='display: ". ($scoring_method == 5 ? "table-row" : "none") .";' class='custom-holder'>
                <td >
                    ". $escaper->escapeHtml($lang['CustomValue']) .":
                </td>
                <td >
                    <input type='number' min='0' step='0.1' max='10' name='Custom[]' id='Custom' value='{$custom}' />
                    <small>(Must be a numeric value between 0 and 10)</small>
                </td>
            </tr>
            <tr id='contributing-risk' style='display: ". ($scoring_method == 6 ? "table-row" : "none") .";' class='contributing-risk-holder'>
                <td >&nbsp;</td>
                <td>
                    <p><input type='button' name='contributingRiskSubmit' id='contributingRiskSubmit' value='". $escaper->escapeHtml($lang["ScoreUsingContributingRisk"]) ."' /></p>

                    <input type='hidden' name='ContributingLikelihood[]' id='contributing_likelihood' value='".($ContributingLikelihood ? $ContributingLikelihood : count(get_table("likelihood")))."' />";

                    $max_impact_value = count(get_table("impact"));
                    $contributing_risks = get_contributing_risks();
                    foreach($contributing_risks as $contributing_risk){
                        $html .= "<input type='hidden' class='contributing-impact' name='ContributingImpacts[{$contributing_risk['id']}][]' id='contributing_impact_". $escaper->escapeHtml($contributing_risk['id']) ."' value='". $escaper->escapeHtml(empty($ContributingImpacts[ $contributing_risk['id'] ]) ? $max_impact_value : $ContributingImpacts[ $contributing_risk['id'] ]) ."' />";
                    }
                    
            $html .= "
                </td>
            </tr>
        </tbody>
    ";

    echo $html;
}

/***************************************************
* FUNCTION: DISPLAY SET DEFAULT DATE FORMAT SCRIPT *
****************************************************/
function display_set_default_date_format_script()
{
    global $lang, $escaper;
    echo "
        <script type=\"\">
           \$.datepicker.setDefaults({dateFormat: '".$escaper->escapeHtml(get_default_date_format_for_js())."'});
           var default_date_format = '".$escaper->escapeHtml(get_default_date_format_for_js())."';
        </script>
    ";
}

/*******************************
 * FUNCTION: CREATE RISK TABLE *
 *******************************/
function create_risk_formula_table()
{
    global $lang;
    global $escaper;

    $impacts = arraY_reverse(get_table("impact"));
    $likelihoods = get_table("likelihood");

    $risk_levels = get_risk_levels();
    $risk_levels_by_color = array();
    foreach($risk_levels as $risk_level){
        $risk_levels_by_color[$risk_level['name']] = $risk_level;
    }

    $risk_model = get_setting("risk_model");
    echo "<h4>".$escaper->escapeHtml($lang['MyClassicRiskFormulaIs']).":</h4>";
    echo "<form name=\"risk_levels\" method=\"post\" action=\"\">";
    echo "<p>". $escaper->escapeHtml($lang['RISK']) ." = "; create_dropdown("risk_models", $risk_model, null, false); echo "</p>";
    echo "<input type=\"submit\" value=\"". $escaper->escapeHtml($lang['Update']) ."\" name=\"update_risk_formula\" />";
    echo "</form>";
    echo "<br>";
    
    // Create legend table
    echo "<table>\n";
    echo "<tr height=\"20px\">\n";
    echo "<td><div class=\"risk-table-veryhigh\" style=\"background-color: {$risk_levels_by_color['Very High']['color']}\" /></td>\n";
    echo "<td>". $escaper->escapeHtml($risk_levels_by_color['Very High']['display_name']. " ". $lang['Risk']) ."</td>\n";
    echo "<td>&nbsp;</td>\n";
    echo "<td><div class=\"risk-table-high\" style=\"background-color: {$risk_levels_by_color['High']['color']}\" /></td>\n";
    echo "<td>". $escaper->escapeHtml($risk_levels_by_color['High']['display_name']. " ". $lang['Risk']) ."</td>\n";
    echo "<td>&nbsp;</td>\n";
    echo "<td><div class=\"risk-table-medium\" style=\"background-color: {$risk_levels_by_color['Medium']['color']}\" /></td>\n";
    echo "<td>". $escaper->escapeHtml($risk_levels_by_color['Medium']['display_name']. " ". $lang['Risk']) ."</td>\n";
    echo "<td>&nbsp;</td>\n";
    echo "<td><div class=\"risk-table-low\" style=\"background-color: {$risk_levels_by_color['Low']['color']}\" /></td>\n";
    echo "<td>". $escaper->escapeHtml($risk_levels_by_color['Low']['display_name']. " ". $lang['Risk']) ."</td>\n";
    echo "<td>&nbsp;</td>\n";
    echo "<td><div class=\"risk-table-insignificant\" style=\"background-color: white\" /></td>\n";
    echo "<td>". $escaper->escapeHtml($lang['Insignificant']) ."</td>\n";
    echo "</tr>\n";
    echo "</table>\n";

    echo "<br />\n";

    echo "
        <div>
            <a id='add-impact' href='#'><img title='".$escaper->escapeHtml($lang['AddImpact'])."' width='25px' src='".$_SESSION['base_url']."/images/plus.png'></a>&nbsp;&nbsp;&nbsp;&nbsp;
            <a id='delete-impact' href='#'><img title='".$escaper->escapeHtml($lang['DeleteImpact'])."' width='25px' src='".$_SESSION['base_url']."/images/minus.png'></a>
        </div>
        ";

    echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"10\" style=\"display: block; overflow-x: auto; white-space: nowrap;\">\n";
        echo "<tr>\n";
            echo "<td>&nbsp;</td>";
            echo "<td align=\"center\">
                </td>";
            echo "<td colspan=\"".count($likelihoods)."\"></td>";
        echo "</tr>\n";

    // For each impact level
    foreach($impacts as $i => $impact)
    {

        echo "<tr>\n";

        // If this is the first row add the y-axis label
        if ($i == 0)
        {
            echo "<td rowspan=\"".count($impacts)."\"><div id=\"impact-label\"><b>". $escaper->escapeHtml($lang['Impact']) ."</b></div></td>\n";
        }
        $impact_name = $impacts[$i]['name'] ? $escaper->escapeHtml($impacts[$i]['name']) : "--";
        $impact_value = $escaper->escapeHtml($impacts[$i]['value']);
        // Add the y-axis values
        echo "
            <td bgcolor=\"silver\" height=\"50px\" width=\"200px\">
                <span>
                    <span class='editable'>$impact_name</span>
                    <input type='text' class='editable' value='$impact_name' style='display: none;' data-type='impact' data-id='$impact_value'>
                </span>                    
            </td>\n";
        echo "<td bgcolor=\"silver\" align=\"center\" height=\"50px\" width=\"50px\">" . $escaper->escapeHtml($impacts[$i]['value']) . "</td>\n";

        // For each likelihood level
        foreach($likelihoods as $j => $likelihood)
        {
            // Calculate risk
            $risk = calculate_risk($impact['value'], $likelihood['value']);

            // Get the risk color
            $color = get_risk_color($risk);
            $value = $escaper->escapeHtml($risk);

            echo "
                <td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" height=\"50px\" width=\"150px\">
                    <span>";

            if ($risk_model == 6) {
                echo "
                        <span class='editable'>$value</span>
                        <input type='text' class='editable' value='$value' style='display: none;' data-type='score' data-impact='{$impact['value']}' data-likelihood='{$likelihood['value']}'>";
            } else {
                echo $value;
            }

            echo "
                    </span>
                </td>\n";
        }
        echo "<td>&nbsp;</td>";

        echo "</tr>\n";
    }

    echo "<tr>\n";
    echo "<td>&nbsp;</td>\n";
    echo "<td>&nbsp;</td>\n";
    echo "<td>&nbsp;</td>\n";

    // Add the x-axis values
    foreach(range(1, count($likelihoods)) as $likelihood_value) {
        echo "
            <td align=\"center\" bgcolor=\"silver\">
                $likelihood_value
            </td>\n";
    }

    echo "</tr>\n";

    echo "<tr>\n";
    echo "<td>&nbsp;</td>\n";
    echo "<td>&nbsp;</td>\n";
    echo "<td>&nbsp;</td>\n";

    // Add the x-axis names
    foreach($likelihoods as $likelihood) {
        $likelihood_name = $likelihood['name'] ? $escaper->escapeHtml($likelihood['name']) : "--";
        $likelihood_value = $escaper->escapeHtml($likelihood['value']);

        echo "
            <td align=\"center\" bgcolor=\"silver\" height=\"50px\" width=\"100px\">
                <span>
                    <span class='editable'>$likelihood_name</span>
                    <input type='text' class='editable' value='$likelihood_name' style='display: none;' data-type='likelihood' data-id='$likelihood_value'>
                </span> 
            </td>\n";
    }

    echo "</tr>\n";

    echo "<tr>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<td colspan=\"".count($likelihoods)."\" align=\"center\"><b>". $escaper->escapeHtml($lang['Likelihood']) ."</b></td>\n";
        echo "<td align=\"center\"></td>";
    echo "</tr>\n";
    echo "</table>\n";

    echo "
        <div style=\"float:right;\">
            <a id='add-likelihood' href='#'><img title='".$escaper->escapeHtml($lang['AddLikelihood'])."' width='25px' src='".$_SESSION['base_url']."/images/plus.png'></a>&nbsp;&nbsp;&nbsp;&nbsp;
            <a id='delete-likelihood' href='#'><img title='".$escaper->escapeHtml($lang['DeleteLikelihood'])."' width='25px' src='".$_SESSION['base_url']."/images/minus.png'></a>
        </div>
        ";

    echo
    "
        <script>
            $(document).ready(function(){

                // To save the original value
                $('#classic-risk-formula input.editable').on('focusin', function(){
                    $(this).data('original-value', $(this).val());
                });

                $('#classic-risk-formula input.editable').change(function(){

                    var container = $(this).parent();
                    var type = $(this).data('type');
                    var original_value = $(this).data('original-value');

                    switch(type) {
                        case 'impact':
                        case 'likelihood':
                            url = BASE_URL + '/api/riskformula/update_impact_or_likelihood_name';
                            var value = $(this).data('id');
                            var name = $(this).val();
                            var data = {
                                value: value,
                                name: name,
                                type: type
                            };
                        break;
                        case 'score':
                            var url = BASE_URL + '/api/riskformula/update_custom_score';
                            var impact = $(this).data('impact');
                            var likelihood = $(this).data('likelihood');
                            var score = $(this).val();
                            var data = {
                                impact: impact,
                                likelihood: likelihood,
                                score: score
                            };
                        break;
                    }

                    $.ajax({
                        type: 'POST',
                        url: url,
                        data: data,
                        success: function(response){
                            container.find('span.editable').text(response.data.confirmed_data ? response.data.confirmed_data : '--');
                            if (response.data.color) {
                                container.closest('td').css('background-color', response.data.color);
                            }
                            showAlertsFromArray(response.status_message);
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                container.find('span.editable').text(original_value);
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                })
                
                $('#add-impact').click(function(e){
                    e.preventDefault();
                    $.ajax({
                        type: \"POST\",
                        url: BASE_URL + \"/api/riskformula/add_impact\",
                        success: function(data){
                            document.location.reload();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                })
                $('#delete-impact').click(function(e){
                    e.preventDefault();
                    $.ajax({
                        type: \"POST\",
                        url: BASE_URL + \"/api/riskformula/delete_impact\",
                        success: function(data){
                            document.location.reload();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                })
                $('#add-likelihood').click(function(e){
                    e.preventDefault();
                    $.ajax({
                        type: \"POST\",
                        url: BASE_URL + \"/api/riskformula/add_likelihood\",
                        success: function(data){
                            document.location.reload();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                })
                $('#delete-likelihood').click(function(e){
                    e.preventDefault();
                    $.ajax({
                        type: \"POST\",
                        url: BASE_URL + \"/api/riskformula/delete_likelihood\",
                        success: function(data){
                            document.location.reload();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                })
            })
        </script>
    ";
}

/**********************************************
* FUNCTION: VIEW RISKS AND CONTROLS SELECTIONS *
**********************************************/
function view_risks_and_controls_selections($report, $sort_by=0, $projects)
{
    global $lang;
    global $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span12\">\n";
    echo "<a href=\"javascript:;\" onclick=\"javascript: closeSearchBox()\"><img src=\"../images/X-100.png\" width=\"10\" height=\"10\" align=\"right\" /></a>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span3\">";
    echo $escaper->escapeHtml($lang['Report']) . ":&nbsp;\n";
    echo "<select id=\"report\" name=\"report\" onchange=\"javascript: submit()\">\n";
    echo "<option value=\"0\"" . ($report == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['RisksByControl']) . "</option>\n";
    echo "<option value=\"1\"" . ($report == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ControlsByRisk']) . "</option>\n";
    echo "</select>\n";
    echo "</div>\n";
    echo "<div class=\"span3\">";
    echo $escaper->escapeHtml($lang['Project']) . ":&nbsp;\n";
    create_multiple_dropdown("projects", $projects, NULL, NULL, true, $lang['Unassigned'], "-1");
    echo "</div>\n";
    if($report == 0){
        echo "<div class=\"span3\">";
        echo $escaper->escapeHtml($lang['SortBy']).": ";
        echo "<select id=\"sortby\" name=\"sort_by\" onchange=\"javascript: submit()\">\n";
        echo "<option value=\"0\"" . ($sort_by == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ControlName']) . "</option>\n";
        echo "<option value=\"1\"" . ($sort_by == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ControlRisk']) . "</option>\n";
        echo "</select>\n";
        echo "</div>\n";
    }
    echo "</div>\n";

    echo "<script>
            $('#projects').multiselect({
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                enableFiltering: true,
                maxHeight: 250,
                buttonWidth: 300,
                includeSelectAllOption: true,
                enableCaseInsensitiveFiltering: true,
                onDropdownHide: function(){
                    $('form[name=select_report]').submit();
                }
            });
        </script>";

}

/********************************************
* FUNCTION: VIEW CONTROLS FILTER SELECTIONS *
*********************************************/
function view_controls_filter_selections()
{
    global $lang;
    global $escaper;
    if(count($_POST) > 3) {
        $control_framework = isset($_POST['control_framework']) ? $_POST['control_framework'] : [];
        $control_family = isset($_POST['control_family']) ? $_POST['control_family'] : [];
        $control_class = isset($_POST['control_class']) ? $_POST['control_class'] : [];
        $control_phase = isset($_POST['control_phase']) ? $_POST['control_phase'] : [];
        $control_priority = isset($_POST['control_priority']) ? $_POST['control_priority'] : [];
        $control_owner = isset($_POST['control_owner']) ? $_POST['control_owner'] : [];
    } else {
        $control_framework = "all";
        $control_family = "all";
        $control_class = "all";
        $control_phase = "all";
        $control_priority = "all";
        $control_owner = "all";
    }


    echo "<div class=\"row-fluid\">";
    echo "<div class=\"span4\">";
    echo $escaper->escapeHtml($lang['ControlFrameworks']).":";
    create_multiple_dropdown("control_framework", $control_framework, null, getAvailableControlFrameworkList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "</div>";
    echo "<div class=\"span4\">";
    echo $escaper->escapeHtml($lang['ControlFamily']).":";
    create_multiple_dropdown("control_family", $control_family, null, getAvailableControlFamilyList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "</div>";
    echo "<div class=\"span4\">";
    echo $escaper->escapeHtml($lang['ControlClass']).":";
    create_multiple_dropdown("control_class", $control_class, null, getAvailableControlClassList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "</div>";
    echo "</div>";
    echo "<div class=\"row-fluid\">";
    echo "<div class=\"span4\">";
    echo $escaper->escapeHtml($lang['ControlPhase']).":";
    create_multiple_dropdown("control_phase", $control_phase, null, getAvailableControlPhaseList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "</div>";
    echo "<div class=\"span4\">";
    echo $escaper->escapeHtml($lang['ControlPriority']).":";
    create_multiple_dropdown("control_priority", $control_priority, null, getAvailableControlPriorityList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "</div>";
    echo "<div class=\"span4\">";
    echo $escaper->escapeHtml($lang['ControlOwner']).":";
    create_multiple_dropdown("control_owner", $control_owner, null, getAvailableControlOwnerList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "</div>";
    echo "</div>";
    echo "
        <script>
            $(document).ready( function(){
                $('select[multiple]').multiselect({
                    allSelectedText: '". $escaper->escapeHtml($lang['ALL'])."',
                    enableFiltering: true,
                    maxHeight: 250,
                    buttonWidth: '80%',
                    includeSelectAllOption: true,
                    enableCaseInsensitiveFiltering: true,
                    onDropdownHide: function(){
                        $('form[name=select_report]').submit();
                    }
                });
            });
        </script>
    ";

}

/**********************************************
* FUNCTION: DISPLAY CONTRIBUTING RISK FORMULA *
**********************************************/
function display_contributing_risk_formula()
{
    global $lang, $escaper;
    echo "
        <table id='template-for-adding' style='display: none'>
            <tr>
                <td align='center'></td>
                <td align='center'><input type='text' class='new-name' value=''></td>
                <td align='center'><a class='delete-row'><i class='fa fa-trash'></i></a></td>
                <td></td>
            </tr>
        </table>
        <table id='template-for-impact-adding' style='display: none'>
            <tr>
                <td align='center'><input type='text' class='subject' required name='subject[]' style='max-width: none'></td>
                <td align='center'><input type='number' class='weight' required step='0.01' name='weight[]' max='1' min='0'></td>
                <td align='center'><a class='delete-row' href=''><img src='../images/minus.png' width='15px' height='15px'></a></td>
            </tr>
        </table>

    ";
 
    echo "
        <div class='well'>
            <div class='row-fluid'>
                <div class='span6'><h4>".$escaper->escapeHtml($lang["Likelihood"])."</h4></div>
                <div class='span6 text-right'><button id='likelihood-add-btn'><i class='fa fa-plus'></i></button></div>
            </div>

            <table width='100%' id='contributing-risks-likelihood-table'>
                <thead>
                    <tr>
                        <th width='10%'>".$escaper->escapeHtml($lang["Value"])."</th>
                        <th width='40%'>".$escaper->escapeHtml($lang["Name"])."</th>
                        <th width='20%'>&nbsp;</td>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>";
                $table_list = display_contributing_risks_likelihood_table_list();
                echo $table_list;
                echo "</tbody>
            </table>
        </div>
    ";
    echo "
        <div class='well'>
            <div class='row-fluid'>
                <div class='span6'><h4>".$escaper->escapeHtml($lang["Impact"])."</h4></div>
                <div class='span6 text-right'><button id='add-impact-row'>".$escaper->escapeHtml($lang["AddImpact"])."</button></div>
            </div>
            <form class=\"contributing_risk_form\" method=\"post\" action=\"\">\n
            <table width='100%' id='contributing-risk-table'>
                <thead>
                    <tr>
                        <th width='50%'>".$escaper->escapeHtml($lang["Subject"])."</th>
                        <th width='30%'>".$escaper->escapeHtml($lang["ContributionWeight"])."</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>";
                $contributing_risks = get_contributing_risks();
                foreach($contributing_risks as $key => $contributing_risk){
                    echo "
                        <tr>
                            <td align='center'><input type='text' class='subject' required name='existing_subject[".$contributing_risk["id"]."]' style='max-width: none' value='". $escaper->escapeHtml($contributing_risk['subject']) ."'></td>
                            <td align='center'><input type='number' class='weight' required step='0.01' name='existing_weight[".$contributing_risk["id"]."]' value='". $escaper->escapeHtml($contributing_risk['weight']) ."' max='1' min='0'></td>
                            <td align='center' ". ($key==0 ? ("style='display: none'") : "") ."><a class='delete-row' href=''><img src='../images/minus.png' width='15px' height='15px'></a></td>
                        </tr>
                    ";
                }
                
                echo "</tbody>
                <tfoot>
                    <tr>
                        <td colspan='3' align='right'><button type='submit' name='save_contributing_risk'>".$escaper->escapeHtml($lang["Save"])."</button></td>
                    </tr>
                </tfoot>
            </table>
            </form>
            <hr><br>
            <table width='100%' id='contributing-risks-impact-table'>
                <thead>
                    <tr>
                        <th width='10%'>".$escaper->escapeHtml($lang["Value"])."</th>
                        <th width='40%'>".$escaper->escapeHtml($lang["Name"])."</th>
                        <th width='20%'>".$escaper->escapeHtml($lang["Name"])."</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>";
                $table_list = display_contributing_risks_impact_table_list();
                echo $table_list;
                echo "</tbody>
            </table>
        </div>
    ";
    
    echo "
        <script>
            $('#likelihood-add-btn').click(function(e){
                e.preventDefault();
                $('#contributing-risks-likelihood-table tbody').prepend($('#template-for-adding tbody').html());
            })
            $('#contributing-risk-formula').on('click', '.impact-add-btn', function(e){
                e.preventDefault();
                $(this).parents('.subject-row').after($('#template-for-adding tbody').html());
            })
            $('#contributing-risk-formula').on('click', '#add-impact-row', function(e){
                e.preventDefault();
                $('#contributing-risk-table tbody').append($('#template-for-impact-adding tbody').html());
            })
            $('body').on('click', '.delete-row', function(e){
                e.preventDefault();
                $(this).closest('tr').remove()
            });
            $('#contributing-risks-likelihood-table, #contributing-risks-impact-table').on('change','.new-name', function(){
                var name = $(this).val();
                var table_id = $(this).parents('table').attr('id');
                if(table_id == 'contributing-risks-likelihood-table') {
                    var table = 'likelihood';
                    var data = {
                        table: table,
                        name: name
                    };
                } else {
                    var table = 'impact';
                    var contributing_risks_id = $(this).closest('tr').prev().data('contributing_risks_id');
                    var data = {
                        table: table,
                        name: name,
                        contributing_risks_id : contributing_risks_id
                    };
                }
                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/contributing_risks/add',
                    data: data,
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }
                        redraw_contributing_table_list(table);
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    },
                });
            });
            $('#contributing-risks-likelihood-table, #contributing-risks-impact-table').on('change', 'input.editable', function(){
                var _this = $(this);
                var id = _this.parent().data('id');
                var value = _this.parent().data('value');
                var name = _this.val();
                if(!name) return false;
                var table_id = $(this).parents('table').attr('id');
                if(table_id == 'contributing-risks-likelihood-table') var table = 'likelihood';
                else var table = 'impact';

                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/contributing_risks/update/' + table,
                    data: {
                        id: id,
                        value: value,
                        name: name
                    },
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }
                        _this.blur();
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    },
                });
            });
            $('#contributing-risks-likelihood-table, #contributing-risks-impact-table').on('click', '.delete-value', function(){
                var id = $(this).data('id');
                var value = $(this).data('value');
                var table_id = $(this).parents('table').attr('id');
                if(table_id == 'contributing-risks-likelihood-table') {
                    var table = 'likelihood';
                    var data = {
                        id: id,
                        value: value,
                    };
                } else {
                    var table = 'impact';
                    var contributing_risks_id = $(this).data('contributing_risks_id');
                    var data = {
                        id: id,
                        value: value,
                        contributing_risks_id: contributing_risks_id,
                    };
                }
                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/contributing_risks/delete/' + table,
                    data: data,
                    dataType : 'json',
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }
                        redraw_contributing_table_list(table);
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    },
                });
            });
            function redraw_contributing_table_list(table){
                if(table == 'likelihood') var table_body = 'contributing-risks-likelihood-table';
                else var table_body = 'contributing-risks-impact-table';
                $.ajax({
                    type: 'POST',
                    data: {
                        table: table,
                    },
                    url: BASE_URL + '/api/contributing_risks/table_list',
                    success: function(data){
                        $('#'+table_body+' tbody').html(data);
                        $('input.editable').each(function(){
                            resizable($(this));
                        });
                    },
                });
            }

            $('.contributing_risk_form').submit(function(){
                if(!$(this).find('.weight').length)
                    return false;
                var totalWeight = 0;
                $(this).find('.weight').each(function(){
                    totalWeight += Number($(this).val());
                    totalWeight = (+totalWeight.toFixed(4))
                })
                if(totalWeight != 1){
                    toastr.error(\"" . $escaper->escapeHtml($lang['TotalContributingWeightsShouldBe1']) ."\");
                    
                    return false;
                }
            })
        </script>
    ";

}

/**********************************************
* FUNCTION: DISPLAY CONTRIBUTING RISK FORMULA *
**********************************************/
function display_contributing_risk_from_calculator()
{
    global $lang, $escaper;
    echo "<table width=\"100%\" class=\"contributing-risk-table\"  border=\"0\" cellpadding=\"1\" cellspacing=\"1\" >";
        echo "
            <tr>
                <th>".$escaper->escapeHtml($lang["Subject"])."</th>
                <th>".$escaper->escapeHtml($lang["Weight"])."</th>
                <th>".$escaper->escapeHtml($lang["Impact"])."</th>
            </tr>
        ";
        $contributing_risks = get_contributing_risks();
        foreach($contributing_risks as $contributing_risk){
            $impacts = get_impact_values_from_contributing_risks_id($contributing_risk['id']);
            echo "
                <tr>
                    <td colspan='3' height='5'></td>
                </tr>
                <tr class='contributing_impact_row'>
                    <td>".$escaper->escapeHtml($contributing_risk['subject'])."</td>
                    <td align='center' class='contributing_weight'>".$escaper->escapeHtml($contributing_risk['weight'])."</td>
                    <td align='center' class='contributing_impact'>".create_dropdown("impact", NULL, "contributing_impact_{$contributing_risk['id']}", false, false, true, "", "--", "", true, 0, $impacts)."</td>
                </tr>
            ";
        }
    echo "</table>";
    
}

/*******************************************
* FUNCTION: DISPLAY PLAN MITIGATIONS TABLE *
********************************************/
function display_plan_mitigations()
{
    global $lang, $escaper;
    $user = get_user_by_id($_SESSION['uid']);
    $settings = json_decode($user["custom_plan_mitigation_display_settings"], true);
    $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
    $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
    $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
    $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
    $columns = [];
    foreach($columns_setting as $column){
        if(stripos($column[0], "custom_field_") !== false){
            if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
        } else if($column[1] == 1) $columns[] = $column[0];
    }
    if(!count($columns)){
        $columns = array("id","risk_status","subject","calculated_risk","submission_date","mitigation_planned","management_review");
    }
    $tr = "";
    $index = 0;
    $order_index = 0;
    $order_dir = "asc";

    // If the Customization Extra exists
    $file = realpath(__DIR__ . '/../extras/customization/index.php');
    if (file_exists($file))
    {
        // Load it
        require_once($file);
    }

    foreach($columns as $column){
        if($column == "calculated_risk") {
            $order_index = $index;
            $order_dir = "desc";
        }
        if($column == "subject") {
            $style = "min-width:250px;";
        } else {
            $style = "min-width:100px;";
        }
        if(($pos = stripos($column, "custom_field_")) !== false){
            if(customization_extra()){
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $label = $escaper->escapeHtml($custom_field['name']);
                $tr .= "<th data-name='".$column."' align=\"left\" style=\"".$style."\">".$label."</th>";
                $index++;
            }
        } else {
            $label = get_label_by_risk_field_name($column);
            $tr .= "<th data-name='".$column."' align=\"left\" style=\"".$style."\">".$label."</th>";
            $index++;
        }
    }
    $tableID = "plan-mitigations";
    echo "

        <table id=\"{$tableID}\" width=\"100%\" class=\"risk-datatable table table-bordered table-striped table-condensed\">
            <thead >
                <tr>{$tr}</tr> 
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            $('#{$tableID} thead tr').clone(true).appendTo( '#{$tableID} thead');
            $('#{$tableID} thead tr:eq(1) th').each( function (i) {
                var title = $(this).text();
                var data_name = $(this).attr('data-name');
                if(data_name == 'mitigation_planned') {
                    $(this).html( '<select name=\"mitigation_planned\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option></select>' );
                } else if(data_name == 'management_review') {
                    $(this).html( '<select name=\"management_review\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option><option value=\"".$escaper->escapeHtml($lang['PASTDUE'])."\">".$escaper->escapeHtml($lang['PASTDUE'])."</option></select>' );
                } else {
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                }

                $( 'input, select', this ).on( 'keyup change', function () {
                    if ( datatableInstance.column(i).search() !== this.value ) {
                        datatableInstance.column(i).search( this.value ).draw();
                    }
                });
            });
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: true,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                orderCellsTop: true,
                pagingType: \"full_numbers\",
                pageLength: pageLength,
                dom : \"lrti<'#view-all.view-all'>p\",
                createdRow: function(row, data, index){
                    var background = $('.background-class', $(row)).data('background');
                    $(row).find('td').addClass(background)
                },
                scrollX: true,
                order: [[{$order_index}, '{$order_dir}']],
                ajax: {
                    url: BASE_URL + '/api/risk_management/plan_mitigation',
                    type: 'post',
                    data: function(d){
                    },
                    complete: function(response){
                    }
                },
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
            $(document).ready(function(){
                $('.sortable-risk').sortable({
                    connectWith: '.sortable-risk'
                });
                $('.sortable-mitigation').sortable({
                    connectWith: '.sortable-mitigation'
                });
                $('.sortable-review').sortable({
                    connectWith: '.sortable-review'
                });
                $('#save_display_settings').click(function(){
                    var risk_checkboxes = $('.sortable-risk .hidden-checkbox');
                    var riskColumns = [];
                    risk_checkboxes.each(function(){
                        var check_val = $(this).is(':checked')?1:0;
                        riskColumns.push([$(this).attr('name'),check_val]);
                    });
                    var mitigation_checkboxes = $('.sortable-mitigation .hidden-checkbox');
                    var mitigationColumns = [];
                    mitigation_checkboxes.each(function(){
                        var check_val = $(this).is(':checked')?1:0;
                        mitigationColumns.push([$(this).attr('name'),check_val]);
                    });
                    var review_checkboxes = $('.sortable-review .hidden-checkbox');
                    var reviewColumns = [];
                    review_checkboxes.each(function(){
                        var check_val = $(this).is(':checked')?1:0;
                        reviewColumns.push([$(this).attr('name'),check_val]);
                    });
                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/risk_management/save_custom_plan_mitigation_display_settings',
                        data:{
                            risk_columns: riskColumns,
                            mitigation_columns: mitigationColumns,
                            review_columns: reviewColumns,
                        },
                        success: function(res){
                            $('#setting_modal').modal('hide');
                            showAlertsFromArray(res.status_message);
                            document.location.reload();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });
            });
            
        </script>
    ";
}

/********************************************
* FUNCTION: DISPLAY MANAGEMENT REVIEW TABLE *
*********************************************/
function display_management_review()
{
    global $lang, $escaper;
    $user = get_user_by_id($_SESSION['uid']);
    $settings = json_decode($user["custom_perform_reviews_display_settings"], true);
    $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
    $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
    $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
    $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
    $columns = [];
    foreach($columns_setting as $column){
        if(stripos($column[0], "custom_field_") !== false){
            if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
        } else if($column[1] == 1) $columns[] = $column[0];
    }
    if(!count($columns)){
        $columns = array("id","risk_status","subject","calculated_risk","submission_date","mitigation_planned","management_review");
    }
    $tr = "";
    $index = 0;
    $order_index = 0;
    $order_dir = "asc";

    // If the Customization Extra exists
    $file = realpath(__DIR__ . '/../extras/customization/index.php');
    if (file_exists($file))
    {
        // Load it
        require_once($file);
    }

    foreach($columns as $column){
        if($column == "calculated_risk") {
            $order_index = $index;
            $order_dir = "desc";
        }
        if($column == "subject") {
            $style = "min-width:250px;";
        } else {
            $style = "min-width:100px;";
        }
        if(($pos = stripos($column, "custom_field_")) !== false){
            if(customization_extra()){
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $label = $escaper->escapeHtml($custom_field['name']);
                $tr .= "<th data-name='".$column."' align=\"left\" style=\"".$style."\">".$label."</th>";
                $index++;
            }
        } else {
            $label = get_label_by_risk_field_name($column);
            $tr .= "<th data-name='".$column."' align=\"left\" style=\"".$style."\">".$label."</th>";
            $index++;
        }
    }
    $tableID = "management-review";
    echo "

        <table id=\"{$tableID}\" width=\"100%\" class=\"risk-datatable table table-bordered table-striped table-condensed\">
            <thead >
                <tr>{$tr}</tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            $('#{$tableID} thead tr').clone(true).appendTo( '#{$tableID} thead');
            $('#{$tableID} thead tr:eq(1) th').each( function (i) {
                var title = $(this).text();
                var data_name = $(this).attr('data-name');
                if(data_name == 'mitigation_planned') {
                    $(this).html( '<select name=\"mitigation_planned\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option></select>' );
                } else if(data_name == 'management_review') {
                    $(this).html( '<select name=\"management_review\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option><option value=\"".$escaper->escapeHtml($lang['PASTDUE'])."\">".$escaper->escapeHtml($lang['PASTDUE'])."</option></select>' );
                } else {
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                }

                $( 'input, select', this ).on( 'keyup change', function () {
                    if ( datatableInstance.column(i).search() !== this.value ) {
                        datatableInstance.column(i).search( this.value ).draw();
                    }
                });
            });
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: true,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                orderCellsTop: true,
                pagingType: \"full_numbers\",
                pageLength: pageLength,
                dom : \"lrti<'#view-all.view-all'>p\",
                createdRow: function(row, data, index){
                    var background = $('.background-class', $(row)).data('background');
                    $(row).find('td').addClass(background)
                },
                scrollX: true,
                order: [[{$order_index}, '{$order_dir}']],
                ajax: {
                    url: BASE_URL + '/api/risk_management/managment_review',
                    type: 'post',
                    data: function(d){
                    },
                    complete: function(response){
                    }
                },
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
            $(document).ready(function(){
                $('.sortable-risk').sortable({
                    connectWith: '.sortable-risk'
                });
                $('.sortable-mitigation').sortable({
                    connectWith: '.sortable-mitigation'
                });
                $('.sortable-review').sortable({
                    connectWith: '.sortable-review'
                });
                $('#save_display_settings').click(function(){
                    var risk_checkboxes = $('.sortable-risk .hidden-checkbox');
                    var riskColumns = [];
                    risk_checkboxes.each(function(){
                        var check_val = $(this).is(':checked')?1:0;
                        riskColumns.push([$(this).attr('name'),check_val]);
                    });
                    var mitigation_checkboxes = $('.sortable-mitigation .hidden-checkbox');
                    var mitigationColumns = [];
                    mitigation_checkboxes.each(function(){
                        var check_val = $(this).is(':checked')?1:0;
                        mitigationColumns.push([$(this).attr('name'),check_val]);
                    });
                    var review_checkboxes = $('.sortable-review .hidden-checkbox');
                    var reviewColumns = [];
                    review_checkboxes.each(function(){
                        var check_val = $(this).is(':checked')?1:0;
                        reviewColumns.push([$(this).attr('name'),check_val]);
                    });
                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/risk_management/save_custom_perform_reviews_display_settings',
                        data:{
                            risk_columns: riskColumns,
                            mitigation_columns: mitigationColumns,
                            review_columns: reviewColumns,
                        },
                        success: function(res){
                            $('#setting_modal').modal('hide');
                            showAlertsFromArray(res.status_message);
                            document.location.reload();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });
            });
        </script>
    ";
}

/***************************************
* FUNCTION: DISPLAY REVIEW RISKS TABLE *
****************************************/
function display_review_risks()
{
    global $lang, $escaper;
    $user = get_user_by_id($_SESSION['uid']);
    $settings = json_decode($user["custom_reviewregularly_display_settings"], true);
    $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
    $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
    $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
    $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
    $columns = [];
    foreach($columns_setting as $column){
        if(stripos($column[0], "custom_field_") !== false){
            if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
        } else if($column[1] == 1) $columns[] = $column[0];
    }
    if(!count($columns)){
        $columns = array("id","risk_status","subject","calculated_risk","days_open","next_review_date");
    }
    $tr = "";
    $index = 0;
    $order_index = 0;
    $order_dir = "asc";

    // If the Customization Extra exists
    $file = realpath(__DIR__ . '/../extras/customization/index.php');
    if (file_exists($file))
    {
        // Load it
        require_once($file);
    }

    foreach($columns as $column){
        if($column == "next_review_date") {
            $order_index = $index;
            $order_dir = "asc";
        }
        if($column == "subject") {
            $style = "min-width:250px;";
        } else {
            $style = "min-width:100px;";
        }
        if(($pos = stripos($column, "custom_field_")) !== false){
            if(customization_extra()){
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $label = $escaper->escapeHtml($custom_field['name']);
                $tr .= "<th data-name='".$column."' align=\"left\" style=\"".$style."\">".$label."</th>";
                $index++;
            }
        } else {
            $label = get_label_by_risk_field_name($column);
            $tr .= "<th data-name='".$column."' align=\"left\" style=\"".$style."\">".$label."</th>";
            $index++;
        }
    }
    $tableID = "review-risks";
    echo "

        <table id=\"{$tableID}\" width=\"100%\" class=\"risk-datatable table table-bordered table-striped table-condensed\">
            <thead >
                <tr>{$tr}</tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            $('#{$tableID} thead tr').clone(true).appendTo( '#{$tableID} thead');
            $('#{$tableID} thead tr:eq(1) th').each( function (i) {
                var title = $(this).text();
                var data_name = $(this).attr('data-name');
                if(data_name == 'mitigation_planned') {
                    $(this).html( '<select name=\"mitigation_planned\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option></select>' );
                } else if(data_name == 'management_review') {
                    $(this).html( '<select name=\"management_review\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option><option value=\"".$escaper->escapeHtml($lang['PASTDUE'])."\">".$escaper->escapeHtml($lang['PASTDUE'])."</option></select>' );
                } else {
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                }

                $( 'input, select', this ).on( 'keyup change', function () {
                    if ( datatableInstance.column(i).search() !== this.value ) {
                        datatableInstance.column(i).search( this.value ).draw();
                    }
                });
            });
             var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: true,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                orderCellsTop: true,
                pagingType: \"full_numbers\",
                pageLength: pageLength,
                dom : \"lrti<'#view-all.view-all'>p\",
                createdRow: function(row, data, index){
                    var background = $('.background-class', $(row)).data('background');
                    $(row).find('td').addClass(background)
                },
                scrollX: true,
                order: [[{$order_index}, '{$order_dir}']],
                ajax: {
                    url: BASE_URL + '/api/risk_management/review_risks',
                    type: 'post',
                    data: function(d){
                    },
                    complete: function(response){
                    }
                },
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
            $(document).ready(function(){
                $('.sortable-risk').sortable({
                    connectWith: '.sortable-risk'
                });
                $('.sortable-mitigation').sortable({
                    connectWith: '.sortable-mitigation'
                });
                $('.sortable-review').sortable({
                    connectWith: '.sortable-review'
                });
                $('#save_display_settings').click(function(){
                    var risk_checkboxes = $('.sortable-risk .hidden-checkbox');
                    var riskColumns = [];
                    risk_checkboxes.each(function(){
                        var check_val = $(this).is(':checked')?1:0;
                        riskColumns.push([$(this).attr('name'),check_val]);
                    });
                    var mitigation_checkboxes = $('.sortable-mitigation .hidden-checkbox');
                    var mitigationColumns = [];
                    mitigation_checkboxes.each(function(){
                        var check_val = $(this).is(':checked')?1:0;
                        mitigationColumns.push([$(this).attr('name'),check_val]);
                    });
                    var review_checkboxes = $('.sortable-review .hidden-checkbox');
                    var reviewColumns = [];
                    review_checkboxes.each(function(){
                        var check_val = $(this).is(':checked')?1:0;
                        reviewColumns.push([$(this).attr('name'),check_val]);
                    });
                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/risk_management/save_custom_reviewregularly_display_settings',
                        data:{
                            risk_columns: riskColumns,
                            mitigation_columns: mitigationColumns,
                            review_columns: reviewColumns,
                        },
                        success: function(res){
                            $('#setting_modal').modal('hide');
                            showAlertsFromArray(res.status_message);
                            document.location.reload();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });
            });
        </script>
    ";
}

/*****************************************
* FUNCTION: DISPLAY AUDIT TIMELINE TABLE *
******************************************/
function display_audit_timeline()
{
    global $lang, $escaper;
    
    // If User has permission for complicance menu, shows Audit Timeline report
    if(!empty($_SESSION['compliance']))
    {
        $tableID = "audit-timeline";
        
        echo "
            <table id=\"{$tableID}\" width=\"100%\" class=\"risk-datatable table table-bordered table-striped table-condensed\">
                <thead >
                    <tr >
                        <th data-name='actions' align=\"left\" width=\"200px\" valign=\"top\">".$escaper->escapeHtml($lang['Actions'])."</th>
                        <th data-name='test_name' align=\"left\" width=\"200px\" valign=\"top\">".$escaper->escapeHtml($lang['TestName'])."</th>
                        <th data-name='associated_frameworks' align=\"left\" valign=\"top\">".$escaper->escapeHtml($lang['AssociatedFrameworks'])."</th>
                        <th data-name='last_test_date' align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['LastTestDate'])."</th>
                        <th data-name='last_test_result' align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['LastTestResult'])."</th>
                        <th data-name='next_test_date' align=\"center\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['NextTestDate'])."</th>
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
                    bSort: true,
                    pagingType: \"full_numbers\",
                    dom : \"flrtip\",
                    pageLength: pageLength,
                    dom : \"flrti<'#view-all.view-all'>p\",
                    createdRow: function(row, data, index){
                        var background = $('.background-class', $(row)).data('background');
                        $(row).find('td').addClass(background)
                    },
                    order: [[3, 'asc']],
                    ajax: {
                        url: BASE_URL + '/api/compliance/audit_timeline',
                        data: function(d){
                        },
                        complete: function(response){
                        }
                    }
                });

                // Initiate Audit
                datatableInstance.on('draw', function() {
                    $('.btn-initiate-audit').on('click', function() {
                        // alert($(this).attr('id')); return;
                        $.ajax({
                            url: BASE_URL + '/api/compliance/audit_initiation/initiate',
                            type: 'POST',
                            data: {
                                type: 'test',
                                id: $(this).attr('id')
                            },
                            success: function(res) {
                                if (res.status_message) {
                                    showAlertsFromArray(res.status_message);
                                }
                            },
                            error: function(xhr, status, error) {
                                if (!retryCSRF(xhr, this)) {
                                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                                        showAlertsFromArray(xhr.responseJSON.status_message);
                                    }
                                }
                            }
                        });
                    });
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
    }
}


/***************************************
* FUNCTION: DISPLAY REVIEW RISKS TABLE *
****************************************/
function display_review_date_issues()
{
    global $lang, $escaper;

    $tableID = "review-date-issues";

    echo "
        <table id=\"{$tableID}\" width=\"100%\" class=\"risk-datatable table table-bordered table-striped table-condensed\">
            <thead>
                <tr>
                    <th data-name='id' align=\"left\" valign=\"top\">".$escaper->escapeHtml($lang['ID'])."</th>
                    <th data-name='subject' align=\"left\" valign=\"top\">".$escaper->escapeHtml($lang['Subject'])."</th>
                    <th data-name='next_review' align=\"center\" valign=\"top\">".$escaper->escapeHtml($lang['NextReviewDate'])."</th>
                    <th data-name='date_format' align=\"center\" valign=\"top\">".$escaper->escapeHtml($lang['SuspectedDateFormat'])."</th>
                    <th data-name='action' align=\"center\" valign=\"top\"></th>
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
                pagingType: \"full_numbers\",
                dom : \"flrtip\",
                pageLength: pageLength,
                dom : \"flrti<'#view-all.view-all'>p\",
                createdRow: function(row, data, index){
                    var background = $('.background-class', $(row)).data('background');
                    $(row).find('td').addClass(background)
                },
                order: [[0, 'asc']],
                ajax: {
                    url: BASE_URL + '/api/risk_management/review_date_issues',
                    data: function(d){ },
                    complete: function(response){ }
                },
                columnDefs : [
                    {
                        'targets' : [0],
                        'width': '5%'
                    },
                    {
                        'targets' : [-3],
                        'width': '10%'
                    },
                    {
                        'targets' : [-2],
                        'width': '12%'
                    },
                    {
                        'targets' : [-1],
                        'width': '5%'
                    },
                    {
                        'targets' : [-1, -2, -3],
                        'orderable': false,
                    },
                    {
                        'targets': -1,
                        'data': null,
                        'defaultContent': '<button class=\"confirm\" style=\"padding: 2px 15px;\">" . $escaper->escapeHtml($lang['Confirm']) . "</button>'
                    }
                ]
            });

            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');

                if (datatableInstance.page() == 0) {
                    // Reload the page when no more issues left so the page load code can
                    // run the wrap-up logic
                    if (datatableInstance.rows( {page:'current'} ).count() == 0) {
                        setTimeout(function(){window.location=window.location;}, 1);
                    }
                } else {// get to the previous page in case we confirmed the last one from the page and it's not the first page
                    if (datatableInstance.rows( {page:'current'} ).count() == 0) {
                        setTimeout(function(){datatableInstance.page('previous').draw('page');}, 1);
                    }
                }

                $('#{$tableID} tbody').off('click', 'button.confirm');
                $('#{$tableID} tbody').on('click', 'button.confirm', function () {
                    var data = datatableInstance.row($(this).closest('tr')).data();
                    var format = $('#format_' + data[4]).val();
                    if (format == '') {
                        alert('" . $escaper->escapeHtml($lang['YouNeedToSpecifyTheFormatParameter']) . "');
                    } else {
                        $.ajax({
                            type: \"POST\",
                            url: BASE_URL + \"/api/management/risk/fix_review_date_format\",
                            data : {
                                review_id: data[4],
                                format: format
                            },
                            success: function(data){
                                if(data.status_message){
                                    showAlertsFromArray(data.status_message);
                                }
                                datatableInstance.ajax.reload(null, false);
                            },
                            error: function(xhr,status,error){
                                if(!retryCSRF(xhr, this))
                                {
                                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                                        showAlertsFromArray(xhr.responseJSON.status_message);
                                    }
                                }
                            }
                        });
                    }
                });
            });

            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw();
                $(this).addClass(\"current\");
            });

            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw();
                }
            });
        </script>
    ";
}

/**********************************
 * FUNCTION: GET AUDIT TRAIL HTML *
 **********************************/
function get_audit_trail_html($id = NULL, $days = 7, $log_type=NULL)
{
    global $escaper;

    // If the ID is greater than 1000 or NULL
    if ($id > 1000 || $id === NULL)
    {
        $logs = get_audit_trail($id, $days, $log_type);

        foreach ($logs as $log)
        {
            $date = date(get_default_datetime_format("g:i A T"), strtotime($log['timestamp']));

            echo "<p>" . $escaper->escapeHtml($date) . " > " . $escaper->escapeHtml($log['message']) . "</p>\n";
        }

        // Return true
        return true;
    }
    // Otherwise this is not a valid ID
    else
    {
        // Return false
        return false;
    }
}

/*************************************
 * FUNCTION: DISPLAY SIDE NAVIGATION *
 *************************************/
function display_side_navigation($active)
{
	global $escaper;
	global $lang;

	// Display the smaller side navigation
	echo "<div id=\"sidenavsmall\" class=\"sidenavsmall\">\n";

        echo "  <div class=\"navbtn\">\n";
        echo "    <a href=\"javascript:void(0)\" onclick=\"hideUnhideNav()\"><img src=\"../images/menu-icon-png-3-lines-white-horizontal.png\" /></a>\n";
        echo "  </div>\n";

	echo ($active == "GovernanceRiskCompliance" ? "<li class=\"active\">\n" : "<li>\n");
	echo "  <a href=\"../reports/index.php\">GRC</a>\n";
	echo "</li>\n";

        // If the incident management extra is enabled
        if (incident_management_extra())
        {
                // Include the incident management extra
                require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));

                // If the user has incident management permissions
		if (check_permission("im_incidents"))
                {
			echo ($active == "IncidentManagement" ? "<li class=\"active\">\n" : "<li>\n");
        		echo "  <a href=\"../incidents/index.php\">IM</a>\n";
			echo "</li>\n";
		}
	}

	// If the vulnerability management extra is enabled
	if (vulnmgmt_extra())
	{
		// Include the vulnerability management extra
		require_once(realpath(__DIR__ . '/../extras/vulnmgmt/index.php'));

		// If the user has vulnerability management permissions
		if (check_permission("vm_vulnerabilities"))
		{
			echo ($active == "VulnerabilityManagement" ? "<li class=\"active\">\n" : "<li>\n");
			echo "  <a href=\"../vulnerabilities/index.php\">VM</a>\n";
                        echo "</li>\n";
                }
        }

        echo "</div>\n";

	// Display the larger side navigation
        echo "<div id=\"sidenavbig\" class=\"sidenavbig\" style=\"display: none;\">\n";

        echo "  <div class=\"navbtn\">\n";
        echo "    <a href=\"javascript:void(0)\" onclick=\"hideUnhideNav()\"><img src=\"../images/menu-icon-png-3-lines-white-horizontal.png\" /></a>\n";
        echo "  </div>\n";
        
        echo ($active == "GovernanceRiskCompliance" ? "<li class=\"active\">\n" : "<li>\n");
        echo "  <a href=\"../reports/index.php\">" . $escaper->escapeHtml($lang['GovernanceRiskCompliance']) . "</a>\n";
        echo "</li>\n";
        
        // If the incident management extra is enabled
        if (incident_management_extra())
        {
                // Include the incident management extra
                require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));
                
                // If the user has incident management permissions
		if (check_permission("im_incidents"))
                {
                        echo ($active == "IncidentManagement" ? "<li class=\"active\">\n" : "<li>\n");
                        echo "  <a href=\"../incidents/index.php\">" . $escaper->escapeHtml($lang['IncidentManagement']) . "</a>\n";
                        echo "</li>\n";
                }
        }

	// If the vulnerability management extra is enabled
        if (vulnmgmt_extra())
        {
                // Include the vulnerability management extra
                require_once(realpath(__DIR__ . '/../extras/vulnmgmt/index.php'));

                // If the user has vulnerability management permissions
                if (check_permission("vm_vulnerabilities"))
                {
                        echo ($active == "VulnerabilityManagement" ? "<li class=\"active\">\n" : "<li>\n");
                        echo "  <a href=\"../vulnerabilities/index.php\">" . $escaper->escapeHtml($lang['VulnerabilityManagement']) . "</a>\n";
                        echo "</li>\n";
                }
        }

        echo "</div>\n";

	echo "<script>\n";
	echo "function hideUnhideNav(){\n";
	echo "  if (document.getElementById('sidenavsmall').style.display == 'none'){\n";
	echo "    document.getElementById('sidenavsmall').style.display = '';\n";
	echo "    document.getElementById('sidenavbig').style.display = 'none';\n";
	echo "  } else if (document.getElementById('sidenavbig').style.display == 'none'){\n";
	echo "    document.getElementById('sidenavsmall').style.display = 'none';\n";
	echo "    document.getElementById('sidenavbig').style.display = '';\n";
	echo "  }\n";
	echo "}\n";
	echo "</script>\n";
}
/****************************************
* FUNCTION: DISPLAY CUSTOM RISK COLUMNS *
****************************************/
function display_custom_risk_columns($custom_setting_field = "custom_plan_mitigation_display_settings"){
    global $escaper, $lang;
    $user = get_user_by_id($_SESSION['uid']);
    $settings = json_decode($user[$custom_setting_field], true);

    $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
    $risk_setting = [];
    foreach($risk_colums_setting as $column){
        $risk_setting[$column[0]] = $column[1];
    }

    $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
    $mitigation_setting = [];
    foreach($mitigation_colums_setting as $column){
        $mitigation_setting[$column[0]] = $column[1];
    }

    $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
    $review_setting = [];
    foreach($review_colums_setting as $column){
        $review_setting[$column[0]] = $column[1];
    }
    $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
    $columns = [];
    foreach($columns_setting as $column){
        if(stripos($column[0], "custom_field_") !== false){
            if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
        } else if($column[1] == 1) $columns[] = $column[0];
    }
    if(!count($columns)){
        if($custom_setting_field == "custom_reviewregularly_display_settings"){
            $risk_setting = array("id"=>1,"risk_status"=>1,"subject"=>1,"calculated_risk"=>1,"days_open"=>1);
            $mitigation_setting = array();
            $review_setting = array("management_review"=>0,"review_date"=>0,"next_step"=>0,"next_review_date"=>1);
        } else {
            $risk_setting = array("id"=>1,"risk_status"=>1,"subject"=>1,"calculated_risk"=>1,"submission_date"=>1);
            $mitigation_setting = array("mitigation_planned"=>1);
            $review_setting = array("management_review"=>1);
        }
    }

    $str = "
        <style>
            #column-selections-container label{color:#000;}
            ul.sortable{list-style:none;margin:0;}
            ul.sortable li{border: 1px dotted #cccccc;margin:2px 0;padding:5px;}
        </style>
        <div class=\"well\" id='column-selections-container'>
            <h4 class=\"collapsible--toggle clearfix\">
                <span>".$escaper->escapeHtml($lang['ColumnSelections'])."</span>
            </h4>\n";

    
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $active_fields = get_active_fields();

        $risk_columns = array(
            'id' => $escaper->escapeHtml($lang['ID']),
            'risk_status' => $escaper->escapeHtml($lang['Status']),
            'closure_date' => $escaper->escapeHtml($lang['DateClosed']),
            'subject' => $escaper->escapeHtml($lang['Subject']),
            'calculated_risk' => $escaper->escapeHtml($lang['InherentRisk']),
            'residual_risk' => $escaper->escapeHtml($lang['ResidualRisk']),
            'project' => $escaper->escapeHtml($lang['Project']),
            'days_open' => $escaper->escapeHtml($lang['DaysOpen']),
        );
        $mitigation_columns = array(
            'mitigation_planned' => $escaper->escapeHtml($lang['MitigationPlanned']),
        );
        $review_columns = array("management_review"=>$escaper->escapeHtml($lang['ManagementReview']));
        foreach($active_fields as $active_field)
        {
            $field = $label = "";
            // If this is main field
            if($active_field['is_basic'] == 1) {
                $dynamic_field_info = get_dynamic_names_by_main_field_name($active_field['name']);
                if($dynamic_field_info) {
                    $field = $dynamic_field_info['name'];
                    $label = $dynamic_field_info['text'];
                } else continue;
            } else {
                $field = "custom_field_{$active_field['id']}";
                $label = $escaper->escapeHtml($active_field['name']);
            }
            $active_field["field"] = $field;
            $active_field["label"] = $label;
            switch($active_field['tab_index']){
                case 1:
                    $risk_columns[$field] = $label;
                break;
                case 2:
                    $mitigation_columns[$field] = $label;
                break;
                case 3:
                    $review_columns[$field] = $label;
                break;
            }
        }
    } else {
        // Names list of Risk columns
        $risk_columns = array(
            'id' => $escaper->escapeHtml($lang['ID']),
            'risk_status' => $escaper->escapeHtml($lang['Status']),
            'closure_date' => $escaper->escapeHtml($lang['DateClosed']),
            'subject' => $escaper->escapeHtml($lang['Subject']),
            'reference_id' => $escaper->escapeHtml($lang['ExternalReferenceId']),
            'regulation' => $escaper->escapeHtml($lang['ControlRegulation']),
            'control_number' => $escaper->escapeHtml($lang['ControlNumber']),
            'location' => $escaper->escapeHtml($lang['SiteLocation']),
            'source' => $escaper->escapeHtml($lang['RiskSource']),
            'category' => $escaper->escapeHtml($lang['Category']),
            'team' => $escaper->escapeHtml($lang['Team']),
            'additional_stakeholders' => $escaper->escapeHtml($lang['AdditionalStakeholders']),
            'technology' => $escaper->escapeHtml($lang['Technology']),
            'owner' => $escaper->escapeHtml($lang['Owner']),
            'manager' => $escaper->escapeHtml($lang['OwnersManager']),
            'submitted_by' => $escaper->escapeHtml($lang['SubmittedBy']),
            'risk_tags' => $escaper->escapeHtml($lang['Tags']),
            'scoring_method' => $escaper->escapeHtml($lang['RiskScoringMethod']),
            'calculated_risk' => $escaper->escapeHtml($lang['InherentRisk']),
            'residual_risk' => $escaper->escapeHtml($lang['ResidualRisk']),
            'submission_date' => $escaper->escapeHtml($lang['SubmissionDate']),
            'project' => $escaper->escapeHtml($lang['Project']),
            'days_open' => $escaper->escapeHtml($lang['DaysOpen']),
            'affected_assets' => $escaper->escapeHtml($lang['AffectedAssets']),
            'risk_assessment' => $escaper->escapeHtml($lang['RiskAssessment']),
            'additional_notes' => $escaper->escapeHtml($lang['AdditionalNotes']),
            'risk_mapping' => $escaper->escapeHtml($lang['RiskMapping']),
            'threat_mapping' => $escaper->escapeHtml($lang['ThreatMapping']),
        );
        $mitigation_columns = array(
            'mitigation_planned' => $escaper->escapeHtml($lang['MitigationPlanned']),
            'planning_strategy' => $escaper->escapeHtml($lang['PlanningStrategy']),
            'planning_date' => $escaper->escapeHtml($lang['MitigationPlanning']),
            'mitigation_effort' => $escaper->escapeHtml($lang['MitigationEffort']),
            'mitigation_cost' => $escaper->escapeHtml($lang['MitigationCost']),
            'mitigation_owner' => $escaper->escapeHtml($lang['MitigationOwner']),
            'mitigation_team' => $escaper->escapeHtml($lang['MitigationTeam']),
            'mitigation_accepted' => $escaper->escapeHtml($lang['MitigationAccepted']),
            'mitigation_date' => $escaper->escapeHtml($lang['MitigationDate']),
            'mitigation_controls' => $escaper->escapeHtml($lang['MitigationControls']),
            'current_solution' => $escaper->escapeHtml($lang['CurrentSolution']),
            'security_recommendations' => $escaper->escapeHtml($lang['SecurityRecommendations']),
            'security_requirements' => $escaper->escapeHtml($lang['SecurityRequirements']),
        );
        $review_columns = array(
            'management_review' => $escaper->escapeHtml($lang['ManagementReview']),
            'review_date' => $escaper->escapeHtml($lang['ReviewDate']),
            'next_review_date' => $escaper->escapeHtml($lang['NextReviewDate']),
            'next_step' => $escaper->escapeHtml($lang['NextStep']),
            'comments' => $escaper->escapeHtml($lang['Comments']),
        );
    }
    $risk_columns_keys = array_values(array_unique(array_merge(array_keys($risk_setting),array_keys($risk_columns))));
    $mitigation_columns_keys = array_values(array_unique(array_merge(array_keys($mitigation_setting),array_keys($mitigation_columns))));
    $review_columns_keys = array_values(array_unique(array_merge(array_keys($review_setting),array_keys($review_columns))));
    // risk columns
    $str .= "<div class=\"well\">\n
            <h4 class=\"collapsible--toggle clearfix\">
                <span><i class=\"fa fa-caret-down\"></i>" . $escaper->escapeHtml($lang['RiskColumns']) . ":</span>
            </h4>\n
            <div class=\"collapsible\">
                <div class=\"row-fluid\">\n
                    <div class=\"span6\">\n
                        <ul class=\"sortable sortable-risk\">";
                        $half_num = ceil(count($risk_columns_keys)/2);
                        for($i=0;$i<$half_num;$i++){
                            $field = $risk_columns_keys[$i];
                            $elem_id = "checkbox_".$field;
                            $check_val = isset($risk_setting[$field])?$risk_setting[$field]:0;
                            $checked = $check_val?"checked='yes'":"";
                            if(isset($risk_columns[$field])){
                                $str .= "<li>
                                        <input class='hidden-checkbox' type='checkbox' name='".$field."' id='".$elem_id."' ".$checked."/>
                                        <label for='".$elem_id."'>".$risk_columns[$field]."</label>
                                    </li>";
                            }
                        }
                        $str .= "</ul>
                    </div>\n
                    <div class=\"span6\">\n
                        <ul class=\"sortable sortable-risk\">";
                        for($i;$i<count($risk_columns_keys);$i++){
                            $field = $risk_columns_keys[$i];
                            $elem_id = "checkbox_".$field;
                            $check_val = isset($risk_setting[$field])?$risk_setting[$field]:0;
                            $checked = $check_val?"checked='yes'":"";
                            if(isset($risk_columns[$field])){
                                $str .= "<li>
                                        <input class='hidden-checkbox' type='checkbox' name='".$field."' id='".$elem_id."' ".$checked."/>
                                        <label for='".$elem_id."'>".$risk_columns[$field]."</label>
                                    </li>";
                            }
                        }
                        $str .= "</ul>
                    </div>\n
                </div>\n
            </div>\n
        </div>\n";
    $str .= "<div class=\"row-fluid\">
        <div class=\"span6\">\n";
        // mitigation columns
        $str .= "<div class=\"well\">\n
                <h4 class=\"collapsible--toggle clearfix\">
                    <span><i class=\"fa fa-caret-down\"></i>" . $escaper->escapeHtml($lang['MitigationColumns']) . ":</span>
                </h4>\n
                <div class=\"collapsible\">
                    <ul class=\"sortable sortable-mitigation\">";
                    foreach($mitigation_columns_keys as $field){
                        $check_val = isset($mitigation_setting[$field])?$mitigation_setting[$field]:0;
                        $elem_id = "checkbox_".$field;
                        $checked = $check_val?"checked='yes'":"";
                        if(isset($mitigation_columns[$field])){
                            $str .= "<li>
                                    <input class='hidden-checkbox' type='checkbox' name='".$field."' id='".$elem_id."' ".$checked."/>
                                    <label for='".$elem_id."'>".$mitigation_columns[$field]."</label>
                                </li>";
                        }
                    }
                    $str .= "</ul>
                </div>\n
            </div>\n
        </div>\n";
        // review columns
        $str .= "<div class=\"span6\">\n
            <div class=\"well\">\n
                <h4 class=\"collapsible--toggle clearfix\">
                    <span><i class=\"fa fa-caret-down\"></i>" . $escaper->escapeHtml($lang['ReviewColumns']) . ":</span>
                </h4>\n
                <div class=\"collapsible\">
                    <ul class=\"sortable sortable-review\">";
                    foreach($review_columns_keys as $field){
                        $check_val = isset($review_setting[$field])?$review_setting[$field]:0;
                        $elem_id = "checkbox_".$field;
                        $checked = $check_val?"checked='yes'":"";
                        if(isset($review_columns[$field])){
                            $str .= "<li>
                                    <input class='hidden-checkbox' type='checkbox' name='".$field."' id='".$elem_id."' ".$checked."/>
                                    <label for='".$elem_id."'>".$review_columns[$field]."</label>
                                </li>";
                        }
                    }
                    $str .= "</ul>
                </div>\n
            </div>\n
        </div>\n
    </div>\n";
    $str .= "</div>\n";
    echo $str;
}
function get_label_by_risk_field_name($field){
    global $lang, $escaper;
	// Names list of Risk columns
	$columns = array(
		'id' => js_string_escape($lang['ID']),
		'risk_status' => js_string_escape($lang['Status']),
		'closure_date' => js_string_escape($lang['DateClosed']),
        'subject' => js_string_escape($lang['Subject']),
        'risk_mapping' => js_string_escape($lang['RiskMapping']),
        'threat_mapping' => js_string_escape($lang['ThreatMapping']),
		'reference_id' => js_string_escape($lang['ExternalReferenceId']),
		'regulation' => js_string_escape($lang['ControlRegulation']),
		'control_number' => js_string_escape($lang['ControlNumber']),
		'location' => js_string_escape($lang['SiteLocation']),
		'source' => js_string_escape($lang['RiskSource']),
		'category' => js_string_escape($lang['Category']),
		'team' => js_string_escape($lang['Team']),
		'additional_stakeholders' => js_string_escape($lang['AdditionalStakeholders']),
		'technology' => js_string_escape($lang['Technology']),
		'owner' => js_string_escape($lang['Owner']),
		'manager' => js_string_escape($lang['OwnersManager']),
		'submitted_by' => js_string_escape($lang['SubmittedBy']),
		'risk_tags' => js_string_escape($lang['Tags']),
		'submission_date' => js_string_escape($lang['SubmissionDate']),
        'project' => js_string_escape($lang['Project']),
        'project_status' => js_string_escape($lang['ProjectStatus']),
		'days_open' => js_string_escape($lang['DaysOpen']),
		'affected_assets' => js_string_escape($lang['AffectedAssets']),
		'risk_assessment' => js_string_escape($lang['RiskAssessment']),
        'additional_notes' => js_string_escape($lang['AdditionalNotes']),
        'closed_by' => js_string_escape($lang['ClosedBy']),
        'close_reason' => js_string_escape($lang['CloseReason']),
        'close_out' => js_string_escape($lang['CloseOutInformation']),

		'mitigation_planned' => js_string_escape($lang['MitigationPlanned']),
		'planning_strategy' => js_string_escape($lang['PlanningStrategy']),
		'planning_date' => js_string_escape($lang['MitigationPlanning']),
		'mitigation_effort' => js_string_escape($lang['MitigationEffort']),
		'mitigation_cost' => js_string_escape($lang['MitigationCost']),
		'mitigation_owner' => js_string_escape($lang['MitigationOwner']),
		'mitigation_team' => js_string_escape($lang['MitigationTeam']),
		'mitigation_accepted' => js_string_escape($lang['MitigationAccepted']),
		'mitigation_date' => js_string_escape($lang['MitigationDate']),
        'mitigation_controls' => js_string_escape($lang['MitigationControls']),
        'mitigation_percent' => js_string_escape($lang['MitigationPercent']),
		'current_solution' => js_string_escape($lang['CurrentSolution']),
		'security_recommendations' => js_string_escape($lang['SecurityRecommendations']),
		'security_requirements' => js_string_escape($lang['SecurityRequirements']),

		'management_review' => js_string_escape($lang['ManagementReview']),
		'review_date' => js_string_escape($lang['ReviewDate']),
		'next_review_date' => js_string_escape($lang['NextReviewDate']),
		'next_step' => js_string_escape($lang['NextStep']),
		'comments' => js_string_escape($lang['Comments']),

        'scoring_method' => js_string_escape($lang['RiskScoringMethod']),
        'calculated_risk' => js_string_escape($lang['InherentRiskCurrent']),
        'residual_risk' => js_string_escape($lang['ResidualRiskCurrent']),
        'calculated_risk_30' => js_string_escape(_lang('InherentRiskDays', ['days' => 30])),
        'residual_risk_30' => js_string_escape(_lang('ResidualRiskDays', ['days' => 30])),
        'calculated_risk_60' => js_string_escape(_lang('InherentRiskDays', ['days' => 60])),
        'residual_risk_60' => js_string_escape(_lang('ResidualRiskDays', ['days' => 60])),
        'calculated_risk_90' => js_string_escape(_lang('InherentRiskDays', ['days' => 90])),
        'residual_risk_90' => js_string_escape(_lang('ResidualRiskDays', ['days' => 90])),

        'CLASSIC_likelihood' => js_string_escape($lang['Classic'].": ".$lang['Likelihood']),
        'CLASSIC_impact' => js_string_escape($lang['Classic'].": ".$lang['Impact']),
        'CVSS_AccessVector' => js_string_escape("CVSS: ".$lang['AccessVector']),
        'CVSS_AccessComplexity' => js_string_escape("CVSS: ".$lang['AccessComplexity']),
        'CVSS_Authentication' => js_string_escape("CVSS: ".$lang['Authentication']),
        'CVSS_ConfImpact' => js_string_escape("CVSS: ".$lang['ConfidentialityImpact']),
        'CVSS_IntegImpact' => js_string_escape("CVSS: ".$lang['IntegrityImpact']),
        'CVSS_AvailImpact' => js_string_escape("CVSS: ".$lang['AvailabilityImpact']),
        'CVSS_Exploitability' => js_string_escape("CVSS: ".$lang['Exploitability']),
        'CVSS_RemediationLevel' => js_string_escape("CVSS: ".$lang['RemediationLevel']),
        'CVSS_ReportConfidence' => js_string_escape("CVSS: ".$lang['ReportConfidence']),
        'CVSS_CollateralDamagePotential' => js_string_escape("CVSS: ".$lang['CollateralDamagePotential']),
        'CVSS_TargetDistribution' => js_string_escape("CVSS: ".$lang['TargetDistribution']),
        'CVSS_ConfidentialityRequirement' => js_string_escape("CVSS: ".$lang['ConfidentialityRequirement']),
        'CVSS_IntegrityRequirement' => js_string_escape("CVSS: ".$lang['IntegrityRequirement']),
        'CVSS_AvailabilityRequirement' => js_string_escape("CVSS: ".$lang['AvailabilityRequirement']),
        'DREAD_DamagePotential' => js_string_escape("DREAD: ".$lang['DamagePotential']),
        'DREAD_Reproducibility' => js_string_escape("DREAD: ".$lang['Reproducibility']),
        'DREAD_Exploitability' => js_string_escape("DREAD: ".$lang['Exploitability']),
        'DREAD_AffectedUsers' => js_string_escape("DREAD: ".$lang['AffectedUsers']),
        'DREAD_Discoverability' => js_string_escape("DREAD: ".$lang['Discoverability']),
        'OWASP_SkillLevel' => js_string_escape("OWASP: ".$lang['SkillLevel']),
        'OWASP_Motive' => js_string_escape("OWASP: ".$lang['Motive']),
        'OWASP_Opportunity' => js_string_escape("OWASP: ".$lang['Opportunity']),
        'OWASP_Size' => js_string_escape("OWASP: ".$lang['Size']),
        'OWASP_EaseOfDiscovery' => js_string_escape("OWASP: ".$lang['EaseOfDiscovery']),
        'OWASP_EaseOfExploit' => js_string_escape("OWASP: ".$lang['EaseOfExploit']),
        'OWASP_Awareness' => js_string_escape("OWASP: ".$lang['Awareness']),
        'OWASP_IntrusionDetection' => js_string_escape("OWASP: ".$lang['IntrusionDetection']),
        'OWASP_LossOfConfidentiality' => js_string_escape("OWASP: ".$lang['LossOfConfidentiality']),
        'OWASP_LossOfIntegrity' => js_string_escape("OWASP: ".$lang['LossOfIntegrity']),
        'OWASP_LossOfAvailability' => js_string_escape("OWASP: ".$lang['LossOfAvailability']),
        'OWASP_LossOfAccountability' => js_string_escape("OWASP: ".$lang['LossOfAccountability']),
        'OWASP_FinancialDamage' => js_string_escape("OWASP: ".$lang['FinancialDamage']),
        'OWASP_ReputationDamage' => js_string_escape("OWASP: ".$lang['ReputationDamage']),
        'OWASP_NonCompliance' => js_string_escape("OWASP: ".$lang['NonCompliance']),
        'OWASP_PrivacyViolation' => js_string_escape("OWASP: ".$lang['PrivacyViolation']),
        'Contributing_Likelihood' => js_string_escape($lang['ContributingRisk'].": ".$lang['Likelihood']),

        'risk_mapping_risk_grouping' => js_string_escape($lang['RiskGrouping']),
        'risk_mapping_risk' => js_string_escape($lang['Risk']),
        'risk_mapping_risk_event' => js_string_escape($lang['RiskEvent']),
        'risk_mapping_description' => js_string_escape($lang['Description']),
        'risk_mapping_function' => js_string_escape($lang['Function']),
	);
    $contributing_risks = get_contributing_risks();
    foreach($contributing_risks as $contributing_risk){
        $columns["Contributing_Impact_".$contributing_risk['id']] = js_string_escape($lang['ContributingRisk'].": ".$lang['Impact']." - ".$contributing_risk['subject']);
    }
	return $columns[$field];

}

/***********************************
 * FUNCTION: DISPLAY LICENSE CHECK *
 ***********************************/
function display_license_check()
{
	global $lang;
	global $escaper;

	// If the license check failed
	if (isset($_SESSION['license_check']) && $_SESSION['license_check'] == "fail")
	{
		echo "<div class=\"license_check\" style=\"width: 100%; text-align: center; font-weight: bold; background-color: #ffcccb;\">\n";
		echo $escaper->escapeHtml($lang['LicenseCheckFailed']);	
		echo "</div>\n";
	}
}

/******************************************
 * FUNCTION: DISPLAY CONTROL GAP ANALYSIS *
 ******************************************/
function display_control_gap_analysis()
{
    global $lang, $escaper;

    // If User has permission for governance menu, shows Control Gap Analysis report
    if(!empty($_SESSION['governance']))
    {
        // Begin the framework filter form
        echo "<form id=\"framework_form\" name=\"framework_form\" method=\"post\" action=\"\">\n";

	// Add the filter
	echo $escaper->escapeHtml($lang['ControlFramework']) . ":&nbsp;&nbsp;";

        // If no framework was posted
        if (!isset($_POST['framework']))
        {
                // Set the filter option to None Selected
                $framework = null;
        }
        else $framework = (int)$_POST['framework'];

        // Create the dropdown
	create_dropdown("frameworks", $framework, "framework");

	echo "</form>\n";

	// If a framework id was posted
	if ($framework != null)
	{

		// Display the control maturity spider chart
		display_control_maturity_spider_chart($framework);

        echo "
            <div class=\"span10\">
              <div class=\"wrap\">
                <ul class=\"tabs group\">
                  <li><a class=\"active\" href=\"#/below_maturity\">" . $escaper->escapeHtml($lang['BelowMaturity']) . "</a></li>
                  <li><a href=\"#/at_maturity\">" . $escaper->escapeHtml($lang['AtMaturity']) . "</a></li>
                  <li><a href=\"#/above_maturity\">" . $escaper->escapeHtml($lang['AboveMaturity']) . "</a></li>
                </ul>
                <div id=\"content\">
                  <div id=\"below_maturity\" class=\"settings_tab\">
        ";

			display_gap_analysis_table($framework, "below_maturity");

        echo "
                  </div>
                  <div id=\"at_maturity\" class=\"settings_tab\" style=\"display: none\">
        ";

			display_gap_analysis_table($framework, "at_maturity");

        echo "
                  </div>
                  <div id=\"above_maturity\" class=\"settings_tab\" style=\"display: none\">
        ";

			display_gap_analysis_table($framework, "above_maturity");

        echo "
                  </div>
                </div>
              </div>
            </div>
        ";

	}
    }
}

/****************************************
 * FUNCTION: DISPLAY GAP ANALYSIS TABLE *
 ****************************************/
function display_gap_analysis_table($framework, $maturity)
{
	global $lang, $escaper;

	$tableID = "control-gap-analysis-" . $maturity;

	echo "
            <table id=\"{$tableID}\" width=\"100%\" class=\"risk-datatable table table-bordered table-striped table-condensed\">
                <thead >
                    <tr >
                        <th data-name='control_number' align=\"left\" width=\"50px\" valign=\"top\">".$escaper->escapeHtml($lang['ControlNumber'])."</th>
                        <th data-name='control_short_name' align=\"left\" width=\"200px\" valign=\"top\">".$escaper->escapeHtml($lang['ControlShortName'])."</th>
                        <th data-name='control_phase' align=\"left\" width=\"50px\" valign=\"top\">".$escaper->escapeHtml($lang['ControlPhase'])."</th>
                        <th data-name='control_family' align=\"left\" width=\"50px\" valign=\"top\">".$escaper->escapeHtml($lang['ControlFamily'])."</th>
                        <th data-name='control_current_maturity' align=\"left\" width=\"50px\" valign=\"top\">".$escaper->escapeHtml($lang['CurrentControlMaturity'])."</th>
                        <th data-name='control_desired_maturity' align=\"center\" width=\"50px\" valign=\"top\">".$escaper->escapeHtml($lang['DesiredControlMaturity'])."</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <br>
            <script>
                var pageLength = 25;
                var form = $('#{$tableID}').parents('form');
                var datatableInstance_{$maturity} = $('#{$tableID}').DataTable({
                    bFilter: false,
                    bLengthChange: false,
                    processing: true,
                    serverSide: true,
                    bSort: true,
                    pagingType: \"full_numbers\",
                    dom : \"flrtip\",
                    pageLength: pageLength,
                    dom : \"flrti<'#view-all.view-all'>p\",
                    createdRow: function(row, data, index){
                        var background = $('.background-class', $(row)).data('background');
                        $(row).find('td').addClass(background)
                    },
                    order: [[1, 'asc']],
                    ajax: {
                        url: BASE_URL + '/api/reports/governance/control_gap_analysis?framework_id=" . $escaper->escapeHtml($framework) . "&maturity=" . $escaper->escapeHtml($maturity) . "',
                        data: function(d){
                        },
                        complete: function(response){
                        }
                    }
                });

                // Add paginate options
                datatableInstance_{$maturity}.on('draw', function(e, settings){
                    $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                    $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                    $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                    $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
                })
                
                // Add all text to View All button on bottom
                $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

                // View All
                $(\"#{$maturity}\").find(\".view-all\").click(function(){
                    var oSettings =  datatableInstance_{$maturity}.settings();
                    oSettings[0]._iDisplayLength = -1;
                    datatableInstance_{$maturity}.draw()
                    $(this).addClass(\"current\");
                })
                
                // Page event
                $(\"body #{$maturity}\").on(\"click\", \"span > .paginate_button\", function(){
                    var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                    var oSettings =  datatableInstance_{$maturity}.settings();
                    if(oSettings[0]._iDisplayLength == -1){
                        $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                        oSettings[0]._iDisplayLength = pageLength;
                        datatableInstance_{$maturity}.draw()
                    }
                })
                
            </script>
        ";
}

/*************************************************************
* FUNCTION: DISPLAY CONTRIBUTING RISKS LIKELIHOOD TABLE LIST *
**************************************************************/
function display_contributing_risks_likelihood_table_list(){
    global $lang, $escaper;
    $rows = get_contributing_risks_likelihood_list();
    $str = "";
    foreach($rows as $row){
        if(count($rows) != 1) $delete_button = "<a class='delete-value' data-id='{$row['id']}' data-value='{$row['value']}'><i class='fa fa-trash'></i></a>";
        else $delete_button = "";
        $str .= "
            <tr>
                <td align='center'>{$row['value']}</td>
                <td align='center'>
                    <span data-id='{$row['id']}' data-value='{$row['value']}'><span class='editable'>{$escaper->escapeHtml($row['name'])}</span>
                    <input type='text' class='editable' value='{$escaper->escapeHtml($row['name'])}' style='display: none;'></span>
                </td>
                <td align='center'>{$delete_button}</td>
                <td></td>
            </tr>
        ";
    }
    return $str;
}
/*********************************************************
* FUNCTION: DISPLAY CONTRIBUTING RISKS IMPACT TABLE LIST *
**********************************************************/
function display_contributing_risks_impact_table_list(){
    global $lang, $escaper;
    $rows = get_contributing_risks_impact_list();
    $str = "";
    $prev_subject = "";
    foreach($rows as $row){
        if($prev_subject != $row['subject']) {
            $str .= "
                <tr class='subject-row' data-value='{$row['value']}' data-contributing_risks_id='{$row['contributing_risks_id']}'>
                    <td align='center'>{$escaper->escapeHtml($row['subject'])}</td>
                    <td></td>
                    <td align='center'>
                        <button class='impact-add-btn'><i class='fa fa-plus'></i></button>
                    </td>
                    <td></td>
                </tr>
            ";
        }
        $prev_subject = $row['subject'];
        $str .= "
            <tr>
                <td align='center'>{$row['value']}</td>
                <td align='center'>
                    <span data-id='{$row['id']}' data-value='{$row['value']}'><span class='editable'>{$escaper->escapeHtml($row['name'])}</span>
                    <input type='text' class='editable' value='{$escaper->escapeHtml($row['name'])}' style='display: none;'></span>
                </td>
                <td align='center'>
                    <a class='delete-value' data-id='{$row['id']}' data-value='{$row['value']}' data-contributing_risks_id='{$row['contributing_risks_id']}'><i class='fa fa-trash'></i></a>
                </td>
                <td></td>
            </tr>
        ";
    }
    return $str;
}

/*******************************************
 * FUNCTION: DISPLAY HIGHCHARTS JAVASCRIPT *
 *******************************************/
function display_highcharts_javascript($scripts)
{
        // If the global value is not already set
        if (!isset($GLOBALS['highcharts_delivery_method']))
        {       
                // Set the highcharts delivery method
                $GLOBALS['highcharts_delivery_method'] = get_setting("highcharts_delivery_method");
        }

        // Set the highcharts delivery method
	$highcharts_delivery_method = $GLOBALS['highcharts_delivery_method'];

        // If the highcharts delivery method is local
        if ($highcharts_delivery_method == "local")
        {
                // Get the SimpleRisk Base URL
                $simplerisk_base_url = get_setting('simplerisk_base_url');

                // If the last character is not a /
                if (substr($simplerisk_base_url, -1) != "/")
                {
                        // Append a / to the SimpleRisk Base URL
                        $simplerisk_base_url .= "/";
                }

                // Append the path to the highcharts code
                $path = $simplerisk_base_url . "vendor/node_modules/highcharts/";
        }
        // Otherwise
        else
        {
                // Use the HighCharts CDN as the path
                $path = "https://code.highcharts.com/";
        }

        // For each script provided
        foreach ($scripts as $script)
        {
                // Display it as a script src
                echo "<script src=\"" . $path . $script . "?" . current_version("app") . "\"></script>\n";
        }
}

/***************************************
 * FUNCTION: DISPLAY JQUERY JAVASCRIPT *
 ***************************************/
function display_jquery_javascript($scripts)
{
        // If the global value is not already set
        if (!isset($GLOBALS['jquery_delivery_method']))
        {       
                // Set the jquery delivery method
                $GLOBALS['jquery_delivery_method'] = get_setting("jquery_delivery_method");
        }

        // Set the jquery delivery method
	$jquery_delivery_method = $GLOBALS['jquery_delivery_method'];

        // If the jquery delivery method is local
        if ($jquery_delivery_method == "local")
        {
                // Get the SimpleRisk Base URL
                $simplerisk_base_url = get_setting('simplerisk_base_url');

                // If the last character is not a /
                if (substr($simplerisk_base_url, -1) != "/")
                {
                        // Append a / to the SimpleRisk Base URL
                        $simplerisk_base_url .= "/";
                }

                // Append the path to the jquery code
                $path = $simplerisk_base_url . "vendor/components/jquery/";
        }
        // Otherwise
        else
        {
                // Use the Google CDN as the path
                $path = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/";
        }

        // For each script provided
        foreach ($scripts as $script)
        {
                // Display it as a script src
                echo "<script src=\"" . $path . $script . "?" . current_version("app") . "\"></script>\n";
        }
}

/******************************************
 * FUNCTION: DISPLAY JQUERY UI JAVASCRIPT *
 ******************************************/
function display_jquery_ui_javascript($scripts)
{
        // If the global value is not already set
        if (!isset($GLOBALS['jquery_delivery_method']))
        {
		// Set the jquery delivery method
		$GLOBALS['jquery_delivery_method'] = get_setting("jquery_delivery_method");
        }

	// Set the jquery delivery method
	$jquery_delivery_method = $GLOBALS['jquery_delivery_method'];

        // If the jquery delivery method is local
        if ($jquery_delivery_method == "local")
        {
                // Get the SimpleRisk Base URL
                $simplerisk_base_url = get_setting('simplerisk_base_url');

                // If the last character is not a /
                if (substr($simplerisk_base_url, -1) != "/")
                {
                        // Append a / to the SimpleRisk Base URL
                        $simplerisk_base_url .= "/";
                }

                // Append the path to the jquery code
                $path = $simplerisk_base_url . "vendor/components/jqueryui/";
        }
        // Otherwise
        else
        {
                // Use the Google CDN as the path
                $path = "https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/";
        }

        // For each script provided
        foreach ($scripts as $script)
        {
                // Display it as a script src
                echo "<script src=\"" . $path . $script . "?" . current_version("app") . "\"></script>\n";
        }
}

/******************************************
 * FUNCTION: DISPLAY BOOTSTRAP JAVASCRIPT *
 ******************************************/
function display_bootstrap_javascript()
{
        // If the global value is not already set
        if (!isset($GLOBALS['bootstrap_delivery_method']))
        {
                // Set the bootstrap delivery method
                $GLOBALS['bootstrap_delivery_method'] = get_setting("bootstrap_delivery_method");
        }

        // Set the bootstrap delivery method
        $bootstrap_delivery_method = $GLOBALS['bootstrap_delivery_method'];

        // If the bootstrap delivery method is local
        if ($bootstrap_delivery_method == "local")
        {
                // Get the SimpleRisk Base URL
                $simplerisk_base_url = get_setting('simplerisk_base_url');

                // If the last character is not a /
                if (substr($simplerisk_base_url, -1) != "/")
                {
                        // Append a / to the SimpleRisk Base URL
                        $simplerisk_base_url .= "/";
                }

                // Append the path to the bootstrap code
                $path = $simplerisk_base_url . "vendor/twbs/bootstrap/dist/js/bootstrap.min.js";
        }
        // Otherwise
        else
        {
                // Use the jsDelivr CDN as the path
		$path = "https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js";
        }

	// Display it as a script src
	echo "<script src=\"{$path}?" . current_version("app") . "\" crossorigin=\"anonymous\"></script>\n";
}

/***********************************
 * FUNCTION: DISPLAY BOOTSTRAP CSS *
 ***********************************/
function display_bootstrap_css()
{
        // If the global value is not already set
        if (!isset($GLOBALS['bootstrap_delivery_method']))
        {
                // Set the bootstrap delivery method
                $GLOBALS['bootstrap_delivery_method'] = get_setting("bootstrap_delivery_method");
        }

        // Set the bootstrap delivery method
        $bootstrap_delivery_method = $GLOBALS['bootstrap_delivery_method'];

        // If the bootstrap delivery method is local
        if ($bootstrap_delivery_method == "local")
        {
                // Get the SimpleRisk Base URL
                $simplerisk_base_url = get_setting('simplerisk_base_url');

                // If the last character is not a /
                if (substr($simplerisk_base_url, -1) != "/")
                {
                        // Append a / to the SimpleRisk Base URL
                        $simplerisk_base_url .= "/";
                }

                // Append the path to the bootstrap code
                $path = $simplerisk_base_url . "vendor/twbs/bootstrap/dist/css/bootstrap.min.css";
        }
        // Otherwise
        else
        {
                // Use the jsDelivr CDN as the path
		$path = "https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css";
        }

	// Display it as a stylesheet
	echo "<link href=\"{$path}?" . current_version("app")  . "\" rel=\"stylesheet\" crossorigin=\"anonymous\">\n";
}

/********************************
* FUNCTION: DISPLAY ADD PROJECT *
*********************************/
function display_add_projects($template_group_id = ""){
    global $lang;
    global $escaper;
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        if(!$template_group_id){
            $group = get_default_template_group("project");
            $template_group_id = $group["id"];

        }
        $active_fields = get_active_fields("project", $template_group_id);
        foreach($active_fields as $field)
        {
            if($field['is_basic'] == 1)
            {
                switch($field['name']){
                    case 'ProjectName':
                        echo "
                            <label for=\"\">".$escaper->escapeHtml($lang['NewProjectName'])."</label>
                            <input type=\"text\" name=\"new_project\" id=\"project--name\" value=\"\" class=\"form-control\" required>
                        ";
                    break;
                    case 'DueDate':
                        echo "
                            <label for=\"\">".$escaper->escapeHtml($lang['DueDate'])."</label>
                            <input type=\"text\" name=\"due_date\" class=\"form-control datepicker\" value=\"\">
                        ";
                    break;
                    case 'Consultant':
                        echo "<label for=\"\">".$escaper->escapeHtml($lang['Consultant'])."</label>";
                        create_dropdown("enabled_users", NULL, "consultant", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
                    break;
                    case 'BusinessOwner':
                        echo "<label for=\"\">".$escaper->escapeHtml($lang['BusinessOwner'])."</label>";
                        create_dropdown("enabled_users", NULL, "business_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
                    break;
                    case 'DataClassification':
                        echo "<label for=\"\">".$escaper->escapeHtml($lang['DataClassification'])."</label>";
                        create_dropdown("data_classification", NULL, "data_classification", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
                    break;
                }
            }
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    display_custom_field_edit($field, [], "label");
                }
            }
        }
    } else {
        echo "
            <label for=\"\">".$escaper->escapeHtml($lang['NewProjectName'])."</label>
            <input type=\"text\" name=\"new_project\" id=\"project--name\" value=\"\" class=\"form-control\" required>
            <label for=\"\">".$escaper->escapeHtml($lang['DueDate'])."</label>
            <input type=\"text\" name=\"due_date\" class=\"form-control datepicker\" value=\"\">
            <label for=\"\">".$escaper->escapeHtml($lang['Consultant'])."</label>
            ".create_dropdown("enabled_users", NULL, "consultant", true, false, true, "", $escaper->escapeHtml($lang['Unassigned']))."
            <label for=\"\">".$escaper->escapeHtml($lang['BusinessOwner'])."</label>
            ".create_dropdown("enabled_users", NULL, "business_owner", true, false, true, "", $escaper->escapeHtml($lang['Unassigned']))."
            <label for=\"\">".$escaper->escapeHtml($lang['DataClassification'])."</label>
            ".create_dropdown("data_classification", NULL, "data_classification", true, false, true, "", $escaper->escapeHtml($lang['Unassigned']))."
        ";
    }
}
/*********************************
* FUNCTION: DISPLAY EDIT PROJECT *
*********************************/
function display_edit_projects($template_group_id = ""){
    global $lang;
    global $escaper;
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        if(!$template_group_id){
            $group = get_default_template_group("project");
            $template_group_id = $group["id"];

        }
        $active_fields = get_active_fields("project", $template_group_id);
        foreach($active_fields as $field)
        {
            if($field['is_basic'] == 1)
            {
                switch($field['name']){
                    case 'ProjectName':
                        echo "
                            <label for=\"\">".$escaper->escapeHtml($lang['Name'])."</label>
                            <input type=\"text\" name=\"name\" class=\"form-control\" required>
                        ";
                    break;
                    case 'DueDate':
                        echo "
                            <label for=\"\">".$escaper->escapeHtml($lang['DueDate'])."</label>
                            <input type=\"text\" name=\"due_date\" class=\"form-control datepicker\" value=\"\">
                        ";
                    break;
                    case 'Consultant':
                        echo "<label for=\"\">".$escaper->escapeHtml($lang['Consultant'])."</label>";
                        create_dropdown("enabled_users", NULL, "consultant", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
                    break;
                    case 'BusinessOwner':
                        echo "<label for=\"\">".$escaper->escapeHtml($lang['BusinessOwner'])."</label>";
                        create_dropdown("enabled_users", NULL, "business_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
                    break;
                    case 'DataClassification':
                        echo "<label for=\"\">".$escaper->escapeHtml($lang['DataClassification'])."</label>";
                        create_dropdown("data_classification", NULL, "data_classification", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
                    break;
                }
            }
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    display_custom_field_edit($field, [], "label");
                }
            }
        }
        echo "<input type=\"hidden\" name=\"project_id\" value=\"\">";
    } else {
        echo "
            <label for=\"\">".$escaper->escapeHtml($lang['Name'])."</label>
            <input type=\"text\" name=\"name\" class=\"form-control\" required>
            <label for=\"\">".$escaper->escapeHtml($lang['DueDate'])."</label>
            <input type=\"text\" name=\"due_date\" class=\"form-control datepicker\" value=\"\">
            <label for=\"\">".$escaper->escapeHtml($lang['Consultant'])."</label>
            ".create_dropdown("enabled_users", NULL, "consultant", true, false, true, "", $escaper->escapeHtml($lang['Unassigned']))."
            <label for=\"\">".$escaper->escapeHtml($lang['BusinessOwner'])."</label>
            ".create_dropdown("enabled_users", NULL, "business_owner", true, false, true, "", $escaper->escapeHtml($lang['Unassigned']))."
            <label for=\"\">".$escaper->escapeHtml($lang['DataClassification'])."</label>
            ".create_dropdown("data_classification", NULL, "data_classification", true, false, true, "", $escaper->escapeHtml($lang['Unassigned']))."
            <input type=\"hidden\" name=\"project_id\" value=\"\">
        ";
    }
}
/*****************************************
* FUNCTION: DISPLAY PROJECT TABLE HEADER *
*****************************************/
function display_project_table_header($template_group_id = ""){
    global $lang;
    global $escaper;
    $header_html = "";
    $header_width = "1301";
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        if(!$template_group_id){
            $group = get_default_template_group("project");
            $template_group_id = $group["id"];

        }
        $active_fields = get_active_fields("project", $template_group_id);
        $header_html .= '
                <div class="project-block--priority white-labels">'.$escaper->escapeHtml($lang['Priority']).'</div>
            ';
        $custom_field_count = 0;
        foreach($active_fields as $field)
        {
            if($field['is_basic'] == 1)
            {
                switch($field['name']){
                    case 'ProjectName':
                        $header_html .= '<div class="project-block--name white-labels">'.$escaper->escapeHtml($lang['Name']).'</div>';
                    break;
                    case 'DueDate':
                        $header_html .= '<div class="project-block--field white-labels">'.$escaper->escapeHtml($lang['DueDate']).'</div>';
                    break;
                    case 'Consultant':
                        $header_html .= '<div class="project-block--field white-labels">'.$escaper->escapeHtml($lang['Consultant']).'</div>';
                    break;
                    case 'BusinessOwner':
                        $header_html .= '<div class="project-block--field white-labels">'.$escaper->escapeHtml($lang['BusinessOwner']).'</div>';
                    break;
                    case 'DataClassification':
                        $header_html .= '<div class="project-block--field white-labels">'.$escaper->escapeHtml($lang['DataClassification']).'</div>';
                    break;
                }
            }
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_field_count++;
                    $header_html .= '<div class="project-block--field white-labels">'.$escaper->escapeHtml($field['name']).'</div>';
                }
            }
        }
        $header_html .= ' <div class="project-block--risks white-labels">'.$escaper->escapeHtml($lang['Risk']).'</div>';
        $header_width += $custom_field_count * 150;
        $header_html = '<div class="project-headers clearfix" style="width:'.$header_width.'px">'.$header_html.'</div>';
        //$header_html .= ' <div class="project-block--risks white-labels">'.$escaper->escapeHtml($lang['Risk']).'</div>           </div>';
    } else {
        $header_html .= '
            <div class="project-headers clearfix" style="width:'.$header_width.'px">
              <div class="project-block--priority white-labels">'.$escaper->escapeHtml($lang['Priority']).'</div>
              <div class="project-block--name white-labels">'.$escaper->escapeHtml($lang['Name']).'</div>
              <div class="project-block--field white-labels">'.$escaper->escapeHtml($lang['DueDate']).'</div>
              <div class="project-block--field white-labels">'.$escaper->escapeHtml($lang['Consultant']).'</div>
              <div class="project-block--field white-labels">'.$escaper->escapeHtml($lang['BusinessOwner']).'</div>
              <div class="project-block--field white-labels">'.$escaper->escapeHtml($lang['DataClassification']).'</div>
              <div class="project-block--risks white-labels">'.$escaper->escapeHtml($lang['Risk']).'</div>
            </div>
        ';
    }
    echo $header_html;
}

?>
