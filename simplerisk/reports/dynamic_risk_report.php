<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['multiselect', 'selectize', 'datatables', 'blockUI', 'CUSTOM:dynamic.js', 'CUSTOM:common.js'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'DynamicRiskReport');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    // Set the status
    if (isset($_POST['status'])) {

        $status = (int)$_POST['status'];

    } else if (isset($_GET['status'])) {

        $status = (int)$_GET['status'];

    } else {

        $status = 0;

    }

    // Set the group
    if (isset($_POST['group'])) {

        $group = (int)$_POST['group'];

    } else if (isset($_GET['group'])) {

        $group = (int)$_GET['group'];

    } else {

        $group = 0;

    }

    // Set the sort
    if (isset($_POST['sort'])) {

        $sort = (int)$_POST['sort'];

    } else if (isset($_GET['sort'])) {

        $sort = (int)$_GET['sort'];

    } else {
        
        $sort = 0;

    }

    // Set the Tags
    $tags_filter = get_param("REQUEST", "tags_filter", []);
    if (!is_array($tags_filter)) {
        $tags_filter = [$tags_filter];
    }
    $tag_ids = array_map("base64_encode", $tags_filter);

    // Set the locations
    $locations_filter = get_param("REQUEST", "locations_filter", []);
    if (!is_array($locations_filter)) {
        $locations_filter = [$locations_filter];
    }
    $location_ids = array_map("base64_encode", $locations_filter);

    $custom_selection_settings = "";
    $custom_column_filters = "";
    if (!empty($_GET['selection'])) {

        $selection_id = (int)$_GET['selection'];
        $selection = get_dynamic_saved_selection($selection_id);
        
        // Admins can access all saved selections
        if ($selection['type'] == 'private' && $selection['user_id'] != $_SESSION['uid'] && !$_SESSION['admin']) {

            set_alert(true, "bad", $lang['NoPermissionForThisSelection']);
            refresh("dynamic_risk_report.php");

        } else {

            if ($selection['custom_display_settings']) {
                $custom_display_settings = json_decode($selection['custom_display_settings'] ?? '', true);
            } else {
                $custom_display_settings = "";
            }

            if ($selection['custom_selection_settings']) {
                $custom_selection_settings = json_decode($selection['custom_selection_settings'] ?? '', true);
            }

            if ($selection['custom_column_filters']) {
                $custom_column_filters = $selection['custom_column_filters'];
            }
        }

    } else {

        $custom_display_settings = $_SESSION['custom_display_settings'];

    }
        
    // If customization extra is enabled
    if (customization_extra()) {

        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_all_fields();

        $risk_fields = array(
            'id',
            'risk_status',
            'closure_date',
            'subject',
            'project',
            'project_status',
            'days_open',
            'closed_by',
            'close_reason',
            'close_out',
        );

        // Names list of Mitigation columns
        $mitigation_fields = array(
            'mitigation_planned',
            'mitigation_date',
        );

        // Names list of Review columns
        $review_fields = array(
            'management_review',
        );

        $scoring_base_fields = "";
        $risk_custom_fields = [];
        $mitigation_custom_fields = [];
        $review_custom_fields = [];
        $unassigned_fields = [];
        $unassigned_custom_fileds = [];

        foreach ($active_fields as $active_field) {

            $field = get_dynamic_names_by_main_field_name($active_field['name']);

            switch ($active_field['tab_index']) {
                case 1:
                    if ($active_field['is_basic'] == 1 && $field) {
                        if ($active_field['name'] != "RiskScoringMethod") {
                            $risk_fields[] = $field['name'];
                        } else {
                            $scoring_base_fields = $field['name'];
                        } 
                    } else if ($active_field['is_basic'] == 0) {
                        $risk_custom_fields[] = "custom_field_" . $active_field['id'];
                    }
                    break;
                case 2:
                    if ($active_field['is_basic'] == 1 && $field) {
                        $mitigation_fields[] = $field['name'];
                    } else if ($active_field['is_basic'] == 0) {
                        $mitigation_custom_fields[] = "custom_field_" . $active_field['id'];
                    }
                    break;
                case 3:
                    if ($active_field['is_basic'] == 1 && $field) {
                        $review_fields[] = $field['name'];
                    } else if ($active_field['is_basic'] == 0) {
                        $review_fields[] = "custom_field_" . $active_field['id'];
                    }
                    break;
                case 0: // unassigned fields
                    if ($active_field['is_basic'] == 1 && $field) {
                        $unassigned_fields[] = $field['name'];
                    } else if ($active_field['is_basic'] == 0) {
                        $unassigned_custom_fileds[] = "custom_field_" . $active_field['id'];
                    }
                    break;
            }
        }

        $risk_fields = array_unique(array_merge($risk_fields, $risk_custom_fields));
        $mitigation_fields = array_unique(array_merge($mitigation_fields, $mitigation_custom_fields));
        $review_fields = array_unique(array_merge($review_fields, $review_custom_fields));
        $unassigned_fields = array_unique(array_merge($unassigned_fields, $unassigned_custom_fileds));

    } else {

        // Names list of Risk columns
        $risk_fields = array(
            'id',
            'risk_status',
            'closure_date',
            'subject',
            'risk_mapping',
            'threat_mapping',
            'risk_tags',
            'submitted_by',
            'source',
            'submission_date',
            'category',
            'project',
            'project_status',
            'days_open',
            'location',
            'risk_assessment',
            'additional_notes',
            'reference_id',
            'regulation',
            'control_number',
            'affected_assets',
            'technology',
            'team',
            'additional_stakeholders',
            'owner',
            'manager',
            'closed_by',
            'close_reason',
            'close_out',
        );

        // Names list of Mitigation columns
        $mitigation_fields = array(
            'mitigation_planned',
            'current_solution',
            'mitigation_date',
            'security_requirements',
            'planning_date',
            'security_recommendations',
            'mitigation_effort',
            'mitigation_cost',
            'mitigation_owner',
            'mitigation_accepted',
            'planning_strategy',
            'mitigation_team',
            'mitigation_controls',
            'mitigation_percent',
        );

        // Names list of Review columns
        $review_fields = array(
            'management_review',
            'reviewer',
            'review_date',
            'next_step',
            'next_review_date',
            'comments'
        );

        $scoring_base_fields = "scoring_method";
        $unassigned_fields = [];

    }

    // Names list of Risk Scoring columns
    $scoring_fields = array(
        'calculated_risk',
        'residual_risk',
        'calculated_risk_30',
        'residual_risk_30',
        'calculated_risk_60',
        'residual_risk_60',
        'calculated_risk_90',
        'residual_risk_90',
        'CLASSIC_likelihood',
        'CLASSIC_impact',
        'CVSS_AccessVector',
        'CVSS_AccessComplexity',
        'CVSS_Authentication',
        'CVSS_ConfImpact',
        'CVSS_IntegImpact',
        'CVSS_AvailImpact',
        'CVSS_Exploitability',
        'CVSS_RemediationLevel',
        'CVSS_ReportConfidence',
        'CVSS_CollateralDamagePotential',
        'CVSS_TargetDistribution',
        'CVSS_ConfidentialityRequirement',
        'CVSS_IntegrityRequirement',
        'CVSS_AvailabilityRequirement',
        'DREAD_DamagePotential',
        'DREAD_Reproducibility',
        'DREAD_Exploitability',
        'DREAD_AffectedUsers',
        'DREAD_Discoverability',
        'OWASP_SkillLevel',
        'OWASP_Motive',
        'OWASP_Opportunity',
        'OWASP_Size',
        'OWASP_EaseOfDiscovery',
        'OWASP_EaseOfExploit',
        'OWASP_Awareness',
        'OWASP_IntrusionDetection',
        'OWASP_LossOfConfidentiality',
        'OWASP_LossOfIntegrity',
        'OWASP_LossOfAvailability',
        'OWASP_LossOfAccountability',
        'OWASP_FinancialDamage',
        'OWASP_ReputationDamage',
        'OWASP_NonCompliance',
        'OWASP_PrivacyViolation',
        'Contributing_Likelihood',
    );

    if ($scoring_base_fields) {
        array_unshift($scoring_fields, $scoring_base_fields);
    }

    $contributing_risks = get_contributing_risks();
    foreach ($contributing_risks as $contributing_risk) {
        $scoring_fields[] = "Contributing_Impact_".$contributing_risk['id'];
    }

    $risk_mapping_fields = array(
        'risk_mapping_risk_grouping',
        'risk_mapping_risk',
        'risk_mapping_description',
        'risk_mapping_function'
    );

    $risk_columns = [];
    $mitigation_columns = [];
    $review_columns = [];
    $scoring_columns = [];
    $unassigned_columns = [];
    $risk_mapping_columns = [];
     
    if (!is_array($custom_display_settings) || !count($custom_display_settings)) {
        $custom_display_settings = array(
            'id',
            'subject',
            'calculated_risk',
            'residual_risk',
            'submission_date',
            'mitigation_planned',
            'management_review',
        );
    }
    
    foreach ($risk_fields as $column) {
        $risk_columns[$column] = in_array($column, $custom_display_settings) ? true : false;
    }

    foreach ($mitigation_fields as $column) {
        $mitigation_columns[$column] = in_array($column, $custom_display_settings) ? true : false;
    }

    foreach ($review_fields as $column) {
        $review_columns[$column] = in_array($column, $custom_display_settings) ? true : false;
    }

    foreach ($scoring_fields as $column) {
        $scoring_columns[$column] = in_array($column, $custom_display_settings) ? true : false;
    }

    foreach ($unassigned_fields as $column) {
        $unassigned_columns[$column] = in_array($column, $custom_display_settings) ? true : false;
    }

    foreach ($risk_mapping_fields as $column) {
        $risk_mapping_columns[$column] = in_array($column, $custom_display_settings) ? true : false;
    }

    $selected_columns = array_merge($risk_columns, $mitigation_columns, $review_columns, $scoring_columns, $unassigned_columns, $risk_mapping_columns);

    if (is_array($custom_selection_settings)) {
        foreach ($custom_selection_settings as $select=>$custom_selection_setting) {
            if (!isset($_POST[$select])) {
                ${$select} = $custom_selection_setting;
            }
        }
    }


    // Once it has been activated
    if (import_export_extra()) {

        // Include the Import-Export Extra
        require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
        
        // if download request, download all risks
        if (isset($_POST['status']) && isset($_GET['option']) && $_GET['option'] == "download") {

            $column_filters = isset($_GET["column_filters"])?$_GET["column_filters"]:[];
            $order_column = isset($_GET["order_column"])?$_GET["order_column"]:null;
            $order_dir = isset($_GET["order_dir"])?$_GET["order_dir"]:"asc";
            $option = $_GET['option'];

            download_risks_by_table($status, $group, $sort, NULL, $selected_columns, $column_filters, $order_column, $order_dir, $option);

        }

        // if group download request, download risks by the group
        if (isset($_GET['option']) && $_GET['option'] == "download-by-group") {

            $group_value = rawurldecode($_GET['group_value']);
            $column_filters = isset($_GET["column_filters"])?$_GET["column_filters"]:[];
            $order_column = isset($_GET["order_column"])?$_GET["order_column"]:null;
            $order_dir = isset($_GET["order_dir"])?$_GET["order_dir"]:"asc";
            $option = $_GET['option'];

            download_risks_by_table($status, $group, $sort, $group_value, $selected_columns, $column_filters, $order_column, $order_dir, $option);

        }
    }

