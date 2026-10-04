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
     * Shuts every open row-actions menu. `$scope` limits the search to one
     * page's own menus (a jQuery collection, e.g. a table's $tbody, or a
     * comma-selector covering more than one container); omit it to close
     * every open menu in the document, which is correct when a single page
     * has more than one independent surface (Governance's table + rail).
     */
    function close($scope) {
        var $open = $scope ? $scope.find('.sr-row-actions-wrap.is-open') : $('.sr-row-actions-wrap.is-open');
        clearPlacement($open.find('.sr-row-actions'));
        $open.removeClass('is-open is-up is-right')
            .find('.sr-row-actions-toggle')
            .attr('aria-expanded', 'false');
    }

    var PLACEMENT = { position: '', top: '', left: '', right: '', bottom: '', maxHeight: '', overflowY: '', justifyContent: '' };

    function clearPlacement($menu) {
        $menu.css(PLACEMENT);
        $menu.closest('.sr-row-actions-wrap').removeData('srRowActionsAnchor');
    }

    // The viewport the menu has to fit in, WITHOUT the page's own scrollbars:
    // window.innerWidth/innerHeight include them, so a menu clamped against
    // those could still slide under a real (space-taking) scrollbar.
    function viewportSize() {
        var root = document.documentElement;
        return { width: root.clientWidth || window.innerWidth, height: root.clientHeight || window.innerHeight };
    }

    // The top of the area a menu may use: below the app shell's fixed top
    // bar, which paints above page content (a menu slid under it has its
    // first items covered). A menu inside a modal sits above the top bar, so
    // it may use the whole height.
    function usableTop($wrap) {
        if ($wrap.closest('.modal').length) { return 0; }
        var bar = document.querySelector('.topbar');
        if (!bar) { return 0; }
        var position = getComputedStyle(bar).position;
        if (position !== 'fixed' && position !== 'sticky') { return 0; }
        return Math.max(0, bar.getBoundingClientRect().bottom);
    }

    /**
     * Pins an open menu to the viewport (position: fixed) exactly where the
     * stylesheet already put it -- under the toggle, right-aligned, or above
     * it for .is-up, or left-aligned for .is-right -- by measuring its box
     * before pinning and re-applying that box as fixed coordinates, nudged
     * back inside the viewport if it would run off a side.
     *
     * Why fixed. Left in the flow, the menu is a descendant of the table's
     * horizontal scroller (.sr-table-scroll, or DataTables' .dt-scroll), and
     * `overflow-x: auto` computes overflow-y to `auto`. A menu hanging below
     * the last rows therefore became vertical overflow of that scroller.
     * Measured on Manage assets with real (non-overlay) scrollbars: opening
     * the last row's menu gave the scroller a vertical scrollbar, the
     * scrollbar narrowed the scroller until the table overflowed sideways,
     * a horizontal scrollbar appeared too, and the menu was cut off beneath
     * it. The earlier remedy -- lifting the scroller's clip while a menu was
     * open -- only applied when the table was not scrolling sideways, and it
     * decided that AFTER the menu's own overflow had already added the
     * vertical scrollbar. A fixed box is laid out against the viewport, so
     * no scroller clips it and it adds nothing to any scroller's overflow:
     * opening a menu cannot change a scrollbar anywhere. It stays inside its
     * row's stacking context (the open row's pinned cell is lifted above the
     * rows below it, _tables.scss), so it paints where it always did.
     *
     * The measured correction handles an ancestor that would become the
     * fixed box's containing block (a transform, filter or contain): the box
     * is moved by however far it landed from where it was asked to go.
     */
    function placeFixed($wrap, $menu) {
        var menu = $menu[0];
        var box = menu.getBoundingClientRect();
        var view = viewportSize();
        var edge = 4;
        var ceiling = usableTop($wrap) + edge;
        var left = Math.max(edge, Math.min(box.left, view.width - box.width - edge));
        var top = box.top;
        var css = { position: 'fixed', right: 'auto', bottom: 'auto' };

        // Taller than the viewport: cap it and let it scroll inside itself
        // (that scroll is exempt from the close-on-scroll below, and focus
        // moving to an item scrolls the menu, not the page, so Tab never
        // strands an item out of reach). Otherwise keep it whole on screen --
        // when neither the downward nor the upward position fits (orient()
        // then leaves it opening down), slide it up just far enough.
        var room = view.height - edge - ceiling;
        if (box.height > room) {
            css.maxHeight = room + 'px';
            css.overflowY = 'auto';
            // The cluster's own justify-content (flex-end, which right-aligns
            // the inline icon row) packs a column from the BOTTOM, so a capped
            // menu would overflow upward -- negative overflow no scroller can
            // reach. Pack from the top so the overflow is scrollable.
            css.justifyContent = 'flex-start';
            top = ceiling;
        } else {
            top = Math.max(ceiling, Math.min(top, view.height - box.height - edge));
        }

        css.top = top + 'px';
        css.left = left + 'px';
        $menu.css(css);
        var landed = menu.getBoundingClientRect();
        if (Math.abs(landed.left - left) > 0.5 || Math.abs(landed.top - top) > 0.5) {
            $menu.css({ top: (2 * top - landed.top) + 'px', left: (2 * left - landed.left) + 'px' });
        }

        // Where the row was when the menu was pinned; the scroll handler
        // below closes the menu only once the row has actually moved.
        var anchor = $wrap[0].getBoundingClientRect();
        $wrap.data('srRowActionsAnchor', { left: anchor.left, top: anchor.top });
    }

    // A fixed menu does not travel with its row, so a scroll that MOVES the
    // row under it -- the page, a table scrolling sideways, a modal body --
    // closes it, the usual contract for a popup menu, and so does a viewport
    // resize. Registered once for every page.
    //
    // Keyed on whether the open row actually moved, not on the mere fact of a
    // scroll event, so two kinds of scroll never close it:
    //   - scrolling inside the menu itself (a menu taller than the viewport
    //     scrolls; its own scroll events also carry it as their target), and
    //   - a scroll that leaves the row where it was, such as Governance's
    //     virtual list re-anchoring its scroller after a re-render so the
    //     visible rows stay put. (Scroll events fire asynchronously, at the
    //     next frame, so a synchronous "I'm adjusting" flag set around the
    //     scrollTop write would already be cleared by then; comparing the
    //     row's position does not depend on timing.)
    var scrollCloseBound = false;
    function bindScrollClose() {
        if (scrollCloseBound || !global.addEventListener) { return; }
        scrollCloseBound = true;
        global.addEventListener('scroll', function (e) {
            var $open = $('.sr-row-actions-wrap.is-open');
            if (!$open.length) { return; }
            if (e && e.target && e.target.nodeType === 1 && $(e.target).closest('.sr-row-actions').length) { return; }
            var moved = false;
            $open.each(function () {
                var was = $(this).data('srRowActionsAnchor');
                var now = this.getBoundingClientRect();
                if (!was || Math.abs(now.left - was.left) > 1 || Math.abs(now.top - was.top) > 1) { moved = true; }
            });
            if (moved) { close(); }
        }, true);
        global.addEventListener('resize', function () {
            if ($('.sr-row-actions-wrap.is-open').length) { close(); }
        });
    }

    /**
     * Gives an already-open menu somewhere to go: flips it upward when it
     * won't fit below the toggle inside the viewport but does fit above,
     * then pins it there with position: fixed (placeFixed()). The stylesheet
     * owns what "up" looks like; whether it applies is a measurement, so the
     * decision lives here.
     *
     * `extend`, when given, runs after the vertical decision with
     * ($wrap, $menu, wrapRect) -- Governance's framework rail uses it to add
     * its own horizontal is-right flip, which only that narrow pane needs.
     * It measures the menu in its stylesheet position, before it is pinned.
     *
     * Must run AFTER .is-open -- a display:none menu measures 0 high.
     */
    function orient($wrap, extend) {
        $wrap.removeClass('is-up is-right');
        var $menu = $wrap.find('.sr-row-actions');
        if (!$wrap.length || !$menu.length) { return; }
        clearPlacement($menu);

        // The menu is placed against the viewport (placeFixed() below), so
        // no scroller clips it: the viewport is the only floor and ceiling.
        var wrapRect = $wrap[0].getBoundingClientRect();
        var menuHeight = $menu[0].getBoundingClientRect().height;
        var floor = viewportSize().height;
        var ceiling = usableTop($wrap);

        // Only flip when down doesn't fit AND up does -- a menu with room on
        // neither side is better left opening downward, where at least its
        // first item is the one nearest the toggle that opened it.
        if (wrapRect.bottom + 4 + menuHeight > floor && wrapRect.top - 4 - menuHeight > ceiling) {
            $wrap.addClass('is-up');
        }

        if (typeof extend === 'function') {
            extend($wrap, $menu, wrapRect);
        }

        placeFixed($wrap, $menu);
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
        bindScrollClose();
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
