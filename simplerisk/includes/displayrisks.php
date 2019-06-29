<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

require_once(realpath(__DIR__ . '/functions.php'));

/*****************************************
* FUNCTION: DISPLAY SUBMISSION_DATE VIEW *
******************************************/
function display_submission_date_view($submission_date)
{
    global $lang, $escaper;
    
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['SubmissionDate']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"submission_date\"  size=\"50\" value=\"" . $escaper->escapeHtml($submission_date) . "\" title=\"" . $escaper->escapeHtml($submission_date) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/**********************************
* FUNCTION: DISPLAY CATEGORY VIEW *
***********************************/
function display_category_view($category)
{
    global $lang, $escaper;
    
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Category']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"category\" id=\"category\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/***************************************
* FUNCTION: DISPLAY SITE LOCATION VIEW *
****************************************/
function display_site_location_view($location)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['SiteLocation']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"location\" id=\"location\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("location", $location)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("location", $location)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/***********************************************
* FUNCTION: DISPLAY EXTERNAL REFERENCE ID VIEW *
************************************************/
function display_external_reference_id_view($reference_id)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"wrap-text span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['ExternalReferenceId']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo " <input style=\"cursor: default;\" type=\"text\" name=\"reference_id\" id=\"reference_id\" size=\"20\" value=\"" . $escaper->escapeHtml($reference_id) . "\" title=\"" . $escaper->escapeHtml($reference_id) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/********************************************
* FUNCTION: DISPLAY CONTROL REGULATION VIEW *
*********************************************/
function display_control_regulation_view($regulation)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['ControlRegulation']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"regulation\" id=\"regulation\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("frameworks", $regulation)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("frameworks", $regulation)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/****************************************
* FUNCTION: DISPLAY CONTROL NUMBER VIEW *
*****************************************/
function display_control_number_view($control_number)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['ControlNumber']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo " <input style=\"cursor: default;\" type=\"text\" name=\"control_number\" id=\"control_number\" size=\"20\" value=\"" . $escaper->escapeHtml($control_number) . "\" title=\"" . $escaper->escapeHtml($control_number) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/*****************************************
* FUNCTION: DISPLAY AFFECTED ASSETS VIEW *
******************************************/
function display_affected_assets_view($risk_id)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['AffectedAssets']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    $data = get_assets_and_asset_groups_of_type($risk_id, 'risk');
    
    if ($data) {
        echo "<select class='assets-asset-groups-select-disabled' multiple >\n";

        foreach($data as $item) {
            echo "<option data-data='" . json_encode(array('class' => $item['class'])) . "' selected>" . $escaper->escapeHtml($item['name']) . "</option>";
        }

        echo "</select>\n";
    }
    echo "</div>\n";
    echo "</div>\n";
}

/************************************
* FUNCTION: DISPLAY TECHNOLOGY VIEW *
*************************************/
function display_technology_view($technology)
{
    global $lang, $escaper;
    
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Technology']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<span>" . $escaper->escapeHtml(get_technology_names($technology)) . "</span>\n";
    echo "</div>\n";
    echo "</div>\n";
}
    
/******************************
* FUNCTION: DISPLAY TEAM VIEW *
*******************************/
function display_team_view($team)
{
    global $lang, $escaper;
 
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Team']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<span> " . $escaper->escapeHtml(get_names_by_multi_values("team", $team)) . " </span>\n";
    echo "</div>\n";
    echo "</div>\n";
}    
    
/**************************************************
* FUNCTION: DISPLAY ADDIOTIONAL STAKEHOLDERS VIEW *
***************************************************/
function display_additional_stakeholders_view($additional_stakeholders)
{
    global $lang, $escaper;
 
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['AdditionalStakeholders']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<span>" . $escaper->escapeHtml(get_stakeholder_names($additional_stakeholders)) . "</span>\n";
    echo "</div>\n";
    echo "</div>\n";
}
    
/*******************************
* FUNCTION: DISPLAY OWNER VIEW *
********************************/
function display_owner_view($owner)
{
    global $lang, $escaper;
 
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Owner']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"owner\" id=\"owner\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}    
    
/***************************************
* FUNCTION: DISPLAY OWNER MANAGER VIEW *
****************************************/
function display_owner_manager_view($manager)
{
    global $lang, $escaper;
 
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['OwnersManager']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"manager\" id=\"manager\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}
    
/**************************************
* FUNCTION: DISPLAY SUBMITTED BY VIEW *
***************************************/
function display_submitted_by_view($submitted_by)
{
    global $lang, $escaper;
 
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['SubmittedBy']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"submitted_by\" id=\"submitted_by\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $submitted_by)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $submitted_by)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/*************************************
* FUNCTION: DISPLAY RISK SOURCE VIEW *
**************************************/
function display_risk_source_view($source)
{
    global $lang, $escaper;
 
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['RiskSource']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"source\" id=\"source\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("source", $source)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("source", $source)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}
    
/*********************************************
* FUNCTION: DISPLAY RISK SCORING METHOD VIEW *
**********************************************/
function display_risk_scoring_method_view($scoring_method, $CLASSIC_likelihood="", $CLASSIC_impact="")
{
    global $lang, $escaper;
    
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
    
}

/*****************************************
* FUNCTION: DISPLAY RISK ASSESSMENT VIEW *
******************************************/
function display_risk_assessment_view($assessment)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['RiskAssessment']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<textarea style=\"cursor: default;\" name=\"assessment\" cols=\"50\" rows=\"3\" id=\"assessment\" title=\"" . $escaper->escapeHtml($assessment) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($assessment) . "</textarea>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/******************************************
* FUNCTION: DISPLAY ADDITIONAL NOTES VIEW *
*******************************************/
function display_additional_notes_view($notes)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['AdditionalNotes']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<textarea style=\"cursor: default;\" name=\"notes\" cols=\"50\" rows=\"3\" id=\"notes\" title=\"" . $escaper->escapeHtml($notes) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($notes) . "</textarea>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/**************************************************
* FUNCTION: DISPLAY SUPPORTING DOCUMENTATION VIEW *
***************************************************/
function display_supporting_documentation_view($risk_id, $view_type)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"wrap-text span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['SupportingDocumentation']) . ": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    supporting_documentation($risk_id, "view", $view_type);
    echo "</div>\n";
    echo "</div>\n";
}

/*********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN DETAILS VIEW *
**********************************************************/
function display_main_detail_fields_by_panel_view($panel_name, $fields, $risk_id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags)
{

    foreach($fields as $field)
    {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 1)
        {
            if($field['is_basic'] == 1)
            {
                if($field['active'] == 0){
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']){
                    case 'SubmissionDate':
                        display_submission_date_view($submission_date);
                    break;
                    
                    case 'Category':
                        display_category_view($category);
                    break;
                        
                    case 'SiteLocation':
                        display_site_location_view($location);
                    break;
                        
                    case 'ExternalReferenceId':
                        display_external_reference_id_view($reference_id);
                    break;
                        
                    case 'ControlRegulation':
                        display_control_regulation_view($regulation);
                    break;
                        
                    case 'ControlNumber':
                        display_control_number_view($control_number);
                    break;
                        
                    case 'AffectedAssets':
                        display_affected_assets_view($risk_id);
                    break;
                        
                    case 'Technology':
                        display_technology_view($technology);
                    break;
                        
                    case 'Team':
                        display_team_view($team);
                    break;
                        
                    case 'AdditionalStakeholders':
                        display_additional_stakeholders_view($additional_stakeholders);
                    break;
                    
                    case 'Owner':
                        display_owner_view($owner);
                    break;
                        
                    case 'OwnersManager':
                        display_owner_manager_view($manager);
                    break;
                    
                    case 'SubmittedBy':
                        display_submitted_by_view($submitted_by);
                    break;
                        
                    case 'RiskSource':
                        display_risk_source_view($source);
                    break;
                        
                    case 'RiskScoringMethod':
                        display_risk_scoring_method_view($scoring_method, $CLASSIC_likelihood, $CLASSIC_impact);
                    break;
                    
                    case 'RiskAssessment':
                        display_risk_assessment_view($assessment);
                    break;

                    case 'AdditionalNotes':
                        display_additional_notes_view($notes);
                    break;
                        
                    case 'SupportingDocumentation':
                        display_supporting_documentation_view($risk_id, 1);
                    break;

                    case 'Tags':
                        display_risk_tags_view($tags);
                    break;
                }

                if($field['active'] == 0){
                    echo "</div>";
                }
            }
            // If custom field
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                    
                    $custom_values = getCustomFieldValuesByRiskId($risk_id);
                    display_custom_field_risk_view($field, $custom_values);
                }
            }
        }
    }
}

/*****************************************
* FUNCTION: DISPLAY SUBMISSION DATE EDIT *
******************************************/
function display_submission_date_edit($submission_date)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['SubmissionDate']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"submission_date\"  size=\"50\" value=\"" . $escaper->escapeHtml($submission_date) . "\" title=\"" . $escaper->escapeHtml($submission_date) . "\" class=\"datepicker\" />\n"; 
    echo "</div>\n";
    echo "</div>\n";
}

