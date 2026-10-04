/**
 * Review Risk grid (design spec: docs/superpowers/specs/2026-09-09-review-
 * risk-page-design.md; plan: .superpowers/sdd/2026-09-09-review-risk-page/,
 * Task 10). Bootstraps a DataTables `serverSide` grid against
 * POST /api/v2/risk_management/review_risk (getReviewRiskDatatableResponse(),
 * includes/api.php -- Task 5) and renders each row: the Needs badges
 * (mitigation/review), the risk-level severity pill, the Risk Score pill
 * (hidden by default -- toggled visible via the Columns picker, Task 11),
 * and the due-date state pill.
 *
 * SCOPING (load-bearing): `table` / `currentActionType` and every other
 * piece of shared state this page needs are declared as flat, file-top-level
 * `var`s -- NEVER inside a wrapping IIFE/closure. Tasks 11-19 append more
 * top-level code to this SAME file that reads and writes `table` /
 * `selectedIds` / `rowCache` / `filterOptionsCache` directly; hiding them in
 * a closure would throw `ReferenceError` on every one of those later
 * references. NOTE: governance-exceptions.js -- named in this task's brief
 * as "the real pattern to match" -- turns out, on inspection, to be a single
 * page-wide IIFE (`var ExceptionsGrid = (function ($) { ... })(jQuery);`)
 * with fully private internal state; it does NOT itself use flat top-level
 * vars. That shape doesn't fit a file multiple future tasks append to, so
 * this file follows the brief's explicit scoping instruction (flat vars)
 * rather than reconstructing governance-exceptions.js's actual structure.
 */

var table;
var currentActionType = 'all';
// 'mine' | 'all' -- the My Action Items / All Items toggle (display.php's
// #review-risk-scope-filter chips), replacing a checkbox whose unchecked
// state named nothing. 'mine' matches the checkbox's previous default-
// checked state.
var currentScope = 'mine';
// 'all' | 'open' | 'closed' -- the All/Open/Closed status-scope toggle
// (display.php's #review-risk-status-scope-filter chips). A SEPARATE,
// additional control from currentActionType (the All/Mitigation/Review
// chips) and currentScope (My Action Items/All Items) above -- all three
// coexist and are sent as independent params. 'open' matches the page's
// historical default (today's exact row set, zero regression).
var currentStatusScope = 'open';
// 'all' | 'unreviewed' | 'past_due' | 'due_soon' -- due-status filter state.
// NOT paired with a visible toolbar chip-group (see setDueStatus()'s own
// comment for why one was tried and removed) -- still a real, independent
// param alongside currentActionType/currentScope/currentStatusScope above,
// set only via a KPI tile's drill-through link or a hand-edited/shared URL.
// Values mirror classify_review_urgency()'s own bucket vocabulary
// (includes/functions.php). 'all' matches the page's historical default
// (zero regression); exists so a KPI tile's drill-through link
// (get_ui_widget_review_risk_insights(), api/v2/includes/api.php) can land
// on exactly the row set its own count tallied.
var currentDueStatus = 'all';
// Populated once by loadFilterOptions() from GET
// /api/v2/risk_management/review_risk/filter_options and read directly by
// the Reassign Owner/Mitigation Owner and Change Status bulk-action modal
// openers (filterOptionsCache.all_users/.statuses) -- kept as its own
// top-level var (not folded into a closure) per this file's scoping note
// above.
var filterOptionsCache = null;
// Request-sequencing token for loadFilterOptions() (below) -- guards
// against an out-of-order response silently overwriting fresher dropdown
// state. See that function's own comment for why this is needed.
var filterOptionsRequestSeq = 0;
// ColReorder follow-up: the user's saved column order (array of data-col
// keys), read once by loadFilterOptions()'s first-load block above and
// applied by applySavedColumnOrder() once `table` exists -- kept as its
// own var (not folded into filterOptionsCache) because it's read from two
// different call sites that may run in either order (loadFilterOptions()'s
// async response vs. the DataTable's synchronous construction).
var savedColumnOrder = null;
// Set true only for the duration of applySavedColumnOrder()'s own
// table.colReorder.order(desired, false) call below, which -- like a real
// user drag -- fires DataTables' 'column-reorder'/'columns-reordered'
// events internally (colReorder's setOrder()/move()/finalise(), verified
// against the vendored source). Without this guard, restoring a saved
// order on page load would immediately re-trigger the save-on-reorder
// handler bound in init() and POST the just-loaded order straight back to
// the server -- harmless data-wise (same order round-trips) but a wasted
// request on every page load. jQuery's trigger()/triggerHandler() is
// synchronous, so setting/clearing this flag around the one call is
// sufficient -- no risk of a real drag landing inside the window.
var restoringSavedColumnOrder = false;
// Task 8 (Customization Extra dynamic columns) follow-up: the preserved
// Show-N-entries control (detached from the static markup at the very top
// of init(), before DataTables regenerates '.sr-table-foot' around the
// table) -- kept as its own top-level var, same reasoning as
// savedColumnOrder above, because it's captured in init() but consumed by
// buildReviewRiskDataTable() (below), a different function scope, once
// loadFilterOptions()'s first response has resolved COLUMN_GROUPS/
// TOGGLE_COLUMNS to their final (custom-field-inclusive) shape. See
// buildReviewRiskDataTable()'s own comment for why the DataTable's
// construction itself had to move there.
var $lengthWrap;
// Guards the Task 13 columns-picker state application (loadFilterOptions()
// below) to the FIRST successful filter-options fetch only -- per-option-
// counts follow-up made loadFilterOptions() re-run on every filter/scope/
// action-type/search change, but the saved columns-picker state doesn't
// change with the caller's filter state.
var filterOptionsInitialized = false;

// Finding 1 (final whole-branch review): a SEPARATE flag from
// filterOptionsInitialized above, owned by buildReviewRiskDataTable() itself.
// The two are deliberately not the same boolean: filterOptionsInitialized is
// set inside loadFilterOptions()'s `.done()` only, so after a FAILED first
// fetch (which now still builds the grid -- see that function's `.fail()`
// handler) it is still false, and a LATER successful fetch would otherwise
// re-enter the first-load block and call buildReviewRiskDataTable() a second
// time against an already-initialised `#review_risk_table` -- which DataTables
// rejects ("Cannot reinitialise DataTable"). Guarding construction on its own
// flag makes the build happen exactly once regardless of which path (success,
// or failure-then-later-success) gets there first.
var tableBuilt = false;

// URL-params follow-up: the page's OWN initial URL query string, parsed
// once by parseUrlFilterParams() at the very top of init() (before the
// DataTable/loadFilterOptions() calls that need it already applied) and
// stashed here so loadFilterOptions()'s own async `.done()` handler --
// a different function scope -- can reach it too, to seed the four
// secondary-filter <select>s once their <option>s exist (see that
// function's own comment for why those four can't be seeded synchronously
// the way the chips/search box are). Null until init() runs; {} (no keys)
// once parsed if the URL carried no recognized filter params at all.
var initialUrlFilters = null;

// Insights-band follow-up: true from the moment init() finds the page's
// initial URL named ANY of status_scope/action_type/my_action_items (even a
// value that equals that dimension's own default -- see the My Action Items
// tile's own drill-through, which carries my_action_items=1, already the
// default) until the viewer's first real chip interaction. See
// syncFiltersCount()'s own comment for how this is used.
var initialUrlHadInsightsFilter = false;

// Bulk-select state (Task 14): { <risks.id>: true, ... } for every row
// currently checked -- NOT limited to the current page (see "Select all N"
// below). Keyed by row.id (the raw `risks.id`, same value
// renderRowCheckbox() below stamps into data-id) so Tasks 15-19's
// bulk-action POST bodies can read Object.keys(selectedIds) directly.
// SURVIVES a plain page/sort/length redraw (renderRowCheckbox() paints each
// row's checkbox from this map) -- it's cleared only by an actual
// filter/search change (resetSelectionForFilterChange(), below) or the
// bulk bar's own Clear button, both of which can invalidate which ids are
// even still valid to select.
var selectedIds = {};

// "Select all N" (cross-page selection): the grid's own recordsFiltered from
// the most recent /risk_management/review_risk response (getReviewRiskDatatableResponse(),
// includes/api.php) -- captured in the DataTables `ajax.dataSrc` hook below.
// serverSide means the client never has the full matching set in the DOM the
// way a client-side DataTables page does, so syncBulkBar() reads this number
// (rather than counting rendered rows) to decide whether more rows match the
// current filter than are currently selected.
var lastRecordsFiltered = 0;

// True once "Select all N" has resolved every id matching the current
// filter into selectedIds -- suppresses re-showing the banner (see
// syncBulkBar()) until the underlying filter/search actually changes.
// Deliberately NOT flipped back to false by a later manual uncheck: unlike
// Define Control Frameworks, every bulk action on this page already just
// sends Object.keys(selectedIds) as a plain id list regardless of how that
// list was built, so there is no second "wire format" for a partial
// deselection to fall back to -- leaving the flag true just means the
// banner stays hidden until a real filter change, which is the wanted
// behavior (don't re-nag to select everything again after a deliberate
// exclusion).
var selectAllFiltered = false;

// Shared by every control that changes WHICH rows can match (chips,
// secondary filters, search box, Clear Filters) -- a selection made under
// the OLD filter can name rows the new one wouldn't return at all, so it
// must not survive. Pagination/sort/page-size do NOT call this: they
// re-order or re-page the SAME matching set, so a selection made under it
// is still valid and now (see the table.on('draw', ...) handler below)
// survives those redraws instead of being silently dropped.
function resetSelectionForFilterChange() {
    selectedIds = {};
    selectAllFiltered = false;
    syncBulkBar();
}

// Full-row cache (Task 17): { <risks.id>: <row object from the datatable
// response> }, keyed the same way as selectedIds. Populated by the
// DataTables `createdRow` callback in init() below on every row DataTables
// creates (initial load, sort, filter, page, reload), so a bulk-action modal
// can inspect a selected row's other fields (e.g. `needs_mitigation`) without
// a second round-trip. Deliberately NOT reset on redraw the way selectedIds
// is -- a stale entry for an id that's no longer on screen is harmless (it's
// only ever read for an id that's also a key in selectedIds, and selectedIds
// IS reset every draw), and keeping old entries around means a page/sort
// change doesn't have to re-fetch data for rows that just scrolled out of
// view.
var rowCache = {};

// ---- Local helpers (page-specific JS files each define their own copies of
// these rather than relying on a true shared global -- see csrfHeaders()/
// formatDueDate()/esc() in compliance-define-tests.js, governance-frameworks.js,
// etc. `L()` is the one genuine global here: header.php always emits
// `window.L` unconditionally, so no local re-definition is needed for it.
// escapeHtml() (js/simplerisk/common.js) is NOT assumed available -- this
// page's render_header_and_sidebar() call doesn't request 'CUSTOM:common.js',
// so esc() below is self-contained (DOM textContent round-trip, same
// technique ai-capabilities-catalog.js and sr-audit-trail.js use) rather than
// wrapping a global that may not be loaded. ----

function esc(value) {
    var div = document.createElement('div');
    div.textContent = value === undefined || value === null ? '' : String(value);
    return div.innerHTML;
}

