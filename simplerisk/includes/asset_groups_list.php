<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Asset groups listing for the Manage assets page's Asset groups tab (asset
 * management redesign, Phase A, Task 10), backing GET /api/v2/asset-groups
 * when the caller asks for paging or aggregates.
 *
 * Visibility: groups have no team of their own, so every `asset` user sees
 * every group (unchanged from the legacy Manage asset groups page and from
 * GET /asset-groups). What a group CONTAINS is team-scoped: counts, the
 * highest valuation and the team union are computed over the members the
 * caller can see (the same scope GET /assets applies), and the linked-risk
 * count covers only risks the caller may see. The Asset Scoring "highest"
 * columns (asset_group_scoring_summary()) are computed over those same
 * visible members, so a viewer never learns the scoring of an asset they
 * cannot see.
 */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/assets_list.php'));
require_once(realpath(__DIR__ . '/asset_scoring.php'));

/** The per-group Asset Scoring results GET /asset-groups?aggregates=1 returns. */
const ASSET_GROUP_SCORING_COLUMNS = ['highest_fips_categorization', 'highest_weighted_score', 'highest_weighted_band'];

/** Keys the member drawer (GET /asset-groups/{id}/assets) sorts by. */
const ASSET_GROUP_MEMBERS_SORT_KEYS = ['name', 'value', 'fips_categorization', 'weighted_score', 'weighted_band'];

/** The member drawer's default order: highest valuation first, then name. */
const ASSET_GROUP_MEMBERS_DEFAULT_SORT = ['sort' => 'value', 'dir' => 'desc'];

/** Filter dimensions of the Asset groups table (each an id list; see asset_groups_row_matches_filter()). */
const ASSET_GROUPS_FILTER_KEYS = ['team', 'location', 'tag', 'categorization', 'band'];

/**
 * Keys the groups list sorts by: name in SQL, the rest in PHP over the
 * computed aggregates (asset_groups_sort_rows()).
 */
const ASSET_GROUPS_SORT_KEYS = ['name', 'highest_fips_categorization', 'highest_weighted_score', 'highest_weighted_band', 'locations', 'tags'];

/**
 * Normalizes the list options. The filters are parsed by the Manage assets
 * list's own parser (assets_list_normalize_filter(): digit-only ids, at most
 * 500 per list, level ids 1-3 only), so a hostile value is dropped the same
 * way on both tables.
 *
 * @param array<string,mixed> $raw  q, page, per_page (0 = every group, max 100), sort, dir, aggregates,
 *                                  team, location, tag, categorization, band
 * @return array{q:string,page:int,per_page:int,sort:string,dir:string,aggregates:bool,filter:array{team:int[],location:int[],tag:int[],categorization:int[],band:int[]}}
 */
function asset_groups_list_normalize_options(array $raw): array
{
    $int = static fn($v, int $d): int => (is_scalar($v) && is_numeric($v)) ? (int)$v : $d;
    $agg = $raw['aggregates'] ?? false;
    $parsed = assets_list_normalize_filter(array_intersect_key($raw, array_flip(ASSET_GROUPS_FILTER_KEYS)));
    $filter = [];
    foreach (ASSET_GROUPS_FILTER_KEYS as $k) {
        $filter[$k] = $parsed[$k];
    }
    $sort = $raw['sort'] ?? 'name';
    return [
        'q' => (isset($raw['q']) && is_scalar($raw['q'])) ? trim((string)$raw['q']) : '',
        'page' => max(1, $int($raw['page'] ?? 1, 1)),
        'per_page' => max(0, min(100, $int($raw['per_page'] ?? 0, 0))),
        'sort' => is_string($sort) && in_array($sort, ASSET_GROUPS_SORT_KEYS, true) ? $sort : 'name',
        'dir' => (isset($raw['dir']) && is_scalar($raw['dir']) && strtolower((string)$raw['dir']) === 'desc') ? 'desc' : 'asc',
        'aggregates' => $agg === true || (is_scalar($agg) && in_array(strtolower((string)$agg), ['1', 'true'], true)),
        'filter' => $filter,
    ];
}