/**********************************
* FUNCTION: DISPLAY CATEGORY EDIT *
***********************************/
function display_category_edit($category)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Category']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    create_dropdown("category", $category);
    echo "</div>\n";
    echo "</div>\n";
}

/**********************************
* FUNCTION: DISPLAY LOCATION EDIT *
***********************************/
function display_location_edit($location)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['SiteLocation']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    create_dropdown("location", $location);
    echo "</div>\n";
    echo "</div>\n";
}

/***********************************************
* FUNCTION: DISPLAY EXTERNAL REFERENCE ID EDIT *
************************************************/
function display_external_reference_id_edit($reference_id)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"wrap-text span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['ExternalReferenceId']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input type=\"text\" name=\"reference_id\" id=\"reference_id\" class=\"active-textfield\" size=\"20\" value=\"" . $escaper->escapeHtml($reference_id) . "\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}
    
/********************************************
* FUNCTION: DISPLAY CONTROL REGULATION EDIT *
*********************************************/
function display_control_regulation_edit($regulation)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['ControlRegulation']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    create_dropdown("frameworks", $regulation, "regulation");
    echo "</div>\n";
    echo "</div>\n";
}

/****************************************
* FUNCTION: DISPLAY CONTROL NUMBER EDIT *
*****************************************/
function display_control_number_edit($control_number)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['ControlNumber']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input type=\"text\" class=\"active-textfield\" name=\"control_number\" id=\"control_number\" size=\"20\" value=\"" . $escaper->escapeHtml($control_number) . "\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/*****************************************
* FUNCTION: DISPLAY AFFECTED ASSETS EDIT *
******************************************/
function display_affected_assets_edit($risk_id)
{
    global $lang, $escaper;
    $selected_ids = [];
    
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\" id=\"AffectedAssetsTitle\">\n";
    echo $escaper->escapeHtml($lang['AffectedAssets']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7 affected-assets\">\n";
    echo "<select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='" . $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']) . "'>";
    echo "</select>\n";
    echo "<span class='affected-assets-instructions'>" . $escaper->escapeHtml($lang['AffectedAssetsWidgetInstructions']) . "</span>";
    echo "</div>\n";
    echo "</div>\n";
}

/************************************
* FUNCTION: DISPLAY TECHNOLOGY EDIT *
*************************************/
function display_technology_edit($technology)
{
    global $lang, $escaper;
    
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Technology']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    $technology_values = ":".implode(":", explode(",", $technology)).":";
    create_multiple_dropdown("technology", $technology_values, NULL, NULL, false, "", "", true, " class='multiselect' ");
    echo "</div>\n";
    echo "</div>\n";
}

/******************************
* FUNCTION: DISPLAY TEAM EDIT *
*******************************/
function display_team_edit($team)
{
    global $lang, $escaper;
    
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Team']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    $team = ":".implode(":", explode(",", $team)).":";
    create_multiple_dropdown("team", $team, NULL, NULL, false, "", "", true, " class='multiselect' ");
//    create_dropdown("team", $team);
    echo "</div>\n";
    echo "</div>\n";
}

/*************************************************
* FUNCTION: DISPLAY ADDITIONAL STAKEHOLDERS EDIT *
**************************************************/
function display_additional_stakeholders_edit($additional_stakeholders)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['AdditionalStakeholders']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7 multiselect-holder\">\n";
    create_multiusers_dropdown("additional_stakeholders", $additional_stakeholders);
    echo "</div>\n";
    echo "</div>\n";
}

/*******************************
* FUNCTION: DISPLAY OWNER EDIT *
********************************/
function display_owner_edit($owner)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Owner']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    create_dropdown("enabled_users", $owner, "owner");
    echo "</div>\n";
    echo "</div>\n";
}

/****************************************
* FUNCTION: DISPLAY OWNERS MANAGER EDIT *
*****************************************/
function display_owners_manager_edit($manager)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['OwnersManager']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    create_dropdown("enabled_users", $manager, "manager");
    echo "</div>\n";
    echo "</div>\n";
}

