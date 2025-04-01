<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

require_once(realpath(__DIR__ . '/functions.php'));

/*****************************************
* FUNCTION: DISPLAY SUBMISSION_DATE VIEW *
******************************************/
function display_submission_date_view($submission_date, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SubmissionDate']) . ":</label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($submission_date) . "
            </div>
        </div>
    ";
}

/**********************************
* FUNCTION: DISPLAY CATEGORY VIEW *
***********************************/
function display_category_view($category, $panel_name="")
{
    
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Category']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("category", $category)) . "
            </div>
        </div>
    ";
}

/***************************************
* FUNCTION: DISPLAY SITE LOCATION VIEW *
****************************************/
function display_site_location_view($location, $panel_name="")
{

    global $lang, $escaper;

   if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SiteLocation']) . ":</label> 
            </div>
            <div class='{$span2}'>" .
                $escaper->escapeHtml(get_names_by_multi_values("location", $location, false, "; ")) . "
            </div>
        </div>
    ";
}

/***********************************************
* FUNCTION: DISPLAY EXTERNAL REFERENCE ID VIEW *
************************************************/
function display_external_reference_id_view($reference_id, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='wrap-text {$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ExternalReferenceId']) . ":</label>
            </div>
            <div class='{$span2}'>" .
                $escaper->escapeHtml($reference_id) . "
            </div>
        </div>
    ";
}

/********************************************
* FUNCTION: DISPLAY CONTROL REGULATION VIEW *
*********************************************/
function display_control_regulation_view($regulation, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ControlRegulation']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("frameworks", $regulation)) . "
            </div>
        </div>
    ";
}

/****************************************
* FUNCTION: DISPLAY CONTROL NUMBER VIEW *
*****************************************/
function display_control_number_view($control_number, $panel_name="")
{
     
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ControlNumber']) . ":</label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($control_number) . "
            </div>
        </div>
    ";
}

/*****************************************
* FUNCTION: DISPLAY AFFECTED ASSETS VIEW *
******************************************/
function display_affected_assets_view($risk_id, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['AffectedAssets']) . ":</label>
            </div>
            <div class='{$span2}'>
    ";

    $data = get_assets_and_asset_groups_of_type($risk_id, 'risk', true);
    
    if ($data) {

        echo "
                <select class='assets-asset-groups-select-disabled' multiple >
        ";

        foreach($data as $item) {

            echo "
                    <option data-data='" . json_encode(array('class' => $item['class'])) . "' selected>" . $escaper->escapeHtml($item['name']) . "</option>
            ";

        }

        echo "
                </select>
        ";
    }

    echo "
            </div>
        </div>
    ";
}

/************************************
* FUNCTION: DISPLAY TECHNOLOGY VIEW *
*************************************/
function display_technology_view($technology, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Technology']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_technology_names($technology)) . "
            </div>
        </div>
    ";
}
    
/******************************
* FUNCTION: DISPLAY TEAM VIEW *
*******************************/
function display_team_view($team, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Team']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_names_by_multi_values("team", $team)) . "
            </div>
        </div>
    ";
}    
    
/*************************************************
* FUNCTION: DISPLAY ADDITIONAL STAKEHOLDERS VIEW *
**************************************************/
function display_additional_stakeholders_view($additional_stakeholders, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }
    
    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['AdditionalStakeholders']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_stakeholder_names($additional_stakeholders)) . "
            </div>
        </div>
    ";
}
    
/*******************************
* FUNCTION: DISPLAY OWNER VIEW *
********************************/
function display_owner_view($owner, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Owner']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("user", $owner)) . "
            </div>
        </div>
    ";
}    
    
/***************************************
* FUNCTION: DISPLAY OWNER MANAGER VIEW *
****************************************/
function display_owner_manager_view($manager, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['OwnersManager']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("user", $manager)) . "
            </div>
        </div>
    ";
}
    
/**************************************
* FUNCTION: DISPLAY SUBMITTED BY VIEW *
***************************************/
function display_submitted_by_view($submitted_by, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SubmittedBy']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("user", $submitted_by)) . "
            </div>
        </div>
    ";
}

/*************************************
* FUNCTION: DISPLAY RISK SOURCE VIEW *
**************************************/
function display_risk_source_view($source, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['RiskSource']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("source", $source)) . "
            </div>
        </div>
    ";
}
    
/*********************************************
* FUNCTION: DISPLAY RISK SCORING METHOD VIEW *
**********************************************/
function display_risk_scoring_method_view($scoring_method, $CLASSIC_likelihood="", $CLASSIC_impact="", $panel_name="")
{
    
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['RiskScoringMethod']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("scoring_methods", $scoring_method)) . "
            </div>
        </div>
    ";
    
    if($scoring_method == "1") {

        echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['CurrentLikelihood']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("likelihood", $CLASSIC_likelihood)) . "
            </div>
        </div>
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['CurrentImpact']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("impact", $CLASSIC_impact)) . "
            </div>
        </div>
        ";
    }
    
}

/*****************************************
* FUNCTION: DISPLAY RISK ASSESSMENT VIEW *
******************************************/
function display_risk_assessment_view($assessment , $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['RiskAssessment']) . ": </label>
            </div>
            <div class='{$span2} rich-text-container risk-details-assessment'>" . 
                $escaper->purifyHtml($assessment) . "
            </div>
        </div>
    ";
}

/******************************************
* FUNCTION: DISPLAY ADDITIONAL NOTES VIEW *
*******************************************/
function display_additional_notes_view($notes, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['AdditionalNotes']) . ": </label>
            </div>
            <div class='{$span2} rich-text-container risk-details-additional-notes'>" . 
                $escaper->purifyHtml($notes) . "
            </div>
        </div>
    ";
}

/******************************************
* FUNCTION: DISPLAY ADDITIONAL NOTES VIEW *
*******************************************/
function display_jira_issue_key_view($jira_issue_key, $panel_name="")
{
    // We're not displaying anything if the extra isn't turned on
    if (!jira_extra())
        return;

    global $lang, $escaper;

    if ($jira_issue_key) {
        //At this point we don't even have to validate
        preg_match('/^([A-Z][A-Z_0-9]+)-[0-9][0-9]*$/', $jira_issue_key, $matches);
        $project_key = $matches[1];
    }

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['JiraIssueKey']) . ": </label>
            </div>
            <div class='{$span2}' style='margin-top: 5px;'>
                <strong style='cursor: default;'>" . $escaper->escapeHtml($jira_issue_key) . "</strong>
    ";

    if ($jira_issue_key) {
        echo "
                <a href='" . get_setting('JiraInstanceURL') . "projects/{$project_key}/issues/{$jira_issue_key}' target='_blank' class='btn btn-default btn-sm' style='margin-left: 10px;'>" . $escaper->escapeHtml($lang['Open']) . "</a>
        ";
    }

    echo "
            </div>
        </div>
    ";
}

