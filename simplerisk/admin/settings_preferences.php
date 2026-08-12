<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    // customization_extra() lives in functions.php, which renderutils.php does
    // NOT pull in -- and the call below runs before render_header_and_sidebar()
    // loads anything else, so without this the page fatals with "Call to
    // undefined function". Declared here per CLAUDE.md's rule that every direct
    // consumer requires the file defining the helper rather than relying on a
    // transitive include.
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    // The WYSIWYG bundle is ~460KB (HugeRTE + its skin) and is only ever
    // attached to the system-use-notice field, which exists only when the
    // Customization Extra is active. Loading it unconditionally made every
    // admin on this page pay for an editor most of them never see.
    $preferences_scripts = ['blockUI', 'CUSTOM:common.js', 'tabs:logic'];
    $preferences_localization = [];

    if (customization_extra()) {
        $preferences_scripts[] = 'WYSIWYG';
        // The editor's style-dropdown labels; see init_notice_editor().
        $preferences_localization = ['NoticeSizeSmall', 'NoticeSizeNormal', 'NoticeSizeLarge'];
    }

    render_header_and_sidebar($preferences_scripts, ['check_admin' => true], '', '', '', '', '', null, $preferences_localization);

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

        // ---- Login screen branding (System) --------------------------------
        //
        // Gated on the Extra HERE, independently of the UI. The form disables
        // these controls when Customization is inactive, but a disabled input
        // is a rendering choice, not an authorization control -- a request
        // built outside the browser never sees it. Everything below is skipped
        // outright rather than validated-then-skipped, so an unlicensed
        // instance has no write path to these settings at all.
        //
        // The `custom_logo` TABLE belongs to the Customization Extra, which
        // creates it on activation, so the second half of this gate is the
        // Core/Extra boundary guard CLAUDE.md requires before Core touches an
        // Extra-owned table. customization_extra() alone reads the settings
        // flag, which can be true while the table is absent -- an instance
        // mid-upgrade, or one whose flag was flipped directly in the database.
        // The tagline and notice are plain `settings` rows and need no such
        // guard, but they sit inside it because they are the same feature and
        // an admin is better served by one coherent refusal than by a form that
        // half-saves.
        $custom_logo_table_ready = customization_extra() && table_exists('custom_logo');

        if ($custom_logo_table_ready) {

            // Tagline: plain text, length enforced server-side as well as by
            // the field's maxlength, for the same reason.
            if (isset($_POST['login_tagline'])) {
                $login_tagline = trim($_POST['login_tagline']);
                if (mb_strlen($login_tagline) > 120) {
                    $login_tagline = mb_substr($login_tagline, 0, 120);
                }
                if ($login_tagline != get_setting('login_tagline')) {
                    update_setting('login_tagline', $login_tagline);
                }
            }

            // Notice: sanitized on the way IN as well as at render. Storing it
            // clean means the login page is not the only thing standing between
            // an operator's markup and an unauthenticated visitor.
            if (isset($_POST['login_notice'])) {
                $login_notice = purify_html_login_notice(trim($_POST['login_notice']));
                if ($login_notice != get_setting('login_notice')) {
                    update_setting('login_notice', $login_notice);
                }
            }

            // Remove an existing logo. Checked before the upload branch so a
            // submit that both removes and uploads ends up with the upload,
            // which is what the admin most recently chose.
            if (isset($_POST['remove_custom_logo']) && empty($_FILES['custom_logo']['name'])) {
                $db = db_open();
                $db->prepare("DELETE FROM `custom_logo` WHERE `id` = 1;")->execute();
                db_close($db);
                update_setting('custom_logo', '');
                update_setting('custom_logo_version', '');
                set_alert(true, "good", $lang['LogoRemoved']);
            }

            // Upload a new logo.
            if (!empty($_FILES['custom_logo']['name']) && $_FILES['custom_logo']['error'] !== UPLOAD_ERR_NO_FILE) {

                $logo_error = false;

                if ($_FILES['custom_logo']['error'] !== UPLOAD_ERR_OK) {
                    set_alert(true, "bad", $lang['LogoUploadFailed']);
                    $logo_error = true;
                }

                // getimagesize() is the gate, not $_FILES['type'] -- that field
                // is supplied by the client and means nothing. This reads the
                // file's own header, so it proves the upload is really an
                // image, yields the MIME we store and serve, and gives the
                // dimensions, all without decoding the pixels.
                $logo_info = $logo_error ? false : @getimagesize($_FILES['custom_logo']['tmp_name']);

                // The rules themselves live in a pure helper so they can be
                // tested directly rather than through a form post.
                if (!$logo_error) {
                    $logo_rejection = validate_custom_logo_upload((int)$_FILES['custom_logo']['size'], $logo_info);

                    if ($logo_rejection !== null) {
                        set_alert(true, "bad", $lang[$logo_rejection]);
                        $logo_error = true;
                    }
                }

                $allowed_logo_types = get_custom_logo_types();

                if (!$logo_error) {
                    $logo_bytes = file_get_contents($_FILES['custom_logo']['tmp_name']);

                    if ($logo_bytes === false) {
                        set_alert(true, "bad", $lang['LogoUploadFailed']);
                    } else {
                        // The bytes are stored EXACTLY as uploaded -- no resize,
                        // no re-encode. Image parsers are a classic
                        // vulnerability class, and running every customer's
                        // upload through one to save a few pixels is a poor
                        // trade when CSS already bounds the display size.
                        //
                        // The stored filename is display-only (the settings
                        // screen names the current file); it never reaches a
                        // path, a URL or a response header.
                        $logo_name = mb_substr(basename($_FILES['custom_logo']['name']), 0, 255);

                        $db = db_open();
                        $stmt = $db->prepare("
                            REPLACE INTO `custom_logo` (`id`, `filename`, `mime_type`, `content`, `updated_at`)
                            VALUES (1, :filename, :mime_type, :content, NOW());
                        ");
                        $stmt->bindParam(":filename", $logo_name, PDO::PARAM_STR);
                        $stmt->bindParam(":mime_type", $allowed_logo_types[$logo_info[2]], PDO::PARAM_STR);
                        $stmt->bindParam(":content", $logo_bytes, PDO::PARAM_LOB);
                        $stmt->execute();
                        db_close($db);

                        update_setting('custom_logo', $logo_name);
                        // Version by the bytes, not the name. Re-uploading a
                        // corrected image under the same filename otherwise
                        // produced an identical ?v= and the endpoint's 24h
                        // Cache-Control served the old one for a day.
                        update_setting('custom_logo_version', substr(md5($logo_bytes), 0, 8));
                        set_alert(true, "good", $lang['LogoUpdated']);
                    }
                }
            }
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
        <form name="preferences_settings" method="post" action="" enctype="multipart/form-data">
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
<?php
    // ---- Login screen branding (Customization Extra) ------------------------
    //
    // Rendered whether or not the Extra is active. An unavailable capability is
    // shown and MARKED, never hidden: burying a feature cannot create a sell
    // opportunity, which is the rule the .sr-locked component exists to carry
    // (scss/modules/_locked-affordance.scss, already used by Define Control
    // Frameworks and the Statement of Applicability).
    //
    // The controls are disabled rather than merely styled when locked, and the
    // save path refuses these three writes independently -- a disabled input is
    // a UI affordance, not an authorization control, and a crafted POST does
    // not go through the UI at all.
    $branding_unlocked = customization_extra();
    $branding_logo = $branding_unlocked ? trim((string)get_setting('custom_logo')) : '';
    $branding_disabled = $branding_unlocked ? '' : " disabled";
?>
                        <div class="row<?= $branding_unlocked ? '' : ' sr-locked' ?>">
                            <div class="col-12">
                                <h5 class="mt-3">
                                    <?= $escaper->escapeHtml($lang['LoginScreenBranding']); ?>
<?php if (!$branding_unlocked) { ?>
                                    <span class="sr-locked-badge"><i class="fa fa-lock" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['LockedAffordanceBadge']); ?></span>
<?php } ?>
                                </h5>
<?php if (!$branding_unlocked) { ?>
                                <span class="sr-locked-note">
                                    <?= $escaper->escapeHtml($lang['BrandingRequiresCustomization']); ?>
                                    <a class="sr-locked-link" href="https://www.simplerisk.com/extras/customization" target="_blank" rel="noopener noreferrer"><?= $escaper->escapeHtml($lang['LearnMore']); ?></a>
                                </span>
<?php } ?>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="custom_logo"><?= $escaper->escapeHtml($lang['CustomLogo']); ?> :</label>
                                    <input type="file" class="form-control" name="custom_logo" id="custom_logo" accept="image/png,image/jpeg,image/gif,image/webp"<?= $branding_disabled ?> />
                                    <small class="form-text text-muted"><?= $escaper->escapeHtml($lang['CustomLogoHint']); ?></small>
<?php if ($branding_logo !== '') { ?>
                                    <div class="mt-2">
                                        <span class="me-2"><?= $escaper->escapeHtml($lang['CurrentLogo']); ?>: <b><?= $escaper->escapeHtml($branding_logo); ?></b></span>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remove_custom_logo" id="remove_custom_logo" value="1"<?= $branding_disabled ?> />
                                            <label class="form-check-label" for="remove_custom_logo"><?= $escaper->escapeHtml($lang['RemoveLogo']); ?></label>
                                        </div>
                                    </div>
<?php } ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="login_tagline"><?= $escaper->escapeHtml($lang['LoginTagline']); ?> :</label>
                                    <input type="text" class="form-control" name="login_tagline" id="login_tagline" maxlength="120" value="<?= $escaper->escapeHtmlAttr(get_setting('login_tagline')); ?>"<?= $branding_disabled ?> />
                                    <small class="form-text text-muted"><?= $escaper->escapeHtml($lang['LoginTaglineHint']); ?></small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="login_notice"><?= $escaper->escapeHtml($lang['LoginNotice']); ?> :</label>
<?php
    if ($branding_unlocked) {
?>
                                    <textarea class="form-control login-notice-editor" name="login_notice" id="login_notice"><?= $escaper->escapeHtml(get_setting('login_notice')); ?></textarea>
<?php
    } else {
        // Locked: show the notice AS IT RENDERS, not as markup. A disabled
        // textarea full of tags tells an admin nothing about what visitors
        // actually see, and the editor is deliberately not attached to a
        // disabled control -- so the read-only state gets a preview instead.
        //
        // No name attribute, so it submits nothing. That is presentation, not
        // protection: the save path refuses these writes on its own.
        //
        // Re-purified at this boundary for the same reason the login panel
        // re-purifies: a value that reached storage by some other route has
        // never passed the save path, and this is an HTML sink.
        $locked_notice = purify_html_login_notice((string)get_setting('login_notice'));

        if ($locked_notice !== '') {
?>
                                    <div class="sr-notice-preview"><?= $locked_notice ?></div>
<?php
        } else {
?>
                                    <div class="sr-notice-preview sr-notice-preview--empty"><?= $escaper->escapeHtml($lang['NoSystemUseNoticeSet']); ?></div>
<?php
        }
    }
?>
                                    <small class="form-text text-muted"><?= $escaper->escapeHtml($lang['LoginNoticeHint']); ?></small>
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

    // The system-use notice editor. Only attached when the Customization Extra
    // is active -- with it locked the textarea is disabled, and turning a
    // disabled control into a rich-text editor would make it look editable
    // again. editor.js is deferred, so wait for it rather than assuming it has
    // parsed by DOMContentLoaded.
    window.addEventListener('load', function () {
        if (typeof init_notice_editor !== 'function') { return; }
        var notice = document.getElementById('login_notice');
        if (!notice || notice.disabled) { return; }
        init_notice_editor('#login_notice');
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
