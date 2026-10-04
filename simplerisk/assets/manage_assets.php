<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Manage assets (asset management redesign, Phase A, Task 9): one page with
    // two tabs, Assets and Asset groups. A thin shell -- the table, the Columns
    // picker, filters, selection and bulk actions are rendered client-side by
    // js/simplerisk/pages/manage-assets.js from the v2 API (GET /assets,
    // GET/PUT /assets/column-settings, POST /assets/bulk). No table rows are
    // printed here. The Asset groups tab body is Task 10.
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    // Standard shell breadcrumb (landing shape: the crumb is not duplicated).
    $breadcrumb_title_key = $active_sidebar_submenu = 'ManageAssets';
    $active_sidebar_menu = 'AssetManagement';

    // The asset record modal (view / edit / add) is the Cards engines
    // (risk-details-form.js / risk-details-view.js on 'gridstack') driven by
    // the asset profile (common/asset-card-profile.js). 'WYSIWYG' /
    // 'multiselect' / 'selectize' / 'datetimerangepicker' back its widgets
    // (details editor, team/location/risk pickers, tags, custom date fields);
    // sr-faceted-picker + pages/risk-mitigation-controls.js the Mapped
    // controls picker. sr-select and sr-row-actions-menu are the shared
    // design-system widgets (filter selects, row overflow menu).
    render_header_and_sidebar(
        ['blockUI', 'selectize', 'datatables', 'colreorder', 'WYSIWYG', 'multiselect', 'datetimerangepicker', 'gridstack', 'CUSTOM:common.js', 'CUSTOM:sr-select.js', 'CUSTOM:sr-row-actions-menu.js', 'CUSTOM:sr-faceted-picker.js', 'CUSTOM:common/risk-details-form.js', 'CUSTOM:common/risk-details-view.js', 'CUSTOM:pages/risk-mitigation-controls.js', 'CUSTOM:common/asset-card-profile.js', 'CUSTOM:pages/asset-record-modal.js', 'CUSTOM:pages/manage-assets.js', 'CUSTOM:pages/manage-asset-groups.js', 'CUSTOM:pages/manage-asset-discovery.js'],
        ['check_assets' => true],
        $breadcrumb_title_key,
        $active_sidebar_menu,
        $active_sidebar_submenu
    );

    require_once(realpath(__DIR__ . '/../includes/assets.php'));
    require_once(realpath(__DIR__ . '/../includes/assets_page.php'));

    // Capabilities + id/label lookups for the client (raw values; the client
    // inserts every one as text). $_GET['tab'] only picks the opening tab.
    $manage_assets_config = asset_manage_page_config($_GET['tab'] ?? null);
    $caps = $manage_assets_config['caps'];
?>
<script>
    <?php // @phan-suppress-next-line SecurityCheck-XSS -- JSON for a <script> block, encoded with JSON_HEX_TAG|AMP|APOS|QUOT; the client inserts every value as text, never HTML ?>
    window.manageAssetsConfig = <?= json_encode($manage_assets_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<!-- No .row/.col-12 wrapper: the card is a DIRECT child of .content so the
     shared `.content:has(> .sr-table-card)` rule puts it on the gray ground. -->
<!-- sr-dt-fold-compact: the shared compact tier (_tables.scss) at every
     width, so row actions are always the ⋯ overflow menu (spec D6) and the
     name column takes the slack. -->
<div class="sr-table-card sr-dt-fold-compact" id="manage-assets-card">
    <!-- Tab row: the tabs on the left, the ACTIVE tab's create actions on the
         right (design-system 6, tab-row actions). Only the active tab's set is
         shown, so exactly one red button is ever on screen; the toolbar below
         keeps only the controls that shape the table. -->
    <div class="sr-assets-tabbar" id="manage-assets-tabbar">
        <div class="sr-tabs" id="manage-assets-tabs" role="tablist">
            <button type="button" class="sr-tab is-active" role="tab" id="manage-assets-tab-assets" data-tab="assets" aria-selected="true" tabindex="0" aria-controls="manage-assets-panel">
                <?= $escaper->escapeHtml($lang['Assets']) ?>
                <span class="sr-tab-count" id="manage-assets-tab-count-assets"></span>
            </button>
            <button type="button" class="sr-tab" role="tab" id="manage-assets-tab-groups" data-tab="groups" aria-selected="false" tabindex="-1" aria-controls="asset-groups-panel">
                <?= $escaper->escapeHtml($lang['AssetGroups']) ?>
                <span class="sr-tab-count" id="manage-assets-tab-count-groups"><?= (int)$manage_assets_config['groupCount'] ?></span>
            </button>
        </div>
        <?php
            // Built and escaped by asset_manage_tab_actions_html() (includes/assets_page.php).
            echo asset_manage_tab_actions_html($manage_assets_config);
        ?>
    </div>

    <div id="manage-assets-panel" role="tabpanel" aria-labelledby="manage-assets-tab-assets">
        <span class="visually-hidden" id="manage-assets-chip-live" role="status" aria-live="polite"></span>
        <div class="sr-table-toolbar" id="manage-assets-toolbar">
            <div class="sr-table-status-filter" id="manage-assets-status-filter" role="group" aria-label="<?= $escaper->escapeHtmlAttr($lang['Status']) ?>">
                <button type="button" class="sr-status-chip active" data-status="all" aria-pressed="true"><?= $escaper->escapeHtml($lang['All']) ?> <span class="n" id="manage-assets-count-all"></span></button>
                <button type="button" class="sr-status-chip" data-status="verified" aria-pressed="false"><?= $escaper->escapeHtml($lang['Verified']) ?> <span class="n" id="manage-assets-count-verified"></span></button>
                <button type="button" class="sr-status-chip sr-status-chip--attn" data-status="unverified" aria-pressed="false"><?= $escaper->escapeHtml($lang['Unverified']) ?> <span class="n" id="manage-assets-count-unverified"></span></button>
            </div>
            <div class="sr-table-tools">
                <div class="dt-search">
                    <input type="search" id="manage-assets-search" class="form-control" autocomplete="off" placeholder="<?= $escaper->escapeHtmlAttr($lang['SearchAssetsPlaceholder']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['SearchAssetsPlaceholder']) ?>">
                </div>
                <button type="button" class="sr-qf-toggle" id="manage-assets-filters-toggle" aria-expanded="false" aria-controls="manage-assets-quickfilters">
                    <i class="fa fa-filter" aria-hidden="true"></i><span><?= $escaper->escapeHtml($lang['Filters']) ?></span>
                    <span class="sr-qf-toggle-count" id="manage-assets-filters-count" hidden></span>
                </button>
                <div class="colpicker">
                    <button type="button" class="filterbtn sr-table-filter" id="manage-assets-colpicker-btn" aria-haspopup="true" aria-expanded="false" aria-controls="manage-assets-colpanel">
                        <i class="fa fa-table-columns" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['Columns']) ?>
                    </button>
                    <div class="colpanel colpanel-searchable d-none" id="manage-assets-colpanel"></div>
                </div>
            </div>
        </div>

        <div class="sr-bulk-bar d-none" id="manage-assets-bulk-bar">
            <button type="button" class="sr-bulk-clear" id="manage-assets-bulk-clear" data-action="bulk-clear" aria-label="<?= $escaper->escapeHtmlAttr($lang['Clear']) ?>">&times;</button>
            <span class="sr-bulk-count" id="manage-assets-bulk-count" aria-live="polite"></span>
            <button type="button" class="sr-bulk-lnk d-none" id="manage-assets-select-all-filtered" data-action="select-all-filtered"></button>
            <div class="sr-bulk-actions">
