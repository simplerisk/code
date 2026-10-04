/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// ----------------------------------------------------------------------
// Mitigation Controls: the Mitigation tab's control-selection field (a
// chips field opening the shared faceted picker, design-system.md §5 --
// the same engine, js/simplerisk/sr-faceted-picker.js, Document Program's
// control_ids[] field already uses) plus the redesigned Mitigation
// Controls table -- an "Optional / advanced accordion" (§5) EMBEDDED right
// below the picker, inside the same "Mitigation Controls" card, the same
// "selector plus its own data, together, full width" shape RiskScoringMethod/
// RiskScoringHistory already use on the Details tab. Both used to be
// duplicated (as a plain-HTML-blob accordion) inside both
// risk-details-form.js's edit mode and risk-details-view.js's read mode;
// split out here as one shared module instead.
//
// Pieces exposed on window.RiskMitigationControls:
//   - buildField()/initField()/applyPrefill(): the picker/chips field,
//     called from risk-details-form.js's buildMitigationControlsWidget()/
//     initMitigationControlsField()/applyPrefillValue() (edit mode only --
//     read mode renders its own plain chip list instead, via
//     risk-details-view.js's generic entry.names chip path, the same
//     mechanism Tags/RiskMapping already use; the names are resolved
//     server-side, api/v2/includes/api.php's mitigation_controls
//     resolver, not by this module).
//   - buildTableSection()/initTableSection(): the embedded table. Building
//     is separate from init because the CALLER (risk-details-form.js) needs
//     to attach its own shown.bs.collapse/hidden.bs.collapse listeners
//     (the GridStack grow/shrink sweep, exactly like buildAdvanced()'s CVSS
//     Advanced Metrics accordion) between the two calls; read mode has no
//     GridStack and skips straight to initTableSection() once built.
// The table always fetches its OWN fresh list of ATTACHED controls from the
// server (GET /risks/{id}/mitigations/controls) rather than reacting live
// to the picker's in-progress edits, since a control the picker just added
// isn't actually attached (and so can't have its validation edited -- see
// saveMitigationControlValidation(), includes/api.php) until the mitigation
// form's own Save button persists it. The table simply reflects whatever
// was last saved; it does not try to preview an unsaved selection.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    function esc(value) {
        return escapeHtml(value === undefined || value === null ? '' : String(value));
    }

    // ------------------------------------------------------------------
    // Roster -- fetched once per page (memoized promise), shared by the
    // picker (needs every control + the framework/family facet lists) and
    // by applyPrefill() (needs id -> control lookup to build chip labels).
    // ------------------------------------------------------------------
    var rosterPromise = null;
    var roster = [];
    var rosterById = {};
    var rosterFrameworks = [];
    var rosterFamilies = [];

    function loadRoster() {
        if (!rosterPromise) {
            // Shares Document Program's/Define Exceptions' own governance
            // control roster endpoint (governance/documentation.php,
            // governance/document_exceptions.php) rather than a separate
            // duplicate query -- include_facets=1 additionally resolves the
            // framework/family NAME lists this picker's facet columns need
            // (see api_v2_governance_control_roster()'s own docblock,
            // api/v2/includes/governance.php).
            rosterPromise = $.ajax({
                url: BASE_URL + '/api/v2/governance/controls/roster',
                type: 'GET',
                data: { include_facets: 1 }
            }).done(function (res) {
                var data = (res && res.data) ? res.data : {};
                roster = data.controls || [];
                rosterFrameworks = data.frameworks || [];
                rosterFamilies = data.families || [];
                rosterById = {};
                roster.forEach(function (c) { rosterById[String(c.id)] = c; });
            }).fail(function () {
                // Not cached: the next call (picker open, the next form
                // render) asks again instead of failing for the page's life.
                rosterPromise = null;
            });
        }
        return rosterPromise;
    }

    // The roster control for an id, or null (not loaded yet, or not a live
    // control). For callers that build their own <option>s (asset-card-
    // profile.js keeps ids the roster cannot name instead of dropping them).
    function findControl(id) {
        return rosterById[String(id)] || null;
    }

    // ------------------------------------------------------------------
    // Chips field -- same .sr-chips-field/.sr-chip/.sr-chips-add shape as
    // governance/documentation.php's renderControlChips()/
    // populateControlOptionsFromIds(), reproduced here (not called
    // directly: that's page-local script on a different page, not
    // guaranteed loaded here -- same function-reachability reasoning
    // risk-details-form.js's own renderSelectionChips() docblock gives).
    // ------------------------------------------------------------------

    function populateOptionsFromIds($select, ids) {
        var idStrings = (ids || []).map(String);
        $select.empty();
        idStrings.forEach(function (id) {
            var control = rosterById[id];
            if (control) {
                $select.append($('<option>', { value: control.id, text: control.short_name, selected: true }));
            }
        });
        renderChips($select);
    }

    function renderChips($select) {
        if (!$select.length) {
            return;
        }

        var $field = $select.next('.sr-chips-field');
        if (!$field.length) {
            $field = $('<div>', { 'class': 'sr-chips-field' }).insertAfter($select);
        }
        $field.empty();

        $select.find('option:selected').each(function () {
            var $option = $(this);
            var $chip = $('<span>', { 'class': 'sr-chip', text: $option.text() });
            $('<button>', {
                type: 'button',
                'class': 'sr-chip-remove',
                'data-mitigation-control-id': $option.val(),
                'aria-label': L('Remove'),
                html: '&times;'
            }).appendTo($chip);
            $field.append($chip);
        });

        $('<button>', {
            type: 'button',
            'class': 'sr-chips-add',
            'data-mitigation-control-picker-for': $select.attr('id'),
            text: L('AddOrRemoveControls')
        }).appendTo($field);

        syncTableFromSelect($select);
    }

    // Keeps the embedded Control Validation table (buildTableSection()/
    // initTableSection(), below) showing exactly what's currently selected
    // -- called from renderChips() above, so both callers that change the
    // <select>'s value (the picker's onCommit and the chip-remove handler
    // just below) update the table the same way, immediately, without
    // waiting for the mitigation form's own Save button. A no-op when the
    // table section isn't in this field's wrapper at all (a viewer without
    // canEditMitigation never gets one built -- see buildMitigationControlsWidget(),
    // risk-details-form.js).
    function syncTableFromSelect($select) {
        var $tableBody = $select.closest('.sr-mitigation-controls-field')
            .find('.sr-mitigation-controls-accordion .accordion-collapse');
        var sync = $tableBody.data('syncSelection');
        if (typeof sync === 'function') {
            sync(($select.val() || []).map(String));
        }
    }

    $(document).on('click', '.sr-chips-field .sr-chip-remove[data-mitigation-control-id]', function () {
        var $select = $(this).closest('.sr-chips-field').prev('select');
        var removedId = String($(this).attr('data-mitigation-control-id'));
        $select.find('option').filter(function () {
            return String(this.value) === removedId;
        }).prop('selected', false);
        renderChips($select);
    });

    $(document).on('click', '.sr-chips-add[data-mitigation-control-picker-for]', function () {
        var $select = $('#' + $(this).attr('data-mitigation-control-picker-for'));
        if ($select.length) {
            openPicker($select);
        }
    });

    // ------------------------------------------------------------------
    // Picker modal -- built once, lazily, in JS: this page's canvas is
    // entirely client-rendered (unlike governance/documentation.php, which
    // has a static PHP partial to render the shell into), so the shell
    // itself is built here rather than duplicated as a PHP partial nobody
    // else on this page needs. Framework + Family facets, same shape as
    // Document Program's own getDocumentControlPicker() -- the reference
    // implementation this mirrors.
    // ------------------------------------------------------------------

    var pickerModalId = 'mitigation-control-picker';
    var picker = null;

    function buildPickerModal() {
        if ($('#' + pickerModalId).length) {
            return;
        }

        var $modal = $('<div>', {
            id: pickerModalId,
            'class': 'modal fade sr-modal sr-picker-modal',
            tabindex: '-1',
            'aria-hidden': 'true',
            'aria-labelledby': pickerModalId + '-title'
        });

        var $header = $('<div>', { 'class': 'modal-header' })
            .append($('<span>', { 'class': 'sr-modal-icon' }).append($('<i>', { 'class': 'fa fa-list-check', 'aria-hidden': 'true' })))
            .append($('<h5>', { 'class': 'modal-title', id: pickerModalId + '-title', text: L('ChooseControls') }))
            .append($('<button>', { type: 'button', 'class': 'btn-close', 'data-bs-dismiss': 'modal', 'aria-label': L('Close') }));

        // 'autocomplete' set via .attr() AFTER construction, not as a key in
        // the $('<input>', {...}) attributes object -- jQuery UI is loaded
        // sitewide (header.php), and $.fn.autocomplete exists as its widget
        // factory, so jQuery's attribute-object constructor treats an
        // `autocomplete` KEY as a plugin METHOD CALL (`.autocomplete('off')`)
        // instead of setting the HTML attribute, throwing "cannot call
        // methods on autocomplete prior to initialization" the moment this
        // input is built (confirmed live -- it aborted buildPickerModal()
        // before the modal was ever appended to <body>, so the picker never
        // opened at all).
        var $search = $('<div>', { 'class': 'sr-picker-search' })
            .append($('<i>', { 'class': 'fa fa-magnifying-glass sr-picker-search-icon', 'aria-hidden': 'true' }))
            .append($('<input>', { type: 'text', id: pickerModalId + '-search', 'class': 'sr-picker-search-input', placeholder: L('SearchControlsPlaceholder'), 'aria-label': L('SearchControlsPlaceholder') }).attr('autocomplete', 'off'))
            .append($('<span>', { 'class': 'sr-picker-scope', id: pickerModalId + '-scope' }));

        function facetPane(step, labelKey, facetKey, allLabelKey, containerId) {
            return $('<div>', { 'class': 'sr-picker-pane sr-picker-pane--facet' })
                .append(
                    $('<div>', { 'class': 'sr-picker-pane-head' })
                        .append($('<span>', { 'class': 'sr-picker-step', text: step }))
                        .append($('<span>', { text: L(labelKey) }))
                        .append($('<button>', { type: 'button', 'class': 'sr-picker-clear', 'data-picker-clear': facetKey, text: L('Clear') }))
                )
                .append(
                    $('<div>', { 'class': 'sr-picker-scroll', id: containerId })
                        .append(
                            $('<button>', { type: 'button', 'class': 'sr-picker-facet', 'data-picker-facet': facetKey, 'data-picker-value': '', 'aria-pressed': 'true' })
                                .append($('<span>', { 'class': 'sr-picker-facet-label', text: L(allLabelKey) }))
                                .append($('<span>', { 'class': 'sr-picker-facet-count' }))
                        )
                );
        }

        var $frameworkPane = facetPane('1', 'Framework', 'framework', 'AllFrameworks', pickerModalId + '-frameworks');
        var $familyPane = facetPane('2', 'ControlFamily', 'family', 'AllFamilies', pickerModalId + '-families');

        rosterFrameworks.forEach(function (row) {
            $('<button>', { type: 'button', 'class': 'sr-picker-facet', 'data-picker-facet': 'framework', 'data-picker-value': row.value })
                .append($('<span>', { 'class': 'sr-picker-facet-label', text: row.name }))
                .append($('<span>', { 'class': 'sr-picker-facet-count' }))
                .appendTo($frameworkPane.find('.sr-picker-scroll'));
        });
        rosterFamilies.forEach(function (row) {
            $('<button>', { type: 'button', 'class': 'sr-picker-facet', 'data-picker-facet': 'family', 'data-picker-value': row.value })
                .append($('<span>', { 'class': 'sr-picker-facet-label', text: row.name }))
                .append($('<span>', { 'class': 'sr-picker-facet-count' }))
                .appendTo($familyPane.find('.sr-picker-scroll'));
        });

        var $listPane = $('<div>', { 'class': 'sr-picker-pane sr-picker-pane--list' })
            .append(
                $('<div>', { 'class': 'sr-picker-pane-head' })
                    .append($('<span>', { 'class': 'sr-picker-step', text: '3' }))
                    .append($('<span>', { text: L('Controls') }))
                    .append($('<span>', { 'class': 'sr-picker-pane-count', id: pickerModalId + '-count' }))
            )
            .append($('<div>', { 'class': 'sr-picker-scroll', id: pickerModalId + '-list', role: 'listbox', 'aria-multiselectable': 'true', 'aria-label': L('Controls') }));

        var $selectedPane = $('<div>', { 'class': 'sr-picker-pane sr-picker-pane--selected' })
            .append(
                $('<div>', { 'class': 'sr-picker-pane-head' })
                    .append($('<span>', { text: L('Selected') }))
                    .append($('<span>', { 'class': 'sr-picker-pane-count', id: pickerModalId + '-selected-count' }))
            )
            .append($('<div>', { 'class': 'sr-picker-scroll sr-picker-selected', id: pickerModalId + '-selected' }));

        var $footer = $('<div>', { 'class': 'modal-footer sr-picker-foot' })
            .append($('<span>', { 'class': 'sr-picker-hint', text: L('PickerKeyboardHint') }))
            .append($('<button>', { type: 'button', 'class': 'btn btn-dark', 'data-bs-dismiss': 'modal', text: L('Cancel') }))
            .append($('<button>', { type: 'button', 'class': 'btn btn-submit', id: pickerModalId + '-commit', text: L('UseTheseControls') }));

        $modal.append(
            $('<div>', { 'class': 'modal-dialog modal-xl modal-dialog-centered' }).append(
                $('<div>', { 'class': 'modal-content' })
                    .append($header)
                    .append($search)
                    .append($('<div>', { 'class': 'sr-picker-panes' }).append($frameworkPane).append($familyPane).append($listPane).append($selectedPane))
                    .append($footer)
            )
        );

        $('body').append($modal);
    }

    function getPicker() {
        if (!picker) {
            buildPickerModal();
            picker = createFacetedPicker({
                modalId: pickerModalId,
                searchId: pickerModalId + '-search',
                listId: pickerModalId + '-list',
                selectedId: pickerModalId + '-selected',
                countId: pickerModalId + '-count',
                selectedCountId: pickerModalId + '-selected-count',
                scopeId: pickerModalId + '-scope',
                commitId: pickerModalId + '-commit',
                facets: [
                    {
                        key: 'framework',
                        container: pickerModalId + '-frameworks',
                        itemValues: function (c) { return (c.frameworks || []).map(Number); }
                    },
                    {
                        key: 'family',
                        container: pickerModalId + '-families',
                        itemValues: function (c) { return [Number(c.family || 0)]; }
                    }
                ],
                itemId: function (c) { return c.id; },
                itemNumber: function (c) { return c.control_number || ''; },
                itemName: function (c) { return c.short_name || ''; },
                itemHover: function (c) { return c.description ? (c.short_name + '\n\n' + c.description) : c.short_name; },
                searchText: function (c) { return (c.control_number || '') + ' ' + (c.short_name || ''); },
                emptyText: L('NoControlsMatchFilters'),
                nothingSelectedText: L('NoControlsSelectedYet'),
                allScopeText: L('AllControls'),
                removeLabel: L('Remove')
            });
        }
        return picker;
    }

    function openPicker($select) {
        loadRoster().done(function () {
            getPicker().open({
                items: roster,
                chosen: ($select.val() || []).map(String),
                onCommit: function (ids) {
                    populateOptionsFromIds($select, ids);
                }
            });
        });
    }

    // ------------------------------------------------------------------
    // Public field API -- called from risk-details-form.js.
    // ------------------------------------------------------------------

    function buildField($select) {
        $select.addClass('sr-picker-value');
        loadRoster();
        return $('<div>').addClass('sr-mitigation-controls-field').append($select);
    }

    function initField($wrapper) {
        renderChips($wrapper.find('select'));
    }

    function applyPrefill($wrapper, ids) {
        var $select = $wrapper.find('select');
        loadRoster().always(function () {
            populateOptionsFromIds($select, ids);
        });
    }

    // ------------------------------------------------------------------
    // Mitigation Controls table -- embedded, collapsed-by-default section
    // BELOW the picker field, inside the SAME "Mitigation Controls" card
    // (design-system.md §5's "Optional / advanced accordion", the same
    // real Bootstrap .accordion/.collapse shell CVSS's Advanced Metrics
    // uses -- not the top-level §6d disclosure pattern, since this now
    // lives INSIDE an existing card rather than standing as its own).
    // Fetched lazily on first expand.
    //
    // Split in two: buildTableSection() builds the (empty) markup only --
    // callers that need it (risk-details-form.js, for the GridStack
    // grow/shrink sweep on shown.bs.collapse/hidden.bs.collapse, the exact
    // wiring buildAdvanced()'s own CVSS accordion uses) attach their own
    // listeners to the real `collapse` DOM events between the two calls.
    // initTableSection() does the actual lazy-fetch-on-first-expand and
    // row-click wiring, and needs `riskId`, which isn't known at field-
    // BUILD time (create-mode instances have none yet) -- so it's a
    // separate call, made once riskId is known (this page's Mitigation tab
    // only ever renders for an existing risk, so riskId is always real by
    // the time this runs).
    // ------------------------------------------------------------------

    var tableSectionCounter = 0;

    function statusMeta(row) {
        var hasAny = row.has_validation_details || row.validation_owner || row.validation_mitigation_percent > 0;
        var isComplete = row.has_validation_details && row.validation_mitigation_percent >= 100;
        if (isComplete) {
            return { cls: 'sr-state-success', text: L('Complete') };
        }
        if (hasAny) {
            return { cls: 'sr-state-info', text: L('InProgress') };
        }
        return { cls: 'sr-state-neutral', text: L('NotStarted') };
    }

    // A control the picker just added isn't attached server-side yet (see
    // this file's own module docblock), so it has no validation row to show
    // -- synthesized here from the roster (already loaded for the picker)
    // so the table still shows its number/name immediately, matching "when
    // you add a mitigation control, it gets added in the table". No edit
    // action: saveMitigationControlValidation() would 400 on a control
    // that isn't actually attached yet (see that function's own attachment
    // check, includes/api.php) -- the row becomes editable once the
    // mitigation form's Save button persists it.
    function pendingRow(controlId) {
        var control = rosterById[String(controlId)];
        return {
            control_id: controlId,
            control_number: control ? control.control_number : '',
            short_name: control ? control.short_name : String(controlId),
            family_short_name: '',
            validation_owner_name: '',
            validation_mitigation_percent: 0,
            has_validation_details: false,
            validation_owner: 0,
            pending: true
        };
    }

    function rowHtml(row, canEdit) {
        var status = statusMeta(row);
        // Trailing action cluster (§6b's shared row-actions component,
        // js/simplerisk/sr-row-actions-menu.js + _tables.scss's
        // .sr-row-actions-wrap/.sr-row-actions-toggle/.sr-row-actions
        // markup) -- the SAME shared classes Compliance/Governance/Plan
        // Projects use, not a page-local reinvention. At full width the
        // cluster (view + edit) sits inline; at the compact tier
        // (@media max-width: 1400px, generic to every .sr-table-card) OR on
        // a touch pointer (@media hover:none), it collapses behind a single
        // "..." toggle -- design-system.md's established narrow-width
        // pattern, wired up by SRRowActionsMenu.bind() in initTableSection()
        // below. `.sr-mitigation-controls-action` rides alongside the
        // shared `.sr-row-action` class purely so THIS module's own click
        // delegation (initTableSection()) can target its buttons without
        // also catching some other cluster's -- it carries no styling of
        // its own.
        //
        // (Earlier attempt: a single bespoke .sr-mitigation-controls-action
        // button with no .sr-row-actions-wrap/.sr-row-actions-toggle
        // markup. That NEVER implemented the overflow-tier's required
        // companion structure, so the generic `@media (hover: none)`
        // inclusion of sr-row-actions-overflow-tier -- which every
        // .sr-table-card gets automatically -- still tried to collapse it
        // behind a menu that didn't exist, rendering the button at 0x0 and
        // genuinely unclickable on any touch device. Building the real
        // component properly, as this version does, is what makes that
        // tier behave instead of break.)
        //
        // Always rendered when the row itself is real (not pending) --
        // reaching this table at all already required
        // canSelectMitigationControls (governance), so anyone seeing rows
        // has at least VIEW access to them: the eye icon is unconditional.
        // canEdit (plan_mitigations) ADDS a second, separate pencil icon
        // rather than replacing the eye -- deliberately two distinct
        // affordances, not one icon that changes meaning by permission.
        // Reasons (confirmed with the user): (1) an editor can still open
        // the read-only view without risking an accidental in-place edit,
        // and (2) an editor can see exactly what a view-only teammate sees.
        // Both buttons open the SAME modal (openValidationModal()'s own
        // readOnly param); only data-mode differs. Pending (not-yet-saved)
        // rows get neither -- there's nothing to view or edit until the
        // mitigation form's Save button persists the attachment.
        // sr-actions-col-sticky (alongside the shared sr-actions-col) pins
        // this column to the right edge of .sr-table-scroll's horizontal
        // scroll -- same pattern review-risk.js's own 'actions' column def
        // uses for #review_risk_table (scss/modules/_tables.scss's own
        // comment there), reproduced here rather than reused directly
        // (page-scoped). Needed on the <td> too, not just the <th> below --
        // DataTables never copies a header's class onto its body cells.
        var actionsCell;
        if (row.pending) {
            actionsCell = '<td class="sr-actions-col sr-actions-col-sticky"></td>';
        } else {
            var viewButton = '<button type="button" class="sr-row-action sr-mitigation-controls-action" data-mode="view" title="' + esc(L('ViewControlValidation')) + '" aria-label="' + esc(L('ViewControlValidation')) + '">' +
                    '<i class="fa fa-eye" aria-hidden="true"></i>' +
                '</button>';
            var editButton = canEdit
                ? '<button type="button" class="sr-row-action sr-mitigation-controls-action" data-mode="edit" title="' + esc(L('EditControlValidation')) + '" aria-label="' + esc(L('EditControlValidation')) + '">' +
                        '<i class="fa fa-pen" aria-hidden="true"></i>' +
                    '</button>'
                : '';
            var actionsLabel = esc(L('Actions'));
            actionsCell = '<td class="sr-actions-col sr-actions-col-sticky">' +
                '<span class="sr-row-actions-wrap">' +
                    '<button type="button" class="sr-row-actions-toggle" aria-expanded="false" aria-haspopup="true" aria-label="' + actionsLabel + '" title="' + actionsLabel + '">' +
                        '<i class="fa fa-ellipsis" aria-hidden="true"></i>' +
                    '</button>' +
                    '<span class="sr-row-actions">' + viewButton + editButton + '</span>' +
                '</span>' +
            '</td>';
        }
        // Number + short name + family folded into ONE wrapping column
        // (design-system.md §6b's compact-tier rule: "fold related columns
        // into one cell where the pair is really one fact" + "let the name
        // column take the slack") -- these three were three separate,
        // roughly-fixed-width columns fighting the control's own long name
        // for space, forcing a horizontal scrollbar at the card's normal
        // width. One wrapping column lets the row grow TALLER instead of
        // the table growing WIDER than its card.
        // short_name already carries "{number}: {description}" as one
        // string (the SAME field the picker's own chips/list rows show
        // verbatim, js/simplerisk/sr-faceted-picker.js) -- prepending
        // control_number again here duplicated it ("AAT-01.1: AAT-01.1:
        // ...", confirmed live). Falls back to the bare number only for a
        // pending row whose roster lookup came up empty (pendingRow()'s
        // own short_name default).
        var controlCell = '<td>' +
            '<div class="sr-mitigation-controls-name">' + esc(row.short_name || row.control_number) + '</div>' +
            (row.family_short_name ? '<div class="sr-mitigation-controls-family">' + esc(row.family_short_name) + '</div>' : '') +
        '</td>';
        return '<tr data-control-id="' + esc(row.control_id) + '">' +
            controlCell +
            '<td>' + esc(row.validation_owner_name) + '</td>' +
            '<td class="sr-num">' + esc(row.validation_mitigation_percent) + '%</td>' +
            '<td><span class="sr-state-pill ' + status.cls + '">' + esc(status.text) + '</span></td>' +
            actionsCell +
            '</tr>';
    }

    function buildTableSection() {
        var idPrefix = 'mitigation-controls-table-' + (++tableSectionCounter);
        var bodyId = idPrefix + '-body';

        var $toggle = $('<button>', { type: 'button', 'class': 'sr-qcard-head sr-qacc-head accordion-button collapsed', 'data-bs-toggle': 'collapse', 'data-bs-target': '#' + bodyId, 'aria-expanded': 'false' })
            .append($('<span>', { 'class': 'sr-qcard-htext' }).append($('<h6>').text(L('ControlValidation'))));

        // .sr-table-card is required here, not decorative: _tables.scss
        // scopes EVERY table/row/pill/empty-state rule (table.sr-table,
        // .sr-table-empty, .sr-caret-col, etc.) as a descendant of
        // .sr-table-card -- without that ancestor class this table would
        // render with none of it (confirmed live: unstyled, no sticky
        // header, no borders). --no-border (this file's own modifier,
        // scss/modules/_tabs.scss) drops the shell's own background/border/
        // shadow, since the .sr-qaccordion this sits inside already reads
        // as the card -- a second nested white box would double-frame it.
        var $body = $('<div>', { id: bodyId, 'class': 'accordion-collapse collapse' })
            .append($('<div>', { 'class': 'sr-mitigation-controls-body sr-table-card sr-table-card--no-border' }));

        var $section = $('<div>', { 'class': 'sr-qaccordion sr-mitigation-controls-accordion' })
            .append($toggle)
            .append($body);

        return { $section: $section, $body: $body };
    }

    function initTableSection($body, riskId, canEdit) {
        var $inner = $body.find('.sr-mitigation-controls-body');
        var loaded = false;
        var attachedById = {};
        // The picker's live selection, if it has changed since the last
        // server fetch -- null until syncSelection() below is first
        // called. Lets a control added/removed BEFORE the accordion was
        // ever expanded still show correctly on first expand
        // (fetchAndRender() renders THIS list, not the raw server
        // response, whenever it's set), and lets an already-expanded
        // table update immediately without a re-fetch.
        var pendingIds = null;

        // Bound ONCE here, delegated from $inner (the accordion body's
        // stable wrapper), not per-render on the ephemeral <table> inside
        // it -- renderTable() replaces $inner's entire innerHTML on every
        // call (initial load, add/remove sync), so a table-scoped .on()
        // would re-bind (and, for SRRowActionsMenu's own document-level
        // listeners, ACCUMULATE) a fresh handler on every one of those
        // renders. Delegation from the surviving ancestor sidesteps that
        // entirely: it keeps matching new rows for the life of this
        // accordion instance, whatever renderTable() replaces underneath.
        //
        // A namespace unique to THIS accordion instance ($body's own id,
        // buildTableSection()'s counter) rather than the module's bind()
        // default -- unlike Compliance/Governance's own callers (one
        // instance for the page's whole life), this page can create a new
        // table instance more than once per page load (view mode's table,
        // then an edit-mode table when the mitigation form opens, then a
        // fresh view-mode table again after Save) -- a shared literal
        // namespace would accumulate a duplicate document click/keydown
        // listener from SRRowActionsMenu.bind() every time.
        var actionsNamespace = 'srmitigationcontrolsactions-' + ($body.attr('id') || (++tableSectionCounter));
        if (window.SRRowActionsMenu) {
            SRRowActionsMenu.bind({
                container: $inner,
                scope: $inner,
                namespace: actionsNamespace
            });
        }

        // Bound regardless of canEdit -- rowHtml() renders the action
        // icon for every real (non-pending) row once the table is
        // reachable at all (canSelectMitigationControls, governance),
        // just as a pencil (edit) or an eye (view-only). data-mode
        // decides which fetch/save behavior this click gets; the GET
        // itself is identical either way.
        $inner.on('click', '.sr-mitigation-controls-action', function (event) {
            event.preventDefault();
            var $btn = $(this);
            var controlId = $btn.closest('tr').attr('data-control-id');
            var isEdit = $btn.attr('data-mode') === 'edit';
            openValidationModal(riskId, controlId, isEdit ? function () {
                // Re-fetch from the server (not just re-render): a
                // validation save can change validation_owner_name/
                // percent/status for this row, so the cache itself is
                // stale, not just the DOM.
                pendingIds = null;
                fetchAndRender();
            } : null, !isEdit);
        });

        function renderTable(ids) {
            if (!ids.length) {
                $inner.html(
                    '<div class="sr-table-empty">' +
                        '<div class="sr-table-empty-icon"><i class="fa fa-list-check" aria-hidden="true"></i></div>' +
                        '<div class="sr-table-empty-title">' + esc(L('NoControlsSelectedYet')) + '</div>' +
                    '</div>'
                );
                return;
            }

            var rows = ids.map(function (id) {
                return attachedById[String(id)] || pendingRow(id);
            });

            var tableId = $inner.attr('id') || (($inner.attr('id', 'sr-mcb-' + (++tableSectionCounter))).attr('id'));
            $inner.html(
                '<div class="sr-table-scroll">' +
                    '<table class="sr-table" id="' + tableId + '-table" width="100%">' +
                        '<thead><tr>' +
                            '<th>' + esc(L('Controls')) + '</th>' +
                            '<th>' + esc(L('ValidationOwner')) + '</th>' +
                            '<th>' + esc(L('MitigationPercent')) + '</th>' +
                            '<th>' + esc(L('ValidationStatus')) + '</th>' +
                            '<th class="sr-actions-col sr-actions-col-sticky"></th>' +
                        '</tr></thead>' +
                        '<tbody>' + rows.map(function (row) { return rowHtml(row, canEdit); }).join('') + '</tbody>' +
                    '</table>' +
                '</div>'
            );

            if ($.fn.DataTable) {
                // serverSide:false/processing:false override header.php's
                // sitewide DataTable.defaults (serverSide:true,
                // processing:true) -- this table is static/client-rendered
                // from rows already resolved above, not an ajax data
                // source. Without serverSide:false, DataTables expects to
                // fetch rows itself and never renders the ones already in
                // the DOM, showing an eternal "Loading…" placeholder
                // instead (confirmed live). `dom: 't'` (table only) drops
                // the sitewide layout's pageLength/paging chrome this
                // minimal, unpaginated table has no use for --
                // paging:false/info:false alone left that chrome's markup
                // rendering empty/misaligned.
                var dt = $('#' + tableId + '-table').DataTable({
                    serverSide: false,
                    processing: false,
                    // No scrollX: design-system.md §6b treats horizontal
                    // scroll as the overflow VALVE, not the narrow-width
                    // plan -- the folded Control column (rowHtml()'s own
                    // comment) is what actually keeps this table inside its
                    // card at normal widths, by wrapping instead of forcing
                    // every column to stay single-line.
                    searching: false,
                    paging: false,
                    info: false,
                    ordering: true,
                    // Row actions column never sorts (design-system.md §6:
                    // "the select-checkbox and row-actions columns don't
                    // sort").
                    columnDefs: [{ targets: -1, orderable: false }],
                    dom: 't'
                });

                // design-system.md §6's sort-icon rule: DataTables' bundled
                // caret pair is suppressed (sr-sort-native-off,
                // _tables.scss) in favor of a real <i class="fa
                // sr-sort-icon">, neutral fa-sort until a column is the
                // active sort, then red fa-arrow-*-short-*. Same mechanism
                // sr-audit-trail.js's syncSortIcons() uses; reproduced here
                // rather than called directly (page-scoped to that file,
                // not a shared export -- CLAUDE.md's function-reachability
                // rule applies to JS too). The first draw can fire
                // synchronously during construction (before .on('draw', ...)
                // below is even bound), so this runs once explicitly right
                // after init too.
                function syncSortIcons() {
                    $('#' + tableId + '-table thead th[data-dt-column]').each(function () {
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
                syncSortIcons();
                dt.on('draw', syncSortIcons);
            }
        }

        function fetchAndRender() {
            $inner.html('<div class="sr-table-empty"><div class="sr-table-empty-icon"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i></div></div>');
            $.ajax({
                url: BASE_URL + '/api/v2/risks/' + riskId + '/mitigations/controls',
                type: 'GET'
            }).done(function (res) {
                var rows = (res && res.data) ? res.data : [];
                attachedById = {};
                rows.forEach(function (row) {
                    attachedById[String(row.control_id)] = row;
                });
                // pendingIds (a picker change made while this table was
                // collapsed, or before its first-ever load) takes
                // precedence over the server's own list on this first
                // render -- see its own declaration above.
                renderTable(pendingIds || rows.map(function (row) { return row.control_id; }));
            }).fail(function () {
                $inner.html(
                    '<div class="sr-table-empty sr-table-empty-danger">' +
                        '<div class="sr-table-empty-icon"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i></div>' +
                        '<div class="sr-table-empty-title">' + esc(L('RequestFailed')) + '</div>' +
                    '</div>'
                );
            });
        }

        $body.on('show.bs.collapse', function () {
            if (!loaded) {
                loaded = true;
                fetchAndRender();
            }
        });

        // Called by the picker/chips field (same .sr-mitigation-controls-
        // field wrapper, see syncTableFromSelect() below) whenever the
        // <select>'s value changes -- adds/removes a row immediately
        // rather than waiting for the mitigation form's Save button, per
        // the user's own ask. Remembered via pendingIds even before the
        // table has ever been fetched, and applied live if it's already
        // open.
        $body.data('syncSelection', function (ids) {
            pendingIds = ids;
            if (loaded) {
                renderTable(ids);
            }
        });
    }

    // ------------------------------------------------------------------
    // Control Validation modal -- built once, lazily. Saves independently
    // of the rest of the mitigation via a dedicated multipart POST (real
    // file upload, unlike the big Edit Mitigation form's urlencoded PATCH)
    // -- see saveMitigationControlValidation()'s docblock, includes/api.php,
    // for the data-loss bug this replaces.
    // ------------------------------------------------------------------

    var validationModalId = 'mitigation-control-validation';
    var validationModalBuilt = false;
    var keptFileIds = [];
    // HugeRTE builds asynchronously (init_compact_editor(), js/WYSIWYG/
    // editor.js) but this modal is built and initialized ONCE, then
    // reopened with fresh content on every control clicked -- so unlike a
    // field built fresh per-open (risk-details-form.js's own richtext
    // fields), a plain .val() on first open can race HugeRTE's own async
    // boot and silently not reach the rendered editor. detailsEditorReady
    // flips true from init_compact_editor()'s onReady callback below;
    // pendingDetailsContent holds a value that arrived before that point,
    // applied the moment the editor becomes available.
    var detailsEditorReady = false;
    var pendingDetailsContent = null;
    var pendingDetailsReadOnly = false;

    function setDetailsContent(text) {
        if (detailsEditorReady && typeof hugerte !== 'undefined') {
            var ed = hugerte.get(validationModalId + '-details');
            if (ed) {
                ed.setContent(text || '');
                return;
            }
        }
        pendingDetailsContent = text || '';
        $('#' + validationModalId + '-details').val(text || '');
    }

    // Paired with setDetailsContent() above -- reads back from whichever
    // side actually holds the current value (the live editor once ready,
    // the underlying textarea before that).
    function getDetailsContent() {
        if (detailsEditorReady && typeof hugerte !== 'undefined') {
            var ed = hugerte.get(validationModalId + '-details');
            if (ed) {
                return ed.getContent();
            }
        }
        return $('#' + validationModalId + '-details').val();
    }

    // HugeRTE ignores the underlying textarea's disabled prop entirely --
    // it operates its own iframe, not the hidden original element -- so
    // read-only has to go through the editor's own mode API instead.
    // mode.set() is a no-op (and .mode is undefined) before the editor has
    // actually initialized, hence the same detailsEditorReady guard
    // setDetailsContent()/getDetailsContent() use; this modal's very first
    // open always arrives with readOnly known before the editor could
    // possibly be ready, so the fallback path is a real, expected case here,
    // not just defensive.
    function setDetailsReadOnly(readOnly) {
        pendingDetailsReadOnly = !!readOnly;
        if (detailsEditorReady && typeof hugerte !== 'undefined') {
            var ed = hugerte.get(validationModalId + '-details');
            if (ed && ed.mode) {
                ed.mode.set(readOnly ? 'readonly' : 'design');
            }
        }
    }

    function buildValidationModal() {
        if (validationModalBuilt) {
            return;
        }
        validationModalBuilt = true;

        var $modal = $('<div>', { id: validationModalId, 'class': 'modal fade sr-modal', tabindex: '-1', 'aria-hidden': 'true', 'aria-labelledby': validationModalId + '-title' });

        var $header = $('<div>', { 'class': 'modal-header' })
            .append($('<span>', { 'class': 'sr-modal-icon' }).append($('<i>', { 'class': 'fa fa-shield-halved', 'aria-hidden': 'true' })))
            .append($('<h4>', { 'class': 'modal-title', id: validationModalId + '-title', text: L('ControlValidation') }))
            .append($('<button>', { type: 'button', 'class': 'btn-close', 'data-bs-dismiss': 'modal', 'aria-label': L('Close') }));

        var $infoCard = $('<section>', { 'class': 'sr-qcard' })
            .append($('<div>', { 'class': 'sr-qcard-body' }).append($('<div>', { 'class': 'sr-qgrid', id: validationModalId + '-info' })));

        var $detailsField = $('<div>', { 'class': 'sr-qfield sr-qfield--full' })
            .append($('<label>', { 'class': 'sr-qlabel', text: L('Details') }))
            .append($('<textarea>', { 'class': 'form-control', id: validationModalId + '-details', rows: 4 }));

        var $ownerField = $('<div>', { 'class': 'sr-qfield' })
            .append($('<label>', { 'class': 'sr-qlabel', text: L('Owner') }))
            .append($('<select>', { 'class': 'form-select', id: validationModalId + '-owner' }));

        var $percentField = $('<div>', { 'class': 'sr-qfield' })
            .append($('<label>', { 'class': 'sr-qlabel', text: L('MitigationPercent') }))
            .append($('<input>', { type: 'number', min: 0, max: 100, 'class': 'form-control', id: validationModalId + '-percent' }));

        var $fileField = $('<div>', { 'class': 'sr-qfield sr-qfield--full' })
            .append($('<label>', { 'class': 'sr-qlabel', text: L('UploadArtifact') }))
            .append($('<ul>', { 'class': 'list-unstyled', id: validationModalId + '-files' }))
            .append($('<input>', { type: 'file', 'class': 'form-control', id: validationModalId + '-file' }));

        var $editCard = $('<section>', { 'class': 'sr-qcard' })
            .append(
                $('<div>', { 'class': 'sr-qcard-body' }).append(
                    $('<div>', { 'class': 'sr-qgrid' }).append($detailsField).append($ownerField).append($percentField).append($fileField)
                )
            );

        var $footer = $('<div>', { 'class': 'modal-footer' })
            .append($('<button>', { type: 'button', 'class': 'btn btn-dark', 'data-bs-dismiss': 'modal', id: validationModalId + '-dismiss', text: L('Cancel') }))
            .append($('<button>', { type: 'button', 'class': 'btn btn-submit', id: validationModalId + '-save', text: L('Save') }));

        $modal.append(
            $('<div>', { 'class': 'modal-dialog modal-dialog-centered modal-dialog-scrollable' }).append(
                $('<div>', { 'class': 'modal-content' }).append($header).append($('<div>', { 'class': 'modal-body' }).append($infoCard).append($editCard)).append($footer)
            )
        );

        $('body').append($modal);

        // Real WYSIWYG (HugeRTE), same 'compact' toolbar/height variant
        // test-procedure editors already use -- matches the same "should
        // this be rich text" reasoning CurrentSolution/SecurityRequirements/
        // SecurityRecommendations just got (risk-details-form.js): validation
        // notes are free-form write-ups, not a single line, and nothing else
        // in the app renders this field as plain text that would need a
        // parallel escaping update (getMitigationControlValidation()'s own
        // 'validation_details' response feeds ONLY this textarea, never a
        // separate display sink -- rowHtml()'s table listing uses the
        // has_validation_details boolean flag, never the text itself).
        init_compact_editor('#' + validationModalId + '-details', 150, function () {
            detailsEditorReady = true;
            var ed = hugerte.get(validationModalId + '-details');
            if (pendingDetailsContent !== null) {
                ed.setContent(pendingDetailsContent);
                pendingDetailsContent = null;
            }
            if (ed.mode) {
                ed.mode.set(pendingDetailsReadOnly ? 'readonly' : 'design');
            }
        });
        // The remove-button ('.sr-chip-remove') delegated handler is bound
        // per-OPEN instead of here (openValidationModal(), below) -- unlike
        // when it was a Save-time-only kept-list, it now needs riskId/
        // controlId to commit the removal immediately, and those are only
        // known once the modal is opened for a specific control.
    }

    function renderValidationInfo(data) {
        var $info = $('#' + validationModalId + '-info');
        $info.empty();

        function field(labelKey, value) {
            if (!value) {
                return;
            }
            $('<div>', { 'class': 'sr-qfield' })
                .append($('<label>', { 'class': 'sr-qlabel', text: L(labelKey) }))
                .append($('<div>', { text: value }))
                .appendTo($info);
        }

        field('ControlNumber', data.control_number);
        field('ControlShortName', data.short_name);
        field('ControlFamily', data.family_short_name);
        field('ControlType', data.control_type);

        if (data.control_status !== null && data.control_status !== undefined) {
            var statusText = data.control_status === 1 ? L('Pass') : data.control_status === 0 ? L('Fail') : L('NotTested');
            field('ControlStatus', statusText);
        }
    }

    function renderValidationFiles(files, readOnly) {
        keptFileIds = (files || []).map(function (f) { return String(f.id); });
        var $list = $('#' + validationModalId + '-files').empty();
        if (!files || !files.length) {
            // Same empty-state indicator buildSupportingDocumentationWidget()
            // uses (risk-details-form.js) and the legacy supporting_
            // documentation() read passthrough (includes/functions.php) --
            // an empty <ul> alone reads as "still loading" or "silently
            // broken", not "no files".
            $list.append($('<li>', { 'class': 'sr-supporting-docs-empty', text: L('None') }));
            return;
        }
        files.forEach(function (file) {
            // A plain <span> here never let a user reach the file at all, in
            // EITHER mode -- unlike buildSupportingDocumentationWidget()'s own
            // list (risk-details-form.js), which was built with a real link
            // from the start. download.php's own file_type=validation_file
            // branch (management/download.php -> download_file(),
            // includes/functions.php) is what makes 'id' here a
            // validation_files.id, not a unique_name string like the OTHER
            // widget's files -- confirmed against the legacy datatable's own
            // identical href (includes/api.php, getMitigationControlsDatatable()).
            // link-success + a download icon -- same treatment
            // buildSupportingDocumentationWidget() (risk-details-form.js)
            // uses, matching the legacy supporting_documentation() read-mode
            // link's own class (includes/functions.php) so all three file
            // widgets read as "click to download" the same way, not plain
            // text (confirmed by the user). document.createTextNode(), not
            // jQuery's own text-string .append(): a plain string there gets
            // parsed as HTML if it looks like markup, and file.name is an
            // uploaded filename, not a trusted literal.
            var $item = $('<li>').append($('<a>', {
                'class': 'link-success',
                href: BASE_URL + '/management/download.php?id=' + encodeURIComponent(file.id) + '&file_type=validation_file',
                target: '_blank'
            })
                .append($('<i>', { 'class': 'fa fa-download', 'aria-hidden': 'true' }))
                .append(document.createTextNode(' ' + file.name)));
            if (!readOnly) {
                $('<button>', { type: 'button', 'class': 'sr-chip-remove', 'data-file-id': file.id, 'aria-label': L('Remove'), html: '&times;' }).appendTo($item);
            }
            $list.append($item);
        });
    }

    // Builds the SAME FormData shape both the Save button and the
    // immediate file-add/remove handlers below send -- validation_details/
    // owner/percent are always included (whatever the fields currently
    // hold, saved or not) because the endpoint saves all three together
    // with the file list in one call; there is no server-side "files only"
    // action. Practically this means choosing or removing a file also
    // commits whatever text is currently in Details/Owner/Percent, even if
    // the user hasn't clicked Save yet -- the SAME trade-off already
    // accepted for SupportingDocumentation/MitigationSupportingDocumentation
    // (risk-details-form.js): immediate file actions can't be cleanly
    // separated from "everything else in this record" when they still ride
    // the one combined endpoint.
    function commitValidation(riskId, controlId, extraFile, onDone) {
        var formData = new FormData();
        formData.append('validation_details', getDetailsContent());
        formData.append('validation_owner', $('#' + validationModalId + '-owner').val());
        formData.append('validation_mitigation_percent', $('#' + validationModalId + '-percent').val());
        keptFileIds.forEach(function (id) {
            formData.append('file_ids[]', id);
        });
        if (extraFile) {
            formData.append('artifact_file', extraFile);
        }

        return $.ajax({
            url: BASE_URL + '/api/v2/risks/' + riskId + '/mitigations/controls/' + controlId + '/validation',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function (res) {
            // A control's validation percent overrides its own defined percent in
            // get_residual_risk(), so every validation save (Save button and the
            // immediate file add/remove alike) can move the residual score --
            // tell the Details coordinator, which owns the header tiles.
            $(document).trigger('risk:scoring-changed', [riskId]);
            if (onDone) {
                onDone(res);
            }
        }).fail(function (xhr) {
            if (xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message, false);
            } else {
                showAlertFromMessage(L('RequestFailed'), false);
            }
        });
    }

    // readOnly: for a viewer with canSelectMitigationControls (governance)
    // but not canEditMitigation (plan_mitigations) -- they can still see
    // this control's validation (the GET below is already gated the same
    // way, includes/api.php), just not change it. Same modal, same fetch;
    // only the field states, file-upload affordance and footer differ.
    function openValidationModal(riskId, controlId, onSaved, readOnly) {
        buildValidationModal();
        $('#' + validationModalId + '-file').val('');

        $.ajax({
            url: BASE_URL + '/api/v2/risks/' + riskId + '/mitigations/controls/' + controlId + '/validation',
            type: 'GET'
        }).done(function (res) {
            var data = (res && res.data) ? res.data : {};
            renderValidationInfo(data);

            var $owner = $('#' + validationModalId + '-owner').empty();
            $('<option>').val('').text('--').appendTo($owner);
            (data.owner_options || []).forEach(function (opt) {
                $('<option>').val(opt.value).text(opt.name).appendTo($owner);
            });
            $owner.val(data.validation_owner || '');

            setDetailsContent(data.validation_details || '');
            setDetailsReadOnly(!!readOnly);
            $('#' + validationModalId + '-percent').val(data.validation_mitigation_percent || 0).prop('disabled', !!readOnly);
            $owner.prop('disabled', !!readOnly);
            renderValidationFiles(data.files, !!readOnly);

            // Only the upload INPUT hides for a read-only open, not its
            // whole field wrapper -- that wrapper also holds the existing-
            // files <ul> (renderValidationFiles(), just above), which must
            // stay visible so a view-only viewer can still see what's
            // attached. Save hidden too: nothing here can be changed, so
            // there's nothing to stage a file for or submit. Cancel becomes
            // a plain Close (same dismiss button, relabeled) since there is
            // nothing to cancel.
            $('#' + validationModalId + '-file').toggle(!readOnly);
            $('#' + validationModalId + '-save').toggle(!readOnly);
            $('#' + validationModalId + '-dismiss').text(readOnly ? L('Close') : L('Cancel'));

            var $modal = $('#' + validationModalId);
            $modal.modal('show');

            if (readOnly) {
                return;
            }

            // Uploading now commits IMMEDIATELY, like Supporting Documentation/
            // Mitigation Supporting Documentation already do (buildSupporting
            // DocumentationWidget(), risk-details-form.js) -- previously this
            // only staged the file until the modal's own Save button was
            // clicked, which made adding several files one at a time
            // impossible without closing and reopening the modal in between
            // (confirmed by the user). The input disables for the round trip
            // so a second pick can't race the first, and clears its value on
            // success so choosing the SAME filename again still fires 'change'.
            $('#' + validationModalId + '-file').off('change').on('change', function () {
                var file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    return;
                }
                var $input = $(this).prop('disabled', true);
                commitValidation(riskId, controlId, file, function (res) {
                    renderValidationFiles((res && res.data) ? res.data.files : [], false);
                }).always(function () {
                    $input.prop('disabled', false).val('');
                });
            });

            // Removing also commits immediately -- same reasoning as the
            // file-add handler above, and the SAME "kept list" shape
            // save_mitigation_control_validation() already expects
            // (file_ids[]), just sent one row shorter instead of waiting for
            // a later Save. Delegated per-open (not in buildValidationModal(),
            // which runs once) because it needs THIS open's riskId/controlId.
            $('#' + validationModalId + '-files').off('click', '.sr-chip-remove')
                .on('click', '.sr-chip-remove', function () {
                    var id = String($(this).attr('data-file-id'));
                    keptFileIds = keptFileIds.filter(function (kept) { return kept !== id; });
                    $(this).closest('li').remove();
                    commitValidation(riskId, controlId, null, function (res) {
                        renderValidationFiles((res && res.data) ? res.data.files : [], false);
                    });
                });

            // Save now only has Details/Owner/Percent left to commit -- file
            // changes above already reached the server on their own. Still
            // resends the current (already-kept) file_ids[] via
            // commitValidation(), same as every other call; harmless, since
            // nothing in that list changed since the last file action.
            $('#' + validationModalId + '-save').off('click').on('click', function () {
                var $btn = $(this).prop('disabled', true);
                commitValidation(riskId, controlId, null, function () {
                    $modal.modal('hide');
                    if (onSaved) {
                        onSaved();
                    }
                }).always(function () {
                    $btn.prop('disabled', false);
                });
            });
        });
    }

    window.RiskMitigationControls = {
        buildField: buildField,
        initField: initField,
        applyPrefill: applyPrefill,
        loadRoster: loadRoster,
        findControl: findControl,
        buildTableSection: buildTableSection,
        initTableSection: initTableSection
    };
})();
