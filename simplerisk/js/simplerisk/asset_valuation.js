/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Manual Asset Valuation table (Settings > Preferences > Asset Management):
 * keeps neighbouring levels in step while an administrator edits a boundary.
 *
 *  - Changing a level's minimum moves the level below's maximum to one less,
 *    so the ranges stay contiguous; a minimum raised above its own maximum
 *    raises that maximum too. The first level's minimum never goes below 0.
 *  - Changing a level's maximum moves the level above's minimum to one more;
 *    a maximum lowered below its own minimum lowers that minimum too.
 *  - Each move is followed through: a level pushed out of range by its
 *    neighbour adjusts its other boundary, and so on, so no two levels ever
 *    overlap after an edit.
 *
 * SR-86: the previous version compared the new value with an `oldvalue` that
 * only an onFocus handler set, so a change that arrived without a focus event
 * (the spinner arrows in some browsers, a script, autofill) compared against
 * `undefined` and nothing moved; lowering a maximum also threw on an undefined
 * `prev_id`. Nothing here needs the previous value: each rule follows from the
 * new value and the fields around it. The inputs are found by the
 * data-valuation-bound / data-valuation-level attributes that
 * display_asset_valuation_table() (includes/assets.php) renders.
 */
(function () {
    'use strict';

    var SELECTOR = 'input[data-valuation-bound][data-valuation-level]';

    function boundaryInput(bound, level) {
        return document.querySelector('input[data-valuation-bound="' + bound + '"][data-valuation-level="' + level + '"]');
    }

    function numberOf(input) {
        if (!input || input.value === '') {
            return null;
        }
        var n = parseInt(input.value, 10);
        return isNaN(n) ? null : n;
    }

    // Returns whether the field actually changed.
    function setValue(input, value) {
        if (input && String(input.value) !== String(value)) {
            input.value = value;
            return true;
        }
        return false;
    }

    // Moving one boundary can push the next level out of range, and that
    // level's other boundary then has to move too, and so on down the table
    // (review S5: the first version stopped after one hop and left levels
    // overlapping). Setting a value from script fires no change event, so each
    // handler calls the other side's handler itself whenever it changed a
    // field. Every step only moves a boundary toward consistency, so the walk
    // ends; `depth` is a safety net against a table this code does not expect.
    var MAX_DEPTH = 100;

    function onMinimumChanged(input, level, depth) {
        var value = numberOf(input);
        if (value === null || depth > MAX_DEPTH) {
            return;
        }
        var below = boundaryInput('max', level - 1);
        if (value < 0 && !below) {
            // The lowest level starts at 0.
            value = 0;
            setValue(input, value);
        }
        if (setValue(below, value - 1)) {
            onMaximumChanged(below, level - 1, depth + 1);
        }

        var ownMax = boundaryInput('max', level);
        var max = numberOf(ownMax);
        if (max !== null && max < value && setValue(ownMax, value)) {
            onMaximumChanged(ownMax, level, depth + 1);
        }
    }

    function onMaximumChanged(input, level, depth) {
        var value = numberOf(input);
        if (value === null || depth > MAX_DEPTH) {
            return;
        }
        var above = boundaryInput('min', level + 1);
        if (setValue(above, value + 1)) {
            onMinimumChanged(above, level + 1, depth + 1);
        }

        var ownMin = boundaryInput('min', level);
        var min = numberOf(ownMin);
        if (min !== null && min > value && setValue(ownMin, value)) {
            onMinimumChanged(ownMin, level, depth + 1);
        }
    }

    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input || typeof input.matches !== 'function' || !input.matches(SELECTOR)) {
            return;
        }
        var level = parseInt(input.getAttribute('data-valuation-level'), 10);
        if (isNaN(level)) {
            return;
        }
        if (input.getAttribute('data-valuation-bound') === 'min') {
            onMinimumChanged(input, level, 0);
        } else {
            onMaximumChanged(input, level, 0);
        }
    });
})();
