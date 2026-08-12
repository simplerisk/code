<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Declared directly rather than relied on transitively via api/v2/index.php's
// require order, so this file keeps working if that order ever changes.
// api_v2_check_permission() / api_v2_json_result() live in api.php;
// import_export_extra() in functions.php; the whole SoA domain — build_soa(),
// soa_framework_refusal() and the derivation rules — in includes/soa.php.
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/soa.php'));

/******************************************************************************
 * THE STATEMENT OF APPLICABILITY ENDPOINT (spec §5.4a)
 *
 *   GET /governance/soa?framework=<id>                ->  { cover, rows, can_export }
 *   GET /governance/soa/export?framework=<id>&format=…  ->  the same document, as a file
 *
 * `framework` is the only parameter the read takes, and `format` the only one the
 * export adds. There is ONE Statement of Applicability -- six columns plus
 * Appendix A and Appendix R -- so there is nothing about the document to select.
 *
 * ONE ENDPOINT, ONE DOCUMENT. The report page renders from the first, and the
 * export streams the same build_soa() result to a file. That is deliberate: the
 * document on screen and the document downloaded are the same computation, so
 * they cannot disagree about what the organisation's Statement of Applicability
 * says — which is the only property that makes handing the download to an
 * auditor defensible.
 *
 * WHY CLIENT-RENDERED, unlike the sibling Hub reports which are PHP-rendered
 * (reports/control_gap_analysis.php calls display_control_gap_analysis() and
 * echoes markup). Ruled 2026-07-25, and it follows the API-first rule: the view
 * and the export must share one row builder, and a PHP-rendered page would give
 * the screen its own render path with the export reading the data separately.
 *
 * PERMISSIONS. `governance` and nothing more — the same gate the controls page
 * and GET /governance/applicability already use, on BOTH routes. The export is
 * a READ of the same document, so it does not ask for `modify_frameworks`
 * (which is what WRITING an applicability decision requires); verified against
 * the endpoint rather than assumed, and `governance` is all-or-nothing here —
 * neither `frameworks` nor `framework_controls` carries a team, business-unit
 * or separation scope, so a user with `governance` sees every framework.
 *
 * THE REPORT IS CORE: no is_extra_installed() / *_extra() check gates reading
 * it. Only the EXPORT is Extra-gated (Import/Export), which is why `can_export`
 * is a flag on the payload rather than a condition on the whole response. The
 * export endpoint below enforces that check ITSELF rather than trusting the
 * flag: hiding a control is presentation, the endpoint is the gate.
 ******************************************************************************/

/****************************************************
 * FUNCTION: API V2 GOVERNANCE SOA                  *
 ****************************************************
 * GET /governance/soa?framework=<id>
 *
 * THREE REFUSALS, all delegated to soa_framework_refusal() (includes/soa.php)
 * rather than re-expressed here, so this endpoint and Task 18's export refuse
 * the same requests for the same reasons:
 *
 *   400 framework_required   — an SoA is a per-framework document. Applicability
 *                              is stored per (framework, control_id) and the
 *                              controls page withholds the column entirely under
 *                              "All frameworks"; there is no Applicable-in-N-of-M
 *                              roll-up, and inventing one for this report would
 *                              answer a question the data model does not.
 *   404 framework_not_found  — rather than an empty document, which would read as
 *                              a framework with no controls.
 *   409 framework_inactive   — a deactivated framework is not a live scope. See
 *                              soa_framework_refusal() for the full reasoning and
 *                              the note this leaves for SR-1988.
 *
 * The `error` token is machine-readable so the page localizes its own message;
 * the status_message text is composed only from fixed strings and never from
 * request input, so it is safe in the toast that renders it as HTML.
 */
function api_v2_governance_soa() {

    api_v2_check_permission("governance");

    $framework = (int)($_GET['framework'] ?? 0);

    // Refuses and exits when the framework is missing, unknown or inactive.
    api_v2_soa_refuse_framework_or_continue($framework);

    // ONE call, so the cover's counts and the rows beneath them are the same
    // array by construction. See build_soa(), includes/soa.php.
    $soa = build_soa($framework);

    api_v2_json_result(200, "SUCCESS", [
        'cover' => $soa['cover'],
        'rows'  => $soa['rows'],
        // ADVISORY ONLY. api_v2_governance_soa_export() re-checks this itself; a
        // client cannot obtain an export by ignoring the flag. It is here so the
        // page renders the button only where it would work, rather than offering
        // a download that answers 403.
        'can_export' => import_export_extra(),
    ]);
}

