/**
 * Define Tests grid (Phase 1, Task 6 of the Define Tests redesign).
 *
 * Fetches POST /api/v2/compliance/tests_grid and renders the grouped-by-
 * control table client-side into the ".sr-table-card" shell emitted by
 * display_framework_controls_in_compliance() (includes/compliance.php) --
 * design-system.md §6 (tables), §7 (state pills), §10 (empty states).
 *
 * Row-expand (Task 7, this file): clicking a control group's caret/header
 * fetches GET /api/v2/compliance/control_mappings and renders the control's
 * description + a Framework/Control/Reference mapping table into a
 * ".sr-expand-row"/".sr-expand-panel" directly under the group row.
 * Clicking a test row's name fetches GET /api/v2/compliance/test
 * (getTestResponse()) and renders a read-only procedure panel (objective,
 * test steps, expected results, tester, approximate time, tags) into the
 * same shell directly under the test row. Both fetches are lazy (first
 * expand only) and cached on the detail row's own `data('loaded')` flag --
 * not a page-level cache -- because loadGrid() rebuilds #define-tests-tbody
 * from scratch on every filter/search/page change, so any cache keyed
 * across reloads would go stale the moment the DOM it pointed at was
 * discarded.
 *
 * Add/Edit Test modal control roster (Issue 5; multi-select since Phase 4a,
 * common tests): loadControlRoster() fetches GET
 * /api/v2/compliance/control_roster once on page load -- a lightweight
 * id/control_number/short_name-only list with no test/last-result/tag
 * enrichment -- to populate both modals' required control
 * <select multiple>s (#add_test_control / #edit_test_control), instead of
 * piggybacking on a length=-1 tests_grid() request that would enrich and
 * return every control's full test set just to throw it away.
 *
 * FIX (audit_initiation.php edit-modal roster regression): this roster
 * machinery (controlsRoster/loadControlRoster()/populateControlMultiselect())
 * now lives in js/simplerisk/pages/compliance.js, not this file --
 * compliance.js loads on every page that renders either modal (this file
 * only loads on compliance/index.php), so the Edit modal's
 * #edit_test_control multiselect now initializes correctly on
 * compliance/audit_initiation.php too, which loads compliance.js but not
 * this file. See compliance.js for the implementation.
 *
 * Coverage select (Issue 4, this file): the toolbar's Coverage <select>
 * (with tests / all controls / gaps) is bound to state.coverage and passed
 * straight through to tests_grid()'s own coverage param -- 'all' is the
 * neutral default (unchanged behavior). The "Untested" quick chip stays
 * independent and keeps working as the one-click shortcut to gaps
 * (build_tests_grid() ORs quick.untested into the same gap filter
 * server-side, includes/compliance_grid.php).
 *
 * Batch select + retire/delete (Task 8, this file): per-test checkboxes +
 * a header select-all swap the toolbar for a contextual bulk bar (Reassign
 * tester / Set schedule are deliberately stubbed disabled this task; only
 * Retire/Delete are wired, each shown only when the server-exposed
 * can-retire/can-delete flag allows it). Selection is intentionally scoped
 * to the *current* render only -- state.selected is cleared at the start of
 * every loadGrid() (search/filter/page-size/page-change all call it),
 * matching the "selects the current page" half of design-system.md's
 * selection spec; the "escalate to the whole result set" banner is out of
 * scope for this task.
 *
 * All user-supplied/API-returned text is set via jQuery's `text:`/`.text()`
 * (never string-concatenated into HTML), so nothing here needs a second
 * escaping pass -- the CLAUDE.md double-escaping rule doesn't apply
 * because no HTML-from-string is ever built from untrusted data. The
 * control description and the objective/test_steps/expected_results
 * procedure fields are purified server-side (purify_rich_text_output())
 * for their *other* consumers (the WYSIWYG edit modal, the legacy
 * treegrid), but this read-only panel deliberately does not add a new
 * raw-HTML render sink for them either -- htmlToPlainText() below strips
 * markup via plain string replacement (no DOM parsing/injection) before
 * the result is set via `.text()`, matching the plain-text treatment the
 * codebase already gives a read-only control detail view (compare
 * `$escaper->escapeHtml($control['description'])` in
 * view_print_mitigation_controls(), includes/display.php, which also
 * renders that same WYSIWYG field as plain text rather than markup).
 *
 * Insight-band drill-through (Phase 2, Task 7, this file): the Define Tests
 * insights band (Phase 2, Task 4 -- includes/compliance.php KPI tiles) links
 * each tile to this page as index.php?insight=<key>. applyInsightFromUrl()
 * reads that param once the grid's first (unfiltered) load completes,
 * force-activates the matching quick-filter chip or Coverage-select option
 * via activateQuickFilter()/setCoverage() -- the same state.quick/
 * state.coverage/loadGrid() path the chip-row click handler and the
 * Coverage <select>'s own 'change' handler already use, not a parallel
 * mechanism -- then strips the query param with history.replaceState() so a
 * later manual refresh doesn't re-pin a filter the user has since changed
 * (mirrors the existing history.replaceState() in compliance/index.php's
 * inline script) and smooth-scrolls the ".sr-table-card" grid into view.
 * The "Failing" quick-filter chip itself (data-quick='failing') is already
 * rendered server-side in the chip row (includes/compliance.php) and
 * already wired end-to-end by the pre-existing generic chip click handler
 * (it iterates any data-quick key present in state.quick, which already
 * includes 'failing') -- no new chip markup or handler was needed here.
 */
