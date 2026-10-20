/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Popover for the locked topbar search (display_locked_topbar_search(),
 * includes/display.php). The trigger is a <button>; the body is a server-
 * rendered hidden panel of escaped text. This script only shows and hides it.
 *
 * Loaded with `defer`, so it runs after bootstrap.bundle.min.js (also deferred,
 * earlier in header.php) and after the DOM is parsed.
 */
(function () {
    'use strict';

    function init() {
        var root = document.querySelector('.sr-locked-search');
        // No-op rather than throw: a page without Bootstrap, or a topbar that
        // is not in the locked state, must keep working.
        if (!root || !window.bootstrap || !window.bootstrap.Popover) {
            return;
        }

        var trigger = root.querySelector('.sr-locked-search__trigger');
        var item = root.closest('li');
        var panel = item ? item.querySelector('.sr-locked-search__panel') : null;
        if (!trigger || !panel) {
            return;
        }
        // Idempotent: a second run must not stack a second popover and listeners.
        if (window.bootstrap.Popover.getInstance(trigger)) {
            return;
        }

        // Bootstrap moves the content element into the popover, so hand it a
        // visible clone and leave the hidden template in place.
        var content = panel.cloneNode(true);
        content.hidden = false;

        // `html: true` is REQUIRED for an element `content`: otherwise Bootstrap
        // 5.3.7 flattens an element to its textContent, dropping the chips' list
        // structure and the unlock <a>. The element is our own server-escaped
        // markup, never user input.
        //
        // `container` puts the popover right after the trigger in the DOM instead
        // of at the end of <body>, so the unlock link is the NEXT TAB STOP after
        // the trigger.
        var popover = new window.bootstrap.Popover(trigger, {
            content: content,
            html: true,
            title: panel.getAttribute('data-sr-popover-title') || '',
            placement: 'bottom',
            trigger: 'manual',
            container: item,
            customClass: 'sr-locked-search-popover'
        });

        var open = false;

        function setOpen(next) {
            open = next;
            trigger.setAttribute('aria-expanded', String(next));
            if (next) {
                popover.show();
            } else {
                popover.hide();
            }
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            setOpen(!open);
        });

        // Esc closes and hands focus back to the trigger, so a keyboard user is
        // never stranded inside a floating panel.
        document.addEventListener('keydown', function (event) {
            if (open && event.key === 'Escape') {
                setOpen(false);
                trigger.focus();
            }
        });

        // Click anywhere outside the trigger and the popover closes it.
        document.addEventListener('click', function (event) {
            if (!open) {
                return;
            }
            if (trigger.contains(event.target) || event.target.closest('.sr-locked-search-popover')) {
                return;
            }
            setOpen(false);
        });
    }

    init();
})();
