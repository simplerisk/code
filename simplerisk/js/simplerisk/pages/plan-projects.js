/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Plan Projects (SR-2229): the priority table + unassigned queue.
 * Client-side DataTables over GET /api/v2/management/projects; every string
 * in the payload is already HTML-escaped by the server, so cell renderers
 * inject it as-is and escape only what THIS file builds (labels, attrs).
 *
 * Severity pill markup/paint and debounce() are copied from
 * js/simplerisk/pages/review-risk.js (renderRiskLevelPill()'s pill shape /
 * paintColorPills() / debounce()) rather than reused via a shared module --
 * review-risk.js doesn't export them and this page has its own #plan-projects
 * scoping, so the controller ruling for this task pinned a local copy,
 * renamed/rescoped to #plan-projects-card, over inventing a new shared file.
 *
 * The Columns picker (renderColpanel / filterColpanelItems /
 * resetColpanelSearch / applyColumnVisibility / syncSortIcons + the colpicker
 * click bindings) is likewise copied verbatim from review-risk.js per this
 * task's brief, with #review-risk-* / #review_risk_table swapped for
 * #plan-projects-* / #plan_projects_table and COLUMN_GROUPS collapsed to the
 * single ungrouped PlanProjects.TOGGLE_COLUMNS list this page uses (no
 * Risk/Mitigation/Review sections here).
 */
