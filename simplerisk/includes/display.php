<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/displayrisks.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/assessments.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/governance.php'));
require_once(realpath(__DIR__ . '/reporting.php'));

/****************************
* FUNCTION: VIEW SCORE HTML *
****************************/
function view_score_html($risk_id, $calculated_risk, $mitigation_percent)
{
    global $lang, $escaper;

    echo "
        <div class='row'>
            <div class='col-6'>
    ";

    // Inherent Risk
    echo "
                <div class='p-10 text-center' style='background-color: " . $escaper->escapeHtml(get_risk_color($calculated_risk)) . "'>
                    <h5 class=''>" .$escaper->escapeHtml($lang['InherentRisk']) . "</h5>
                    <h1 class='my-0'>" .$escaper->escapeHtml($calculated_risk) . "</h5>
                    <h5 class=''>" . $escaper->escapeHtml(get_risk_level_name($calculated_risk)) . "</h5>
                </div>
            </div>
    ";

    // Residual Risk
    if(!$calculated_risk || $calculated_risk == "0.0")
    {
        $residual_risk = "0.0";
    }
    else
    {
        $residual_risk = get_residual_risk($risk_id);
    }

    echo "
            <div class='col-6'>
                <div class='p-10 text-center' style='background-color: " . $escaper->escapeHtml(get_risk_color($residual_risk)) . "'>
                    <h5 class=''>" . $escaper->escapeHtml($lang['ResidualRisk']) . "</h5>
                    <h1 class='my-0'>" . $escaper->escapeHtml($residual_risk) . "</h5>
                    <h5 class=''>" . $escaper->escapeHtml(get_risk_level_name($residual_risk)) . "</h5>
                </div>
            </div>
        </div>
    ";
}

/****************************
* FUNCTION: VIEW TOP TABLE *
****************************/
function view_top_table($risk_id, $calculated_risk, $subject, $status, $show_details = false, $mitigation_percent = 0, $display_risk = true)
{
    
    global $lang, $escaper;

    // Decrypt fields
    $subject = try_decrypt($subject);

    echo "
        <div class='row pt-2'>
            <div class='col-md-12 col-lg-3 score--wrapper'>
    ";
                view_score_html($risk_id, $calculated_risk, $mitigation_percent);
    echo "
            </div>
            
            <div class='col-md-12 col-lg-9 details--wrapper'>
                <div class='row mb-2'>
                    <div class='col-3'>
                        <label>" . $escaper->escapeHtml($lang['IDNumber']) . ": <span class='fs-3 risk-id'>" . $escaper->escapeHtml($risk_id) . "</span></label>
                    </div>
                    <div class='col-5'>
                        <label>" . $escaper->escapeHtml($lang['Status']) . ": <span class='fs-3 status-text'>" . $escaper->escapeHtml($status) . "</span></label>
                    </div>
    ";

    if($display_risk == true) {

        echo "
                    <div class='col-4 text-end'>
                        <div class='btn-group pull-right'>
                            <a class='btn btn-primary dropdown-toggle' data-bs-toggle='dropdown' href='#'>" . $escaper->escapeHtml($lang['RiskActions']) . "<span class='caret'></span></a>
                                <ul class='dropdown-menu'>
        ";

        // If the risk is closed, offer to reopen
        if ($status == "Closed") { 
            echo "
                                    <li><a class='reopen-risk dropdown-item' href='reopen.php?id=" . $escaper->escapeHtml($risk_id) . "'>" . $escaper->escapeHtml($lang['ReopenRisk']) . "</a></li>
            "; 
        }

        // Otherwise, offer to close
        else {
            // If the user has permission to close risks
            if (has_permission("close_risks")) {
                echo "
                                    <li><a class='close-risk dropdown-item' href='close.php?id=" . $escaper->escapeHtml($risk_id) . "'>" . $escaper->escapeHtml($lang['CloseRisk']) . "</a></li>
                ";
            }
        }

        // If the user has permission to modify risks
        if (has_permission("modify_risks"))
        {
            echo "
                                    <li><a class='edit-risk dropdown-item' href='view.php?action=editdetail&id=" . $escaper->escapeHtml($risk_id) . "'>" . $escaper->escapeHtml($lang['EditRisk']) . "</a></li>
            ";
        }

        // If the user has permission to plan mitigations
        if (has_permission("plan_mitigations"))
        {
            echo "
                                    <li><a class='edit-mitigation dropdown-item' href='#'>" . $escaper->escapeHtml($lang['PlanAMitigation']) . "</a></li>
            ";
        }

        // Check the review permissions for this risk id
        $approved = check_review_permission_by_risk_id($risk_id);

        // If the user has permission to review the current level
        if ($approved) {
            echo "
                                    <li><a class='perform-review dropdown-item' href='#'>" . $escaper->escapeHtml($lang['PerformAReview']) . "</a></li>
            ";
        }

        // If the user has permission to modify risks
        if (has_permission("modify_risks")) {
            echo "
                                    <li><a class='change-status dropdown-item' href='#'>" . $escaper->escapeHtml($lang['ChangeStatus']) . "</a></li>
            ";
        }

        // If the user has permission to comment on risk management
        if (has_permission("comment_risk_management")) {
            echo "
                                    <li><a href='#comment' class='add-comment-menu dropdown-item'>" . $escaper->escapeHtml($lang['AddAComment']) . "</a></li>
            ";
        }
        // If the user has permission to plan mitigations
        if (has_permission("plan_mitigations")) {
            echo "
                                    <li><a class='mark-unmitigation dropdown-item' href='#'>" . $escaper->escapeHtml($lang['ResetMitigations']) . "</a></li>
            ";
        }

        // If the user has permission to plan mitigations
        if (has_permission("modify_risks")) {
            echo "
                                    <li><a class='mark-unreview dropdown-item' href='#'>" . $escaper->escapeHtml($lang['ResetReviews']) . "</a></li>
            ";
        }

        echo "
                                    <li><a class='printable-veiw dropdown-item' href='print_view.php?id=" . $escaper->escapeHtml($risk_id) . "' target='_blank'>" . $escaper->escapeHtml($lang['PrintableView']) . "</a></li>
                                </ul>
                            </div>
                        </div>
        ";
    }

    echo "
                    </div>
                    
                    <div class='row border-top pt-2'>
                        <div class='col-12'>
                            <div id='static-subject' class='static-subject'>
                                <label>Subject : <span class='fs-3 risk-subject'>" . $escaper->escapeHtml($subject) . "</span>
    ";

    // If we are displaying the risk and the user has modify risk permissions
    if($display_risk == true && has_permission("modify_risks")) {
        echo "
                                    <div id='edit-subject' class='edit-subject-btn d-inline ms-2 fs-4' role='button'>
                                        <i class='fa fa-edit' aria-hidden='true'></i>
                                    </div>
        ";
    }

    echo "
                                </label>
                            </div>
                            
                            <form name='details' method='post' action=''>
                                <input type='hidden' name='riskid' value='" . $escaper->escapeHtml($risk_id) . "'/>
                                <div class='edit-subject row d-none'>
                                    <div class='d-flex align-items-center'>
                                        <input maxlength='" . (int)get_setting('maximum_risk_subject_length', 300) . "' class='form-control' type='text' name='subject' value='" . $escaper->escapeHtml($subject) . "' style='max-width:none;'/>
                                        <div style='width: 200px;' class='m-l-20 text-end'>
                                            <button type='button' class='btn btn-primary cancel-edit-subject' style='margin:0 5px 0 0;' >Cancel</button>
                                            <button type='button' class='btn btn-submit' name='update_subject' >Save</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
    ";

}

/**********************************
* FUNCTION: VIEW PRINT TOP TABLE *
**********************************/
function view_print_top_table($id, $calculated_risk, $subject, $status) {

    global $lang;
    global $escaper;

    // Decrypt fields
    $subject = try_decrypt($subject);

    echo "
        <div class='d-flex align-items-center'>
            <div class='flex-shrink-0 d-flex flex-column align-items-center justify-content-center py-2 border' style='height: 120px; width: 120px; background-color: " . $escaper->escapeHtml(get_risk_color($calculated_risk)) . "'>
                <span>" . $escaper->escapeHtml($lang['InherentRisk']) . "</span>
                <span style='font-size: 30px;'>" . $escaper->escapeHtml($calculated_risk) . "</span>
                <span>(". $escaper->escapeHtml(get_risk_level_name($calculated_risk)) . ")</span>
            </div>
    ";

    $residual_risk = get_residual_risk($id);

    echo "
            <div class='flex-shrink-0 d-flex flex-column align-items-center justify-content-center py-2 ms-3 border' style='height: 120px; width: 120px; background-color: " . $escaper->escapeHtml(get_risk_color($residual_risk)) . "'>
                <span>" . $escaper->escapeHtml($lang['ResidualRisk']) . "</span>
                <span style='font-size: 30px;'>" . $escaper->escapeHtml($residual_risk) . "</span>
                <span>(". $escaper->escapeHtml(get_risk_level_name($residual_risk)) . ")</span>
            </div>
            <div class='ms-5 font-18'>
                <div class='d-flex align-items-center mb-2'>
                    <label class='mb-0' style='min-width: 100px;'>" . $escaper->escapeHtml($lang['RiskId']) . ":</label>
                    <p class='mb-0'>" . $escaper->escapeHtml($id) . "</p>
                </div>
                <div class='d-flex align-items-center mb-2'>
                    <label class='mb-0' style='min-width: 100px;'>" . $escaper->escapeHtml($lang['Subject']) . ":</label>
                    <p class='mb-0'>" . $escaper->escapeHtml($subject) . "</p>
                </div>
                <div class='d-flex align-items-center'>
                    <label class='mb-0' style='min-width: 100px;'>" . $escaper->escapeHtml($lang['Status']) . ":</label>
                    <p class='mb-0'>" . $escaper->escapeHtml($status) . "</p>
                </div>
            </div>
        </div>
    ";
}

/*****************************
* FUNCTION: ADD RISK DETAILS *
*******************************/
function add_risk_details($template_group_id = "") {
    
    global $lang, $escaper;

    echo "
        <form name='submit_risk' method='post' action='' enctype='multipart/form-data' id='risk-submit-form'>
    ";

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        if(!$template_group_id) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        echo "
            <input type='hidden' name='template_group_id' id='template_group_id' value='{$template_group_id}' />
            <div class='row mb-2'>
                <div class='col-2 d-flex align-items-center justify-content-end'>
                    <label>" . $escaper->escapeHtml($lang['Subject']) . ":</label>
                </div>
                <div class='col-10'>
                    <input maxlength='" . (int)get_setting('maximum_risk_subject_length', 300) . "' title='" . $escaper->escapeHtml($lang['Subject']) . "' required name='subject' id='subject' class='form-control' type='text'>
                </div>
            </div>
        ";

        $active_fields = get_active_fields("risk", $template_group_id);

        // Top panel
        echo "
            <div class='row'>
                <div class='col-12 top-panel'>
        ";
                    display_main_detail_fields_by_panel_add('top', $active_fields, $template_group_id);
        echo "
                </div>
            </div>
            
            <div class='row'>
        ";
        // Left Panel
        echo "
                <div class='col-6 left-panel'>
        ";
                    display_main_detail_fields_by_panel_add('left', $active_fields, $template_group_id);
        echo "
                    &nbsp;
                </div>
        ";

        // Right Panel
        echo "
                <div class='col-6 right-panel'>
        ";
                    display_main_detail_fields_by_panel_add('right', $active_fields, $template_group_id);
        echo "
                    &nbsp;
                </div>
            </div>
        ";

        // Bottom panel
        echo "
            <div class='row'>
                <div class='col-12 bottom-panel'>
        ";
                    display_main_detail_fields_by_panel_add('bottom', $active_fields, $template_group_id);
        echo "
                    &nbsp;
                </div>
            </div>
        ";
    }
    else
    {
        echo "
            <div class='row mb-2'>
                <div class='col-2 d-flex align-items-center justify-content-end'>
                    <label>" . $escaper->escapeHtml($lang['Subject']) . ":</label>
                </div>
                <div class='col-10'>
                    <input maxlength='" . (int)get_setting('maximum_risk_subject_length', 300) . "' title='" . $escaper->escapeHtml($lang['Subject']) . "' required name='subject' id='subject' class='form-control' type='text'>
                </div>
            </div>
        ";

            display_risk_mapping_edit([], "top");

            display_threat_mapping_edit([], "top");

        echo "
            <div class='row'>
                <!-- first coulmn -->
                <div class='col-6 left-panel'>
        ";
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

        echo "
                </div>
                <!-- first coulmn end -->

                <!-- second coulmn -->
                <div class='col-6 right-panel'>
            ";

                    display_risk_source_edit('');

                    risk_score_method_html();

                    display_risk_assessment_title_edit('');

                    display_additional_notes_edit('');

                    display_jira_issue_key_edit('');

                    display_supporting_documentation_add();

        echo "
                </div>
                <!-- second column end -->
                
            </div>
        ";
            display_risk_tags_edit();
    }

    echo "
            <div class='row'>
                <div class='col-12'>
                    <div class='actions risk-form-actions d-flex justify-content-between align-items-center'>
                        <span class='m-r-20'>" . $escaper->escapeHtml($lang['NewRiskInstruction']) . "</span>
                        <div>
                            <button type='button' name='submit' class='btn btn-submit text-end save-risk-form'>" . $escaper->escapeHtml($lang['SubmitRisk']) . "</button>
                            <button class='btn btn-secondary' id='reset_form' type='reset'>" . $escaper->escapeHtml($lang['ClearForm']) . " </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <script>
            function reset_new_risk_form(reset_btn) {

                // To make sure the reset only affects the current tab
                var tab = $(reset_btn).closest('.tab-data');

                // reset the form
                $('form[name=submit_risk]', tab).trigger('reset');

                // re-draw the multiselects as they ARE reset, but their texts still display the previous selections
                $('form[name=submit_risk] span.multiselect-native-select select[multiple]', tab).multiselect('refresh');

                // Clear all the selectize widgets
                $('form[name=submit_risk] select.selectized', tab).each(function() {
                    $(this)[0].selectize.clear();
                });

                // Trigger the file removal logic for the added files
                $('form[name=submit_risk] .file-list .remove-file', tab).trigger('click');

                // Select the classic scoring and trigger a change event instead of click to make sure the logic runs properly
                $('#scoring_method', tab).find('option[value=1]').prop('selected', true).trigger('change');

            }
            $(document).ready(function(){
        		$('body').on('click', '#reset_form', function(){

                    // Reset the form
                    reset_new_risk_form(this);
                    
        		});
            });
        </script>
    ";

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

    echo "
        <h4 class='m-b-25'>" . $escaper->escapeHtml($lang['Details']) . "</h4>";

    //echo "<h4>". $escaper->escapeHtml($lang['Details']) ."</h4>\n";
        // If the page is the view.php page and the user has permission to modify risks
    if (basename($_SERVER['PHP_SELF']) == "view.php" && has_permission("modify_risks")) {
        // Give the option to edit the risk details
        echo "
        <div class='tabs--action position-absolute top-0 end-0'>
            <button type='submit' name='edit_details' class='btn btn-dark'>" . $escaper->escapeHtml($lang['EditDetails']) . "</button>
        </div>
        ";
    }

    // If customization extra is enabled
    if(customization_extra()) {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);

        // Top panel
        echo "
        <div class='row'>
            <div class='col-12 top-panel'>
        ";
                display_main_detail_fields_by_panel_view('top', $active_fields, $id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "
            </div>
        </div>
        
        <div class='row'>
        ";
        // Left Panel
        echo "
            <div class='col-6 left-panel'>
        ";
                display_main_detail_fields_by_panel_view('left', $active_fields, $id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "
                &nbsp;
            </div>
        ";

        // Right Panel
        echo "
            <div class='col-6 right-panel'>
        ";
                display_main_detail_fields_by_panel_view('right', $active_fields, $id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "
                &nbsp;
            </div>
        </div>
        ";

        // Bottom panel
        echo "
        <div class='row'>
            <div class='col-12 bottom-panel'>
        ";
                display_main_detail_fields_by_panel_view('bottom', $active_fields, $id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "
                &nbsp;
            </div>
        </div>
        ";
    } else {
        echo "
        <div class='row'>
            <div class='col-12 top-panel'>
        ";
                display_risk_mapping_view($risk_catalog_mapping, "top");
                display_threat_mapping_view($threat_catalog_mapping, "top");
        echo "
            </div>
        </div>
        <div class='row'>
        ";
        // Left Panel
        echo "
            <div class='col-6 left-panel'>
        ";
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
        echo "
            </div>
            
            <div class='col-6 right-panel'>
        ";
                display_submitted_by_view($submitted_by);

                display_risk_source_view($source);

                display_risk_scoring_method_view($scoring_method, $CLASSIC_likelihood, $CLASSIC_impact);

                display_risk_assessment_view($assessment);

                display_additional_notes_view($notes);
                
                display_supporting_documentation_view($id, 1);

                display_jira_issue_key_view($jira_issue_key);

        echo "
            </div>
        </div>
        
        <div class='row'>
            <div class='col-12 bottom-panel'>
        ";
                display_risk_tags_view($tags);
        echo "
            </div>
        </div>
        ";
    }
}

/*************************************
* FUNCTION: VIEW PRINT RISK DETAILS *
*************************************/
function view_print_risk_details($id, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $additional_stakeholders, $owner, $manager, $assessment, $notes, $tags, $submitted_by, $source, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $risk_catalog_mapping, $threat_catalog_mapping, $template_group_id="") {

    global $lang;
    global $escaper;

    // Decrypt fields
    $subject = try_decrypt($subject);
    $assessment = try_decrypt($assessment);
    $notes = try_decrypt($notes);

    echo "
        <h4>" . $escaper->escapeHtml($lang['Details']) . "</h4>
        <div class='risk-details-container card-body border mt-2'>
    ";

    // If customization extra is enabled
    if(customization_extra()) {

        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $custom_values = getCustomFieldValuesByRiskId($id);
        
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }

        $active_fields = get_active_fields("risk", $template_group_id);
        foreach($active_fields as $field) {

            // Check if this field is custom field and details
            if($field['tab_index'] == 1) {

                if($field['is_basic'] == 1) {

                    switch($field['name']) {

                        case 'SubmissionDate':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SubmissionDate']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($submission_date) . "</p>
            </div>
                            ";
                            break;

                        case 'Category':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Category']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "</p>
            </div>
                            ";
                            break;

                        case 'SiteLocation':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SiteLocation']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($location) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'ExternalReferenceId':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ExternalReferenceId']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($reference_id) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'ControlRegulation':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlRegulation']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("frameworks", $regulation)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'ControlNumber':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlNumber']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($control_number) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'AffectedAssets':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['AffectedAssets']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(implode(',', array_map(function($item) use ($escaper) {
                    return $item['class'] === 'group' ? "[{$item['name']}]" : $item['name'];
                }, get_assets_and_asset_groups_of_type($id, 'risk', true)))) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'Technology':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Technology']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($technology) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'Team':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Team']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($team) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'AdditionalStakeholders':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['AdditionalStakeholders']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($additional_stakeholders) . "</p>
            </div>
                            ";
                            break;
                        
                        case 'Owner':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Owner']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'OwnersManager':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['OwnersManager']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "</p>
            </div>
                            ";
                            break;
                        
                        case 'SubmittedBy':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SubmittedBy']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $submitted_by)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'RiskSource':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['RiskSource']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("source", $source)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'RiskScoringMethod':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['RiskScoringMethod']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("scoring_methods", $scoring_method)) . "</p>
            </div>
                            ";
                            
                            if($scoring_method == "1") {
                                echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['CurrentLikelihood']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("likelihood", $CLASSIC_likelihood)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['CurrentImpact']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("impact", $CLASSIC_impact)) . "</p>
            </div>
                                ";
                            }

                            break;
                        
                        case 'RiskAssessment':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['RiskAssessment']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($assessment) . "</p>
            </div>
                            ";
                            break;

                        case 'AdditionalNotes':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['AdditionalNotes']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($notes) . "</p>
            </div>
                            ";
                            break;

                        case 'Tags':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Tags']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($tags) . "</p>
            </div>
                            ";
                            break;

                        case 'RiskMapping':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['RiskMapping']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_names_by_multi_values("risk_catalog", $risk_catalog_mapping, false, ", ", true)) . "</p>
            </div>
                            ";
                            break;

                        case 'ThreatMapping':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ThreatMapping']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_names_by_multi_values("threat_catalog", $threat_catalog_mapping, false, ", ", true)) . "</p>
            </div>
                            ";
                            break;

                    }
                } else {
                    display_custom_field_print($field, $custom_values);
                }
            }
            
        }
    } else {
        echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SubmissionDate']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($submission_date) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Subject']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($subject) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ExternalReferenceId']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($reference_id) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlRegulation']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("frameworks", $regulation)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlNumber']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($control_number) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SiteLocation']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($location) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Category']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Team']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($team) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Technology']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($technology) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['AdditionalStakeholders']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($additional_stakeholders) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Owner']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['OwnersManager']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['RiskAssessment']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($assessment) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['AdditionalNotes']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($notes) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['AffectedAssets']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(implode(',', array_map(function($item) use ($escaper) {
                    return $item['class'] === 'group' ? "[{$item['name']}]" : $item['name'];
                }, get_assets_and_asset_groups_of_type($id, 'risk')))) . "</p>
            </div>
            <div class='d-flex align-items-center'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Tags']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($tags) . "</p>
            </div>
        ";
    }

    echo "
        </div>
    ";
}

/****************************************
* FUNCTION: VIEW PRINT RISK SCORE FORMS *
*****************************************/
function risk_score_method_html($panel_name="", $scoring_method="1", $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamagePotential="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkillLevel="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPEaseOfDiscovery="10", $OWASPEaseOfExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $custom=false, $ContributingLikelihood="", $ContributingImpacts=[]){

    global $escaper, $lang;
    
    if($custom === false) {
        $custom = get_setting("default_risk_score");
    }
    if($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2' >
            <div class='{$span1} d-flex align-items-center justify-content-end'>
                <label>" . $escaper->escapeHtml($lang['RiskScoringMethod']) . ":</label>
            </div>
            <div class='{$span2}'>" . 
                create_dropdown("scoring_methods", $scoring_method, "scoring_method", false, false, true) . "
            </div>
        </div>
        <div id='classic' class='classic-holder' style='display:" . ($scoring_method == 1 ? "block" : "none") . "'>
            <div class='row mb-2'>
                <div class='{$span1} d-flex align-items-center justify-content-end'>
                    <label>" . $escaper->escapeHtml($lang['CurrentLikelihood']) . ":</label>
                </div>
                <div class='{$span2}'>" . 
                    create_dropdown('likelihood', $CLASSIC_likelihood, NULL, true, false, true) . "
                </div>
            </div>
            <div class='row mb-2'>
                <div class='{$span1} d-flex align-items-center justify-content-end'>
                    <label>" . $escaper->escapeHtml($lang['CurrentImpact']) . ":</label>
                </div>
                <div class='{$span2}'>" . 
                    create_dropdown('impact', $CLASSIC_impact, NULL, true, false, true) . "
                </div>
            </div>
        </div>

        <div id='cvss' style='display: " . ($scoring_method == 2 ? "block" : "none") . ";' class='cvss-holder'>
            <div class='row mb-2'>
                <div class='{$span1}'>
                    &nbsp;
                </div>
                <div class='{$span2}'>
                    <input type='button' class='btn btn-primary' name='cvssSubmit' id='cvssSubmit' value='Score Using CVSS' />
                </div>
            </div>
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
        <div id='dread' style='display: " . ($scoring_method == 3 ? "block" : "none") . ";' class='dread-holder'>
            <div class='row mb-2'>
                <div class='{$span1}'>
                    &nbsp;
                </div>
                <div class='{$span2}'>
                    <input type='button' class='btn btn-primary' name='dreadSubmit' id='dreadSubmit' value='Score Using DREAD' onclick='javascript: popupdread();' />
                </div>
            </div>
            <input type='hidden' name='DREADDamage' id='DREADDamage' value='{$DREADDamagePotential}' />
            <input type='hidden' name='DREADReproducibility' id='DREADReproducibility' value='{$DREADReproducibility}' />
            <input type='hidden' name='DREADExploitability' id='DREADExploitability' value='{$DREADExploitability}' />
            <input type='hidden' name='DREADAffectedUsers' id='DREADAffectedUsers' value='{$DREADAffectedUsers}' />
            <input type='hidden' name='DREADDiscoverability' id='DREADDiscoverability' value='{$DREADDiscoverability}' />
        </div>
        <div id='owasp' style='display: " . ($scoring_method == 4 ? "block" : "none") . ";' class='owasp-holder'>
            <div class='row mb-2'>
                <div class='{$span1}'>
                    &nbsp;
                </div>
                <div class='{$span2}'>
                    <input type='button' class='btn btn-primary' name='owaspSubmit' id='owaspSubmit' value='Score Using OWASP'  />
                </div>
            </div>
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
        <div id='custom' style='display: " . ($scoring_method == 5 ? "block" : "none") . ";' class='custom-holder'>
            <div class='row mb-2'>
                <div class='{$span1} d-flex align-items-center justify-content-end'>
                    <label>" . $escaper->escapeHtml($lang['CustomValue']) . ":</label>
                </div>
                <div class='{$span2} d-flex align-items-center'>
                    <input class='form-control m-r-10' style='width: 70px;' type='number' min='0' step='0.1' max='10' name='Custom' id='Custom' value='{$custom}' />
                    <small>(Must be a numeric value between 0 and 10)</small>
                </div>
            </div>
        </div>
        <div id='contributing-risk' style='display: " . ($scoring_method == 6 ? "block" : "none") . ";' class='contributing-risk-holder'>
            <div class='row mb-2'>
                <div class='{$span1}'>
                    &nbsp;
                </div>
                <div class='{$span2}'>
                    <input type='button' class='btn btn-primary' name='contributingRiskSubmit' id='contributingRiskSubmit' value='" . $escaper->escapeHtml($lang["ScoreUsingContributingRisk"]) ."' />
                </div>
            </div>
            <input type='hidden' name='ContributingLikelihood' id='contributing_likelihood' value='" . ($ContributingLikelihood ? $ContributingLikelihood : count(get_table("likelihood"))) . "' />";
            
            $max_impact_value = count(get_table("impact"));
            $contributing_risks = get_contributing_risks();

            foreach($contributing_risks as $contributing_risk) {
                $html .= "
            <input type='hidden' class='contributing-impact' name='ContributingImpacts[{$contributing_risk['id']}]' id='contributing_impact_{$contributing_risk['id']}' value='" . $escaper->escapeHtml(empty($ContributingImpacts[ $contributing_risk['id'] ]) ? $max_impact_value : $ContributingImpacts[ $contributing_risk['id'] ]) . "' />
                ";
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

    echo "
        <h4 class='m-b-25'>" . $escaper->escapeHtml($lang['Details']) . "</h4>
        <div class='tabs--action position-absolute top-0 end-0'>
            <button type='button' class='btn btn-primary cancel-edit-details on-edit'>" . $escaper->escapeHtml($lang['Cancel']) . "</button>
            <button type='submit' name='update_details' class='btn btn-submit save-details on-edit'>" . $escaper->escapeHtml($lang['SaveDetails']) . "</button>
        </div>
    ";


    // If customization extra is enabled
    if(customization_extra()) {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);

        // Top panel
        echo "
        <div class='row'>
            <div class='col-12 top-panel'>
        ";
                display_main_detail_fields_by_panel_edit('top', $active_fields, $id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "
            </div>
        </div>
        ";

        echo "
        <div class='row'>
        ";

        // Left Panel
        echo "
            <div class='col-6 left-panel'>
        ";
                display_main_detail_fields_by_panel_edit('left', $active_fields, $id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "
                &nbsp;
            </div>
        ";

        // Right Panel
        echo "
            <div class='col-6 right-panel'>
        ";
                display_main_detail_fields_by_panel_edit('right', $active_fields, $id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "
                &nbsp;
            </div>
        </div>
        ";

        // Bottom panel
        echo "
        <div class='row'>
            <div class='col-12 bottom-panel'>
        ";
                display_main_detail_fields_by_panel_edit('bottom', $active_fields, $id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping);
        echo "
                &nbsp;
            </div>
        </div>
        ";
    } else {
        echo "
        <div class='row'>
            <div class='col-12 top-panel'>
        ";
                display_risk_mapping_edit($risk_catalog_mapping, "top");

                display_threat_mapping_edit($threat_catalog_mapping, "top");
        echo "
            </div>
        </div>
        <div class='row'>
            <div class='col-6 left-panel'>
        ";
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
        echo "
            </div>
            
            <div class='col-6 right-panel'>
        ";
                display_risk_source_edit($source);

                risk_score_method_html("", $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);

                display_risk_assessment_title_edit($assessment);

                display_additional_notes_edit($notes);
                
                display_supporting_documentation_edit($id, 1);

                display_jira_issue_key_edit($jira_issue_key);
        echo "
            </div>
        </div>";

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

    echo "
        <h4 class='m-b-25'>" . $escaper->escapeHtml($lang['Mitigation']) . "</h4>
    ";

    if (basename($_SERVER['PHP_SELF']) == "view.php" && has_permission("plan_mitigations")) {
        // Give the option to edit the mitigation details
        echo "
        <div class='tabs--action position-absolute top-0 end-0'>
            <button type='submit' name='edit_mitigation' class='btn btn-dark'>" . $escaper->escapeHtml($lang['EditMitigation']) . "</button>
        </div>
        ";
    }

    // If customization extra is enabled
    if(customization_extra()) {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);

        echo "
        <div class='row'>
        ";

        // Left Panel
        echo "
            <div class='col-6 left-panel'>
        ";
                display_main_mitigation_fields_by_panel_view('left', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
        echo "
                &nbsp;
            </div>
        ";

        // Right Panel
        echo "
            <div class='col-6 right-panel'>
        ";
                display_main_mitigation_fields_by_panel_view('right', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
        echo "
                &nbsp;
            </div>
        </div>
        ";

        // Bottom panel
        echo "
        <div class='row'>
            <div class='col-12 bottom-panel'>
        ";
                display_main_mitigation_fields_by_panel_view('bottom', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
        echo "
                &nbsp;
            </div>
        </div>
        ";
    } else {
        echo "
        <div class='row'>
        ";

        // Left Panel
        echo "
            <div class='col-6 left-panel'>
        ";

                display_mitigation_submission_date_view($mitigation_date);

                display_mitigation_planning_date_view($planning_date);

                display_mitigation_planning_strategy_view($planning_strategy);

                display_mitigation_effort_view($mitigation_effort);

                display_mitigation_cost_view($mitigation_cost);

                display_mitigation_owner_view($mitigation_owner);

                display_mitigation_team_view($mitigation_team);

                display_mitigation_percent_view($mitigation_percent);

                display_accept_mitigation_view($risk_id);

        echo "
            </div>
        ";

        // Right Panel
        echo "
            <div class='col-6 right-panel'>
        ";

                display_current_solution_view($current_solution);

                display_security_requirements_view($security_requirements);

                display_security_recommendations_view($security_recommendations);

                display_supporting_documentation_view($risk_id, 2);

        echo "
            </div>
        </div>
        ";

        // Bottom panel
        echo "
        <div class='row'>
            <div class='col-12 bottom-panel'>
        ";
                print_mitigation_controls_table($mitigation_controls,$mitigation_id,"view");
        echo "
            </div>
        </div>
        ";
    }
}

/*******************************************
* FUNCTION: VIEW PRINT MITIGATION DETAILS *
*******************************************/
function view_print_mitigation_details($id, $mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_percent, $template_group_id) {

    global $lang;
    global $escaper;

    // Decrypt fields
    $current_solution = try_decrypt($current_solution);
    $security_requirements = try_decrypt($security_requirements);
    $security_recommendations = try_decrypt($security_recommendations);

    echo "
        <h4>". $escaper->escapeHtml($lang['Mitigation']) ."</h4>
        <div class='mitigation-details-container card-body border mt-2'>
    ";

    // If customization extra is enabled
    if(customization_extra()) {

        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $custom_values = getCustomFieldValuesByRiskId($id);
        
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }

        $active_fields = get_active_fields("risk", $template_group_id);
        foreach($active_fields as $field) {

            // Check if this field is custom field and details
            if($field['tab_index'] == 2) {

                if($field['is_basic'] == 1) {

                    switch($field['name']) {

                        case 'MitigationDate':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationDate']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($mitigation_date) . "</p>
            </div>
                            ";
                            break;
                        
                        case 'MitigationPlanning':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationPlanning']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($planning_date) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'PlanningStrategy':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['PlanningStrategy']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'MitigationEffort':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationEffort']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'MitigationCost':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationCost']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_asset_value_by_id($mitigation_cost)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'MitigationOwner':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationOwner']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $mitigation_owner)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'MitigationTeam':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationTeam']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_names_by_multi_values("team", $mitigation_team)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'MitigationPercent':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationPercent']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($mitigation_percent) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'AcceptMitigation':
                            $message = view_accepted_mitigations($id);
                            if($message) {
                                echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['AcceptMitigation']) . ":</label>
                <p class='mb-0'>" . $message . "</p>
            </div>
                                ";
                            }
                            break;
                            
                        case 'CurrentSolution':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['CurrentSolution']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($current_solution) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'SecurityRequirements':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SecurityRequirements']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($security_requirements) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'SecurityRecommendations':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SecurityRecommendations']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($security_recommendations) . "</p>
            </div>
                            ";
                            break;
                    }
                } else {
                   display_custom_field_print($field, $custom_values);
                }
            }
        }
    } else {
        echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationDate']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($mitigation_date) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['PlanningStrategy']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationEffort']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationCost']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_asset_value_by_id($mitigation_cost)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationOwner']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $mitigation_owner)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationTeam']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_names_by_multi_values("team", $mitigation_team)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationPercent']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($mitigation_percent) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['CurrentSolution']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($current_solution) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SecurityRequirements']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($security_requirements) . "</p>
            </div>
            <div class='d-flex align-items-center'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SecurityRecommendations']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($security_recommendations) . "</p>
            </div>
        ";
    }
    echo "
        </div>
    ";
}

