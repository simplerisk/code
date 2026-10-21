<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Filtered, sorted, paged asset listing backing GET /api/v2/assets (asset
 * management redesign, Phase A, Task 5).
 *
 * All logic lives in plain functions so it is unit-testable without HTTP:
 *
 *   assets_list_normalize_filter($raw)            sanitize request filters
 *   assets_build_filter_query($filter, $ignore_verified)  SQL WHERE + params (reused by bulk-by-filter actions)
 *   assets_list_matching_ids($filter)             ids matching a filter (reused by bulk-by-filter actions)
 *   assets_list($filter, $opts)                   the paged listing + counts
 *
 * Design notes:
 *  - name/ip/details are encrypted when the Encryption Extra is active, so the
 *    free-text `q` search cannot run as SQL LIKE on ciphertext. Candidate rows
 *    (id, verified, name, ip, sort keys) are fetched under the SQL-expressible
 *    filters + team separation, decrypted, and `q` / sorting / paging are
 *    applied in PHP over that WHOLE candidate set. The same code path runs with
 *    encryption on and off, and counts/paging are computed from the same set.
 *  - Sorting is always done over the whole filtered set (never just a page),
 *    including custom fields.
 *  - That PHP path is only needed when the Encryption Extra is active, a free-text
 *    `q` or an Asset Scoring / rating filter is set, or the sort is a scoring or
 *    custom column. Otherwise (assets_list_needs_php_filtering() is false) the
 *    database does the work: tab counts and totals are COUNT queries, the id /
 *    verified / created / valuation sorts are ORDER BY ... LIMIT queries, the
 *    name / IP sorts fetch only that one sort column, facet counts are GROUP BY
 *    queries and bulk-by-filter ids are one SELECT. Results are identical to the
 *    PHP path (the natural, case-insensitive name / IP order included), which
 *    tests compare directly. The PHP path reads every matching row, so its cost
 *    grows linearly with the inventory; it is sized for tens of thousands of
 *    assets, not millions.
 *  - Asset Scoring results (categorization, weighted score/band) are never
 *    stored: they are filtered, sorted and returned by asset_scoring_compute()
 *    over the same candidate set, so a settings change re-scores instantly and
 *    there is no SQL scoring expression to drift from the PHP one (ruling G1).
 */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/assets_page_rules.php'));
require_once(realpath(__DIR__ . '/assets_write_rules.php'));
// check_permission() (Governance-gated control names).
require_once(realpath(__DIR__ . '/permissions.php'));
// Asset Scoring: the one scoring function the list filters, sorts and renders with.
require_once(realpath(__DIR__ . '/asset_scoring.php'));

/**
 * Sanitizes raw request filters into the canonical filter array.
 *
 * @param array<string,mixed> $raw
 * @return array{verified:?int,q:string,location:int[],team:int[],tag:int[],group:int[],valuation:int[],categorization:int[],band:int[],confidentiality:int[],integrity:int[],availability:int[]}
 */
function assets_list_normalize_filter(array $raw): array
{
    $ids = static function ($v): array {
        if (is_array($v)) {
            $parts = $v;
        } elseif (is_scalar($v)) {
            $parts = explode(',', (string)$v);
        } else {
            $parts = [];
        }
        $out = [];
        foreach ($parts as $p) {
            if (!is_scalar($p)) {
                continue;
            }
            $p = trim((string)$p);
            if ($p !== '' && ctype_digit($p)) {
                $out[(int)$p] = (int)$p;
            }
        }
        // One bound placeholder per id: cap the list well below MySQL's limit.
        return array_slice(array_values($out), 0, 500);
    };
    // Asset Scoring level ids (1 Low, 2 Moderate, 3 High); anything else is dropped,
    // which the bulk lost-key check turns into a refusal (never a wider set).
    $levels = static fn($v): array => array_values(array_filter($ids($v), static fn(int $id): bool => $id >= 1 && $id <= count(ASSET_SCORING_LEVELS)));
    // An objective's rating filter keeps only the ids that objective offers
    // (asset_scoring_filter_ids(): Not applicable, id 0, for confidentiality
    // only); anything else is dropped exactly like a level id.
    $ratings = static fn($v, string $objective): array => array_values(array_filter($ids($v), static fn(int $id): bool => in_array($id, asset_scoring_filter_ids($objective), true)));

    return [
        // Shared with the bulk validator: a JSON false is unverified, not "absent".
        'verified' => assets_parse_verified($raw['verified'] ?? null),
        'q' => (isset($raw['q']) && is_scalar($raw['q'])) ? trim((string)$raw['q']) : '',
        'location' => $ids($raw['location'] ?? []),
        'team' => $ids($raw['team'] ?? []),
        'tag' => $ids($raw['tag'] ?? []),
        'group' => $ids($raw['group'] ?? []),
        // asset_values ids (the asset's valuation level)
        'valuation' => $ids($raw['valuation'] ?? []),
        // FIPS categorization / weighted band, by level id (Asset Scoring)
        'categorization' => $levels($raw['categorization'] ?? []),
        'band' => $levels($raw['band'] ?? []),
        // The stored ratings, by filter id (Asset Scoring)
        'confidentiality' => $ratings($raw['confidentiality'] ?? [], 'confidentiality'),
        'integrity' => $ratings($raw['integrity'] ?? [], 'integrity'),
        'availability' => $ratings($raw['availability'] ?? [], 'availability'),
    ];
}

/**
 * The tag names of one asset from its GROUP_CONCAT (unit-separator joined,
 * because tag names can contain commas). Pure.
 *
 * @param mixed $raw
 * @return string[]
 */
function assets_list_split_tags($raw): array
{
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    return array_values(array_filter(explode("\x1F", $raw), static fn($t) => $t !== ''));
}

/**
 * Rows per query for id-list lookups (one bound placeholder per id; MySQL caps
 * a prepared statement at 65,535). Tests lower it through the
 * $GLOBALS['assets_list_chunk_size_override'] hook.
 */
function assets_list_chunk_size(): int
{
    $override = $GLOBALS['assets_list_chunk_size_override'] ?? null;
    return (is_int($override) && $override > 0) ? $override : 1000;
}