(function ($) {
    'use strict';

    var $grid = $('#define-tests-grid');
    if (!$grid.length) {
        return;
    }

    var $tbody = $('#define-tests-tbody');
    var $count = $('#define-tests-count');
    var $overduePill = $('#define-tests-overdue-pill');
    var $info = $('#define-tests-info');
    var $pager = $('#define-tests-pager');
    var $search = $('#define-tests-search');
    var $frameworkFilter = $('#define-tests-framework-filter');
    var $familyFilter = $('#define-tests-family-filter');
    var $coverageFilter = $('#define-tests-coverage-filter');
    // Latest quick counts, reused to label the Status/Results options.
    var quickCounts = {};

    // The insights-band drill-through key, captured HERE at module init rather
    // than read later: writeFiltersToUrl() rewrites the query string on the
    // first load (it only emits known filter params), so by the time
    // applyInsightFromUrl() runs the ?insight= it needs would already be gone.
    var pendingInsightKey = new URLSearchParams(window.location.search).get('insight');
    var $statusFilter = $('#define-tests-status-filter');
    var $resultFilter = $('#define-tests-result-filter');
    var $testerFilter = $('#define-tests-tester-filter');
    var $scheduleFilter = $('#define-tests-schedule-filter');
    var $tagFilter = $('#define-tests-tag-filter');
    var $showFilter = $('#define-tests-show-filter');
    var $lengthSelect = $('#define-tests-length');
    var $quickfilters = $('#define-tests-quickfilters');
    var $removeControlConfirm = $('#define-tests-remove-control-confirm');

    // Batch select + retire/delete (Task 8) -- toolbar/bulk-bar swap + select-all.
    var $toolbar = $('#define-tests-toolbar');
    var $bulkBar = $('#define-tests-bulk-bar');
    var $bulkCount = $('#define-tests-bulk-count');
    var $selectAll = $('#define-tests-select-all');
    var $retireConfirmModal = $('#define-tests-bulk-retire-confirm');
    var $deleteConfirmModal = $('#define-tests-bulk-delete-confirm');

    var canAdd = $grid.data('can-add') === true || $grid.data('can-add') === 'true';
    var canEdit = $grid.data('can-edit') === true || $grid.data('can-edit') === 'true';
    var canDelete = $grid.data('can-delete') === true || $grid.data('can-delete') === 'true';
    // can_retire_tests() (Task 1, includes/compliance.php) -- edit_tests OR
    // delete_tests, exposed to the page via the grid shell's data attribute
    // (display_framework_controls_in_compliance()).
    var canRetire = $grid.data('can-retire') === true || $grid.data('can-retire') === 'true';
    // Batch select itself is gated on canRetire || canDelete server-side
    // (includes/compliance.php) -- with neither, there's nothing a selection
    // could do, so no checkbox column is rendered at all (server) and no
    // per-row checkbox is built here (client).
    var canBulkSelect = $grid.data('can-bulk-select') === true || $grid.data('can-bulk-select') === 'true';
    // AI control-test generation capability (design-system.md §3). Emitted by
    // display_framework_controls_in_compliance() from ai_capability_enabled(
    // 'control_test_generation'); the server ALSO gates every AI surface, so this
    // flag is cosmetic -- it lets the client gate the Generate button + suggestion
    // actions (Task B3) without a round-trip. When off, the grid never receives
    // suggestion rows or the ai_suggested filter option, so nothing here fires.
    var aiGenEnabled = $grid.data('ai-gen-enabled') === true || $grid.data('ai-gen-enabled') === 'true';

    // proposal_id -> the full suggestion payload the grid already carried in
    // memory (Task B3). Rebuilt on every render (renderControls/renderSortedTests)
    // so "Review & edit" can pre-fill the Add-test modal from the in-memory row
    // instead of issuing a second fetch for a payload the client already holds.
    var proposalPayloads = {};

    // The quick-flag set, defined once. The initial state and resetFilters()
    // both build from this: when they each carried their own literal they
    // drifted (reset dropped scheduled/inconclusive/not_tested), which left
    // setQuickFilter()'s hasOwnProperty guard silently rejecting those keys
    // after any "clear filters" click.
    function defaultQuickFlags() {
        return {
            mine: false,
            overdue: false,
            due_soon: false,
            scheduled: false,
            failing: false,
            passing: false,
            inconclusive: false,
            not_tested: false,
            manual: false,
            untested: false,
            show_retired: false,
        };
    }

    var state = {
        search: '',
        framework: [],
        family: [],
        // 'with'|'all'|'gaps' -- bound to the toolbar's Coverage select
        // (Issue 4). 'with' ("All tests") is the default: this is a grid OF
        // TESTS, and a control with none has nothing to show in it. It also
        // makes every test-level filter narrow the CONTROL list, because
        // 'with' adds HAVING COUNT(t.id) > 0 to the candidate query
        // (resolve_candidate_control_ids(), includes/compliance_grid.php)
        // whose test join already carries the retired-mode predicate -- so
        // "Retired only" lands on the controls holding a retired test instead
        // of returning the whole catalogue with empty rows.
        //
        // Coverage gaps are one click away ('gaps' / the Untested chip, which
        // ORs in the same HAVING COUNT(t.id) = 0 server-side), and 'all'
        // restores the every-control view.
        coverage: 'with',
        // Right-side chip-row selects. schedule/tag map to the existing
        // server-side schedule/tag filters; retired is the tri-state "Show"
        // filter ('active' default | 'all' | 'retired_only') that replaced the
        // old show_retired chip. quick.show_retired is left in place but unused.
        schedule: null,
        tag: null,
        // Defaults to 'active': this is an operational surface, and it keeps the
        // grid in step with the insights band, whose tiles count active tests
        // only. A control whose only test is retired still shows up as a
        // coverage gap under Untested (that count is active-only too), so
        // nothing is hidden by this -- 'all' and 'retired_only' are one click.
        // null = All testers. Replaces the self-only quick.mine chip; the
        // roster comes back with the grid (tester_options) and is org-hierarchy
        // scoped server-side.
        tester: null,
        retired: 'active',
        // '' = grouped by control (the default). A column key here flattens
        // the grid into one ordered list of tests -- see sort_tests_flat().
        sort: '',
        dir: 'asc',
        quick: defaultQuickFlags(),
        start: 0,
        length: 25,
        // Batch-select ids for the *currently rendered* page only -- reset on
        // every loadGrid() (see the file-header comment above).
        selected: {},
        // Batch-select proposal ids for suggestion rows (Task B3). Kept apart
        // from `selected` because a suggestion has no test id -- its checkbox
        // carries data-proposal-id, and "Create selected" approves proposals
        // while Retire/Delete act on the real-test `selected` set.
        selectedProposals: {},
    };

    /* ---------------------------------------------------------------- *
     * Small utilities
     * ---------------------------------------------------------------- */

    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(ctx, args);
            }, delay);
        };
    }

    // Mirrors format_every_n_units()'s {$key} substitution convention
    // (includes/compliance_grid.php) so the client-side template stays
    // consistent with the server-side one.
    function formatTemplate(template, vars) {
        return String(template || '').replace(/\{\$(\w+)\}/g, function (match, key) {
            return Object.prototype.hasOwnProperty.call(vars, key) ? vars[key] : match;
        });
    }

    function csrfHeaders() {
        // A JSON-body POST bypasses csrf-magic's automatic token injection
        // (it only rewrites form-urlencoded / FormData bodies), so send the
        // token explicitly via the CSRF-TOKEN header -- csrf-magic.php
        // copies that header into $_POST['__csrf_magic']. Matches the
        // app's other JSON AJAX calls (settings-hub.js, notifications.js,
        // and this page's own schedule_preview call in compliance.js).
        return { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' };
    }

    function fetchGrid(filters) {
        return $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/compliance/tests_grid',
            contentType: 'application/json',
            headers: csrfHeaders(),
            data: JSON.stringify(filters),
        });
    }

    /* ---------------------------------------------------------------- *
     * Empty / error states (design-system.md §10)
     * ---------------------------------------------------------------- */

    // which is one of null (show the table), 'nodata', 'noresults', 'error'.
    function showEmptyState(which) {
        $grid.find('#define-tests-empty-nodata, #define-tests-empty-noresults, #define-tests-empty-error').addClass('d-none');

        var showTable = !which;
        $grid.find('.sr-table-scroll').toggleClass('d-none', !showTable);
        $grid.find('.sr-table-foot').toggleClass('d-none', !showTable);

        if (which) {
            $('#define-tests-empty-' + which).removeClass('d-none');
        }
    }

    function filtersActive() {
        return !!(state.search || state.framework.length || state.family.length
            || state.coverage !== 'with'
            || state.quick.mine || state.quick.overdue || state.quick.due_soon || state.quick.failing
            || state.quick.passing || state.quick.manual || state.quick.untested || state.quick.show_retired
            || state.schedule || state.tag || state.retired !== 'active');
    }

    /**
     * How many filters are narrowing the grid right now.
     *
     * Counts the CONTROLS the user set, not the predicates they imply: the
     * status select carries overdue/due_soon/scheduled and the result select
     * carries failing/passing, so a user who picked one thing from each has
     * set two filters and should be told "2", not "4". Search is excluded --
     * the search box stays on screen at every width, so it can speak for
     * itself and doesn't need the badge to speak for it.
     */
    function activeFilterCount() {
        var n = 0;
        if (state.framework.length) { n++; }
        if (state.family.length) { n++; }
        if (state.coverage !== 'with') { n++; }
        if (state.quick.overdue || state.quick.due_soon || state.quick.scheduled) { n++; }
        if (state.quick.failing || state.quick.passing || state.quick.inconclusive || state.quick.not_tested) { n++; }
        if (state.tester) { n++; }
        if (state.schedule || state.quick.manual) { n++; }
        if (state.tag) { n++; }
        if (state.retired !== 'active') { n++; }
        return n;
    }

    /**
     * Keeps the narrow-width Filters button honest: the badge says how many
     * filters are on, so a narrowed grid never looks like the whole data set
     * with rows missing. Runs on every load, because a filter can be set from
     * the URL, an insights-tile drill-through, or the selects themselves.
     */
    function syncFilterToggleCount() {
        var $toggle = $('#define-tests-filters-toggle');
        if (!$toggle.length) {
            return;
        }
        var count = activeFilterCount();
        $('#define-tests-filters-count')
            .text(count ? String(count) : '')
            .prop('hidden', !count);
        $toggle.toggleClass('has-filters', count > 0);
    }

    /* ---------------------------------------------------------------- *
     * Row builders
     * ---------------------------------------------------------------- */

    // Total column count: select-checkbox, name, tester, schedule, result,
    // due, actions -- the spanning <td>s below (group header, coverage-gap,
    // and both detail rows) must span all of them, including the checkbox
    // column (no control-level checkbox -- Task 8 -- so the group row's
    // single wide <td> just starts one column further right visually).
    // check, caret, id, name, tester, schedule, last test date, last result,
    // next due, actions -- plus Control when the grid is sorted (flat), where
    // there are no group rows to carry it.
    var GRID_COLUMN_COUNT = 10;

    function gridColumnCount() {
        return GRID_COLUMN_COUNT + (state.sort ? 1 : 0);
    }

    // Strips a leading control-number prefix (e.g. "GOV-01: ") from a control
    // name. SCF import stores short_name / long_name as "<number>: <name>", so
    // rendering the number chip AND the full prefixed name repeats the number.
    // Removes the number when the name starts with it, plus any following
    // separator (":", "-", en/em dash, ".") and spaces.
    function stripControlNumberPrefix(name, num) {
        name = String(name || '');
        num = String(num || '');
        if (num && name.indexOf(num) === 0) {
            name = name.slice(num.length).replace(/^[\s:.\-–—]+/, '');
        }
        return name;
    }

    // The control's display name: short_name (else long_name) with the
    // redundant control-number prefix removed. Falls back to the raw short_name
    // if stripping leaves nothing.
    function controlDisplayName(control) {
        var stripped = stripControlNumberPrefix(control.short_name || control.long_name, control.control_number);
        return stripped || control.short_name || '';
    }

    function buildGroupRow(control) {
        var $tr = $('<tr>', { 'class': 'sr-group-row', 'data-control-id': control.id, 'aria-expanded': 'false' });
        var $td = $('<td>', { colspan: gridColumnCount() });
        var $main = $('<div>', { 'class': 'sr-group-main' });

        // Inert hook for Task 7 -- rotates via .is-expanded/[aria-expanded]
        // once that task wires a click handler that fetches control_mappings.
        $('<span>', { 'class': 'sr-group-caret' })
            .append($('<i>', { 'class': 'fa fa-chevron-right', 'aria-hidden': 'true' }))
            .appendTo($main);

        // Number chip only when present; name with the redundant number prefix
        // stripped so "GOV-01" + "GOV-01: Security…" no longer double up.
        if (control.control_number) {
            $('<span>', { 'class': 'sr-group-number', text: control.control_number }).appendTo($main);
        }
        var displayName = controlDisplayName(control);
        $('<span>', { 'class': 'sr-group-name', text: displayName }).appendTo($main);

        if (control.framework_count) {
            $('<span>', {
                'class': 'sr-badge-frameworks',
                text: '🔗 ' + control.framework_count + ' ' + _lang['Frameworks'],
            }).appendTo($main);
        }

        var testWord = control.test_count === 1 ? _lang['Test'] : _lang['Tests'];
        var metaParts = [];
        // long_name in the meta line only when it ADDS information beyond the
        // display name. SCF import stores short_name == long_name == the same
        // "<number>: <name>" string, so in the common case the stripped
        // long_name equals the display name and is dropped (it was showing the
        // control's name a third time). The toolbar search still matches
        // long_name server-side (build_grid_search() in compliance_grid.php)
        // regardless of whether it's displayed, so nothing becomes unfindable.
        var longStripped = stripControlNumberPrefix(control.long_name, control.control_number);
        if (longStripped && longStripped !== displayName) {
            metaParts.push(longStripped);
        }
        if (control.owner_name) {
            metaParts.push(control.owner_name);
        }
        metaParts.push(control.test_count + ' ' + testWord);
        $('<span>', { 'class': 'sr-group-meta', text: metaParts.join(' · ') }).appendTo($main);

        // Right-hand action cluster. Both affordances live in one wrapper that
        // owns the `margin-left: auto` push -- two buttons each carrying their
        // own auto margin would split the free space and strand the first one
        // mid-row instead of keeping the pair together at the right edge.
        var $actions = $('<div>', { 'class': 'sr-group-actions' });

        if (canAdd) {
            $('<button>', {
                type: 'button',
                'class': 'sr-group-add add-test',
                'data-control-id': control.id,
                text: '+ ' + _lang['AddTest'],
            }).appendTo($actions);
        }

        // Applying an EXISTING test to this control edits that test's control
        // set, so it's gated on edit_tests rather than the add permission.
        // Offered on every control, not just zero-test ones (buildCoverageGapRow
        // below): a control that already has one test is just as likely to want
        // a shared test added to it.
        if (canEdit) {
            $('<button>', {
                type: 'button',
                'class': 'sr-group-add apply-common-test',
                'data-control-id': control.id,
                text: _lang['ApplyCommonTests'],
            }).appendTo($actions);
        }

        // Generate with AI (design-system.md §7) -- enqueues control-test
        // generation for this control. Gated on the AI capability flag AND
        // edit_tests (the same write permission Create/Review need); the trigger
        // endpoint re-gates both server-side. The ✨ mark signals AI origin, the
        // same vocabulary the suggestion pill uses.
        if (aiGenEnabled && canEdit) {
            $('<button>', {
                type: 'button',
                'class': 'sr-group-add generate-tests',
                'data-control-id': control.id,
                text: '✨ ' + (_lang['GenerateTestsWithAI'] || 'Generate Tests with AI'),
            }).appendTo($actions);
        }

        if ($actions.children().length) {
            $actions.appendTo($main);
        }

        $td.append($main);
        $tr.append($td);
        return $tr;
    }

    // A control with zero (surviving-filter) tests -- the design-system §10
    // coverage-gap surfacing: the group header still appears, but its body
    // is a quiet "no tests yet" row with the same "+ Add test" affordance
    // instead of any test rows.
    function buildCoverageGapRow(control) {
        var $tr = $('<tr>', { 'class': 'sr-expand-row sr-empty-row' });
        var $td = $('<td>', { colspan: gridColumnCount() });
        // Compact single-line empty state -- the "no tests yet" note and the
        // inline "+ Add test" affordance sit on ONE row (not the full
        // expand-panel with its stacked text/button), so a zero-test control
        // stays short instead of leaving a tall blank band.
        var $line = $('<div>', { 'class': 'sr-empty-tests' });

        $('<span>', { 'class': 'sr-empty-text', text: _lang['ControlHasNoTestCoverage'] }).appendTo($line);

        if (canAdd) {
            // Ghost, not a primary: this row repeats once per zero-test control
            // (1,500+ on a real SCF import), so a filled accent button here
            // would spend the accent on every row instead of once per view.
            $('<button>', {
                type: 'button',
                'class': 'sr-group-add add-test',
                'data-control-id': control.id,
                text: _lang['AddTheFirstTest'],
            }).appendTo($line);
        }

        // Kept alongside "Add the first test" even though the group row above
        // now carries it for every control: this empty row is where the eye
        // lands on a zero-test control, and it's the row that states the
        // problem ("no test coverage yet"), so both ways out of that state
        // belong next to the sentence describing it. Gated on edit_tests, not
        // the add permission -- applying an existing test edits that test's
        // control set.
        if (canEdit) {
            $('<button>', {
                type: 'button',
                'class': 'sr-group-add apply-common-test',
                'data-control-id': control.id,
                text: _lang['ApplyCommonTests'],
            }).appendTo($line);
        }

        $td.append($line);
        $tr.append($td);
        return $tr;
    }

    // Derives the next-due pill's state/family/label. schedule_type='manual'
    // wins outright (a manual test's next_date is still populated -- see the
    // schedule_type field comment in compliance_grid.php -- so overdue/due-
    // soon math alone can't tell a manual test apart from a scheduled one).
    // The due-soon DECISION itself comes from the server's test.due_soon flag
    // (build_tests_grid(), includes/compliance_grid.php) -- the audit-initiation
    // lead-in window, not a hardcoded client-side day count -- so the pill and
    // the "Due soon" quick filter can never disagree about which tests match.
    // Whole days between today and a YYYY-MM-DD date: negative = in the past.
    // Both sides are floored to the day so "due today" can't read as overdue
    // because of a timestamp.
    /**
     * Whole days until a test's next date, from the SERVER's clock.
     *
     * The grid sends `days_until` alongside `overdue`/`due_soon`, all three
     * decided against one date. Deriving the count here from the browser's
     * timezone instead is what produced "Due in -1 days" inside the yellow Due
     * Soon pill: straddle midnight between the two clocks and the server's
     * "not yet overdue" met the browser's "yesterday".
     *
     * The local computation stays as the fallback for a row that predates the
     * field (a cached response mid-deploy), and it is the same arithmetic --
     * just the wrong clock when the two disagree.
     */
    function daysUntil(test) {
        if (test && test.days_until !== undefined && test.days_until !== null) {
            return parseInt(test.days_until, 10);
        }

        var dateString = (test && test.next_date) ? test.next_date : test;
        if (!dateString || typeof dateString !== 'string') {
            return null;
        }
        var target = moment(dateString, 'YYYY-MM-DD', true);
        if (!target.isValid()) {
            return null;
        }
        return target.startOf('day').diff(moment().startOf('day'), 'days');
    }

    function formatDueDate(dateString) {
        var m = dateString ? moment(dateString, 'YYYY-MM-DD', true) : null;
        return (m && m.isValid()) ? m.format(default_date_format) : '';
    }

    // Substitutes {n} / {date} in a language string. Kept here (rather than
    // concatenating in the caller) so the whole phrase stays translatable and
    // word order is the translator's to choose.
    function fillTemplate(template, values) {
        var out = String(template || '');
        Object.keys(values).forEach(function (key) {
            out = out.replace('{' + key + '}', values[key]);
        });
        return out;
    }

    // The last-result pill: HOW IT WENT.
    //
    // The base state pill carries a bare status dot, which says nothing the
    // pill's own tint doesn't already say -- so at a glance the column offered
    // colour plus a word, and colour twice. Swapping the dot for a glyph per
    // family (check / x / warning triangle / dash) gives the column a signal
    // that survives colour-blindness and a greyscale print, the same way the
    // next-due pill's clock says "this column is about time". The result
    // FAMILY drives the glyph, not the label, so a customer-renamed result in
    // the `test_results` table still gets the right mark with no JS change.
    //
    // -result also gives the pill a shared min-width so the column reads as
    // one lane instead of ragged (Fail 55px vs Inconclusive 101px).
    var RESULT_ICONS = {
        success: 'fa-check',
        danger: 'fa-xmark',
        warning: 'fa-triangle-exclamation',
        info: 'fa-hourglass-half',
        neutral: 'fa-minus',
    };

    /**
     * The Last Result pill. `family` is the HUE, `resultFamily` is the RESULT
     * the glyph draws -- they diverge for a retired test, whose Fail keeps its
     * ✗ but drops to a neutral hue (see the note at the call site).
     *
     * A test that has never run passes no resultFamily and gets NO glyph. The
     * glyphs exist to say pass or fail at a glance; on "Not Tested" there is
     * nothing to say, and the neutral dash rendered as a stray hyphen sitting
     * in front of the words ("– Not Tested"), which reads like punctuation
     * someone forgot to delete rather than an icon.
     */
    function resultPill(family, label, resultFamily) {
        var $pill = $('<span>', {
            'class': 'sr-state-pill sr-state-pill-result sr-state-pill-glyph sr-state-' + family,
        });
        var icon = resultFamily ? RESULT_ICONS[resultFamily] : null;
        if (icon) {
            $('<i>', { 'class': 'fa ' + icon, 'aria-hidden': 'true' }).appendTo($pill);
        }
        $('<span>', { text: label }).appendTo($pill);
        return $pill;
    }

    // The next-due pill: WHEN, never how it went.
    //
    // This column and the Last Result column both used bare state words in
    // the same four hues, so a red "Overdue" was read as a failure -- red
    // means "failed" one column to the left, and on the Failing tile above.
    // Saying the actual time ("Overdue · 14 days", "Due in 4 days",
    // "Scheduled · 10/01/2026") plus a clock/calendar icon makes the column
    // unmistakably temporal, so the hue is a second signal rather than the
    // only one. It also puts back information that was previously hidden in
    // the cell's title attribute.
    //
    // Overdue/due-soon are evaluated BEFORE the manual check: a manual test
    // still carries last_date/next_date and can absolutely be late (Josh),
    // and the row stripe already treated it that way -- so a "Manual" pill on
    // a red-striped row was the pill contradicting its own row.
    function computeNextDueState(test) {
        var days = daysUntil(test);

        if (test.overdue) {
            var late = (days === null) ? null : Math.abs(days);
            return {
                family: 'danger',
                icon: 'fa-clock',
                label: (late === null)
                    ? _lang['Overdue']
                    : fillTemplate(late === 1 ? _lang['OverdueByOneDay'] : _lang['OverdueByXDays'], { n: late }),
                dueSoon: false,
                overdueRow: true,
            };
        }

        if (test.due_soon) {
            var label;
            if (days === null) {
                label = _lang['DueSoon'];
            } else if (days === 0) {
                label = _lang['DueToday'];
            } else if (days === 1) {
                label = _lang['DueTomorrow'];
            } else if (days < 0) {
                // Unreachable once both halves read the same clock, and left in
                // deliberately: "Due in -1 days" is a sentence no user should
                // ever be shown, whatever disagrees upstream to produce it.
                label = _lang['DueSoon'];
            } else {
                label = fillTemplate(_lang['DueInXDays'], { n: days });
            }
            return { family: 'warning', icon: 'fa-clock', label: label, dueSoon: true };
        }

        if (test.schedule_type === 'manual') {
            // No cadence driving it and not late: there is no next date to
            // promise, so the pill says how it's run rather than when.
            return { family: 'neutral', icon: 'fa-hand-pointer', label: _lang['ScheduleManual'] };
        }

        var scheduledFor = formatDueDate(test.next_date);
        return {
            family: 'info',
            icon: 'fa-calendar-days',
            label: scheduledFor
                ? fillTemplate(_lang['ScheduledForX'], { date: scheduledFor })
                : _lang['Scheduled'],
        };
    }

    // Up to two initials for the tester avatar ("Dana Reyes" -> "DR").
    function avatarInitials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return '';
        }
        var first = parts[0].charAt(0);
        var last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        return (first + last).toUpperCase();
    }

    // Deterministic palette slot (1..N) so the same tester keeps the same
    // avatar color across rows, pages and reloads. The colors themselves live
    // in SCSS (.sr-avatar-c1..cN) -- no hex in JS.
    var AVATAR_COLOR_COUNT = 6;
    function avatarColorIndex(name) {
        var s = String(name || ''), h = 0;
        for (var i = 0; i < s.length; i++) {
            h = (h * 31 + s.charCodeAt(i)) % 1000000007;
        }
        return (h % AVATAR_COLOR_COUNT) + 1;
    }

    // Schedule mode icon + badge. format_test_schedule_summary()
    // (includes/compliance_grid.php) renders BOTH 'calendar' and 'interval'
    // as "Every N <unit>", so without the mode badge a calendar cadence and
    // an interval frequency are indistinguishable in the grid. 'manual'
    // needs no badge -- its summary already reads "Manual".
    function scheduleModeMeta(scheduleType) {
        if (scheduleType === 'calendar') {
            return { icon: 'fa-calendar', label: _lang['ScheduleCalendar'] };
        }
        if (scheduleType === 'interval') {
            return { icon: 'fa-clock', label: _lang['ScheduleInterval'] };
        }
        return { icon: '', label: '' };
    }

    // Appends " · N" to the Schedule / Show / Tag options, the way the Tester
    // roster and the quick chips already carry their counts -- a select that
    // stays silent while everything around it counts reads as if it can't.
    //
    // The base label is stashed on first run so repeated loads decorate the
    // original text instead of stacking counts onto an already-decorated one.
    // Written with .text() -- tag names are user-authored.
    function decorateOptionCounts($select, counts) {
        if (!$select.length) {
            return;
        }
        $select.find('option').each(function () {
            var $option = $(this);
            var value = $option.attr('value');
            // The neutral first option ("Any schedule", "Any tag") counts
            // nothing in particular, so it stays bare.
            var n = (value === '' || value === undefined) ? null : counts[value];

            // The count rides as DATA, not as text appended to the label: the
            // sr-select renderer draws it as a chip, so "Overdue" stays the
            // label and 4 stays a quantity. (It also means the label is never
            // mangled, so nothing has to remember an original to restore.)
            if (typeof n === 'number') {
                $option.attr('data-count', n);
            } else {
                $option.removeAttr('data-count');
            }

            // A zero option keeps its "0" chip (often the answer you wanted --
            // "Calendar 0" says no calendar-scheduled tests exist) AND stays
            // selectable: clicking it shows the empty filtered view, which is a
            // legitimate way to confirm the count. We deliberately do NOT disable
            // zero-count options -- disabling a view-mode/filter the user (or an
            // automated flow) may want to apply on an as-yet-empty grid is a dead
            // end of its own. Reset any stale disabled state a prior pass may have
            // left, so the option is always actionable regardless of count.
            $option.prop('disabled', false);
        });

        srSelectRender($select);
    }

    /* ---------------------------------------------------------------- *
     * sr-select: a listbox that can show a count chip per option
     * ---------------------------------------------------------------- *
     * A native <option> is plain text -- no markup, so no chip, and the
     * count ended up reading as part of the label. This draws a listbox
     * over the real <select>, which stays in the DOM as the source of
     * truth: selecting sets the native value and fires its 'change', so
     * every existing handler, .val() call and URL-sync path is untouched.
     *
     * Counts live on the option as data-count (set by decorateOptionCounts)
     * rather than being baked into its text, so the label stays clean and
     * the chip is a separate element.
     *
     * Labels go in with .text() only. These include user-authored values
     * (tag names, tester names), and this component exists precisely
     * because we would NOT turn on a widget's enableHTML to get a chip.
     */
    function srSelectRender($native) {
        var api = $native.data('srSelect');
        if (!api) {
            return;
        }

        var isMulti = api.multiple;
        var value = $native.val();
        var selectedValues = isMulti ? (value || []) : [value];
        var selectedLabels = [];
        // The selected option's own count (data-count), captured during the loop
        // so a captioned single-select can append it -- "Tests: Active tests · 142".
        var selectedCount = null;
        api.$menu.empty();

        $native.find('option').each(function () {
            var $option = $(this);
            var count = $option.attr('data-count');
            var isSelected = selectedValues.indexOf($option.attr('value')) !== -1;

            var $row = $('<button>', {
                type: 'button',
                'class': 'sr-select-option',
                role: 'option',
                'data-value': $option.attr('value'),
                'aria-selected': isSelected ? 'true' : 'false',
            });
            $row.prop('disabled', $option.prop('disabled'));

            // A multi-select row carries a tick so its state is readable
            // without relying on the row's weight alone.
            if (isMulti) {
                $('<i>', {
                    'class': 'fa fa-check sr-select-tick' + (isSelected ? '' : ' is-empty'),
                    'aria-hidden': 'true',
                }).appendTo($row);
            }

            $('<span>', { 'class': 'sr-select-text', text: $option.text() }).appendTo($row);
            if (count !== undefined && count !== '') {
                $('<span>', { 'class': 'sr-count-chip', text: count }).appendTo($row);
            }

            if (isSelected) {
                selectedLabels.push($option.text());
                selectedCount = count;
            }

            $row.appendTo(api.$menu);
        });

        // Closed-state label. Multi-selects summarise: nothing picked reads as
        // the placeholder ("All Frameworks"), one reads as itself, more than
        // one as a count -- names would overflow the control.
        var label;
        if (isMulti) {
            if (!selectedLabels.length) {
                label = api.placeholder;
            } else if (selectedLabels.length === 1) {
                label = selectedLabels[0];
            } else {
                label = String(_lang['NSelected'] || '{n} selected').replace('{n}', selectedLabels.length);
            }
        } else {
            label = selectedLabels.length ? selectedLabels[0] : $native.find('option:first').text();
        }

        // Opt-in caption: a select carrying data-caption bakes the dimension name
        // INTO the closed control -- "Tests: AI suggested tests · 9" -- and appends
        // the selected option's own count when it has one. Guarded on data-caption's
        // presence so every other sr-select is untouched. The caption value is
        // resolved server-side ($lang) into the attribute, so it's inserted as text
        // like the rest of the label.
        var caption = $native.attr('data-caption');
        if (caption) {
            label = caption + ': ' + label;
            if (selectedCount !== null && selectedCount !== undefined && selectedCount !== '') {
                label += ' · ' + selectedCount;
            }
        }
        api.$button.find('.sr-select-value').text(label);
    }

    function srSelectClose(api, refocus) {
        api.$menu.attr('hidden', 'hidden');
        api.$button.attr('aria-expanded', 'false');
        if (refocus) {
            api.$button.trigger('focus');
        }
    }

    function srSelectOpen(api) {
        api.$menu.removeAttr('hidden');
        api.$button.attr('aria-expanded', 'true');
        // Land on the current selection so arrow keys continue from where the
        // value already is, not from the top of the list.
        var $selected = api.$menu.find('[aria-selected="true"]').first();
        srSelectActivate(api, $selected.length ? $selected : api.$menu.find('.sr-select-option:not(:disabled)').first());
    }

    function srSelectActivate(api, $row) {
        if (!$row || !$row.length) {
            return;
        }
        api.$menu.find('.sr-select-option').removeClass('is-active');
        $row.addClass('is-active');
        if ($row[0].scrollIntoView) {
            $row[0].scrollIntoView({ block: 'nearest' });
        }
    }

    // Arrow keys skip disabled rows: a zero-count option is shown because the
    // absence is information, but it is not a place you can land.
    function srSelectMove(api, delta) {
        var $rows = api.$menu.find('.sr-select-option').filter(function () { return !this.disabled; });
        if (!$rows.length) {
            return;
        }
        var index = $rows.index(api.$menu.find('.sr-select-option.is-active'));
        var next = index + delta;
        if (next < 0) { next = $rows.length - 1; }
        if (next >= $rows.length) { next = 0; }
        srSelectActivate(api, $rows.eq(next));
    }

    function srSelectChoose($native, value) {
        var api = $native.data('srSelect');

        if (api.multiple) {
            // Toggle, and keep the menu OPEN: picking several is the whole
            // point, and closing after each tick would make that a chore.
            var $option = $native.find('option').filter(function () { return $(this).attr('value') === value; });
            $option.prop('selected', !$option.prop('selected'));
            srSelectRender($native);
            srSelectActivate(api, api.$menu.find('[data-value="' + value + '"]'));
        } else {
            $native.val(value);
            srSelectRender($native);
            srSelectClose(api, true);
        }

        // The native 'change' is what the rest of the page listens to.
        $native.trigger('change');
    }

    function srSelectEnhance($native, placeholder) {
        if (!$native.length || $native.data('srSelect')) {
            return;
        }

        var $wrapper = $('<div>', { 'class': 'sr-select' });
        var $button = $('<button>', {
            type: 'button',
            'class': 'sr-select-button',
            'aria-haspopup': 'listbox',
            'aria-expanded': 'false',
            'aria-label': $native.attr('aria-label') || $native.attr('title') || '',
        });
        $('<span>', { 'class': 'sr-select-value' }).appendTo($button);
        $('<i>', { 'class': 'fa fa-chevron-down sr-select-caret', 'aria-hidden': 'true' }).appendTo($button);

        var $menu = $('<div>', { 'class': 'sr-select-menu', role: 'listbox', tabindex: '-1' }).attr('hidden', 'hidden');
        if ($native.prop('multiple')) {
            $menu.attr('aria-multiselectable', 'true');
        }

        $native.addClass('sr-select-native').attr('tabindex', '-1').attr('aria-hidden', 'true');
        $native.after($wrapper);
        $wrapper.append($button).append($menu);

        var api = {
            $button: $button,
            $menu: $menu,
            multiple: !!$native.prop('multiple'),
            placeholder: placeholder || $native.attr('data-placeholder') || $native.attr('title') || '',
        };
        $native.data('srSelect', api);

        $button.on('click', function (e) {
            e.preventDefault();
            if ($menu.attr('hidden')) { srSelectOpen(api); } else { srSelectClose(api, false); }
        });

        $button.on('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                srSelectOpen(api);
                $menu.trigger('focus');
            }
        });

        $menu.on('click', '.sr-select-option', function () {
            srSelectChoose($native, $(this).attr('data-value'));
        });

        $menu.on('keydown', function (e) {
            switch (e.key) {
                case 'ArrowDown': e.preventDefault(); srSelectMove(api, 1); break;
                case 'ArrowUp': e.preventDefault(); srSelectMove(api, -1); break;
                case 'Home': e.preventDefault(); srSelectActivate(api, $menu.find('.sr-select-option:not(:disabled)').first()); break;
                case 'End': e.preventDefault(); srSelectActivate(api, $menu.find('.sr-select-option:not(:disabled)').last()); break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    var $active = $menu.find('.sr-select-option.is-active');
                    if ($active.length && !$active.prop('disabled')) {
                        srSelectChoose($native, $active.attr('data-value'));
                    }
                    break;
                case 'Escape': e.preventDefault(); srSelectClose(api, true); break;
                case 'Tab': srSelectClose(api, false); break;
                default: break;
            }
        });

        // Clicking anywhere else dismisses it, like any other menu.
        $(document).on('mousedown.srselect', function (e) {
            if (!$wrapper[0].contains(e.target) && !$menu.attr('hidden')) {
                srSelectClose(api, false);
            }
        });

        srSelectRender($native);
    }

    function renderFilterCounts(counts) {
        decorateOptionCounts($scheduleFilter, counts.schedule || {});
        decorateOptionCounts($showFilter, counts.retired || {});
        decorateOptionCounts($tagFilter, counts.tags || {});
        // Status/Results options are the old chips, so they carry the same
        // quick counts those chips displayed.
        decorateOptionCounts($coverageFilter, counts.coverage || {});
        decorateOptionCounts($frameworkFilter, counts.framework || {});
        decorateOptionCounts($familyFilter, counts.family || {});
        decorateOptionCounts($statusFilter, quickCounts);
        decorateOptionCounts($resultFilter, quickCounts);
    }

    // Fills the Tester filter from the grid response. Rebuilt on every load
    // (a test's tester can change, and so can the org-hierarchy scope) while
    // preserving the current selection, so a refresh doesn't silently widen
    // the view back to All testers.
    //
    // `text:` never html -- these are user-authored names. A tester whose id
    // is no longer in the roster (their tests were reassigned, or they're
    // outside the viewer's business unit) keeps a placeholder option so the
    // select still shows what the grid is actually filtered by.
    function renderTesterOptions(options) {
        if (!$testerFilter.length) {
            return;
        }

        var selected = state.tester;
        $testerFilter.empty();
        $('<option>', { value: '', text: _lang['AllTesters'] }).appendTo($testerFilter);

        var seen = false;
        options.forEach(function (option) {
            var id = parseInt(option.value, 10);
            if (id === selected) {
                seen = true;
            }
            // The count goes on as DATA so sr-select can draw it as a chip.
            // This used to bake "name · count" into the label, which is exactly
            // the blending-into-the-words problem the chip exists to fix -- and
            // it left the Tester filter as the one select still doing it.
            $('<option>', { value: id, text: option.name })
                .attr('data-count', option.count)
                .appendTo($testerFilter);
        });

        if (selected && !seen) {
            $('<option>', { value: selected, text: _lang['Tester'] }).appendTo($testerFilter);
        }

        $testerFilter.val(selected ? String(selected) : '');
        srSelectRender($testerFilter);
    }

    function buildTestRow(test, control, showControl) {
        var due = computeNextDueState(test);

        var rowClasses = ['sr-test-row'];
        // A retired test is ARCHIVE, not workload: the artifact dims the whole
        // row and strips its live-schedule signals, so it reads as history you
        // can still find rather than something competing for attention. The
        // overdue/due-soon stripes are deliberately not applied -- a retired
        // test is not late, it's out of service (retire_framework_control_test()
        // also stops it spawning audits).
        if (test.retired) {
            rowClasses.push('sr-row-retired');
        } else if (test.overdue) {
            rowClasses.push('sr-row-overdue');
        } else if (due.dueSoon) {
            rowClasses.push('sr-row-due-soon');
        }

        // The test's full breadth from the server (get_tests_control_counts(),
        // includes/compliance_grid.php) -- how many controls this one test
        // validates, not how many survived the current filters.
        var controlCount = parseInt(test.control_count, 10) || 1;

        // data-control-count rides the ROW, not the unlink button: the button is
        // gated on edit_tests, so a user holding only delete_tests would read an
        // absent attribute as "1 control" and never see the shared-test warning
        // on the very action (delete) their permission lets them take.
        var $tr = $('<tr>', {
            'class': rowClasses.join(' '),
            'data-test-id': test.id,
            'data-control-count': controlCount,
            'aria-expanded': 'false',
        });

        var $checkTd = $('<td>', { 'class': 'sr-check-col' });
        if (canBulkSelect) {
            $('<input>', {
                type: 'checkbox',
                'class': 'form-check-input row-select',
                'data-test-id': test.id,
                'aria-label': _lang['Select'] + ' ' + test.name,
            }).appendTo($checkTd);
        }
        $tr.append($checkTd);

        // Expand caret, in its own leading column so it reads as the row's
        // handle rather than as decoration on the name.
        var $caretTd = $('<td>', { 'class': 'sr-caret-col' });
        $('<span>', { 'class': 'sr-test-caret' })
            .append($('<i>', { 'class': 'fa fa-chevron-right', 'aria-hidden': 'true' }))
            .appendTo($caretTd);
        $tr.append($caretTd);

        // Sorted (flat) view only: the control gets its own column ahead of the
        // id, rather than a line squeezed under the test name. Grouped view
        // omits it entirely -- the group row above already says it.
        if (showControl && control) {
            var $controlTd = $('<td>', { 'class': 'sr-control-col sr-control-cell' });
            if (control.control_number) {
                $('<span>', { 'class': 'sr-ctx-number', text: control.control_number }).appendTo($controlTd);
            }
            var controlName = controlDisplayName(control);
            $('<span>', { 'class': 'sr-ctx-name', text: controlName }).appendTo($controlTd);

            // The column is deliberately narrow, so the full identification goes
            // in the tooltip: this cell REPLACES the group row in sorted view,
            // and that row also shows the long name whenever it says something
            // the display name doesn't. Without this, a control whose long name
            // differs is identifiable when grouped and not when sorted.
            var controlLong = stripControlNumberPrefix(control.long_name, control.control_number);
            var controlTitle = (control.control_number ? control.control_number + ': ' : '') + controlName;
            if (controlLong && controlLong !== controlName) {
                controlTitle += ' · ' + controlLong;
            }
            $controlTd.attr('title', controlTitle);
            $tr.append($controlTd);
        }

        // The raw test id, not a display-offset one: this is the value that
        // works in /api/v2/compliance/tests/{id} and in a support conversation,
        // so showing anything else here would be a number the user can't use.
        $tr.append($('<td>', { 'class': 'sr-id-col sr-id-cell', text: test.id }));

        var $nameTd = $('<td>', { 'class': 'sr-name-col sr-name-cell' });
        // Name only -- the caret sits in its own column ahead of the id (added
        // above), so the expand affordance lines up down the left edge with the
        // group rows' carets instead of starting at a different x per row.
        // Clicking either the caret or the name toggles the read-only procedure
        // panel (buildTestDetailRow()/toggleTestDetail()); the caret rotates off
        // the row's aria-expanded, mirroring .sr-group-caret.
        var $nameCell = $('<span>', { 'class': 'sr-test-namecell' });
        $('<span>', { 'class': 'sr-test-name', text: test.name }).appendTo($nameCell);

        // Common test: ONE test validating several controls (Phase 4a's
        // test_control_map). Only badged when it actually spans more than one
        // control -- a single-control test isn't "common". control_count is
        // the test's full breadth from the server (get_tests_control_counts(),
        // includes/compliance_grid.php), not a count of how many of its
        // controls survived the current filters, so the number doesn't shift
        // as the user filters.
        if (controlCount > 1) {
            $('<span>', {
                'class': 'sr-badge-common ms-1',
                text: '🔗 ' + _lang['Common'] + ' · ' + controlCount,
                title: controlCount + ' ' + _lang['Controls'],
            }).appendTo($nameCell);
        }
        if (test.retired) {
            $('<span>', { 'class': 'sr-badge-retired ms-1', text: _lang['Retired'] }).appendTo($nameCell);
        }
        $nameTd.append($nameCell);
        $tr.append($nameTd);

        // Tester: initials avatar + name (em dash when unassigned). The class is
        // the hook the responsive rules hide this column by on narrow screens
        // (scss/modules/_tables.scss).
        var $testerTd = $('<td>', { 'class': 'sr-tester-col' });
        if (test.tester_name) {
            var $who = $('<span>', { 'class': 'sr-who' });
            $('<span>', {
                // Retired rows lose the tester's identity colour too -- the
                // avatar palette marks who's carrying live work.
                'class': 'sr-avatar ' + (test.retired ? 'sr-avatar-muted' : 'sr-avatar-c' + avatarColorIndex(test.tester_name)),
                text: avatarInitials(test.tester_name),
                'aria-hidden': 'true',
            }).appendTo($who);
            $('<span>', { text: test.tester_name }).appendTo($who);
            $testerTd.append($who);
        } else {
            $testerTd.text('—');
        }
        $tr.append($testerTd);

        // A retired test has no live schedule and nothing coming due, so both
        // columns state that plainly instead of showing a cadence and a
        // next-due pill that will never fire.
        var $scheduleTd = $('<td>', { 'class': 'sr-sched-col' });
        if (test.retired) {
            $('<span>', { 'class': 'sr-cell-dash', text: '—' }).appendTo($scheduleTd);
        } else {
            var $scheduleChip = $('<span>', { 'class': 'sr-schedule-chip' });
            var mode = scheduleModeMeta(test.schedule_type);
            if (mode.icon) {
                $('<i>', { 'class': 'fa ' + mode.icon, 'aria-hidden': 'true' }).appendTo($scheduleChip);
            }
            if (mode.label) {
                $('<span>', { 'class': 'sr-sched-mode', text: mode.label }).appendTo($scheduleChip);
            }
            $('<span>', { text: test.schedule_summary || '' }).appendTo($scheduleChip);
            $scheduleChip.appendTo($scheduleTd);
        }
        $tr.append($scheduleTd);

        // When it last actually ran. Sits beside the result it produced, so
        // "Pass" and "how long ago" read as one fact rather than two. Rendered
        // in the instance's configured date format like every other date here;
        // a test that has never run says so rather than showing an empty cell.
        var $lastDateTd = $('<td>', { 'class': 'sr-lastdate-col sr-lastdate-cell' });
        var lastRun = formatDueDate(test.last_date);
        if (lastRun) {
            // A NEUTRAL chip, matching the Schedule chip rather than the Last
            // Result / Next Due pills. Those two are coloured because they carry
            // a state that may need attention; a date is a fact, and giving it a
            // semantic colour would add a third thing competing for the eye in
            // the same row. Neutral keeps the row visually even without
            // pretending the date is a status.
            $('<span>', { 'class': 'sr-date-chip' })
                .append($('<i>', { 'class': 'fa fa-calendar-check', 'aria-hidden': 'true' }))
                .append($('<span>', { text: lastRun }))
                .appendTo($lastDateTd);
        } else {
            $('<span>', { 'class': 'sr-cell-dash', text: '—' }).appendTo($lastDateTd);
        }
        $tr.append($lastDateTd);

        var $resultTd = $('<td>', { 'class': 'sr-result-col sr-result-cell' });
        var resultLabel = test.last_result ? (_lang[test.last_result] || test.last_result) : _lang['NotTested'];
        // A retired test's last result is history, not current health: the pill
        // keeps its LABEL but drops to neutral, so an archived Fail stops
        // reading as a live problem in the Failing lane.
        resultPill(
            test.retired ? 'neutral' : (test.last_result_family || 'neutral'),
            resultLabel,
            test.last_result ? (test.last_result_family || 'neutral') : null,
        ).appendTo($resultTd);

        // The compact tier (design-system.md 6b) drops the Last Tested column
        // and reads its date here instead: "Pass . 04/12" is one fact, and it
        // is how a practitioner says it out loud. Rendered at every width and
        // revealed by CSS, so the fold is a stylesheet decision -- no resize
        // listener, and no second render path that can disagree with the first.
        if (lastRun) {
            $('<span>', { 'class': 'sr-result-date', text: lastRun }).appendTo($resultTd);
        }
        $tr.append($resultTd);

        var $dueTd = $('<td>', { 'class': 'sr-due-col' });
        if (test.retired) {
            $('<span>', { 'class': 'sr-cell-dash', text: '— ' + _lang['Archived'] }).appendTo($dueTd);
        } else {
            // The pill now says the date/countdown itself (computeNextDueState),
            // so the title only repeats the literal next_date for the cases
            // where the label is relative ("Overdue · 14 days").
            var dueTitle = test.next_date ? formatDueDate(test.next_date) : '';
            var $duePill = $('<span>', {
                'class': 'sr-state-pill sr-state-pill-due sr-state-' + due.family,
                title: dueTitle,
            });
            if (due.icon) {
                // Icon + words + hue, per the design system's "never colour
                // alone" rule -- and it's the glyph that says "this column is
                // about time", which is the whole point of the treatment.
                $('<i>', { 'class': 'fa ' + due.icon, 'aria-hidden': 'true' }).appendTo($duePill);
            }
            $('<span>', { text: due.label }).appendTo($duePill);
            $duePill.appendTo($dueTd);
        }
        $tr.append($dueTd);

        var $actionsTd = $('<td>', { 'class': 'sr-actions-col' });
        // One DOM, two presentations: wide shows the cluster inline; the
        // compact and queue tiers hide it and pop this same element from the
        // toggle below (.sr-row-actions-wrap, _tables.scss). Building a second
        // menu instead would duplicate every permission branch below it and
        // let the two copies drift apart.
        var $actionsWrap = $('<span>', { 'class': 'sr-row-actions-wrap' });
        var $actions = $('<span>', { 'class': 'sr-row-actions' });
        // View -- the whole definition, read-only. Deliberately ungated and
        // deliberately FIRST: Edit is gated on edit_tests, so without this a
        // read-only compliance user could see a test's name in the grid and
        // nothing else. Shown to editors too rather than only to non-editors --
        // "read the details" is a different intent from "change them", and
        // hiding it from the people who edit most would be an odd trade.
        $('<button>', { type: 'button', 'class': 'sr-row-action view-test', 'data-id': test.id, title: _lang['ViewTest'] })
            .append($('<i>', { 'class': 'fa fa-eye', 'aria-hidden': 'true' }))
            .appendTo($actions);
        // Edit is withheld on a retired test (the artifact's archived row
        // offers only Restore and Delete): editing something that's out of
        // service invites changes nobody is running. Restore first, then edit.
        // History stays -- reading what an archived test used to do is the
        // main reason to keep it.
        if (canEdit && !test.retired) {
            $('<button>', { type: 'button', 'class': 'sr-row-action edit-test', 'data-id': test.id, title: _lang['Edit'] })
                .append($('<i>', { 'class': 'fa fa-edit', 'aria-hidden': 'true' }))
                .appendTo($actions);
        }
        // History -- the test's audit history (its runs). Ungated: reading which
        // runs happened needs no more privilege than seeing the row, and the
        // endpoint enforces per-test access on its own. Sits after Edit so the
        // destructive actions stay last in the cluster.
        $('<button>', { type: 'button', 'class': 'sr-row-action test-history', 'data-id': test.id, title: _lang['History'] })
            .append($('<i>', { 'class': 'fa fa-clock-rotate-left', 'aria-hidden': 'true' }))
            .appendTo($actions);
        // Remove from THIS control. Shown only when the test is on more than
        // one control: with a single control, removing it would leave a test
        // mapped to nothing -- which the server refuses (test_controls_valid()
        // requires >= 1) and which the grid, being grouped by control, could
        // never show again. So on a single-control test the action is absent
        // and Retire/Delete are the honest options; the affordance appearing
        // exactly when it is legal is what teaches the rule.
        if (canEdit && !test.retired && controlCount > 1 && control && control.id) {
            $('<button>', {
                type: 'button',
                'class': 'sr-row-action remove-from-control',
                'data-id': test.id,
                'data-control-id': control.id,
                'data-control-name': (control.control_number ? control.control_number + ' ' : '') + controlDisplayName(control),
                'data-test-name': test.name || '',
                'data-control-count': controlCount,
                title: _lang['RemoveFromThisControl'],
            })
                .append($('<i>', { 'class': 'fa fa-link-slash', 'aria-hidden': 'true' }))
                .appendTo($actions);
        }
        // Retire/Restore (Task 8) -- toggles on the row's own retired state,
        // gated by the same can_retire_tests() flag as the bulk-bar action.
        // Single-row retire/restore is immediate (no confirm) -- unlike
        // delete, it's a reversible, non-destructive state flip.
        if (canRetire) {
            if (test.retired) {
                $('<button>', { type: 'button', 'class': 'sr-row-action restore-row', 'data-id': test.id, title: _lang['Restore'] })
                    .append($('<i>', { 'class': 'fa fa-rotate-left', 'aria-hidden': 'true' }))
                    .appendTo($actions);
            } else {
                $('<button>', { type: 'button', 'class': 'sr-row-action retire-row', 'data-id': test.id, title: _lang['Retire'] })
                    .append($('<i>', { 'class': 'fa fa-box-archive', 'aria-hidden': 'true' }))
                    .appendTo($actions);
            }
        }
        if (canDelete) {
            $('<button>', { type: 'button', 'class': 'sr-row-action sr-row-action-danger delete-row', 'data-id': test.id, title: _lang['Delete'] })
                .append($('<i>', { 'class': 'fa fa-trash', 'aria-hidden': 'true' }))
                .appendTo($actions);
        }
        // aria-label as well as title: this is an icon-only button (its only
        // child is an aria-hidden glyph), so title alone gives it a tooltip
        // but no accessible NAME -- a screen reader would announce an
        // unlabelled button. The || fallback keeps it labelled even if the
        // lang key ever fails to ship (jQuery treats attr(name, undefined) as
        // a getter, so an undefined here would silently write no attribute).
        var actionsLabel = _lang['Actions'] || 'Actions';
        $('<button>', {
            type: 'button',
            'class': 'sr-row-actions-toggle',
            'aria-expanded': 'false',
            'aria-haspopup': 'true',
            'aria-label': actionsLabel,
            title: actionsLabel,
        })
            .append($('<i>', { 'class': 'fa fa-ellipsis', 'aria-hidden': 'true' }))
            .appendTo($actionsWrap);
        $actionsWrap.append($actions);
        $actionsTd.append($actionsWrap);
        $tr.append($actionsTd);

        return $tr;
    }

    // An AI-suggested test row (design-system.md §6). Renders ONE pending
    // control_test_generation ai_proposals row (kind:'suggestion', shaped by
    // includes/compliance_grid.php) inline under its control -- reads like a test
    // but is unmistakably a DRAFT: a soft info-cyan wash (.sr-row-suggestion) and,
    // in the name cell, a single "✨ AI suggested" Info-family state pill. It has
    // no id / tester / last-result / next-due, so those cells read "—". The three
    // actions -- Create / Review & edit / Dismiss -- are ALWAYS visible (never
    // behind the ⋯ overflow), in every view.
    //
    // Cloned from buildTestRow's cell structure so the columns line up exactly.
    // Every user/AI-authored field (name, schedule_summary) arrives RAW from the
    // server and is inserted through jQuery text:/.text() at THIS render sink --
    // never .html()/innerHTML -- the same single-escape contract the module header
    // documents; the ✨ emoji + the $lang pill label go in via text: too.
    //
    // RENDER ONLY: the caret/procedure-expand and the three action handlers are
    // wired in Task B3. The caret cell is left empty here (column alignment) and no
    // procedure detail row is appended, so the row carries no un-wired affordance.
    function buildSuggestionRow(test, control) {
        var $tr = $('<tr>', {
            'class': 'sr-test-row sr-row-suggestion',
            'data-proposal-id': test.proposal_id,
            'aria-expanded': 'false',
        });

        // Select checkbox -- gated by the same canBulkSelect as a real row, and
        // tagged data-kind='suggestion' so a bulk action (B3) can tell a
        // suggestion selection apart from a real-test one. It carries
        // data-proposal-id (not data-test-id) because a suggestion has no test id.
        var $checkTd = $('<td>', { 'class': 'sr-check-col' });
        if (canBulkSelect) {
            $('<input>', {
                type: 'checkbox',
                'class': 'form-check-input row-select',
                'data-kind': 'suggestion',
                'data-proposal-id': test.proposal_id,
                'aria-label': _lang['Select'] + ' ' + test.name,
            }).appendTo($checkTd);
        }
        $tr.append($checkTd);

        // Caret column kept for alignment but left empty -- the procedure-expand
        // affordance is behaviour (Task B3), not render.
        $tr.append($('<td>', { 'class': 'sr-caret-col' }));

        // Sorted (flat) view carries a control column ahead of the id, exactly as
        // buildTestRow does; the grouped view omits it (the group row says it).
        if (state.sort && control) {
            var $controlTd = $('<td>', { 'class': 'sr-control-col sr-control-cell' });
            if (control.control_number) {
                $('<span>', { 'class': 'sr-ctx-number', text: control.control_number }).appendTo($controlTd);
            }
            $('<span>', { 'class': 'sr-ctx-name', text: controlDisplayName(control) }).appendTo($controlTd);
            $tr.append($controlTd);
        }

        // ID: not created yet.
        $tr.append(
            $('<td>', { 'class': 'sr-id-col sr-id-cell' })
                .append($('<span>', { 'class': 'sr-cell-dash', text: '—' }))
        );

        // Name + the "✨ AI suggested" Info pill. The ✨ is the pill's mark, so the
        // pill drops the generic status dot (sr-state-pill-glyph) -- two marks
        // before one word is noise -- and the words come from $lang. Both go in
        // via text:.
        var $nameTd = $('<td>', { 'class': 'sr-name-col sr-name-cell' });
        var $nameCell = $('<span>', { 'class': 'sr-test-namecell' });
        $('<span>', { 'class': 'sr-test-name', text: test.name }).appendTo($nameCell);
        $('<span>', { 'class': 'sr-state-pill sr-state-info sr-state-pill-glyph ms-1' })
            .append($('<span>', { text: '✨ ' + (_lang['AiSuggested'] || 'AI suggested') }))
            .appendTo($nameCell);
        $nameTd.append($nameCell);
        $tr.append($nameTd);

        // Tester: a suggestion has none yet.
        $tr.append($('<td>', { 'class': 'sr-tester-col', text: '—' }));

        // Schedule: the AI's suggested cadence as the standard chip, or "—" when
        // the proposal carried no frequency.
        var $scheduleTd = $('<td>', { 'class': 'sr-sched-col' });
        if (test.schedule_summary) {
            $('<span>', { 'class': 'sr-schedule-chip' })
                .append($('<span>', { text: test.schedule_summary }))
                .appendTo($scheduleTd);
        } else {
            $('<span>', { 'class': 'sr-cell-dash', text: '—' }).appendTo($scheduleTd);
        }
        $tr.append($scheduleTd);

        // Last tested / Last result / Next due: a suggestion has run nothing.
        $tr.append(
            $('<td>', { 'class': 'sr-lastdate-col sr-lastdate-cell' })
                .append($('<span>', { 'class': 'sr-cell-dash', text: '—' }))
        );
        $tr.append(
            $('<td>', { 'class': 'sr-result-col sr-result-cell' })
                .append($('<span>', { 'class': 'sr-cell-dash', text: '—' }))
        );
        $tr.append(
            $('<td>', { 'class': 'sr-due-col' })
                .append($('<span>', { 'class': 'sr-cell-dash', text: '—' }))
        );

        // Actions: Create / Review & edit / Dismiss -- ALWAYS visible, no overflow
        // menu, in every view. Rendered here with their labels + data-proposal-id
        // and data-control-id; the click behaviour is wired in Task B3.
        var $actionsTd = $('<td>', { 'class': 'sr-actions-col' });
        var $actions = $('<span>', { 'class': 'sr-suggestion-actions' });
        var controlId = (control && control.id) ? control.id : '';
        $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-secondary suggestion-create',
            'data-proposal-id': test.proposal_id,
            'data-control-id': controlId,
            text: _lang['Create'] || 'Create',
        }).appendTo($actions);
        $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-secondary suggestion-review',
            'data-proposal-id': test.proposal_id,
            'data-control-id': controlId,
            text: _lang['ReviewAndEdit'] || 'Review & edit',
        }).appendTo($actions);
        $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-secondary suggestion-dismiss',
            'data-proposal-id': test.proposal_id,
            'data-control-id': controlId,
            text: _lang['Dismiss'] || 'Dismiss',
        }).appendTo($actions);
        $actionsTd.append($actions);
        $tr.append($actionsTd);

        return $tr;
    }

    // The control-mapping detail row (Task 7) -- always inserted directly
    // after its group row (renderControls()), collapsed until the group
    // row's caret/header is clicked (toggleControlDetail()). Distinct from
    // buildCoverageGapRow()'s "no tests yet" row, which is a permanently
    // visible ".sr-expand-row" for zero-test controls -- both can appear
    // for the same control (this one toggled, that one always shown); they
    // share the ".sr-expand-row"/".sr-expand-panel" shell on purpose (one
    // visual "detail panel" pattern for both).
    function buildControlDetailRow(control) {
        var $tr = $('<tr>', { 'class': 'sr-expand-row d-none', 'data-control-detail-for': control.id });
        var $td = $('<td>', { colspan: gridColumnCount() });
        $td.append($('<div>', { 'class': 'sr-expand-panel' }));
        $tr.append($td);
        return $tr;
    }

    // The read-only procedure detail row (Task 7) -- always inserted
    // directly after its test row, collapsed until the test name is
    // clicked (toggleTestDetail()).
    function buildTestDetailRow(test) {
        var $tr = $('<tr>', { 'class': 'sr-expand-row d-none', 'data-test-detail-for': test.id });
        var $td = $('<td>', { colspan: gridColumnCount() });
        $td.append($('<div>', { 'class': 'sr-expand-panel' }));
        $tr.append($td);
        return $tr;
    }

    /* ---------------------------------------------------------------- *
     * Row expand: SCF-mapping + read-only procedure panels (Task 7)
     * ---------------------------------------------------------------- */

    // Strips HTML markup from a server-purified rich-text string (the
    // control description and the objective/test_steps/expected_results
    // procedure fields all pass through purify_rich_text_output() server-
    // side -- see api_v2_compliance_control_mappings() and
    // getTestResponse()) down to plain, readable text for this read-only
    // panel. Pure string replacement -- no DOM node is ever assigned this
    // HTML, so there is no innerHTML/.html() sink here; the result is
    // always rendered below via jQuery's `text:`/`.text()`.
    // Splits WYSIWYG markup into an array of plain-text lines, breaking on the
    // same block boundaries htmlToPlainText() collapses to a space. A list
    // authored in the editor can then render as a real list here instead of
    // one run-on sentence.
    //
    // Still TEXT ONLY: tags are stripped exactly as in htmlToPlainText() and
    // every segment is emitted through jQuery's `text:`, so no markup from the
    // field is ever inserted into the DOM. Same safety posture -- this changes
    // how the plain text is SEGMENTED, not whether HTML is trusted.
    function htmlToLines(html) {
        return String(html || '')
            .replace(/<\s*(br|\/p|\/div|\/li|\/tr|\/h[1-6])\s*\/?>/gi, '\n')
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;/gi, ' ')
            .replace(/&amp;/gi, '&')
            .replace(/&lt;/gi, '<')
            .replace(/&gt;/gi, '>')
            .replace(/&quot;/gi, '"')
            .replace(/&#0?39;/gi, '\'')
            .split('\n')
            .map(function (line) { return line.replace(/[ \t]+/g, ' ').trim(); })
            .filter(function (line) { return line !== ''; });
    }

    function htmlToPlainText(html) {
        return String(html || '')
            .replace(/<\s*(br|\/p|\/div|\/li|\/tr|\/h[1-6])\s*\/?>/gi, ' ')
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;/gi, ' ')
            .replace(/&amp;/gi, '&')
            .replace(/&lt;/gi, '<')
            .replace(/&gt;/gi, '>')
            .replace(/&quot;/gi, '"')
            .replace(/&#0?39;/gi, '\'')
            .replace(/\s+/g, ' ')
            .trim();
    }

    // Mirrors the "{$n} minute(s)" singular/plural pattern already used for
    // approximate_time server-side (includes/compliance.php's audit views:
    // `$test_audit['approximate_time'] > 1 ? $lang['minutes'] : $lang['minute']`).
    function formatApproximateTime(minutes) {
        var n = parseInt(minutes, 10);
        if (!n || n <= 0) {
            return '';
        }
        return n + ' ' + (n > 1 ? _lang['minutes'] : _lang['minute']);
    }

    function fetchControlMappings(controlId) {
        // GET -- csrf-magic only gates form-urlencoded/FormData POST bodies,
        // so no CSRF-TOKEN header is needed here (see csrfHeaders() above).
        return $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/compliance/control_mappings',
            data: { control_id: controlId },
        });
    }

    // --- Apply a common test -------------------------------------------------
    // Applies an EXISTING test to an additional control, turning it into (or
    // extending) a common test. Needs no dedicated endpoint: PATCH
    // /compliance/tests/{id} (updateTestById) treats every omitted field as
    // "keep existing", and a submitted controls[] replaces the junction set --
    // so we read the test's current controls and PATCH back that set plus the
    // new control.
    var applyCommonTestRoster = null;   // cached test list for the picker

    function fetchTestRoster() {
        if (applyCommonTestRoster) {
            return $.Deferred().resolve(applyCommonTestRoster).promise();
        }
        return $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/compliance/tests',
        }).then(function (result) {
            var data = (result && result.data) ? result.data : {};
            applyCommonTestRoster = data.tests || [];
            return applyCommonTestRoster;
        });
    }

    // Rebuilds the "apply a common test" picker's options and (re)initializes
    // its bootstrap-multiselect -- the same searchable widget the Add/Edit Test
    // modals use for their control roster (populateControlMultiselect(),
    // js/simplerisk/pages/compliance.js), rather than a second picker library.
    // A plain <select> is unusable once a customer has hundreds of tests.
    //
    // Multi-select (the <select> carries `multiple`, includes/compliance.php):
    // a control commonly needs several shared tests attached at once, and each
    // pick is an independent PATCH, so batching them in one open beats
    // re-opening the modal per test. No select-all option -- "every test in the
    // library" is never a deliberate choice for one control.
    //
    // Called on every open -- first with [] (placeholder only) and again when
    // the roster resolves -- so a re-opened modal never shows the previous
    // pick. The roster loads async, so the widget is init'd/rebuilt AFTER the
    // options are appended; rebuilding an already-initialized widget is what
    // keeps the second open from rendering an empty dropdown.
    //
    // Note the select deliberately carries no `multiselect` class (see the
    // #edit_test_control comment in includes/compliance.php): that class is a
    // blanket selector other code calls .multiselect('refresh') on, which would
    // force the library's lazy-init fallback before this init runs.
    function renderCommonTestPicker($select, tests) {
        if (!$select.length) {
            return;
        }

        var isInit = !!$select.data('multiselect');

        $select.empty();
        (tests || []).forEach(function (test) {
            // `text:` (never html) -- test names are user-authored.
            $('<option>', { value: test.id, text: test.name }).appendTo($select);
        });
        $select.val([]);

        if (isInit) {
            $select.multiselect('rebuild');
        } else {
            $select.multiselect({
                enableFiltering: true,
                enableCaseInsensitiveFiltering: true,
                maxHeight: 300,
                buttonWidth: '100%',
                nonSelectedText: _lang['SelectOneOrMoreTests'],
            });
        }
    }

    // PATCH bodies are read with parse_str(php://input) server-side, so the
    // payload must be form-urlencoded (jQuery's default), not JSON.
    //
    // `tags` is sent deliberately: unlike every other field in updateTestById,
    // it defaults to [] rather than false when omitted, so a controls-only
    // PATCH would clear the test's tags. Round-tripping the persisted tags
    // keeps this operation additive.
    function applyCommonTest(testId, controlId) {
        return fetchTestDetail(testId).then(function (result) {
            var test = (result && result.data) ? result.data : {};

            var controls = String(test.controls || '')
                .split(',')
                .filter(Boolean)
                .map(function (id) { return parseInt(id, 10); })
                .filter(function (id) { return !isNaN(id); });

            if (controls.indexOf(controlId) === -1) {
                controls.push(controlId);
            }

            return $.ajax({
                type: 'PATCH',
                url: BASE_URL + '/api/v2/compliance/tests/' + testId,
                headers: csrfHeaders(),
                data: { controls: controls, tags: test.tags || [] },
            });
        });
    }

    function fetchTestHistory(testId) {
        // GET -- csrf-magic only gates form-urlencoded/FormData POST bodies.
        return $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/compliance/tests/' + testId + '/audits',
        });
    }

    // Maps an audit's approval_state to its display label. 'none' means the
    // test has no approvers, i.e. approval never applied to this run -- an em
    // dash, not an empty cell, so the column reads as "not applicable" rather
    // than "data missing".
    function approvalStateLabel(state) {
        switch (state) {
            case 'pending': return _lang['Pending'];
            case 'approved': return _lang['Approved'];
            case 'rejected': return _lang['Rejected'];
            default: return '—';
        }
    }

    // Renders the audit-history table into the modal body. Every cell goes in
    // via `text:` -- test names, tester names and the customer-configurable
    // result labels are all user-authored.
    function renderTestHistory($body, audits) {
        $body.empty();

        if (!audits || !audits.length) {
            $('<p>', { 'class': 'sr-empty-text', text: _lang['ThisTestHasNotBeenRunYet'] }).appendTo($body);
            return;
        }

        var $table = $('<table>', { 'class': 'sr-history-table' });
        var $headRow = $('<tr>');
        [_lang['Date'], _lang['Result'], _lang['Tester'], _lang['Approval'], ''].forEach(function (label) {
            $('<th>', { text: label }).appendTo($headRow);
        });
        $('<thead>').append($headRow).appendTo($table);

        var $tb = $('<tbody>');
        audits.forEach(function (audit) {
            var $row = $('<tr>');
            $('<td>', { text: audit.date || '' }).appendTo($row);

            // A run with no recorded result yet is in flight -- label it as such
            // rather than showing it as an untested/blank result.
            var $resultTd = $('<td>');
            resultPill(
                audit.result_family || 'neutral',
                audit.result ? (_lang[audit.result] || audit.result) : _lang['InProgress'],
                // An in-flight run gets the hourglass rather than no glyph: unlike
                // "Not Tested", it IS a state, and one the reader can act on.
                audit.result ? (audit.result_family || 'neutral') : 'info'
            ).appendTo($resultTd);
            $row.append($resultTd);

            $('<td>', { text: audit.tester_name || '' }).appendTo($row);
            $('<td>', { text: approvalStateLabel(audit.approval_state) }).appendTo($row);

            // The server picks the target page (read-only view vs editor) from
            // the audit's own state -- the client never re-derives it.
            var $linkTd = $('<td>', { 'class': 'sr-history-link' });
            $('<a>', { href: audit.link, title: _lang['Open'] })
                .append($('<i>', { 'class': 'fa fa-arrow-right', 'aria-hidden': 'true' }))
                .appendTo($linkTd);
            $row.append($linkTd);

            $tb.append($row);
        });
        $table.append($tb);
        $body.append($table);
    }

    function fetchTestDetail(testId) {
        return $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/compliance/test',
            data: { id: testId },
        });
    }

    function renderDetailError($panel) {
        $panel.empty();
        $('<div>', { 'class': 'sr-detail-desc', text: _lang['CouldNotLoadTests'] || '' }).appendTo($panel);
    }

    // Renders the description + Framework/Control/Reference mapping table
    // into a control's detail panel. `data` is control_mappings' response
    // `.data` -- { description, mappings: [{framework_name, reference_name,
    // reference_text}] }.
    function renderControlMappings($panel, data) {
        $panel.empty();

        // Both sections carry a label (the same .sr-proc-label token the test
        // drawer uses), so an expanded control reads as titled sections rather
        // than bare text followed by an unexplained table.
        var description = htmlToPlainText(data.description);
        if (description) {
            var $descBlock = $('<div>', { 'class': 'sr-detail-block' });
            $('<span>', { 'class': 'sr-proc-label', text: _lang['Description'] }).appendTo($descBlock);
            $('<div>', { 'class': 'sr-detail-desc', text: description }).appendTo($descBlock);
            $panel.append($descBlock);
        }

        var mappings = data.mappings || [];
        if (!mappings.length) {
            $('<div>', { 'class': 'sr-detail-desc', text: _lang['NoFrameworksMapped'] }).appendTo($panel);
            return;
        }

        var $mapBlock = $('<div>', { 'class': 'sr-detail-block' });

        var $head = $('<div>', { 'class': 'sr-mapping-head' });
        $('<span>', { 'class': 'sr-proc-label', text: _lang['ValidatesAcrossMappedFrameworks'] }).appendTo($head);

        // Always present: reference TEXT is the thing worth searching, and the
        // AI Extra populates it. Three frameworks with a paragraph under each is
        // exactly when you need to find a phrase, even at three rows.
        var $filter = $('<input>', {
            type: 'search',
            'class': 'form-control sr-mapping-filter',
            placeholder: _lang['SearchMappings'],
            'aria-label': _lang['SearchMappings'],
        });
        $head.append($filter);
        $mapBlock.append($head);

        // Group by framework id, not by name: two frameworks can share a display
        // name and grouping on the name would silently merge them. The server
        // orders by framework name then reference, so insertion order here is
        // already the order to render.
        var order = [];
        var byFramework = {};
        mappings.forEach(function (mapping) {
            var key = String(mapping.framework_id || 0) + '|' + (mapping.framework_name || '');
            if (!byFramework[key]) {
                byFramework[key] = { name: mapping.framework_name || '', rows: [] };
                order.push(key);
            }
            byFramework[key].rows.push(mapping);
        });

        var $groups = $('<div>', { 'class': 'sr-mapping-groups' }).appendTo($mapBlock);
        var $noMatch = $('<div>', { 'class': 'sr-detail-desc sr-mapping-nomatch d-none', text: _lang['NoMatchingMappings'] }).appendTo($mapBlock);

        // Summary line per framework -- name plus its reference ids as chips --
        // over a collapsed detail list. The summary answers "which frameworks
        // and which references" at a glance, which is the whole question when
        // the references carry no text; expanding answers "and what does that
        // reference actually say", which is the question once the AI Extra has
        // written it. Neither state buries the other.
        order.forEach(function (key) {
            var group = byFramework[key];
            // Nothing to reveal if no reference in this framework carries text --
            // an expander that opens onto a repeat of the chips above it is a
            // promise the UI can't keep.
            var expandable = group.rows.some(function (m) { return !!m.reference_text; });

            var $group = $('<div>', { 'class': 'sr-mapping-group' + (expandable ? ' is-expandable' : ''), 'aria-expanded': 'false' });

            var $groupHead = $('<div>', { 'class': 'sr-mapping-group-head' });
            if (expandable) {
                $groupHead.attr({ role: 'button', tabindex: 0 });
                $('<span>', { 'class': 'sr-mapping-caret' })
                    .append($('<i>', { 'class': 'fa fa-chevron-right', 'aria-hidden': 'true' }))
                    .appendTo($groupHead);
            } else {
                // Keeps the framework names aligned whether or not a group can open.
                $('<span>', { 'class': 'sr-mapping-caret is-empty' }).appendTo($groupHead);
            }

            $('<span>', { 'class': 'sr-mapping-fw', text: group.name }).appendTo($groupHead);

            // The reference ids, in the summary line itself.
            var $chips = $('<span>', { 'class': 'sr-mapping-chips' });
            group.rows.forEach(function (mapping) {
                $('<span>', { 'class': 'sr-ref-chip', text: mapping.reference_name || '—' }).appendTo($chips);
            });
            $chips.appendTo($groupHead);
            $groupHead.appendTo($group);

            // Detail, collapsed until asked for (or until a search matches it).
            var $refs = $('<div>', { 'class': 'sr-mapping-refs d-none' });
            // framework_name/reference_name/reference_text are plain DB text
            // fields (not purified/WYSIWYG -- see get_mapping_control_frameworks(),
            // includes/governance.php), rendered via `text:` like every other
            // cell in this file. That holds for AI-written text too: it arrives
            // as data and goes out through .text().
            group.rows.forEach(function (mapping) {
                // Built once here rather than read out of the DOM per keystroke,
                // and it includes the text so a phrase from the explanation
                // finds its reference.
                var haystack = [group.name, mapping.reference_name, mapping.reference_text]
                    .join(' ').toLowerCase();

                var $ref = $('<div>', { 'class': 'sr-mapping-ref', 'data-haystack': haystack });
                $('<span>', { 'class': 'sr-ref-chip', text: mapping.reference_name || '—' }).appendTo($ref);
                $('<span>', {
                    'class': 'sr-ref-text' + (mapping.reference_text ? '' : ' sr-view-empty'),
                    text: mapping.reference_text || _lang['NotSpecified'],
                }).appendTo($ref);
                $ref.appendTo($refs);
            });

            $group.append($refs);
            $groups.append($group);
        });

        function toggleMappingGroup($group) {
            if (!$group.hasClass('is-expandable')) {
                return;
            }
            var open = $group.attr('aria-expanded') === 'true';
            $group.attr('aria-expanded', open ? 'false' : 'true');
            $group.find('.sr-mapping-refs').toggleClass('d-none', open);
        }

        $groups.on('click', '.sr-mapping-group-head', function () {
            toggleMappingGroup($(this).closest('.sr-mapping-group'));
        });

        // The head is a div acting as a button; tabindex without key handling
        // would be a keyboard trap.
        $groups.on('keydown', '.sr-mapping-group-head', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                toggleMappingGroup($(this).closest('.sr-mapping-group'));
            }
        });

        $filter.on('input', function () {
            var term = $(this).val().toLowerCase().trim();
            var anyVisible = false;

            $groups.find('.sr-mapping-group').each(function () {
                var $group = $(this);
                var visible = 0;

                $group.find('.sr-mapping-ref').each(function () {
                    var match = !term || (this.getAttribute('data-haystack') || '').indexOf(term) !== -1;
                    $(this).toggleClass('d-none', !match);
                    if (match) { visible++; }
                });

                $group.toggleClass('d-none', visible === 0);
                if (visible) { anyVisible = true; }

                // A search that matched text you cannot see would be a search
                // that appears not to work, so a matching group opens itself --
                // and closes again when the box is cleared.
                if (term && visible && $group.hasClass('is-expandable')) {
                    $group.attr('aria-expanded', 'true');
                    $group.find('.sr-mapping-refs').removeClass('d-none');
                } else if (!term) {
                    $group.attr('aria-expanded', 'false');
                    $group.find('.sr-mapping-refs').addClass('d-none');
                }
            });

            $noMatch.toggleClass('d-none', anyVisible);
        });

        $panel.append($mapBlock);
    }

    // Capitalizes a snake/plain token's first letter -- 'inquiry' -> 'Inquiry'
    // -- so it can be concatenated onto the 'TestMethod' lang-key prefix
    // ('TestMethod' + capitalize('inquiry') === 'TestMethodInquiry').
    function capitalize(str) {
        str = String(str || '');
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Renders the read-only procedure fields into a test's detail panel.
    // `test` is /compliance/test's response `.data` (getTestResponse()) --
    // objective, test steps, expected results, tester, approximate time,
    // tags, plus the Phase 3a fields (method, sample, required evidence,
    // approvers).
    function renderTestProcedure($panel, test) {
        $panel.empty();

        // ---- Meta: the at-a-glance facts, scanned before the prose ----
        var $meta = $('<div>', { 'class': 'sr-proc-grid' });

        function addMeta(label, value, wide) {
            var $field = $('<div>', { 'class': 'sr-proc-field' + (wide ? ' sr-col-2' : '') });
            $('<span>', { 'class': 'sr-proc-label', text: label }).appendTo($field);
            var hasValue = !!value;
            $('<span>', {
                'class': 'sr-proc-value' + (hasValue ? '' : ' sr-proc-empty'),
                text: hasValue ? value : '—',
            }).appendTo($field);
            $meta.append($field);
        }

        addMeta(
            _lang['TestMethod'],
            test.test_method ? (_lang['TestMethod' + capitalize(test.test_method)] || test.test_method) : ''
        );
        addMeta(_lang['Sample'], htmlToPlainText(test.sample));
        addMeta(_lang['ApproximateTime'], formatApproximateTime(test.approximate_time));

        // Tester -> Approver on one line: separation of duties is a *flow*, and
        // showing it as one fact makes a missing approver obvious.
        // approver_names is built server-side (getTestResponse(), includes/api.php)
        // as `$test['approver_names'][$approver_id] = name`, i.e. keyed by
        // (arbitrary, non-sequential) user id -- json_encode() therefore emits it
        // as a JSON *object*, not an array, in the near-universal case where the
        // approver ids aren't a contiguous 0-based run. Object.values() handles
        // both that object shape and a plain array uniformly, unlike .join()
        // which only works on a real array.
        var approverNames = Object.values(test.approver_names || {}).join(', ');
        // Flows as one more cell in the auto-fit grid (NOT full-width): forcing
        // it to span made it and every field after it break onto their own row,
        // leaving the meta block tall and sparse.
        var $sod = $('<div>', { 'class': 'sr-proc-field' });
        $('<span>', { 'class': 'sr-proc-label', text: _lang['Tester'] + ' → ' + _lang['Approvers'] }).appendTo($sod);
        var $sodValue = $('<span>', { 'class': 'sr-proc-value' });
        $('<span>', { 'class': test.tester_name ? '' : 'sr-proc-empty', text: test.tester_name || '—' }).appendTo($sodValue);
        $('<span>', { 'class': 'sr-proc-arrow', text: '→', 'aria-hidden': 'true' }).appendTo($sodValue);
        $('<span>', { 'class': approverNames ? '' : 'sr-proc-empty', text: approverNames || '—' }).appendTo($sodValue);
        $sodValue.appendTo($sod);
        $meta.append($sod);

        // Common-test breadth. `controls` is a GROUP_CONCAT of the test's
        // mapped framework_control_ids (get_framework_control_test_by_id()),
        // so its length is the breadth; only badge a genuinely common test.
        var mappedControls = String(test.controls || '').split(',').filter(Boolean);
        if (mappedControls.length > 1) {
            var $common = $('<div>', { 'class': 'sr-proc-field' });
            $('<span>', { 'class': 'sr-proc-label', text: _lang['Common'] }).appendTo($common);
            var $commonValue = $('<span>', { 'class': 'sr-proc-value' });
            $('<span>', {
                'class': 'sr-badge-common',
                text: '🔗 ' + _lang['Common'] + ' · ' + mappedControls.length,
                title: mappedControls.length + ' ' + _lang['Controls'],
            }).appendTo($commonValue);
            $commonValue.appendTo($common);
            $meta.append($common);
        }

        // Tags last -- an addition to the mockup's meta set, kept at the end so
        // it reads as a trailing detail rather than interrupting the core facts.
        addMeta(_lang['Tags'], (test.tags || []).join(', '));

        $panel.append($meta);

        // ---- Procedure: the prose fields, structure preserved ----
        var $proc = $('<div>', { 'class': 'sr-proc-body' });

        // mode: 'steps' -> ordered list, 'evidence' -> chip list, else prose.
        function addBlock(label, html, mode) {
            var lines = htmlToLines(html);
            var $block = $('<div>', { 'class': 'sr-proc-block' });
            $('<span>', { 'class': 'sr-proc-label', text: label }).appendTo($block);

            if (!lines.length) {
                $('<span>', { 'class': 'sr-proc-value sr-proc-empty', text: '—' }).appendTo($block);
            } else if (mode === 'steps' && lines.length > 1) {
                var $ol = $('<ol>', { 'class': 'sr-proc-steps' });
                lines.forEach(function (line) {
                    $('<li>', { text: line }).appendTo($ol);
                });
                $ol.appendTo($block);
            } else if (mode === 'evidence' && lines.length > 1) {
                var $ul = $('<ul>', { 'class': 'sr-proc-evidence' });
                lines.forEach(function (line) {
                    // Each artefact is a document the tester has to produce, so
                    // the chip carries a file glyph as well as the label.
                    $('<li>')
                        .append($('<i>', { 'class': 'fa fa-file-lines', 'aria-hidden': 'true' }))
                        .append($('<span>', { text: line }))
                        .appendTo($ul);
                });
                $ul.appendTo($block);
            } else {
                $('<span>', { 'class': 'sr-proc-value', text: lines.join(' ') }).appendTo($block);
            }

            $proc.append($block);
        }

        addBlock(_lang['Objective'], test.objective);
        addBlock(_lang['TestSteps'], test.test_steps, 'steps');
        addBlock(_lang['ExpectedResults'], test.expected_results);
        addBlock(_lang['RequiredEvidence'], test.required_evidence, 'evidence');

        $panel.append($proc);

        // ---- Actions: edit without hunting for the row's hover icons ----
        if (canEdit) {
            var $actions = $('<div>', { 'class': 'sr-proc-actions' });
            $('<button>', {
                type: 'button',
                'class': 'sr-proc-action edit-test',
                'data-id': test.id,
                text: _lang['EditTest'],
            }).appendTo($actions);
            $panel.append($actions);
        }
    }

    // Toggles the control-mapping detail row directly under `$groupRow`
    // (always its next sibling -- renderControls() inserts them adjacently).
    // Lazy-fetches control_mappings on first expand only; re-expand after a
    // collapse reuses the already-rendered panel (`$detailRow.data('loaded')`).
    function toggleControlDetail($groupRow) {
        var $detailRow = $groupRow.next('tr.sr-expand-row');
        if (!$detailRow.length) {
            return;
        }

        var expanded = $groupRow.attr('aria-expanded') === 'true';
        $groupRow.attr('aria-expanded', expanded ? 'false' : 'true').toggleClass('is-expanded', !expanded);
        $detailRow.toggleClass('d-none', expanded);

        if (expanded || $detailRow.data('loaded')) {
            return;
        }

        $detailRow.data('loaded', true);
        var $panel = $detailRow.find('.sr-expand-panel');
        var controlId = $groupRow.data('control-id');

        fetchControlMappings(controlId)
            .done(function (result) {
                renderControlMappings($panel, (result && result.data) ? result.data : {});
            })
            .fail(function () {
                $detailRow.data('loaded', false);
                renderDetailError($panel);
            });
    }

    // Toggles the read-only procedure row directly under `$testRow` (always
    // its next sibling). Same lazy-fetch/re-expand-without-refetch contract
    // as toggleControlDetail() above.
    function toggleTestDetail($testRow) {
        var $detailRow = $testRow.next('tr.sr-expand-row');
        if (!$detailRow.length) {
            return;
        }

        var expanded = $testRow.attr('aria-expanded') === 'true';
        $testRow.attr('aria-expanded', expanded ? 'false' : 'true');
        $detailRow.toggleClass('d-none', expanded);

        if (expanded || $detailRow.data('loaded')) {
            return;
        }

        $detailRow.data('loaded', true);
        var $panel = $detailRow.find('.sr-expand-panel');
        var testId = $testRow.data('test-id');

        fetchTestDetail(testId)
            .done(function (result) {
                renderTestProcedure($panel, (result && result.data) ? result.data : {});
            })
            .fail(function () {
                $detailRow.data('loaded', false);
                renderDetailError($panel);
            });
    }

    /* ---------------------------------------------------------------- *
     * Pager (footer zone)
     * ---------------------------------------------------------------- */

    function renderFooter(data) {
        var total = data.recordsFiltered || 0;
        var length = state.length;

        $pager.empty();

        if (!total) {
            $info.text('');
            return;
        }

        if (length === -1) {
            $info.text(formatTemplate(_lang['ShowingXToYOfZ'], { start: 1, end: total, total: total }));
            return;
        }

        var start = Math.min(state.start, Math.max(0, total - 1));
        var end = Math.min(start + length, total);
        $info.text(formatTemplate(_lang['ShowingXToYOfZ'], { start: start + 1, end: end, total: total }));

        var pageCount = Math.max(1, Math.ceil(total / length));
        var currentPage = Math.floor(start / length) + 1;
        if (pageCount <= 1) {
            return;
        }

        var $ul = $('<ul>', { 'class': 'pagination' });

        function addButton(label, page, disabled, current) {
            var classes = 'page-item' + (disabled ? ' disabled' : '') + (current ? ' active' : '');
            var $li = $('<li>', { 'class': classes });
            var $btn = $('<button>', { type: 'button', 'class': 'page-link', text: label });
            if (!disabled && !current) {
                $btn.on('click', function () {
                    state.start = (page - 1) * length;
                    loadGrid();
                });
            }
            $li.append($btn);
            $ul.append($li);
        }

        addButton(_lang['Previous'], currentPage - 1, currentPage === 1, false);

        var windowSize = 5;
        var startPage = Math.max(1, currentPage - Math.floor(windowSize / 2));
        var endPage = Math.min(pageCount, startPage + windowSize - 1);
        startPage = Math.max(1, endPage - windowSize + 1);

        for (var p = startPage; p <= endPage; p++) {
            addButton(String(p), p, false, p === currentPage);
        }

        addButton(_lang['Next'], currentPage + 1, currentPage === pageCount, false);

        $pager.append($ul);
    }



    /* ---------------------------------------------------------------- *
     * Column sorting
     * ---------------------------------------------------------------- */

    // Paint the header state from `state`. Called on every render so a sort
    // restored from the URL shows its arrow without a click.
    function syncSortHeaders() {
        // The Control column exists only in the sorted view. Toggling the
        // header here (rather than rebuilding the thead) keeps the server's
        // markup authoritative for everything else.
        $('#define-tests-table thead th.sr-control-col').toggleClass('d-none', !state.sort);

        $('#define-tests-table thead th.sr-sortable').each(function () {
            var $th = $(this);
            var active = state.sort && $th.data('sort') === state.sort;
            var $icon = $th.find('.sr-sort-icon');

            $th.toggleClass('is-sorted', !!active);
            $th.attr('aria-sort', active ? (state.dir === 'desc' ? 'descending' : 'ascending') : 'none');
            $icon
                .removeClass('fa-arrow-up-short-wide fa-arrow-down-wide-short fa-sort')
                .addClass(active ? (state.dir === 'desc' ? 'fa-arrow-down-wide-short' : 'fa-arrow-up-short-wide') : 'fa-sort');
        });
    }

    // Three states per column: ascending, descending, then off. "Off" matters
    // -- sorting flattens the control grouping, so a user needs a way back to
    // the grouped view without reloading the page.
    function toggleSort(key) {
        // "Control" isn't another ordering -- grouping BY control IS the
        // grouped view, so its header is the way back rather than a fourth
        // sort state to cycle through.
        if (key === 'control') {
            state.sort = '';
            state.dir = 'asc';
            state.start = 0;
            loadGrid();
            return;
        }

        if (state.sort !== key) {
            state.sort = key;
            state.dir = 'asc';
        } else if (state.dir === 'asc') {
            state.dir = 'desc';
        } else {
            state.sort = '';
            state.dir = 'asc';
        }

        // Any reorder invalidates the current offset: page 2 of the old order
        // has nothing to do with page 2 of the new one.
        state.start = 0;
        loadGrid();
    }

    /* ---------------------------------------------------------------- *
     * View test (read-only)
     * ---------------------------------------------------------------- */

    // One definition-list row. `html` selects the sink: rich-text fields keep
    // the author's formatting and are purified server-side at the
    // getTestById() output boundary; everything else goes out through .text().
    function viewRow($body, label, value, html) {
        var $row = $('<div>', { 'class': 'sr-view-row' });
        $('<div>', { 'class': 'sr-view-label', text: label }).appendTo($row);
        var $val = $('<div>', { 'class': 'sr-view-value' });
        var empty = (value === null || value === undefined || String(value).trim() === '');
        if (empty) {
            $val.addClass('sr-view-empty').text(_lang['NotSpecified'] || '');
        } else if (html) {
            $val.html(value);
        } else {
            $val.text(value);
        }
        $val.appendTo($row);
        $row.appendTo($body);
    }

    function viewCard($into, icon, title) {
        var $card = $('<section>', { 'class': 'sr-qcard' });
        var $head = $('<div>', { 'class': 'sr-qcard-head' });
        $('<span>', { 'class': 'sr-qcard-icon' })
            .append($('<i>', { 'class': 'fa ' + icon, 'aria-hidden': 'true' }))
            .appendTo($head);
        $('<h3>', { 'class': 'sr-qcard-title', text: title }).appendTo($head);
        $head.appendTo($card);
        var $body = $('<div>', { 'class': 'sr-qcard-body sr-view-grid' });
        $body.appendTo($card);
        $card.appendTo($into);
        return $body;
    }

    // A comma-joined list, or '' so viewRow() renders the empty state.
    function viewList(value) {
        // control_names arrives as an id => name MAP, teams/approvers as a
        // comma-joined string, tags as an array. Normalise all three rather
        // than letting an object reach .text() as "[object Object]".
        if (value && typeof value === 'object') {
            value = Array.isArray(value) ? value : Object.keys(value).map(function (k) { return value[k]; });
        }
        if (Array.isArray(value)) {
            return value.filter(function (v) { return v !== null && String(v).trim() !== ''; }).join(', ');
        }
        return (value === null || value === undefined) ? '' : String(value);
    }

    function renderTestView(test) {
        var $body = $('#test-view-body').empty();
        $('#test-view-title').text(test.name || _lang['ViewTest']);

        // Identity -- who owns this and what it applies to. Mirrors the Edit
        // modal's section order so the two read as the same object.
        var $identity = viewCard($body, 'fa-vial', _lang['Identity']);
        viewRow($identity, _lang['TestName'], test.name);
        viewRow($identity, _lang['Controls'], viewList(test.control_names));
        viewRow($identity, _lang['Tester'], test.tester_name);
        viewRow($identity, _lang['AdditionalStakeholders'], viewList(test.additional_stakeholder_names));
        viewRow($identity, _lang['Teams'], viewList(test.team_names));
        viewRow($identity, _lang['Approvers'], viewList(test.approver_names));
        viewRow($identity, _lang['Tags'], viewList(test.tags));
        var mins = parseInt(test.approximate_time, 10) || 0;
        viewRow($identity, _lang['ApproximateTime'], mins > 0 ? (mins + ' ' + (mins === 1 ? _lang['minute'] : _lang['minutes'])) : '');

        // Schedule -- the same summary chip the grid row shows, then the parts
        // that produced it, so the reader can check the chip rather than trust it.
        var $schedule = viewCard($body, 'fa-calendar-days', _lang['Schedule']);
        viewRow($schedule, _lang['Schedule'], test.schedule_summary);
        viewRow($schedule, _lang['AnchorDate'], formatDueDate(test.cadence_anchor_date));
        viewRow($schedule, _lang['LastTestDate'], formatDueDate(test.last_date));
        viewRow($schedule, _lang['NextTestDate'], formatDueDate(test.next_date));
        viewRow($schedule, _lang['AuditInitiationOffset'], test.audit_initiation_offset);

        // Procedure -- the rich-text half. These are the only .html() sinks here.
        var $proc = viewCard($body, 'fa-list-check', _lang['ProcedureAndEvidence']);
        viewRow($proc, _lang['Objective'], test.objective, true);
        viewRow($proc, _lang['TestSteps'], test.test_steps, true);
        viewRow($proc, _lang['ExpectedResults'], test.expected_results, true);
        viewRow($proc, _lang['TestMethod'], test.test_method);
        viewRow($proc, _lang['Sample'], test.sample, true);
        viewRow($proc, _lang['RequiredEvidence'], test.required_evidence, true);
    }

    /* ---------------------------------------------------------------- *
     * Render + fetch orchestration
     * ---------------------------------------------------------------- */

    function renderControls(data) {
        $tbody.empty();
        // Rebuilt from scratch each draw: the map only ever names suggestions the
        // current response carried, so a proposal that was created/dismissed and
        // dropped by the feed can't linger as a stale "Review & edit" source.
        proposalPayloads = {};
        syncSortHeaders();
        // Title pills: total tests (neutral) + overdue (attn, hidden at 0) --
        // global test-level totals from the feed (mirror the insights band).
        // Names the noun: this pill sits beside a grid whose visible rows are
        // CONTROLS, so a bare number reads as a control count. Its neighbour
        // already says "N Overdue", which made the silent one look like a
        // different kind of thing.
        var totalTests = data.total_tests || 0;
        $count
            .text(totalTests + ' ' + (totalTests === 1 ? _lang['Test'] : _lang['Tests']))
            .removeClass('d-none');
        var overdue = data.overdue_tests || 0;
        if (overdue > 0) {
            $overduePill.text(overdue + ' ' + (_lang['Overdue'] || 'Overdue')).removeClass('d-none');
        } else {
            $overduePill.addClass('d-none');
        }
        // Global per-filter totals from the feed. Kept in module scope because
        // decorateOptionCounts() labels the Status/Results options from them.
        quickCounts = data.quick_counts || {};

        renderTesterOptions(data.tester_options || []);
        renderFilterCounts(data.filter_counts || {});

        // Sorted responses carry `tests` and an empty `controls` -- branch
        // before the grouped path so the two renderers never half-run.
        if (data.sorted) {
            renderSortedTests(data);
            return;
        }

        var controls = data.controls || [];

        if (!controls.length) {
            if (!data.recordsTotal && !filtersActive()) {
                showEmptyState('nodata');
            } else {
                showEmptyState('noresults');
            }
            renderFooter(data);
            // Nothing rendered means nothing selectable: prune here too, or a
            // bulk bar survives over an empty grid.
            pruneSelectionToRendered();
            return;
        }

        showEmptyState(null);

        controls.forEach(function (control) {
            $tbody.append(buildGroupRow(control));
            // The SCF-mapping detail row always follows its group row
            // immediately (toggleControlDetail() relies on .next() for
            // this), whether or not the control has tests.
            $tbody.append(buildControlDetailRow(control));

            if (!control.tests || !control.tests.length) {
                $tbody.append(buildCoverageGapRow(control));
                return;
            }

            control.tests.forEach(function (test) {
                // A suggestion row (kind:'suggestion') is a pending AI proposal,
                // rendered as a draft (buildSuggestionRow); a real row gets the
                // test row + its procedure detail row. A suggestion has no
                // procedure detail row here -- that expand is Task B3.
                if (test.kind === 'suggestion') {
                    proposalPayloads[test.proposal_id] = test;
                    $tbody.append(buildSuggestionRow(test, control));
                    return;
                }
                $tbody.append(buildTestRow(test, control));
                // Likewise, the procedure detail row always follows its
                // test row immediately (toggleTestDetail() relies on
                // .next()).
                $tbody.append(buildTestDetailRow(test));
            });
        });

        renderFooter(data);
        pruneSelectionToRendered();
    }

    // Sorted view: one ordered list, no group rows. Each row carries the
    // control the group row would have supplied, because a sorted list has no
    // group to read it from.
    function renderSortedTests(data) {
        var tests = data.tests || [];

        if (!tests.length) {
            showEmptyState(data.recordsTotal ? 'noresults' : 'nodata');
            renderFooter(data);
            pruneSelectionToRendered();
            return;
        }

        showEmptyState(null);
        tests.forEach(function (test) {
            // Same suggestion/real split as the grouped view. state.sort is truthy
            // in this (sorted) path, so buildSuggestionRow renders its control
            // column just as buildTestRow's showControl=true does.
            if (test.kind === 'suggestion') {
                proposalPayloads[test.proposal_id] = test;
                $tbody.append(buildSuggestionRow(test, test.control));
                return;
            }
            $tbody.append(buildTestRow(test, test.control, true));
            $tbody.append(buildTestDetailRow(test));
        });

        renderFooter(data);
        pruneSelectionToRendered();
    }

    function buildFilters() {
        return {
            framework: state.framework,
            family: state.family,
            search: state.search,
            coverage: state.coverage,
            schedule: state.schedule,
            tag: state.tag,
            tester: state.tester,
            retired: state.retired,
            sort: state.sort,
            dir: state.dir,
            quick: state.quick,
            start: state.start,
            length: state.length,
        };
    }

    /* ---------------------------------------------------------------- *
     * Shareable views: every filter round-trips through the URL          *
     * ---------------------------------------------------------------- *
     * The whole point of holding the filters in one state object is that
     * a view can be handed to someone else. On every load the active
     * filters are written to the query string, so copying the address bar
     * copies what you are looking at; on arrival they are read back before
     * the first fetch, so the recipient lands on the same grid.
     *
     * Only NON-DEFAULT values are written -- a default view keeps a clean
     * /compliance/index.php with no query string, and a shared link
     * carries only what was deliberately chosen.
     *
     * history.replaceState (not pushState): filtering is refining one view,
     * not navigating, so it shouldn't bury the previous page under a dozen
     * back-button entries. This mirrors what applyInsightFromUrl() already
     * does when it strips ?insight=.
     */
    var URL_PARAMS = {
        search: 'search',
        coverage: 'coverage',
        schedule: 'schedule',
        tag: 'tag',
        tester: 'tester',
        retired: 'show',
    };

    function writeFiltersToUrl() {
        if (!window.history.replaceState) {
            return;
        }

        var params = new URLSearchParams();

        if (state.search) { params.set(URL_PARAMS.search, state.search); }
        // 'with' / 'active' are this page's defaults (see the state block).
        if (state.coverage && state.coverage !== 'with') { params.set(URL_PARAMS.coverage, state.coverage); }
        if (state.retired && state.retired !== 'active') { params.set(URL_PARAMS.retired, state.retired); }
        if (state.schedule) { params.set(URL_PARAMS.schedule, state.schedule); }
        if (state.tag) { params.set(URL_PARAMS.tag, state.tag); }
        if (state.tester) { params.set(URL_PARAMS.tester, state.tester); }
        // A sorted view is as shareable as a filtered one -- "here are the most
        // overdue tests" is a link, not an instruction to click a header.
        if (state.sort) {
            params.set('sort', state.sort);
            if (state.dir === 'desc') { params.set('dir', 'desc'); }
        }
        if (state.framework.length) { params.set('framework', state.framework.join(',')); }
        if (state.family.length) { params.set('family', state.family.join(',')); }

        var status = currentFlagIn(STATUS_FLAGS);
        if (status) { params.set('status', status); }
        var result = currentFlagIn(RESULT_FLAGS);
        if (result) { params.set('result', result); }

        // quick.untested isn't in either exclusive group -- it's the
        // coverage-gap shortcut the Untested Controls tile drills through to.
        if (state.quick.untested) { params.set('untested', '1'); }

        // Page position, so a shared link lands on the page the sender was
        // looking at -- and so an in-place refresh after an add/update doesn't
        // silently drop someone back to page 1 of a long list. 1-based here:
        // the URL is for humans, `start` is the API's offset.
        if (state.length !== 25) { params.set('size', state.length); }
        if (state.start > 0 && state.length > 0) {
            params.set('page', Math.floor(state.start / state.length) + 1);
        }

        var query = params.toString();
        window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
    }

    // Applies ?param= values to state BEFORE the first fetch. Unknown or
    // malformed values are ignored rather than guessed at, so a mangled link
    // degrades to the default view instead of an empty or misleading one.
    function readFiltersFromUrl() {
        var params = new URLSearchParams(window.location.search);
        if (!params.toString()) {
            return;
        }

        var search = params.get(URL_PARAMS.search);
        if (search) {
            state.search = search;
            $search.val(search);
        }

        var coverage = params.get(URL_PARAMS.coverage);
        if (['with', 'all', 'gaps'].indexOf(coverage) !== -1) {
            state.coverage = coverage;
            $coverageFilter.val(coverage);
        }

        var show = params.get(URL_PARAMS.retired);
        if (['active', 'all', 'retired_only'].indexOf(show) !== -1) {
            state.retired = show;
            $showFilter.val(show);
        }

        var sort = params.get('sort');
        if (['id', 'name', 'tester', 'schedule', 'last_date', 'last_result', 'next_due'].indexOf(sort) !== -1) {
            state.sort = sort;
            state.dir = (params.get('dir') === 'desc') ? 'desc' : 'asc';
        }

        var schedule = params.get(URL_PARAMS.schedule);
        if (['calendar', 'interval', 'manual'].indexOf(schedule) !== -1) {
            state.schedule = schedule;
            $scheduleFilter.val(schedule);
        }

        var tag = params.get(URL_PARAMS.tag);
        if (tag) {
            state.tag = tag;
            $tagFilter.val(tag);
        }

        var tester = parseInt(params.get(URL_PARAMS.tester), 10);
        if (tester > 0) {
            // The option itself arrives with the grid response; renderTesterOptions()
            // keeps a placeholder for a selection it doesn't recognise.
            state.tester = tester;
        }

        var status = params.get('status');
        if (STATUS_FLAGS.indexOf(status) !== -1) {
            state.quick[status] = true;
        }

        var result = params.get('result');
        if (RESULT_FLAGS.indexOf(result) !== -1) {
            state.quick[result] = true;
        }

        if (params.get('untested')) {
            state.quick.untested = true;
        }

        // Page size first: `page` is meaningless without the size it counts in.
        // -1 is the "All" option; anything else must be one of the offered
        // sizes, so a hand-edited link can't ask for 100000 rows.
        var size = parseInt(params.get('size'), 10);
        if (size === -1 || [10, 25, 50, 100].indexOf(size) !== -1) {
            state.length = size;
            $lengthSelect.val(String(size));
        }

        var page = parseInt(params.get('page'), 10);
        if (page > 1 && state.length > 0) {
            state.start = (page - 1) * state.length;
        }

        var idList = function (raw) {
            return String(raw || '').split(',')
                .map(function (v) { return parseInt(v, 10); })
                .filter(function (v) { return v > 0; });
        };
        state.framework = idList(params.get('framework'));
        state.family = idList(params.get('family'));

        syncFilterSelects();
    }

    function loadGrid() {
        // Selection is scoped to what is on screen: a row the next render
        // drops (searched away, filtered out, paged past, retired) leaves the
        // selection with it, so a bulk action can never reach a row the user
        // can no longer see. That pruning happens AFTER the render, in
        // pruneSelectionToRendered() -- not here.
        //
        // It used to happen here, by emptying state.selected up front. That
        // discarded selections the render was about to put straight back:
        // any reload landing after a click (the search debounce firing a beat
        // late, a mutation refresh, the band's own reload) silently unchecked
        // a row the user had just checked and dropped the bulk bar out from
        // under them, on a grid whose rows hadn't changed at all.

        // Returns the request promise (Phase 2, Task 7) so the init
        // sequence can chain applyInsightFromUrl() onto the *first* load's
        // completion instead of racing it with a second, filtered load --
        // every existing caller (window.reloadDefineTestsGrid, the filter/
        // pager/bulk handlers below) already ignores the return value, so
        // this is additive.
        writeFiltersToUrl();
        syncFilterToggleCount();

        return fetchGrid(buildFilters())
            .done(function (result) {
                var data = (result && result.data) ? result.data : { controls: [], recordsTotal: 0, recordsFiltered: 0 };

                // Retiring or deleting the last rows on the final page leaves
                // state.start past the end of a now-shorter result set: the
                // server correctly returns no controls for that offset, and the
                // grid would render "no results" over a set that still HAS
                // rows, one page back, with a footer count that contradicts it.
                // Step back a page and refetch. Every mutation path (bulk bar,
                // single-row retire/restore/delete) funnels through loadGrid(),
                // so fixing it here covers all of them rather than asking each
                // handler to remember.
                // state.length > 0 matters: the "All" page size is -1, and
                // subtracting it would step start FORWARD, turning a one-shot
                // backward clamp into an unbounded refetch loop. Unreachable
                // today (every path that sets -1 also zeroes start), but the
                // guard belongs where the arithmetic is, not in the callers.
                if (state.start > 0 && state.length > 0 && data.recordsFiltered > 0 && !(data.controls || []).length) {
                    state.start = Math.max(0, state.start - state.length);
                    loadGrid();
                    return;
                }

                renderControls(data);
                // Re-decide the fold now the rows exist. Called HERE rather
                // than inside renderControls() so it covers that function's
                // several early returns (both empty states, and the sorted
                // branch) with one call, and called SYNCHRONOUSLY rather than
                // through the frame-coalescing scheduler so the grid is never
                // painted at the wrong fold. renderControls() covers what the
                // table CONTAINS -- the sorted view's Control column arrives
                // this way -- while the observer below covers what it FITS
                // INTO.
                evaluateColumnBudget();
            })
            .fail(function () {
                showEmptyState('error');
            });
    }

    /**
     * A grid reload that also refreshes the insights band.
     *
     * Every count in that band -- Total, Passing, Failing, Due soon, Overdue,
     * Untested controls -- is computed server-side per tile, so adding,
     * retiring or deleting a test changes them all. The grid re-fetches itself,
     * but the tiles are separate widgets and were left showing the numbers from
     * page load: the row would vanish from the table while "6 Tests" and the
     * Overdue count sat there contradicting it until a refresh.
     *
     * Only MUTATIONS call this. Searching, filtering and paging also reload the
     * grid, and the tiles are deliberately unfiltered totals -- refreshing them
     * there would fire six widget requests per keystroke to redraw the same
     * numbers.
     */
    function reloadAfterMutation() {
        var promise = loadGrid();

        // srRefreshLayoutWidgets() is published by includes/Widgets/UILayout.php
        // and no-ops for a layout that isn't on the page, so pages that render
        // the grid without the band (audit_initiation.php) need no guard beyond
        // this existence check.
        if (typeof window.srRefreshLayoutWidgets === 'function') {
            window.srRefreshLayoutWidgets('define_tests_insights');
        }

        return promise;
    }

    // Exposed globally (Issue 6) so compliance.js's Add-Test AJAX submit
    // handler -- openAddTestModal() and the rest of the Add-Test modal's
    // lifecycle already live there, shared across pages -- can trigger an
    // in-place refresh after a successful create instead of a full page
    // reload. Mirrors the reverse direction: this file already duck-type
    // calls openAddTestModal() from compliance.js the same way.
    window.reloadDefineTestsGrid = reloadAfterMutation;

    /* ---------------------------------------------------------------- *
     * Batch select + retire/delete (Task 8)
     * ---------------------------------------------------------------- */

    // How many of the currently-selected rows hold a test that lives on more
    // than one control. Bulk retire/delete act on the TEST, so for those rows
    // the action reaches controls the user isn't looking at -- the confirms say
    // so. Read off the ROW's data-control-count: it is always rendered, unlike
    // the unlink button, which only exists for edit_tests holders.
    function selectedSharedCount() {
        var shared = 0;
        $tbody.find('.row-select:checked').each(function () {
            var count = parseInt($(this).closest('tr').data('control-count'), 10) || 1;
            if (count > 1) {
                ++shared;
            }
        });
        return shared;
    }

    // Appends (or clears) the shared-test warning under a bulk confirm's title.
    // Takes two keys because "1 of these is" and "N of these are" differ by more
    // than a digit -- a template with {n}=1 would read "1 of these are".
    function setBulkScopeNote(titleSelector, oneKey, manyKey) {
        var $title = $(titleSelector);
        var $note = $title.next('.sr-confirm-sub');
        if (!$note.length) {
            $note = $('<p>', { 'class': 'sr-confirm-sub' }).insertAfter($title);
        }
        var shared = selectedSharedCount();
        if (shared === 1) {
            $note.text(_lang[oneKey] || '').show();
        } else if (shared > 1) {
            $note.text(fillTemplate(_lang[manyKey], { n: shared })).show();
        } else {
            $note.text('').hide();
        }
    }

    function selectedIds() {
        return Object.keys(state.selected).map(Number);
    }

    // Selected suggestion proposal ids ("Create selected" acts on these).
    function selectedProposalIds() {
        return Object.keys(state.selectedProposals).map(Number);
    }

    // Reflects state.selected onto the currently-rendered row checkboxes,
    // updates the header select-all tri-state, and swaps the toolbar for
    // the bulk bar once at least one row is selected. Called after every
    // render and after every selection change (not just on click) so the
    // two stay in sync regardless of what triggered the change.
    /**
     * Drops any selected id the current render doesn't show, then syncs the
     * bar. Called at the end of every render, so the selection can only ever
     * name rows the user is looking at -- see the note in loadGrid().
     */
    function pruneSelectionToRendered() {
        var renderedTests = {};
        var renderedProposals = {};
        $tbody.find('.row-select').each(function () {
            var $cb = $(this);
            if ($cb.data('kind') === 'suggestion') {
                renderedProposals[$cb.data('proposal-id')] = true;
            } else {
                renderedTests[$cb.data('test-id')] = true;
            }
        });

        Object.keys(state.selected).forEach(function (id) {
            // Object keys are strings; the checkbox's data-test-id comes back
            // from jQuery as a number, so compare on the same side.
            if (!renderedTests[Number(id)]) {
                delete state.selected[id];
            }
        });
        Object.keys(state.selectedProposals).forEach(function (id) {
            if (!renderedProposals[Number(id)]) {
                delete state.selectedProposals[id];
            }
        });

        updateBulkBar();
    }

    function updateBulkBar() {
        var $rowChecks = $tbody.find('.row-select');
        $rowChecks.each(function () {
            var $cb = $(this);
            if ($cb.data('kind') === 'suggestion') {
                $cb.prop('checked', !!state.selectedProposals[$cb.data('proposal-id')]);
            } else {
                $cb.prop('checked', !!state.selected[$cb.data('test-id')]);
            }
        });

        var total = $rowChecks.length;
        var checked = $rowChecks.filter(':checked').length;
        $selectAll.prop('checked', total > 0 && checked === total);
        $selectAll.prop('indeterminate', checked > 0 && checked < total);

        var ids = selectedIds();
        var proposalIds = selectedProposalIds();
        var selectedTotal = ids.length + proposalIds.length;

        // "Create selected" only applies to a suggestion selection -- show/enable
        // it when at least one suggestion row is picked (Retire/Delete keep
        // acting on the real-test selection). The button is server-gated to
        // $ai_gen_enabled, so it may not be in the DOM at all.
        $bulkBar.find('#define-tests-bulk-create').toggleClass('d-none', proposalIds.length === 0);

        if (selectedTotal) {
            $toolbar.addClass('d-none');
            $bulkBar.removeClass('d-none');
            $bulkCount.text(String(_lang['NSelected'] || '').replace('{n}', selectedTotal));
        } else {
            $bulkBar.addClass('d-none');
            $toolbar.removeClass('d-none');
        }
    }

    function clearSelection() {
        state.selected = {};
        state.selectedProposals = {};
        updateBulkBar();
    }

    /** Shuts every open row-actions overflow menu (see the toggle handler). */
    function closeRowActionMenus() {
        // The scroller's clip is lifted only for as long as a menu needs it --
        // leaving .is-unclipped behind would hand the table a permanently
        // unclipped scroller, so a genuinely wide table could then spill its
        // rows out of the card instead of scrolling them.
        $tbody.closest('.sr-table-scroll').removeClass('is-unclipped');
        $tbody.find('.sr-row-actions-wrap.is-open')
            .removeClass('is-open is-up')
            .find('.sr-row-actions-toggle')
            .attr('aria-expanded', 'false');
    }

    /**
     * Gives an already-open menu somewhere to go: lifts the table scroller's
     * clip when it is only clipping, and flips the menu upward when it still
     * won't fit below. (_tables.scss owns what "unclipped" and "up" look like;
     * both are measurements, so the decision lives here.)
     *
     * Every row sits inside .sr-table-scroll, whose `overflow-x: auto` computes
     * overflow-y to `auto` along with it -- the two axes cannot be auto and
     * visible at once -- so a menu popped from a row near the bottom of the
     * list is clipped VERTICALLY by a container that only ever wanted to scroll
     * horizontally. Measured on this page before this existed, with the last
     * row's menu open: at 1400, 1200 and 1024 the menu ran 134px past the
     * scroller and elementFromPoint() on ALL FIVE items (View / Edit / History
     * / Retire / Delete) returned something else, i.e. the menu was open and
     * not one of its actions was clickable; at 900 it ran 65px past and Retire
     * and Delete were unreachable.
     *
     * Unclipping fixes that wherever the table isn't actually scrolling
     * sideways, which is the normal case in this tier -- the tier hides columns
     * precisely so the row fits. Where it IS scrolling the clip has to stay, and
     * the flip is what's left.
     *
     * This is the same treatment governance-frameworks.js applies to the
     * control table and the framework rail; _tables.scss shipped both classes
     * for both pages and Define Tests was the one page never opting in.
     *
     * Must run AFTER .is-open -- a display:none menu measures 0 high.
     */
    function orientRowActionMenu($wrap) {
        $wrap.removeClass('is-up');
        var $menu = $wrap.find('.sr-row-actions');
        if (!$wrap.length || !$menu.length) { return; }

        var $scroller = $wrap.closest('.sr-table-scroll');
        if ($scroller.length && $scroller[0].scrollWidth <= $scroller[0].clientWidth) {
            $scroller.addClass('is-unclipped');
            $scroller = $();          // no longer a clipping ancestor
        }

        var wrapRect = $wrap[0].getBoundingClientRect();
        var menuHeight = $menu[0].getBoundingClientRect().height;
        var scrollerRect = $scroller.length ? $scroller[0].getBoundingClientRect() : null;
        var floor = scrollerRect ? Math.min(scrollerRect.bottom, window.innerHeight) : window.innerHeight;
        var ceiling = scrollerRect ? Math.max(scrollerRect.top, 0) : 0;

        // Only flip when down doesn't fit AND up does -- a menu with room on
        // neither side is better left opening downward, where at least its
        // first item is the one nearest the toggle that opened it.
        if (wrapRect.bottom + 4 + menuHeight > floor && wrapRect.top - 4 - menuHeight > ceiling) {
            $wrap.addClass('is-up');
        }
    }

    // ===== The column budget, measured rather than predicted ================
    //
    // _tables.scss folds this grid's columns in rungs, and each rung has always
    // had a viewport @media: 1500 stands Test ID + Tester down, 1400 folds Last
    // Tested and turns the action cluster into a ⋯ menu, 1100 drops Schedule,
    // 900 rebuilds the row as a stacked block. A viewport width, though, is a
    // PREDICTION of the fit. The fit itself is a fact about the table's
    // min-content width against the room the card has, and the two disagree.
    //
    // Measured live on this page at 2px resolution, the @media-only ladder left
    // two residual overflow bands, each sitting immediately ABOVE a threshold
    // placed slightly too low:
    //
    //   1502-1540px   the full column set needs 1265px; the card offers
    //                 1212-1250. The table froze at its 1265px min-content
    //                 inside the scroller and Actions -- the LAST column, so at
    //                 the table's right edge -- hung up to 53px outside it.
    //   1102-1140px   the same one rung down: 852 needed, 812-850 offered, up
    //                 to 40px over, closed 2px lower by the 1100 fold.
    //
    // Nudging the thresholds cannot fix it, because there is no single correct
    // value. The SORTED (flat) view carries a Control column the grouped view
    // does not, which moves min-content by ~190px: measured across 900-1920px
    // in 10px steps, the sorted view overflowed at 70 of 103 sampled widths, by
    // up to 236px. One number cannot serve two column sets. Nor can a container
    // query, which is the obvious next idea -- it measures the CONTAINER, and
    // the card is the same width in both views; what differs is the TABLE's
    // min-content. This is the identical trap governance-frameworks.js
    // documents at length, sprung by the identical cause (a column added to one
    // scope only), and it has the identical answer: read the two quantities the
    // browser has already computed instead of predicting them.
    //
    //   scrollWidth   what the column set needs (the table is pinned at its
    //                 min-content inside the scroller, which IS the failure
    //                 mode -- so this is the requirement, not an estimate)
    //   clientWidth   the room the card actually has
    //
    // The rungs keep their @media, unchanged, so a page with no JS behaves
    // exactly as it does today. This ladder only ever folds FURTHER than the
    // stylesheet did -- it cannot un-fold a tier the @media applied, because a
    // class that restates rules already in force does not move scrollWidth.
    // Measured after this change: zero overflow at every width from 900 to
    // 1920px, in BOTH views.
    //
    // Sidebar-, zoom- and font-size-aware for free, because none of those fire
    // a window resize but all of them change what the card measures -- which is
    // also why the observer below watches the card rather than the window.
    var COLUMN_BUDGET_RUNGS = ['sr-dt-fold-ids', 'sr-dt-fold-compact', 'sr-dt-fold-sched', 'sr-dt-queue'];

    function evaluateColumnBudget() {
        var $table = $('#define-tests-table');
        if (!$table.length) { return; }

        var card = $table.closest('.sr-table-card')[0];
        var scroll = $table.closest('.sr-table-scroll')[0];
        if (!card || !scroll) { return; }

        // A hidden or empty scroller reports 0/0, and 0 > 0 is false -- which
        // reads as "everything fits" and would strip the fold off a table that
        // isn't there. Keep the standing decision; the next render re-measures.
        if (!scroll.clientWidth) { return; }

        // No guard for .is-unclipped here, deliberately. orientRowActionMenu()
        // lifts the scroller's clip to `overflow: visible` while a row menu is
        // open, and the worry is that an element which is no longer a scroll
        // container would report scrollWidth == clientWidth -- which would read
        // as "everything fits" and strip a fold that was the only reason it did.
        // Measured on this page at 1520px with the clip lifted: scrollWidth
        // still reports 1265 against a 1230 clientWidth, i.e. the overflow is
        // still visible to this test. Nothing to guard.

        // Rung 0's state: none of our own classes. Stripped BEFORE anything is
        // read, so each rung's input is the page rather than the output of the
        // previous decision. That is the property that makes this settle in one
        // pass instead of oscillating between two tiers.
        card.classList.remove.apply(card.classList, COLUMN_BUDGET_RUNGS);

        // The queue tier's own @media (max-width: 900px) is deliberately still
        // a width, because it answers a different question -- "is this a phone,
        // so should the row stop being a row and become a stacked block?" --
        // which is about how the row is READ, not about whether it fits. Where
        // that query is in force the rows are already stacked, so the table
        // cannot overflow, a fit test would read "everything fits", and folding
        // on top of it would hide the Schedule and Tester cells that tier
        // deliberately RESTORES as lines of the block.
        //
        // Detected from that tier's own output -- it hides the thead -- rather
        // than by restating its 900px, which would be a second copy of the one
        // number this function exists to stop keeping. Read with our own
        // classes already stripped, so a hidden thead can only mean the @media
        // and never our own last answer.
        var thead = $table.find('thead')[0];
        if (thead && window.getComputedStyle(thead).display === 'none') { return; }

        // Climb only as far as the measurement asks. A rung the @media had
        // already applied is a no-op -- the class restates rules in force, so
        // scrollWidth does not move -- and the ladder carries straight on to the
        // rung that actually buys the width back.
        for (var i = 0; i < COLUMN_BUDGET_RUNGS.length; i++) {
            if (scroll.scrollWidth <= scroll.clientWidth) { return; }
            card.classList.add(COLUMN_BUDGET_RUNGS[i]);
        }
    }

    // Coalesces the resize/observer firehose to one measurement per frame.
    // Callers that have just rebuilt the table call evaluateColumnBudget()
    // directly instead, so the fold is correct before anything can observe it.
    var columnBudgetFrame = 0;
    function scheduleColumnBudgetEval() {
        if (columnBudgetFrame) { return; }
        columnBudgetFrame = window.requestAnimationFrame(function () {
            columnBudgetFrame = 0;
            evaluateColumnBudget();
        });
    }

    function setBulkBusy(busy) {
        $bulkBar.find('#define-tests-bulk-retire, #define-tests-bulk-delete, #define-tests-bulk-create, #define-tests-bulk-clear')
            .toggleClass('is-busy', busy)
            .prop('disabled', busy);
    }

    function retireTestRequest(id) {
        return $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/compliance/tests/' + id + '/retire', headers: csrfHeaders() });
    }

    function restoreTestRequest(id) {
        return $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/compliance/tests/' + id + '/restore', headers: csrfHeaders() });
    }

    // Removes ONE (test, control) pairing. DELETE on the mapping itself rather
    // than a PATCH of the whole controls list, so two people detaching
    // different controls at once can't clobber each other.
    function detachTestFromControlRequest(id, controlId) {
        return $.ajax({
            type: 'DELETE',
            url: BASE_URL + '/api/v2/compliance/tests/' + id + '/controls/' + controlId,
            headers: csrfHeaders(),
        });
    }

    function deleteTestRequest(id) {
        return $.ajax({ type: 'DELETE', url: BASE_URL + '/api/v2/compliance/tests/' + id, headers: csrfHeaders() });
    }

    /* ---------------------------------------------------------------- *
     * AI suggestion actions (Task B3) -- reuse the Plan A proposal      *
     * endpoints. Approve applies the proposal server-side (writes the   *
     * real test + its M:N control junction); reject dismisses it. Both  *
     * carry a JSON body, so they mirror fetchGrid()'s JSON+CSRF shape   *
     * rather than applyCommonTest()'s form-encoded PATCH -- the handler *
     * (api/v2/includes/artificial_intelligence.php) reads the decision  *
     * from php://input as JSON.                                         *
     * ---------------------------------------------------------------- */
    function patchProposalDecision(id, decision) {
        return $.ajax({
            type: 'PATCH',
            url: BASE_URL + '/api/v2/ai/proposals/' + id,
            contentType: 'application/json',
            headers: csrfHeaders(),
            data: JSON.stringify({ decision: decision }),
        });
    }

    function approveProposalRequest(id) {
        return patchProposalDecision(id, 'approve');
    }

    function rejectProposalRequest(id) {
        return patchProposalDecision(id, 'reject');
    }

    // Exposed for compliance.js's Add-test success handler: a "Review & edit"
    // that ends in Save has materialised the suggestion as a real test, so its
    // source proposal must be consumed (rejected). Mirrors the existing
    // window.reloadDefineTestsGrid bridge between the two files.
    window.rejectDefineTestsProposal = rejectProposalRequest;

    // Enqueue AI control-test generation for one control (Plan B/C trigger,
    // shipped by the AI Extra). Async: a 202 means "queued", not "here are the
    // tests" -- the proposals land on a later grid draw.
    function generateTestsRequest(controlId) {
        return $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/ai/controls/' + controlId + '/generate-tests', headers: csrfHeaders() });
    }

    // Calls `requestFn` for each id in turn (sequential, not parallel -- keeps
    // the request rate predictable and mirrors the codebase's existing bulk
    // pattern, e.g. self-assessment.js's runSequential()), resolving with
    // {ok, fail} counts once every id has settled -- a per-id failure never
    // aborts the remaining ids.
    function runSequential(ids, requestFn) {
        var ok = 0;
        var fail = 0;
        function next(i) {
            if (i >= ids.length) {
                return $.Deferred().resolve({ ok: ok, fail: fail }).promise();
            }
            return requestFn(ids[i]).then(
                function () { ok++; return next(i + 1); },
                function () { fail++; return next(i + 1); }
            );
        }
        return next(0);
    }

    // Shared tail for both bulk actions: run sequentially, clear selection,
    // reload the grid (so retired/deleted rows drop out of the default
    // view immediately), and surface a toast only when something failed --
    // a full success is already visible in the reloaded grid.
    function runBulkAction(ids, requestFn) {
        setBulkBusy(true);
        return runSequential(ids, requestFn).then(function (summary) {
            setBulkBusy(false);
            clearSelection();
            reloadAfterMutation();
            if (summary.fail) {
                var message = String(_lang['BulkPartialFailure'] || '')
                    .replace('{n}', summary.ok)
                    .replace('{total}', ids.length);
                showAlertFromMessage(message, false);
            }
        });
    }

    /* ---------------------------------------------------------------- *
     * Filters / toolbar wiring
     * ---------------------------------------------------------------- */

    function resetFilters() {
        state.search = '';
        state.framework = [];
        state.family = [];
        state.coverage = 'with';
        state.schedule = null;
        state.tag = null;
        state.tester = null;
        state.retired = 'active';
        state.sort = '';
        state.dir = 'asc';
        state.quick = defaultQuickFlags();
        state.start = 0;

        $search.val('');
        $frameworkFilter.find('option').prop('selected', false);
        $familyFilter.find('option').prop('selected', false);
        srSelectRender($frameworkFilter);
        srSelectRender($familyFilter);
        $coverageFilter.val('with');
        $scheduleFilter.val('');
        $tagFilter.val('');
        $testerFilter.val('');
        $statusFilter.val('');
        $resultFilter.val('');
        $showFilter.val('active');
    }

    // Sets a quick-filter chip's on/off state in both `state.quick` and its
    // DOM class -- the single source of truth the toolbar's own click
    // handler (toggle, below) and activateQuickFilter() (force-on, used by
    // the insight-band drill-through) both route through, so the two can
    // never drift on how a chip's active state is represented. Returns
    // false (no-op) for an unknown key, e.g. a stale/unmapped chip.
    // Status and Results are single-choice views onto the quick flags, so
    // picking one clears its siblings in the same group -- "Overdue AND Due
    // soon" is not a state a test can be in, and neither is Passing AND
    // Failing.
    var STATUS_FLAGS = ['overdue', 'due_soon', 'scheduled'];
    var RESULT_FLAGS = ['passing', 'failing', 'inconclusive', 'not_tested'];

    function currentFlagIn(group) {
        for (var i = 0; i < group.length; i++) {
            if (state.quick[group[i]]) {
                return group[i];
            }
        }
        return '';
    }

    function syncFilterSelects() {
        if ($statusFilter.length) {
            $statusFilter.val(currentFlagIn(STATUS_FLAGS));
            srSelectRender($statusFilter);
        }
        if ($resultFilter.length) {
            $resultFilter.val(currentFlagIn(RESULT_FLAGS));
            srSelectRender($resultFilter);
        }
    }

    function applyExclusiveGroup(group, chosen) {
        group.forEach(function (flag) {
            state.quick[flag] = (flag === chosen);
        });
        state.start = 0;
        loadGrid();
    }

    function setQuickFilter(key, active) {
        if (!Object.prototype.hasOwnProperty.call(state.quick, key)) {
            return false;
        }
        state.quick[key] = active;
        // The filters ARE the selects now, so reflecting the flag there is the
        // whole of "showing it as active": an insights-tile drill-through lands
        // with the select reading what the grid is filtered by, rather than a
        // filtered grid sitting above selects that still say "All".
        syncFilterSelects();
        return true;
    }

    // Programmatic (force-on) quick-filter activation -- used by
    // applyInsightFromUrl() to land the grid pre-filtered exactly as if the
    // user had chosen that option in the Status/Results select themselves,
    // through the same state.quick -> syncFilterSelects() -> loadGrid() path.
    // A drill-through is not a second filtering mechanism.
    function activateQuickFilter(key) {
        if (!setQuickFilter(key, true)) {
            return;
        }
        state.start = 0;
        loadGrid();
    }

    // Programmatic Coverage-select activation -- used by
    // applyInsightFromUrl() below for the "coverage gaps" insight tile.
    // Setting the <select>'s value and firing its own 'change' handler
    // (bound further down) reuses that exact state.coverage/loadGrid()
    // path rather than duplicating it here.
    function setCoverage(value) {
        if ($coverageFilter.length) {
            $coverageFilter.val(value).trigger('change');
        }
    }

    // Insight-band drill-through (Phase 2, Task 7) -- a KPI tile on the
    // insights band links to this page as index.php?insight=<key>. On load,
    // once the grid's own unfiltered render has finished (called from the
    // loadGrid().done() in the init block below -- never raced against it),
    // map the key to the same quick-filter/coverage action a user clicking
    // the equivalent chip/select would trigger, then strip the query param
    // via history.replaceState() so a later manual refresh doesn't re-pin a
    // filter the user has since changed or cleared (mirrors the existing
    // history.replaceState() in compliance/index.php's own inline script),
    // and smooth-scroll the grid card into view since the insights band
    // sits above it on the page. A bare index.php / unknown/missing
    // `insight` param is a no-op -- the grid's default cleared-filter state
    // stands.
    function applyInsightFromUrl() {
        var key = pendingInsightKey;
        pendingInsightKey = null;
        if (!key) {
            return;
        }

        var actions = {
            kpi_dt_overdue: function () { activateQuickFilter('overdue'); },
            kpi_dt_due_soon: function () { activateQuickFilter('due_soon'); },
            kpi_dt_failing: function () { activateQuickFilter('failing'); },
            kpi_dt_passing: function () { activateQuickFilter('passing'); },
            kpi_dt_coverage_gaps: function () { setCoverage('gaps'); },
        };

        if (Object.prototype.hasOwnProperty.call(actions, key)) {
            actions[key]();
        }

        // No explicit strip needed any more: the load each action above triggers
        // ends in writeFiltersToUrl(), which rewrites the query string from
        // state and therefore drops ?insight= while restating the filters --
        // including the one this drill-through just applied, so the resulting
        // URL is itself shareable.
        writeFiltersToUrl();

        var $card = $('.sr-table-card');
        if ($card.length) {
            $card[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    $(function () {
        var allFrameworksText = (typeof _lang !== 'undefined' && _lang['AllFrameworks']) ? _lang['AllFrameworks'] : 'All frameworks';
        var allFamiliesText = (typeof _lang !== 'undefined' && _lang['AllFamilies']) ? _lang['AllFamilies'] : 'All families';

        // Framework/Family move to sr-select alongside the single-choice
        // filters, so every filter on the page is the same control with the
        // same count chips. Empty = no filter = all controls, which is what
        // the placeholder says; there's deliberately no select-all, since
        // empty already means all (and a select-all would subtly differ by
        // dropping frameworkless controls).
        srSelectEnhance($frameworkFilter, allFrameworksText);
        srSelectEnhance($familyFilter, allFamiliesText);

        $search.on('input', debounce(function () {
            state.search = $search.val();
            state.start = 0;
            loadGrid();
        }, 300));

        $frameworkFilter.on('change', function () {
            state.framework = ($frameworkFilter.val() || []).map(Number);
            state.start = 0;
            loadGrid();
        });

        $familyFilter.on('change', function () {
            state.family = ($familyFilter.val() || []).map(Number);
            state.start = 0;
            loadGrid();
        });

        $coverageFilter.on('change', function () {
            state.coverage = $coverageFilter.val() || 'with';
            state.start = 0;
            loadGrid();
        });

        $scheduleFilter.on('change', function () {
            state.schedule = $scheduleFilter.val() || null;
            state.start = 0;
            loadGrid();
        });

        $statusFilter.on('change', function () {
            applyExclusiveGroup(STATUS_FLAGS, $statusFilter.val() || '');
        });

        $resultFilter.on('change', function () {
            applyExclusiveGroup(RESULT_FLAGS, $resultFilter.val() || '');
        });

        $testerFilter.on('change', function () {
            var value = parseInt($testerFilter.val(), 10);
            state.tester = value > 0 ? value : null;
            state.start = 0;
            loadGrid();
        });

        $tagFilter.on('change', function () {
            state.tag = $tagFilter.val() || null;
            state.start = 0;
            loadGrid();
        });

        $showFilter.on('change', function () {
            state.retired = $showFilter.val() || 'active';
            state.start = 0;
            loadGrid();
        });

        $lengthSelect.on('change', function () {
            state.length = parseInt($lengthSelect.val(), 10) || 25;
            state.start = 0;
            loadGrid();
        });

        $(document).on('click', '#define-tests-clear-filters', function () {
            resetFilters();
            loadGrid();
        });

        $(document).on('click', '#define-tests-retry', function () {
            loadGrid();
        });

        $(document).on('click', '#define-tests-toolbar-add', function () {
            if (typeof openAddTestModal === 'function') {
                openAddTestModal();
            }
        });

        // Control group expand (Task 7) -- clicking anywhere on the group
        // header row toggles its SCF-mapping detail panel, except the
        // "+ Add test" button, which keeps its own click behavior.
        $(document).on('click', '#define-tests-tbody tr.sr-group-row', function (e) {
            if ($(e.target).closest('.sr-group-add').length) {
                return;
            }
            toggleControlDetail($(this));
        });

        // Test row expand (Task 7) -- clicking the test name OR its caret
        // toggles the read-only procedure detail panel.
        $(document).on('click', '#define-tests-tbody tr.sr-test-row .sr-test-name, #define-tests-tbody tr.sr-test-row .sr-test-caret', function () {
            toggleTestDetail($(this).closest('tr.sr-test-row'));
        });

        /* ------------------------------------------------------------ *
         * Batch select + retire/delete (Task 8)
         * ------------------------------------------------------------ */

        // Header select-all -- selects/deselects every test row rendered on
        // the current page (not the whole filtered result set; see the
        // file-header comment).
        $selectAll.on('change', function () {
            var checkAll = $selectAll.prop('checked');
            $tbody.find('.row-select').each(function () {
                var $cb = $(this);
                if ($cb.data('kind') === 'suggestion') {
                    var proposalId = $cb.data('proposal-id');
                    if (checkAll) {
                        state.selectedProposals[proposalId] = true;
                    } else {
                        delete state.selectedProposals[proposalId];
                    }
                } else {
                    var id = $cb.data('test-id');
                    if (checkAll) {
                        state.selected[id] = true;
                    } else {
                        delete state.selected[id];
                    }
                }
            });
            updateBulkBar();
        });

        // Per-row checkbox (delegated -- rows are rebuilt on every render). A
        // suggestion checkbox carries data-kind='suggestion' and a proposal id
        // (no test id), so it toggles the separate selectedProposals set that
        // "Create selected" reads; a real-test checkbox toggles `selected`.
        $tbody.on('change', '.row-select', function () {
            var $cb = $(this);
            var checked = $cb.prop('checked');
            if ($cb.data('kind') === 'suggestion') {
                var proposalId = $cb.data('proposal-id');
                if (checked) {
                    state.selectedProposals[proposalId] = true;
                } else {
                    delete state.selectedProposals[proposalId];
                }
            } else {
                var id = $cb.data('test-id');
                if (checked) {
                    state.selected[id] = true;
                } else {
                    delete state.selected[id];
                }
            }
            updateBulkBar();
        });

        // Row-actions overflow (compact/queue tiers, design-system.md 6b). The
        // toggle is display:none at full width, so this handler is inert there
        // -- the cluster is simply on screen. Delegated, because rows are
        // rebuilt on every render.
        $tbody.on('click', '.sr-row-actions-toggle', function (e) {
            // The row's own click handler opens the procedure drawer; opening a
            // menu is not asking to expand the row.
            e.stopPropagation();

            var $wrap = $(this).closest('.sr-row-actions-wrap');
            var wasOpen = $wrap.hasClass('is-open');
            closeRowActionMenus();
            if (!wasOpen) {
                $wrap.addClass('is-open').find('.sr-row-actions-toggle').attr('aria-expanded', 'true');
                orientRowActionMenu($wrap);
            }
        });

        // Anywhere else, including another row's toggle (handled above by
        // closing everything first) and any action inside the menu, which
        // does its own thing and should leave the menu shut behind it.
        $(document).on('click.srrowactions', function () {
            closeRowActionMenus();
        });

        $(document).on('keydown.srrowactions', function (e) {
            if (e.key === 'Escape') {
                closeRowActionMenus();
            }
        });

        // Narrow-width filter sheet (design-system.md 6b). The button is
        // display:none at full width, where the filter row is simply on screen,
        // so this handler is inert there.
        $(document).on('click', '#define-tests-filters-toggle', function () {
            var $toggle = $(this);
            var open = $('#define-tests-quickfilters').toggleClass('is-open').hasClass('is-open');
            // || fallbacks for the same reason as the row-actions toggle: an
            // undefined lang value makes jQuery's .attr() a getter, silently
            // leaving the tooltip unset in whichever state hit the gap.
            var title = open ? (_lang['HideFilters'] || 'Hide filters') : (_lang['ShowFilters'] || 'Show filters');
            $toggle
                .attr('aria-expanded', open ? 'true' : 'false')
                .attr('title', title);
        });

        $(document).on('click', '#define-tests-bulk-clear', function () {
            clearSelection();
        });

        $(document).on('click', '#define-tests-bulk-retire', function () {
            var ids = selectedIds();
            if (!ids.length) {
                return;
            }
            $('#define-tests-bulk-retire-confirm-text').text(
                String(_lang['ConfirmRetireSelectedTests'] || '').replace('{n}', ids.length)
            );
            setBulkScopeNote('#define-tests-bulk-retire-confirm-text', 'BulkRetireOneSharedTestNote', 'BulkRetireSharedTestsNote');
            $retireConfirmModal.data('ids', ids).modal('show');
        });

        $(document).on('click', '#define-tests-bulk-retire-confirm-yes', function () {
            var ids = $retireConfirmModal.data('ids') || [];
            $retireConfirmModal.modal('hide');
            if (ids.length) {
                runBulkAction(ids, retireTestRequest);
            }
        });

        $(document).on('click', '#define-tests-bulk-delete', function () {
            var ids = selectedIds();
            if (!ids.length) {
                return;
            }
            $('#define-tests-bulk-delete-confirm-text').text(
                String(_lang['ConfirmDeleteSelectedTests'] || '').replace('{n}', ids.length)
            );
            setBulkScopeNote('#define-tests-bulk-delete-confirm-text', 'BulkDeleteOneSharedTestNote', 'BulkDeleteSharedTestsNote');
            $deleteConfirmModal.data('ids', ids).modal('show');
        });

        $(document).on('click', '#define-tests-bulk-delete-confirm-yes', function () {
            var ids = $deleteConfirmModal.data('ids') || [];
            $deleteConfirmModal.modal('hide');
            if (ids.length) {
                runBulkAction(ids, deleteTestRequest);
            }
        });

        // Bulk "Create selected" -- approve every selected suggestion as-drafted
        // (a batch of approve PATCHes via runBulkAction, same sequential-with-
        // tally + partial-failure toast as bulk retire/delete). No confirm: Create
        // is additive (it writes new tests), unlike the destructive Delete. Server-
        // gated to $ai_gen_enabled, so the button only exists when the capability
        // is on.
        $(document).on('click', '#define-tests-bulk-create', function () {
            var proposalIds = selectedProposalIds();
            if (proposalIds.length) {
                runBulkAction(proposalIds, approveProposalRequest);
            }
        });

        // History: the clicked test's audit history (its runs).
        $tbody.on('click', '.test-history', function () {
            var testId = $(this).data('id');
            var $modal = $('#test-history');
            var $body = $('#test-history-body');
            var $error = $('#test-history-error');

            $error.addClass('d-none').text('');
            $body.empty();
            // The name is filled from the response, not the row, so the modal
            // reflects what the server resolved the id to.
            $('#test-history-name').text('');
            $modal.modal('show');

            fetchTestHistory(testId)
                .done(function (result) {
                    var data = (result && result.data) ? result.data : {};
                    $('#test-history-name').text(data.test_name || '');
                    renderTestHistory($body, data.audits);
                })
                .fail(function () {
                    $error.removeClass('d-none').text(_lang['CouldNotLoadTestHistory'] || '');
                });
        });

        // Apply-a-common-test: open the picker for the clicked control.
        $tbody.on('click', '.apply-common-test', function (e) {
            e.stopPropagation();

            var controlId = $(this).data('control-id');
            var $modal = $('#apply-common-test');
            var $select = $('#apply-common-test-select');
            var $error = $('#apply-common-test-error');

            $modal.data('control-id', controlId);
            $error.addClass('d-none').text('');
            // Render the placeholder-only state through the same builder the
            // roster response uses, so re-opening the modal resets the widget
            // (and clears the previous pick) instead of showing stale options.
            renderCommonTestPicker($select, []);
            $('#apply-common-test-confirm').prop('disabled', true);
            $modal.modal('show');

            fetchTestRoster()
                .done(function (tests) {
                    renderCommonTestPicker($select, tests);
                    $('#apply-common-test-confirm').prop('disabled', false);
                })
                .fail(function () {
                    $error.removeClass('d-none').text(_lang['CouldNotLoadTests'] || '');
                });
        });

        $(document).on('click', '#apply-common-test-confirm', function () {
            var $btn = $(this);
            var $modal = $('#apply-common-test');
            var $error = $('#apply-common-test-error');
            var controlId = parseInt($modal.data('control-id'), 10);
            var testIds = ($('#apply-common-test-select').val() || [])
                .map(function (id) { return parseInt(id, 10); })
                .filter(function (id) { return !isNaN(id); });

            if (!testIds.length || !controlId) {
                $error.removeClass('d-none').text(_lang['SelectOneOrMoreTests']);
                return;
            }

            $btn.prop('disabled', true);
            $error.addClass('d-none').text('');

            // Each pick is its own PATCH (a test's control set is edited one
            // test at a time), so this runs the same sequential-with-tally
            // helper the bulk retire/delete actions use rather than firing N
            // concurrent writes at the same grid.
            runSequential(testIds, function (testId) {
                return applyCommonTest(testId, controlId);
            }).then(function (summary) {
                $btn.prop('disabled', false);

                // Nothing landed: keep the modal open with the selection intact
                // so the user can retry without re-picking.
                if (!summary.ok) {
                    $error.removeClass('d-none').text(
                        testIds.length === 1
                            ? (_lang['CouldNotApplyCommonTest'] || '')
                            : String(_lang['BulkPartialFailure'] || '').replace('{n}', 0).replace('{total}', testIds.length)
                    );
                    return;
                }

                $modal.modal('hide');
                // The roster is unchanged (no test created/removed), but the
                // grid's grouping and control_count both are -- and so are the
                // band's control-scoped numbers: a control that had no test now
                // has one, which moves both "across N controls" and Untested
                // Controls.
                reloadAfterMutation();

                if (summary.fail) {
                    showAlertFromMessage(
                        String(_lang['BulkPartialFailure'] || '').replace('{n}', summary.ok).replace('{total}', testIds.length),
                        false
                    );
                } else if (summary.ok === 1) {
                    showAlertFromMessage(_lang['CommonTestApplied'] || '', true);
                } else {
                    showAlertFromMessage(String(_lang['CommonTestsApplied'] || '').replace('{n}', summary.ok), true);
                }
            });
        });

        $('#define-tests-table').on('click', 'thead th.sr-sortable', function () {
            toggleSort($(this).data('sort'));
        });

        // Enter/Space on a focused header, since these are th elements rather
        // than buttons -- tabindex without key handling is a keyboard trap.
        $('#define-tests-table').on('keydown', 'thead th.sr-sortable', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                toggleSort($(this).data('sort'));
            }
        });

        // View: read-only detail. Fetches rather than rendering from the grid
        // row, so the modal shows what the SERVER resolved the id to -- the row
        // carries only the columns the grid needs.
        $tbody.on('click', '.view-test', function () {
            var id = $(this).data('id');
            var $modal = $('#test-view');
            var $error = $('#test-view-error').addClass('d-none').text('');

            $('#test-view-body').empty();
            $('#test-view-title').text(_lang['ViewTest'] || '');
            $modal.modal('show');

            $.ajax({ type: 'GET', url: BASE_URL + '/api/v2/compliance/tests/' + id, headers: csrfHeaders() })
                .done(function (result) {
                    var test = (result && result.data && result.data.test) ? result.data.test : null;
                    if (!test) {
                        $error.removeClass('d-none').text(_lang['CouldNotLoadTest'] || '');
                        return;
                    }
                    renderTestView(test);
                })
                .fail(function (xhr) {
                    // Surface the server's message (e.g. the per-test access
                    // 403) rather than a generic failure -- it says WHY.
                    var message = (xhr && xhr.responseJSON && xhr.responseJSON.status_message)
                        ? xhr.responseJSON.status_message
                        : (_lang['CouldNotLoadTest'] || '');
                    $error.removeClass('d-none').text(message);
                });
        });

        // Remove-from-this-control: open the confirm, whose whole job is to say
        // what survives. "Remove X from AC-2?" alone reads a lot like Delete;
        // the second line naming the other controls is what distinguishes them.
        $tbody.on('click', '.remove-from-control', function () {
            var $btn = $(this);
            var otherControls = (parseInt($btn.data('control-count'), 10) || 2) - 1;

            $('#define-tests-remove-control-confirm-text').text(
                fillTemplate(_lang['RemoveTestFromControlConfirm'], {
                    test_name: $btn.data('test-name'),
                    control_name: $btn.data('control-name'),
                })
            );
            $('#define-tests-remove-control-confirm-sub').text(
                otherControls === 1
                    ? (_lang['RemoveTestFromControlStaysOne'] || '')
                    : fillTemplate(_lang['RemoveTestFromControlStays'], { n: otherControls })
            );

            $removeControlConfirm
                .data('id', $btn.data('id'))
                .data('control-id', $btn.data('control-id'))
                .modal('show');
        });

        $('#define-tests-remove-control-confirm-yes').on('click', function () {
            var $yes = $(this).addClass('is-busy').prop('disabled', true);
            var id = $removeControlConfirm.data('id');
            var controlId = $removeControlConfirm.data('control-id');

            detachTestFromControlRequest(id, controlId)
                .done(function () {
                    $removeControlConfirm.modal('hide');
                    reloadAfterMutation();
                    showAlertFromMessage(_lang['TestRemovedFromControl'] || '', true);
                })
                .fail(function (xhr) {
                    $removeControlConfirm.modal('hide');
                    // A 409 is the server refusing to orphan the test (it was
                    // the last control) -- surface ITS message, which names the
                    // real alternatives, rather than a generic failure.
                    var message = (xhr && xhr.responseJSON && xhr.responseJSON.status_message)
                        ? xhr.responseJSON.status_message
                        : (_lang['CouldNotRemoveTestFromControl'] || '');
                    showAlertFromMessage(message, false);
                })
                .always(function () {
                    $yes.removeClass('is-busy').prop('disabled', false);
                });
        });

        // Per-row Retire/Restore (Task 8) -- immediate, no confirm (a
        // reversible state flip, unlike delete). Delegated: buildTestRow()
        // only renders these buttons when canRetire is true.
        $tbody.on('click', '.retire-row, .restore-row', function () {
            var $btn = $(this).addClass('is-busy').prop('disabled', true);
            var id = $btn.data('id');
            var request = $btn.hasClass('restore-row') ? restoreTestRequest(id) : retireTestRequest(id);
            request
                .done(function () {
                    reloadAfterMutation();
                })
                .fail(function () {
                    $btn.removeClass('is-busy').prop('disabled', false);
                    showAlertFromMessage(_lang['RequestFailed'] || '', false);
                });
        });

        /* ------------------------------------------------------------ *
         * AI suggestion row actions (Task B3)                           *
         * ------------------------------------------------------------ */

        // Create: approve the proposal as-drafted. The server applies it (writes
        // the real test + its M:N control junction), so a reload swaps the
        // suggestion row for the created test row. The proposal is consumed by
        // the approve itself -- no separate dismiss here.
        $tbody.on('click', '.suggestion-create', function () {
            var $btn = $(this).addClass('is-busy').prop('disabled', true);
            var proposalId = $btn.data('proposal-id');
            approveProposalRequest(proposalId)
                .done(function () {
                    showAlertFromMessage(_lang['TestCreatedFromSuggestion'] || '', true);
                    reloadAfterMutation();
                })
                .fail(function (xhr) {
                    $btn.removeClass('is-busy').prop('disabled', false);
                    var message = (xhr && xhr.responseJSON && xhr.responseJSON.status_message)
                        ? xhr.responseJSON.status_message
                        : (_lang['RequestFailed'] || '');
                    showAlertFromMessage(message, false);
                });
        });

        // Dismiss: reject the proposal (audited, no GRC write). The row leaves
        // the grid on reload.
        $tbody.on('click', '.suggestion-dismiss', function () {
            var $btn = $(this).addClass('is-busy').prop('disabled', true);
            var proposalId = $btn.data('proposal-id');
            rejectProposalRequest(proposalId)
                .done(function () {
                    showAlertFromMessage(_lang['SuggestionDismissed'] || '', true);
                    reloadAfterMutation();
                })
                .fail(function (xhr) {
                    $btn.removeClass('is-busy').prop('disabled', false);
                    var message = (xhr && xhr.responseJSON && xhr.responseJSON.status_message)
                        ? xhr.responseJSON.status_message
                        : (_lang['RequestFailed'] || '');
                    showAlertFromMessage(message, false);
                });
        });

        // Review & edit: open the shared Add-test modal (openAddTestModal lives
        // in compliance.js, pre-selecting the target control), then pre-fill the
        // fields from the in-memory suggestion payload -- no second fetch, the
        // grid already carried it (proposalPayloads). The source proposal id is
        // stashed on the modal so a successful Save consumes it (compliance.js).
        //
        // objective/test_steps/expected_results/sample/required_evidence in the
        // Add modal are HugeRTE editors (initialized at page-ready, ready
        // synchronously once the modal is open). A plain .val() writes the hidden
        // <textarea> while the editor keeps showing its own empty content, so we
        // set the editor via hugerte (falling back to the textarea only when no
        // editor is attached). `name` is a plain <input>, so .val() is correct.
        $tbody.on('click', '.suggestion-review', function () {
            var $btn = $(this);
            var proposalId = $btn.data('proposal-id');
            var controlId = $btn.data('control-id');
            var payload = proposalPayloads[proposalId];

            if (typeof openAddTestModal !== 'function') {
                return;
            }
            openAddTestModal(controlId);

            if (payload) {
                var $modal = $('#test--add');
                $('[name=name]', $modal).val(payload.name || '');

                var setRichField = function (id, value) {
                    var v = value || '';
                    // Always seed the underlying textarea first: the Add modal's
                    // HugeRTE editors initialise ASYNCHRONOUSLY at page-ready (see
                    // compliance.js waitAndOpen()), so a "Review & edit" click
                    // right after load can hit an editor that is registered but
                    // not yet ready -- and a still-initialising editor reads its
                    // initial content from the textarea when it comes up.
                    $('#' + id, $modal).val(v);
                    // If the editor is fully initialised, push the value in
                    // directly too (an already-open editor won't re-read the
                    // textarea). Guard on getBody(): hugerte.get() returns a
                    // truthy handle BEFORE the editor is ready, and setContent()
                    // throws in that window -- which would abort this handler and
                    // leave the modal half-filled.
                    var ed = (typeof hugerte !== 'undefined') ? hugerte.get(id) : null;
                    if (ed && ed.getBody && ed.getBody()) {
                        ed.setContent(v);
                    }
                };
                setRichField('add_objective', payload.objective);
                setRichField('add_test_steps', payload.test_steps);
                setRichField('add_expected_results', payload.expected_results);
                setRichField('add_sample', payload.sample);
                setRichField('add_required_evidence', payload.required_evidence);

                // The server derived schedule_type from the proposed cadence
                // (ai_control_test_schedule_type) -- consume it rather than
                // re-deriving the interval rule here. For 'interval' the modal
                // defaults to Calendar, so select Interval and fire `change` to
                // reveal the interval section before filling the day cadence
                // (the schedule-mode handler listens on change).
                if (payload.schedule_type === 'interval') {
                    $('input[name=schedule_type][value=interval]', $modal)
                        .prop('checked', true)
                        .trigger('change');
                    $('[name=test_frequency]', $modal).val(payload.test_frequency);
                }
            }

            // Consumed on Save (compliance.js): editing + saving a suggestion IS
            // approving it, so the source proposal is rejected once the real test
            // is created. openAddTestModal() clears this at the top of every open,
            // so a normal Add never carries a stale id.
            $('#test--add').data('sourceProposalId', proposalId);
        });

        // Generate with AI: enqueue control-test generation for this control.
        // Async -- the toast says "queued", and the proposals appear on a later
        // grid draw. Rendered only when aiGenEnabled && canEdit (buildGroupRow),
        // and the server re-gates capability + permission.
        // After a generation is enqueued the job runs on the queue, so the
        // drafts don't exist yet. We poll the control's pending proposals until
        // EITHER the count rises above the pre-enqueue baseline (drafts landed)
        // OR the server reports the generation job is no longer running (the
        // `generating` flag). The flag is what distinguishes "finished with no
        // new proposals" (the model only proposed duplicates) from "still
        // running" -- a count check alone can't, so without it the button sat on
        // "Generating…" for the full timeout. Keyed by controlId so a grid reload
        // that re-renders the button mid-poll can't spawn a second poller.
        var activeGenerationPolls = {};

        // The control's generation status: {generating, pending_count}. Both the
        // pre-enqueue baseline and the poll read this one Extra endpoint (GET on
        // the generate-tests path) so generation status stays in the AI Extra.
        function getGenerationStatus(controlId) {
            return $.ajax({
                type: 'GET',
                url: BASE_URL + '/api/v2/ai/controls/' + controlId + '/generate-tests',
                headers: csrfHeaders(),
            });
        }
        function statusPendingCount(resp) {
            return (resp && resp.data && typeof resp.data.pending_count === 'number') ? resp.data.pending_count : 0;
        }

        // The control's current pending-proposal count -- the poll baseline.
        // Resolves to a number, or NULL if the request failed: a null baseline
        // means "unknown", and the poll then relies solely on the `generating`
        // flag for completion (never a count delta), so a failed baseline can't
        // be misread as 0 and trigger a false "complete" when proposals already
        // exist for the control.
        function getPendingProposalCount(controlId) {
            return getGenerationStatus(controlId).then(statusPendingCount, function () {
                return null;
            });
        }

        // Poll the control's generation status until the drafts land (the pending
        // count rises above the pre-enqueue baseline) or the job stops running
        // (`generating` false). The caller owns the activeGenerationPolls guard
        // (claimed before this runs); finish() releases it. `baseline` may be
        // null (unknown) -- then only the generating flag drives completion.
        function pollForGeneratedProposals(controlId, originalText, baseline) {
            var maxAttempts = 45;      // ~3 min at 4s -- covers a slow provider
            var intervalMs = 4000;
            var attempts = 0;

            // Release EVERY currently-rendered Generate button for this control
            // (re-query the DOM rather than closing over a node a grid reload may
            // have detached), and clear the active-poll guard.
            function finish() {
                delete activeGenerationPolls[controlId];
                $('.generate-tests[data-control-id="' + controlId + '"]')
                    .removeClass('is-busy').prop('disabled', false).text(originalText);
            }
            function revealDrafts() {
                // The new suggestions only render in the "AI suggested" (or "All
                // tests") views; if the user is on another filter, switch so the
                // drafts they just asked for are actually visible.
                var $show = $('#define-tests-show-filter');
                var showVal = $show.val();
                if ($show.length && showVal !== 'ai_suggested' && showVal !== 'all') {
                    $show.val('ai_suggested').trigger('change');
                } else {
                    reloadAfterMutation();
                }
            }
            function poll() {
                attempts++;
                getGenerationStatus(controlId).done(function (resp) {
                    var count = statusPendingCount(resp);
                    var generating = !!(resp && resp.data && resp.data.generating);
                    // Drafts landed (only trust a delta when the baseline is known).
                    if (baseline !== null && count > baseline) {
                        finish();
                        showAlertFromMessage(_lang['TestGenerationComplete'] || '', true);
                        revealDrafts();
                        return;
                    }
                    if (!generating) {
                        // The job finished. With an unknown baseline we can't tell
                        // whether it added anything, so reveal to surface whatever
                        // exists; with a known baseline and no delta it produced
                        // nothing new (e.g. only duplicates).
                        finish();
                        if (baseline === null) {
                            showAlertFromMessage(_lang['TestGenerationComplete'] || '', true);
                            revealDrafts();
                        } else {
                            showAlertFromMessage(_lang['TestGenerationNoNew'] || '', true);
                        }
                        return;
                    }
                    if (attempts >= maxAttempts) {
                        finish();
                        showAlertFromMessage(_lang['TestGenerationStillRunning'] || '', true);
                        return;
                    }
                    setTimeout(poll, intervalMs);
                }).fail(function () {
                    // A transient poll failure isn't fatal -- keep trying until
                    // the timeout, then release the button.
                    if (attempts >= maxAttempts) {
                        finish();
                        return;
                    }
                    setTimeout(poll, intervalMs);
                });
            }
            poll();
        }

        $tbody.on('click', '.generate-tests', function (e) {
            e.stopPropagation();
            var $btn = $(this);
            var controlId = $btn.data('control-id');
            var originalText = $btn.text();
            $btn.addClass('is-busy').prop('disabled', true).text(_lang['Generating'] || originalText);

            // Dedup: a generation for this control is already in flight (e.g. a
            // grid reload re-rendered the button) -- don't enqueue or poll twice.
            if (activeGenerationPolls[controlId]) {
                showAlertFromMessage(_lang['TestGenerationQueued'] || '', true);
                return;
            }
            // Claim the guard NOW, before the async baseline+enqueue chain, so a
            // rapid second click on a re-rendered button can't slip through the
            // window. finish() (in the poll) or the real-failure path releases it.
            activeGenerationPolls[controlId] = true;

            // Capture the pre-enqueue baseline BEFORE requesting generation, so a
            // job that completes between the request resolving and the first poll
            // is still detected (its new proposals exceed this baseline).
            getPendingProposalCount(controlId).then(function (baseline) {
                generateTestsRequest(controlId)
                    .done(function () {
                        showAlertFromMessage(_lang['TestGenerationQueued'] || '', true);
                        pollForGeneratedProposals(controlId, originalText, baseline);
                    })
                    .fail(function (xhr) {
                        // Already queued for this control -- not an error: the job
                        // is running, so poll for it just as a fresh enqueue would.
                        if (xhr && xhr.status === 409) {
                            showAlertFromMessage(_lang['TestGenerationQueued'] || '', true);
                            pollForGeneratedProposals(controlId, originalText, baseline);
                            return;
                        }
                        // A real enqueue failure -- release the guard + the button.
                        delete activeGenerationPolls[controlId];
                        $('.generate-tests[data-control-id="' + controlId + '"]')
                            .removeClass('is-busy').prop('disabled', false).text(originalText);
                        var message = (xhr && xhr.responseJSON && xhr.responseJSON.status_message)
                            ? xhr.responseJSON.status_message
                            : (_lang['RequestFailed'] || '');
                        showAlertFromMessage(message, false);
                    });
            });
        });

        // Single-row delete confirm (Issue 6) -- the modal itself (#test--delete)
        // and the .delete-row click that opens it (setting the hidden test_id
        // input) live in compliance.js, shared across every compliance.js page;
        // the actual delete request is handled here instead, alongside the bulk
        // delete it reuses deleteTestRequest() from, so a single-row delete and
        // a bulk delete never drift on the request shape. Mirrors the bulk
        // delete confirm's own hide-then-request ordering (above) rather than
        // the old form's native full-page-reload POST -- no location.reload()
        // here either, just an in-place loadGrid().
        $(document).on('click', '#test-delete-confirm-yes', function () {
            var id = $('#test--delete [name=test_id]').val();
            $('#test--delete').modal('hide');
            if (!id) {
                return;
            }
            deleteTestRequest(id)
                .done(function () {
                    reloadAfterMutation();
                })
                .fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.status_message) ? xhr.responseJSON.status_message : (_lang['RequestFailed'] || '');
                    showAlertFromMessage(message, false);
                });
        });

        // applyInsightFromUrl() is chained onto the first load's completion
        // (not just called after the loadGrid() invocation) so it never
        // races the initial unfiltered fetch -- see loadGrid()'s own
        // return-promise comment above.
        // Draw the custom listboxes over the native selects. Done before the
        // first fetch so the toolbar never flashes native controls, and only
        // for single selects -- Framework/Family stay on bootstrap-multiselect,
        // which is a different (multi-choice) interaction.
        [$coverageFilter, $statusFilter, $resultFilter, $testerFilter, $scheduleFilter, $tagFilter, $showFilter]
            .forEach(function ($select) { srSelectEnhance($select); });

        // Read a shared view's filters BEFORE the first fetch so the grid
        // loads already narrowed, rather than rendering everything and then
        // visibly re-filtering. The framework/family multiselects are
        // initialized just above, so their values can be applied here.
        readFiltersFromUrl();
        if (state.framework.length) {
            $frameworkFilter.val(state.framework.map(String));
        }
        if (state.family.length) {
            $familyFilter.val(state.family.map(String));
        }

        // A URL-applied view set native values directly, so redraw the custom
        // buttons to match before anything is visible.
        [$coverageFilter, $statusFilter, $resultFilter, $testerFilter, $scheduleFilter, $tagFilter, $showFilter,
            $frameworkFilter, $familyFilter]
            .forEach(function ($select) { srSelectRender($select); });

        // Re-decide the column fold whenever the room the card has changes.
        // loadGrid() covers changes in what the table CONTAINS; this covers
        // changes in what it FITS INTO.
        //
        // A ResizeObserver on the CARD rather than a window resize listener:
        // the card also changes width when the shell's sidebar collapses or
        // expands and when the user zooms or changes their font size, none of
        // which fire a window resize -- and the sidebar is worth ~290px of this
        // card, which is the whole reason the folds land where they do. The card
        // is safe to observe without feeding back on itself: it is a block-level
        // element sized by the page layout, and the table folding inside it
        // happens within .sr-table-scroll, so a fold never changes the observed
        // box. The window listener stays as the fallback for anything without
        // ResizeObserver; both routes coalesce through the same one-per-frame
        // scheduler, so having both costs nothing.
        var budgetCard = $('#define-tests-table').closest('.sr-table-card')[0];
        if (budgetCard && typeof window.ResizeObserver !== 'undefined') {
            new window.ResizeObserver(scheduleColumnBudgetEval).observe(budgetCard);
        }
        window.addEventListener('resize', scheduleColumnBudgetEval);

        loadGrid().done(function () {
            applyInsightFromUrl();
        });
        // loadControlRoster() (Add/Edit Test modal control multi-select) now
        // lives in js/simplerisk/pages/compliance.js and is invoked from its
        // own ready handler -- see the FIX note in this file's header
        // comment for why (this file doesn't load on every page that
        // renders the Edit Test modal).
    });
}(jQuery));
