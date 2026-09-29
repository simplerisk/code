/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Shared bulk-action request batching, consolidated out of near-identical
// hand-copies (review-risk.js's runBulkActionBatched(),
// compliance-define-tests.js's runSequential(), governance-documents.js's
// deleteOne() loop, governance-exceptions.js's bulk-delete loop -- the
// original reference implementation this was copied from). Manage Audits'
// inline bulk-delete handler (includes/compliance.php's display_audits())
// used to be a fifth consumer, batched the same way, but its Delete action
// now sends every selected id in a single request to a true batch endpoint
// (api_v2_compliance_audits_batch_delete(), api/v2/includes/
// compliance.php) instead of looping -- it no longer needs this helper.
// Every remaining grid can still hand its bulk-action submit handler up to
// that page's own SELECT_ALL_MAX ids in a single "Select all N" click, and
// firing that many requests simultaneously would overwhelm the browser's
// connection queue and the server. Loaded via the
// 'CUSTOM:bulk-request-batch.js' header.php token (a plain global function,
// not a module), the same way sr-select.js/sr-row-actions-menu.js/
// alert-helper.js are shared across pages.

/**
 * Calls requestFn(id) once for every id in `ids`, chunked into `batchSize`
 * -sized batches that run one at a time -- requests WITHIN a batch run
 * concurrently, but each batch waits for the previous one to fully settle
 * before starting, bounding the request rate instead of firing every id at
 * once.
 *
 * Each requestFn(id) promise is wrapped in its own always-resolving
 * $.Deferred -- $.when.apply() rejects as soon as ANY input promise
 * rejects, which would abandon the in-flight batch the moment the first
 * request failed instead of letting every request in that batch complete --
 * so failures are tallied instead of aborting the chain, and a partial
 * failure can be reported honestly rather than showing the same success
 * outcome either way.
 *
 * Resolves with the total failedCount across every batch (0 when every
 * request succeeded). A caller that doesn't surface partial failure (e.g.
 * Manage Audits' bulk delete, which has never shown a partial-failure
 * toast) can simply ignore the resolved value -- this never rejects, so
 * `.then(function (failedCount) { ... })` / `.always(...)` always fires.
 *
 * @param {Array} ids
 * @param {number} batchSize
 * @param {function(*): JQuery.Promise} requestFn
 * @return {JQuery.Promise} resolves with the total failedCount
 */
function runBatchedRequests(ids, batchSize, requestFn) {
    var failedCount = 0;
    var batches = [];
    for (var i = 0; i < ids.length; i += batchSize) {
        batches.push(ids.slice(i, i + batchSize));
    }
    var chain = $.Deferred().resolve().promise();
    batches.forEach(function (batchIds) {
        chain = chain.then(function () {
            var settled = batchIds.map(function (id) {
                var d = $.Deferred();
                requestFn(id)
                    .fail(function () { failedCount++; })
                    .always(function () { d.resolve(); });
                return d.promise();
            });
            return $.when.apply($, settled);
        });
    });
    return chain.then(function () {
        return failedCount;
    });
}