/*************************************
* FUNCTION: DISPLAY RISK SOURCE EDIT *
**************************************/
function display_risk_source_edit($source)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['RiskSource']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    create_dropdown("source", $source);
    echo "</div>\n";
    echo "</div>\n";
}

/***********************************************
* FUNCTION: DISPLAY RISK ASSESSMENT TITLE EDIT *
************************************************/
function display_risk_assessment_title_edit($assessment)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\" id=\"RiskAssessmentTitle\">\n";
    echo $escaper->escapeHtml($lang['RiskAssessment']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<textarea class=\"active-textfield\" name=\"assessment\" cols=\"50\" rows=\"3\" id=\"assessment\">" . $escaper->escapeHtml($assessment) . "</textarea>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/******************************************
* FUNCTION: DISPLAY ADDITIONAL NOTES EDIT *
*******************************************/
function display_additional_notes_edit($notes)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\" id=\"NotesTitle\">\n";
    echo $escaper->escapeHtml($lang['AdditionalNotes']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<textarea name=\"notes\" class=\"active-textfield\" cols=\"50\" rows=\"3\" id=\"notes\">" . $escaper->escapeHtml($notes) . "</textarea>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/**************************************************
* FUNCTION: DISPLAY SUPPORTING DOCUMENTATION EDIT *
***************************************************/
function display_supporting_documentation_edit($risk_id, $view_type)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"wrap-text span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['SupportingDocumentation']) . ": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    supporting_documentation($risk_id, "edit", $view_type);
    echo "</div>\n";
    echo "</div>\n";
}

/*********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN DETAILS EDIT *
**********************************************************/
function display_main_detail_fields_by_panel_edit($panel_name, $fields, $risk_id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags)
{
    foreach($fields as $field)
    {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 1)
        {
            if($field['is_basic'] == 1)
            {
                if($field['active'] == 0){
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']){
                    case 'SubmissionDate':
                        display_submission_date_edit($submission_date);
                    break;
            
                    case 'Category':
                        display_category_edit($category);
                    break;
                    
                    case 'SiteLocation':
                        display_location_edit($location);
                    break;

                    case 'ExternalReferenceId':
                        display_external_reference_id_edit($reference_id);
                    break;
                    
                    case 'ControlRegulation':
                        display_control_regulation_edit($regulation);
                    break;
                        
                    case 'ControlNumber':
                        display_control_number_edit($control_number);
                    break;
                        
                    case 'AffectedAssets':
                        display_affected_assets_edit($risk_id);
                    break;
                    
                    case 'Technology':
                        display_technology_edit($technology);
                    break;
                        
                    case 'Team':
                        display_team_edit($team);
                    break;
                        
                    case 'AdditionalStakeholders':
                        display_additional_stakeholders_edit($additional_stakeholders);
                    break;
                    
                    case 'Owner':
                        display_owner_edit($owner);
                    break;
                        
                    case 'OwnersManager':
                        display_owners_manager_edit($manager);
                    break;
                    
                    case 'RiskSource':
                        display_risk_source_edit($source);
                    break;
                
                    case 'RiskScoringMethod':
                        risk_score_method_html($scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);
                    break;

                    case 'RiskAssessment':
                        display_risk_assessment_title_edit($assessment);
                    break;
                        
                    case 'AdditionalNotes':
                        display_additional_notes_edit($notes);
                    break;
                        
                    case 'SupportingDocumentation':
                        display_supporting_documentation_edit($risk_id, 1);  
                    break;

                    case 'Tags':
                        display_risk_tags_edit($tags);
                    break;
                }

                if($field['active'] == 0){
                    echo "</div>";
                }
            }
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id);
                    display_custom_field_edit($field, $custom_values);
                }
            }
          
        }
    }
}

