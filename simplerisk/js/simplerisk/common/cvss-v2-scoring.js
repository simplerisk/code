/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// ----------------------------------------------------------------------
// CVSS v2 scoring math -- relocated verbatim from management/partials/
// cvss_modal_content.php's own inline <script> (the legacy modal keeps its
// own copy too; this file does not replace that partial, which other,
// still-live legacy entry points continue to render). No formula constant
// or calculation changed in the move, or in this file's later retargeting
// from a page-level #cvssModal singleton to the inline .cvss-holder widget.
//
// Public surface (window.CvssV2Scoring):
//   FIELD_NAMES     -- the 14 CVSS v2 metric field names, in the same
//                      order the widget renders them
//   calculateCVSS() -- recompute all 5 score displays from the 14 visible
//                      selects' current values (edit-mode .cvss-holder)
//   computeScores(values) -- the same math, pure: takes a plain object
//                      mapping each FIELD_NAMES entry to its raw value
//                      (no DOM reads or writes), returns all 5 scores plus
//                      the cascading CurrentScore. Added for the read-mode
//                      Details tab (risk-details-view.js), which has the
//                      14 raw metric values from the API but no live
//                      .cvss-holder selects to read them from.
//   computeVectors(values) -- same shape/pure as computeScores(), returns
//                      the standard CVSS v2 vector notation (e.g. "AV:N/
//                      AC:L/Au:N/C:P/I:P/A:N") split per score, for the
//                      light caption shown under each score display in
//                      both edit and read mode.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    var FIELD_NAMES = [
        'AccessVector', 'AccessComplexity', 'Authentication',
        'ConfImpact', 'IntegImpact', 'AvailImpact',
        'Exploitability', 'RemediationLevel', 'ReportConfidence',
        'CollateralDamagePotential', 'TargetDistribution',
        'ConfidentialityRequirement', 'IntegrityRequirement', 'AvailabilityRequirement'
    ];

    // Base metric numeric values
    var CVSS2_BASE_VALUES = {
        AccessVector: { N: 1.0, A: 0.646, L: 0.395 },
        AccessComplexity: { L: 0.71, M: 0.61, H: 0.35 },
        Authentication: { N: 0.704, S: 0.56, M: 0.45 },
        Impact: { N: 0.0, P: 0.275, C: 0.66 }
    };

    // Temporal metric numeric values
    var CVSS2_EXPLOITABILITY_VALUES = { ND: 1.0, U: 0.85, POC: 0.9, F: 0.95, H: 1.0 };
    var CVSS2_REMEDIATION_LEVEL_VALUES = { ND: 1.0, OF: 0.87, TF: 0.90, W: 0.95, U: 1.0 };
    var CVSS2_REPORT_CONFIDENCE_VALUES = { ND: 1.0, UC: 0.90, UR: 0.95, C: 1.0 };

    // Environmental metric numeric values
    var CVSS2_ENV_VALUES = {
        CDP: { ND: 0.0, N: 0.0, L: 0.1, LM: 0.3, MH: 0.4, H: 0.5 },
        TD: { ND: 1.0, N: 0.0, L: 0.25, M: 0.75, H: 1.0 }
    };

    // Impact requirement modifiers
    var CVSS2_IMPACT_MODIFIER = { ND: 1.0, L: 0.5, M: 1.0, H: 1.51 };

    // management/view.php (and every other page that can show an EXISTING
    // CVSS-scored risk) also renders a completely separate, pre-existing
    // legacy "Update Score" accordion (includes/display.php's
    // edit_cvss_score(), management/partials/score.php's #updatescore --
    // always present, collapsed, whenever a risk's stored scoring_method is
    // CVSS) that reuses these SAME literal metric ids via
    // create_cvss_dropdown(). Those ids cannot change here -- cve_lookup.js
    // depends on them verbatim -- so a bare document.getElementById(fieldName)
    // is ambiguous the instant both exist on the page, and getElementById
    // always resolves to whichever is FIRST in document order: the legacy
    // accordion's, since it's part of the page's original server-rendered
    // HTML. Scoping every lookup to a descendant of .cvss-holder -- the only
    // container this file's math has ever been about -- resolves the
    // ambiguity without touching the legacy accordion (a separate,
    // independently-live feature tracked for full retirement under SR-2279).
    function holderEl(id) {
        return document.querySelector('.cvss-holder #' + id);
    }

    function roundTo1Decimal(num) {
        return Math.round(num * 10) / 10;
    }

    // Pure from here through pickCurrentScore(): every function in this
    // block takes a plain `values` object (FIELD_NAMES -> raw value, e.g.
    // {AccessVector: 'N', ConfImpact: 'C', ...}; '' / undefined / null means
    // unset, matching the blank '--' option) and does no DOM reads or
    // writes. The DOM-driven wrappers below (getBaseMetricsFromHolder() and
    // the exported calculateCVSS()) are thin adapters over this math, not a
    // second copy of it -- keeps the edit-mode widget's live-select behavior
    // and computeScores()'s read-mode use identical by construction.
    function metricsFromValues(values) {
        var AV = CVSS2_BASE_VALUES.AccessVector[values.AccessVector] || 0;
        var AC = CVSS2_BASE_VALUES.AccessComplexity[values.AccessComplexity] || 0;
        var Au = CVSS2_BASE_VALUES.Authentication[values.Authentication] || 0;
        var C = CVSS2_BASE_VALUES.Impact[values.ConfImpact] || 0;
        var I = CVSS2_BASE_VALUES.Impact[values.IntegImpact] || 0;
        var A = CVSS2_BASE_VALUES.Impact[values.AvailImpact] || 0;
        return { AV: AV, AC: AC, Au: Au, C: C, I: I, A: A };
    }

    function computeBaseScore(values) {
        var m = metricsFromValues(values);
        var Impact = 10.41 * (1 - (1 - m.C) * (1 - m.I) * (1 - m.A));
        var Exploitability = 20 * m.AV * m.AC * m.Au;
        var f = Impact === 0 ? 0 : 1.176;
        var BaseScore = roundTo1Decimal(((0.6 * Impact) + (0.4 * Exploitability) - 1.5) * f);

        // Rounded here, not left raw: every caller (the edit-mode DOM
        // display below, and computeScores()'s read-mode result) wants the
        // same rounded figure a user sees elsewhere on the page.
        return { BaseScore: BaseScore, Exploitability: roundTo1Decimal(Exploitability), Impact: roundTo1Decimal(Impact) };
    }

    function computeTemporalScore(values, baseScore) {
        var E = CVSS2_EXPLOITABILITY_VALUES[values.Exploitability] || 1;
        var RL = CVSS2_REMEDIATION_LEVEL_VALUES[values.RemediationLevel] || 1;
        var RC = CVSS2_REPORT_CONFIDENCE_VALUES[values.ReportConfidence] || 1;
        return roundTo1Decimal(baseScore * E * RL * RC);
    }

    function computeEnvironmentalScore(values) {
        var m = metricsFromValues(values);
        var Exploitability = 20 * m.AV * m.AC * m.Au;

        var CR = CVSS2_IMPACT_MODIFIER[values.ConfidentialityRequirement] || 1;
        var IR = CVSS2_IMPACT_MODIFIER[values.IntegrityRequirement] || 1;
        var AR = CVSS2_IMPACT_MODIFIER[values.AvailabilityRequirement] || 1;

        var CDP = CVSS2_ENV_VALUES.CDP[values.CollateralDamagePotential] || 0;
        var TD = CVSS2_ENV_VALUES.TD[values.TargetDistribution] || 1;

        var ModifiedImpact = Math.min(10, 10.41 * (1 - (1 - m.C * CR) * (1 - m.I * IR) * (1 - m.A * AR)));

        var f = ModifiedImpact === 0 ? 0 : 1.176;
        var AdjustedBase = ((0.6 * ModifiedImpact) + (0.4 * Exploitability) - 1.5) * f;

        var E = CVSS2_EXPLOITABILITY_VALUES[values.Exploitability] || 1;
        var RL = CVSS2_REMEDIATION_LEVEL_VALUES[values.RemediationLevel] || 1;
        var RC = CVSS2_REPORT_CONFIDENCE_VALUES[values.ReportConfidence] || 1;
        var AdjustedTemporal = AdjustedBase * E * RL * RC;

        return roundTo1Decimal((AdjustedTemporal + (10 - AdjustedTemporal) * CDP) * TD);
    }

    // A field counts as "not specified" for cascading purposes exactly when
    // its CVSS_scoring.numeric_value would be the server's -1 sentinel
    // (score_type(), includes/cvss.php): either never touched (the blank
    // '--' option, value '' -- or absent entirely, undefined/null, which is
    // how a read-mode `values` object represents a metric the risk never
    // had a value for) or explicitly set to the CVSS v2 "Not Defined"
    // choice ('ND') -- all three mean the metric contributes nothing.
    function isUnsetForCascade(value) {
        return value === '' || value === undefined || value === null || value === 'ND';
    }

    // Mirrors score_type()/overall_score() (includes/cvss.php) client-side:
    // Environmental if either of its two fields is set, else Temporal if any
    // of its three fields is set, else Base. This is what decides which of
    // the 5 displayed scores is "the" current score for a CVSS-scored risk
    // -- the same figure submit_risk_scoring()/update_risk_scoring() persist
    // into risk_scoring.calculated_risk, which risk_levels thresholds (and
    // therefore every risk-level pill in the app) are matched against.
    function pickCurrentScore(values, scores) {
        if (!(isUnsetForCascade(values.CollateralDamagePotential) && isUnsetForCascade(values.TargetDistribution))) {
            return scores.EnvironmentalScore;
        }
        if (!(isUnsetForCascade(values.Exploitability) && isUnsetForCascade(values.RemediationLevel) && isUnsetForCascade(values.ReportConfidence))) {
            return scores.TemporalScore;
        }
        return scores.BaseScore;
    }

    // The read-mode entry point (risk-details-view.js): all 5 scores plus
    // the cascading CurrentScore (see pickCurrentScore()), computed purely
    // from a plain values object -- no .cvss-holder, no DOM, so this works
    // identically for a saved risk's read-only Details tab as it does for
    // the live edit-mode widget below.
    function computeScores(values) {
        var base = computeBaseScore(values);
        var TemporalScore = computeTemporalScore(values, base.BaseScore);
        var EnvironmentalScore = computeEnvironmentalScore(values);
        var scores = {
            BaseScore: base.BaseScore,
            ExploitabilitySubscore: base.Exploitability,
            ImpactSubscore: base.Impact,
            TemporalScore: TemporalScore,
            EnvironmentalScore: EnvironmentalScore
        };
        scores.CurrentScore = pickCurrentScore(values, scores);
        return scores;
    }

    // The standard CVSS v2 vector notation (e.g. "AV:N/AC:L/Au:N/C:P/I:P/
    // A:N"), shown as a light caption under each score so a reader can see
    // WHY a number is what it is without opening Advanced Metrics or
    // hovering every field's help popover. Split per score rather than one
    // block of "the whole vector" under Base Score alone: Exploitability
    // Score's caption is only the 3 metrics that feed it (AV/AC/Au), Impact
    // Score's only the 3 that feed it (C/I/A) -- Base Score gets both
    // together, since base_score() (includes/cvss.php) combines them. An
    // unset metric (never chosen, or the genuine CVSS v2 "Not Defined"
    // choice) renders as 'ND' either way -- this is a reading aid, not a
    // strict-spec vector the app parses back, so collapsing "blank" and
    // "explicitly ND" to the same literal keeps every caption filled in
    // instead of a metric silently missing from it.
    function computeVectors(values) {
        function v(name) { return values[name] || 'ND'; }
        var av = v('AccessVector'), ac = v('AccessComplexity'), au = v('Authentication');
        var c = v('ConfImpact'), i = v('IntegImpact'), a = v('AvailImpact');
        return {
            Base: 'AV:' + av + '/AC:' + ac + '/Au:' + au + '/C:' + c + '/I:' + i + '/A:' + a,
            Exploitability: 'AV:' + av + '/AC:' + ac + '/Au:' + au,
            Impact: 'C:' + c + '/I:' + i + '/A:' + a,
            Temporal: 'E:' + v('Exploitability') + '/RL:' + v('RemediationLevel') + '/RC:' + v('ReportConfidence'),
            Environmental: 'CDP:' + v('CollateralDamagePotential') + '/TD:' + v('TargetDistribution')
                + '/CR:' + v('ConfidentialityRequirement') + '/IR:' + v('IntegrityRequirement') + '/AR:' + v('AvailabilityRequirement')
        };
    }

    // Edit-mode entry point: reads the 14 live selects into a values object
    // (same shape computeScores()/computeVectors() take), delegates to both,
    // then writes the results into the matching score-display/vector-
    // caption elements -- the DOM side effect every existing caller of
    // calculateCVSS() (this file's own cve_lookup.js trigger,
    // buildCvssHolder()'s change handlers) depends on. Existing callers
    // that ignore the return value are unaffected. A vector caption
    // element is optional (holderEl() may return null) -- buildCvssHolder()
    // only renders one for the 5 score rows, not the 2 raw subscore
    // spans (ImpactSubscore/ExploitabilitySubscore), which are inputs to
    // Base Score's own display, not reader-facing rows of their own.
    function calculateCVSS() {
        var values = {};
        FIELD_NAMES.forEach(function (name) {
            var el = holderEl(name);
            values[name] = el ? el.value : '';
        });

        var scores = computeScores(values);
        holderEl('ImpactSubscore').textContent = scores.ImpactSubscore;
        holderEl('ExploitabilitySubscore').textContent = scores.ExploitabilitySubscore;
        holderEl('BaseScore').textContent = scores.BaseScore;
        holderEl('TemporalScore').textContent = scores.TemporalScore;
        holderEl('EnvironmentalScore').textContent = scores.EnvironmentalScore;

        var vectors = computeVectors(values);
        [
            ['BaseScoreVector', vectors.Base],
            ['ExploitabilityScoreVector', vectors.Exploitability],
            ['ImpactScoreVector', vectors.Impact],
            ['TemporalScoreVector', vectors.Temporal],
            ['EnvironmentalScoreVector', vectors.Environmental]
        ].forEach(function (pair) {
            var el = holderEl(pair[0]);
            if (el) {
                el.textContent = pair[1];
            }
        });

        return scores.CurrentScore;
    }

    window.CvssV2Scoring = {
        FIELD_NAMES: FIELD_NAMES,
        calculateCVSS: calculateCVSS,
        computeScores: computeScores,
        computeVectors: computeVectors
    };
})();
