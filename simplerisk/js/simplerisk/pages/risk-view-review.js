/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// ----------------------------------------------------------------------
// Coordinates the Review tab's read/submit toggle on management/view.php
// (Phase 4c-ii). Structurally mirrors risk-view-mitigation.js (generation
// guard, read-mode cache, $isAjax re-init guard) with one fundamental
// difference: Review is an append-only log, not an editable record --
// "edit mode" here always opens a BLANK submit form (RiskDetailsForm's new
// submitMode: 'create-log-entry'), never a prefilled one. There is no
// "current review" to edit.
//
// TWO entry points call performReview(): the tab's own "Perform a Review"
// button, AND the Actions-menu .perform-review link (view_top_table(),
// includes/display.php) -- both switch to this tab and open the same blank
// form, retiring risk.js's separate AJAX-partial-refresh handler for this
// action (risk.js:729) the same way Phase 4a/4b-iii retired it for
// Details/Mitigation.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    var VIEW_SELECTOR = '#risk-view-review-mount';
    var VIEW_CONTAINER_SELECTOR = '#risk-review-view-container';
    var FORM_CONTAINER_SELECTOR = '#risk-view-review-form-container';
    var VALUES_PATH_SUFFIX = '/review-values';
    var HISTORY_MODAL_ID = 'risk-review-history-modal';

    var currentGeneration = 0;
    var cachedViewData = null;
    var historyModalBuilt = false;

    function valuesPath(riskId) {
        return '/ui/risk/' + riskId + VALUES_PATH_SUFFIX;
    }

    // `onLoaded`, when given, fires once this read-mode render has real data
    // behind it -- either the cache-hit branch or the values fetch actually
    // resolving. Only render()'s initial page-load call passes one (Finding
    // 5's deep-link check needs read mode's values fetch to have completed
    // before it can decide whether to auto-open the submit form); every
    // other caller (performReview()'s own eventual return-to-read-mode,
    // cancelReview()) omits it and behaves exactly as before.
    function renderReadMode($mount, riskId, useCache, onLoaded) {
        currentGeneration += 1;

        RiskDetailsForm.destroy(FORM_CONTAINER_SELECTOR);
        $mount.empty();

        var $viewContainer = $('<div>', { id: 'risk-review-view-container' });
        $mount.append($viewContainer);

        if (useCache && cachedViewData && cachedViewData.riskId === riskId) {
            RiskDetailsView.renderFromData(VIEW_CONTAINER_SELECTOR, cachedViewData.data, cachedViewData.riskId);
            renderPerformReviewButton($mount, riskId, cachedViewData.data.can_perform_review);
            if (typeof onLoaded === 'function') {
                onLoaded();
            }
            return;
        }

        RiskDetailsView.init(VIEW_CONTAINER_SELECTOR, riskId, function (data) {
            cachedViewData = { riskId: riskId, data: data };
            renderPerformReviewButton($mount, riskId, data.can_perform_review);
            if (typeof onLoaded === 'function') {
                onLoaded();
            }
        }, {
            tabIndex: 3,
            valuesPath: valuesPath(riskId)
        });
    }

    // can_perform_review is RISK-INSTANCE-SPECIFIC (check_review_permission_
    // by_risk_id(), tiered by the risk's own calculated level) -- unlike
    // Details/Mitigation's plain data-can-edit="<?php has_permission(...) ?>"
    // session-wide check, this cannot be resolved from a static mount-point
    // attribute; it comes back on every values fetch instead. "View All
    // Reviews" carries no such gate -- viewing history is a read concern,
    // available to anyone who reached this tab at all -- so it always
    // renders, alone in its own .sr-risk-tab-actions wrapper when Perform a
    // Review is absent (the SAME wrapper Mitigation's Accept/Reject +
    // Edit Mitigation cluster uses, risk-view-mitigation.js's
    // renderAcceptMitigationWidget() -- .sr-risk-tab-action alone assumes
    // it is the ONLY absolutely-positioned trigger in the tab-pane; two of
    // them side by side need the flex wrapper instead, per that class's
    // own scss/modules/_tabs.scss comment).
    function renderPerformReviewButton($mount, riskId, canPerformReview) {
        $mount.find('.sr-risk-tab-actions').remove();

        var $actionRow = $('<div>').addClass('sr-risk-tab-actions');

        if (canPerformReview) {
            // .btn-submit, not .btn-dark -- see risk-view-details.js's own
            // $editBtn for the full reasoning (design-system.md's documented
            // .btn-dark/.btn-submit meanings).
            $('<button>', { type: 'button', 'class': 'btn btn-sm btn-submit risk-review-perform-toggle sr-risk-tab-action' })
                .text(_lang['PerformAReview'])
                .appendTo($actionRow);
        }

        $('<button>', { type: 'button', 'class': 'btn btn-sm sr-risk-tab-cancel risk-review-view-all sr-risk-tab-action' })
            .text(_lang['ViewAllReviews'])
            .appendTo($actionRow);

        $mount.append($actionRow);
    }

    function renderEditMode($mount, riskId) {
        currentGeneration += 1;
        var generation = currentGeneration;

        RiskDetailsView.destroy(VIEW_CONTAINER_SELECTOR);
        $mount.empty();

        var $formContainer = $('<div>', { id: 'risk-view-review-form-container', 'class': 'sr-qform' })
            .attr('data-submit-label', _lang['SubmitReview']);
        // .sr-risk-tab-cancel, not .btn-secondary -- see _tabs.scss's own
        // rule for the full reasoning (matches the ghost/secondary language
        // .sr-qcancel uses elsewhere, instead of Bootstrap's solid grey fill).
        var $cancelBtn = $('<button>', { type: 'button', 'class': 'btn btn-sm sr-risk-tab-cancel risk-review-cancel-edit position-absolute sr-risk-tab-action end-0' })
            .text(_lang['Cancel']);
        $mount.append($formContainer).append($cancelBtn);

        // Re-review carry-forward, at the user's explicit request: the
        // legacy form pre-filled Review/Next Step/Comment from the risk's
        // most recent prior mgmt_reviews row (display_review_edit($review)/
        // display_next_step_edit($next_step)/display_comments_edit($comment),
        // includes/displayrisks.php, fed by management/view.php's own
        // get_review_by_id($id)[0] fetch) -- this engine dropped that when
        // it moved Review to a genuinely blank create-log-entry form, and a
        // truly first-ever review (no prior row) degrades to the exact same
        // blank-values shape as before, harmlessly. `values` here is the
        // SAME {formName: {raw, display}} map read mode's own Review card
        // renders from (api_get_ui_risk_review_values(), api/v2/includes/
        // api.php, resolved against get_review_by_id()'s most recent row)
        // -- reshaped into RiskDetailsForm's own {formName: raw} prefill
        // contract exactly like risk-view-details.js/risk-view-mitigation.js
        // already do for their own values responses. NextReviewDate is
        // deliberately NOT part of this carry-forward -- Finding 3(a)'s own
        // reasoning below still holds: a fresh review always recomputes its
        // own default from the risk's CURRENT score, never a prior review's
        // saved override.
        function buildPrefillValues(values) {
            var prefillValues = {};
            $.each(values || {}, function (formName, entry) {
                prefillValues[formName] = entry.raw;
            });
            return prefillValues;
        }

        // template_group_id/values/next_review_default all come from the
        // cached read-mode fetch (or a fresh one) so the form renders the
        // right field roster pre-filled from the same data read mode shows.
        var templateGroupId = (cachedViewData && cachedViewData.riskId === riskId)
            ? cachedViewData.data.template_group_id
            : null;

        function openForm(pinnedTemplateGroupId, nextReviewDateDefault, values) {
            if (generation !== currentGeneration) {
                return;
            }
            RiskDetailsForm.init(FORM_CONTAINER_SELECTOR, {
                tabIndex: 3,
                prefillValues: buildPrefillValues(values),
                submitMode: 'create-log-entry',
                createUrl: '/api/v2/risks/' + riskId + '/reviews',
                pinnedTemplateGroupId: pinnedTemplateGroupId,
                // Finding 3(a): the SetNextReviewDate widget's read-only
                // informational text (RiskDetailsForm.init()'s own comment
                // on this option) -- NOT a prefill; the date input itself
                // still opens blank.
                nextReviewDateDefault: nextReviewDateDefault || null,
                onSaveSuccess: function () {
                    renderReadMode($mount, riskId);
                },
                // Sticky action bar's own Cancel button (design-system.md
                // #5's ".sr-qcancel", buildActionsBar() in risk-details-
                // form.js) -- mirrors risk-view-details.js's identical
                // option, added there first: a long form's only dismiss
                // affordance being the top-of-tab Cancel (.risk-review-
                // cancel-edit above) left no in-view way to back out
                // without scrolling back up. Same re-render-from-cache path
                // (useCache=true) that button's own click handler already
                // uses, so both Cancel affordances behave identically.
                onCancel: function () {
                    renderReadMode($mount, riskId, true);
                }
            });
        }

        if (templateGroupId) {
            var cachedNextReviewDateDefault = (cachedViewData && cachedViewData.riskId === riskId)
                ? cachedViewData.data.next_review_default
                : null;
            var cachedValues = (cachedViewData && cachedViewData.riskId === riskId)
                ? cachedViewData.data.values
                : null;
            openForm(templateGroupId, cachedNextReviewDateDefault, cachedValues);
        } else {
            $.ajax({ type: 'GET', url: BASE_URL + '/api/v2' + valuesPath(riskId), dataType: 'json' })
                .done(function (response) {
                    openForm(response.data.template_group_id, response.data.next_review_default, response.data.values);
                })
                .fail(function () {
                    if (generation !== currentGeneration) {
                        return;
                    }
                    showAlertFromMessage(_lang['RequestFailed'], false);
                });
        }
    }

    function performReview() {
        var $mount = $(VIEW_SELECTOR);
        if (!$mount.length) {
            return;
        }
        renderEditMode($mount, $mount.data('risk-id'));
    }

    // ------------------------------------------------------------------
    // "View All Reviews" modal -- built once, lazily, matching risk-
    // mitigation-controls.js's own buildValidationModal() shape. Replaces
    // the always-inline, unlabeled review_history_html dump renderCards()
    // (risk-details-view.js) used to append under this tab's own Review
    // card: that showed as a plain-text duplicate of the SAME single
    // review already displayed above it whenever a risk had exactly one
    // (the common case), with its own Comment cell never purified/escaped
    // at its sink either (literal "<p>...</p>" for any review whose
    // comment happened to contain markup). GET /ui/risk/{id}/review-history
    // (api/v2/includes/api.php) returns every review as clean {raw,
    // display, display_html} entries instead, reusing
    // api_ui_review_field_resolvers() -- the SAME resolvers the current-
    // review Card above already uses, keyed identically to
    // CORE_FIELD_FORM_NAMES (risk-details-view.js), so each history entry
    // can be handed straight to RiskDetailsView.renderFieldsGrid() as its
    // `values` object. Each entry renders through the exact same
    // renderFieldItem()/field-roster/order the live Review card above uses
    // (reviewCardFields()/renderHistoryEntries() below), not a hand-rolled
    // lookalike -- so a history entry and the current review are genuinely
    // the same kind of thing, Comment's richtext display_html included.
    // ------------------------------------------------------------------

    function buildHistoryModal() {
        if (historyModalBuilt) {
            return;
        }
        historyModalBuilt = true;

        var $modal = $('<div>', { id: HISTORY_MODAL_ID, 'class': 'modal fade sr-modal', tabindex: '-1', 'aria-hidden': 'true', 'aria-labelledby': HISTORY_MODAL_ID + '-title' });

        var $header = $('<div>', { 'class': 'modal-header' })
            .append($('<span>', { 'class': 'sr-modal-icon' }).append($('<i>', { 'class': 'fa fa-clock-rotate-left', 'aria-hidden': 'true' })))
            .append($('<h4>', { 'class': 'modal-title', id: HISTORY_MODAL_ID + '-title', text: _lang['ReviewHistory'] }))
            .append($('<button>', { type: 'button', 'class': 'btn-close', 'data-bs-dismiss': 'modal', 'aria-label': _lang['Close'] }));

        // sr-qcards-stack is required, not decorative -- .sr-qcard-head's
        // icon+title styling (sr-qcard-head-styled, scss/mixins/qcard-
        // head.scss) is scoped to `.sr-qcards-stack .sr-qcard-head`
        // specifically (modules/_sr-modal.scss), the SAME scoping the live
        // Review card's own .sr-qcards-stack wrapper (risk-details-view.js's
        // renderCards()) relies on. Without it here, each entry's <h2> falls
        // through to the older bare `.sr-qcard-head` rule below (which
        // styles an <h3>/.sr-qcard-title, not this markup's <h2>) and
        // renders at the browser's default oversized h2 size -- confirmed
        // live. It also gives the 16px inter-card gap the live card stack
        // has, instead of the entries touching edge-to-edge.
        var $body = $('<div>', { 'class': 'modal-body sr-qcards-stack', id: HISTORY_MODAL_ID + '-list' });

        var $footer = $('<div>', { 'class': 'modal-footer' })
            .append($('<button>', { type: 'button', 'class': 'btn btn-dark', 'data-bs-dismiss': 'modal', text: _lang['Close'] }));

        $modal.append(
            $('<div>', { 'class': 'modal-dialog modal-dialog-centered modal-dialog-scrollable' }).append(
                $('<div>', { 'class': 'modal-content' }).append($header).append($body).append($footer)
            )
        );

        $('body').append($modal);
    }

    // The live Review card's own field roster (card_key === 'review'),
    // straight from the SAME payload renderReadMode() already cached for its
    // cache-hit re-render branch -- so a history entry renders through
    // RiskDetailsView.renderFieldsGrid()/renderFieldItem() with the exact
    // field list/order/widgets (including Comment's richtext display_html)
    // the live card uses, not a hand-rolled lookalike that silently drifts
    // the next time a Review-tab field or its resolver changes. Returns null
    // only in the narrow race where this modal's Actions-menu trigger (no
    // tab switch required, see that click handler below) fires before this
    // tab's own initial RiskDetailsView.init() fetch has resolved --
    // renderHistoryEntries() falls back to a fixed field order in that case.
    function reviewCardFields() {
        if (!cachedViewData || !Array.isArray(cachedViewData.data.fields)) {
            return null;
        }
        return cachedViewData.data.fields.filter(function (f) {
            return (f.card_key || 'general') === 'review';
        });
    }

    var FALLBACK_FIELD_ORDER = ['ReviewDate', 'Reviewer', 'Review', 'NextStep', 'NextReviewDate', 'Comment'];

    // One .sr-qcard per review, newest first (the server's own ORDER BY --
    // get_review_by_id(), includes/functions.php), each with the SAME
    // icon+title header and .sr-qgrid field layout as the live Review card
    // above it (RiskDetailsView.cardIcon()/cardLabel()/renderFieldsGrid()) --
    // a plain <div> head, not the live card's collapsible <button> one,
    // since collapsing a single entry in an already-scrollable list of
    // entries has no clear value.
    function renderHistoryEntries(reviews, riskId) {
        var $list = $('#' + HISTORY_MODAL_ID + '-list').empty();

        if (!reviews || !reviews.length) {
            $list.append($('<div>', { 'class': 'sr-qhint', text: _lang['None'] }));
            return;
        }

        var fields = reviewCardFields() || FALLBACK_FIELD_ORDER.map(function (name) {
            return { name: name, is_basic: 1, pos_y: 0, pos_x: 0 };
        });

        reviews.forEach(function (entry) {
            var $head = $('<div>', { 'class': 'sr-qcard-head' });
            $('<span>', { 'class': 'sr-qcard-ico' })
                .append($('<i>', { 'class': 'fa ' + RiskDetailsView.cardIcon('review'), 'aria-hidden': 'true' }))
                .appendTo($head);
            $('<span>', { 'class': 'sr-qcard-htext' })
                .append($('<h2>', { text: RiskDetailsView.cardLabel('review') }))
                .appendTo($head);

            var $qgrid = RiskDetailsView.renderFieldsGrid(fields, entry, riskId);

            $('<section>', { 'class': 'sr-qcard' })
                .append($head)
                .append($('<div>', { 'class': 'sr-qcard-body' }).append($qgrid))
                .appendTo($list);
        });
    }

    function openReviewHistoryModal(riskId) {
        buildHistoryModal();

        $('#' + HISTORY_MODAL_ID + '-list').html(
            '<div class="sr-table-empty"><div class="sr-table-empty-icon"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i></div></div>'
        );
        var $modal = $('#' + HISTORY_MODAL_ID);
        $modal.modal('show');

        $.ajax({
            url: BASE_URL + '/api/v2/ui/risk/' + riskId + '/review-history',
            type: 'GET'
        }).done(function (res) {
            renderHistoryEntries((res && res.data) ? res.data.reviews : [], riskId);
        }).fail(function (xhr) {
            if (xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message, false);
            } else {
                showAlertFromMessage(_lang['RequestFailed'], false);
            }
            $modal.modal('hide');
        });
    }

    $('body').on('click', VIEW_SELECTOR + ' .risk-review-perform-toggle', function (e) {
        e.preventDefault();
        performReview();
    });

    $('body').on('click', VIEW_SELECTOR + ' .risk-review-view-all', function (e) {
        e.preventDefault();
        openReviewHistoryModal($(VIEW_SELECTOR).data('risk-id'));
    });

    // The Actions-menu entry point (view_top_table(), includes/display.php)
    // -- same dual-trigger shape as .risk-review-perform-from-actions just
    // below, but this one needs no tab switch: the modal is not scoped to
    // the Review tab's own mount, so it can open from anywhere on the page.
    $('body').on('click', '.risk-review-view-all-from-actions', function (e) {
        e.preventDefault();
        openReviewHistoryModal($(VIEW_SELECTOR).data('risk-id'));
    });

    $('body').on('click', VIEW_SELECTOR + ' .risk-review-cancel-edit', function (e) {
        e.preventDefault();
        var $mount = $(VIEW_SELECTOR);
        renderReadMode($mount, $mount.data('risk-id'), true);
    });

    // The Actions-menu entry point (view_top_table(), includes/display.php).
    // Switches to the Review tab (matching callbackAfterRefreshTab()'s own
    // bootstrap.Tab mechanism, risk.js) THEN opens the same blank form --
    // one code path, two triggers.
    $('body').on('click', '.risk-review-perform-from-actions', function (e) {
        e.preventDefault();
        var tabEl = document.querySelector('#tab_review');
        if (tabEl) {
            new bootstrap.Tab(tabEl).show();
        }
        performReview();
    });

    // Finding 5: `?action=editreview#review` is a repo-wide-confirmed deep-
    // link contract (~12 call sites -- review-risk.js's "Perform Review" row
    // action, reporting.php, the Management Review Yes/No/PASTDUE column
    // links in functions.php, and the notification Extra's e-mail links),
    // expecting the page to land directly in the submit form. Before this
    // phase, management/partials/details.php's `$action == 'editreview'`
    // branch handled that; nothing reads the query param after the
    // mount-point swap. Checked only after read mode's OWN values fetch has
    // resolved (via renderReadMode()'s onLoaded) -- not on every read-mode
    // render, just the initial page load -- so cancelling back to read mode
    // never reopens the form on its own.
    function maybeAutoOpenFromDeepLink() {
        try {
            if (new URLSearchParams(window.location.search).get('action') === 'editreview') {
                performReview();
            }
        } catch (e) {
            // URLSearchParams is universally supported in this app's target
            // browsers; guarded only so a future odd query string can never
            // throw out of this read-mode callback.
        }
    }

    function render() {
        var $mount = $(VIEW_SELECTOR);
        if ($mount.length) {
            renderReadMode($mount, $mount.data('risk-id'), false, maybeAutoOpenFromDeepLink);
        }
    }

    $(render);

    window.RiskViewReview = {
        render: render,
        performReview: performReview
    };
})();