/****************************************************
* FUNCTION: DISPLAY MITIGATION SUBMISSION DATE VIEW *
*****************************************************/
function display_mitigation_submission_date_view($mitigation_date)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['MitigationDate']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_date\" id=\"mitigation_date\" size=\"50\" value=\"" . $escaper->escapeHtml($mitigation_date) . "\" title=\"" . $escaper->escapeHtml($mitigation_date) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/**************************************************
* FUNCTION: DISPLAY MITIGATION PLANNING DATE VIEW *
***************************************************/
function display_mitigation_planning_date_view($planning_date)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['MitigationPlanning']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"planning_date\" id=\"planning_date\" size=\"50\" value=\"" . $escaper->escapeHtml($planning_date) . "\" title=\"" . $escaper->escapeHtml($planning_date) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/******************************************************
* FUNCTION: DISPLAY MITIGATION PLANNING STRATAGE VIEW *
*******************************************************/
function display_mitigation_planning_strategy_view($planning_strategy)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['PlanningStrategy']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"planning_strategy\" id=\"planning_strategy\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/*******************************************
* FUNCTION: DISPLAY MITIGATION EFFORT VIEW *
********************************************/
function display_mitigation_effort_view($mitigation_effort)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['MitigationEffort']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_effort\" id=\"mitigation_effort\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/*****************************************
* FUNCTION: DISPLAY MITIGATION COST VIEW *
******************************************/
function display_mitigation_cost_view($mitigation_cost)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['MitigationCost']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_cost\" id=\"mitigation_cost\" size=\"50\" value=\"" . $escaper->escapeHtml(get_asset_value_by_id($mitigation_cost)) . "\" title=\"" . $escaper->escapeHtml(get_asset_value_by_id($mitigation_cost)) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/******************************************
* FUNCTION: DISPLAY MITIGATION OWNER VIEW *
*******************************************/
function display_mitigation_owner_view($mitigation_owner)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['MitigationOwner']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<span>". $escaper->escapeHtml(get_name_by_value("user", $mitigation_owner)) ."</span>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/*****************************************
* FUNCTION: DISPLAY MITIGATION TEAM VIEW *
******************************************/
function display_mitigation_team_view($mitigation_team)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['MitigationTeam']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<span>".$escaper->escapeHtml(get_names_by_multi_values("team", $mitigation_team))."</span>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/********************************************
* FUNCTION: DISPLAY MITIGATION PERCENT VIEW *
*********************************************/
function display_mitigation_percent_view($mitigation_percent)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['MitigationPercent']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_percent\" id=\"mitigation_percent\" size=\"50\" value=\"" . $escaper->escapeHtml($mitigation_percent) . "%\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/*******************************************
* FUNCTION: DISPLAY ACCEPT MITIGATION VIEW *
********************************************/
function display_accept_mitigation_view($risk_id)
{
    global $lang, $escaper;

    $message = view_accepted_mitigations($risk_id);

    echo "<div class=\"row-fluid ".(!$message ? "hide" : "")."\" >\n";
        echo "<div class=\"span12 accept_mitigation_text\">\n";
            echo $message;
        echo "</div>\n";
    echo "</div>\n";

    // If user has able to accept mitigation permission
    if(!empty($_SESSION['accept_mitigation']))
    {
        // Get accepted mitigation by login user
        $accepted_mitigation = get_accpeted_mitigation($risk_id);

        echo "<div class=\"row-fluid accept-mitigation-container\" style=\"margin-bottom: 12px; ".($accepted_mitigation ? "display:none":"")."\">\n";
            echo "<div class=\"span5 text-right \">\n";
                echo "<button class='accept_mitigation'>".$escaper->escapeHtml($lang['AcceptMitigation'])."</button> \n";
            echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"reject-mitigation-container\" style=\"margin-bottom: 12px; ".($accepted_mitigation ? "":"display:none")."\">";
            
            echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span5 text-right \">\n";
                    echo "<button class='reject_mitigation'>".$escaper->escapeHtml($lang['RejectMitigation'])."</button> \n";
                echo "</div>\n";
            echo "</div>\n";
        echo "</div>";
    }
    
    echo "
        <script>
            if(typeof  called_accept_mitigation == 'undefined')
            {
                // Accept mitigation 
                $('body').on('click', '.accept_mitigation', function(e){
                    e.preventDefault();
                    var tabContainer = $(this).parents('.tab-data');
                    var risk_id = $('.large-text', tabContainer).html();
                    var self = $(this);
                    self.prop(\"disabled\", true);
                    self.html($(\"#_lang_accepting\").val());
                    
                    $.ajax({
                        type: \"POST\",
                        data: {
                            accept: 1
                        },
                        url: BASE_URL + \"/api/management/risk/accept_mitigation?id=\" + risk_id,
                        success: function(data){
                            $(\".accept-mitigation-container\", tabContainer).hide();
                            $(\".reject-mitigation-container\", tabContainer).show();
                            if(data.data.accept_mitigation_text){
                                $(\".accept_mitigation_text\", tabContainer).parent().show();
                                $(\".accept_mitigation_text\", tabContainer).html(data.data.accept_mitigation_text);
                            }
                            else{
                                $(\".accept_mitigation_text\", tabContainer).parent().hide();
                            }

                            self.prop(\"disabled\", false);
                            self.html($(\"#_lang_accept_mitigation\").val());
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                                self.prop(\"disabled\", false);
                                self.html($(\"#_lang_accept_mitigation\").val());
                            }
                        }
                    })
                })
                
                // Reject mitigation 
                $('body').on('click', '.reject_mitigation', function(e){
                    e.preventDefault();
                    var tabContainer = $(this).parents('.tab-data');
                    var risk_id = $('.large-text', tabContainer).html();
                    var self = $(this);
                    self.prop(\"disabled\", true);
                    self.html($(\"#_lang_rejecting\").val());
                    
                    $.ajax({
                        type: \"POST\",
                        data: {
                            accept: 0
                        },
                        url: BASE_URL + \"/api/management/risk/accept_mitigation?id=\" + risk_id,
                        success: function(data){
                            $(\".accept-mitigation-container\", tabContainer).show();
                            $(\".reject-mitigation-container\", tabContainer).hide();
                            if(data.data.accept_mitigation_text){
                                $(\".accept_mitigation_text\", tabContainer).parent().show();
                                $(\".accept_mitigation_text\", tabContainer).html(data.data.accept_mitigation_text);
                            }
                            else{
                                $(\".accept_mitigation_text\", tabContainer).parent().hide();
                            }
                            self.prop(\"disabled\", false);
                            self.html($(\"#_lang_reject_mitigation\").val());
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                                self.prop(\"disabled\", false);
                                self.html($(\"#_lang_reject_mitigation\").val());
                            }
                        }
                    })
                })            
            }
            called_accept_mitigation = true;
        </script>
    ";
}

/******************************************
* FUNCTION: DISPLAY CURRENT SOLUTION VIEW *
*******************************************/
function display_current_solution_view($current_solution)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\" id=\"CurrentSolutionTitle\">\n";
    echo $escaper->escapeHtml($lang['CurrentSolution']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<textarea style=\"cursor: default;\" name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\" title=\"" . $escaper->escapeHtml($current_solution) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($current_solution) . "</textarea>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/***********************************************
* FUNCTION: DISPLAY SECURITY REQUIREMENTS VIEW *
************************************************/
function display_security_requirements_view($security_requirements)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\" >\n";
    echo $escaper->escapeHtml($lang['SecurityRequirements']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<textarea style=\"cursor: default;\" name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\" title=\"" . $escaper->escapeHtml($security_requirements) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($security_requirements) . "</textarea>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/**************************************************
* FUNCTION: DISPLAY SECURITY RECOMMENDATIONS VIEW *
***************************************************/
function display_security_recommendations_view($security_recommendations)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\" >\n";
    echo $escaper->escapeHtml($lang['SecurityRecommendations']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<textarea style=\"cursor: default;\" name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\" title=\"" . $escaper->escapeHtml($security_recommendations) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($security_recommendations) . "</textarea>\n";
    echo "</div>\n";
    echo "</div>\n";
}


/************************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN MITIGATION VIEW *
*************************************************************/
function display_main_mitigation_fields_by_panel_view($panel_name, $fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls)
{

    foreach($fields as $field)
    {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 2)
        {
            // If main field
            if($field['is_basic'] == 1)
            {
                if($field['active'] == 0){
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']){
                    case 'MitigationDate':
                        display_mitigation_submission_date_view($mitigation_date);
                    break;
                    
                    case 'MitigationPlanning':
                        display_mitigation_planning_date_view($planning_date);    
                    break;
                        
                    case 'PlanningStrategy':
                        display_mitigation_planning_strategy_view($planning_strategy);
                    break;
                        
                    case 'MitigationEffort':
                        display_mitigation_effort_view($mitigation_effort);
                    break;
                        
                    case 'MitigationCost':
                        display_mitigation_cost_view($mitigation_cost);
                    break;
                        
                    case 'MitigationOwner':
                        display_mitigation_owner_view($mitigation_owner);
                    break;
                        
                    case 'MitigationTeam':
                        display_mitigation_team_view($mitigation_team);
                    break;
                        
                    case 'MitigationPercent':
                        display_mitigation_percent_view($mitigation_percent);
                    break;
                        
                    case 'AcceptMitigation':
                        display_accept_mitigation_view($risk_id);
                    break;
                        
                    case 'CurrentSolution':
                        display_current_solution_view($current_solution);
                    break;
                        
                    case 'SecurityRequirements':
                        display_security_requirements_view($security_requirements);
                    break;
                        
                    case 'SecurityRecommendations':
                        display_security_recommendations_view($security_recommendations);
                    break;
                        
                    case 'MitigationSupportingDocumentation':
                        display_supporting_documentation_view($risk_id, 2);
                    break;

                    case 'MitigationControlsList':
                        print_mitigation_controls_table($mitigation_controls);
                    break;
                }

                if($field['active'] == 0){
                    echo "</div>";
                }
            }
            // If custom field
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id);
                    display_custom_field_risk_view($field, $custom_values);
                }
            }
            
        }
    }
}

/***************************************
* FUNCTION: DISPLAY CUSTOM FIELD PRINT *
****************************************/
function display_custom_field_print($field, $custom_values, $review_id=0)
{
    global $lang, $escaper;

    $value = "";
    
    // Get value of custom filed
    foreach($custom_values as $custom_value)
    {
        if($custom_value['field_id'] == $field['id'] && $custom_value['review_id'] == $review_id){
            $value = $custom_value['value'];
            break;
        }
    }
    
    echo "<tr>\n";
        echo "<td >\n";
            echo "<b>". $escaper->escapeHtml($field['name']) .":</td>\n";
        echo "<td>\n";
            echo get_custom_field_name_by_value($field['id'], $field['type'], $value);
        echo "</td>\n";
    echo "</tr>";
    
}

/****************************************************
* FUNCTION: DISPLAY MITIGATION SUBMISSION DATE EDIT *
*****************************************************/
function display_mitigation_submission_date_edit($mitigation_date)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\">"
                .$escaper->escapeHtml($lang['MitigationDate']) .": \n
            </div>
            <div class=\"span7\">
                <input style=\"cursor: default;\" type=\"text\" name=\"mitigation_date\" id=\"mitigation_date\" size=\"50\" value=\"" . $escaper->escapeHtml($mitigation_date) . "\" title=\"" . $escaper->escapeHtml($mitigation_date) . "\" disabled=\"disabled\" />
            </div>
        </div>
    ";
}

/**************************************************
* FUNCTION: DISPLAY MITIGATION PLANNING DATE EDIT *
***************************************************/
function display_mitigation_planning_date_edit($planning_date)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\">"
                .$escaper->escapeHtml($lang['MitigationPlanning']) .": \n
            </div>
            <div class=\"span7\">
                <input type=\"text\" name=\"planning_date\"  size=\"50\" value=\"" . $escaper->escapeHtml($planning_date) . "\" class='datepicker active-textfield' />
            </div>
        </div>
    ";
}

/******************************************************
* FUNCTION: DISPLAY MITIGATION PLANNING STRATEGY EDIT *
*******************************************************/
function display_mitigation_planning_strategy_edit($planning_strategy)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\">"
                .$escaper->escapeHtml($lang['PlanningStrategy']) .": \n
            </div>
            <div class=\"span7\">";
                create_dropdown("planning_strategy", $planning_strategy);
            echo "</div>
        </div>
    ";
}

/*******************************************
* FUNCTION: DISPLAY MITIGATION EFFORT EDIT *
********************************************/
function display_mitigation_effort_edit($mitigation_effort)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\">"
                .$escaper->escapeHtml($lang['MitigationEffort']) .": 
            </div>
            <div class=\"span7\">";
                create_dropdown("mitigation_effort", $mitigation_effort);
        echo "</div>
        </div>
    ";
}

/*****************************************
* FUNCTION: DISPLAY MITIGATION COST EDIT *
******************************************/
function display_mitigation_cost_edit($mitigation_cost)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\">"
                .$escaper->escapeHtml($lang['MitigationCost']) .": 
            </div>
            <div class=\"span7\">";
                echo create_asset_valuation_dropdown("mitigation_cost", $mitigation_cost);
        echo "</div>
        </div>
    ";
}

/******************************************
* FUNCTION: DISPLAY MITIGATION OWNER EDIT *
*******************************************/
function display_mitigation_owner_edit($mitigation_owner)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\">"
                .$escaper->escapeHtml($lang['MitigationOwner']) .": 
            </div>
            <div class=\"span7\">";
                create_dropdown("enabled_users", $mitigation_owner, "mitigation_owner", true);
        echo "</div>
        </div>
    ";
}

/*****************************************
* FUNCTION: DISPLAY MITIGATION TEAM EDIT *
******************************************/
function display_mitigation_team_edit($mitigation_team)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\">"
                .$escaper->escapeHtml($lang['MitigationTeam']) .": 
            </div>
            <div class=\"span7\">";
                $mitigation_team_values = ":".implode(":", explode(",", $mitigation_team)).":";
//                create_dropdown("team", $mitigation_team, "mitigation_team", true);
                create_multiple_dropdown("team", $mitigation_team_values, "mitigation_team", NULL, false, "", "", true, " class='multiselect' ");
            echo "</div>
        </div>
    ";
}

/********************************************
* FUNCTION: DISPLAY MITIGATION PERCENT EDIT *
*********************************************/
function display_mitigation_percent_edit($mitigation_percent)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\">"
                .$escaper->escapeHtml($lang['MitigationPercent']) .": 
            </div>
            <div class=\"span7\">";
                echo "<input type=\"number\" min=\"0\" max=\"100\" name=\"mitigation_percent\" id=\"mitigation_percent\" size=\"50\" value=\"" . $escaper->escapeHtml($mitigation_percent) . "\" class='percent active-textfield' />";
            echo "</div>
        </div>
    ";
}

/*********************************************
* FUNCTION: DISPLAY MITIGATION CONTROLS EDIT *
**********************************************/
function display_mitigation_controls_edit($mitigation_controls)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\">"
                .$escaper->escapeHtml($lang['MitigationControls']) .": 
            </div>
            <div class=\"span7 text-left\">";
                mitigation_controls_dropdown($mitigation_controls);
            echo "</div>
         </div>
    ";
}

/******************************************
* FUNCTION: DISPLAY CURRENT SOLUTION EDIT *
*******************************************/
function display_current_solution_edit($current_solution)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\" id=\"CurrentSolutionTitle\">"
                .$escaper->escapeHtml($lang['CurrentSolution']) .": 
            </div>
            <div class=\"span7\">
                <textarea  class=\"active-textfield\" name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\" tabindex=\"1\">" . $escaper->escapeHtml($current_solution) . "</textarea>
            </div>
        </div>
    ";
}

/***********************************************
* FUNCTION: DISPLAY SECURITY REQUIREMENTS EDIT *
************************************************/
function display_security_requirements_edit($security_requirements)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\" id=\"SecurityRequirementsTitle\">"
                .$escaper->escapeHtml($lang['SecurityRequirements']) .": 
            </div>
            <div class=\"span7\">
                <textarea class=\"active-textfield\" name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\" tabindex=\"1\">" . $escaper->escapeHtml($security_requirements) . "</textarea>
            </div>
        </div>
    ";
}

/**************************************************
* FUNCTION: DISPLAY SECURITY RECOMMENDATIONS EDIT *
***************************************************/
function display_security_recommendations_edit($security_recommendations)
{
    global $lang, $escaper;

    echo "
        <div class=\"row-fluid\">
            <div class=\"span5 text-right\" id=\"SecurityRecommendationsTitle\">"
                .$escaper->escapeHtml($lang['SecurityRecommendations']) .": 
            </div>
            <div class=\"span7\">
                <textarea class=\"active-textfield\" name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\" tabindex=\"1\">" . $escaper->escapeHtml($security_recommendations) . "</textarea>
            </div>
        </div>
    ";
}

/************************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN MITIGATION EDIT *
*************************************************************/
function display_main_mitigation_fields_by_panel_edit($panel_name, $fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls)
{

    foreach($fields as $field)
    {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 2)
        {
            if($field['is_basic'] == 1)
            {
                if($field['active'] == 0){
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']){
                    case 'MitigationDate':
                        display_mitigation_submission_date_edit($mitigation_date);
                    break;
                    
                    case 'MitigationPlanning':
                        display_mitigation_planning_date_edit($planning_date);    
                    break;
                        
                    case 'PlanningStrategy':
                        display_mitigation_planning_strategy_edit($planning_strategy);
                    break;
                        
                    case 'MitigationEffort':
                        display_mitigation_effort_edit($mitigation_effort);
                    break;
                        
                    case 'MitigationCost':
                        display_mitigation_cost_edit($mitigation_cost);
                    break;
                        
                    case 'MitigationOwner':
                        display_mitigation_owner_edit($mitigation_owner);
                    break;
                        
                    case 'MitigationTeam':
                        display_mitigation_team_edit($mitigation_team);
                    break;
                        
                    case 'MitigationPercent':
                        display_mitigation_percent_edit($mitigation_percent);
                    break;
                        
                    case 'CurrentSolution':
                        display_current_solution_edit($current_solution);
                    break;
                        
                    case 'SecurityRequirements':
                        display_security_requirements_edit($security_requirements);
                    break;
                        
                    case 'SecurityRecommendations':
                        display_security_recommendations_edit($security_recommendations);
                    break;
                        
                    case 'MitigationSupportingDocumentation':
                        display_supporting_documentation_edit($risk_id, 2);
                    break;

                    case 'MitigationControls':
                        display_mitigation_controls_edit($mitigation_controls);
                    break;

                    case 'MitigationControlsList':
                        // Add controls table html
                        print_mitigation_controls_table($mitigation_controls);

                        // Add javascript code for mitigation controls
                        display_mitigation_controls_script();
                    break;
                }

                if($field['active'] == 0){
                    echo "</div>";
                }
            }
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id);
                    display_custom_field_edit($field, $custom_values);
                }
            }


        }
    }
}

/*************************************
* FUNCTION: DISPLAY REVIEW DATE VIEW *
**************************************/
function display_review_date_view($review_date)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['ReviewDate']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"review_date\" id=\"review_date\" size=\"100\" value=\"" . $escaper->escapeHtml($review_date) . "\" title=\"" . $escaper->escapeHtml($review_date) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/**********************************
* FUNCTION: DISPLAY REVIEWER VIEW *
***********************************/
function display_reviewer_view($reviewer)
{
    global $lang, $escaper;

    $reviewer_name = get_name_by_value("user", $reviewer);
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Reviewer']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"reviewer\" id=\"reviewer\" size=\"100\" value=\"" . $escaper->escapeHtml($reviewer_name) . "\" title=\"" . $escaper->escapeHtml($reviewer_name) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/********************************
* FUNCTION: DISPLAY REVIEW VIEW *
*********************************/
function display_review_view($review)
{
    global $lang, $escaper;

    $review_value = get_name_by_value("review", $review);
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Review']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"review\" id=\"review\" size=\"100\" value=\"" . $escaper->escapeHtml($review_value) . "\" title=\"" . $escaper->escapeHtml($review_value) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/***********************************
* FUNCTION: DISPLAY NEXT STEP VIEW *
************************************/
function display_next_step_view($next_step_value, $risk_id)
{
    global $lang, $escaper;

    $next_step = get_name_by_value("next_step", $next_step_value);
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['NextStep']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"next_step\" id=\"next_step\" size=\"100\" value=\"" . $escaper->escapeHtml($next_step) . "\" title=\"" . $escaper->escapeHtml($next_step) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";

    if ($next_step_value == 2) {
        $project = get_project_by_risk_id($risk_id);
        $project_name = $project? $project['name'] : $lang['UnassignedRisks'];
        if ($project_name) {
            echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span5 text-right\">\n";
            echo $escaper->escapeHtml($lang['ProjectName']) .": \n";
            echo "</div>\n";
            echo "<div class=\"span7\">\n";
            echo "<input style=\"cursor: default;\" type=\"text\" name=\"project_name\" id=\"project_name\" size=\"100\" value=\"" . $escaper->escapeHtml($project_name) . "\" title=\"" . $escaper->escapeHtml($project_name) . "\" disabled=\"disabled\" />\n";
            echo "</div>\n";
            echo "</div>\n";
        }
    }
}

/******************************************
* FUNCTION: DISPLAY NEXT REVIEW DATE VIEW *
*******************************************/
function display_next_review_date_view($next_review)
{
    global $lang, $escaper;
    
    if(!$next_review){
        $next_review = "";
    }
    
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['NextReviewDate']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<input style=\"cursor: default;\" type=\"text\" name=\"next_review\" id=\"next_review\" size=\"100\" value=\"" . $escaper->escapeHtml($next_review) . "\" title=\"" . $escaper->escapeHtml($next_review) . "\" disabled=\"disabled\" />\n";
    echo "</div>\n";
    echo "</div>\n";
}

/**********************************
* FUNCTION: DISPLAY COMMENTS VIEW *
***********************************/
function display_comments_view($comment)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Comment']) .": \n";
    echo "</div>\n";
    echo "<div class=\"span7\">\n";
    echo "<textarea style=\"cursor: default;\" name=\"comment\" cols=\"100\" rows=\"3\" title=\"" . $escaper->escapeHtml($comment) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($comment) . "</textarea>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN REVIEW VIEW *
*********************************************************/
function display_main_review_fields_by_panel_view($panel_name, $fields, $risk_id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comment)
{

    foreach($fields as $field)
    {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 3)
        {
            // If main field
            if($field['is_basic'] == 1)
            {
                if($field['active'] == 0){
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']){
                    case 'ReviewDate':
                        display_review_date_view($review_date);
                    break;
                    
                    case 'Reviewer':
                        display_reviewer_view($reviewer);
                    break;

                    case 'Review':
                        display_review_view($review);
                    break;
                        
                    case 'NextStep':
                        display_next_step_view($next_step, $risk_id);
                    break;
                        
                    case 'NextReviewDate':
                        display_next_review_date_view($next_review);
                    break;

                    case 'Comment':
                        display_comments_view($comment);
                    break;
                    
                }

                if($field['active'] == 0){
                    echo "</div>";
                }
            }
            // If custom field
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id, false, $review_id);
                    display_custom_field_risk_view($field, $custom_values, $review_id);
                }
            }
        
        }
    }
}

/*************************************
* FUNCTION: DISPLAY REVIEW DATE EDIT *
**************************************/
function display_review_date_edit()
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['ReviewDate']) .":</div>\n";
    echo "<div class=\"span7 reviewdate\">\n";
    echo date(get_default_date_format());
    echo "</div></div>\n";
}

/*************************************
* FUNCTION: DISPLAY REVIEW NAME EDIT *
**************************************/
function display_reviewer_name_edit()
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Reviewer']) .":</div>";
    echo "<div class=\"span7 reviewername\">\n";
    echo $escaper->escapeHtml($_SESSION['name']);
    echo "</div></div>\n";
}

/********************************
* FUNCTION: DISPLAY REVIEW EDIT *
*********************************/
function display_review_edit($review)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['Review']) .":</div>";
    echo "<div class=\"span7\">\n";
    create_dropdown("review", $review, NULL, true);
    echo "</div></div>\n";
}

/********************************
* FUNCTION: DISPLAY REVIEW EDIT *
*********************************/
function display_next_step_edit($next_step)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['NextStep']) .":</div>";
    echo "<div class=\"span7\">\n";
    create_dropdown("next_step", $next_step, NULL, true);
    echo "</div></div>\n";

    // Projects
    $project = get_project_by_risk_id($_GET['id']);
    $project_id = $project? $project['value'] : false;

    // If Next Step is Consider for Project (value=2), show project list
    if($next_step == 2)
    {
        echo "<div class=\"row-fluid project-holder\">\n";
    }
    else
    {
        echo "<div class=\"row-fluid project-holder\" style=\"display:none;\">\n";
    }
    echo "<div class=\"span5 text-right\">\n";
    echo $escaper->escapeHtml($lang['ProjectName']) .":</div>";
    echo "<div class=\"span7\">\n";
    create_dropdown("projects", $project_id, "project", false);

    echo "<span class='project-instructions'>{$escaper->escapeHtml($lang['ReviewProjectSelectionInstructions'])}</span></div>

    </div>\n";
    
    echo "
        <script>
            $(document).ready(function(){
                $('#project').selectize({
                    create: true,
                    //sortField: 'text',
                    addPrecedence: true,
                    placeholder: '{$escaper->escapeJS($lang['ReviewProjectSelectionPlaceholder'])}',
                    sortField: 'value',
                    create: function(input) {return { 'value': 'new-projval-prfx-' + input, 'text': input }; }
                });";
    if ($project_id === false) {
        echo "
                $('#project')[0].selectize.clear();";
    }
    echo "
            })
        </script>
        <style>
            .project-instructions {
                font-size: 0.8em;
                color: red;
                position:relative;
                top: -20px;
            }
        </style>
    ";
}

