<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(
    // Script tokens (header.php's render_header_and_sidebar() script list):
    // - CUSTOM:sr-row-actions-menu.js: the row-actions overflow disclosure
    //   (js/simplerisk/sr-row-actions-menu.js) needed by the
    //   .sr-row-actions-wrap/.sr-row-actions-toggle markup on touch/narrow
    //   viewports (_tables.scss's sr-row-actions-overflow-tier mixin). Same
    //   token Compliance/Governance's own row-actions-menu pages load.
    // - 'datetimerangepicker': pulls in moment.min.js, which
    //   formatDueDate()/renderDueState() (review-risk.js) call directly --
    //   without it every date-typed column (Due Date, submission_date,
    //   closure_date, planning_date, mitigation_date, review_date,
    //   next_review_date) throws `ReferenceError: moment is not defined` and
    //   aborts DataTables' row rendering entirely.
    // - CUSTOM:sr-select.js: the Owner/Team/Risk Level/Reviewer filter
    //   <select>s (includes/display.php) use the shared sr-select
    //   multi-select/search widget; without it they silently fall back to
    //   plain unstyled native multi-selects.
    // - WYSIWYG/CUSTOM:common.js/CUSTOM:pages/risk.js/CUSTOM:cve_lookup.js:
    //   back the embedded Add Risk modal (display_add_risk(), includes/
    //   display.php), the same form compliance/testing.php and
    //   compliance/view_test.php embed in their own #modal-new-risk.
    // - 'UILayoutWidget': the Insights band's Gridstack + per-widget AJAX
    //   fetch (includes/Widgets/UILayout.php) -- without it the band's
    //   markup renders but the grid never initializes.
    // - 'EXTRA:JS:artificial_intelligence:ai-chat.js' + 'show_ai_chat': AI
    //   Chat parity with the three legacy pages this page replaces (Plan
    //   Your Mitigations/Perform Management Reviews/Review Risks
    //   Regularly), gated the same way management/index.php and
    //   management/view.php gate it.
    // - 'colreorder': DataTables' ColReorder extension, used by
    //   reviewRiskDtOptions' colReorder option below.
    // - CUSTOM:bulk-request-batch.js is NOT needed here: every bulk-action
    //   submit handler (Comment/Reassign Owner/Reassign Mitigation Owner/
    //   Change Status/Close Risk) now sends its whole "Select all N" id set
    //   to ONE dedicated batch endpoint in a single request instead of
    //   chunking client-side, so review-risk.js no longer calls
    //   runBatchedRequests() (js/simplerisk/bulk-request-batch.js).
    // - 'gridstack' + CUSTOM:common/risk-details-form.js: the + Add Risk
    //   modal's Cards-based form engine (js/simplerisk/common/
    //   risk-details-form.js, the same one management/index.php's standalone
    //   Submit Risk page uses) -- review-risk.js's shown.bs.modal handler
    //   calls window.RiskDetailsForm.init() against #review-risk-add-modal's
    //   canvas. selectize/multiselect/WYSIWYG/datetimerangepicker (already
    //   listed above for this page's own grid/filters) double as this
    //   engine's field-widget dependencies -- gridstack is the only one this
    //   page didn't already need for itself.
    // - CUSTOM:common/cvss-v2-scoring.js MUST precede CUSTOM:common/
    //   risk-details-form.js: the RiskScoringMethod widget's CVSS holder
    //   (Phase 4d-ii) calls window.CvssV2Scoring.loadFromHiddenFields()/
    //   calculateCVSS() directly from its "Score with CVSS" click handler --
    //   without this script this page's own + Add Risk modal throws
    //   TypeError: Cannot read properties of undefined (reading
    //   'loadFromHiddenFields') the moment CVSS is picked. Same insertion
    //   management/index.php and management/view.php already carry.
    // - CUSTOM:common/dread-scoring.js MUST precede CUSTOM:common/
    //   risk-details-form.js for the same reason: the RiskScoringMethod
    //   widget's DREAD holder (Phase 4d-iii) calls
    //   window.DreadScoring.calculateDread() at BUILD time, the moment the
    //   holder is constructed -- not only from a later change handler like
    //   CVSS's own click-triggered call above -- so the script must already
    //   be loaded before this page's own + Add Risk modal's canvas is
    //   first built, not merely by the time a user interacts with it.
    // - CUSTOM:common/owasp-scoring.js MUST precede CUSTOM:common/
    //   risk-details-form.js for the same reason as DREAD above: the
    //   RiskScoringMethod widget's OWASP holder (Phase 4d-iv) calls
    //   window.OwaspScoring.calculateOwasp() at BUILD time. Missing here
    //   originally (caught by Task 5's own SCENARIO-7 Playwright test --
    //   window.OwaspScoring was undefined and #OwaspScore silently stayed
    //   '0' with no thrown JS error, since buildOwaspHolder()'s own calls
    //   are all guarded by `if (window.OwaspScoring && ...)`).
    // - CUSTOM:common/classic-scoring.js MUST precede CUSTOM:common/
    //   risk-details-form.js for the same reason: the RiskScoringMethod
    //   widget's Classic holder (Risk Scoring -- Classic Inline) calls
    //   window.ClassicScoring.calculateClassic() at BUILD time. Found
    //   missing from every page's script list during this phase's own
    //   live verification -- same silent-'0' failure mode owasp-scoring.js's
    //   own omission had (buildClassicHolder()'s calls are all guarded by
    //   `if (window.ClassicScoring && ...)`, so nothing threw).
    // - CUSTOM:common/contributing-risk-scoring.js MUST precede CUSTOM:common/
    //   risk-details-form.js for the same reason as Classic/CVSS/DREAD/OWASP's
    //   own scoring scripts: the RiskScoringMethod widget's Contributing Risk
    //   holder calls window.ContributingRiskScoring.calculateContributingRisk()
    //   at BUILD time, the moment the holder is constructed.
    ['blockUI', 'selectize', 'datatables', 'colreorder', 'multiselect', 'datetimerangepicker', 'WYSIWYG', 'UILayoutWidget', 'gridstack', 'CUSTOM:common.js', 'CUSTOM:common/cvss-v2-scoring.js', 'CUSTOM:common/dread-scoring.js', 'CUSTOM:common/owasp-scoring.js', 'CUSTOM:common/classic-scoring.js', 'CUSTOM:common/contributing-risk-scoring.js', 'CUSTOM:common/risk-details-form.js', 'CUSTOM:pages/risk.js', 'CUSTOM:cve_lookup.js', 'CUSTOM:sr-row-actions-menu.js', 'CUSTOM:sr-select.js', 'CUSTOM:pages/review-risk.js', 'EXTRA:JS:artificial_intelligence:ai-chat.js'],
    ['check_riskmanagement' => true, 'show_ai_chat' => true],
    'ReviewRisk',
    'RiskManagement',
    'ReviewRisk',
    // required_localization_keys: every key L() (header.php's window.L,
    // which falls back to the raw key name when a key is missing here) or
    // sr-select.js (which reads window._lang directly for Search/
    // NoMatchingOptions) looks up must be listed here -- a key existing in
    // lang.en.php is not sufficient on its own. AllUsers/AllTeams merge the
    // former separate Owner/Reviewer/Team/Mitigation Team filters into one
    // User/Team filter each, reusing existing keys rather than adding new
    // ones; AllRiskLevels replaces the bare RiskLevels key (a plain field
    // label, not this placeholder's "All risk levels" empty-state phrasing).
    // The Columns picker section (RiskScoreColumn through Comments below)
    // reuses every key verbatim from the legacy display_custom_risk_columns()
    // helper (deleted in a4e1321963) -- no new lang.en.php keys needed there.
    // SomeRowsSkippedNoMitigation/ChangeStatusHint/StatusChanged are
    // genuinely new, append-only keys, registered in the array below since
    // review-risk.js reads them through L(). RisksClosed reuses a
    // pre-existing, previously-unused key. CloseRiskBulkConfirmBody is also
    // genuinely new, but rendered server-side via $lang[] directly in
    // includes/display.php (the modal body copy) -- not through L() -- so
    // it's deliberately absent from the array below.
    required_localization_keys: ['Mitigation', 'Review', 'Unreviewed', 'PASTDUE', 'Actions', 'AllUsers', 'AllTeams', 'AllRiskLevels', 'View', 'Edit', 'PlanYourMitigations', 'PerformReview', 'NSelected', 'SelectAllN', 'BulkAddCommentTitle', 'CommentRiskRequired', 'BulkActionPartialSuccess', 'BulkActionSuccess', 'RequestFailed', 'BulkReassignRiskOwnerTitle', 'ThisFieldIsRequired', 'BulkReassignMitigationOwnerTitle', 'SomeRowsSkippedNoMitigation', 'BulkChangeStatusTitle', 'ChangeStatusHint', 'StatusChanged', 'BulkCloseRiskTitle', 'RisksClosed', 'NoActionItemsTitle', 'NoActionItemsBody', 'Search', 'NoMatchingOptions',
        'RiskScoreColumn', 'Owner', 'Team', 'RiskColumns', 'MitigationColumns', 'ReviewColumns', 'Yes', 'No',
        'Status', 'SubmissionDate', 'DateClosed', 'ExternalReferenceId', 'ControlRegulation', 'ControlNumber', 'SiteLocation', 'RiskSource', 'Category', 'AdditionalStakeholders', 'Technology', 'OwnersManager', 'SubmittedBy', 'Tags', 'RiskScoringMethod', 'ResidualRisk', 'Project', 'DaysOpen', 'AffectedAssets', 'RiskAssessment', 'AdditionalNotes', 'RiskMapping', 'ThreatMapping', 'LastComment',
        'MitigationPlanned', 'PlanningStrategy', 'MitigationPlanning', 'MitigationEffort', 'MitigationCost', 'MitigationOwner', 'MitigationTeam', 'MitigationAccepted', 'MitigationDate', 'MitigationControls', 'CurrentSolution', 'SecurityRecommendations', 'SecurityRequirements',
        'ReviewCompleted', 'ManagementReview', 'ReviewDate', 'NextReviewDate', 'NextStep', 'Comments',
        // 'FilterBy' -- clickable Team chip follow-up (renderTeamChips(),
        // review-risk.js): the chip's title attribute reads
        // L('FilterBy') + ' ' + <team name>. Reused, pre-existing key (not
        // added here) -- verified live before this entry was added that
        // L() otherwise falls back to the raw key name ("FilterBy" shown
        // literally) when it's missing from this array.
        'FilterBy',
        // 'ReviewedBy' -- the new reviewer column's picker checkbox text
        // (columnLabel()'s L(COLUMN_LABEL_KEYS['reviewer']) call,
        // review-risk.js). The <th> itself is server-rendered (display.php
        // reads $lang['ReviewedBy'] directly, no L() call needed there) --
        // this entry is for the picker only.
        'ReviewedBy',
        // 'SubmitRisk' -- the + Add Risk modal's footer Submit proxy button
        // (syncAddRiskModalFooter(), review-risk.js). window.RiskDetailsForm's
        // embedded .save-risk-form affordance is deliberately unlabeled (it's
        // never shown), so the footer button falls back to this key -- the
        // same one the legacy display_add_risk() markup rendered server-side
        // for this action.
        'SubmitRisk']
);