/**
 * Builds the SQL-expressible part of the filter for `assets a`. The free-text
 * `q` is NOT part of the SQL (see file header) and is returned separately for
 * the caller to apply after decryption. Team separation is always included
 * (fail-open when the Separation Extra is off or the caller is admin).
 *
 * @param array<string,mixed> $filter  Output of assets_list_normalize_filter()
 * @param bool $ignore_verified  Omit the verified predicate (used for counts)
 * @return array{where:string,params:array<string,mixed>,q:string}
 */
function assets_build_filter_query(array $filter, bool $ignore_verified = false): array
{
    $filter = assets_list_normalize_filter($filter);
    $params = [];
    $where = 'WHERE 1';

    if (!$ignore_verified && $filter['verified'] !== null) {
        $where .= ' AND `a`.`verified` = :verified';
        $params['verified'] = $filter['verified'];
    }

    foreach (['location' => 'location', 'team' => 'teams'] as $key => $column) {
        if (!empty($filter[$key])) {
            $ors = [];
            foreach ($filter[$key] as $i => $id) {
                $ors[] = "FIND_IN_SET(:{$key}_{$i}, `a`.`{$column}`)";
                $params["{$key}_{$i}"] = $id;
            }
            $where .= ' AND (' . implode(' OR ', $ors) . ')';
        }
    }

    if (!empty($filter['valuation'])) {
        $in = [];
        foreach ($filter['valuation'] as $i => $id) {
            $in[] = ":valuation_{$i}";
            $params["valuation_{$i}"] = $id;
        }
        $where .= ' AND `a`.`value` IN (' . implode(',', $in) . ')';
    }

    if (!empty($filter['tag'])) {
        $in = [];
        foreach ($filter['tag'] as $i => $id) {
            $in[] = ":tag_{$i}";
            $params["tag_{$i}"] = $id;
        }
        $where .= " AND `a`.`id` IN (SELECT `tt`.`taggee_id` FROM `tags_taggees` tt WHERE `tt`.`type` = 'asset' AND `tt`.`tag_id` IN (" . implode(',', $in) . '))';
    }

    if (!empty($filter['group'])) {
        $in = [];
        foreach ($filter['group'] as $i => $id) {
            $in[] = ":group_{$i}";
            $params["group_{$i}"] = $id;
        }
        $where .= ' AND `a`.`id` IN (SELECT `aag`.`asset_id` FROM `assets_asset_groups` aag WHERE `aag`.`asset_group_id` IN (' . implode(',', $in) . '))';
    }

    $where .= call_extra_function(
        'team_separation_extra',
        __DIR__ . '/../extras/separation/index.php',
        'get_user_teams_query_for_assets',
        ['a', false, true],
        ''
    );

    return ['where' => $where, 'params' => $params, 'q' => $filter['q']];
}

/**
 * Whether answering this filter needs the rows in PHP: the Encryption Extra
 * encrypts name / IP (no SQL LIKE or ORDER BY on ciphertext), the free-text `q`
 * is a case-insensitive, accent-sensitive substring match, and the Asset
 * Scoring level / rating filters are computed, never stored. Everything else
 * is SQL. $GLOBALS['assets_list_force_php_path'] is a TEST-ONLY hook (nothing in
 * production sets it): it forces the PHP path so tests can compare the two.
 *
 * @param array<string,mixed> $filter  Output of assets_list_normalize_filter()
 */
function assets_list_needs_php_filtering(array $filter): bool
{
    if (!empty($GLOBALS['assets_list_force_php_path'])) {
        return true;
    }
    return encryption_extra()
        || $filter['q'] !== ''
        || !empty($filter['categorization']) || !empty($filter['band'])
        || !empty($filter['confidentiality']) || !empty($filter['integrity']) || !empty($filter['availability']);
}

/**
 * SQL path: asset counts by verified state under the filter (the verified
 * predicate left out, as the tab counts need), keyed by the stored value.
 *
 * @param array<string,mixed> $filter  Output of assets_list_normalize_filter()
 * @return array<int,int>
 */
function assets_list_sql_counts_by_verified(array $filter): array
{
    $built = assets_build_filter_query($filter, true);
    $db = db_open();
    $stmt = $db->prepare("SELECT `a`.`verified`, COUNT(*) FROM `assets` a {$built['where']} GROUP BY `a`.`verified`");
    $stmt->execute($built['params']);
    $by = [];
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) {
        $by[(int)$r[0]] = (int)$r[1];
    }
    db_close($db);
    return $by;
}

/**
 * SQL path: ids matching the filter (the verified predicate included), ordered
 * by one of the columns the database can order exactly as the PHP path does
 * (id, verified, created, valuation rank), ties broken by ascending id, and
 * optionally limited to one page.
 *
 * @param array<string,mixed> $filter  Output of assets_list_normalize_filter()
 * @return int[]
 */
function assets_list_sql_page_ids(array $filter, string $sort, string $dir, int $offset, int $limit): array
{
    $built = assets_build_filter_query($filter, false);
    $join = '';
    switch ($sort) {
        case 'verified':
            $order = '`a`.`verified`';
            break;
        case 'created':
            $order = '`a`.`created`';
            break;
        case 'value':
            // The level's rank (lowest range first); -1 when the stored value matches no level.
            $join = 'LEFT JOIN `asset_values` av ON `av`.`id` = `a`.`value`';
            $order = 'IF(`av`.`id` IS NULL, -1, (SELECT COUNT(*) FROM `asset_values` v2 WHERE `v2`.`min_value` < `av`.`min_value` OR (`v2`.`min_value` = `av`.`min_value` AND `v2`.`id` < `av`.`id`)))';
            break;
        default:
            $order = '`a`.`id`';
    }
    $dir_sql = $dir === 'desc' ? 'DESC' : 'ASC';
    $limit_sql = $limit > 0 ? 'LIMIT ' . max(0, $offset) . ', ' . $limit : '';
    $db = db_open();
    $stmt = $db->prepare("SELECT `a`.`id` FROM `assets` a {$join} {$built['where']} ORDER BY {$order} {$dir_sql}, `a`.`id` ASC {$limit_sql}");
    $stmt->execute($built['params']);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    db_close($db);
    return $ids;
}

/**
 * SQL path: id => text of one column (name or ip) for every asset matching the
 * filter (the verified predicate included). Only that column is read; the
 * caller sorts it naturally in PHP because SQL has no natural order.
 *
 * @param array<string,mixed> $filter  Output of assets_list_normalize_filter()
 * @return array<int,string>
 */
