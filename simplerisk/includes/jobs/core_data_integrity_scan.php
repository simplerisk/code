<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../queues.php'));
require_once(realpath(__DIR__ . '/../data_integrity.php'));
require_once(realpath(__DIR__ . '/../notifications.php'));

/**
 * Runs every registered detector, upserts findings (dedup on
 * upsert_data_integrity_issue()'s natural key: issue_type, table_name,
 * column_name, record_id), auto-resolves open issues a detector's recheck_fn
 * confirms are no longer broken, purges old resolved issues, and reconciles
 * the admin notification: creates one the first time open issues appear,
 * resolves it for every recipient the moment the queue empties. Called by
 * queue_check (weekly/scheduled) and directly by the on-demand scan-trigger
 * API endpoint -- both paths share this single implementation.
 */
function run_data_integrity_scan(PDO $db): void
{
    $had_open_issues_before = data_integrity_has_open_issues($db);

    foreach (data_integrity_detectors() as $issue_type => $detector) {
        $found = ($detector['scan_fn'])($db);
        foreach ($found as $issue) {
            upsert_data_integrity_issue(
                $db,
                $issue_type,
                $issue['table_name'],
                $issue['column_name'],
                $issue['record_id'],
                $issue['broken_value'],
                $issue['suggested_value']
            );
        }

        // A detector without a recheck_fn opts out of auto-reconciliation
        // -- its issues stay open until repaired, matching the pre-existing
        // behavior for every detector before this was added.
        if ($detector['recheck_fn'] !== null) {
            resolve_stale_data_integrity_issues($db, $issue_type, $detector['recheck_fn'], $found);
        }
    }

    // Resolved issues otherwise accumulate in data_integrity_issues forever.
    purge_resolved_data_integrity_issues($db);

    $has_open_issues_now = data_integrity_has_open_issues($db);

    if (!$had_open_issues_before && $has_open_issues_now) {
        // Absolute link to the Data Integrity page from simplerisk_base_url.
        // create_notification() rejects any link that isn't a safe http(s)
        // URL outright (is_safe_notification_link() in notifications.php) --
        // a bare relative path here would make every call return
        // 'rejected_link' and silently drop the notification. Mirrors the
        // pattern in licensing.php's queue_license_expiration_notifications().
        $base_url = (string)get_setting('simplerisk_base_url');
        $link = $base_url !== '' ? rtrim($base_url, '/') . '/admin/data_integrity.php' : null;
        if ($link !== null && !is_safe_notification_link($link)) {
            $link = null;
        }

        create_notification(
            source: 'data_integrity',
            title: _lang_raw('DataIntegrityNotificationTitle'),
            body: _lang_raw('DataIntegrityNotificationBody'),
            link: $link,
            audience_type: 'all_admin',
            audience_id: null,
            created_by: null,
            expires_at: null,
            external_guid: 'data_integrity_open_issues',
            db: $db
        );
    } elseif ($had_open_issues_before && !$has_open_issues_now) {
        resolve_notification_for_all_recipients('data_integrity_open_issues', $db);

        // Free the guid for reuse. notifications.external_guid has a UNIQUE
        // KEY, and create_notification_for_user_ids() dedupes via INSERT
        // IGNORE -- without this, the trashed-but-still-present parent row
        // from this occurrence would silently block every future
        // create_notification() call for this guid (a no-op 'duplicate'
        // result) until the 30-day trash-retention purge finally deletes it,
        // meaning a resolve -> reopen cycle inside that window would never
        // notify admins of the new occurrence. MySQL/InnoDB treats each NULL
        // as distinct under a UNIQUE KEY, so this doesn't collide with
        // anything -- the trashed row (and its title/body/timestamps) stays
        // exactly as-is for the admin's Trash view; only the identity used
        // for future dedup is cleared.
        $stmt = $db->prepare("UPDATE `notifications` SET `external_guid` = NULL WHERE `external_guid` = :guid");
        $stmt->bindValue(':guid', 'data_integrity_open_issues', PDO::PARAM_STR);
        $stmt->execute();
    }
}

return [
    'type' => 'core_data_integrity_scan',

    'task_check' => function (PDO $db) {
        $last = get_setting('queue_timestamp_last_data_integrity_scan', false, false, db: $db);
        if ($last && (time() - (int)$last) < 7 * 24 * 60 * 60) {
            return false;
        }
        if (!empty(get_queue_items($db, 'core_data_integrity_scan', ['pending', 'in_progress']))) {
            return false;
        }
        return queue_task($db, 'core_data_integrity_scan', ['triggered_at' => time()], 50, 5, 3600);
    },

    'queue_check' => function (array $task, PDO $db) {
        return run_timestamped_queue_check(
            $task,
            $db,
            'queue_timestamp_last_data_integrity_scan',
            'core_data_integrity_scan',
            function () use ($db) {
                run_data_integrity_scan($db);
                return true;
            }
        );
    },
];
