<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'CUSTOM:common.js', 'tabs:logic'], ['check_admin' => true]);

    // If the Preferences settings form was submitted
    if (isset($_POST['update_preferences_settings'])) {

        // Set the error to false
        $error = false;

        // Update the default language setting
        $default_language = get_name_by_value("languages", (int)$_POST['languages']);
        $current_default_language = get_setting("default_language");
        if ($default_language != $current_default_language) {
            update_setting("default_language", $default_language);

            // refresh the page to update the language for the user interface
            header("Location: settings_preferences.php");
            exit();
        }

        // Update the default timezone setting
        $default_timezone = $_POST['default_timezone'];
        $current_default_timezone = get_setting("default_timezone");
        if ($default_timezone != $current_default_timezone) {

            // Get the list of timezones
            $timezones = timezone_list();

            // If the selected timezone is not valid
            if (!array_key_exists($default_timezone, $timezones)) {
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['PleaseEnterAValidTimezone']));
                $error = true;
            // If the selected timezone is valid
            } else {
                update_setting("default_timezone", $default_timezone);
            }
        }

        // Update the default date format setting
        $default_date_format = $_POST['default_date_format'];
        $current_default_date_format = get_setting("default_date_format");
        if ($default_date_format != $current_default_date_format) {
            update_setting("default_date_format", $default_date_format);
        }

        // Update the default risk score setting
        $default_risk_score = (float)$_POST['default_risk_score'];
        $current_default_risk_score = get_setting("default_risk_score");
        if ($default_risk_score != $current_default_risk_score) {
            // If the default risk score is a numeric value between 0 and 10
            if (is_numeric($default_risk_score) && ($default_risk_score >= 0) && ($default_risk_score <= 10)) {
                update_setting("default_risk_score", $default_risk_score);
            }
        }

        // Update the maximum risk subject length setting
        $maximum_risk_subject_length = (float)$_POST['maximum_risk_subject_length'];
        $current_maximum_risk_subject_length = get_setting("maximum_risk_subject_length");
        if ($maximum_risk_subject_length != $current_maximum_risk_subject_length) {
            // If the maximum_risk_subject_length is a numeric value between 0 and 1000
            if (is_numeric($maximum_risk_subject_length) && ($maximum_risk_subject_length > 0) && ($maximum_risk_subject_length <= 1000)) {
                update_setting("maximum_risk_subject_length", $maximum_risk_subject_length);
            }
        }

        // Update the default closed audit status setting
        $default_closed_audit_status = (int)$_POST['closed_audit_status'];
        $current_default_closed_audit_status = get_setting("closed_audit_status");
        if ($default_closed_audit_status != $current_default_closed_audit_status) {
            // If the default closed audit status is empty
            if (empty($default_closed_audit_status)) {
                set_alert(true, "bad", $escaper->escapeHtml($lang['ClosedAuditStatusIsRequired']));
                $error = true;
            } else {
                update_setting("closed_audit_status", $default_closed_audit_status);
            }
        }

        // Update the default initiated audit status setting
        $default_initiated_audit_status = (int)$_POST['initiated_audit_status'];
        $current_default_initiated_audit_status = get_setting("initiated_audit_status");
        if ($default_initiated_audit_status != $current_default_initiated_audit_status) {
            update_setting("initiated_audit_status", $default_initiated_audit_status);
        }

        // Update the default currency setting
        $default_currency = $_POST['default_currency'];
        $current_default_currency = get_setting("currency");
        if ($default_currency != $current_default_currency) {
            // If the default currency is not empty
            if ($default_currency != "") {
                // If the default currency value is less than or equal to six characters long
                if (strlen($default_currency) <= 6) {
                    // Update the currency
                    update_setting("currency", $default_currency);
                }
            }
        }

        // Update the default asset valuation setting
        $default_asset_valuation = (int)$_POST['default_asset_valuation'];
        $current_default_asset_valuation = get_setting("default_asset_valuation");
        if ($default_asset_valuation != $current_default_asset_valuation) {
            // If the default asset valuation is numeric
            if (is_numeric($default_asset_valuation)) {
                // If the default asset valuation is between 1 and 10
                if ($default_asset_valuation >= 1 && $default_asset_valuation <= 10) {
                    // Update the default asset valuation
                    update_setting("default_asset_valuation", $default_asset_valuation);
                }
            }
        }

        // Update the default user role setting
        $default_user_role = (int)$_POST['default_user_role'];
        $current_default_user_role = get_default_role_id();
        if ($default_user_role != $current_default_user_role) {
            // Update the default user role
            set_default_role($default_user_role);
        }

        // Update the default current maturity setting
        $default_current_maturity = (int)$_POST['default_current_maturity'];
        $current_default_current_maturity = get_setting("default_current_maturity");
        if ($default_current_maturity != $current_default_current_maturity) {
            // Update the default current maturity
            update_setting("default_current_maturity", $default_current_maturity);
        }

        // Update the default desired maturity setting
        $default_desired_maturity = (int)$_POST['default_desired_maturity'];
        $current_default_desired_maturity = get_setting("default_desired_maturity");
        if ($default_desired_maturity != $current_default_desired_maturity) {
            // Update the default desired maturity
            update_setting("default_desired_maturity", $default_desired_maturity);
        }

        // Update the next review date setting
        $next_review_date_uses = $_POST['next_review_date_uses'];
        $current_next_review_date_uses = get_setting("next_review_date_uses");
        if ($next_review_date_uses != $current_next_review_date_uses) {
            update_setting("next_review_date_uses", $next_review_date_uses);
        }

        // Update the 'Show all risks for plan projects' setting (Risk Management)
        $plan_projects_show_all = (isset($_POST['plan_projects_show_all'])) ? 1 : 0;
        $current_plan_projects_show_all = get_setting("plan_projects_show_all");
        if ($plan_projects_show_all != $current_plan_projects_show_all) {
            update_setting("plan_projects_show_all", $plan_projects_show_all);
        }

        // Update the 'Require a Risk Mapping for all risks' setting (Risk Management)
        $risk_mapping_required = (isset($_POST['risk_mapping_required'])) ? 1 : 0;
        if ($risk_mapping_required != get_setting("risk_mapping_required")) {
            update_setting("risk_mapping_required", $risk_mapping_required);
        }

        // Update the 'Automatically verify new assets' setting (Asset Management)
        $auto_verify_new_assets = (isset($_POST['auto_verify_new_assets'])) ? 1 : 0;
        $current_auto_verify_new_assets = get_setting("auto_verify_new_assets");
        if ($auto_verify_new_assets != $current_auto_verify_new_assets) {
            update_setting("auto_verify_new_assets", $auto_verify_new_assets);
        }

        // Update the 'Document Exception update resets its approval' setting (Governance)
        $exception_update_resets_approval = (isset($_POST['exception_update_resets_approval'])) ? 1 : 0;
        if ($exception_update_resets_approval != get_setting("exception_update_resets_approval")) {
            update_setting("exception_update_resets_approval", $exception_update_resets_approval);
        }

        // Update the alert timeout setting (System)
        $alert_timeout = $_POST['alert_timeout'];
        if ($alert_timeout != get_setting("alert_timeout")) {
            update_setting("alert_timeout", $alert_timeout);
        }

        // Asset Valuation (Asset Management tab): branch by the selected mode
        // so the Linear / Exponential / Manual paths run independently. All
        // three write to the same 10 asset_values rows; running more than
        // one in a single submit would produce ambiguous output, so the
        // mode selector picks exactly one and we only execute that branch.
        $allowed_modes = ['linear', 'exponential', 'manual'];
        $selected_mode = isset($_POST['asset_valuation_mode']) && in_array($_POST['asset_valuation_mode'], $allowed_modes, true)
            ? $_POST['asset_valuation_mode']
            : 'manual';
        if ($selected_mode !== get_setting('asset_valuation_mode')) {
            update_setting('asset_valuation_mode', $selected_mode);
        }

        if ($selected_mode === 'linear' && isset($_POST['min_value']) && isset($_POST['max_value'])) {

            $min_value = $_POST['min_value'];
            $max_value = $_POST['max_value'];

            // If the minimum value is an integer >= 0
            if (is_numeric($min_value) && $min_value >= 0) {

                // If the maximum value is an integer
                if (is_numeric($max_value)) {

                    // Update the asset values (linear distribution)
                    $success = update_asset_values($min_value, $max_value);

                    // If the update was successful
                    if ($success) {

                        // Display an alert
                        set_alert(true, "good", "The asset valuation settings were updated successfully.");

                    } else {

                        // Display an alert
                        set_alert(true, "bad", "There was an issue updating the asset valuation settings.");

                    }

                } else {

                    // Display an alert
                    set_alert(true, "bad", "Please specify an integer for the maximum value.");

                }

            } else {

                // Display an alert
                set_alert(true, "bad", "Please specify an integer greater than or equal to zero for the minimum value.");

            }
        }
        elseif ($selected_mode === 'exponential' && isset($_POST['min_value']) && isset($_POST['max_value'])) {

            $min_value = $_POST['min_value'];
            $max_value = $_POST['max_value'];

            // Min must be non-negative (zero is allowed and produces the
            // natural "0-10, 11-100, ..." sequence anchored on the 10th
            // root of max); max must be a number strictly greater than min.
            if (!is_numeric($min_value) || $min_value < 0) {
                set_alert(true, "bad", $escaper->escapeHtml($lang['AssetValuationMinMustBeNonNegative']));
            } elseif (!is_numeric($max_value) || $max_value <= $min_value) {
                set_alert(true, "bad", "Please specify a maximum value greater than the minimum.");
            } else {

                // Update the asset values (exponential distribution)
                $success = update_asset_values_exponential($min_value, $max_value);

                if ($success) {
                    set_alert(true, "good", "The asset valuation settings were updated successfully.");
                } else {
                    set_alert(true, "bad", "There was an issue updating the asset valuation settings.");
                }
            }
        }
        elseif ($selected_mode === 'manual' && isset($_POST['valuation_level_name_1'])) {

            // For each value range
            for ($i=1; $i<=10; $i++) {

                $valuation_level_name = $_POST["valuation_level_name_" . $i];

                if (strlen($valuation_level_name) > 100) {

                    set_alert(true, "bad", _lang('ValuationLevelNameSizeError', array('valuation_level_name' => $valuation_level_name)));
                    refresh();

                }
            }

            // For each value range
            for ($i=1; $i<=10; $i++) {

                $id = $i;
                $min_value = $_POST["min_value_" . $i];
                $max_value = $_POST["max_value_" . $i];
                $valuation_level_name = $_POST["valuation_level_name_" . $i];

                // If the min_value and max_value are numeric
                if (is_numeric($min_value) && is_numeric($max_value)) {

                    // Update the asset value
                    $success = update_asset_value($id, $min_value, $max_value, $valuation_level_name);

                    // If the update was successful
                    if ($success) {

                        // Display an alert
                        set_alert(true, "good", "The asset valuation settings were updated successfully.");

                    } else {

                        // Display an alert
                        set_alert(true, "bad", "There was an issue updating the asset valuation settings.");

                    }
                }
            }
        }

        // If all setting values were saved successfully
        if (!$error) {
            // Display an alert
            set_alert(true, "good", "The settings were updated successfully.");
        }
    }

    global $escaper, $lang;
