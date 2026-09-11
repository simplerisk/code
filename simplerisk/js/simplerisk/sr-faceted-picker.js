/**
 * FACETED PICKER -- choosing from a roster too large to scroll
 *
 * A dialog that narrows a roster by facets before listing it. Originally
 * built page-local for the Define Tests / Manage Audits control roster
 * (js/simplerisk/pages/compliance.js, backing the Add/Edit Test modals'
 * Control Name field -- its markup is display_control_picker_modal(),
 * includes/compliance.php); extracted here once Document Program's control
 * picker (simplerisk/governance/documentation.php) needed the same engine,
 * so both pages share one implementation instead of two copies.
 *
 * WHY: a real SCF import is ~1,500 controls. A dropdown asks "which of
 * these"; past a few dozen options the actual question is "how do I get
 * to the right neighbourhood", which needs narrowing, not scrolling.
 * Roster size is the deciding factor, not field type -- the Tester /
 * Teams / Stakeholders / Approvers fields in the same modal are tens of
 * rows and keep their dropdown, where a dialog would be ceremony.
 *
 * Written against a config rather than against controls: `facets` is a
 * list of {key, container, itemValues} and every label comes from the
 * caller, so the next large roster reuses this instead of copying it.
 * An empty config.facets = [] is a supported shape, not a special case --
 * the facets.forEach loops below just don't run, and itemsBefore()
 * degrades to pure search-filtering (Document Program's control picker
 * uses this: its roster is already framework-scoped one step earlier via
 * sets_controls_by_framework_ids(), so a second, independent facet layer
 * here would just re-litigate a choice the user already made).
 *
 * config.singleSelect (boolean, default falsy) makes toggle() REPLACE the
 * working selection instead of appending to it -- Define Exceptions'
 * `control` field (governance/document_exceptions.php) is genuinely
 * single-value even though its roster is the same ~1,500-row scale as
 * Document Program's control_ids[] (design-system.md §182/§14b: roster
 * SIZE decides whether a field earns this dialog, not single- vs.
 * multi-select shape). Every other caller leaves this falsy and gets
 * byte-identical behaviour to before this option existed --
 * renderSelected(), the chip list, search, keyboard nav, facets, and
 * commit/backdrop handling all already work correctly with 0 or 1 chosen
 * items, so only toggle() needed a branch.
 *
 * Only createFacetedPicker(config) is exposed -- a plain global function,
 * matching how the rest of compliance.js (where this lived before) and
 * documentation.php's inline <script> call it: as a bare global, not
 * through a namespace object. Callers build the per-roster config
 * (createControlPicker()-style factories) and own their own module state
 * (e.g. compliance.js's controlsRoster/controlsById) -- this file has none
 * of its own.
 */
