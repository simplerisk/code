$.fn.extend({
    initAsInitiateAuditTreegrid: function() {
        this.treegrid({
            iconCls: 'icon-ok',
            animate: true,
            fitColumns: true,
            nowrap: true,
            collapsible: false,
            url: BASE_URL + '/api/v2/compliance/initiate_audits',
            method: 'get',
            idField: 'id',
            treeField: 'name',
            scrollbarSize: 0,
            onBeforeLoad: function(row, param){
                param.filter_by_text = $('#filter_by_text').val();
                param.filter_by_status = $('#filter_by_status').val();
                param.filter_by_frequency = $('#filter_by_frequency').val();
                param.filter_by_framework = $('#filter_by_framework').val();
                param.filter_by_control = $('#filter_by_control').val();
            },
        });
    },
});

function createTagsInstance(tag, tag_type, options) {
    if (typeof tag_type === 'undefined') tag_type = 'test';
	var selectize_setup = {
        plugins: ['remove_button', 'restore_on_backspace'],
        delimiter: '|',
        createFilter: function(input) { return input.length <= 255; },
        create: true,
        valueField: 'label',
        labelField: 'label',
        searchField: 'label',

        // Show the existing tags the moment the field is focused, most-reused
        // first. Tags only pay off when they're REUSED -- an empty box invites
        // a new near-duplicate ("SOX", "sox", "SOX-404") every time, so the
        // list of what the team already uses has to be the first thing you
        // see, not something you discover by typing a lucky prefix.
        // usage_count comes from GET /management/tag_options_of_types
        // (getTagsOfTypes(), includes/functions.php).
        openOnFocus: true,
        sortField: [
            { field: 'usage_count', direction: 'desc' },
            { field: 'label', direction: 'asc' }
        ],

        render: {
            // Makes "you can invent a tag here" visible instead of something
            // the user has to guess. `escape` is selectize's own escaper --
            // the typed text is user input and goes into markup here.
            option_create: function(data, escape) {
                var template = (typeof _lang !== 'undefined' && _lang['CreateTagX']) ? _lang['CreateTagX'] : 'Create “{tag}”';
                return '<div class="create sr-tag-create">+ ' + template.replace('{tag}', escape(data.input)) + '</div>';
            }
        }
    };
	// If options aren't provided, setup the selectize's preload to load them
	if (typeof options === 'undefined' || options.length == 0) {
		selectize_setup.preload = true;
		selectize_setup.load = function(query, callback) {
            if (query.length) return callback();
            
            $.ajax({
                url: BASE_URL + '/api/v2/management/tag_options_of_types?type=' + tag_type,
                type: 'GET',
                dataType: 'json',
                error: function() {
                    console.log('Error loading!');
                    callback();
                },
                success: function(res) {
                    callback(res.data);
                }
            });
        };
	} else {
		selectize_setup.options = options;
	}

    tag.selectize(selectize_setup);
}

$(function() {

    // Display the test type tags as selectize
    if($('select.test_tags').length > 0) createTagsInstance($('select.test_tags'));

    // Display the test_audit type tags as selectize
    if($('select.test_audit_tags').length > 0) createTagsInstance($('select.test_audit_tags'), 'test_audit');
    
    // Display the test and test_audit type tags as selectize
    if($('select.test_audit_test_tags').length > 0) createTagsInstance($('select.test_audit_test_tags'), 'test,test_audit');
    
})

/***********************************************************************
 * Schedule mode (Manual / Interval / Calendar) — shared by the Add and *
 * Edit test modals. Field visibility follows the selected schedule_type,
 * the cadence preset drives the underlying cadence_unit/cadence_interval
 * hidden inputs, and the Upcoming occurrences preview is populated by
 * calling the schedule_preview endpoint whenever the cadence or anchor
 * date changes. Per-occurrence skip / override-date edits are collected
 * client-side into a schedule_exceptions map keyed by the *natural*
 * (un-overridden) occurrence date, and serialized into a hidden
 * `schedule_exceptions` field right before the form is submitted.
 ***********************************************************************/

// Show/hide the schedule-mode-dependent field groups within a modal and
// refresh the occurrences preview when Calendar is selected.
function applyScheduleModeVisibility($modal) {
    let mode = $modal.find('input[name="schedule_type"]:checked').val() || 'calendar';

    $modal.find('.schedule-field-interval').toggleClass('d-none', mode !== 'interval');
    $modal.find('.schedule-field-calendar').toggleClass('d-none', mode !== 'calendar');

    // The custom cadence inputs are calendar fields too, so the line above
    // un-hides them along with the rest whenever Calendar is selected -- even
    // when the preset is Monthly. They're only meaningful for the "Custom"
    // preset, so re-apply that rule here rather than waiting for the next
    // .cadence-preset change event to correct it.
    $modal.find('.schedule-cadence-custom')
        .toggleClass('d-none', mode !== 'calendar' || $modal.find('.cadence-preset').val() !== 'custom');
    $modal.find('.schedule-field-noncalendar').toggleClass('d-none', mode === 'calendar');
    $modal.find('.schedule-field-offset').toggleClass('d-none', mode === 'manual');

    // Anchor date is only meaningful (and only shown) for a Calendar schedule.
    $modal.find('input[name="cadence_anchor_date"]').prop('required', mode === 'calendar');

    if (mode === 'manual') {
        // Lead-in days isn't applicable without a schedule driving next_date.
        $modal.find('input[name="audit_initiation_offset"]').val('');
    }

    if (mode === 'calendar') {
        // The server computes next_date from the cadence engine for calendar
        // schedules; clear any stale manually-entered value so it isn't
        // mistaken for an explicit override on submit.
        $modal.find('input[name="next_date"]').val('');
        refreshOccurrencesPreview($modal);
    } else {
        $modal.find('.schedule-occurrences-list').empty();
        $modal.find('.schedule-occurrences-empty, .schedule-occurrences-error').addClass('d-none');
    }
}

// Sync the hidden cadence_unit/cadence_interval fields from the custom
// unit/interval controls (used when the preset select is set to "Custom").
function syncCustomCadence($modal) {
    let unit = $modal.find('.cadence-custom-unit').val() || 'month';
    let interval = parseInt($modal.find('.cadence-custom-interval').val(), 10);
    if (!interval || interval < 1) interval = 1;
    $modal.find('.cadence-unit-value').val(unit);
    $modal.find('.cadence-interval-value').val(interval);
}

// Select the cadence preset (or "Custom") matching a given unit/interval —
// used to repopulate the Edit modal from a test's persisted schedule.
function setCadencePresetFromValue($modal, unit, interval) {
    let $preset = $modal.find('.cadence-preset');
    let matched = false;

    $preset.find('option').each(function() {
        if ($(this).data('unit') === unit && String($(this).data('interval')) === String(interval)) {
            $preset.val($(this).val());
            matched = true;
            return false;
        }
    });

    if (matched) {
        $modal.find('.schedule-cadence-custom').addClass('d-none');
    } else {
        $preset.val('custom');
        $modal.find('.cadence-custom-unit').val(unit || 'month');
        $modal.find('.cadence-custom-interval').val(interval || 1);
        $modal.find('.schedule-cadence-custom').removeClass('d-none');
    }

    $modal.find('.cadence-unit-value').val(unit || 'month');
    $modal.find('.cadence-interval-value').val(interval || 1);
}

// Convert a cadence-anchor / override-date field's display-format value (the
// instance's configured default_date_format, e.g. "MM/DD/YYYY") into
// canonical ISO ("YYYY-MM-DD") for the schedule_preview / schedule_exceptions
// API contract, which is engine-native ISO in/out. Returns null for an
// empty or unparseable value (strict parse — no lenient fallback formats).
function parseCalendarAnchorToIso(displayValue) {
    if (!displayValue) return null;
    let m = moment(displayValue, default_date_format, true);
    return m.isValid() ? m.format('YYYY-MM-DD') : null;
}

// Merge the current on-screen skip / override state of the rendered
// occurrence rows into the modal's persisted schedule_exceptions map (keyed
// by the row's natural, un-overridden occurrence date), and return the map.
// Rows for occurrences outside the current preview window (e.g. seeded from
// an existing test but not re-rendered) are left untouched. The map (like the
// natural-date keys) is kept in ISO — the engine-native format — even though
// the on-screen .occurrence-override field displays/accepts the instance's
// default_date_format; renderOccurrences() converts back to display format
// when re-seeding a row.
function collectPendingExceptions($modal) {
    let map = $modal.data('scheduleExceptions') || {};

    $modal.find('.schedule-occurrences-list .occurrence-row').each(function() {
        let $row = $(this);
        let date = $row.attr('data-natural-date');
        if (!date) return;

        let skipped = $row.find('.occurrence-skip').is(':checked');
        let overrideIso = parseCalendarAnchorToIso($row.find('.occurrence-override').val());

        if (skipped) {
            map[date] = { skipped: true };
        } else if (overrideIso) {
            map[date] = { skipped: false, override_date: overrideIso };
        } else {
            delete map[date];
        }
    });

    $modal.data('scheduleExceptions', map);
    return map;
}

// Build the request-shaped schedule_exceptions payload (array of
// {occurrence_date, override_date, skipped}) the API expects.
function buildScheduleExceptionsPayload($modal) {
    let map = collectPendingExceptions($modal);
    let payload = [];

    Object.keys(map).forEach(function(date) {
        let entry = map[date];
        payload.push({
            occurrence_date: date,
            skipped: !!entry.skipped,
            override_date: entry.skipped ? null : (entry.override_date || null),
        });
    });

    return payload;
}