/**************************************************
* FUNCTION: DISPLAY SUPPORTING DOCUMENTATION VIEW *
***************************************************/
function display_supporting_documentation_view($risk_id, $view_type, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SupportingDocumentation']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                supporting_documentation($risk_id, "view", $view_type);
    echo "  
            </div>
        </div>
    ";
}
/**************************************
* FUNCTION: DISPLAY RISK MAPPING VIEW *
***************************************/
function display_risk_mapping_view($risk_catalog_mapping, $panel_name="") {

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['RiskMapping']) . ":</label>
            </div>
            <div class='{$span2}'>" .
                $escaper->escapeHtml(get_names_by_multi_values("risk_catalog", $risk_catalog_mapping, false, ", ", true)) . "
            </div>
        </div>
    ";
}
/****************************************
* FUNCTION: DISPLAY THREAT MAPPING VIEW *
*****************************************/
function display_threat_mapping_view($threat_catalog_mapping, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ThreatMapping']) . ":</label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_names_by_multi_values("threat_catalog", $threat_catalog_mapping, false, ", ", true)) . "
            </div>
        </div>
    ";
}

/*********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN DETAILS VIEW *
**********************************************************/
function display_main_detail_fields_by_panel_view($panel_name, $fields, $risk_id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping)
{

    foreach($fields as $field) {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 1) {
            if($field['is_basic'] == 1) {
                if($field['active'] == 0) {
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']) {
                    case 'SubmissionDate':
                        display_submission_date_view($submission_date, $panel_name);
                        break;
                    
                    case 'Category':
                        display_category_view($category, $panel_name);
                        break;
                        
                    case 'SiteLocation':
                        display_site_location_view($location, $panel_name);
                        break;
                        
                    case 'ExternalReferenceId':
                        display_external_reference_id_view($reference_id, $panel_name);
                        break;
                        
                    case 'ControlRegulation':
                        display_control_regulation_view($regulation, $panel_name);
                        break;
                        
                    case 'ControlNumber':
                        display_control_number_view($control_number, $panel_name);
                        break;
                        
                    case 'AffectedAssets':
                        display_affected_assets_view($risk_id, $panel_name);
                        break;
                        
                    case 'Technology':
                        display_technology_view($technology, $panel_name);
                        break;
                        
                    case 'Team':
                        display_team_view($team, $panel_name);
                        break;
                        
                    case 'AdditionalStakeholders':
                        display_additional_stakeholders_view($additional_stakeholders, $panel_name);
                        break;
                    
                    case 'Owner':
                        display_owner_view($owner, $panel_name);
                        break;
                        
                    case 'OwnersManager':
                        display_owner_manager_view($manager, $panel_name);
                        break;
                    
                    case 'SubmittedBy':
                        display_submitted_by_view($submitted_by, $panel_name);
                        break;
                        
                    case 'RiskSource':
                        display_risk_source_view($source, $panel_name);
                        break;
                        
                    case 'RiskScoringMethod':
                        display_risk_scoring_method_view($scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $panel_name);
                        break;
                    
                    case 'RiskAssessment':
                        display_risk_assessment_view($assessment, $panel_name);
                        break;

                    case 'AdditionalNotes':
                        display_additional_notes_view($notes, $panel_name);
                        break;

                    case 'JiraIssueKey':
                        display_jira_issue_key_view($jira_issue_key, $panel_name);
                        break;

                    case 'SupportingDocumentation':
                        display_supporting_documentation_view($risk_id, 1, $panel_name);
                        break;

                    case 'Tags':
                        display_risk_tags_view($tags, $panel_name);
                        break;

                    case 'RiskMapping':
                        display_risk_mapping_view($risk_catalog_mapping, $panel_name);
                        break;

                    case 'ThreatMapping':
                        display_threat_mapping_view($threat_catalog_mapping, $panel_name);
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
                    display_custom_field_risk_view($field, $custom_values, 0, $panel_name);
                }
            }
        }
    }
}

/*****************************************
* FUNCTION: DISPLAY SUBMISSION DATE EDIT *
******************************************/
function display_submission_date_edit($submission_date, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SubmissionDate']) . ": </label>
            </div>
            <div class='{$span2}'>
                <input style='cursor: default;' type='text' name='submission_date'  size='50' value='" . $escaper->escapeHtml($submission_date) . "' title='" . $escaper->escapeHtml($submission_date) . "' class='datepicker form-control' />
            </div>
        </div>
    ";
}

/**********************************
* FUNCTION: DISPLAY CATEGORY EDIT *
***********************************/
function display_category_edit($category, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Category']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                create_dropdown("category", $category);
    echo "
            </div>
        </div>
    ";
}

/**********************************
* FUNCTION: DISPLAY LOCATION EDIT *
***********************************/
function display_location_edit($location, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SiteLocation']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";

    if($location) {
        $locations = explode(",", $location);
    } else {
        $locations = [];
    }
    
                create_multiple_dropdown("location", $locations, NULL, NULL, false, "", "", true, " class='multiselect' ");
    echo "
            </div>
        </div>
    ";
}

/***********************************************
* FUNCTION: DISPLAY EXTERNAL REFERENCE ID EDIT *
************************************************/
function display_external_reference_id_edit($reference_id, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ExternalReferenceId']) . ": </label>
            </div>
            <div class='{$span2}'>
                <input type='text' name='reference_id' id='reference_id' class='form-control' size='20' value='" . $escaper->escapeHtml($reference_id) . "' maxlength='20'/>
            </div>
        </div>
    ";
}
    
/********************************************
* FUNCTION: DISPLAY CONTROL REGULATION EDIT *
*********************************************/
function display_control_regulation_edit($regulation, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ControlRegulation']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                create_dropdown("frameworks", $regulation, "regulation");
    echo "
            </div>
        </div>
    ";
}

/****************************************
* FUNCTION: DISPLAY CONTROL NUMBER EDIT *
*****************************************/
function display_control_number_edit($control_number, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ControlNumber']) . ": </label>
            </div>
            <div class='{$span2}'>
                <input type='text' class='form-control' name='control_number' id='control_number' size='20' value='" . $escaper->escapeHtml($control_number) . "' maxlength='50'/>
            </div>
        </div>
    ";
}

/*****************************************
* FUNCTION: DISPLAY AFFECTED ASSETS EDIT *
******************************************/
function display_affected_assets_edit($risk_id, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['AffectedAssets']) . ": </label>
            </div>
            <div class='{$span2} affected-assets'>
                <select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='" . $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']) . "'>
                </select>
            </div>
        </div>
        <div class='row mb-2'>
            <div class='{$span1}'></div>
            <div class='{$span2}'>
                <span class='affected-assets-instructions text-danger'>" . $escaper->escapeHtml($lang['AffectedAssetsWidgetInstructions']) . "</span>
            </div>
        </div>
    ";
}

/************************************
* FUNCTION: DISPLAY TECHNOLOGY EDIT *
*************************************/
function display_technology_edit($technology, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Technology']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";

    $technology_values = ":" . implode(":", explode(",", (string)$technology)) . ":";

                create_multiple_dropdown("technology", $technology_values, NULL, NULL, false, "", "", true, " class='multiselect' ");
    echo "
            </div>
        </div>
    ";
}

/******************************
* FUNCTION: DISPLAY TEAM EDIT *
*******************************/
function display_team_edit($team, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Team']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";

    $team = ":" . implode(":", explode(",", (string)$team)) . ":";

                create_multiple_dropdown("team", $team, NULL, NULL, false, "", "", true, " class='multiselect' ");

    echo "
            </div>
        </div>
    ";
}

