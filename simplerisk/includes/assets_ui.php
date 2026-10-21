<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Data builders behind the asset record modal's read endpoints
 * (/ui/asset/template_groups|fields|layout and /ui/asset/{id}/values,
 * api/v2/includes/api.php). The endpoints own authentication and the
 * permission / Team Separation gates; everything here assumes the caller has
 * already passed them and returns RAW text (no HTML escaping -- the client
 * inserts values with text/attribute setters, so escaping here would
 * double-encode).
 *
 * Core file: every Customization Extra call is guarded, and the no-Extra
 * branches read only Core helpers, because the shipped bundle has no
 * simplerisk/extras/ directory.
 */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/assets_page_rules.php'));
require_once(realpath(__DIR__ . '/assets_write_rules.php'));
require_once(realpath(__DIR__ . '/asset_scoring.php'));

/**
 * Loads the Customization Extra when it is active and its tables exist.
 * Returns whether its functions may be used.
 */
function assets_ui_customization_available(string $table = 'custom_template'): bool
{
    if (!customization_extra() || !table_exists($table)) {
        return false;
    }
    $extra_index = realpath(__DIR__ . '/../extras/customization/index.php');
    if ($extra_index === false) {
        return false;
    }
    require_once($extra_index);
    return true;
}

/**
 * The asset template groups the current user may create an asset under. One
 * synthetic group without the Extra, matching resolve_template_group_id_from_core().
 *
 * @return array<int,array{id:int,name:string,is_default:int}>
 */
function assets_ui_template_groups(): array
{
    global $lang;

    if (assets_ui_customization_available('custom_template_group') && function_exists('get_template_groups_for_user')) {
        $groups = array_map(fn($group) => [
            'id' => (int)$group['id'],
            'name' => (string)$group['name'],
            'is_default' => (int)$group['is_default'],
        ], get_template_groups_for_user('asset'));
        if ($groups) {
            return array_values($groups);
        }
    }

    return [['id' => 1, 'name' => (string)$lang['Asset'], 'is_default' => 1]];
}

/**
 * The asset record's field roster for a template group (tab 1), every field
 * carrying a card_key and a nested-grid position. Rows with no (or a foreign)
 * card_key are placed in memory by assets_ui_place_fields() (ledger ruling
 * T2b) -- nothing is written. Without the Extra the ten core fields are
 * synthesized from the Core card map. AssetName is always present and
 * required (synthetic, like Subject for risks). AssetScoring is dropped
 * while the database lacks the scoring columns (code deployed before the
 * upgrade) and is never required (ruling G8).
 *
 * @return array<int,array<string,mixed>>
 */
function assets_ui_fields_for_group(int $template_group_id): array
{
    $rows = [];
    if (assets_ui_customization_available() && function_exists('get_active_fields')) {
        foreach (get_active_fields('asset', $template_group_id, 1) as $row) {
            if ((int)$row['is_basic'] === 1 && $row['name'] === 'AssetScoring' && !asset_scoring_schema_ready()) {
                continue;
            }
            if (asset_field_is_positionable((string)$row['name'], (int)$row['is_basic'] === 1)) {
                $rows[] = $row;
            }
        }
    } else {
        foreach (get_asset_core_field_default_order() as $name) {
            if ($name === 'AssetScoring' && !asset_scoring_schema_ready()) {
                continue;
            }
            $rows[] = ['id' => 0, 'name' => $name, 'type' => '', 'is_basic' => 1, 'required' => 0, 'encryption' => 0, 'card_key' => null];
        }
    }

    if (!in_array('AssetName', array_column($rows, 'name'), true)) {
        array_unshift($rows, ['id' => 0, 'name' => 'AssetName', 'type' => '', 'is_basic' => 1, 'required' => 1, 'encryption' => 0, 'card_key' => null]);
    }

    $required_names = get_asset_synthetic_required_field_names();
    $fields = [];
    foreach ($rows as $row) {
        $is_scoring = (int)($row['is_basic'] ?? 0) === 1 && $row['name'] === 'AssetScoring';
        $fields[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)$row['name'],
            'type' => (string)($row['type'] ?? ''),
            'is_basic' => (int)($row['is_basic'] ?? 0),
            // AssetScoring is never required (ruling G8): the composite has no
            // single input and "Not set" is a meaningful answer.
            'required' => $is_scoring ? 0 : (in_array($row['name'], $required_names, true) ? 1 : (int)($row['required'] ?? 0)),
            'encryption' => (int)($row['encryption'] ?? 0),
            'alphabetical_order' => (int)($row['alphabetical_order'] ?? 0),
            'removable' => in_array($row['name'], $required_names, true) ? 0 : 1,
            'active' => 1,
            'card_key' => $row['card_key'] ?? null,
            'pos_x' => $row['pos_x'] ?? null,
            'pos_y' => $row['pos_y'] ?? null,
            'pos_w' => $row['pos_w'] ?? null,
            'pos_h' => $row['pos_h'] ?? null,
        ];
    }

    $fields = assets_ui_place_fields(
        $fields,
        get_asset_core_field_card_map(),
        customization_asset_cards_layout_card_keys(),
        customization_nested_grid_columns(),
        customization_default_field_width()
    );

    return assets_ui_attach_field_options($fields);
}

