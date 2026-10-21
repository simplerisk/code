/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Manage assets (asset management redesign, Phase A, Task 9).
 *
 * One .sr-table-card with two tabs. The Assets tab is a serverSide DataTable
 * fed by GET /api/v2/assets (paging, sorting, the All/Verified/Unverified
 * segment, search and the filter row all run on the server). Columns come from
 * GET /api/v2/assets/column-settings and are saved with a debounced PUT to the
 * same route. Bulk and row actions go through POST /api/v2/assets/bulk.
 *
 * Escaping: every value from the API or from window.manageAssetsConfig is RAW
 * text. Cells are built as DOM nodes in createdCell() with jQuery `text:` /
 * .text(), never from HTML strings carrying data. Language strings come from
 * _lang (via L()), also raw; the few places that build a toast escape the text
 * once with esc() because toastr renders its message as HTML (CLAUDE.md
 * "toastr / alerts rule": escape once at the source, never set escapeHtml).
 *
 * Hooks left for later tasks:
 *   Task 10  #asset-groups-panel (tab body), the 'manageassets:tabshown' event,
 *            ManageAssets.setGroups(list) to refresh the group pickers.
 *   Task 11  manage-asset-discovery.js replaces ManageAssets.openDiscovery();
 *            the Discover assets button shows when manageAssetsConfig.discoveryEnabled.
 */