/*************************************************
* FUNCTION: DISPLAY ADDITIONAL STAKEHOLDERS EDIT *
**************************************************/
function display_additional_stakeholders_edit($additional_stakeholders, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['AdditionalStakeholders']) . ": </label>
            </div>
            <div class='{$span2} multiselect-holder'>
    ";
                create_multiusers_dropdown("additional_stakeholders", $additional_stakeholders);
    echo "
            </div>
        </div>
    ";
}

/*******************************
* FUNCTION: DISPLAY OWNER EDIT *
********************************/
function display_owner_edit($owner, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Owner']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                create_selectize_dropdown("enabled_users", $owner, ['name' => 'owner']);
    echo "
            </div>
        </div>
    ";
}

/****************************************
* FUNCTION: DISPLAY OWNERS MANAGER EDIT *
*****************************************/
function display_owners_manager_edit($manager, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['OwnersManager']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                create_selectize_dropdown("enabled_users", $manager, ['name' => 'manager']);
    echo "
            </div>
        </div>
    ";
}

/*************************************
* FUNCTION: DISPLAY RISK SOURCE EDIT *
**************************************/
function display_risk_source_edit($source, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'> 
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['RiskSource']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                 create_dropdown("source", $source);
    echo "
            </div>
        </div>
    ";
}

/***********************************************
* FUNCTION: DISPLAY RISK ASSESSMENT TITLE EDIT *
************************************************/
function display_risk_assessment_title_edit($assessment, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label id='RiskAssessmentTitle'>" . $escaper->escapeHtml($lang['RiskAssessment']) . ": </label>
            </div>
            <div class='{$span2}'>
                <textarea class='form-control' name='assessment' cols='50' rows='5' id='assessment'>" . $escaper->escapeHtml($assessment) . "</textarea>
            </div>
        </div>
    ";
}

/******************************************
* FUNCTION: DISPLAY ADDITIONAL NOTES EDIT *
*******************************************/
function display_additional_notes_edit($notes, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label id='NotesTitle'>" . $escaper->escapeHtml($lang['AdditionalNotes']) . ": </label>
            </div>
            <div class='{$span2}'>
                <textarea name='notes' class='form-control' cols='50' rows='3' id='notes'>" . $escaper->escapeHtml($notes) . "</textarea>
            </div>
        </div>
    ";
}

/****************************************
* FUNCTION: DISPLAY JIRA ISSUE KEY EDIT *
*****************************************/
function display_jira_issue_key_edit($jira_issue_key, $panel_name="") {

    // We're not displaying anything if the extra isn't turned on
    if (!jira_extra())
        return;
    
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['JiraIssueKey']) . ": </label>
            </div>
            <div class='{$span2}'>
                <input type='text' class='form-control' name='jira_issue_key' id='jira_issue_key' size='20' value='" . $escaper->escapeHtml($jira_issue_key) . "' />
            </div>
        </div>
    ";
}

/**************************************************
* FUNCTION: DISPLAY SUPPORTING DOCUMENTATION EDIT *
***************************************************/
function display_supporting_documentation_edit($risk_id, $view_type, $panel_name="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SupportingDocumentation']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                supporting_documentation($risk_id, "edit", $view_type);
    echo "
            </div>
        </div>
    ";
}

/**************************************
* FUNCTION: DISPLAY RISK MAPPING EDIT *
***************************************/
function display_risk_mapping_edit($risk_catalog_mapping=[], $panel_name="") {

    global $lang, $escaper;

    if ($risk_catalog_mapping) {
        if (!is_array($risk_catalog_mapping)) {
            $risk_catalog_mapping = explode(",", $risk_catalog_mapping);
        }
    } else {
        $risk_catalog_mapping = [];
    }
    
    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['RiskMapping']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                create_selectize_dropdown('risk_catalog', $risk_catalog_mapping);
    echo "
            </div>
        </div>
    ";
}

/**************************************
* FUNCTION: DISPLAY THREAT MAPPING EDIT *
***************************************/
function display_threat_mapping_edit($threat_catalog_mapping=[], $panel_name="") {

    global $lang, $escaper;

    if ($threat_catalog_mapping) {
        if (!is_array($threat_catalog_mapping)) {
            $threat_catalog_mapping = explode(",", $threat_catalog_mapping);
        }
    } else {
        $threat_catalog_mapping = [];
    }

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ThreatMapping']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                create_selectize_dropdown('threat_catalog', $threat_catalog_mapping);
    echo "
            </div>
        </div>
    ";
}

/*********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN DETAILS EDIT *
**********************************************************/
function display_main_detail_fields_by_panel_edit($panel_name, $fields, $risk_id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping = [], $threat_catalog_mapping = [])
{
    foreach($fields as $field) {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 1) {
            if($field['is_basic'] == 1) {
                if($field['active'] == 0) {
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']) {
                    case 'SubmissionDate':
                        display_submission_date_edit($submission_date, $panel_name);
                        break;
            
                    case 'Category':
                        display_category_edit($category, $panel_name);
                        break;
                    
                    case 'SiteLocation':
                        display_location_edit($location, $panel_name);
                        break;

                    case 'ExternalReferenceId':
                        display_external_reference_id_edit($reference_id, $panel_name);
                        break;
                    
                    case 'ControlRegulation':
                        display_control_regulation_edit($regulation, $panel_name);
                        break;
                        
                    case 'ControlNumber':
                        display_control_number_edit($control_number);
                        break;
                        
                    case 'AffectedAssets':
                        display_affected_assets_edit($risk_id, $panel_name);
                        break;
                    
                    case 'Technology':
                        display_technology_edit($technology, $panel_name);
                        break;
                        
                    case 'Team':
                        display_team_edit($team, $panel_name);
                        break;
                        
                    case 'AdditionalStakeholders':
                        display_additional_stakeholders_edit($additional_stakeholders, $panel_name);
                        break;
                    
                    case 'Owner':
                        display_owner_edit($owner, $panel_name);
                        break;
                        
                    case 'OwnersManager':
                        display_owners_manager_edit($manager, $panel_name);
                        break;
                    
                    case 'RiskSource':
                        display_risk_source_edit($source, $panel_name);
                        break;
                
                    case 'RiskScoringMethod':
                        risk_score_method_html($panel_name, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);
                        break;

                    case 'RiskAssessment':
                        display_risk_assessment_title_edit($assessment, $panel_name);
                        break;
                        
                    case 'AdditionalNotes':
                        display_additional_notes_edit($notes, $panel_name);
                        break;

                    case 'JiraIssueKey':
                        display_jira_issue_key_edit($jira_issue_key, $panel_name);
                        break;

                    case 'SupportingDocumentation':
                        display_supporting_documentation_edit($risk_id, 1, $panel_name);  
                        break;

                    case 'Tags':
                        display_risk_tags_edit($tags, $panel_name);
                        break;

                    case 'RiskMapping':
                        display_risk_mapping_edit($risk_catalog_mapping, $panel_name);
                        break;

                    case 'ThreatMapping':
                        display_threat_mapping_edit($threat_catalog_mapping, $panel_name);
                        break;
                }

                if($field['active'] == 0) {
                    echo "</div>";
                }
            } else {
                // If customization extra is enabled
                if(customization_extra()) {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id);
                    display_custom_field_edit($field, $custom_values, "div", false, $panel_name);
                }
            }
        }
    }
}

/****************************************************
* FUNCTION: DISPLAY MITIGATION SUBMISSION DATE VIEW *
*****************************************************/
function display_mitigation_submission_date_view($mitigation_date, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }
    
    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationDate']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($mitigation_date) . "
            </div>
        </div>
    ";
}

