/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * The asset record modal (view / edit / add) on Manage assets
 * (asset record modal spec, docs/superpowers/specs/2026-09-29-asset-record-
 * modal-design.md §2). One wide .sr-modal.sr-modal--cards whose body is laid
 * out by the Customization template as Gridstack cards: RiskDetailsView for
 * View, RiskDetailsForm for Edit and Add, both driven by AssetCardProfile.
 *
 * URL-addressable:
 *   ?asset=<id>             view
 *   ?asset=<id>&mode=edit   edit
 *   ?asset=new              add
 * Opening and switching mode push a history entry; Back/Forward (popstate)
 * reopen, switch or close; closing clears only `asset`/`mode` with
 * replaceState and keeps every other param (tab, filters) and the hash.
 * parseRecordParams() validates the params: a positive integer id or `new`,
 * mode `edit` or nothing; anything else is ignored.
 *
 * Never shows an asset the caller cannot access: view/edit fetch
 * GET /ui/asset/{id}/values BEFORE the modal is shown. The server answers a
 * foreign-team and a nonexistent asset with the same 404 and the same
 * message, and that message is the only thing shown (a toast; no modal).
 *
 * Coordinator shape (pages/risk-view-details.js): a generation counter drops
 * every stale response, each render destroys the other engine first, and
 * the last View payload is cached so Cancel returns to View without a
 * refetch.
 *
 * Escaping: every value from the API is RAW text and reaches the DOM through
 * text/attribute setters or the engines' own purified paths. Toasts get text
 * escaped once here (esc()) or a server message the server escaped once.
 *
 * Host hooks (AssetRecordModal.init(host)):
 *   host.changed(kind)          the list must refresh ('saved' | 'created' | 'verified')
 *   host.rememberOpener()       note what had focus before the modal opened
 *   host.restoreFocus()         put it back after the modal closed
 *   host.addToGroup(id, name)   open the Add to group dialog for one asset
 *   host.deleteAsset(id, name)  open the delete confirm for one asset
 *   host.valuation(id)          {level, range, label} for a valuation id, or null
 */
