<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// db_open() / db_close() / get_name_by_value() / write_debug_log() and $lang all
// live here. Required directly rather than relied on transitively, so this file
// keeps working from any entry point that includes it (CLAUDE.md, cross-file
// reachability).
require_once(realpath(__DIR__ . '/functions.php'));
// resolve_applicability() / get_framework_applicability_map() and the
// APPLICABILITY_* constants — the one place the "no row means applicable" rule
// lives.
require_once(realpath(__DIR__ . '/applicability.php'));
// controls_table_filtered_controls() — THE CONTROLS TABLE'S OWN PIPELINE. See
// the "ONE POPULATION" note below for why the SoA borrows it instead of
// querying `framework_control_mappings` itself. The direction of this require
// is deliberate and is the whole point: the SoA is defined as the controls
// page's population for one framework, annotated. Both that file and its own
// require chain (api/v2/includes/api.php, functions.php, governance.php,
// applicability.php) are pure function definitions with no top-level side
// effects, so pulling them in from a report page or a cron entry point is safe.
require_once(realpath(__DIR__ . '/../api/v2/includes/governance_controls.php'));
// last_result_state_family() / test_last_result_is_passing() /
// test_last_result_is_failing() / is_test_overdue() — the ALREADY-SHARED pure
// vocabulary for "what does this test_result mean" and "is this test late",
// which the Define Tests grid, its quick-filters and its KPI tiles all key off.
// soa_implemented_for() calls them rather than re-deriving the Pass/Fail check,
// on those functions' own instruction ("both must call this function rather
// than re-deriving the danger/Fail check independently") and on this file's
// RULE 2: the SoA must not become a second opinion about a fact the product
// already states elsewhere. Required directly per CLAUDE.md's cross-file
// reachability rule; the file and its own chain are pure function definitions.
require_once(realpath(__DIR__ . '/compliance_grid.php'));

/******************************************************************************
 * THE STATEMENT OF APPLICABILITY (spec §5.4a; ISO/IEC 27001:2022 clause 6.1.3(d))
 *
 * Clause 6.1.3(d) asks one thing of this document, for EVERY control in the
 * framework:
 *
 *   (1) whether it is applicable,
 *   (2) the justification for its inclusion or its exclusion, and
 *   (3) its implementation status.
 *
 * Everything in this file is one of those three columns, and two rules govern
 * all of it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RULE 1 — APPLICABLE IS THE DEFAULT, AND THE DEFAULT IS NEVER STORED.
 * ─────────────────────────────────────────────────────────────────────────────
 * `framework_control_applicability` holds only what someone had something to say
 * about: the two deviations, plus (since Task 4) an applicable control carrying
 * its OWN justification. A control with nothing recorded has NO ROW, and on a
 * 1,535-control framework that is almost all of them. So this file iterates the
 * framework's CONTROLS and asks each one what its applicability is — it never
 * iterates the applicability table. A report built
 * the other way round would list only the exclusions and silently omit every
 * applicable control, which on a 1,535-control framework with nine exclusions
 * is a nine-row SoA. Every state is resolved through resolve_applicability()
 * (includes/applicability.php), the single place that rule lives.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RULE 2 — ONE POPULATION. The SoA's numbers must equal the controls page's.
 * ─────────────────────────────────────────────────────────────────────────────
 * The rows here are resolved through controls_table_filtered_controls()
 * (api/v2/includes/governance_controls.php) — the SAME three calls
 * GET /governance/controls/table makes to build the grid, and the same ones
 * POST /governance/applicability and POST /governance/controls/bulk-delete
 * escalate through. Not a second query that ought to match it.
 *
 * That is not stylistic. A private `SELECT ... FROM framework_control_mappings
 * INNER JOIN framework_controls` was the obvious way to write this, and it is
 * WRONG, measurably: `framework_control_mappings` is unique on
 * (control_id, framework, reference_name), so one control legitimately carries
 * SEVERAL mapping rows in the same framework — that is how a cross-referenced
 * catalogue records its several citations of one control. Without the
 * `GROUP BY t1.id` that get_framework_controls_by_filter() applies, each of
 * those rows becomes another SoA line for the same control. Measured on the
 * development dataset (2026-07-27):
 *
 *     framework   mapping rows   distinct controls
 *        30           316               51            6.2x
 *        31           506              316
 *        32           611              250            2.4x
 *
 * — a Statement of Applicability claiming 316 controls for a framework whose
 * own controls page shows 51, with control 1484 listed twenty-five times. This
 * page has removed that class of count-vs-grid disagreement four times already
 * (Tasks 29, 37, 40, 15/54); on an SoA it is not an inconsistency but a
 * defective audit artefact, because the totals on the cover are the numbers an
 * auditor checks first. SoaReportTest::testTheReportAgreesWithTheControlsPage()
 * asserts the agreement rather than trusting this comment.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RULE 2b — ONE ROW PER CONTROL, BUT THE FRAMEWORK'S OWN REFERENCES ON IT.
 * ─────────────────────────────────────────────────────────────────────────────
 * Collapsing to one row per control is only half the answer, and shipping only
 * that half produced the SECOND defect on this document. `control_number` — the
 * control's own identifier — was printed in the Reference column, and on any
 * catalogue whose controls come from somewhere other than the framework being
 * stated (every SCF-derived one, which is most of what SimpleRisk ships) that
 * identifier is NOT the framework's reference:
 *
 *     framework   ISO 27002 (2022)
 *     printed     GOV-01                    <- the SCF control id
 *     correct     5.1, 5.4, 5.37            <- the ISO clauses it satisfies
 *
 * An auditor reading an ISO 27001 SoA looks for clause 5.1 and cannot find it.
 * On a NON-SCF framework the control's own number IS the framework reference,
 * which is exactly why this hid for as long as it did.
 *
 * The per-framework reference lives on `framework_control_mappings`
 * .`reference_name`, and it is one-to-MANY: on the development dataset ISO
 * 27002 carries 506 mappings over 316 controls, and NIST CSF v2.0 carries 611
 * over 250. So the reference dimension is AGGREGATED onto the control's single
 * row (soa_reference_map() below) exactly as the Evidence column already
 * aggregates document names — never re-joined, which would put RULE 2 back.
 *
 * Sorted NATURALLY, not lexically: "8.3, 8.5, 8.9, 8.12" is the order the
 * standard is written in and the order a reader scans for. GROUP_CONCAT would
 * have given "8.12, 8.25, 8.3" (real ISO 27002 data, control CFG-02), which is
 * why the aggregation is done in PHP over fetched rows rather than in SQL.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RENDERING CONTRACT — EVERYTHING HERE IS RAW PLAIN TEXT.
 * ─────────────────────────────────────────────────────────────────────────────
 * `narrative`, `provider`, `scope_statement` and
 * `default_inclusion_justification` are stored verbatim: unpurified,
 * unescaped, unencrypted (see update_framework_soa_fields(),
 * includes/governance.php). Control names and document names are likewise
 * returned raw. Consumers escape at their own sink — $escaper->escapeHtml() in
 * PHP, .text()/textContent in JS. Escaping here would double-encode a
 * justification reading "R&D < 500 users" at that sink.
 *
 * NULL IS NOT THE EMPTY STRING on the two framework fields. NULL means "never
 * asked for" and is what makes the report prompt for them; '' means a customer
 * deliberately cleared one. build_soa_cover() preserves the distinction rather
 * than coercing both to ''.
 *
 * AUTHORIZATION IS THE CALLER'S JOB, as in includes/governance.php and
 * includes/applicability.php. Nothing here checks a permission; the API layer
 * gates on `governance` before calling in.
 ******************************************************************************/

/**
 * The SIX values the Implemented column can take. DERIVED on every read from the
 * control's live tests and their latest results — never stored — so it cannot
 * drift out of sync with the control it describes.
 *
 * They are TOKENS, not labels. The report localizes them at its sink; returning
 * "Yes" or an em dash from here would bake English into a document that ships in
 * 39 languages.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY SIX AND NOT FOUR — the two unverified states are different findings.
 * ─────────────────────────────────────────────────────────────────────────────
 * `no_test_defined` and `never_run` are both "we cannot tell you", and they are
 * NOT the same admission. "We never decided how to verify this control" is a
 * GOVERNANCE failure; "we decided, and did not do it" is an OPERATIONAL one.
 * They have different owners and different remedies, and folding them into a
 * single "not verified" would hide which of the two an organisation actually
 * has — from the auditor, and from the organisation.
 *
 * Neither of them is `no`. This is the distinction the whole state set exists
 * for: the SoA is ATTESTED, so printing "not implemented" when the truth is
 * "not checked" is a false statement against yourself, exactly as printing
 * "implemented" without evidence is a false statement the other way. Auditors
 * treat an honest "not yet verified" far better than either error.
 *
 * TASK 6 RENDERS THESE and needs a $lang key per token plus one for the overdue
 * marker — six labels and a marker, all currently unwritten. Task 6 also owes
 * the document a LEGEND defining each, in every export
 * format: six states is more nuanced than the Yes/No/Partial an auditor
 * expects, and an undefined vocabulary invites the reader to guess
 * unfavourably. Nothing in this file emits a label.
 */
const SOA_IMPLEMENTED_YES             = 'yes';
const SOA_IMPLEMENTED_PARTIAL         = 'partial';
const SOA_IMPLEMENTED_NO              = 'no';
const SOA_IMPLEMENTED_NOT_APPLICABLE  = 'not_applicable';
const SOA_IMPLEMENTED_NO_TEST_DEFINED = 'no_test_defined';
const SOA_IMPLEMENTED_NEVER_RUN       = 'never_run';

/**
 * Structural fallback for the risk-linked justification template.
 *
 * Deliberately carries NO English prose. The real template is
 * $lang['SoaJustifiedByRiskAssessment'], which build_soa_rows() always passes in;
 * this default exists only so soa_justification_for() stays a pure function that
 * a test can call without standing up $lang. A caller that forgets the argument
 * gets the bare risk ids — degraded, but never an untranslatable English string
 * baked into a customer's SoA (CLAUDE.md, user-facing text).
 */
const SOA_RISK_TEMPLATE_FALLBACK = '{risks}';

/**
 * Structural fallback for an APPLICABLE control that stored BOTH inclusion
 * reasons and prose of its own (spec §4).
 *
 * Carries no English prose for the same reason SOA_RISK_TEMPLATE_FALLBACK does
 * not — only the two placeholders and the punctuation that joins them. The real
 * template is $lang['SoaJustifiedByInclusionReasons'], which build_soa_rows()
 * always passes in; a locale that separates clauses differently overrides the
 * separator there rather than inheriting an em dash from English typography.
 *
 * Only reached when BOTH halves are present: reasons alone print as the reason
 * list, and prose alone prints verbatim.
 */
const SOA_APPLICABLE_TEMPLATE_FALLBACK = '{reasons} — {narrative}';

/**
 * How several inclusion or exclusion reasons are joined into one cell.
 *
 * A SEMICOLON, not a comma: `control_applicability_reason`.`name` is
 * customer-authored free text and may itself contain a comma ("Legal, regulatory
 * or contractual"), which would make a comma-joined list unparseable by the
 * reader — the same reasoning soa_reference_map()'s subject join already
 * applies.
 */
const SOA_REASON_SEPARATOR = '; ';

/**
 * Whether the Name column prints the FRAMEWORK'S OWN TITLE for the control
 * (`framework_control_mappings`.`reference_subject`) in place of the SimpleRisk
 * control's `short_name`.
 *
 * OFF, DELIBERATELY, AND NOT BECAUSE THE FEATURE IS UNFINISHED. The rendering
 * below works and is kept whole behind this switch.
 *
 * WHY OFF. An SoA line is one CONTROL, not one clause — RULE 2, the invariant
 * that keeps the row count equal to the controls table's. A control routinely
 * answers several clauses of the same framework: GOV-01 satisfies ISO 27002 5.1,
 * 5.4 and 5.37, and those are three DIFFERENTLY TITLED requirements. There is no
 * single framework title that is correct for such a row — printing one clause's
 * title states something false about the other two, and printing all three
 * ("Policies for information security; Management responsibilities; Documented
 * operating procedures") is a Name cell that names nothing. The SimpleRisk
 * control's own name is the thing that legitimately spans every clause the row
 * aggregates, which is precisely why those clauses were mapped to one control.
 * For an aggregated row it is the MORE honest rendering, not a compromise.
 *
 * The Reference column still cites the framework's own clause numbers (that
 * change stands), so the row is traceable into the framework document; only the
 * Name stays the control's.
 *
 * WHAT WOULD TURN IT ON. A decision about how a multi-clause row should read —
 * most likely splitting the Name per clause, which means abandoning or amending
 * RULE 2 and accepting a row count that exceeds the control count. Until that
 * decision exists, `reference_subject` is CAPTURED (the AI enhancement job
 * populates it per clause) but never rendered: with this off, a populated column
 * produces byte-identical SoA output to a NULL one, in every sink.
 */
const SOA_RENDER_FRAMEWORK_SUBJECTS = false;

/**
 * WHAT THE EVIDENCE COLUMN SAYS WHEN IT HAS NOTHING TO LIST — and there are TWO
 * answers, because the two absences are semantic OPPOSITES (spec §3).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NOT_EXPECTED — the control is EXCLUDED from scope. No evidence is expected,
 * so the absence is CORRECT and the cell renders a neutral em dash.
 *
 * GAP — the control IS in scope, is possibly claimed implemented, and NOTHING
 * substantiates it: no confirmed document, no test that produced a verdict.
 * That absence is a FINDING, and it is the first thing an auditor circles.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Rendering both as an empty cell hides the second. That is the whole reason
 * this is a derived value with a name rather than a `if (empty($evidence))` in
 * each of the three sinks: an empty cell is what an unmaintained SoA looks like,
 * and the one thing this document must never do is make a gap look like a
 * formatting artefact.
 *
 * DERIVED ON EVERY READ, NEVER STORED — the same principle that makes deriving
 * Implemented correct. There is no `no_evidence_linked` column and there must
 * not be one: a control that gains its first policy tomorrow would leave a
 * stored flag asserting a gap that has been closed.
 */
const SOA_EVIDENCE_NOT_EXPECTED = 'not_expected';
const SOA_EVIDENCE_GAP          = 'gap';

/**
 * THE FOUR EVIDENCE GLYPHS, defined ONCE.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS CONSTANT CLOSES A REAL DEFECT. THE GLYPHS WERE DEFINED NOWHERE.
 * ─────────────────────────────────────────────────────────────────────────────
 * Each bullet in the Evidence column is marked by a symbol: a tick for a passing
 * test, a cross for a failing one, a neutral dot for inconclusive, a section sign
 * for a design document. They became LOAD-BEARING when the visible result word was
 * dropped from pass/fail bullets -- the word is now printed only for the neutral
 * bullet, where the glyph alone says nothing -- so an auditor meets four symbols
 * the document never defines. They existed only as `content:` values in
 * scss/modules/_statement-of-applicability.scss, which no reader of the PDF or the
 * spreadsheet can consult.
 *
 * SO THEY ARE DEFINED HERE, AND THE LEGEND PRINTS THEM. The characters are
 * mirrored in three places -- this constant, the `::before` rules in the partial,
 * and the page script's own copy -- because a stylesheet cannot read a PHP
 * constant and neither can JS. SoaLegendAndMarkersTest asserts all three agree.
 *
 * THE KEYS ARE testResultGlyph()'s RETURN VALUES ('pass' / 'fail' / 'unknown')
 * plus 'doc', so a sink maps an item straight to its mark with no second
 * vocabulary in between.
 *
 * WHY THESE FOUR CHARACTERS. Each is in DejaVu Sans (the font Dompdf embeds) and
 * in every locale's fallback stack, and each survives a monochrome printout, which
 * an emoji or a coloured dot does not. U+2717 rather than U+2715 or a multiplication
 * sign because it is the ballot X that pairs visually with U+2713's check.
 */
