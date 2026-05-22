<?php
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js'], ['check_riskmanagement' => true], active_sidebar_submenu: 'Reporting_Reports', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'MeanTimeToRemediate');

    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    $by_team     = get_mttr_by_team();
    $by_category = get_mttr_by_category();
    $by_level    = get_mttr_by_risk_level();

    // --- By Team ---
    $team_labels   = array_map(fn($r) => $r['label'], $by_team);
    $team_data     = array_map(fn($r) => (float)$r['avg_days'], $by_team);
    $team_datasets = [[
        'label'           => $lang['AverageDaysToClose'],
        'data'            => $team_data,
        'backgroundColor' => '#4472C4',
    ]];

    // --- By Category ---
    $cat_labels   = array_map(fn($r) => $r['label'], $by_category);
    $cat_data     = array_map(fn($r) => (float)$r['avg_days'], $by_category);
    $cat_datasets = [[
        'label'           => $lang['AverageDaysToClose'],
        'data'            => $cat_data,
        'backgroundColor' => '#ED7D31',
    ]];

    // --- By Risk Level ---
    $level_labels   = array_map(fn($r) => $r['label'], $by_level);
    $level_data     = array_map(fn($r) => (float)$r['avg_days'], $by_level);
    $level_datasets = [[
        'label'           => $lang['AverageDaysToClose'],
        'data'            => $level_data,
        'backgroundColor' => '#A9D18E',
    ]];
?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body border my-2">
            <strong><?= $escaper->escapeHtml($lang['MeanTimeToRemediateDescription']); ?></strong>

            <?php if (empty($by_team) && empty($by_category) && empty($by_level)): ?>
                <div class="alert alert-info mt-3"><?= $escaper->escapeHtml($lang['NoRisksAvailable'] ?? 'No closed risks found.'); ?></div>
            <?php else: ?>

            <?php if (!empty($by_team)): ?>
            <div class="mt-4">
                <?php
                    create_chartjs_bar_code(
                        $lang['ByTeam'] ?? 'By Team',
                        'mttr_team_chart',
                        $team_labels,
                        $team_datasets,
                        $lang['Team'],
                        $lang['AverageDaysToClose']
                    );
                ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($by_category)): ?>
            <div class="mt-4">
                <?php
                    create_chartjs_bar_code(
                        $lang['ByCategory'] ?? 'By Category',
                        'mttr_category_chart',
                        $cat_labels,
                        $cat_datasets,
                        $lang['Category'],
                        $lang['AverageDaysToClose']
                    );
                ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($by_level)): ?>
            <div class="mt-4">
                <?php
                    create_chartjs_bar_code(
                        $lang['ByRiskLevel'] ?? 'By Risk Level',
                        'mttr_level_chart',
                        $level_labels,
                        $level_datasets,
                        $lang['RiskLevel'],
                        $lang['AverageDaysToClose']
                    );
                ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>
<?php render_footer(); ?>
