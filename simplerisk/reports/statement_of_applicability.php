<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    /**************************************************************************
     * THE STATEMENT OF APPLICABILITY (spec §5.4a; ISO/IEC 27001:2022 §6.1.3(d))
     *
     * ONE URL, TWO STATES, decided on the SERVER by whether a framework is
     * named:
     *
     *   no ?framework=   ->  the LAUNCHER. An ordinary app screen with the full
     *                        shell: pick a framework, then open or (Task 18)
     *                        export it.
     *   ?framework=<id>  ->  the DOCUMENT. A chrome-free artefact: no sidebar,
     *                        no topbar, no breadcrumb.
     *
     * WHY THE DOCUMENT HAS NO CHROME. An SoA is the thing an auditor is handed.
     * It is printed, saved, and mailed, and app furniture around it is noise in
     * every one of those uses -- it is an artefact, not a screen.
     *
     * DEEP LINKS GET THE ARTEFACT TOO. Someone arriving from a bookmark, or from
     * a link sent to an auditor, gets exactly what the launcher's "Open in
     * browser" produces. That is why the branch is on the URL and not on how the
     * page was reached: the document must not depend on the route taken to it.
     *
     * THIS IS THE SECOND OF 47 PAGES IN reports/ TO SKIP THE APP SHELL. The
     * other is print_by_group.php, and this file follows its established shape
     * rather than inventing one: the gate is performed explicitly, in the same
     * order, before any output.
     *
     * ==== THE GATE, WHICH IS THE PART THAT MATTERS ==========================
     *
     * render_header_and_sidebar() is NOT just chrome -- via head.php it carries
     * add_security_headers(), add_session_check($permissions) (which enforces
     * `access` and then the named permission), include_csrf_magic(), the
     * language file and $escaper. Dropping the shell would drop all of that, and
     * the page's own $_SESSION['governance'] redirect is NOT a substitute: it
     * runs after output would have started, checks one flag rather than the
     * session's validity, and does nothing about security headers or CSRF.
     *
     * So the document branch performs the SAME calls head.php performs, in the
     * same order, before it emits a byte -- add_session_check() with the same
     * ['check_governance' => true] the shelled branch passes, plus an explicit
     * enforce_permission('governance') for belt and braces, exactly as
     * print_by_group.php does for `riskmanagement`. Verified: an authenticated
     * session without `governance` is redirected out and renders nothing, and an
     * unauthenticated request never reaches the markup.
     **************************************************************************/

    // Which state. Read before anything else, because it decides which
    // bootstrap runs -- and int-cast so a non-numeric ?framework= is simply "no
    // framework" (the launcher) rather than something the branch has to handle.
    $sr_soa_framework = (int)($_GET['framework'] ?? 0);

    /**************************************************************************
     * `?preselect=` — THE LAUNCHER, ARMED (Task 8).
     *
     * Deliberately a SECOND parameter rather than a mode flag on `?framework=`:
     * that one means "render the document", and it has to keep meaning exactly
     * that for a bookmark, for a link mailed to an auditor, and for the
     * launcher's own "Open in browser". So the two Generate buttons on Define
     * Control Frameworks send `?preselect=<id>` instead, which lands here in the
     * LAUNCHER state with the framework already chosen — where the two downloads
     * are offered beside the browser view, which the old direct-to-document route
     * could not do.
     *
     * INT-CAST, AND THAT IS THE WHOLE VALIDATION ON THIS SIDE. The value is
     * stamped into a data attribute that the page script reads and composes API
     * URLs from — the same shape as the reflected `?importoption=` an earlier
     * task on this branch fixed — so what is emitted below is an integer this
     * line produced, never request text. A garbage or negative value becomes 0
     * and is not stamped at all.
     *
     * WHETHER THE ID IS A REAL FRAMEWORK IS NOT DECIDED HERE. The picker is
     * populated from GET /governance/frameworks/rail?status=1, which is the
     * authority on what may be chosen, and the client honours the preselection
     * only when it matches an option that endpoint actually returned. So an id
     * for a deleted, inactive, or never-existing framework arms nothing and
     * leaves an ordinary picker — rather than a launcher pointing at a document
     * the API would refuse.
     *************************************************************************/
    $sr_soa_preselect = (int)($_GET['preselect'] ?? 0);

    // The same permission set for both states. Named once so the two branches
    // provably gate on the same thing.
    $permissions = ['check_governance' => true];

    if ($sr_soa_framework <= 0) {

        /**********************************************************************
         * THE LAUNCHER — an ordinary app screen, full shell.
         *********************************************************************/

        require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
        // import_export_extra() lives here. The shell's own chain loads
        // functions.php anyway, but the call below is a DIRECT consumer and
        // says so, exactly as the document branch does (CLAUDE.md,
        // cross-file reachability). require_once makes the duplicate a no-op.
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        // SOA_EXPORT_PDF_MAX_CONTROLS, stamped into the container below. Same
        // direct-consumer rule: the constant's file is required here rather than
        // relied on transitively.
        require_once(realpath(__DIR__ . '/../includes/soa.php'));
        // framework_acquisition_path_states(), which resolves the locked state of
        // the Import-Export-backed export buttons. soa.php happens to pull this in
        // transitively today, but the direct-consumer rule is the point: an
        // include reorder upstream would otherwise turn the call below into a
        // fatal on a page a customer reaches from the reports menu.
        require_once(realpath(__DIR__ . '/../includes/governance.php'));

        render_header_and_sidebar(
            ['CUSTOM:pages/statement-of-applicability.js', 'CUSTOM:common.js'],
            $permissions,
            active_sidebar_submenu: 'Reporting_Reports',
            active_sidebar_menu: 'Reporting',
            breadcrumb_title_key: 'StatementOfApplicability'
        );

        // Belt-and-suspenders on top of add_session_check()'s own gate, the same
        // pairing every sibling report in this directory uses.
        if (empty($_SESSION['governance'])) {

            header("Location: ../index.php");
            exit(0);
        }
?>
<!-- NO `bg-white` on the wrapper, unlike the 36 legacy reports in this
     directory. That class is the pre-redesign pattern: a white slab holding
     un-carded content, and it paints over `.page-wrapper`'s canvas grey
     (#E8E9EB / $sr-default, measured) that every redesigned page on this branch
     sits on, governance/index.php included. The launcher is an ordinary app
     screen and belongs on that canvas; the stylesheet opts `.content` out of its
     white slab via the same `:has()` mechanism _questionnaire.scss and
     _ai_catalog.scss already use. -->
<div class="row">
  <div class="col-12">

    <!-- Filled in by the client. Empty `data-sr-soa-framework` is what tells it
         this is the LAUNCHER -- the mode is the server's decision, echoed into
         the DOM, rather than something the script re-derives from the URL.

         `data-sr-soa-can-export` carries the SERVER'S answer to "is the
         Import/Export Extra active", which is what decides whether the two
         download affordances are LIVE. It is not a styling hook and there is no
         CSS rule keyed on it. The attribute is also not the gate --
         GET /api/v2/governance/soa/export enforces the same check itself.

         WHEN IT IS EMPTY THE BUTTONS ARE STILL BUILT, LOCKED. They used to be
         omitted from the DOM entirely. That is the wrong default for a paid
         Extra: a customer who cannot see that SimpleRisk exports a Statement of
         Applicability at all has no reason to ask for the Extra that does it,
         so hiding the affordance costs the sale it was protecting. The rule
         across the product is now "show what is possible, and mark what is out
         of reach because it is not licensed" -- the .sr-locked* component in
         scss/modules/_locked-affordance.scss, whose block comment names these
         two buttons as a consumer.

         `data-sr-soa-unlock-*` carry that lock's REASON and its single next
         step, resolved server-side by framework_acquisition_path_states()
         (includes/governance.php) -- the SAME decision the "+ Add framework"
         chooser's Import-Export row asks, which is what makes the two surfaces
         say the same sentence about the same Extra in the same state. They are
         resolved TEXT rather than lang keys because this page's script has no
         localization map of its own; escapeHtmlAttr is doing real work here,
         unlike the two integer attributes below.

         Read `framework_acquisition_path_states()` directly rather than its
         request-resolved wrapper framework_acquisition_paths(): the wrapper
         gates every route on `add_new_frameworks`, which is the permission to
         ADD a framework and has nothing to do with EXPORTING a report the user
         is already permitted to read. Passing $can_add = true asks it only the
         Extra-state question, which is the one this surface has.

         `data-sr-soa-pdf-max` carries SOA_EXPORT_PDF_MAX_CONTROLS, the framework
         size above which the SERVER-SIDE PDF is not offered at all
         (includes/soa.php holds the number and the measurement behind it).
         Stamped rather than restated in the script, for the same reason
         `data-sr-soa-can-export` is: a second copy of the threshold in JS is a
         second place for it to drift from the endpoint that enforces it. It is
         an integer constant this file did not compose from request input, so
         there is nothing here for an escaper to do.

         `data-sr-soa-preselect` carries the framework the user already named by
         clicking Generate on its rail row (Task 8), so the picker opens with it
         chosen rather than re-asking. It is an INT this file cast, never request
         text -- see the note beside $sr_soa_preselect above -- and it is emitted
         as an integer literal, so there is nothing here for an escaper to do.
         Absent, not zero, when there is nothing to preselect: the client tests
         for a positive id, and an empty attribute is the same "no framework"
         answer `data-sr-soa-framework` gives one line up. -->
<?php
    // Resolved once, here, so the tag below stays a tag. Only the 'import' route
    // is asked for -- the SCF and manual routes are the frameworks page's
    // business, not this report's.
    $sr_soa_unlock = framework_acquisition_path_states(
        true,                                   // see the note above: NOT add_new_frameworks
        is_admin(),
        get_setting('registration_registered') == 1,
        false,                                  // scf_installed -- not this surface's route
        false,                                  // scf_activated
        is_extra_installed('import-export'),
        import_export_extra()
    )['import'] ?? null;

    // A note with no link is a real state, not a truncated one: a non-admin
    // resolves to 'admin_required', which explains the lock and deliberately
    // offers no destination, because both destinations are check_admin pages.
    $sr_soa_unlock_note = ($sr_soa_unlock && $sr_soa_unlock['note_key'] !== null)
        ? ($lang[$sr_soa_unlock['note_key']] ?? '')
        : '';
    $sr_soa_unlock_link = ($sr_soa_unlock && $sr_soa_unlock['link_key'] !== null)
        ? ($lang[$sr_soa_unlock['link_key']] ?? '')
        : '';
?>
    <div id="sr-soa" class="sr-soa" data-sr-soa-framework=""
         data-sr-soa-preselect="<?= $sr_soa_preselect > 0 ? $sr_soa_preselect : '' ?>"
         data-sr-soa-pdf-max="<?= (int)SOA_EXPORT_PDF_MAX_CONTROLS ?>"
         data-sr-soa-can-export="<?= import_export_extra() ? '1' : '' ?>"
         data-sr-soa-unlock-state="<?= $escaper->escapeHtmlAttr($sr_soa_unlock['state'] ?? '') ?>"
         data-sr-soa-unlock-note="<?= $escaper->escapeHtmlAttr($sr_soa_unlock_note) ?>"
         data-sr-soa-unlock-link="<?= $escaper->escapeHtmlAttr($sr_soa_unlock_link) ?>"
         data-sr-soa-unlock-href="<?= $escaper->escapeHtmlAttr($sr_soa_unlock['unlock_href'] ?? '') ?>"
         data-sr-soa-unlock-external="<?= !empty($sr_soa_unlock['external']) ? '1' : '' ?>"
         data-sr-soa-locked-badge="<?= $escaper->escapeHtmlAttr($lang['LockedAffordanceBadge']) ?>"></div>

  </div>
</div>
<?php
        // Render the footer of the page. Please don't put code after this part.
        render_footer();
        exit;
    }

    /**************************************************************************
     * THE DOCUMENT — a chrome-free artefact.
     *
     * Everything below mirrors print_by_group.php's bootstrap. Nothing is
     * emitted until the gate has run.
     *************************************************************************/

    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    // SOA_EXPORT_PDF_MAX_CONTROLS, which this page stamps into the DOM for the
    // launcher to read. Required directly per CLAUDE.md's cross-file
    // reachability rule; the file is pure function and constant definitions with
    // no top-level side effects.
    require_once(realpath(__DIR__ . '/../includes/soa.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

    global $escaper, $lang, $current_app_version;

    $escaper = new simpleriskEscaper();

    // Same security headers every shelled page gets.
    add_security_headers();

    // THE GATE. Enforces `access` first and then `governance`, identically to
    // the shelled branch above — the argument is the same $permissions array.
    add_session_check($permissions);

    // Belt and braces, matching print_by_group.php's own second check.
    enforce_permission("governance");

    include_csrf_magic();

    require_once(language_file());

    $current_app_version = current_version("app");

    /**************************************************************************
     * EVERY ATTRIBUTE THIS PAGE STAMPS IS A VALUE THIS FILE PRODUCED.
     *
     * An int cast, a strict comparison against a literal, or a constant -- never
     * request text, because the page script reads these attributes and builds API
     * URLs from them. An earlier task on this branch fixed a reflected
     * `?importoption=` that reached an inline <script> through the HTML escaper.
     * $sr_soa_preselect and $sr_soa_autoprint below are both that shape.
     *************************************************************************/

    /**************************************************************************
     * `?print=1` — THE BROWSER PRINT ROUTE.
     *
     * The launcher's "Print to PDF" affordance opens THIS URL — the same
     * document "Open in browser" opens, plus this flag — and the page script
     * raises window.print() once the document has actually rendered. The flag
     * changes NOTHING about the document itself: same query, same payload, same
     * markup. A print route that produced a different artefact from the one the
     * reader can check on screen would be a second reading of the statement.
     *
     * WHY IT EXISTS AT ALL. The server-side PDF is Dompdf, whose peak memory
     * grows with the control count and exceeds a 256M `memory_limit` somewhere
     * around 858 controls (SOA_EXPORT_PDF_MAX_CONTROLS, includes/soa.php). The
     * browser has no such ceiling: it renders the document it is already
     * rendering. So above the threshold this is the PDF route, and below it it
     * is a second one.
     *
     * A STRICT COMPARISON AGAINST '1', so the attribute below is one of two
     * literals this line produced and never request text — the same rule
     * $sr_soa_preselect follows, and the same reflected-input class of bug an
     * earlier task on this branch fixed.
     *************************************************************************/
    $sr_soa_autoprint = (($_GET['print'] ?? '') === '1');

    // The keys THIS state's script needs. The launcher's subset is registered in
    // header.php's $localization_required_by_scripts map, which only pages that
    // load header.php can use — this branch does not, so it builds the same
    // _lang object with the same helpers instead. The two lists are genuinely
    // different (the launcher has no table and the document has no picker), so
    // this is a split rather than a duplicate.
    $sr_soa_lang_keys = [
        'StatementOfApplicability', 'SoaGeneratedOn', 'Controls',
        'ApplicabilityApplicable', 'ApplicabilityNotApplicable', 'ApplicabilityInherited',
        // 'DefaultInclusionJustification' is deliberately absent: the cover no
        // longer prints that statement (renderCover(), pages/statement-of-
        // applicability.js), because clause 6.1.3(d) wants the justification per
        // control and the Justification column already carries this exact
        // sentence on every applicable row. 'SoaMissingInclusionJustification'
        // below is still needed -- the report still PROMPTS for the field.
        'SoaExcludedCount', 'IsmsScopeStatement',
        // THE SIX COLUMN HEADINGS -- which are ALSO the legend's four group
        // headings, three of them here and 'SoaAppendixRemediation' below. That
        // reuse is the point of grouping the legend by column: the reader matches
        // two identical words.
        'Reference', 'ControlName', 'Applicability', 'Justification', 'SoaImplemented', 'Evidence',
        // 'SoaVerification' IS NOT LISTED. It headed the merged Implemented +
        // Evidence + Review Cadence column, which is two columns again -- see
        // renderTable() for the three reasons. 'SoaReviewCadence',
        // 'SoaRiskRegister', 'ControlOwner' and 'SoaNotRecorded' left with the
        // three columns that are gone entirely.
        //
        // THE TWO APPENDICES. Justifications are truncated in the row and printed
        // in full after the register; remediation plans moved out of the table
        // entirely, because ISO 27001 keeps the SoA (6.1.3(d)) and the risk
        // treatment plan (6.1.3(e)) as separate artifacts.
        'SoaAppendixJustifications', 'SoaAppendixRemediation',
        // NOT LISTED, because no sink resolves them any more: the three
        // required-evidence keys ('SoaAppendixRequiredEvidence' and the two
        // supplied/not-supplied verdicts) and 'SoaNextDue'. They stay in
        // lang.en.php for the other locales.
        //
        // The evidence bullets' remaining labels: the four singular document-type
        // labels. 'Pass', 'Fail' and 'Inconclusive' are read by testResultGlyph()
        // and resultIsNamedByTheLegend() -- both match the RESOLVED label rather
        // than a raw token, so all three must be present or a result would get the
        // wrong bullet or have its word suppressed by the wrong rule. They are ALSO
        // three of the legend's four glyph terms.
        //
        // 'SoaTested' IS NO LONGER HERE. It prefixed the last-run date -- "Tested
        // 2026-04-01" -- and the label spent width saying what the position already
        // says: in a column headed Evidence, beside a test's name, the only date
        // there is to state is when it ran. The bare date also matches what both
        // exports have always printed.
        //
        // 'SoaNextDue' is NOT here either: the next-due date left the document. An
        // SoA records the control's current state (clause 6.1.3(d)), and when a test
        // will next run is programme administration -- its one relevant use,
        // deciding whether the displayed result is stale, is already reported by
        // the overdue marker derived from it.
        'SoaEvidencePolicy', 'SoaEvidenceStandard', 'SoaEvidenceProcedure', 'SoaEvidenceGuideline',
        // The four legend glyph terms.
        'Pass', 'Fail', 'Inconclusive', 'SoaEvidenceDesignDocument',
        'Yes', 'No', 'SoaImplementedPartial', 'NotApplicable',
        // The two unverified states and the not-a-verdict fallback. Every token
        // implementedLabel() can return must be listed here: L() falls back to
        // the KEY NAME, so a missing entry prints 'SoaImplementedNeverRun' into
        // the Implementation Status column of a document handed to an auditor.
        'SoaImplementedNoTestDefined', 'SoaImplementedNeverRun', 'SoaImplementedUnknown',
        'Reason', 'Provider', 'ApplicabilityDecidedBy',
        // TASK 6'S THREE MARKERS. The overdue caveat that composes with a
        // verdict, and the Evidence column's two absences -- which are
        // opposites, so both are words the reader can look up rather than an
        // empty cell.
        'Overdue', 'SoaNoEvidenceLinked', 'SoaEvidenceNotExpected',
        // Appendix R's entries, for a control reading Partial or No.
        'SoaRemediation', 'SoaRemediationUnplanned',
        'Risk', 'MitigationPlanning', 'MitigationOwner', 'PercentComplete',
        // THE LEGEND -- its heading and the definition half of every entry in
        // SOA_LEGEND_GROUPS (includes/soa.php). Every TERM and every GROUP HEADING
        // is already listed above, because a legend term is by construction
        // something the table itself prints and a group heading IS a column
        // heading. Listed rather than derived for the same reason
        // soa_export_labels() lists them, and SoaLegendAndMarkersTest asserts
        // both lists cover the constant.
        'SoaLegendTitle',
        'SoaLegendApplicable', 'SoaLegendApplicabilityNotApplicable', 'SoaLegendInherited',
        'SoaLegendYes', 'SoaLegendPartial', 'SoaLegendNo',
        'SoaLegendNoTestDefined', 'SoaLegendNeverRun', 'SoaLegendNotApplicable',
        'SoaLegendUnknown', 'SoaLegendOverdue', 'SoaLegendNoEvidence',
        'SoaLegendEvidenceNotExpected', 'SoaLegendUnplanned',
        'SoaLegendEvidencePass', 'SoaLegendEvidenceFail', 'SoaLegendEvidenceInconclusive',
        'SoaLegendEvidenceDocument',
        'SoaMissingFieldsTitle', 'SoaMissingScopeStatement', 'SoaMissingInclusionJustification',
        'SoaEditFrameworkToAdd', 'SoaFrameworkInactiveTitle', 'SoaFrameworkInactiveBody',
        'SoaFrameworkNotFoundBody', 'SoaNoControls', 'SoaNoControlsHint',
        'SoaBackToSelector', 'RequestFailed',
    ];

    /**************************************************************************
     * THE PRINTED PAGE ITSELF — @page, AND WHY IT IS EMITTED HERE RATHER THAN
     * IN THE STYLESHEET.
     *
     * `@page` IS DOCUMENT-GLOBAL. It is not scoped by a selector and there is no
     * way to scope it to one page. Putting `@page { size: letter landscape }`
     * into scss/modules/_statement-of-applicability.scss would put it into
     * css/style.min.css, which EVERY page in the application loads — and every
     * printed page in SimpleRisk would come out landscape. That single fact is
     * why this one at-rule lives in the document that wants it and the rest of
     * the print rules stay in the partial.
     *
     * WHAT IT FIXES. There was no `@page` rule anywhere, so a real Ctrl+P on
     * this report produced Letter PORTRAIT and Chromium shrink-to-fit the wide
     * table into 612pt — measured at 26 pages of roughly 5pt type with the last
     * column still clipped. `size: letter landscape` gives 792×612, which is the
     * same paper Dompdf's setPaper('letter','landscape') has always used, so the
     * two routes produce the same shape of document.
     *
     * THE 12mm MARGIN IS NOT A TASTE CHOICE. Letter landscape is 279.4mm wide;
     * 12mm on each side leaves 255.4mm = 965px of printable width at 96dpi,
     * which is the width the per-column table widths in the partial's @media
     * print block were fitted against. Changing it moves the columns.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * PAGE NUMBERS ARE CHROMIUM-ONLY. DO NOT READ THIS AS UNIVERSAL SUPPORT.
     * ─────────────────────────────────────────────────────────────────────────
     * CSS `@page` margin boxes (`@bottom-center`) are implemented in Chromium
     * (Chrome 131+) and are SILENTLY IGNORED by Firefox and Safari — no error,
     * no fallback, just an unnumbered document. Verified on a 57-page render:
     * 57/57 pages numbered in Chromium. A customer printing from another browser
     * gets a document with no page numbers, which is what Dompdf's own output
     * does on every browser today. So this is strictly an improvement where it
     * works and a no-op where it does not; it is not a guarantee, and nothing in
     * the product may depend on the numbers being there.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE LABEL IS TRANSLATED, AND IT IS ONE STRING RATHER THAN TWO WORDS.
     * ─────────────────────────────────────────────────────────────────────────
     * CSS `content` cannot interpolate, so the obvious shape is two keys ("Page"
     * and "of") concatenated around the counters — which bakes English word
     * order into every locale. Instead ONE key carries the whole sentence with
     * `{page}` / `{pages}` placeholders, and it is split here into literal
     * segments and counter() calls in whatever order the translation puts them.
     *
     * Every literal segment goes through escapeCss(). Inside a CSS string a
     * `\hh ` escape is the character it encodes, so the rendered footer is the
     * translator's text exactly — and a language file carrying a stray quote or
     * brace cannot close the string and reach the stylesheet.
     *************************************************************************/
    $sr_soa_page_number_css = '';

    foreach (preg_split('/(\{page\}|\{pages\})/u',
                        (string)($lang['SoaPrintPageNumber'] ?? ''),
                        -1,
                        PREG_SPLIT_DELIM_CAPTURE) as $sr_soa_segment) {

        if ($sr_soa_segment === '{page}')  { $sr_soa_page_number_css .= ' counter(page)';  continue; }
        if ($sr_soa_segment === '{pages}') { $sr_soa_page_number_css .= ' counter(pages)'; continue; }
        if ($sr_soa_segment === '')        { continue; }

        $sr_soa_page_number_css .= ' "' . $escaper->escapeCss($sr_soa_segment) . '"';
    }
?>
<!doctype html>
<html lang="<?= $escaper->escapeHtml($_SESSION['lang'] ?? 'en') ?>" xml:lang="<?= $escaper->escapeHtml($_SESSION['lang'] ?? 'en') ?>">
    <head>
        <title><?= $escaper->escapeHtml($lang['StatementOfApplicability']) ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

        <?php setup_favicon(".."); ?>

        <link rel="stylesheet" href="../css/style.min.css?<?= $escaper->escapeHtmlAttr($current_app_version) ?>" />
        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $escaper->escapeHtmlAttr($current_app_version) ?>">
        <link rel="stylesheet" href="../vendor/components/font-awesome/css/solid.min.css?<?= $escaper->escapeHtmlAttr($current_app_version) ?>">

        <!-- See the @page note above: this at-rule is document-global, so it
             cannot live in the shared bundle without turning every printed page
             in the application landscape. Everything else about how this
             document prints IS in scss/modules/_statement-of-applicability.scss.
             The only value interpolated here is the page-number label, split
             from one translated string and escapeCss()'d segment by segment. -->
        <style type="text/css">
            @page {
                size: letter landscape;
                margin: 12mm;
<?php if ($sr_soa_page_number_css !== '') { ?>
                @bottom-center {
                    font-family: "Nunito Sans", sans-serif;
                    font-size: 8pt;
                    color: #6c757d;
                    content:<?= $sr_soa_page_number_css ?>;
                }
<?php } ?>
            }
        </style>

        <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $escaper->escapeHtmlAttr($current_app_version) ?>"></script>

        <script type="text/javascript">
            // The two globals the page script expects. BASE_URL is built exactly
            // as header.php builds it, so a sub-path install
            // (https://host/simplerisk/) resolves its API calls the same way here.
            var BASE_URL = '<?= $escaper->escapeHtml(rtrim(($_SESSION['base_url'] ?? get_setting("simplerisk_base_url")), '/')) ?>';
            var _lang = <?= encode_js_lang_subset(build_js_lang_subset($sr_soa_lang_keys, $lang)) ?>;
        </script>
    </head>
    <body class="sr-soa-standalone">

        <!-- The document identifies ITSELF, because there is no shell left to do
             it: organisation, framework and generation date are rendered onto
             the cover by the client from the endpoint's payload. -->
        <!-- `data-sr-soa-autoprint` is the print route's flag, and it is one of
             two literals this file produced from a strict `=== '1'` — never
             request text. The script raises window.print() from the END of
             renderDocument(), so the dialog opens on a document that is really
             there rather than on an empty container. -->
        <!-- A deep link and the launcher's "Open in browser" produce byte-identical
             documents by construction: `?framework=` is the only thing either of
             them says about the document. -->
        <div id="sr-soa" class="sr-soa sr-soa--document"
             data-sr-soa-framework="<?= $escaper->escapeHtmlAttr($sr_soa_framework) ?>"
             data-sr-soa-autoprint="<?= $sr_soa_autoprint ? '1' : '' ?>"></div>

        <script src="../js/simplerisk/pages/statement-of-applicability.js?<?= $escaper->escapeHtmlAttr($current_app_version) ?>"></script>
    </body>
</html>
