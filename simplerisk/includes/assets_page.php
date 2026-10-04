<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Server-side configuration for the Manage assets page (asset management
 * redesign, Phase A, Task 9). The page is client-rendered from the v2 API;
 * this file supplies only what the list API does not: the capability map and
 * the id -> label lookups the table and its filters need (teams, locations,
 * tags, asset groups, valuations). Values are RAW text -- the page emits them
 * as JSON and the client inserts every one with textContent, never as HTML.
 */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/assets_page_rules.php'));
require_once(realpath(__DIR__ . '/assets_list.php'));
require_once(realpath(__DIR__ . '/assets_discovery.php'));
require_once(realpath(__DIR__ . '/assets_bulk.php'));
require_once(realpath(__DIR__ . '/asset_scoring.php'));

/**
 * Id -> label lookups for the Manage assets table and filter row.
 *
 *  teams, team_options  the teams the viewer may see, filter by and assign
 *                (their own teams under Team Separation, every team otherwise;
 *                admins get all). Both keys carry the same list.
 *  tags          tags in use on assets the viewer can see (team-scoped)
 *  locations, groups, valuations  install-wide lookup tables; the legacy
 *                create/edit modal and GET /asset-groups already offer these
 *                complete lists to every asset user.
 *  scoring_levels  Asset Scoring levels in rank order: id (the categorization
 *                / band filter value, 1 Low .. 3 High), key (the code the list
 *                rows carry) and name (raw label). scoring_not_applicable is
 *                the raw label of the confidentiality-only code; scoring_enabled
 *                is false until the upgrade has added the scoring columns.
 *  scoring_ratings  objective => the options of its rating filter
 *                (Confidentiality / Integrity / Availability): {id, key, name}
 *                per rating, Low/Moderate/High (ids 1-3) and, for
 *                confidentiality only, Not applicable (id 0).
 *
 * @return array{teams:array<int,array{id:int,name:string}>,team_options:array<int,array{id:int,name:string}>,locations:array<int,array{id:int,name:string}>,tags:array<int,array{id:int,name:string}>,groups:array<int,array{id:int,name:string}>,valuations:array<int,array{id:int,label:string,level:string,range:string}>,default_valuation:int,scoring_levels:array<int,array{id:int,key:string,name:string}>,scoring_not_applicable:string,scoring_ratings:array<string,array<int,array{id:int,key:string,name:string}>>,scoring_enabled:bool}
 */
function asset_manage_page_lookups(): array
{
    $pairs = static function (array $rows, string $id_key, string $name_key): array {
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int)$r[$id_key], 'name' => (string)$r[$name_key]];
        }
        return $out;
    };

    $db = db_open();
    $stmt = $db->prepare("SELECT `value`, `name` FROM `location` ORDER BY `name`");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tags on assets the viewer can see: the same team-separation scope the
    // list API applies (assets_build_filter_query() with no filters), so a
    // team-scoped user never learns tag names used only on other teams' assets.
    $scope = assets_build_filter_query([]);
    $stmt = $db->prepare("
        SELECT DISTINCT `t`.`id`, `t`.`tag`
        FROM `tags` t
            INNER JOIN `tags_taggees` tt ON `tt`.`tag_id` = `t`.`id` AND `tt`.`type` = 'asset'
            INNER JOIN `assets` a ON `a`.`id` = `tt`.`taggee_id`
        {$scope['where']}
        ORDER BY `t`.`tag`");
    $stmt->execute($scope['params']);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT `id`, `name` FROM `asset_groups` ORDER BY `name`");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT `id`, `min_value`, `max_value`, `valuation_level_name` FROM `asset_values` ORDER BY `min_value`, `id`");
    $stmt->execute();
    $value_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $currency = (string)get_setting('currency');
    $valuations = [];
    foreach ($value_rows as $v) {
        $parts = asset_valuation_parts($v, $currency);
        $valuations[] = [
            'id' => (int)$v['id'],
            // Same text the legacy page and the edit form's dropdown show
            // ("$1,000 to $10,000 (Level)"); the filter options use it.
            'label' => $parts['label'],
            // The text level name ('' when unnamed) and the numeric range,
            // which the table and group cells show together.
            'level' => $parts['level'],
            'range' => $parts['range'],
        ];
    }

    // Teams the viewer may see and assign: their own teams under Team
    // Separation (admins get every team, via get_user_teams()), every team
    // otherwise -- the rule POST /assets/bulk assign_teams enforces. The table's
    // team chips use the same list, so a team-scoped user is not sent the names
    // of other teams; an asset's other teams simply show no chip.
    $visible_teams = $pairs(get_teams_by_login_user(), 'value', 'name');

    $level_labels = asset_scoring_level_labels();
    $scoring_levels = [];
    foreach (ASSET_SCORING_LEVELS as $i => $code) {
        $scoring_levels[] = ['id' => $i + 1, 'key' => $code, 'name' => $level_labels[$code]];
    }

    return [
        'teams' => $visible_teams,
        'team_options' => $visible_teams,
        'locations' => $pairs($locations, 'value', 'name'),
        'tags' => $pairs($tags, 'id', 'tag'),
        'groups' => $pairs($groups, 'id', 'name'),
        'valuations' => $valuations,
        'default_valuation' => (int)get_default_asset_valuation(),
        'scoring_levels' => $scoring_levels,
        'scoring_not_applicable' => $level_labels[ASSET_SCORING_NOT_APPLICABLE],
        'scoring_ratings' => array_combine(ASSET_SCORING_OBJECTIVES, array_map('asset_scoring_filter_levels', ASSET_SCORING_OBJECTIVES)),
        'scoring_enabled' => asset_scoring_schema_ready(),
    ];
}