/**
 * Attaches option lists to the select-shaped asset fields. Every list is cut
 * to value/name by api_trim_ui_field_options()'s rules (rebuilt here so this
 * Core file does not depend on the v2 API layer), and AssociatedRisks lists
 * only the risks the caller may see under Team Separation.
 *
 * @param array<int,array<string,mixed>> $fields
 * @return array<int,array<string,mixed>>
 */
function assets_ui_attach_field_options(array $fields): array
{
    $trim = fn($rows) => array_values(array_map(
        fn($row) => ['value' => $row['value'] ?? null, 'name' => (string)($row['name'] ?? '')],
        array_filter(is_array($rows) ? $rows : [], 'is_array')
    ));

    foreach ($fields as &$field) {
        if ((int)$field['is_basic'] === 1) {
            switch ($field['name']) {
                case 'SiteLocation':
                    $field['options'] = $trim(get_options_from_table('location'));
                    break;
                case 'Team':
                    $field['options'] = $trim(get_options_from_table('team'));
                    break;
                case 'AssetValuation':
                    $field['options'] = assets_ui_valuation_options();
                    $field['default_value'] = (int)get_default_asset_valuation();
                    break;
                case 'AssociatedRisks':
                    // Risk subjects need Risk Management (SR-2313). Without
                    // it the roster is empty and the client shows a count,
                    // never sending associated_risks (a body naming them is
                    // refused 403), so a save keeps the stored links.
                    $field['can_select_risks'] = assets_caller_can_see_associated_risks();
                    $field['options'] = $field['can_select_risks'] ? assets_ui_accessible_risk_options() : [];
                    break;
                case 'MappedControls':
                    // The control picker loads its roster from the Governance
                    // roster endpoint, which applies its own permission.
                    $field['maturity_options'] = $trim(get_options_from_table('control_maturity'));
                    // The picker's roster endpoint needs Governance. Without
                    // it the client shows the mapping read-only and never
                    // sends it, so a save keeps the stored mapping.
                    $field['can_select_controls'] = (bool)check_permission('governance');
                    break;
                case 'AssetScoring':
                    // Numbers in hundredths for the composite's live preview (the JS
                    // mirror of asset_scoring_compute()); defaults pre-fill Add only.
                    $settings = asset_scoring_settings();
                    $field['scoring'] = [
                        'weights' => $settings['weights'],
                        'values' => $settings['values'],
                        'thresholds' => $settings['thresholds'],
                        'defaults' => $settings['defaults'],
                    ];
                    break;
            }
        } elseif (in_array($field['type'], ['dropdown', 'multidropdown', 'user_multidropdown'], true)) {
            $source = $field['type'] === 'user_multidropdown' ? 'enabled_users' : 'custom_field_' . (int)$field['id'];
            $field['options'] = $trim(get_options_from_table($source));
        }
    }
    unset($field);

    return $fields;
}

/**
 * Asset valuation levels as {value, name} with the same range/level label the
 * rest of the asset pages use (asset_valuation_parts()), unescaped.
 *
 * @return array<int,array{value:int,name:string}>
 */
function assets_ui_valuation_options(): array
{
    $db = db_open();
    $rows = $db->query("SELECT `id`, `min_value`, `max_value`, `valuation_level_name` FROM `asset_values` ORDER BY `min_value`, `id`")->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $currency = (string)get_setting('currency');
    return array_map(fn($row) => [
        'value' => (int)$row['id'],
        'name' => asset_valuation_parts($row, $currency)['label'],
    ], $rows);
}

/**
 * {value, name} for every risk the caller may see ("[<display id>] subject"),
 * value being the internal risk id the risks_to_assets table stores.
 *
 * @return array<int,array{value:int,name:string}>
 */
function assets_ui_accessible_risk_options(): array
{
    $options = get_options_from_table('risks_with_id');
    $visible = array_flip(filter_risk_ids_by_team_scope(array_column($options, 'value')));

    $out = [];
    foreach ($options as $option) {
        if (isset($visible[(int)$option['value']])) {
            $out[] = ['value' => (int)$option['value'], 'name' => (string)$option['name']];
        }
    }
    return $out;
}