function assets_list_sql_text_keys(array $filter, string $column): array
{
    $column = $column === 'ip' ? 'ip' : 'name';
    $built = assets_build_filter_query($filter, false);
    $db = db_open();
    $stmt = $db->prepare("SELECT `a`.`id`, `a`.`{$column}` FROM `assets` a {$built['where']}");
    $stmt->execute($built['params']);
    $keys = [];
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) {
        $keys[(int)$r[0]] = (string)($r[1] ?? '');
    }
    db_close($db);
    return $keys;
}

/**
 * Orders ids by their sort keys, ties by ascending id: the same order as the
 * comparator in assets_list()'s PHP path (ints numerically, strings naturally
 * and case-insensitively), done in C by array_multisort() when every key is the
 * same plain type. A null key (an unscored asset) sorts last in either
 * direction, which only the comparator expresses, so any null falls back to it.
 *
 * @param int[] $ids
 * @param array<int,int|string|null> $keyOf  id => sort key
 * @return int[]
 */
function assets_list_order_ids(array $ids, array $keyOf, string $dir): array
{
    $ints = $strings = true;
    foreach ($ids as $id) {
        $k = $keyOf[$id];
        $ints = $ints && is_int($k);
        $strings = $strings && is_string($k);
    }
    if ($ids !== [] && ($ints || $strings)) {
        $keys = [];
        foreach ($ids as $id) {
            $keys[] = $keyOf[$id];
        }
        array_multisort(
            $keys,
            $dir === 'desc' ? SORT_DESC : SORT_ASC,
            $ints ? SORT_NUMERIC : (SORT_NATURAL | SORT_FLAG_CASE),
            $ids,
            SORT_ASC,
            SORT_NUMERIC
        );
        return $ids;
    }
    $mult = $dir === 'desc' ? -1 : 1;
    usort($ids, static function ($x, $y) use ($keyOf, $mult) {
        $a = $keyOf[$x];
        $b = $keyOf[$y];
        // A missing value (an unscored asset) sorts last in BOTH directions.
        if ($a === null || $b === null) {
            if ($a === $b) {
                return $x <=> $y;
            }
            return $a === null ? 1 : -1;
        }
        $c = (is_int($a) && is_int($b)) ? ($a <=> $b) : strnatcasecmp((string)$a, (string)$b);
        return $c !== 0 ? $c * $mult : ($x <=> $y);
    });
    return $ids;
}

/**
 * Candidate rows (decrypted name/ip) matching the filter. `q` is applied in PHP
 * as a case-insensitive substring match on name and IP, the Asset Scoring
 * `categorization` / `band` filters through asset_scoring_compute() (an
 * unscored asset never matches one), and the `confidentiality` / `integrity`
 * / `availability` rating filters on the stored selection itself (an objective
 * that is not set never matches one). Every filter combines with AND. Rows
 * carry the three scoring selections once the scoring columns exist.
 *
 * @param array<string,mixed> $filter  raw or normalised (bulk passes its raw filter)
 * @return array<int,array<string,mixed>>
 */