/*******************************************
* FUNCTION: VIEW PRINT MITIGATION CONTROLS *
*******************************************/
function view_print_mitigation_controls($mitigation) {
    
    global $lang;
    global $escaper;

    $control_ids = empty($mitigation[0]['mitigation_controls']) ? "" : $mitigation[0]['mitigation_controls'];
    $controls = get_framework_controls($control_ids);
    $html = "";

    echo "
        <h4>" . $escaper->escapeHtml($lang['MitigationControls']) . "</h4>
    ";

    foreach ($controls as $key=>$control) {
        echo "
        <div class='card-body border my-2'>
            <div class='row'>
                <div class='col-12'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlLongName']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['long_name']) . "</p>
                    </div>
                </div>
            </div>
            <div class='row'>
                <div class='col-8'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlShortName']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['short_name']) . "</p>
                    </div>
                </div>
                <div class='col-4'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlOwner']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['control_owner_name']) . "</p>
                    </div>
                </div>
            </div>
            <div class='row'>
                <div class='col-4'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlClass']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['control_class_name']) . "</p>
                    </div>
                </div>
                <div class='col-4'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlPhase']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['control_phase_name']) . "</p>
                    </div>
                </div>
                <div class='col-4'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlNumber']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['control_number']) . "</p>
                    </div>
                </div>
            </div>
            <div class='row'>
                <div class='col-4'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlPriority']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['control_priority_name']) . "</p>
                    </div>
                </div>
                <div class='col-4'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ControlFamily']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['family_short_name']) . "</p>
                    </div>
                </div>
                <div class='col-4'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationPercent']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['mitigation_percent']) . "</p>
                    </div>
                </div>
            </div>
            <div class='row'>
                <div class='col-12'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Description']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['description']) . "</p>
                    </div>
                </div>
            </div>
            <div class='row'>
                <div class='col-12'>
                    <div class='d-flex align-items-center mb-2'>
                        <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['SupplementalGuidance']) . ":</label>
                        <p class='mb-0'>" . $escaper->escapeHtml($control['supplemental_guidance']) . "</p>
                    </div>
                </div>
            </div>
        ";

        $mapped_frameworks = get_mapping_control_frameworks($control['id']);

        echo "
            <h5 class='mt-2'>" . $escaper->escapeHtml($lang['MappedControlFrameworks']) . "</h5>
            <table width='100%' class='table table-bordered mb-0'>
                <tr>
                    <th width='50%'>" . $escaper->escapeHtml($lang['Framework']) . "</th>
                    <th width='35%'>" . $escaper->escapeHtml($lang['Control']) . "</th>
                </tr>
        ";

        foreach ($mapped_frameworks as $framework) {
            echo "
                <tr>
                    <td>" . $escaper->escapeHtml($framework['framework_name']) . "</td>
                    <td>" . $escaper->escapeHtml($framework['reference_name']) . "</td>
                </tr>
            ";
        }

        echo "
            </table>
        ";

        $validation = get_mitigation_to_controls($mitigation[0]['mitigation_id'],$control['id']);
        $validation_mitigation_percent = ($validation["validation_mitigation_percent"] >= 0 && $validation["validation_mitigation_percent"] <= 100) ? $validation["validation_mitigation_percent"] : 0;

        if($validation["validation_details"] || $validation["validation_owner"] || $validation_mitigation_percent) {
            echo "
            <h5 class='mt-2'>" . $escaper->escapeHtml($lang['ControlValidation']) . "</h5>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Details']) . ":</label>
                <p class='mb-0'>" . nl2br($escaper->escapeHtml($validation["validation_details"])) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Owner']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $validation["validation_owner"])) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['MitigationPercent']) . ":</label>
                <p class='mb-0'>" . $validation_mitigation_percent . " %</p>
            </div>
            ";
        }
        echo "
        </div>
        ";
    }
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
        <h4 class='m-b-25'>" . $escaper->escapeHtml($lang['Mitigation']) . "</h4>
        <div class='tabs--action position-absolute top-0 end-0'>
            <button type='button' class='btn btn-primary cancel-edit-mitigation'>" . $escaper->escapeHtml($lang['Cancel']) . "</button>
            <button type='submit' name='update_mitigation' class='btn btn-submit'>" . $escaper->escapeHtml($lang['SaveMitigation']) . "</button>
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

        echo "
        <div class='row'>
        ";
        // Left Panel
        echo "
            <div class='col-6 left-panel'>
        ";
                display_main_mitigation_fields_by_panel_edit('left', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
        echo "
            </div>
        ";
        // Right Panel
        echo "
            <div class='col-6 right-panel'>
        ";
                display_main_mitigation_fields_by_panel_edit('right', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
        echo "
            </div>
        </div>
        ";
        // Bottom panel
        echo "
        <div class='row'>
            <div class='col-12 bottom-panel'>
        ";
                display_main_mitigation_fields_by_panel_edit('bottom', $active_fields, $risk_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team,  $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $mitigation_id);
        echo "
            </div>
        </div>
        ";
    } else {
        echo "
        <div class='row'>
            <div class='col-6 left-panel'>
                <input type='hidden' name='tab_type' value='1' />
        ";
                display_mitigation_submission_date_edit($mitigation_date);

                display_mitigation_planning_date_edit($planning_date);

                display_mitigation_planning_strategy_edit($planning_strategy);

                display_mitigation_effort_edit($mitigation_effort);

                display_mitigation_cost_edit($mitigation_cost);

                display_mitigation_owner_edit($mitigation_owner);

                display_mitigation_team_edit($mitigation_team);

                display_mitigation_percent_edit($mitigation_percent);

                display_mitigation_controls_edit($mitigation_controls);
        echo "
            </div>
            <div class='col-6 right-panel'>
        ";
                display_current_solution_edit($current_solution);

                display_security_requirements_edit($security_requirements);

                display_security_recommendations_edit($security_recommendations);

                display_supporting_documentation_edit($risk_id, 2);
        echo "
            </div>
        </div>

        <div class='row'>
            <div class='col-12 bottom-panel'>
        ";
                // Add controls table html
                print_mitigation_controls_table($mitigation_controls,$mitigation_id,"edit");

                // Add javascript code for mitigation controls
                display_mitigation_controls_script();
        echo "
            </div>
        </div>
        ";
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
function mitigation_controls_dropdown($selected_control_ids_string = "", $element_name = "mitigation_controls[]", $initialize = true, $datatable_redraw = false) {

    global $lang, $escaper;

    require_once(realpath(__DIR__ . '/governance.php'));
    $controls = get_framework_controls_dropdown_data();

    if ($controls && !empty($controls)) {
        $selected_control_ids = empty($selected_control_ids_string) ? [] : explode(",", $selected_control_ids_string);
        $eID = "mitigation_controls_" . generate_token(10);

        echo  "
            <select id='{$eID}' name='{$element_name}' title='{$escaper->escapeHtml($lang['MitigationControls'])}' class='mitigation_controls' multiple='multiple'>
        ";

        foreach ($controls as $control) {
            if (in_array($control['id'], $selected_control_ids)) {
                echo "
                <option value='{$control['id']}' selected title='{$escaper->escapeHtml($control['long_name'])}'>{$escaper->escapeHtml($control['short_name'])}</option>
                ";
            } else {
                echo "
                <option value='{$control['id']}' title='{$escaper->escapeHtml($control['long_name'])}'>{$escaper->escapeHtml($control['short_name'])}</option>
                ";
            }
        }

        echo "
            </select>
        ";
    
        if ($initialize) {
            echo "
            <script>
                $(document).ready(function(){
                    // Because it is initialized separately from the other multiselects it needs to move to the end of the call stack
                    // so the multiple initialization calls won't interfere with each-other's DOM manipulations  
                    setTimeout(() => {
                        $('#{$eID}').multiselect({
                            enableFiltering: true,
                            enableCaseInsensitiveFiltering: true,
                            buttonWidth: '100%',
                            maxHeight: 250,
                            dropUp: true,
                            filterPlaceholder: '{$escaper->escapeHtml($lang["SelectForMitigationControls"])}'" . ($datatable_redraw ? ",
                            onDropdownHide: function() {
                                var form = $('#{$eID}').parents('form');
                                var tableId = $('.mitigation-controls-table-container', form).data('tableid');
                                $('#' + tableId).DataTable().draw();
                            }" : "") . "
                        });
                    }, 0);
                });
            </script>
            ";
        }
    
    } else {
        echo "
            <span style='vertical-align: middle;'><strong>{$escaper->escapeHtml($lang['NoControlsAvailable'])}</strong></span>
        ";
    }
}

/**********************************************
* FUNCTION: Display Mitigation Controls Table *
***********************************************/
function print_mitigation_controls_table($control_ids, $mitigation_id, $flag="view") {

    global $lang, $escaper;

    $key = uniqid(rand());
    $tableID = "mitigation-controls-table" . $key;

    echo "
        <br>
        <input type='hidden' value='" . $escaper->escapeHtml($control_ids) . "' class='active-textfield mitigation_control_ids' />
        <div class='row mb-2 mitigation-controls-table-container hide' data-tableid='{$tableID}'>
            <div class='accordion'>
                <div class='accordion-item'>
                    <h2 class='accordion-header'>
                        <button type='button' class='accordion-button' data-bs-toggle='collapse' data-bs-target='#{$tableID}-accordion-body'>Mitigation Controls</button>
                    </h2>
                    <div id='{$tableID}-accordion-body' class='accordion-collapse'>
                        <div class='accordion-body'>
                            <table id='{$tableID}' width='100%'>
                                <thead style='display:none;'>
                                    <tr>
                                        <th>&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>    
        </div>
        <script>
            $(document).ready(function(){
                var mitigationControlDatatable = $('#{$tableID}').DataTable({
                    scrollX: true,
                    bFilter: false,
                    processing: true,
                    serverSide: true,
                    bSort: true,
                    ajax: {
                        url: BASE_URL + '/api/datatable/mitigation_controls',
                        type: 'POST',
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
            });
        </script>
    ";
}

/*******************************************
* FUNCTION: Add Mitigation Controls Script *
********************************************/
function display_mitigation_controls_script(){
    echo "
        <script language='javascript'>
            $(document).ready(function(){
                /**
                * events in clicking Select Mitigating Controls
                */
                $('body').on('click', '.select-mitigating-controls', function(e){
                    e.preventDefault();
                    var form = $(this).parents('form');
                    var mitigation_controls = $('input[name=mitigation_controls]', form).val();
                    var mitigation_controls_array = mitigation_controls.split(',');
                    mitigation_controls_array = mitigation_controls_array.map(function(value){ return parseInt(value); });

                    $('.mitigating-controls-modal input[type=checkbox]', form).each(function(){
                        if(mitigation_controls_array.indexOf(parseInt($(this).val())) > -1){
                            $(this).prop('checked', true);
                        }
                    })
                    $('.mitigating-controls-modal', form).modal();
                })

                /**
                * events in clicking Add button on Mitigation Controls modal
                */
                $('body').on('click', '.mitigating-controls-modal [name=add_controls]', function(e){
                    e.preventDefault();
                    var form = $(this).parents('form');
                    var mitigation_controls_array = [];
                    var mitigation_control_names_array = [];
                    $('.mitigating-controls-modal input[type=checkbox]', form).each(function(){
                        if($(this).is(':checked')){
                            mitigation_controls_array.push(parseInt($(this).val()));
                            mitigation_control_names_array.push($(this).parent().find('.name').html());
                        }
                    })
                    var mitigation_controls = mitigation_controls_array.join(',');
                    var mitigation_control_names = mitigation_control_names_array.join(', ');
                    $('input[name=mitigation_controls]', form).val(mitigation_controls);
                    $('.mitigation_control_names', form).html(mitigation_control_names);
                    $('.mitigating-controls-modal', form).modal('hide');

                    var tableId = $('.mitigation-controls-table-container', form).data('tableid');
                    $('#' + tableId).DataTable().draw();
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

    echo "
        <div class='tabs--action position-absolute top-0 end-0'>
            <button type='submit' name='view_all_reviews' class='btn btn-dark all_reviews_btn'>" . $escaper->escapeHtml($lang['ViewAllReviews']) . "</button>
            <input type='hidden' id='lang_last_review' value='" . $escaper->escapeHtml($lang['LastReview']) . "' />
            <input type='hidden' id='lang_all_reviews' value='" . $escaper->escapeHtml($lang['ViewAllReviews']) . "' />
        </div>
        
        <div class='current_review'>
            <h4 class='m-b-25'>" . $escaper->escapeHtml($lang['LastReview']) . "</h4>
    ";

    // If customization extra is enabled
    if(customization_extra()) {

        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }

        $active_fields = get_active_fields("risk", $template_group_id);

        echo "
            <div class='row'>
        ";

        // Left Panel
        echo "
                <div class='col-6 left-panel'>
        ";
                    display_main_review_fields_by_panel_view('left', $active_fields, $id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comment);
        echo "
                </div>
        ";

        // Right Panel
        echo "
                <div class='col-6 right-panel'>
        ";
                    display_main_review_fields_by_panel_view('right', $active_fields, $id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comment);
        echo "
                </div>
            </div>
        ";

        // Bottom panel
        echo "
            <div class='row'>
                <div class='col-12 bottom-panel'>
        ";
                    display_main_review_fields_by_panel_view('bottom', $active_fields, $id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comment);
        echo "
                </div>
            </div>
        ";

    } else {

        echo "
            <div class='row'>
                <div class='col-6 left-panel'>
        ";
                    display_review_date_view($review_date);

                    display_reviewer_view($reviewer);

                    display_review_view($review);

                    display_next_step_view($next_step, $id);

                    display_next_review_date_view($next_review);

                    display_comments_view($comment);
        echo "
                </div>
            </div>
        ";
    }

    echo "
        </div>
        <div class='all_reviews' style='display:none;'>
            <h4 class='m-b-25'>" . $escaper->escapeHtml($lang['ReviewHistory']) . "</h4>
    ";
            get_reviews($id, $template_group_id);
    echo "
        </div>
    ";
}

/***************************************
* FUNCTION: VIEW PRINT REVIEW DETAILS *
***************************************/
function view_print_review_details($id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comments, $template_group_id) {

    global $lang;
    global $escaper;

    // Decrypt fields
    $comments = try_decrypt($comments);

    echo "
        <h4>" . $escaper->escapeHtml($lang['LastReview']) . "</h4>
        <div class='review-details-container card-body border mt-2'>
    ";

    // If customization extra is enabled
    if(customization_extra()) {

        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $custom_values = getCustomFieldValuesByRiskId($id);
        
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }

        $active_fields = get_active_fields("risk", $template_group_id);
        foreach($active_fields as $field) {

            // Check if this field is custom field and review
            if($field['tab_index'] == 3) {

                if($field['is_basic'] == 1) {

                    switch($field['name']) {

                        case 'ReviewDate':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ReviewDate']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($review_date) . "</p>
            </div>
                            ";
                            break;
                        
                        case 'Reviewer':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Reviewer']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $reviewer)) . "</p>
            </div>
                            ";
                            break;

                        case 'Review':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Review']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("review", $review)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'NextStep':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['NextStep']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("next_step", $next_step)) . "</p>
            </div>
                            ";
                            break;
                            
                        case 'NextReviewDate':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['NextReviewDate']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($next_review) . "</p>
            </div>
                            ";
                            break;

                        case 'Comment':
                            echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Comments']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($comments) . "</p>
            </div>
                            ";
                            break;
                    }
                } else {
                    display_custom_field_print($field, $custom_values, $review_id);
                }
            }
        }
    } else {
        echo "
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['ReviewDate']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($review_date) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Reviewer']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("user", $reviewer)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Review']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("review", $review)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['NextStep']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml(get_name_by_value("next_step", $next_step)) . "</p>
            </div>
            <div class='d-flex align-items-center mb-2'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['NextReviewDate']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($next_review) . "</p>
            </div>
            <div class='d-flex align-items-center'>
                <label class='mb-0' style='width: 200px; min-width: 200px;'>" . $escaper->escapeHtml($lang['Comments']) . ":</label>
                <p class='mb-0'>" . $escaper->escapeHtml($comments) . "</p>
            </div>
        ";        
    }

    echo "
        </div>
    ";
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

    echo "
        <h4 class='m-b-25'>" . $escaper->escapeHtml($lang['SubmitManagementReview']) . "</h4>
        <form name='submit_management_review' method='post' action=''>
            <div class='tabs--action position-absolute top-0 end-0'>
    ";
    //    echo "<input id=\"cancel_disable\" class=\"btn cancel-edit-review \" value=\"". $escaper->escapeHtml($lang['Cancel']) ."\" type=\"reset\">\n";
    echo "
                <button type='button' id='cancel_disable' class='btn btn-primary cancel-edit-review'>Cancel</button>
                <button type='submit' name='submit' class='btn btn-danger save-review'>" . $escaper->escapeHtml($lang['SubmitReview']) . "</button>
            </div>
    ";

    // If customization extra is enabled
    if(customization_extra()) {

        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $template_group = get_custom_template_group_by_id($template_group_id);
        if(!$template_group_id || !$template_group) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);

        echo "
            <div class='row'>
        ";
        // Left Panel
        echo "
                <div class='col-6 left-panel'>
        ";
                    display_main_review_fields_by_panel_edit('left', $active_fields, $id, $review_id, $review, $next_step, $next_review, $comment, $default_next_review);
        echo "
                </div>
        ";
        // Right Panel
        echo "
                <div class='col-6 right-panel'>
        ";
                    display_main_review_fields_by_panel_edit('right', $active_fields, $id, $review_id, $review, $next_step, $next_review, $comment, $default_next_review);
        echo "
                </div>
            </div>
        ";
        // Bottom panel
        echo "
            <div class='row'>
                <div class='col-12 bottom-panel'>
        ";
                    display_main_review_fields_by_panel_edit('bottom', $active_fields, $id, $review_id, $review, $next_step, $next_review, $comment, $default_next_review);
        echo "
                </div>
            </div>
        ";

    } else {

        echo "
            <div class='row-fluid'>
                <div class='span5 left-panel'>
        ";
                    display_review_date_edit();

                    display_reviewer_name_edit();

                    display_review_edit($review);

                    display_next_step_edit($next_step);

                    display_comments_edit($comment);

        echo "
                </div>
                <div class='span5 right-panel'>
        ";
                    display_set_next_review_date_edit($default_next_review);
        echo "
                </div>
            </div>
        ";
    
    }

    echo "
        </form>
    ";

    return;
    
}

/********************************
* FUNCTION: edit_classic_score *
********************************/
function edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact)
{
    global $lang;
    global $escaper;
    echo "
        <form name='update_classic' method='post' action=''>
            <div class='row mb-2'>
                <div class='col-12'>
                    <h4>" . $escaper->escapeHtml($lang['UpdateClassicScore']) . "</h4>
                </div>
            </div>
            <div class='row'>
                <div class='col-12 col-md-4'>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['CurrentLikelihood']) . ":</label>
                        </div>
                        <div class='col-6'>" .
                            create_dropdown("likelihood", $CLASSIC_likelihood, NULL, false, false, true) . "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('likelihoodHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['CurrentImpact']) . ":</label>
                        </div>
                        <div class='col-6'>" . 
                            create_dropdown("impact", $CLASSIC_impact, NULL, false, false, true) . "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('impactHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                </div>
                <div class='col-12 col-md-8 p-l-40'>
    ";
                    view_classic_help();
    echo "
                </div>
            </div>
            <div class='form-actions mt-2'>
                <button type='button' class='btn btn-primary cancel-update'>" . $escaper->escapeHtml($lang['Cancel']) . "</button>
                <button type='submit' name='update_classic' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Update']) . "</button>
            </div>
        </form>
    ";
}

/*****************************
* FUNCTION: edit_cvss_score *
*****************************/
function edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement)
{
    global $lang;
    global $escaper;

    echo "
        <form name='update_cvss' method='post' action=''>
            <div class='row mb-2'>
                <div class='col-12'>
                    <h4>" . $escaper->escapeHtml($lang['UpdateCVSSScore']) . "</h4>
                </div>
            </div>
            <div class='row'>
                <div class='col-12 col-md-4'>
                    <div class='row mb-2'>
                        <div class='col-12'>
                            <h5>" . $escaper->escapeHtml($lang['BaseVector']) . "</h5>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['AttackVector']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("AccessVector", $AccessVector, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('AccessVectorHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['AttackComplexity']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("AccessComplexity", $AccessComplexity, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('AccessComplexityHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['Authentication']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("Authentication", $Authentication, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('AuthenticationHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['ConfidentialityImpact']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("ConfImpact", $ConfImpact, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('ConfImpactHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['IntegrityImpact']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("IntegImpact", $IntegImpact, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('IntegImpactHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['AvailabilityImpact']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("AvailImpact", $AvailImpact, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('AvailImpactHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>

                    <div class='row mt-4 mb-2'>
                        <div class='col-12'>
                            <h5>" . $escaper->escapeHtml($lang['TemporalScoreMetrics']) . "</h5>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['Exploitability']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("Exploitability", $Exploitability, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('ExploitabilityHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['RemediationLevel']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("RemediationLevel", $RemediationLevel, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('RemediationLevelHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['ReportConfidence']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("ReportConfidence", $ReportConfidence, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('ReportConfidenceHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>

                    <div class='row mt-4 mb-2'>
                        <div class='col-12'>
                            <h5>" . $escaper->escapeHtml($lang['EnvironmentalScoreMetrics']) . "</h5>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['CollateralDamagePotential']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("CollateralDamagePotential", $CollateralDamagePotential, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('CollateralDamagePotentialHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['TargetDistribution']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("TargetDistribution", $TargetDistribution, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('TargetDistributionHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['ConfidentialityRequirement']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("ConfidentialityRequirement", $ConfidentialityRequirement, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('ConfidentialityRequirementHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['IntegrityRequirement']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("IntegrityRequirement", $IntegrityRequirement, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('IntegrityRequirementHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['AvailabilityRequirement']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_cvss_dropdown("AvailabilityRequirement", $AvailabilityRequirement, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('AvailabilityRequirementHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                </div>
                <div class='col-12 col-md-8 p-l-40'>
    ";
                    view_cvss_help();
    echo "
                </div>
            </div>
            <div class='form-actions mt-2'>
                <button type='button' class='btn btn-primary cancel-update'>" . $escaper->escapeHtml($lang['Cancel']) . "</button>
                <button type='submit' name='update_cvss' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Update']) . "</button>
            </div>
        </form>
    ";
}

/******************************
* FUNCTION: edit_dread_score *
******************************/
function edit_dread_score($DamagePotential, $Reproducibility, $Exploitability, $AffectedUsers, $Discoverability)
{

    global $lang;
    global $escaper;

    echo "
        <form name='update_dread' method='post' action=''>
            <div class='row mb-2'>
                <div class='col-12'>
                    <h4>" . $escaper->escapeHtml($lang['UpdateDREADScore']) . "</h4>
                </div>
            </div>
            <div class='row'>
                <div class='col-12 col-md-4'>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['DamagePotential']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("DamagePotential", $DamagePotential, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href=\"#\" onClick=\"javascript:showHelp('DamagePotentialHelp');\"><i class=\"fa fa-question-circle\"></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['Reproducibility']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("Reproducibility", $Reproducibility, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href=\"#\" onClick=\"javascript:showHelp('ReproducibilityHelp');\"><i class=\"fa fa-question-circle\"></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['Exploitability']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("Exploitability", $Exploitability, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href=\"#\" onClick=\"javascript:showHelp('ExploitabilityHelp');\"><i class=\"fa fa-question-circle\"></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['AffectedUsers']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("AffectedUsers", $AffectedUsers, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href=\"#\" onClick=\"javascript:showHelp('AffectedUsersHelp');\"><i class=\"fa fa-question-circle\"></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['Discoverability']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("Discoverability", $Discoverability, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href=\"#\" onClick=\"javascript:showHelp('DiscoverabilityHelp');\"><i class=\"fa fa-question-circle\"></i></a>
                        </div>
                    </div>
                </div>
                <div class='col-12 col-md-8 p-l-40'>
    ";
                    view_dread_help();
    echo "
                </div>
            </div>
            <div class='form-actions mt-2'>
                <button type='button' class='btn btn-primary cancel-update'>" . $escaper->escapeHtml($lang['Cancel']) . "</button>
                <button type='submit' name='update_dread' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Update']) . "</button>
            </div>
        </form>
    ";
}

/******************************
* FUNCTION: edit_owasp_score *
******************************/
function edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation)
{
    global $lang;
    global $escaper;

    echo "
        <form name='update_owasp' method='post' action=''>
            <div class='row mb-2'>
                <div class='col-12'>
                    <h4>" . $escaper->escapeHtml($lang['UpdateOWASPScore']) . "</h4>
                </div>
            </div>
            <div class='row'>
                <div class='col-12 col-md-4'>
                    <div class='row mt-4 mb-2'>
                        <div class='col-12'>
                            <h5>" . $escaper->escapeHtml($lang['ThreatAgentFactors']) . "</h5>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['SkillLevel']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("SkillLevel", $OWASPSkillLevel, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('SkillLevelHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['Motive']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("Motive", $OWASPMotive, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('MotiveHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['Opportunity']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("Opportunity", $OWASPOpportunity, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('OpportunityHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['Size']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("Size", $OWASPSize, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('SizeHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>

                    <div class='row mt-4 mb-2'>
                        <div class='col-12'>
                            <h5>" . $escaper->escapeHtml($lang['VulnerabilityFactors']) . "</h5>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['EaseOfDiscovery']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("EaseOfDiscovery", $OWASPEaseOfDiscovery, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('EaseOfDiscoveryHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['EaseOfExploit']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("EaseOfExploit", $OWASPEaseOfExploit, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('EaseOfExploitHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['Awareness']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("Awareness", $OWASPAwareness, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('AwarenessHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['IntrusionDetection']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("IntrusionDetection", $OWASPIntrusionDetection, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('IntrusionDetectionHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>

                    <div class='row mt-4 mb-2'>
                        <div class='col-12'>
                            <h5>" . $escaper->escapeHtml($lang['TechnicalImpact']) . "</h5>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['LossOfConfidentiality']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("LossOfConfidentiality", $OWASPLossOfConfidentiality, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('LossOfConfidentialityHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['LossOfIntegrity']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("LossOfIntegrity", $OWASPLossOfIntegrity, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('LossOfIntegrityHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['LossOfAvailability']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("LossOfAvailability", $OWASPLossOfAvailability, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('LossOfAvailabilityHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['LossOfAccountability']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("LossOfAccountability", $OWASPLossOfAccountability, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('LossOfAccountabilityHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>

                    <div class='row mt-4 mb-2'>
                        <div class='col-12'>
                            <h5>" . $escaper->escapeHtml($lang['BusinessImpact']) . "</h5>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['FinancialDamage']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("FinancialDamage", $OWASPFinancialDamage, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('FinancialDamageHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['ReputationDamage']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("ReputationDamage", $OWASPReputationDamage, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('ReputationDamageHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['NonCompliance']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("NonCompliance", $OWASPNonCompliance, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('NonComplianceHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col-5'>
                            <label>" . $escaper->escapeHtml($lang['PrivacyViolation']) . ":</label>
                        </div>
                        <div class='col-6'>
    ";
                            create_numeric_dropdown("PrivacyViolation", $OWASPPrivacyViolation, false);
    echo "
                        </div>
                        <div class='col-1'>
                            <a type='button' class='btn score--help' href='#' onClick=\"javascript:showHelp('PrivacyViolationHelp');\"><i class='fa fa-question-circle'></i></a>
                        </div>
                    </div>
                </div>
                <div class='col-12 col-md-8 p-l-40'>
    ";
                    view_owasp_help();
    echo "
                </div>
            </div>
            <div class='form-actions mt-2'>
                <button type='button' class='btn btn-primary cancel-update'>" . $escaper->escapeHtml($lang['Cancel']) . "</button>
                <button type='submit' name='update_owasp' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Update']) . "</button>
            </div>
        </form>
    ";

}

/*******************************
* FUNCTION: edit_custom_score *
*******************************/
function edit_custom_score($custom)
{
    global $lang;
    global $escaper;

    echo "
        <form name='update_custom' method='post' action=''>
            <div class='row mb-2'>
                <div class='col-12'>
                    <h4>" . $escaper->escapeHtml($lang['UpdateCustomScore']) . "</h4>
                </div>
            </div>
            <div class='row'>
                <div class='col-12 col-md-6'>
                    <div class='row mb-2'>
                        <div class='col col-md-4 col-form-label'>
                            <label>" . $escaper->escapeHtml($lang['ManuallyEnteredValue']) . ":</label>
                        </div>
                        <div class='col col-md-2'>
                            <input type='number' class='form-control' min='0' max='10' name='Custom' id='Custom' style='width:70px;' value='" . $escaper->escapeHtml($custom) . "' step='0.1' />
                        </div>
                        <div class='col col-md-6 col-form-label'>
                            (Must be a numeric value between 0 and 10)
                        </div>
                    </div>
                </div>
            </div>
            <div class='form-actions mt-2'>
                <button type='button' class='btn btn-primary cancel-update'>" . $escaper->escapeHtml($lang['Cancel']) . "</button>
                <button type='submit' name='update_custom' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Update']) . "</button>
            </div>
        </form>
    ";
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
    
    echo "
        <form name='update_contributing_risk' method='post' action=''>
            <div class='row mb-2'>
                <div class='col-12'>
                    <h4>" . $escaper->escapeHtml($lang['UpdateContributingRiskScore']) . "</h4>
                </div>
            </div>
            <div class='row'>
                <div class='col-12 col-md-6'>
                    <div class='row mb-2 align-items-center'>
                        <div class='col col-md-4'>
                            <label>" . $escaper->escapeHtml($lang['ContributingLikelihood']) . ":</label>
                        </div>
                        <div class='col col-md-4'>" . 
                            create_dropdown("contributing_risks_likelihood", $ContributingLikelihood, "ContributingLikelihood", false, false, true) . "
                        </div>
                    </div>
                    <div class='row my-3'>
                        <div class='col-12'>
                            <h5>" . $escaper->escapeHtml($lang['ContributingRisk']) . "</h5>
                        </div>
                    </div>
                    <div class='row mb-2 align-items-center'>
                        <div class='col col-md-4'>
                            <h5>" . $escaper->escapeHtml($lang['Subject']) .  "</h5>
                        </div>
                        <div class='col col-md-4'>
                            <h5>" . $escaper->escapeHtml($lang['Weight']) . "</h5>
                        </div>
                        <div class='col col-md-4'>
                            <h5>" . $escaper->escapeHtml($lang['Impact']) . "</h5>
                        </div>
                    </div>
    ";

    foreach($contributing_risks as $contributing_risk){

        $impacts = get_impact_values_from_contributing_risks_id($contributing_risk['id']);
        $max_impact = max(array_column($impacts, 'value'));
        $impact = empty($ContributingImpacts[$contributing_risk["id"]]) ? $max_impact : $ContributingImpacts[$contributing_risk["id"]];
        
        echo "
                    <div class='row mb-2 align-items-center'>
                        <div class='col col-md-4'>" . 
                            $escaper->escapeHtml($contributing_risk['subject']) . "
                        </div>
                        <div class='col col-md-4'>" . 
                            $escaper->escapeHtml($contributing_risk['weight']) . "
                        </div>
                        <div class='col col-md-4'>" . 
                            create_dropdown("", $impact, "ContributingImpacts[{$contributing_risk["id"]}]", false, false, true, "", "--", "", true, 0, $impacts) . "
                        </div>
                    </div>
        ";
    }

    echo "
                </div>
            </div>
            <div class='form-actions'>
                <button type='button' class='btn btn-primary cancel-update'>" . $escaper->escapeHtml($lang['Cancel']) . "</button>
                <button type='submit' name='update_contributing_risk' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Update']) . "</button>
            </div>
        </form>
    ";

    // echo "<h4>" . $escaper->escapeHtml($lang['UpdateContributingRiskScore']) . "</h4>\n";
    // echo "<form name=\"update_contributing_risk\" method=\"post\" action=\"\">\n";
    // echo "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    // echo "<tr>\n";
    // echo "<td colspan=\"4\">&nbsp;</td>\n";
    // echo "</tr>\n";

    // echo "<tr>\n";
    // echo "<td width=\"175\">" . $escaper->escapeHtml($lang['ContributingLikelihood']) . ":</td>\n";
    // echo "<td width=\"200\">\n";
    // create_dropdown("contributing_risks_likelihood", $ContributingLikelihood, "ContributingLikelihood", false);
    // echo "</td>\n";
    // echo "<td colspan='2'>&nbsp;</td>\n";
    // echo "</tr>\n";
    
    // echo "<tr>\n";
    // echo "<td colspan=\"4\"><b class=\"section--header\">" . $escaper->escapeHtml($lang['ContributingRisk']) . "</b></td>\n";
    // echo "</tr>\n";

    // echo "<tr>\n";
    // echo "<td ><b>" . $escaper->escapeHtml($lang["Subject"]) . "</b></td>\n";
    // echo "<td ><b>" . $escaper->escapeHtml($lang["Weight"]) . "</b></td>\n";
    // echo "<td ><b>" . $escaper->escapeHtml($lang["Impact"]) . "</b></td>\n";
    // echo "<td>&nbsp;</td>\n";
    // echo "</tr>\n";

    // foreach($contributing_risks as $contributing_risk){
    //     $impacts = get_impact_values_from_contributing_risks_id($contributing_risk['id']);
    //     $max_impact = max(array_column($impacts, 'value'));
    //     echo "<tr>\n";
    //     echo "<td >" . $escaper->escapeHtml($contributing_risk['subject']) . "</td>\n";
    //     echo "<td >" . $escaper->escapeHtml($contributing_risk['weight']) . "</td>\n";
    //     $impact = empty($ContributingImpacts[$contributing_risk["id"]]) ? $max_impact : $ContributingImpacts[$contributing_risk["id"]];
    //     echo "<td >\n";
    //     create_dropdown("", $impact, "ContributingImpacts[{$contributing_risk["id"]}]", false, false, false, "", "--", "", true, 0, $impacts);
    //     echo "</td>\n";
    //     echo "<td>&nbsp;</td>\n";
    //     echo "</tr>\n";
    // }
    
    // echo "</table>\n";

    // echo "<div class=\"form-actions\">\n";
    // echo "<button type=\"submit\" name=\"update_contributing_risk\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
    // echo "</div>\n";
    // echo "</form>\n";
}

/***********************************
* FUNCTION: CLASSIC SCORING TABLE *
***********************************/
function classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact,$type=0)
{

    global $lang;
    global $escaper;

    echo "
        <div class='row mb-2 align-items-center'>
            <div class='col-6'>
                <h4>" . $escaper->escapeHtml($lang['ClassicRiskScoring']) . "</h4>
            </div>
            <div class='col-6 text-end'>
                <button type='button' class='btn btn-primary update-score'>" . $escaper->escapeHtml($lang['UpdateClassicScore']) . "</button>
                <button type='button' class='btn btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>" . $escaper->escapeHtml($lang['RiskScoringActions']) . "</button>
                <ul class='dropdown-menu'>
                    <li><a class='dropdown-item score-action' data-method='2' href='#'>" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='3' href='#'>" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='4' href='#'>" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='5' href='#'>" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='6' href='#'>" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>
                </ul>
            </div>
        </div>
        
        <table width='100%' class='table table-borderless mb-0' cellpadding='0' cellspacing='0'>
            <tr>
                <td width='180'>" . $escaper->escapeHtml($lang['Likelihood']) . ":</td>
                <td width='40'>[ " . $escaper->escapeHtml($CLASSIC_likelihood) . " ]</td>
                <td>" . $escaper->escapeHtml(get_name_by_value("likelihood", $CLASSIC_likelihood)) . "</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td width='200'>" . $escaper->escapeHtml($lang['Impact']) . ":</td>
                <td width='80'>[ " . $escaper->escapeHtml($CLASSIC_impact) . " ]</td>
                <td>" . $escaper->escapeHtml(get_name_by_value("impact", $CLASSIC_impact)) . "</td>
                <td>&nbsp;</td>
            </tr>
    ";

    if (get_setting("risk_model") == 1) {
        echo "
            <tr>
                <td colspan='4'><b>" . $escaper->escapeHtml($lang['RISKClassicExp1']) . " x ( 10 / 35 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>
            </tr>
        ";
    } else if (get_setting("risk_model") == 2) {
        echo "
            <tr>
                <td colspan='4'><b>" . $escaper->escapeHtml($lang['RISKClassicExp2']) . " x ( 10 / 30 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>
            </tr>
        ";
    } else if (get_setting("risk_model") == 3) {
        echo "
            <tr>
                <td colspan='4'><b>" . $escaper->escapeHtml($lang['RISKClassicExp3']) . " x ( 10 / 25 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>
            </tr>
        ";
    } else if (get_setting("risk_model") == 4)
    {
        echo "
            <tr>
                <td colspan='4'><b>" . $escaper->escapeHtml($lang['RISKClassicExp4']) . " x ( 10 / 30 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>
            </tr>
        ";
    } else if (get_setting("risk_model") == 5) {
        echo "
            <tr>
                <td colspan='4'><b>" . $escaper->escapeHtml($lang['RISKClassicExp5']) . " x ( 10 / 35 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>
            </tr>
        ";
    }

    echo "
        </table>
    ";
}

/********************************
* FUNCTION: CVSS SCORING TABLE *
********************************/
function cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement,$type=0)
{
    global $lang;
    global $escaper;

    echo "
        <div class='row mb-2 align-items-center'>
            <div class='col-6'>
                <h4>" . $escaper->escapeHtml($lang['CVSSRiskScoring']) . "</h4>
            </div>
            <div class='col-6 text-end'>
                <button type='button' class='btn btn-primary update-score'>" . $escaper->escapeHtml($lang['UpdateCVSSScore']) . "</button>
                <button type='button' class='btn btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>" . $escaper->escapeHtml($lang['RiskScoringActions']) . "</button>
                <ul class='dropdown-menu'>
                    <li><a class='dropdown-item score-action' data-method='1' href='#'>" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='3' href='#'>" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='4' href='#'>" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='5' href='#'>" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='6' href='#'>" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>
                </ul>
            </div>
        </div>
        
        <table width='100%' class='table table-borderless mb-0' cellpadding='0'' cellspacing='0'>
            <tr>
                <td colspan='6'>" . $escaper->escapeHtml($lang['BaseVector']) . ": AV:" . $escaper->escapeHtml($AccessVector) . "/AC:" . $escaper->escapeHtml($AccessComplexity) . "/Au:" . $escaper->escapeHtml($Authentication) . "/C:" . $escaper->escapeHtml($ConfImpact) . "/I:" . $escaper->escapeHtml($IntegImpact) . "/A:" . $escaper->escapeHtml($AvailImpact) . "</td>
            </tr>
            <tr>
                <td colspan='6'>" . $escaper->escapeHtml($lang['TemporalVector']) . ": E:" . $escaper->escapeHtml($Exploitability) . "/RL:" . $escaper->escapeHtml($RemediationLevel) . "/RC:" . $escaper->escapeHtml($ReportConfidence) . "</td>
            </tr>
            <tr>
                <td colspan='6'>" . $escaper->escapeHtml($lang['EnvironmentalVector']) . ": CDP:" . $escaper->escapeHtml($CollateralDamagePotential) . "/TD:" . $escaper->escapeHtml($TargetDistribution) . "/CR:" . $escaper->escapeHtml($ConfidentialityRequirement) . "/IR:" . $escaper->escapeHtml($IntegrityRequirement) . "/AR:" . $escaper->escapeHtml($AvailabilityRequirement) . "</td>
            </tr>
            <tr><td colspan='6'>&nbsp;</td></tr>
            <tr class='fw-bold'>
                <td colspan='2'>" . $escaper->escapeHtml($lang['BaseScoreMetrics']) . "</td>
                <td colspan='2'>" . $escaper->escapeHtml($lang['TemporalScoreMetrics']) . "</td>
                <td colspan='2'>" . $escaper->escapeHtml($lang['EnvironmentalScoreMetrics']) . "</td>
            </tr>
            <tr>
                <td width='20%'>" . $escaper->escapeHtml($lang['AttackVector']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("AccessVector", $AccessVector)) . "</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>
                <td width='10%''>" . $escaper->escapeHtml(get_cvss_name("Exploitability", $Exploitability)) . "</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['CollateralDamagePotential']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("CollateralDamagePotential", $CollateralDamagePotential)) . "</td>
            </tr>
            <tr>
                <td width='20%'>" . $escaper->escapeHtml($lang['AttackComplexity']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("AccessComplexity", $AccessComplexity)) . "</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['RemediationLevel']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("RemediationLevel", $RemediationLevel)) . "</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['TargetDistribution']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("TargetDistribution", $TargetDistribution)) . "</td>
            </tr>
            <tr>
                <td width='20%'>" . $escaper->escapeHtml($lang['Authentication']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("Authentication", $Authentication)) . "</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['ReportConfidence']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("ReportConfidence", $ReportConfidence)) . "</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['ConfidentialityRequirement']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("ConfidentialityRequirement", $ConfidentialityRequirement)) . "</td>
            </tr>
            <tr>
                <td width='20%'>" . $escaper->escapeHtml($lang['ConfidentialityImpact']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("ConfImpact", $ConfImpact)) . "</td>
                <td width='20%'>&nbsp;</td>
                <td width='10%'>&nbsp</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['IntegrityRequirement']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("IntegrityRequirement", $IntegrityRequirement)) . "</td>
            </tr>
            <tr>
                <td width='20%'>" . $escaper->escapeHtml($lang['IntegrityImpact']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("IntegImpact", $IntegImpact)) . "</td>
                <td width='20%'>&nbsp;</td>
                <td width='10%'>&nbsp</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['AvailabilityRequirement']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("AvailabilityRequirement", $AvailabilityRequirement)) . "</td>
            </tr>
            <tr>
                <td width='20%'>" . $escaper->escapeHtml($lang['AvailabilityImpact']) . ":</td>
                <td width='10%'>" . $escaper->escapeHtml(get_cvss_name("AvailImpact", $AvailImpact)) . "</td>
                <td width='20%'>&nbsp;</td>
                <td width='10%'>&nbsp</td>
                <td width='20%'>&nbsp;</td>
                <td width='10%'>&nbsp</td>
            </tr>
            <tr>
                <td colspan='6'>&nbsp;</td>
            </tr>
            <tr>
                <td colspan='6'><strong>Full details of CVSS Version 2.0 scoring can be found <a href='https://www.first.org/cvss/v2/guide' class='link-success' target='_blank'>here</a>.</strong></td>
            </tr>
        </table>
    ";
}

/*********************************
* FUNCTION: DREAD SCORING TABLE *
*********************************/
function dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability,$type=0)
{
    global $lang;
    global $escaper;

    echo "
        <div class='row mb-2 align-items-center'>
            <div class='col-6'>
                <h4>". $escaper->escapeHtml($lang['DREADRiskScoring']) . "</h4>
            </div>
            <div class='col-6 text-end'>
                <button type='button' class='btn btn-primary update-score'>" . $escaper->escapeHtml($lang['UpdateDREADScore']) . "</button>
                <button type='button' class='btn btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>" . $escaper->escapeHtml($lang['RiskScoringActions']) . "</button>
                <ul class='dropdown-menu'>
                    <li><a class='dropdown-item score-action' data-method='1' href='#'>" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='2' href='#'>" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='4' href='#'>" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='5' href='#'>" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='6' href='#'>" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>
                </ul>
            </div>
        </div>
        
        <table width='100%' class='table table-borderless mb-0'>
            <tr>
                <td width='150'>" . $escaper->escapeHtml($lang['DamagePotential']) . ":</td>
                <td>" . $escaper->escapeHtml($DREADDamagePotential) . "</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td width='150'>" . $escaper->escapeHtml($lang['Reproducibility']) . ":</td>
                <td>" . $escaper->escapeHtml($DREADReproducibility) . "</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td width='150'>" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>
                <td>" . $escaper->escapeHtml($DREADExploitability) . "</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td width='150'>" . $escaper->escapeHtml($lang['AffectedUsers']) . ":</td>
                <td>" . $escaper->escapeHtml($DREADAffectedUsers) . "</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td width='150'>" . $escaper->escapeHtml($lang['Discoverability']) . ":</td>
                <td>" . $escaper->escapeHtml($DREADDiscoverability) . "</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td colspan='3'><b>RISK = ( " . $escaper->escapeHtml($DREADDamagePotential) . " + " . $escaper->escapeHtml($DREADReproducibility) . " + " . $escaper->escapeHtml($DREADExploitability) . " + " . $escaper->escapeHtml($DREADAffectedUsers) . " + " . $escaper->escapeHtml($DREADDiscoverability) . " ) / 5 = " . $escaper->escapeHtml($calculated_risk) . "</b></td>
            </tr>
        </table>
    ";
}

/*********************************
* FUNCTION: OWASP SCORING TABLE *
*********************************/
function owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation,$type=0)
{
    global $lang;
    global $escaper;

    echo "
        <div class='row mb-2'>
            <div class='col-6'>
                <h4>" . $escaper->escapeHtml($lang['OWASPRiskScoring']) . "</h4>
            </div>
            <div class='col-6 text-end'>
                    <button type='button' class='btn btn-primary update-score'>". $escaper->escapeHtml($lang['UpdateOWASPScore']) . "</button>
                    <button type='button' class='btn btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>" . $escaper->escapeHtml($lang['RiskScoringActions']) . "</button>
                    <ul class='dropdown-menu'>
                        <li><a class='dropdown-item score-action' data-method='1' href='#'>" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>
                        <li><a class='dropdown-item score-action' data-method='2' href='#'>" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>
                        <li><a class='dropdown-item score-action' data-method='3' href='#'>" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>
                        <li><a class='dropdown-item score-action' data-method='5' href='#'>" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>
                        <li><a class='dropdown-item score-action' data-method='6' href='#'>" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>
                    </ul>
            </div>
        </div>

        <table width='100%' class='table table-borderless mb-0'>
            <tr>
                <td colspan='2'><b class='section--header'>" . $escaper->escapeHtml($lang['ThreatAgentFactors']) . "</b></td>
                <td colspan='2'><b class='section--header'>" . $escaper->escapeHtml($lang['VulnerabilityFactors']) . "</b></td>
                <td colspan='2'><b class='section--header'>" . $escaper->escapeHtml($lang['TechnicalImpact']) . "</b></td>
                <td colspan='2'><b class='section--header'>" . $escaper->escapeHtml($lang['BusinessImpact']) . "</b></td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td width='20%'>" . $escaper->escapeHtml($lang['SkillLevel']) . ":</td>
                <td width='5%' class='vtop'>" . $escaper->escapeHtml($OWASPSkillLevel) . "</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['EaseOfDiscovery']) . ":</td>
                <td width='5%' class='vtop'>" . $escaper->escapeHtml($OWASPEaseOfDiscovery) . "</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['LossOfConfidentiality']) . ":</td>
                <td width='5%' class='vtop'>" . $escaper->escapeHtml($OWASPLossOfConfidentiality) . "</td>
                <td width='20%'>" . $escaper->escapeHtml($lang['FinancialDamage']) . ":</td>
                <td width='5%' class='vtop'>" . $escaper->escapeHtml($OWASPFinancialDamage) . "</td>
            </tr>
            <tr>
                <td>" . $escaper->escapeHtml($lang['Motive']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPMotive) . "</td>
                <td>" . $escaper->escapeHtml($lang['EaseOfExploit']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPEaseOfExploit) . "</td>
                <td>" . $escaper->escapeHtml($lang['LossOfIntegrity']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPLossOfIntegrity) . "</td>
                <td>" . $escaper->escapeHtml($lang['ReputationDamage']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPReputationDamage) . "</td>
            </tr>
            <tr>
                <td>" . $escaper->escapeHtml($lang['Opportunity']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPOpportunity) . "</td>
                <td>" . $escaper->escapeHtml($lang['Awareness']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPAwareness) . "</td>
                <td>" . $escaper->escapeHtml($lang['LossOfAvailability']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPLossOfAvailability) . "</td>
                <td>" . $escaper->escapeHtml($lang['NonCompliance']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPNonCompliance) . "</td>
            </tr>
            <tr>
                <td>" . $escaper->escapeHtml($lang['Size']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPSize) . "</td>
                <td>" . $escaper->escapeHtml($lang['IntrusionDetection']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPIntrusionDetection) . "</td>
                <td>" . $escaper->escapeHtml($lang['LossOfAccountability']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPLossOfAccountability) . "</td>
                <td>" . $escaper->escapeHtml($lang['PrivacyViolation']) . ":</td>
                <td>" . $escaper->escapeHtml($OWASPPrivacyViolation) . "</td>
            </tr>
            <tr>
                <td colspan='9'>&nbsp;</td>
            </tr>
            <tr>
                <td colspan='4'><b class='section--header'>" . $escaper->escapeHtml($lang['Likelihood']) . "</b></td>
                <td colspan='4'><b class='section--header'>" . $escaper->escapeHtml($lang['Impact']) . "</b></td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td colspan='4'>" . $escaper->escapeHtml($lang['ThreatAgentFactors']) . " = ( " . $escaper->escapeHtml($OWASPSkillLevel) . " + " . $escaper->escapeHtml($OWASPMotive) . " + " . $escaper->escapeHtml($OWASPOpportunity) . " + " . $escaper->escapeHtml($OWASPSize) . " ) / 4</td>
                <td colspan='4'>" . $escaper->escapeHtml($lang['TechnicalImpact']) . " = ( " . $escaper->escapeHtml($OWASPLossOfConfidentiality) . " + " . $escaper->escapeHtml($OWASPLossOfIntegrity) . " + " . $escaper->escapeHtml($OWASPLossOfAvailability) . " + " . $escaper->escapeHtml($OWASPLossOfAccountability) . " ) / 4</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td colspan='4'>" . $escaper->escapeHtml($lang['VulnerabilityFactors']) . " = ( " . $escaper->escapeHtml($OWASPEaseOfDiscovery) . " + " . $escaper->escapeHtml($OWASPEaseOfExploit) . " + " . $escaper->escapeHtml($OWASPAwareness) . " + " . $escaper->escapeHtml($OWASPIntrusionDetection) . " ) / 4</td>
                <td colspan='4'>" . $escaper->escapeHtml($lang['BusinessImpact']) . " = ( " . $escaper->escapeHtml($OWASPFinancialDamage) . " + " . $escaper->escapeHtml($OWASPReputationDamage) . " + " . $escaper->escapeHtml($OWASPNonCompliance) . " + " . $escaper->escapeHtml($OWASPPrivacyViolation) . " ) / 4</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td colspan='9'>&nbsp;</td>
            </tr>
            <tr>
                <td colspan='9'><strong>Full details of the OWASP Risk Rating Methodology can be found <a href='https://owasp.org/www-community/OWASP_Risk_Rating_Methodology' class='link-success' target='_blank'>here</a>.</strong></td>
            </tr>
        </table>
    ";
}

/**********************************
* FUNCTION: CUSTOM SCORING TABLE *
**********************************/
function custom_scoring_table($id, $custom,$type=0)
{
    global $lang;
    global $escaper;

    echo "
        <div class='row mb-2 align-items-center'>
            <div class='col-6'>
                <h4>" . $escaper->escapeHtml($lang['CustomRiskScoring']) . "</h4>
            </div>
            <div class='col-6 text-end'>
                <button type='button' class='btn btn-primary update-score'>" . $escaper->escapeHtml($lang['UpdateCustomScore']) . "</button>
                <button type='button' class='btn btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>" . $escaper->escapeHtml($lang['RiskScoringActions']) . "</button>
                <ul class='dropdown-menu'>
                    <li><a class='dropdown-item score-action' data-method='1' href='#'>" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='2' href='#'>" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='3' href='#'>" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='4' href='#'>" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='6' href='#'>" . $escaper->escapeHtml($lang['ScoreByContributingRisk']) . "</a></li>
                </ul>
            </div>
        </div>
        <div class='row mb-2'>
            <div class='col-12'><label>" . $escaper->escapeHtml($lang['ManuallyEnteredValue']) . ":</label> &nbsp; ".$escaper->escapeHtml($custom) . "</div>
        </div>
    ";
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
        <div class='row mb-2 align-items-center'>
            <div class='col-6'>
                <h4>" . $escaper->escapeHtml($lang['ContributingRiskScoring']) . "</h4>
            </div>
            <div class='col-6 text-end'>
                <button type='button' class='btn btn-primary update-score'>" . $escaper->escapeHtml($lang['UpdateContributingRiskScore']) . "</button>
                <button type='button' class='btn btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>" . $escaper->escapeHtml($lang['RiskScoringActions']) . "</button>
                <ul class='dropdown-menu'>
                    <li><a class='dropdown-item score-action' data-method='1' href='#'>" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='2' href='#'>" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='3' href='#'>" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='4' href='#'>" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>
                    <li><a class='dropdown-item score-action' data-method='5' href='#'>" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>
                </ul>
            </div>
        </div>
        
        <table width='100%' class='table table-bordered mb-0'>
        <!------<table class='risk_scores' width='100%' cellpadding='0' cellspacing='0' border='1'>   --->
            <tr class='table-dark'>
                <th colspan='2' class='text-center'><u>" . $escaper->escapeHtml($lang['ContributingLikelihood']) . "</u></th>
            </tr>
            <tr class='table-secondary'>
                <th width='50%' class='text-center'>" . $escaper->escapeHtml($lang['Selected']) . "</th>
                <th width='50%' class='text-center'>" . $escaper->escapeHtml($lang['MaximumValue']) . "</th>
            </tr>
            <tr>
                <td align='center'>[ " . $Contributing_Likelihood . " ] ". $Contributing_Likelihood_name . "</td>
                <td align='center'>[ " . $max_likelihood . " ] " . $max_likelihood_name . "</td>
            </tr>
        </table>
        <br>
        <table width='100%' class='table table-bordered table-light'>
        <!------<table class='risk_scores' width='100%' cellpadding='0' cellspacing='0' border='1'>  --->
            <tr class='table-dark'>
                <th colspan='4' class='text-center'><u>" . $escaper->escapeHtml($lang['ContributingImpact']) . "</u></td>
            </tr>
            <tr class='table-secondary'>
                <th width='20%' class='text-center'>" . $escaper->escapeHtml($lang['Subject']) . "</th>
                <th width='20%' class='text-center'>" . $escaper->escapeHtml($lang['Weight']) . "</th>
                <th width='30%' class='text-center'>" . $escaper->escapeHtml($lang['Selected']) . "</th>
                <th width='30%' class='text-center'>" . $escaper->escapeHtml($lang['MaximumValue']) . "</th>
            </tr>
    ";

    $contributing_risks = get_contributing_risks();
    
    $contributing_likelihood_formula = "( " . $escaper->escapeHtml($Contributing_Likelihood) . " X 5 / " . $escaper->escapeHtml($max_likelihood) . " )";
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

        echo "
            <tr>
                <td align='center'>" . $escaper->escapeHtml($contributing_risk['subject']) . "</td>
                <td align='center'>" . $escaper->escapeHtml($contributing_risk['weight']) . "</td>
                <td align='center'>[ " . $escaper->escapeHtml($impact) . " ] " . $escaper->escapeHtml($impact_name) . "</td>
                <td align='center'>[ " . $escaper->escapeHtml($max_impact) . " ] " . $escaper->escapeHtml($max_impact_name) . "</td>
            </tr>
        ";

        $contributing_impact_formula[] = " ( " . $escaper->escapeHtml($contributing_risk['weight']) . " X (" . $escaper->escapeHtml($impact) . " X 5 / " . $escaper->escapeHtml($max_impact) . "))"; 

    }

    echo "
        </table>
        <br>
    ";

    $risk_formula = $contributing_likelihood_formula . " +  (" . implode(" + ", $contributing_impact_formula) . " = " . $escaper->escapeHtml($calculated_risk);
    
    echo "
        <b>RISK = " . $risk_formula . " </b>
    ";
}

/*******************************
* FUNCTION: VIEW CLASSIC HELP *
*******************************/
function view_classic_help()
{

    global $escaper;

    // Get the arrray of likelihood values
    $likelihoods = get_table("likelihood");

    $likelihoods_description = array(
        "May only occur in exceptional circumstances.",
        "Expected to occur in a few circumstances.",
        "Expected to occur in some circumstances.",
        "Expected to occur in many circumstances.",
        "Expected to occur frequently and in most circumstances."
    );

    // Get the array of impact values
    $impacts = get_table("impact");

    $impacts_description = array(
        "No impact on service, no impact on reputation, complaint unlikely, or litigation risk remote.",
        "Slight impact on service, slight impact on reputation, complaint possible, or litigation possible.",
        "Some service disruption, potential for adverse publicity (avoidable with careful handling), complaint probable, or litigation probably.",
        "Service disrupted, adverse publicity not avoidable (local media), complaint probably, or litigation probable.",
        "Service interrupted for significant time, major adverse publicity not avoidable (national media), major litigation expected, resignation of senior management and board, or loss of benficiary confidence."
    );

    echo "
        <div id='divHelp' style='width:100%;overflow:auto'></div>
        <div id='likelihoodHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
    ";

    foreach($likelihoods as $index => $likelihood) {

        $description = isset($likelihoods_description[$index]) ? $likelihoods_description[$index] : "";

        echo "
                        <p><b>" . $escaper->escapeHtml($likelihood['name']) . ":</b> " . $description . "</p>
        ";
    }

    echo "
                    </td>
                </tr>
            </table>
        </div>
        
        <div id='impactHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
    ";

    foreach($impacts as $index => $impact) {

        $description = isset($impacts_description[$index]) ? $impacts_description[$index] : "";

        echo "
                        <p><b>" . $escaper->escapeHtml($impact['name']) . ":</b> " . $description . "</p>
        ";
    }

    echo "
                    </td>
                </tr>
            </table>
        </div>
    ";
}

/*****************************
* FUNCTION: VIEW OWASP HELP *
*****************************/
function view_owasp_help()
{
    echo "
        <div id='divHelp' style='width:100%;overflow:auto'></div>

        <div id='SkillLevelHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How technically skilled is this group of threat agents?</b></p>
                        <p>1 = No Technical Skills</p>
                        <p>3 = Some Technical Skills</p>
                        <p>5 = Advanced Computer User</p>
                        <p>6 = Network and Programming Skills</p>
                        <p>9 = Security Penetration Skills</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='MotiveHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How motivated is this group of threat agents to find and exploit this vulnerability?</b></p>
                        <p>1 = Low or No Reward</p>
                        <p>4 = Possible Reward</p>
                        <p>9 = High Reward</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='OpportunityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>What resources and opportunity are required for this group of threat agents to find and exploit this vulnerability?</b></p>
                        <p>0 = Full Access or Expensive Resources Required</p>
                        <p>4 = Special Access or Resources Required</p>
                        <p>7 = Some Access or Resources Required</p>
                        <p>9 = No Access or Resources Required</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='SizeHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How large is this group of threat agents?</b></p>
                        <p>2 = Developers</p>
                        <p>2 = System Administrators</p>
                        <p>4 = Intranet Users</p>
                        <p>5 = Partners</p>
                        <p>6 = Authenticated Users</p>
                        <p>9 = Anonymous Internet Users</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='EaseOfDiscoveryHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How easy is it for this group of threat agents to discover this vulnerability?</b></p>
                        <p>1 = Practically Impossible</p>
                        <p>3 = Difficult</p>
                        <p>7 = Easy</p>
                        <p>9 = Automated Tools Available</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='EaseOfExploitHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How easy is it for this group of threat agents to actually exploit this vulnerability?</b></p>
                        <p>1 = Theoretical</p>
                        <p>3 = Difficult</p>
                        <p>5 = Easy</p>
                        <p>9 = Automated Tools Available</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='AwarenessHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How well known is this vulnerability to this group of threat agents?</b></p>
                        <p>1 = Unknown</p>
                        <p>4 = Hidden</p>
                        <p>6 = Obvious</p>
                        <p>9 = Public Knowledge</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='IntrusionDetectionHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How likely is an exploit to be detected?</b></p>
                        <p>1 = Active Detection in Application</p>
                        <p>3 = Logged and Reviewed</p>
                        <p>8 = Logged Without Review</p>
                        <p>9 = Not Logged</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='LossOfConfidentialityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How much data could be disclosed and how sensitive is it?</b></p>
                        <p>2 = Minimal Non-Sensitive Data Disclosed</p>
                        <p>6 = Minimal Critical Data Disclosed</p>
                        <p>6 = Extensive Non-Sensitive Data Disclosed</p>
                        <p>7 = Extensive Critical Data Disclosed</p>
                        <p>9 = All Data Disclosed</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='LossOfIntegrityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How much data could be corrupted and how damaged is it?</b></p>
                        <p>1 = Minimal Slightly Corrupt Data</p>
                        <p>3 = Minimal Seriously Corrupt Data</p>
                        <p>5 = Extensive Slightly Corrupt Data</p>
                        <p>7 = Extensive Seriously Corrupt Data</p>
                        <p>9 = All Data Totally Corrupt</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='LossOfAvailabilityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How much service could be lost and how vital is it?</b></p>
                        <p>1 = Minimal Secondary Services Interrupted</p>
                        <p>5 = Minimal Primary Services Interrupted</p>
                        <p>5 = Extensive Secondary Services Interrupted</p>
                        <p>7 = Extensive Primary Services Interrupted</p>
                        <p>9 = All Services Completely Lost</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='LossOfAccountabilityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>Are the threat agents' actions traceable to an individual?</b></p>
                        <p>1 = Fully Traceable</p>
                        <p>7 = Possibly Traceable</p>
                        <p>9 = Completely Anonymous</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='FinancialDamageHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How much financial damage will result from an exploit?</b></p>
                        <p>1 = Less than the Cost to Fix the Vulnerability</p>
                        <p>3 = Minor Effect on Annual Profit</p>
                        <p>7 = Significant Effect on Annual Profit</p>
                        <p>9 = Bankruptcy</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='ReputationDamageHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>Would an exploit result in reputation damage that would harm the business?</b></p>
                        <p>1 = Minimal Damage</p>
                        <p>4 = Loss of Major Accounts</p>
                        <p>5 = Loss of Goodwill</p>
                        <p>9 = Brand Damage</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='NonComplianceHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How much exposure does non-compliance introduce?</b></p>
                        <p>2 = Minor Violation</p>
                        <p>5 = Clear Violation</p>
                        <p>7 = High Profile Violation</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='PrivacyViolationHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How much personally identifiable information could be disclosed?</b></p>
                        <p>3 = One Individual</p>
                        <p>5 = Hundreds of People</p>
                        <p>7 = Thousands of People</p>
                        <p>9 = Millions of People</p>
                    </td>
                </tr>
            </table>
        </div>
    ";
}

/*****************************
* FUNCTION: VIEW CVSS HELP *
*****************************/
function view_cvss_help()
{
    echo "
        <div id='divHelp' style='width:100%;overflow:auto'></div>
        <div id='AccessVectorHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>Local</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>A vulnerability exploitable with only local access requires the attacker to have either physical access to the vulnerable system or a local (shell) account.  Examples of locally exploitable vulnerabilities are peripheral attacks such as Firewire/USB DMA attacks, and local privilege escalations (e.g., sudo).</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Adjacent Network</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>A vulnerability exploitable with adjacent network access requires the attacker to have access to either the broadcast or collision domain of the vulnerable software.  Examples of local networks include local IP subnet, Bluetooth, IEEE 802.11, and local Ethernet segment.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Network</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>A vulnerability exploitable with network access means the vulnerable software is bound to the network stack and the attacker does not require local network access or local access.  Such a vulnerability is often termed 'remotely exploitable'.  An example of a network attack is an RPC buffer overflow.</td>
                </tr>
            </table>
        </div>

        <div id='AccessComplexityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>High</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>
                        Specialized access conditions exist. For example:
                        <ul>
                            <li>In most configurations, the attacking party must already have elevated privileges or spoof additional systems in addition to the attacking system (e.g., DNS hijacking).</li>
                            <li>The attack depends on social engineering methods that would be easily detected by knowledgeable people. For example, the victim must perform several suspicious or atypical actions.</li>
                            <li>The vulnerable configuration is seen very rarely in practice.</li>
                            <li>If a race condition exists, the window is very narrow.</li>
                        </ul>
                    </td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Medium</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>
                        The access conditions are somewhat specialized; the following are examples:
                        <ul>
                            <li>The attacking party is limited to a group of systems or users at some level of authorization, possibly untrusted.</li>
                            <li>Some information must be gathered before a successful attack can be launched.</li>
                            <li>The affected configuration is non-default, and is not commonly configured (e.g., a vulnerability present when a server performs user account authentication via a specific scheme, but not present for another authentication scheme).</li>
                            <li>The attack requires a small amount of social engineering that might occasionally fool cautious users (e.g., phishing attacks that modify a web browsers status bar to show a false link, having to be on someones buddy list before sending an IM exploit).</li>
                        </ul>
                    </td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Low</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>
                        Specialized access conditions or extenuating circumstances do not exist. The following are examples:
                        <ul>
                            <li>The affected product typically requires access to a wide range of systems and users, possibly anonymous and untrusted (e.g., Internet-facing web or mail server).</li>
                            <li>The affected configuration is default or ubiquitous.</li>
                            <li>The attack can be performed manually and requires little skill or additional information gathering.</li>
                            <li>The race condition is a lazy one (i.e., it is technically a race but easily winnable).</li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>

        <div id='AuthenticationHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>None</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Authentication is not required to exploit the vulnerability.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Single Instance</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>The vulnerability requires an attacker to be logged into the system (such as at a command line or via a desktop session or web interface).</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Multiple Instances</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Exploiting the vulnerability requires that the attacker authenticate two or more times, even if the same credentials are used each time. An example is an attacker authenticating to an operating system in addition to providing credentials to access an application hosted on that system.</td>
                </tr>
            </table>
        </div>

        <div id='ConfImpactHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>None</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is no impact to the confidentiality of the system.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Partial</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is considerable informational disclosure. Access to some system files is possible, but the attacker does not have control over what is obtained, or the scope of the loss is constrained. An example is a vulnerability that divulges only certain tables in a database.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Complete</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is total information disclosure, resulting in all system files being revealed. The attacker is able to read all of the system's data (memory, files, etc.)</td>
                </tr>
            </table>
        </div>

        <div id='IntegImpactHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>None</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is no impact to the integrity of the system.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Partial</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Modification of some system files or information is possible, but the attacker does not have control over what can be modified, or the scope of what the attacker can affect is limited. For example, system or application files may be overwritten or modified, but either the attacker has no control over which files are affected or the attacker can modify files within only a limited context or scope.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Complete</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is a total compromise of system integrity. There is a complete loss of system protection,resulting in the entire system being compromised. The attacker is able to modify any files on the target system.</td>
                </tr>
            </table>
        </div>

        <div id='AvailImpactHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>None</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is no impact to the availability of the system.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Partial</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is reduced performance or interruptions in resource availability. An example is a network-based flood attack that permits a limited number of successful connections to an Internet service.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Complete</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is a total shutdown of the affected resource. The attacker can render the resource completely unavailable.</td>
                </tr>
            </table>
        </div>

        <div id='ExploitabilityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>Unproven that exploit exists</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>No exploit code is available, or an exploit is entirely theoretical.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Proof of concept code</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Proof-of-concept exploit code or an attack demonstration that is not practical for most systems is available. The code or technique is not functional in all situations and may require substantial modification by a skilled attacker.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Functional exploit exists</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Functional exploit code is available. The code works in most situations where the vulnerability exists.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Widespread</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Either the vulnerability is exploitable by functional mobile autonomous code, or no exploit is required (manual trigger) and details are widely available. The code works in every situation, or is actively being delivered via a mobile autonomous agent (such as a worm or virus).</td>
                </tr>
            </table>
        </div>

        <div id='RemediationLevelHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>Official Fix</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>A complete vendor solution is available. Either the vendor has issued an official patch, or an upgrade is available.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Temporary Fix</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is an official but temporary fix available. This includes instances where the vendor issues a temporary hotfix, tool, or workaround.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Workaround</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is an unofficial, non-vendor solution available. In some cases, users of the affected technology will create a patch of their own or provide steps to work around or otherwise mitigate the vulnerability.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Unavailable</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is either no solution available or it is impossible to apply.</td>
                </tr>
            </table>
        </div>

        <div id='ReportConfidenceHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>Not Confirmed</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is a single unconfirmed source or possibly multiple conflicting reports. There is little confidence in the validity of the reports. An example is a rumor that surfaces from the hacker underground.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Uncorroborated</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There are multiple non-official sources, possibly including independent security companies or research organizations. At this point there may be conflicting technical details or some other lingering ambiguity.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Confirmed</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>The vulnerability has been acknowledged by the vendor or author of the affected technology. The vulnerability may also be ?Confirmed? when its existence is confirmed from an external event such as publication of functional or proof-of-concept exploit code or widespread exploitation.</td>
                </tr>
            </table>
        </div>

        <div id='CollateralDamagePotentialHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>None</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>There is no potential for loss of life, physical assets, productivity or revenue.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Low</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>A successful exploit of this vulnerability may result in slight physical or property damage. Or, there may be a slight loss of revenue or productivity to the organization.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Low-Medium</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>A successful exploit of this vulnerability may result in moderate physical or property damage. Or, there may be a moderate loss of revenue or productivity to the organization.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Medium-High</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>A successful exploit of this vulnerability may result in significant physical or property damage or loss. Or, there may be a significant loss of revenue or productivity.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>High</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>A successful exploit of this vulnerability may result in catastrophic physical or property damage and loss. Or, there may be a catastrophic loss of revenue or productivity.</td>
                </tr>
            </table>
        </div>

        <div id='TargetDistributionHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>None</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>No target systems exist, or targets are so highly specialized that they only exist in a laboratory setting. Effectively 0% of the environment is at risk.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Low</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Targets exist inside the environment, but on a small scale. Between 1% - 25% of the total environment is at risk.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Medium</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Targets exist inside the environment, but on a medium scale. Between 26% - 75% of the total environment is at risk.</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>High</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Targets exist inside the environment on a considerable scale. Between 76% - 100% of the total environment is considered at risk.</td>
                </tr>
            </table>
        </div>

        <div id='ConfidentialityRequirementHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>Low</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Loss of confidentiality is likely to have only a limited adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Medium</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Loss of confidentiality is likely to have a serious adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>High</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Loss of confidentiality is likely to have a catastrophic adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>
                </tr>
            </table>
        </div>

        <div id='IntegrityRequirementHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>Low</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Loss of integrity is likely to have only a limited adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Medium</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Loss of integrity is likely to have a serious adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>High</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Loss of integrity is likely to have a catastrophic adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>
                </tr>
            </table>
        </div>

        <div id='AvailabilityRequirementHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-head no-border'><b>Low</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Loss of availability is likely to have only a limited adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>Medium</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Loss of availability is likely to have a serious adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <td class='cal-head'><b>High</b></td>
                </tr>
                <tr>
                    <td class='cal-text'>Loss of availability is likely to have a catastrophic adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>
                </tr>
            </table>
        </div>
    ";

}

/*****************************
* FUNCTION: VIEW DREAD HELP *
*****************************/
function view_dread_help()
{
    echo "
        <div id='divHelp' style='width:100%;overflow:auto'></div>

        <div id='DamagePotentialHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>If a threat exploit occurs, how much damage will be caused?</b></p>
                        <p>0 = Nothing</p>
                        <p>5 = Individual user data is compromised or affected.</p>
                        <p>10 = Complete system or data destruction</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='ReproducibilityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How easy is it to reproduce the threat exploit?</b></p>
                        <p>0 = Very hard or impossible, even for administrators of the application.</p>
                        <p>5 = One or two steps required, may need to be an authorized user.</p>
                        <p>10 = Just a web browser and the address bar is sufficient, without authentication.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='ExploitabilityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>What is needed to exploit this threat?</b></p>
                        <p>0 = Advanced programming and networking knowledge, with custom or advanced attack tools.</p>
                        <p>5 = Malware exists on the Internet, or an exploit is easily performed, using available attack tools.</p>
                        <p>10 = Just a web browser</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='AffectedUsersHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How many users will be affected?</b></p>
                        <p>0 = None</p>
                        <p>5 = Some users, but not all</p>
                        <p>10 = All users</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id='DiscoverabilityHelp'  style='display:none; visibility:hidden'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                <tr>
                    <td class='cal-text'>
                        <br />
                        <p><b>How easy is it to discover this threat?</b></p>
                        <p>0 = Very hard to impossible; requires source code or administrative access.</p>
                        <p>5 = Can figure it out by guessing or by monitoring network traces.</p>
                        <p>9 = Details of faults like this are already in the public domain and can be easily discovered using a search engine.</p>
                        <p>10 = The information is visible in the web browser address bar or in a form.</p>
                    </td>
                </tr>
            </table>
        </div>
    ";
}

/***************************
* FUNCTION: VIEW TOP MENU *
***************************/
function view_top_menu($active)
{
    global $lang, $escaper;

    echo "<script>\n";
    echo "var BASE_URL = '". (isset($_SESSION['base_url']) ? $escaper->escapeHtml($_SESSION['base_url']) : "") ."'; \n";
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
            echo "<a href=\"".$_SESSION['base_url']."/api/v2/documentation.php\" target=\"_blank\"><i class=\"fa fa-book\"></i>&nbsp&nbsp;". $escaper->escapeHtml($lang['APIDocumentation']) ."</a>\n";
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
    echo "<a href=\"manage_assets.php\"> <span>2</span>" . $escaper->escapeHtml($lang['ManageAssets']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "ManageAssetGroups" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"manage_asset_groups.php\"> <span>3</span>" . $escaper->escapeHtml($lang['ManageAssetGroups']) . "</a>\n";
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
function view_risks_and_assets_selections($report, $sort_by, $asset_tags, $projects) {

    global $lang, $escaper;

    echo "
        <form name='select_report' method='POST' action=''>
            <div class='accordion'>
                <div class='accordion-item' id='filter-selections-container'>
                    <h2 class='accordion-header'>
                        <button type='button' class='accordion-button' data-bs-toggle='collapse' data-bs-target='#filter-selections-accordion-body'>" . $escaper->escapeHtml($lang['GroupAndFilteringSelections']) . "</button>
                    </h2>
                    <div id='filter-selections-accordion-body' class='accordion-collapse collapse show'>
                        <div class='accordion-body card-body'>
                            <div class='row'>
                                <div class='col-3'>
                                    <label>" . $escaper->escapeHtml($lang['Report']) . " :</label>
                                    <select id='report' name='report' class='form-select' onchange='javascript: submitFilterForm()'>
                                        <option value='0'" . ($report == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['RisksByAsset']) . "</option>
                                        <option value='1'" . ($report == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['AssetsByRisk']) . "</option>
                                    </select>
                                </div>
                                <div class='col-3'>
                                    <label>" . $escaper->escapeHtml($lang['AssetTags']) . " :</label>
    ";
                                    create_multiple_dropdown("asset_tags", $asset_tags, NULL, NULL, true, $lang['Unassigned'], "-1");
    echo "
                                </div>
                                <div class='col-3'>
                                    <label>" . $escaper->escapeHtml($lang['Project']) . " :</label>
    ";
                                    create_multiple_dropdown("projects", $projects, NULL, NULL, true, $lang['Unassigned'], "-1");
    echo "
                                </div>
    ";
    if ($report == 0) {
        echo "
                                <div class='col-3'>
                                    <label>" . $escaper->escapeHtml($lang['SortBy']) . " :</label>
                                    <select id='sort_by' name='sort_by' class='form-select' onchange='javascript: submitFilterForm()'>
                                        <option value='0'" . ($sort_by == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['AssetName']) . "</option>
                                        <option value='1'" . ($sort_by == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['AssetRisk']) . "</option>
                                    </select>
                                </div>
        ";
    }
    echo "
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <script>
            function submitFilterForm() {
                $('form[name=select_report]').submit();
            }

            $(function() {
                $('#asset_tags, #projects').multiselect({
                    allSelectedText: _lang['All'],
                    enableFiltering: true,
                    maxHeight: 250,
                    buttonWidth: '100%',
                    includeSelectAllOption: true,
                    enableCaseInsensitiveFiltering: true,
                    onChange: submitFilterForm,
                    onSelectAll: submitFilterForm,
                    onDeselectAll: submitFilterForm
                });

                // Multiselects' selected options are sent to the server as separate parameters making large sets of selected options
                // go over the server's maximum allowed number of variables. To solve this we're sending the list of ids as a single JSON string
                $('form[name=select_report]').on('submit', function() {
                    // Create a hidden input with the list of selected ids in a JSON array as the value
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'asset_tags',
                        value: JSON.stringify($('#asset_tags').val())
                    }).appendTo($(this));

                    // disable the original multiselect to make sure it's not submitted to the server
                    $('#asset_tags').attr('disabled','disabled');
                });
            });
        </script>
    ";

}

/*********************************************
* FUNCTION: VIEW RISKS AND ISSUES SELECTIONS *
**********************************************/
function view_risks_and_issues_selections($risk_tags, $start_date="", $end_date="")
{

    global $lang, $escaper;

    $start_date  = $start_date ? $start_date : format_date(date('Y-m-d', strtotime('-30 days')));
    $end_date  = $end_date ? $end_date : format_date(date('Y-m-d'));

    echo "
        <form name='issues_report' method='POST' action=''>
            <div class='accordion'>
                <div class='accordion-item' id='filter-selections-container'>
                    <h2 class='accordion-header'>
                        <button type='button' class='accordion-button' data-bs-toggle='collapse' data-bs-target='#filter-selections-accordion-body'>{$escaper->escapeHtml($lang['GroupAndFilteringSelections'])}</button>
                    </h2>
                    <div id='filter-selections-accordion-body' class='accordion-collapse collapse show'>
                        <div class='accordion-body card-body'>
                            <div class='row'>
                                <div class='col-4'>
                                    <label>{$escaper->escapeHtml($lang['RiskTags'])} :</label>
    ";
                                    create_multiple_dropdown("risk_tags", $risk_tags, NULL, NULL, true, $lang['Unassigned'], "-1");
    echo "
                                </div>
                                <div class='col-4'>
                                    <label>{$escaper->escapeHtml($lang['StartDate'])} :</label>
                                    <input type='text' name='start_date' value='{$start_date}' class='form-control datepicker'>
                                </div>
                                <div class='col-4'>
                                    <label>{$escaper->escapeHtml($lang['EndDate'])} :</label>
                                    <input type='text' name='end_date' value='{$end_date}' class='form-control datepicker'>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <script>
            $(function() {
                $('#risk_tags').multiselect({
                    allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
                    enableFiltering: true,
                    maxHeight: 250,
                    buttonWidth: '100%',
                    includeSelectAllOption: true,
                    enableCaseInsensitiveFiltering: true,
                    onDropdownHide: function(){
                        $('form[name=issues_report]').submit();
                    }
                });
                $('.datepicker').initAsDatePicker();
                $('.datepicker').change(function(){
                    $('form[name=issues_report]').submit();
                });
            });
        </script>
    ";
}

/******************************************
* FUNCTION: VIEW GET RISKS BY SELECTIONS *
******************************************/
function view_get_risks_by_selections($status=0, $group=0, $sort=0, $risk_columns=[], $mitigation_columns=[], $review_columns=[], $scoring_columns=[], $unassigned_columns=[], $risk_mapping_columns=[]) {

    global $lang, $escaper;
    
    $encoded_request_uri = get_encoded_request_uri();
    
    echo   "
        <div class='accordion-item' id='group-selections-container'>
            <h2 class='accordion-header'>
                <button type='button' class='accordion-button' data-bs-toggle='collapse' data-bs-target='#group-selections-accordion-body'>{$escaper->escapeHtml($lang['GroupAndFilteringSelections'])}</button>
            </h2>
            <div id='group-selections-accordion-body' class='accordion-collapse collapse show'>
                <div class='accordion-body card-body'>
                    <form id='get_risks_by' name='get_risks_by' method='post' action='{$_SESSION['base_url']}{$encoded_request_uri}'>
                        <div class='row'>
                            <!-- Risk Status Selection -->
                            <div class='col-4'>
                                <label>{$escaper->escapeHtml($lang['Status'])} :</label>
                                <select id='status' name='status' onchange='javascript: submit()' class='form-select'>
                                    <option value='0'" . ($status == 0 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['OpenRisks'])}</option>
                                    <option value='1'" . ($status == 1 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['ClosedRisks'])}</option>
                                    <option value='2'" . ($status == 2 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['AllRisks'])}</option>
                                </select>
                            </div>
                            <!-- Group By Selection -->    
                            <div class='col-4'>
                                <label>{$escaper->escapeHtml($lang['GroupBy'])} :</label>
                                <select id='group' name='group' onchange='javascript: submit()' class='form-select'>
                                    <option value='0'" . ($group == 0 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['None'])}</option>
                                    <option value='5'" . ($group == 5 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['Category'])}</option>
                                    <option value='11'" . ($group == 11 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['ControlRegulation'])}</option>
                                    <option value='14'" . ($group == 14 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['MonthSubmitted'])}</option>
                                    <option value='13'" . ($group == 13 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['NextStep'])}</option>
                                    <option value='8'" . ($group == 8 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['Owner'])}</option>
                                    <option value='9'" . ($group == 9 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['OwnersManager'])}</option>
                                    <option value='12'" . ($group == 12 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['Project'])}</option>
                                    <option value='1'" . ($group == 1 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['RiskLevel'])}</option>
                                    <option value='10'" . ($group == 10 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['RiskScoringMethod'])}</option>
                                    <option value='4'" . ($group == 4 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['RiskSource'])}</option>
                                    <option value='2'" . ($group == 2 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['Status'])}</option>
                                    <option value='6'" . ($group == 6 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['Team'])}</option>
                                    <option value='7'" . ($group == 7 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['Technology'])}</option>
                                </select>
                            </div>
                            <!-- Sort By Selection -->
                            <div class='col-4'>
                                <label>{$escaper->escapeHtml($lang['SortBy'])} :</label>
                                <select id='sort' name='sort' onchange='javascript: submit()' class='form-select'>
                                    <option value='0'" . ($sort == 0 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['InherentRisk'])}</option>
                                    <option value='1'" . ($sort == 1 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['ID'])}</option>
                                    <option value='2'" . ($sort == 2 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['Subject'])}</option>
                                    <option value='3'" . ($sort == 3 ? ' selected' : '') . ">{$escaper->escapeHtml($lang['ResidualRisk'])}</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    ";

    // Risk columns
        display_risk_columns($risk_columns, $mitigation_columns, $review_columns, $scoring_columns, $unassigned_columns, $risk_mapping_columns);
}

/*************************************************
* FUNCTION: DISPLAY SAVE DYNAMIC RISK SELECTIONS *
**************************************************/
function display_save_dynamic_risk_selections() {

    global $lang, $escaper;
    
    $selection_id = !empty($_GET['selection']) ? (int)$_GET['selection'] : '';
    
    $options = get_dynamic_saved_selections($_SESSION['uid']);
    
    $private = $escaper->escapeHtml($lang['Private']);
    $public = $escaper->escapeHtml($lang['Public']);
    
    echo "
        <div class='accordion-item' id='save-selections-container'>
            <h2 class='accordion-header'>
                <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#save-selections-accordion-body'>{$escaper->escapeHtml($lang['SaveSelections'])}</button>
            </h2>
            <div id='save-selections-accordion-body' class='accordion-collapse collapse'>
                <div class='accordion-body card-body'>
                    <form method='post' >
                        <div class='row align-items-end'>
                            <div class='col-8 form-group dynamic-save-selections'>
                                <label>{$escaper->escapeHtml($lang['SavedSelections'])} :</label>

                                <script>
                                    $(document).ready(function(){
                                        $('#saved_selections').selectize({
                                            options: [
    ";

    $selection = false;
    foreach ($options as $option) {
        if ($selection_id == $option['value']) {
            $selection = $option;
        }
        echo "
                                                {
                                                    class: '{$option['type']}', 
                                                    value: '{$option['value']}', 
                                                    name: '{$escaper->escapeHtml($option['name'])}'
                                                },
        ";
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

    if ($selection_id) {
        echo "
                                            items: [{$selection_id}],
        ";
    }
                
    echo "
                                            render: {
                                                optgroup_header: function (data) {
                                                    return $('<div>', {class: 'optgroup-header'}).text(data.label);
                                                },
                                                option: function (data) {
                                                    if (data.value) {
                                                        return $('<div>', {class: 'option d-flex'}).html(data.name + '<i class=\'fa fa-trash font-10 rounded-5 p-1 delete-option-btn\'></i>');
                                                    } else {
                                                        return $('<div>', {class: 'option'}).html(data.name);
                                                    }
                                                },
                                                item: function (data) {
                                                    if (data.value) {
                                                        return $('<div>', {class: 'item'}).html('[' + (data.class == 'private' ? '$private' : '$public') + '] ' + data.name); 
                                                    } else {
                                                        return $('<div>', {class: 'item'}).html('');
                                                    }
                                                }
                                            }
                                        });

                                        //stop option changing and open delete confirm modal.
                                        $('.dynamic-save-selections .selectize-dropdown').on('mousedown', '.delete-option-btn', function(e) {
                                            
                                            //stop bubbling mousedown event
                                            e.stopPropagation();

                                            let id = $(e.target).parents('.option').first().data('value');
                                            confirm('{$escaper->escapeHtml($lang["AreYouSureYouWantToDeleteSelction"])}', () => delete_saved_selection(id));
                                        });            
                                    });
            
                                    function delete_saved_selection(id)
                                    {
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

                                <select required id='saved_selections'></select>
                            </div>
                        </div>
                    </form>
                    <form method='post' id='save-selections-form'>
                        <div class='row align-items-end'>
                            <div class='col-4'>
                                <label>{$escaper->escapeHtml($lang['Type'])} :</label>
                                <select required id='saved-selection-type' name='type' title='{$escaper->escapeHtml($lang['PleaseSelectTypeForSaving'])}' class='form-select'>
                                    <option value=''>--</option>
                                    <option value='public'>{$public}</option>
                                    <option value='private'>{$private}</option>
                                </select>
                            </div>
                            <div class='col-4'>
                                <label>{$escaper->escapeHtml($lang['Name'])} :</label>
                                <input name='name' required type='text' placeholder='{$escaper->escapeHtml($lang['Name'])}' title='{$escaper->escapeHtml($lang['Name'])}' style='max-width: unset;' class='form-control'>
                            </div>
                            <div class='col-4'>
                                <button class='btn btn-submit'>{$escaper->escapeHtml($lang['Save'])}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
            
        <script>
            $(document).ready(function(){
                
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
        </script>
    ";
}

/*********************************
* FUNCTION: DISPLAY RISK COLUMNS *
**********************************/
function display_risk_columns($risk_columns=[], $mitigation_columns=[], $review_columns=[], $scoring_columns=[], $unassigned_columns=[], $risk_mapping_columns=[]) {

    global $escaper, $lang;

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
    if(customization_extra()) {

        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        foreach ($risk_columns as $column=>$value) {

            if (stripos($column, "custom_field_") !== false) {
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $name = $custom_field['name'];
            } else {
                $name = get_label_by_risk_field_name($column);
            }

            $risk_columns_option[] = array('value'=>$column, 'name'=>$name);

            if ($value == true) {
                $risk_columns_selected[] = $column;
            }
        }

        foreach ($mitigation_columns as $column=>$value) {

            if (stripos($column, "custom_field_") !== false) {
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $name = $custom_field['name'];
            } else {
                $name = get_label_by_risk_field_name($column);
            }

            $mitigation_columns_option[] = array('value'=>$column, 'name'=>$name);

            if ($value == true) {
                $mitigation_columns_selected[] = $column;
            }
        }

        foreach ($review_columns as $column=>$value) {

            if (stripos($column, "custom_field_") !== false) {
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $name = $custom_field['name'];
            } else {
                $name = get_label_by_risk_field_name($column);
            }

            $review_columns_option[] = array('value'=>$column, 'name'=>$name);

            if ($value == true) {
                $review_columns_selected[] = $column;
            }
        }

        foreach ($scoring_columns as $column=>$value) {
            $name = get_label_by_risk_field_name($column);
            $scoring_columns_option[] = array('value'=>$column, 'name'=>$name);
            if ($value == true) {
                $scoring_columns_selected[] = $column;
            }
        }

        foreach ($unassigned_columns as $column=>$value) {

            if (stripos($column, "custom_field_") !== false) {
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $name = $custom_field['name'];
            } else {
                $name = get_label_by_risk_field_name($column);
            }

            $unassigned_columns_option[] = array('value'=>$column, 'name'=>$name);

            if ($value == true) {
                $unassigned_columns_selected[] = $column;
            }
        }
    } else {

        foreach ($risk_columns as $column=>$value) {
            $name = get_label_by_risk_field_name($column);
            $risk_columns_option[] = array('value'=>$column, 'name'=>$name);
            if ($value == true) {
                $risk_columns_selected[] = $column;
            }
        }

        foreach ($mitigation_columns as $column=>$value) {
            $name = get_label_by_risk_field_name($column);
            $mitigation_columns_option[] = array('value'=>$column, 'name'=>$name);
            if ($value == true) {
                $mitigation_columns_selected[] = $column;
            }
        }

        foreach ($review_columns as $column=>$value) {
            $name = get_label_by_risk_field_name($column);
            $review_columns_option[] = array('value'=>$column, 'name'=>$name);
            if ($value == true) {
                $review_columns_selected[] = $column;
            }
        }

        foreach ($scoring_columns as $column=>$value) {
            $name = get_label_by_risk_field_name($column);
            $scoring_columns_option[] = array('value'=>$column, 'name'=>$name);
            if ($value == true) {
                $scoring_columns_selected[] = $column;
            }
        }
    }

    foreach ($risk_mapping_columns as $column=>$value) {
        $name = get_label_by_risk_field_name($column);
        $risk_mapping_columns_option[] = array('value'=>$column, 'name'=>$name);
        if ($value == true) {
            $risk_mapping_columns_selected[] = $column;
        }
    }

    echo "
        <div class='accordion-item' id='column-selections-container'>
            <h2 class='accordion-header'>
                <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#column-selection-accordion-body'>{$escaper->escapeHtml($lang['ColumnSelections'])}</button>
            </h2>
            <div id='column-selection-accordion-body' class='accordion-collapse collapse'>
                <div class='accordion-body card-body'>
                    <div class='row'>
                        <div class='col-4 form-group'>
                            <label>{$escaper->escapeHtml($lang['RiskColumns'])} :</label>
    ";
                            create_multiple_dropdown("", $risk_columns_selected, "risk_columns", $risk_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
    echo "
                        </div>
                        <div class='col-4 form-group'>
                            <label>{$escaper->escapeHtml($lang['MitigationColumns'])} :</label>
    ";
                            create_multiple_dropdown("", $mitigation_columns_selected, "mitigation_columns", $mitigation_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
    echo "
                        </div>
                        <div class='col-4 form-group'>
                            <label>{$escaper->escapeHtml($lang['ReviewColumns'])} :</label>
    ";
                            create_multiple_dropdown("", $review_columns_selected, "review_columns", $review_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
    echo "
                        </div>
                    </div>
                    <div class='row'>
                        <div class='col-4'>
                            <label>{$escaper->escapeHtml($lang['RiskScoringColumns'])} :</label>
    ";
                            create_multiple_dropdown("", $scoring_columns_selected, "scoring_columns", $scoring_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
    echo "
                        </div>
    ";

    if (count($unassigned_columns_option)) {
        echo "
                        <div class='col-4'>
                            <label>{$escaper->escapeHtml($lang['UnassignedColumns'])} :</label>
        ";
                            create_multiple_dropdown("", $unassigned_columns_selected, "unassigned_columns", $unassigned_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
        echo "
                        </div>
        "; 
    }

    echo "
                        <div class='col-4'>
                            <label>{$escaper->escapeHtml($lang['RiskMappingColumns'])} :</label>
    ";
                            create_multiple_dropdown("", $risk_mapping_columns_selected, "risk_mapping_columns", $risk_mapping_columns_option, false, "", "", true, "class='multiselect' multiple='multiple'");
    echo "
                        </div>
                    </div>
                </div>
            </div>
        </div>
    ";

}

/********************************************************
* FUNCTION: DISPLAY RISKS OPEN BY TEAM DROPDOWN SCRIPT  *
*********************************************************/
function display_risks_open_by_team_dropdown_script() {

    global $escaper, $lang;

    echo "
        <script type='text/javascript'>
            $(function(){
                // timer identifier
                var typingTimer;                
                // time in ms (1 second)
                var doneInterval = 2000;  
                function submit_form(){
                    $('#risks_by_teams_form').submit();
                }

                function throttledFormSubmit() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(submit_form, doneInterval);
                }            
                
                // Team dropdown
                $('#teams').multiselect({
                    allSelectedText: '{$escaper->escapeJs($lang['AllTeams'])}',
                    includeSelectAllOption: true,
                    onChange: throttledFormSubmit,
                    onSelectAll: throttledFormSubmit,
                    onDeselectAll: throttledFormSubmit,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                });
                
                // Owner dropdown
                $('#owners').multiselect({
                    allSelectedText: '{$escaper->escapeJs($lang['AllOwners'])}',
                    includeSelectAllOption: true,
                    onChange: throttledFormSubmit,
                    onSelectAll: throttledFormSubmit,
                    onDeselectAll: throttledFormSubmit,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                });
                
                // Owner's dropdown
                $('#ownersmanagers').multiselect({
                    allSelectedText: '{$escaper->escapeJs($lang['AllOwnersManagers'])}',
                    includeSelectAllOption: true,
                    onChange: throttledFormSubmit,
                    onSelectAll: throttledFormSubmit,
                    onDeselectAll: throttledFormSubmit,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                });
            });
        </script>
        <style type=''>
            .download-by-group, .print-by-group{
                display: none;
            }
        </style>
    ";
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
       'Reviewer' => 
           [
               'name' => "reviewer",
               'text' => $escaper->escapeHtml($lang['ReviewedBy']),
           ],
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

    echo "
        <div class='registration-table-edit'>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['FirstName']) . ":</label>
                <input type='text' name='fname' id='fname' value='" . $escaper->escapeHtml($fname) . "' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['LastName']) . ":</label>
                <input type='text' name='lname' id='lname' value='" . $escaper->escapeHtml($lname) . "' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['Company']) . ":</label>
                <input type='text' name='company' id='company' value='" . $escaper->escapeHtml($company) . "' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['JobTitle']) . ":</label>
                <input type='text' name='title' id='title' value='" . $escaper->escapeHtml($title) . "' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['Phone']) . ":</label>
                <input type='tel' name='phone' id='phone' value='" . $escaper->escapeHtml($phone) . "' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['EmailAddress']) . ":</label>
                <input type='email' name='email' id='email' value='" . $escaper->escapeHtml($email) . "' class='form-control' />
            </div>
            <button type='submit' name='register' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Register']) . "</button>
        </div>
    ";
}

/****************************************
* FUNCTION: DISPLAY REGISTRATION TABLE *
****************************************/
function display_registration_table($name="", $company="", $title="", $phone="", $email="", $fname="", $lname="")
{
    global $escaper;
    global $lang;

    echo "
        <div class='registration-table'>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['FirstName']) . ":</label>
                <input type='text' name='fname' id='fname' value='" . $escaper->escapeHtml($fname) . "' title='" . $escaper->escapeHtml($fname) . "' disabled='disabled' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['LastName']) . ":</label>
                <input type='text' name='lname' id='lname' value='" . $escaper->escapeHtml($lname) . "' title='" . $escaper->escapeHtml($lname) . "' disabled='disabled' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['Company']) . ":</label>
                <input type='text' name='company' id='company' value='" . $escaper->escapeHtml($company) . "' title='" . $escaper->escapeHtml($company) . "' disabled='disabled' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['JobTitle']) . ":</label>
                <input type='text' name='title' id='title' value='" . $escaper->escapeHtml($title) . "' title='" . $escaper->escapeHtml($title) . "' disabled='disabled' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['Phone']) . ":</label>
                <input type='tel' name='phone' id='phone' value='" . $escaper->escapeHtml($phone) . "' title='" . $escaper->escapeHtml($phone) . "' disabled='disabled' class='form-control' />
            </div>
            <div class='form-group'>
                <label>" . $escaper->escapeHtml($lang['EmailAddress']) . ":</label>
                <input type='email' name='email' id='email' value='" . $escaper->escapeHtml($email) . "' title='" . $escaper->escapeHtml($email) . "' disabled='disabled' class='form-control' />
            </div>
            <button type='submit' name='update' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Update']) . "</button>
        </div> 
    ";
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
function display_self_assessments() {

    global $lang;
    global $escaper;

    echo "
        <div class='mt-2'>
            <nav class='nav nav-tabs'>
                <a class='nav-link active' data-bs-target='#self_assessments' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['Assessments'])}</a>
                <a class='nav-link' data-bs-target='#pending_risks' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['PendingRisks'])}</a>
            </nav>
        </div>
        <div class='tab-content'>
            <div id='self_assessments' class='tab-pane active card-body my-2 border'>
    ";

    // Start the list
    echo "
                <ul class='nav nav-pills nav-stacked flex-column'>
    ";

    // Get the assessments
    $assessments = get_assessment_names();

    // For each entry in the assessments array
    foreach ($assessments as $assessment) {

        // Get the assessment values
        $assessment_name = $assessment['name'];
        $assessment_id = (int)$assessment['id'];

        // Display the assessment
        echo "
                    <li style='text-align:center'>
                        <a class='nav-link text-info' href='index.php?action=view&assessment_id={$escaper->escapeHtml($assessment_id)}'>{$escaper->escapeHTML($assessment_name)}</a>
                    </li>
        ";
    }

    // End the list
    echo "
                </ul>
            </div>
            <div id='pending_risks' class='tab-pane card-body my-2 border'>
    ";
                display_pending_risks();
    echo "
            </div>
        </div>
    ";

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
function display_view_assessment_questions($assessment_id = NULL) {

    global $escaper;
    global $lang;

    echo "
        <div class='mt-2'>
            <nav class='nav nav-tabs'>
                <a class='nav-link active' data-bs-target='#self_assessments' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['Assessments'])}</a>
                <a class='nav-link' data-bs-target='#pending_risks' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['PendingRisks'])}</a>
            </nav>
        </div>
        <div class='tab-content'>
            <div id='self_assessments' class='tab-pane active card-body my-2 border'>
                <div class='hero-unit'>
                    <form name='submit_assessment' class='form-horizontal' method='POST' action=''>
    ";
    
    // If the assessment id was sent by get
    if (isset($_GET['assessment_id'])) {

        // Set the assessment id
        $assessment_id = $_GET['assessment_id'];
        
    // If the assessment id was sent by post
    } else if (isset($_POST['assessment_id'])) {

        // Set the assessment id
        $assessment_id = $_POST['assessment_id'];

    }

    // Add a hidden value for the assessment id
    echo "
                        <input type='hidden' name='assessment_id' value='{$escaper->escapeHtml($assessment_id)}' />
    ";

    // Add a hidden value for the action
    echo "
                        <input type='hidden' name='action' value='submit' />
    ";

    // Get the assessment name
    $assessment = get_assessment_names($assessment_id);
    $assessment_name = $assessment['name'];

    $show_autocomplete = get_setting("ASSESSMENT_ASSET_SHOW_AVAILABLE");
    if ($show_autocomplete) {
        $AffectedAssetsWidgetPlaceholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);
    } else {
        $AffectedAssetsWidgetPlaceholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetNoDropdownPlaceholder']);
    }

    echo "
                        <h3 class = 'text-center'>{$escaper->escapeHtml($assessment_name)}</h3>
                        <div class = 'form-group'>
                            <label>{$escaper->escapeHtml($lang['AssetName'])} :</label>
                            <select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='$AffectedAssetsWidgetPlaceholder'>
                            </select>
                        </div>

                        <script>
                            var assets_and_asset_groups = [];

                            $(document).ready(function() {
    ";
    if ($show_autocomplete) {

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
                                        }
        ";

    }

    echo "
                                selectize_pending_risk_affected_assets_widget($('select.assets-asset-groups-select'), assets_and_asset_groups);
    ";

    if ($show_autocomplete) {
        echo "
                                    }
                                });
        ";
    }
    echo "
                            });
                        </script>
    ";
    
    // Get the assessment
    $assessment = get_assessment($assessment_id);

    // Set a variable to track the current question
    $current_question = "";


    $rows = array();
    foreach ($assessment as $row) {
        $question_id = (int)$row['question_id'];
        if (!isset($rows[$question_id])) {
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
    foreach ($rows as $question_id => $row) {

        // Display the question
        echo "
                        <div class='form-group'>
                            <p class='mb-2'><strong>{$escaper->escapeHtml($row['question'])}</strong></p>
                            <div class='mb-2 d-flex align-items-center'>
        ";

        // Display the answers
        foreach ($row['answers'] as $answer) {
            echo "
                                <div class='form-check mx-4'>
                                    <input class='form-check-input' id='{$escaper->escapeHtml($answer['id'])}' type='radio' name='{$escaper->escapeHtml($question_id)}' value='{$escaper->escapeHtml($answer['id'])}' />
                                    <label class = 'form-check-label mb-0' for='{$escaper->escapeHtml($answer['id'])}'>{$escaper->escapeHtml($answer['answer'])}</label>
                                </div>
            ";
        }
        
        echo "
                            </div>
                            <div>
                                <label>Comment :</label>
                                <textarea class='form-control assessment-comment' name='comment[{$question_id}]'></textarea>
                            </div>
                        </div>
        ";
    }

    echo "
                        <div>
                            <input class='btn btn-submit' type='submit' name='submit_assessment' value='{$escaper->escapeHtml($lang['Submit'])}'/>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id='pending_risks' class='tab-pane card-body my-2 border'>
    ";
                display_pending_risks($assessment_id);
    echo "  
            </div>
        </div>
    ";
}

/***********************************
* FUNCTION: DISPLAY PENDING RISKS *
***********************************/
function display_pending_risks($assessment_id = null) {

    global $escaper;
    global $lang;

    // Get the pending risks
    $risks = get_pending_risks();

    $affected_assets_placeholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);

    $maxlength = (int)get_setting('maximum_risk_subject_length', 300);
    $date_time_format = get_default_datetime_format('H:i:s');

    // For each pending risk
    foreach ($risks as $risk) {

        //When assessment is selected, only show the pending risks created by that assessment
        if($assessment_id && ($risk['assessment_id'] != $assessment_id)) {
            continue;
        } 

        // Get the assessment name
        $assessment = get_assessment_names($risk['assessment_id']);
        $submission_date = format_datetime($risk['submission_date'], '', 'H:i:s');

        echo "
            <form name='submit_risk' method='post' action='' enctype='multipart/form-data' class='pending-risk-form card-body border my-2'>
                <input type='hidden' name='assessment_id' value='{$escaper->escapeHtml($risk['assessment_id'])}' />
                <input type='hidden' name='pending_risk_id' value='{$escaper->escapeHtml($risk['id'])}' />
                <div class='form-group'>
                    <label for='submission_date'>{$lang['SubmissionDate']} :</label>
                    <input type='text' class='form-control' name='submission_date' value='{$escaper->escapeHtml($submission_date)}' />
                </div>
                <div class='form-group'>
                    <label for='subject'>{$lang['Subject']} :</label>
                    <input maxlength='{$maxlength}' type='text' class='form-control' name='subject' value='{$escaper->escapeHtml($risk['subject'])}' />
                </div>
        ";

        if ($risk['scoring_method']) {

                display_score_html_from_pending_risk($risk['scoring_method'], $risk['Custom'], $risk['CLASSIC_likelihood'], $risk['CLASSIC_impact'], $risk['CVSS_AccessVector'], $risk['CVSS_AccessComplexity'], $risk['CVSS_Authentication'], $risk['CVSS_ConfImpact'], $risk['CVSS_IntegImpact'], $risk['CVSS_AvailImpact'], $risk['CVSS_Exploitability'], $risk['CVSS_RemediationLevel'], $risk['CVSS_ReportConfidence'], $risk['CVSS_CollateralDamagePotential'], $risk['CVSS_TargetDistribution'], $risk['CVSS_ConfidentialityRequirement'], $risk['CVSS_IntegrityRequirement'], $risk['CVSS_AvailabilityRequirement'], $risk['DREAD_DamagePotential'], $risk['DREAD_Reproducibility'], $risk['DREAD_Exploitability'], $risk['DREAD_AffectedUsers'], $risk['DREAD_Discoverability'], $risk['OWASP_SkillLevel'], $risk['OWASP_Motive'], $risk['OWASP_Opportunity'], $risk['OWASP_Size'], $risk['OWASP_EaseOfDiscovery'], $risk['OWASP_EaseOfExploit'], $risk['OWASP_Awareness'], $risk['OWASP_IntrusionDetection'], $risk['OWASP_LossOfConfidentiality'], $risk['OWASP_LossOfIntegrity'], $risk['OWASP_LossOfAvailability'], $risk['OWASP_LossOfAccountability'], $risk['OWASP_FinancialDamage'], $risk['OWASP_ReputationDamage'], $risk['OWASP_NonCompliance'], $risk['OWASP_PrivacyViolation']);

        } else {
                display_score_html_from_pending_risk(5, $risk['Custom']);
        }

        echo "
                <div class='form-group'>
                    <label for='owner'>{$lang['Owner']} :</label>
        ";
                    create_dropdown("enabled_users", $risk['owner'], "owner");
        echo "
                </div>
                <div class='form-group'>
                    <label for='assets_asset_groups'>{$lang['AffectedAssets']} :</label>
                    <select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='{$affected_assets_placeholder}'>
        ";

        if ($risk['affected_assets']) {
            foreach (explode(',', $risk['affected_assets']) as $value) {

                $value = $name = trim($value);

                if (preg_match('/^\[(.+)\]$/', $name, $matches)) {
                    $name = trim($matches[1]);
                    $type = 'group';
                } else {
                    $type = 'asset';
                }

                echo "
                        <option value='{$escaper->escapeHtml($value)}' selected data-class='{$type}'>{$escaper->escapeHtml($name)}</option>
                ";
            }
        }

        echo "
                    </select>
                </div>
                <div class='form-group'>
                    <label for='note'>{$lang['AdditionalNotes']} :</label>
                    <textarea class='form-control' name='note' cols='50' rows='3' id='note'>Risk created using the &quot;{$escaper->escapeHtml($assessment['name'])}&quot; assessment.\n{$escaper->escapeHtml($risk['comment'])}</textarea>
                </div>
                <div class='form-actions'>
        ";

        if (isset($_SESSION["submit_risks"]) && $_SESSION["submit_risks"] == 1) {
            echo "
                    <button type='submit' name='add' class='btn btn-submit'>{$escaper->escapeHtml($lang['Add'])}</button>
                    <button type='submit' name='delete' class='btn btn-dark'>{$escaper->escapehtml($lang['Delete'])}</button>
            ";
        }

        echo "  </div>
            </form>
        ";

    }

    echo "
            <script>
                var assets_and_asset_groups = [];
                $(document).ready(function() {
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
                                $(this).parent().find('.selectize-control>div').css('width', '100%');

                            });
                        }
                    });
                    $('input[name=\"submission_date\"]').initAsDateTimePicker();
                });
            </script>
    ";
}

/******************************************
 * FUNCTION: RISK AVERAGE BASELINE METRIC *
 *****************************************/
function risk_average_baseline_metric($time = "day", $title = "") {
    
    global $escaper, $lang;

    // Get the inherent risk average values by day
    $endpoint = "/api/v2/reports/risk/average?type=inherent&timeframe={$time}";
    $inherent_averages_result = call_simplerisk_api_endpoint($endpoint);

    // Get the residual risk average values by day
    $endpoint = "/api/v2/reports/risk/average?type=residual&timeframe={$time}";
    $residual_averages_result = call_simplerisk_api_endpoint($endpoint);

    // Get the risk count values by day
    $endpoint = "/api/v2/reports/risk/opencount?timeframe={$time}";
    $count_result = call_simplerisk_api_endpoint($endpoint);

    // Create the data arrays
    $labels = $inherent_averages_result['dates'] ?? [];
    $inherent_averages = $inherent_averages_result['averages'] ?? [];
    $residual_averages = $residual_averages_result['averages'] ?? [];
    $counts = $count_result['counts'] ?? [];

    // Create the inherent average dataset
    $label = $escaper->escapeHtml($lang['InherentRisk']);
    $inherent_average_dataset = [
        "label" => "{$label}",
        "data" => $inherent_averages,
        "fill" => "false",
        "borderColor" => "#000000",
        "borderWidth" => "1",
        "tension" => "0.1"
    ];

    // Create the residual average dataset
    $label = $escaper->escapeHtml($lang['ResidualRisk']);
    $residual_average_dataset = [
        "label" => "{$label}",
        "data" => $residual_averages,
        "fill" => "false",
        "borderColor" => "#0000FF",
        "borderWidth" => "1",
        "tension" => "0.1"
    ];

    // Create an array of the combined datasets
    $datasets = [
        $inherent_average_dataset,
        $residual_average_dataset,
    ];

    // Add the background dataset
    $background_dataset = create_background_dataset(count($labels));
    $datasets = array_merge($datasets, $background_dataset);

    // Create a javascript array of open risks
    $risksOpened_label = $escaper->escapeHtml($lang['OpenRisks']);
    $risksOpened = implode(',', $counts);

    // Create the Chart.js line chart
    $title = $escaper->escapeHtml($lang['RiskAverageOverTime']);
    $element_id = "risk_score_average";
    $x_axis_title = $escaper->escapeHtml($lang['Date']);
    $y_axis_title = $escaper->escapeHtml($lang['RiskScore']);
    $tooltip = "
        tooltip: {
            callbacks: {
                label: function (tooltipItem) {
                    // Use the average risk score index
                    if (tooltipItem.datasetIndex === 0)
                    {
                        var dataIndex = tooltipItem.dataIndex;
                        var value = tooltipItem.formattedValue;
                        var label = tooltipItem.label;
                        var datasetLabel = tooltipItem.dataset.label;
                        result = [
                            datasetLabel + ': ' + value,
                        ];
                        return result;
                    }
                    else if (tooltipItem.datasetIndex === 1)
                    {
                        var risksOpened = [{$risksOpened}];
                        var dataIndex = tooltipItem.dataIndex;
                        var value = tooltipItem.formattedValue;
                        var label = tooltipItem.label;
                        var datasetLabel = tooltipItem.dataset.label;
                        var risksOpened_value = risksOpened[dataIndex];
                        result = [
                            datasetLabel + ': ' + value,
                            '{$risksOpened_label}: ' + risksOpened_value,
                        ];
                        return result;
                    }
                    else return '';
                }
            }
        }
    ";
    create_chartjs_line_code($title, $element_id, $labels, $datasets, $tooltip, $x_axis_title, $y_axis_title, 10);
}

/*****************************
 * FUNCTION: SCORE OVER TIME *
 *****************************/
function score_over_time($time = "day")
{
    global $escaper, $lang;

    // Get the risk id
    $risk_id = get_param("GET", "id", null);

    // Get the inherent risk average values by day
    $endpoint = "/api/v2/reports/risk/average?risk_id={$risk_id}&type=inherent&timeframe={$time}";
    $inherent_averages_result = call_simplerisk_api_endpoint($endpoint);

    // Get the residual risk average values by day
    $endpoint = "/api/v2/reports/risk/average?risk_id={$risk_id}&type=residual&timeframe={$time}";
    $residual_averages_result = call_simplerisk_api_endpoint($endpoint);

    // Create the data arrays
    $labels = $inherent_averages_result['dates'] ?? [];
    $inherent_averages = $inherent_averages_result['averages'] ?? [];
    $residual_averages = $residual_averages_result['averages'] ?? [];

    // Create the inherent average dataset
    $label = $escaper->escapeHtml($lang['InherentRisk']);
    $inherent_average_dataset = [
        "label" => "{$label}",
        "data" => $inherent_averages,
        "fill" => "false",
        "borderColor" => "#000000",
        "borderWidth" => "1",
        "tension" => "0.1"
    ];

    // Create the residual average dataset
    $label = $escaper->escapeHtml($lang['ResidualRisk']);
    $residual_average_dataset = [
        "label" => "{$label}",
        "data" => $residual_averages,
        "fill" => "false",
        "borderColor" => "#0000FF",
        "borderWidth" => "1",
        "tension" => "0.1"
    ];

    // Create an array of the combined datasets
    $datasets = [
        $inherent_average_dataset,
        $residual_average_dataset,
    ];

    // Add the background dataset
    $background_dataset = create_background_dataset(count($labels));
    $datasets = array_merge($datasets, $background_dataset);

    // Create the Chart.js line chart
    $title = $escaper->escapeHtml($lang['RiskScoringHistory']);
    $element_id = "risk_score_average";
    $x_axis_title = $escaper->escapeHtml($lang['Date']);
    $y_axis_title = $escaper->escapeHtml($lang['RiskScore']);
    $tooltip = "
        tooltip: {
            callbacks: {
                label: function (tooltipItem) {
                    // Use the average risk score index
                    if (tooltipItem.datasetIndex === 0)
                    {
                        var dataIndex = tooltipItem.dataIndex;
                        var value = tooltipItem.formattedValue;
                        var label = tooltipItem.label;
                        var datasetLabel = tooltipItem.dataset.label;
                        result = [
                            datasetLabel + ': ' + value,
                        ];
                        return result;
                    }
                    else if (tooltipItem.datasetIndex === 1)
                    {
                        var dataIndex = tooltipItem.dataIndex;
                        var value = tooltipItem.formattedValue;
                        var label = tooltipItem.label;
                        var datasetLabel = tooltipItem.dataset.label;
                        result = [
                            datasetLabel + ': ' + value,
                        ];
                        return result;
                    }
                    else return '';
                }
            }
        }
    ";
    create_chartjs_line_code($title, $element_id, $labels, $datasets, $tooltip, $x_axis_title, $y_axis_title, 10);
}

/********************************************
 * FUNCTION: RISK FOR LIKELIHOOD AND IMPACT *
 ********************************************/
function report_likelihood_impact() {

    global $lang;
    global $escaper;

    // Get classic risks
    $risks = get_risks(10);

    // Create an empty datasets array
    $datasets = [];

    // Create arrays for each data point
    $max_x = get_likelihoods_count();
    $max_y = get_impacts_count();

    // For each x value from the max to zero
    for ($x=$max_x; $x>=0; $x--) {

        // For each y value from the max to zero
        for ($y=$max_y; $y>=0; $y--) {

            // Create the default values
            $risk_ids = [];
            $risk_subjects = [];
            $count = 0;

            // Search the $risks array for the likelihood and impact values
            foreach ($risks as $risk) {

                // If we have a matching likelihood and impact
                if ($x == $risk['CLASSIC_likelihood'] && $y == $risk['CLASSIC_impact']) {
                    // Get the risk information
                    $risk_id = $risk['id'] + 1000;
                    $risk_ids[] = $risk_id;
                    $mitigation_percent = $risk['mitigation_percent'];
                    $count = count($risk_ids);

                    // Get the inherent and residual risk scores
                    $residual_risk = round(($risk['calculated_risk'] - ($risk['calculated_risk'] * $mitigation_percent/100)), 2);
                    $inherent_risk = round($risk['calculated_risk'], 2);

                    // Get the color of the calculated risk
                    $color = get_risk_color($inherent_risk);

                    // Get the risk subject to be displayed
                    $subject = str_replace("'", "\'", $risk['subject']);
                    $residual_risk = "{$escaper->escapeHtml($lang['ResidualRisk'])}: {$residual_risk}";
                    $residual_risk = str_pad($residual_risk, 20);
                    $risk_subjects[] = truncate_to("{$residual_risk}[{$risk_id}] {$subject}", 50);
                }
            }

            // If we have at least one risk in the dataset
            if ($count > 0) {

                // Scale the r value based on the count but no more than 50
                $r = ($count * 3 < 50 ? $count * 3 : 50);

                // Create the dataset for these likelihood and impact values
                $dataset = [
                    "x" => $x,
                    "y" => $y,
                    "r" => $r,
                    "label" => "{$inherent_risk}",
                    "color" => "{$color}",
                    "ids" => $risk_ids,
                    "subjects" => $risk_subjects,
                    "count" => $count,
                ];

                // Add the dataset to the datasets array
                $datasets[] = $dataset;
            }
        }
    }

    // Create a custom tooltip to update the labels
    $tooltip = "
        tooltip: {
            callbacks: {
                label: function (tooltipItem) {
                    // Get the dataset index for the selected tooltip
                    var index = tooltipItem.datasetIndex;
                    // Get the score
                    var score = scores[index];
                    dataset_ids = ids[index];
                    dataset_subjects = subjects[index];
                    result = [
                        '{$escaper->escapeHtml($lang['InherentRisk'])}: ' + score,
                    ];
                    // For each risk with this score
                    for (i=0; i<dataset_ids.length; ++i)
                    {
                        // Add it to the result
                        result.push(dataset_subjects[i]);
                    }
                    return result;
                }
            }
        }
    ";

    $element_id = "likelihood_impact_chart";
    $x_axis_title = $escaper->escapeHtml($lang['Likelihood']);
    $y_axis_title = $escaper->escapeHtml($lang['Impact']);
    create_chartjs_bubble_code("", $element_id, $datasets, $tooltip, $x_axis_title, $y_axis_title);
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
function display_score_html_from_pending_risk($scoring_method="5", $custom=false, $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamagePotential="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkillLevel="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPEaseOfDiscovery="10", $OWASPEaseOfExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $ContributingLikelihood="", $ContributingImpacts=[], $display_type = 1) {

    global $escaper;
    global $lang;

    if ($custom === false) {
        $custom = get_setting("default_risk_score");
    }

    if (!$scoring_method) {
        $scoring_method = 5;
    }

    if ($display_type === 2) {
        $html = "
            <div class='risk-scoring-container'>
                <div class='row mb-2'>
                    <div class='col-2 d-flex align-items-center justify-content-start'>
                        <label>{$escaper->escapeHtml($lang['RiskScoringMethod'])} :</label>
                    </div>
                    <div class='col-10'>" . 
                        create_dropdown("scoring_methods", $scoring_method, "scoring_method[]", false, false, true, 'form-select') . "
                    </div>
                </div>
                <div id='classic' class='classic-holder' style='display:" . ($scoring_method == 1 ? "block" : "none") . "'>
                    <div class='row mb-2'>
                        <div class='col-2 d-flex align-items-center justify-content-start'>
                            <label>{$escaper->escapeHtml($lang['CurrentLikelihood'])} :</label>
                        </div>
                        <div class='col-10'>" . 
                            create_dropdown('likelihood', $CLASSIC_likelihood, 'likelihood[]', true, false, true, 'form-select') . "
                        </div>
                    </div>
                    <div class='row mb-2'>
                        <div class='col-2 d-flex align-items-center justify-content-start'>
                            <label>{$escaper->escapeHtml($lang['CurrentImpact'])} :</label>
                        </div>
                        <div class='col-10'>" . 
                            create_dropdown('impact', $CLASSIC_impact, 'impact[]', true, false, true, 'form-select') . "
                        </div>
                    </div>
                </div>
        ";
    } else {
        $html = "
            <div class='risk-scoring-container'>
                <div class='mb-3'>
                    <label>{$escaper->escapeHtml($lang['RiskScoringMethod'])} :</label>" . 
                    create_dropdown("scoring_methods", $scoring_method, "scoring_method[]", false, false, true, 'form-select') . "
                </div>
                <div id='classic' class='classic-holder' style='display:" . ($scoring_method == 1 ? "block" : "none") . "'>
                    <div class='mb-3'>
                        <label>{$escaper->escapeHtml($lang['CurrentLikelihood'])} :</label>" . 
                        create_dropdown('likelihood', $CLASSIC_likelihood, 'likelihood[]', true, false, true, 'form-select') . "
                    </div>
                    <div class='mb-3'>
                        <label>{$escaper->escapeHtml($lang['CurrentImpact'])} :</label>" . 
                        create_dropdown('impact', $CLASSIC_impact, 'impact[]', true, false, true, 'form-select') . "
                    </div>
                </div>
        ";
    }

    $html .= "
                <div id='cvss' style='display: " . ($scoring_method == 2 ? "block" : "none") . ";' class='cvss-holder'>
                    <div class='mb-3'>
                        <p><input type='button' class='btn btn-primary' name='cvssSubmit' id='cvssSubmit' value='Score Using CVSS' /></p>
                        <!-- Additional hidden inputs for CVSS -->
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
                </div>
                <div id='dread' style='display: " . ($scoring_method == 3 ? "block" : "none") . ";' class='dread-holder'>
                    <div class='mb-3'>
                        <p><input type='button' class = 'btn btn-primary' name='dreadSubmit' id='dreadSubmit' value='Score Using DREAD' onclick='javascript: popupdread();' /></p>
                        <!-- Additional hidden inputs for DREAD -->
                        <input type='hidden' name='DREADDamage[]' id='DREADDamage' value='{$DREADDamagePotential}' />
                        <input type='hidden' name='DREADReproducibility[]' id='DREADReproducibility' value='{$DREADReproducibility}' />
                        <input type='hidden' name='DREADExploitability[]' id='DREADExploitability' value='{$DREADExploitability}' />
                        <input type='hidden' name='DREADAffectedUsers[]' id='DREADAffectedUsers' value='{$DREADAffectedUsers}' />
                        <input type='hidden' name='DREADDiscoverability[]' id='DREADDiscoverability' value='{$DREADDiscoverability}' />
                    </div>
                </div>
                <div id='owasp' style='display: " . ($scoring_method == 4 ? "block" : "none") . ";' class='owasp-holder'>
                    <div class='mb-3'>
                        <p><input type='button' class = 'btn btn-primary' name='owaspSubmit' id='owaspSubmit' value='Score Using OWASP'  /></p>
                        <!-- Additional hidden inputs for OWASP -->
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
                </div>
    ";
    
    if ($display_type === 2) {
        $html .= "
                <div id='custom' style='display: " . ($scoring_method == 5 ? "block" : "none") . ";' class='custom-holder'>
                    <div class='row mb-2'>
                        <div class='col-2 d-flex align-items-center justify-content-start'>
                            <label>{$escaper->escapeHtml($lang['CustomValue'])} :</label>
                        </div>
                        <div class='col-10 d-flex align-items-center'>
                            <input type='number' min='0' step='0.1' max='10' name='Custom[]' id='Custom' value='{$custom}' class='form-control m-r-10' style='width: 70px;'/>
                            <small class='form-text text-muted'>(Must be a numeric value between 0 and 10)</small>
                        </div>
                    </div>
                </div>          
                                    
        ";
    } else {
        $html .= "
                <div id='custom' style='display: " . ($scoring_method == 5 ? "block" : "none") . ";' class='custom-holder'>
                    <div class='mb-3'>
                        <label>{$escaper->escapeHtml($lang['CustomValue'])} :</label>
                        <input type='number' min='0' step='0.1' max='10' name='Custom[]' id='Custom' value='{$custom}' class='form-control' />
                        <small class='form-text text-muted'>(Must be a numeric value between 0 and 10)</small>
                    </div>
                </div>
        ";
    }
                            
    $html .= "
                <div id='contributing-risk' style='display: " . ($scoring_method == 6 ? "block" : "none") . ";' class='contributing-risk-holder'>
                    <div class='mb-3'>
                        <p><input type='button' class = 'btn btn-primary' name='contributingRiskSubmit' id='contributingRiskSubmit' value='{$escaper->escapeHtml($lang["ScoreUsingContributingRisk"])}' /></p>
                        <input type='hidden' name='ContributingLikelihood[]' id='contributing_likelihood' value='" . ($ContributingLikelihood ? $ContributingLikelihood : count(get_table("likelihood"))) . "' />
    ";
    $max_impact_value = count(get_table("impact"));
    $contributing_risks = get_contributing_risks();
    foreach ($contributing_risks as $contributing_risk) {
        $html .= "
                        <input type='hidden' class='contributing-impact' name='ContributingImpacts[{$contributing_risk['id']}][]' id='contributing_impact_{$escaper->escapeHtml($contributing_risk['id'])}' value='{$escaper->escapeHtml(empty($ContributingImpacts[ $contributing_risk['id'] ]) ? $max_impact_value : $ContributingImpacts[ $contributing_risk['id'] ])}' />
        ";
    }
    $html .= "
                    </div>
                </div>
            </div>
    ";

    echo $html;
}

/***************************************************
* FUNCTION: DISPLAY SET DEFAULT DATE FORMAT SCRIPT *
****************************************************/
function display_set_default_date_format_script()
{
    global $escaper;
    echo "
        <script type=''>
            if($.datepicker !== undefined){
                $.datepicker.setDefaults({dateFormat: '{$escaper->escapeHtml(get_default_date_format_for_js())}'});
            }
            var default_date_format = '{$escaper->escapeHtml(get_default_date_format_for_js())}';
        </script>
    ";
}

/*******************************
 * FUNCTION: CREATE RISK TABLE *
 *******************************/
function create_risk_formula_table() {

    global $lang;
    global $escaper;

    $impacts = array_reverse(get_table("impact"));
    $likelihoods = get_table("likelihood");

    $risk_levels = get_risk_levels();
    $risk_levels_by_color = array();
    foreach ($risk_levels as $risk_level) {
        $risk_levels_by_color[$risk_level['name']] = $risk_level;
    }

    $risk_model = get_setting("risk_model");

    echo "
        <h4 class='page-title'>{$escaper->escapeHtml($lang['MyClassicRiskFormulaIs'])} :</h4>
        <form name='risk_levels' method='post' action=''>
            <div class='row'>
                <div class='col-md-4'>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['RISK'])} =</label>
    ";
                        create_dropdown("risk_models", $risk_model, null, false);
    echo "
                    </div>
                </div>
            </div>
            <input type='submit' value='{$escaper->escapeHtml($lang['Update'])}' name='update_risk_formula' class='btn btn-submit'/>
        </form>
        <br>
    ";
    
    // Create legend table
    echo "
        <table class='risk-level-table'>
            <tr height='20px'>
                <td><div class='risk-table-veryhigh' style='background-color: {$risk_levels_by_color['Very High']['color']}' /></td>
                <td>{$escaper->escapeHtml($risk_levels_by_color['Very High']['display_name'] . " " . $lang['Risk'])}</td>
                <td>&nbsp;</td>
                <td><div class='risk-table-high' style='background-color: {$risk_levels_by_color['High']['color']}' /></td>
                <td>{$escaper->escapeHtml($risk_levels_by_color['High']['display_name'] . " " . $lang['Risk'])}</td>
                <td>&nbsp;</td>
                <td><div class='risk-table-medium' style='background-color: {$risk_levels_by_color['Medium']['color']}' /></td>
                <td>{$escaper->escapeHtml($risk_levels_by_color['Medium']['display_name'] . " " . $lang['Risk'])}</td>
                <td>&nbsp;</td>
                <td><div class='risk-table-low' style='background-color: {$risk_levels_by_color['Low']['color']}' /></td>
                <td>{$escaper->escapeHtml($risk_levels_by_color['Low']['display_name'] . " " . $lang['Risk'])}</td>
                <td>&nbsp;</td>
                <td><div class='risk-table-insignificant' style='background-color: white' /></td>
                <td>{$escaper->escapeHtml($lang['Insignificant'])}</td>
            </tr>
        </table>
        
        <br>
        
        <div class='d-flex m-l-40 mb-2 impact-btn-container'>
            <a id='add-impact' href='#'><i class='fa fa-plus' title='{$escaper->escapeHtml($lang['AddImpact'])}' width='25px'></i></a>&nbsp;&nbsp;&nbsp;&nbsp;
            <a id='delete-impact' href='#'><i class='fa fa-minus' title='{$escaper->escapeHtml($lang['DeleteImpact'])}' width='25px'></i></a>
        </div>
        
        <div class='risk-formula-table-container overflow-x-auto'>
            <table class='risk-formula-table' border='0' cellspacing='0' cellpadding='10' style='display: block; white-space: nowrap;'>
    ";

    // For each impact level
    foreach($impacts as $i => $impact) {

        echo "
                <tr>
        ";

        // If this is the first row add the y-axis label
        if ($i == 0) {
            echo "
                    <td rowspan='" . count($impacts) . "'>
                        <div id='impact-label'>
                            <b>{$escaper->escapeHtml($lang['Impact'])}</b>
                        </div>
                    </td>
            ";
        }

        $impact_name = $impacts[$i]['name'] ? $escaper->escapeHtml($impacts[$i]['name']) : "--";
        $impact_value = $escaper->escapeHtml($impacts[$i]['value']);

        // Add the y-axis values
        echo "
                    <td bgcolor='silver' height='50px' width='200px'>
                        <span>
                            <span class='editable'>{$impact_name}</span>
                            <input type='text' class='editable' value='{$impact_name}' style='display: none;' data-type='impact' data-id='{$impact_value}'>
                        </span>                    
                    </td>
                    <td bgcolor='silver' align='center' height='50px' width='50px'>{$escaper->escapeHtml($impacts[$i]['value'])}</td>
        ";

        // For each likelihood level
        foreach($likelihoods as $j => $likelihood) {

            // Calculate risk
            $risk = calculate_risk($impact['value'], $likelihood['value']);

            // Get the risk color
            $color = get_risk_color($risk);
            $value = $escaper->escapeHtml($risk);

            echo "
                    <td align='center' bgcolor='{$escaper->escapeHtml($color)}' height='50px' width='150px'>
                        <span>
            ";

            if ($risk_model == 6) {

                echo "
                            <span class='editable'>{$value}</span>
                            <input type='text' class='editable' value='{$value}' style='display: none;' data-type='score' data-impact='{$impact['value']}' data-likelihood='{$likelihood['value']}'>
                ";

            } else {

                echo        $value;

            }

            echo "
                        </span>
                    </td>
            ";

        }

        echo "
                    <td>&nbsp;</td>
                </tr>
        ";

    }

    echo "
                <tr>
                    <td>&nbsp;</td>
                    <td bgcolor='silver'>&nbsp;</td>
                    <td bgcolor='silver'>&nbsp;</td>
    ";

    // Add the x-axis values
    foreach (range(1, count($likelihoods)) as $likelihood_value) {

        echo "
                    <td align='center' bgcolor='silver'>
                        {$likelihood_value}
                    </td>
        ";

    }

    echo "
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td bgcolor='silver'>&nbsp;</td>
                    <td bgcolor='silver'>&nbsp;</td>
    ";

    // Add the x-axis names
    foreach ($likelihoods as $likelihood) {

        $likelihood_name = $likelihood['name'] ? $escaper->escapeHtml($likelihood['name']) : "--";
        $likelihood_value = $escaper->escapeHtml($likelihood['value']);

        echo "
                    <td align='center' bgcolor='silver' height='50px' width='100px'>
                        <span>
                            <span class='editable'>{$likelihood_name}</span>
                            <input type='text' class='editable' value='{$likelihood_name}' style='display: none;' data-type='likelihood' data-id='{$likelihood_value}'>
                        </span> 
                    </td>
        ";
        
    }

    echo "
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td colspan='" . count($likelihoods) . "' align='center'><b>{$escaper->escapeHtml($lang['Likelihood'])}</b></td>
                    <td align='center'>
                        <div class='d-flex likelihood-btn-container'>
                            <a id='add-likelihood' href='#'><i class='fa fa-plus' title='{$escaper->escapeHtml($lang['AddLikelihood'])}' width='25px'></i></a>&nbsp;&nbsp;&nbsp;&nbsp;
                            <a id='delete-likelihood' href='#'><i class='fa fa-minus' title='{$escaper->escapeHtml($lang['DeleteLikelihood'])}' width='25px'></i></a>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
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
                        type: 'POST',
                        url: BASE_URL + '/api/riskformula/add_impact',
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
                        type: 'POST',
                        url: BASE_URL + '/api/riskformula/delete_impact',
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
                        type: 'POST',
                        url: BASE_URL + '/api/riskformula/add_likelihood',
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
                        type: 'POST',
                        url: BASE_URL + '/api/riskformula/delete_likelihood',
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
function view_risks_and_controls_selections($report, $sort_by, $projects, $status) {

    global $lang;
    global $escaper;

    echo "
        <div class='row form-group'>
            <div class='col-3'>
                <label>{$escaper->escapeHtml($lang['Report'])} :</label>
                <select class='form-select' id='report' name='report' onchange='javascript: submit()'>
                    <option value='0'" . ($report == 0 ? " selected" : "") . ">{$escaper->escapeHtml($lang['RisksByControl'])}</option>
                    <option value='1'" . ($report == 1 ? " selected" : "") . ">{$escaper->escapeHtml($lang['ControlsByRisk'])}</option>
                </select>
            </div>
            <div class='col-3'>
                <label>{$escaper->escapeHtml($lang['Project'])} :</label>
    "; 
                create_multiple_dropdown("projects", $projects, NULL, NULL, true, $lang['Unassigned'], "-1");
    echo "
            </div>
    ";
    if ($report == 0) {
        echo "
            <div class='col-3'>
                <label>{$escaper->escapeHtml($lang['SortBy'])} :</label>
                <select class='form-select' id='sortby' name='sort_by' onchange='javascript: submit()'>
                    <option value='0'" . ($sort_by == 0 ? " selected" : "") . ">{$escaper->escapeHtml($lang['ControlName'])}</option>
                    <option value='1'" . ($sort_by == 1 ? " selected" : "") . ">{$escaper->escapeHtml($lang['ControlRisk'])}</option>
                </select>
            </div>
        ";
    }
    echo "
            <div class='col-3'>
                <label>{$escaper->escapeHtml($lang['Status'])} :</label>
                <select class='form-select' id='status' name='status' onchange='javascript: submit();'>
                    <option value='0'" . ($status == 0 ? " selected" : "") . ">{$escaper->escapeHtml($lang['OpenRisks'])}</option>
                    <option value='1'" . ($status == 1 ? " selected" : "") . ">{$escaper->escapeHtml($lang['ClosedRisks'])}</option>
                    <option value='2'" . ($status == 2 ? " selected" : "") . ">{$escaper->escapeHtml($lang['AllRisks'])}</option>
                </select>
            </div>
        </div>
            
        <script>
            $(function() {
                $('#projects').multiselect({
                    allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
                    enableFiltering: true,
                    buttonWidth: '100%',
                    maxHeight: 250,
                    includeSelectAllOption: true,
                    enableCaseInsensitiveFiltering: true,
                    onChange: function(){
                        $('form[name=select_report]').submit();
                    }
                });
            });
        </script>
    ";
}

/********************************************
* FUNCTION: VIEW CONTROLS FILTER SELECTIONS *
*********************************************/
function view_controls_filter_selections() {

    global $lang;
    global $escaper;

    if (count($_POST) > 3) {
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

    echo   "
        <div class='row form-group'>
            <div class='col-4'>
                <label>{$escaper->escapeHtml($lang['ControlFrameworks'])} :</label>
    ";
                create_multiple_dropdown("control_framework", $control_framework, null, getAvailableControlFrameworkList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "
            </div>
            <div class='col-4'>
                <label>{$escaper->escapeHtml($lang['ControlFamily'])} :</label>
    ";
                create_multiple_dropdown("control_family", $control_family, null, getAvailableControlFamilyList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "
            </div>
            <div class='col-4'>
                <label>{$escaper->escapeHtml($lang['ControlClass'])} :</label>
    ";
                create_multiple_dropdown("control_class", $control_class, null, getAvailableControlClassList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "
            </div>
        </div>
        <div class='row form-group'>
            <div class='col-4'>
                <label>{$escaper->escapeHtml($lang['ControlPhase'])} :</label>
    ";
                create_multiple_dropdown("control_phase", $control_phase, null, getAvailableControlPhaseList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "
            </div>
            <div class='col-4'>
                <label>{$escaper->escapeHtml($lang['ControlPriority'])} :</label>
    ";
                create_multiple_dropdown("control_priority", $control_priority, null, getAvailableControlPriorityList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "
            </div>
            <div class='col-4'>
                <label>{$escaper->escapeHtml($lang['ControlOwner'])} :</label>
    ";
                create_multiple_dropdown("control_owner", $control_owner, null, getAvailableControlOwnerList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1");
    echo "
            </div>
        </div>
        
        <script>
            $(document).ready( function(){
                $('select[multiple]').multiselect({
                    allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
                    enableFiltering: true,
                    maxHeight: 250,
                    buttonWidth: '100%',
                    includeSelectAllOption: true,
                    enableCaseInsensitiveFiltering: true,
                    onChange: function(){
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
function display_contributing_risk_formula() {

    global $lang, $escaper;

    echo "
        <table id='template-for-adding' style='display: none'>
            <tr>
                <td align='center'></td>
                <td align='center'><input type='text' class='new-name form-control' value=''></td>
                <td align='center'><a class='delete-row'><i class='fa fa-trash'></i></a></td>
                <td></td>
            </tr>
        </table>
        <table id='template-for-impact-adding' style='display: none'>
            <tr>
                <td class='p-1 border-0' align='center'><input type='text' class='subject form-control' required name='subject[]' style='max-width: none'></td>
                <td class='p-1 border-0' align='center'><input type='number' class='weight form-control' required step='0.01' name='weight[]' max='1' min='0'></td>
                <td class='p-1 border-0' align='center' style='vertical-align: middle;'><a class='delete-row' href=''><i class='fa fa-trash'></i></a></td>
            </tr>
        </table>
        <div class='well card-body my-2 border'>
            <div class='row'>
                <div class='col-6'>
                    <h4>{$escaper->escapeHtml($lang["Likelihood"])}</h4>
                </div>
                <div class='col-6 text-end'>
                    <button id='likelihood-add-btn' class='btn btn-dark'><i class='fa fa-plus'></i></button>
                </div>
            </div>

            <table width='100%' id='contributing-risks-likelihood-table' class='table table-hover header'>
                <thead>
                    <tr class='text-center'>
                        <th width='10%'>{$escaper->escapeHtml($lang["Value"])}</th>
                        <th width='40%'>{$escaper->escapeHtml($lang["Name"])}</th>
                        <th width='20%'>{$escaper->escapeHtml($lang["Actions"])}</td>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
    ";

    $table_list = display_contributing_risks_likelihood_table_list();

    echo            $table_list;
    echo "
                </tbody>
            </table>
        </div>
        <div class='well card-body my-2 border'>
            <div class='row'>
                <div class='col-6'>
                    <h4>{$escaper->escapeHtml($lang["Impact"])}</h4>
                </div>
                <div class='col-6 text-end'>
                    <button id='add-impact-row' class='btn btn-dark'>{$escaper->escapeHtml($lang["AddImpact"])}</button>
                </div>
            </div>
            <form id='contributing_risk_form' method='post' action=''>
                <table width='100%' id='contributing-risk-table' class='table header'>
                    <thead>
                        <tr>
                            <th width='50%'>{$escaper->escapeHtml($lang["Subject"])}</th>
                            <th width='30%'>{$escaper->escapeHtml($lang["ContributionWeight"])}</th>
                            <th class='text-center'>{$escaper->escapeHtml($lang["Actions"])}</th>
                        </tr>
                    </thead>
                    <tbody>
    ";

    $contributing_risks = get_contributing_risks();
    foreach ($contributing_risks as $key => $contributing_risk) {

        echo "
                        <tr>
                            <td class='p-1 border-0' align='center'><input type='text' class='subject form-control' required name='existing_subject[{$contributing_risk["id"]}]' style='max-width: none' value='{$escaper->escapeHtml($contributing_risk['subject'])}'></td>
                            <td class='p-1 border-0' align='center'><input type='number' class='weight form-control' required step='0.01' name='existing_weight[{$contributing_risk["id"]}]' value='{$escaper->escapeHtml($contributing_risk['weight'])}' max='1' min='0'></td>
                            <td class='p-1 border-0' align='center' style='vertical-align: middle; " . ($key==0 ? ("display: none;") : "") . "'><a class='delete-row' href=''><i class='fa fa-trash'></i></a></td>
                        </tr>
        ";

    }
                
    echo "
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' align='right'><button type='submit' name='save_contributing_risk' class='btn btn-submit'>{$escaper->escapeHtml($lang["Save"])}</button></td>
                        </tr>
                    </tfoot>
                </table>
            </form>
            <hr><br>
            <table width='100%' id='contributing-risks-impact-table' class='table table-hover v-middle header'>
                <thead>
                    <tr class='text-center'>
                        <th width='10%'>{$escaper->escapeHtml($lang["Value"])}</th>
                        <th width='40%'>{$escaper->escapeHtml($lang["Name"])}</th>
                        <th>{$escaper->escapeHtml($lang["Actions"])}</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
    ";

    $table_list = display_contributing_risks_impact_table_list();
    echo            $table_list;
    echo "
                </tbody>
            </table>
        </div>
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

                    // Check if the name is empty or trimmed empty
                    if (!name || !name.trim()) {
                        showAlertFromMessage('{$escaper->escapeHtml($lang['TheNameFieldCannotBeEmptyOrContainOnlySpaces'])}');
                        return;
                    }

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
                        if(!retryCSRF(xhr, this)) {
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
                var table_id = $(this).parents('table').attr('id');
                var name = _this.val();

                // Check if the name is empty or trimmed empty in the contributing risks likelihood table
                if (table_id == 'contributing-risks-likelihood-table') {
                    if (!name || !name.trim()) {
                        showAlertFromMessage('{$escaper->escapeHtml($lang['TheNameFieldCannotBeEmptyOrContainOnlySpaces'])}');
                        return;
                    }
                } else {
                    if(!name) {
                        return false;
                    }
                }

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
                        if(!retryCSRF(xhr, this)) {
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
                        if(!retryCSRF(xhr, this)) {
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

            $('#contributing_risk_form').submit(function(){
                if(!$(this).find('.weight').length) {
                    return false;
                }
                var totalWeight = 0;
                $(this).find('.weight').each(function(){
                    totalWeight += Number($(this).val());
                    totalWeight = (+totalWeight.toFixed(4))
                })
                if(totalWeight != 1){
                    showAlertFromMessage('{$escaper->escapeHtml($lang['TotalContributingWeightsShouldBe1'])}');
                    
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

    echo "
        <div class='row align-items-center mb-2'>
            <div class='col-3'>
                <label>" . $escaper->escapeHtml($lang["Subject"]) . "</label>
            </div>
            <div class='col-3'>
                <label>" . $escaper->escapeHtml($lang["Weight"]) . "</label>
            </div>
            <div class='col-6'>
                <label>" . $escaper->escapeHtml($lang["Impact"]) . "</label>
            </div>
        </div>
    ";

    $contributing_risks = get_contributing_risks();

    foreach($contributing_risks as $contributing_risk) {

        $impacts = get_impact_values_from_contributing_risks_id($contributing_risk['id']);

        echo "
        <div class='contributing_impact_row row align-items-center mb-2'>
            <div class='col-3'>" . 
                $escaper->escapeHtml($contributing_risk['subject']) . "
            </div>
            <div class='col-3 contributing_weight'>" . 
                $escaper->escapeHtml($contributing_risk['weight']) . "
            </div>
            <div class='col-6 contributing_impact'>" . 
                create_dropdown("impact", NULL, "contributing_impact_{$contributing_risk['id']}", false, false, true, "", "--", "", true, 0, $impacts) . "
            </div>
        </div>
        ";
    }
}

/*******************************************
* FUNCTION: DISPLAY PLAN MITIGATIONS TABLE *
********************************************/
function display_plan_mitigations()
{

    global $lang, $escaper;

    $user = get_user_by_id($_SESSION['uid']);
    $settings = json_decode($user["custom_plan_mitigation_display_settings"] ?? '', true);
    $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
    $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
    $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
    $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
    $columns = [];

    foreach($columns_setting as $column) {
        if(stripos($column[0], "custom_field_") !== false) {
            if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
        } else if($column[1] == 1) {
            $columns[] = $column[0];
        }
    }
    if(!count($columns)) {
        $columns = array("id","risk_status","subject","calculated_risk","submission_date","mitigation_planned","management_review");
    }

    $tr = "";
    $index = 0;
    $order_index = 0;
    $order_dir = "asc";

    // If the Customization Extra exists
    $file = realpath(__DIR__ . '/../extras/customization/index.php');
    if (file_exists($file)) {
        // Load it
        require_once($file);
    }

    foreach($columns as $column) {

        if($column == "calculated_risk") {
            $order_index = $index;
            $order_dir = "desc";
        }
        if($column == "subject") {
            $style = "min-width:250px;";
        } else {
            $style = "min-width:100px;";
        }

        if(($pos = stripos($column, "custom_field_")) !== false) {
            if(customization_extra()) {
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $label = $escaper->escapeHtml($custom_field['name']);
                $tr .= "<th data-name='" . $column . "' align='left' style='" . $style . "'>" . $label . "</th>";
                $index++;
            }
        } else {
            $label = get_label_by_risk_field_name($column);
            $tr .= "<th data-name='" . $column . "' align='left' style='" . $style . "'>" . $label . "</th>";
            $index++;
        }
    }

    $tableID = "plan-mitigations";
    echo "
        <table id='{$tableID}' width='100%' class='risk-datatable table table-bordered table-striped table-condensed'>
            <thead >
                <tr>{$tr}</tr> 
            </thead>
            <tbody>
            </tbody>
        </table>
        <script>
            $(function(){
                var pageLength = 10;
                var form = $('#{$tableID}').parents('form');
                $('#{$tableID} thead tr').clone(true).appendTo( '#{$tableID} thead');
                $('#{$tableID} thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    var data_name = $(this).attr('data-name');
                    if(data_name == 'mitigation_planned') {
                        $(this).html( '<select name=\"mitigation_planned\" class= \"form-control\"><option value=\"\">--</option><option value=\"" . $escaper->escapeHtml($lang['Yes']) . "\">" . $escaper->escapeHtml($lang['Yes']) . "</option><option value=\"" . $escaper->escapeHtml($lang['No']) . "\">" . $escaper->escapeHtml($lang['No']) . "</option></select>' );
                    } else if(data_name == 'management_review') {
                        $(this).html( '<select name=\"management_review\" class= \"form-control\"><option value=\"\">--</option><option value=\"" . $escaper->escapeHtml($lang['Yes']) . "\">" . $escaper->escapeHtml($lang['Yes']) . "</option><option value=\"" . $escaper->escapeHtml($lang['No']) . "\">" . $escaper->escapeHtml($lang['No']) . "</option><option value=\"" . $escaper->escapeHtml($lang['PASTDUE']) . "\">" . $escaper->escapeHtml($lang['PASTDUE']) . "</option></select>' );
                    } else {
                        $(this).html(''); // To clear the title out of the header cell
                        $('<input type=\"text\" class= \"form-control\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    }

                    $( 'input, select', this ).on( 'change', function () {
                        if ( datatableInstance.column(i).search() !== this.value ) {
                            datatableInstance.column(i).search( this.value ).draw();
                        }
                    });
                });
                var datatableInstance = $('#{$tableID}').DataTable({
                    bFilter: true,
                    bSort: true,
                    orderCellsTop: true,
                    scrollX: true,
                    createdRow: function(row, data, index){
                        var background = $('.background-class', $(row)).data('background');
                        $(row).find('td').addClass(background)
                    },
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
    $settings = json_decode($user["custom_perform_reviews_display_settings"] ?? '', true);
    $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
    $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
    $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
    $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
    $columns = [];

    foreach($columns_setting as $column) {
        if(stripos($column[0], "custom_field_") !== false) {
            if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
        } else if($column[1] == 1) {
            $columns[] = $column[0];
        }
    }
    if(!count($columns)) {
        $columns = array("id","risk_status","subject","calculated_risk","submission_date","mitigation_planned","management_review");
    }

    $tr = "";
    $index = 0;
    $order_index = 0;
    $order_dir = "asc";

    // If the Customization Extra exists
    $file = realpath(__DIR__ . '/../extras/customization/index.php');
    if (file_exists($file)) {
        // Load it
        require_once($file);
    }

    foreach($columns as $column) {

        if($column == "calculated_risk") {
            $order_index = $index;
            $order_dir = "desc";
        }

        if($column == "subject") {
            $style = "min-width:250px;";
        } else {
            $style = "min-width:100px;";
        }

        if(($pos = stripos($column, "custom_field_")) !== false) {
            if(customization_extra()){
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $label = $escaper->escapeHtml($custom_field['name']);
                $tr .= "<th data-name='" . $column . "' align='left' style='" . $style . "'>" . $label . "</th>";
                $index++;
            }
        } else {
            $label = get_label_by_risk_field_name($column);
            $tr .= "<th data-name='" . $column . "' align='left' style='" . $style . "'>" . $label . "</th>";
            $index++;
        }
    }

    $tableID = "management-review";
    echo "
        <table id='{$tableID}' width='100%' class='risk-datatable table table-bordered table-striped table-condensed'>
            <thead >
                <tr>{$tr}</tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <script>
            $(function(){
                var form = $('#{$tableID}').parents('form');
                $('#{$tableID} thead tr').clone(true).appendTo( '#{$tableID} thead');
                $('#{$tableID} thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    var data_name = $(this).attr('data-name');
                    if(data_name == 'mitigation_planned') {
                        $(this).html( '<select name=\"mitigation_planned\" class=\"form-control\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option></select>' );
                    } else if(data_name == 'management_review') {
                        $(this).html( '<select name=\"management_review\" class=\"form-control\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option><option value=\"".$escaper->escapeHtml($lang['PASTDUE'])."\">".$escaper->escapeHtml($lang['PASTDUE'])."</option></select>' );
                    } else {
                        $(this).html(''); // To clear the title out of the header cell
                        $('<input type=\"text\" class=\"form-control\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    }

                    $( 'input, select', this ).on( 'change', function () {
                        if ( datatableInstance.column(i).search() !== this.value ) {
                            datatableInstance.column(i).search( this.value ).draw();
                        }
                    });
                });
                var datatableInstance = $('#{$tableID}').DataTable({
                    bFilter: true,
                    bSort: true,
                    orderCellsTop: true,
                    scrollX: true,
                    createdRow: function(row, data, index){
                        var background = $('.background-class', $(row)).data('background');
                        $(row).find('td').addClass(background)
                    },
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
    $settings = json_decode($user["custom_reviewregularly_display_settings"] ?? '', true);
    $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
    $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
    $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
    $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
    $columns = [];

    foreach($columns_setting as $column) {
        if(stripos($column[0], "custom_field_") !== false) {
            if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
        } else if($column[1] == 1) {
            $columns[] = $column[0];
        }
    }

    if(!count($columns)) {
        $columns = array("id","risk_status","subject","calculated_risk","days_open","next_review_date");
    }

    $tr = "";
    $index = 0;
    $order_index = 0;
    $order_dir = "asc";

    // If the Customization Extra exists
    $file = realpath(__DIR__ . '/../extras/customization/index.php');
    if (file_exists($file)) {
        // Load it
        require_once($file);
    }

    foreach($columns as $column) {
        if($column == "next_review_date") {
            $order_index = $index;
            $order_dir = "asc";
        }
        if($column == "subject") {
            $style = "min-width:250px;";
        } else {
            $style = "min-width:100px;";
        }
        if(($pos = stripos($column, "custom_field_")) !== false) {
            if(customization_extra()) {
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $label = $escaper->escapeHtml($custom_field['name']);
                $tr .= "<th data-name='" . $column . "' align='left' style='" . $style . "'>" . $label . "</th>";
                $index++;
            }
        } else {
            $label = get_label_by_risk_field_name($column);
            $tr .= "<th data-name='" . $column . "' align='left' style='" . $style . "'>" . $label . "</th>";
            $index++;
        }
    }

    $tableID = "review-risks";
    echo "

        <table id='{$tableID}' width='100%' class='risk-datatable table table-bordered table-striped table-condensed'>
            <thead >
                <tr>{$tr}</tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <script>
            $(function(){
                var form = $('#{$tableID}').parents('form');
                $('#{$tableID} thead tr').clone(true).appendTo( '#{$tableID} thead');
                $('#{$tableID} thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    var data_name = $(this).attr('data-name');
                    if(data_name == 'mitigation_planned') {
                        $(this).html( '<select name=\"mitigation_planned\" class=\"form-control\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option></select>' );
                    } else if(data_name == 'management_review') {
                        $(this).html( '<select name=\"management_review\" class=\"form-control\"><option value=\"\">--</option><option value=\"".$escaper->escapeHtml($lang['Yes'])."\">".$escaper->escapeHtml($lang['Yes'])."</option><option value=\"".$escaper->escapeHtml($lang['No'])."\">".$escaper->escapeHtml($lang['No'])."</option><option value=\"".$escaper->escapeHtml($lang['PASTDUE'])."\">".$escaper->escapeHtml($lang['PASTDUE'])."</option></select>' );
                    } else {
                        $(this).html(''); // To clear the title out of the header cell
                        $('<input type=\"text\" class=\"form-control\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    }

                    $( 'input, select', this ).on( 'change', function () {
                        if ( datatableInstance.column(i).search() !== this.value ) {
                            datatableInstance.column(i).search( this.value ).draw();
                        }
                    });
                });
                 var datatableInstance = $('#{$tableID}').DataTable({
                    bFilter: true,
                    bSort: true,
                    orderCellsTop: true,
                    scrollX: true,
                    createdRow: function(row, data, index){
                        var background = $('.background-class', $(row)).data('background');
                        $(row).find('td').addClass(background)
                    },
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
            });
        </script>
    ";
}

/**********************************************
 * FUNCTION: DISPLAY THE DYNAMIC AUDIT REPORT *
***********************************************/
function display_dynamic_audit_report() {

    global $lang, $escaper;
    
	echo "
		<div class='card-body border my-2'>
			<div class='row'>
				<div class='col-10'></div>
				<div class='col-2'>
					<div style='float: right;'>
	";
						render_column_selection_widget('dynamic_audit_report');
	echo "
					</div>
				</div>
			</div>
			<div class='row'>
				<div class='col-12'>
	";
					render_view_table('dynamic_audit_report');
	echo "
				</div>
			</div>
		</div>

        <script>
            $(function () {
                $('.header_filter .multiselect').multiselect({
					allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
					includeSelectAllOption: true,
					buttonWidth: '100%',
                    maxHeight: 400,
					enableCaseInsensitiveFiltering: true,
				});
            });
        </script>
	";

}

/*****************************************
* FUNCTION: DISPLAY AUDIT TIMELINE TABLE *
******************************************/
function display_audit_timeline() {

    global $lang, $escaper;
    
    // If User has permission for complicance menu, shows Audit Timeline report
    if(!empty($_SESSION['compliance'])) {

        $tableID = "audit-timeline";
        
        echo "
            <table id='{$tableID}' width='100%' class='risk-datatable table table-bordered table-striped table-condensed'>
                <thead >
                    <tr >
                        <th data-name='actions' align='left' width='200px' valign='top'>{$escaper->escapeHtml($lang['Actions'])}</th>
                        <th data-name='test_name' align='left' width='200px' valign='top'>{$escaper->escapeHtml($lang['TestName'])}</th>
                        <th data-name='associated_frameworks' align='left' valign='top'>{$escaper->escapeHtml($lang['AssociatedFrameworks'])}</th>
                        <th data-name='last_test_date' align='left' width='150px' valign='top'>{$escaper->escapeHtml($lang['LastTestDate'])}</th>
                        <th data-name='last_test_result' align='left' width='150px' valign='top'>{$escaper->escapeHtml($lang['LastTestResult'])}</th>
                        <th data-name='next_test_date' align='center' width='150px' valign='top'>{$escaper->escapeHtml($lang['NextTestDate'])}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            
            <script>
                $(function () {
                    var form = $('#{$tableID}').parents('form');
                    var datatableInstance = $('#{$tableID}').DataTable({
                        bSort: true,
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
                });    
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
        <table id='{$tableID}' width='100%' class='risk-datatable table table-bordered table-striped table-condensed'>
            <thead>
                <tr>
                    <th data-name='id' align='left' valign='top'>" . $escaper->escapeHtml($lang['ID']) . "</th>
                    <th data-name='subject' align='left' valign='top'>" . $escaper->escapeHtml($lang['Subject']) . "</th>
                    <th data-name='next_review' align='center' valign='top'>".$escaper->escapeHtml($lang['NextReviewDate'])."</th>
                    <th data-name='date_format' align='center' valign='top'>".$escaper->escapeHtml($lang['SuspectedDateFormat'])."</th>
                    <th data-name='action' align='center' valign='top'></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

        <script>
            $(function () {
                var datatableInstance = $('#{$tableID}').DataTable({
                    bFilter: false,
                    bSort: true,
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
                            'defaultContent': '<button class=\"confirm btn btn-submit\" style=\"padding: 2px 15px;\">" . $escaper->escapeHtml($lang['Confirm']) . "</button>'
                        }
                    ]
                });

                // Add paginate options
                datatableInstance.on('draw', function(e, settings){

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
                                type: 'POST',
                                url: BASE_URL + '/api/management/risk/fix_review_date_format',
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
            });
        </script>
    ";
}

/**********************************
 * FUNCTION: GET AUDIT TRAIL HTML *
 **********************************/
function get_audit_trail_html($id = NULL, $days = 7, $log_type=NULL) {

    global $escaper;

    // If the ID is greater than 1000 or NULL
    if ($id > 1000 || $id === NULL) {

        $logs = get_audit_trail($id, $days, $log_type);

        foreach ($logs as $log) {

            $date = date(get_default_datetime_format("g:i A T"), strtotime($log['timestamp']));

            echo "
                <p>{$escaper->escapeHtml($date)} > {$escaper->escapeHtml($log['message'])}</p>
            ";
        }

        // Return true
        return true;

    // Otherwise this is not a valid ID
    } else {
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
function display_custom_risk_columns($custom_setting_field = "custom_plan_mitigation_display_settings") {

    global $escaper, $lang;
    $user = get_user_by_id($_SESSION['uid']);
    $settings = json_decode($user[$custom_setting_field], true);

    $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
    $risk_setting = [];
    foreach ($risk_colums_setting as $column) {
        $risk_setting[$column[0]] = $column[1];
    }

    $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
    $mitigation_setting = [];
    foreach ($mitigation_colums_setting as $column) {
        $mitigation_setting[$column[0]] = $column[1];
    }

    $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
    $review_setting = [];
    foreach ($review_colums_setting as $column) {
        $review_setting[$column[0]] = $column[1];
    }

    $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
    $columns = [];
    foreach ($columns_setting as $column) {
        if (stripos($column[0], "custom_field_") !== false) {
            if (customization_extra() && $column[1] == 1) {
                $columns[] = $column[0];
            }
        } else if ($column[1] == 1) {
            $columns[] = $column[0];
        }
    }

    if (!count($columns)) {
        if ($custom_setting_field == "custom_reviewregularly_display_settings") {
            $risk_setting = array("id" => 1, "risk_status" => 1, "subject" => 1, "calculated_risk" => 1, "days_open" => 1);
            $mitigation_setting = array();
            $review_setting = array("management_review" => 0, "review_date" => 0, "next_step" => 0, "next_review_date" => 1);
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
        <div class='well accordion' id='column-selections-container'>
    ";
    
    // If customization extra is enabled
    if(customization_extra()) {

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

        foreach ($active_fields as $active_field) {

            $field = $label = "";
            // If this is main field
            if ($active_field['is_basic'] == 1) {

                $dynamic_field_info = get_dynamic_names_by_main_field_name($active_field['name']);
                if ($dynamic_field_info) {
                    $field = $dynamic_field_info['name'];
                    $label = $dynamic_field_info['text'];
                } else {
                    continue;
                }

            } else {

                $field = "custom_field_{$active_field['id']}";
                $label = $escaper->escapeHtml($active_field['name']);

            }

            $active_field["field"] = $field;
            $active_field["label"] = $label;
            switch ($active_field['tab_index']) {
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
    $str .= "
            <div class='accordion-item'>
                <h2 class='accordion-header'>
                    <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#RiskColumns_container'>{$escaper->escapeHtml($lang['RiskColumns'])}</button>
                </h2>
                <div id='RiskColumns_container' class='accordion-collapse collapse'>
                    <div class='accordion-body card-body'>
                        <div class='row'>
                            <div class='col-6'>
                                <div class='p-3 h-100 border'>
                                    <ul class='sortable sortable-risk mb-0 ps-0'>
    ";

    // variable to store the custom display settings
    $custom_display_settings = [];

    $half_num = ceil(count($risk_columns_keys)/2);
    for ($i = 0; $i < $half_num ; $i++) {

        $field = $risk_columns_keys[$i];
        $elem_id = "checkbox_" . $field;
        $check_val = isset($risk_setting[$field]) ? $risk_setting[$field] : 0;
        $checked = $check_val ? "checked='yes'" : "";

        // if the field is checked, add it to the custom display settings
        if ($check_val) {
            $custom_display_settings[] = $field;
        }

        if (isset($risk_columns[$field])) {
            $str .= "
                                        <li>
                                            <input class='hidden-checkbox form-check-input' type='checkbox' name='{$field}' id='{$elem_id}' {$checked}/>
                                            <label class='ms-2' for='{$elem_id}'>{$risk_columns[$field]}</label>
                                        </li>
            ";
        }
    }
    
    $str .= "
                                    </ul>
                                </div>
                            </div>
                            <div class='col-6'>
                                <div class='p-3 h-100 border'>
                                    <ul class='sortable sortable-risk mb-0 ps-0'>
    ";

    for ($i ; $i < count($risk_columns_keys) ; $i++) {

        $field = $risk_columns_keys[$i];
        $elem_id = "checkbox_" . $field;
        $check_val = isset($risk_setting[$field]) ? $risk_setting[$field] : 0;
        $checked = $check_val ? "checked='yes'" : "";
    
        // if the field is checked, add it to the custom display settings
        if ($check_val) {
            $custom_display_settings[] = $field;
        }

        if (isset($risk_columns[$field])) {
            $str .= "
                                        <li>
                                            <input class='hidden-checkbox form-check-input' type='checkbox' name='{$field}' id='{$elem_id}' {$checked}/>
                                            <label for='{$elem_id}'>{$risk_columns[$field]}</label>
                                        </li>
            ";
        }
    }

    $str .= "
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    ";

    // mitigation columns
    $str .= "
            <div class='accordion-item'>
                <h2 class='accordion-header'>
                    <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#MitigationColumns_container'>{$escaper->escapeHtml($lang['MitigationColumns'])}</button>
                </h2>
                <div id='MitigationColumns_container' class='accordion-collapse collapse'>
                    <div class='accordion-body card-body'>
                        <div class='row'>
                            <div class='col-6'>
                                <div class='p-3 h-100 border'>
                                    <ul class='sortable sortable-mitigation mb-0 ps-0'>
    ";

    $half_num = ceil(count($mitigation_columns_keys)/2);
    for ($i = 0; $i < $half_num ; $i++) {

        $field = $mitigation_columns_keys[$i];
        $check_val = isset($mitigation_setting[$field]) ? $mitigation_setting[$field] : 0;
        $elem_id = "checkbox_" . $field;
        $checked = $check_val ? "checked='yes'" : "";

        // if the field is checked, add it to the custom display settings
        if ($check_val) {
            $custom_display_settings[] = $field;
        }

        if (isset($mitigation_columns[$field])) {
            $str .= "
                                        <li>
                                            <input class='hidden-checkbox form-check-input' type='checkbox' name='{$field}' id='{$elem_id}' {$checked}/>
                                            <label class='ms-2' for='{$elem_id}'>{$mitigation_columns[$field]}</label>
                                        </li>
            ";
        }
    }

    $str .= "
                                    </ul>
                                </div>
                            </div>
                            <div class='col-6'>
                                <div class='p-3 h-100 border'>
                                    <ul class='sortable sortable-mitigation mb-0 ps-0'>
    ";

    for ($i ; $i < count($mitigation_columns_keys) ; $i++) {

        $field = $mitigation_columns_keys[$i];
        $elem_id = "checkbox_" . $field;
        $check_val = isset($mitigation_setting[$field]) ? $mitigation_setting[$field] : 0;
        $checked = $check_val ? "checked='yes'" : "";

        // if the field is checked, add it to the custom display settings
        if ($check_val) {
            $custom_display_settings[] = $field;
        }

        if (isset($mitigation_columns[$field])) {
            $str .= "
                                        <li>
                                            <input class='hidden-checkbox form-check-input' type='checkbox' name='{$field}' id='{$elem_id}' {$checked}/>
                                            <label for='{$elem_id}'>{$mitigation_columns[$field]}</label>
                                        </li>
            ";
        }
    }

    $str .= "
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    ";

    // review columns
    $str .= "
            <div class='accordion-item'>
                <h2 class='accordion-header'>
                    <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#ReviewColumns_container'>{$escaper->escapeHtml($lang['ReviewColumns'])}</button>
                </h2>
                <div id='ReviewColumns_container' class='accordion-collapse collapse'>
                    <div class='accordion-body card-body'>
                        <div class='row'>
                            <div class='col-6'>
                                <div class='p-3 h-100 border'>
                                    <ul class='sortable sortable-review mb-0 ps-0'>
    ";

    $half_num = ceil(count($review_columns_keys)/2);
    for ($i = 0; $i < $half_num ; $i++) {

        $field = $review_columns_keys[$i];
        $check_val = isset($review_setting[$field]) ? $review_setting[$field] : 0;
        $elem_id = "checkbox_" . $field;
        $checked = $check_val ? "checked='yes'" : "";

        // if the field is checked, add it to the custom display settings
        if ($check_val) {
            $custom_display_settings[] = $field;
        }

        if (isset($review_columns[$field])) {
            $str .= "
                                        <li>
                                            <input class='hidden-checkbox form-check-input' type='checkbox' name='{$field}' id='{$elem_id}' {$checked}/>
                                            <label class='ms-2' for='{$elem_id}'>{$review_columns[$field]}</label>
                                        </li>
            ";
        }
    }
    $str .= "
                                    </ul>
                                </div>
                            </div>
                            <div class='col-6'>
                                <div class='p-3 h-100 border'>
                                    <ul class='sortable sortable-review mb-0 ps-0'>
    ";

    for ($i ; $i < count($review_columns_keys) ; $i++) {

        $field = $review_columns_keys[$i];
        $elem_id = "checkbox_" . $field;
        $check_val = isset($review_setting[$field]) ? $review_setting[$field] : 0;
        $checked = $check_val ? "checked='yes'" : "";

        // if the field is checked, add it to the custom display settings
        if ($check_val) {
            $custom_display_settings[] = $field;
        }

        if (isset($review_columns[$field])) {
            $str .= "
                                        <li>
                                            <input class='hidden-checkbox form-check-input' type='checkbox' name='{$field}' id='{$elem_id}' {$checked}/>
                                            <label for='{$elem_id}'>{$review_columns[$field]}</label>
                                        </li>
            ";
        }
    }

    $str .= "
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    ";
    echo $str;

    echo "
        <script>

            // variable to store the custom display settings
            var custom_display_settings = JSON.parse('" . json_encode($custom_display_settings) . "');

        </script>
    ";
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
        'reviewer' => js_string_escape($lang['ReviewedBy']),
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
		echo "
            <div class='license_check alert alert-danger mt-2 mb-0'>" . 
                $escaper->escapeHtml($lang['LicenseCheckFailed']) . "
            </div>
        ";
	}
}

/******************************************
 * FUNCTION: DISPLAY CONTROL GAP ANALYSIS *
 ******************************************/
function display_control_gap_analysis() {

    global $lang, $escaper;

    // If User has permission for governance menu, shows Control Gap Analysis report
    if (!empty($_SESSION['governance'])) {

        // Begin the framework filter form
        echo "
            <div class='card-body border my-2'>
                <form class='w-50' id='framework_form' name='framework_form' method='post' action=''>
        ";

        // Add the filter
        echo "
                    <label>{$escaper->escapeHtml($lang['ControlFramework'])} :</label>
        ";

        // If no framework was posted
        if (!isset($_POST['framework'])) {

            // Set the filter option to None Selected
            $framework = null;

        } else {

            $framework = (int)$_POST['framework'];

        }

                    // Create the dropdown
                    create_dropdown("frameworks", $framework, "framework");

        echo "
                </form>
            </div>

            <script>
                $(function () {
                    var frameworks = document.getElementById('framework');
                    frameworks.addEventListener('change', function() {
                        document.getElementById('framework_form').submit();
                    });
                });
            </script>
        ";

        // If a framework id was posted
        if ($framework != null) {

            echo "
            <div class='card-body border'>
            ";

                // Display the control maturity spider chart
                display_control_maturity_spider_chart($framework);
                
            echo " 
            </div>
            <div class='row my-2'>
                <div class='col-12'>
                    <div>
                        <nav class='nav nav-tabs'>
                            <a class='nav-link active' data-bs-target='#below_maturity' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['BelowMaturity'])}</a>
                            <a class='nav-link' data-bs-target='#at_maturity' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['AtMaturity'])}</a>
                            <a class='nav-link' data-bs-target='#above_maturity' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['AboveMaturity'])}</a>
                        </nav>
                    </div>
                    <div class='tab-content card-body border mt-2'>
                        <div id='below_maturity' class='tab-pane active' tabindex='0'>
            ";
                            display_gap_analysis_table($framework, "below_maturity");
            echo "
                        </div>
                        <div id='at_maturity' class='tab-pane' tabindex='0'>
            ";
                            display_gap_analysis_table($framework, "at_maturity");
            echo "
                        </div>
                        <div id='above_maturity' class='tab-pane' tabindex='0'>
            ";
                            display_gap_analysis_table($framework, "above_maturity");
            echo "
                        </div>
                    </div>
                </div>
            </div>

            <script>
                $(function() {

                    // Scroll to the top of the page when loading the page.
                    $('.content-wrapper')[0].scrollIntoView();

                    // As a default, when clicking the tab, we set the page to scroll to the top so it doesn't jump to the tab's content
                    // by calling $('.content-wrapper')[0].scrollIntoView() in the 'click' and 'shown.bs.tab' event handler.
                    // To prevent the page from scrolling to the top of it, we should remove those event handlers.
                    $(document).off('shown.bs.tab', 'nav a[data-bs-toggle=\"tab\"]');
                    $(document).off('click', 'nav a[data-bs-toggle=\"tab\"]');
                    
                    // We should change the hash and scroll to the tab when the tab is clicked.
                    $(document).on('click', 'nav a[data-bs-toggle=\"tab\"]', function (e) {
                        
                        // change the hash
                        let hash = $(this).data('bs-target');
                        window.location.hash = hash.replace('#', '');

                        // If we don't set the following, it jumps to the tab's content when clicking the tab.
                        $(this).parent().parent().parent()[0].scrollIntoView();

                    });

                });
            </script>
            ";
        }
    }
}

/****************************************
 * FUNCTION: DISPLAY GAP ANALYSIS TABLE *
 ****************************************/
function display_gap_analysis_table($framework, $maturity) {

	global $lang, $escaper;

	$tableID = "control-gap-analysis-" . $maturity;

	echo "
        <table id='{$tableID}' width='100%' class='risk-datatable table table-bordered table-striped table-condensed'>
            <thead >
                <tr >
                    <th data-name='control_number' align='left' width='50px' valign='top'>{$escaper->escapeHtml($lang['ControlNumber'])}</th>
                    <th data-name='control_short_name' align='left' width='200px' valign='top'>{$escaper->escapeHtml($lang['ControlShortName'])}</th>
                    <th data-name='control_phase' align='left' width='50px' valign='top'>{$escaper->escapeHtml($lang['ControlPhase'])}</th>
                    <th data-name='control_family' align='left' width='50px' valign='top'>{$escaper->escapeHtml($lang['ControlFamily'])}</th>
                    <th data-name='control_current_maturity' align='left' width='50px' valign='top'>{$escaper->escapeHtml($lang['CurrentControlMaturity'])}</th>
                    <th data-name='control_desired_maturity' align='center' width='50px' valign='top'>{$escaper->escapeHtml($lang['DesiredControlMaturity'])}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <script>
            $(function() {
                var form = $('#{$tableID}').parents('form');
                var datatableInstance_{$maturity} = $('#{$tableID}').DataTable({
                    createdRow: function(row, data, index){
                        var background = $('.background-class', $(row)).data('background');
                        $(row).find('td').addClass(background)
                    },
                    order: [[1, 'asc']],
                    ajax: {
                        url: BASE_URL + '/api/reports/governance/control_gap_analysis?framework_id={$escaper->escapeHtml($framework)}&maturity={$escaper->escapeHtml($maturity)}',
                        data: function(d){
                        },
                        complete: function(response){
                        }
                    }
                });
            });
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
                        <button class='impact-add-btn btn btn-dark'><i class='fa fa-plus'></i></button>
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

function display_datetimepicker_javascript($initialize = false) {
    // Get the SimpleRisk Base URL
    $simplerisk_base_url = get_setting('simplerisk_base_url');

    // If the last character is not a /
    if (substr($simplerisk_base_url, -1) != "/") {
        // Append a / to the SimpleRisk Base URL
        $simplerisk_base_url .= "/";
    }

    $app_version = current_version("app");
    echo "<script src='{$simplerisk_base_url}/js/jquery.datetimepicker.full.min.js?{$app_version}'></script>\n";
    echo "<link rel='stylesheet' href='{$simplerisk_base_url}/css/jquery.datetimepicker.min.css?{$app_version}'/>\n";

    if ($initialize) {
        // Initialize if needed
        echo "
            <script>
                $(document).ready(function(){
                    $('input.datetimepicker').datetimepicker({
                        lazyInit: true,
                        format: '" . get_default_datetime_format() . "',
                        step: 5
                    });
                });
            </script>
        ";
    }
}

/********************************
* FUNCTION: DISPLAY ADD PROJECT *
*********************************/
function display_add_projects($template_group_id = "") {
    
    global $lang, $escaper;

    // If customization extra is enabled
    if(customization_extra()) {

        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        if(!$template_group_id) {
            $group = get_default_template_group("project");
            $template_group_id = $group["id"];
        }

        $active_fields = get_active_fields("project", $template_group_id);
        foreach($active_fields as $field) {
            if($field['is_basic'] == 1) {
                switch($field['name']) {
                    case 'ProjectName':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['NewProjectName'])}<span class='required'>*</span> :</label>
                                <input type='text' name='new_project' value='' class='form-control' required>
                            </div>
                        ";
                        break;
                    case 'DueDate':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['DueDate'])} :</label>
                                <input type='text' name='due_date' class='form-control datepicker' value='' autocomplete='off'>
                            </div>
                        ";
                        break;
                    case 'Consultant':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['Consultant'])} :</label>
                        ";
                                create_dropdown("enabled_users", NULL, "consultant", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
                        echo "
                            </div>
                        ";
                        break;
                    case 'BusinessOwner':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['BusinessOwner'])} :</label>
                        ";
                                create_dropdown("enabled_users", NULL, "business_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
                        echo "
                            </div>
                        ";
                        break;
                    case 'DataClassification':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['DataClassification'])} :</label>
                        ";
                                create_dropdown("data_classification", NULL, "data_classification", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']));
                        echo "
                            </div>
                        ";
                        break;
                }
            }
            else {
                display_custom_field_edit($field, [], "label");
            }
        }
    } else {
        echo "
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['NewProjectName'])}<span class='required'>*</span> :</label>
                <input type='text' name='new_project' value='' class='form-control' required>
            </div>
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['DueDate'])} :</label>
                <input type='text' name='due_date' class='form-control datepicker' value='' autocomplete='off'>
            </div>
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['Consultant'])} :</label>" . 
                create_dropdown("enabled_users", NULL, "consultant", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) . "
            </div>
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['BusinessOwner'])} :</label>" . 
                create_dropdown("enabled_users", NULL, "business_owner", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) . "
            </div>
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['DataClassification'])} :</label>" . 
                create_dropdown("data_classification", NULL, "data_classification", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) . "
            </div>
        ";
    }
}
/*********************************
* FUNCTION: DISPLAY EDIT PROJECT *
*********************************/
function display_edit_projects($template_group_id = ""){
    
    global $lang, $escaper;
    
    // If customization extra is enabled
    if(customization_extra()) {

        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        if(!$template_group_id) {
            $group = get_default_template_group("project");
            $template_group_id = $group["id"];
        }

        $active_fields = get_active_fields("project", $template_group_id);
        foreach($active_fields as $field) {
            if($field['is_basic'] == 1) {
                switch($field['name']) {
                    case 'ProjectName':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['Name'])}<span class='required'>*</span> :</label>
                                <input type='text' name='name' class='form-control' required>
                            </div>
                        ";
                        break;
                    case 'DueDate':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['DueDate'])} :</label>
                                <input type='text' name='due_date' class='form-control datepicker' value='' autocomplete='off'>
                            </div>
                        ";
                        break;
                    case 'Consultant':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['Consultant'])} :</label>" . 
                                create_dropdown("enabled_users", NULL, "consultant", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) . "
                            </div>
                        ";
                        break;
                    case 'BusinessOwner':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['BusinessOwner'])} :</label>" . 
                                create_dropdown("enabled_users", NULL, "business_owner", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) . "
                            </div>
                        ";
                        break;
                    case 'DataClassification':
                        echo "
                            <div class='form-group'>
                                <label for=''>{$escaper->escapeHtml($lang['DataClassification'])} :</label>" . 
                                create_dropdown("data_classification", NULL, "data_classification", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) . "
                            </div>        
                        ";
                        break;
                }
            } else {
                display_custom_field_edit($field, [], "label");
            }
        }
    } else {
        echo "
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['Name'])}<span class='required'>*</span> :</label>
                <input type='text' name='name' class='form-control' required>
            </div>
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['DueDate'])} :</label>
                <input type='text' name='due_date' class='form-control datepicker' value='' autocomplete='off'>
            </div>
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['Consultant'])} :</label>" . 
                create_dropdown("enabled_users", NULL, "consultant", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) . "
            </div>
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['BusinessOwner'])} :</label>" . 
                create_dropdown("enabled_users", NULL, "business_owner", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) . "
            </div>
            <div class='form-group'>
                <label for=''>{$escaper->escapeHtml($lang['DataClassification'])} :</label>" . 
                create_dropdown("data_classification", NULL, "data_classification", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) . "
            </div>
        ";
    }
}
/*****************************************
* FUNCTION: DISPLAY PROJECT TABLE HEADER *
*****************************************/
function display_project_table_header($template_group_id = "") {

    global $lang, $escaper;
    $header_html = "";
    $header_width = "1301";
    
    // If customization extra is enabled
    if (customization_extra()) {

        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        if (!$template_group_id) {
            $group = get_default_template_group("project");
            $template_group_id = $group["id"];
        }

        $active_fields = get_active_fields("project", $template_group_id);
        $header_html .= "
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['Priority'])}</div>
        ";
        $custom_field_count = 0;

        foreach ($active_fields as $field) {
            if ($field['is_basic'] == 1) {
                switch ($field['name']) {
                    case 'ProjectName':
                        $header_html .= "
                <div class='col-3 p-2 border border-light'>{$escaper->escapeHtml($lang['Name'])}</div>
                        ";
                        break;
                    case 'DueDate':
                        $header_html .= "
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['DueDate'])}</div>
                        ";
                        break;
                    case 'Consultant':
                        $header_html .= "
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['Consultant'])}</div>
                        ";
                        break;
                    case 'BusinessOwner':
                        $header_html .= "
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['BusinessOwner'])}</div>
                        ";
                        break;
                    case 'DataClassification':
                        $header_html .= "
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['DataClassification'])}</div>
                        ";
                        break;
                }
            } else {

                // If customization extra is enabled
                if (customization_extra()) {

                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                    $custom_field_count++;
                    $header_html .= "
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($field['name'])}</div>
                    ";
                }
            }
        }
        $header_html .= "
                <div class='col-2 p-2 border border-light'>{$escaper->escapeHtml($lang['Risk'])}</div>
        ";
        $header_width += $custom_field_count * 150;
        $header_html = "
            <div class='d-flex bg-secondary text-light table-header' style='width:{$header_width}px'>
                {$header_html}
            </div>
        ";
    } else {
        $header_html .= "
            <div class='d-flex bg-secondary text-light table-header' style='width:{$header_width}px'>
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['Priority'])}</div>
                <div class='col-3 p-2 border border-light'>{$escaper->escapeHtml($lang['Name'])}</div>
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['DueDate'])}</div>
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['Consultant'])}</div>
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['BusinessOwner'])}</div>
                <div class='col p-2 border border-light'>{$escaper->escapeHtml($lang['DataClassification'])}</div>
                <div class='col-2 p-2 border border-light'>{$escaper->escapeHtml($lang['Risk'])}</div>
            </div>
        ";
    }
    echo $header_html;
}

