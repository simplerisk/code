/**
 * Add and remove values (admin/add_remove_values.php, SR-2475).
 *
 * Fills the static shell from the admin-only v2 API:
 *  - GET    /api/v2/admin/lookup-values/lists?include_values=1   the lists, their groups and value names
 *  - GET    /api/v2/admin/lookup-values/lists/{list}/values       a list's values with usage counts
 *  - POST   /api/v2/admin/lookup-values/lists/{list}/values       add
 *  - PATCH  /api/v2/admin/lookup-values/lists/{list}/values/{id}  rename
 *  - DELETE /api/v2/admin/lookup-values/lists/{list}/values/{id}  delete, optional reassign_to
 *
 * ESCAPING: every string from the API (list and value names, labels, reasons) reaches
 * the DOM through .text() or an attribute setter only, so the browser escapes it exactly
 * once; nothing is concatenated into an HTML string. A toast is built only from lang text
 * and integers, escaped once by escapeHtml() right before showAlertFromMessage().
 */
(function (window, $) {
    'use strict';

    var API = '/api/v2/admin/lookup-values/lists';

    /* ------------------------------------------------------------------ *
     * Pure helpers (no DOM), exposed on window.SRLookupValues            *
     * ------------------------------------------------------------------ */

    function lang(key) {
        return (typeof window.L === 'function') ? window.L(key) : key;
    }

    /** Substitutes {$name} placeholders (the lang.en.php template style). */
    function format(template, vars) {
        return String(template).replace(/\{\$(\w+)\}/g, function (match, name) {
            return Object.prototype.hasOwnProperty.call(vars || {}, name) ? String(vars[name]) : match;
        });
    }

    function t(key, vars) {
        return format(lang(key), vars);
    }

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
        });
    }

    function normalize(text) {
        return String(text === null || text === undefined ? '' : text).toLowerCase().trim();
    }

    /**
     * The lists that match a query by their own name or by a value inside them.
     * An empty query matches every list. Each hit carries the matching value
     * names (empty when only the list's name matched).
     */
    function filterLists(lists, query) {
        var q = normalize(query);
        var hits = [];
        (lists || []).forEach(function (list) {
            if (q === '') {
                hits.push({ list: list, matches: [] });
                return;
            }
            var nameMatch = normalize(list.name).indexOf(q) !== -1;
            var matches = (list.values || []).filter(function (v) {
                return normalize(v.name).indexOf(q) !== -1;
            }).map(function (v) { return v.name; });
            if (nameMatch || matches.length) {
                hits.push({ list: list, matches: matches });
            }
        });
        return hits;
    }

    /** The values whose name contains the filter text, in their given order. */
    function filterValues(values, text) {
        var q = normalize(text);
        return (values || []).filter(function (v) {
            return q === '' || normalize(v.name).indexOf(q) !== -1;
        });
    }

    /** "3 risks" style summary: [{label, count}] -> "Risks: 3, Assets: 1". */
    function usageText(usage) {
        if (!usage || !usage.total) {
            return lang('LookupNotInUse');
        }
        return usage.by_entity.map(function (e) { return e.label + ': ' + e.count; }).join(', ');
    }

    window.SRLookupValues = {
        filterLists: filterLists,
        filterValues: filterValues,
        usageText: usageText,
        format: format
    };

    /* ------------------------------------------------------------------ *
     * State                                                              *
     * ------------------------------------------------------------------ */

    var state = {
        groups: [],
        lists: [],
        byKey: {},
        selected: null,
        values: [],
        filter: '',
        query: '',
        busy: false,
        editingId: null,
        loadSeq: 0,
        deleting: null
    };

    var $root, $lists, $select, $tbody, $empty, $newInput, $newError, $filter, $search;

    /* ------------------------------------------------------------------ *
     * API                                                                *
     * ------------------------------------------------------------------ */

    // A JSON body skips csrf-magic's automatic token injection, so the token goes in the header.
    function csrfHeaders() {
        return { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' };
    }

    function request(method, path, body) {
        var settings = {
            type: method,
            url: BASE_URL + API + path,
            dataType: 'json',
            headers: csrfHeaders(),
            timeout: 30000
        };
        if (body !== undefined) {
            settings.contentType = 'application/json';
            settings.data = JSON.stringify(body);
        }
        return $.ajax(settings);
    }

    function errorMessage(xhr) {
        try {
            var parsed = xhr.responseJSON || JSON.parse(xhr.responseText);
            if (parsed && parsed.status_message) {
                return String(parsed.status_message);
            }
        } catch { return lang('RequestFailed'); }
        return lang('RequestFailed');
    }

    function toast(message, success) {
        showAlertFromMessage(escapeHtml(message), success);
    }

    function announce(message) {
        $('#lv-announcer').text(message);
    }

    /* ------------------------------------------------------------------ *
     * Loading                                                            *
     * ------------------------------------------------------------------ */

    function loadLists() {
        return request('GET', '?include_values=1').then(function (response) {
            var data = response.data || {};
            state.groups = data.groups || [];
            state.lists = data.lists || [];
            state.byKey = {};
            state.lists.forEach(function (list) { state.byKey[list.list] = list; });
        });
    }

    function loadValues() {
        var seq = ++state.loadSeq;
        var key = state.selected;
        return request('GET', '/' + encodeURIComponent(key) + '/values').then(function (response) {
            if (seq !== state.loadSeq) {
                return; // a newer selection superseded this response
            }
            state.values = (response.data && response.data.values) || [];
            renderTable();
        }, function () {
            if (seq !== state.loadSeq) {
                return;
            }
            state.values = [];
            renderLoadError();
        });
    }

    function refresh() {
        return loadLists().then(function () {
            renderRail();
            return loadValues();
        });
    }

    /* ------------------------------------------------------------------ *
     * Rail                                                               *
     * ------------------------------------------------------------------ */

    function renderRail() {
        var hits = filterLists(state.lists, state.query);
        var byGroup = {};
        hits.forEach(function (hit) {
            (byGroup[hit.list.group] = byGroup[hit.list.group] || []).push(hit);
        });

        $lists.empty();
        state.groups.forEach(function (group) {
            var inGroup = byGroup[group.group];
            if (!inGroup) {
                return;
            }
            $lists.append($('<div>', { 'class': 'sr-lv-group', text: group.name }));
            inGroup.forEach(function (hit) {
                var list = hit.list;
                var $button = $('<button>', {
                    type: 'button',
                    'class': 'sr-lv-list' + (list.list === state.selected ? ' is-active' : ''),
                    'data-list': list.list,
                    'aria-current': list.list === state.selected ? 'true' : 'false'
                });
                var $label = $('<span>', { 'class': 'sr-lv-list-label' }).append($('<span>', { text: list.name }));
                if (hit.matches.length) {
                    $label.append($('<span>', {
                        'class': 'sr-lv-list-sub',
                        text: t('LookupMatches', { names: hit.matches.slice(0, 2).join(', ') })
                    }));
                } else if (list.used_by.length > 1) {
                    $label.append($('<span>', { 'class': 'sr-lv-list-sub', text: list.used_by.join(', ') }));
                }
                $button.append($label, $('<span>', { 'class': 'sr-lv-list-count', text: list.count }));
                $lists.append($button);
            });
        });

        $('#lv-rail-empty').toggleClass('d-none', hits.length > 0);

        // The narrow-width select always lists every list.
        $select.empty();
        state.groups.forEach(function (group) {
            var $group = $('<optgroup>', { label: group.name });
            state.lists.filter(function (l) { return l.group === group.group; }).forEach(function (list) {
                $group.append($('<option>', { value: list.list, text: list.name + ' (' + list.count + ')' }));
            });
            $select.append($group);
        });
        $select.val(state.selected);
    }

    /* ------------------------------------------------------------------ *
     * Table                                                              *
     * ------------------------------------------------------------------ */

    function currentList() {
        return state.byKey[state.selected];
    }

    function countText(n) {
        return n === 1 ? lang('LookupOneValue') : t('LookupNValues', { n: n });
    }

    function renderHeader() {
        var list = currentList();
        $('#lv-title').text(list.name);
        $('#lv-count').text(countText(state.values.length));
        $('#lv-subtitle').text(t('LookupUsedBy', { entities: list.used_by.join(', ') }));
        $newInput.attr('maxlength', list.length_limit);
        updateNewCounter();
    }

    function updateNewCounter() {
        var list = currentList();
        $('#lv-new-counter').text(list ? t('LookupNOfLimit', { n: $newInput.val().length, limit: list.length_limit }) : '');
    }

    function renderTable() {
        var list = currentList();
        if (!list) {
            return;
        }
        state.editingId = null;
        renderHeader();

        var shown = filterValues(state.values, state.filter);
        $tbody.empty();
        shown.forEach(function (value) { $tbody.append(renderRow(value)); });

        $('#lv-table').closest('.sr-table-scroll').toggleClass('d-none', shown.length === 0);
        $('#lv-foot-count').text(state.filter && shown.length !== state.values.length
            ? t('LookupShowingNOfM', { n: shown.length, m: state.values.length })
            : countText(state.values.length));

        if (shown.length === 0) {
            showEmpty(state.values.length === 0 ? 'none' : 'filtered');
        } else {
            $empty.addClass('d-none');
        }
    }

    function showEmpty(kind) {
        $('#lv-empty-icon').attr('class', 'fa fa-list');
        $empty.removeClass('d-none');
        var $action = $('#lv-empty-action').off('click');
        if (kind === 'none') {
            $('#lv-empty-title').text(lang('LookupNoValuesYet'));
            $('#lv-empty-body').text(lang('LookupNoValuesYetHint'));
            $('#lv-empty-action-wrap').removeClass('d-none');
            $action.text(lang('LookupAddValue')).on('click', function () { $newInput.trigger('focus'); });
        } else {
            $('#lv-empty-title').text(lang('LookupNoValuesMatch'));
            $('#lv-empty-body').text(t('LookupNoValuesMatchHint', { filter: state.filter }));
            $('#lv-empty-action-wrap').removeClass('d-none');
            $action.text(lang('ClearFilters')).on('click', function () {
                state.filter = '';
                $filter.val('');
                renderTable();
                $filter.trigger('focus');
            });
        }
    }

    function renderLoadError() {
        var list = currentList();
        if (list) {
            $('#lv-title').text(list.name);
        }
        $tbody.empty();
        $('#lv-table').closest('.sr-table-scroll').addClass('d-none');
        $('#lv-empty-icon').attr('class', 'fa fa-triangle-exclamation');
        $('#lv-empty-title').text(lang('LookupCouldNotLoad'));
        $('#lv-empty-body').text(lang('LookupCouldNotLoadHint'));
        $('#lv-empty-action-wrap').removeClass('d-none');
        $('#lv-empty-action').text(lang('Retry')).off('click').on('click', function () { loadValues(); });
        $empty.removeClass('d-none');
    }

    function renderRow(value) {
        var $tr = $('<tr>', { 'data-id': value.id });
        var $name = $('<td>', { 'class': 'sr-lv-name-cell' });
        $name.append($('<span>', { 'class': 'sr-lv-name', text: value.name }));
        if (value.protected && value.protected_reason) {
            $name.append($('<span>', { 'class': 'sr-lv-protected' })
                .append($('<i>', { 'class': 'fa fa-lock', 'aria-hidden': 'true' }))
                .append(document.createTextNode(' ' + value.protected_reason)));
        }

        var $use = $('<td>', { 'class': 'sr-lv-use-col' }).append($('<span>', {
            'class': value.usage.total ? 'sr-lv-use' : 'sr-lv-use sr-lv-use--none',
            text: usageText(value.usage)
        }));

        var $actions = $('<td>', { 'class': 'sr-actions-col' });
        var $cluster = $('<div>', { 'class': 'sr-row-actions' });
        if (value.can_rename) {
            $cluster.append($('<button>', {
                type: 'button',
                'class': 'sr-row-action sr-lv-rename',
                'aria-label': t('LookupRenameX', { name: value.name }),
                title: lang('LookupRename')
            }).append($('<i>', { 'class': 'fa fa-pen', 'aria-hidden': 'true' })).data('value', value));
        }
        if (value.can_delete) {
            $cluster.append($('<button>', {
                type: 'button',
                'class': 'sr-row-action sr-row-action-danger sr-lv-delete',
                'aria-label': t('LookupDeleteX', { name: value.name }),
                title: lang('Delete')
            }).append($('<i>', { 'class': 'fa fa-trash', 'aria-hidden': 'true' })).data('value', value));
        }
        $actions.append($cluster);

        return $tr.append($name, $use, $actions);
    }

    /* ------------------------------------------------------------------ *
     * Rename (inline: Enter saves, Esc cancels)                          *
     * ------------------------------------------------------------------ */

    function startRename($tr, value) {
        if (state.busy) {
            return;
        }
        cancelRename();
        state.editingId = value.id;
        var list = currentList();
        var limit = list.length_limit;

        var $input = $('<input>', {
            type: 'text',
            'class': 'form-control sr-lv-rename-input',
            maxlength: limit,
            'aria-label': lang('LookupValueName'),
            value: value.name
        // 'autocomplete' cannot sit in the creation map: jQuery would call the jQuery UI
        // autocomplete plugin with it instead of setting the attribute.
        }).attr('autocomplete', 'off');
        var $hint = $('<div>', { 'class': 'sr-lv-hint' });
        var $error = $('<div>', { 'class': 'sr-lv-error d-none', role: 'alert' });

        function updateHint() {
            var text = t('LookupNOfLimit', { n: $input.val().length, limit: limit });
            if (state.selected === 'status' && value.usage.total) {
                text += ' ' + lang('LookupRenameUpdatesStatus');
            }
            $hint.text(text);
        }
        updateHint();

        var $cancel = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-outline-secondary', text: lang('Cancel') });
        var $save = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-dark', text: lang('Save') });

        $tr.addClass('is-editing');
        $tr.children('.sr-lv-name-cell').empty().append($input, $hint, $error);
        $tr.children('.sr-actions-col').empty().append($('<div>', { 'class': 'sr-lv-edit-actions' }).append($cancel, $save));

        function commit() {
            var name = $.trim($input.val());
            if (name === '') {
                $error.text(lang('YouNeedToSpecifyANameParameter')).removeClass('d-none');
                $input.trigger('focus');
                return;
            }
            if (name === value.name) {
                cancelRename();
                return;
            }
            state.busy = true;
            $save.prop('disabled', true);
            request('PATCH', '/' + encodeURIComponent(state.selected) + '/values/' + value.id, { name: name })
                .then(function () {
                    state.busy = false;
                    toast(lang('AnItemWasUpdatedSuccessfully'), true);
                    announce(lang('AnItemWasUpdatedSuccessfully'));
                    return refresh();
                }, function (xhr) {
                    state.busy = false;
                    $save.prop('disabled', false);
                    $error.text(errorMessage(xhr)).removeClass('d-none');
                    $input.trigger('focus');
                });
        }

        $input.on('input', function () { updateHint(); $error.addClass('d-none'); });
        $input.on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                commit();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                cancelRename(true);
            }
        });
        $cancel.on('click', function () { cancelRename(true); });
        $save.on('click', commit);
        $input.trigger('focus').trigger('select');
    }

    function cancelRename(restoreFocus) {
        if (state.editingId === null) {
            return;
        }
        var id = state.editingId;
        renderTable();
        if (restoreFocus) {
            $tbody.find('tr[data-id="' + id + '"] .sr-lv-rename').trigger('focus');
        }
    }

    /* ------------------------------------------------------------------ *
     * Add                                                                *
     * ------------------------------------------------------------------ */

    function addValue() {
        if (state.busy || !state.selected) {
            return;
        }
        var name = $.trim($newInput.val());
        if (name === '') {
            showAddError(lang('YouNeedToSpecifyANameParameter'));
            return;
        }
        state.busy = true;
        $newError.addClass('d-none');
        request('POST', '/' + encodeURIComponent(state.selected) + '/values', { name: name })
            .then(function () {
                state.busy = false;
                $newInput.val('');
                updateNewCounter();
                toast(lang('ANewItemWasAddedSuccessfully'), true);
                announce(lang('ANewItemWasAddedSuccessfully'));
                state.filter = '';
                $filter.val('');
                return refresh();
            }, function (xhr) {
                state.busy = false;
                showAddError(errorMessage(xhr));
            })
            .always(function () { $newInput.trigger('focus'); });
    }

    function showAddError(message) {
        $newError.text(message).removeClass('d-none');
        $newInput.trigger('focus');
    }

    /* ------------------------------------------------------------------ *
     * Delete                                                             *
     * ------------------------------------------------------------------ */

    function openDelete(value) {
        var total = value.usage.total;
        state.deleting = value;

        $('#lv-delete-title').text(t('LookupDeleteTitle', { name: value.name }));
        $('#lv-delete-error').addClass('d-none').text('');
        $('#lv-delete-plain').toggleClass('d-none', total > 0).text(lang('LookupDeletePlain'));
        $('#lv-delete-note').toggleClass('d-none', total === 0);
        $('#lv-delete-move').toggleClass('d-none', total === 0);

        if (total > 0) {
            $('#lv-delete-note-text').text(total === 1
                ? lang('LookupInUseWarningOne')
                : t('LookupInUseWarningMany', { n: total }));
            $('#lv-delete-target-label').text(total === 1
                ? lang('LookupMoveRecordOneTo')
                : t('LookupMoveRecordsTo', { n: total }));
            $('#lv-delete-target-hint').text(lang('LookupMoveHint'));
            var $target = $('#lv-delete-target').empty()
                .append($('<option>', { value: '', text: lang('LookupLeaveEmpty') }));
            state.values.filter(function (v) { return v.id !== value.id; }).forEach(function (v) {
                $target.append($('<option>', { value: v.id, text: v.name }));
            });
        }

        $('#lv-delete-commit').prop('disabled', false).text(t('LookupDeleteValue'));
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('lv-delete-modal')).show();
    }

    function commitDelete() {
        var value = state.deleting;
        if (!value || state.busy) {
            return;
        }
        var body = {};
        var target = $('#lv-delete-target').val();
        if (value.usage.total > 0 && target) {
            body.reassign_to = parseInt(target, 10);
        }
        state.busy = true;
        var $commit = $('#lv-delete-commit').prop('disabled', true);
        $commit.prepend($('<span>', { 'class': 'spinner-border spinner-border-sm me-1', 'aria-hidden': 'true' }));

        request('DELETE', '/' + encodeURIComponent(state.selected) + '/values/' + value.id, body)
            .then(function (response) {
                state.busy = false;
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('lv-delete-modal')).hide();
                toast(lang('AnItemWasDeletedSuccessfully'), true);
                var moved = response && response.data ? parseInt(response.data.reassigned, 10) : 0;
                if (moved > 0) {
                    toast(moved === 1 ? lang('LookupRecordMovedOne') : t('LookupRecordsMoved', { n: moved }), true);
                }
                announce(lang('AnItemWasDeletedSuccessfully'));
                return refresh();
            }, function (xhr) {
                state.busy = false;
                $commit.prop('disabled', false).text(lang('LookupDeleteValue'));
                $('#lv-delete-error').text(errorMessage(xhr)).removeClass('d-none');
            });
    }

    /* ------------------------------------------------------------------ *
     * Selection                                                          *
     * ------------------------------------------------------------------ */

    function selectList(key, fromQuery) {
        if (!state.byKey[key]) {
            return;
        }
        state.selected = key;
        state.editingId = null;
        // A list picked out of a value search opens already filtered to that search.
        state.filter = fromQuery ? state.query : '';
        $filter.val(state.filter);
        try {
            window.history.replaceState(null, '', '?list=' + encodeURIComponent(key));
        } catch { /* history is a convenience */ }
        renderRail();
        $('#lv-card').attr('data-list', key);
        loadValues();
    }

    /* ------------------------------------------------------------------ *
     * Wiring                                                             *
     * ------------------------------------------------------------------ */

    $(function () {
        $root = $('#lookup-values');
        if (!$root.length) {
            return;
        }
        $lists = $('#lv-lists');
        $select = $('#lv-list-select');
        $tbody = $('#lv-tbody');
        $empty = $('#lv-empty');
        $newInput = $('#lv-new');
        $newError = $('#lv-new-error');
        $filter = $('#lv-filter');
        $search = $('#lv-search');

        $search.on('input', function () {
            state.query = $search.val();
            renderRail();
        });

        $lists.on('click', '.sr-lv-list', function () {
            var key = $(this).data('list');
            var hit = filterLists(state.lists, state.query).filter(function (h) { return h.list.list === key; })[0];
            selectList(key, !!(hit && hit.matches.length));
        });

        $select.on('change', function () { selectList($select.val(), false); });

        $filter.on('input', function () {
            state.filter = $filter.val();
            renderTable();
        });

        $('#lv-add-button').on('click', function () { $newInput.trigger('focus'); });

        $newInput.on('input', function () { updateNewCounter(); $newError.addClass('d-none'); });
        $newInput.on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addValue();
            }
        });

        $tbody.on('click', '.sr-lv-rename', function () {
            startRename($(this).closest('tr'), $(this).data('value'));
        });
        $tbody.on('click', '.sr-lv-delete', function () {
            openDelete($(this).data('value'));
        });

        $('#lv-delete-commit').on('click', commitDelete);
        // Destructive confirm: focus lands on Cancel, not on the red button.
        $('#lv-delete-modal').on('shown.bs.modal', function () { $('#lv-delete-cancel').trigger('focus'); });
        $('#lv-delete-modal').on('hidden.bs.modal', function () { state.deleting = null; });

        loadLists().then(function () {
            var initial = String($root.data('initial-list') || '');
            state.selected = state.byKey[initial] ? initial : (state.lists[0] ? state.lists[0].list : null);
            renderRail();
            if (state.selected) {
                $('#lv-card').attr('data-list', state.selected);
                return loadValues();
            }
        }, function () {
            state.selected = null;
            $('#lv-title').text(lang('LookupCouldNotLoad'));
            renderLoadError();
        });
    });
})(window, jQuery);
