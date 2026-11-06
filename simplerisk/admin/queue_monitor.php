<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    global $lang, $escaper;

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    // Queue Monitor (SR-2408). A static .sr-table-card shell; queue-monitor.js
    // fills it from the v2 API (GET /admin/queue, /summary, /steps, /payload)
    // and polls for updates; the row actions, bulk bar and retention modal
    // POST to /admin/queue/cancel|restart|run-now|delete|settings. Not a DataTables
    // grid: paging, sorting and filtering are server-side, so the 'datatables'
    // module is not loaded. sr-row-actions-menu.js drives the row-actions
    // overflow menu that _tables.scss swaps in at narrow widths and on touch.
    render_header_and_sidebar(['CUSTOM:sr-row-actions-menu.js', 'CUSTOM:pages/queue-monitor.js'], ['check_admin' => true]);

    // load_all_jobs() is in queues.php, QUEUE_BULK_MAX in queue_actions.php and
    // QUEUE_RETENTION_MAX_DAYS in queue_monitor.php; each is declared here
    // because this page uses it directly (function reachability rule).
    require_once(realpath(__DIR__ . '/../includes/queues.php'));
    require_once(realpath(__DIR__ . '/../includes/queue_monitor.php'));
    require_once(realpath(__DIR__ . '/../includes/queue_actions.php'));

    // The task-type filter lists every registered job type (Core plus any
    // installed Extra's jobs). Read from the job registry rather than a
    // SELECT DISTINCT over queue_tasks, which would be an unindexed scan.
    $queue_task_types = [];
    foreach (load_all_jobs() as $job) {
        if (isset($job['type']) && is_string($job['type']) && $job['type'] !== '') {
            $queue_task_types[$job['type']] = true;
        }
    }
    $queue_task_types = array_keys($queue_task_types);
    sort($queue_task_types, SORT_STRING);

    // Status pills, in display order. The key is the API's filter value ('' = all).
    $queue_status_pills = [
        ''          => $lang['All'],
        'pending'   => $lang['Pending'],
        'running'   => $lang['UpgradeStateRunning'],
        'retrying'  => $lang['Retrying'],
        'completed' => $lang['Completed'],
        'failed'    => $lang['Failed'],
        'canceled'  => $lang['Canceled'],
    ];
?>

