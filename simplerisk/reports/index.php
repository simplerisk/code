<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));

// The IM Extra contributes its own reports to the hub. When the Extra
// is enabled, im_reporting is a valid gate; otherwise the perm is
// irrelevant and we keep it off the list so a user with a stale
// im_reporting session value can't land on an empty hub.
//
// view_exception is included because at least one catalog tile
// (connectivity_visualizer) is reachable by a view_exception-only caller
// (reports_catalog.php's 'permissions.require' for that entry, kept in sync
// with connectivity_visualizer.php's own page gate -- see that file's
// comment). Without it here, a view_exception-only caller is redirected
// away from this hub shell before the catalog ever loads, so the tile they
// are otherwise entitled to see is unreachable by normal navigation.
$hub_required = ['riskmanagement', 'asset', 'governance', 'compliance', 'view_exception'];
if (incident_management_extra()) {
    $hub_required[] = 'im_reporting';
}

render_header_and_sidebar(
    ['blockUI', 'CUSTOM:reports-hub.js'],
    ['check_any_of' => $hub_required],
    active_sidebar_menu: 'Reporting',
    active_sidebar_submenu: 'Reporting_Reports',
    required_localization_keys: [
        'LoadingReports', 'FailedToLoadReports', 'Retry',
        'AddToFavorites', 'RemoveFromFavorites', 'Favorites',
        'RiskManagement', 'Compliance', 'Governance', 'AssetManagement', 'IncidentManagement',
        'All', 'NoReportsMatch', 'ClearFilters',
        'Cards', 'List',
        'Reports', 'Dashboards',
    ]
);

global $escaper, $lang;

?>

<div class="hub" data-hub-kind="report">
    <div class="hub__controls">
        <div class="hub__search">
            <input type="search"
                   placeholder="<?= $escaper->escapeHtmlAttr($lang['SearchReports']) ?>"
                   aria-label="<?= $escaper->escapeHtmlAttr($lang['SearchReports']) ?>" />
        </div>
        <div class="hub__chips" role="group">
            <button type="button" data-chip="all"            aria-pressed="true"><?= $escaper->escapeHtml($lang['All']) ?></button>
            <button type="button" data-chip="favorites"      aria-pressed="false">★ <?= $escaper->escapeHtml($lang['Favorites']) ?></button>
            <button type="button" data-chip="riskmanagement" aria-pressed="false"><?= $escaper->escapeHtml($lang['RiskManagement']) ?></button>
            <button type="button" data-chip="compliance"     aria-pressed="false"><?= $escaper->escapeHtml($lang['Compliance']) ?></button>
            <button type="button" data-chip="governance"     aria-pressed="false"><?= $escaper->escapeHtml($lang['Governance']) ?></button>
            <button type="button" data-chip="asset"          aria-pressed="false"><?= $escaper->escapeHtml($lang['AssetManagement']) ?></button>
<?php if (incident_management_extra() && check_permission('im_reporting')): ?>
            <button type="button" data-chip="incident_management" aria-pressed="false"><?= $escaper->escapeHtml($lang['IncidentManagement']) ?></button>
<?php endif; ?>
        </div>
        <div class="hub__view-toggle" role="group" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cards']) ?> / <?= $escaper->escapeHtmlAttr($lang['List']) ?>">
            <button type="button" data-view="cards" aria-pressed="true"  aria-label="<?= $escaper->escapeHtmlAttr($lang['Cards']) ?>" title="<?= $escaper->escapeHtmlAttr($lang['Cards']) ?>"><i class="fas fa-th-large" aria-hidden="true"></i></button>
            <button type="button" data-view="list"  aria-pressed="false" aria-label="<?= $escaper->escapeHtmlAttr($lang['List']) ?>"  title="<?= $escaper->escapeHtmlAttr($lang['List']) ?>"><i class="fas fa-list" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="hub__main"></div>

</div>

<?php render_footer();
