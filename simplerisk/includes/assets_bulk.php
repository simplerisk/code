<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Bulk asset actions behind POST /assets/bulk (asset-management redesign).
 * Plain functions so they are testable without an HTTP layer. Each id is
 * independent and reported; the request as a whole is not atomic.
 */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/assets_list.php'));
// assets_parse_verified() and the other pure asset helpers.
require_once(realpath(__DIR__ . '/assets_write_rules.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/extras.php'));

/** Maximum number of asset ids one bulk request may act on. */
const ASSETS_BULK_MAX_IDS = 5000;

/**
 * Maximum number of assets one bulk DELETE may remove. Lower than
 * ASSETS_BULK_MAX_IDS because every deleted asset writes an audit line and
 * fires the asset.deleted workflow event, which runs matching workflows in the
 * request: measured on a test instance with one asset.deleted workflow, 5,000
 * deletes took ~63 s (past the 60 s many reverse proxies allow) and 1,000
 * took ~13 s, so 2,000 stays near 25 s.
 */
const ASSETS_BULK_MAX_DELETE = 2000;

/** Most assets one request may act on for the action. */
function assets_bulk_max_for_action(string $action): int
{
    return $action === 'delete' ? ASSETS_BULK_MAX_DELETE : ASSETS_BULK_MAX_IDS;
}

/**
 * The error when a resolved selection is larger than the action allows, or
 * null. Delete has its own, lower cap and its own message.
 *
 * @return array{error:string,params:array<string,int>}|null
 */
function assets_bulk_size_error(string $action, int $count): ?array
{
    $max = assets_bulk_max_for_action($action);
    if ($count <= $max) {
        return null;
    }
    return ['error' => $action === 'delete' ? 'AssetBulkTooManyToDelete' : 'AssetBulkTooManyAssets', 'params' => ['max' => $max]];
}

/**
 * Permission key required for a bulk action, or null for an unknown action.
 * One narrow permission per action; never an OR of several.
 */
function assets_bulk_required_permission(string $action): ?string
{
    static $map = [
        'verify' => 'asset_verify',
        'delete' => 'asset_delete',
        'assign_teams' => 'asset_edit',
        'add_to_group' => 'asset_group_edit',
    ];
    return $map[$action] ?? null;
}

/** Whether the current session holds the narrow permission for the action. */
function assets_bulk_user_may(string $action): bool
{
    $permission = assets_bulk_required_permission($action);
    return $permission !== null && has_permission($permission);
}

/** Keys a bulk `filter` object may carry (the Manage assets list filters plus `all`). */
const ASSETS_BULK_FILTER_KEYS = ['verified', 'q', 'team', 'location', 'tag', 'group', 'valuation', 'categorization', 'band', 'confidentiality', 'integrity', 'availability', 'all'];

/**
 * Id-list filter keys; each is capped at ASSETS_BULK_FILTER_MAX_VALUES values.
 * categorization / band are Asset Scoring level ids (1-3), confidentiality /
 * integrity / availability rating ids (1-3, plus 0 Not applicable for
 * confidentiality only): an id the key does not offer is dropped by the list
 * normaliser, so the lost-key check refuses it.
 */
const ASSETS_BULK_FILTER_ID_KEYS = ['team', 'location', 'tag', 'group', 'valuation', 'categorization', 'band', 'confidentiality', 'integrity', 'availability'];

/**
 * Most values one id-list filter may carry in a bulk request. The list API
 * silently truncates longer lists; a destructive bulk action must not act on
 * a set wider than the one the caller described, so bulk rejects instead.
 */
const ASSETS_BULK_FILTER_MAX_VALUES = 500;

/** Whether a value is a non-negative JSON integer or a string of digits. */
function assets_bulk_is_digits($v): bool
{
    return is_int($v) ? $v >= 0 : (is_string($v) && $v !== '' && ctype_digit($v));
}

/**
 * Strict validation of a bulk `filter` object. Anything the list normaliser
 * would silently drop (an unknown key, a non-digit id, a verified value it
 * does not understand) is rejected here, because a dropped constraint widens
 * a destructive action to everything the caller can see. An empty or
 * effectively empty filter is rejected too: "every asset in scope" must be
 * said explicitly with {"all": true}, and `all` cannot be combined with
 * other filters.
 *
 * @param array<mixed> $filter
 * @return array{ok:bool,status:int,error:string,params:array<string,mixed>}
 */
