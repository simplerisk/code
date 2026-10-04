<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/** Dependency-free rules for the Manage assets page: capabilities, column settings, valuation display, layout placement and the audit-trail window. Keep it that way: unit tests load this file alone. */

/**
 * Facet counts for the asset picker (design-system section 5): each facet is
 * counted inside the selections of the facets BEFORE it in $order, never its
 * own, so a location shows what the chosen team actually holds. An "all" count
 * per facet is the size of that facet's scope.
 *
 * @param array<int,array<string,mixed>> $rows   one entry per asset (or per group of identical assets, weighted by an
 *                                               optional integer 'n', default 1): facet key -> the value ids
 * @param array<string,?int> $selected           facet key -> the chosen value id, or null
 * @param string[] $order                         the facet sequence
 * @return array<string,array{all:int,values:array<int,int>}>
 */
function assets_facet_chain_counts(array $rows, array $selected, array $order): array
{
    $out = [];
    $scope = $rows;
    foreach ($order as $key) {
        $values = [];
        $all = 0;
        foreach ($scope as $row) {
            $weight = (int)($row['n'] ?? 1);
            $all += $weight;
            foreach (array_unique(array_map('intval', $row[$key] ?? [])) as $v) {
                $values[$v] = ($values[$v] ?? 0) + $weight;
            }
        }
        ksort($values);
        $out[$key] = ['all' => $all, 'values' => $values];
        $pick = $selected[$key] ?? null;
        if ($pick !== null) {
            $pick = (int)$pick;
            $scope = array_values(array_filter($scope, static fn($row) => in_array($pick, array_map('intval', $row[$key] ?? []), true)));
        }
    }
    return $out;
}

function assets_sanitize_column_settings($raw, array $allowed_keys): array
{
    $out = ['columns' => [], 'order' => []];
    if (!is_array($raw)) return $out;
    $seen = [];
    $columns = $raw['columns'] ?? [];
    if (is_array($columns)) {
        foreach ($columns as $pair) {
            if (!is_array($pair) || !isset($pair[0], $pair[1]) || !is_scalar($pair[0]) || !is_scalar($pair[1])) continue;
            [$k, $v] = [(string)$pair[0], (string)$pair[1]];
            if (!in_array($k, $allowed_keys, true) || isset($seen[$k])) continue;
            $seen[$k] = true;
            $out['columns'][] = [$k, $v === '1' ? '1' : '0'];
        }
    }
    $seen = [];
    $order = $raw['order'] ?? [];
    if (is_array($order)) {
        foreach ($order as $k) {
            if (!is_scalar($k)) continue;
            $k = (string)$k;
            if (in_array($k, $allowed_keys, true) && !isset($seen[$k])) { $seen[$k] = true; $out['order'][] = $k; }
        }
    }
    return $out;
}

/**
 * Capability map the Manage assets page hands to its JavaScript. Actions the
 * user may not take are omitted from the page (never shown disabled), so the
 * client needs one boolean per action. Adding an asset is open to anyone with
 * the base `asset` permission (spec D8), hence can_add is always true; the page
 * itself is only reachable with `asset`.
 *
 * @param callable(string):bool $can  asset_user_can()-shaped checker, injected so
 *                                    tests can assert which permission backs which flag
 * @return array<string,bool>
 */
function asset_page_capabilities(callable $can): array
{
    return [
        'can_add' => true,
        'can_edit' => (bool)$can('edit'),
        'can_delete' => (bool)$can('delete'),
        'can_verify' => (bool)$can('verify'),
        'can_discovery' => (bool)$can('discovery'),
        'can_group_create' => (bool)$can('group_create'),
        'can_group_edit' => (bool)$can('group_edit'),
        'can_group_delete' => (bool)$can('group_delete'),
    ];
}

/**
 * The tab Manage assets opens on, from the `?tab=` query value. Anything but
 * the literal `groups` falls back to the Assets tab.
 *
 * @param mixed $raw
 */