const SOA_EVIDENCE_GLYPHS = [
    'pass'    => "\u{2713}", // ✓  a test that recorded a pass
    'fail'    => "\u{2717}", // ✗  a test that recorded a fail
    'unknown' => "\u{2022}", // •  inconclusive, or a result vocabulary a sink does not recognise
    'doc'     => "\u{00A7}", // §  a confirmed design document, which has no verdict to report
];

/**
 * THE LEGEND — every value this document can print, and what each one means,
 * GROUPED BY THE COLUMN IT APPEARS IN.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * REQUIRED, NOT DECORATIVE. This is what makes the document shippable.
 * ─────────────────────────────────────────────────────────────────────────────
 * The vocabulary is more nuanced than the Yes / No / Partial an auditor expects to
 * find, and an undefined vocabulary invites the reader to guess -- unfavourably.
 * "Status unavailable" is the sharpest case: with nothing to explain it, it reads
 * as an admission rather than as what it is, a defect in the software. So the
 * legend is carried in EVERY format (screen, PDF, XLSX), and it is COMPLETE rather
 * than filtered to the values that happen to occur -- a controlled document's
 * vocabulary must not change shape between two generations of it, or last year's
 * SoA cannot be diffed against this year's.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * GROUPED BY COLUMN, BECAUSE A FLAT LIST WAS A LIST OF ELEVEN UNRELATED THINGS.
 * ─────────────────────────────────────────────────────────────────────────────
 * This was one flat sequence with implementation states, markers and evidence
 * absences interleaved, ordered "as the reader meets the values". That ordering
 * was a guess about reading order; the reader's actual question is never "what is
 * the fourth term in the key?" but "what does THIS cell mean?", and the cell they
 * are looking at is in a column. Each group now lists exactly the values its
 * column can print, so a reader looks up the column and finds a closed set.
 *
 * THE GROUP HEADING IS THE COLUMN HEADING -- the same lang key the table's own
 * <th> uses, not a legend-private one. That is the whole point of the grouping: a
 * reader matching the key to the table matches two identical words, and a column
 * renamed in one place cannot be named differently in the other.
 *
 * `heading`, `term` and `definition` are LANGUAGE KEYS, never prose -- this
 * constant is read by Core, by the Import/Export Extra's two writers and
 * (mirrored, and asserted equal by SoaLegendAndMarkersTest) by the page script,
 * and a literal here would bake English into a document that ships in 39
 * languages.
 *
 * `family` is a SEMANTIC family, not a class name: success / warning / danger /
 * info / neutral / nil. Each sink maps it to its own vocabulary -- the screen to
 * the shipped `.sr-state-*` pills, the PDF to its own small stylesheet, the XLSX
 * to nothing at all, because a cell is not markup. Putting a CSS class here would
 * make Core carry the stylesheet's private names.
 *
 * `glyph`, where present, is a key into SOA_EVIDENCE_GLYPHS: the entry's mark IS
 * the glyph, and the sinks render it beside the word rather than describing it.
 * That follows the rule the whole legend already follows -- SHOW the mark, do not
 * describe it -- because a key that describes a mark in words leaves the reader
 * matching prose to graphics.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE IMPLEMENTATION STATUS GROUP CONTAINS THREE THINGS THAT ARE NOT STATUSES,
 * AND THAT IS ACCEPTED RATHER THAN PAPERED OVER.
 * ─────────────────────────────────────────────────────────────────────────────
 * Only Yes / Partial / No are implementation statuses in clause 6.1.3(d)'s sense.
 * "No tests defined" and "Tests never run" report the state of VERIFICATION --
 * they exist because splitting "No" from "not verified" was a deliberate decision,
 * and collapsing them back would assert that an untested control does not work.
 * "Status unavailable" reports a software defect. All three sit under an
 * "Implementation Status" heading because that is the column they print in, and
 * the legend is where the imprecision is EXPLAINED rather than hidden: each of the
 * three definitions says plainly that implementation is not DEMONSTRATED, and
 * none of them implies the control is absent.
 */
const SOA_LEGEND_GROUPS = [
    [
        // The clause 6.1.3(d) decision itself. These three were never in the flat
        // legend at all, which meant an auditor met "Inherited" -- a word with no
        // obvious meaning outside SimpleRisk -- with nothing to look it up in.
        'heading' => 'Applicability',
        'entries' => [
            ['term' => 'ApplicabilityApplicable',    'definition' => 'SoaLegendApplicable',               'family' => 'success'],
            ['term' => 'ApplicabilityNotApplicable', 'definition' => 'SoaLegendApplicabilityNotApplicable', 'family' => 'neutral'],
            ['term' => 'ApplicabilityInherited',     'definition' => 'SoaLegendInherited',                'family' => 'info'],
        ],
    ],
    [
        'heading' => 'SoaImplemented',
        'entries' => [
            // The three verdicts.
            ['term' => 'Yes',                         'definition' => 'SoaLegendYes',           'family' => 'success'],
            ['term' => 'SoaImplementedPartial',       'definition' => 'SoaLegendPartial',       'family' => 'warning'],
            ['term' => 'No',                          'definition' => 'SoaLegendNo',            'family' => 'danger'],
            // The three non-verdicts. See the note above.
            ['term' => 'SoaImplementedNoTestDefined', 'definition' => 'SoaLegendNoTestDefined', 'family' => 'neutral'],
            ['term' => 'SoaImplementedNeverRun',      'definition' => 'SoaLegendNeverRun',      'family' => 'neutral'],
            ['term' => 'SoaImplementedUnknown',       'definition' => 'SoaLegendUnknown',       'family' => 'neutral'],
            // The excluded control's status, whose English value is the
            // abbreviation "N/A" -- distinct from the Applicability group's "Not
            // applicable", which is the DECISION. Two cells, two words, two
            // definitions.
            ['term' => 'NotApplicable',               'definition' => 'SoaLegendNotApplicable', 'family' => 'neutral'],
            // A caveat ON a verdict rather than a verdict, which is why it is last
            // in its group: the reader meets it attached to one of the values above.
            ['term' => 'Overdue',                     'definition' => 'SoaLegendOverdue',       'family' => 'danger'],
        ],
    ],
    [
        'heading' => 'Evidence',
        'entries' => [
            // THE FOUR BULLET GLYPHS. Documented here for the first time; see
            // SOA_EVIDENCE_GLYPHS for why they became load-bearing.
            ['term' => 'Pass',                      'definition' => 'SoaLegendEvidencePass',         'family' => 'success', 'glyph' => 'pass'],
            ['term' => 'Fail',                      'definition' => 'SoaLegendEvidenceFail',         'family' => 'danger',  'glyph' => 'fail'],
            ['term' => 'Inconclusive',              'definition' => 'SoaLegendEvidenceInconclusive', 'family' => 'neutral', 'glyph' => 'unknown'],
            ['term' => 'SoaEvidenceDesignDocument', 'definition' => 'SoaLegendEvidenceDocument',     'family' => 'neutral', 'glyph' => 'doc'],
            // And the two ABSENCES, which are semantic opposites: one is a finding
            // and one is correct. See the SOA_EVIDENCE_* constants above.
            ['term' => 'SoaNoEvidenceLinked',    'definition' => 'SoaLegendNoEvidence',          'family' => 'warning'],
            ['term' => 'SoaEvidenceNotExpected', 'definition' => 'SoaLegendEvidenceNotExpected', 'family' => 'nil'],
        ],
    ],
    [
        // Not a column -- a section. It is here because the marker that points into
        // it is printed in a column, and because "Unplanned" is a term the reader
        // meets and cannot look up anywhere else.
        'heading' => 'SoaAppendixRemediation',
        'entries' => [
            ['term' => 'SoaRemediationUnplanned', 'definition' => 'SoaLegendUnplanned', 'family' => 'danger'],
        ],
    ],
];

/******************************************************************************
 * THE DOCUMENT: SIX COLUMNS AND TWO APPENDICES.
 *
 *     Reference · Control Name · Applicability · Justification ·
 *     Implementation Status · Evidence
 *
 *     Appendix A  every control's justification, in full.
 *     Appendix R  the remediation plan behind every failing control.
 *
 * Those six are the ISO/IEC 27001:2022 clause 6.1.3(d) minimum plus evidence.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THE LONG CONTENT IS IN APPENDICES AND NOT IN THE TABLE.
 * ─────────────────────────────────────────────────────────────────────────────
 * Width is the constraint. Nine columns measures 1,321px against 965px of
 * landscape printable width, and the overflow does not wrap or scale -- it CLIPS,
 * with nothing on the paper to say it happened. A compliance document that drops a
 * column without saying so is worse than one that fails loudly.
 *
 * Moving the justifications and the remediation plans out of the register brings
 * the rendering to 957px inside 965px with zero clipped cells, and it costs
 * nothing: the appendices print the full text, so the document says everything it
 * said before. It also matches how the standard hands these over -- clause
 * 6.1.3(d) is the statement and 6.1.3(e) is the risk treatment plan, so a marker
 * into a numbered plan is closer to what an auditor expects than a paragraph
 * wedged into the register.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY IMPLEMENTATION STATUS AND EVIDENCE ARE TWO COLUMNS.
 * ─────────────────────────────────────────────────────────────────────────────
 *   - CLAUSE 6.1.3(d) NAMES implementation status. A named requirement of the
 *     standard this document exists to satisfy gets a column, not a line inside
 *     one.
 *   - THE SCAN IS THE POINT. An auditor's first pass is "which controls are not
 *     implemented", and that is a finger down one narrow column. A status sitting
 *     atop a variable-length evidence list does not line up with the status of the
 *     row above it, so there is nothing to run an eye down.
 *   - DOCUMENT EVIDENCE FEEDS NO PART OF THE STATUS, which is computed from tests
 *     alone (soa_implemented_for()). Merging them would imply a derivation that
 *     does not exist: a reader could reasonably infer the policy listed beneath
 *     "No" had been weighed in reaching it. It had not.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CONTROL OWNER AND REVIEW CADENCE ARE NOT COLUMNS, AND RISK LINKAGE IS NOT ONE
 * EITHER.
 * ─────────────────────────────────────────────────────────────────────────────
 * Control owner has no clause behind it and is one lookup away on the control
 * record. A review cadence belongs on the test it schedules -- a control with
 * three tests has three schedules, and one cell above three evidence lines cannot
 * say which it schedules -- so each test's next date rides on its own evidence
 * item (soa_evidence_for()).
 *
 * Risk linkage IS in the document, inside Appendix R: each entry names the risk
 * id beside the planning date, the mitigation owner and the percentage. That is
 * more traceability than a column of bare ids, and it is attached to the failure
 * it explains. soa_justification_for() also cites those ids in the Justification
 * cell for a control justified by its risk assessment.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE PAYLOAD IS BUILT SLIM, NOT BUILT WIDE AND NARROWED.
 * ─────────────────────────────────────────────────────────────────────────────
 * `tests` is not emitted (709 bytes an element, a full audit history no sink
 * prints), and neither is anything else the six columns and two appendices do not
 * render. build_soa_rows() emits the shape the sinks read, so nothing is ever
 * allocated in order to be dropped.
 *
 * WHAT THE MEMORY CEILING IS AND IS NOT. ~93% of the PDF's peak is Dompdf's
 * renderer rather than the payload, so shrinking the document does not move the
 * ceiling -- the answer is the pair of PDF routes below (server-side Dompdf under
 * SOA_EXPORT_PDF_MAX_CONTROLS, the browser's own engine at or above it). The slim
 * payload is worth having on its own terms; it is not the mitigation.
 ******************************************************************************/

/******************************************************************************
 * HOW BIG A FRAMEWORK THE SERVER-SIDE PDF IS OFFERED FOR — AND WHERE 500 CAME
 * FROM.
 *
 * The server-side PDF is Dompdf. Dompdf's peak memory grows with the number of
 * control rows, and on a host that disables ini_set() the process ceiling is
 * whatever `memory_limit` says — commonly 256M. Above some framework size the
 * export cannot complete, so above that size it is NOT OFFERED. An affordance
 * that is present and fails is worse than one that is absent: the reader of an
 * audit document should never be left wondering whether the file they did not
 * get was refused or was empty.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE MEASUREMENT (2026-07-29, dev dataset, Dompdf 3.1.6, with
 * export_soa_pdf()'s own ini_set('memory_limit') bump REMOVED so the numbers
 * are the real requirement rather than the raised one). Measured against a
 * nine-column table; the six-column document is CHEAPER than that, so the
 * threshold errs safe:
 *
 *     NIST CSF v2.0    250 controls    126 MB peak
 *     ISO 27002        316 controls    130 MB peak
 *     SCF 2026.2      1534 controls    412 MB peak
 *
 * A least-squares line through those three points is
 *
 *     peak(N) ≈ 57 MB + 0.232 MB × N
 *
 * so the break-even against a 256 MB limit is N ≈ 858, and against a 200 MB
 * working budget (leaving room for the rest of the request) N ≈ 616.
 *
 * ~93% of that peak is Dompdf's renderer, not the payload: build_soa() itself
 * peaks at 36 MB and the HTML string is 0.6 MB. That is why shrinking the
 * document does not move the ceiling, and why this is a threshold rather than an
 * optimisation.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY 500 AND NOT 858. 500 measures at ~173 MB — deliberately conservative:
 *
 *   - the fit is three points on ONE dataset. A framework whose controls carry
 *     more tests, evidence and remediation than this instance's do costs more
 *     per row than 0.232 MB;
 *   - the document has grown a legend, an evidence itemisation and an overdue
 *     marker since the chunk sizing was done, and the measurement above was taken
 *     against a NINE-column table rather than this six-column one, which is slack
 *     in the conservative direction and is left unspent;
 *   - the request needs headroom above the render for the session, the language
 *     file and the response itself.
 *
 * On the shipped catalogue the threshold is in a wide gap and not near any
 * edge: the two SCF catalogues are 1,534 and 1,468, and the next framework down
 * is ISO 27002 at 316. Nothing sits between 316 and 1,468, so no shipped
 * framework is close enough to the line for a re-fit to change its answer.
 *
 * TO RE-DERIVE: re-run the three-framework measurement above with the ini_set
 * bump removed, re-fit the line, and take a value comfortably below the
 * 256 MB break-even. Do not guess it.
 ******************************************************************************/
const SOA_EXPORT_PDF_MAX_CONTROLS = 500;

/****************************************************
 * FUNCTION: SOA PDF EXPORT SUPPORTED               *
 ****************************************************
 * Whether a framework of this size may be exported through the server-side PDF
 * writer.
 *
 * ONE DECISION, THREE CONSUMERS. soa_pdf_routes() below composes it into the
 * launcher's answer, the export endpoint refuses through that same composition,
 * and PHPUnit consults both directly. A second copy of the comparison in the
 * client would be a second place for the number to drift.
 *
 * NOT A PERMISSION AND NOT A GATE ON THE DOCUMENT. The report itself, the
 * browser print route and the spreadsheet export are all unaffected by size —
 * this is about one renderer's memory ceiling and nothing else.
 *
 * A NEGATIVE OR ZERO COUNT IS SUPPORTED. "No controls" is a legitimate document
 * (the report has an empty state that says exactly that) and costs Dompdf
 * nothing; refusing it would be refusing the cheapest possible export.
 *
 * Pure: no DB, no globals, no output.
 */
function soa_pdf_export_supported(int $control_count): bool {

    return $control_count <= SOA_EXPORT_PDF_MAX_CONTROLS;
}

/*******************************************************************************
 * THE TWO WAYS THIS PRODUCT TURNS A STATEMENT OF APPLICABILITY INTO A PDF.
 *
 * `download` is the server-side Dompdf writer behind
 * GET /api/v2/governance/soa/export?format=pdf.
 * `print` is the browser's own engine, raised on the chrome-free document by
 * reports/statement_of_applicability.php?...&print=1.
 *
 * These are MECHANISMS, not options. Nothing in the product asks a user to pick
 * between them and no label names either one; the tokens exist so that one
 * decision function can say which mechanism a given document gets, and so the
 * launcher can stamp its answer where a test can read it
 * (`data-sr-soa-pdf-route`). The client mirrors the two spellings verbatim.
 ******************************************************************************/
