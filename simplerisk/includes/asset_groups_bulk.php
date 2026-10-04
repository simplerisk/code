<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Bulk asset-group actions behind POST /asset-groups/bulk (the Asset groups
 * tab's selection). The request shape, validation codes, the "Select all N"
 * count guard and the per-id reporting follow POST /assets/bulk
 * (includes/assets_bulk.php); deleting goes through the single-group delete,
 * delete_asset_group(), once per id, so each group gets the same row
 * removal, junction cleanup and audit line as deleting it one at a time. The
 * request as a whole is not atomic: each id is independent and reported.
 *
 * Groups have no team of their own, so every caller holding asset +
 * asset_group_delete may delete any group, exactly as DELETE
 * /asset-groups/{id} allows; a missing id is `not_found`.
 */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/assets_bulk.php'));
require_once(realpath(__DIR__ . '/asset_groups_list.php'));
require_once(realpath(__DIR__ . '/permissions.php'));

/**
 * Most groups one bulk delete may remove: the same cap as a bulk asset
 * delete (ASSETS_BULK_MAX_DELETE), which bounds the request's run time.
 */
const ASSET_GROUPS_BULK_MAX_DELETE = ASSETS_BULK_MAX_DELETE;

/** Keys a bulk `filter` object may carry: the Asset groups list filters plus `all`. */
const ASSET_GROUPS_BULK_FILTER_KEYS = ['q', 'team', 'location', 'tag', 'categorization', 'band', 'all'];

/**
 * The shared POST /assets/bulk codes whose messages name assets, mapped to
 * the Asset groups wording (the code is also the message's language key).
 * Codes about the request shape alone (action, filter keys and values) are
 * shared as they are.
 */
const ASSET_GROUPS_BULK_CODES = [
    'AssetBulkSelectionRequired' => 'AssetGroupBulkSelectionRequired',
    'AssetBulkIdsRequired' => 'AssetGroupBulkIdsRequired',
    'AssetBulkIdsInvalid' => 'AssetGroupBulkIdsInvalid',
    'AssetBulkFilterAllAlone' => 'AssetGroupBulkFilterAllAlone',
    'AssetBulkFilterEmpty' => 'AssetGroupBulkFilterEmpty',
    'AssetBulkExpectedCountInvalid' => 'AssetGroupBulkExpectedCountInvalid',
    'AssetBulkExpectedCountRequired' => 'AssetGroupBulkExpectedCountRequired',
    'AssetBulkNoMatch' => 'AssetGroupBulkNoMatch',
    'AssetBulkCountMismatch' => 'AssetGroupBulkCountMismatch',
];

/** Permission key a bulk group action needs, or null for an unknown action (one narrow permission each). */
function asset_groups_bulk_required_permission(string $action): ?string
{
    return $action === 'delete' ? 'asset_group_delete' : null;
}

/**
 * Pure shape validation of a decoded POST /asset-groups/bulk body, with the
 * same machine codes as POST /assets/bulk: an action, exactly one of ids or
 * filter, ids a non-empty list of digits within the delete cap, a filter
 * validated strictly (an unknown key, a non-digit id or an empty filter is
 * refused rather than widening a destructive action; every group is
 * {"all": true}, alone), and expected_count, which a delete by filter must
 * send.
 *
 * @param array<mixed> $body
 * @return array{ok:bool,status:int,error:string,params:array<string,mixed>}
 */
function asset_groups_bulk_validate_request(array $body): array
{
    $r = asset_groups_bulk_validate_request_shared_codes($body);
    $r['error'] = ASSET_GROUPS_BULK_CODES[$r['error']] ?? $r['error'];
    return $r;
}

/** asset_groups_bulk_validate_request() before its codes are mapped to the group wording. */
function asset_groups_bulk_validate_request_shared_codes(array $body): array
{
    $fail = static fn(int $status, string $code, array $params = []): array => ['ok' => false, 'status' => $status, 'error' => $code, 'params' => $params];

    $action = $body['action'] ?? null;
    if (!is_string($action) || $action === '') {
        return $fail(400, 'AssetBulkActionRequired');
    }
    if (asset_groups_bulk_required_permission($action) === null) {
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
        if (count($body['ids']) > ASSET_GROUPS_BULK_MAX_DELETE) {
            return $fail(422, 'AssetGroupBulkTooManyToDelete', ['max' => ASSET_GROUPS_BULK_MAX_DELETE]);
        }
        foreach ($body['ids'] as $id) {
            if (!(is_int($id) || (is_string($id) && ctype_digit($id)))) {
                return $fail(422, 'AssetBulkIdsInvalid');
            }
        }
    } else {
        if (!is_array($body['filter']) || ($body['filter'] && array_is_list($body['filter']))) {
            return $fail(400, 'AssetBulkFilterInvalid');
        }
        foreach (array_keys($body['filter']) as $key) {
            if (!in_array($key, ASSET_GROUPS_BULK_FILTER_KEYS, true)) {
                return $fail(422, 'AssetBulkFilterUnknownKey', ['key' => (string)$key]);
            }
        }
        // The group keys are a subset of the asset filter keys with the same
        // value rules (assets_list_normalize_filter() parses both lists).
        $filter_check = assets_bulk_validate_filter($body['filter']);
        if (!$filter_check['ok']) {
            return $filter_check;
        }
    }

    if (array_key_exists('expected_count', $body) && !assets_bulk_is_digits($body['expected_count'])) {
        return $fail(422, 'AssetBulkExpectedCountInvalid');
    }
    if ($has_filter && !array_key_exists('expected_count', $body)) {
        return $fail(422, 'AssetBulkExpectedCountRequired');
    }
    return ['ok' => true, 'status' => 200, 'error' => '', 'params' => []];
}

/**
 * The selection as de-duplicated group ids: explicit ids as given (positive
 * ints), or every group the Asset groups list returns for the filter (its own
 * search and filters, computed over what the caller can see).
 *
 * @param array<string,mixed> $selection ['ids' => int[]] or ['filter' => array]
 * @return int[]
 */
function asset_groups_bulk_resolve_ids(array $selection): array
{
    if (isset($selection['filter']) && is_array($selection['filter'])) {
        $filter = $selection['filter'];
        unset($filter['all']);
        $opts = array_intersect_key($filter, array_flip(ASSET_GROUPS_BULK_FILTER_KEYS));
        $opts['per_page'] = 0;
        return array_map('intval', array_column(asset_groups_list($opts)['asset_groups'], 'id'));
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
 * Deletes each group through the single-group delete (delete_asset_group():
 * the group row, its junction rows -- memberships and risk links -- and an
 * AssetGroupDeleteAuditLog line). Member assets are never deleted.
 *
 * Status per id: ok | not_found (no such group, including one deleted since
 * it was listed) | error (the delete failed; the rest still run). The caller
 * has already checked the permission.
 *
 * @param int[] $ids
 * @param (callable(int):bool)|null $deleter defaults to delete_asset_group() (tests inject failures)
 * @return array{results:array<int,array{id:int,status:string}>,summary:array{ok:int,failed:int}}
 */
function asset_groups_bulk_delete(array $ids, ?callable $deleter = null): array
{
    $deleter = $deleter ?? 'delete_asset_group';
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));

    $existing = [];
    if ($ids) {
        $db = db_open();
        foreach (array_chunk($ids, assets_list_chunk_size()) as $chunk) {
            $stmt = $db->prepare('SELECT `id` FROM `asset_groups` WHERE `id` IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $existing[(int)$id] = true;
            }
        }
        db_close($db);
    }

    $results = [];
    $ok = 0;
    foreach ($ids as $id) {
        if (!isset($existing[$id])) {
            $results[] = ['id' => $id, 'status' => 'not_found'];
            continue;
        }
        try {
            $done = (bool)$deleter($id);
        } catch (Throwable $e) {
            write_debug_log('asset_groups bulk delete: deleting group ' . $id . ' failed: ' . $e->getMessage(), 'error');
            $done = false;
        }
        // delete_asset_group() answers false when the group is gone by the
        // time it runs (deleted by someone else in the meantime).
        $results[] = ['id' => $id, 'status' => $done ? 'ok' : 'error'];
        if ($done) {
            $ok++;
        }
    }
    return ['results' => $results, 'summary' => ['ok' => $ok, 'failed' => count($results) - $ok]];
}