/** Whether any group filter has a value. Pure. */
function asset_groups_filter_active(array $filter): bool
{
    foreach (ASSET_GROUPS_FILTER_KEYS as $k) {
        if (!empty($filter[$k])) {
            return true;
        }
    }
    return false;
}

/**
 * Whether a group (its computed aggregates) passes the filter. Every filter
 * matches when the group has ANY of its ids (OR within a filter); filters
 * combine with AND. team / location / tag look at the group's visible
 * members (teams, locations, tags aggregates); categorization / band at the
 * group's highest FIPS categorization / weighted band, so a group with no
 * scored member never matches either. Pure.
 *
 * @param array<string,mixed> $row
 * @param array<string,int[]> $filter
 */
function asset_groups_row_matches_filter(array $row, array $filter): bool
{
    foreach (['team' => 'teams', 'location' => 'locations', 'tag' => 'tags'] as $key => $field) {
        if (!empty($filter[$key]) && !array_intersect($filter[$key], array_map('intval', $row[$field] ?? []))) {
            return false;
        }
    }
    foreach (['categorization' => 'highest_fips_categorization', 'band' => 'highest_weighted_band'] as $key => $field) {
        if (!empty($filter[$key])) {
            $rank = asset_scoring_level_rank($row[$field] ?? null);
            if ($rank === null || !in_array($rank, $filter[$key], true)) {
                return false;
            }
        }
    }
    return true;
}

/**
 * $ids de-duplicated and ordered by their names (natural, case-insensitive;
 * then id). An id with no name in $names is dropped. Pure.
 *
 * @param int[] $ids
 * @param array<int,string> $names
 * @return int[]
 */
function asset_groups_order_ids_by_name(array $ids, array $names): array
{
    $ids = array_values(array_filter(array_unique(array_map('intval', $ids)), static fn(int $id): bool => isset($names[$id])));
    usort($ids, static fn(int $a, int $b): int => strnatcasecmp((string)$names[$a], (string)$names[$b]) ?: ($a <=> $b));
    return $ids;
}

/**
 * Sorts groups (with aggregates) by an aggregate column: the Asset Scoring
 * results by level rank / numeric score, locations / tags by the group's
 * first value alphabetically (the lists are already in name order). A group
 * with nothing there sorts last in both directions; ties fall back to the
 * group name, ascending, then the id. Pure.
 *
 * @param array<int,array<string,mixed>> $rows
 * @param array<int,string> $location_names
 * @param array<int,string> $tag_names
 * @return array<int,array<string,mixed>>
 */
function asset_groups_sort_rows(array $rows, string $sort, string $dir, array $location_names, array $tag_names): array
{
    $key = static function (array $r) use ($sort, $location_names, $tag_names) {
        switch ($sort) {
            case 'highest_fips_categorization':
            case 'highest_weighted_band':
                return asset_scoring_level_rank($r[$sort] ?? null);
            case 'highest_weighted_score':
                return isset($r[$sort]) ? asset_scoring_parse_hundredths((string)$r[$sort]) : null;
            case 'locations':
            case 'tags':
                $names = $sort === 'locations' ? $location_names : $tag_names;
                $first = $r[$sort][0] ?? null;
                return $first !== null && isset($names[(int)$first]) ? (string)$names[(int)$first] : null;
        }
        return null;
    };
    $by_name = static fn(array $a, array $b): int => strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')) ?: ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
    $sign = $dir === 'desc' ? -1 : 1;
    usort($rows, static function (array $a, array $b) use ($key, $by_name, $sign): int {
        $ka = $key($a);
        $kb = $key($b);
        if ($ka === null || $kb === null) {
            return $ka === $kb ? $by_name($a, $b) : ($ka === null ? 1 : -1);
        }
        $c = is_string($ka) ? strnatcasecmp($ka, (string)$kb) : ($ka <=> $kb);
        return $sign * $c ?: $by_name($a, $b);
    });
    return $rows;
}