// Render one row per natural occurrence date, re-applying any pending
// skip/override state collected from the previous render (or seeded from an
// existing test's persisted exceptions).
function renderOccurrences($modal, dates) {
    let $list = $modal.find('.schedule-occurrences-list');
    let $empty = $modal.find('.schedule-occurrences-empty');
    let $template = $modal.find('.occurrence-row-template');
    let existing = collectPendingExceptions($modal);
    let today = moment().format('YYYY-MM-DD');

    $list.empty();

    if (!dates.length) {
        $empty.removeClass('d-none');
        return;
    }
    $empty.addClass('d-none');

    dates.forEach(function(dateStr) {
        let $row = $template.find('.occurrence-row').clone();
        $row.attr('data-natural-date', dateStr);

        // dateStr comes from the schedule_preview API response (ISO) and is the
        // engine key kept in data-natural-date above; it is not format-validated
        // server-side, so never trust it as markup — set the display text via
        // .text() only, never .html(), to rule out an XSS sink here. Displayed to
        // the user in the instance's configured date format.
        $row.find('.occurrence-date').text(moment(dateStr, 'YYYY-MM-DD').format(default_date_format));

        if (dateStr < today) {
            $row.find('.occurrence-overdue').removeClass('d-none');
        }

        let seeded = existing[dateStr];
        let $override = $row.find('.occurrence-override');
        let overrideDisabled = false;
        let overrideVal = '';
        if (seeded) {
            if (seeded.skipped) {
                $row.find('.occurrence-skip').prop('checked', true);
                overrideDisabled = true;
            } else if (seeded.override_date) {
                // seeded.override_date is ISO (the pending-exceptions map is kept
                // engine-native); display it in the instance's date format.
                overrideVal = moment(seeded.override_date, 'YYYY-MM-DD').format(default_date_format);
                $row.find('.occurrence-skip').prop('disabled', true);
            }
        }

        $list.append($row);

        // Initialize the datepicker only once the row is attached to the DOM,
        // then apply the seeded value/disabled state explicitly.
        $override.initAsDatePicker();
        $override.val(overrideVal).prop('disabled', overrideDisabled);
    });
}