function createFacetedPicker(config) {
    var $modal = $('#' + config.modalId);
    if (!$modal.length) {
        return null;
    }

    var $search = $('#' + config.searchId);
    var $list = $('#' + config.listId);
    var $selected = $('#' + config.selectedId);
    var $count = $('#' + config.countId);
    var $selectedCount = $('#' + config.selectedCountId);
    var $scope = $('#' + config.scopeId);

    // Everything the dialog is currently working with. `chosen` is the
    // WORKING copy: the field behind the dialog only changes on commit, so
    // Cancel (and the backdrop, and Esc) genuinely abandon.
    var items = [];
    var chosen = [];
    var facetState = {};
    var cursor = 0;
    var onCommit = null;

    config.facets.forEach(function (facet) {
        facetState[facet.key] = null;
    });

    // A facet narrows what the facets AFTER it may offer -- framework, then
    // family counted inside that framework. Everything before the given facet
    // applies; the facet itself doesn't, or it could only ever count itself.
    function itemsBefore(facetKey) {
        return items.filter(function (item) {
            return config.facets.every(function (facet) {
                if (facet.key === facetKey) {
                    return true;
                }
                var value = facetState[facet.key];
                return value === null || facet.itemValues(item).indexOf(value) !== -1;
            });
        });
    }

    function matchesTerm(item) {
        var term = $.trim(($search.val() || '')).toLowerCase();
        if (!term) {
            return true;
        }
        return config.searchText(item).toLowerCase().indexOf(term) !== -1;
    }

    function visibleItems() {
        return itemsBefore(null).filter(matchesTerm);
    }

    function renderFacet(facet) {
        var scoped = itemsBefore(facet.key);
        var counts = {};
        scoped.forEach(function (item) {
            facet.itemValues(item).forEach(function (value) {
                counts[value] = (counts[value] || 0) + 1;
            });
        });

        $('#' + facet.container).find('.sr-picker-facet').each(function () {
            var $button = $(this);
            var raw = $button.attr('data-picker-value');
            var isAll = (raw === '' || raw === undefined);
            var value = isAll ? null : parseInt(raw, 10);
            var count = isAll ? scoped.length : (counts[value] || 0);

            $button.attr('aria-pressed', facetState[facet.key] === value ? 'true' : 'false');
            // An option with nothing behind it stays visible but inert: hiding
            // it would make the list length jump around as other facets change,
            // and a greyed row still answers "is there anything here?".
            $button.toggleClass('is-empty', !isAll && count === 0);
            $button.find('.sr-picker-facet-count').text(count ? String(count) : '');
        });
    }

    function renderFacets() {
        config.facets.forEach(renderFacet);
    }

    function renderList() {
        var found = visibleItems();
        if (cursor > found.length - 1) {
            cursor = Math.max(found.length - 1, 0);
        }

        $list.empty();
        found.forEach(function (item, index) {
            var id = String(config.itemId(item));
            var isChosen = chosen.indexOf(id) !== -1;

            var $row = $('<button>', {
                type: 'button',
                'class': 'sr-picker-row' + (index === cursor ? ' is-cursor' : ''),
                role: 'option',
                'data-picker-id': id,
                'aria-selected': isChosen ? 'true' : 'false',
            });
            $('<span>', { 'class': 'sr-picker-check', html: isChosen ? '<i class="fa fa-check" aria-hidden="true"></i>' : '' }).appendTo($row);
            // text:, never html: -- every one of these is user-authored.
            $('<span>', { 'class': 'sr-picker-num', text: config.itemNumber(item) }).appendTo($row);
            $('<span>', { 'class': 'sr-picker-name', text: config.itemName(item) }).appendTo($row);
            // The hover: identity on the first line, then what the row is
            // actually FOR. A truncated one-line name and a bare number are
            // both unreadable to anyone who doesn't know the catalogue by
            // heart, and the description is the thing that settles "is this
            // the control I mean". On the whole row rather than the name span,
            // so the hover target is the thing the eye is already on.
            //
            // A native title, deliberately: it costs nothing, it survives
            // inside a scrolling pane and a stacked modal, and the text is
            // plain by the time it gets here (control_roster_description(),
            // includes/compliance_grid.php).
            var hover = config.itemHover ? config.itemHover(item) : '';
            if (hover) {
                $row.attr('title', hover);
            }
            $list.append($row);
        });

        if (!found.length) {
            $('<div>', { 'class': 'sr-picker-empty', text: config.emptyText }).appendTo($list);
        }

        $count.text(found.length ? String(found.length) : '0');
        $scope.text(currentScopeLabel());
    }

    // Names the set being searched, so a search that finds nothing is
    // self-explaining rather than mysterious.
    function currentScopeLabel() {
        var deepest = null;
        config.facets.forEach(function (facet) {
            var value = facetState[facet.key];
            if (value !== null) {
                deepest = $('#' + facet.container)
                    .find('.sr-picker-facet[data-picker-value="' + value + '"] .sr-picker-facet-label')
                    .text();
            }
        });
        return deepest || config.allScopeText;
    }

    function renderSelected() {
        $selected.empty();

        if (!chosen.length) {
            $('<div>', { 'class': 'sr-picker-empty', text: config.nothingSelectedText }).appendTo($selected);
        }

        // Roster order, not click order: a stable list is easier to re-read
        // than one that reshuffles as you work.
        items.forEach(function (item) {
            var id = String(config.itemId(item));
            if (chosen.indexOf(id) === -1) {
                return;
            }
            var $chip = $('<span>', { 'class': 'sr-picker-chip' });
            $('<span>', { 'class': 'sr-picker-chip-num', text: config.itemNumber(item) || config.itemName(item) }).appendTo($chip);
            $('<button>', {
                type: 'button',
                'class': 'sr-picker-chip-remove',
                'data-picker-remove': id,
                'aria-label': config.removeLabel + ' ' + config.itemNumber(item),
                html: '&times;',
            }).appendTo($chip);
            $selected.append($chip);
        });

        $selectedCount.text(chosen.length ? String(chosen.length) : '');
    }

    function renderAll() {
        renderFacets();
        renderList();
        renderSelected();
    }

    function toggle(id) {
        // Single-select: clicking a row REPLACES the current choice, except
        // clicking the row that is already the sole chosen item clears it --
        // preserving the "click again to remove" mental model the
        // multi-select branch below already has, just capped at one.
        if (config.singleSelect) {
            chosen = (chosen.length === 1 && chosen[0] === String(id)) ? [] : [String(id)];
            renderList();
            renderSelected();
            return;
        }

        var index = chosen.indexOf(String(id));
        if (index === -1) {
            chosen.push(String(id));
        } else {
            chosen.splice(index, 1);
        }
        renderList();
        renderSelected();
    }

    function setFacet(facetKey, value) {
        facetState[facetKey] = (facetState[facetKey] === value) ? null : value;

        // A later facet that no longer has anything behind it would strand the
        // list on an empty result the user can't explain, so it clears with the
        // facet that emptied it.
        var passed = false;
        config.facets.forEach(function (facet) {
            if (facet.key === facetKey) {
                passed = true;
                return;
            }
            if (!passed || facetState[facet.key] === null) {
                return;
            }
            var stillThere = itemsBefore(facet.key).some(function (item) {
                return facet.itemValues(item).indexOf(facetState[facet.key]) !== -1;
            });
            if (!stillThere) {
                facetState[facet.key] = null;
            }
        });

        cursor = 0;
        renderAll();
    }

    $modal.on('click', '.sr-picker-facet', function () {
        var $button = $(this);
        if ($button.hasClass('is-empty')) {
            return;
        }
        var raw = $button.attr('data-picker-value');
        setFacet($button.attr('data-picker-facet'), (raw === '' || raw === undefined) ? null : parseInt(raw, 10));
    });

    $modal.on('click', '.sr-picker-clear', function () {
        facetState[$(this).attr('data-picker-clear')] = null;
        cursor = 0;
        renderAll();
    });

    $modal.on('click', '.sr-picker-row', function () {
        cursor = $(this).index();
        toggle($(this).attr('data-picker-id'));
    });

    $modal.on('click', '.sr-picker-chip-remove', function () {
        toggle($(this).attr('data-picker-remove'));
    });

    $search.on('input', function () {
        cursor = 0;
        renderList();
    });

    // Type-then-Enter is the fast path for anyone who knows the number, so the
    // list is drivable without leaving the search box.
    $search.on('keydown', function (event) {
        var found = visibleItems();
        if (event.key === 'ArrowDown') {
            cursor = Math.min(cursor + 1, found.length - 1);
            renderList();
            scrollCursorIntoView();
            event.preventDefault();
        } else if (event.key === 'ArrowUp') {
            cursor = Math.max(cursor - 1, 0);
            renderList();
            scrollCursorIntoView();
            event.preventDefault();
        } else if (event.key === 'Enter') {
            // Enter in a dialog would otherwise submit the form behind it.
            event.preventDefault();
            if (found[cursor]) {
                toggle(config.itemId(found[cursor]));
                scrollCursorIntoView();
            }
        }
    });

    function scrollCursorIntoView() {
        var row = $list.find('.is-cursor')[0];
        if (row && row.scrollIntoView) {
            row.scrollIntoView({ block: 'nearest' });
        }
    }

    $('#' + config.commitId).on('click', function () {
        if (typeof onCommit === 'function') {
            onCommit(chosen.slice());
        }
        $modal.modal('hide');
    });

    $modal.on('shown.bs.modal', function () {
        $search.trigger('focus');
    });

    // This dialog opens FROM another modal, and the app pins every modal and
    // backdrop to the same z-index (theme CSS), so Bootstrap's second backdrop
    // lands *under* the first modal: the form behind stays undimmed and reads
    // as still-interactive. The body class is what the stylesheet keys the
    // stacking fix off -- scoped to this state rather than applied to every
    // stacked modal in the app.
    $modal.on('show.bs.modal', function () {
        $('body').addClass('sr-picker-open');
    });

    // Bootstrap appends this dialog's backdrop to <body>, but the app pins
    // every backdrop to one z-index, so the new one lands UNDER the modal that
    // launched the picker -- leaving that form bright and looking clickable
    // while a dialog waits on it. Raise the newest backdrop once it exists.
    // Done here rather than in CSS: the two backdrops are not adjacent
    // siblings (the modals sit between them), so no selector can name "the
    // second one". Bootstrap removes the element on hide, so there is nothing
    // to undo.
    $modal.on('shown.bs.modal', function () {
        $('.modal-backdrop').last().css('z-index', 1060);
    });

    $modal.on('hidden.bs.modal', function () {
        $('body').removeClass('sr-picker-open');
        // Bootstrap removes .modal-open from <body> when ANY modal closes, so
        // the still-open modal underneath loses its scroll lock. Put it back.
        if ($('.modal.show').length) {
            $('body').addClass('modal-open');
        }
    });

    return {
        open: function (options) {
            items = options.items || [];
            // A copy, so edits inside the dialog can be abandoned.
            chosen = (options.chosen || []).map(String);
            config.facets.forEach(function (facet) {
                facetState[facet.key] = null;
            });
            cursor = 0;
            onCommit = options.onCommit || null;
            $search.val('');
            renderAll();
            $modal.modal('show');
        },
    };
}