<div class="sr-qm" id="queue-monitor" data-bulk-max="<?= (int)QUEUE_BULK_MAX ?>" data-retention-max="<?= (int)QUEUE_RETENTION_MAX_DAYS ?>">
    <div class="sr-table-card" id="queue-monitor-grid">
        <div class="sr-table-toolbar" id="qm-toolbar">
            <div class="sr-table-title">
                <?= $escaper->escapeHtml($lang['QueueMonitor']) ?>
                <span class="sr-table-count d-none" id="qm-count"></span>
            </div>
            <div class="sr-table-tools">
                <span class="sr-qm-live" id="qm-live">
                    <span class="sr-qm-live-dot" aria-hidden="true"></span>
                    <span id="qm-live-text"></span>
                </span>
                <button type="button" class="btn btn-outline-secondary btn-sm sr-qm-retention-btn" id="qm-retention-open">
                    <i class="fa fa-gear" aria-hidden="true"></i>
                    <?= $escaper->escapeHtml($lang['TaskRetention']) ?>
                </button>
            </div>
        </div>

        <!-- Row checks replace the toolbar with this bar (design-system §6). The
             actions shown depend on the status filter: Cancel selected for
             Pending/Running/Retrying, Restart selected and Delete selected for
             Failed/Canceled, Delete selected for Completed, none for All. -->
        <div class="sr-bulk-bar d-none" id="qm-bulk-bar">
            <button type="button" class="sr-bulk-clear" id="qm-bulk-clear" aria-label="<?= $escaper->escapeHtmlAttr($lang['Clear']) ?>">&times;</button>
            <span class="sr-bulk-count" id="qm-bulk-count"></span>
            <button type="button" class="sr-bulk-lnk d-none" id="qm-select-all-filtered"></button>
            <span class="sr-qm-bulk-note d-none" id="qm-bulk-note"></span>
            <div class="sr-bulk-actions">
                <button type="button" class="btn btn-sm d-none" id="qm-bulk-cancel" data-bulk-action="cancel"><?= $escaper->escapeHtml($lang['CancelSelected']) ?></button>
                <button type="button" class="btn btn-sm d-none" id="qm-bulk-restart" data-bulk-action="restart"><?= $escaper->escapeHtml($lang['RestartSelected']) ?></button>
                <button type="button" class="btn btn-sm d-none" id="qm-bulk-delete" data-bulk-action="delete"><?= $escaper->escapeHtml($lang['DeleteSelected']) ?></button>
            </div>
        </div>

        <div class="sr-qm-health" id="qm-health"></div>

        <!-- Announces state transitions only (polling failed/resumed, a worker
             stopped/recovered, session ended); the ticking text above is not live. -->
        <div class="visually-hidden" id="qm-announcer" aria-live="polite" aria-atomic="true"></div>

        <div class="alert alert-warning d-flex align-items-center gap-3 mx-3 mb-2 d-none sr-qm-auth-notice" id="qm-auth-notice">
            <i class="fa fa-lock" aria-hidden="true"></i>
            <span class="flex-grow-1"><?= $escaper->escapeHtml($lang['SessionEndedRefreshToSignIn']) ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="qm-auth-reload"><?= $escaper->escapeHtml($lang['Refresh']) ?></button>
        </div>

        <div class="sr-qm-filters">
            <div class="dt-search sr-qm-search">
                <input type="search" id="qm-search" maxlength="200" autocomplete="off"
                       placeholder="<?= $escaper->escapeHtmlAttr($lang['SearchTaskTypeIdOrErrorText']) ?>"
                       aria-label="<?= $escaper->escapeHtmlAttr($lang['Search']) ?>">
            </div>
            <div class="sr-table-status-filter sr-qm-status-filter" id="qm-status-filter" role="group" aria-label="<?= $escaper->escapeHtmlAttr($lang['Status']) ?>">
<?php foreach ($queue_status_pills as $status_key => $status_label) { ?>
                <button type="button" class="sr-status-chip<?= $status_key === '' ? ' active' : '' ?>" data-status="<?= $escaper->escapeHtmlAttr($status_key) ?>" aria-pressed="<?= $status_key === '' ? 'true' : 'false' ?>">
                    <?= $escaper->escapeHtml($status_label) ?>
                    <span class="n" data-count-for="<?= $escaper->escapeHtmlAttr($status_key === '' ? 'all' : $status_key) ?>"></span>
                </button>
<?php } ?>
            </div>
            <div class="sr-qm-filter-selects">
                <select class="form-select" id="qm-task-type" aria-label="<?= $escaper->escapeHtmlAttr($lang['Task']) ?>">
                    <option value=""><?= $escaper->escapeHtml($lang['AllTypes']) ?></option>
