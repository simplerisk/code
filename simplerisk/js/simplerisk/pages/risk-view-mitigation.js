/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// ----------------------------------------------------------------------
// Coordinates the Mitigation tab's read/edit toggle on management/view.php
// (Phase 4b-iii). Near-identical structure to risk-view-details.js (the
// Details-tab equivalent, Phase 4a) -- see that module's own docblock for
// the reasoning behind the generation guard and the read-mode cache, both
// reproduced here unchanged.
//
// Scoped to #risk-view-mitigation-mount ONLY -- Details and Review keep
// using their own wiring untouched. Deliberately does NOT reuse risk.js's
// [name=edit_mitigation]/.edit-mitigation/.cancel-edit-mitigation/
// [name=update_mitigation] selectors: those drove the OLD AJAX-partial-
// refresh endpoints (/api/v2/management/risk/editdetails?action=editmitigation,
// .../saveMitigation) this task retires for the Mitigation tab specifically.
// Their trigger markup is gone from management/partials/details.php's
// #mitigation pane, so those risk.js handlers are simply unreachable from
// this tab now -- Details and Review still trigger their own handlers via
// their own untouched markup. The `?action=editmitigation` QUERY PARAM
// those old links still set is read again below (maybeAutoOpenFromDeepLink()),
// just routed through this module's own editMitigation(), not the retired
// risk.js AJAX handler.
//
// Edit mode uses the SAME non-embedded (`embedded` omitted/false) path
// risk-view-details.js uses, for the same reason: RiskDetailsForm's
// `embedded: true` affordance is wired to risk.js's own `.save-risk-form`
// (addRisk()) handler, which always POSTs to /api/v2/risks to CREATE a risk
// -- it has no notion of `submitMode`/`updateRiskId`/`updateMitigationId`.
// The PATCH-based update path is reachable only through the engine's own
// sticky action-bar button (buildActionsBar(), the non-embedded path), so
// this coordinator gives the form container the `.sr-qform` class plus a
// `data-submit-label`, matching management/index.php's
// #submit-risk-container shape, and supplies its own Cancel button
// alongside it.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    var VIEW_SELECTOR = '#risk-view-mitigation-mount';
    var VIEW_CONTAINER_SELECTOR = '#risk-mitigation-view-container';
    var FORM_CONTAINER_SELECTOR = '#risk-view-mitigation-form-container';
    var VALUES_PATH_SUFFIX = '/mitigation-values';
    var SUPPORTING_DOCUMENTATION_HTML_KEY = 'mitigation_supporting_documentation_html';

    // See risk-view-details.js's module docblock's "Generation guard" /
    // "Read-mode cache" paragraphs -- identical reasoning, reproduced here
    // for this tab's own module-level state.
    var currentGeneration = 0;
    var cachedViewData = null; // { riskId, data } from RiskDetailsView's onLoaded

    function valuesPath(riskId) {
        return '/ui/risk/' + riskId + VALUES_PATH_SUFFIX;
    }

    // Accept/Reject Mitigation -- a top-level tab action (design-system.md
    // review: it's the mitigation's APPROVAL STATUS, not a planning field),
    // rendered next to whichever trigger button ($otherActionBtn -- Edit
    // Mitigation in read mode, Cancel in edit mode) owns the tab's one
    // top-right corner today. Built via window.RiskDetailsView's shared
    // buildAcceptMitigationWidget() (risk-details-view.js) rather than
    // duplicated here -- see that function's own docblock for the full
    // reasoning and for why it returns {$text, $actions} separately instead
    // of one combined element.
    //
    // Idempotent: removes whatever THIS function rendered last time before
    // rendering again, the same "clear the old one, add the new one" shape
    // risk-view-review.js's renderPerformReviewButton() uses -- both
    // renderReadMode() and renderEditMode() rebuild $mount from scratch on
    // every call anyway (see their own $mount.empty() above), but keeping
    // this function self-contained (rather than relying on that) means it
    // stays correct even if a future caller stops clearing the mount first.
    function renderAcceptMitigationWidget($mount, riskId, data, $otherActionBtn) {
        $mount.find('.sr-risk-tab-actions, .accept-mitigation-text').remove();

        var widget = RiskDetailsView.buildAcceptMitigationWidget(riskId, data);

        // Only actually wraps something when can_accept is true (buildAccept
        // MitigationWidget() returns an EMPTY $actions span otherwise) --
        // an empty flex wrapper is harmless, but skip it so a no-permission
        // viewer's DOM has no trace of a row that would only ever be empty.
        if (widget.$actions.children().length) {
            var $actionRow = $('<div>').addClass('sr-risk-tab-actions');
            widget.$actions.find('button').addClass('sr-risk-tab-action');
            $actionRow.append(widget.$actions.children());
            if ($otherActionBtn) {
                $otherActionBtn.removeClass('position-absolute end-0');
                $actionRow.append($otherActionBtn);
            }
            $mount.append($actionRow);
        } else if ($otherActionBtn) {
            $mount.append($otherActionBtn);
        }

        // Prepended, not appended -- reads as a status line ABOVE the Cards
        // stack (view mode) / form (edit mode), not tacked on after it.
        $mount.prepend(widget.$text);
    }

    // Read mode's Mitigation Controls table -- appended into the SAME
    // 'controls' card RiskDetailsView.renderCards() already built (its own
    // chips-only field summary sits above it, via risk-details-view.js's
    // generic entry.names chip path), rather than threaded through
    // renderCards()/renderFieldItem() as a special case: this table needs
    // its own live fetch + row-click wiring, so it is appended from
    // OUTSIDE that function instead of from inside it.
    // idPrefix + '-' + card_key + '-collapse' is renderCards()'s own body-id
    // convention (that function's own idPrefix comment); riskId is used
    // for its own lazy fetch (initTableSection()), same reasoning
    // instance.updateRiskId gets threaded to the edit-mode counterpart in
    // risk-details-form.js.
    //
    // Gated on canSelectMitigationControls (governance) -- the values
    // response's own can_select_mitigation_controls flag, mirroring edit
    // mode's instance.canSelectMitigationControls gate exactly (see that
    // file's own docblock). Reads MitigationControls' own card_key from the
    // rendered `fields` roster rather than assuming 'controls' -- the admin
    // Cards layout editor can drag any field onto a different card, so the
    // field's LIVE card_key (not get_risk_mitigation_core_field_card_map()'s
    // default) is what actually decided which card renderCards() put it on.
    // A no-op if that field isn't found, or its card isn't in the rendered
    // layout at all, since $cardBody would simply not exist.
    function renderMitigationControlsTable(riskId, canSelectMitigationControls, fields, canEditMitigation) {
        if (!canSelectMitigationControls) {
            return;
        }
        var mitigationControlsField = (fields || []).filter(function (f) { return f.name === 'MitigationControls'; })[0];
        if (!mitigationControlsField || !mitigationControlsField.card_key) {
            return;
        }
        var $cardBody = $('#' + VIEW_CONTAINER_SELECTOR.slice(1) + '-' + mitigationControlsField.card_key + '-collapse');
        if (!$cardBody.length) {
            return;
        }
        var canEdit = !!canEditMitigation;
        var table = RiskMitigationControls.buildTableSection();
        $cardBody.append(table.$section);
        RiskMitigationControls.initTableSection(table.$body, riskId, canEdit);
    }

    // `onLoaded`, when given, fires once this read-mode render has real data
    // behind it -- either the cache-hit branch or the values fetch actually
    // resolving. Only render()'s initial page-load call passes one (the
    // action=editmitigation deep-link check, mirroring risk-view-review.js's
    // identical Finding-5 fix, needs read mode's own fetch to have completed
    // before it can decide whether to auto-open edit mode). Every other
    // caller (Save's return to read mode, Cancel) omits it and behaves
    // exactly as before.
    function renderReadMode($mount, riskId, useCache, onLoaded) {
        currentGeneration += 1;

        // Idempotent no-op the first time this runs -- see RiskDetailsForm.
        // destroy()'s own docs. Called here so a Save/Cancel back to read
        // mode always tears down the edit engine's GridStack instances and
        // resize binding, rather than just discarding their DOM.
        RiskDetailsForm.destroy(FORM_CONTAINER_SELECTOR);
        $mount.empty();

        var $editBtn = null;
        if ($mount.data('can-edit') == 1) {
            // .btn-submit, not .btn-dark -- see risk-view-details.js's own
            // $editBtn for the full reasoning (design-system.md's documented
            // .btn-dark/.btn-submit meanings).
            $editBtn = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-submit risk-mitigation-edit-toggle position-absolute sr-risk-tab-action end-0' })
                .text(_lang['EditMitigation']);
        }

        var $viewContainer = $('<div>', { id: 'risk-mitigation-view-container' });
        $mount.append($viewContainer);
        if ($editBtn) {
            $mount.append($editBtn);
        }

        if (useCache && cachedViewData && cachedViewData.riskId === riskId) {
            // Cancel: nothing changed server-side, so render straight from
            // the last successful load instead of re-fetching.
            RiskDetailsView.renderFromData(VIEW_CONTAINER_SELECTOR, cachedViewData.data, cachedViewData.riskId);
            renderAcceptMitigationWidget($mount, riskId, cachedViewData.data.accept_mitigation, $editBtn);
            renderMitigationControlsTable(riskId, cachedViewData.data.can_select_mitigation_controls, cachedViewData.data.fields, cachedViewData.data.can_edit_mitigation);
            if (typeof onLoaded === 'function') {
                onLoaded();
            }
            return;
        }

        // Initial load (no cache yet) or a post-Save return to read mode
        // (values genuinely changed) -- do a real fetch, and cache the
        // result for a future Cancel.
        RiskDetailsView.init(VIEW_CONTAINER_SELECTOR, riskId, function (data) {
            cachedViewData = { riskId: riskId, data: data };
            renderAcceptMitigationWidget($mount, riskId, data.accept_mitigation, $editBtn);
            renderMitigationControlsTable(riskId, data.can_select_mitigation_controls, data.fields, data.can_edit_mitigation);
            if (typeof onLoaded === 'function') {
                onLoaded();
            }
        }, {
            tabIndex: 2,
            valuesPath: valuesPath(riskId),
            supportingDocumentationHtmlKey: SUPPORTING_DOCUMENTATION_HTML_KEY
        });
    }

    function renderEditMode($mount, riskId) {
        currentGeneration += 1;
        var generation = currentGeneration;

        // Idempotent no-op if nothing was ever built into the view
        // container -- see RiskDetailsView.destroy()'s own docs.
        RiskDetailsView.destroy(VIEW_CONTAINER_SELECTOR);
        $mount.empty();

        var $formContainer = $('<div>', { id: 'risk-view-mitigation-form-container', 'class': 'sr-qform' })
            .attr('data-submit-label', _lang['Save']);
        // .sr-risk-tab-cancel, not .btn-secondary -- see _tabs.scss's own
        // rule for the full reasoning (matches the ghost/ secondary
        // language .sr-qcancel uses elsewhere, instead of Bootstrap's solid
        // grey fill).
        var $cancelBtn = $('<button>', { type: 'button', 'class': 'btn btn-sm sr-risk-tab-cancel risk-mitigation-cancel-edit position-absolute sr-risk-tab-action end-0' })
            .text(_lang['Cancel']);
        $mount.append($formContainer).append($cancelBtn);

        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2' + valuesPath(riskId),
            dataType: 'json'
        }).done(function (response) {
            // A later renderReadMode()/renderEditMode() call (e.g. a rapid
            // Edit -> Cancel -> Edit) has superseded this fetch -- do not
            // clobber whatever the user is now looking at/typing into.
            if (generation !== currentGeneration) {
                return;
            }

            var prefillValues = {};
            $.each(response.data.values, function (formName, entry) {
                prefillValues[formName] = entry.raw;
            });

            RiskDetailsForm.init(FORM_CONTAINER_SELECTOR, {
                tabIndex: 2,
                prefillValues: prefillValues,
                submitMode: 'update',
                updateRiskId: riskId,
                // Mitigation fields live on a DIFFERENT resource than the
                // risk itself -- PATCH /api/v2/risks/{id}/mitigations
                // (saveMitigation(), includes/api.php), not PATCH
                // /api/v2/risks/{id} (updateRisk()). Without this override
                // risk-details-form.js's submitRiskUpdate() defaults to the
                // risk URL, which silently ignores every mitigation-only
                // field name.
                updateUrl: '/api/v2/risks/' + riskId + '/mitigations',
                // Governance permission gate for the control-selection field
                // -- see risk-details-form.js's own instance.
                // canSelectMitigationControls docblock.
                canSelectMitigationControls: !!response.data.can_select_mitigation_controls,
                // Gates the Control Validation table's row edit-action --
                // see that key's own docblock, api/v2/includes/api.php.
                canEditMitigation: !!response.data.can_edit_mitigation,
                // This risk's OWN template group, as the values response
                // reports it -- not the viewer's default. Pinning it
                // suppresses the engine's template-group tab bar, which
                // belongs to the create flow -- see risk-view-details.js's
                // identical option for the full reasoning.
                pinnedTemplateGroupId: response.data.template_group_id,
                onSaveSuccess: function () {
                    renderReadMode($mount, riskId);
                    // The mitigation percent, or a control attached in this same save,
                    // moves the residual score -- tell the Details coordinator, which
                    // owns the header tiles (see refreshRiskSummary(), risk-view-details.js).
                    $(document).trigger('risk:scoring-changed', [riskId]);
                },
                // Sticky action bar's own Cancel button (design-system.md
                // #5's ".sr-qcancel", buildActionsBar() in risk-details-
                // form.js) -- mirrors risk-view-details.js's identical
                // option, added there first: a long form's only dismiss
                // affordance being the top-of-tab Cancel (.risk-mitigation-
                // cancel-edit above) left no in-view way to back out
                // without scrolling back up. Same re-render-from-cache path
                // (useCache=true) that button's own click handler already
                // uses, so both Cancel affordances behave identically.
                onCancel: function () {
                    renderReadMode($mount, riskId, true);
                }
            });

            renderAcceptMitigationWidget($mount, riskId, response.data.accept_mitigation, $cancelBtn);
        }).fail(function () {
            if (generation !== currentGeneration) {
                return;
            }
            showAlertFromMessage(_lang['RequestFailed'], false);
        });
    }

    function editMitigation() {
        var $mount = $(VIEW_SELECTOR);
        if (!$mount.length) {
            return;
        }
        renderEditMode($mount, $mount.data('risk-id'));
    }

    $('body').on('click', VIEW_SELECTOR + ' .risk-mitigation-edit-toggle', function (e) {
        e.preventDefault();
        editMitigation();
    });

    // The Actions-menu entry point (view_top_table(), includes/display.php)
    // -- same dual-trigger shape as risk-view-review.js's
    // .risk-review-perform-from-actions: switches to the Mitigation tab
    // (matching callbackAfterRefreshTab()'s own bootstrap.Tab mechanism,
    // risk.js) THEN opens the same edit form -- one code path, two triggers.
    // Its <a> is `href='#'` (unlike Edit Risk's, which carries the deep-link
    // query param directly), so there is no separate same-page-vs-deep-link
    // split to handle here -- this IS the only trigger a same-page click
    // ever reaches. Retired risk.js's own '.edit-mitigation' handler used to
    // catch this click first (same broken .content-container-replacement
    // shape this module's own docblock already describes retiring for the
    // tab's internal markup) -- removing '.edit-mitigation' from that
    // handler's selector is what lets this one run.
    $('body').on('click', '.edit-mitigation', function (e) {
        e.preventDefault();
        var tabEl = document.querySelector('#tab_mitigation');
        if (tabEl) {
            new bootstrap.Tab(tabEl).show();
        }
        editMitigation();
    });

    $('body').on('click', VIEW_SELECTOR + ' .risk-mitigation-cancel-edit', function (e) {
        e.preventDefault();
        var $mount = $(VIEW_SELECTOR);
        renderReadMode($mount, $mount.data('risk-id'), true);
    });

    // `?action=editmitigation#mitigation` is a repo-wide deep-link contract
    // (review-risk.js's Mitigation row action, functions.php's Mitigation
    // status column links, and the notification Extra's e-mail link) that
    // expects the page to land directly in the edit form -- the same
    // program-wide gap Phase 4c-ii found and fixed for `action=editreview`
    // (risk-view-review.js), never fixed here when this tab's own mount-
    // point swap (Phase 4b-iii) retired the legacy AJAX handler that used
    // to read it. Checked only after read mode's OWN values fetch has
    // resolved (via renderReadMode()'s onLoaded) -- not on every read-mode
    // render, just the initial page load -- so cancelling back to read mode
    // never reopens the form on its own.
    function maybeAutoOpenFromDeepLink() {
        try {
            if (new URLSearchParams(window.location.search).get('action') === 'editmitigation') {
                editMitigation();
            }
        } catch (e) {
            // URLSearchParams is universally supported in this app's target
            // browsers; guarded only so a future odd query string can never
            // throw out of this read-mode callback.
        }
    }

    // Renders (or re-renders) read mode into whatever
    // #risk-view-mitigation-mount is currently in the DOM. Safe to call
    // repeatedly -- see risk-view-details.js's render()/window.RiskViewDetails
    // for the exact same reasoning: risk.js's Details/Review handlers
    // (Save/Cancel Details is retired, but Perform Review, Save/Cancel
    // Review, View All Reviews, Close Risk, Change Status all still re-
    // render the WHOLE management/partials/details.php partial server-side)
    // replace the #mitigation pane too, so the mount this module rendered
    // into on page load is gone and the freshly-inserted one is empty.
    function render() {
        var $mount = $(VIEW_SELECTOR);
        if ($mount.length) {
            renderReadMode($mount, $mount.data('risk-id'), false, maybeAutoOpenFromDeepLink);
        }
    }

    $(render);

    window.RiskViewMitigation = {
        render: render
    };
})();
