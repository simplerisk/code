<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
// includes/permissions.php requires includes/alerts.php before it requires
// functions.php itself, and alerts.php calls language_file() (defined in
// functions.php) unconditionally at require-time -- so functions.php must
// be loaded first, matching management/reopen.php's own require order.
require_once(realpath(__DIR__ . '/../includes/functions.php'));
// check_permission()/enforce_permission() below are reachable transitively
// (render_header_and_sidebar() -> sidebar.php -> header.php -> head.php ->
// includes/authenticate.php -> includes/permissions.php), but every direct
// consumer declares its own require_once per the CLAUDE.md function-
// reachability rule.
require_once(realpath(__DIR__ . '/../includes/permissions.php'));

// render_header_and_sidebar() -> sidebar.php -> header.php -> head.php stream
// real HTML as they execute (none of those three files buffer their own
// output -- confirmed by inspection, no ob_start() anywhere in that chain).
// A submit_risks check performed AFTER render_header_and_sidebar() returns
// therefore runs after the whole page shell has already been emitted, and a
// shell this large (full topbar + sidebar navigation) will typically already
// have exceeded PHP's ambient output_buffering threshold and started
// streaming to the client well before that point -- at which point a denied
// enforce_permission()'s header("Location: ...") redirect silently fails
// ("headers already sent"), stranding an unauthorized user on a
// half-rendered shell instead of being redirected.
//
// ob_start() here makes that failure impossible regardless of php.ini's
// output_buffering setting: it forces the ENTIRE shell into a PHP-level
// buffer -- nothing reaches the client -- until it is explicitly released
// below, by which point the fine-grained submit_risks check has already run.
ob_start();

