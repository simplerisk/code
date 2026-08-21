/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/* Define Control Frameworks -- master-detail page.
 * The rail selects a framework; the table renders scoped to it. Selection
 * lives in the URL (?framework=<id>) so the view is linkable and Back works.
 */
(function () {
    'use strict';

    /* ===== Permission gating (Task 58) =====================================
     *
     * Which affordances this page RENDERS. Every one of them is already
     * enforced server-side; this is purely so a user who cannot do a thing is
     * not shown the button for it and then handed a 403 toast. Nothing here
     * is a security control.
     *
     * The map, endpoint by endpoint -- deliberately NOT one permission for the
     * whole page, because these five are genuinely different grants and a user
     * can hold any subset of them:
     *
     *   + Add framework             POST   /governance/frameworks       add_new_frameworks
     *   rail Edit                   PATCH  /governance/frameworks/{id}  modify_frameworks
     *   rail Delete                 DELETE /governance/frameworks/{id}  delete_frameworks
     *   + Add control               POST   /governance/controls         add_new_controls
     *   row Clone                   POST   /governance/controls         add_new_controls
     *   row Edit                    PATCH  /governance/controls/{id}    modify_controls
     *   row Delete                  DELETE /governance/controls/{id}    delete_controls
     *   bulk Delete                 POST   /governance/controls/bulk-delete  delete_controls
     *   row / bulk Set applicability POST  /governance/applicability    modify_frameworks
     *
     * NOT GATED, on purpose: both Statement of Applicability entry points
     * (the rail row's file-shield action and the toolbar's #sr-soa-btn). The
     * SoA is a READ -- GET /governance/soa and /governance/soa/export both
     * gate on `governance` alone (api/v2/includes/soa.php), and `governance`
     * is what render_header_and_sidebar()'s `check_governance` already
     * required to reach this page at all. Hiding it from someone who can open
     * it would be the "hides too much" failure.
     *
     * ABSENT, NEVER DISABLED, throughout -- the rule Task 15 set for the bulk
     * applicability button under "All controls": a disabled control asserts
     * that the action exists here and is merely unavailable, which is the
     * wrong sentence for a permission the user does not hold.
     *
     * Read LAZILY, per call, rather than snapshotting at IIFE time: this file
     * is a header-loaded <script> and window.SR_GOV_PERMS is emitted by
     * governance/index.php's inline block further down the document, so the
     * object does not exist yet when this closure is evaluated. Every caller
     * is a render function that runs on/after $(document).ready.
     *
     * DEFAULT DENY on a missing map. If the global never arrives -- a page
     * that loads this script without emitting it, or an emission that fails --
     * the honest fallback is to show nothing mutating rather than to show
     * everything: the endpoints will refuse anyway, so denying costs a
     * correctly-permissioned user a visible affordance (loud, reported in
     * minutes) while allowing would restore exactly the regression this
     * closes (quiet, invisible until someone hits a 403).
     */
    function can(permission) {
        var perms = window.SR_GOV_PERMS;
        return !!(perms && perms[permission] === true);
    }

    /* Row selection exists only to feed the bulk bar, and the bulk bar has
     * exactly two actions. With neither permission there is no bulk action to
     * escalate a selection into, so the checkbox column is a dead affordance:
     * ticking it can only ever produce a bar showing a count and a dismiss.
     * Same absent-not-disabled rule -- the column is dropped from BOTH
     * controlColumns() and renderRow(), so the header and the rows stay in
     * agreement, and the drawer's colspan (derived from controlColumns())
     * follows automatically. */
    function canSelectRows() {
        return can('delete_controls') || can('modify_frameworks');
    }

    // applicabilityScoped is the SERVER's answer to "is applicability
    // answerable for the view currently on screen?" -- adopted from the
    // controls/table response (`applicability_scoped`), never re-derived from
    // state.framework here. The server is what decides which rows carry an
    // applicability record and which requests the applicability facet applies
    // to; a second copy of that rule in the client is how a column ends up
    // rendered over rows that have no such field. Same reason
    // GET /governance/applicability echoes `default_state` rather than letting
    // the client invent one. False until the first response lands, so the
    // column simply isn't built before anything is known.
    var state = { framework: null, status: '1', rows: [], total: 0, filtered: 0,
                  start: 0, length: 25, sort: 'control_number', dir: 'asc',
                  applicabilityScoped: false };

    // ===== The rail's two SYNTHETIC scopes ===================================
    //
    // The rail selects a SCOPE, and two of the scopes it offers are not
    // frameworks:
    //
    //   state.framework === null   "All controls" -- every control in the
    //                              catalogue, mapped or not.
    //   state.framework === -1     "Unassigned controls" -- the controls
    //                              mapped to NO framework at all.
    //
    // The first row used to be labelled "All frameworks" while scoping to all
    // CONTROLS, which is not the same set: 16 of this instance's 1,552 live
    // controls belong to no framework, so the row named a union of frameworks
    // and showed a superset of it. Renaming it to "All controls" is the honest
    // half of the fix; "Unassigned controls" is the useful half, because the
    // difference between the two labels was previously reachable only by
    // paging through the whole catalogue looking for a blank Frameworks cell.
    // Nothing was removed from any view to make the label true -- that would
    // have hidden those 16 controls from this page entirely.
    //
    // -1 is NOT a new invention on the client. It is the "Unassigned" sentinel
    // every id facet on this page already speaks, and the server has
    // implemented it for the framework facet all along -- in
    // get_framework_controls_by_filter() and control_framework_scope_sql()
    // (includes/governance.php) it is spelled `m.control_id is NULL`, and
    // controls_table_applicability_framework() (api/v2/includes/
    // governance_controls.php) already documents that it is "not a framework"
    // and refuses to answer applicability within it. So the table, the filter
    // sheet's count chips and the insights band all scope to it correctly with
    // no server change; what follows is the client half.
    var UNMAPPED = -1;

    /**
     * The rail's selection when it is a REAL framework, else null.
     *
     * The distinction the rest of this file has to make constantly: "is the
     * view narrowed?" (state.framework !== null -- both synthetic scopes narrow
     * it, and "All controls" does not) is a DIFFERENT question from "is there a
     * framework to act on?" (this). Everything that edits, documents, or
     * decides something ABOUT a framework -- the Statement of Applicability,
     * the applicability facet and its bulk write, the "did my scoped framework
     * just leave the rail?" recovery -- asks this one, because -1 names no
     * framework and none of those have an answer there.
     *
     * One helper rather than a `> 0` test repeated at each site: a future
     * synthetic scope then has one place to be recognised, not seven.
     */
    function scopedFramework() {
        return state.framework !== null && state.framework > 0 ? state.framework : null;
    }

    // ===== Request sequencing (Task 46; closes Task 21) ======================
    //
    // Both of this page's loaders fire on input that arrives faster than the
    // responses come back -- three quick Next clicks, a page-size change on
    // top of a search keystroke, a rail status switch mid-flight. jQuery
    // settles whatever LANDS last, which is not necessarily whatever was
    // ASKED for last, so without this the table can end up showing page 2
    // after the user has already moved to page 3.
    //
    // One counter per endpoint. Every call takes the next ticket; a response
    // whose ticket is no longer the newest is discarded whole -- no state
    // written, no render, no empty-state switch. The FAILURE path checks too,
    // so a slow request that eventually fails can't paint the "couldn't load"
    // tile over a newer request that already succeeded.
    var requestSeq = { controls: 0, frameworks: 0 };

    function nextSeq(key) { return ++requestSeq[key]; }
    function isStaleSeq(key, seq) { return seq !== requestSeq[key]; }

    // Set by openControlForClone() just before it calls #control--add's
    // modal('show'); consumed and cleared by the show.bs.modal delegate
    // (Task 24) that decides whether that open gets the clone framing
    // (title + banner) or the blank "New Control" one. See that handler's
    // comment for why this can't just be "clear on every show" the way
    // clearModalError() does for .sr-modal-inline-error -- Clone WANTS its
    // framing to survive the show event, a plain add doesn't.
    var pendingCloneSourceName = null;

    // Task 64, and the framework twin of pendingCloneSourceName above -- same
    // hand-off, same reason it cannot just be cleared on every show: Clone WANTS
    // its framing to survive the show event and a plain "+ Add framework" does
    // not. Written only by openFrameworkForClone(), immediately before
    // modal('show'); consumed and cleared by the #framework--add show.bs.modal
    // delegate, which is the SOLE owner of that modal's title, banner and reset.
    //
    // An OBJECT rather than a bare name, because a framework clone carries more
    // state through the modal than a control clone does:
    //
    //   id        the source, sent back as `clone_from` so the server copies its
    //             control mappings -- the one thing about a clone that cannot
    //             ride in the form, since the form has no mappings widget
    //   name      the banner and title text
    //   count     how many mappings the banner promises will be copied
    //   parent    applied AFTER the parent dropdown is injected (see the delegate)
    //
    // Nulled again by the delegate on every open, clone or not, so a cancelled
    // clone can never leak `clone_from` into the next plain add.
    var pendingCloneFramework = null;

    // Task 60. Same hand-off shape as pendingCloneSourceName above, for the
    // same reason: the row action that opens #applicability--set knows
    // something the modal cannot work out for itself -- that this open is
    // about ONE named control and not about the checkbox selection.
    //
    // `pendingApplicabilityControl` is written only by the row action, and only
    // immediately before modal('show'); `applicabilityTarget` is what
    // show.bs.modal resolves it (or its absence) into, and is the single object
    // both the modal's copy and its submit are read from. Consumed and cleared
    // on every open, so a bulk-bar open can never inherit a row's target and a
    // row open can never inherit a stale one.
    var pendingApplicabilityControl = null;
    var applicabilityTarget = null;

    // ===== Framework rail search (Task 22) ====================================
    //
    // Client-side only -- the rail is tens of rows, never worth a round trip.
    // `railFrameworks` is the full, unfiltered list the LAST loadFrameworks()
    // call resolved (GET /api/v2/governance/frameworks/rail?status=...,
    // already in parent-before-child tree order with a `depth` per row --
    // build_framework_rail_rows(), includes/governance.php). `railSearch` is
    // the current search box value. renderRail() (below) is the only reader
    // of either: it re-derives the visible subset from these two on every
    // call, so a load, a search keystroke, and a Clear search click all funnel
    // through the exact same render path.
    var railFrameworks = [];
    var railSearch = '';

    // The count for the synthetic "Unassigned controls" row, straight from the
    // rail endpoint's `unmapped_count`. Not derived here from railFrameworks:
    // a control mapped into two frameworks is counted by BOTH of their rows, so
    // state.total minus the sum of the per-framework counts is not the number
    // of unmapped controls and would print a negative number on any instance
    // with shared controls. The server computes it with get_control_scope_totals([-1]) --
    // the SAME aggregate the insights band's Controls tile reads -- so the chip,
    // the tile and the table's own row count are one computation, not three
    // that ought to agree. Only ever rendered after a SUCCESSFUL loadFrameworks()
    // has written it (renderRail() is called from nowhere else that hasn't), so
    // the 0 it starts at is never on screen as an answer.
    var railUnmappedCount = 0;

    // ===== Row selection + contextual bulk bar (Task 7) ======================
    //
    // `selection` only ever holds ids from the CURRENTLY RENDERED page --
    // renderTable() rebuilds #sr-ctl-tbody from scratch on every reload, and
    // the sr:controls-loaded handler below clears both of these the moment
    // that happens, so a hidden selection can never survive a reload/page
    // turn/filter change to surprise a later bulk action.
    //
    // `selectAllFiltered` is the escalation flag for "Select all N" -- the
    // header checkbox only ever selects the current page (the client only
    // ever holds one page of rows), so escalating to the whole filtered
    // result set has to be tracked as a flag and resolved server-side against
    // the current filter, not as a list of ids the client can't enumerate.
    var selection = new Set();
    var selectAllFiltered = false;

    // ===== Filter toolbar state (Task 4) =====
    //
    // Every facet is a multi-select array of string ids EXCEPT status, whose
    // values are the 3 fixed tokens the controls/table endpoint accepts
    // ('pass'/'fail'/'not_tested' -- api/v2/includes/governance_controls.php's
    // CONTROLS_TABLE_STATUSES), and text, a single search string. -1 in any
    // id-array facet is the "Unassigned" sentinel the server already
    // understands (get_framework_controls_by_filter(), includes/governance.php).
    // maturity (Task 34): a real multi-select facet on the filter sheet now,
    // so it is an ARRAY of bucket tokens exactly like status -- not the single
    // string Task 10 left it as when its only source was the insights band's
    // Below-target tile link.
    //
    // applicability (Task 14): a real multi-select facet on the filter sheet
    // too, so it is an ARRAY of state tokens exactly like status and maturity
    // -- not the single string Task 10 left it as when its only source was the
    // insights band's Excluded tile link. That tile's legacy ?applicability=
    // excluded still works and folds onto the two deviation states it has
    // always meant (readUrl() below), matching the server's own
    // CONTROLS_TABLE_APPLICABILITY_ALIASES.
    //
    // Built through a factory, not a literal, because "no filters" is also what
    // "Clear filters" resets to -- two literals of the same object is one
    // rename away from the two disagreeing about which facets exist.
    function emptyFilters() {
        return { family: [], owner: [], class: [], phase: [],
                 priority: [], type: [], status: [], text: '',
                 maturity: [], applicability: [] };
    }

    var filters = emptyFilters();

    // The 6 id-based facets that live in the filter sheet and get their
    // option lists (with per-option counts) from rebuild_control_filters.
    // Status is deliberately excluded here -- it's a fixed 3-token enum with
    // no server-side option list to rebuild.
    var FACET_FIELDS = ['family', 'owner', 'class', 'phase', 'priority', 'type'];

    var STATUS_OPTIONS = [
        { value: 'pass', key: 'Pass' },
        { value: 'fail', key: 'Fail' },
        { value: 'not_tested', key: 'NotTested' }
    ];

    // Maturity buckets (Task 34) -- MIRRORS control_maturity_bucket_tokens()
    // (includes/governance.php), the same three tokens
    // governance_maturity_gap_table() already takes for the governance
    // dashboard's Below/At/Above Maturity widgets. One vocabulary across the
    // column chip, the facet, the count chips and the server-side filter.
    //
    // `cls` is the .sr-state-pill family each bucket renders in. The
    // assignment is NOT a bad-to-good ramp, and this is the easy thing to get
    // wrong here: "above" is not success, it is over-investment relative to
    // the target the organisation itself set, so painting it green (or
    // painting "below" red) would tell the user to chase it.
    //
    //   below -> warning : the only bucket that asks for action
    //   at    -> success : the target is met; this is the intended state
    //   above -> info    : informational/neutral. Not a problem, not a win --
    //                      worth knowing when reallocating effort.
    //
    // Deliberately NOT danger for "below" either: a control merely short of a
    // desired maturity is not a failure, and danger is already spoken for by
    // the Status column's Fail pill right beside it. Two red pills on one row
    // would read as two failures.
    var MATURITY_OPTIONS = [
        { value: 'below', key: 'BelowMaturity', cls: 'sr-state-warning' },
        { value: 'at',    key: 'AtMaturity',    cls: 'sr-state-success' },
        { value: 'above', key: 'AboveMaturity', cls: 'sr-state-info' }
    ];

    // Applicability states (Task 14) -- MIRRORS applicability_requestable_states()
    // (includes/applicability.php): the DEFAULT plus the two stored deviations,
    // the same three tokens the controls/table filter and
    // POST /governance/applicability both accept. One vocabulary across the
    // column chip, the facet, and the server-side filter, exactly as
    // MATURITY_OPTIONS above does for its buckets.
    //
    // `applicable` is not stored anywhere. A control with no decision row IS
    // applicable -- the server resolves that (resolve_applicability()) and sends
    // the resolved state, so nothing here re-derives it from a missing field.
    //
    // `cls` is the shipped .sr-state-pill family each state renders in
    // (_tables.scss). Deliberately NO 'danger' anywhere in this column: excluding
    // a control from a framework's scope is a legitimate, justified, audited
    // decision, not a failure, and red is already spoken for by the Status
    // column's Fail pill two cells away. Mapped by MEANING, per design-system §7:
    //
    //   applicable     -> success : in scope. The affirmative default.
    //   not_applicable -> neutral : out of play, which is precisely what the
    //                               grey family means (Closed, Untreated, Draft).
    //   inherited      -> info    : informational. The control IS applicable --
    //                               someone else performs it -- which is the
    //                               distinction the state exists for and would
    //                               be lost if it shared not_applicable's chip.
    var APPLICABILITY_OPTIONS = [
        { value: 'applicable',     key: 'ApplicabilityApplicable',    cls: 'sr-state-success' },
        { value: 'not_applicable', key: 'ApplicabilityNotApplicable', cls: 'sr-state-neutral' },
        { value: 'inherited',      key: 'ApplicabilityInherited',     cls: 'sr-state-info' }
    ];

    // The one legacy applicability spelling, folded onto the states it has
    // always meant. MIRRORS CONTROLS_TABLE_APPLICABILITY_ALIASES
    // (api/v2/includes/governance_controls.php) -- one alias expands to TWO
    // tokens, because the insights band's Excluded tile counts every stored
    // deviation ("Not applicable or inherited" is its own subtitle).
    var APPLICABILITY_ALIASES = { excluded: ['not_applicable', 'inherited'] };

    // ===== The compact tier, measured (Task 45) =============================
    //
    // ONE condition -- "the full column set fits the pane" -- decided in ONE
    // place, which is what Task 42 established and this preserves. What has
    // changed is that the condition is no longer approximated by a number.
    //
    // Every number this tier has been given went stale. Task 5 picked 1400px.
    // Task 42 found that was 140px short of the fit and DERIVED 1540px from
    // the table's then-measured 959px min-content plus 582px of chrome. Task
    // 14 then added the Applicability column, which moved min-content to
    // 1081px in the framework-scoped view and invalidated 1540 on the spot:
    // between 1541 and 1662, with a framework selected, the table sat frozen
    // at 1081px inside a 977-1056px scroller and Edit/Clone/Delete -- the LAST
    // column, so at the table's right edge -- hung up to 90px outside it,
    // reachable only by scrolling the table sideways.
    //
    // A single threshold cannot serve both scopes, because the Applicability
    // column is absent under "All controls": the same width that is correct
    // for one view folds three columns away for nothing in the other. Nor does
    // a container query on the pane fix it, which is worth stating because it
    // is the obvious next idea. A container query measures the CONTAINER, and
    // the pane is the same width in both scopes; what differs is the TABLE's
    // min-content. It would still need one hardcoded threshold per column set,
    // and a container query condition cannot read a custom property, so the
    // per-scope value could not be computed in CSS anyway.
    //
    // Both terms of the condition are things the browser has already computed,
    // so this reads them instead of predicting them:
    //
    //   scrollWidth   what the full column set needs (the table is pinned at
    //                 its min-content inside the scroller, which is exactly
    //                 the failure mode -- so this IS the requirement)
    //   clientWidth   the room the pane actually has
    //
    // Both are read with the tier REMOVED, always. The tier changes the rail
    // width and hides three columns, so measuring while it is applied would
    // make the decision a function of its own result and the tier would
    // oscillate. Measuring the full state makes the input independent of the
    // output, so this converges in one pass at every width.
    //
    // The answer goes on .sr-fw-panes as `sr-fw-compact`, which the stylesheet
    // and syncInlineFacetPlacement() below both key off -- still one
    // definition, with no second copy anywhere. It is scope-aware,
    // sidebar-state-aware, zoom-aware and font-size-aware for free, and it
    // cannot go stale when the next column is added because it never knew how
    // many columns there were.
    //
    // governance/index.php ships the class ON, so the SAFE state is the
    // default: with no JS, or before the first measurement, the row actions
    // are the ⋯ menu and are reachable. This only ever relaxes the tier, and
    // only after measuring that relaxing it is safe.
    //
    // ===== ONE FOLD FURTHER: the queue tier, measured too (Task 48) =========
    //
    // Task 45 measured ONE fold: "does the full column set fit?", answered by
    // stripping .sr-fw-compact and comparing scrollWidth against clientWidth.
    // What it could not answer is the question that comes next -- "and does the
    // FOLDED set fit?" -- and at narrow panes the answer is no. The row actions
    // are the table's LAST column, so they sit at the table's right edge, and a
    // table pinned at its min-content width inside a narrower scroller puts that
    // edge outside the container no matter which of the two things occupies it.
    // Swapping three icons for one ⋯ toggle buys back 42px; measured live with
    // a framework selected and the sidebar EXPANDED, the compact set overflowed
    // by 105px at a 1020px viewport, 93px at 1040, 73px at 1060, 53px at 1080,
    // 33px at 1100 and 13px at 1120 -- so the toggle that replaced the clipped
    // cluster was itself clipped, and Edit / Clone / Delete were reachable by no
    // path at all. Task 45 found this band, reported it, and deliberately left
    // it: the remedy is a tier-ladder change, not a patch inside a regression
    // fix.
    //
    // The remedy is NOT a width. It is the same measurement, applied twice,
    // because "the folded set fits" is exactly as emergent a layout fact as "the
    // full set fits" and every constant this tier has been given went stale
    // (1400 -> 1540 -> invalid the day the Applicability column landed). What
    // stops the second fold is the QUEUE tier, which rebuilds every row as a
    // grid: once a row is a grid it cannot overflow at all, so the actions are
    // inside the scroller by construction rather than by arithmetic.
    //
    // The ladder is three rungs, each measured from a state that is fixed by the
    // rung and never by the class the page happens to be wearing:
    //
    //   1. strip BOTH tier classes, measure. Fits => full tier, no classes.
    //   2. else add .sr-fw-compact alone, measure. Fits => compact tier.
    //   3. else => queue tier (.sr-fw-compact + .sr-fw-queue).
    //
    // Each rung's input is therefore independent of its own output -- the
    // property Task 45 established and the reason the tier settles in one pass
    // instead of oscillating. Rung 2 deliberately measures WITH the compact
    // class on, because "does the folded set fit" is a question about the folded
    // layout (three columns hidden AND the rail narrowed to 232px); that is the
    // state being asked about, not the state being reacted to.
    //
    // The sidebar is the trigger that found this. Expanding it costs the pane
    // ~194px, so a viewport that is fine collapsed is broken expanded -- which
    // is why nothing keyed on VIEWPORT width can be correct here. This ladder
    // never reads a viewport width at all; the ResizeObserver on the card
    // already re-runs it on a sidebar toggle, with no resize event involved.
    //
    // governance/index.php ships BOTH classes on, so the fail-safe stays the
    // fail-safe one rung further down: with no JS, or before the first
    // measurement, the rows are stacked and the ⋯ menu is inside the scroller.
    // JS only ever RELAXES, and only after measuring that relaxing is safe.
    function compactTierIsOn() {
        var panes = document.querySelector('.sr-fw-panes');
        return !!panes && panes.classList.contains('sr-fw-compact');
    }

    function evaluateResponsiveTiers() {
        var panes = document.querySelector('.sr-fw-panes');
        var scroll = document.querySelector('#sr-ctl-table .sr-table-scroll');
        if (!panes || !scroll) { return; }
        // An empty or hidden scroller (an empty state is showing, or the card
        // is display:none) reports 0/0, and 0 > 0 is false -- which would read
        // as "everything fits" and drop the tier on a table that isn't there.
        // Keep the standing decision instead; the next render re-measures.
        if (!scroll.clientWidth) { return; }

        var wasCompact = panes.classList.contains('sr-fw-compact');
        var wasQueue = panes.classList.contains('sr-fw-queue');
        var compact;
        var queue;

        // Rung 1's state: neither class. Both are removed BEFORE anything is
        // read -- including the queue-tier probe below, which would otherwise
        // be reading the output of the last decision rather than the page.
        panes.classList.remove('sr-fw-compact', 'sr-fw-queue');

        // The queue tier ALSO has a viewport trigger of its own, and that one
        // is deliberately still an @media. It answers a different question --
        // "is this a phone, so should the row stop being a row and become a
        // stacked block?" -- which is about how the row is READ, not about
        // whether it fits. Where that @media is in force the stylesheet has
        // already stacked the rows, so there is nothing left to measure: the
        // table cannot overflow, a fit test would read "everything fits", and
        // dropping the compact tier on that reading would take .sr-ctl-sub with
        // it -- the ONLY place Family and Owner survive once the queue tier has
        // hidden their columns -- along with the Family/Owner facet fold. So the
        // compact tier is held ON there instead, and .sr-fw-queue is left OFF:
        // the layout it would apply is already applied, by the @media.
        //
        // Detected from that tier's own output (it hides the thead) rather than
        // by restating its 900px, which would be a second copy of a number this
        // file exists to stop keeping. Read with both classes already stripped,
        // so a hidden thead can only mean the @media -- never our own class.
        var thead = document.getElementById('sr-ctl-thead');
        if (thead && window.getComputedStyle(thead).display === 'none') {
            compact = true;
            queue = false;
        } else if (scroll.scrollWidth <= scroll.clientWidth) {
            // Forced layout, deliberately: this read is rung 1's decision.
            compact = false;
            queue = false;
        } else {
            // Rung 2's state: the compact class alone. The read below is what
            // Task 45 never asked -- the folded set's own fit.
            panes.classList.add('sr-fw-compact');
            compact = true;
            queue = scroll.scrollWidth > scroll.clientWidth;
        }

        panes.classList.toggle('sr-fw-compact', compact);
        panes.classList.toggle('sr-fw-queue', queue);

        // The Family/Owner facets move between the toolbar and the filter
        // sheet on the COMPACT condition, so a flip of that rung has to
        // relocate them -- this is what the old matchMedia 'change' listener
        // did. Guarded on the toolbar existing: before the first
        // renderToolbar() there is nothing to place, and renderToolbar() calls
        // syncInlineFacetPlacement() itself the moment there is.
        if (compact !== wasCompact && document.getElementById('sr-ctl-filters')) {
            syncInlineFacetPlacement();
        }

        // A tier flip changes how tall a row is -- the queue tier stacks each
        // row into a grid -- so the virtual list's height model is void the
        // instant either class moves (Task 47). Discarded rather than adjusted:
        // a stale height model is a lying scrollbar, and re-measuring costs one
        // render pass.
        if (compact !== wasCompact || queue !== wasQueue) { virtInvalidateHeights(); }
    }

    // Coalesces the resize/observer firehose to one measurement per frame.
    // Callers that have just rebuilt the table call evaluateResponsiveTiers()
    // directly instead, so the tier is correct before anything can observe it.
    var tierEvalFrame = 0;
    function scheduleTierEval() {
        if (tierEvalFrame) { return; }
        tierEvalFrame = window.requestAnimationFrame(function () {
            tierEvalFrame = 0;
            evaluateResponsiveTiers();
        });
    }

    // The ONE-TIME-built { $native, $wrapper } pair for each inline facet
    // (Family, Owner), keyed by facet name. This is the actual persistence
    // mechanism for "never rebuilt" -- inlineFacetPair() below returns from
    // here before ever touching the DOM. A DOM lookup alone (e.g.
    // `$('#sr-ctl-filter-family')`, which jQuery fast-paths to
    // getElementById) is NOT sufficient: getElementById cannot find a node
    // while it's detached (e.g. mid-renderToolbar(), between the pair being
    // detached to survive $toolbar.empty() and being re-inserted by
    // syncInlineFacetPlacement()), so a lookup-only approach falls through
    // to "build a new one" on every wide-tier reload -- silently duplicating
    // the pair's srSelectEnhance() side effects (an un-.off()'d
    // `$(document).on('mousedown.srselect', ...)` per build) on every
    // search-debounce settle, sort, framework switch, filter change, and
    // page turn. This cache is the fix: it's the reference itself, valid
    // whether the pair is currently attached anywhere or not.
    var inlineFacetCache = {};

    // reloadTable()'s integration hook (Task 2) -- merges this straight into
    // the controls/table query string.
    window.SRFrameworksFilters = function () {
        var q = {};
        FACET_FIELDS.forEach(function (k) {
            if (filters[k].length) { q[k] = filters[k].join(','); }
        });
        if (filters.status.length) { q.status = filters.status.join(','); }
        // maturity is a CSV of bucket tokens (Task 34), applicability a CSV of
        // state tokens (Task 14); both are allowlisted server-side
        // (parse_controls_table_request(), api/v2/includes/governance_controls.php)
        // so an unrecognized value here would just be dropped, never mis-filter.
        if (filters.maturity.length) { q.maturity = filters.maturity.join(','); }
        if (filters.applicability.length) { q.applicability = filters.applicability.join(','); }
        if (filters.text) { q.text = filters.text; }
        return q;
    };

    /**
     * The filter map an ESCALATED ("Select all N") bulk action is resolved over
     * server-side.
     *
     * window.SRFrameworksFilters() deliberately omits the rail's framework
     * scope: the server takes it as its own `framework` argument and ASSIGNS it
     * over the map (controls_table_filtered_controls(), api/v2/includes/
     * governance_controls.php), so for a real framework sending it twice is
     * redundant.
     *
     * It is NOT redundant for the synthetic "Unassigned controls" scope. Both
     * that assignment and parse_controls_bulk_delete_request() read the
     * `framework` argument as "a real framework id, or nothing" -- any
     * non-positive value becomes null -- so a -1 sent through that channel
     * arrives as NO framework scope at all, and an escalated Delete of the 16
     * rows on screen would resolve over the entire 1,552-control catalogue.
     * The filter MAP is the one channel that accepts -1, because
     * parse_controls_table_request() parses `framework` like every other id
     * facet, sentinel included. Carrying the scope there is what keeps the
     * number the bulk bar shows and the set the server acts on identical --
     * the same invariant Task 13 built the escalation around.
     */
    function escalatedFilters() {
        var q = window.SRFrameworksFilters();
        if (state.framework !== null) { q.framework = String(state.framework); }
        return q;
    }

    // How many filters are narrowing the table right now, including the free
    // text search -- unlike Define Tests' quickfilters (whose search box
    // stays on screen at every width and can speak for itself), this page's
    // search box is REBUILT into the toolbar on every render alongside the
    // Filters badge, so leaving it out of the count would make the button
    // under-report while the search field silently filters the view.
    function activeFilterCount() {
        var n = 0;
        Object.keys(filters).forEach(function (k) {
            if (k === 'text') { if (filters[k]) { n++; } }
            else if (filters[k].length) { n++; }
        });
        return n;
    }

    // Whether the control table is currently narrowed by ANYTHING that could
    // make an empty result a "no results" rather than a genuine "no data yet"
    // -- the filter sheet/search (activeFilterCount()) PLUS the rail's own
    // framework selection. reloadTable() sends state.framework straight
    // through as `framework=` on every request (below), so picking a
    // zero-control framework in the rail narrows the view exactly like a
    // filter does, even though it isn't tracked in the `filters` object and
    // deliberately does NOT count toward the "Filters · n" toolbar badge
    // (framework selection already has its own affordance -- the highlighted
    // rail row -- so activeFilterCount() alone is right for that badge).
    // Empty-state intent (showControlsEmptyState() below) is the one place
    // that must fold both in: without it, clicking any zero-control framework
    // rendered "No controls defined yet" + Add control over an org with
    // hundreds of controls under its OTHER frameworks -- the exact §10
    // failure this task exists to prevent.
    function controlsViewIsNarrowed() {
        return activeFilterCount() > 0 || state.framework !== null;
    }

    // Reads every param the insights band's tiles deep-link with (Task 10) --
    // status/maturity/applicability/framework -- so the FIRST request this
    // page ever sends is already scoped exactly like a tile promised, with no
    // second, correcting fetch and no flash of an unfiltered table. Deliberately
    // validates each value against the same token set the server accepts
    // (STATUS_OPTIONS / the literal maturity/applicability tokens) rather than
    // passing it through raw -- an unrecognized value in the URL degrades to
    // "no filter" instead of silently round-tripping garbage back out via
    // writeUrl() on the next change.
    function readUrl() {
        var p = new URLSearchParams(window.location.search);
        var fw = p.get('framework');
        state.framework = fw === null || fw === '' ? null : parseInt(fw, 10);

        var statusValues = STATUS_OPTIONS.map(function (opt) { return opt.value; });
        filters.status = (p.get('status') || '').split(',')
            .map(function (s) { return s.trim(); })
            .filter(function (s) { return statusValues.indexOf(s) !== -1; });

        // Maturity is a CSV of bucket tokens now (Task 34), read the same way
        // status is. `below_target` -- the spelling the Below-target KPI tile
        // used before this facet existed -- is folded onto `below` rather than
        // rejected, matching CONTROLS_TABLE_MATURITY_ALIASES server-side, so a
        // bookmarked tile link still lands on a filter the sheet can show.
        var maturityValues = MATURITY_OPTIONS.map(function (opt) { return opt.value; });
        filters.maturity = (p.get('maturity') || '').split(',')
            .map(function (s) { return s.trim(); })
            .map(function (s) { return s === 'below_target' ? 'below' : s; })
            .filter(function (s, i, all) { return maturityValues.indexOf(s) !== -1 && all.indexOf(s) === i; });

        // Applicability is a CSV of state tokens now (Task 14), read the same
        // way status and maturity are, with two differences that both mirror
        // the server (parse_controls_table_request()):
        //
        //   - `excluded` -- the spelling the Excluded KPI tile used before this
        //     facet existed -- expands to BOTH deviation states rather than
        //     being rejected, so a bookmarked tile link still lands on a filter
        //     the sheet can show;
        //   - the whole facet is dropped unless a framework is scoped. Outside
        //     one framework's scope applicability has no single honest answer,
        //     the server ignores the parameter, and the facet is not offered --
        //     so keeping it in `filters` would make the "Filters · n" badge
        //     count a filter that is neither visible nor in force.
        //
        // scopedFramework() rather than a bare null test, so a hand-typed
        // ?framework=-1&applicability=... lands on the same "not offered"
        // answer syncApplicabilityFacet() gives the sheet.
        filters.applicability = scopedFramework() === null ? [] : readApplicabilityTokens(p.get('applicability'));
    }

    // The CSV -> validated, de-aliased, de-duplicated state token list.
    // Extracted from readUrl() because the framework-selection handler has to
    // apply the same rule when the scope changes, and two copies of an
    // allowlist is how a URL and a facet come to disagree. Pure.
    function readApplicabilityTokens(csv) {
        var allowed = APPLICABILITY_OPTIONS.map(function (opt) { return opt.value; });
        var out = [];
        (csv || '').split(',').forEach(function (raw) {
            var token = raw.trim();
            (APPLICABILITY_ALIASES[token] || [token]).forEach(function (s) {
                if (allowed.indexOf(s) !== -1 && out.indexOf(s) === -1) { out.push(s); }
            });
        });
        return out;
    }

    // Keeps the URL shareable as the user changes any of the params above --
    // called from every place that changes one of them (selectFramework(),
    // the sheet's Status select, Clear filters). Params this page's OTHER
    // facets (family/owner/class/phase/priority/type/text) apply are
    // deliberately left out of the URL: only the tokens the insights band
    // itself links to round-trip, matching "keep param names identical to
    // what the API already accepts" -- there's one filter vocabulary, not two.
    function writeUrl() {
        var p = new URLSearchParams(window.location.search);
        if (state.framework === null) { p.delete('framework'); }
        else { p.set('framework', String(state.framework)); }

        if (filters.status.length) { p.set('status', filters.status.join(',')); }
        else { p.delete('status'); }

        if (filters.maturity.length) { p.set('maturity', filters.maturity.join(',')); }
        else { p.delete('maturity'); }

        if (filters.applicability.length) { p.set('applicability', filters.applicability.join(',')); }
        else { p.delete('applicability'); }

        history.replaceState(null, '', window.location.pathname + (p.toString() ? '?' + p : ''));
    }

    // Empty states (Task 9, design-system.md §10) for the framework rail --
    // "no frameworks yet" (railFrameworks.length === 0 on the DEFAULT Active
    // status, decided in renderRail() below), "no frameworks match this
    // status" (railFrameworks.length === 0 after the user explicitly switched
    // status) or "no frameworks match your search" (railFrameworks.length > 0
    // but the search box narrowed the VISIBLE list to nothing -- Task 22), vs
    // "couldn't load" (this request failed outright). Distinct states so a
    // fetch failure never reads as "you have zero frameworks", and so an
    // explicit status switch or a search never reads as "you've never added
    // one" (§10's over-filtered-vs-no-data distinction, applied to the rail).
    //
    // `which` is one of null (show the list), 'empty' (no frameworks at all,
    // or the unavoidable first-load-on-Active ambiguity -- see renderRail()),
    // 'filtered' (nothing VISIBLE right now, for either of two causes -- see
    // `reason`), 'error'.
    //
    // `reason` only matters when which === 'filtered': it picks which of two
    // title/action pairs the shared tile shows -- 'status' (default, the
    // original Task 9 tile: "No frameworks match this status" + View active
    // frameworks) or 'search' ("No frameworks match your search" + Clear
    // search). Task 22 deliberately reuses this ONE tile for both causes
    // rather than adding a fourth rail empty-state block -- a status filter
    // and a search are both "the list you loaded has nothing visible right
    // now", just with a different way back.
    function showFrameworksEmptyState(which, reason) {
        $('#sr-fw-empty, #sr-fw-filtered, #sr-fw-error').addClass('d-none');
        $('#sr-fw-list').toggleClass('d-none', !!which);
        if (which) { $('#sr-fw-' + which).removeClass('d-none'); }

        if (which === 'filtered') {
            var isSearch = reason === 'search';
            $('#sr-fw-filtered-title').text(isSearch ? _lang['NoFrameworksMatchSearch'] : _lang['NoFrameworksMatchFilter']);
            $('#sr-fw-view-active').toggleClass('d-none', isSearch);
            $('#sr-fw-clear-search').toggleClass('d-none', !isSearch);
        }
    }

    // Fetches the purpose-built rail payload (Task 22 -- replaces the former
    // GET .../frameworks/treegrid call, which is shaped for the easyui
    // treegrids governance.js's Document Program/Exceptions tabs still use
    // and must not change) and caches it in railFrameworks before rendering.
    // The endpoint already returns a flat, parent-before-child ordered list
    // with a `depth` per row (build_framework_rail_rows(),
    // includes/governance.php) and a real per-framework control_count -- no
    // client-side flattening needed any more.
    function loadFrameworks() {
        var seq = nextSeq('frameworks');
        return $.getJSON(BASE_URL + '/api/v2/governance/frameworks/rail?status=' + state.status)
            .then(function (res) {
                if (isStaleSeq('frameworks', seq)) { return; }
                var rows = (res && res.data && res.data.rows) || [];
                // Not affected by ?status= -- a control mapped to no framework
                // is outside every framework, so no framework status filter can
                // change how many there are. The endpoint computes it that way
                // too; adopted here rather than recomputed for the same reason
                // every other count on this page is.
                railUnmappedCount = (res && res.data && res.data.unmapped_count) || 0;
                railFrameworks = rows.map(function (f) {
                    return {
                        value: f.value,
                        name: f.name,
                        depth: f.depth || 0,
                        control_count: f.control_count || 0,
                        // Task 27: SCF-origin chip flag from the rail endpoint
                        // (api_v2_governance_frameworks_rail()) -- dropped here
                        // like every other field not explicitly whitelisted, so
                        // it needs its own line same as control_count above.
                        is_scf: !!f.is_scf
                    };
                });
                renderRail();
            })
            .fail(function () {
                if (isStaleSeq('frameworks', seq)) { return; }
                showFrameworksEmptyState('error');
            });
    }

    // Which railFrameworks indices are visible for the current search text --
    // null means "no search, show everything" (the common, cheap case). A row
    // matches when its OWN name contains the query (case-insensitive); every
    // ANCESTOR of a match is pulled in too, even when the ancestor's own name
    // doesn't match. railFrameworks is in parent-before-child DFS order with
    // a `depth` per row (build_framework_rail_rows(), includes/governance.php),
    // so a match's nearest ancestor at each shallower depth is simply the
    // nearest PRECEDING row at that depth -- no separate parent-id lookup
    // needed. Without this, a child whose name matches but whose parent's
    // doesn't would render as an indented row with no visible parent above
    // it: orphaned under a hidden ancestor, the exact trap this task's brief
    // calls out. Pure: no DOM, no globals -- directly unit-testable.
    function computeVisibleFrameworkIndices(frameworks, query) {
        var q = (query || '').trim().toLowerCase();
        if (!q) { return null; }

        var visible = {};
        frameworks.forEach(function (f, i) {
            if ((f.name || '').toLowerCase().indexOf(q) === -1) { return; }
            visible[i] = true;
            var depth = f.depth || 0;
            for (var j = i - 1; j >= 0 && depth > 0; j--) {
                if ((frameworks[j].depth || 0) < depth) {
                    visible[j] = true;
                    depth = frameworks[j].depth || 0;
                }
            }
        });
        return visible;
    }

    // Re-derives the rendered rail from railFrameworks + railSearch on every
    // call -- the single render path for a fresh load, a search keystroke,
    // and a Clear search click alike (no separate "filter the existing DOM"
    // code path to keep in sync with this one).
    function renderRail() {
        var $list = $('#sr-fw-list').empty();
        // The two synthetic scopes (see UNMAPPED above) are always present,
        // never hidden by a search or a facet, and "All controls" is the
        // default selection: facets narrow, they never gate, and a name search
        // has no honest bearing on a row that names no framework to match.
        //
        // "All controls", not "All frameworks": this row scopes to every
        // control in the catalogue, including the ones belonging to no
        // framework, so the old label named a set the row did not show.
        $list.append(railRow(null, _lang['AllControls'], state.total, 0, false));
        // "Unassigned controls" -- rendered unconditionally, including at zero.
        // A zero here is an ANSWER ("nothing is orphaned"), which is exactly
        // what a compliance manager wants to be able to see at a glance, and it
        // renders in the same dimmed .sr-fw-zero treatment a framework with no
        // controls already gets. Hiding it at zero would also strand a deep
        // link (?framework=-1) on a rail with no row to highlight, and would
        // make the row appear and disappear under a user who is unmapping
        // controls -- the two states this row exists to make visible.
        $list.append(railRow(UNMAPPED, _lang['UnassignedControls'], railUnmappedCount, 0, false));

        // Neither synthetic row counts toward #sr-fw-count: that chip sits
        // beside the rail's "Frameworks" heading and counts frameworks, which
        // is what `shown` below tallies.
        var visible = computeVisibleFrameworkIndices(railFrameworks, railSearch);
        var shown = 0;
        railFrameworks.forEach(function (f, i) {
            if (visible !== null && !visible[i]) { return; }
            $list.append(railRow(f.value, f.name, f.control_count || 0, f.depth || 0, !!f.is_scf));
            shown++;
        });
        $('#sr-fw-count').text(shown);
        highlightRail();
        // The controls pane's heading names the selected framework, and the
        // name comes from railFrameworks -- which is loaded CONCURRENTLY with
        // the first table render ($.when(loadFrameworks(), reloadTable())), so
        // a deep link (?framework=<id>) can build the toolbar before any name
        // exists. Rebuilt from the same controlsPaneHeading() renderToolbar()
        // uses, not a second way of assembling it, so the two cannot disagree.
        // A no-op before the first toolbar render, when there is nothing to
        // replace.
        $('#sr-ctl-toolbar .sr-table-title').replaceWith(controlsPaneHeading());

        if (!railFrameworks.length) {
            // A successful load with zero real frameworks (the two synthetic
            // scope rows above don't count) is either "no frameworks
            // match this status" or "no frameworks yet", decided from state:
            // state.status !== '1' means the user explicitly left the default
            // Active status, so an empty result is unambiguously filter-driven
            // (there's a way back -- View active frameworks). state.status ===
            // '1' is the page's own default on first load, where an empty
            // result is genuinely ambiguous -- the endpoint's response is
            // scoped to the requested status alone, so this can't tell "no
            // frameworks at all" from "none active" without a second request.
            // Left as the documented limitation (task-9-report.md) rather
            // than guessed at. Nothing loaded means a search can't be the
            // cause, so `reason` is always 'status' here.
            showFrameworksEmptyState(state.status !== '1' ? 'filtered' : 'empty', 'status');
        } else if (railSearch && shown === 0) {
            // Real frameworks exist -- there IS data -- but none of the
            // loaded ones match the search box right now. Distinct from the
            // branch above (whose "no frameworks yet"/"no frameworks match
            // this status" copy would be actively misleading here), reusing
            // the SAME #sr-fw-filtered tile with the search wording
            // (showFrameworksEmptyState()'s own comment above).
            showFrameworksEmptyState('filtered', 'search');
        } else {
            showFrameworksEmptyState(null);
        }
    }

    /**
     * Wraps a .sr-row-actions cluster in the SHIPPED compact-tier disclosure
     * (.sr-row-actions-wrap + .sr-row-actions-toggle, _tables.scss), the same
     * shape compliance-define-tests.js renders.
     *
     * This is not decoration. _tables.scss's compact tier does two things at
     * once: it turns .sr-row-actions-toggle on (display: inline-flex) AND it
     * takes .sr-row-actions out of the flow (display: none) so the cluster can
     * be popped as a menu from that toggle. Rendering the cluster WITHOUT the
     * toggle -- which both call sites below used to do -- adopts only the
     * second half, so below the breakpoint the row's actions are not merely
     * hidden, they are unreachable: nothing is left on screen to open them
     * with. Measured before the fix: at 1400px and narrower, on both the
     * control table and the rail, computed display was `none` and the visible
     * button count was 0 at every width down to 768.
     *
     * Hover is the other half of the same problem. The cluster reveals on
     * hover, which a touch pointer never produces at ANY width -- so the
     * toggle is also the only path these actions have on a tablet or phone.
     * _tables.scss claims the disclosure for `(hover: none)` as well as for
     * the narrow viewport for exactly that reason.
     *
     * ONE DOM, two presentations (Define Tests' own note): the wide tier shows
     * the cluster inline and the toggle is display:none; the compact tier pops
     * this same cluster as the menu. Building a second menu would duplicate
     * every permission branch inside it and let the two copies drift.
     *
     * @param {jQuery} $actions a populated .sr-row-actions cluster
     * @returns {jQuery} the .sr-row-actions-wrap to insert in its place
     */
    function rowActionsWrap($actions) {
        // aria-label as well as title: the toggle is icon-only (its only child
        // is an aria-hidden glyph), so title alone gives it a tooltip but no
        // accessible NAME. The || fallback keeps it labelled even if the lang
        // value ever fails to ship -- jQuery treats .attr(name, undefined) as a
        // GETTER, so an undefined would silently write no attribute at all.
        var actionsLabel = _lang['Actions'] || 'Actions';
        return $('<span class="sr-row-actions-wrap">')
            .append($('<button type="button" class="sr-row-actions-toggle" aria-expanded="false" aria-haspopup="true">')
                .attr({ 'aria-label': actionsLabel, title: actionsLabel })
                .append($('<i class="fa fa-ellipsis" aria-hidden="true">')))
            .append($actions);
    }

    /** Shuts every open row-actions overflow menu, on both surfaces. */
    function closeRowActionMenus() {
        $('.sr-table-scroll.is-unclipped').removeClass('is-unclipped');
        $('.sr-row-actions-wrap.is-open')
            .removeClass('is-open is-up is-right')
            .find('.sr-row-actions-toggle')
            .attr('aria-expanded', 'false');
    }

    /**
     * Gives an already-open menu somewhere to go: lifts the table scroller's
     * clip when it is only clipping, flips the menu upward when it still won't
     * fit below, and -- in the rail only -- opens it RIGHTWARD when the
     * right-anchored menu would run off the rail's own left edge.
     * (_tables.scss/_governance-frameworks.scss own what "unclipped", "up" and
     * "right" look like; all three are measurements, so the decisions live
     * here.)
     *
     * The control table's rows sit inside .sr-table-scroll, whose
     * `overflow-x: auto` computes overflow-y to `auto` along with it -- the two
     * axes cannot be auto/visible -- so a menu popped from a row is clipped
     * VERTICALLY by a container that only ever wanted to scroll horizontally.
     * Two failures were measured before this existed, both at 1200px:
     *
     *   - last row of a full table: the menu ran 52px past the scroller and
     *     elementFromPoint() on Clone and Delete returned the footer;
     *   - a single filtered row: the scroller was 117px tall against a 92px
     *     menu, so neither direction fit and the flipped-up menu's top item
     *     hit-tested to the toolbar's own button.
     *
     * Unclipping fixes both wherever the table isn't actually scrolling
     * sideways, which is the normal case in this tier (the tier hides columns
     * precisely so the row fits). Where it IS scrolling the clip has to stay,
     * and the flip is what's left. The rail has no scroll container at all and
     * clips against the viewport; the same two rules cover it.
     *
     * Must run AFTER .is-open -- a display:none menu measures 0 high.
     */
    function orientRowActionMenu($wrap) {
        $wrap.removeClass('is-up is-right');
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

        // ===== The horizontal half of the same question (rail only) =========
        // The shipped menu is anchored to the toggle's RIGHT edge (`right: 0`,
        // _tables.scss) so it opens INTO the row rather than off the card's
        // right edge. That is correct for a data table, which is wide. The rail
        // is not: it is a 232-276px pane, and once the menu carries text labels
        // it measures 255px -- WIDER than the pane it is anchored inside. A
        // right-anchored menu therefore grows leftward straight out of the rail
        // and under the left navigation. Measured on the pre-fix build at every
        // width from 900 to 1920 with the sidebar expanded: the menu's left edge
        // sat 36px outside the rail's own left edge and 26px underneath the
        // sidebar, with the last four characters of every item buried.
        //
        // The remedy is to open it rightward instead, into the controls pane,
        // which has the room the rail does not. That is a MEASUREMENT, not a
        // constant, for the same reason `is-up` is: at <=900px the rail stops
        // being a 276px column and spans the full width (_governance-frameworks
        // .scss's stacked rule), so the toggle sits at the right of the VIEWPORT
        // and a rightward menu would run off screen -- there the shipped
        // right-anchored geometry is already correct and is left alone.
        //
        // Deliberately NOT applied to the control table, whose cluster is a real
        // column in a wide card: its menu has never reached that card's left
        // edge, so this condition is false there anyway, and right-anchoring is
        // what keeps it off the card's RIGHT edge, which is the edge that
        // surface actually overflows (Tasks 42/45). _governance-frameworks.scss
        // scopes the `is-right` geometry to #sr-fw-rail to match.
        var $rail = $wrap.closest('#sr-fw-rail');
        if (!$rail.length) { return; }

        var railLeft = $rail[0].getBoundingClientRect().left;
        var menuWidth = $menu[0].getBoundingClientRect().width;

        // Where each anchoring would put the menu. `right: 0`/`left: 0` are
        // relative to the wrap, which has no padding or border of its own, so
        // the menu's edge IS the wrap's edge in each case.
        var rightAnchoredLeft = wrapRect.right - menuWidth;
        var leftAnchoredRight = wrapRect.left + menuWidth;

        // Flip only when the default overflows the rail AND the flip fits on
        // screen -- the same "neither side fits, so leave it alone" rule the
        // vertical decision above uses.
        if (rightAnchoredLeft < railLeft && leftAnchoredRight <= window.innerWidth) {
            $wrap.addClass('is-right');
        }
    }

    function railRow(id, name, count, depth, isScf) {
        // .text() everywhere -- framework names are user-authored.
        var $li = $('<li class="sr-fw-item" role="button" tabindex="0">')
            .attr('data-sr-fw', id === null ? '' : id)
            .toggleClass('sr-fw-child', depth > 0)
            .toggleClass('sr-fw-zero', count === 0);
        // Name + SCF chip share the grid's first column, with the chip INLINE
        // after the name rather than stacked above the count in the right-hand
        // meta column (Josh: "align the SCF chip to the right of the framework
        // name and style it more like the count chip").
        //
        // Inline in the TEXT FLOW, inside a wrapper -- not a grid column of its
        // own. A third track is what an earlier pass tried and what the file's
        // own history warns about: it starved the name to ~67px at this rail's
        // widest tier and broke ordinary words mid-word ("Cybersecu"/"rity").
        // Flowing after the last word costs the name nothing when the chip
        // isn't there and wraps with it when it is.
        //
        // The wrapper, rather than putting the chip inside .sr-fw-name itself:
        // that span's text is the framework's name and nothing else -- the rail
        // is addressed by it (define-control-frameworks.page.ts matches
        // normalize-space() on exactly that element), so folding a badge into
        // it would make every framework read as "<name> SCF".
        var $nameWrap = $('<span class="sr-fw-namewrap">')
            .append($('<span class="sr-fw-name">').text(name));
        if (isScf) {
            // Composes the SHIPPED .sr-table-count pill the count chip already
            // uses, instead of restating its shape: same radius, type, colour
            // and border, so the two read as one family. It does NOT inherit
            // the count's fixed width -- that is keyed on #sr-fw-list .sr-fw-n,
            // and exists so numerals line up down a column, which a badge
            // sitting beside a name has no reason to do.
            $nameWrap.append($('<span class="sr-fw-origin sr-table-count">')
                .attr('title', _lang['ScfOriginHint'])
                .text(_lang['SCF']));
        }
        $li.append($nameWrap);
        // Count chip: the shipped .sr-table-count pill (_tables.scss, already
        // used for the toolbar's own result count just above this list) --
        // .sr-fw-n stays as a JS selector hook (updateAllFrameworksBadge()),
        // its FIXED width comes from _governance-frameworks.scss so every
        // count renders at the same width regardless of digit count. It is now
        // the grid's second item directly: the .sr-fw-meta flex column existed
        // to stack the SCF chip above it, and with the chip moved it had one
        // child and nothing left to arrange.
        $li.append($('<span class="sr-fw-n sr-table-count">').text(count.toLocaleString()));
        // Row actions (Task 8): the ⋯ menu the selected row pops -- same
        // shipped .sr-row-actions component the control table uses, and since
        // Task 90 always in its MENU presentation, at every width
        // (_governance-frameworks.scss's `#sr-fw-rail` include; the rationale
        // for the rail and not the control table is there). Not offered on
        // EITHER synthetic scope row -- "All controls" (id === null) or
        // "Unassigned controls" (id === -1) -- because neither names a
        // framework to edit, clone, delete or write a Statement of
        // Applicability about. `id > 0` rather than a second `id !== UNMAPPED`
        // test, so any future synthetic scope is excluded by construction
        // rather than by remembering to add it here.
        //
        // Each action is independently permission-gated (Task 58): Edit needs
        // `modify_frameworks`, Clone needs `add_new_frameworks` (it CREATES a
        // framework -- traced to createFrameworkCrud()'s own check, not assumed
        // from Edit's), Delete needs `delete_frameworks`, and the SoA needs
        // nothing beyond the `governance` this page already required. A user may
        // hold any subset, so the cluster can legitimately end up with four
        // actions, one, or -- once the synthetic rows are excluded -- never
        // zero, since the SoA action is always present.
        //
        // ===== ORDER, and why it is not frequency order (Task 90) ===========
        // Ordered by what the action does to the object the row names:
        //
        //   Edit                 act ON this framework. Routine, most frequent,
        //   Generate SoA         so it leads; the SoA is the marquee output of
        //                        this whole feature, run every audit cycle, and
        //                        sat third-of-four behind an unlabelled shield.
        //   Clone framework      creates a NEW framework FROM this one. Not an
        //                        edit of the row you opened the menu on, which
        //                        is exactly the confusion its old second
        //                        position invited.
        //   Delete               destroys it. Last, and the only
        //                        .sr-row-action-danger, per design-system §6.
        //
        // ORDER IS THE ONLY THING THAT ENCODES THAT (Task 94). The first pass at
        // this menu drew a hairline between each of those three intent groups,
        // on the reasoning that a menu can GROUP where a row of icons cannot --
        // two rules, cutting four items into 2/1/1. Josh: "The three dot menu
        // looks weird with the lines for sections." That is more structure than
        // four items can carry, and a 1px rule inset 6px each side reads as
        // clutter rather than as grouping. Nothing was lost with it: the
        // sequence above already says
        // what the rules said, and Delete being last and the only red item
        // already says "this one destroys" without a rule standing over it.
        //
        // The control table's own row menu orders Edit / Clone / Set
        // applicability / Delete -- Clone second, not third. The two agree on
        // the thing that matters (Delete last, alone, and the only red one);
        // they differ on Clone because "clone a control" copies a row into the
        // same table you are looking at, while "clone a framework" produces a
        // second framework with a controls set of its own. Deliberately not
        // reconciled by moving the control table's Clone: that would be a
        // change to a surface this task was not asked to touch, made only to
        // make two lists look alike.
        if (id !== null && id > 0) {
            var $actions = $('<span class="sr-row-actions">');
            // Appended straight into the cluster in the order above. The
            // group-then-join machinery this replaced existed ONLY to place the
            // separators safely -- every action is permission-gated, so a rule
            // emitted inline with the buttons is how a menu ends up opening on a
            // hairline, or carrying two in a row, for a user holding some other
            // subset of the bits. With no rules to place, what is left is the
            // three permission gates and the one ungated action, in order.
            if (can('modify_frameworks')) {
                $actions.append($('<button type="button" class="sr-row-action" data-sr-fw-edit>')
                    .attr({ title: _lang['Edit'], 'aria-label': _lang['Edit'], 'data-id': id, 'data-name': name })
                    .append($('<i class="fa fa-pen-to-square" aria-hidden="true">')));
            }
            // Task 17: the framework's Statement of Applicability, reachable
            // without selecting the framework first. Rendered on every rail row
            // -- unlike the toolbar button, which exists only while a framework
            // is scoped, this row IS the framework, so there is no ambiguity
            // about which one the document would be about.
            $actions.append($('<button type="button" class="sr-row-action" data-sr-fw-soa>')
                .attr({
                    title: _lang['GenerateStatementOfApplicability'],
                    'aria-label': _lang['GenerateStatementOfApplicability'],
                    'data-id': id
                })
                .append($('<i class="fa fa-file-shield" aria-hidden="true">')));

            // Clone (Task 64): the same fa-clone glyph the control table's own
            // Clone row action uses, and the same neutral .sr-row-action -- it
            // creates, it does not destroy.
            //
            // 'CloneFramework' rather than the bare 'Clone' the control row
            // uses: both surfaces on this page now carry a clone icon, and a
            // label reading only "Clone" beside a framework name does not say
            // whether it is the framework or the selection that would be cloned.
            //
            // `data-count` rides along so the banner can promise a real number
            // without a second round trip -- it is the count the rail is already
            // showing for this row, which is the number the user is looking at
            // when they click.
            if (can('add_new_frameworks')) {
                $actions.append($('<button type="button" class="sr-row-action" data-sr-fw-clone>')
                    .attr({
                        title: _lang['CloneFramework'],
                        'aria-label': _lang['CloneFramework'],
                        'data-id': id,
                        'data-name': name,
                        'data-count': count
                    })
                    .append($('<i class="fa fa-clone" aria-hidden="true">')));
            }

            if (can('delete_frameworks')) {
                $actions.append($('<button type="button" class="sr-row-action sr-row-action-danger" data-sr-fw-delete>')
                    .attr({ title: _lang['Delete'], 'aria-label': _lang['Delete'], 'data-id': id, 'data-name': name })
                    .append($('<i class="fa fa-trash" aria-hidden="true">')));
            }

            // The wrap, not the bare cluster: it carries the ⋯ toggle
            // (rowActionsWrap() above) that is the ONLY way into these actions
            // now, at every width. In this rail the wrap -- not the cluster --
            // is the absolutely-positioned overlay pinned to the row's content
            // edge, so the count chip still ends flush with the card
            // (_governance-frameworks.scss; the alignment 6b52e15944 bought is
            // preserved by keeping the actions entirely out of the row's grid
            // flow, exactly as before).
            //
            // Guarded on the cluster having something in it, the way the
            // control table's own guard is: a toggle that opens an empty menu
            // is worse than no toggle. Today the SoA action is ungated so this
            // cannot be false on a real framework row -- it is here so that
            // gating the SoA, or adding a bit to it, cannot silently ship one.
            if ($actions.children().length) {
                $li.append(rowActionsWrap($actions));
            }
        }
        return $li;
    }

    function highlightRail() {
        $('#sr-fw-list .sr-fw-item').attr('aria-current', null);
        var sel = state.framework === null ? '' : String(state.framework);
        $('#sr-fw-list .sr-fw-item[data-sr-fw="' + sel + '"]').attr('aria-current', 'true');
    }

    // The "All controls" row's count is the grand total (get_framework_controls_count()
    // server-side), which the controls-table endpoint returns as `total` on every request
    // regardless of the `framework` filter -- so it's correct to refresh straight from
    // state.total after any table load, never re-fetching the framework list for it.
    // This also fixes the race between loadFrameworks() and reloadTable() (run concurrently
    // via $.when in the initial load): whichever finishes first renders the badge with
    // whatever state.total happens to be at that moment, so this re-syncs it once
    // reloadTable() is actually done, and again on every later table reload.
    function updateAllFrameworksBadge() {
        $('#sr-fw-list .sr-fw-item[data-sr-fw=""] .sr-fw-n').text(state.total.toLocaleString());
    }

    /* ================= sr-select: listbox with a count chip per option ===
     * The reference implementation (srSelectEnhance() in
     * js/simplerisk/pages/compliance-define-tests.js) lives inside that
     * page's own closure and isn't exposed on `window` -- and this page
     * never loads that script anyway (governance/index.php only loads
     * CUSTOM:pages/governance-frameworks.js). So this ports the same
     * pattern here rather than reaching across pages: identical markup and
     * CSS classes (_sr-select.scss applies unchanged), same two rules --
     * counts ride as data-count (never baked into the label text), and every
     * label is set with .text() (family/owner names are user-authored, so
     * no .html() and no enableHTML anywhere in this component).
     *
     * Every facet select in this page's sheet is multi (<select multiple>),
     * so unlike the reference this port drops the single-select branch and
     * the zero-count "disabled option" support -- a zero-count facet option
     * still says something (no controls have this class), and is left
     * selectable for the same reason decorateOptionCounts() in
     * compliance-define-tests.js gives: clicking it is a legitimate way to
     * confirm the count.
     * ======================================================================
     */
    function srSelectRender($native) {
        var api = $native.data('srSelect');
        if (!api) { return; }

        var selectedValues = $native.val() || [];
        var selectedLabels = [];
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
                'aria-selected': isSelected ? 'true' : 'false'
            });

            $('<i>', {
                'class': 'fa fa-check sr-select-tick' + (isSelected ? '' : ' is-empty'),
                'aria-hidden': 'true'
            }).appendTo($row);

            $('<span>', { 'class': 'sr-select-text', text: $option.text() }).appendTo($row);
            if (count !== undefined && count !== '') {
                $('<span>', { 'class': 'sr-count-chip', text: count }).appendTo($row);
            }

            if (isSelected) { selectedLabels.push($option.text()); }

            $row.appendTo(api.$menu);
        });

        var label;
        if (!selectedLabels.length) {
            label = api.placeholder;
        } else if (selectedLabels.length === 1) {
            label = selectedLabels[0];
        } else {
            label = String(_lang['NSelected'] || '{n} selected').replace('{n}', selectedLabels.length);
        }
        api.$button.find('.sr-select-value').text(label);
    }

    function srSelectClose(api, refocus) {
        api.$menu.attr('hidden', 'hidden');
        api.$button.attr('aria-expanded', 'false');
        if (refocus) { api.$button.trigger('focus'); }
    }

    function srSelectOpen(api) {
        api.$menu.removeAttr('hidden');
        api.$button.attr('aria-expanded', 'true');
        var $selected = api.$menu.find('[aria-selected="true"]').first();
        srSelectActivate(api, $selected.length ? $selected : api.$menu.find('.sr-select-option').first());
    }

    function srSelectActivate(api, $row) {
        if (!$row || !$row.length) { return; }
        api.$menu.find('.sr-select-option').removeClass('is-active');
        $row.addClass('is-active');
        if ($row[0].scrollIntoView) { $row[0].scrollIntoView({ block: 'nearest' }); }
    }

    function srSelectMove(api, delta) {
        var $rows = api.$menu.find('.sr-select-option');
        if (!$rows.length) { return; }
        var index = $rows.index(api.$menu.find('.sr-select-option.is-active'));
        var next = index + delta;
        if (next < 0) { next = $rows.length - 1; }
        if (next >= $rows.length) { next = 0; }
        srSelectActivate(api, $rows.eq(next));
    }

    function srSelectChoose($native, value) {
        var api = $native.data('srSelect');
        // Toggle, and keep the menu OPEN: picking several is the whole point,
        // and closing after each tick would make that a chore.
        var $option = $native.find('option').filter(function () { return $(this).attr('value') === value; });
        $option.prop('selected', !$option.prop('selected'));
        srSelectRender($native);
        srSelectActivate(api, api.$menu.find('[data-value="' + value + '"]'));
        // The native 'change' is what the rest of the page listens to.
        $native.trigger('change');
    }

    function srSelectEnhance($native, placeholder) {
        if (!$native.length || $native.data('srSelect')) { return; }

        var $wrapper = $('<div>', { 'class': 'sr-select' });
        var $button = $('<button>', {
            type: 'button',
            'class': 'sr-select-button',
            'aria-haspopup': 'listbox',
            'aria-expanded': 'false',
            'aria-label': $native.attr('aria-label') || $native.attr('title') || ''
        });
        $('<span>', { 'class': 'sr-select-value' }).appendTo($button);
        $('<i>', { 'class': 'fa fa-chevron-down sr-select-caret', 'aria-hidden': 'true' }).appendTo($button);

        var $menu = $('<div>', { 'class': 'sr-select-menu', role: 'listbox', tabindex: '-1', 'aria-multiselectable': 'true' })
            .attr('hidden', 'hidden');

        // Visually hidden (not display:none) -- stays the source of truth
        // for .val()/'change', and stays a real, non-zero-size element the
        // e2e specs can drive with selectOption() (see _sr-select.scss).
        $native.addClass('sr-select-native').attr('tabindex', '-1').attr('aria-hidden', 'true');
        $native.after($wrapper);
        $wrapper.append($button).append($menu);

        var api = { $button: $button, $menu: $menu, placeholder: placeholder || $native.attr('data-placeholder') || '' };
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
                case 'Home': e.preventDefault(); srSelectActivate(api, $menu.find('.sr-select-option').first()); break;
                case 'End': e.preventDefault(); srSelectActivate(api, $menu.find('.sr-select-option').last()); break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    var $active = $menu.find('.sr-select-option.is-active');
                    if ($active.length) { srSelectChoose($native, $active.attr('data-value')); }
                    break;
                case 'Escape': e.preventDefault(); srSelectClose(api, true); break;
                case 'Tab': srSelectClose(api, false); break;
                default: break;
            }
        });

        // Clicking anywhere else dismisses it, like any other menu. Shared
        // namespace across every enhanced select on the page -- jQuery is
        // fine stacking multiple handlers under one namespace, and none of
        // them are ever individually .off()'d.
        $(document).on('mousedown.srselect', function (e) {
            if (!$wrapper[0].contains(e.target) && !$menu.attr('hidden')) { srSelectClose(api, false); }
        });

        srSelectRender($native);
    }

    // ===== Filter sheet: 5 sr-selects (+ Family/Owner below the compact
    // breakpoint) + Clear filters, behind "Filters · n" =====================
    //
    // Class/Phase/Priority/Type/Status always live here. Family and Owner
    // are built into #sr-ctl-toolbar instead ([Family ▾] [Owner ▾]
    // [Filters · n]) at wide widths -- design-system.md 6b's "filters
    // collapse before the table does" only means something if there is a
    // visible-at-wide-widths filter set for Task 5's responsive ladder to
    // fold away. Below the compact breakpoint, syncInlineFacetPlacement()
    // physically MOVES the Family/Owner <select> + its sr-select wrapper
    // into this sheet (prepended before the Class field, so the sheet reads
    // family -> owner -> class -> phase -> priority -> type -> status) --
    // moved, never hidden-in-place: a filter a user can't see or clear is
    // worse than not having it, and a hidden-but-applied filter makes the
    // Filters badge lie about what's actionable. Applicability (Task 14) is
    // the 6th permanent select here, and the one field in this sheet that
    // appears CONDITIONALLY -- see syncApplicabilityFacet().
    function ensureFilterSheet() {
        if ($('#sr-ctl-filter-sheet').length) { return; }

        var $sheet = $('<div class="sr-ctl-filter-sheet" id="sr-ctl-filter-sheet">').attr('hidden', 'hidden');
        var $grid = $('<div class="sr-ctl-filter-grid">').appendTo($sheet);

        appendFacetField($grid, 'class', _lang['ControlClass'], _lang['AnyClass']);
        appendFacetField($grid, 'phase', _lang['ControlPhase'], _lang['AnyPhase']);
        appendFacetField($grid, 'priority', _lang['ControlPriority'], _lang['AnyPriority']);
        appendFacetField($grid, 'type', _lang['ControlType'], _lang['AnyType']);
        var $statusSelect = appendFacetField($grid, 'status', _lang['Status'], _lang['AnyStatus']);
        // Maturity (Task 34) sits after Status, the other computed-token
        // facet -- both answer "what state is this control in", as opposed to
        // the six lookup-table facets above them that answer "what is it".
        var $maturitySelect = appendFacetField($grid, 'maturity', _lang['Maturity'], _lang['AnyMaturity']);
        // Applicability (Task 14) sits last, after the other two computed-token
        // facets, because it is the one that is only sometimes there: a field
        // that appears and disappears reads as a glitch in the middle of a grid
        // and as a disclosure at the end of one.
        var $applicabilitySelect = appendFacetField($grid, 'applicability', _lang['Applicability'], _lang['AnyApplicability']);

        var $actions = $('<div class="sr-ctl-filter-actions">').appendTo($sheet);
        $('<button type="button" class="sr-ctl-filter-clear" id="sr-ctl-clear-filters">')
            .text(_lang['ClearFilters'])
            .appendTo($actions);

        // Sibling of the toolbar, inside the same card -- so it survives
        // every renderToolbar() re-render (which only empties #sr-ctl-toolbar
        // itself) and sits between the toolbar and the table.
        $('#sr-ctl-toolbar').after($sheet);

        // Status, Maturity and Applicability are fixed 3-token enums -- no
        // server round trip needed for the OPTIONS, so populate them once here
        // rather than waiting on rebuildFilterOptions(). (Status's and
        // Maturity's COUNT chips do come from that fetch, and both are
        // repopulated when it resolves.)
        populateStatusSelect($statusSelect);
        populateMaturitySelect($maturitySelect);
        populateApplicabilitySelect($applicabilitySelect);
        syncApplicabilityFacet();

        // The sheet's 4 id-based facets (status has no list to rebuild) start
        // with whatever the cache already holds -- non-empty after the first
        // rebuildFilterOptions() resolves, so re-opening the sheet later never
        // finds it emptier than a fresh table load would.
        populateFacetSelect($sheet.find('[data-facet="class"]'), facetListCache.class);
        populateFacetSelect($sheet.find('[data-facet="phase"]'), facetListCache.phase);
        populateFacetSelect($sheet.find('[data-facet="priority"]'), facetListCache.priority);
        populateFacetSelect($sheet.find('[data-facet="type"]'), facetListCache.type);
    }

    // No visible <label>: the sr-select button already shows the facet name as
    // its placeholder whenever nothing is selected (every call site passes the
    // facet's own "Any <facet>" string), so a label above it said the same
    // thing twice.
    //
    // The label was the select's only accessible name though, so the name moves
    // to aria-label rather than being dropped. It is set on the NATIVE select
    // BEFORE srSelectEnhance() runs, because that function copies
    // $native.attr('aria-label') onto the .sr-select-button it builds -- and
    // that button, not the (aria-hidden, tabindex="-1") native select, is what
    // actually receives focus.
    function appendFacetField($grid, facet, label, placeholder) {
        var $field = $('<div class="sr-ctl-filter-field">').appendTo($grid);
        var $select = $('<select multiple>')
            .attr({ id: 'sr-ctl-filter-' + facet, 'data-facet': facet, 'aria-label': label })
            .addClass('sr-ctl-filter-select')
            .appendTo($field);
        srSelectEnhance($select, placeholder);
        return $select;
    }

    // ===== Family/Owner facet placement (Task 5 fix) =========================
    //
    // These two facets have exactly ONE native <select> + one sr-select
    // wrapper each, for the life of the page -- never destroyed and rebuilt.
    // Only their CONTAINER changes: #sr-ctl-toolbar at wide widths,
    // #sr-ctl-filter-sheet below the compact breakpoint. Recreating them on
    // every fold (the way the rest of the toolbar is rebuilt on every
    // render) would lose the enhanced widget's bound handlers and drop
    // whatever the user had selected -- exactly the "filter vanishes on
    // resize" failure mode this exists to prevent.

    // Returns the { $native, $wrapper } pair for a facet from
    // inlineFacetCache, building it (once, ever -- srSelectEnhance() must
    // run exactly once per facet, not once per render) only on the very
    // first call. Deliberately does NOT fall back to a DOM lookup: a
    // getElementById-style lookup can't find the pair while it's mid-move
    // (detached to survive a renderToolbar() rebuild -- see the cache's own
    // comment above), and a lookup that "fails" there is exactly what
    // produced the leak this cache exists to fix. srSelectEnhance() needs
    // the select attached to something before it can insert its wrapper as
    // a following sibling, so a brand new select is parked in a throwaway
    // detached <div> first -- placeInlineFacet() (called right after, from
    // syncInlineFacetPlacement()) moves the pair to its real home on the
    // same tick.
    //
    // `label` is the facet's accessible name. It has to be applied HERE, on
    // the one-and-only build, because srSelectEnhance() copies it onto the
    // .sr-select-button (the element that actually takes focus) exactly once
    // -- setting it later, e.g. when the pair moves into the sheet, would
    // never reach the button. Before Task 29 these two facets carried no
    // aria-label at all and relied on a <label> that only existed while they
    // were folded into the sheet, so at wide widths they had no accessible
    // name whatsoever.
    function inlineFacetPair(facet, placeholder, label) {
        var cached = inlineFacetCache[facet];
        if (cached) { return cached; }

        var $native = $('<select multiple>')
            .attr({ id: 'sr-ctl-filter-' + facet, 'data-facet': facet, 'aria-label': label })
            .addClass('sr-ctl-filter-select sr-ctl-inline-select');
        $('<div>').append($native);
        srSelectEnhance($native, placeholder);

        cached = { $native: $native, $wrapper: $native.next('.sr-select') };
        inlineFacetCache[facet] = cached;
        return cached;
    }

    // Moves one facet's native <select> + its sr-select wrapper (captured
    // together in `pair`, from inlineFacetCache -- NOT re-derived via
    // $native.next() here, because a detached native select has no sibling
    // list to walk) as a single unit to wherever it belongs for the CURRENT
    // viewport -- idempotent, so calling this when the pair is already in
    // the right place is a no-op. Always MOVES the existing nodes
    // (.before()/.append() reparent rather than clone when given live DOM
    // elements), never rebuilds them, so bound handlers, jQuery data, and
    // the current selection all survive.
    function placeInlineFacet(pair, folded, $toolbarAnchor, $sheetAnchor) {
        var $native = pair.$native;
        var $wrapper = pair.$wrapper;
        var inSheet = $native.closest('#sr-ctl-filter-sheet').length > 0;

        if (folded) {
            if (inSheet) { return; }
            // Wrapped in a .sr-ctl-filter-field only while resident in the
            // sheet's grid, so it picks up the grid cell's sizing -- unwrapped
            // again the moment it moves back to the toolbar. No <label> in
            // either home now (Task 29): the accessible name lives on the
            // pair's aria-label, set once in inlineFacetPair(), so it survives
            // every move instead of appearing and disappearing with the fold.
            var $field = $('<div class="sr-ctl-filter-field sr-ctl-filter-field--facet">');
            $field.append($native, $wrapper);
            $sheetAnchor.before($field);
            return;
        }

        var $oldField = $native.closest('.sr-ctl-filter-field--facet');
        if ($oldField.length) {
            $toolbarAnchor.before($native, $wrapper);
            $oldField.remove();
            return;
        }
        if (!$native.closest('#sr-ctl-toolbar').length) {
            // First-ever placement of a brand new pair from inlineFacetPair().
            $toolbarAnchor.before($native, $wrapper);
        }
        // else: already correctly placed in the toolbar -- nothing to do.
    }

    // Single source of truth for where Family/Owner belong right now --
    // called after every renderToolbar() rebuild (so a fresh table load
    // lands them correctly) AND from evaluateResponsiveTiers() on every tier flip
    // (so a plain resize, with no data reload in between, still relocates
    // them). The fold reads the tier's own class rather than a query of its
    // own: this is the same condition, and a second expression of it is how
    // the two drifted before Task 42.
    function syncInlineFacetPlacement() {
        ensureFilterSheet();
        var folded = compactTierIsOn();
        var $toolbarAnchor = $('#sr-ctl-filters');
        var $sheetAnchor = $('#sr-ctl-filter-sheet [data-facet="class"]').closest('.sr-ctl-filter-field');

        var familyPair = inlineFacetPair('family', _lang['AnyFamily'], _lang['ControlFamily']);
        var ownerPair = inlineFacetPair('owner', _lang['AnyOwner'], _lang['Owner']);

        // Family before Owner in both homes -- called in this order so the
        // toolbar's second row reads Search -> Family -> Owner -> Filters
        // (Task 49 moved Add Control up to the first row, beside the pane's
        // heading), and the sheet reads Family -> Owner -> Class -> ... (both
        // inserted via .before() against a stable anchor that doesn't itself
        // move).
        placeInlineFacet(familyPair, folded, $toolbarAnchor, $sheetAnchor);
        placeInlineFacet(ownerPair, folded, $toolbarAnchor, $sheetAnchor);

        populateFacetSelect(familyPair.$native, facetListCache.family);
        populateFacetSelect(ownerPair.$native, facetListCache.owner);
    }

    // Writes a count onto an <option> as data-count -- the ONLY way a count is
    // ever attached in this page. The sr-select renderer draws it as a chip, so
    // the option's own text stays the label and nothing is appended to it.
    // A count the server didn't send leaves the option chip-less rather than
    // showing a made-up 0: "we don't know yet" and "there are none" are
    // different statements, and the first is true until rebuildFilterOptions()
    // resolves.
    function applyOptionCount($option, count) {
        if (typeof count !== 'undefined' && count !== null && count !== '') {
            $option.attr('data-count', count);
        }
    }

    function populateStatusSelect($select) {
        if (!$select || !$select.length) { return; }
        var selected = {};
        filters.status.forEach(function (v) { selected[v] = true; });

        $select.empty();
        STATUS_OPTIONS.forEach(function (opt) {
            var $option = $('<option>', { value: opt.value }).text(_lang[opt.key]);
            // Status has no option LIST to carry counts on (three computed
            // tokens, no lookup table), so its counts arrive as their own
            // rebuild_control_filters key, keyed by the very same tokens
            // STATUS_OPTIONS uses and the table filter sends.
            applyOptionCount($option, facetCountCache.status[opt.value]);
            if (selected[opt.value]) { $option.prop('selected', true); }
            $select.append($option);
        });

        srSelectRender($select);
    }

    // Maturity's three buckets (Task 34). Same shape as populateStatusSelect()
    // above and for the same reason: computed tokens, so there is no option
    // LIST from the server to carry counts -- the counts arrive as their own
    // rebuild_control_filters key (maturityCounts), keyed by the very same
    // tokens MATURITY_OPTIONS uses and the table filter sends. That shared
    // keying is what makes "the chip equals what filtering by it returns"
    // structural rather than a coincidence.
    function populateMaturitySelect($select) {
        if (!$select || !$select.length) { return; }
        var selected = {};
        filters.maturity.forEach(function (v) { selected[v] = true; });

        $select.empty();
        MATURITY_OPTIONS.forEach(function (opt) {
            var $option = $('<option>', { value: opt.value }).text(_lang[opt.key]);
            applyOptionCount($option, facetCountCache.maturity[opt.value]);
            if (selected[opt.value]) { $option.prop('selected', true); }
            $select.append($option);
        });

        srSelectRender($select);
    }

    // Applicability's three states (Task 14). Same shape as the two above --
    // computed tokens, no lookup table -- with ONE difference: no count chips.
    // The counts the other two carry come from rebuild_control_filters, which
    // scopes by framework alone; an applicability count would additionally have
    // to resolve every control's state against the stored decisions, and a chip
    // whose number came from a second computation is exactly the tile-vs-table
    // drift Tasks 29/37/40 fixed on this page. applyOptionCount() draws no chip
    // for an unknown count rather than a false 0, so "we don't count this yet"
    // renders honestly instead of claiming zero.
    function populateApplicabilitySelect($select) {
        if (!$select || !$select.length) { return; }
        var selected = {};
        filters.applicability.forEach(function (v) { selected[v] = true; });

        $select.empty();
        APPLICABILITY_OPTIONS.forEach(function (opt) {
            var $option = $('<option>', { value: opt.value }).text(_lang[opt.key]);
            if (selected[opt.value]) { $option.prop('selected', true); }
            $select.append($option);
        });

        srSelectRender($select);
    }

    // Shows the Applicability facet only where the question has an answer.
    //
    // Applicability is per-framework: the same control excluded from ISO 27001
    // is not thereby excluded from PCI DSS, so under "All controls" there is
    // nothing for the facet to select on -- the server drops the parameter
    // (parse_controls_table_request()) and the column isn't rendered either.
    //
    // Hidden AND CLEARED together, never hidden while still applied: a filter
    // the user can't see or clear is unreachable, and a hidden-but-applied one
    // makes the "Filters · n" badge lie about what's actionable -- the same rule
    // that made Task 5 MOVE Family/Owner between the toolbar and this sheet
    // rather than hide them in place. Clearing is also the only honest option
    // here: on losing the framework scope the filter's tokens stop meaning
    // anything, so carrying them forward would silently narrow the next view by
    // a condition that no longer exists.
    //
    // The token clearing happens whether or not the sheet has been built yet
    // (it is created lazily on the first renderToolbar()); only the DOM half is
    // guarded. Clearing `filters` is the part that must never be skipped --
    // that is the state the next request is built from.
    function syncApplicabilityFacet() {
        // scopedFramework(), not `state.framework !== null`: applicability is a
        // decision made WITHIN a framework, and "Unassigned controls" is the one
        // scope where there is provably no framework to have made it in. The
        // server already refuses the facet there
        // (controls_table_applicability_framework() resolves -1 to null), so a
        // client that still offered it would show a filter that silently does
        // nothing -- and the "Filters · n" badge would count it.
        var scoped = scopedFramework() !== null;
        if (!scoped) { filters.applicability = []; }

        var $select = $('#sr-ctl-filter-applicability');
        if (!$select.length) { return; }

        $select.closest('.sr-ctl-filter-field').toggleClass('d-none', !scoped);
        if (!scoped) {
            $select.val([]);
            srSelectRender($select);
        }
    }

    // Rebuilds one id-based facet <select>'s options from a rebuild_control_
    // filters list item ({value, name[, count]}), Unassigned(-1) always
    // first -- mirrors rebuild_filter()'s existing convention in
    // governance.js -- preserving whatever is currently selected in `filters`.
    function populateFacetSelect($select, list) {
        if (!$select || !$select.length) { return; }
        var facet = $select.attr('data-facet');
        var selected = {};
        (filters[facet] || []).forEach(function (v) { selected[String(v)] = true; });

        $select.empty();

        var $unassigned = $('<option>', { value: '-1' }).text(_lang['Unassigned']);
        // The -1 bucket can never appear in `list`: every getAvailableControl*
        // List() query ends its WHERE with "t2.value is not null", which drops
        // exactly the controls that have no value for this facet. Its count
        // therefore arrives separately, under rebuild_control_filters'
        // unassignedCounts key, computed from the same predicate
        // get_framework_controls_by_filter() uses for -1.
        applyOptionCount($unassigned, facetCountCache.unassigned[facet]);
        if (selected['-1']) { $unassigned.prop('selected', true); }
        $select.append($unassigned);

        (list || []).forEach(function (item) {
            var value = String(item.value);
            // .text() only -- family/owner/class/phase/priority/type names
            // are user-authored (add_remove_values.php lets admins rename
            // these lookup tables).
            var $option = $('<option>', { value: value }).text(item.name);
            // The count rides as DATA, not text appended to the label: the
            // sr-select renderer draws it as a chip, so the name stays the
            // name and the count stays a quantity.
            applyOptionCount($option, item.count);
            if (selected[value]) { $option.prop('selected', true); }
            $select.append($option);
        });

        srSelectRender($select);
    }

    // Last-fetched option lists, keyed by facet. renderToolbar() rebuilds the
    // 2 inline selects (Family, Owner) from SCRATCH on every table reload
    // (same full-rerender style as renderRail()/renderTable() elsewhere in
    // this file) -- without this cache, a fresh <select> would have no
    // <option>s until the NEXT framework change triggered a re-fetch, since
    // rebuildFilterOptions() below only calls the server when state.framework
    // actually changes. Populated once rebuildFilterOptions() resolves; every
    // later (re)build of an inline or sheet select reads from here instead of
    // re-requesting.
    var facetListCache = { family: [], owner: [], class: [], phase: [], priority: [], type: [] };

    // The three counts that don't ride on an option list, cached beside it and
    // refreshed by the same fetch, so they re-scope with the framework exactly
    // when the lists do:
    //
    //   .unassigned  facet key    -> count of controls in that facet's -1 bucket
    //   .status      status token -> count of controls in that status
    //   .maturity    bucket token -> count of controls in that maturity bucket
    //
    // All start empty rather than zeroed: until the first
    // rebuildFilterOptions() resolves the honest answer is "no count yet", and
    // applyOptionCount() renders no chip for that, rather than a false 0.
    var facetCountCache = { unassigned: {}, status: {}, maturity: {} };

    // Options are scoped only by the selected framework (rebuild_control_
    // filters' own contract -- includes/api.php's getControlFiltersByFrameworksResponse()
    // takes a single control_framework param, not the other facets), so
    // there's nothing to gain from refetching on every table reload the way
    // the legacy DataTable page did (there each reload was one combined
    // response anyway). maybeRebuildFilterOptions() below only calls this
    // when state.framework has actually changed.
    function rebuildFilterOptions() {
        ensureFilterSheet();
        var fw = state.framework;
        return $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/governance/rebuild_control_filters',
            data: { control_framework: fw === null ? [] : [fw] }
        }).then(function (res) {
            var data = (res && res.data) || {};
            facetListCache.family = data.familyList || [];
            facetListCache.owner = data.ownerList || [];
            facetListCache.class = data.classList || [];
            facetListCache.phase = data.phaseList || [];
            facetListCache.priority = data.priorityList || [];
            facetListCache.type = data.typeList || [];
            facetCountCache.unassigned = data.unassignedCounts || {};
            facetCountCache.status = data.statusCounts || {};
            facetCountCache.maturity = data.maturityCounts || {};

            populateFacetSelect($('#sr-ctl-filter-family'), facetListCache.family);
            populateFacetSelect($('#sr-ctl-filter-owner'), facetListCache.owner);
            var $sheet = $('#sr-ctl-filter-sheet');
            populateFacetSelect($sheet.find('[data-facet="class"]'), facetListCache.class);
            populateFacetSelect($sheet.find('[data-facet="phase"]'), facetListCache.phase);
            populateFacetSelect($sheet.find('[data-facet="priority"]'), facetListCache.priority);
            populateFacetSelect($sheet.find('[data-facet="type"]'), facetListCache.type);
            // Status and Maturity have no list, but their counts ARE
            // framework-scoped, so they have to be repopulated here too --
            // ensureFilterSheet() builds them once, before any fetch has
            // resolved, and nothing else would ever re-render them when the
            // framework changes.
            populateStatusSelect($sheet.find('[data-facet="status"]'));
            populateMaturitySelect($sheet.find('[data-facet="maturity"]'));
            // Applicability carries no counts (see populateApplicabilitySelect())
            // so it has nothing to refresh from this response -- but the fetch
            // fires exactly when the framework changed, which is precisely when
            // the facet has to appear or disappear.
            syncApplicabilityFacet();
        });
    }

    // undefined (not null) is the "never rebuilt yet" sentinel, distinct from
    // state.framework's own null ("All controls").
    var filterOptionsFramework;
    function maybeRebuildFilterOptions(st) {
        if (filterOptionsFramework !== undefined && filterOptionsFramework === st.framework) { return; }
        filterOptionsFramework = st.framework;
        rebuildFilterOptions();
    }

    // ===== Insights band rescoping (Task 10) =================================
    //
    // The band sits above this page's own rail (unlike its sibling on Define
    // Tests, which is global) -- a band reading "All controls" totals above
    // a table scoped to one framework would contradict itself. Re-fetches
    // every tile (window.srRefreshLayoutWidgets(), includes/Widgets/UILayout.php)
    // with the rail's current framework appended, but ONLY when the framework
    // actually changed -- same undefined-sentinel gating as
    // maybeRebuildFilterOptions() above, so a sort/page/search/facet reload
    // (which never changes what the band itself measures) doesn't refetch six
    // tiles for nothing.
    var bandFramework;
    function maybeRefreshBand(st) {
        if (bandFramework !== undefined && bandFramework === st.framework) { return; }
        bandFramework = st.framework;
        refreshBand();
    }

    // The UNCONDITIONAL refetch, for when the band's own numbers changed under
    // a framework that did not. maybeRefreshBand() above is deliberately gated
    // on the framework changing -- a sort, a page turn or a facet click never
    // alters what the band measures -- but a bulk applicability write does:
    // the Excluded tile counts exactly the decisions that call just created or
    // removed. Leaving it to the gated path would show a stale Excluded count
    // above a table already displaying the new states, which is the
    // count-disagrees-with-the-grid defect Tasks 29/37/40 spent a day removing
    // from this page. Anything that CHANGES a tile's underlying data calls this.
    function refreshBand() {
        if (typeof window.srRefreshLayoutWidgets !== 'function') { return; }
        var extra = state.framework === null ? '' : ('&framework=' + encodeURIComponent(state.framework));
        window.srRefreshLayoutWidgets('define_frameworks_insights', extra);
    }

    // ===== Toolbar: title + count, search, "Filters · n", + Add control =====
    /**
     * The controls pane's <h2>, naming the framework it is showing.
     *
     * Built here rather than inline in renderToolbar() only so the SCF chip and
     * the heading text can't be assembled two different ways; it is called from
     * exactly one place, in the SAME render path as #sr-ctl-count, so the
     * heading and the count can never disagree about which framework is
     * selected. state.framework is the single source for both.
     *
     * The SCF chip is the SAME composition railRow() uses -- the shipped
     * .sr-table-count pill plus the ScfOriginHint title, which is the only
     * thing on the page that explains what SCF means -- so the pane header and
     * the rail row read as one badge, not two. It is also where that signal
     * now lives for the framework being worked in: the rail hides the selected
     * row's own chips (they are what the actions take the place of).
     */
    function controlsPaneHeading() {
        var $h2 = $('<h2 class="sr-table-title">');
        // Both synthetic scopes read back the SAME string their own rail row
        // shows, rather than a second key saying the same thing -- the pane
        // heading and the rail row cannot drift into naming the same scope two
        // different ways.
        if (state.framework === null) {
            return $h2.text(_lang['AllControls']);
        }
        if (state.framework === UNMAPPED) {
            return $h2.text(_lang['UnassignedControls']);
        }
        var selected = null;
        railFrameworks.forEach(function (f) {
            if (String(f.value) === String(state.framework)) { selected = f; }
        });
        // railFrameworks is loaded concurrently with the first table render
        // ($.when(loadFrameworks(), reloadTable())), so the very first toolbar
        // build can land before the rail's names exist. Falling back to the
        // all-frameworks wording would be a lie about what the table is
        // showing; the count chip beside it is already correct, and
        // loadFrameworks()'s own renderRail()/reloadTable() settle re-renders
        // this with the real name.
        if (!selected) { return $h2.text(_lang['Controls']); }

        $h2.text(selected.name);
        if (selected.is_scf) {
            $h2.append($('<span class="sr-ctl-title-origin sr-table-count">')
                .attr('title', _lang['ScfOriginHint'])
                .text(_lang['SCF']));
        }
        return $h2;
    }

    function renderToolbar(st) {
        var $toolbar = $('#sr-ctl-toolbar');

        // Full re-render (matching this file's existing renderRail()/
        // renderTable() style) would otherwise steal focus out from under
        // whatever the user is mid-typing in the search box every time a
        // debounced keystroke reloads the table -- preserve it across the
        // rebuild.
        var $prevSearch = $toolbar.find('.sr-table-search');
        var hadFocus = $prevSearch.length > 0 && $prevSearch.is(':focus');
        var caret = hadFocus && $prevSearch[0].setSelectionRange ? $prevSearch[0].selectionStart : null;

        // Family/Owner (Task 5 fix) must survive this rebuild as the SAME
        // DOM nodes -- detach (not remove) the CACHED pair before wiping the
        // toolbar, so jQuery's .empty() cleanup doesn't strip the enhanced
        // widget's bound handlers/data out from under them. Operates on
        // inlineFacetCache directly rather than re-querying the DOM: a
        // selector-based `$toolbar.find(...).detach()` discards the very
        // reference that made the pair findable again afterward (a detached
        // node isn't reachable via getElementById), which is what silently
        // rebuilt -- and re-enhanced, leaking a mousedown listener -- a new
        // pair on every wide-tier reload. Only detaches a facet that's both
        // already built and currently a toolbar descendant -- narrow width
        // (living in the sheet) and the very first render (not built yet)
        // both correctly no-op here.
        $.each(inlineFacetCache, function (facet, pair) {
            if (pair.$native.closest('#sr-ctl-toolbar').length) {
                pair.$native.add(pair.$wrapper).detach();
            }
        });

        $toolbar.empty();
        // The heading NAMES the pane's subject -- the selected framework --
        // rather than repeating the word "Controls". A detail pane that doesn't
        // say what it is showing is an orientation gap: before this, the header
        // was byte-identical whichever framework was selected ("Controls" + a
        // count), so the only indicator of which framework you were in was the
        // rail's highlight, which is gone the moment the rail is scrolled and
        // unavailable at the widths where the rail collapses. The count chip and
        // the column headers already establish that the rows are controls.
        //
        // .text(), never concatenated into HTML: the name is user-authored.
        //
        // .sr-title-count-group wraps the heading and the count chip so the
        // two can be baseline-aligned WITHOUT touching .sr-table-toolbar's own
        // align-items:center -- that rule is shared with every other toolbar
        // built from _tables.scss (Define Tests, the rail's own header), and
        // re-aligning it there would move .sr-table-tools (the search box and
        // filters), which is much taller than this text and only happens to
        // sit level with it under align-items:center today. The same wrapper
        // class (and the same declaration block, scoped per-container) is
        // reused by the rail's own "Frameworks" + count header in
        // governance/index.php, which has the identical two-text-node
        // alignment problem and no JS-built markup of its own to hang a
        // second class off of. See _governance-frameworks.scss's
        // #sr-ctl-toolbar .sr-title-count-group / #sr-fw-rail
        // .sr-title-count-group rule for the measurements this was built
        // against.
        var $titleGroup = $('<div class="sr-title-count-group">');
        $titleGroup.append(controlsPaneHeading());
        $titleGroup.append($('<span class="sr-table-count" id="sr-ctl-count">').text(st.filtered.toLocaleString()));
        $toolbar.append($titleGroup);

        var $tools = $('<div class="sr-table-tools">');
        $('<input type="search" class="form-control sr-table-search">')
            .attr('placeholder', _lang['SearchControls'])
            .val(filters.text)
            .appendTo($tools);

        $('<button type="button" class="sr-table-filter" id="sr-ctl-filters" aria-haspopup="true" aria-expanded="false">')
            .append(document.createTextNode(_lang['Filters'] + ' '))
            .append($('<span class="sr-table-fcount" id="sr-ctl-fcount">').text(activeFilterCount()))
            .appendTo($tools);

        // The page's single $sr-important fill -- rendered, unwired; Task 8
        // owns the Add Control modal.
        //
        // A DIRECT child of the toolbar, not a member of .sr-table-tools
        // (Task 49). The toolbar is two explicit rows now -- the pane's subject
        // on top with its one primary action opposite it, every search and
        // filter control on the row beneath -- and this button belongs to the
        // first. Appended BEFORE .sr-table-tools so the DOM order is
        // title -> Add -> tools, which is the order the two rows are read in;
        // the stylesheet places all three explicitly, so tab order and visual
        // order agree rather than one being a consequence of the other.
        //
        // .sr-table-tools keeps the search and the filters, which is what the
        // shared class means everywhere else that "search + the one primary
        // action" is one cluster; here the action has its own row-1 slot, and
        // the deviation is scoped to #sr-ctl-toolbar in the stylesheet rather
        // than being taken out of the shared rule.
        // Row 1's action cluster. A WRAPPER, not two direct grid children:
        // #sr-ctl-toolbar is a two-column, two-row grid with exactly three
        // declared placements (Task 49), and a fourth direct child auto-places
        // onto an implicit THIRD row spanning the full width -- which is both
        // visibly wrong and a direct breach of SCENARIO-26's "the toolbar is two
        // declared rows" invariant. The wrapper takes row 1 / column 2 and lays
        // its own contents out, so the grid keeps its three placements.
        //
        // Add control is appended LAST so it stays flush with the toolbar's
        // right edge -- SCENARIO-26 asserts that too, and it is the reason the
        // secondary action goes to its left rather than outboard of it.
        var $actions = $('<div class="sr-ctl-toolbar-actions">');

        // Task 17: the Statement of Applicability -- the document every
        // applicability decision on this page exists to produce.
        //
        // FRAMEWORK-SCOPED ONLY, and absent rather than disabled when it isn't.
        // An SoA is a per-framework document by definition: applicability is
        // stored per (framework, control_id), the same control excluded from ISO
        // 27001 is not thereby excluded from PCI DSS, and there is no
        // "applicable in N of M frameworks" roll-up anywhere in the product --
        // which is exactly why the Applicability column and its facet are absent
        // under "All controls" too (Task 14). A disabled button would imply
        // the document exists here and is merely unavailable.
        //
        // Built here rather than parked in governance/index.php behind a
        // d-none toggle: renderToolbar() rebuilds this row on every
        // sr:controls-loaded, so a static button would need its own subscription
        // to that event to re-apply the same rule this `if` already states once,
        // next to the scope check it belongs to.
        //
        // MIDDLE WEIGHT (Task 65), styled on #sr-soa-btn in
        // scss/modules/_governance-frameworks.scss: outline at rest, red on
        // hover -- design-system.md §5's middle of three, and the only honest
        // reading of "actionable, not primary" while §6 keeps App Red spent once
        // on + Add control. It used to be `btn-outline-secondary btn-sm`: the
        // size class made it 28.4px/12.25px next to a 35px/14px neighbour and
        // 3.3px lower on the row, and btn-outline-secondary is raw Bootstrap
        // grey, absent from the palette, which is why it read as unstyled rather
        // than merely mis-sized. Bare `.btn` here so the geometry is the same one
        // + Add control gets; the ID rule supplies the colour.
        //
        // SHORT LABEL, for the same row it shares with the primary action. At
        // 230.6px the full sentence made the SECONDARY button more than twice
        // the width of the primary (102.5px) and the largest thing in the row,
        // so the eye landed on it first -- a hierarchy inverted by width no
        // amount of colour discipline fixes. The page context already names the
        // subject: a framework is scoped, the Applicability column is on screen,
        // and this button only exists in that state. `title`/`aria-label` keep
        // the full name, so nothing is lost to a hover or a screen reader.
        //
        // ICON PLUS A LABEL IN ITS OWN SPAN, so the stylesheet can drop the
        // label and leave the icon when the toolbar has no room for both. That
        // is not decoration: MEASURED at 1024px with the sidebar expanded, the
        // toolbar is 486px wide, and the 211px label forces the auto-width
        // action column to 321px -- leaving column 1 at 121px, which wraps the
        // framework name onto two lines and drops the count below it, breaking
        // the one-baseline rule SCENARIO-26 asserts. Column 1 is the minmax(0,
        // 1fr) track and column 2 is `auto`, so ALL of the pressure lands on
        // the title; no label short enough to be safe for every framework name
        // exists. The title stays intact and the label goes instead.
        //
        // `title` and `aria-label` carry the name in both states, so the
        // icon-only form is still announced and still hoverable.
        // scopedFramework(), not `state.framework !== null`: a Statement of
        // Applicability is a document ABOUT a framework -- it names the
        // framework, prints its scope statement, and lists the framework's
        // controls with their applicability decisions. "Unassigned controls"
        // has none of those things, so there is no document to generate, and an
        // absent button says that better than a disabled one (same rule the
        // rail applies to its own per-row SoA action).
        if (scopedFramework() !== null) {
            $('<button type="button" class="btn" id="sr-soa-btn">')
                .attr({
                    'data-framework': state.framework,
                    title: _lang['GenerateStatementOfApplicability'],
                    'aria-label': _lang['GenerateStatementOfApplicability']
                })
                .append($('<i class="fa fa-file-shield" aria-hidden="true">'))
                .append($('<span class="sr-soa-btn-label">').text(_lang['GenerateSoa']))
                .appendTo($actions);
        }

        // The page's single $sr-important fill -- and absent, not disabled,
        // without `add_new_controls` (Task 58; POST /governance/controls ->
        // createControlCrud() enforces the same bit). Losing it costs the row-1
        // action cluster its right-hand anchor, which is fine: the wrapper is
        // `auto`-width in a two-column grid, so with only the SoA button left
        // that button becomes the flush-right element, and with neither the
        // wrapper collapses to nothing and the title takes the full width.
        // Both were swept at 1024/1280/1440 and keep SCENARIO-26's two
        // declared rows.
        if (can('add_new_controls')) {
            $('<button type="button" class="btn btn-submit" id="sr-ctl-add">')
                .text(_lang['AddControl'])
                .appendTo($actions);
        }

        $actions.appendTo($toolbar);

        $tools.appendTo($toolbar);

        if (hadFocus) {
            var $newSearch = $toolbar.find('.sr-table-search');
            $newSearch.trigger('focus');
            if (caret !== null && $newSearch[0].setSelectionRange) {
                $newSearch[0].setSelectionRange(caret, caret);
            }
        }

        // Re-inserts Family/Owner into #sr-ctl-filters's freshly-rebuilt
        // toolbar (at wide widths) or confirms they're still correctly
        // parked in the filter sheet (below the compact breakpoint) --
        // never rebuilds them, so their current value/handlers survive this
        // renderToolbar() call the same way a plain resize does.
        syncInlineFacetPlacement();
    }

    function selectFramework(id) {
        state.framework = id;
        // Before writeUrl(), so a scope change that retires the applicability
        // facet also takes its tokens out of the URL in the same step -- rather
        // than leaving ?applicability= behind on a view that no longer offers,
        // applies, or can clear it.
        syncApplicabilityFacet();
        writeUrl();
        highlightRail();
        return reloadFirstPage();
    }

    // The paging reset rule, in ONE place (Task 46): any change that alters
    // WHICH rows match -- the rail's framework selection, a filter facet, the
    // search text, the sort, the page size, "Clear filters" -- returns to page
    // one before reloading. Anything else (a page click, a Retry, a reload
    // after a CRUD action) keeps the current offset.
    //
    // This exists as a helper rather than as a `state.start = 0` repeated at
    // each call site because the sort handler was the one path that had been
    // written without it: sorting 1,547 controls while on page 40 kept the
    // offset and silently teleported the user into the middle of a completely
    // different ordering. One helper means a future sixth path can only be
    // right or obviously missing, never subtly half-right.
    function reloadFirstPage() {
        state.start = 0;
        return reloadTable();
    }

    // The one place a controls/table query is built. Factored out so a virtual
    // chunk (Task 47) and a page differ ONLY in their offset -- there is no
    // second idea anywhere of what the current filter, scope or sort is, which
    // is what lets the two render strategies share a result set.
    function controlsQuery(start, length) {
        var q = { start: start, length: length, sort: state.sort, dir: state.dir };
        if (state.framework !== null) { q.framework = state.framework; }
        $.extend(q, window.SRFrameworksFilters ? window.SRFrameworksFilters() : {});
        return q;
    }

    function reloadTable() {
        // "All" (Task 47) asks the server for the FIRST CHUNK, not for
        // everything: the response's `filtered` is what the virtual scrollbar
        // spans, and the remaining chunks are fetched as they are scrolled into
        // view. So the request that opens a 1,552-row continuous list is the
        // same 200-row request the paged table already makes.
        virt.on = lengthIsAll(state.length);
        var q = virt.on
            ? controlsQuery(0, VIRT_CHUNK)
            : controlsQuery(state.start, state.length);
        var seq = nextSeq('controls');
        return $.getJSON(BASE_URL + '/api/v2/governance/controls/table', q)
            .then(function (res) {
                if (isStaleSeq('controls', seq)) { return; }
                state.rows = res.data.rows;
                state.total = res.data.total;
                state.filtered = res.data.filtered;
                // Adopt the offset and page size the SERVER actually sliced
                // at, never the ones we asked for. The client can't know how
                // many rows the current filter matches until this response
                // arrives, so a bookmarked ?start= or a filter narrowed while
                // sitting on page 40 can legitimately ask for a page that no
                // longer exists; controls_table_clamp_start()
                // (api/v2/includes/governance_controls.php) snaps that onto
                // the last real page and reports back what it used. Reading
                // it here is what keeps the rendered rows, the pager's
                // current-page highlight and the "Showing x-y of z" summary
                // derived from ONE offset instead of two that can disagree.
                //
                // NOT adopted in virtual mode, either of them (Task 47). The
                // request the virtual list makes is a 200-row CHUNK at offset 0,
                // so the server correctly reports start=0 length=200 -- and
                // writing that back would silently demote "All" to a 200-row
                // page on the very first load. There is one page in virtual
                // mode, its offset is 0 by construction, and the clamp the two
                // lines exist for has nothing to clamp.
                if (!virt.on) {
                    if (typeof res.data.start === 'number') { state.start = res.data.start; }
                    if (typeof res.data.length === 'number') { state.length = res.data.length; }
                }
                // Adopted for the same reason as the two above: the server
                // decides whether applicability is answerable for this request,
                // and the rows it just sent either carry an applicability
                // record or they don't. Re-deriving it here from
                // state.framework would be a second copy of that rule, free to
                // disagree with the payload it is describing.
                state.applicabilityScoped = res.data.applicability_scoped === true;
                if (virt.on) {
                    state.start = 0;
                    // The scrollbar spans the whole filtered set; chunk 0 is
                    // already in hand, so seed the cache with it rather than
                    // asking for the same 200 rows a second time.
                    virtReset(state.filtered);
                    virt.chunks[0] = state.rows;
                }
                // Candidates for the min-content probe come from every row this
                // page has seen, and the first batch arrives here. Fed BEFORE
                // the render, because renderTable() mounts the probe and
                // evaluateResponsiveTiers() reads it in the same pass.
                probeObserve(state.rows);
                $(document).trigger('sr:controls-loaded', [state]);
            })
            // A failed request leaves state.rows/total/filtered exactly as
            // they were (nothing is discarded) -- only the display switches
            // to the "couldn't load" tile, which renderTable() never runs
            // for on this path, so it can't get overwritten with an
            // incorrect "no data"/"no results" read of stale state.
            // ...and the selection goes with it (Task 62). The success path has
            // dropped the selection on every reload since Task 7 -- ids the
            // rebuilt tbody cannot show as checked are exactly how a bulk action
            // surprises someone -- but the FAILURE path kept it, and there the
            // same rule matters more, not less: showControlsEmptyState('error')
            // hides .sr-table-scroll outright, so the bulk bar was left claiming
            // "3 selected" over a table with no visible rows at all, with Delete
            // and Set applicability both still live against ids the user could
            // no longer see, count or verify. Measured before this line: bar
            // visible, "3 selected", zero visible checkboxes, error tile shown.
            //
            // CLEARED rather than re-synced, and the choice is forced here even
            // though the success path could defensibly have gone either way:
            // after a failed reload there is no rendered page for a retained
            // selection to be reconciled against, so "re-run syncSelection() so
            // the checkboxes match" has no checkboxes to match. Clearing is also
            // what keeps ONE answer across both branches of this one function --
            // a reload either replaces the population or fails to, and neither
            // outcome leaves the previous selection standing.
            //
            // A write-triggered reload reaches this: submitApplicabilitySet()
            // and the CRUD handlers all call reloadTable() after a successful
            // POST, so a session drop or a network blip in that window is
            // precisely how the stale bar shipped to a user.
            .fail(function () {
                if (isStaleSeq('controls', seq)) { return; }
                showControlsEmptyState('error');
                clearSelection();
            });
    }

    // ===== Control table rendering (columns, pills, sort, paging) =====
    //
    // Column order mirrors the <td> order renderRow() builds below. Only the
    // columns with a `sort` field are clickable/sortable -- the server's
    // allowlist (governance/controls/table) is exactly these six fields, so
    // there is nothing here for the checkbox or actions columns to sort by.
    var COLUMNS = [
        { cls: 'sr-col-check' },
        { key: 'ControlNumber', sort: 'control_number', cls: 'sr-col-num' },
        { key: 'ControlName', sort: 'short_name', cls: 'sr-col-name' },
        { key: 'ControlFamily', sort: 'family_name', cls: 'sr-col-family' },
        { key: 'Owner', sort: 'control_owner_name', cls: 'sr-col-owner' },
        // Applicability (Task 14) is spliced in HERE, between Owner and
        // Maturity, only when the view is scoped to one framework -- see
        // controlColumns() below. Its position follows spec §4.3's column
        // order, and it deliberately sits BEFORE the two columns the compact
        // tier drops, so folding the table narrower moves it left rather than
        // stranding it beyond a column that has already gone.
        { key: 'Maturity', sort: 'control_maturity', cls: 'sr-col-mat' },
        { key: 'Status', sort: 'control_status', cls: 'sr-col-stat' },
        { cls: 'sr-col-acts' }
    ];

    // The columns for the CURRENT view.
    //
    // Applicability renders only when the table is scoped to exactly one
    // framework: the same control excluded from ISO 27001 is not thereby
    // excluded from PCI DSS, so under "All controls" there is no single
    // honest answer to put in the cell. The column is ABSENT from the DOM
    // rather than hidden -- a hidden cell would still occupy the row's column
    // count, and an empty cell reads as "unknown" rather than "not asked here".
    //
    // Keyed off state.applicabilityScoped -- the SERVER's own answer, adopted
    // from the response -- so the column can only appear over rows that
    // actually carry an applicability record. NOT sortable: the sort allowlist
    // (CONTROLS_TABLE_SORTS, api/v2/includes/governance_controls.php) is six
    // raw control columns, and applicability is resolved after that sort from a
    // different table entirely; offering a header that silently sorted by
    // control_number would be worse than not offering one.
    function controlColumns() {
        // The selection column comes and goes with the user's bulk permissions
        // exactly as the Applicability column comes and goes with the rail's
        // scope (Task 58). Filtered FIRST, so the splice below still locates
        // Maturity by its class rather than by an index that would have
        // shifted. renderRow() applies the identical canSelectRows() test, and
        // renderDrawer()'s colspan reads this function -- so the header, the
        // rows and the drawer all move together.
        var base = canSelectRows() ? COLUMNS : COLUMNS.filter(function (col) {
            return col.cls !== 'sr-col-check';
        });
        if (!state.applicabilityScoped) { return base; }
        var cols = base.slice();
        // Positioned relative to the Maturity column rather than at a literal
        // index -- a number here would silently point at the wrong slot the
        // first time a column is added or reordered above it, and the header
        // and the row would then disagree about which cell is which.
        var at = cols.length;
        cols.forEach(function (col, i) { if (col.cls === 'sr-col-mat') { at = i; } });
        cols.splice(at, 0, { key: 'Applicability', cls: 'sr-col-appl' });
        return cols;
    }

    function renderThead() {
        var $tr = $('<tr>');
        controlColumns().forEach(function (col) {
            var $th = $('<th scope="col">').addClass(col.cls);
            // "Select all" for the current PAGE only. The thead IS rebuilt on
            // every load now (the Applicability column comes and goes with the
            // rail's scope), which is harmless: every handler that reaches into
            // it -- the sort click, this checkbox's change -- is delegated from
            // `document`, and the load that rebuilds it also clears the
            // selection and re-runs syncHeaderCheckbox().
            // .sr-check-col is the SHIPPED checkbox-column styling
            // (_tables.scss, already used by Define Tests/Self-Assessment) --
            // added alongside .sr-col-check, which is this page's OWN
            // grid-position hook for the responsive ladder's queue tier
            // (Task 5) and stays untouched.
            if (col.cls === 'sr-col-check') {
                $th.addClass('sr-check-col')
                    .append($('<input type="checkbox" class="form-check-input" id="sr-ctl-check-all">')
                        .attr('aria-label', _lang['SelectAll']));
            }
            if (col.sort) {
                // Font Awesome, not a text glyph -- matches Define Tests
                // (compliance-define-tests.js): the icon IS .sr-sort-icon
                // (an <i>, not a wrapper), toggled between fa-sort/
                // fa-arrow-up-short-wide/fa-arrow-down-wide-short in
                // updateSortIndicators() below.
                $th.addClass('sr-sortable').attr({ 'data-sort': col.sort, tabindex: 0 })
                    .append(document.createTextNode(_lang[col.key]))
                    .append($('<i class="fa sr-sort-icon" aria-hidden="true">'));
            } else if (col.key) {
                // A labelled but unsortable column (Applicability). Without
                // this branch the header cell would render EMPTY -- every
                // labelled column happened to be sortable until Task 14, so the
                // label only ever went in inside the branch above.
                $th.append(document.createTextNode(_lang[col.key]));
            }
            $tr.append($th);
        });
        $('#sr-ctl-thead').empty().append($tr);
        updateSortIndicators();
    }

    // Reflects state.sort/state.dir onto the header markup -- called once at
    // render time and again after every sort click, never a full thead rebuild
    // (that would also work, but this keeps the click-bound elements stable).
    function updateSortIndicators() {
        $('#sr-ctl-thead th.sr-sortable').each(function () {
            var $th = $(this);
            var active = $th.attr('data-sort') === state.sort;
            var $icon = $th.find('.sr-sort-icon');
            $th.toggleClass('is-sorted', active);
            if (active) {
                $th.attr('aria-sort', state.dir === 'desc' ? 'descending' : 'ascending');
                $icon.removeClass('fa-sort fa-arrow-up-short-wide fa-arrow-down-wide-short')
                    .addClass(state.dir === 'desc' ? 'fa-arrow-down-wide-short' : 'fa-arrow-up-short-wide');
            } else {
                $th.removeAttr('aria-sort');
                $icon.removeClass('fa-arrow-up-short-wide fa-arrow-down-wide-short')
                    .addClass('fa-sort');
            }
        });
    }

    function applySort($th) {
        var field = $th.attr('data-sort');
        if (!field) { return; }
        if (state.sort === field) {
            state.dir = state.dir === 'asc' ? 'desc' : 'asc';
        } else {
            state.sort = field;
            state.dir = 'asc';
        }
        updateSortIndicators();
        // Re-sorting reorders the WHOLE result set, so the rows at the
        // current offset are unrelated to the ones that were there a moment
        // ago -- back to page one (Task 46).
        reloadFirstPage();
    }

    // Soft state pills (design-system 7): tint + label, never a solid fill --
    // solid red belongs to the single primary action only. Pass/Fail carry a
    // check/x GLYPH instead of the generic dot (same fa-check/fa-xmark names
    // as RESULT_ICONS in compliance-define-tests.js's resultPill(), so the
    // two surfaces can't drift) -- .sr-state-pill-glyph drops the dot via its
    // own ::before override, and the dot and a glyph are never shown together.
    //
    // Not Tested KEEPS the generic grey dot (no -glyph) -- Josh: the grey
    // chip + grey dot reads as "no result yet" more clearly than a bare chip
    // does. It's also the honest encoding: the dot is the neutral mark for a
    // state with no outcome, whereas dropping every mark makes the cell look
    // like a rendering gap rather than a deliberate third state. This is the
    // one place we diverge from resultPill()'s never-run treatment.
    //
    // .sr-state-pill-result gives all three pills a shared min-width so the
    // column reads as one lane instead of ragged -- that applies to Not
    // Tested too, which is why the width survives the -glyph split above.
    var STATUS = {
        1: { cls: 'sr-state-success', key: 'Pass', icon: 'fa-check' },
        0: { cls: 'sr-state-danger',  key: 'Fail', icon: 'fa-xmark' },
        2: { cls: 'sr-state-neutral', key: 'NotTested', icon: null }
    };

    function renderStatusPill(code) {
        var s = STATUS[code] || STATUS[2];
        // -glyph only where an icon actually replaces the dot; without an
        // icon it would strip the dot and leave nothing.
        var $pill = $('<span class="sr-state-pill sr-state-pill-result">')
            .addClass(s.cls)
            .addClass(s.icon ? 'sr-state-pill-glyph' : '');
        if (s.icon) {
            $pill.append($('<i class="fa ' + s.icon + '" aria-hidden="true">'));
        }
        $('<span>').text(_lang[s.key]).appendTo($pill);
        return $pill;
    }

    // Shade modifier for a maturity level. MIRRORS maturity_pill_class()
    // (includes/reporting.php) -- both sides only build the class NAME; the
    // ramp behind it is defined once, in SCSS ($sr-maturity-ramp,
    // scss/modules/_home.scss), so there is no palette to keep in sync here.
    // The scale is a fixed 0-5 (admin/custom_names.php renames control_maturity
    // rows but cannot add one), so --na is defensive.
    function maturityPillClass(value) {
        var n = parseInt(value, 10);
        return (n >= 0 && n <= 5) ? 'sr-maturity-pill--' + n : 'sr-maturity-pill--na';
    }

    // One maturity level pill, labelled with the level's NAME. The name is
    // admin-authored (Configure -> custom names), so it goes in with .text(),
    // never string-concatenated into markup. It comes from the endpoint rather
    // than a number->name map here, which would go stale the moment an admin
    // renames a level.
    function maturityPill(value, name) {
        return $('<span class="sr-maturity-pill">')
            .addClass(maturityPillClass(value))
            .text(String(name === undefined || name === null || name === '' ? (value || 0) : name));
    }

    // The exact current -> desired level pair, as the SAME two shaded pills the
    // governance dashboard's Below/At/Above Maturity widgets render
    // (governance_maturity_gap_table(), includes/reporting.php), with the cyan
    // ramp encoding the level (light = low, dark = high).
    //
    // Task 34 moved this out of the Maturity COLUMN and into the row drawer.
    // Two 104px-min pills plus an arrow cost ~230px of table width to say
    // something the column can say in one chip, and the column's real question
    // -- "does this control need attention?" -- is answered by the bucket, not
    // by the level names. The drawer is the read view and has room, so the
    // precise levels stay exactly one click away rather than being dropped.
    //
    // A shortfall of 2 or more levels is a distinct signal from "below" (which
    // is any shortfall at all), so it survives the move: .sr-mat--gap tints the
    // arrow, paired with visually-hidden text so the state is never conveyed by
    // colour alone. The arrow itself stays aria-hidden -- it is decoration, and
    // the two pill labels already read in order.
    function maturityLevelPair(cur, des, curName, desName) {
        var curVal = cur || 0;
        var $n = $('<span class="sr-mat">').append(
            maturityPill(curVal, curName),
            $('<span class="sr-maturity-arrow" aria-hidden="true">').text('→'),
            maturityPill(des, desName));
        if (des - curVal >= 2) {
            $n.addClass('sr-mat--gap')
              .append($('<span class="visually-hidden">').text(_lang['BelowMaturity']));
        }
        return $n;
    }

    // Which maturity bucket a control falls in. MIRRORS
    // control_maturity_bucket() (includes/governance.php) -- the function the
    // server filters AND counts by -- so the chip the user reads, the option
    // they filter on, and the number on that option's count chip are all the
    // same statement. Returns '' when there is no desired maturity to compare
    // against.
    function maturityBucket(cur, des) {
        var desired = parseInt(des, 10) || 0;
        if (desired < 1) { return ''; }
        var current = parseInt(cur, 10) || 0;
        if (current < desired) { return 'below'; }
        if (current > desired) { return 'above'; }
        return 'at';
    }

    // The Maturity COLUMN: one Below/At/Above chip per control.
    //
    // Josh: the level pair "consumes a lot of space, and a simpler Below/At/
    // Above reading is both easier to scan and easier to filter". It is also
    // the vocabulary the rest of the product already speaks -- the dashboard's
    // three maturity-gap widgets and this page's own filter facet.
    //
    // The shipped .sr-state-pill family (_tables.scss), same component
    // renderStatusPill() above uses -- no new chip. No lane modifier
    // (.sr-state-pill-result) either: the three labels share the word
    // "Maturity" and differ by ≤23px, so there is no ragged column for a
    // shared min-width to fix, and the point of this change is a NARROWER
    // column.
    //
    // The no-target case is preserved exactly as it was: a control with no
    // desired maturity renders an em dash, not a chip. "At" and "above" would
    // both be unfounded claims with nothing to compare against.
    function renderMaturity(cur, des) {
        var bucket = maturityBucket(cur, des);
        if (!bucket) { return $('<span class="sr-mat-none">').text('—'); }

        var opt = MATURITY_OPTIONS.filter(function (o) { return o.value === bucket; })[0];
        return $('<span class="sr-state-pill">')
            .addClass(opt.cls)
            .attr('data-sr-maturity', bucket)
            .text(_lang[opt.key]);
    }

    // The Applicability COLUMN and the drawer's decision line: one state chip.
    //
    // The shipped .sr-state-pill family (_tables.scss), same component
    // renderStatusPill() and renderMaturity() use -- no new chip and no new
    // SCSS. No lane modifier (.sr-state-pill-result): its shared 105px min-width
    // exists to stop a ragged column of short result labels, and this column is
    // going into a table whose width is already tight. The generic status dot is
    // kept (no -glyph): there is no check/x that means "in scope", and a glyph
    // that had to be invented would be a third mark competing with the Status
    // pill's own.
    //
    // data-sr-applicability carries the machine token beside the localized
    // label, exactly as renderMaturity()'s chip carries data-sr-maturity, so a
    // mis-rendered state is detectable independently of the translation.
    //
    // An unrecognized state falls back to the default rather than rendering a
    // classless chip: the server resolves every row through
    // resolve_applicability(), so the only way to get here with something else
    // is schema drift, and the default is the honest reading of "no deviation
    // this client understands".
    function renderApplicability(state_token) {
        var opt = APPLICABILITY_OPTIONS.filter(function (o) { return o.value === state_token; })[0]
            || APPLICABILITY_OPTIONS[0];
        return $('<span class="sr-state-pill">')
            .addClass(opt.cls)
            .attr('data-sr-applicability', opt.value)
            .text(_lang[opt.key]);
    }

    // Mirrors format_every_n_units()'s {$key} substitution convention
    // (includes/compliance_grid.php) / compliance-define-tests.js's own
    // formatTemplate() so "Showing X-Y of Z" stays one shared token style.
    function formatTemplate(template, vars) {
        return String(template || '').replace(/\{\$(\w+)\}/g, function (match, key) {
            return Object.prototype.hasOwnProperty.call(vars, key) ? vars[key] : match;
        });
    }

    function renderRow(c) {
        var $tr = $('<tr class="sr-ctl-row">').attr('data-sr-ctl', c.id);
        // Selection checkbox. .sr-check-col is the shipped checkbox-column
        // styling (_tables.scss); .sr-col-check is this page's own
        // grid-position hook for the queue-tier responsive ladder (Task 5).
        // Rendered only when a bulk action exists to select FOR -- see
        // canSelectRows(); controlColumns() drops the matching <th> on the
        // same condition, so the two can never disagree about the row's cell
        // count.
        if (canSelectRows()) {
            $tr.append($('<td class="sr-col-check sr-check-col">').append(
                $('<input type="checkbox" class="form-check-input sr-ctl-check">').attr('value', c.id)));
        }
        // Expand caret -- the shipped .sr-group-caret (_tables.scss), same
        // chevron Define Tests uses for its own row expanders, rather than a
        // bespoke glyph (Task 23). Stays a real <button> (not a <span> on a
        // clickable <tr>, Define Tests' own shape) so it keeps its own
        // keyboard operability and aria-expanded/aria-controls -- the icon is
        // decorative (aria-hidden), so the button needs its own accessible
        // name since it has no visible text.
        $tr.append($('<td class="sr-col-num">').append(
            $('<button type="button" class="sr-group-caret" aria-expanded="false">')
                .attr('aria-label', _lang['Details'])
                .append($('<i class="fa fa-chevron-right" aria-hidden="true">')),
            document.createTextNode(' ' + (c.control_number || ''))));
        $tr.append($('<td class="sr-col-name">')
            .append($('<span class="sr-ctl-name">').text(c.short_name || ''))
            .append($('<span class="sr-ctl-sub">').text(
                (c.family_name || _lang['Unassigned']) + (c.control_owner_name ? ' · ' + c.control_owner_name : ''))));
        $tr.append($('<td class="sr-col-family">').text(c.family_name || _lang['Unassigned']));
        $tr.append($('<td class="sr-col-owner">').text(c.control_owner_name || _lang['NoOwner']));
        // Only when the server said applicability is answerable for this view
        // -- the same flag controlColumns() builds the header from, so the cell
        // count and the header count are one decision, not two.
        if (state.applicabilityScoped) {
            $tr.append($('<td class="sr-col-appl">').append(renderApplicability(c.applicability)));
        }
        $tr.append($('<td class="sr-col-mat">').append(
            renderMaturity(c.control_maturity, c.desired_maturity)));
        $tr.append($('<td class="sr-col-stat">').append(renderStatusPill(c.control_status)));
        // Row actions (Task 8, Clone restored by Task 24): Edit / Clone /
        // Delete, revealed on hover -- the SHIPPED
        // .sr-row-actions/.sr-row-action(-danger) component (_tables.scss,
        // scoped to .sr-table-card -- #sr-ctl-table already carries that
        // class), same pattern Define Tests uses (compliance-define-tests.js).
        // Replaces Task 3's placeholder .sr-row-more "⋯" button -- that was
        // scaffolding for a menu nobody else in this plan claimed; three
        // always-fit actions still don't need one.
        //
        // Clone sits between Edit and Delete, both visually (accent
        // discipline, design-system.md §6: App Red is spent once, on
        // "+ Add control" -- Clone is a non-destructive action like Edit, so
        // it gets the same neutral .sr-row-action, never
        // .sr-row-action-danger) and semantically (primary action, secondary
        // action, destructive last). Restores the `.control-block--clone`
        // capability the pre-redesign js/simplerisk/pages/governance.js /
        // includes/api.php's getFrameworkControlsDatatable() shipped, dropped
        // silently by the Task 2/3 row-actions redesign (task-24-report.md).
        // Click handler: openControlForClone() below -- pre-fills #control--add
        // (same modal "+ Add control" opens, per design-system.md §8: a modal
        // never opens another modal) for the user to review and edit before
        // saving, matching the legacy pre-redesign behavior exactly (a
        // one-click server-side duplicate was tried first, then reverted --
        // Josh: "the user doesn't know what was cloned" -- see task-24-report.md).
        //
        // Every action here is independently permission-gated (Task 58), and
        // the three permissions really are distinct: Edit is `modify_controls`
        // (PATCH), Clone is `add_new_controls` (it POSTs a NEW control, which
        // is why the pre-redesign getFrameworkControlsDatatable() gated Clone
        // on exactly that bit and nothing else), Delete is `delete_controls`.
        // Set applicability is `modify_frameworks` -- scoping a control out of
        // a framework is a framework-scoping decision, which is the permission
        // api_v2_governance_applicability_set() enforces. Unlike the rail, this
        // cluster CAN come out empty; see the tail of this function.
        var $actions = $('<span class="sr-row-actions">');
        if (can('modify_controls')) {
            $('<button type="button" class="sr-row-action" data-sr-ctl-edit>')
                .attr({ title: _lang['Edit'], 'aria-label': _lang['Edit'] })
                .append($('<i class="fa fa-pen-to-square" aria-hidden="true">'))
                .appendTo($actions);
        }
        if (can('add_new_controls')) {
            $('<button type="button" class="sr-row-action" data-sr-ctl-clone>')
                .attr({ title: _lang['Clone'], 'aria-label': _lang['Clone'] })
                .append($('<i class="fa fa-clone" aria-hidden="true">'))
                .appendTo($actions);
        }
        // Set applicability (Task 60) -- scoping ONE control out of a framework
        // had no affordance anywhere a user would look for it. The only entry
        // point was the selection bulk bar (Task 15), which meant ticking a
        // checkbox to make a decision about the row already under the cursor,
        // and the row drawer only ever DISPLAYED the record it could not change.
        //
        // Rendered from state.applicabilityScoped -- the SERVER's answer, the
        // same flag the row's own Applicability cell and the table header are
        // built from, so the action appears exactly where the column does and
        // the two cannot come apart. ABSENT under "All controls", never
        // disabled: applicability is per-framework (a control excluded from
        // ISO 27001 is not thereby excluded from PCI DSS), so with no framework
        // scoped there is nothing to record a decision against. A disabled
        // button would imply the decision exists here and is merely unavailable
        // -- the same reasoning that keeps the column, the facet and the bulk
        // button absent there (Tasks 14/15).
        //
        // Neutral .sr-row-action, never .sr-row-action-danger (design-system
        // §6): $sr-important is spent on "+ Add control", and recording an
        // applicability decision is non-destructive and reversible (setting a
        // control back to Applicable deletes the row again), exactly like Edit.
        // Delete stays last and stays the only danger-styled action. The glyph
        // is the one this page already uses for applicability -- the scales in
        // #applicability--set's own modal header (governance/index.php).
        if (state.applicabilityScoped && can('modify_frameworks')) {
            $('<button type="button" class="sr-row-action" data-sr-ctl-applicability>')
                .attr({ title: _lang['SetApplicability'], 'aria-label': _lang['SetApplicability'] })
                .append($('<i class="fa fa-scale-balanced" aria-hidden="true">'))
                .appendTo($actions);
        }
        if (can('delete_controls')) {
            $('<button type="button" class="sr-row-action sr-row-action-danger" data-sr-ctl-delete>')
                .attr({ title: _lang['Delete'], 'aria-label': _lang['Delete'] })
                .append($('<i class="fa fa-trash" aria-hidden="true">'))
                .appendTo($actions);
        }
        // The wrap carries the overflow toggle the compact tier pops this
        // cluster from (rowActionsWrap() above). Without it, everything below
        // was measured unreachable at 1400px and narrower.
        //
        // With NO actions -- a read-only governance user, who now holds none
        // of the four bits above -- the cell goes in EMPTY: no cluster and, in
        // particular, no ⋯ toggle. Task 48's invariant ("at every width either
        // every action is fully inside the scroll container, or the toggle is
        // visible and fully inside it") is satisfied vacuously in that state,
        // and the alternative -- a toggle that opens an empty menu -- would
        // breach the spirit of it outright. The <td> itself stays so the row's
        // cell count keeps matching controlColumns(), which always emits the
        // .sr-col-acts header (it is unlabelled, so an empty column is a blank
        // gutter, not a header with nothing under it).
        var $acts = $('<td class="sr-col-acts">');
        if ($actions.children().length) { $acts.append(rowActionsWrap($actions)); }
        $tr.append($acts);
        return $tr;
    }

    // Empty states (Task 9, design-system.md §10) for the control table --
    // decided from the response, never guessed: an empty result with active
    // filters is "no results" (Clear filters, never Add), an empty result
    // with none is "no data yet" (Add control). "Couldn't load" (reloadTable()'s
    // own fail path, above) is a THIRD state this function never selects --
    // it's only ever entered directly from the .fail() handler, so a request
    // failure can't be mistaken for either kind of legitimate empty result.
    // which is one of null (show the table), 'nodata', 'noresults', 'error'.
    function showControlsEmptyState(which) {
        $('#sr-ctl-empty-nodata, #sr-ctl-empty-noresults, #sr-ctl-empty-error').addClass('d-none');
        var showTable = !which;
        $('#sr-ctl-table .sr-table-scroll').toggleClass('d-none', !showTable);
        $('#sr-ctl-foot').toggleClass('d-none', !showTable);
        if (which) { $('#sr-ctl-empty-' + which).removeClass('d-none'); }
    }

    // ===== The min-content probe (Task 47) ==================================
    //
    // Exists for ONE reason: to make the responsive ladder's measurement
    // independent of which rows happen to be rendered.
    //
    // evaluateResponsiveTiers() decides the tier by comparing the table's
    // scrollWidth -- the table pinned at its MIN-CONTENT width -- against the
    // scroller's clientWidth. A table's min-content is the per-column maximum
    // over the rows that are IN THE DOM, so it is a function of the rendered
    // set. Virtual scrolling renders a window rather than the whole result set,
    // which would make that input change as the user scrolls and the widest
    // rendered cell changes -- and the ladder would oscillate.
    //
    // MEASURED, not predicted, before this existed (1,534 framework-scoped
    // controls, sliding 40-row windows, the ladder's own rung-1 and rung-2
    // reads replicated per window):
    //
    //   rung 1 (both tier classes stripped): 984..1026px -- a 42px spread
    //   rung 2 (compact class alone):         608..651px -- a 43px spread
    //   at a 960px viewport the DECISION itself disagreed:
    //     23 of 38 windows read "queue", 15 read "compact"
    //
    // So this is not a theoretical risk; the tier really does flip on which
    // rows are in view. It is also, note, a LATENT DEFECT IN THE SHIPPED PAGED
    // TABLE -- today's basis is the current PAGE's widest row, so clicking Next
    // can already move the tier. The probe fixes both at once, which is why it
    // is mounted in both modes and not only under virtualization.
    //
    // How it works: a handful of hidden rows are kept permanently in the tbody,
    // built from the LONGEST values seen in each column. Because a table's
    // min-content is the per-column max over all rows, and the probe rows hold
    // each column's widest candidate, the probe DOMINATES every real row --
    // so scrollWidth is the probe's width and nothing else, and scrolling
    // cannot change it.
    //
    // Two properties make it safe rather than merely stable:
    //
    //   CONSERVATIVE. The probe is at least as wide as every row that can be
    //   rendered, so a tier the probe says is safe is safe for all of them. The
    //   failure mode virtualization would otherwise introduce -- relax the tier
    //   against narrow rendered rows, then scroll a wide one into an overflowing
    //   table -- cannot occur.
    //
    //   MONOTONE within a result set. Candidates only ever widen as more rows
    //   are seen (virtual mode discovers rows chunk by chunk), and are reset
    //   only when the result set itself changes (scope / filters / column set).
    //   A monotone basis can fold one rung further as wider content is
    //   discovered; it can never unfold, so it cannot oscillate.
    //
    // Built by renderRow() from a synthetic control rather than by hand-rolled
    // markup, so the probe's structure -- the caret, the pill shapes, the action
    // buttons, the .sr-ctl-sub second line -- is the row's structure by
    // construction and cannot drift from it.
    //
    // It carries `sr-ctl-probe` and NOT `sr-ctl-row`: `.sr-ctl-row` is the
    // page's (and the E2E suite's) name for "a real, addressable control row",
    // and the probe is neither. visibility:hidden keeps it out of find-in-page
    // and off the accessibility tree along with aria-hidden; line-height:0 and
    // zero padding collapse it to ~0px tall while leaving its WIDTH intact,
    // which is the only thing it is here for (_governance-frameworks.scss).
    // TWO candidates per field per rank, because a column's min-content is
    // decided by one of two different things and which one depends on whether
    // the cell wraps:
    //
    //   a cell that does NOT wrap  -> the widest WHOLE STRING
    //   a cell that DOES wrap      -> the widest single WORD
    //
    // Ranking one merged list by string length gets the second case wrong, and
    // measurably so. The first implementation did exactly that, and
    // ".sr-col-name" -- which wraps -- came out 37px short: the widest cell in
    // the 1,534-row set was "NET-03.2: External Telecommunications Services",
    // whose 18-character "Telecommunications" decides the column, while the
    // string itself is far from the six longest and was evicted from the ranking
    // every time. Two independently-ranked lists, one by total length and one by
    // longest word, cover both cases; the word list stores the WORD, so the
    // probe cell's min-content is that word exactly.
    var PROBE_ROWS = 6;

    // The four columns whose width is a function of user-authored text.
    var PROBE_FIELDS = ['control_number', 'short_name', 'family_name', 'control_owner_name'];

    var probe = { key: null, cand: null, seen: null, mounted: false, dirty: false };

    // The identity of the result set the candidates belong to. Framework scope
    // and the filters decide WHICH rows exist; applicabilityScoped decides which
    // COLUMNS exist. Sort order does not appear here on purpose: reordering the
    // same rows cannot change any column's widest value, so a sort must not
    // throw away candidates that are still correct.
    function probeKey() {
        return JSON.stringify([
            state.framework,
            state.applicabilityScoped === true,
            window.SRFrameworksFilters ? window.SRFrameworksFilters() : {}
        ]);
    }

    // Candidates are ranked by MEASURED PIXEL WIDTH, not by character count.
    //
    // Character count is the obvious proxy and it is wrong, measurably: the
    // widest .sr-col-name cell in this 1,534-row set is
    // "NET-06: Network Segmentation (macrosegmentation)", whose min-content is
    // decided by the 19-character "(macrosegmentation)" -- and a 19-character
    // string loses a char-count ranking to "Technologies-Related" (20) and ties
    // "Compliance-Specific" (19), both of which are NARROWER in pixels because
    // 'm' and 'o' are wide where 'l', 'i' and '-' are not. The probe came out
    // exactly 23px short as a result, all of it in that one column, and the
    // ladder still read a 24px spread across scroll positions.
    //
    // Measured through a canvas 2D context rather than by putting each candidate
    // in the DOM: 1,534 rows x 4 fields is ~6,000 measurements per result set,
    // which measureText() does in a few milliseconds and layout would not. The
    // font is read off the table itself, so it follows the user's zoom and font
    // size. It is an APPROXIMATION of the eventual cell width -- per-column font
    // weights differ, and this uses the table's base font throughout -- but only
    // the RANKING has to be right, and six candidates per list absorb a
    // near-tie. Falls back to character count where canvas is unavailable.
    var probeMeasure = null;
    var probeWidthCache = null;

    function probeFontSync() {
        probeMeasure = null;
        probeWidthCache = {};
        var table = document.querySelector('#sr-ctl-table table.sr-table');
        if (!table || !document.createElement('canvas').getContext) { return; }
        var ctx = document.createElement('canvas').getContext('2d');
        if (!ctx) { return; }
        var cs = window.getComputedStyle(table);
        ctx.font = cs.fontStyle + ' ' + cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
        probeMeasure = ctx;
    }

    // Memoized, because the inputs repeat heavily -- 1,552 controls share a few
    // dozen family names and owners, and every kept candidate is re-measured on
    // every comparison. Unmemoized this cost 162ms of time-to-first-row on a
    // 200-row page (407ms -> 569ms, measured), which is a regression on the
    // DEFAULT paged path in service of a mode it is not even in.  The cache is
    // dropped whenever the font is re-read.
    function probeWidth(s) {
        var key = String(s);
        if (!probeMeasure) { return key.length; }
        var hit = probeWidthCache[key];
        if (typeof hit !== 'number') {
            hit = probeMeasure.measureText(key).width;
            probeWidthCache[key] = hit;
        }
        return hit;
    }

    // Inserts `value` into a ranked, de-duplicated, capped list. Ties break on
    // character count so the list is deterministic when canvas is unavailable
    // and probeWidth() degenerates to the length itself.
    //
    // The cheap reject comes first: once the list is full, a value no wider than
    // its narrowest member cannot displace anything, and on a 1,552-row result
    // set almost every value is in that case. One memoized measurement against
    // one cached bound, instead of a push and a sort.
    function probeRank(list, value) {
        if (!value || list.indexOf(value) !== -1) { return; }
        if (list.length >= PROBE_ROWS && probeWidth(value) <= probeWidth(list[list.length - 1])) { return; }
        list.push(value);
        list.sort(function (a, b) {
            var d = probeWidth(b) - probeWidth(a);
            return d !== 0 ? d : b.length - a.length;
        });
        if (list.length > PROBE_ROWS) { list.length = PROBE_ROWS; }
        // The KEPT set moved -- the only thing that changes the DOM, and
        // therefore the only thing worth a rebuild.
        probe.dirty = true;
    }

    function longestWordOf(s) {
        var longest = '';
        var longestWidth = -1;
        String(s).split(/\s+/).forEach(function (w) {
            var width = probeWidth(w);
            if (width > longestWidth) { longestWidth = width; longest = w; }
        });
        return longest;
    }

    function probeOffer(slot, value) {
        var s = String(value === null || value === undefined ? '' : value);
        if (!s) { return; }
        probeRank(slot.full, s);
        probeRank(slot.word, longestWordOf(s));
    }

    // Folds a batch of rows into the candidate set, resetting first if the
    // result set has changed underneath us.
    //
    // The pill columns are OBSERVED here too rather than enumerated from their
    // option lists. Cycling all three status codes / three applicability states
    // / three maturity buckets covers whatever the data might hold, but it also
    // reserves width for labels the data may never use -- measured at 21px on
    // Applicability and 30px on Status in this framework, i.e. 51px of folding
    // bought for nothing. Reserving for the states that ARE present is both
    // exact and no more code.
    function probeObserve(rows) {
        var key = probeKey();
        if (key !== probe.key) {
            probe.key = key;
            probe.cand = null;
        }
        if (!probe.cand) {
            probe.cand = {};
            PROBE_FIELDS.forEach(function (f) { probe.cand[f] = { full: [], word: [] }; });
            probe.seen = { status: [], appl: [], bucket: [] };
            // Re-read the font whenever the candidate set resets, so a zoom or
            // font-size change between result sets is picked up.
            probeFontSync();
        }
        (rows || []).forEach(function (c) {
            PROBE_FIELDS.forEach(function (f) { probeOffer(probe.cand[f], c[f]); });
            [['status', c.control_status], ['appl', c.applicability],
             ['bucket', maturityBucket(c.control_maturity, c.desired_maturity)]]
                .forEach(function (pairing) {
                    var list = probe.seen[pairing[0]];
                    if (list.indexOf(pairing[1]) === -1) { list.push(pairing[1]); probe.dirty = true; }
                });
        });
    }

    // A (current, desired) maturity pair that renders as the given bucket. Only
    // the BUCKET reaches the Maturity column (renderMaturity()), so any pair
    // producing it is equivalent for measurement -- and this keeps the probe
    // free of an assumption about what levels exist.
    function probeMaturityPair(bucket) {
        if (bucket === 'below') { return [1, 3]; }
        if (bucket === 'at') { return [3, 3]; }
        if (bucket === 'above') { return [4, 2]; }
        return [0, 0];                       // '' -- the em dash, no chip
    }

    // The k-th probe row's synthetic control. Ranks 0..PROBE_ROWS-1 take the
    // k-th longest WHOLE STRING per field; ranks PROBE_ROWS..2*PROBE_ROWS-1 take
    // the k-th longest WORD. Both are needed -- see the note on PROBE_ROWS.
    function probeControl(k) {
        var wordHalf = k >= PROBE_ROWS;
        var rank = wordHalf ? k - PROBE_ROWS : k;
        var seen = probe.seen || { status: [], appl: [], bucket: [] };
        var bucket = seen.bucket.length ? seen.bucket[k % seen.bucket.length] : '';
        var pair = probeMaturityPair(bucket);
        var c = {
            id: null,
            control_status: seen.status.length ? seen.status[k % seen.status.length] : 2,
            control_maturity: pair[0],
            desired_maturity: pair[1],
            applicability: seen.appl.length ? seen.appl[k % seen.appl.length] : null
        };
        PROBE_FIELDS.forEach(function (f) {
            var slot = (probe.cand && probe.cand[f]) || { full: [], word: [] };
            var list = wordHalf ? slot.word : slot.full;
            c[f] = list.length ? (list[rank] || list[0]) : '';
        });
        return c;
    }

    // (Re)builds the probe rows at the end of the tbody. Called on every render
    // so a candidate discovered by a freshly-loaded chunk reaches the DOM before
    // the ladder next reads it.
    function probeMount($tbody) {
        $tbody.children('.sr-ctl-probe').remove();
        probe.mounted = false;
        probe.dirty = false;
        if (!probe.cand) { return; }
        for (var k = 0; k < PROBE_ROWS * 2; k++) {
            var $tr = renderRow(probeControl(k))
                .removeClass('sr-ctl-row')
                .addClass('sr-ctl-probe')
                .removeAttr('data-sr-ctl')
                .attr('aria-hidden', 'true');
            // Nothing inside a probe row is a control the user can act on. The
            // ids are stripped so a stray selector cannot address it, and the
            // interactive descendants are made unfocusable so Tab cannot land
            // in a row that is not there -- visibility:hidden already removes
            // them from the tab order in every current engine, but the
            // attribute says so rather than relying on it.
            $tr.find('input, button').attr('tabindex', '-1').prop('disabled', true).removeAttr('value');
            $tbody.append($tr);
        }
        probe.mounted = true;
    }

    // ===== Virtual scrolling for the control table (Task 47) ================
    //
    // WHAT THIS IS, AND WHAT IT DELIBERATELY IS NOT
    //
    // Reachability was already solved. Task 46 added server-side offset
    // pagination, and it works: every one of the 1,552 controls is reachable
    // through the pager, the offset is in the URL, and the server clamps a
    // stale ?start= onto a page that exists. NOTHING HERE REPLACES THAT. The
    // pager is the default and its code path is untouched.
    //
    // What virtualization adds is a render strategy: it decouples the number of
    // <tr>s in the DOM from the number of rows in the result set. Once that is
    // decoupled, the reason the page size was capped at 200 stops existing --
    // that cap was a measured RENDER cost (task-46-report.md), and the render
    // cost is now constant. So the rows-per-page select gains "All", and
    // choosing it puts the table into one continuous scroll over every filtered
    // control.
    //
    // Measured on this dev instance, 1,552 controls, before any of this:
    //
    //   len=25  time-to-first-row 163ms, 25 <tr>
    //   len=200 time-to-first-row 407ms, 200 <tr>
    //   ALL, rendered the old way: 1,175ms of fetch + 2,329ms of render,
    //        1,552 <tr>, a 94,282px-tall tbody, 1.37MB over the wire
    //
    // That 2.3s render is what made "All" unofferable without a warning, and
    // the warning is what this replaces.
    //
    // WHY CHUNKED-ON-DEMAND RATHER THAN ONE BIG WINDOW
    //
    // The obvious cheaper shape is "ask the server for everything, then
    // virtualize locally". It was rejected on the payload: 1.37MB for 1,552
    // controls is linear in the size of the install, so a customer with 20,000
    // controls would be handed an 18MB response to render 16 rows of. That is
    // the SAME unbounded cost the 200-row cap existed to prevent, moved from
    // the DOM to the network, and it would have forced either a new server
    // clamp (a cap by another name) or a warning (the thing being replaced).
    //
    // Fetching 200-row chunks on demand bounds the response REGARDLESS of how
    // many controls exist, and 200 is not a new number: it is the endpoint's
    // own existing `length` clamp (parse_controls_table_request()). So there is
    // no API change here at all -- the virtual list is built entirely out of
    // requests the paged table already makes.
    //
    // WHAT THE SCROLLBAR MEANS
    //
    // In virtual mode the scrollbar spans all `filtered` rows, and the table
    // gets its own bounded-height scroll region. That region is what makes the
    // sticky header real: today `thead th { position: sticky }` is INERT on this
    // page (measured -- the thead's top always equals the scroller's top,
    // because .sr-table-scroll never scrolls vertically, so the header scrolls
    // away with .page-wrapper like any other content). Virtual mode is the first
    // state in which it sticks.
    //
    // The height is MEASURED from the room the page shell has, not declared as
    // a viewport fraction, for the reason this file's whole tier ladder exists:
    // a number would be wrong for the next layout change. overflow-y is `scroll`
    // rather than `auto` so the gutter is always reserved and clientWidth cannot
    // change underneath the tier ladder as rows load.
    var VIRT_CHUNK = 200;        // == the endpoint's own length clamp
    var VIRT_OVERSCAN = 8;       // rows kept rendered beyond each viewport edge
    var VIRT_EST_ROW = 44;       // px; replaced by the first real measurement
    var VIRT_MIN_VIEWPORT = 320; // px; a scroll region smaller than this is worse than none

    var virt = {
        on: false,
        n: 0,               // rows the scrollbar spans == state.filtered
        chunks: {},         // chunk index -> array of row objects
        pending: {},        // chunk index -> jqXHR
        seq: 0,             // invalidates chunk responses across reloads
        h: null,            // Float64Array(n): per-row height, incl. its drawer
        measured: null,     // Uint8Array(n): 1 once the row has been measured
        off: null,          // Float64Array(n+1): prefix sums of h
        offDirty: true,
        est: VIRT_EST_ROW,
        first: 0,
        last: -1,
        rendered: {},       // row index -> { $row: jQuery, $drawer: jQuery|null }
        expanded: {},       // control id -> true
        frame: 0,
        adjusting: false,
        bound: false
    };

    function virtScroller() {
        return document.querySelector('#sr-ctl-table .sr-table-scroll');
    }

    function virtIsOn() { return virt.on; }

    // "All" is the sentinel, kept as the string the <option> carries so the
    // select's value, state.length and the URL all spell it the same way.
    function lengthIsAll(v) { return String(v) === 'all'; }

    function virtReset(n) {
        virt.n = Math.max(0, n | 0);
        virt.chunks = {};
        virt.pending = {};
        virt.seq++;
        virt.h = new Float64Array(virt.n);
        virt.measured = new Uint8Array(virt.n);
        virt.off = new Float64Array(virt.n + 1);
        for (var i = 0; i < virt.n; i++) { virt.h[i] = virt.est; }
        virt.offDirty = true;
        virt.first = 0;
        virt.last = -1;
        virt.rendered = {};
        virt.expanded = {};
    }

    function virtOffsets() {
        if (!virt.offDirty) { return virt.off; }
        var acc = 0;
        virt.off[0] = 0;
        for (var i = 0; i < virt.n; i++) {
            acc += virt.h[i];
            virt.off[i + 1] = acc;
        }
        virt.offDirty = false;
        return virt.off;
    }

    // The row index whose band contains `px`. Binary search over the prefix
    // sums, so this stays O(log n) as the result set grows.
    function virtIndexAt(px) {
        var off = virtOffsets();
        if (virt.n === 0) { return 0; }
        var lo = 0;
        var hi = virt.n - 1;
        while (lo < hi) {
            var mid = (lo + hi) >> 1;
            if (off[mid + 1] <= px) { lo = mid + 1; } else { hi = mid; }
        }
        return lo;
    }

    function virtChunkIndex(i) { return Math.floor(i / VIRT_CHUNK); }

    function virtRowAt(i) {
        var chunk = virt.chunks[virtChunkIndex(i)];
        return chunk ? (chunk[i % VIRT_CHUNK] || null) : null;
    }

    // Every loaded row, in index order -- the population a bulk action taken
    // without escalation acts on, and the one probeObserve() has already seen.
    function virtLoadedRows() {
        var out = [];
        Object.keys(virt.chunks).forEach(function (ci) {
            (virt.chunks[ci] || []).forEach(function (r) { if (r) { out.push(r); } });
        });
        return out;
    }

    function virtRowById(id) {
        var match = null;
        Object.keys(virt.chunks).some(function (ci) {
            return (virt.chunks[ci] || []).some(function (r) {
                if (r && String(r.id) === String(id)) { match = r; return true; }
                return false;
            });
        });
        return match;
    }

    // Which rendered index a <tr> belongs to, or null when it is not one of
    // ours (a probe row, a spacer, or the drawer's own <tr>).
    function virtIndexOfRow(node) {
        var found = null;
        Object.keys(virt.rendered).some(function (k) {
            if (virt.rendered[k].$row[0] === node) { found = +k; return true; }
            return false;
        });
        return found;
    }

    // "Give me the control this row is about." ONE lookup for both render
    // strategies: paged mode holds its whole page in state.rows, virtual mode
    // holds loaded chunks -- and either way a row that is on screen has its
    // control in hand, which is what the row drawer needs and why it still
    // re-fetches nothing.
    function rowById(id) {
        if (virt.on) { return virtRowById(id); }
        return state.rows.filter(function (r) { return String(r.id) === String(id); })[0] || null;
    }

    // Fetches the chunks the requested index range needs. Uses the SAME query
    // builder the paged path uses, so a virtual chunk and a page differ only in
    // their offset -- there is no second idea anywhere of what the current
    // filter is.
    function virtEnsureChunks(a, b) {
        var first = virtChunkIndex(a);
        var last = virtChunkIndex(b);
        for (var ci = first; ci <= last; ci++) {
            if (virt.chunks[ci] || virt.pending[ci]) { continue; }
            virtFetchChunk(ci);
        }
    }

    function virtFetchChunk(ci) {
        var seq = virt.seq;
        var q = controlsQuery(ci * VIRT_CHUNK, VIRT_CHUNK);
        virt.pending[ci] = $.getJSON(BASE_URL + '/api/v2/governance/controls/table', q)
            .then(function (res) {
                // A chunk that lands after a reload belongs to a result set that
                // no longer exists. Dropped whole, exactly as reloadTable()
                // drops a stale page -- and note this path deliberately does NOT
                // fire sr:controls-loaded, which is wired to clearSelection():
                // loading more of the list the user is already looking at must
                // not throw away what they have ticked.
                if (seq !== virt.seq) { return; }
                delete virt.pending[ci];
                virt.chunks[ci] = (res && res.data && res.data.rows) || [];
                probeObserve(virt.chunks[ci]);
                virtScheduleRender();
            })
            .fail(function () {
                if (seq !== virt.seq) { return; }
                // Left un-cached and un-pending so the next scroll into this
                // band retries. A failed chunk shows its skeleton rows rather
                // than replacing the whole table with the "couldn't load" tile:
                // the rest of the list is fine and still usable.
                delete virt.pending[ci];
            });
    }

    // A placeholder for a row whose chunk has not arrived. Carries the column
    // classes so the table's column widths do not jump when it is swapped for
    // the real row, and is explicitly NOT a `.sr-ctl-row` -- there is no control
    // here to select, expand or act on, and every selector in this file and in
    // the E2E suite reads `.sr-ctl-row` as "a real control row".
    function virtSkeletonRow() {
        var $tr = $('<tr class="sr-ctl-skel" aria-hidden="true">');
        controlColumns().forEach(function (col) {
            $tr.append($('<td>').addClass(col.cls).append($('<span class="sr-skel-bar">')));
        });
        return $tr;
    }

    function virtHeightOf(entry) {
        var h = entry.$row[0].offsetHeight;
        if (entry.$drawer) { h += entry.$drawer[0].offsetHeight; }
        return h;
    }

    // Renders one index into the window. Selection and expansion are restored
    // from the authoritative client state, never from whatever the recycled DOM
    // happened to hold -- which is what makes a tick survive scrolling out of
    // the window and back.
    function virtBuild(i) {
        var c = virtRowAt(i);
        if (!c) { return { $row: virtSkeletonRow(), $drawer: null, skel: true }; }
        var $row = renderRow(c).attr('aria-rowindex', String(i + 2));
        var checked = selectAllFiltered || selection.has(c.id);
        if (checked) {
            $row.addClass('sr-row-checked').find('.sr-ctl-check').prop('checked', true);
        }
        var $drawer = null;
        if (virt.expanded[c.id]) {
            $drawer = renderDrawer(c);
            $row.find('.sr-group-caret')
                .attr({ 'aria-expanded': 'true', 'aria-controls': $drawer.attr('id') });
        }
        return { $row: $row, $drawer: $drawer, skel: false };
    }

    function virtNodesOf(entry) {
        return entry.$drawer ? entry.$row.add(entry.$drawer) : entry.$row;
    }

    function virtDrop(i) {
        var entry = virt.rendered[i];
        if (!entry) { return; }
        // Hand focus off BEFORE the node holding it is detached (see
        // virtHoldsFocus() above for why the row is not kept instead).
        if (virtHoldsFocus(entry)) {
            var sc = virtScroller();
            if (sc) { sc.focus({ preventScroll: true }); }
        }
        // A row-actions menu open inside a row about to leave the DOM would be
        // detached mid-flight -- the wrap, its is-open class and the scroller's
        // is-unclipped state would all be lost with it, leaving the scroller
        // permanently unclipped. Closing through the one function that owns that
        // state keeps the invariant instead of unwinding it by hand.
        if (entry.$row.find('.sr-row-actions-wrap.is-open').length) { closeRowActionMenus(); }
        virtNodesOf(entry).remove();
        delete virt.rendered[i];
    }

    // Keyboard focus lives in a DOM node, and recycling that node throws the
    // user to the top of the document -- one of the standard ways a virtual list
    // breaks keyboard navigation. So focus is HANDED SOMEWHERE before the node
    // goes: to the scroll region itself, which is the nearest thing that still
    // exists and keeps the next Tab where the user was rather than at the top of
    // the page.
    //
    // PINNING THE ROW INSTEAD WAS TRIED AND IS WRONG. Keeping a focused row
    // rendered outside the window looks like the kinder answer, and it corrupts
    // the geometry: the spacers are sized for a CONTIGUOUS window, so an extra
    // row smuggled in beside them adds its height to the content without being
    // accounted for anywhere, every row below it shifts, and the browser's own
    // scroll anchoring then drags the scroll position back. Measured on a 60-row
    // list: scrollTop set to 1,865 came back as 78 about 300ms later, and only
    // when a row checkbox held focus. The version of this without a focused
    // element scrolled to 2,124 and stayed there.
    //
    // A virtual list genuinely cannot keep focus on a row that no longer exists.
    // Moving it to the container is the honest version of that, and it is what
    // the scroller's tabindex="-1" (virtSyncViewport()) exists for.
    function virtHoldsFocus(entry) {
        var active = document.activeElement;
        if (!active || active === document.body) { return false; }
        return virtNodesOf(entry).toArray().some(function (n) { return n === active || n.contains(active); });
    }

    function virtSpacers() {
        var off = virtOffsets();
        var top = virt.last < virt.first ? 0 : off[virt.first];
        var bottom = virt.last < virt.first ? off[virt.n] : off[virt.n] - off[virt.last + 1];
        // .find(), not .children(): the block is inside the spacer's <td>, not a
        // direct child of the <tr>. With .children() the height silently went
        // nowhere and the scrollbar spanned the rendered window instead of the
        // result set -- 1,417px where 68,288px was owed. Measured, not guessed:
        // the smoke run reported scrollHeight 1,417 against 1,552 rows.
        $('#sr-ctl-spacer-top').find('div').css('height', Math.max(0, top) + 'px');
        $('#sr-ctl-spacer-bot').find('div').css('height', Math.max(0, bottom) + 'px');
    }

    // Reads back the heights the browser actually gave the rendered rows and
    // folds them into the model. The row height is not a constant this can
    // assume: the compact and queue tiers change it, a long control name wraps,
    // and an expanded drawer adds a variable block. Measuring is the same
    // discipline the tier ladder itself follows.
    //
    // Returns true when anything moved, so the caller can re-anchor the scroll
    // position rather than letting the content slide under the user.
    function virtMeasure() {
        var changed = false;
        Object.keys(virt.rendered).forEach(function (k) {
            var i = +k;
            var entry = virt.rendered[i];
            if (entry.skel) { return; }          // a placeholder's height is not the row's
            var h = virtHeightOf(entry);
            if (h > 0 && Math.abs(h - virt.h[i]) > 0.5) {
                virt.h[i] = h;
                changed = true;
            }
            if (h > 0 && !virt.measured[i]) {
                virt.measured[i] = 1;
                // The first real measurement replaces the estimate for every
                // row still carrying it, so the scrollbar is roughly right
                // immediately instead of converging over the whole list.
                if (virt.est !== h && Object.keys(virt.rendered).length === 1) {
                    virt.est = h;
                }
                changed = true;
            }
        });
        if (changed) { virt.offDirty = true; }
        return changed;
    }

    // A height change anywhere above the viewport moves everything below it.
    // Pinning the top visible row -- its index and its sub-row offset -- and
    // restoring that after the model is rebuilt is what stops the list from
    // jumping while chunks land.
    function virtWithAnchor(fn) {
        var sc = virtScroller();
        if (!sc) { fn(); return; }
        var off = virtOffsets();
        var anchor = virt.last < virt.first ? 0 : virt.first;
        var delta = sc.scrollTop - off[anchor];
        fn();
        var next = virtOffsets();
        var target = next[Math.min(anchor, virt.n)] + delta;
        if (Math.abs(target - sc.scrollTop) > 0.5) {
            virt.adjusting = true;
            sc.scrollTop = Math.max(0, target);
            virt.adjusting = false;
        }
    }

    function virtRender() {
        var sc = virtScroller();
        if (!virt.on || !sc) { return; }
        var off = virtOffsets();
        var a = virt.n === 0 ? 0 : Math.max(0, virtIndexAt(sc.scrollTop) - VIRT_OVERSCAN);
        var b = virt.n === 0 ? -1 : Math.min(virt.n - 1, virtIndexAt(sc.scrollTop + sc.clientHeight) + VIRT_OVERSCAN);

        virtEnsureChunks(a, b);

        // Retire everything outside the new window, plus any row whose skeleton
        // can now be replaced by the real thing. The window stays CONTIGUOUS --
        // nothing is held back for any reason -- because the two spacers are
        // sized on exactly that assumption and a row smuggled outside it breaks
        // the scroll position (see virtHoldsFocus()).
        Object.keys(virt.rendered).forEach(function (k) {
            var i = +k;
            var entry = virt.rendered[i];
            var upgradable = entry.skel && virtRowAt(i);
            if (i < a || i > b || upgradable) { virtDrop(i); }
        });

        var $bot = $('#sr-ctl-spacer-bot');
        for (var i = a; i <= b; i++) {
            if (virt.rendered[i]) { continue; }
            var entry = virtBuild(i);
            // Inserted in index order: before the first rendered row that comes
            // after it, else before the bottom spacer.
            var anchorNode = null;
            for (var j = i + 1; j <= virt.n; j++) {
                if (virt.rendered[j]) { anchorNode = virt.rendered[j].$row[0]; break; }
            }
            // (The window is contiguous, so this finds the immediate successor
            // or nothing; the loop is kept general so a future non-contiguous
            // case cannot silently insert out of order.)
            var $nodes = virtNodesOf(entry);
            if (anchorNode) { $nodes.insertBefore(anchorNode); } else { $nodes.insertBefore($bot); }
            virt.rendered[i] = entry;
        }

        virt.first = a;
        virt.last = b;

        // A chunk that landed since the last pass can have widened a candidate,
        // and a candidate that is only in the model is worth nothing -- the
        // ladder reads the DOM. Without this the probe was frozen at whatever
        // chunk 0 happened to contain, and the widest .sr-col-name cell in the
        // whole result set (at index ~984, four chunks in) never reached it: the
        // probe measured 23px short and the ladder's read still moved by 23px as
        // that row scrolled through. Guarded on the KEPT candidate set actually
        // having changed, so a steady scroll through already-seen data rebuilds
        // nothing.
        if (probe.dirty) {
            probeMount($('#sr-ctl-tbody'));
            // The tier was decided against the narrower probe, so re-decide it
            // against the wider one. This is the only path by which the tier can
            // fold further during a scroll, and it is monotone: candidates never
            // shrink within a result set, so it cannot unfold and cannot cycle.
            evaluateResponsiveTiers();
        }

        if (virtMeasure()) {
            virtWithAnchor(function () { /* offsets already invalidated */ });
        }
        virtSpacers();
        syncHeaderCheckbox();
    }

    function virtScheduleRender() {
        if (virt.frame || !virt.on) { return; }
        virt.frame = window.requestAnimationFrame(function () {
            virt.frame = 0;
            virtRender();
        });
    }

    // The tier ladder changes row heights (the queue tier turns every row into
    // a stacked grid), so every measurement the model holds is void after a
    // flip. Discarded rather than corrected: a wrong height model is a wrong
    // scrollbar, and re-measuring costs one render pass.
    function virtInvalidateHeights() {
        if (!virt.on) { return; }
        for (var i = 0; i < virt.n; i++) {
            virt.h[i] = virt.est;
            virt.measured[i] = 0;
        }
        virt.offDirty = true;
        virtScheduleRender();
    }

    // The bounded scroll region, sized from the room the page shell actually
    // has. Every term is measured: the card's chrome above the scroller (the
    // toolbar or bulk bar) and below it (the footer) are read off the rendered
    // card, and the shell's own clientHeight is the room there is. Nothing here
    // reads a viewport width or height directly, so the sidebar, zoom and font
    // size all ride along for free -- the same property the tier ladder has.
    function virtSyncViewport() {
        var sc = virtScroller();
        var card = document.getElementById('sr-ctl-table');
        if (!sc || !card) { return; }
        if (!virt.on) {
            sc.style.maxHeight = '';
            sc.classList.remove('sr-table-scroll-virt');
            sc.removeAttribute('tabindex');
            return;
        }
        var shell = document.querySelector('.page-wrapper') || document.documentElement;
        var cardRect = card.getBoundingClientRect();
        var scRect = sc.getBoundingClientRect();
        var above = scRect.top - cardRect.top;
        var below = cardRect.bottom - scRect.bottom;
        var avail = shell.clientHeight - above - below - 16;
        sc.classList.add('sr-table-scroll-virt');
        // Programmatically focusable, never in the tab order. This is where
        // focus goes when the row holding it is recycled (virtDrop()), and it is
        // also what makes a keyboard-only user able to scroll the region at all
        // -- a scroll container with no focusable content cannot be reached by
        // the arrow keys otherwise.
        sc.setAttribute('tabindex', '-1');
        sc.style.maxHeight = Math.max(VIRT_MIN_VIEWPORT, Math.round(avail)) + 'px';
    }

    // Mounts the virtual list into a freshly emptied tbody: the two spacer rows
    // the scrollbar is made of, the probe, and the first window.
    function virtMount($tbody) {
        var cols = controlColumns().length;
        $tbody.append($('<tr class="sr-ctl-spacer" id="sr-ctl-spacer-top" aria-hidden="true">')
            .append($('<td>').attr('colspan', cols).append($('<div>'))));
        $tbody.append($('<tr class="sr-ctl-spacer" id="sr-ctl-spacer-bot" aria-hidden="true">')
            .append($('<td>').attr('colspan', cols).append($('<div>'))));
        probeMount($tbody);
        virt.rendered = {};
        virt.first = 0;
        virt.last = -1;
        virtSyncViewport();
        virtSpacers();
        virtRender();
    }

    // Puts an index at the top of the viewport. The public entry point for
    // "show me row N" -- used by the E2E suite and available for a future
    // deep link, and the reason a bookmarked ?start= remains meaningful in a
    // list with no pages.
    function virtScrollToIndex(i) {
        var sc = virtScroller();
        if (!virt.on || !sc) { return; }
        var off = virtOffsets();
        sc.scrollTop = off[Math.max(0, Math.min(virt.n - 1, i | 0))];
        virtRender();
    }

    function renderTable(st) {
        // Rebuilt on every load, not once at startup: the Applicability column
        // appears and disappears with the rail's scope, and the header has to
        // be built from the same controlColumns() the rows are. Cheap, and
        // every handler that reaches into the thead is delegated from document,
        // so nothing is lost by replacing it.
        renderThead();
        // Emptying the tbody detaches whatever wrap a menu was open in, so the
        // shared close path runs first -- otherwise .sr-table-scroll keeps the
        // is-unclipped class the open menu put on it, forever.
        closeRowActionMenus();
        var $b = $('#sr-ctl-tbody').empty();
        if (virt.on) {
            virtMount($b);
        } else {
            st.rows.forEach(function (c) { $b.append(renderRow(c)); });
            probeMount($b);
        }
        // aria-rowcount is the whole result set, not the rendered window --
        // "row 900 of 1,552" is only true if the count is the result set's.
        $('#sr-ctl-table table.sr-table').attr('aria-rowcount', String(virt.on ? virt.n : st.filtered));
        renderFoot(st);
        var populated = virt.on ? virt.n > 0 : st.rows.length > 0;
        showControlsEmptyState(populated ? null : (controlsViewIsNarrowed() ? 'noresults' : 'nodata'));

        // The tier depends on what the table CONTAINS, not only on how much
        // room it has -- scoping the rail to one framework adds the
        // Applicability column and moves the requirement by ~122px without any
        // resize happening -- so it is re-decided here, synchronously, on every
        // render. A resize-only trigger is precisely how the tier and the fit
        // came apart in Task 14.
        evaluateResponsiveTiers();
        // AFTER the tier, because the tier decides how tall a row is and the
        // scroll region's height decides how many of them are rendered.
        if (virt.on) {
            virtSyncViewport();
            virtScheduleRender();
        }
    }

    // ===== Selection + contextual bulk bar (Task 7) =========================
    //
    // Swaps #sr-ctl-toolbar for #sr-ctl-bulk the moment anything is checked
    // (design-system §6) -- both containers already exist in the page markup
    // (#sr-ctl-bulk carries d-none from the start), so this is pure
    // visibility + content, no structural changes to the shell.

    // Single source of truth for "how many controls are selected right now" --
    // used by both the bar's displayed count (renderBulkBar()) and, via
    // selectionPayload() reading the same `selectAllFiltered` flag, the
    // payload a bulk action would send. Keeping one function backing both
    // means the count on screen and the set an action would actually act on
    // can never drift apart (the bug this exists to prevent: `selection`
    // holds page ids, `selectAllFiltered` means "ignore that, act on
    // everything the filter matches").
    function selectionCount() {
        return selectAllFiltered ? state.filtered : selection.size;
    }

    // Reflects the live selection onto the header "select all this page"
    // checkbox -- checked when every row on the current page is selected,
    // indeterminate for a partial page, unchecked otherwise.
    //
    // In PAGED mode it reads the DOM, because it's the row checkboxes' own
    // checked state that the header checkbox has to agree with, regardless of
    // what produced it (a row click, the header checkbox itself, or a fresh
    // render after a reload). The selector excludes `.sr-ctl-probe`: the
    // min-content probe rows (Task 47) carry a checkbox because they are built
    // by renderRow(), and counting six permanently-unchecked phantom rows would
    // make "every row is selected" unreachable.
    //
    // In VIRTUAL mode it reads the MODEL, because the DOM holds a window and
    // the question is about the whole result set. This is the same reason
    // selectionCount() reads `selectAllFiltered` instead of counting ticks:
    // the header checkbox must describe the population a bulk action would act
    // on, not the population that happens to be on screen. A DOM-based read
    // here would have made the header checkbox mean "all rendered", which is
    // exactly the silent narrowing this task forbids.
    function syncHeaderCheckbox() {
        var $all = $('#sr-ctl-check-all');
        if (!$all.length) { return; }
        var total;
        var checkedCount;
        if (virt.on) {
            total = virt.n;
            checkedCount = selectAllFiltered ? total : Math.min(selection.size, total);
        } else {
            var $rows = $('#sr-ctl-tbody tr:not(.sr-ctl-probe) .sr-ctl-check');
            total = $rows.length;
            checkedCount = $rows.filter(function () { return this.checked; }).length;
        }
        $all.prop('checked', total > 0 && checkedCount === total);
        $all.prop('indeterminate', checkedCount > 0 && checkedCount < total);
    }

    // Rebuilds #sr-ctl-bulk's contents from scratch on every call -- the same
    // full-rebuild style renderToolbar() already uses elsewhere in this file
    // -- rather than mutating individual nodes. That's what lets "Select all
    // N" appear, disappear once escalated, and come back after a Clear, with
    // no separate DOM-state bookkeeping to keep in sync with `selectAllFiltered`.
    //
    // DOM order mirrors the SHIPPED .sr-bulk-bar component (Define Tests'
    // #define-tests-bulk-bar in includes/compliance.php, and
    // self-assessment.js's JS-built one): clear (x) first, then the count,
    // then the actions cluster. #sr-ctl-select-all-filtered has no shipped
    // equivalent (neither of those bars escalates past the current page), so
    // it's inserted between the count and the actions -- extending the
    // shared shape rather than replacing it.
    function renderBulkBar() {
        var n = selectionCount();
        var $bulk = $('#sr-ctl-bulk').empty();

        $('<button type="button" class="sr-bulk-clear" id="sr-ctl-clear-sel">')
            .attr('aria-label', _lang['Clear'])
            .html('&times;')
            .appendTo($bulk);

        // Thousands-formatted for the same reason the footer summary is: an
        // escalated selection under "All" reads "1,552 selected", and the
        // "Select all N" link just below it has always formatted its own number
        // through state.filtered. Values below 1,000 are untouched.
        $('<span class="sr-bulk-count" id="sr-ctl-bulk-n">')
            .text(_lang['NSelected'].replace('{n}', n.toLocaleString()))
            .appendTo($bulk);

        // Escalating to "every filtered row" only means something once there
        // IS a wider filtered set than what's already selected on this page,
        // and only until it actually happens -- offering a no-op escalation
        // (or one already in effect) is a dead affordance.
        if (!selectAllFiltered && state.filtered > selection.size) {
            $('<button type="button" class="sr-bulk-lnk" id="sr-ctl-select-all-filtered">')
                .text(_lang['SelectAllN'].replace('{n}', state.filtered.toLocaleString()))
                .appendTo($bulk);
        }

        var $actions = $('<div class="sr-bulk-actions">').appendTo($bulk);

        // Set applicability (Task 15) -- the interaction the Statement of
        // Applicability stands on, so it leads the cluster and the destructive
        // Delete stays last.
        //
        // ABSENT under "All controls", not disabled. Applicability is
        // per-framework: the same control excluded from ISO 27001 is not thereby
        // excluded from PCI DSS, so with no framework scoped there is nothing to
        // record a decision against -- exactly why the column and the facet are
        // absent there too (Task 14). Offering a disabled button instead would
        // imply the decision exists here and is merely unavailable.
        //
        // AND `modify_frameworks` (Task 58) -- the permission
        // api_v2_governance_applicability_set() actually enforces, for the
        // reason stated at the top of api/v2/includes/applicability.php:
        // scoping a control out of a framework is a framework-scoping
        // decision, not a control edit. Same absent-not-disabled treatment as
        // the scope rule it sits beside.
        // scopedFramework() for the same reason the facet and the column are
        // withheld under "Unassigned controls": there is no framework for the
        // decision to belong to, and api_v2_governance_applicability_set()
        // would refuse the write anyway.
        if (scopedFramework() !== null && can('modify_frameworks')) {
            $('<button type="button" class="btn btn-outline-secondary btn-sm" id="sr-ctl-set-applicability">')
                .text(_lang['SetApplicability'])
                .appendTo($actions);
        }

        // Neutral-until-hover (design-system §6) -- $sr-important is already
        // spent on the toolbar's + Add control, so bulk Delete is the same
        // btn-outline-danger pattern Define Tests' own bulk bar already uses
        // (includes/compliance.php's #define-tests-bulk-delete), never a
        // standing red fill. Wired only to OPEN the existing controls--delete
        // confirm modal (already rendered, inert, in governance/index.php) --
        // the delete call itself is Task 8's, which also converts that modal
        // onto the sr-modal shell.
        //
        // `delete_controls` (Task 58) -- POST /governance/controls/bulk-delete
        // gates on it explicitly (api/v2/includes/governance_controls.php), and
        // this is the affordance the redesign made most dangerous to leave
        // ungated: escalated with "Select all N" it offers to delete up to the
        // endpoint's 2,000-row cap in one click to a user who cannot delete one.
        if (can('delete_controls')) {
            $('<button type="button" class="btn btn-outline-danger btn-sm" id="sr-ctl-bulk-delete"'
                    + ' data-bs-toggle="modal" data-bs-target="#controls--delete">')
                .text(_lang['DeleteSelectedControls'])
                .appendTo($actions);
        }
    }

    // Drops the selection and everything derived from it, in one place (Task
    // 62). Three callers now mean "there is no selection any more" -- the Clear
    // (x), a completed reload, and a FAILED reload -- and each of them
    // previously spelled it out for itself, which is how the failure path came
    // to spell out only two thirds of it. The DOM half is included here rather
    // than left to the caller because a cleared `selection` with ticked
    // checkboxes behind it is the same lie in the other direction.
    function clearSelection() {
        selection.clear();
        selectAllFiltered = false;
        $('.sr-ctl-check').prop('checked', false).closest('tr').removeClass('sr-row-checked');
        syncSelection();
    }

    function syncSelection() {
        // selectionCount(), not selection.size (Task 47). The two agreed as long
        // as the only route to `selectAllFiltered` was the bulk bar's own
        // escalation link, which could not be reached without something already
        // ticked. Virtual mode adds a route that does not add ids -- the header
        // checkbox, which under one continuous page MEANS "every filtered
        // control" -- so an escalated selection can legitimately hold zero ids,
        // and reading .size would have hidden the bulk bar over a selection of
        // 1,552. One function backs the bar's visibility, its number and the
        // payload, which is the invariant selectionCount() was introduced for.
        var n = selectionCount();
        $('#sr-ctl-bulk').toggleClass('d-none', n === 0);
        $('#sr-ctl-toolbar').toggleClass('d-none', n > 0);
        if (n > 0) { renderBulkBar(); }
        syncHeaderCheckbox();
        // Bulk actions send the FILTER, not ids, when escalated -- so the
        // server acts on exactly the set the user was looking at, including
        // rows the client never fetched.
        $(document).trigger('sr:selection-changed', [Array.from(selection)]);
    }

    // ===== Row drawer: in-place read view (Task 6) ==========================
    //
    // Expanding a row reads the control straight out of SRFrameworks.state.rows
    // -- no re-fetch, no reload -- so the caller's filters, scroll position and
    // page are never disturbed. renderTable() above rebuilds #sr-ctl-tbody from
    // scratch on every reload, which discards any open drawer along with the
    // rest of the tbody; that's intentional (a stale drawer is worse than a
    // dropped one) and needs no special-casing here.
    //
    // Multiple rows may be expanded at once: each caret only opens/closes its
    // OWN drawer and never touches a sibling's. There's no product requirement
    // for exclusive expansion, and the per-row toggle is simpler to reason
    // about (nothing has to hunt down and collapse "whatever else was open").

    // A missing value renders as an em dash, never a blank cell (design-system
    // 11: values, not disabled controls). `opts.isEmpty` lets a caller override
    // the default null/undefined/'' check -- needed for mitigation_percent,
    // where 0 is a real value, and for control_type_names, which arrives as an
    // (possibly empty) array joined into a string before it ever reaches here.
    function dt(labelKey, value, opts) {
        opts = opts || {};
        var isEmpty = Object.prototype.hasOwnProperty.call(opts, 'isEmpty')
            ? opts.isEmpty
            : (value === null || value === undefined || value === '');
        var text = isEmpty ? '—' : (opts.format ? opts.format(value) : value);
        // .text() only -- control_class_name/control_phase_name/etc. are
        // user-authored lookup-table labels (add_remove_values.php).
        return $('<div class="sr-dl-item">')
            .append($('<span class="sr-dl-label">').text(_lang[labelKey]))
            .append($('<span class="sr-dl-value">').toggleClass('is-empty', isEmpty).text(text));
    }

    // Same labelled .sr-dl cell as dt(), but for a value that is rendered
    // MARKUP rather than a string -- the maturity level pair. Kept separate
    // rather than adding a "this value is a node" flag to dt(), so dt() stays
    // the one place that decides the em-dash-for-empty rule for text values.
    // `$node` is built by maturityLevelPair(), whose labels go in via .text().
    function dtNode(labelKey, $node) {
        return $('<div class="sr-dl-item sr-dl-item--wide">')
            .append($('<span class="sr-dl-label">').text(_lang[labelKey]))
            .append($('<span class="sr-dl-value">').append($node));
    }

    // description_purified / supplemental_guidance_purified are purified
    // EXACTLY ONCE, server-side, by controls_table_shape_row()
    // (api/v2/includes/governance_controls.php). Insert with .html() and never
    // re-escape or re-purify here -- that would double-encode the markup and
    // show the user literal "&lt;p&gt;" instead of a paragraph.
    function richBlock(labelKey, html) {
        var $block = $('<div class="sr-dl-block">')
            .append($('<span class="sr-dl-label">').text(_lang[labelKey]));
        var isEmpty = !html || !String(html).trim();
        if (isEmpty) {
            $block.append($('<span class="sr-dl-value is-empty">').text('—'));
        } else {
            $block.append($('<div class="sr-dl-rich">').html(html));
        }
        return $block;
    }

    // A labelled block of PLAIN TEXT (the applicability narrative), as opposed
    // to richBlock()'s purified markup. Same .sr-dl-block shell, but the value
    // goes in with .text() and NEVER .html(): `applicability_narrative` and
    // `applicability_provider` are returned raw by the endpoint, exactly like
    // short_name/long_name, because purifying plain text would mangle a
    // justification containing "<" or "&" into entities.
    //
    // Newlines are preserved by rendering each line as its own .sr-dl-value
    // rather than by a white-space rule -- there is no shipped pre-line class,
    // and one line of JS is cheaper than a page-local one that would have to be
    // compiled into the global bundle.
    function textBlock(labelKey, text) {
        var $block = $('<div class="sr-dl-block">')
            .append($('<span class="sr-dl-label">').text(_lang[labelKey]));
        var value = (text === null || text === undefined) ? '' : String(text);
        if (!value.trim()) {
            return $block.append($('<span class="sr-dl-value is-empty">').text('—'));
        }
        value.split('\n').forEach(function (line) {
            $block.append($('<div class="sr-dl-value">').text(line));
        });
        return $block;
    }

    // The drawer's applicability record (spec §4.4): the decision, why, who
    // recorded it and when.
    //
    // Rendered only when the view is scoped to one framework -- the same
    // state.applicabilityScoped flag the column keys off, so the drawer cannot
    // claim a decision the column isn't showing.
    //
    // THE GATE IS "WAS A DECISION RECORDED", NOT "IS THIS A DEVIATION".
    //
    // It used to be the latter, and that was correct when it was written:
    // nothing was stored for an applicable control, so there was nothing to
    // show, and five em dashes under a heading is not "no value" -- it reads as
    // a record that failed to load. Then Task 4 gave an applicable control its
    // own decision (inclusion reasons, optional prose, decided_by, decided_at)
    // and Task 5 gave the modal a way to record one. The condition never
    // changed, so it went from correct to wrong without being edited: a user
    // could record a justification here and then find this drawer -- the read
    // view on the very page they recorded it -- showing the state alone.
    //
    // hasApplicabilityRecord() mirrors applicability_is_empty_decision()
    // (includes/applicability.php) rather than testing the timestamp: an
    // applicable control stores a row IFF it has reasons, prose, or both
    // (delete-to-reset removes it otherwise), so those two fields are what say
    // whether a decision exists. A deviation always has a row -- absence of one
    // resolves to 'applicable' -- so it is recorded by construction, which keeps
    // that path byte-for-byte what it was.
    //
    // Reason and Provider stay conditional within it, because each is
    // state-specific by construction: the domain layer normalises the provider
    // to '' for every state but 'inherited', and an inheritance needs no reason.
    //
    // THE REASONS ARE SHOWN HERE THOUGH THE SoA SUPPRESSES THEM on its
    // applicable rows (Task 6), and the two are not in conflict. There the
    // reasons ARE the Justification cell -- soa_justification_for() step 2
    // composes them into that sentence -- so a separate Reason line restated the
    // clause directly above it. This is a labelled definition list whose
    // Justification block renders `narrative` VERBATIM, so the taxonomy answer
    // appears nowhere else in it; dropping it would hide half of what the user
    // recorded. `applicability_reason` is the endpoint's JOINED form of the
    // multi-select, which is exactly what this one label wants.
    //
    // Every value goes in with .text(): the reason NAME comes from the
    // customer-extendable `control_applicability_reason` table and the provider
    // and narrative are user-authored free text.
    function hasApplicabilityRecord(c) {
        if (c.applicability !== 'applicable') { return true; }
        return !!c.applicability_reason || !!narrativeText(c);
    }

    // The narrative as a trimmed string. Whitespace-only never reaches storage
    // (applicability_effective_fields() folds it to NULL), so this is a guard
    // against a legacy row rather than against the current writer.
    function narrativeText(c) {
        var narrative = c.applicability_narrative;
        return (narrative === null || narrative === undefined) ? '' : String(narrative).trim();
    }

    function renderApplicabilityRecord(c) {
        var $wrap = $('<div>');
        var $dl = $('<div class="sr-dl">')
            .append(dtNode('Applicability', renderApplicability(c.applicability)));

        var recorded = hasApplicabilityRecord(c);

        if (recorded) {
            if (c.applicability_reason) { $dl.append(dt('Reason', c.applicability_reason)); }
            if (c.applicability_provider) { $dl.append(dt('Provider', c.applicability_provider)); }
            $dl.append(dt('ApplicabilityDecidedBy', c.applicability_by));
            $dl.append(dt('ApplicabilityDecidedOn', c.applicability_at));
        }

        $wrap.append($dl);

        // A DEVIATION'S narrative is mandatory (assert_applicability_narrative()
        // refuses the write), so an empty one is a real anomaly and gets
        // textBlock()'s em dash rather than being hidden. An APPLICABLE
        // control's is optional -- reasons alone are a complete answer -- so the
        // block is drawn only when there is prose, and a control justified by
        // its taxonomy reasons gets no empty Justification heading.
        if (c.applicability !== 'applicable' || narrativeText(c) !== '') {
            $wrap.append(textBlock('Justification', c.applicability_narrative));
        }

        return $wrap;
    }

    function renderDrawer(c) {
        // The shipped disclosure shell (_tables.scss: tr.sr-expand-row /
        // .sr-expand-panel) rather than a bespoke .sr-ctl-drawer -- same
        // shell Define Tests uses under its own group/test rows (Task 23).
        // The .sr-dl-*/richBlock() content inside is unchanged; only the
        // outer <tr>/wrapper classes moved to the shared pattern.
        var $tr = $('<tr class="sr-expand-row">').attr({ id: 'sr-ctl-drawer-' + c.id, 'data-sr-drawer': c.id });
        // controlColumns(), not COLUMNS: the Applicability column comes and
        // goes with the rail's scope, so a colspan off the fixed list would
        // leave the drawer one cell short of the row it belongs to whenever
        // that column is present.
        var $td = $('<td>').attr('colspan', controlColumns().length);
        var $panel = $('<div class="sr-expand-panel">');

        var $dl = $('<div class="sr-dl">')
            .append(dt('ControlClass', c.control_class_name))
            .append(dt('ControlPhase', c.control_phase_name))
            .append(dt('ControlPriority', c.control_priority_name))
            .append(dt('ControlType', (c.control_type_names || []).join(', '),
                { isEmpty: !(c.control_type_names && c.control_type_names.length) }))
            .append(dt('MitigationPercent', c.mitigation_percent,
                {
                    isEmpty: c.mitigation_percent === null || c.mitigation_percent === undefined,
                    format: function (v) { return v + '%'; }
                }));

        // The exact maturity levels (Task 34). The Maturity COLUMN now shows
        // only the Below/At/Above bucket, so the drawer is where the precise
        // current -> desired pair lives -- the read view, which has the room
        // the column doesn't. A control with no desired maturity falls through
        // to dt()'s ordinary em dash: there is no pair to draw.
        $dl.append(c.desired_maturity
            ? dtNode('ControlMaturity', maturityLevelPair(
                c.control_maturity, c.desired_maturity,
                c.control_maturity_name, c.desired_maturity_name))
            : dt('ControlMaturity', null));

        $panel.append($dl);

        // The applicability record (spec §4.4), directly under the control's
        // own facts and above the two long prose blocks -- it is a decision
        // about this control's scope, which is read alongside its
        // classification rather than after two screens of guidance.
        if (state.applicabilityScoped) {
            $panel.append(renderApplicabilityRecord(c));
        }

        $panel.append(richBlock('Description', c.description_purified));
        $panel.append(richBlock('SupplementalGuidance', c.supplemental_guidance_purified));

        $td.append($panel);
        $tr.append($td);
        return $tr;
    }

    // ===== Footer zone: row count, page size, pager (Task 46) ===============
    //
    // The footer's static parts (#sr-ctl-info, the rows-per-page <select>,
    // #sr-ctl-pager) are server-rendered in governance/index.php on the
    // SHIPPED .sr-table-foot / .dt-info / .dt-length / .dt-paging shape from
    // _tables.scss -- the same markup Define Tests emits
    // (includes/compliance.php) and the same pager shape it renders
    // (compliance-define-tests.js's renderFooter()). Nothing here invents a
    // class: this page gets the identical pager for free, and a change to
    // that component moves both surfaces together.

    // How many pages the current result set has. Guards the two degenerate
    // inputs -- an empty result set, and a page size that somehow arrived as
    // zero -- so the pager can never divide by zero or claim "page 1 of 0".
    function controlsPageCount(filtered, length) {
        if (filtered <= 0 || length <= 0) { return 1; }
        return Math.ceil(filtered / length);
    }

    // Which page an offset lands on, 1-based. `start` is the offset the
    // SERVER sliced at (reloadTable() adopts res.data.start), already clamped
    // onto a page that exists, so this never has to re-derive the clamp.
    function controlsCurrentPage(start, length) {
        if (start <= 0 || length <= 0) { return 1; }
        return Math.floor(start / length) + 1;
    }

    // The sliding window of numbered buttons around the current page. 62
    // pages of controls will not fit in a footer, so the pager shows at most
    // `size` numbers, kept centred on the current page and pinned to the ends
    // (page 1 shows 1-5, the last page shows the last five) -- the same
    // windowing Define Tests' pager does.
    function controlsPageWindow(currentPage, pageCount, size) {
        var end = Math.min(pageCount, Math.max(1, currentPage - Math.floor(size / 2)) + size - 1);
        return { start: Math.max(1, end - size + 1), end: end };
    }

    function renderFoot(st) {
        // Virtual mode is ONE page holding everything (Task 47), so the summary
        // spans the whole filtered set. Reusing ShowingXToYOfZ rather than
        // inventing a second sentence: "Showing 1-1,552 of 1,552" is both true
        // and the same shape the paged view prints, which is what a user
        // switching between the two should see.
        var start = st.filtered === 0 ? 0 : (virt.on ? 1 : st.start + 1);
        var end = virt.on ? st.filtered : Math.min(st.start + st.length, st.filtered);
        // All three numbers are thousands-formatted now, not just the total. The
        // total always was, and with one continuous page the range reaches the
        // same magnitude -- "Showing 1-1552 of 1,552" formats two of the three
        // numbers in one sentence and looks like a bug. Values below 1,000 are
        // unchanged by toLocaleString(), so nothing the paged view already
        // prints moves.
        $('#sr-ctl-info').text(formatTemplate(_lang['ShowingXToYOfZ'], {
            start: start.toLocaleString(),
            end: end.toLocaleString(),
            total: st.filtered.toLocaleString()
        }));
        // The select is the input for state.length AND a display of it: the
        // server clamps the page size it was handed, and reloadTable() adopts
        // whatever came back, so re-sticking the value here keeps the control
        // showing the size actually in force rather than the one requested.
        $('#sr-ctl-length').val(String(st.length));
        renderPager(st);
    }

    // The offset a numbered button jumps to. A numbered button is ABSOLUTE --
    // clicking "5" twice stays on page 5 -- so the page is captured, but the
    // page SIZE is still read at click time. Built through a factory rather
    // than a closure written inline in the loop below because `var` is
    // function-scoped: an inline closure would leave every numbered button
    // pointing at the last page in the window.
    function pageStartResolver(page) {
        return function () { return (page - 1) * state.length; };
    }

    function renderPager(st) {
        var $pager = $('#sr-ctl-pager').empty();
        // Virtual mode has exactly one page (Task 47), which is the same
        // condition the guard below already treats as "no pager": the scrollbar
        // is the navigation, and a Previous/Next pair with nowhere to go is the
        // noise that guard exists to suppress. Returning through the SAME rule
        // rather than a mode check keeps one definition of "there is nothing to
        // page through".
        var pageCount = virt.on ? 1 : controlsPageCount(st.filtered, st.length);
        // One page of results needs no pager at all -- a lone disabled
        // Previous/Next pair is noise, not navigation.
        if (pageCount <= 1) { return; }

        var currentPage = controlsCurrentPage(st.start, st.length);
        var $ul = $('<ul class="pagination">');

        // `resolveStart` runs at CLICK time, never at render time. That is
        // what makes three quick Next clicks advance three pages: all three
        // land on the pager built for page 1 (the first response hasn't
        // re-rendered it yet), so a target captured when the button was built
        // would make every one of them ask for page 2. Reading state.start
        // when the click happens makes them ask for pages 2, 3 and 4 instead,
        // and reloadTable()'s sequence guard makes the LAST of those the one
        // that renders. Overshooting the end is safe: the server clamps the
        // offset onto the last real page and reports back what it used.
        function addButton(label, resolveStart, disabled, current) {
            var $li = $('<li>').addClass('page-item' + (disabled ? ' disabled' : '') + (current ? ' active' : ''));
            var $btn = $('<button type="button" class="page-link">').text(label);
            // Genuinely disabled, not merely tinted: a styled-only disabled
            // button still takes focus and still fires on Enter, so keyboard
            // users could page "before page 1". .disabled on the <li> is what
            // _tables.scss paints; the property is what makes it true.
            if (disabled) { $btn.prop('disabled', true); }
            // The current page is the one piece of pager state that is
            // otherwise purely visual (a charcoal fill). aria-current is how
            // it reaches a screen reader.
            if (current) { $btn.attr('aria-current', 'page'); }
            if (!disabled && !current) {
                $btn.on('click', function () {
                    state.start = resolveStart();
                    reloadTable();
                });
            }
            $li.append($btn);
            $ul.append($li);
        }

        addButton(_lang['Previous'], function () {
            return Math.max(0, state.start - state.length);
        }, currentPage === 1, false);

        var win = controlsPageWindow(currentPage, pageCount, 5);
        for (var p = win.start; p <= win.end; p++) {
            addButton(String(p), pageStartResolver(p), false, p === currentPage);
        }

        addButton(_lang['Next'], function () {
            return state.start + state.length;
        }, currentPage === pageCount, false);

        // A labelled <nav> landmark, so the pager is reachable by landmark
        // navigation and announces as something other than a stray button
        // group. The <ul> stays a direct descendant of .dt-paging's subtree,
        // which is all _tables.scss's .pagination rules require.
        $pager.append($('<nav>').attr('aria-label', _lang['ControlsPagination']).append($ul));
    }

    // ===== CRUD wiring: frameworks + controls (Task 8) ======================
    //
    // The seven modals converted to the sr-modal shell (governance/index.php,
    // includes/governance.php's display_update_framework_modal()/
    // display_update_control_modal()) submitted to nothing after Task 2
    // removed this page's native POST handlers. This wires each one to the
    // v2 CRUD that already exists -- createFrameworkCrud/updateFrameworkById/
    // deleteFrameworkById, createControlCrud/updateControlById/
    // deleteControlById (includes/api.php) -- no new endpoints.

    // A JSON-body or plain-object POST/PATCH/DELETE via $.ajax bypasses
    // csrf-magic's automatic <form> rewrite (csrf_ob_handler(),
    // vendor/simplerisk/csrf-magic/csrf-magic.php), so it needs the token
    // explicitly via the CSRF-TOKEN header -- csrf-magic.php copies that
    // header into $_POST['__csrf_magic']. Mirrors compliance-define-tests.js's
    // own csrfHeaders().
    function csrfHeaders() {
        return { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' };
    }

    // Flushes every live WYSIWYG editor's content back into its source
    // <textarea> before a submit reads the form. hugerte's own 'change'
    // handler (js/WYSIWYG/editor.js) already does this on every edit, but a
    // save clicked immediately after typing -- before that handler fires --
    // would otherwise read a stale value.
    function flushEditors() {
        if (typeof hugerte !== 'undefined' && hugerte.triggerSave) { hugerte.triggerSave(); }
    }

    // design-system.md §8: "On error the modal stays open and surfaces the
    // message inline." The banner keeps the failure visible inside the modal
    // (so it isn't lost behind a toast that has already faded); the toast
    // matches how every other page in the app surfaces an API failure.
    // Message text is the server's own status_message (plain, non-HTML
    // English already) or the generic RequestFailed fallback -- never
    // re-escaped here.
    function showModalError($modal, message) {
        var text = message || _lang['RequestFailed'] || '';
        $modal.find('.sr-modal-inline-error').text(text).removeClass('d-none');
        showAlertFromMessage(text, false);
    }

    function clearModalError($modal) {
        $modal.find('.sr-modal-inline-error').addClass('d-none').text('');
    }

    function apiFailureMessage(xhr) {
        return (xhr && xhr.responseJSON && xhr.responseJSON.status_message) || _lang['RequestFailed'] || '';
    }

    // design-system.md §8: "A form/async primary disables ... during the API
    // call."
    function setBusy($btn, busy) {
        $btn.prop('disabled', busy).toggleClass('is-busy', busy);
    }

    // ---- Framework add / edit ----------------------------------------------
    //
    // createFrameworkCrud()/updateFrameworkById() (includes/api.php) read
    // POST params 'name'/'description'/'parent' -- this form's own fields are
    // named 'framework_name'/'framework_description'/'parent' (the first two
    // inherited from the legacy native-POST contract this page used to
    // submit to directly), so the payload remaps the first two rather than
    // changing either contract.
    //
    // ===== The two SoA fields are OPTIONAL keys, not always-sent ones ========
    //
    // Task 16 put a Statement of Applicability scope statement and a default
    // inclusion justification on `frameworks`, with storage, the API and the
    // modal markup all in place -- but this payload was an explicit three-key
    // object, so both textareas were silently discarded on save and the feature
    // did nothing. They are added here as CONDITIONAL keys, because on this
    // endpoint an absent key and an empty one mean different things:
    //
    //   key absent      leave the stored value alone (the server reads
    //                   isset($_POST[...]) and passes `false` through to
    //                   update_framework_soa_fields(), which skips the column)
    //   key = ""        a deliberate clear, written as an empty string
    //
    // And NULL is a third state the column really carries: every framework that
    // predates Task 16's migration has it, and it means "nobody has ever been
    // asked for this" -- which is what makes the SoA export prompt for it rather
    // than render a blank. Sending "" for an untouched textarea would convert
    // that NULL into a deliberate empty string on the first unrelated rename,
    // and the prompt would never come back. jQuery makes this easy to get wrong:
    // $.param() serialises an `undefined` value as `key=`, so a key can only be
    // omitted by not being on the object at all.
    //
    // So a field is sent only when it would CHANGE something -- measured against
    // what the edit modal actually loaded, which openFrameworkForEdit() parks on
    // the modal. On the Add modal there is nothing loaded, so an untouched
    // textarea is sent as nothing at all and the new framework's column stays
    // NULL: "never asked", which is true.
    //
    // BOTH ARE READ AND WRITTEN THROUGH THE <textarea>, with .val(), never
    // .html() -- including the scope statement, which is now a HugeRTE field.
    // HugeRTE's source element IS that textarea: flushEditors() (hugerte
    // .triggerSave()) writes the editor's live content back into it before this
    // reads, and setEditorContent() is what pushes a value the other way. So the
    // payload contract below is unchanged by the field becoming rich text; what
    // changed is that the string it carries is now MARKUP for scope_statement
    // and still raw plain text for default_inclusion_justification (see
    // update_framework_soa_fields(), includes/governance.php, for why the pair
    // is deliberately asymmetric).
    //
    // Scoped with $form.find()/$modal.find() rather than by id, because
    // display_framework_soa_card() renders the same [name] into BOTH the Add and
    // the Edit modal -- a bare $('[name=scope_statement]') would find the Add
    // modal's copy while editing. The ids DO differ ('add_'/'update_' prefix),
    // and that is what the setEditorContent() calls below address.
    var FRAMEWORK_SOA_FIELDS = ['scope_statement', 'default_inclusion_justification'];

    // The subset of the pair that has a WYSIWYG editor bound to it, as
    // field name -> the id governance/index.php initialised, per modal prefix.
    // Setting a hugerte-backed textarea with .val() alone updates the hidden
    // source and leaves the visible editor showing the PREVIOUS framework's
    // statement -- which the next save would then write back.
    // The hugerte.get() check is not belt-and-braces: setEditorContent()
    // (js/WYSIWYG/helpers.js) dereferences the result unguarded, so calling it
    // for an editor that was never initialised -- a modal rendered without the
    // SoA card, or a page whose WYSIWYG bundle failed to load -- would throw
    // and abandon the rest of the prefill, leaving the previous framework's
    // values in the other fields.
    function setSoaEditorContent(prefix, field, value) {
        if (field !== 'scope_statement') { return; }
        if (typeof setEditorContent !== 'function' || typeof hugerte === 'undefined') { return; }
        if (!hugerte.get(prefix + field)) { return; }
        setEditorContent(prefix + field, value);
    }

    function frameworkSoaPayload($form, loaded) {
        var out = {};
        FRAMEWORK_SOA_FIELDS.forEach(function (field) {
            var $field = $form.find('[name=' + field + ']');
            // The Initiate Audits page's Edit modal renders no SoA card
            // (display_add_framework($include_soa)); nothing to send from there.
            if (!$field.length) { return; }

            var now = $field.val();
            var was = (loaded && typeof loaded[field] !== 'undefined') ? loaded[field] : null;

            if (was === null) {
                // Never stored. Only a value the user actually typed is a change;
                // an empty box means the question still has not been answered.
                if (now !== '') { out[field] = now; }
            } else if (now !== was) {
                out[field] = now;
            }
        });
        return out;
    }

    // ---- Customization Extra: custom field values ---------------------------
    //
    // The Customization Extra lets an admin add arbitrary fields to the
    // Framework and Control forms (admin/customization.php?fgroup=framework and
    // ?fgroup=control). display_add_framework() / display_add_control()
    // (includes/governance.php) render them into ALL FOUR modals on this page as
    // `custom_field[<id>]` inputs, and add_framework() / update_framework() /
    // add_framework_control() / update_framework_control() persist whatever
    // arrives under that key through save_custom_field_values() (the Extra).
    //
    // Both halves of that round trip have to be done HERE, because this page
    // submits over AJAX instead of doing the full-page form POST the
    // pre-redesign page did (js/simplerisk/pages/governance.js, still loaded by
    // Compliance -> Initiate Audits, does both of these for its copies of these
    // same two modals):
    //
    //   WRITE  frameworkFormPayload() names every key it sends, one by one, so
    //          custom fields were never in a framework create or update body at
    //          all. The value was typed, the save reported success, and nothing
    //          was stored.
    //   READ   the Edit modals prefill field by field, and custom fields were in
    //          nobody's list. That is not merely cosmetic on the CONTROL form:
    //          it submits with $form.serialize(), so the blank inputs WERE sent,
    //          and save_custom_field_values() wrote '' over every stored value.
    //          Renaming a control erased its custom fields.
    //
    // save_custom_field_values() iterates over what it RECEIVES and leaves
    // anything absent alone -- which is why an empty <select multiple> (a
    // multiselect with nothing chosen submits no key at all) survived that wipe
    // while an empty text box did not, and why prefilling is a real fix rather
    // than a cosmetic one.
    //
    // The `]` in the prefix selector is load-bearing: '[name^="custom_field[9]"]'
    // must not match custom_field[96].
    var CUSTOM_FIELD_SELECTOR = '[name^="custom_field["]';

    function customFieldSelector(fieldId) {
        return '[name^="custom_field[' + fieldId + ']"]';
    }

    // bootstrap-multiselect shadows its <select> with its own button and menu,
    // so changing the underlying element's selection is invisible until the
    // widget re-reads it. governance/index.php's ready handler initialises every
    // multi-valued custom field (select.multiselect[name^='custom_field[']), and
    // resetForm() (js/simplerisk/common.js) already calls 'refresh' on exactly
    // this class unguarded.
    //
    // That selector keys off the NAME, not the id, and has to: the Add and
    // Update modals render the same field group, so the id carries the modal's
    // $id_prefix ('add_' / 'update_') to keep it unique per document. The name
    // is deliberately left un-prefixed -- it is what the form submits under and
    // what every consumer selects on.
    //
    // Guarded here anyway, on .data('multiselect') -- the instance the plugin
    // parks on the element, and the same test compliance.js uses before driving
    // one. A plain <select multiple> that was never wrapped, or a page whose
    // multiselect bundle failed to load, would otherwise throw mid-prefill and
    // abandon the REMAINING fields, leaving the previously opened row's values
    // in them: a missing widget would become a data-loss bug.
    function refreshMultiselect($el) {
        if (typeof $el.multiselect !== 'function' || !$el.data('multiselect')) { return; }
        $el.multiselect('refresh');
    }

    function clearCustomFields($scope) {
        $scope.find(CUSTOM_FIELD_SELECTOR).each(function () {
            var $el = $(this);
            if ($el.prop('multiple')) {
                $el.val([]);
                refreshMultiselect($el);
            } else {
                $el.val('');
            }
        });
    }

    // Prefill a modal's custom fields from an API response's `custom_values`
    // (get_custom_value_by_row_id(), attached by get_framework() and
    // get_framework_control()).
    //
    // Clears FIRST, unconditionally: one pair of modals serves every framework
    // and every control on the page, so a value left over from the previously
    // opened row would otherwise be shown as -- and then saved onto -- the next
    // one. That is the same rule the SoA pair and the status select above are
    // set unconditionally for.
    function applyCustomFieldValues($scope, customValues) {

        clearCustomFields($scope);

        if (!customValues || !customValues.length) { return; }

        customValues.forEach(function (cv) {
            var $el = $scope.find(customFieldSelector(cv.field_id));
            if (!$el.length) { return; }

            // display_value is the stored value put through the field's own
            // input-element transformation (custom_field_display_value(), the
            // Customization Extra): a date in the instance's configured format
            // rather than the column's Y-m-d, and the plaintext of an encrypted
            // field rather than its ciphertext. Both matter on the way back OUT
            // as well -- save_custom_field_values() parses dates with
            // get_standard_date_from_default_format() and re-encrypts what it is
            // handed -- so prefilling the raw column value would save a
            // 0000-00-00 date and a doubly-encrypted string.
            //
            // Falls back to `value` so a Core running against an older
            // Customization Extra, whose response carries no display_value,
            // still prefills everything except those two transformations.
            var value = (cv.display_value === null || typeof cv.display_value === 'undefined')
                ? cv.value
                : cv.display_value;
            value = (value === null || typeof value === 'undefined') ? '' : String(value);

            if ($el.prop('multiple')) {
                // Stored as a comma-separated list of option ids. filter(Boolean)
                // drops the empty string ''.split(',') yields, which would
                // otherwise ask the widget to select an option with no value.
                $el.val(value.split(',').filter(Boolean));
                refreshMultiselect($el);
            } else {
                $el.val(value);
            }
        });
    }

    // The custom fields of a form as name/value pairs, for a request body built
    // by hand. serializeArray() rather than an object because a multi-valued
    // field submits its name more than once (custom_field[7][]=a&custom_field[7][]=b)
    // and an object would keep only the last one.
    function customFieldPairs($form) {
        return $form.find(CUSTOM_FIELD_SELECTOR).serializeArray();
    }

    // A framework request body: the named payload keys plus whatever custom
    // fields the form is carrying. $.param() accepts the {name, value} pair
    // array serializeArray() produces, so the two halves concatenate cleanly and
    // the bracketed custom_field keys are encoded exactly as a plain form POST
    // would encode them.
    function frameworkRequestBody($form, payload) {
        var pairs = [];
        $.each(payload, function (name, value) { pairs.push({ name: name, value: value }); });
        return $.param(pairs.concat(customFieldPairs($form)));
    }

    function frameworkFormPayload($form, loaded) {
        var payload = $.extend({
            name: $form.find('[name=framework_name]').val(),
            description: $form.find('[name=framework_description]').val(),
            parent: $form.find('[name=parent]').val() || 0
        }, frameworkSoaPayload($form, loaded));

        // Active (1) / Inactive (2). Sent whenever the select is rendered --
        // display_framework_status_edit() emits it only where the receiving
        // handler actually reads it, so an absent field means "this caller
        // cannot set status", not "leave it alone by accident", and adding a
        // key the server would ignore is what the $include_status gate exists
        // to prevent. Sending it unconditionally on Edit is safe because
        // updateFrameworkById() no-ops (and writes no audit-trail entry) when
        // the value matches what is already stored.
        var $status = $form.find('[name=status]');
        if ($status.length) { payload.status = $status.val(); }

        return payload;
    }

    // ---- Keeping the "+ Add Mapping" framework dropdown current -------------
    //
    // The mapping row's framework <select> is rendered ONCE, server-side, at
    // page load: display_add_mapping_row() (includes/governance.php) calls
    // create_dropdown('frameworks', ...), which reads the frameworks table at
    // THAT moment. The pre-redesign page got away with that because Add
    // Framework was a real <form> POST that reloaded the whole page (the
    // `add_framework` submit handler at the top of governance/index.php), so
    // the dropdown was rebuilt on every framework create.
    //
    // This page never full-page-reloads -- framework CRUD is AJAX and only the
    // rail and the control table are re-fetched -- so without this a framework
    // the user just created is absent from the dropdown, and they cannot map a
    // control to the very framework they are looking at until they navigate
    // away and back. Mirror the CRUD into the widget instead.
    //
    // Both the hidden #add_mapping_row template (every future row is cloned
    // from it) and any mapping rows already appended to #add-control-form are
    // updated. create_dropdown() emits a leading blank option followed by one
    // option per framework ordered by `value`, so appending a newly created
    // framework -- whose id is the largest -- lands it exactly where a reload
    // would have put it.
    function mappingFrameworkSelects() {
        return $('#add_mapping_row select[name="map_framework_id[]"]')
            .add('#add-control-form .mapping_framework_table select[name="map_framework_id[]"]');
    }

    function syncMappingFrameworkOption(action, id, name) {
        if (!id && id !== 0) { return; }
        mappingFrameworkSelects().each(function () {
            var $select = $(this);
            var $option = $select.find('option').filter(function () {
                return String(this.value) === String(id);
            });
            if (action === 'delete') {
                // If the removed option was the selected one the <select> falls
                // back to its leading blank, which is `required` -- so the row
                // reads as unanswered rather than silently submitting a
                // framework that no longer exists.
                $option.remove();
            } else if ($option.length) {
                $option.text(name);
            } else {
                $select.append($('<option></option>').attr('value', id).text(name));
            }
        });
    }

    function openFrameworkForEdit(id, name) {
        $.ajax({ type: 'GET', url: BASE_URL + '/api/v2/governance/framework?framework_id=' + id, headers: csrfHeaders() })
            .done(function (res) {
                var fw = (res.data || {}).framework || {};
                var $modal = $('#framework--update');
                $modal.find('[name=framework_id]').val(id);
                $modal.find('[name=framework_name]').val(fw.name || name || '');
                $modal.find('[name=framework_description]').val(fw.description || '');
                if (typeof setEditorContent === 'function') {
                    setEditorContent('update_framework_description', fw.description || '');
                }

                // The SoA pair, prefilled and REMEMBERED. The remembered copy is
                // what the payload above compares against, so it has to keep the
                // server's own null rather than the '' the textarea has to show
                // for it -- that is the whole distinction between "never asked"
                // and "cleared on purpose".
                //
                // Set unconditionally, including to '': this modal is reused for
                // every framework, so a value left from the previously edited one
                // would otherwise be shown as -- and then saved onto -- the next.
                var loaded = {};
                FRAMEWORK_SOA_FIELDS.forEach(function (field) {
                    var value = (typeof fw[field] === 'undefined') ? null : fw[field];
                    loaded[field] = value;
                    $modal.find('[name=' + field + ']').val(value === null ? '' : value);
                    // The scope statement's <textarea> is a hugerte SOURCE
                    // element: .val() alone updates what a submit would read
                    // while the visible editor still shows the previously
                    // edited framework's statement, and the next
                    // flushEditors() would write that stale content back over
                    // the value just loaded.
                    setSoaEditorContent('update_', field, value === null ? '' : value);
                });
                $modal.data('srSoaLoaded', loaded);

                // Active (1) / Inactive (2). Set unconditionally, for the same
                // reason the SoA pair above is: one modal serves every framework,
                // so a status left over from the previously edited one would be
                // shown as -- and then saved onto -- the next. Falls back to '1'
                // only when the response genuinely carries no status, which the
                // NOT NULL DEFAULT 1 column makes impossible for a real row.
                $modal.find('[name=status]').val(String(fw.status || 1));

                // Customization Extra fields. Unlike the control modal below,
                // this one is never resetForm()'d, so applyCustomFieldValues()'s
                // own clear is the only thing standing between the previously
                // edited framework's values and this one.
                applyCustomFieldValues($modal, fw.custom_values);

                $.ajax({
                    type: 'GET',
                    url: BASE_URL + '/api/v2/governance/selected_parent_frameworks_dropdown?child_id=' + id,
                    headers: csrfHeaders()
                }).done(function (parentRes) {
                    // The container carries the id its injected <select> is to
                    // be given (data-sr-field-id, display_framework_parent_edit()
                    // in includes/governance.php). The endpoint's own HTML
                    // cannot: the same markup lands in the Add modal too, so a
                    // baked-in id would be a duplicate -- and the response shape
                    // is a published v1+v2 contract either way.
                    var $container = $modal.find('.parent_frameworks_container');
                    $container.html((parentRes.data || {}).html || '')
                        .find('select[name="parent"]').attr('id', $container.data('sr-field-id'));
                });
                $modal.modal('show');
            })
            .fail(function (xhr) { showAlertFromMessage(apiFailureMessage(xhr), false); });
    }

    // ---- Framework clone (Task 64) ------------------------------------------
    //
    // NOT one-click, and for exactly the reason Clone control is not (Task 24):
    // a silent server-side duplicate "doesn't prompt or anything so the user
    // doesn't know what was cloned" (Josh). This pre-fills the SAME Add
    // Framework modal "+ Add framework" opens -- design-system.md §8, a modal
    // never opens another modal -- from the source framework, and nothing is
    // created until the user reviews it and clicks Add, through the ordinary
    // submitFrameworkAdd() create path.
    //
    // WHAT CARRIES OVER, and the one thing that pointedly does not:
    //
    //   name        seeded as "<source> (Clone)". Not the bare name, the way
    //               Clone control leaves short_name alone -- framework names are
    //               UNIQUE (add_framework() answers 409 on a collision), so a
    //               verbatim copy is a form that cannot be saved.
    //   description carried
    //   parent      carried, applied in the show delegate (the dropdown is
    //               injected on show, so there is nothing to set before then)
    //   status      NOT carried; a clone is always seeded Active. Cloning is the
    //               act of STARTING work on a new scope, and a framework nobody
    //               has retired should not arrive pre-retired -- particularly
    //               since an Inactive create drops straight back out of a rail
    //               filtered to Active and reads as a clone that failed. The
    //               field is right there and editable if Inactive is wanted.
    //               SR-1988 (Archive) inherits this: cloning an archived
    //               framework yields a live one.
    //   default_inclusion_justification  carried. It is program-level boilerplate
    //               naming the driver, not a fact about one framework's scope,
    //               and soa_framework_default_justification() would substitute
    //               the same sentence at read time anyway -- so copying it only
    //               makes the visible value match what the SoA already prints.
    //               A source holding NOTHING is copied as nothing: the box is
    //               left empty, the key is omitted, and the clone's column stays
    //               NULL rather than picking up the Add form's server-seeded
    //               default sentence, which would silently answer a question the
    //               source's owner declined to answer.
    //
    //               "Nothing" here means NULL *or* ''. Those two are one state
    //               downstream -- soa_framework_default_justification()
    //               (includes/soa.php) substitutes the same sentence for both,
    //               and update_framework_soa_fields() compares against
    //               `$current[$column] ?? ''`, so on a CREATE (where the column
    //               is always NULL) sending '' is already a no-op that leaves
    //               NULL. There is therefore nothing for this pre-fill to
    //               preserve by distinguishing them, and code written to try
    //               would be code the endpoint ignores.
    //   scope_statement  DELIBERATELY BLANK, and left NULL rather than ''. It
    //               names entities, sites and systems; a clone that inherited it
    //               would assert a certified scope nobody reviewed for the new
    //               framework, and a stale scope statement on an SoA is worse
    //               than an empty one. NULL is the state Task 16 defined as
    //               "never asked for", which is what makes the SoA prompt for it
    //               -- '' would mean "deliberately cleared" and prompt for
    //               nothing. Leaving the textarea empty on a create is exactly
    //               what produces NULL (frameworkSoaPayload() omits the key).
    //   mappings    copied SERVER-SIDE from `clone_from`, because the form has no
    //               mappings widget to carry them in.
    //   applicability  NOT copied at all -- see clone_framework_control_mappings()
    //               (includes/governance.php). The clone starts with every
    //               control applicable, which is the point of it.
    //
    // The banner is where the blank scope statement is made legible as a
    // decision rather than a field that failed to populate.
    function openFrameworkForClone(id, name, count) {
        $.ajax({ type: 'GET', url: BASE_URL + '/api/v2/governance/framework?framework_id=' + id, headers: csrfHeaders() })
            .done(function (res) {
                var fw = (res.data || {}).framework || {};
                var $modal = $('#framework--add');
                var $form = $('#framework-create-form');

                // Back to the create form's own defaults FIRST, so anything the
                // pre-fill below does not set cannot be inherited from a
                // previous open -- including the Add modal's server-seeded
                // default inclusion justification, which a native reset()
                // restores to its HTML default (submitFrameworkAdd()'s comment
                // has why that seed is server-rendered rather than assigned).
                resetForm('#framework-create-form');

                var clonedName = formatTemplate(_lang['CloneOfFrameworkName'], { name: fw.name || name || '' });
                $form.find('[name=framework_name]').val(clonedName);
                $form.find('[name=framework_description]').val(fw.description || '');
                if (typeof setEditorContent === 'function') {
                    setEditorContent('add_framework_description', fw.description || '');
                }
                // Always Active on a clone -- see the note above. Set explicitly
                // rather than left to the reset, so the rule is stated where the
                // decision is rather than inferred from a markup default.
                $form.find('[name=status]').val('1');

                // The SoA pair. The scope statement is emptied EXPLICITLY, not
                // merely left alone: this modal is reused, and a statement typed
                // into a previous clone that was then cancelled would otherwise
                // still be sitting there.
                $form.find('[name=scope_statement]').val('');
                // ...and emptied in the EDITOR too, not just in its source
                // textarea -- resetForm() above restores the markup default of
                // the <textarea>, which hugerte no longer reads from once it is
                // initialised. Without this the visible box would still hold the
                // previous clone's statement and the save would carry it.
                setSoaEditorContent('add_', 'scope_statement', '');
                var justification = (typeof fw.default_inclusion_justification === 'undefined')
                    ? null
                    : fw.default_inclusion_justification;
                $form.find('[name=default_inclusion_justification]').val(justification === null ? '' : justification);

                // Customization Extra fields carry over, like every other
                // ordinary field on this form: a clone is a pre-filled CREATE
                // that the user reviews, so what it shows has to be what the
                // source framework holds. resetForm() above has already blanked
                // them, so a source with no custom values leaves them blank.
                applyCustomFieldValues($form, fw.custom_values);

                pendingCloneFramework = {
                    id: id,
                    name: fw.name || name || '',
                    count: Number(count) || 0,
                    parent: Number(fw.parent) || 0
                };

                $modal.modal('show');
            })
            .fail(function (xhr) { showAlertFromMessage(apiFailureMessage(xhr), false); });
    }

    function submitFrameworkAdd($form, $btn) {
        flushEditors();
        var $modal = $form.closest('.modal');
        // Whatever the LAST open of this modal was, as the show delegate
        // recorded it: a clone (an object naming the source) or a plain add
        // (null). Read from the element rather than from the closure variable
        // the delegate consumed, so a submit can only ever act on the open it
        // belongs to.
        var clone = $modal.data('srCloneFramework') || null;

        // No loaded state: the framework does not exist yet, so every SoA field
        // is "never stored" and only a typed value is sent.
        var payload = frameworkFormPayload($form, null);

        if (clone) {
            // The one thing the form cannot carry. Everything else about a clone
            // -- name, description, parent, status, justification -- is an
            // ordinary create field the user has just reviewed, which is why
            // this endpoint takes a create request with one extra key rather
            // than a separate clone route that would have to re-accept them all.
            payload.clone_from = clone.id;

            // Nothing else is special-cased here, and one thing that LOOKS like
            // it should be is deliberately not: an SoA field whose source value
            // was a deliberate '' rather than NULL. frameworkSoaPayload() omits
            // an empty box on a create, so such a field arrives as NULL. That
            // is not a lossy shortcut -- it is the only outcome this endpoint
            // has. update_framework_soa_fields() compares a submitted value
            // against `$current[$column] ?? ''`, and on a create the column is
            // always NULL, so an explicit '' is already a no-op that leaves
            // NULL; and downstream the two are one state anyway
            // (soa_framework_default_justification() substitutes the same
            // sentence for NULL and for ''). Code written to carry the
            // distinction would be code the server discards.
        }

        setBusy($btn, true);
        $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/governance/frameworks',
            // The named keys above PLUS the form's custom fields -- see
            // frameworkRequestBody(). add_framework() persists those through
            // save_custom_field_values(), which reads them straight off $_POST.
            data: frameworkRequestBody($form, payload),
            headers: csrfHeaders()
        }).done(function (res) {
            setBusy($btn, false);
            $modal.modal('hide');
            showAlertFromMessage(res.status_message, true);
            // create_dropdown('frameworks') (includes/functions.php) selects
            // WHERE status=1, so a framework created Inactive is one a page
            // reload would NOT put in the mapping dropdown -- mirror that rather
            // than offering an option that vanishes on the next load. 'delete'
            // on an id that was never added is a no-op, which is exactly what
            // is wanted here.
            syncMappingFrameworkOption(
                $form.find('[name=status]').val() === '2' ? 'delete' : 'add',
                (res.data || {}).id,
                $form.find('[name=framework_name]').val()
            );
            $form[0].reset();
            // A native reset() restores the markup defaults of the custom field
            // inputs (blank -- display_add_framework() renders the create form
            // with no values), but it cannot tell a bootstrap-multiselect to
            // re-read its <select>, so a multi-valued custom field would keep
            // showing the just-saved picks. clearCustomFields() does both.
            clearCustomFields($form);
            if (typeof setEditorContent === 'function') { setEditorContent('add_framework_description', ''); }
            // The SoA scope statement is a WYSIWYG field too, and a native
            // reset() puts the <textarea> back without touching the editor that
            // shadows it -- so the next "+ Add framework" would open showing
            // the statement just saved.
            setSoaEditorContent('add_', 'scope_statement', '');
            loadFrameworks();
            reloadTable();
        }).fail(function (xhr) {
            setBusy($btn, false);
            showModalError($modal, apiFailureMessage(xhr));
        });
    }

    function submitFrameworkUpdate($form, $btn) {
        flushEditors();
        var $modal = $form.closest('.modal');
        var id = $form.find('[name=framework_id]').val();
        setBusy($btn, true);
        $.ajax({
            type: 'PATCH',
            url: BASE_URL + '/api/v2/governance/frameworks/' + id,
            // What openFrameworkForEdit() loaded, so an SoA field the user never
            // touched is omitted rather than re-sent -- which is what keeps a
            // rename from converting a NULL scope statement into an empty one.
            // Custom fields ride along on the same body (frameworkRequestBody);
            // update_framework() hands them to save_custom_field_values().
            data: frameworkRequestBody($form, frameworkFormPayload($form, $modal.data('srSoaLoaded'))),
            headers: csrfHeaders()
        }).done(function (res) {
            setBusy($btn, false);
            $modal.modal('hide');
            showAlertFromMessage(res.status_message, true);
            // Same status=1 rule as submitFrameworkAdd(): a framework just set
            // to Inactive leaves the mapping dropdown, one set back to Active
            // rejoins it. 'add' and 'rename' are the same operation inside
            // syncMappingFrameworkOption() -- it appends when the option is
            // missing and relabels when it isn't -- so one call covers both the
            // rename this always was and the re-activation it now can be.
            //
            // Known limitation: deactivating a framework cascades to its whole
            // subtree (update_framework_status(), includes/governance.php), and
            // only the edited framework's own option is synced here. Descendant
            // options linger until the next page load. Harmless -- a mapping made
            // through a stale option is still a valid row, it just won't be
            // attributed on the controls table, which joins frameworks on
            // status=1 -- and not worth re-deriving the whole widget for.
            syncMappingFrameworkOption(
                $form.find('[name=status]').val() === '2' ? 'delete' : 'rename',
                id,
                $form.find('[name=framework_name]').val()
            );
            // The rail is re-fetched against whatever the status FILTER currently
            // reads, so a framework just set to Inactive while the filter says
            // Active drops out of the list instead of lingering in it. When the
            // row that dropped out is the SCOPED one -- either because it is the
            // framework just edited, or because deactivating an ancestor cascaded
            // onto it -- the controls pane would otherwise keep naming and
            // filtering by a framework with no row to highlight. Fall back to
            // "All controls", the same recovery submitFrameworkDelete() makes
            // for the same reason. Only on a SUCCESSFUL reload: a failed one
            // leaves railFrameworks stale, and deselecting off stale data would
            // throw away a scope that is still perfectly valid.
            loadFrameworks().done(function () {
                // scopedFramework(): the two synthetic scopes are never rows in
                // railFrameworks, so a bare null test would read "Unassigned
                // controls" as a framework that just vanished from the rail and
                // silently throw the user back to "All controls" every time any
                // framework's status changed.
                var scopeGone = scopedFramework() !== null && !railFrameworks.some(function (f) {
                    return String(f.value) === String(state.framework);
                });
                if (scopeGone) { selectFramework(null); } else { reloadTable(); }
            }).fail(function () { reloadTable(); });
        }).fail(function (xhr) {
            setBusy($btn, false);
            showModalError($modal, apiFailureMessage(xhr));
        });
    }

    function submitFrameworkDelete($form, $btn) {
        var $modal = $form.closest('.modal');
        var id = $form.find('[name=framework_id]').val();
        setBusy($btn, true);
        $.ajax({
            type: 'DELETE',
            url: BASE_URL + '/api/v2/governance/frameworks/' + id,
            headers: csrfHeaders()
        }).done(function (res) {
            setBusy($btn, false);
            $modal.modal('hide');
            showAlertFromMessage(res.status_message, true);
            syncMappingFrameworkOption('delete', id);
            // The deleted framework can no longer be the scoped view --
            // fall back to "All controls" when it was.
            if (state.framework !== null && String(state.framework) === String(id)) {
                selectFramework(null);
            } else {
                reloadTable();
            }
            loadFrameworks();
        }).fail(function (xhr) {
            setBusy($btn, false);
            showAlertFromMessage(apiFailureMessage(xhr), false);
        });
    }

    // ---- Control add / edit -------------------------------------------------
    //
    // Every field in display_add_control()'s default (non-Customization-Extra)
    // markup is already named exactly what createControlCrud()/
    // updateControlById() (includes/api.php) read -- short_name, long_name,
    // description, supplemental_guidance, control_owner, control_class,
    // control_phase, control_number, control_current_maturity,
    // control_desired_maturity, control_priority, control_type[], family,
    // control_status, mitigation_percent -- so the whole form serializes
    // directly with no remapping. The mapping_framework_table rows
    // (map_framework_id[]/reference_name[]/reference_text[]) also ride along
    // in the serialized body, and createControlCrud() now actually persists
    // them (Task 24 -- parse_control_map_frameworks_request(),
    // includes/governance.php). mapping_asset_table's
    // (asset_maturity[]/assets_asset_groups[][]) rows serialize the same way,
    // and Task 53 gave that widget a working UI path to populate them.
    //
    // The UPDATE verb reads both key sets too, as of Task 20 -- but only when
    // the request carries the submission markers
    // display_mapping_framework_edit()/display_mapping_asset_edit() emit.
    // Absent those, updateControlById() still omits the keys entirely, which
    // is what keeps an unrelated PATCH from wiping a control's mappings
    // through update_framework_control()'s isset([]) trap.
    // ---- Mapped Assets widget (Task 53) -------------------------------------
    //
    // display_mapping_asset_edit() (includes/governance.php) renders the
    // "Mapped Assets" table inside BOTH control modals on this page exactly
    // as it does on the pre-redesign one -- an "+ Add Mapping" button, a
    // maturity dropdown per row, and an assets-asset-groups-select the user
    // picks assets/asset-groups in. What never got ported when this page
    // stopped loading js/simplerisk/pages/governance.js (Task 2) was the JS
    // half: the selectize initializer plus the add / remove / re-index
    // handlers. The visible result was a "Mapped Assets" label with nothing
    // usable beneath it -- no way to map an asset to a control from this page
    // at all, and Clone silently dropping whatever the source had mapped.
    //
    // The selectize configuration itself now lives ONCE, in
    // js/simplerisk/common.js (setupAssetsAssetGroupsSelectize()), which
    // js/simplerisk/pages/governance.js and js/simplerisk/pages/risk.js also
    // delegate to; this page calls its control-scoped wrapper rather than
    // carrying a third copy of it.

    // Re-index every asset row's select as assets_asset_groups[N][].
    //
    // Not cosmetic. display_add_asset_row() names the select
    // `assets_asset_groups[]`, and a <select multiple> serializes EVERY
    // picked value as another top-level `assets_asset_groups[]` element -- so
    // with two rows the server sees one flat list and cannot tell where row 0
    // ended. parse_control_mapped_assets_request() (includes/governance.php)
    // pairs `assets_asset_groups[N]` with `asset_maturity[N]` positionally,
    // so row N's assets must arrive under index N. Has to be redone after
    // every add AND every delete: removing a middle row otherwise leaves a
    // hole in the asset indexes that the maturity array does not have, and
    // every row below it silently re-pairs with the wrong maturity.
    function reindexAssetRows($scope) {
        $scope.find('.mapping_asset_table select.assets-asset-groups-select').each(function (index, element) {
            $(element).attr('name', 'assets_asset_groups[' + index + '][]');
        });
    }

    function toggleAssetRequiredMark($scope) {
        var hasRows = $scope.find('.mapping_asset_table tbody tr').length > 0;
        $scope.find('.mapping-asset-required-mark').toggleClass('d-none', !hasRows);
    }

    // Replace the asset rows of a modal with a server-rendered fragment
    // (getControlById()'s `mapped_assets`, includes/api.php) and make each one
    // live. `maturities` is the parallel control.mapped_maturity array the same
    // response carries -- (control_id, maturity) is the pair
    // get_assets_and_asset_groups_by_control_for_dropdown() resolves a row's
    // already-mapped assets from, so a row initialized with the wrong maturity
    // comes back pre-selected with a DIFFERENT row's assets.
    function fillAssetRows($modal, html, controlId, maturities) {
        var $tbody = $modal.find('.mapping_asset_table tbody');
        $tbody.html(html || '');
        reindexAssetRows($modal);
        $modal.find('.mapping_asset_table select.assets-asset-groups-select').each(function (index, element) {
            setupAssetsAssetGroupsWidgetForControl(
                $(element),
                controlId,
                maturities && maturities[index] !== undefined ? maturities[index] : 0
            );
        });
        toggleAssetRequiredMark($modal);
    }

    // A row the user just clicked "+ Add Mapping" for. Initialized with
    // (0, 0), NOT the real control id: a brand-new row has nothing mapped to
    // it, and passing the control's id plus this row's maturity would make the
    // options endpoint pre-select whatever another row at that same maturity
    // already holds -- silently duplicating a mapping the user never picked.
    function appendAssetRow($form) {
        var $row = $($('#add_asset_row table tr:first-child').parent().html())
            .appendTo($form.find('.mapping_asset_table tbody'));
        reindexAssetRows($form);
        setupAssetsAssetGroupsWidgetForControl($row.find('select.assets-asset-groups-select'), 0, 0);
        toggleAssetRequiredMark($form);
        return $row;
    }

    function submitControlAdd($form, $btn) {
        flushEditors();
        var $modal = $form.closest('.modal');
        setBusy($btn, true);
        $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/governance/controls',
            data: $form.serialize(),
            headers: csrfHeaders()
        }).done(function (res) {
            setBusy($btn, false);
            $modal.modal('hide');
            showAlertFromMessage(res.status_message, true);
            // Mapping table / clone banner / title are NOT reset here: the
            // show.bs.modal delegate (above) is the sole owner of that
            // decision and re-derives it correctly on whatever the NEXT
            // open turns out to be (plain add or another Clone), the same
            // way it would if this save had failed instead.
            resetForm('#add-control-form');
            if (typeof setEditorContent === 'function') {
                setEditorContent('add_control_description', '');
                setEditorContent('add_supplemental_guidance', '');
            }
            reloadTable();
            loadFrameworks();
        }).fail(function (xhr) {
            setBusy($btn, false);
            showModalError($modal, apiFailureMessage(xhr));
        });
    }

    function openControlForEdit(id) {
        $.ajax({ type: 'GET', url: BASE_URL + '/api/v2/governance/control?control_id=' + id, headers: csrfHeaders() })
            .done(function (res) {
                var control = (res.data || {}).control || {};
                var $modal = $('#control--update');
                resetForm('#update-control-form');

                $modal.find('[name=control_id]').val(id);
                $modal.find('[name=short_name]').val(control.short_name || '');
                $modal.find('[name=long_name]').val(control.long_name || '');
                $modal.find('[name=description]').val(control.description || '');
                $modal.find('[name=supplemental_guidance]').val(control.supplemental_guidance || '');
                $modal.find('[name=control_class]').val(Number(control.control_class) ? control.control_class : '');
                $modal.find('[name=control_phase]').val(Number(control.control_phase) ? control.control_phase : '');
                $modal.find('[name=control_owner]').val(Number(control.control_owner) ? control.control_owner : '');
                $modal.find('[name=control_number]').val(control.control_number || '');
                // The DB column is control_maturity (current); the desired
                // column is desired_maturity -- neither matches this form's
                // control_current_maturity/control_desired_maturity field
                // names, same split get_framework_control() has always had
                // (mirrored from the legacy edit-modal wiring this page used
                // to load via js/simplerisk/pages/governance.js).
                $modal.find('[name=control_current_maturity]').val(Number(control.control_maturity) ? control.control_maturity : 0);
                $modal.find('[name=control_desired_maturity]').val(Number(control.desired_maturity) ? control.desired_maturity : 0);
                $modal.find('[name=control_priority]').val(Number(control.control_priority) ? control.control_priority : '');
                $modal.find('[name=control_status]').val(control.control_status !== null && control.control_status !== undefined ? control.control_status : 2);
                $modal.find('[name=family]').val(Number(control.family) ? control.family : '');
                $modal.find('[name=mitigation_percent]').val(control.mitigation_percent || 0);

                // governance/index.php's ready handler always runs
                // .multiselect() on this select for both the Add and Update
                // copies, so the plugin API is available unconditionally by
                // the time a user can have triggered an edit -- same call
                // sequence js/simplerisk/pages/governance.js used for this
                // exact modal before Task 2 stopped loading that file.
                var $typeSelect = $modal.find('[name="control_type[]"]');
                var typeIds = control.control_type_ids ? String(control.control_type_ids).split(',') : [];
                $typeSelect.multiselect('deselectAll', false);
                $typeSelect.multiselect('select', typeIds);
                $typeSelect.multiselect('refresh');

                // Both mapping tables, Task 20. Pre-filling them is not a
                // convenience -- it is what makes the submission safe to treat
                // as authoritative. updateControlById() now REPLACES the
                // control's mappings with whatever this form sends, so a modal
                // that opened with empty tables would ask the server to delete
                // every mapping the control had the moment the user pressed
                // Save on an unrelated field.
                var data = res.data || {};
                $modal.find('.mapping_framework_table tbody').html(data.mapped_frameworks || '');
                $modal.find('.mapping-framework-required-mark')
                    .toggleClass('d-none', $modal.find('.mapping_framework_table tbody tr').length === 0);
                fillAssetRows($modal, data.mapped_assets, id, control.mapped_maturity);

                // Customization Extra fields. Prefilling them is what stops this
                // form's $form.serialize() submission from writing '' over every
                // stored custom value on an edit that only touched the name --
                // see the CUSTOM_FIELD_SELECTOR block for the full story.
                applyCustomFieldValues($modal, control.custom_values);

                if (typeof setEditorContent === 'function') {
                    setEditorContent('update_control_description', control.description || '');
                    setEditorContent('update_supplemental_guidance', control.supplemental_guidance || '');
                }

                $modal.modal('show');
            })
            .fail(function (xhr) { showAlertFromMessage(apiFailureMessage(xhr), false); });
    }

    function submitControlUpdate($form, $btn) {
        flushEditors();
        var $modal = $form.closest('.modal');
        var id = $form.find('[name=control_id]').val();
        setBusy($btn, true);
        $.ajax({
            type: 'PATCH',
            url: BASE_URL + '/api/v2/governance/controls/' + id,
            data: $form.serialize(),
            headers: csrfHeaders()
        }).done(function (res) {
            setBusy($btn, false);
            $modal.modal('hide');
            showAlertFromMessage(res.status_message, true);
            reloadTable();
            loadFrameworks();
        }).fail(function (xhr) {
            setBusy($btn, false);
            showModalError($modal, apiFailureMessage(xhr));
        });
    }

    function deleteControlById(id) {
        return $.ajax({ type: 'DELETE', url: BASE_URL + '/api/v2/governance/controls/' + id, headers: csrfHeaders() });
    }

    function submitControlDelete($form, $btn) {
        var $modal = $form.closest('.modal');
        var id = $form.find('[name=control_id]').val();
        setBusy($btn, true);
        deleteControlById(id).done(function (res) {
            setBusy($btn, false);
            $modal.modal('hide');
            showAlertFromMessage(res.status_message, true);
            reloadTable();
            loadFrameworks();
        }).fail(function (xhr) {
            setBusy($btn, false);
            showAlertFromMessage(apiFailureMessage(xhr), false);
        });
    }

    // ---- Control clone (Task 24) --------------------------------------------
    //
    // A one-click server-side duplicate was the first version of this
    // feature (POST .../clone, clone_framework_control()) but was reverted:
    // it created the row immediately with no review step, so the user had
    // no way to tell what had actually been cloned before it was already
    // sitting in the table. This version instead pre-fills the existing
    // #control--add modal -- the SAME modal "+ Add control" opens
    // (design-system.md §8: a modal never opens another modal) -- from the
    // source control, the same way the pre-redesign
    // js/simplerisk/pages/governance.js's clone handler did. Nothing is
    // created until the user reviews the fields (the sr-clone-banner names
    // the source control so they know what carried over) and clicks Save,
    // which goes through the normal submitControlAdd() create path.
    //
    // Framework mappings are pre-filled (the mapping_framework_table rows
    // the response's mapped_frameworks HTML fragment renders -- same markup
    // display_mapping_framework_edit() emits, same field names
    // createControlCrud() now actually persists, below). Asset mappings are
    // pre-filled too since Task 53: the assets-asset-groups-select widget
    // has a working initializer on this page now (setupAssetsAssetGroups*,
    // js/simplerisk/common.js), so the source control's mapped assets carry
    // over as live, editable rows rather than the inert ones that were the
    // reason this was scoped out originally.
    function openControlForClone(id) {
        $.ajax({ type: 'GET', url: BASE_URL + '/api/v2/governance/control?control_id=' + id, headers: csrfHeaders() })
            .done(function (res) {
                var data = res.data || {};
                var control = data.control || {};
                var $modal = $('#control--add');
                resetForm('#add-control-form');
                $modal.find('.mapping_framework_table tbody').empty();
                $modal.find('.mapping-framework-required-mark').addClass('d-none');

                $modal.find('[name=short_name]').val(control.short_name || '');
                $modal.find('[name=long_name]').val(control.long_name || '');
                $modal.find('[name=description]').val(control.description || '');
                $modal.find('[name=supplemental_guidance]').val(control.supplemental_guidance || '');
                $modal.find('[name=control_class]').val(Number(control.control_class) ? control.control_class : '');
                $modal.find('[name=control_phase]').val(Number(control.control_phase) ? control.control_phase : '');
                $modal.find('[name=control_owner]').val(Number(control.control_owner) ? control.control_owner : '');
                $modal.find('[name=control_number]').val(control.control_number || '');
                $modal.find('[name=control_current_maturity]').val(Number(control.control_maturity) ? control.control_maturity : 0);
                $modal.find('[name=control_desired_maturity]').val(Number(control.desired_maturity) ? control.desired_maturity : 0);
                $modal.find('[name=control_priority]').val(Number(control.control_priority) ? control.control_priority : '');
                $modal.find('[name=control_status]').val(control.control_status !== null && control.control_status !== undefined ? control.control_status : 2);
                $modal.find('[name=family]').val(Number(control.family) ? control.family : '');
                $modal.find('[name=mitigation_percent]').val(control.mitigation_percent || 0);

                var $typeSelect = $modal.find('[name="control_type[]"]');
                var typeIds = control.control_type_ids ? String(control.control_type_ids).split(',') : [];
                $typeSelect.multiselect('deselectAll', false);
                $typeSelect.multiselect('select', typeIds);
                $typeSelect.multiselect('refresh');

                if (data.mapped_frameworks) {
                    $modal.find('.mapping_framework_table tbody').html(data.mapped_frameworks);
                    $modal.find('.mapping-framework-required-mark').removeClass('d-none');
                }

                // Asset mappings carry over from the SOURCE control: the rows
                // are its rows, so the widgets are initialized against its id
                // and its per-row maturity -- that pair is what
                // get_assets_and_asset_groups_by_control_for_dropdown() uses
                // to decide which assets come back pre-selected. The clone
                // itself has no id yet; the picks ride along in the POST body
                // and createControlCrud() persists them against the new row.
                fillAssetRows($modal, data.mapped_assets, id, control.mapped_maturity);

                // Customization Extra fields carry over too -- same reasoning as
                // every other pre-filled field here: a clone is a create the
                // user reviews, so it has to show what the source control holds.
                applyCustomFieldValues($modal, control.custom_values);

                if (typeof setEditorContent === 'function') {
                    setEditorContent('add_control_description', control.description || '');
                    setEditorContent('add_supplemental_guidance', control.supplemental_guidance || '');
                }

                // Consumed by the show.bs.modal delegate below, which sets
                // the title/banner -- doing it there (not here) is what lets
                // that ONE handler also own resetting them back to blank for
                // every other way #control--add can be shown, present or
                // future, the same "single delegated handler" fix Task 8
                // used for the stale error banner.
                pendingCloneSourceName = control.short_name || '';

                $modal.modal('show');
            })
            .fail(function (xhr) { showAlertFromMessage(apiFailureMessage(xhr), false); });
    }

    // ---- Bulk delete (controls--delete, Task 54) ---------------------------
    //
    // ONE POST does the whole thing, twice: once to ASK what deleting this
    // selection means, and once to do it. POST /governance/controls/bulk-delete
    // accepts EITHER control_ids OR all_filtered + filters, resolves the second
    // through the controls table's own request pipeline, and reports the
    // resolved set's soft/hard split. Sending `confirm: true` is the only thing
    // that makes it write.
    //
    // TWO THINGS THIS REPLACED, both deliberately.
    //
    // 1. The escalated "Select all N filtered" case used to be REFUSED. That
    //    was honest at the time: the client holds one page (the table pages),
    //    there was no endpoint that deleted by filter, and the alternatives
    //    were enumerating the whole set by paging the table or silently
    //    deleting only the visible page. The endpoint resolving the filter
    //    server-side is what removed that choice -- the bar says 1,535 and the
    //    server deletes the 1,535 the same filter returns, because it is the
    //    same computation, not two that ought to agree.
    //
    // 2. It used to issue one DELETE per selected id in parallel and reconcile
    //    the results. That path had no transaction, so a failure mid-flight
    //    left a partial delete with no way back, and it needed careful
    //    settlement bookkeeping (NOT $.when.apply(), whose aggregate rejects on
    //    the first failure without waiting for the rest) purely to report the
    //    partial honestly. One transactional call has no partial to report: it
    //    either deletes the set or deletes nothing, and the toast states the
    //    server's own count either way. The honesty is now structural rather
    //    than reconciled after the fact.
    //
    // WHY A PREVIEW AT ALL. Deleting a control is TWO outcomes
    // (delete_framework_controls_batch(), includes/governance.php): a control
    // carrying test history is KEPT in a deleted state, a control with no tests
    // is REMOVED permanently. Which of those a selection means is a fact about
    // the rows, not about the click, so the confirmation has to ask the server
    // rather than assert something generic -- and it must ask before anything
    // is committed, because unlike the applicability write this is not
    // reversible.

    function controlsDeletePayload() {
        var payload = (typeof SRFrameworks !== 'undefined' && SRFrameworks.selectionPayload)
            ? SRFrameworks.selectionPayload()
            : { control_ids: [] };

        var body = {};
        // `framework` is OPTIONAL for delete and required for applicability.
        // Deleting a control removes it from every framework it is mapped to,
        // so the unscoped "All controls" view is a legitimate population --
        // which is exactly why the bulk bar renders Delete there and withholds
        // Set applicability. Sent only when there IS one, so the server can
        // tell "no framework scoped" from "framework 0".
        if (payload.framework !== null && payload.framework !== undefined) {
            body.framework = payload.framework;
        }
        if (payload.all_filtered) {
            body.all_filtered = true;
            body.filters = payload.filters;
        } else {
            body.control_ids = payload.control_ids || [];
        }
        return body;
    }

    function postControlsDelete(body) {
        return $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/governance/controls/bulk-delete',
            contentType: 'application/json',
            data: JSON.stringify(body),
            // A JSON body bypasses csrf-magic's <form> rewrite, so the token has
            // to travel in the CSRF-TOKEN header or the request comes back 403.
            headers: csrfHeaders()
        });
    }

    // The sentence the user confirms against. EVERY branch leads with the same
    // irreversible warning: a soft-deleted control (has_tests -> deleted = 1,
    // kept only to preserve its test history) is not recoverable -- nothing in
    // this product ever flips that flag back to 0, only test-history restore
    // exists. So there is no "kept" outcome from the user's side, only "will be
    // deleted" with or without an audit-history footnote. THREE templates
    // rather than one with zeroes substituted in: "0 are retained for audit
    // history" reads as a warning about something that is not going to happen,
    // and on a delete confirmation that is the kind of noise that trains people
    // to stop reading. Every number comes from the response -- never from
    // selectionCount(), which is the client's idea of the selection.
    function controlsDeleteSplitMessage(data) {
        var soft = data.soft_delete || 0;
        var hard = data.hard_delete || 0;
        var found = data.found || 0;

        if (found === 0) { return _lang['DeleteControlsPreviewNone']; }
        if (hard === 0)  { return formatTemplate(_lang['DeleteControlsPreviewKeptOnly'], { n: found }); }
        if (soft === 0)  { return formatTemplate(_lang['DeleteControlsPreviewRemovedOnly'], { n: found }); }
        return formatTemplate(_lang['DeleteControlsPreviewSplit'], { n: found, m: soft, k: hard });
    }

    // Runs on every open of #controls--delete. The Delete button stays disabled
    // until this lands, so the destructive verb is never clickable while the
    // sentence above it still says "Checking...".
    function renderControlsDeleteScope() {
        var $modal = $('#controls--delete');
        var $btn = $modal.find('[name=delete_controls]');
        var $split = $('#sr-ctl-delete-split');

        $btn.prop('disabled', true);
        $split.text(_lang['DeleteControlsPreviewChecking']);

        // No `confirm` key: this is the PREVIEW branch, and the endpoint
        // defaults to it. Nothing is written.
        return postControlsDelete(controlsDeletePayload()).done(function (res) {
            var data = res.data || {};
            $split.text(controlsDeleteSplitMessage(data));
            // Nothing there to delete is not an error, but it is not something
            // to offer a Delete button for either.
            $btn.prop('disabled', (data.found || 0) === 0);
        }).fail(function (xhr) {
            // The refusals that land here are real and worth showing: an
            // empty selection, a filter matching nothing, a resolved set over
            // the cap. The button stays disabled.
            $split.text('');
            showModalError($modal, apiFailureMessage(xhr));
        });
    }

    function submitControlsDelete($form, $btn) {
        var $modal = $form.closest('.modal');

        // The SAME payload the preview was computed from, plus the interlock.
        var body = controlsDeletePayload();
        body.confirm = true;

        setBusy($btn, true);
        postControlsDelete(body).done(function (res) {
            var data = res.data || {};
            setBusy($btn, false);
            $modal.modal('hide');
            // The server's own count of what it deleted -- never ids.length,
            // which for an escalated selection is a set the client never held.
            showAlertFromMessage(
                formatTemplate(_lang['ControlsDeletedResult'], { n: data.found || 0 }), true);
            reloadTable();
            loadFrameworks();
            // A delete changes what every insights tile measures, and
            // maybeRefreshBand() is gated on the framework changing -- which a
            // delete does not do. Same ungated form the bulk applicability
            // write uses, for the same reason.
            refreshBand();
        }).fail(function (xhr) {
            setBusy($btn, false);
            showModalError($modal, apiFailureMessage(xhr));
        });
    }

    // ---- Set applicability (applicability--set, Task 15; extended for the SoA
    // audit-readiness work) --------------------------------------------------
    //
    // ONE RULE SHAPES EVERY FUNCTION BELOW (SoA design §4):
    //
    //     ABSENCE OF A ROW MEANS APPLICABLE. A ROW IS NOT REQUIRED TO MEAN IT.
    //
    // Applicable is still the default and clearing still means deleting the
    // row. What changed is that an applicable control MAY carry a row of its
    // own: ISO/IEC 27001 clause 6.1.3(d) asks for a justification per control
    // for INCLUSION as much as for exclusion, and before this every applicable
    // control printed the framework's identical default_inclusion_justification.
    // Ninety identical sentences is what invites an auditor to ask whether
    // anyone considered the controls one at a time.
    //
    // So the Applicable radio is no longer a CLEAR. It is a clear only when the
    // user leaves both the reasons and the justification empty, and that
    // distinction lives on the SERVER (set_applicability() deletes when an
    // applicable decision has nothing to record). The client's job is to send
    // the truth about what the form holds.
    //
    //     ABSENT PRESERVES. EXPLICITLY EMPTY CLEARS.
    //
    // This is the half that is easy to get wrong and impossible to see in the
    // rendered page. POST /governance/applicability reads a field the body did
    // not mention as "say nothing about it" and KEEPS what is stored. So
    // resetting a control to the framework default is not `{state:
    // 'applicable'}` -- that request preserves the justification the user
    // believes they just removed. Every field is sent on every submit, empty
    // when the form is empty.
    //
    // Both DEVIATIONS still require a justification, which is what makes an
    // unjustified exclusion impossible by construction rather than something a
    // report has to go hunting for. Making it optional for inclusion is exactly
    // the change that could have softened that, so "offered" and "required" are
    // two separate attributes in the markup rather than one implied by the
    // other.

    // The reason picklist, keyed by the state it is offered for. The list is a
    // customer-extendable DB table (control_applicability_reason, the
    // control_class option-table pattern), so it is READ rather than hardcoded
    // -- but it cannot change while this page is open, and re-fetching on every
    // radio click would make the picklist flicker under the user's cursor.
    var applicabilityReasonCache = {};

    function loadApplicabilityReasons(state_token) {
        if (applicabilityReasonCache[state_token]) {
            return $.Deferred().resolve(applicabilityReasonCache[state_token]).promise();
        }
        return $.getJSON(BASE_URL + '/api/v2/governance/applicability/reasons',
                         { applies_to: state_token })
            .then(function (res) {
                var reasons = ((res.data || {}).reasons) || [];
                applicabilityReasonCache[state_token] = reasons;
                return reasons;
            });
    }

    // Labels go in with .text() only. These names are customer-authored rows,
    // exactly like the family / class / phase / priority labels this page
    // already renders, so nothing on this path touches .html().
    //
    // A CHECKBOX GROUP, not a <select>: reasons are multi-select since the join
    // table, and at four-to-six options per state every one of them fits on
    // screen at once (see the markup's note in governance/index.php for why not
    // bootstrap-multiselect). There is no placeholder row -- "none chosen" is
    // the state of the group itself, not an option in it.
    //
    // `preselect` is the round trip: the ids a stored decision already holds, so
    // reopening the modal on a decided control shows what it decided rather
    // than a blank form the user reads as their work having been lost.
    //
    // Each box's id is derived from the reason's own value, which is a primary
    // key in one table -- unique across all three states, and only one state's
    // list is ever in the DOM. Nothing else on the page uses that prefix, so
    // this cannot reintroduce the duplicate ids the Add/Edit modals were
    // de-duplicated of (a spec asserts the document has none).
    function renderApplicabilityReasons(state_token, preselect) {
        var $box = $('#appl-reason');
        if (!$box.length) { return; }

        var chosen = (preselect || []).map(Number);

        return loadApplicabilityReasons(state_token).then(function (reasons) {
            // A later radio click may have landed while this was in flight;
            // rendering its answer over the newer state would offer exclusion
            // reasons for an inheritance.
            if (currentApplicabilityState() !== state_token) { return; }

            $box.empty();

            reasons.forEach(function (reason) {
                var id = 'appl-reason-opt-' + reason.value;
                var $check = $('<div class="form-check">');
                $('<input type="checkbox" class="form-check-input" name="reason_ids">')
                    .attr('id', id)
                    .attr('value', reason.value)
                    .prop('checked', chosen.indexOf(Number(reason.value)) !== -1)
                    .appendTo($check);
                $('<label class="form-check-label">')
                    .attr('for', id)
                    .text(reason.name)
                    .appendTo($check);
                $box.append($check);
            });

            // The group's boxes only exist now, so the "at least one" rule can
            // only be applied now.
            syncApplicabilityReasonRequired();
        });
    }

    /**
     * The reason ids currently ticked.
     *
     * Read from the DOM at submit time rather than tracked in a variable: the
     * group is rebuilt on every state change, and a shadow copy of the
     * selection is the thing that would survive a rebuild it should not have.
     */
    function checkedApplicabilityReasonIds() {
        return $('#appl-reason input[type=checkbox]:checked').map(function () {
            return parseInt(this.value, 10);
        }).get().filter(function (id) { return !isNaN(id); });
    }

    /**
     * "At least one reason", expressed so the BROWSER enforces it.
     *
     * HTML `required` on a checkbox means THAT box must be ticked, which is not
     * the rule -- the rule is that the group must not be empty. The standard
     * spelling is to mark every box required while none is ticked and to lift it
     * from all of them as soon as one is, which makes the browser refuse the
     * submit and point its own bubble at the field.
     *
     * Cleared entirely when the field is hidden or the state does not demand a
     * reason: a `required` control inside a `d-none` field makes the browser
     * block the submit while pointing at something the user cannot see, which
     * reads as a form that has simply stopped working.
     */
    function syncApplicabilityReasonRequired() {
        var $field = applicabilityFieldOf('#appl-reason');
        var state_token = currentApplicabilityState();
        var demanded = applicabilityFieldRequires($field, state_token) && !$field.hasClass('d-none');
        var $boxes = $('#appl-reason input[type=checkbox]');

        $boxes.prop('required', demanded && $boxes.filter(':checked').length === 0);
        $field.find('.sr-qlabel .required').toggleClass('d-none', !demanded);
    }

    /** The .sr-qfield wrapper a modal control lives in -- what carries the two state attributes. */
    function applicabilityFieldOf(selector) {
        return $(selector).closest('[data-sr-appl-for]');
    }

    /** Whether a field's data-sr-appl-required names this state. */
    function applicabilityFieldRequires($field, state_token) {
        return ($field.attr('data-sr-appl-required') || '').split(' ').indexOf(state_token) !== -1;
    }

    function currentApplicabilityState() {
        return $('#applicability-set-form input[name=applicability_state]:checked').val() || 'applicable';
    }

    // Which fields the chosen state OFFERS, and which it REQUIRES -- driven
    // entirely off each field's own two data attributes rather than per-state
    // branches that could drift from the server's own
    // assert_applicability_requirements().
    //
    // The two questions became separate when the applicable path started
    // offering fields it does not demand. Deriving "required" from "offered",
    // as this did, would now make a justification mandatory for every
    // applicable control -- the filler the design specifically refuses.
    //
    // `required` is applied to the VISIBLE fields and removed from the hidden
    // ones on every change -- never baked into the markup. A required control
    // inside a `d-none` field makes the browser refuse the submit while
    // pointing its validation bubble at something the user cannot see, which
    // reads as a form that has simply stopped working.
    //
    // `preselect` carries a stored decision's reason ids through to the group
    // so a reopened modal shows them ticked.
    function applyApplicabilityState(state_token, preselect) {
        $('#applicability--set [data-sr-appl-for]').each(function () {
            var $field = $(this);
            var offered = $field.attr('data-sr-appl-for').split(' ').indexOf(state_token) !== -1;
            var demanded = offered && applicabilityFieldRequires($field, state_token);
            $field.toggleClass('d-none', !offered);
            $field.find('textarea, input[type=text]').prop('required', demanded);
            // The marker follows the RULE rather than sitting on the label
            // permanently and over-promising. The reason group's own marker is
            // owned by syncApplicabilityReasonRequired(), which has to re-run on
            // every tick, so it is skipped here rather than set twice.
            if (!$field.find('#appl-reason').length) {
                $field.find('.sr-qlabel .required').toggleClass('d-none', !demanded);
            }
        });

        var hints = {
            applicable: 'ApplicabilityApplicableHint',
            not_applicable: 'ApplicabilityNotApplicableHint',
            inherited: 'ApplicabilityInheritedHint'
        };
        $('#appl-state-hint').text(_lang[hints[state_token]] || '');

        // Emptied FIRST, then refilled asynchronously. The previous state's
        // boxes must not stay tickable for even a moment: a reason belongs to
        // exactly one state, and one that survived a state change would ride
        // along into a submit the server would (rightly) refuse.
        $('#appl-reason').empty();
        syncApplicabilityReasonRequired();

        renderApplicabilityReasons(state_token, preselect);
    }

    /**
     * What a stored decision should put back into the form on reopen, or null.
     *
     * ONLY IN ROW MODE. A bulk selection has no single stored decision to show:
     * prefilling it from any one member would state a decision about controls
     * the user never looked at, and the modal is about to write to all of them.
     * So bulk keeps opening on the default state with empty fields, which is
     * the honest reading of "one decision, for all of these".
     *
     * Read from the row the table already holds rather than re-fetched.
     * GET /governance/controls/table sends every applicability field with each
     * row, so a second request would only add a window in which the modal and
     * the grid could disagree about the same control.
     */
    function applicabilityPrefillFor(target) {
        if (!target || !target.single) { return null; }

        var row = rowById((target.control_ids || [])[0]);
        if (!row) { return null; }

        return {
            // resolve_applicability() already answered this for the row,
            // including the 'applicable' a control with no row resolves to.
            state: row.applicability || 'applicable',
            reason_ids: row.applicability_reason_ids || [],
            // Nullable server-side (an applicable control justified by its
            // reasons alone has no narrative), and a textarea holds a string.
            narrative: row.applicability_narrative || '',
            provider: row.applicability_provider || ''
        };
    }

    // ---- WHAT AM I ACTING ON? (Task 60) ------------------------------------
    //
    // #applicability--set now has TWO entry points -- the selection bulk bar
    // (Task 15) and the per-row action -- and exactly ONE of them may be right
    // about the target at any moment. Before this task the modal derived its
    // target from selectionPayload() / selectionCount() directly, the way
    // #controls--delete still does, which is correct for a bulk bar and
    // catastrophic for a row: a row action that merely opened the modal would
    // have written to whatever checkboxes happened to be ticked, and under
    // "Select all N filtered" that is every control in the framework, from a
    // click the user believed applied to one row. Silently doing MORE than
    // asked is the same class of failure Task 15 fixed in the other direction
    // (a bar claiming 1,535 while 25 rows were written), and it is the worse
    // direction.
    //
    // So the target is RESOLVED ONCE PER OPEN, into one object, and both the
    // copy the user reads and the request the form sends are derived from that
    // same object. There is no path by which the modal can say one thing and
    // do another, because there is no second place either of them can look.
    //
    // Row mode ignores `selection` and `selectAllFiltered` entirely, and writes
    // to neither -- opening the modal from a row neither reads nor disturbs the
    // checkbox selection, and the bulk bar is still showing the same count when
    // it closes.
    function resolveApplicabilityTarget() {
        if (pendingApplicabilityControl) {
            return {
                single: true,
                name: pendingApplicabilityControl.name,
                count: 1,
                framework: state.framework,
                control_ids: [pendingApplicabilityControl.id]
            };
        }

        // selectionPayload() and selectionCount() read the same
        // `selectAllFiltered` flag, which is what keeps the bar's number and
        // the written set in step (Task 15).
        var payload = SRFrameworks.selectionPayload();
        return {
            single: false,
            count: selectionCount(),
            framework: payload.framework,
            control_ids: payload.control_ids,
            all_filtered: payload.all_filtered,
            filters: payload.filters
        };
    }

    // How many controls are selected BESIDES the one a row action is about to
    // write to (Task 63) -- the number the row-scoped note promises is
    // unaffected, and therefore a number that must exclude the target itself.
    //
    // The target is very often ticked too: a user who selected a page and then
    // reached for one row's own action is the ordinary case. "the 3 controls you
    // have selected are not affected" while one of those 3 is the control being
    // written is the same class of lie as a bar that miscounts, so the target is
    // subtracted whenever it is in the set. Under a standing "Select all N
    // filtered" it is in the set by construction -- the row is rendered by the
    // very filter the escalation resolves against.
    //
    // Falling to 0 is a real outcome (only the target ticked), and the caller
    // prints the plain sentence for it rather than a sentence about zero
    // controls.
    //
    // READ FOR COPY, never for the target. selectionCount() is the same function
    // the bulk bar's own number comes from, so the two cannot describe different
    // selections.
    function otherSelectedCount(target) {
        var n = selectionCount();
        if (n === 0) { return 0; }
        var targetId = (target.control_ids || [])[0];
        var targetSelected = selectAllFiltered || selection.has(targetId);
        return targetSelected ? n - 1 : n;
    }

    // The amber scope note, in two sentences answering two different questions:
    // which framework the decision belongs to, and which controls are about to
    // receive it -- plus the title, which names the same thing the second
    // sentence does.
    //
    // The population sentence is the one that must not lie, and it now has
    // three spellings rather than two because there are three populations. The
    // header checkbox selects the CURRENT PAGE (the only page the client
    // holds); "Select all N" escalates to the whole filtered result set, which
    // the server resolves from the same filter the table was built with; and a
    // row action targets exactly one control, whatever else is ticked. All
    // three are read off the ONE resolved target, so the sentence cannot
    // describe a different set than the submit sends.
    //
    // Everything goes in with .text(): both a framework name and a control
    // name are user-authored.
    function renderApplicabilityScope() {
        var $heading = controlsPaneHeading();
        // controlsPaneHeading() carries the SCF chip as a child element; the
        // name alone is this <h2>'s own first text node.
        var frameworkName = $heading.contents().filter(function () { return this.nodeType === 3; }).text();
        var target = applicabilityTarget || resolveApplicabilityTarget();

        // Title names the specific object when there is one to name
        // (design-system.md §8) -- a row action that opened a modal titled only
        // "Set applicability", above a form that could equally have meant the
        // selection, is exactly the ambiguity this task exists to remove.
        $('#applicability--set-title').text(target.single
            ? formatTemplate(_lang['SetApplicabilityForControl'], { name: target.name })
            : _lang['SetApplicability']);

        $('#appl-scope-framework').text(
            formatTemplate(_lang['ApplicabilityScopeNote'], { framework: frameworkName })
        );

        if (target.single) {
            // Two spellings, not one (Task 63). The row form used to end
            // "...only, whatever else is selected" on EVERY row-action open, so
            // with nothing else ticked it reassured the user about a situation
            // that was not occurring -- and read as a truncated sentence,
            // because the clause had no antecedent to point at. Same "do not
            // print a sentence with a zero in it" discipline Task 54 applied to
            // the delete preview: the reassurance is printed only where there is
            // something to be reassured about.
            //
            // Where there IS a competing selection the second form earns its
            // place outright: it is the visible half of the guarantee Task 60
            // built, and the only thing that tells a user their standing "Select
            // all N filtered" is not about to be overwritten by a click they
            // made on one row.
            //
            // COPY ONLY. The target is still resolveApplicabilityTarget()'s and
            // nothing here feeds it -- Task 60's whole point is that the target
            // is resolved independently of the selection, so the note and the
            // write cannot disagree. otherSelectedCount() is read for the
            // sentence and for nothing else.
            var others = otherSelectedCount(target);
            $('#appl-scope-population').text(others > 0
                ? formatTemplate(_lang['ApplicabilityAppliesToControlNotSelection'],
                    { name: target.name, n: others })
                : formatTemplate(_lang['ApplicabilityAppliesToControl'], { name: target.name }));
            return;
        }

        $('#appl-scope-population').text(formatTemplate(
            _lang[target.all_filtered ? 'ApplicabilityAppliesToAllFiltered' : 'ApplicabilityAppliesToSelected'],
            { n: target.count }
        ));
    }

    function submitApplicabilitySet($form, $btn) {
        var $modal = $form.closest('.modal');
        // The SAME object renderApplicabilityScope() wrote the note and the
        // title from, resolved once when the modal opened. Re-deriving the
        // target here is what would let the two disagree -- a selection change
        // behind an open modal, or a second entry point that forgot the rule.
        // The || is unreachable through the UI (show.bs.modal always resolves)
        // and is there so a programmatic open cannot submit against nothing.
        var target = applicabilityTarget || resolveApplicabilityTarget();
        var state_token = currentApplicabilityState();

        // Defence in depth: neither trigger is rendered under "All controls"
        // at all, so this is unreachable through the UI -- but a decision with
        // no framework to belong to must never be attempted rather than sent
        // and rejected.
        if (target.framework === null || target.framework === undefined) {
            showModalError($modal, _lang['RequestFailed']);
            return;
        }

        // EXPLICIT EMPTINESS, NEVER OMISSION. The endpoint reads an absent field
        // as "say nothing about it" and PRESERVES what is stored; only an
        // explicit '' / [] clears. Every field is therefore sent on every
        // submit, empty when the form is empty -- which is what makes an
        // Applicable save with nothing filled in a RESET to the framework
        // default rather than a request that quietly keeps the justification
        // the user believes they just removed.
        //
        // A LIST for the reasons, because they are multi-select server-side, and
        // read from the checkboxes at this moment rather than from any tracked
        // copy of the selection.
        var body = {
            framework: target.framework,
            state: state_token,
            reason_ids: checkedApplicabilityReasonIds(),
            narrative: $('#appl-narrative').val() || '',
            // A provider belongs to an inheritance and to nothing else. Sent as
            // an explicit '' for the other two states so that changing a control
            // from inherited to applicable clears the provider it used to name,
            // instead of leaving a managed-service name attached to a decision
            // that no longer mentions one.
            provider: state_token === 'inherited' ? ($('#appl-provider').val() || '') : ''
        };

        // Unlike bulk DELETE -- which has no filter-aware endpoint and honestly
        // refuses the escalated case -- POST /governance/applicability resolves
        // `all_filtered` itself, through the controls table's own request
        // pipeline. A row target is never escalated: resolveApplicabilityTarget()
        // leaves all_filtered unset in single mode, so a row action cannot
        // inherit an escalation the user made for something else.
        if (target.all_filtered) {
            body.all_filtered = true;
            body.filters = target.filters;
        } else {
            body.control_ids = target.control_ids || [];
            if (!body.control_ids.length) {
                $modal.modal('hide');
                return;
            }
        }

        setBusy($btn, true);
        $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/governance/applicability',
            contentType: 'application/json',
            data: JSON.stringify(body),
            // A JSON body bypasses csrf-magic's <form> rewrite, so the token has
            // to travel in the CSRF-TOKEN header or the request comes back 403.
            headers: csrfHeaders()
        }).done(function (res) {
            setBusy($btn, false);
            $modal.modal('hide');

            // The COUNT the server actually acted on -- never the client's own
            // idea of the selection size. For an escalated "Select all N" the
            // client never held those ids, and for a clear only the controls
            // that really carried a decision were removed.
            var n = (res.data || {}).updated;
            // "Reset to applicable" only when there was nothing to record --
            // the same test the server deletes on. An applicable control that
            // DID carry a justification was recorded, not cleared, and a toast
            // saying otherwise would tell the user their justification was
            // discarded at the moment it was saved.
            var cleared = state_token === 'applicable'
                       && body.reason_ids.length === 0
                       && body.narrative === '';
            showAlertFromMessage(formatTemplate(
                _lang[cleared ? 'ApplicabilityClearResult' : 'ApplicabilitySetResult'],
                { n: n }
            ), true);

            // The table, the applicability facet and the Excluded tile all read
            // the same decisions, so they are refreshed TOGETHER: reloadTable()
            // re-runs the current filter (which may itself be an applicability
            // facet, so rows can legitimately leave the view), and refreshBand()
            // re-counts the tiles that the framework-gated maybeRefreshBand()
            // would otherwise have skipped. No manual refresh, and no window in
            // which a tile and the grid disagree about the same population.
            reloadTable();
            refreshBand();
        }).fail(function (xhr) {
            setBusy($btn, false);
            // Stays open with the message inline -- the user's typed
            // justification is never thrown away by a failed save.
            showModalError($modal, apiFailureMessage(xhr));
        });
    }

    // ---- Destructive-confirm titles (design-system.md §8: "Title is the
    // message... destructive names the specific object") -----------------
    function setDeleteTitle($modal, template, vars) {
        $modal.find('.modal-title').text(formatTemplate(template, vars));
    }

    $(function () {
        readUrl();
        renderThead();

        // Re-decide the responsive tier whenever the room the pane has changes
        // (Task 45). renderTable() covers changes in what the table CONTAINS;
        // this covers changes in what it FITS INTO, and relocating Family/Owner
        // between the toolbar and the filter sheet on a tier flip -- which the
        // old matchMedia 'change' listener did -- rides along inside
        // evaluateResponsiveTiers(), because the fold and the fit are one
        // condition.
        //
        // A ResizeObserver on the card rather than a window resize listener:
        // the pane also changes width when the sidebar collapses or expands and
        // when the user zooms or changes their font size, none of which fire a
        // window resize. That is also why the old viewport threshold carried a
        // 250px expanded-sidebar assumption it could not check -- observing the
        // element removes the assumption instead of restating it. The window
        // listener stays as the fallback for anything without ResizeObserver;
        // both routes coalesce through the same one-per-frame scheduler, so
        // having both costs nothing.
        var ctlCard = document.getElementById('sr-ctl-table');
        if (ctlCard && typeof window.ResizeObserver !== 'undefined') {
            new window.ResizeObserver(scheduleTierEval).observe(ctlCard);
        }
        window.addEventListener('resize', scheduleTierEval);
        // The virtual list's viewport is measured from the room the shell has
        // (virtSyncViewport()), so it moves with the same events the tier does.
        window.addEventListener('resize', function () { virtSyncViewport(); virtScheduleRender(); });

        // The scroll driver for virtual mode (Task 47). Bound to the static
        // scroller, which governance/index.php ships and nothing ever replaces,
        // so this survives every table rebuild.
        //
        // Menus close on scroll, unconditionally and before anything is
        // recycled. orientRowActionMenu() flips a menu by measuring the
        // scroller's edges ONCE, at open time, so a menu left standing while the
        // rows move under it is mis-oriented by construction -- and its row can
        // be recycled out from under it entirely. Closing is the behaviour a
        // popover in a scroll container wants anyway; it is not a workaround.
        var $ctlScroll = $('#sr-ctl-table .sr-table-scroll');
        $ctlScroll.on('scroll', function () {
            if (virt.adjusting) { return; }   // our own anchor correction, not the user
            if ($('.sr-row-actions-wrap.is-open').length) { closeRowActionMenus(); }
            virtScheduleRender();
        });

        $('#sr-fw-status').on('change', function () { state.status = $(this).val(); loadFrameworks(); });

        // Framework rail search (Task 22) -- client-side only (the rail is
        // tens of rows loaded once already, never worth a round trip), so
        // this just re-runs renderRail() against the cached railFrameworks
        // list. No debounce: filtering an in-memory array of this size is
        // effectively free, unlike the control table's server round trip.
        $(document).on('input', '#sr-fw-search', function () {
            railSearch = $(this).val();
            renderRail();
        });

        // "Clear search" (Task 22) -- the search-caused variant of the
        // shared #sr-fw-filtered tile's action (showFrameworksEmptyState()'s
        // own comment above). Clears both the box and the cached search
        // state, then re-renders straight from the already-loaded list --
        // no refetch needed, the data never changed.
        $(document).on('click', '#sr-fw-clear-search', function () {
            railSearch = '';
            $('#sr-fw-search').val('').trigger('focus');
            renderRail();
        });

        $(document).on('click keydown', '#sr-fw-list .sr-fw-item', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
            // A key pressed on a control INSIDE the row -- the overflow toggle,
            // or an action in the menu it pops -- belongs to that control, not
            // to the row. Without this the row's preventDefault() below
            // swallows the native activation Enter/Space produces on a
            // <button> and selects the framework instead, which made every
            // rail action keyboard-unreachable (the click path was already
            // covered, by each action's own stopPropagation()).
            if ($(e.target).closest('.sr-row-actions-wrap').length) { return; }
            e.preventDefault();
            var raw = $(this).attr('data-sr-fw');
            selectFramework(raw === '' ? null : parseInt(raw, 10));
        });
        // Row-actions overflow (the compact tier and any no-hover pointer,
        // design-system.md 6b) -- same behaviour compliance-define-tests.js
        // implements, for both of this page's surfaces at once.
        //
        // Delegated from the two STATIC containers rather than from `document`,
        // exactly as Define Tests delegates from its own $tbody: the toggle
        // handler has to stopPropagation() -- the rail's <li> and the control
        // table's <tr> both carry their own click handlers, and opening a menu
        // is not asking to select a framework or expand a row -- and a
        // stopPropagation() from a container-delegated handler is what reliably
        // keeps the document-level "close everything" handler below from
        // shutting the menu in the same click that opened it. Both containers
        // are emptied and refilled on every render, never replaced, so a
        // delegated binding survives.
        $('#sr-ctl-tbody, #sr-fw-list').on('click', '.sr-row-actions-toggle', function (e) {
            e.stopPropagation();
            var $wrap = $(this).closest('.sr-row-actions-wrap');
            var wasOpen = $wrap.hasClass('is-open');
            // Close first, unconditionally: opening one row's menu closes any
            // other row's, and a second click on the same toggle just closes.
            closeRowActionMenus();
            if (!wasOpen) {
                $wrap.addClass('is-open').find('.sr-row-actions-toggle').attr('aria-expanded', 'true');
                orientRowActionMenu($wrap);
            }
        });
        // Anywhere else -- including an action inside the menu, which does its
        // own thing and should leave the menu shut behind it.
        $(document).on('click.srrowactions', function () {
            closeRowActionMenus();
        });
        $(document).on('keydown.srrowactions', function (e) {
            if (e.key !== 'Escape') { return; }
            // Focus goes back to the toggle that opened the menu, not to
            // wherever Escape happened to be pressed -- otherwise a keyboard
            // user who tabbed into the menu is left with focus on a button
            // that is now display:none, and the browser drops them to the top
            // of the document.
            var $toggle = $('.sr-row-actions-wrap.is-open').find('.sr-row-actions-toggle');
            if (!$toggle.length) { return; }
            closeRowActionMenus();
            $toggle.trigger('focus');
        });

        $(document).on('click keydown', '#sr-ctl-thead th.sr-sortable', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
            e.preventDefault();
            applySort($(this));
        });
        $(document).on('sr:controls-loaded', function (e, st) { renderTable(st); });
        $(document).on('sr:controls-loaded', updateAllFrameworksBadge);
        $(document).on('sr:controls-loaded', function (e, st) {
            renderToolbar(st);
            maybeRebuildFilterOptions(st);
            maybeRefreshBand(st);
        });

        // Free-text search -- debounced so typing doesn't fire a request per
        // keystroke. Delegated (not bound to a cached element) because
        // renderToolbar() recreates the input on every table reload.
        var textTimer = null;
        $(document).on('input', '.sr-table-search', function () {
            var v = $(this).val();
            clearTimeout(textTimer);
            textTimer = setTimeout(function () {
                filters.text = v;
                reloadFirstPage();
            }, 300);
        });

        // "Filters · n" toggles the sheet open/closed. Task 5 (responsive
        // ladder) decides when this collapses by width -- here it's a plain
        // click toggle at every width.
        $(document).on('click', '#sr-ctl-filters', function (e) {
            e.preventDefault();
            var $sheet = $('#sr-ctl-filter-sheet');
            var isHidden = $sheet.is('[hidden]');
            $sheet.prop('hidden', !isHidden);
            $(this).attr('aria-expanded', isHidden ? 'true' : 'false');
        });

        // Any facet select in the sheet changing narrows the table. Delegated
        // on the shared class since every select is (re)built by
        // appendFacetField()/populateFacetSelect().
        $(document).on('change', '.sr-ctl-filter-select', function () {
            var facet = $(this).attr('data-facet');
            var val = $(this).val();
            filters[facet] = val ? val.slice() : [];
            // Status, Maturity and Applicability are the sheet facets that
            // round-trip through the URL -- all three are part of the insights
            // band's own deep-link vocabulary, so a tile link and a hand-set
            // facet have to produce the same URL. The others
            // (family/owner/class/phase/priority/type) stay session-only, same
            // as before.
            if (facet === 'status' || facet === 'maturity' || facet === 'applicability') { writeUrl(); }
            reloadFirstPage();
        });

        // Rows-per-page (Task 46). Server-rendered and never rebuilt, but
        // delegated like every other handler on this page for consistency.
        // A page-size change moves every row boundary, so it goes back to
        // page one through the same helper the filters use.
        $(document).on('change', '#sr-ctl-length', function () {
            var raw = $(this).val();
            // "All" (Task 47) is kept as the string the <option> carries rather
            // than encoded as a number, so the select's value, state.length and
            // anything that later puts the page size in the URL all spell it the
            // same way -- and so parseInt() cannot quietly turn it into a page
            // size of 0 or NaN.
            state.length = lengthIsAll(raw) ? 'all' : (parseInt(raw, 10) || 25);
            // Leaving virtual mode has to hand the scroller its height back
            // before the reload renders into it, or the bounded region survives
            // as a short window over a 25-row page.
            if (!lengthIsAll(raw)) {
                virt.on = false;
                virtSyncViewport();
            }
            reloadFirstPage();
        });

        // Row drawer (Task 6) -- delegated because renderTable() rebuilds
        // #sr-ctl-tbody (and every .sr-group-caret in it) on every reload.
        // The icon itself rotates via CSS keyed off [aria-expanded="true"]
        // (_governance-frameworks.scss) -- no .text()/glyph swap needed here.
        $(document).on('click', '.sr-group-caret', function () {
            var $btn = $(this);
            var $row = $btn.closest('tr');
            var id = $row.attr('data-sr-ctl');
            var $existing = $('#sr-ctl-tbody').find('tr[data-sr-drawer="' + id + '"]');

            if ($existing.length) {
                $existing.remove();
                $btn.attr('aria-expanded', 'false').removeAttr('aria-controls');
                // Virtual mode (Task 47): expansion is CLIENT STATE, not just a
                // node. A drawer whose row scrolls out of the window is dropped
                // with it and rebuilt when the row comes back, so the set of
                // expanded controls has to live outside the DOM -- and the height
                // model has to be told, because a collapsed row is shorter and
                // every offset below it moves.
                if (virt.on) {
                    delete virt.expanded[parseInt(id, 10)];
                    virtScheduleRender();
                }
                return;
            }

            var c = rowById(id);
            if (!c) { return; }
            var $drawer = renderDrawer(c);
            $row.after($drawer);
            $btn.attr({ 'aria-expanded': 'true', 'aria-controls': $drawer.attr('id') });
            if (virt.on) {
                virt.expanded[parseInt(id, 10)] = true;
                // Adopt the drawer that was just inserted so the next render
                // pass recycles it with its row instead of building a second
                // one beside it.
                var idx = virtIndexOfRow($row[0]);
                if (idx !== null && virt.rendered[idx]) { virt.rendered[idx].$drawer = $drawer; }
                virtScheduleRender();
            }
        });

        // #sr-ctl-empty-clear (Task 9) is the "no results" empty tile's own
        // Clear filters action -- same reset, reached from inside the empty
        // state rather than the toolbar's Filters sheet.
        $(document).on('click', '#sr-ctl-clear-filters, #sr-ctl-empty-clear', function (e) {
            e.preventDefault();
            filters = emptyFilters();
            // Not scoped to the sheet -- Family/Owner are inline in the
            // toolbar now, so ".sr-ctl-filter-select" spans both containers.
            $('.sr-ctl-filter-select').each(function () {
                $(this).val([]);
                srSelectRender($(this));
            });
            $('#sr-ctl-filter-sheet').prop('hidden', true);
            $('#sr-ctl-filters').attr('aria-expanded', 'false');
            // controlsViewIsNarrowed() now counts the rail's framework
            // selection alongside the sheet/search filters (Task 9 review
            // fix) -- so "Clear filters" has to clear that too, or the "no
            // results" tile's own Clear filters button would reload against
            // the SAME zero-control framework and land right back on the
            // tile it just claimed to clear.
            state.framework = null;
            // Clearing the framework also retires the Applicability facet --
            // the same rule selectFramework() applies, called from here too so
            // the two paths out of a scoped view can't leave the sheet in
            // different states.
            syncApplicabilityFacet();
            writeUrl();
            highlightRail();
            reloadFirstPage();
        });

        // #sr-ctl-empty-retry (Task 9) -- the "couldn't load" tile's Retry
        // action re-issues the exact same request that failed.
        $(document).on('click', '#sr-ctl-empty-retry', function () {
            reloadTable();
        });

        // #sr-fw-retry (Task 9) -- the framework rail's own "couldn't load"
        // Retry action.
        $(document).on('click', '#sr-fw-retry', function () {
            loadFrameworks();
        });

        // #sr-fw-view-active (Task 9 review fix) -- the "no frameworks match
        // this status" tile's way back: reset the status dropdown to the
        // default Active and reload.
        $(document).on('click', '#sr-fw-view-active', function () {
            state.status = '1';
            $('#sr-fw-status').val('1');
            loadFrameworks();
        });

        // Row selection (Task 7) -- delegated because renderTable() rebuilds
        // #sr-ctl-tbody (and every .sr-ctl-check in it) on every reload.
        //
        // Unchecking a row always retires the "Select all N" escalation, even
        // though `selection` itself only ever tracks page ids: once the user
        // deselects anything, the set they're now looking at is no longer
        // "every filtered row" (that's exactly what `selectAllFiltered` means),
        // so leaving the flag set here would silently widen a later single-row
        // selection back out to the whole filtered result set (post-review
        // fix -- see selectionPayload()'s test coverage for the regression).
        $(document).on('change', '.sr-ctl-check', function () {
            var id = parseInt($(this).val(), 10);
            if (this.checked) {
                selection.add(id);
            } else {
                // Unticking a row while "every filtered control" is in force
                // asks for a set this client cannot express (Task 47): under a
                // standing escalation the selection is a FILTER, not a list, and
                // in virtual mode the ids of the 1,532 rows the user is not
                // looking at were never fetched -- so "all of them except this
                // one" cannot be enumerated, and pretending otherwise would send
                // the server a set that silently stops at the loaded chunks.
                // The escalation is dropped whole instead, through the same path
                // the Clear (x) uses, so the bar and the rows agree: nothing is
                // selected. Retiring the flag while leaving the other rendered
                // ticks standing was the alternative, and that IS the lie.
                //
                // VIRTUAL MODE ONLY. In paged mode the escalation is retired the
                // way it always was, because there the ids of every row the user
                // can see ARE in `selection` (the header checkbox put them
                // there), so "this page minus this row" is a real, enumerable
                // set and dropping it would throw away a selection the user
                // built. That distinction is the whole difference: one mode holds
                // its page, the other holds a window.
                if (virt.on && selectAllFiltered) {
                    clearSelection();
                    return;
                }
                selection.delete(id);
                selectAllFiltered = false;
            }
            $(this).closest('tr').toggleClass('sr-row-checked', this.checked);
            syncSelection();
        });

        // Header checkbox selects/deselects the CURRENT PAGE only -- it's
        // the only page the client holds. "Select all N" (below) is the
        // escalation to the whole filtered result set. Unchecking it shrinks
        // the selection to nothing, which -- same reasoning as the per-row
        // handler above -- must retire any standing escalation too.
        $(document).on('change', '#sr-ctl-check-all', function () {
            var checked = this.checked;
            if (!checked) { selectAllFiltered = false; }
            // VIRTUAL mode (Task 47): the "current page" IS the whole filtered
            // result set, so ticking this box means every filtered control --
            // which is precisely what `selectAllFiltered` already meant, and it
            // is resolved server-side against the same filter the table was
            // built from. Escalating here rather than enumerating ids is the
            // point: the client holds a window, so a list of ids WOULD have been
            // "all rendered", and a header checkbox that silently means that is
            // the failure this branch exists to prevent. Every rendered
            // checkbox is ticked too, so the escalation is visible in the rows
            // and not only in the bulk bar's number.
            if (virt.on) {
                selectAllFiltered = checked;
                if (!checked) { selection.clear(); }
                $('#sr-ctl-tbody tr:not(.sr-ctl-probe) .sr-ctl-check').each(function () {
                    $(this).prop('checked', checked).closest('tr').toggleClass('sr-row-checked', checked);
                });
                syncSelection();
                return;
            }
            $('#sr-ctl-tbody tr:not(.sr-ctl-probe) .sr-ctl-check').each(function () {
                var id = parseInt($(this).val(), 10);
                $(this).prop('checked', checked).closest('tr').toggleClass('sr-row-checked', checked);
                if (checked) { selection.add(id); } else { selection.delete(id); }
            });
            syncSelection();
        });

        $(document).on('click', '#sr-ctl-select-all-filtered', function () {
            selectAllFiltered = true;
            renderBulkBar();
        });

        $(document).on('click', '#sr-ctl-clear-sel', clearSelection);

        // Selection does NOT survive a reload -- ids off the current page
        // can't be shown as checked, and a hidden selection is how bulk
        // actions surprise people. reloadTable()'s .fail() branch says the same
        // thing for the reload that never arrived (Task 62).
        $(document).on('sr:controls-loaded', clearSelection);

        // ===== CRUD wiring (Task 8): triggers + submits =====================

        // Clears a stale .sr-modal-inline-error on every open, for every
        // sr-modal on this page (all 7 carry the class) -- ONE mechanism
        // instead of a clearModalError() call in each individual open
        // handler, so a future 8th modal (or a trigger this file doesn't
        // know about, e.g. a plain data-bs-toggle button) can't reintroduce
        // a stale-banner-on-reopen bug by forgetting to call it. Fixed
        // post-review: framework--add opens via native data-bs-toggle (no
        // JS open-handler to attach a clear to) and controls--delete's own
        // show.bs.modal handler below only set the title, never cleared the
        // banner -- both left a stale error (e.g. a prior 409, or a prior
        // "Select all filtered" refusal that a narrowed selection would now
        // pass) visible on reopen.
        $(document).on('show.bs.modal', '.sr-modal', function () {
            clearModalError($(this));
        });

        // #control--add serves both "+ Add control" (blank) and Clone
        // (pre-filled) -- Task 24 review finding: a modal titled "New
        // Control" with mysteriously populated fields "looks like a bug, or
        // like the form remembered stale input" (the exact complaint this
        // whole feature exists to fix, just moved from "no clone at all" to
        // "clone with no indication it's a clone"). One delegated handler,
        // same shape as the .sr-modal-inline-error clearing above, decides
        // on every open which framing applies -- so a future trigger for
        // this modal can't reintroduce either a stale "Clone of ..." title
        // on a blank add (if it forgets to clear) or a silently-blank title
        // on a clone (if it forgets to set): there is exactly one place this
        // decision is made, not one per trigger.
        //
        // Can't just "always clear on show" the way clearModalError() does:
        // openControlForClone() populates the form and sets
        // pendingCloneSourceName BEFORE calling modal('show'), so a blanket
        // clear here would erase the very framing Clone just asked for,
        // immediately before the modal becomes visible.
        $(document).on('show.bs.modal', '#control--add', function () {
            var $modal = $(this);
            if (pendingCloneSourceName !== null) {
                $('#control--add-title').text(formatTemplate(_lang['CloneOfControlTitle'], { name: pendingCloneSourceName }));
                $modal.find('.sr-clone-banner')
                    .text(formatTemplate(_lang['ClonedFromControlNotice'], { name: pendingCloneSourceName }))
                    .removeClass('d-none');
                pendingCloneSourceName = null;
            } else {
                $('#control--add-title').text(_lang['NewControl']);
                $modal.find('.sr-clone-banner').addClass('d-none').text('');
                $modal.find('.mapping_framework_table tbody').empty();
                $modal.find('.mapping-framework-required-mark').addClass('d-none');
                // Same reasoning for the asset rows (Task 53). Emptying the
                // tbody discards the selectize controls with it -- they are
                // rendered inside the row they belong to, so there is nothing
                // left behind to destroy separately.
                $modal.find('.mapping_asset_table tbody').empty();
                $modal.find('.mapping-asset-required-mark').addClass('d-none');
            }
        });

        // #framework--add serves both "+ Add framework" (blank) and Clone
        // (pre-filled), so it gets the same single-delegate treatment
        // #control--add has above, for the same Task 24 review finding: a modal
        // titled "New Framework" over mysteriously populated fields reads as a
        // bug. ONE place decides which framing applies, so neither of the two
        // native data-bs-toggle triggers (#sr-fw-add, #sr-fw-empty-add -- there
        // is no JS open-handler to hang a clear on) nor any future trigger can
        // leave a stale "Clone of ..." title on a plain add or a silently blank
        // one on a clone.
        //
        // Registered here, in $(function(){}), which is what puts it AFTER
        // governance/index.php's own inline show.bs.modal handler for this
        // modal -- that one binds during body parse, and jQuery fires delegated
        // handlers on the same element in binding order. It matters: that
        // handler injects the parent-framework <select> (async: false), so this
        // is the first moment a clone's parent can be selected at all.
        $(document).on('show.bs.modal', '#framework--add', function () {
            var $modal = $(this);
            var clone = pendingCloneFramework;
            pendingCloneFramework = null;

            // The modal's own copy, which submitFrameworkAdd() reads. Parked on
            // the element rather than left in the closure variable so a submit
            // can never read a clone that a LATER open has already replaced.
            $modal.data('srCloneFramework', clone);

            if (clone) {
                $('#framework--add-title').text(formatTemplate(_lang['CloneOfFrameworkTitle'], { name: clone.name }));
                $modal.find('.sr-clone-banner')
                    .text(formatTemplate(_lang['ClonedFromFrameworkNotice'], {
                        name: clone.name,
                        n: clone.count.toLocaleString()
                    }))
                    .removeClass('d-none');

                // The parent, now that the dropdown exists. .val() on a value
                // with no matching <option> is a no-op and the select falls back
                // to its leading blank -- which is the honest outcome when the
                // source's parent is Inactive, since the dropdown is built
                // status=1 and that parent is not an option a user could have
                // picked here either.
                $modal.find('[name=parent]').val(String(clone.parent));
            } else {
                $('#framework--add-title').text(_lang['NewFramework']);
                $modal.find('.sr-clone-banner').addClass('d-none').text('');
                // A cancelled clone leaves its values in the form -- the fields
                // are only reset on a successful save -- so the plain-add open
                // has to put the create form back to its own defaults. The
                // native reset() is also what restores the SERVER-SEEDED default
                // inclusion justification (display_add_framework()'s third
                // argument), which is why it is a reset rather than a blanking.
                resetForm('#framework-create-form');
                if (typeof setEditorContent === 'function') { setEditorContent('add_framework_description', ''); }
                setSoaEditorContent('add_', 'scope_statement', '');
            }
        });

        // "+ Add control" (scaffolded by Task 7 specifically for this task
        // to wire) opens the blank Add Control modal. #sr-ctl-empty-add
        // (Task 9) is the "no data yet" empty tile's own copy of the same
        // action -- same modal, same reset, just a second trigger.
        // pendingCloneSourceName is left untouched here on purpose -- the
        // show.bs.modal delegate above is the sole owner of the title/
        // banner/mapping-table decision; this handler only needs to reset
        // the field VALUES resetForm() itself covers.
        $(document).on('click', '#sr-ctl-add, #sr-ctl-empty-add', function () {
            resetForm('#add-control-form');
            if (typeof setEditorContent === 'function') {
                setEditorContent('add_control_description', '');
                setEditorContent('add_supplemental_guidance', '');
            }
            $('#control--add').modal('show');
        });

        // Row actions (Task 8): Edit / Delete for a single control. Delegated
        // because renderTable() rebuilds #sr-ctl-tbody on every reload.
        $(document).on('click', '[data-sr-ctl-edit]', function () {
            var id = $(this).closest('tr').attr('data-sr-ctl');
            openControlForEdit(id);
        });
        // Clone (Task 24): pre-fills the Add Control modal -- see
        // openControlForClone()'s comment for why this isn't a one-click
        // duplicate.
        $(document).on('click', '[data-sr-ctl-clone]', function () {
            var id = $(this).closest('tr').attr('data-sr-ctl');
            openControlForClone(id);
        });

        // Mapped Control Frameworks and Mapped Assets widgets ("+ Add Mapping"
        // / per-row delete), in BOTH control modals.
        //
        // Both were dead in the redesign -- js/simplerisk/pages/governance.js
        // owned this wiring and stopped loading on this page at Task 2, and
        // nothing replaced it -- so a user could never map a framework or an
        // asset even though display_add_control() still rendered the buttons
        // and tables. Task 24 wired the framework half and Task 53 the asset
        // half, both for #add-control-form only: updateControlById() dropped a
        // submitted mapping on the floor, so offering working controls in the
        // UPDATE modal would have silently discarded the user's edit on Save.
        //
        // Task 20 removed that reason. updateControlById() now REPLACES a
        // control's mappings when the request carries the submission markers
        // display_mapping_framework_edit()/display_mapping_asset_edit() emit,
        // and openControlForEdit() pre-fills both tables from the control's
        // stored rows -- so what the Update modal sends is the complete,
        // reviewed set, and both widgets are live there too. The handlers are
        // scoped to the two control forms rather than the document so they
        // cannot reach an identically-classed widget on some other surface.
        // A helper, not a constant: '#add-control-form, #update-control-form .x'
        // does NOT distribute -- CSS reads it as "#add-control-form" OR
        // "#update-control-form .x", so the Add modal's own button stops
        // matching entirely. Each form's prefix has to be repeated per
        // selector, which is what this does.
        function inControlForms(selector) {
            return '#add-control-form ' + selector + ', #update-control-form ' + selector;
        }

        $(document).on('click', inControlForms('.control-block--add-mapping'), function (event) {
            event.preventDefault();
            var $table = $(this).closest('.sr-qfield').find('.mapping_framework_table');
            $table.find('tbody').append($('#add_mapping_row table tr:first-child').parent().html());
            $table.closest('.sr-qfield').find('.mapping-framework-required-mark').removeClass('d-none');
        });
        $(document).on('click', inControlForms('.control-block--delete-mapping'), function (event) {
            event.preventDefault();
            var $table = $(this).closest('table');
            $(this).closest('tr').remove();
            if ($table.find('tbody tr').length === 0) {
                $table.closest('.sr-qfield').find('.mapping-framework-required-mark').addClass('d-none');
            }
        });

        // One framework per mapping row. framework_control_mappings is keyed on
        // (control_id, framework), so a second row naming a framework another
        // row already names is not a second mapping -- it silently overwrites
        // the first. Same guard js/simplerisk/pages/governance.js has always
        // had; it matters more now that Save also PRUNES, because the
        // overwritten row's reference text would be gone for good.
        $(document).on('change', inControlForms('[name="map_framework_id[]"]'), function () {
            var current = this;
            if (!$(current).val()) { return; }
            var $form = $(current).closest('form');
            $form.find('[name="map_framework_id[]"]').each(function () {
                if (this !== current && $(this).val() === $(current).val()) {
                    $(current).find('option:eq(0)').prop('selected', true);
                    showAlertFromMessage(_lang['ExistingMappings'], false);
                    return false;
                }
            });
        });

        // See the reindexAssetRows()/appendAssetRow() block above for why the
        // asset row names have to be rewritten on both events.
        $(document).on('click', inControlForms('.control-block--add-asset'), function (event) {
            event.preventDefault();
            appendAssetRow($(this).closest('form'));
        });
        $(document).on('click', inControlForms('.control-block--delete-asset'), function (event) {
            event.preventDefault();
            var $form = $(this).closest('form');
            $(this).closest('tr').remove();
            reindexAssetRows($form);
            toggleAssetRequiredMark($form);
        });

        // One maturity level per row. control_to_assets stores the level on
        // the mapping row itself, so two rows at the same level are two
        // answers to the same question and the second silently wins. Same
        // guard js/simplerisk/pages/governance.js has always had on this
        // widget; the offending select is reset to its placeholder rather
        // than left holding a value the save would not honour.
        $(document).on('change', inControlForms('[name="asset_maturity[]"]'), function () {
            var current = this;
            if (!$(current).val()) { return; }
            var $form = $(current).closest('form');
            $form.find('[name="asset_maturity[]"]').each(function () {
                if (this !== current && $(this).val() === $(current).val()) {
                    $(current).find('option:eq(0)').prop('selected', true);
                    showAlertFromMessage(_lang['ExistingMappings'], false);
                    return false;
                }
            });
        });
        // A destructive confirm opens with focus on its SAFE action.
        //
        // Bootstrap focuses the modal CONTAINER, not a control inside it, so
        // until now whichever element happened to hold focus was incidental --
        // true often enough for a test to assert, not often enough to rely on.
        // Placing it deliberately is the point of a destructive confirm: a stray
        // Enter must not delete the thing, and a keyboard user must not have to
        // find the way out of a dialog that opened with the red verb under their
        // hands. The Cancel buttons on these three confirms drop their
        // `aria-hidden` for the same reason (governance/index.php) -- an element
        // hidden from assistive technology must never receive focus.
        //
        // `shown.bs.modal`, not `show`: the element is not focusable until it is
        // painted. Bound on `document` for all three confirms at once, so a
        // future confirm cannot be added without it.
        //
        // The jQuery form works here despite Bootstrap 5 dispatching native
        // events: its EventHandler.trigger() still fires a parallel jQuery event
        // for NAMESPACED types whenever `window.jQuery` exists and <body> lacks
        // `data-bs-no-jquery` -- both true on every page in this app. Verified
        // live rather than assumed: a delegated `$(document).on('shown.bs.modal',
        // ...)` handler does run. Should that interop ever be turned off, this
        // (and every other `.bs.` handler in the codebase) would need a native
        // addEventListener instead.
        $(document).on('shown.bs.modal', '#control--delete, #controls--delete, #framework--delete', function () {
            $(this).find('[data-bs-dismiss="modal"].btn-dark').first().trigger('focus');
        });

        $(document).on('click', '[data-sr-ctl-delete]', function () {
            var $row = $(this).closest('tr');
            var id = $row.attr('data-sr-ctl');
            var name = $row.find('.sr-ctl-name').text();
            var $modal = $('#control--delete');
            $modal.find('[name=control_id]').val(id);
            setDeleteTitle($modal, _lang['DeleteControlTitle'], { name: name });
            $modal.modal('show');
        });

        // Rail row actions (Task 8): Edit / Clone / Delete for a framework.
        // stopPropagation -- these buttons live inside the clickable
        // .sr-fw-item <li> (selectFramework()'s own click target) and must
        // not also select the row they sit on.
        $(document).on('click', '[data-sr-fw-edit]', function (e) {
            e.stopPropagation();
            openFrameworkForEdit($(this).attr('data-id'), $(this).attr('data-name'));
        });
        // Clone (Task 64): pre-fills the Add Framework modal -- see
        // openFrameworkForClone() for why this isn't a one-click duplicate.
        $(document).on('click', '[data-sr-fw-clone]', function (e) {
            e.stopPropagation();
            openFrameworkForClone(
                $(this).attr('data-id'),
                $(this).attr('data-name'),
                $(this).attr('data-count')
            );
        });
        $(document).on('click', '[data-sr-fw-delete]', function (e) {
            e.stopPropagation();
            var $modal = $('#framework--delete');
            $modal.find('[name=framework_id]').val($(this).attr('data-id'));
            setDeleteTitle($modal, _lang['DeleteFrameworkTitle'], { name: $(this).attr('data-name') });
            $modal.modal('show');
        });

        // Task 17: both routes to the Statement of Applicability -- the rail row
        // action (which names its own framework) and the toolbar button (which
        // carries the scoped one). One handler for both, so the two cannot come
        // to build different URLs for the same document.
        //
        // ===== TASK 8: THEY GO TO THE LAUNCHER, NOT TO THE DOCUMENT =========
        //
        // `?preselect=` opens reports/statement_of_applicability.php in its
        // LAUNCHER state with this framework already chosen -- not `?framework=`,
        // which is that page's DOCUMENT state and would render the artefact
        // directly, as both of these buttons used to. Three reasons, and the
        // first is the one that forced it:
        //
        //   1. Task 7 made BASIC vs DETAILED a generation-time choice, made on
        //      the launcher. A button that skips the launcher can only ever
        //      produce one variant -- so the choice that decides what an audit
        //      document omits would be unreachable from the page where the
        //      decision actually gets made.
        //
        //   2. It HID THE EXPORTS. Two of the three affordances (XLSX and PDF)
        //      live on the launcher, so someone who wanted the PDF had to open
        //      the browser view and then navigate backwards to find it.
        //
        //   3. ONE PLACE GENERATES SoAs. Two entry points with different
        //      capabilities keep diverging; this is also why the two selectors
        //      still share one handler rather than being split so that only one
        //      of them changed.
        //
        // `?framework=` is untouched and still the document -- a bookmark, a
        // link mailed to an auditor, and the launcher's own "Open" action
        // all keep landing on the artefact with no launcher in the way.
        //
        // BASE_URL, not a bare path: sub-path installs (https://host/simplerisk/)
        // are the reason every fetch on this page is written that way.
        $(document).on('click', '[data-sr-fw-soa], #sr-soa-btn', function (e) {
            e.stopPropagation();
            var framework = $(this).attr('data-id') || $(this).attr('data-framework');
            if (!framework) { return; }
            window.location = BASE_URL + '/reports/statement_of_applicability.php?preselect='
                + encodeURIComponent(framework);
        });

        // Bulk delete (Task 7 built the trigger; Task 8 populates it; Task 54
        // makes it tell the truth about what deleting means).
        //
        // The TITLE's count is still selectionCount() -- the same function the
        // bulk bar's own number comes from, so the modal cannot open naming a
        // different number than the bar the user clicked. The CONSEQUENCE
        // sentence is a different claim ("N will be deleted, M kept, K removed
        // for good"), and that one is resolved by the server before the Delete
        // button becomes clickable: the split is a fact about the rows, and a
        // client that guessed it would be guessing about data loss.
        $(document).on('show.bs.modal', '#controls--delete', function () {
            setDeleteTitle($(this), _lang['DeleteControlsTitle'], { n: selectionCount() });
            renderControlsDeleteScope();
        });

        // Form submits. type="submit" buttons inside each form (design-system.md
        // §8's Save/Delete) -- prevented here so the redesigned flow (AJAX,
        // stay-open-on-error, reload-in-place) replaces the browser's native
        // full-page POST, never falls back to it.
        $(document).on('submit', '#framework-create-form', function (e) {
            e.preventDefault();
            submitFrameworkAdd($(this), $('#framework--add [name=add_framework]'));
        });
        $(document).on('submit', '#update-framework-form', function (e) {
            e.preventDefault();
            submitFrameworkUpdate($(this), $('#update_framework'));
        });
        $(document).on('submit', '#framework-delete-form', function (e) {
            e.preventDefault();
            submitFrameworkDelete($(this), $(this).find('[name=delete_framework]'));
        });
        $(document).on('submit', '#add-control-form', function (e) {
            e.preventDefault();
            submitControlAdd($(this), $('#add_control'));
        });
        $(document).on('submit', '#update-control-form', function (e) {
            e.preventDefault();
            submitControlUpdate($(this), $('#update_control'));
        });
        $(document).on('submit', '#control--delete-form', function (e) {
            e.preventDefault();
            submitControlDelete($(this), $(this).find('[name=delete_control]'));
        });
        $(document).on('submit', '#controls--delete-form', function (e) {
            e.preventDefault();
            submitControlsDelete($(this), $(this).find('[name=delete_controls]'));
        });

        // Bulk-set applicability (Task 15). The trigger is rebuilt by
        // renderBulkBar() on every selection change, so the handler is
        // delegated like every other one on this page.
        //
        // Clears the row hand-off explicitly rather than relying on the show
        // handler having consumed the last one: this is the entry point that
        // means "the selection", and it says so at the point of intent instead
        // of trusting a variable it did not write.
        $(document).on('click', '#sr-ctl-set-applicability', function () {
            pendingApplicabilityControl = null;
            $('#applicability--set').modal('show');
        });

        // Row applicability (Task 60) -- the second entry point, and the one
        // the target resolution exists for. Delegated because renderTable()
        // rebuilds #sr-ctl-tbody on every reload, and rendered only when
        // state.applicabilityScoped (renderRow()).
        //
        // Reads the row it was clicked in and NOTHING else. It does not tick
        // the row's checkbox, does not clear the selection, and does not read
        // it -- opening this from a row while five others are checked (or while
        // "Select all N filtered" is in force) leaves that selection exactly as
        // it was, and writes to this control alone.
        $(document).on('click', '[data-sr-ctl-applicability]', function () {
            var $row = $(this).closest('tr');
            pendingApplicabilityControl = {
                // parseInt, matching what the checkbox handler puts in
                // `selection` -- so both entry points hand the endpoint the
                // same JSON shape and not a number in one case and a string
                // in the other.
                id: parseInt($row.attr('data-sr-ctl'), 10),
                name: $row.find('.sr-ctl-name').text()
            };
            $('#applicability--set').modal('show');
        });

        // Every open starts from the default state, with the fields the
        // previous open left behind cleared. The reset lives in ONE delegated
        // handler rather than in the click above, so a future second trigger
        // (or a plain data-bs-toggle button) cannot reintroduce a
        // stale-value-on-reopen bug by forgetting to call it -- the same fix
        // Task 8 applied to the stale inline error banner.
        //
        // The TARGET is resolved here for the same reason, and it is the more
        // important one: resolving in each click handler would put "what am I
        // acting on" in as many places as there are triggers, which is the
        // arrangement that let a row action mean the bulk selection in the
        // first place. One open, one resolution, consumed and cleared -- so a
        // trigger this file does not know about gets the SELECTION (the
        // conservative reading, and the one the copy will then describe),
        // never a stale row.
        $(document).on('show.bs.modal', '#applicability--set', function () {
            var $form = $('#applicability-set-form');
            $form[0].reset();
            applicabilityTarget = resolveApplicabilityTarget();
            pendingApplicabilityControl = null;

            // THE ROUND TRIP. A row action opens onto the decision that control
            // already carries -- state, reasons and prose -- because a modal
            // that wrote correctly and always reopened blank teaches the user
            // their decision was lost, which is the half of this feature they
            // notice first. Null for a bulk selection, which has no single
            // decision to show; see applicabilityPrefillFor().
            var prefill = applicabilityPrefillFor(applicabilityTarget);

            if (prefill) {
                // Matched on the VALUE rather than interpolated into a selector:
                // the token comes from the server, but a selector built from a
                // response is a habit worth not having.
                $form.find('input[name=applicability_state]').each(function () {
                    this.checked = (this.value === prefill.state);
                });
                $('#appl-narrative').val(prefill.narrative);
                $('#appl-provider').val(prefill.provider);
            }

            // .reset() restores the CHECKED attribute, which is 'applicable'
            // in the markup, but not the derived field visibility/required
            // state that depends on it.
            applyApplicabilityState(currentApplicabilityState(), prefill ? prefill.reason_ids : []);
            renderApplicabilityScope();
        });

        $(document).on('change', '#applicability-set-form input[name=applicability_state]', function () {
            // No preselect: the reasons a stored decision holds belong to the
            // state it was stored in, and carrying them across a state change
            // would tick an exclusion reason on an inclusion.
            applyApplicabilityState($(this).val());
        });

        // "At least one" is not a rule any single checkbox can carry, so the
        // group's required-ness is recomputed on every tick.
        $(document).on('change', '#appl-reason input[type=checkbox]', function () {
            syncApplicabilityReasonRequired();
        });

        $(document).on('submit', '#applicability-set-form', function (e) {
            e.preventDefault();
            submitApplicabilitySet($(this), $('#appl-save'));
        });

        $.when(loadFrameworks(), reloadTable());
    });

    window.SRFrameworks = {
        selectFramework: selectFramework,
        reloadTable: reloadTable,
        currentFramework: function () { return state.framework; },
        state: state,
        selection: function () { return Array.from(selection); },
        // Bulk actions send the FILTER, not ids, when escalated -- so the
        // server acts on exactly the set the user was looking at, including
        // rows never fetched to the client. Task 13's endpoint accepts
        // EITHER control_ids OR all_filtered + filters.
        selectionPayload: function () {
            return selectAllFiltered
                ? { framework: state.framework, filters: escalatedFilters(), all_filtered: true }
                : { framework: state.framework, control_ids: Array.from(selection) };
        },
        // ---- Virtual scrolling (Task 47) --------------------------------
        // Exposed for the same reason `state` and `selection` are: the E2E
        // suite has to be able to ask "put row 1,400 on screen" and "how many
        // <tr>s are actually in the DOM", and neither is answerable by
        // clicking. virtualOn() is the mode; virtualRowCount() is what the
        // scrollbar spans; scrollToRow() is the jump.
        virtualOn: virtIsOn,
        virtualRowCount: function () { return virt.on ? virt.n : state.rows.length; },
        virtualLoadedRows: function () { return virt.on ? virtLoadedRows().length : state.rows.length; },
        scrollToRow: virtScrollToIndex,
        rowById: rowById
    };
})();