function assets_bulk_validate_filter(array $filter): array
{
    $fail = static fn(string $code, array $params = []): array => ['ok' => false, 'status' => 422, 'error' => $code, 'params' => $params];

    foreach (array_keys($filter) as $key) {
        if (!in_array($key, ASSETS_BULK_FILTER_KEYS, true)) {
            return $fail('AssetBulkFilterUnknownKey', ['key' => (string)$key]);
        }
    }

    if (array_key_exists('all', $filter)) {
        if ($filter['all'] !== true) {
            return $fail('AssetBulkFilterBadValue', ['key' => 'all']);
        }
        if (count($filter) > 1) {
            return $fail('AssetBulkFilterAllAlone');
        }
        return ['ok' => true, 'status' => 200, 'error' => '', 'params' => []];
    }

    $narrowing = false;

    if (array_key_exists('verified', $filter)) {
        // Same reading as the list normaliser (assets_parse_verified()).
        if (assets_parse_verified($filter['verified']) === null) {
            return $fail('AssetBulkFilterBadValue', ['key' => 'verified']);
        }
        $narrowing = true;
    }

    if (array_key_exists('q', $filter)) {
        if (!is_string($filter['q'])) {
            return $fail('AssetBulkFilterBadValue', ['key' => 'q']);
        }
        $narrowing = $narrowing || trim($filter['q']) !== '';
    }

    foreach (ASSETS_BULK_FILTER_ID_KEYS as $key) {
        if (!array_key_exists($key, $filter)) {
            continue;
        }
        $list = $filter[$key];
        if (!is_array($list) || !array_is_list($list)) {
            return $fail('AssetBulkFilterBadValue', ['key' => $key]);
        }
        if (count($list) > ASSETS_BULK_FILTER_MAX_VALUES) {
            return $fail('AssetBulkFilterTooManyValues', ['key' => $key, 'max' => ASSETS_BULK_FILTER_MAX_VALUES]);
        }
        foreach ($list as $v) {
            if (!assets_bulk_is_digits($v)) {
                return $fail('AssetBulkFilterBadValue', ['key' => $key]);
            }
        }
        $narrowing = $narrowing || count($list) > 0;
    }

    if (!$narrowing) {
        return $fail('AssetBulkFilterEmpty');
    }

    // Structural safety net: whatever this validator accepted must survive
    // the normaliser the resolver uses. A narrowing value that comes out
    // dropped or emptied would widen the action, so refuse instead.
    $lost = assets_bulk_filter_lost_key($filter, assets_list_normalize_filter($filter));
    if ($lost !== null) {
        return $fail('AssetBulkFilterNotApplied', ['key' => $lost]);
    }
    return ['ok' => true, 'status' => 200, 'error' => '', 'params' => []];
}

/**
 * The first filter key the client sent with a narrowing value that the
 * normalised filter no longer carries in full (dropped, emptied, or with
 * fewer ids than were sent), or null when every sent constraint survived.
 * A key sent with an empty value (q of spaces, an empty list) narrows
 * nothing, so it cannot be lost. The `all` marker is not a constraint. Pure.
 *
 * @param array<mixed> $sent
 * @param array<string,mixed> $normalized output of assets_list_normalize_filter()
 */
function assets_bulk_filter_lost_key(array $sent, array $normalized): ?string
{
    foreach ($sent as $key => $value) {
        $key = (string)$key;
        if ($key === 'all') {
            continue;
        }
        if ($key === 'verified') {
            if (($normalized['verified'] ?? null) === null || $normalized['verified'] !== assets_parse_verified($value)) {
                return $key;
            }
        } elseif ($key === 'q') {
            $want = is_scalar($value) ? trim((string)$value) : '';
            if ($want !== ($normalized['q'] ?? '')) {
                return $key;
            }
        } elseif (in_array($key, ASSETS_BULK_FILTER_ID_KEYS, true)) {
            $want = is_array($value) ? $value : [$value];
            $want = array_values(array_unique(array_map(static fn($v) => is_scalar($v) ? trim((string)$v) : '', $want)));
            $want = array_values(array_filter($want, static fn($v) => $v !== ''));
            $got = array_map('strval', (array)($normalized[$key] ?? []));
            $want_ints = array_values(array_unique(array_map(static fn($v) => ctype_digit($v) ? (string)(int)$v : $v, $want)));
            sort($want_ints);
            sort($got);
            if ($want_ints !== $got) {
                return $key;
            }
        } else {
            // Not a key the normaliser knows at all.
            return $key;
        }
    }
    return null;
}