/****************************************************
 * FUNCTION: API V2 SOA REFUSE FRAMEWORK OR CONTINUE *
 ****************************************************
 * The three framework refusals, expressed ONCE for both routes.
 *
 * The read and the export must refuse the same requests for the same reasons —
 * an export that succeeded for a framework the report itself will not render
 * would be a document with no on-screen counterpart to check it against. Both
 * delegate the decision to soa_framework_refusal() (includes/soa.php) and both
 * emit their message from this function.
 *
 * Emits the JSON refusal and EXITS (json_response() exits) when the framework is
 * not usable; returns normally when it is.
 */
function api_v2_soa_refuse_framework_or_continue(int $framework): void {

    $refusal = soa_framework_refusal($framework);

    if ($refusal['allowed']) {
        return;
    }

    $messages = [
        'framework_required'  => "BAD REQUEST: A framework is required. A Statement of Applicability is a per-framework document.",
        'framework_not_found' => "NOT FOUND: That framework does not exist.",
        'framework_inactive'  => "CONFLICT: That framework is inactive. A Statement of Applicability states the scope an organization currently operates under.",
    ];

    api_v2_json_result(
        $refusal['status'],
        $messages[$refusal['error']] ?? "BAD REQUEST: A Statement of Applicability cannot be generated for that framework.",
        ['error' => $refusal['error'], 'framework' => $framework]
    );
}

/****************************************************
 * FUNCTION: API V2 GOVERNANCE SOA EXPORT           *
 ****************************************************
 * GET /governance/soa/export?framework=<id>&format=xlsx|pdf
 *
 * The same document GET /governance/soa returns, as a file. Streams a binary
 * body and exits; it does not answer with JSON on success.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE EXTRA CHECK IS HERE, NOT ONLY ON THE BUTTON.
 * ─────────────────────────────────────────────────────────────────────────────
 * The launcher renders no export affordance at all when the Import/Export Extra
 * is off — not a hidden one; the markup is never emitted (contrast
 * includes/reporting.php:4006, which hides its download buttons with
 * `display: none` and leaves them in the DOM). But that is PRESENTATION. This
 * endpoint refuses independently, so a user who knows the URL, or whose page was
 * rendered before the Extra was deactivated, gets a 403 rather than a document.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BOTH HALVES OF THE CORE→EXTRA CALL ARE REQUIRED.
 * ─────────────────────────────────────────────────────────────────────────────
 * `import_export_extra()` says whether the customer HAS the capability;
 * `require_once` is what puts export_soa_xlsx() / export_soa_pdf() in scope.
 * An Extra's index.php is only loaded when the matching gate passes, so without
 * the require the call is a fatal "Call to undefined function" — and Phan will
 * not catch it, because it resolves the definition regardless of load order.
 * `is_dir()` first, because a bundle can legitimately ship without the Extra's
 * directory at all (the same shape includes/api.php:3840 uses).
 *
 * ORDER OF CHECKS. Permission, then capability, then input (`format`), then the
 * framework.
 * The capability gate is about the instance rather than about the resource, so
 * it does not need to know which framework was asked for; putting it first also
 * means a caller without the Extra learns nothing about the framework catalogue
 * from the shape of the refusal.
 */
