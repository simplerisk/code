/**
 * sr-select -- a keyboard-navigable custom listbox that skins a native
 * <select> (single or multiple) to match design-system.md's toolbar filter
 * look. Originally built page-local for the Define Tests redesign
 * (compliance-define-tests.js); extracted here once the Manage Audits and
 * Initiate Audits toolbars needed the same widget, so all three pages share
 * one implementation instead of three copies.
 *
 * Only srSelectEnhance($native, placeholder) is exposed globally -- call it
 * once per native <select> to enhance. The rest are internal.
 */
(function ($) {
    'use strict';

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
        api.$options.empty();

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

            $row.appendTo(api.$options);
        });

        $('<div>', { 'class': 'sr-select-empty', text: _lang['NoMatchingOptions'] || 'No matching options.' }).appendTo(api.$options);

        // A rebuild (a filter dropdown repopulated from fresh data) starts
        // from a clean slate elsewhere, but the search box's own text is
        // this widget's local state -- re-apply it so an in-progress search
        // survives the option list underneath it changing.
        srSelectFilter(api, api.$search.val());

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

    var srSelectUid = 0;

    // Inside a modal the absolutely-positioned menu would be clipped by the
    // scrolling .modal-body (overflow-y:auto) and force an inner scrollbar. So
    // there the menu is laid out position:fixed, placed under -- or, when there
    // is no room, over -- the button, and re-placed on scroll/resize. It stays a
    // DOM child of the wrapper so Bootstrap's focus trap and the keydown
    // handlers keep working. The menu's z-index only orders it inside the
    // modal's own stacking context (.modal forms one), which is all it needs.
    //
    // position:fixed resolves against the viewport ONLY when no ancestor
    // establishes a containing block for fixed descendants (transform, filter,
    // will-change, contain:layout|paint, container-type, or the modal's fade
    // transform while it animates in). Rather than enumerate those, we place
    // using viewport coordinates, then measure where the menu actually landed
    // and shift by the difference (one correction pass). That is correct for
    // any containing block and is re-run on every re-place.
    function srSelectPlace(api) {
        var btn = api.$button[0].getBoundingClientRect();
        var menu = api.$menu[0];
        var vw = document.documentElement.clientWidth;
        var vh = window.innerHeight;
        var gap = 4;
        var margin = 8;
        var below = vh - btn.bottom - gap - margin;
        var above = btn.top - gap - margin;
        var openUp = below < 200 && above > below;
        var room = Math.max(120, openUp ? above : below);
        var width = Math.min(Math.max(btn.width, 0), vw - 2 * margin);
        menu.style.position = 'fixed';
        menu.style.minWidth = width + 'px';
        menu.style.maxHeight = Math.min(320, room) + 'px';
        var w = menu.offsetWidth;
        var wantLeft = Math.max(margin, Math.min(btn.left, vw - w - margin));
        var wantTop;
        menu.style.left = wantLeft + 'px';
        menu.style.bottom = 'auto';
        if (openUp) {
            wantTop = btn.top - gap - menu.offsetHeight;
        } else {
            wantTop = btn.bottom + gap;
        }
        menu.style.top = wantTop + 'px';
        // Correction pass: compare the desired viewport position with the
        // actual one and shift by the delta.
        var actual = menu.getBoundingClientRect();
        var dx = wantLeft - actual.left;
        var dy = wantTop - actual.top;
        if (Math.abs(dx) > 0.5) { menu.style.left = (wantLeft + dx) + 'px'; }
        if (Math.abs(dy) > 0.5) { menu.style.top = (wantTop + dy) + 'px'; }
    }

    function srSelectDetachFloat(api) {
        $(window).off('.srselectfloat' + api.uid);
        if (api.onScroll) {
            document.removeEventListener('scroll', api.onScroll, true);
            api.onScroll = null;
        }
        if (api.vv && api.onVvResize) {
            api.vv.removeEventListener('resize', api.onVvResize);
        }
        api.vv = null;
        api.onVvResize = null;
    }

    function srSelectClose(api, refocus) {
        if (api.floating) {
            srSelectDetachFloat(api);
            api.$menu.css({ position: '', left: '', top: '', bottom: '', minWidth: '', maxHeight: '' });
        }
        api.$menu.attr('hidden', 'hidden');
        api.$button.attr('aria-expanded', 'false');
        // Every reopen starts from the full list -- a search left over from
        // last time would otherwise silently hide options the next visit,
        // reading as "the filter is broken" rather than "still searching".
        api.$search.val('');
        srSelectFilter(api, '');
        if (refocus) {
            api.$button.trigger('focus');
        }
    }

    function srSelectOpen(api) {
        // Idempotent: re-opening an open menu must not stack listeners.
        if (!api.$menu.attr('hidden')) {
            return;
        }
        api.$menu.removeAttr('hidden');
        api.$button.attr('aria-expanded', 'true');
        if (api.floating) {
            srSelectDetachFloat(api);
            srSelectPlace(api);
            var ns = '.srselectfloat' + api.uid;
            var startW = window.innerWidth;
            // A real width change (or rotation) closes the menu. A height-only
            // resize -- the on-screen keyboard appearing because the search
            // input took focus -- must not.
            $(window).on('resize' + ns, function () {
                var searchFocused = document.activeElement === api.$search[0];
                if (window.innerWidth === startW && searchFocused) {
                    srSelectPlace(api);
                    return;
                }
                srSelectClose(api, false);
            });
            // Capture phase: scroll does not bubble. A scroll inside the menu's
            // own option list is not a reason to move or close it.
            api.onScroll = function (e) {
                if (!api.$menu[0].contains(e.target)) { srSelectPlace(api); }
            };
            document.addEventListener('scroll', api.onScroll, true);
            if (window.visualViewport) {
                api.vv = window.visualViewport;
                api.onVvResize = function () { srSelectPlace(api); };
                api.vv.addEventListener('resize', api.onVvResize);
            }
        }
        api.$search.trigger('focus');
        // Land on the current selection so arrow keys continue from where the
        // value already is, not from the top of the list.
        var $selected = api.$options.find('[aria-selected="true"]').first();
        srSelectActivate(api, $selected.length ? $selected : api.$options.find('.sr-select-option:not(:disabled)').first());
    }

    function srSelectActivate(api, $row) {
        if (!$row || !$row.length) {
            return;
        }
        api.$options.find('.sr-select-option').removeClass('is-active');
        $row.addClass('is-active');
        if ($row[0].scrollIntoView) {
            $row[0].scrollIntoView({ block: 'nearest' });
        }
    }

    // Case-insensitive substring match against each option's own label --
    // matches how every other filter/search box in the app works (e.g. the
    // toolbar search boxes these dropdowns sit beside). Hiding via a class
    // (not detaching the row) keeps selection state and the DOM structure
    // intact for when the query is cleared.
    function srSelectFilter(api, query) {
        var q = String(query || '').toLowerCase();
        var visibleCount = 0;
        api.$options.find('.sr-select-option').each(function () {
            var $row = $(this);
            var matches = !q || $row.find('.sr-select-text').text().toLowerCase().indexOf(q) !== -1;
            $row.toggleClass('is-search-hidden', !matches);
            if (matches) {
                visibleCount++;
            }
        });
        api.$options.find('.sr-select-empty').toggle(visibleCount === 0);

        // The active row may have just been hidden -- land on the first
        // visible one instead of on nothing.
        var $active = api.$options.find('.sr-select-option.is-active');
        if (!$active.length || $active.hasClass('is-search-hidden')) {
            srSelectActivate(api, api.$options.find('.sr-select-option:not(:disabled):not(.is-search-hidden)').first());
        }
    }

    // Arrow keys skip disabled and search-hidden rows: a zero-count option is
    // shown because the absence is information, but it is not a place you
    // can land, and a row a live search has hidden is not currently a row.
    function srSelectMove(api, delta) {
        var $rows = api.$options.find('.sr-select-option').filter(function () {
            return !this.disabled && !$(this).hasClass('is-search-hidden');
        });
        if (!$rows.length) {
            return;
        }
        var index = $rows.index(api.$options.find('.sr-select-option.is-active'));
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
            srSelectActivate(api, api.$options.find('[data-value="' + value + '"]'));
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

        // A search box per dropdown, not just the roster-scale sr-picker --
        // even a short list is faster to narrow by typing than by scanning,
        // and every sr-select instance gets it uniformly rather than only
        // the ones that happen to have a long option list today.
        var $searchWrap = $('<div>', { 'class': 'sr-select-search' });
        $('<i>', { 'class': 'fa fa-search sr-select-search-icon', 'aria-hidden': 'true' }).appendTo($searchWrap);
        // 'autocomplete' is set via .attr(), not in the constructor's
        // attributes object -- jQuery UI's autocomplete widget registers
        // itself as $.fn.autocomplete, and passing `autocomplete: 'off'`
        // through $('<input>', {...}) makes jQuery call that PLUGIN method
        // ("off") on a not-yet-initialized widget instead of setting the
        // plain HTML attribute, throwing at construction time.
        var $search = $('<input>', {
            type: 'text',
            'class': 'sr-select-search-input',
            placeholder: (typeof _lang !== 'undefined' && _lang['Search']) || 'Search',
            'aria-label': (typeof _lang !== 'undefined' && _lang['Search']) || 'Search',
        }).attr('autocomplete', 'off').appendTo($searchWrap);
        var $options = $('<div>', { 'class': 'sr-select-options' });

        $native.addClass('sr-select-native').attr('tabindex', '-1').attr('aria-hidden', 'true');
        $native.after($wrapper);
        $wrapper.append($button).append($menu);
        $menu.append($searchWrap).append($options);

        var api = {
            $button: $button,
            $menu: $menu,
            $search: $search,
            $options: $options,
            uid: ++srSelectUid,
            floating: !!$native.closest('.modal').length,
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
            }
        });

        $options.on('click', '.sr-select-option', function () {
            srSelectChoose($native, $(this).attr('data-value'));
        });

        $search.on('input', function () {
            srSelectFilter(api, $search.val());
        });

        // Bound on the whole menu (search input + options list both bubble
        // here) rather than duplicated on each -- but Space and Home/End must
        // still behave as ordinary text-editing keys while the search input
        // itself has focus, or typing "test date" or pressing Home to jump to
        // the start of what's typed would instead select/jump options.
        $menu.on('keydown', function (e) {
            var inSearch = e.target === $search[0];
            switch (e.key) {
                case 'ArrowDown': e.preventDefault(); srSelectMove(api, 1); break;
                case 'ArrowUp': e.preventDefault(); srSelectMove(api, -1); break;
                case 'Home':
                    if (inSearch) { break; }
                    e.preventDefault();
                    srSelectActivate(api, $options.find('.sr-select-option:not(:disabled):not(.is-search-hidden)').first());
                    break;
                case 'End':
                    if (inSearch) { break; }
                    e.preventDefault();
                    srSelectActivate(api, $options.find('.sr-select-option:not(:disabled):not(.is-search-hidden)').last());
                    break;
                case ' ':
                    if (inSearch) { break; }
                    // fall through
                case 'Enter':
                    e.preventDefault();
                    var $active = $options.find('.sr-select-option.is-active');
                    if ($active.length && !$active.prop('disabled')) {
                        srSelectChoose($native, $active.attr('data-value'));
                    }
                    break;
                case 'Escape': e.preventDefault(); srSelectClose(api, true); break;
                case 'Tab': srSelectClose(api, false); break;
                default: break;
            }
        });

        // Per-instance namespaces so a re-bind replaces rather than stacks,
        // and destroy can unbind exactly this instance's handlers.
        var evNs = '.srselect' + api.uid;
        api.evNs = evNs;
        if (api.floating) {
            api.$modal = $native.closest('.modal');
            api.$modal.off('hide.bs.modal' + evNs).on('hide.bs.modal' + evNs, function () {
                if (!$menu.attr('hidden')) { srSelectClose(api, false); }
            });
        }

        // Clicking anywhere else dismisses it, like any other menu.
        $(document).off('mousedown' + evNs).on('mousedown' + evNs, function (e) {
            if (!$wrapper[0].contains(e.target) && !$menu.attr('hidden')) {
                srSelectClose(api, false);
            }
        });

        srSelectRender($native);
    }

    // srSelectRender is also exposed: callers that rebuild a select's
    // <option> list at runtime (e.g. from an AJAX response) need to
    // re-render the enhanced widget without re-enhancing it.
    // Unbinds every document/window/modal handler an instance owns.
    function srSelectDestroy($native) {
        var api = $native.data('srSelect');
        if (!api) { return; }
        srSelectDetachFloat(api);
        $(document).off('mousedown' + api.evNs);
        if (api.$modal) { api.$modal.off('hide.bs.modal' + api.evNs); }
    }

    window.srSelectEnhance = srSelectEnhance;
    window.srSelectDestroy = srSelectDestroy;
    window.srSelectRender = srSelectRender;

}(jQuery));