/**
 * The whole config object the page emits as `window.manageAssetsConfig`.
 *
 * @param mixed $tab_param  raw `?tab=` value
 * @return array<string,mixed>
 */
function asset_manage_page_config($tab_param): array
{
    $db = db_open();
    $stmt = $db->prepare("SELECT COUNT(*) FROM `asset_groups`");
    $stmt->execute();
    $group_count = (int)$stmt->fetchColumn();
    db_close($db);

    $caps = asset_page_capabilities('asset_user_can');
    // Discover assets needs asset_discovery and the registered queue job.
    $discovery = !empty($caps['can_discovery']) && assets_discovery_job_registered();

    return [
        'caps' => $caps,
        'initialTab' => asset_page_initial_tab($tab_param),
        'groupCount' => $group_count,
        'discoveryEnabled' => $discovery,
        // Informational only (the modal's "Add new assets as"): the server
        // decides the real value when a run is started.
        'discoveryAddsVerified' => $discovery && asset_new_verified_for_current_user(),
        // Which runs' Cancel action to offer: the requester's own, or every
        // run for an admin (DELETE /assets/discovery-runs/{id} enforces it).
        'discoveryUid' => (int)($_SESSION['uid'] ?? 0),
        'discoveryCancelAny' => $discovery && is_admin(),
        // The Asset groups tab shows its Linked risks column only to users who
        // may see risks (GET /asset-groups returns risk_count null otherwise).
        'canViewRisks' => (bool)check_permission('riskmanagement'),
        // The keys GET /assets can sort by, so the table marks every other
        // column non-sortable instead of keeping its own copy of the list.
        'sortable' => assets_list_sortable_keys(),
        // Most assets one bulk request may act on (POST /assets/bulk enforces
        // it): the page says so before sending instead of after.
        'bulkMax' => ['delete' => ASSETS_BULK_MAX_DELETE, 'other' => ASSETS_BULK_MAX_IDS],
        'lookups' => asset_manage_page_lookups(),
    ];
}

/**
 * The Manage assets tab row's create actions (design-system 6, tab-row
 * actions): one set per tab, only the active tab's set shown by the page's
 * script. Assets: Discover assets (asset_discovery plus the registered job,
 * config['discoveryEnabled']) and its <=900px home in the More actions menu,
 * then + Add asset, the one red button. Asset groups: + Add group
 * (asset_group_create). An action the user may not take is omitted, never
 * rendered disabled; a tab left with no action renders no set at all.
 *
 * Every label is escaped here, once.
 *
 * @param array<string,mixed> $config  asset_manage_page_config()
 */
function asset_manage_tab_actions_html(array $config): string
{
    global $lang, $escaper;
    $caps = $config['caps'] ?? [];
    $t = fn(string $k): string => $escaper->escapeHtml($lang[$k]);
    $a = fn(string $k): string => $escaper->escapeHtmlAttr($lang[$k]);

    $html = '<div class="sr-assets-tab-actions sr-table-toolbar-actions" id="manage-assets-tab-actions-assets" data-tab-actions="assets">';
    if (!empty($config['discoveryEnabled'])) {
        // Revealed by manage-assets.js; the stylesheet shows the button above
        // 900px and the More actions menu at and below it.
        $html .= '<button type="button" class="btn btn-outline-secondary d-none" id="manage-assets-discover" data-action="discover">' . $t('DiscoverAssets') . '</button>'
            . '<div class="sr-assets-more-actions d-none" id="manage-assets-more-actions">'
            . '<button type="button" class="sr-assets-more-toggle" id="manage-assets-more-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="manage-assets-more-menu" aria-label="' . $a('MoreActions') . '" title="' . $a('MoreActions') . '"><i class="fa fa-ellipsis" aria-hidden="true"></i></button>'
            . '<div class="sr-assets-more-menu" id="manage-assets-more-menu" role="menu" aria-labelledby="manage-assets-more-toggle" hidden>'
            . '<button type="button" class="sr-assets-more-item" role="menuitem" tabindex="-1" id="manage-assets-discover-menu" data-action="discover"><i class="fa fa-satellite-dish" aria-hidden="true"></i>' . $t('DiscoverAssets') . '</button>'
            . '</div></div>';
    }
    // Anyone with `asset` may add an asset (can_add is always true).
    if (!empty($caps['can_add'])) {
        $html .= '<button type="button" class="btn btn-danger sr-assets-add-btn" id="manage-assets-add" data-action="add"><span class="sr-assets-add-plus">+</span> <span class="sr-assets-add-label">' . $t('AddAsset') . '</span></button>';
    }
    $html .= '</div>';

    if (!empty($caps['can_group_create'])) {
        $html .= '<div class="sr-assets-tab-actions sr-table-toolbar-actions d-none" id="manage-assets-tab-actions-groups" data-tab-actions="groups">'
            . '<button type="button" class="btn btn-danger sr-assets-add-btn" id="asset-groups-add" data-action="group-add"><span class="sr-assets-add-plus">+</span> <span class="sr-assets-add-label">' . $t('AddAssetGroup') . '</span></button>'
            . '</div>';
    }
    return $html;
}