<?php if ($caps['can_verify']) { ?>
                <button type="button" class="btn btn-sm" id="manage-assets-bulk-verify" data-action="bulk-verify"><?= $escaper->escapeHtml($lang['Verify']) ?></button>
<?php } ?>
<?php if ($caps['can_edit']) { ?>
                <button type="button" class="btn btn-sm" id="manage-assets-bulk-assign-teams" data-action="bulk-assign-teams"><?= $escaper->escapeHtml($lang['AssetBulkAssignTeams']) ?></button>
<?php } ?>
<?php if ($caps['can_group_edit'] || $caps['can_group_create']) { ?>
                <button type="button" class="btn btn-sm" id="manage-assets-bulk-add-to-group" data-action="bulk-add-to-group"><?= $escaper->escapeHtml($lang['AssetBulkAddToGroup']) ?></button>
<?php } ?>
<?php if ($caps['can_delete']) { ?>
                <button type="button" class="btn btn-sm" id="manage-assets-bulk-delete" data-action="bulk-delete"><?= $escaper->escapeHtml($lang['Delete']) ?></button>
<?php } ?>
            </div>
        </div>

        <div class="sr-table-quickfilters d-none" id="manage-assets-quickfilters">
            <div class="sr-qf-selects">
                <select id="manage-assets-team-filter" class="form-select" multiple data-filter="team" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllTeams']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Team']) ?>"></select>
                <select id="manage-assets-location-filter" class="form-select" multiple data-filter="location" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllLocations']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['SiteLocation']) ?>"></select>
                <select id="manage-assets-tag-filter" class="form-select" multiple data-filter="tag" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllTags']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Tags']) ?>"></select>
                <select id="manage-assets-group-filter" class="form-select" multiple data-filter="group" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllAssetGroups']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['AssetGroups']) ?>"></select>
                <select id="manage-assets-valuation-filter" class="form-select" multiple data-filter="valuation" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllValuations']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['AssetValuation']) ?>"></select>
<?php if (!empty($manage_assets_config['lookups']['scoring_enabled'])) { ?>
                <select id="manage-assets-confidentiality-filter" class="form-select" multiple data-filter="confidentiality" data-caption="<?= $escaper->escapeHtmlAttr($lang['Confidentiality']) ?>" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllConfidentialityRatings']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Confidentiality']) ?>"></select>
                <select id="manage-assets-integrity-filter" class="form-select" multiple data-filter="integrity" data-caption="<?= $escaper->escapeHtmlAttr($lang['Integrity']) ?>" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllIntegrityRatings']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Integrity']) ?>"></select>
                <select id="manage-assets-availability-filter" class="form-select" multiple data-filter="availability" data-caption="<?= $escaper->escapeHtmlAttr($lang['Availability']) ?>" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllAvailabilityRatings']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Availability']) ?>"></select>
                <select id="manage-assets-categorization-filter" class="form-select" multiple data-filter="categorization" data-caption="<?= $escaper->escapeHtmlAttr($lang['FIPSCategorization']) ?>" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllCategorizations']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['FIPSCategorization']) ?>"></select>
                <select id="manage-assets-band-filter" class="form-select" multiple data-filter="band" data-caption="<?= $escaper->escapeHtmlAttr($lang['WeightedBand']) ?>" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllBands']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['WeightedBand']) ?>"></select>
