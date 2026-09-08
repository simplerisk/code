/**
 * Data Integrity review/repair page controller.
 *
 * Populates the static .sr-table-card shell emitted by
 * admin/data_integrity.php (design-system.md §6): fills the two
 * tbodies from GET /api/v2/admin/data-integrity/issues, toggles tab/table/
 * empty-state visibility, and wires bulk selection + repair.
 *
 * Two issue types, two repair affordances:
 *  - invalid_text_encoding (Text Encoding tab): inline_text_edit repair --
 *    an editable "Suggested Fix" cell + a per-row Repair action (PATCH
 *    /issues/{id}) and a bulk "Repair Selected" action
 *    (POST /issues/bulk-repair).
 *  - file_content_mismatch (File Encoding tab): link_to_record repair --
 *    there is no auto-suggested fix for corrupted binary content (the admin
 *    has to re-upload the file), so the row has no clickable action --
 *    only plain "Record: <table_name> / <record_id>" text telling the admin
 *    what to go find manually. No SimpleRisk page today accepts a bare
 *    unique_name across the three file-storage tables the file detector
 *    scans (compliance_files, files, questionnaire_files -- see
 *    scan_file_content_mismatch(), includes/data_integrity.php) to view or
 *    re-upload the file by, so there is nothing to link to yet. FOLLOW-UP: a
 *    future task needs to build that viewer page (and re-upload flow) and
 *    turn this into a real link.
 *
 * No dismiss/ignore action anywhere -- this feature deliberately has none.
 *
 * All user-facing strings come from window.L()/window._lang (header.php's
 * $localization_required_by_scripts convention -- see the
 * 'CUSTOM:pages/data-integrity.js' entry in header.php). Issue data
 * (broken_value, suggested_value, table_name, record_id) is untrusted --
 * every value from the API is set via jQuery's `text:`/`.text()`, never
 * concatenated into an HTML string, so nothing here needs a second escaping
 * pass (matches the plain-text-only rule compliance-define-tests.js
 * documents for its own untrusted fields).
 */
