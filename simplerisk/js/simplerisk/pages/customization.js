(function () {
    'use strict';

    var SEEDED_TYPES = ['policies', 'guidelines', 'standards', 'procedures'];

    // header.php defines a single global window.L()/window._lang (see
    // header.php's inline _lang <script> block) -- do not redeclare a local
    // L() here (per the using-language-lookups skill). This script's keys are
    // registered under the 'CUSTOM:pages/customization.js' token in
    // header.php's $localization_required_by_scripts map.

    function esc(s) {
        return escapeHtml(s == null ? '' : String(s));
    }

    function renderDocumentTypesRows(types) {
        types = types || [];
        $('#document-types-count').text(types.length);
        var rows = types.map(function (t) {
            var isSeeded = SEEDED_TYPES.indexOf(t.name) !== -1;
            var status = isSeeded
                ? '<span class="sr-state-pill sr-state-neutral">' + esc(L('BuiltIn')) + '</span>'
                : '<span class="text-muted">&mdash;</span>';
            var deleteBtn = isSeeded
                ? '<span class="sr-row-action sr-row-action-danger disabled" title="' + esc(L('CantDeleteSeededDocumentCategory')) + '"><i class="fa fa-trash" aria-hidden="true"></i></span>'
                : '<span class="sr-row-action sr-row-action-danger document-type-delete" data-id="' + t.value + '"><i class="fa fa-trash" aria-hidden="true"></i></span>';
            var renameBtn = isSeeded
                ? '<span class="sr-row-action disabled" title="' + esc(L('CantRenameSeededDocumentCategory')) + '"><i class="fa fa-pen" aria-hidden="true"></i></span>'
                : '<span class="sr-row-action document-type-edit" data-id="' + t.value + '" data-name="' + esc(t.name) + '"><i class="fa fa-pen" aria-hidden="true"></i></span>';
            return '<tr><td>' + esc(t.label) + '</td><td>' + status + '</td>' +
                '<td class="text-end">' + renameBtn + ' ' + deleteBtn + '</td></tr>';
        }).join('');
        $('#document-types-body').html(rows);
    }

    function fetchDocumentTypes() {
        return $.ajax({ url: BASE_URL + '/api/v2/governance/document_types', method: 'GET' })
            .done(function (result) { renderDocumentTypesRows(result.data); });
    }

    function openAddModal() {
        $('#document-type-id').val('');
        $('#document-type-name').val('');
        $('#document-type-modal-title').text(L('AddDocumentType'));
    }

    function openEditModal(id, name) {
        $('#document-type-id').val(id);
        $('#document-type-name').val(name);
        $('#document-type-modal-title').text(L('DocumentType'));
    }

    function saveDocumentType() {
        var id = $('#document-type-id').val();
        var name = $('#document-type-name').val();
        var isUpdate = !!id;
        var url = BASE_URL + '/api/v2/governance/document_types' + (isUpdate ? '/' + id : '');

        $.ajax({
            url: url,
            method: isUpdate ? 'PATCH' : 'POST',
            data: { name: name }
        }).done(function (result) {
            $('#document-type-modal').modal('hide');
            renderDocumentTypesRows(result.data);
            showAlertsFromArray(result.status_message, true);
        }).fail(function (xhr) {
            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }

    function deleteDocumentType(id) {
        $.ajax({ url: BASE_URL + '/api/v2/governance/document_types/' + id, method: 'DELETE' })
            .done(function (result) {
                renderDocumentTypesRows(result.data);
                showAlertsFromArray(result.status_message, true);
            })
            .fail(function (xhr) {
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
    }

    $(document).ready(function () {
        if (!$('#document_types').length) { return; }

        fetchDocumentTypes();

        $('#document-types-add').on('click', openAddModal);
        $('#document-type-save').on('click', saveDocumentType);
        $(document).on('click', '.document-type-edit', function () {
            openEditModal($(this).data('id'), $(this).data('name'));
            $('#document-type-modal').modal('show');
        });
        $(document).on('click', '.document-type-delete', function () {
            deleteDocumentType($(this).data('id'));
        });
    });

    window.SR_CUSTOMIZATION = window.SR_CUSTOMIZATION || {};
    window.SR_CUSTOMIZATION.renderDocumentTypes = fetchDocumentTypes;

    // Field group selector -- a row of pills is the visible control; the real
    // '#fgroup' <select> stays in the DOM (hidden) purely so its existing
    // .change() handlers and the saveTemplate AJAX call's $('#fgroup').val()
    // read keep working unmodified. Clicking a pill sets the select's value
    // and re-fires 'change' rather than duplicating the navigation logic here.
    $(document).ready(function () {
        $(document).on('click', '.sr-fgroup-pill', function () {
            var fgroup = $(this).data('fg');
            $('.sr-fgroup-pill').removeClass('active').attr('aria-pressed', 'false');
            $(this).addClass('active').attr('aria-pressed', 'true');
            $('#fgroup').val(fgroup).trigger('change');
        });
    });

    // Fields tab -- backed by GET/PATCH /api/v2/admin/fields/list
    // and /api/v2/admin/fields/{id}, plus the pre-existing
    // POST /api/v2/admin/fields/add, POST /api/v2/admin/fields/delete,
    // GET /api/v2/admin/fields/get, POST /api/v2/customization/addOption
    // and POST /api/v2/customization/deleteOption endpoints (unchanged).
    var FIELD_TYPE_LABELS = {
        dropdown: L('Dropdown'), multidropdown: L('MultiDropdown'), shorttext: L('ShortText'),
        longtext: L('LongText'), date: L('DateSelector'), user_multidropdown: L('UserMultiDropdown'), hyperlink: L('Hyperlink')
    };

    // Field types whose values come from the custom_field_<id> name/value table and
    // are therefore manageable via the "Manage Options" modal + addOption/deleteOption.
    // user_multidropdown's options are the users table (create_multiusers_dropdown()),
    // so it gets the read-only rendered picker but not the add/delete controls -- this
    // mirrors the pre-existing getField()/'.action-content' show/hide logic exactly.
    var FIELD_OPTION_TYPES = ['dropdown', 'multidropdown', 'user_multidropdown'];
    var FIELD_MANAGEABLE_OPTION_TYPES = ['dropdown', 'multidropdown'];

    function renderFieldsRows(fields) {
        fields = fields || [];
        $('#custom-fields-count').text(fields.length);
        var rows = fields.map(function (f) {
            // Manage Options is folded into the edit modal now -- there is
            // no separate icon for it. .custom-field-edit's handler
            // decides whether to show the options section based on the
            // field's type (FIELD_OPTION_TYPES), same gate this used to use
            // for the standalone icon.
            return '<tr><td>' + esc(f.name) + '</td><td>' + esc(FIELD_TYPE_LABELS[f.type] || f.type) + '</td>' +
                '<td>' + (String(f.required) === '1' ? esc(L('Required')) : '<span class="text-muted">&mdash;</span>') + '</td>' +
                '<td class="text-end">' +
                // data-type/data-encryption dropped: .custom-field-edit
                // now fetches the field's current data by id instead of reading
                // it off attributes here -- see the handler below.
                '<span class="sr-row-action custom-field-edit" data-id="' + f.id + '"><i class="fa fa-pen" aria-hidden="true"></i></span> ' +
                '<span class="sr-row-action sr-row-action-danger custom-field-delete" data-id="' + f.id + '"><i class="fa fa-trash" aria-hidden="true"></i></span>' +
                '</td></tr>';
        }).join('');
        $('#custom-fields-body').html(rows);
    }

    function fetchFields(fgroup) {
        return $.ajax({ url: BASE_URL + '/api/v2/admin/fields/list', method: 'GET', data: { fgroup: fgroup } })
            .done(function (result) { renderFieldsRows(result.data); });
    }

    function createField(fgroup, fieldData) {
        return $.ajax({
            url: BASE_URL + '/api/v2/admin/fields/add',
            method: 'POST',
            data: Object.assign({ fgroup: fgroup }, fieldData)
        });
    }

    function addField() {
        var fgroup = $('#custom-fields-body').data('fgroup');
        createField(fgroup, {
            name: $('#field-create-name').val(),
            type: $('#type').val(),
            required: $('#field_required').is(':checked') ? 1 : 0,
            encryption: $('#field_encryption').length && $('#field_encryption').is(':checked') ? 1 : 0,
            alphabetical_order: $('#field_alphabetical_order').is(':checked') ? 1 : 0
        }).done(function (result) {
            $('#field-create-modal').modal('hide');
            $('#field-create-name').val('');
            $('#field_required').prop('checked', false);
            $('#field_alphabetical_order').prop('checked', false);
            if ($('#field_encryption').length) { $('#field_encryption').prop('checked', false); }
            fetchFields(fgroup);
            showAlertsFromArray(result.status_message, true);
        }).fail(function (xhr) {
            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }

    // Re-fetches this field's option list and re-renders it into the
    // options section that now lives inside '#custom-field-edit-modal' --
    // used after Add/Delete, where the modal is already open
    // and showing the section, so only the content needs refreshing here
    // (visibility of the section itself is decided once, on open, by
    // renderFieldOptionsSection() below).
    function fetchFieldOptions(fieldId) {
        return $.ajax({ url: BASE_URL + '/api/v2/admin/fields/get', method: 'GET', data: { field_id: fieldId } })
            .done(function (result) {
                renderFieldOptionsContent(fieldId, result.data);
            });
    }

    // Renders the option-list markup + multiselect widget + Add/Delete row
    // visibility from an already-fetched field record (`data`, the same
    // shape GET /api/v2/admin/fields/get always returns: type, content_html,
    // etc.). Shared by fetchFieldOptions() (re-fetch after Add/Delete) and
    // renderFieldOptionsSection() (initial open, reusing the record
    // '.custom-field-edit' already fetched -- no need for a second round
    // trip just to get the same data back).
    function renderFieldOptionsContent(fieldId, data) {
        $('#custom-field-options-content').html(data.content_html);
        if (data.type === 'multidropdown' || data.type === 'user_multidropdown') {
            $('#custom-field-options-content select').multiselect({ includeSelectAllOption: true, buttonWidth: '100%' });
        }
        var manageable = FIELD_MANAGEABLE_OPTION_TYPES.indexOf(data.type) !== -1;
        $('#custom-field-options-add-row').toggle(manageable);
        $('#custom-field-options-delete-row').toggle(manageable);
    }

    // Shows/hides the edit modal's Manage Options section for the
    // field currently being edited. Only FIELD_OPTION_TYPES fields (dropdown,
    // multidropdown, user_multidropdown) get a section at all -- everything
    // else gets it genuinely emptied and hidden, not just visually hidden,
    // so there's no leftover <select>/multiselect state from a previously
    // edited field bleeding into this one.
    function renderFieldOptionsSection(id, field) {
        var $section = $('#custom-field-edit-options-section');
        var hasOptions = FIELD_OPTION_TYPES.indexOf(field.type) !== -1;
        if (!hasOptions) {
            $('#custom-field-options-id').val('');
            $('#custom-field-options-new-name').val('');
            $('#custom-field-options-content').empty();
            $('#custom-field-options-add-row').hide();
            $('#custom-field-options-delete-row').hide();
            $section.hide();
            return;
        }
        $('#custom-field-options-id').val(id);
        $('#custom-field-options-new-name').val('');
        renderFieldOptionsContent(id, field);
        $section.show();
    }

    // Destructive confirm (design-system.md §8) -- ONE shared handler for
    // '.custom-field-delete' wherever it's bound: the Fields tab row AND the
    // field picker's custom-field row both emit this same class, so clicking
    // either one now opens '#custom-field-delete-modal' first instead of
    // deleting immediately. This is a deliberate, user-approved behavior
    // change on the previously-immediate Fields tab delete -- delete_field()
    // (extras/customization/index.php) runs a DROP TABLE plus deletes across
    // custom_fields and every fgroup's custom-data table, with no undo.
    var pendingDeleteFieldId = null;

    // Cosmetic only (the modal TITLE names the object per design-system.md
    // §8) -- the actual delete uses the id already on the trigger's data-id,
    // never this. Falls back to an empty string (a title with no name) for
    // any future trigger context this doesn't recognize, rather than
    // throwing.
    function resolveFieldRowName($trigger) {
        var $row = $trigger.closest('tr');
        if ($row.length) {
            return $row.find('td').eq(0).text();
        }
        var $item = $trigger.closest('.sr-fieldpick-item');
        return $item.length ? $item.find('.nm').text() : '';
    }

    $(document).on('click', '.custom-field-delete', function () {
        pendingDeleteFieldId = $(this).data('id');
        $('#custom-field-delete-title').text(L('DeleteCustomFieldTitle').replace('{field}', resolveFieldRowName($(this))));
        $('#custom-field-delete-modal').modal('show');
    });

    // A destructive confirm opens with focus on its SAFE action (same
    // pattern/rationale as governance-frameworks.js) -- Esc and backdrop are
    // disabled on this modal (data-bs-backdrop='static' data-bs-keyboard=
    // 'false', extras/customization/index.php), so Cancel is the only way
    // out besides confirming.
    $(document).on('shown.bs.modal', '#custom-field-delete-modal', function () {
        // '.btn-dark', not just '[data-bs-dismiss="modal"]' -- the modal's
        // header close (X) also carries that attribute and renders FIRST in
        // DOM order, so an unscoped selector would focus the X instead of
        // Cancel (same pattern as governance-frameworks.js's identical
        // destructive-confirm focus handler).
        $(this).find('[data-bs-dismiss="modal"].btn-dark').first().trigger('focus');
    });

    $(document).on('click', '#custom-field-delete-confirm', function () {
        var id = pendingDeleteFieldId;
        if (!id) {
            return;
        }
        $.ajax({ url: BASE_URL + '/api/v2/admin/fields/delete', method: 'POST', data: { field_id: id } })
            .done(function (result) {
                pendingDeleteFieldId = null;
                $('#custom-field-delete-modal').modal('hide');
                fetchFields($('#custom-fields-body').data('fgroup'));
                // The picker's custom column lists the same fields -- if it's
                // open, it needs the deleted field's row gone too.
                if (isFieldPickerOpen()) {
                    renderFieldPickerColumn('custom');
                }
                // The field no longer exists at all, so any chip for it
                // already sitting in a template layout (this tab or any
                // other -- a field can be added to more than one tab's
                // layout) is now a stale reference to a deleted row. Without
                // this, deleting an in-use field looked like it did nothing:
                // it vanished from Custom Fields/the picker but stayed
                // visible right there on the template.
                $('li.field-holder[data-main="0"][data-value="' + id + '"]').each(function () {
                    // .sort-list is the shared class every panel (.top-panel/
                    // .left-panel/.right-panel/.bottom-panel) carries.
                    var $panel = $(this).closest('.sort-list');
                    $(this).remove();
                    if ($panel.length) {
                        $panel.sortable('refresh');
                    }
                });
                showAlertsFromArray(result.status_message, true);
            })
            .fail(function (xhr) {
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
    });

    // Encryption is only meaningful for shorttext/longtext fields -- matches the
    // pre-existing getField()/update-encryption-wrapper show/hide condition this
    // modal replaces. The wrapper only exists in the DOM when encryption_extra()
    // is installed (see the matching PHP gate in extras/customization/index.php),
    // so every jQuery call against it below is a safe no-op otherwise.
    var FIELD_ENCRYPTABLE_TYPES = ['shorttext', 'longtext'];

    // Fetches the field's CURRENT data by id (GET /api/v2/admin/fields/get)
    // rather than reading it off '$(this).closest('tr')' -- the picker row
    // and the layout chip that also trigger this handler have no <tr>
    // ancestor, so the DOM-scraping version silently opened the modal with
    // empty fields from either surface. One handler now works identically
    // from all three contexts (Fields tab row, picker row, layout chip).
    $(document).on('click', '.custom-field-edit', function () {
        var id = $(this).data('id');
        $.ajax({ url: BASE_URL + '/api/v2/admin/fields/get', method: 'GET', data: { field_id: id } })
            .done(function (result) {
                var field = result.data;
                var encryptable = FIELD_ENCRYPTABLE_TYPES.indexOf(field.type) !== -1;
                $('#custom-field-edit-id').val(id);
                $('#custom-field-edit-name').val(field.name);
                $('#custom-field-edit-required').prop('checked', String(field.required) === '1');
                $('#custom-field-edit-encryption-wrapper').toggle(encryptable).data('encryptable', encryptable);
                $('#custom-field-edit-encryption').prop('checked', encryptable && String(field.encryption) === '1');
                // Manage Options -- folded into this same open-for-edit
                // flow instead of a separate '.custom-field-options' icon/modal.
                renderFieldOptionsSection(id, field);
                $('#custom-field-edit-modal').modal('show');
            })
            .fail(function (xhr) {
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
    });

    // Keeps an already-on-the-template '.field-holder' chip's displayed name/
    // required flag in sync after an edit through ITS OWN new pencil -- the
    // chip is plain client-side DOM the Templates designer owns (built by the
    // inline '#add_main_field'/'#add_custom_field' handlers and the two
    // display_admin_field_list() PHP echo sites), not re-fetched from
    // anywhere else, so nothing else refreshes it. Matches every
    // '.field-holder[data-main="0"]' with this field's id, not just the
    // visible tab's copy -- the Control fgroup mirrors each field onto TWO
    // tabs (Edit/View). Rewrites only the leading text node so the sibling
    // The name lives in its own '.nm' span (sibling of '.grip' and the
    // actions cluster), so updating it in place is a plain text swap --
    // no detach/reappend dance needed to keep the grip/edit/un-assign
    // icons from being wiped out.
    function updateFieldHolderChipText(id, name, required) {
        var displayName = name + (required ? '*' : '');
        $('li.field-holder[data-main="0"][data-value="' + id + '"]').each(function () {
            var $li = $(this);
            $li.find('.nm').text(displayName);
            $li.attr('data-text', displayName).data('text', displayName);
        });
    }

    $(document).on('click', '#custom-field-edit-save', function () {
        var id = $('#custom-field-edit-id').val();
        var fgroup = $('#custom-fields-body').data('fgroup');
        var data = {
            name: $('#custom-field-edit-name').val(),
            required: $('#custom-field-edit-required').is(':checked') ? 1 : 0
        };
        var encryptionWrapper = $('#custom-field-edit-encryption-wrapper');
        // Only send `encryption` when this field's type actually supports it --
        // resolve_custom_field_update_encryption() (extras/customization/includes/api.php) preserves the
        // field's current value when the param is absent, so omitting it for
        // non-applicable types (e.g. dropdown) is the correct "don't touch it" signal.
        if (encryptionWrapper.length && encryptionWrapper.data('encryptable')) {
            data.encryption = $('#custom-field-edit-encryption').is(':checked') ? 1 : 0;
        }
        $.ajax({
            url: BASE_URL + '/api/v2/admin/fields/' + id,
            method: 'PATCH',
            data: data
        }).done(function (result) {
            $('#custom-field-edit-modal').modal('hide');
            fetchFields(fgroup);
            // The picker's custom column lists the same fields -- refresh it
            // if open so a rename doesn't leave a stale label behind there.
            if (isFieldPickerOpen()) {
                renderFieldPickerColumn('custom');
            }
            // Neither refresh above touches an ALREADY-ON-THE-TEMPLATE
            // '.field-holder' chip -- editing via the chip's own new pencil
            // is possible now, and the chip is plain client-side DOM the
            // layout designer owns (not re-fetched from anywhere), so keep
            // its displayed name/required flag in sync in place.
            updateFieldHolderChipText(id, data.name, data.required);
            showAlertsFromArray(result.status_message, true);
        }).fail(function (xhr) {
            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });

    $(document).on('click', '#custom-field-options-add', function () {
        var id = $('#custom-field-options-id').val();
        var name = $('#custom-field-options-new-name').val();
        $.ajax({
            url: BASE_URL + '/api/v2/customization/addOption',
            method: 'POST',
            data: { field: id, name: name }
        }).done(function (result) {
            $('#custom-field-options-new-name').val('');
            fetchFieldOptions(id);
            showAlertsFromArray(result.status_message, true);
        }).fail(function (xhr) {
            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });

    $(document).on('click', '#custom-field-options-delete', function () {
        var id = $('#custom-field-options-id').val();
        var value = $('#custom-field-options-content select').val();
        $.ajax({
            url: BASE_URL + '/api/v2/customization/deleteOption',
            method: 'POST',
            data: { field: id, value: value }
        }).done(function (result) {
            fetchFieldOptions(id);
            showAlertsFromArray(result.status_message, true);
        }).fail(function (xhr) {
            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });

    window.SR_CUSTOMIZATION.renderFields = fetchFields;

    $(document).ready(function () {
        var fieldsBody = $('#custom-fields-body');
        if (fieldsBody.length) {
            fetchFields(fieldsBody.data('fgroup'));
            $('#field-create-save').on('click', addField);
        }
    });

    // Template Groups + business-unit assignment, merged into one admin
    // section (previously two: a Template Groups list with no assignment
    // UI, and a separate Template Assignment tab). Backed by
    // GET/POST /api/v2/customization/templateGroups,
    // PATCH/DELETE /api/v2/customization/templateGroups/{id}, and
    // GET/POST /api/v2/organizational_hierarchy/templateAssignments (moved
    // off the customization namespace -- business_unit_to_template_group is
    // owned by the Organizational Hierarchy Extra) -- both GET calls already
    // existed, this just renders them as one table.
    // The row's name link (full-page navigation, matching the pre-existing
    // behavior) goes to ?fgroup=...&template_group_id=...#templates, which is
    // what selects the group the layout designer further down the page
    // renders -- that designer markup is untouched by this task.
    // saveTemplateGroupAssignments() keeps the FULL-REPLACE semantics
    // documented on organizational_hierarchy_extra_saveTemplateAssignments()
    // (extras/organizational_hierarchy/includes/api.php): every save submits
    // every row's current selection. It runs automatically, debounced, off
    // the multiselect's own onChange hook -- there is no explicit Save
    // button.
    // Tracks the Default group's id for this fgroup so openAddTemplateGroupModal()
    // (the toolbar's "+ Add" button) can default-clone its fields into every new
    // group. Updated on every render since it's fgroup-specific and the fgroup can
    // change without a page reload.
    var defaultTemplateGroupId = null;

    function renderMergedTemplateGroupsTable(groups, assignmentData) {
        assignmentData = assignmentData || {};
        var businessUnits = assignmentData.business_units || [];
        var assignmentByGroupId = {};
        (assignmentData.groups || []).forEach(function (g) {
            assignmentByGroupId[g.id] = (g.business_unit_ids || []).map(String);
        });

        var fgroup = $('#template-groups-body').data('fgroup');
        var activeId = String($('#template-groups-body').data('activeId') == null ? '' : $('#template-groups-body').data('activeId'));
        defaultTemplateGroupId = null;
        var rows = groups.map(function (g) {
            var assigned = assignmentByGroupId[g.id] || [];
            var options = businessUnits.map(function (bu) {
                var isSelected = assigned.indexOf(String(bu.value)) !== -1;
                return '<option value="' + esc(bu.value) + '"' + (isSelected ? ' selected' : '') + '>' + esc(bu.name) + '</option>';
            }).join('');
            var href = BASE_URL + '/admin/customization.php?fgroup=' + encodeURIComponent(fgroup) + '&template_group_id=' + encodeURIComponent(g.id) + '#templates';
            var isDefault = String(g.is_default) === '1';
            if (isDefault) {
                defaultTemplateGroupId = g.id;
            }
            var isActive = activeId !== '' && String(g.id) === activeId;
            var renameBtn = '<span class="sr-row-action template-group-edit" data-id="' + esc(g.id) + '" data-name="' + esc(g.name) + '"><i class="fa fa-pen" aria-hidden="true"></i></span>';
            var cloneBtn = '<span class="sr-row-action template-group-clone" data-id="' + esc(g.id) + '" title="' + esc(L('CloneTemplateGroup')) + '"><i class="fa fa-copy" aria-hidden="true"></i></span>';
            var deleteBtn = isDefault
                ? '<span class="sr-row-action sr-row-action-danger disabled" title="' + esc(L('CantDeleteDefaultTemplateGroup')) + '"><i class="fa fa-trash" aria-hidden="true"></i></span>'
                : '<span class="sr-row-action sr-row-action-danger template-group-delete" data-id="' + esc(g.id) + '"><i class="fa fa-trash" aria-hidden="true"></i></span>';
            return '<tr data-group-id="' + esc(g.id) + '"' + (isActive ? ' class="sr-row-active" aria-current="true"' : '') + '>' +
                '<td><a href="' + href + '">' + esc(g.name) + '</a></td>' +
                '<td><select class="form-select" multiple="multiple">' + options + '</select></td>' +
                '<td class="text-end">' + renameBtn + ' ' + cloneBtn + ' ' + deleteBtn + '</td></tr>';
        }).join('');

        $('#template-groups-body').html(rows);
        $('#template-groups-count').text(groups.length);
        // bootstrap-multiselect gotcha (design-system.md #14b): these
        // <select multiple> elements are rendered fresh into the DOM on
        // every fetch, so .multiselect() is (re-)initialized here rather
        // than at page-load time. enableHTML is never set (XSS vector --
        // option labels are user-authored business unit names). A pick does
        // NOT fire a native `change` event on the underlying <select> --
        // listening there would silently miss every toggle -- so the
        // auto-save is wired through the plugin's own `onChange` hook
        // instead (the same pattern as chipSelectOnChange in
        // js/simplerisk/pages/compliance.js). Selections are still read via
        // $select.val() at save time.
        $('#template-groups-body select[multiple=multiple]').multiselect({
            includeSelectAllOption: true,
            buttonWidth: '100%',
            onChange: saveAssignmentsDebounced
        });
    }

    function fetchAndRenderTemplateGroups(fgroup) {
        return $.when(
            $.ajax({ url: BASE_URL + '/api/v2/customization/templateGroups', method: 'GET', data: { fgroup: fgroup } }),
            $.ajax({ url: BASE_URL + '/api/v2/organizational_hierarchy/templateAssignments', method: 'GET', data: { fgroup: fgroup } })
        ).done(function (groupsResp, assignmentsResp) {
            // jQuery's $.when resolves each deferred's arguments as
            // [data, textStatus, jqXHR] -- [0][0] is the parsed JSON body.
            renderMergedTemplateGroupsTable(groupsResp[0].data, assignmentsResp[0].data);
        });
    }

    function openAddTemplateGroupModal() {
        $('#template-group-id').val('');
        $('#template-group-name').val('');
        // Toolbar "+ Add" clones the Default group's fields by default, so a
        // brand-new group's designer isn't empty. Falls back to no clone when
        // there's no Default row yet (e.g. an fgroup with zero groups).
        $('#template-group-clone-from').val(defaultTemplateGroupId || '');
        $('#template-group-modal-title').text(L('AddTemplateGroup'));
    }

    function openEditTemplateGroupModal(id, name) {
        $('#template-group-id').val(id);
        $('#template-group-name').val(name);
        $('#template-group-clone-from').val('');
        $('#template-group-modal-title').text(L('UpdateTemplateGroup'));
    }

    // Per-row "clone" action -- opens the same add-modal, but pre-fills
    // clone-from with THIS row's id instead of Default's, and clears the
    // name field for the admin to type a new one.
    function openCloneTemplateGroupModal(id) {
        $('#template-group-id').val('');
        $('#template-group-name').val('');
        $('#template-group-clone-from').val(id);
        $('#template-group-modal-title').text(L('AddTemplateGroup'));
    }

    function saveTemplateGroup() {
        var id = $('#template-group-id').val();
        var name = $('#template-group-name').val();
        var fgroup = $('#template-groups-body').data('fgroup');
        var cloneFrom = $('#template-group-clone-from').val();
        var isUpdate = !!id;
        var url = BASE_URL + '/api/v2/customization/templateGroups' + (isUpdate ? '/' + id : '');

        $.ajax({
            url: url,
            method: isUpdate ? 'PATCH' : 'POST',
            data: isUpdate ? { name: name } : { name: name, fgroup: fgroup, clone_from: cloneFrom }
        }).done(function (result) {
            $('#template-group-modal').modal('hide');
            fetchAndRenderTemplateGroups(fgroup);
            showAlertsFromArray(result.status_message, true);
        }).fail(function (xhr) {
            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }

    function deleteTemplateGroup(id) {
        var fgroup = $('#template-groups-body').data('fgroup');
        confirm(L('AreYouSureYouWantToDeleteThisTemplateGroup'), function () {
            $.ajax({ url: BASE_URL + '/api/v2/customization/templateGroups/' + id, method: 'DELETE' })
                .done(function (result) {
                    fetchAndRenderTemplateGroups(fgroup);
                    showAlertsFromArray(result.status_message, true);
                })
                .fail(function (xhr) {
                    if (!retryCSRF(xhr, this)) {
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                });
        });
    }

    function saveTemplateGroupAssignments() {
        var fgroup = $('#template-groups-body').data('fgroup');
        var businessUnitIds = {};
        $('#template-groups-body tr').each(function () {
            var groupId = $(this).data('group-id');
            businessUnitIds[groupId] = $(this).find('select').val() || [];
        });

        $.ajax({
            url: BASE_URL + '/api/v2/organizational_hierarchy/templateAssignments',
            method: 'POST',
            data: { fgroup: fgroup, business_unit_ids: businessUnitIds }
        }).done(function (result) {
            fetchAndRenderTemplateGroups(fgroup);
            showAlertsFromArray(result.status_message, true);
        }).fail(function (xhr) {
            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }

    // bootstrap-multiselect's onChange fires once PER checkbox toggle, not
    // once per "session" at the field -- checking 3 business units in quick
    // succession fires 3 times. Debounce so a burst of toggles collapses
    // into a single save, sent shortly after the user stops clicking.
    var saveAssignmentsDebounced = (function () {
        var timer = null;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(saveTemplateGroupAssignments, 500);
        };
    })();

    window.SR_CUSTOMIZATION.renderTemplateGroups = fetchAndRenderTemplateGroups;

    $(document).ready(function () {
        var groupsBody = $('#template-groups-body');
        if (groupsBody.length) {
            fetchAndRenderTemplateGroups(groupsBody.data('fgroup'));
            $('#template-groups-add').on('click', openAddTemplateGroupModal);
            $('#template-group-save').on('click', saveTemplateGroup);
            $(document).on('click', '.template-group-edit', function () {
                openEditTemplateGroupModal($(this).data('id'), $(this).data('name'));
                $('#template-group-modal').modal('show');
            });
            $(document).on('click', '.template-group-clone', function () {
                openCloneTemplateGroupModal($(this).data('id'));
                $('#template-group-modal').modal('show');
            });
            $(document).on('click', '.template-group-delete', function () {
                deleteTemplateGroup($(this).data('id'));
            });
        }
    });

    // Templates designer -- inline two-column "+ Add field" picker,
    // redesigned after live user feedback rejected an earlier single-list
    // modal. Replaces the per-panel Main Field/Custom Field <select> +
    // button pairs (each fgroup branch in extras/customization/index.php's
    // display_customization() renders one, plus a matching hidden
    // '#add_main_field'/'#add_custom_field' button) with a panel docked
    // under the Template section's header (display_field_picker_panel()),
    // opened by the '#field-picker-open' button that now lives in that
    // header's '.sr-section-head-actions' slot.
    //
    // Deliberately client-side only for the add/remove/list itself, no AJAX
    // call: the pre-existing add-to-panel flow this retargets is itself pure
    // DOM manipulation (moving an <option> into a '<li class="field-holder">'
    // in the active tab's '.left-panel') -- persistence only happens later,
    // on '#save_template'. The two columns are built entirely from the
    // still-present (now permanently hidden) '#main_field_<tabIndex>' and
    // '#custom_fields' <select>s that extras/customization/index.php already
    // renders pre-filtered to "not yet in this template group"
    // (get_inactive_fields()) and, for core/main fields, to this tab's own
    // tab_index. The Custom column's "+ New custom field" row is the one
    // exception that DOES call the network -- it reuses the existing
    // POST /api/v2/admin/fields/add endpoint (createField(), defined above)
    // the Fields tab's own form already posts to.
    //
    // Only one fgroup branch's markup exists in the DOM per page load, so
    // '#tabs .nav-link.active' (or its absence, for the single-panel fgroups
    // that have no '#tabs' at all) is enough to resolve which tab/panel is
    // "current" -- exactly what the pre-existing getTabIndex() helpers
    // (defined per-branch in those inline scripts) already compute the same
    // way.
    //
    // Clicking a row sets the matching hidden select's value, then re-fires
    // the SAME '#add_main_field'/'#add_custom_field' click handlers those
    // inline scripts already bind -- so the exact original per-tab
    // targeting, including the Control fgroup's Edit/View tab-mirroring
    // (which reads the "currently selected" option off these same selects),
    // is unchanged and untouched by this redesign.
    function currentPanelTabIndex() {
        var tabIndex = $('#tabs .nav-link.active').data('tab-index');
        return tabIndex || 1;
    }

    function currentPanelTabName() {
        var $activeTab = $('#tabs .nav-link.active');
        return $activeTab.length ? $.trim($activeTab.text()) : '';
    }

    function isFieldPickerOpen() {
        var $picker = $('#sr-fieldpick');
        return $picker.length > 0 && !$picker.prop('hidden');
    }

    function renderFieldPickerHeading() {
        var tabName = currentPanelTabName();
        var $title = $('#fp-head .sr-fieldpick-title').empty();
        if (tabName) {
            $title.append(document.createTextNode(L('AddFieldsToPrefix') + ' '));
            $('<b>', { text: tabName }).appendTo($title);
        } else {
            $title.text(L('AddFieldsHeading'));
        }
    }

    // variant: 'idle' (muted prompt), 'ok' (a leading checkmark), or omitted
    // (plain). role="status"/aria-live="polite" on the container (server-
    // rendered) -- deliberately not a toastr: adding several fields in a row
    // would stack several toasts over the very designer the user is trying
    // to watch.
    function setFieldPickerStatus(text, variant) {
        var $status = $('#fp-status').empty();
        if (variant === 'ok') {
            $('<span>', { 'class': 'ok', text: '✓ ' }).appendTo($status);
        } else if (variant === 'idle') {
            $('<span>', { 'class': 'idle', text: text }).appendTo($status);
            return;
        }
        $status.append(document.createTextNode(text));
    }

    function fieldPickerEmptyState(source, query, $select) {
        var isCore = source === 'core';
        var $box = $('<div>', { 'class': 'sr-fieldpick-empty' });
        var $ico = $('<div>', { 'class': 'ico', 'aria-hidden': 'true' });
        var $ttl = $('<div>', { 'class': 'ttl' });

        if (query) {
            $('<i>', { 'class': 'fa fa-search' }).appendTo($ico);
            $ttl.text(L('NoFieldsMatchSearchTerm').replace('{term}', query));
            $box.append($ico, $ttl);
            $('<button>', { type: 'button', 'class': 'lnk', text: L('ClearSearch') })
                .on('click', function () {
                    var $search = $(isCore ? '#fp-core-search' : '#fp-custom-search');
                    $search.val('');
                    renderFieldPickerColumn(source);
                    $search.trigger('focus');
                })
                .appendTo($box);
            return $box;
        }

        // "No custom fields yet" needs the TOTAL count ever defined for this
        // fgroup (data-total-count, set by display_custom_fields_dropdown()),
        // not just the currently-inactive count -- otherwise "all added" and
        // "none exist" read identically. Undefined/NaN for the core select
        // (it carries no such attribute), so this branch never fires there.
        if (!isCore && parseInt($select.attr('data-total-count'), 10) === 0) {
            $('<i>', { 'class': 'fa fa-pen' }).appendTo($ico);
            $ttl.text(L('NoCustomFieldsYet'));
            $box.append($ico, $ttl);
            return $box;
        }

        $('<i>', { 'class': 'fa fa-check' }).appendTo($ico);
        $ttl.text(isCore ? L('AllCoreFieldsAddedToTab') : L('AllCustomFieldsAddedToTemplate'));
        $box.append($ico, $ttl);
        return $box;
    }

    function renderFieldPickerColumn(source) {
        var isCore = source === 'core';
        var $select = isCore ? $('#main_field_' + currentPanelTabIndex()) : $('#custom_fields');
        var $list = $(isCore ? '#fp-core-list' : '#fp-custom-list');
        var $count = $(isCore ? '#fp-core-count' : '#fp-custom-count');
        var query = $.trim($(isCore ? '#fp-core-search' : '#fp-custom-search').val() || '').toLowerCase();

        var $options = $select.find('option');
        $count.text($options.length);
        $list.empty();

        var $shown = $options.filter(function () {
            return !query || $(this).text().toLowerCase().indexOf(query) !== -1;
        });

        if (!$shown.length) {
            $list.append(fieldPickerEmptyState(source, query, $select));
            return;
        }

        $shown.each(function () {
            var $option = $(this);
            var value = $option.attr('value');
            var type = $option.attr('data-type');

            // .sr-fieldpick-item is a plain wrapper, not a <button> -- a
            // button cannot contain another interactive element, and custom
            // rows now carry edit/delete icons alongside the click-to-add
            // affordance. .sr-fieldpick-add is the actual click target
            // (delegated handler below); the row-actions cluster is its
            // sibling.
            var $item = $('<div>', { 'class': 'sr-fieldpick-item' });
            var $add = $('<button>', { type: 'button', 'class': 'sr-fieldpick-add' })
                .attr('data-source', source)
                .attr('data-value', value);
            $('<span>', { 'class': 'plus', 'aria-hidden': 'true', text: '+' }).appendTo($add);
            $('<span>', { 'class': 'nm', text: $option.text() }).appendTo($add);
            if (!isCore) {
                var typeLabel = FIELD_TYPE_LABELS[type];
                if (typeLabel) {
                    $('<span>', { 'class': 'tagc', text: typeLabel }).appendTo($add);
                }
            }
            $item.append($add);

            // Edit/delete -- custom fields only (Core fields have no
            // custom_fields row to manage). Same classes/data-id/delegated
            // handlers the Fields tab renders (renderFieldsRows() above), so
            // no new JS is needed to wire them up here beyond the markup
            // itself. Manage Options no longer has its own icon --
            // it's folded into the edit modal, gated there on the field's
            // type (FIELD_OPTION_TYPES), so both surfaces still agree on
            // which types get it.
            if (!isCore) {
                var $actions = $('<div>', { 'class': 'sr-fieldpick-actions' });
                $('<span>', { 'class': 'sr-row-action custom-field-edit' })
                    .attr('data-id', value)
                    .append($('<i>', { 'class': 'fa fa-pen', 'aria-hidden': 'true' }))
                    .appendTo($actions);
                $('<span>', { 'class': 'sr-row-action sr-row-action-danger custom-field-delete' })
                    .attr('data-id', value)
                    .append($('<i>', { 'class': 'fa fa-trash', 'aria-hidden': 'true' }))
                    .appendTo($actions);
                $item.append($actions);
            }

            $item.appendTo($list);
        });
    }

    // Flashes the just-added row in the actual layout panel below (not the
    // picker) so the eye can find it among what may be many fields. Only the
    // CURRENTLY VISIBLE tab's copy -- Control's mirrored second tab isn't
    // visible right now anyway.
    function flashJustAddedField(tabIndex) {
        var $li = $('.left-panel', '.tabs' + tabIndex).find('li.field-holder').last();
        if (!$li.length) {
            return;
        }
        $li.addClass('is-new');
        window.setTimeout(function () { $li.removeClass('is-new'); }, 1400);
    }

    // Re-fires the existing, UNMODIFIED add handlers (see the file-level
    // comment above) -- this is the entire "how does it land in the right
    // panel/tab" mechanism, unchanged from before this redesign.
    function addFieldFromPicker(source, value) {
        var tabIndex = currentPanelTabIndex();
        if (source === 'core') {
            $('#main_field_' + tabIndex).val(value);
            $('#add_main_field').trigger('click');
        } else {
            $('#custom_fields').val(value);
            $('#add_custom_field').trigger('click');
        }
        flashJustAddedField(tabIndex);
    }

    function showFieldPickerCreateRow() {
        $('#fp-create').prop('hidden', false);
        $('#fp-create-name').val('');
        $('#fp-create-required').prop('checked', false);
        $('#fp-create-name').trigger('focus');
    }

    function hideFieldPickerCreateRow() {
        $('#fp-create').prop('hidden', true);
    }

    function openFieldPicker() {
        $('#sr-fieldpick').prop('hidden', false);
        $('#field-picker-open').attr('aria-expanded', 'true');
        hideFieldPickerCreateRow();
        $('#fp-core-search').val('');
        $('#fp-custom-search').val('');
        renderFieldPickerHeading();
        renderFieldPickerColumn('core');
        renderFieldPickerColumn('custom');
        setFieldPickerStatus(L('PickFieldToAdd'), 'idle');
        window.setTimeout(function () { $('#fp-core-search').trigger('focus'); }, 20);
    }

    function closeFieldPicker() {
        $('#sr-fieldpick').prop('hidden', true);
        $('#field-picker-open').attr('aria-expanded', 'false');
        hideFieldPickerCreateRow();
    }

    $(document).on('click', '#field-picker-open', function () {
        if (isFieldPickerOpen()) { closeFieldPicker(); } else { openFieldPicker(); }
    });
    $(document).on('click', '#fp-done', closeFieldPicker);

    $(document).on('input', '#fp-core-search', function () { renderFieldPickerColumn('core'); });
    $(document).on('input', '#fp-custom-search', function () { renderFieldPickerColumn('custom'); });

    // Delegated on '.sr-fieldpick-add' (the inner click-to-add button), not
    // '.sr-fieldpick-item' (the row wrapper) -- see the restructuring
    // comment in renderFieldPickerColumn() above. The row-actions icons are
    // this button's SIBLINGS, so clicking one of them never reaches here.
    $(document).on('click', '.sr-fieldpick-add', function () {
        var $add = $(this);
        if ($add.prop('disabled')) {
            return;
        }
        var $item = $add.closest('.sr-fieldpick-item');
        var source = $add.attr('data-source');
        var value = $add.attr('data-value');
        var name = $add.find('.nm').text();
        var tabName = currentPanelTabName() || L('Template');

        addFieldFromPicker(source, value);

        // Collapse the row out of its column rather than yanking it away
        // instantly -- the shrink is what tells the eye "that's the one
        // that just moved," then the column rebuilds without it.
        $item.addClass('is-leaving');
        $add.prop('disabled', true);
        window.setTimeout(function () {
            renderFieldPickerColumn(source);
        }, 180);

        setFieldPickerStatus(L('FieldAddedToTab').replace('{field}', name).replace('{tab}', tabName), 'ok');
    });

    // Capture phase, not the usual bubble-phase $(document).on(): each fgroup
    // branch's own inline <script> already binds a bubble-phase
    // '.field-holder .delete' handler that reads this row's data AND removes
    // it from the DOM. Reading the same data here needs to happen BEFORE
    // that removal, so this listener must run first -- then the actual
    // re-render is deferred with setTimeout(0) to run AFTER that handler (and
    // everything else the click triggers) has finished mutating the
    // select/DOM, since capture-then-bubble is still one synchronous pass.
    document.addEventListener('click', function (e) {
        var del = e.target.closest && e.target.closest('.field-holder .delete');
        if (!del) {
            return;
        }
        var $li = $(del).closest('.field-holder');
        if (!$li.length) {
            return;
        }
        var isMain = String($li.data('main')) === '1' || $li.attr('data-main') === '1';
        var name = $li.data('text') || $li.attr('data-text') || $li.find('.nm').text();

        window.setTimeout(function () {
            if (!isFieldPickerOpen()) {
                return;
            }
            renderFieldPickerColumn(isMain ? 'core' : 'custom');
            setFieldPickerStatus(L('FieldRemovedBackToList').replace('{field}', name));
        }, 0);
    }, true);

    // Bootstrap's own 'shown.bs.tab' (fired once the new pane is fully shown
    // and the nav-link's 'active' class is set) rather than a raw click
    // handler -- avoids guessing whether this fires before or after
    // Bootstrap's own class toggle. Never fires on the single-panel fgroups
    // (no '#tabs' there at all), which is correct -- there's only ever one
    // panel to re-scope to.
    $(document).on('shown.bs.tab', '#tabs [data-bs-toggle="tab"]', function () {
        if (!isFieldPickerOpen()) {
            return;
        }
        $('#fp-core-search').val('');
        renderFieldPickerHeading();
        renderFieldPickerColumn('core');
        setFieldPickerStatus(L('NowAddingToTab').replace('{tab}', currentPanelTabName()), 'idle');
    });

    // Option B: the dashed "+ New custom field" row at the foot of the
    // Custom column, replacing the deleted '#field-create-shortcut' button/
    // modal. Creates the field via the same endpoint the Fields tab's own
    // form posts to, then adds it to the current panel in the same gesture
    // -- no page reload, unlike the shortcut it replaces, because the new
    // field's id/type come back in the response and get inserted as a fresh
    // <option> here (mirroring exactly what the delete handler already does
    // when it returns a removed field's <option> to its select).
    $(document).on('click', '#fp-new', showFieldPickerCreateRow);
    $(document).on('click', '#fp-create-cancel', hideFieldPickerCreateRow);
    $(document).on('click', '#fp-create-save', function () {
        var fgroup = $('#template-groups-body').data('fgroup') || $('#fgroup').val();
        var name = $.trim($('#fp-create-name').val() || '');
        if (!name) {
            $('#fp-create-name').trigger('focus');
            return;
        }
        var type = $('#fp-create-type').val();
        var required = $('#fp-create-required').is(':checked') ? 1 : 0;

        createField(fgroup, { name: name, type: type, required: required }).done(function (result) {
            hideFieldPickerCreateRow();
            var created = result.data || {};
            var $customSelect = $('#custom_fields');
            $('<option>')
                .text(name + (required ? '*' : ''))
                .val(created.id)
                .attr('data-type', type)
                .appendTo($customSelect);
            $customSelect.attr(
                'data-total-count',
                (parseInt($customSelect.attr('data-total-count'), 10) || 0) + 1
            );

            addFieldFromPicker('custom', created.id);
            renderFieldPickerColumn('custom');
            // The Custom Fields section (its own #custom-fields-body table,
            // now living above the tab strip) has no idea this field was
            // just created -- it's populated by its own fetchFields() call,
            // not by anything the picker touches. Without this, a field
            // created here shows up on the template immediately but is
            // missing from Custom Fields until the next full page load.
            fetchFields(fgroup);
            showAlertsFromArray(result.status_message, true);
            setFieldPickerStatus(
                L('FieldCreatedAndAddedToTab')
                    .replace('{field}', name)
                    .replace('{tab}', currentPanelTabName() || L('Template')),
                'ok'
            );
        }).fail(function (xhr) {
            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });
})();