/**
 * Renders the column selection widget, including the modal window, the button that opens the modal on click
 * and the javascripts required for saving the selections
 */
function render_column_selection_widget($view) {

    global $field_settings_display_groups, $escaper, $lang;
    
    // The function returns the whole settings including the 'display_settings' field that contains the list of names of selected columns
    $settings = display_settings_get_saved_selection($view);
    
    // If there're no saved settings for this view yet
    if (empty($settings)) {

        // then we load the list of default selected fields
        $settings = field_settings_get_display_defaults($view);

    } else {

        // For this we only need the list of names in the 'display_settings' field
        $settings = $settings['display_settings'];

    }
    
    $groups = [];
    foreach (field_settings_get_localization($view) as $group_name => $group) {

        $groups[$group_name] = [
            'header' => empty($field_settings_display_groups[$group_name]['header_key']) ? false : $escaper->escapeHtml($lang[$field_settings_display_groups[$group_name]['header_key']]),
            'fields' =>  $group
        ];

    }
    
    echo "
        <script>

            // This is the list of selected columns for the view
            var custom_display_settings = JSON.parse('" . json_encode($settings) . "');

            $(function() {
                $('form#custom_display_settings-{$view}').submit(function() {
                    event.preventDefault();
                    var form = new FormData($(this)[0]);
                    $.blockUI({message:\"<i class=\'fa fa-spinner fa-spin\' style=\'font-size:24px\'></i>\"});
                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/v2/admin/column_settings/save_column_settings',
                        data: form,
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            $('#setting_modal-{$view}').modal('hide');
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }
                            document.location.reload(true);
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        },
                        complete: function() {
                            $.unblockUI();
                        }
                    });
                });
            });
        </script>

        <a class='btn btn-primary float-end' title='{$escaper->escapeHtml($lang['Settings'])}' data-sr-role='dt-settings' data-sr-target='{$view}_datatable' data-bs-toggle='modal' data-bs-target='#setting_modal-{$view}'><i class='fa fa-cog'></i></a>
        <div id='setting_modal-{$view}' class='modal fade hide' tabindex='-1' role='dialog' aria-labelledby='setting_modal-{$view}' aria-hidden='true'>
            <div class='modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h4 class='modal-title'>{$escaper->escapeHtml($lang['ColumnSelections'])}</h4>
                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                    </div>
                    <div class='modal-body column-selections-container'>
                        <form id='custom_display_settings-{$view}' name='custom_display_settings-{$view}' method='post'>
                            <input type='hidden' name='display_settings_view' value='{$view}'>
                            <div class='accordion'>
    ";

    foreach ($groups as $group_name => $group) {

        // If the group has a header setup, then render it
        if ($group['header']) {

            echo "
                                <div class='accordion-item'>
                                    <h2 class='accordion-header'>
                                        <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#{$group['header']}_container'>{$group['header']}</button>
                                    </h2>
                                    <div id='{$group['header']}_container' class='accordion-collapse collapse'>
                                        <div class='accordion-body card-body'>
            ";

        }

        echo "
                                <div class='row'>
                                    <div class='col-6'>
                                        <div class='p-3 h-100 border'>
        ";

        // Within a section the options are split into two columns.
        $counter = 1;
        $halfpoint = count($group['fields']) / 2;

        foreach ($group['fields'] as $field_name => $text) { 

            echo "
                                            <div class='mb-1'>
                                                <input class='form-check-input' type='checkbox' name='{$field_name}' id='checkbox_{$field_name}-{$view}-{$group_name}' " . (in_array($field_name, $settings) ? "checked" : "") . "/>
                                                <label class='form-check-label mb-0 ms-2' for='checkbox_{$field_name}-{$view}-{$group_name}'>{$text}</label>
                                            </div>
            ";
                                
            // Add the closing of the left column and the start of the right column
            if ($counter !== false) {

                if ($counter >= $halfpoint) {

                    echo "
                                        </div>
                                    </div>
                                    <div class='col-6'>
                                        <div class='p-3 h-100 border'>
                    ";

                    // disable the counting, we're over the halfway point
                    $counter = false;

                } else {
                    $counter += 1;
                }
            }
        }

        echo "
                                        </div>
                                    </div>
                                </div>
        ";

        // Only have to add these if the section had a header
        if ($group['header']) {

            echo "
                                        </div>
                                    </div>
                                </div>
            ";

        }
    }

    echo "
                            </div>
                        </form>
                    </div>
                    <div class='modal-footer'>
                        <button class='btn btn-secondary' data-bs-dismiss='modal'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                        <button type='submit' form='custom_display_settings-{$view}' class='btn btn-submit'>{$escaper->escapeHtml($lang['Save'])}</button>
                    </div>
                </div>
            </div>
        </div> <!-- modal -->
    ";

}