?>
<div class="row bg-white my-2">
	<div class="col-12" id="selections">
		<div class="accordion">
    <?php 
            view_get_risks_by_selections($status, $group, $sort, $risk_columns, $mitigation_columns, $review_columns, $scoring_columns, $unassigned_columns, $risk_mapping_columns); 

            display_save_dynamic_risk_selections();
    ?>
		</div>
	</div>
</div>
<div class="row bg-white mt-2">
    <div class="col-12">
        <div class="card-body border d-flex justify-content-end">
    <?php
        // If the Import-Export Extra is installed
        if (is_dir(realpath(__DIR__ . '/../extras/import-export'))) {

            // And the Extra is activated
            if (import_export_extra()) {
                // Include the Import-Export Extra
                require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
                // Display the download link
                display_download_link();
            }
        }
    ?>
        </div>
    </div>
</div>
<div class="row bg-white my-2">
	<div class="col-12">
		<div id="risk-table-container" class="card-body border">
    <?php 
            get_risks_by_table($status, $sort, $group, $selected_columns); 
    ?>
		</div>
	</div>
	<input type="hidden" id="hidden_location_filters" value="<?= implode(",", $location_ids); ?>">
	<input type="hidden" id="hidden_tag_filters" value="<?= implode(",", $tag_ids); ?>">
	<input type="hidden" id="unassigned_option" value="<?= $escaper->escapeHtml($lang["Unassigned"]);?>">
	<input type="hidden" id="date_format" value="<?= $escaper->escapeHtml(get_setting("default_date_format"));?>">
	<input type="hidden" id="custom_column_filters" value="<?= $escaper->escapeHtml($custom_column_filters);?>">
</div>
<?php
	// Render the footer of the page. Please don't put code after this part.
	render_footer();
?>