?>
<div class="row">
    <div class="col-12">
        <form name="preferences_settings" method="post" action="">
            <nav class="nav nav-tabs" role="tablist">
                <a class="nav-link active" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="true">
                    <?= $escaper->escapeHtml($lang['System']) ?>
                </a>
                <a class="nav-link" id="risk-management-tab" data-bs-toggle="tab" data-bs-target="#risk-management" type="button" role="tab" aria-controls="risk-management" aria-selected="false">
                    <?= $escaper->escapeHtml($lang['RiskManagement']) ?>
                </a>
                <a class="nav-link" id="governance-tab" data-bs-toggle="tab" data-bs-target="#governance" type="button" role="tab" aria-controls="governance" aria-selected="false">
                    <?= $escaper->escapeHtml($lang['Governance']) ?>
                </a>
                <a class="nav-link" id="compliance-tab" data-bs-toggle="tab" data-bs-target="#compliance" type="button" role="tab" aria-controls="compliance" aria-selected="false">
                    <?= $escaper->escapeHtml($lang['Compliance']) ?>
                </a>
                <a class="nav-link" id="asset-management-tab" data-bs-toggle="tab" data-bs-target="#asset-management" type="button" role="tab" aria-controls="asset-management" aria-selected="false">
                    <?= $escaper->escapeHtml($lang['AssetManagement']) ?>
                </a>
            </nav>
            <div class="tab-content my-2">
                <div id="system" class="tab-pane active" role="tabpanel" aria-labelledby="system-tab">
                    <div class="card-body my-2 border">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultLanguage']); ?> :</label>
