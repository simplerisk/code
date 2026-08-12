/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/******************************************************************************
 * THE STATEMENT OF APPLICABILITY (spec §5.4a; ISO/IEC 27001:2022 §6.1.3(d))
 *
 * Renders reports/statement_of_applicability.php from
 * GET /api/v2/governance/soa?framework=<id>.
 *
 * TWO RULES GOVERN EVERY LINE BELOW.
 *
 * 1. THIS FILE DERIVES NOTHING. Every number, state and label on screen comes
 *    from the payload as sent. There is no client-side counting, no re-deriving
 *    "applicable" from the absence of something, no recomputing a total from the
 *    rows. The server counted the rows it sent (soa_count_by_state(),
 *    includes/soa.php) precisely so the cover and the table cannot disagree, and
 *    a client that re-counted would reintroduce the second computation that
 *    whole design exists to remove -- and the XLSX/PDF export, which does not
 *    run this file, would then be a third.
 *
 * 2. EVERY VALUE IS TEXT, NEVER HTML. Control names, narratives, provider
 *    names, document names and the two cover statements are stored VERBATIM --
 *    unpurified and unescaped -- and returned raw by design (see the rendering
 *    contract in includes/soa.php). They are inserted with .text() / document
 *    .createTextNode() throughout. There is no .html() call in this file and
 *    there must not be one: a justification reading "<script>" is a legitimate
 *    thing for a customer to have typed, and it is text.
 ******************************************************************************/