/**
 * Pure shape validation of a decoded request body. Touches no state.
 * `error` is a stable machine code that is also the language key of the
 * message shown to the user; `params` fills that message's placeholders.
 *
 * @param array<mixed> $body
 * @return array{ok:bool,status:int,error:string,params:array<string,mixed>}
 */
function assets_bulk_validate_request(array $body): array
{
    $fail = static fn(int $status, string $code, array $params = []): array => ['ok' => false, 'status' => $status, 'error' => $code, 'params' => $params];

    $action = $body['action'] ?? null;
    if (!is_string($action) || $action === '') {
        return $fail(400, 'AssetBulkActionRequired');
    }
    if (assets_bulk_required_permission($action) === null) {
        return $fail(422, 'AssetBulkUnknownAction');
    }

    $has_ids = array_key_exists('ids', $body);
    $has_filter = array_key_exists('filter', $body);
    if ($has_ids === $has_filter) {
        return $fail(400, 'AssetBulkSelectionRequired');
    }

    if ($has_ids) {
        if (!is_array($body['ids']) || !$body['ids']) {
            return $fail(400, 'AssetBulkIdsRequired');
        }
        $too_many = assets_bulk_size_error($action, count($body['ids']));
        if ($too_many !== null) {
            return $fail(422, $too_many['error'], $too_many['params']);
        }
        foreach ($body['ids'] as $id) {
            if (!(is_int($id) || (is_string($id) && ctype_digit($id)))) {
                return $fail(422, 'AssetBulkIdsInvalid');
            }
        }
    } else {
        // A JSON object; a JSON array (a list) is not a filter.
        if (!is_array($body['filter']) || ($body['filter'] && array_is_list($body['filter']))) {
            return $fail(400, 'AssetBulkFilterInvalid');
        }
        $filter_check = assets_bulk_validate_filter($body['filter']);
        if (!$filter_check['ok']) {
            return $filter_check;
        }
    }

    if (array_key_exists('expected_count', $body) && !assets_bulk_is_digits($body['expected_count'])) {
        return $fail(422, 'AssetBulkExpectedCountInvalid');
    }
    // A destructive action on a filter must say how many assets the caller
    // expects it to remove; a changed set is then refused (409), never widened.
    if ($has_filter && $action === 'delete' && !array_key_exists('expected_count', $body)) {
        return $fail(422, 'AssetBulkExpectedCountRequired');
    }

    $params = $body['params'] ?? [];
    if (!is_array($params)) {
        return $fail(422, 'AssetBulkParamsInvalid');
    }
    if ($action === 'assign_teams') {
        $teams = $params['team_ids'] ?? null;
        if (!is_array($teams) || !$teams) {
            return $fail(422, 'AssetBulkTeamsRequired');
        }
        foreach ($teams as $t) {
            if (!(is_int($t) || (is_string($t) && ctype_digit($t))) || (int)$t < 1) {
                return $fail(422, 'AssetBulkParamsInvalid');
            }
        }
    }
    if ($action === 'add_to_group') {
        $g = $params['group_id'] ?? null;
        if (!(is_int($g) || (is_string($g) && ctype_digit($g))) || (int)$g < 1) {
            return $fail(422, 'AssetBulkParamsInvalid');
        }
    }

    return ['ok' => true, 'status' => 200, 'error' => '', 'params' => []];
}

/**
 * A stale "Select all N" must not act on a set that has since grown or
 * shrunk: when the client says how many assets it expects (the N it showed),
 * the resolved count must match exactly. Null when acceptable, else the
 * error for a 409.
 *
 * @param array<mixed> $body
 * @return array{error:string,params:array<string,int>}|null
 */
function assets_bulk_count_mismatch(array $body, int $resolved_count): ?array
{
    if (!array_key_exists('expected_count', $body)) {
        return null;
    }
    $expected = (int)$body['expected_count'];
    if ($expected === $resolved_count) {
        return null;
    }
    return ['error' => 'AssetBulkCountMismatch', 'params' => ['expected' => $expected, 'actual' => $resolved_count]];
}