<?php
                                    create_dropdown("languages", get_value_by_name("languages", $escaper->escapeHtml(get_setting("default_language"))), null, false);
?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultTimezone']); ?> :</label>
                                    <select class="form-select" id="default_timezone" name="default_timezone">
<?php
    // Get the list of timezones
    $timezones = timezone_list();
    // Get the default timezone
    $default_timezone = $escaper->escapeHtml(get_setting("default_timezone"));
    // For each timezone
    foreach($timezones as $key => $value) {
        echo "
                                        <option value='{$key}'" . ($key == $default_timezone ? " selected" : "") . ">{$value}</option>
        ";
    }
?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultDateFormat']); ?> :</label>
<?php
    // Get the default date format
    $default_date_format = $escaper->escapeHtml(get_setting("default_date_format"));

                                    create_dropdown("date_formats", $default_date_format, "default_date_format", false);
?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultCurrencySymbol']); ?> :</label>
                                    <input type="text" name="default_currency" maxlength="3" value="<?= $escaper->escapeHtml(get_setting("currency")); ?>" class="form-control"/>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultUserRole']); ?> :</label>
<?php
                                    // Create role dropdown
                                    create_dropdown("role", $escaper->escapeHtml(get_default_role_id()), "default_user_role");
?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['AlertTimeout']); ?> :</label>
                                    <select class="form-select" id="alert_timeout" name="alert_timeout">