var PlanProjects = {
    state: {
        status: '1',          // current chip: '1'..'4' | 'all'
        rows: [],
        counts: {},
        columnSettings: null, // {columns: [[key,'1'|'0']...], order: [...]} | null
        activeColumns: null,  // Customization vocabulary | null
        filterOptions: { consultants: [], business_owners: [], data_classifications: [] },
        showAll: false,
        table: null,
        selected: {},         // project id -> true
        can: { add: false, manage: false, delete: false, modifyRisks: false, closeRisks: false },
    },
    loadSeq: 0,       // load(): ignore a stale /api/v2/management/projects response
    unassigned: { rows: [], table: null, selected: {}, showAll: false, loadSeq: 0 },

    esc: function (s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    },
    escAttr: function (s) { return PlanProjects.esc(s); },
    fmt: function (key, n) { return String(L(key)).replace('{n}', n); },

    ajaxError: function (xhr) {
        if (!retryCSRF(xhr, this)) {
            if (xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message);
            } else {
                showAlertFromMessage(L('RequestFailed'), false);
            }
        }
    },

    init: function () {
        var $card = $('#plan-projects-card');
        this.state.can = {
            add: $card.attr('data-can-add') === 'true',
            manage: $card.attr('data-can-manage') === 'true',
            delete: $card.attr('data-can-delete') === 'true',
            modifyRisks: $card.attr('data-can-modify-risks') === 'true',
            closeRisks: $card.attr('data-can-close-risks') === 'true',
        };
        this.saveColumnsDebounced = debounce(function () { PlanProjects.saveColumns(); }, 500);
        // Re-pin open drawer panels whenever the grid's scroller changes
        // width -- a window resize, but also the sidebar collapsing, which
        // resizes the content area without any window event. Observing the
        // scroller's own box fires after layout settles, where a debounced
        // resize handler measured mid-reflow (11px short) in testing.
        var scroller = document.querySelector('#plan-projects-grid .sr-table-scroll');
        if (scroller && window.ResizeObserver) {
            new ResizeObserver(function () { PlanProjects.sizeDrawerPanels(); }).observe(scroller);
        } else {
            $(window).on('resize', debounce(function () { PlanProjects.sizeDrawerPanels(); }, 150));
        }
        this.bindChips();
        this.bindToolbar();
        this.bindDrawerActions();
        this.bindRowActions();
        this.bindProjectModals();
        this.bindStatusModal();
        this.bindDeleteModal();
        this.bindSelection();
        this.bindAssignModal();   // Task 11
        // Modal datepicker inputs -- ported from the deleted plan-project.js's
        // page-ready handler, which ran this globally for the whole page.
        $('.datepicker').initAsDatePicker();
        // Customization's per-project custom-field multiselects -- ditto,
        // ported from the old page's inline <script> (not plan-project.js
        // itself). Only initialized when Customization actually rendered
        // multi-value custom fields into the Add/Edit modals.
        var $customMultiselects = $("select.multiselect[name^='custom_field[']");
        if ($customMultiselects.length) {
            $customMultiselects.multiselect({ buttonWidth: '100%', enableFiltering: true, enableCaseInsensitiveFiltering: true });
        }
        // The status chip is bookmarkable: ?status=1..4|all|unassigned selects
        // it on load (default Active), and setStatus() writes it back.
        var initial = this.statusFromUrl() || '1';
        this.setStatus(initial);
        // Each mode's load also fills the OTHER mode's chip count(s): the
        // projects load carries the per-status counts and the queue load the
        // Unassigned count. setStatus() only ran the active mode's load.
        if (initial === 'unassigned') { this.load(); } else { this.loadUnassigned(); }
    },

    load: function () {
        var self = this;
        var seq = ++self.loadSeq;
        $.ajax({
            url: BASE_URL + '/api/v2/management/projects',
            type: 'GET',
            data: { status: self.state.status },
            dataType: 'json',
            success: function (json) {
                if (seq !== self.loadSeq) { return; } // a newer load() superseded this response
                self.state.rows = json.data || [];
                self.state.counts = json.counts || {};
                self.state.columnSettings = json.column_settings || null;
                self.state.activeColumns = json.active_columns || null;
                self.state.filterOptions = json.filter_options || self.state.filterOptions;
                self.state.showAll = !!json.show_all;
                self.renderCounts();
                self.renderFilterOptions();
                if (!self.state.table) {
                    self.buildTable();          // Task 8 finishes column setup before this
                } else {
                    self.state.table.clear().rows.add(self.state.rows).draw();
                }
                self.applyReorderAvailability(); // Task 11
            },
            error: function (xhr) {
                if (seq !== self.loadSeq) { return; } // a newer load() superseded this failure too
                if (!retryCSRF(xhr, this)) {
                    self.renderLoadError();
                }
            },
        });
    },

    /* ---------- chips ---------- */
    bindChips: function () {
        var self = this;
        $('#plan-projects-status-filter').on('click', '.sr-status-chip', function () {
            self.setStatus(String($(this).data('status')));
        });
    },
    STATUS_KEYS: ['1', '2', '3', '4', 'all', 'unassigned'],
    statusFromUrl: function () {
        var s = new URLSearchParams(window.location.search).get('status');
        return this.STATUS_KEYS.indexOf(s) !== -1 ? s : null;
    },
    // Mirror the chip into the query string with replaceState() -- never
    // pushState(), which would add a history entry per click (review-risk.js
    // syncUrlParams() makes the same call). The default chip is omitted so
    // the bare page URL stays canonical; other params are left alone.
    syncStatusUrl: function (status) {
        var params = new URLSearchParams(window.location.search);
        if (status === '1') { params.delete('status'); } else { params.set('status', status); }
        var qs = params.toString();
        history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
    },
    setStatus: function (status) {
        this.state.status = status;
        this.syncStatusUrl(status);
        $('#plan-projects-status-filter .sr-status-chip').removeClass('active')
            .filter('[data-status="' + status + '"]').addClass('active');
        this.state.selected = {};
        this.unassigned.selected = {};
        var queue = status === 'unassigned';
        this.setQueueMode(queue);
        if (queue) { this.loadUnassigned(); } else { this.load(); }
    },
    // The Unassigned chip swaps the card's body from the projects grid to the
    // queue of risks waiting for a project: one card, nothing below the fold.
    // Filters and the Columns picker belong to the grid, so they hide; the
    // search box drives whichever table is showing.
    setQueueMode: function (on) {
        $('#plan-projects-grid').toggleClass('d-none', on);
        $('#plan-projects-queue').toggleClass('d-none', !on);
        $('#plan-projects-filters-toggle').toggleClass('d-none', on);
        $('#plan-projects-colpicker-btn').closest('.colpicker').toggleClass('d-none', on);
        if (on) {
            $('#plan-projects-quickfilters').addClass('d-none');
            $('#plan-projects-filters-toggle').attr('aria-expanded', 'false');
        }
        var $search = $('#plan-projects-search');
        if (!this.searchPlaceholder) { this.searchPlaceholder = $search.attr('placeholder'); }
        $search.attr('placeholder', on ? L('Search') : this.searchPlaceholder).val('');
        if (this.state.table) { this.state.table.search('').draw(); }
        if (this.unassigned.table) {
            this.unassigned.table.search('').draw();
            if (on) { this.unassigned.table.columns.adjust(); }
        }
        this.syncBulkBar();
        this.syncUnassignedBulkBar();
    },
    renderCounts: function () {
        var c = this.state.counts;
        ['1', '2', '3', '4', 'all'].forEach(function (k) {
            $('#plan-projects-count-' + k).text(c[k] != null ? c[k] : '');
        });
    },

    /* ---------- toolbar: search + filters ---------- */
    bindToolbar: function () {
        var self = this;
        $('#plan-projects-search').on('input', function () {
            var t = self.state.status === 'unassigned' ? self.unassigned.table : self.state.table;
            if (t) { t.search(this.value).draw(); }
        });
        $('#plan-projects-filters-toggle').on('click', function () {
            var open = $('#plan-projects-quickfilters').toggleClass('d-none').is(':visible');
            $(this).attr('aria-expanded', open ? 'true' : 'false');
        });
        ['#plan-projects-consultant-filter', '#plan-projects-owner-filter', '#plan-projects-classification-filter', '#plan-projects-due-filter'].forEach(function (id) {
            $(id).on('change', function () { self.applyFilters(); });
        });
        $('#plan-projects-filters-clear').on('click', function () {
            $('#plan-projects-consultant-filter, #plan-projects-owner-filter, #plan-projects-classification-filter').val([]);
            $('#plan-projects-due-filter').val('');
            ['#plan-projects-consultant-filter', '#plan-projects-owner-filter', '#plan-projects-classification-filter', '#plan-projects-due-filter'].forEach(function (id) { srSelectRender($(id)); });
            self.applyFilters();
        });
        // One DataTables custom filter for the whole quick-filter set.
        // settings.nTable is a DataTables 1.x/2.x-era internal removed in
        // the datatables.net 3.0.2 this app bundles (package.json) --
        // confirmed live: settings.nTable is undefined here, so the brief's
        // `settings.nTable.id` throws a TypeError on the very first row of
        // the very first draw (during DataTable() construction itself,
        // before state.table is ever assigned), aborting the whole build.
        // new $.fn.dataTable.Api(settings) is the version-stable way to
        // reach the table's own node regardless of DataTables major --
        // same fix already applied in compliance-initiate-audits.js's
        // identical ext.search.push() for the same reason.
        $.fn.dataTable.ext.search.push(function (settings, data, index) {
            var api = new $.fn.dataTable.Api(settings);
            if (api.table().node() !== $('#plan_projects_table')[0]) { return true; }
            var row = api.row(index).data();
            return self.rowMatchesFilters(row);
        });
        $(document).on('click', '#plan-projects-empty-clear', function () { $('#plan-projects-search').val(''); $('#plan-projects-filters-clear').trigger('click'); if (self.state.table) { self.state.table.search('').draw(); } });
        $(document).on('click', '#plan-projects-empty-add', function () { $('#plan-projects-add-btn').trigger('click'); });
    },
    filterValues: function () {
        var many = function (id) { return ($(id).val() || []).map(String); };
        return {
            consultant: many('#plan-projects-consultant-filter'),
            owner: many('#plan-projects-owner-filter'),
            classification: many('#plan-projects-classification-filter'),
            due: $('#plan-projects-due-filter').val() || '',
        };
    },
    rowMatchesFilters: function (row) {
        var f = this.filterValues();
        if (f.consultant.length && f.consultant.indexOf(String(row.consultant.id)) === -1) { return false; }
        if (f.owner.length && f.owner.indexOf(String(row.business_owner.id)) === -1) { return false; }
        if (f.classification.length && f.classification.indexOf(String(row.data_classification.id)) === -1) { return false; }
        if (f.due && row.due_status !== f.due) { return false; }
        return true;
    },
    applyFilters: function () {
        var f = this.filterValues();
        var n = f.consultant.length + f.owner.length + f.classification.length + (f.due ? 1 : 0);
        $('#plan-projects-filters-count').text(n).prop('hidden', n === 0);
        $('#plan-projects-filters-clear').toggleClass('d-none', n === 0);
        if (this.state.table) { this.state.table.draw(); }
    },
    renderFilterOptions: function () {
        var self = this;
        var fill = function (id, options) {
            var $sel = $(id);
            var current = ($sel.val() || []).map(String);
            $sel.empty();
            options.forEach(function (o) {
                $('<option>', { value: o.id, text: o.name, selected: current.indexOf(String(o.id)) !== -1 }).appendTo($sel);
            });
            if ($sel.data('srSelect')) { srSelectRender($sel); } else { srSelectEnhance($sel, $sel.data('placeholder')); }
        };
        // Option text arrives escaped; $('<option>', {text}) sets textContent,
        // so unescape once to avoid showing entities.
        var plain = function (list) { return list.map(function (o) { return { id: o.id, name: $('<textarea>').html(o.name).text() }; }); };
        fill('#plan-projects-consultant-filter', plain(self.state.filterOptions.consultants || []));
        fill('#plan-projects-owner-filter', plain(self.state.filterOptions.business_owners || []));
        fill('#plan-projects-classification-filter', plain(self.state.filterOptions.data_classifications || []));
        var $due = $('#plan-projects-due-filter');
        if (!$due.data('srSelect')) { srSelectEnhance($due, $due.data('placeholder')); }
    },

    /* ---------- cell renderers (row values are pre-escaped by the server) ---------- */
    renderCheckbox: function (data, type, row) {
        return '<input type="checkbox" class="form-check-input sr-row-check" data-id="' + PlanProjects.escAttr(row.id) + '" aria-label="' + PlanProjects.escAttr(L('Select')) + '">';
    },
    // Expand caret: the shipped .sr-group-caret <span> (Define Tests' shape --
    // compliance-define-tests.js) rather than a <button>, which inherits the
    // UA's outset border/grey fill unless every page resets it. Rotation is
    // CSS: _tables.scss keys it on tr.sr-group-row[aria-expanded="true"], so
    // the ROW carries both the class and the state (toggleDrawer()).
    renderCaret: function (data, type, row) {
        return '<span class="sr-group-caret" role="button" tabindex="0" aria-label="' + PlanProjects.escAttr(L('View')) + '"><i class="fa fa-chevron-right" aria-hidden="true"></i></span>';
    },
    // Drag handle in its own first column so the grip reads as "move this
    // row", not as part of the priority number.
    renderDragHandle: function (data, type, row) {
        if (type !== 'display' || !PlanProjects.state.can.manage) { return ''; }
        return '<span class="sr-drag-handle" title="' + PlanProjects.escAttr(L('Priority')) + '"><i class="fa fa-grip-vertical" aria-hidden="true"></i></span>';
    },
    renderPriority: function (data, type, row) {
        if (type !== 'display') { return row.priority; }
        return '<span class="sr-priority-rank">' + row.priority + '</span>';
    },
    renderName: function (data, type, row) {
        if (type !== 'display') { return $('<textarea>').html(row.name).text(); }
        return '<span class="sr-project-name">' + row.name + '</span>';
    },
    renderDueDate: function (data, type, row) {
        if (type !== 'display') { return row.due_date || ''; }
        if (!row.due_date) { return '<span class="sr-cell-dash">&mdash;</span>'; }
        var label = moment(row.due_date, 'YYYY-MM-DD').format('ll');
        var family = { overdue: 'danger', due_soon: 'warning' }[row.due_status] || 'neutral';
        var prefix = row.due_status === 'overdue' ? L('Overdue') + ' · ' : '';
        return '<span class="sr-state-pill sr-state-' + family + '">' + PlanProjects.esc(prefix + label) + '</span>';
    },
    renderPerson: function (person, type) {
        if (type !== 'display') { return person && person.name ? $('<textarea>').html(person.name).text() : ''; }
        if (!person || !person.id) { return '<span class="sr-cell-dash">&mdash;</span>'; }
        var plain = $('<textarea>').html(person.name).text();
        var initials = plain.split(/\s+/).map(function (w) { return w.charAt(0); }).join('').substring(0, 2).toUpperCase();
        return '<span class="sr-avatar sr-avatar-c' + ((person.id % 6) + 1) + '">' + PlanProjects.esc(initials) + '</span>' + person.name;
    },
    renderClassification: function (data, type, row) {
        var v = row.data_classification && row.data_classification.name;
        if (type !== 'display') { return v ? $('<textarea>').html(v).text() : ''; }
        return v ? v : '<span class="sr-cell-dash">&mdash;</span>';
    },
    renderRiskCount: function (data, type, row) {
        return type === 'display' ? '<span class="sr-num">' + row.risk_count + '</span>' : row.risk_count;
    },
    renderHighestRisk: function (data, type, row) {
        var h = row.highest_risk || {};
        if (type !== 'display') { return h.score == null ? -1 : h.score; }
        if (h.score == null) { return '<span class="sr-cell-dash">&mdash;</span>'; }
        // color arrives through escapeCssColor(); level name arrives through
        // escapeHtml() (both server-side, plan_projects_risk_level_payload())
        // -- inject both as-is; escAttr()'ing h.level here would double-encode
        // an admin-named level containing '&' etc. into '&amp;amp;'.
        // Severity pill markup matches Review Risk's shipped
        // renderRiskLevelPill() shape (review-risk.js) -- .sr-level-pill does
        // not exist anywhere in the stylesheet; .sr-sev-pill/.sr-sev-pill-sm
        // does (_tables.scss) and is what paintColorPills() below targets.
        // No inline style= here -- paintColorPills() paints data-color via
        // the style PROPERTY on the table's 'draw' handler.
        return '<span class="sr-sev-pill sr-sev-pill-sm" data-color="' + h.color + '" title="' + h.level + '">' + PlanProjects.esc(h.score) + '</span>';
    },
    renderStatus: function (data, type, row) {
        var map = { 1: ['Active', 'info'], 2: ['OnHold', 'warning'], 3: ['Completed', 'success'], 4: ['Canceled', 'neutral'] };
        var m = map[row.status] || ['Active', 'neutral'];
        if (type !== 'display') { return L(m[0]); }
        return '<span class="sr-state-pill sr-state-' + m[1] + '">' + PlanProjects.esc(L(m[0])) + '</span>';
    },
    renderCustomField: function (key) {
        return function (data, type, row) {
            var v = (row.custom_fields || {})[key] || '';
            if (type !== 'display') { return v ? $('<textarea>').html(v).text() : ''; }
            return v ? v : '<span class="sr-cell-dash">&mdash;</span>';
        };
    },
    renderActions: function (data, type, row) {
        return PlanProjects.renderRowActions(row);
    },
    /* ==================== Task 11: unassigned queue, assign to project, drag enhancements ==================== */

    // Queue-first when the projects grid is empty and there is intake work.
    // load() and loadUnassigned() fire in parallel from init() -- reading
    // self.state.rows.length before load()'s response lands would see the
    // state object's [] default and treat that as "the grid is genuinely
    // empty", flipping the queue to the top on every page load regardless of
    // how many projects actually exist. Gate on self.state.table (only set
    // once buildTable() has run, i.e. load() has completed at least once)
    // and call this from BOTH loaders' success handlers so whichever
    // finishes second reconciles the ordering with up-to-date counts from
    // both sides (controller ruling for this task).
    loadUnassigned: function () {
        var self = this;
        var seq = ++self.unassigned.loadSeq;
        $.getJSON(BASE_URL + '/api/v2/management/projects/unassigned_risks', function (json) {
            if (seq !== self.unassigned.loadSeq) { return; } // a newer loadUnassigned() superseded this response
            self.unassigned.rows = json.data || [];
            self.unassigned.showAll = !!json.show_all;
            var n = self.unassigned.rows.length;
            $('#plan-projects-count-unassigned').text(n);
            $('#plan-projects-status-filter .sr-status-chip[data-status="unassigned"]').toggleClass('has-items', n > 0);
            if (!self.unassigned.table) { self.buildUnassignedTable(); } else { self.unassigned.table.clear().rows.add(self.unassigned.rows).draw(); }
            if (self.state.status === 'unassigned') { self.unassigned.table.columns.adjust(); }
        }).fail(function (xhr) {
            if (seq !== self.unassigned.loadSeq) { return; } // a newer loadUnassigned() superseded this failure too
            self.ajaxError.call(this, xhr);
        });
    },

    buildUnassignedTable: function () {
        var self = this;
        var canAssign = this.state.can.manage && this.state.can.modifyRisks;
        this.unassigned.table = $('#unassigned_risks_table').DataTable({
            data: this.unassigned.rows,
            columns: [
                { data: null, orderable: false, className: 'sr-check-col', render: function (d, t, r) { return '<input type="checkbox" class="form-check-input sr-risk-check" data-id="' + self.escAttr(r.id) + '">'; } },
                { data: 'display_id', className: 'num', render: function (d, t, r) { return t === 'display' ? '<a href="' + self.escAttr(r.view_url) + '" target="_blank" rel="noopener noreferrer">#' + self.esc(d) + '</a>' : d; } },
                { data: 'subject', render: function (d, t) { return t === 'display' ? d : $('<textarea>').html(d).text(); } },
                { data: 'calculated_risk', className: 'num', render: function (d, t, r) { return t === 'display' ? self.renderRiskPill(r) : (d == null ? -1 : d); } },
                { data: null, render: function (d, t, r) { return self.renderPerson(r.owner, t); } },
                { data: 'team', render: function (d, t) {
                    // Array of individually server-escaped names -> one .sr-chip
                    // each (Review Risk's Team column shape, minus the filter
                    // behaviour: the queue has no team filter). A risk on many
                    // teams shows the first three and a "+N" chip carrying the
                    // rest in its tooltip, so the row stays one line tall. The
                    // names are already escapeHtml()'d (quotes included), so
                    // they are safe as text and inside the title attribute.
                    var names = Array.isArray(d) ? d : [];
                    if (t !== 'display') { return names.map(function (n) { return $('<textarea>').html(n).text(); }).join(', '); }
                    if (!names.length) { return '<span class="sr-cell-dash">&mdash;</span>'; }
                    var MAX = 3;
                    var html = names.slice(0, MAX).map(function (n) { return '<span class="sr-chip">' + n + '</span>'; }).join('');
                    if (names.length > MAX) { html += '<span class="sr-chip sr-chip-more" title="' + names.slice(MAX).join(', ') + '">+' + (names.length - MAX) + '</span>'; }
                    return '<span class="sr-chip-row">' + html + '</span>';
                } },
                { data: 'reviewed', className: 'num', render: function (d, t) { return t === 'display' ? (d ? self.esc(moment(d, 'YYYY-MM-DD').format('ll')) : '<span class="sr-cell-dash">&mdash;</span>') : d; } },
                // Same hover-revealed .sr-row-actions cluster (with the compact
                // kebab overflow) the projects grid and the drawer use, rather
                // than a standing bordered button in every row: View, plus
                // Assign to project (fa-link, pairing with the drawer's
                // fa-link-slash Remove from project) for users who may assign.
                { data: null, orderable: false, className: 'sr-actions-col sr-actions-col-sticky', render: function (d, t, r) {
                    var acts = '<a class="sr-row-action" href="' + self.escAttr(r.view_url) + '" target="_blank" rel="noopener noreferrer" title="' + self.escAttr(L('View')) + '" aria-label="' + self.escAttr(L('View')) + '"><i class="fa fa-eye" aria-hidden="true"></i></a>';
                    if (canAssign) {
                        acts += '<button type="button" class="sr-row-action sr-assign-one" data-id="' + self.escAttr(r.id) + '" title="' + self.escAttr(L('AssignToProject')) + '" aria-label="' + self.escAttr(L('AssignToProject')) + '"><i class="fa fa-link" aria-hidden="true"></i></button>';
                    }
                    return '<span class="sr-row-actions-wrap"><button type="button" class="sr-row-actions-toggle" aria-expanded="false" aria-haspopup="true" aria-label="' + self.escAttr(L('Actions')) + '"><i class="fa fa-ellipsis" aria-hidden="true"></i></button><span class="sr-row-actions">' + acts + '</span></span>';
                } },
            ],
            order: [[3, 'desc']],
            pagingType: 'simple_numbers', pageLength: 10, autoWidth: false,
            serverSide: false,
            processing: false,
            rowId: function (r) { return 'unassigned-' + r.id; },
            createdRow: function (tr, r) { $(tr).attr('data-risk-id', r.id); },
            // Rows-per-page pill reads "Show [N v]" like the other redesigned
            // grids (governance-documents.js, review-risk.js); DataTables'
            // bundled "_MENU_ entries per page" template puts the <select>
            // first and adds trailing text the shared .dt-length pill
            // (_tables.scss) was not sized for.
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, L('ALL')]],
            language: { emptyTable: '', zeroRecords: '', lengthMenu: L('Show') + ' _MENU_' },
            dom: 'rt<"sr-table-foot"<"sr-table-foot-left"li><"sr-table-foot-right"p>>',
            drawCallback: function () {
                var api = this.api();
                if (api.rows({ search: 'applied' }).count() === 0) {
                    var filtered = !!$('#plan-projects-search').val();
                    $('#unassigned_risks_table tbody').html('<tr class="sr-empty-row"><td colspan="8"><div class="sr-table-empty ' + (filtered ? '' : 'sr-table-empty-success') + '"><div class="sr-table-empty-icon"><i class="fa ' + (filtered ? 'fa-filter' : 'fa-check') + '"></i></div><div class="sr-table-empty-title">' + self.esc(L(filtered ? 'NoRisksMatchYourSearch' : 'NoRisksWaitingForProject')) + '</div>' + (filtered ? '' : '<div class="sr-table-empty-body">' + self.esc(L('NoRisksWaitingForProjectHint')) + '</div>') + '</div></td></tr>');
                }
                self.paintSevPills();
                self.syncUnassignedBulkBar();
                self.bindRiskDragDrop();
            },
        });
        $('#unassigned_risks_table').on('change', '.sr-risk-check', function () { if (this.checked) { self.unassigned.selected[this.getAttribute('data-id')] = true; } else { delete self.unassigned.selected[this.getAttribute('data-id')]; } self.syncUnassignedBulkBar(); });
        $('#unassigned-risks-select-all').on('change', function () { var on = this.checked; $('#unassigned_risks_table .sr-risk-check').each(function () { if (on) { self.unassigned.selected[this.getAttribute('data-id')] = true; } else { delete self.unassigned.selected[this.getAttribute('data-id')]; } }); self.syncUnassignedBulkBar(); });
        $('#unassigned-risks-bulk-clear').on('click', function () { self.unassigned.selected = {}; self.syncUnassignedBulkBar(); });
        $('#unassigned-risks-select-all-filtered').on('click', function () {
            self.matchingUnassignedIds().forEach(function (id) { self.unassigned.selected[id] = true; });
            self.syncUnassignedBulkBar();
        });
        $('#unassigned-risks-bulk-assign').on('click', function () { self.openAssignModal(Object.keys(self.unassigned.selected).map(Number)); });
        $('#unassigned_risks_table').on('click', '.sr-assign-one', function () { self.openAssignModal([Number($(this).data('id'))]); });
        SRRowActionsMenu.bind({ container: '#unassigned_risks_table', scope: $('#unassigned_risks_table'), namespace: 'planprojectsqueue' });
    },
    syncUnassignedBulkBar: function () {
        var self = this;
        $('#unassigned_risks_table .sr-risk-check').each(function () { this.checked = !!self.unassigned.selected[this.getAttribute('data-id')]; });
        // Same header-checkbox reflection plan-projects-select-all gets in
        // syncBulkBar() above -- this one was missing it, so the header
        // checkbox stayed unchecked even once "Select all N" had ticked
        // every row (confirmed live: all 6 rows checked, header still not).
        var $checks = $('#unassigned_risks_table .sr-risk-check'), total = $checks.length, checked = $checks.filter(':checked').length;
        $('#unassigned-risks-select-all').prop('checked', total > 0 && checked === total).prop('indeterminate', checked > 0 && checked < total);
        var n = Object.keys(this.unassigned.selected).length;
        if (this.state.status !== 'unassigned') {
            $('#unassigned-risks-bulk-bar').addClass('d-none');
            return;
        }
        $('#plan-projects-toolbar').toggle(n === 0);
        $('#unassigned-risks-bulk-bar').toggleClass('d-none', n === 0);
        $('#unassigned-risks-bulk-count').text(this.fmt('NSelected', n));
        var matching = this.matchingUnassignedIds().length;
        $('#unassigned-risks-select-all-filtered').toggleClass('d-none', !(n > 0 && matching > n)).text(this.fmt('SelectAllN', matching));
    },
    matchingUnassignedIds: function () {
        if (!this.unassigned.table) { return []; }
        return this.unassigned.table.rows({ search: 'applied' }).data().toArray().map(function (r) { return String(r.id); });
    },

    pendingAssignIds: [],
    openAssignModal: function (riskIds) {
        var self = this;
        if (!riskIds.length) { return; }
        this.pendingAssignIds = riskIds;
        $.getJSON(BASE_URL + '/api/v2/management/projects', { status: '1' }, function (json) {
            var $sel = $('#assign-project-select').empty();
            $('<option>', { value: '', text: L('AssignToProject') }).appendTo($sel);
            (json.data || []).forEach(function (p) { $('<option>', { value: p.id, text: p.priority + ' · ' + $('<textarea>').html(p.name).text() }).appendTo($sel); });
            if ($sel.data('srSelect')) { srSelectRender($sel); } else { srSelectEnhance($sel, $sel.data('placeholder')); }
            $('#assign-project-modal').modal('show');
        });
    },
    bindAssignModal: function () {   // once from init()
        var self = this;
        $('#assign-project-confirm').on('click', function () {
            var projectId = Number($('#assign-project-select').val() || 0);
            if (!projectId) { return; }
            $('#assign-project-modal').modal('hide');
            self.assignRisks(self.pendingAssignIds, projectId, function (data) {
                self.unassigned.selected = {};
                self.invalidateProject(projectId);
                self.loadUnassigned();
                if (data.denied && data.denied.length) { showAlertFromMessage(L('NoPermissionForRiskManagement') + ' (' + data.denied.length + ')', false); }
            });
        });
    },

    // Whether any of the projects-grid quick filters (consultant/owner/
    // classification/due) currently narrows the row set -- used both to
    // gate priority drag (a page-relative reorder can't map onto the full
    // status list while filtered) and to decide the queue's empty-state copy.
    hasActiveQuickFilters: function () {
        var f = this.filterValues();
        return !!(f.consultant.length || f.owner.length || f.classification.length || f.due);
    },

    rowReorderBound: false,
    applyReorderAvailability: function () {
        var self = this;
        var t = this.state.table; if (!t) { return; }
        var order = t.order()[0] || [];
        var priorityIdx = t.column('priority:name').index();
        var filtered = !!t.search() || this.hasActiveQuickFilters();
        var ok = this.state.can.manage && this.state.status !== 'all' && order[0] === priorityIdx && order[1] === 'asc' && !filtered;
        $('#plan_projects_table').toggleClass('sr-reorder-off', !ok);
        $('#plan_projects_table .sr-drag-handle').attr('title', ok ? L('Priority') : L('ReorderNeedsPrioritySort'));
        // Gate the drag interaction itself (not just the visual state) via
        // RowReorder's own registered Api methods -- vendored as
        // rowReorder.enable(toggle)/rowReorder.disable(), the same
        // `<plugin>.<method>()` namespacing colReorder.order() already uses
        // above in buildTable() (controller ruling for this task).
        // t.rowReorder is an Api.register()'d method, present unconditionally
        // once the plugin script loads (not per-instance) -- no need to guard
        // its existence; .enable() itself already no-ops via its own
        // `if (ctx.rowreorder)` check when a table has no RowReorder instance.
        t.rowReorder.enable(ok);
        if (ok && !this.rowReorderBound) { this.bindRowReorder(); }
    },
    bindRowReorder: function () {
        var self = this;
        this.rowReorderBound = true;
        // RowReorder is configured at init (buildTable()) with update:false
        // and the handle selector; this only wires the event.
        this.state.table.on('row-reorder', function (e, diff) {
            // RowReorder's own event dispatch (_mouseUp() in the vendored
            // plugin) fires 'row-reorder' BEFORE it cleans up its drag
            // helper -- a full <table> clone of #plan_projects_table
            // (jQuery .clone(false) copies attributes, so the clone carries
            // the SAME id and classes as the real table) appended to <body>
            // for the duration of the drag. An id selector is not unique
            // like getElementById -- $('#plan_projects_table ...') matches
            // BOTH tables while that clone still exists, so this handler
            // must resolve the real table via the DataTables API's own node
            // reference, never a bare '#plan_projects_table' jQuery lookup
            // (confirmed live: the bare selector picked up the clone's one
            // cloned <tr>, inflating the posted id list to 155 entries with
            // the dragged row duplicated).
            var $realTable = $(self.state.table.table().node());
            if ($realTable.hasClass('sr-reorder-off') || !diff.length) { return; }
            // RowReorder 2.0.0 computes diff[].newPosition over
            // dt.rows({page:'current'}).nodes() -- it is PAGE-relative, not
            // an index into the whole status's id list (confirmed against
            // the vendored plugin's _mouseUp(): `endNodes` comes from
            // `dt.rows({page:'current'}).nodes()`). Splicing newPosition
            // into the full id list (as if it were absolute) would corrupt
            // the order on any page past the first. Controller ruling:
            // read the page's rows in their NEW DOM order (RowReorder has
            // already moved the nodes) and splice that whole page-slice
            // into the full, status-scoped id list at page.info().start.
            if (self.state.table.search() || self.hasActiveQuickFilters()) {
                // The page slice can't be mapped onto the full status list
                // while filtered/searched -- bail out and snap back.
                showAlertFromMessage(L('ReorderNeedsPrioritySort'), false);
                self.load();
                return;
            }
            var pageIds = $realTable.find('tbody > tr[data-project-id]').map(function () {
                return Number(this.getAttribute('data-project-id'));
            }).get();
            var fullIds = self.state.table.rows({ order: 'applied', search: 'none' }).data().toArray().map(function (r) { return r.id; });
            var info = self.state.table.page.info();
            Array.prototype.splice.apply(fullIds, [info.start, pageIds.length].concat(pageIds));
            $.ajax({
                url: BASE_URL + '/api/v2/management/projects/reorder', type: 'POST',
                data: { status: self.state.status, project_ids: fullIds },
                success: function (json) { if (json.status_message) { showAlertsFromArray(json.status_message); } self.load(); },
                error: function (xhr) { if (!retryCSRF(xhr, this)) { self.ajaxError.call(this, xhr); self.load(); } },
            });
        });
        this.state.table.on('order.dt', function () { self.applyReorderAvailability(); });
    },

    bindRiskDragDrop: function () {
        // Intentionally empty: the queue lives behind the Unassigned chip, so
        // a queue row and a project row are never on screen together and
        // there is nothing to drag onto. Assignment is the row action, the
        // bulk bar, or the drawer's picker. Kept as a named hook so the draw
        // handlers that call it need no change.
    },


    /* ==================== Task 9: expand drawer, remove-from-project, add-risks picker ==================== */

    // projectId -> the drawer's already-fetched risk rows. Cleared per-project
    // by invalidateProject() whenever a risk is added to or removed from that
    // project, so the NEXT open re-fetches; every other project's cached
    // drawer stays valid.
    drawerCache: {},

    // projectId -> true once the drawer's "Show all" toggle has been clicked
    // for that project (re-render with no LIMIT slice). Cleared alongside
    // drawerCache by invalidateProject() so a re-opened drawer starts
    // truncated again.
    drawerShowAll: {},
    drawerSort: {},       // project id -> {key, dir}; absent = the default (score desc)

    bindDrawerRows: function () {
        var self = this;
        $('#plan_projects_table tbody').off('click.ppdrawer keydown.ppdrawer')
            .on('click.ppdrawer', '.sr-group-caret', function (e) {
                e.preventDefault();
                self.toggleDrawer($(this).closest('tr'));
            })
            .on('keydown.ppdrawer', '.sr-group-caret', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); self.toggleDrawer($(this).closest('tr')); }
            });
    },

    toggleDrawer: function ($tr) {
        var self = this;
        var projectId = $tr.data('project-id');
        var $existing = $tr.next('tr.sr-expand-row[data-drawer-for="' + projectId + '"]');
        if ($existing.length) {
            $existing.remove();
            $tr.attr('aria-expanded', 'false');
            return;
        }
        var colspan = this.state.table.columns().count();
        var $drawer = $('<tr class="sr-expand-row" data-drawer-for="' + this.escAttr(projectId) + '"><td colspan="' + colspan + '"><div class="sr-expand-panel"><div class="sr-subtable-loading">' + this.esc(L('ProcessingPleaseWait')) + '</div></div></td></tr>');
        $tr.after($drawer);
        this.sizeDrawerPanels();
        $tr.attr('aria-expanded', 'true');
        if (this.drawerCache[projectId]) {
            this.renderDrawer(projectId, this.drawerCache[projectId]);
            return;
        }
        $.ajax({
            url: BASE_URL + '/api/v2/management/project/' + encodeURIComponent(projectId) + '/risks',
            type: 'GET', dataType: 'json',
            success: function (json) {
                self.drawerCache[projectId] = json.data || [];
                self.renderDrawer(projectId, self.drawerCache[projectId]);
            },
            error: function (xhr) { self.ajaxError.call(this, xhr); $drawer.remove(); },
        });
    },

    // Shape matches renderHighestRisk()'s shipped .sr-sev-pill markup exactly
    // (controller ruling for this task -- .sr-level-pill does not exist
    // anywhere in the stylesheet). data-color and title arrive pre-escaped
    // from the server (escapeCssColor() / escapeHtml() respectively, both in
    // plan_projects_risk_level_payload(), includes/functions.php) -- inject
    // both as-is; escAttr()'ing r.level here would double-encode an
    // admin-named level containing '&' etc. into '&amp;amp;'.
    renderRiskPill: function (r) {
        if (r.calculated_risk == null) { return '<span class="sr-cell-dash">&mdash;</span>'; }
        return '<span class="sr-sev-pill sr-sev-pill-sm" data-color="' + r.color + '" title="' + r.level + '">' + this.esc(r.calculated_risk) + '</span>';
    },
    renderMitigationPill: function (state) {
        var m = { not_planned: ['MitigationNotPlanned', 'neutral'], planned: ['MitigationStatePlanned', 'warning'], mitigated: ['Mitigated', 'success'] }[state] || ['MitigationNotPlanned', 'neutral'];
        return '<span class="sr-state-pill sr-state-' + m[1] + '">' + this.esc(L(m[0])) + '</span>';
    },
    // Planned mitigation date for a drawer/queue risk row. A date that has
    // passed on a risk that is not yet mitigated gets the same "Overdue"
    // danger pill renderDueDate() gives an overdue project; otherwise the
    // date reads as plain text. '' (no mitigation, or one saved without a
    // date) renders the shared em-dash.
    renderPlanningDate: function (r) {
        if (!r.planning_date) { return '<span class="sr-cell-dash">&mdash;</span>'; }
        var m = moment(r.planning_date, 'YYYY-MM-DD', true);
        if (!m.isValid()) { return '<span class="sr-cell-dash">&mdash;</span>'; }
        var label = m.format('ll');
        if (r.mitigation_state !== 'mitigated' && m.isBefore(moment(), 'day')) {
            return '<span class="sr-state-pill sr-state-danger">' + this.esc(L('Overdue') + ' · ' + label) + '</span>';
        }
        return this.esc(label);
    },
    renderRiskStatusPill: function (status) {
        // Statuses are customer-editable text: map by meaning (§7), default info.
        var plain = $('<textarea>').html(status).text().toLowerCase();
        var family = plain === 'closed' ? 'neutral' : /mitigat|reviewed/.test(plain) ? 'success' : /untreated|unmitigated/.test(plain) ? 'warning' : 'info';
        return '<span class="sr-state-pill sr-state-' + family + '">' + status + '</span>';
    },

    // Pin every open drawer panel to the card's visible width. The drawer
    // <td> spans the whole table, which outgrows the card once optional
    // columns push the grid into horizontal scroll; the panel is sticky-left
    // (_plan-projects.scss) and sized here to the scroller's clientWidth so
    // it neither stretches into the scrolled-away area nor scrolls with it.
    // Called when a drawer is inserted and from the ResizeObserver on the
    // scroller (init()); its clientWidth does not change with column
    // visibility, only with the content area.
    sizeDrawerPanels: function () {
        var scroller = document.querySelector('#plan-projects-grid .sr-table-scroll');
        if (!scroller) { return; }
        $('#plan_projects_table tr.sr-expand-row .sr-expand-panel').css('width', scroller.clientWidth + 'px');
    },
    renderDrawer: function (projectId, risks) {
        var self = this;
        var $panel = $('tr.sr-expand-row[data-drawer-for="' + projectId + '"] .sr-expand-panel');
        var canAssign = this.state.can.manage && this.state.can.modifyRisks;
        var addBtn = canAssign ? '<button type="button" class="btn btn-outline-secondary btn-sm sr-add-risks-btn" data-project-id="' + this.escAttr(projectId) + '">+ ' + this.esc(L('AddRisksToThisProject')) + '</button>' : '';
        if (!risks.length) {
            // Define Tests' one-line drawer empty state (.sr-empty-tests,
            // _tables.scss) -- a note plus the inline add button, aligned under
            // the caret -- rather than the full-height card empty state.
            $panel.html('<div class="sr-empty-tests"><span>' + this.esc(L('NoRisksInThisProject')) + '</span>' + addBtn + '</div>');
            return;
        }
        var LIMIT = 50;
        var showAll = !!this.drawerShowAll[projectId];
        risks = this.sortDrawerRisks(projectId, risks);
        var visibleRisks = showAll ? risks : risks.slice(0, LIMIT);
        var rows = visibleRisks.map(function (r) {
            var actions = '<a class="sr-row-action" href="' + r.view_url + '" target="_blank" rel="noopener noreferrer" title="' + self.escAttr(L('View')) + '"><i class="fa fa-eye" aria-hidden="true"></i></a>';
            if (canAssign) {
                actions += '<button type="button" class="sr-row-action sr-remove-from-project" data-risk-id="' + self.escAttr(r.id) + '" data-project-id="' + self.escAttr(projectId) + '" title="' + self.escAttr(L('RemoveFromProject')) + '"><i class="fa fa-link-slash" aria-hidden="true"></i></button>';
            }
            return '<tr data-risk-id="' + self.escAttr(r.id) + '">' +
                '<td class="num"><a href="' + r.view_url + '" target="_blank" rel="noopener noreferrer">#' + self.esc(r.display_id) + '</a></td>' +
                '<td><span class="sr-cell-truncate" title="' + r.subject + '">' + r.subject + '</span></td>' +
                '<td>' + self.renderRiskPill(r) + '</td>' +
                '<td>' + self.renderRiskStatusPill(r.status) + '</td>' +
                '<td>' + self.renderMitigationPill(r.mitigation_state) + '</td>' +
                '<td class="num">' + self.renderPlanningDate(r) + '</td>' +
                '<td class="sr-actions-col"><span class="sr-row-actions">' + actions + '</span></td></tr>';
        }).join('');
        var more = (!showAll && risks.length > LIMIT)
            ? '<div class="sr-subtable-more">' + this.esc(this.fmt('NMoreRisks', risks.length - LIMIT)) +
                ' <button type="button" class="btn btn-link btn-sm sr-subtable-show-all" data-project-id="' + this.escAttr(projectId) + '">' + this.esc(L('datatables_ShowAll')) + '</button></div>'
            : '';
        var sort = this.drawerSortState(projectId);
        var th = function (key, labelKey, cls) {
            var active = sort.key === key;
            var icon = active ? (sort.dir === 'desc' ? 'fa-arrow-down-wide-short' : 'fa-arrow-up-short-wide') : 'fa-sort';
            return '<th class="sr-sortable' + (active ? ' is-sorted' : '') + (cls ? ' ' + cls : '') + '" data-sort="' + key + '" data-project-id="' + self.escAttr(projectId) + '" role="columnheader" tabindex="0" aria-sort="' + (active ? (sort.dir === 'desc' ? 'descending' : 'ascending') : 'none') + '">' + self.esc(L(labelKey)) + '<i class="fa sr-sort-icon ' + icon + '" aria-hidden="true"></i></th>';
        };
        $panel.html('<table class="sr-subtable"><thead><tr>' + th('id', 'ID') + th('subject', 'Subject') + th('score', 'InherentRisk') + th('status', 'Status') + th('mitigation', 'Mitigation') + th('planning_date', 'MitigationPlanning', 'num') + '<th></th></tr></thead><tbody>' + rows + '</tbody></table>' +
            '<div class="sr-subtable-foot">' + more + addBtn + '</div>');
        // paintSevPills() normally runs from the table's own 'draw' handler --
        // this markup is injected outside that cycle (into a hand-built <tr>,
        // not through DataTables), so the sub-table's pills need their own
        // paint pass (controller ruling for this task).
        self.paintSevPills();
    },

    // Drawer sub-table sorting. The rows are hand-built (not a DataTable), so
    // the sort is a re-render from drawerCache: the same th.sr-sortable /
    // .sr-sort-icon / aria-sort header contract Define Tests uses, applied to
    // the cached risk array before the 50-row slice. Default is inherent
    // risk descending -- the order the server already returns.
    DRAWER_SORT_DEFAULT: { key: 'score', dir: 'desc' },
    DRAWER_SORT_FIRST_DIR: { score: 'desc' }, // everything else opens ascending
    drawerSortState: function (projectId) {
        return this.drawerSort[projectId] || this.DRAWER_SORT_DEFAULT;
    },
    toggleDrawerSort: function (projectId, key) {
        var cur = this.drawerSortState(projectId);
        var dir = cur.key === key ? (cur.dir === 'asc' ? 'desc' : 'asc') : (this.DRAWER_SORT_FIRST_DIR[key] || 'asc');
        this.drawerSort[projectId] = { key: key, dir: dir };
        this.renderDrawer(projectId, this.drawerCache[projectId] || []);
    },
    drawerSortValue: function (r, key) {
        var plain = function (html) { return $('<textarea>').html(html || '').text().toLowerCase(); };
        switch (key) {
            case 'id': return Number(r.display_id);
            case 'subject': return plain(r.subject);
            case 'score': return r.calculated_risk == null ? null : Number(r.calculated_risk);
            case 'status': return plain(r.status);
            case 'mitigation': return { not_planned: 0, planned: 1, mitigated: 2 }[r.mitigation_state] || 0;
            case 'planning_date': return r.planning_date || null; // ISO date: string order is date order
        }
        return null;
    },
    sortDrawerRisks: function (projectId, risks) {
        var self = this, sort = this.drawerSortState(projectId), sign = sort.dir === 'desc' ? -1 : 1;
        return risks.slice().sort(function (a, b) {
            var va = self.drawerSortValue(a, sort.key), vb = self.drawerSortValue(b, sort.key);
            // Empty values (no score, no planned date) sink to the bottom in
            // either direction rather than flipping to the top on desc.
            if (va == null && vb == null) { return Number(a.display_id) - Number(b.display_id); }
            if (va == null) { return 1; }
            if (vb == null) { return -1; }
            var c = typeof va === 'number' ? va - vb : String(va).localeCompare(String(vb));
            return c !== 0 ? sign * c : Number(a.display_id) - Number(b.display_id);
        });
    },

    invalidateProject: function (projectId) {
        delete this.drawerCache[projectId];
        delete this.drawerShowAll[projectId];
        this.load();
    },

    bindDrawerActions: function () {    // called once from init()
        var self = this;
        $(document).on('click', '.sr-remove-from-project', function () {
            var riskId = $(this).data('risk-id'), projectId = $(this).data('project-id');
            // project_id 0 unassigns the risk, which makes it eligible for the
            // queue again -- refresh it too (Task 11; this Task 9 handler
            // predates loadUnassigned() becoming a real fetch, unlike the
            // add-risks-picker commit below, which already pairs the two).
            self.assignRisks([riskId], 0, function () { self.invalidateProject(projectId); self.loadUnassigned(); });
        });
        $(document).on('click', '.sr-add-risks-btn', function () {
            self.openAddRisksPicker($(this).data('project-id'));
        });
        $(document).on('click', '.sr-subtable-show-all', function () {
            var projectId = $(this).data('project-id');
            self.drawerShowAll[projectId] = true;
            self.renderDrawer(projectId, self.drawerCache[projectId] || []);
        });
        $(document).on('click', '.sr-subtable th.sr-sortable', function () {
            self.toggleDrawerSort($(this).data('project-id'), $(this).data('sort'));
        });
        // Enter/Space on a focused header -- these are th elements rather than
        // buttons, so tabindex without key handling would be a keyboard trap.
        $(document).on('keydown', '.sr-subtable th.sr-sortable', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                self.toggleDrawerSort($(this).data('project-id'), $(this).data('sort'));
            }
        });
    },

    assignRisks: function (riskIds, projectId, onDone) {
        var self = this;
        $.ajax({
            url: BASE_URL + '/api/v2/management/projects/assign_risks',
            type: 'POST',
            data: { risk_ids: riskIds, project_id: projectId },
            dataType: 'json',
            success: function (json) {
                if (json.status_message) { showAlertsFromArray(json.status_message); }
                if (onDone) { onDone(json.data || {}); }
            },
            error: self.ajaxError,
        });
    },

    addRisksPicker: null,
    openAddRisksPicker: function (projectId) {
        var self = this;
        if (!this.addRisksPicker) {
            this.addRisksPicker = createFacetedPicker({
                modalId: 'add-risks-picker', searchId: 'add-risks-picker-search', listId: 'add-risks-picker-list',
                selectedId: 'add-risks-picker-selected', countId: 'add-risks-picker-count',
                selectedCountId: 'add-risks-picker-selected-count', scopeId: 'add-risks-picker-scope',
                commitId: 'add-risks-picker-commit', facets: [],
                itemId: function (r) { return r.id; },
                // No number column: the subject is what a person recognises,
                // and the engine's selected chips show itemNumber() when it is
                // non-empty -- so an empty number puts the subject on the chip.
                itemNumber: function () { return ''; },
                // sr-faceted-picker.js writes itemName()/itemNumber() via
                // jQuery's `text:` option (a textContent assignment, never
                // html:), so this must be the entity-DECODED subject -- never
                // pre-escaped HTML -- or the picker's row/chip would show
                // literal "&amp;"-style entities (controller ruling).
                itemName: function (r) { return $('<textarea>').html(r.subject).text(); },
                // Every other createFacetedPicker() caller (compliance.js's
                // getControlPicker(), documentation.php's
                // getDocumentControlPicker(), document_exceptions.php) sets
                // all five of these; matchesTerm()/renderList()/
                // renderSelected()/currentScopeLabel() in sr-faceted-picker.js
                // call them unconditionally once the dialog is used (e.g. any
                // search keystroke), so omitting them throws
                // "config.searchText is not a function". 'AllUnassignedRisks'
                // and 'NoRisksSelectedYet' are registered in header.php's
                // CUSTOM:pages/plan-projects.js key list for exactly this
                // (fix round 1); 'RemoveFromProject' would read oddly for a
                // roster picker over the Unassigned queue (nothing here is
                // yet in a project to be "removed from"), so this uses the
                // page's separately-registered generic 'Remove' instead.
                searchText: function (r) { return '#' + r.display_id + ' ' + $('<textarea>').html(r.subject).text(); },
                emptyText: L('NoRisksMatchYourSearch'),
                nothingSelectedText: L('NoRisksSelectedYet'),
                allScopeText: L('AllUnassignedRisks'),
                removeLabel: L('Remove'),
            });
        }
        $.getJSON(BASE_URL + '/api/v2/management/projects/unassigned_risks', function (json) {
            self.addRisksPicker.open({
                items: json.data || [],
                chosen: [],
                onCommit: function (ids) {
                    if (!ids.length) { return; }
                    self.assignRisks(ids, projectId, function () {
                        self.invalidateProject(projectId);
                        self.loadUnassigned();     // Task 11
                    });
                },
            });
        });
    },

    /* ==================== Task 10: row actions, add/edit/status/delete modals, bulk bar ==================== */

    renderRowActions: function (row) {
        var self = this, a = '';
        if (this.state.can.manage) {
            a += '<button type="button" class="sr-row-action sr-action-edit" data-id="' + this.escAttr(row.id) + '" title="' + this.escAttr(L('Edit')) + '" aria-label="' + this.escAttr(L('Edit')) + '"><i class="fa fa-edit" aria-hidden="true"></i></button>';
            a += '<button type="button" class="sr-row-action sr-action-status" data-id="' + this.escAttr(row.id) + '" title="' + this.escAttr(L('ChangeStatus')) + '" aria-label="' + this.escAttr(L('ChangeStatus')) + '"><i class="fa fa-arrow-right-arrow-left" aria-hidden="true"></i></button>';
        }
        if (this.state.can.delete) {
            a += '<button type="button" class="sr-row-action sr-row-action-danger sr-action-delete" data-id="' + this.escAttr(row.id) + '" title="' + this.escAttr(L('Delete')) + '" aria-label="' + this.escAttr(L('Delete')) + '"><i class="fa fa-trash" aria-hidden="true"></i></button>';
        }
        if (!a) { return ''; }
        return '<span class="sr-row-actions-wrap"><button type="button" class="sr-row-actions-toggle" aria-expanded="false" aria-haspopup="true" aria-label="' + this.escAttr(L('Actions')) + '"><i class="fa fa-ellipsis" aria-hidden="true"></i></button><span class="sr-row-actions">' + a + '</span></span>';
    },

    bindRowActions: function () {   // once from init()
        var self = this;
        SRRowActionsMenu.bind({ container: '#plan_projects_table', scope: $('#plan_projects_table'), namespace: 'planprojects' });
        $('#plan_projects_table').on('click', '.sr-action-edit', function () { self.openEditModal($(this).data('id')); });
        $('#plan_projects_table').on('click', '.sr-action-status', function () { self.openStatusModal([$(this).data('id')]); });
        $('#plan_projects_table').on('click', '.sr-action-delete', function () { self.openDeleteModal([$(this).data('id')]); });
        $('#plan-projects-add-btn').on('click', function () { self.openAddModal(); });
    },

    /* ---- Add / Edit (ported from the deleted plan-project.js) ---- */

    openAddModal: function () {
        resetForm('#project-modal-add form');
        $('#project-modal-add').modal('show');
    },

    openEditModal: function (id) {
        var self = this;
        resetForm('#project-modal-edit form');
        $.ajax({
            url: BASE_URL + '/api/v2/management/project/detail?project_id=' + encodeURIComponent(id),
            type: 'GET',
            success: function (res) {
                var project = res.data || {};
                $('#project-modal-edit [name=project_id]').val(id);
                $('#project-modal-edit [name=name]').val(project.name);
                $('#project-modal-edit [name=due_date]').val(project.due_date);
                // Show 'Unassigned' if the value of the following field is not
                // assigned to the project.
                $('#project-modal-edit [name=consultant]').val(project.consultant || '');
                $('#project-modal-edit [name=business_owner]').val(project.business_owner || '');
                $('#project-modal-edit [name=data_classification]').val(project.data_classification || '');
                if (project.custom_values) {
                    var custom_values = project.custom_values;
                    for (var i = 0; i < custom_values.length; i++) {
                        var field_value = custom_values[i].value;
                        var element = $("#project-modal-edit [name^='custom_field[" + custom_values[i].field_id + "]']");
                        var isMulti = ['multidropdown', 'user_multidropdown'].indexOf(custom_values[i].field_type) !== -1;
                        if (isMulti) {
                            if (element.data('multiselect') || element.hasClass('multiselect')) {
                                element.multiselect('deselectAll', false);
                                if (field_value) { element.multiselect('select', field_value); }
                                element.multiselect('refresh');
                            }
                        } else {
                            element.val(field_value ? field_value : '');
                        }
                    }
                }
                $('#project-modal-edit').modal('show');
            },
            error: self.ajaxError,
        });
    },

    bindProjectModals: function () {   // once from init()
        var self = this;

        // Compound tag+id selector (not a bare '#project-new') so this binds
        // to EVERY form sharing that id -- display_add_projects() renders one
        // <form id="project-new"> per template-group tab when Customization
        // resolves more than one group for this user (duplicate ids across
        // panes -- same established pattern as display_add_risk()).
        $('form#project-new').on('submit', function (event) {
            event.preventDefault();

            var form_element = this;
            var trim_flag = false;

            // Check if the trimmed value is empty for the required fields.
            $(form_element).find('input[required], textarea[required]').each(function () {
                var trimmed_value = $(this).val().trim();
                $(this).val(trimmed_value);
                if (!trimmed_value) {
                    toastr.error(L('ThereAreRequiredFields'));
                    trim_flag = true;
                    return;
                }
            });
            if (trim_flag) { return false; }

            var form = new FormData(form_element);
            $.ajax({
                url: BASE_URL + '/api/v2/management/project/add',
                type: 'POST',
                data: form,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {
                    showAlertsFromArray(data.status_message);
                    // Reset every matched form, not just the first -- see the
                    // 'form#project-new' comment above for why there can be
                    // more than one.
                    $('form#project-new').each(function () { this.reset(); });
                    $('#project-modal-add').modal('hide');
                    self.load();
                },
                // On error (e.g. a 400 for a duplicate name) the modal stays
                // open with the user's input intact and self.ajaxError's toast
                // explains what to fix, rather than closing the dialog and
                // discarding what they typed.
                error: self.ajaxError,
            });
            return false;
        });

        // With multiple template-group tabs, the Save button lives once in
        // the shared modal footer (rather than once per pane) so route its
        // click to whichever pane's <form> is the currently visible (active)
        // tab. With a single un-tabbed form this still resolves correctly
        // since that form is always visible.
        $(document).on('click', '.project-add-save-btn', function (event) {
            event.preventDefault();
            $(this).closest('.modal-content').find('form.project-new-form:visible').trigger('submit');
        });

        // Re-assert the first tab/pane as active every time the Add Project
        // modal opens. A sitewide Bootstrap Tab/ARIA-enhancement pass over
        // every [data-bs-toggle=tab] strips the server-rendered active/show
        // state from these panes before the user ever interacts with them,
        // leaving the modal looking blank until a tab is clicked.
        $('#project-modal-add').on('show.bs.modal', function () {
            var $tabLinks = $(this).find('.nav-tabs .nav-link');
            if ($tabLinks.length) {
                $tabLinks.removeClass('active');
                $tabLinks.first().addClass('active');
                var $tabPanes = $(this).find('.tab-pane');
                $tabPanes.removeClass('show active');
                $tabPanes.first().addClass('show active');
            }
        });

        $('#project-edit').on('submit', function (event) {
            event.preventDefault();

            var form_element = this;
            var trim_flag = false;

            $(form_element).find('input[required], textarea[required]').each(function () {
                var trimmed_value = $(this).val().trim();
                $(this).val(trimmed_value);
                if (!trimmed_value) {
                    toastr.error(L('ThereAreRequiredFields'));
                    trim_flag = true;
                    return;
                }
            });
            if (trim_flag) { return false; }

            var form = new FormData(form_element);
            $.ajax({
                url: BASE_URL + '/api/v2/management/project/edit',
                type: 'POST',
                data: form,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {
                    showAlertsFromArray(data.status_message);
                    form_element.reset();
                    $('#project-modal-edit').modal('hide');
                    self.drawerCache = {};   // the project's name may have changed
                    self.load();
                },
                // On error the modal stays open with the user's input intact
                // (see the Add form's identical comment above).
                error: self.ajaxError,
            });
            return false;
        });
    },

    /* ---- Change status (single + bulk) with the consequence copy ---- */

    pendingStatusIds: [],
    openStatusModal: function (ids) {
        var self = this;
        this.pendingStatusIds = ids.map(Number);
        var rows = this.state.rows.filter(function (r) { return self.pendingStatusIds.indexOf(r.id) !== -1; });
        var openRisks = rows.reduce(function (n, r) { return n + (r.status === 3 ? 0 : r.risk_count); }, 0);
        var $m = $('#project-status-modal');
        $m.find('input[name="project_status"]').prop('checked', false);
        if (rows.length === 1) { $m.find('input[name="project_status"][value="' + rows[0].status + '"]').prop('checked', true); }
        var explain = function () {
            var checked = $m.find('input[name="project_status"]:checked');
            if (!checked.length) { $('#project-status-consequence').addClass('d-none').empty(); return; }
            var target = Number(checked.val());
            var lines = [];
            if (target === 3 && openRisks > 0) { lines.push(self.fmt('ChangeStatusClosesRisks', openRisks)); }
            if (target !== 3 && rows.some(function (r) { return r.status === 3; })) { lines.push(L('ChangeStatusReopensRisks')); }
            if (lines.length && !self.state.can.closeRisks) { lines.push(L('ChangeStatusNoCloseRightsHint')); }
            $('#project-status-consequence').toggleClass('d-none', !lines.length).html(lines.map(self.esc).join('<br>'));
        };
        $m.find('input[name="project_status"]').off('change.pp').on('change.pp', explain);
        explain();
        $m.modal('show');
    },
    bindStatusModal: function () {   // once from init()
        var self = this;
        $('#project-status-confirm').on('click', function () {
            var target = $('#project-status-modal input[name="project_status"]:checked').val();
            if (!target) { return; }
            var queue = self.pendingStatusIds.slice(), failed = [];
            $('#project-status-modal').modal('hide');
            $.blockUI({ message: '<i class="fa fa-spinner fa-spin"></i>' });
            (function next() {
                if (!queue.length) {
                    $.unblockUI();
                    if (failed.length) { showAlertFromMessage(L('RequestFailed') + ' (' + failed.join(', ') + ')', false); }
                    self.state.selected = {};
                    self.drawerCache = {};
                    self.load();
                    return;
                }
                var id = queue.shift();
                $.ajax({
                    url: BASE_URL + '/api/v2/management/project/update_status', type: 'POST',
                    data: { update_project_status: true, project_id: id, status: target },
                    success: function (json) { if (queue.length === 0 && json.status_message) { showAlertsFromArray(json.status_message); } next(); },
                    error: function (xhr) { if (!retryCSRF(xhr, this)) { failed.push(id); next(); } },
                });
            })();
        });
    },

    /* ---- Delete (single + bulk) ---- */

    pendingDeleteIds: [],
    openDeleteModal: function (ids) {
        var self = this;
        this.pendingDeleteIds = ids.map(Number);
        var risks = this.state.rows.filter(function (r) { return self.pendingDeleteIds.indexOf(r.id) !== -1; })
            .reduce(function (n, r) { return n + r.risk_count; }, 0);
        $('#project-delete-consequence').text(risks ? this.fmt('DeleteProjectReturnsRisks', risks) : '');
        $('#project-delete-modal').modal('show');
    },
    bindDeleteModal: function () {   // once from init()
        var self = this;
        $('#project-delete-confirm').on('click', function () {
            var queue = self.pendingDeleteIds.slice(), failed = [];
            $('#project-delete-modal').modal('hide');
            $.blockUI({ message: '<i class="fa fa-spinner fa-spin"></i>' });
            (function next() {
                if (!queue.length) {
                    $.unblockUI();
                    if (failed.length) { showAlertFromMessage(L('RequestFailed') + ' (' + failed.join(', ') + ')', false); }
                    self.state.selected = {}; self.drawerCache = {};
                    self.load(); self.loadUnassigned();   // deleted projects' risks return to the queue
                    return;
                }
                var id = queue.shift();
                $.ajax({
                    url: BASE_URL + '/api/v2/management/project/delete', type: 'POST',
                    data: { delete_project: true, project_id: id },
                    success: function (json) { if (queue.length === 0 && json.status_message) { showAlertsFromArray(json.status_message); } next(); },
                    error: function (xhr) { if (!retryCSRF(xhr, this)) { if (xhr.responseJSON && xhr.responseJSON.status_message) { showAlertsFromArray(xhr.responseJSON.status_message); } failed.push(id); next(); } },
                });
            })();
        });
    },

    /* ---- Selection + bulk bar ---- */

    selectedIds: function () { return Object.keys(this.state.selected).map(Number); },
    syncBulkBar: function () {
        var self = this;
        if (this.state.status === 'unassigned') {
            $('#plan-projects-bulk-bar').addClass('d-none');
            return;
        }
        $('#plan_projects_table .sr-row-check').each(function () { this.checked = !!self.state.selected[this.getAttribute('data-id')]; });
        var n = this.selectedIds().length;
        $('#plan-projects-toolbar').toggle(n === 0);
        $('#plan-projects-bulk-bar').toggleClass('d-none', n === 0);
        $('#plan-projects-bulk-count').text(this.fmt('NSelected', n));
        var $checks = $('#plan_projects_table .sr-row-check'), total = $checks.length, checked = $checks.filter(':checked').length;
        $('#plan-projects-select-all').prop('checked', total > 0 && checked === total).prop('indeterminate', checked > 0 && checked < total);
        // The header checkbox only reaches the current page; offer the rest
        // of the matching set (every page under the current chip, search and
        // filters) while there is more of it than is selected.
        var matching = this.matchingProjectIds().length;
        $('#plan-projects-select-all-filtered').toggleClass('d-none', !(n > 0 && matching > n)).text(this.fmt('SelectAllN', matching));
    },
    // Ids of every project row DataTables currently has after search/filter,
    // across all pages -- the set "Select all N" escalates to.
    matchingProjectIds: function () {
        if (!this.state.table) { return []; }
        return this.state.table.rows({ search: 'applied' }).data().toArray().map(function (r) { return String(r.id); });
    },
    bindSelection: function () {   // once from init()
        var self = this;
        $('#plan_projects_table').on('change', '.sr-row-check', function () {
            if (this.checked) { self.state.selected[this.getAttribute('data-id')] = true; } else { delete self.state.selected[this.getAttribute('data-id')]; }
            self.syncBulkBar();
        });
        $('#plan-projects-select-all').on('change', function () {
            var on = this.checked;
            $('#plan_projects_table .sr-row-check').each(function () { if (on) { self.state.selected[this.getAttribute('data-id')] = true; } else { delete self.state.selected[this.getAttribute('data-id')]; } });
            self.syncBulkBar();
        });
        $('#plan-projects-bulk-clear').on('click', function () { self.state.selected = {}; self.syncBulkBar(); });
        $('#plan-projects-select-all-filtered').on('click', function () {
            self.matchingProjectIds().forEach(function (id) { self.state.selected[id] = true; });
            self.syncBulkBar();
        });
        $('#plan-projects-bulk-status').on('click', function () { self.openStatusModal(self.selectedIds()); });
        $('#plan-projects-bulk-delete').on('click', function () { self.openDeleteModal(self.selectedIds()); });
    },

    /* ---------- empty / error states ---------- */
    emptyStateHtml: function () {
        var filtered = $('#plan-projects-search').val() || !$('#plan-projects-filters-clear').hasClass('d-none');
        if (filtered) {
            return '<div class="sr-table-empty"><div class="sr-table-empty-icon"><i class="fa fa-filter"></i></div>' +
                '<div class="sr-table-empty-title">' + PlanProjects.esc(L('NoProjectsMatchFilters')) + '</div>' +
                '<div class="sr-table-empty-action"><button type="button" class="btn btn-link btn-sm" id="plan-projects-empty-clear">' + PlanProjects.esc(L('ClearFilters')) + '</button></div></div>';
        }
        var action = PlanProjects.state.can.add
            ? '<div class="sr-table-empty-action"><button type="button" class="btn btn-danger" id="plan-projects-empty-add">+ ' + PlanProjects.esc(L('AddProject')) + '</button></div>'
            : '';
        return '<div class="sr-table-empty"><div class="sr-table-empty-icon"><i class="fa fa-folder-open"></i></div>' +
            '<div class="sr-table-empty-title">' + PlanProjects.esc(L('NoProjectsYet')) + '</div>' +
            '<div class="sr-table-empty-body">' + PlanProjects.esc(L('NoProjectsYetHint')) + '</div>' + action + '</div>';
    },
    renderLoadError: function () {
        var colspan = this.state.table ? this.state.table.columns().count() : $('#plan_projects_table thead th').length;
        $('#plan_projects_table tbody').html('<tr><td colspan="' + colspan + '"><div class="sr-table-empty sr-table-empty-danger">' +
            '<div class="sr-table-empty-icon"><i class="fa fa-triangle-exclamation"></i></div>' +
            '<div class="sr-table-empty-title">' + this.esc(L('CouldNotLoadProjects')) + '</div>' +
            '<div class="sr-table-empty-action"><button type="button" class="btn btn-outline-secondary btn-sm" id="plan-projects-retry">' + this.esc(L('Retry')) + '</button></div></div></td></tr>');
        $('#plan-projects-retry').one('click', function () { PlanProjects.load(); });
    },

    /* ==================== Task 8: table build, columns ==================== */

    CORE_TOGGLE: ['due_date', 'consultant', 'business_owner', 'data_classification', 'risk_count', 'highest_risk', 'status'],
    // Consultant / Data classification / Status start hidden so the default
    // grid fits a laptop-width card without a horizontal scroll; the
    // Columns picker turns them on, and the choice persists per user.
    HIDDEN_BY_DEFAULT: { status: true, consultant: true, data_classification: true },
    TOGGLE_COLUMNS: [],
    columnVisible: {},
    columnLabelOverride: {},
    saveColumnsDebounced: null,   // assigned in init(): debounce(fn, 500)
    restoringOrder: false,

    resolveColumns: function () {
        var self = this;
        var ac = this.state.activeColumns;
        var keys, labels = {};
        if (ac === null) {
            keys = this.CORE_TOGGLE.slice();
        } else {
            keys = ac.map(function (c) { labels[c.key] = c.label; return c.key; });
        }
        this.TOGGLE_COLUMNS = keys;
        this.columnLabelOverride = labels;
        // Visibility: saved settings win; else defaults (custom + status hidden).
        var saved = {};
        if (this.state.columnSettings && Array.isArray(this.state.columnSettings.columns)) {
            this.state.columnSettings.columns.forEach(function (pair) { saved[pair[0]] = pair[1] === '1' || pair[1] === 1; });
        }
        this.columnVisible = {};
        keys.forEach(function (k) {
            self.columnVisible[k] = (k in saved) ? saved[k] : !(self.HIDDEN_BY_DEFAULT[k] || k.indexOf('custom_field_') === 0);
        });
        // Append <th>s for custom fields (and remove Core ths not in the vocabulary).
        var $tr = $('#plan_projects_table thead tr');
        $tr.find('th[data-col]').each(function () {
            if (keys.indexOf(this.getAttribute('data-col')) === -1) { $(this).remove(); }
        });
        keys.forEach(function (k) {
            if (!$tr.find('th[data-col="' + k + '"]').length) {
                // active_columns[].label arrives RAW by design -- escape it
                // exactly once here (via jQuery's text: option, which sets
                // textContent) and never entity-decode it.
                $('<th>', { 'data-col': k, text: labels[k] || k }).insertBefore($tr.find('th.sr-actions-col'));
            }
        });
    },

    columnLabel: function (key) {
        // columnLabelOverride[key] holds active_columns[].label, which is
        // RAW (unescaped) by design -- escape it here, once, and never
        // entity-decode it (unlike the pre-escaped `data` values elsewhere
        // in this file, which DO get an unescape-once pass before being
        // treated as plain text).
        if (this.columnLabelOverride[key]) { return this.columnLabelOverride[key]; }
        return L({ due_date: 'DueDate', consultant: 'Consultant', business_owner: 'BusinessOwner', data_classification: 'DataClassification', risk_count: 'Risks', highest_risk: 'HighestRisk', status: 'Status' }[key] || key);
    },

    columnDefs: function () {
        var self = this;
        var rendererFor = {
            due_date: this.renderDueDate,
            consultant: function (d, t, r) { return self.renderPerson(r.consultant, t); },
            business_owner: function (d, t, r) { return self.renderPerson(r.business_owner, t); },
            data_classification: this.renderClassification,
            risk_count: this.renderRiskCount,
            highest_risk: this.renderHighestRisk,
            status: this.renderStatus,
        };
        // 'dt-head-left' on the numeric columns: DataTables stamps dt-type-numeric
        // on them and the vendored bootstrap5 stylesheet then row-reverses the
        // header flex, putting the sort icon on the LEFT of the title while every
        // other header has it on the right (Review Risk's toggleColumnDef()
        // documents the same fix).
        var defs = [
            { data: null, name: 'drag', orderable: false, className: 'sr-drag-col', render: this.renderDragHandle },
            { data: null, name: 'select', orderable: false, className: 'sr-check-col', render: this.renderCheckbox },
            { data: null, name: 'caret', orderable: false, className: 'sr-caret-col', render: this.renderCaret },
            { data: 'priority', name: 'priority', className: 'num dt-head-left', render: this.renderPriority },
            { data: 'name', name: 'name', render: this.renderName },
        ];
        // Column order follows the CURRENT <th> order (ColReorder-restored in buildTable).
        $('#plan_projects_table thead th[data-col]').each(function () {
            var key = this.getAttribute('data-col');
            defs.push({
                data: null, name: key,
                // dt-head-left on EVERY toggleable column: DataTables type-detects
                // numeric AND date columns (due_date gets dt-type-date) and the
                // vendored stylesheet row-reverses both, moving the sort icon to
                // the left of the title; the class is a no-op on string columns.
                className: (key === 'risk_count' ? 'num ' : '') + 'dt-head-left',
                render: rendererFor[key] || self.renderCustomField(key),
                createdCell: function (td) { $(td).attr('data-col', key).toggleClass('d-none', !self.columnVisible[key]); },
            });
        });
        // sr-actions-col-sticky must be on the <td>s too (DataTables does not
        // copy the <th> class down) for the right-pinned rule in _tables.scss.
        defs.push({ data: null, name: 'actions', orderable: false, className: 'sr-actions-col sr-actions-col-sticky', render: this.renderActions });
        return defs;
    },

    buildTable: function () {
        var self = this;
        this.resolveColumns();
        var defs = this.columnDefs();
        var priorityIdx = defs.findIndex(function (c) { return c.name === 'priority'; });
        // ColReorder is vendored at 3.0.1 here (datatables.net-colreorder
        // package.json), which dropped the 1.x `fixedColumnsLeft`/
        // `fixedColumnsRight` options entirely -- confirmed by grepping the
        // vendored dataTables.colReorder.js: no match for either name
        // anywhere in the file. This major's only column-scoping option is
        // `columns`, a DataTables column-selector naming which columns ARE
        // moveable (ColReorder.defaults.columns = ''; `dt.columns(this.c.
        // columns)` is what `_move()`/`_target()` consult) -- there is no
        // separate "these columns are fixed" concept, so the fixed-left-4/
        // fixed-right-1 shape is expressed as "everything BETWEEN them is
        // moveable": an explicit index array computed from defs.length
        // (select=0, caret=1, priority=2, name=3 fixed left; actions=last
        // fixed right), rebuilt here because the toggle/custom-field column
        // count varies with Customization curation.
        var moveable = [];
        for (var i = 5; i < defs.length - 1; i++) { moveable.push(i); }
        this.state.table = $('#plan_projects_table').DataTable({
            data: this.state.rows,
            columns: defs,
            order: [[priorityIdx, 'asc']],
            pagingType: 'simple_numbers',
            pageLength: 25,
            autoWidth: false,
            // header.php's shared 'datatables' bootstrap sets
            // DataTable.defaults.serverSide = true / processing = true
            // app-wide (every OTHER grid on the site -- review-risk.js,
            // compliance-initiate-audits.js, etc. -- is serverSide). This
            // page is a plain client-side table (`data:` is the full row
            // set fetched once by load(), not a per-page ajax source), and
            // without this explicit override DataTables silently inherits
            // serverSide:true and waits forever for an ajax response that
            // never comes -- confirmed live: table.page.info() reported
            // serverSide:true/recordsTotal:0 despite state.rows.length
            // being 151, and the tbody was stuck on the literal built-in
            // "Loading..." placeholder (language.loadingRecords) instead of
            // ever rendering a row.
            serverSide: false,
            processing: false,
            colReorder: { columns: moveable },
            // Configured (not `false`) per this task's controller ruling --
            // Task 11 binds the 'row-reorder.dt' event and gates availability
            // per-status (only the active-status priority sort is reorderable);
            // this task only wires the handle selector so nothing throws.
            rowReorder: { selector: 'td.sr-drag-col .sr-drag-handle', update: false, snapX: true },
            rowId: function (row) { return 'project-' + row.id; },
            createdRow: function (tr, row) { $(tr).attr('data-project-id', row.id).addClass('sr-group-row').attr('aria-expanded', 'false'); },
            // Rows-per-page pill reads "Show [N v]" like the other redesigned
            // grids (governance-documents.js, review-risk.js); DataTables'
            // bundled "_MENU_ entries per page" template puts the <select>
            // first and adds trailing text the shared .dt-length pill
            // (_tables.scss) was not sized for.
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, L('ALL')]],
            language: { emptyTable: '', zeroRecords: '', lengthMenu: L('Show') + ' _MENU_' },
            dom: 'rt<"sr-table-foot"<"sr-table-foot-left"li><"sr-table-foot-right"p>>',
            drawCallback: function () {
                var api = this.api();
                if (api.rows({ search: 'applied' }).count() === 0) {
                    $('#plan_projects_table tbody').html('<tr class="sr-empty-row"><td colspan="' + defs.length + '">' + self.emptyStateHtml() + '</td></tr>');
                }
                self.paintSevPills();
            },
        });
        // Restore saved column order (keys -> current indices), then bind the save.
        var saved = this.state.columnSettings && Array.isArray(this.state.columnSettings.order) ? this.state.columnSettings.order : null;
        if (saved) {
            var current = this.state.table.colReorder.order();
            var want = [];
            var fixedLeft = current.slice(0, 5), fixedRight = current.slice(-1);
            saved.forEach(function (key) {
                var col = self.state.table.column(key + ':name');
                if (col.length && fixedLeft.indexOf(col.index()) === -1 && fixedRight.indexOf(col.index()) === -1) { want.push(col.index()); }
            });
            current.forEach(function (idx) { if (want.indexOf(idx) === -1 && fixedLeft.indexOf(idx) === -1 && fixedRight.indexOf(idx) === -1) { want.push(idx); } });
            this.restoringOrder = true;
            this.state.table.colReorder.order(fixedLeft.concat(want, fixedRight), true);
            this.restoringOrder = false;
        }
        this.state.table.on('column-reorder.dt', function () { if (!self.restoringOrder) { self.saveColumnsDebounced(); } });
        this.state.table.on('draw', function () {
            self.syncSortIcons();
            self.applyColumnVisibility();
            self.syncBulkBar();              // Task 10
            self.bindDrawerRows();           // Task 9
            self.bindRiskDragDrop();         // Task 11 -- project rows are rebuilt on every draw
            self.applyReorderAvailability(); // Task 11 -- search/filter draws can change gating too
        });
        this.renderColpanel();
        // The first draw of a client-side DataTable fires synchronously inside
        // the constructor, before the 'draw' listener above exists -- so run
        // the same post-draw wiring once here or the caret, the drag handles
        // and the bulk bar stay inert until something redraws the table.
        this.syncSortIcons();
        this.applyColumnVisibility();
        this.syncBulkBar();
        this.bindDrawerRows();
        this.bindRiskDragDrop();
        this.applyReorderAvailability();
        this.bindColpicker();
    },

    currentColumnOrderForSave: function () {
        var self = this;
        return this.state.table.columns().indexes().toArray().map(function (i) {
            return self.state.table.column(i).header().getAttribute('data-col');
        }).filter(Boolean);
    },

    // Paints each severity/score pill's data-color onto the element via the
    // style PROPERTY (the browser validates it -- an invalid value is a safe
    // no-op, never a markup break-out) and flips `.on-light` when the fill's
    // luminance is pale enough to need dark text -- copied from
    // review-risk.js's paintColorPills(), scoped to #plan-projects-card and
    // renamed to avoid a global collision with review-risk.js's own
    // top-level function of the same name (both pages may share sidebar-wide
    // globals; this file keeps everything on the PlanProjects object).
    paintSevPills: function () {
        // Both tables (grid + the queue behind the Unassigned chip) live inside
        // #plan-projects-card, so one selector covers every painted pill.
        $('#plan-projects-card .sr-sev-pill[data-color]').each(function () {
            var $el = $(this);
            var color = $el.attr('data-color');
            if (!color) { return; }
            this.style.backgroundColor = color;
            var probe = document.createElement('span');
            probe.style.color = color;
            document.body.appendChild(probe);
            var rgb = window.getComputedStyle(probe).color;
            document.body.removeChild(probe);
            var m = rgb.match(/\d+/g);
            if (m && m.length >= 3) {
                var lum = 0.299 * (+m[0]) + 0.587 * (+m[1]) + 0.114 * (+m[2]);
                $el.toggleClass('on-light', lum > 150);
            }
        });
    },

    // ---- Columns picker (copied from review-risk.js's renderColpanel() /
    // filterColpanelItems() / resetColpanelSearch() / applyColumnVisibility()
    // / syncSortIcons() + its colpicker click bindings, #review-risk-* ->
    // #plan-projects-*, #review_risk_table -> #plan_projects_table,
    // COLUMN_GROUPS collapsed to one ungrouped list off
    // PlanProjects.TOGGLE_COLUMNS/.columnLabel(), bare `table` ->
    // `PlanProjects.state.table`) ----

    renderColpanel: function () {
        var self = this;
        var searchHtml = '<div class="colpanel-search">' +
            '<i class="fa fa-search colpanel-search-icon" aria-hidden="true"></i>' +
            '<input type="text" class="colpanel-search-input" id="plan-projects-colpanel-search" placeholder="' + this.escAttr(L('Search')) + '" aria-label="' + this.escAttr(L('Search')) + '" autocomplete="off">' +
            '</div>';
        var items = this.TOGGLE_COLUMNS.map(function (key) {
            return '<label class="colpanel-item"><input type="checkbox" data-col="' + self.escAttr(key) + '"' + (self.columnVisible[key] ? ' checked' : '') + '> ' + self.esc(self.columnLabel(key)) + '</label>';
        }).join('');
        var groupsHtml = '<div class="colpanel-group">' + items + '</div>';
        var emptyHtml = '<div class="colpanel-empty d-none">' + this.esc(L('NoMatchingOptions')) + '</div>';
        $('#plan-projects-colpanel').html(searchHtml + groupsHtml + emptyHtml);
    },

    filterColpanelItems: function (query) {
        var q = String(query || '').toLowerCase();
        var anyVisible = false;
        $('#plan-projects-colpanel .colpanel-group').each(function () {
            var $group = $(this);
            var groupHasMatch = false;
            $group.find('.colpanel-item').each(function () {
                var matches = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
                $(this).toggleClass('d-none', !matches);
                if (matches) { groupHasMatch = true; }
            });
            $group.toggleClass('d-none', !groupHasMatch);
            if (groupHasMatch) { anyVisible = true; }
        });
        $('#plan-projects-colpanel .colpanel-empty').toggleClass('d-none', anyVisible);
    },

    resetColpanelSearch: function () {
        $('#plan-projects-colpanel-search').val('');
        this.filterColpanelItems('');
    },

    // Plain display-toggle keyed off data-col -- no DataTables
    // column-visibility API involvement for the picker's OWN state (see
    // review-risk.js's identical function for the full rationale); the
    // native-visibility mirror loop below is required ONLY because this page
    // also runs ColReorder, whose own drop-zone width math
    // (dataTables.colReorder.js's _regions()) reads DataTables' native
    // column().visible() state, not `d-none` -- a CSS-hidden-but-natively-
    // visible column reports a phantom non-zero width and throws off every
    // later drop-zone boundary. Native-visibility-first (before the d-none
    // loop) matters: column().visible(true) re-attaches the original node
    // with whatever classes it already had, so the d-none clear must run
    // after re-attachment finds it.
    applyColumnVisibility: function () {
        var self = this;
        var managed = {};
        this.TOGGLE_COLUMNS.forEach(function (col) { managed[col] = true; });

        var table = this.state.table;
        if (table) {
            table.columns().every(function () {
                var header = this.header();
                var key = header ? header.getAttribute('data-col') : null;
                if (!key) { return; } // Core/non-toggleable column -- always native-visible.
                var shouldBeVisible = managed[key] ? !!self.columnVisible[key] : false;
                if (this.visible() !== shouldBeVisible) { this.visible(shouldBeVisible, false); }
            });
            table.columns.adjust();
        }

        this.TOGGLE_COLUMNS.forEach(function (col) {
            $('#plan_projects_table [data-col="' + col + '"]').toggleClass('d-none', !self.columnVisible[col]);
        });

        $('#plan_projects_table thead th[data-col]').each(function () {
            var key = this.getAttribute('data-col');
            if (key && !managed[key]) {
                $('#plan_projects_table [data-col="' + key + '"]').addClass('d-none');
            }
        });
    },

    // Swaps DataTables' own bundled sort-indicator pseudo-elements for the
    // shared fa-sort/fa-arrow-up-short-wide/fa-arrow-down-wide-short glyph
    // convention (review-risk.js's syncSortIcons() twin).
    syncSortIcons: function () {
        $('#plan_projects_table thead th[data-dt-column]').each(function () {
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
    },

    saveColumns: function () {
        var self = this;
        var payload = this.TOGGLE_COLUMNS.map(function (k) { return [k, self.columnVisible[k] ? '1' : '0']; });
        $.post({
            url: BASE_URL + '/api/v2/management/projects/display_settings',
            data: { columns: payload, order: this.currentColumnOrderForSave() },
            error: function (xhr) { retryCSRF(xhr, this); },
        });
    },

    bindColpicker: function () {
        var self = this;
        $(document).on('input', '#plan-projects-colpanel-search', function () {
            self.filterColpanelItems($(this).val());
        });
        $(document).on('click', '#plan-projects-colpicker-btn', function (e) {
            e.stopPropagation();
            $('#plan-projects-colpanel').toggleClass('d-none');
            self.resetColpanelSearch();
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.colpicker').length) {
                var $panel = $('#plan-projects-colpanel');
                if (!$panel.hasClass('d-none')) {
                    $panel.addClass('d-none');
                    self.resetColpanelSearch();
                }
            }
        });
        $(document).on('change', '#plan-projects-colpanel input[type="checkbox"]', function () {
            var col = $(this).data('col');
            self.columnVisible[col] = this.checked;
            self.applyColumnVisibility();
            self.saveColumnsDebounced();
        });
    },
};

// Copied from review-risk.js's local debounce() -- not exported by
// common.js (confirmed: no `function debounce`/`window.debounce` there).
function debounce(fn, delay) {
    var timer;
    return function () {
        var args = arguments, ctx = this;
        clearTimeout(timer);
        timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
}

$(function () {
    if ($('#plan-projects-card').length) {
        PlanProjects.init();
    }
});