function assets_list_candidates(array $filter, bool $ignore_verified = false): array
{
    $filter = assets_list_normalize_filter($filter);
    $scoring = asset_scoring_schema_ready();
    $level_filter = (bool)($filter['categorization'] || $filter['band']);
    $rating_filter = (bool)($filter['confidentiality'] || $filter['integrity'] || $filter['availability']);
    if (($level_filter || $rating_filter) && !$scoring) {
        // No scoring columns yet (upgrade not run): nothing is scored, so
        // nothing matches. The narrower set is the safe answer.
        return [];
    }
    $scoring_cols = $scoring ? ', `a`.`confidentiality`, `a`.`integrity`, `a`.`availability`' : '';
    // Read once for the whole candidate set, never per row.
    $settings = $level_filter ? asset_scoring_settings() : null;

    $built = assets_build_filter_query($filter, $ignore_verified);
    $encryption = encryption_extra();
    if ($encryption) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }

    $db = db_open();
    $stmt = $db->prepare("
        SELECT `a`.`id`, `a`.`verified`, `a`.`name`, `a`.`ip`, `a`.`value`, `a`.`created`, `a`.`location`, `a`.`teams`{$scoring_cols}
        FROM `assets` a
        {$built['where']}
    ");
    $stmt->execute($built['params']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $q = $built['q'];
    $out = [];
    foreach ($rows as $row) {
        $row['id'] = (int)$row['id'];
        $row['verified'] = (int)$row['verified'];
        $row['name'] = (string)($row['name'] ?? '');
        $row['ip'] = (string)($row['ip'] ?? '');
        if ($encryption) {
            $row['name'] = (string)try_decrypt($row['name']);
            $row['ip'] = (string)try_decrypt($row['ip']);
        }
        if ($q !== '' && mb_stripos($row['name'], $q) === false && mb_stripos($row['ip'], $q) === false) {
            continue;
        }
        if ($rating_filter && !asset_scoring_row_matches_objective_filters($row, $filter)) {
            continue;
        }
        if ($level_filter) {
            $result = asset_scoring_compute($row, $settings);
            if ($filter['categorization'] && !in_array(asset_scoring_level_rank($result['categorization']), $filter['categorization'], true)) {
                continue;
            }
            if ($filter['band'] && !in_array(asset_scoring_level_rank($result['band']), $filter['band'], true)) {
                continue;
            }
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Ids of every asset matching the filter (all pages), in ascending id order.
 * Reused by bulk-by-filter actions.
 *
 * @param array<string,mixed> $filter
 * @return int[]
 */
function assets_list_matching_ids(array $filter): array
{
    $norm = assets_list_normalize_filter($filter);
    if (!assets_list_needs_php_filtering($norm)) {
        return assets_list_sql_page_ids($norm, 'id', 'asc', 0, 0);
    }
    $ids = array_column(assets_list_candidates($filter), 'id');
    sort($ids);
    return $ids;
}

/**
 * Active custom fields for assets keyed by id. Empty when the Customization
 * Extra is off or its table is absent (Core must not query it unguarded).
 * Built-in fields (is_basic = 1: name, IP address, valuation, ...) are rows in
 * the same custom_fields table but are NOT custom fields -- they are the core
 * asset columns -- so they are excluded here (see assets_list_real_custom_fields()).
 *
 * @return array<int,array<string,mixed>>
 */
function assets_list_custom_fields(): array
{
    if (!customization_extra() || !table_exists('custom_asset_data')) {
        return [];
    }
    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
    return assets_list_real_custom_fields((array)get_active_fields('asset'));
}

/**
 * Keeps only real custom fields (is_basic falsy) from an active-field list,
 * keyed by id. Pure, so the is_basic rule is unit-testable without the Extra.
 *
 * @param array<int,array<string,mixed>> $active_fields
 * @return array<int,array<string,mixed>>
 */
function assets_list_real_custom_fields(array $active_fields): array
{
    $fields = [];
    foreach ($active_fields as $f) {
        if (!is_array($f) || !isset($f['id']) || !empty($f['is_basic'])) {
            continue;
        }
        $fields[(int)$f['id']] = $f;
    }
    return $fields;
}

/**
 * Option value => label map for a dropdown-type custom field, built once per
 * field per request (table order preserved). Empty for non-option types.
 *
 * @param array<string,mixed> $field
 * @return array<int,string>
 */
function assets_list_option_labels(array $field): array
{
    $id = (int)$field['id'];
    switch ($field['type']) {
        case 'dropdown':
        case 'multidropdown':
            $table = "custom_field_{$id}";
            break;
        case 'user_multidropdown':
            $table = 'user';
            break;
        default:
            return [];
    }
    if (!table_exists($table)) {
        return [];
    }
    $db = db_open();
    $stmt = $db->prepare("SELECT `value`, `name` FROM `{$table}` ORDER BY `value`");
    $stmt->execute();
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[(int)$r['value']] = (string)$r['name'];
    }
    db_close($db);
    return $map;
}

/**
 * Plain (unescaped, decrypted) display label of a stored custom value. Option
 * types are resolved through the pre-built $labels map (no per-row queries).
 *
 * @param array<string,mixed> $field
 * @param array<int,string> $labels  From assets_list_option_labels()
 */
function assets_list_custom_display(array $field, $value, array $labels = []): string
{
    $value = (string)($value ?? '');
    switch ($field['type']) {
        case 'dropdown':
            return $labels[(int)$value] ?? '';
        case 'multidropdown':
        case 'user_multidropdown':
            $wanted = array_flip(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
            $names = [];
            foreach ($labels as $v => $label) {
                if (isset($wanted[(string)$v])) {
                    $names[] = $label;
                }
            }
            return implode(', ', $names);
        case 'shorttext':
        case 'longtext':
            return ((string)$field['encryption'] === '1') ? (string)try_decrypt($value) : $value;
        case 'date':
            return (string)format_date($value);
        default:
            return $value;
    }
}

/**
 * Sort key of a custom value. The Customization Extra supports dropdown,
 * multidropdown, shorttext, longtext, date, user_multidropdown and hyperlink;
 * there is no numeric type. Dates sort by the stored value normalised to
 * Y-m-d (the display format, e.g. m/d/Y, does not sort chronologically);
 * every other type sorts naturally and case-insensitively by its display label.
 * Empty/invalid dates sort as ''.
 */
function assets_list_custom_sort_key(string $type, $raw, string $display): string
{
    if ($type === 'date') {
        $raw = (string)($raw ?? '');
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return '';
        }
        $t = strtotime($raw);
        return $t ? date('Y-m-d', $t) : '';
    }
    return $display;
}

/**
 * Stored (raw) custom values for the given assets and fields, looked up in
 * id chunks.
 *
 * @param int[] $asset_ids
 * @param array<int,array<string,mixed>> $fields
 * @return array<int,array<int,string>>  [asset_id][field_id] => raw stored value
 */
function assets_list_custom_raw_values(array $asset_ids, array $fields): array
{
    if (!$asset_ids || !$fields || !table_exists('custom_asset_data')) {
        return [];
    }
    $f_params = [];
    $f_in = [];
    foreach (array_keys($fields) as $i => $id) {
        $f_in[] = ":f{$i}";
        $f_params["f{$i}"] = (int)$id;
    }
    $out = [];
    $db = db_open();
    foreach (array_chunk(array_values($asset_ids), assets_list_chunk_size()) as $chunk) {
        $params = $f_params;
        $a_in = [];
        foreach ($chunk as $i => $id) {
            $a_in[] = ":a{$i}";
            $params["a{$i}"] = (int)$id;
        }
        $stmt = $db->prepare('SELECT `asset_id`, `field_id`, `value` FROM `custom_asset_data` WHERE `asset_id` IN (' . implode(',', $a_in) . ') AND `field_id` IN (' . implode(',', $f_in) . ')');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['asset_id']][(int)$r['field_id']] = (string)$r['value'];
        }
    }
    db_close($db);
    return $out;
}

/**
 * Display labels of custom values for the given assets and fields.
 *
 * @param int[] $asset_ids
 * @param array<int,array<string,mixed>> $fields
 * @return array<int,array<int,string>>  [asset_id][field_id] => display string
 */
function assets_list_custom_values(array $asset_ids, array $fields): array
{
    $labels = [];
    foreach ($fields as $fid => $field) {
        $labels[$fid] = assets_list_option_labels($field);
    }
    $out = [];
    foreach (assets_list_custom_raw_values($asset_ids, $fields) as $aid => $byField) {
        foreach ($byField as $fid => $raw) {
            $out[$aid][$fid] = assets_list_custom_display($fields[$fid], $raw, $labels[$fid]);
        }
    }
    return $out;
}

/**
 * Rank of every asset_values row, lowest valuation first (min_value, then id:
 * the order the valuation lookups and the Asset groups tab's "highest
 * valuation" use). The ids themselves carry no order -- an administrator can
 * redefine the ranges -- so sorting by valuation sorts by this rank.
 *
 * @return array<int,int>  asset_values id => rank (0 = lowest)
 */
function assets_list_valuation_ranks(): array
{
    $db = db_open();
    $stmt = $db->prepare("SELECT `id` FROM `asset_values` ORDER BY `min_value`, `id`");
    $stmt->execute();
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    db_close($db);
    return array_flip($ids);
}

/**
 * Sort key of an asset's valuation: its level's rank (lowest range first),
 * or -1 when the stored value matches no level, so it sorts below them all.
 * Never the asset_values id itself -- ids do not follow the ranges.
 *
 * @param array<int,int> $ranks  assets_list_valuation_ranks()
 */
function assets_list_valuation_sort_key(int $value, array $ranks): int
{
    return $ranks[$value] ?? -1;
}

/**
 * Sortable column keys: fixed base keys, the Asset Scoring columns (once their
 * database columns exist), plus the active custom fields.
 *
 * @return string[]
 */
function assets_list_sortable_keys(): array
{
    $keys = ['id', 'name', 'ip', 'value', 'verified', 'created'];
    if (asset_scoring_schema_ready()) {
        $keys = array_merge($keys, ASSET_SCORING_COLUMNS);
    }
    foreach (array_keys(assets_list_custom_fields()) as $id) {
        $keys[] = "custom_field_{$id}";
    }
    return $keys;
}

/**
 * The paged asset listing.
 *
 * $opts: sort (string, default 'name'), dir ('asc'|'desc'), page (1-based),
 *        per_page (0 = every row, max 500), columns (string[]|null)
 *
 * @param array<string,mixed> $filter
 * @param array<string,mixed> $opts
 * @return array{assets:array<int,array<string,mixed>>,total:int,page:int,per_page:int,counts:array{all:int,verified:int,unverified:int},sort:array{key:string,dir:string},sortable_columns:string[]}
 */
function assets_list(array $filter, array $opts = []): array
{
    $filter = assets_list_normalize_filter($filter);

    $custom_fields = assets_list_custom_fields();
    $sortable = assets_list_sortable_keys();
    $sort = (isset($opts['sort']) && is_scalar($opts['sort'])) ? (string)$opts['sort'] : 'name';
    if (!in_array($sort, $sortable, true)) {
        $sort = 'name';
    }
    $dir = (isset($opts['dir']) && is_scalar($opts['dir']) && strtolower((string)$opts['dir']) === 'desc') ? 'desc' : 'asc';
    $per_page = (isset($opts['per_page']) && is_scalar($opts['per_page'])) ? (int)$opts['per_page'] : 0;
    $per_page = max(0, min(500, $per_page));
    $page = max(1, (isset($opts['page']) && is_scalar($opts['page'])) ? (int)$opts['page'] : 1);
    if ($per_page === 0) {
        $page = 1;
    }

    // Counts ignore the verified filter but respect everything else.
    $base = null;
    $sql = !assets_list_needs_php_filtering($filter);
    if ($sql) {
        $by = assets_list_sql_counts_by_verified($filter);
        $all_n = (int)array_sum($by);
        $verified_n = $by[1] ?? 0;
        $filtered_n = $filter['verified'] === null ? $all_n : ($by[$filter['verified']] ?? 0);
    } else {
        $base = assets_list_candidates($filter, true);
        $verified_n = 0;
        foreach ($base as $r) {
            if ($r['verified'] === 1) {
                $verified_n++;
            }
        }
        $all_n = count($base);
        $filtered_n = null;
    }
    $counts = ['all' => $all_n, 'verified' => $verified_n, 'unverified' => $all_n - $verified_n];

    // Sorting -- always over the whole filtered set, paged afterwards.
    if ($sql && in_array($sort, ['id', 'verified', 'created', 'value'], true)) {
        $total = (int)$filtered_n;
        $page_ids = assets_list_sql_page_ids($filter, $sort, $dir, ($page - 1) * $per_page, $per_page);
    } elseif ($sql && in_array($sort, ['name', 'ip'], true)) {
        $keyOf = assets_list_sql_text_keys($filter, $sort);
        $ordered = assets_list_order_ids(array_keys($keyOf), $keyOf, $dir);
        $total = count($ordered);
        $page_ids = $per_page > 0 ? array_slice($ordered, ($page - 1) * $per_page, $per_page) : $ordered;
    } else {
        $base ??= assets_list_candidates($filter, true);
        $cands = $base;
        if ($filter['verified'] !== null) {
            $cands = array_values(array_filter($base, static fn($r) => $r['verified'] === $filter['verified']));
        }
        $keyOf = [];
        if (in_array($sort, ASSET_SCORING_COLUMNS, true)) {
            // Level rank / score hundredths; null (unscored) sorts last either way.
            $scoring_settings = asset_scoring_settings();
            foreach ($cands as $r) {
                $keyOf[$r['id']] = asset_scoring_sort_key($sort, $r, $scoring_settings);
            }
        } elseif (str_starts_with($sort, 'custom_field_')) {
            $fid = (int)substr($sort, strlen('custom_field_'));
            $field = $custom_fields[$fid];
            $labels = assets_list_option_labels($field);
            $raws = assets_list_custom_raw_values(array_column($cands, 'id'), [$fid => $field]);
            foreach ($cands as $r) {
                $raw = $raws[$r['id']][$fid] ?? '';
                $keyOf[$r['id']] = assets_list_custom_sort_key((string)$field['type'], $raw, assets_list_custom_display($field, $raw, $labels));
            }
        } else {
            // Valuation sorts by the level's numeric range, not its row id; an
            // asset whose value matches no level sorts below the lowest one.
            $ranks = $sort === 'value' ? assets_list_valuation_ranks() : [];
            foreach ($cands as $r) {
                switch ($sort) {
                    case 'id':
                        $keyOf[$r['id']] = $r['id'];
                        break;
                    case 'verified':
                        $keyOf[$r['id']] = $r['verified'];
                        break;
                    case 'value':
                        $keyOf[$r['id']] = assets_list_valuation_sort_key((int)$r['value'], $ranks);
                        break;
                    case 'created':
                        $keyOf[$r['id']] = (string)$r['created'];
                        break;
                    case 'ip':
                        $keyOf[$r['id']] = $r['ip'];
                        break;
                    default: // name: order_by_name is the SQL-sortable proxy under encryption, but decrypted name is authoritative
                        $keyOf[$r['id']] = $r['name'];
                }
            }
        }
        $ordered = assets_list_order_ids(array_column($cands, 'id'), $keyOf, $dir);
        $total = count($ordered);
        $page_ids = $per_page > 0 ? array_slice($ordered, ($page - 1) * $per_page, $per_page) : $ordered;
    }

    $result = [
        'assets' => assets_list_load_rows($page_ids, $opts['columns'] ?? null, $custom_fields),
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'counts' => $counts,
        'sort' => ['key' => $sort, 'dir' => $dir],
        'sortable_columns' => $sortable,
    ];
    if (!empty($opts['facet_counts'])) {
        $result['facet_counts'] = assets_list_facet_counts($filter);
    }
    return $result;
}

/**
 * Per-value counts for the asset picker's facets (team, then location, then
 * valuation), each counted inside the choices of the facets before it
 * (assets_facet_chain_counts()). The scope is the filter WITHOUT those three
 * facets (search, verified, tag, group and the Asset Scoring filters still apply)
 * under the caller's team separation; the first value of each facet list is
 * the facet's choice.
 * Team counts are limited to the teams the caller may see, so the ids of
 * other teams are never disclosed.
 *
 * @param array<string,mixed> $filter  Output of assets_list_normalize_filter()
 * @return array<string,array{all:int,values:array<int,int>}>
 */
function assets_list_facet_counts(array $filter): array
{
    $order = ['team', 'location', 'valuation'];
    $scope_filter = $filter;
    $selected = [];
    foreach ($order as $k) {
        $selected[$k] = $filter[$k][0] ?? null;
        $scope_filter[$k] = [];
    }
    $csv = static fn($v): array => array_values(array_filter(array_map('intval', explode(',', (string)$v))));
    $rows = [];
    if (!assets_list_needs_php_filtering($scope_filter)) {
        // One row per distinct team / location / valuation combination, weighted by its asset count.
        $built = assets_build_filter_query($scope_filter, false);
        $db = db_open();
        $stmt = $db->prepare("SELECT `a`.`teams`, `a`.`location`, `a`.`value`, COUNT(*) FROM `assets` a {$built['where']} GROUP BY `a`.`teams`, `a`.`location`, `a`.`value`");
        $stmt->execute($built['params']);
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $g) {
            $rows[] = ['team' => $csv($g[0] ?? ''), 'location' => $csv($g[1] ?? ''), 'valuation' => [(int)$g[2]], 'n' => (int)$g[3]];
        }
        db_close($db);
    } else {
        foreach (assets_list_candidates($scope_filter) as $c) {
            $rows[] = ['team' => $csv($c['teams'] ?? ''), 'location' => $csv($c['location'] ?? ''), 'valuation' => [(int)$c['value']]];
        }
    }
    $counts = assets_facet_chain_counts($rows, $selected, $order);
    $visible = array_flip(array_map('intval', array_column(get_teams_by_login_user(), 'value')));
    $counts['team']['values'] = array_intersect_key($counts['team']['values'], $visible);
    return $counts;
}