<?php

    // Create the list of possible timeouts
    $possible_timeouts = array(
        "5"     => _lang('TimeoutXSeconds', array('timeout' => '5')),
        "10"    => _lang('TimeoutXSeconds', array('timeout' => '10')),
        "15"    => _lang('TimeoutXSeconds', array('timeout' => '15')),
        "30"    => _lang('TimeoutXSeconds', array('timeout' => '30')),
        "60"    => _lang('TimeoutXSeconds', array('timeout' => '60')),
        "0"     => $lang['StayUntilClicked'],
    );

    // Get the current value
    $alert_timeout = get_setting("alert_timeout", "5");

    // For each possible timeout
    foreach($possible_timeouts as $key => $value) {
        echo "
                                        <option value='{$key}'" . ($key == $alert_timeout ? " selected" : "") . ">{$escaper->escapeHtml($value)}</option>
        ";
    }

?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="risk-management" class="tab-pane" role="tabpanel" aria-labelledby="risk-management-tab">
                    <div class="card-body my-2 border">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultRiskScore']); ?> :</label>
                                    <input value="<?= $escaper->escapeHtml(get_setting('default_risk_score')); ?>" name="default_risk_score" id="default_risk_score" type="number" min="0" step="0.1" max="10" class="form-control"/>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['MaximumRiskSubjectLength']); ?> :</label>
                                    <input value="<?= $escaper->escapeHtml(get_setting('maximum_risk_subject_length')); ?>" name="maximum_risk_subject_length" id="maximum_risk_subject_length" type="number" min="1" step="1" max="1000" class="form-control"/>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['NextReviewDateUses']); ?> :</label>
                                    <select name="next_review_date_uses" class="form-select">
                                        <option value="InherentRisk" <?= $escaper->escapeHtml(get_setting("next_review_date_uses")) == "InherentRisk" ? "selected" : ""; ?> ><?= $escaper->escapeHtml($lang['InherentRisk']); ?></option>
                                        <option value="ResidualRisk" <?= $escaper->escapeHtml(get_setting("next_review_date_uses")) == "ResidualRisk" ? "selected" : ""; ?>><?= $escaper->escapeHtml($lang['ResidualRisk']); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row form-group mt-2">
                            <div class="col-md-12">
                                <div class="form-check mr-sm-2">
                                    <input <?php if($escaper->escapeHtml(get_setting('plan_projects_show_all')) == 1){ echo "checked"; } ?> name="plan_projects_show_all" class="form-check-input" size="2" value="90" id="plan_projects_show_all" type="checkbox">
                                    <label class="form-check-label mb-0 ms-2" for="plan_projects_show_all"><?= $escaper->escapeHtml($lang['ShowAllRisksForPlanProjects']); ?></label>
                                </div>
                                <div class="form-check mr-sm-2">
                                    <input <?php if($escaper->escapeHtml(get_setting('risk_mapping_required')) == 1){ echo "checked"; } ?> name="risk_mapping_required" class="form-check-input" size="2" value="90" id="risk_mapping_required" type="checkbox">
                                    <label class="form-check-label mb-0 ms-2" for="risk_mapping_required"><?= $escaper->escapeHtml($lang['RequireRiskMappingForAllRisks']); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="governance" class="tab-pane" role="tabpanel" aria-labelledby="governance-tab">
                    <div class="card-body my-2 border">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultCurrentMaturity']); ?> :</label>