function asset_page_initial_tab($raw): string
{
    return (is_string($raw) && $raw === 'groups') ? 'groups' : 'assets';
}

/**
 * Display parts of one asset_values row: the currency-formatted numeric
 * `range` ("$1,000 to $10,000", or a single amount when min equals max), the
 * text `level` name ('' when unnamed), and the combined `label` that the
 * legacy pages show ("$1,000 to $10,000 (High)"). The range and label follow
 * get_asset_value_by_id() character for character, so every surface that
 * names a valuation (table, filters, group cells, the edit form's dropdown)
 * says the same thing. RAW text: the caller escapes once at its sink.
 *
 * @param array<string,mixed> $row  min_value, max_value, valuation_level_name
 * @return array{range:string,level:string,label:string}
 */
function asset_valuation_parts(array $row, string $currency): array
{
    $min = $row['min_value'] ?? 0;
    $max = $row['max_value'] ?? 0;
    $range = $currency . number_format((float)$min);
    if ((string)$min !== (string)$max) {
        $range .= ' to ' . $currency . number_format((float)$max);
    }
    // !empty() like the legacy formatter, so the label matches it exactly.
    $level = !empty($row['valuation_level_name']) ? (string)$row['valuation_level_name'] : '';
    return [
        'range' => $range,
        'level' => $level,
        'label' => $level !== '' ? "{$range} ({$level})" : $range,
    ];
}

/**
 * Defensive placement for the asset record's field roster (/ui/asset/fields,
 * ledger ruling T2b). A field with no card_key -- or one outside $card_keys --
 * goes to its core card from $core_map (name => ['card', 'w']) or to the
 * custom_fields catch-all, packed left to right below whatever that card
 * already holds, the same way backfill_customization_asset_cards_layout()
 * would place it. Placed fields are returned unchanged, in input order.
 * Read-only: nothing here is persisted.
 *
 * @param array<int,array<string,mixed>> $fields
 * @param array<string,array{card:string,w:int}> $core_map
 * @param string[] $card_keys
 * @return array<int,array<string,mixed>>
 */
function assets_ui_place_fields(array $fields, array $core_map, array $card_keys, int $columns, int $default_width, int $field_height = 2): array
{
    $is_placed = function (array $field) use ($card_keys): bool {
        if (!in_array($field['card_key'] ?? null, $card_keys, true)) {
            return false;
        }
        foreach (['pos_x', 'pos_y', 'pos_w', 'pos_h'] as $k) {
            if (!isset($field[$k]) || !is_numeric($field[$k])) {
                return false;
            }
        }
        return true;
    };

    // Cursor per card starts below the lowest placed field.
    $cursor = [];
    foreach ($fields as $field) {
        if ($is_placed($field)) {
            $bottom = (int)$field['pos_y'] + (int)$field['pos_h'];
            $cursor[$field['card_key']] = ['x' => 0, 'y' => max($cursor[$field['card_key']]['y'] ?? 0, $bottom)];
        }
    }

    foreach ($fields as &$field) {
        foreach (['pos_x', 'pos_y', 'pos_w', 'pos_h'] as $k) {
            if (isset($field[$k]) && is_numeric($field[$k])) {
                $field[$k] = (int)$field[$k];
            }
        }
        if ($is_placed($field)) {
            continue;
        }
        $name = (string)($field['name'] ?? '');
        $is_basic = (int)($field['is_basic'] ?? 0) === 1;
        $card = ($is_basic && isset($core_map[$name])) ? $core_map[$name]['card'] : 'custom_fields';
        if (!in_array($card, $card_keys, true)) {
            $card = 'custom_fields';
        }
        $width = ($is_basic && isset($core_map[$name])) ? (int)$core_map[$name]['w'] : $default_width;
        $width = min($columns, max(1, $width));

        $c = $cursor[$card] ?? ['x' => 0, 'y' => 0];
        if ($c['x'] > 0 && $c['x'] + $width > $columns) {
            $c = ['x' => 0, 'y' => $c['y'] + $field_height];
        }
        $field['card_key'] = $card;
        $field['pos_x'] = $c['x'];
        $field['pos_y'] = $c['y'];
        $field['pos_w'] = $width;
        $field['pos_h'] = $field_height;

        $c['x'] += $width;
        if ($c['x'] >= $columns) {
            $c = ['x' => 0, 'y' => $c['y'] + $field_height];
        }
        $cursor[$card] = $c;
    }
    unset($field);

    return $fields;
}