/********************************
* FUNCTION: DISPLAY REVIEW EDIT *
*********************************/
function display_comments_edit($comments)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span5 text-right\" id=\"CommentsTitle\">\n";;
    echo $escaper->escapeHtml($lang['Comments']) .":</div>";
    echo "<div class=\"span7\">\n";
    echo "<textarea name=\"comments\" cols=\"50\" rows=\"3\" id=\"comments\">" . $escaper->escapeHtml($comments) . "</textarea>\n";
    echo "</div>\n";
    echo "</div>\n";

}

/**********************************************
* FUNCTION: DISPLAY SET NEXT REVIEW DATE EDIT *
***********************************************/
function display_set_next_review_date_edit($default_next_review)
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span5 text-left\"> </div>";
        echo "<div class=\"span7 text-left\">";

            echo '<strong class="small-text">'.$escaper->escapeHtml($lang['BasedOnTheCurrentRiskScore']) . $escaper->escapeHtml($default_next_review) . "<br />\n";
            echo $escaper->escapeHtml($lang['WouldYouLikeToUseADifferentDate']).'</strong>';

            echo "<div class=\"clearfix radio-buttons-holder radio-padded-top-bottom\">";
                echo "<div class=\"pull-left active-textfield\"><input type=\"radio\" name=\"custom_date\" value=\"no\" id=\"no\" class=\"hidden-radio\" checked /> <label for=\"no\">".$escaper->escapeHtml($lang['No'])."</label></div>";
                echo "<div class=\"pull-left radio-padded-right\"><input type=\"radio\" name=\"custom_date\" value=\"yes\" id=\"yes\" class=\"hidden-radio\" /><label for=\"yes\">".$escaper->escapeHtml($lang['Yes'])."</label></div>";
            echo "</div>";
        echo "</div>";
    echo "</div>";

    echo "<div id=\"nextreview\" class=\"nextreview row-fluid\" style=\"display:none;\">\n";
        echo "<div class=\"span5 text-right\">\n";
            echo $escaper->escapeHtml($lang['NextReviewDate']) .": \n";
        echo "</div>\n";
        echo "<div class=\"span7\">\n";
        //echo "<input type=\"date\" name=\"next_review\" value=\"" . $escaper->escapeHtml($next_review) . "\" />\n";
            echo "<input type=\"text\" class=\"datepicker active-textfield\" name=\"next_review\" id=\"nextreviewvalue\" value=\"" . $escaper->escapeHtml($default_next_review) . "\" />\n";
        echo "</div>\n";
    echo "</div>\n";

}

