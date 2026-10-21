/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// ----------------------------------------------------------------------
// Coordinates the Details tab's read/edit toggle on management/view.php.
// Scoped to #risk-view-details-mount ONLY -- Mitigation and Review keep
// using risk.js's existing delegated handlers untouched. Deliberately does
// NOT reuse risk.js's [name=edit_details]/.edit-risk/.save-details/
// .cancel-edit-details selectors: those drove the OLD AJAX-partial-refresh
// endpoints (/api/v2/management/risk/editdetails, .../saveDetails) this
// task retires for the Details tab specifically. Their trigger markup
// (the `[name=edit_details]` button) is gone from
// management/partials/details.php's #details pane, so those risk.js
// handlers are simply unreachable from this tab now -- Mitigation and
// Review still trigger them via their own untouched markup below the
// #details pane.
//
// Edit mode deliberately does NOT use RiskDetailsForm's `embedded: true`
// option. That path (buildEmbeddedSubmitAffordance()'s hidden
// `.save-risk-form` button) is wired to risk.js's OWN delegated
// `.save-risk-form` handler (addRisk()), which always POSTs to
// /api/v2/risks to CREATE a new risk -- it has no notion of `submitMode`/
// `updateRiskId` at all. The PATCH-based update path
// (submitRisk()/submitRiskUpdate() in risk-details-form.js) is reachable
// ONLY through the engine's own sticky action-bar button
// (buildActionsBar(), the NON-embedded path), so this coordinator renders
// the form container without `embedded: true`, gives it the `.sr-qform`
// class plus a `data-submit-label` -- the exact same shape
// management/index.php's #submit-risk-container uses -- so the engine
// renders its own real, visible, correctly-wired Save button, and (via the
// `onCancel` option) its own Cancel button in the same sticky action bar
// (design-system.md #5's ".sr-qcancel") -- this coordinator used to render
// a separate floating Cancel button itself; consolidated into the engine's
// one spot for it instead.
//
// Generation guard: `currentGeneration` is bumped on every renderReadMode()/
// renderEditMode() call and captured locally by renderEditMode()'s prefill
// fetch. There is only ever one mount on this page (VIEW_SELECTOR is a
// fixed id), so a single module-level counter is enough -- no
// instancesByContainer map needed the way the reusable RiskDetailsView/
// RiskDetailsForm engines require one. Without this guard, a rapid
// Edit -> Cancel -> Edit sequence could let the FIRST click's now-stale
// /ui/risk/{id}/values response land after the SECOND click's edit form
// already has in-progress user input, silently overwriting it via a second
// RiskDetailsForm.init() call.
//
// Read-mode cache: `cachedViewData` holds the last successfully-loaded
// {riskId, data} pair from RiskDetailsView.init()'s onLoaded callback.
// Cancel (nothing changed) renders straight from that cache via
// RiskDetailsView.renderFromData() instead of re-fetching -- matching this
// task's brief ("Cancel ... re-renders read mode from the values already
// held in memory -- no re-fetch, since nothing changed"). Initial load and
// post-Save both force a real fetch (useCache=false/omitted): initial load
// has no cache yet, and post-Save the values genuinely changed server-side.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    var VIEW_SELECTOR = '#risk-view-details-mount';
    var VIEW_CONTAINER_SELECTOR = '#risk-details-view-container';
    var FORM_CONTAINER_SELECTOR = '#risk-view-details-form-container';

    // See the module docblock's "Generation guard" / "Read-mode cache"
    // paragraphs above.
    var currentGeneration = 0;
    var cachedViewData = null; // { riskId, data } from RiskDetailsView's onLoaded

    function renderReadMode($mount, riskId, useCache, onLoaded) {
        currentGeneration += 1;

        // Idempotent no-op the first time this runs (nothing was ever built
        // into the form container yet) -- see RiskDetailsForm.destroy()'s
        // own docs. Called here so a Save/Cancel back to read mode always
        // tears down the edit engine's GridStack instances, HugeRTE editors
        // and resize binding rather than just discarding their DOM.
        RiskDetailsForm.destroy(FORM_CONTAINER_SELECTOR);
        $mount.empty();

        var canEdit = $mount.data('can-edit') == 1;

        var $editBtn = null;
        if (canEdit) {
            // .btn-submit, not .btn-dark: design-system.md documents .btn-dark
            // as the GHOST/Cancel treatment everywhere it's scoped (§8 modal
            // Cancel, §12b auth actions) and .btn-submit as the single red
            // primary ($sr-important) -- unscoped here, .btn-dark would just
            // fall through to raw Bootstrap black (#252525), a 4th, undocumented
            // button style. Edit is this card's one primary action (§11), so
            // it gets the same .btn-submit red the Subject Save button beside
            // it already uses.
            $editBtn = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-submit risk-details-edit-toggle position-absolute sr-risk-tab-action end-0' })
                .text(_lang['EditDetails']);
        }

        // NOTE: this container deliberately does NOT carry `.sr-qform`.
        // That was tried (to fix DREAD's/OWASP's read-mode cards rendering
        // stacked instead of side-by-side) and reverted -- this container is
        // the root of the ENTIRE read-mode Details tab, not just the scoring
        // card, so it dragged every `.sr-qform`-scoped SCSS rule (card
        // shadows/padding/margins, 72px of dead trailing space) onto every
        // OTHER read-mode card too, including Classic/Custom's, which have
        // nothing to do with scoring layout. The actual fix now lives where
        // the layout problem actually is: buildDreadReadView() and
        // buildOwaspReadView() (risk-details-view.js) each apply `sr-qform`
        // to their OWN outer wrapper, with the grid-layout classes on a
        // nested child -- mirroring buildCvssReadView()'s own pre-existing,
        // never-broken shape. See the block comment above
        // buildDreadReadView() in that file for the full reasoning.
        var $viewContainer = $('<div>', { id: 'risk-details-view-container' });
        $mount.append($viewContainer);
        if ($editBtn) {
            $mount.append($editBtn);
        }

        if (useCache && cachedViewData && cachedViewData.riskId === riskId) {
            // Cancel: nothing changed server-side, so render straight from
            // the last successful load instead of re-fetching.
            RiskDetailsView.renderFromData(VIEW_CONTAINER_SELECTOR, cachedViewData.data, cachedViewData.riskId, canEdit);
            if (typeof onLoaded === 'function') {
                onLoaded();
            }
            return;
        }

        // Initial load (no cache yet) or a post-Save return to read mode
        // (values genuinely changed) -- do a real fetch, and cache the
        // result for a future Cancel. inlineEditable: canEdit -- gates the
        // per-field inline-edit affordance (renderFieldItem(), risk-details-
        // view.js) to whoever can already reach the big Edit Details form;
        // Mitigation/Review's own coordinators never pass this, so this stays
        // a Details-tab-only rollout for now.
        RiskDetailsView.init(VIEW_CONTAINER_SELECTOR, riskId, function (data) {
            cachedViewData = { riskId: riskId, data: data };
            patchRiskSummaryTiles(data.risk_summary);
            if (typeof onLoaded === 'function') {
                onLoaded();
            }
        }, { inlineEditable: canEdit });
    }

    // The page-header Inherent/Residual Risk tiles (view_score_html(),
    // includes/display.php) are plain server-rendered HTML with no client-
    // side render path of their own -- a scoring save here only re-fetches
    // the Cards system's own data, so without this they stay frozen at
    // whatever they were at initial page load. A harmless no-op on the
    // INITIAL load (the tiles are already correct, freshly server-rendered)
    // -- this only visibly does anything after a save actually changed the
    // score. Undefined on a risk that has no id yet (Submit Risk never
    // reaches this coordinator) or if the ids below aren't on the page for
    // some other reason -- .length guards make every jQuery call a no-op
    // rather than throwing.
    function patchRiskSummaryTiles(riskSummary) {
        if (!riskSummary) {
            return;
        }
        // .sr-risk-level-tile's white-vs-charcoal text is a luminance pick
        // against the painted fill (see risk_level_tile_is_light_color(),
        // includes/display.php, for the server-rendered initial paint) --
        // reuse window.CvssRiskLevelPill.paint (risk-details-form.js) rather
        // than duplicating that luminance math a third time, so a save that
        // moves a tile onto a pale configured color still gets .on-light
        // here too, not just on first page load.
        var $inherentTile = $('#inherent-risk-tile');
        if ($inherentTile.length && window.CvssRiskLevelPill) {
            window.CvssRiskLevelPill.paint($inherentTile, riskSummary.calculated_risk_color);
            $('#inherent-risk-score').text(riskSummary.calculated_risk);
            $('#inherent-risk-level').text(riskSummary.calculated_risk_level_name);
        }
        var $residualTile = $('#residual-risk-tile');
        if ($residualTile.length && window.CvssRiskLevelPill) {
            window.CvssRiskLevelPill.paint($residualTile, riskSummary.residual_risk_color);
            $('#residual-risk-score').text(riskSummary.residual_risk);
            $('#residual-risk-level').text(riskSummary.residual_risk_level_name);
        }
    }

    // Re-syncs the header tiles (and drops the read-mode cache) after a save made
    // on ANOTHER tab that moves this risk's residual score: an Edit Mitigation
    // save (its own mitigation percent, or the controls attached to it --
    // get_residual_risk() falls back to the HIGHEST attached control's percent
    // when the risk carries none of its own) or a per-control Control Validation
    // save (whose percent overrides the control's own). Those coordinators
    // announce it as `risk:scoring-changed` on document; THIS module owns the
    // tiles and the cache, so it does the refresh. Without it the residual score
    // only caught up on a full page reload.
    var summaryRefreshSeq = 0;

    function refreshRiskSummary(riskId) {
        cachedViewData = null;
        // Two saves in quick succession fire two GETs that can return out of
        // order; only the newest request may repaint the tiles.
        var seq = ++summaryRefreshSeq;
        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/ui/risk/' + riskId + '/values',
            dataType: 'json'
        }).done(function (response) {
            if (seq === summaryRefreshSeq && response && response.data) {
                patchRiskSummaryTiles(response.data.risk_summary);
            }
        });
    }

    $(document).on('risk:scoring-changed', function (event, riskId) {
        var $mount = $(VIEW_SELECTOR);
        if (!$mount.length || String($mount.data('risk-id')) !== String(riskId)) {
            return;
        }
        refreshRiskSummary(riskId);
    });

    // The legacy record header (view_top_table(), includes/display.php --
    // ID#/Status/Subject + Actions menu) is a SEPARATE, server-rendered
    // region (.overview-container) with no client-side render path of its
    // own. A Details-tab save that changes Subject (or, once other fields
    // grow their own inline editors, anything else that header displays)
    // only re-fetches THIS module's own data via renderReadMode() above --
    // without this, the header stays frozen at whatever it was at page
    // load/last legacy-side refresh. Reuses the same GET .../risk/overview
    // endpoint risk.js's own .change-status handler already fetches from,
    // just to re-render the fragment rather than to extract a form out of
    // it. Only one .overview-container exists per page (one risk view at a
    // time), so no tabContainer scoping is needed the way risk.js's own
    // handlers require it.
    function refreshLegacyOverview(riskId) {
        var $overview = $('.overview-container');
        if (!$overview.length) {
            return;
        }
        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/management/risk/overview?id=' + riskId,
            dataType: 'json'
        }).done(function (response) {
            if (response && response.data) {
                $overview.html(response.data);
            }
        });
    }

    function renderEditMode($mount, riskId) {
        currentGeneration += 1;
        var generation = currentGeneration;

        // Idempotent no-op if nothing was ever built into the view
        // container -- see RiskDetailsView.destroy()'s own docs.
        RiskDetailsView.destroy(VIEW_CONTAINER_SELECTOR);
        $mount.empty();

        // `.sr-qform` is REQUIRED, not decorative -- _questionnaire.scss
        // scopes every .sr-qcard/.sr-qactions rule the engine's rendered
        // Cards and sticky Save button depend on under a `.sr-qform { ... }`
        // parent selector (see management/index.php's #submit-risk-container
        // for the identical precedent). `data-submit-label` carries the
        // localized Save button text into buildActionsBar(), which reads it
        // via `container.data('submit-label')`.
        var $formContainer = $('<div>', { id: 'risk-view-details-form-container', 'class': 'sr-qform' })
            .attr('data-submit-label', _lang['Save']);
        // Top-of-tab Cancel, replacing Edit Details for the duration of edit
        // mode -- mirrors risk-view-mitigation.js's/risk-view-review.js's own
        // .risk-mitigation-cancel-edit/.risk-review-cancel-edit (same classes,
        // same position), which this tab was the odd one out for: it only had
        // the bottom sticky action bar's Cancel (onCancel below), leaving a
        // long form with no dismiss affordance in view without scrolling back
        // up. Both now coexist -- this one for the top of a long form, the
        // sticky bar's for the bottom -- exactly like Save already has a
        // top-of-form-adjacent Edit trigger AND a bottom sticky Save.
        var $cancelBtn = $('<button>', { type: 'button', 'class': 'btn btn-sm sr-risk-tab-cancel risk-details-cancel-edit position-absolute sr-risk-tab-action end-0' })
            .text(_lang['Cancel']);
        $mount.append($formContainer).append($cancelBtn);

        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/ui/risk/' + riskId + '/values',
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
                prefillValues: prefillValues,
                submitMode: 'update',
                updateRiskId: riskId,
                // Gates SupportingDocumentation's edit-mode file widget
                // (buildSupportingDocumentationWidget(), risk-details-form.js).
                canModifyRisk: !!response.data.can_modify_risk,
                // This risk's OWN template group, as the values response
                // reports it -- not the viewer's default. Pinning it suppresses
                // the engine's template-group tab bar, which belongs to the
                // create flow: an existing risk's group is a property of the
                // row, and its custom-field values are stored against it, so a
                // mid-edit switcher would swap the field roster out from under
                // values that cannot follow it.
                pinnedTemplateGroupId: response.data.template_group_id,
                onSaveSuccess: function () {
                    renderReadMode($mount, riskId);
                    refreshLegacyOverview(riskId);
                },
                // Sticky action bar's own Cancel button (design-system.md
                // #5's ".sr-qcancel", buildActionsBar() in risk-details-
                // form.js) -- consolidated here from a separate floating
                // top-right button this coordinator used to render itself.
                // Nothing changed server-side, so this is the SAME re-
                // render-from-cache path the old button's delegated handler
                // used (useCache=true).
                onCancel: function () {
                    renderReadMode($mount, riskId, true);
                }
            });
        }).fail(function () {
            if (generation !== currentGeneration) {
                return;
            }
            showAlertFromMessage(_lang['RequestFailed'], false);
        });
    }

    function editDetails() {
        var $mount = $(VIEW_SELECTOR);
        if (!$mount.length) {
            return;
        }
        renderEditMode($mount, $mount.data('risk-id'));
    }

    $('body').on('click', VIEW_SELECTOR + ' .risk-details-edit-toggle', function (e) {
        e.preventDefault();
        editDetails();
    });

    // The Actions-menu entry point (view_top_table(), includes/display.php)
    // -- same dual-trigger shape as risk-view-review.js's
    // .risk-review-perform-from-actions, but this one needs no tab switch:
    // Details is the default/already-active tab. Its <a> also carries a
    // real href (view.php?action=editdetail&id=X) for the case where this
    // handler is reached from a DIFFERENT page (e.g. a bookmark or a link
    // clicked from search results) -- preventDefault() here only matters
    // for the same-page case, where a fetch+reload would be a needless
    // round trip when the Cards engine can just switch modes in place.
    $('body').on('click', '.edit-risk', function (e) {
        e.preventDefault();
        editDetails();
    });

    // `?action=editdetail` is the SAME repo-wide deep-link contract
    // Mitigation's/Review's own maybeAutoOpenFromDeepLink() already fix
    // (see risk-view-mitigation.js's identical function for the full
    // history) -- the Actions-menu "Edit Risk" link (view_top_table(),
    // includes/display.php) still sets it, but nothing read it for the
    // Details tab specifically until now: this mount-point swap retired the
    // legacy AJAX handler that used to. Unlike editmitigation/editreview, no
    // `#hash` accompanies it -- Details is the default/already-active tab,
    // so there is nothing for the page's own hash-based tab-selector to do.
    // Checked only after read mode's OWN values fetch has resolved (via
    // renderReadMode()'s onLoaded) -- not on every read-mode render, just
    // the initial page load -- so cancelling back to read mode never
    // reopens the form on its own.
    function maybeAutoOpenFromDeepLink() {
        try {
            if (new URLSearchParams(window.location.search).get('action') === 'editdetail') {
                editDetails();
            }
        } catch (e) {
            // URLSearchParams is universally supported in this app's target
            // browsers; guarded only so a future odd query string can never
            // throw out of this read-mode callback.
        }
    }

    // Mirrors risk-view-mitigation.js's/risk-view-review.js's own top-of-tab
    // Cancel handler -- same re-render-from-cache path (useCache=true) the
    // sticky action bar's onCancel (above) already uses, so both Cancel
    // affordances behave identically regardless of which one was clicked.
    $('body').on('click', VIEW_SELECTOR + ' .risk-details-cancel-edit', function (e) {
        e.preventDefault();
        var $mount = $(VIEW_SELECTOR);
        renderReadMode($mount, $mount.data('risk-id'), true);
    });

    // Renders (or re-renders) read mode into whatever #risk-view-details-mount
    // is currently in the DOM. Safe to call repeatedly: renderReadMode() bumps
    // the generation guard, tears down the edit engine, empties the mount and
    // rebuilds from a fresh fetch, so a second call simply discards the first
    // call's DOM and supersedes any of its still-in-flight responses.
    //
    // Exported because risk.js's Mitigation/Review handlers (Edit Mitigation,
    // Cancel Mitigation, Perform Review, Save/Cancel Review, View All Reviews,
    // Close Risk, Change Status) re-render the WHOLE
    // management/partials/details.php partial server-side and drop it in with
    // `$('.content-container', tabContainer).html(data.data)`. That replaces the
    // #details pane too, so the mount this module rendered into on page load is
    // gone and the freshly-inserted one is empty. Some of those handlers call
    // risk.js's callbackAfterRefreshTab() afterwards and some do not, so the
    // re-invocation hook lives in the partial itself (an inline <script> jQuery
    // executes on insertion, emitted only on the AJAX path) rather than in any
    // one handler -- see management/partials/details.php's #details pane.
    function render() {
        var $mount = $(VIEW_SELECTOR);
        if ($mount.length) {
            renderReadMode($mount, $mount.data('risk-id'), false, maybeAutoOpenFromDeepLink);
        }
    }

    $(render);

    window.RiskViewDetails = {
        render: render
    };
})();