// Call schedule_preview for the modal's current cadence/anchor and render
// the first handful of natural (un-overridden) occurrences as editable rows.
function refreshOccurrencesPreview($modal) {
    let anchorDisplay = $modal.find('input[name="cadence_anchor_date"]').val();
    let unit = $modal.find('.cadence-unit-value').val();
    let interval = $modal.find('.cadence-interval-value').val();
    let $list = $modal.find('.schedule-occurrences-list');
    let $empty = $modal.find('.schedule-occurrences-empty');
    let $error = $modal.find('.schedule-occurrences-error');

    $empty.addClass('d-none');
    $error.addClass('d-none');

    // cadence_anchor_date is entered/displayed in the instance's configured
    // date format (like last_date/next_date); convert to ISO for the
    // schedule_preview API, which is engine-native ISO in/out.
    let anchor = parseCalendarAnchorToIso(anchorDisplay);

    if (!anchor || !unit || !interval) {
        $list.empty();
        return;
    }

    // Automated (Calendar) schedules must not anchor to a past date -- block
    // the preview call and tell the user rather than silently previewing an
    // already-overdue schedule. Manual tests are the path for backdated
    // scheduling.
    if (anchor < moment().format('YYYY-MM-DD')) {
        $list.empty();
        showAlertFromMessage(_lang["AnchorDateMustBeTodayOrLater"]);
        return;
    }

    // Clear stale rows from a previous modal session immediately, rather than
    // leaving them visible until the request resolves.
    $list.empty();

    let endDate = moment(anchor, 'YYYY-MM-DD').add(3, 'years').format('YYYY-MM-DD');

    $.ajax({
        type: 'POST',
        url: BASE_URL + '/api/v2/compliance/schedule_preview',
        contentType: 'application/json',
        // A JSON-body POST bypasses csrf-magic's automatic token injection
        // (it only rewrites form-urlencoded / FormData bodies), so send the
        // token explicitly via the CSRF-TOKEN header — csrf-magic.php copies
        // that header into $_POST['__csrf_magic']. Matches the app's other
        // JSON AJAX calls (settings-hub.js, notifications.js, ...).
        headers: { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' },
        data: JSON.stringify({
            cadence_unit: unit,
            cadence_interval: interval,
            cadence_anchor_date: anchor,
            start: anchor,
            end: endDate,
            // Always preview the *natural* sequence here — previously
            // collected skip/override state is re-applied client-side by
            // renderOccurrences() so in-progress edits aren't lost.
            schedule_exceptions: [],
        }),
        success: function(result) {
            let occurrences = (result && result.data && Array.isArray(result.data.occurrences)) ? result.data.occurrences : [];
            renderOccurrences($modal, occurrences.slice(0, 8));
        },
        error: function() {
            $list.empty();
            $error.removeClass('d-none');
        },
    });
}

$(document).on('change', '.schedule-mode-group input[name="schedule_type"]', function() {
    applyScheduleModeVisibility($(this).closest('.modal'));
});

$(document).on('change', '.cadence-preset', function() {
    let $modal = $(this).closest('.modal');
    let isCustom = $(this).val() === 'custom';

    $modal.find('.schedule-cadence-custom').toggleClass('d-none', !isCustom);

    if (isCustom) {
        syncCustomCadence($modal);
    } else {
        let $opt = $(this).find(':selected');
        $modal.find('.cadence-unit-value').val($opt.data('unit'));
        $modal.find('.cadence-interval-value').val($opt.data('interval'));
    }

    refreshOccurrencesPreview($modal);
});

$(document).on('change', '.cadence-custom-unit, .cadence-custom-interval', function() {
    let $modal = $(this).closest('.modal');
    syncCustomCadence($modal);
    refreshOccurrencesPreview($modal);
});

$(document).on('change', 'input[name="cadence_anchor_date"]', function() {
    refreshOccurrencesPreview($(this).closest('.modal'));
});

$(document).on('change', '.occurrence-skip', function() {
    let $row = $(this).closest('.occurrence-row');
    let $override = $row.find('.occurrence-override');

    if ($(this).is(':checked')) {
        $override.val('').prop('disabled', true);
    } else {
        $override.prop('disabled', false);
    }

    collectPendingExceptions($(this).closest('.modal'));
});

$(document).on('change', '.occurrence-override', function() {
    let $row = $(this).closest('.occurrence-row');
    let $skip = $row.find('.occurrence-skip');
    let val = $(this).val();

    // The datepicker (Apply/Cancel) only ever hands back a value matching the
    // instance's configured date format or an empty string, but validate
    // defensively (strict parse, no lenient fallback) before trusting it.
    if (val && parseCalendarAnchorToIso(val)) {
        $skip.prop('checked', false).prop('disabled', true);
    } else {
        $(this).val('');
        $skip.prop('disabled', false);
    }

    collectPendingExceptions($(this).closest('.modal'));
});

/***********************************************************************
 * Client-side SoD (segregation-of-duties) hint (Phase 3a) — shared by   *
 * the Add and Edit test modals. Non-blocking: the server                *
 * (test_tester_conflicts_with_approvers(), includes/compliance.php)     *
 * still enforces this authoritatively with a 400 on submit; this is     *
 * only a UX nudge so the conflict is visible before the user submits.   *
 * The approvers <select> has a different id per modal (#approvers_add   *
 * in Add, #approvers in Edit -- see compliance/index.php /              *
 * includes/compliance.php), so it's looked up by an "id starts with     *
 * approvers" match rather than a fixed id.                              *
 ***********************************************************************/
function updateSodWarning($modal) {
    var testerVal = String($modal.find('[name=tester]').val() || '');
    var approverVals = ($modal.find('select[id^="approvers"]').val() || []).map(String);
    var conflict = testerVal !== '' && approverVals.indexOf(testerVal) !== -1;
    $modal.find('.sod-warning').toggleClass('d-none', !conflict);
}

$(document).on('change', '#test--add [name=tester], #test--add select[id^="approvers"], #test--update [name=tester], #test--update select[id^="approvers"]', function() {
    updateSodWarning($(this).closest('.modal'));
});

/***********************************************************************
 * Add Test modal open/reset — shared by the Define Tests grid's         *
 * per-control "+ Add test" button (rendered client-side by               *
 * compliance-define-tests.js) and its toolbar's primary Add Test        *
 * action. Lives here (not in compliance-define-tests.js) because it's   *
 * modal lifecycle logic, alongside the modal's other handlers below.    *
 * Declared at top level (like applyScheduleModeVisibility() above) so   *
 * it's callable from any script loaded after this one.                  *
 ***********************************************************************/
function openAddTestModal(prefillControlId) {
    resetForm('#test-new-form');

    // Clear any AI-suggestion source id from a prior "Review & edit" open so a
    // plain Add never consumes a stale proposal. The suggestion-review handler
    // (compliance-define-tests.js) re-sets this AFTER this function returns.
    $('#test--add').removeData('sourceProposalId');

    // resetForm() clears the underlying <select>s and refreshes the widgets, but
    // it fires no `change` -- and the chips render off `change`. So every
    // chip field kept displaying the PREVIOUS test's picks over a select that
    // was already empty: Cancel, reopen, and Team(s)/Additional Stakeholders
    // still showed the last selection while the form would have submitted none.
    // The same desync is what made a reopened dropdown show nothing checked
    // while chips claimed otherwise -- two views of one selection, one of them
    // never told it had changed.
    //
    // Re-rendered here rather than by making resetForm() fire change events:
    // that helper is used by forms across the app, and synthesising events into
    // all of them to fix this modal would be a much larger blast radius than
    // the bug.
    $('#test-new-form').find('select.multiselect').each(function () {
        renderSelectionChips($(this));
    });

    var $select = $('#add_test_control', $('#test-new-form'));
    var addModal = $('#test--add');
    // Phase 4a (common tests): #add_test_control holds N controls, not the old
    // single-select + disabled-select/hidden-input lock trick -- there's nothing
    // left to disable/re-enable here. resetForm() above already cleared the
    // <select> (native form reset()); clearing again here is what stops a prior
    // per-control open's selection leaking into a fresh toolbar-triggered open,
    // and re-rendering the chips is what makes that visible (a native reset()
    // doesn't tell anything that the value changed).
    applyControlSelection($select, []);

    if (prefillControlId) {
        var controlIdValue = String(prefillControlId);
        if (controlsRoster.length) {
            // The roster has landed, so the <select> has real options -- select
            // the prefilled control directly. Opened via a control's own
            // "+ Add test" button (Issue 9): the control is pre-selected but,
            // unlike the old locked single select, not disabled -- a common test
            // can map to more than this one control, so the user may add others.
            applyControlSelection($select, [controlIdValue]);
        } else {
            // The roster (loadControlRoster()) is fetched asynchronously on page
            // load and may not have resolved yet, so the <select> has no option
            // to select. Queue the desired selection on the modal instead;
            // populateControlSelect() re-applies it once the roster arrives.
            addModal.data('pendingControlPrefill', [controlIdValue]);
        }
    } else {
        // Opened via the toolbar's primary Add Test action -- no pre-fill.
        // Clear any stale queued prefill from a prior per-control open that
        // raced ahead of the roster load and never got applied.
        addModal.removeData('pendingControlPrefill');
    }

    // Backward-looking date fields in this form (Last Test Date) can't be in
    // the future. The cadence anchor is the OPPOSITE -- a Calendar schedule
    // can't anchor to the past -- so it is excluded here and initialized with
    // its own minDate below. A blanket sweep handed it maxDate=today, which
    // left the picker offering only today-or-earlier while the validation
    // told the user the anchor must be today-or-later: two rules pointing in
    // opposite directions, with no date that satisfied both.
    //
    // Occurrence-override inputs are excluded for the same reason (an
    // override moves an occurrence to a future date); the per-row copies get
    // their own unconstrained init when they're rendered.
    // Tear down first: this runs on EVERY open, and daterangepicker builds a
    // fresh instance (with its own calendar container and its own bound
    // handlers) per init rather than reconfiguring the existing one. Stacked
    // instances are why the anchor field could still offer a past-only
    // calendar after being re-initialized with a minDate -- an older
    // instance's calendar was the one opening.
    $('#test-new-form .datepicker').each(function () {
        var picker = $(this).data('daterangepicker');
        if (picker) {
            picker.remove();
        }
    });

    $('#test-new-form .datepicker')
        .not('[name=cadence_anchor_date]')
        .not('.occurrence-override')
        .initAsDatePicker({maxDate: new Date});

    $('#test-new-form [name=cadence_anchor_date]').initAsDatePicker({minDate: moment().format(default_date_format)});

    // Reset the schedule preview state and re-apply the default (Calendar)
    // schedule mode's field visibility + occurrences preview.
    addModal.removeData('scheduleExceptions');
    if (typeof applyScheduleModeVisibility === 'function') {
        applyScheduleModeVisibility(addModal);
    }

    // resetForm() above already cleared the tester select and (via its
    // 'select.multiselect' refresh) the approvers multiselect, so there's
    // never a real conflict on open -- just make sure any warning left
    // visible from a prior open is hidden.
    if (typeof updateSodWarning === 'function') {
        updateSodWarning(addModal);
    }

    addModal.modal('show');
}

/***********************************************************************
 * Add/Edit Test modals' control roster (Phase 4a, common tests).        *
 *                                                                        *
 * FIX (audit_initiation.php edit-modal roster regression): this used to *
 * live entirely in js/simplerisk/pages/compliance-define-tests.js, but  *
 * that file only loads on compliance/index.php (Define Tests). The      *
 * Edit Test modal (display_update_test_modal(), includes/compliance.php)*
 * is also rendered on compliance/audit_initiation.php (Initiate         *
 * Audits), which loads this file (compliance.js) but NOT                *
 * compliance-define-tests.js -- so on that page the required            *
 * #edit_test_control multiselect was never populated/initialized,       *
 * permanently blocking every "Update" submit with an unfillable         *
 * "Field Required" error. Moved here so the roster loads and both       *
 * modals' control multi-selects initialize wherever the modal is        *
 * rendered. Declared at top level (like openAddTestModal() above) so    *
 * it's callable from any script loaded after this one, and so           *
 * compliance-define-tests.js (which still loads *after* this file on    *
 * compliance/index.php -- see the CUSTOM: script array order in that    *
 * page's render_header_and_sidebar() call) could call back into it if   *
 * it ever needs the roster again.
 ***********************************************************************/

// The full control roster (id/control_number/short_name), fetched once
// from the lightweight GET /api/v2/compliance/control_roster (Issue 5)
// to populate the Add and Edit Test modals' control multi-selects
// (Phase 4a, common tests). Unlike the Define Tests grid's own
// tests_grid feed, this endpoint does no test/last-result/tag
// enrichment, so it stays cheap even as the control count grows.
var controlsRoster = [];

// The same roster keyed by id, so rendering a chip for a selected id doesn't
// scan ~1,500 rows per chip per render.
var controlsById = {};

/***********************************************************************
 * FACETED PICKER — choosing from a roster too large to scroll          *
 *                                                                      *
 * A dialog that narrows a roster by facets before listing it, backing   *
 * the Add/Edit Test modals' Control Name field (its markup is           *
 * display_control_picker_modal(), includes/compliance.php).             *
 *                                                                      *
 * WHY: a real SCF import is ~1,500 controls. A dropdown asks "which of  *
 * these"; past a few dozen options the actual question is "how do I get *
 * to the right neighbourhood", which needs narrowing, not scrolling.    *
 * Roster size is the deciding factor, not field type — the Tester /     *
 * Teams / Stakeholders / Approvers fields in the same modal are tens of *
 * rows and keep their dropdown, where a dialog would be ceremony.       *
 *                                                                      *
 * Written against a config rather than against controls: `facets` is a  *
 * list of {key, container, itemValues} and every label comes from the   *
 * caller, so the next large roster reuses this instead of copying it.   *
 * The only control-specific things here are the ids and                 *
 * createControlPicker() at the bottom.                                  *
 ***********************************************************************/
function createFacetedPicker(config) {
    var $modal = $('#' + config.modalId);
    if (!$modal.length) {
        return null;
    }

    var $search = $('#' + config.searchId);
    var $list = $('#' + config.listId);
    var $selected = $('#' + config.selectedId);
    var $count = $('#' + config.countId);
    var $selectedCount = $('#' + config.selectedCountId);
    var $scope = $('#' + config.scopeId);

    // Everything the dialog is currently working with. `chosen` is the
    // WORKING copy: the field behind the dialog only changes on commit, so
    // Cancel (and the backdrop, and Esc) genuinely abandon.
    var items = [];
    var chosen = [];
    var facetState = {};
    var cursor = 0;
    var onCommit = null;

    config.facets.forEach(function (facet) {
        facetState[facet.key] = null;
    });

    // A facet narrows what the facets AFTER it may offer -- framework, then
    // family counted inside that framework. Everything before the given facet
    // applies; the facet itself doesn't, or it could only ever count itself.
    function itemsBefore(facetKey) {
        return items.filter(function (item) {
            return config.facets.every(function (facet) {
                if (facet.key === facetKey) {
                    return true;
                }
                var value = facetState[facet.key];
                return value === null || facet.itemValues(item).indexOf(value) !== -1;
            });
        });
    }

    function matchesTerm(item) {
        var term = $.trim(($search.val() || '')).toLowerCase();
        if (!term) {
            return true;
        }
        return config.searchText(item).toLowerCase().indexOf(term) !== -1;
    }

    function visibleItems() {
        return itemsBefore(null).filter(matchesTerm);
    }

    function renderFacet(facet) {
        var scoped = itemsBefore(facet.key);
        var counts = {};
        scoped.forEach(function (item) {
            facet.itemValues(item).forEach(function (value) {
                counts[value] = (counts[value] || 0) + 1;
            });
        });

        $('#' + facet.container).find('.sr-picker-facet').each(function () {
            var $button = $(this);
            var raw = $button.attr('data-picker-value');
            var isAll = (raw === '' || raw === undefined);
            var value = isAll ? null : parseInt(raw, 10);
            var count = isAll ? scoped.length : (counts[value] || 0);

            $button.attr('aria-pressed', facetState[facet.key] === value ? 'true' : 'false');
            // An option with nothing behind it stays visible but inert: hiding
            // it would make the list length jump around as other facets change,
            // and a greyed row still answers "is there anything here?".
            $button.toggleClass('is-empty', !isAll && count === 0);
            $button.find('.sr-picker-facet-count').text(count ? String(count) : '');
        });
    }

    function renderFacets() {
        config.facets.forEach(renderFacet);
    }

    function renderList() {
        var found = visibleItems();
        if (cursor > found.length - 1) {
            cursor = Math.max(found.length - 1, 0);
        }

        $list.empty();
        found.forEach(function (item, index) {
            var id = String(config.itemId(item));
            var isChosen = chosen.indexOf(id) !== -1;

            var $row = $('<button>', {
                type: 'button',
                'class': 'sr-picker-row' + (index === cursor ? ' is-cursor' : ''),
                role: 'option',
                'data-picker-id': id,
                'aria-selected': isChosen ? 'true' : 'false',
            });
            $('<span>', { 'class': 'sr-picker-check', html: isChosen ? '<i class="fa fa-check" aria-hidden="true"></i>' : '' }).appendTo($row);
            // text:, never html: -- every one of these is user-authored.
            $('<span>', { 'class': 'sr-picker-num', text: config.itemNumber(item) }).appendTo($row);
            $('<span>', { 'class': 'sr-picker-name', text: config.itemName(item) }).appendTo($row);
            // The hover: identity on the first line, then what the row is
            // actually FOR. A truncated one-line name and a bare number are
            // both unreadable to anyone who doesn't know the catalogue by
            // heart, and the description is the thing that settles "is this
            // the control I mean". On the whole row rather than the name span,
            // so the hover target is the thing the eye is already on.
            //
            // A native title, deliberately: it costs nothing, it survives
            // inside a scrolling pane and a stacked modal, and the text is
            // plain by the time it gets here (control_roster_description(),
            // includes/compliance_grid.php).
            var hover = config.itemHover ? config.itemHover(item) : '';
            if (hover) {
                $row.attr('title', hover);
            }
            $list.append($row);
        });

        if (!found.length) {
            $('<div>', { 'class': 'sr-picker-empty', text: config.emptyText }).appendTo($list);
        }

        $count.text(found.length ? String(found.length) : '0');
        $scope.text(currentScopeLabel());
    }

    // Names the set being searched, so a search that finds nothing is
    // self-explaining rather than mysterious.
    function currentScopeLabel() {
        var deepest = null;
        config.facets.forEach(function (facet) {
            var value = facetState[facet.key];
            if (value !== null) {
                deepest = $('#' + facet.container)
                    .find('.sr-picker-facet[data-picker-value="' + value + '"] .sr-picker-facet-label')
                    .text();
            }
        });
        return deepest || config.allScopeText;
    }

    function renderSelected() {
        $selected.empty();

        if (!chosen.length) {
            $('<div>', { 'class': 'sr-picker-empty', text: config.nothingSelectedText }).appendTo($selected);
        }

        // Roster order, not click order: a stable list is easier to re-read
        // than one that reshuffles as you work.
        items.forEach(function (item) {
            var id = String(config.itemId(item));
            if (chosen.indexOf(id) === -1) {
                return;
            }
            var $chip = $('<span>', { 'class': 'sr-picker-chip' });
            $('<span>', { 'class': 'sr-picker-chip-num', text: config.itemNumber(item) || config.itemName(item) }).appendTo($chip);
            $('<button>', {
                type: 'button',
                'class': 'sr-picker-chip-remove',
                'data-picker-remove': id,
                'aria-label': config.removeLabel + ' ' + config.itemNumber(item),
                html: '&times;',
            }).appendTo($chip);
            $selected.append($chip);
        });

        $selectedCount.text(chosen.length ? String(chosen.length) : '');
    }

    function renderAll() {
        renderFacets();
        renderList();
        renderSelected();
    }

    function toggle(id) {
        var index = chosen.indexOf(String(id));
        if (index === -1) {
            chosen.push(String(id));
        } else {
            chosen.splice(index, 1);
        }
        renderList();
        renderSelected();
    }

    function setFacet(facetKey, value) {
        facetState[facetKey] = (facetState[facetKey] === value) ? null : value;

        // A later facet that no longer has anything behind it would strand the
        // list on an empty result the user can't explain, so it clears with the
        // facet that emptied it.
        var passed = false;
        config.facets.forEach(function (facet) {
            if (facet.key === facetKey) {
                passed = true;
                return;
            }
            if (!passed || facetState[facet.key] === null) {
                return;
            }
            var stillThere = itemsBefore(facet.key).some(function (item) {
                return facet.itemValues(item).indexOf(facetState[facet.key]) !== -1;
            });
            if (!stillThere) {
                facetState[facet.key] = null;
            }
        });

        cursor = 0;
        renderAll();
    }

    $modal.on('click', '.sr-picker-facet', function () {
        var $button = $(this);
        if ($button.hasClass('is-empty')) {
            return;
        }
        var raw = $button.attr('data-picker-value');
        setFacet($button.attr('data-picker-facet'), (raw === '' || raw === undefined) ? null : parseInt(raw, 10));
    });

    $modal.on('click', '.sr-picker-clear', function () {
        facetState[$(this).attr('data-picker-clear')] = null;
        cursor = 0;
        renderAll();
    });

    $modal.on('click', '.sr-picker-row', function () {
        cursor = $(this).index();
        toggle($(this).attr('data-picker-id'));
    });

    $modal.on('click', '.sr-picker-chip-remove', function () {
        toggle($(this).attr('data-picker-remove'));
    });

    $search.on('input', function () {
        cursor = 0;
        renderList();
    });

    // Type-then-Enter is the fast path for anyone who knows the number, so the
    // list is drivable without leaving the search box.
    $search.on('keydown', function (event) {
        var found = visibleItems();
        if (event.key === 'ArrowDown') {
            cursor = Math.min(cursor + 1, found.length - 1);
            renderList();
            scrollCursorIntoView();
            event.preventDefault();
        } else if (event.key === 'ArrowUp') {
            cursor = Math.max(cursor - 1, 0);
            renderList();
            scrollCursorIntoView();
            event.preventDefault();
        } else if (event.key === 'Enter') {
            // Enter in a dialog would otherwise submit the form behind it.
            event.preventDefault();
            if (found[cursor]) {
                toggle(config.itemId(found[cursor]));
                scrollCursorIntoView();
            }
        }
    });

    function scrollCursorIntoView() {
        var row = $list.find('.is-cursor')[0];
        if (row && row.scrollIntoView) {
            row.scrollIntoView({ block: 'nearest' });
        }
    }

    $('#' + config.commitId).on('click', function () {
        if (typeof onCommit === 'function') {
            onCommit(chosen.slice());
        }
        $modal.modal('hide');
    });

    $modal.on('shown.bs.modal', function () {
        $search.trigger('focus');
    });

    // This dialog opens FROM another modal, and the app pins every modal and
    // backdrop to the same z-index (theme CSS), so Bootstrap's second backdrop
    // lands *under* the first modal: the form behind stays undimmed and reads
    // as still-interactive. The body class is what the stylesheet keys the
    // stacking fix off -- scoped to this state rather than applied to every
    // stacked modal in the app.
    $modal.on('show.bs.modal', function () {
        $('body').addClass('sr-picker-open');
    });

    // Bootstrap appends this dialog's backdrop to <body>, but the app pins
    // every backdrop to one z-index, so the new one lands UNDER the modal that
    // launched the picker -- leaving that form bright and looking clickable
    // while a dialog waits on it. Raise the newest backdrop once it exists.
    // Done here rather than in CSS: the two backdrops are not adjacent
    // siblings (the modals sit between them), so no selector can name "the
    // second one". Bootstrap removes the element on hide, so there is nothing
    // to undo.
    $modal.on('shown.bs.modal', function () {
        $('.modal-backdrop').last().css('z-index', 1060);
    });

    $modal.on('hidden.bs.modal', function () {
        $('body').removeClass('sr-picker-open');
        // Bootstrap removes .modal-open from <body> when ANY modal closes, so
        // the still-open modal underneath loses its scroll lock. Put it back.
        if ($('.modal.show').length) {
            $('body').addClass('modal-open');
        }
    });

    return {
        open: function (options) {
            items = options.items || [];
            // A copy, so edits inside the dialog can be abandoned.
            chosen = (options.chosen || []).map(String);
            config.facets.forEach(function (facet) {
                facetState[facet.key] = null;
            });
            cursor = 0;
            onCommit = options.onCommit || null;
            $search.val('');
            renderAll();
            $modal.modal('show');
        },
    };
}

// The picker instance for the control roster. Created lazily on first open --
// the modal only exists on pages that render a test modal, and creating it
// eagerly would bind handlers to nothing everywhere else.
var controlPicker = null;

function getControlPicker() {
    if (!controlPicker) {
        controlPicker = createFacetedPicker({
            modalId: 'control-picker',
            searchId: 'control-picker-search',
            listId: 'control-picker-list',
            selectedId: 'control-picker-selected',
            countId: 'control-picker-count',
            selectedCountId: 'control-picker-selected-count',
            scopeId: 'control-picker-scope',
            commitId: 'control-picker-commit',
            facets: [
                {
                    key: 'framework',
                    container: 'control-picker-frameworks',
                    // A control maps into SEVERAL frameworks, so this is a
                    // membership test, not a single value -- which is also why
                    // framework/family is a filter rather than a tree.
                    itemValues: function (control) { return (control.frameworks || []).map(Number); },
                },
                {
                    key: 'family',
                    container: 'control-picker-families',
                    itemValues: function (control) { return [Number(control.family || 0)]; },
                },
            ],
            itemId: function (control) { return control.id; },
            itemNumber: function (control) { return control.control_number || ''; },
            itemName: function (control) { return controlDisplayName(control); },
            itemHover: function (control) {
                // "BCD-11: Data Backups" -- a colon, the separator this app uses
                // between a control's id and its name everywhere else (it is
                // literally how the names are stored). An em dash read as part
                // of the sentence rather than as punctuation between two things.
                //
                // The blank line is doing real work: a native title tooltip
                // can't be styled, so the only way to make the first line read
                // as a heading rather than as the description's opening clause
                // is to put air between them.
                var heading = controlHeadingLabel(control);
                return control.description ? (heading + '\n\n' + control.description) : heading;
            },
            searchText: function (control) { return (control.control_number || '') + ' ' + controlDisplayName(control); },
            emptyText: (typeof _lang !== 'undefined' && _lang['NoControlsMatchFilters']) || 'Nothing here matches.',
            nothingSelectedText: (typeof _lang !== 'undefined' && _lang['NoControlsSelectedYet']) || 'Nothing selected yet.',
            allScopeText: (typeof _lang !== 'undefined' && _lang['AllControls']) || 'All controls',
            removeLabel: (typeof _lang !== 'undefined' && _lang['Remove']) || 'Remove',
        });
    }

    return controlPicker;
}

/**
 * A control as one line: "BCD-11: Data Backups".
 *
 * The colon is the app's own convention -- control names are STORED that way
 * ("BCD-11: Data Backups"), so rebuilding the label with a colon reproduces
 * exactly what a compliance user is used to reading, while still letting the
 * number render as its own column/chip where there is room for one.
 */
function controlHeadingLabel(control) {
    var name = controlDisplayName(control);

    return control.control_number ? (control.control_number + ': ' + name) : name;
}

/**
 * A control's name with its number stripped off the front.
 *
 * Control names are STORED with the number already inside them --
 * short_name is literally "BCD-11: Data Backups" -- so a row that shows the
 * number in its own column and then the raw name reads "BCD-11  BCD-11: Data
 * Backups". Stripping at render time keeps stored data untouched: no
 * migration, and a name that doesn't carry the prefix is returned unchanged.
 */
function controlDisplayName(control) {
    var name = control.short_name || control.long_name || '';
    var number = control.control_number || '';

    if (number && name.indexOf(number) === 0) {
        return $.trim(name.slice(number.length).replace(/^[\s:—-]+/, '')) || name;
    }

    return name;
}

// Applies `values` (an array of control-id strings, or falsy/empty for
// "leave nothing selected") to a control <select>, then re-renders the
// chips that show it.
//
// The <select> stays the form's value -- the picker is UI over it, not a
// replacement for it -- so submission, validation and resetForm() are
// unchanged from when a multiselect drew this field.
function applyControlSelection($select, values) {
    $select.val((values || []).map(String));
    renderControlChips($select);
}

/**
 * The Control Name field at rest: chips for what's chosen, and one button
 * that opens the picker.
 *
 * Replaces the bootstrap-multiselect that used to draw this field. Its
 * dropdown rendered all ~1,500 controls into a 280px menu inside a 640px
 * modal -- three lines per name, five readable at a time, floating over the
 * field beneath it. The chips box itself is unchanged (it is the same
 * .sr-chips-field the other roster fields use), so only the OPENING gesture
 * differs between this field and its neighbours.
 */
function renderControlChips($select) {
    if (!$select.length) {
        return;
    }

    var $field = $select.next('.sr-chips-field');
    if (!$field.length) {
        $field = $('<div>', { 'class': 'sr-chips-field' }).insertAfter($select);
    }

    $field.empty();

    var selectedIds = ($select.val() || []).map(String);
    selectedIds.forEach(function (id) {
        var control = controlsById[id];
        if (!control) {
            return;
        }
        // The chip shows the NUMBER (short, and what a compliance user cites),
        // with the full name on hover -- a number alone is unreadable to anyone
        // who doesn't already know the catalogue by heart.
        var fullLabel = controlHeadingLabel(control);
        var $chip = $('<span>', { 'class': 'sr-chip', title: fullLabel });
        // text:, not html: -- control numbers and names are user-authored.
        $('<span>', { text: control.control_number || controlDisplayName(control) }).appendTo($chip);
        $('<button>', {
            type: 'button',
            'class': 'sr-chip-remove',
            'data-control-id': id,
            'aria-label': ((typeof _lang !== 'undefined' && _lang['Remove']) || 'Remove') + ' ' + (control.control_number || ''),
            html: '&times;',
        }).appendTo($chip);
        $field.append($chip);
    });

    $('<button>', {
        type: 'button',
        'class': 'sr-chips-add',
        'data-control-picker-for': $select.attr('id'),
        text: (typeof _lang !== 'undefined' && _lang['AddOrRemoveControls']) || 'Add or remove controls…',
    }).appendTo($field);
}

// Removing a chip is a direct edit of the field, not of the picker's working
// copy -- it commits immediately, the way removing a chip did before.
$(document).on('click', '.sr-chips-field .sr-chip-remove[data-control-id]', function () {
    var $select = $(this).closest('.sr-chips-field').prev('select');
    var removed = String($(this).attr('data-control-id'));
    applyControlSelection($select, ($select.val() || []).filter(function (id) {
        return String(id) !== removed;
    }));
});

$(document).on('click', '.sr-chips-add[data-control-picker-for]', function () {
    var $select = $('#' + $(this).attr('data-control-picker-for'));
    var picker = getControlPicker();
    if (!picker) {
        return;
    }

    picker.open({
        items: controlsRoster,
        chosen: ($select.val() || []).map(String),
        onCommit: function (ids) {
            applyControlSelection($select, ids);
        },
    });
});

// Rebuilds $select's <option>s from controlsRoster -- the <select> is still
// the form's value, so every control needs an option even though the picker
// draws the UI -- then restores a selection:
//
//   1. Whatever was selected before this rebuild, or
//   2. If $modal has a pending selection queued via .data(pendingKey) --
//      set by openAddTestModal() / openTestForEdit() when they tried to
//      select a value before the roster had loaded -- that queued value
//      instead, consuming it in the process.
//
// Guarded to no-op when $select doesn't exist on the current page/modal --
// e.g. #add_test_control only exists on compliance/index.php, and
// #edit_test_control only on pages that render display_update_test_modal()
// (index.php + audit_initiation.php).
function populateControlSelectOptions($select, $modal, pendingKey) {
    if (!$select.length) {
        return;
    }

    var previousValue = ($select.val() || []).map(String);

    $select.empty();
    controlsRoster.forEach(function (control) {
        var label = controlHeadingLabel(control);
        // The <option> text still carries both, for the one consumer that reads
        // it as text: a native form submit with JS disabled, and any test
        // asserting on the select itself.
        $('<option>', { value: control.id, text: label }).appendTo($select);
    });

    var pending = $modal.data(pendingKey);
    $modal.removeData(pendingKey);
    applyControlSelection($select, (pending !== undefined && pending !== null) ? pending : previousValue);
}

// Each call below no-ops cleanly via the $select.length guard when its target
// select isn't present on the current page, so this is safe to call
// unconditionally from every compliance.js page's ready handler.
function populateControlSelect() {
    populateControlSelectOptions($('#add_test_control'), $('#test--add'), 'pendingControlPrefill');
    populateControlSelectOptions($('#edit_test_control'), $('#test--update'), 'pendingControlSelection');
}

/**
 * Button label for a multiselect whose selection is shown as chips.
 *
 * bootstrap-multiselect's default label lists the selected values (or "N
 * selected"), which duplicates the chips underneath -- the same information
 * twice, in two shapes, one of them truncated. With chips carrying the
 * selection, the button's only remaining job is to open the picker, so it
 * says so.
 *
 * A constant, deliberately: buttonText's return value is rendered with .text()
 * unless enableHTML is on, and it must never carry user-authored option text
 * (see the note on renderSelectionChips below).
 */
function chipSelectButtonText() {
    return (typeof _lang !== 'undefined' && _lang['AddOrRemove']) ? _lang['AddOrRemove'] : 'Add or remove…';
}

/**
 * bootstrap-multiselect's onChange hook, used to keep the chips in step with
 * what the user picks in the dropdown.
 *
 * A user's pick does NOT surface as a change event on the underlying <select>
 * that a handler bound there can see -- the widget toggles its own checkbox,
 * syncs the option, and stops the click from bubbling -- so listening on the
 * select (or delegating on the container) misses it and the chips go stale.
 * This is the plugin's own supported hook; it hands us the <option> that
 * changed, from which the select is one .closest() away.
 */
function chipSelectOnChange($option) {
    if (!$option || !$option.length) {
        return;
    }
    renderSelectionChips($option.closest('select'));
}

/**
 * Renders a bootstrap-multiselect's current selection as removable chips
 * beneath its button, the way the approved Define Tests artifact shows
 * controls / approvers / teams / stakeholders.
 *
 * The chips are built HERE rather than inside the widget's own button on
 * purpose. bootstrap-multiselect can only render markup in its button when
 * `enableHTML: true`, and that flag ALSO switches its option-label rendering
 * from .text() to .html() (see createOptionValue/createDivider in
 * dist/js/bootstrap-multiselect.js) -- and those labels are user-authored
 * (control short_names, user names). Turning it on to win a visual detail
 * would hand every control name an HTML injection point. Chips built here go
 * in via jQuery `text:`, so a control named `<img onerror=…>` renders as
 * characters.
 *
 * Keeps the widget as the source of truth: a chip's × calls back into
 * .multiselect('deselect'), so every existing caller (rebuild, select,
 * deselectAll, the roster-race queue) keeps working untouched.
 */
function renderSelectionChips($select) {
    // Every line below reads ONE widget and rewrites ONE field, so a set with
    // more than one select has to be split first. `[name='team[]']` matches
    // both the Add and the Edit modal's Teams select (they share a name, and
    // unlike stakeholders/approvers they aren't split into _add/_edit ids), so
    // this is the normal case, not a defensive one. Left un-split, the widget
    // came from the FIRST select while $container/$field covered BOTH -- so the
    // dedupe below deleted the Edit modal's live .btn-group (handlers and all),
    // leaving a dead Teams field where clicking a team did nothing.
    if ($select.length > 1) {
        $select.each(function () {
            renderSelectionChips($(this));
        });
        return;
    }

    if (!$select.length || !$select.data('multiselect')) {
        return;
    }

    var $container = $select.closest('.multiselect-native-select');
    if (!$container.length) {
        return;
    }

    // One bordered box that IS the field: chips first, then the widget's own
    // button as the trailing "Add or remove…" affordance -- the artifact's
    // .chips control, where the selection lives inside the input rather than
    // in a strip underneath it.
    //
    // The button is MOVED into the box rather than duplicated: it stays the
    // real bootstrap-multiselect trigger, and its .dropdown-menu stays its
    // sibling inside .btn-group, so positioning and every existing widget
    // call keep working. Chips and the trigger are siblings, so nothing
    // nests an interactive element inside a <button>.
    var $field = $container.find('> .sr-chips-field');
    if (!$field.length) {
        $field = $('<div>', { 'class': 'sr-chips-field' }).appendTo($container);
    }

    // Use the CURRENT widget instance's own button group, and drop any others
    // in this container. A page-wide `$('select[multiple]').multiselect(...)`
    // in includes/display.php runs on this page too, so a select can end up
    // initialized twice -- which leaves a second, orphaned trigger button
    // rendering beside the live one (visible as a duplicated "Add or remove…"
    // on the Edit modal's Teams field). .data('multiselect') always points at
    // the most recent instance, so its container is the one still wired up.
    var widget = $select.data('multiselect');
    var $btnGroup = (widget && widget.$container && widget.$container.length)
        ? widget.$container
        : $container.find('.btn-group').last();
    $container.find('.btn-group').not($btnGroup).remove();

    $field.find('> .sr-chip').remove();

    $select.find('option:selected').each(function () {
        var value = $(this).val();
        if (value === '' || value === null) {
            return;
        }

        // `text:` (never html) -- see the note above.
        var $chip = $('<span>', { 'class': 'sr-chip', text: $(this).text() });

        $('<button>', {
            type: 'button',
            'class': 'sr-chip-x',
            'aria-label': (typeof _lang !== 'undefined' && _lang['Remove']) ? _lang['Remove'] : 'Remove',
        })
            .append($('<i>', { 'class': 'fa fa-xmark', 'aria-hidden': 'true' }))
            .on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $select.multiselect('deselect', value);
                $select.trigger('change');
                renderSelectionChips($select);
            })
            .appendTo($chip);

        // Chips before the trigger, so "Add or remove…" always trails the
        // current selection the way the artifact shows it.
        if ($btnGroup.length) {
            $chip.insertBefore($btnGroup);
        } else {
            $chip.appendTo($field);
        }
    });

    // Pull the trigger into the box (idempotent -- append of an element
    // already there is a no-op move).
    if ($btnGroup.length) {
        $field.append($btnGroup);
    }
}

