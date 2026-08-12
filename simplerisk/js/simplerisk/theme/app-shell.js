/*
 * App shell — three-state collapsing sidebar (new base design, §12).
 * States cycle expanded -> rail -> hidden; persisted in localStorage only.
 *
 * In the rail state the top-level items are icons only. Hovering an icon opens
 * a "flyout" (its label + submenu); clicking the icon PINS the flyout open until
 * the user clicks it again, clicks outside, presses Escape, or changes state.
 * Flyouts render into a <body>-level host and are positioned with fixed
 * coordinates clamped to the viewport, so they escape the sidebar's overflow and
 * never open off-screen for items low in the list.
 */
(function () {
    "use strict";

    // The hamburger cycles three states: expanded -> rail -> hidden -> expanded.
    // "hidden" (full-bleed, no sidebar) is safe now that the hamburger is always
    // visible in the topbar, so there's always a way back.
    var STATES = ["expanded", "rail", "hidden"];
    var KEY = "sr_sidebar_state";
    var CLOSE_DELAY = 160; // ms grace period to move from the icon onto the flyout

    var host = null;        // <div class="sr-flyout-host"> in <body>
    var pairs = [];         // [{ li, flyout }]
    var openLi = null;      // li whose flyout is currently shown (hover or pinned)
    var pinned = false;     // true once opened by a click
    var closeTimer = null;
    var suppressFocusOpen = false;  // true while we programmatically restore focus to an
                                    // icon (Escape), so the reveal-on-focus handler below
                                    // doesn't immediately re-open the flyout we just closed.

    // The user's explicit choice, or null if they've never toggled.
    function storedPref() {
        var s = null;
        try { s = localStorage.getItem(KEY); } catch (e) { /* private mode */ }
        return STATES.indexOf(s) === -1 ? null : s;
    }

    function currentState() {
        var dom = document.documentElement.getAttribute("data-sr-sidebar");
        return STATES.indexOf(dom) === -1 ? effectiveState() : dom;
    }

    // Below AUTO_RAIL_MAX the sidebar defaults to the icon rail (the minimum for
    // a small screen), but the hamburger can cycle it to any state temporarily;
    // any resize re-collapses to the rail. At/above AUTO_RAIL_MAX the hamburger
    // cycles the three states and the choice is remembered.
    // The breakpoint is emitted once by header.php (window.SR_AUTO_RAIL_MAX) and
    // read here so the pre-paint script and this file can't drift; 992 is only a
    // safety default if that global is somehow absent.
    var AUTO_RAIL_MAX = window.SR_AUTO_RAIL_MAX || 992;
    var narrowOverride = null;   // a state the user cycled to on a narrow screen (else auto-rail)

    function effectiveState() {
        if (window.innerWidth >= AUTO_RAIL_MAX) {
            return storedPref() || "expanded";
        }
        return narrowOverride || "rail";
    }

    // Apply the effective state to the DOM. Pass a state to also persist it as the
    // new stored preference (used by the hamburger); omit to just re-derive (resize).
    function applyState(persistPref) {
        if (persistPref && STATES.indexOf(persistPref) !== -1) {
            try { localStorage.setItem(KEY, persistPref); } catch (e) { /* private mode */ }
        }
        closeFlyout();
        var eff = effectiveState();
        document.documentElement.setAttribute("data-sr-sidebar", eff);
        var btn = document.getElementById("sr-hamburger");
        if (btn) {
            var collapsed = (eff !== "expanded");
            btn.setAttribute("aria-pressed", String(collapsed));
            // Keep the accessible name matching the affordance: expanded -> the next
            // activation collapses; rail/hidden -> it restores the sidebar. Labels are
            // localized via header.php data-* with English fallbacks.
            btn.setAttribute(
                "aria-label",
                collapsed
                    ? (btn.getAttribute("data-label-expand") || "Expand sidebar")
                    : (btn.getAttribute("data-label-collapse") || "Collapse sidebar")
            );
        }
        updateFooterFit();
    }

    // Hide the sidebar footer when the nav would otherwise collide with it —
    // i.e. when the nav's natural height plus the footer exceeds the space
    // available in the sidebar. (In rail/hidden the footer is hidden by CSS.)
    function updateFooterFit() {
        var sidebar = document.querySelector(".left-sidebar");
        var nav = document.querySelector(".left-sidebar .sidebar-nav");
        var footer = document.querySelector(".sr-sidebar-footer");
        if (!sidebar || !nav || !footer) { return; }
        if (currentState() !== "expanded") { return; }
        footer.classList.remove("sr-sf-collapsed");                 // show so we can measure it
        var padTop = parseFloat(getComputedStyle(sidebar).paddingTop) || 0;
        var available = sidebar.clientHeight - padTop;
        var needed = nav.scrollHeight + footer.offsetHeight;
        if (needed > available) { footer.classList.add("sr-sf-collapsed"); }
    }

    // The hamburger advances expanded -> rail -> hidden -> expanded from whatever
    // is currently shown. On a wide screen the choice is persisted; on a narrow
    // screen it's a temporary override that a resize will undo (back to the rail).
    function cycle() {
        var next = STATES[(STATES.indexOf(effectiveState()) + 1) % STATES.length];
        if (window.innerWidth >= AUTO_RAIL_MAX) {
            applyState(next);
        } else {
            narrowOverride = next;
            applyState();
        }
    }

    function buildFlyouts() {
        if (host) { return; }
        host = document.createElement("div");
        host.className = "sr-flyout-host";
        document.body.appendChild(host);

        var items = document.querySelectorAll("#sidebarnav > li.sidebar-item");
        Array.prototype.forEach.call(items, function (li) {
            var link = li.querySelector(":scope > a.sidebar-link");
            if (!link) { return; }
            var labelSpan = link.querySelector(".hide-menu");
            var label = labelSpan ? labelSpan.textContent.trim() : "";

            var fly = document.createElement("div");
            fly.className = "sr-flyout";

            var title = document.createElement("span");
            title.className = "sr-flyout-title";
            title.textContent = label;
            fly.appendChild(title);

            var sub = li.querySelector(":scope > ul.first-level");
            if (sub) {
                var subLinks = sub.querySelectorAll(":scope > li.sidebar-item > a.sidebar-link");
                Array.prototype.forEach.call(subLinks, function (a) {
                    var na = document.createElement("a");
                    na.className = "sr-flyout-item";
                    if (a.parentNode.classList.contains("active")) {
                        na.className += " active";
                    }
                    na.setAttribute("href", a.getAttribute("href") || "#");
                    var sp = a.querySelector(".hide-menu");
                    na.textContent = sp ? sp.textContent.trim() : a.textContent.trim();
                    fly.appendChild(na);
                });
            }

            host.appendChild(fly);
            pairs.push({ li: li, flyout: fly });

            // Hover keeps the flyout open across the icon and the flyout itself.
            li.addEventListener("mouseenter", function () {
                if (currentState() !== "rail" || pinned) { return; }
                cancelClose();
                openFlyout(li);
            });
            li.addEventListener("mouseleave", scheduleClose);
            fly.addEventListener("mouseenter", cancelClose);
            fly.addEventListener("mouseleave", scheduleClose);

            // Keyboard parity (design-system §7): focusing the icon reveals its
            // flyout, and focus leaving BOTH the icon and its body-level flyout
            // closes it. ArrowDown/Enter navigation INTO the flyout is wired in the
            // document keydown handler below (the inline submenu is display:none in
            // rail, so the flyout is the only keyboard path to the submenu items).
            link.addEventListener("focus", function () {
                if (suppressFocusOpen || currentState() !== "rail" || pinned) { return; }
                cancelClose();
                openFlyout(li);
            });
            li.addEventListener("focusout", scheduleClose);
            fly.addEventListener("focusin", cancelClose);
            fly.addEventListener("focusout", scheduleClose);
        });
    }

    function flyoutFor(li) {
        for (var i = 0; i < pairs.length; i++) {
            if (pairs[i].li === li) { return pairs[i].flyout; }
        }
        return null;
    }

    function cancelClose() {
        if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
    }

    function scheduleClose() {
        if (pinned) { return; }
        cancelClose();
        closeTimer = setTimeout(closeFlyout, CLOSE_DELAY);
    }

    function closeFlyout() {
        cancelClose();
        pinned = false;
        if (!openLi) { return; }
        var f = flyoutFor(openLi);
        if (f) { f.classList.remove("sr-flyout-open"); }
        openLi.classList.remove("sr-flyout-active");
        openLi = null;
    }

    function openFlyout(li) {
        var fly = flyoutFor(li);
        if (!fly) { return; }
        if (openLi && openLi !== li) {
            var prev = flyoutFor(openLi);
            if (prev) { prev.classList.remove("sr-flyout-open"); }
            openLi.classList.remove("sr-flyout-active");
        }
        var r = li.getBoundingClientRect();
        // Show first so we can measure height, then clamp into the viewport.
        fly.style.left = Math.round(r.right) + "px";
        fly.style.top = Math.round(r.top) + "px";
        fly.classList.add("sr-flyout-open");
        var h = fly.offsetHeight;
        var top = r.top;
        var maxTop = window.innerHeight - h - 8;
        if (top > maxTop) { top = maxTop; }
        if (top < 8) { top = 8; }
        fly.style.top = Math.round(top) + "px";
        li.classList.add("sr-flyout-active");
        openLi = li;
    }

    document.addEventListener("DOMContentLoaded", function () {
        applyState();
        buildFlyouts();

        var btn = document.getElementById("sr-hamburger");
        if (btn) {
            btn.addEventListener("click", function (e) { e.preventDefault(); cycle(); });
        }

        var nav = document.getElementById("sidebarnav");
        if (nav) {
            // Capture phase so we run before the theme's accordion handler and can
            // stop it: in rail, a top-level icon click PINS/unpins its flyout
            // instead of expanding the (label-hidden) inline submenu.
            nav.addEventListener("click", function (e) {
                if (currentState() !== "rail") { return; }
                var link = e.target.closest("#sidebarnav > li.sidebar-item > a.sidebar-link");
                if (!link) { return; }
                e.preventDefault();
                e.stopPropagation();
                var li = link.parentNode;
                if (pinned && openLi === li) {
                    closeFlyout();
                } else {
                    cancelClose();
                    openFlyout(li);
                    pinned = true;
                }
            }, true);
        }

        // Dismiss on outside click / Escape / resize.
        document.addEventListener("click", function (e) {
            if (!openLi) { return; }
            if (e.target.closest(".sr-flyout")) { return; }  // a flyout link — let it navigate
            // Belt-and-suspenders: the rail capture-handler above already
            // stopPropagation()s clicks on a top-level link, so this normally
            // can't fire for one — kept in case that capture guard is ever removed.
            if (e.target.closest("#sidebarnav > li.sidebar-item > a.sidebar-link")) { return; }
            closeFlyout();
        });
        document.addEventListener("keydown", function (e) {
            var key = e.key;
            if (key === "Escape" || e.keyCode === 27) {
                // Return focus to the icon whose flyout we're closing, so keyboard
                // focus doesn't get stranded on a now-hidden body-level menu item.
                var returnLi = openLi;
                closeFlyout();
                if (returnLi) {
                    var tl = returnLi.querySelector(":scope > a.sidebar-link");
                    // Suppress the reveal-on-focus handler during this programmatic
                    // focus restore, or it would immediately re-open the flyout we
                    // just closed (focus dispatch is synchronous). Reset right after.
                    if (tl) { suppressFocusOpen = true; tl.focus(); suppressFocusOpen = false; }
                }
                return;
            }
            if (currentState() !== "rail") { return; }

            // From a top-level rail icon: ArrowDown opens + pins its flyout and moves
            // focus to the first submenu item. (Enter is left to the click handler,
            // which pins the flyout; preventing it here would double-handle.)
            var topLink = e.target.closest("#sidebarnav > li.sidebar-item > a.sidebar-link");
            if (topLink && key === "ArrowDown") {
                var li = topLink.parentNode;
                var fly = flyoutFor(li);
                if (!fly) { return; }
                e.preventDefault();
                cancelClose();
                openFlyout(li);
                pinned = true;
                var first = fly.querySelector(".sr-flyout-item");
                if (first) { first.focus(); }
                return;
            }

            // Within an open flyout: Up/Down rove between items (they have no natural
            // tab-stop into them, being body-level); Escape (above) closes + returns.
            var inFlyout = e.target.closest(".sr-flyout");
            if (inFlyout && (key === "ArrowDown" || key === "ArrowUp")) {
                e.preventDefault();
                var items = Array.prototype.slice.call(inFlyout.querySelectorAll(".sr-flyout-item"));
                var idx = items.indexOf(e.target);
                if (idx === -1) { return; }
                var nextIdx = key === "ArrowDown" ? idx + 1 : idx - 1;
                if (nextIdx < 0) { nextIdx = items.length - 1; }
                if (nextIdx >= items.length) { nextIdx = 0; }
                items[nextIdx].focus();
            }
        });
        // Debounced: a window drag fires many resize events; coalesce so we do the
        // layout-reading re-derive once the drag settles, not on every pixel.
        var resizeTimer = null;
        window.addEventListener("resize", function () {
            closeFlyout();
            if (resizeTimer) { clearTimeout(resizeTimer); }
            resizeTimer = setTimeout(function () {
                narrowOverride = null;  // re-collapse to the rail minimum after a resize
                applyState();           // re-derive the effective state + footer fit
            }, 120);
        });
        // The accordion animates open/closed (~350ms), changing the nav height —
        // re-check the footer fit after it settles.
        if (nav) {
            nav.addEventListener("click", function () { setTimeout(updateFooterFit, 400); });
        }
        updateFooterFit();
    });
})();