/**
 * Loads full rows for the page's ids (preserving order), decrypts, and applies
 * column selection (id is always kept). Unknown columns are ignored. Once the
 * scoring columns exist, rows carry fips_categorization / weighted_score /
 * weighted_band computed by asset_scoring_compute() (null when unscored), for
 * every row when no columns are selected, else only when one is asked for.
 *
 * @param int[] $ids
 * @param string[]|null $columns
 * @param array<int,array<string,mixed>> $custom_fields
 * @return array<int,array<string,mixed>>
 */
function assets_list_load_rows(array $ids, $columns, array $custom_fields): array
{
    if (!$ids) {
        return [];
    }
    $encryption = encryption_extra();
    if ($encryption) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }
    $byId = [];
    $db = db_open();
    foreach (array_chunk(array_values($ids), assets_list_chunk_size()) as $chunk) {
        $params = [];
        $in = [];
        foreach ($chunk as $i => $id) {
            $in[] = ":i{$i}";
            $params["i{$i}"] = (int)$id;
        }
        $stmt = $db->prepare("
            SELECT `a`.*, GROUP_CONCAT(DISTINCT `tg`.`tag` ORDER BY `tg`.`tag` ASC SEPARATOR '\x1F') AS tags
            FROM `assets` a
                LEFT JOIN `tags_taggees` tt ON `tt`.`taggee_id` = `a`.`id` AND `tt`.`type` = 'asset'
                LEFT JOIN `tags` tg ON `tg`.`id` = `tt`.`tag_id`
            WHERE `a`.`id` IN (" . implode(',', $in) . ')
            GROUP BY `a`.`id`');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($encryption) {
                foreach (['ip', 'name', 'details'] as $k) {
                    $row[$k] = try_decrypt($row[$k]);
                }
            }
            // A tag may itself contain a comma ("PCI, SOX"), so the tags come
            // back as a list (tag_list); `tags` keeps its comma-joined string
            // form for existing API consumers.
            $row['tag_list'] = assets_list_split_tags($row['tags'] ?? null);
            $row['tags'] = implode(',', $row['tag_list']);
            $byId[(int)$row['id']] = $row;
        }
    }
    db_close($db);

    $wanted_custom = [];
    $derived = [];
    $keep = null;
    if (is_array($columns) && $columns) {
        $keep = ['id' => true];
        foreach ($columns as $c) {
            if (!is_scalar($c)) {
                continue;
            }
            $c = trim((string)$c);
            if (preg_match('/^custom_field_(\d+)$/', $c, $m)) {
                if (isset($custom_fields[(int)$m[1]])) {
                    $wanted_custom[(int)$m[1]] = $custom_fields[(int)$m[1]];
                    $keep[$c] = true;
                }
            } elseif ($c === 'associated_risks' || $c === 'mapped_controls') {
                $derived[$c] = true;
                $keep[$c] = true;
                $keep["{$c}_count"] = true;
            } elseif (in_array($c, ASSET_SCORING_RESULT_COLUMNS, true)) {
                if (asset_scoring_schema_ready()) {
                    $derived['scoring'] = true;
                    $keep[$c] = true;
                }
            } elseif ($byId && array_key_exists($c, reset($byId))) {
                $keep[$c] = true;
                if ($c === 'tags') {
                    $keep['tag_list'] = true;
                }
            }
        }
    }
    if ($keep === null && asset_scoring_schema_ready()) {
        $derived['scoring'] = true;
    }
    // Read once for the page, never per row.
    $scoring_settings = isset($derived['scoring']) ? asset_scoring_settings() : null;
    $custom_vals = assets_list_custom_values($ids, $wanted_custom);
    // Derived columns are computed only when requested, and only for this page.
    $risks = isset($derived['associated_risks']) ? assets_list_associated_risks($ids) : [];
    $controls = isset($derived['mapped_controls']) ? assets_list_mapped_controls($ids) : [];

    $out = [];
    foreach ($ids as $id) {
        if (!isset($byId[$id])) {
            continue;
        }
        $row = $byId[$id];
        foreach ($wanted_custom as $fid => $_) {
            $row["custom_field_{$fid}"] = $custom_vals[$id][$fid] ?? '';
        }
        if (isset($derived['associated_risks'])) {
            $row['associated_risks'] = $risks[$id]['items'] ?? [];
            $row['associated_risks_count'] = $risks[$id]['count'] ?? 0;
        }
        if (isset($derived['mapped_controls'])) {
            $row['mapped_controls'] = $controls[$id]['items'] ?? [];
            $row['mapped_controls_count'] = $controls[$id]['count'] ?? 0;
        }
        if (isset($derived['scoring'])) {
            $result = asset_scoring_compute($row, $scoring_settings);
            $row['fips_categorization'] = $result['categorization'];
            $row['weighted_score'] = $result['score'];
            $row['weighted_band'] = $result['band'];
        }
        if ($keep !== null) {
            $row = array_intersect_key($row, $keep);
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Risks associated with the given assets, visible to the caller only.
 *
 * Mirrors the legacy Manage assets column (the `associated_risks` select part
 * in functions.php): DIRECT links only (risks_to_assets), not risks linked
 * through the asset's groups. Unlike the legacy column, which showed every
 * linked risk's subject, each risk must pass check_access_for_risk() (Team
 * Separation), so risks the caller cannot see are never returned or counted,
 * and naming them needs the Risk Management permission (SR-2313): without it
 * `items` is empty and only `count` (of the risks the caller could see) is set.
 *
 * @param int[] $asset_ids  The page's asset ids
 * @return array<int,array{items:array<int,array{id:int,display_id:int,subject:string}>,count:int}>
 */
function assets_list_associated_risks(array $asset_ids): array
{
    if (!$asset_ids) {
        return [];
    }
    $can_name = assets_caller_can_see_associated_risks();
    $encryption = $can_name && encryption_extra();
    if ($encryption) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }
    $rows = [];
    $db = db_open();
    foreach (array_chunk(array_values($asset_ids), assets_list_chunk_size()) as $chunk) {
        $params = [];
        $in = [];
        foreach ($chunk as $i => $aid) {
            $in[] = ":a{$i}";
            $params["a{$i}"] = (int)$aid;
        }
        $stmt = $db->prepare('
            SELECT DISTINCT `rta`.`asset_id`, `r`.`id`, `r`.`subject`
            FROM `risks_to_assets` rta
                INNER JOIN `risks` r ON `r`.`id` = `rta`.`risk_id`
            WHERE `rta`.`asset_id` IN (' . implode(',', $in) . ')
            ORDER BY `r`.`id` ASC');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $row;
        }
    }
    db_close($db);

    $visible = array_flip(filter_accessible_risk_ids(array_column($rows, 'id')));
    $out = [];
    foreach ($rows as $row) {
        $rid = (int)$row['id'];
        if (!isset($visible[$rid])) {
            continue;
        }
        $aid = (int)$row['asset_id'];
        $out[$aid]['count'] = ($out[$aid]['count'] ?? 0) + 1;
        $out[$aid]['items'] = $out[$aid]['items'] ?? [];
        if ($can_name) {
            $subject = $encryption ? try_decrypt($row['subject']) : $row['subject'];
            $out[$aid]['items'][] = ['id' => $rid, 'display_id' => convert_to_risk_id($rid), 'subject' => (string)$subject];
        }
    }
    return $out;
}