/**
 * Resolves the selection to a de-duplicated id list: explicit ids as given
 * (positive ints), or every asset matching the filter under the caller's
 * team-separation scope (the Manage assets list resolver).
 *
 * @param array<string,mixed> $selection ['ids' => int[]] or ['filter' => array]
 * @return int[]
 */
function assets_bulk_resolve_ids(array $selection): array
{
    if (isset($selection['filter']) && is_array($selection['filter'])) {
        // {"all": true} is the explicit "every asset in scope"; it is not a list filter.
        $filter = $selection['filter'];
        unset($filter['all']);
        return array_map('intval', assets_list_matching_ids($filter));
    }
    $out = [];
    foreach ((array)($selection['ids'] ?? []) as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $out[$id] = $id;
        }
    }
    return array_values($out);
}

/**
 * Validates the action's params against the database and the caller's scope.
 * Returns an error code (also the message's language key), or null when acceptable.
 *
 * @param array<string,mixed> $params
 */
function assets_bulk_check_params(string $action, array $params): ?string
{
    if ($action === 'assign_teams') {
        $wanted = array_values(array_unique(array_map('intval', (array)($params['team_ids'] ?? []))));
        if (!$wanted) {
            return 'AssetBulkTeamsRequired';
        }
        $db = db_open();
        $in = implode(',', array_fill(0, count($wanted), '?'));
        $stmt = $db->prepare("SELECT `value` FROM `team` WHERE `value` IN ({$in})");
        $stmt->execute($wanted);
        $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        db_close($db);
        if (array_diff($wanted, $existing)) {
            return 'AssetBulkTeamsNotFound';
        }
        // Under Team Separation a caller may only assign teams they belong to
        // (admins may assign any team).
        if (team_separation_extra() && !is_admin()) {
            $mine = array_map('intval', get_user_teams((int)($_SESSION['uid'] ?? 0)));
            if (array_diff($wanted, $mine)) {
                return 'AssetBulkTeamsNotMember';
            }
        }
    } elseif ($action === 'add_to_group') {
        $gid = (int)($params['group_id'] ?? 0);
        if ($gid < 1 || !get_asset_group($gid)) {
            return 'AssetBulkGroupNotFound';
        }
    }
    return null;
}

/**
 * Applies one action to each id, independently.
 *
 * Status per id: ok | not_found (missing OR outside the caller's scope --
 * deliberately indistinguishable) | error. The caller must already have checked the action's
 * permission and params.
 *
 * @param int[] $ids
 * @param array<string,mixed> $params
 * @param (callable(int):bool)|null $access_checker  Per-id scope check; defaults to check_access_for_asset()
 * @param (callable(int[]):array<int,string>)|null $chunk_deleter  Delete only: removes one chunk (tests inject failures); defaults to assets_bulk_delete_chunk()
 * @return array{results:array<int,array{id:int,status:string,message?:string}>,summary:array{ok:int,failed:int}}
 */
