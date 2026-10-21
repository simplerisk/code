/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Contributing Risk scoring math -- matches update_contributing_risk_score()'s
// own logic exactly (includes/functions.php): a weighted sum of a shared
// Likelihood term plus one term per currently-configured contributing_risks
// factor. Unlike DREAD's fixed 5-field average or OWASP's fixed 16-field
// severity matrix, the factor ROSTER here is genuinely dynamic -- there is
// no FIELD_NAMES constant the way owasp-scoring.js/dread-scoring.js have
// one, because the set of factors (and each factor's own weight/max impact)
// is admin-configured and can change at any time. The caller (risk-details-
// form.js's buildContributingRiskHolder()) passes the SAME `field` object
// (carrying field.contributing_risks/field.contributing_likelihood_options)
// the holder was built from, keeping this file free of any dependency on
// that file or a network fetch of its own -- the same "pure DOM + math"
// rule cvss-v2-scoring.js/dread-scoring.js/owasp-scoring.js already follow.
//
// Bounded 0-10 by save_contributing_risks()'s own admin-config-time
// validation (includes/functions.php) that rejects any factor-weight save
// where the factors' weights don't sum to 1 -- this file does NOT clamp its
// own result, matching this phase's own spec.
//
// Public surface (window.ContributingRiskScoring):
//   calculateContributingRisk(root, field) -- recompute the score display
//                        from the Likelihood select's current value, every
//                        rendered factor's own Impact select, and the given
//                        field.contributing_risks/contributing_likelihood_options
//                        config, and return the score. `root` (optional,
//                        defaults to `document`) -- same detached-DOM-safe
//                        scoping-root support dread-scoring.js's
//                        calculateDread(root) already has.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    // Scoped to the nearest .contributing-risk-holder ancestor -- same
    // defensive pattern as cvss-v2-scoring.js's/dread-scoring.js's/
    // owasp-scoring.js's own holderEl().
    function holderEl(id, root) {
        return (root || document).querySelector('.contributing-risk-holder #' + id);
    }

    function maxOptionValue(options) {
        return (options || []).reduce(function (max, opt) {
            return Math.max(max, Number(opt.value));
        }, 0);
    }

    // score.php parity: the legacy table named the max option (e.g.
    // "[5] Certain"), not just its number -- see ContributingLikelihoodFormula/
    // ContributingFactorFormula's own comment (lang.en.php) for where the
    // {maxName} token these feed goes. Ties keep the FIRST match, same as
    // maxOptionValue()'s own reduce would if it tracked names too.
    function maxOptionName(options) {
        var max = maxOptionValue(options);
        var match = (options || []).filter(function (opt) {
            return Number(opt.value) === max;
        })[0];
        return match ? match.name : '';
    }

    function calculateContributingRisk(root, field) {
        var contributingRisks = field.contributing_risks || [];
        var likelihoodOptions = field.contributing_likelihood_options || [];

        var likelihoodEl = holderEl('ContributingLikelihood', root);
        var likelihoodValue = likelihoodEl ? (Number(likelihoodEl.value) || 0) : 0;
        var maxLikelihood = maxOptionValue(likelihoodOptions);
        var likelihoodSum = maxLikelihood ? (likelihoodValue * 5 / maxLikelihood) : 0;

        // Subscore value + formula now live in the Score card
        // (ContributingLikelihoodSubscore/-Formula, buildContributingRiskHolder()'s
        // own summary row) rather than under the Likelihood select itself --
        // same "computed breakdown in the Score card" shape owasp-scoring.js's
        // calculateOwasp() already writes for its own 4 subscore rows.
        // risk-details-view.js's read-mode buildContributingRiskReadView()
        // duplicates this same substitution for the identical reason its own
        // comment gives. _lang is a page global -- ContributingLikelihoodFormula
        // is registered for both risk-details-form.js and risk-details-view.js
        // in header.php.
        var likelihoodSubscoreEl = holderEl('ContributingLikelihoodSubscore', root);
        if (likelihoodSubscoreEl) {
            likelihoodSubscoreEl.textContent = Math.round(likelihoodSum * 100) / 100;
        }
        var likelihoodFormulaEl = holderEl('ContributingLikelihoodSubscoreFormula', root);
        if (likelihoodFormulaEl && window._lang && maxLikelihood) {
            likelihoodFormulaEl.textContent = window._lang.ContributingLikelihoodFormula
                .replace('{value}', likelihoodValue).replace('{max}', maxLikelihood)
                .replace('{maxName}', maxOptionName(likelihoodOptions));
        }

        // Impact selects are looked up by a bracket-free id
        // ('ContributingImpacts_' + factor.id) -- their wire-level `name`
        // attribute carries the real bracketed POST identifier
        // ("ContributingImpacts[5]") separately; a CSS id selector cannot
        // safely contain '[' ']' without escaping, so the two attributes
        // are deliberately different strings (see buildContributingRiskFactorItem(),
        // risk-details-form.js).
        var impactSum = contributingRisks.reduce(function (sum, factor) {
            var select = holderEl('ContributingImpacts_' + factor.id, root);
            if (!select) {
                return sum; // Review Focus: a factor the config lists but
                            // whose row failed to render for some reason --
                            // skip rather than throw.
            }
            var impactValue = Number(select.value) || 0;
            var maxImpact = maxOptionValue(factor.impact_options);
            var factorTerm = maxImpact ? (Number(factor.weight) * (impactValue * 5 / maxImpact)) : 0;

            // Same Score-card-subscore treatment likelihoodSubscoreEl/
            // likelihoodFormulaEl above write -- one row PER FACTOR
            // (buildContributingRiskHolder()'s own summary row, risk-details-
            // form.js), since the factor roster is dynamic.
            var factorSubscoreEl = holderEl('ContributingFactorSubscore_' + factor.id, root);
            if (factorSubscoreEl) {
                factorSubscoreEl.textContent = Math.round(factorTerm * 100) / 100;
            }
            var factorFormulaEl = holderEl('ContributingFactorSubscoreFormula_' + factor.id, root);
            if (factorFormulaEl && window._lang && maxImpact) {
                var weightPct = Math.round(Number(factor.weight) * 100);
                factorFormulaEl.textContent = window._lang.ContributingFactorFormula
                    .replace('{weight}', weightPct).replace('{impact}', impactValue).replace('{max}', maxImpact)
                    .replace('{maxName}', maxOptionName(factor.impact_options));
            }

            if (!maxImpact) {
                return sum;
            }
            return sum + factorTerm;
        }, 0);

        // Contributing Risk's own subscore row (buildContributingRiskHolder()'s
        // summary, risk-details-form.js) -- the sum every factor row above
        // rolls up into, one level above them (see that function's own
        // comment on the 2-level nesting this and the per-factor rows form).
        // Generic, not per-factor-substituted -- unlike DreadScoreFormula/
        // OwaspSubgroupFormula's fixed term count, the number of terms here
        // is however many factors are configured.
        var subtotalEl = holderEl('ContributingRiskSubtotal', root);
        if (subtotalEl) {
            subtotalEl.textContent = Math.round(impactSum * 100) / 100;
        }
        var subtotalFormulaEl = holderEl('ContributingRiskSubtotalFormula', root);
        if (subtotalFormulaEl && window._lang) {
            subtotalFormulaEl.textContent = window._lang.ContributingRiskSubtotalFormula;
        }

        var score = Math.round((likelihoodSum + impactSum) * 100) / 100;

        var scoreEl = holderEl('ContributingScore', root);
        if (scoreEl) {
            scoreEl.textContent = score;
        }

        // Contributing Risk Score's own formula -- always exactly 2 terms
        // (the Likelihood and Contributing Risk subtotals just above), so
        // substituted with their real current values, same convention
        // DreadScoreFormula/OwaspSubgroupFormula use for their own fixed
        // roster.
        var scoreFormulaEl = holderEl('ContributingRiskScoreFormula', root);
        if (scoreFormulaEl && window._lang) {
            scoreFormulaEl.textContent = window._lang.ContributingRiskScoreFormula
                .replace('{likelihood}', Math.round(likelihoodSum * 100) / 100)
                .replace('{contributing}', Math.round(impactSum * 100) / 100);
        }

        return score;
    }

    window.ContributingRiskScoring = {
        calculateContributingRisk: calculateContributingRisk
    };
})();