/**************************************************
* FUNCTION: DISPLAY MITIGATION PLANNING DATE VIEW *
***************************************************/
function display_mitigation_planning_date_view($planning_date, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationPlanning']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($planning_date) . "
            </div>
        </div>
    ";
}

/******************************************************
* FUNCTION: DISPLAY MITIGATION PLANNING STRATAGE VIEW *
*******************************************************/
function display_mitigation_planning_strategy_view($planning_strategy, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['PlanningStrategy'])  .": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "
            </div>
        </div>
    ";
}

/*******************************************
* FUNCTION: DISPLAY MITIGATION EFFORT VIEW *
********************************************/
function display_mitigation_effort_view($mitigation_effort, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationEffort']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "
            </div>
        </div>
    ";
}

/*****************************************
* FUNCTION: DISPLAY MITIGATION COST VIEW *
******************************************/
function display_mitigation_cost_view($mitigation_cost, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationCost']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_asset_value_by_id($mitigation_cost)) . "
            </div>
        </div>
    ";
}

/******************************************
* FUNCTION: DISPLAY MITIGATION OWNER VIEW *
*******************************************/
function display_mitigation_owner_view($mitigation_owner, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationOwner']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_name_by_value("user", $mitigation_owner)) . "
            </div>
        </div>
    ";
}

/*****************************************
* FUNCTION: DISPLAY MITIGATION TEAM VIEW *
******************************************/
function display_mitigation_team_view($mitigation_team, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationTeam']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml(get_names_by_multi_values("team", $mitigation_team)) . "
            </div>
        </div>
    ";
}

/********************************************
* FUNCTION: DISPLAY MITIGATION PERCENT VIEW *
*********************************************/
function display_mitigation_percent_view($mitigation_percent, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationPercent']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($mitigation_percent) . "
            </div>
        </div>
    ";
}