const SOA_PDF_ROUTE_DOWNLOAD = 'download';
const SOA_PDF_ROUTE_PRINT    = 'print';

/****************************************************
 * FUNCTION: SOA PDF ROUTES                         *
 ****************************************************
 * Every PDF affordance the launcher offers for one document — which is at most
 * one, and exactly one whenever the customer can export at all.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THE ANSWER IS A LIST WITH ONE ELEMENT IN IT
 * ─────────────────────────────────────────────────────────────────────────────
 * A Statement of Applicability is a CONTROLLED DOCUMENT. Offering two PDF
 * buttons means two people can hand an auditor two different-looking PDFs of the
 * same statement, and which one an auditor is holding becomes a function of
 * which button somebody happened to click rather than of the data. So the
 * product offers ONE, labelled 'SoaPdf', and the framework's size picks the
 * mechanism behind it.
 *
 * Returning the SET rather than a single token is what makes "exactly one"
 * something a test can assert directly, instead of inferring it from two
 * independent branch tests that would both still pass if a size window rendered
 * both affordances or neither.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE TWO INPUTS, AND WHY THE EXTRA IS ONE OF THEM
 * ─────────────────────────────────────────────────────────────────────────────
 * The PDF is an EXPORT, and every export affordance on this launcher is absent
 * entirely without the Import/Export Extra — not disabled, not hidden, not
 * built. That is why an instance without the Extra gets an empty list here, the
 * print mechanism included: a "PDF" button that behaved like the spreadsheet
 * button on one instance and unlike it on another would be a worse answer than
 * a consistent absence. Nothing is lost that the customer had: "Open"
 * is Core and ungated, the print stylesheet applies to it, and Ctrl+P on that
 * tab is the same browser-rendered PDF.
 *
 * TWO CONSUMERS, ONE RULE. The launcher composes this in JS from the two answers
 * the report page stamps into the DOM (`data-sr-soa-can-export` and
 * `data-sr-soa-pdf-max`); api_v2_governance_soa_export() calls it directly and
 * refuses `format=pdf` whenever the download is not the route it names. The 409
 * is therefore not a second, parallel size rule — it is this rule, read
 * server-side.
 *
 * Pure: no DB, no globals, no output.
 *
 * @param bool $import_export_active Whether the Import/Export Extra is active,
 *                                   i.e. import_export_extra().
 * @param int  $control_count        The document's own row count.
 *
 * @return string[] Zero or one of SOA_PDF_ROUTE_DOWNLOAD / SOA_PDF_ROUTE_PRINT.
 */
function soa_pdf_routes(bool $import_export_active, int $control_count): array {

    if (!$import_export_active) {

        return [];
    }

    return [
        soa_pdf_export_supported($control_count)
            ? SOA_PDF_ROUTE_DOWNLOAD
            : SOA_PDF_ROUTE_PRINT
    ];
}

/******************************************************************************
 * THE EVIDENCE COLUMN MUST NEVER FALL BACK TO THE FLAT `evidence` STRING ALONE.
 *
 * Worth stating where a future reader will look for it, because the failure is
 * silent and the fix is not obvious from the field names.
 *
 * `evidence` is the confirmed DOCUMENTS, comma-joined -- design evidence only. A
 * control substantiated solely by a passing quarterly test therefore has an EMPTY
 * `evidence` string and a non-empty `evidence_items`. Any sink that reads the flat
 * string when the itemisation looks absent will print "No evidence linked" against
 * a control the organisation actively tests: a FABRICATED FINDING in a document
 * the customer attests to, which is the class of defect this whole file is shaped
 * to prevent.
 *
 * build_soa_rows() emits `evidence_items` on every row, so the situation cannot
 * arise from this side. The guard that matters is in the sinks: read the items,
 * and treat the flat string only as a last resort for a hand-assembled array.
 ******************************************************************************/

/****************************************************
 * FUNCTION: SOA IMPLEMENTED FAMILY                 *
 ****************************************************
 * The semantic family one implementation token belongs to, so the three sinks
 * colour the same verdict the same way.
 *
 * NEUTRAL FOR THE THREE NON-VERDICTS, deliberately. `no_test_defined`,
 * `never_run` and the unknown fallback are not bad news about the CONTROL — they
 * are the absence of news — and painting them the same red as a confirmed
 * failure would state something the derivation refused to state. They are
 * findings, and the document says so in the legend and in the Evidence column;
 * the colour is not where that argument is made.
 *
 * Pure: no DB, no globals, no output.
 */
function soa_implemented_family(string $token): string {

    switch ($token) {
        case SOA_IMPLEMENTED_YES:     return 'success';
        case SOA_IMPLEMENTED_PARTIAL: return 'warning';
        case SOA_IMPLEMENTED_NO:      return 'danger';
        default:                      return 'neutral';
    }
}

/****************************************************
 * FUNCTION: SOA EVIDENCE ABSENCE                   *
 ****************************************************
 * Which of the two absences (if either) this control's Evidence cell is looking
 * at — see the SOA_EVIDENCE_* constants for why there are two.
 *
 * READS `evidence_items`, NOT the flat `evidence` string. The string is the
 * confirmed DOCUMENTS, comma-joined; the itemised list is documents AND the
 * tests that produced a verdict. A control substantiated only by a passing test
 * has an empty string and is NOT a gap, and deciding from the string would print
 * "No evidence linked" against a control the organisation tests every quarter —
 * a fabricated finding in a document the customer attests to.
 *
 * AN ABSENT `evidence_items` KEY IS TREATED AS NO ITEMS, which is the truthful
 * reading for a row that carries no evidence data at all.
 *
 * THE FLAT STRING IS STILL BELIEVED WHEN THERE IS NO LIST. A row carrying
 * `evidence` = "Information Security Policy" and no `evidence_items` at all is
 * one built by hand, by an older instance, or by a projection that dropped the
 * itemisation — and it is plainly not a control with nothing behind it. Reading
 * the missing list as a gap there would print a fabricated finding, which is the
 * same class of error as reading a shared test's absent scalar FK as "no test
 * defined". The list is preferred where it exists BECAUSE it is the larger
 * truth, not because the string is untrustworthy.
 *
 * IF A LEANER PAYLOAD IS EVER WANTED, this derived answer has to travel with it.
 * Dropping `evidence_items` for size is safe only while the flat `evidence` string
 * survives — and that string LOSES the test half, so a row substantiated solely by
 * a passing test would read as a gap. Carry `evidence_absence` alongside rather
 * than relying on the fallback.
 *
 * Pure: no DB, no globals, no output.
 *
 * @param array $row Needs `applicable` (or `applicability`), and `evidence_items`
 *                   or `evidence`.
 * @return string '' when there IS evidence, else SOA_EVIDENCE_NOT_EXPECTED or
 *                SOA_EVIDENCE_GAP.
 */
function soa_evidence_absence(array $row): string {

    $items = $row['evidence_items'] ?? [];

    if (is_array($items) && !empty($items)) {
        return '';
    }

    if (trim((string)($row['evidence'] ?? '')) !== '') {
        return '';
    }

    // `applicable` is the row builder's key; `applicability` is the derivation
    // array's. Both are accepted so this predicate can be called from either
    // side without a caller having to translate.
    $state = (string)($row['applicable'] ?? $row['applicability'] ?? APPLICABILITY_DEFAULT_STATE);

    // ONLY an EXCLUDED control expects nothing. An INHERITED control is in
    // scope — a provider performs it — so an absence of evidence for it is the
    // same finding it would be for any other applicable control, and softening
    // it would let "somebody else does it" mean "nobody checked".
    return $state === 'not_applicable' ? SOA_EVIDENCE_NOT_EXPECTED : SOA_EVIDENCE_GAP;
}

/****************************************************
 * FUNCTION: SOA JUSTIFICATION FOR                  *
 ****************************************************
 * Clause 6.1.3(d)'s second requirement: the justification for this control's
 * inclusion or exclusion. It resolves in FOUR steps, and THE COLUMN IS NEVER
 * BLANK — a blank justification cell is the single most common finding against a
 * hand-maintained SoA.
 *
 * That claim used to be false, and it is worth saying how, because the shape of
 * the mistake is general: every step here was individually correct, and the
 * guarantee lived in the gap between them. Step 1 cannot be empty
 * (assert_applicability_narrative() refuses the write); step 2 only runs when
 * there are risks to name; and step 3 was `?? ''` on a nullable column that
 * nothing seeded, nothing required and nothing backfilled — NULL on 19 of the 20
 * frameworks on the development instance. So the majority case printed nothing,
 * under a docblock asserting it could not. Task 67 closed it at the source
 * (soa_framework_default_justification() now falls back to a localized system
 * sentence, the create form seeds the field, and a migration backfills the rows
 * that predate it), which is why the invariant is now a property of the code
 * rather than of the data — and why the three steps below are exhaustive.
 *
 *   1. A DEVIATION carries its own narrative. assert_applicability_narrative()
 *      (includes/applicability.php) makes that narrative mandatory at write time,
 *      so an unjustified exclusion is impossible by construction — this function
 *      does not have to detect one.
 *
 *   2. An APPLICABLE control WITH ITS OWN STORED JUSTIFICATION uses it (spec §4,
 *      added by Task 4). Before that step existed, the only per-control variation
 *      an applicable control could show was risk linkage, so every other
 *      applicable control printed the same boilerplate — and 90 identical
 *      justifications teach an auditor nothing except to ask whether anyone
 *      considered each control at all.
 *
 *      The stored answer is inclusion reason(s) from the taxonomy, freeform
 *      prose, or BOTH, and all three are complete answers. Both halves print
 *      when both exist: the taxonomy gives consistency and filterability, the
 *      prose carries the specifics an auditor actually reads, and dropping
 *      either would discard something the user deliberately recorded.
 *
 *      IT OUTRANKS THE RISK TEMPLATE deliberately. A control can have linked
 *      risks AND a stored justification; the stored one is what a person wrote
 *      about this control, and the generated sentence is what SimpleRisk can say
 *      about any control. The specific answer wins.
 *
 *   3. An APPLICABLE control with LINKED RISKS is justified by those risks. This
 *      is the reason an SoA built inside SimpleRisk beats the spreadsheet it
 *      replaces: "we included this control because risk 1042 required it" is the
 *      answer ISO actually wants, and it is already recorded in
 *      `mitigation_to_controls` — nobody has to retype it, and it cannot go stale.
 *
 *   4. Otherwise the FRAMEWORK'S default inclusion justification fills the cell
 *      (spec §5.4a). Inclusion is the default state, so demanding a per-control
 *      rationale for it would mean 1,535 identical fields nobody would fill in.
 *      Non-empty by the time it gets here: soa_framework_default_justification()
 *      substitutes the localized system sentence for a framework that has none.
 *
 * WHITESPACE AND NULL ARE NOT A STORED JUSTIFICATION. `narrative` is nullable for
 * exactly the applicable case (an inclusion justified by its reasons alone), so
 * step 2 tests the TRIMMED value: a narrative of spaces would otherwise win the
 * cell and print as blank, which is the finding this whole function exists to
 * prevent.
 *
 * Pure: no DB, no globals, no output. STILL PURE after Task 67 and Task 4 — every
 * localized string is passed in, so this remains callable from a test with no
 * $lang and no database, and a caller that genuinely wants "" (a unit test
 * pinning step 4's passthrough) still gets it.
 *
 * @param array  $row               Needs `applicability` (a resolve_applicability()
 *                                  result); for the applicable path `reason_names`,
 *                                  `narrative` and `risk_ids`; for a deviation,
 *                                  `narrative`.
 * @param string $framework_default The framework's default inclusion justification,
 *                                  already defaulted by the caller.
 * @param string $risk_template     Localized template containing `{risks}`. See
 *                                  SOA_RISK_TEMPLATE_FALLBACK for why it has a default.
 * @param string $applicable_template Localized template containing `{reasons}` and
 *                                  `{narrative}`, used only when an applicable
 *                                  control stored both.
 */
function soa_justification_for(array $row, string $framework_default,
                               string $risk_template = SOA_RISK_TEMPLATE_FALLBACK,
                               string $applicable_template = SOA_APPLICABLE_TEMPLATE_FALLBACK): string {

    $state = (string)($row['applicability'] ?? APPLICABILITY_DEFAULT_STATE);

    if ($state !== APPLICABILITY_DEFAULT_STATE) {
        // The deviation's own narrative, verbatim. Not defaulted to the
        // framework's inclusion justification when empty: that string explains
        // why controls are INCLUDED, and printing it against an exclusion would
        // state the opposite of what happened. It cannot be empty anyway —
        // assert_applicability_narrative() refuses to store a deviation without one.
        return (string)($row['narrative'] ?? '');
    }

    // STEP 2 — the applicable control's own stored justification.
    $reasons = array_values(array_filter(
        array_map(static fn($name) => trim((string)$name), (array)($row['reason_names'] ?? [])),
        static fn($name) => $name !== ''
    ));

    $narrative = trim((string)($row['narrative'] ?? ''));

    if (!empty($reasons) && $narrative !== '') {
        return str_replace(
            ['{reasons}', '{narrative}'],
            [implode(SOA_REASON_SEPARATOR, $reasons), $narrative],
            $applicable_template
        );
    }

    if (!empty($reasons)) {
        return implode(SOA_REASON_SEPARATOR, $reasons);
    }

    if ($narrative !== '') {
        return $narrative;
    }

    $risk_ids = array_values(array_unique(array_filter(
        array_map('intval', (array)($row['risk_ids'] ?? [])),
        static fn($id) => $id > 0
    )));

    if (!empty($risk_ids)) {
        sort($risk_ids);
        return str_replace('{risks}', implode(', ', $risk_ids), $risk_template);
    }

    return $framework_default;
}

/******************************************************************************
 * THE IMPLEMENTATION-STATUS INPUT CONTRACT — `$row['tests']`
 *
 * THE ONE INPUT the three functions below read. Everything else on the row is
 * ignored by them, deliberately (see soa_implemented_for()).
 *
 * `$row['tests']` is a LIST (0-indexed array) of associative arrays, one per
 * `framework_control_tests` row belonging to this control — retired ones
 * INCLUDED, because the filtering is this file's job and not the query's. The
 * key may be ABSENT or an empty array; that is a supported input meaning "this
 * control has no tests", and it yields SOA_IMPLEMENTED_NO_TEST_DEFINED. Any row
 * shape that omits it therefore still renders a truthful SoA rather than
 * erroring.
 *
 * REQUIRED KEYS PER TEST — exactly three, all of them columns that already exist:
 *
 *   'retired_at'  ?string  `framework_control_tests`.`retired_at` (DATETIME),
 *                          verbatim or NULL. A non-empty, non-zero value means
 *                          the test is RETIRED and is dropped from the
 *                          derivation entirely.
 *
 *   'next_date'   ?string  `framework_control_tests`.`next_date` (DATE,
 *                          'Y-m-d'). The customer's own next scheduled window —
 *                          the ONLY currency signal, see the note in
 *                          soa_implemented_is_overdue(). NULL, '' or a zero date
 *                          means "no next window scheduled", never "overdue".
 *
 *   'test_result' ?string  THE LATEST RECORDED RESULT for this test, verbatim
 *                          from `framework_control_test_results`.`test_result`
 *                          (VARCHAR(50); the live vocabulary is 'Pass', 'Fail'
 *                          and 'Inconclusive'). NULL or '' means NO RESULT HAS
 *                          EVER BEEN RECORDED.
 *
 * "LATEST RECORDED RESULT" IS NOT "THE NEWEST AUDIT". An audit that has been
 * initiated but not yet resulted stores the EMPTY STRING, and 351 such rows
 * exist on the development instance. Taking the newest audit regardless would
 * report a test that passed last quarter as never run, the exact defect
 * get_tests_last_results() (includes/compliance_grid.php) was fixed for. So the
 * supplier must resolve it the way that function already does:
 *
 *     framework_control_test_audits.test_id = <test>
 *       INNER JOIN framework_control_test_results ON test_audit_id = audits.id
 *                  AND test_result IS NOT NULL AND test_result <> ''
 *     newest by audits.created_at, highest audits.id breaking the tie
 *
 * — i.e. the newest audit that ACTUALLY RECORDED a result, skipping in-flight
 * ones. get_tests_last_results() returns exactly `[test_id => ['result' => ?string,
 * 'date' => ?string]]` and seeds every requested id, so the supplier should CALL
 * IT rather than write that NOT EXISTS a second time; 'result' is this contract's
 * 'test_result'.
 *
 * EXTRA KEYS ARE IGNORED, NOT REJECTED. The supplier is expected to carry the
 * richer per-test shape the Evidence column and Appendix R need — `id`, `name`,
 * `last_date`, `next_date`, `summary`, `result_id`, the `schedule_type` /
 * `cadence_unit` / `cadence_interval` / `cadence_anchor_date` quartet — on these
 * same elements. One tests[] array serves every consumer; nothing here needs a
 * second projection.
 *
 * TYPES ARE FORGIVING BY DESIGN. Values are read as strings and trimmed, so a
 * PDO fetch (everything a string) and a hand-built fixture (real nulls) behave
 * identically. That is what lets the exhaustive derivation tests be pure array
 * literals with no database at all.
 ******************************************************************************/

