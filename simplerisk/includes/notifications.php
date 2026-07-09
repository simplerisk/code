<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Core helpers for SimpleRisk in-app notifications.
 *
 * Public surface (added across the next several tasks):
 *   - is_valid_audience_type(string): bool                            (Task 2)
 *   - is_safe_notification_link(?string): bool                        (Task 2)
 *   - validate_bulk_ids_payload(array): bool                          (Task 2)
 *   - resolve_audience(string, ?int, PDO): array<int>                 (Task 3)
 *   - create_notification(...): array{status:string,recipient_count:int}  (Task 4)
 *   - mark_notifications_read(array, int, PDO): int                   (Task 5)
 *   - move_notifications_to_trash(array, int, PDO): int               (Task 5)
 *   - restore_notifications(array, int, PDO): int                     (Task 5)
 *   - get_notifications_for_user(int, string, int, int, PDO): array   (Task 6)
 *   - get_notification_counts_for_user(int, PDO): array               (Task 6)
 *   - purge_expired_notifications(int, int, PDO): array               (Task 7)
 *   - ingest_remote_feed_item(array, PDO): string                     (Task 8)
 *   - parse_remote_feed_response(string|false, int): array|string|null  (Task 8)
 *   - fetch_remote_feed_json(string, ?string): array{payload:array,last_modified:string|null}|string|null  (Task 8)
 */

require_once(realpath(__DIR__ . '/functions.php'));

define('NOTIFICATION_AUDIENCE_TYPES',        ['user', 'team', 'role', 'all_admin', 'all_user']);
// Subset of NOTIFICATION_AUDIENCE_TYPES used by remote feed ingest (Task 8).
define('NOTIFICATION_REMOTE_AUDIENCE_TYPES', ['all_admin', 'all_user']);
// Valid source values for notifications.
define('NOTIFICATION_SOURCES',              ['workflow', 'remote_promo', 'license']);
define('NOTIFICATION_MAX_LINK_LENGTH',       2048);
define('NOTIFICATION_MAX_BULK_IDS',          100);

/**
 * Whether the given audience type is one of the five legal values for
 * local-source notifications (workflow action + future admin broadcast).
 */
function is_valid_audience_type(string $type): bool
{
    return in_array($type, NOTIFICATION_AUDIENCE_TYPES, true);
}

/**
 * Whether $url is a safe link to render as a "View" action.
 * Accepts http(s):// URLs up to NOTIFICATION_MAX_LINK_LENGTH characters.
 * Rejects empty strings and dangerous schemes (javascript:, data:, file:, vbscript:).
 */
function is_safe_notification_link(?string $url): bool
{
    if ($url === null || $url === '') return false;
    if (strlen($url) > NOTIFICATION_MAX_LINK_LENGTH) return false;
    return (bool)preg_match('/^https?:\/\//i', $url);
}

/**
 * Whether $url is an acceptable remote-feed URL. The feed is fetched
 * server-side over the network, so it is HTTPS-only — http:// and every other
 * scheme is rejected to keep the fetch off a non-TLS channel.
 *
 * Pure (no I/O): the scheme decision is split out of fetch_remote_feed_json so
 * it can be exercised by a @group pure test.
 */
function is_safe_feed_url(string $url): bool
{
    return (bool)preg_match('/^https:\/\//i', $url);
}

/**
 * Validates the payload shape for the bulk-action endpoints
 * (mark-read, trash, restore).
 *
 * Accepted: {"ids": [1, 2, 3, ...]} where each element is a positive int
 * and there are between 1 and NOTIFICATION_MAX_BULK_IDS elements.
 */
function validate_bulk_ids_payload(array $payload): bool
{
    if (!isset($payload['ids']) || !is_array($payload['ids'])) return false;
    $ids = $payload['ids'];
    if (count($ids) === 0 || count($ids) > NOTIFICATION_MAX_BULK_IDS) return false;
    foreach ($ids as $id) {
        if (!is_int($id) || $id <= 0) return false;
    }
    return true;
}

