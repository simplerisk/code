/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Shared row-actions overflow-menu behavior (design-system.md 6b) for the
 * .sr-row-actions-wrap/.sr-row-actions-toggle/.sr-row-actions markup shipped
 * by _tables.scss. Three pages need this independently -- Manage Audits
 * (includes/compliance.php), Define Tests (compliance-define-tests.js), and
 * Governance Frameworks/Control Catalog (governance-frameworks.js, across
 * both its control table and its framework rail) -- each hiding a row's
 * action buttons behind a toggle at narrower widths and needing the resulting
 * menu kept on screen. This used to be three near-identical copies of the
 * same close/orient/bind logic; this file is the one copy all three use.
 */
(function (global, $) {
    'use strict';

    /**
     * Walks up from $wrap to find the nearest ancestor that's actually
     * clipping (computed overflow-x or overflow-y other than 'visible'),
     * stopping at .sr-table-card (which never clips -- see its own SCSS
     * comment -- so it's a safe search boundary, not a candidate).
     *
     * On a plain (non-DataTables) table this is .sr-table-scroll itself. On
     * a DataTables scrollX table, .sr-table-scroll is deliberately made
     * NON-clipping (_tables.scss's '.sr-table-scroll:has(.dt-container)'
     * rule, avoiding a redundant second scrollbar under DataTables' own), so
     * the real clipping ancestor is one level further in: DataTables' own
     * '.dt-scroll' wrapper. Measuring/unclipping the wrong one is exactly
     * what let a downward-opened menu render invisible and unclickable on
     * Manage Audits at 900-1150px: the floor/ceiling math below, computed
     * against .sr-table-scroll's looser (non-clipping) rect, concluded a
     * menu "fits downward" while '.dt-scroll' clipped it anyway.
     */
    function findClipAncestor($wrap) {
        var card = $wrap.closest('.sr-table-card')[0];
        var el = $wrap[0] ? $wrap[0].parentElement : null;
        while (el && el !== card && el !== document.body) {
            var cs = getComputedStyle(el);
            if (cs.overflowX !== 'visible' || cs.overflowY !== 'visible') {
                return $(el);
            }
            el = el.parentElement;
        }
        return $();
    }

    /**
     * Shuts every open row-actions menu. `$scope` limits the search to one
     * page's own menus (a jQuery collection, e.g. a table's $tbody, or a
     * comma-selector covering more than one container); omit it to close
     * every open menu in the document, which is correct when a single page
     * has more than one independent surface (Governance's table + rail).
     *
     * The clipping ancestor's unclip is lifted only for as long as a menu
     * needs it -- leaving .is-unclipped behind would hand the table a
     * permanently unclipped scroller, so a genuinely wide table could then
     * spill its rows out of the card instead of scrolling them. Searched by
     * the .is-unclipped class itself rather than a specific element/class
     * (orient() below can land it on .sr-table-scroll OR, on a DataTables
     * scrollX table, DataTables' own '.dt-scroll' -- see findClipAncestor()).
     */
    function close($scope) {
        var $scroller = $scope ? $scope.find('.is-unclipped') : $('.is-unclipped');
        $scroller.removeClass('is-unclipped');

        var $open = $scope ? $scope.find('.sr-row-actions-wrap.is-open') : $('.sr-row-actions-wrap.is-open');
        $open.removeClass('is-open is-up is-right')
            .find('.sr-row-actions-toggle')
            .attr('aria-expanded', 'false');
    }

    /**
     * Gives an already-open menu somewhere to go: lifts its clipping
     * ancestor's clip when it is only clipping, and flips the menu upward
     * when it still won't fit below. (The stylesheet owns what "unclipped"
     * and "up" look like; both are measurements, so the decision lives
     * here.)
     *
     * A row's clipping ancestor (findClipAncestor()) typically has
     * `overflow-x: auto`, which computes `overflow-y` to `auto` along with
     * it -- the two axes cannot be auto and visible at once -- so a menu
     * popped from a row near the bottom of the list is clipped VERTICALLY by
     * a container that only ever wanted to scroll horizontally. Unclipping
     * fixes that wherever the table isn't actually scrolling sideways, which
     * is the normal case once a narrower tier has trimmed the columns to
     * fit. Where it IS scrolling the clip has to stay, and the flip is
     * what's left.
     *
     * `extend`, when given, runs after the vertical decision with
     * ($wrap, $menu, wrapRect) -- Governance's framework rail uses it to add
     * its own horizontal is-right flip, which only that narrow pane needs.
     *
     * Must run AFTER .is-open -- a display:none menu measures 0 high.
     */
    function orient($wrap, extend) {
        $wrap.removeClass('is-up is-right');
        var $menu = $wrap.find('.sr-row-actions');
        if (!$wrap.length || !$menu.length) { return; }

        var $scroller = findClipAncestor($wrap);
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

        if (typeof extend === 'function') {
            extend($wrap, $menu, wrapRect);
        }
    }

    /**
     * Wires the click-to-open/close/Escape behavior for one page.
     *
     * opts.container  - selector (or comma-selector) to delegate the toggle
     *                    click from. Required. Delegated rather than bound
     *                    per-row since rows are rebuilt on every render.
     * opts.scope      - jQuery collection passed to close()/used to find the
     *                    open menu on Escape; omit for a page with a single
     *                    global surface, pass e.g. a $tbody to scope a page
     *                    that has more than one independent table/list.
     * opts.namespace  - event namespace suffix for the document-level click/
     *                    keydown handlers (default 'srrowactions'); give each
     *                    page's own namespace so re-binding never doubles up.
     * opts.orientExtend - passed through to orient() as `extend`.
     * opts.restoreFocusOnEscape - default true. On Escape, returns focus to
     *                    the toggle that opened the menu rather than leaving
     *                    it wherever Escape was pressed -- otherwise a
     *                    keyboard user who tabbed into the menu is left with
     *                    focus on a button that is now display:none, and the
     *                    browser drops them to the top of the document. Pass
     *                    false only if a page has a reason not to.
     *
     * Returns { close } so a caller can also trigger a close programmatically
     * (e.g. before reloading a table's data).
     */
    function bind(opts) {
        opts = opts || {};
        var $container = $(opts.container);
        // A page's container selector is a fixed, hand-written string, not
        // user input -- a typo or a markup rename that drops it out of sync
        // silently disables this page's ENTIRE row-actions menu (the click
        // delegation just has nothing to delegate from), with no visible
        // symptom until someone happens to narrow the window to the compact
        // tier. That is exactly the shape of bug a console warning is worth
        // its keep for, unlike jQuery's usual silent-no-op-on-empty-selector
        // idiom (which is fine when the selector legitimately targets
        // optional, per-row content).
        if (!$container.length && global.console && typeof global.console.warn === 'function') {
            global.console.warn('SRRowActionsMenu.bind(): opts.container matched 0 elements: ' + opts.container);
        }
        var $scope = opts.scope || null;
        var namespace = opts.namespace || 'srrowactions';
        var extend = opts.orientExtend;
        var restoreFocusOnEscape = opts.restoreFocusOnEscape !== false;

        function closeAll() {
            close($scope);
        }

        // The toggle's own click handler has to stopPropagation() -- a row or
        // list item commonly carries its own click handler (expand a
        // procedure, select a framework), and opening a menu is not asking
        // for that. A stopPropagation() from a container-delegated handler is
        // what reliably keeps the document-level "close everything" handler
        // below from shutting the menu in the same click that opened it.
        $container.on('click', '.sr-row-actions-toggle', function (e) {
            e.stopPropagation();
            var $wrap = $(this).closest('.sr-row-actions-wrap');
            var wasOpen = $wrap.hasClass('is-open');
            // Close first, unconditionally: opening one row's menu closes any
            // other row's, and a second click on the same toggle just closes.
            closeAll();
            if (!wasOpen) {
                $wrap.addClass('is-open').find('.sr-row-actions-toggle').attr('aria-expanded', 'true');
                orient($wrap, extend);
            }
        });

        // Anywhere else, including another row's toggle (handled above by
        // closing everything first) and any action inside the menu, which
        // does its own thing and should leave the menu shut behind it.
        $(document).on('click.' + namespace, closeAll);

        $(document).on('keydown.' + namespace, function (e) {
            if (e.key !== 'Escape') { return; }

            if (!restoreFocusOnEscape) {
                closeAll();
                return;
            }

            var $openScope = $scope || $(document);
            var $toggle = $openScope.find('.sr-row-actions-wrap.is-open').find('.sr-row-actions-toggle');
            if (!$toggle.length) { return; }
            closeAll();
            $toggle.trigger('focus');
        });

        return { close: closeAll };
    }

    global.SRRowActionsMenu = { close: close, orient: orient, bind: bind };
})(window, jQuery);