/****************************************************
 * FUNCTION: SOA LIVE TESTS                         *
 ****************************************************
 * The control's tests with the RETIRED ones removed — the population every
 * implementation-status answer is computed over.
 *
 * `framework_control_tests`.`retired_at` soft-retires a test, and honouring it
 * here is not tidiness. Ignoring it breaks the derivation in both directions at
 * once: an old failure the organisation deliberately retired would drag its
 * control to Partial forever, and retiring the last remaining test would leave
 * the control's stale verdict standing rather than admitting it is no longer
 * verified. A control whose only tests are retired is genuinely unverified, and
 * that has to be a DELIBERATE outcome rather than an accident of the query.
 *
 * A zero datetime counts as NOT retired: it is a sentinel for "no value", and
 * reading it as a retirement would silently delete a live test from an audit
 * document.
 *
 * Pure: no DB, no globals, no output.
 *
 * @return array<int, array> the elements of $row['tests'] that are still live
 */
function soa_live_tests(array $row): array {

    $tests = $row['tests'] ?? [];

    if (!is_array($tests)) {
        return [];
    }

    $live = [];

    foreach ($tests as $test) {

        if (!is_array($test)) {
            continue;
        }

        $retired_at = trim((string)($test['retired_at'] ?? ''));

        if ($retired_at === '' || strpos($retired_at, '0000-00-00') === 0) {
            $live[] = $test;
        }
    }

    return $live;
}

/****************************************************
 * FUNCTION: SOA TEST LATEST RESULT                 *
 ****************************************************
 * One test's latest recorded result, or NULL when nothing has ever been
 * recorded.
 *
 * THE EMPTY STRING IS NOT A RESULT. `framework_control_test_results`.`test_result`
 * is a NOT NULL VARCHAR, so an audit that was opened and never resulted stores
 * '' — 351 such rows on the development instance. Treating that as a result
 * would let "we started looking" render as a verdict.
 *
 * Pure: no DB, no globals, no output.
 */
function soa_test_latest_result(array $test): ?string {

    $result = trim((string)($test['test_result'] ?? ''));

    return $result === '' ? null : $result;
}

/****************************************************
 * FUNCTION: SOA TEST IS OVERDUE                    *
 ****************************************************
 * Whether one test has slipped past ITS OWN next scheduled window.
 *
 * Delegates the comparison to is_test_overdue() (includes/compliance_grid.php),
 * which is the same "strictly before today" rule the Define Tests grid's Overdue
 * filter and KPI tile apply — so a test the product calls overdue on screen is
 * the one the SoA marks overdue on paper.
 *
 * A MISSING next_date is not an overdue one. `next_date` is DATE NOT NULL with
 * no default, so legacy and manually-scheduled rows carry '0000-00-00', which
 * sorts before every real date and would therefore satisfy a bare `< today`.
 * Stamping "evidence out of date" on every control that simply has no next
 * window scheduled is a self-inflicted audit finding, and it would also be the
 * product inventing a currency opinion where the customer's schedule states
 * none.
 *
 * THAT GUARD NOW LIVES IN is_test_overdue() ITSELF, so the duplicate below is
 * redundant. It stays deliberately, as defence in depth: this function is the
 * one whose output lands in a document an auditor reads, and the cost of the
 * shared helper's guard being weakened or reverted by unrelated grid work is a
 * false finding on a customer's SoA. Two lines of dead-but-correct code is the
 * cheaper side of that trade. (The original rationale — "the grid has no such
 * guard, and on a coloured pill that is cosmetic" — was wrong twice over: the
 * unguarded grid ALSO fed the Overdue KPI tile, and a badge that says Overdue
 * on a test with no schedule is not cosmetic. Hence the move.)
 *
 * Pure: no DB, no globals, no output.
 */
function soa_test_is_overdue(array $test): bool {

    $next_date = trim((string)($test['next_date'] ?? ''));

    if ($next_date === '' || strpos($next_date, '0000-00-00') === 0) {
        return false;
    }

    return is_test_overdue($next_date);
}

/****************************************************
 * FUNCTION: SOA IMPLEMENTED FOR                    *
 ****************************************************
 * Clause 6.1.3(d)'s third requirement: implementation status.
 *
 * DERIVED, NEVER STORED. A stored "implemented" flag is a second source of truth
 * for a fact the tests already state, and it is the one an SoA is most likely to
 * be caught lying about — a control whose test started failing in March does not
 * update a flag somebody set in January. Computing it on every read means the
 * SoA cannot disagree with the control.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MATURITY IS NOT IMPLEMENTATION. This function reads NEITHER maturity column,
 * and that is the whole change.
 * ─────────────────────────────────────────────────────────────────────────────
 * The derivation this replaced read `desired_maturity > 0 && current < desired`
 * as Partial. That expression means "we have not reached our internal maturity
 * ambition" — a TARGET GAP, not an effectiveness failure. An auditor does not
 * care about an organisation's aspiration; they care whether the control
 * OPERATES. A control can operate perfectly and still sit below a stretch
 * target, and printing Partial for it UNDERSTATES the organisation's position in
 * a document it attests to.
 *
 * The same rule OVERSTATED in the other direction, which is the worse half: it
 * answered Yes for a control nobody had ever tested that merely carried a
 * maturity value. `control_status` is ignored for a related reason — it is a
 * denormalised summary of the tests, and the tests themselves are right here.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE RULE. Over the control's LIVE tests and their LATEST RECORDED results:
 * ─────────────────────────────────────────────────────────────────────────────
 *   no live test                  -> no_test_defined  (a GOVERNANCE gap)
 *   live tests, no result ever    -> never_run        (an OPERATIONAL gap)
 *   every live test passed        -> yes
 *   nothing passed, ≥1 failed     -> no
 *   anything else                 -> partial
 *
 * PASS AND FAIL ARE NOT DECIDED HERE. They are read through
 * last_result_state_family() + test_last_result_is_passing() /
 * _is_failing() (includes/compliance_grid.php), the vocabulary the Define Tests
 * grid, its quick-filters and its KPI tiles already share. A control the product
 * counts as Failing must not be a control the SoA prints as implemented.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO THINGS PRODUCE NO VERDICT, AND THEY ARE THE SAME FACT ABOUT THE EVIDENCE.
 * ─────────────────────────────────────────────────────────────────────────────
 *   'Inconclusive'  — the test ran and could not tell. A REAL stored value of
 *                     `framework_control_test_results`.`test_result`.
 *   never run       — a live test that has no recorded result at all.
 *
 * Both are counted together as UNRESOLVED below. Neither substantiates that the
 * control operates, and neither is evidence that it fails. Any future result
 * vocabulary that is neither Pass nor Fail inherits this treatment
 * automatically, which is the point of counting it rather than enumerating it.
 *
 * `never_run` is reached only when NO live test has a result — that is what makes
 * it a statement about the CONTROL rather than about one of its tests.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE ASYMMETRY IS DELIBERATE. `yes` AND `no` ARE NOT MIRROR IMAGES, AND MAKING
 * THEM MIRROR IMAGES IS A BUG. DO NOT "FIX" IT.
 * ─────────────────────────────────────────────────────────────────────────────
 * Unresolved evidence BLOCKS `yes`. Unresolved evidence DOES NOT RESCUE a
 * control from `no`. Read as a symmetry that is plainly inconsistent, and the
 * inconsistency is the point — the invariant this derivation is built on is not
 * "treat unresolved evidence consistently", it is:
 *
 *     MISSING EVIDENCE MUST NEVER IMPROVE THE ORGANISATION'S REPORTED POSITION.
 *
 * That invariant is DIRECTIONAL, so applying it to the two verdicts pulls them
 * apart, because they are claims in OPPOSITE directions:
 *
 *   `yes` is a claim IN THE ORGANISATION'S FAVOUR. Incomplete evidence must
 *   block it. One passing test beside one never-run test is `partial`, NOT
 *   `yes` — otherwise `yes` claims FULL implementation on PARTIAL evidence, in
 *   a document the customer attests to. Declaring Yes without evidence is a
 *   false statement against yourself.
 *
 *   `no` is a claim AGAINST the organisation. Incomplete evidence must NOT
 *   soften it. One failing test beside one never-run test is `no`, NOT
 *   `partial` — otherwise an unrun test UPGRADES a confirmed failure from "not
 *   implemented" to "partially implemented", and missing data has made the
 *   position look better. An auditor reading "partial" investigates less than
 *   one reading "no", so a real control failure would be softened by the
 *   ABSENCE of information. That is precisely the failure mode this whole
 *   derivation exists to remove.
 *
 * Both readings of the failing case are defensible in the abstract. The
 * tiebreak for a COMPLIANCE ARTEFACT is conservatism against your own interest:
 * where two readings are defensible, report the less flattering one. That is
 * what makes the document credible, and it is why `yes` requires COMPLETE
 * positive evidence while `no` requires only that NOTHING PASSED.
 *
 * Note what this does NOT do: an unresolved result on its own still cannot
 * produce `no`. A single Inconclusive test is `partial`, because there is no
 * failing verdict to be conservative about — inventing one would be
 * understating, which the same invariant forbids in the other direction.
 *
 * Applies to APPLICABLE and INHERITED controls alike. An inherited control is
 * applicable — a provider performs it — so its implementation status is a real
 * question, and answering it from the same tests is what stops "somebody else
 * does it" from being a synonym for "we don't know". Only a not_applicable
 * control has no implementation status; build_soa_rows() gives it
 * SOA_IMPLEMENTED_NOT_APPLICABLE without calling this function.
 *
 * Pure: no DB, no globals, no output. See the INPUT CONTRACT above for the
 * `tests[]` shape, including why an absent key is a supported input.
 */
function soa_implemented_for(array $row): string {

    $tests = soa_live_tests($row);

    // No live test: the organisation has not decided how to verify this control.
    // NOT `no` — that would declare the control broken on the strength of never
    // having looked at it.
    if (empty($tests)) {
        return SOA_IMPLEMENTED_NO_TEST_DEFINED;
    }

    $passed = $failed = $inconclusive = $never_run = 0;

    foreach ($tests as $test) {

        $result = soa_test_latest_result($test);

        // Defined, never run.
        if ($result === null) {
            $never_run++;
            continue;
        }

        $family = last_result_state_family($result);

        if (test_last_result_is_passing($family)) {
            $passed++;
        } elseif (test_last_result_is_failing($family)) {
            $failed++;
        } else {
            // Recorded, but neither verdict — 'Inconclusive' today.
            $inconclusive++;
        }
    }

    // Tests exist and not one of them has ever produced a result: the decision
    // was made and not carried out. Checked BEFORE the tallies below, because
    // this is the only case where "no verdict" describes the whole control
    // rather than leaving it partially evidenced.
    if ($passed + $failed + $inconclusive === 0) {
        return SOA_IMPLEMENTED_NEVER_RUN;
    }

    // Ran-but-inconclusive and never-ran are the same fact about the evidence:
    // this test produced no verdict.
    $unresolved = $inconclusive + $never_run;

    // `yes` needs COMPLETE positive evidence — every live test passed. Any
    // unresolved test blocks it, because `yes` is a claim in the organisation's
    // favour and missing evidence may not support one.
    if ($failed === 0 && $unresolved === 0) {
        return SOA_IMPLEMENTED_YES;
    }

    // `no` needs only that NOTHING PASSED and something actually failed.
    // DELIBERATELY NOT THE MIRROR of the branch above: `$unresolved` is absent
    // from this condition on purpose. Requiring it to be zero would let an unrun
    // test upgrade a confirmed failure to `partial` — missing evidence improving
    // the reported position, which is the one thing this derivation must never
    // do. See the asymmetry note in the docblock before changing this.
    if ($passed === 0 && $failed > 0) {
        return SOA_IMPLEMENTED_NO;
    }

    return SOA_IMPLEMENTED_PARTIAL;
}

/****************************************************
 * FUNCTION: SOA IMPLEMENTED IS OVERDUE             *
 ****************************************************
 * Whether the evidence behind this control's implementation status is out of
 * date — TRUE when the state is yes / partial / no and at least one test that
 * CONTRIBUTED a result has slipped past its own next_date.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A MARKER, NOT A STATE. It composes.
 * ─────────────────────────────────────────────────────────────────────────────
 * An overdue test KEEPS ITS RESULT. The test genuinely did pass, or genuinely
 * did fail; its staleness is separate metadata. Collapsing an overdue pass to
 * "unverified" would discard a real result the reader needs, and printing `no`
 * for it would assert a failure that never happened. So the verdict stands and
 * this predicate carries the caveat alongside it — which is why it is a second
 * function rather than a seventh SOA_IMPLEMENTED_* constant.
 *
 * TASK 6 OWES THIS A RENDERING, and the requirement is unusually strict: the
 * marker must be VISUALLY UNMISSABLE. An auditor skimming a column of
 * "Implemented: Yes" must not be able to overlook that the evidence behind one
 * of them is stale. A marker that is easy to miss looks like disclosure while
 * functioning as concealment, which is worse than not having one.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CURRENCY IS THE CUSTOMER'S, NEVER OURS.
 * ─────────────────────────────────────────────────────────────────────────────
 * SimpleRisk holds NO opinion about what "stale" means. Six months for one
 * organisation is two years for another and can vary per test, so there is no
 * product-level threshold here, anywhere, at any granularity — and adding one
 * later would silently start contradicting customers' own schedules inside a
 * document they attest to.
 *
 * It does not need one. Each test already carries its complete schedule
 * (`schedule_type`, `cadence_unit`, `cadence_interval`, `cadence_anchor_date`),
 * and `next_date` is the date those fields resolve to. A test is current when
 * ITS OWN schedule says so, and this function reads only that answer. A test
 * whose schedule sets no next window is never overdue — see soa_test_is_overdue().
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHICH TESTS COUNT
 * ─────────────────────────────────────────────────────────────────────────────
 * CONTRIBUTING ones: live, and holding a recorded result. Retired tests are
 * excluded by the same rule that excludes them from the verdict. A live test
 * that has NEVER RUN is excluded too — it produced no result, so there is no
 * evidence of its whose currency could be in question; its gap is surfaced
 * against the test itself in the per-test detail, not as a staleness caveat on
 * another test's verdict.
 *
 * NOT_APPLICABLE SHORT-CIRCUITS. An excluded control has no implementation
 * status, so it has nothing for a currency marker to qualify. The guard is
 * repeated here rather than left to build_soa_rows() so the predicate is
 * self-sufficient — a caller that reaches for the marker without going through
 * the row builder cannot get a stale-evidence claim on a control that is out of
 * scope. INHERITED does NOT short-circuit: a provider performs it, so its
 * evidence can absolutely be out of date.
 *
 * Pure: no DB, no globals, no output.
 */