/*******************************************
* FUNCTION: DISPLAY ACCEPT MITIGATION VIEW *
********************************************/
function display_accept_mitigation_view($risk_id, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $message = view_accepted_mitigations($risk_id);

    echo "
        <div class='row mb-2 " . (!$message ? "hide" : "") . "' >
            <div class='col-12 accept_mitigation_text'>" . 
                $message . "
            </div>
        </div>
    ";

    // If user has able to accept mitigation permission
    if(!empty($_SESSION['accept_mitigation']))
    {
        // Get accepted mitigation by login user
        $accepted_mitigation = get_accpeted_mitigation($risk_id);

        echo "
        <div class='row accept-mitigation-container' style='margin-bottom: 12px; " . ($accepted_mitigation ? "display:none" : "") . "'>
            <div class='{$span1} text-end '>
                <button type='button' class='btn btn-submit accept_mitigation'>" . $escaper->escapeHtml($lang['AcceptMitigation']) . "</button>
            </div>
        </div>
        <div class='reject-mitigation-container' style='margin-bottom: 12px; " . ($accepted_mitigation ? "" : "display:none") . "'>
            <div class='row mb-2'>
                <div class='{$span1} text-end '>
                    <button type='button' class='btn btn-primary reject_mitigation'>" . $escaper->escapeHtml($lang['RejectMitigation']) . "</button>
                </div>
            </div>
        </div>
        ";
    }
    
    echo "
        <script>
            if(typeof  called_accept_mitigation == 'undefined')
            {
                // Accept mitigation 
                $('body').on('click', '.accept_mitigation', function(e){
                    e.preventDefault();
                    var tabContainer = $(this).parents('.tab-data');
                    var risk_id = $('span.risk-id', tabContainer).text();
                    var self = $(this);
                    self.prop('disabled', true);
                    self.html($('#_lang_accepting').val());
                    
                    $.ajax({
                        type: 'POST',
                        data: {
                            accept: 1
                        },
                        url: BASE_URL + '/api/management/risk/accept_mitigation?id=' + risk_id,
                        success: function(data){
                            $('.accept-mitigation-container', tabContainer).hide();
                            $('.reject-mitigation-container', tabContainer).show();
                            if(data.data.accept_mitigation_text){
                                $('.accept_mitigation_text', tabContainer).parent().show();
                                $('.accept_mitigation_text', tabContainer).html(data.data.accept_mitigation_text);
                            }
                            else{
                                $('.accept_mitigation_text', tabContainer).parent().hide();
                            }

                            self.prop('disabled', false);
                            self.html($('#_lang_accept_mitigation').val());
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                                self.prop('disabled', false);
                                self.html($('#_lang_accept_mitigation').val());
                            }
                        }
                    })
                })
                
                // Reject mitigation 
                $('body').on('click', '.reject_mitigation', function(e){
                    e.preventDefault();
                    var tabContainer = $(this).parents('.tab-data');
                    var risk_id = $('.risk-id', tabContainer).html();
                    var self = $(this);
                    self.prop('disabled', true);
                    self.html($('#_lang_rejecting').val());
                    
                    $.ajax({
                        type: 'POST',
                        data: {
                            accept: 0
                        },
                        url: BASE_URL + '/api/management/risk/accept_mitigation?id=' + risk_id,
                        success: function(data){
                            $('.accept-mitigation-container', tabContainer).show();
                            $('.reject-mitigation-container', tabContainer).hide();
                            if(data.data.accept_mitigation_text){
                                $('.accept_mitigation_text', tabContainer).parent().show();
                                $('.accept_mitigation_text', tabContainer).html(data.data.accept_mitigation_text);
                            }
                            else{
                                $('.accept_mitigation_text', tabContainer).parent().hide();
                            }
                            self.prop('disabled', false);
                            self.html($('#_lang_reject_mitigation').val());
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                                self.prop('disabled', false);
                                self.html($('#_lang_reject_mitigation').val());
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
function display_current_solution_view($current_solution, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2 align-items-center'>
            <div class='{$span1} d-flex align-items-center justify-content-end' id='CurrentSolutionTitle'>
                <label>" . $escaper->escapeHtml($lang['CurrentSolution']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->purifyHtml($current_solution) . "
            </div>
        </div>
    ";
}

/***********************************************
* FUNCTION: DISPLAY SECURITY REQUIREMENTS VIEW *
************************************************/
function display_security_requirements_view($security_requirements, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2 align-items-center'>
            <div class='{$span1} d-flex align-items-center justify-content-end' >
                <label>" . $escaper->escapeHtml($lang['SecurityRequirements']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->purifyHtml($security_requirements) . "
            </div>
        </div>
    ";
}

/**************************************************
* FUNCTION: DISPLAY SECURITY RECOMMENDATIONS VIEW *
***************************************************/
function display_security_recommendations_view($security_recommendations, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2 align-items-center'>
            <div class='{$span1} d-flex align-items-center justify-content-end' >
                <label>" . $escaper->escapeHtml($lang['SecurityRecommendations']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->purifyHtml($security_recommendations) . "
            </div>
        </div>
    ";
}


/************************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN MITIGATION VIEW *
*************************************************************/
function display_main_mitigation_fields_by_panel_view($panel_name, $fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id)
{

    foreach($fields as $field) {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 2) {
            // If main field
            if($field['is_basic'] == 1) {

                if($field['active'] == 0) {
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']) {
                    case 'MitigationDate':
                        display_mitigation_submission_date_view($mitigation_date, $panel_name);
                        break;
                    
                    case 'MitigationPlanning':
                        display_mitigation_planning_date_view($planning_date, $panel_name);    
                        break;
                        
                    case 'PlanningStrategy':
                        display_mitigation_planning_strategy_view($planning_strategy, $panel_name);
                        break;
                        
                    case 'MitigationEffort':
                        display_mitigation_effort_view($mitigation_effort, $panel_name);
                        break;
                        
                    case 'MitigationCost':
                        display_mitigation_cost_view($mitigation_cost, $panel_name);
                        break;
                        
                    case 'MitigationOwner':
                        display_mitigation_owner_view($mitigation_owner, $panel_name);
                        break;
                        
                    case 'MitigationTeam':
                        display_mitigation_team_view($mitigation_team, $panel_name);
                        break;
                        
                    case 'MitigationPercent':
                        display_mitigation_percent_view($mitigation_percent, $panel_name);
                        break;
                        
                    case 'AcceptMitigation':
                        display_accept_mitigation_view($risk_id, $panel_name);
                        break;
                        
                    case 'CurrentSolution':
                        display_current_solution_view($current_solution, $panel_name);
                        break;
                        
                    case 'SecurityRequirements':
                        display_security_requirements_view($security_requirements, $panel_name);
                        break;
                        
                    case 'SecurityRecommendations':
                        display_security_recommendations_view($security_recommendations, $panel_name);
                        break;
                        
                    case 'MitigationSupportingDocumentation':
                        display_supporting_documentation_view($risk_id, 2, $panel_name);
                        break;

                    case 'MitigationControlsList':
                        print_mitigation_controls_table($mitigation_controls, $mitigation_id, "view");
                        break;
                }

                if($field['active'] == 0) {
                    echo "</div>";
                }
            
            // If custom field
            } else {
                // If customization extra is enabled
                if(customization_extra()) {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id);
                    display_custom_field_risk_view($field, $custom_values, 0, $panel_name);
                }
            }
        }
    }
}

/***************************************
* FUNCTION: DISPLAY CUSTOM FIELD PRINT *
****************************************/
function display_custom_field_print($field, $custom_values, $review_id=0) {

    global $lang, $escaper;

    $value = "";
    
    // Get value of custom filed
    foreach($custom_values as $custom_value) {

        if($custom_value['field_id'] == $field['id'] && $custom_value['review_id'] == $review_id) {
            $value = $custom_value['value'];
            break;
        }
    }
    
    echo "
        <div class='d-flex align-items-center mb-2'>
            <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($field['name']) . ":</label>
            <p class='mb-0'>" . get_custom_field_name_by_value($field['id'], $field['type'], $field['encryption'], $value) . "</p>
        </div>
    ";
}

/****************************************************
* FUNCTION: DISPLAY MITIGATION SUBMISSION DATE EDIT *
*****************************************************/
function display_mitigation_submission_date_edit($mitigation_date, $panel_name="")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationDate']) . ": </label>
            </div>
            <div class='{$span2}'>
                <input class='form-control' title='" . $escaper->escapeHtml($lang['MitigationDate']) . "' type='text' name='mitigation_date' id='mitigation_date' size='50' value='" . $escaper->escapeHtml($mitigation_date) . "' title='" . $escaper->escapeHtml($mitigation_date) . "' disabled='disabled' />
            </div>
        </div>
    ";
}

/**************************************************
* FUNCTION: DISPLAY MITIGATION PLANNING DATE EDIT *
***************************************************/
function display_mitigation_planning_date_edit($planning_date, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }
    
    // if planning_date is empty, current date is shown
    if (!$planning_date) {
        $planning_date = format_date(date('Y-m-d'));
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationPlanning']) . ": </label>
            </div>
            <div class='{$span2}'>
                <input title='" . $escaper->escapeHtml($lang['MitigationPlanning']) . "' type='text' name='planning_date'  size='50' value='" . $escaper->escapeHtml($planning_date) . "' class='datepicker form-control' />
            </div>
        </div>
    ";
}

/******************************************************
* FUNCTION: DISPLAY MITIGATION PLANNING STRATEGY EDIT *
*******************************************************/
function display_mitigation_planning_strategy_edit($planning_strategy, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['PlanningStrategy']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                create_dropdown("planning_strategy", $planning_strategy, NULL, true, false, true, $customHtml="title='" . $escaper->escapeHtml($lang['PlanningStrategy']) . "'") . "
            </div>
        </div>
    ";
}

/*******************************************
* FUNCTION: DISPLAY MITIGATION EFFORT EDIT *
********************************************/
function display_mitigation_effort_edit($mitigation_effort, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationEffort']) . ":</label>
            </div>
            <div class='{$span2}'>" . 
                create_dropdown("mitigation_effort", $mitigation_effort, NULL, true, false, true, $customHtml="title='" . $escaper->escapeHtml($lang['MitigationEffort']) . "'") . "
            </div>
        </div>
    ";
}

/*****************************************
* FUNCTION: DISPLAY MITIGATION COST EDIT *
******************************************/
function display_mitigation_cost_edit($mitigation_cost, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationCost']) . ":</label>
            </div>
            <div class='{$span2}'>
    ";
                create_asset_valuation_dropdown("mitigation_cost", $mitigation_cost, NULL, "title='" . $escaper->escapeHtml($lang['MitigationCost']) . "'");
    echo "
            </div>
        </div>
    ";
}

/******************************************
* FUNCTION: DISPLAY MITIGATION OWNER EDIT *
*******************************************/
function display_mitigation_owner_edit($mitigation_owner, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationOwner']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                create_dropdown("enabled_users", $mitigation_owner, "mitigation_owner", true, $help = false, $returnHtml=true, $customHtml="title='" . $escaper->escapeHtml($lang['MitigationOwner']) . "'") . "
            </div>
        </div>
    ";
}

/*****************************************
* FUNCTION: DISPLAY MITIGATION TEAM EDIT *
******************************************/
function display_mitigation_team_edit($mitigation_team, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationTeam']) . ": </label>
            </div>
            <div class='{$span2}'>
    ";
                $mitigation_team_values = ":" . implode(":", explode(",", (string)$mitigation_team)) . ":";
                //create_dropdown("team", $mitigation_team, "mitigation_team", true);
                create_multiple_dropdown("team", $mitigation_team_values, "mitigation_team", NULL, false, "", "", true, " class='multiselect' ");
    echo "
            </div>
        </div>
    ";
}

/********************************************
* FUNCTION: DISPLAY MITIGATION PERCENT EDIT *
*********************************************/
function display_mitigation_percent_edit($mitigation_percent, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationPercent']) . ": </label>
            </div>
            <div class='{$span2}'>
                <input type='number' min='0' max='100' name='mitigation_percent' title='" . $escaper->escapeHtml($lang['MitigationPercent']) . "' id='mitigation_percent' size='50' value='" . $escaper->escapeHtml($mitigation_percent) . "' class='percent form-control' />
            </div>
        </div>
    ";
}

/*********************************************
* FUNCTION: DISPLAY MITIGATION CONTROLS EDIT *
**********************************************/
function display_mitigation_controls_edit($mitigation_controls, $panel_name = "")
{
    
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }
    
    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['MitigationControls']) . ": </label>
            </div>
            <div class='{$span2}'>";
                mitigation_controls_dropdown($mitigation_controls, "mitigation_controls[]", true, true);
    echo "
            </div>
         </div>
    ";
}

/******************************************
* FUNCTION: DISPLAY CURRENT SOLUTION EDIT *
*******************************************/
function display_current_solution_edit($current_solution, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }
    
    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['CurrentSolution']) . ": </label>
            </div>
            <div class='{$span2}'>
                <textarea  class='form-control' title='" . $escaper->escapeHtml($lang['CurrentSolution']) . "' name='current_solution' cols='50' rows='3' id='current_solution' tabindex='1'>" . $escaper->escapeHtml($current_solution) . "</textarea>
            </div>
        </div>
    ";
}