/**
 * Groups by name, optionally searched, filtered, sorted, paged and
 * aggregated. Without a filter or an aggregate sort the page is read in SQL
 * (name order, LIMIT); with one, every group matching the search is
 * aggregated (asset_groups_aggregates(), chunked, no per-group query),
 * filtered and sorted in PHP over those computed rows (ruling G1), then
 * paged, so the total and the pager always describe the filtered set.
 *
 * @param array<string,mixed> $raw_opts
 * @return array{asset_groups:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
 */
function asset_groups_list(array $raw_opts): array
{
    $opts = asset_groups_list_normalize_options($raw_opts);

    $where = '';
    $params = [];
    if ($opts['q'] !== '') {
        $where = 'WHERE `name` LIKE :q';
        // LIKE wildcards in the search text match literally.
        $params['q'] = '%' . addcslashes($opts['q'], '\\%_') . '%';
    }
    $computed = asset_groups_filter_active($opts['filter']) || $opts['sort'] !== 'name';
    $name_dir = $opts['sort'] === 'name' ? $opts['dir'] : 'asc';

    $db = db_open();
    $limit = '';
    $total = 0;
    if (!$computed) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM `asset_groups` {$where}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        if ($opts['per_page'] > 0) {
            $offset = ($opts['page'] - 1) * $opts['per_page'];
            $limit = 'LIMIT ' . (int)$offset . ', ' . (int)$opts['per_page'];
        }
    }
    $stmt = $db->prepare("SELECT `id`, `name` FROM `asset_groups` {$where} ORDER BY `name` {$name_dir}, `id` {$limit}");
    $stmt->execute($params);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    foreach ($groups as &$g) {
        $g['id'] = (int)$g['id'];
        $g['name'] = (string)$g['name'];
    }
    unset($g);

    if ($computed) {
        $agg = $groups ? asset_groups_aggregates(array_column($groups, 'id')) : [];
        $rows = [];
        foreach ($groups as $g) {
            $row = array_merge($g, $agg[$g['id']] ?? []);
            if (asset_groups_row_matches_filter($row, $opts['filter'])) {
                $rows[] = $row;
            }
        }
        if ($opts['sort'] !== 'name') {
            $names = asset_groups_value_names(array_merge([], ...array_column($rows, 'locations')), array_merge([], ...array_column($rows, 'tags')));
            $rows = asset_groups_sort_rows($rows, $opts['sort'], $opts['dir'], $names['locations'], $names['tags']);
        }
        $total = count($rows);
        if ($opts['per_page'] > 0) {
            $rows = array_slice($rows, ($opts['page'] - 1) * $opts['per_page'], $opts['per_page']);
        }
        $groups = $opts['aggregates'] ? $rows : array_map(static fn(array $r): array => ['id' => $r['id'], 'name' => $r['name']], $rows);
    } elseif ($opts['aggregates'] && $groups) {
        $agg = asset_groups_aggregates(array_column($groups, 'id'));
        foreach ($groups as &$g) {
            $g += $agg[$g['id']];
        }
        unset($g);
    }

    return [
        'asset_groups' => $groups,
        'total' => $total,
        'page' => $opts['per_page'] > 0 ? $opts['page'] : 1,
        'per_page' => $opts['per_page'],
    ];
}

/**
 * Location and tag names (raw text) of the given ids, for ordering a group's
 * locations and tags and for the aggregate sorts. Two queries, chunked id
 * lists.
 *
 * @param int[] $location_ids
 * @param int[] $tag_ids
 * @return array{locations:array<int,string>,tags:array<int,string>}
 */
