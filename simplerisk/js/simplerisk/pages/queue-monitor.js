/**
 * Queue Monitor page controller (SR-2408).
 *
 * Fills the static .sr-table-card shell emitted by admin/queue_monitor.php from
 * the v2 API and keeps it live:
 *  - GET /api/v2/admin/queue          paged, filtered, sorted list + pill counts
 *  - GET /api/v2/admin/queue/summary  worker health strip
 *  - GET /api/v2/admin/queue/steps    a task's steps, loaded when its row opens
 *  - GET /api/v2/admin/queue/payload  the redacted payload, for the modal
 *  - POST /api/v2/admin/queue/cancel | restart | run-now   row + bulk actions
 *  - POST /api/v2/admin/queue/delete                       bulk delete of finished tasks
 *  - GET/POST /api/v2/admin/queue/settings                 retention modal
 *
 * The list and summary are polled every 5 s while the tab is visible. A failed
 * poll doubles the interval (up to 60 s); a good one resets it. Open expanders,
 * the row selection and the scroll position survive every refresh.
 *
 * ESCAPING: every string from the API (task_type, error, stage, payload, ...)
 * is raw. Each one reaches the DOM through jQuery's `text:` / .text() only, so
 * the browser escapes it exactly once; nothing is concatenated into an HTML
 * string. The _lang values from header.php are raw text too and go through
 * the same .text() sink, so they are never pre-escaped here.
 *
 * Toasts: toastr renders its message as HTML, so a toast is built only from
 * _lang text and integers, escaped exactly once by escapeHtml() right before
 * showAlertFromMessage() (toastr decodes it once). No API string (task_type,
 * status_message, ...) is ever put in a toast.
 */