function assets_bulk_apply(string $action, array $ids, array $params, ?callable $access_checker = null, ?callable $chunk_deleter = null): array
{
    $access_checker = $access_checker ?? 'check_access_for_asset';
    $ids = array_values(array_unique(array_map('intval', $ids)));

    // Existing rows (id => [verified, teams]) in bounded chunks.
    $rows = [];
    if ($ids) {
        $db = db_open();
        foreach (array_chunk($ids, assets_list_chunk_size()) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $db->prepare("SELECT `id`, `verified`, `teams` FROM `assets` WHERE `id` IN ({$in})");
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[(int)$r['id']] = $r;
            }
        }
        db_close($db);
    }

    $results = [];
    $allowed = [];
    foreach ($ids as $id) {
        // Access first, and one status for both outcomes: an asset the caller
        // cannot see is reported exactly like one that does not exist, so a
        // bulk request is not a cross-team id enumeration oracle (the same
        // rule as asset_record_not_available_response()).
        if (!$access_checker($id) || !isset($rows[$id])) {
            $results[$id] = ['id' => $id, 'status' => 'not_found'];
        } else {
            $allowed[] = $id;
        }
    }

    if ($action === 'add_to_group') {
        // One membership update for the whole batch; existing members (including
        // ones this caller cannot see) are preserved, and re-adding is a no-op.
        $gid = (int)($params['group_id'] ?? 0);
        $ok = false;
        try {
            $group = $gid > 0 ? get_asset_group($gid) : [];
            if ($group && $allowed) {
                $current = array_map('intval', array_column($group['selected_assets'], 'id'));
                $merged = array_values(array_unique(array_merge($current, $allowed)));
                update_asset_group($gid, $group['name'], $merged);
                $ok = true;
            }
        } catch (Throwable $e) {
            write_debug_log('assets_bulk add_to_group failed: ' . $e->getMessage(), 'error');
        }
        foreach ($allowed as $id) {
            $results[$id] = $ok
                ? ['id' => $id, 'status' => 'ok']
                : ['id' => $id, 'status' => 'error', 'message' => 'Failed'];
        }
    } elseif ($action === 'delete') {
        // Batched: one DELETE per chunk instead of delete_asset() per id, whose
        // whole-table orphan sweep per asset made large deletes outrun the
        // request time limit. Same effects, same per-asset audit and event.
        // Ids of a chunk that failed are `error`; ids after it were not attempted.
        $outcome = assets_bulk_delete_many($allowed, $chunk_deleter);
        foreach ($allowed as $id) {
            if (!empty($outcome['deleted'][$id])) {
                $results[$id] = ['id' => $id, 'status' => 'ok'];
            } else {
                $results[$id] = ['id' => $id, 'status' => 'error', 'message' => !empty($outcome['failed'][$id]) ? 'Failed' : 'AssetBulkReasonNotAttempted'];
            }
        }
    } else {
        foreach ($allowed as $id) {
            try {
                $done = false;
                switch ($action) {
                    case 'verify':
                        $done = ((int)$rows[$id]['verified'] === 1) || verify_asset($id);
                        break;
                    case 'assign_teams':
                        $done = assets_bulk_merge_teams($id, (string)($rows[$id]['teams'] ?? ''), (array)($params['team_ids'] ?? []));
                        break;
                }
                $results[$id] = $done ? ['id' => $id, 'status' => 'ok'] : ['id' => $id, 'status' => 'error', 'message' => 'Failed'];
            } catch (Throwable $e) {
                write_debug_log('assets_bulk ' . $action . ' failed for asset ' . $id . ': ' . $e->getMessage(), 'error');
                $results[$id] = ['id' => $id, 'status' => 'error', 'message' => 'Failed'];
            }
        }
    }

    // Preserve the caller's id order.
    $ordered = [];
    foreach ($ids as $id) {
        $ordered[] = $results[$id];
    }
    $ok_count = count(array_filter($ordered, static fn($r) => $r['status'] === 'ok'));
    return ['results' => $ordered, 'summary' => ['ok' => $ok_count, 'failed' => count($ordered) - $ok_count]];
}

/**
 * Assets deleted per statement by assets_bulk_delete_many(). Tests lower it
 * through $GLOBALS['assets_bulk_delete_chunk_override'] to exercise chunking.
 */
function assets_bulk_delete_chunk_size(): int
{
    $override = $GLOBALS['assets_bulk_delete_chunk_override'] ?? null;
    return (is_int($override) && $override > 0) ? $override : 200;
}

/**
 * Deletes one chunk of assets: reads their names (plaintext, as
 * get_name_by_value() returns them) and removes the `assets` rows in one
 * statement. Returns id => name for the rows that existed. Throws on a
 * database error; the connection is closed either way.
 *
 * @param int[] $chunk
 * @return array<int,string>
 */
function assets_bulk_delete_chunk(array $chunk): array
{
    $in = implode(',', array_fill(0, count($chunk), '?'));
    $db = db_open();
    try {
        $stmt = $db->prepare("SELECT `id`, `name` FROM `assets` WHERE `id` IN ({$in})");
        $stmt->execute($chunk);
        $names = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $names[(int)$row['id']] = (string)try_decrypt($row['name']);
        }
        $stmt = $db->prepare("DELETE FROM `assets` WHERE `id` IN ({$in})");
        if (!$stmt->execute($chunk)) {
            throw new RuntimeException('DELETE returned false');
        }
        return $names;
    } finally {
        db_close($db);
    }
}