/**
 * Card tiles for a template group (tab 1): stored tiles plus in-memory tiles
 * for any missing asset card (ledger ruling T2b), each with the field_count
 * of the resolved roster. Without the Extra the whole set is synthesized.
 *
 * @param array<int,array<string,mixed>>|null $fields the resolved roster, when the caller already has it
 * @return array<int,array<string,mixed>>
 */
function assets_ui_layout_for_group(int $template_group_id, ?array $fields = null): array
{
    $fields = $fields ?? assets_ui_fields_for_group($template_group_id);

    $stored = [];
    if (assets_ui_customization_available('custom_template_card') && function_exists('get_customization_layout')) {
        $stored = get_customization_layout('asset', $template_group_id, 1);
    }

    return assets_ui_layout_cards(
        $stored,
        $fields,
        customization_asset_cards_layout_card_keys(),
        fn(int $rows): int => customization_card_height_for_field_count($rows * customization_fields_per_row())
    );
}

/**
 * One asset's field values for the record modal, keyed by the write-payload
 * names PATCH /assets/{id} accepts (name, ip, value, location, team, details,
 * tags, mapped_controls, associated_risks, custom_field_<id>) plus `verified`
 * and `created` (read-only, for the record's provenance line) and `scoring`
 * (the confidentiality/integrity/availability selections as `raw`, and the
 * computed `result` -- present once the database has the scoring columns).
 * Each entry is {raw, display} with optional `names` / `items` /
 * `display_html`. Stored ciphertext is decrypted. Returns null when the asset
 * does not exist. The CALLER must have checked check_access_for_asset().
 *
 * Related objects are limited to what the caller may see: associated risks
 * only with the Risk Management permission and through Team Separation
 * (without the permission only their count), control names only with the
 * Governance permission (the ids alone are kept so an edit round-trips the
 * mapping).
 *
 * @return array{template_group_id:int, values:array<string,array<string,mixed>>}|null
 */