function soa_implemented_is_overdue(array $row): bool {

    if ((string)($row['applicability'] ?? APPLICABILITY_DEFAULT_STATE) === 'not_applicable') {
        return false;
    }

    // no_test_defined and never_run have no verdict for the marker to qualify.
    if (!in_array(soa_implemented_for($row),
                  [SOA_IMPLEMENTED_YES, SOA_IMPLEMENTED_PARTIAL, SOA_IMPLEMENTED_NO], true)) {
        return false;
    }

    foreach (soa_live_tests($row) as $test) {

        // Contributed no result -> no evidence of its own to be out of date.
        if (soa_test_latest_result($test) === null) {
            continue;
        }

        if (soa_test_is_overdue($test)) {
            return true;
        }
    }

    return false;
}

/****************************************************
 * FUNCTION: SOA COUNT BY STATE                     *
 ****************************************************
 * The cover's numbers, counted FROM THE ROWS THE REPORT PRINTS rather than from
 * a COUNT(*) over the same tables.
 *
 * That is the whole reason this takes an array instead of a framework id. A
 * separate aggregate query is how a cover page comes to say 1,535 over a table
 * that lists 1,530 — and on an SoA the cover's totals are the first thing an
 * auditor reconciles. Counted this way the two cannot differ, because there is
 * only one computation.
 *
 * `excluded` is the sum of BOTH deviations, matching what the controls page's
 * Excluded insight tile counts (CONTROLS_TABLE_APPLICABILITY_ALIASES, whose
 * `excluded` token expands to not_applicable + inherited). The two deviations are
 * ALSO reported separately, because an SoA that folded them together would be
 * factually wrong: a cloud-hosted organisation does not EXCLUDE the datacenter
 * perimeter controls its provider performs.
 *
 * Pure: no DB, no globals, no output.
 *
 * @return array{total:int, applicable:int, not_applicable:int, inherited:int, excluded:int}
 */
function soa_count_by_state(array $rows): array {

    $counts = [
        'total'          => count($rows),
        'applicable'     => 0,
        'not_applicable' => 0,
        'inherited'      => 0,
    ];

    foreach ($rows as $row) {
        $state = (string)($row['applicable'] ?? APPLICABILITY_DEFAULT_STATE);
        if (isset($counts[$state])) {
            $counts[$state]++;
        }
    }

    $counts['excluded'] = $counts['not_applicable'] + $counts['inherited'];

    return $counts;
}

/****************************************************
 * FUNCTION: BUILD SOA ROWS                         *
 ****************************************************
 * Every SoA line for one framework, in control-number order.
 *
 * The population comes from controls_table_filtered_controls() with an EMPTY
 * filter map — the controls page's own pipeline, scoped to this framework and
 * nothing else. See RULE 2 in this file's opening note for why that matters and
 * what the alternative measured.
 *
 * An EMPTY filter map, specifically: the SoA is the whole framework. A filtered
 * SoA is not a Statement of Applicability, it is a worksheet, and handing an
 * auditor a document that silently omitted the rows somebody had filtered out on
 * screen is the worst failure this report could have. The framework is passed as
 * the scoping argument, which controls_table_filtered_controls() ASSIGNS over any
 * `framework` key in the map, so there is no path by which a caller narrows it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * EVERY DIMENSION IS BATCHED. There is no per-control query in this function.
 * ─────────────────────────────────────────────────────────────────────────────
 * Six maps are built before the loop — applicability, risks, documents,
 * references, tests, remediation — each one query (soa_test_map() costs three)
 * over the whole framework, and the loop only reads arrays. On the largest
 * framework in the development dataset that is 1,534 rows from a constant number
 * of round trips; the same builder written with a lookup inside the loop would
 * issue several thousand. The SoA also feeds a PDF export already running close
 * to its memory ceiling, so nothing here selects a blob or a document body.
 *
 * @return array<int, array{ref:string, refs:string[], control_number:string,
 *                          name:string, control_name:string, applicable:string,
 *                          justification:string, implemented:string,
 *                          implemented_overdue:bool, evidence:string,
 *                          evidence_items:array, evidence_absence:string,
 *                          remediation:array,
 *                          control_id:int, reason:?string, reasons:string[],
 *                          provider:?string, decided_by:?string, decided_at:?string}>
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE ROW IS BUILT AT THE SHAPE THE DOCUMENT RENDERS.
 * ─────────────────────────────────────────────────────────────────────────────
 * `tests` is deliberately NOT on the row. It is the derivation's own input, no
 * sink prints it, and at ~709 bytes an element it would be the single largest
 * thing in the payload — a fully-tested 1,500-control framework would carry
 * 1.3–1.5 MB of test arrays and roughly TRIPLE the row array. It lives on
 * `$derivation` below, local to the loop, so it is freed per control rather than
 * accumulated across the framework.
 *
 * `evidence_items` and `remediation` DO stay on every row, and the distinction
 * matters: those two are what the Evidence column and Appendix R print, so
 * dropping either would remove something the document says rather than something
 * it merely carried.
 */
function build_soa_rows(int $framework): array {

    global $lang;

    if ($framework <= 0) {
        return [];
    }

    // THE CONTROLS PAGE'S POPULATION. Distinct by construction
    // (get_framework_controls_by_filter() groups by t1.id) and already excluding
    // soft-deleted controls (its own `t1.deleted=0`).
    $controls = controls_table_filtered_controls($framework, []);

    if (empty($controls)) {
        return [];
    }

    $control_ids = array_map(static fn($c) => (int)$c['id'], $controls);

    // Deviations only — a control absent from this map is applicable, and
    // resolve_applicability() is what turns that absence into the answer.
    $decisions = get_framework_applicability_map($framework);

    $default_justification = soa_framework_default_justification($framework);
    $risk_map              = soa_risk_map($control_ids);
    $document_map          = soa_document_map($control_ids);
    // THE TESTS, WITH THEIR LATEST RECORDED RESULTS — the input the whole
    // implementation-status derivation reads (see the INPUT CONTRACT above).
    // Built ONCE for the framework: three queries for 1,500 controls, not three
    // per control. An N+1 here would be 4,500 round trips on SCF.
    $test_map              = soa_test_map($control_ids);
    // THE FRAMEWORK'S OWN REFERENCES, not the controls' numbers. See RULE 2b.
    $reference_map         = soa_reference_map($framework, $control_ids);

    // THE FAILING RESULTS' TREATMENT PLANS, in ONE query for the framework.
    //
    // Which results are failing is known before the loop runs — the verdicts are
    // already on $test_map — so this is gathered up front rather than looked up
    // per control, which is the N+1 this whole builder is shaped to avoid.
    // Nothing is fetched when a framework has no failures, which is the common
    // case on a healthy framework.
    $failing_result_ids = [];

    foreach ($test_map as $control_tests) {
        foreach (soa_live_tests(['tests' => $control_tests]) as $test) {
            $result = soa_test_latest_result($test);
            if ($result !== null
                && test_last_result_is_failing(last_result_state_family($result))
                && (int)($test['result_id'] ?? 0) > 0) {
                $failing_result_ids[] = (int)$test['result_id'];
            }
        }
    }

    $remediation_map = soa_remediation_map($failing_result_ids);

    // Resolved ONCE, outside the loop. $lang is the page's language file; the
    // structural fallback keeps this working on an instance whose language file
    // predates the key rather than printing the literal key name into an audit
    // document.
    $risk_template = (string)($lang['SoaJustifiedByRiskAssessment'] ?? SOA_RISK_TEMPLATE_FALLBACK);
    // Joins an applicable control's stored reasons to its stored prose. Same
    // structural-fallback rule as the risk template: never an English literal.
    $applicable_template = (string)($lang['SoaJustifiedByInclusionReasons'] ?? SOA_APPLICABLE_TEMPLATE_FALLBACK);

    $rows = [];

    foreach ($controls as $control) {

        $control_id = (int)$control['id'];
        $decision   = $decisions[$control_id] ?? null;
        $state      = resolve_applicability($decision);

        $tests = $test_map[$control_id] ?? [];

        $derivation = [
            'applicability'    => $state,
            'narrative'        => $decision['narrative'] ?? '',
            // THE INCLUSION/EXCLUSION TAXONOMY, multi-select. Read for the
            // applicable path (soa_justification_for()'s step 2); a deviation's
            // cell is its narrative, so its reasons are attribution rather than
            // justification and are carried on the row instead.
            'reason_names'     => $decision['reason_names'] ?? [],
            'risk_ids'         => $risk_map[$control_id] ?? [],
            // THE ONE INPUT soa_implemented_for() reads. The three maturity keys
            // below are NOT read by it and are kept only because
            // soa_justification_for() shares this array — see the note on
            // soa_implemented_for() about why maturity is not implementation.
            'tests'            => $tests,
            'control_status'   => $control['control_status'] === null ? 2 : (int)$control['control_status'],
            'control_maturity' => (int)($control['control_maturity'] ?? 0),
            'desired_maturity' => (int)($control['desired_maturity'] ?? 0),
        ];

        $implemented = $state === 'not_applicable'
            ? SOA_IMPLEMENTED_NOT_APPLICABLE
            : soa_implemented_for($derivation);

        $documents = $document_map[$control_id] ?? [];

        // Documents (design) plus the tests that produced a verdict (operation).
        // Built before the row so the derived evidence_absence below can read it
        // without calling soa_evidence_for() a second time.
        $evidence_items = soa_evidence_for($derivation, $documents);

        $control_number = trim((string)($control['control_number'] ?? ''));
        $control_name   = (string)($control['short_name'] ?? '');

        // THE FRAMEWORK'S references and titles for this control (RULE 2b).
        // Already natural-ordered and de-duplicated by soa_reference_map().
        $mapping    = $reference_map[$control_id] ?? [];
        $references = $mapping['refs'] ?? [];
        $subjects   = $mapping['subjects'] ?? [];

        // FALLS BACK TO THE CONTROL'S OWN NUMBER, never to blank. A control can
        // legitimately reach here with no `reference_name` in this framework —
        // the column is NOT NULL but accepts '', and the pre-2021 migration that
        // created these rows seeded it from `control_number` for exactly this
        // reason. An unlabelled line in a Statement of Applicability is a line an
        // auditor cannot reconcile against anything, so the control's own
        // identifier is printed instead: it is at worst the wrong catalogue's
        // code, which is still traceable, where blank is not.
        if (empty($references) && $control_number !== '') {
            $references = [$control_number];
        }

        $rows[] = [
            'control_id'    => $control_id,
            // Raw plain text throughout — see the rendering contract above.
            //
            // BOTH SHAPES ARE SENT. `ref` is the joined string a flat cell wants
            // (the XLSX); `refs` is the list a markup sink needs to keep each
            // code unbreakable while letting the CELL wrap — "GV.OC-01, GV.OC-02"
            // in a 12%-wide PDF column has to break, and it must break between
            // the codes rather than inside one.
            'refs'          => $references,
            'ref'           => implode(', ', $references),
            // The control's own identifier, always, and separately from `ref`.
            // On an SCF-derived catalogue this is the SCF code — the provenance
            // that lets a reader find the row in SimpleRisk itself once the
            // Reference column stopped being it.
            'control_number' => $control_number,
            // THE CONTROL'S OWN NAME, unless SOA_RENDER_FRAMEWORK_SUBJECTS is on.
            //
            // THE SWITCH IS OFF. Read its declaration above for why: a row
            // aggregates every clause of the framework this control answers, and
            // for GOV-01 in ISO 27002 that is 5.1, 5.4 and 5.37 — three
            // differently-titled requirements with no single correct title
            // between them. The control's own name is the thing that spans them
            // all. The subject is captured per clause and simply not printed.
            //
            // The branch is kept, not deleted, because the capture is real and
            // the decision that would turn it on (how a multi-clause row should
            // read, which is a question about RULE 2) is a live one.
            //
            // When it IS on, several titles join with "; " because a title can
            // itself contain a comma, which the Reference column's separator
            // cannot; and the fallback is still the control's own name, for the
            // frameworks that have told us no title.
            //
            // `control_name` below carries the control's name either way, so the
            // sinks show it as provenance rather than as the answer. With the
            // switch off the two are always equal, and every sink's "print the
            // control name as a second line only when it differs" test is
            // therefore false — which is what makes the output byte-identical
            // whether or not `reference_subject` is populated.
            'name'          => (SOA_RENDER_FRAMEWORK_SUBJECTS && !empty($subjects))
                                    ? implode('; ', $subjects)
                                    : $control_name,
            // The SimpleRisk control's own name, ALWAYS — the provenance that
            // lets a reader take a line of the SoA back to the control record it
            // came from. Equal to `name` when the framework supplied no title,
            // and the sinks print it only when it differs.
            'control_name'  => $control_name,
            'applicable'    => $state,
            'justification' => soa_justification_for($derivation, $default_justification, $risk_template, $applicable_template),
            'implemented'   => $implemented,
            // THE OVERDUE MARKER, alongside the verdict rather than instead of
            // it. The test genuinely did pass or fail; its staleness is separate
            // metadata, so the state stands and this carries the caveat.
            //
            // COMPUTED ONCE, HERE, for the same reason the state is: the screen,
            // the PDF and the XLSX all read this one answer, so they cannot
            // disagree about whether a control's evidence is current — and the
            // screen, which cannot call PHP, would otherwise have to re-derive
            // it from `tests[]` and become a second opinion.
            //
            // soa_implemented_is_overdue() short-circuits not_applicable itself,
            // and returns false for the two states that have no verdict for a
            // currency marker to qualify.
            'implemented_overdue' => soa_implemented_is_overdue($derivation),
            // THE FLAT EVIDENCE CELL, unchanged: the confirmed documents' names,
            // comma-joined, which is what the screen, the XLSX and the PDF have
            // always printed. `evidence_items` beside it is the itemised form.
            'evidence'      => implode(', ', array_column($documents, 'name')),
            // ─────────────────────────────────────────────────────────────────
            // THE AUDIT-READINESS DIMENSIONS.
            // ─────────────────────────────────────────────────────────────────
            // `tests` IS NOT HERE. It was the derivation's own input, carried so
            // a sink could show its working; no sink ever printed it, and it was
            // the largest thing in the payload. It stays on `$derivation`, which
            // is local to this loop. See the contract note above.
            //
            // Documents (design) plus tests that produced a verdict (operation).
            // EMPTY IS THE SIGNAL: an applicable control with an empty list is
            // the "no evidence linked" gap §3 requires be rendered distinctly
            // from a not-applicable control's neutral dash. Derived, never stored.
            'evidence_items' => $evidence_items,
            // WHICH ABSENCE, when the list above is empty — and the two are
            // opposites, so the sinks must not decide it for themselves. See the
            // SOA_EVIDENCE_* constants. Derived, never stored.
            'evidence_absence' => soa_evidence_absence([
                'applicable'     => $state,
                'evidence_items' => $evidence_items,
            ]),
            // ONLY FOR PARTIAL AND NO. A control that passes has nothing to
            // remediate, and one that was never verified has no failure to trace
            // — offering a treatment plan for either would invent a finding.
            'remediation'   => in_array($implemented, [SOA_IMPLEMENTED_PARTIAL, SOA_IMPLEMENTED_NO], true)
                ? soa_remediation_for($derivation, $remediation_map)
                : [],
            // NO `risks` KEY, and the reason is worth stating because the ids
            // ARE in the document. Traceability is half of what clause 6.1.3(d)
            // is for, so `remediation` above carries, per failing test, the risk
            // id AND the planning date, the mitigation owner and the percentage --
            // attached to the failure it explains rather than sitting in a column
            // of bare ids. soa_justification_for() cites the same ids in the
            // Justification cell for a control justified by its risk assessment.
            //
            // `$risk_map` is therefore still built and still read, through
            // `$derivation['risk_ids']` below, which is what that citation reads.
            // The audit-trail half of clause 6.1.3(d): a justification an auditor
            // cannot attribute is an assertion, not a record. Null on a control
            // with no stored decision, which has nothing to attribute.
            //
            // JOINED FOR THE FLAT CELL the three sinks already print, exactly as
            // `ref` joins `refs`; `reasons` beside it is the list a markup sink
            // can itemise. Reasons are multi-select since Task 4.
            'reason'        => empty($decision['reason_names'])
                                    ? null
                                    : implode(SOA_REASON_SEPARATOR, $decision['reason_names']),
            'reasons'       => $decision['reason_names'] ?? [],
            'provider'      => $decision['provider'] ?? null,
            'decided_by'    => $decision['decided_by_name'] ?? null,
            'decided_at'    => $decision['decided_at'] ?? null,
        ];
    }

    // ORDERED BY THE FRAMEWORK'S OWN REFERENCE — an ISO SoA reads 5.1, 5.2,
    // 5.3, which is the order the auditor holding the standard works down.
    //
    // The key is the control's LOWEST reference, which is `refs[0]` because
    // soa_reference_map() already sorted each control's list naturally. Sorting
    // on the joined `ref` string instead would order "5.1, 5.37" against
    // "5.1, 5.4" by their second element, which is not a document order anybody
    // asked for; and the previous sort on `control_number` is meaningless now
    // that it is no longer what the column prints.
    //
    // NATURAL and case-insensitive: a reference is a string ("A.5.1",
    // "AC-2(3)", "8.12"), and strcmp() files "A.10" before "A.2".
    //
    // Ties break on `control_number` so the order is TOTAL. Without it, the two
    // controls a framework cites as "5.1" would come back in whatever order the
    // pipeline happened to yield them, and a document that reorders between two
    // exports of the same data cannot be diffed — which is the first thing done
    // to last year's SoA.
    usort($rows, static function ($a, $b) {
        $order = strnatcasecmp($a['refs'][0] ?? '', $b['refs'][0] ?? '');
        return $order !== 0 ? $order : strnatcasecmp($a['control_number'], $b['control_number']);
    });

    return $rows;
}