function asset_groups_value_names(array $location_ids, array $tag_ids): array
{
    $out = ['locations' => [], 'tags' => []];
    $db = db_open();
    foreach (['locations' => [$location_ids, 'SELECT `value` AS id, `name` FROM `location` WHERE `value` IN '], 'tags' => [$tag_ids, 'SELECT `id`, `tag` AS name FROM `tags` WHERE `id` IN ']] as $kind => [$ids, $sql]) {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        foreach (array_chunk($ids, assets_list_chunk_size()) as $chunk) {
            $stmt = $db->prepare($sql . '(' . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[$kind][(int)$r['id']] = (string)$r['name'];
            }
        }
    }
    db_close($db);
    return $out;
}

/**
 * Per-group aggregates for a page of groups, in a fixed number of queries
 * (chunked id lists, no per-group query):
 *   asset_count    members the caller can see
 *   max_valuation  asset_values id of the highest-valued visible member (null when none)
 *   teams          ids of the caller's visible teams that any visible member carries
 *   risk_count     risks linked to the group that the caller may see; null
 *                  without the Risk Management permission
 *   locations      ids of the distinct locations of the visible members,
 *                  alphabetically by name
 *   tags           ids of the distinct tags on the visible members, alphabetically
 *   highest_fips_categorization / highest_weighted_score / highest_weighted_band
 *                  asset_group_scoring_summary() over the visible members
 *                  (null when none is scored, or before the upgrade has added
 *                  the scoring columns)
 *
 * @param int[] $group_ids
 * @return array<int,array{asset_count:int,max_valuation:?int,teams:int[],risk_count:?int,highest_fips_categorization:?string,highest_weighted_score:?string,highest_weighted_band:?string,locations:int[],tags:int[]}>
 */