/**
 * Wires renderSelectionChips() to a select and keeps it in sync. Safe to call
 * more than once (the change handler is namespaced and rebound), and safe to
 * call with a set that matches several selects -- each is wired to its OWN
 * field, so a change on the Add modal's Teams select never re-renders the Edit
 * modal's (see the note in renderSelectionChips).
 */
function attachSelectionChips($selects) {
    if (!$selects.length) {
        return;
    }

    $selects.each(function () {
        var $select = $(this);

        // Programmatic paths (openTestForEdit, applyControlSelection, resetForm)
        // change the <select> itself.
        $select.off('change.srchips').on('change.srchips', function () {
            renderSelectionChips($select);
        });

        renderSelectionChips($select);
    });
}

function fetchControlRoster() {
    // GET -- csrf-magic only gates form-urlencoded/FormData POST bodies,
    // so no CSRF-TOKEN header is needed here (see csrfHeaders() above).
    return $.ajax({
        type: 'GET',
        url: BASE_URL + '/api/v2/compliance/control_roster',
    });
}

function loadControlRoster() {
    fetchControlRoster()
        .done(function (result) {
            controlsRoster = (result && result.data) ? result.data : [];
            controlsById = {};
            controlsRoster.forEach(function (control) {
                controlsById[String(control.id)] = control;
            });
            populateControlSelect();
        });
    // No .fail() handler -- both modals' control selects just stay empty
    // on failure (blocking their now-required submit until a reload
    // succeeds); the per-control "+ Add test" button's own click still
    // works (it passes the control id straight through, no roster
    // lookup needed) even though the resulting Add modal's select can't
    // yet be prefilled from a roster that never loaded.
}