function render_field_edit_popup_modal($view) {
    global $field_settings_views, $field_settings_display_groups, $field_settings, $escaper, $lang;

    $view_type = $field_settings_views[$view]['view_type'];
    $view_edit_ajax_uri = $field_settings_views[$view]['edit']['edit_ajax_uri'];
    //$id_field_settings = !empty($field_settings_views[$view]['id_field']) ?  $field_settings[$view_type][$field_settings_views[$view]['id_field']] : false;
    $groups = [];
    $has_header = false;
    foreach (field_settings_get_localization($view) as $group_name => $group) {
        $groups[$group_name] = [
            'header' => empty($field_settings_display_groups[$group_name]['header_key']) ? false : $escaper->escapeHtml($lang[$field_settings_display_groups[$group_name]['header_key']]),
            'fields' =>  $group
        ];
        
        $has_header |= !empty($groups[$group_name]['header']);
    }
    
    echo "
        <script>
            $(function() {
    ";
    if ($has_header) {
        echo "
                // .off() is there to make sure there's no multiple click handlers on it in case multiple of this widget is rendered on the same page
                $('body').off('click', '#edit_popup_modal-{$view} .collapsible--toggle span.collapse-title').on('click', '#edit_popup_modal-{$view} .collapsible--toggle span.collapse-title', function(event) {
                    event.preventDefault();
                    $(this).closest('.collapsible--toggle').next('.collapsible').slideToggle('400');
                    $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                });
        ";
                    
    }
    
    echo "
                $('form#edit_popup-{$view}').submit(function() {
                    event.preventDefault();

                    // Create a JSON object based on the control mapping row
                    $(this).find(\"table.mapping_control_table input[name='mapped_controls[]']\").each(function() {
                    	let _this = $(this), tr = _this.closest('tr');

                    	_this.val(JSON.stringify({
                    		control_maturity: tr.find(\"select[name='control_maturity[]']\").val(),
                    		control_id: tr.find(\"select[name='control_id[]']\").val()
                    	}));
                    });

                    var form = new FormData($(this)[0]);
                    $.blockUI({message:\"<i class=\'fa fa-spinner fa-spin\' style=\'font-size:24px\'></i>\"});
                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '{$view_edit_ajax_uri}',
                        data: form,
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }

                            $('#edit_popup_modal-{$view}').modal('hide');
                            // document.location.reload(true);
                            datatableInstances['{$view}'].draw();
                            
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        },
                        complete: function() {
                            $.unblockUI();
                        }
                    });
                });
            });
        </script>
        <div id='edit_popup_modal-{$view}' class='modal fade' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h4 class='modal-title'>
    ";
    if ($view == 'asset_verified') {
        echo "
                            {$escaper->escapeHtml($lang['EditAsset'])}
        ";
    } else {
        echo "
                            {$escaper->escapeHtml($lang['Edit'])}
        ";
    }
    echo "
                        </h4>
                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                    </div>
                    <div class='modal-body edit-popup-container'>
                        <form id='edit_popup-{$view}' name='edit_popup-{$view}' method='post'>
                            <input type='hidden' name='edit_view' value='{$view}'>";

    // If there's an id field setup add a hidden field for it
    if (!empty($field_settings_views[$view]['id_field'])) {
        echo "
                            <input type='hidden' name='{$field_settings_views[$view]['id_field']}' class='edit_input'/>
        ";
    }

    if ($customization = customization_extra()) {
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields($view_type);
        $mapped_custom_field_settings = [];
        foreach ($active_fields as $active_field) {

            // Skip this step for basic fields
            if ($active_field['is_basic']) {
                continue;
            }

            $field_name = "custom_field_{$active_field['id']}";

            switch($active_field['type']) {
                case "shorttext":
                case "hyperlink":
                    $type = 'short_text';
                    break;
                case "longtext":
                    $type = 'long_text';
                    break;
                case "date":
                    $type = 'date';
                    break;
                case "dropdown":
                    $type = "select[{$field_name}]";
                    break;
                case "multidropdown":
                    $type = "multiselect[{$field_name}]";
                    break;
                case "user_multidropdown":
                    $type = "multiselect[user]";
                    break;
            }

            $mapped_custom_field_settings[$field_name] = [
                'type' => $type,
                'required' => $active_field['required'],
                'alphabetical_order' => $active_field['alphabetical_order'],
                'editable' => true,
            ];
        }
    }
    
    foreach ($groups as $group_name => $group) {
        // If the group has a header setup, then render it
        if ($group['header']) {
            echo "
                            <h4 class='collapsible--toggle clearfix'>
                                <span class='collapse-title'><i class='fa fa-caret-down'></i>{$group['header']}</span>
                            </h4>
                            <div class='collapsible'>
            ";
        }
        echo "
                            <div class='row'>
                                <div class='col-12'>
        ";
        
        foreach ($group['fields'] as $field_name => $text) {
            // If it's not in the field settings then it's a custom field
            $field_setting = $field_settings[$view_type][$field_name] ?? $mapped_custom_field_settings[$field_name];
            $required = $field_setting['required'];
            // Currently not displaying uneditable fields, we could display but make them disabled
            if (!$field_setting['editable']) {
                continue;
            }


            $required_text = $required ? "<span class='required'>*</span>" : '';
            $required_attribute = $required ? " required " : '';

            [$field_type, $field_sub_type] = array_pad(preg_split('/(\[|\])/', $field_setting['type'], 0, PREG_SPLIT_NO_EMPTY), 2, false);
            
            $custom_html_structure = ['long_text', 'mapped_controls'];
            
            if (!in_array($field_type, $custom_html_structure)) {
                echo "
                                    <div class='form-group row'>
                                        <label class='col-3' for='edit_{$field_name}-{$view}-{$group_name}'>{$text}{$required_text}</label>
                                        <div class='col-9'>
                ";
            }

            switch($field_type) {
                case 'short_text':
                    echo "
                                            <input type='text' name='{$field_name}' id='edit_{$field_name}-{$view}-{$group_name}' autocomplete='off' class='form-control edit_input'{$required_attribute}/>";
                    break;
                case 'long_text':
                    echo "
                                    <div class='form-group row'>
                                        <label class='col-12' for='edit_{$field_name}-{$view}-{$group_name}'>{$text}{$required_text}</label>
                                        <div class='col-12'>
                                            <textarea name='{$field_name}' id='edit_{$field_name}-{$view}-{$group_name}' style='width: 100%;' class='form-control edit_input'{$required_attribute}></textarea>";
                    break;
                case 'select':
                    create_dropdown($field_sub_type, null, $field_name, !$field_setting['required'], false, false, " class='form-select edit_input' {$required_attribute}", '--', '', true, $field_setting['alphabetical_order'] ?? 0);
                    break;
                case 'multiselect':
                    create_multiple_dropdown($field_sub_type, null, $field_name, null, false, "--", "", true, " {$required_attribute}", $field_setting['alphabetical_order'] ?? 0, additionalClasses: 'edit_input');
                    echo "
                                            <script>
                                                $(function() {
                                                    $('#edit_popup-{$view} #{$field_name}.multiselect').multiselect({buttonWidth: '100%', enableFiltering: true, enableCaseInsensitiveFiltering: true,});
                                                });
                                            </script>
                    ";
                    break;
                case 'datetime':
                case 'date':
                    echo "
                                            <input type='text' name='{$field_name}' id='edit_{$field_name}-{$view}-{$group_name}' style='cursor: default;' class='form-control {$field_type}picker edit_input' autocomplete='off'{$required_attribute}/>";
                    break;
                case 'tags':
                    $tag_input_id = "edit_{$field_name}_{$view}_{$group_name}";
                    echo "
                                            <select class='edit_input' readonly id='{$tag_input_id}' name='{$field_name}[]' multiple placeholder='Select/Add tag'{$required_attribute}></select>
                                            <script>
                                                $(function() {
                                                    var tags_{$tag_input_id}_selectize = $('#{$tag_input_id}').selectize({
                                                        plugins: ['remove_button', 'restore_on_backspace'],
                                                        delimiter: '|',
                                                        create: true,
                                                        valueField: 'label',
                                                        labelField: 'label',
                                                        searchField: 'label',
                                                        sortField: [{ field: 'label', direction: 'asc' }],
                                                        onChange: function() { $('#{$tag_input_id}').data('changed', true);},
                                                    });
                                                    $.ajax({
                                                        url: BASE_URL + '/api/management/tag_options_of_type?type={$view_type}',
                                                        type: 'GET',
                                                        dataType: 'json',
                                                        error: function(xhr,status,error){
                                                            if(!retryCSRF(xhr, this)){
                                                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                                                }
                                                            }
                                                        },
                                                        success: function(res) {
                                                            tags_{$tag_input_id}_selectize[0].selectize.addOption(res.data);
                                                            tags_{$tag_input_id}_selectize[0].selectize.refreshOptions(true);
                                                        }
                                                    });
                                                });
                                            </script>";
                    break;
                case 'mapped_controls':
                    echo "
                                    <div class='form-group row'>
                                        <div class='col-12'>
                                            <div class='row p-2'>
                                                <div class='bg-light border'>
                                                    <div class='row'>
                                                        <label class='col-10 col-form-label' for='edit_{$field_name}-{$view}-{$group_name}'>{$text}{$required_text}</label>
                                                        <div class='col-2 text-end col-form-label'>
                                                            <button type='button' name='add_control' class='btn btn-primary btn-sm add-control'>{$escaper->escapeHtml($lang['AddControl'])}</button>
                                                        </div>
                                                    </div>
                                                    <div class='row'>
                                                        <div class='col-12'>
                                                            <table width='100%' class='table table-bordered mapping_control_table' name='{$field_name}' style='table-layout: fixed;'>
                                                                <thead>
                                                                    <tr>
                                                                        <th width='23%'>{$escaper->escapeHtml($lang['CurrentMaturity'])}</th>
                                                                        <th>{$escaper->escapeHtml($lang['Control'])}</th>
                                                                        <th width='12%'>{$escaper->escapeHtml($lang['Actions'])}</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

";
                    break;
            }
            echo "
                                        </div>
                                    </div>
            "; 
        }

        echo "
                                </div>
                            </div>";
            // Only have to add these if the section had a header
        if ($group['header']) {
            echo "
                            </div>
            ";
        }
    }

    echo "
                        </form>
                    </div>
                    <div class='modal-footer'>
                        <button class='btn btn-secondary' data-bs-dismiss='modal'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                        <button type='submit' form='edit_popup-{$view}' class='btn btn-submit'>{$escaper->escapeHtml($lang['Update'])}</button>
                    </div>
                </div>
            </div>
        </div>
    ";
}


