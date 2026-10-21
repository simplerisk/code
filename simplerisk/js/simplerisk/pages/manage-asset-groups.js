/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Manage assets, Asset groups tab (asset management redesign, Phase A, Task 10).
 *
 * Loaded after manage-assets.js and built on its window.ManageAssets (the
 * page config, caps, lookups, the shared helpers in ManageAssets.util, the
 * tab events, setGroups() and showGroup()).
 *
 *   Table   GET /api/v2/asset-groups?aggregates=1 (server-side search, paging,
 *           name order), in the #asset-groups-panel of the shared card. Its
 *           columns come from the user's saved layout (GET/PUT
 *           /asset-groups/column-settings, the Columns picker), Manage
 *           assets' mechanics with the table's own storage.
 *   Drawer  GET /asset-groups/{id}/assets?per_page=10&sort=&dir=: the first
 *           members in the drawer's sort (highest valuation first by default;
 *           sorted on the server over every visible member, since the drawer
 *           shows only the first page) plus the visible total, with View
 *           (the asset record modal, ManageAssets.openRecord()), Remove from
 *           group (DELETE /asset-groups/{id}/assets/{asset_id}) and "View all
 *           in the Assets tab" (ManageAssets.showGroup()).
 *   Modals  Add/Edit group (POST /asset-groups, PATCH /asset-groups/{id}),
 *           members through the faceted asset picker below; Delete group
 *           (DELETE /asset-groups/{id}).
 *
 * The picker is this file's own small engine rather than createFacetedPicker()
 * (sr-faceted-picker.js): that engine needs the whole roster in the browser
 * and counts facets client-side, and an asset roster can run to thousands.
 * Here every search / facet change asks GET /assets for at most 500 matches
 * plus server-side facet counts (facet_counts=1), while keeping the shipped
 * .sr-picker-* markup and its rules: facets narrow and never gate, each facet
 * is counted inside the ones before it, a zero-count option greys out, an
 * emptied facet clears itself, a scope pill names the searched set, the
 * selection is a working copy committed only by "Use these assets", and the
 * hidden <select multiple> stays the form's value.
 *
 * Escaping: every API value and lookup is raw text and is inserted with
 * jQuery `text:` / .text(). Toast text is escaped once (server messages are
 * escaped at their source; client strings go through util.esc()).
 */