$(document).on('click', '.add-test', function() {
    openAddTestModal($(this).data('control-id'));
});

$(document).on('click', '.delete-row', function() {
    var testId = $(this).data('id');
    $('#test--delete [name=test_id]').val(testId);

    // Say what else this touches. Tests are shared across controls now, so a
    // delete launched from one control's row can remove the test from controls
    // the user can't see from here -- the row itself says "4 Frameworks", never
    // "4 controls". Read the ROW's data-control-count (buildTestRow()): it is
    // rendered for every row, whereas the unlink button exists only for
    // edit_tests holders -- so a delete_tests-only user would otherwise read
    // "1 control" and get no warning on the one action they CAN take.
    var controlCount = parseInt($(this).closest('tr').data('control-count'), 10) || 1;
    var $scope = $('#test-delete-scope');
    if (controlCount > 1 && window._lang && _lang['DeleteTestUsedByNControls']) {
        $scope.text(String(_lang['DeleteTestUsedByNControls']).replace('{n}', controlCount)).show();
    } else {
        $scope.text('').hide();
    }

    $('#test--delete').modal('show');
});

$(function(){

    // Add test form event -- AJAX create (Define Tests redesign, Issue 6):
    // replaces the modal's old native full-page-reload POST with an in-place
    // submit so the Define Tests grid's toolbar search/filter/quick-chip/
    // Coverage state (js/simplerisk/pages/compliance-define-tests.js) survives
    // adding a test. Mirrors #update-test-form's own AJAX submit handler below
    // (same validation shape, same FormData/contentType:false transport).
    //
    // Deliberately a click handler on the button (like the pre-AJAX version),
    // NOT a 'submit' handler on the form: event.preventDefault() on the
    // button's click cancels the browser's default action (submitting the
    // form) before a 'submit' event is ever dispatched. That matters here
    // because prevent_form_double_submit_script(['test-new-form', ...])
    // (compliance/index.php) binds its own submit-disables-the-buttons
    // handler directly on #test-new-form; a 'submit' handler on the form
    // fires *after* that direct-bound handler regardless of what our
    // validation below decides, permanently disabling Add on every failed
    // validation. Cancelling at the click stage means 'submit' never fires
    // at all, so that handler never runs -- our own disable/enable around
    // the AJAX call below is the only button-state management in play.
    //
    // #add_test is type="button" (compliance/index.php), not type="submit" --
    // it is never the form's implicit-submission default button, so it can
    // never trigger a native form POST on its own (by click or otherwise).
    // Enter-key submission is handled separately below by a 'keydown'
    // listener on #test-new-form that re-triggers this same click handler,
    // rather than by relying on any native implicit-submission behavior.
    $("#add_test").on("click", function(event) {
        event.preventDefault();

        let $form = $("#test-new-form");

        // Phase 4a (common tests): this check IS the client-side rule for the
        // control field, and it runs before checkAndSetValidation() rather
        // than after.
        //
        // #add_test_control carries no `required` attribute (see the note on
        // the markup in compliance/index.php): the picker hides that <select>,
        // and a browser won't report a constraint violation on a control it
        // can't focus -- it aborted the submit instead, so this handler never
        // ran and the button did nothing, silently. Nor could
        // checkAndSetValidation() have helped: all it does with an invalid
        // field is add .error and focus it, and neither is visible on an
        // element the user can't see. A message is the only thing that works
        // here, so it goes first.
        //
        // The server (createTest(), includes/api.php) enforces the same >=1
        // rule authoritatively.
        if (($('#add_test_control', $form).val() || []).length === 0) {
            showAlertFromMessage(_lang["AtLeastOneControlRequired"]);
            return;
        }

        // Check if the required fields have empty / trimmed empty values
        if (!checkAndSetValidation("#test-new-form")) {
            return;
        }

        let scheduleType = $("[name='schedule_type']:checked", $form).val();

        if (scheduleType !== 'manual') {

            let audit_initiation_offset = $("[name='audit_initiation_offset']", $form).val();
            let test_frequency = $("[name='test_frequency']", $form).val();

            if (audit_initiation_offset !== '' && audit_initiation_offset < 0) {

                showAlertFromMessage(_lang["AuditInitiationOffsetMustBeANonNegativeValue"]);
                return;

            } else if (scheduleType === 'interval' && audit_initiation_offset !== '' && test_frequency !== '' && Number(audit_initiation_offset) > Number(test_frequency)) {

                showAlertFromMessage(_lang["AuditInitiationOffsetMustBeLessThanOrEqualToTestFrequency"]);
                return;

            }
        }

        // Automated (Calendar) schedules must not anchor to a past date -- a past
        // anchor would immediately auto-schedule an overdue first occurrence.
        // Manual tests are the path for backdated scheduling. This is a UX
        // shortcut; the server enforces the same rule authoritatively
        // (createTest(), includes/api.php).
        if (scheduleType === 'calendar') {
            let anchorIso = parseCalendarAnchorToIso($("[name='cadence_anchor_date']", $form).val());
            if (anchorIso && anchorIso < moment().format('YYYY-MM-DD')) {
                showAlertFromMessage(_lang["AnchorDateMustBeTodayOrLater"]);
                return;
            }
        }

        $form.find('.schedule-exceptions-value').val(JSON.stringify(buildScheduleExceptionsPayload($form.closest('.modal'))));

        let formData = new FormData($form[0]);

        // createTest() (POST /api/v2/compliance/tests, includes/api.php) expects
        // 'teams[]' (array) and a single comma-joined 'additional_stakeholders'
        // string; this form's fields are named 'team[]'/'additional_stakeholders_add[]'
        // to match the legacy native-POST contract (compliance/index.php's old
        // add_test handler). Remap here rather than changing either contract.
        let teamValues = formData.getAll('team[]');
        formData.delete('team[]');
        teamValues.forEach(function(value) {
            formData.append('teams[]', value);
        });

        let stakeholderValues = formData.getAll('additional_stakeholders_add[]');
        formData.delete('additional_stakeholders_add[]');
        formData.set('additional_stakeholders', stakeholderValues.join(','));

        // Phase 3a: the approvers multiselect is named 'approvers_add[]' in
        // this modal (mirroring the additional_stakeholders_add/
        // additional_stakeholders split above) to keep its DOM id unique from
        // the Edit modal's copy for bootstrap-multiselect's init -- remap to
        // the plain 'approvers[]' createTest() (includes/api.php) expects.
        let approverValues = formData.getAll('approvers_add[]');
        formData.delete('approvers_add[]');
        approverValues.forEach(function(value) {
            formData.append('approvers[]', value);
        });

        // Phase 4a (common tests): same add/edit unique-DOM-id split as
        // approvers_add[] above -- #add_test_control is named 'controls_add[]'
        // in this modal so its id doesn't collide with the Edit modal's
        // #edit_test_control 'controls[]' copy (includes/compliance.php) --
        // remap to the plain 'controls[]' createTest() (includes/api.php)
        // expects.
        let controlValues = formData.getAll('controls_add[]');
        formData.delete('controls_add[]');
        controlValues.forEach(function(value) {
            formData.append('controls[]', value);
        });

        let $submitBtn = $('#add_test').prop('disabled', true).addClass('is-busy');

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/compliance/tests",
            data: formData,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function() {
                $submitBtn.prop('disabled', false).removeClass('is-busy');

                // A "Review & edit" of an AI suggestion stashes the source
                // proposal id on the modal (compliance-define-tests.js). Editing
                // + saving IS approving it, so consume (reject) the proposal now
                // that the real test exists -- otherwise the suggestion would
                // linger in the grid. Read + clear BEFORE modal('hide') fires its
                // own clear.
                var sourceProposalId = $('#test--add').data('sourceProposalId');
                $('#test--add').removeData('sourceProposalId');

                $('#test--add').modal('hide');
                showAlertFromMessage(_lang['TestSuccessCreated'] || '', true);

                // compliance-define-tests.js exposes reloadDefineTestsGrid once its
                // grid shell is on the page (Define Tests) -- re-fetches in place so
                // the toolbar's search/filter/quick-chip/Coverage state survives the
                // add, instead of a full page reload.
                var reloadGrid = function () {
                    if (typeof reloadDefineTestsGrid === 'function') {
                        reloadDefineTestsGrid();
                    }
                };

                if (sourceProposalId && typeof window.rejectDefineTestsProposal === 'function') {
                    // Sequence the reload AFTER the reject resolves so the redraw
                    // reflects the consumed suggestion (racing them could redraw the
                    // still-pending suggestion row). The created test stands either
                    // way; if the reject fails, surface it so the user can dismiss
                    // the lingering suggestion manually rather than leaving it silent.
                    window.rejectDefineTestsProposal(sourceProposalId)
                        .fail(function () {
                            showAlertFromMessage(_lang['SuggestionDismissFailed'] || '', false);
                        })
                        .always(reloadGrid);
                } else {
                    reloadGrid();
                }
            }
        })
        .fail(function(xhr, textStatus) {
            $submitBtn.prop('disabled', false).removeClass('is-busy');

            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertFromMessage(xhr.responseJSON.status_message, false);
                } else {
                    showAlertFromMessage(_lang['RequestFailed'] || '', false);
                }
            }
        });
    });

    // Belt-and-suspenders: if a "Review & edit" is cancelled (the modal closes
    // without a successful create), drop the stashed AI-suggestion source id so
    // it can't attach to a later, unrelated Add. The success path clears it
    // before hide; this covers every other way the modal closes.
    $(document).on('hidden.bs.modal', '#test--add', function () {
        $('#test--add').removeData('sourceProposalId');
    });

    // Add test form Enter-key submit (Define Tests redesign, Issue 6 fix).
    // #add_test is type="button" (compliance/index.php), so it is no longer
    // #test-new-form's implicit-submission default button -- per the HTML
    // implicit-submission algorithm, a button-less form only submits on Enter
    // when exactly one field in it "blocks implicit submission", and this
    // form has several (Test Name, Last Test Date, Test Frequency, ...), so a
    // native Enter keypress in this form can no longer submit it at all. That
    // fixed the old bug (Enter used to bypass the click handler above and hit
    // the legacy full-page-reload $_POST['add_test'] handler, now removed
    // from compliance/index.php) but would otherwise leave Enter doing
    // nothing. Route it through the exact same AJAX path as a click, rather
    // than depending on any native form-submission behavior at all.
    $(document).on('keydown', '#test-new-form', function(event) {
        if (event.key !== 'Enter' && event.keyCode !== 13) {
            return;
        }

        var $target = $(event.target);

        // Let these widgets keep handling Enter themselves instead of
        // hijacking it into a form submit:
        //  - <textarea> (the WYSIWYG-backed objective/test_steps/
        //    expected_results source textareas -- Enter inserts a newline)
        //  - the Tags field's Selectize widget (Enter commits/creates a tag
        //    chip -- see createTagsInstance() above)
        //  - an open date-range-picker calendar dropdown (Enter should
        //    confirm the picker's own highlighted date, not submit the form)
        if ($target.is('textarea') ||
            $target.closest('.selectize-control').length ||
            $('.daterangepicker:visible').length) {
            return;
        }

        var $submitBtn = $('#add_test');
        if ($submitBtn.prop('disabled')) {
            return;
        }

        event.preventDefault();
        $submitBtn.trigger('click');
    });

    // Update test form event
    // This can be used for the following pages
    // the Compliance > Define Tests page and 
    // the Compliance > Initiate Audits page
    $(document).on("submit", "#update-test-form", function(event) {
        event.preventDefault();

        // Same >=1 rule, same message, and the same before-not-after ordering
        // as the Add form above -- see the note there for why an empty control
        // set has to be caught here rather than left to checkAndSetValidation().
        //
        // A test can't end up control-less either way: an empty control set
        // means "keep the existing mapping" to update_framework_control_test()
        // (includes/compliance.php), so the data was safe regardless. What was
        // missing was telling the user why their save didn't happen.
        if (($('#edit_test_control', this).val() || []).length === 0) {
            showAlertFromMessage(_lang["AtLeastOneControlRequired"]);
            return;
        }

        // Check if the required fields have empty / trimmed empty values
        if (!checkAndSetValidation(this)) {
            return;
        }

        let scheduleType = $("[name='schedule_type']:checked", this).val();

        if (scheduleType !== 'manual') {

            let audit_initiation_offset = $("[name='audit_initiation_offset']", this).val();
            let test_frequency = $("[name='test_frequency']", this).val();

            if (audit_initiation_offset !== '' && audit_initiation_offset < 0) {

                showAlertFromMessage(_lang["AuditInitiationOffsetMustBeANonNegativeValue"]);
                return;

            } else if (scheduleType === 'interval' && audit_initiation_offset !== '' && test_frequency !== '' && Number(audit_initiation_offset) > Number(test_frequency)) {

                showAlertFromMessage(_lang["AuditInitiationOffsetMustBeLessThanOrEqualToTestFrequency"]);
                return;

            }
        }

        // Automated (Calendar) schedules must not anchor to a past date -- a past
        // anchor would immediately auto-schedule an overdue first occurrence.
        // Manual tests are the path for backdated scheduling. This is a UX
        // shortcut; the server enforces the same rule authoritatively.
        if (scheduleType === 'calendar') {
            let anchorIso = parseCalendarAnchorToIso($("[name='cadence_anchor_date']", this).val());
            if (anchorIso && anchorIso < moment().format('YYYY-MM-DD')) {
                showAlertFromMessage(_lang["AnchorDateMustBeTodayOrLater"]);
                return;
            }
        }

        // Variable for indicating where the update test form is submitted from
        // It can be either the Compliance > Define Tests page or the Compliance > Initiate Audits page
        // If the page is the Compliance > Define Tests page, the value is "define_tests"
        // If the page is the Compliance > Initiate Audits page, the value is "audit_initiation"
        let where = $('[name=where]', this).val();

        $("[name='schedule_exceptions']", this).val(JSON.stringify(buildScheduleExceptionsPayload($(this).closest('.modal'))));

        let form = new FormData($(this)[0]);

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/compliance/update_test",
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(result){
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }

                $('#test--update').modal('hide');

                // Re-fetch the grid in place rather than reloading the page, so
                // the user keeps the view they were working in: their filters,
                // the shareable URL those filters produced, their page position
                // and their scroll offset. Mirrors what the Add handler above
                // already does (Issue 6).
                //
                // The fallback matters: this same modal is rendered on
                // compliance/audit_initiation.php, which loads this file but NOT
                // compliance-define-tests.js, so there is no in-place grid to
                // refresh there and a reload is still the only way to show the
                // change.
                if (typeof reloadDefineTestsGrid === 'function') {
                    reloadDefineTestsGrid();
                } else {
                    location.reload();
                }
            }
        })
        .fail(function(xhr, textStatus) {
            if (!retryCSRF(xhr, this)) {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });

    // Event handler when clicking
    // Fetch a test's definition and open the "update test" modal populated with
    // it. Shared by the edit-test / test-name click handlers and the ?test_id=
    // deep-link (e.g. the dashboard's Upcoming Tests name link). The endpoint
    // enforces compliance permission + team-separation on the test itself.
    function openTestForEdit(test_id) {

        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/compliance/test?id=" + test_id,
            success: function(result) {
                let data = result['data'];
                let modal = $('#test--update');
                
                $('[name=test_id]', modal).val(data['id']);

                // Phase 4a (common tests): #edit_test_control holds N controls,
                // fed by the same client-side roster as the Add modal's
                // #add_test_control (loadControlRoster()). That roster fetch is
                // independent of this GET and may not have resolved -- and
                // therefore may not have built the <option>s -- yet. When it
                // hasn't, queue the desired selection on the modal instead of
                // setting a value that has no matching option (a <select> drops
                // one silently, which would open the modal showing none of the
                // test's own controls); populateControlSelect() re-applies it
                // once the roster arrives.
                let $editControlSelect = $('#edit_test_control', modal);
                if (controlsRoster.length) {
                    applyControlSelection($editControlSelect, data['controls']);
                    modal.removeData('pendingControlSelection');
                } else {
                    modal.data('pendingControlSelection', data['controls']);
                }

                $('[name=tester]', modal).val(data['tester']);
                $('#additional_stakeholders', modal).multiselect('deselectAll', false);
                $('#additional_stakeholders', modal).multiselect('select', data['additional_stakeholders']);

                $("[name='team[]']", modal).multiselect('deselectAll', false);
                $("[name='team[]']", modal).multiselect('select', data['teams']);

                // bootstrap-multiselect's programmatic select() does not fire a
                // change event on the underlying <select>, so the chips (which
                // now carry the visible selection -- the button says only "Add
                // or remove…") have to be re-rendered explicitly here. Without
                // this an edited test opens showing none of its own values.
                renderSelectionChips($('#additional_stakeholders', modal));
                renderSelectionChips($("[name='team[]']", modal));
                renderSelectionChips($('#approvers', modal));

                $('[name=test_frequency]', modal).val(data['test_frequency']);

                $('[name=last_date]', modal).val(data['last_date']);
                $('[name=last_date]', modal).initAsDatePicker({maxDate: moment().format(default_date_format)});

                let scheduleType = data['schedule_type'] || 'calendar';

                if (scheduleType === 'calendar') {

                    // The server computes next_date from the cadence engine for
                    // Calendar schedules; just reflect it (the field is hidden
                    // in this mode).
                    $('[name=next_date]', modal).val(data['next_date'] || '');

                } else {

                    // Mirror what the server will actually store
                    // (resolve_interval_next_date(), includes/audit_schedule.php):
                    // last date + frequency when there is a cadence to project
                    // from, otherwise the stored date.
                    //
                    // Critically, a result in the PAST is shown as-is. This block
                    // used to substitute today whenever the date had already
                    // passed -- the client twin of the server's clamp -- so the
                    // modal displayed "due today" for a test that was months
                    // late, and then posted that back, which is how an unrelated
                    // edit erased the overdue state.
                    let frequency = parseInt(data['test_frequency'], 10) || 0;
                    if (data['last_date'] && frequency > 0) {
                        $('[name=next_date]', modal).val(
                            moment(data['last_date'], default_date_format).add(frequency, 'days').format(default_date_format)
                        );
                    } else {
                        $('[name=next_date]', modal).val(data['next_date'] || '');
                    }

                    // The picker floors at the last test date -- a test can't be
                    // due before the run it follows, which the server enforces
                    // too ($lang['InvalidNextTestDateLastTestDateOrder']). It does
                    // NOT floor at today: a due date in the past is exactly what
                    // an overdue test has, and blocking it here would stop anyone
                    // recording one.
                    let min_next_date = data['last_date'] ? data['last_date'] : null;
                    $('[name=next_date]', modal).initAsDatePicker(min_next_date ? {minDate: min_next_date} : {});
                }

                // Lead-in days is optional (blank = no automatic initiation) and
                // shown for Interval/Calendar only; applyScheduleModeVisibility()
                // below clears it for Manual.
                $('[name=audit_initiation_offset]', modal).val(data['audit_initiation_offset'] != null ? data['audit_initiation_offset'] : '');

                $('[name=name]', modal).val(data['name']);
                $('[name=objective]', modal).val(data['objective']);
                $('[name=test_steps]', modal).val(data['test_steps']);
                $('[name=approximate_time]', modal).val(data['approximate_time']);
                $('[name=expected_results]', modal).val(data['expected_results']);

                // Phase 3a fields: test_method is a plain enum <select>, sample/
                // required_evidence are WYSIWYG-backed textareas (same round-trip
                // as objective/test_steps/expected_results above -- the source
                // textarea's value is set here, and the editor itself is pushed
                // via setEditorContent() below), and approvers is a
                // permission-filtered multiselect (same shape as
                // additional_stakeholders above).
                $('[name=test_method]', modal).val(data['test_method']);
                $('[name=sample]', modal).val(data['sample']);
                $('[name=required_evidence]', modal).val(data['required_evidence']);
                $('#approvers', modal).multiselect('deselectAll', false);
                $('#approvers', modal).multiselect('select', data['approvers']);

                $.each(data['tags'], function (i, item) {
                    $('[name=\'tags[]\']', modal).append($('<option>', { 
                        value: item,
                        text : item,
                        selected : true,
                    }));
                });
                var select = $('[name=\'tags[]\']', modal).selectize();
                var selectize = select[0].selectize;
                selectize.setValue(data['tags']);

                setEditorContent("update_objective", data['objective']);
                setEditorContent("update_test_steps", data['test_steps']);
                setEditorContent("update_expected_results", data['expected_results']);
                setEditorContent("update_sample", data['sample']);
                setEditorContent("update_required_evidence", data['required_evidence']);

                // Client-side SoD hint (Phase 3a) -- reflect the freshly
                // prefilled tester/approvers state.
                if (typeof updateSodWarning === 'function') {
                    updateSodWarning(modal);
                }

                // Schedule mode — seed the pending-exceptions map from the test's
                // persisted exceptions, populate the anchor/cadence fields, select
                // the matching schedule_type segment, then apply field visibility
                // (which triggers the occurrences preview for Calendar).
                modal.data('scheduleExceptions', data['schedule_exceptions'] || {});
                // cadence_anchor_date comes back display-formatted (like last_date/
                // next_date above); initAsDatePicker() mirrors the last_date field,
                // with a minDate of today since Calendar schedules can't anchor to
                // the past.
                $('[name=cadence_anchor_date]', modal).val(data['cadence_anchor_date'] || '');
                $('[name=cadence_anchor_date]', modal).initAsDatePicker({minDate: moment().format(default_date_format)});
                setCadencePresetFromValue(modal, data['cadence_unit'], data['cadence_interval']);
                $('[name=schedule_type][value="' + scheduleType + '"]', modal).prop('checked', true);
                applyScheduleModeVisibility(modal);

                modal.modal("show");
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    }

    // the edit test button on Compliance > Define Tests page and
    // the test name link on the Compliance > Initiate Audits page
    $(document).on('click', '.edit-test, .test-name', function(event) {
        event.preventDefault();
        openTestForEdit($(this).data('id'));
    });

    // Deep-link: open a specific test's edit modal on load — e.g. the dashboard's
    // Upcoming Tests name link (compliance/index.php?test_id=N). No-op on pages
    // without the update-test modal; the endpoint enforces access on its own.
    $(function() {
        if (!$('#test--update').length) return;
        var deepTestId = new URLSearchParams(window.location.search).get('test_id');
        if (!(deepTestId && /^\d+$/.test(deepTestId))) return;

        // The update modal's WYSIWYG editors initialise asynchronously; opening
        // before they're ready makes setEditorContent() throw so the modal never
        // shows. Poll until the editors are live, then open (bounded fallback).
        var tries = 0;
        (function waitAndOpen() {
            var ready = ['update_objective', 'update_test_steps', 'update_expected_results', 'update_sample', 'update_required_evidence'].every(function(id) {
                var ed = (typeof hugerte !== 'undefined') && hugerte.get(id);
                try { return !!(ed && ed.getBody && ed.getBody()); } catch (e) { return false; }
            });
            if (ready || tries >= 50) {
                openTestForEdit(deepTestId);
            } else {
                tries++;
                setTimeout(waitAndOpen, 100);
            }
        })();
    });

    // Add/Edit Test modals' control multi-select roster (Phase 4a, common
    // tests) -- fetched once per page load. Safe to call unconditionally on
    // every compliance.js page: populateControlSelect() (above)
    // individually no-ops for whichever of #add_test_control/
    // #edit_test_control isn't present on the current page.
    loadControlRoster();
});

