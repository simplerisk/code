<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    render_header_and_sidebar(['chart.js'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'RiskAdvice');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));
    require_once(realpath(__DIR__ . '/../includes/analysis.php'));

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <h4 class="mb-0"><?= $escaper->escapeHtml($lang['RiskDistribution']); ?></h4>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card-body border">
    <?php 
                    open_risk_level_pie($escaper->escapeHtml($lang['InherentRisk']), "open_inherent_risk_level_pie", false, "inherent"); 
    ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-body border">
    <?php 
                    open_risk_level_pie($escaper->escapeHtml($lang['ResidualRisk']), "open_residual_risk_level_pie", false, "residual"); 
    ?>
                </div>
            </div>
        </div>
        <div class="card-body my-2 border">
            <strong><?= $escaper->escapeHtml($lang['RiskDistributionDescription']); ?></strong>
        </div>
    </div>
    <div class="col-12">
        <div class="card-body mb-2 border">
            <h4><?= $escaper->escapeHtml($lang['RiskAdvice']); ?></h4>
    <?php 
            get_risk_advice(); 
    ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>