<?php } ?>
                <button type="button" class="btn btn-link btn-sm sr-qf-clear d-none" id="manage-assets-filters-clear" data-action="clear-filters"><?= $escaper->escapeHtml($lang['ClearFilters']) ?></button>
            </div>
        </div>

        <div class="sr-table-scroll">
            <!-- <th>s are built by manage-assets.js from the saved column
                 settings (GET /assets/column-settings) before DataTables
                 initializes; every configurable <th>/<td> carries data-col. -->
            <table id="manage-assets-table" class="sr-table" width="100%">
                <thead><tr></tr></thead>
                <tbody></tbody>
            </table>
        </div>
<?php if ($manage_assets_config['discoveryEnabled']) { ?>

        <!-- Discovery runs (Task 11): recent background discovery runs,
             rendered by manage-asset-discovery.js from GET
             /assets/discovery-runs. Hidden until there is at least one run;
             polled only while a run is queued or running. -->
        <section class="sr-assets-runs d-none" id="asset-discovery-runs">
            <h2 class="sr-assets-runs-head">
                <button type="button" class="sr-assets-runs-toggle" id="asset-discovery-runs-toggle" aria-expanded="false" aria-controls="asset-discovery-runs-body">
                    <i class="fa fa-chevron-right sr-assets-runs-caret" aria-hidden="true"></i>
                    <span><?= $escaper->escapeHtml($lang['DiscoveryRuns']) ?></span>
                    <span class="sr-tab-count" id="asset-discovery-runs-count"></span>
                </button>
            </h2>
            <div id="asset-discovery-runs-body" hidden>
                <div class="sr-table-scroll">
                    <table class="sr-table sr-assets-runs-table" id="asset-discovery-runs-table">
                        <thead><tr>
                            <th class="dt-head-left"><?= $escaper->escapeHtml($lang['IPRange']) ?></th>
                            <th class="dt-head-left"><?= $escaper->escapeHtml($lang['Status']) ?></th>
                            <th class="sr-assets-runs-num"><?= $escaper->escapeHtml($lang['DiscoveryLiveHosts']) ?></th>
                            <th class="sr-assets-runs-num"><?= $escaper->escapeHtml($lang['DiscoveryNewAssets']) ?></th>
                            <th class="dt-head-left sr-assets-runs-opt"><?= $escaper->escapeHtml($lang['DiscoveryStartedAt']) ?></th>
                            <th class="dt-head-left sr-assets-runs-opt"><?= $escaper->escapeHtml($lang['StartedBy']) ?></th>
                            <th class="sr-actions-col"><span class="visually-hidden"><?= $escaper->escapeHtml($lang['Actions']) ?></span></th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
<?php } ?>
    </div>

    <!-- Asset groups tab (Task 10): rows, the member drawer and the group
         modals are driven by js/simplerisk/pages/manage-asset-groups.js from
         GET /asset-groups (paged, with aggregates) and
         GET /asset-groups/{id}/assets (paged members). -->
    <div id="asset-groups-panel" class="d-none" role="tabpanel" aria-labelledby="manage-assets-tab-groups">
        <span class="visually-hidden" id="asset-groups-chip-live" role="status" aria-live="polite"></span>
        <div class="sr-table-toolbar" id="asset-groups-toolbar">
            <div class="sr-table-tools">
                <div class="dt-search">
                    <input type="search" id="asset-groups-search" class="form-control" autocomplete="off" placeholder="<?= $escaper->escapeHtmlAttr($lang['SearchAssetGroupsPlaceholder']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['SearchAssetGroupsPlaceholder']) ?>">
                </div>
                <button type="button" class="sr-qf-toggle" id="asset-groups-filters-toggle" aria-expanded="false" aria-controls="asset-groups-quickfilters">
                    <i class="fa fa-filter" aria-hidden="true"></i><span><?= $escaper->escapeHtml($lang['Filters']) ?></span>
                    <span class="sr-qf-toggle-count" id="asset-groups-filters-count" hidden></span>
                </button>
                <div class="colpicker">
                    <button type="button" class="filterbtn sr-table-filter" id="asset-groups-colpicker-btn" aria-haspopup="true" aria-expanded="false" aria-controls="asset-groups-colpanel">
                        <i class="fa fa-table-columns" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['Columns']) ?>
                    </button>
                    <div class="colpanel colpanel-searchable d-none" id="asset-groups-colpanel"></div>
                </div>
            </div>
        </div>

<?php if ($caps['can_group_delete']) { ?>
        <!-- Bulk bar (Manage assets' shape): replaces the toolbar while groups
             are selected. Only for asset_group_delete holders, as are the
             selection column and POST /asset-groups/bulk. -->
        <div class="sr-bulk-bar d-none" id="asset-groups-bulk-bar">
            <button type="button" class="sr-bulk-clear" id="asset-groups-bulk-clear" data-action="bulk-clear" aria-label="<?= $escaper->escapeHtmlAttr($lang['Clear']) ?>">&times;</button>
            <span class="sr-bulk-count" id="asset-groups-bulk-count" aria-live="polite"></span>
            <button type="button" class="sr-bulk-lnk d-none" id="asset-groups-select-all-filtered" data-action="select-all-filtered"></button>
            <div class="sr-bulk-actions">
                <button type="button" class="btn btn-sm" id="asset-groups-bulk-delete" data-action="bulk-delete"><?= $escaper->escapeHtml($lang['Delete']) ?></button>
            </div>
        </div>
<?php } ?>

        <!-- Group filters: a group matches a Team / Site/Location / Tag when a
             member the viewer can see carries it, and a categorization / band
             through its highest result (an unscored group never matches). -->
        <div class="sr-table-quickfilters d-none" id="asset-groups-quickfilters">
            <div class="sr-qf-selects">
                <select id="asset-groups-team-filter" class="form-select" multiple data-filter="team" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllTeams']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Team']) ?>"></select>
                <select id="asset-groups-location-filter" class="form-select" multiple data-filter="location" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllLocations']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['SiteLocation']) ?>"></select>
                <select id="asset-groups-tag-filter" class="form-select" multiple data-filter="tag" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllTags']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Tags']) ?>"></select>
