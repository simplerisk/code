// ====================================================================
// Define Exceptions grid (design-system.md §6) -- Task 11.
//
// A single client-rendered .sr-table-card replacing the old per-tab
// (Policy Exceptions / Control Exceptions / Unapproved Exceptions)
// EasyUI treegrid trio in governance/document_exceptions.php. The three
// old tabs partitioned the full exception set with no overlap (policy
// type + approved, control type + approved, both types + unapproved),
// so this module fetches all three GET /api/v2/exceptions/tree?type=
// responses and merges them into ONE flat row set rather than adding a
// new "give me everything" endpoint.
//
// Lifecycle (Open/Closed -- document_exceptions.status) and approval
// (Pending/Approved -- document_exceptions.approved) are genuinely
// separate DB fields, so they render as two SEPARATE .sr-state-pill
// columns rather than being collapsed into one.
//
// The Columns picker (.sr-table-tools .colpicker/.colpanel, toggling
// data-col="..." th/td visibility) is new to the design system as of
// this page -- see design-system.md §6c for the pattern spec, added
// once this task shipped.
//
// Split out into its own file (rather than added to js/simplerisk/pages/
// governance.js, which still owns the Define Framework Controls page's
// JS) following the same precedent Task 9 set with governance-documents.js
// -- see task-11-report.md for the actual line-count rationale.
// ====================================================================
var ExceptionsGrid = (function ($) {
    'use strict';

    // Lifecycle pill family (design-system.md §7 "State -- soft"), keyed off
    // the STABLE numeric document_exceptions.status value (1=Open, 2=Closed
    // by default) rather than the DB-stored label text -- document_exceptions_status
    // is customer-editable via Add/Remove Values, so a renamed/added status
    // still gets a sensible family via the fallback below. The actual label
    // shown always comes from the API's own `status` field (the real,
    // possibly-customized name), never a hardcoded lang key.
    var LIFECYCLE_PILL_CLASS_BY_VALUE = {
        1: 'sr-state-info',   // Open
        2: 'sr-state-neutral' // Closed
    };

    // The four togglable columns (Columns picker) -- order here drives both
    // the panel's checkbox order and (implicitly) the visibility default.
    var TOGGLE_COLUMNS = ['owner', 'framework_control', 'associated_risks', 'next_review'];

    // The full, unfiltered row set from the last fetch (all three
    // partitions merged) -- currentRows (below) is the FILTERED subset
    // actually rendered, same "rebuild fresh on every filter change" split
    // governance-documents.js's allRows/renderBody() uses.
    var allRows = [];
    var currentRows = [];
    var dt = null;
    var perms = { canEdit: false, canDelete: false, canApprove: false };
    var selectedIds = {};
    var columnVisible = { owner: true, framework_control: true, associated_risks: true, next_review: true };
    var approveModalMode = null; // 'approve' | 'unapprove' | 'batch-approve'
    var approveModalIds = [];
    var deleteModalIds = [];

    // Filters row state (design-system.md §6b/§6c). type/framework/control/
    // risk are multi-selects (never hold '' -- see normalizeMultiValue());
    // state/status are plain single selects. `review` has no dropdown of its
    // own (only type/framework/control/state/status/risk were asked for as
    // visible facets) -- it exists solely so the insights band's Overdue/Due
    // Soon tiles' ?review= deep-link actually narrows the grid instead of
    // landing on an unfiltered page; readFiltersFromControls() never touches
    // it, and Clear filters resets it same as any other facet.
    var filters = { type: [], framework: [], control: [], state: '', status: '', risk: [], review: '' };
    var previousTypeValue = [];
    var previousFrameworkValue = [];
    var previousControlValue = [];
    var previousRiskValue = [];
    // True once the KPI insights band's ?type=/?status=/?review= deep-link
    // (if any) has been read into `filters` -- see fetchAndRender()'s own
    // comment.
    var urlFiltersConsumed = false;

    function esc(value) {
        return escapeHtml(value === undefined || value === null ? '' : String(value));
    }

    function columnLabel(col) {
        switch (col) {
            case 'owner': return L('Owner');
            case 'framework_control': return L('FrameworkControl');
            case 'associated_risks': return L('AssociatedRisks');
            case 'next_review': return L('NextReviewDate');
            default: return col;
        }
    }

    function typeLabel(row) {
        return row.type === 'control' ? L('Control') : L('Policy');
    }

    function lifecyclePillHtml(row) {
        var cls = LIFECYCLE_PILL_CLASS_BY_VALUE[row.status_value] || 'sr-state-info';
        return '<span class="sr-state-pill ' + cls + '">' + esc(row.status) + '</span>';
    }

    function approvalPillHtml(row) {
        var cls = row.approved ? 'sr-state-success' : 'sr-state-warning';
        var label = row.approved ? L('Approved') : L('Pending');
        return '<span class="sr-state-pill ' + cls + '">' + esc(label) + '</span>';
    }

    // What this exception is attached to: a policy document by name, or a
    // control by "Framework — Control short name". Populates the toggleable
    // "Framework / Control" column (design-system.md §6c reference impl).
    function frameworkControlHtml(row) {
        if (row.type === 'control') {
            var parts = [];
            if (row.framework_name) { parts.push(esc(row.framework_name)); }
            if (row.parent_name) { parts.push(esc(row.parent_name)); }
            return parts.length ? parts.join(' &mdash; ') : '<span class="sr-cell-dash">&mdash;</span>';
        }
        return row.parent_name ? esc(row.parent_name) : '<span class="sr-cell-dash">&mdash;</span>';
    }

    function associatedRisksHtml(row) {
        var subjects = row.associated_risk_subjects || [];
        if (!subjects.length) {
            return '<span class="sr-cell-dash">&mdash;</span>';
        }
        return subjects.map(esc).join('<br>');
    }

    // The next-review date lane is always a status, never a plain fact:
    // overdue/due-soon/on-track each borrow the matching danger/warning/
    // success .sr-state-* tint via the --overdue/--due-soon/--ok chip
    // modifiers -- same convention governance-documents.js's
    // reviewChipHtml() uses. On-track (7+ days out) is affirmatively green
    // rather than falling back to the neutral base chip.
    function reviewChipHtml(row) {
        if (!row.next_review_date || row.next_review_date === '0000-00-00') {
            return '<span class="sr-cell-dash">&mdash;</span>';
        }
        var modifierClass = ' sr-date-chip--ok';
        var icon = 'fa-calendar-check';
        if (row.next_review_status === 'overdue') {
            modifierClass = ' sr-date-chip--overdue';
            icon = 'fa-triangle-exclamation';
        } else if (row.next_review_status === 'due_soon') {
            modifierClass = ' sr-date-chip--due-soon';
            icon = 'fa-calendar-days';
        }
        return '<span class="sr-date-chip' + modifierClass + '"><i class="fa ' + icon + '" aria-hidden="true"></i><span>' +
            esc(row.next_review_date) + '</span></span>';
    }

    // Row actions cluster: view (always), edit/approve-or-unapprove/delete
    // (permission-gated). Wrapped in the shipped .sr-row-actions-wrap/
    // .sr-row-actions-toggle/.sr-row-actions disclosure (_tables.scss,
    // design-system.md §6/§6b) -- matching Manage Audits, Define Tests, and
    // Governance Frameworks/Control Catalog. See governance-documents.js's
    // rowActionsHtml() for why the toggle markup is required (not optional)
    // once a cluster this size needs to stay reachable on touch. Shared
    // open/close/orient behavior via js/simplerisk/sr-row-actions-menu.js
    // (SRRowActionsMenu.bind(), wired once in init() below).
    function rowActionsHtml(row) {
        var actionsLabel = L('Actions');
        var html = '<span class="sr-row-actions-wrap"><button type="button" class="sr-row-actions-toggle" aria-expanded="false" aria-haspopup="true" aria-label="' + esc(actionsLabel) + '" title="' + esc(actionsLabel) + '"><i class="fa fa-ellipsis" aria-hidden="true"></i></button><span class="sr-row-actions">';
        html += '<button type="button" class="sr-row-action exception--view" data-id="' + esc(row.id) + '" data-type="' + esc(row.type) + '" data-approved="' + (row.approved ? 'true' : 'false') + '" title="' + esc(L('View')) + '" aria-label="' + esc(L('View')) + '"><i class="fa fa-eye" aria-hidden="true"></i></button>';
        if (perms.canEdit) {
            html += '<button type="button" class="sr-row-action exception--edit" data-id="' + esc(row.id) + '" data-type="' + esc(row.type) + '" title="' + esc(L('Edit')) + '" aria-label="' + esc(L('Edit')) + '"><i class="fa fa-edit" aria-hidden="true"></i></button>';
        }
        if (perms.canApprove) {
            if (row.approved) {
                html += '<button type="button" class="sr-row-action exception--unapprove" data-id="' + esc(row.id) + '" data-name="' + esc(row.name) + '" title="' + esc(L('Unapprove')) + '" aria-label="' + esc(L('Unapprove')) + '"><i class="fa fa-rotate-left" aria-hidden="true"></i></button>';
            } else {
                html += '<button type="button" class="sr-row-action exception--approve" data-id="' + esc(row.id) + '" data-name="' + esc(row.name) + '" title="' + esc(L('Approve')) + '" aria-label="' + esc(L('Approve')) + '"><i class="fa fa-check" aria-hidden="true"></i></button>';
            }
        }
        if (perms.canDelete) {
            html += '<button type="button" class="sr-row-action sr-row-action-danger exception--delete" data-id="' + esc(row.id) + '" data-name="' + esc(row.name) + '" title="' + esc(L('Delete')) + '" aria-label="' + esc(L('Delete')) + '"><i class="fa fa-trash" aria-hidden="true"></i></button>';
        }
        html += '</span></span>';
        return html;
    }

    function rowHtml(row) {
        var showCheckbox = perms.canApprove || perms.canDelete;
        return '<tr data-id="' + esc(row.id) + '">' +
            '<td class="sr-check-col">' + (showCheckbox ? '<input type="checkbox" class="form-check-input sr-row-check" data-id="' + esc(row.id) + '" aria-label="' + esc(L('Select')) + '">' : '') + '</td>' +
            '<td><a class="exception--view text-info" href="#" data-id="' + esc(row.id) + '" data-type="' + esc(row.type) + '" data-approved="' + (row.approved ? 'true' : 'false') + '">' + esc(row.name) + '</a></td>' +
            '<td>' + esc(typeLabel(row)) + '</td>' +
            '<td data-col="owner">' + (row.owner_name ? esc(row.owner_name) : '<span class="sr-cell-dash">&mdash;</span>') + '</td>' +
            '<td data-col="framework_control">' + frameworkControlHtml(row) + '</td>' +
            '<td>' + lifecyclePillHtml(row) + '</td>' +
            '<td>' + approvalPillHtml(row) + '</td>' +
            '<td data-col="associated_risks">' + associatedRisksHtml(row) + '</td>' +
            '<td data-col="next_review">' + reviewChipHtml(row) + '</td>' +
            '<td class="sr-actions-col">' + rowActionsHtml(row) + '</td>' +
        '</tr>';
    }

    function theadHtml() {
        var showCheckbox = perms.canApprove || perms.canDelete;
        return '<tr>' +
            '<th class="sr-check-col">' + (showCheckbox ? '<input type="checkbox" class="form-check-input" id="exceptions-select-all" aria-label="' + esc(L('SelectAll')) + '">' : '') + '</th>' +
            '<th>' + esc(L('ExceptionName')) + '</th>' +
            '<th>' + esc(L('Type')) + '</th>' +
            '<th data-col="owner">' + esc(L('Owner')) + '</th>' +
            '<th data-col="framework_control">' + esc(L('FrameworkControl')) + '</th>' +
            '<th>' + esc(L('ExceptionStatus')) + '</th>' +
            '<th>' + esc(L('ApprovalStatus')) + '</th>' +
            '<th data-col="associated_risks">' + esc(L('AssociatedRisks')) + '</th>' +
            '<th data-col="next_review">' + esc(L('NextReviewDate')) + '</th>' +
            '<th class="sr-actions-col"><span class="visually-hidden">' + esc(L('Actions')) + '</span></th>' +
        '</tr>';
    }

    // Move DataTables' own generated search box up into the toolbar tools
    // (left of the Columns picker / Add button) -- same relocation
    // governance-documents.js's relocateSearchIntoTools() performs.
    function relocateSearchIntoTools() {
        var $filter = $('#exceptions-body .dt-search, #exceptions-body .dataTables_filter').first();
        var $tools = $('#exceptions-toolbar .sr-table-tools');
        if ($filter.length && $tools.length) {
            $filter.find('input[type="search"]').attr('placeholder', L('SearchExceptionsPlaceholder')).attr('aria-label', L('SearchExceptionsPlaceholder'));
            $tools.prepend($filter);
        }
    }

    function showEmptyState(kind) {
        $('#exceptions-empty-nodata, #exceptions-empty-noresults, #exceptions-empty-error').addClass('d-none');
        if (kind) {
            $('#exceptions-empty-' + kind).removeClass('d-none');
        }
    }

    function updateBulkBar() {
        var $bar = $('#exceptions-bulk-bar');
        if (!$bar.length) {
            return;
        }
        var n = Object.keys(selectedIds).length;
        var $toolbar = $('#exceptions-toolbar');
        if (n > 0) {
            $('#exceptions-bulk-count').text(String(L('NSelected')).replace('{n}', n));
            $bar.removeClass('d-none');
            $toolbar.hide();
        } else {
            $bar.addClass('d-none');
            $toolbar.show();
        }
    }

    function syncCheckboxes() {
        $('#exceptions-table .sr-row-check').each(function () {
            this.checked = !!selectedIds[$(this).data('id')];
        });
        var $all = $('#exceptions-select-all');
        if ($all.length) {
            var $visible = $('#exceptions-table tbody tr:visible .sr-row-check');
            var total = $visible.length;
            var checked = $visible.filter(function () { return this.checked; }).length;
            $all.prop('checked', total > 0 && checked === total);
            $all.prop('indeterminate', checked > 0 && checked < total);
        }
    }

    // Swaps DataTables' own bundled up/down-caret sort indicator
    // (.dt-column-order's :before/:after pseudo-elements) for the same
    // FontAwesome 'fa-sort'/'fa-arrow-up-short-wide'/'fa-arrow-down-wide-short'
    // glyph Compliance's tables use (compliance-initiate-audits.js's
    // syncSortIcons(), compliance.php's syncAuditsSortIcons()), so this grid
    // reads the same sort affordance. 'sr-sort-native-off' is what
    // _tables.scss keys the native-caret suppression off (a class on the
    // element itself, not an ID-scoped ancestor selector) -- this table has
    // no scrollX header clone to worry about, unlike Manage Audits/Initiate
    // Audits, so there is only ever one header to sync.
    function syncSortIcons() {
        $('#exceptions-table thead th[data-dt-column]').each(function () {
            var $th = $(this);
            if (!$th.is('.dt-orderable-asc, .dt-orderable-desc')) { return; }
            var $order = $th.find('.dt-column-order').addClass('sr-sort-native-off');
            if (!$order.length) { return; }
            $th.addClass('sr-sortable');
            var $icon = $order.find('.sr-sort-icon');
            if (!$icon.length) {
                $icon = $('<i>', { 'class': 'fa sr-sort-icon', 'aria-hidden': 'true' }).appendTo($order);
            }
            var sort = $th.attr('aria-sort');
            $th.toggleClass('is-sorted', sort === 'ascending' || sort === 'descending');
            $icon
                .removeClass('fa-arrow-up-short-wide fa-arrow-down-wide-short fa-sort')
                .addClass(sort === 'ascending' ? 'fa-arrow-up-short-wide' : sort === 'descending' ? 'fa-arrow-down-wide-short' : 'fa-sort');
        });
    }

    function wireSelection(tableEl) {
        selectedIds = {};
        updateBulkBar();
        $(tableEl).on('change', '.sr-row-check', function () {
            var id = $(this).data('id');
            if (this.checked) {
                selectedIds[id] = true;
            } else {
                delete selectedIds[id];
            }
            syncCheckboxes();
            updateBulkBar();
        });
    }

    // Applies the current columnVisible state to the rendered table's
    // data-col cells/headers -- called after every render since renderBody()
    // rebuilds the table from scratch (same full-rebuild-per-load shape
    // governance-documents.js uses).
    function applyColumnVisibility() {
        TOGGLE_COLUMNS.forEach(function (col) {
            $('#exceptions-table [data-col="' + col + '"]').toggle(!!columnVisible[col]);
        });
    }

    function renderColpanel() {
        var html = TOGGLE_COLUMNS.map(function (col) {
            return '<label class="colpanel-item"><input type="checkbox" data-col="' + esc(col) + '"' + (columnVisible[col] ? ' checked' : '') + '> ' + esc(columnLabel(col)) + '</label>';
        }).join('');
        $('#exceptions-colpanel').html(html);
    }

    function renderBody(rows) {
        currentRows = rows || [];

        var html =
            '<div class="sr-table-scroll">' +
                '<table class="sr-table" id="exceptions-table">' +
                    '<thead>' + theadHtml() + '</thead>' +
                    '<tbody>' + currentRows.map(rowHtml).join('') + '</tbody>' +
                '</table>' +
            '</div>' +
            '<div class="sr-table-empty d-none" id="exceptions-empty-nodata">' +
                '<div class="sr-table-empty-icon"><i class="fa fa-file-shield" aria-hidden="true"></i></div>' +
                '<div class="sr-table-empty-title">' + esc(L('NoExceptionsYet')) + '</div>' +
                '<div class="sr-table-empty-body">' + esc(L('NoExceptionsYetBody')) + '</div>' +
            '</div>' +
            '<div class="sr-table-empty d-none" id="exceptions-empty-noresults">' +
                '<div class="sr-table-empty-icon"><i class="fa fa-search" aria-hidden="true"></i></div>' +
                '<div class="sr-table-empty-title">' + esc(L('NoExceptionsMatchFilters')) + '</div>' +
                '<div class="sr-table-empty-body">' + esc(L('NoExceptionsMatchFiltersBody')) + '</div>' +
                '<div class="sr-table-empty-action"><button type="button" class="btn btn-outline-secondary btn-sm" id="exceptions-clear-search">' + esc(L('ClearFilters')) + '</button></div>' +
            '</div>';

        $('#exceptions-body').html(html);

        var $count = $('#exceptions-count');
        if (currentRows.length > 0) {
            $count.text(currentRows.length).removeClass('d-none');
        } else {
            $count.addClass('d-none');
        }

        dt = null;
        if (!currentRows.length) {
            showEmptyState('nodata');
            $('#exceptions-bulk-bar').addClass('d-none');
            $('#exceptions-toolbar').show();
            return;
        }
        showEmptyState(null);
        applyColumnVisibility();

        if (!$.fn.DataTable) {
            return;
        }
        var tableEl = document.getElementById('exceptions-table');
        dt = $(tableEl).DataTable({
            serverSide: false,
            processing: false,
            dom: 'rt<"sr-table-foot"<"sr-table-foot-left"li><"sr-table-foot-right"p>>f',
            pagingType: 'simple_numbers',
            pageLength: 25,
            // Matches Compliance's page-size pill wording ("Show 25") --
            // DataTables' own built-in default is '_MENU_ _ENTRIES_ per page',
            // which renders as a second, separate label inside the same
            // .dt-length pill and widens it unnecessarily.
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, L('All')]],
            order: [[8, 'asc']],
            columnDefs: [{ orderable: false, targets: [0, 9] }],
            // Seeds the search box from ?search= (a bookmarked/shared link
            // lands already filtered) -- see the search.dt handler below,
            // which keeps the URL in sync as the user types.
            search: { search: new URLSearchParams(window.location.search).get('search') || '' },
            language: {
                paginate: { previous: L('Previous'), next: L('Next') },
                lengthMenu: L('Show') + ' _MENU_'
            }
        });
        relocateSearchIntoTools();

        dt.on('draw', function () {
            showEmptyState(dt.rows({ search: 'applied' }).count() === 0 ? 'noresults' : null);
            syncCheckboxes();
            syncSortIcons();
        });
        // Keeps the current search shareable/bookmarkable. history.replaceState
        // (not pushState) -- typing in the search box refines the same view,
        // not a new page in browser history.
        dt.on('search.dt', function () {
            if (!window.history.replaceState) {
                return;
            }
            var params = new URLSearchParams(window.location.search);
            var term = dt.search();
            if (term) { params.set('search', term); } else { params.delete('search'); }
            var query = params.toString();
            window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
        });
        syncSortIcons();

        wireSelection(tableEl);
    }

    function decorateOptionCounts($select, counts) {
        $select.find('option').each(function () {
            var value = $(this).attr('value');
            if (value === '') {
                return;
            }
            var count = counts[value] || 0;
            $(this).attr('data-count', count);
        });
        if (window.srSelectRender) {
            window.srSelectRender($select);
        }
    }

    // Real "All ..." option mutual-exclusion for a multi-select: picking
    // "All" clears every other checked value; picking a specific value
    // drops "All"; nothing checked falls back to ['']. See
    // governance-documents.js's identical helper for the sr-select.js
    // DOM-detach gotcha this works around (racing its own click handler,
    // which rebuilds every option row as part of its own toggle).
    function normalizeMultiValue($select, previous) {
        var current = $select.val() || [];
        var hadAll = previous.indexOf('') !== -1;
        var hasAll = current.indexOf('') !== -1;
        var result;
        if (hasAll && !hadAll) {
            result = [''];
        } else if (current.length > 1 && hasAll) {
            result = current.filter(function (v) { return v !== ''; });
        } else if (current.length === 0) {
            result = [''];
        } else {
            result = current;
        }
        $select.val(result);
        if (window.srSelectRender) {
            window.srSelectRender($select);
        }
        previous.length = 0;
        Array.prototype.push.apply(previous, result);
        return result;
    }

    function readFiltersFromControls() {
        filters.type = normalizeMultiValue($('#exceptions-type-filter'), previousTypeValue).filter(function (v) { return v !== ''; });
        filters.framework = normalizeMultiValue($('#exceptions-framework-filter'), previousFrameworkValue).filter(function (v) { return v !== ''; });
        filters.control = normalizeMultiValue($('#exceptions-control-filter'), previousControlValue).filter(function (v) { return v !== ''; });
        filters.risk = normalizeMultiValue($('#exceptions-risk-filter'), previousRiskValue).filter(function (v) { return v !== ''; });
        filters.state = $('#exceptions-state-filter').val() || '';
        filters.status = $('#exceptions-status-filter').val() || '';
    }

    // A row passes when it matches every ACTIVE facet (AND across facets,
    // OR within a multi-select facet's own chosen values) -- same semantics
    // governance-documents.js's rowPassesFilters() documents.
    function rowPassesFilters(row) {
        if (filters.type.length && filters.type.indexOf(row.type) === -1) {
            return false;
        }
        if (filters.framework.length && filters.framework.indexOf(row.framework_name) === -1) {
            return false;
        }
        // Control is only meaningful for type='control' rows -- a policy
        // exception's parent_name is the POLICY it's attached to, not a
        // control, so it can never match a Control filter pick.
        if (filters.control.length && (row.type !== 'control' || filters.control.indexOf(row.parent_name) === -1)) {
            return false;
        }
        if (filters.state && String(row.status_value) !== filters.state) {
            return false;
        }
        if (filters.status && (row.approved ? '1' : '0') !== filters.status) {
            return false;
        }
        if (filters.risk.length) {
            var subjects = row.associated_risk_subjects || [];
            var matches = filters.risk.some(function (r) { return subjects.indexOf(r) !== -1; });
            if (!matches) {
                return false;
            }
        }
        if (filters.review && row.next_review_status !== filters.review) {
            return false;
        }
        return true;
    }

    // Builds the Type/Framework/Control/Risk <option> lists from whatever's
    // actually in allRows -- State/Status are fixed 2-value enums, built
    // once here too (their options never depend on the data, only their
    // COUNTS would, and this page doesn't do the faceted/cross-filtered
    // count refinement governance-documents.js's own filters got -- every
    // option here is built from the unfiltered allRows, same simpler
    // baseline that page started from). Called once per fetch AND on every
    // filter change (the roster is stable; re-running this is cheap and
    // keeps data-count chips honest against the CURRENT allRows).
    function renderFilterOptions() {
        var typeCounts = {};
        var frameworkCounts = {};
        var controlCounts = {};
        var riskCounts = {};

        allRows.forEach(function (row) {
            if (row.type) { typeCounts[row.type] = (typeCounts[row.type] || 0) + 1; }
            if (row.framework_name) { frameworkCounts[row.framework_name] = (frameworkCounts[row.framework_name] || 0) + 1; }
            if (row.type === 'control' && row.parent_name) { controlCounts[row.parent_name] = (controlCounts[row.parent_name] || 0) + 1; }
            (row.associated_risk_subjects || []).forEach(function (subject) {
                if (subject) { riskCounts[subject] = (riskCounts[subject] || 0) + 1; }
            });
        });

        var $typeFilter = $('#exceptions-type-filter');
        $typeFilter.html(
            '<option value=""' + (filters.type.length === 0 ? ' selected' : '') + '>' + esc(L('AllTypes')) + '</option>' +
            ['policy', 'control'].filter(function (t) { return typeCounts[t]; }).map(function (t) {
                return '<option value="' + esc(t) + '"' + (filters.type.indexOf(t) !== -1 ? ' selected' : '') + '>' + esc(L(t === 'policy' ? 'Policy' : 'Control')) + '</option>';
            }).join('')
        );
        decorateOptionCounts($typeFilter, typeCounts);

        var frameworkNames = Object.keys(frameworkCounts).sort();
        var $frameworkFilter = $('#exceptions-framework-filter');
        $frameworkFilter.html(
            '<option value=""' + (filters.framework.length === 0 ? ' selected' : '') + '>' + esc(L('AllFrameworks')) + '</option>' +
            frameworkNames.map(function (name) {
                return '<option value="' + esc(name) + '"' + (filters.framework.indexOf(name) !== -1 ? ' selected' : '') + '>' + esc(name) + '</option>';
            }).join('')
        );
        decorateOptionCounts($frameworkFilter, frameworkCounts);

        var controlNames = Object.keys(controlCounts).sort();
        var $controlFilter = $('#exceptions-control-filter');
        $controlFilter.html(
            '<option value=""' + (filters.control.length === 0 ? ' selected' : '') + '>' + esc(L('AllControls')) + '</option>' +
            controlNames.map(function (name) {
                return '<option value="' + esc(name) + '"' + (filters.control.indexOf(name) !== -1 ? ' selected' : '') + '>' + esc(name) + '</option>';
            }).join('')
        );
        decorateOptionCounts($controlFilter, controlCounts);

        // State: the STABLE numeric value (1=Open/2=Closed by default, same
        // convention the lifecycle pill uses), but the LABEL always comes
        // from the row's own `status` text -- document_exceptions_status is
        // customer-editable via Add/Remove Values, never a hardcoded lang
        // key (see governance-exceptions.js's own LIFECYCLE_PILL_CLASS_BY_VALUE
        // comment). Falls back to the raw value string if no row currently
        // carries that state (a customer-renamed/added state with zero
        // exceptions today still needs SOME label to show).
        var stateLabels = {};
        allRows.forEach(function (row) {
            if (row.status_value !== undefined && row.status) {
                stateLabels[row.status_value] = row.status;
            }
        });
        var $stateFilter = $('#exceptions-state-filter');
        var stateHtml = '<option value=""' + (filters.state === '' ? ' selected' : '') + '>' + esc(L('AllStates')) + '</option>';
        Object.keys(stateLabels).forEach(function (value) {
            stateHtml += '<option value="' + esc(value) + '"' + (filters.state === String(value) ? ' selected' : '') + '>' + esc(stateLabels[value]) + '</option>';
        });
        $stateFilter.html(stateHtml);

        var $statusFilter = $('#exceptions-status-filter');
        $statusFilter.html(
            '<option value=""' + (filters.status === '' ? ' selected' : '') + '>' + esc(L('AllStatuses')) + '</option>' +
            '<option value="1"' + (filters.status === '1' ? ' selected' : '') + '>' + esc(L('Approved')) + '</option>' +
            '<option value="0"' + (filters.status === '0' ? ' selected' : '') + '>' + esc(L('Pending')) + '</option>'
        );

        var riskNames = Object.keys(riskCounts).sort();
        var $riskFilter = $('#exceptions-risk-filter');
        $riskFilter.html(
            '<option value=""' + (filters.risk.length === 0 ? ' selected' : '') + '>' + esc(L('AllRisks')) + '</option>' +
            riskNames.map(function (name) {
                return '<option value="' + esc(name) + '"' + (filters.risk.indexOf(name) !== -1 ? ' selected' : '') + '>' + esc(name) + '</option>';
            }).join('')
        );
        decorateOptionCounts($riskFilter, riskCounts);

        if (window.srSelectRender) {
            window.srSelectRender($typeFilter);
            window.srSelectRender($frameworkFilter);
            window.srSelectRender($controlFilter);
            window.srSelectRender($stateFilter);
            window.srSelectRender($statusFilter);
            window.srSelectRender($riskFilter);
        }
    }

    function activeFilterCount() {
        var n = 0;
        if (filters.type.length) { n++; }
        if (filters.framework.length) { n++; }
        if (filters.control.length) { n++; }
        if (filters.state) { n++; }
        if (filters.status) { n++; }
        if (filters.risk.length) { n++; }
        if (filters.review) { n++; }
        return n;
    }

    function syncFilterCount() {
        var count = activeFilterCount();
        $('#exceptions-filters-count').text(count ? String(count) : '').prop('hidden', !count);
        $('#exceptions-filters-toggle').toggleClass('has-filters', count > 0);
        // The multi-selects (Type/Framework/Control/Risk) have no clickable
        // "All X" row of their own -- see governance-documents.js's
        // identical comment. This button is the actual clear affordance,
        // shown only once a filter is actually narrowing the grid.
        $('#exceptions-filters-clear').toggleClass('d-none', count === 0);
    }

    // Split from readFiltersFromControls() so a caller that already knows
    // the CORRECT `filters` state (fetchAndRender()'s URL-seeded first
    // render, the Clear-filters handler's reset) can render without first
    // overwriting it from the DOM -- the filter <select>s are still empty
    // (no <option>s yet) on that first render, so reading them there would
    // silently wipe out whatever `filters` was just set to.
    function renderFilteredGrid() {
        renderFilterOptions();
        syncFilterCount();
        renderBody(allRows.filter(rowPassesFilters));
    }

    function applyFiltersAndRender() {
        readFiltersFromControls();
        renderFilteredGrid();
    }

    function renderError() {
        $('#exceptions-body').html(
            '<div class="sr-table-empty sr-table-empty-danger" id="exceptions-empty-error">' +
                '<div class="sr-table-empty-icon"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i></div>' +
                '<div class="sr-table-empty-title">' + esc(L('CouldNotLoadExceptions')) + '</div>' +
                '<div class="sr-table-empty-body">' + esc(L('CouldNotLoadExceptionsBody')) + '</div>' +
                '<div class="sr-table-empty-action"><button type="button" class="btn btn-outline-secondary btn-sm" id="exceptions-retry">' + esc(L('Retry')) + '</button></div>' +
            '</div>'
        );
        $('#exceptions-count').addClass('d-none');
        $('#exceptions-bulk-bar').addClass('d-none');
        $('#exceptions-toolbar').show();
        dt = null;
    }

    // Fetches the three partitions (policy+approved, control+approved,
    // both-types+unapproved) that together cover every exception with no
    // overlap, and merges them into one flat row set -- see the file banner
    // comment for why this isn't a single "give me everything" request.
    function fetchAndRender() {
        var types = ['policy', 'control', 'unapproved'];
        var requests = types.map(function (type) {
            return $.ajax({
                type: 'GET',
                url: BASE_URL + '/api/v2/exceptions/tree?type=' + encodeURIComponent(type),
                dataType: 'json'
            });
        });

        $.when.apply($, requests).done(function () {
            var responses = requests.length === 1 ? [arguments] : Array.prototype.slice.call(arguments);
            var rows = [];
            responses.forEach(function (response) {
                var body = response[0];
                rows = rows.concat((body && body.data) || []);
            });
            allRows = rows;
            // Seeds `filters` from a bookmarked/shared link (the KPI insights
            // band's own tiles link here with ?type=/?status=/?review=) on
            // the FIRST load only -- reload() (after an approve/delete/etc.)
            // must NOT re-apply the URL every time, or a user's own
            // mid-session filter pick would keep getting silently reset back
            // to it. Goes straight to renderFilteredGrid(), bypassing
            // readFiltersFromControls(): the filter <select>s have no
            // <option>s yet on this first render, so reading them here would
            // wipe the seed before it's ever used (see renderFilteredGrid()'s
            // own comment).
            if (!urlFiltersConsumed) {
                urlFiltersConsumed = true;
                var urlParams = new URLSearchParams(window.location.search);
                var urlType = urlParams.get('type');
                if (urlType === 'policy' || urlType === 'control') {
                    filters.type = [urlType];
                    previousTypeValue = [urlType];
                }
                var urlStatus = urlParams.get('status');
                if (urlStatus === 'pending') {
                    filters.status = '0';
                } else if (urlStatus === 'approved') {
                    filters.status = '1';
                }
                var urlReview = urlParams.get('review');
                if (urlReview === 'overdue' || urlReview === 'due_soon') {
                    filters.review = urlReview;
                }
                renderFilteredGrid();
            } else {
                applyFiltersAndRender();
            }
        }).fail(function (xhr, status, error) {
            if (!retryCSRF(xhr, this)) {
                renderError();
            }
        });
    }

    function reload() {
        fetchAndRender();
    }

    function openApproveConfirm(mode, ids, name) {
        if (!ids || !ids.length) {
            return;
        }
        approveModalMode = mode;
        approveModalIds = ids;
        var title, body, primaryLabel, iconClass;
        if (mode === 'unapprove') {
            title = L('UnapproveExceptionQuestion');
            body = String(L('UnapproveExceptionConfirmBody')).replace('{name}', name || '');
            primaryLabel = L('Unapprove');
            iconClass = 'fa fa-rotate-left';
        } else if (mode === 'batch-approve') {
            title = String(L('ApproveSelectedExceptionsQuestion')).replace('{n}', ids.length);
            body = String(L('ApproveSelectedExceptionsConfirmBody')).replace('{n}', ids.length);
            primaryLabel = L('ApproveSelected');
            iconClass = 'fa fa-check-double';
        } else {
            title = L('ApproveExceptionQuestion');
            body = String(L('ApproveExceptionConfirmBody')).replace('{name}', name || '');
            primaryLabel = L('Approve');
            iconClass = 'fa fa-check';
        }
        $('#exception-approve-confirm-icon').html('<i class="' + iconClass + '" aria-hidden="true"></i>');
        $('#exception-approve-confirm-title').text(title);
        $('#exception-approve-confirm-body').text(body);
        $('#exception-approve-confirm-yes').text(primaryLabel);
        $('#exception-approve-confirm').modal('show');
    }

    function openDeleteConfirm(ids, name) {
        if (!ids || !ids.length) {
            return;
        }
        deleteModalIds = ids;
        var body = ids.length > 1 ? L('AreYouSureYouWantToDeleteTheseExceptions') :
            String(L('AreYouSureYouWantToDeleteThisException'));
        var title = ids.length > 1 ? L('DeleteSelected') : (name || L('Delete'));
        $('#exception-delete-confirm-title').text(title);
        $('#exception-delete-confirm-body').text(body);
        $('#exception-delete-confirm').modal('show');
    }

    function init() {
        var $grid = $('#exceptions-grid');
        perms = {
            canEdit: $grid.data('can-edit') === true,
            canDelete: $grid.data('can-delete') === true,
            canApprove: $grid.data('can-approve') === true
        };

        renderColpanel();

        // Row-actions compact-tier disclosure (design-system.md §6/§6b), shared
        // with Manage Audits/Define Tests/Governance Frameworks via
        // js/simplerisk/sr-row-actions-menu.js. Delegated from the grid itself
        // (not the rebuilt tbody), so one bind() survives every load()/reload.
        SRRowActionsMenu.bind({
            container: $grid,
            scope: $grid,
            namespace: 'exceptions'
        });

        // Columns picker (design-system.md §6c): the .filterbtn trigger toggles
        // the .colpanel dropdown; a checkbox change toggles that column's
        // data-col cells/headers. Click-outside (any click that didn't land in
        // the .colpicker wrapper) closes the panel.
        $(document).on('click', '#exceptions-colpicker-btn', function (e) {
            e.stopPropagation();
            $('#exceptions-colpanel').toggleClass('d-none');
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.colpicker').length) {
                $('#exceptions-colpanel').addClass('d-none');
            }
        });
        $(document).on('change', '#exceptions-colpanel input[type="checkbox"]', function () {
            var col = $(this).data('col');
            columnVisible[col] = this.checked;
            applyColumnVisibility();
        });

        // Filters row (design-system.md §6b/§6c).
        $(document).on('change', '#exceptions-type-filter, #exceptions-framework-filter, #exceptions-control-filter, #exceptions-state-filter, #exceptions-status-filter, #exceptions-risk-filter', function () {
            applyFiltersAndRender();
        });

        $('#exceptions-filters-clear').on('click', function () {
            filters = { type: [], framework: [], control: [], state: '', status: '', risk: [], review: '' };
            previousTypeValue = [];
            previousFrameworkValue = [];
            previousControlValue = [];
            previousRiskValue = [];
            renderFilteredGrid();
        });

        // Narrow-width filter sheet (design-system.md §6b) -- inert at full
        // width, where the row is simply on screen (matches
        // governance-documents.js's #document-program-filters-toggle
        // exactly).
        $(document).on('click', '#exceptions-filters-toggle', function () {
            var $toggle = $(this);
            var open = $('#exceptions-quickfilters').toggleClass('is-open').hasClass('is-open');
            $toggle
                .attr('aria-expanded', open ? 'true' : 'false')
                .attr('title', open ? (L('HideFilters') || 'Hide filters') : (L('ShowFilters') || 'Show filters'));
        });

        if (window.srSelectEnhance) {
            window.srSelectEnhance($('#exceptions-type-filter'), L('AllTypes'));
            window.srSelectEnhance($('#exceptions-framework-filter'), L('AllFrameworks'));
            window.srSelectEnhance($('#exceptions-control-filter'), L('AllControls'));
            window.srSelectEnhance($('#exceptions-state-filter'));
            window.srSelectEnhance($('#exceptions-status-filter'));
            window.srSelectEnhance($('#exceptions-risk-filter'), L('AllRisks'));
        }

        // Row-level approve/unapprove -- opens the shared confirm modal.
        $(document).on('click', '.exception--approve', function (e) {
            e.preventDefault();
            openApproveConfirm('approve', [$(this).data('id')], $(this).data('name'));
        });
        $(document).on('click', '.exception--unapprove', function (e) {
            e.preventDefault();
            openApproveConfirm('unapprove', [$(this).data('id')], $(this).data('name'));
        });
        $(document).on('click', '#exceptions-bulk-approve', function (e) {
            e.preventDefault();
            openApproveConfirm('batch-approve', Object.keys(selectedIds), null);
        });

        $(document).on('click', '#exception-approve-confirm-yes', function () {
            var mode = approveModalMode;
            var ids = approveModalIds;
            if (!ids || !ids.length) {
                return;
            }
            var $yes = $(this);
            $yes.prop('disabled', true);

            function onSuccess(data) {
                if (data.status_message) {
                    showAlertsFromArray(data.status_message);
                }
                $yes.prop('disabled', false);
                $('#exception-approve-confirm').modal('hide');
                reload();
            }
            function onError(xhr, status, error) {
                $yes.prop('disabled', false);
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            }

            if (mode === 'unapprove') {
                $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/exceptions/unapprove', data: { exception_id: ids[0] }, success: onSuccess, error: onError });
            } else if (mode === 'batch-approve') {
                $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/exceptions/batch-approve', data: { exception_ids: ids }, success: onSuccess, error: onError });
            } else {
                $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/exceptions/approve', data: { exception_id: ids[0] }, success: onSuccess, error: onError });
            }
        });

        // Row-level delete + bulk delete -- both funnel into the same confirm
        // modal. POST /exceptions/batch-delete (the existing endpoint) deletes
        // every exception under ONE policy/control parent, not an arbitrary set
        // of selected ids across different parents/types -- that shape doesn't
        // fit a flat multi-select grid, so a bulk delete here loops
        // POST /exceptions/delete once per selected id instead (the same
        // "loop the single endpoint" fallback this task's brief sanctions for
        // approve when no ids-array endpoint exists).
        $(document).on('click', '.exception--delete', function (e) {
            e.preventDefault();
            openDeleteConfirm([$(this).data('id')], $(this).data('name'));
        });
        $(document).on('click', '#exceptions-bulk-delete', function (e) {
            e.preventDefault();
            openDeleteConfirm(Object.keys(selectedIds), null);
        });

        $(document).on('click', '#exception-delete-confirm-yes', function () {
            var ids = deleteModalIds;
            if (!ids || !ids.length) {
                return;
            }
            var $yes = $(this);
            $yes.prop('disabled', true);

            var rowsById = {};
            currentRows.forEach(function (row) { rowsById[row.id] = row; });

            function deleteOne(id) {
                var row = rowsById[id];
                return $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/v2/exceptions/delete',
                    data: { exception_id: id, type: row ? row.type : 'policy' }
                });
            }

            if (ids.length === 1) {
                // Single delete: surface the server's own (already-localized,
                // already-escaped) status message, same as every other single-
                // record action on this page.
                deleteOne(ids[0]).done(function (data) {
                    if (data.status_message) {
                        showAlertsFromArray(data.status_message);
                    }
                }).fail(function (xhr, status, error) {
                    if (!retryCSRF(xhr, this)) {
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }).always(function () {
                    $yes.prop('disabled', false);
                    $('#exception-delete-confirm').modal('hide');
                    selectedIds = {};
                    reload();
                });
                return;
            }

            // Bulk delete: loop the single-delete endpoint once per selected id
            // (see the file banner comment on why the existing parent-scoped
            // batch-delete endpoint doesn't fit an arbitrary multi-row
            // selection), then show ONE aggregate toast rather than one per
            // looped request. Each deleteOne() promise is wrapped in its own
            // deferred that always resolves -- $.when.apply() rejects as soon
            // as ANY input promise rejects, which would abandon the count
            // before the other requests finish; wrapping lets every request
            // complete and be tallied, so a partial failure is reported
            // honestly instead of showing the same success toast either way.
            var failedCount = 0;
            var settled = ids.map(function (id) {
                var d = $.Deferred();
                deleteOne(id)
                    .fail(function () { failedCount++; })
                    .always(function () { d.resolve(); });
                return d.promise();
            });
            $.when.apply($, settled).always(function () {
                $yes.prop('disabled', false);
                $('#exception-delete-confirm').modal('hide');
                if (failedCount > 0) {
                    showAlertFromMessage(L('SomeExceptionsNotDeleted'), false);
                } else {
                    showAlertFromMessage(L('ExceptionsDeleted'), true);
                }
                selectedIds = {};
                reload();
            });
        });

        $(document).on('change', '#exceptions-select-all', function () {
            var checked = this.checked;
            $('#exceptions-table tbody tr:visible .sr-row-check').each(function () {
                var id = $(this).data('id');
                if (checked) {
                    selectedIds[id] = true;
                } else {
                    delete selectedIds[id];
                }
            });
            syncCheckboxes();
            updateBulkBar();
        });

        $(document).on('click', '#exceptions-bulk-clear', function () {
            selectedIds = {};
            syncCheckboxes();
            updateBulkBar();
        });

        $(document).on('click', '#exceptions-retry', function () {
            reload();
        });
        $(document).on('click', '#exceptions-clear-search', function () {
            if (dt) {
                dt.search('').draw();
            }
        });

        reload();
    }

    return { init: init, reload: reload };
})(jQuery);