/*****************************************************
 * Lazy-load mapped control frameworks (collapse view)
 *****************************************************/
(function ($) {

    const loadedFrameworks = {};

    function loadMappedFrameworks(controlId, $collapse) {
        if (!controlId || loadedFrameworks[controlId]) return;
        loadedFrameworks[controlId] = true;

        const $table = $collapse.find('table');
        const $placeholder = $collapse.find('.loading-placeholder');

        $.ajax({
            url: BASE_URL + '/api/v2/governance/controls/mapped-frameworks',
            type: 'GET',
            dataType: 'json',
            data: { control_id: controlId },
            success: function (res) {
                $placeholder.remove(); // remove loading text
                $table.removeClass('d-none');

                const $tbody = $table.find('tbody');
                $tbody.empty();

                if (!res || !Array.isArray(res.data) || !res.data.length) {
                    $tbody.append('<tr><td colspan="3" class="text-center text-muted">No mapped frameworks found.</td></tr>');
                    return;
                }

                // --- Add search input above table ---
                let $searchWrapper = $collapse.find('.table-search-wrapper');
                if (!$searchWrapper.length) {
                    $searchWrapper = $(`
                        <div class="mb-2 table-search-wrapper">
                            <input type="text" class="form-control form-control-sm mapped-framework-search" placeholder="Search frameworks or controls...">
                        </div>
                    `);
                    $table.before($searchWrapper);
                }
                $searchWrapper.removeClass('d-none');

                // Group by framework
                const grouped = {};
                res.data.forEach(row => {
                    const name = row.framework_name || 'Unknown Framework';
                    if (!grouped[name]) grouped[name] = [];
                    grouped[name].push(row);
                });

                for (const frameworkName in grouped) {
                    const rows = grouped[frameworkName];
                    // Framework header row
                    const $headerRow = $(`
                        <tr class="fw-bold table-primary framework-header">
                            <td colspan="3">${escapeHtml(frameworkName)}</td>
                        </tr>
                    `);
                    $tbody.append($headerRow);

                    rows.forEach(row => {
                        const $dataRow = $(`
                            <tr class="framework-row">
                                <td>${escapeHtml(row.framework_name || '')}</td>
                                <td>${escapeHtml(row.reference_name || '')}</td>
                                <td>${escapeHtml(row.reference_text || '')}</td>
                            </tr>
                        `);
                        $tbody.append($dataRow);
                    });
                }

                // --- Search/filter logic ---
                $collapse.find('.mapped-framework-search').off('keyup').on('keyup', function () {
                    const query = $(this).val().toLowerCase();

                    // Track which frameworks have at least one visible row
                    const frameworkVisible = {};

                    $tbody.find('tr').each(function () {
                        const $tr = $(this);

                        if ($tr.hasClass('framework-header')) {
                            const frameworkName = $tr.text();
                            frameworkVisible[frameworkName] = false; // reset
                            return;
                        }

                        const $header = $tr.prevAll('.framework-header:first');
                        const frameworkName = $header.text();

                        const text = $tr.text().toLowerCase();
                        const match = text.includes(query);
                        $tr.toggle(match);

                        if (match) frameworkVisible[frameworkName] = true;
                    });

                    // Show/hide framework headers based on if they have visible rows
                    $tbody.find('.framework-header').each(function () {
                        const $header = $(this);
                        const frameworkName = $header.text();
                        $header.toggle(frameworkVisible[frameworkName]);
                    });
                });
            },
            error: function () {
                $placeholder.text('Failed to load mapped frameworks.');
                loadedFrameworks[controlId] = false; // allow retry
            }
        });
    }

    // Listen for collapse shown events
    $(document).on('shown.bs.collapse', '[id^="mapped-frameworks-collapse-"]', function () {
        const $collapse = $(this);
        const controlId = $collapse.prev().data('control-id'); // get from header div
        loadMappedFrameworks(controlId, $collapse);
    });

    // Smooth rotating caret
    $(document).on('show.bs.collapse', '[id^="mapped-frameworks-collapse-"]', function () {
        const $header = $(this).prev();
        $header.find('.collapse-caret').addClass('rotate');
    });

    $(document).on('hide.bs.collapse', '[id^="mapped-frameworks-collapse-"]', function () {
        const $header = $(this).prev();
        $header.find('.collapse-caret').removeClass('rotate');
    });

})(jQuery);