<?php
                                    // Create default current maturity dropdown
                                    create_dropdown("control_maturity", $escaper->escapeHtml(get_setting("default_current_maturity")), "default_current_maturity", false);
?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultDesiredMaturity']); ?> :</label>
<?php
                                    // Create default desired maturity dropdown
                                    create_dropdown("control_maturity", $escaper->escapeHtml(get_setting("default_desired_maturity")), "default_desired_maturity", false);
?>
                                </div>
                            </div>
                        </div>
                        <div class="row form-group mt-2">
                            <div class="col-md-12">
                                <div class="form-check mr-sm-2">
                                    <input <?php if($escaper->escapeHtml(get_setting('exception_update_resets_approval')) == 1){ echo "checked"; } ?> name="exception_update_resets_approval" class="form-check-input" size="2" value="90" id="exception_update_resets_approval" type="checkbox" >
                                    <label class="form-check-label mb-0 ms-2" for="exception_update_resets_approval"><?= $escaper->escapeHtml($lang['ExceptionUpdateResetsApproval']); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="compliance" class="tab-pane" role="tabpanel" aria-labelledby="compliance-tab">
                    <div class="card-body my-2 border">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultInitiatedAuditStatus']); ?> :</label>
<?php
                                    create_dropdown("test_status", $escaper->escapeHtml(get_setting("initiated_audit_status")), "initiated_audit_status", true, false, false, "", "--", 0);
?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultClosedAuditStatus']); ?> :</label>
<?php
                                    create_dropdown("test_status", $escaper->escapeHtml(get_setting("closed_audit_status")), "closed_audit_status", false, false, false, "required");
?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="asset-management" class="tab-pane" role="tabpanel" aria-labelledby="asset-management-tab">
                    <div class="card-body my-2 border">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['DefaultAssetValuation']); ?> :</label>
<?php
    // Get the default asset valuation
    $default = get_default_asset_valuation();

                                    // Create the asset valuation dropdown
                                    create_asset_valuation_dropdown("default_asset_valuation", $default);
