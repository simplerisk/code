/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// ----------------------------------------------------------------------
// Asset record profile for the Cards engines (risk-details-form.js and
// risk-details-view.js). Passed as `options.profile` to RiskDetailsForm.init()
// and RiskDetailsView.init(); see RISK_PROFILE's docblock in
// risk-details-form.js for what every key means.
//
//   window.AssetCardProfile           add mode (fields/layout by
//                                     template_group_id)
//   window.AssetCardProfile.forAsset(id)
//                                     view/edit of one asset: fields/layout
//                                     are asked for with ?asset_id=<id>, which
//                                     the server resolves to the asset's own
//                                     template group. (The engines also send
//                                     template_group_id; the server ignores it
//                                     when asset_id is present. template_group_id
//                                     alone falls back to the caller's default
//                                     group when the caller may not use the
//                                     asset's group, e.g. under Organizational
//                                     Hierarchy.)
//
// Values (GET /ui/asset/{id}/values) are RAW text. Everything here inserts
// them with text setters only; the one HTML value (details.display_html,
// purified server-side) is rendered by the engine's own display_html path.
//
// Writes: POST /assets and PATCH /assets/{id}, both urlencoded. serialize()
// sends the Mapped controls only when the user changed them (rebuilt as
// control_maturity[i] / control_id[i][] pairs; rows without a control are
// dropped), and the explicit `mapped_controls[]=` marker only when an update
// empties a stored mapping (a blank row alone is a 400). See the composite's
// docblock below. The Asset Scoring selections are sent the same way: only
// the ones the user changed on an update, only the answered ones on a create
// (see scoringPayload()).
//
// A `required` flag on MappedControls is not enforced client-side: the
// composite has no single input for checkAndSetValidation() to mark (the API
// does not require a mapping either).
//
// Needs, on the host page: risk-details-form.js, risk-details-view.js, and
// for the Mapped controls picker js/simplerisk/sr-faceted-picker.js plus
// pages/risk-mitigation-controls.js (RiskMitigationControls). Without
// RiskMitigationControls the field is read-only.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    // Field name -> widget type. Built-in types come from risk-details-form.js;
    // 'mapped-controls' is this file's composite (widgetRegistry below).
    var WIDGETS = {
        AssetName: 'text',
        IPAddress: 'text',
        AssetValuation: 'select',
        SiteLocation: 'multiselect',
        Team: 'multiselect',
        AssetDetails: 'richtext',
        Tags: 'selectize-tags',
        MappedControls: 'mapped-controls',
        AssociatedRisks: 'multiselect',
        AssetScoring: 'asset-scoring'
    };

    // Field name -> write-payload name (POST/PATCH /assets) AND values-response
    // key (/ui/asset/{id}/values). The two are the same by design (Task 4).
    var FORM_NAMES = {
        AssetName: 'name',
        IPAddress: 'ip',
        AssetValuation: 'value',
        SiteLocation: 'location',
        Team: 'team',
        AssetDetails: 'details',
        Tags: 'tags',
        MappedControls: 'mapped_controls',
        AssociatedRisks: 'associated_risks',
        // Values/prefill key only: the composite names its own three selects
        // (confidentiality / integrity / availability).
        AssetScoring: 'scoring'
    };

    // Widgets whose form name takes a '[]' suffix. 'mapped-controls' is not
    // one: its rows name their own controls (see buildMappedControlsRow()).
    var MULTI_VALUE = {
        multiselect: true,
        'selectize-tags': true
    };

    // Card keys: customization_asset_cards_layout_card_keys() (includes/
    // functions.php). Labels and icons match the Assets designer canvas
    // (customization-layout-editor.js, CARD_DEFS_BY_CANVAS.asset), which uses
    // the risk Details cards' keys, labels and icons.
    var CARD_LABELS = {
        general: _lang['CardGeneral'],
        assignment: _lang['Assignment'],
        classification: _lang['CardClassification'],
        scoring: _lang['CardScoring'],
        additional_info: _lang['CardAdditionalInformation'],
        custom_fields: _lang['CardCustomFields']
    };

    var CARD_ICONS = {
        general: 'fa-circle-info',
        assignment: 'fa-users',
        classification: 'fa-tags',
        scoring: 'fa-gauge',
        additional_info: 'fa-file-lines',
        custom_fields: 'fa-puzzle-piece'
    };

    // The assets table's column sizes (name varchar 200, ip varchar 15).
    var MAX_LENGTHS = {
        AssetName: 200,
        IPAddress: 15
    };

    var COMPOSITE_CLASS = 'sr-asset-mapped-controls';
    var PAIR_NAME = /^control_(maturity|id)\[/;

    function picker() {
        var rmc = window.RiskMitigationControls;
        return (rmc && typeof rmc.buildField === 'function' && typeof rmc.loadRoster === 'function'
            && typeof rmc.findControl === 'function') ? rmc : null;
    }

    // ------------------------------------------------------------------
    // Mapped controls composite: one row per maturity, each row a maturity
    // <select> plus the shared faceted control picker (RiskMitigationControls,
    // the same field the risk Mitigation tab uses; hundreds+ controls, so a
    // picker rather than a dropdown, design-system.md §5).
    //
    // States (data-state on the wrapper):
    //   noperm   the caller lacks Governance (field.can_select_controls) or the
    //            picker script is not loaded
    //   loading  waiting for the control roster
    //   failed   the roster request failed (Retry asks again)
    //   ready    editable rows
    // Only `ready` renders named inputs. In every other state the field shows a
    // read-only summary of the stored mapping and sends nothing, so a save
    // leaves the stored mapping alone.
    //
    // Even when ready, serialize() sends the mapping only if it differs from
    // the stored one (see mappedControlsPayload()). An unrelated save never
    // rewrites the mapping, and the clear marker goes out only when the user
    // emptied a mapping that had controls.
    // ------------------------------------------------------------------

    function maturityOptions(field) {
        return Array.isArray(field.maturity_options) ? field.maturity_options : [];
    }

    function maturityName(field, value) {
        var match = maturityOptions(field).filter(function (opt) {
            return String(opt.value) === String(value);
        })[0];
        return match ? match.name : String(value);
    }

    // {maturity: [sorted ids]} from [{maturity, ids}] rows, merging repeated
    // maturities and skipping rows without a control. Compared as JSON with
    // sorted keys.
    function normalizeMapping(rows) {
        var byMaturity = {};
        rows.forEach(function (row) {
            var ids = (row.ids || []).map(String).filter(function (id) { return id !== ''; });
            if (!ids.length || row.maturity === null || row.maturity === undefined || row.maturity === '') {
                return;
            }
            var key = String(row.maturity);
            byMaturity[key] = (byMaturity[key] || []).concat(ids);
        });
        var out = {};
        Object.keys(byMaturity).sort().forEach(function (key) {
            out[key] = byMaturity[key].filter(function (id, i, all) { return all.indexOf(id) === i; }).sort();
        });
        return out;
    }

    function mappingKey(mapping) {
        return JSON.stringify(Object.keys(mapping).map(function (key) { return [key, mapping[key]]; }));
    }

    function storedRows($wrap) {
        return ($wrap.data('prefillRows') || []).map(function (row) {
            return { maturity: row.control_maturity, ids: Array.isArray(row.control_id) ? row.control_id : [] };
        });
    }

    function isUpdate($wrap) {
        var ctx = $wrap.data('ctx') || {};
        return ctx.submitMode === 'update';
    }

    function buildMappedControls(field, ctx) {
        var $wrap = $('<div>').addClass(COMPOSITE_CLASS + ' sr-qstack')
            .data('rowSeq', 0)
            .data('field', field)
            .data('ctx', ctx)
            .data('prefillRows', []);
        $wrap.append($('<div>').addClass('sr-asset-mapped-controls-body sr-qstack'));
        setState($wrap, (field.can_select_controls && picker()) ? 'loading' : 'noperm');
        return $wrap;
    }

    // Substitutes {placeholder} with a literal value. A function replacer, so
    // a name containing '$&' or '$1' is inserted as typed.
    function fillPlaceholder(template, placeholder, value) {
        return String(template).replace(placeholder, function () { return String(value); });
    }

    function nControls(count) {
        return fillPlaceholder(_lang['NControls'], '{n}', count);
    }

    // A live region, so a screen reader hears the loading/failed state change;
    // focusable (tabindex -1) so focus can land here after Retry.
    function hintNode(keys) {
        return $('<div>').addClass('sr-qhint sr-asset-mapped-controls-status')
            .attr({ role: 'status', 'aria-live': 'polite', tabindex: '-1' })
            .text(keys.map(function (key) { return _lang[key]; }).join(' '));
    }

    function summaryNode($wrap) {
        var field = $wrap.data('field');
        var $summary = $('<div>').addClass('sr-asset-mapped-controls-summary');
        ($wrap.data('prefillRows') || []).forEach(function (row) {
            var count = Array.isArray(row.control_id) ? row.control_id.length : 0;
            $('<div>').text(maturityName(field, row.control_maturity) + ': '
                + nControls(count)).appendTo($summary);
        });
        return $summary;
    }

    function setState($wrap, state) {
        $wrap.attr('data-state', state);
        var $body = $wrap.find('.sr-asset-mapped-controls-body').empty();
        var keepHint = isUpdate($wrap) ? ['SavingKeepsTheCurrentControlMappings'] : [];

        if (state === 'ready') {
            $body.append($('<div>').addClass('sr-asset-mapped-controls-rows sr-qstack'));
            // Wrapped so the button keeps its own width inside the stack.
            $('<div>').append(
                $('<button>', { type: 'button', 'class': 'sr-asset-mapped-controls-add' })
                    .append($('<i>', { 'class': 'fa fa-plus', 'aria-hidden': 'true' }))
                    .append(document.createTextNode(' ' + _lang['AddControlsAtAnotherMaturity']))
            ).appendTo($body);
            var rows = $wrap.data('prefillRows') || [];
            if (rows.length) {
                rows.forEach(function (row) {
                    buildMappedControlsRow($wrap, row.control_maturity, row.control_id || []);
                });
            } else {
                buildMappedControlsRow($wrap);
            }
        } else if (state === 'loading') {
            $body.append(hintNode(['LoadingControls'])).append(summaryNode($wrap));
        } else if (state === 'failed') {
            $body.append(hintNode(['ControlListCouldNotBeLoaded'].concat(keepHint)));
            $('<div>').append($('<button>', { type: 'button', 'class': 'btn btn-link btn-sm p-0 sr-asset-mapped-controls-retry', text: _lang['Retry'] }))
                .appendTo($body);
            $body.append(summaryNode($wrap));
        } else {
            $body.append(hintNode(['ChoosingControlsNeedsGovernancePermission'].concat(keepHint))).append(summaryNode($wrap));
        }
        refit($wrap);
    }

    // The engine keeps its instances by container selector, which the
    // widget context does not carry; every id'd ancestor is a candidate
    // (RiskDetailsForm.isDirty/markClean are no-ops for a non-instance).
    function formContainerSelectors($wrap) {
        return $wrap.parents('[id]').map(function () { return '#' + this.id; }).get();
    }

    function formIsDirty($wrap) {
        var rdf = window.RiskDetailsForm;
        return !!rdf && typeof rdf.isDirty === 'function'
            && formContainerSelectors($wrap).some(function (sel) { return rdf.isDirty(sel); });
    }

    // Rebuilding the rows renders new named inputs. Once the user has
    // interacted with the form, the engine's dirty baseline is frozen (Retry
    // itself is a click), so the rebuild alone would read as an unsaved
    // change; when the form was clean before the rebuild it is marked clean
    // again after it. Before any interaction the engine's baseline still
    // follows the settling form, and markClean() would freeze it too early,
    // so it is left alone. The interaction test mirrors the engine's own
    // (trusted focusin/mousedown/keydown/input inside the form).
    function trackInteraction($wrap) {
        var events = ['focusin', 'mousedown', 'keydown', 'input'];
        function onEvent(e) {
            if (!$wrap.closest('body').length) {
                events.forEach(function (type) { document.removeEventListener(type, onEvent, true); });
                return;
            }
            var form = $wrap.closest('form')[0];
            if (e.isTrusted && form && form.contains(e.target)) {
                $wrap.data('userInteracted', true);
                events.forEach(function (type) { document.removeEventListener(type, onEvent, true); });
            }
        }
        events.forEach(function (type) { document.addEventListener(type, onEvent, true); });
    }

    function rebuild($wrap, state, focusAfter) {
        var wasDirty = formIsDirty($wrap);
        setState($wrap, state);
        if (!wasDirty && $wrap.data('userInteracted') && window.RiskDetailsForm && typeof window.RiskDetailsForm.markClean === 'function') {
            formContainerSelectors($wrap).forEach(function (sel) { window.RiskDetailsForm.markClean(sel); });
        }
        if (focusAfter) {
            var $target = state === 'ready'
                ? $wrap.find('.sr-asset-mapped-controls-row select').first()
                : $wrap.find('.sr-asset-mapped-controls-status').first();
            $target.trigger('focus');
        }
    }

    // `userInitiated` (Retry): focus follows the state so it never falls back
    // to <body>. The first load never moves focus.
    function loadRosterInto($wrap, userInitiated) {
        rebuild($wrap, 'loading', userInitiated);
        picker().loadRoster().done(function () {
            if ($wrap.closest('body').length) {
                rebuild($wrap, 'ready', userInitiated);
            }
        }).fail(function () {
            if ($wrap.closest('body').length) {
                rebuild($wrap, 'failed', userInitiated);
            }
        });
    }

    function removeLabel($wrap, maturity) {
        return fillPlaceholder(_lang['RemoveControlsAtMaturity'], '{maturity}', maturityName($wrap.data('field'), maturity));
    }

    // Row inputs are real named controls inside the <form>, so the engine's
    // isDirty() snapshot sees every change. Ids the roster cannot name (a
    // control deleted since it was mapped -- the roster lists every live
    // control) stay selected, labelled '#<id> (unavailable)', so the stored
    // mapping is shown as it is; mappedControlsPayload() leaves them out of a
    // changed-mapping save because the server rejects deleted controls.
    function buildMappedControlsRow($wrap, maturity, ids) {
        var rmc = picker();
        var field = $wrap.data('field');
        var ctx = $wrap.data('ctx');
        var seq = $wrap.data('rowSeq');
        $wrap.data('rowSeq', seq + 1);

        var $maturity = $('<select>').addClass('form-select')
            .attr('name', 'control_maturity[' + seq + ']')
            .attr('aria-label', _lang['ControlMaturity']);
        maturityOptions(field).forEach(function (opt) {
            $('<option>').val(opt.value).text(opt.name).appendTo($maturity);
        });
        if (maturity !== undefined && maturity !== null) {
            if (!$maturity.find('option').filter(function () { return this.value === String(maturity); }).length) {
                $('<option>').val(String(maturity)).text(String(maturity)).appendTo($maturity);
            }
            $maturity.val(String(maturity));
        }

        var $controls = $('<select>').addClass('form-select')
            .attr('multiple', 'multiple')
            .attr('id', ctx.elementId + '-row' + seq)
            .attr('name', 'control_id[' + seq + '][]')
            .attr('aria-label', _lang['Controls']);
        (ids || []).forEach(function (id) {
            var control = rmc.findControl(id);
            $('<option>', { value: String(id), selected: true })
                .text(control ? control.short_name : fillPlaceholder(_lang['ControlIdUnavailable'], '{id}', id))
                .appendTo($controls);
        });
        var $controlsField = rmc.buildField($controls);

        var $remove = $('<button>', {
            type: 'button',
            'class': 'sr-row-action sr-asset-mapped-controls-remove',
            title: removeLabel($wrap, $maturity.val()),
            'aria-label': removeLabel($wrap, $maturity.val())
        }).append($('<i>', { 'class': 'fa fa-trash', 'aria-hidden': 'true' }));

        $maturity.on('change', function () {
            var label = removeLabel($wrap, $maturity.val());
            $remove.attr({ title: label, 'aria-label': label });
        });

        var $row = $('<div>').addClass('sr-asset-mapped-controls-row')
            .append($('<div>').addClass('sr-asset-mapped-controls-maturity').append($maturity))
            .append($('<div>').addClass('sr-asset-mapped-controls-picker').append($controlsField))
            .append($('<div>').addClass('sr-asset-mapped-controls-remove-cell').append($remove));

        $wrap.find('.sr-asset-mapped-controls-rows').append($row);
        // The chips field renders against the live row (the picker's open
        // button looks its <select> up by id).
        rmc.initField($controlsField);
        return $row;
    }

    function refit($wrap) {
        var ctx = $wrap.data('ctx');
        if (!$wrap.closest('.grid-stack-item').length) {
            return; // not mounted yet (build time) or outside the canvas
        }
        if (ctx && typeof ctx.fitToContent === 'function') {
            ctx.fitToContent($wrap[0]);
        }
        if (ctx && typeof ctx.scheduleAutoFit === 'function') {
            ctx.scheduleAutoFit();
        }
    }

    function activateMappedControls($wrap) {
        trackInteraction($wrap);
        $wrap.on('click', '.sr-asset-mapped-controls-add', function (e) {
            e.preventDefault();
            var $row = buildMappedControlsRow($wrap);
            refit($wrap);
            $row.find('select[name^="control_maturity["]').trigger('focus');
        });
        $wrap.on('click', '.sr-asset-mapped-controls-remove', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.sr-asset-mapped-controls-row');
            var $next = $row.next('.sr-asset-mapped-controls-row');
            var $prev = $row.prev('.sr-asset-mapped-controls-row');
            $row.remove();
            refit($wrap);
            var $target = $next.length ? $next : $prev;
            if ($target.length) {
                $target.find('.sr-asset-mapped-controls-remove').trigger('focus');
            } else {
                $wrap.find('.sr-asset-mapped-controls-add').trigger('focus');
            }
        });
        $wrap.on('click', '.sr-asset-mapped-controls-retry', function (e) {
            e.preventDefault();
            loadRosterInto($wrap, true);
        });
        if ($wrap.attr('data-state') === 'loading') {
            loadRosterInto($wrap);
        }
    }

    // `value` is values.mapped_controls.raw: [{control_maturity, control_id: []}].
    // Runs synchronously after activate, i.e. while the roster is still
    // loading; re-rendering the current state picks the rows up.
    function prefillMappedControls($wrap, value) {
        $wrap.data('prefillRows', Array.isArray(value) ? value : []);
        setState($wrap, $wrap.attr('data-state'));
    }

    // [{maturity, ids}] for every row of a ready composite.
    function collectMappedRows($wrap) {
        var rows = [];
        $wrap.find('.sr-asset-mapped-controls-row').each(function () {
            rows.push({
                maturity: $(this).find('select[name^="control_maturity["]').val(),
                ids: $(this).find('select[name^="control_id["]').val() || []
            });
        });
        return rows;
    }

    // The mapped-controls part of the body, as urlencoded pairs: nothing
    // unless the composite is ready and its mapping differs from the stored
    // one; the explicit clear marker only when an update empties a stored
    // mapping (a blank row alone is a 400).
    function mappedControlsPayload($form, mode) {
        var $wrap = $form.find('.' + COMPOSITE_CLASS + '[data-state="ready"]').first();
        if (!$wrap.length) {
            return [];
        }
        var rows = collectMappedRows($wrap);
        // Change detection uses the rows as shown (dead ids included), so an
        // untouched mapping that holds a deleted control still sends nothing.
        if (mappingKey(normalizeMapping(rows)) === mappingKey(normalizeMapping(storedRows($wrap)))) {
            return [];
        }
        var rmc = picker();
        var current = normalizeMapping(rows.map(function (row) {
            return {
                maturity: row.maturity,
                ids: row.ids.filter(function (id) { return rmc.findControl(id) !== null; })
            };
        }));
        var maturities = Object.keys(current);
        if (!maturities.length) {
            return mode === 'update' ? [encodeURIComponent('mapped_controls[]') + '='] : [];
        }
        var parts = [];
        maturities.forEach(function (maturity, index) {
            parts.push(encodeURIComponent('control_maturity[' + index + ']') + '=' + encodeURIComponent(maturity));
            current[maturity].forEach(function (id) {
                parts.push(encodeURIComponent('control_id[' + index + '][]') + '=' + encodeURIComponent(id));
            });
        });
        return parts;
    }

    function pairName(pair) {
        var name = pair.split('=')[0];
        try {
            return decodeURIComponent(name.replace(/\+/g, ' '));
        } catch (ignore) { // eslint-disable-line no-unused-vars
            return name;
        }
    }

    // The request body for POST (create) or PATCH (update) /assets.
    //   create: the plain serialized form (an absent field takes the server's
    //           default; no empty markers, so no blank tag is created)
    //   update: the serialized form with an explicit empty marker for every
    //           emptied multi-select (RiskDetailsForm's own update body), so
    //           emptying Team/Site location/Tags/Associated risks clears them
    // with the composites' own pairs replaced by mappedControlsPayload() and
    // scoringPayload().
    function serialize($form, opts) {
        var mode = (opts && opts.mode) || 'create';
        var formEl = $form[0];
        var base = (mode === 'update' && window.RiskDetailsForm && typeof window.RiskDetailsForm.serializeWithEmptyMultiValueMarkers === 'function')
            ? window.RiskDetailsForm.serializeWithEmptyMultiValueMarkers(formEl)
            : $form.serialize();

        var parts = (base ? base.split('&') : []).filter(function (pair) {
            var name = pairName(pair);
            return pair !== '' && !PAIR_NAME.test(name) && SCORING_OBJECTIVES.indexOf(name) === -1;
        });
        return parts.concat(mappedControlsPayload($form, mode), scoringPayload($form, mode)).join('&');
    }

    // ------------------------------------------------------------------
    // Asset Scoring composite (spec 2026-09-30-asset-scoring-design.md §3):
    // three named selects (confidentiality / integrity / availability) in a
    // Security objectives card beside a live Summary card (weighted score
    // meter, FIPS categorization, weighted band); see "Two cards" below.
    // Named inputs inside the form, so the engine's isDirty() snapshot sees
    // every change and a changed-then-reverted selection reads clean.
    //
    // serialize() replaces the selects' own pairs with scoringPayload():
    //   update: only the objectives that differ from the stored selection;
    //           "Not set" posts '' (present-empty), which the API reads as
    //           "clear". An unrelated save never rewrites the scoring.
    //   create: only the answered objectives (an unset one is omitted).
    //
    // The results come from scoringCompute(), the JS mirror of
    // asset_scoring_compute() (includes/asset_scoring.php): integer
    // hundredths, half-up rounding by integer division, not applicable out of
    // both sides, no score without a usable denominator (ruling G3). Both run
    // the shared table
    // tests/web-e2e/src/data/asset-management/asset-scoring-cases.json.
    //
    // The Preferences defaults (field.scoring.defaults) pre-fill Add only
    // (ruling G5); the server never applies them. Every label is set with
    // text setters.
    // ------------------------------------------------------------------

    var SCORING_OBJECTIVES = ['confidentiality', 'integrity', 'availability'];
    var SCORING_LEVELS = ['low', 'moderate', 'high'];
    var SCORING_NA = 'not_applicable';
    var SCORING_CLASS = 'sr-asset-scoring';

    function levelRank(v) {
        var i = SCORING_LEVELS.indexOf(v);
        return i === -1 ? null : i + 1;
    }

    function scoringAllowed(objective) {
        return objective === 'confidentiality' ? SCORING_LEVELS.concat([SCORING_NA]) : SCORING_LEVELS;
    }

    function formatHundredths(h) {
        return Math.floor(h / 100) + '.' + ('0' + (h % 100)).slice(-2);
    }

    // asset_scoring_settings_usable(): every number an integer (hundredths).
    function scoringSettingsUsable(settings) {
        var isInt = function (v) { return typeof v === 'number' && Number.isInteger(v); };
        return !!settings && !!settings.weights && !!settings.values && !!settings.thresholds
            && SCORING_OBJECTIVES.every(function (o) { return isInt(settings.weights[o]); })
            && SCORING_LEVELS.every(function (l) { return isInt(settings.values[l]); })
            && isInt(settings.thresholds.moderate) && isInt(settings.thresholds.high);
    }

    function scoringCompute(selections, settings) {
        var none = { scored: false, categorization: null, score_hundredths: null, score: null, band: null };
        if (!scoringSettingsUsable(settings)) {
            return none;
        }
        var sel = {};
        var answered = SCORING_OBJECTIVES.every(function (o) {
            var v = selections ? selections[o] : null;
            sel[o] = (typeof v === 'string' && scoringAllowed(o).indexOf(v) !== -1) ? v : null;
            return sel[o] !== null;
        });
        if (!answered) {
            return none;
        }
        var top = 0;
        var num = 0;
        var den = 0;
        SCORING_OBJECTIVES.forEach(function (o) {
            var rank = levelRank(sel[o]);
            if (rank === null) {
                return; // not applicable: out of the high-water mark and the weights
            }
            top = Math.max(top, rank);
            num += settings.weights[o] * settings.values[sel[o]];
            den += settings.weights[o];
        });
        var out = { scored: true, categorization: SCORING_LEVELS[top - 1] || null, score_hundredths: null, score: null, band: null };
        if (den > 0) {
            // intdiv(2 * num + den, 2 * den): every operand is a non-negative
            // integer well inside 2^53, so Math.floor of the quotient is exact.
            var h = Math.floor((2 * num + den) / (2 * den));
            out.score_hundredths = h;
            out.score = formatHundredths(h);
            out.band = h >= settings.thresholds.high ? 'high' : (h >= settings.thresholds.moderate ? 'moderate' : 'low');
        }
        return out;
    }

    function levelLabel(code) {
        if (code === SCORING_NA) {
            return _lang['ApplicabilityNotApplicable'];
        }
        return {
            low: _lang['AssetScoringLevelLow'],
            moderate: _lang['AssetScoringLevelModerate'],
            high: _lang['AssetScoringLevelHigh']
        }[code] || '';
    }

    function objectiveLabel(objective) {
        return {
            confidentiality: _lang['Confidentiality'],
            integrity: _lang['Integrity'],
            availability: _lang['Availability']
        }[objective];
    }

    // ---- Two cards (the risk Scoring card's summary | inputs shape) --------
    // Left, the Summary card: the weighted score as a cold-to-hot meter over
    // the Low..High level-value range (threshold ticks at Moderate and High),
    // then the FIPS categorization and weighted band as neutral chips. Right,
    // the Security objectives card: the three selects, each label followed by
    // the risk scoring help icon (same classes, same Bootstrap popover). View
    // shows the same two cards with the ratings as text. The cards sit side
    // by side and stack, Summary first, when the composite itself is narrow
    // (a container query in _asset-scoring.scss), so Edit, Add, View and the
    // inline control all follow the space they are given.

    var SCORING_HELP_KEYS = {
        confidentiality: 'AssetScoringConfidentialityHelp',
        integrity: 'AssetScoringIntegrityHelp',
        availability: 'AssetScoringAvailabilityHelp'
    };
    // jQuery calls a special event's `remove` hook while it cleans a removed
    // node (.remove(), .empty(), .html()), so a help popover shown from a
    // control that is torn down (inline Cancel, a redraw, a mode switch) is
    // disposed with it rather than left behind in <body>.
    var HELP_REMOVED = 'srAssetScoringHelpRemoved';
    if (!$.event.special[HELP_REMOVED]) {
        $.event.special[HELP_REMOVED] = {
            remove: function (handleObj) {
                if (handleObj && typeof handleObj.handler === 'function') {
                    handleObj.handler.call(this);
                }
            }
        };
    }

    // The three result explanations (what each number or label is and how it
    // is worked out). The keys are the Summary labels' own lang keys; the
    // thresholds in the band text come from the live scale.
    var RESULT_HELP = {
        score: { label: 'WeightedScore', body: 'AssetScoringScoreHelp' },
        categorization: { label: 'FIPSCategorization', body: 'AssetScoringCategorizationHelp' },
        band: { label: 'WeightedBand', body: 'AssetScoringBandHelp' }
    };

    function resultHelpContent(kind, scale) {
        // Every occurrence: the band text names each threshold twice.
        var values = {
            '{$low}': formatHundredths(scale.low),
            '{$high}': formatHundredths(scale.high),
            '{$moderate}': formatHundredths(scale.moderate),
            '{$highAt}': formatHundredths(scale.highAt)
        };
        var text = String(_lang[RESULT_HELP[kind].body]);
        Object.keys(values).forEach(function (placeholder) {
            text = text.split(placeholder).join(values[placeholder]);
        });
        return $('<div>').addClass('sr-asset-scoring-help')
            .append($('<p>').addClass('sr-asset-scoring-help-question').text(text))[0];
    }

    function helpPopover(el) {
        return (window.bootstrap && bootstrap.Popover) ? bootstrap.Popover.getInstance(el) : null;
    }

    // The guidance as DOM built with text setters: the question, then one
    // row per level -- the level name (the AssetScoringLevel* / Not
    // applicable labels the selects use) in <strong>, then its description.
    // Built afresh on every show (Bootstrap calls the content function then)
    // and handed over as an Element, which Bootstrap appends as-is in html
    // mode: no string is ever parsed as HTML.
    function scoringHelpContent(objective) {
        var rows = [
            ['high', 'AssetScoringHelpHigh'],
            ['moderate', 'AssetScoringHelpModerate'],
            ['low', 'AssetScoringHelpLow']
        ];
        if (objective === 'confidentiality') {
            rows.push([SCORING_NA, 'AssetScoringHelpNotApplicable']);
        }
        var $dl = $('<dl>').addClass('sr-asset-scoring-help-levels');
        rows.forEach(function (row) {
            $('<div>')
                .append($('<dt>').append($('<strong>').text(levelLabel(row[0]))))
                .append($('<dd>').text(_lang[row[1]]))
                .appendTo($dl);
        });
        return $('<div>').addClass('sr-asset-scoring-help')
            .append($('<p>').addClass('sr-asset-scoring-help-question').text(_lang[SCORING_HELP_KEYS[objective]]))
            .append($dl)[0];
    }

    // The risk scoring help icon (scoringSubField(), risk-details-form.js):
    // the same classes and hover/focus Bootstrap popover, plus a role, a name
    // and Enter/Space to toggle. Esc closes it: the record modal's capture
    // handler (asset-record-modal.js, via AssetCardProfile.scoring.hideHelp)
    // and, outside the modal, the composite's own handler
    // (activateAssetScoring()). `is-open` marks the icon from the moment it
    // starts to show until it starts to hide, so Esc during the fade-in is
    // still the popover's. It follows the label instead of sitting inside
    // it, so a click does not also focus the select and the select's name
    // stays just the objective.
    // `kind` is an objective, or (with `scale`) one of the Summary results.
    function scoringHelpIcon(kind, scale) {
        var isResult = !!RESULT_HELP[kind];
        var $help = $('<i>')
            .addClass('fa fa-info-circle text-muted sr-scoring-help-icon')
            .attr({
                role: 'button',
                tabindex: '0',
                'aria-label': isResult
                    ? fillPlaceholder(_lang['AssetScoringResultHelpLabel'], '{$result}', _lang[RESULT_HELP[kind].label])
                    : fillPlaceholder(_lang['AssetScoringHelpLabel'], '{$objective}', objectiveLabel(kind)),
                'data-bs-toggle': 'popover',
                'data-bs-trigger': 'hover focus',
                'data-bs-placement': 'top'
            })
            .attr(isResult ? 'data-result-help' : 'data-objective', kind);
        try {
            new bootstrap.Popover($help[0], {
                customClass: 'sr-scoring-help-popover',
                // html: true only so Bootstrap appends this Element as-is;
                // the content is never a string.
                html: true,
                content: function () { return isResult ? resultHelpContent(kind, scale) : scoringHelpContent(kind); }
            });
        } catch (err) {
            console.error('Failed to initialize asset scoring help popover:', err);
        }
        $help.on('show.bs.popover', function () { $(this).addClass('is-open'); })
            .on('hide.bs.popover', function () { $(this).removeClass('is-open'); });
        $help.on('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            e.preventDefault();
            var popover = helpPopover(this);
            if (popover) {
                if ($(this).hasClass('is-open')) {
                    popover.hide();
                } else {
                    popover.show();
                }
            }
        });
        $help.on(HELP_REMOVED, function () {
            var popover = helpPopover(this);
            if (popover) {
                popover.dispose();
            }
        });
        return $help;
    }

    // Hides every guidance popover open under `root`; true when one was.
    function hideScoringHelp(root) {
        var hid = false;
        $(root).find('.sr-scoring-help-icon.is-open').each(function () {
            var popover = helpPopover(this);
            if (popover) {
                popover.hide();
                hid = true;
            }
        });
        return hid;
    }

    // The meter's range and ticks from the settings in the fields payload
    // (hundredths): Low level value .. High level value, ticks at the Moderate
    // and High thresholds. Falls back to the spec defaults (1.00-3.00,
    // 1.67 / 2.34) when the payload has no usable numbers.
    function meterScale(settings) {
        var isInt = function (v) { return typeof v === 'number' && Number.isInteger(v); };
        var v = settings && settings.values;
        var t = settings && settings.thresholds;
        if (v && t && isInt(v.low) && isInt(v.high) && v.high > v.low && isInt(t.moderate) && isInt(t.high)) {
            return { low: v.low, high: v.high, moderate: t.moderate, highAt: t.high };
        }
        return { low: 100, high: 300, moderate: 167, highAt: 234 };
    }

    function meterPercent(h, scale) {
        var p = ((h - scale.low) / (scale.high - scale.low)) * 100;
        return Math.max(0, Math.min(100, p));
    }

    // "2.00" -> 200 (the View's result carries the formatted score only).
    function parseHundredths(text) {
        var m = /^(\d+)\.(\d{2})$/.exec(String(text || ''));
        return m ? parseInt(m[1], 10) * 100 + parseInt(m[2], 10) : null;
    }

    function scoringCard(cls, titleKey) {
        return $('<div>').addClass('sr-asset-scoring-card ' + cls)
            .append($('<h3>').addClass('sr-asset-scoring-card-head').text(_lang[titleKey]));
    }

    // The Summary card's fixed skeleton, built once per composite;
    // fillResults() only updates it, so the fill animates and the live
    // region hears one change at a time. The meter is named by its visible
    // "Weighted Score" label.
    var meterLabelSeq = 0;
    function buildResults($r, scale) {
        var labelId = 'sr-asset-scoring-score-label-' + (++meterLabelSeq);
        function labelRow($label, kind) {
            return $('<div>').addClass('sr-asset-scoring-label-row').append($label).append(scoringHelpIcon(kind, scale));
        }
        function item(key, labelKey) {
            return $('<div>').addClass('sr-asset-scoring-result').attr('data-result', key)
                .append(labelRow($('<span>').addClass('sr-asset-scoring-result-label').text(_lang[labelKey]), key))
                .append($('<span>').attr('data-value', ''));
        }
        function tick(h, kind) {
            return $('<span>').addClass('sr-asset-scoring-meter-tick')
                .attr('data-threshold', kind).css('left', meterPercent(h, scale) + '%');
        }
        var $meter = $('<div>').addClass('sr-asset-scoring-meter')
            .attr({
                role: 'meter',
                'aria-labelledby': labelId,
                'aria-valuemin': formatHundredths(scale.low),
                'aria-valuemax': formatHundredths(scale.high)
            })
            .css({
                '--sr-asset-scoring-moderate': meterPercent(scale.moderate, scale) + '%',
                '--sr-asset-scoring-high': meterPercent(scale.highAt, scale) + '%'
            })
            .append($('<div>').addClass('sr-asset-scoring-meter-value-row')
                .append($('<span>').addClass('sr-asset-scoring-meter-value').attr('data-value', '')))
            .append($('<div>').addClass('sr-asset-scoring-meter-track')
                .append($('<div>').addClass('sr-asset-scoring-meter-fill'))
                .append(tick(scale.moderate, 'moderate'))
                .append(tick(scale.highAt, 'high'))
                .append($('<span>').addClass('sr-asset-scoring-meter-marker')))
            .append($('<div>').addClass('sr-asset-scoring-meter-scale').attr('aria-hidden', 'true')
                .append($('<span>').text(formatHundredths(scale.low)))
                .append($('<span>').text(formatHundredths(scale.high))));
        $r.append($('<div>').addClass('sr-asset-scoring-result sr-asset-scoring-result--score').attr('data-result', 'score')
            .append(labelRow($('<span>').addClass('sr-asset-scoring-result-label').attr('id', labelId).text(_lang['WeightedScore']), 'score'))
            .append($meter));
        $r.append($('<p>').addClass('sr-asset-scoring-unscored sr-qhint')
            .text(_lang['NotScored'] + ' — ' + _lang['AssetScoringNotScoredHint']));
        $r.append($('<p>').addClass('sr-asset-scoring-note sr-qhint').text(_lang['AssetScoringNoWeightedScoreNote']));
        $r.append($('<div>').addClass('sr-asset-scoring-chips')
            .append(item('categorization', 'FIPSCategorization'))
            .append(item('band', 'WeightedBand')));
        $r.data('scale', scale);
        return $r;
    }

    // Updates the Summary card from a result: scoringCompute()'s (Edit, Add,
    // inline) or the server's values.scoring.result (View). Unscored: an
    // empty meter and "Not scored"; scored without a weighted score (no
    // usable denominator, ruling G3): the categorization, an empty meter and
    // a short note. The meter is never colour-only: aria-valuenow/-valuetext
    // carry the score and band, and the score is printed above the fill.
    function fillResults($r, result) {
        var scale = $r.data('scale') || meterScale(null);
        var scored = !!(result && result.scored);
        var h = null;
        if (scored) {
            h = (typeof result.score_hundredths === 'number') ? result.score_hundredths : parseHundredths(result.score);
        }
        var hasScore = h !== null;
        function setValue($node, text, cls) {
            $node.removeClass('sr-chip sr-asset-scoring-score is-empty sr-chip--level-low sr-chip--level-moderate sr-chip--level-high')
                .addClass(text ? cls : 'is-empty').text(text || '—');
        }
        function levelClass(code) { return 'sr-chip sr-chip--level-' + String(code); }
        setValue($r.find('[data-result="categorization"] [data-value]'), scored ? levelLabel(result.categorization) : '', levelClass(result.categorization));
        setValue($r.find('[data-result="band"] [data-value]'), hasScore ? levelLabel(result.band) : '', levelClass(result.band));

        var pct = hasScore ? meterPercent(h, scale) : 0;
        var score = hasScore ? formatHundredths(h) : '';
        var valueText;
        if (hasScore) {
            valueText = fillPlaceholder(fillPlaceholder(_lang['AssetScoringMeterValue'], '{$score}', score),
                '{$band}', levelLabel(result.band));
        } else {
            valueText = scored ? _lang['AssetScoringNoWeightedScore'] : _lang['NotScored'];
        }
        var $meter = $r.find('.sr-asset-scoring-meter');
        $meter.toggleClass('is-empty', !hasScore).attr({
            'aria-valuenow': formatHundredths(hasScore ? Math.max(scale.low, Math.min(scale.high, h)) : scale.low),
            'aria-valuetext': valueText
        }).css('--sr-asset-scoring-fill', pct + '%');
        // The gradient spans the whole track, so the fill shows the colour of
        // the score it reaches: its background is sized to the track's width.
        $meter.find('.sr-asset-scoring-meter-fill').css({
            width: pct + '%',
            'background-size': pct > 0 ? (10000 / pct) + '% 100%' : ''
        });
        $meter.find('.sr-asset-scoring-meter-value').text(score || '—');
        $r.find('.sr-asset-scoring-unscored').prop('hidden', scored);
        $r.find('.sr-asset-scoring-note').prop('hidden', !scored || hasScore);
        return $r;
    }

    function currentSelections($wrap) {
        var sel = {};
        SCORING_OBJECTIVES.forEach(function (o) {
            var v = $wrap.find('select[name="' + o + '"]').val();
            sel[o] = v ? String(v) : null;
        });
        return sel;
    }

    function refreshScoring($wrap) {
        var field = $wrap.data('field') || {};
        var result = field.scoring ? scoringCompute(currentSelections($wrap), field.scoring) : null;
        fillResults($wrap.find('.sr-asset-scoring-results').first(), result);
        refit($wrap);
    }

    function buildAssetScoring(field, ctx) {
        var baseId = (ctx && ctx.elementId) ? ctx.elementId : 'asset-scoring';
        var $wrap = $('<div>').addClass(SCORING_CLASS)
            .attr({ role: 'group', 'aria-label': _lang['AssetScoring'] })
            .data('field', field)
            .data('ctx', ctx)
            .data('stored', { confidentiality: '', integrity: '', availability: '' });
        var $cards = $('<div>').addClass('sr-asset-scoring-cards').appendTo($wrap);
        var $summary = scoringCard('sr-asset-scoring-summary', 'Summary').appendTo($cards);
        var $objectives = scoringCard('sr-asset-scoring-objectives', 'AssetScoringSecurityObjectives').appendTo($cards);
        var $inputs = $('<div>').addClass('sr-asset-scoring-inputs').appendTo($objectives);
        SCORING_OBJECTIVES.forEach(function (o) {
            var id = baseId + '-' + o;
            var $select = $('<select>').addClass('form-select').attr({ id: id, name: o });
            $('<option>').val('').text(_lang['AssetScoringNotSet']).appendTo($select);
            scoringAllowed(o).forEach(function (code) {
                $('<option>').val(code).text(levelLabel(code)).appendTo($select);
            });
            $('<div>').addClass('sr-asset-scoring-objective')
                .append($('<div>').addClass('sr-asset-scoring-label-row')
                    .append($('<label>').addClass('sr-asset-scoring-label').attr('for', id).text(objectiveLabel(o)))
                    .append(scoringHelpIcon(o)))
                .append($select)
                .appendTo($inputs);
        });
        // Filled now, and made a live region only once the form has settled
        // (activateAssetScoring()), so neither the build nor the Add
        // defaults / Edit prefill is announced when the form opens; after
        // that each change is read as a whole (aria-atomic).
        var $results = $('<div>').addClass('sr-asset-scoring-results').appendTo($summary);
        buildResults($results, meterScale(field.scoring));
        fillResults($results, field.scoring ? scoringCompute(currentSelections($wrap), field.scoring) : null);
        return $wrap;
    }

    function activateAssetScoring($wrap) {
        var field = $wrap.data('field') || {};
        var ctx = $wrap.data('ctx') || {};
        // Ruling G5: the Preferences default pre-fills Add only.
        if (ctx.submitMode === 'create' && field.scoring && field.scoring.defaults) {
            SCORING_OBJECTIVES.forEach(function (o) {
                var d = field.scoring.defaults[o];
                if (typeof d === 'string' && scoringAllowed(o).indexOf(d) !== -1) {
                    $wrap.find('select[name="' + o + '"]').val(d);
                }
            });
        }
        $wrap.on('change', 'select', function () { refreshScoring($wrap); });
        // Esc with a guidance popover open closes only the popover. In the
        // record modal its capture-phase handler does this first (wherever
        // focus is); this covers focus inside the composite anywhere else.
        $wrap.on('keydown', function (e) {
            if (e.key === 'Escape' && hideScoringHelp($wrap[0])) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
        refreshScoring($wrap);
        // The engine prefills synchronously right after activate; going live
        // on the next tick keeps that prefill silent too.
        setTimeout(function () {
            $wrap.find('.sr-asset-scoring-results').first()
                .attr({ role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true' });
        }, 0);
    }

    // `value` is values.scoring.raw: {confidentiality, integrity, availability}.
    function prefillAssetScoring($wrap, value) {
        var stored = {};
        SCORING_OBJECTIVES.forEach(function (o) {
            var v = (value && typeof value[o] === 'string') ? value[o] : '';
            if (v && scoringAllowed(o).indexOf(v) === -1) {
                v = ''; // an unreadable stored code shows as Not set
            }
            stored[o] = v;
            $wrap.find('select[name="' + o + '"]').val(v);
        });
        $wrap.data('stored', stored);
        refreshScoring($wrap);
    }

    // The scoring part of the request body, as urlencoded pairs (see the
    // docblock above).
    function scoringPayload($form, mode) {
        var $wrap = $form.find('.' + SCORING_CLASS).not('.sr-asset-scoring-read').first();
        if (!$wrap.length) {
            return [];
        }
        var stored = $wrap.data('stored') || {};
        var parts = [];
        SCORING_OBJECTIVES.forEach(function (o) {
            var $select = $wrap.find('select[name="' + o + '"]');
            if (!$select.length) {
                return;
            }
            var v = $select.val() ? String($select.val()) : '';
            var send = mode === 'update' ? v !== (stored[o] || '') : v !== '';
            if (send) {
                parts.push(encodeURIComponent(o) + '=' + encodeURIComponent(v));
            }
        });
        return parts;
    }

    // View: the same two cards, read-only. The ratings are text (em dash when
    // unset); the Summary shows the server's result over the meter. Each
    // objective keeps its guidance icon, so the wording that defines the
    // levels is available while reading, not only while editing.
    function readAssetScoring(entry, field) {
        var raw = (entry && entry.raw) || {};
        var $wrap = $('<div>').addClass(SCORING_CLASS + ' sr-asset-scoring-read');
        var $cards = $('<div>').addClass('sr-asset-scoring-cards').appendTo($wrap);
        var $summary = scoringCard('sr-asset-scoring-summary', 'Summary').appendTo($cards);
        var $objectives = scoringCard('sr-asset-scoring-objectives', 'AssetScoringSecurityObjectives').appendTo($cards);
        var $dl = $('<dl>').addClass('sr-asset-scoring-selections').appendTo($objectives);
        SCORING_OBJECTIVES.forEach(function (o) {
            var text = (typeof raw[o] === 'string') ? levelLabel(raw[o]) : '';
            $('<div>')
                .append($('<dt>').text(objectiveLabel(o)).append(scoringHelpIcon(o)))
                .append($('<dd>').attr('data-objective', o).toggleClass('is-empty', !text).text(text || '—'))
                .appendTo($dl);
        });
        $summary.append(fillResults(buildResults($('<div>').addClass('sr-asset-scoring-results'),
            meterScale(field && field.scoring)), entry && entry.result));
        return $wrap;
    }

    // ------------------------------------------------------------------
    // Read rendering.
    // ------------------------------------------------------------------

    function chipsNode(names) {
        var $chips = $('<div>').addClass('sr-qfield-value--chips');
        names.forEach(function (name) {
            $('<span>').addClass('sr-chip').text(name).appendTo($chips);
        });
        return $chips;
    }

    function readMappedControls(entry) {
        var items = Array.isArray(entry.items) ? entry.items : [];
        if (!items.length) {
            return null; // the engine's em dash
        }
        var $stack = $('<div>').addClass('sr-qstack');
        items.forEach(function (item) {
            var controls = Array.isArray(item.controls) ? item.controls : [];
            var anyNamed = controls.some(function (c) { return c && c.name; });
            var $row = $('<div>').append($('<div>').addClass('sr-qhint').text(item.maturity_name || String(item.control_maturity)));
            if (anyNamed) {
                // A control the server could not name (deleted since) keeps
                // a '#<id>' chip rather than silently dropping out.
                $row.append(chipsNode(controls.map(function (c) {
                    return (c && c.name) ? String(c.name) : '#' + String(c && c.id);
                })));
            } else {
                // Names are sent only to Governance users; show the count.
                $row.append($('<div>').text(nControls(controls.length)));
            }
            $stack.append($row);
        });
        return $stack;
    }

    // ------------------------------------------------------------------
    // Associated risks without the Risk Management permission (SR-2313):
    // /ui/asset/fields says can_select_risks false and sends no roster, and
    // /ui/asset/{id}/values sends only `count` (risks the caller could see
    // under Team Separation). The field becomes this read-only type: no named
    // input, so Add/Edit never send associated_risks (the API refuses a body
    // that names them, 403) and a save keeps the stored links; no inline
    // editor (not an inline type); View shows the count, never a subject.
    // ------------------------------------------------------------------

    var LOCKED_RISKS = 'associated-risks-count';

    function widgetFor(field) {
        if (Number(field.is_basic) === 1 && field.name === 'AssociatedRisks' && field.can_select_risks !== true) {
            return LOCKED_RISKS;
        }
        return null;
    }

    function nAssociatedRisks(count) {
        return fillPlaceholder(_lang['NAssociatedRisks'], '{n}', count);
    }

    function buildLockedRisks(ctx) {
        var keys = ['ChoosingRisksNeedsRiskManagementPermission'];
        if (ctx && ctx.submitMode === 'update') {
            keys.push('SavingKeepsTheCurrentRiskAssociations');
        }
        return $('<div>').addClass('sr-qhint sr-asset-associated-risks-locked')
            .text(keys.map(function (key) { return _lang[key]; }).join(' '));
    }

    function readLockedRisks(entry) {
        var count = Number(entry && entry.count) || 0;
        return count > 0
            ? $('<div>').addClass('sr-asset-associated-risks-count').text(nAssociatedRisks(count))
            : null; // the engine's em dash
    }

    // location/team/associated_risks carry names as {id, name} objects; the
    // engine's chip path expects strings.
    function readNamedObjects(entry) {
        if (!Array.isArray(entry.names) || !entry.names.length) {
            return null;
        }
        var hasObjects = entry.names.some(function (n) { return n && typeof n === 'object'; });
        if (!hasObjects) {
            return null;
        }
        return chipsNode(entry.names.map(function (n) {
            return (n && typeof n === 'object') ? String(n.name || '') : String(n);
        }));
    }

    // ------------------------------------------------------------------
    // Widget registry.
    // ------------------------------------------------------------------

    function prepareValuationSelect($select, field, ctx) {
        // A valuation is always set; the built-in select's blank '--' option
        // would submit value='' and is removed.
        $select.find('option').filter(function () { return this.value === ''; }).remove();
        if (ctx.submitMode === 'create' && field.default_value !== undefined && field.default_value !== null) {
            $select.val(String(field.default_value));
        }
    }

    // A stored valuation that is no longer a level (legacy 0, reconfigured
    // levels) is kept as its own selected option, labelled by the stored
    // value, so an unrelated save sends it back unchanged instead of
    // silently moving the asset to whatever option happens to be first.
    function prefillValuation($select, value) {
        if (value === null || value === undefined || value === '') {
            return;
        }
        var stored = String(value);
        if (!$select.find('option').filter(function () { return this.value === stored; }).length) {
            $('<option>').val(stored).text(stored).appendTo($select);
        }
        $select.val(stored);
    }

    var widgetRegistry = {
        build: function (type, field, ctx) {
            if (type === 'mapped-controls') {
                return buildMappedControls(field, ctx);
            }
            if (type === 'asset-scoring') {
                return buildAssetScoring(field, ctx);
            }
            if (type === LOCKED_RISKS) {
                return buildLockedRisks(ctx);
            }
            return null;
        },
        activate: function (type, $el, field, ctx) {
            if (type === 'text' && MAX_LENGTHS[field.name]) {
                $el.attr('maxlength', MAX_LENGTHS[field.name]);
                return false;
            }
            if (type === 'select' && Number(field.is_basic) === 1 && field.name === 'AssetValuation') {
                prepareValuationSelect($el, field, ctx);
                return false;
            }
            if (type === 'mapped-controls') {
                activateMappedControls($el);
                return true;
            }
            if (type === 'asset-scoring') {
                activateAssetScoring($el);
                return true;
            }
            if (type === LOCKED_RISKS) {
                return true;
            }
            return false;
        },
        prefill: function (type, $el, value, ctx) {
            if (type === 'select' && ctx && ctx.formName === FORM_NAMES.AssetValuation) {
                prefillValuation($el, value);
                return true;
            }
            if (type === 'mapped-controls') {
                prefillMappedControls($el, value);
                return true;
            }
            if (type === 'asset-scoring') {
                prefillAssetScoring($el, value);
                return true;
            }
            if (type === LOCKED_RISKS) {
                return true; // nothing to prefill: no input, no ids
            }
            return false;
        },
        readRender: function (type, entry, ctx) {
            var field = (ctx && ctx.field) || {};
            if (Number(field.is_basic) === 1 && field.name === 'AssetScoring') {
                return readAssetScoring(entry, field);
            }
            if (Number(field.is_basic) === 1 && field.name === 'MappedControls') {
                return readMappedControls(entry);
            }
            if (type === LOCKED_RISKS) {
                return readLockedRisks(entry);
            }
            return readNamedObjects(entry);
        }
    };

    function assetUrl(id) {
        return '/api/v2/assets/' + encodeURIComponent(id);
    }

    // The View's inline editor body (RiskDetailsView profile.inlineSerialize):
    // the Asset Scoring field sends only its changed objectives, like Edit
    // (scoringPayload(), "Not set" as a present-empty clear). Every other
    // field keeps the engine's own body (null).
    function inlineSerialize(formEl, info) {
        if (info && info.widgetType === 'asset-scoring') {
            return scoringPayload($(formEl), 'update').join('&');
        }
        return null;
    }

    function makeProfile(endpoints) {
        return {
            endpoints: endpoints,
            maps: {
                widgets: WIDGETS,
                formNames: FORM_NAMES,
                cardLabels: CARD_LABELS,
                cardIcons: CARD_ICONS,
                multiValue: MULTI_VALUE,
                widgetFor: widgetFor
            },
            tagType: 'asset',
            requiredNames: ['AssetName'],
            widgetRegistry: widgetRegistry,
            submit: {
                createUrl: '/api/v2/assets',
                updateUrl: assetUrl,
                method: { create: 'POST', update: 'PATCH' },
                encode: 'urlencoded',
                serialize: serialize
            },
            inlineSaveUrl: assetUrl,
            // The Asset Scoring selects edit in place in View as well; Mapped
            // controls stays Edit-only.
            inlineWidgetTypes: { 'asset-scoring': true },
            inlineSerialize: inlineSerialize
        };
    }

    var BASE_ENDPOINTS = {
        templateGroups: '/ui/asset/template_groups',
        fields: '/ui/asset/fields',
        layout: '/ui/asset/layout',
        values: '/ui/asset/{id}/values'
    };

    var profile = makeProfile(BASE_ENDPOINTS);

    // One profile object per asset id, so the engines' per-profile caches
    // stay warm across re-renders of the same record.
    var perAsset = {};
    profile.forAsset = function (id) {
        var assetId = parseInt(id, 10);
        if (!(assetId > 0)) {
            return profile;
        }
        if (!perAsset[assetId]) {
            perAsset[assetId] = makeProfile($.extend({}, BASE_ENDPOINTS, {
                fields: BASE_ENDPOINTS.fields + '?asset_id=' + assetId,
                layout: BASE_ENDPOINTS.layout + '?asset_id=' + assetId
            }));
        }
        return perAsset[assetId];
    };

    window.AssetCardProfile = profile;
    // The shared-table harness (asset-scoring.spec.ts) calls the mirror directly.
    // hideHelp(root): the record modal's Esc closes an open guidance popover first.
    profile.scoring = { compute: scoringCompute, hideHelp: hideScoringHelp };
}());