/********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN REVIEW EDIT *
*********************************************************/
function display_main_review_fields_by_panel_edit($panel_name, $fields, $risk_id, $review_id, $review, $next_step, $next_review, $comment, $default_next_review)
{

    foreach($fields as $field)
    {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 3)
        {
            if($field['is_basic'] == 1)
            {

                if($field['active'] == 0){
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']){
                    case 'ReviewDate':
                        display_review_date_edit();
                    break;
                    
                    case 'Reviewer':
                        display_reviewer_name_edit();
                    break;

                    case 'Review':
                        display_review_edit($review);
                    break;
                        
                    case 'NextStep':
                        display_next_step_edit($next_step);
                    break;
                        
                    case 'Comment':
                        display_comments_edit($comment);
                    break;
                    
                    case 'SetNextReviewDate':
                        display_set_next_review_date_edit($default_next_review);
                    break;
                    
                }

                if($field['active'] == 0){
                    echo "</div>";
                }
                
            }
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id, false, $review_id);
                    display_custom_field_edit($field, $custom_values);
                }
            }
        }
    }
}

/*************************************************
* FUNCTION: DISPLAY SUPPORTING DOCUMENTATION ADD *
**************************************************/
function display_supporting_documentation_add()
{
    global $lang, $escaper;

    echo "<div class=\"row-fluid\">";
        echo "<div class=\"wrap-text span5 text-right\">".$escaper->escapeHtml($lang['SupportingDocumentation'])."</div>";
        echo "<div class=\"span7\">";

            echo "<div class=\"file-uploader\">";
                echo "<label for=\"file-upload\" class=\"btn\">".$escaper->escapeHtml($lang['ChooseFile'])."</label>";
                echo "<span class=\"file-count-html\"> <span class=\"file-count\">0</span> ".$escaper->escapeHtml($lang['FileAdded'])."</span>";
                echo "<p><font size=\"2\"><strong>Max ".round(get_setting('max_upload_size')/1024/1024)." Mb</strong></font></p>";
                echo "<ul class=\"file-list\">";

                echo "</ul>";
                echo "<input type=\"file\" id=\"file-upload\" name=\"file[]\" class=\"hidden-file-upload active\" />";
            echo "</div>";

        echo "</div>";
    echo "</div>";
}