(function ($) {
    'use strict';

    var $grid = $('#data-integrity-grid');
    if (!$grid.length) {
        return;
    }

    var state = {
        issues: [],
        totalsByType: {},
        activeTab: 'invalid_text_encoding',
        selected: new Set(),
    };

    // GET -- csrf-magic only gates form-urlencoded/FormData bodies, so no
    // CSRF-TOKEN header is needed for a read (matches fetchControlMappings()
    // in compliance-define-tests.js).
    function csrfHeaders() {
        return { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' };
    }

    function reportRequestFailure(xhr) {
        var message = (xhr && xhr.responseJSON && xhr.responseJSON.status_message)
            ? xhr.responseJSON.status_message
            : window.L('RequestFailed');
        showAlertFromMessage(message, false);
    }

    function fetchIssuesForType(issueType) {
        return $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/admin/data-integrity/issues?issue_type=' + encodeURIComponent(issueType),
        });
    }

    // Fetched per issue_type (two independent calls) rather than one
    // combined GET -- the server-side LIMIT applies per query, so a single
    // combined fetch let one detector's older backlog silently crowd the
    // other detector's rows out of the capped result (that tab would show
    // 0 with no signal the true count was nonzero). Fetching per type gives
    // each tab its own independent cap+total, so truncation is always
    // detectable and attributable to the right tab.
    function fetchIssues() {
        return $.when(
            fetchIssuesForType('invalid_text_encoding'),
            fetchIssuesForType('file_content_mismatch')
        ).then(function (textResult, fileResult) {
            var textData = (textResult[0] && textResult[0].data) ? textResult[0].data : {};
            var fileData = (fileResult[0] && fileResult[0].data) ? fileResult[0].data : {};
            var textIssues = textData.issues || [];
            var fileIssues = fileData.issues || [];

            state.issues = textIssues.concat(fileIssues);
            state.totalsByType = {
                invalid_text_encoding: (typeof textData.total === 'number') ? textData.total : textIssues.length,
                file_content_mismatch: (typeof fileData.total === 'number') ? fileData.total : fileIssues.length,
            };
            state.selected.clear();
            render();
        }).fail(reportRequestFailure);
    }

    function issuesFor(issueType) {
        return state.issues.filter(function (issue) {
            return issue.issue_type === issueType;
        });
    }

    function render() {
        var textIssues = issuesFor('invalid_text_encoding');
        var fileIssues = issuesFor('file_content_mismatch');
        var textTotal = state.totalsByType.invalid_text_encoding || 0;
        var fileTotal = state.totalsByType.file_content_mismatch || 0;
        var total = textTotal + fileTotal;
        var shown = textIssues.length + fileIssues.length;

        $('#data-integrity-count').text(total).toggleClass('d-none', total === 0);
        $('#data-integrity-foot-count').text(shown + ' ' + window.L('DataIntegrityTotalCount'));

        // Each detector type's LIMIT is independent (see fetchIssues()), so
        // truncation is reported per type -- naming which tab is affected,
        // rather than a single aggregate figure that can't tell the admin
        // which type's rows are actually missing.
        var cappedMessages = [];
        if (textTotal > textIssues.length) {
            cappedMessages.push(window.L('DataIntegrityTextEncoding') + ': ' + window.L('DataIntegrityShowingCapped').replace('{n}', textIssues.length).replace('{total}', textTotal));
        }
        if (fileTotal > fileIssues.length) {
            cappedMessages.push(window.L('DataIntegrityFileEncoding') + ': ' + window.L('DataIntegrityShowingCapped').replace('{n}', fileIssues.length).replace('{total}', fileTotal));
        }
        $('#data-integrity-showing-capped')
            .text(cappedMessages.join(' '))
            .toggleClass('d-none', cappedMessages.length === 0);

        $('#data-integrity-tab-count-text').text(textIssues.length).toggleClass('d-none', textIssues.length === 0);
        $('#data-integrity-tab-count-file').text(fileIssues.length).toggleClass('d-none', fileIssues.length === 0);

        // Tabs and the toolbar (including Scan Now) stay visible even at zero
        // issues -- design already picked "always present, count communicates
        // emptiness" over hide-when-zero for the tab strip, and Scan Now must
        // stay clickable on a never-scanned or fully-resolved instance. Only
        // the table area itself swaps for the "all caught up" empty state.
        // (#data-integrity-grid used to toggle d-none here instead, which hid
        // the toolbar -- and, being an ancestor of #data-integrity-empty, hid
        // the empty state right along with it -- leaving a blank card with no
        // way to trigger a scan.)
        $('#data-integrity-table-scroll').toggleClass('d-none', total === 0);
        $('#data-integrity-empty').toggleClass('d-none', total !== 0);

        renderTabActive();
        renderTextTable(textIssues);
        renderFileTable(fileIssues);
        renderBulkBar();
    }

    function renderTabActive() {
        $('#data-integrity-tab-text').toggleClass('is-active', state.activeTab === 'invalid_text_encoding');
        $('#data-integrity-tab-file').toggleClass('is-active', state.activeTab === 'file_content_mismatch');
        $('#data-integrity-table-text').toggleClass('d-none', state.activeTab !== 'invalid_text_encoding');
        $('#data-integrity-table-file').toggleClass('d-none', state.activeTab !== 'file_content_mismatch');
    }

    // "Open" is the only state this page's issues can be in (a resolved
    // issue drops out of GET /issues entirely -- see
    // get_open_data_integrity_issues()), so this is a fixed pill rather than
    // a per-issue lookup. Info/active family per design-system.md §7 ("Open"
    // is explicitly listed under .sr-state-info), not Warning.
    function statePill() {
        return $('<span>', { 'class': 'sr-state-pill sr-state-info', text: window.L('Open') });
    }

    function renderTextTable(issues) {
        var $tbody = $('#data-integrity-tbody-text').empty();

        issues.forEach(function (issue) {
            var $tr = $('<tr>');

            var $check = $('<td>', { 'class': 'sr-check-col' }).append(
                $('<input>', {
                    type: 'checkbox',
                    'data-issue-id': issue.id,
                }).prop('checked', state.selected.has(issue.id))
            );

            var $location = $('<td>', { text: issue.table_name + ' · ' + issue.column_name + ' #' + issue.record_id });

            var $broken = $('<td>').append(
                $('<span>', { 'class': 'sr-di-broken-value', text: issue.broken_value })
            );

            var $suggested = $('<td>').append(
                $('<input>', {
                    type: 'text',
                    'class': 'form-control form-control-sm',
                    'data-suggested-input': issue.id,
                    value: issue.suggested_value,
                })
            );

            var $status = $('<td>').append(statePill());

            var $actions = $('<td>', { 'class': 'sr-actions-col' }).append(
                $('<span>', { 'class': 'sr-row-actions' }).append(
                    $('<a>', { href: '#', 'data-repair-id': issue.id, text: window.L('Repair') })
                )
            );

            $tr.append($check, $location, $broken, $suggested, $status, $actions);
            $tbody.append($tr);
        });
    }

    function renderFileTable(issues) {
        var $tbody = $('#data-integrity-tbody-file').empty();

        issues.forEach(function (issue) {
            var $location = $('<td>', { text: issue.table_name });
            var $file = $('<td>', { text: issue.record_id });
            var $status = $('<td>').append(statePill());

            // See the file-header comment: there is no viewer page to link
            // to yet, so this is plain text (not an <a>) telling the admin
            // where to look manually.
            var $actions = $('<td>', { 'class': 'sr-actions-col' }).append(
                $('<span>', {
                    'class': 'sr-row-actions',
                    text: window.L('DataIntegrityRecordLocation') + ' ' + issue.table_name + ' / ' + issue.record_id,
                })
            );

            $tbody.append($('<tr>').append($location, $file, $status, $actions));
        });
    }

    function renderBulkBar() {
        var count = state.selected.size;
        $('#data-integrity-bulk-bar').toggleClass('d-none', count === 0);
        $('#data-integrity-toolbar').toggleClass('d-none', count > 0);
        $('#data-integrity-bulk-count').text(count + ' ' + window.L('Selected'));
    }

    function bulkRepairSelected() {
        var repairs = [];
        state.selected.forEach(function (id) {
            var $input = $grid.find('[data-suggested-input="' + id + '"]');
            repairs.push({ id: id, value: $input.length ? $input.val() : null });
        });

        if (!repairs.length) {
            return;
        }

        $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/admin/data-integrity/issues/bulk-repair',
            contentType: 'application/json',
            headers: csrfHeaders(),
            data: JSON.stringify({ repairs: repairs }),
        }).done(function (resp) {
            var results = (resp && resp.data) ? resp.data : [];
            var failedCount = results.filter(function (r) { return !r.applied; }).length;
            if (failedCount > 0) {
                showAlertFromMessage(window.L('RequestFailed'), false);
            }
            fetchIssues();
        }).fail(reportRequestFailure);
    }

    function applyOneRepair(id) {
        var $input = $grid.find('[data-suggested-input="' + id + '"]');
        var value = $input.length ? $input.val() : null;

        return $.ajax({
            type: 'PATCH',
            url: BASE_URL + '/api/v2/admin/data-integrity/issues/' + id,
            contentType: 'application/json',
            headers: csrfHeaders(),
            data: JSON.stringify({ value: value }),
        }).done(fetchIssues).fail(reportRequestFailure);
    }

    $('#data-integrity-tabs').on('click', '.sr-tab', function () {
        state.activeTab = $(this).data('tab');
        render();
    });

    $grid.on('change', '[data-issue-id]', function () {
        var id = parseInt($(this).attr('data-issue-id'), 10);
        if (this.checked) {
            state.selected.add(id);
        } else {
            state.selected.delete(id);
        }
        renderBulkBar();
    });

    $('#data-integrity-select-all').on('change', function () {
        var checked = this.checked;
        issuesFor('invalid_text_encoding').forEach(function (issue) {
            if (checked) {
                state.selected.add(issue.id);
            } else {
                state.selected.delete(issue.id);
            }
        });
        render();
    });

    $grid.on('click', '[data-repair-id]', function (e) {
        e.preventDefault();
        applyOneRepair(parseInt($(this).attr('data-repair-id'), 10));
    });

    $('#data-integrity-bulk-clear').on('click', function () {
        state.selected.clear();
        render();
    });

    $('#data-integrity-bulk-repair').on('click', bulkRepairSelected);

    $('#data-integrity-scan-now').on('click', function () {
        $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/admin/data-integrity/scan',
            headers: csrfHeaders(),
        }).done(fetchIssues).fail(reportRequestFailure);
    });

    fetchIssues();
}(jQuery));
