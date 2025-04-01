<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['multiselect', 'datatables', 'blockUI', 'CUSTOM:dynamic.js','CUSTOM:common.js'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'AllOpenRisksByTeamByLevel');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    // Get page info
    $currentpage = isset($_GET['currentpage']) ? $_GET['currentpage'] : "1";
    // Get teams submitted by user
    $teams = isset($_GET['teams']) ? $_GET['teams'] : [];
    // Get owners submitted by user
    $owners = isset($_GET['owners']) ? $_GET['owners'] : [];
    // Get owner's managers submitted by user
    $ownersmanagers = isset($_GET['ownersmanagers']) ? $_GET['ownersmanagers'] : [];

    $teamOptions = get_teams_by_login_user();
    array_unshift($teamOptions, array(
        'value' => "0",
        'name' => $lang['Unassigned'],
    ));

    $ownerOptions = $ownersManagerOptions = get_options_from_table("enabled_users");
    array_unshift($ownerOptions, array(
        'value' => "0",
        'name' => $lang['NoOwner'],
    ));
    array_unshift($ownersManagerOptions, array(
        'value' => "0",
        'name' => $lang['NoOwnersManager'],
    ));

    $custom_display_settings = $_SESSION['custom_display_settings'];

    // If customization extra is enabled
    if (customization_extra()) {

        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields();

        $risk_fields = array(
            'id',
            'risk_status',
            'closure_date',
            'subject',
            'project',
            'project_status',
            'days_open',
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
            }
        }

        $risk_fields = array_merge($risk_fields, $risk_custom_fields);
        $mitigation_fields = array_merge($mitigation_fields, $mitigation_custom_fields);
        $review_fields = array_merge($review_fields, $review_custom_fields);

    } else {

        // Names list of Risk columns
        $risk_fields = array(
            'id',
            'risk_status',
            'closure_date',
            'subject',
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
            'review_date',
            'next_step',
            'next_review_date',
            'comments'
        );
        $scoring_base_fields = "scoring_method";
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
        $scoring_fields[] = "Contributing_Impact_" . $contributing_risk['id'];
    }

    $risk_columns = [];
    $mitigation_columns = [];
    $review_columns = [];
    $scoring_columns = [];
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
    $selected_columns = array_merge($risk_columns, $mitigation_columns, $review_columns, $scoring_columns);

?>
<div class="row bg-white">
    <div class="col-12">
        <form id="risks_by_teams_form" name="get_risks_by" method="GET">
            <div class="accordion mt-2">
                <div class='accordion-item' id='filter-selections-container'>
                    <h2 class='accordion-header'>
                        <button type='button' class='accordion-button' data-bs-toggle='collapse' data-bs-target='#filter-selections-accordion-body'>
                            <?= $escaper->escapeHtml($lang['GroupAndFilteringSelections']) ?> 
                        </button>
                    </h2>
                    <div id='filter-selections-accordion-body' class='accordion-collapse collapse show'>
                        <div class='accordion-body card-body'>
                            <div class='row'>
                                <div class='col-4'>
                                    <label><?= $escaper->escapeHtml($lang['Teams']); ?> :</label>
    <?php 
                                    create_multiple_dropdown("teams", $teams, NULL, $teamOptions); 
    ?>
                                </div>
                                <div class="col-4">
                                    <label><?= $escaper->escapeHtml($lang['Owner']); ?> :</label>
    <?php 
                                    create_multiple_dropdown("owners", $owners , NULL, $ownerOptions); 
    ?>
                                </div>
                                <div class="col-4">
                                    <label><?= $escaper->escapeHtml($lang['OwnersManager']); ?> :</label>
    <?php 
                                    create_multiple_dropdown("ownersmanagers", $ownersmanagers, NULL, $ownersManagerOptions); 
    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
    <?php 
                display_risk_columns($risk_columns, $mitigation_columns, $review_columns, $scoring_columns); 
    ?>
            </div>
            <input type="hidden" value="<?= (int)$currentpage; ?>" name="currentpage" id="currentpage" >
    <?php 
            display_risks_open_by_team_dropdown_script(); 
    ?>
        </form>
    </div>
    <div class="col-12">
        <div id="risks-open-by-team-container" class="card-body border my-2">
    <?php 
        risk_table_open_by_team($selected_columns); 
    ?>
        </div>
    </div>
    <input type="hidden" id="unassigned_option" value="<?= $escaper->escapeHtml($lang["Unassigned"]);?>">
    <input type="hidden" id="date_format" value="<?= $escaper->escapeHtml(get_setting("default_date_format"));?>">
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>