<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['tabs:logic', 'editable'], ['check_admin' => true]);

    // Check if the review settings update was submitted
    //
    // Relocated from admin/review_settings.php during the Settings Hub
    // consolidation — review cadence is a risk-lifecycle concern that
    // lives on the Risk Configuration page (Review Settings tab) rather
    // than a standalone Hub tile.
    if (isset($_POST['update_review_settings_input']) && $_POST['update_review_settings_input'] == 'review_settings')
    {
        $veryhigh = (int)$_POST['veryhigh'];
        $high = (int)$_POST['high'];
        $medium = (int)$_POST['medium'];
        $low = (int)$_POST['low'];
        $insignificant = (int)$_POST['insignificant'];

        // Check if all values are integers
        if (is_int($veryhigh) && is_int($high) && is_int($medium) && is_int($low) && is_int($insignificant)){
            // Update the review settings
            update_review_settings($veryhigh, $high, $medium, $low, $insignificant);

            // Display an alert
            set_alert(true, "good", "The review settings have been updated successfully!");
        }
            // NOTE: This will never trigger as we bind $high, $medium, and $low to integer values
        else{
            // Display an alert
            set_alert(true, "bad", "One of your review settings is not an integer value.  Please try again.");
        }
    }

    // Check if the Risk Appetite was submitted
    //
    // Relocated from admin/settings.php's Default Values tab during the
    // Settings Hub split — the slider lives in the Risk Levels tab here
    // because it identifies an organizational risk tolerance against the
    // risk-level gradient defined on the same page, not a "form default".
    if (isset($_POST['update_risk_appetite'])) {
        $risk_appetite_post = (float)$_POST['risk_appetite'];
        if ($risk_appetite_post != get_setting("risk_appetite") && $_POST['risk_appetite'] !== "") {
            update_setting("risk_appetite", $risk_appetite_post);
            set_alert(true, "good", "The settings were updated successfully.");
        }
        refresh();
    }

    // Check if the SLA thresholds were submitted
    if (isset($_POST['update_sla_thresholds'])) {

        $sla_error = false;
        $risk_levels_for_sla = get_risk_levels();

        foreach ($risk_levels_for_sla as $rl) {
            $setting_name = 'sla_threshold_' . strtolower(str_replace(' ', '_', $rl['name']));
            if (isset($_POST[$setting_name])) {
                $sla_val = (int)$_POST[$setting_name];
                if ($sla_val >= 1 && $sla_val <= 3650) {
                    if ($sla_val != (int)get_setting($setting_name)) {
                        update_setting($setting_name, $sla_val);
                    }
                } else {
                    $sla_error = true;
                    set_alert(true, "bad", $escaper->escapeHtml($lang['SLAThresholdMustBeBetween1And3650']));
                }
            }
        }

        if (!$sla_error) {
            set_alert(true, "good", $escaper->escapeHtml($lang['SLAThresholdsUpdatedSuccessfully']));
        }

        refresh();
    }

    // Check if the risk formula update was submitted
    if (isset($_POST['update_risk_formula'])) {
        
        global $lang, $escaper;

        $risk_model = (int)$_POST['risk_models'];
        $need_risk_score_normalization = !empty($_POST['need_risk_score_normalization']) ? 'true' : 'false';

        // Check if risk model value is integer
        if (is_int($risk_model)) {

            // Risk model should be between 1 and 5
            if ((1 <= $risk_model) && ($risk_model <= 6)) {

                $current_need_risk_score_normalization = get_setting('need_risk_score_normalization', 'true');
                if ($need_risk_score_normalization != $current_need_risk_score_normalization) {

                    //Update the need_risk_score_normalization setting
                    update_setting('need_risk_score_normalization', $need_risk_score_normalization);

                    // Display an alert
                    set_alert(true, "good", $escaper->escapeHtml($lang['TheRiskScoreNormalizationSettingWasUpdatedSuccessfully_TheScoresOfExistingRisksWereRecalculatedBasedOnTheNewSetting']));

                }

                // Update the risk model
                update_risk_model($risk_model);

                // Display an alert
                set_alert(true, "good", "The configuration was updated successfully.");
                
                refresh();

            // Otherwise, there was a problem
            } else {

                // Display an alert
                set_alert(true, "bad", "The risk formula submitted was an invalid value.");

            }
        }
    }
    
    // Check if the impact update was submitted
    if (isset($_POST['update_impact'])) {

        $new_name = $_POST['new_name'];
        $value = (int)$_POST['impact'];

        // Verify value is an integer
        if (is_int($value)) {

            update_table("impact", $new_name, $value);

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['SuccessUpdatingImpactName']));

            refresh();

        }
    }

    // Check if the likelihood update was submitted
    if (isset($_POST['update_likelihood'])) {

        $new_name = $_POST['new_name'];
        $value = (int)$_POST['likelihood'];

        // Verify value is an integer
        if (is_int($value)) {

            update_table("likelihood", $new_name, $value);

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['SuccessUpdatingLikelihoodName']));
            
            refresh();

        }
    }
        
    // Check if contributing risk was submitted
    if (isset($_POST['save_contributing_risk'])) {

        $subjects = empty($_POST['subject']) ? [] : $_POST['subject'];
        $weights = empty($_POST['weight']) ? [] : $_POST['weight'];
        $existing_subjects = empty($_POST['existing_subject']) ? [] : $_POST['existing_subject'];
        $existing_weights = empty($_POST['existing_weight']) ? [] : $_POST['existing_weight'];
        
        // Save contributing risks
        if (save_contributing_risks($subjects, $weights, $existing_subjects, $existing_weights)) {

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['SuccessSaveContributingRisks']));
            
            refresh();

        }
    }

    function display_editable_line_for($localizationKey, $risk_levels, $level) {

        global $escaper;

        $risk_name = "
            <span data-level='{$level}'>
                <span class='editable'>{$escaper->escapeHtml($risk_levels[$level]['display_name'])}</span>
                <input type='text' data-field='display_name' class='editable' value='{$escaper->escapeHtml($risk_levels[$level]['display_name'])}' style='display: none;'>
            </span>
        ";

        $risk_value = "
            <span data-level='{$level}'>
                <span class='editable'>{$escaper->escapeHtml($risk_levels[$level]['value'])}</span>
                <input type='text' data-field='value' class='editable' value='{$escaper->escapeHtml($risk_levels[$level]['value'])}' style='display: none;'>
            </span>
        ";

        $color_select = "
            <span data-level='{$level}'>
                <input data-field='color' class='level-colorpicker level-color editable' type='hidden' value='{$escaper->escapeHtml($risk_levels[$level]['color'])}'>
                <input type='color' class='form-control-color my-1 color-picker'/>
            </span>
        ";

        echo "
            <div>" . 
                _lang_raw($localizationKey, array('risk_name' => $risk_name, 'risk_value' => $risk_value, 'color_select' => $color_select)) . "
            </div>
        ";
    }