<?php foreach ($queue_task_types as $task_type) { ?>
                    <option value="<?= $escaper->escapeHtmlAttr($task_type) ?>"><?= $escaper->escapeHtml($task_type) ?></option>
<?php } ?>
                </select>
                <label class="sr-qm-date-label" for="qm-date-from"><?= $escaper->escapeHtml($lang['From']) ?></label>
                <input type="date" class="form-control" id="qm-date-from">
                <label class="sr-qm-date-label" for="qm-date-to"><?= $escaper->escapeHtml($lang['to']) ?></label>
                <input type="date" class="form-control" id="qm-date-to">
            </div>
        </div>

        <div class="sr-table-scroll" id="qm-table-scroll">
            <table class="sr-table" id="qm-table">
                <thead>
                    <tr>
                        <th class="sr-check-col"><input type="checkbox" class="form-check-input" id="qm-select-all" aria-label="<?= $escaper->escapeHtmlAttr($lang['SelectAll']) ?>"></th>
                        <th class="sr-caret-col"><span class="visually-hidden"><?= $escaper->escapeHtml($lang['Expand']) ?></span></th>
                        <th class="sr-qm-id-col sr-sortable" data-order-col="id" tabindex="0"><?= $escaper->escapeHtml($lang['ID']) ?><i class="fa fa-sort sr-sort-icon" aria-hidden="true"></i></th>
                        <th class="sr-sortable" data-order-col="task_type" tabindex="0"><?= $escaper->escapeHtml($lang['Task']) ?><i class="fa fa-sort sr-sort-icon" aria-hidden="true"></i></th>
                        <th class="sr-sortable" data-order-col="status" tabindex="0"><?= $escaper->escapeHtml($lang['Status']) ?><i class="fa fa-sort sr-sort-icon" aria-hidden="true"></i></th>
                        <th><?= $escaper->escapeHtml($lang['Progress']) ?></th>
                        <th class="sr-num sr-sortable" data-order-col="attempts" tabindex="0"><?= $escaper->escapeHtml($lang['attempts']) ?><i class="fa fa-sort sr-sort-icon" aria-hidden="true"></i></th>
                        <th class="sr-sortable" data-order-col="priority" tabindex="0"><?= $escaper->escapeHtml($lang['Priority']) ?><i class="fa fa-sort sr-sort-icon" aria-hidden="true"></i></th>
                        <th class="sr-sortable" data-order-col="created_at" tabindex="0"><?= $escaper->escapeHtml($lang['DiscoveryStatusQueued']) ?><i class="fa fa-sort sr-sort-icon" aria-hidden="true"></i></th>
                        <th class="sr-num"><?= $escaper->escapeHtml($lang['Duration']) ?></th>
                        <th class="sr-actions-col"><span class="visually-hidden"><?= $escaper->escapeHtml($lang['Actions']) ?></span></th>
                    </tr>
                </thead>
                <tbody id="qm-tbody"></tbody>
            </table>
        </div>

        <div class="sr-table-empty d-none" id="qm-empty">
            <div class="sr-table-empty-icon"><i class="fa fa-inbox" id="qm-empty-icon" aria-hidden="true"></i></div>
            <div class="sr-table-empty-title" id="qm-empty-title"></div>
            <div class="sr-table-empty-body" id="qm-empty-body"></div>
            <div class="sr-table-empty-action d-none" id="qm-empty-action-wrap">
                <button type="button" class="btn btn-outline-secondary" id="qm-empty-action"></button>
            </div>
        </div>

        <div class="sr-table-foot">
            <div class="sr-table-foot-left">
                <span class="dt-info" id="qm-info"></span>
            </div>
            <div class="sr-table-foot-right">
                <div class="dt-paging" id="qm-pager"></div>
            </div>
        </div>
    </div>

    <div class="modal fade sr-modal" id="qm-payload-modal" tabindex="-1" aria-labelledby="qm-payload-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="sr-modal-icon"><i class="fa fa-code" aria-hidden="true"></i></span>
                    <h4 class="modal-title" id="qm-payload-title"></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
                </div>
                <div class="modal-body">
                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-file-code" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['Payload']) ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <p class="sr-qhint sr-qm-payload-hint"><?= $escaper->escapeHtml($lang['PayloadSecretsRedacted']) ?></p>
                            <pre class="sr-qm-payload" id="qm-payload-pre"></pre>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Close']) ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- One confirm dialog for every row and bulk action (design-system §8).
         queue-monitor.js sets the title, body, notes and verb as text. -->
    <div class="modal fade sr-modal" id="qm-confirm-modal" tabindex="-1" aria-labelledby="qm-confirm-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="sr-modal-icon" id="qm-confirm-icon-wrap"><i class="fa fa-circle-question" id="qm-confirm-icon" aria-hidden="true"></i></span>
                    <h4 class="modal-title" id="qm-confirm-title"></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
                </div>
                <div class="modal-body sr-qform">
                    <section class="sr-qcard">
                        <div class="sr-qcard-body sr-qm-confirm">
                            <p class="sr-qm-confirm-body" id="qm-confirm-body"></p>
                            <div class="sr-qnote d-none" id="qm-confirm-rerun">
                                <i class="fa fa-triangle-exclamation sr-qnote-ico" aria-hidden="true"></i>
                                <span id="qm-confirm-rerun-text"></span>
                            </div>
                            <div class="sr-qnote d-none" id="qm-confirm-worker">
                                <i class="fa fa-circle-pause sr-qnote-ico" aria-hidden="true"></i>
                                <span id="qm-confirm-worker-text"></span>
                            </div>
                            <div class="alert alert-danger d-none mb-0" id="qm-confirm-error" role="alert"></div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal" id="qm-confirm-dismiss"><?= $escaper->escapeHtml($lang['Back']) ?></button>
                    <button type="button" class="btn btn-danger" id="qm-confirm-commit"></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Retention settings (design-system §8 form-in-modal): GET/POST
         /admin/queue/settings. -->
    <div class="modal fade sr-modal" id="qm-retention-modal" tabindex="-1" aria-labelledby="qm-retention-title" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="qm-retention-form" novalidate autocomplete="off">
                    <div class="modal-header">
                        <span class="sr-modal-icon"><i class="fa fa-clock-rotate-left" aria-hidden="true"></i></span>
                        <h4 class="modal-title" id="qm-retention-title"><?= $escaper->escapeHtml($lang['TaskRetention']) ?></h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
                    </div>
                    <div class="modal-body sr-qform">
                        <section class="sr-qcard">
                            <div class="sr-qcard-body">
                                <p class="sr-qhint sr-qm-retention-hint" id="qm-retention-hint"><?= $escaper->escapeHtml($lang['QueueRetentionHint']) ?></p>
                                <div class="sr-qgrid">
                                    <div class="sr-qfield">
                                        <label class="sr-qlabel" for="qm-retention-completed"><?= $escaper->escapeHtml($lang['CompletedAndCanceledTasksDays']) ?></label>
                                        <input type="number" class="form-control" id="qm-retention-completed" name="completed_days" min="0" max="<?= (int)QUEUE_RETENTION_MAX_DAYS ?>" step="1" inputmode="numeric" aria-describedby="qm-retention-zero qm-retention-completed-error" disabled>
                                        <div class="invalid-feedback" id="qm-retention-completed-error"></div>
                                    </div>
                                    <div class="sr-qfield">
                                        <label class="sr-qlabel" for="qm-retention-failed"><?= $escaper->escapeHtml($lang['FailedTasksDays']) ?></label>
                                        <input type="number" class="form-control" id="qm-retention-failed" name="failed_days" min="0" max="<?= (int)QUEUE_RETENTION_MAX_DAYS ?>" step="1" inputmode="numeric" aria-describedby="qm-retention-zero qm-retention-failed-error" disabled>
                                        <div class="invalid-feedback" id="qm-retention-failed-error"></div>
                                    </div>
                                </div>
                                <p class="sr-qhint sr-qm-retention-zero" id="qm-retention-zero"><?= $escaper->escapeHtml($lang['ZeroKeepsTasksForever']) ?></p>
                                <div class="alert alert-danger d-none mb-0 sr-qm-retention-error" id="qm-retention-error" role="alert"></div>
                            </div>
                        </section>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                        <button type="submit" class="btn btn-submit" id="qm-retention-save" disabled><?= $escaper->escapeHtml($lang['Save']) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