/***********************************************
* FUNCTION: DISPLAY SECURITY REQUIREMENTS EDIT *
************************************************/
function display_security_requirements_edit($security_requirements, $panel_name="")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SecurityRequirements']) . ": </label>
            </div>
            <div class='{$span2}'>
                <textarea class='form-control' title='" . $escaper->escapeHtml($lang['SecurityRequirements']) ."' name='security_requirements' cols='50' rows='3' id='security_requirements' tabindex='1'>" . $escaper->escapeHtml($security_requirements) . "</textarea>
            </div>
        </div>
    ";
}

/**************************************************
* FUNCTION: DISPLAY SECURITY RECOMMENDATIONS EDIT *
***************************************************/
function display_security_recommendations_edit($security_recommendations, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SecurityRecommendations']) . ": </label>
            </div>
            <div class='{$span2}'>
                <textarea class='form-control' title='" . $escaper->escapeHtml($lang['SecurityRecommendations']) . "' name='security_recommendations' cols='50' rows='3' id='security_recommendations' tabindex='1'>" . $escaper->escapeHtml($security_recommendations) . "</textarea>
            </div>
        </div>
    ";
}

/************************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN MITIGATION EDIT *
*************************************************************/
function display_main_mitigation_fields_by_panel_edit($panel_name, $fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id)
{

    foreach($fields as $field) {
        
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 2) {
            if($field['is_basic'] == 1) {
                if($field['active'] == 0) {
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']) {
                    case 'MitigationDate':
                        display_mitigation_submission_date_edit($mitigation_date, $panel_name);
                        break;
                    
                    case 'MitigationPlanning':
                        display_mitigation_planning_date_edit($planning_date, $panel_name);    
                        break;
                        
                    case 'PlanningStrategy':
                        display_mitigation_planning_strategy_edit($planning_strategy, $panel_name);
                        break;
                        
                    case 'MitigationEffort':
                        display_mitigation_effort_edit($mitigation_effort, $panel_name);
                        break;
                        
                    case 'MitigationCost':
                        display_mitigation_cost_edit($mitigation_cost, $panel_name);
                        break;
                        
                    case 'MitigationOwner':
                        display_mitigation_owner_edit($mitigation_owner, $panel_name);
                        break;
                        
                    case 'MitigationTeam':
                        display_mitigation_team_edit($mitigation_team, $panel_name);
                        break;
                        
                    case 'MitigationPercent':
                        display_mitigation_percent_edit($mitigation_percent, $panel_name);
                        break;
                        
                    case 'CurrentSolution':
                        display_current_solution_edit($current_solution, $panel_name);
                        break;
                        
                    case 'SecurityRequirements':
                        display_security_requirements_edit($security_requirements, $panel_name);
                        break;
                        
                    case 'SecurityRecommendations':
                        display_security_recommendations_edit($security_recommendations, $panel_name);
                        break;
                        
                    case 'MitigationSupportingDocumentation':
                        display_supporting_documentation_edit($risk_id, 2, $panel_name);
                        break;

                    case 'MitigationControls':
                        display_mitigation_controls_edit($mitigation_controls, $panel_name);
                        break;

                    case 'MitigationControlsList':
                        // Add controls table html
                        print_mitigation_controls_table($mitigation_controls, $mitigation_id, "edit");

                        // Add javascript code for mitigation controls
                        display_mitigation_controls_script();
                        break;
                }

                if($field['active'] == 0) {
                    echo "</div>";
                }
                
            } else {

                // If customization extra is enabled
                if(customization_extra()) {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id);
                    display_custom_field_edit($field, $custom_values, "div", false, $panel_name);
                }

            }
        }
    }
}

/*************************************
* FUNCTION: DISPLAY REVIEW DATE VIEW *
**************************************/
function display_review_date_view($review_date, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ReviewDate']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($review_date) . "
            </div>
        </div>
    ";
}

/**********************************
* FUNCTION: DISPLAY REVIEWER VIEW *
***********************************/
function display_reviewer_view($reviewer, $panel_name = "")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $reviewer_name = get_name_by_value("user", $reviewer);

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Reviewer']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($reviewer_name) . "
            </div>
        </div>
    ";
}

/********************************
* FUNCTION: DISPLAY REVIEW VIEW *
*********************************/
function display_review_view($review, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $review_value = get_name_by_value("review", $review);

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Review']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($review_value) . "
            </div>
        </div>
    ";
}

/***********************************
* FUNCTION: DISPLAY NEXT STEP VIEW *
************************************/
function display_next_step_view($next_step_value, $risk_id, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $next_step = get_name_by_value("next_step", $next_step_value);

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['NextStep']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($next_step) . "
            </div>
        </div>
    ";

    if ($next_step_value == 2) {

        $project = get_project_by_risk_id($risk_id);
        $project_name = $project? $project['name'] : $lang['UnassignedRisks'];

        if ($project_name) {

            echo "
                <div class='row mb-2'>
                    <div class='{$span1} d-flex align-items-center justify-content-end'>
                        <label>" . $escaper->escapeHtml($lang['ProjectName']) . ": </label>
                    </div>
                    <div class='{$span2}'>" . 
                        $escaper->escapeHtml($project_name) . "
                    </div>
                </div>
            ";
        }
    }
}

/******************************************
* FUNCTION: DISPLAY NEXT REVIEW DATE VIEW *
*******************************************/
function display_next_review_date_view($next_review, $panel_name = "")
{

    global $lang, $escaper;
    
    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    if(!$next_review){
        $next_review = "";
    }
    
    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['NextReviewDate']) . ": </label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($next_review) . "
            </div>
        </div>
    ";
}

/**********************************
* FUNCTION: DISPLAY COMMENTS VIEW *
***********************************/
function display_comments_view($comment, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Comment']) . ": </label>
            </div>
            <div class='{$span2} rich-text-container'>" . 
                $escaper->purifyHtml($comment) . "
            </div>
        </div>
    ";
}

/********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN REVIEW VIEW *
*********************************************************/
function display_main_review_fields_by_panel_view($panel_name, $fields, $risk_id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comment)
{

    foreach($fields as $field) {

        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 3) {

            // If main field
            if($field['is_basic'] == 1) {

                if($field['active'] == 0) {
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']) {
                    case 'ReviewDate':
                        display_review_date_view($review_date, $panel_name);
                        break;
                    
                    case 'Reviewer':
                        display_reviewer_view($reviewer, $panel_name);
                        break;

                    case 'Review':
                        display_review_view($review, $panel_name);
                        break;
                        
                    case 'NextStep':
                        display_next_step_view($next_step, $risk_id, $panel_name);
                        break;
                        
                    case 'NextReviewDate':
                        display_next_review_date_view($next_review, $panel_name);
                        break;

                    case 'Comment':
                        display_comments_view($comment, $panel_name);
                        break;
                }

                if($field['active'] == 0) {
                    echo "</div>";
                }

            // If custom field
            } else {

                // If customization extra is enabled
                if(customization_extra()) {

                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id, false, $review_id);
                    display_custom_field_risk_view($field, $custom_values, $review_id, $panel_name);
                }
            }
        }
    }
}