function api_v2_governance_soa_export() {

    // A READ of the document, so `governance` — not `modify_frameworks`, which
    // is what SETTING an applicability decision requires.
    api_v2_check_permission("governance");

    $extra_index = realpath(__DIR__ . '/../../../extras/import-export/index.php');

    if (!is_dir(realpath(__DIR__ . '/../../../extras/import-export')) || $extra_index === false || !import_export_extra()) {

        api_v2_json_result(
            403,
            "FORBIDDEN: The Import/Export Extra is required to export a Statement of Applicability.",
            ['error' => 'import_export_extra_required']
        );

        return;
    }

    $format = strtolower(trim((string)($_GET['format'] ?? 'xlsx')));

    // An allow-list, not a default-to-xlsx fallback: silently answering an
    // unrecognised `format` with a spreadsheet would hand somebody who asked for
    // a PDF a file their reader cannot open, with no indication why.
    if (!in_array($format, ['xlsx', 'pdf'], true)) {

        // The rejected value is NOT echoed back. It is arbitrary request input,
        // and the only thing reflecting it would buy is a sink for whichever
        // future client renders a status_message or a data field as HTML — the
        // toast helper does exactly that. The allow-list is in the message.
        api_v2_json_result(
            400,
            "BAD REQUEST: format must be one of: xlsx, pdf.",
            ['error' => 'unsupported_format']
        );

        return;
    }

    $framework = (int)($_GET['framework'] ?? 0);

    // Identical refusals to the read. See the shared helper above.
    api_v2_soa_refuse_framework_or_continue($framework);

    require_once($extra_index);

    // The Extra's directory exists and its setting is on, but the two functions
    // are not defined — a partially-deployed Extra. Refusing here beats a fatal.
    if (!function_exists('export_soa_xlsx') || !function_exists('export_soa_pdf')) {

        write_debug_log(
            "[APIv2] SoA export: the Import/Export Extra is enabled but does not define export_soa_xlsx()/export_soa_pdf(). The Extra is likely out of date.",
            "error"
        );

        api_v2_json_result(
            500,
            "INTERNAL SERVER ERROR: The installed Import/Export Extra does not support exporting a Statement of Applicability.",
            ['error' => 'import_export_extra_outdated']
        );

        return;
    }

    // THE SAME ONE CALL the read makes. Not a second query shaped to match it:
    // the file and the screen are the same computation, which is the only
    // property that makes handing the download to an auditor defensible.
    $soa = build_soa($framework);

    // ─────────────────────────────────────────────────────────────────────────
    // THE PDF HAS A SIZE CEILING, AND IT IS ENFORCED HERE AS WELL AS ON THE
    // BUTTON.
    // ─────────────────────────────────────────────────────────────────────────
    // Dompdf's peak grows with the row count and blows past a 256M
    // `memory_limit` somewhere around 858 controls; SOA_EXPORT_PDF_MAX_CONTROLS
    // (includes/soa.php) carries the measurement and the derivation. Above that
    // size the launcher's ONE "PDF" affordance points at the browser print route
    // instead of here, so nothing this build draws sends a request that reaches
    // this refusal — but that is presentation. A bookmarked URL, a page rendered
    // before a framework grew, or a script gets an explained refusal here rather
    // than a fatal on a host that cannot raise the limit mid-request.
    //
    // THE SAME RULE THE BUTTON USED, NOT A PARALLEL ONE. soa_pdf_routes() is the
    // single definition of which PDF mechanism a given document gets; asking it
    // here means the 409 and the affordance cannot disagree about one document.
    // `import_export_extra()` was already proved true by the 403 gate at the top
    // of this function and is memoised in $GLOBALS, so re-reading it costs
    // nothing and keeps the call honest rather than passing a hardcoded `true`.
    //
    // COUNTED OFF THE DOCUMENT THAT WAS JUST BUILT, not from a second query.
    // build_soa() is ~36 MB at SCF size — a rounding error against the 412 MB
    // the renderer would then cost — and it is the ONE number the cover, the
    // launcher's chip and this refusal all have to agree on. A separate COUNT(*)
    // shaped to match it is exactly the second computation this document's whole
    // design removes.
    //
    // ONLY THE PDF. The spreadsheet writer streams and is unaffected, so an
    // oversized framework still exports — and the browser print route renders
    // the same document client-side with no server ceiling at all.
    $pdf_routes = soa_pdf_routes(import_export_extra(), count((array)($soa['rows'] ?? [])));

    if ($format === 'pdf' && !in_array(SOA_PDF_ROUTE_DOWNLOAD, $pdf_routes, true)) {

        api_v2_json_result(
            409,
            "CONFLICT: This framework has too many controls for the server-generated PDF. Export it as a spreadsheet, or open the report and print it to PDF from the browser.",
            [
                'error'     => 'soa_pdf_control_limit_exceeded',
                'controls'  => count((array)($soa['rows'] ?? [])),
                'max'       => SOA_EXPORT_PDF_MAX_CONTROLS,
                'framework' => $framework,
            ]
        );

        return;
    }

    // Both stream and exit.
    if ($format === 'pdf') {
        export_soa_pdf($soa);
    }

    export_soa_xlsx($soa);
}

?>