?>
                                </div>
                            </div>
                        </div>
                        <div class="row form-group mt-2">
                            <div class="col-md-12">
                                <div class="form-check mr-sm-2">
                                    <input <?php if($escaper->escapeHtml(get_setting('auto_verify_new_assets')) == 1){ echo "checked"; } ?> name="auto_verify_new_assets" class="form-check-input" size="2" value="90" id="auto_verify_new_assets" type="checkbox">
                                    <label class="form-check-label mb-0 ms-2" for="auto_verify_new_assets"><?= $escaper->escapeHtml($lang['AutomaticallyVerifyNewAssets']); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
<?php
                    // Asset Valuation mode persists as a setting (default
                    // 'manual'). The selector drives which card is visible
                    // and which math runs on submit; see the
                    // $selected_mode branch at the top of this file.
                    $asset_valuation_mode = get_setting('asset_valuation_mode', 'manual');
                    if (!in_array($asset_valuation_mode, ['linear', 'exponential', 'manual'], true)) {
                        $asset_valuation_mode = 'manual';
                    }
?>
                    <div class="card-body my-2 border">
                        <h4><?= $escaper->escapeHtml($lang['AssetValuation']); ?></h4>
                        <div class="row form-group">
                            <div class="col-6">
                                <label for="asset_valuation_mode"><?= $escaper->escapeHtml($lang['AssetValuationMode']); ?> :</label>
                                <select name="asset_valuation_mode" id="asset_valuation_mode" class="form-select">
                                    <option value="linear" <?= $asset_valuation_mode === 'linear' ? 'selected' : '' ?>><?= $escaper->escapeHtml($lang['AutomaticLinearRange']); ?></option>
                                    <option value="exponential" <?= $asset_valuation_mode === 'exponential' ? 'selected' : '' ?>><?= $escaper->escapeHtml($lang['AutomaticExponentialRange']); ?></option>
                                    <option value="manual" <?= $asset_valuation_mode === 'manual' ? 'selected' : '' ?>><?= $escaper->escapeHtml($lang['ManualValuation']); ?></option>
                                </select>
                            </div>
                        </div>
                        <div id="asset-valuation-automatic" class="<?= in_array($asset_valuation_mode, ['linear', 'exponential'], true) ? '' : 'd-none' ?>">
                            <div class="row form-group">
                                <div class="col-6">
                                    <label><?= $escaper->escapeHtml($lang['MinimumValue']); ?> :</label>
                                    <input id="dollarsign_min" type="number" name="min_value" min="0" size="20" value="<?= asset_min_value(); ?>" class="form-control"/>
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="col-6">
                                    <label><?= $escaper->escapeHtml($lang['MaximumValue']); ?> :</label>
                                    <input id="dollarsign_max" type="number" name="max_value" size="20" value="<?= asset_max_value(); ?>" class="form-control"/>
                                </div>
                            </div>
                        </div>
                        <div id="asset-valuation-manual" class="<?= $asset_valuation_mode === 'manual' ? '' : 'd-none' ?>">
<?php
                            display_asset_valuation_table();
?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body my-2 border">
                <div class="row">
                    <div class="col-12">
                        <div>
                            <input type="submit" value="<?= $escaper->escapeHtml($lang['Update']); ?>" name="update_preferences_settings" class="btn btn-submit"/>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    // Toggle the Automatic / Manual asset-valuation cards based on the
    // mode selector. Only the visible card's inputs participate in the
    // submit because the POST handler branches by the selected mode, but
    // hiding the unused card keeps the UI honest.
    document.addEventListener('DOMContentLoaded', function () {
        var sel  = document.getElementById('asset_valuation_mode');
        var auto = document.getElementById('asset-valuation-automatic');
        var man  = document.getElementById('asset-valuation-manual');
        if (!sel || !auto || !man) { return; }
        function applyMode() {
            var m = sel.value;
            auto.classList.toggle('d-none', m === 'manual');
            man.classList.toggle('d-none', m !== 'manual');
        }
        sel.addEventListener('change', applyMode);
        applyMode();
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