/*************************************
* FUNCTION: DISPLAY REVIEW DATE EDIT *
**************************************/
function display_review_date_edit($panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ReviewDate']) . ":</label>
            </div>
            <div class='{$span2}'>
    ";
    echo        date(get_default_date_format());
    echo "
            </div>
        </div>
    ";
}

/*************************************
* FUNCTION: DISPLAY REVIEW NAME EDIT *
**************************************/
function display_reviewer_name_edit($panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Reviewer']) . ":</label>
            </div>
            <div class='{$span2}'>" . 
                $escaper->escapeHtml($_SESSION['name']) . "
            </div>
        </div>
    ";
}

/********************************
* FUNCTION: DISPLAY REVIEW EDIT *
*********************************/
function display_review_edit($review, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Review']) . ":</label>
            </div>
            <div class='{$span2}'>
    ";
                create_dropdown("review", $review, NULL, true);
    echo "
            </div>
        </div>
    ";
}

/********************************
* FUNCTION: DISPLAY REVIEW EDIT *
*********************************/
function display_next_step_edit($next_step, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['NextStep']) . ":</label>
            </div>
            <div class='{$span2}'>
    ";
                create_dropdown("next_step", $next_step, NULL, true);
    echo "
            </div>
        </div>
    ";

    // Projects
    $project = get_project_by_risk_id($_GET['id']);
    $project_id = $project? $project['value'] : false;

    // If Next Step is Consider for Project (value=2), show project list
    if($next_step == 2) {
        echo "
        <div class='row mb-2 project-holder'>
        ";
    } else {
        echo "
        <div class='row mb-2 project-holder' style='display:none;'>
        ";
    }
    
    echo "
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['ProjectName']) . ":</label>
            </div>
            <div class='{$span2}'>
    ";
                create_dropdown("projects", $project_id, "project", false);
    echo "
            </div>
            <div class='{$span1}'></div>
            <div class='{$span2}'>
                <div class='project-instructions'>
                    {$escaper->escapeHtml($lang['ReviewProjectSelectionInstructions'])}
                </div>
            </div>
        </div>
        
        <script>
            $(document).ready(function(){
                $('#project').selectize({
                    create: true,
                    //sortField: 'text',
                    addPrecedence: true,
                    placeholder: '{$escaper->escapeJS($lang['ReviewProjectSelectionPlaceholder'])}',
                    sortField: 'value',
                    create: function(input) {return { 'value': 'new-projval-prfx-' + input, 'text': input }; }
                });
    ";

    if ($project_id === false) {
        echo "
                $('#project')[0].selectize.clear();
        ";
    }

    echo "
            });
        </script>
        <style>
            .project-instructions {
                font-size: 0.8em;
                color: red;
                position:relative;
            }
        </style>
    ";
}

/********************************
* FUNCTION: DISPLAY REVIEW EDIT *
*********************************/
function display_comments_edit($comments, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Comments']) . ":</label>
            </div>
            <div class='{$span2}'>
                <textarea class='form-control' name='comments' cols='50' rows='3' id='comments'>" . $escaper->escapeHtml($comments) . "</textarea>
            </div>
        </div>
    ";
}

/**********************************************
* FUNCTION: DISPLAY SET NEXT REVIEW DATE EDIT *
***********************************************/
function display_set_next_review_date_edit($default_next_review, $panel_name = "")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1}'>&nbsp;</div>
            <div class='{$span2}'>
                <strong class='small-text'>" . 
                    $escaper->escapeHtml($lang['BasedOnTheCurrentRiskScore']) . $escaper->escapeHtml($default_next_review) . "
                    <br />" . 
                    $escaper->escapeHtml($lang['WouldYouLikeToUseADifferentDate']) . "
                </strong>
                <div class='form-check'>
                    <div class='form-check-label'>
                        <input type='radio' name='custom_date' value='no' id='no' class='form-check-input hidden-radio' checked /> 
                        <label for='no'>" . $escaper->escapeHtml($lang['No']) . "</label>
                    </div>
                    <div class='form-check-label'>
                        <input type='radio' name='custom_date' value='yes' id='yes' class='form-check-input hidden-radio' />
                        <label for='yes'>" . $escaper->escapeHtml($lang['Yes']) . "</label>
                    </div>
                </div>
            </div>
        </div>
        
        <div id='nextreview' class='nextreview row mb-2' style='display:none;'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['NextReviewDate']) . ":</label>
            </div>
            <div class='{$span2}'>
                <input type='text' class='datepicker form-control' name='next_review' id='nextreviewvalue' value='" . $escaper->escapeHtml($default_next_review) . "' />
            </div>
        </div>
    ";
}

/********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN REVIEW EDIT *
*********************************************************/
function display_main_review_fields_by_panel_edit($panel_name, $fields, $risk_id, $review_id, $review, $next_step, $next_review, $comment, $default_next_review)
{

    foreach($fields as $field) {

        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 3) {

            if($field['is_basic'] == 1) {

                if($field['active'] == 0) {
                    echo "<div style='display: none'>";
                    echo $field['name'];
                }
                
                switch($field['name']) {
                    case 'ReviewDate':
                        display_review_date_edit($panel_name);
                        break;
                    
                    case 'Reviewer':
                        display_reviewer_name_edit($panel_name);
                        break;

                    case 'Review':
                        display_review_edit($review, $panel_name);
                        break;
                        
                    case 'NextStep':
                        display_next_step_edit($next_step, $panel_name);
                        break;
                        
                    case 'Comment':
                        display_comments_edit($comment, $panel_name);
                        break;
                    
                    case 'SetNextReviewDate':
                        display_set_next_review_date_edit($default_next_review, $panel_name);
                        break;
                }

                if($field['active'] == 0) {
                    echo "</div>";
                }
                
            } else {

                // If customization extra is enabled
                if(customization_extra()) {

                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_values = getCustomFieldValuesByRiskId($risk_id, false, $review_id);
                    display_custom_field_edit($field, $custom_values, "div", false, $panel_name);
                }
            }
        }
    }
}

/*************************************************
* FUNCTION: DISPLAY SUPPORTING DOCUMENTATION ADD *
**************************************************/
function display_supporting_documentation_add($panel_name="", $template_group_id="")
{

    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['SupportingDocumentation']) . ":</label>
            </div>
            <div class='{$span2}'>
                <div class='file-uploader'>
                
                    <script>
                        var max_upload_size = " . $escaper->escapeJs(get_setting('max_upload_size', 0)) . ";
                        var fileTooBigMessage = '" . $escaper->escapeJs($lang['FileIsTooBigToUpload']) . "'; 
                    </script>

                    <label for='file-upload-" . $template_group_id . "' class='btn btn-primary m-r-20'>" . $escaper->escapeHtml($lang['ChooseFile']) . "</label>
                    <span class='file-count-html'> <span class='file-count'>0</span> " . $escaper->escapeHtml($lang['FileAdded']) . "</span>
                    <p><font size='2'><strong>Max " . $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)) . " Mb</strong></font></p>
                    <ul class='file-list'>
                    </ul>
                    <input type='file' id='file-upload-" . $template_group_id . "' name='file[]' class='d-none hidden-file-upload active' />
                </div>
            </div>
        </div>
    ";
}