(function (window, $) {
    'use strict';

    var cfg = window.manageAssetsConfig || {};
    var caps = cfg.caps || {};
    var lookups = cfg.lookups || {};

    // Mirrors asset_core_column_labels() (includes/assets_list.php), used only
    // when GET /assets/column-settings fails, so the table can still render.
    var CORE_LABEL_KEYS = {
        name: 'AssetName', ip: 'IPAddress', value: 'AssetValuation', location: 'SiteLocation',
        teams: 'Team', details: 'AssetDetails', mapped_controls: 'MappedControls', tags: 'Tags',
        associated_risks: 'AssociatedRisks', verified: 'Verified', created: 'CreationDate',
        // Asset Scoring (asset_scoring_column_labels(), includes/assets_list.php)
        confidentiality: 'Confidentiality', integrity: 'Integrity', availability: 'Availability',
        fips_categorization: 'FIPSCategorization', weighted_score: 'WeightedScore', weighted_band: 'WeightedBand'
    };
    // The Asset Scoring columns exist only once the upgrade has added the
    // scoring columns (lookups.scoring_enabled mirrors asset_scoring_schema_ready()).
    var SCORING_COLUMN_KEYS = /^(confidentiality|integrity|availability|fips_categorization|weighted_score|weighted_band)$/;
    // Mirrors asset_default_column_keys() (includes/assets_list.php).
    var FALLBACK_DEFAULTS = ['name', 'value', 'fips_categorization', 'weighted_score'];

    // name and the verified state are fixed columns (design-system 6b: they
    // must never scroll off); they show in the picker locked on.
    var FIXED_KEYS = ['name', 'verified'];

    // Keys the list API can sort by, from the server's own
    // assets_list_sortable_keys() (page config, so it is known before the
    // table is built). The rest are marked non-sortable rather than sorting a
    // page-only slice.
    var SORTABLE = Array.isArray(cfg.sortable) ? cfg.sortable.map(String) : ['name'];

    var MAX_CHIPS = 3;

    // Filter row dimensions, each an id list sent as a GET /assets param and
    // inside the bulk `filter` object.
    var FILTER_KEYS = ['team', 'location', 'tag', 'group', 'valuation', 'categorization', 'band', 'confidentiality', 'integrity', 'availability'];
    // The three stored ratings (Asset Scoring); each is a filter key too.
    var RATING_KEYS = ['confidentiality', 'integrity', 'availability'];
    function emptyFilters() {
        var f = {};
        FILTER_KEYS.forEach(function (k) { f[k] = []; });
        return f;
    }
    // The Asset groups tab's filters (manage-asset-groups.js), kept in the
    // URL as g<key> so they never collide with the Assets tab's own params.
    var GROUP_FILTER_KEYS = ['team', 'location', 'tag', 'categorization', 'band'];
    function emptyGroupFilters() {
        var f = {};
        GROUP_FILTER_KEYS.forEach(function (k) { f[k] = []; });
        return f;
    }
    // Comma-separated ids from a URL value: digits only, offered by the
    // matching select (allowed), no duplicates. Anything else is dropped.
    function urlIds(raw, allowed) {
        var out = [], seen = {};
        String(raw || '').split(',').forEach(function (part) {
            var id = part.trim();
            if (/^\d{1,10}$/.test(id) && (allowed || {})[id] === true && !seen[id]) {
                seen[id] = true;
                out.push(id);
            }
        });
        return out;
    }

    /* ---------------- shareable view state (URL query string) ----------------
     * The Assets tab (status, search, filters, sort, page size, page) and the
     * Asset groups search live in the address bar so a bookmarked or shared
     * URL reproduces the same view. Both functions are pure (no DOM, no
     * window): parseViewState() validates everything defensively, and
     * serializeViewState() rewrites only the params this page owns, leaving
     * every other param (tab aside) exactly where it was.
     * Grammar: status=verified|unverified, q, team/location/tag/group/valuation
     * (comma-separated ids), categorization/band (comma-separated Asset Scoring
     * level ids 1-3: Low/Moderate/High), confidentiality/integrity/availability
     * (comma-separated rating ids 1-3, plus 0 Not applicable for confidentiality
     * only), sort, dir=asc|desc, per_page, page (1-based),
     * tab=groups, gq, and the Asset groups filters gteam/glocation/gtag/
     * gcategorization/gband (comma-separated ids, validated like the Assets
     * filters against the groups tab's own selects). A value equal to its
     * default is left out. */
    var URL_DEFAULTS = { sort: 'name', dir: 'asc', perPage: 25 };
    var PAGE_SIZES = [10, 25, 50, 100];
    var URL_TEXT_MAX = 200;
    var URL_PAGE_MAX = 1000000;

    function urlText(raw) {
        return String(raw == null ? '' : raw).split('').map(function (c) { return c < ' ' || c === '\u007f' ? ' ' : c; }).join('').trim().slice(0, URL_TEXT_MAX);
    }

    // ctx: {sortable: [keys], sizes: [ints], ids: {filterKey: {id: true}}}.
    // An id the matching select does not offer is dropped (Team Separation
    // scopes the Teams options; a URL can neither add nor reveal a team).
    function parseViewState(search, ctx) {
        ctx = ctx || {};
        var p = new URLSearchParams(search || '');
        var out = {
            tab: p.get('tab') === 'groups' ? 'groups' : 'assets',
            status: 'all', q: urlText(p.get('q')), filters: emptyFilters(),
            sort: URL_DEFAULTS.sort, dir: URL_DEFAULTS.dir, perPage: URL_DEFAULTS.perPage, page: 1,
            gq: urlText(p.get('gq')), gfilters: emptyGroupFilters()
        };
        var st = p.get('status');
        if (st === 'verified' || st === 'unverified') { out.status = st; }
        FILTER_KEYS.forEach(function (k) {
            out.filters[k] = urlIds(p.get(k), (ctx.ids || {})[k]);
        });
        GROUP_FILTER_KEYS.forEach(function (k) {
            out.gfilters[k] = urlIds(p.get('g' + k), (ctx.gids || {})[k]);
        });
        var sort = p.get('sort');
        if (sort && (ctx.sortable || []).indexOf(sort) !== -1) { out.sort = sort; }
        if (p.get('dir') === 'asc' || p.get('dir') === 'desc') { out.dir = p.get('dir'); }
        var size = /^\d{1,4}$/.test(p.get('per_page') || '') ? parseInt(p.get('per_page'), 10) : 0;
        if ((ctx.sizes || PAGE_SIZES).indexOf(size) !== -1) { out.perPage = size; }
        var page = /^\d{1,7}$/.test(p.get('page') || '') ? parseInt(p.get('page'), 10) : 0;
        if (page >= 1 && page <= URL_PAGE_MAX) { out.page = page; }
        return out;
    }

    // state: {tab, status, q, filters, sort, dir, perPage, page, gq}.
    // Returns the new query string ('?a=b' or ''), never the hash.
    function serializeViewState(state, defaults, existingSearch) {
        defaults = defaults || URL_DEFAULTS;
        var p = new URLSearchParams(existingSearch || '');
        var put = function (k, v) {
            if (v === null || v === undefined || v === '') { p.delete(k); } else { p.set(k, String(v)); }
        };
        put('tab', state.tab === 'groups' ? 'groups' : null);
        put('status', state.status === 'verified' || state.status === 'unverified' ? state.status : null);
        put('q', state.q);
        FILTER_KEYS.forEach(function (k) { put(k, ((state.filters || {})[k] || []).join(',')); });
        put('sort', state.sort && state.sort !== defaults.sort ? state.sort : null);
        put('dir', state.dir && state.dir !== defaults.dir ? state.dir : null);
        put('per_page', state.perPage && state.perPage !== defaults.perPage ? state.perPage : null);
        put('page', state.page > 1 ? state.page : null);
        put('gq', state.gq);
        GROUP_FILTER_KEYS.forEach(function (k) { put('g' + k, ((state.gfilters || {})[k] || []).join(',')); });
        // Commas stay readable (team=1,2); everything else keeps the standard encoding.
        var qs = p.toString().replace(/%2C/gi, ',');
        return qs ? '?' + qs : '';
    }

    // Selection only exists to feed the bulk bar: a user with no bulk action
    // gets no checkbox column at all rather than boxes that lead nowhere.
    // Add to group is offered with either group permission: an existing group
    // needs asset_group_edit, "Create a new group…" needs asset_group_create.
    var CAN_ADD_TO_GROUP = !!(caps.can_group_edit || caps.can_group_create);
    var CAN_BULK = !!(caps.can_verify || caps.can_edit || CAN_ADD_TO_GROUP || caps.can_delete);

    // The Add to group picker's "Create a new group…" option value.
    var NEW_GROUP = '__new__';
    // Ids one POST /asset-groups may carry (PHP's max_input_vars is 1000 by
    // default; a larger form body would be cut short without an error).
    var NEW_GROUP_MAX_IDS = 900;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Raw-text interpolation of a {$name}-placeholder lang string.
    function fmt(key, params) {
        var s = String(L(key));
        Object.keys(params || {}).forEach(function (k) {
            s = s.split('{$' + k + '}').join(String(params[k]));
        });
        return s;
    }

    function csrfHeaders() {
        // A JSON body bypasses csrf-magic's automatic token injection, so send
        // the token in the CSRF-TOKEN header (same as the other JSON callers).
        return { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' };
    }

    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    // True when csrf-magic's retryCSRF() re-sent the request with a fresh token.
    // It re-issues `$.ajax(settings)`, so a caller's outcome handlers must live
    // IN the settings (success/error), not on the returned jqXHR, and the caller
    // must not report a failure for the first attempt. retryCSRF() also returns
    // true WITHOUT re-sending once it has retried five times; the counter it
    // bumps on a real re-send tells the two apart.
    function reissuedByCsrfRetry(xhr, settings) {
        if (typeof retryCSRF !== 'function') { return false; }
        var before = (typeof retryCSRFCount !== 'undefined') ? retryCSRFCount : null;
        if (!retryCSRF(xhr, settings)) { return false; }
        return before === null || (typeof retryCSRFCount !== 'undefined' && retryCSRFCount > before);
    }

    function isAbort(xhr) { return !!xhr && xhr.statusText === 'abort'; }

    // Toasts a failed request (after reissuedByCsrfRetry() said it was not re-sent).
    function reportError(xhr) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.status_message) {
            // Server messages are escaped at their source.
            showAlertsFromArray(xhr.responseJSON.status_message);
        } else {
            showAlertFromMessage(esc(L('RequestFailed')), false);
        }
    }

    function isVerified(row) { return String(row.verified) === '1'; }

    function dash() { return $('<span>', { 'class': 'sr-cell-dash', text: '—' }); }

    // Plain text of an HTML fragment (asset details are stored as purified
    // HTML). DOMParser builds an inert document: no script runs, nothing loads.
    function htmlToText(html) {
        if (!html) { return ''; }
        var doc = new DOMParser().parseFromString(String(html), 'text/html');
        return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
    }

    // Filterable chips: a cell chip that ADDS its value to the matching Filters
    // panel select (never toggles it off; removal stays in the panel). One
    // table drives it, so adopting a new kind is a one-line entry here plus
    // passing the kind to filterChip() where the cell is built.
    //   select  the Filters panel <select> (its options are the only ids a
    //           click may use; nothing is ever fabricated)
    //   tip     lang key for the tooltip / aria-label ("Filter by team {$name}")
    //   say     lang key for the polite live-region announcement
    // The filter key in state.filters is the kind itself.
    var CHIP_FILTERS = {
        team: { select: '#manage-assets-team-filter', tip: 'AssetFilterByTeam', say: 'AssetFilteringByTeam' },
        valuation: { select: '#manage-assets-valuation-filter', tip: 'AssetFilterByValuation', say: 'AssetFilteringByValuation' },
        tag: { select: '#manage-assets-tag-filter', tip: 'AssetFilterByTag', say: 'AssetFilteringByTag' },
        location: { select: '#manage-assets-location-filter', tip: 'AssetFilterByLocation', say: 'AssetFilteringByLocation' },
        categorization: { select: '#manage-assets-categorization-filter', tip: 'AssetFilterByCategorization', say: 'AssetFilteringByCategorization' },
        band: { select: '#manage-assets-band-filter', tip: 'AssetFilterByBand', say: 'AssetFilteringByBand' },
        confidentiality: { select: '#manage-assets-confidentiality-filter', tip: 'AssetFilterByConfidentiality', say: 'AssetFilteringByConfidentiality' },
        integrity: { select: '#manage-assets-integrity-filter', tip: 'AssetFilterByIntegrity', say: 'AssetFilteringByIntegrity' },
        availability: { select: '#manage-assets-availability-filter', tip: 'AssetFilterByAvailability', say: 'AssetFilteringByAvailability' },
        // Single-valued, so a click REPLACES the status segment (never a
        // toggle: All clears it). Lang keys are per value, ids are the
        // segment's own data-status values.
        status: {
            byId: {
                verified: { tip: 'AssetShowOnlyVerified', say: 'AssetShowingVerified' },
                unverified: { tip: 'AssetShowOnlyUnverified', say: 'AssetShowingUnverified' }
            }
        }
    };
    function chipLang(kind, id, which) {
        var def = CHIP_FILTERS[kind];
        return def.byId ? (def.byId[String(id)] || {})[which] : def[which];
    }

    // Ids each filter offers, from the same server lookups that fill the
    // selects: a chip whose id is not among them renders as a plain chip.
    var chipIdSets = null;
    function chipFilterable(kind, id) {
        if (!chipIdSets) {
            var ids = function (list) { var o = {}; (list || []).forEach(function (x) { o[String(x.id)] = true; }); return o; };
            chipIdSets = {
                team: ids(lookups.team_options), valuation: ids(lookups.valuations),
                tag: ids(lookups.tags), location: ids(lookups.locations),
                categorization: ids(lookups.scoring_levels), band: ids(lookups.scoring_levels)
            };
            RATING_KEYS.forEach(function (k) { chipIdSets[k] = ids((lookups.scoring_ratings || {})[k]); });
        }
        return !!(CHIP_FILTERS[kind] && chipIdSets[kind] && chipIdSets[kind][String(id)] === true);
    }

    // Tag chips carry the tag's NAME (tag_list), the tag filter is id-based:
    // map through the lookups, and only when the name is unambiguous.
    var tagIdsByName = null;
    function tagIdForName(name) {
        if (!tagIdsByName) {
            tagIdsByName = {};
            (lookups.tags || []).forEach(function (t) {
                var n = String(t.name);
                tagIdsByName[n] = Object.prototype.hasOwnProperty.call(tagIdsByName, n) ? null : String(t.id);
            });
        }
        return Object.prototype.hasOwnProperty.call(tagIdsByName, name) ? tagIdsByName[name] : null;
    }

    // A chip click's add-only step, shared by both tabs: selects `id` in the
    // filter <select> when that select offers it and it is not selected yet
    // (never invents an option), runs `before` (the caller remembers the chip
    // to refocus), fires the select's change and announces it in the live
    // region. Returns whether anything changed.
    function addToFilterSelect($sel, id, sayKey, liveSelector, before) {
        var $opt = $sel.find('option').filter(function () { return String(this.value) === id; }).first();
        if (!$opt.length) { return false; }
        var cur = ($sel.val() || []).map(String);
        if (cur.indexOf(id) !== -1) { return false; }
        if (before) { before(); }
        $sel.val(cur.concat(id));
        if (typeof srSelectRender === 'function' && $sel.data('srSelect')) { srSelectRender($sel); }
        $sel.trigger('change');
        $(liveSelector).text(fmt(sayKey, { name: $opt.text() }));
        return true;
    }

    // The one chip builder: a real <button> that filters by {kind,id}. `content`
    // (nodes) replaces the plain label text when the chip has inner structure;
    // `bare` drops the .sr-chip box for a chip that draws its own (valuation).
    function filterChip(opts) {
        // opts.tipKey: another table's own wording for the same filter
        // (the Asset groups tab's "highest" categorization / band).
        var tip = fmt(opts.tipKey || chipLang(opts.kind, opts.id, 'tip'), { name: opts.label });
        var $b = $('<button>', {
            type: 'button',
            'class': (opts.bare ? '' : 'sr-chip ') + 'sr-assets-chip-filter' + (opts.extraClass ? ' ' + opts.extraClass : ''),
            'data-filter-kind': opts.kind,
            'data-filter-id': String(opts.id),
            title: tip,
            'aria-label': tip
        });
        if (opts.content) { $b.append(opts.content); } else { $b.text(String(opts.label)); }
        return $b;
    }

    // Valuation cell: the text level name (a neutral chip) over its numeric
    // range, or the range alone when the level is unnamed; a dash when the
    // stored value matches no level. One renderer for both tabs, fed by the
    // server-built lookups (label/level/range, see asset_valuation_parts()).
    // `filterable` (Assets tab only) makes the whole cell one filter button.
    function valuationCell(id, filterable) {
        var v = null;
        if (id != null && String(id) !== '' && String(id) !== '0') {
            (lookups.valuations || []).forEach(function (x) { if (String(x.id) === String(id)) { v = x; } });
        }
        if (!v) { return dash(); }
        var range = String(v.range || v.label || '');
        var canFilter = !!filterable && chipFilterable('valuation', v.id);
        if (!v.level) {
            if (canFilter) {
                return filterChip({ kind: 'valuation', id: v.id, label: String(v.label || range), extraClass: 'sr-num' }).text(range);
            }
            // An unnamed level: the range itself in the valuation chip, so the
            // cell reads as a chip whether or not the level has a name.
            return $('<span>', { 'class': 'sr-assets-val', title: String(v.label || range) })
                .append($('<span>', { 'class': 'sr-assets-val-chip sr-num', text: range }));
        }
        var parts = [
            $('<span>', { 'class': 'sr-assets-val-chip', text: String(v.level) }),
            $('<span>', { 'class': 'sr-assets-val-sub sr-num', text: range })
        ];
        if (canFilter) {
            return filterChip({ kind: 'valuation', id: v.id, label: String(v.label || v.level), extraClass: 'sr-assets-val', bare: true, content: parts });
        }
        return $('<span>', { 'class': 'sr-assets-val', title: String(v.label || '') }).append(parts);
    }

    // Asset Scoring labels from the server lookups (raw text; set with text setters).
    var SCORING_LEVELS = {};
    (lookups.scoring_levels || []).forEach(function (l) { SCORING_LEVELS[String(l.key)] = l; });

    // Categorization / band: a filter chip coloured by level, a dash when unscored.
    function scoringChip(kind, code) {
        var level = SCORING_LEVELS[String(code)];
        if (!level) { return dash(); }
        if (chipFilterable(kind, level.id)) {
            return filterChip({ kind: kind, id: level.id, label: String(level.name), extraClass: 'sr-chip--level-' + String(code) });
        }
        return $('<span>', { 'class': 'sr-chip sr-chip--level-' + String(code), text: String(level.name) });
    }

    // Confidentiality / integrity / availability: the stored rating as a filter
    // chip, coloured by level like the categorization chip; Not applicable is a
    // neutral (uncoloured) chip with the word; a dash when the objective is not set.
    function ratingChip(objective, code) {
        var opt = null;
        ((lookups.scoring_ratings || {})[objective] || []).forEach(function (o) { if (String(o.key) === String(code)) { opt = o; } });
        if (!opt) { return dash(); }
        var cls = code === 'not_applicable' ? 'sr-chip--na' : 'sr-chip--level-' + String(code);
        if (chipFilterable(objective, opt.id)) {
            return filterChip({ kind: objective, id: opt.id, label: String(opt.name), extraClass: cls });
        }
        return $('<span>', { 'class': 'sr-chip ' + cls, text: String(opt.name) });
    }

    // A scoring level as a plain (never filtering) chip coloured by level:
    // the Asset groups tab's aggregates and member drawer. A dash when unset.
    function levelChip(code) {
        var level = SCORING_LEVELS[String(code)];
        if (!level) { return dash(); }
        return $('<span>', { 'class': 'sr-chip sr-chip--level-' + String(code), text: String(level.name) });
    }

    // Weighted score: the two-decimal score in a chip coloured by its band. A
    // continuous value, so never a filter (no button, no pointer).
    function scoreChip(score, band) {
        if (!score) { return dash(); }
        var cls = SCORING_LEVELS[String(band)] ? ' sr-chip--level-' + String(band) : '';
        return $('<span>', { 'class': 'sr-chip sr-num sr-assets-score-chip' + cls, text: String(score) });
    }

    function byId(list, idKey, labelKey) {
        var map = {};
        (list || []).forEach(function (o) { map[String(o[idKey || 'id'])] = o[labelKey || 'name']; });
        return map;
    }

    /* ---------------- column drag-reorder (ColReorder), shared by both tabs ----------------
     * Plan Projects' / Review Risk's ColReorder rules (the plugin is vendored
     * at 3.0.1, see plan-projects.js buildTable()):
     *  - only the configurable columns (a <th data-col>) are moveable, through
     *    the `columns` option; the fixed columns (checkbox, caret, name,
     *    status, actions) never move;
     *  - order is read and written as data-col keys straight off each
     *    column's CURRENT <th> (DataTables' column index is the current
     *    position), never through colReorder.order()'s getter, which returns
     *    load-time indexes (review-risk.js currentColumnOrderForSave());
     *  - DataTables' native column visibility mirrors the picker's d-none
     *    state, set BEFORE the d-none classes change, because ColReorder's
     *    drop-zone math reads native visibility (plan-projects.js
     *    applyColumnVisibility()). */

    // Indexes (build order) of the moveable columns: the ones with a key.
    function moveableColumns(defs) {
        var out = [];
        defs.forEach(function (d, i) { if (d.sr_moveable) { out.push(i); } });
        return out;
    }

    // data-col keys in current visual order (hidden columns included).
    function dataColOrder(table) {
        var out = [];
        for (var p = 0; p < table.columns().count(); p++) {
            var h = table.column(p).header();
            var key = h ? h.getAttribute('data-col') : null;
            if (key) { out.push(key); }
        }
        return out;
    }

    // Moves the data-col columns into `order` (keys; unknown keys skipped,
    // keys it does not name keep their relative order after it), leaving
    // every fixed column where it is (review-risk.js applySavedColumnOrder()).
    function applyDataColOrder(table, order) {
        var keyAt = [];
        for (var p = 0; p < table.columns().count(); p++) {
            var h = table.column(p).header();
            keyAt.push(h ? h.getAttribute('data-col') : null);
        }
        var want = [];
        (order || []).forEach(function (key) {
            var pos = keyAt.indexOf(key);
            if (pos !== -1 && want.indexOf(pos) === -1) { want.push(pos); }
        });
        keyAt.forEach(function (key, pos) { if (key !== null && want.indexOf(pos) === -1) { want.push(pos); } });
        var cursor = 0;
        var desired = keyAt.map(function (key, pos) { return key !== null ? want[cursor++] : pos; });
        var unchanged = desired.every(function (v, i) { return v === i; });
        // `false`: desired is in current-position space (see above).
        if (!unchanged) { table.colReorder.order(desired, false); }
    }

    // Both tables paint their cells in createdCell() (each column's `render`
    // returns ''), unlike Plan Projects / Review Risk, whose `render`
    // functions return the cell HTML. ColReorder ends every move -- a header
    // drag, or colReorder.order() from applyDataColOrder() -- by invalidating
    // every row from its data (dataTables.colReorder.js finalise():
    // rows().invalidate('data')), which rewrites each cell with its render
    // output and so empties every cell on the page. Once the move is done
    // ('columns-reordered', fired after that invalidation) each column's
    // createdCell() runs again on its existing cell node; every painter
    // empties its cell first, and row events are delegated, so this is safe
    // to repeat.
    function repaintCreatedCells(table) {
        var settings = table.settings()[0];
        table.rows().every(function (rowIdx) {
            var rowData = this.data();
            settings.columns.forEach(function (col, colIdx) {
                if (typeof col.createdCell !== 'function') { return; }
                var td = table.cell(rowIdx, colIdx).node();
                if (td) { col.createdCell.call(settings.instance, td, null, rowData, rowIdx, colIdx); }
            });
        });
    }
    function keepCellsPaintedOnReorder(table) {
        table.on('columns-reordered.dt', function () { repaintCreatedCells(table); });
    }

    // Native visibility of the data-col columns follows `visible` (key -> bool).
    function syncNativeVisibility(table, visible) {
        table.columns().every(function () {
            var h = this.header();
            var key = h ? h.getAttribute('data-col') : null;
            if (!key) { return; }
            var want = !!visible[key];
            if (this.visible() !== want) { this.visible(want, false); }
        });
        table.columns.adjust();
    }

    // The layout order to save after a drag: name (and any other fixed key the
    // saved order carries, kept in place) first, then the data-col keys as
    // they now stand, then anything else the old order had.
    function mergedOrder(oldOrder, dragged) {
        var out = [];
        (oldOrder || []).forEach(function (k) { if (dragged.indexOf(k) === -1 && out.indexOf(k) === -1 && (k === 'name' || k === 'verified')) { out.push(k); } });
        dragged.forEach(function (k) { if (out.indexOf(k) === -1) { out.push(k); } });
        (oldOrder || []).forEach(function (k) { if (out.indexOf(k) === -1) { out.push(k); } });
        return out;
    }

    // The default layout as the server builds it (asset_normalize_column_settings()
    // with nothing stored): the default columns in their order, then every
    // other offered key in picker order; only the defaults visible.
    function defaultLayout(defaults, availableKeys) {
        var order = ['name'];
        (defaults || []).concat(availableKeys).forEach(function (k) {
            if (availableKeys.indexOf(k) !== -1 && order.indexOf(k) === -1) { order.push(k); }
        });
        var visible = {};
        availableKeys.forEach(function (k) { visible[k] = (defaults || []).indexOf(k) !== -1; });
        return { order: order, visible: visible };
    }

    var ManageAssets = {
        config: cfg,
        state: {
            tab: 'assets',
            gq: '',                 // Asset groups search (kept in the URL as gq)
            gfilters: emptyGroupFilters(), // Asset groups filters (URL: gteam, glocation, ...)
            initial: { sort: URL_DEFAULTS.sort, dir: URL_DEFAULTS.dir, perPage: URL_DEFAULTS.perPage, page: 1 },
            status: 'all',          // all | verified | unverified
            q: '',
            filters: emptyFilters(),
            columns: [],            // pickable columns [{key,label,group}] in saved order
            visible: {},            // key -> bool
            savedColumns: {},       // key -> '1'|'0' as last loaded/saved
            order: [],              // full saved order (every allowed key)
            selected: {},           // id -> name
            selectAll: false,       // escalated to the whole filtered set
            total: 0,
            counts: null,
            rows: [],
            loadFailed: false,
            table: null,
            page: 0,                // page index of the rendered response
            pageKey: null,          // start|length|sort of the rendered response
            bulkBusy: false         // a bulk/row action is in flight
        },
        fetchSeq: 0,                // GET /assets request sequence (R1)
        fetchXhr: null,
        colSave: { inFlight: false, pending: false, lastSent: null },
        lastOpener: null,           // control that opened the current modal
        maps: {
            teams: byId(lookups.teams),
            locations: byId(lookups.locations),
            groups: byId(lookups.groups)
        },
        colKeys: [],                // DataTables column index -> key

        init: function () {
            var self = this;
            this.saveColumnsDebounced = debounce(function () { self.saveColumns(); }, 500);

            this.bindTabs();
            this.bindTabActions();
            this.bindToolbar();
            this.bindFilters();
            this.bindColpicker();
            this.bindSelection();
            this.bindRowActions();
            this.bindModals();
            this.bindRecordModal();

            // The URL is read before the first list request (the table is only
            // built once the column settings arrive) and before setTab().
            var fromUrl = this.applyUrlState();
            var initial = cfg.initialTab === 'groups' || window.location.hash === '#groups' || fromUrl.tab === 'groups' ? 'groups' : 'assets';
            this.setTab(initial, true);

            if (cfg.discoveryEnabled && caps.can_discovery) {
                $('#manage-assets-discover').removeClass('d-none');
                // The <=900px home of Discover assets (the stylesheet decides
                // which of the two is shown at a given width).
                $('#manage-assets-more-actions').addClass('is-available');
            }

            var syncShadow = debounce(function () { self.syncScrollShadow(); }, 50);
            $('#manage-assets-panel > .sr-table-scroll').on('scroll', syncShadow);
            $(window).on('resize', syncShadow);

            this.showLoadingRow();
            this.loadColumnSettings();
        },

        /* ---------------- tabs ---------------- */
        // WAI-ARIA tabs: one tab in the tab order (roving tabindex), arrows /
        // Home / End move between tabs and activate them.
        bindTabs: function () {
            var self = this;
            $('#manage-assets-tabs').on('click', '.sr-tab', function () {
                self.setTab(String($(this).data('tab')));
            });
            $('#manage-assets-tabs').on('keydown', '.sr-tab', function (e) {
                var $tabs = $('#manage-assets-tabs .sr-tab');
                var i = $tabs.index(this), next = null;
                if (e.key === 'ArrowRight') { next = (i + 1) % $tabs.length; }
                else if (e.key === 'ArrowLeft') { next = (i - 1 + $tabs.length) % $tabs.length; }
                else if (e.key === 'Home') { next = 0; }
                else if (e.key === 'End') { next = $tabs.length - 1; }
                if (next === null) { return; }
                e.preventDefault();
                var $t = $tabs.eq(next);
                self.setTab(String($t.data('tab')));
                $t.trigger('focus');
            });
        },
        setTab: function (tab, initial) {
            this.state.tab = tab;
            $('#manage-assets-tabs .sr-tab').each(function () {
                var on = String($(this).data('tab')) === tab;
                $(this).toggleClass('is-active', on).attr('aria-selected', on ? 'true' : 'false')
                    .attr('tabindex', on ? '0' : '-1');
            });
            $('#manage-assets-panel').toggleClass('d-none', tab !== 'assets');
            $('#asset-groups-panel').toggleClass('d-none', tab !== 'groups');
            // Only the active tab's create actions are shown (one red button).
            this.closeMoreMenu(false);
            $('#manage-assets-tabbar [data-tab-actions]').each(function () {
                $(this).toggleClass('d-none', String($(this).data('tab-actions')) !== tab);
            });
            this.syncUrl();
            if (tab === 'assets' && !initial && this.state.table) { this.state.table.columns.adjust(); this.syncScrollShadow(); }
            $(document).trigger('manageassets:tabshown', [tab]);
        },

        /* ---------------- tab-row actions: the More actions menu (<=900px) ---------------- */
        // A one-item menu today (Discover assets), keyboard operable: Enter /
        // Space / ArrowDown open it on its first item, arrows move, Escape and
        // Tab close it, and Escape returns focus to the toggle.
        bindTabActions: function () {
            var self = this;
            var $toggle = $('#manage-assets-more-toggle');
            if (!$toggle.length) { return; }
            var items = function () { return $('#manage-assets-more-menu [role="menuitem"]'); };
            $toggle.on('click', function (e) {
                e.stopPropagation();
                if ($toggle.attr('aria-expanded') === 'true') { self.closeMoreMenu(true); } else { self.openMoreMenu(); }
            });
            $toggle.on('keydown', function (e) {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    self.openMoreMenu(e.key === 'ArrowUp');
                }
            });
            $('#manage-assets-more-menu').on('keydown', '[role="menuitem"]', function (e) {
                var $all = items(), i = $all.index(this), next = null;
                if (e.key === 'ArrowDown') { next = (i + 1) % $all.length; }
                else if (e.key === 'ArrowUp') { next = (i - 1 + $all.length) % $all.length; }
                else if (e.key === 'Home') { next = 0; }
                else if (e.key === 'End') { next = $all.length - 1; }
                else if (e.key === 'Escape') { e.preventDefault(); self.closeMoreMenu(true); return; }
                else if (e.key === 'Tab') { self.closeMoreMenu(false); return; }
                if (next === null) { return; }
                e.preventDefault();
                $all.eq(next).trigger('focus');
            });
            $('#manage-assets-discover-menu').on('click', function () {
                // The toggle is the opener the dialog hands focus back to.
                self.closeMoreMenu(true);
                ManageAssets.openDiscovery();
            });
            $(document).on('click.manageassetsmore', function (e) {
                if (!$(e.target).closest('#manage-assets-more-actions').length) { self.closeMoreMenu(false); }
            });
        },
        openMoreMenu: function (last) {
            var $menu = $('#manage-assets-more-menu');
            if (!$menu.length) { return; }
            $menu.prop('hidden', false);
            $('#manage-assets-more-toggle').attr('aria-expanded', 'true');
            var $items = $menu.find('[role="menuitem"]');
            $items.eq(last ? $items.length - 1 : 0).trigger('focus');
        },
        closeMoreMenu: function (refocus) {
            var $toggle = $('#manage-assets-more-toggle');
            if (!$toggle.length || $toggle.attr('aria-expanded') !== 'true') { return; }
            $('#manage-assets-more-menu').prop('hidden', true);
            $toggle.attr('aria-expanded', 'false');
            if (refocus) { $toggle.trigger('focus'); }
        },

        /* ---------------- view state <-> URL ---------------- */
        viewCtx: function () {
            var ids = {};
            FILTER_KEYS.forEach(function (k) {
                ids[k] = {};
                $('#manage-assets-' + k + '-filter option').each(function () { ids[k][String(this.value)] = true; });
            });
            var gids = {};
            GROUP_FILTER_KEYS.forEach(function (k) {
                gids[k] = {};
                $('#asset-groups-' + k + '-filter option').each(function () { gids[k][String(this.value)] = true; });
            });
            return { sortable: SORTABLE, sizes: PAGE_SIZES, ids: ids, gids: gids };
        },
        // Reads the query string into the controls/state (values only ever
        // reach the DOM through .val()/.attr(); nothing is parsed as HTML).
        applyUrlState: function () {
            var v = parseViewState(window.location.search, this.viewCtx());
            var s = this.state;
            s.status = v.status;
            s.q = v.q;
            s.filters = v.filters;
            s.gq = v.gq;
            s.gfilters = v.gfilters;
            s.initial = { sort: v.sort, dir: v.dir, perPage: v.perPage, page: v.page };
            $('#manage-assets-status-filter .sr-status-chip').each(function () {
                var on = String($(this).data('status')) === v.status;
                $(this).toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
            });
            $('#manage-assets-search').val(v.q);
            $('#manage-assets-quickfilters select[data-filter]').each(function () {
                $(this).val(v.filters[$(this).data('filter')] || []);
                if (typeof srSelectRender === 'function' && $(this).data('srSelect')) { srSelectRender($(this)); }
            });
            // The Asset groups filters: values only; manage-asset-groups.js
            // reads state.gfilters and shows its own row and count.
            $('#asset-groups-quickfilters select[data-filter]').each(function () {
                $(this).val(v.gfilters[$(this).data('filter')] || []);
                if (typeof srSelectRender === 'function' && $(this).data('srSelect')) { srSelectRender($(this)); }
            });
            this.syncFilterCount();
            if (this.filterValueCount() > 0) {
                $('#manage-assets-quickfilters').removeClass('d-none').addClass('is-open');
                $('#manage-assets-filters-toggle').attr('aria-expanded', 'true');
            }
            return v;
        },
        // The live view: the table's own sort/size/page once it exists, the
        // URL-requested ones until then.
        currentView: function () {
            var s = this.state, t = s.table;
            var view = {
                tab: s.tab, status: s.status, q: s.q, filters: s.filters, gq: s.gq, gfilters: s.gfilters,
                sort: s.initial.sort, dir: s.initial.dir, perPage: s.initial.perPage, page: s.initial.page
            };
            if (t) {
                var ord = t.order()[0];
                var head = ord ? t.column(ord[0]).header() : null;
                var key = head ? head.getAttribute('data-key') : null;
                if (key && key.charAt(0) !== '_') { view.sort = key; view.dir = ord[1] === 'desc' ? 'desc' : 'asc'; }
                view.perPage = t.page.len();
                view.page = t.page() + 1;
            }
            return view;
        },
        // replaceState, never pushState: filters must not flood history.
        syncUrl: function () {
            var loc = window.location;
            var hash = loc.hash === '#groups' ? '' : loc.hash;
            var next = loc.pathname + serializeViewState(this.currentView(), URL_DEFAULTS, loc.search) + hash;
            if (next === loc.pathname + loc.search + loc.hash) { return; }
            try { history.replaceState(history.state, '', next); } catch (err) { void err; /* URL is a convenience only */ }
        },
        // The status segment, from a segment click or a status pill.
        setStatus: function (status) {
            if (status === this.state.status) { return false; }
            this.state.status = status;
            $('#manage-assets-status-filter .sr-status-chip').each(function () {
                var on = String($(this).data('status')) === status;
                $(this).toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
            });
            this.selectionChanged();
            return true;
        },

        /* ---------------- toolbar: segment, search, filters toggle ---------------- */
        bindToolbar: function () {
            var self = this;
            $('#manage-assets-status-filter').on('click', '.sr-status-chip', function () {
                self.setStatus(String($(this).data('status')));
            });
            $('#manage-assets-search').on('input', debounce(function () {
                var q = String($(this).val() || '').trim();
                if (q === self.state.q) { return; }
                self.state.q = q;
                self.selectionChanged();
            }, 300));
            $('#manage-assets-filters-toggle').on('click', function () {
                var $qf = $('#manage-assets-quickfilters');
                var open = $qf.hasClass('d-none');
                // d-none drives the wide layout; .is-open is what the shared
                // <=1100px filter-sheet rule keys on. Both follow the button.
                $qf.toggleClass('d-none', !open).toggleClass('is-open', open);
                $(this).attr('aria-expanded', open ? 'true' : 'false');
            });
            $('#manage-assets-add').on('click', function () { self.openAdd(); });
            $('#manage-assets-discover').on('click', function () { ManageAssets.openDiscovery(); });
            $(document).on('click', '#manage-assets-empty-add', function () { self.openAdd(); });
            $(document).on('click', '#manage-assets-empty-clear', function () { self.clearAllFilters(); });
            $(document).on('click', '#manage-assets-retry', function () {
                if (self.state.table) { self.refresh(false); } else { self.showLoadingRow(); self.loadColumnSettings(); }
            });
        },

        bindFilters: function () {
            var self = this;
            var fill = function (id, list) {
                var $sel = $(id);
                (list || []).forEach(function (o) {
                    $('<option>', { value: o.id, text: o.name }).appendTo($sel);
                });
                if (typeof srSelectEnhance === 'function') { srSelectEnhance($sel, $sel.data('placeholder')); }
            };
            fill('#manage-assets-team-filter', lookups.team_options);
            fill('#manage-assets-location-filter', lookups.locations);
            fill('#manage-assets-tag-filter', lookups.tags);
            // The Asset groups tab's filters offer the same options (same
            // lookups, same Team Separation scope). Filled here, before the
            // URL is read, so viewCtx() can validate its g* params.
            fill('#asset-groups-team-filter', lookups.team_options);
            fill('#asset-groups-location-filter', lookups.locations);
            fill('#asset-groups-tag-filter', lookups.tags);
            fill('#manage-assets-group-filter', lookups.groups);
            // One label source: asset_valuation_parts()' label, which already
            // ends with the level name ("$1,000 to $10,000 (High)").
            fill('#manage-assets-valuation-filter', (lookups.valuations || []).map(function (v) {
                return { id: v.id, name: v.label };
            }));
            // Asset Scoring: Low / Moderate / High only (no "Not scored", ruling G9).
            var levelOptions = (lookups.scoring_levels || []).map(function (l) { return { id: l.id, name: l.name }; });
            fill('#manage-assets-categorization-filter', levelOptions);
            fill('#manage-assets-band-filter', levelOptions);
            fill('#asset-groups-categorization-filter', levelOptions);
            fill('#asset-groups-band-filter', levelOptions);
            // The ratings: Low / Moderate / High, and Not applicable (id 0) for
            // confidentiality only (asset_scoring_filter_levels()).
            RATING_KEYS.forEach(function (k) {
                fill('#manage-assets-' + k + '-filter', ((lookups.scoring_ratings || {})[k] || []).map(function (l) { return { id: l.id, name: l.name }; }));
            });
            $('#manage-assets-quickfilters').on('change', 'select[data-filter]', function () {
                self.state.filters[$(this).data('filter')] = ($(this).val() || []).map(String);
                self.syncFilterCount();
                self.selectionChanged();
            });
            $('#manage-assets-filters-clear').on('click', function () { self.clearAllFilters(); });
            // A cell chip adds its value to the matching filter.
            $('#manage-assets-table').on('click', '.sr-assets-chip-filter', function (e) {
                e.stopPropagation();
                self.addChipFilter(String($(this).attr('data-filter-kind')), String($(this).attr('data-filter-id')));
            });
        },
        // Add-only (a second click on the same chip changes nothing). Only ids
        // present in the filter's own options are accepted: an id the viewer
        // is not offered is ignored, never invented as a new option.
        addChipFilter: function (kind, id) {
            var def = CHIP_FILTERS[kind];
            if (!def || id === '') { return; }
            if (kind === 'status') {
                if (!def.byId[id] || id === this.state.status) { return; }
                this.state.refocusChip = { kind: kind, id: id };
                this.setStatus(id);
                $('#manage-assets-chip-live').text(fmt(def.byId[id].say, {}));
                return;
            }
            if (!addToFilterSelect($(def.select), id, def.say, '#manage-assets-chip-live', function () {
                ManageAssets.state.refocusChip = { kind: kind, id: id };
            })) { return; }
        },
        filterValueCount: function () {
            var f = this.state.filters;
            return FILTER_KEYS.reduce(function (n, k) { return n + f[k].length; }, 0);
        },
        syncFilterCount: function () {
            var n = this.filterValueCount();
            $('#manage-assets-filters-count').text(n).prop('hidden', n === 0);
            $('#manage-assets-filters-toggle').toggleClass('has-filters', n > 0);
            $('#manage-assets-filters-clear').toggleClass('d-none', n === 0);
        },
        // Search, the segment, or any filter narrows the set.
        filtersActive: function () {
            return this.state.status !== 'all' || this.state.q !== '' || this.filterValueCount() > 0;
        },
        clearAllFilters: function () {
            this.state.filters = emptyFilters();
            $('#manage-assets-quickfilters select[data-filter]').each(function () {
                $(this).val([]);
                if (typeof srSelectRender === 'function' && $(this).data('srSelect')) { srSelectRender($(this)); }
            });
            this.state.q = '';
            $('#manage-assets-search').val('');
            this.state.status = 'all';
            $('#manage-assets-status-filter .sr-status-chip').each(function () {
                var on = String($(this).data('status')) === 'all';
                $(this).toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
            });
            this.syncFilterCount();
            this.selectionChanged();
        },
        // The matching set changed: the old selection no longer describes it.
        selectionChanged: function () {
            this.state.selected = {};
            this.state.selectAll = false;
            this.refresh(true);
        },

        /* ---------------- data ---------------- */
        listParams: function () {
            var s = this.state, p = {};
            if (s.status === 'verified') { p.verified = 1; } else if (s.status === 'unverified') { p.verified = 0; }
            if (s.q) { p.q = s.q; }
            FILTER_KEYS.forEach(function (k) {
                if (s.filters[k].length) { p[k] = s.filters[k].join(','); }
            });
            return p;
        },
        // The same selection, as the filter object POST /assets/bulk accepts.
        // The API rejects an empty filter, so the unfiltered view says
        // "every asset in scope" explicitly with {all: true}.
        bulkFilter: function () {
            var s = this.state, f = {};
            if (s.status === 'verified') { f.verified = 1; } else if (s.status === 'unverified') { f.verified = 0; }
            if (s.q) { f.q = s.q; }
            FILTER_KEYS.forEach(function (k) {
                if (s.filters[k].length) { f[k] = s.filters[k].map(Number); }
            });
            return $.isEmptyObject(f) ? { all: true } : f;
        },
        requestColumns: function () {
            var self = this;
            var keys = FIXED_KEYS.slice();
            this.state.columns.forEach(function (c) { if (self.state.visible[c.key]) { keys.push(c.key); } });
            // The score chip is coloured by its band, so the band rides along.
            if (keys.indexOf('weighted_score') !== -1 && keys.indexOf('weighted_band') === -1) { keys.push('weighted_band'); }
            return keys;
        },
        // `quiet`: the Asset groups tab asks for this after its own change
        // and reloads itself; any other refresh (a save or delete in the
        // record modal, a bulk action) also tells that tab its aggregates
        // may be stale ('manageassets:refreshed').
        refresh: function (resetPaging, quiet) {
            if (!quiet) { $(document).trigger('manageassets:refreshed'); }
            if (!this.state.table) { return; }
            this.state.table.ajax.reload(null, !!resetPaging);
        },

        loadColumnSettings: function () {
            var self = this;
            $.ajax({ url: BASE_URL + '/api/v2/assets/column-settings', type: 'GET', dataType: 'json' })
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
                // Settings could not load: core columns at their defaults.
                // Default columns first, in their default order (as the server does).
                order = FALLBACK_DEFAULTS.slice();
                Object.keys(CORE_LABEL_KEYS).forEach(function (k) {
                    if (SCORING_COLUMN_KEYS.test(k) && !lookups.scoring_enabled) { return; }
                    available.push({ key: k, label_key: CORE_LABEL_KEYS[k], label: null, group: 'asset' });
                    saved[k] = FALLBACK_DEFAULTS.indexOf(k) !== -1 ? '1' : '0';
                    if (order.indexOf(k) === -1) { order.push(k); }
                });
            }
            var byKey = {};
            available.forEach(function (c) { byKey[c.key] = c; });
            // Saved order first, then anything allowed the order lacks.
            available.forEach(function (c) { if (order.indexOf(c.key) === -1) { order.push(c.key); } });
            this.state.order = order.filter(function (k) { return byKey[k]; });
            this.state.savedColumns = saved;
            // For Restore default layout: the server's defaults and offer.
            this.state.defaultColumns = (data && Array.isArray(data.default_columns)) ? data.default_columns.slice() : FALLBACK_DEFAULTS.slice();
            this.state.availableKeys = available.map(function (c) { return c.key; });
            this.state.columns = this.state.order
                .filter(function (k) { return FIXED_KEYS.indexOf(k) === -1; })
                .map(function (k) {
                    var c = byKey[k];
                    // label_key is a lang key (core columns); label is the raw
                    // custom-field name. Both are inserted as text.
                    return { key: k, label: c.label_key ? L(c.label_key) : String(c.label || k), group: c.group === 'custom' ? 'custom' : 'asset' };
                });
            this.state.visible = {};
            this.state.columns.forEach(function (c) { self.state.visible[c.key] = saved[c.key] === '1'; });
            this.buildTable();
            this.renderColpanel();
        },

        /* ---------------- table ---------------- */
        buildHeader: function () {
            var self = this;
            var $tr = $('#manage-assets-table thead tr').empty();
            var $check = $('<th>', { 'class': 'sr-check-col', 'data-key': '_check' }).appendTo($tr);
            if (CAN_BULK) {
                $check.append($('<input>', { type: 'checkbox', 'class': 'form-check-input', id: 'manage-assets-select-page', 'aria-label': L('SelectAll') }));
            }
            $('<th>', { 'class': 'sr-name-col sr-assets-name-col', 'data-key': 'name', text: L('AssetName') }).appendTo($tr);
            $('<th>', { 'class': 'sr-assets-status-col', 'data-key': 'verified', text: L('Status') }).appendTo($tr);
            this.state.columns.forEach(function (c) {
                $('<th>', { 'data-col': c.key, 'data-key': c.key, text: c.label }).toggleClass('d-none', !self.state.visible[c.key]).appendTo($tr);
            });
            $('<th>', { 'class': 'sr-actions-col', 'data-key': '_actions' }).append($('<span>', { 'class': 'visually-hidden', text: L('Actions') })).appendTo($tr);
        },
        isSortable: function (key) {
            return SORTABLE.indexOf(key) !== -1;
        },
        columnDefs: function () {
            var self = this;
            var blank = function () { return ''; };
            var defs = [
                { data: null, name: '_check', orderable: false, visible: CAN_BULK, className: 'sr-check-col', render: blank, createdCell: function (td, d, row) { self.cellCheck(td, row); } },
                { data: null, name: 'name', orderable: true, className: 'sr-name-col sr-assets-name-col dt-head-left', render: blank, createdCell: function (td, d, row) { self.cellName(td, row); } },
                { data: null, name: 'verified', orderable: true, className: 'sr-assets-status-col dt-head-left', render: blank, createdCell: function (td, d, row) { self.cellStatus(td, row); } }
            ];
            this.colKeys = ['_check', 'name', 'verified'];
            this.state.columns.forEach(function (c) {
                self.colKeys.push(c.key);
                defs.push({
                    data: null,
                    name: c.key,
                    sr_moveable: true,
                    orderable: self.isSortable(c.key),
                    className: 'dt-head-left sr-assets-col-' + c.key.replace(/[^a-z0-9_]/gi, ''),
                    render: blank,
                    createdCell: function (td, d, row) {
                        // data-label: the column's (server-resolved) label,
                        // shown before the value once rows stack (queue tier).
                        $(td).attr('data-col', c.key).attr('data-label', c.label).toggleClass('d-none', !self.state.visible[c.key]);
                        self.cellValue(td, c.key, row);
                        $(td).toggleClass('sr-assets-cell-empty', $(td).children('.sr-cell-dash').length > 0);
                    }
                });
            });
            this.colKeys.push('_actions');
            defs.push({ data: null, name: '_actions', orderable: false, className: 'sr-actions-col', render: blank, createdCell: function (td, d, row) { self.cellActions(td, row); } });
            return defs;
        },
        buildTable: function () {
            var self = this;
            this.buildHeader();
            var defs = this.columnDefs();
            // Sort/size/page from the URL: a sort key with no column here
            // (e.g. id) falls back to the default column, keeping the direction.
            var init = this.state.initial;
            var sortIdx = init.sort === URL_DEFAULTS.sort ? -1 : this.colKeys.indexOf(init.sort);
            if (sortIdx < 1 || sortIdx >= this.colKeys.length - 1 || !this.isSortable(init.sort)) { sortIdx = 1; init.sort = URL_DEFAULTS.sort; }
            this.state.table = $('#manage-assets-table').DataTable({
                serverSide: true,
                processing: true,
                searching: false,
                autoWidth: false,
                pagingType: 'simple_numbers',
                pageLength: init.perPage,
                displayStart: (init.page - 1) * init.perPage,
                lengthMenu: [PAGE_SIZES, PAGE_SIZES],
                order: [[sortIdx, init.dir]],
                columns: defs,
                // Drag a configurable column's header to move it; the fixed
                // columns stay put (see moveableColumns()).
                colReorder: { columns: moveableColumns(defs) },
                language: { emptyTable: '', zeroRecords: '', lengthMenu: L('Show') + ' _MENU_', processing: L('Loading') },
                dom: 'rt<"sr-table-foot"<"sr-table-foot-left"li><"sr-table-foot-right"p>>',
                ajax: function (d, callback) { self.fetchPage(d, callback); },
                createdRow: function (tr, row) {
                    $(tr).attr('data-asset-id', row.id).attr('data-verified', isVerified(row) ? '1' : '0')
                        .toggleClass('sr-assets-row-checked', !!(self.state.selectAll || self.state.selected[String(row.id)]));
                },
                drawCallback: function () { self.afterDraw(); }
            });
            keepCellsPaintedOnReorder(this.state.table);
            this.restoringOrder = true;
            applyDataColOrder(this.state.table, this.state.order);
            this.restoringOrder = false;
            syncNativeVisibility(this.state.table, this.state.visible);
            this.state.table.on('column-reorder.dt', function () {
                if (self.restoringOrder) { return; }
                self.orderChangedByDrag();
            });
        },
        // A header was dragged: the picker lists the new order and it is saved.
        orderChangedByDrag: function () {
            this.state.order = mergedOrder(this.state.order, dataColOrder(this.state.table));
            var byKey = {};
            this.state.columns.forEach(function (c) { byKey[c.key] = c; });
            this.state.columns = this.state.order.filter(function (k) { return byKey[k]; }).map(function (k) { return byKey[k]; });
            this.renderColpanel();
            this.saveColumnsDebounced();
        },
        fetchPage: function (d, callback) {
            var self = this;
            // Only the latest request may touch page state (R1): a superseded
            // request is aborted, and any response that still arrives for it
            // is dropped before it writes rows, totals or counts.
            var seq = ++this.fetchSeq;
            if (this.fetchXhr) { this.fetchXhr.abort(); }
            var params = this.listParams();
            params.page = Math.floor(d.start / d.length) + 1;
            params.per_page = d.length;
            var ord = d.order && d.order[0];
            if (ord) {
                // By name, not position: a dragged column changes positions.
                var key = (d.columns && d.columns[ord.column]) ? d.columns[ord.column].name : null;
                if (key && key.charAt(0) !== '_' && this.isSortable(key)) {
                    params.sort = key;
                    params.dir = ord.dir === 'desc' ? 'desc' : 'asc';
                }
            }
            params.columns = this.requestColumns().join(',');
            // A different page, page size or sort shows different rows: the
            // selection (and "all matching") resets, as for filter changes (D8).
            var pageKey = [d.start, d.length, params.sort || '', params.dir || ''].join('|');
            if (this.state.pageKey !== null && pageKey !== this.state.pageKey) {
                this.state.selected = {};
                this.state.selectAll = false;
            }
            this.state.pageKey = pageKey;
            this.fetchXhr = $.ajax({ url: BASE_URL + '/api/v2/assets', type: 'GET', data: params, dataType: 'json' })
                .done(function (json) {
                    if (seq !== self.fetchSeq) { return; }
                    self.fetchXhr = null;
                    var data = (json && json.data) || {};
                    self.state.loadFailed = false;
                    self.state.rows = data.assets || [];
                    self.state.total = data.total || 0;
                    self.state.page = Math.floor(d.start / d.length);
                    self.renderCounts(data.counts);
                    callback({ draw: d.draw, recordsTotal: self.state.total, recordsFiltered: self.state.total, data: self.state.rows });
                })
                .fail(function (xhr) {
                    if (seq !== self.fetchSeq || isAbort(xhr)) { return; }
                    self.fetchXhr = null;
                    self.state.loadFailed = true;
                    self.state.rows = [];
                    self.state.total = 0;
                    callback({ draw: d.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                });
        },
        renderCounts: function (counts) {
            if (!counts) { return; }
            this.state.counts = counts;
            $('#manage-assets-count-all').text(counts.all);
            $('#manage-assets-count-verified').text(counts.verified);
            $('#manage-assets-count-unverified').text(counts.unverified);
            $('#manage-assets-status-filter .sr-status-chip--attn').toggleClass('has-items', counts.unverified > 0);
            // The tab shows the whole (team-scoped) collection, so it only
            // takes the count from an unfiltered response.
            if (this.state.q === '' && this.filterValueCount() === 0) {
                $('#manage-assets-tab-count-assets').text(counts.all);
            }
        },
        afterDraw: function () {
            var colspan = $('#manage-assets-table thead th').length;
            var empty = this.state.loadFailed || !this.state.rows.length;
            // Past the last page (a verify in the Unverified segment, a delete,
            // a save from the record modal): step back to the last page that
            // has rows instead of calling the result empty (R3).
            if (!this.state.loadFailed && !this.state.rows.length && this.state.total > 0 && this.state.page > 0) {
                var len = this.state.table.page.len();
                this.state.table.page(Math.max(0, Math.ceil(this.state.total / len) - 1)).draw(false);
                return;
            }
            this.syncUrl();
            // ?asset=<id>[&mode=edit] / ?asset=new: read once, after the
            // first draw (the ?control_id= / ?test_id= precedent), so the
            // list is on screen behind the record.
            if (!this.recordLinkRead) {
                this.recordLinkRead = true;
                if (window.AssetRecordModal) { AssetRecordModal.openFromUrl(); }
            }
            if (this.state.loadFailed) {
                this.renderStateRow(colspan, 'error');
            } else if (this.state.total === 0) {
                this.renderStateRow(colspan, this.filtersActive() ? 'no-results' : 'no-data');
            }
            // Pager and "Showing 0 to 0 of 0" say nothing about an empty state.
            $('#manage-assets-panel .sr-table-foot').toggleClass('d-none', empty);
            this.syncSortIcons();
            this.syncBulkBar();
            this.syncScrollShadow();
            // A chip click redrew the table under the focused chip: put focus
            // back on the same chip (if the new page has one).
            var rf = this.state.refocusChip;
            if (rf) {
                this.state.refocusChip = null;
                $('#manage-assets-table .sr-assets-chip-filter').filter(function () {
                    return $(this).attr('data-filter-kind') === rf.kind && $(this).attr('data-filter-id') === rf.id;
                }).first().trigger('focus');
            }
        },
        showLoadingRow: function () {
            var $tbody = $('#manage-assets-table tbody').empty();
            $('<tr>', { 'class': 'sr-empty-row' }).append(
                $('<td>', { colspan: 99, 'class': 'sr-assets-loading' }).append(
                    $('<i>', { 'class': 'fa fa-spinner fa-spin', 'aria-hidden': 'true' }), ' ', $('<span>', { text: L('Loading') })
                )
            ).appendTo($tbody);
        },
        // design-system 10: the reason decides the copy and the one action.
        renderStateRow: function (colspan, intent) {
            var $box = $('<div>', { 'class': 'sr-table-empty', id: 'manage-assets-empty', 'data-intent': intent });
            var $icon = $('<div>', { 'class': 'sr-table-empty-icon' });
            var $action = $('<div>', { 'class': 'sr-table-empty-action' });
            if (intent === 'error') {
                $box.addClass('sr-table-empty-danger');
                $icon.append($('<i>', { 'class': 'fa fa-triangle-exclamation', 'aria-hidden': 'true' }));
                $box.append($icon, $('<div>', { 'class': 'sr-table-empty-title', text: L('CouldNotLoadAssets') }));
                $action.append($('<button>', { type: 'button', 'class': 'btn btn-outline-secondary btn-sm', id: 'manage-assets-retry', 'data-action': 'retry', text: L('Retry') }));
            } else if (intent === 'no-results') {
                $icon.append($('<i>', { 'class': 'fa fa-filter', 'aria-hidden': 'true' }));
                $box.append($icon, $('<div>', { 'class': 'sr-table-empty-title', text: L('NoAssetsMatchFilters') }));
                $action.append($('<button>', { type: 'button', 'class': 'btn btn-link btn-sm', id: 'manage-assets-empty-clear', 'data-action': 'clear-filters', text: L('ClearFilters') }));
            } else {
                $icon.append($('<i>', { 'class': 'fa fa-server', 'aria-hidden': 'true' }));
                $box.append($icon, $('<div>', { 'class': 'sr-table-empty-title', text: L('NoAssetsYet') }),
                    $('<div>', { 'class': 'sr-table-empty-body', text: L('NoAssetsYetHint') }));
                // Outline, not red: the tab row's Add asset stays the one red
                // button in view (design-system 3/10).
                $action.append($('<button>', { type: 'button', 'class': 'btn btn-outline-secondary btn-sm', id: 'manage-assets-empty-add', 'data-action': 'add', text: '+ ' + L('AddAsset') }));
            }
            $box.append($action);
            $('#manage-assets-table tbody').empty().append(
                $('<tr>', { 'class': 'sr-empty-row' }).append($('<td>', { colspan: colspan }).append($box))
            );
        },
        // DataTables' bundled carets swapped for the shared Font Awesome set
        // (design-system 6), driven off each header's aria-sort.
        syncSortIcons: function () {
            $('#manage-assets-table thead th').each(function () {
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

        /* ---------------- cells (DOM only; values inserted as text) ---------------- */
        cellCheck: function (td, row) {
            if (!CAN_BULK) { return; }
            var id = String(row.id);
            $(td).empty().append($('<input>', {
                type: 'checkbox', 'class': 'form-check-input sr-assets-row-check', 'data-id': id,
                'aria-label': L('Select') + ' ' + (row.name || ''),
                checked: !!(this.state.selectAll || this.state.selected[id])
            }));
        },
        cellName: function (td, row) {
            // Opens the asset record modal (View); the URL becomes ?asset=<id>.
            $(td).empty().append($('<button>', {
                type: 'button', 'class': 'sr-assets-name', 'data-action': 'row-view', 'data-id': row.id, text: row.name || ''
            }));
        },
        cellStatus: function (td, row) {
            var on = isVerified(row);
            // A filter button that keeps the pill's soft-state look.
            $(td).empty().append(filterChip({
                kind: 'status', id: on ? 'verified' : 'unverified', label: on ? L('Verified') : L('Unverified'),
                bare: true, extraClass: 'sr-state-pill ' + (on ? 'sr-state-success' : 'sr-state-warning')
            }));
        },
        // `filter` (optional): {kind, ids} with ids parallel to names; a chip
        // with a filterable id becomes a filter button, the rest stay plain.
        chipList: function (names, extraCount, filter) {
            if (!names.length) { return dash(); }
            var $row = $('<span>', { 'class': 'sr-chip-row' });
            names.slice(0, MAX_CHIPS).forEach(function (n, i) {
                var id = filter ? filter.ids[i] : null;
                if (filter && id != null && chipFilterable(filter.kind, id)) {
                    $row.append(filterChip({ kind: filter.kind, id: id, label: n }));
                } else {
                    $row.append($('<span>', { 'class': 'sr-chip', text: n }));
                }
            });
            var more = names.length - MAX_CHIPS + (extraCount || 0);
            if (more > 0) { $row.append($('<span>', { 'class': 'sr-assets-more', text: '+' + more, title: names.slice(MAX_CHIPS).join(', ') })); }
            return $row;
        },
        idsToNames: function (csv, map) {
            return this.idNamePairs(csv, map).names;
        },
        // Names and the ids they came from, index for index.
        idNamePairs: function (csv, map) {
            var out = { names: [], ids: [] };
            String(csv || '').split(',').map(function (s) { return s.trim(); })
                .filter(function (s) { return s !== '' && s !== '0'; })
                .forEach(function (id) {
                    if (map[id] != null) { out.names.push(String(map[id])); out.ids.push(id); }
                });
            return out;
        },
        cellValue: function (td, key, row) {
            var $td = $(td).empty();
            var v = row[key];
            switch (key) {
                case 'ip':
                    $td.append(v ? $('<span>', { 'class': 'sr-num', text: v }) : dash());
                    break;
                case 'value':
                    $td.append(valuationCell(v, true));
                    break;
                case 'location':
                    var loc = this.idNamePairs(v, this.maps.locations);
                    $td.append(this.chipList(loc.names, 0, { kind: 'location', ids: loc.ids }));
                    break;
                case 'teams':
                    var tm = this.idNamePairs(v, this.maps.teams);
                    $td.append(this.chipList(tm.names, 0, { kind: 'team', ids: tm.ids }));
                    break;
                case 'tags':
                    // tag_list, not the comma-joined `tags`: a tag may contain a comma.
                    var tagNames = (row.tag_list || []).map(String);
                    $td.append(this.chipList(tagNames, 0, { kind: 'tag', ids: tagNames.map(tagIdForName) }));
                    break;
                case 'details': {
                    var text = htmlToText(v);
                    $td.append(text ? $('<span>', { 'class': 'sr-cell-truncate', title: text, text: text }) : dash());
                    break;
                }
                case 'mapped_controls':
                    $td.append(this.chipList((v || []).map(function (c) { return String(c.short_name); })));
                    break;
                case 'associated_risks': {
                    var risks = v || [];
                    if (!risks.length) {
                        // Without Risk Management the API sends only a count
                        // of the risks the viewer could see, never subjects (SR-2313).
                        var riskCount = Number(row.associated_risks_count) || 0;
                        $td.append(riskCount > 0
                            ? $('<span>', { 'class': 'sr-assets-risk-count', text: String(L('NAssociatedRisks')).split('{n}').join(String(riskCount)) })
                            : dash());
                        break;
                    }
                    var $row = $('<span>', { 'class': 'sr-chip-row' });
                    risks.slice(0, MAX_CHIPS).forEach(function (r) {
                        $row.append($('<a>', {
                            'class': 'sr-chip sr-assets-risk-link',
                            href: BASE_URL + '/management/view.php?id=' + encodeURIComponent(r.display_id),
                            title: String(r.subject || ''),
                            text: '#' + r.display_id
                        }));
                    });
                    if (risks.length > MAX_CHIPS) { $row.append($('<span>', { 'class': 'sr-assets-more', text: '+' + (risks.length - MAX_CHIPS) })); }
                    $td.append($row);
                    break;
                }
                case 'created': {
                    if (!v) { $td.append(dash()); break; }
                    var label = (window.moment && moment(v, 'YYYY-MM-DD HH:mm:ss').isValid()) ? moment(v, 'YYYY-MM-DD HH:mm:ss').format('ll') : String(v).substring(0, 10);
                    $td.append($('<span>', { 'class': 'sr-num', title: String(v), text: label }));
                    break;
                }
                case 'confidentiality':
                case 'integrity':
                case 'availability':
                    $td.append(ratingChip(key, v));
                    break;
                case 'fips_categorization':
                    $td.append(scoringChip('categorization', v));
                    break;
                case 'weighted_band':
                    $td.append(scoringChip('band', v));
                    break;
                case 'weighted_score':
                    $td.append(scoreChip(v, row.weighted_band));
                    break;
                default: // custom_field_<id>: server-resolved display text
                    $td.append(v ? $('<span>', { 'class': 'sr-cell-truncate', title: String(v), text: String(v) }) : dash());
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
            // Actions the user lacks are omitted, never disabled (spec 5).
            add('row-view', 'fa-eye', 'View');
            if (caps.can_edit) { add('row-edit', 'fa-pen', 'Edit'); }
            if (caps.can_verify && !isVerified(row)) { add('row-verify', 'fa-check', 'Verify'); }
            if (CAN_ADD_TO_GROUP) { add('row-add-to-group', 'fa-layer-group', 'AssetBulkAddToGroup'); }
            if (caps.can_delete) { add('row-delete', 'fa-trash', 'Delete', true); }
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

        /* ---------------- Columns picker (searchable; Review Risk's shape) ---------------- */
        renderColpanel: function () {
            var self = this;
            var $panel = $('#manage-assets-colpanel').empty();
            $('<div>', { 'class': 'colpanel-search' }).append(
                $('<i>', { 'class': 'fa fa-search colpanel-search-icon', 'aria-hidden': 'true' }),
                // autocomplete is set with .attr(): as a key of jQuery's props
                // object it is treated as a METHOD name, and jQuery UI's
                // autocomplete widget (loaded on this page) throws on it.
                $('<input>', { type: 'text', 'class': 'colpanel-search-input', id: 'manage-assets-colpanel-search', placeholder: L('Search'), 'aria-label': L('Search') }).attr('autocomplete', 'off')
            ).appendTo($panel);
            var item = function (key, label, checked, locked) {
                return $('<label>', { 'class': 'colpanel-item' }).append(
                    $('<input>', { type: 'checkbox', 'data-col': key, checked: checked, disabled: !!locked }),
                    document.createTextNode(' ' + label)
                );
            };
            var groups = [
                { id: 'asset', label: L('AssetFields') },
                { id: 'custom', label: L('CustomFields') }
            ];
            groups.forEach(function (g) {
                var cols = self.state.columns.filter(function (c) { return c.group === g.id; });
                if (g.id === 'asset') {
                    // Locked on: the name and the verified state never hide.
                    cols = [{ key: 'name', label: L('AssetName'), locked: true }, { key: 'verified', label: L('Status'), locked: true }].concat(cols);
                }
                if (!cols.length) { return; }
                var $g = $('<div>', { 'class': 'colpanel-group', 'data-group': g.id }).append($('<div>', { 'class': 'colpanel-group-label', text: g.label }));
                cols.forEach(function (c) { $g.append(item(c.key, c.label, c.locked ? true : !!self.state.visible[c.key], c.locked)); });
                $panel.append($g);
            });
            $('<div>', { 'class': 'colpanel-empty d-none', text: L('NoMatchingOptions') }).appendTo($panel);
            $('<div>', { 'class': 'colpanel-foot' }).append($('<button>', {
                type: 'button', 'class': 'btn btn-link btn-sm colpanel-reset', id: 'manage-assets-colpanel-reset', 'data-action': 'reset-columns', text: L('RestoreDefaultLayout')
            })).appendTo($panel);
        },
        // The default columns, in the default order (drag order included).
        restoreDefaultColumns: function () {
            var self = this;
            var layout = defaultLayout(this.state.defaultColumns, this.state.availableKeys);
            this.state.order = layout.order;
            this.state.columns.forEach(function (c) { self.state.visible[c.key] = !!layout.visible[c.key]; });
            var byKey = {};
            this.state.columns.forEach(function (c) { byKey[c.key] = c; });
            this.state.columns = layout.order.filter(function (k) { return byKey[k]; }).map(function (k) { return byKey[k]; });
            if (this.state.table) {
                this.restoringOrder = true;
                applyDataColOrder(this.state.table, layout.order);
                this.restoringOrder = false;
            }
            this.renderColpanel();
            this.applyColumnVisibility();
            this.saveColumnsDebounced();
            this.refresh(false);
        },
        filterColpanelItems: function (query) {
            var q = String(query || '').toLowerCase();
            var anyVisible = false;
            $('#manage-assets-colpanel .colpanel-group').each(function () {
                var $group = $(this), groupHasMatch = false;
                $group.find('.colpanel-item').each(function () {
                    var matches = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
                    $(this).toggleClass('d-none', !matches);
                    if (matches) { groupHasMatch = true; }
                });
                $group.toggleClass('d-none', !groupHasMatch);
                if (groupHasMatch) { anyVisible = true; }
            });
            $('#manage-assets-colpanel .colpanel-empty').toggleClass('d-none', anyVisible);
        },
        resetColpanelSearch: function () {
            $('#manage-assets-colpanel-search').val('');
            this.filterColpanelItems('');
        },
        closeColpanel: function () {
            var $panel = $('#manage-assets-colpanel');
            if (!$panel.hasClass('d-none')) {
                $panel.addClass('d-none');
                $('#manage-assets-colpicker-btn').attr('aria-expanded', 'false');
                this.resetColpanelSearch();
            }
        },
        bindColpicker: function () {
            var self = this;
            $(document).on('input', '#manage-assets-colpanel-search', function () { self.filterColpanelItems($(this).val()); });
            $('#manage-assets-colpicker-btn').on('click', function (e) {
                e.stopPropagation();
                var $panel = $('#manage-assets-colpanel').toggleClass('d-none');
                $(this).attr('aria-expanded', $panel.hasClass('d-none') ? 'false' : 'true');
                self.resetColpanelSearch();
                if (!$panel.hasClass('d-none')) { $('#manage-assets-colpanel-search').trigger('focus'); }
            });
            $(document).on('click', function (e) {
                if (!$(e.target).closest('#manage-assets-card .colpicker').length) { self.closeColpanel(); }
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && !$('#manage-assets-colpanel').hasClass('d-none')) {
                    self.closeColpanel();
                    $('#manage-assets-colpicker-btn').trigger('focus');
                }
            });
            $(document).on('click', '#manage-assets-colpanel-reset', function (e) {
                // The panel is rebuilt under this click: keep the
                // outside-click handler from reading it as a click outside.
                e.stopImmediatePropagation();
                self.restoreDefaultColumns();
                $('#manage-assets-colpanel-search').trigger('focus');
            });
            $(document).on('change', '#manage-assets-colpanel input[type="checkbox"]', function () {
                var key = String($(this).data('col'));
                if (FIXED_KEYS.indexOf(key) !== -1) { this.checked = true; return; }
                self.state.visible[key] = this.checked;
                self.applyColumnVisibility();
                self.saveColumnsDebounced();
                // A column turned on was not requested for this page yet.
                if (this.checked) { self.refresh(false); }
            });
        },
        applyColumnVisibility: function () {
            var self = this;
            // Native visibility first (ColReorder's drop zones read it), then d-none.
            if (this.state.table) { syncNativeVisibility(this.state.table, this.state.visible); }
            this.state.columns.forEach(function (c) {
                $('#manage-assets-table [data-col="' + c.key + '"]').toggleClass('d-none', !self.state.visible[c.key]);
            });
            // Keep an empty/error state row spanning the visible width.
            $('#manage-assets-table tbody tr.sr-empty-row > td').attr('colspan', $('#manage-assets-table thead th').length);
            this.syncScrollShadow();
        },
        // The pinned ⋯ column shows its separator only while columns are
        // scrolled underneath it (there is more table to its right to reveal).
        syncScrollShadow: function () {
            var el = $('#manage-assets-panel > .sr-table-scroll')[0];
            if (!el) { return; }
            var under = el.scrollWidth - el.clientWidth - el.scrollLeft > 1;
            $(el).toggleClass('sr-assets-scrolled-under', under);
        },
        // One PUT at a time (R5): a change made while a save is in flight is
        // sent once that save settles, so saves cannot land out of order. An
        // unchanged payload is not re-sent. A failed save puts the columns
        // back to what is actually stored.
        saveColumns: function () {
            var self = this, st = this.colSave;
            if (st.inFlight) { st.pending = true; return; }
            var columns = this.state.order.map(function (k) {
                if (k === 'name') { return [k, '1']; }
                if (k === 'verified') { return [k, self.state.savedColumns.verified === '1' ? '1' : '0']; }
                if (self.state.visible[k] !== undefined) { return [k, self.state.visible[k] ? '1' : '0']; }
                return [k, self.state.savedColumns[k] === '1' ? '1' : '0'];
            });
            var body = JSON.stringify({ columns: columns, order: this.state.order });
            if (body === st.lastSent) { return; }
            st.inFlight = true;
            var settle = function () {
                st.inFlight = false;
                if (st.pending) { st.pending = false; self.saveColumns(); }
            };
            $.ajax({
                url: BASE_URL + '/api/v2/assets/column-settings', type: 'PUT',
                contentType: 'application/json', headers: csrfHeaders(), dataType: 'json',
                data: body,
                success: function () {
                    st.lastSent = body;
                    columns.forEach(function (p) { self.state.savedColumns[p[0]] = p[1]; });
                    settle();
                },
                error: function (xhr) {
                    if (reissuedByCsrfRetry(xhr, this)) { return; }
                    reportError(xhr);
                    st.pending = false;
                    st.inFlight = false;
                    self.revertColumns();
                }
            });
        },
        revertColumns: function () {
            var self = this;
            this.state.columns.forEach(function (c) { self.state.visible[c.key] = self.state.savedColumns[c.key] === '1'; });
            this.renderColpanel();
            this.applyColumnVisibility();
        },

        /* ---------------- selection + bulk bar ---------------- */
        selectedIds: function () { return Object.keys(this.state.selected).map(Number); },
        selectedCount: function () {
            return this.state.selectAll ? this.state.total : this.selectedIds().length;
        },
        // Selection ids come from the rows actually on screen (their checkbox
        // and name), never from a data array a late response could replace.
        renderedRows: function () {
            return $('#manage-assets-table tbody tr[data-asset-id]').map(function () {
                return { id: String($(this).attr('data-asset-id')), name: $(this).find('.sr-assets-name').text() };
            }).get();
        },
        bindSelection: function () {
            var self = this;
            $('#manage-assets-table').on('change', '.sr-assets-row-check', function () {
                var id = String($(this).data('id'));
                if (self.state.selectAll) {
                    // Leaving "all N": keep this page's rows, minus the one unticked.
                    self.state.selectAll = false;
                    self.state.selected = {};
                    self.renderedRows().forEach(function (r) { self.state.selected[r.id] = r.name; });
                }
                if (this.checked) {
                    self.state.selected[id] = $(this).closest('tr').find('.sr-assets-name').text();
                } else {
                    delete self.state.selected[id];
                }
                self.syncBulkBar();
            });
            $('#manage-assets-table').on('change', '#manage-assets-select-page', function () {
                var on = this.checked;
                if (!on) { self.state.selectAll = false; }
                self.renderedRows().forEach(function (r) {
                    if (on) { self.state.selected[r.id] = r.name; } else { delete self.state.selected[r.id]; }
                });
                self.syncBulkBar();
            });
            $('#manage-assets-bulk-clear').on('click', function () {
                self.state.selected = {};
                self.state.selectAll = false;
                self.syncBulkBar();
            });
            $('#manage-assets-select-all-filtered').on('click', function () {
                self.state.selectAll = true;
                self.syncBulkBar();
            });
            $('#manage-assets-bulk-verify').on('click', function () {
                var target = self.selection();
                if (!self.overBulkLimit('verify', target)) { self.runBulk('verify', target, {}); }
            });
            $('#manage-assets-bulk-assign-teams').on('click', function () { self.openTeamsModal(self.selection()); });
            $('#manage-assets-bulk-add-to-group').on('click', function () { self.openGroupModal(self.selection()); });
            $('#manage-assets-bulk-delete').on('click', function () { self.openDeleteModal(self.selection()); });
        },
        // The current selection as a bulk-request target: the filter when
        // escalated to "all N" (never N ids), else the ticked ids.
        selection: function () {
            if (this.state.selectAll) { return { filter: this.bulkFilter(), count: this.state.total }; }
            var ids = this.selectedIds();
            return { ids: ids, count: ids.length, name: ids.length === 1 ? this.state.selected[String(ids[0])] : null };
        },
        syncBulkBar: function () {
            var self = this;
            var n = this.selectedCount();
            $('#manage-assets-table .sr-assets-row-check').each(function () {
                var on = !!(self.state.selectAll || self.state.selected[String($(this).data('id'))]);
                this.checked = on;
                $(this).closest('tr').toggleClass('sr-assets-row-checked', on);
            });
            var $checks = $('#manage-assets-table .sr-assets-row-check');
            var checked = $checks.filter(':checked').length;
            $('#manage-assets-select-page').prop('checked', $checks.length > 0 && checked === $checks.length)
                .prop('indeterminate', checked > 0 && checked < $checks.length);
            $('#manage-assets-toolbar').toggleClass('d-none', n > 0);
            if (n > 0) { this.closeColpanel(); }
            $('#manage-assets-bulk-bar').toggleClass('d-none', n === 0);
            $('#manage-assets-bulk-count').text(this.state.selectAll
                ? fmt('AssetBulkAllSelected', { count: this.state.total })
                : String(L('NSelected')).replace('{n}', n));
            var offer = !this.state.selectAll && n > 0 && this.state.total > n;
            $('#manage-assets-select-all-filtered').toggleClass('d-none', !offer)
                .text(fmt('AssetBulkSelectAll', { count: this.state.total }));
        },

        /* ---------------- row actions ---------------- */
        bindRowActions: function () {
            var self = this;
            if (window.SRRowActionsMenu) {
                SRRowActionsMenu.bind({ container: '#manage-assets-table', scope: $('#manage-assets-table'), namespace: 'manageassets' });
            }
            $('#manage-assets-table').on('click', '[data-action="row-view"]', function () { self.openRecord($(this).data('id'), 'view'); });
            $('#manage-assets-table').on('click', '[data-action="row-edit"]', function () { self.openRecord($(this).data('id'), 'edit'); });
            $('#manage-assets-table').on('click', '[data-action="row-verify"]', function () {
                self.runBulk('verify', { ids: [Number($(this).data('id'))], count: 1 }, {});
            });
            $('#manage-assets-table').on('click', '[data-action="row-add-to-group"]', function () {
                self.openGroupModal({ ids: [Number($(this).data('id'))], count: 1 });
            });
            $('#manage-assets-table').on('click', '[data-action="row-delete"]', function () {
                var id = Number($(this).data('id')), row = self.rowById(id);
                self.openDeleteModal({ ids: [id], count: 1, name: row ? row.name : '' });
            });
        },

        /* ---------------- bulk requests ---------------- */
        SUMMARY_KEYS: {
            verify: 'AssetBulkVerifiedSummary', 'delete': 'AssetBulkDeletedSummary',
            assign_teams: 'AssetBulkTeamsSummary', add_to_group: 'AssetBulkGroupSummary'
        },
        // One bulk request at a time (R4): while one is in flight the bulk bar
        // and the row Verify items are inert and a second call is ignored.
        // Outcome handlers live in the ajax settings so a CSRF-token retry,
        // which re-sends those same settings, still reports and refreshes (R2).
        runBulk: function (action, target, params, onDone) {
            var self = this;
            if (this.state.bulkBusy) { return false; }
            this.setBulkBusy(true);
            var body = { action: action, params: params || {} };
            // "Select all N" sends the N it showed; the API refuses (409) when
            // the matching set has changed since, instead of acting on it.
            if (target.filter) { body.filter = target.filter; body.expected_count = target.count; } else { body.ids = target.ids; }
            var finish = function (ok) {
                self.setBulkBusy(false);
                if (onDone) { onDone(ok); }
            };
            $.ajax({
                type: 'POST', url: BASE_URL + '/api/v2/assets/bulk',
                contentType: 'application/json', headers: csrfHeaders(), dataType: 'json',
                data: JSON.stringify(body),
                success: function (json) {
                    var res = (json && json.data) || {};
                    var summary = res.summary || { ok: 0, failed: 0 };
                    self.toastResults(action, summary, res.results || []);
                    self.state.selected = {};
                    self.state.selectAll = false;
                    // A delete can empty the current page; go back to the first.
                    self.refresh(action === 'delete');
                    finish(true);
                },
                error: function (xhr) {
                    if (reissuedByCsrfRetry(xhr, this)) { return; }   // the retry reports
                    reportError(xhr);
                    if (xhr && xhr.status === 409) {
                        // The set changed since the user selected it: show the
                        // current list and count, and make them select again.
                        // The open confirm dialog describes the old set: close it.
                        self.state.selected = {};
                        self.state.selectAll = false;
                        self.pending = null;
                        $('#manage-assets-delete-modal, #manage-assets-teams-modal, #manage-assets-group-modal').modal('hide');
                        self.refresh(false);
                    }
                    finish(false);
                }
            });
            return true;
        },
        setBulkBusy: function (on) {
            this.state.bulkBusy = on;
            $('#manage-assets-bulk-bar .sr-bulk-actions .btn').prop('disabled', on).toggleClass('is-busy', on);
            $('#manage-assets-table').toggleClass('sr-assets-busy', on);
        },
        // One toast per action (design-system 9: don't flood): the summary,
        // then the skipped assets and why. Escaped once here; toastr decodes.
        toastResults: function (action, summary, results) {
            var self = this;
            var lines = [esc(fmt(this.SUMMARY_KEYS[action], { ok: summary.ok || 0, failed: summary.failed || 0 }))];
            // An asset outside the caller's scope is reported as not_found too.
            var reasons = { not_found: 'AssetBulkReasonNotFound' };
            var skipped = results.filter(function (r) { return r.status !== 'ok'; });
            if (skipped.length) {
                var items = skipped.slice(0, 10).map(function (r) {
                    var row = self.rowById(r.id);
                    var who = row && row.name ? row.name : '#' + r.id;
                    // `message` is a language key for errors (e.g. not attempted after a failure).
                    return who + ' (' + L(reasons[r.status] || r.message || 'Failed') + ')';
                });
                if (skipped.length > 10) { items.push('…'); }
                lines.push(esc(fmt('AssetBulkSkippedList', { list: items.join(', ') })));
            }
            showAlertFromMessage(lines.join('<br>'), skipped.length === 0);
        },

        /* ---------------- modals ---------------- */
        pending: null,
        bindModals: function () {
            var self = this;
            $('#manage-assets-delete-modal').on('shown.bs.modal', function () { $('#manage-assets-delete-cancel').trigger('focus'); });

            // Confirm button: disabled + busy while the request runs; the
            // dialog closes on success and stays open on failure (design-system 8).
            var confirmWith = function ($btn, $modal, action, paramsFn) {
                $btn.on('click', function () {
                    var params = paramsFn();
                    if (!params || !self.pending) { return; }
                    $btn.prop('disabled', true).addClass('is-busy');
                    var started = self.runBulk(action, self.pending, params, function (ok) {
                        $btn.prop('disabled', false).removeClass('is-busy');
                        if (ok) { $modal.modal('hide'); }
                    });
                    if (!started) { $btn.prop('disabled', false).removeClass('is-busy'); }
                });
            };
            confirmWith($('#manage-assets-delete-confirm'), $('#manage-assets-delete-modal'), 'delete', function () { return {}; });
            confirmWith($('#manage-assets-teams-confirm'), $('#manage-assets-teams-modal'), 'assign_teams', function () {
                var teamIds = ($('#manage-assets-teams-select').val() || []).map(Number);
                return teamIds.length ? { team_ids: teamIds } : null;
            });
            $('#manage-assets-group-confirm').on('click', function () {
                var $btn = $(this), $modal = $('#manage-assets-group-modal');
                if (!self.pending) { return; }
                if (self.groupModalIsNew()) {
                    self.createGroupFromSelection(self.pending, $btn, $modal);
                    return;
                }
                var groupId = Number($('#manage-assets-group-select').val());
                if (!groupId) { return; }
                $btn.prop('disabled', true).addClass('is-busy');
                var started = self.runBulk('add_to_group', self.pending, { group_id: groupId }, function (ok) {
                    $btn.prop('disabled', false).removeClass('is-busy');
                    if (ok) { $modal.modal('hide'); }
                });
                if (!started) { $btn.prop('disabled', false).removeClass('is-busy'); }
            });

            $('#manage-assets-teams-select').on('change', function () {
                $('#manage-assets-teams-confirm').prop('disabled', !($(this).val() || []).length);
            });
            $('#manage-assets-group-select').on('change', function () {
                var isNew = String($(this).val() || '') === NEW_GROUP;
                $('#manage-assets-group-new-field').toggleClass('d-none', !isNew);
                self.setNewGroupError(null);
                if (isNew) { $('#manage-assets-group-new-name').trigger('focus'); }
                self.syncGroupConfirm();
            });
            $('#manage-assets-group-new-name').on('input', function () {
                self.setNewGroupError(null);
                self.syncGroupConfirm();
            }).on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); $('#manage-assets-group-confirm').trigger('click'); }
            });
            $('#manage-assets-group-modal').on('shown.bs.modal', function () {
                if (self.groupModalIsNew()) { $('#manage-assets-group-new-name').trigger('focus'); }
            });

            // Focus goes back to the control that opened the dialog (a row's
            // menu item is hidden by then, so its row's ⋯ toggle instead); if
            // that row is gone (deleted), to the search box.
            $('#manage-assets-delete-modal, #manage-assets-teams-modal, #manage-assets-group-modal').on('hidden.bs.modal', function () {
                self.restoreFocus();
            });

            this.fillTeamsSelect();
            this.fillGroupSelect();
        },
        fillTeamsSelect: function () {
            var $sel = $('#manage-assets-teams-select');
            if (!$sel.length) { return; }
            $sel.empty();
            (lookups.team_options || []).forEach(function (t) { $('<option>', { value: t.id, text: t.name }).appendTo($sel); });
            if (typeof srSelectEnhance === 'function') {
                if ($sel.data('srSelect')) { srSelectRender($sel); } else { srSelectEnhance($sel, $sel.data('placeholder')); }
            }
        },
        fillGroupSelect: function () {
            var $sel = $('#manage-assets-group-select');
            if (!$sel.length) { return; }
            $sel.empty().append($('<option>', { value: '', text: $sel.data('placeholder') }));
            (lookups.groups || []).forEach(function (g) { $('<option>', { value: g.id, text: g.name }).appendTo($sel); });
            if (caps.can_group_create) { $('<option>', { value: NEW_GROUP, text: L('AssetCreateNewGroupOption') }).appendTo($sel); }
            if (typeof srSelectEnhance === 'function') {
                if ($sel.data('srSelect')) { srSelectRender($sel); } else { srSelectEnhance($sel, $sel.data('placeholder')); }
            }
        },
        rememberOpener: function () {
            var el = document.activeElement;
            var $wrap = $(el).closest('.sr-row-actions-wrap');
            if ($(el).is('#asset-groups-table [data-action="member-view"]')) {
                // An Asset groups drawer's View: the drawer may be rebuilt
                // (a save refreshes the tab), so remember the member, not the button.
                this.lastOpener = { memberView: { gid: String($(el).attr('data-group-id')), aid: String($(el).attr('data-asset-id')) } };
            } else if ($wrap.length) {
                // Remember the row, not the element: a refresh rebuilds rows.
                this.lastOpener = { rowId: String($wrap.closest('tr').attr('data-asset-id')) };
            } else if ($(el).is('#manage-assets-table .sr-assets-name')) {
                this.lastOpener = { rowId: String($(el).closest('tr').attr('data-asset-id')), name: true };
            } else {
                this.lastOpener = { el: el && el !== document.body ? el : null };
            }
        },
        restoreFocus: function () {
            var o = this.lastOpener || {};
            this.lastOpener = null;
            var $target = $();
            if (o.memberView) {
                var mv = o.memberView;
                $target = $('#asset-groups-table [data-action="member-view"][data-group-id="' + mv.gid + '"][data-asset-id="' + mv.aid + '"]');
                if (!$target.length) { $target = $('#asset-groups-table tr[data-group-id="' + mv.gid + '"] .sr-group-caret'); }
                if (!$target.length) { $target = $('#asset-groups-search'); }
            } else if (o.rowId) {
                $target = $('#manage-assets-table tr[data-asset-id="' + o.rowId + '"] ' + (o.name ? '.sr-assets-name' : '.sr-row-actions-toggle'));
            } else if (o.el && document.body.contains(o.el) && $(o.el).is(':visible')) {
                $target = $(o.el);
            }
            if (!$target.length) {
                $target = $('#manage-assets-bulk-bar').is(':visible') ? $('#manage-assets-bulk-clear') : $('#manage-assets-search');
            }
            $target.first().trigger('focus');
        },
        // The selection is larger than one request may act on (the API
        // enforces the same caps): say so and ask the user to narrow it.
        overBulkLimit: function (action, target) {
            var max = cfg.bulkMax || {};
            var limit = action === 'delete' ? max['delete'] : max.other;
            if (!limit || target.count <= limit) { return false; }
            showAlertFromMessage(esc(fmt(action === 'delete' ? 'AssetBulkTooManyToDelete' : 'AssetBulkTooManyAssets', { max: limit })), false);
            return true;
        },
        openDeleteModal: function (target) {
            if (!caps.can_delete || !target.count) { return; }
            if (this.overBulkLimit('delete', target)) { return; }
            this.rememberOpener();
            this.pending = target;
            var single = !target.filter && target.count === 1;
            $('#manage-assets-delete-title').text(single
                ? fmt('AssetDeleteConfirmTitle', { name: target.name || ('#' + target.ids[0]) })
                : fmt('AssetBulkDeleteConfirmTitle', { count: target.count }));
            $('#manage-assets-delete-confirm').text(single ? L('DeleteAsset') : L('DeleteAssets'));
            $('#manage-assets-delete-modal').modal('show');
        },
        openTeamsModal: function (target) {
            if (!caps.can_edit || !target.count) { return; }
            if (this.overBulkLimit('assign_teams', target)) { return; }
            this.rememberOpener();
            this.pending = target;
            var $sel = $('#manage-assets-teams-select').val([]);
            if ($sel.data('srSelect')) { srSelectRender($sel); }
            $('#manage-assets-teams-confirm').prop('disabled', true);
            $('#manage-assets-teams-title').text(fmt('AssetBulkAssignTeamsTitle', { count: target.count }));
            $('#manage-assets-teams-modal').modal('show');
        },
        openGroupModal: function (target) {
            if (!CAN_ADD_TO_GROUP || !target.count) { return; }
            if (this.overBulkLimit('add_to_group', target)) { return; }
            this.rememberOpener();
            this.pending = target;
            var $sel = $('#manage-assets-group-select').val('');
            if ($sel.data('srSelect')) { srSelectRender($sel); }
            // Without the existing-group picker (no asset_group_edit) the
            // dialog is the new-group form alone.
            $('#manage-assets-group-new-field').toggleClass('d-none', $sel.length > 0);
            $('#manage-assets-group-new-name').val('');
            this.setNewGroupError(null);
            this.syncGroupConfirm();
            $('#manage-assets-group-title').text(fmt('AssetAddToGroupTitle', { count: target.count }));
            $('#manage-assets-group-modal').modal('show');
        },
        // True when the Add to group dialog is creating a new group.
        groupModalIsNew: function () {
            var $sel = $('#manage-assets-group-select');
            return $('#manage-assets-group-new-name').length > 0
                && ($sel.length === 0 || String($sel.val() || '') === NEW_GROUP);
        },
        syncGroupConfirm: function () {
            var ready = this.groupModalIsNew()
                ? String($('#manage-assets-group-new-name').val() || '').trim() !== ''
                : !!$('#manage-assets-group-select').val();
            $('#manage-assets-group-confirm').prop('disabled', !ready);
        },
        setNewGroupError: function (msg) {
            $('#manage-assets-group-new-name').toggleClass('is-invalid', !!msg).attr('aria-invalid', msg ? 'true' : 'false');
            $('#manage-assets-group-new-name-error').text(msg || '');
        },
        // "Create a new group…": POST /asset-groups with the selected assets.
        // A filter selection ("all N") with asset_group_edit creates the group
        // and then adds the matching set through POST /assets/bulk (which keeps
        // its changed-set 409 check); without asset_group_edit the matching
        // ids are listed first, up to what one create request can carry.
        createGroupFromSelection: function (target, $btn, $modal) {
            var self = this;
            var name = String($('#manage-assets-group-new-name').val() || '').trim();
            if (!name || this.state.bulkBusy) { return; }
            var viaBulk = !!target.filter && !!caps.can_group_edit;
            if (target.filter && !viaBulk && target.count > NEW_GROUP_MAX_IDS) {
                showAlertFromMessage(esc(fmt('AssetBulkTooManyAssets', { max: NEW_GROUP_MAX_IDS })), false);
                return;
            }
            $btn.prop('disabled', true).addClass('is-busy');
            this.setBulkBusy(true);
            var fail = function (xhr) {
                self.setBulkBusy(false);
                $btn.prop('disabled', false).removeClass('is-busy');
                var body = xhr && xhr.responseJSON;
                if (body && body.data && body.data.error === 'duplicate_name') {
                    // On the field that is wrong; the dialog stays open.
                    self.setNewGroupError(L('AssetGroupNameAlreadyInUse'));
                    $('#manage-assets-group-new-name').trigger('focus');
                    return;
                }
                reportError(xhr);
            };
            var afterCreate = function (json) {
                var groupId = Number(json && json.data && json.data.id);
                if (self.groups && typeof self.groups.refreshGroupLookups === 'function') { self.groups.refreshGroupLookups(); }
                if (self.groups && typeof self.groups.reload === 'function') { self.groups.reload(false); }
                if (viaBulk && groupId) {
                    self.setBulkBusy(false);
                    self.runBulk('add_to_group', target, { group_id: groupId }, function (ok) {
                        $btn.prop('disabled', false).removeClass('is-busy');
                        if (ok) { $modal.modal('hide'); }
                    });
                    return;
                }
                self.setBulkBusy(false);
                $btn.prop('disabled', false).removeClass('is-busy');
                // Escaped at its source (json_response).
                var msg = json && json.status_message;
                showAlertFromMessage(typeof msg === 'string' && msg ? msg : esc(L('SavedSuccess')), true);
                self.state.selected = {};
                self.state.selectAll = false;
                $modal.modal('hide');
                self.refresh(false);
            };
            var create = function (ids) {
                $.ajax({
                    url: BASE_URL + '/api/v2/asset-groups', type: 'POST',
                    data: { name: name, selected_assets: ids }, headers: csrfHeaders(), dataType: 'json',
                    success: afterCreate,
                    error: function (xhr) {
                        if (reissuedByCsrfRetry(xhr, this)) { return; }
                        fail(xhr);
                    }
                });
            };
            if (viaBulk) { create([]); return; }
            this.resolveTargetIds(target, function (ids) { create(ids); }, fail);
        },
        // The asset ids a bulk target stands for: the ticked ids, or every id
        // matching the filter (listed through GET /assets, 500 per page).
        resolveTargetIds: function (target, done, fail) {
            if (!target.filter) { done((target.ids || []).slice()); return; }
            var params = this.listParams(), ids = [], page = 1;
            var next = function () {
                $.ajax({
                    url: BASE_URL + '/api/v2/assets', type: 'GET', dataType: 'json',
                    data: $.extend({}, params, { page: page, per_page: 500, columns: 'name' })
                }).done(function (json) {
                    var data = (json && json.data) || {};
                    (data.assets || []).forEach(function (a) { ids.push(Number(a.id)); });
                    if ((data.assets || []).length && ids.length < (data.total || 0) && ids.length <= NEW_GROUP_MAX_IDS) {
                        page++;
                        next();
                    } else {
                        done(ids.slice(0, NEW_GROUP_MAX_IDS));
                    }
                }).fail(fail);
            };
            next();
        },

        /* ---------------- asset record modal (view / edit / add) ---------------- */
        // js/simplerisk/pages/asset-record-modal.js owns the modal, its URL
        // params (asset/mode) and history; this page supplies the list
        // refresh, focus return and the follow-up dialogs of its ⋯ menu.
        bindRecordModal: function () {
            var self = this;
            if (!window.AssetRecordModal) { return; }
            AssetRecordModal.init({
                canVerify: !!caps.can_verify,
                changed: function () { self.refresh(false); },
                rememberOpener: function () { self.rememberOpener(); },
                restoreFocus: function () { self.restoreFocus(); },
                addToGroup: function (id) { self.openGroupModal({ ids: [Number(id)], count: 1 }); },
                deleteAsset: function (id, name) { self.openDeleteModal({ ids: [Number(id)], count: 1, name: name }); },
                valuation: function (id) {
                    var v = null;
                    if (id != null && String(id) !== '' && String(id) !== '0') {
                        (lookups.valuations || []).forEach(function (x) { if (String(x.id) === String(id)) { v = x; } });
                    }
                    return v;
                }
            });
        },
        openAdd: function () {
            // Anyone with asset access may add (spec D8).
            if (window.AssetRecordModal) { AssetRecordModal.open({ id: 'new', history: 'push' }); }
        },
        openRecord: function (id, mode) {
            if (mode === 'edit' && !caps.can_edit) { return; }
            if (window.AssetRecordModal) { AssetRecordModal.open({ id: id, mode: mode, history: 'push' }); }
        },

        /* ---------------- hooks for later tasks ---------------- */
        // Replaced by manage-asset-discovery.js (loaded after this file).
        openDiscovery: function () {},
        // The Asset groups tab calls this after creating/renaming/deleting a
        // group: refreshes the group filter, the Add to group picker and the
        // tab count. A filter on a group that no longer exists is dropped.
        setGroups: function (list) {
            lookups.groups = list || [];
            this.maps.groups = byId(lookups.groups);
            var $filter = $('#manage-assets-group-filter');
            var self = this;
            var current = this.state.filters.group.filter(function (id) { return self.maps.groups[String(id)] != null; });
            var dropped = current.length !== this.state.filters.group.length;
            this.state.filters.group = current;
            $filter.empty();
            lookups.groups.forEach(function (g) {
                $('<option>', { value: g.id, text: g.name, selected: current.indexOf(String(g.id)) !== -1 }).appendTo($filter);
            });
            if ($filter.data('srSelect')) { srSelectRender($filter); }
            this.fillGroupSelect();
            $('#manage-assets-tab-count-groups').text(lookups.groups.length);
            if (dropped) { this.syncFilterCount(); this.selectionChanged(); }
        },
        // "View all in the Assets tab": the Assets tab filtered to one group
        // (every other filter, the search and the segment cleared, so the list
        // is exactly the group's members the viewer can see).
        showGroup: function (groupId, status) {
            var id = String(groupId);
            this.state.filters = emptyFilters();
            this.state.filters.group = [id];
            $('#manage-assets-quickfilters select[data-filter]').each(function () {
                $(this).val($(this).data('filter') === 'group' ? [id] : []);
                if (typeof srSelectRender === 'function' && $(this).data('srSelect')) { srSelectRender($(this)); }
            });
            this.state.q = '';
            $('#manage-assets-search').val('');
            // Optional status ('verified' | 'unverified', from a member pill).
            status = status === 'verified' || status === 'unverified' ? status : 'all';
            this.state.status = status;
            $('#manage-assets-status-filter .sr-status-chip').each(function () {
                var on = String($(this).data('status')) === status;
                $(this).toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
            });
            // Open the filter row so the applied group filter is visible.
            $('#manage-assets-quickfilters').removeClass('d-none').addClass('is-open');
            $('#manage-assets-filters-toggle').attr('aria-expanded', 'true');
            this.syncFilterCount();
            this.setTab('assets');
            this.selectionChanged();
            $('#manage-assets-tab-assets').trigger('focus');
        },

        // Shared with manage-asset-groups.js (loaded after this file) so the
        // two tabs use one copy of each helper.
        // Pure URL <-> view-state functions (unit-testable without a DOM).
        url: { parse: parseViewState, serialize: serializeViewState, defaults: URL_DEFAULTS },
        syncGroupsSearch: function (q) { this.state.gq = String(q || ''); this.syncUrl(); },
        syncGroupsFilters: function (filters) {
            var f = emptyGroupFilters();
            GROUP_FILTER_KEYS.forEach(function (k) { f[k] = ((filters || {})[k] || []).map(String); });
            this.state.gfilters = f;
            this.syncUrl();
        },
        util: {
            esc: esc, fmt: fmt, csrfHeaders: csrfHeaders, debounce: debounce,
            reissuedByCsrfRetry: reissuedByCsrfRetry, isAbort: isAbort,
            reportError: reportError, dash: dash, byId: byId, valuationCell: valuationCell,
            moveableColumns: moveableColumns, dataColOrder: dataColOrder, applyDataColOrder: applyDataColOrder,
            keepCellsPaintedOnReorder: keepCellsPaintedOnReorder,
            syncNativeVisibility: syncNativeVisibility, mergedOrder: mergedOrder, defaultLayout: defaultLayout,
            levelChip: levelChip, scoreChip: scoreChip, scoringChip: scoringChip,
            filterChip: filterChip, chipFilterable: chipFilterable, chipLang: chipLang,
            addToFilterSelect: addToFilterSelect
        }
    };

    window.ManageAssets = ManageAssets;

    $(function () {
        if ($('#manage-assets-card').length) { ManageAssets.init(); }
    });
})(window, jQuery);