/****************************************************
 * FUNCTION: BUILD SOA COVER                        *
 ****************************************************
 * The document's cover block: what framework this is a statement about, the
 * scope it is made against, how inclusion was determined, and the counts.
 *
 * IT TAKES THE ROWS. That is the point — the counts on the cover are derived
 * from the exact array the table below prints (soa_count_by_state()), so the
 * cover cannot contradict the document it introduces. A cover that re-queried
 * would be the second computation this whole file exists to avoid.
 *
 * `scope_statement` and `default_inclusion_justification` are returned as NULL
 * when they have never been set, and as '' when a customer deliberately cleared
 * one. The report distinguishes them: NULL is what makes it prompt. Coercing
 * both to '' here would suppress the prompt and hand an auditor a scope nobody
 * reviewed (see update_framework_soa_fields(), includes/governance.php).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE SCOPE STATEMENT IS RICH TEXT, AND IS PURIFIED HERE AS WELL AS ON WRITE.
 * ─────────────────────────────────────────────────────────────────────────────
 * This array is what the screen renders and what `GET /governance/soa` returns,
 * and the screen inserts the statement as HTML (`.html()`, not `.text()`) — so
 * this is an output boundary in the sense purify_rich_text_output()'s own note
 * means it. On-write purification (update_framework_soa_fields()) is the primary
 * control; purifying again here is what keeps a row that never went through that
 * path — one written before the field became rich text, one written straight
 * into the database, one restored from an old backup — from delivering stored
 * XSS. purify_html() is idempotent, so legitimate rich text is unchanged.
 *
 * It is the COVER BUILDER that purifies, not soa_framework_meta() below: that
 * getter also feeds soa_framework_default_justification(), and purifying inside
 * a shared getter is exactly the placement purify_rich_text_output() warns
 * against.
 *
 * `default_inclusion_justification` is NOT purified — it is raw plain text by
 * contract and every sink escapes it. Purifying it here would hand the PDF's
 * $escaper a value to escape a second time.
 *
 * `default_inclusion_justification` IS RETURNED BUT NOT PRINTED ON THE COVER by
 * any of the three sinks (the screen, the XLSX, the PDF). It stays in the
 * payload because it is a contract other consumers read — the framework edit
 * modal and the missing-fields prompt both need it — but rendering it above the
 * table repeated, verbatim, the sentence the Justification column already prints
 * on every applicable control with no linked risks: ~1,500 times on SCF. Clause
 * 6.1.3(d) asks for the justification PER CONTROL; the cover's job is the scope.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THE COVER SAYS, AND WHAT IT DELIBERATELY DOES NOT.
 * ─────────────────────────────────────────────────────────────────────────────
 * The organisation, the framework, the scope statement, the generation timestamp
 * and the five counts. A controlled document has to identify itself, and on a
 * chrome-free page the cover is the only thing that can.
 *
 * `default_inclusion_justification` is returned but NOT printed by any of the
 * three sinks — see the note above. Neither is anything describing the shape of
 * the document: there is one shape.
 *
 * @return array{framework:int, framework_name:string, framework_status:int,
 *               scope_statement:?string, default_inclusion_justification:?string,
 *               total:int, applicable:int, not_applicable:int,
 *               inherited:int, excluded:int, generated_at:string}
 */
function build_soa_cover(int $framework, array $rows): array {

    $meta = soa_framework_meta($framework);

    return array_merge([
        // WHOSE statement this is. The document is rendered chrome-free -- no
        // sidebar, no breadcrumb -- and is meant to be printed and handed over,
        // so the page itself has to carry the context the app shell used to
        // supply. Read from `registration_company`, the name the instance is
        // registered under, which is the only organisation name SimpleRisk
        // holds. Empty when unregistered; the renderer omits the line rather
        // than printing a blank one.
        'organization'                    => (string)get_setting('registration_company'),
        'framework'                       => $framework,
        // `frameworks`.`name` is one of the Encrypted Database Extra's fields, so
        // it is read through get_name_by_value() rather than selected directly —
        // a raw SELECT returns ciphertext on an encrypted instance.
        'framework_name'                  => $meta['name'],
        'framework_status'                => $meta['status'],
        // NULL is preserved rather than purified: purify_html() would answer ''
        // for it, which is the OTHER state ("deliberately cleared") and would
        // silence the missing-fields prompt for a framework nobody has been
        // asked about. A stored value that renders as nothing is normalised the
        // other way — reported as '' — so a legacy `<p><br></p>` row prompts
        // exactly like the empty statement it looks like.
        'scope_statement'                 => $meta['scope_statement'] === null
            ? null
            : (rich_text_is_blank($meta['scope_statement'])
                ? ''
                : purify_rich_text_output($meta['scope_statement'])),
        'default_inclusion_justification' => $meta['default_inclusion_justification'],
        // Not the moment of a "generate" click: this document is assembled on
        // every read, so the only honest timestamp is now.
        'generated_at'                    => date('Y-m-d H:i:s'),
    ], soa_count_by_state($rows));
}

/****************************************************
 * FUNCTION: BUILD SOA                              *
 ****************************************************
 * The whole document — cover and rows — in ONE call.
 *
 * This is what every consumer should use (the endpoint below, and Task 18's
 * export). Not because it is shorter, but because it is the only signature in
 * which the cover's counts and the printed rows are structurally the same array.
 * Two callers each doing build_soa_rows() + build_soa_cover() in their own order
 * is two chances to pass the cover a different row set than the one they print.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THERE IS NO PROJECTION STEP, AND THAT IS DELIBERATE.
 * ─────────────────────────────────────────────────────────────────────────────
 * build_soa_rows() emits the shape the sinks render, so there is nothing here to
 * reduce. Anything that narrowed a built row would have to run every derivation
 * FIRST, or it would leave `evidence_absence` derived from evidence the row no
 * longer carried — and the document would claim a tested control had none. Not
 * having the step is what makes that impossible rather than merely guarded.
 *
 * @return array{cover:array, rows:array}
 */
function build_soa(int $framework): array {

    $rows = build_soa_rows($framework);

    return [
        'cover' => build_soa_cover($framework, $rows),
        'rows'  => $rows,
    ];
}

/****************************************************
 * FUNCTION: SOA FRAMEWORK META                     *
 ****************************************************
 * The framework facts the cover needs, or the empty shape when no such framework
 * exists.
 *
 * @return array{exists:bool, name:string, status:int, scope_statement:?string,
 *               default_inclusion_justification:?string}
 */
function soa_framework_meta(int $framework): array {

    $empty = [
        'exists'                          => false,
        'name'                            => '',
        'status'                          => 0,
        'scope_statement'                 => null,
        'default_inclusion_justification' => null,
    ];

    if ($framework <= 0) {
        return $empty;
    }

    $db   = db_open();
    $stmt = $db->prepare(
        "SELECT `status`, `scope_statement`, `default_inclusion_justification`
         FROM `frameworks` WHERE `value` = :framework LIMIT 1;"
    );
    $stmt->execute([':framework' => $framework]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);

    if (!$row) {
        return $empty;
    }

    return [
        'exists' => true,
        'name'   => (string)get_name_by_value('frameworks', $framework, ''),
        'status' => (int)$row['status'],
        // NULL is preserved. See build_soa_cover().
        'scope_statement'                 => $row['scope_statement'] === null ? null : (string)$row['scope_statement'],
        'default_inclusion_justification' => $row['default_inclusion_justification'] === null
            ? null : (string)$row['default_inclusion_justification'],
    ];
}

/****************************************************
 * FUNCTION: SOA FRAMEWORK DEFAULT JUSTIFICATION    *
 ****************************************************
 * The framework's default inclusion justification as a STRING, for the
 * Justification column's third fallback step.
 *
 * NULL collapses to the SYSTEM DEFAULT here — and only here. The column-filling
 * path does not care whether the field was never set or deliberately cleared;
 * both mean "this framework has no sentence of its own to print". The
 * distinction that matters lives on the cover, which is what drives the report's
 * prompt, and soa_framework_meta() keeps it intact for that caller.
 *
 * WHY A FALLBACK RATHER THAN ''. Returning '' is what made
 * soa_justification_for()'s "the column is never blank" untrue for the majority
 * of real frameworks: on the development instance 19 of 20 had the field NULL,
 * so every applicable control with no linked risks printed an EMPTY
 * justification cell — the single most common finding against a hand-maintained
 * SoA, reproduced by the tool that was supposed to prevent it. Both halves of
 * Task 67 (a seeded value on the create form, a backfill for existing rows) fix
 * the data; this fixes the CONSTRUCTION, so no future row of any framework can
 * reach the column empty. It is the same guarantee assert_applicability_narrative()
 * (includes/applicability.php) already gives the exclusion path, reached the
 * other way round: that one refuses the write, this one cannot produce a blank
 * read.
 *
 * THE SENTENCE IS THE SAME ONE the create form seeds and the field's placeholder
 * shows — $lang['DefaultInclusionJustificationPlaceholder'], which names the
 * DRIVER (the organization's risk assessment) rather than the framework, because
 * "included because it is in the framework" is the circular answer clause 6.1.3
 * does not accept. One string, so a stored default and a fallen-back one read
 * identically and a reader cannot tell which row came from which.
 *
 * LOCALIZED, NOT A CONSTANT. An English literal here would print English into a
 * document that ships in 39 languages (CLAUDE.md). '' remains the structural
 * fallback for an instance whose language file predates the key — degraded, but
 * never the literal key name in an audit artefact.
 */
function soa_framework_default_justification(int $framework): string {

    global $lang;

    $stored = (string)(soa_framework_meta($framework)['default_inclusion_justification'] ?? '');

    if ($stored !== '') {
        return $stored;
    }

    return (string)($lang['DefaultInclusionJustificationPlaceholder'] ?? '');
}

/****************************************************
 * FUNCTION: SOA RISK MAP                           *
 ****************************************************
 * control id => the DISPLAYED ids of the risks whose treatment names that
 * control.
 *
 * `mitigation_to_controls` is the risk-treatment link: a mitigation belongs to a
 * risk, and names the controls that implement it. That is the fact that lets an
 * SoA say "included because risk 1042 required it" without anyone retyping it.
 *
 * DISPLAYED ids, not stored ones: SimpleRisk shows a risk as `risks`.`id` + 1000
 * everywhere a user can see it (management/view.php?id=…), so printing the raw id
 * would give an auditor a number that matches nothing they can look up.
 *
 * DISTINCT at the SQL level: one risk can carry several mitigations naming the
 * same control, and listing risk 1042 three times in one justification would look
 * like three different risks.
 *
 * @param  int[] $control_ids
 * @return array<int, int[]>
 */