function render_create_modal($view) {

    global $field_settings_views, $field_settings_display_groups, $field_settings, $escaper, $lang;
    
    $view_type = $field_settings_views[$view]['view_type'];
    $view_create_ajax_uri = $field_settings_views[$view]['create']['create_ajax_uri'];
    //$id_field_settings = !empty($field_settings_views[$view]['id_field']) ?  $field_settings[$view_type][$field_settings_views[$view]['id_field']] : false;
    $groups = [];
    $has_header = false;

    foreach (field_settings_get_localization($view) as $group_name => $group) {
        $groups[$group_name] = [
            'header' => empty($field_settings_display_groups[$group_name]['header_key']) ? false : $escaper->escapeHtml($lang[$field_settings_display_groups[$group_name]['header_key']]),
            'fields' =>  $group
        ];
        
        $has_header |= !empty($groups[$group_name]['header']);
    }
    
    echo "
        <script>
            $(function() {
    ";

    if ($has_header) {

        echo "
                // .off() is there to make sure there's no multiple click handlers on it in case multiple of this widget is rendered on the same page
                $('body').off('click', '#create_popup_modal-{$view} .collapsible--toggle span.collapse-title').on('click', '#create_popup_modal-{$view} .collapsible--toggle span.collapse-title', function(event) {
                    event.preventDefault();
                    $(this).closest('.collapsible--toggle').next('.collapsible').slideToggle('400');
                    $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                });
        ";
    
    }
    
    echo "
                $('form#create_popup-{$view}').submit(function() {
                    event.preventDefault();

                    // Create a JSON object based on the control mapping row
                    $(this).find(\"table.mapping_control_table input[name='mapped_controls[]']\").each(function() {
                    	let _this = $(this), tr = _this.closest('tr');

                    	_this.val(JSON.stringify({
                    		control_maturity: tr.find(\"select[name='control_maturity[]']\").val(),
                    		control_id: tr.find(\"select[name='control_id[]']\").val()
                    	}));
                    });

                    var form = new FormData($(this)[0]);
                    $.blockUI({message:\"<i class=\'fa fa-spinner fa-spin\' style=\'font-size:24px\'></i>\"});
                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '{$view_create_ajax_uri}',
                        data: form,
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){

                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }

                            $('#create_popup_modal-{$view}').modal('hide');
                            // document.location.reload(true);
                            datatableInstances['{$view}'].draw();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        },
                        complete: function() {
                            $.unblockUI();
                        }
                    });
                });
            });
        </script>
        <div id='create_popup_modal-{$view}' class='modal fade' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h4 class='modal-title'>
    ";
    if ($view == 'asset_verified') {
        echo "
                            {$escaper->escapeHtml($lang['NewAsset'])}
        ";
    } else {
        echo "
                            {$escaper->escapeHtml($lang['Create'])}
        ";
    }
    echo "
                        </h4>
                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                    </div>
                    <div class='modal-body'>
                        <form id='create_popup-{$view}' name='create_popup-{$view}' method='post'>
                            <input type='hidden' name='create_view' value='{$view}'>
    ";
    
    // If there's an id field setup add a hidden field for it
    if (!empty($field_settings_views[$view]['id_field'])) {

        echo "
                            <input type='hidden' name='{$field_settings_views[$view]['id_field']}' class='create_input'/>
        ";

    }
    
    echo "
                            <div class='well create-popup-container'>
    ";
    
    if ($customization = customization_extra()) {

        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $active_fields = get_active_fields($view_type);
        $mapped_custom_field_settings = [];
        
        foreach ($active_fields as $active_field) {
            
            // Skip this step for basic fields
            if ($active_field['is_basic']) {
                continue;
            }
            
            $field_name = "custom_field_{$active_field['id']}";
            
            switch($active_field['type']) {
                case "shorttext":
                case "hyperlink":
                    $type = 'short_text';
                    break;
                case "longtext":
                    $type = 'long_text';
                    break;
                case "date":
                    $type = 'date';
                    break;
                case "dropdown":
                    $type = "select[{$field_name}]";
                    break;
                case "multidropdown":
                    $type = "multiselect[{$field_name}]";
                    break;
                case "user_multidropdown":
                    $type = "multiselect[user]";
                    break;
            }
            
            $mapped_custom_field_settings[$field_name] = [
                'type' => $type,
                'required' => $active_field['required'],
                'alphabetical_order' => $active_field['alphabetical_order'],
            ];
        }
    }
    
    foreach ($groups as $group_name => $group) {

        // If the group has a header setup, then render it
        if ($group['header']) {
            echo "
                            <div class='row'>
                                <h4 class='collapsible--toggle clearfix'>
                                    <span class='collapse-title'><i class='fa fa-caret-down'></i>{$group['header']}</span>
                                </h4>
                                <div class='collapsible'>
            ";
        }

        echo "
                            <div class='row'>
                                <div class='col-12'>
        ";
        
        foreach ($group['fields'] as $field_name => $text) {

            // If it's not in the field settings then it's a custom field
            $field_setting = $field_settings[$view_type][$field_name] ?? $mapped_custom_field_settings[$field_name];
            $required = $field_setting['required'];
            
            $required_text = $required ? "<span class='required'>*</span>" : '';
            $required_attribute = $required ? " required " : '';

            [$field_type, $field_sub_type] = array_pad(preg_split('/(\[|\])/', $field_setting['type'], 0, PREG_SPLIT_NO_EMPTY), 2, false);

            $custom_html_structure = ['long_text', 'mapped_controls'];

            if (!in_array($field_type, $custom_html_structure)) {
                echo "
                                    <div class='form-group row'>
                                        <label class='col-3' for='create_{$field_name}-{$view}-{$group_name}'>{$text}{$required_text}</label>
                                        <div class='col-9'>
                ";
            }

            switch ($field_type) {
                case 'short_text':
                    echo "
                                            <input type='text' name='{$field_name}' id='create_{$field_name}-{$view}-{$group_name}' autocomplete='off' class='form-control create_input'{$required_attribute}/>
                    ";
                    break;
                case 'long_text':
                    echo "
                                    <div class='form-group row'>
                                        <label class='col-12' for='create_{$field_name}-{$view}-{$group_name}'>{$text}{$required_text}</label>
                                        <div class='col-12'>
                                            <textarea name='{$field_name}' id='create_{$field_name}-{$view}-{$group_name}' style='width: 100%;' class='form-control create_input'{$required_attribute}></textarea>
                    ";
                    break;
                case 'select':
                    create_dropdown($field_sub_type, null, $field_name, !$field_setting['required'], false, false, " class='form-select create_input' {$required_attribute}", '--', '', true, $field_setting['alphabetical_order'] ?? 0);
                    break;
                case 'multiselect':
                    create_multiple_dropdown($field_sub_type, null, $field_name, null, false, "--", "", true, " class='form-select multiselect create_input' {$required_attribute}", $field_setting['alphabetical_order'] ?? 0);
                    echo "
                                            <script>
                                                $(function() {
                                                    $('#create_popup-{$view} #{$field_name}.multiselect').multiselect({buttonWidth: '100%', enableFiltering: true, enableCaseInsensitiveFiltering: true,});
                                                });
                                            </script>
                    ";
                    break;
                case 'datetime':
                case 'date':
                    echo "
                                            <input type='text' name='{$field_name}' id='create_{$field_name}-{$view}-{$group_name}' style='cursor: default;' class='form-control {$field_type}picker create_input' autocomplete='off'{$required_attribute}/>";
                    break;
                case 'tags':
                    $tag_input_id = "create_{$field_name}_{$view}_{$group_name}";
                    echo "
                                            <select class='create_input' readonly id='{$tag_input_id}' name='{$field_name}[]' multiple placeholder='Select/Add tag'{$required_attribute}></select>
                                            <script>
                                                $(function() {
                                                    var tags_{$tag_input_id}_selectize = $('#{$tag_input_id}').selectize({
                                                        plugins: ['remove_button', 'restore_on_backspace'],
                                                        delimiter: '|',
                                                        create: true,
                                                        valueField: 'label',
                                                        labelField: 'label',
                                                        searchField: 'label',
                                                        sortField: [{ field: 'label', direction: 'asc' }],
                                                        onChange: function() { $('#{$tag_input_id}').data('changed', true);},
                                                    });
                                                    $.ajax({
                                                        url: BASE_URL + '/api/management/tag_options_of_type?type={$view_type}',
                                                        type: 'GET',
                                                        dataType: 'json',
                                                        error: function(xhr,status,error){
                                                            if(!retryCSRF(xhr, this)){
                                                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                                                }
                                                            }
                                                        },
                                                        success: function(res) {
                                                            tags_{$tag_input_id}_selectize[0].selectize.addOption(res.data);
                                                            tags_{$tag_input_id}_selectize[0].selectize.refreshOptions(true);
                                                        }
                                                    });
                                                });
                                            </script>
                    ";
                    break;
                case 'mapped_controls':
                    echo "
                                    <div class='form-group row'>
                                        <div class='col-12'>
                                            <div class='row p-2'>
                                                <div class='bg-light border'>
                                                    <div class='row'>
                                                        <label class='col-10 col-form-label' for='create_{$field_name}-{$view}-{$group_name}'>{$text}{$required_text}</label>
                                                        <div class='col-2 text-end col-form-label'>
                                                            <button type='button' name='add_control' class='btn btn-primary btn-sm add-control'>{$escaper->escapeHtml($lang['AddControl'])}</button>
                                                        </div>
                                                    </div>
                                                    <div class='row'>
                                                        <div class='col-12'>
                                                            <table width='100%' class='table table-bordered mapping_control_table' name='{$field_name}' style='table-layout: fixed;'>
                                                                <thead>
                                                                    <tr>
                                                                        <th width='23%'>{$escaper->escapeHtml($lang['CurrentMaturity'])}</th>
                                                                        <th>{$escaper->escapeHtml($lang['Control'])}</th>
                                                                        <th width='12%'>{$escaper->escapeHtml($lang['Actions'])}</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                    ";
                    break;
            }
            
            
            echo "
                                        </div>
                                    </div>
            ";
        }
        
        echo "
                                </div>
                            </div>
        ";
        // Only have to add these if the section had a header
        if ($group['header']) {
            echo "
                                </div>
                            </div>
            ";
        }
    }
    
    echo "
                        </div>
                    </form>
                </div>
                <div class='modal-footer'>
                    <button class='btn btn-secondary' data-bs-dismiss='modal'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                    <button type='submit' form='create_popup-{$view}' class='btn btn-submit'>{$escaper->escapeHtml($lang['Save'])}</button>
                </div>
            </div>
        </div>
    </div>
    ";
}


