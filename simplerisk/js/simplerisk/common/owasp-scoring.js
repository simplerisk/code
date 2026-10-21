/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// OWASP Risk Rating Methodology scoring math -- matches
// update_owasp_score()'s own logic exactly (includes/functions.php):
// 4 Threat Agent + 4 Vulnerability factors average into a Likelihood
// category (LOW/MEDIUM/HIGH); 4 Technical + 4 Business Impact factors
// average into an Impact category; a Likelihood x Impact severity matrix
// resolves to a number -- LOW/LOW and HIGH/HIGH are hardcoded (0 and 10),
// the three middle tiers each average two SPECIFIC NAMED risk_levels rows
// together, the same live DB query update_owasp_score() runs
// (SELECT AVG(value) FROM risk_levels WHERE name IN (...)). This file has
// no way to run that query itself -- the caller (risk-details-form.js's
// buildOwaspHolder(), via window.CvssRiskLevelPill.levels()) fetches the
// already-cached risk_levels array and passes it in as `levels`, keeping
// this file free of any dependency on that file, the same "pure DOM +
// math" rule cvss-v2-scoring.js/dread-scoring.js already follow.
//
// FIELD_NAMES are the real POST/wire identifiers addRisk()/updateRisk()
// read (includes/api.php: get_param("post", "OWASPSkillLevel") etc.) --
// verified directly against that source, not update_owasp_score()'s own
// PHP parameter names, which differ for 3 of the 16 fields (SkillLevel/
// EaseOfDiscovery/EaseOfExploit) -- see this phase's own spec for the
// full verified-name table. This is the exact class of bug the DREAD
// phase caught only during implementation review; here it's verified up
// front.
//
// Public surface (window.OwaspScoring):
//   FIELD_NAMES       -- the 16 OWASP metric field names, in the same
//                        order the holder renders them
//   calculateOwasp(root, levels) -- recompute the score display from the
//                        16 visible selects' current values and the
//                        given risk_levels array, and return the score.
//                        Also writes the 4 factor-group averages (Threat
//                        Agent Factors / Vulnerability Factors /
//                        Technical Impact / Business Impact) to their own
//                        DOM elements as a side effect, so the Score
//                        card's 4 extra rows stay live along with the
//                        score itself. `root` (optional, defaults to
//                        `document`) -- same detached-element support
//                        dread-scoring.js's calculateDread(root) already
//                        has.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    var FIELD_NAMES = [
        'OWASPSkillLevel', 'OWASPMotive', 'OWASPOpportunity', 'OWASPSize',
        'OWASPEaseOfDiscovery', 'OWASPEaseOfExploit', 'OWASPAwareness', 'OWASPIntrusionDetection',
        'OWASPLossOfConfidentiality', 'OWASPLossOfIntegrity', 'OWASPLossOfAvailability', 'OWASPLossOfAccountability',
        'OWASPFinancialDamage', 'OWASPReputationDamage', 'OWASPNonCompliance', 'OWASPPrivacyViolation'
    ];

    // Scoped to the nearest .owasp-holder ancestor -- same defensive
    // pattern as cvss-v2-scoring.js's/dread-scoring.js's own holderEl().
    function holderEl(id, root) {
        return (root || document).querySelector('.owasp-holder #' + id);
    }

    function categoryFor(avg) {
        if (avg < 3) { return 'LOW'; }
        if (avg < 6) { return 'MEDIUM'; }
        return 'HIGH';
    }

    // Averages the value of every risk_levels row whose name is in
    // `names` together -- the SAME operation update_owasp_score()'s live
    // SQL runs (SELECT AVG(value) FROM risk_levels WHERE name IN (...)),
    // just against the already-fetched array instead of a fresh query.
    // Distinct from matchRiskLevel() (risk-details-form.js), which finds
    // the single highest-value tier AT OR BELOW a score -- this averages
    // two SPECIFIC named tiers together, a different operation entirely.
    function averageNamedLevels(names, levels) {
        var matching = levels.filter(function (level) {
            return names.indexOf(level.name) !== -1;
        });
        if (!matching.length) {
            return 0;
        }
        var sum = matching.reduce(function (acc, level) {
            return acc + Number(level.value);
        }, 0);
        return Math.round((sum / matching.length) * 100) / 100;
    }

    // The exact 5-branch matrix update_owasp_score() computes.
    function severityScore(likelihoodCategory, impactCategory, levels) {
        if (likelihoodCategory === 'LOW' && impactCategory === 'LOW') {
            return 0;
        }
        if (likelihoodCategory === 'HIGH' && impactCategory === 'HIGH') {
            return 10;
        }
        if ((likelihoodCategory === 'LOW' && impactCategory === 'MEDIUM') || (likelihoodCategory === 'MEDIUM' && impactCategory === 'LOW')) {
            return averageNamedLevels(['Low', 'Medium'], levels);
        }
        if ((likelihoodCategory === 'LOW' && impactCategory === 'HIGH') || (likelihoodCategory === 'MEDIUM' && impactCategory === 'MEDIUM') || (likelihoodCategory === 'HIGH' && impactCategory === 'LOW')) {
            return averageNamedLevels(['Medium', 'High'], levels);
        }
        // Remaining combinations: (MEDIUM, HIGH) or (HIGH, MEDIUM).
        return averageNamedLevels(['High', 'Very High'], levels);
    }

    function calculateOwasp(root, levels) {
        var v = {};
        FIELD_NAMES.forEach(function (name) {
            v[name] = Number(holderEl(name, root).value) || 0;
        });

        var threatAgent = (v.OWASPSkillLevel + v.OWASPMotive + v.OWASPOpportunity + v.OWASPSize) / 4;
        var vulnerability = (v.OWASPEaseOfDiscovery + v.OWASPEaseOfExploit + v.OWASPAwareness + v.OWASPIntrusionDetection) / 4;
        var likelihoodAvg = (threatAgent + vulnerability) / 2;
        var likelihoodCategory = categoryFor(likelihoodAvg);

        var technicalImpact = (v.OWASPLossOfConfidentiality + v.OWASPLossOfIntegrity + v.OWASPLossOfAvailability + v.OWASPLossOfAccountability) / 4;
        var businessImpact = (v.OWASPFinancialDamage + v.OWASPReputationDamage + v.OWASPNonCompliance + v.OWASPPrivacyViolation) / 4;
        var impactAvg = (technicalImpact + businessImpact) / 2;
        var impactCategory = categoryFor(impactAvg);

        // The 4 factor-group averages, displayed under the Score card --
        // always computed internally to derive Likelihood/Impact category,
        // just never surfaced before now. Same 2-decimal rounding
        // averageNamedLevels() already uses, for visual consistency with
        // the main score's own precision. likelihoodAvg/impactAvg (their
        // own parents in the Score card's new hierarchy) are the SAME
        // values categoryFor() already consumed above -- named out here
        // purely so this write block doesn't recompute them.
        holderEl('OwaspThreatAgentFactors', root).textContent = Math.round(threatAgent * 100) / 100;
        holderEl('OwaspVulnerabilityFactors', root).textContent = Math.round(vulnerability * 100) / 100;
        holderEl('OwaspTechnicalImpact', root).textContent = Math.round(technicalImpact * 100) / 100;
        holderEl('OwaspBusinessImpact', root).textContent = Math.round(businessImpact * 100) / 100;
        holderEl('OwaspLikelihood', root).textContent = Math.round(likelihoodAvg * 100) / 100;
        holderEl('OwaspImpact', root).textContent = Math.round(impactAvg * 100) / 100;

        // Same ".sr-cvss-vector" caption treatment risk-details-view.js's
        // read-mode owaspFormulaText() already established for this exact
        // formula -- ported here so the edit-mode holder shows it too and
        // stays live on every change (see buildOwaspHolder()'s own
        // 4 *Formula ids, risk-details-form.js). _lang is a page global
        // (OwaspSubgroupFormula is registered for both risk-details-form.js
        // and risk-details-view.js in header.php).
        if (window._lang) {
            var formula = window._lang.OwaspSubgroupFormula;
            var writeFormula = function (id, a, b, c, d) {
                var el = holderEl(id, root);
                if (el) {
                    el.textContent = formula.replace('{a}', a).replace('{b}', b).replace('{c}', c).replace('{d}', d);
                }
            };
            writeFormula('OwaspThreatAgentFactorsFormula', v.OWASPSkillLevel, v.OWASPMotive, v.OWASPOpportunity, v.OWASPSize);
            writeFormula('OwaspVulnerabilityFactorsFormula', v.OWASPEaseOfDiscovery, v.OWASPEaseOfExploit, v.OWASPAwareness, v.OWASPIntrusionDetection);
            writeFormula('OwaspTechnicalImpactFormula', v.OWASPLossOfConfidentiality, v.OWASPLossOfIntegrity, v.OWASPLossOfAvailability, v.OWASPLossOfAccountability);
            writeFormula('OwaspBusinessImpactFormula', v.OWASPFinancialDamage, v.OWASPReputationDamage, v.OWASPNonCompliance, v.OWASPPrivacyViolation);
        }

        var score = severityScore(likelihoodCategory, impactCategory, levels);

        holderEl('OwaspScore', root).textContent = score;

        return score;
    }

    window.OwaspScoring = {
        FIELD_NAMES: FIELD_NAMES,
        calculateOwasp: calculateOwasp
    };
})();