require_once(realpath(__DIR__ . '/../includes/display.php'));

// Insights band above the action queue -- five KPI tiles, each with a real
// 30-day trend sparkline (get_ui_widget_review_risk_insights(),
// api/v2/includes/api.php). Same UILayout machinery and same options as the
// four sibling bands (governance/index.php, compliance/index.php,
// governance/documentation.php, governance/document_exceptions.php).
//
// The permission re-check is the same belt-and-suspenders those pages use: the
// page as a whole is already gated by render_header_and_sidebar()'s
// ['check_riskmanagement' => true] above, and api_get_ui_widget() re-checks
// $ui_layout_config['review_risk_insights']['required_permission'] on every
// widget fetch -- but gating the render here means a user without the
// permission never even gets the empty band frame.
//
// Collapsible, like all four siblings: the band's ~120px competes with the
// queue below it for vertical space, and this page's own toolbar already eats
// a chunk of the fold.
if (check_permission('riskmanagement')) {
    require_once(realpath(__DIR__ . '/../includes/settings_catalog.php'));
    (new \includes\Widgets\UILayout('review_risk_insights', [
        'show_edit_layout' => true,
        'edit_layout_locked_state' => customization_acquisition_state(is_admin(), get_setting('registration_registered') == 1),
        'collapsible' => true,
    ]))->render();
}
?>
<!-- No .row/.col-12/.card-body wrapper: .sr-table-card renders as a DIRECT
     child of .content, matching every other direct-child-card surface
     (Manage/Initiate Audits, Define Exceptions, .sr-qform) -- see
     scss/modules/_tables.scss's `.content:has(> .sr-table-card)` rule,
     which zeroes .content's own 10px margin so the card's left edge lands
     flush with the page title/breadcrumb above it. The .row/.col-12/
     .card-body wrapper this page originally shipped with doesn't match
     that selector (.sr-table-card isn't a direct child through it), so
     none of that zero-out ever applied -- left over from before the design
     system's direct-child-card pattern was established for table grids. -->
<?php display_review_risk(); ?>
<?php
// Render the footer of the page. Please don't put code after this part.
render_footer();