function soa_risk_map(array $control_ids): array {

    $ids = array_values(array_unique(array_filter(
        array_map('intval', $control_ids), static fn($id) => $id > 0
    )));

    if (empty($ids)) {
        return [];
    }

    $db = db_open();

    // Only the placeholder ARITY is interpolated into the SQL; every value is bound.
    $stmt = $db->prepare(
        "SELECT DISTINCT mtc.`control_id`, m.`risk_id`
         FROM `mitigation_to_controls` mtc
         INNER JOIN `mitigations` m ON mtc.`mitigation_id` = m.`id`
         INNER JOIN `risks` r ON m.`risk_id` = r.`id`
         WHERE mtc.`control_id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
         ORDER BY mtc.`control_id` ASC, m.`risk_id` ASC;"
    );
    $stmt->execute($ids);

    $out = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        // The INNER JOIN to `risks` is what drops a mitigation whose risk has been
        // deleted: citing a risk id an auditor cannot open is worse than citing none.
        $out[(int)$row['control_id']][] = (int)$row['risk_id'] + 1000;
    }

    db_close($db);

    return $out;
}

/****************************************************
 * FUNCTION: SOA SORT REFERENCES                    *
 ****************************************************
 * A control's framework references, cleaned and put in the order a reader
 * scans them in.
 *
 * NATURAL, CASE-INSENSITIVE, and that is the whole reason this exists rather
 * than a GROUP_CONCAT in SQL. A reference is a dotted or suffixed code, so the
 * lexical order MySQL would give is wrong in a way that is immediately visible
 * in an audit document — real ISO 27002 data for control CFG-02:
 *
 *     lexical   8.12, 8.25, 8.26, 8.3, 8.5, 8.9
 *     natural   8.3, 8.5, 8.9, 8.12, 8.25, 8.26
 *
 * Blanks are dropped and duplicates collapsed: `framework_control_mappings` is
 * unique on (control_id, framework, reference_name) so exact duplicates cannot
 * be stored, but whitespace-only entries and " 5.1 "/"5.1" pairs can be, and a
 * reference printed twice on one line reads as two different clauses.
 *
 * Pure: no DB, no globals, no output.
 *
 * @param  string[] $references
 * @return string[]
 */
function soa_sort_references(array $references): array {

    $out = [];

    foreach ($references as $reference) {
        $reference = trim((string)$reference);
        if ($reference !== '' && !in_array($reference, $out, true)) {
            $out[] = $reference;
        }
    }

    usort($out, 'strnatcasecmp');

    return $out;
}

/****************************************************
 * FUNCTION: SOA REFERENCE MAP                      *
 ****************************************************
 * control id => the references THIS FRAMEWORK cites that control by AND the
 * titles it gives them, both in natural reference order.
 *
 * The Reference column, and the fix for the defect described in RULE 2b at the
 * top of this file. `framework_control_mappings`.`reference_name` is the
 * per-framework reference; `framework_controls`.`control_number` is the
 * control's OWN identifier, which on an SCF-derived catalogue is an SCF code and
 * not an ISO clause.
 *
 * SCOPED TO ONE FRAMEWORK, always. An SoA is a per-framework document, and the
 * same control carries entirely different references in ISO 27002 and in NIST
 * CSF. `$framework` is bound, not interpolated, and only the placeholder ARITY
 * of the id list is interpolated — the same shape soa_document_map() uses.
 *
 * RETURNS A MAP, NOT ROWS. That is what keeps RULE 2 intact: the caller looks a
 * control up in this array, so a control with seven mappings still contributes
 * exactly one SoA line. This function is the reason the reference dimension can
 * be restored WITHOUT re-joining the mapping table into the population — which
 * is the 6.2x row inflation RULE 2 exists to prevent.
 *
 * Aggregated in PHP rather than by GROUP_CONCAT for two reasons: natural
 * ordering (see soa_sort_references()), and because `group_concat_max_len`
 * defaults to 1024 bytes — a silently truncated Reference cell in an audit
 * document is worse than a slower query.
 *
 * `subjects` IS THE FRAMEWORK'S OWN TITLE for each control it cites
 * (`reference_subject`, added in the 20260709-001 upgrade), ordered by the
 * reference it belongs to and with the empties dropped — so a control the
 * framework titled for only two of its five clauses contributes those two and
 * nothing blank.
 *
 * CAPTURED HERE, NOT PRINTED. build_soa_rows() leaves the Name column on the
 * control's own `short_name` while SOA_RENDER_FRAMEWORK_SUBJECTS is off, which
 * it is — a row aggregates every clause the control answers and those clauses
 * have different titles, so no single one of them is true of the row. This map
 * still carries them, correctly ordered, so the day that decision changes the
 * data is already here and already right.
 *
 * @param  int[] $control_ids
 * @return array<int, array{refs:string[], subjects:string[]}>
 */
function soa_reference_map(int $framework, array $control_ids): array {

    $ids = array_values(array_unique(array_filter(
        array_map('intval', $control_ids), static fn($id) => $id > 0
    )));

    if ($framework <= 0 || empty($ids)) {
        return [];
    }

    $db = db_open();

    // Only the placeholder ARITY is interpolated into the SQL; every value is bound.
    //
    // ORDER BY is deliberately absent: MySQL cannot express the natural order
    // this needs (see soa_sort_references()), so the ordering is done below over
    // the fetched rows and a SQL sort would only be work thrown away.
    $stmt = $db->prepare(
        "SELECT `control_id`, `reference_name`, `reference_subject`
         FROM `framework_control_mappings`
         WHERE `framework` = ?
           AND `control_id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ");"
    );
    $stmt->execute(array_merge([$framework], $ids));

    $mappings = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mappings[(int)$row['control_id']][] = [
            'name'    => trim((string)($row['reference_name'] ?? '')),
            'subject' => trim((string)($row['reference_subject'] ?? '')),
        ];
    }

    db_close($db);

    $out = [];

    foreach ($mappings as $control_id => $rows) {

        // Natural, by the reference — which is also the order the subjects come
        // out in, so "5.1, 5.4" and its two titles read down the row together.
        usort($rows, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $subjects = [];

        foreach ($rows as $row) {
            if ($row['subject'] !== '' && !in_array($row['subject'], $subjects, true)) {
                $subjects[] = $row['subject'];
            }
        }

        $out[$control_id] = [
            'refs'     => soa_sort_references(array_column($rows, 'name')),
            'subjects' => $subjects,
        ];
    }

    return $out;
}

/****************************************************
 * FUNCTION: SOA DOCUMENT MAP                       *
 ****************************************************
 * control id => the names of the governing documents mapped to it, comma-joined.
 *
 * This is the Evidence column: the policy or procedure that documents how the
 * control is met.
 *
 * RETURNS THE DOCUMENTS THEMSELVES, not a joined string. The Evidence column
 * needs both shapes — the flat `evidence` cell every sink already prints, and
 * the itemised `evidence_items` list Task 3 added so a reader can tell WHICH
 * documents substantiate the control — and build_soa_rows() derives the flat
 * one by joining the names. Two shapes, ONE query.
 *
 * That also retires the GROUP_CONCAT this used to be, whose `group_concat_max_len`
 * default of 1024 bytes silently truncates a control carrying a dozen policies —
 * the same reason soa_reference_map() aggregates in PHP. The ORDER BY stays in
 * SQL so the joined string keeps MySQL's collation order rather than PHP's, and
 * names are de-duplicated exactly as `GROUP_CONCAT(DISTINCT …)` did.
 *
 * `selected = 1` IS LOAD-BEARING, not a tidy-up. `document_control_mappings`
 * holds two very different kinds of row: mappings a human CONFIRMED
 * (`selected = 1`, written by save_document_control_mappings() and by the
 * document form) and unconfirmed CANDIDATES proposed by the TF-IDF and AI
 * matchers (`selected = 0`, with their own `score` / `ai_confidence` /
 * `ai_reasoning` columns). Every other reader in the codebase filters on it —
 * includes/reporting.php:9740 and :9786, includes/governance.php:2403,
 * includes/functions.php:2762, includes/workflows.php:1156 — and this one must
 * too. Without the filter the Evidence column would present a machine's
 * unreviewed guesses to an auditor as the organisation's governing documentation.
 *
 * `documents`.`document_name` is NOT in the Encrypted Database Extra's field map
 * (extras/encryption/index.php), unlike `frameworks`.`name`, so it is selected
 * directly rather than through get_name_by_value().
 *
 * @param  int[] $control_ids
 * @return array<int, array<int, array{id:int, name:string}>>
 */
function soa_document_map(array $control_ids): array {

    $ids = array_values(array_unique(array_filter(
        array_map('intval', $control_ids), static fn($id) => $id > 0
    )));

    if (empty($ids)) {
        return [];
    }

    $db = db_open();

    // `document_type` IS SELECTED SO THE EVIDENCE CELL CAN SAY WHICH KIND.
    // SimpleRisk's governance documents are policies, standards, procedures or
    // guidelines (governance/documentation.php's tabs are the vocabulary), and an
    // auditor reading "Legal & Regulatory Policy" beside "Access Control
    // Standard" is reading two different classes of design evidence. Without the
    // type the SoA flattened all four into one undifferentiated list of names.
    $stmt = $db->prepare(
        "SELECT DISTINCT dcm.`control_id`, d.`id`, d.`document_name`, d.`document_type`
         FROM `document_control_mappings` dcm
         INNER JOIN `documents` d ON dcm.`document_id` = d.`id`
         WHERE dcm.`selected` = 1
           AND dcm.`control_id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
         ORDER BY dcm.`control_id`, d.`document_name`;"
    );
    $stmt->execute($ids);

    $out  = [];
    $seen = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $control_id = (int)$row['control_id'];
        $name       = (string)($row['document_name'] ?? '');

        // De-duplicated BY NAME, which is what GROUP_CONCAT(DISTINCT …) did:
        // one control mapped twice to the same document is one policy, and an
        // Evidence cell naming it twice reads as two.
        if (isset($seen[$control_id][$name])) {
            continue;
        }

        $seen[$control_id][$name] = true;

        $out[$control_id][] = [
            'id'   => (int)$row['id'],
            'name' => $name,
            // The raw column value ('policies' / 'standards' / 'procedures' /
            // 'guidelines'). Kept RAW rather than localized here: this function
            // is a data reader with no business resolving display text, and the
            // three sinks (screen, PDF, XLSX) each have their own label lookup.
            'document_type' => (string)($row['document_type'] ?? ''),
        ];
    }

    db_close($db);

    return $out;
}

/****************************************************
 * FUNCTION: SOA TEXT OR NULL                       *
 ****************************************************
 * One column value, as the `tests[]` input contract wants it: NULL stays NULL,
 * everything else becomes a TRIMMED STRING.
 *
 * That is the whole rule, and it is what lets the exhaustive derivation tests be
 * pure array literals — a PDO fetch (every column a string) and a hand-built
 * fixture (real nulls, real ints) reach soa_implemented_for() looking identical.
 *
 * Pure: no DB, no globals, no output.
 */
function soa_text_or_null($value): ?string {

    return $value === null ? null : trim((string)$value);
}

/****************************************************
 * FUNCTION: SOA ZERO DATE OR NULL                  *
 ****************************************************
 * A date column's value, with MySQL's zero date reported as the absence it is.
 *
 * `0000-00-00` is what a NOT NULL date column holds when nobody supplied one, and
 * it is not a date -- it is a sentinel. Printed verbatim it reads as a real target
 * that has been missed by a century, which in an audit document is worse than
 * printing nothing at all.
 *
 * TRIMMED AND PREFIX-MATCHED, so both `0000-00-00` and `0000-00-00 00:00:00`
 * answer NULL. '' answers NULL too, for the same reason soa_plain_text_or_null()
 * used to: a value that is present but says nothing is the absence it looks like.
 *
 * Pure: no DB, no globals, no output.
 */
function soa_zero_date_or_null($value): ?string {

    $date = $value === null ? '' : trim((string)$value);

    if ($date === '' || strpos($date, '0000-00-00') === 0) {
        return null;
    }

    return $date;
}

/******************************************************************************
 * soa_plain_text_or_null() USED TO BE HERE.
 *
 * It projected a RICH-TEXT column to plain lines, and it had exactly one caller:
 * soa_test_map()'s `required_evidence`, the only WYSIWYG value the SoA ever
 * carried. `framework_control_tests`.`required_evidence` is a HugeRTE field, and
 * on the development instance 32 of 68 populated rows hold real markup -- so
 * without a projection the document printed `<ul><li>` at the reader.
 *
 * With the required-evidence marker removed from every rendering, no SoA payload
 * carries rich text at all, and the projection has nothing to project.
 *
 * IF A RICH-TEXT COLUMN COMES BACK INTO THIS FILE, project it rather than adding
 * a third HTML sink, and the reason is not a preference:
 *
 *   THE PDF holds the whole table in memory (SOA_EXPORT_PDF_MAX_CONTROLS exists
 *   for that), so a nested `<ul>` of four `<li>` per tested control is thousands
 *   of extra boxes in the document that already has a size ceiling.
 *
 *   THE XLSX needs it regardless: a spreadsheet cell is not markup.
 *
 * rich_text_to_plain_text() (includes/functions.php) is the projection to use --
 * it converts block boundaries FIRST, so `<li>` becomes "• item" on its own line
 * and a list still reads as a list in all three sinks. Report '' as NULL, because
 * a value that was present but rendered as nothing (`<p>&nbsp;</p>`, what an
 * emptied editor leaves behind) is the absence it looks like.
 ******************************************************************************/

/****************************************************
 * FUNCTION: SOA TEST MAP                           *
 ****************************************************
 * control id => that control's tests, RETIRED ONES INCLUDED, each carrying its
 * latest RECORDED result.
 *
 * This is the supplier the `$row['tests']` INPUT CONTRACT above describes — read
 * it before changing anything here; it governs.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RETIRED TESTS ARE RETURNED, DELIBERATELY.
 * ─────────────────────────────────────────────────────────────────────────────
 * Filtering them out here would look tidier and would be wrong twice over. The
 * contract puts that decision in soa_live_tests(), which is pure and therefore
 * testable without a database; and soa_implemented_for() has to be able to tell
 * "this control's only test was retired last month" from "this control never had
 * one", which a query that dropped the row could never tell it. One `tests[]`
 * array serves every consumer.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHICH CONTROLS A TEST COVERS IS NOT `framework_control_id`.
 * ─────────────────────────────────────────────────────────────────────────────
 * A COMMON TEST maps to N controls through the `test_control_map` junction, so
 * reading the scalar column alone would attribute one shared test to a single
 * control and leave every other control it covers reporting "no test defined" —
 * on an audit document, a fabricated governance gap. The pair source is
 * test_control_pairs_sql() (includes/compliance_grid.php), the SAME fragment the
 * Define Tests grid and its coverage counts use, including its fallback for
 * tests written by the two raw-SQL writers that never populate the junction. It
 * takes no bound parameters and is safe to inline verbatim.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE LATEST RESULT COMES FROM get_tests_last_results(), NOT FROM A JOIN.
 * ─────────────────────────────────────────────────────────────────────────────
 * "Latest recorded result" is not "newest audit": an audit that was initiated
 * and not yet resulted stores the EMPTY STRING, and 351 such rows exist on the
 * development instance. A newest-wins join would hand those back as the current
 * verdict, so every affected control would read as never run — the exact defect
 * that function was fixed for. It is called ONCE for every test on the
 * framework, so this is one query, not one per control.
 *
 * @param  int[] $control_ids
 * @return array<int, array<int, array>>
 */
function soa_test_map(array $control_ids): array {

    $ids = array_values(array_unique(array_filter(
        array_map('intval', $control_ids), static fn($id) => $id > 0
    )));

    if (empty($ids)) {
        return [];
    }

    $db = db_open();

    // Only the placeholder ARITY and test_control_pairs_sql() — which is a
    // constant, parameterless fragment — are interpolated; every value is bound.
    $stmt = $db->prepare(
        "SELECT p.`framework_control_id` AS `control_id`,
                t.`id`, t.`name`, t.`last_date`, t.`next_date`, t.`retired_at`,
                t.`schedule_type`, t.`cadence_unit`, t.`cadence_interval`,
                t.`cadence_anchor_date`, t.`test_frequency`
         FROM " . test_control_pairs_sql() . " p
         INNER JOIN `framework_control_tests` t ON t.`id` = p.`test_id`
         WHERE p.`framework_control_id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
         ORDER BY p.`framework_control_id` ASC, t.`id` ASC;"
    );
    $stmt->execute($ids);

    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    if (empty($tests)) {
        return [];
    }

    // ONE call for every test on the framework — see the note above.
    $last_results = get_tests_last_results(
        array_values(array_unique(array_map(static fn($t) => (int)$t['id'], $tests)))
    );

    // NO AUDIT-EVIDENCE LOOKUP. soa_audit_evidence_map() used to run here, a
    // third query counting `compliance_files` rows per audit, purely to answer
    // "did the evidence this test declared it needed actually arrive?". That fed
    // the required-evidence marker, which is gone from every rendering, so the
    // query is gone with it -- one fewer round trip on every SoA.

    $out = [];

    foreach ($tests as $test) {

        $test_id = (int)$test['id'];
        $last    = $last_results[$test_id] ?? [];

        $out[(int)$test['control_id']][] = [
            // The join key, and the only value that is not a string: every
            // consumer uses it to look a test up rather than to print it.
            'id'                  => $test_id,
            'name'                => soa_text_or_null($test['name']),
            // THE TEST'S OWN last/next window, from `framework_control_tests`.
            // `next_date` is the ONLY currency signal — see soa_test_is_overdue().
            //
            // `last_date` GOES THROUGH THE ZERO-DATE GUARD, at construction rather
            // than at each sink. (`next_date` is left alone deliberately: it is
            // the overdue signal, soa_test_is_overdue() already reasons about its
            // emptiness, and soa_evidence_for() applies its own zero-date check
            // where it prints it. Moving that here would mean re-deriving the
            // overdue rule.) `framework_control_tests`.`last_date` is DATE NOT NULL
            // with no default, the same shape as `next_date`, so it carries
            // MySQL's `0000-00-00` sentinel for a test that has never run. It
            // reaches the document through soa_remediation_for() and
            // soa_evidence_for(), and nothing downstream re-checked it: the only
            // reason it has not printed a 1899-era date into an appendix is that
            // get_tests_last_results() happens to always pair a result with a
            // date, so the `last_result_date` branch wins first. That invariant
            // lives in compliance_grid.php, not here, and the INPUT CONTRACT above
            // explicitly supports a hand-built `tests[]` fixture that would not
            // honour it. Guarding here means every consumer inherits it.
            'last_date'           => soa_zero_date_or_null($test['last_date'] ?? null),
            'next_date'           => soa_text_or_null($test['next_date']),
            'retired_at'          => soa_text_or_null($test['retired_at']),
            'schedule_type'       => soa_text_or_null($test['schedule_type']),
            'cadence_unit'        => soa_text_or_null($test['cadence_unit']),
            'cadence_interval'    => soa_text_or_null($test['cadence_interval']),
            'cadence_anchor_date' => soa_text_or_null($test['cadence_anchor_date']),
            // The INTERVAL schedule's period, in days. Retained because
            // soa_test_is_overdue() and the Define Tests grid's own
            // format_test_schedule_summary() both read the schedule shape; the
            // 'calendar' type uses the three cadence_* columns above instead.
            'test_frequency'      => soa_text_or_null($test['test_frequency']),
            // THE CONTRACT'S THIRD REQUIRED KEY. NULL or '' both mean "never
            // run"; soa_test_latest_result() collapses them.
            'test_result'         => soa_text_or_null($last['result'] ?? null),
            // The record that verdict came from: when it was PERFORMED, what the
            // tester wrote, and the id Appendix R's treatment plans walk.
            'last_result_date'    => soa_text_or_null($last['date'] ?? null),
            'summary'             => soa_text_or_null($last['summary'] ?? null),
            'result_id'           => (int)($last['result_id'] ?? 0),
            // `audit_id`, `evidence_supplied` and `required_evidence` USED TO BE
            // HERE. All three served the required-evidence marker and have no
            // reader left; `required_evidence` was also the only WYSIWYG column
            // this map projected, so it took soa_plain_text_or_null() with it.
        ];
    }

    return $out;
}

