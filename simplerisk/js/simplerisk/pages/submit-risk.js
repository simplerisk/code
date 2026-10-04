/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// The standalone Submit Risk page's entry point. Every line of rendering
// logic that used to live here now lives in
// js/simplerisk/common/risk-details-form.js, which management/index.php loads
// alongside this file; this wrapper's only job is to point that engine at the
// page's own container.
//
// `embedded: false` is what keeps the standalone page's behavior identical to
// before the extraction: it gets the sticky .sr-qactions action bar and the
// engine's own submitRisk() path, not the hidden .save-risk-form affordance a
// modal caller uses to drive risk.js's delegated handler.

(function () {
    'use strict';

    $(function () {
        var container = $('#submit-risk-container');
        if (container.length === 0) {
            return;
        }
        window.RiskDetailsForm.init('#submit-risk-container', {
            embedded: false,
            // Gates SupportingDocumentation's file input (buildSupportingDocumentationWidget(),
            // risk-details-form.js) -- read from the container's own
            // data attribute (management/index.php), not hardcoded true:
            // this page already enforces submit_risks before rendering at
            // all, but the container carries the real session value anyway
            // so this call site matches review-risk.js's identical one
            // exactly, rather than being a special case.
            canSubmitRisk: container.attr('data-can-submit-risk') === '1'
        });
    });
})();