(function (window, $) {
    'use strict';

    var MODAL = '#asset-record-modal';
    var VIEW = '#asset-record-view';
    var FORM = '#asset-record-form';

    function L(key) {
        return (window._lang && window._lang[key] !== undefined) ? window._lang[key] : key;
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // {$name} placeholders, substituted literally (a function replacer, so a
    // value holding '$&' is inserted as typed).
    function fmt(key, params) {
        var s = String(L(key));
        Object.keys(params || {}).forEach(function (k) {
            s = s.split('{$' + k + '}').join(String(params[k]));
        });
        return s;
    }

    /* ---------------- URL <-> record state (pure) ---------------- */

    // {id: <positive int as string> | 'new', mode: 'view' | 'edit' | 'add'}
    // or null when the URL names no (valid) record.
    function parseRecordParams(search) {
        var p = new URLSearchParams(search || '');
        var raw = p.get('asset');
        if (raw === null) {
            return null;
        }
        if (raw === 'new') {
            return { id: 'new', mode: 'add' };
        }
        if (!/^[1-9]\d{0,9}$/.test(raw) || Number(raw) > 2147483647) {
            return null;
        }
        return { id: raw, mode: p.get('mode') === 'edit' ? 'edit' : 'view' };
    }

    // The query string with `asset`/`mode` set for `rec` (or removed when rec
    // is null); every other param keeps its place and value.
    function serializeRecordParams(rec, existingSearch) {
        var p = new URLSearchParams(existingSearch || '');
        p.delete('asset');
        p.delete('mode');
        if (rec) {
            p.set('asset', rec.mode === 'add' ? 'new' : String(rec.id));
            if (rec.mode === 'edit') {
                p.set('mode', 'edit');
            }
        }
        var qs = p.toString().replace(/%2C/gi, ',');
        return qs ? '?' + qs : '';
    }

    function urlFor(rec) {
        var loc = window.location;
        return loc.pathname + serializeRecordParams(rec, loc.search) + loc.hash;
    }

    function sameRec(a, b) {
        return !!a && !!b && a.mode === b.mode && String(a.id) === String(b.id);
    }

    /* ---------------- module state ---------------- */

    var host = {};
    var bound = false;
    var generation = 0;
    var modalInstance = null;
    var shown = false;
    var closing = false;          // an allowed hide is in progress
    var afterHidden = null;       // one callback to run once the modal is hidden
    var current = null;           // {id, mode, values, canEdit, canVerify, canDelete, templateGroupId}
    var viewCache = null;         // {id, data} of the last View render
    var templateGroups = [];      // add mode: [{id, name}]
    var discardThen = null;       // the action waiting on the discard confirm
    var saving = false;
    var pendingClose = null;      // a close asked for during the show transition

    function profileFor(id) {
        var base = window.AssetCardProfile;
        return id ? base.forAsset(id) : base;
    }

    // The engines' profile plus the Verification card's value, which lives
    // outside the engine's <form>. Memoised per base profile so the engines'
    // per-profile caches stay warm.
    var derived = [];
    function formProfile(base) {
        for (var i = 0; i < derived.length; i++) {
            if (derived[i].base === base) {
                return derived[i].profile;
            }
        }
        var p = $.extend({}, base);
        p.submit = $.extend({}, base.submit, {
            serialize: function ($form, opts) {
                var body = base.submit.serialize($form, opts);
                var extra = verificationPayload((opts && opts.mode) || 'create');
                if (!extra) {
                    return body;
                }
                return body ? body + '&' + extra : extra;
            }
        });
        derived.push({ base: base, profile: p });
        return p;
    }

    // The View's profile: the base profile plus this module's inline-save
    // hooks (memoised per base, like formProfile()).
    var viewDerived = [];
    function viewProfile(base) {
        for (var i = 0; i < viewDerived.length; i++) {
            if (viewDerived[i].base === base) {
                return viewDerived[i].profile;
            }
        }
        var p = $.extend({}, base, { onInlineSaved: inlineSaved, onInlineSaveFailed: inlineSaveFailed });
        viewDerived.push({ base: base, profile: p });
        return p;
    }

    /* ---------------- View: per-field inline edit ----------------
     *
     * With asset_edit (payload.can_edit) the View is rendered inline-editable:
     * RiskDetailsView puts a pencil beside every field whose widget type it
     * can edit in place (text, select, multiselect, tags, richtext, and the
     * Asset Scoring selects through the profile's inlineWidgetTypes; never
     * the Mapped controls composite), and Save PATCHes just that field through
     * the engine (profile.inlineSaveUrl). This module adds, around the
     * engine's controls:
     *   - a per-field accessible name on each pencil ("Edit <label>");
     *   - one open field at a time (another pencil asks first when the open
     *     one has changes);
     *   - a dirty baseline per open field, so the record's dirty guard (×,
     *     Back, Edit asset, ⋯ actions, another record) covers it;
     *   - required fields validated before anything is sent, and no second
     *     request while one is in flight (Enter included);
     *   - Esc cancels the open field (asking first when it has changes), not
     *     the record;
     *   - focus into the control on open and back to the pencil afterwards;
     *   - after a save, the record is re-read (GET /values): header, footer,
     *     cards and cache follow the stored asset (the server may have
     *     returned a renamed verified asset to unverified), and the list
     *     refreshes. An asset that has gone (404) closes the record with the
     *     same toast as everywhere else.
     */

    function inlineAllowed() {
        return !!current && current.mode === 'view' && !!current.canEdit;
    }

    function openInlineForms() {
        return $(VIEW).find('.sr-qfield-edit-form').toArray();
    }

    function inlineItemOf(el) {
        return $(el).closest('.sr-qfield')[0] || null;
    }

    function itemLabel(item) {
        return $.trim($(item).children('.sr-qlabel').first().text());
    }

    function editorsIn(form) {
        var eds = [];
        if (typeof hugerte === 'undefined') {
            return eds;
        }
        $(form).find('textarea[id]').each(function () {
            var ed = hugerte.get(this.id);
            if (ed) {
                eds.push(ed);
            }
        });
        return eds;
    }

    // Every non-richtext control's value (richtext compares editor content).
    function fieldSnapshot(form) {
        return $(form).find(':input').not('textarea, button').serialize();
    }

    function takeInlineBaseline(form) {
        var base = { fields: fieldSnapshot(form), textareas: {}, editors: {} };
        $(form).find('textarea[id]').each(function () {
            base.textareas[this.id] = String($(this).val() || '');
        });
        $(form).data('armBaseline', base);
        editorsIn(form).forEach(function (ed) {
            var capture = function () {
                base.editors[ed.id] = ed.getContent();
            };
            if (ed.initialized) {
                capture();
            } else {
                ed.on('init', capture);
            }
            // Keydown inside the editor's iframe never reaches the dialog.
            ed.on('keydown', function (e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    escapeInline(form);
                }
            });
        });
    }

    function inlineFormDirty(form) {
        var base = $(form).data('armBaseline');
        if (!base) {
            return false;
        }
        if (fieldSnapshot(form) !== base.fields) {
            return true;
        }
        var dirty = false;
        $(form).find('textarea[id]').each(function () {
            var ed = (typeof hugerte !== 'undefined') ? hugerte.get(this.id) : null;
            if (ed && ed.initialized) {
                if (base.editors[this.id] !== undefined && ed.getContent() !== base.editors[this.id]) {
                    dirty = true;
                }
            } else if (String($(this).val() || '') !== base.textareas[this.id]) {
                dirty = true;
            }
        });
        return dirty;
    }

    function inlineDirty() {
        return openInlineForms().some(inlineFormDirty);
    }

    // The field definition behind an open control, from its form name.
    function inlineFieldOf(form) {
        var data = viewCache && viewCache.data;
        var fields = (data && data.fields) || [];
        var formNames = ((profileFor(current && current.id).maps) || {}).formNames || {};
        var found = null;
        $(form).find('[name]').each(function () {
            var name = String(this.name || '').replace(/\[\]$/, '');
            var custom = /^custom_field\[(\d+)\]$/.exec(name);
            fields.forEach(function (f) {
                if (found) {
                    return;
                }
                if (custom ? (Number(f.is_basic) !== 1 && String(f.id) === custom[1])
                    : (Number(f.is_basic) === 1 && formNames[f.name] === name)) {
                    found = f;
                }
            });
            return !found;
        });
        return found;
    }

    // Required-ness reaches the DOM the way the Edit form sets it, so the
    // shared checkAndSetValidation() marks, focuses and names the field.
    function markInlineRequired(form, label) {
        var field = inlineFieldOf(form);
        if (!field) {
            return;
        }
        var required = (profileFor(current.id).requiredNames || []).indexOf(field.name) !== -1 || Number(field.required) === 1;
        if (!required) {
            return;
        }
        $(form).find('input[name], select[name]').not('[type="hidden"]').first()
            .attr('required', 'required').attr('title', label);
    }

    function focusInlineControl(form) {
        var eds = editorsIn(form);
        if (eds.length) {
            var ed = eds[0];
            if (ed.initialized) {
                ed.focus();
            } else {
                ed.on('init', function () { ed.focus(); });
            }
            return;
        }
        $(form).find('.selectize-input input:visible, button.multiselect:visible, input:visible, select:visible')
            .not('[type="hidden"]').first().trigger('focus');
    }

    function syncEditors(form) {
        editorsIn(form).forEach(function (ed) {
            if (ed.initialized) {
                ed.save();
            }
        });
    }

    function inlineWidgetDropdownOpen(form) {
        return $(form).find('.selectize-input.dropdown-active, .dropdown-menu.show').length > 0;
    }

    // Cancels an open field through its own Cancel (the engine closes the
    // row; the click listeners below return focus to its pencil).
    function cancelInline(form) {
        var btn = $(form).find('.cancel-edit-field')[0];
        if (btn) {
            btn.click();
        }
    }

    function cancelOpenInline() {
        openInlineForms().forEach(cancelInline);
    }

    function escapeInline(form) {
        if (inlineFormDirty(form)) {
            askDiscard(function () { cancelInline(form); });
        } else {
            cancelInline(form);
        }
    }

    // Per-field accessible names on the engine's generic pencils.
    function decorateInlineEdit() {
        $(VIEW).find('.sr-qfield--inline-editable').each(function () {
            var label = itemLabel(this);
            if (label) {
                var text = fmt('AssetRecordEditField', { field: label });
                $(this).find('.sr-qfield-edit-btn').attr({ 'aria-label': text, title: text });
            }
        });
    }

    function pencilFor(label) {
        var $item = $(VIEW).find('.sr-qfield--inline-editable').filter(function () {
            return itemLabel(this) === label;
        }).first();
        return $item.find('.sr-qfield-edit-btn');
    }

    function drawView(id, data) {
        RiskDetailsView.renderFromData(VIEW, data, id, inlineAllowed(), {
            profile: viewProfile(profileFor(id)), honorCardWidths: true, responsive: true
        });
        decorateInlineEdit();
    }

    // An inline save in flight: from the PATCH until its re-read settles.
    // While busy the record is neither closable nor switchable (the write
    // cannot be taken back, so there is nothing to "discard"): ×, Close,
    // Esc, the backdrop, Back, Edit asset, the ⋯ verbs and another pencil
    // wait. `label` is the saved field's label, captured at the Save click,
    // for the focus return.
    var inlineSave = { busy: false, label: '', seq: 0 };

    function inlineBusy() {
        return inlineSave.busy;
    }

    function beginInlineSave(label) {
        inlineSave.busy = true;
        inlineSave.label = label;
    }

    function endInlineSave() {
        inlineSave.busy = false;
    }

    // profile.onInlineSaved: the write happened. Re-read the record so every
    // derived piece (verified pill, valuation, title, provenance) is the
    // stored truth, then redraw in place. Re-reads are sequenced: only the
    // latest one for the record draws.
    function inlineSaved(field, response, ctx) {
        var id = String(ctx.recordId);
        var gen = generation;
        var label = inlineSave.label;
        var seq = ++inlineSave.seq;
        if (response && response.status_message) {
            showAlertFromMessage(response.status_message, true); // escaped once at the server
        }
        if (host.changed) {
            host.changed('saved');
        }
        var stale = function () {
            return gen !== generation || seq !== inlineSave.seq || !current || current.id !== id;
        };
        fetchValues(id).done(function (payload) {
            if (stale()) {
                return;
            }
            endInlineSave();
            if (!payload) {
                notAvailable(null);
                return;
            }
            if (!redrawWithValues(id, payload)) {
                return;
            }
            var $pencil = pencilFor(label);
            ($pencil.length ? $pencil : $('#asset-record-title')).trigger('focus');
        }).fail(function (xhr) {
            if (stale()) {
                return;
            }
            endInlineSave();
            if (xhr && xhr.status === 404) {
                notAvailable(xhr);
                return;
            }
            // Saved, but the record could not be re-read. Keep the last good
            // View on screen (the field closes; its Save is still disabled),
            // and mark the cache stale so Edit and a later View fetch afresh
            // instead of starting from pre-save values.
            if (viewCache && viewCache.id === id) {
                viewCache.stale = true;
            }
            cancelOpenInline();
            showAlertFromMessage(esc(L('AssetRecordLoadFailed')), false);
        });
    }

    // A re-read record's values: kept as the current record and the View
    // cache, and the View redrawn in place. True when it was redrawn; with
    // no View to lay the values out with, the whole View is fetched instead.
    function redrawWithValues(id, payload) {
        current.values = payload.values || {};
        current.canEdit = !!payload.can_edit;
        current.canVerify = !!payload.can_verify;
        current.canDelete = !!payload.can_delete;
        if (!viewCache || viewCache.id !== id || !viewCache.data) {
            viewCache = null;
            if (current.mode === 'view') {
                load({ id: id, mode: 'view' });
            }
            return false;
        }
        var data = $.extend({}, viewCache.data, { values: payload.values || {} });
        viewCache = { id: id, data: data, payload: payload };
        if (current.mode !== 'view') {
            return false;
        }
        renderChrome();
        drawView(id, data);
        return true;
    }

    // A save went through but its re-read failed (viewCache.stale): the View
    // still shows the pre-save values, and an inline control prefills from
    // them. Read the record first, redraw, then open the field the user
    // asked for, so the control starts from what is stored.
    function viewIsStale() {
        return !!(current && viewCache && viewCache.stale && viewCache.id === current.id);
    }

    function rereadThenOpenInline(label) {
        var id = current.id;
        var gen = generation;
        fetchValues(id).done(function (payload) {
            if (gen !== generation || !current || current.id !== id || current.mode !== 'view') {
                return;
            }
            if (!payload) {
                notAvailable(null);
                return;
            }
            if (!redrawWithValues(id, payload)) {
                return;
            }
            var $pencil = pencilFor(label);
            if ($pencil.length) {
                $pencil[0].click();
            } else {
                $('#asset-record-title').trigger('focus');
            }
        }).fail(function (xhr) {
            if (gen !== generation || !current || current.id !== id) {
                return;
            }
            if (xhr && xhr.status === 404) {
                notAvailable(xhr);
                return;
            }
            showAlertFromMessage(esc(L('AssetRecordLoadFailed')), false);
        });
    }

    // profile.onInlineSaveFailed: an asset that has gone meanwhile closes the
    // record with the one "not available" toast. Anything else keeps the
    // control open with the user's input (the engine's own toast, and it
    // re-enables Save). A response for a record no longer shown is left to
    // the engine.
    function inlineSaveFailed(field, xhr, ctx) {
        if (!current || current.id !== String(ctx && ctx.recordId)) {
            return false;
        }
        endInlineSave();
        if (xhr && xhr.status === 404) {
            notAvailable(xhr);
            return true;
        }
        return false;
    }

    // Runs `fn` now, or after the discard confirm when an open field has
    // changes; nothing while an inline save is in flight.
    function guardInline(fn) {
        if (inlineBusy()) {
            return;
        }
        if (isDirty()) {
            askDiscard(fn);
        } else {
            fn();
        }
    }

    function bindInlineEdit() {
        var view = $(VIEW)[0];
        var focusAfterCancel = null;

        // Capture: runs before the engine's own button handlers.
        view.addEventListener('click', function (e) {
            if (!current || current.mode !== 'view') {
                return;
            }
            var pencil = e.target.closest('.sr-qfield-edit-btn');
            if (pencil && inlineBusy()) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            if (pencil) {
                var mine = inlineItemOf(pencil);
                var others = openInlineForms().filter(function (f) { return inlineItemOf(f) !== mine; });
                if (others.some(inlineFormDirty)) {
                    e.preventDefault();
                    e.stopPropagation();
                    askDiscard(function () {
                        others.forEach(cancelInline);
                        pencil.click();
                    });
                    return;
                }
                others.forEach(cancelInline);
                if (viewIsStale()) {
                    e.preventDefault();
                    e.stopPropagation();
                    rereadThenOpenInline(itemLabel(mine));
                }
                return;
            }
            var cancelBtn = e.target.closest('.cancel-edit-field');
            if (cancelBtn) {
                focusAfterCancel = $(inlineItemOf(cancelBtn)).find('.sr-qfield-edit-btn')[0] || null;
                return;
            }
            var saveBtn = e.target.closest('.save-edit-field');
            if (saveBtn) {
                var form = $(saveBtn).closest('form')[0];
                syncEditors(form);
                if (inlineBusy() || (typeof checkAndSetValidation === 'function' && !checkAndSetValidation($(form)))) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                beginInlineSave(itemLabel(inlineItemOf(saveBtn)));
            }
        }, true);

        // Enter inside a control submits the engine's <form>, which triggers
        // Save's jQuery handler directly: validate and de-duplicate here.
        view.addEventListener('submit', function (e) {
            var form = e.target;
            if (!$(form).hasClass('sr-qfield-edit-form')) {
                return;
            }
            syncEditors(form);
            if (inlineBusy() || $(form).find('.save-edit-field').prop('disabled')
                || (typeof checkAndSetValidation === 'function' && !checkAndSetValidation($(form)))) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            // The engine's submit handler triggers Save's jQuery handler
            // directly (the capture click listener above never sees it).
            beginInlineSave(itemLabel(inlineItemOf(form)));
        }, true);

        // Bubble (native, so it still fires after the engine removed the
        // clicked Cancel): focus back on the pencil; after an open, set the
        // baseline and move focus into the control.
        view.addEventListener('click', function (e) {
            if (focusAfterCancel) {
                var target = focusAfterCancel;
                focusAfterCancel = null;
                if (document.body.contains(target)) {
                    target.focus();
                }
                return;
            }
            var pencil = e.target.closest && e.target.closest('.sr-qfield-edit-btn');
            if (!pencil || !current || current.mode !== 'view') {
                return;
            }
            var item = inlineItemOf(pencil);
            var form = $(item).find('.sr-qfield-edit-form')[0];
            if (!form) {
                return;
            }
            markInlineRequired(form, itemLabel(item));
            takeInlineBaseline(form);
            focusInlineControl(form);
        });
    }

    /* ---------------- Verification card ---------------- */

    function verificationAvailable() {
        return !!current && !!current.canVerify && (current.mode === 'edit' || current.mode === 'add');
    }

    function verificationStored() {
        if (!current || current.mode === 'add') {
            return true; // a verifier's own creations start verified (spec §10)
        }
        return String(((current.values || {}).verified || {}).raw) === '1';
    }

    function verificationValue() {
        return $('#asset-record-verified').is(':checked');
    }

    // Sent only by a verifier; on update only when it changed (an unchanged
    // value leaves the decision to the server's edit rules).
    function verificationPayload(mode) {
        if (!verificationAvailable()) {
            return '';
        }
        var on = verificationValue();
        if (mode === 'update' && on === verificationStored()) {
            return '';
        }
        return 'verified=' + (on ? '1' : '0');
    }

    function setupVerificationCard() {
        var $card = $('#asset-record-verification');
        if (!verificationAvailable()) {
            $card.prop('hidden', true);
            return;
        }
        $('#asset-record-verified').prop('checked', verificationStored());
        $card.prop('hidden', false);
    }

    /* ---------------- dirty state ---------------- */

    function isDirty() {
        if (!current) {
            return false;
        }
        if (current.mode === 'view' || current.mode === 'audit') {
            // An open inline field with changes (the audit pane only hides it).
            return inlineDirty();
        }
        if (current.mode !== 'edit' && current.mode !== 'add') {
            return false;
        }
        var formDirty = !!(window.RiskDetailsForm && RiskDetailsForm.isDirty(FORM));
        var verDirty = verificationAvailable() && verificationValue() !== verificationStored();
        return formDirty || verDirty;
    }

    /* ---------------- rendering helpers ---------------- */

    function modal() {
        if (!modalInstance) {
            modalInstance = bootstrap.Modal.getOrCreateInstance($(MODAL)[0]);
        }
        return modalInstance;
    }

    function setBusy(on) {
        $(MODAL).find('.modal-content').attr('aria-busy', on ? 'true' : 'false');
        $('#asset-record-loading').prop('hidden', !on);
    }

    function teardownEngines() {
        if (window.RiskDetailsForm) {
            RiskDetailsForm.destroy(FORM);
        }
        if (window.RiskDetailsView) {
            RiskDetailsView.destroy(VIEW);
        }
        $(VIEW).empty();
        $(FORM).empty();
    }

    function hideDiscardConfirm() {
        discardThen = null;
        $('#asset-record-discard-live').text('');
        $('#asset-record-discard').prop('hidden', true);
        $('#asset-record-foot').prop('hidden', false);
    }

    function closeMenu(refocus) {
        var $toggle = $('#asset-record-more-toggle');
        if ($toggle.attr('aria-expanded') !== 'true') {
            return;
        }
        $('#asset-record-more-menu').prop('hidden', true);
        $toggle.attr('aria-expanded', 'false');
        if (refocus) {
            $toggle.trigger('focus');
        }
    }

    function valuationNodes(id) {
        var v = host.valuation ? host.valuation(id) : null;
        if (!v) {
            return [];
        }
        var nodes = [];
        if (v.level) {
            nodes.push($('<span>', { 'class': 'sr-arm-val-chip', text: String(v.level) }));
        }
        var range = String(v.range || (v.level ? '' : v.label || ''));
        if (range) {
            nodes.push($('<span>', { 'class': 'sr-arm-val-range sr-num', text: range }));
        }
        return nodes;
    }

    // Header + footer for the current mode. Values are text only.
    function renderChrome() {
        var mode = current.mode;
        var values = current.values || {};
        var name = String(((values.name || {}).raw) || '');
        var $title = $('#asset-record-title');
        var $meta = $('#asset-record-meta').empty();

        var icons = { view: 'fa-server', audit: 'fa-server', add: 'fa-plus', edit: 'fa-pen' };
        $('#asset-record-icon').attr('class', 'fa ' + (icons[mode] || 'fa-server'));

        if (mode === 'add') {
            $title.text(L('AddAsset'));
        } else if (mode === 'edit') {
            $title.text(L('AssetRecordEdit'));
            $meta.append($('<span>', { 'class': 'sr-arm-meta-text', text: name }))
                .append($('<span>', { 'class': 'sr-arm-meta-text', text: fmt('AssetRecordIdN', { id: current.id }) }));
        } else {
            $title.text(name);
            $meta.append($('<span>', { 'class': 'sr-arm-meta-text', text: fmt('AssetRecordIdN', { id: current.id }) }));
            var val = valuationNodes((values.value || {}).raw);
            if (val.length) {
                $meta.append($('<span>', { 'class': 'sr-arm-val' }).append(val));
            }
            var verified = String((values.verified || {}).raw) === '1';
            $meta.append($('<span>', {
                'class': 'sr-state-pill ' + (verified ? 'sr-state-success' : 'sr-state-warning'),
                text: verified ? L('Verified') : L('Unverified')
            }));
        }

        // Header actions (View only). Each exists in the markup only when
        // the page's caps allow it; the record's own flags narrow it further.
        var inView = mode === 'view';
        $('#asset-record-actions').prop('hidden', !inView);
        $('#asset-record-edit').prop('hidden', !(inView && current.canEdit));
        var verifiedNow = String((values.verified || {}).raw) === '1';
        $('#asset-record-verify').prop('hidden', !(current.canVerify && !verifiedNow));
        $('#asset-record-unverify').prop('hidden', !(current.canVerify && verifiedNow));
        $('#asset-record-delete').prop('hidden', !current.canDelete);
        var anyItem = $('#asset-record-more-menu [role="menuitem"]').filter(function () { return !this.hidden; }).length > 0;
        $('#asset-record-more').prop('hidden', !anyItem);

        // Footer
        var created = (values.created || {}).display || '';
        var hint = '';
        if (inView) {
            hint = fmt(verifiedNow ? 'AssetRecordProvenanceVerified' : 'AssetRecordProvenanceUnverified', { date: created });
        } else if (mode === 'audit') {
            hint = '';
        } else {
            hint = L('AssetRecordUnsavedHint');
        }
        $('#asset-record-hint').text(hint);
        $('#asset-record-close').prop('hidden', !inView);
        $('#asset-record-back').prop('hidden', mode !== 'audit');
        $('#asset-record-close-audit').prop('hidden', mode !== 'audit');
        $('#asset-record-cancel').prop('hidden', !(mode === 'edit' || mode === 'add'));
        $('#asset-record-save').prop('hidden', !(mode === 'edit' || mode === 'add'));
        $('#asset-record-audit').prop('hidden', mode !== 'audit');
        $(VIEW).prop('hidden', mode !== 'view');
        $(FORM).prop('hidden', !(mode === 'edit' || mode === 'add'));
        if (!(mode === 'edit' || mode === 'add')) {
            $('#asset-record-verification').prop('hidden', true);
        }
        $('#asset-record-templates').prop('hidden', !(mode === 'add' && templateGroups.length > 1));
        $(MODAL).attr('data-mode', mode);
        hideDiscardConfirm();
        keepFocusInModal();
    }

    // A mode switch hides the control that had focus (Edit, Save, a menu
    // item): move focus to the title rather than letting it drop to <body>.
    function keepFocusInModal() {
        setTimeout(function () {
            if (!shown) {
                return;
            }
            var active = document.activeElement;
            var modalEl = $(MODAL)[0];
            if (!active || active === document.body || !modalEl.contains(active) || !$(active).is(':visible')) {
                $('#asset-record-title').trigger('focus');
            }
        }, 0);
    }

    /* ---------------- data ---------------- */

    function api(path, data) {
        return $.ajax({ type: 'GET', url: BASE_URL + '/api/v2' + path, data: data || {}, dataType: 'json' });
    }

    // The one failure message: the server's (escaped once at its source) when
    // it sent one, else a generic one escaped here. Never the asset's name.
    function failureMessage(xhr) {
        var msg = xhr && xhr.responseJSON && xhr.responseJSON.status_message;
        if (xhr && xhr.status === 404 && typeof msg === 'string' && msg) {
            return msg;
        }
        return esc(L('AssetRecordLoadFailed'));
    }

    function fetchValues(id) {
        return api('/ui/asset/' + encodeURIComponent(id) + '/values').then(function (json) {
            return (json && json.data) || null;
        });
    }

    function fetchViewData(id, valuesPayload) {
        var endpoints = profileFor(id).endpoints;
        var params = { template_group_id: valuesPayload.template_group_id || '', tab_index: 1 };
        return $.when(api(endpoints.fields, params), api(endpoints.layout, params)).then(function (f, c) {
            return {
                fields: (f[0] && f[0].data) || [],
                cards: (c[0] && c[0].data) || [],
                values: valuesPayload.values || {},
                template_group_id: valuesPayload.template_group_id
            };
        });
    }

    /* ---------------- modes ---------------- */

    function showModal() {
        if (!shown) {
            shown = true;
            closing = false;
            modal().show();
        }
    }

    function renderView(id, payload, gen) {
        teardownEngines();
        current = {
            id: String(id), mode: 'view', values: payload.values || {},
            canEdit: !!payload.can_edit, canVerify: !!payload.can_verify, canDelete: !!payload.can_delete,
            templateGroupId: payload.template_group_id
        };
        renderChrome();
        showModal();
        var draw = function (data) {
            if (gen !== generation) {
                return;
            }
            viewCache = { id: String(id), data: data, payload: payload };
            drawView(id, data);
            setBusy(false);
        };
        if (viewCache && viewCache.id === String(id) && viewCache.payload === payload) {
            draw(viewCache.data);
            return;
        }
        setBusy(true);
        fetchViewData(id, payload).done(draw).fail(function () {
            if (gen !== generation) {
                return;
            }
            setBusy(false);
            showAlertFromMessage(esc(L('AssetRecordLoadFailed')), false);
        });
    }

    function renderEdit(id, payload) {
        teardownEngines();
        var st = history.state && history.state.assetRecord;
        current = {
            id: String(id), mode: 'edit', values: payload.values || {},
            canEdit: !!payload.can_edit, canVerify: !!payload.can_verify, canDelete: !!payload.can_delete,
            templateGroupId: payload.template_group_id,
            // Entered from this asset's View (also after a reload or Forward).
            fromView: !!(st && st.fromView && st.mode === 'edit' && String(st.id) === String(id))
        };
        renderChrome();
        setupVerificationCard();
        showModal();
        var prefill = {};
        $.each(payload.values || {}, function (key, entry) {
            prefill[key] = entry ? entry.raw : null;
        });
        RiskDetailsForm.init(FORM, {
            profile: formProfile(profileFor(id)),
            prefillValues: prefill,
            submitMode: 'update',
            updateRiskId: Number(id),
            pinnedTemplateGroupId: payload.template_group_id,
            actionsBar: false,
            responsive: true
        });
        setBusy(false);
    }

    function renderAdd(groupId) {
        teardownEngines();
        current = {
            id: 'new', mode: 'add', values: {},
            canEdit: true, canVerify: !!host.canVerify, canDelete: false,
            templateGroupId: groupId
        };
        renderChrome();
        setupVerificationCard();
        $('#asset-record-templates .sr-arm-seg').each(function () {
            var on = String($(this).attr('data-template-group-id')) === String(groupId);
            $(this).toggleClass('is-active', on).attr('aria-checked', on ? 'true' : 'false').attr('tabindex', on ? '0' : '-1');
        });
        showModal();
        RiskDetailsForm.init(FORM, {
            profile: formProfile(profileFor(null)),
            submitMode: 'create',
            pinnedTemplateGroupId: groupId,
            actionsBar: false,
            responsive: true
        });
        setBusy(false);
    }

    function buildTemplateSwitch(groups) {
        var $wrap = $('#asset-record-templates-options').empty();
        groups.forEach(function (g) {
            $('<button>', {
                type: 'button', 'class': 'sr-arm-seg', role: 'radio',
                'data-template-group-id': String(g.id), 'aria-checked': 'false', tabindex: '-1',
                text: String(g.name)
            }).appendTo($wrap);
        });
    }

    // Loads and renders `rec`. For view/edit the values come first: a record
    // the server will not show (404) ends in notAvailable() before anything
    // is rendered or the modal is shown.
    function load(rec) {
        generation += 1;
        endInlineSave();
        var gen = generation;
        closeMenu(false);

        if (rec.mode === 'add') {
            setBusy(true);
            api(window.AssetCardProfile.endpoints.templateGroups).done(function (json) {
                if (gen !== generation) {
                    return;
                }
                templateGroups = ((json && json.data) || []).map(function (g) { return { id: g.id, name: g.name }; });
                buildTemplateSwitch(templateGroups);
                if (!templateGroups.length) {
                    setBusy(false);
                    showAlertFromMessage(esc(L('AssetRecordLoadFailed')), false);
                    if (shown) {
                        requestClose(true);
                    } else {
                        clearUrl();
                    }
                    return;
                }
                renderAdd(templateGroups[0].id);
            }).fail(function () {
                if (gen !== generation) {
                    return;
                }
                setBusy(false);
                showAlertFromMessage(esc(L('AssetRecordLoadFailed')), false);
                if (shown) {
                    requestClose(true);
                } else {
                    clearUrl();
                }
            });
            return;
        }

        var useCache = rec.mode === 'view' && viewCache && !viewCache.stale && viewCache.id === String(rec.id) && rec.fromCache;
        if (useCache) {
            renderView(rec.id, viewCache.payload, gen);
            return;
        }
        setBusy(true);
        fetchValues(rec.id).done(function (payload) {
            if (gen !== generation) {
                return;
            }
            if (!payload) {
                notAvailable(null);
                return;
            }
            if (rec.mode === 'edit' && !payload.can_edit) {
                // No asset_edit: the record opens read-only and the URL says so.
                rec = { id: rec.id, mode: 'view' };
                replaceUrl(rec);
            }
            if (rec.mode === 'edit') {
                renderEdit(rec.id, payload);
            } else {
                renderView(rec.id, payload, gen);
            }
        }).fail(function (xhr) {
            if (gen !== generation) {
                return;
            }
            notAvailable(xhr);
        });
    }

    // The same toast for a forbidden and a nonexistent asset, and no modal.
    function notAvailable(xhr) {
        setBusy(false);
        showAlertFromMessage(failureMessage(xhr), false);
        viewCache = null;
        if (shown) {
            requestClose(true);
        } else {
            current = null;
            clearUrl();
        }
    }

    /* ---------------- history ---------------- */

    function pushUrl(rec, extraState) {
        try {
            history.pushState($.extend({}, history.state || {}, { assetRecord: $.extend({ id: String(rec.id), mode: rec.mode }, extraState || {}) }), '', urlFor(rec));
        } catch (err) { void err; }
    }

    function replaceUrl(rec) {
        try {
            var state = $.extend({}, history.state || {});
            if (rec) {
                // Rewriting the same Edit entry (a reload normalising its
                // URL) keeps whether it was entered from View.
                var prev = state.assetRecord;
                state.assetRecord = { id: String(rec.id), mode: rec.mode };
                if (rec.mode === 'edit' && prev && prev.fromView && prev.mode === 'edit' && String(prev.id) === String(rec.id)) {
                    state.assetRecord.fromView = true;
                }
            } else {
                delete state.assetRecord;
            }
            history.replaceState(state, '', urlFor(rec));
        } catch (err) { void err; }
    }

    function clearUrl() {
        if (parseRecordParams(window.location.search)) {
            replaceUrl(null);
        }
    }

    function urlRec() {
        return parseRecordParams(window.location.search);
    }

    /* ---------------- open / close ---------------- */

    // opts: {id: <int>|'new', mode: 'view'|'edit'|'add', history: 'push'|'replace'|'none', rememberOpener: bool}
    function open(opts) {
        opts = opts || {};
        var rec = opts.id === 'new' || opts.mode === 'add'
            ? { id: 'new', mode: 'add' }
            : parseRecordParams('?asset=' + encodeURIComponent(String(opts.id)) + (opts.mode === 'edit' ? '&mode=edit' : ''));
        if (!rec) {
            return;
        }
        if (shown && current && sameRec(current, rec)) {
            return; // already showing exactly this
        }
        var go = function () {
            if (!shown && opts.rememberOpener !== false && host.rememberOpener) {
                host.rememberOpener();
            }
            if (opts.history === 'push') {
                if (!sameRec(urlRec(), rec)) {
                    pushUrl(rec);
                }
            } else if (opts.history === 'replace') {
                replaceUrl(rec);
            }
            load(rec);
        };
        if (shown && inlineBusy() && !sameRec(current, rec)) {
            return; // an inline save is in flight
        }
        if (shown && isDirty() && !sameRec(current, rec)) {
            askDiscard(go);
            return;
        }
        go();
    }

    // force: skip the dirty guard (the data is gone or the action confirmed).
    function requestClose(force) {
        if (!shown) {
            return;
        }
        // Bootstrap's hide() is a no-op while the show transition runs: keep
        // the request and act on it once the dialog is shown (setting
        // `closing` now would leave it stuck and disable the dirty guard).
        if ($(MODAL).attr('data-shown') !== '1') {
            pendingClose = { force: !!force || (pendingClose && pendingClose.force) };
            return;
        }
        if (!force && inlineBusy()) {
            return; // an inline save is in flight
        }
        if (!force && isDirty()) {
            askDiscard(function () { requestClose(true); });
            return;
        }
        closing = true;
        $(MODAL).removeAttr('data-shown');
        modal().hide();
    }

    function askDiscard(then) {
        discardThen = then;
        $('#asset-record-foot').prop('hidden', true);
        $('#asset-record-discard').prop('hidden', false);
        // The live region announces the question (the bar is a group, not a dialog).
        $('#asset-record-discard-live').text(L('AssetRecordDiscardQuestion'));
        $('#asset-record-keep-editing').trigger('focus');
    }

    function switchToEdit() {
        if (!current || current.mode !== 'view' || !current.canEdit || inlineBusy()) {
            return;
        }
        if (isDirty()) {
            // An open inline field has changes: ask before Edit replaces it.
            askDiscard(function () {
                cancelOpenInline();
                switchToEdit();
            });
            return;
        }
        var rec = { id: current.id, mode: 'edit' };
        pushUrl(rec, { fromView: true });
        generation += 1;
        var payload = viewCache && viewCache.id === current.id && !viewCache.stale ? viewCache.payload : null;
        if (payload) {
            renderEdit(current.id, payload);
            current.fromView = true;
            focusFirstField();
        } else {
            load(rec);
        }
    }

    // Focus goes to the dialog's title, not into the first field: the
    // engine's dirty baseline follows the settling form until the first
    // focus/keydown inside it (risk-details-form.js), and a programmatic
    // focus there would freeze it before the late widgets (Mapped controls
    // roster, HugeRTE) have finished filling in. The title is the first stop
    // of the header -> body -> footer tab order.
    function focusFirstField() {
        $('#asset-record-title').trigger('focus');
    }

    // Back to View of the same asset (Cancel of an Edit entered from View, or
    // after any save).
    function backToView(fresh) {
        var st = history.state && history.state.assetRecord;
        if (fresh) {
            viewCache = null;
        }
        if (st && st.fromView && st.mode === 'edit' && String(st.id) === String(current.id)) {
            // The entry below is this asset's View: go back to it.
            pendingFromCache = !fresh;
            navigating = true;
            history.back();
            return;
        }
        var rec = { id: current.id, mode: 'view', fromCache: !fresh };
        replaceUrl(rec);
        load(rec);
    }
    var pendingFromCache = false;
    // Set when this module itself navigates history (Cancel/Save back to
    // View): the resulting popstate skips the dirty guard.
    var navigating = false;

    // Whether this Edit was entered from this asset's View (the header's Edit
    // button), so Cancel belongs back there. The history entry remembers it
    // across a reload or a Forward into the Edit entry. An Edit opened
    // directly (a row's ⋯ menu, a &mode=edit deep link) has no View below it.
    function editCameFromView() {
        if (!current || current.mode !== 'edit') {
            return false;
        }
        if (current.fromView) {
            return true;
        }
        var st = history.state && history.state.assetRecord;
        return !!(st && st.fromView && st.mode === 'edit' && String(st.id) === String(current.id));
    }

    // Cancel returns to where the user came from: View when Edit was entered
    // from View, otherwise the list (the record closes, as the × would).
    function cancel() {
        if (!current) {
            return;
        }
        var go = function () {
            if (editCameFromView()) {
                backToView(false);
            } else {
                requestClose(true);
            }
        };
        if (isDirty()) {
            askDiscard(go);
        } else {
            go();
        }
    }

    function save() {
        if (saving || !current || (current.mode !== 'edit' && current.mode !== 'add')) {
            return;
        }
        saving = true;
        var $btn = $('#asset-record-save').prop('disabled', true).addClass('is-busy').attr('aria-busy', 'true');
        var mode = current.mode;
        var gen = generation;
        RiskDetailsForm.submit(FORM).then(function (res) {
            saving = false;
            $btn.prop('disabled', false).removeClass('is-busy').removeAttr('aria-busy');
            if (!res || !res.ok) {
                return; // the engine already showed the validation/server error
            }
            var json = res.data || {};
            // The write happened: the list refreshes and the user is told,
            // even if the record was closed or replaced meanwhile.
            if (json.status_message) {
                showAlertFromMessage(json.status_message, true);
            }
            if (host.changed) {
                host.changed(mode === 'add' ? 'created' : 'saved');
            }
            if (gen !== generation) {
                return;
            }
            if (mode === 'add') {
                var newId = json.data && json.data.id;
                if (newId) {
                    var rec = { id: String(newId), mode: 'view' };
                    replaceUrl(rec);
                    load(rec);
                } else {
                    requestClose(true);
                }
            } else {
                backToView(true);
            }
        });
    }

    /* ---------------- ⋯ actions ---------------- */

    function openMenu(last) {
        var $menu = $('#asset-record-more-menu');
        $menu.prop('hidden', false);
        $('#asset-record-more-toggle').attr('aria-expanded', 'true');
        var $items = $menu.find('[role="menuitem"]').filter(function () { return !this.hidden; });
        $items.eq(last ? $items.length - 1 : 0).trigger('focus');
    }

    // Close the record, then run `fn` (a follow-up dialog never stacks on it).
    function closeThen(fn) {
        afterHidden = fn;
        requestClose(true);
    }

    function setVerified(on) {
        if (!current || current.mode !== 'view') {
            return;
        }
        var id = current.id;
        $.ajax({
            type: 'PATCH', url: BASE_URL + '/api/v2/assets/' + encodeURIComponent(id),
            data: { verified: on ? '1' : '0' }, dataType: 'json',
            success: function (json) {
                showAlertFromMessage((json && json.status_message) || esc(L('SavedSuccess')), true);
                viewCache = null;
                if (host.changed) {
                    host.changed('verified');
                }
                requestClose(true);
            },
            error: function (xhr) {
                if (window.ManageAssets && ManageAssets.util && ManageAssets.util.reissuedByCsrfRetry(xhr, this)) {
                    return;
                }
                if (xhr && xhr.status === 404) {
                    notAvailable(xhr);
                    return;
                }
                var msg = xhr && xhr.responseJSON && xhr.responseJSON.status_message;
                showAlertFromMessage(msg || esc(L('RequestFailed')), false);
            }
        });
    }

    /* ---------------- audit trail pane ---------------- */

    var auditSeq = 0;
    function showAudit() {
        if (!current || current.mode !== 'view') {
            return;
        }
        current.mode = 'audit';
        renderChrome();
        loadAudit();
        $('#asset-record-audit-window').trigger('focus');
    }

    function loadAudit() {
        var seq = ++auditSeq;
        var id = current.id;
        var days = $('#asset-record-audit-window').val() || '365';
        var $body = $('#asset-record-audit-rows').empty();
        var $state = $('#asset-record-audit-state').prop('hidden', false).text(L('Loading'));
        $('#asset-record-audit-table').prop('hidden', true);
        api('/assets/' + encodeURIComponent(id) + '/audit-trail', { days: days }).done(function (json) {
            if (seq !== auditSeq || !current || current.mode !== 'audit') {
                return;
            }
            var entries = ((json && json.data) || {}).entries || [];
            if (!entries.length) {
                $state.text(L('AssetRecordAuditTrailEmpty'));
                return;
            }
            $state.prop('hidden', true).text('');
            entries.forEach(function (e) {
                $('<tr>').append(
                    $('<td>', { 'class': 'sr-num sr-arm-audit-when', text: String(e.timestamp_display || e.timestamp || '') }),
                    $('<td>', { 'class': 'sr-arm-audit-who' }).append(e.user_name ? document.createTextNode(String(e.user_name)) : $('<span>', { 'class': 'sr-cell-dash', text: '—' })),
                    $('<td>', { 'class': 'sr-arm-audit-what', text: String(e.message || '') })
                ).appendTo($body);
            });
            $('#asset-record-audit-table').prop('hidden', false);
        }).fail(function (xhr) {
            if (seq !== auditSeq) {
                return;
            }
            if (xhr && xhr.status === 404) {
                notAvailable(xhr);
                return;
            }
            $state.text(L('AssetRecordAuditTrailFailed'));
        });
    }

    function backFromAudit() {
        if (!current || current.mode !== 'audit') {
            return;
        }
        current.mode = 'view';
        renderChrome();
        $('#asset-record-back').prop('hidden', true);
        $('#asset-record-close').trigger('focus');
    }

    /* ---------------- wiring ---------------- */

    function bind() {
        if (bound) {
            return;
        }
        bound = true;
        var $m = $(MODAL);
        var escForWidget = false; // this Esc closes a dropdown in an inline field
        bindInlineEdit();

        // Esc, the backdrop and the × all try to hide. While the form is
        // dirty, Esc and the backdrop do nothing and the × asks first.
        $m.on('hide.bs.modal', function (e) {
            if (e.target !== this || closing) {
                return;
            }
            if (isDirty() || escForWidget || inlineBusy()) {
                e.preventDefault();
                return;
            }
            closing = true;
            $m.removeAttr('data-shown');
        });
        $m.on('hidden.bs.modal', function (e) {
            if (e.target !== this) {
                return;
            }
            shown = false;
            closing = false;
            pendingClose = null;
            endInlineSave();
            generation += 1;
            closeMenu(false);
            teardownEngines();
            // Nothing of the closed record stays in the DOM.
            $('#asset-record-title, #asset-record-hint').text('');
            $('#asset-record-meta').empty();
            $('#asset-record-audit-rows').empty();
            $('#asset-record-verification').prop('hidden', true);
            current = null;
            clearUrl();
            var next = afterHidden;
            afterHidden = null;
            if (host.restoreFocus) {
                host.restoreFocus();
            }
            if (next) {
                next();
            }
        });
        $m.on('shown.bs.modal', function (e) {
            if (e.target !== this) {
                return;
            }
            // Bootstrap ignores Esc/× while its show transition runs; this
            // marks the moment the dialog accepts them (also used by tests).
            $m.attr('data-shown', '1');
            closing = false;
            if (pendingClose) {
                var pending = pendingClose;
                pendingClose = null;
                requestClose(pending.force);
                return;
            }
            if (current && (current.mode === 'edit' || current.mode === 'add')) {
                focusFirstField();
            } else {
                var $edit = $('#asset-record-edit');
                ($edit.is(':visible') ? $edit : $('#asset-record-title')).trigger('focus');
            }
        });

        $('#asset-record-close-x').on('click', function () { requestClose(false); });
        $('#asset-record-close, #asset-record-close-audit').on('click', function () { requestClose(false); });
        $('#asset-record-cancel').on('click', cancel);
        $('#asset-record-save').on('click', save);
        $('#asset-record-edit').on('click', switchToEdit);
        $('#asset-record-back').on('click', backFromAudit);
        $('#asset-record-keep-editing').on('click', function () {
            hideDiscardConfirm();
            var form = openInlineForms()[0];
            if (current && (current.mode === 'view' || current.mode === 'audit') && form) {
                focusInlineControl(form);
            } else {
                $('#asset-record-save').trigger('focus');
            }
        });
        $('#asset-record-discard-confirm').on('click', function () {
            var then = discardThen;
            hideDiscardConfirm();
            if (window.RiskDetailsForm) {
                RiskDetailsForm.markClean(FORM);
            }
            if (then) {
                then();
            }
        });
        $('#asset-record-audit-window').on('change', loadAudit);

        $('#asset-record-copy').on('click', function () {
            if (!current || !current.id || current.id === 'new') {
                return;
            }
            var link = window.location.origin + window.location.pathname + '?asset=' + encodeURIComponent(current.id);
            var ok = function () { showAlertFromMessage(esc(L('AssetRecordLinkCopied')), true); };
            var bad = function () { showAlertFromMessage(esc(L('AssetRecordLinkCopyFailed')), false); };
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(link).then(ok, bad);
            } else {
                bad();
            }
        });

        // Template switch (Add, several asset template groups): a radio group.
        $('#asset-record-templates').on('click', '.sr-arm-seg', function () {
            var id = $(this).attr('data-template-group-id');
            if (!current || current.mode !== 'add' || String(id) === String(current.templateGroupId)) {
                return;
            }
            var go = function () { generation += 1; renderAdd(id); };
            if (isDirty()) { askDiscard(go); } else { go(); }
        }).on('keydown', '.sr-arm-seg', function (e) {
            var $all = $('#asset-record-templates .sr-arm-seg');
            var i = $all.index(this), next = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { next = (i + 1) % $all.length; }
            else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { next = (i - 1 + $all.length) % $all.length; }
            if (next === null) { return; }
            e.preventDefault();
            $all.eq(next).trigger('focus').trigger('click');
        });

        // ⋯ menu (the Manage assets tab-row menu's keyboard model).
        var $toggle = $('#asset-record-more-toggle');
        var items = function () { return $('#asset-record-more-menu [role="menuitem"]').filter(function () { return !this.hidden; }); };
        $toggle.on('click', function (e) {
            e.stopPropagation();
            if ($toggle.attr('aria-expanded') === 'true') { closeMenu(true); } else { openMenu(false); }
        }).on('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                openMenu(e.key === 'ArrowUp');
            }
        });
        $('#asset-record-more-menu').on('keydown', '[role="menuitem"]', function (e) {
            var $all = items(), i = $all.index(this), next = null;
            if (e.key === 'ArrowDown') { next = (i + 1) % $all.length; }
            else if (e.key === 'ArrowUp') { next = (i - 1 + $all.length) % $all.length; }
            else if (e.key === 'Home') { next = 0; }
            else if (e.key === 'End') { next = $all.length - 1; }
            else if (e.key === 'Escape') {
                // Close the menu, not the modal.
                e.preventDefault();
                e.stopPropagation();
                closeMenu(true);
                return;
            } else if (e.key === 'Tab') { closeMenu(false); return; }
            if (next === null) { return; }
            e.preventDefault();
            $all.eq(next).trigger('focus');
        });
        $m.on('click', function (e) {
            if (!$(e.target).closest('#asset-record-more').length) { closeMenu(false); }
        });
        // Esc while the ⋯ menu is open closes the menu, not the dialog:
        // capture phase on the dialog runs before Bootstrap's own handler.
        $m[0].addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') {
                return;
            }
            // An open guidance popover (Asset Scoring's help icons), opened
            // by focus or by hover with focus anywhere in the record, closes
            // first and alone: never the record, the field or the menu.
            var scoring = window.AssetCardProfile && window.AssetCardProfile.scoring;
            if (scoring && typeof scoring.hideHelp === 'function' && scoring.hideHelp($m[0])) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            if ($('#asset-record-more-toggle').attr('aria-expanded') === 'true') {
                e.preventDefault();
                e.stopPropagation();
                closeMenu(true);
                return;
            }
            if (current && current.mode === 'view' && inlineBusy()) {
                e.preventDefault();
                e.stopPropagation();
                return; // the save in flight is not cancellable
            }
            // Esc with an inline field open cancels that field, not the
            // record. An open dropdown inside it takes the Esc first.
            var form = (current && current.mode === 'view') ? openInlineForms()[0] : null;
            if (!form) {
                return;
            }
            if (inlineWidgetDropdownOpen(form)) {
                escForWidget = true;
                setTimeout(function () { escForWidget = false; }, 0);
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            if (!$('#asset-record-discard').prop('hidden')) {
                $('#asset-record-keep-editing').trigger('click');
                return;
            }
            escapeInline(form);
        }, true);
        // Each of these ends the View; an open inline field with changes asks first.
        $('#asset-record-add-to-group').on('click', function () {
            var id = Number(current.id), name = String(((current.values || {}).name || {}).raw || '');
            closeMenu(false);
            guardInline(function () { closeThen(function () { if (host.addToGroup) { host.addToGroup(id, name); } }); });
        });
        $('#asset-record-delete').on('click', function () {
            var id = Number(current.id), name = String(((current.values || {}).name || {}).raw || '');
            closeMenu(false);
            guardInline(function () { closeThen(function () { if (host.deleteAsset) { host.deleteAsset(id, name); } }); });
        });
        $('#asset-record-verify').on('click', function () { closeMenu(false); guardInline(function () { setVerified(true); }); });
        $('#asset-record-unverify').on('click', function () { closeMenu(false); guardInline(function () { setVerified(false); }); });
        // The audit pane replaces the body: an open inline field is closed
        // first (asking when it has changes), so the pane never hides one.
        $('#asset-record-audit-open').on('click', function () {
            closeMenu(false);
            guardInline(function () {
                cancelOpenInline();
                showAudit();
            });
        });


        // Back / Forward.
        $(window).on('popstate.assetrecord', function () {
            var rec = urlRec();
            if (rec && pendingFromCache) {
                rec.fromCache = true;
            }
            pendingFromCache = false;
            var skipGuard = navigating;
            navigating = false;
            if (shown && !skipGuard && inlineBusy() && current && !(rec && sameRec(current, rec))) {
                // An inline save is in flight: stay (the URL goes back to
                // this record) until it settles.
                pushUrl({ id: current.id, mode: current.mode === 'audit' ? 'view' : current.mode },
                    current.fromView ? { fromView: true } : null);
                return;
            }
            var dirtyStay = shown && !skipGuard && isDirty() && !(rec && current && sameRec(current, rec));
            if (!dirtyStay && !(rec && current && sameRec(current, rec))) {
                // Whatever was loading is no longer what the URL names: drop it
                // (a Back before /values answered must not open that record).
                generation += 1;
                setBusy(false);
            }
            if (!rec) {
                if (shown) {
                    if (!skipGuard && isDirty()) {
                        // Stay: put the record back in the URL and ask.
                        pushUrl({ id: current.id, mode: current.mode === 'audit' ? 'view' : current.mode },
                            current.fromView ? { fromView: true } : null);
                        askDiscard(function () { requestClose(true); });
                    } else {
                        requestClose(true);
                    }
                }
                return;
            }
            if (shown && current && sameRec(current, rec)) {
                return;
            }
            if (shown && !skipGuard && isDirty()) {
                var back = { id: current.id, mode: current.mode === 'audit' ? 'view' : current.mode };
                pushUrl(back, current.fromView ? { fromView: true } : null);
                askDiscard(function () { replaceUrl(rec); load(rec); });
                return;
            }
            load(rec);
        });
    }

    var AssetRecordModal = {
        init: function (hostHooks) {
            host = hostHooks || {};
            if (!$(MODAL).length) {
                return;
            }
            bind();
        },
        open: open,
        close: function () { requestClose(false); },
        isOpen: function () { return shown; },
        isDirty: isDirty,
        // Opens whatever ?asset=&mode= names (after the list's first draw).
        openFromUrl: function () {
            var rec = urlRec();
            if (!rec) {
                return;
            }
            // Normalise the URL to what was understood (e.g. mode=bogus -> view).
            replaceUrl(rec);
            load(rec);
        },
        // Pure helpers (unit-testable without a DOM).
        url: { parse: parseRecordParams, serialize: serializeRecordParams }
    };

    window.AssetRecordModal = AssetRecordModal;
})(window, jQuery);