/**
 * Framework controls mapped to the given assets.
 *
 * Mirrors the legacy Manage assets column: DIRECT links only
 * (control_to_assets), not controls mapped to the asset's groups, one entry
 * per control regardless of maturity. The legacy column had no gate; here the
 * caller needs the Governance permission to see control names.
 *
 * @param int[] $asset_ids  The page's asset ids
 * @return array<int,array{items:array<int,array{id:int,short_name:string}>,count:int}>
 */
function assets_list_mapped_controls(array $asset_ids): array
{
    if (!$asset_ids || !check_permission('governance')) {
        return [];
    }
    $out = [];
    $db = db_open();
    foreach (array_chunk(array_values($asset_ids), assets_list_chunk_size()) as $chunk) {
        $params = [];
        $in = [];
        foreach ($chunk as $i => $aid) {
            $in[] = ":a{$i}";
            $params["a{$i}"] = (int)$aid;
        }
        $stmt = $db->prepare('
            SELECT DISTINCT `cta`.`asset_id`, `fc`.`id`, `fc`.`short_name`
            FROM `control_to_assets` cta
                INNER JOIN `framework_controls` fc ON `fc`.`id` = `cta`.`control_id`
            WHERE `cta`.`asset_id` IN (' . implode(',', $in) . ')
            ORDER BY `fc`.`short_name` ASC, `fc`.`id` ASC');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['asset_id']]['items'][] = ['id' => (int)$row['id'], 'short_name' => (string)$row['short_name']];
        }
    }
    db_close($db);
    foreach ($out as $aid => $entry) {
        $out[$aid]['count'] = count($entry['items']);
    }
    return $out;
}