/**
 * Renders the datatable for the view based on the settings in '$field_settings_views' global variable
 * 
 * -Wires in the API endpoint setup in the settings as the datasource
 * -Adds the filter bar and logic for the filtering
 * -Adds editing functionality(inline or popup)
 * 
 * @param string $view
 */
function render_view_table($view) {
    global $lang, $escaper, $field_settings, $field_settings_views;

    if ($customization = customization_extra()) {
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
    }

    $view_type = $field_settings_views[$view]['view_type'];
    $datatable_data_type_associative = $field_settings_views[$view]['datatable_data_type'] === 'associative';
    $datatable_filter_submit_delay = $field_settings_views[$view]['datatable_filter_submit_delay'] ?? 400;
    $view_editable = !empty($field_settings_views[$view]['edit']);
    $view_edit_type = $view_editable ? $field_settings_views[$view]['edit']['type'] : false;
    $view_edit_type_inline = $view_edit_type && $view_edit_type === 'inline';
    $view_edit_type_popup = $view_edit_type && $view_edit_type === 'popup';
    $view_edit_ajax_uri = $view_editable ? $field_settings_views[$view]['edit']['edit_ajax_uri'] : false;
    
    $settings = display_settings_get_display_settings_for_view($view);
    $localizations = field_settings_get_localization($view, false);

    $actions_column_info = !empty($field_settings_views[$view]['actions_column']) ? $field_settings_views[$view]['actions_column'] : false;
    $actions_column_first = $actions_column_info && $actions_column_info['position'] === 'first';

    $order_index = false;
    $order_dir = "asc";

    $header_row = "";
    $filter_header_row = "";

    if ($actions_column_info) {
        if ($actions_column_first) {
            $settings = array_merge([$actions_column_info['field_name']], $settings);
        } else {
            $settings []= $actions_column_info['field_name'];
        }

        $localizations[$actions_column_info['field_name']] = $escaper->escapeHtml($field_settings[$view_type][$actions_column_info['field_name']]['localization_key']);
    }
    
    // Iterate through the list of selected fields
    foreach ($settings as $field_idx => $field_name) {

        if ($order_index === false && !empty($field_settings[$view_type][$field_name]['orderable']) && $field_settings[$view_type][$field_name]['orderable']) {
            $order_index = $field_idx;
        }

        // If there's a custom column style defined for the field then use that instead of the default
        if (!empty($field_settings[$view_type][$field_name]['custom_column_style'])) {
            $style = $field_settings[$view_type][$field_name]['custom_column_style'];
        } else {
            $style = "min-width:100px;";
        }

        $header_row .= "<th data-name='{$field_name}' align='left' style='{$style}'>{$localizations[$field_name]}</th>";

        // non render-related logic
        switch($field_name) {
            case 'calculated_risk':
                $order_index = $field_idx;
                $order_dir = "desc";
                break;
        }

        // Only render the search field if in the configuration it's set as searchable or it's a custom field which as of now always searchable
        if ((!empty($field_settings[$view_type][$field_name]['searchable']) && $field_settings[$view_type][$field_name]['searchable']) || str_starts_with($field_name, 'custom_field_')) {

            // render-related logic for views
            $filter_field = "";
            
            $filter_field = get_filter_field_for_views($view, $field_name, $localizations);

            $filter_header_row .= "
                <th data-column-number='{$field_idx}' data-name='{$field_name}' align='left' style='{$style}'>{$filter_field}</th>
            ";
        } else {
            // add an empty header cell if the field isn't searchable
            $filter_header_row .= "<th></th>";
        }
    }

    $datatable_id = "{$view}_datatable";
    echo "
        <table id='{$datatable_id}' width='100%' class='datatable table table-bordered table-striped " . ($view_editable ? " editable-{$view_edit_type}" : '') . "' data-view='{$view}'>
            <thead >
                <tr class='header'>{$header_row}</tr>
                <tr class='header_filter'>{$filter_header_row}</tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    ";
    
    if ($view_edit_type_popup) {
        render_field_edit_popup_modal($view);
    }

    echo "
        <script>
            // Storing the datatable instances in a dictionary so it can be reached by other parts of the code by the name of the view
            // Only initialize if it doesn't exist yet
            if (typeof datatableInstances === 'undefined') {
                datatableInstances = [];
            }

            $(function() {
                // Save the datatable instance into the dictionary
                datatableInstances['{$view}'] = $('#{$datatable_id}').DataTable({
                    bSort: true,
                    orderCellsTop: true,
                    scrollX: true,
    " .
    ($order_index !== false ? "
                    order: [[{$order_index}, '{$order_dir}']],
    " : "
                    ordering: false,
    ") .
    ((!empty($field_settings_views[$view]['datatable_options']) && $field_settings_views[$view]['datatable_options']) ? 
                    $field_settings_views[$view]['datatable_options'] : "") . "
                    ajax: {
                        url: BASE_URL + '{$field_settings_views[$view]['datatable_ajax_uri']}',
                        type: 'post',
                        data: function(d){ },
                        complete: function(response){ },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        },
                    },
                    columns: [
    ";
    
    $actions_column_info = !empty($field_settings_views[$view]['actions_column']) ? $field_settings_views[$view]['actions_column'] : false;

    // If the array isn't associative then if the foreach is constructed this way then the $field_idx will have the index of the array element
    foreach ($settings as $field_idx => $field_name) {
        
        // if it's not defined in the settings it's probably a custom field
        if ($customization && empty($field_settings[$view_type][$field_name]) && preg_match('/custom_field_([\d]+)/', $field_name, $matches) === 1) {
            $custom_field = get_field_by_id($matches[1]);
            $searchable = 'true';
            $orderable = !in_array($custom_field['type'], ['multidropdown', 'user_multidropdown']) ? 'true' : 'false';
            // by default custom fields are editable, but only need this when the edit type is inline
            $editable_cell = $view_edit_type_inline;
            $has_display_field = in_array($custom_field['type'], ['dropdown', 'multidropdown', 'user_multidropdown']);
        } else {
            $searchable = $field_settings[$view_type][$field_name]['searchable'] ? 'true' : 'false';
            $orderable = $field_settings[$view_type][$field_name]['orderable'] ? 'true' : 'false';
            $editable_cell = $view_edit_type_inline && $field_settings[$view_type][$field_name]['editable'];
            $has_display_field = $field_settings[$view_type][$field_name]['has_display_field'];
        }

        // Get the renderer if there's any defined for the field
        $renderer = !empty($field_settings[$view_type][$field_name]['renderer']) ? $field_settings[$view_type][$field_name]['renderer'] : false;
        $display_post_fix = $has_display_field ? '_display' : '';
        
        
        if ($actions_column_info && $actions_column_info['field_name'] == $field_name) {
            $class_name = 'cell-action';
        } elseif($editable_cell) {
            $class_name = 'cell-editable';
        } else {
            $class_name = false;
        }
        
        // If the data coming from the server side as an associative array we need to add the configuration a bit differently
        if ($datatable_data_type_associative) {
            echo "
                        {
                            'data': '{$field_name}{$display_post_fix}', 
                            'searchable': {$searchable}, 
                            'orderable': {$orderable}" . 
                            ($class_name ? ", 
                            'className': '{$class_name}'" : "") . 
                            ($renderer ? ", 
                            'render': {$renderer}" : "") . ", 
                            'defaultContent': ''
                        },
            ";
        } else {
            echo "
                        {
                            'target': '{$field_idx}', 
                            'searchable': {$searchable}, 
                            'orderable': {$orderable}" . 
                            ($class_name ? ", 
                            'className': '{$class_name}'" : "") . 
                            ($renderer ? ", 
                            'render': {$renderer}" : "") . ", 
                            'defaultContent': ''
                        },
            ";
        }
        
    }
    echo "
                    ],
                });
    ";
    
    // Only need this section when inline edit is enabled for the view
    if ($view_edit_type_inline) {
        echo "
            // Only define the function if it doesn't exist yet
            if (typeof getColumnsToTriggerRedrawWhenChanged !== 'function') {

                var columnsToTriggerRedrawWhenChanged = [];
                // The function is to gather the column names that when their field changed have to trigger a re-draw as the changes could affect the sorting/filtering
                // defining it as a global function 
                window.getColumnsToTriggerRedrawWhenChanged = function(instance) {
                    var settings = instance.settings();
                    var columns = settings.init().columns;
                    var fields = [];

                    // Get the fields the datatable is sorted by
                    for (const order of settings.order()) {
                        fields.push(columns[order[0]].data);
                    }

                    // Get the fields filtered on
                    var len = instance.columns().count();
                    for (i = 0; i < len; i++) {
                        // Add to the list if the search field isn't empty and the field isn't added yet
                        if (instance.column(i).search() && fields.indexOf(columns[i].data) === -1) {
                            fields.push(columns[i].data);    
                        }
                    }

                    return fields;
                }
            }

            // Only define the function if it doesn't exist yet
            if (typeof make_resizable !== 'function') {
                function make_resizable(element, view) {
                    var factor = 7.6;
                    function resize() {
                        element.width((element.val().length + 1) * factor);
                        datatableInstances[view].columns.adjust();
                    }

                    for (const event of ['keyup', 'keypress', 'focus', 'blur', 'change']) {
                        element.on(event, resize);
                    }
                    resize();
                }
            }
        ";
    }
    
    echo "
                // Add paginate options
                datatableInstances['{$view}'].on('draw', function(e, settings) {
                    " . ($view_edit_type_inline ? "columnsToTriggerRedrawWhenChanged = getColumnsToTriggerRedrawWhenChanged(datatableInstances['{$view}']);" : "") . "
                });

                var filter_submit_timer;
                // we should attach this event handler to the only elements in the filter part of the table header not a dropdown menu.
                $('body').on('change input', '#{$datatable_id}_wrapper tr.header_filter input:not(.multiselect-container.dropdown-menu *), #{$datatable_id}_wrapper tr.header_filter select:not(.multiselect-container.dropdown-menu *)', function () {
                    clearTimeout(filter_submit_timer);

                    // Retrieve all selected values for multi-select
                    var _val;
                    if ($(this).is('select[multiple]')) {
                        // Get all selected options
                        _val = $(this).val();
                    } else {
                        // For single inputs or non-multi-selects
                        _val = this.value;
                    }
                    var column_number = $(this).closest('th').attr('data-column-number');
                    if (datatableInstances['{$view}'].column(column_number).search() !== _val) {
                        filter_submit_timer = setTimeout(function() {
                          datatableInstances['{$view}'].column(column_number).search(_val).draw();
                        }, {$datatable_filter_submit_delay});
                    }
                });
    ";

    if ($view_edit_type_inline) {
        echo "
                // When clicking on a cell that's not edited the text should be replaced with the component used to edit it
                $('body').on('click', '#{$datatable_id} td.cell-editable', function() {

                    var _this = $(this);
                    // If the cell is already edited then there's nothing left to do
                    if (_this.hasClass('editing')) {
                        return;
                    }

                    // Mark the cell as being edited
                    _this.addClass('editing');

                    var instance = datatableInstances['{$view}'];

                    $(this).html(instance.cell(this).render('edit'));
                    instance.columns.adjust();
                    _this.find('.edited-field').focus();

                    // If it's a textarea save its original width to be able to detect if it changed
                    var textarea = $('textarea.edited-field', _this);
                    if (textarea !== undefined && textarea.length) {
                        textarea.data('old_width', textarea[0].clientWidth);
                    }

                    // If it's an input field, make it resizable
                    var input = $('input[type=text].edited-field', _this);
                    if (input !== undefined && input.length) {
                        make_resizable(input, '{$view}');
                    }
                });

                // Save the changes when the component loses focus
                $('body').on('blur', '#{$datatable_id} td.cell-editable.editing', function() {
                    var _this = $(this);
                    _this.removeClass('editing');
    
                    var instance = datatableInstances['{$view}'];
                    var rowData = instance.row(this).data();                
                    var columns = instance.settings().init().columns;
                    var colIndex = instance.cell(this).index().column;
                    var editedField = _this.find('.edited-field');
                    var fieldDataChanged = editedField.data('changed') === true;

                    // Only if the data changed
                    if (fieldDataChanged) {
    
                        var id = rowData['id'];    
                        var fieldName = columns[colIndex].data;
                        var fieldValue = editedField.val();

                        // Check if the field's name is in the list of fields that should trigger a redraw when changed
                        var needsRedraw = columnsToTriggerRedrawWhenChanged.indexOf(fieldName) !== -1;
    
                        // Hit the edit API and save the field's new value
                        $.ajax({
                            type: 'POST',
                            url: BASE_URL + '{$view_edit_ajax_uri}',
                            data : {
                                id: id,
                                fieldName: fieldName,
                                fieldValue: fieldValue,
                                view: '{$view}',
                            },
                            context: this,
                            success: function(data){
                                if(data.status_message){
                                    showAlertsFromArray(data.status_message);
                                }
                                // Have to re-populate the cell with the data coming from the server, not trusting data on the client side
                                if (data.data) {
                                    var cell = instance.cell(this);
                                    // Set the data to the cell and let it be rendered too
                                    cell.data(data.data);
                                    // Adjust the columns
                                    instance.columns.adjust();
                                    // Redraw the whole table if a column that's used for sorting or filtering is edited
                                    if (needsRedraw) {
                                        instance.draw();
                                    }
                                }
                            },
                            error: function(xhr,status,error){
                                if(!retryCSRF(xhr, this)) {
                                	showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                                _this.html(instance.cell(this).render('display'));
                            }
                        });
                    } else {
                        _this.html(instance.cell(this).render('display'));
                        instance.columns.adjust();
                    }
                });

                // Detect width resize of textareas in the table when inline editing to adjust the width of the headers
                $('body').on('mouseup', '#{$datatable_id} td.cell-editable textarea.edited-field', function() {
                    // On mouseup(supposedly after dragging the resize widget on the lower right) we check if the current width is matching with the width saved on the textarea
                    if($(this)[0].clientWidth != $(this).data('old_width')){
                        // If it doesn't then adjust the header column widths 
                        datatableInstances['{$view}'].columns.adjust();
                        // and save the current width so this logic only triggers if the width changed again 
                        $(this).data('old_width', $(this)[0].clientWidth); 
                    }
                });
        ";
    }

    echo "
            });
        </script>
    ";
}

/*****************************************
 * FUNCTION: DISPLAY GENERIC MULTISELECT *
 *****************************************/
function display_generic_multiselect($select_name, $select_array = [], $selected_array = [])
{
    global $escaper;

    // Add the select to the text
    $text = "<select class='form-select multiselect' multiple='multiple' id='" . $escaper->escapeHtml($select_name) . "' name='" . $escaper->escapeHtml($select_name) . "[]' >\n";

    // For each group in the array
    foreach ($select_array as $key => $group)
    {
        // Begin the option group
        $text .= "  <optgroup label='" . $escaper->escapeHtml($key) . "'>\n";

        // For each item in the group
        foreach ($group as $value)
        {
            // Check if the value is selected
            $selected = (in_array($value, $selected_array) ? " selected" : "");

            // Display the value as an option
            $text .= "  <option value='"  . $escaper->escapeHtml($value) . "'{$selected}>" . $escaper->escapeHtml($value) . "</option>\n";
        }

        // End the option group
        $text .= "  </optgroup>\n";
    }

    $text .= "</select>";

    // Output the text
    echo $text;
}

/******************************************
 * FUNCTION: DISPLAY GENERIC RADIO SELECT *
 ******************************************/
function display_generic_radio_select($select_name, $select_array = [], $selected_value = null)
{
    global $escaper;

    // Create the default text
    $text = "";

    // For each of the values in the array
    foreach ($select_array as $value)
    {
        // Get the value text and html
        $value_text = $value['text'];
        $value_html = $value['html'];

        // Check if the value is selected
        $selected = ($selected_value == $value_text ? " checked='checked'" : "");

        // Add the value as an option
        // NOTE: The value is not HTML encoded as we don't want to strip out HTML in this function, but make sure you HTML encode
        // any user supplied data from the select_array first.
        $text .= "
            <div class='row'>
                <div class='col-sm-1'>
                    <input type='radio' name='" . $escaper->escapeHtml($select_name) . "' value='" . $escaper->escapeHtml($value_text) . "' {$selected}/>
                </div>
                <div class='col'>{$value_html}</div>
            </div>";
    }

    // Output the text
    echo $text;
}

/**************************************
 * FUNCTION: DISPLAY GENERIC DROPDOWN *
 **************************************/
function display_generic_dropdown($select_name, $select_array = [], $selected_value = null)
{
    global $escaper;

    // Create the default text
    $text = "<select name='" . $escaper->escapeHtml($select_name) . "' class='form-select'>\n";

    // For each of the values in the array
    foreach ($select_array as $value)
    {
        // Check if the value is selected
        $selected = ($selected_value == $value ? " selected" : "");

        // Add the value as an option
        // NOTE: The value is not HTML encoded as we don't want to strip out HTML in this function, but make sure you HTML encode
        // any user supplied data from the select_array first.
        $text .= "<option value='" . $escaper->escapeHtml($value) . "'{$selected}>" . $escaper->escapeHtml($value) . "</option>\n";
    }

    $text .= "</select>";

    // Output the text
    echo $text;
}

?>