(function (window, $) {
    'use strict';

    var MA = window.ManageAssets;
    if (!MA) { return; }

    var U = MA.util;
    var cfg = MA.config || {};
    var caps = cfg.caps || {};
    var lookups = cfg.lookups || {};
    var MEMBER_LIMIT = 10;      // members shown in a row's drawer
    var ROSTER_LIMIT = 500;     // assets per picker request (the list API's page cap)
    var CHIP_LIMIT = 40;        // member chips shown in the form field at rest
    var FACETS = ['team', 'location', 'valuation'];

    // Columns of the layout (name is always on; the caret and the row
    // actions are not part of it). Used only when GET
    // /asset-groups/column-settings fails, so the table can still render.
    var COLUMN_LABEL_KEYS = {
        asset_count: 'Assets', max_valuation: 'HighestValuation',
        highest_fips_categorization: 'HighestFIPSCategorization', highest_weighted_score: 'HighestWeightedScore',
        highest_weighted_band: 'HighestWeightedBand', risk_count: 'LinkedRisks', teams: 'TeamsHeader',
        locations: 'SiteLocation', tags: 'Tags'
    };
    // Columns GET /asset-groups can sort by besides the name
    // (ASSET_GROUPS_SORT_KEYS, includes/asset_groups_list.php).
    var SORTABLE = ['highest_fips_categorization', 'highest_weighted_score', 'highest_weighted_band', 'locations', 'tags'];

    // Group filters: each an id list sent as a GET /asset-groups param, with
    // the chip kind that adds to it. Tooltips / announcements are Manage
    // assets' own (CHIP lang keys), since the values are the same.
    var FILTER_KEYS = ['team', 'location', 'tag', 'categorization', 'band'];
    var CHIP_SAY = { team: 'AssetFilteringByTeam', location: 'AssetFilteringByLocation', tag: 'AssetFilteringByTag', categorization: 'AssetGroupFilteringByHighestCategorization', band: 'AssetGroupFilteringByHighestBand' };
    var LEVEL_CHIP_TIP = { categorization: 'AssetGroupFilterByHighestCategorization', band: 'AssetGroupFilterByHighestBand' };
    var SCORING_LEVELS = {};
    (lookups.scoring_levels || []).forEach(function (l) { SCORING_LEVELS[String(l.key)] = l; });

    // A group's highest categorization / band: a filter chip coloured by
    // level (adds the level to the matching filter), plain when the filter
    // does not offer it, a dash when no member is scored.
    function levelFilterChip(kind, code) {
        var level = SCORING_LEVELS[String(code)];
        if (!level || !U.chipFilterable(kind, level.id)) { return U.levelChip(code); }
        return U.filterChip({ kind: kind, id: level.id, label: String(level.name), extraClass: 'sr-chip--level-' + String(code), tipKey: LEVEL_CHIP_TIP[kind] });
    }
    function emptyFilters() {
        var f = {};
        FILTER_KEYS.forEach(function (k) { f[k] = []; });
        return f;
    }
    var FALLBACK_DEFAULTS = ['name', 'asset_count', 'risk_count', 'highest_fips_categorization', 'highest_weighted_score', 'highest_weighted_band', 'max_valuation'];
    var SCORING_COLUMN = /^highest_/;
    var NUMBER_COLUMNS = { asset_count: 'Assets', risk_count: 'LinkedRisks' };

    // Bulk delete: the selection column, the bulk bar and POST
    // /asset-groups/bulk exist only for asset_group_delete holders (the
    // permission the single delete needs); without it nothing renders.
    var CAN_BULK_DELETE = !!caps.can_group_delete;

    // Member drawer sort: the API's sort keys; the scoring keys and the
    // valuation open highest first, the name A-Z (plan-projects' drawer rule).
    var MEMBER_SORT_DEFAULT = { key: 'value', dir: 'desc' };
    var MEMBER_SORT_FIRST_DIR = { name: 'asc', value: 'desc', fips_categorization: 'desc', weighted_score: 'desc', weighted_band: 'desc' };

    function api(path) { return BASE_URL + '/api/v2' + path; }

    // The Assets tab's valuation renderer: level name over its range.
    function valuationChip(id) {
        return U.valuationCell(id);
    }

    // Toast a success message the server escaped at its source.
    function toastServer(json, fallbackKey) {
        var msg = json && json.status_message;
        showAlertFromMessage(msg && typeof msg === 'string' ? msg : U.esc(L(fallbackKey)), true);
    }

    /* =================================================================
     * Faceted asset picker
     * ================================================================= */
    var Picker = {
        $modal: null,
        facetState: {},
        chosen: [],             // working copy of asset ids (strings)
        known: {},              // id -> {name, ip}, for the Selected pane
        items: [],
        total: 0,
        counts: null,
        cursor: 0,
        seq: 0,
        xhr: null,
        loading: false,
        onCommit: null,

        init: function () {
            var self = this;
            this.$modal = $('#asset-picker');
            if (!this.$modal.length) { return; }
            FACETS.forEach(function (f) { self.facetState[f] = null; });
            this.buildFacets();

            this.$modal.on('click', '.sr-picker-facet', function () {
                if ($(this).hasClass('is-empty')) { return; }
                var raw = $(this).attr('data-picker-value');
                self.setFacet($(this).attr('data-picker-facet'), raw === '' ? null : Number(raw));
            });
            this.$modal.on('click', '.sr-picker-clear', function () {
                self.setFacet($(this).attr('data-picker-clear'), null);
            });
            this.$modal.on('click', '.sr-picker-row', function () {
                self.cursor = $(this).index('.sr-picker-row');
                self.toggle($(this).attr('data-picker-id'));
            });
            this.$modal.on('click', '.sr-picker-chip-remove', function () {
                self.toggle($(this).attr('data-picker-remove'));
            });
            var fetchSoon = U.debounce(function () { self.fetch(null); }, 250);
            $('#asset-picker-search').on('input', function () { self.cursor = 0; fetchSoon(); });
            $('#asset-picker-search').on('keydown', function (e) {
                var n = self.items.length;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    self.cursor = Math.min(self.cursor + 1, n - 1);
                    self.renderList();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    self.cursor = Math.max(self.cursor - 1, 0);
                    self.renderList();
                } else if (e.key === 'Enter') {
                    // Enter would otherwise submit the group form behind this dialog.
                    e.preventDefault();
                    if (self.items[self.cursor]) { self.toggle(String(self.items[self.cursor].id)); }
                }
            });
            $('#asset-picker-commit').on('click', function () {
                if (typeof self.onCommit === 'function') { self.onCommit(self.chosen.slice(), self.known); }
                self.$modal.modal('hide');
            });

            // Stacked over the group modal: the app pins every modal and
            // backdrop to one z-index, so raise this dialog (body class, CSS)
            // and its backdrop (JS -- the two backdrops are not siblings a
            // selector could tell apart), and give the modal underneath its
            // scroll lock back when this one closes. Same plumbing as
            // createFacetedPicker().
            this.$modal.on('show.bs.modal', function () { $('body').addClass('sr-picker-open'); });
            this.$modal.on('shown.bs.modal', function () {
                $('.modal-backdrop').last().css('z-index', 1060);
                $('#asset-picker-search').trigger('focus');
            });
            this.$modal.on('hidden.bs.modal', function () {
                $('body').removeClass('sr-picker-open');
                if (self.xhr) { self.xhr.abort(); }
                if ($('.modal.show').length) { $('body').addClass('modal-open'); }
                $('#asset-group-members').next('.sr-chips-field').find('.sr-chips-add').trigger('focus');
            });
        },

        buildFacets: function () {
            var options = {
                team: (lookups.team_options || []).map(function (t) { return { id: t.id, label: t.name }; }),
                location: (lookups.locations || []).map(function (l) { return { id: l.id, label: l.name }; }),
                valuation: (lookups.valuations || []).map(function (v) { return { id: v.id, label: v.label }; })
            };
            FACETS.forEach(function (f) {
                var $box = $('#asset-picker-' + f).empty();
                var button = function (value, label) {
                    return $('<button>', {
                        type: 'button', 'class': 'sr-picker-facet', 'data-picker-facet': f,
                        'data-picker-value': value, 'aria-pressed': 'false', title: label
                    }).append(
                        $('<span>', { 'class': 'sr-picker-facet-label', text: label }),
                        $('<span>', { 'class': 'sr-picker-facet-count' })
                    );
                };
                $box.append(button('', $box.attr('data-all-label')));
                options[f].forEach(function (o) { $box.append(button(String(o.id), String(o.label))); });
            });
        },

        open: function (opts) {
            var self = this;
            if (!this.$modal || !this.$modal.length) { return; }
            this.chosen = (opts.chosen || []).map(String);
            this.known = $.extend({}, opts.known || {});
            this.onCommit = opts.onCommit || null;
            FACETS.forEach(function (f) { self.facetState[f] = null; });
            this.cursor = 0;
            this.items = [];
            this.counts = null;
            $('#asset-picker-search').val('');
            this.renderSelected();
            this.fetch(null);
            this.$modal.modal('show');
        },

        setFacet: function (facet, value) {
            this.facetState[facet] = (value !== null && this.facetState[facet] === value) ? null : value;
            this.cursor = 0;
            this.fetch(facet);
        },

        // One request answers the list, its total and every facet's counts.
        fetch: function (changedFacet) {
            var self = this;
            var seq = ++this.seq;
            if (this.xhr) { this.xhr.abort(); }
            var params = { per_page: ROSTER_LIMIT, page: 1, sort: 'name', dir: 'asc', columns: 'name,ip', facet_counts: 1 };
            var q = String($('#asset-picker-search').val() || '').trim();
            if (q) { params.q = q; }
            FACETS.forEach(function (f) { if (self.facetState[f] !== null) { params[f] = self.facetState[f]; } });
            this.loading = true;
            this.renderFacets();
            this.xhr = $.ajax({ url: api('/assets'), type: 'GET', data: params, dataType: 'json' })
                .done(function (json) {
                    if (seq !== self.seq) { return; }
                    self.xhr = null;
                    self.loading = false;
                    var data = (json && json.data) || {};
                    self.counts = data.facet_counts || null;
                    // An emptied later facet clears itself rather than
                    // stranding the list on a result nobody can explain.
                    if (changedFacet && self.clearEmptiedFacets(changedFacet)) { self.fetch(null); return; }
                    self.items = data.assets || [];
                    self.total = data.total || 0;
                    self.items.forEach(function (a) { self.known[String(a.id)] = { name: String(a.name || ''), ip: String(a.ip || '') }; });
                    self.renderAll();
                })
                .fail(function (xhr) {
                    if (seq !== self.seq || U.isAbort(xhr)) { return; }
                    self.xhr = null;
                    self.loading = false;
                    self.items = [];
                    self.total = 0;
                    self.renderAll();
                    U.reportError(xhr);
                });
        },

        clearEmptiedFacets: function (changedFacet) {
            var self = this, passed = false, cleared = false;
            FACETS.forEach(function (f) {
                if (f === changedFacet) { passed = true; return; }
                if (!passed || self.facetState[f] === null || !self.counts || !self.counts[f]) { return; }
                var values = self.counts[f].values || {};
                if (!values[String(self.facetState[f])]) { self.facetState[f] = null; cleared = true; }
            });
            return cleared;
        },

        renderAll: function () {
            this.renderFacets();
            this.renderList();
            this.renderSelected();
        },

        renderFacets: function () {
            var self = this;
            FACETS.forEach(function (f) {
                var c = self.counts && self.counts[f];
                var values = (c && c.values) || {};
                $('#asset-picker-' + f + ' .sr-picker-facet').each(function () {
                    var raw = $(this).attr('data-picker-value');
                    var isAll = raw === '';
                    var value = isAll ? null : Number(raw);
                    var n = isAll ? (c ? c.all : null) : (values[raw] || 0);
                    $(this).attr('aria-pressed', self.facetState[f] === value ? 'true' : 'false')
                        .toggleClass('is-empty', !!c && !isAll && n === 0 && self.facetState[f] !== value);
                    $(this).find('.sr-picker-facet-count').text(c && n ? String(n) : '');
                });
            });
        },

        scopeLabel: function () {
            var label = null, self = this;
            FACETS.forEach(function (f) {
                if (self.facetState[f] !== null) {
                    label = $('#asset-picker-' + f + ' .sr-picker-facet[data-picker-value="' + self.facetState[f] + '"] .sr-picker-facet-label').text();
                }
            });
            return label || L('AllAssets');
        },

        renderList: function () {
            var self = this;
            var $list = $('#asset-picker-list').empty();
            if (this.cursor > this.items.length - 1) { this.cursor = Math.max(this.items.length - 1, 0); }
            if (this.loading && !this.items.length) {
                $list.append($('<div>', { 'class': 'sr-picker-empty', text: L('Loading') }));
            }
            this.items.forEach(function (a, i) {
                var id = String(a.id);
                var on = self.chosen.indexOf(id) !== -1;
                var $row = $('<button>', {
                    type: 'button', role: 'option', 'data-picker-id': id,
                    'class': 'sr-picker-row' + (i === self.cursor ? ' is-cursor' : ''),
                    'aria-selected': on ? 'true' : 'false',
                    title: a.ip ? String(a.name) + ' (' + a.ip + ')' : String(a.name)
                });
                $row.append(
                    $('<span>', { 'class': 'sr-picker-check' }).append(on ? $('<i>', { 'class': 'fa fa-check', 'aria-hidden': 'true' }) : null),
                    $('<span>', { 'class': 'sr-picker-num', text: a.ip || '' }),
                    $('<span>', { 'class': 'sr-picker-name', text: a.name || '' })
                );
                $list.append($row);
            });
            if (!this.loading && !this.items.length) {
                $list.append($('<div>', { 'class': 'sr-picker-empty', text: L('NoAssetsMatchFilters') }));
            }
            if (this.total > this.items.length) {
                $list.append($('<div>', { 'class': 'sr-picker-empty sr-asset-picker-capped', text: U.fmt('PickerShowingFirstN', { count: this.items.length, total: this.total }) }));
            }
            $('#asset-picker-count').text(String(this.total || 0));
            $('#asset-picker-scope').text(this.scopeLabel());
            var row = $list.find('.is-cursor')[0];
            if (row && row.scrollIntoView) { row.scrollIntoView({ block: 'nearest' }); }
        },

        renderSelected: function () {
            var self = this;
            var $sel = $('#asset-picker-selected').empty();
            if (!this.chosen.length) {
                $sel.append($('<div>', { 'class': 'sr-picker-empty', text: L('NoControlsSelectedYet') }));
            }
            // Name order, not click order: a stable list is easier to re-read.
            this.chosen.slice().sort(function (a, b) {
                return String((self.known[a] || {}).name || '').localeCompare(String((self.known[b] || {}).name || ''));
            }).forEach(function (id) {
                var name = (self.known[id] || {}).name || ('#' + id);
                $sel.append($('<span>', { 'class': 'sr-picker-chip', title: name }).append(
                    $('<span>', { 'class': 'sr-picker-chip-num', text: name }),
                    $('<button>', { type: 'button', 'class': 'sr-picker-chip-remove', 'data-picker-remove': id, 'aria-label': L('Remove') + ' ' + name, text: '×' })
                ));
            });
            $('#asset-picker-selected-count').text(this.chosen.length ? String(this.chosen.length) : '');
        },

        toggle: function (id) {
            id = String(id);
            var i = this.chosen.indexOf(id);
            if (i === -1) { this.chosen.push(id); } else { this.chosen.splice(i, 1); }
            this.renderList();
            this.renderSelected();
        }
    };

    /* =================================================================
     * Asset groups tab
     * ================================================================= */
    var Groups = {
        state: { q: '', rows: [], total: 0, loadFailed: false, page: 0, table: null, columns: [], visible: {}, order: [], savedColumns: {}, filters: emptyFilters(), refocusChip: null,
            selected: {},           // group id -> name, the ticked groups (cleared by a page, sort or filter change)
            pageKey: null,          // start|length|sort|dir of the requested page
            selectAll: false,       // escalated to every group matching the search and filters
            bulkBusy: false },
        pendingDelete: null,    // {ids|filter, count, name}: what the delete dialog will remove
        colSave: { inFlight: false, pending: false, lastSent: null },
        memberSort: {},         // group id -> {key, dir}, the drawer's sort
        fetchSeq: 0,
        fetchXhr: null,
        openDrawers: {},        // group id -> true, re-opened after a redraw
        editing: null,          // the group being edited, null when adding
        deleting: null,
        busy: false,
        focusAfterDraw: null,
        lastOpener: null,
        teamNames: U.byId(lookups.team_options || lookups.teams),
        locationNames: U.byId(lookups.locations),
        tagNames: U.byId(lookups.tags),

        init: function () {
            var self = this;
            this.bindToolbar();
            this.bindFilters();
            this.bindSelection();
            this.bindColpicker();
            this.bindRows();
            this.saveColumnsDebounced = U.debounce(function () { self.saveColumns(); }, 500);
            // A save or delete elsewhere on the page (the asset record modal,
            // a bulk action) can change what a group contains.
            $(document).on('manageassets:refreshed', function () { if (self.state.table) { self.reload(false); } });
            this.bindGroupModal();
            this.bindDeleteModal();
            Picker.init();
            $(document).on('manageassets:tabshown', function (e, tab) { if (tab === 'groups') { self.show(); } });
            // A shared URL's gq (validated and capped by ManageAssets).
            if (MA.state.gq) {
                this.state.q = MA.state.gq;
                $('#asset-groups-search').val(MA.state.gq);
            }
            // A shared URL's g* filters (ManageAssets validated them against
            // these selects and set their values).
            FILTER_KEYS.forEach(function (k) { self.state.filters[k] = ((MA.state.gfilters || {})[k] || []).map(String); });
            this.syncFilterCount();
            if (this.filterValueCount() > 0) {
                $('#asset-groups-quickfilters').removeClass('d-none').addClass('is-open');
                $('#asset-groups-filters-toggle').attr('aria-expanded', 'true');
            }
            if (MA.state.tab === 'groups') { this.show(); }
        },

        // Built on first show; reloaded on every later show so changes made
        // in the Assets tab (bulk add to group, deleted assets) are reflected.
        show: function () {
            if (!this.state.table) {
                if (this.columnsRequested) { return; }
                this.columnsRequested = true;
                this.showLoadingRow();
                this.loadColumnSettings();
            } else {
                this.reload(false);
            }
        },

        reload: function (resetPaging) {
            if (this.state.table) { this.state.table.ajax.reload(null, !!resetPaging); }
        },

        /* ---------------- filters (Manage assets' row, mechanics and chips) ---------------- */
        bindFilters: function () {
            var self = this;
            // The selects are filled by ManageAssets (same lookups) before the
            // URL is read; this tab owns their changes.
            $('#asset-groups-quickfilters').on('change', 'select[data-filter]', function () {
                self.state.filters[$(this).data('filter')] = ($(this).val() || []).map(String);
                self.filtersChanged();
            });
            $('#asset-groups-filters-toggle').on('click', function () {
                var $qf = $('#asset-groups-quickfilters');
                var open = $qf.hasClass('d-none');
                $qf.toggleClass('d-none', !open).toggleClass('is-open', open);
                $(this).attr('aria-expanded', open ? 'true' : 'false');
            });
            $('#asset-groups-filters-clear').on('click', function () { self.clearAllFilters(); });
            // A cell chip adds its value to the matching filter (add-only).
            $('#asset-groups-table').on('click', '.sr-assets-chip-filter', function (e) {
                e.stopPropagation();
                self.addChipFilter(String($(this).attr('data-filter-kind')), String($(this).attr('data-filter-id')));
            });
        },
        addChipFilter: function (kind, id) {
            var self = this;
            if (!CHIP_SAY[kind] || id === '') { return; }
            U.addToFilterSelect($('#asset-groups-' + kind + '-filter'), id, CHIP_SAY[kind], '#asset-groups-chip-live', function () {
                self.state.refocusChip = { kind: kind, id: id };
            });
        },
        filterValueCount: function () {
            var f = this.state.filters;
            return FILTER_KEYS.reduce(function (n, k) { return n + f[k].length; }, 0);
        },
        syncFilterCount: function () {
            var n = this.filterValueCount();
            $('#asset-groups-filters-count').text(n).prop('hidden', n === 0);
            $('#asset-groups-filters-toggle').toggleClass('has-filters', n > 0);
            $('#asset-groups-filters-clear').toggleClass('d-none', n === 0);
        },
        filtersChanged: function () {
            this.syncFilterCount();
            MA.syncGroupsFilters(this.state.filters);
            this.selectionChanged();
        },
        // The matching set changed: the old selection no longer describes it.
        selectionChanged: function () {
            this.state.selected = {};
            this.state.selectAll = false;
            this.syncBulkBar();
            this.reload(true);
        },
        // Like Manage assets: clears every filter and the search.
        clearAllFilters: function () {
            this.state.filters = emptyFilters();
            $('#asset-groups-quickfilters select[data-filter]').each(function () {
                $(this).val([]);
                if (typeof srSelectRender === 'function' && $(this).data('srSelect')) { srSelectRender($(this)); }
            });
            this.state.q = '';
            $('#asset-groups-search').val('');
            MA.syncGroupsSearch('');
            this.filtersChanged();
        },

        bindToolbar: function () {
            var self = this;
            $('#asset-groups-search').on('input', U.debounce(function () {
                var q = String($(this).val() || '').trim();
                if (q === self.state.q) { return; }
                self.state.q = q;
                MA.syncGroupsSearch(q);
                self.selectionChanged();
            }, 300));
            $('#asset-groups-add').on('click', function () { self.openAdd(); });
            $(document).on('click', '#asset-groups-empty-add', function () { self.openAdd(); });
            $(document).on('click', '#asset-groups-empty-clear', function () { self.clearAllFilters(); });
            $(document).on('click', '#asset-groups-retry', function () { self.reload(false); });
        },

        /* ---------------- column layout + Columns picker ---------------- */
        loadColumnSettings: function () {
            var self = this;
            $.ajax({ url: api('/asset-groups/column-settings'), type: 'GET', dataType: 'json' })
                .done(function (json) { self.applyColumnSettings((json && json.data) || null); })
                .fail(function () { self.applyColumnSettings(null); });
        },
        applyColumnSettings: function (data) {
            var self = this;
            var available = [], saved = {}, order = [];
            if (data && Array.isArray(data.available_columns)) {
                available = data.available_columns;
                ((data.column_settings || {}).columns || []).forEach(function (p) { saved[p[0]] = String(p[1]) === '1' ? '1' : '0'; });
                order = ((data.column_settings || {}).order || []).slice();
            } else {
                // Settings could not load: the default layout (as the server builds it).
                order = FALLBACK_DEFAULTS.slice();
                Object.keys(COLUMN_LABEL_KEYS).forEach(function (k) {
                    if (SCORING_COLUMN.test(k) && !lookups.scoring_enabled) { return; }
                    if (k === 'risk_count' && !cfg.canViewRisks) { return; }
                    available.push({ key: k, label_key: COLUMN_LABEL_KEYS[k], label: null });
                    saved[k] = FALLBACK_DEFAULTS.indexOf(k) !== -1 ? '1' : '0';
                    if (order.indexOf(k) === -1) { order.push(k); }
                });
            }
            var byKey = {};
            available.forEach(function (c) { byKey[c.key] = c; });
            available.forEach(function (c) { if (order.indexOf(c.key) === -1) { order.push(c.key); } });
            this.state.order = order.filter(function (k) { return byKey[k] || k === 'name'; });
            if (this.state.order[0] !== 'name') { this.state.order = ['name'].concat(this.state.order.filter(function (k) { return k !== 'name'; })); }
            this.state.savedColumns = saved;
            this.state.defaultColumns = (data && Array.isArray(data.default_columns)) ? data.default_columns.slice() : FALLBACK_DEFAULTS.slice();
            this.state.availableKeys = available.map(function (c) { return c.key; });
            this.state.columns = this.state.order
                .filter(function (k) { return k !== 'name' && byKey[k]; })
                .map(function (k) { return { key: k, label: byKey[k].label_key ? L(byKey[k].label_key) : String(byKey[k].label || k) }; });
            this.state.visible = {};
            this.state.columns.forEach(function (c) { self.state.visible[c.key] = saved[c.key] === '1'; });
            this.buildTable();
            this.renderColpanel();
        },
        renderColpanel: function () {
            var self = this;
            var $panel = $('#asset-groups-colpanel').empty();
            if (!$panel.length) { return; }
            $('<div>', { 'class': 'colpanel-search' }).append(
                $('<i>', { 'class': 'fa fa-search colpanel-search-icon', 'aria-hidden': 'true' }),
                // autocomplete via .attr(): jQuery UI's autocomplete widget is
                // a jQuery method name (see manage-assets.js).
                $('<input>', { type: 'text', 'class': 'colpanel-search-input', id: 'asset-groups-colpanel-search', placeholder: L('Search'), 'aria-label': L('Search') }).attr('autocomplete', 'off')
            ).appendTo($panel);
            var $g = $('<div>', { 'class': 'colpanel-group', 'data-group': 'group' }).append($('<div>', { 'class': 'colpanel-group-label', text: L('AssetGroupFields') }));
            // Locked on: the group's name never hides.
            [{ key: 'name', label: L('Name'), locked: true }].concat(this.state.columns).forEach(function (c) {
                $g.append($('<label>', { 'class': 'colpanel-item' }).append(
                    $('<input>', { type: 'checkbox', 'data-col': c.key, checked: c.locked ? true : !!self.state.visible[c.key], disabled: !!c.locked }),
                    document.createTextNode(' ' + c.label)
                ));
            });
            $panel.append($g);
            $('<div>', { 'class': 'colpanel-empty d-none', text: L('NoMatchingOptions') }).appendTo($panel);
            $('<div>', { 'class': 'colpanel-foot' }).append($('<button>', {
                type: 'button', 'class': 'btn btn-link btn-sm colpanel-reset', id: 'asset-groups-colpanel-reset', 'data-action': 'reset-columns', text: L('RestoreDefaultLayout')
            })).appendTo($panel);
        },
        // The default columns in the default order (drag order included).
        restoreDefaultColumns: function () {
            var self = this;
            var layout = U.defaultLayout(this.state.defaultColumns, this.state.availableKeys);
            this.state.order = layout.order;
            this.state.columns.forEach(function (c) { self.state.visible[c.key] = !!layout.visible[c.key]; });
            this.reorderStateColumns();
            if (this.state.table) {
                this.restoringOrder = true;
                U.applyDataColOrder(this.state.table, layout.order);
                this.restoringOrder = false;
            }
            this.renderColpanel();
            this.applyColumnVisibility();
            this.saveColumnsDebounced();
        },
        // state.columns follows state.order (the picker lists them in it).
        reorderStateColumns: function () {
            var byKey = {};
            this.state.columns.forEach(function (c) { byKey[c.key] = c; });
            this.state.columns = this.state.order.filter(function (k) { return byKey[k]; }).map(function (k) { return byKey[k]; });
        },
        // A header was dragged: the picker lists the new order and it is saved.
        orderChangedByDrag: function () {
            this.state.order = U.mergedOrder(this.state.order, U.dataColOrder(this.state.table));
            this.reorderStateColumns();
            this.renderColpanel();
            this.saveColumnsDebounced();
        },
        filterColpanelItems: function (query) {
            var q = String(query || '').toLowerCase();
            var any = false;
            $('#asset-groups-colpanel .colpanel-item').each(function () {
                var match = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
                $(this).toggleClass('d-none', !match);
                if (match) { any = true; }
            });
            $('#asset-groups-colpanel .colpanel-group').toggleClass('d-none', !any);
            $('#asset-groups-colpanel .colpanel-empty').toggleClass('d-none', any);
        },
        closeColpanel: function () {
            var $panel = $('#asset-groups-colpanel');
            if (!$panel.hasClass('d-none')) {
                $panel.addClass('d-none');
                $('#asset-groups-colpicker-btn').attr('aria-expanded', 'false');
                $('#asset-groups-colpanel-search').val('');
                this.filterColpanelItems('');
            }
        },
        bindColpicker: function () {
            var self = this;
            $(document).on('input', '#asset-groups-colpanel-search', function () { self.filterColpanelItems($(this).val()); });
            $('#asset-groups-colpicker-btn').on('click', function (e) {
                e.stopPropagation();
                var $panel = $('#asset-groups-colpanel').toggleClass('d-none');
                $(this).attr('aria-expanded', $panel.hasClass('d-none') ? 'false' : 'true');
                $('#asset-groups-colpanel-search').val('');
                self.filterColpanelItems('');
                if (!$panel.hasClass('d-none')) { $('#asset-groups-colpanel-search').trigger('focus'); }
            });
            $(document).on('click', function (e) {
                if (!$(e.target).closest('#asset-groups-panel .colpicker').length) { self.closeColpanel(); }
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && !$('#asset-groups-colpanel').hasClass('d-none')) {
                    self.closeColpanel();
                    $('#asset-groups-colpicker-btn').trigger('focus');
                }
            });
            $(document).on('click', '#asset-groups-colpanel-reset', function (e) {
                // The panel is rebuilt under this click: keep the
                // outside-click handler from reading it as a click outside.
                e.stopImmediatePropagation();
                self.restoreDefaultColumns();
                $('#asset-groups-colpanel-search').trigger('focus');
            });
            $(document).on('change', '#asset-groups-colpanel input[type="checkbox"]', function () {
                var key = String($(this).data('col'));
                if (key === 'name') { this.checked = true; return; }
                self.state.visible[key] = this.checked;
                self.applyColumnVisibility();
                self.saveColumnsDebounced();
            });
        },
        applyColumnVisibility: function () {
            var self = this;
            // Native visibility first (ColReorder's drop zones read it), then d-none.
            if (this.state.table) { U.syncNativeVisibility(this.state.table, this.state.visible); }
            this.state.columns.forEach(function (c) {
                $('#asset-groups-table [data-col="' + c.key + '"]').toggleClass('d-none', !self.state.visible[c.key]);
            });
        },
        // One PUT at a time (Manage assets' R5): a change made while a save is
        // in flight is sent once it settles; an unchanged payload is not
        // re-sent; a failed save puts the columns back to what is stored.
        saveColumns: function () {
            var self = this, st = this.colSave;
            if (st.inFlight) { st.pending = true; return; }
            var columns = this.state.order.map(function (k) {
                if (k === 'name') { return [k, '1']; }
                if (self.state.visible[k] !== undefined) { return [k, self.state.visible[k] ? '1' : '0']; }
                return [k, self.state.savedColumns[k] === '1' ? '1' : '0'];
            });
            var body = JSON.stringify({ columns: columns, order: this.state.order });
            if (body === st.lastSent) { return; }
            st.inFlight = true;
            $.ajax({
                url: api('/asset-groups/column-settings'), type: 'PUT',
                contentType: 'application/json', headers: U.csrfHeaders(), dataType: 'json', data: body,
                success: function () {
                    st.lastSent = body;
                    columns.forEach(function (p) { self.state.savedColumns[p[0]] = p[1]; });
                    st.inFlight = false;
                    if (st.pending) { st.pending = false; self.saveColumns(); }
                },
                error: function (xhr) {
                    if (U.reissuedByCsrfRetry(xhr, this)) { return; }
                    U.reportError(xhr);
                    st.pending = false;
                    st.inFlight = false;
                    self.state.columns.forEach(function (c) { self.state.visible[c.key] = self.state.savedColumns[c.key] === '1'; });
                    self.renderColpanel();
                    self.applyColumnVisibility();
                }
            });
        },

        buildHeader: function () {
            var self = this;
            var $tr = $('#asset-groups-table thead tr').empty();
            if (CAN_BULK_DELETE) {
                $('<th>', { 'class': 'sr-check-col', 'data-key': '_check' }).append($('<input>', { type: 'checkbox', 'class': 'form-check-input', id: 'asset-groups-select-page', 'aria-label': L('SelectAll') })).appendTo($tr);
            }
            $('<th>', { 'class': 'sr-caret-col', 'data-key': '_caret' }).append($('<span>', { 'class': 'visually-hidden', text: L('ViewGroupMembers') })).appendTo($tr);
            $('<th>', { 'class': 'sr-name-col dt-head-left', 'data-key': 'name', text: L('Name') }).appendTo($tr);
            this.state.columns.forEach(function (c) {
                $('<th>', { 'data-col': c.key, 'data-key': c.key, 'class': NUMBER_COLUMNS[c.key] ? 'sr-asset-groups-count-col' : 'dt-head-left', text: c.label })
                    .toggleClass('d-none', !self.state.visible[c.key]).appendTo($tr);
            });
            $('<th>', { 'class': 'sr-actions-col', 'data-key': '_actions' }).append($('<span>', { 'class': 'visually-hidden', text: L('Actions') })).appendTo($tr);
        },

        buildTable: function () {
            var self = this;
            var blank = function () { return ''; };
            this.buildHeader();
            var cols = [];
            this.colKeys = [];
            if (CAN_BULK_DELETE) {
                cols.push({ data: null, name: '_check', orderable: false, className: 'sr-check-col', render: blank, createdCell: function (td, d, row) { self.cellCheck(td, row); } });
                this.colKeys.push('_check');
                $('#asset-groups-table').addClass('sr-asset-groups-selectable');
            }
            cols.push(
                { data: null, name: '_caret', orderable: false, className: 'sr-caret-col', render: blank, createdCell: function (td) { self.cellCaret(td); } },
                { data: null, name: 'name', orderable: true, className: 'sr-name-col dt-head-left', render: blank, createdCell: function (td, d, row) { $(td).empty().append($('<span>', { 'class': 'sr-asset-groups-name', text: row.name })); } }
            );
            this.colKeys.push('_caret', 'name');
            this.state.columns.forEach(function (c) {
                self.colKeys.push(c.key);
                cols.push({
                    data: null, name: c.key, sr_moveable: true, orderable: SORTABLE.indexOf(c.key) !== -1, render: blank,
                    className: (NUMBER_COLUMNS[c.key] ? 'sr-asset-groups-count-col' : 'dt-head-left') + ' sr-asset-groups-col-' + c.key.replace(/[^a-z0-9_]/gi, ''),
                    createdCell: function (td, d, row) {
                        $(td).attr('data-col', c.key).attr('data-label', c.label).toggleClass('d-none', !self.state.visible[c.key]);
                        self.cellValue(td, c.key, row);
                        $(td).toggleClass('sr-assets-cell-empty', $(td).children('.sr-cell-dash').length > 0);
                    }
                });
            });
            cols.push({ data: null, name: '_actions', orderable: false, className: 'sr-actions-col', render: blank, createdCell: function (td, d, row) { self.cellActions(td, row); } });
            this.colKeys.push('_actions');

            this.state.table = $('#asset-groups-table').DataTable({
                serverSide: true,
                processing: true,
                searching: false,
                autoWidth: false,
                pagingType: 'simple_numbers',
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[this.colKeys.indexOf('name'), 'asc']],
                columns: cols,
                // Drag a configurable column's header to move it; the
                // checkbox, caret, name and actions columns stay put.
                colReorder: { columns: U.moveableColumns(cols) },
                language: { emptyTable: '', zeroRecords: '', lengthMenu: L('Show') + ' _MENU_', processing: L('Loading') },
                dom: 'rt<"sr-table-foot"<"sr-table-foot-left"li><"sr-table-foot-right"p>>',
                ajax: function (d, callback) { self.fetchPage(d, callback); },
                createdRow: function (tr, row) {
                    $(tr).attr('data-group-id', row.id).addClass('sr-group-row sr-asset-groups-row').attr('aria-expanded', 'false')
                        .toggleClass('sr-assets-row-checked', !!(self.state.selectAll || self.state.selected[String(row.id)]));
                },
                drawCallback: function () { self.afterDraw(); }
            });
            U.keepCellsPaintedOnReorder(this.state.table);
            this.restoringOrder = true;
            U.applyDataColOrder(this.state.table, this.state.order);
            this.restoringOrder = false;
            U.syncNativeVisibility(this.state.table, this.state.visible);
            this.state.table.on('column-reorder.dt', function () {
                if (self.restoringOrder) { return; }
                self.orderChangedByDrag();
            });
        },

        fetchPage: function (d, callback) {
            var self = this;
            var seq = ++this.fetchSeq;
            if (this.fetchXhr) { this.fetchXhr.abort(); }
            var ord = d.order && d.order[0];
            // By name, not position: a dragged column changes positions.
            var sortKey = (ord && d.columns && d.columns[ord.column]) ? d.columns[ord.column].name : 'name';
            var params = {
                aggregates: 1,
                page: Math.floor(d.start / d.length) + 1,
                per_page: d.length,
                sort: SORTABLE.indexOf(sortKey) !== -1 ? sortKey : 'name',
                dir: ord && ord.dir === 'desc' ? 'desc' : 'asc'
            };
            if (this.state.q) { params.q = this.state.q; }
            // A different page, page size or sort shows different rows: the
            // selection resets, as on the Assets tab.
            var pageKey = [d.start, d.length, params.sort, params.dir].join('|');
            if (this.state.pageKey && pageKey !== this.state.pageKey) {
                this.state.selected = {};
                this.state.selectAll = false;
            }
            this.state.pageKey = pageKey;
            var f = this.state.filters;
            FILTER_KEYS.forEach(function (k) { if (f[k].length) { params[k] = f[k].join(','); } });
            this.fetchXhr = $.ajax({ url: api('/asset-groups'), type: 'GET', data: params, dataType: 'json' })
                .done(function (json) {
                    if (seq !== self.fetchSeq) { return; }
                    self.fetchXhr = null;
                    var data = (json && json.data) || {};
                    self.state.loadFailed = false;
                    self.state.rows = data.asset_groups || [];
                    self.state.total = data.total || 0;
                    self.state.page = Math.floor(d.start / d.length);
                    // Unsearched and unfiltered, the total is the whole collection: the tab count.
                    if (!self.state.q && !self.filterValueCount()) { $('#manage-assets-tab-count-groups').text(self.state.total); }
                    callback({ draw: d.draw, recordsTotal: self.state.total, recordsFiltered: self.state.total, data: self.state.rows });
                })
                .fail(function (xhr) {
                    if (seq !== self.fetchSeq || U.isAbort(xhr)) { return; }
                    self.fetchXhr = null;
                    self.state.loadFailed = true;
                    self.state.rows = [];
                    self.state.total = 0;
                    callback({ draw: d.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                });
        },

        afterDraw: function () {
            var self = this;
            var colspan = $('#asset-groups-table thead th').length;
            // Past the last page (a delete emptied it): step back instead of
            // reporting an empty collection.
            if (!this.state.loadFailed && !this.state.rows.length && this.state.total > 0 && this.state.page > 0) {
                var len = this.state.table.page.len();
                this.state.table.page(Math.max(0, Math.ceil(this.state.total / len) - 1)).draw(false);
                return;
            }
            if (this.state.loadFailed) {
                this.renderStateRow(colspan, 'error');
            } else if (this.state.total === 0) {
                this.renderStateRow(colspan, this.state.q || this.filterValueCount() ? 'no-results' : 'no-data');
            }
            $('#asset-groups-panel .sr-table-foot').toggleClass('d-none', this.state.loadFailed || !this.state.rows.length);
            this.syncSortIcons();
            this.syncBulkBar();
            // Drawers that were open before the redraw open again.
            var open = this.openDrawers;
            this.openDrawers = {};
            $('#asset-groups-table tbody tr[data-group-id]').each(function () {
                if (open[String($(this).attr('data-group-id'))]) { self.openDrawer($(this)); }
            });
            // A chip click redrew the table under the focused chip: focus the
            // same chip again when the new page still has it.
            var rf = this.state.refocusChip;
            if (rf) {
                this.state.refocusChip = null;
                $('#asset-groups-table .sr-assets-chip-filter').filter(function () {
                    return $(this).attr('data-filter-kind') === rf.kind && $(this).attr('data-filter-id') === rf.id;
                }).first().trigger('focus');
            }
            if (this.focusAfterDraw) {
                $('#asset-groups-table tbody tr[data-group-id="' + this.focusAfterDraw + '"] .sr-group-caret').trigger('focus');
                this.focusAfterDraw = null;
            }
        },

        syncSortIcons: function () {
            $('#asset-groups-table thead th').each(function () {
                var $th = $(this);
                if (!$th.is('.dt-orderable-asc, .dt-orderable-desc')) { return; }
                var $order = $th.find('.dt-column-order').addClass('sr-sort-native-off');
                if (!$order.length) { return; }
                $th.addClass('sr-sortable');
                var $icon = $order.find('.sr-sort-icon');
                if (!$icon.length) { $icon = $('<i>', { 'class': 'fa sr-sort-icon', 'aria-hidden': 'true' }).appendTo($order); }
                var sort = $th.attr('aria-sort');
                $th.toggleClass('is-sorted', sort === 'ascending' || sort === 'descending');
                $icon.removeClass('fa-arrow-up-short-wide fa-arrow-down-wide-short fa-sort')
                    .addClass(sort === 'ascending' ? 'fa-arrow-up-short-wide' : sort === 'descending' ? 'fa-arrow-down-wide-short' : 'fa-sort');
            });
        },

        showLoadingRow: function () {
            $('#asset-groups-table tbody').empty().append($('<tr>', { 'class': 'sr-empty-row' }).append(
                $('<td>', { colspan: 99, 'class': 'sr-assets-loading' }).append(
                    $('<i>', { 'class': 'fa fa-spinner fa-spin', 'aria-hidden': 'true' }), ' ', $('<span>', { text: L('Loading') })
                )
            ));
        },

        // design-system 10: the reason decides the copy and the one action.
        renderStateRow: function (colspan, intent) {
            var $box = $('<div>', { 'class': 'sr-table-empty', id: 'asset-groups-empty', 'data-intent': intent });
            var $icon = $('<div>', { 'class': 'sr-table-empty-icon' });
            var $action = $('<div>', { 'class': 'sr-table-empty-action' });
            if (intent === 'error') {
                $box.addClass('sr-table-empty-danger');
                $icon.append($('<i>', { 'class': 'fa fa-triangle-exclamation', 'aria-hidden': 'true' }));
                $box.append($icon, $('<div>', { 'class': 'sr-table-empty-title', text: L('CouldNotLoadAssetGroups') }));
                $action.append($('<button>', { type: 'button', 'class': 'btn btn-outline-secondary btn-sm', id: 'asset-groups-retry', 'data-action': 'retry', text: L('Retry') }));
            } else if (intent === 'no-results') {
                $icon.append($('<i>', { 'class': 'fa fa-magnifying-glass', 'aria-hidden': 'true' }));
                var filtered = this.filterValueCount() > 0;
                $box.append($icon, $('<div>', { 'class': 'sr-table-empty-title', text: L(filtered ? 'NoAssetGroupsMatchFilters' : 'NoAssetGroupsMatchSearch') }));
                $action.append($('<button>', { type: 'button', 'class': 'btn btn-link btn-sm', id: 'asset-groups-empty-clear', 'data-action': filtered ? 'clear-filters' : 'clear-search', text: L(filtered ? 'ClearFilters' : 'Clear') }));
            } else {
                $icon.append($('<i>', { 'class': 'fa fa-layer-group', 'aria-hidden': 'true' }));
                $box.append($icon, $('<div>', { 'class': 'sr-table-empty-title', text: L('NoAssetGroupsYet') }),
                    $('<div>', { 'class': 'sr-table-empty-body', text: L('NoAssetGroupsYetHint') }));
                // Outline: the tab row's Add group stays the one red button.
                if (caps.can_group_create) {
                    $action.append($('<button>', { type: 'button', 'class': 'btn btn-outline-secondary btn-sm', id: 'asset-groups-empty-add', 'data-action': 'group-add', text: '+ ' + L('AddAssetGroup') }));
                }
            }
            $box.append($action);
            $('#asset-groups-table tbody').empty().append(
                $('<tr>', { 'class': 'sr-empty-row' }).append($('<td>', { colspan: colspan }).append($box))
            );
        },

        /* ---------------- cells ---------------- */
        cellCheck: function (td, row) {
            var id = String(row.id);
            $(td).empty().append($('<input>', {
                type: 'checkbox', 'class': 'form-check-input sr-asset-groups-row-check', 'data-id': id,
                'aria-label': L('Select') + ' ' + (row.name || ''),
                checked: !!(this.state.selectAll || this.state.selected[id])
            }));
        },
        cellCaret: function (td) {
            // The shipped .sr-group-caret <span> (plan-projects' shape); its
            // rotation keys on tr.sr-group-row[aria-expanded="true"].
            $(td).empty().append($('<span>', {
                'class': 'sr-group-caret', role: 'button', tabindex: 0, 'data-action': 'group-toggle',
                'aria-label': L('ViewGroupMembers')
            }).append($('<i>', { 'class': 'fa fa-chevron-right', 'aria-hidden': 'true' })));
        },
        cellNumber: function (td, n, labelKey) {
            $(td).attr('data-label', L(labelKey)).empty().append(n == null ? U.dash() : $('<span>', { 'class': 'sr-num', text: String(n) }));
        },
        // Team / Site/Location / Tag chips: Manage assets' chip list (three
        // chips then "+N", bounded width), each a filter chip when the
        // matching filter offers its id (chipFilterable()), else plain.
        cellIdChips: function (td, ids, names, kind) {
            var pairs = { names: [], ids: [] };
            (ids || []).forEach(function (id) {
                var n = names[String(id)];
                if (n != null) { pairs.names.push(String(n)); pairs.ids.push(String(id)); }
            });
            $(td).empty().append(pairs.names.length ? MA.chipList(pairs.names, 0, { kind: kind, ids: pairs.ids }) : U.dash());
        },
        cellValue: function (td, key, row) {
            switch (key) {
                case 'asset_count':
                case 'risk_count':
                    this.cellNumber(td, row[key], NUMBER_COLUMNS[key]);
                    break;
                case 'max_valuation':
                    $(td).empty().append(valuationChip(row.max_valuation));
                    break;
                case 'teams':
                    this.cellIdChips(td, row.teams, this.teamNames, 'team');
                    break;
                case 'locations':
                    this.cellIdChips(td, row.locations, this.locationNames, 'location');
                    break;
                case 'tags':
                    this.cellIdChips(td, row.tags, this.tagNames, 'tag');
                    break;
                case 'highest_fips_categorization':
                    // Coloured by level, a filter chip like Manage assets'.
                    $(td).empty().append(levelFilterChip('categorization', row[key]));
                    break;
                case 'highest_weighted_band':
                    $(td).empty().append(levelFilterChip('band', row[key]));
                    break;
                case 'highest_weighted_score':
                    $(td).empty().append(U.scoreChip(row.highest_weighted_score, row.highest_weighted_band));
                    break;
                default:
                    $(td).empty().append(U.dash());
            }
        },
        cellActions: function (td, row) {
            var id = String(row.id);
            var $menu = $('<span>', { 'class': 'sr-row-actions', role: 'menu' });
            var add = function (action, icon, labelKey, danger) {
                $menu.append($('<button>', {
                    type: 'button', role: 'menuitem',
                    'class': 'sr-row-action' + (danger ? ' sr-row-action-danger' : ''),
                    'data-action': action, 'data-id': id, title: L(labelKey), 'aria-label': L(labelKey)
                }).append($('<i>', { 'class': 'fa ' + icon, 'aria-hidden': 'true' })));
            };
            // Actions the user lacks are omitted, never disabled.
            add('group-members', 'fa-eye', 'ViewGroupMembers');
            if (caps.can_group_edit) { add('group-edit', 'fa-pen', 'EditAssetGroup'); }
            if (caps.can_group_delete) { add('group-delete', 'fa-trash', 'DeleteAssetGroup', true); }
            $(td).empty().append($('<span>', { 'class': 'sr-row-actions-wrap' }).append(
                $('<button>', { type: 'button', 'class': 'sr-row-actions-toggle', 'aria-expanded': 'false', 'aria-haspopup': 'true', 'aria-label': L('Actions'), 'data-action': 'row-menu' })
                    .append($('<i>', { 'class': 'fa fa-ellipsis', 'aria-hidden': 'true' })),
                $menu
            ));
        },
        rowById: function (id) {
            id = String(id);
            for (var i = 0; i < this.state.rows.length; i++) {
                if (String(this.state.rows[i].id) === id) { return this.state.rows[i]; }
            }
            return null;
        },

        /* ---------------- selection + bulk bar (Manage assets' mechanics) ---------------- */
        selectedIds: function () { return Object.keys(this.state.selected).map(Number); },
        selectedCount: function () {
            return this.state.selectAll ? this.state.total : this.selectedIds().length;
        },
        // Selection ids come from the rows on screen, never from a data array
        // a late response could replace.
        renderedRows: function () {
            return $('#asset-groups-table tbody tr[data-group-id]').map(function () {
                return { id: String($(this).attr('data-group-id')), name: $(this).find('.sr-asset-groups-name').text() };
            }).get();
        },
        bindSelection: function () {
            var self = this;
            if (!CAN_BULK_DELETE) { return; }
            var $t = $('#asset-groups-table');
            $t.on('change', '.sr-asset-groups-row-check', function () {
                var id = String($(this).data('id'));
                if (self.state.selectAll) {
                    // Leaving "all N": keep this page's rows, minus the one unticked.
                    self.state.selectAll = false;
                    self.state.selected = {};
                    self.renderedRows().forEach(function (r) { self.state.selected[r.id] = r.name; });
                }
                if (this.checked) {
                    self.state.selected[id] = $(this).closest('tr').find('.sr-asset-groups-name').text();
                } else {
                    delete self.state.selected[id];
                }
                self.syncBulkBar();
            });
            $t.on('change', '#asset-groups-select-page', function () {
                var on = this.checked;
                if (!on) { self.state.selectAll = false; }
                self.renderedRows().forEach(function (r) {
                    if (on) { self.state.selected[r.id] = r.name; } else { delete self.state.selected[r.id]; }
                });
                self.syncBulkBar();
            });
            $('#asset-groups-bulk-clear').on('click', function () {
                self.state.selected = {};
                self.state.selectAll = false;
                self.syncBulkBar();
                $('#asset-groups-search').trigger('focus');
            });
            $('#asset-groups-select-all-filtered').on('click', function () {
                self.state.selectAll = true;
                self.syncBulkBar();
            });
            $('#asset-groups-bulk-delete').on('click', function () { self.openBulkDelete(self.selection()); });
        },
        // The selection as a request target: the search + filters when
        // escalated to "all N" (never N ids), else the ticked ids.
        selection: function () {
            if (this.state.selectAll) { return { filter: this.bulkFilter(), count: this.state.total }; }
            var ids = this.selectedIds();
            return { ids: ids, count: ids.length, name: ids.length === 1 ? this.state.selected[String(ids[0])] : null };
        },
        // The list's search and filters as POST /asset-groups/bulk's filter;
        // the unfiltered list is "every group", said explicitly.
        bulkFilter: function () {
            var s = this.state, f = {};
            if (s.q) { f.q = s.q; }
            FILTER_KEYS.forEach(function (k) { if (s.filters[k].length) { f[k] = s.filters[k].map(Number); } });
            return $.isEmptyObject(f) ? { all: true } : f;
        },
        syncBulkBar: function () {
            if (!CAN_BULK_DELETE) { return; }
            var self = this;
            var n = this.selectedCount();
            var $checks = $('#asset-groups-table .sr-asset-groups-row-check');
            $checks.each(function () {
                var on = !!(self.state.selectAll || self.state.selected[String($(this).data('id'))]);
                this.checked = on;
                $(this).closest('tr').toggleClass('sr-assets-row-checked', on);
            });
            var checked = $checks.filter(':checked').length;
            $('#asset-groups-select-page').prop('checked', $checks.length > 0 && checked === $checks.length)
                .prop('indeterminate', checked > 0 && checked < $checks.length);
            // The bar takes the toolbar's place while anything is selected.
            $('#asset-groups-toolbar').toggleClass('d-none', n > 0);
            if (n > 0) { this.closeColpanel(); }
            $('#asset-groups-bulk-bar').toggleClass('d-none', n === 0);
            $('#asset-groups-bulk-count').text(this.state.selectAll
                ? U.fmt('AssetGroupBulkAllSelected', { count: this.state.total })
                : String(L('NSelected')).replace('{n}', n));
            var offer = !this.state.selectAll && n > 0 && this.state.total > n;
            $('#asset-groups-select-all-filtered').toggleClass('d-none', !offer)
                .text(U.fmt('AssetGroupBulkSelectAll', { count: this.state.total }));
        },
        openBulkDelete: function (target) {
            if (!CAN_BULK_DELETE || !target.count || this.state.bulkBusy) { return; }
            this.rememberOpener();
            this.deleting = null;
            this.pendingDelete = target;
            var single = !target.filter && target.count === 1;
            $('#asset-group-delete-title').text(single
                ? U.fmt('AssetGroupDeleteConfirmTitle', { name: target.name || ('#' + target.ids[0]) })
                : U.fmt('AssetGroupBulkDeleteConfirmTitle', { count: target.count }));
            $('#asset-group-delete-note').text(L(single ? 'AssetGroupDeleteKeepsAssets' : 'AssetGroupBulkDeleteKeepsAssets'));
            $('#asset-group-delete-confirm').text(L(single ? 'DeleteAssetGroup' : 'DeleteAssetGroups'));
            $('#asset-group-delete-modal').modal('show');
        },
        // POST /asset-groups/bulk; one request at a time. "Select all N"
        // sends the N it showed, and a changed set is refused (409).
        runBulkDelete: function ($btn, $modal) {
            var self = this, target = this.pendingDelete;
            if (!target || this.state.bulkBusy) { return; }
            this.state.bulkBusy = true;
            $btn.prop('disabled', true).addClass('is-busy');
            var body = { action: 'delete' };
            if (target.filter) { body.filter = target.filter; body.expected_count = target.count; } else { body.ids = target.ids; }
            var done = function () {
                self.state.bulkBusy = false;
                $btn.prop('disabled', false).removeClass('is-busy');
            };
            $.ajax({
                type: 'POST', url: api('/asset-groups/bulk'),
                contentType: 'application/json', headers: U.csrfHeaders(), dataType: 'json',
                data: JSON.stringify(body),
                success: function (json) {
                    done();
                    var res = (json && json.data) || {};
                    self.toastBulkResults(res.summary || { ok: 0, failed: 0 }, res.results || []);
                    (res.results || []).forEach(function (r) { if (r.status === 'ok') { delete self.openDrawers[String(r.id)]; } });
                    self.pendingDelete = null;
                    self.state.selected = {};
                    self.state.selectAll = false;
                    $modal.modal('hide');
                    self.afterMutation();
                },
                error: function (xhr) {
                    if (U.reissuedByCsrfRetry(xhr, this)) { return; }
                    done();
                    U.reportError(xhr);
                    if (xhr && xhr.status === 409) {
                        // The set changed since it was selected: show the
                        // current list and make the user select again.
                        self.pendingDelete = null;
                        self.state.selected = {};
                        self.state.selectAll = false;
                        $modal.modal('hide');
                        self.reload(false);
                    }
                }
            });
        },
        // One toast (design-system 9): the summary, then the skipped groups.
        // Escaped once here; toastr decodes.
        toastBulkResults: function (summary, results) {
            var self = this;
            var lines = [U.esc(U.fmt('AssetGroupBulkDeletedSummary', { ok: summary.ok || 0, failed: summary.failed || 0 }))];
            var skipped = results.filter(function (r) { return r.status !== 'ok'; });
            if (skipped.length) {
                var items = skipped.slice(0, 10).map(function (r) {
                    var row = self.rowById(r.id);
                    return (row && row.name ? row.name : '#' + r.id) + ' (' + L(r.status === 'not_found' ? 'AssetBulkReasonNotFound' : 'Failed') + ')';
                });
                if (skipped.length > 10) { items.push('…'); }
                lines.push(U.esc(U.fmt('AssetBulkSkippedList', { list: items.join(', ') })));
            }
            showAlertFromMessage(lines.join('<br>'), skipped.length === 0);
        },

        /* ---------------- rows, drawer ---------------- */
        bindRows: function () {
            var self = this;
            if (window.SRRowActionsMenu) {
                SRRowActionsMenu.bind({ container: '#asset-groups-table', scope: $('#asset-groups-table'), namespace: 'assetgroups' });
            }
            var $t = $('#asset-groups-table');
            $t.on('click', '[data-action="group-toggle"]', function () { self.toggleDrawer($(this).closest('tr')); });
            $t.on('keydown', '[data-action="group-toggle"]', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); self.toggleDrawer($(this).closest('tr')); }
            });
            $t.on('click', '[data-action="group-members"]', function () {
                var $tr = $t.find('tr[data-group-id="' + String($(this).data('id')) + '"]');
                if ($tr.attr('aria-expanded') !== 'true') { self.toggleDrawer($tr); }
                $tr.find('.sr-group-caret').trigger('focus');
            });
            $t.on('click', '[data-action="group-edit"]', function () { self.rememberOpener(); self.openEdit(self.rowById($(this).data('id'))); });
            $t.on('click', '[data-action="group-delete"]', function () { self.rememberOpener(); self.openDelete(self.rowById($(this).data('id'))); });
            $t.on('click', '[data-action="member-remove"]', function () {
                self.removeMember(String($(this).data('group-id')), String($(this).data('asset-id')), $(this));
            });
            $t.on('click', '[data-action="member-status"]', function () { MA.showGroup($(this).data('group-id'), String($(this).data('status'))); });
            $t.on('click', '[data-action="group-view-all"]', function () { MA.showGroup($(this).data('group-id')); });
            $t.on('click', '[data-action="member-view"]', function () {
                // The asset record modal in View (the URL becomes ?asset=<id>;
                // Esc / Cancel / Back return here with the drawer still open).
                MA.openRecord($(this).attr('data-asset-id'), 'view');
            });
            $t.on('click', 'th.sr-sortable[data-member-sort]', function () {
                self.toggleMemberSort($(this).attr('data-group-id'), $(this).attr('data-member-sort'));
            });
            // Enter / Space on a focused header (th, not a button: without key
            // handling tabindex alone would be a dead stop).
            $t.on('keydown', 'th.sr-sortable[data-member-sort]', function (e) {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                    e.preventDefault();
                    self.toggleMemberSort($(this).attr('data-group-id'), $(this).attr('data-member-sort'));
                }
            });
            $t.on('click', '[data-action="member-retry"]', function () {
                var gid = String($(this).data('group-id'));
                self.loadMembers(gid);
            });
        },

        toggleDrawer: function ($tr) {
            var gid = String($tr.attr('data-group-id'));
            var $existing = $tr.next('tr.sr-expand-row[data-drawer-for="' + gid + '"]');
            if ($existing.length) {
                $existing.remove();
                $tr.attr('aria-expanded', 'false');
                delete this.openDrawers[gid];
                return;
            }
            this.openDrawer($tr);
        },

        openDrawer: function ($tr) {
            var gid = String($tr.attr('data-group-id'));
            this.openDrawers[gid] = true;
            var colspan = $('#asset-groups-table thead th').length;
            $tr.after($('<tr>', { 'class': 'sr-expand-row', 'data-drawer-for': gid }).append(
                $('<td>', { colspan: colspan }).append($('<div>', { 'class': 'sr-expand-panel' }))
            ));
            $tr.attr('aria-expanded', 'true');
            this.loadMembers(gid);
        },

        drawerPanel: function (gid) {
            return $('#asset-groups-table tr.sr-expand-row[data-drawer-for="' + gid + '"] .sr-expand-panel');
        },

        loadMembers: function (gid) {
            var self = this;
            this.drawerPanel(gid).empty().append($('<div>', { 'class': 'sr-subtable-loading', text: L('Loading') }));
            var sort = this.memberSortState(gid);
            $.ajax({ url: api('/asset-groups/' + encodeURIComponent(gid) + '/assets'), type: 'GET', data: { per_page: MEMBER_LIMIT, page: 1, sort: sort.key, dir: sort.dir }, dataType: 'json' })
                .done(function (json) { self.renderMembers(gid, (json && json.data) || {}); })
                .fail(function () {
                    self.drawerPanel(gid).empty().append($('<div>', { 'class': 'sr-empty-tests' }).append(
                        $('<span>', { text: L('CouldNotLoadGroupMembers') }),
                        $('<button>', { type: 'button', 'class': 'btn btn-link btn-sm', 'data-action': 'member-retry', 'data-group-id': gid, text: L('Retry') })
                    ));
                });
        },

        renderMembers: function (gid, data) {
            var $panel = this.drawerPanel(gid).empty();
            var members = data.assets || [];
            var total = data.total || 0;
            if (!members.length) {
                $panel.append($('<div>', { 'class': 'sr-empty-tests' }).append($('<span>', { text: L('NoAssetsInGroup') })));
                return;
            }
            var sort = this.memberSortState(gid);
            // Sortable headers: plan-projects' drawer contract (th.sr-sortable,
            // role=columnheader, tabindex 0, aria-sort, .sr-sort-icon). The
            // drawer shows one page of members, so a sort asks the server
            // again (it sorts every visible member before paging).
            var th = function (key, labelKey) {
                var active = sort.key === key;
                var icon = active ? (sort.dir === 'desc' ? 'fa-arrow-down-wide-short' : 'fa-arrow-up-short-wide') : 'fa-sort';
                return $('<th>', {
                    'class': 'sr-sortable' + (active ? ' is-sorted' : ''), role: 'columnheader', tabindex: 0,
                    'data-member-sort': key, 'data-group-id': gid,
                    'aria-sort': active ? (sort.dir === 'desc' ? 'descending' : 'ascending') : 'none'
                }).append(document.createTextNode(L(labelKey)), $('<i>', { 'class': 'fa sr-sort-icon ' + icon, 'aria-hidden': 'true' }));
            };
            var $head = $('<tr>').append(th('name', 'AssetName'), th('value', 'AssetValuation'), $('<th>', { text: L('Status') }));
            if (cfg.lookups && cfg.lookups.scoring_enabled) {
                $head.append(th('fips_categorization', 'FIPSCategorization'), th('weighted_score', 'WeightedScore'), th('weighted_band', 'WeightedBand'));
            }
            $head.append($('<th>').append($('<span>', { 'class': 'visually-hidden', text: L('Actions') })));
            var $body = $('<tbody>');
            members.forEach(function (a) {
                var verified = String(a.verified) === '1';
                var $tr = $('<tr>', { 'data-asset-id': a.id }).append(
                    $('<td>').append($('<span>', { 'class': 'sr-cell-truncate', title: String(a.name || ''), text: String(a.name || '') })),
                    $('<td>').append(valuationChip(a.value)),
                    // Same pattern as "View all in the Assets tab": jumps to the
                    // Assets tab filtered to this group AND this status.
                    $('<td>').append($('<button>', {
                        type: 'button',
                        'class': 'sr-assets-chip-filter sr-state-pill ' + (verified ? 'sr-state-success' : 'sr-state-warning'),
                        'data-action': 'member-status', 'data-group-id': gid, 'data-status': verified ? 'verified' : 'unverified',
                        title: L(verified ? 'AssetShowOnlyVerified' : 'AssetShowOnlyUnverified'),
                        'aria-label': L(verified ? 'AssetShowOnlyVerified' : 'AssetShowOnlyUnverified'),
                        text: verified ? L('Verified') : L('Unverified')
                    }))
                );
                if (cfg.lookups && cfg.lookups.scoring_enabled) {
                    // The member's own results: plain chips (a per-asset value,
                    // not something this table filters by).
                    $tr.append(
                        $('<td>', { 'data-member-col': 'fips_categorization' }).append(U.levelChip(a.fips_categorization)),
                        $('<td>', { 'data-member-col': 'weighted_score' }).append(U.scoreChip(a.weighted_score, a.weighted_band)),
                        $('<td>', { 'data-member-col': 'weighted_band' }).append(U.levelChip(a.weighted_band))
                    );
                }
                // Standing buttons, not a .sr-row-actions cluster: the card's
                // compact tier turns every such cluster into a hidden overflow
                // menu, and two actions need no menu.
                var $actions = $('<td>', { 'class': 'sr-asset-groups-member-action' }).append(
                    $('<button>', {
                        type: 'button', 'class': 'sr-row-action', 'data-action': 'member-view',
                        'data-group-id': gid, 'data-asset-id': a.id,
                        title: L('ViewAsset'), 'aria-label': L('ViewAsset') + ': ' + String(a.name || '')
                    }).append($('<i>', { 'class': 'fa fa-eye', 'aria-hidden': 'true' }))
                );
                if (caps.can_group_edit) {
                    $actions.append($('<button>', {
                        type: 'button', 'class': 'sr-row-action', 'data-action': 'member-remove',
                        'data-group-id': gid, 'data-asset-id': a.id,
                        title: L('RemoveFromGroup'), 'aria-label': L('RemoveFromGroup') + ': ' + String(a.name || '')
                    }).append($('<i>', { 'class': 'fa fa-link-slash', 'aria-hidden': 'true' })));
                }
                $tr.append($actions);
                $body.append($tr);
            });
            $panel.append($('<table>', { 'class': 'sr-subtable' }).append($('<thead>').append($head), $body));
            // A header sort rebuilt the drawer: keep focus on the header used.
            if (this.focusMemberSort && this.focusMemberSort.gid === String(gid)) {
                $panel.find('th[data-member-sort="' + this.focusMemberSort.key + '"]').trigger('focus');
                this.focusMemberSort = null;
            }
            if (total > members.length) {
                $panel.append($('<div>', { 'class': 'sr-subtable-foot' }).append(
                    $('<div>', { 'class': 'sr-subtable-more' }).append(
                        document.createTextNode(U.fmt('AssetGroupMoreMembers', { count: total - members.length }) + ' · '),
                        $('<button>', { type: 'button', 'class': 'btn btn-link btn-sm sr-asset-groups-view-all', 'data-action': 'group-view-all', 'data-group-id': gid, text: L('ViewAllInAssetsTab') })
                    )
                ));
            }
        },

        memberSortState: function (gid) {
            return this.memberSort[String(gid)] || MEMBER_SORT_DEFAULT;
        },
        toggleMemberSort: function (gid, key) {
            var cur = this.memberSortState(gid);
            var dir = cur.key === key ? (cur.dir === 'asc' ? 'desc' : 'asc') : (MEMBER_SORT_FIRST_DIR[key] || 'asc');
            this.memberSort[String(gid)] = { key: key, dir: dir };
            this.focusMemberSort = { gid: String(gid), key: key };
            this.loadMembers(String(gid));
        },

        removeMember: function (gid, aid, $btn) {
            var self = this;
            if (!caps.can_group_edit || this.busy) { return; }
            this.busy = true;
            $btn.prop('disabled', true);
            $.ajax({
                url: api('/asset-groups/' + encodeURIComponent(gid) + '/assets/' + encodeURIComponent(aid)),
                type: 'DELETE', headers: U.csrfHeaders(), dataType: 'json',
                success: function (json) {
                    self.busy = false;
                    toastServer(json, 'RemoveFromGroup');
                    // The redraw refreshes this row's counts and re-opens its
                    // drawer; focus lands on that row's caret (the button that
                    // was clicked no longer exists).
                    self.openDrawers[gid] = true;
                    self.focusAfterDraw = gid;
                    self.reload(false);
                    MA.refresh(false, true);
                },
                error: function (xhr) {
                    if (U.reissuedByCsrfRetry(xhr, this)) { return; }
                    self.busy = false;
                    $btn.prop('disabled', false);
                    U.reportError(xhr);
                }
            });
        },

        // After a group is created, renamed or deleted: the Assets tab's group
        // filter, its Add to group picker and the tab count follow.
        refreshGroupLookups: function () {
            $.ajax({ url: api('/asset-groups'), type: 'GET', dataType: 'json' })
                .done(function (json) {
                    var list = ((json && json.data && json.data.asset_groups) || []).map(function (g) { return { id: g.id, name: g.name }; });
                    MA.setGroups(list);
                });
        },

        afterMutation: function () {
            this.reload(false);
            this.refreshGroupLookups();
            MA.refresh(false, true);
        },

        rememberOpener: function () {
            var $wrap = $(document.activeElement).closest('.sr-row-actions-wrap');
            this.lastOpener = $wrap.length
                ? { rowId: String($wrap.closest('tr').attr('data-group-id')) }
                : { el: document.activeElement && document.activeElement !== document.body ? document.activeElement : null };
        },
        restoreFocus: function () {
            var o = this.lastOpener || {};
            this.lastOpener = null;
            var $target = $();
            if (o.rowId) {
                $target = $('#asset-groups-table tr[data-group-id="' + o.rowId + '"] .sr-row-actions-toggle');
            } else if (o.el && document.body.contains(o.el) && $(o.el).is(':visible')) {
                $target = $(o.el);
            }
            if (!$target.length) { $target = $('#asset-groups-search'); }
            $target.first().trigger('focus');
        },

        /* ---------------- Add / Edit group ---------------- */
        bindGroupModal: function () {
            var self = this;
            var $modal = $('#asset-group-modal');
            if (!$modal.length) { return; }
            $modal.on('shown.bs.modal', function () { $('#asset-group-name').trigger('focus'); });
            $modal.on('hidden.bs.modal', function () {
                // The picker closing leaves this modal open; only a real close restores focus.
                self.restoreFocus();
            });
            $('#asset-group-name').on('input', function () { self.setNameError(null); });
            $('#asset-group-name').on('blur', function () {
                if (!String($(this).val() || '').trim() && $modal.hasClass('show')) { self.setNameError(U.fmt('FieldRequired', { field: L('Name') })); }
            });
            $('#asset-group-form').on('submit', function (e) { e.preventDefault(); self.save(); });
            $modal.on('click', '.sr-chips-add', function () {
                var $sel = $('#asset-group-members');
                var known = {};
                $sel.find('option').each(function () { known[String(this.value)] = { name: $(this).text(), ip: '' }; });
                Picker.open({
                    chosen: ($sel.val() || []).map(String),
                    known: known,
                    onCommit: function (ids, map) { self.setMembers(ids.map(function (id) { return { id: id, name: (map[id] || {}).name || ('#' + id) }; })); }
                });
            });
            // Removing a chip edits the field directly (not the picker's copy).
            $modal.on('click', '.sr-chips-field .sr-chip-remove[data-asset-id]', function () {
                var removed = String($(this).attr('data-asset-id'));
                $('#asset-group-members option').filter(function () { return String(this.value) === removed; }).remove();
                self.renderMemberChips();
                $('#asset-group-members').next('.sr-chips-field').find('.sr-chips-add').trigger('focus');
            });
        },

        setNameError: function (msg) {
            $('#asset-group-name').toggleClass('is-invalid', !!msg).attr('aria-invalid', msg ? 'true' : 'false');
            $('#asset-group-name-error').text(msg || '');
        },

        // members: [{id, name}] -- the hidden <select multiple> is the value.
        setMembers: function (members) {
            var $sel = $('#asset-group-members').empty();
            members.slice().sort(function (a, b) { return String(a.name).localeCompare(String(b.name)); }).forEach(function (m) {
                $('<option>', { value: m.id, text: m.name, selected: true }).appendTo($sel);
            });
            this.renderMemberChips();
        },

        renderMemberChips: function () {
            var $sel = $('#asset-group-members');
            var $field = $sel.next('.sr-chips-field');
            if (!$field.length) { $field = $('<div>', { 'class': 'sr-chips-field', id: 'asset-group-members-field' }).insertAfter($sel); }
            $field.empty();
            var $opts = $sel.find('option');
            $opts.slice(0, CHIP_LIMIT).each(function () {
                var name = $(this).text();
                $field.append($('<span>', { 'class': 'sr-chip', title: name }).append(
                    $('<span>', { text: name }),
                    $('<button>', { type: 'button', 'class': 'sr-chip-remove', 'data-asset-id': this.value, 'aria-label': L('Remove') + ' ' + name, text: '×' })
                ));
            });
            if ($opts.length > CHIP_LIMIT) {
                $field.append($('<span>', { 'class': 'sr-assets-more', text: '+' + ($opts.length - CHIP_LIMIT) }));
            }
            // Disabled while the current members are still loading: a commit
            // made before they arrive would be overwritten by setMembers().
            $field.append($('<button>', { type: 'button', 'class': 'sr-chips-add', 'data-action': 'choose-assets', text: L('AddOrRemoveAssets'), disabled: !!this.membersLoading }));
        },

        openAdd: function () {
            if (!caps.can_group_create) { return; }
            if (!this.lastOpener) { this.rememberOpener(); }
            this.editing = null;
            this.membersLoading = false;
            $('#asset-group-modal-title').text(L('AddAssetGroup'));
            $('#asset-group-name').val('');
            this.setNameError(null);
            this.setMembers([]);
            $('#asset-group-save').prop('disabled', false).removeClass('is-busy');
            $('#asset-group-modal').modal('show');
        },

        openEdit: function (group) {
            var self = this;
            if (!caps.can_group_edit || !group) { return; }
            this.editing = group;
            this.membersLoading = true;
            $('#asset-group-modal-title').text(L('EditAssetGroup'));
            $('#asset-group-name').val(group.name);
            this.setNameError(null);
            this.setMembers([]);
            // Every visible member (not a page): the save replaces the list,
            // so a member left out here would be removed.
            $('#asset-group-save').prop('disabled', true).addClass('is-busy');
            $.ajax({ url: api('/asset-groups/' + encodeURIComponent(group.id) + '/assets'), type: 'GET', dataType: 'json' })
                .done(function (json) {
                    if (self.editing !== group) { return; }
                    var members = ((json && json.data && json.data.assets) || []).map(function (a) { return { id: String(a.id), name: String(a.name || '') }; });
                    self.membersLoading = false;
                    self.setMembers(members);
                    $('#asset-group-save').prop('disabled', false).removeClass('is-busy');
                })
                .fail(function (xhr) {
                    if (self.editing !== group) { return; }
                    U.reportError(xhr);
                    $('#asset-group-modal').modal('hide');
                });
            $('#asset-group-modal').modal('show');
        },

        save: function () {
            var self = this;
            if (this.busy) { return; }
            var name = String($('#asset-group-name').val() || '').trim();
            if (!name) {
                this.setNameError(U.fmt('FieldRequired', { field: L('Name') }));
                $('#asset-group-name').trigger('focus');
                return;
            }
            var ids = ($('#asset-group-members').val() || []).map(Number);
            var editing = this.editing;
            var $btn = $('#asset-group-save').prop('disabled', true).addClass('is-busy');
            this.busy = true;
            var settings = editing ? {
                url: api('/asset-groups/' + encodeURIComponent(editing.id)), type: 'PATCH',
                contentType: 'application/json', data: JSON.stringify({ name: name, selected_assets: ids })
            } : {
                url: api('/asset-groups'), type: 'POST', data: { name: name, selected_assets: ids }
            };
            $.ajax($.extend(settings, {
                headers: U.csrfHeaders(), dataType: 'json',
                success: function (json) {
                    self.busy = false;
                    $btn.prop('disabled', false).removeClass('is-busy');
                    toastServer(json, 'SavedSuccess');
                    $('#asset-group-modal').modal('hide');
                    self.afterMutation();
                },
                error: function (xhr) {
                    if (U.reissuedByCsrfRetry(xhr, this)) { return; }
                    self.busy = false;
                    $btn.prop('disabled', false).removeClass('is-busy');
                    var body = xhr && xhr.responseJSON;
                    if (body && body.data && body.data.error === 'duplicate_name') {
                        // On the field that is wrong; the dialog stays open.
                        self.setNameError(L('AssetGroupNameAlreadyInUse'));
                        $('#asset-group-name').trigger('focus');
                        return;
                    }
                    U.reportError(xhr);
                }
            }));
        },

        /* ---------------- Delete group ---------------- */
        bindDeleteModal: function () {
            var self = this;
            var $modal = $('#asset-group-delete-modal');
            if (!$modal.length) { return; }
            $modal.on('shown.bs.modal', function () { $('#asset-group-delete-cancel').trigger('focus'); });
            $modal.on('hidden.bs.modal', function () { self.restoreFocus(); });
            $('#asset-group-delete-confirm').on('click', function () {
                var group = self.deleting, $btn = $(this);
                if (!group && self.pendingDelete) { self.runBulkDelete($btn, $modal); return; }
                if (!group || self.busy) { return; }
                self.busy = true;
                $btn.prop('disabled', true).addClass('is-busy');
                $.ajax({
                    url: api('/asset-groups/' + encodeURIComponent(group.id)), type: 'DELETE',
                    headers: U.csrfHeaders(), dataType: 'json',
                    success: function (json) {
                        self.busy = false;
                        $btn.prop('disabled', false).removeClass('is-busy');
                        toastServer(json, 'DeleteAssetGroup');
                        delete self.openDrawers[String(group.id)];
                        $modal.modal('hide');
                        self.afterMutation();
                    },
                    error: function (xhr) {
                        if (U.reissuedByCsrfRetry(xhr, this)) { return; }
                        self.busy = false;
                        $btn.prop('disabled', false).removeClass('is-busy');
                        U.reportError(xhr);
                    }
                });
            });
        },

        openDelete: function (group) {
            if (!caps.can_group_delete || !group) { return; }
            this.deleting = group;
            this.pendingDelete = null;
            $('#asset-group-delete-title').text(U.fmt('AssetGroupDeleteConfirmTitle', { name: group.name }));
            $('#asset-group-delete-note').text(L('AssetGroupDeleteKeepsAssets'));
            $('#asset-group-delete-confirm').text(L('DeleteAssetGroup'));
            $('#asset-group-delete-modal').modal('show');
        }
    };

    MA.groups = Groups;
    MA.assetPicker = Picker;

    $(function () {
        if ($('#manage-assets-card').length && $('#asset-groups-panel').length) { Groups.init(); }
    });
})(window, jQuery);