/************************************
 * FUNCTION: DISPLAY RISK TAGS EDIT *
 ************************************/
function display_risk_tags_edit($tags = "")
{
    global $lang, $escaper;

    echo "  <div class=\"row-fluid\">";
    echo "      <div class=\"span10 hero-unit\">";
    echo "          <div class=\"row-fluid\">";
    echo "              <div class=\"wrap-text span1 text-left\"><strong>".$escaper->escapeHtml($lang['Tags'])."</strong></div>";
    echo "          </div>";
    echo "          <div class=\"row-fluid\">";
    echo "              <div class=\"span12\">";
    echo "                  <input type=\"text\" readonly id=\"tags\" name=\"tags\" value=\"" . $escaper->escapeHtml($tags) . "\">
                            <script>
                                $('#tags').selectize({
                                    plugins: ['remove_button', 'restore_on_backspace'],
                                    delimiter: ',',
                                    create: true,
                                    valueField: 'label',
                                    labelField: 'label',
                                    searchField: 'label',
                                    preload: true,
                                    load: function(query, callback) {
                                        if (query.length) return callback();
                                        $.ajax({
                                            url: '/api/management/tag_options_of_type?type=risk',
                                            type: 'GET',
                                            dataType: 'json',
                                            error: function() {
                                                console.log('Error loading!');
                                                callback();
                                            },
                                            success: function(res) {
                                                callback(res.data);
                                            }
                                        });
                                    }
                                });
                            </script>";
    echo "              </div>";
    echo "          </div>";
    echo "      </div>";
    echo "  </div>";
}

