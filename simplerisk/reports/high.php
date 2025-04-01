<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['datatables', 'chart.js'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'HighRiskReport');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    $score_used = 'inherent';
    if (!empty($_GET['score_used']) && $_GET['score_used'] === 'residual') {
        $score_used = 'residual';
    }

    $next_review_date_uses = get_setting('next_review_date_uses');

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="row mt-2">
            <div class="col-6">
                <div class="card-body border h-100">
                    <label><?= $escaper->escapeHtml($lang['RiskScoreUsing']); ?></label>
                    <form method="GET">
                        <select name="score_used" id="score_used_dropdown" class="form-field form-select" onchange="submit();">
                            <option value='inherent' name="" <?php if ($score_used !== "residual") echo 'selected'; ?>><?= $escaper->escapeHtml($lang['InherentRisk']);?></option>
                            <option value='residual' <?php if ($score_used === "residual") echo 'selected'; ?>><?= $escaper->escapeHtml($lang['ResidualRisk']);?></option>
                        </select>
                    </form>
    <?php
        $open = get_open_risks();
        $high = get_risk_count_of_risk_level('High', $score_used);
        $veryhigh = get_risk_count_of_risk_level('Very High', $score_used);

        // If there are open risks
        if ($open != 0) {
            $highpercent = 100*($high/$open);
            $veryhighpercent = 100*($veryhigh/$open);
        } else {
            $highpercent = 0;
            $veryhighpercent = 0;
        }

        echo "
                    <h3 class='mt-4'>{$escaper->escapeHtml(_lang('NumberOfOpenRisks', ['number' => $open], false))}</h3>
        ";

        // If we have very high risks
        if ($veryhigh > 0) {

            $display_name = get_risk_level_display_name('Very High');

            echo "
                    <h3>{$escaper->escapeHtml(_lang('RiskNumberOfRiskLevel', ['display_name' => $display_name, 'number' => $veryhigh], false))}</h3>
                    <h3>{$escaper->escapeHtml(_lang('RiskPercentageOfRiskLevel', ['display_name' => $display_name, 'percentage' => round($veryhighpercent, 2)], false))}</h3>
            ";
        }

        // If we have high risks
        if ($high > 0) {

            $display_name = get_risk_level_display_name('High');

            echo "
                    <h3>{$escaper->escapeHtml(_lang('RiskNumberOfRiskLevel', ['display_name' => $display_name, 'number' => $high], false))}</h3>
                    <h3>{$escaper->escapeHtml(_lang('RiskPercentageOfRiskLevel', ['display_name' => $display_name, 'percentage' => round($highpercent, 2)], false))}</h3>
            ";
        }
    ?>
                </div>
            </div>
            <div class="col-6">
                <div class="card-body border">
    <?php 
                    open_risk_level_pie($escaper->escapeHtml($lang['RiskLevel']), "open_risk_level_pie", false, $score_used); 
    ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
    <?php
        // Display the warning when the selected score is not matching with the 'next_review_date_uses' setting
        if (($next_review_date_uses === 'ResidualRisk' ? 'residual' : 'inherent') !== $score_used) {
            $warning = _lang('HighRiskReport_ScoreWarning', [
                'score_used' => $lang[$score_used === 'inherent' ? 'InherentRisk' : 'ResidualRisk'],
                'next_review_date_uses_name' => $lang['NextReviewDateUses'],
                'management_review_header' => $lang['ManagementReview'],
                'next_review_date_uses_value' => $lang[$next_review_date_uses]
            ], false);
            echo "
                <div class='card-body border mt-2'>
                    <div class='text-danger'><strong>{$escaper->escapeHtml($warning)}</strong></div>
                </div>
            ";
        }
    ?>
            </div>
        </div>
        <div class="row my-2">
            <div class="col-12">
                <div class="card-body border">
    <?php 
                    get_high_risk_report_table(); 
    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>