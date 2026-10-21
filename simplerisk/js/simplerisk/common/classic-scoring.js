/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Classic Risk Formula scoring math -- matches calculate_risk()'s own
// 6-branch logic exactly (includes/functions.php:7407-7466): a range guard
// wrapping the whole formula (out of range -> default_risk_score), then 5
// arithmetic formulas keyed by the admin-configured `risk_model` (1-5), plus
// a model-6 live grid lookup (get_stored_risk_score(), functions.php:26570)
// against the `custom_risk_model_values` table. The already-fetched
// GET /riskformula/config response (risk_model, need_risk_score_normalization,
// likelihood_count, impact_count, default_risk_score, custom_risk_model_values)
// is taken as a plain `config` argument, keeping this file free of any
// dependency on risk-details-form.js or a network call of its own -- the
// same "pure DOM + math" isolation rule owasp-scoring.js/dread-scoring.js/
// cvss-v2-scoring.js already follow.
//
// Normalization mirrors calculate_risk()'s own unconditional post-branch
// step (functions.php:7449-7458: `round($risk * (10 / $max_risk), 2)` when
// `need_risk_score_normalization` is true) via calculateMaxRawScore(),
// itself a direct copy of calculate_maximum_risk_score()'s own
// likelihood_count/impact_count-keyed switch (functions.php:7502-7510).
// Model 6 is handled as a special case that SKIPS this helper entirely
// rather than routing through it: calculate_maximum_risk_score()'s own
// switch has no `case 6` and falls through to `default: return 10`, so on
// the PHP side model 6's normalization step is always `round($risk * (10 /
// 10), 2)` -- an identity multiply, since custom_risk_model_values.value is
// already stored on a 0-10 scale (confirmed via get_stored_risk_score()'s
// own SELECT and the table's CREATE TABLE in includes/upgrade.php, which
// has exactly `impact`, `likelihood`, `value` -- no `color` column, and
// GET /riskformula/config reports rows in that same plain shape). Skipping
// the helper for model 6 therefore produces the exact same numeric result
// as routing through it, whether or not `need_risk_score_normalization` is
// set -- the flag is irrelevant to model 6's output either way, since
// max/raw always resolve to a 10/10 ratio.
//
// FIELD_NAMES are the real POST/wire identifiers addRisk()/updateRisk()
// read (includes/api.php get_param() calls, and risk-details-form.js's
// buildClassicHolder()'s own `.attr('name', 'likelihood')` /
// `.attr('name', 'impact')`) -- unlike every OTHER scoring method's fields,
// these are NOT prefixed (no 'Classic' prefix), matching the legacy
// Current Likelihood / Current Impact selects they replace.
//
// Public surface (window.ClassicScoring):
//   FIELD_NAMES        -- the 2 Classic field names, in the same order the
//                        holder renders them (likelihood, impact)
//   calculateClassic(root, config) -- recompute the score display from the
//                        2 visible selects' current values and the given
//                        /riskformula/config object, and return the score.
//                        `root` (optional, defaults to `document`) -- same
//                        detached-element support dread-scoring.js's
//                        calculateDread(root) already has. Task 3 adds
//                        id="likelihood"/id="impact" to the two selects
//                        (currently name= only) for holderEl() to find them
//                        -- this file assumes those ids exist by the time
//                        it runs.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    var FIELD_NAMES = ['likelihood', 'impact'];

    // Scoped to the nearest .classic-holder ancestor -- the same defensive
    // pattern cvss-v2-scoring.js's/dread-scoring.js's/owasp-scoring.js's
    // own holderEl() already uses.
    function holderEl(id, root) {
        return (root || document).querySelector('.classic-holder #' + id);
    }

    // The exact 5-branch arithmetic calculate_risk() computes for models
    // 1-5 (includes/functions.php:7423-7441). Model 6 is handled separately
    // in calculateClassic() via a grid lookup, never through here.
    function calculateRawScore(likelihood, impact, riskModel) {
        switch (riskModel) {
            case 1: return (likelihood * impact) + (2 * impact);
            case 2: return (likelihood * impact) + impact;
            case 3: return likelihood * impact;
            case 4: return (likelihood * impact) + likelihood;
            case 5: return (likelihood * impact) + (2 * likelihood);
            default: return 0;
        }
    }

    // Mirrors calculate_maximum_risk_score() (includes/functions.php:
    // 7502-7510) exactly, using the live likelihood_count/impact_count from
    // /riskformula/config in place of that function's own
    // $GLOBALS['count_of_likelihoods']/$GLOBALS['count_of_impacts']. Only
    // ever called for models 1-5 -- see the module docblock for why model 6
    // (this switch's own `default`, returning 10 on the PHP side) is
    // special-cased out in calculateClassic() instead of routed through
    // here.
    function calculateMaxRawScore(likelihoodCount, impactCount, riskModel) {
        switch (riskModel) {
            case 1: return (likelihoodCount * impactCount) + (2 * impactCount);
            case 2: return (likelihoodCount * impactCount) + impactCount;
            case 3: return likelihoodCount * impactCount;
            case 4: return (likelihoodCount * impactCount) + likelihoodCount;
            case 5: return (likelihoodCount * impactCount) + (2 * likelihoodCount);
            default: return 10;
        }
    }

    // Mirrors calculate_risk()'s own range guard (includes/functions.php:
    // 7417: `in_array($impact, range(1, $count_of_impacts)) &&
    // in_array($likelihood, range(1, $count_of_likelihoods))`) -- 1-indexed,
    // inclusive of both ends.
    function inRange(value, count) {
        return value >= 1 && value <= count;
    }

    function calculateClassic(root, config) {
        var likelihood = Number(holderEl('likelihood', root).value) || 0;
        var impact = Number(holderEl('impact', root).value) || 0;

        var risk;

        // calculate_risk()'s range guard wraps its ENTIRE formula (functions.
        // php:7417-7463) -- when either value is out of range (e.g. both
        // selects still on the blank '--' option, value 0), the whole
        // model-1-6 branching below is skipped and the risk is just the
        // admin-configured default_risk_score, exactly as the PHP `else`
        // branch does. This must come before the model===6 check, not
        // nested inside it.
        if (!inRange(impact, config.impact_count) || !inRange(likelihood, config.likelihood_count)) {
            risk = Number(config.default_risk_score) || 0;
        } else if (config.risk_model === 6) {
            // get_stored_risk_score()'s own grid lookup (functions.php:
            // 26570) -- already on a 0-10 scale, normalization skipped
            // entirely (see module docblock for why this is numerically
            // identical to routing through calculateMaxRawScore() anyway).
            var cell = (config.custom_risk_model_values || []).filter(function (c) {
                return Number(c.impact) === impact && Number(c.likelihood) === likelihood;
            })[0];
            risk = cell ? Number(cell.value) : 0;
        } else {
            risk = calculateRawScore(likelihood, impact, config.risk_model);

            if (config.need_risk_score_normalization) {
                var max = calculateMaxRawScore(config.likelihood_count, config.impact_count, config.risk_model);
                risk = max ? risk * (10 / max) : risk;
            }
        }

        // Same 2-decimal rounding convention every other formula file in
        // this directory uses (e.g. owasp-scoring.js's averageNamedLevels()).
        risk = Math.round(risk * 100) / 100;

        holderEl('ClassicScore', root).textContent = risk;

        // Same ".sr-cvss-vector" caption treatment every other formula file
        // in this directory writes -- reuses the legacy RISKClassicExp1-5
        // lang keys (config.risk_model-keyed) rather than a new one, same
        // reasoning buildClassicReadView()'s identical comment gives
        // (risk-details-view.js). Model 6 (a custom lookup grid) has no
        // closed-form formula, so its caption is cleared rather than left
        // stale from a previous model-1-5 selection. _lang is a page
        // global -- RISKClassicExp1-5 are registered for both
        // risk-details-form.js and risk-details-view.js in header.php.
        //
        // score.php parity: the legacy table appended " x ( 10 / N )" after
        // the symbolic formula whenever normalization is on -- but with a
        // HARDCODED N per model (35/30/25/30/35, includes/display.php),
        // correct only for the default 5-likelihood/5-impact scale. This
        // computes the same denominator the actual score calculation above
        // uses (calculateMaxRawScore(), keyed off the LIVE likelihood_count/
        // impact_count), so it stays correct under a customized scale
        // instead of reproducing that latent bug.
        var formulaEl = holderEl('ClassicScoreFormula', root);
        if (formulaEl) {
            if (window._lang && config.risk_model >= 1 && config.risk_model <= 5) {
                var formulaText = window._lang['RISKClassicExp' + config.risk_model];
                if (config.need_risk_score_normalization) {
                    var maxRawScore = calculateMaxRawScore(config.likelihood_count, config.impact_count, config.risk_model);
                    formulaText += ' x (10/' + maxRawScore + ')';
                }
                formulaEl.textContent = formulaText;
            } else {
                formulaEl.textContent = '';
            }
        }

        return risk;
    }

    window.ClassicScoring = {
        FIELD_NAMES: FIELD_NAMES,
        calculateClassic: calculateClassic
    };
})();