(function () {
    'use strict';

    var $root;

    /** The document currently on screen, or null. Kept for the export button. */
    var soa = null;

    // -------------------------------------------------------------------------
    // Small builders
    // -------------------------------------------------------------------------

    function L(key) {
        return (window._lang && window._lang[key]) || key;
    }

    /**
     * The framework this page is FOR, or null for the launcher.
     *
     * Read from the container's `data-sr-soa-framework`, which the SERVER
     * stamped. The mode is the server's decision -- it is what chose between
     * the shelled launcher and the chrome-free document -- so the script reads
     * that decision rather than re-deriving it from the URL and risking the two
     * disagreeing.
     */
    function framework() {
        var value = $root.attr('data-sr-soa-framework');
        return value ? parseInt(value, 10) : null;
    }

    /**
     * The framework the launcher should open ALREADY CHOSEN, or 0 (Task 8).
     *
     * The SERVER'S answer again, read from `data-sr-soa-preselect`, which
     * reports/statement_of_applicability.php int-cast from `?preselect=`. Set
     * only when the user reached the launcher from a Generate button that
     * already knew which framework it meant -- the rail row's ⋯ action or the
     * scoped toolbar button -- and absent for the Reporting Hub's plain link,
     * which cannot supply one.
     *
     * NOT A GATE, and not trusted as an answer either: renderFrameworkPicker()
     * honours it only when it matches an option the frameworks endpoint
     * actually returned, so an id for a deleted or inactive framework leaves an
     * ordinary picker rather than arming the launcher for a document the API
     * would refuse.
     */
    function preselected() {
        var value = parseInt($root.attr('data-sr-soa-preselect'), 10);
        return value > 0 ? value : 0;
    }

    /** The canonical address of one framework's document. */
    function documentUrl(id) {
        return BASE_URL + '/reports/statement_of_applicability.php?framework=' + encodeURIComponent(id);
    }

    /** The canonical address of one framework's document as a downloadable file. */
    function exportUrl(id, format) {
        return BASE_URL + '/api/v2/governance/soa/export?framework=' + encodeURIComponent(id) +
               '&format=' + encodeURIComponent(format);
    }

    /**
     * The document, opened with the browser's own print dialog already up.
     *
     * THE SAME URL "Open" uses, plus one flag. It has to be the same
     * document -- a print route that rendered anything different from what the
     * reader can check on screen would be a second reading of the statement, and
     * this page has removed that class of disagreement everywhere else.
     */
    function printUrl(id) {
        return documentUrl(id) + '&print=1';
    }

    /**
     * Whether THIS page was asked to raise the print dialog on load.
     *
     * The SERVER'S answer again, read from the container attribute
     * reports/statement_of_applicability.php stamped after casting `?print=`.
     * Nothing here re-reads the URL: the same rule framework() and canExport()
     * follow.
     */
    function autoPrint() {
        return $root.attr('data-sr-soa-autoprint') === '1';
    }

    /**
     * The largest framework the SERVER-SIDE PDF is offered for, or null when this
     * page did not say.
     *
     * SOA_EXPORT_PDF_MAX_CONTROLS (includes/soa.php) carries the number and the
     * measurement it was derived from; the launcher page stamps it into
     * `data-sr-soa-pdf-max`. It is NOT restated here, because a second copy in the
     * client is a second place for it to drift from the endpoint that enforces it.
     *
     * NULL when the attribute is missing or unparseable -- an older page, or the
     * document state, which has no launcher on it. The affordance is then built as
     * before and GET /api/v2/governance/soa/export answers 409 if the framework is
     * in fact too large: this is which controls exist, never what is allowed.
     */
    function pdfMaxControls() {
        var value = parseInt($root.attr('data-sr-soa-pdf-max'), 10);
        return isNaN(value) ? null : value;
    }

    /**
     * Whether the server-side PDF is offered for a framework of this size.
     *
     * MIRRORS soa_pdf_export_supported() (includes/soa.php), which is what the
     * endpoint enforces and what PHPUnit tests. Same comparison, same boundary:
     * a framework EXACTLY at the threshold is supported.
     */
    function pdfSupported(controlCount) {
        var max = pdfMaxControls();
        return max === null || controlCount <= max;
    }

    /**
     * Whether this instance can export at all -- i.e. whether the Import/Export
     * Extra is active.
     *
     * THE SERVER'S ANSWER, read from the container attribute the launcher page
     * stamped (`import_export_extra()` at render time). Not re-derived here, and
     * deliberately not a styling flag: renderLaunchActions() consults it before
     * BUILDING the export affordances, so when the Extra is off they are absent
     * from the DOM rather than present and hidden. The endpoint enforces the same
     * check independently -- this is which controls exist, not what a user is
     * allowed to do.
     */
    function canExport() {
        return $root.attr('data-sr-soa-can-export') === '1';
    }

    /**
     * Whether the ONE "PDF" affordance for a framework of this size is the browser
     * print route rather than the server-side download.
     *
     * MIRRORS soa_pdf_routes() (includes/soa.php), which is the single definition
     * of which mechanism a given document gets and which the export endpoint's 409
     * is also written against. The Extra half of that decision is applied by the
     * caller -- nothing at all is built without it -- so what is left here is the
     * size half, and its two answers are the two SOA_PDF_ROUTE_* tokens spelled
     * exactly as PHP spells them.
     *
     * AN UNKNOWN COUNT IS NOT EVIDENCE THAT THE FRAMEWORK IS BIG. An option with
     * no `data-count` (an older rail payload) takes the download, and the endpoint
     * -- which is where the gate actually is -- answers 409 if it was in fact too
     * large. The alternative, defaulting to print, would silently move an ordinary
     * framework off the server-rendered artefact on the strength of a missing
     * attribute.
     */
    function pdfPrints(controlCount) {
        return !(isNaN(controlCount) || pdfSupported(controlCount));
    }

    /**
     * A whole-page message: an explained refusal, an empty framework, or a
     * loading failure. Reuses the .sr-table-empty vocabulary the Define Control
     * Frameworks page already uses for the same job, so a refusal here looks
     * like a refusal there.
     */
    function emptyState(icon, title, body, danger) {

        var $state = $('<div class="sr-table-empty">').toggleClass('sr-table-empty-danger', !!danger);
        $state.append($('<div class="sr-table-empty-icon">')
            .append($('<i aria-hidden="true">').addClass('fa ' + icon)));
        $state.append($('<div class="sr-table-empty-title">').text(title));
        if (body) {
            $state.append($('<div class="sr-table-empty-body">').text(body));
        }

        // WRAPPED IN A CARD deliberately: _tables.scss scopes the whole
        // .sr-table-empty block inside .sr-table-card, so an unwrapped one is
        // valid markup that silently renders unstyled.
        return $('<div class="sr-table-card">').append($state);
    }

    /** One cover statistic. */
    function statTile(label, value) {
        return $('<div class="sr-soa-stat">')
            .append($('<span class="sr-soa-stat-n">').text(Number(value).toLocaleString()))
            .append($('<span class="sr-soa-stat-l">').text(label));
    }

    /**
     * The three applicability states, as the controls table renders them.
     *
     * Deliberately the SAME shipped .sr-state-pill component and the SAME three
     * modifier classes governance-frameworks.js's APPLICABILITY_OPTIONS uses, so
     * one control reads identically on the grid and in the document produced
     * from it. A private set of chip classes here would let the two drift into
     * saying the same thing two different ways.
     */
    var APPLICABILITY_OPTIONS = {
        applicable:     { key: 'ApplicabilityApplicable',    cls: 'sr-state-success' },
        not_applicable: { key: 'ApplicabilityNotApplicable', cls: 'sr-state-neutral' },
        inherited:      { key: 'ApplicabilityInherited',     cls: 'sr-state-info' }
    };

    /** A row's applicability state, as the shipped state pill. */
    function applicabilityPill(state) {
        var option = APPLICABILITY_OPTIONS[state] || APPLICABILITY_OPTIONS.applicable;
        return $('<span class="sr-state-pill">')
            .addClass(option.cls)
            .attr('data-sr-applicability', state)
            .text(L(option.key));
    }

    /**
     * The label for a row's implementation status.
     *
     * `not_applicable` reuses the existing 'NotApplicable' key, whose value is
     * the abbreviation "N/A" -- which is exactly right in a dense column and is
     * why the server sends a TOKEN here rather than a rendered em dash.
     *
     * -----------------------------------------------------------------------
     * EVERY TOKEN IS LISTED. THERE IS NO FALLTHROUGH TO A VERDICT.
     * -----------------------------------------------------------------------
     * This function used to end `return L('No');`. When `no_test_defined` and
     * `never_run` joined the derivation (includes/soa.php), that line silently
     * rendered "No" -- an assertion that the control does NOT WORK -- for every
     * control the organisation had simply never tested. On a framework where
     * most controls carry no test, that was the whole document, in an artefact
     * the customer attests to.
     *
     * The final return is now 'SoaImplementedUnknown' ("Status unavailable"),
     * which is deliberately NOT A VERDICT: an unrecognized token is a bug, and
     * the only safe way to render a bug in a compliance document is as a value
     * that states nothing about the control. "No" understates; "Yes" would
     * overstate, which is worse.
     *
     * KEEP THIS IN STEP WITH soa_export_implemented_label()
     * (extras/import-export/includes/soa_export.php). The screen and the two
     * exports must not label the same token differently, and
     * SoaImplementedDerivationTest asserts the two mappings cover the same set.
     */
    function implementedLabel(token) {
        if (token === 'yes') { return L('Yes'); }
        if (token === 'partial') { return L('SoaImplementedPartial'); }
        if (token === 'no') { return L('No'); }
        if (token === 'not_applicable') { return L('NotApplicable'); }
        if (token === 'no_test_defined') { return L('SoaImplementedNoTestDefined'); }
        if (token === 'never_run') { return L('SoaImplementedNeverRun'); }
        // NOT a verdict. See the note above.
        return L('SoaImplementedUnknown');
    }

    /**
     * The semantic family a verdict belongs to, mirroring soa_implemented_family()
     * (includes/soa.php) so the screen, the PDF and the spreadsheet colour one
     * verdict the same way.
     *
     * NEUTRAL FOR THE THREE NON-VERDICTS, deliberately. `no_test_defined`,
     * `never_run` and the unknown fallback are the ABSENCE of news, not bad news
     * about the control; painting them the red of a confirmed failure would
     * state something the derivation refused to state. They are findings, and
     * the legend and the Evidence column are where that is said.
     */
    function implementedFamily(token) {
        if (token === 'yes') { return 'success'; }
        if (token === 'partial') { return 'warning'; }
        if (token === 'no') { return 'danger'; }
        return 'neutral';
    }

    /**
     * THE FOUR EVIDENCE GLYPHS.
     *
     * A MIRROR of SOA_EVIDENCE_GLYPHS (includes/soa.php), asserted equal by
     * SoaLegendAndMarkersTest -- which also asserts the same four characters are
     * the `content:` values in scss/modules/_statement-of-applicability.scss.
     * Three copies because a stylesheet cannot read a PHP constant and neither
     * can this file.
     *
     * ONLY THE LEGEND READS THIS. The bullets themselves are marked by CSS
     * `::before` on the `.sr-soa-evitem--*` class, which is what keeps the glyph
     * out of the DOM text and therefore out of a screen reader's way. The legend
     * needs the character in the DOM because there is no per-entry class to hang
     * it off -- and the legend is the one place the reader is being told what the
     * symbol IS, so it has to be the symbol and not a description of one.
     */
    var EVIDENCE_GLYPHS = {
        pass:    '✓', // ✓
        fail:    '✗', // ✗
        unknown: '•', // •
        doc:     '§'  // §
    };

    /**
     * THE DOCUMENT'S LEGEND — every value it can print and what each means,
     * GROUPED BY THE COLUMN IT APPEARS IN.
     *
     * A MIRROR of SOA_LEGEND_GROUPS (includes/soa.php), asserted equal by
     * SoaLegendAndMarkersTest. The screen cannot read a PHP constant, and the
     * three sinks defining the vocabulary differently would be worse than any of
     * them defining it at all — a reader holding the printout and the screen has
     * to be able to reconcile them.
     *
     * COMPLETE, never filtered to the values this framework happens to contain:
     * a controlled document's key must not change shape between two generations
     * of it, or last year's SoA cannot be diffed against this year's.
     *
     * THE GROUP HEADING IS THE COLUMN HEADING — the same lang key the table's own
     * <th> uses. That is the point of the grouping: a reader matching the key to
     * the table matches two identical words.
     */
    var LEGEND_GROUPS = [
        {
            heading: 'Applicability',
            entries: [
                { term: 'ApplicabilityApplicable',    definition: 'SoaLegendApplicable',                 family: 'success' },
                { term: 'ApplicabilityNotApplicable', definition: 'SoaLegendApplicabilityNotApplicable', family: 'neutral' },
                { term: 'ApplicabilityInherited',     definition: 'SoaLegendInherited',                  family: 'info'    }
            ]
        },
        {
            heading: 'SoaImplemented',
            entries: [
                { term: 'Yes',                         definition: 'SoaLegendYes',           family: 'success' },
                { term: 'SoaImplementedPartial',       definition: 'SoaLegendPartial',       family: 'warning' },
                { term: 'No',                          definition: 'SoaLegendNo',            family: 'danger'  },
                { term: 'SoaImplementedNoTestDefined', definition: 'SoaLegendNoTestDefined', family: 'neutral' },
                { term: 'SoaImplementedNeverRun',      definition: 'SoaLegendNeverRun',      family: 'neutral' },
                { term: 'SoaImplementedUnknown',       definition: 'SoaLegendUnknown',       family: 'neutral' },
                { term: 'NotApplicable',               definition: 'SoaLegendNotApplicable', family: 'neutral' },
                { term: 'Overdue',                     definition: 'SoaLegendOverdue',       family: 'danger'  }
            ]
        },
        {
            heading: 'Evidence',
            entries: [
                { term: 'Pass',                      definition: 'SoaLegendEvidencePass',         family: 'success', glyph: 'pass'    },
                { term: 'Fail',                      definition: 'SoaLegendEvidenceFail',         family: 'danger',  glyph: 'fail'    },
                { term: 'Inconclusive',              definition: 'SoaLegendEvidenceInconclusive', family: 'neutral', glyph: 'unknown' },
                { term: 'SoaEvidenceDesignDocument', definition: 'SoaLegendEvidenceDocument',     family: 'neutral', glyph: 'doc'     },
                { term: 'SoaNoEvidenceLinked',       definition: 'SoaLegendNoEvidence',           family: 'warning' },
                { term: 'SoaEvidenceNotExpected',    definition: 'SoaLegendEvidenceNotExpected',  family: 'nil'     }
            ]
        },
        {
            heading: 'SoaAppendixRemediation',
            entries: [
                { term: 'SoaRemediationUnplanned', definition: 'SoaLegendUnplanned', family: 'danger' }
            ]
        }
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // THERE IS NO REQUIRED-EVIDENCE MARKER, AND THAT IS A POSITION.
    // ─────────────────────────────────────────────────────────────────────────
    // A "required evidence not supplied" note briefly hung off each test bullet,
    // with the requirement's wording in an Appendix E. Both are gone, because the
    // claim was not one this document is entitled to make.
    //
    // WHAT IT ACTUALLY MEASURED: the test definition's `required_evidence` field
    // is non-empty, AND the audit behind the displayed verdict has no row in
    // `compliance_files` (soa_audit_evidence_map(), includes/soa.php). That is a
    // file-attachment check. Nobody assessed whether the evidence was adequate,
    // and it has no bearing on the verdict -- a test passes or fails on the
    // tester's conclusion, not on whether they uploaded something.
    //
    // WHY THAT IS WRONG HERE, in this file's own terms: RULE 2 says never a second
    // opinion about a fact the product already states. The tester examined the
    // control and recorded Pass. Flagging that same test as deficient because a
    // file row is missing contradicts the recorded conclusion using a worse-
    // informed proxy -- and plenty of organisations keep evidence outside
    // SimpleRisk entirely, which the check cannot distinguish from negligence.
    //
    // AND IT FIRED ON 44 OF 60 EVIDENCE ITEMS on the ISO 27001 framework. A
    // finding that appears on three-quarters of the population is not a finding;
    // it teaches a reader to discount the document's other markers.
    //
    // The defensible version of this claim is already here and is unaffected:
    // `evidence_absence` = 'gap' reports a control with NOTHING linked -- no
    // confirmed document and no test that produced a result. That is a real
    // finding about the SoA's own subject matter. Whether a particular test's
    // paperwork is complete belongs to the test, on Define Tests.

    /**
     * The Evidence cell's content as lines — mirroring soa_export_evidence_lines()
     * (extras/import-export/includes/soa_export.php).
     *
     * Called ONLY when there IS evidence; the two absences are handled by the
     * caller, because each gets markup of its own rather than a line of text.
     *
     * ITEMS, NOT THE FLAT STRING: `evidence` is the confirmed documents joined,
     * which is DESIGN only, while `evidence_items` adds the tests that produced a
     * verdict, which is OPERATION. The flat string stays the fallback for a
     * payload that predates the items.
     *
     * EACH LINE CARRIES ITS KIND, which the two export sinks have no use for and
     * this one does: the cell stacks four different kinds of fact (a document, a
     * test's verdict, an evidence requirement and the artifacts it names) and
     * they were all rendered in the same weight, which is what made a tested
     * control's cell a wall. The TEXT stays identical to the PHP mirror -- the
     * kind is a rendering hint beside it, not a change to what is said.
     */
    function evidenceLines(row) {

        var items = row.evidence_items;

        if (!items || !items.length) {

            // ─────────────────────────────────────────────────────────────
            // NO ITEMISATION AT ALL — WHICH THIS SERVER NEVER SENDS.
            // ─────────────────────────────────────────────────────────────
            // Every row build_soa_rows() emits carries `evidence_items`, so this is
            // a last resort for a hand-assembled payload.
            //
            // NEVER PREFER THE FLAT STRING WHEN THE ITEMISATION EXISTS. It is the
            // confirmed DOCUMENTS only, so a control substantiated solely by a
            // passing quarterly test has an empty one -- and reading it first would
            // print "No evidence linked" against a control the organisation tests
            // every quarter. A FABRICATED FINDING in a document the customer
            // attests to. (Caught by SCENARIO-46, not by the PHP tests -- they
            // cover the two EXPORT sinks, and this is the third.)
            return row.evidence ? [{ kind: 'doc', text: row.evidence }] : [];
        }

        var lines = [];

        items.forEach(function (item) {

            var name = (item.name || '').trim();

            if (item.type !== 'test') {
                // ─────────────────────────────────────────────────────────────
                // DESIGN EVIDENCE — a governance document.
                // ─────────────────────────────────────────────────────────────
                // Labelled by KIND, because a policy and a procedure are not
                // interchangeable to an auditor: one states intent, the other
                // states how it is carried out. The list used to be four
                // document types flattened into undifferentiated names.
                //
                // `glyph: 'doc'` rather than a result glyph: a document has no
                // verdict. It either exists and is linked, or it is not here at
                // all -- which is what makes marking it with a tick wrong.
                if (!name) { return; }

                lines.push({
                    kind: 'doc',
                    glyph: 'doc',
                    text: name,
                    meta: documentTypeLabel(item.document_type)
                });
                return;
            }

            // ─────────────────────────────────────────────────────────────────
            // OPERATIONAL EVIDENCE — a test that produced a verdict.
            // ─────────────────────────────────────────────────────────────────
            // THE VERDICT BECOMES THE BULLET, not a clause in the sentence. The
            // line used to read "Name — Pass (2026-07-02)", which put the one
            // fact a reader scans for in the middle of a string; as a glyph it
            // is comparable down a column at a glance, and the row keeps the
            // date it is asking about.
            //
            // The glyph is derived from the SERVER'S `test_result` and the
            // result text is still printed as `meta`, so the glyph is never the
            // only channel -- it survives a monochrome print and a screen
            // reader, and a result vocabulary this file does not recognise
            // degrades to a neutral bullet rather than to a wrong one.
            if (name || item.test_result) {

                // ─────────────────────────────────────────────────────────────
                // THE RESULT WORD IS PRINTED ONLY WHEN THE LEGEND CANNOT NAME IT.
                // ─────────────────────────────────────────────────────────────
                // "✓ … Pass" and "✗ … Fail" state the verdict twice, and so does
                // "• … Inconclusive": all three glyphs are DEFINED in the legend
                // (SOA_LEGEND_GROUPS' Evidence group), so the word beside them is
                // noise in a cell whose whole problem was volume.
                //
                // THE ONE CASE THAT STILL PRINTS IT is a result vocabulary this
                // build does not recognise -- a locale that spells its results
                // differently, or a future result state. testResultGlyph() degrades
                // that to the same neutral dot, and there the dot says only "not a
                // pass or a fail". The legend cannot name a value it has never seen,
                // so the word is the ONLY channel carrying the verdict and is kept.
                //
                // Either way the word reaches assistive technology, via the
                // visually-hidden span the caller renders -- so dropping it is a
                // visual economy, not a loss of information, and a screen reader
                // still hears "Pass" rather than a bare glyph.
                var glyph = testResultGlyph(item.test_result);

                // ─────────────────────────────────────────────────────────────
                // AND THE NEXT-DUE DATE IS GONE.
                // ─────────────────────────────────────────────────────────────
                // A Statement of Applicability records the CURRENT state of the
                // control -- ISO 27001 clause 6.1.3(d) asks for applicability,
                // justification, implementation status and the evidence behind
                // them. All four are retrospective. When a test will next run is
                // programme administration, and it is read on Define Tests.
                //
                // Its one SoA-relevant use is deciding whether the result on
                // display is STALE, and that is already reported: the overdue
                // marker sits on the status pill and is derived from this very
                // date. Printing the date as well restated the input to a
                // judgement the document had already made.
                // THE DATE IS BARE -- no "Tested" prefix. In a column headed
                // Evidence, beside a glyph and a test's name, the only date there is
                // to state is when that test last ran, and the label spent width
                // saying what the position already says. It also aligns the screen
                // with both exports, which have always printed "(2026-04-01)".
                // 'SoaTested' keeps no sink and is no longer shipped to this page.
                var meta = [];
                if (item.test_result && !resultIsNamedByTheLegend(item.test_result)) {
                    meta.push(item.test_result);
                }
                if (item.last_date) { meta.push(item.last_date); }

                lines.push({
                    kind: 'test',
                    glyph: glyph,
                    text: name,
                    meta: meta.join(' · '),
                    // Always the full word, for the visually-hidden label -- the
                    // glyph is a visual shorthand and must not be the only form
                    // the verdict exists in.
                    resultLabel: item.test_result || ''
                });
            }
        });

        return lines;
    }

    /**
     * The bullet glyph for a test's verdict.
     *
     * Matched case-insensitively against the SERVER'S resolved label, and
     * anything unrecognised gets the neutral bullet. That fallback is the point:
     * a locale or a future result vocabulary this file has never seen must not be
     * silently drawn as a tick, because a tick on a failing control is the one
     * error this document cannot afford.
     */
    function testResultGlyph(result) {

        var value = String(result || '').trim().toLowerCase();

        if (value === '') { return 'unknown'; }
        if (value.indexOf(String(L('Pass')).toLowerCase()) === 0) { return 'pass'; }
        if (value.indexOf(String(L('Fail')).toLowerCase()) === 0) { return 'fail'; }

        // 'Inconclusive' and anything else that is neither a pass nor a fail.
        return 'unknown';
    }

    /**
     * Whether the LEGEND names this result, and therefore whether the bullet needs
     * to print the word as well as the glyph.
     *
     * THREE RESULTS ARE NAMED: Pass, Fail and Inconclusive each have their own
     * legend entry showing their glyph beside their word, so repeating the word on
     * the bullet states the verdict twice.
     *
     * ANYTHING ELSE IS NOT NAMED, and that is the case this function exists for. A
     * locale that spells its results differently, or a result state added after this
     * build, degrades to the same neutral dot as Inconclusive -- and the legend
     * cannot define a value it has never seen. There the word is the only channel
     * carrying the verdict, so it is printed.
     *
     * MATCHED THE SAME WAY testResultGlyph() matches, against the SERVER'S resolved
     * label rather than a raw token, and with the same prefix comparison -- the two
     * have to agree about what "recognised" means or a result would get a glyph
     * whose word was suppressed by a different rule.
     */
    function resultIsNamedByTheLegend(result) {

        var value = String(result || '').trim().toLowerCase();

        if (value === '') { return false; }

        return ['Pass', 'Fail', 'Inconclusive'].some(function (key) {
            return value.indexOf(String(L(key)).toLowerCase()) === 0;
        });
    }

    /**
     * A document's type as a display label -- "Policy", "Standard", "Procedure",
     * "Guideline" -- from the raw `documents`.`document_type` value.
     *
     * SINGULAR keys, because this labels ONE document. SimpleRisk's existing keys
     * for these are the plural tab names ('Policies', 'Standards', ...), which
     * would print "Legal & Regulatory Policy (Policies)".
     *
     * An unrecognised or absent type returns '' and the bullet simply carries no
     * kind -- better than inventing a class for a document whose type the
     * governance module may add later.
     */
    function documentTypeLabel(type) {

        var map = {
            policies:   'SoaEvidencePolicy',
            standards:  'SoaEvidenceStandard',
            procedures: 'SoaEvidenceProcedure',
            guidelines: 'SoaEvidenceGuideline'
        };

        var key = map[String(type || '').trim().toLowerCase()];

        return key ? L(key) : '';
    }

    /**
     * The remediation block's lines — mirroring soa_export_remediation_lines().
     *
     * The first entry is the heading; the rest are the findings. EVERY failing
     * test, because the server sent every one, and the unplanned flag says so
     * out loud rather than leaving a blank that reads as "nothing to report".
     */
    function remediationLines(row) {

        var remediation = row.remediation;

        if (!remediation || !remediation.length) { return []; }

        var lines = [L('SoaRemediation')];

        remediation.forEach(function (finding) {

            var headline = finding.test_name || '';
            if (finding.test_result) { headline += (headline ? ' — ' : '') + finding.test_result; }

            var date = finding.last_result_date || finding.last_date;
            if (date) { headline += ' (' + date + ')'; }
            if (headline) { lines.push(headline); }

            if (finding.summary) { lines.push(finding.summary); }

            // THE FINDING, before the plans — a reader scanning for it must not
            // have to reach the end of a list to discover there are none.
            if (finding.unplanned) {
                lines.push(L('SoaRemediationUnplanned'));
                return;
            }

            (finding.risks || []).forEach(function (risk) {

                var line = L('Risk') + ' ' + risk.risk_id;

                if (!risk.planned) {
                    lines.push(line + ' — ' + L('SoaRemediationUnplanned'));
                    return;
                }

                if (risk.planning_date) { line += ' — ' + L('MitigationPlanning') + ': ' + risk.planning_date; }
                if (risk.mitigation_owner) { line += ' — ' + L('MitigationOwner') + ': ' + risk.mitigation_owner; }
                if (risk.mitigation_percent !== null && risk.mitigation_percent !== undefined) {
                    line += ' — ' + L('PercentComplete') + ': ' + risk.mitigation_percent + '%';
                }

                lines.push(line);
            });
        });

        return lines;
    }

    // -------------------------------------------------------------------------
    // The document
    // -------------------------------------------------------------------------

    /**
     * The cover block: what this is a statement about, the scope it is made
     * against, and the counts.
     *
     * The counts come straight off `cover`. See rule 1 above.
     */
    function renderCover(cover) {

        var $cover = $('<section class="sr-soa-cover">');

        // WHOSE statement this is. The document has no sidebar and no
        // breadcrumb, so the cover is the only context a reader -- or an
        // auditor holding a printout -- has. Omitted rather than rendered blank
        // when the instance is unregistered and has no organisation name.
        if (cover.organization) {
            $cover.append($('<p class="sr-soa-org">').text(cover.organization));
        }

        $cover.append($('<h1 class="sr-soa-title">').text(L('StatementOfApplicability')));
        $cover.append($('<p class="sr-soa-framework">').text(cover.framework_name));

        var $meta = $('<div class="sr-soa-meta">');
        $meta.append($('<span>').text(L('SoaGeneratedOn') + ': ' + cover.generated_at));

        $cover.append($meta);

        var $stats = $('<div class="sr-soa-stats">');
        $stats.append(statTile(L('Controls'), cover.total));
        $stats.append(statTile(L('ApplicabilityApplicable'), cover.applicable));
        $stats.append(statTile(L('ApplicabilityNotApplicable'), cover.not_applicable));
        $stats.append(statTile(L('ApplicabilityInherited'), cover.inherited));
        $stats.append(statTile(L('SoaExcludedCount'), cover.excluded));
        $cover.append($stats);

        // THE SCOPE STATEMENT, AND ONLY THE SCOPE STATEMENT. Rendered only when
        // there is something to render; when there is not, the prompt below says
        // so instead of leaving a silently empty heading.
        //
        // The default inclusion justification is deliberately NOT on the cover,
        // although the payload still carries it (the framework modal reads the
        // same object). Clause 6.1.3(d) wants the justification PER CONTROL, and
        // that is the Justification cell -- which prints this very sentence on
        // every applicable control with no linked risks. Printing it here as
        // well repeated one sentence ~1,500 times on an SCF-sized document and
        // told the reader nothing the rows had not already said. The cover's job
        // is the scope; the rows' job is the justification.
        //
        // INSERTED AS HTML, AND THAT IS DELIBERATE. The scope statement is a
        // rich-text field (HugeRTE) -- it holds the paragraphs and the list of
        // in-scope entities the author wrote, and .text() would print "<p>" to
        // the reader. Every other value on this page is still .text().
        //
        // Why this is not a stored-XSS sink: the value is purified TWICE before
        // it gets here -- on write (update_framework_soa_fields(),
        // includes/governance.php) and again at the payload boundary
        // (build_soa_cover(), includes/soa.php), which is what covers a row that
        // never went through the write path. Nothing is purified client-side;
        // adding an escape here would print entities instead.
        //
        // The wrapper is a <div>, not a <p>: the value is block-level, so a <p>
        // would nest <p> inside <p> and browsers would silently unnest it.
        if (cover.scope_statement) {
            $cover.append($('<div class="sr-soa-statement">')
                .append($('<h2>').text(L('IsmsScopeStatement')))
                .append($('<div class="sr-soa-statement-body">').html(cover.scope_statement)));
        }

        return $cover;
    }

    /**
     * The prompt for the cover fields the framework has never been given.
     *
     * PROMPTS ON EITHER SPELLING OF EMPTY -- null (never set) and '' (set once
     * and deliberately cleared). The API preserves the distinction and a
     * consumer that cares can read it, but from a reader's point of view both
     * mean there is nothing on the cover, and an SoA with no stated scope is the
     * first thing an auditor asks about. Shown ABOVE the table rather than
     * blocking it: the document is still worth reading, and refusing to render
     * it would hide the very rows the user came for.
     */
    function renderMissingFieldsPrompt(cover) {

        var missing = [];
        if (!cover.scope_statement) { missing.push(L('SoaMissingScopeStatement')); }
        if (!cover.default_inclusion_justification) { missing.push(L('SoaMissingInclusionJustification')); }

        if (!missing.length) { return null; }

        // Its OWN classes, not the modal shell's .sr-qnote: that block is scoped
        // inside .sr-qform / .sr-modal, so reusing it on a page would be markup
        // that reads correct and renders unstyled.
        var $note = $('<div class="sr-soa-prompt">');
        $note.append($('<span class="sr-soa-prompt-ico">').append($('<i class="fa fa-triangle-exclamation" aria-hidden="true">')));

        var $body = $('<div class="sr-soa-prompt-body">');
        $body.append($('<strong>').text(L('SoaMissingFieldsTitle')));
        missing.forEach(function (line) { $body.append($('<p>').text(line)); });

        // Straight to the framework the fields live on. The SoA card in that
        // modal (display_framework_soa_card()) is where they are edited; sending
        // the user to a second copy of the form here would be a second place for
        // the same two fields to be set from.
        $body.append($('<a class="sr-soa-fix">')
            .attr('href', BASE_URL + '/governance/index.php?framework=' + encodeURIComponent(cover.framework))
            .text(L('SoaEditFrameworkToAdd')));

        return $note.append($body);
    }

    /**
     * THE LEGEND — required, not decorative.
     *
     * The vocabulary is more nuanced than the Yes/No/Partial an auditor expects to
     * meet, and 'Status unavailable' in particular reads as an admission about the
     * control when it is actually a defect in the software. An undefined
     * vocabulary invites the reader to guess, and they guess unfavourably — so the
     * document defines every value it can print, in every format.
     *
     * ONE <dl> PER COLUMN, each under that column's own heading. It used to be a
     * single flat list of eleven terms with states, markers and evidence absences
     * interleaved; the reader's question is never "what is the fourth term in the
     * key?" but "what does THIS cell mean?", and the cell is in a column.
     *
     * The four evidence glyphs are documented here for the first time — see
     * SOA_EVIDENCE_GLYPHS (includes/soa.php) for why they had become load-bearing
     * while being defined nowhere.
     */
    function renderLegend() {

        var $legend = $('<section class="sr-soa-legend">');
        $legend.append($('<h2>').text(L('SoaLegendTitle')));

        LEGEND_GROUPS.forEach(function (group) {

            // THE COLUMN'S OWN HEADING, so the reader matches the key to the table
            // by matching two identical words.
            $legend.append($('<h3 class="sr-soa-legend-h">').text(L(group.heading)));

            var $list = $('<dl class="sr-soa-legend-list">');

            group.entries.forEach(function (entry) {

                var $row = $('<div class="sr-soa-legend-row">');
                var $term = $('<dt class="sr-soa-legend-term">');

                // THE TERM IS RENDERED AS THE MARK ITSELF, not as a description of
                // it: the same pill, the same overdue badge, the same em dash, the
                // same bullet glyph the table uses. A key that describes a mark in
                // words leaves the reader matching prose to graphics; one that SHOWS
                // the mark is matched at a glance.
                if (entry.glyph) {
                    // A BULLET ENTRY. The glyph, then the word it stands for --
                    // both, because the glyph is what the table prints and the word
                    // is what makes it findable. The same `--<glyph>` modifier the
                    // real bullets carry, so the colour comes from one rule set.
                    $term.append($('<span class="sr-soa-legend-glyph">')
                        .addClass('sr-soa-evitem--' + entry.glyph)
                        .text(EVIDENCE_GLYPHS[entry.glyph] || ''));
                    $term.append($('<span class="sr-soa-legend-glyph-l">').text(L(entry.term)));
                } else if (entry.term === 'Overdue') {
                    $term.append(overdueMark());
                } else if (entry.term === 'SoaNoEvidenceLinked') {
                    $term.append(gapMark());
                } else if (entry.term === 'SoaEvidenceNotExpected') {
                    $term.append(nilMark());
                } else {
                    // Every remaining entry is a state pill, which is what the
                    // Applicability and Implementation Status columns print. No
                    // family filter: an entry whose family this build does not
                    // recognise still gets its pill and its definition rather than
                    // silently rendering an empty term.
                    $term.append($('<span class="sr-state-pill">')
                        .addClass('sr-state-' + entry.family)
                        .text(L(entry.term)));
                }

                $row.append($term);
                $row.append($('<dd class="sr-soa-legend-def">').text(L(entry.definition)));
                $list.append($row);
            });

            $legend.append($list);
        });

        return $legend;
    }

    /**
     * THE OVERDUE MARK — and the requirement on it is unusually strict: an
     * auditor skimming a column of "Implemented: Yes" must not be able to
     * overlook that the evidence behind one of them is years stale. A marker
     * that is easy to skip past looks like disclosure while functioning as
     * concealment, which is worse than not having one.
     *
     * So it is deliberately NOT a superscript, a muted glyph or a "(overdue)"
     * suffix. It carries FOUR redundant signals:
     *
     *   1. WORDS, not a symbol — legible with no key, and searchable.
     *   2. A FILLED, RULED BLOCK in the danger family, on its own line, so it
     *      is never truncated by a narrow column and never reads as part of the
     *      verdict above it.
     *   3. A GLYPH, so the signal survives a monochrome printout — and this
     *      document is printed.
     *   4. A RAIL down the whole row (`sr-soa-row--overdue`), which is what
     *      makes it findable while SKIMMING rather than while reading.
     *
     * The verdict itself still stands beside it: the test genuinely did pass or
     * fail, and only its currency is in question.
     */
    function overdueMark() {
        return $('<span class="sr-soa-overdue">')
            .append($('<i class="fa fa-triangle-exclamation" aria-hidden="true">'))
            .append(document.createTextNode(' ' + L('Overdue')));
    }

    /**
     * THE EVIDENCE GAP — the control is in scope, is possibly claimed
     * implemented, and NOTHING substantiates it. Stated in words, in the warning
     * family, because an empty cell would hide the finding an auditor circles
     * first.
     */
    function gapMark() {
        return $('<span class="sr-soa-gap">')
            .append($('<i class="fa fa-circle-exclamation" aria-hidden="true">'))
            .append(document.createTextNode(' ' + L('SoaNoEvidenceLinked')));
    }

    /**
     * THE OTHER ABSENCE, which is its opposite: the control is excluded, so no
     * evidence is EXPECTED and the blank is correct. A quiet em dash — and it
     * carries a title, because a lone dash tells a screen reader nothing.
     */
    function nilMark() {
        return $('<span class="sr-soa-nil">')
            .attr('title', L('SoaLegendEvidenceNotExpected'))
            .text(L('SoaEvidenceNotExpected'));
    }

    // riskLines() AND cadenceLines() USED TO BE HERE. Both were columns, and both
    // columns are gone.
    //
    // riskLines() printed the linked risk ids for a Risk Register column, saying
    // "Not recorded" rather than leaving a blank. The traceability it carried is
    // NOT lost -- Appendix R names the risk id beside the planning date, the
    // mitigation owner and the percentage, attached to the failure it explains
    // rather than sitting in a column of bare ids -- and soa_justification_for()
    // still cites those ids in the Justification cell for a control justified by
    // its risk assessment. `row.risks` is no longer in the payload.
    //
    // cadenceLines() read `review_cadence`, one composed line per live test, for a
    // Review Cadence column. That column went and each test's schedule moved onto
    // that test's own evidence bullet -- the only place it can be unambiguous,
    // because a control with three tests has three schedules and one line above
    // three bullets cannot say which it schedules. The export was the last reader
    // of the key and has now followed; `review_cadence` is gone from the payload
    // too, so nothing reads it anywhere.

    /** The document body: one row per control. */
    function renderTable(rows) {

        var $card = $('<div class="sr-table-card sr-soa-table">');
        var $scroll = $('<div class="sr-table-scroll">');
        var $table = $('<table class="sr-table">');

        // ─────────────────────────────────────────────────────────────────────
        // SIX COLUMNS, ONE DOCUMENT, AND THE PRINT WIDTHS KEY ON THIS CLASS.
        // ─────────────────────────────────────────────────────────────────────
        // The class is unconditional; it exists because the print stylesheet has
        // to pin a width per column -- CSS cannot count them -- and those widths
        // MIRROR SOA_EXPORT_PDF_COLUMN_WIDTHS
        // (extras/import-export/includes/soa_export.php), which is what Dompdf
        // uses for the same six. SoaPdfThresholdTest asserts the two agree; a
        // width set fitted to the wrong column count is a silent clip on paper
        // with nothing to say it happened.
        $card.addClass('sr-soa-table--single');

        // ─────────────────────────────────────────────────────────────────────
        // IMPLEMENTATION STATUS AND EVIDENCE ARE TWO COLUMNS, NOT ONE.
        // ─────────────────────────────────────────────────────────────────────
        // They were briefly merged into one stacked "Verification" cell, together
        // with Review Cadence, on the argument that all three are derived from the
        // same tests and answer one question. Cadence leaving for the evidence
        // bullets was right and stands. Merging the other two was not, for three
        // reasons:
        //
        //   - CLAUSE 6.1.3(d) NAMES IMPLEMENTATION STATUS. A named requirement of
        //     the standard this document exists to satisfy gets a column, not a
        //     line inside one.
        //
        //   - THE SCAN IS THE POINT. An auditor's first pass is "which controls
        //     are not implemented", and that is a finger down one narrow column.
        //     A status sitting atop a variable-length evidence list does not line
        //     up with the status of the row above it, so there is nothing to run
        //     an eye down.
        //
        //   - DOCUMENT EVIDENCE FEEDS NO PART OF THE STATUS, which is computed
        //     from tests alone (soa_implemented_for(), includes/soa.php). Stacking
        //     the two implied a derivation that does not exist: a reader could
        //     reasonably infer the policy listed beneath "No" had been weighed in
        //     reaching it. It had not.
        //
        // THE 87px MEASUREMENT THAT DROVE THE MERGE IS NO LONGER THE CONSTRAINT.
        // Evidence resolved to 87px when there were NINE columns -- "No evidence
        // linked" wrapped over five lines inside its own badge. At six it gets 19%
        // (~183px), because the justification prose moved to Appendix A and two
        // columns left entirely. That is what makes splitting these affordable
        // again; it was not affordable at nine.
        var headings = [
            L('Reference'), L('ControlName'), L('Applicability'),
            L('Justification'), L('SoaImplemented'), L('Evidence')
        ];

        var $tr = $('<tr>');
        headings.forEach(function (h) { $tr.append($('<th>').text(h)); });
        $table.append($('<thead>').append($tr));

        var $tbody = $('<tbody>');

        // ─────────────────────────────────────────────────────────────────────
        // THE TWO APPENDICES, COLLECTED WHILE THE ROWS ARE BUILT.
        // ─────────────────────────────────────────────────────────────────────
        // The table is a REGISTER: one row per control, scannable, countable.
        // Two things kept breaking that.
        //
        // Justifications, because they are long and largely repeated -- on the
        // ISO 27001 framework 51 controls carry 27 distinct justifications and a
        // single 375-character paragraph appears 25 times, in the widest column
        // on the page. The row now prints its opening and a marker; the complete
        // text of every control's justification is printed in full afterwards, so
        // nothing is lost and no reader has to read the same paragraph 25 times
        // to find the one that differs.
        //
        // Remediation, because it was a full-width sub-row: it broke the
        // one-row-per-control scan, and made a count of tbody rows stop meaning
        // the control count. It is also a different artifact -- ISO 27001 keeps
        // the Statement of Applicability (6.1.3(d)) and the risk treatment plan
        // (6.1.3(e)) separate, so a marker into a numbered plan is closer to how
        // an auditor expects to receive it than a paragraph wedged into the
        // register.
        //
        // Numbered in ROW ORDER as they are collected, so A3 and R2 are findable
        // by counting down the table, and the markers are stable for a given
        // document rather than depending on anything the client re-sorts.
        var justificationNotes = [];
        var remediationNotes   = [];

        rows.forEach(function (row) {

            // `sr-soa-row` marks a CONTROL row, because the table also carries
            // remediation sub-rows now and a count of `tbody tr` would otherwise
            // stop being the control count -- the one number this document is
            // reconciled by.
            var $row = $('<tr class="sr-soa-row">')
                .toggleClass('sr-soa-row--overdue', !!row.implemented_overdue);

            // THE FRAMEWORK'S references, one unbreakable token each.
            //
            // A control carries as many references as this framework cites it by
            // (ISO 27002 cites SCF's TPM-05 seven times), so the CELL has to be
            // able to wrap while each code stays whole -- "GV.OC-01" broken
            // across a line at its hyphen is a code a reader cannot scan for.
            // That is why the server sends `refs` as a list and not only the
            // joined `ref`; the fallback keeps this working against a payload
            // that predates it.
            var references = row.refs && row.refs.length
                ? row.refs
                : (row.ref ? [row.ref] : []);

            var $ref = $('<td class="sr-soa-ref">');

            if (references.length > 1) {
                // Only a multi-reference cell relaxes to normal wrapping and
                // tokenizes; a single code stays a bare nowrap cell, which is
                // both the common case and what this column always was.
                $ref.addClass('sr-soa-ref--multi');
                references.forEach(function (reference, index) {
                    if (index > 0) { $ref.append(document.createTextNode(', ')); }
                    $ref.append($('<span class="sr-soa-refitem">').text(reference));
                });
            } else {
                $ref.text(references[0] || '');
            }

            $row.append($ref);

            // The FRAMEWORK's title for the control, then the SimpleRisk
            // control's own name beneath it -- and only when the two differ,
            // which is exactly when the framework supplied a title of its own.
            // A reader needs both: ISO's wording to read the document, and the
            // SimpleRisk name to find the control in the app. Same muted
            // treatment as the justification's attribution lines, because it is
            // the same kind of secondary fact.
            var $name = $('<td class="sr-soa-name">').text(row.name);
            if (row.control_name && row.control_name !== row.name) {
                $name.append($('<span class="sr-soa-attrib">').text(row.control_name));
            }
            $row.append($name);

            $row.append($('<td class="sr-soa-appl">').append(applicabilityPill(row.applicable)));

            // NEVER BLANK by construction (soa_justification_for()) -- a
            // deviation's own narrative, the linked risks, or the framework
            // default, in that order. Nothing here supplies a fallback, because
            // a client-side fallback would paper over a server-side blank rather
            // than surfacing it.
            // THE ROW PRINTS AN OPENING, THE APPENDIX PRINTS THE RECORD.
            //
            // Every control gets its own appendix entry, including the ones whose
            // text is identical to twenty others': the appendix is the record, and
            // a record that says "same as A1" makes the reader prove the sameness
            // themselves. The row's job is only to be scannable and to point.
            //
            // Truncation is by CHARACTER BUDGET rather than by sentence, because
            // these are user- and AI-authored strings with no guaranteed
            // punctuation -- splitting on the first '.' turns "approved by J. Smith"
            // into two words. Broken at the last space inside the budget so a word
            // is never cut in half.
            var fullJustification = String(row.justification || '');
            var marker = 'A' + (justificationNotes.length + 1);

            justificationNotes.push({
                marker: marker,
                ref: row.ref || (row.refs || []).join(', '),
                name: row.control_short_name || row.control_name || '',
                text: fullJustification
            });

            var $justification = $('<td class="sr-soa-just">')
                .append($('<span class="sr-soa-just-lead">').text(truncateForRow(fullJustification)))
                .append($('<span class="sr-soa-note-ref">')
                    .attr('data-sr-soa-note', marker)
                    .text('[' + marker + ']'));

            // The attribution that turns a justification into a record. Only a
            // deviation has one. It stays in the ROW rather than moving to the
            // appendix: who decided, and when, is what a reader checks while
            // scanning for unapproved deviations, and it is one short line.
            if (row.decided_by) {
                var attribution = L('ApplicabilityDecidedBy') + ': ' + row.decided_by;
                if (row.decided_at) { attribution += ' — ' + row.decided_at; }
                $justification.append($('<span class="sr-soa-attrib">').text(attribution));
            }
            // THE REASON LINE IS ATTRIBUTION ONLY FOR A DEVIATION, and printing
            // it for an applicable control repeated the cell it sits under.
            //
            // The two states put the reasons in different places, deliberately.
            // For a DEVIATION the cell is the narrative that justifies the
            // exclusion, and the taxonomy reason is a separate fact about how
            // the decision was classified -- attribution. For an APPLICABLE
            // control the reasons ARE the justification: soa_justification_for()
            // step 2 prints them as the cell, alone or joined to the control's
            // own prose. So the attribution line restated, verbatim, the first
            // clause of the sentence directly above it.
            //
            // CONDITIONED ON THE STATE, never on comparing the two strings. A
            // string comparison would break the moment a locale's
            // SoaJustifiedByInclusionReasons reorders its clauses, and it would
            // also suppress a genuinely different pair.
            if (row.reason && row.applicable !== 'applicable') {
                $justification.append($('<span class="sr-soa-attrib">').text(L('Reason') + ': ' + row.reason));
            }
            if (row.provider) {
                $justification.append($('<span class="sr-soa-attrib">').text(L('Provider') + ': ' + row.provider));
            }

            $row.append($justification);

            // ─────────────────────────────────────────────────────────────────
            // IMPLEMENTATION STATUS — the clause 6.1.3(d) verdict, on its own.
            // ─────────────────────────────────────────────────────────────────
            // A column of its own so it can be SCANNED: the pills line up with the
            // pills above and below, which is what makes "which controls are not
            // implemented" one finger down one column. See the heading note.
            var $impl = $('<td class="sr-soa-impl">');

            // THE VERDICT AS A PILL, the same shipped .sr-state-pill the
            // Applicability column uses -- so the two read as one system, and
            // the overdue mark has a quiet thing to be loud against.
            var $verdict = $('<span class="sr-soa-impl-status">')
                .append($('<span class="sr-state-pill">')
                    .addClass('sr-state-' + implementedFamily(row.implemented))
                    .attr('data-sr-implemented', row.implemented)
                    .text(implementedLabel(row.implemented)));

            if (row.implemented_overdue) {
                $verdict.append(overdueMark());
            }

            // THE REMEDIATION MARKER SITS ON THE VERDICT, not in the Evidence
            // column: it is a fact ABOUT the status, and a reader who has just read
            // "No" should find the pointer to the plan in the same glance.
            //
            // ON EVERY SoA, unconditionally: the risk-to-treatment traceability
            // in Appendix R is the whole reason the appendix exists.
            var remediation = remediationLines(row);

            if (remediation.length) {
                var rMarker = 'R' + (remediationNotes.length + 1);

                remediationNotes.push({
                    marker: rMarker,
                    ref: row.ref || (row.refs || []).join(', '),
                    name: row.control_short_name || row.control_name || '',
                    // slice(1) DROPS remediationLines()' first element, which is
                    // the literal "Remediation" label. The old sub-row shift()ed
                    // it off to use as its own heading; the appendix section has
                    // a heading of its own, so carrying it here would print
                    // "Remediation" again as the first line of every entry.
                    lines: remediation.slice(1)
                });

                $verdict.append($('<span class="sr-soa-note-ref">')
                    .attr('data-sr-soa-note', rMarker)
                    .text('[' + rMarker + ']'));
            }

            $impl.append($verdict);
            $row.append($impl);

            // ─────────────────────────────────────────────────────────────────
            // EVIDENCE — what an auditor can actually go and look at.
            // ─────────────────────────────────────────────────────────────────
            // A column of its own, and NOT a derivation of the status beside it:
            // the status is computed from tests alone, so a confirmed policy listed
            // here fed no part of it. Two columns is what stops a reader inferring
            // otherwise.
            //
            // NO STANDALONE SCHEDULE LINE. Each test's next date is on that test's
            // own bullet, which is the only place it can be unambiguous: a control
            // with three tests has three schedules, and one date above three
            // bullets cannot say which it belongs to.
            var $evidence = $('<td class="sr-soa-ev">');

            // THE EVIDENCE, OR WHICH KIND OF NOTHING. Never absent: the two
            // absences are opposites and one of them is a finding. Which one is
            // the SERVER'S answer (`evidence_absence`, derived by
            // build_soa_rows()) -- this file derives nothing.
            if (row.evidence_absence === 'gap') {
                $evidence.append($('<span class="sr-soa-ev-none">').append(gapMark()));
            } else if (row.evidence_absence === 'not_expected') {
                $evidence.append($('<span class="sr-soa-ev-none">').append(nilMark()));
            } else {
                // The KIND becomes a modifier class. The cell stacks a document
                // name, a test's verdict, an evidence requirement and the
                // artifacts that requirement names, and rendering four kinds of
                // fact in one weight is what made this a wall of text.
                // .text(), never .html(): every value here is user-authored, and
                // the requirement in particular is a WYSIWYG field the server has
                // already projected to plain lines.
                // A BULLETED LIST, one entry per piece of evidence, because that
                // is what it is: a set of independent items, not a paragraph.
                // Comma-joined or newline-stacked prose made a reader parse where
                // one item ended and the next began; bullets make the count
                // visible before any of it is read.
                //
                // THE BULLET CARRIES THE VERDICT. A tick for a passing test, a
                // cross for a failing one, a neutral dot for inconclusive, a
                // document glyph for design evidence -- comparable down a column
                // at a glance, which "Name — Pass (2026-07-02)" was not. The
                // result WORD is still printed beside it, so the glyph is a
                // second channel and never the only one (it survives a
                // monochrome print, and testResultGlyph() degrades an
                // unrecognised result to neutral rather than to a wrong tick).
                var $list = $('<ul class="sr-soa-evlist">');

                evidenceLines(row).forEach(function (line) {

                    var $item = $('<li class="sr-soa-evitem">')
                        .addClass('sr-soa-evitem--' + (line.glyph || 'unknown'))
                        .addClass('sr-soa-evitem--kind-' + line.kind);

                    // THE VERDICT FOR ASSISTIVE TECHNOLOGY, always, even where the
                    // glyph has replaced the visible word. Bootstrap's own
                    // .visually-hidden (already in the bundle) rather than a
                    // hand-rolled clip: it is the shipped helper and it is the one
                    // screen readers are known to read.
                    //
                    // FIRST in the item, so it is announced before the name --
                    // "Pass, Compliance Obligation Register Review" reads the way
                    // the glyph is scanned.
                    if (line.resultLabel) {
                        $item.append($('<span class="visually-hidden">').text(line.resultLabel + '. '));
                    }

                    if (line.text) {
                        $item.append($('<span class="sr-soa-evname">').text(line.text));
                    }

                    // The result and date, or a document's type. Quieter than the
                    // name it qualifies -- a reader scans names and glyphs, and
                    // reaches for the date only once something has caught them.
                    if (line.meta) {
                        $item.append($('<span class="sr-soa-evmeta">').text(line.meta));
                    }

                    $list.append($item);
                });

                $evidence.append($list);
            }

            $row.append($evidence);

            // NOTHING FOLLOWS EVIDENCE. See the note above riskLines()' old home
            // for where the risk traceability lives instead.

            $tbody.append($row);
        });

        $table.append($tbody);
        $card.append($scroll.append($table));

        // EVERY tbody tr IS A CONTROL AGAIN. The remediation sub-row used to
        // break that, and the control count is the one number this document is
        // reconciled by.
        appendNotes($card, 'sr-soa-appendix--just', L('SoaAppendixJustifications'),
            justificationNotes.map(function (n) {
                return { marker: n.marker, ref: n.ref, name: n.name, lines: [n.text] };
            }));

        // TWO APPENDICES, NOT THREE. An Appendix E briefly carried each test's
        // required-evidence wording; it existed only to hold the text a
        // per-test marker pointed at, and both went together. See the block
        // comment where requiredEvidenceMarker() used to be for why that claim
        // was not one this document is entitled to make.
        appendNotes($card, 'sr-soa-appendix--rem', L('SoaAppendixRemediation'), remediationNotes);

        return $card;
    }

    /**
     * Truncate a justification to what the row prints, breaking at the last space
     * inside the budget so no word is cut in half. The complete text is always in
     * the appendix, so this only ever removes what the reader can still reach.
     *
     * A CHARACTER BUDGET, not a sentence split: these strings are user- and
     * AI-authored with no guaranteed punctuation, and splitting on the first '.'
     * turns "approved by J. Smith" into two words.
     */
    function truncateForRow(text) {

        var JUSTIFICATION_ROW_BUDGET = 90;

        var value = String(text || '').replace(/\s+/g, ' ').trim();

        if (value.length <= JUSTIFICATION_ROW_BUDGET) {
            return value;
        }

        var cut = value.slice(0, JUSTIFICATION_ROW_BUDGET);
        var lastSpace = cut.lastIndexOf(' ');

        return (lastSpace > 40 ? cut.slice(0, lastSpace) : cut) + '…';
    }

    /**
     * One appendix: a heading and a numbered entry per note. Built only when
     * there is something in it, so a framework with no failing control prints no
     * empty "Remediation plans" heading.
     *
     * Rendered OUTSIDE the table's horizontal scroller (it is appended to the
     * card, not to $scroll), because these are full-width prose blocks and there
     * is nothing about them that should scroll sideways with a wide table.
     */
    function appendNotes($card, modifier, heading, notes) {

        if (!notes.length) {
            return;
        }

        var $section = $('<section class="sr-soa-appendix">').addClass(modifier);

        $section.append($('<h2 class="sr-soa-appendix-h">').text(heading));

        notes.forEach(function (note) {

            var $entry = $('<div class="sr-soa-appendix-entry">')
                .attr('data-sr-soa-note-id', note.marker);

            // The marker, then which control it belongs to -- a reader arriving
            // from "[A17]" needs to confirm they landed on the right control
            // before reading the paragraph.
            var title = note.marker + '. ' + [note.ref, note.name].filter(Boolean).join(' — ');
            $entry.append($('<span class="sr-soa-appendix-ref">').text(title));

            (note.lines || []).forEach(function (line) {
                // Same unplanned emphasis the sub-row carried: the control failed
                // and NOTHING is planned about it is the finding, not a detail.
                var unplanned = line === L('SoaRemediationUnplanned')
                    || line.indexOf('— ' + L('SoaRemediationUnplanned')) !== -1;

                $entry.append($('<span class="sr-soa-appendix-l">')
                    .toggleClass('is-unplanned', unplanned)
                    .text(line));
            });

            $section.append($entry);
        });

        $card.append($section);
    }

    /**
     * Sets the ground for whichever state is about to render.
     *
     * The LAUNCHER is an ordinary app screen and belongs on `.page-wrapper`'s
     * canvas grey, like every other redesigned page on this branch. The DOCUMENT
     * is a printable artefact and stays white -- and, being served chrome-free,
     * already carries `sr-soa--document` from the server, so this only matters
     * for the states the client swaps between.
     */
    function setGround(state) {
        $root.removeClass('sr-soa--document sr-soa--selector').addClass('sr-soa--' + state);
    }

    /**
     * The screen-only way back, for anyone who lands on a document cold -- from
     * a bookmark, or a link an auditor was sent. Without the app shell there is
     * otherwise no navigation on the page at all.
     *
     * Hidden in print (see the partial): a "back to" link is furniture, and this
     * document is meant to be printed.
     */
    function backToLauncher() {
        return $('<a class="sr-soa-back">')
            .attr('href', BASE_URL + '/reports/statement_of_applicability.php')
            .append($('<i class="fa fa-arrow-left" aria-hidden="true">'))
            .append(document.createTextNode(' ' + L('SoaBackToSelector')));
    }

    /** The whole document, or the reason there isn't one. */
    function renderDocument(data) {

        soa = data;

        setGround('document');
        $root.empty();
        $root.append(renderCover(data.cover));

        var $prompt = renderMissingFieldsPrompt(data.cover);
        if ($prompt) { $root.append($prompt); }

        // BEFORE THE TABLE, ALWAYS -- including on an empty framework, where the
        // reader still deserves to know what the document would have said. A key
        // that trails 1,534 rows is a key nobody scrolls back to.
        $root.append(renderLegend());

        if (!data.rows.length) {
            $root.append(emptyState('fa-list-check', L('SoaNoControls'), L('SoaNoControlsHint')));
        } else {
            $root.append(renderTable(data.rows));
        }

        // The only navigation on a chrome-free page. Screen-only; the partial
        // hides it in print, where a "back to" link is furniture on an artefact.
        $root.append($('<div class="sr-soa-back-row">').append(backToLauncher()));

        // ─────────────────────────────────────────────────────────────────────
        // THE PRINT ROUTE — raise the dialog once the document is really here.
        // ─────────────────────────────────────────────────────────────────────
        // AFTER RENDER, NEVER ON LOAD. This document is assembled from an XHR,
        // so a print() fired at DOMContentLoaded would capture an empty
        // container. It is called from the end of the one function that has
        // just finished building the whole artefact -- and only from the success
        // path, so a refusal or a failed load raises no dialog at all.
        //
        // WAITS FOR THE FONTS. The print stylesheet pins per-column widths, and
        // measuring them against a fallback face and then swapping in Nunito Sans
        // mid-dialog is how a column ends up a glyph short. document.fonts is
        // guarded because it is not in every browser this product supports; the
        // timeout fallback is the same "next frame" the promise would have given.
        if (autoPrint()) {
            if (window.document.fonts && window.document.fonts.ready) {
                window.document.fonts.ready.then(function () { window.print(); });
            } else {
                window.setTimeout(function () { window.print(); }, 0);
            }
        }
    }

    // -------------------------------------------------------------------------
    // The framework picker
    // -------------------------------------------------------------------------

    /**
     * Shown when the page was opened with no ?framework= -- which is what
     * happens when it is opened from the Reporting Hub, whose tiles are plain
     * links and cannot supply one.
     *
     * A PICKER, NOT AN ERROR, and not a cross-framework document. Applicability
     * is per-framework by design: the same control excluded from ISO 27001 is
     * not thereby excluded from PCI DSS, which is why it is stored per
     * (framework, control_id) and why the controls page withholds the
     * applicability column entirely under "All frameworks". There is no
     * "applicable in N of M frameworks" roll-up anywhere in the product, and
     * inventing one here would answer a question the data model does not.
     *
     * ACTIVE FRAMEWORKS ONLY (?status=1), matching what the endpoint will
     * accept -- offering an inactive framework in a picker whose every choice
     * leads to a 409 would be a menu of refusals.
     */
    function renderFrameworkPicker() {

        setGround('selector');
        $root.empty();

        var $panel = $('<div class="sr-soa-picker sr-table-card">');
        $panel.append($('<h1 class="sr-soa-title">').text(L('StatementOfApplicability')));
        $panel.append($('<h2 class="sr-soa-pick-label">').text(L('SoaChooseFramework')));
        $panel.append($('<p class="sr-soa-pick-hint">').text(L('SoaChooseFrameworkHint')));

        // The NATIVE select is the source of truth and stays in the DOM, so if
        // srSelectEnhance() never runs the control is still usable.
        var $select = $('<select class="form-select" id="sr-soa-framework">')
            .attr('aria-label', L('SoaChooseFramework'))
            .appendTo($panel);

        $root.append($panel);

        $.getJSON(BASE_URL + '/api/v2/governance/frameworks/rail?status=1')
            .done(function (res) {

                var frameworks = (res && res.data && res.data.rows) || [];

                if (!frameworks.length) {
                    // A user with `governance` and no frameworks gets told so,
                    // not handed an empty dropdown that looks broken.
                    $select.remove();
                    $panel.find('.sr-soa-pick-label, .sr-soa-pick-hint').remove();
                    $panel.append(emptyState('fa-folder-open', L('SoaNoFrameworks'), L('SoaNoFrameworksHint')));
                    return;
                }

                $('<option>', { value: '', text: L('SoaChooseFramework') }).appendTo($select);

                frameworks.forEach(function (framework) {
                    // `text:` -- framework names are user-authored (and encrypted
                    // at rest; they arrive decrypted, as text). Never concatenated
                    // into markup, and never through a widget's enableHTML.
                    //
                    // THE COUNT GOES ON AS DATA, never baked into the label.
                    // Appending "name · 51" is precisely what the chip exists to
                    // replace -- it blends into the words -- and an option that
                    // bakes it in silently renders with no chip at all.
                    //
                    // The number is the SERVER'S. `control_count` comes from
                    // get_framework_control_counts(), and it is the same number
                    // the cover will show: verified across all 20 frameworks on
                    // the development dataset that it equals
                    // count(controls_table_filtered_controls($id, [])), which is
                    // what build_soa_cover() counts. Re-deriving it here would
                    // reintroduce exactly the second computation this report
                    // exists to eliminate -- and it would be advertising a number
                    // the page the user lands on could contradict.
                    $('<option>', { value: String(framework.value), text: framework.name })
                        .attr('data-count', Number(framework.control_count || 0))
                        .appendTo($select);
                });

                // Choosing a framework ARMS the launcher rather than navigating.
                // The affordances live below the select because there is more
                // than one thing to do with a chosen framework -- open it now,
                // and (Task 18) export it -- and a select that navigated on
                // change could only ever offer the first.
                $select.on('change', function () {
                    renderLaunchActions($panel, $(this).val());
                });

                // ───────────────────────────────────────────────────────────
                // ARRIVING ALREADY ARMED (Task 8).
                // ───────────────────────────────────────────────────────────
                // A user who clicked Generate on a framework's rail row has
                // answered the framework question. Landing them on a blank
                // picker would ask it again, so the choice is applied here and
                // the launcher comes up ready to generate.
                //
                // MATCHED AGAINST THE OPTIONS THIS ENDPOINT JUST RETURNED,
                // never applied blind. That list is the authority on what may be
                // chosen (active frameworks only), so an id for a deleted or
                // deactivated framework selects nothing and leaves an ordinary
                // picker -- rather than arming three affordances that all point
                // at a document the API answers 409 for.
                //
                // BEFORE srSelectEnhance(), because that is what renders the
                // button's label from the native select's current value: setting
                // the value afterwards would leave the visible control reading
                // "Choose a framework" over a select that had already chosen
                // one. renderLaunchActions() is called directly rather than by
                // firing `change`, so this cannot be mistaken for -- or
                // intercepted as -- a choice the user made.
                var arrived = preselected();
                if (arrived > 0 && $select.find('option[value="' + arrived + '"]').length) {
                    $select.val(String(arrived));
                    renderLaunchActions($panel, String(arrived));
                }

                // SEARCHABLE because Josh asked for it. Twenty frameworks is a
                // dropdown, not the faceted sr-picker (design-system §5: tens of
                // rows is a menu, hundreds+ is a picker), but a catalogue that
                // grows past a screenful is tedious to scroll and SCF-derived
                // installs carry nested framework trees.
                srSelectEnhance($select, L('SoaChooseFramework'), true);
            })
            .fail(function () {
                $select.remove();
                $panel.find('.sr-soa-pick-label, .sr-soa-pick-hint').remove();
                $panel.append(emptyState('fa-triangle-exclamation', L('RequestFailed'), '', true));
            });
    }

    /**
     * The launcher's affordance row: what you can DO with the framework you
     * just chose.
     *
     * RENDERED ONLY ONCE A FRAMEWORK IS CHOSEN, and rebuilt on every change, so
     * there is never a control on screen that would act on nothing. Nothing here
     * is disabled or a placeholder -- a button that does nothing is worse than a
     * button that is not there yet.
     *
     * THE THREE LABELS ARE SYMMETRIC: "Open", "XLSX", "PDF". The row used to
     * read "Open in browser" / "Download as XLSX" / "PDF", which put a verb on
     * two of the three and left the third looking like an abbreviation of a
     * label somebody forgot to finish. The row's own context is the verb -- it
     * is what you can do with the framework you just chose -- so the words
     * carried nothing and cost a comparison. "PDF" is the short one on purpose:
     * above SOA_EXPORT_PDF_MAX_CONTROLS it opens a print view rather than
     * downloading a file, so a verb on it would be wrong half the time, and the
     * other two match the honest one rather than the other way round.
     *
     * "Open" is an ANCHOR with target="_blank", not window.open():
     * popup blockers eat programmatic opens, and a blocked Statement of
     * Applicability looks to the user like a broken report rather than like a
     * blocked popup. An anchor is a user-initiated navigation and is never
     * blocked. It points at the SAME URL a deep link uses, which is what makes
     * the two routes produce identical documents.
     *
     * ---------------------------------------------------------------------
     * THE TWO EXPORTS ARE BUILT ONLY WHERE THEY WOULD WORK.
     *
     * "XLSX" and the single "PDF" affordance exist only when the
     * Import/Export Extra is active. When it is not, this function CREATES
     * NOTHING for them -- no disabled button, no hidden one, no placeholder.
     * The nearest precedent in the codebase (includes/reporting.php:4006) emits
     * its download and print buttons unconditionally and then hides them with
     * `display: none`, which leaves controls in the DOM that answer 403 if
     * anyone reaches them; that half was deliberately not copied.
     *
     * THE PDF'S PRINT MECHANISM IS GATED WITH IT even though nothing
     * server-side generates that file. The PDF is an export, and an export
     * affordance that appeared on an instance with no Import/Export Extra --
     * beside a spreadsheet button that did not -- would make the row's one rule
     * ("these are the exports; you have the Extra or you do not") into two.
     * Nothing is lost that the customer had: "Open" is Core and
     * ungated, the print stylesheet applies to that tab, and Ctrl+P on it is the
     * same browser-rendered PDF.
     *
     * They are ANCHORS, for the same reason "Open" is one: a
     * browser-native navigation to a Content-Disposition response downloads the
     * file with no JS, no blob and no popup blocker in the way, and a
     * programmatic open of the print route would be eaten by a popup blocker.
     *
     * THE GENERATION LIVES IN THE EXTRA (export_soa_xlsx() / export_soa_pdf(),
     * extras/import-export/includes/soa_export.php); Core holds the endpoint,
     * and the PDF library itself is a Core dependency because a renderer is a
     * neutral capability while the function that uses it is the gated feature.
     *
     * HIDING IS NOT THE GATE. The endpoint re-checks the Extra itself, so a user
     * who keeps this page open across a deactivation, or who types the URL, gets
     * a 403 rather than a document.
     * ---------------------------------------------------------------------
     */

    /**
     * The two exports, SHOWN BUT LOCKED, when the Import/Export Extra is not
     * active.
     *
     * These used to be omitted from the DOM. That buried a paid capability from
     * the only person who would ever buy it: someone who cannot see that
     * SimpleRisk exports a Statement of Applicability has no reason to ask for
     * the Extra that does it. So the affordance stays, greyed, NAMED as locked,
     * and carrying one sentence about what is missing plus the single next step.
     *
     * Presentation is the shared .sr-locked* component
     * (scss/modules/_locked-affordance.scss) -- the same one the "+ Add
     * framework" chooser's Import-Export row uses, reached here through the same
     * server-side decision, so the two surfaces cannot drift into saying
     * different things about one Extra in one state.
     *
     * <button disabled> rather than an <a> with the href removed: a disabled
     * button is inert to click, to Enter, and to the accessibility tree in one
     * declaration, whereas a dead anchor is still focusable and still announces
     * as a link. The unlock link is a SIBLING of the button, never a descendant
     * of it -- a <button disabled> swallows pointer events for everything
     * inside it, which would turn the one actionable thing here into decoration.
     * That is the structural rule the component's own block comment states.
     */
    function lockedExports($actions, $panel) {

        // Resolved server-side; see the container in
        // reports/statement_of_applicability.php.
        var note     = $root.attr('data-sr-soa-unlock-note') || '';
        var linkText = $root.attr('data-sr-soa-unlock-link') || '';
        var href     = $root.attr('data-sr-soa-unlock-href') || '';
        var external = $root.attr('data-sr-soa-unlock-external') === '1';
        var state    = $root.attr('data-sr-soa-unlock-state') || '';
        var badge    = $root.attr('data-sr-soa-locked-badge') || '';

        [
            { cls: 'sr-soa-export-xlsx', kind: 'xlsx', icon: 'fa-file-excel', key: 'SoaXlsx' },
            { cls: 'sr-soa-export-pdf',  kind: 'pdf',  icon: 'fa-file-pdf',   key: 'SoaPdf'  }
        ].forEach(function (spec) {

            // data-sr-soa-export keeps its value in BOTH regimes so a test can
            // find the affordance the same way whether or not it is live, and
            // data-sr-locked-state names WHICH lock -- assertable without
            // reading copy Crowdin may translate.
            var $wrap = $('<span class="sr-locked">')
                .attr('data-sr-locked-state', state);

            var $btn = $('<button type="button" class="btn btn-outline-secondary sr-locked--btn">')
                .addClass(spec.cls)
                .attr({
                    disabled: 'disabled',
                    'aria-disabled': 'true',
                    'data-sr-soa-export': spec.kind
                })
                .append($('<i class="fa ' + spec.icon + '" aria-hidden="true">'))
                .append(document.createTextNode(' ' + L(spec.key)));

            if (badge) {
                $btn.append(
                    $('<span class="sr-locked-badge">')
                        .append($('<i class="fa fa-lock" aria-hidden="true">'))
                        .append(document.createTextNode(badge))
                );
            }

            $actions.append($wrap.append($btn));
        });

        // ONE SENTENCE FOR BOTH BUTTONS, BENEATH THE ROW -- not one per button.
        // Both exports are locked by the same Extra in the same state, so a
        // per-button note prints the identical sentence twice, side by side, and
        // the repetition reads as two different problems rather than one.
        //
        // The sentence itself is NOT optional: a greyed control with nothing to
        // say reads as a failed load rather than an unmet prerequisite, which is
        // the exact misreading this treatment exists to prevent. Only its
        // PLACEMENT differs from the dropdown-row consumer's.
        //
        // .sr-soa-actions-note rather than the component's own .sr-locked-note:
        // this row is centred (see _statement-of-applicability.scss), and that
        // class already carries the centred, width-capped, muted treatment a
        // sentence under this row needs, whereas .sr-locked-note is built for a
        // left-aligned menu row. Same component, surface-appropriate placement --
        // and no new SCSS, so the shipped bundle does not need rebuilding.
        if (note) {
            var $note = $('<div class="sr-soa-actions-note">')
                .attr('data-sr-soa-locked-note', state)
                .text(note);

            // No link in the admin_required state -- both destinations are
            // check_admin pages, so there is nowhere to usefully send a
            // non-admin. The sentence still explains the lock.
            if (linkText && href) {
                var $link = $('<a class="sr-locked-link">')
                    .attr('href', href)
                    .text(linkText);

                // Only the marketplace is external; opening an in-product admin
                // page in a new tab would strand the reader's place.
                if (external) {
                    $link.attr({ target: '_blank', rel: 'noopener noreferrer' });
                }

                $note.append(document.createTextNode(' ')).append($link);
            }

            $panel.append($actions).append($note);
            return true;
        }

        return false;
    }

    function renderLaunchActions($panel, frameworkId) {

        // TORN DOWN AND REBUILT, and `.sr-soa-actions-note` has to be in the list.
        // This function runs again on every framework change, and the note is built
        // by lockedExports() to carry the Import/Export unlock sentence -- omitting
        // it was a real defect once, appending another copy of that sentence on
        // every rebuild.
        $panel.find('.sr-soa-actions, .sr-soa-actions-note').remove();

        if (!frameworkId) { return; }

        var $actions = $('<div class="sr-soa-actions">');

        $('<a class="btn btn-submit sr-soa-open">')
            .attr({
                href: documentUrl(frameworkId),
                target: '_blank',
                // A new tab opened from an anchor keeps a handle on this page
                // via window.opener unless told otherwise.
                rel: 'noopener',
            })
            .append($('<i class="fa fa-up-right-from-square" aria-hidden="true">'))
            .append(document.createTextNode(' ' + L('SoaOpen')))
            .appendTo($actions);

        // BOTH EXPORTS ARE BUILT EITHER WAY. When the Extra is off they are built
        // LOCKED -- see lockedExports() below and the note on the container in
        // reports/statement_of_applicability.php for why omitting them was wrong.
        if (!canExport()) {
            // lockedExports() appends the row itself when it has a note to put
            // beneath it, so the note lands OUTSIDE the centred flex row rather
            // than as a third flex item wedged between the buttons.
            if (!lockedExports($actions, $panel)) {
                $panel.append($actions);
            }
            return;
        }

        {

            // NEUTRAL WEIGHT. $sr-important is spent on "Open", which
            // is what most people came to do; the exports use the same
            // btn-outline-secondary every other secondary action on this branch
            // uses (the controls toolbar's SoA button, the bulk bar).
            $('<a class="btn btn-outline-secondary sr-soa-export-xlsx">')
                .attr({
                    href: exportUrl(frameworkId, 'xlsx'),
                    'data-sr-soa-export': 'xlsx'
                })
                .append($('<i class="fa fa-file-excel" aria-hidden="true">'))
                .append(document.createTextNode(' ' + L('SoaXlsx')))
                .appendTo($actions);

            // ─────────────────────────────────────────────────────────────────
            // ONE PDF AFFORDANCE. THE SIZE PICKS THE MECHANISM, NOT THE BUTTON.
            // ─────────────────────────────────────────────────────────────────
            // There used to be two here: a Core browser-print affordance and a
            // gated server-side download, which meant a small framework offered
            // the user a choice between two renderings of one controlled
            // document. Two
            // people could then hand an auditor two different-looking PDFs of the
            // same Statement of Applicability, and which one they got was a
            // function of which button they clicked rather than of the data.
            //
            // So there is ONE, labelled 'SoaPdf' -- just "PDF" -- in BOTH
            // regimes. Below SOA_EXPORT_PDF_MAX_CONTROLS it points at the
            // server-side Dompdf writer; at or above it, at the browser print
            // route, which has no server-side memory ceiling because the browser
            // is rendering a page it has already rendered. Neither the label nor
            // the icon names the mechanism: signposting the difference would put
            // the choice back in front of a reader who has no basis to make it.
            //
            // HOW BIG THE CHOSEN FRAMEWORK IS -- the SERVER'S number, off the
            // option the frameworks rail endpoint populated. Not re-derived, and
            // not fetched again: it is the same `control_count` the cover will
            // print, which is what makes the decision here and the refusal in the
            // endpoint agree about the same document.
            //
            // MATCHED WITH A FILTER, not by interpolating the id into a selector
            // string. `frameworkId` is an option value this page put there and is
            // an integer in practice, but a value with a quote in it would make
            // the concatenated selector a syntax error jQuery throws on -- and
            // the launcher would then render nothing at all.
            var $option = $panel.find('#sr-soa-framework option').filter(function () {
                return $(this).attr('value') === String(frameworkId);
            });
            var prints = pdfPrints(parseInt($option.attr('data-count'), 10));

            // AN ANCHOR either way, for the same reason "Open" is one:
            // a Content-Disposition response downloads with no JS and no blob,
            // and a programmatic window.open() on the print route is eaten by
            // popup blockers -- a blocked Statement of Applicability looks like a
            // broken report rather than like a blocked popup.
            var $pdf = $('<a class="btn btn-outline-secondary sr-soa-export-pdf">')
                .attr({
                    href: prints ? printUrl(frameworkId)
                                 : exportUrl(frameworkId, 'pdf'),
                    'data-sr-soa-export': 'pdf',
                    // SOA_PDF_ROUTE_PRINT / SOA_PDF_ROUTE_DOWNLOAD. Not a styling
                    // hook and not read back by this script: it is how a test can
                    // see which mechanism was chosen without opening the file, and
                    // it is the only place the two differ visibly in the DOM.
                    'data-sr-soa-pdf-route': prints ? 'print' : 'download'
                })
                .append($('<i class="fa fa-file-pdf" aria-hidden="true">'))
                .append(document.createTextNode(' ' + L('SoaPdf')))
                .appendTo($actions);

            // ONLY THE PRINT ROUTE OPENS A TAB. It navigates to the document and
            // raises the browser's dialog on it, so it must not replace the
            // launcher; the download is an attachment response, which leaves the
            // current page where it is -- opening a tab that closes itself is a
            // flicker with no purpose. A new tab opened from an anchor keeps a
            // handle on this page via window.opener unless told otherwise.
            if (prints) {
                $pdf.attr({ target: '_blank', rel: 'noopener' });
            }
        }

        $panel.append($actions);
    }

    // -------------------------------------------------------------------------
    // sr-select (design-system §14b / §"Select dropdown")
    //
    // A SINGLE-select port. The shipped reference is srSelectEnhance() in
    // pages/compliance-define-tests.js; the sibling port in
    // pages/governance-frameworks.js deliberately DROPPED the single-select
    // branch, because every facet in that page's filter sheet is a
    // <select multiple>. This picker is single-select, so it is ported from the
    // reference rather than from the neighbour -- taking the neighbour's copy
    // would have meant re-adding the branch it removed on purpose.
    //
    // Two rules from §14b that the count requirement makes easy to break, and
    // which this port keeps:
    //   - the NATIVE select stays the source of truth and fires `change`, so the
    //     navigation handler above is bound to the native control;
    //   - option labels go in with .text(). No enableHTML, ever: it switches
    //     every label to .html() and turns a list of user-authored framework
    //     names into a stored-XSS sink.
    //
    // The one addition over the reference is the search field, which sr-select
    // does not ship. It is styled under `.sr-soa` in this page's own partial
    // rather than in the shared _sr-select.scss, so the ~10 other sr-select
    // consumers are untouched; promoting it into the component is a later call
    // to make on evidence, not a change to make in passing.
    // -------------------------------------------------------------------------

    function srSelectClose(api, refocus) {
        api.$menu.attr('hidden', 'hidden');
        api.$button.attr('aria-expanded', 'false');
        if (refocus) { api.$button.trigger('focus'); }
    }

    function srSelectActivate(api, $row) {
        if (!$row || !$row.length) { return; }
        api.$menu.find('.sr-select-option').removeClass('is-active');
        $row.addClass('is-active');
        if ($row[0].scrollIntoView) { $row[0].scrollIntoView({ block: 'nearest' }); }
    }

    /** Arrow keys skip disabled and search-hidden rows. */
    function srSelectMove(api, delta) {
        var $rows = api.$menu.find('.sr-select-option').filter(function () {
            return !this.disabled && $(this).css('display') !== 'none';
        });
        if (!$rows.length) { return; }
        var index = $rows.index(api.$menu.find('.sr-select-option.is-active'));
        var next = index + delta;
        if (next < 0) { next = $rows.length - 1; }
        if (next >= $rows.length) { next = 0; }
        srSelectActivate(api, $rows.eq(next));
    }

    function srSelectChoose($native, value) {
        var api = $native.data('srSelect');
        $native.val(value);
        srSelectRender($native);
        srSelectClose(api, true);
        // The native `change` is what the rest of the page listens to.
        $native.trigger('change');
    }

    function srSelectRender($native) {

        var api = $native.data('srSelect');
        if (!api) { return; }

        var value = $native.val();
        var selectedLabel = null;

        api.$menu.find('.sr-select-option').remove();

        $native.find('option').each(function () {

            var $option = $(this);
            var count = $option.attr('data-count');
            var isSelected = $option.attr('value') === value;

            var $row = $('<button>', {
                type: 'button',
                'class': 'sr-select-option',
                role: 'option',
                'data-value': $option.attr('value'),
                'aria-selected': isSelected ? 'true' : 'false',
            });

            $('<span>', { 'class': 'sr-select-text', text: $option.text() }).appendTo($row);

            // Zero-count options keep their `0` chip and stay selectable: the
            // absence IS information, and a framework with no controls mapped
            // into it is a real answer -- the report has an empty state that
            // says exactly that.
            if (count !== undefined && count !== '') {
                $('<span>', { 'class': 'sr-count-chip', text: Number(count).toLocaleString() }).appendTo($row);
            }

            if (isSelected) { selectedLabel = $option.text(); }

            $row.appendTo(api.$menu);
        });

        api.$button.find('.sr-select-value')
            .text(selectedLabel !== null ? selectedLabel : ($native.find('option').first().text() || api.placeholder));
    }

    function srSelectOpen(api) {
        api.$menu.removeAttr('hidden');
        api.$button.attr('aria-expanded', 'true');
        if (api.$search) {
            api.$search.val('');
            api.$menu.find('.sr-select-option').show();
            api.$search.trigger('focus');
        }
        var $selected = api.$menu.find('[aria-selected="true"]').first();
        srSelectActivate(api, $selected.length ? $selected : api.$menu.find('.sr-select-option:not(:disabled)').first());
    }

    function srSelectEnhance($native, placeholder, searchable) {

        if (!$native.length || $native.data('srSelect')) { return; }

        var $wrapper = $('<div>', { 'class': 'sr-select' });
        var $button = $('<button>', {
            type: 'button',
            'class': 'sr-select-button',
            'aria-haspopup': 'listbox',
            'aria-expanded': 'false',
            'aria-label': $native.attr('aria-label') || '',
        });
        $('<span>', { 'class': 'sr-select-value' }).appendTo($button);
        $('<i>', { 'class': 'fa fa-chevron-down sr-select-caret', 'aria-hidden': 'true' }).appendTo($button);

        var $menu = $('<div>', { 'class': 'sr-select-menu', role: 'listbox', tabindex: '-1' }).attr('hidden', 'hidden');

        // The native select stays in the DOM as the value; §14b's hidden-native
        // gotcha is that a `min-width` from any other rule beats a smaller
        // `width`, so _sr-select.scss clamps both on .sr-select-native.
        $native.addClass('sr-select-native').attr('tabindex', '-1').attr('aria-hidden', 'true');
        $native.after($wrapper);
        $wrapper.append($button).append($menu);

        var api = {
            $button: $button,
            $menu: $menu,
            $search: null,
            placeholder: placeholder || '',
        };
        $native.data('srSelect', api);

        if (searchable) {
            // `autocomplete` is set with .attr(), NOT in the property map.
            // jQuery's $('<tag>', {...}) treats any key matching a $.fn method
            // as a METHOD CALL, and jQuery UI defines $.fn.autocomplete -- so
            // `autocomplete: 'off'` there invokes the autocomplete WIDGET with
            // the argument 'off' and throws "cannot call methods on
            // autocomplete prior to initialization", aborting the rest of this
            // function. The symptom is not an obviously broken search box: the
            // button and menu are already in the DOM by then, so the control
            // renders and simply never opens.
            api.$search = $('<input>', {
                type: 'search',
                'class': 'form-control sr-select-search',
                placeholder: L('Search'),
                'aria-label': L('Search'),
            }).attr('autocomplete', 'off').prependTo($menu);

            api.$search.on('input', function () {
                var needle = $(this).val().toLowerCase();
                api.$menu.find('.sr-select-option').each(function () {
                    // Matched against the row's TEXT, so the search sees exactly
                    // what the reader sees.
                    var hit = $(this).find('.sr-select-text').text().toLowerCase().indexOf(needle) !== -1;
                    $(this).toggle(hit);
                });
                srSelectActivate(api, api.$menu.find('.sr-select-option:visible').first());
            });

            // Keys that drive the list must work while the caret is in the
            // search box, or searching would mean reaching back for the mouse.
            api.$search.on('keydown', function (e) {
                switch (e.key) {
                    case 'ArrowDown': e.preventDefault(); srSelectMove(api, 1); break;
                    case 'ArrowUp': e.preventDefault(); srSelectMove(api, -1); break;
                    case 'Enter':
                        e.preventDefault();
                        var $active = api.$menu.find('.sr-select-option.is-active');
                        if ($active.length && !$active.prop('disabled')) {
                            srSelectChoose($native, $active.attr('data-value'));
                        }
                        break;
                    case 'Escape': e.preventDefault(); srSelectClose(api, true); break;
                    default: break;
                }
            });
        }

        $button.on('click', function (e) {
            e.preventDefault();
            if ($menu.attr('hidden')) { srSelectOpen(api); } else { srSelectClose(api, false); }
        });

        $button.on('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                srSelectOpen(api);
                (api.$search || $menu).trigger('focus');
            }
        });

        $menu.on('click', '.sr-select-option', function () {
            srSelectChoose($native, $(this).attr('data-value'));
        });

        $menu.on('keydown', function (e) {
            switch (e.key) {
                case 'ArrowDown': e.preventDefault(); srSelectMove(api, 1); break;
                case 'ArrowUp': e.preventDefault(); srSelectMove(api, -1); break;
                case 'Home': e.preventDefault(); srSelectActivate(api, $menu.find('.sr-select-option:not(:disabled)').first()); break;
                case 'End': e.preventDefault(); srSelectActivate(api, $menu.find('.sr-select-option:not(:disabled)').last()); break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    var $active = $menu.find('.sr-select-option.is-active');
                    if ($active.length && !$active.prop('disabled')) {
                        srSelectChoose($native, $active.attr('data-value'));
                    }
                    break;
                case 'Escape': e.preventDefault(); srSelectClose(api, true); break;
                case 'Tab': srSelectClose(api, false); break;
                default: break;
            }
        });

        $(document).on('mousedown.srselect', function (e) {
            if (!$wrapper[0].contains(e.target) && !$menu.attr('hidden')) {
                srSelectClose(api, false);
            }
        });

        srSelectRender($native);
    }

    // -------------------------------------------------------------------------
    // Load
    // -------------------------------------------------------------------------

    /**
     * Turns the endpoint's machine-readable refusal token into an explanation.
     *
     * The TOKEN is what is switched on, never the status_message -- that string
     * is English composed on the server, and localizing it here is the whole
     * reason the endpoint sends a token alongside it.
     */
    function renderRefusal(error) {

        // A refusal is not a document, so it does not take the document's sheet
        // -- a white page wrapped around "this framework is inactive" would read
        // as an empty statement rather than as a refusal.
        setGround('selector');
        $root.empty();

        if (error === 'framework_inactive') {
            $root.append(emptyState('fa-ban', L('SoaFrameworkInactiveTitle'), L('SoaFrameworkInactiveBody')));
        } else if (error === 'framework_not_found') {
            $root.append(emptyState('fa-circle-question', L('SoaFrameworkNotFoundBody'), ''));
        } else {
            $root.append(emptyState('fa-triangle-exclamation', L('RequestFailed'), '', true));
        }

        // Every refusal reached this page with a framework named, which means it
        // was served chrome-free -- so it has no navigation of its own and needs
        // the way back. (`framework_required` cannot occur here: the server
        // sends a request with no framework to the launcher instead.)
        $root.append($('<div class="sr-soa-back-row">').append(backToLauncher()));
    }

    function load(framework) {

        // ONE PARAMETER. The screen and the two downloads are the same document by
        // construction -- there is no token they could disagree about.
        $.getJSON(BASE_URL + '/api/v2/governance/soa?framework=' + encodeURIComponent(framework))
            .done(function (res) {
                if (res && res.data && res.data.cover) {
                    renderDocument(res.data);
                    return;
                }
                renderRefusal('');
            })
            .fail(function (xhr) {
                var payload = xhr && xhr.responseJSON;
                renderRefusal((payload && payload.data && payload.data.error) || '');
            });
    }

    $(function () {

        $root = $('#sr-soa');

        if (!$root.length) { return; }

        var id = framework();

        // The SERVER decided which state this is, and said so in the container's
        // data attribute. No framework means it served the launcher; a framework
        // means it served the chrome-free document.
        if (id === null || id <= 0) {
            renderFrameworkPicker();
            return;
        }

        load(id);
    });

    // Exposed for the e2e suite, which needs the framework the document on
    // screen was built for. Read-only by design:
    // nothing outside this file may hand the page a different document than the
    // one the endpoint sent.
    window.SRSoa = {
        currentFramework: function () {
            var value = $('#sr-soa').attr('data-sr-soa-framework');
            return value ? parseInt(value, 10) : null;
        },
        // `currentVariant()` USED TO BE HERE, reporting which of the two documents
        // was on screen. There is one, so there is nothing to report; a spec that
        // wants to assert the shape of what was produced should read document()
        // and count the columns.
        document: function () { return soa; }
    };
})();