/**
 * Card tiles for the asset record (/ui/asset/layout, ledger ruling T2b):
 * the stored tiles that belong to the asset card set, plus a tile for every
 * card in $card_keys that has none, stacked full width below the lowest
 * stored tile. Each tile carries field_count from the (already placed)
 * $fields. $height_for_rows(rows) sizes a synthesized tile from the number
 * of field rows its card spans. Read-only.
 *
 * @param array<int,array<string,mixed>> $stored_cards
 * @param array<int,array<string,mixed>> $fields
 * @param string[] $card_keys
 * @return array<int,array<string,mixed>>
 */
function assets_ui_layout_cards(array $stored_cards, array $fields, array $card_keys, callable $height_for_rows, int $field_height = 2): array
{
    $counts = [];
    $bottoms = [];
    foreach ($fields as $field) {
        $card = $field['card_key'] ?? null;
        if (!is_string($card)) {
            continue;
        }
        $counts[$card] = ($counts[$card] ?? 0) + 1;
        $bottoms[$card] = max($bottoms[$card] ?? 0, (int)($field['pos_y'] ?? 0) + (int)($field['pos_h'] ?? $field_height));
    }

    $cards = [];
    $seen = [];
    $next_y = 0;
    foreach ($stored_cards as $card) {
        $key = $card['card_key'] ?? null;
        if (!in_array($key, $card_keys, true) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $tile = [
            'card_key' => $key,
            'pos_x' => (int)($card['pos_x'] ?? 0),
            'pos_y' => (int)($card['pos_y'] ?? 0),
            'pos_w' => (int)($card['pos_w'] ?? 12),
            'pos_h' => (int)($card['pos_h'] ?? 1),
        ];
        $next_y = max($next_y, $tile['pos_y'] + $tile['pos_h']);
        $cards[] = $tile;
    }

    foreach ($card_keys as $key) {
        if (isset($seen[$key])) {
            continue;
        }
        $rows = (int)ceil(($bottoms[$key] ?? 0) / $field_height);
        $height = max(1, (int)$height_for_rows($rows));
        $cards[] = ['card_key' => $key, 'pos_x' => 0, 'pos_y' => $next_y, 'pos_w' => 12, 'pos_h' => $height];
        $next_y += $height;
    }

    foreach ($cards as &$tile) {
        $tile['field_count'] = (int)($counts[$tile['card_key']] ?? 0);
    }
    unset($tile);

    return $cards;
}

/**
 * The look-back window (days) GET /assets/{id}/audit-trail answers for: one of
 * ASSETS_AUDIT_TRAIL_WINDOWS (past week/month/quarter/year). Anything else --
 * missing, non-numeric, negative, an unlisted number -- falls back to the
 * default (a year: an asset changes rarely, so a week would usually be empty).
 */
const ASSETS_AUDIT_TRAIL_WINDOWS = [7, 30, 90, 365];
const ASSETS_AUDIT_TRAIL_DEFAULT_DAYS = 365;

/** @param mixed $raw */
function assets_audit_trail_days($raw): int
{
    if (is_int($raw) || (is_string($raw) && preg_match('/^\d{1,4}$/', $raw))) {
        $days = (int)$raw;
        if (in_array($days, ASSETS_AUDIT_TRAIL_WINDOWS, true)) {
            return $days;
        }
    }
    return ASSETS_AUDIT_TRAIL_DEFAULT_DAYS;
}