// Attribute-context escaping. esc() is a textContent->innerHTML round-trip,
// and browsers' HTML serializers only escape &/</> that way -- NOT " or '.
// That's safe for text-node contexts (between tags) but NOT for attribute
// values, where an unescaped " or ' breaks out of the quote and lets the
// rest of the string be parsed as markup/attributes/event handlers. Same
// split ai-capabilities-catalog.js uses (esc() for text nodes, a separate
// escAttr() for attribute contexts) -- kept as two functions rather than
// changing esc() itself so every existing bare-esc() text-node call site
// here keeps behaving exactly as before.
function escAttr(value) {
    return esc(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function csrfHeaders() {
    // Not needed by this file's own DataTables ajax call (a form-urlencoded
    // POST, auto-handled by csrf-magic.js's global XMLHttpRequest override --
    // see retryCSRF() below), but predefined here for the JSON-body POSTs
    // later tasks (bulk actions, Task 14+) add to this same file, matching
    // the convention csrfHeaders() callers elsewhere use.
    return { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' };
}

// Column-parity fix (post-review): accepts both a date-only 'YYYY-MM-DD'
// string (planning_date, next_review_date -- real `date` columns) AND a
// full 'YYYY-MM-DD HH:mm:ss' datetime string (submission_date, closure_date,
// mitigation_date, review_date/mgmt_review_date -- all `timestamp`/
// `datetime` columns, sent by the API as-is). moment()'s array-of-formats
// form tries each in order and keeps the first strict match; a single
// bare-string strict format (the original code here) silently failed on
// every datetime value and formatDueDate() returned '' for it, rendering
// those cells permanently blank. This also happens to fix the same latent
// gap in renderDueState() below's own formatDueDate() call, whose
// review_due_date can fall back to the (datetime) submission_date.
function formatDueDate(dateString) {
    var m = dateString ? moment(dateString, ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD'], true) : null;
    return (m && m.isValid()) ? m.format(default_date_format) : '';
}

// Generic "how urgent is this date" classification, independent of the
// server's review_bucket (which only classifies REVIEW urgency -- a
// mitigation-only row's due_date comes from its planning_date instead, and
// still deserves the same overdue/due-soon/on-track treatment). Mirrors
// classify_review_urgency()'s own due-soon window (includes/functions.php)
// for visual consistency, but this is a display-only classification; the
// server's classify_review_urgency() remains the authority for which rows
// are actionable at all.
function classifyDueDate(dueDateStr) {
    if (!dueDateStr) {
        return null;
    }
    // Same two-format array as formatDueDate() below, for the same reason --
    // currently unreachable in practice (a datetime-shaped due_date only
    // occurs on the 'unreviewed' bucket, which renderOneDueChip()'s
    // isUnreviewed branch short-circuits before this ever runs), but a
    // strict date-only parse failing silently returns null here -- which
    // renderDueState() then treats as "on track" (the green sr-date-chip--ok
    // default), a wrong-signal bug rather than a blank one if that early
    // return is ever narrowed. Kept in sync defensively.
    var due = moment(dueDateStr, ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD'], true);
    if (!due.isValid()) {
        return null;
    }
    var days = due.diff(moment().startOf('day'), 'days');
    if (days < 0) {
        return 'overdue';
    }
    if (days <= 30) {
        return 'due_soon';
    }
    return 'on_track';
}

// Populates the ALL/Mitigation/Review toolbar chips' count badges, plus the
// My Action Items/All Items scope-toggle chips' count badges, from the
// server's per-draw tally (getReviewRiskDatatableResponse()'s `counts`,
// includes/api.php) -- independent of which chip is currently active in
// EITHER control, so all five numbers show live at once (a row needing both
// actions counts toward both 'mitigation' and 'review', so those two can sum
// to MORE than 'all' -- matches the approved design mockup's own example,
// 5/3/3 not 5/3/2; similarly 'mine' and 'all_items' are independent of the
// CURRENTLY SELECTED scope, so they stay stable across a Mine<->All Items
// click and only move when some OTHER filter dimension changes). `counts`
// can be undefined on a request that errored before reaching the counts
// tally (retryCSRF's retry path re-triggers this same ajax call, so a
// transient failure just leaves the prior counts in place rather than
// blanking them).
function updateChipCounts(counts) {
    if (!counts) {
        return;
    }
    $('#review-risk-count-all').text(counts.all);
    $('#review-risk-count-mitigation').text(counts.mitigation);
    $('#review-risk-count-review').text(counts.review);
    $('#review-risk-count-mine').text(counts.mine);
    $('#review-risk-count-all-items').text(counts.all_items);
    // All/Open/Closed status-scope chips -- same independent-of-current-
    // selection principle as the counts above (status_all/status_open/
    // status_closed tallied in getReviewRiskDatatableResponse(), api.php).
    $('#review-risk-count-status-all').text(counts.status_all);
    $('#review-risk-count-status-open').text(counts.status_open);
    $('#review-risk-count-status-closed').text(counts.status_closed);

    // Narrow-width <select> stand-ins (includes/display.php's .sr-chip-select
    // <option>s) mirror the SAME counts into their option labels -- "All
    // (157)" -- rather than a second server round-trip or a duplicated lang
    // lookup: each <option> already carries its own bare translated text in
    // `data-label` (set once, server-side), so this just appends the count.
    setSelectOptionCount('#review-risk-status-filter-select', 'all', counts.all);
    setSelectOptionCount('#review-risk-status-filter-select', 'mitigation', counts.mitigation);
    setSelectOptionCount('#review-risk-status-filter-select', 'review', counts.review);
    setSelectOptionCount('#review-risk-scope-filter-select', 'mine', counts.mine);
    setSelectOptionCount('#review-risk-scope-filter-select', 'all', counts.all_items);
    setSelectOptionCount('#review-risk-status-scope-filter-select', 'all', counts.status_all);
    setSelectOptionCount('#review-risk-status-scope-filter-select', 'open', counts.status_open);
    setSelectOptionCount('#review-risk-status-scope-filter-select', 'closed', counts.status_closed);

    // due_status_unreviewed/due_status_past_due/due_status_due_soon (still
    // sent by getReviewRiskDatatableResponse()) have no chip-group badge to
    // update anymore -- see setDueStatus()'s own comment for why the toolbar
    // control was removed. The counts themselves are unused client-side now.
}

// One <option>'s label rebuild, shared by every call above -- avoids
// repeating the same `data-label + ' (' + count + ')'` string-build six
// times. No-ops if the option isn't found (defensive; every value passed
// above always has a matching <option> in the markup).
function setSelectOptionCount(selectSelector, value, count) {
    var $option = $(selectSelector + ' option[value="' + value + '"]');
    if (!$option.length) {
        return;
    }
    $option.text($option.data('label') + ' (' + count + ')');
}

// Shared "set this chip-group's active value" functions -- the SINGLE
// implementation of "what happens when the action-type/scope/status-scope
// filter changes," called by both the wide-width chip click handler and
// the narrow-width (<1320px, _tables.scss) <select> change handler further
// below, so the two input widths never diverge into two separate
// filtering code paths. Each function updates the module-level current*
// var, both visual representations (the chip row's .active class AND the
// <select>'s value -- whichever one did NOT just fire the event that got
// here), and -- unless told not to -- reloads the grid, re-fetches the
// facet counts, and writes the URL. `reload` defaults to true; init()'s
// URL-param restore (which runs before `table` exists) is the one caller
// that passes `false`.
//
// Race-condition fix (post-review, Task 8): a REAL (reload !== false) call
// can ALSO land before `table` exists now -- not just init()'s own
// reload:false seeding calls, but a genuine user click/keypress on the chip
// row or its narrow-width <select> equivalent, during the window between
// page-interactive and loadFilterOptions()'s first response resolving
// (construction moved into that response's own `.done()`,
// buildReviewRiskDataTable()). Each `table.ajax.reload()` below is guarded
// `if (table)` for this reason -- the current* state var above is already
// updated by this point regardless of the guard, and
// buildReviewRiskDataTable()'s own first ajax call (fired automatically on
// construction, a moment later) reads that SAME state fresh via
// buildFilterQueryParams(), so a click landing in the window still reaches
// the very first server request correctly; skipping the (impossible)
// reload here just avoids a redundant double-fetch a real reload would
// otherwise cause a few hundred ms later anyway.
function setActionType(value, reload) {
    currentActionType = value;
    $('#review-risk-status-filter .sr-status-chip').removeClass('active');
    $('#review-risk-status-filter .sr-status-chip[data-action-type="' + value + '"]').addClass('active');
    $('#review-risk-status-filter-select').val(value);
    // Insights-band follow-up: keeps the Filters badge/Clear-Filters button
    // in sync with this chip-group too -- called unconditionally (not just
    // when reload !== false) so the init()-time URL-seeded state (which
    // passes reload:false, before `table` exists) still shows the correct
    // badge on first paint, e.g. after landing from a KPI tile's link.
    if (typeof syncFiltersCount === 'function') {
        syncFiltersCount();
    }
    if (reload !== false) {
        // A real (non-seeding) chip interaction -- from here on,
        // syncFiltersCount() goes back to judging "active" purely by
        // current-value-vs-default; "did the initial URL carry a param"
        // stops mattering once the viewer has actually touched a chip.
        initialUrlHadInsightsFilter = false;
        resetSelectionForFilterChange();
        if (table) {
            table.ajax.reload();
        }
        loadFilterOptions();
        syncUrlParams();
    }
}

function setScope(value, reload) {
    currentScope = value;
    $('#review-risk-scope-filter .sr-status-chip').removeClass('active');
    $('#review-risk-scope-filter .sr-status-chip[data-scope="' + value + '"]').addClass('active');
    $('#review-risk-scope-filter-select').val(value);
    if (typeof syncFiltersCount === 'function') {
        syncFiltersCount();
    }
    if (reload !== false) {
        // A real (non-seeding) chip interaction -- from here on,
        // syncFiltersCount() goes back to judging "active" purely by
        // current-value-vs-default; "did the initial URL carry a param"
        // stops mattering once the viewer has actually touched a chip.
        initialUrlHadInsightsFilter = false;
        resetSelectionForFilterChange();
        if (table) {
            table.ajax.reload();
        }
        loadFilterOptions();
        syncUrlParams();
    }
}

function setStatusScope(value, reload) {
    currentStatusScope = value;
    $('#review-risk-status-scope-filter .sr-status-chip').removeClass('active');
    $('#review-risk-status-scope-filter .sr-status-chip[data-status-scope="' + value + '"]').addClass('active');
    $('#review-risk-status-scope-filter-select').val(value);
    if (typeof syncFiltersCount === 'function') {
        syncFiltersCount();
    }
    if (reload !== false) {
        // A real (non-seeding) chip interaction -- from here on,
        // syncFiltersCount() goes back to judging "active" purely by
        // current-value-vs-default; "did the initial URL carry a param"
        // stops mattering once the viewer has actually touched a chip.
        initialUrlHadInsightsFilter = false;
        resetSelectionForFilterChange();
        if (table) {
            table.ajax.reload();
        }
        loadFilterOptions();
        syncUrlParams();
    }
}

// Due-status state setter -- NOT paired with a visible toolbar chip-group
// (one existed briefly and was removed: the Insights band's own Needs
// Review/Past Due/Coming Soon KPI tiles already give one-click access to
// each bucket, so a duplicate toolbar control added width/clutter with no
// real gain -- see includes/display.php's removal comment). Still a real
// setter, not a no-op: a KPI tile's drill-through link still needs
// currentDueStatus updated, the grid reloaded, and Clear Filters still
// needs a way to reset it back to 'all' -- this function is that shared
// path for both, same reasoning as the other three setters, minus the DOM
// sync they still do for their own (still-visible) chip groups.
function setDueStatus(value, reload) {
    currentDueStatus = value;
    if (typeof syncFiltersCount === 'function') {
        syncFiltersCount();
    }
    if (reload !== false) {
        initialUrlHadInsightsFilter = false;
        resetSelectionForFilterChange();
        if (table) {
            table.ajax.reload();
        }
        loadFilterOptions();
        syncUrlParams();
    }
}

// Builds a `columns:` config entry for one column-parity toggleable field
// (see COLUMN_GROUPS below for the full list) -- same createdCell shape the
// pre-existing risk_score/responsible/team column defs use (stamp the
// shared data-col key + the current columnVisible d-none state onto every
// <td> DataTables creates), factored into a helper because there are ~40 of
// these rather than 3.
//
// `dateField`: runs the raw 'YYYY-MM-DD' value through this file's own
// formatDueDate() instead of inserting it as plain data.
// `boolField`: renders a Yes/No `.sr-state-pill` (the same state-pill
// convention self-assessment.js's boolean-ish answer pills and this app's
// other status indicators use -- .sr-state-success/.sr-state-neutral,
// scss/modules/_tables.scss) instead of the raw true/false value.
// `longText`: wraps the value in a `<span class="sr-cell-truncate">` (new
// rule, _tables.scss -- CSS-only single-line ellipsis, the same technique
// .sr-group-name already uses elsewhere in this file's design system) for
// the handful of long free-text fields (assessment/notes/current solution/
// security recommendations/requirements). Post-review fix: the class MUST
// go on an inner <span>, not the <td> itself -- `.sr-cell-truncate` sets
// `display:block`, and setting that directly on a <td> pulls it out of
// table layout (the browser wraps it in an anonymous table-cell),
// misaligning the column from its <thead> header. `data` is already
// HTML-escaped server-side (api.php), so this is plain string concatenation
// around it, not a second escape pass. Deliberately NOT paired with a
// JS-built `title` tooltip: re-reading that pre-escaped string into an
// attribute here would either show literal entities (attributes aren't
// HTML-decoded the way element content is) or need a second, easy-to-get-
// wrong escape pass -- not worth it for a table cell a viewer can already
// open the row to read in full.
//
// Every other field (plain text, already escaped server-side, or a number)
// uses DataTables' own default cell insertion -- no `render:` at all --
// the same as the pre-existing risk_score/responsible/team defs and
// 'subject' above: this file's established pattern is escape-once at the
// API, no client-side re-escape.
//
// `notOrderable`: click-to-sort follow-up -- every column now defaults to
// orderable:true (matches most of these having a real get_risks() sort case,
// includes/functions.php) since that's the common case; pass this opt-out for
// the handful that genuinely can't sort -- the 8 encrypted free-text/name
// fields (sorting ciphertext produces no meaningful order) and the 2 columns
// get_risks() has no working SQL case for (management_review, next_review_date
// -- see that switch's comment). See each call site below for the specific
// reason.
//
// `numericHeader`: icon-position fix, same root cause as the 'id' column def
// below -- DataTables auto-detects a column as numeric from its underlying
// `data` value (not the rendered HTML), even when a `render:` function wraps
// that value in markup, and stamps `dt-type-numeric` onto the <th>. The
// vendored dataTables.bootstrap5(.min).css reverses that header's flex order
// (`flex-direction:row-reverse`), which visually swaps the title and the
// sort-icon wrapper and puts the icon on the LEFT instead of the right every
// other sortable header here uses. Verified live (not just read off the
// stylesheet) that 'dt-head-left' -- a real DataTables class, appearing later
// in that same stylesheet at equal selector specificity, and scoped to
// `thead`/`tfoot` selectors only so it can't affect this column's <td>
// body-cell alignment -- wins the cascade and restores normal order.
// `residual_risk`/`days_open` (both plain numbers via toggleColumnDef's
// default cell insertion) and the hand-written `risk_score` column
// (renderRiskScorePill's markup wraps a plain numeric `data: 'calculated_risk'`)
// all hit this; every other toggleable column's `data` is a string, so this
// opt-in stays rare.
function toggleColumnDef(key, opts) {
    opts = opts || {};
    var def = {
        data: key,
        name: key,
        orderable: !opts.notOrderable,
        createdCell: function (td) {
            $(td).attr('data-col', key).toggleClass('d-none', !columnVisible[key]);
        },
    };
    if (opts.numericHeader) {
        def.className = 'dt-head-left';
    }
    // Minor Fix A (final whole-branch review) -- opt-in, set only by
    // customFieldColumnDefsForGroup() below. A custom-field column's `<th>`
    // set is fixed at PAGE RENDER time (display.php's server-rendered thead)
    // while its row DATA arrives from a separate, later POST, so the two can
    // disagree if the site's active-field set changes in between (an admin
    // deactivates a field mid-session). A `data` property missing from the
    // row object is DataTables' "Requested unknown parameter" condition;
    // declaring a defaultContent is the documented way to say "an absent
    // value is legal here, render nothing" so that path is never entered at
    // all. Note this is hygiene, not a user-visible bug fix on THIS page:
    // common.js sets `$.fn.dataTable.ext.errMode = 'none'` globally (and
    // review_risk.php loads it), so the blocking alert that default errMode
    // would raise is already suppressed app-wide -- verified live against
    // simplerisk-dev by stripping custom_field_266 from the row payload, both
    // with and without this option: the grid rendered 25 rows either way, no
    // alert, no console error. Kept because a page that stops loading
    // common.js, or a future errMode change, would otherwise reintroduce it.
    // Deliberately NOT set on the static basic-field columns: those are
    // always present in getReviewRiskDatatableResponse()'s row shape, and a
    // silent empty cell there would mask a real server-side regression.
    if (opts.defaultContent) {
        def.defaultContent = '';
    }
    if (opts.dateField) {
        // Empty/unset dates (Planned Mitigation Date, Review Date, etc. --
        // formatDueDate() returns '' for a null or 0000-00-00 value) render
        // as the same em-dash placeholder the Due Date column already uses
        // (renderOneDueChip()'s own '<span class="sr-cell-dash">' fallback
        // below) rather than a blank cell, so an empty date reads as
        // "confirmed not set" rather than looking like missing data.
        def.render = function (data) {
            var formatted = formatDueDate(data);
            return formatted ? formatted : '<span class="sr-cell-dash">&mdash;</span>';
        };
    } else if (opts.boolField) {
        def.render = function (data) {
            return data
                ? '<span class="sr-state-pill sr-state-success">' + esc(L('Yes')) + '</span>'
                : '<span class="sr-state-pill sr-state-neutral">' + esc(L('No')) + '</span>';
        };
    } else if (opts.longText) {
        // Post-review fix: `.sr-cell-truncate` (max-width + ellipsis,
        // _tables.scss) must land on a <span> INSIDE the cell, not on the
        // <td> itself -- setting `display:block` directly on a <td> pulls it
        // out of table layout (the browser wraps it in an anonymous
        // table-cell), misaligning the column from its <thead> header. `data`
        // is already HTML-escaped server-side (api.php), so wrapping it in a
        // literal `<span>...</span>` here is plain string concatenation, not
        // a second escape pass.
        def.render = function (data) {
            return '<span class="sr-cell-truncate">' + (data || '') + '</span>';
        };
    } else if (opts.render) {
        // Escape hatch for a column whose render doesn't fit any of the
        // shapes above (e.g. the clickable-chip columns -- renderUserChip()/
        // renderChipListHtml()-based renderers -- needs the same treatment
        // 'owner'/'team' already get on their own hand-written column defs,
        // but these ARE among the ~40 toggleable columns this function
        // generates, unlike owner/team).
        def.render = opts.render;
    }
    return def;
}

// ---- Row rendering ----

// Clickable filter follow-up: each badge sets the Action Type chip-group's
// own toggle (setActionType('mitigation'/'review')) -- a quick way to jump
// from "All" straight to just this action type, matching what clicking
// Mitigation/Review in the toolbar chip-group itself already does (same
// function, same reload/URL-sync). `.sr-need-badge--clickable`, not the
// Team/Owner/Risk-Level chips' shared `.sr-filter-chip` -- Action Type is a
// single mutually-exclusive toggle, not a multi-select to add a value to,
// so it needs its own small click handler (init(), below), not the shared
// multi-select one.
function renderNeedsBadges(data, type, row) {
    var badges = '';
    if (row.needs_mitigation) {
        badges += '<span class="sr-need-badge sr-need-badge--clickable" data-action-type="mitigation" role="button" tabindex="0" title="' + escAttr(L('FilterBy')) + ' ' + esc(L('Mitigation')) + '"><i class="fa fa-shield-halved" aria-hidden="true"></i> ' + esc(L('Mitigation')) + '</span>';
    }
    if (row.needs_review) {
        badges += '<span class="sr-need-badge sr-need-badge--clickable" data-action-type="review" role="button" tabindex="0" title="' + escAttr(L('FilterBy')) + ' ' + esc(L('Review')) + '"><i class="fa fa-clipboard-check" aria-hidden="true"></i> ' + esc(L('Review')) + '</span>';
    }
    // Defensive only -- getReviewRiskDatatableResponse() already drops any
    // row where neither flag is set, so every row returned here has at
    // least one badge in practice.
    if (!badges) {
        return '<span class="sr-cell-dash">&mdash;</span>';
    }
    // .sr-chip-row is the gap between the two badges when a row carries
    // both -- see its own comment in _review-risk.scss for why the gap
    // lives on this wrapper rather than a margin on .sr-need-badge itself.
    return '<span class="sr-chip-row">' + badges + '</span>';
}

// Risk Level column: a solid "severity" pill (design-system.md §7,
// .sr-sev-pill -- NOT the brief's invented, unstyled ".sev" class). The fill
// color is meant to come from server-resolved risk_levels config, applied
// via the style PROPERTY by paintColorPills() below (never string-
// interpolated into the markup -- browser-validated, matches self-
// assessment.js's paintSevPills(), the established safe-color-application
// technique elsewhere in this codebase; there is no "escapeCssColorClient"
// helper anywhere in the bundle despite the brief citing one).
//
// getReviewRiskDatatableResponse() (includes/api.php) now returns
// risk_level_color -- get_risk_color_from_levels($risk['calculated_risk'],
// $risk_levels), the same call functions.php's own risk-list rendering uses
// -- resolved from the same $risk_levels the handler already fetches. The
// value is admin-configurable (admin/risk_configuration.php's <input
// type="color">, not guaranteed strictly format-validated at the API/DB
// layer), so it lands in an HTML attribute (data-color) via escAttr(), not
// esc() -- esc()'s textContent->innerHTML round-trip doesn't escape " or ',
// which would otherwise let a crafted color value break out of the
// attribute.
// Clickable filter follow-up: adds `role='button' tabindex='0'` +
// `.sr-filter-chip` (shared click/keydown-delegated behavior, wired once in
// init() -- see that handler's own comment) and a `data-filter-select`
// pointing at the Risk Level secondary filter (#review-risk-level-filter).
// `row.risk_level` is RAW here (get_risk_level_name()'s resolved display
// name, unescaped server-side -- unlike the user/team fields, this one
// already went through the client-side esc() call below before this
// change), so it's both the correct filter VALUE (matching
// #review-risk-level-filter's <option value>, riskLevelOption()) and safe
// to read directly rather than needing the DOM-decode trick
// renderTeamChips()/renderUserChip() use for their server-pre-escaped data.
function renderRiskLevelPill(data, type, row) {
    return '<span class="sr-sev-pill sr-sev-pill-sm sr-filter-chip" data-color="' + escAttr(row.risk_level_color || '') + '" data-filter-select="review-risk-level-filter" data-filter-value="' + escAttr(row.risk_level || '') + '" role="button" tabindex="0" title="' + escAttr(L('FilterBy')) + ' ' + esc(row.risk_level) + '">' + esc(row.risk_level) + '</span>';
}

// Risk Score column (hidden by default -- d-none in the static thead/tbody
// until the Columns picker, Task 11, exposes it). ".sr-score-pill" (renamed
// from the original bare ".score" in the final whole-branch review's Finding
// 6 -- unscoped, it collided with 4 unrelated .score tiles in the Incident
// Management Extra; see _review-risk.scss's matching comment) is the pill
// class Task 20's SCSS defines (base pill + ".sr-score-pill.on-light"); the
// on-light class itself is applied by paintColorPills() below, consistent
// with the severity pill.
function renderRiskScorePill(data, type, row) {
    return '<span class="sr-score-pill" data-color="' + escAttr(row.risk_level_color || '') + '">' + esc(row.calculated_risk) + '</span>';
}

// 'All users' merge (user request follow-up): every user-bearing role on a
// risk -- Owner, "Last Review By" (data-col 'reviewer'; the person who
// submitted the risk's CURRENT management review --
// getReviewRiskDatatableResponse()'s `mgmt_reviews l`/`l.reviewer` join, not
// a full review history), Owner's Manager, Submitted By, and Mitigation
// Owner -- now shares ONE clickable-chip renderer and ONE target filter
// (#review-risk-user-filter), replacing the former separate owner/reviewer
// chip functions and their own separate dropdowns. `data` is already
// HTML-escaped server-side (getReviewRiskDatatableResponse(), includes/
// api.php) -- plain string concatenation, no second escape pass; the click
// handler (init(), .sr-filter-chip) reads the chip's own browser-decoded
// .text() for the filter value, same as Team.
function renderUserChip(data) {
    if (!data) {
        return '<span class="sr-cell-dash">&mdash;</span>';
    }
    return '<span class="sr-chip-row"><span class="sr-chip sr-filter-chip" data-filter-select="review-risk-user-filter" role="button" tabindex="0" title="' + escAttr(L('FilterBy')) + ' ' + data + '">' + data + '</span></span>';
}

// Shared rendering for any column whose value is an ARRAY of individually
// HTML-escaped names, each a clickable chip that adds itself to a secondary
// filter <select> (targetSelectId) and reloads -- the SAME effect picking it
// from that dropdown has, not a second filtering mechanism, so it stays in
// sync with (and clearable via) the existing filter / Clear Filters
// affordance. Each entry is already escaped server-side, so this is plain
// string concatenation, same convention every other server-escaped
// free-text field in this file follows -- do NOT run `name` through
// esc()/escAttr() again. The click handler itself (init(), below) reads the
// clicked chip's own .text() rather than a data-* attribute -- the browser
// has already HTML-decoded that back to the plain name by the time it's a
// live DOM node, which is exactly the value format the target <select>'s
// <option value> uses (populateSelect()/nameOption()) -- no second
// escaped/raw representation to keep in sync.
function renderChipListHtml(data, type, targetSelectId) {
    if (type !== 'display') {
        return Array.isArray(data) ? data.join(', ') : (data || '');
    }
    if (!data || !data.length) {
        return '<span class="sr-cell-dash">&mdash;</span>';
    }
    // escAttr() wraps ONLY L('FilterBy') (raw, client-side translation text)
    // -- `name` is already escaped, so it's concatenated as-is into both the
    // title attribute and the chip's own text content.
    var filterByLabel = escAttr(L('FilterBy'));
    return '<span class="sr-chip-row">' + data.map(function (name) {
        return '<span class="sr-chip sr-filter-chip" data-filter-select="' + targetSelectId + '" role="button" tabindex="0" title="' + filterByLabel + ' ' + name + '">' + name + '</span>';
    }).join('') + '</span>';
}

// Team column: one chip per team instead of a comma-joined string.
// getReviewRiskDatatableResponse() (includes/api.php) sends `team` as an
// array of individually HTML-escaped names now (previously a single joined,
// UNESCAPED string -- a real stored-XSS gap for an admin-managed team name
// viewed by every riskmanagement user, fixed at the same time this chip
// rendering was added; see that endpoint's own comment). 'All teams' merge
// follow-up: also reused directly (same target filter, same shape) for the
// Mitigation Team column -- Team and Mitigation Team now filter the SAME
// #review-risk-team-filter dropdown.
function renderTeamChips(data, type) {
    return renderChipListHtml(data, type, 'review-risk-team-filter');
}

// Additional Stakeholders column: 'All users' merge follow-up -- same
// array-of-chips shape as renderTeamChips(), but each stakeholder chip
// targets the merged #review-risk-user-filter dropdown (the same one Owner/
// Owner's Manager/Submitted By/Mitigation Owner/Reviewed By chips target).
function renderStakeholderChips(data, type) {
    return renderChipListHtml(data, type, 'review-risk-user-filter');
}

// One .sr-date-chip (design-system.md §7 "date chip" --
// governance-exceptions.js's/governance-documents.js's own reviewChipHtml()
// reference implementation) for a single due date, tinted by
// classifyDueDate(). `isUnreviewed` is only ever true for the review chip
// (a mitigation-only date has no "never reviewed" analog) and renders the
// Unreviewed label instead of a date, matching the single-chip version's
// prior behavior for that case. Returns '' when there's nothing to show for
// this action type, so the caller can tell "no chip" apart from "a chip
// rendered."
function renderOneDueChip(dueDateStr, isUnreviewed) {
    if (isUnreviewed) {
        return '<span class="sr-date-chip sr-date-chip--overdue"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i><span>' + esc(L('Unreviewed')) + '</span></span>';
    }
    if (!dueDateStr) {
        return '';
    }
    var bucket = classifyDueDate(dueDateStr);
    var modifierClass = ' sr-date-chip--ok';
    var icon = 'fa-calendar-check';
    if (bucket === 'overdue') {
        modifierClass = ' sr-date-chip--overdue';
        icon = 'fa-triangle-exclamation';
    } else if (bucket === 'due_soon') {
        modifierClass = ' sr-date-chip--due-soon';
        icon = 'fa-calendar-days';
    }
    return '<span class="sr-date-chip' + modifierClass + '"><i class="fa ' + icon + '" aria-hidden="true"></i><span>' + esc(formatDueDate(dueDateStr)) + '</span></span>';
}

// Due Date column: independent chips for mitigation and review -- a row
// needing both shows both, same two-chip pattern as the Needs column
// (renderNeedsBadges() above), rather than one collapsed date that couldn't
// say which action it belonged to. Order matches the Needs column
// (mitigation first, review second) so the two columns read as paired.
function renderDueState(data, type, row) {
    var chips = renderOneDueChip(row.mitigation_due_date, false);
    chips += renderOneDueChip(row.review_due_date, row.needs_review && row.review_bucket === 'unreviewed');
    if (!chips) {
        return '<span class="sr-cell-dash">&mdash;</span>';
    }
    // Same shared wrapper renderNeedsBadges() above uses -- both are "1-2
    // small chips side by side in a table cell," the same layout need.
    return '<span class="sr-chip-row">' + chips + '</span>';
}

// Row actions (Task 12): View / Edit / Plan Mitigation / Perform Review --
// pure navigation into the matching management/view.php tab (design spec:
// "Inline row actions ... pure navigation, no new logic"; view.php itself
// is explicitly out of this page's scope -- "inline row actions link into
// those tabs as they exist today").
//
// Tab selection is NOT a `?type=details|mitigation|review` query param --
// view.php reads no such param (confirmed by direct grep; no `$_GET['type']`
// anywhere in management/view.php). The REAL mechanism, confirmed against
// simplerisk/header.php's 'tabs:logic' asset (which view.php requests --
// management/view.php's render_header_and_sidebar() call includes
// 'tabs:logic' in its script list) and management/partials/details.php's
// real tab markup (`<a id="tab_details" data-bs-target="#details" ...>`,
// `#tab_mitigation` -> `#mitigation`, `#tab_review` -> `#review`): on
// $(document).ready, 'tabs:logic' reads `location.hash` and calls
// `.tab('show')` on whichever `nav-tabs a[data-bs-target]` matches --  i.e.
// the deep-link is a URL HASH matching the tab-pane's real id, not a query
// param. View/Edit both omit a hash: view.php's Details tab (the default
// active tab) already IS the risk's form -- modify_risks gates which
// fields render editable inside it (includes/display.php:625), not which
// tab shows -- so both actions just open the risk. Plan Mitigation/Perform
// Review append '#mitigation'/'#review' so 'tabs:logic' activates that tab
// on load.
//
// row.id is the RAW `risks.id` (getReviewRiskDatatableResponse() casts
// $risk['id'] straight through) -- but view.php's own `id` GET param is the
// DISPLAY id, raw + 1000 (confirmed live: get_risk_by_id(993979) -- the raw
// id -- returns zero rows; get_risk_by_id(994979) returns the risk).
// includes/functions.php's convert_to_risk_id($id) is the established,
// hardcoded "+ 1000" conversion every other view.php link in the app builds
// through (includes/reporting.php, includes/functions.php's risk-table
// renderers, includes/api.php); mirrored here as plain arithmetic rather
// than adding a second id field to the API response for one fixed, stable
// offset.
function renderRowActions(data, type, row) {
    var viewUrl = BASE_URL + '/management/view.php?id=' + encodeURIComponent(row.id + 1000);
    var actions = rowActionLink(viewUrl, 'fa-eye', L('View'));
    if (row.can_edit) {
        // Land directly in the same edit-mode a real click on view.php's own
        // "Edit Details" button produces. view.php reads $_GET['action'] on
        // a plain page load (management/view.php:256) and
        // management/partials/details.php checks it identically to a real
        // click's $_POST['edit_details'] ($action == 'editdetail',
        // gated on the same modify_risks permission this row's own
        // can_edit flag already reflects) -- so no new server/JS logic is
        // needed here, just the same query param the AJAX-driven button
        // (js/simplerisk/pages/risk.js's editDetailsRequest()) already
        // passes to the identical include.
        actions += rowActionLink(viewUrl + '&action=editdetail', 'fa-edit', L('Edit'));
    }
    if (row.can_plan_mitigation) {
        // #mitigation selects the tab (header.php's 'tabs:logic' reads
        // location.hash on load, unchanged from before); action=editmitigation
        // lands that tab in edit mode, matching a real click on "Edit
        // Mitigation" (management/partials/details.php's
        // $action == 'editmitigation' check, gated on plan_mitigations --
        // same permission row.can_plan_mitigation already reflects).
        actions += rowActionLink(viewUrl + '&action=editmitigation#mitigation', 'fa-shield-halved', L('PlanYourMitigations'));
    }
    if (row.can_perform_review) {
        // Same pattern: #review selects the tab, action=editreview matches
        // a real click on "Perform a Review" from the Actions menu
        // (management/partials/details.php's $action == 'editreview' check,
        // gated on check_review_permission_by_risk_id() -- the same check
        // row.can_perform_review's value already reflects, Task 16's N+1
        // fix notwithstanding).
        actions += rowActionLink(viewUrl + '&action=editreview#review', 'fa-clipboard-check', L('PerformReview'));
    }
    var actionsLabel = L('Actions');
    return '<span class="sr-row-actions-wrap"><button type="button" class="sr-row-actions-toggle" aria-expanded="false" aria-haspopup="true" aria-label="' + escAttr(actionsLabel) + '" title="' + escAttr(actionsLabel) + '"><i class="fa fa-ellipsis" aria-hidden="true"></i></button><span class="sr-row-actions">' + actions + '</span></span>';
}

// One `.sr-row-action` navigational icon-link (same shipped component/markup
// shape governance-documents.js's Download row action uses), opened in a new
// tab (target="_blank") so acting on a row doesn't lose the reviewer's place
// in this grid's filtered/paginated/scrolled state -- rel="noopener
// noreferrer" is the standard safeguard for a target="_blank" link (the
// opened page can't reach back into window.opener). `href` may already
// carry a `#hash` (Plan Mitigation/Perform Review above) -- escAttr()
// escapes the whole assembled URL string as a single attribute value, which
// is safe either way (a `#`/`&` needs no escaping in an href attribute).
// `label` is used for both `title` and `aria-label`, matching every other
// row-action-menu page's convention (governance-exceptions.js,
// governance-documents.js).
function rowActionLink(href, iconClass, label) {
    return '<a href="' + escAttr(href) + '" class="sr-row-action" target="_blank" rel="noopener noreferrer" title="' + escAttr(label) + '" aria-label="' + escAttr(label) + '"><i class="fa ' + iconClass + '" aria-hidden="true"></i></a>';
}

function renderRowCheckbox(data, type, row) {
    // No change handler bound HERE -- .sr-row-check is regenerated by
    // DataTables on every draw (full tbody rebuild), so the actual handler
    // is a single $(document)-delegated binding wired once in init() (Task
    // 14, "Bulk-select / bulk bar" section below), matching this file's
    // existing pattern for the colpanel checkboxes (also redraw-vulnerable).
    //
    // "Select all N" follow-up: selectedIds now SURVIVES a redraw (see the
    // table.on('draw', ...) handler's own comment, below) instead of being
    // wiped every time -- so a row re-rendered after a page/sort change (or
    // after "Select all N" resolved ids from other pages) must come back
    // checked when it's still selected, not always blank.
    var checked = selectedIds[row.id] ? ' checked' : '';
    return '<input type="checkbox" class="form-check-input sr-row-check" data-id="' + escAttr(row.id) + '"' + checked + '>';
}

// Paints each severity/score pill's data-color onto the element via the
// style PROPERTY (the browser validates it -- an invalid value is a safe
// no-op, never a markup break-out) and flips `.on-light` when the fill's
// luminance is pale enough to need dark text -- identical technique to
// self-assessment.js's paintSevPills(). Falls back to .sr-sev-pill's own
// neutral CSS default (gray, white text) only if data-color is ever empty
// (e.g. a level with no configured color).
function paintColorPills() {
    $('#review_risk_table .sr-sev-pill[data-color], #review_risk_table .sr-score-pill[data-color]').each(function () {
        var $el = $(this);
        var color = $el.attr('data-color');
        if (!color) {
            return;
        }
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
}

// Swaps DataTables' own bundled up/down-caret sort indicator
// (.dt-column-order's :before/:after pseudo-elements) for the shared
// fa-sort/fa-arrow-up-short-wide/fa-arrow-down-wide-short glyph convention
// Define Tests and Manage/Initiate Audits use (compliance-initiate-audits.js's
// syncSortIcons(), governance-exceptions.js's identical twin) -- NOT the
// static thead's own pre-built (and entirely unstyled -- no CSS rule exists
// for it anywhere) `<span class="sort-ic">` placeholders from Task 9's
// markup, which this leaves untouched/inert. 'sr-sort-native-off' is what
// _tables.scss keys the native-caret suppression off.
function syncSortIcons() {
    $('#review_risk_table thead th[data-dt-column]').each(function () {
        var $th = $(this);
        if (!$th.is('.dt-orderable-asc, .dt-orderable-desc')) {
            return;
        }
        var $order = $th.find('.dt-column-order').addClass('sr-sort-native-off');
        if (!$order.length) {
            return;
        }
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

function debounce(fn, delay) {
    var timer;
    return function () {
        var args = arguments, ctx = this;
        clearTimeout(timer);
        timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
}

// ---- Columns picker (Task 13; design-system.md §6c; corrected post-review) ----
//
// FIRST PASS (27ca587c8b) built this against DataTables' own column-
// visibility API (`table.column(name + ':name').visible(bool)`) -- the
// exact anti-pattern §6c names explicitly: "No DataTables column-visibility
// API involvement -- this is independent of (and simpler than)
// `columns().visible()`." Corroboration that the FIRST pass had it backwards:
// includes/display.php's static thead (display_review_risk(), ~line 8113)
// already carries `<th data-col='risk_score' class='d-none'>`,
// `<th data-col='responsible'>`, `<th data-col='team' class='d-none'>` --
// staged specifically for the data-col mechanism, using the SAME key
// spelling the design system's own sample uses (matching the <th> text:
// RiskScoreColumn/Owner/Team), not the DataTables internal `columns[].name`
// values ('calculated_risk'/'owner'/'team') the first pass keyed off.
//
// TOGGLE_COLUMNS below is now keyed by those data-col values
// ('risk_score'/'responsible'/'team') -- the SAME keys the colpanel
// checkboxes, the static <th>s, and every generated <td> (via createdCell()
// on the matching column def below) all share. The two are DIFFERENT
// namespaces on purpose: the DataTable's internal `columns[].name` values
// ('calculated_risk'/'owner'/'team') are what `columns:` / the server-side
// `d.columns[].name` filtering still uses; the data-col values are a purely
// presentational key the picker's plain display-toggle owns. Neither
// DataTables column-visibility API call remained anywhere in this section
// as of this pass -- a LATER fix reintroduced one, deliberately: see
// applyColumnVisibility()'s own comment below for why a d-none-only
// approach turned out to be incompatible with ColReorder specifically.
//
// #review_risk_table is a `serverSide` DataTable -- unlike
// governance-exceptions.js's hand-rendered <table> (which stamps its own
// <td data-col="..."> once per render), DataTables generates every <td>
// itself from the `columns:` config on each full-table redraw (sort/filter/
// page), and there is no DOM diff to preserve a toggle through -- exactly
// the situation design-system.md §6c's own text anticipates ("re-apply the
// current visibility state after every full-table re-render ... rather than
// trying to preserve it through the DOM diff -- there isn't one"). So each
// of the 3 toggleable column defs below gets a `createdCell` callback that
// stamps `data-col="<key>"` (and the correct initial hidden/shown class) on
// every <td> as DataTables creates it, and `applyColumnVisibility()` is
// re-bound on the table's own 'draw' event (below, in init()) so a toggle
// made before a sort/filter/page click still holds after it.
//
// `d-none` (Bootstrap's `display:none !important` utility), not jQuery's
// plain `.toggle()`/inline-style show-hide, is the actual flip mechanism
// here -- display.php's static <th>s for risk_score/team already carry a
// literal `class='d-none'` for their default-hidden state (matching this
// page's design-approved default -- Risk Score and Team start hidden, only
// Owner starts shown; see the file-header comment and Task 8/11's specs --
// a deliberate per-page deviation from §6c's generic "every togglable
// column defaults to visible" guidance). A plain inline-style `.toggle()`
// cannot ever SHOW an element that still carries that `!important` class --
// inline `style` never beats `!important` regardless of source order -- so
// toggling the SAME `d-none` class (add to hide, remove to show) is what
// actually composes with that existing markup instead of fighting it.
//
// DataTables' OWN internal visibility (`visible:false` in `columns:`) is
// not set on these 3 column defs at construction -- data-col/d-none is
// still what the picker checkbox drives and what every OTHER reader in
// this file (including this comment's own "never fight" reasoning below)
// keys off. applyColumnVisibility() DOES also call the native
// column().visible() API now, but only as a follower that mirrors
// columnVisible's existing state onto DataTables' model after the fact --
// see that function's own comment for why (ColReorder's drop-zone math
// needs DataTables to know the truth). The two mechanisms still don't
// fight over WHO decides visibility (data-col/columnVisible remains the
// only source of truth a checkbox or this file's own code ever writes to
// directly) -- they just no longer disagree about the RESULT the way they
// did before that fix.
// Column-parity follow-up (post-merge; see .superpowers/sdd/review-risk-
// followups/column-parity-report.md): the three legacy pages this page
// replaced (Plan Your Mitigations / Perform Management Reviews / Review
// Risks Regularly) each had a much richer "Columns" picker, grouped into
// Risk / Mitigation / Review sections (their own settings JSON keys were
// literally risk_colums/mitigation_colums/review_colums -- see
// get_risks()'s sort_order==22 branch, functions.php, for where every
// field below comes from). COLUMN_GROUPS below is the single source of
// truth for that grouping: each entry is { group: <lang key for the
// section header>, columns: [{ key, label }, ...] }, where `key` is the
// data-col value (shared by the colpanel checkbox, the static <th
// data-col="...">, includes/display.php, and the matching `columns:`
// column def's createdCell() below) and `label` is the lang key for that
// column's checkbox text. Every `key`/`label` pair reuses the EXACT
// key spelling the legacy pages' risk_colums/mitigation_colums/
// review_colums JSON used (custom_display_columns_are_valid(), functions.php,
// already documents 'risk_status'/'mitigation_planned' as example legitimate
// names -- this was anticipated). NOT a settings migration, though (post-
// review correction of an earlier version of this comment that overclaimed
// one): the legacy pages stored their column settings under three DIFFERENT
// user-table columns (custom_plan_mitigation_display_settings/custom_perform_
// reviews_display_settings/custom_reviewregularly_display_settings), not this
// page's custom_review_risk_display_settings, and the retirement commit
// (a4e1321963) dropped all three legacy columns outright -- there is no old
// JSON left to read even where the field name matched. The reuse is purely
// for a familiar naming convention, not data continuity. It's also why the
// *_columns label keys below reuse the legacy display_custom_risk_columns()
// helper's (includes/display.php, deleted in a4e1321963 -- see it at
// a4e1321963~1) own lang-key choices verbatim rather than inventing new
// ones -- e.g. 'location' -> lang key 'SiteLocation', not a new 'Location'
// key, and 'regulation' -> 'ControlRegulation', not 'Regulation' -- every one
// of those already existed in lang.en.php, so nothing new needed adding
// (append-only rule: zero new keys for the column labels themselves; three
// new acronym keys -- CVSS/DREAD/OWASP -- were added for a separate reason,
// the scoring_method column's value, not its label; see api.php).
//
// risk_score/responsible/team (the 3 pre-existing toggle columns) are
// folded into the Risk group rather than given their own section --
// legacy risk_colums actually included 'team' and 'calculated_risk'
// (risk_score) and 'owner' (responsible) as toggleable entries too, so
// this matches the legacy grouping, not just a convenient default.
var COLUMN_GROUPS = [
    {
        group: 'RiskColumns',
        columns: [
            { key: 'risk_score', label: 'RiskScoreColumn' },
            { key: 'responsible', label: 'Owner' },
            { key: 'team', label: 'Team' },
            { key: 'risk_status', label: 'Status' },
            { key: 'submission_date', label: 'SubmissionDate' },
            { key: 'closure_date', label: 'DateClosed' },
            { key: 'reference_id', label: 'ExternalReferenceId' },
            { key: 'regulation', label: 'ControlRegulation' },
            { key: 'control_number', label: 'ControlNumber' },
            { key: 'location', label: 'SiteLocation' },
            { key: 'source', label: 'RiskSource' },
            { key: 'category', label: 'Category' },
            { key: 'additional_stakeholders', label: 'AdditionalStakeholders' },
            { key: 'technology', label: 'Technology' },
            { key: 'manager', label: 'OwnersManager' },
            { key: 'submitted_by', label: 'SubmittedBy' },
            { key: 'risk_tags', label: 'Tags' },
            { key: 'scoring_method', label: 'RiskScoringMethod' },
            { key: 'residual_risk', label: 'ResidualRisk' },
            { key: 'project', label: 'Project' },
            { key: 'days_open', label: 'DaysOpen' },
            { key: 'affected_assets', label: 'AffectedAssets' },
            { key: 'risk_assessment', label: 'RiskAssessment' },
            { key: 'additional_notes', label: 'AdditionalNotes' },
            { key: 'risk_mapping', label: 'RiskMapping' },
            { key: 'threat_mapping', label: 'ThreatMapping' },
            { key: 'last_comment', label: 'LastComment' },
        ],
    },
    {
        group: 'MitigationColumns',
        columns: [
            { key: 'mitigation_planned', label: 'MitigationPlanned' },
            { key: 'planning_strategy', label: 'PlanningStrategy' },
            // Legacy label key -- 'planning_date' reuses 'MitigationPlanning'
            // ("Planned Mitigation Date"), the same key the legacy Plan Your
            // Mitigations page's own column list used for this field. There
            // is no separate 'PlanningDate' key in lang.en.php.
            { key: 'planning_date', label: 'MitigationPlanning' },
            { key: 'mitigation_effort', label: 'MitigationEffort' },
            { key: 'mitigation_cost', label: 'MitigationCost' },
            { key: 'mitigation_owner', label: 'MitigationOwner' },
            { key: 'mitigation_team', label: 'MitigationTeam' },
            { key: 'mitigation_accepted', label: 'MitigationAccepted' },
            { key: 'mitigation_date', label: 'MitigationDate' },
            { key: 'mitigation_controls', label: 'MitigationControls' },
            { key: 'current_solution', label: 'CurrentSolution' },
            { key: 'security_recommendations', label: 'SecurityRecommendations' },
            { key: 'security_requirements', label: 'SecurityRequirements' },
        ],
    },
    {
        group: 'ReviewColumns',
        columns: [
            // Mirrors mitigation_planned's own boolField Yes/No shape (the
            // Mitigation group above) -- "has a review happened at all",
            // distinct from management_review just below, which shows the
            // review's OUTCOME (Approve/Reject), not whether one occurred.
            { key: 'review_completed', label: 'ReviewCompleted' },
            { key: 'management_review', label: 'ManagementReview' },
            { key: 'review_date', label: 'ReviewDate' },
            { key: 'next_review_date', label: 'NextReviewDate' },
            { key: 'next_step', label: 'NextStep' },
            { key: 'comments', label: 'Comments' },
            // Who submitted the risk's CURRENT review (renderUserChip()'s
            // own comment has the exact query shape). Previously filter-only
            // (the secondary reviewer dropdown, since merged into
            // #review-risk-user-filter, had no matching column) -- now a
            // real, toggleable column with the same clickable-chip treatment
            // as Owner. Reuses 'ReviewedBy'
            // (get_dynamic_names_by_main_field_name()'s own key for this
            // same field, includes/display.php) rather than a new key --
            // this column's label would otherwise silently change on any
            // Customization-Extra-active site, since applyActiveColumns()
            // replaces this whole static list with that function's own
            // {key, label} resolution whenever the Extra is on.
            { key: 'reviewer', label: 'ReviewedBy' },
        ],
    },
];

// Flattened [key, ...] list, derived from COLUMN_GROUPS -- this is what
// every pre-existing generic consumer below (applyColumnVisibility(),
// saveColumnsDebounced(), the column_settings.columns.forEach load in
// loadFilterOptions()) already iterates, and none of them needed to change
// shape to support grouping -- they only ever cared about the flat key
// list, never about section boundaries.
var TOGGLE_COLUMNS = COLUMN_GROUPS.reduce(function (keys, g) {
    return keys.concat(g.columns.map(function (c) { return c.key; }));
}, []);

// col -> label lang-key, derived from COLUMN_GROUPS.
var COLUMN_LABEL_KEYS = COLUMN_GROUPS.reduce(function (labels, g) {
    g.columns.forEach(function (c) { labels[c.key] = c.label; });
    return labels;
}, {});

// Every column defaults hidden except 'responsible' (Owner) -- the
// pre-existing default this file's header comment above documents (a
// deliberate per-page deviation from design-system.md §6c's generic
// "every togglable column defaults to visible" guidance). All ~40
// column-parity fields added by this follow-up are customer-OPT-IN, not
// defaults, per the explicit product decision that drove this task.
var columnVisible = TOGGLE_COLUMNS.reduce(function (v, col) {
    v[col] = false;
    return v;
}, {});
columnVisible.responsible = true;

// Load-time snapshots of the STATIC (built-in) column set, captured before
// applyActiveColumns() can replace COLUMN_GROUPS/COLUMN_LABEL_KEYS with the
// Customization Extra's active field set. Read only by activeColumnsFromDom()
// (Finding 1's loadFilterOptions() failure path, below), which has to recover
// each static key's group and label lang-key from the page's own thead when
// the server's active_columns response never arrived. Plain object
// references, not copies -- applyActiveColumns() REASSIGNS those two globals
// rather than mutating them, so the originals these point at stay intact.
var STATIC_COLUMN_GROUP_BY_KEY = COLUMN_GROUPS.reduce(function (groups, g) {
    g.columns.forEach(function (c) { groups[c.key] = g.group; });
    return groups;
}, {});
var STATIC_COLUMN_LABEL_KEYS = COLUMN_LABEL_KEYS;

// Customization Extra follow-up: when active_columns is non-null (the
// Customization Extra is active on this site -- build_active_review_risk_columns(),
// includes/functions.php), REPLACES COLUMN_GROUPS/TOGGLE_COLUMNS/
// COLUMN_LABEL_KEYS/columnVisible with the site's actually-active field set
// instead of this file's static default list -- matching the legacy
// display_custom_risk_columns()'s own behavior of only ever offering
// fields the site has activated, not every field this codebase knows
// about. Called from loadFilterOptions()'s first-load block, BEFORE
// renderColpanel()/applyColumnVisibility() run and before the DataTable's
// own `columns:` config array is built (see Task 8) -- COLUMN_GROUPS must
// be in its final form before either of those reads it.
//
// `active_columns` entries are grouped by their own `group` field (one of
// 'RiskColumns'/'MitigationColumns'/'ReviewColumns', matching this file's
// existing three sections) -- built fresh each call rather than mutating
// the existing groups in place, so a stale key from an earlier call never
// lingers.
//
// get_dynamic_names_by_main_field_name() (includes/display.php), which
// build_active_review_risk_columns() uses to resolve a basic field's
// db-column name, spells the Owner field's key 'owner' -- but this file's
// own pre-existing 3 legacy toggle columns (risk_score/responsible/team,
// folded into the Risk group above) have always used 'responsible' as BOTH
// the colpanel's data-col key and the hardcoded DataTable column def's own
// createdCell() lookup further down this file (`{ data: 'owner', name:
// 'owner', createdCell: function (td) { $(td).attr('data-col',
// 'responsible').toggleClass('d-none', !columnVisible.responsible); } }` --
// 'owner' there is the AJAX row's data PROPERTY name, a different namespace
// from the toggle/visibility key 'responsible'). Left unaliased, an active
// Customization Extra site's Owner column silently flips from
// "visible by default" to "hidden" the instant this merge runs: the rebuilt
// columnVisible carries an 'owner' key nothing else in this file reads,
// while 'responsible' -- the key createdCell() actually checks, and the
// only column this file ever defaults to visible (columnVisible.responsible
// = true, above) -- goes missing entirely. Confirmed live against
// simplerisk-dev: without this alias, the Owner column's header stays
// visible (unmanaged static markup) but every data cell goes blank.
// Remapped here, not in PHP: get_dynamic_names_by_main_field_name() is a
// shared resolver with other legacy consumers who already treat 'owner' as
// their own canonical key, so changing its output there risks breaking
// them. 'team' and 'risk_score' need no such entry -- 'team' already
// resolves to the same spelling on both sides, and 'risk_score' is a
// calculated value with no entry in get_active_fields('risk') at all, so it
// never appears in activeColumns to begin with (matching the legacy
// display_custom_risk_columns()'s own omission of it).
//
// Task 8 audit (per this task's brief -- checking the remaining ~44 active
// BASIC fields for the SAME class of mismatch, not just owner/responsible):
// found exactly one more. custom_fields seeds a basic field named literally
// 'Review' (extras/customization/upgrade.php, fgroup='risk', tab_index=3 --
// ReviewColumns), and get_dynamic_names_by_main_field_name('Review')
// resolves it to key 'review' -- but the Review Risk grid's actual toggle
// column for this concept has always been keyed 'management_review' (both
// display.php's static `<th data-col='management_review'>` and this file's
// own toggleColumnDef('management_review', ...) below), matching the
// SEPARATE 'ManagementReview' => 'management_review' entry in
// extras/customization/index.php's own (differently-keyed, legacy) field
// map -- 'Review'/'review' has no relationship to that column at all.
// Same failure mode as Owner unaliased: an active Customization Extra site
// with 'Review' active would get a phantom 'review'-keyed checkbox in the
// picker that toggles nothing (no `[data-col="review"]` anywhere in the
// DOM), while 'management_review' itself -- no longer present in the
// rebuilt TOGGLE_COLUMNS post-applyActiveColumns() -- permanently loses its
// own checkbox and gets stuck hidden. Every OTHER basic-field name in
// get_dynamic_names_by_main_field_name() was checked against this file's
// toggleColumnDef()/hardcoded-column-def key list and matches directly (no
// alias needed) -- with one EXCEPTION that is a different bug class, not a
// spelling mismatch, so NOT aliased here: 'MitigationPercent' (-> key
// 'mitigation_percent') resolves to a key with NO backing column under ANY
// name anywhere in this file or display.php's thead -- aliasing to a real
// column would be actively wrong, since none represents the same concept.
// ('Reviewer' (-> key 'reviewer') was in this same bucket originally --
// filter-only, no grid column -- but is now a real toggleable column
// (display.php's `<th data-col='reviewer'>`, this file's own
// toggleColumnDef('reviewer', ...) below), so it no longer needs this
// carve-out.) That follow-up is now DONE, in this same function:
// columnHasBackingHeader() (below) drops any key with no
// `[data-col="<key>"]` <th> in the page's own thead before it can reach
// COLUMN_GROUPS, so 'mitigation_percent' (and any future basic field in the
// same position) never gets a dead, no-op picker checkbox at all.
var COLUMN_KEY_ALIASES = { owner: 'responsible', review: 'management_review' };

// Finding 2 (final whole-branch review): is there a real <th> backing this
// column key? The page's ENTIRE thead -- the ~46 static basic-field <th>s AND
// every active custom field's `<th data-col='custom_field_N'>` -- is rendered
// SERVER-SIDE by display_review_risk() (includes/display.php) before any of
// this file runs; nothing here ever appends a <th>. So by the time
// applyActiveColumns() is called (loadFilterOptions()'s `.done()`/`.fail()`,
// well after DOM-ready) the header row is already complete and final, and a
// key with no match here is one that will NEVER have a column -- not one
// whose <th> merely hasn't been created yet. That makes this a safe,
// unambiguous "unbacked key" test for custom_field_* and basic keys alike.
function columnHasBackingHeader(key) {
    return $('#review_risk_table thead th[data-col="' + key + '"]').length > 0;
}

// TWO-REQUEST ORDERING CONTRACT (Minor Fix B, final whole-branch review).
// DataTables matches `columns:` config entries to <th> elements purely by
// POSITION, and as of this plan that position contract now spans TWO
// INDEPENDENT HTTP REQUESTS rather than one page render:
//
//   1. the initial page load, whose server-rendered thead (display.php's
//      display_review_risk()) fixes the <th> sequence, and
//   2. the async GET .../review_risk/filter_options, whose `active_columns`
//      payload is what this function turns into the COLUMN_GROUPS that
//      buildReviewRiskDataTable() then reads to build its `columns:` array.
//
// Both are answered by build_active_review_risk_columns() (functions.php),
// which iterates get_active_fields('risk') (extras/customization/index.php).
// That query GROUPs BY t1.id, so for a field belonging to more than one
// template group MySQL may pick EITHER row's tab_index/ordering -- stable in
// practice for a given query plan and dataset, but not guaranteed by the SQL
// itself. Before this plan that non-determinism only affected which tab a
// field rendered under; it is now load-bearing for positional COLUMN
// ALIGNMENT between the two requests. It is also why an admin activating or
// deactivating a field BETWEEN the two requests can desync them. Two
// defenses exist for that window and should be kept: columnHasBackingHeader()
// above (drops a key the thead has no column for) and `defaultContent: ''` on
// the custom-field column defs (toggleColumnDef(), above -- declares an
// absent row value legal, so DataTables' "Requested unknown parameter" path
// is never entered when a column's row data is missing).
function applyActiveColumns(activeColumns) {
    if (!activeColumns) {
        return; // Customization Extra not active -- keep the static default COLUMN_GROUPS as-is.
    }

    var byGroup = { RiskColumns: [], MitigationColumns: [], ReviewColumns: [] };
    activeColumns.forEach(function (col) {
        if (byGroup[col.group]) {
            var key = COLUMN_KEY_ALIASES[col.key] || col.key;
            // Finding 2: skip any key with no backing <th> anywhere in the
            // table (see columnHasBackingHeader() above). Two basic fields
            // reach here in that state on a real site --
            // get_dynamic_names_by_main_field_name() resolves 'Reviewer' to
            // 'reviewer' and 'MitigationPercent' to 'mitigation_percent',
            // neither of which this grid has ever had a column for under any
            // name -- and before this guard each produced a dead picker
            // checkbox that toggled nothing. Dropping them here keeps the
            // picker's contents and the table's real columns in agreement.
            if (!columnHasBackingHeader(key)) {
                return;
            }
            byGroup[col.group].push({ key: key, label: col.label });
        }
    });

    COLUMN_GROUPS = Object.keys(byGroup).map(function (group) {
        return { group: group, columns: byGroup[group] };
    }).filter(function (g) { return g.columns.length > 0; });

    TOGGLE_COLUMNS = COLUMN_GROUPS.reduce(function (keys, g) {
        return keys.concat(g.columns.map(function (c) { return c.key; }));
    }, []);

    COLUMN_LABEL_KEYS = COLUMN_GROUPS.reduce(function (labels, g) {
        g.columns.forEach(function (c) { labels[c.key] = c.label; });
        return labels;
    }, {});

    var previousVisible = columnVisible;
    columnVisible = TOGGLE_COLUMNS.reduce(function (v, col) {
        // Preserve any visibility state already read from a saved
        // custom_review_risk_display_settings (this runs BEFORE that JSON's
        // columns[] is applied to the NEW columnVisible -- see
        // loadFilterOptions()'s call order, below) -- default to false
        // (matching this file's existing "opt-in" default) only for a key
        // with no prior state.
        v[col] = previousVisible.hasOwnProperty(col) ? previousVisible[col] : false;
        return v;
    }, {});
}

// Finding 1 (final whole-branch review): reconstructs an `active_columns`-
// shaped array from the page's OWN server-rendered thead, for the one case
// where the real one never arrives -- loadFilterOptions()'s GET failing (a
// transient 500, a dropped connection, a proxy hiccup). Returns null when the
// thead carries no `custom_field_*` <th> at all, which is exactly the
// Customization-Extra-inactive case: the static COLUMN_GROUPS is already
// correct there and applyActiveColumns(null) deliberately leaves it alone.
//
// Why this is needed rather than just skipping the merge on failure: the
// thead is rendered ONCE, server-side, and on an Extra-active site it already
// contains one extra <th> per active custom field. Building the DataTable
// from the STATIC COLUMN_GROUPS against that thead would hand DataTables a
// `columns:` array shorter than the header row -- the exact column-count
// mismatch Task 8 exists to prevent, and a hard init failure rather than a
// degraded grid. Recovering the custom-field keys from the markup keeps the
// positional contract intact with no second request.
//
// Group is derived POSITIONALLY, which is sound because display.php emits
// each group's custom-field <th>s immediately after that same group's last
// static basic-field <th>: the running `group` variable therefore always
// holds the group of the most recent static column seen. Labels: a static key
// keeps its own lang KEY (so L() still translates it), while a custom field
// uses its <th> text, which PHP already localized -- L() answers an unknown
// key with the key itself (header.php's `_lang[k] || k`), so both round-trip
// correctly through columnLabel().
function activeColumnsFromDom() {
    var columns = [];
    var sawCustomField = false;
    var group = 'RiskColumns';
    $('#review_risk_table thead th[data-col]').each(function () {
        var key = this.getAttribute('data-col');
        if (!key) {
            return;
        }
        if (key.indexOf('custom_field_') === 0) {
            sawCustomField = true;
            columns.push({ key: key, label: $.trim($(this).text()), group: group });
        } else {
            group = STATIC_COLUMN_GROUP_BY_KEY[key] || group;
            columns.push({ key: key, label: STATIC_COLUMN_LABEL_KEYS[key] || key, group: group });
        }
    });
    return sawCustomField ? columns : null;
}

function columnLabel(col) {
    return L(COLUMN_LABEL_KEYS[col] || col);
}

// Task 8 (Customization Extra dynamic columns): the active custom fields
// belonging to ONE section (RiskColumns/MitigationColumns/ReviewColumns),
// as `columns:` config entries, in the SAME relative order
// display_review_risk() (includes/display.php) emits their <th> markup in.
//
// Reads COLUMN_GROUPS (not the flattened TOGGLE_COLUMNS) because only
// COLUMN_GROUPS still carries which section each key belongs to --
// applyActiveColumns() (Task 5) builds each group's column list by
// iterating build_active_review_risk_columns()'s single flat, get_active_
// fields()-ordered array and pushing into byGroup[col.group], so a group's
// entries here land in exactly the relative order display.php's own
// per-group `foreach ($active_review_risk_columns as $col) { if
// ($col['group'] === '<Group>' && strpos($col['key'], 'custom_field_') ===
// 0) ... }` loop produces -- filtering THIS group's columns down to the
// custom_field_* keys (basic fields in the group are skipped; they already
// have their own hand-written/toggleColumnDef() entry elsewhere in
// `columns:`) preserves that same order.
//
// MUST be spliced in at the 3 group-boundary positions below (after
// last_comment/security_requirements/comments, before mitigation_planned/
// review_completed/due_date) rather than appended as one trailing block --
// display.php interleaves each group's custom-field <th>s right after that
// group's LAST static basic-field <th>, not after every group. DataTables
// matches `columns:` entries to <th> elements purely by position, so a
// single trailing block would misalign every column from the first custom
// field onward whenever a Customization Extra site has an active custom
// field in the Risk or Mitigation section (not just Review) -- confirmed
// against display.php's own 3 separate per-group loops, not assumed.
function customFieldColumnDefsForGroup(groupName) {
    var matches = COLUMN_GROUPS.filter(function (g) { return g.group === groupName; });
    if (!matches.length) {
        return [];
    }
    return matches[0].columns.filter(function (c) {
        return c.key.indexOf('custom_field_') === 0;
    }).map(function (c) {
        // defaultContent: '' -- see toggleColumnDef()'s own comment for why
        // only the custom-field columns opt into it.
        return toggleColumnDef(c.key, { defaultContent: true });
    });
}

// Core columns (checkbox, ID, Risk, Needs, Risk Level, Due Date, Actions)
// have no entry in TOGGLE_COLUMNS at all, so they never get a checkbox here
// -- always-visible per design-system.md §6c, matching this file's own
// `columns` config below (no visible:false on any of them).
//
// Renders one `.colpanel-group` block per COLUMN_GROUPS entry -- a small
// uppercase section label (`.colpanel-group-label`, scss/modules/
// _tables.scss -- new rule added by this follow-up, matching the
// `__eyebrow`/`__foot-label` small-caps section-label formula already
// established in _home.scss) followed by that group's checkboxes, instead
// of one flat list -- restoring the legacy pages' Risk/Mitigation/Review
// column-picker sections.
//
// Also renders a search box (`.colpanel-search`) as the FIRST child, ahead
// of the groups, and a `.colpanel-empty` "no matches" row as the LAST
// child -- both built into this same `html` string (rather than static
// markup in display.php) because this call fully replaces
// #review-risk-colpanel's contents via .html(), which would otherwise wipe
// a statically-placed search box on every re-render. renderColpanel() only
// actually runs once today (initColumnsPicker() below), but building the
// search box into the generated markup keeps it correct even if that
// changes. The `keyup`/`input` filtering handler is bound via delegation
// (initColumnsPicker()) rather than rebound here, so there's no re-bind
// hazard either way.
function renderColpanel() {
    var searchHtml = '<div class="colpanel-search">' +
        '<i class="fa fa-search colpanel-search-icon" aria-hidden="true"></i>' +
        '<input type="text" class="colpanel-search-input" id="review-risk-colpanel-search" placeholder="' + escAttr(L('Search')) + '" aria-label="' + escAttr(L('Search')) + '" autocomplete="off">' +
        '</div>';
    var groupsHtml = COLUMN_GROUPS.map(function (g) {
        var items = g.columns.map(function (c) {
            return '<label class="colpanel-item"><input type="checkbox" data-col="' + escAttr(c.key) + '"' + (columnVisible[c.key] ? ' checked' : '') + '> ' + esc(columnLabel(c.key)) + '</label>';
        }).join('');
        return '<div class="colpanel-group"><div class="colpanel-group-label">' + esc(L(g.group)) + '</div>' + items + '</div>';
    }).join('');
    var emptyHtml = '<div class="colpanel-empty d-none">' + esc(L('NoMatchingOptions')) + '</div>';
    $('#review-risk-colpanel').html(searchHtml + groupsHtml + emptyHtml);
}

// Case-insensitive substring match against each `.colpanel-item`'s own
// label text -- same matching style as sr-select.js's srSelectFilter()
// (js/simplerisk/sr-select.js), this app's existing "type to filter a
// labeled list" convention: no trimming, substring-anywhere (not
// starts-with), no debounce (the list is ~40 items, filtering is cheap
// enough to run on every keystroke synchronously).
//
// Hides via the `d-none` utility class, never by touching a checkbox's
// `checked` property -- a checked-but-filtered-out column must stay
// checked underneath. A `.colpanel-group` is hidden entirely once every
// `.colpanel-item` inside it is filtered out (so "mitigation" shows only
// the Mitigation Columns group, not empty Risk/Review group headers), and
// `.colpanel-empty` is shown only when the search matches nothing in any
// group.
function filterColpanelItems(query) {
    var q = String(query || '').toLowerCase();
    var anyVisible = false;
    $('#review-risk-colpanel .colpanel-group').each(function () {
        var $group = $(this);
        var groupHasMatch = false;
        $group.find('.colpanel-item').each(function () {
            var matches = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
            $(this).toggleClass('d-none', !matches);
            if (matches) {
                groupHasMatch = true;
            }
        });
        $group.toggleClass('d-none', !groupHasMatch);
        if (groupHasMatch) {
            anyVisible = true;
        }
    });
    $('#review-risk-colpanel .colpanel-empty').toggleClass('d-none', anyVisible);
}

// Clears the search box and restores every item/group to visible -- called
// whenever the panel transitions closed (or reopens), per product
// direction that a stale filter from a previous session shouldn't persist
// into the next open.
function resetColpanelSearch() {
    $('#review-risk-colpanel-search').val('');
    filterColpanelItems('');
}

// Plain display-toggle, driven entirely off the shared data-col key -- no
// DataTables column-visibility API call. Hits both the static <th>s
// (display.php) and every <td> stamped by the matching column def's
// createdCell() below; safe to call before `table` exists (loadFilterOptions()
// calls this from its async .done() handler, which may resolve before the
// synchronous DataTable() build below completes -- the <th>s are already in
// the DOM from PHP, and any <td> not yet created will get the current
// columnVisible state directly from createdCell() when DataTables creates
// it, so there's no ordering hazard to guard against here).
//
// Finding 2 (final whole-branch review): the loop below only ever touches
// keys that are IN TOGGLE_COLUMNS, so any data-col column that applyActiveColumns()
// DROPPED from that list is left with whatever class the static markup gave
// it -- and display.php renders exactly one static <th> WITHOUT `d-none`:
// `<th data-col='responsible'>` (Owner), the page's one default-visible
// toggle column. So on a Customization Extra site where an admin deactivates
// the 'Owner' basic field (seeded with required=0, so this is reachable), the
// Owner HEADER stayed permanently visible while every Owner <td> went hidden
// (createdCell() reads columnVisible.responsible, now undefined -> falsy) --
// a visible header over an entirely blank column, with no picker checkbox
// left to turn it off. The second pass below closes that by force-hiding
// every data-col element this page is no longer managing. It runs on each
// call (including every 'draw') rather than once, because DataTables rebuilds
// tbody from scratch on every redraw; the thead scan is ~50 elements and
// normally yields zero unmanaged keys, so the added cost is negligible.
function applyColumnVisibility() {
    var managed = {};
    TOGGLE_COLUMNS.forEach(function (col) { managed[col] = true; });

    // Column visibility here is DELIBERATELY a custom `d-none` CSS toggle,
    // not DataTables' own `column().visible()` API -- design-system.md
    // section 6c names that API choice explicitly as the pattern to avoid
    // ("No DataTables column-visibility API involvement -- this is
    // independent of (and simpler than) columns().visible()"), and this
    // file's own header comment records a first pass that used it being
    // reverted post-review for exactly that reason. That guidance is
    // correct for every OTHER page using this same .colpicker pattern
    // (Define Exceptions, the reference implementation, has no drag-reorder
    // at all) -- but this page is the only one in the design system that
    // combines column-hiding with ColReorder, and the combination has a
    // real bug the guidance never anticipated: ColReorder's own drop-zone
    // math (dataTables.colReorder.js's _regions()) calls
    // dt.columns().widths() over ALL 54 columns and only skips a column
    // from that calculation via DataTables' NATIVE column().visible() check
    // -- never d-none, which DataTables has no way to know about. A
    // CSS-hidden-but-DataTables-visible column reports a PHANTOM non-zero
    // width (confirmed live via a CDP breakpoint inside the vendored
    // library: 28px per hidden column) instead of the 0px it actually
    // occupies on screen, so every drop-zone boundary past the first
    // hidden column is wrong. The fewer toggleable columns are currently
    // visible, the shorter the real (correct) zone section is relative to
    // the phantom one, so the cursor's true on-screen position falls into
    // the WRONG zone far more easily -- confirmed live via the same
    // breakpoint: a drag that should land at column index 6 instead lands
    // around index 28 for a fresh account (2 default-visible toggleable
    // columns) while landing correctly for an account with 3+ visible
    // (the same drag geometry, only the phantom-to-real zone ratio
    // differs). The result is deterministic, not flaky: a first-time
    // user's very first column drag on this page silently moves the
    // column into the hidden-column range instead of where they dropped
    // it, with no visible change and no error.
    //
    // The fix keeps the d-none toggle below as the single source of truth
    // for what the picker checkbox/UI does (unchanged from every other
    // .colpicker page) and ADDITIONALLY mirrors that same state onto
    // DataTables' native per-column visibility, purely so ColReorder's own
    // width calculation sees the truth. This block runs FIRST, before the
    // d-none toggling below -- confirmed live this order matters:
    // `column().visible(true)` is what RE-ATTACHES a `<th>`/`<td>` set that
    // a PRIOR call's `column().visible(false)` removed from the DOM
    // entirely, and it re-attaches the ORIGINAL node with whatever classes
    // it already had (including a stale `d-none` from before it was last
    // hidden). Running the d-none-clearing loop first found nothing to
    // clear (the node wasn't attached yet) and left that stale `d-none` on
    // the freshly-reattached header -- DataTables correctly reports the
    // column visible=true, but it stayed CSS-hidden and vanished from
    // getColumnOrder()'s reads regardless. Native-visibility-first fixes
    // that: by the time the d-none loop runs below, every column that
    // needs to be shown is already back in the DOM for it to find.
    //
    // Iterates via `table.columns().every()` (DataTables' OWN column
    // model), NOT a `$('...thead th[data-col]')` DOM query -- a jQuery
    // selector only finds `<th>`s CURRENTLY present in the DOM, and a
    // column already natively hidden from a PRIOR call would therefore
    // never be found by a DOM-selector loop on the NEXT call, so it could
    // never be natively re-shown again once hidden once. `table.columns().
    // every()` walks DataTables' internal column list, which is complete
    // regardless of current visibility, so a column's `<th>` node (kept
    // alive by DataTables even while detached) is always reachable to flip
    // back.
    if (table) {
        table.columns().every(function () {
            var header = this.header();
            var key = header ? header.getAttribute('data-col') : null;
            if (!key) {
                return; // Core/non-toggleable column -- always native-visible, leave it alone.
            }
            var shouldBeVisible = managed[key] ? !!columnVisible[key] : false;
            if (this.visible() !== shouldBeVisible) {
                this.visible(shouldBeVisible, false);
            }
        });
        // `columns.adjust()` only recalculates column widths/positions from
        // the current DOM (no ajax refetch, no redraw of row data) -- it
        // must run every time visibility changes, not just once, so
        // ColReorder's cached zone math stays in sync with whatever the
        // loop above just changed. Runs before the d-none loop below reads
        // the DOM, same reattachment-ordering reason as above.
        table.columns.adjust();
    }

    TOGGLE_COLUMNS.forEach(function (col) {
        $('#review_risk_table [data-col="' + col + '"]').toggleClass('d-none', !columnVisible[col]);
    });

    $('#review_risk_table thead th[data-col]').each(function () {
        var key = this.getAttribute('data-col');
        if (key && !managed[key]) {
            $('#review_risk_table [data-col="' + key + '"]').addClass('d-none');
        }
    });
}

// IMPORTANT (verified live against simplerisk-dev, not just read off the
// vendored source -- see this function's own file-header cross-reference):
// this does NOT use `table.colReorder.order()`/DataTables' `columns[].name`.
// Two independent traps ruled those out:
//
// 1) `columns[].name` (and `column().dataSrc()`) is NOT the same key as
//    data-col for 2 of the 46 toggleable columns -- risk_score/responsible's
//    hand-written column defs (init(), below) set `name`/`data` to
//    'calculated_risk'/'owner', a DELIBERATELY different namespace from
//    their data-col value (see TOGGLE_COLUMNS' own file-header comment, "the
//    two are DIFFERENT namespaces on purpose"). Keying off `name` silently
//    dropped these 2 columns from a saved order every time.
// 2) `table.colReorder.order()` (getter, no args) does NOT return current
//    visual order -- confirmed live via direct introspection (a single
//    drag produced order values like `15` for the column now at position
//    `0`) -- it returns, per POSITION, that column's frozen ORIGINAL/
//    load-time index (ColReorder's own `_crOriginalIdx` bookkeeping,
//    dataTables.colReorder.js's getOrder()). Feeding those values into
//    `table.column(idx)` (which indexes by CURRENT position, confirmed via
//    the same introspection) reads the WRONG column whenever any prior
//    reorder has happened. table.columns().count()/table.column(p).header()
//    -- plain current-position iteration -- has no such trap: DataTables'
//    own numeric column index IS current visual position, always.
//
// Reads the data-col key straight off each column's live <th> header node
// (the authoritative, page-wide key every other data-col consumer here
// uses) in CURRENT POSITION order (0..count()-1) -- exactly what a drag
// just produced, no original/current index bookkeeping involved.
function currentColumnOrderForSave() {
    if (!table) {
        return savedColumnOrder;
    }
    var order = [];
    for (var p = 0; p < table.columns().count(); p++) {
        var header = table.column(p).header();
        var key = header ? header.getAttribute('data-col') : null;
        if (key && TOGGLE_COLUMNS.indexOf(key) !== -1) {
            order.push(key);
        }
    }
    return order;
}

// Applies a saved column order (array of data-col keys, in desired visual
// order) to the live table. See currentColumnOrderForSave()'s comment,
// above, for why this reads/writes CURRENT-position data-col keys straight
// off each column's <th> header rather than DataTables' `columns[].name`
// or ColReorder's own `order()` getter -- both traps apply here too.
//
// Only the TOGGLEABLE (data-col-bearing) columns are reordered among
// themselves to match savedColumnOrder; every non-toggleable column
// (the checkbox, ID, Risk Title, Needs Review, Risk Level, Due Date,
// Actions -- none of which carry a data-col attribute) stays PINNED to its
// own current position via the stable merge below. An earlier version of
// this function appended every non-participating column at the very end
// instead -- verified LIVE against simplerisk-dev that this shoved the
// fixed-left checkbox column (and every other core column) off to the far
// right on any page load with a saved order, breaking row-selection/ID/
// Title/Due-Date layout entirely. Skips any saved key that no longer
// resolves to a real column (a stale name from a prior release, or a
// custom field the site has since deactivated) rather than throwing.
function applySavedColumnOrder() {
    if (!savedColumnOrder || !table) {
        return;
    }
    var columnCount = table.columns().count();
    var keyAtPosition = [];
    for (var p = 0; p < columnCount; p++) {
        var header = table.column(p).header();
        keyAtPosition.push(header ? header.getAttribute('data-col') : null);
    }

    // The desired NEW sequence of just the toggleable columns' CURRENT
    // positions: savedColumnOrder's keys first, then any toggleable column
    // NOT named in savedColumnOrder (e.g. added by a later release),
    // appended in current relative order.
    var orderedTogglePositions = [];
    savedColumnOrder.forEach(function (key) {
        var pos = keyAtPosition.indexOf(key);
        if (pos !== -1 && orderedTogglePositions.indexOf(pos) === -1) {
            orderedTogglePositions.push(pos);
        }
    });
    keyAtPosition.forEach(function (key, pos) {
        if (key !== null && orderedTogglePositions.indexOf(pos) === -1) {
            orderedTogglePositions.push(pos);
        }
    });

    // Stable merge: rebuild the full position sequence, substituting each
    // toggleable slot with the next entry from orderedTogglePositions while
    // leaving every non-toggleable slot pointing at itself (unchanged).
    var toggleCursor = 0;
    var desired = keyAtPosition.map(function (key, pos) {
        return key !== null ? orderedTogglePositions[toggleCursor++] : pos;
    });

    // `false` (not `true`): `desired` is already in CURRENT-position space
    // (see the two-function header comment above) -- passing `true` here
    // would tell ColReorder to transpose it AGAIN as if it were
    // original-index space, corrupting the result.
    restoringSavedColumnOrder = true;
    table.colReorder.order(desired, false);
    restoringSavedColumnOrder = false;
}

// Debounced save -- reuses this file's own debounce() (defined just above;
// review-risk.js already has its own local copy for the search box below,
// same convention as settings-hub.js/reports-hub.js/compliance-define-
// tests.js/compliance-initiate-audits.js, each of which defines its own
// rather than relying on one shared global). Sends the FULL TOGGLE_COLUMNS
// state on every save, not just the toggled column -- save_custom_risk_
// display_settings() (includes/functions.php) does a straight UPDATE that
// replaces the whole stored JSON, there's no server-side merge. Also
// always includes the current column `order` alongside `columns` (via
// currentColumnOrderForSave() above) for the same reason -- see that
// helper's comment; this is a plain checkbox toggle, not a drag, but it
// must not wipe a previously-established order out of the stored blob.
//
// IMPORTANT: `columns` is passed as a real JS array of [name, '1'/'0']
// pairs, NOT JSON.stringify()'d (the task brief's sample did -- confirmed
// stale against Task 6's real saveCustomReviewRiskDisplaySettingsAPI(),
// includes/api.php, which requires `is_array($_POST["columns"])` and would
// 400 on a JSON string). jQuery's default (non-traditional) param
// serialization turns a nested JS array into repeated `columns[i][]=...`
// form fields, which PHP reassembles into `$_POST['columns']` as an array of
// [name, value] pairs -- exactly the shape custom_display_columns_are_valid()
// and the save function expect. No explicit CSRF header needed here (this
// is a plain form-urlencoded POST, auto-handled by csrf-magic.js's global
// XMLHttpRequest override -- see csrfHeaders()'s own comment above); the
// `error` handler mirrors the DataTables ajax error handler's retryCSRF()
// call in case a rotated token causes the first attempt to fail.
var saveColumnsDebounced = debounce(function () {
    var payload = TOGGLE_COLUMNS.map(function (col) {
        return [col, columnVisible[col] ? '1' : '0'];
    });
    var postData = { columns: payload };
    // Only include `order` when one is actually established (see
    // currentColumnOrderForSave()'s comment) -- omitting the key entirely
    // preserves the exact pre-ColReorder behavior (server treats a missing
    // `order` as "use natural order") for a site where no drag has ever
    // happened and no saved order was ever loaded.
    var order = currentColumnOrderForSave();
    if (order) {
        postData.order = order;
    }
    $.post({
        url: BASE_URL + '/api/v2/risk_management/save_custom_review_risk_display_settings',
        data: postData,
        error: function (xhr) { retryCSRF(xhr, this); },
    });
}, 500);

// ---- Secondary filters panel (User/Team/Risk Level) ----
//
// The three filter <select>s post their value straight through to
// getReviewRiskDatatableResponse() (includes/api.php) as dedicated
// user_filter[]/team_filter[]/risk_level_filter[] params, which that
// handler matches with an exact array_intersect-any-of check against the
// row's already-resolved NAME string(s) for that dimension --
// $filter_row['user']/['team_all'] are the merged, deduplicated arrays built
// from every user-bearing/team-bearing role on the risk (see that
// function's own comment for the full field list), and
// $filter_row['risk_level'] is get_risk_level_name()'s resolved display
// name. get_options_from_table()
// returns {value, name} pairs shaped for ID-keyed <select> use elsewhere in
// the app (value = the row's numeric id) -- sending that id as the filter
// value here would never match anything, since the server compares against
// the name string, not the id. nameOption()/riskLevelOption() below remap
// every option so the <option>'s value is the same name string the server
// filters against.
// `count` (per-option counts follow-up) rides straight through from
// getReviewRiskFilterOptions()'s response (includes/api.php) -- undefined
// when the server didn't send one, which populateSelect() below treats as
// "no count chip for this option" (a zero-count option is never sent at all,
// per that same endpoint -- see its own docblock).
function nameOption(opt) {
    return { value: opt.name, name: opt.name, count: opt.count };
}

// risk_levels rows: get_risk_level_name() (includes/functions.php) --
// exactly what getReviewRiskDatatableResponse() uses to build
// $filter_row['risk_level'] -- resolves to the row's admin-configurable
// display_name when set, falling back to name otherwise. Mirror that same
// precedence here so a customer who has renamed a level
// (admin/risk_configuration.php) gets a filter option that actually matches
// both what the grid displays and what the server filters against.
function riskLevelOption(level) {
    var label = level.display_name || level.name;
    return { value: label, name: label, count: level.count };
}

// Builds a fresh <option> list for a filter <select>: escAttr() for the
// value ATTRIBUTE (User/Team names are free-text, user-controlled
// values -- an unescaped " or ' there would break out of the attribute) and
// esc() for the TEXT content between the tags. Preserves the select's
// current selection(s) across a repopulate -- loadFilterOptions() (below)
// now runs on every filter/scope/action-type/search change (per-option-counts
// follow-up: the counts shown in the OTHER filters must stay in sync with
// whatever's currently selected), not just once at init(), so a user's
// active filter surviving a repopulate is load-bearing, not just defensive.
// $select.val(array) on a multi-select silently ignores any previous value no
// longer present in the fresh option list and otherwise re-selects the rest,
// so this works unchanged for both single- and multi-select without a
// special case -- EXCEPT when the previously-selected value's own count has
// dropped to 0: getReviewRiskFilterOptions() (includes/api.php) omits
// zero-count options entirely (the page's deliberate zero-count-hiding
// deviation from design-system.md §5's default), so that option no longer
// exists to re-select and the selection is silently dropped, same as any
// other option that stopped being valid. This is the intended faceted-search
// behaviour the product owner asked for, not a bug: if the other active
// filters truly leave zero matching risks for a given user/team/level,
// that value can no longer meaningfully stay "selected".
//
// srSelect (multiselect/search follow-up): pass true for the three toolbar
// filter dropdowns (User/Team/Risk Level -- now `multiple` native
// <select>s, includes/display.php) to wrap/refresh them as a shared
// sr-select widget (design-system.md §5) instead of a plain <select>. Two
// effects:
//   1. The placeholder <option value=""> is omitted entirely. A multi-select
//      already reads "nothing picked" as its data-placeholder text
//      (srSelectRender()'s isMulti branch in sr-select.js), so an explicit
//      "All Users" row would be a redundant, tickable option sitting next
//      to the real values -- not what the shipped precedent does
//      (compliance-define-tests.js's Framework/Family filters carry no such
//      option either; see design-system.md §5).
//   2. srSelectEnhance() is called on first use (builds the widget chrome
//      around the native <select>) and srSelectRender() on every later call
//      (srSelectEnhance()'s own `$native.data('srSelect')` guard makes a
//      second enhance() call a silent no-op, so it would never pick up a
//      refreshed option list -- srSelectRender() is what sr-select.js
//      exposes specifically for that case).
// Every other populateSelect() caller (the bulk Reassign Owner/Reassign
// Mitigation Owner/Change Status modal selects) omits this flag and stays a
// plain single-choice native <select> -- these are modal pickers, not
// toolbar filters, and carry no per-option counts, so design-system.md §5's
// "a plain form select with no counts does not need it" guidance applies.
function populateSelect(selector, options, placeholderLabel, srSelect) {
    var $select = $(selector);
    var previousValue = $select.val();
    var html = srSelect ? '' : ('<option value="">' + esc(placeholderLabel) + '</option>');
    (options || []).forEach(function (opt) {
        // data-count (per-option-counts follow-up): sr-select.js's own
        // srSelectRender() already reads this exact attribute name
        // ($option.attr('data-count')) to render the "(12)"-style chip next
        // to each option's label -- same contract compliance-define-tests.js
        // established (see its own data-count call sites). Omitted entirely
        // when the option carries no count (opt.count is undefined/null) --
        // matching sr-select.js's own `count !== undefined && count !== ''`
        // guard for when to show a chip at all -- rather than stamping
        // data-count="undefined".
        var countAttr = (opt.count !== undefined && opt.count !== null) ? ' data-count="' + escAttr(String(opt.count)) + '"' : '';
        html += '<option value="' + escAttr(opt.value) + '"' + countAttr + '>' + esc(opt.name) + '</option>';
    });
    $select.html(html);
    if (previousValue) {
        $select.val(previousValue);
    }
    if (srSelect && typeof window.srSelectEnhance === 'function') {
        if ($select.data('srSelect')) {
            window.srSelectRender($select);
        } else {
            window.srSelectEnhance($select, placeholderLabel);
        }
    }
}

// Current live filter/search/scope state, read straight off the DOM --
// shared by both the DataTable's own `data: function (d)` (POST payload,
// below in the `ajax:` config) and loadFilterOptions() (GET query string,
// just below) so there is exactly one place that knows how to read "what's
// currently selected/typed/toggled" rather than two independently-
// maintained copies of the same DOM reads that could drift apart.
function buildFilterQueryParams() {
    return {
        action_type: currentActionType,
        my_action_items: currentScope === 'mine' ? '1' : '0',
        status_scope: currentStatusScope,
        due_status: currentDueStatus,
        user_filter: $('#review-risk-user-filter').val() || [],
        team_filter: $('#review-risk-team-filter').val() || [],
        risk_level_filter: $('#review-risk-level-filter').val() || [],
        search: $('#review-risk-search').val() || '',
    };
}

// URL-params follow-up: mirrors buildFilterQueryParams()'s live DOM/var
// state into the URL's query string via history.replaceState() -- NEVER
// pushState(), which would spam the browser's back/forward history on
// every keystroke/click; replaceState() still gives full bookmark/share/
// reload support, which is the actual ask. Array-valued params (user_
// filter/team_filter/risk_level_filter) are written with
// repeated `key[]=value` entries -- the same bracket convention jQuery's
// own $.param() (and so $.ajax's own `data:` serialization, used
// elsewhere on this page for the identical param names) produces for an
// array value, so the URL's own shape matches what this page already
// sends over the wire. Only NON-default/non-empty values are written --
// a bookmarked no-filters URL stays exactly '/management/review_risk.php'
// with no query string at all, rather than a full param dump -- so
// parseUrlFilterParams() (below) and this function must agree on what
// "default" means for every key. Called from every call site that already
// calls loadFilterOptions()/table.ajax.reload() on a control change (see
// init()'s event handlers below), alongside those existing calls, not
// instead of them.
function syncUrlParams() {
    var filters = buildFilterQueryParams();
    var params = new URLSearchParams();

    if (filters.status_scope && filters.status_scope !== 'open') {
        params.set('status_scope', filters.status_scope);
    }
    if (filters.action_type && filters.action_type !== 'all') {
        params.set('action_type', filters.action_type);
    }
    if (filters.my_action_items !== '1') {
        params.set('my_action_items', filters.my_action_items);
    }
    if (filters.due_status && filters.due_status !== 'all') {
        params.set('due_status', filters.due_status);
    }
    ['user_filter', 'team_filter', 'risk_level_filter'].forEach(function (key) {
        (filters[key] || []).forEach(function (val) {
            params.append(key + '[]', val);
        });
    });
    if (filters.search) {
        params.set('search', filters.search);
    }

    var qs = params.toString();
    var newUrl = window.location.pathname + (qs ? ('?' + qs) : '') + window.location.hash;
    history.replaceState(null, '', newUrl);
}

// URL-params follow-up: the read-side counterpart to syncUrlParams()
// (above) -- parses the page's OWN initial window.location.search (NOT
// re-invoked on later changes; init() calls this exactly once, at the
// very top, before seeding any control's initial state) back into the
// same shape buildFilterQueryParams() returns. Reads the SAME
// `key[]=a&key[]=b` bracket convention syncUrlParams() writes, via
// URLSearchParams's own repeated-getAll() support for that convention.
// status_scope/action_type/my_action_items are validated against the
// same allowlists the backend already enforces
// (getReviewRiskDatatableResponse(), includes/api.php) -- an invalid or
// unrecognized value in the URL (hand-edited, stale, or from a future
// version) is silently dropped rather than applied, so the caller falls
// back to this file's normal defaults instead of erroring. Only keys the
// URL actually carried a recognized value for are present on the
// returned object -- callers check `if (result.xyz)` rather than relying
// on an explicit "not present" sentinel.
function parseUrlFilterParams() {
    var params = new URLSearchParams(window.location.search);
    var result = {};

    var statusScope = params.get('status_scope');
    if (['all', 'open', 'closed'].indexOf(statusScope) !== -1) {
        result.status_scope = statusScope;
    }

    var actionType = params.get('action_type');
    if (['all', 'mitigation', 'review'].indexOf(actionType) !== -1) {
        result.action_type = actionType;
    }

    var myActionItems = params.get('my_action_items');
    if (myActionItems === '0' || myActionItems === '1') {
        result.my_action_items = myActionItems;
    }

    var dueStatus = params.get('due_status');
    if (['all', 'unreviewed', 'past_due', 'due_soon'].indexOf(dueStatus) !== -1) {
        result.due_status = dueStatus;
    }

    ['user_filter', 'team_filter', 'risk_level_filter'].forEach(function (key) {
        var vals = params.getAll(key + '[]');
        if (vals.length) {
            result[key] = vals;
        }
    });

    var search = params.get('search');
    if (search) {
        result.search = search;
    }

    return result;
}

// Per-option-counts + cross-filter-reactivity follow-up: loadFilterOptions()
// now takes the caller's current filter state (defaulting to whatever
// buildFilterQueryParams() reads off the DOM right now, when the caller
// doesn't already have a fresher copy in hand) and sends it to
// getReviewRiskFilterOptions() (includes/api.php) as GET query params, so
// the counts that come back are faceted against every OTHER active filter --
// see that endpoint's own docblock for the exact "which filters apply to
// which dimension's counts" rule. Previously this only ran once, at init(),
// with no params at all; it's now re-invoked (see the init() event handlers
// below) on every filter-select change, action-type chip click, scope
// toggle click, and (debounced) search-box keystroke, alongside -- not
// instead of -- the existing table.ajax.reload() each of those already
// triggers.
function loadFilterOptions(filterParams) {
    var params = filterParams || buildFilterQueryParams();
    // Out-of-order-response guard: this function is re-invoked on every
    // filter-select change, action-type chip click, scope toggle click, and
    // search keystroke WITHOUT waiting for a prior in-flight call to resolve
    // (by design -- see this function's own docblock above), so nothing
    // stops a slower EARLIER request's response from arriving AFTER a
    // faster LATER one. Without this guard the earlier (now-stale) response
    // wins the race and silently overwrites the dropdowns with the WRONG
    // filter state's counts -- confirmed live: toggling the "All Items"
    // scope chip fires its own loadFilterOptions() call that can lose this
    // race against the page's initial "My Action Items"-scoped load still
    // in flight, leaving the Team/Owner/Risk-Level selects populated from
    // the (zero-match) "My Action Items" counts instead -- e.g. reachable
    // via `openSecondaryFiltersPanel()` + `setShowMyActionItems(false)` in
    // tests/web-e2e/src/specs/risk-management/review-risk-2.spec.ts's
    // SCENARIO-10, which saw an empty Team filter as a result. Only the
    // response matching the MOST RECENTLY issued request is applied.
    var requestSeq = ++filterOptionsRequestSeq;
    // dataType: 'json' explicitly, rather than leaving jQuery to auto-detect
    // from the response Content-Type header -- belt-and-suspenders alongside
    // getReviewRiskFilterOptions() (includes/api.php) now setting that
    // header itself; without EITHER, `opts` below is the raw JSON text
    // string, not a parsed object, and every populateSelect() call below
    // silently gets `[]` (confirmed live against simplerisk-dev).
    return $.ajax({ url: BASE_URL + '/api/v2/risk_management/review_risk/filter_options', method: 'GET', dataType: 'json', data: params }).done(function (opts) {
        if (requestSeq !== filterOptionsRequestSeq) {
            return; // A newer loadFilterOptions() call has since been issued; this response is stale.
        }
        filterOptionsCache = opts;
        populateSelect('#review-risk-user-filter', (opts.users || []).map(nameOption), L('AllUsers'), true);
        populateSelect('#review-risk-team-filter', (opts.teams || []).map(nameOption), L('AllTeams'), true);
        populateSelect('#review-risk-level-filter', (opts.levels || []).map(riskLevelOption), L('AllRiskLevels'), true);

        // Task 13: apply the current user's saved columns-picker state (added
        // to getReviewRiskFilterOptions(), includes/api.php). Applied only on
        // the FIRST successful load (filterOptionsInitialized guard, below) --
        // this saved state doesn't change with the caller's filter/search/
        // scope state, and re-running renderColpanel() on every keystroke in
        // the search box would rebuild (and steal focus/scroll from) an open
        // colpanel for no reason. The datatable response itself only carries
        // row data, not the caller's own settings, so this first fetch is the
        // only place it can come from. Only a pair whose name is still a real
        // toggleable column (the data-col keys -- 'risk_score'/'responsible'/
        // 'team', not the DataTable's internal 'calculated_risk'/'owner'/
        // 'team' column names) is applied -- a stale saved name is silently
        // ignored. No same-tick race to guard against here (Task 13 fix):
        // applyColumnVisibility() is a plain jQuery data-col selector, not a
        // DataTables API call, so it works whether or not `table` has been
        // built yet -- any <td> not yet created gets the current
        // columnVisible state directly from createdCell() (columns: config
        // below) when DataTables creates it.
        if (filterOptionsInitialized) {
            return;
        }
        filterOptionsInitialized = true;

        // URL-params follow-up: seed the three secondary-filter <select>s'
        // initial selection from the page's own URL (parseUrlFilterParams(),
        // stashed by init() into initialUrlFilters before this fetch was
        // even issued) now that populateSelect() (above) has just given each
        // of them real <option> elements to select among -- a plain
        // `$select.val([...])` earlier, against an empty <select>, would
        // have been a silent no-op (no matching <option>s yet to select).
        // Runs exactly once (inside this same filterOptionsInitialized
        // guard): every LATER loadFilterOptions() call already has the
        // right values selected, and populateSelect()'s own
        // previousValue-capture/restore (see that function, above) carries
        // them through every subsequent <option> rebuild on its own.
        if (initialUrlFilters) {
            var hasInitialSecondaryFilters = false;
            [
                ['user_filter', '#review-risk-user-filter'],
                ['team_filter', '#review-risk-team-filter'],
                ['risk_level_filter', '#review-risk-level-filter'],
            ].forEach(function (pair) {
                var vals = initialUrlFilters[pair[0]];
                if (vals && vals.length) {
                    hasInitialSecondaryFilters = true;
                    $(pair[1]).val(vals);
                }
            });
            if (hasInitialSecondaryFilters) {
                // Re-render each enhanced sr-select widget so its closed-
                // state UI (the checkbox list / summary label) reflects the
                // native <select> values just set -- matching every other
                // srSelectRender() call in this file (populateSelect()
                // itself, the filters-clear handler below).
                $('#review-risk-user-filter, #review-risk-team-filter, #review-risk-level-filter').each(function () {
                    var $select = $(this);
                    if ($select.data('srSelect') && typeof window.srSelectRender === 'function') {
                        window.srSelectRender($select);
                    }
                });
                syncFiltersCount();
                // Task 8 follow-up: this used to conditionally re-fire the
                // DataTable's own first `ajax` call here, because that first
                // call previously fired synchronously from init()'s
                // `table = $(...).DataTable(...)` construction -- before
                // these four <select>s had any <option>s to read a value
                // from, so it always went out unfiltered. The DataTable is
                // now built later in THIS SAME callback (buildReviewRiskDataTable(),
                // below) -- after the four <select>s above already have the
                // correct URL-seeded values selected -- so its own first ajax
                // call already carries them; no separate reload is needed
                // (or possible: `table` doesn't exist yet at this point
                // either way).
            }
        }

        // Customization Extra follow-up: resolve the site's active fields
        // FIRST -- everything below (saved-visibility application, the
        // `columns:` config's custom-field entries built by
        // buildReviewRiskDataTable(), renderColpanel(), applyColumnVisibility())
        // must see the FINAL COLUMN_GROUPS/TOGGLE_COLUMNS, not the static
        // default.
        applyActiveColumns(opts.active_columns);

        if (opts.column_settings && opts.column_settings.columns) {
            opts.column_settings.columns.forEach(function (pair) {
                if (TOGGLE_COLUMNS.indexOf(pair[0]) !== -1) {
                    columnVisible[pair[0]] = pair[1] === '1';
                }
            });
        }
        // ColReorder follow-up: stash the saved column order (applied by
        // buildReviewRiskDataTable(), below, once `table` exists -- see that
        // function's own comment for why the DataTable's construction itself
        // now lives there instead of synchronously in init()).
        if (opts.column_settings && Array.isArray(opts.column_settings.order)) {
            savedColumnOrder = opts.column_settings.order;
        }

        // Task 8 (Customization Extra dynamic columns): build/construct the
        // DataTable HERE -- the first (and only) time this callback runs
        // (filterOptionsInitialized guard, above) -- now that COLUMN_GROUPS/
        // TOGGLE_COLUMNS/columnVisible/savedColumnOrder are all in their
        // FINAL form. See buildReviewRiskDataTable()'s own header comment
        // for the full race-condition rationale.
        buildReviewRiskDataTable();

        renderColpanel();
        applyColumnVisibility();
    }).fail(function () {
        // Finding 1 (final whole-branch review): BEFORE Task 8 relocated the
        // DataTable's construction into the `.done()` handler above, a failed
        // filter-options fetch cost the viewer only the three secondary-filter
        // <select>s' <option> lists -- the grid itself was built synchronously
        // in init() and worked normally. After that relocation, with no
        // `.fail()` anywhere on this request, a single transient failure left
        // the page with a bare static <table>: no rows, no paging, no search,
        // no sort, no bulk bar. This handler restores the old failure
        // behaviour by building the grid anyway, from defaults:
        //   - no saved column_settings -> this file's built-in columnVisible
        //     defaults (everything hidden except Owner) and no saved order;
        //   - active_columns recovered from the page's own thead
        //     (activeColumnsFromDom(), above) rather than skipped, so an
        //     Extra-active site's custom-field <th>s still get matching
        //     `columns:` entries and DataTables' positional column contract
        //     holds.
        // filterOptionsInitialized is deliberately NOT set here: a later
        // loadFilterOptions() call (the user changes a filter, which re-runs
        // this request) should still get its one shot at applying the real
        // saved settings/option lists. buildReviewRiskDataTable()'s own
        // `tableBuilt` guard is what keeps that later success from
        // re-initialising the table -- see that flag's declaration.
        if (tableBuilt) {
            return;
        }
        applyActiveColumns(activeColumnsFromDom());
        buildReviewRiskDataTable();
        renderColpanel();
        applyColumnVisibility();
    });
}

// Filters-toggle count badge + active-state styling -- matches
// governance-exceptions.js's/governance-documents.js's/
// compliance-define-tests.js's identical syncFilterCount() shape
// (#exceptions-filters-count/#document-program-filters-count/
// #define-tests-filters-count): `.prop('hidden', ...)` on the count span
// (Task 8's markup uses the native `hidden` attribute, not a d-none class,
// for #review-risk-filters-count) and `.has-filters` -- NOT an invented
// `.active-filters` class, which has no CSS rule anywhere in the bundle --
// on the toggle button, per _tables.scss's `.sr-qf-toggle.has-filters` rule.
function syncFiltersCount() {
    // (multiselect/search follow-up): these three are now `multiple`
    // <select>s, so $(sel).val() returns an ARRAY -- [] (empty) when nothing
    // is picked, never '' or null -- and a bare truthiness check on an array
    // is always true, even an empty one. That silently counted every
    // dropdown as "active" regardless of selection. Check .length instead.
    var active = ['#review-risk-user-filter', '#review-risk-team-filter', '#review-risk-level-filter']
        .filter(function (sel) { return ($(sel).val() || []).length > 0; }).length;
    // Insights-band follow-up: a KPI tile's drill-through link (and a
    // bookmarked/shared URL generally) can land here with one or more of the
    // four chip-groups already off their default -- e.g. the "Needs
    // Mitigation" tile sets action_type=mitigation. Without this, a viewer
    // who arrived that way had no single obvious way back to the default
    // view short of clicking each chip's own "All"/"Open"/"My Action Items"
    // option by hand. Folding these into the SAME active-filter count/Clear
    // affordance (rather than a separate, KPI-specific "reset" control)
    // keeps "Clear Filters" meaning exactly what it says regardless of
    // which control put the page into a non-default state.
    var kpiChipActive = false;
    if (currentActionType !== 'all') {
        active++;
        kpiChipActive = true;
    }
    if (currentScope !== 'mine') {
        active++;
        kpiChipActive = true;
    }
    if (currentStatusScope !== 'open') {
        active++;
        kpiChipActive = true;
    }
    if (currentDueStatus !== 'all') {
        active++;
        kpiChipActive = true;
    }
    // The My Action Items tile's own drill-through carries
    // my_action_items=1 -- already currentScope's default ('mine') -- so
    // none of the four checks above catch it and the button would stay
    // hidden despite the viewer having explicitly followed a KPI-tile link.
    // initialUrlHadInsightsFilter (set once in init(), cleared on the first
    // real chip interaction -- see setActionType()/setScope()/
    // setStatusScope()/setDueStatus()) covers exactly this gap without
    // double-counting when a chip ALSO differs from default for its own
    // reason.
    if (!kpiChipActive && initialUrlHadInsightsFilter) {
        active++;
    }
    $('#review-risk-filters-count').text(active ? String(active) : '').prop('hidden', active === 0);
    $('#review-risk-filters-toggle').toggleClass('has-filters', active > 0);
    $('#review-risk-filters-clear').toggleClass('d-none', active === 0);
}

// ---- Bulk-select / bulk bar (Task 14; design-system.md §6: "bulk-select ->
// bulk bar") ----
//
// #review-risk-bulk-bar / #review-risk-bulk-clear / #review-risk-select-all
// / #review-risk-bulk-count are Task 8's real static markup
// (display_review_risk(), includes/display.php ~line 8065-8108) -- verified
// against source rather than trusting the brief, per this plan's running
// pattern-consistency note. #review-risk-bulk-bar already carries the
// default-hidden 'd-none' class in that markup; the individual bulk-action
// buttons inside it (#review-risk-bulk-comment etc.) are already
// permission-gated server-side (if-guarded echo per button) -- their click
// handlers are Tasks 15-19's job, not this one's.
//
// Shape matches the shipped bulk-bar implementations elsewhere in the app
// (governance-exceptions.js's updateBulkBar()/syncCheckboxes(),
// governance-documents.js's identical twin) rather than the brief's sample,
// which the brief itself hardcoded two raw English literals ('risk
// selected'/'risks selected') for -- a hardcoded-English violation per this
// repo's CLAUDE.md ("Every user-facing string ... must render through a
// language-file lookup"). The reusable 'NSelected' => '{n} selected' key
// already exists in lang.en.php and is exactly what every other bulk bar in
// the codebase uses (governance-exceptions.js, governance-documents.js,
// self-assessment.js, compliance-initiate-audits.js, compliance-define-
// tests.js, governance-frameworks.js) -- reused here rather than adding a
// near-duplicate key.
//
// .sr-table-toolbar has no default 'd-none' and no `!important` display
// rule (_tables.scss: `display: flex` unconditionally), so a plain jQuery
// .hide()/.show() (inline style) toggling it composes cleanly -- same
// mechanism governance-exceptions.js's/governance-documents.js's own
// updateBulkBar() use for their own toolbar element.
function syncBulkBar() {
    var count = Object.keys(selectedIds).length;
    if (count > 0) {
        $('.sr-table-toolbar').hide();
        $('#review-risk-bulk-bar').removeClass('d-none');
        $('#review-risk-bulk-count').text(String(L('NSelected')).replace('{n}', count));
    } else {
        $('#review-risk-bulk-bar').addClass('d-none');
        $('.sr-table-toolbar').show();
    }
    // "Select all N": the header checkbox only ever reaches the current
    // page (serverSide -- see syncSelectAllState()'s own comment). Offer to
    // escalate to every row the current filter/search matches, across every
    // page, while there is more of it than is currently selected. Hidden
    // once selectAllFiltered is true regardless of count -- see that var's
    // own comment for why a later manual deselection doesn't re-offer it.
    $('#review-risk-select-all-filtered')
        .toggleClass('d-none', selectAllFiltered || !(count > 0 && lastRecordsFiltered > count))
        .text(String(L('SelectAllN')).replace('{n}', lastRecordsFiltered));
    syncSelectAllState();
}

// Reflects the actual per-row checkbox states (already synced by the
// handlers below) onto the header checkbox's tri-state: fully checked when
// every currently-rendered row is selected, indeterminate when some but not
// all are, unchecked otherwise. #review_risk_table is `serverSide` --
// unlike governance-exceptions.js's hand-rendered/client-filtered table,
// every '.sr-row-check' in the DOM at any moment belongs to the CURRENT
// PAGE only (DataTables never hides rows client-side here), so a plain
// unfiltered selector is correct -- no ':visible' filter needed.
function syncSelectAllState() {
    var $checks = $('.sr-row-check');
    var total = $checks.length;
    var checked = $checks.filter(':checked').length;
    var $all = $('#review-risk-select-all');
    $all.prop('checked', total > 0 && checked === total);
    $all.prop('indeterminate', checked > 0 && checked < total);
}

// ---- Bulk Add Comment (Task 15; design-system.md's bulk-bar -> modal
// pattern; first of Tasks 15-19's bulk actions) ----
//
// setBusy()/showModalError()/clearModalError() are NOT true shared globals --
// confirmed by grep, they only exist today as governance-frameworks.js's own
// page-local copies (that file's own header comment: "page-specific JS files
// each define their own copies of these rather than relying on a true shared
// global"). Reproduced here rather than invented from scratch, matching that
// page's exact shape: setBusy() toggles disabled + '.is-busy'; showModalError()
// writes into '.sr-modal-inline-error' via .text() (never re-escaped -- plain
// textContent) AND fires a toast so the failure isn't lost once the modal
// closes; clearModalError() blanks the banner. showAlertFromMessage() itself
// IS a true global (js/simplerisk/alert-helper.js, emitted unconditionally by
// get_alert() in includes/alerts.php -- not in this page's own
// render_header_and_sidebar() asset list, but loaded on every page
// regardless, same as L()).
function setBusy($btn, busy) {
    $btn.prop('disabled', busy).toggleClass('is-busy', busy);
}

function showModalError($modal, message) {
    var text = message || L('RequestFailed') || '';
    $modal.find('.sr-modal-inline-error').text(text).removeClass('d-none');
    showAlertFromMessage(text, false);
}

function clearModalError($modal) {
    $modal.find('.sr-modal-inline-error').addClass('d-none').text('');
}

// Minimal '%s' placeholder substitution. This task's brief called a global
// `sprintf()` -- confirmed by grep not to exist anywhere in the bundle (no
// sprintf-js vendor package, no definition on any page). lang.en.php's
// Bulk*Title / BulkAction* keys (pre-added ahead of this task, for Tasks
// 15-19) use printf-style '%s' placeholders rather than this file's own
// '{n}'-token convention (see syncBulkBar()'s L('NSelected').replace('{n}', ...)
// above) -- matched here rather than changing the already-shipped lang keys.
function formatBulkMessage(str, args) {
    var i = 0;
    return str.replace(/%s/g, function () {
        return i < args.length ? args[i++] : '%s';
    });
}

// review-risk.js's `selectedIds` keys are the RAW risks.id (numbers,
// stringified by Object.keys()); every risk endpoint this file calls --
// batch or single -- takes the DISPLAY id (raw + 1000) instead. parseInt()
// first: a bare `id + 1000` on a string id would string-concatenate
// ('993979' + 1000 === '9939791000') rather than add.
function toDisplayIds(ids) {
    return ids.map(function (id) { return parseInt(id, 10) + 1000; });
}

// Shared completion handling for the four single-request bulk-action
// endpoints below (batch-comment/batch-reassign-owner/
// batch-reassign-mitigation-owner/batch-close). Each answers 200 with
// {processed, denied, total, truncated} in `data` when at least one id was
// processed -- `denied` counts ids the per-id check_access_for_risk() call
// skipped (Team Separation), which is why a batch that partially succeeds
// still reports itself as a partial success rather than an all-or-nothing
// failure.
// `successMessage` defaults to the shared "%s risks updated." phrasing;
// Close Risk passes its own L('RisksClosed') instead, matching what its
// pre-batch handler showed on a full success.
function bulkActionRequestDone($btn, $modal, ids, successMessage) {
    return function (response) {
        setBusy($btn, false);
        $modal.modal('hide');
        var data = (response && response.data) || {};
        var denied = data.denied || 0;
        if (denied > 0) {
            showAlertFromMessage(formatBulkMessage(L('BulkActionPartialSuccess'), [ids.length - denied, ids.length]), false);
        } else {
            showAlertFromMessage(successMessage || formatBulkMessage(L('BulkActionSuccess'), [ids.length]), true);
        }
        selectedIds = {};
        selectAllFiltered = false;
        table.ajax.reload();
    };
}

// The complement of bulkActionRequestDone() above -- reached when the whole
// batch is refused (e.g. the caller holds none of the plain session
// permission the endpoint requires, answered as a whole-request 403; see
// e.g. saveCommentBatch()'s docblock, includes/api.php) or the request
// itself fails (network error, 500). xhr.responseJSON.status_message is
// already escaped server-side (get_alert(true) / $escaper->escapeHtml()) --
// CLAUDE.md's double-escaping rule -- so it is shown as-is, never re-escaped
// here. Falls back to the same "0 of N" phrasing bulkActionRequestDone()
// would show for a fully-denied batch when the response carries no JSON body
// at all (a network-level failure never reaches the server).
function bulkActionRequestFail($btn, $modal, ids) {
    return function (xhr) {
        setBusy($btn, false);
        $modal.modal('hide');
        if (xhr.responseJSON && xhr.responseJSON.status_message) {
            showAlertFromMessage(xhr.responseJSON.status_message, false);
        } else {
            showAlertFromMessage(formatBulkMessage(L('BulkActionPartialSuccess'), [0, ids.length]), false);
        }
        selectedIds = {};
        selectAllFiltered = false;
        table.ajax.reload();
    };
}

// Clears a stale error banner every time ANY '.sr-modal' on this page opens
// -- covers every bulk-action modal Tasks 16-19 add too, not just this one.
// Matches governance-frameworks.js's identical page-wide delegate (added
// there post-review, after a modal-specific handler forgot to clear a prior
// banner on reopen).
$(document).on('show.bs.modal', '.sr-modal', function () {
    clearModalError($(this));
});

$('#review-risk-bulk-comment').on('click', function () {
    var count = Object.keys(selectedIds).length;
    $('#review-risk-comment-modal-title').text(formatBulkMessage(L('BulkAddCommentTitle'), [count]));
    $('#review-risk-comment-text').val('');
    $('#review-risk-comment-modal').modal('show');
});

// POST /api/v2/risks/batch-comment -> saveCommentBatch() (includes/api.php;
// routed in api/v2/index.php) -- ONE request carrying every selected risk's
// DISPLAY id (raw risks.id + 1000, same conversion the single-risk endpoint
// this replaced always required) plus the comment text, replacing the
// previous "loop the single-risk endpoint once per selected id" client
// behavior. Gated server-side on $_SESSION['comment_risk_management'] (the
// same permission that gates #review-risk-bulk-comment's own rendering,
// includes/display.php) AND a PER-ID check_access_for_risk($id) that runs
// for EVERY id in the batch, exactly like the single-risk endpoint it
// replaces -- an id that fails either check is skipped server-side and
// counted in the response's `denied`, never silently dropped or treated as
// success.
$('#review-risk-comment-submit').on('click', function () {
    var $btn = $(this);
    var $modal = $('#review-risk-comment-modal');
    var comment = $('#review-risk-comment-text').val();
    if (!comment) {
        showModalError($modal, L('CommentRiskRequired'));
        return;
    }
    var ids = Object.keys(selectedIds);
    if (!ids.length) {
        $modal.modal('hide');
        return;
    }
    setBusy($btn, true);
    $.post({
        url: BASE_URL + '/api/v2/risks/batch-comment',
        data: { risk_ids: toDisplayIds(ids), comment: comment },
        headers: csrfHeaders(),
    })
        .done(bulkActionRequestDone($btn, $modal, ids))
        .fail(bulkActionRequestFail($btn, $modal, ids));
});

// ---- Bulk Reassign Risk Owner (Task 16) ----
//
// #review-risk-bulk-reassign-owner's own rendering (includes/display.php) is
// gated on `$_SESSION['modify_risks']` -- the same permission
// updateRisk()/update_risk() (includes/api.php, includes/functions.php)
// enforce server-side for the PATCH below.
$('#review-risk-bulk-reassign-owner').on('click', function () {
    var count = Object.keys(selectedIds).length;
    $('#review-risk-reassign-owner-modal-title').text(formatBulkMessage(L('BulkReassignRiskOwnerTitle'), [count]));
    // filterOptionsCache.all_users is the RAW {value: <uid>, name: <display
    // name>} list from loadFilterOptions() (Task 11) -- NOT the nameOption()-
    // remapped copy the quickfilters' own User <select> uses (that one swaps
    // `value` for the display NAME so the datatable's server-side name match
    // can match against it; see nameOption()'s comment above), and NOT
    // filterOptionsCache.users either -- that list is narrowed to users who
    // currently hold one of the 6 merged roles on a risk the active filters
    // show (the 'All users' secondary filter's own option list), which is
    // the wrong candidate set for "who can I reassign this risk's Owner to"
    // (design decision, user request follow-up: every enabled, Org-
    // Hierarchy-scoped user should be a reassignment candidate, not just
    // users already holding some role on a currently-visible risk). This
    // modal needs the real numeric uid, since that's what PATCH /risks/{id}'s
    // `owner` param expects (update_risk(), includes/functions.php: `$owner =
    // (int)get_param("post", "owner", false)`), so the raw cached list is
    // passed straight through unmapped.
    populateSelect('#review-risk-reassign-owner-select', filterOptionsCache ? filterOptionsCache.all_users : [], null);
    $('#review-risk-reassign-owner-select').val('');
    $('#review-risk-reassign-owner-modal').modal('show');
});

// POST /api/v2/risks/batch-reassign-owner -> updateRiskOwnerBatch()
// (includes/api.php) -- ONE request carrying every selected risk's DISPLAY id
// (raw risks.id + 1000) plus the new owner, replacing the previous "loop a
// PATCH /risks/{id} once per selected id" client behavior.
// RiskOwnerPatchScoringRegressionTest (tests/api/) still applies: this
// endpoint calls update_risk($id, true) directly rather than updateRisk()'s
// own PATCH handler, so it never touches update_risk_scoring() at all for
// this owner-only action. Gated server-side on `$_SESSION['modify_risks']`
// (same permission updateRisk()/update_risk() enforce) AND a PER-ID
// check_access_for_risk($id) that runs for EVERY id in the batch -- an id
// that fails either check is skipped server-side and counted in the
// response's `denied`, never silently dropped or treated as success.
$('#review-risk-reassign-owner-submit').on('click', function () {
    var $btn = $(this);
    var $modal = $('#review-risk-reassign-owner-modal');
    var owner = $('#review-risk-reassign-owner-select').val();
    if (!owner) {
        showModalError($modal, L('ThisFieldIsRequired'));
        return;
    }
    var ids = Object.keys(selectedIds);
    if (!ids.length) {
        $modal.modal('hide');
        return;
    }
    setBusy($btn, true);
    $.post({
        url: BASE_URL + '/api/v2/risks/batch-reassign-owner',
        data: { risk_ids: toDisplayIds(ids), owner: owner },
        headers: csrfHeaders(),
    })
        .done(bulkActionRequestDone($btn, $modal, ids))
        .fail(bulkActionRequestFail($btn, $modal, ids));
});

// ---- Bulk Reassign Mitigation Owner (Task 17) ----
//
// #review-risk-bulk-reassign-mitigation-owner's own rendering
// (includes/display.php) is gated on `$_SESSION['plan_mitigations']` -- the
// same permission saveMitigation() (includes/api.php, PATCH
// /risks/{id}/mitigations) enforces server-side for the PATCH below.
//
// Eligibility is the OPPOSITE of a naive reading of this task's brief. The
// brief's own sample filters selectedIds to rows where `needs_mitigation` is
// true, but get_risks()'s sort_order=22 query (includes/functions.php)
// defines `needs_mitigation` as `(b.mitigation_id = 0)` -- i.e. TRUE means NO
// mitigation record exists yet. saveMitigation() branches on
// `$risk[0]['mitigation_id']`: when it's falsy it calls submit_mitigation()
// and CREATES a new, mostly-empty mitigation record rather than updating an
// owner on an existing one. Filtering on needs_mitigation === true would
// therefore target exactly the rows with nothing to reassign, and silently
// create throwaway mitigation rows for any that got through. A row in this
// queue with needs_mitigation === false already has a mitigation record (the
// row is only here at all because needs_mitigation OR needs_review is true,
// per getReviewRiskDatatableResponse()'s `continue` guard), so THAT is the
// eligible set. Confirmed against the live schema/handlers before writing
// this, not merely inferred from the brief's prose.
$('#review-risk-bulk-reassign-mitigation-owner').on('click', function () {
    var eligibleIds = Object.keys(selectedIds).filter(function (id) {
        return rowCache[id] && !rowCache[id].needs_mitigation;
    });
    if (eligibleIds.length < Object.keys(selectedIds).length) {
        showAlertFromMessage(L('SomeRowsSkippedNoMitigation'), false);
    }
    $('#review-risk-reassign-mitigation-owner-modal-title').text(formatBulkMessage(L('BulkReassignMitigationOwnerTitle'), [eligibleIds.length]));
    $('#review-risk-reassign-mitigation-owner-modal').data('eligible-ids', eligibleIds);
    // Same raw {value: <uid>, name: <display name>} list #review-risk-bulk-
    // reassign-owner's trigger handler above uses -- mitigation_owner is a
    // plain user uid too
    // (saveMitigation() -> update_mitigation()/submit_mitigation(): `(int)$post['mitigation_owner']`).
    populateSelect('#review-risk-reassign-mitigation-owner-select', filterOptionsCache ? filterOptionsCache.all_users : [], null);
    $('#review-risk-reassign-mitigation-owner-select').val('');
    $('#review-risk-reassign-mitigation-owner-modal').modal('show');
});

// POST /api/v2/risks/batch-reassign-mitigation-owner ->
// saveMitigationOwnerBatch() (includes/api.php) -- ONE request carrying every
// eligible risk's DISPLAY id (raw risks.id + 1000) plus the new mitigation
// owner, replacing the previous "loop a PATCH /risks/{id}/mitigations once
// per eligible id" client behavior. Gated server-side on
// `$_SESSION['plan_mitigations']` (same permission saveMitigation()
// enforces) AND a PER-ID check_access_for_risk($id) that runs for EVERY id
// in the batch -- an id that fails either check is skipped server-side and
// counted in the response's `denied`, never silently dropped or treated as
// success.
$('#review-risk-reassign-mitigation-owner-submit').on('click', function () {
    var $btn = $(this);
    var $modal = $('#review-risk-reassign-mitigation-owner-modal');
    var owner = $('#review-risk-reassign-mitigation-owner-select').val();
    var ids = $modal.data('eligible-ids') || [];
    if (!owner) {
        showModalError($modal, L('ThisFieldIsRequired'));
        return;
    }
    if (!ids.length) {
        $modal.modal('hide');
        return;
    }
    setBusy($btn, true);
    $.post({
        url: BASE_URL + '/api/v2/risks/batch-reassign-mitigation-owner',
        data: { risk_ids: toDisplayIds(ids), mitigation_owner: owner },
        headers: csrfHeaders(),
    })
        .done(bulkActionRequestDone($btn, $modal, ids))
        .fail(bulkActionRequestFail($btn, $modal, ids));
});

// ---- Bulk Change Status (Task 18) ----
//
// #review-risk-bulk-change-status's own rendering (includes/display.php) is
// gated on `$_SESSION['modify_risks']` -- the same permission
// updateStatusBatch() (includes/api.php) enforces server-side via
// `has_permission("modify_risks") && check_access_for_risk($id)` for the
// POST below.
$('#review-risk-bulk-change-status').on('click', function () {
    var count = Object.keys(selectedIds).length;
    $('#review-risk-status-modal-title').text(formatBulkMessage(L('BulkChangeStatusTitle'), [count]));
    // filterOptionsCache.statuses is the raw `status` table
    // ({value, name} already -- see getReviewRiskFilterOptions()'s comment,
    // includes/api.php), so it needs no nameOption()-style remap the way
    // owners/teams do elsewhere on this page.
    populateSelect('#review-risk-status-select', filterOptionsCache ? filterOptionsCache.statuses : [], null);
    $('#review-risk-status-select').val('');
    $('#review-risk-status-modal').modal('show');
});

// POST /api/v2/risks/batch-update-status -> updateStatusBatch()
// (includes/api.php) -- ONE request carrying every selected risk's DISPLAY id
// (raw risks.id + 1000) plus the new status, replacing the previous "loop
// POST /management/risk/updateStatus?id={id} once per selected id" client
// behavior (changeStatusOnOne() via runBulkActionBatched()). `status` is
// sent as `$_POST['status']`, read server-side as `(int)$_POST['status']`
// and resolved to a status name via get_name_by_value("status", $status_id)
// -- a numeric status id (the `status` table's `value` column), not a name
// string. Gated server-side on `$_SESSION['modify_risks']` (same permission
// updateStatusForm() enforces) AND a PER-ID check_access_for_risk($id) that
// runs for EVERY id in the batch -- an id that fails either check is skipped
// server-side and counted in the response's `denied`, never silently dropped
// or treated as success.
$('#review-risk-status-submit').on('click', function () {
    var $btn = $(this);
    var $modal = $('#review-risk-status-modal');
    var statusId = $('#review-risk-status-select').val();
    if (!statusId) {
        showModalError($modal, L('ThisFieldIsRequired'));
        return;
    }
    var ids = Object.keys(selectedIds);
    if (!ids.length) {
        $modal.modal('hide');
        return;
    }
    setBusy($btn, true);
    $.post({
        url: BASE_URL + '/api/v2/risks/batch-update-status',
        data: { risk_ids: toDisplayIds(ids), status: statusId },
        headers: csrfHeaders(),
    })
        .done(bulkActionRequestDone($btn, $modal, ids, L('StatusChanged')))
        .fail(bulkActionRequestFail($btn, $modal, ids));
});

// ---- Bulk Close Risk (Task 19) ----
//
// #review-risk-bulk-close's own rendering (includes/display.php) is gated on
// `$_SESSION['close_risks']` -- the same permission closeriskForm()
// (includes/api.php) enforces server-side via
// `check_permission("close_risks") && check_access_for_risk($id)` for the
// POST below. This is a DEDICATED endpoint, not a reuse of the
// batch-update-status endpoint above: updateStatusForm()/updateStatusBatch()
// are gated on `modify_risks` instead, which would enforce the wrong
// permission for a Close action. This is also a destructive-confirm (§8)
// with no form fields, unlike the status-change modal above -- there's
// nothing to populate on open beyond the title.
$('#review-risk-bulk-close').on('click', function () {
    var count = Object.keys(selectedIds).length;
    $('#review-risk-close-modal-title').text(formatBulkMessage(L('BulkCloseRiskTitle'), [count]));
    // Reset the Reason/Close-Out Information fields on every open -- a
    // second bulk-close later in the same page session must not silently
    // carry over the previous operation's reason/note (the modal element is
    // never removed from the DOM between opens, only hidden).
    $('#review-risk-close-reason').val('');
    $('#review-risk-close-note').val('');
    $('#review-risk-close-modal').modal('show');
});

// A destructive confirm opens with focus on its SAFE action (§8), not the
// destructive verb -- a stray Enter must not close risks, and a keyboard
// user must not have to find the way out of a dialog that opened with the
// red verb under their hands. The modal markup's `autofocus` attribute on
// the Cancel button (includes/display.php) does NOT achieve this: per
// Bootstrap 5's documented behavior, the HTML `autofocus` attribute is only
// processed once at DOM insertion time, while the modal is still
// `display:none` -- it is inert by the time `.modal('show')` later reveals
// the dialog. `shown.bs.modal`, not `show.bs.modal`, is required for the
// same reason: the element is not focusable until it is actually painted.
// Mirrors governance-frameworks.js's identical fix for its own
// destructive-confirm modals (#control--delete/#controls--delete/
// #framework--delete), verified there to work despite Bootstrap 5
// dispatching native events because jQuery's EventHandler.trigger() still
// fires a parallel namespaced jQuery event.
$(document).on('shown.bs.modal', '#review-risk-close-modal', function () {
    $(this).find('[data-bs-dismiss="modal"].btn-dark').first().trigger('focus');
});

// POST /api/v2/risks/batch-close -> closeRiskBatch() (includes/api.php) --
// ONE request carrying every selected risk's DISPLAY id (raw risks.id +
// 1000) plus close_reason/note, replacing the previous "loop
// POST /management/risk/closerisk?id={id} once per selected id" client
// behavior. close_reason/note come from the modal's own Reason/Close-Out
// Information fields (includes/display.php's #review-risk-close-reason/
// #review-risk-close-note, the same create_dropdown('close_reason')/note
// textarea the single-risk Close Risk flow, management/partials/close.php,
// already prompts for) and apply the SAME values to every id in the batch --
// a bulk "select many, act once" action doesn't collect one reason per risk.
// Gated server-side on `close_risks` (same permission closeriskForm()
// enforces) AND a PER-ID check_access_for_risk($id) that runs for EVERY id
// in the batch -- an id that fails either check is skipped server-side and
// counted in the response's `denied`, never silently dropped or treated as
// success.
$('#review-risk-close-submit').on('click', function () {
    var $btn = $(this);
    var $modal = $('#review-risk-close-modal');
    var ids = Object.keys(selectedIds);
    if (!ids.length) {
        $modal.modal('hide');
        return;
    }
    setBusy($btn, true);
    $.post({
        url: BASE_URL + '/api/v2/risks/batch-close',
        data: {
            risk_ids: toDisplayIds(ids),
            close_reason: $('#review-risk-close-reason').val() || '',
            note: $('#review-risk-close-note').val() || '',
        },
        headers: csrfHeaders(),
    })
        .done(bulkActionRequestDone($btn, $modal, ids, L('RisksClosed')))
        .fail(bulkActionRequestFail($btn, $modal, ids));
});

// ---- + Add Risk modal (embedded Submit Risk form) ----
//
// #review-risk-add-btn (includes/display.php) is a plain '#' link, not a
// data-bs-toggle/data-bs-target pair -- matching this page's own convention
// of explicit .modal('show') calls for every other trigger button here (see
// #review-risk-bulk-close etc. above), which also leaves room to reset the
// embedded form on each open below.
//
// #review-risk-add-modal's body is an empty canvas div that
// window.RiskDetailsForm.init() (js/simplerisk/common/risk-details-form.js)
// renders the Cards form into on open, with embedded: true so it emits a
// hidden .save-risk-form affordance instead of its own sticky action bar.
// That affordance's submit handler lives in the SHARED
// js/simplerisk/pages/risk.js (used by the standalone Submit Risk page, Edit
// Risk, and both Compliance embeds too), which we don't want to make aware
// of Review Risk specifically. Instead, the modal itself opts into a
// page-agnostic "stay on this page" contract via
// data-on-save="refresh-and-close" (see #review-risk-add-modal's own
// comment in includes/display.php): on a successful save, risk.js hides
// that modal and fires 'simplerisk:risk-created' on document with the new
// risk_id, rather than doing its normal full-page redirect to view.php.
$('#review-risk-add-btn').on('click', function (e) {
    e.preventDefault();
    $('#review-risk-add-modal').modal('show');
});

// ---- + Add Risk modal: footer actions ----
//
// Fills the empty .modal-footer that includes/display.php ships on this
// modal with the design system's 'left hint + right-aligned actions' row.
//
// The real submit control is the engine's own hidden .save-risk-form button
// -- built by buildEmbeddedSubmitAffordance() in
// js/simplerisk/common/risk-details-form.js when init() is called with
// embedded: true -- which sits inside the rendered <form> (class .tab-data)
// and is never shown. There is NO reset/Clear button for this call site at
// all: the engine's embedded rendering emits only the submit affordance, so
// the [type="reset"] branch below is a no-op here and exists purely so this
// function stays correct if a future embedder does render one.
//
// The real button cannot simply be re-parented into the footer: risk.js's
// delegated .save-risk-form handler resolves the form it submits with
// $this.closest('form'), so a button moved out of the <form> would silently
// submit nothing. (Same for a reset button, whose handler resolves its tab
// with closest('.tab-data').) The footer buttons are therefore proxies that
// forward a click to the real one where it already lives.
//
// Labels and hint text are READ OFF the rendered markup with .text() rather
// than hardcoded, so they stay translated and this file adds no user-facing
// English of its own. .text() in both directions also means a label can
// never be interpreted as markup. window.RiskDetailsForm's embedded
// .save-risk-form affordance is deliberately unlabeled (it's never shown --
// see buildEmbeddedSubmitAffordance() in risk-details-form.js), so the save
// proxy falls back to $lang['SubmitRisk'] (the same key the legacy
// display_add_risk() markup used for this action) when the harvested text
// comes back empty.
//
// Re-runnable by design: it targets whichever .tab-data form is active NOW,
// so calling it again after a template-group switch or a re-render is safe.
// It rebuilds the footer's contents each time rather than appending, so
// reopening the modal or switching tabs can never duplicate the buttons.
function syncAddRiskModalFooter() {

    var $modal = $('#review-risk-add-modal');
    var $footer = $modal.find('#review-risk-add-modal-footer');

    if (!$footer.length) {
        return;
    }

    // window.RiskDetailsForm renders a single '<prefix>-form' (class
    // 'tab-data') for the whole modal -- there is no per-template-group
    // '.tab-pane' the way the legacy display_add_risk() markup had, but this
    // selector still resolves it dynamically by class rather than by a
    // hardcoded form id (the derived id is
    // 'review-risk-add-modal-canvas-form').
    var $pane = $modal.find('.tab-pane.tab-data.active');
    if (!$pane.length) {
        $pane = $modal.find('.tab-data').first();
    }
    if (!$pane.length) {
        $pane = $modal;
    }

    var $realSave = $pane.find('.save-risk-form').first();
    var $realReset = $pane.find('[type="reset"]').first();

    // Nothing to proxy (a consumer/permission combination that renders no
    // action row) -- leave the footer as the shell rendered it.
    if (!$realSave.length && !$realReset.length) {
        return;
    }

    // window.RiskDetailsForm's embedded rendering has no instruction line
    // before the action row (unlike the legacy display_add_risk() markup),
    // so this normally comes back empty -- which is fine, the footer hint
    // simply stays blank. Tagging the exact element found here (rather than
    // letting the stylesheet match "the <p> before .risk-form-actions") keeps
    // the hiding rule honest for any future markup that does render one.
    var $hintSource = $pane.find('.risk-form-actions').prevAll('p').first();
    $hintSource.addClass('review-risk-add-hint-source');
    $footer.find('.sr-modal-hint').text($hintSource.text().trim());

    // Drop any pair built by a previous open/tab-switch before building the
    // current one -- this is what keeps the footer idempotent.
    $footer.find('.review-risk-add-footer-action').remove();

    if ($realReset.length) {
        $('<button>', {
            'type': 'button',
            'class': 'btn btn-secondary review-risk-add-footer-action',
            'text': $realReset.text().trim()
        }).on('click', function () {
            // Unreachable for this call site -- the engine renders no reset
            // button (see this function's header comment). Kept so that if a
            // future embedder does, the proxy triggers the REAL button and
            // display.php's delegated #reset_form handler runs with a $(this)
            // that can still find its .tab-data.
            $pane.find('[type="reset"]').first().trigger('click');
        }).appendTo($footer);
    }

    if ($realSave.length) {
        var saveLabel = $realSave.text().trim();
        if (!saveLabel) {
            // See the comment above this function: the engine's hidden
            // .save-risk-form button carries no text of its own.
            saveLabel = _lang['SubmitRisk'];
        }
        $('<button>', {
            'type': 'button',
            'class': 'btn btn-submit review-risk-add-footer-action',
            'text': saveLabel
        }).on('click', function () {
            // Same reasoning as the reset proxy: risk.js's delegated
            // .save-risk-form handler needs closest('form') to resolve.
            $pane.find('.save-risk-form').first().trigger('click');
        }).appendTo($footer);
    }
}

// window.RiskDetailsForm.init() is async (it fetches
// /ui/risk/template_groups before rendering anything), so the form does not
// exist in the DOM at the moment 'shown.bs.modal' fires -- a
// syncAddRiskModalFooter() call right after init() finds nothing to proxy
// and returns early. This MutationObserver closes that gap.
//
// It fires ONCE per open, not once per template-group tab switch, and that
// is all it needs to do. It watches the canvas with `{ childList: true }`
// and no `subtree`, so the only mutation it can see is a change to the
// canvas's own direct children -- which happens in exactly one place:
// renderTabs()'s `container.empty().append($form)` on the initial render.
// Every later tab switch goes through loadTemplateGroup()/buildCanvas(),
// which only ever rewrite a NESTED canvas div inside the already-built
// $form, leaving the mount's direct children untouched. That is sufficient
// because the <form> and its hidden .save-risk-form affordance are built
// once by renderTabs() and persist across tab switches, so the footer proxy
// the first sync builds stays correct for the life of the open modal.
// Disconnected on close and reconnected on the next open, mirroring
// window.RiskDetailsForm's own init()/destroy() lifecycle for this
// container.
var reviewRiskAddModalCanvasObserver = null;

$('#review-risk-add-modal')
    .on('shown.bs.modal', function () {
        // The page-wide GridStack.renderCB guard that used to live here now
        // lives in the engine itself (guardGridStackRenderCB() in
        // js/simplerisk/common/risk-details-form.js, called from init()), so
        // every embedder of the Cards form gets it, not just this modal.
        window.RiskDetailsForm.init('#review-risk-add-modal-canvas', {
            embedded: true,
            // Gates SupportingDocumentation's file input (buildSupportingDocumentationWidget(),
            // risk-details-form.js) -- read from the canvas's own data
            // attribute (includes/display.php), the real submit_risks
            // session check: this modal is reachable by anyone with the
            // broader riskmanagement permission, which does not imply
            // submit_risks.
            canSubmitRisk: $('#review-risk-add-modal-canvas').attr('data-can-submit-risk') === '1'
        });
        syncAddRiskModalFooter();

        var canvasEl = document.getElementById('review-risk-add-modal-canvas');
        if (canvasEl && typeof MutationObserver !== 'undefined') {
            if (reviewRiskAddModalCanvasObserver) {
                reviewRiskAddModalCanvasObserver.disconnect();
            }
            reviewRiskAddModalCanvasObserver = new MutationObserver(function () {
                syncAddRiskModalFooter();
            });
            reviewRiskAddModalCanvasObserver.observe(canvasEl, { childList: true });
        }
    })
    .on('hidden.bs.modal', function () {
        if (reviewRiskAddModalCanvasObserver) {
            reviewRiskAddModalCanvasObserver.disconnect();
            reviewRiskAddModalCanvasObserver = null;
        }

        // Full teardown on close: every nested GridStack instance, HugeRTE
        // editor and pending timer the engine built for this container. Also
        // what makes open/close/reopen safe -- window.RiskDetailsForm.init()
        // tears down and rebuilds from scratch regardless, but destroying
        // here as soon as the modal is gone avoids leaving a live instance
        // (and its window resize binding) around while the modal is hidden.
        window.RiskDetailsForm.destroy('#review-risk-add-modal-canvas');
    })
    // The new engine's tabs (.sr-tabs/.sr-tab, risk-details-form.js) are NOT
    // Bootstrap tabs, so this no longer fires for template-group switching --
    // left in place as a harmless no-op rather than removed, since nothing
    // in this modal emits 'shown.bs.tab' anymore. No replacement per-tab-
    // switch sync is needed: a tab switch rewrites only the nested canvas
    // inside the already-built form, and the form's hidden .save-risk-form
    // button (the only thing the footer proxies) survives it untouched, so
    // the one sync the MutationObserver above performs on the initial render
    // stays valid for the life of the open modal.
    .on('shown.bs.tab', function () {
        syncAddRiskModalFooter();
    });

$(document).on('simplerisk:risk-created', function () {
    // risk.js already hid #review-risk-add-modal itself (it owns the modal
    // reference at the point of success, via the submitted form's closest
    // data-on-save ancestor) -- calling .modal('hide') again here is a safe
    // no-op on an already-hidden Bootstrap modal, so this doesn't need to
    // check state first.
    $('#review-risk-add-modal').modal('hide');

    // Same "something changed, refresh both" pairing every other
    // data-changing control on this page uses (filter chips, quickfilters,
    // filter-clear -- see table.ajax.reload()/loadFilterOptions() above):
    // a new risk can change every facet count (owner/team/level/reviewer
    // options, the action-type and status-scope chip counts) in addition to
    // the grid's own row set.
    //
    // Deliberately NOT resetSelectionForFilterChange() -- the currently
    // selected rows are all still valid matches for the CURRENT filter (a
    // new risk being created doesn't invalidate them the way a changed
    // filter would), so wiping selectedIds here would just be an
    // unnecessary UX regression. But "Select all N" (below) can no longer
    // claim to be an exhaustive selection once a new row might now match --
    // clearing just the flag lets the reload's own draw handler re-offer
    // the banner (against the freshly updated lastRecordsFiltered) instead
    // of leaving it stuck hidden as if the selection still covered every
    // filtered row.
    selectAllFiltered = false;
    table.ajax.reload();
    loadFilterOptions();

    // risk.js's success handler already fires the save toast via
    // showAlertsFromArray(data.status_message) (server-side alert, before
    // any of its branching) -- verified live rather than assumed; no
    // additional toast needed here.
});

// Empty state (final whole-branch review, Finding 2). NoActionItemsTitle/
// NoActionItemsBody were added to lang.en.php in Task 7 but never wired
// anywhere -- there was no `language.emptyTable` option on this serverSide
// DataTable and no custom empty-state markup, so an empty result set fell
// through to DataTables' own bundled, untranslated English default ("No data
// available in table."). The spec calls this an EXPECTED state, not an edge
// case -- a riskmanagement-only user with no plan_mitigations/review_<level>
// permission sees it on every load ("Show my action items" defaults checked).
// Reuses the shipped .sr-table-empty/-icon/-title/-body vocabulary
// (design-system.md §10; governance-exceptions.js's/governance-documents.js's
// own reference shape) rather than inventing new markup. DataTables injects
// language.emptyTable via .html() into a single <td> spanning the table (see
// extras/workflows/includes/display.php's own emptyTable comment), so a full
// markup string -- not just plain text -- renders correctly here; esc() is
// still required on both interpolated lang values for the same reason every
// other render* helper in this file uses it.
function emptyStateHtml() {
    return '<div class="sr-table-empty">' +
        '<div class="sr-table-empty-icon"><i class="fa fa-search" aria-hidden="true"></i></div>' +
        '<div class="sr-table-empty-title">' + esc(L('NoActionItemsTitle')) + '</div>' +
        '<div class="sr-table-empty-body">' + esc(L('NoActionItemsBody')) + '</div>' +
        '</div>';
}

// Task 8 (Customization Extra dynamic columns): constructs the Review Risk
// DataTable and wires its column-order/draw/length-control plumbing.
//
// RELOCATED here from a synchronous call inside init() (task-8-brief.md's
// Step 3, option (a)): the `columns:` config below now ends with 3
// TOGGLE_COLUMNS/COLUMN_GROUPS-driven custom_field_* splices
// (customFieldColumnDefsForGroup(), above -- Task 5's applyActiveColumns()
// is what populates those with the site's active custom fields), which have
// to be in the SAME order display_review_risk() (includes/display.php,
// Task 6) emitted the matching <th data-col="custom_field_N"> markup in --
// DataTables matches `columns:` entries to <th> elements purely by
// position. COLUMN_GROUPS only reaches that final, custom-field-inclusive
// shape once applyActiveColumns(opts.active_columns) has run inside
// loadFilterOptions()'s own `.done()` handler (above) -- so this function's
// ONLY call site is there, right after that call and after the saved
// columnVisible/savedColumnOrder state is applied, never from init()
// directly. Before this change, `table = $(...).DataTable(...)` ran
// synchronously in init(), immediately after `loadFilterOptions()` was
// merely KICKED OFF (not awaited) -- so the very first construction always
// missed the async response's custom-field keys entirely, matching the
// live 0-rows DataTables-init failure Task 8 exists to fix (a `columns:`
// entry count that didn't match the (by-then-47) `<th data-col>` count).
//
// Safe to defer this way: applyColumnVisibility() (loadFilterOptions()'s
// own comment) and applySavedColumnOrder()/currentColumnOrderForSave()
// (their own `if (!table)` guards) were ALREADY written to tolerate `table`
// not existing yet, and every OTHER init()-time handler below that touches
// `table` only does so inside a deferred event-handler callback body (a
// click/change/input), never synchronously at bind time -- by the time a
// real user can trigger one, this function's single ajax round-trip
// (loadFilterOptions()'s GET) has already resolved and `table` exists. The
// one caller that COULD reach a `table.ajax.reload()` synchronously before
// this runs -- init()'s own URL-seeded setActionType()/setScope()/
// setStatusScope()/setDueStatus() calls -- already pass `reload:false` for
// exactly this reason (see setActionType()'s own comment, above), predating
// this task.
function buildReviewRiskDataTable() {
    // Finding 1 (final whole-branch review): construct exactly once, whichever
    // path gets here first -- loadFilterOptions()'s `.done()` normally, or its
    // `.fail()` fallback when the very first fetch failed. A second call would
    // re-init an already-initialised #review_risk_table, which DataTables
    // rejects outright. See tableBuilt's own declaration for why this can't
    // just reuse filterOptionsInitialized.
    if (tableBuilt) {
        // The failure path built the table with no saved order (there was no
        // response to read one from). If a later successful fetch has since
        // delivered one, apply it now rather than silently dropping it --
        // re-initialising the table to pick it up is not an option, and
        // applySavedColumnOrder() is a pure ColReorder call that works fine on
        // a live table (it is what a real drag does).
        if (savedColumnOrder) {
            applySavedColumnOrder();
        }
        return;
    }
    tableBuilt = true;

    // Default-sort-indicator follow-up: built as a plain options object first
    // (rather than passed inline to .DataTable()) so `order` below can find
    // the Risk Score column's actual position in `columns` instead of a
    // hardcoded index that would silently go stale the next time a column is
    // added/reordered.
    var reviewRiskDtOptions = {
        serverSide: true,
        processing: true,
        pagingType: 'simple_numbers',
        pageLength: 25,
        // autoWidth's default (true) column-width calculation builds a
        // <colgroup> reserving a fixed px width per COLUMN, including the
        // ~40 toggleable ones this page hides via its own data-col/d-none
        // convention rather than DataTables' native column().visible() API
        // (see toggleColumnDef()'s own createdCell(), above) -- DataTables
        // never learns those columns are hidden, so their <col> entries
        // keep reserving width forever. With most columns hidden by
        // default, that reserved-but-invisible width (confirmed live:
        // ~28px x ~44 hidden columns, over 1200px) is exactly why the
        // visible columns stayed narrow instead of growing to fill the
        // table even though the <table> element itself was already 100%
        // wide. autoWidth:false drops the whole <colgroup> scheme and lets
        // the browser's native table-layout:auto size columns from actual
        // VISIBLE cell content only, so a narrower column set expands to
        // fill the available width the way a plain HTML table would.
        autoWidth: false,
        // Column drag-reorder (functional-parity audit): ColReorder moves
        // whole <th>/<td> DOM node pairs, so it composes with this file's
        // existing data-col/d-none visibility convention (applyColumnVisibility())
        // with no change needed there -- a reordered column keeps its
        // data-col attribute and current visibility state automatically.
        // fixedColumnsLeft: excludes the checkbox column (index 0) from
        // being draggable -- it must always stay first.
        colReorder: {
            fixedColumnsLeft: 1,
        },
        // URL-params follow-up: seeds DataTables' OWN internal search state
        // (what its `ajax.data` protocol serializes into d.search.value,
        // below) -- this custom search box isn't DataTables' own generated
        // 'f' widget and DataTables never reads an arbitrary input's value
        // on its own, so without this a bookmarked '?search=...' URL would
        // show the right text in the box but the table's FIRST page would
        // still come back unfiltered by it.
        //
        // Race-condition fix (post-review, Task 8): reads the LIVE
        // '#review-risk-search' input .val() at construction time, not
        // initialUrlFilters.search directly. By the time this function runs
        // (inside loadFilterOptions()'s own `.done()`, Task 8), the input
        // already reflects BOTH the URL-seeded value (init() sets it, above,
        // before loadFilterOptions() is even called) AND any text a user
        // typed into the box during the async window before `table` existed
        // -- the search-box handler below now guards its own
        // `table.search(...).draw()` call with `if (table)`, so a keystroke
        // landing in that window updates the box (a plain DOM input write,
        // always safe) but can't call .search()/.draw() on a `table` that
        // doesn't exist yet; reading the box's live value here is what makes
        // that keystroke still reach the very first server request instead
        // of being silently dropped.
        search: { search: $('#review-risk-search').val() || '' },
        ajax: {
            url: BASE_URL + '/api/v2/risk_management/review_risk',
            type: 'POST',
            // dataSrc runs on every successful draw with the full parsed
            // response, before DataTables consumes `data` -- the standard
            // hook for reading a page-specific field alongside the
            // DataTables-protocol ones, and where updateChipCounts() gets
            // json.counts (getReviewRiskDatatableResponse(), includes/
            // api.php) to populate the All/Mitigation/Review chip badges.
            // Must return json.data; returning nothing here would blank the
            // grid.
            dataSrc: function (json) {
                updateChipCounts(json.counts);
                lastRecordsFiltered = json.recordsFiltered || 0;
                return json.data;
            },
            data: function (d) {
                // Secondary filters (multiselect/search follow-up): each
                // dimension is now zero or more values picked from a real
                // sr-select multiselect (User/Team/Risk Level -- 'All users'/
                // 'All teams' merge follow-up merged the former separate
                // Owner/Reviewer dropdowns into one User dropdown, and Team +
                // Mitigation Team into one Team dropdown -- now `multiple`
                // native <select>s, includes/display.php), not one string, so
                // these can no longer ride DataTables' own per-column
                // search.value protocol (a single string slot per column) the
                // way the old single-select version did via d.columns.push().
                // Sent as dedicated top-level custom params instead -- the
                // same pattern this function already uses for action_type/
                // my_action_items, not shoehorned into d.columns. (Review
                // Risk is the only serverSide: true DataTable in this
                // codebase -- verified by grep -- so there is no other page's
                // own precedent for this exact array-transmission shape;
                // compliance-initiate-audits.js's similar-looking
                // `.val() || []` reads feed a client-side
                // $.fn.dataTable.ext.search filter on a serverSide: false
                // table, not a POST to the server, so that shape doesn't
                // apply here.) getReviewRiskDatatableResponse() (includes/
                // api.php) reads these directly off $_POST and OR-matches a
                // row's value against the selected set (AND across the three
                // dimensions).
                //
                // Per-option-counts follow-up: reads via the SAME
                // buildFilterQueryParams() helper loadFilterOptions() (above)
                // uses to build its own GET query string, rather than a
                // second independent copy of these DOM reads -- 'search'
                // is deliberately NOT copied from it here, since DataTables'
                // own protocol already populates d.search.value from the
                // table.search() call the search-box handler below makes.
                var filters = buildFilterQueryParams();
                d.action_type = filters.action_type;
                d.my_action_items = filters.my_action_items;
                d.status_scope = filters.status_scope;
                d.due_status = filters.due_status;
                d.user_filter = filters.user_filter;
                d.team_filter = filters.team_filter;
                d.risk_level_filter = filters.risk_level_filter;
            },
            error: function (xhr) {
                retryCSRF(xhr, this);
            },
        },
        columns: [
            { data: null, orderable: false, render: renderRowCheckbox },
            // Renders the DISPLAY id (raw risks.id + 1000), matching every
            // other place this app shows a risk id -- view.php's own "ID #:"
            // header, renderRowActions()'s view.php links below, and
            // convert_to_risk_id()'s convention throughout includes/api.php.
            // `data`/`name` stay 'id' (the raw value) since that's what
            // server-side sorting/searching operate on -- only the rendered
            // TEXT shifts by the constant +1000, which doesn't change sort
            // order (a uniform offset is monotonic).
            //
            // Icon-position fix: 'num' makes DataTables right-align this column
            // (intentional, design-system.md's numeric-column rule) but ALSO gets
            // ADDED TO by DataTables' own numeric type auto-detection, which
            // stamps 'dt-type-numeric' onto this <th> because the rendered values
            // are numbers -- no other column here is auto-detected numeric. The
            // vendored dataTables.bootstrap5(.min).css sets
            // `th.dt-type-numeric div.dt-column-header{flex-direction:row-reverse}`,
            // which swaps the header's two flex children (title, then the sort-
            // icon wrapper, in DOM order) and visually put the icon on the LEFT
            // instead of the right every other sortable header here uses. The
            // same stylesheet's `th.dt-head-left div.dt-column-header{flex-
            // direction:row}` rule appears LATER in that file at equal selector
            // specificity, so adding 'dt-head-left' here (a real DataTables class,
            // scoped to `thead`/`tfoot` selectors only -- it does NOT affect this
            // column's <td> body-cell right-alignment or its numeric sort) wins
            // the cascade and restores normal (title-then-icon, icon-on-right)
            // order. Verified live against the rendered header, not just read off
            // the stylesheet.
            // Clickable ID follow-up: the legacy Plan Your Mitigations/Perform
            // Management Reviews/Review Risks Regularly pages this grid
            // replaced (retired, upgrade_from_20260908001()) let a user click
            // the ID straight through to view.php -- restored here as a plain
            // link (same BASE_URL + '/management/view.php?id=' + DISPLAY id
            // construction and target="_blank"/rel="noopener noreferrer"
            // convention renderRowActions()'s own View action already uses,
            // just inline instead of in the Actions menu), styled subtly
            // (.sr-id-link, _tables.scss -- inherits the cell's normal text
            // color, underline only on hover/focus) rather than a bright
            // link-blue that would fight the design system's "accent spent
            // once" rule. `data` here is the raw risks.id; escAttr() on the
            // assembled URL is the same escaping rowActionLink() already
            // applies to an href built the identical way.
            { data: 'id', name: 'id', className: 'num dt-head-left', render: function (data) {
                var displayId = data + 1000;
                var viewUrl = BASE_URL + '/management/view.php?id=' + encodeURIComponent(displayId);
                return '<a href="' + escAttr(viewUrl) + '" class="sr-id-link" target="_blank" rel="noopener noreferrer">' + displayId + '</a>';
            } },
            { data: 'subject', name: 'subject' },
            { data: null, name: 'needs', orderable: false, render: renderNeedsBadges },
            // Click-to-sort follow-up: 'risk_level' is now orderable -- functions.php's
            // get_risks() gained a matching `case "risk_level":` that sorts by
            // a.calculated_risk (the same underlying score this pill is derived from,
            // via get_risk_level_name()), since risk_level is a strictly monotonic
            // function of that score. Was orderable:false (Finding 4b, final
            // whole-branch review) when get_risks() had no case for it at all.
            { data: null, name: 'risk_level', render: renderRiskLevelPill },
            // visible:false/className:'d-none' removed here (Task 13 fix) --
            // DataTables' own visibility API no longer has any role for
            // these 3 columns; they're always DataTables-visible, and
            // createdCell() stamps the data-col key + the CURRENT
            // columnVisible state's d-none class onto every <td> as
            // DataTables creates it (see the Columns picker section above).
            // className: 'dt-head-left' -- icon-position fix, same as toggleColumnDef()'s
            // numericHeader opt (see its comment): DataTables auto-detects this column as
            // numeric from the raw `data: 'calculated_risk'` value even though
            // renderRiskScorePill() wraps it in a pill <span>, which would otherwise put
            // this header's sort icon on the left.
            { data: 'calculated_risk', name: 'calculated_risk', className: 'dt-head-left', render: renderRiskScorePill, createdCell: function (td) { $(td).attr('data-col', 'risk_score').toggleClass('d-none', !columnVisible.risk_score); } },
            { data: 'owner', name: 'owner', render: renderUserChip, createdCell: function (td) { $(td).attr('data-col', 'responsible').toggleClass('d-none', !columnVisible.responsible); } },
            { data: 'team', name: 'team', render: renderTeamChips, createdCell: function (td) { $(td).attr('data-col', 'team').toggleClass('d-none', !columnVisible.team); } },

            // Column-parity follow-up: the ~37 remaining toggleable columns
            // (COLUMN_GROUPS above), built via toggleColumnDef() rather than
            // hand-written like the 3 above -- same createdCell shape, just
            // factored out. Order matches includes/display.php's static
            // <th data-col="..."> order (display_review_risk()), which
            // DataTables requires (column defs and <th>s are matched
            // positionally). All default hidden (columnVisible's reduce()
            // above), same as risk_score/team.
            toggleColumnDef('risk_status'),
            toggleColumnDef('submission_date', { dateField: true }),
            toggleColumnDef('closure_date', { dateField: true }),
            toggleColumnDef('reference_id'),
            toggleColumnDef('regulation'),
            toggleColumnDef('control_number'),
            toggleColumnDef('location'),
            toggleColumnDef('source'),
            toggleColumnDef('category'),
            // 'All users'/'All teams' merge follow-up: additional_stakeholders/
            // manager (Owner's Manager)/submitted_by are now clickable chips
            // into the same merged #review-risk-user-filter as Owner/
            // Reviewed By (renderUserChip()/renderStakeholderChips() above).
            // Not notOrderable -- same as 'team' above, an array-rendered
            // column still orders fine server-side against its underlying
            // GROUP_CONCAT SQL value.
            toggleColumnDef('additional_stakeholders', { render: renderStakeholderChips }),
            toggleColumnDef('technology'),
            toggleColumnDef('manager', { render: renderUserChip }),
            toggleColumnDef('submitted_by', { render: renderUserChip }),
            toggleColumnDef('risk_tags'),
            toggleColumnDef('scoring_method'),
            // numericHeader: DataTables auto-detects this as numeric (raw ROUND()
            // value) -- see toggleColumnDef()'s numericHeader comment above.
            toggleColumnDef('residual_risk', { numericHeader: true }),
            // project's SQL value is the encrypted projects.name (enc_projects_name)
            // when the Encrypted Database Extra is active -- ordering by ciphertext
            // produces no meaningful sequence, so this stays unsortable.
            toggleColumnDef('project', { notOrderable: true }),
            // numericHeader: DataTables auto-detects this as numeric (raw DATEDIFF()
            // value) -- see toggleColumnDef()'s numericHeader comment above.
            toggleColumnDef('days_open', { numericHeader: true }),
            // affected_assets is a GROUP_CONCAT of individually-encrypted asset
            // names (enc_assets_name) -- same ciphertext-ordering problem as project.
            toggleColumnDef('affected_assets', { notOrderable: true }),
            // risk_assessment backs the encrypted risks.assessment (enc_risks_assessment).
            toggleColumnDef('risk_assessment', { longText: true, notOrderable: true }),
            // additional_notes backs the encrypted risks.notes (enc_risks_notes).
            toggleColumnDef('additional_notes', { longText: true, notOrderable: true }),
            toggleColumnDef('risk_mapping'),
            toggleColumnDef('threat_mapping'),
            // last_comment backs the encrypted comments.comment (enc_comments_comment).
            toggleColumnDef('last_comment', { longText: true, notOrderable: true }),
            // Task 8: RiskColumns' active custom fields (if any) -- see
            // customFieldColumnDefsForGroup()'s own comment for why this has
            // to splice in HERE, not as one trailing block. Positionally
            // matches display.php's own RiskColumns custom-field loop, which
            // fires right after its last_comment <th>, before mitigation_planned's.
        ].concat(customFieldColumnDefsForGroup('RiskColumns')).concat([
            toggleColumnDef('mitigation_planned', { boolField: true }),
            toggleColumnDef('planning_strategy'),
            toggleColumnDef('planning_date', { dateField: true }),
            toggleColumnDef('mitigation_effort'),
            toggleColumnDef('mitigation_cost'),
            toggleColumnDef('mitigation_owner', { render: renderUserChip }),
            // renderTeamChips() reused directly -- Mitigation Team now
            // filters the same merged #review-risk-team-filter as Team.
            toggleColumnDef('mitigation_team', { render: renderTeamChips }),
            toggleColumnDef('mitigation_accepted', { boolField: true }),
            toggleColumnDef('mitigation_date', { dateField: true }),
            toggleColumnDef('mitigation_controls'),
            // current_solution/security_recommendations/security_requirements back
            // the encrypted mitigations.current_solution/security_recommendations/
            // security_requirements columns (enc_mitigations_*).
            toggleColumnDef('current_solution', { longText: true, notOrderable: true }),
            toggleColumnDef('security_recommendations', { longText: true, notOrderable: true }),
            toggleColumnDef('security_requirements', { longText: true, notOrderable: true }),
            // Task 8: MitigationColumns' active custom fields (if any) --
            // matches display.php's MitigationColumns custom-field loop,
            // which fires right after its security_requirements <th>,
            // before review_completed's.
        ]).concat(customFieldColumnDefsForGroup('MitigationColumns')).concat([
            // review_completed is !$needs_review (api.php), computed from
            // classify_review_urgency()'s bucket classification -- no single
            // backing SQL column to sort by, same reasoning as the 'needs'
            // composite badge column staying notOrderable.
            toggleColumnDef('review_completed', { boolField: true, notOrderable: true }),
            // get_risks() has no working SQL sort case for 'management_review'
            // (resolved via a `review` table join not every sort_order branch has) --
            // see that switch's comment in functions.php.
            toggleColumnDef('management_review', { notOrderable: true }),
            toggleColumnDef('review_date', { dateField: true }),
            // Now sortable (follow-up): get_risks() gained a sort_order==22-
            // scoped case for 'next_review_date' (functions.php's switch),
            // ordering by the correlated subquery aliased `next_review`.
            toggleColumnDef('next_review_date', { dateField: true }),
            toggleColumnDef('next_step'),
            // comments backs the encrypted mgmt_reviews.comments (enc_mgmt_reviews_comment).
            toggleColumnDef('comments', { notOrderable: true }),
            // notOrderable: get_risks() (functions.php) has no ORDER BY case
            // for 'reviewer' (confirmed via grep) -- same reasoning as
            // 'management_review'/'next_review_date' above.
            toggleColumnDef('reviewer', { render: renderUserChip, notOrderable: true }),
            // Task 8: ReviewColumns' active custom fields (if any) -- matches
            // display.php's ReviewColumns custom-field loop, which fires
            // right after its comments <th>, before due_date's.
        ]).concat(customFieldColumnDefsForGroup('ReviewColumns')).concat([

            // Click-to-sort follow-up: 'due_date' is now orderable, but it can never be
            // a real SQL ORDER BY target -- it's computed in PHP after get_risks()'s
            // query runs (compute_next_action_due_date()). getReviewRiskDatatableResponse()
            // (includes/api.php) special-cases name==='due_date' and sorts the already-
            // built row array in PHP instead, honoring $orderDir. Was orderable:false
            // (Finding 4b, final whole-branch review) before that PHP-level sort existed.
            { data: null, name: 'due_date', render: renderDueState },
            // className matches the sticky-scoping class on the <th> in
            // display.php's display_review_risk() (sr-actions-col-sticky) --
            // DataTables does NOT propagate a <th>'s class onto its <td>s, so
            // this has to be set explicitly for the sticky CSS in
            // _tables.scss (#review_risk_table .sr-actions-col-sticky) to
            // reach the body cells too, not just the header.
            { data: null, name: 'actions', orderable: false, className: 'sr-actions-col-sticky', render: renderRowActions },
        ]),
        language: { emptyTable: emptyStateHtml() },
        // Task 17: stamp every row DataTables creates into rowCache (see its
        // declaration above) so bulk-action modals can read needs_mitigation/
        // needs_review off a selected id without a second fetch. Fires for
        // every row on every ajax-backed (re)draw, not just the first.
        createdRow: function (row, data) {
            rowCache[data.id] = data;
        },
        dom: 'rt<"sr-table-foot"<"sr-table-foot-left"i><"sr-table-foot-right"p>>',
    };

    // Default-sort-indicator follow-up: the grid's default sort is Risk
    // Score descending (getReviewRiskDatatableResponse()'s fallback,
    // includes/api.php -- applied whenever no column is explicitly ordered),
    // but that fact previously lived ONLY server-side -- this table's own
    // `order` was `[]` (no client-side default), so DataTables never marked
    // any header as the active sort, and the Risk Score column (d-none by
    // default in the Columns picker) showed no indicator even when a viewer
    // turned it on. Pointing `order` at its real column index instead makes
    // DataTables decorate that header correctly (aria-sort/dt-ordering-desc,
    // picked up by syncSortIcons() the same as any explicitly-clicked
    // column) AND serializes as `columns[idx].name = 'calculated_risk'` in
    // the initial request -- which is already in api.php's
    // $sql_sortable_columns allowlist, so the very first draw reaches the
    // SAME sort (ORDER BY a.calculated_risk DESC, functions.php's
    // get_risks()) via the explicit-column path instead of the fallback
    // path. Equivalent result, now visibly correct too. Falls back to no
    // default order (matching the prior behavior) if the column is ever
    // renamed/removed and this lookup can no longer find it, rather than
    // erroring.
    var calculatedRiskColumnIndex = reviewRiskDtOptions.columns.findIndex(function (c) {
        return c.name === 'calculated_risk';
    });
    reviewRiskDtOptions.order = calculatedRiskColumnIndex !== -1 ? [[calculatedRiskColumnIndex, 'desc']] : [];

    table = $('#review_risk_table').DataTable(reviewRiskDtOptions);

    // ColReorder follow-up: apply a saved order now that `table` exists.
    // Unconditional (Task 8): this function only ever runs from inside
    // loadFilterOptions()'s own `.done()` handler (see this function's
    // header comment), AFTER that same handler has already set
    // savedColumnOrder a few lines above -- there's no longer a race to
    // guard against between "table built" and "saved order arrived", since
    // both now happen in the same synchronous pass through one callback.
    if (savedColumnOrder) {
        applySavedColumnOrder();
    }

    // Debounced save -- same debounce() helper saveColumnsDebounced() uses,
    // same 500ms window. Reads the CURRENT full column order (not just what
    // moved) via currentColumnOrderForSave() (shared with saveColumnsDebounced()
    // above, so a plain checkbox toggle and a drag-reorder both always send
    // both `columns` and `order` together -- see that helper's comment).
    // `table`/ColReorder are always live inside this handler, so the
    // returned order is never null here.
    var saveColumnOrderDebounced = debounce(function () {
        $.post({
            url: BASE_URL + '/api/v2/risk_management/save_custom_review_risk_display_settings',
            data: {
                columns: TOGGLE_COLUMNS.map(function (col) { return [col, columnVisible[col] ? '1' : '0']; }),
                order: currentColumnOrderForSave(),
            },
            error: function (xhr) { retryCSRF(xhr, this); },
        });
    }, 500);
    table.on('column-reorder.dt', function () {
        // Skip the save when this event fired from applySavedColumnOrder()
        // restoring a saved order on load, not a real user drag -- see
        // restoringSavedColumnOrder's own comment, above.
        if (restoringSavedColumnOrder) {
            return;
        }
        saveColumnOrderDebounced();
    });

    // Relocate the preserved Show-N-entries control into DataTables' own
    // freshly-generated footer (see the comment above) and wire it -- same
    // technique compliance-initiate-audits.js uses for its own dedicated
    // length control.
    $lengthWrap.prependTo($('#review_risk_table').closest('.dt-container').find('.sr-table-foot-left'));
    $lengthWrap.on('change', '#review-risk-length', function () {
        table.page.len(parseInt($(this).val(), 10) || 25).draw();
    });

    table.on('draw', function () {
        paintColorPills();
        syncSortIcons();
        // Task 13 fix: DataTables fully rebuilds tbody on every draw (sort/
        // filter/page click) -- createdCell() (columns: config above)
        // already stamps each new <td>'s data-col class correctly at
        // creation time, but re-applying here too is what design-system.md
        // §6c's own text calls for ("re-apply the current visibility state
        // after every full-table re-render ... rather than trying to
        // preserve it through the DOM diff -- there isn't one") and is what
        // keeps the static <th>s (never recreated) in sync with any toggle
        // made since the last draw.
        applyColumnVisibility();
        // "Select all N" follow-up: this used to unconditionally reset
        // selectedIds = {} on every draw (sort/filter/page/reload), on the
        // reasoning that DataTables fully rebuilds tbody, so a selection
        // made before could only ever name rows no longer in the DOM. That
        // reasoning conflated "the DOM row is gone" with "the id is no
        // longer a valid selection" -- true for a FILTER change (the new
        // result set may not even contain that id), but not for a plain
        // page/sort/length change, which re-pages or re-orders the exact
        // same matching set. Wiping it there is what made "Select all N"
        // (which resolves ids across every page) evaporate the instant the
        // viewer turned the page to review what they'd selected.
        //
        // Filter/search changes now clear selectedIds themselves, at the
        // point they change (resetSelectionForFilterChange(), called from
        // the four chip setters, the secondary filters, the search box, and
        // Clear Filters) -- BEFORE this draw fires -- so by the time a draw
        // runs for one of those, selectedIds is already correctly empty.
        // A plain page/sort/length draw reaches here with selectedIds
        // untouched, and renderRowCheckbox() (above) already paints each
        // row's checkbox from it, so no reset belongs here at all anymore.
        syncBulkBar();
    });
    syncSortIcons();
}

function init() {
    // Preserve the page's own pre-built Show-N-entries control (Task 8's
    // static '.dt-length' wrapper inside '#review-risk-table-card') before
    // the `dom` string below makes DataTables generate a FRESH
    // '.sr-table-foot'/'-left'/'-right' around the table with real bound
    // info/paging widgets -- the same shape governance-documents.js's and
    // compliance-initiate-audits.js's own serverSide/clientSide grids use.
    // The rest of Task 8's static footer shell (the empty '#review-risk-info'
    // / '#review-risk-pager' placeholders) becomes a dead, unbound duplicate
    // once that happens, so it's removed here rather than left as a second,
    // non-functional footer bar under the real one -- fixing this at the
    // source in includes/display.php is out of this task's scope.
    $lengthWrap = $('#review-risk-table-card > .sr-table-foot .dt-length').detach();
    $('#review-risk-table-card > .sr-table-foot').remove();

    // Task 8's static markup bakes a Bootstrap 'd-none' (display:none
    // !important) onto '#review-risk-quickfilters'. _tables.scss's real
    // collapse mechanism for this panel is 'is-open', gated inside a
    // `@media (max-width: 1100px)` block -- above that width the panel is
    // plain `display:flex` with no d-none involved at all, and below it the
    // media query's own `display:none` applies UNCONDITIONALLY unless
    // '.is-open' is present, completely independent of 'd-none'. Verified
    // live (viewport 900px): toggling 'd-none' off left the panel with a
    // zero-size bounding rect -- the media query's plain `display:none` was
    // still in force. So the static 'd-none' is stripped once here (making
    // this panel behave exactly like governance-exceptions.js's/governance-
    // documents.js's/compliance-define-tests.js's own quickfilters row: an
    // always-visible inline row at >=1100px, collapsed behind the toggle
    // button -- itself only shown <1100px -- below it), and the toggle
    // handler below flips 'is-open', matching that same real, working
    // convention instead of the brief's invented 'd-none' toggle.
    $('#review-risk-quickfilters').removeClass('d-none');

    // URL-params follow-up: seed every control's INITIAL state from the
    // page's own URL query string (a bookmarked/shared link) before
    // anything below reads that state -- loadFilterOptions()'s first GET
    // and the DataTable's own first POST (both below) each read
    // currentActionType/currentScope/currentStatusScope and the search
    // box's live .val() via buildFilterQueryParams(), so those three chip
    // rows + the search box MUST already reflect the URL by the time
    // either fires. The three secondary-filter <select>s (user/team/risk
    // level) can't be seeded here -- their <option>s don't exist
    // yet, populateSelect() only creates them once loadFilterOptions()'s
    // response comes back -- so initialUrlFilters is stashed at module
    // scope for that function's own `.done()` handler (below) to apply
    // once its <option>s exist. Parsed exactly once, here; every later
    // control change updates the URL via syncUrlParams() instead (see the
    // event handlers below), never re-reads it.
    initialUrlFilters = parseUrlFilterParams();
    // Insights-band follow-up: set BEFORE the seeding calls just below, not
    // after -- each setter's own syncFiltersCount() call runs synchronously
    // inside the call, so setting this first is what makes the very first
    // paint (not just a later re-sync) show the Clear Filters button
    // correctly when the URL named a param whose value equals its own
    // dimension's default (the My Action Items tile's my_action_items=1).
    initialUrlHadInsightsFilter = !!(
        initialUrlFilters.status_scope || initialUrlFilters.action_type ||
        initialUrlFilters.my_action_items || initialUrlFilters.due_status
    );
    // setStatusScope()/setActionType()/setScope()/setDueStatus() (below) are the SAME
    // shared setters the chip click handlers and the narrow-width <select>
    // change handlers call -- using them here too means the URL-seeded
    // state lands on BOTH representations (chip .active class AND the
    // matching <select>'s value) in one place, rather than this block only
    // ever touching the chip markup and leaving the <select> to default to
    // its server-rendered `selected` option regardless of what the URL
    // said. `reload=false`: `table` doesn't exist yet at this point in
    // init() -- the DataTable's own first POST and loadFilterOptions()
    // (both below) already cover the initial fetch, reading the
    // current*/select state this just seeded.
    if (initialUrlFilters.status_scope) {
        setStatusScope(initialUrlFilters.status_scope, false);
    }
    if (initialUrlFilters.action_type) {
        setActionType(initialUrlFilters.action_type, false);
    }
    if (initialUrlFilters.due_status) {
        setDueStatus(initialUrlFilters.due_status, false);
    }
    if (initialUrlFilters.my_action_items) {
        setScope(initialUrlFilters.my_action_items === '1' ? 'mine' : 'all', false);
    }
    if (initialUrlFilters.search) {
        $('#review-risk-search').val(initialUrlFilters.search);
    }

    loadFilterOptions();

    // Columns picker (Task 13; design-system.md §6c). Rendered/applied here
    // with the built-in STATIC defaults immediately -- loadFilterOptions()
    // (called above; its response, not yet arrived at this point) overrides
    // both once its saved opts.column_settings arrives, same async-arrives-
    // after-sync-init pattern the Owner/Team/Level/Reviewer filter <select>s
    // already use in this file. (Task 8: the DataTable itself -- `table` --
    // no longer exists yet at THIS particular call either; its own
    // construction moved inside that same async response, see
    // buildReviewRiskDataTable()'s header comment -- but that's fine,
    // applyColumnVisibility() is a plain data-col selector with no `table`
    // dependency, per its own comment.) The .filterbtn trigger toggles the .colpanel dropdown; a
    // checkbox change flips that column's visibility (a plain data-col
    // display toggle -- applyColumnVisibility() above -- NOT DataTables'
    // own column-visibility API; see that function's comment for why) and
    // queues a debounced save. Click-outside (any click that didn't land in
    // the .colpicker wrapper) closes the panel -- identical shape to
    // governance-exceptions.js's own colpicker wiring.
    renderColpanel();
    applyColumnVisibility();
    // Search box (Column-search follow-up): delegated `input` handler
    // (element is rebuilt inside renderColpanel()'s .html() call, but
    // delegation off $(document) means no re-bind is needed either way --
    // same reasoning as the checkbox `change` handler just below). The
    // search term is reset every time the picker transitions -- both on
    // the trigger-button click (covers open AND close, since a single
    // toggle click alternates between them) and on the outside-click
    // close path -- so a stale filter never survives into the next open.
    $(document).on('input', '#review-risk-colpanel-search', function () {
        filterColpanelItems($(this).val());
    });
    $(document).on('click', '#review-risk-colpicker-btn', function (e) {
        e.stopPropagation();
        $('#review-risk-colpanel').toggleClass('d-none');
        resetColpanelSearch();
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.colpicker').length) {
            var $panel = $('#review-risk-colpanel');
            if (!$panel.hasClass('d-none')) {
                $panel.addClass('d-none');
                resetColpanelSearch();
            }
        }
    });
    $(document).on('change', '#review-risk-colpanel input[type="checkbox"]', function () {
        var col = $(this).data('col');
        columnVisible[col] = this.checked;
        applyColumnVisibility();
        saveColumnsDebounced();
    });

    // Row-actions overflow disclosure (design-system.md §6b; Task 12; shared
    // js/simplerisk/sr-row-actions-menu.js -- see management/review_risk.php's
    // asset-list comment for why 'CUSTOM:sr-row-actions-menu.js' had to be
    // added there alongside this page's own script). Delegated off the
    // <table> element itself, not '#review_risk_table tbody' -- DataTables'
    // serverSide draw() replaces the <tr> rows inside tbody but never the
    // table element this delegation is bound to, so it survives every
    // redraw without needing to be re-bound.
    SRRowActionsMenu.bind({
        container: '#review_risk_table',
        scope: $('#review_risk_table'),
        namespace: 'reviewrisk',
    });

    // Bulk-select / bulk bar (Task 14). '.sr-row-check' is delegated off
    // $(document) -- same reasoning as the colpanel checkboxes just above --
    // because DataTables rebuilds tbody (and every checkbox in it) on each
    // draw. '#review-risk-select-all' and '#review-risk-bulk-clear' are
    // Task 8's static markup (the header <th>/the bulk bar itself), neither
    // of which DataTables ever regenerates, so a direct, one-time bind is
    // enough for both -- matching this file's own direct-bind convention
    // for every other static control (#review-risk-search,
    // #review-risk-scope-filter, #review-risk-filters-toggle, etc.).
    $(document).on('change', '.sr-row-check', function () {
        var id = $(this).data('id');
        if (this.checked) {
            selectedIds[id] = true;
        } else {
            delete selectedIds[id];
        }
        syncBulkBar();
    });

    $('#review-risk-select-all').on('change', function () {
        var checked = this.checked;
        $('.sr-row-check').each(function () {
            this.checked = checked;
            var id = $(this).data('id');
            if (checked) {
                selectedIds[id] = true;
            } else {
                delete selectedIds[id];
            }
        });
        syncBulkBar();
    });

    $('#review-risk-bulk-clear').on('click', function () {
        selectedIds = {};
        selectAllFiltered = false;
        $('.sr-row-check, #review-risk-select-all').prop('checked', false);
        syncBulkBar();
    });

    // "Select all N": resolves every risk id matching the CURRENT filter/
    // search across every page (POST /risk_management/review_risk/filtered_ids,
    // review_risk_filter_ids()/review_risk_evaluate_row() in includes/
    // reporting.php -- the exact same predicate the grid's own paginated
    // response applies, so the resolved set can never disagree with what the
    // grid shows), then merges the returned ids into selectedIds and marks
    // selectAllFiltered so the banner doesn't re-offer it. Every existing
    // bulk action already just loops Object.keys(selectedIds), so nothing
    // else needs to change to act on the full set. Deliberately does NOT
    // call table.ajax.reload()/table.draw() for its own sake -- selectedIds
    // now survives a plain redraw (see the table.on('draw', ...) handler's
    // own comment), so there's no need to avoid triggering one here either;
    // simply nothing about resolving ids requires a redraw at all.
    $('#review-risk-select-all-filtered').on('click', function () {
        var $btn = $(this);
        if ($btn.prop('disabled')) {
            return;
        }
        $btn.prop('disabled', true);
        $.ajax({
            url: BASE_URL + '/api/v2/risk_management/review_risk/filtered_ids',
            type: 'POST',
            data: buildFilterQueryParams(),
            success: function (json) {
                (json.ids || []).forEach(function (id) {
                    selectedIds[id] = true;
                });
                selectAllFiltered = true;
                $('.sr-row-check').each(function () {
                    if (selectedIds[$(this).data('id')]) {
                        this.checked = true;
                    }
                });
                syncBulkBar();
            },
            error: function (xhr) {
                if (!retryCSRF(xhr, this)) {
                    var message = (xhr.responseJSON && xhr.responseJSON.status_message) || L('RequestFailed');
                    showAlertFromMessage(message, false);
                }
            },
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // Custom search box (toolbar) -- not DataTables' own generated 'f'
    // filter widget, matching compliance-initiate-audits.js's
    // #initiate-audits-search. Debounced so every keystroke doesn't fire a
    // fresh serverSide request. Per-option-counts follow-up: loadFilterOptions()
    // runs in the SAME debounced callback as the existing table.search().draw()
    // -- not a second, independently-debounced call -- so a burst of keystrokes
    // still produces exactly one of each request, not two independently-timed
    // bursts.
    $('#review-risk-search').on('input', debounce(function () {
        // Race-condition fix (post-review, Task 8): `table` doesn't exist
        // yet for the (normally brief) window between page-interactive and
        // loadFilterOptions()'s first response resolving -- construction
        // moved into that response's own `.done()` handler (Task 8,
        // buildReviewRiskDataTable()). A keystroke landing in that window
        // used to throw `TypeError: Cannot read properties of undefined
        // (reading 'search')`. Guarded here rather than disabling the input:
        // the box's own value (a plain DOM write, always safe) is already
        // what buildReviewRiskDataTable()'s own `search:` option reads at
        // construction time (see its comment), so skipping just the
        // `.search().draw()` call is a safe no-op -- the eventual first
        // server request still comes back filtered by whatever the user
        // typed, it just doesn't ALSO trigger a redundant second request
        // once `table` exists a moment later.
        resetSelectionForFilterChange();
        if (table) {
            table.search($(this).val()).draw();
        }
        loadFilterOptions();
        syncUrlParams();
    }, 300));

    // Scoped to each chip row's OWN .sr-status-chip children (not a bare
    // $('.sr-status-chip') across the whole page) -- this page now has TWO
    // independent chip-row controls (#review-risk-status-filter's action-
    // type chips, #review-risk-scope-filter's scope toggle below), and an
    // unscoped removeClass('active') here would have cleared the OTHER
    // row's active state too.
    //
    // Below 1320px wide (_tables.scss), these chip rows are hidden in favor
    // of a native <select> per group (#review-risk-status-filter-select
    // etc., includes/display.php) -- see the three `.on('change', ...)`
    // handlers right below this block. Both the chip click and the select
    // change funnel through the SAME setActionType()/setScope()/
    // setStatusScope() functions (defined further below, hoisted) rather
    // than each maintaining its own copy of "update state, reload the
    // grid, sync the URL" -- so there is exactly one filtering
    // implementation regardless of which control width the user is on, and
    // the OTHER representation (chip <-> select) always stays in sync too.
    $('#review-risk-status-filter').on('click', '.sr-status-chip', function () {
        setActionType($(this).data('action-type'));
    });

    $('#review-risk-scope-filter').on('click', '.sr-status-chip', function () {
        setScope($(this).data('scope'));
    });

    // Third independent chip-row control (see the two comments above) -- the
    // All/Open/Closed status-scope toggle.
    $('#review-risk-status-scope-filter').on('click', '.sr-status-chip', function () {
        setStatusScope($(this).data('status-scope'));
    });

    // The narrow-width <select> stand-ins -- see the comment above the chip
    // handlers. `change` (not `input`): a native <select> only fires
    // `change`, and firing on every keystroke doesn't apply here anyway
    // (this is a closed set of options, not free text).
    $('#review-risk-status-filter-select').on('change', function () {
        setActionType($(this).val());
    });

    $('#review-risk-scope-filter-select').on('change', function () {
        setScope($(this).val());
    });

    $('#review-risk-status-scope-filter-select').on('change', function () {
        setStatusScope($(this).val());
    });

    // Secondary filters panel toggle -- 'is-open', matching governance-
    // exceptions.js's/governance-documents.js's/compliance-define-tests.js's
    // identical toggle (the static 'd-none' this page's markup started with
    // was already stripped above, at init time).
    $('#review-risk-filters-toggle').on('click', function () {
        var isOpen = $('#review-risk-quickfilters').toggleClass('is-open').hasClass('is-open');
        $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Per-option-counts + cross-filter-reactivity follow-up: loadFilterOptions()
    // here re-fetches all 4 dropdowns' option/count lists (faceted against
    // whichever dimension just changed), including the one the user is
    // actively interacting with -- populateSelect()'s own capture-before-
    // rebuild/restore-after-rebuild of $select.val() (and srSelectRender()'s
    // read of that restored native-select state into its own checkbox UI)
    // is what keeps the user's just-made selection visually intact through
    // that rebuild. Safe to call alongside table.ajax.reload() without
    // risking a recursive change-event loop: srSelectRender()'s own DOM
    // rebuild sets .val() via a plain jQuery property set (no native
    // 'change' event), and srSelectChoose() -- the ONLY place sr-select.js
    // itself calls $native.trigger('change') -- only runs on a direct user
    // click/keypress against an option row, never as part of a re-render.
    $('#review-risk-user-filter, #review-risk-team-filter, #review-risk-level-filter').on('change', function () {
        syncFiltersCount();
        resetSelectionForFilterChange();
        // Race-condition fix (post-review, Task 8): these 3 <select>s have
        // no <option>s until loadFilterOptions()'s own populateSelect()
        // calls create them (same `.done()` callback that later calls
        // buildReviewRiskDataTable()), all in one synchronous pass with no
        // await/setTimeout between the two -- so in practice a real 'change'
        // here can't land before `table` exists. Guarded anyway, for the
        // same belt-and-suspenders reason as the four chip setters above.
        if (table) {
            table.ajax.reload();
        }
        loadFilterOptions();
        syncUrlParams();
    });

    // Clickable filter chips (User/Team/Risk Level, renderUserChip()/
    // renderChipListHtml()-based renderers/renderRiskLevelPill()) -- one
    // shared, delegated handler for all of them (`.sr-filter-chip`), rather
    // than one handler per column: clicking (or Enter/Space, since these are
    // role='button' spans, not real <button>s -- design-system's own
    // row-expander precedent for a non-native interactive element) adds that
    // chip's value to whichever secondary filter its `data-filter-select`
    // attribute names, and fires the SAME 'change' handling that filter
    // already has (bound above: syncFiltersCount()/reload/
    // loadFilterOptions()/syncUrlParams()) rather than duplicating that
    // logic per chip type. Delegated on the table body -- individual chip
    // elements don't persist across a redraw, only the table container does.
    //
    // Filter VALUE resolution differs by column, both correct for their own
    // data shape: a chip with a `data-filter-value` attribute (Risk Level --
    // renderRiskLevelPill() has direct access to the row's RAW,
    // unescaped-server-side risk_level string, so it sets this explicitly)
    // uses that; otherwise (Team/Mitigation Team/Owner/Owner's Manager/
    // Submitted By/Mitigation Owner/Reviewed By/Additional Stakeholders --
    // all server-PRE-escaped, with no raw representation available
    // client-side) this reads the chip's own .text(), which the browser has
    // already HTML-decoded back to the exact plain value
    // #review-risk-team-filter's/#review-risk-user-filter's <option value>
    // uses (populateSelect()/nameOption()).
    $('#review_risk_table').on('click keydown', '.sr-filter-chip', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        if (e.type === 'keydown') {
            e.preventDefault(); // Space must not also scroll the page.
        }
        var $chip = $(this);
        var selectId = $chip.data('filter-select');
        if (!selectId) {
            return;
        }
        var value = $chip.attr('data-filter-value');
        if (value === undefined) {
            value = $chip.text();
        }
        var $select = $('#' + selectId);
        var current = $select.val() || [];
        if (current.indexOf(value) !== -1) {
            return; // Already filtered to this value.
        }
        $select.val(current.concat([value]));
        if ($select.data('srSelect') && typeof window.srSelectRender === 'function') {
            window.srSelectRender($select);
        }
        $select.trigger('change');
    });

    // Needs badges (renderNeedsBadges()) -- clicking Mitigation/Review jumps
    // straight to that Action Type, same effect as clicking the chip-group
    // button itself (setActionType(), already bound above) -- a quick way
    // to go from "All" to just this action type without hunting for the
    // toolbar control. Delegated on the table body, same reason as the
    // filter-chip handler above.
    $('#review_risk_table').on('click keydown', '.sr-need-badge--clickable', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        if (e.type === 'keydown') {
            e.preventDefault();
        }
        setActionType($(this).data('action-type'));
    });

    $('#review-risk-filters-clear').on('click', function () {
        // Reset all three, then sync/reload ONCE -- chaining .trigger('change')
        // across a 3-element jQuery set instead would fire the change handler
        // (and its table.ajax.reload()) once per select, redundant
        // requests for one user action.
        //
        // (multiselect/search follow-up): these are now `multiple`
        // <select>s with NO empty-value placeholder <option> (populateSelect()
        // omits it in srSelect mode), so the old `.val('')` -- which relied
        // on matching a placeholder option that value against -- no longer
        // has anything to match and would leave selections untouched on a
        // browser that doesn't happen to no-op that call. Deselect every
        // real <option> directly and re-render each sr-select widget so its
        // closed-state label falls back to the placeholder text, matching
        // compliance-define-tests.js's resetFilters() (the shipped
        // precedent for clearing a multi-select sr-select).
        $('#review-risk-user-filter, #review-risk-team-filter, #review-risk-level-filter').each(function () {
            var $select = $(this);
            $select.find('option').prop('selected', false);
            if ($select.data('srSelect') && typeof window.srSelectRender === 'function') {
                window.srSelectRender($select);
            }
        });
        // Insights-band follow-up: also reset the four chip-groups back to
        // their own defaults (All/Open/My Action Items/All) -- a KPI tile's
        // drill-through link sets one or more of these, and this button is
        // now the single "back to default" affordance regardless of which
        // control put the page into a non-default state. reload:false on
        // all four, same reasoning as the four-select reset just above --
        // one explicit reload/sync at the end instead of four redundant
        // ones (each setter would otherwise fire its own).
        setActionType('all', false);
        setScope('mine', false);
        setStatusScope('open', false);
        setDueStatus('all', false);
        // The four setters above used reload:false, so their own
        // `if (reload !== false)` branch (which is where
        // initialUrlHadInsightsFilter normally gets cleared on a real
        // interaction) never ran -- clear it explicitly here instead, since
        // clicking Clear Filters is just as much a real interaction as
        // clicking a chip directly.
        initialUrlHadInsightsFilter = false;
        syncFiltersCount();
        resetSelectionForFilterChange();
        // Race-condition fix (post-review, Task 8): see setActionType()'s
        // own comment above -- a click on this static, always-present
        // button can land before `table` exists.
        if (table) {
            table.ajax.reload();
        }
        loadFilterOptions();
        syncUrlParams();
    });

    syncFiltersCount();
}

var ReviewRiskGrid = { init: init };

$(document).ready(function () {
    ReviewRiskGrid.init();
});