// Standard page-head for a section-landing page: breadcrumb "Risk Management >
// Submit Your Risks", the left-nav Submit Your Risks leaf highlighted, and the
// H4 page title. $active_sidebar_submenu and $breadcrumb_title_key are BOTH
// 'SubmitYourRisks' on purpose -- the submenu IS the current page, so
// sidebar.php renders the submenu as the breadcrumb leaf and (because title
// === submenu) skips the redundant final crumb rather than duplicating it.
// Mirrors compliance/index.php's identical Define Tests pattern. Was missing
// entirely here (all three args previously omitted), so this page rendered no
// breadcrumb trail at all -- just a JS-derived page title with no "Risk
// Management >" context above it.
$breadcrumb_title_key = "SubmitYourRisks";
$active_sidebar_menu = "RiskManagement";
$active_sidebar_submenu = "SubmitYourRisks";
// CUSTOM:common/classic-scoring.js MUST precede CUSTOM:common/
// risk-details-form.js for the same reason as CVSS/DREAD/OWASP's own
// scoring scripts: the RiskScoringMethod widget's Classic holder (Risk
// Scoring -- Classic Inline) calls window.ClassicScoring.calculateClassic()
// at BUILD time, the moment the holder is constructed.
// CUSTOM:common/contributing-risk-scoring.js MUST precede CUSTOM:common/
// risk-details-form.js for the same reason as Classic/CVSS/DREAD/OWASP's
// own scoring scripts: the RiskScoringMethod widget's Contributing Risk
// holder calls window.ContributingRiskScoring.calculateContributingRisk()
// at BUILD time, the moment the holder is constructed.
// CUSTOM:pages/risk.js: submit-risk.js has no Owner -> Owner's Manager
// auto-populate logic of its own. That behavior lives in risk.js as a
// body-delegated change handler on [name=owner] (GET /api/v2/user/manager,
// then selectize.setValue() on [name=manager]) -- purely name-attribute-
// and event-delegation-based, so it already works against risk-details-
// form.js's rendered Owner/Owner's Manager selects with no page-specific
// wiring, exactly as it already does on view.php/review_risk.php/
// compliance's testing.php/view_test.php (all four also load it alongside
// risk-details-form.js for the same reason). Every other handler in the
// file is likewise delegated to elements this page's DOM doesn't have, so
// loading the whole file here is inert except for this one behavior.
render_header_and_sidebar(['blockUI', 'tabs:logic', 'selectize', 'datatables', 'chart.js', 'WYSIWYG', 'multiselect', 'gridstack', 'CUSTOM:common.js', 'CUSTOM:common/cvss-v2-scoring.js', 'CUSTOM:common/dread-scoring.js', 'CUSTOM:common/owasp-scoring.js', 'CUSTOM:common/classic-scoring.js', 'CUSTOM:common/contributing-risk-scoring.js', 'CUSTOM:common/risk-details-form.js', 'CUSTOM:pages/submit-risk.js', 'CUSTOM:pages/risk.js', 'CUSTOM:cve_lookup.js', 'datetimerangepicker', 'JSLocalization', 'EXTRA:JS:artificial_intelligence:ai-chat.js'], ['check_riskmanagement' => true, 'show_ai_chat' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu, required_localization_keys: ['MitigationPlanned']);

// Submitting a new risk requires the fine-grained submit_risks permission, which
// is distinct from the module-level riskmanagement access already enforced above
// via check_riskmanagement -- a user can hold riskmanagement access (e.g. to
// review/mitigate risks) without holding submit_risks. Same split
// management/reopen.php enforces for modify_risks after its own check_riskmanagement
// gate. enforce_permission() redirects an authenticated-but-unauthorized browser
// visit to the login page (and answers an XHR/API caller with a 403 JSON envelope)
// -- there is no dedicated "check_submit_risks" case in add_session_check()'s
// switch, so this fine-grained permission is enforced directly here rather than
// via the $permissions array above.
if (!check_permission("submit_risks")) {
    // Discard the buffered shell before redirecting -- an unauthorized user
    // should never receive any of it, and a 302 with a multi-KB HTML body is
    // sloppy regardless. Matches redirect_permission_denied()'s own
    // drain_output_buffers() precedent (includes/services.php) for the same
    // class of problem.
    ob_end_clean();
}
// Performs the actual redirect (or, for an XHR/API caller, the 403 JSON
// envelope) and exits when the permission is missing; a no-op when it's
// present. The check_permission() call above is what decides whether the
// buffer gets discarded -- this call's own internal re-check is redundant
// but harmless, and keeps the actual enforcement logic centralized in
// includes/permissions.php rather than duplicated here.
enforce_permission("submit_risks");

// Permission confirmed: release the buffered shell to the client and
// continue rendering normally.
ob_end_flush();

// .sr-qform on #submit-risk-container is REQUIRED, not decorative:
// _questionnaire.scss scopes every .sr-qcard/.sr-qcard-head/.sr-qcard-body/
// .sr-qactions rule (which submit-risk.js's rendered cards and sticky action
// bar depend on) under a `.sr-qform { ... }` parent selector -- without this
// class every card renders with no background/border/shadow/header styling.
// This div is also the DIRECT child of .col-12 that design-system.md #5's
// `.content > .row > .col-12 > .sr-qform` structure requires -- the :has()
// rule in _questionnaire.scss that turns this page's .content background
// transparent only matches that exact ancestor chain. The `.content` in that
// chain is sidebar.php's OWN `<div class="content container-fluid">`, which
// already wraps everything a sidebar-rendered page echoes -- this page must
// NOT open a second one. Every other `class="content"` in the codebase
// (includes/install.php, includes/healthcheck.php, admin/upgrade.php,
// management/print_view.php, assessments/questionnaire_result_share.php)
// belongs to a STANDALONE shell that never renders inside sidebar.php; the
// shape this page follows is assessments/questionnaires.php's, which echoes a
// bare `.row > .col-12 > .sr-qform` straight into sidebar.php's container.
//
?>
<div class="row">
    <div class="col-12">
        <div id="submit-risk-container" class="sr-qform" data-can-submit-risk="<?= !empty($_SESSION['submit_risks']) ? '1' : '0' ?>"></div>
    </div>
</div>
<?php
// "Confirm" type per design-system.md #8 (reversible-but-worth-a-beat: leave
// an unsaved form) -- shown by risk-details-form.js's Reset Form button ONLY
// when the form has entered data; an already-blank form clears silently
// without opening this. #reset-risk-form-discard is a plain JS hook, not a
// form submit -- risk-details-form.js wires its own click handler and closes
// the modal itself once the reset completes.
?>
<div class="modal fade sr-modal" id="reset-risk-form-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-rotate-left"></i></span>
                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['ResetFormConfirmTitle']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo $escaper->escapeHtml($lang['Close']); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo $escaper->escapeHtml($lang['ResetFormConfirmBody']); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-submit" id="reset-risk-form-discard"><?php echo $escaper->escapeHtml($lang['Discard']); ?></button>
            </div>
        </div>
    </div>
</div>
<?php
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>
