<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

render_header_and_sidebar(['chart.js']);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/reporting.php'));
require_once(realpath(__DIR__ . '/../includes/analysis.php'));

?>
<div class="row bg-white">
    <div class="col-12">
       <div class="card-body my-2 border">
            <div class="row">
                <h4><?php echo $escaper->escapeHtml($lang['RiskDistribution']); ?></h4>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <?php open_risk_level_pie($escaper->escapeHtml($lang['InherentRisk']), "open_inherent_risk_level_pie", false, "inherent"); ?>
                </div>
                <div class="col-md-6">
                    <?php open_risk_level_pie($escaper->escapeHtml($lang['ResidualRisk']), "open_residual_risk_level_pie", false, "residual"); ?>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <?php echo $escaper->escapeHtml($lang['RiskDistributionDescription']); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card-body mb-2 border">
            <h4><?php echo $escaper->escapeHtml($lang['RiskAdvice']); ?></h4>
            <?php get_risk_advice(); ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>