/**
 * Deletes many assets with the same effects as calling delete_asset() for
 * each id, in chunks:
 *  - the `assets` rows (one DELETE ... IN per chunk, $chunk_deleter);
 *  - one AssetDeletedLog audit line and one `asset.deleted` workflow event
 *    per asset, written as soon as its chunk's DELETE succeeded;
 *  - their Customization data (delete_custom_data_by_row_ids(), no-op without the Extra);
 *  - their junction rows and orphaned tags via cleanup_after_delete('assets'),
 *    which sweeps every junction table in $junction_config['assets'] for rows
 *    without an asset, so running it once per chunk instead of once per asset
 *    removes exactly the same rows.
 * A chunk whose DELETE fails stops the run: its ids are `failed`, and the
 * ids of later chunks are neither deleted nor failed (not attempted). A
 * failure of the custom-data or orphan sweep after a successful DELETE is
 * logged and the chunk still counts as deleted (the next sweep of any asset
 * delete removes the orphans).
 * The caller has already checked permission and per-asset scope.
 *
 * @param int[] $ids
 * @param (callable(int[]):array<int,string>)|null $chunk_deleter defaults to assets_bulk_delete_chunk()
 * @return array{deleted:array<int,bool>,failed:array<int,bool>}
 */
function assets_bulk_delete_many(array $ids, ?callable $chunk_deleter = null): array
{
    $chunk_deleter = $chunk_deleter ?? 'assets_bulk_delete_chunk';
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    $deleted = [];
    $failed = [];

    foreach (array_chunk($ids, assets_bulk_delete_chunk_size()) as $chunk) {
        try {
            $names = $chunk_deleter($chunk);
        } catch (Throwable $e) {
            write_debug_log('assets_bulk delete: a chunk of ' . count($chunk) . ' assets failed, the rest was not attempted: ' . $e->getMessage(), 'error');
            foreach ($chunk as $id) {
                $failed[$id] = true;
            }
            break;
        }

        foreach ($chunk as $id) {
            $deleted[$id] = true;
            $name = $names[$id] ?? '';
            try {
                write_log($id, $_SESSION['uid'] ?? 0, _lang('AssetDeletedLog', [
                    'name' => $name,
                    'user' => $_SESSION['user'] ?? '',
                ]), 'asset');
                trigger_workflow_event('asset.deleted', [
                    'asset_id' => $id,
                    'name'     => $name,
                ]);
            } catch (Throwable $e) {
                write_debug_log('assets_bulk delete: audit or workflow event for asset ' . $id . ' failed: ' . $e->getMessage(), 'error');
            }
        }

        try {
            call_extra_function(
                'customization_extra',
                __DIR__ . '/../extras/customization/index.php',
                'delete_custom_data_by_row_ids',
                [$chunk, 'asset']
            );
            cleanup_after_delete('assets');
        } catch (Throwable $e) {
            write_debug_log('assets_bulk delete: cleanup after deleting ' . count($chunk) . ' assets failed: ' . $e->getMessage(), 'error');
        }
    }

    return ['deleted' => $deleted, 'failed' => $failed];
}

/**
 * Merges team ids into an asset's CSV `teams` (existing order kept, no
 * duplicates, never removes). Audit-logged when it changes anything.
 *
 * @param array<int|string> $add
 */
function assets_bulk_merge_teams(int $asset_id, string $current_csv, array $add): bool
{
    $current = array_values(array_filter(array_map('intval', explode(',', $current_csv)), static fn($t) => $t > 0));
    $merged = array_values(array_unique(array_merge($current, array_map('intval', $add))));
    if ($merged === $current) {
        return true;
    }
    $csv = implode(',', $merged);

    $db = db_open();
    $stmt = $db->prepare('UPDATE `assets` SET `teams` = :teams WHERE `id` = :id');
    $stmt->bindParam(':teams', $csv);
    $stmt->bindParam(':id', $asset_id, PDO::PARAM_INT);
    $ok = $stmt->execute();
    db_close($db);

    if ($ok) {
        $message = _lang('AssetTeamsAssignedLog', [
            'name' => get_name_by_value('assets', $asset_id, '', true),
            'teams' => $csv,
            'user' => $_SESSION['user'] ?? '',
        ]);
        write_log($asset_id, $_SESSION['uid'] ?? 0, $message, 'asset');
    }
    return (bool)$ok;
}