function assets_ui_values_for_asset(int $id): ?array
{
    $asset = get_asset_by_id($id);
    if (!$asset) {
        return null;
    }

    $template_group_id = asset_record_template_group_id($asset);
    $text = fn($v) => ['raw' => (string)$v, 'display' => (string)$v];

    $name = (string)try_decrypt($asset['name']);
    $ip = (string)try_decrypt((string)$asset['ip']);
    $details = (string)try_decrypt((string)$asset['details']);

    $values = [
        'name' => $text($name),
        'ip' => $text($ip),
        'details' => ['raw' => $details, 'display' => $details, 'display_html' => purify_html($details)],
        'verified' => ['raw' => (int)$asset['verified'], 'display' => (string)(int)$asset['verified']],
        'created' => ['raw' => (string)$asset['created'], 'display' => (string)format_date((string)$asset['created'])],
        'value' => assets_ui_valuation_value((int)$asset['value']),
        'location' => assets_ui_named_ids('location', (string)$asset['location']),
        'team' => assets_ui_named_ids('team', (string)$asset['teams']),
    ];

    if (asset_scoring_schema_ready()) {
        // Computed once, server side, from the same function the list and
        // Import-Export use; the widget's live preview mirrors it in JS.
        $selections = asset_scoring_normalize_selections($asset);
        $result = asset_scoring_compute($selections, asset_scoring_settings());
        $values['scoring'] = [
            'raw' => $selections,
            'display' => '',
            'result' => [
                'scored' => $result['scored'],
                'categorization' => $result['categorization'],
                'score' => $result['score'],
                'band' => $result['band'],
            ],
        ];
    }

    $tags = getTagsOfTaggee($id, 'asset') ?: [];
    sort($tags, SORT_NATURAL | SORT_FLAG_CASE);
    $values['tags'] = ['raw' => array_values($tags), 'display' => implode(', ', $tags), 'names' => array_values($tags)];

    $values['mapped_controls'] = assets_ui_mapped_controls_value($id);
    $values['associated_risks'] = assets_ui_associated_risks_value($id);

    if (assets_ui_customization_available('custom_asset_data') && function_exists('api_ui_custom_field_raw_and_display_value')) {
        $db = db_open();
        $stmt = $db->prepare("SELECT `field_id`, `value` FROM `custom_asset_data` WHERE `asset_id` = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $stored = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'value', 'field_id');
        db_close($db);

        foreach (asset_custom_fields_by_id_for_group($template_group_id) as $field_id => $field) {
            $values['custom_field_' . $field_id] = api_ui_custom_field_raw_and_display_value($field, $stored[$field_id] ?? '');
        }
    }

    return ['template_group_id' => $template_group_id, 'values' => $values];
}

/** @return array{raw:int, display:string} */
function assets_ui_valuation_value(int $valuation_id): array
{
    foreach (assets_ui_valuation_options() as $option) {
        if ($option['value'] === $valuation_id) {
            return ['raw' => $valuation_id, 'display' => $option['name']];
        }
    }
    return ['raw' => $valuation_id, 'display' => ''];
}

/**
 * A CSV id column (location, teams) as {raw: int[], display, names}, where
 * names is a list of {id, name} objects in raw order (ids that no longer
 * resolve are left out of names). $table is 'location' or 'team'.
 *
 * @return array{raw:int[], display:string, names:array<int,array{id:int,name:string}>}
 */
function assets_ui_named_ids(string $table, string $csv): array
{
    $ids = assets_positive_ids($csv);
    $names = [];
    if ($ids && in_array($table, ['location', 'team'], true)) {
        $db = db_open();
        $stmt = $db->prepare("SELECT `value`, `name` FROM `{$table}` WHERE `value` IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
        $stmt->execute($ids);
        $by_id = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name', 'value');
        db_close($db);
        foreach ($ids as $id) {
            if (isset($by_id[$id])) {
                $names[] = ['id' => $id, 'name' => (string)$by_id[$id]];
            }
        }
    }
    return ['raw' => $ids, 'display' => implode(', ', array_column($names, 'name')), 'names' => $names];
}

/**
 * raw: [{control_maturity, control_id: int[]}] (the rows the Mapped controls
 * widget edits); items add maturity and control names, the latter only for a
 * caller with the Governance permission.
 */
function assets_ui_mapped_controls_value(int $asset_id): array
{
    $grouped = get_existing_mapped_controls_for_asset($asset_id);
    ksort($grouped);

    $maturity_names = [];
    foreach (get_options_from_table('control_maturity') as $row) {
        $maturity_names[(int)$row['value']] = (string)$row['name'];
    }

    $control_names = [];
    $all_ids = array_values(array_unique(array_map('intval', array_merge([], ...array_values($grouped)))));
    if ($all_ids && check_permission('governance')) {
        $db = db_open();
        $stmt = $db->prepare("SELECT `id`, `short_name` FROM `framework_controls` WHERE `id` IN (" . implode(',', array_fill(0, count($all_ids), '?')) . ")");
        $stmt->execute($all_ids);
        $control_names = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'short_name', 'id');
        db_close($db);
    }

    $raw = [];
    $items = [];
    foreach ($grouped as $maturity => $control_ids) {
        $control_ids = array_map('intval', $control_ids);
        sort($control_ids);
        $raw[] = ['control_maturity' => (int)$maturity, 'control_id' => $control_ids];
        $items[] = [
            'control_maturity' => (int)$maturity,
            'maturity_name' => $maturity_names[(int)$maturity] ?? '',
            'controls' => array_map(fn($cid) => ['id' => $cid, 'name' => (string)($control_names[$cid] ?? '')], $control_ids),
        ];
    }

    return ['raw' => $raw, 'display' => '', 'items' => $items];
}

/**
 * The asset's directly associated risks the caller may see: raw internal
 * ids, names as {id: internal id, name: "[<display id>] subject"}, and
 * `count`. Hidden risks are left out entirely (and not counted); the write
 * path (update_asset_risks_associations()) never removes a risk the caller
 * cannot see, so an edit built from this list keeps them.
 *
 * Without the Risk Management permission (SR-2313) raw, names and display
 * are empty and only `count` is set: how many risks the caller could see
 * under Team Separation, so the count never reveals another team's risks.
 *
 * @return array{raw:int[], display:string, names:array<int,array{id:int,name:string}>, count:int}
 */
function assets_ui_associated_risks_value(int $asset_id): array
{
    $visible = filter_risk_ids_by_team_scope(array_map('intval', get_associated_risks_for_asset($asset_id)));
    sort($visible);

    if (!assets_caller_can_see_associated_risks()) {
        return ['raw' => [], 'display' => '', 'names' => [], 'count' => count($visible)];
    }

    $names = [];
    if ($visible) {
        $db = db_open();
        $stmt = $db->prepare("SELECT `id`, `subject` FROM `risks` WHERE `id` IN (" . implode(',', array_fill(0, count($visible), '?')) . ")");
        $stmt->execute($visible);
        $subjects = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'subject', 'id');
        db_close($db);
        foreach ($visible as $risk_id) {
            $names[] = ['id' => $risk_id, 'name' => '[' . ($risk_id + 1000) . '] ' . try_decrypt((string)($subjects[$risk_id] ?? ''))];
        }
    }

    return ['raw' => $visible, 'display' => implode(', ', array_column($names, 'name')), 'names' => $names, 'count' => count($visible)];
}