(function (window, $) {
    'use strict';

    /* ------------------------------------------------------------------ *
     * Pure helpers (no DOM). Exposed on window.SRQueueMonitor so the      *
     * Playwright spec can call them through page.evaluate().             *
     * ------------------------------------------------------------------ */

    var PRIORITY_TIERS = ['immediate', 'high', 'medium', 'low', 'lowest'];

    function lang(key) {
        return (typeof window.L === 'function') ? window.L(key) : key;
    }

    /** Substitutes {$name} placeholders (the lang.en.php template style). */
    function formatTemplate(template, vars) {
        return String(template).replace(/\{\$(\w+)\}/g, function (match, name) {
            return Object.prototype.hasOwnProperty.call(vars, name) ? String(vars[name]) : match;
        });
    }

    /**
     * Seconds as a short duration: "45s", "4m 12s", "2h 5m", "3d 4h". The
     * second unit is dropped when it is zero ("4m", not "4m 0s"). Returns null
     * for a missing or non-numeric value so the caller can show a dash.
     */
    function formatDuration(seconds) {
        if (seconds === null || seconds === undefined || seconds === '' || !isFinite(Number(seconds))) {
            return null;
        }
        var s = Math.max(0, Math.floor(Number(seconds)));
        var days = Math.floor(s / 86400);
        var hours = Math.floor((s % 86400) / 3600);
        var minutes = Math.floor((s % 3600) / 60);
        var secs = s % 60;
        var parts;

        if (days > 0) {
            parts = [[days, 'XDaysShort'], [hours, 'XHoursShort']];
        } else if (hours > 0) {
            parts = [[hours, 'XHoursShort'], [minutes, 'XMinutesShort']];
        } else if (minutes > 0) {
            parts = [[minutes, 'XMinutesShort'], [secs, 'XSecondsShort']];
        } else {
            parts = [[secs, 'XSecondsShort']];
        }

        return parts
            .filter(function (part, index) { return index === 0 || part[0] > 0; })
            .map(function (part) { return formatTemplate(lang(part[1]), { n: part[0] }); })
            .join(' ');
    }

    /**
     * Seconds left before a retry, given the API's retry_in (seconds, as of the
     * response) and how long ago that response arrived. Never negative.
     */
    function retryCountdown(retryIn, fetchedAtMs, nowMs) {
        var base = Number(retryIn);
        if (!isFinite(base)) {
            return 0;
        }
        var elapsed = Math.max(0, (Number(nowMs) - Number(fetchedAtMs)) / 1000);
        return Math.max(0, Math.ceil(base - elapsed));
    }

    /** The chip class for a priority tier; unknown tiers fall back to lowest. */
    function priorityTierClass(tier) {
        return 'sr-qm-prio--' + (PRIORITY_TIERS.indexOf(tier) !== -1 ? tier : 'lowest');
    }

    /**
     * A server "YYYY-MM-DD HH:MM:SS" stamp as epoch ms, read as UTC. Only ever
     * used for differences between two server stamps, so the zone cancels out
     * (and Safari's Date parser, which rejects the space form, is not needed).
     */
    function parseServerTime(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/.exec(String(value || ''));
        if (!m) {
            return null;
        }
        return Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6]);
    }

    /**
     * The row actions a task offers. The list's cancelable / restartable flags
     * describe the JOB TYPE only (a failed row still says cancelable: true),
     * so they are combined with the task's own status here:
     *  - Stop:            running (in_progress, incl. a retrying step), not already stopping
     *  - Cancel, Run now: pending (incl. one waiting on a retry); never running
     *  - Restart:         failed or canceled, not while a canceled task is still
     *                     stopping, and not once it was restarted (restarted_as set)
     *  - Delete:          completed, failed or canceled, not while stopping, and
     *                     only for a job type whose history may go (bulk only; the
     *                     server also skips a task with a step still running)
     */
    function availableActions(row) {
        var status = row ? row.status : '';
        var key = row && row.display ? row.display.key : '';
        var cancelable = !!(row && row.cancelable);
        return {
            stop: status === 'in_progress' && key !== 'stopping' && cancelable,
            cancel: status === 'pending' && cancelable,
            run_now: status === 'pending' && cancelable,
            restart: (status === 'failed' || status === 'canceled') && key !== 'stopping' && !!(row && row.restartable)
                && (row.restarted_as === null || row.restarted_as === undefined),
            delete: (status === 'completed' || status === 'failed' || status === 'canceled') && key !== 'stopping'
                && !!(row && row.deletable),
        };
    }

    /**
     * The bulk actions a status filter offers, in bar order: Cancel selected
     * for the pending/running/retrying views, Restart selected and Delete
     * selected for failed/canceled, Delete selected for completed, none for All
     * (a mixed view).
     */
    function bulkActionsForStatus(status) {
        if (status === 'pending' || status === 'running' || status === 'retrying') {
            return ['cancel'];
        }
        if (status === 'failed' || status === 'canceled') {
            return ['restart', 'delete'];
        }
        if (status === 'completed') {
            return ['delete'];
        }
        return [];
    }

    // API skip reason -> lang template taking {$count}. An unknown code reads
    // as an unexpected error rather than being dropped from the summary.
    var SKIP_REASONS = {
        wrong_state: 'XSkippedTaskStateChanged',
        not_found: 'XSkippedTaskNoLongerExists',
        not_actionable: 'XSkippedTaskTypeCannotBeCanceled',
        not_restartable: 'XSkippedTaskTypeCannotBeRestarted',
        already_restarted: 'XSkippedTaskAlreadyRestarted',
        stopping: 'XSkippedTaskStillStopping',
        steps_running: 'XSkippedTaskStepStillRunning',
        error: 'XSkippedUnexpectedError',
    };

    // Where one code means something else for a given action.
    var SKIP_REASONS_BY_ACTION = {
        delete: { not_actionable: 'XSkippedTaskTypeCannotBeDeleted' },
    };

    /** Skipped results grouped by reason, first-seen order: [[langKey, count], ...]. */
    function groupSkipped(results, action) {
        var order = [];
        var counts = {};
        var overrides = Object.prototype.hasOwnProperty.call(SKIP_REASONS_BY_ACTION, action) ? SKIP_REASONS_BY_ACTION[action] : {};
        (Array.isArray(results) ? results : []).forEach(function (result) {
            if (!result || result.result === 'applied') {
                return;
            }
            var key;
            if (Object.prototype.hasOwnProperty.call(overrides, result.reason)) {
                key = overrides[result.reason];
            } else {
                key = Object.prototype.hasOwnProperty.call(SKIP_REASONS, result.reason) ? SKIP_REASONS[result.reason] : 'XSkippedUnexpectedError';
            }
            if (!counts[key]) {
                counts[key] = 0;
                order.push(key);
            }
            counts[key]++;
        });
        return order.map(function (key) { return [key, counts[key]]; });
    }

    /** Escapes text once for an HTML sink (the toastr message). */
    function escapeHtml(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    window.SRQueueMonitor = {
        formatDuration: formatDuration,
        retryCountdown: retryCountdown,
        priorityTierClass: priorityTierClass,
        formatTemplate: formatTemplate,
        parseServerTime: parseServerTime,
        availableActions: availableActions,
        bulkActionsForStatus: bulkActionsForStatus,
        groupSkipped: groupSkipped,
        escapeHtml: escapeHtml,
    };

    var $root = $('#queue-monitor');
    if (!$root.length) {
        return;
    }

    /* ------------------------------------------------------------------ *
     * State                                                              *
     * ------------------------------------------------------------------ */

    var POLL_MS = 5000;
    var POLL_MAX_MS = 60000;
    // A hung request counts as a failure instead of leaving its in-flight flag set.
    var REQUEST_TIMEOUT_MS = 15000;
    // A bulk action can lock and update up to 500 rows one by one; give it far
    // longer than a read before treating it as lost.
    var ACTION_TIMEOUT_MS = 120000;
    var PAGE_LENGTH = 25;
    var COLUMN_COUNT = 11;
    var STATUS_FILTERS = ['pending', 'running', 'retrying', 'completed', 'failed', 'canceled'];
    var DESC_FIRST = ['id', 'priority', 'attempts', 'created_at', 'updated_at'];
    // The API's per-request cap (QUEUE_BULK_MAX) and the retention ceiling,
    // rendered by the page so the client never disagrees with the server.
    var BULK_MAX = parseInt($root.attr('data-bulk-max'), 10) || 500;
    var RETENTION_MAX = parseInt($root.attr('data-retention-max'), 10) || 3650;

    // Row action buttons, built once per row and shown/hidden per state.
    var ROW_ACTIONS = [
        { name: 'stop', label: 'Stop', icon: 'fa-circle-stop', danger: true },
        { name: 'cancel', label: 'Cancel', icon: 'fa-ban', danger: true },
        { name: 'run_now', label: 'RunNow', icon: 'fa-forward', danger: false },
        { name: 'restart', label: 'Restart', icon: 'fa-rotate-right', danger: false },
    ];
    var ACTION_URLS = {
        stop: '/api/v2/admin/queue/cancel',
        cancel: '/api/v2/admin/queue/cancel',
        run_now: '/api/v2/admin/queue/run-now',
        restart: '/api/v2/admin/queue/restart',
        delete: '/api/v2/admin/queue/delete', // bulk only: no row button opens it
    };
    var TERMINAL = ['completed', 'fulfilled', 'failed', 'canceled'];

    // display.key -> [lang key, .sr-state-* family] (design-system.md §7).
    var STATUS_CHIPS = {
        pending: ['Pending', 'warning'],
        running: ['UpgradeStateRunning', 'info'],
        retrying: ['Retrying', 'warning'],
        stopping: ['Stopping', 'neutral'],
        completed: ['Completed', 'success'],
        failed: ['Failed', 'danger'],
        canceled: ['Canceled', 'neutral'],
    };

    var PRIORITY_LABELS = {
        immediate: 'Immediate',
        high: 'AssetScoringLevelHigh',
        medium: 'Medium',
        low: 'AssetScoringLevelLow',
        lowest: 'Lowest',
    };

    var state = {
        // True from a filter change until the list for the new filter arrives: the rows on
        // screen are the OLD filter's, so they must not be selectable meanwhile.
        awaitingFilter: false,
        q: '',
        status: '',
        taskType: '',
        dateFrom: '',
        dateTo: '',
        start: 0,
        orderCol: 'id',
        orderDir: 'desc',

        rows: [],
        filtered: 0,
        total: 0,
        counts: {},
        listFetchedAt: 0,
        loadedOnce: false,

        summary: null,
        serverSkewMs: 0,

        rowNodes: new Map(),   // task id -> { tr, cells, sigs } (patched in place)
        expanded: new Map(),   // task id -> { $row, signature, loading, row }
        selected: new Set(),
        allFiltered: false,    // escalated to "all N matching the filter"
        maxId: 0,              // newest task id when the list was read (filter.max_id snapshot)
        actionableTotal: null, // failed/canceled views: tasks an all_filtered restart would target
        deletableTotal: null,  // completed/failed/canceled views: tasks an all_filtered delete would target
        pageSig: null,         // which page the last list response was for
        actionBusy: false,     // an action POST is in flight

        interval: POLL_MS,
        timer: null,
        seq: 0,
        inFlight: false,
        failing: false,
        lastOkAt: 0,
        nextPollAt: 0,
        authLost: false,
        tickTimer: null,
        deferredPolls: 0,      // consecutive list polls with a text selection held on the page
        pagerSig: null,

        summarySeq: 0,
        summaryInFlight: false,
        summaryFailing: false,
        healthSig: null,
        workersAlive: null,    // last announced { name: bool }, null before the first summary
    };

    /* ------------------------------------------------------------------ *
     * Small DOM builders                                                 *
     * ------------------------------------------------------------------ */

    function dash() {
        return $('<span>', { 'class': 'sr-cell-dash', text: '—' });
    }

    /**
     * True when the user has a non-empty text selection touching `node`. A
     * poll never rewrites such a node (that would collapse the selection an
     * admin is copying an error message from); the update waits for a later
     * poll instead.
     */
    function hasSelectionIn(node) {
        var selection = window.getSelection ? window.getSelection() : null;
        if (!node || !selection || selection.isCollapsed || !selection.rangeCount || !selection.toString()) {
            return false;
        }
        for (var i = 0; i < selection.rangeCount; i++) {
            var range = selection.getRangeAt(i);
            if (node.contains(range.commonAncestorContainer) || range.intersectsNode(node)) {
                return true;
            }
        }
        return false;
    }

    // A held selection defers updates for at most this many consecutive list
    // polls (~15 s at the 5 s cadence); the next poll applies them anyway, so
    // a forgotten Ctrl+A cannot freeze the page.
    var SELECTION_DEFER_POLLS = 3;

    /** True when `node` holds a selection AND the deferral cap has not tripped. */
    function selectionBlocks(node) {
        return state.deferredPolls <= SELECTION_DEFER_POLLS && hasSelectionIn(node);
    }

    /** Counts consecutive polls that arrive while a selection is held on the page. */
    function noteSelectionForPoll() {
        if (!hasSelectionIn($root[0])) {
            state.deferredPolls = 0;
        } else if (state.deferredPolls > SELECTION_DEFER_POLLS) {
            state.deferredPolls = 1; // the cap tripped last poll; start a new window
        } else {
            state.deferredPolls++;
        }
    }

    var pendingAnnouncements = [];
    var announceTimer = null;

    /**
     * Polite screen-reader announcement of a state change (never a tick).
     * Messages raised in the same tick are joined into one announcement (each
     * is a complete localized sentence, joined with a space) so the second
     * does not overwrite the first.
     */
    function announce(text) {
        if (!text) {
            return;
        }
        pendingAnnouncements.push(text);
        if (announceTimer) {
            return;
        }
        var region = document.getElementById('qm-announcer');
        if (region) {
            // Clear first so repeating the same message is announced again.
            region.textContent = '';
        }
        announceTimer = setTimeout(function () {
            announceTimer = null;
            var message = pendingAnnouncements.join(' ');
            pendingAnnouncements = [];
            var target = document.getElementById('qm-announcer');
            if (target) {
                target.textContent = message;
            }
        }, 50);
    }

    function statePill(labelKey, family, extraClass) {
        return $('<span>', {
            'class': 'sr-state-pill sr-state-' + family + (extraClass ? ' ' + extraClass : ''),
            text: lang(labelKey),
        });
    }

    function retryChipText(attempt, remaining) {
        if (remaining > 0) {
            return formatTemplate(lang('RetryingAttemptXInY'), { attempt: attempt, time: formatDuration(remaining) });
        }
        return formatTemplate(lang('RetryingAttemptX'), { attempt: attempt });
    }

    function statusChip(row) {
        var display = row.display || {};
        var key = STATUS_CHIPS[display.key] ? display.key : 'pending';
        var $pill = statePill(STATUS_CHIPS[key][0], STATUS_CHIPS[key][1], 'sr-qm-status');

        // A pending task waiting on a retry carries its attempt and countdown;
        // a running task with a retrying step has neither, so it just reads
        // "Retrying". The countdown text is refreshed by the 1 s ticker.
        if (key === 'retrying' && display.retry_attempt) {
            $pill.addClass('sr-qm-retry-chip')
                .attr('data-retry-attempt', display.retry_attempt)
                .attr('data-retry-in', display.retry_in === null ? '' : display.retry_in)
                .text(retryChipText(display.retry_attempt, retryCountdown(display.retry_in, state.listFetchedAt, Date.now())));
        }
        return $pill;
    }

    function priorityChip(row) {
        var tier = PRIORITY_TIERS.indexOf(row.priority_tier) !== -1 ? row.priority_tier : 'lowest';
        return $('<span>', {
            'class': 'sr-chip sr-qm-prio ' + priorityTierClass(tier),
            text: lang(PRIORITY_LABELS[tier]),
            title: lang('Priority') + ': ' + row.priority,
        });
    }

    function isTerminalRow(row) {
        var key = row.display && row.display.key;
        return key === 'completed' || key === 'failed' || key === 'canceled';
    }

    function rowDuration(row) {
        if (!isTerminalRow(row)) {
            return null;
        }
        var created = parseServerTime(row.created_at);
        var updated = parseServerTime(row.updated_at);
        if (created === null || updated === null) {
            return null;
        }
        return Math.max(0, Math.round((updated - created) / 1000));
    }

    /* ------------------------------------------------------------------ *
     * Data                                                               *
     * ------------------------------------------------------------------ */

    function listParams() {
        var params = {
            start: state.start,
            length: PAGE_LENGTH,
            order_col: state.orderCol,
            order_dir: state.orderDir,
        };
        if (state.q) { params.q = state.q; }
        if (state.status) { params.status = state.status; }
        if (state.taskType) { params.task_type = state.taskType; }
        if (state.dateFrom) { params.date_from = state.dateFrom; }
        if (state.dateTo) { params.date_to = state.dateTo; }
        return params;
    }

    function filtersActive() {
        return !!(state.q || state.status || state.taskType || state.dateFrom || state.dateTo);
    }

    function schedule() {
        clearTimeout(state.timer);
        if (document.hidden) {
            return; // resumed by the visibilitychange handler
        }
        state.nextPollAt = Date.now() + state.interval;
        state.timer = setTimeout(function () { refresh(false); }, state.interval);
    }

    function isAuthFailure(xhr) {
        return !!xhr && (xhr.status === 401 || xhr.status === 403);
    }

    /**
     * The session ended or the admin right was removed: polling cannot
     * recover on its own, so it stops and the page says how to get back in,
     * instead of backing off against a 401 forever.
     */
    function stopForAuth() {
        if (state.authLost) {
            return;
        }
        state.authLost = true;
        clearTimeout(state.timer);
        clearInterval(state.tickTimer);
        state.tickTimer = null;
        $('#qm-auth-notice').removeClass('d-none');
        announce(lang('SessionEndedRefreshToSignIn'));
        updateLiveText();
    }

    /**
     * One poll: the list and the summary are fetched side by side but settle
     * independently, so a summary failure never marks the list stale or slows
     * it down. A user-driven refresh (filter, sort, page) always runs and
     * supersedes a list request in flight; a timer refresh is skipped while
     * one is in flight. Sequence numbers drop responses a newer request has
     * overtaken.
     */
    function refresh(userDriven) {
        if (state.authLost) {
            return;
        }
        if (!userDriven && (state.inFlight || document.hidden)) {
            schedule();
            return;
        }
        clearTimeout(state.timer);
        refreshList();
        refreshSummary();
    }

    function refreshList() {
        var seq = ++state.seq;
        state.inFlight = true;

        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/admin/queue',
            data: listParams(),
            dataType: 'json',
            timeout: REQUEST_TIMEOUT_MS,
        }).done(function (response) {
            if (seq !== state.seq || state.authLost) {
                return;
            }
            var list = (response && response.data) ? response.data : {};
            var wasFailing = state.failing;
            if (state.awaitingFilter) {
                // Anything selected while the old rows were still shown must not carry over.
                state.selected.clear();
                state.allFiltered = false;
                state.awaitingFilter = false;
            }

            var previousRows = state.rows;
            var previousSig = state.pageSig;
            state.rows = Array.isArray(list.rows) ? list.rows : [];
            state.filtered = Number(list.filtered) || 0;
            state.maxId = Number(list.max_id) || 0;
            state.actionableTotal = (typeof list.actionable_total === 'number') ? list.actionable_total : null;
            state.deletableTotal = (typeof list.deletable_total === 'number') ? list.deletable_total : null;
            state.pageSig = JSON.stringify(listParams());
            if (previousSig === state.pageSig) {
                pruneVanishedSelection(previousRows);
            }
            state.total = Number(list.total) || 0;
            state.counts = list.status_counts || {};
            state.listFetchedAt = Date.now();
            state.loadedOnce = true;
            state.failing = false;
            state.interval = POLL_MS;
            state.lastOkAt = Date.now();
            if (wasFailing) {
                announce(lang('LiveUpdatesResumed'));
            }

            // A page past the end (rows were pruned or finished out of the
            // filter) steps back to the last page that exists. Only ever
            // backwards, so this can never become a zero-delay request loop.
            if (!state.rows.length && state.filtered > 0) {
                var lastStart = Math.max(0, (Math.ceil(state.filtered / PAGE_LENGTH) - 1) * PAGE_LENGTH);
                if (lastStart < state.start) {
                    state.start = lastStart;
                    state.inFlight = false;
                    refreshList();
                    return;
                }
            }

            render();
        }).fail(function (xhr) {
            if (seq !== state.seq) {
                return;
            }
            if (isAuthFailure(xhr)) {
                stopForAuth();
                return;
            }
            if (!state.failing) {
                announce(lang('LiveUpdatesFailed'));
            }
            state.failing = true;
            state.interval = Math.min(POLL_MAX_MS, state.interval * 2);
            if (!state.loadedOnce) {
                renderEmpty('error');
            }
        }).always(function () {
            if (seq !== state.seq) {
                return;
            }
            state.inFlight = false;
            if (!state.authLost) {
                schedule();
            }
            updateLiveText();
        });
    }

    function refreshSummary() {
        if (state.summaryInFlight) {
            return;
        }
        var seq = ++state.summarySeq;
        state.summaryInFlight = true;

        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/admin/queue/summary',
            dataType: 'json',
            timeout: REQUEST_TIMEOUT_MS,
        }).done(function (response, textStatus, xhr) {
            // After the session is gone a late response must not repaint or
            // announce anything.
            if (seq !== state.summarySeq || state.authLost) {
                return;
            }
            state.summary = (response && response.data) ? response.data : null;
            state.serverSkewMs = serverSkew(xhr);
            state.summaryFailing = false;
            renderHealth();
        }).fail(function (xhr) {
            if (seq !== state.summarySeq) {
                return;
            }
            if (isAuthFailure(xhr)) {
                stopForAuth();
                return;
            }
            // The health strip shows its own "unavailable" state; the list
            // keeps polling at its normal pace.
            state.summaryFailing = true;
            renderHealth();
        }).always(function () {
            if (seq === state.summarySeq) {
                state.summaryInFlight = false;
            }
        });
    }

    /** Server clock minus client clock, from the response Date header. */
    function serverSkew(xhr) {
        var header = xhr && xhr.getResponseHeader ? xhr.getResponseHeader('Date') : null;
        var serverMs = header ? Date.parse(header) : NaN;
        return isFinite(serverMs) ? serverMs - Date.now() : 0;
    }

    /* ------------------------------------------------------------------ *
     * Rendering                                                          *
     * ------------------------------------------------------------------ */

    function render() {
        noteSelectionForPoll();
        renderCounts();
        renderSortHeaders();

        var empty = state.filtered === 0;
        $('#qm-table-scroll').toggleClass('d-none', empty);
        $root.find('.sr-table-foot').toggleClass('d-none', empty);
        if (empty) {
            patchRows([]);
            renderEmpty(filtersActive() ? 'no-results' : 'no-data');
        } else {
            $('#qm-empty').addClass('d-none');
            patchRows(state.rows);
            renderFooter();
        }
        updateLiveText();
    }

    function renderCounts() {
        $('#qm-count').text(state.filtered).toggleClass('d-none', !state.loadedOnce);
        $('#qm-status-filter [data-count-for]').each(function () {
            var key = $(this).attr('data-count-for');
            var value = state.counts[key];
            var text = typeof value === 'number' ? String(value) : '';
            if (this.textContent !== text) {
                this.textContent = text;
            }
        });
    }

    function renderEmpty(intent) {
        var $empty = $('#qm-empty').removeClass('d-none sr-table-empty-danger');
        var $icon = $('#qm-empty-icon').attr('class', 'fa');
        var $actionWrap = $('#qm-empty-action-wrap').addClass('d-none');
        var $action = $('#qm-empty-action').off('click');

        $('#qm-table-scroll').addClass('d-none');
        $root.find('.sr-table-foot').addClass('d-none');

        if (intent === 'error') {
            $empty.addClass('sr-table-empty-danger');
            $icon.addClass('fa-triangle-exclamation');
            $('#qm-empty-title').text(lang('CouldNotLoadTheQueue'));
            $('#qm-empty-body').text(lang('AdvancedSearchFailedBody'));
            $action.text(lang('Retry')).on('click', function () { refresh(true); });
            $actionWrap.removeClass('d-none');
        } else if (intent === 'no-results') {
            $icon.addClass('fa-filter');
            $('#qm-empty-title').text(lang('NoTasksMatchYourFilters'));
            $('#qm-empty-body').text('');
            $action.text(lang('ClearFilters')).on('click', clearFilters);
            $actionWrap.removeClass('d-none');
        } else {
            $icon.addClass('fa-inbox');
            $('#qm-empty-title').text(lang('NoTasksInTheQueue'));
            $('#qm-empty-body').text(lang('BackgroundTasksAppearHere'));
        }
    }

    /* --- Rows: patched in place ----------------------------------------- *
     * Each task keeps one <tr> for as long as it stays on the page. A poll  *
     * compares per-cell signatures and rewrites only the cells that         *
     * changed, inserts new rows, removes vanished ones and moves rows only  *
     * when the order changed. The checkbox, caret and actions cells are     *
     * built once and never replaced, so focus, hover and clicks survive,    *
     * and an open expander stays attached under its row.                    */

    function progressText(progress) {
        if (Number(progress.total) === 1) {
            return formatTemplate(lang('XOfOneStep'), { done: progress.done });
        }
        return formatTemplate(lang('XOfYSteps'), { done: progress.done, total: progress.total });
    }

    // The data cells, in column order after checkbox + caret + ID. Each has a
    // signature of exactly what it renders and a renderer that fills its <td>.
    var CELLS = [
        {
            name: 'task',
            cls: 'sr-qm-task',
            sig: function (row) { return String(row.task_type); },
            fill: function (td, row) { $(td).text(row.task_type); },
        },
        {
            name: 'status',
            cls: 'sr-qm-status-cell',
            label: null,
            sig: function (row) {
                var d = row.display || {};
                return [d.key, d.retry_attempt, row.restarted_as].join('|');
            },
            fill: function (td, row) {
                $(td).empty().append(statusChip(row));
                if (row.restarted_as !== null && row.restarted_as !== undefined) {
                    $(td).append($('<span>', {
                        'class': 'sr-qm-restarted',
                        text: formatTemplate(lang('RestartedAsTaskX'), { id: Number(row.restarted_as) }),
                    }));
                }
            },
        },
        {
            name: 'progress',
            cls: 'sr-qm-progress',
            label: 'Progress',
            sig: function (row) {
                var p = row.progress || {};
                return [p.done, p.total].join('|');
            },
            fill: function (td, row) {
                var p = row.progress || {};
                if (p.total > 0) {
                    $(td).text(progressText(p));
                } else {
                    $(td).empty().append(dash());
                }
            },
        },
        {
            name: 'attempts',
            cls: 'sr-num sr-qm-attempts-cell',
            label: 'attempts',
            sig: function (row) { return String(row.attempts); },
            fill: function (td, row) { $(td).text(row.attempts); },
        },
        {
            name: 'priority',
            cls: 'sr-qm-prio-cell',
            label: null,
            sig: function (row) { return [row.priority_tier, row.priority].join('|'); },
            fill: function (td, row) { $(td).empty().append(priorityChip(row)); },
        },
        {
            name: 'queued',
            cls: 'sr-qm-time',
            label: 'DiscoveryStatusQueued',
            sig: function (row) { return String(row.created_at); },
            fill: function (td, row) { $(td).text(row.created_at); },
        },
        {
            name: 'duration',
            cls: 'sr-num sr-qm-duration-cell',
            label: 'Duration',
            sig: function (row) { return String(formatDuration(rowDuration(row))); },
            fill: function (td, row) {
                var duration = formatDuration(rowDuration(row));
                if (duration === null) {
                    $(td).empty().append(dash());
                } else {
                    $(td).text(duration);
                }
            },
        },
    ];

    function createRowNode(row) {
        var id = row.id;
        var tr = $('<tr>', { 'class': 'sr-qm-row', 'data-task-id': id })[0];
        var entry = { tr: tr, cells: {}, sigs: {} };

        $(tr).append(
            $('<td>', { 'class': 'sr-check-col' }).append(
                $('<input>', {
                    type: 'checkbox',
                    'class': 'form-check-input',
                    'data-select-id': id,
                    'aria-label': lang('Select') + ' #' + id,
                })
            ),
            $('<td>', { 'class': 'sr-caret-col' }).append(
                $('<button>', {
                    type: 'button',
                    'class': 'sr-qm-caret',
                    'data-expand-id': id,
                    'aria-expanded': 'false',
                    // aria-controls is set only while the expander row exists.
                    'aria-label': lang('Expand') + ' #' + id,
                }).append($('<i>', { 'class': 'fa fa-chevron-right', 'aria-hidden': 'true' }))
            ),
            $('<td>', { 'class': 'sr-qm-id-cell', 'data-label': lang('ID'), text: id })
        );
        CELLS.forEach(function (cell) {
            var td = $('<td>', { 'class': cell.cls })[0];
            // Read by the narrow-width stacked block (_queue-monitor.scss), where
            // the column header is gone and a bare "3" would not say what it counts.
            if (cell.label) {
                td.setAttribute('data-label', lang(cell.label));
            }
            entry.cells[cell.name] = td;
            tr.appendChild(td);
        });
        // Every action button exists from the start; patchRowNode() shows the
        // ones the task's state allows. The wrap + toggle is the shared
        // overflow menu _tables.scss turns the cluster into at narrow widths
        // and on touch (sr-row-actions-menu.js).
        var $menu = $('<span>', { 'class': 'sr-row-actions' });
        ROW_ACTIONS.forEach(function (action) {
            $menu.append(
                $('<button>', {
                    type: 'button',
                    'class': 'sr-row-action d-none' + (action.danger ? ' sr-row-action-danger' : ''),
                    'data-row-action': action.name,
                    'data-task-id': id,
                    // The menu shows the title as the item's label, so it is the bare verb.
                    title: lang(action.label),
                    'aria-label': lang(action.label) + ' #' + id,
                }).append($('<i>', { 'class': 'fa ' + action.icon, 'aria-hidden': 'true' }))
            );
        });
        var actionsCell = $('<td>', { 'class': 'sr-actions-col' }).append(
            $('<span>', { 'class': 'sr-row-actions-wrap d-none' }).append(
                $('<button>', {
                    type: 'button',
                    'class': 'sr-row-actions-toggle',
                    'aria-expanded': 'false',
                    'aria-haspopup': 'true',
                    'aria-label': lang('Actions') + ' #' + id,
                }).append($('<i>', { 'class': 'fa fa-ellipsis', 'aria-hidden': 'true' })),
                $menu
            )
        )[0];
        entry.actionsCell = actionsCell;
        tr.appendChild(actionsCell);
        return entry;
    }

    /** True when the element takes up space (not display:none, nor inside it). */
    function isShown(el) {
        return !!el && el.isConnected && el.getClientRects().length > 0;
    }

    /**
     * Shows the action buttons the state allows. A button that held focus and
     * is now hidden hands focus to the row's checkbox instead of <body>.
     */
    function syncRowActions(entry, actions) {
        var active = document.activeElement;
        var any = false;
        $(entry.actionsCell).find('[data-row-action]').each(function () {
            var on = !!actions[this.getAttribute('data-row-action')];
            any = any || on;
            $(this).toggleClass('d-none', !on);
        });
        var $wrap = $(entry.actionsCell).find('.sr-row-actions-wrap');
        var focusLost = active && entry.actionsCell.contains(active) && !isShown(active);
        // An open overflow menu closes when it empties or its focused item goes.
        if ((!any || focusLost) && $wrap.hasClass('is-open') && window.SRRowActionsMenu) {
            window.SRRowActionsMenu.close($(entry.actionsCell));
        }
        $wrap.toggleClass('d-none', !any);
        if (focusLost) {
            var checkbox = entry.tr.querySelector('[data-select-id]');
            if (checkbox) {
                checkbox.focus({ preventScroll: true });
            }
        }
    }

    function isSelected(id) {
        return state.allFiltered || state.selected.has(id);
    }

    /** Puts a row's checkbox and selected tint in line with the selection. */
    function syncRowSelection(entry, id) {
        var selected = isSelected(id);
        var checkbox = entry.tr.querySelector('[data-select-id]');
        if (checkbox.checked !== selected) {
            checkbox.checked = selected;
        }
        $(entry.tr).toggleClass('is-selected', selected);
    }

    /** Rewrites the cells whose signature changed; false when deferred. */
    function patchRowNode(entry, row) {
        if (selectionBlocks(entry.tr)) {
            return false; // the user is selecting text here; try next poll
        }
        CELLS.forEach(function (cell) {
            var sig = cell.sig(row);
            if (entry.sigs[cell.name] !== sig) {
                cell.fill(entry.cells[cell.name], row);
                entry.sigs[cell.name] = sig;
            }
        });

        // The countdown base moves every poll; the chip node stays and the
        // 1 s ticker rewrites its text.
        var chip = entry.cells.status.querySelector('.sr-qm-retry-chip');
        if (chip) {
            var retryIn = row.display && row.display.retry_in !== null && row.display.retry_in !== undefined ? String(row.display.retry_in) : '';
            if (chip.getAttribute('data-retry-in') !== retryIn) {
                chip.setAttribute('data-retry-in', retryIn);
            }
        }

        // Actions follow the task's state; the signature covers every input.
        var actions = availableActions(row);
        var actionSig = [row.status, row.display && row.display.key, !!row.cancelable, !!row.restartable, row.restarted_as].join('|');
        if (entry.sigs.actions !== actionSig) {
            syncRowActions(entry, actions);
            entry.sigs.actions = actionSig;
        }

        syncRowSelection(entry, row.id);
        var expanded = state.expanded.has(row.id);
        $(entry.tr).toggleClass('is-expanded', expanded);
        var caret = entry.tr.querySelector('.sr-qm-caret');
        syncCaret(caret, row.id, expanded);
        return true;
    }

    /** aria-expanded always; aria-controls only while the expander row exists. */
    function syncCaret(caret, id, expanded) {
        if (!caret) {
            return;
        }
        var ariaExpanded = expanded ? 'true' : 'false';
        if (caret.getAttribute('aria-expanded') !== ariaExpanded) {
            caret.setAttribute('aria-expanded', ariaExpanded);
        }
        if (expanded) {
            if (caret.getAttribute('aria-controls') !== 'qm-expand-' + id) {
                caret.setAttribute('aria-controls', 'qm-expand-' + id);
            }
        } else if (caret.hasAttribute('aria-controls')) {
            caret.removeAttribute('aria-controls');
        }
    }

    function removeRowNode(id) {
        var entry = state.rowNodes.get(id);
        if (entry) {
            entry.tr.remove();
            state.rowNodes.delete(id);
        }
        var expander = state.expanded.get(id);
        if (expander) {
            expander.$row.remove();
            state.expanded.delete(id);
        }
    }

    function patchRows(rows) {
        var tbody = document.getElementById('qm-tbody');
        var keep = new Set(rows.map(function (row) { return row.id; }));
        var focusedBefore = document.activeElement;
        var focusInBody = !!focusedBefore && tbody.contains(focusedBefore);

        // Vanished tasks (and their expanders) go.
        Array.from(state.rowNodes.keys()).forEach(function (id) {
            if (!keep.has(id)) {
                removeRowNode(id);
            }
        });
        state.expanded.forEach(function (entry, id) {
            if (!keep.has(id)) {
                entry.$row.remove();
                state.expanded.delete(id);
            }
        });

        // Create and patch; collect each task's node group (row + expander).
        var groups = [];
        var pinned = [];
        var active = document.activeElement;
        rows.forEach(function (row, index) {
            var entry = state.rowNodes.get(row.id);
            if (!entry) {
                entry = createRowNode(row);
                state.rowNodes.set(row.id, entry);
            }
            var applied = patchRowNode(entry, row);
            var nodes = [entry.tr];
            var expander = state.expanded.get(row.id);
            if (expander) {
                nodes.push(expander.$row[0]);
                expander.row = row;
                syncExpander(expander, row);
            }
            groups.push(nodes);
            // A row whose update was deferred (text selected in it), or that
            // holds keyboard focus, stays where it is; the others move around it.
            var holdsFocus = active && active !== document.body && nodes.some(function (n) { return n.contains(active); });
            if (!applied || holdsFocus) {
                pinned.push(index);
            }
        });

        placeGroups(tbody, groups, pinned);

        // Moving a node can drop focus in some browsers; put it back.
        if (active && active.isConnected && document.activeElement !== active && tbody.contains(active)) {
            active.focus({ preventScroll: true });
        }
        // The focused row left the page (e.g. a canceled task dropped out of
        // the Pending view): focus the header checkbox rather than <body>.
        if (focusInBody && !focusedBefore.isConnected) {
            focusFallback();
        }

        syncSelectAll();
        renderBulkBar();
        // Last: the bulk bar above the table can appear or vanish in this pass too.
        reorientMovedMenus(tbody);
    }

    /**
     * An open overflow menu is pinned to the viewport (position: fixed) where
     * its row was when it opened. When this poll moved that row (rows placed
     * in a new order, or rows above it added or removed), re-place the menu at
     * the row's new position so it never sits over another task's row. It
     * stays open and keeps focus.
     */
    function reorientMovedMenus(tbody) {
        if (!window.SRRowActionsMenu) {
            return;
        }
        $(tbody).find('.sr-row-actions-wrap.is-open').each(function () {
            var was = $(this).data('srRowActionsAnchor');
            var now = this.getBoundingClientRect();
            if (!was || Math.abs(now.left - was.left) > 1 || Math.abs(now.top - was.top) > 1) {
                window.SRRowActionsMenu.orient($(this));
            }
        });
    }

    function focusFallback() {
        var target = document.getElementById('qm-select-all');
        if (!isShown(target)) {
            target = document.getElementById('qm-search');
        }
        if (target) {
            target.focus({ preventScroll: true });
        }
    }

    /**
     * Puts the row groups in order with as few moves as possible. With no
     * pinned group it walks the list and moves a node only when it is not
     * already in place. With one pinned group (deferred or focused), that
     * group is never moved: the groups before it are placed in front of it
     * and the ones after it behind it. With several pinned groups the order is
     * left alone this poll and only rows not yet in the table are appended.
     */
    function placeGroups(tbody, groups, pinned) {
        function first(group) { return group[0]; }
        function last(group) { return group[group.length - 1]; }
        function insertGroup(group, ref) {
            group.forEach(function (node) { tbody.insertBefore(node, ref); });
        }
        function inPlaceBefore(group, ref) {
            // Already contiguous and immediately followed by ref?
            for (var i = 1; i < group.length; i++) {
                if (group[i - 1].nextSibling !== group[i]) {
                    return false;
                }
            }
            return last(group).nextSibling === ref;
        }

        if (pinned.length > 1) {
            groups.forEach(function (group) {
                group.forEach(function (node) {
                    if (!node.isConnected) {
                        tbody.appendChild(node);
                    }
                });
            });
            return;
        }

        if (!pinned.length) {
            var ref = tbody.firstChild;
            groups.forEach(function (group) {
                group.forEach(function (node) {
                    if (ref !== node) {
                        tbody.insertBefore(node, ref);
                    }
                    ref = node.nextSibling;
                });
            });
            return;
        }

        var k = pinned[0];
        var anchor = groups[k];
        if (!first(anchor).isConnected) {
            insertGroup(anchor, null);
        }
        // Expander directly under its (pinned) row.
        if (anchor.length > 1 && first(anchor).nextSibling !== anchor[1]) {
            tbody.insertBefore(anchor[1], first(anchor).nextSibling);
        }
        // Before the anchor, back to front.
        var before = first(anchor);
        for (var i = k - 1; i >= 0; i--) {
            if (!inPlaceBefore(groups[i], before)) {
                insertGroup(groups[i], before);
            }
            before = first(groups[i]);
        }
        // After the anchor, front to back.
        var after = last(anchor).nextSibling;
        for (var j = k + 1; j < groups.length; j++) {
            if (first(groups[j]) !== after || !inPlaceBefore(groups[j], last(groups[j]).nextSibling)) {
                insertGroup(groups[j], after);
            }
            after = last(groups[j]).nextSibling;
        }
    }

    function renderSortHeaders() {
        $root.find('th.sr-sortable').each(function () {
            var $th = $(this);
            var active = $th.attr('data-order-col') === state.orderCol;
            var icon = 'fa-sort';
            if (active) {
                icon = state.orderDir === 'asc' ? 'fa-arrow-up-short-wide' : 'fa-arrow-down-wide-short';
            }
            $th.toggleClass('is-sorted', active)
                .attr('aria-sort', active ? (state.orderDir === 'asc' ? 'ascending' : 'descending') : 'none');
            $th.find('.sr-sort-icon').attr('class', 'fa sr-sort-icon ' + icon);
        });
    }

    // Hand-rolled pager, same shape as compliance-define-tests.js renderFooter():
    // always rendered (even one page), Previous / numbers / Next, no First/Last.
    function renderFooter() {
        var total = state.filtered;
        var $pager = $('#qm-pager');
        var start = Math.min(state.start, Math.max(0, total - 1));
        var end = Math.min(start + PAGE_LENGTH, total);
        var info = formatTemplate(lang('ShowingXToYOfZ'), { start: start + 1, end: end, total: total });
        var infoEl = document.getElementById('qm-info');
        if (infoEl.textContent !== info) {
            infoEl.textContent = info;
        }

        var pageCount = Math.max(1, Math.ceil(total / PAGE_LENGTH));
        var currentPage = Math.floor(start / PAGE_LENGTH) + 1;

        // Unchanged pager: leave the buttons (and any focus on them) alone.
        var sig = [currentPage, pageCount, total].join('|');
        if (sig === state.pagerSig) {
            return;
        }
        state.pagerSig = sig;

        // Rebuilding: remember which button had focus (Previous/Next by its
        // class, a number by its page) and give the new one focus.
        var active = document.activeElement;
        var refocus = null;
        if (active && $pager[0].contains(active)) {
            refocus = $(active).hasClass('previous') ? '.previous' : ($(active).hasClass('next') ? '.next' : '[data-page="' + $(active).attr('data-page') + '"]');
        }
        $pager.empty();
        var $ul = $('<ul>', { 'class': 'pagination' });

        function addButton(label, page, disabled, current, extraClass) {
            var $li = $('<li>', { 'class': 'page-item' + (disabled ? ' disabled' : '') + (current ? ' active' : '') });
            var $btn = $('<button>', { type: 'button', 'class': 'page-link' + (extraClass ? ' ' + extraClass : ''), text: label, 'data-page': page });
            if (extraClass) {
                $btn.attr('aria-label', label);
            }
            if (current) {
                $btn.attr('aria-current', 'page');
            }
            if (!disabled && !current) {
                $btn.on('click', function () {
                    state.start = (page - 1) * PAGE_LENGTH;
                    refresh(true);
                });
            }
            $ul.append($li.append($btn));
        }

        addButton(lang('Previous'), currentPage - 1, currentPage === 1, false, 'previous');
        var windowSize = 5;
        var firstPage = Math.max(1, currentPage - Math.floor(windowSize / 2));
        var lastPage = Math.min(pageCount, firstPage + windowSize - 1);
        firstPage = Math.max(1, lastPage - windowSize + 1);
        for (var p = firstPage; p <= lastPage; p++) {
            addButton(String(p), p, false, p === currentPage, '');
        }
        addButton(lang('Next'), currentPage + 1, currentPage === pageCount, false, 'next');

        $pager.append($ul);
        if (refocus) {
            var target = $pager.find(refocus)[0] || $pager.find('[aria-current="page"]')[0];
            if (target) {
                target.focus({ preventScroll: true });
            }
        }
    }

    /* --- Worker health strip ------------------------------------------- */

    function heartbeatAge(lastHeartbeat) {
        if (lastHeartbeat === null || lastHeartbeat === undefined) {
            return null;
        }
        var serverNowSec = (Date.now() + state.serverSkewMs) / 1000;
        return Math.max(0, Math.floor(serverNowSec - Number(lastHeartbeat)));
    }

    var WORKERS = [['cron_queue_worker', 'QueueWorker'], ['cron_promise_worker', 'StepWorker']];

    function workerDetail(worker) {
        var age = heartbeatAge(worker.last_heartbeat);
        return age === null
            ? lang('NoHeartbeatRecorded')
            : formatTemplate(lang('LastHeartbeatXAgo'), { ago: formatDuration(age) });
    }

    /**
     * How many of the configured copies of a worker type are running, when
     * more than one is configured (QUEUE_WORKER_COUNT / PROMISE_WORKER_COUNT):
     * { alive, configured }. null for the default single worker, whose pill
     * reads exactly as it always has.
     */
    function workerCounts(worker) {
        var configured = Number(worker.workers_configured);
        if (!isFinite(configured) || configured <= 1) {
            return null;
        }
        var alive = Number(worker.workers_alive);
        return { alive: isFinite(alive) ? Math.max(0, alive) : 0, configured: configured };
    }

    function workerPill(nameKey, worker) {
        worker = worker || {};
        var alive = !!worker.alive;
        var detail = workerDetail(worker);
        var counts = alive ? workerCounts(worker) : null;
        // Some, not all, of the configured workers are running: still working,
        // but short of what was configured.
        var partial = counts !== null && counts.alive < counts.configured;
        var stateClass = !alive ? 'sr-state-danger' : (partial ? 'sr-state-warning' : 'sr-state-success');

        var $pill = $('<span>', {
            'class': 'sr-state-pill sr-qm-worker ' + stateClass,
            'data-worker': nameKey,
            title: detail,
        });
        $pill.append(
            $('<span>', { 'class': 'sr-qm-worker-name', text: lang(nameKey) }),
            document.createTextNode(' · '),
            $('<span>', { text: lang(alive ? 'UpgradeStateRunning' : 'NotRunning') })
        );
        if (counts !== null) {
            $pill.append(
                document.createTextNode(' · '),
                $('<span>', {
                    'class': 'sr-qm-worker-count',
                    text: formatTemplate(lang('XOfY'), { alive: counts.alive, configured: counts.configured }),
                })
            );
        }
        // A stopped worker states its last heartbeat in the strip itself,
        // not only in the tooltip: it is the fact the admin needs.
        if (!alive) {
            $pill.append(document.createTextNode(' · '), $('<span>', { text: detail }));
        }
        return $pill;
    }

    function stat(labelKey, value, attention) {
        return $('<span>', { 'class': 'sr-qm-stat' }).append(
            $('<span>', { 'class': 'sr-qm-stat-label', text: lang(labelKey) }),
            $('<span>', { 'class': 'sr-table-count' + (attention ? ' sr-table-count--attn' : ''), text: value })
        );
    }

    /** Announces a worker stopping or recovering (never the first reading). */
    function announceWorkerChanges(workers) {
        var now = {};
        WORKERS.forEach(function (w) { now[w[0]] = !!(workers[w[0]] || {}).alive; });
        if (state.workersAlive) {
            WORKERS.forEach(function (w) {
                if (state.workersAlive[w[0]] !== now[w[0]]) {
                    announce(lang(w[1]) + ': ' + lang(now[w[0]] ? 'UpgradeStateRunning' : 'NotRunning'));
                }
            });
        }
        state.workersAlive = now;
    }

    /**
     * The strip is rebuilt only when what it shows changes; otherwise only
     * the heartbeat tooltips are refreshed in place. A summary failure shows
     * its own "unavailable" pill and leaves the list alone.
     */
    function renderHealth() {
        var el = document.getElementById('qm-health');
        var summary = state.summary;
        var sig;
        var build;

        if (state.summaryFailing) {
            sig = 'unavailable';
            build = function () {
                return [$('<span>', { 'class': 'sr-state-pill sr-state-warning', text: lang('WorkerStatusUnavailable') })];
            };
        } else if (summary) {
            var workers = summary.workers || {};
            var oldest = formatDuration(summary.oldest_pending_age_seconds);
            var failed = Number(summary.failed_24h) || 0;
            announceWorkerChanges(workers);
            sig = JSON.stringify([
                WORKERS.map(function (w) {
                    var worker = workers[w[0]] || {};
                    // A running worker's heartbeat age lives in its tooltip,
                    // so it does not force a rebuild every poll; how many of
                    // its configured copies are running does.
                    var counts = workerCounts(worker);
                    return worker.alive
                        ? 'up' + (counts ? ':' + counts.alive + '/' + counts.configured : '')
                        : 'down:' + workerDetail(worker);
                }),
                oldest,
                failed,
            ]);
            build = function () {
                return [
                    workerPill('QueueWorker', workers.cron_queue_worker),
                    workerPill('StepWorker', workers.cron_promise_worker),
                    stat('OldestPending', oldest === null ? '—' : oldest, false),
                    stat('FailedInTheLast24Hours', failed, failed > 0),
                ];
            };
        } else {
            return;
        }

        if (sig !== state.healthSig) {
            if (selectionBlocks(el)) {
                return; // rebuild after the user is done selecting
            }
            $(el).empty().append(build());
            state.healthSig = sig;
        } else if (summary && !state.summaryFailing) {
            WORKERS.forEach(function (w) {
                var pill = el.querySelector('.sr-qm-worker[data-worker="' + w[1] + '"]');
                if (pill) {
                    pill.title = workerDetail((summary.workers || {})[w[0]] || {});
                }
            });
        }
    }

    /* --- Live indicator + retry countdowns (1 s ticker) ---------------- */

    function updateLiveText() {
        var $live = $('#qm-live');
        var now = Date.now();
        var text;
        if (state.authLost) {
            text = lang('LiveUpdatesStopped');
        } else if (state.failing) {
            var wait = Math.max(0, Math.ceil((state.nextPollAt - now) / 1000));
            text = formatTemplate(lang('UpdateFailedRetryingInX'), { time: formatDuration(wait) });
        } else if (state.lastOkAt) {
            text = formatTemplate(lang('LiveUpdatedXAgo'), { ago: formatDuration(Math.floor((now - state.lastOkAt) / 1000)) });
        } else {
            text = lang('Loading');
        }
        $live.toggleClass('is-error', state.failing || state.authLost);
        var liveText = document.getElementById('qm-live-text');
        // Not selection-guarded: the age must stay truthful.
        if (liveText && liveText.textContent !== text) {
            liveText.textContent = text;
        }
    }

    function updateCountdowns() {
        if (state.authLost) {
            return;
        }
        var now = Date.now();
        $root.find('.sr-qm-retry-chip').each(function () {
            var $chip = $(this);
            var attempt = $chip.attr('data-retry-attempt');
            var remaining = retryCountdown($chip.attr('data-retry-in'), state.listFetchedAt, now);
            var text = retryChipText(attempt, remaining);
            if (this.textContent !== text && !selectionBlocks(this)) {
                this.textContent = text;
            }
        });
    }

    /* ------------------------------------------------------------------ *
     * Expander                                                           *
     * ------------------------------------------------------------------ */

    function rowSignature(row) {
        var progress = row.progress || {};
        return [row.display && row.display.key, row.updated_at, progress.done, progress.total, row.error].join('|');
    }

    function rowById(id) {
        for (var i = 0; i < state.rows.length; i++) {
            if (state.rows[i].id === id) {
                return state.rows[i];
            }
        }
        return null;
    }

    function toggleExpander(id) {
        var $tr = $('#qm-tbody tr.sr-qm-row[data-task-id="' + id + '"]');
        var $btn = $tr.find('.sr-qm-caret');

        if (state.expanded.has(id)) {
            state.expanded.get(id).$row.remove();
            state.expanded.delete(id);
            $tr.removeClass('is-expanded');
            syncCaret($btn[0], id, false);
            return;
        }

        var $panel = $('<div>', { 'class': 'sr-expand-panel sr-qm-panel' }).append(
            $('<div>', { 'class': 'sr-subtable-loading', text: lang('Loading') })
        );
        var $row = $('<tr>', { 'class': 'sr-expand-row sr-qm-expand-row', id: 'qm-expand-' + id }).append(
            $('<td>', { colspan: COLUMN_COUNT }).append($panel)
        );
        state.expanded.set(id, { $row: $row, signature: null, loading: false, row: rowById(id) });
        $tr.after($row).addClass('is-expanded');
        syncCaret($btn[0], id, true);
        loadSteps(id);
    }

    /** Applies deferred steps or fetches fresh ones when the row changed. */
    function syncExpander(expander, row) {
        var panel = expander.$row.find('.sr-qm-panel')[0];
        if (selectionBlocks(panel)) {
            return; // at most the one fetch already made; apply later
        }
        var current = rowSignature(row);
        if (expander.pendingData) {
            var data = expander.pendingData;
            var sig = expander.pendingSig;
            expander.pendingData = null;
            expander.pendingSig = null;
            if (sig === current) {
                expander.signature = sig;
                renderPanel($(panel), row, data);
            }
        }
        if (expander.signature !== current && !expander.loading) {
            loadSteps(row.id);
        }
    }

    function loadSteps(id) {
        var entry = state.expanded.get(id);
        if (!entry) {
            return;
        }
        var row = entry.row || rowById(id);
        entry.loading = true;

        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/admin/queue/steps',
            data: { queue_task_id: id },
            dataType: 'json',
            timeout: REQUEST_TIMEOUT_MS,
        }).done(function (response) {
            var current = state.expanded.get(id);
            if (current !== entry) {
                return; // closed (or reopened) meanwhile
            }
            var data = (response && response.data) ? response.data : {};
            var $panel = entry.$row.find('.sr-qm-panel');
            // Never rewrite a panel the user is selecting text in (copying an
            // error message): leave the signature stale so a later poll
            // retries once the selection is gone.
            var sig = row ? rowSignature(row) : null;
            if (selectionBlocks($panel[0])) {
                // Keep the fetched steps and apply them when the selection
                // clears or the deferral cap trips -- no refetch per poll.
                entry.pendingData = data;
                entry.pendingSig = sig;
                return;
            }
            entry.signature = sig;
            renderPanel($panel, entry.row || row, data);
        }).fail(function () {
            if (state.expanded.get(id) !== entry) {
                return;
            }
            var $panel = entry.$row.find('.sr-qm-panel').empty();
            $panel.append($('<div>', { 'class': 'sr-subtable-loading', text: lang('CouldNotLoadTheSteps') }));
        }).always(function () {
            entry.loading = false;
        });
    }

    function stepIsFailed(step) {
        return step.status === 'failed' || step.state === 'failed';
    }

    /** [lang key, .sr-state-* family] for one step, given its task's state. */
    function stepStatus(step, taskTerminal) {
        if (step.status === 'completed' || step.status === 'fulfilled' || step.state === 'completed' || step.state === 'fulfilled') {
            return ['Done', 'success'];
        }
        if (stepIsFailed(step)) {
            return ['Failed', 'danger'];
        }
        if (step.status === 'canceled' || step.state === 'canceled') {
            return step.state === 'in_progress' ? ['Stopping', 'neutral'] : ['Canceled', 'neutral'];
        }
        if (step.state === 'in_progress' || step.status === 'in_progress') {
            return ['UpgradeStateRunning', 'info'];
        }
        if (taskTerminal) {
            return ['NotRun', 'neutral'];
        }
        return step.retry_attempt ? ['Retrying', 'warning'] : ['Pending', 'warning'];
    }

    function renderPanel($panel, row, data) {
        var steps = Array.isArray(data.steps) ? data.steps : [];
        var taskTerminal = row ? isTerminalRow(row) : false;
        var failingIndex = -1;
        for (var i = 0; i < steps.length; i++) {
            if (stepIsFailed(steps[i])) {
                failingIndex = i;
                break;
            }
        }

        // The panel is rebuilt as a whole; if focus was inside it (the "View
        // payload" link), put it back on the rebuilt link afterwards.
        var active = document.activeElement;
        var refocusPayload = active && $panel[0].contains(active) && active.getAttribute('data-payload-id');

        $panel.empty();

        // The banner: the failing step's error, else the task's last error.
        // Shown for a failed or retrying task, or whenever a step failed, so a
        // stale last_error on a task that later succeeded does not read as a
        // current fault.
        var failingStep = failingIndex !== -1 ? steps[failingIndex] : null;
        var key = row && row.display ? row.display.key : '';
        var message = (failingStep && failingStep.error) || (row && row.error) || '';
        if (message && (failingStep || key === 'failed' || key === 'retrying')) {
            var title = failingStep
                ? formatTemplate(lang('FailedAtStepXY'), { number: failingIndex + 1, step: failingStep.stage || '' })
                : lang('LastError');
            // A named group, not role=alert: the panel is rebuilt on every
            // change, and a live region would re-announce the same error each time.
            var $banner = $('<div>', { 'class': 'sr-qm-banner', role: 'group', 'aria-label': title }).append(
                $('<i>', { 'class': 'fa fa-circle-exclamation', 'aria-hidden': 'true' }),
                $('<div>', { 'class': 'sr-qm-banner-text' }).append(
                    $('<strong>', { text: title + ':' }),
                    document.createTextNode(' '),
                    $('<span>', { 'class': 'sr-qm-banner-message', text: message })
                )
            );
            var meta = [];
            if (row && row.attempts > 1) {
                meta.push(formatTemplate(lang('AfterXAttempts'), { count: row.attempts }));
            }
            // When the error was last hit: the failing step's last update,
            // else the task's (server time, shown as the server wrote it).
            var lastTried = (failingStep && failingStep.updated_at) || (row && row.updated_at) || '';
            if (lastTried) {
                meta.push(formatTemplate(lang('LastTriedX'), { time: lastTried }));
            }
            if (meta.length) {
                $banner.find('.sr-qm-banner-text').append(
                    $('<span>', { 'class': 'sr-qm-banner-meta', text: ' · ' + meta.join(' · ') })
                );
            }
            $panel.append($banner);
        }

        if (data.purged || !steps.length) {
            $panel.append($('<p>', { 'class': 'sr-qm-purged', text: lang('QueueStepDetailsPurged') }));
        } else {
            var $tbody = $('<tbody>');
            steps.forEach(function (step, index) {
                var status = stepStatus(step, taskTerminal);
                var duration = formatDuration(step.duration_seconds);
                var $note = $('<td>', { 'class': 'sr-qm-step-note' });
                if (step.error && stepIsFailed(step)) {
                    $note.text(step.error);
                } else if (step.retry_attempt && TERMINAL.indexOf(step.status) === -1) {
                    $note.text(formatTemplate(lang('RetryAttemptX'), { attempt: step.retry_attempt }));
                }
                $tbody.append(
                    $('<tr>', { 'class': index === failingIndex ? 'is-failing' : '' }).append(
                        $('<td>', { 'class': 'sr-num', text: index + 1 }),
                        $('<td>', { 'class': 'sr-qm-step-name', text: step.stage || '' }),
                        $('<td>').append(statePill(status[0], status[1])),
                        duration === null ? $('<td>').append(dash()) : $('<td>', { 'class': 'sr-num', text: duration }),
                        $note
                    )
                );
            });
            $panel.append(
                $('<table>', { 'class': 'sr-subtable sr-qm-steps' }).append(
                    $('<thead>').append($('<tr>').append(
                        $('<th>', { 'class': 'sr-num', text: '#' }),
                        $('<th>', { text: lang('Step') }),
                        $('<th>', { text: lang('Status') }),
                        $('<th>', { text: lang('Time') }),
                        $('<th>', { text: lang('Note') })
                    )),
                    $tbody
                )
            );
        }

        $panel.append(
            $('<div>', { 'class': 'sr-subtable-foot' }).append(
                $('<button>', {
                    type: 'button',
                    'class': 'sr-qm-payload-link',
                    'data-payload-id': row ? row.id : '',
                    text: lang('ViewPayload'),
                })
            )
        );

        if (refocusPayload) {
            $panel.find('.sr-qm-payload-link[data-payload-id="' + refocusPayload + '"]').trigger('focus');
        }
    }

    /* ------------------------------------------------------------------ *
     * Payload modal                                                      *
     * ------------------------------------------------------------------ */

    var payloadSeq = 0;

    var payloadTriggerId = null;

    // Focus goes back to what opened the modal. The panel may have been
    // re-rendered while the modal was up, so the link is looked up again by
    // task id; if it is gone, the row's caret takes focus instead.
    $('#qm-payload-modal').on('hidden.bs.modal', function () {
        var id = payloadTriggerId;
        payloadTriggerId = null;
        if (!id) {
            return;
        }
        var target = $root.find('.sr-qm-payload-link[data-payload-id="' + id + '"]')[0]
            || $root.find('.sr-qm-caret[data-expand-id="' + id + '"]')[0];
        if (target) {
            target.focus();
        }
    });

    function openPayload(id) {
        var seq = ++payloadSeq;
        payloadTriggerId = id;
        var $pre = $('#qm-payload-pre').text(lang('Loading'));
        $('#qm-payload-title').text(formatTemplate(lang('PayloadForTaskX'), { id: id }));
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('qm-payload-modal')).show();

        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/admin/queue/payload',
            data: { queue_task_id: id },
            dataType: 'json',
            timeout: REQUEST_TIMEOUT_MS,
        }).done(function (response) {
            if (seq !== payloadSeq) {
                return;
            }
            var payload = response && response.data ? response.data.payload : null;
            // An unparseable payload comes back as {"_raw": "<redacted text>"}:
            // show that text as-is rather than as a JSON-quoted string.
            var keys = payload && typeof payload === 'object' ? Object.keys(payload) : [];
            var text = (keys.length === 1 && keys[0] === '_raw' && typeof payload._raw === 'string')
                ? payload._raw
                : JSON.stringify(payload, null, 2);
            $pre.text(text);
        }).fail(function () {
            if (seq === payloadSeq) {
                $pre.text(lang('RequestFailed'));
            }
        });
    }

    /* ------------------------------------------------------------------ *
     * Selection + bulk bar                                               *
     * ------------------------------------------------------------------ *
     * The selection is a Set of task ids that survives polls and pages,    *
     * or -- once escalated with "Select all N matching" -- the filter      *
     * itself (state.allFiltered), sent as {all_filtered, filter} so the    *
     * server resolves the same set. A filter change or an action clears    *
     * it.                                                                  */

    function syncSelectAll() {
        var ids = state.rows.map(function (row) { return row.id; });
        var selectedOnPage = ids.filter(isSelected).length;
        $('#qm-select-all')
            .prop('checked', ids.length > 0 && selectedOnPage === ids.length)
            .prop('indeterminate', selectedOnPage > 0 && selectedOnPage < ids.length);
    }

    function syncAllRowSelection() {
        state.rowNodes.forEach(function (entry, id) {
            syncRowSelection(entry, id);
        });
        syncSelectAll();
    }

    /**
     * How many tasks an "all N matching" request for `action` targets: for
     * restart the server's actionable_total (already-restarted tasks excluded,
     * exactly as the restart resolver does), for delete its deletable_total
     * (types that cannot be deleted excluded, as the delete resolver does), else
     * the filtered total.
     */
    function actionTotal(action) {
        if (action === 'restart' && state.actionableTotal !== null) {
            return state.actionableTotal;
        }
        if (action === 'delete' && state.deletableTotal !== null) {
            return state.deletableTotal;
        }
        return state.filtered;
    }

    /**
     * The "all N matching" count. For one action: what that action targets (the
     * confirm dialog names it). Without one (the bar and the Select all link):
     * when the view offers a single action, that action's count, so the bar and
     * the dialog say the same number; when it offers several (Failed/Canceled:
     * Restart and Delete, whose counts can differ), the tasks matching the
     * filter, and each dialog then names its own action's count.
     */
    function escalatedCount(action) {
        if (action) {
            return actionTotal(action);
        }
        var actions = bulkActionsForStatus(state.status);
        return actions.length === 1 ? actionTotal(actions[0]) : state.filtered;
    }

    /** True when an "all N" request for this action would be accepted: 1..BULK_MAX targets. */
    function actionWithinCap(action) {
        var total = actionTotal(action);
        return total > 0 && total <= BULK_MAX;
    }

    function selectedCount() {
        return state.allFiltered ? escalatedCount() : state.selected.size;
    }

    /**
     * Drops selected ids that left the view, judged from two polls of the same
     * page. A row can also leave a page by being pushed to the next one, so an
     * id is only dropped when it provably no longer matches: everything fits
     * on one page, or (sorted by id) its id lies inside the new page's id
     * range, where it would still appear if it matched.
     */
    function pruneVanishedSelection(previousRows) {
        if (state.allFiltered || !state.selected.size) {
            return;
        }
        var now = new Set(state.rows.map(function (row) { return row.id; }));
        var ids = state.rows.map(function (row) { return row.id; });
        var lo = ids.length ? Math.min.apply(null, ids) : null;
        var hi = ids.length ? Math.max.apply(null, ids) : null;
        var singlePage = state.filtered <= PAGE_LENGTH && state.start === 0;
        previousRows.forEach(function (row) {
            var id = row.id;
            if (!state.selected.has(id) || now.has(id)) {
                return;
            }
            var insideRange = state.orderCol === 'id' && lo !== null && id >= lo && id <= hi;
            if (singlePage || insideRange) {
                state.selected.delete(id);
            }
        });
    }

    function setText(el, text) {
        if (el && el.textContent !== text) {
            el.textContent = text;
        }
    }

    /** The applied filter (never the DOM's in-progress values), for all_filtered. */
    function currentFilter() {
        return {
            q: state.q,
            status: state.status,
            task_type: state.taskType,
            date_from: state.dateFrom,
            date_to: state.dateTo,
            // Never act on tasks queued after the count the user saw.
            max_id: state.maxId,
        };
    }

    function renderBulkBar() {
        var actions = bulkActionsForStatus(state.status);
        // An "all N" selection that no offered action has anything left to act on.
        if (state.allFiltered && !actions.some(function (a) { return actionTotal(a) > 0; })) {
            state.allFiltered = false;
            state.selected.clear();
            syncAllRowSelection();
        }
        var count = selectedCount();
        var pageIds = state.rows.map(function (row) { return row.id; });
        var pageAllSelected = pageIds.length > 0 && pageIds.every(isSelected);
        var allCount = escalatedCount();
        // The cap is judged per action (each one is its own request): one action
        // over it must not block another that is under it.
        var anyWithinCap = actions.some(actionWithinCap);
        var everyOverCap = actions.length > 0 && !anyWithinCap && actions.some(function (a) { return actionTotal(a) > BULK_MAX; });
        var escalationPossible = !state.allFiltered && actions.length > 0 && pageAllSelected && state.filtered > pageIds.length;
        var canEscalate = escalationPossible && anyWithinCap;
        var explicitTooMany = !state.allFiltered && state.selected.size > BULK_MAX;

        $('#qm-bulk-bar').toggleClass('d-none', count === 0);
        $('#qm-toolbar').toggleClass('d-none', count > 0);

        // Not a live region: it ticks with every poll. Escalation is announced
        // once, by the link's handler.
        // ("All 1 matching tasks" reads badly; one task is just "1 selected".)
        setText(document.getElementById('qm-bulk-count'), (state.allFiltered && allCount !== 1)
            ? formatTemplate(lang('AllXMatchingTasksSelected'), { count: allCount })
            : String(lang('NSelected')).replace('{n}', count));

        var $link = $('#qm-select-all-filtered').toggleClass('d-none', !canEscalate);
        setText($link[0], formatTemplate(lang('SelectAllXMatchingTasks'), { count: allCount }));

        // Why nothing can be done, as visible text (a disabled button's
        // tooltip is unreachable by keyboard).
        var note = '';
        if (!actions.length) {
            note = lang('ChooseAStatusFilterToActOnSelectedTasks');
        } else if ((escalationPossible || state.allFiltered) && everyOverCap) {
            note = formatTemplate(lang('SelectAllTooManyMatches'), { max: BULK_MAX, noun: lang('Tasks') });
        } else if (explicitTooMany) {
            note = formatTemplate(lang('TooManyTasksSelectedSelectAtMostX'), { max: BULK_MAX });
        }
        var noteEl = document.getElementById('qm-bulk-note');
        setText(noteEl, note);
        $(noteEl).toggleClass('d-none', note === '');

        $('#qm-bulk-bar [data-bulk-action]').each(function () {
            var action = $(this).attr('data-bulk-action');
            // An "all N" selection this action would reach none of (every match
            // already restarted, or of a type that cannot be deleted) or more
            // than one request may take.
            var refused = state.allFiltered ? !actionWithinCap(action) : explicitTooMany;
            $(this)
                .toggleClass('d-none', actions.indexOf(action) === -1)
                .prop('disabled', refused || state.actionBusy);
        });
    }

    function clearSelection() {
        state.selected.clear();
        state.allFiltered = false;
        syncAllRowSelection();
        renderBulkBar();
    }

    /* ------------------------------------------------------------------ *
     * Actions: confirm -> POST -> result toast -> refresh                *
     * ------------------------------------------------------------------ */

    // Same request convention as data-integrity.js: a JSON body skips
    // csrf-magic's automatic token injection, so the token goes in the
    // CSRF-TOKEN header.
    function csrfHeaders() {
        return { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' };
    }

    /** True when csrf-magic re-sent the request with a fresh token (the retry reports). */
    function reissuedByCsrfRetry(xhr, settings) {
        if (typeof retryCSRF !== 'function') {
            return false;
        }
        var before = (typeof retryCSRFCount !== 'undefined') ? retryCSRFCount : null;
        if (!retryCSRF(xhr, settings)) {
            return false;
        }
        return before === null || (typeof retryCSRFCount !== 'undefined' && retryCSRFCount > before);
    }

    // Title / body / verb per action. Stop, Cancel and Delete cannot be undone,
    // so their dialog is destructive (static backdrop, no Esc). Delete is bulk
    // only; its singular entries serve a selection of one.
    var CONFIRM = {
        stop: { title: 'StopTaskXQuestion', body: 'QueueStopConfirmBody', verb: 'StopTask', icon: 'fa-circle-stop', destructive: true },
        cancel: { title: 'CancelTaskXQuestion', body: 'QueueCancelConfirmBody', verb: 'CancelTask', icon: 'fa-ban', destructive: true },
        run_now: { title: 'RunTaskXNowQuestion', body: 'QueueRunNowConfirmBody', verb: 'RunNow', icon: 'fa-forward', destructive: false },
        restart: { title: 'RestartTaskXQuestion', body: 'QueueRestartConfirmBody', verb: 'RestartTask', icon: 'fa-rotate-right', destructive: false },
        delete: { title: 'DeleteTaskXQuestion', body: 'QueueDeleteConfirmBody', verb: 'DeleteTask', icon: 'fa-trash', destructive: true },
    };
    var CONFIRM_BULK = {
        cancel: { title: 'CancelXSelectedTasksQuestion', body: 'QueueCancelSelectedConfirmBody', verb: 'CancelXTasks', icon: 'fa-ban', destructive: true },
        restart: { title: 'RestartXSelectedTasksQuestion', body: 'QueueRestartSelectedConfirmBody', verb: 'RestartXTasks', icon: 'fa-rotate-right', destructive: false },
        delete: { title: 'DeleteXSelectedTasksQuestion', body: 'QueueDeleteSelectedConfirmBody', verb: 'DeleteXTasks', icon: 'fa-trash', destructive: true },
    };

    // Title for a single task whose id is not known (select-all that shrank to one).
    var CONFIRM_NAMELESS_TITLE = { cancel: 'CancelThisTaskQuestion', restart: 'RestartThisTaskQuestion', delete: 'DeleteThisTaskQuestion' };

    var confirmSpec = null;

    /**
     * Why a restarted / run-now task might sit waiting: a worker reported as
     * stopped, or worker status that could not be read at all (softer
     * wording). '' when both workers are known to be running.
     */
    function workerNote() {
        var workers = (state.summary && !state.summaryFailing) ? state.summary.workers : null;
        var queueWorker = workers && workers.cron_queue_worker;
        if (!queueWorker || typeof queueWorker.alive !== 'boolean') {
            return lang('WorkerStatusUnknownTasksMayWait');
        }
        var parts = [];
        if (queueWorker.alive !== true) {
            parts.push(lang('QueueWorkerNotRunningTasksWait'));
        }
        var stepWorker = workers.cron_promise_worker;
        if (stepWorker && stepWorker.alive === false) {
            parts.push(lang('StepWorkerNotRunningStepsWait'));
        }
        return parts.join(' ');
    }

    /**
     * Builds the dialog for one request. spec: { action, bulk, ids | allFiltered,
     * count, taskId (one task), row (when on this page) }.
     */
    function describeConfirm(spec) {
        // An "all N" that has shrunk to one task reads in the singular. Its id is only
        // named when exactly one displayed row can take this action (the one task the
        // server will act on); otherwise (the other rows are already restarted, or the
        // one target is on another page) the wording is singular but names no id.
        if (spec.allFiltered && spec.count === 1 && !spec.taskId) {
            var candidates = state.rows.filter(function (r) {
                var avail = availableActions(r);
                return spec.action === 'cancel' ? (avail.cancel || avail.stop) : !!avail[spec.action];
            });
            if (candidates.length === 1) {
                spec.taskId = candidates[0].id;
                spec.row = candidates[0];
            }
        }
        var nameless = spec.count === 1 && !spec.taskId;
        var one = spec.count === 1 && spec.taskId;
        var cfg;
        var body;
        var titleKey;
        if (one) {
            // A bulk cancel of one running task reads as Stop.
            var action = (spec.action === 'cancel' && spec.row && spec.row.status === 'in_progress') ? 'stop' : spec.action;
            cfg = CONFIRM[action];
            // A bulk cancel of one task not on this page may be running or pending.
            body = (spec.bulk && spec.action === 'cancel' && !spec.row) ? CONFIRM_BULK.cancel.body : cfg.body;
        } else if (nameless) {
            cfg = CONFIRM[spec.action];
            titleKey = CONFIRM_NAMELESS_TITLE[spec.action];
            // The one target is not on this page, so it may be running or pending.
            body = spec.action === 'cancel' ? CONFIRM_BULK.cancel.body : cfg.body;
        } else {
            cfg = CONFIRM_BULK[spec.action];
            body = cfg.body;
        }
        var vars = { id: spec.taskId, count: spec.count };

        var rerun = '';
        if (spec.action === 'restart') {
            rerun = (one || nameless) ? 'QueueTaskWillRunAgainWarning' : 'QueueTasksWillRunAgainWarning';
        } else if (spec.action === 'delete') {
            // The consequence, stated once in the amber note (design-system §8).
            rerun = (one || nameless) ? 'QueueTaskDeletedPermanentlyWarning' : 'QueueTasksDeletedPermanentlyWarning';
        } else if (spec.action === 'run_now') {
            rerun = (spec.row && spec.row.display && spec.row.display.key === 'retrying') ? 'QueueTaskWillRunAgainWarning' : 'QueueTaskWillRunOnNextPassWarning';
        }
        return {
            title: formatTemplate(lang(titleKey || cfg.title), vars),
            body: lang(body),
            verb: formatTemplate(lang(cfg.verb), vars),
            icon: cfg.icon,
            destructive: cfg.destructive,
            rerun: rerun ? lang(rerun) : '',
            workerNote: (spec.action === 'restart' || spec.action === 'run_now') ? workerNote() : '',
        };
    }

    function openConfirm(spec) {
        var el = document.getElementById('qm-confirm-modal');
        if (state.actionBusy || el.classList.contains('show')) {
            return;
        }
        var view = describeConfirm(spec);
        confirmSpec = spec;
        spec.destructive = view.destructive;

        $('#qm-confirm-title').text(view.title);
        $('#qm-confirm-icon').attr('class', 'fa ' + view.icon);
        $('#qm-confirm-icon-wrap').toggleClass('sr-modal-icon--danger', view.destructive);
        $('#qm-confirm-body').text(view.body);
        $('#qm-confirm-rerun-text').text(view.rerun);
        $('#qm-confirm-rerun').toggleClass('d-none', !view.rerun);
        $('#qm-confirm-worker-text').text(view.workerNote);
        $('#qm-confirm-worker').toggleClass('d-none', !view.workerNote);
        $('#qm-confirm-error').addClass('d-none').text('');
        // The body and whichever notes are shown (the "will run again"
        // safeguard included) are read when the dialog opens. Hidden notes are
        // left out: a referenced element is read even while hidden.
        var described = ['qm-confirm-body'];
        if (view.rerun) {
            described.push('qm-confirm-rerun-text');
        }
        if (view.workerNote) {
            described.push('qm-confirm-worker-text');
        }
        $(el).attr('aria-describedby', described.join(' '));
        $('#qm-confirm-commit').text(view.verb).prop('disabled', false).removeAttr('aria-busy');

        // Backdrop/keyboard are set at construction, so the instance is
        // rebuilt per dialog: a destructive confirm ignores Esc and the backdrop.
        var existing = window.bootstrap.Modal.getInstance(el);
        if (existing) {
            existing.dispose();
        }
        new window.bootstrap.Modal(el, {
            backdrop: view.destructive ? 'static' : true,
            keyboard: !view.destructive,
        }).show();
    }

    function setConfirmBusy(on) {
        state.actionBusy = on;
        var $commit = $('#qm-confirm-commit').prop('disabled', on);
        $commit.find('.spinner-border').remove();
        if (on) {
            $commit.attr('aria-busy', 'true').prepend(
                $('<span>', { 'class': 'spinner-border spinner-border-sm me-2', 'aria-hidden': 'true' })
            );
        } else {
            $commit.removeAttr('aria-busy');
        }
        $('#qm-confirm-dismiss, #qm-confirm-modal .btn-close').prop('disabled', on);
        renderBulkBar();
    }

    /** One toast per action: "N applied (deleted), M skipped", then the skips grouped by reason. */
    function toastResults(action, data) {
        var results = Array.isArray(data.results) ? data.results : [];
        var applied = Number(data.applied) || 0;
        var skipped = Number(data.skipped) || 0;
        var lines = [action === 'delete'
            ? formatTemplate(lang('XDeletedYSkipped'), { deleted: applied, skipped: skipped })
            : formatTemplate(lang('XAppliedYSkipped'), { applied: applied, skipped: skipped })];
        if (action === 'restart' && results.length === 1 && results[0].result === 'applied' && Number(results[0].new_id) > 0) {
            lines.push(formatTemplate(lang('TaskXRestartedAsTaskY'), { id: Number(results[0].id), new_id: Number(results[0].new_id) }));
        }
        groupSkipped(results, action).forEach(function (group) {
            lines.push(formatTemplate(lang(group[0]), { count: group[1] }));
        });
        // _lang text + integers only, escaped once here; toastr decodes once.
        showAlertFromMessage(lines.map(escapeHtml).join('<br>'), skipped === 0);
    }

    /** The API's machine-readable 400 reason (data.code), or ''. */
    function errorCode(xhr) {
        var json = xhr && xhr.responseJSON;
        return (json && json.data && typeof json.data.code === 'string') ? json.data.code : '';
    }

    function confirmErrorText(spec, xhr, textStatus) {
        if (errorCode(xhr) === 'too_many_targets') {
            return spec.allFiltered
                ? formatTemplate(lang('SelectAllTooManyMatches'), { max: BULK_MAX, noun: lang('Tasks') })
                : formatTemplate(lang('TooManyTasksSelectedSelectAtMostX'), { max: BULK_MAX });
        }
        // No response at all (timeout, dropped connection): the server may
        // still have applied it.
        if (textStatus === 'timeout' || !xhr || xhr.status === 0) {
            return lang('RequestMayHaveBeenAppliedCheckTheList');
        }
        return lang('RequestFailed');
    }

    $('#qm-confirm-commit').on('click', function () {
        var spec = confirmSpec;
        if (!spec || state.actionBusy) {
            return;
        }
        setConfirmBusy(true);
        var body = spec.allFiltered ? { all_filtered: true, filter: spec.filter } : { ids: spec.ids };

        $.ajax({
            type: 'POST',
            url: BASE_URL + ACTION_URLS[spec.action],
            contentType: 'application/json',
            headers: csrfHeaders(),
            dataType: 'json',
            data: JSON.stringify(body),
            timeout: ACTION_TIMEOUT_MS,
            success: function (response) {
                var data = (response && response.data) ? response.data : {};
                setConfirmBusy(false);
                if (spec.bulk) {
                    clearSelection();
                }
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('qm-confirm-modal')).hide();
                toastResults(spec.action, data);
                refresh(true);
            },
            error: function (xhr, textStatus) {
                if (reissuedByCsrfRetry(xhr, this)) {
                    return; // re-sent with a fresh token; that request reports
                }
                setConfirmBusy(false);
                if (errorCode(xhr) === 'no_targets') {
                    // Nothing matches any more (finished, pruned, or changed
                    // state): close, say so and show the current list.
                    if (spec.bulk) {
                        clearSelection();
                    }
                    window.bootstrap.Modal.getOrCreateInstance(document.getElementById('qm-confirm-modal')).hide();
                    showAlertFromMessage(escapeHtml(lang('NoTasksMatchAnyMore')), false);
                    refresh(true);
                    return;
                }
                // The dialog stays open and says what went wrong; the list is
                // refreshed in case part of it was applied.
                $('#qm-confirm-error').text(confirmErrorText(spec, xhr, textStatus)).removeClass('d-none');
                $('#qm-confirm-commit').trigger('focus');
                refresh(true);
            },
        });
    });

    var $confirmModal = $('#qm-confirm-modal');
    $confirmModal.on('shown.bs.modal', function () {
        // Focus the safe choice: the dialog asks before anything runs or stops.
        $('#qm-confirm-dismiss').trigger('focus');
    });
    $confirmModal.on('hide.bs.modal', function (e) {
        if (state.actionBusy) {
            e.preventDefault(); // a write in flight cannot be dismissed
        }
    });
    $confirmModal.on('hidden.bs.modal', function () {
        var spec = confirmSpec;
        confirmSpec = null;
        if (spec) {
            returnFocusAfterConfirm(spec);
        }
    });

    /**
     * Focus goes back to what opened the dialog: the row's action button if
     * it is still shown, else that row's overflow toggle (compact widths),
     * else its checkbox; for a bulk action, the bulk button, else the header
     * checkbox.
     */
    function returnFocusAfterConfirm(spec) {
        var candidates = [];
        if (!spec.bulk && spec.taskId) {
            var $tr = $('#qm-tbody tr.sr-qm-row[data-task-id="' + spec.taskId + '"]');
            candidates.push(
                $tr.find('[data-row-action="' + spec.opener + '"]')[0],
                $tr.find('.sr-row-actions-toggle')[0],
                $tr.find('[data-select-id]')[0]
            );
        } else {
            candidates.push($('#qm-bulk-bar [data-bulk-action="' + spec.action + '"]')[0]);
        }
        var target = candidates.filter(isShown)[0];
        if (target) {
            target.focus();
        } else {
            focusFallback();
        }
    }

    function startRowAction(action, id) {
        var row = rowById(id);
        openConfirm({ action: action, opener: action, bulk: false, ids: [id], count: 1, taskId: id, row: row });
    }

    function startBulkAction(action) {
        if (state.actionBusy || bulkActionsForStatus(state.status).indexOf(action) === -1) {
            return;
        }
        var spec = { action: action, bulk: true };
        if (state.allFiltered) {
            spec.allFiltered = true;
            spec.filter = currentFilter();
            spec.count = escalatedCount(action);
        } else {
            spec.ids = Array.from(state.selected);
            spec.count = spec.ids.length;
            if (spec.count === 1) {
                spec.taskId = spec.ids[0];
                spec.row = rowById(spec.taskId);
            }
        }
        if (spec.count > 0 && spec.count <= BULK_MAX) {
            openConfirm(spec);
        }
    }

    /* ------------------------------------------------------------------ *
     * Retention settings modal                                          *
     * ------------------------------------------------------------------ */

    var retention = { seq: 0, loaded: false, busy: false };
    var RETENTION_FIELDS = [
        { key: 'completed_days', input: '#qm-retention-completed', error: '#qm-retention-completed-error' },
        { key: 'failed_days', input: '#qm-retention-failed', error: '#qm-retention-failed-error' },
    ];

    function retentionInputs() {
        return $('#qm-retention-completed, #qm-retention-failed');
    }

    function clearRetentionErrors() {
        RETENTION_FIELDS.forEach(function (field) {
            $(field.input).removeClass('is-invalid').removeAttr('aria-invalid');
            $(field.error).text('');
        });
        $('#qm-retention-error').addClass('d-none').text('');
    }

    function setRetentionEnabled(on) {
        retentionInputs().prop('disabled', !on);
        $('#qm-retention-save').prop('disabled', !on);
    }

    function showRetentionError(text) {
        $('#qm-retention-error').text(text).removeClass('d-none');
    }

    /**
     * A whole number 0..RETENTION_MAX, or null. Same rule as the API: digits
     * only, so "1.5", "-1", "1e3" and a non-numeric entry (badInput) all fail.
     */
    function parseRetentionDays(input) {
        if (input.validity && input.validity.badInput) {
            return null;
        }
        var raw = String(input.value).trim();
        if (!/^\d{1,4}$/.test(raw)) {
            return null;
        }
        var days = parseInt(raw, 10);
        return days <= RETENTION_MAX ? days : null;
    }

    function retentionFocusFirst() {
        var first = document.getElementById('qm-retention-completed');
        if (retention.loaded && first && !first.disabled) {
            first.focus();
        }
    }

    $('#qm-retention-open').on('click', function () {
        var seq = ++retention.seq;
        retention.loaded = false;
        clearRetentionErrors();
        setRetentionEnabled(false);
        retentionInputs().val('');
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('qm-retention-modal')).show();

        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/admin/queue/settings',
            dataType: 'json',
            timeout: REQUEST_TIMEOUT_MS,
        }).done(function (response) {
            if (seq !== retention.seq) {
                return;
            }
            var data = (response && response.data) ? response.data : {};
            RETENTION_FIELDS.forEach(function (field) {
                var value = Number(data[field.key]);
                $(field.input).val(isFinite(value) ? String(value) : '');
            });
            retention.loaded = true;
            setRetentionEnabled(true);
            if ($('#qm-retention-modal').hasClass('show')) {
                retentionFocusFirst();
            }
        }).fail(function () {
            if (seq === retention.seq) {
                showRetentionError(lang('RequestFailed'));
            }
        });
    });

    $('#qm-retention-modal').on('shown.bs.modal', retentionFocusFirst);
    $('#qm-retention-modal').on('hide.bs.modal', function (e) {
        if (retention.busy) {
            e.preventDefault();
        }
    });
    $('#qm-retention-modal').on('hidden.bs.modal', function () {
        retention.seq++; // drop a load still in flight
        $('#qm-retention-open').trigger('focus');
    });

    $('#qm-retention-form').on('submit', function (e) {
        e.preventDefault();
        if (retention.busy || !retention.loaded) {
            return;
        }
        clearRetentionErrors();

        var values = {};
        var firstInvalid = null;
        var message = formatTemplate(lang('EnterAWholeNumberFrom0ToX'), { max: RETENTION_MAX });
        RETENTION_FIELDS.forEach(function (field) {
            var input = $(field.input)[0];
            var days = parseRetentionDays(input);
            if (days === null) {
                $(input).addClass('is-invalid').attr('aria-invalid', 'true');
                $(field.error).text(message);
                firstInvalid = firstInvalid || input;
            } else {
                values[field.key] = days;
            }
        });
        if (firstInvalid) {
            firstInvalid.focus();
            return;
        }

        retention.busy = true;
        setRetentionEnabled(false);
        $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/admin/queue/settings',
            contentType: 'application/json',
            headers: csrfHeaders(),
            dataType: 'json',
            data: JSON.stringify(values),
            timeout: REQUEST_TIMEOUT_MS,
            success: function () {
                retention.busy = false;
                setRetentionEnabled(true);
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('qm-retention-modal')).hide();
                showAlertFromMessage(escapeHtml(lang('RetentionSettingsSaved')), true);
            },
            error: function (xhr) {
                if (reissuedByCsrfRetry(xhr, this)) {
                    return;
                }
                retention.busy = false;
                setRetentionEnabled(true);
                if (xhr && xhr.status === 400) {
                    // The server rejected a value the client accepted: mark both.
                    RETENTION_FIELDS.forEach(function (field) {
                        $(field.input).addClass('is-invalid').attr('aria-invalid', 'true');
                        $(field.error).text(message);
                    });
                    $(RETENTION_FIELDS[0].input).trigger('focus');
                } else {
                    showRetentionError(lang('RequestFailed'));
                }
            },
        });
    });

    /* ------------------------------------------------------------------ *
     * Filters + URL                                                      *
     * ------------------------------------------------------------------ */

    function syncStatusPills() {
        $('#qm-status-filter .sr-status-chip').each(function () {
            var on = $(this).attr('data-status') === state.status;
            $(this).toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
        });
    }

    // The status filter lives in the query string (the failure notification
    // deep-links to ?status=failed); keep it current without adding history.
    function syncStatusParam() {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        var url = new URL(window.location.href);
        if (state.status) {
            url.searchParams.set('status', state.status);
        } else {
            url.searchParams.delete('status');
        }
        window.history.replaceState(window.history.state, '', url.toString());
    }

    function readStatusParam() {
        var value = '';
        try {
            value = (new URL(window.location.href).searchParams.get('status') || '').toLowerCase();
        } catch {
            value = '';
        }
        return STATUS_FILTERS.indexOf(value) !== -1 ? value : '';
    }

    function applyFilterChange() {
        state.start = 0;
        state.awaitingFilter = true;
        clearSelection();
        refresh(true);
    }

    function clearFilters() {
        state.q = '';
        state.status = '';
        state.taskType = '';
        state.dateFrom = '';
        state.dateTo = '';
        $('#qm-search').val('');
        $('#qm-task-type').val('');
        $('#qm-date-from').val('');
        $('#qm-date-to').val('');
        syncStatusPills();
        syncStatusParam();
        applyFilterChange();
    }

    /* ------------------------------------------------------------------ *
     * Events                                                             *
     * ------------------------------------------------------------------ */

    var searchTimer = null;
    $('#qm-search').on('input', function () {
        var value = $(this).val().trim();
        clearTimeout(searchTimer);
        // Covers the debounce window too: the rows shown are about to be replaced.
        if (value !== state.q) {
            state.awaitingFilter = true;
        }
        searchTimer = setTimeout(function () {
            if (value !== state.q) {
                state.q = value;
                applyFilterChange();
            } else if (!state.inFlight) {
                // Typed and reverted. A list request still in flight (an earlier chip, date or
                // type change) clears the flag itself when its response lands.
                state.awaitingFilter = false;
            }
        }, 300);
    });

    $('#qm-status-filter').on('click', '.sr-status-chip', function () {
        var value = $(this).attr('data-status') || '';
        if (value === state.status) {
            return;
        }
        state.status = value;
        syncStatusPills();
        syncStatusParam();
        applyFilterChange();
    });

    $('#qm-task-type').on('change', function () {
        state.taskType = $(this).val() || '';
        applyFilterChange();
    });

    $('#qm-date-from, #qm-date-to').on('change', function () {
        state.dateFrom = $('#qm-date-from').val() || '';
        state.dateTo = $('#qm-date-to').val() || '';
        applyFilterChange();
    });

    function sortBy($th) {
        var col = $th.attr('data-order-col');
        if (!col) {
            return;
        }
        if (col === state.orderCol) {
            state.orderDir = state.orderDir === 'asc' ? 'desc' : 'asc';
        } else {
            state.orderCol = col;
            state.orderDir = DESC_FIRST.indexOf(col) !== -1 ? 'desc' : 'asc';
        }
        state.start = 0;
        renderSortHeaders();
        refresh(true);
    }

    $root.on('click', 'th.sr-sortable', function () { sortBy($(this)); });
    $root.on('keydown', 'th.sr-sortable', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            sortBy($(this));
        }
    });

    $root.on('click', '.sr-qm-caret', function () {
        toggleExpander(parseInt($(this).attr('data-expand-id'), 10));
    });

    $root.on('click', '.sr-qm-payload-link', function () {
        var id = parseInt($(this).attr('data-payload-id'), 10);
        if (id > 0) {
            openPayload(id);
        }
    });

    $root.on('change', '[data-select-id]', function () {
        if (state.awaitingFilter) {
            syncAllRowSelection();
            renderBulkBar();
            return;
        }
        var id = parseInt($(this).attr('data-select-id'), 10);
        if (state.allFiltered && !this.checked) {
            // Unticking one row of an "all N" selection falls back to explicit
            // ids: the rows on this page, minus this one.
            state.allFiltered = false;
            state.selected.clear();
            state.rows.forEach(function (row) {
                if (row.id !== id) {
                    state.selected.add(row.id);
                }
            });
        } else if (this.checked) {
            state.selected.add(id);
        } else {
            state.selected.delete(id);
        }
        syncAllRowSelection();
        renderBulkBar();
    });

    $('#qm-select-all').on('change', function () {
        if (state.awaitingFilter) {
            syncAllRowSelection();
            renderBulkBar();
            return;
        }
        var checked = this.checked;
        if (!checked && state.allFiltered) {
            state.allFiltered = false;
            state.selected.clear();
        }
        state.rows.forEach(function (row) {
            if (checked) {
                state.selected.add(row.id);
            } else {
                state.selected.delete(row.id);
            }
        });
        syncAllRowSelection();
        renderBulkBar();
    });

    $('#qm-select-all-filtered').on('click', function () {
        state.allFiltered = true;
        syncAllRowSelection();
        renderBulkBar();
        announce(formatTemplate(lang('AllXMatchingTasksSelected'), { count: escalatedCount() }));
        // The link is gone once used; focus moves to the action it enables.
        var target = $('#qm-bulk-bar [data-bulk-action]').filter(function () { return isShown(this) && !this.disabled; })[0]
            || document.getElementById('qm-bulk-clear');
        target.focus();
    });

    $('#qm-bulk-clear').on('click', function () {
        clearSelection();
        focusFallback();
    });

    $('#qm-bulk-bar').on('click', '[data-bulk-action]', function () {
        startBulkAction($(this).attr('data-bulk-action'));
    });

    $root.on('click', '[data-row-action]', function () {
        var id = parseInt($(this).attr('data-task-id'), 10);
        var action = $(this).attr('data-row-action');
        if (id > 0 && Object.prototype.hasOwnProperty.call(ACTION_URLS, action)) {
            startRowAction(action, id);
        }
    });

    if (window.SRRowActionsMenu) {
        window.SRRowActionsMenu.bind({ container: '#qm-table', scope: $('#qm-table'), namespace: 'queuemonitor' });
    }

    $('#qm-auth-reload').on('click', function () {
        window.location.reload();
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearTimeout(state.timer);
        } else {
            refresh(true);
        }
    });

    state.tickTimer = setInterval(function () {
        updateLiveText();
        updateCountdowns();
    }, 1000);

    // When a held selection is released, apply whatever was deferred for it
    // right away instead of waiting for the next poll.
    var selectionTimer = null;
    document.addEventListener('selectionchange', function () {
        clearTimeout(selectionTimer);
        selectionTimer = setTimeout(function () {
            if (state.deferredPolls > 0 && !hasSelectionIn($root[0]) && state.loadedOnce && !state.authLost) {
                state.deferredPolls = 0;
                if (state.filtered > 0) {
                    patchRows(state.rows);
                }
                renderHealth();
            }
        }, 300);
    });

    /* ------------------------------------------------------------------ *
     * Start                                                              *
     * ------------------------------------------------------------------ */

    state.status = readStatusParam();
    syncStatusPills();
    renderSortHeaders();
    updateLiveText();
    refresh(true);
}(window, jQuery));