/************************************
 * FUNCTION: DISPLAY RISK TAGS VEIW *
 ************************************/
function display_risk_tags_view($tags)
{
    global $lang, $escaper;

    echo "  <div class=\"row-fluid\">";
    echo "      <div class=\"span10 hero-unit\">";
    echo "          <div class=\"row-fluid\">";
    echo "              <div class=\"wrap-text span1 text-left\"><strong>".$escaper->escapeHtml($lang['Tags'])."</strong></div>";
    echo "          </div>";
    echo "          <div class=\"row-fluid\">";
    echo "              <div class=\"span12\">";
    if ($tags) {
        foreach(explode(",", $tags) as $tag) {
            echo "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin-right:2px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
        }
    } else {
        echo $escaper->escapeHtml($lang['NoTagAssigned']);
    }
    echo "              </div>";
    echo "          </div>";
    echo "      </div>";
    echo "  </div>";
}


/********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN DETAILS ADD *
*********************************************************/
function display_main_detail_fields_by_panel_add($panel_name, $fields)
{
    foreach($fields as $field)
    {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 1)
        {
            if($field['is_basic'] == 1)
            {
                if($field['active'] == 0){
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']){
                    case 'Category':
                        display_category_edit('');
                    break;
                    
                    case 'SiteLocation':
                        display_location_edit('');
                    break;

                    case 'ExternalReferenceId':
                        display_external_reference_id_edit('');
                    break;
                    
                    case 'ControlRegulation':
                        display_control_regulation_edit('');
                    break;
                        
                    case 'ControlNumber':
                        display_control_number_edit('');
                    break;
                        
                    case 'AffectedAssets':
                        display_affected_assets_edit('');
                    break;
                    
                    case 'Technology':
                        display_technology_edit('');
                    break;
                        
                    case 'Team':
                        display_team_edit('');
                    break;
                        
                    case 'AdditionalStakeholders':
                        display_additional_stakeholders_edit('');
                    break;
                    
                    case 'Owner':
                        display_owner_edit('');
                    break;
                        
                    case 'OwnersManager':
                        display_owners_manager_edit('');
                    break;
                    
                    case 'RiskSource':
                        display_risk_source_edit('');
                    break;
                
                    case 'RiskScoringMethod':
                        risk_score_method_html();
                    break;

                    case 'RiskAssessment':
                        display_risk_assessment_title_edit('');
                    break;
                        
                    case 'AdditionalNotes':
                        display_additional_notes_edit('');
                    break;
                        
                    case 'SupportingDocumentation':
                        display_supporting_documentation_add();  
                    break;

                    case 'Tags':
                        display_risk_tags_edit();
                    break;
                }

                if($field['active'] == 0){
                    echo "</div>";
                }
            }
            else
            {
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    display_custom_field_edit($field, []);
                }
            }
        }
    }
}



?>