// --- Phase 3b Task 6: audit approval workflow (approve/reject) ---
// Shared between the Active Audits row actions (functions.php's
// get_custom_item_actions_for_active_audits()) and the testing.php detail
// page (display_testing()'s approver controls) -- both pages load this
// script, and both render buttons carrying the same classes/data-id, so one
// pair of delegated handlers here covers both surfaces. The API
// (approveAuditById()/rejectAuditById(), Phase 3b Task 5) re-enforces every
// permission/state check server-side; these handlers only fire the request
// and render whatever the API decides.

function refreshAfterAuditApprovalAction() {
    // On the Active Audits list, reload the row in place. On the testing.php
    // detail page there's no datatable -- send the user back to the list,
    // since the audit they were looking at just left the "awaiting approval"
    // state (approved -> closed, or rejected -> back in-progress).
    if (typeof datatableInstances !== 'undefined' && datatableInstances['active_audits']) {
        datatableInstances['active_audits'].ajax.reload(null, false);
    } else {
        window.location.href = BASE_URL + '/compliance/active_audits.php';
    }
}

$(document).on('click', '.audit-approve-btn', function() {
    var id = $(this).data('id');

    confirm(_lang['AreYouSureYouWantToApproveThisAudit'], function() {
        $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/v2/compliance/audits/' + id + '/approve',
            success: function(data) {
                if (data.status_message) {
                    showAlertsFromArray(data.status_message, true);
                }
                refreshAfterAuditApprovalAction();
            },
            error: function(xhr, status, error) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
                if (!retryCSRF(xhr, this)) {
                }
            }
        });
    });
});

$(document).on('click', '.audit-reject-btn', function() {
    var id = $(this).data('id');
    var $textarea = $('#audit-reject-comment-' + id);
    var comment = $.trim($textarea.val());

    if (!comment) {
        showAlertsFromArray(_lang['RejectCommentRequired']);
        $textarea.trigger('focus');
        return;
    }

    $.ajax({
        type: 'POST',
        url: BASE_URL + '/api/v2/compliance/audits/' + id + '/reject',
        contentType: 'application/json',
        // A JSON-body POST bypasses csrf-magic's automatic token injection
        // (it only rewrites form-urlencoded / FormData bodies), so send the
        // token explicitly via the CSRF-TOKEN header -- csrf-magic.php
        // copies that header into $_POST['__csrf_magic']. Matches the app's
        // other JSON AJAX calls (refreshOccurrencesPreview() above,
        // settings-hub.js, notifications.js, ...).
        headers: { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' },
        data: JSON.stringify({ comment: comment }),
        success: function(data) {
            if (data.status_message) {
                showAlertsFromArray(data.status_message, true);
            }
            refreshAfterAuditApprovalAction();
        },
        error: function(xhr, status, error) {
            if (xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
            if (!retryCSRF(xhr, this)) {
            }
        }
    });
});