function asset_groups_aggregates(array $group_ids): array
{
    $group_ids = array_values(array_unique(array_map('intval', $group_ids)));
    $can_risks = check_permission('riskmanagement');
    $out = [];
    foreach ($group_ids as $gid) {
        $out[$gid] = ['asset_count' => 0, 'max_valuation' => null, 'teams' => [], 'risk_count' => $can_risks ? 0 : null]
            + asset_group_scoring_result(null) + ['locations' => [], 'tags' => []];
    }
    if (!$group_ids) {
        return $out;
    }

    // Valuation rank: the same order GET /assets sorts valuations by.
    $rank = assets_list_valuation_ranks();
    $db = db_open();

    $visible_teams = array_flip(array_map('intval', array_column(get_teams_by_login_user(), 'value')));

    // Team Separation scope on `assets a`: the same clause GET /assets uses.
    $scope = assets_build_filter_query([]);
    // Scoring is read from the same scoped rows, in PHP (ruling G1), with the
    // settings loaded once for the page.
    $scoring = asset_scoring_schema_ready();
    $scoring_select = $scoring ? ', `a`.`confidentiality`, `a`.`integrity`, `a`.`availability`' : '';
    $scoring_settings = $scoring ? asset_scoring_settings() : null;
    $scored = [];
    $best = [];
    $teams = [];
    $locations = [];
    $tags = [];
    foreach (array_chunk($group_ids, assets_list_chunk_size()) as $chunk) {
        $params = $scope['params'];
        $in = [];
        foreach ($chunk as $i => $gid) {
            $in[] = ":g{$i}";
            $params["g{$i}"] = $gid;
        }
        $stmt = $db->prepare("
            SELECT `aag`.`asset_group_id` AS gid, `a`.`value`, `a`.`teams`, `a`.`location`{$scoring_select},
                (SELECT GROUP_CONCAT(`tt`.`tag_id`) FROM `tags_taggees` tt WHERE `tt`.`taggee_id` = `a`.`id` AND `tt`.`type` = 'asset') AS tag_ids
            FROM `assets_asset_groups` aag
                INNER JOIN `assets` a ON `a`.`id` = `aag`.`asset_id`
            {$scope['where']} AND `aag`.`asset_group_id` IN (" . implode(',', $in) . ')');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $gid = (int)$row['gid'];
            $out[$gid]['asset_count']++;
            $v = (int)$row['value'];
            if (isset($rank[$v]) && (!isset($best[$gid]) || $rank[$v] > $rank[$best[$gid]])) {
                $best[$gid] = $v;
            }
            foreach (explode(',', (string)$row['teams']) as $t) {
                $t = (int)trim($t);
                if ($t > 0 && isset($visible_teams[$t])) {
                    $teams[$gid][$t] = true;
                }
            }
            foreach (['location' => &$locations, 'tag_ids' => &$tags] as $col => &$bucket) {
                foreach (explode(',', (string)$row[$col]) as $id) {
                    $id = (int)trim($id);
                    if ($id > 0) {
                        $bucket[$gid][$id] = true;
                    }
                }
            }
            unset($bucket);
            if ($scoring) {
                $scored[$gid] = asset_group_scoring_fold($scored[$gid] ?? null, asset_scoring_compute($row, $scoring_settings));
            }
        }

        if ($can_risks) {
            $rparams = [];
            foreach ($chunk as $i => $gid) {
                $rparams["g{$i}"] = $gid;
            }
            $stmt = $db->prepare('SELECT `asset_group_id` AS gid, `risk_id` FROM `risks_to_asset_groups` WHERE `asset_group_id` IN (' . implode(',', $in) . ')');
            $stmt->execute($rparams);
            $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $visible = array_flip(filter_accessible_risk_ids(array_column($links, 'risk_id')));
            foreach ($links as $l) {
                if (isset($visible[(int)$l['risk_id']])) {
                    $out[(int)$l['gid']]['risk_count']++;
                }
            }
        }
    }
    db_close($db);

    // Each group's distinct locations and tags, alphabetically (the names of
    // just the ids found, in two queries).
    $all_locations = $all_tags = [];
    foreach ($locations as $set) {
        $all_locations += $set;
    }
    foreach ($tags as $set) {
        $all_tags += $set;
    }
    $names = asset_groups_value_names(array_keys($all_locations), array_keys($all_tags));

    foreach ($group_ids as $gid) {
        $out[$gid]['locations'] = asset_groups_order_ids_by_name(array_keys($locations[$gid] ?? []), $names['locations']);
        $out[$gid]['tags'] = asset_groups_order_ids_by_name(array_keys($tags[$gid] ?? []), $names['tags']);
        $out[$gid]['max_valuation'] = $best[$gid] ?? null;
        $ids = array_keys($teams[$gid] ?? []);
        sort($ids);
        $out[$gid]['teams'] = $ids;
        $out[$gid] = array_merge($out[$gid], asset_group_scoring_result($scored[$gid] ?? null));
    }
    return $out;
}

/**
 * Folds one member's asset_scoring_compute() result into a group's running
 * highest results ($acc null = nothing scored yet). Unscored members are
 * ignored. The categorization and the score are maximized independently (the
 * highest categorization may belong to another member than the highest
 * score); the band is the highest score's band, which is the highest band
 * since the band rises with the score. Ties keep the first. Pure.
 *
 * @param array{cat:int,score:?int,band:?string}|null $acc
 * @param array{scored:bool,categorization:?string,score_hundredths:?int,score:?string,band:?string} $result
 * @return array{cat:int,score:?int,band:?string}|null
 */
function asset_group_scoring_fold(?array $acc, array $result): ?array
{
    if (empty($result['scored'])) {
        return $acc;
    }
    $acc = $acc ?? ['cat' => 0, 'score' => null, 'band' => null];
    $rank = asset_scoring_level_rank($result['categorization'] ?? null);
    if ($rank !== null && $rank > $acc['cat']) {
        $acc['cat'] = $rank;
    }
    $h = $result['score_hundredths'] ?? null;
    if (is_int($h) && ($acc['score'] === null || $h > $acc['score'])) {
        $acc['score'] = $h;
        $acc['band'] = $result['band'] ?? null;
    }
    return $acc;
}

/**
 * A folded accumulator (asset_group_scoring_fold()) as the API fields: level
 * codes ('low' | 'moderate' | 'high') and the score as two decimals, each null
 * when no member is scored. Pure.
 *
 * @param array{cat:int,score:?int,band:?string}|null $acc
 * @return array{highest_fips_categorization:?string,highest_weighted_score:?string,highest_weighted_band:?string}
 */
function asset_group_scoring_result(?array $acc): array
{
    return [
        'highest_fips_categorization' => $acc && $acc['cat'] > 0 ? asset_scoring_level_for_rank($acc['cat']) : null,
        'highest_weighted_score' => $acc && $acc['score'] !== null ? asset_scoring_format_hundredths($acc['score']) : null,
        'highest_weighted_band' => $acc && $acc['score'] !== null ? $acc['band'] : null,
    ];
}

/**
 * The highest Asset Scoring results over a group's members (rows carrying
 * confidentiality / integrity / availability), through the one scoring
 * function (asset_scoring_compute()). The caller passes only the members the
 * viewer may see. Pure.
 *
 * @param array<int,array<string,mixed>> $member_rows
 * @return array{highest_fips_categorization:?string,highest_weighted_score:?string,highest_weighted_band:?string}
 */
function asset_group_scoring_summary(array $member_rows, array $settings): array
{
    $acc = null;
    foreach ($member_rows as $row) {
        $acc = asset_group_scoring_fold($acc, asset_scoring_compute($row, $settings));
    }
    return asset_group_scoring_result($acc);
}

/**
 * One member's computed scoring fields, keyed as the Manage assets list rows
 * carry them (null when the member is not scored). Pure.
 *
 * @param array<string,mixed> $row
 * @return array{fips_categorization:?string,weighted_score:?string,weighted_band:?string}
 */
function asset_group_member_scoring(array $row, array $settings): array
{
    $r = asset_scoring_compute($row, $settings);
    return ['fips_categorization' => $r['categorization'], 'weighted_score' => $r['score'], 'weighted_band' => $r['band']];
}

/**
 * The member drawer's sort from raw request values: a key of
 * ASSET_GROUP_MEMBERS_SORT_KEYS (else name) and asc/desc (else asc).
 *
 * @param mixed $sort
 * @param mixed $dir
 * @return array{sort:string,dir:string}
 */
function asset_group_members_sort_options($sort, $dir): array
{
    $sort = is_string($sort) && in_array($sort, ASSET_GROUP_MEMBERS_SORT_KEYS, true) ? $sort : 'name';
    $dir = is_string($dir) && strtolower($dir) === 'desc' ? 'desc' : 'asc';
    return ['sort' => $sort, 'dir' => $dir];
}

/**
 * Sorts a group's members for the drawer. value sorts by the valuation level's
 * rank (never its id or label); the scoring keys by level rank or the numeric
 * score. A member with no value there (unscored, or a valuation that matches
 * no level) sorts last in both directions; ties fall back to the name,
 * ascending (natural, case-insensitive), then the id. Pure.
 *
 * @param array<int,array<string,mixed>> $members
 * @param array<int,int> $valuation_ranks  assets_list_valuation_ranks()
 * @return array<int,array<string,mixed>>
 */
function asset_group_sort_members(array $members, string $sort, string $dir, array $valuation_ranks): array
{
    $opts = asset_group_members_sort_options($sort, $dir);
    $key = static function (array $m) use ($opts, $valuation_ranks) {
        switch ($opts['sort']) {
            case 'value':
                return $valuation_ranks[(int)($m['value'] ?? 0)] ?? null;
            case 'fips_categorization':
            case 'weighted_band':
                return asset_scoring_level_rank($m[$opts['sort']] ?? null);
            case 'weighted_score':
                return isset($m['weighted_score']) ? asset_scoring_parse_hundredths((string)$m['weighted_score']) : null;
        }
        return null;
    };
    $by_name = static fn(array $a, array $b): int => strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')) ?: ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
    $sign = $opts['dir'] === 'desc' ? -1 : 1;
    usort($members, static function (array $a, array $b) use ($opts, $key, $by_name, $sign): int {
        if ($opts['sort'] === 'name') {
            return $sign * $by_name($a, $b);
        }
        $ka = $key($a);
        $kb = $key($b);
        if ($ka === null || $kb === null) {
            if ($ka !== $kb) {
                return $ka === null ? 1 : -1;
            }
            return $by_name($a, $b);
        }
        return $sign * ($ka <=> $kb) ?: $by_name($a, $b);
    });
    return $members;
}

/**
 * The members of a group the caller can see, with the fields the tab's member
 * drawer shows: the asset, its valuation and status, and its Asset Scoring
 * results computed by asset_group_member_scoring() (null when unscored or
 * before the upgrade has added the scoring columns). Sorted by
 * asset_group_sort_members(): by name unless the caller asks otherwise (the
 * drawer asks for ASSET_GROUP_MEMBERS_DEFAULT_SORT).
 *
 * @return array<int,array{id:int,name:string,ip:string,value:int,verified:int,fips_categorization:?string,weighted_score:?string,weighted_band:?string}>
 */
function asset_group_visible_members(int $group_id, string $sort = 'name', string $dir = 'asc'): array
{
    $encryption = encryption_extra();
    if ($encryption) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }
    $scoring = asset_scoring_schema_ready();
    $scoring_select = $scoring ? ', `a`.`confidentiality`, `a`.`integrity`, `a`.`availability`' : '';
    $scope = assets_build_filter_query([]);
    $params = $scope['params'];
    $params['gid'] = $group_id;
    $db = db_open();
    $stmt = $db->prepare("
        SELECT `a`.`id`, `a`.`name`, `a`.`ip`, `a`.`value`, `a`.`verified`{$scoring_select}
        FROM `assets` a
            INNER JOIN `assets_asset_groups` aag ON `aag`.`asset_id` = `a`.`id` AND `aag`.`asset_group_id` = :gid
        {$scope['where']}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $settings = $scoring && $rows ? asset_scoring_settings() : null;
    $none = ['fips_categorization' => null, 'weighted_score' => null, 'weighted_band' => null];
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)$r['id'],
            'name' => (string)($encryption ? try_decrypt($r['name']) : $r['name']),
            'ip' => (string)($encryption ? try_decrypt($r['ip']) : ($r['ip'] ?? '')),
            'value' => (int)$r['value'],
            'verified' => (int)$r['verified'],
        ] + ($settings !== null ? asset_group_member_scoring($r, $settings) : $none);
    }
    $ranks = asset_group_members_sort_options($sort, $dir)['sort'] === 'value' ? assets_list_valuation_ranks() : [];
    return asset_group_sort_members($out, $sort, $dir, $ranks);
}

