/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

(function () {
    'use strict';

    // Details' card defs, moved out of the bare CARD_DEFS constant into a
    // per-canvas-key lookup so Mitigation's different card set can live
    // beside it without either canvas needing to know the other exists.
    //
    // Order MUST match customization_cards_layout_card_keys()
    // (includes/functions.php), which is the canonical card order every other
    // surface -- the backfill's seeded stack, the no-Extra synthesis, and the
    // reorder migration -- derives from. This array is only the tiebreak for a
    // card with no stored geometry at all, so a mismatch is not visible day to
    // day; it is still a duplicate of a single source of truth and drifting
    // from it is how a "benign" duplicate becomes a real ordering bug later.
    var CARD_DEFS_BY_CANVAS = {
        details: [
            { key: 'general', label: _lang['CardGeneral'], icon: 'fa-circle-info' },
            { key: 'scoring', label: _lang['CardScoring'], icon: 'fa-gauge' },
            // Reuses the pre-existing 'Assignment' key -- Incident Management
            // already labels the same owner/team/stakeholders grouping with it.
            // Ordered ahead of Classification: who is on the hook reads as more
            // load-bearing than what kind of risk this is.
            { key: 'assignment', label: _lang['Assignment'], icon: 'fa-users' },
            { key: 'classification', label: _lang['CardClassification'], icon: 'fa-tags' },
            { key: 'additional_info', label: _lang['CardAdditionalInformation'], icon: 'fa-file-lines' },
            // Same catch-all Mitigation/Review already have, trailing last
            // like theirs (customization_cards_layout_card_keys(),
            // includes/functions.php) -- an admin-created custom field
            // defaults here now instead of Additional information (see
            // DEFAULT_CATCH_ALL_CARD_KEY_BY_CANVAS below and backfill_
            // customization_cards_layout()'s matching bucketing rule,
            // extras/customization/upgrade.php).
            { key: 'custom_fields', label: _lang['CardCustomFields'], icon: 'fa-puzzle-piece' }
        ],
        // Order MUST match customization_mitigation_cards_layout_card_keys()
        // (includes/functions.php) -- same reasoning the 'details' array's
        // original comment gives for its own order.
        mitigation: [
            { key: 'strategy', label: _lang['CardMitigationStrategy'], icon: 'fa-diagram-project' },
            { key: 'assignment', label: _lang['Assignment'], icon: 'fa-users' },
            { key: 'solution', label: _lang['CardMitigationSolution'], icon: 'fa-file-lines' },
            { key: 'controls', label: _lang['CardMitigationControls'], icon: 'fa-shield-halved' },
            { key: 'custom_fields', label: _lang['CardCustomFields'], icon: 'fa-puzzle-piece' }
        ],
        // Order MUST match customization_review_cards_layout_card_keys()
        // (includes/functions.php) -- same reasoning the 'details' array's
        // original comment gives for its own order.
        review: [
            { key: 'review', label: _lang['CardReview'], icon: 'fa-clipboard-check' },
            { key: 'custom_fields', label: _lang['CardCustomFields'], icon: 'fa-puzzle-piece' }
        ],
        // Assets designer (fgroup asset, tab 1). Order MUST match
        // customization_asset_cards_layout_card_keys() (includes/functions.php).
        // Same card keys, labels and icons as the risk Details cards above;
        // 'scoring' holds the Asset Scoring field here (Risk Scoring Method on
        // the risk Details canvas). The key is shared, the cards are not --
        // every layout row is scoped by fgroup.
        asset: [
            { key: 'general', label: _lang['CardGeneral'], icon: 'fa-circle-info' },
            { key: 'assignment', label: _lang['Assignment'], icon: 'fa-users' },
            { key: 'classification', label: _lang['CardClassification'], icon: 'fa-tags' },
            { key: 'scoring', label: _lang['CardScoring'], icon: 'fa-gauge' },
            { key: 'additional_info', label: _lang['CardAdditionalInformation'], icon: 'fa-file-lines' },
            { key: 'custom_fields', label: _lang['CardCustomFields'], icon: 'fa-puzzle-piece' }
        ]
    };

    // Where an uncategorized field lands, on all three canvases now: the
    // real 'custom_fields' catch-all (CARD_DEFS_BY_CANVAS above), matching
    // backfill_customization_cards_layout()'s own bucketing rule
    // (extras/customization/upgrade.php).
    var DEFAULT_CATCH_ALL_CARD_KEY_BY_CANVAS = {
        details: 'custom_fields',
        mitigation: 'custom_fields',
        review: 'custom_fields',
        asset: 'custom_fields'
    };

    // One entry per live canvas, keyed by 'details' | 'mitigation' | 'review'
    // (risk tabs 1-3) or 'asset' (asset tab 1).
    // Mirrors risk-details-form.js's instancesByContainer pattern (Phase 3)
    // for the same reason: independent canvases on one page must never let
    // one's teardown or rebuild touch another's grids/state.
    var instancesByCanvas = {};

    function getOrCreateInstance(canvasKey) {
        instancesByCanvas[canvasKey] = instancesByCanvas[canvasKey] || {
            canvasKey: canvasKey,
            cardDefs: CARD_DEFS_BY_CANVAS[canvasKey],
            topGrid: null,
            nestedGrids: {},
            fieldsByCard: {},
            buildingCanvas: false,
            cardGeometry: {},
            // Debounce state for scheduleCardFit()/runPendingFits(), kept
            // per-instance for the same reason as everything else above:
            // Details and Mitigation share card keys ('assignment',
            // 'custom_fields'), so a single shared timer/pending-set could
            // route a queued fit at the WRONG canvas's nestedGrids the
            // moment both canvases have ever scheduled one.
            fitTimer: null,
            pendingFits: {},
            fitDeferrals: 0
        };
        return instancesByCanvas[canvasKey];
    }

    // Nested-grid geometry, mirroring customization_nested_grid_columns() and
    // customization_fields_per_row() (includes/functions.php). The server seeds
    // fields two to a row at half width; anything this file creates has to
    // agree, or a picker-added field lands at a width no seeded field has.
    var NESTED_GRID_COLUMNS = 6;
    var FIELDS_PER_ROW = 2;

    // Subject is synthesized server-side (get_subject_synthetic_field_entry())
    // and has no custom_template row, so it is never removable and never part
    // of a save payload.
    var SUBJECT_FIELD_ID = '0';

    function fetchJSON(path, params) {
        return $.ajax({
            url: BASE_URL + '/api/v2' + path,
            type: 'GET',
            data: params
        }).then(function (result) {
            return result.data;
        });
    }

    function cardDefByKey(instance, key) {
        for (var i = 0; i < instance.cardDefs.length; i++) {
            if (instance.cardDefs[i].key === key) {
                return instance.cardDefs[i];
            }
        }
        return null;
    }

    // Top-grid rows a card spends on chrome rather than on field chips.
    //
    // One row (60px) for the header strip, which _customization-layout-editor
    // .scss pins to a fixed 36px precisely so it fits that budget -- see the
    // arithmetic in that file's .sr-cust-card-head rule.
    //
    // The Custom fields catch-all draws a second thing the shared budget knows
    // nothing about: the .sr-cust-card-hint line under its header. It gets a
    // second row rather than being left to overdraw and scroll.
    function cardChromeRows(instance, cardKey) {
        return cardKey === 'custom_fields' ? 2 : 1;
    }

    // Mirrors customization_card_height_for_field_count() in
    // includes/functions.php: a chip is 2 nested rows at cellHeight 30 against
    // a top grid at cellHeight 60, and the nested grid's minRow is 2.
    function cardHeightForNestedRows(instance, nestedRows, cardKey) {
        return Math.ceil(Math.max(2, nestedRows) / 2) + cardChromeRows(instance, cardKey);
    }

    /**
     * Geometry for a card the layout API returned no stored row for.
     *
     * Seeded at the MINIMUM height, never a guessed one. buildCanvas()'s fit
     * pass sizes every card to exactly what its own chips need the moment the
     * canvas is built (fitCardToContent(), below), so a guess here is
     * corrected either way -- but seeding at the minimum keeps this fallback
     * honest on its own terms rather than leaning on that later pass.
     *
     * That is not hypothetical. This fallback used to be a flat pos_h=4, and a
     * dev instance whose custom_template_card rows were missing ended up with
     * all five cards stored at 4 -- including Additional information, which
     * needs 10, and Scoring, which the server-side seed
     * (customization_card_height_for_field_count(), includes/functions.php)
     * correctly sizes at 2 for its single field. Both the "fields just stack
     * and scroll" report and the "Scoring is disproportionately tall" one trace
     * back to that one magic number.
     */
    function fallbackCardGeometry(instance, cardKey) {
        return { pos_x: 0, pos_y: 0, pos_w: 12, pos_h: cardChromeRows(instance, cardKey) + 1 };
    }

    function nestedRowsUsed(instance, cardKey) {
        var grid = instance.nestedGrids[cardKey];
        if (!grid) {
            return 0;
        }
        var bottom = 0;
        (grid.save(false) || []).forEach(function (node) {
            bottom = Math.max(bottom, (node.y || 0) + (node.h || 1));
        });
        return bottom;
    }

    // How many nested rows a card of `cardH` top-grid rows can actually show.
    // Exact inverse of cardHeightForNestedRows() above -- chrome rows come off
    // the top, and each remaining top row (60px) holds two nested rows (30px).
    // Keep the two in step: a card that reports more capacity than it grows to
    // would hide chips with no pill to say so.
    function nestedRowsAvailable(instance, cardH, cardKey) {
        return Math.max(0, ((parseInt(cardH, 10) || 0) - cardChromeRows(instance, cardKey)) * 2);
    }

    function fieldCount(instance, cardKey) {
        var grid = instance.nestedGrids[cardKey];
        return grid ? (grid.save(false) || []).length : 0;
    }

    // Chips whose bottom edge falls past what the card's CURRENT height can
    // show. Counted per field rather than per row because "3 fields don't fit"
    // is actionable to an admin in a way "6 rows don't fit" is not.
    function fieldsNotFitting(instance, cardKey) {
        var grid = instance.nestedGrids[cardKey];
        var el = cardElement(instance, cardKey);
        if (!grid || !el) {
            return 0;
        }
        var available = nestedRowsAvailable(instance, cardHeight(el), cardKey);
        var hidden = 0;
        (grid.save(false) || []).forEach(function (node) {
            if ((node.y || 0) + (node.h || 1) > available) {
                hidden++;
            }
        });
        return hidden;
    }

    // DOM lookup scoped to THIS instance's own canvas container -- was
    // hardcoded to '#details-layout-canvas', which would have silently
    // matched nothing (or, worse, matched the wrong canvas's card once two
    // canvases share card keys like 'assignment') for Mitigation.
    function cardElement(instance, cardKey) {
        return $(instance.containerSelector + ' .grid-stack-item[data-card-key="' + cardKey + '"]')[0] || null;
    }

    // A card's height in top-grid rows, read from Gridstack's own node rather
    // than the `gs-h` ATTRIBUTE: Gridstack omits that attribute entirely when
    // h === 1, so an attribute read silently yields null for a one-row card.
    // Nothing breaks today only because nestedRowsAvailable() floors both null
    // and '1' to the same 0 -- which is luck, not a guarantee.
    function cardHeight(el) {
        if (!el) {
            return 0;
        }
        if (el.gridstackNode && typeof el.gridstackNode.h === 'number') {
            return el.gridstackNode.h;
        }
        return parseInt(el.getAttribute('gs-h'), 10) || 0;
    }

    function refreshCardCount(instance, cardKey) {
        $(instance.containerSelector + ' .grid-stack-item[data-card-key="' + cardKey + '"] .sr-cust-card-count')
            .text(fieldCount(instance, cardKey));
    }

    // Size the card tile to exactly what its current chips need -- grows it
    // if content no longer fits, shrinks it if a chip was removed (dragged
    // out, deleted via its own remove control, or simply absent this load
    // because the Extra that owned it was disabled since the layout was last
    // saved). See syncCardFit()'s own docblock for why this reacts to
    // content changes only, never to a bare resize.
    function fitCardToContent(instance, cardKey) {
        var el = cardElement(instance, cardKey);
        if (!el || !instance.topGrid) {
            return;
        }
        var needed = cardHeightForNestedRows(instance, nestedRowsUsed(instance, cardKey), cardKey);
        var current = cardHeight(el);
        if (needed !== current) {
            instance.topGrid.update(el, { h: needed });
        }
    }

    // Show/hide the "N fields don't fit" pill on one card.
    //
    // Never resizes anything -- this is the half of the feedback loop that is
    // safe to run in response to the ADMIN's own card resize, where growing
    // the card back would be fighting them.
    function refreshCardOverflow(instance, cardKey) {
        var el = cardElement(instance, cardKey);
        if (!el) {
            return;
        }
        var hidden = fieldsNotFitting(instance, cardKey);
        var $card = $(el).find('> .grid-stack-item-content.sr-cust-card');

        $card.toggleClass('is-overflowing', hidden > 0);
        if (hidden > 0) {
            $card.find('.sr-cust-card-overflow')
                .attr('title', String(_lang['NFieldsDoNotFitCard'] || '').replace('{n}', hidden))
                .find('.sr-cust-card-overflow-count')
                .text(hidden);
        }
    }

    /**
     * Keeps one card honest about the fields it holds: fits its height to
     * them (grow OR shrink), then reports whatever still doesn't fit.
     *
     * WHY a fit-on-content-change rule, and why the card is not simply
     * pinned to its content's height at every possible moment:
     *
     * This is an EDITABLE grid. The admin can drag a card's own resize handle,
     * and that has to mean something -- a card that springs back to "exactly
     * as tall as its fields" the instant they let go of the handle is an
     * editor fighting its user. So a card resize is left completely alone
     * here; the pill (refreshCardOverflow()) tells them what they just hid
     * instead, which is the feedback the silent inner scrollbar never gave.
     *
     * The fit is therefore triggered by CONTENT changes only -- a field
     * added, dragged in from another card, removed, moved, or resized taller
     * -- where resizing the CARD is unambiguously a consequence of what the
     * admin just did to its fields, not a sizing gesture on the card itself.
     * Previously grow-only ("shrinking on removal would silently undo a
     * deliberately roomy card", matching Submit Risk's own auto-fit) --
     * reversed on request: a card's height now tracks its current field set
     * on every add/remove, the same way it already did for adds. An admin
     * who wants room to spare gets it the same way as before, by resizing
     * the card (a gesture this function never reacts to) AFTER arranging its
     * fields; that room just no longer survives a LATER field add/remove the
     * way it used to.
     *
     * The initial build pass -- see buildCanvas() -- runs this same fit for
     * every card regardless of interaction, which is also why a card whose
     * saved height no longer matches its current fields (the common case:
     * the Extra that owned a since-removed field was disabled after the
     * layout was last saved) self-corrects the moment the editor opens,
     * with no separate "shrink to fit" affordance needed.
     */
    function syncCardFit(instance, cardKey) {
        fitCardToContent(instance, cardKey);
        refreshCardOverflow(instance, cardKey);
    }

    function refreshAllCardOverflow(instance) {
        Object.keys(instance.nestedGrids).forEach(function (cardKey) {
            refreshCardOverflow(instance, cardKey);
        });
    }

    /**
     * Queues a fit pass for one card instead of running it inside Gridstack's
     * own event handler.
     *
     * This is NOT a performance debounce. Gridstack emits 'change' (and
     * 'added'/'removed' on the two grids either side of a cross-card drag)
     * repeatedly WHILE the pointer is still down, and growing the destination
     * card at that moment moves the drop target out from under the cursor
     * mid-gesture -- which makes the drop silently fail. Caught by the existing
     * Playwright SCENARIO-2 (cross-card drag persists the new assignment),
     * which passed before this auto-fit work and failed the moment the fit ran
     * synchronously from the event.
     *
     * So the pass waits for the gesture to actually end: Gridstack marks an
     * in-flight drag/resize with '.ui-draggable-dragging'/'.ui-resizable-
     * resizing' on the element being moved, and the timer simply re-arms while
     * either is present. The retry budget exists for ONE failure mode only:
     * Gridstack leaving that class behind and never emitting another event, at
     * which point running the pass anyway beats a card that never grows again.
     *
     * The budget therefore resets on every new event, NOT once per gesture. A
     * slow drag -- an admin holding a chip while deciding where to drop -- emits
     * events the whole way, and a per-gesture budget would tick down across all
     * of them and eventually expire mid-drag, running the fit while the pointer
     * is still down. That is precisely the cross-card-drag breakage this queue
     * exists to prevent, just reached by a slower hand.
     *
     * Timer/pending-set/deferral-count all live on `instance` (see
     * getOrCreateInstance()) rather than as module globals, so a gesture on
     * one canvas can never cause a queued fit to run against the OTHER
     * canvas's grids.
     */
    var MAX_FIT_DEFERRALS = 40; // ~5s of SILENCE at 120ms, not ~5s of gesture

    function scheduleCardFit(instance, cardKey) {
        instance.pendingFits[cardKey] = true;
        // A fresh event means Gridstack is still live, so the "it stopped
        // talking to us" budget starts over.
        instance.fitDeferrals = 0;
        if (instance.fitTimer !== null) {
            window.clearTimeout(instance.fitTimer);
        }
        instance.fitTimer = window.setTimeout(function () {
            runPendingFits(instance);
        }, 120);
    }

    function runPendingFits(instance) {
        instance.fitTimer = null;

        var gestureInFlight = document.querySelector(
            instance.containerSelector + ' .ui-draggable-dragging, ' + instance.containerSelector + ' .ui-resizable-resizing'
        );
        if (gestureInFlight && instance.fitDeferrals < MAX_FIT_DEFERRALS) {
            instance.fitDeferrals++;
            instance.fitTimer = window.setTimeout(function () {
                runPendingFits(instance);
            }, 120);
            return;
        }
        instance.fitDeferrals = 0;

        var keys = Object.keys(instance.pendingFits);
        instance.pendingFits = {};
        keys.forEach(function (cardKey) {
            if (!instance.nestedGrids[cardKey]) {
                return; // card was torn down by a rebuild while we waited
            }
            syncCardFit(instance, cardKey);
            refreshCardCount(instance, cardKey);
        });
    }

    // One field chip inside a card's nested grid. The remove control carries
    // data-main/data-text so customization.js's picker can put the field back
    // into the right hidden <select>'s list without having to look the field
    // up again (same contract as a legacy '.field-holder .delete' row).
    function addFieldChip(instance, cardKey, field) {
        var nestedGrid = instance.nestedGrids[cardKey];
        if (!nestedGrid) {
            return;
        }

        var fieldId = String(field.id);
        var label = field.display_name || field.name;
        var fieldItem = nestedGrid.addWidget({
            x: field.pos_x, y: field.pos_y, w: field.pos_w, h: field.pos_h,
            id: 'field-' + fieldId
        });

        // Set the field's class/attributes/content directly on GridStack's own
        // content div instead of replacing that div, so its drag/resize DD
        // wiring (drag between cards, the resize handle) survives -- see the
        // card widget below for the full explanation.
        var $content = $(fieldItem).find('.grid-stack-item-content')
            .addClass('sr-cust-field')
            .attr('data-field-id', fieldId)
            .empty();

        $('<span>').addClass('sr-cust-field-name').text(label).appendTo($content);

        // Subject (risk) is synthetic; a field the server marks removable:0
        // (asset AssetName) can be moved but never taken off the layout.
        var removable = String(field.removable) !== '0';
        if (fieldId !== SUBJECT_FIELD_ID && removable) {
            $('<button>')
                .attr('type', 'button')
                .addClass('sr-cust-field-remove')
                .attr('title', _lang['Remove'])
                .attr('aria-label', _lang['Remove'])
                .attr('data-field-id', fieldId)
                .attr('data-main', parseInt(field.is_basic, 10) === 1 ? '1' : '0')
                .attr('data-text', label)
                .attr('data-type', field.type || '')
                .append($('<i>').addClass('fa fa-times').attr('aria-hidden', 'true'))
                .appendTo($content);
        }
    }

    function buildCard(instance, def, geometry) {
        var widgetHtml = $('<div>').addClass('grid-stack-item-content sr-cust-card')
            .append($('<div>').addClass('sr-cust-card-head')
                .append($('<span>').addClass('sr-cust-card-ico').append($('<i>').addClass('fa ' + def.icon)))
                .append($('<span>').addClass('sr-cust-card-title').text(def.label))
                // Built once, hidden by CSS, and revealed by the card's
                // .is-overflowing class -- see refreshCardOverflow().
                .append($('<span>').addClass('sr-cust-card-overflow')
                    .append($('<i>').addClass('fa fa-triangle-exclamation').attr('aria-hidden', 'true'))
                    .append($('<span>').addClass('sr-cust-card-overflow-count')))
                .append($('<span>').addClass('sr-cust-card-count').text(0)))
            .append(def.key === 'custom_fields'
                ? $('<div>').addClass('sr-cust-card-hint').text(_lang['CardCustomFieldsHint'])
                : null)
            .append($('<div>').addClass('grid-stack sr-cust-nested-grid').attr('data-card-key', def.key));

        var item = instance.topGrid.addWidget({
            x: geometry.pos_x, y: geometry.pos_y, w: geometry.pos_w, h: geometry.pos_h,
            id: def.key
        });
        $(item).attr('data-card-key', def.key);
        // GridStack 13's default GridStack.renderCB does
        // `el.textContent = w.content` (an intentional XSS-safe default --
        // see gridstack.d.ts's renderCB doc), so passing `content` as an
        // HTML *string* renders as literal escaped text, not markup: the
        // card head/nested-grid div never become real DOM at all. Build the
        // real DOM node separately (widgetHtml) and move ITS CHILDREN into
        // GridStack's own auto-created .grid-stack-item-content div, rather
        // than replacing that div outright: GridStack's drag/resize DD is
        // wired to that specific content element at addWidget() time
        // (verified live -- a `.replaceWith()` swap here left the visual
        // replacement completely undraggable, never firing so much as a
        // '.ui-draggable-dragging' helper, while gs-x/gs-y attributes on
        // the untouched outer .grid-stack-item stayed correct), so a
        // wholesale node replacement silently drops that wiring even
        // though the card still renders and looks identical.
        $(item).find('.grid-stack-item-content').addClass('sr-cust-card').append(widgetHtml.contents());

        var nestedEl = $(item).find('.sr-cust-nested-grid')[0];
        instance.nestedGrids[def.key] = GridStack.init({
            column: NESTED_GRID_COLUMNS,
            cellHeight: 30,
            float: true,
            staticGrid: false,
            // A card that currently has zero fields renders a nested grid
            // with no rows, which collapses to 0px height -- a 0-height
            // element is not a usable drop target (verified live: a
            // synthetic drag landing on an empty card's nested grid never
            // registered a drop). minRow keeps every card's dropzone at a
            // real, hoverable height even when empty, per gridstack's own
            // docs: "minimum rows amount which is handy to prevent grid
            // from collapsing when empty."
            minRow: 2,
            // true accepts any element carrying Gridstack's own
            // auto-generated 'grid-stack-item' wrapper class -- which is
            // what every field widget added via nestedGrid.addWidget()
            // actually gets. '.sr-cust-field-item' (the brief's original
            // value) never matched anything: our field wrapper's class is
            // 'sr-cust-field' (see addFieldChip above), and acceptWidgets
            // filters the auto-generated OUTER .grid-stack-item element,
            // not the inner .grid-stack-item-content we control -- so drag
            // between cards silently never accepted a drop. Confirmed
            // against gridstack/dist/types.d.ts's GridStackOptions.acceptWidgets
            // doc: "true: will accept HTML elements having 'grid-stack-item'
            // as class attribute".
            acceptWidgets: true
        }, nestedEl);

        // CONTENT changes on this card -- a chip added, dropped in from
        // another card, removed, moved or resized -- are the only thing that
        // may resize the card tile. See syncCardFit()'s docblock for why a
        // card resize deliberately does NOT feed back into this.
        //
        // 'dropped' fires on the RECEIVING grid of a cross-card drag and
        // 'removed' on the source, so both ends of a drag are covered -- the
        // source now fits (shrinks, if nothing else needs the freed rows)
        // the same as the destination grows.
        instance.nestedGrids[def.key].on('added change removed dropped', function () {
            if (instance.buildingCanvas) {
                return; // one deterministic pass at the end of buildCanvas()
            }
            // Queued, never run inline -- these fire mid-drag, and resizing a
            // card then breaks the drop. See scheduleCardFit().
            scheduleCardFit(instance, def.key);
        });

        return instance.nestedGrids[def.key];
    }

    // Lazily materializes a curated card that buildCanvas() skipped because it
    // was empty (only 'custom_fields' is ever skipped). Returns false when the
    // canvas isn't up at all.
    function ensureCard(instance, cardKey) {
        if (!instance.topGrid) {
            return false;
        }
        if (instance.nestedGrids[cardKey]) {
            return true;
        }
        var def = cardDefByKey(instance, cardKey);
        if (!def) {
            return false;
        }
        buildCard(instance, def, instance.cardGeometry[cardKey] || fallbackCardGeometry(instance, cardKey));
        return true;
    }

    function buildCanvas(instance, container, cards, fields) {
        container.empty();
        instance.nestedGrids = {};
        instance.fieldsByCard = {};
        instance.cardGeometry = {};

        fields.forEach(function (field) {
            // A stored key this canvas has no card for (foreign / renamed) falls to the
            // catch-all instead of vanishing: only rendered fields survive the next Save.
            var key = field.card_key && cardDefByKey(instance, field.card_key)
                ? field.card_key
                : DEFAULT_CATCH_ALL_CARD_KEY_BY_CANVAS[instance.canvasKey];
            if (!instance.fieldsByCard[key]) {
                instance.fieldsByCard[key] = [];
            }
            instance.fieldsByCard[key].push(field);
        });

        cards.forEach(function (card) {
            instance.cardGeometry[card.card_key] = {
                pos_x: parseInt(card.pos_x, 10) || 0,
                pos_y: parseInt(card.pos_y, 10) || 0,
                pos_w: parseInt(card.pos_w, 10) || 12,
                pos_h: parseInt(card.pos_h, 10) || 2
            };
        });

        var topGridEl = $('<div>').addClass('grid-stack sr-cust-top-grid');
        container.append(topGridEl);

        instance.topGrid = GridStack.init({
            column: 12,
            cellHeight: 60,
            float: true,
            staticGrid: false
        }, topGridEl[0]);

        // An admin resizing a CARD must never be argued with, so this handler
        // only ever re-evaluates the pills -- it cannot call syncCardFit().
        // (No feedback loop either way: syncCardFit()'s own topGrid.update()
        // re-enters here, which does nothing but re-read the pill it just set.)
        instance.topGrid.on('resizestop change', function () {
            if (!instance.buildingCanvas) {
                refreshAllCardOverflow(instance);
            }
        });

        instance.buildingCanvas = true;
        try {
            instance.cardDefs.forEach(function (def) {
                var cardFields = instance.fieldsByCard[def.key] || [];

                // The Custom fields catch-all only appears when it currently has
                // fields in it -- matches the runtime renderer's "computed, not
                // stored" visibility rule from the spec. Its stored geometry is
                // still remembered above, so ensureCard() can place it correctly
                // if a field is added to it later in this page's life, and the
                // server re-seeds its row on save rather than dropping it.
                if (def.key === 'custom_fields' && cardFields.length === 0) {
                    return;
                }

                buildCard(instance, def, instance.cardGeometry[def.key] || fallbackCardGeometry(instance, def.key));
                cardFields.forEach(function (field) {
                    addFieldChip(instance, def.key, field);
                });
                refreshCardCount(instance, def.key);
            });
        } finally {
            instance.buildingCanvas = false;
        }

        // One deterministic fit pass, after every card and every chip exists.
        //
        // Per-card as the chips went in would have been wrong twice over: it
        // would grow a card from a half-populated row count, and each grow
        // pushes the cards below it down, so the cards would have been
        // re-laid-out repeatedly mid-build. Walking them in stored pos_y order
        // means each card is grown into space the cards above it have already
        // finished claiming.
        //
        // Purely arithmetic -- nestedRowsUsed() reads GridStack's own node
        // records, never a measured DOM box -- so unlike submit-risk.js's
        // auto-fit this needs no deferral past a render cycle to be correct.
        Object.keys(instance.nestedGrids)
            .sort(function (a, b) {
                return (instance.cardGeometry[a] ? instance.cardGeometry[a].pos_y : 0) - (instance.cardGeometry[b] ? instance.cardGeometry[b].pos_y : 0);
            })
            .forEach(function (cardKey) {
                syncCardFit(instance, cardKey);
            });
    }

    function serializeLayout(instance) {
        var cards = [];
        (instance.topGrid.save(false) || []).forEach(function (node) {
            cards.push({ card_key: node.id, pos_x: node.x, pos_y: node.y, pos_w: node.w, pos_h: node.h });
        });

        var fields = [];
        Object.keys(instance.nestedGrids).forEach(function (cardKey) {
            (instance.nestedGrids[cardKey].save(false) || []).forEach(function (node) {
                var fieldId = String(node.id).replace('field-', '');
                if (fieldId === SUBJECT_FIELD_ID) {
                    return; // Subject's synthetic id=0 is not a real custom_template row
                }
                fields.push({ id: fieldId, card_key: cardKey, pos_x: node.x, pos_y: node.y, pos_w: node.w, pos_h: node.h });
            });
        });

        return { cards: cards, fields: fields };
    }

    function initCanvas(containerSelector, canvasKey) {
        var container = $(containerSelector);
        if (container.length === 0) {
            return;
        }

        // Stamped here rather than relying solely on Task 5's static markup:
        // the field-remove click handler below resolves which instance owns
        // a clicked chip via the closest [data-canvas-key] ancestor, and
        // this canvas's own container is that ancestor. Setting it
        // programmatically means Details keeps working today even before
        // any markup adds the attribute, and Mitigation gets it for free the
        // moment its container exists -- a static duplicate in markup later
        // is harmless (same value, set twice).
        container.attr('data-canvas-key', canvasKey);

        var instance = getOrCreateInstance(canvasKey);
        instance.containerSelector = containerSelector;

        var fgroup = container.data('fgroup');
        var templateGroupId = container.data('template-group-id');
        var tabIndex = container.data('tab-index');
        // Used by the field-remove handler below to put a removed "main"
        // field back into the right tab's hidden '#main_field_<N>' select
        // (that select is per-tab; '#custom_fields' is shared across all
        // tabs for the fgroup, so it needs no such scoping).
        instance.tabIndex = tabIndex;
        instance.fgroup = fgroup;

        $.when(
            fetchJSON('/customization/fields', { fgroup: fgroup, template_group_id: templateGroupId, tab_index: tabIndex }),
            fetchJSON('/customization/layout', { fgroup: fgroup, template_group_id: templateGroupId, tab_index: tabIndex })
        ).done(function (fields, cards) {
            buildCanvas(instance, container, cards, fields);
        }).fail(function () {
            // showAlertFromMessage(message, success) is the supported shape --
            // alert-helper.js's showAlertsFromArray() expects a JSON *string* of
            // {alert_type, alert_message} objects (or a status_message payload from
            // the server) and does not understand a raw {type, text} object literal.
            showAlertFromMessage(_lang['RequestFailed'], false);
        });
    }

    // Resolves the live canvas instance for a tab index, optionally narrowed by
    // fgroup. Keyed off what initCanvas() actually built (instance.tabIndex /
    // instance.fgroup) instead of a hardcoded tab -> canvas map, so risk tabs
    // 1-3 and the asset tab 1 coexist without a per-fgroup table. A page only
    // ever renders one fgroup's canvases, so the fgroup argument is optional.
    function findInstance(tabIndex, fgroup) {
        var keys = Object.keys(instancesByCanvas);
        for (var i = 0; i < keys.length; i++) {
            var candidate = instancesByCanvas[keys[i]];
            if (String(candidate.tabIndex) === String(tabIndex) && (!fgroup || candidate.fgroup === fgroup)) {
                return candidate;
            }
        }
        return null;
    }

    // Adds a field the picker just handed us into the given tab's canvas.
    // Returns true when taken (caller removes the <option>), false when
    // there is no canvas for that tabIndex (caller falls back to its legacy
    // panel append) -- same contract addFieldToDetailsLayout() had, now
    // dispatched by tabIndex instead of being Details-only.
    window.addFieldToLayoutCanvas = function (tabIndex, field, fgroup) {
        var instance = findInstance(tabIndex, fgroup);
        var canvasKey = instance ? instance.canvasKey : null;
        if (!instance || !instance.topGrid || !field || !field.id) {
            return false;
        }

        var fieldId = String(field.id);
        if ($(instance.containerSelector + ' .sr-cust-field[data-field-id="' + fieldId + '"]').length) {
            return true;
        }

        // All three canvases carry a real 'custom_fields' catch-all card key
        // now, so a freshly-picked field always has the same obvious landing
        // spot -- see customization_cards_layout_card_keys()'s docblock
        // (includes/functions.php), same as an uncategorized field the
        // backfill places (DEFAULT_CATCH_ALL_CARD_KEY_BY_CANVAS above).
        var cardKey = DEFAULT_CATCH_ALL_CARD_KEY_BY_CANVAS[canvasKey];
        if (!ensureCard(instance, cardKey)) {
            return false;
        }

        addFieldChip(instance, cardKey, {
            id: fieldId,
            display_name: field.name,
            name: field.name,
            is_basic: field.is_basic,
            type: field.type,
            pos_x: 0,
            pos_y: nestedRowsUsed(instance, cardKey),
            pos_w: NESTED_GRID_COLUMNS / FIELDS_PER_ROW,
            pos_h: 2
        });
        syncCardFit(instance, cardKey);
        refreshCardCount(instance, cardKey);

        return true;
    };

    // Removing a chip is purely client-side: the field is simply excluded from
    // the next Save's `fields` array, and save_customization_layout() deletes
    // any custom_template row for this scope that the payload omits. The
    // <option> goes back into the hidden select the picker builds its columns
    // from, exactly as the legacy '.field-holder .delete' handler does.
    $(document).on('click', '.sr-cust-field-remove', function (event) {
        event.preventDefault();
        event.stopPropagation();

        var $button = $(this);
        var fieldId = $button.attr('data-field-id');
        if (!fieldId || fieldId === SUBJECT_FIELD_ID) {
            return;
        }

        // Resolve which canvas (and therefore which instance's grids) this
        // chip belongs to -- Details and Mitigation share card keys like
        // 'custom_fields', so looking `nestedGrids[cardKey]` up on shared
        // module state would silently reach into the wrong canvas.
        var canvasKey = $button.closest('[data-canvas-key]').attr('data-canvas-key');
        var instance = canvasKey ? instancesByCanvas[canvasKey] : null;
        if (!instance) {
            return;
        }

        var $item = $button.closest('.grid-stack-item');
        var cardKey = $button.closest('.sr-cust-nested-grid').attr('data-card-key');
        var grid = instance.nestedGrids[cardKey];
        if (!grid || !$item.length) {
            return;
        }

        var $select = $button.attr('data-main') === '1' ? $('#main_field_' + instance.tabIndex) : $('#custom_fields');
        if ($select.length && !$select.find('option[value="' + fieldId + '"]').length) {
            $('<option>')
                .text($button.attr('data-text') || '')
                .val(fieldId)
                .attr('data-type', $button.attr('data-type') || '')
                .appendTo($select);
        }

        grid.removeWidget($item[0], true);
        instance.removedCount = (instance.removedCount || 0) + 1;
        refreshCardCount(instance, cardKey);
        // Re-fits the card to its now-smaller field set (shrinking it if
        // nothing else still needs the freed rows) and retires any overflow
        // pill the removal fixed. See syncCardFit()'s own docblock.
        syncCardFit(instance, cardKey);
    });

    // Gridstack builds its resize handles itself, at init/addWidget time and
    // again as widgets are dragged between grids, so there is no single moment
    // at which every handle exists to be labelled. Stamping the title on first
    // hover covers all of them -- including handles on chips that do not exist
    // yet -- and lands well inside the browser's own ~1s tooltip delay, so the
    // tooltip still appears on the very first hover. Every canvas shares the
    // identical tooltip behavior, so one delegated handler covers them all.
    //
    // Matched on the '[data-canvas-key]' attribute initCanvas() stamps rather
    // than on a hand-maintained list of canvas ids: the id list was
    // '#details-layout-canvas, #mitigation-layout-canvas' and adding Review
    // (Phase 4c-i) silently skipped it, so Review's resize handles had no
    // tooltip at all. Nothing else on the page carries that attribute.
    $(document).on('mouseenter', '[data-canvas-key] .ui-resizable-se', function () {
        if (!this.getAttribute('title')) {
            this.setAttribute('title', _lang['DragToResize']);
        }
    });

    // Degrades to a resolved promise rather than throwing when there is no
    // canvas for tabIndex: this is called unconditionally by '#save_template',
    // which is the shared Save button for EVERY fgroup (risk and asset render
    // canvases), and a synchronous throw there would leave that handler's
    // `loading` flag stuck true and kill the Save button for the rest of the
    // page's life.
    window.saveLayoutCanvas = function (tabIndex, fgroup) {
        var instance = findInstance(tabIndex, fgroup);
        if (!instance || !instance.topGrid) {
            return $.Deferred().resolve().promise();
        }

        var container = $(instance.containerSelector);
        var payload = serializeLayout(instance);
        // Only a deliberate removal in this page view lets the server accept a
        // bulk removal (asset scope refuses one otherwise).
        if (instance.removedCount > 0) {
            payload.confirm_remove = 1;
        }

        return $.ajax({
            url: BASE_URL + '/api/v2/customization/layout?fgroup=' + encodeURIComponent(container.data('fgroup'))
                + '&template_group_id=' + encodeURIComponent(container.data('template-group-id'))
                + '&tab_index=' + encodeURIComponent(container.data('tab-index')),
            type: 'POST',
            data: payload
        }).done(function () {
            // The removals are now stored; a later save in this page view must
            // not carry their confirmation forward to an unrelated bulk loss.
            // .done() returns the same jqXHR, so callers see no difference.
            instance.removedCount = 0;
        });
    };

    $(function () {
        initCanvas('#details-layout-canvas', 'details');
        initCanvas('#mitigation-layout-canvas', 'mitigation');
        initCanvas('#review-layout-canvas', 'review');
        initCanvas('#asset-layout-canvas', 'asset');
    });
})();