/**
 * Resolves an (audience_type, audience_id) descriptor to a list of user.value
 * IDs at the moment of the call. Snapshot semantics — callers persist the
 * returned list as recipient rows.
 *
 * Returns [] for invalid audience types or audiences that resolve to no users.
 */
function resolve_audience(string $audience_type, ?int $audience_id, PDO $db): array
{
    if (!is_valid_audience_type($audience_type)) {
        return [];
    }

    switch ($audience_type) {
        case 'user':
            if ($audience_id === null) return [];
            $stmt = $db->prepare("SELECT `value` FROM `user` WHERE `value` = :id AND `enabled` = 1");
            $stmt->execute([':id' => $audience_id]);
            break;

        case 'team':
            if ($audience_id === null) return [];
            $stmt = $db->prepare("
                SELECT u.`value`
                FROM `user_to_team` u2t
                INNER JOIN `user` u ON u.`value` = u2t.`user_id`
                WHERE u2t.`team_id` = :id AND u.`enabled` = 1
            ");
            $stmt->execute([':id' => $audience_id]);
            break;

        case 'role':
            if ($audience_id === null) return [];
            $stmt = $db->prepare("SELECT `value` FROM `user` WHERE `role_id` = :id AND `enabled` = 1");
            $stmt->execute([':id' => $audience_id]);
            break;

        case 'all_admin':
            $stmt = $db->prepare("SELECT `value` FROM `user` WHERE `admin` = 1 AND `enabled` = 1");
            $stmt->execute();
            break;

        case 'all_user':
            $stmt = $db->prepare("SELECT `value` FROM `user` WHERE `enabled` = 1");
            $stmt->execute();
            break;

        default:
            return [];
    }

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Inserts a notification and materializes its recipient rows.
 *
 * Returns ['status' => 'inserted'|'duplicate', 'recipient_count' => int].
 * - 'duplicate' means the external_guid already exists (only possible when
 *   $external_guid is non-null). No new rows are inserted in that case.
 * - 'inserted' with recipient_count 0 means the parent was inserted but the
 *   audience resolved to no users — caller should treat this as a soft
 *   failure if it cares (workflow action does; remote feed treats it as
 *   acceptable since the audience may be empty on small instances).
 *
 * The $db parameter is optional — when omitted, a fresh connection is
 * opened and closed by this function. When provided (tests, batch calls),
 * the caller is responsible for the connection's lifecycle.
 */
function create_notification(
    string $source,
    string $title,
    string $body,
    ?string $link,
    string $audience_type,
    ?int $audience_id,
    ?int $created_by,
    ?string $expires_at,
    ?string $external_guid = null,
    ?PDO $db = null
): array {
    $owns_db = ($db === null);
    if ($owns_db) $db = db_open();

    // Resolve the (type, id) descriptor to a user list, then materialize the
    // notification against that explicit recipient set.
    $user_ids = resolve_audience($audience_type, $audience_id, $db);
    $result = create_notification_for_user_ids(
        source:        $source,
        title:         $title,
        body:          $body,
        link:          $link,
        user_ids:      $user_ids,
        created_by:    $created_by,
        expires_at:    $expires_at,
        external_guid: $external_guid,
        db:            $db
    );

    if ($owns_db) db_close($db);
    return $result;
}

/**
 * Inserts a notification and materializes recipient rows for an explicit,
 * pre-resolved list of user IDs.
 *
 * This is the recipient-set entry point used when the caller has already
 * resolved its audience to concrete users — e.g. the workflow "Send In-App
 * Notification" action, which may target several teams/roles/users at once and
 * unions + de-duplicates them into a single recipient set before calling here.
 * `create_notification()` is a thin wrapper that resolves an (audience_type,
 * audience_id) descriptor and delegates to this function.
 *
 * Returns ['status' => 'rejected_source'|'rejected_link'|'duplicate'|'inserted',
 *          'recipient_count' => int].
 * - 'inserted' with recipient_count 0 means the parent was inserted but the
 *   recipient list was empty — caller decides whether that is a soft failure.
 *
 * $user_ids is de-duplicated and integer-cast before insertion, so callers may
 * pass overlapping lists (a user on two selected teams) without double-delivery.
 *
 * The $db parameter is optional — when omitted, a fresh connection is opened
 * and closed by this function. When provided, the caller owns the connection.
 */
function create_notification_for_user_ids(
    string $source,
    string $title,
    string $body,
    ?string $link,
    array $user_ids,
    ?int $created_by,
    ?string $expires_at,
    ?string $external_guid = null,
    ?PDO $db = null
): array {
    $owns_db = ($db === null);
    if ($owns_db) $db = db_open();

    // Validate source
    if (!in_array($source, NOTIFICATION_SOURCES, true)) {
        if ($owns_db) db_close($db);
        return ['status' => 'rejected_source', 'recipient_count' => 0];
    }

    // Validate link — reject non-http(s) schemes regardless of source
    if ($link !== null && $link !== '' && !is_safe_notification_link($link)) {
        if ($owns_db) db_close($db);
        return ['status' => 'rejected_link', 'recipient_count' => 0];
    }

    // INSERT IGNORE makes duplicate external_guid a no-op.
    $stmt = $db->prepare("
        INSERT IGNORE INTO `notifications`
          (`source`, `title`, `body`, `link`, `external_guid`, `created_by`, `expires_at`)
        VALUES
          (:source, :title, :body, :link, :guid, :by, :exp)
    ");
    $stmt->execute([
        ':source' => $source,
        ':title'  => $title,
        ':body'   => $body,
        ':link'   => $link,
        ':guid'   => $external_guid,
        ':by'     => $created_by,
        ':exp'    => $expires_at,
    ]);

    $notification_id = (int)$db->lastInsertId();
    if ($notification_id === 0) {
        // Duplicate external_guid (INSERT IGNORE no-op).
        if ($owns_db) db_close($db);
        return ['status' => 'duplicate', 'recipient_count' => 0];
    }

    // De-duplicate and integer-cast the recipient list, then fan out.
    $user_ids = array_values(array_unique(array_map('intval', $user_ids)));
    $recipient_count = 0;

    if (!empty($user_ids)) {
        $placeholders = implode(',', array_fill(0, count($user_ids), '(?, ?)'));
        $params = [];
        foreach ($user_ids as $uid) {
            $params[] = $notification_id;
            $params[] = $uid;
        }
        $insert = $db->prepare("
            INSERT IGNORE INTO `notification_recipients` (`notification_id`, `user_id`)
            VALUES {$placeholders}
        ");
        $insert->execute($params);
        $recipient_count = $insert->rowCount();
    }

    if ($owns_db) db_close($db);
    return ['status' => 'inserted', 'recipient_count' => $recipient_count];
}

/**
 * Mark the given notifications as read for the given user.
 * Returns the number of recipient rows actually updated (already-read rows
 * and rows belonging to other users are silently skipped).
 */
function mark_notifications_read(array $notification_ids, int $user_id, PDO $db): int
{
    return _update_notification_recipients($notification_ids, $user_id, $db,
        "SET `read_at` = NOW()",
        "AND `read_at` IS NULL"
    );
}

function move_notifications_to_trash(array $notification_ids, int $user_id, PDO $db): int
{
    return _update_notification_recipients($notification_ids, $user_id, $db,
        "SET `deleted_at` = NOW()",
        "AND `deleted_at` IS NULL"
    );
}

function restore_notifications(array $notification_ids, int $user_id, PDO $db): int
{
    return _update_notification_recipients($notification_ids, $user_id, $db,
        "SET `deleted_at` = NULL",
        "AND `deleted_at` IS NOT NULL"
    );
}

/**
 * Internal: runs an UPDATE on notification_recipients filtered by the
 * user's own rows. Returns the row-count of affected rows.
 */
function _update_notification_recipients(
    array $notification_ids,
    int $user_id,
    PDO $db,
    string $set_clause,
    string $extra_where
): int {
    $notification_ids = array_values(array_filter($notification_ids, fn($id) => is_int($id) && $id > 0));
    if (empty($notification_ids)) return 0;

    $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
    $sql = "UPDATE `notification_recipients` {$set_clause}
            WHERE `notification_id` IN ({$placeholders})
              AND `user_id` = ?
              {$extra_where}";
    $stmt = $db->prepare($sql);
    $params = $notification_ids;
    $params[] = $user_id;
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Returns counts for the bell badge + tab labels — {unread, all, trash}.
 * Filtered to the given user. Single query using the covering index.
 */
function get_notification_counts_for_user(int $user_id, PDO $db): array
{
    $stmt = $db->prepare("
        SELECT
          COALESCE(SUM(`deleted_at` IS NULL AND `read_at` IS NULL), 0) AS unread,
          COALESCE(SUM(`deleted_at` IS NULL), 0)                       AS `all`,
          COALESCE(SUM(`deleted_at` IS NOT NULL), 0)                   AS trash
        FROM `notification_recipients`
        WHERE `user_id` = :uid
    ");
    $stmt->execute([':uid' => $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'unread' => (int)$row['unread'],
        'all'    => (int)$row['all'],
        'trash'  => (int)$row['trash'],
    ];
}

/**
 * Returns notifications for the given user filtered by tab.
 *
 * Returns ['items' => [...], 'total' => int, 'limit' => int, 'offset' => int].
 * Each item: id, title, body, link, source, is_promo, read_at, deleted_at, created_at.
 *
 * $tab must be 'unread' | 'all' | 'trash'. Invalid → empty result.
 * $limit is clamped to [1, 100]. $offset >= 0.
 */
function get_notifications_for_user(int $user_id, string $tab, int $limit, int $offset, PDO $db): array
{
    $limit  = max(1, min(100, $limit));
    $offset = max(0, $offset);

    switch ($tab) {
        case 'unread': $where = "nr.`deleted_at` IS NULL AND nr.`read_at` IS NULL"; break;
        case 'all':    $where = "nr.`deleted_at` IS NULL"; break;
        case 'trash':  $where = "nr.`deleted_at` IS NOT NULL"; break;
        default:
            return ['items' => [], 'total' => 0, 'limit' => $limit, 'offset' => $offset];
    }

    $count_stmt = $db->prepare("
        SELECT COUNT(*) FROM `notification_recipients` nr
        WHERE nr.`user_id` = :uid AND {$where}
    ");
    $count_stmt->execute([':uid' => $user_id]);
    $total = (int)$count_stmt->fetchColumn();

    $list_stmt = $db->prepare("
        SELECT
          n.`id`, n.`title`, n.`body`, n.`link`, n.`source`,
          (n.`source` = 'remote_promo') AS is_promo,
          nr.`read_at`, nr.`deleted_at`, n.`created_at`
        FROM `notification_recipients` nr
        INNER JOIN `notifications` n ON n.`id` = nr.`notification_id`
        WHERE nr.`user_id` = :uid AND {$where}
        ORDER BY n.`created_at` DESC, n.`id` DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $list_stmt->execute([':uid' => $user_id]);
    $rows = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id']       = (int)$r['id'];
        $r['is_promo'] = (bool)$r['is_promo'];
    }
    unset($r);

    return ['items' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
}

/**
 * Three-step retention purge. Runs in a transaction.
 *
 * 1. Drop read-and-not-trashed recipient rows older than $read_days.
 * 2. Drop trashed recipient rows older than $trash_days.
 * 3. Drop notifications whose expires_at is in the past, OR which have
 *    no remaining recipient rows. CASCADE pulls any leftover recipients.
 *
 * Returns ['purged_read' => N, 'purged_trash' => M, 'purged_expired' => K].
 */
function purge_expired_notifications(int $read_days, int $trash_days, PDO $db): array
{
    $db->beginTransaction();
    try {
        $r1 = $db->prepare("
            DELETE FROM `notification_recipients`
            WHERE `deleted_at` IS NULL
              AND `read_at` IS NOT NULL
              AND `read_at` < (NOW() - INTERVAL :d DAY)
        ");
        $r1->execute([':d' => $read_days]);
        $purged_read = $r1->rowCount();

        $r2 = $db->prepare("
            DELETE FROM `notification_recipients`
            WHERE `deleted_at` IS NOT NULL
              AND `deleted_at` < (NOW() - INTERVAL :d DAY)
        ");
        $r2->execute([':d' => $trash_days]);
        $purged_trash = $r2->rowCount();

        // Wrap subquery in a derived table to work around MySQL's restriction
        // on deleting from and selecting the same table in a single statement.
        $r3 = $db->prepare("
            DELETE FROM `notifications`
            WHERE (`expires_at` IS NOT NULL AND `expires_at` < NOW())
               OR `id` NOT IN (SELECT * FROM (SELECT DISTINCT `notification_id` FROM `notification_recipients`) AS x)
        ");
        $r3->execute();
        $purged_expired = $r3->rowCount();

        $db->commit();

        return [
            'purged_read'    => $purged_read,
            'purged_trash'   => $purged_trash,
            'purged_expired' => $purged_expired,
        ];
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Validates and inserts one remote feed item. Returns one of:
 *   'inserted'  — a new notifications row + recipient rows created
 *   'duplicate' — UNIQUE constraint matched the existing external_guid
 *   'rejected'  — validation failed (missing field, bad audience, unsafe link)
 *
 * Sanitizes body through HTMLPurifier even though the source is
 * SimpleRisk-controlled — defense in depth.
 */
function ingest_remote_feed_item(array $item, PDO $db): string
{
    $guid          = trim((string)($item['guid']          ?? ''));
    $title         = trim((string)($item['title']         ?? ''));
    $body          = trim((string)($item['body']          ?? ''));
    $link          = trim((string)($item['link']          ?? ''));
    $audience_type = trim((string)($item['audience_type'] ?? ''));
    $audience_id   = $item['audience_id'] ?? null;
    $expires_at    = $item['expires_at']  ?? null;

    if ($guid === '' || $title === '' || $body === '') {
        write_debug_log("Remote Feed: rejected — missing required field (guid='{$guid}')", "warning");
        return 'rejected';
    }
    if (!in_array($audience_type, NOTIFICATION_REMOTE_AUDIENCE_TYPES, true)) {
        write_debug_log("Remote Feed: rejected {$guid} — invalid audience_type '{$audience_type}'", "warning");
        return 'rejected';
    }
    if ($audience_id !== null) {
        write_debug_log("Remote Feed: rejected {$guid} — audience_id must be null for remote items", "warning");
        return 'rejected';
    }
    if ($link !== '' && !is_safe_notification_link($link)) {
        write_debug_log("Remote Feed: rejected {$guid} — unsafe link", "warning");
        return 'rejected';
    }
    // Normalize/validate the optional expires_at. A blank value means "no
    // expiry"; a non-empty value that doesn't parse as a date would silently
    // coerce to 0000-00-00 in non-strict MySQL and get purged on the next
    // retention sweep, so reject the item rather than store a self-expiring row.
    if ($expires_at !== null) {
        $expires_at = trim((string)$expires_at);
        if ($expires_at === '') {
            $expires_at = null;
        } elseif (strtotime($expires_at) === false) {
            write_debug_log("Remote Feed: rejected {$guid} — invalid expires_at", "warning");
            return 'rejected';
        }
    }

    // Body sanitization (defense in depth)
    $safe_body = purify_html($body);

    $result = create_notification(
        source:        'remote_promo',
        title:         $title,
        body:          $safe_body,
        link:          $link !== '' ? $link : null,
        audience_type: $audience_type,
        audience_id:   null,
        created_by:    null,
        expires_at:    $expires_at,
        external_guid: $guid,
        db:            $db
    );

    if ($result['status'] === 'rejected_link') {
        write_debug_log("Remote Feed: rejected {$guid} - create_notification rejected link", "warning");
        return 'rejected';
    }
    return $result['status'];
}

/**
 * Parses a remote-feed HTTP response into a payload, 'not_modified', or null.
 * Pure (no I/O); HTTP transport happens in fetch_remote_feed_json.
 *
 * @param string|false $body       Response body (string on success, false on cURL failure)
 * @param int          $http_code  HTTP status code
 * @return array|string|null  Decoded payload array on 200+valid JSON,
 *                            'not_modified' on 304, null otherwise
 */
function parse_remote_feed_response($body, int $http_code)
{
    if ($http_code === 304) return 'not_modified';
    if ($http_code !== 200) return null;
    if (!is_string($body) || $body === '') return null;
    $payload = json_decode($body, true);
    return is_array($payload) ? $payload : null;
}

/**
 * GET the remote feed JSON.
 *
 * Routes through configure_curl_proxy() (for proxy/CA bundle) and captures the
 * Last-Modified response header so the caller can persist it for future
 * If-Modified-Since conditional requests.
 *
 * Only https:// URLs are accepted; http:// is rejected to enforce HTTPS-only.
 *
 * Returns one of:
 *   - ['payload' => array, 'last_modified' => string|null]  on 200 + valid JSON
 *   - 'not_modified'                                         on 304
 *   - null                                                   on any failure
 *
 * @param string      $url           Feed URL (must be https://).
 * @param string|null $last_modified RFC 7231 date string for If-Modified-Since,
 *                                   or null to skip the conditional header.
 * @return array{payload:array,last_modified:string|null}|string|null
 */
function fetch_remote_feed_json(string $url, ?string $last_modified = null): array|string|null
{
    // Enforce HTTPS-only; reject plain HTTP and any other scheme.
    if (!is_safe_feed_url($url)) {
        write_debug_log("Remote Feed: URL scheme is not HTTPS — rejecting.", "warning");
        return null;
    }

    $request_headers = ['User-Agent: SimpleRisk-Notifications/1.0'];
    if ($last_modified !== null && $last_modified !== '') {
        // Strip CR/LF/NUL so a malicious feed server can't smuggle extra
        // request headers via a poisoned stored Last-Modified value.
        $request_headers[] = 'If-Modified-Since: ' . preg_replace('/[\r\n\0]/', '', $last_modified);
    }

    // Capture the Last-Modified response header via CURLOPT_HEADERFUNCTION.
    $captured_last_modified = null;
    $header_callback = function($ch, string $header_line) use (&$captured_last_modified): int {
        // @phan-suppress-next-line PhanUnusedClosureParameter
        if (stripos($header_line, 'Last-Modified:') === 0) {
            // Strip CR/LF/NUL before this value is persisted and later replayed
            // into an outbound If-Modified-Since header.
            $captured_last_modified = preg_replace('/[\r\n\0]/', '',
                trim(substr($header_line, strlen('Last-Modified:'))));
        }
        return strlen($header_line);
    };

    $ch = curl_init($url);

    // Apply the shared proxy + CA-bundle config so this request honours the
    // same proxy settings as every other outbound call in SimpleRisk.
    configure_curl_proxy($ch);

    // SSL: use the Composer CA bundle for peer verification.
    $ca_bundle = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        // Pin both the initial request and any redirect hops to HTTPS so a 3xx
        // from the feed server can't downgrade to http://, file://, gopher://,
        // etc. and reach an internal address (SSRF). is_safe_feed_url() only
        // covers the first URL; CURLOPT_REDIR_PROTOCOLS covers the chain.
        CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => $request_headers,
        CURLOPT_HEADERFUNCTION => $header_callback,
    ]);
    if ($ca_bundle) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca_bundle);
    }

    $body      = @curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error     = curl_error($ch);

    if ($body === false) {
        write_debug_log("Remote Feed: cURL error: {$error}", "error");
        return null;
    }

    $parsed = parse_remote_feed_response($body, $http_code);

    if ($parsed === null) {
        if ($http_code !== 200) {
            write_debug_log("Remote Feed: HTTP {$http_code}", "warning");
        } else {
            write_debug_log("Remote Feed: response is not valid JSON", "warning");
        }
        return null;
    }

    if ($parsed === 'not_modified') {
        return 'not_modified';
    }

    // 200 + valid JSON — return payload alongside the captured Last-Modified header.
    return [
        'payload'       => $parsed,
        'last_modified' => $captured_last_modified,
    ];
}


// ============================================================================
// HTTP handlers — registered on the v2 router in simplerisk/api/v2/index.php
// ============================================================================

/**
 * GET /api/v2/notifications/counts
 *
 * Returns {unread, all, trash} for the authenticated user.
 * Used by the bell badge polling and the dropdown tab labels.
 */
function api_v2_notifications_counts(): void
{
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        api_v2_json_result(401, 'Authentication required.', null);
        return;
    }

    $db     = db_open();
    $counts = get_notification_counts_for_user($uid, $db);
    db_close($db);

    api_v2_json_result(200, 'Notification counts.', $counts);
}

/**
 * GET /api/v2/notifications?tab=unread|all|trash&limit=50&offset=0
 */
function api_v2_notifications_list(): void
{
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        api_v2_json_result(401, 'Authentication required.', null);
        return;
    }

    $tab    = $_GET['tab']    ?? '';
    $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    if (!in_array($tab, ['unread', 'all', 'trash'], true)) {
        api_v2_json_result(400, "Invalid tab. Must be 'unread', 'all', or 'trash'.", null);
        return;
    }

    $db     = db_open();
    $result = get_notifications_for_user($uid, $tab, $limit, $offset, $db);
    db_close($db);

    api_v2_json_result(200, 'Notifications.', $result);
}

/**
 * Shared implementation for the three bulk-write endpoints.
 * $action: 'mark_read' | 'trash' | 'restore'
 */
function _api_v2_notifications_bulk_write(string $action): void
{
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        api_v2_json_result(401, 'Authentication required.', null);
        return;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        // Form-encoded body fallback (Leaf supports both)
        $payload = $_POST;
    }
    // Normalize ids to integers if they came in as strings (form-encoded path)
    if (isset($payload['ids']) && is_array($payload['ids'])) {
        $payload['ids'] = array_map(fn($v) => is_numeric($v) ? (int)$v : $v, $payload['ids']);
    }

    if (!validate_bulk_ids_payload($payload)) {
        api_v2_json_result(400, 'Invalid payload. Expected {"ids":[positive int, ...]} (1-100 elements).', null);
        return;
    }

    $db = db_open();
    switch ($action) {
        case 'mark_read': $updated = mark_notifications_read($payload['ids'], $uid, $db); break;
        case 'trash':     $updated = move_notifications_to_trash($payload['ids'], $uid, $db); break;
        case 'restore':   $updated = restore_notifications($payload['ids'], $uid, $db); break;
        default:
            db_close($db);
            api_v2_json_result(500, "Unknown bulk action '{$action}'.", null);
            return;
    }
    $counts = get_notification_counts_for_user($uid, $db);
    db_close($db);

    api_v2_json_result(200, 'Bulk action applied.', [
        'updated' => $updated,
        'counts'  => $counts,
    ]);
}

function api_v2_notifications_mark_read(): void { _api_v2_notifications_bulk_write('mark_read'); }
function api_v2_notifications_trash():     void { _api_v2_notifications_bulk_write('trash');     }
function api_v2_notifications_restore():   void { _api_v2_notifications_bulk_write('restore');   }

?>