/**
 * Core Manage assets columns: key => language key of the label. `name` is
 * always on and cannot be toggled. Every key here is returnable through
 * GET /assets?columns=; mapped_controls and associated_risks are derived per
 * page (see assets_list_mapped_controls() / assets_list_associated_risks())
 * and are not sortable.
 *
 * @return array<string,string>
 */
function asset_core_column_labels(): array
{
    return [
        'name' => 'AssetName',
        'ip' => 'IPAddress',
        'value' => 'AssetValuation',
        'location' => 'SiteLocation',
        'teams' => 'Team',
        'details' => 'AssetDetails',
        'mapped_controls' => 'MappedControls',
        'tags' => 'Tags',
        'associated_risks' => 'AssociatedRisks',
        'verified' => 'Verified',
        'created' => 'CreationDate',
    ];
}

/**
 * Asset Scoring columns: key => lang key of the label. Offered only once the
 * scoring columns exist. FIPS Categorization and Weighted Score are default
 * columns (asset_default_column_keys()), the rest are opt-in; a saved layout
 * never gains a visible column (asset_normalize_column_settings()). Sortable
 * (unscored last); every one but the continuous Weighted Score is filterable.
 *
 * @return array<string,string>
 */
function asset_scoring_column_labels(): array
{
    return [
        'confidentiality' => 'Confidentiality',
        'integrity' => 'Integrity',
        'availability' => 'Availability',
        'fips_categorization' => 'FIPSCategorization',
        'weighted_score' => 'WeightedScore',
        'weighted_band' => 'WeightedBand',
    ];
}