<?php if (!empty($manage_assets_config['lookups']['scoring_enabled'])) { ?>
                <select id="asset-groups-categorization-filter" class="form-select" multiple data-filter="categorization" data-caption="<?= $escaper->escapeHtmlAttr($lang['HighestFIPSCategorization']) ?>" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllCategorizations']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['HighestFIPSCategorization']) ?>"></select>
                <select id="asset-groups-band-filter" class="form-select" multiple data-filter="band" data-caption="<?= $escaper->escapeHtmlAttr($lang['HighestWeightedBand']) ?>" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AllBands']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['HighestWeightedBand']) ?>"></select>
<?php } ?>
                <button type="button" class="btn btn-link btn-sm sr-qf-clear d-none" id="asset-groups-filters-clear" data-action="clear-filters"><?= $escaper->escapeHtml($lang['ClearFilters']) ?></button>
            </div>
        </div>
        <div class="sr-table-scroll">
            <!-- <th>s are built by manage-asset-groups.js from the saved column
                 settings (GET /asset-groups/column-settings) before DataTables
                 initializes; every configurable <th>/<td> carries data-col. -->
            <table id="asset-groups-table" class="sr-table" width="100%">
                <thead><tr></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($caps['can_group_create'] || $caps['can_group_edit']) { ?>
<!-- Add / Edit group (design-system §8 shell). Members is the faceted asset
     picker's field at rest: the hidden <select multiple> is the value, the
     chips box is its UI (design-system §5). -->
<div class="modal fade sr-modal" id="asset-group-modal" tabindex="-1" aria-hidden="true" aria-labelledby="asset-group-modal-title">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="asset-group-form" novalidate autocomplete="off">
                <div class="modal-header">
                    <span class="sr-modal-icon"><i class="fa fa-layer-group" aria-hidden="true"></i></span>
                    <h4 class="modal-title" id="asset-group-modal-title"></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
                </div>
                <div class="modal-body">
                    <section class="sr-qcard"><div class="sr-qcard-body">
                        <div class="sr-qfield sr-qfield--full">
                            <label class="sr-qlabel" for="asset-group-name"><?= $escaper->escapeHtml($lang['Name']) ?> <span class="sr-req" aria-hidden="true">*</span></label>
                            <input type="text" id="asset-group-name" class="form-control" maxlength="100" required aria-describedby="asset-group-name-error">
                            <div class="invalid-feedback" id="asset-group-name-error"></div>
                        </div>
                    </div></section>
                    <section class="sr-qcard"><div class="sr-qcard-body">
                        <div class="sr-qfield sr-qfield--full">
                            <label class="sr-qlabel" id="asset-group-members-label"><?= $escaper->escapeHtml($lang['AssetGroupMembers']) ?></label>
                            <select id="asset-group-members" class="sr-picker-value" multiple aria-labelledby="asset-group-members-label"></select>
                            <span class="sr-qhint"><?= $escaper->escapeHtml($lang['AssetGroupMembersHint']) ?></span>
                        </div>
                    </div></section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                    <button type="submit" class="btn btn-submit" id="asset-group-save" data-action="group-save"><?= $escaper->escapeHtml($lang['Save']) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Faceted asset picker (design-system §5, the shipped .sr-picker-* shell).
     Facets: team, site/location, valuation. The roster is searched and
     narrowed on the server (GET /assets?facet_counts=1), never loaded whole. -->
<div id="asset-picker" class="modal fade sr-modal sr-picker-modal" tabindex="-1" aria-hidden="true" aria-labelledby="asset-picker-title">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-server" aria-hidden="true"></i></span>
                <h5 class="modal-title" id="asset-picker-title"><?= $escaper->escapeHtml($lang['ChooseAssets']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
            </div>
            <div class="sr-picker-search">
                <i class="fa fa-magnifying-glass sr-picker-search-icon" aria-hidden="true"></i>
                <input type="text" id="asset-picker-search" class="sr-picker-search-input" autocomplete="off" placeholder="<?= $escaper->escapeHtmlAttr($lang['SearchAssetsPlaceholder']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['SearchAssetsPlaceholder']) ?>">
                <span class="sr-picker-scope" id="asset-picker-scope"></span>
            </div>
            <div class="sr-picker-panes" id="asset-picker-panes">
<?php foreach ([['team', 'Team', 'AllTeams'], ['location', 'SiteLocation', 'AllLocations'], ['valuation', 'Valuation', 'AllValuations']] as $i => [$facet, $label_key, $all_key]) { ?>
                <div class="sr-picker-pane sr-picker-pane--facet">
                    <div class="sr-picker-pane-head">
                        <span class="sr-picker-step"><?= $i + 1 ?></span>
                        <span><?= $escaper->escapeHtml($lang[$label_key]) ?></span>
                        <button type="button" class="sr-picker-clear" data-picker-clear="<?= $facet ?>"><?= $escaper->escapeHtml($lang['Clear']) ?></button>
                    </div>
                    <!-- Options are built by the page script from the page's lookups. -->
                    <div class="sr-picker-scroll" id="asset-picker-<?= $facet ?>" data-all-label="<?= $escaper->escapeHtmlAttr($lang[$all_key]) ?>"></div>
                </div>
<?php } ?>
                <div class="sr-picker-pane sr-picker-pane--list">
                    <div class="sr-picker-pane-head">
                        <span class="sr-picker-step">4</span>
                        <span><?= $escaper->escapeHtml($lang['Asset']) ?></span>
                        <span class="sr-picker-pane-count" id="asset-picker-count"></span>
                    </div>
                    <div class="sr-picker-scroll" id="asset-picker-list" role="listbox" aria-multiselectable="true" aria-label="<?= $escaper->escapeHtmlAttr($lang['Assets']) ?>"></div>
                </div>
                <div class="sr-picker-pane sr-picker-pane--selected">
                    <div class="sr-picker-pane-head">
                        <span><?= $escaper->escapeHtml($lang['Selected']) ?></span>
                        <span class="sr-picker-pane-count" id="asset-picker-selected-count"></span>
                    </div>
                    <div class="sr-picker-scroll sr-picker-selected" id="asset-picker-selected"></div>
                </div>
            </div>
            <div class="modal-footer sr-picker-foot">
                <span class="sr-picker-hint"><?= $escaper->escapeHtml($lang['PickerKeyboardHint']) ?></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                <button type="button" class="btn btn-submit" id="asset-picker-commit"><?= $escaper->escapeHtml($lang['UseTheseAssets']) ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($caps['can_group_delete']) { ?>
<!-- Delete group confirm (design-system §8 destructive pattern). -->
<div class="modal fade sr-modal" id="asset-group-delete-modal" tabindex="-1" aria-hidden="true" aria-labelledby="asset-group-delete-title" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon sr-modal-icon--danger"><i class="fa fa-trash" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="asset-group-delete-title"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
            </div>
            <div class="modal-body">
                <section class="sr-qcard"><div class="sr-qcard-body">
                    <div class="sr-qnote">
                        <i class="fa fa-circle-info sr-qnote-ico" aria-hidden="true"></i>
                        <span id="asset-group-delete-note"><?= $escaper->escapeHtml($lang['AssetGroupDeleteKeepsAssets']) ?></span>
                    </div>
                </div></section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal" id="asset-group-delete-cancel"><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                <button type="button" class="btn btn-danger" id="asset-group-delete-confirm" data-action="confirm-group-delete"><?= $escaper->escapeHtml($lang['DeleteAssetGroup']) ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($caps['can_delete']) { ?>
<!-- Delete confirm (design-system §8 destructive pattern): title names what
     is deleted, amber note, Cancel focused, Esc/backdrop disabled. -->
<div class="modal fade sr-modal" id="manage-assets-delete-modal" tabindex="-1" aria-hidden="true" aria-labelledby="manage-assets-delete-title" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon sr-modal-icon--danger"><i class="fa fa-trash" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="manage-assets-delete-title"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
            </div>
            <div class="modal-body">
                <section class="sr-qcard"><div class="sr-qcard-body">
                    <div class="sr-qnote">
                        <i class="fa fa-triangle-exclamation sr-qnote-ico" aria-hidden="true"></i>
                        <span><?= $escaper->escapeHtml($lang['DeleteCannotBeUndone']) ?></span>
                    </div>
                </div></section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal" id="manage-assets-delete-cancel"><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                <button type="button" class="btn btn-danger" id="manage-assets-delete-confirm" data-action="confirm-delete"></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($manage_assets_config['discoveryEnabled']) { ?>
<!-- Discover assets (design-system §8 form-in-modal). Starts a background
     run (POST /assets/discovery-runs); "Add new assets as" is informational --
     the server decides it from the requester's permission. -->
<div class="modal fade sr-modal" id="asset-discovery-modal" tabindex="-1" aria-hidden="true" aria-labelledby="asset-discovery-title" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="asset-discovery-form" novalidate autocomplete="off">
                <div class="modal-header">
                    <span class="sr-modal-icon"><i class="fa fa-network-wired" aria-hidden="true"></i></span>
                    <h4 class="modal-title" id="asset-discovery-title"><?= $escaper->escapeHtml($lang['DiscoverAssets']) ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
                </div>
                <div class="modal-body sr-qform">
                    <section class="sr-qcard"><div class="sr-qcard-body">
                        <!-- One .sr-qstack owns the dialog's vertical rhythm (design-system
                             §8: 12px between every field and note, whichever are shown). -->
                        <div class="sr-qstack">
                            <!-- Fail closed: without the config.php allowlist
                                 ($asset_discovery_allowed_ranges) nothing may be
                                 scanned; manage-asset-discovery.js shows this and
                                 disables Start when the capabilities say so. -->
                            <div class="sr-qnote" id="asset-discovery-not-configured" role="status" hidden>
                                <i class="fa fa-lock sr-qnote-ico" aria-hidden="true"></i>
                                <span><?= $escaper->escapeHtml($lang['DiscoveryNotConfigured']) ?></span>
                            </div>
                            <div class="sr-qfield sr-qfield--full">
                                <label class="sr-qlabel" for="asset-discovery-range"><?= $escaper->escapeHtml($lang['IPRange']) ?> <span class="sr-req" aria-hidden="true">*</span></label>
                                <input type="text" id="asset-discovery-range" class="form-control" maxlength="100" required spellcheck="false" aria-describedby="asset-discovery-range-hint asset-discovery-allowed asset-discovery-range-error">
                                <span class="sr-qhint" id="asset-discovery-range-hint"><?= $escaper->escapeHtml($lang['DiscoveryRangeHint']) ?></span>
                                <span class="sr-qhint" id="asset-discovery-allowed" hidden></span>
                                <div class="invalid-feedback" id="asset-discovery-range-error"></div>
                            </div>
                            <div class="sr-qswitches">
                                <div class="sr-qswitch-row">
                                    <div class="form-check form-switch sr-qswitch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="asset-discovery-resolve" checked>
                                    </div>
                                    <label class="sr-qswitch-text" for="asset-discovery-resolve">
                                        <span class="sr-qswitch-title"><?= $escaper->escapeHtml($lang['DiscoveryResolveNames']) ?></span>
                                    </label>
                                </div>
                            </div>
                            <div class="sr-qfield sr-qfield--full">
                                <span class="sr-qlabel" id="asset-discovery-add-as-label"><?= $escaper->escapeHtml($lang['DiscoveryAddAs']) ?></span>
                                <span class="sr-assets-discovery-addas" id="asset-discovery-add-as" aria-labelledby="asset-discovery-add-as-label"><?= $escaper->escapeHtml($manage_assets_config['discoveryAddsVerified'] ? $lang['Verified'] : $lang['Unverified']) ?></span>
                                <span class="sr-qhint"><?= $escaper->escapeHtml($lang['DiscoveryAddAsHint']) ?></span>
                            </div>
                            <div class="sr-qfield sr-qfield--full">
                                <label class="sr-qlabel" for="asset-discovery-teams"><?= $escaper->escapeHtml($lang['DiscoveryAssignTeams']) ?></label>
                                <select id="asset-discovery-teams" class="form-select" multiple data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AssetChooseTeams']) ?>" aria-describedby="asset-discovery-teams-hint asset-discovery-teams-error"></select>
                                <span class="sr-qhint" id="asset-discovery-teams-hint"><?= $escaper->escapeHtml($lang['DiscoveryAssignTeamsHint']) ?></span>
                                <div class="invalid-feedback" id="asset-discovery-teams-error"></div>
                            </div>
                            <!-- Probe method (GET /assets/discovery-runs/capabilities),
                                 filled in by manage-asset-discovery.js when the dialog opens.
                                 With TCP connects only, the dialog warns and offers
                                 per-run ports. -->
                            <div class="sr-qfield sr-qfield--full" id="asset-discovery-probe" hidden>
                                <span class="sr-qhint" id="asset-discovery-probe-line"></span>
                                <span class="sr-qhint" id="asset-discovery-probe-source" hidden><?= $escaper->escapeHtml($lang['DiscoveryProbeDetectedByWebServer']) ?></span>
                            </div>
                            <div class="sr-qnote" id="asset-discovery-tcp-warning" hidden>
                                <i class="fa fa-triangle-exclamation sr-qnote-ico" aria-hidden="true"></i>
                                <span><?= $escaper->escapeHtml($lang['DiscoveryTcpProbeWarning']) ?></span>
                            </div>
                            <div class="sr-qfield sr-qfield--full" id="asset-discovery-ports-field" hidden>
                                <label class="sr-qlabel" for="asset-discovery-ports"><?= $escaper->escapeHtml($lang['DiscoveryTcpPortsForRun']) ?></label>
                                <input type="text" id="asset-discovery-ports" class="form-control" maxlength="200" inputmode="numeric" spellcheck="false" aria-describedby="asset-discovery-ports-hint asset-discovery-ports-error">
                                <span class="sr-qhint" id="asset-discovery-ports-hint"></span>
                                <div class="invalid-feedback" id="asset-discovery-ports-error"></div>
                            </div>
                            <div class="sr-qnote">
                                <i class="fa fa-circle-info sr-qnote-ico" aria-hidden="true"></i>
                                <span><?= $escaper->escapeHtml($lang['DiscoveryBackgroundNote']) ?></span>
                            </div>
                        </div>
                    </div></section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                    <button type="submit" class="btn btn-submit" id="asset-discovery-start" data-action="start-discovery"><?= $escaper->escapeHtml($lang['DiscoveryStart']) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($caps['can_edit']) { ?>
<!-- Assign teams (bulk): adds teams, never removes. -->
<div class="modal fade sr-modal" id="manage-assets-teams-modal" tabindex="-1" aria-hidden="true" aria-labelledby="manage-assets-teams-title">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-users" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="manage-assets-teams-title"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
            </div>
            <div class="modal-body">
                <section class="sr-qcard"><div class="sr-qcard-body">
                    <div class="sr-qfield sr-qfield--full">
                        <label class="sr-qlabel" for="manage-assets-teams-select"><?= $escaper->escapeHtml($lang['Teams']) ?></label>
                        <select id="manage-assets-teams-select" class="form-select" multiple data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AssetChooseTeams']) ?>"></select>
                        <span class="sr-qhint"><?= $escaper->escapeHtml($lang['AssetAssignTeamsHint']) ?></span>
                    </div>
                </div></section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                <button type="button" class="btn btn-submit" id="manage-assets-teams-confirm" data-action="confirm-assign-teams" disabled><?= $escaper->escapeHtml($lang['Assign']) ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($caps['can_group_edit'] || $caps['can_group_create']) { ?>
<!-- Add to group (row and bulk). An existing group needs asset_group_edit;
     "Create a new group…" (a brand-new group holding the selection) needs
     asset_group_create. Each part is omitted without its permission. -->
<div class="modal fade sr-modal" id="manage-assets-group-modal" tabindex="-1" aria-hidden="true" aria-labelledby="manage-assets-group-title">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-layer-group" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="manage-assets-group-title"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
            </div>
            <div class="modal-body">
                <section class="sr-qcard"><div class="sr-qcard-body">
<?php if ($caps['can_group_edit']) { ?>
                    <div class="sr-qfield sr-qfield--full" id="manage-assets-group-select-field">
                        <label class="sr-qlabel" for="manage-assets-group-select"><?= $escaper->escapeHtml($lang['AssetGroup']) ?></label>
                        <select id="manage-assets-group-select" class="form-select" data-placeholder="<?= $escaper->escapeHtmlAttr($lang['AssetChooseGroup']) ?>"></select>
                    </div>
<?php } ?>
<?php if ($caps['can_group_create']) { ?>
                    <div class="sr-qfield sr-qfield--full<?= $caps['can_group_edit'] ? ' d-none' : '' ?>" id="manage-assets-group-new-field">
                        <label class="sr-qlabel" for="manage-assets-group-new-name"><?= $escaper->escapeHtml($lang['AssetNewGroupName']) ?> <span class="sr-req" aria-hidden="true">*</span></label>
                        <input type="text" id="manage-assets-group-new-name" class="form-control" maxlength="100" autocomplete="off" aria-describedby="manage-assets-group-new-name-error">
                        <div class="invalid-feedback" id="manage-assets-group-new-name-error"></div>
                    </div>
<?php } ?>
                </div></section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                <button type="button" class="btn btn-submit" id="manage-assets-group-confirm" data-action="confirm-add-to-group" disabled><?= $escaper->escapeHtml($lang['Add']) ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- Asset record modal (view / edit / add; spec 2026-09-29-asset-record-modal
     §2), driven by js/simplerisk/pages/asset-record-modal.js. URL-addressable:
     ?asset=<id> view, &mode=edit edit, ?asset=new add. The body's cards come
     from the Customization template (the Cards engines). Header actions a
     user lacks are not rendered; the record's own flags narrow them further
     client-side. Every value is inserted as text by the script. -->
<div class="modal fade sr-modal sr-modal--cards" id="asset-record-modal" tabindex="-1" aria-hidden="true" aria-labelledby="asset-record-title" aria-describedby="asset-record-meta">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header sr-arm-head">
                <span class="sr-modal-icon"><i class="fa fa-server" id="asset-record-icon" aria-hidden="true"></i></span>
                <div class="sr-arm-titles">
                    <h4 class="modal-title" id="asset-record-title" tabindex="-1"></h4>
                    <div class="sr-arm-meta" id="asset-record-meta"></div>
                </div>
                <div class="sr-arm-actions" id="asset-record-actions" hidden>
                    <button type="button" class="btn btn-submit" id="asset-record-edit" hidden><?= $escaper->escapeHtml($lang['AssetRecordEdit']) ?></button>
                    <button type="button" class="sr-arm-iconbtn" id="asset-record-copy" title="<?= $escaper->escapeHtmlAttr($lang['AssetRecordCopyLink']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['AssetRecordCopyLink']) ?>"><i class="fa fa-link" aria-hidden="true"></i></button>
                    <div class="sr-arm-more" id="asset-record-more">
                        <button type="button" class="sr-arm-iconbtn" id="asset-record-more-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="asset-record-more-menu" title="<?= $escaper->escapeHtmlAttr($lang['MoreActions']) ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['MoreActions']) ?>"><i class="fa fa-ellipsis" aria-hidden="true"></i></button>
                        <div class="sr-arm-menu" id="asset-record-more-menu" role="menu" aria-labelledby="asset-record-more-toggle" hidden>
<?php if ($caps['can_group_edit'] || $caps['can_group_create']) { ?>
                            <button type="button" class="sr-arm-menu-item" role="menuitem" tabindex="-1" id="asset-record-add-to-group"><i class="fa fa-layer-group" aria-hidden="true"></i><?= $escaper->escapeHtml($lang['AssetBulkAddToGroup']) ?></button>
<?php } ?>
<?php if ($caps['can_verify']) { ?>
                            <button type="button" class="sr-arm-menu-item" role="menuitem" tabindex="-1" id="asset-record-verify" hidden><i class="fa fa-check" aria-hidden="true"></i><?= $escaper->escapeHtml($lang['Verify']) ?></button>
                            <button type="button" class="sr-arm-menu-item" role="menuitem" tabindex="-1" id="asset-record-unverify" hidden><i class="fa fa-rotate-left" aria-hidden="true"></i><?= $escaper->escapeHtml($lang['AssetRecordMarkUnverified']) ?></button>
<?php } ?>
                            <button type="button" class="sr-arm-menu-item" role="menuitem" tabindex="-1" id="asset-record-audit-open"><i class="fa fa-clock-rotate-left" aria-hidden="true"></i><?= $escaper->escapeHtml($lang['AssetRecordViewAuditTrail']) ?></button>
<?php if ($caps['can_delete']) { ?>
                            <button type="button" class="sr-arm-menu-item sr-arm-menu-item--danger" role="menuitem" tabindex="-1" id="asset-record-delete" hidden><i class="fa fa-trash" aria-hidden="true"></i><?= $escaper->escapeHtml($lang['DeleteAsset']) ?></button>
<?php } ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" id="asset-record-close-x" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="sr-arm-loading" id="asset-record-loading" role="status" hidden>
                    <i class="fa fa-spinner fa-spin" aria-hidden="true"></i> <span><?= $escaper->escapeHtml($lang['Loading']) ?></span>
                </div>
                <!-- Add with several asset template groups: pick the template first. -->
                <div class="sr-arm-templates" id="asset-record-templates" hidden>
                    <span class="sr-qlabel" id="asset-record-templates-label"><?= $escaper->escapeHtml($lang['Template']) ?></span>
                    <div class="sr-arm-segment" id="asset-record-templates-options" role="radiogroup" aria-labelledby="asset-record-templates-label"></div>
                </div>
                <div class="sr-arm-view" id="asset-record-view"></div>
                <div class="sr-qform sr-arm-form" id="asset-record-form" hidden></div>
<?php if ($caps['can_verify']) { ?>
                <div class="sr-qform sr-arm-extra">
                <!-- Edit/Add, asset_verify only (spec §10): outside the engine's
                     form; asset-record-modal.js adds its value to the body. -->
                <section class="sr-qcard sr-arm-verification" id="asset-record-verification" hidden>
                    <!-- Same head markup as the engine's cards (risk-details-form.js). -->
                    <div class="sr-qcard-head">
                        <span class="sr-qcard-ico"><i class="fa fa-circle-check" aria-hidden="true"></i></span>
                        <span class="sr-qcard-htext"><h2><?= $escaper->escapeHtml($lang['AssetRecordVerificationCard']) ?></h2></span>
                        <span class="sr-qcard-tag"><?= $escaper->escapeHtml($lang['AssetRecordVerificationTag']) ?></span>
                    </div>
                    <div class="sr-qcard-body">
                        <div class="sr-qswitches">
                            <div class="sr-qswitch-row">
                                <div class="form-check form-switch sr-qswitch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="asset-record-verified" aria-describedby="asset-record-verified-hint">
                                </div>
                                <label class="sr-qswitch-text" for="asset-record-verified">
                                    <span class="sr-qswitch-title"><?= $escaper->escapeHtml($lang['Verified']) ?></span>
                                    <span class="sr-qswitch-desc" id="asset-record-verified-hint"><?= $escaper->escapeHtml($lang['AssetRecordVerifiedHint']) ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
                </div>
<?php } ?>
                <!-- "View audit trail" replaces the body (a modal never opens another). -->
                <section class="sr-qcard sr-arm-audit" id="asset-record-audit" hidden>
                    <div class="sr-qcard-head">
                        <span class="sr-qcard-icon"><i class="fa fa-clock-rotate-left" aria-hidden="true"></i></span>
                        <h3><?= $escaper->escapeHtml($lang['AssetRecordAuditTrailTitle']) ?></h3>
                        <label class="visually-hidden" for="asset-record-audit-window"><?= $escaper->escapeHtml($lang['Period']) ?></label>
                        <select class="form-select form-select-sm sr-arm-audit-window" id="asset-record-audit-window">
<?php foreach ([7 => 'PastWeek', 30 => 'PastMonth', 90 => 'PastQuarter', 365 => 'PastYear'] as $days => $key) { ?>
                            <option value="<?= (int)$days ?>"<?= $days === 365 ? ' selected' : '' ?>><?= $escaper->escapeHtml($lang[$key]) ?></option>
<?php } ?>
                        </select>
                    </div>
                    <div class="sr-qcard-body">
                        <p class="sr-qhint sr-arm-audit-state" id="asset-record-audit-state" role="status" aria-live="polite"></p>
                        <div class="sr-table-scroll">
                            <table class="sr-table sr-arm-audit-table" id="asset-record-audit-table" hidden>
                                <thead><tr>
                                    <th class="dt-head-left"><?= $escaper->escapeHtml($lang['AuditTrailDateAndTime']) ?></th>
                                    <th class="dt-head-left"><?= $escaper->escapeHtml($lang['User']) ?></th>
                                    <th class="dt-head-left"><?= $escaper->escapeHtml($lang['Message']) ?></th>
                                </tr></thead>
                                <tbody id="asset-record-audit-rows"></tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <div class="sr-arm-foot" id="asset-record-foot">
                    <span class="sr-modal-hint" id="asset-record-hint"></span>
                    <div class="sr-arm-foot-actions">
                    <button type="button" class="btn btn-dark" id="asset-record-back" hidden><?= $escaper->escapeHtml($lang['AssetRecordBackToAsset']) ?></button>
                    <button type="button" class="btn btn-dark" id="asset-record-close-audit" hidden><?= $escaper->escapeHtml($lang['Close']) ?></button>
                    <button type="button" class="btn btn-dark" id="asset-record-close" hidden><?= $escaper->escapeHtml($lang['Close']) ?></button>
                    <button type="button" class="btn btn-dark" id="asset-record-cancel" hidden><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                    <button type="button" class="btn btn-submit" id="asset-record-save" hidden><?= $escaper->escapeHtml($lang['AssetRecordSave']) ?></button>
                    </div>
                </div>
                <!-- Dirty guard (design-system §8 confirm): replaces the footer. -->
                <span class="visually-hidden" id="asset-record-discard-live" role="status" aria-live="polite"></span>
                <div class="sr-arm-foot sr-arm-discard" id="asset-record-discard" role="group" aria-labelledby="asset-record-discard-question" hidden>
                    <span class="sr-arm-discard-question" id="asset-record-discard-question"><?= $escaper->escapeHtml($lang['AssetRecordDiscardQuestion']) ?></span>
                    <div class="sr-arm-foot-actions">
                        <button type="button" class="btn btn-dark" id="asset-record-keep-editing"><?= $escaper->escapeHtml($lang['AssetRecordKeepEditing']) ?></button>
                        <button type="button" class="btn btn-submit" id="asset-record-discard-confirm"><?= $escaper->escapeHtml($lang['AssetRecordDiscardChanges']) ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