/************************************
 * FUNCTION: DISPLAY RISK TAGS EDIT *
 ************************************/
function display_risk_tags_edit($tags = "", $panel_name = "bottom")
{
    
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $tags_placeholder = $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);

    echo "
        <div class='row'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <strong>" . $escaper->escapeHtml($lang['Tags']) . ":</strong>
            </div>
            <div class='{$span2}'>
                <select readonly id='tags' class='tags' name='tags[]' multiple placeholder='{$tags_placeholder}'>
    ";

    if ($tags) {
        foreach(explode(",", $tags) as $tag) {
            $tag = $escaper->escapeHtml($tag);
            echo "
                    <option selected value='{$tag}'>{$tag}</option>
            ";
        }
    }

    echo "
                </select>
                
                <script>
                    $(function(){
                        $('select.tags').selectize({
                            plugins: ['remove_button', 'restore_on_backspace'],
                            delimiter: '|',
                            create: true,
                            valueField: 'label',
                            labelField: 'label',
                            searchField: 'label',
                            createFilter: function(input) { return input.length <= 255; },
                            preload: true,
                            load: function(query, callback) {
                                if (query.length) return callback();
                                $.ajax({
                                    url: BASE_URL + '/api/management/tag_options_of_type?type=risk',
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
                    });
                </script>

            </div>
        </div>
        <div class='row mb-2'>
            <div class='{$span1}'></div>
            <div class='{$span2}'>
                <div class='tag-max-length-warning text-danger'>" . $escaper->escapeHtml($lang['MaxTagLengthWarning']) . "</div>
            </div>
        </div>
    ";
}

/************************************
 * FUNCTION: DISPLAY RISK TAGS VEIW *
 ************************************/
function display_risk_tags_view($tags, $panel_name = "bottom")
{
    global $lang, $escaper;

    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    echo "
        <div class='row mb-2'>
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['Tags']) . ":</label>
            </div>
            <div class='{$span2}'>
    ";
    //     <div class='row mb-2'>
    //         <div class='col-12 hero-unit'>
    //             <div class='row mb-2'>
    //                 <div class='wrap-text span1 text-left'><strong>".$escaper->escapeHtml($lang['Tags'])."</strong></div>
    //                 </div>
    //                 <div class='row mb-2'>
    //                 <div class='col-12'>
    // ";

    if ($tags) {

        foreach(explode(",", $tags) as $tag) {

            echo "
                <button class='btn btn-secondary btn-sm' style='pointer-events: none;margin:2px;padding: 4px 12px;' role='button' aria-disabled='true'>" . $escaper->escapeHtml($tag) . "</button>
            ";

        }

    } else {

        echo    $escaper->escapeHtml($lang['NoTagAssigned']);

    }

    echo "  </div>
        </div>";
}


/********************************************************
* FUNCTION: DISPLAY MAIN FIELDS BY PANEL IN DETAILS ADD *
*********************************************************/
function display_main_detail_fields_by_panel_add($panel_name, $fields, $template_group_id="")
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
                        display_category_edit('', $panel_name);
                    break;
                    
                    case 'SiteLocation':
                        display_location_edit('', $panel_name);
                    break;

                    case 'ExternalReferenceId':
                        display_external_reference_id_edit('', $panel_name);
                    break;
                    
                    case 'ControlRegulation':
                        display_control_regulation_edit('', $panel_name);
                    break;
                        
                    case 'ControlNumber':
                        display_control_number_edit('', $panel_name);
                    break;
                        
                    case 'AffectedAssets':
                        display_affected_assets_edit('', $panel_name);
                    break;
                    
                    case 'Technology':
                        display_technology_edit('', $panel_name);
                    break;
                        
                    case 'Team':
                        display_team_edit('', $panel_name);
                    break;
                        
                    case 'AdditionalStakeholders':
                        display_additional_stakeholders_edit('', $panel_name);
                    break;
                    
                    case 'Owner':
                        display_owner_edit('', $panel_name);
                    break;
                        
                    case 'OwnersManager':
                        display_owners_manager_edit('', $panel_name);
                    break;
                    
                    case 'RiskSource':
                        display_risk_source_edit('', $panel_name);
                    break;
                
                    case 'RiskScoringMethod':
                        risk_score_method_html($panel_name);
                    break;

                    case 'RiskAssessment':
                        display_risk_assessment_title_edit('', $panel_name);
                    break;
                        
                    case 'AdditionalNotes':
                        display_additional_notes_edit('', $panel_name);
                    break;

                    case 'JiraIssueKey':
                        display_jira_issue_key_edit('', $panel_name);
                    break;
                        
                    case 'SupportingDocumentation':
                        display_supporting_documentation_add($panel_name, $template_group_id);  
                    break;

                    case 'Tags':
                        display_risk_tags_edit('', $panel_name);
                    break;

                    case 'RiskMapping':
                        display_risk_mapping_edit([], $panel_name);
                    break;

                    case 'ThreatMapping':
                        display_threat_mapping_edit([], $panel_name);
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

                    display_custom_field_edit($field, [], "div", false, $panel_name);
                }
            }
        }
    }
}


/**
 * Render the html for the whole section where the customer can select between risk templates(if customization is enabled)
 * and the templates themselves for adding a new risk. 
 */
function display_add_risk() {
    global $escaper;

    if(customization_extra()) {
        ?>
        <div class="mt-2">
            <nav class="nav nav-tabs">

<?php
            $tab_str = "";
            if(organizational_hierarchy_extra()) {
                require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
                $template_groups = get_assigned_template_group_by_user_id($_SESSION['uid'], "risk");
            } else {
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                $template_groups = get_custom_template_groups('risk');
            }
            foreach($template_groups as $index=>$template_group){
                $active = $index == 0 ? "active" : ""; 
                echo "
                <a class='nav-link {$active}' data-bs-target='#template_group_{$template_group['id']}' data-bs-toggle='tab'>{$escaper->escapeHtml($template_group['name'])}</a>
                ";
            }
?>
            </nav>
        </div>
<?php
        }
?>
        <div class="tab-content card-body my-2 border" id="tab-content-container">
<?php
        if(customization_extra()) {
            if(organizational_hierarchy_extra()) {
                require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
                $template_groups = get_assigned_template_group_by_user_id($_SESSION['uid'], "risk");
            } else {
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                $template_groups = get_custom_template_groups('risk');
            }
            foreach ($template_groups as $index=>$template_group) {
                $active = $index == 0 ? "show active" : ""; 
                $template_group_id = $template_group["id"];
                echo "
            <div class='tab-pane tab-data fade col-12 mt-2 {$active}' id='template_group_{$template_group_id}'>
                ";
                include(realpath(__DIR__ . '/../management/partials/add.php'));
                echo "
            </div>
                ";
            }
        } else {
            $template_group_id = "";
            echo "
            <div class='tab-data' id='tab-container'>
                ";
                include(realpath(__DIR__ . '/../management/partials/add.php'));
            echo "
            </div>
                ";
        }
?>
        </div>
<?php 
    
}



?>