/**
 * Columns shown when a user has saved nothing, in their default order: the
 * asset and its valuation, then its FIPS categorization and weighted score (the two Asset Scoring results; they drop out on their own until the
 * upgrade has added the scoring columns, since only allowed keys are shown).
 * Sized by measurement so the table (plus its fixed checkbox, Status and
 * actions columns) fits without scrolling sideways (with IP address and Team
 * as well it overran a 1440px window's 1150px scroller by 80px). IP address,
 * Team, Site / location, Asset details and Tags are one click away in the
 * Columns picker. A saved layout is loaded exactly as stored
 * (asset_load_column_settings()), so changing this list never changes what an
 * existing saved layout shows.
 *
 * @return string[]
 */
function asset_default_column_keys(): array
{
    return ['name', 'value', 'fips_categorization', 'weighted_score'];
}

/**
 * Every column a user may enable: core columns, the Asset Scoring columns
 * (once the upgrade has added them), plus one `custom_field_<id>` per active
 * asset custom field (only when the Customization Extra is active).
 *
 * @return array<int,array{key:string,label_key:?string,label:?string,group:string}>
 *         label_key is a $lang key for core columns; label is the raw field
 *         name for custom columns (the consumer escapes it exactly once).
 */
function asset_available_columns(): array
{
    $out = [];
    foreach (asset_core_column_labels() as $key => $label_key) {
        $out[] = ['key' => $key, 'label_key' => $label_key, 'label' => null, 'group' => 'asset'];
    }
    if (asset_scoring_schema_ready()) {
        foreach (asset_scoring_column_labels() as $key => $label_key) {
            $out[] = ['key' => $key, 'label_key' => $label_key, 'label' => null, 'group' => 'asset'];
        }
    }
    foreach (assets_list_custom_fields() as $id => $field) {
        $out[] = ['key' => "custom_field_{$id}", 'label_key' => null, 'label' => (string)$field['name'], 'group' => 'custom'];
    }
    return $out;
}

/** @return string[] */
function asset_allowed_column_keys(): array
{
    return array_column(asset_available_columns(), 'key');
}

/**
 * Sanitizes a raw settings value against $allowed and materializes a complete,
 * deterministic {columns, order}: every allowed key appears exactly once in
 * both lists and `name` is forced on and first. Keys the value does not list
 * take their default visibility -- except in a STORED layout ($stored true
 * with at least one column pair): there an unlisted key (one added since it
 * was saved: a new custom field, the Asset Scoring columns, a key that became
 * a default later) is off, so a saved layout never gains a visible column and
 * changing the defaults never changes it. Pure (no DB); the loader/saver
 * supply $allowed.
 *
 * @param mixed $raw
 * @param string[] $allowed
 * @return array{columns:array<int,array{0:string,1:string}>,order:string[]}
 */
function asset_normalize_column_settings($raw, array $allowed, bool $stored = false): array
{
    return assets_normalize_column_layout($raw, $allowed, asset_default_column_keys(), $stored);
}

/**
 * The rules of asset_normalize_column_settings() for any table's layout,
 * with that table's default columns: Manage assets and the Asset groups tab
 * (asset_group_normalize_column_settings()) share them. Pure.
 *
 * @param mixed $raw
 * @param string[] $allowed
 * @param string[] $defaults  the table's default columns, in their order
 * @return array{columns:array<int,array{0:string,1:string}>,order:string[]}
 */
function assets_normalize_column_layout($raw, array $allowed, array $defaults, bool $stored = false): array
{
    $clean = assets_sanitize_column_settings($raw, $allowed);
    $vis = [];
    foreach ($clean['columns'] as [$k, $v]) {
        $vis[$k] = $v;
    }
    $vis['name'] = '1';

    // Saved order (or the defaults when none), then everything else allowed.
    $order = ['name'];
    foreach (array_merge($clean['order'] ?: $defaults, $allowed) as $k) {
        if (in_array($k, $allowed, true) && !in_array($k, $order, true)) {
            $order[] = $k;
        }
    }

    // A stored layout lists what its user chose; anything it does not list is off.
    $saved_layout = $stored && $clean['columns'];
    $columns = [];
    foreach ($order as $k) {
        $columns[] = [$k, $vis[$k] ?? (!$saved_layout && in_array($k, $defaults, true) ? '1' : '0')];
    }
    return ['columns' => $columns, 'order' => $order];
}

/**
 * The user's saved Manage assets column settings, normalized against the
 * columns allowed right now (deleted custom fields drop out; columns added
 * since the save are off). Defaults when nothing is stored or the stored
 * value is unreadable. Read-only: the stored value is never rewritten here.
 *
 * @return array{columns:array<int,array{0:string,1:string}>,order:string[]}
 */
function asset_load_column_settings(int $user_id): array
{
    $stored = null;
    if (field_exists_in_table('custom_manage_assets_display_settings', 'user')) {
        $db = db_open();
        $stmt = $db->prepare('SELECT `custom_manage_assets_display_settings` FROM `user` WHERE `value` = :id');
        $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $json = $stmt->fetchColumn();
        db_close($db);
        if (is_string($json) && $json !== '') {
            $stored = json_decode($json, true);
        }
    }
    return asset_normalize_column_settings($stored, asset_allowed_column_keys(), true);
}

/**
 * Sanitizes and stores the user's column settings (through the shared
 * save_custom_risk_display_settings() helper) and returns what is now stored.
 *
 * @param mixed $raw
 * @throws RuntimeException when the storage column has not been created yet (upgrade not run)
 * @return array{columns:array<int,array{0:string,1:string}>,order:string[]}
 */
function asset_save_column_settings(int $user_id, $raw): array
{
    if (!field_exists_in_table('custom_manage_assets_display_settings', 'user')) {
        throw new RuntimeException('custom_manage_assets_display_settings is missing; run the SimpleRisk upgrade.');
    }
    $settings = asset_normalize_column_settings($raw, asset_allowed_column_keys());
    save_custom_risk_display_settings('custom_manage_assets_display_settings', $settings, $user_id);
    return $settings;
}