/* =====================================================================
 * Column layout of the Asset groups table (the Columns picker)
 * ===================================================================== */

/**
 * Core Asset groups columns: key => lang key of the label. `name` is always
 * on (as are the expand caret and the row actions, which are not columns of
 * the layout). risk_count is offered only to users who may see risks.
 * locations / tags (the members' distinct values) are opt-in, not defaults.
 *
 * @return array<string,string>
 */
function asset_group_core_column_labels(): array
{
    return [
        'name' => 'Name',
        'asset_count' => 'Assets',
        'max_valuation' => 'HighestValuation',
        'teams' => 'TeamsHeader',
        'risk_count' => 'LinkedRisks',
        'locations' => 'SiteLocation',
        'tags' => 'Tags',
    ];
}

/**
 * The per-group Asset Scoring columns: key => lang key. Offered once the
 * upgrade has added the scoring columns (asset_scoring_schema_ready()).
 *
 * @return array<string,string>
 */
function asset_group_scoring_column_labels(): array
{
    return [
        'highest_fips_categorization' => 'HighestFIPSCategorization',
        'highest_weighted_score' => 'HighestWeightedScore',
        'highest_weighted_band' => 'HighestWeightedBand',
    ];
}

/**
 * Columns shown when a user has saved nothing, in their default order: the
 * table's columns before the picker existed minus Team (still one click away
 * in the picker), plus the three Asset Scoring results; Linked risks sits
 * next to Assets and Highest valuation follows the scoring results. Keys not offered to the viewer (Linked risks without Risk
 * Management, the scoring columns before the upgrade) simply drop out. A
 * saved layout is loaded exactly as stored, so changing this list never
 * changes what an existing saved layout shows.
 *
 * @return string[]
 */
