/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// DREAD scoring math -- a plain average of 5 fields (0-10 each), matching
// update_dread_score()'s own formula exactly (includes/functions.php:
// round((DamagePotential + Reproducibility + Exploitability + AffectedUsers
// + Discoverability) / 5, 2)). Unlike CVSS v2, DREAD had no prior client-
// side JS formula anywhere to relocate -- this file is a first-time
// implementation, not a relocation.
//
// FIELD_NAMES are the real POST/wire identifiers addRisk()/updateRisk()
// read (includes/api.php: get_param("POST", 'DREADDamage') etc.) -- NOT
// the bare metric names ('DamagePotential' etc.) used for the $lang label
// keys or for the separate legacy "update_dread" AJAX action's own fields
// (includes/api.php's `case "update_dread"`, which backs
// includes/display.php's edit_dread_score() accordion and reads bare
// $_POST['DamagePotential'] etc. -- a different endpoint, unrelated to
// this holder). 'DamagePotential' specifically maps to wire name
// 'DREADDamage', not 'DREADDamagePotential' -- confirmed directly against
// api.php's own get_param() calls, not inferred from the DB column names
// (DREAD_DamagePotential) or the $lang keys, which follow a different,
// unrelated naming convention.
//
// Public surface (window.DreadScoring):
//   FIELD_NAMES      -- the 5 DREAD metric field names, in the same order
//                       the holder renders them
//   calculateDread(root) -- recompute the score display from the 5 visible
//                       selects' current values, and return the score.
//                       `root` (optional, defaults to `document`) lets a
//                       caller building a holder that is NOT YET attached
//                       to the document (e.g. risk-details-form.js's
//                       buildDreadHolder(), which computes an initial score
//                       before returning the still-detached element to its
//                       caller) pass that element directly instead. This
//                       still works because Element.querySelector() matches
//                       against the element's own (possibly detached)
//                       descendant tree -- it does not require the element
//                       to be attached to `document`, only that the
//                       queried class/id actually exist somewhere under it.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    var FIELD_NAMES = [
        'DREADDamage', 'DREADReproducibility', 'DREADExploitability', 'DREADAffectedUsers', 'DREADDiscoverability'
    ];

    // Scoped to the nearest .dread-holder ancestor -- the same defensive
    // pattern cvss-v2-scoring.js's holderEl() uses. The DREADDamage/etc.
    // wire names (unlike the bare DamagePotential/etc. names this file used
    // before the wire-identifier fix) don't collide with either the legacy
    // scoring accordion (includes/display.php's edit_dread_score(), which
    // uses the bare metric names via create_numeric_dropdown() and posts to
    // a separate "update_dread" endpoint) or CVSS's own 'Exploitability'
    // metric id (cvss-v2-scoring.js) -- but this file keeps the same
    // .dread-holder-scoped lookup discipline as its CVSS counterpart
    // regardless, since both holders sit in the DOM simultaneously
    // (hidden, not removed) and any future id reuse should stay contained
    // to its own holder by construction, not by naming happening to avoid
    // a collision today.
    // `root`, when given, scopes the lookup to that (possibly still-
    // detached) element instead of the live `document` -- see this file's
    // top-of-file doc comment for why that's safe.
    function holderEl(id, root) {
        return (root || document).querySelector('.dread-holder #' + id);
    }

    function roundTo2Decimals(num) {
        return Math.round(num * 100) / 100;
    }

    function calculateDread(root) {
        var damagePotential = Number(holderEl('DREADDamage', root).value) || 0;
        var reproducibility = Number(holderEl('DREADReproducibility', root).value) || 0;
        var exploitability = Number(holderEl('DREADExploitability', root).value) || 0;
        var affectedUsers = Number(holderEl('DREADAffectedUsers', root).value) || 0;
        var discoverability = Number(holderEl('DREADDiscoverability', root).value) || 0;

        var score = roundTo2Decimals(
            (damagePotential + reproducibility + exploitability + affectedUsers + discoverability) / 5
        );

        holderEl('DreadScore', root).textContent = score;

        // Same ".sr-cvss-vector" caption calculateCVSS() (cvss-v2-scoring.js)
        // writes for its own vector ids -- DreadScoreFormula is optional
        // (buildDreadHolder() always renders one, but a future caller might
        // not), so guarded the same way that file's own vector writes are.
        // _lang is a page global (see CLAUDE.md's "Localized strings in JS"
        // -- DreadScoreFormula is registered for both risk-details-form.js
        // and risk-details-view.js in header.php, and dread-scoring.js is
        // always loaded alongside one of the two).
        var formulaEl = holderEl('DreadScoreFormula', root);
        if (formulaEl && window._lang) {
            formulaEl.textContent = window._lang.DreadScoreFormula
                .replace('{a}', damagePotential).replace('{b}', reproducibility).replace('{c}', exploitability)
                .replace('{d}', affectedUsers).replace('{e}', discoverability);
        }

        return score;
    }

    window.DreadScoring = {
        FIELD_NAMES: FIELD_NAMES,
        calculateDread: calculateDread
    };
})();