/******************************************************************************
 * soa_audit_evidence_map() USED TO BE HERE.
 *
 * It answered "does this audit carry at least one uploaded file?" with a COUNT
 * over `compliance_files` (never a read -- `content` is a LONGBLOB), so that the
 * Evidence column could mark a test whose declared required-evidence had not
 * arrived. That marker was removed from every rendering because it re-judged a
 * verdict the tester had already recorded, using a file-attachment check with no
 * bearing on pass/fail, and it fired on 44 of 60 evidence items.
 *
 * With no reader, the function and its query retire. soa_test_map() is now two
 * queries rather than three.
 *
 * IF SOMETHING LIKE IT COMES BACK, it does not belong on the SoA. Whether a
 * particular test's paperwork is complete is a fact about the TEST, and Define
 * Tests is where it can be stated against the record that owns it.
 ******************************************************************************/

/****************************************************
 * FUNCTION: SOA REMEDIATION MAP                    *
 ****************************************************
 * test result id => the risks that result was linked to, each with its
 * mitigation's plan.
 *
 * The chain clause 6.1.3(e) cares about, walked in one query for the whole
 * framework:
 *
 *   framework_control_test_results_to_risks -> risks -> mitigations -> user
 *
 * KEYED ON THE RESULT, not the test. Risks are linked when a result is RECORDED,
 * so the links that belong to today's failure are the ones on today's result
 * row. Aggregating every result a test ever produced would cite risks raised
 * against a failure that was closed two years ago as though they were the
 * current plan.
 *
 * INNER JOIN to `risks` for the same reason soa_risk_map() has one: a mitigation
 * whose risk has been deleted must not put a number in an audit document that
 * the auditor cannot open. LEFT JOIN to `mitigations`, deliberately — a risk
 * that was raised and never planned is a finding the SoA should show, not a row
 * it should drop.
 *
 * Risk ids are returned as the DISPLAYED id (`id` + 1000), which is what every
 * SimpleRisk surface shows and the only number a reader can look up. `user` is
 * not in the Encrypted Database Extra's field map, so the owner's name is joined
 * directly rather than resolved per row.
 *
 * @param  int[] $result_ids
 * @return array<int, array<int, array{risk_id:int, planning_date:?string,
 *                                     mitigation_owner:?string,
 *                                     mitigation_percent:?int, planned:bool}>>
 */
function soa_remediation_map(array $result_ids): array {

    $ids = array_values(array_unique(array_filter(
        array_map('intval', $result_ids), static fn($id) => $id > 0
    )));

    if (empty($ids)) {
        return [];
    }

    $db = db_open();

    $stmt = $db->prepare(
        "SELECT r2r.`test_results_id`, r2r.`risk_id`,
                m.`id` AS `mitigation_id`, m.`planning_date`,
                m.`mitigation_percent`, u.`name` AS `mitigation_owner`
         FROM `framework_control_test_results_to_risks` r2r
         INNER JOIN `risks` r ON r.`id` = r2r.`risk_id`
         LEFT JOIN `mitigations` m ON m.`risk_id` = r2r.`risk_id`
         LEFT JOIN `user` u ON u.`value` = m.`mitigation_owner`
         WHERE r2r.`test_results_id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
         ORDER BY r2r.`test_results_id` ASC, r2r.`risk_id` ASC, m.`id` ASC;"
    );
    $stmt->execute($ids);

    $out  = [];
    $seen = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $result_id = (int)$row['test_results_id'];
        $risk_id   = (int)$row['risk_id'];

        // ONE LINE PER RISK. `mitigations`.`risk_id` carries no unique key, and
        // a risk listed twice reads as two separate treatment plans.
        if (isset($seen[$result_id][$risk_id])) {
            continue;
        }

        $seen[$result_id][$risk_id] = true;

        $planned = $row['mitigation_id'] !== null;

        $out[$result_id][] = [
            // The DISPLAYED id — management/view.php?id= takes this, the raw
            // primary key matches nothing a reader can look up.
            'risk_id'            => $risk_id + 1000,
            // THE ZERO DATE IS THE "NO VALUE" SENTINEL, NOT A DATE IN 1899, and it
            // is normalised to NULL here so no sink has to know that.
            //
            // MEASURED IN A RENDERED PDF: `mitigations`.`planning_date` is NOT NULL
            // with a '0000-00-00' default, so a mitigation saved without a target
            // date printed "Planned Mitigation Date: 0000-00-00" into Appendix R of
            // an audit document. soa_text_or_null() only trims, so it passed the
            // sentinel straight through -- the same trap soa_evidence_for() and the
            // retired soa_review_cadence() both guard against for `next_date`, and
            // the one place on this path that had not.
            //
            // NULL means the line simply omits the date, which is honest: nobody
            // recorded one.
            'planning_date'      => $planned ? soa_zero_date_or_null($row['planning_date']) : null,
            'mitigation_owner'   => $planned ? soa_text_or_null($row['mitigation_owner']) : null,
            'mitigation_percent' => $planned ? (int)$row['mitigation_percent'] : null,
            // FALSE means the risk exists and nothing has been planned against
            // it. Distinct from the test having no risk at all, which
            // soa_remediation_for() flags separately.
            'planned'            => $planned,
        ];
    }

    db_close($db);

    return $out;
}

/****************************************************
 * FUNCTION: SOA REMEDIATION FOR                    *
 ****************************************************
 * One control's failing tests and what is being done about each — the answer to
 * the question an auditor asks the moment a control reads Partial or No.
 *
 * EVERY FAILING TEST, NOT THE FIRST. A control verified by four tests of which
 * three fail has three findings, and a document that showed one of them would be
 * understating a position the customer attests to.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A FAILING TEST WITH NO LINKED RISK IS RETURNED, FLAGGED — NOT OMITTED.
 * ─────────────────────────────────────────────────────────────────────────────
 * It is the single most serious thing this column can find: the control failed
 * and NOTHING IS PLANNED. Dropping the row for want of a risk to join to would
 * render that as a blank cell, which reads as "nothing to report" — concealment
 * by omission, and precisely the failure mode the whole SoA redesign exists to
 * remove. `unplanned` says it out loud instead.
 *
 * Live tests only (soa_live_tests()), and "failing" is
 * test_last_result_is_failing() over last_result_state_family() — the SAME
 * vocabulary soa_implemented_for() derived the Partial/No from, so the remediation
 * block cannot list a test the status column did not count.
 *
 * Pure: no DB, no globals, no output. The DB half is soa_remediation_map().
 *
 * @param  array $row              an SoA row carrying `tests`
 * @param  array $remediation_map  soa_remediation_map()'s output
 * @return array<int, array>
 */
function soa_remediation_for(array $row, array $remediation_map): array {

    $out = [];

    foreach (soa_live_tests($row) as $test) {

        $result = soa_test_latest_result($test);

        if ($result === null || !test_last_result_is_failing(last_result_state_family($result))) {
            continue;
        }

        $risks = $remediation_map[(int)($test['result_id'] ?? 0)] ?? [];

        $out[] = [
            'test_id'          => (int)($test['id'] ?? 0),
            'test_name'        => $test['name'] ?? null,
            'test_result'      => $result,
            // Both dates: the test's own last window, and when the failing
            // result was actually performed. They differ whenever a test was
            // rescheduled after its last run.
            'last_date'        => $test['last_date'] ?? null,
            'last_result_date' => $test['last_result_date'] ?? null,
            'summary'          => $test['summary'] ?? null,
            'risks'            => $risks,
            // THE FINDING. True when the failure is traceable to no risk at all.
            'unplanned'        => empty($risks),
        ];
    }

    return $out;
}

/****************************************************
 * FUNCTION: SOA EVIDENCE FOR                       *
 ****************************************************
 * One control's evidence, as items rather than a sentence: the documents that
 * show the control's DESIGN and the tests that show its OPERATION.
 *
 * Both halves belong. A policy proves somebody wrote the control down; a test
 * result proves it runs. An auditor asks for both, and the two absences are not
 * the same absence.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ONLY A TEST THAT PRODUCED A RESULT IS EVIDENCE.
 * ─────────────────────────────────────────────────────────────────────────────
 * A live test that has never run substantiates NOTHING, so it is not an evidence
 * item — it is already visible as the `never_run` implementation state, which is
 * where that fact belongs. Counting it here would fill the Evidence column for a
 * control nothing has ever verified, and destroy the one derived display state
 * §3 of the design turns on: applicable, claimed implemented, and NO EVIDENCE.
 * That gap is derived from this list being empty, and is never stored.
 *
 * Retired tests are excluded (soa_live_tests()): last year's retired test is not
 * this year's evidence.
 *
 * THE ITEM IS SLIM BY CONSTRUCTION, not slim by projection. What a bullet needs
 * is the kind, the name, the verdict and the dates; anything else was carried and
 * never printed. See the note inside the test loop for the four fields that used
 * to be here and why each one left.
 *
 * Pure: no DB, no globals, no output.
 *
 * @param  array $row        an SoA row carrying `tests`
 * @param  array $documents  soa_document_map()'s entry for this control
 * @return array<int, array>
 */
function soa_evidence_for(array $row, array $documents): array {

    $out = [];

    // DESIGN — the confirmed policy mappings (`selected` = 1; see
    // soa_document_map() for why that predicate is load-bearing).
    foreach ($documents as $document) {
        $out[] = [
            'type'        => 'document',
            'name'        => (string)($document['name'] ?? ''),
            // WHICH KIND of design evidence. A policy and a procedure are not
            // interchangeable to an auditor -- one states intent, the other
            // states how it is carried out -- and the SoA used to flatten all
            // four document types into one undifferentiated list of names.
            // Raw, unlocalized; each sink resolves its own label.
            'document_type' => (string)($document['document_type'] ?? ''),
        ];
    }

    // OPERATION — the tests that actually produced a verdict.
    foreach (soa_live_tests($row) as $test) {

        $result = soa_test_latest_result($test);

        if ($result === null) {
            continue;
        }

        // `next_date` IS PER TEST, WHICH IS WHY IT IS HERE.
        //
        // The Verification cell briefly printed ONE "Next due <earliest>" line
        // above the whole evidence list. That was wrong in a way a reader
        // noticed immediately: a control with three tests has three schedules,
        // so a single date above three bullets cannot say which test it is the
        // schedule for. Carried on the item, each test's bullet states its own
        // next date beside its own last result.
        $next = trim((string)($test['next_date'] ?? ''));

        // `test_id` AND `document_id` ARE NOT HERE, and neither is
        // `required_evidence` / `evidence_supplied`.
        //
        // The two ids were never rendered by any sink — an item is identified to
        // the reader by its name — so they were payload for nothing.
        //
        // The other two were the input to a "required evidence not supplied"
        // marker, and that marker is gone from every rendering (see the block
        // comment in js/simplerisk/pages/statement-of-applicability.js where it
        // used to be built). It re-judged a verdict the tester had already
        // recorded, using a file-attachment check with no bearing on pass/fail,
        // and it fired on 44 of 60 evidence items on the ISO 27001 framework — a
        // "finding" that appears on three-quarters of the population teaches a
        // reader to discount the document's real markers. `required_evidence` was
        // also the one WYSIWYG field in the itemisation, whole paragraphs per
        // test, so dropping it is where the size actually was.
        $out[] = [
            'type'        => 'test',
            'name'        => $test['name'] ?? null,
            'test_result' => $result,
            'last_date'   => $test['last_result_date'] ?? ($test['last_date'] ?? null),
            // The zero date is the "no value" sentinel, normalised to null here so
            // no sink has to know that.
            'next_date'   => ($next !== '' && strpos($next, '0000-00-00') !== 0) ? $next : null,
        ];
    }

    return $out;
}

/****************************************************
 * FUNCTION: SOA FRAMEWORK REFUSAL                  *
 ****************************************************
 * Whether a Statement of Applicability may be generated for this framework, and
 * if not, the machine-readable reason.
 *
 * THREE REFUSALS, each a deliberate position:
 *
 *   framework_required — there is no framework. An SoA is a per-framework
 *       document by definition: the same control excluded from ISO 27001 is not
 *       thereby excluded from PCI DSS, which is why applicability is stored per
 *       (framework, control_id) and why the controls page withholds the
 *       applicability column entirely under "All frameworks". There is no
 *       Applicable-in-N-of-M roll-up to fall back on, and inventing one would
 *       mean answering a question the data model does not answer.
 *
 *   framework_not_found — self-explanatory. A 404, not an empty document: an SoA
 *       with no rows and a blank cover looks like a framework with no controls.
 *
 *   framework_inactive — the framework is deactivated (status 2). REFUSED. An SoA
 *       is a live assertion about the scope an organisation currently operates
 *       under; a deactivated framework has been withdrawn from the programme, and
 *       issuing a document titled "Statement of Applicability — <framework>" for
 *       one asserts a certification scope the organisation has retired. The
 *       codebase already treats deactivation this way: every governance aggregate
 *       and get_framework_controls_by_filter()'s own
 *       `LEFT JOIN frameworks f ... AND f.status=1` scope to active frameworks.
 *       Refusing at the DOCUMENT boundary rather than hiding the control also
 *       means the user gets a reason instead of a missing button.
 *
 *       NOTE FOR SR-1988, which proposes replacing Active/Inactive with Archive:
 *       this report is the first consumer to take a position, and the position is
 *       "an archived framework is not a live scope statement". If Archive gains a
 *       distinct "retained for historical reference" meaning, the right change is
 *       to let this function admit an archived framework and have the report stamp
 *       the document as historical — NOT to drop the check, which would silently
 *       start issuing current-tense SoAs for retired scopes.
 *
 * @return array{allowed:bool, error:string, status:int} `error` is '' when allowed.
 */
function soa_framework_refusal(int $framework): array {

    if ($framework <= 0) {
        return ['allowed' => false, 'error' => 'framework_required', 'status' => 400];
    }

    $meta = soa_framework_meta($framework);

    if (!$meta['exists']) {
        return ['allowed' => false, 'error' => 'framework_not_found', 'status' => 404];
    }

    // 1 = active, 2 = inactive (get_frameworks(), includes/governance.php).
    if ($meta['status'] !== 1) {
        return ['allowed' => false, 'error' => 'framework_inactive', 'status' => 409];
    }

    return ['allowed' => true, 'error' => '', 'status' => 200];
}

?>