function asset_group_default_column_keys(): array
{
    return ['name', 'asset_count', 'risk_count', 'highest_fips_categorization', 'highest_weighted_score', 'highest_weighted_band', 'max_valuation'];
}

/**
 * Every Asset groups column the viewer may enable, in picker order (the
 * defaults first).
 *
 * @return array<int,array{key:string,label_key:string}>
 */
function asset_group_available_columns(): array
{
    $labels = asset_group_core_column_labels();
    if (!check_permission('riskmanagement')) {
        unset($labels['risk_count']);
    }
    if (asset_scoring_schema_ready()) {
        $labels += asset_group_scoring_column_labels();
    }
    $out = [];
    foreach (array_unique(array_merge(asset_group_default_column_keys(), array_keys($labels))) as $key) {
        if (isset($labels[$key])) {
            $out[] = ['key' => $key, 'label_key' => $labels[$key]];
        }
    }
    return $out;
}

/** @return string[] */
function asset_group_allowed_column_keys(): array
{
    return array_column(asset_group_available_columns(), 'key');
}

/**
 * asset_normalize_column_settings()'s rules with the Asset groups defaults:
 * unknown keys dropped, name forced on and first, every allowed key exactly
 * once, and a STORED layout never gains a visible column. Pure.
 *
 * @param mixed $raw
 * @param string[] $allowed
 * @return array{columns:array<int,array{0:string,1:string}>,order:string[]}
 */