?>
<div class="row">
    <div class="mt-2">
        <nav class="nav nav-tabs">
            <a class="nav-link active" id="scoring-tab" data-bs-toggle="tab" data-bs-target="#scoring" type="button" role="tab" aria-controls="scoring" aria-selected="true">
                <?= $escaper->escapeHtml($lang['Scoring']); ?>
            </a>
            <a class="nav-link" id="classicriskformula-tab" data-bs-toggle="tab" data-bs-target="#classic-risk-formula" type="button" role="tab" aria-controls="classic-risk-formula" aria-selected="false">
                <?= $escaper->escapeHtml($lang['ClassicRiskFormula']); ?>
            </a>
            <a class="nav-link" id="contributingriskformula-tab" data-bs-toggle="tab" data-bs-target="#contributing-risk-formula" type="button" role="tab" aria-controls="contributing-risk-formula" aria-selected="false">
                <?= $escaper->escapeHtml($lang['ContributingRiskFormula']); ?>
            </a>
            <a class="nav-link" id="reviewsettings-tab" data-bs-toggle="tab" data-bs-target="#review-settings" type="button" role="tab" aria-controls="review-settings" aria-selected="false">
                <?= $escaper->escapeHtml($lang['ReviewSettings']); ?>
            </a>
        </nav>
    </div>
    <div class="tab-content cust-tab-content" id="myTabContent" >
        <div class="tab-pane active risk-levels-container" id="scoring" role="tabpanel" aria-labelledby="scoring-tab">
            <div class="card-body my-2 border">
                <h4 class="page-title"><?= $escaper->escapeHtml($lang['RiskLevels']); ?></h4>
    <?php
        $risk_levels = get_risk_levels();
                display_editable_line_for('RiskLevelTextTop', $risk_levels, 3);
                display_editable_line_for('RiskLevelTextRest', $risk_levels, 2);
                display_editable_line_for('RiskLevelTextRest', $risk_levels, 1);
                display_editable_line_for('RiskLevelTextRest', $risk_levels, 0);
    ?>
            </div>
            <div class="card-body my-2 border">
                <h4 class="page-title"><?= $escaper->escapeHtml($lang['RiskAppetite']); ?></h4>
                <form name="risk_appetite_settings" method="post" action="">
    <?php
        $risk_appetite = (float)get_setting("risk_appetite", 0);

        // Build a separate ranges array for the slider gradient — we extend
        // the persisted risk_levels with a synthetic "Insignificant" bucket at
        // the bottom so the slider can render a 0-value position. This does
        // not modify the configured risk_levels (the editable-line table above
        // still reflects the persisted values).
        $appetite_levels = $risk_levels;
        if ((int)$appetite_levels[0]['value'] > 0) {
            array_unshift($appetite_levels, ['value' => 0.0, 'name' => 'Insignificant', 'color' => 'white', 'display_name' => $lang['Insignificant']]);
        }

        $ranges = [];
        $number_of_levels = count($appetite_levels);
        foreach ($appetite_levels as $key => $level) {
            $next_key = ($key + 1 < $number_of_levels) ? $key + 1 : null;
            $ranges[] = [
                'display_name' => $level['display_name'],
                'color'        => $level['color'],
                'range'        => [(int)$level['value'], $next_key ? $appetite_levels[$next_key]['value'] - 0.1 : 9999],
            ];
        }

        $need_risk_score_normalization = get_setting("need_risk_score_normalization", "true");

        // Calculate the maximum risk score based on the current risk model
        $max_risk = calculate_maximum_risk_score();

        if ($need_risk_score_normalization == "true") {
            $multiple_index = 10;
        } else {
            $multiple_index = round((100 / $max_risk), 2);
        }

        $slider_bg_grad = '';
        foreach ($ranges as $key => $range) {
            if ($key == 0) {
                $slider_bg_grad = "{$escaper->escapeCssColor($range['color'])} " . ($range['range'][1] * $multiple_index) . "%";
            } elseif ($key == count($ranges) - 1) {
                $slider_bg_grad .= ", {$escaper->escapeCssColor($range['color'])} " . ($ranges[$key - 1]['range'][1] * $multiple_index) . "%, {$escaper->escapeCssColor($range['color'])} 100%";
            } else {
                $slider_bg_grad .= ", {$escaper->escapeCssColor($range['color'])} " . ($ranges[$key - 1]['range'][1] * $multiple_index) . "%, {$escaper->escapeCssColor($range['color'])} " . ($range['range'][1] * $multiple_index) . "%";
            }
        }
    ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <div class="slider-progress-values">
                                    <label><?= $escaper->escapeHtml($lang['RiskAppetite']); ?> :</label>
                                    <span id="risk_appetite_display" style="border:0; font-weight:bold;"><?= $escaper->escapeHtml($risk_appetite); ?></span>
                                    <span id="risk_appetite_color" class="risk-color" style="background-color:#ff0000"></span>
                                </div>
                                <input type="hidden" id="risk_appetite" name="risk_appetite" value="<?= $escaper->escapeHtml($risk_appetite); ?>">
                                <div id="slider" style="margin-top: 10px; background-image: linear-gradient(90deg, <?= $slider_bg_grad; ?>); background-size: 100% 100%;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <button type="submit" name="update_risk_appetite" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Update']); ?></button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-body my-2 border">
                <h4 class="page-title"><?= $escaper->escapeHtml($lang['SLAThresholds']); ?></h4>
                <p class="text-muted"><?= $escaper->escapeHtml($lang['SLAThresholdsDescription']); ?></p>
                <form method="post" action="">
                    <div class="row">
    <?php
        // Display one input per risk level, highest first, using the level's own color and display name
        foreach (array_reverse($risk_levels) as $rl) {
            $setting_name = 'sla_threshold_' . strtolower(str_replace(' ', '_', $rl['name']));
            $defaults = ['Very High' => 30, 'High' => 60, 'Medium' => 90, 'Low' => 180];
            $current_val = (int)get_setting($setting_name, $defaults[$rl['name']] ?? 90);
            $display_name = $rl['display_name'] !== '' ? $rl['display_name'] : $rl['name'];
            echo "
                        <div class='col-3'>
                            <div class='form-group'>
                                <label>" . $escaper->escapeHtml($display_name) . " (" . $escaper->escapeHtml($lang['days']) . ") :</label>
                                <input type='number' name='{$setting_name}' id='{$setting_name}'
                                       min='1' max='3650' step='1'
                                       value='" . $escaper->escapeHtml($current_val) . "'
                                       class='form-control'/>
                            </div>
                        </div>
            ";
        }
    ?>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <button type="submit" name="update_sla_thresholds" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Update']); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="tab-pane" id="classic-risk-formula" role="tabpanel" aria-labelledby="classic-risk-formula-tab">
            <div class="card-body my-2 border">
    <?php 
                create_risk_formula_table(); 
    ?>
            </div>
        </div> 
        <div class="tab-pane" id="contributing-risk-formula" role="tabpanel" aria-labelledby="contributing-risk-formula-tab">
            <div class="row">
                <div class="col-12">
    <?php
                    display_contributing_risk_formula();
    ?>
                </div>
            </div>
        </div>
        <div class="tab-pane" id="review-settings" role="tabpanel" aria-labelledby="reviewsettings-tab">
            <form name="review_settings" method="post" action="">
                <?php $review_levels = get_review_levels(); ?>
                <div class="card-body my-2 border">
                    <div class="row align-items-end" >
                        <div class="col-md-6 form-group">
                            <span>
                                <?= $escaper->escapeHtml($lang['IWantToReviewVeryHighRiskEvery']); ?>
                                <span class="editable"><?= $escaper->escapeHtml($review_levels[0]['value']); ?></span>
                                <input type="text" name="veryhigh" size="2" value="<?= $escaper->escapeHtml($review_levels[0]['value']); ?>" class="editable" style='display: none;'>
                                <?= $escaper->escapeHtml($lang['days']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-6 form-group">
                            <span>
                                <?= $escaper->escapeHtml($lang['IWantToReviewHighRiskEvery']); ?>
                                <span class="editable"><?= $escaper->escapeHtml($review_levels[1]['value']); ?></span>
                                <input type="text" name="high" size="2" value="<?= $escaper->escapeHtml($review_levels[1]['value']); ?>" class="editable" style='display: none;'>
                                <?= $escaper->escapeHtml($lang['days']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-6 form-group">
                            <span>
                                <?= $escaper->escapeHtml($lang['IWantToReviewMediumRiskEvery']); ?>
                                <span class="editable"><?= $escaper->escapeHtml($review_levels[2]['value']); ?></span>
                                <input type="text" name="medium" size="2" value="<?= $escaper->escapeHtml($review_levels[2]['value']); ?>" class="editable" style='display: none;'>
                                <?= $escaper->escapeHtml($lang['days']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-6 form-group">
                            <span>
                                <?= $escaper->escapeHtml($lang['IWantToReviewLowRiskEvery']); ?>
                                <span class="editable"><?= $escaper->escapeHtml($review_levels[3]['value']); ?></span>
                                <input type="text" name="low" size="2" value="<?= $escaper->escapeHtml($review_levels[3]['value']); ?>" class="editable" style='display: none;'>
                                <?= $escaper->escapeHtml($lang['days']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-6">
                            <span>
                                <?= $escaper->escapeHtml($lang['IWantToReviewInsignificantRiskEvery']); ?>
                                <span class="editable"><?= $escaper->escapeHtml($review_levels[4]['value']); ?></span>
                                <input type="text" name="insignificant" size="2" value="<?= $escaper->escapeHtml($review_levels[4]['value']); ?>" class="editable" style='display: none;'>
                                <?= $escaper->escapeHtml($lang['days']); ?>
                            </span>
                        </div>
                    </div>
                    <input type="hidden" value="review_settings" name="update_review_settings_input"/>
                </div>
            </form>
        </div>
    </div>
</div>
<script>

    function colourNameToHex(colour) {

        var colours = {"aliceblue":"#f0f8ff","antiquewhite":"#faebd7","aqua":"#00ffff","aquamarine":"#7fffd4","azure":"#f0ffff",
        "beige":"#f5f5dc","bisque":"#ffe4c4","black":"#000000","blanchedalmond":"#ffebcd","blue":"#0000ff","blueviolet":"#8a2be2","brown":"#a52a2a","burlywood":"#deb887",
        "cadetblue":"#5f9ea0","chartreuse":"#7fff00","chocolate":"#d2691e","coral":"#ff7f50","cornflowerblue":"#6495ed","cornsilk":"#fff8dc","crimson":"#dc143c","cyan":"#00ffff",
        "darkblue":"#00008b","darkcyan":"#008b8b","darkgoldenrod":"#b8860b","darkgray":"#a9a9a9","darkgreen":"#006400","darkkhaki":"#bdb76b","darkmagenta":"#8b008b","darkolivegreen":"#556b2f",
        "darkorange":"#ff8c00","darkorchid":"#9932cc","darkred":"#8b0000","darksalmon":"#e9967a","darkseagreen":"#8fbc8f","darkslateblue":"#483d8b","darkslategray":"#2f4f4f","darkturquoise":"#00ced1",
        "darkviolet":"#9400d3","deeppink":"#ff1493","deepskyblue":"#00bfff","dimgray":"#696969","dodgerblue":"#1e90ff",
        "firebrick":"#b22222","floralwhite":"#fffaf0","forestgreen":"#228b22","fuchsia":"#ff00ff",
        "gainsboro":"#dcdcdc","ghostwhite":"#f8f8ff","gold":"#ffd700","goldenrod":"#daa520","gray":"#808080","green":"#008000","greenyellow":"#adff2f",
        "honeydew":"#f0fff0","hotpink":"#ff69b4",
        "indianred ":"#cd5c5c","indigo":"#4b0082","ivory":"#fffff0","khaki":"#f0e68c",
        "lavender":"#e6e6fa","lavenderblush":"#fff0f5","lawngreen":"#7cfc00","lemonchiffon":"#fffacd","lightblue":"#add8e6","lightcoral":"#f08080","lightcyan":"#e0ffff","lightgoldenrodyellow":"#fafad2",
        "lightgrey":"#d3d3d3","lightgreen":"#90ee90","lightpink":"#ffb6c1","lightsalmon":"#ffa07a","lightseagreen":"#20b2aa","lightskyblue":"#87cefa","lightslategray":"#778899","lightsteelblue":"#b0c4de",
        "lightyellow":"#ffffe0","lime":"#00ff00","limegreen":"#32cd32","linen":"#faf0e6",
        "magenta":"#ff00ff","maroon":"#800000","mediumaquamarine":"#66cdaa","mediumblue":"#0000cd","mediumorchid":"#ba55d3","mediumpurple":"#9370d8","mediumseagreen":"#3cb371","mediumslateblue":"#7b68ee",
        "mediumspringgreen":"#00fa9a","mediumturquoise":"#48d1cc","mediumvioletred":"#c71585","midnightblue":"#191970","mintcream":"#f5fffa","mistyrose":"#ffe4e1","moccasin":"#ffe4b5",
        "navajowhite":"#ffdead","navy":"#000080",
        "oldlace":"#fdf5e6","olive":"#808000","olivedrab":"#6b8e23","orange":"#ffa500","orangered":"#ff4500","orchid":"#da70d6",
        "palegoldenrod":"#eee8aa","palegreen":"#98fb98","paleturquoise":"#afeeee","palevioletred":"#d87093","papayawhip":"#ffefd5","peachpuff":"#ffdab9","peru":"#cd853f","pink":"#ffc0cb","plum":"#dda0dd","powderblue":"#b0e0e6","purple":"#800080",
        "rebeccapurple":"#663399","red":"#ff0000","rosybrown":"#bc8f8f","royalblue":"#4169e1",
        "saddlebrown":"#8b4513","salmon":"#fa8072","sandybrown":"#f4a460","seagreen":"#2e8b57","seashell":"#fff5ee","sienna":"#a0522d","silver":"#c0c0c0","skyblue":"#87ceeb","slateblue":"#6a5acd","slategray":"#708090","snow":"#fffafa","springgreen":"#00ff7f","steelblue":"#4682b4",
        "tan":"#d2b48c","teal":"#008080","thistle":"#d8bfd8","tomato":"#ff6347","turquoise":"#40e0d0",
        "violet":"#ee82ee",
        "wheat":"#f5deb3","white":"#ffffff","whitesmoke":"#f5f5f5",
        "yellow":"#ffff00","yellowgreen":"#9acd32"};

        if (typeof colours[colour.toLowerCase()] != "undefined") {

            return colours[colour.toLowerCase()];

        }

        return colour;

    }


    // Risk Appetite slider — relocated from admin/settings.php. The ranges
    // array, slider min/max, and initial value come from the PHP-rendered
    // risk level configuration above.
    <?= "var ranges = " . json_encode($ranges) . ";"; ?>
    function handleValueInRange(value) {
        var index, len;
        for (index = 0, len = ranges.length; index < len; ++index) {
            if (ranges[index]['range'][0] <= value && ranges[index]['range'][1] >= value) {
                $("#risk_appetite_display").text(ranges[index]['display_name'] + " (" + value + ")");
                $("#risk_appetite").val(value);
                $("#risk_appetite_color").css('background-color', ranges[index]['color']);

                return;
            }
        }
    }

    $(document).ready(function() {

        $("#slider").slider({
            value: <?= $risk_appetite * 10; ?>,
            min: 0,
    <?php
        if ($need_risk_score_normalization == "true") {
            echo "
            max: 100,
            ";
        } else {
            echo "
            max: " . $max_risk * 10 . ",
            ";
        }
    ?>
            step: 1,
            create: function() {
                handleValueInRange($("#slider").slider("value") / 10);
            },
            slide: function(event, ui) {
                handleValueInRange(ui.value / 10);
            }
        });

        $("#scoring input.editable").change(function() {

            //saving it so it can be referenced from the AJAX callbacks
            var _this = $(this);
            var level = _this.parent().data("level");
            var field = _this.data("field");
            var value = _this.val();

            $.ajax({
                type: "POST",
                url: "../api/risklevel/update",
                data: {
                    level: level,
                    field: field,
                    value: value
                },
                success: function(data) {
                    $("input.editable").trigger("blur");
                    if (data.status_message) {
                        showAlertsFromArray(data.status_message);
                    }
                },
                error: function(xhr,status,error) {
                    if (!retryCSRF(xhr, this)) {
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                },
                complete: function(xhr,status) {
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        // If there\'s data returned set it back to the label
                        _this.parent().find("span.editable").text(xhr.responseJSON.data);
                        // and to the input
                        _this.val(xhr.responseJSON.data);
                    }
                }
            });
        });

        //The color values are stored in '.level-colorpicker' when the page is rendered.
        //Display those colors in color pickers using the values stored in '.level-colorpicker'.
        $(".color-picker").each(function() {

            //Get the color values.
            let inp = $(this).parent().find(".level-colorpicker");

            //Get the Hex color values since the values assigned to HTML5 colorpicker must be HEX
            let color = colourNameToHex(inp.val());

            //Display colors.
            $(this).val(color);

        });

        $(".color-picker").change(function() {

            //Store the color values in '.level-colorpicker'
            let inp = $(this).parent().find(".level-colorpicker");
            inp.val($(this).val());

            //Update color values
            inp.trigger("change");

        });

        // Review Settings tab — editable-cell behaviour for the per-risk-level
        // review-cadence inputs. Relocated from admin/review_settings.php
        // and scoped to #review-settings so it does not collide with the
        // Scoring tab's #scoring-scoped editable handler above.
        function reviewSettingsResizable(el, factor) {
            var int = Number(factor) || 7.6;
            function resize() { el.width((el.val().length + 1) * int); }
            var e = ["keyup", "keypress", "focus", "blur", "change"];
            for (var i in e)
                el.on(e[i], resize);
            resize();
        }

        $("#review-settings input.editable").each(function() {
            reviewSettingsResizable($(this));
        });

        $("#review-settings").on("click", "span.editable", function() {
            $(this).hide();
            $(this).parent().find("input").show().select();
        });

        $("#review-settings").on("blur", "input.editable", function() {
            if (!$(this).val()) return false;
            var label = $(this).parent().find("span.editable");
            $(this).hide();
            label.text($(this).val());
            label.show();
            $("[name='review_settings']").submit();
        });

        $("#review-settings input.editable").change(function() {
            $("[name='review_settings']").submit();
        });
    });
</script>
<script>
	<?php prevent_form_double_submit_script(['contributing_risk_form']); ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>