function asset_group_normalize_column_settings($raw, array $allowed, bool $stored = false): array
{
    return assets_normalize_column_layout($raw, $allowed, asset_group_default_column_keys(), $stored);
}

/**
 * The user's saved Asset groups column settings (their own storage column,
 * separate from Manage assets'), normalized against the columns allowed
 * right now. Defaults when nothing is stored, the stored value is unreadable,
 * or the storage column does not exist yet (upgrade not run). Read-only.
 *
 * @return array{columns:array<int,array{0:string,1:string}>,order:string[]}
 */
function asset_group_load_column_settings(int $user_id): array
{
    $stored = null;
    if (field_exists_in_table('custom_manage_asset_groups_display_settings', 'user')) {
        $db = db_open();
        $stmt = $db->prepare('SELECT `custom_manage_asset_groups_display_settings` FROM `user` WHERE `value` = :id');
        $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $json = $stmt->fetchColumn();
        db_close($db);
        if (is_string($json) && $json !== '') {
            $stored = json_decode($json, true);
        }
    }
    return asset_group_normalize_column_settings($stored, asset_group_allowed_column_keys(), true);
}

/**
 * Sanitizes and stores the user's Asset groups column settings and returns
 * what is now stored.
 *
 * @param mixed $raw
 * @throws RuntimeException when the storage column has not been created yet (upgrade not run)
 * @return array{columns:array<int,array{0:string,1:string}>,order:string[]}
 */
function asset_group_save_column_settings(int $user_id, $raw): array
{
    if (!field_exists_in_table('custom_manage_asset_groups_display_settings', 'user')) {
        throw new RuntimeException('custom_manage_asset_groups_display_settings is missing; run the SimpleRisk upgrade.');
    }
    $settings = asset_group_normalize_column_settings($raw, asset_group_allowed_column_keys());
    save_custom_risk_display_settings('custom_manage_asset_groups_display_settings', $settings, $user_id);
    return $settings;
}
