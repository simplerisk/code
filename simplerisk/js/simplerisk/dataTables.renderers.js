/******************************************************************
****************Renderers for the datatable widget*****************
*******************************************************************/
DataTable.render.tags = function (tag_type) {
    return function (data, type, row) {
        // console.log(data, type, row);
        if (type === 'edit') {
            if (!(data instanceof Array)) {
                data = [data];
            }
            var id = row['id'];
            
            var result = `<select class='edited-field' readonly id='tags-${id}' name='tags[]' multiple placeholder='Select/Add tag'>`; 
            
            for (const tag of data) {
                result += `<option selected value='${tag}'>${tag}</option>`;
            }
            return result + `</select><script>
                    var tags_${id}_selectize = $('#tags-${id}').selectize({
                        plugins: ['remove_button', 'restore_on_backspace'],
                        delimiter: '|',
                        create: true,
                        valueField: 'label',
                        labelField: 'label',
                        searchField: 'label',
                        sortField: [{ field: 'label', direction: 'asc' }],
                        onChange: function() {$('#tags-${id}').data('changed', true);},
                    });
                    $.ajax({
                        url: BASE_URL + '/api/v2/management/tag_options_of_type?type=${tag_type}',
                        type: 'GET',
                        dataType: 'json',
                        error: function() {
                            console.log('Error loading assets for selectize!');
                        },
                        success: function(res) {
                            tags_${id}_selectize[0].selectize.addOption(res.data);
                            tags_${id}_selectize[0].selectize.refreshOptions(true);
                        }
                    });
                </script>`;
        }
        
        if (type === 'display' && data) {
            if (!(data instanceof Array)) {
                data = [data];
            }
            var result = "<span class='sr-chip-row'>";
            for (const tag of data) {
                result += `<span class='sr-chip'>${tag}</span>`;
            }
            return result + "</span>";
        }

        // Search, order and type can use the original data
        return data;
    };
};

// Manage Audits' Status/Result columns as styled state chips instead of bare
// text -- same .sr-state-pill family Define Tests (resultPill()) and Define
// Control Frameworks (renderStatusPill()) already use, so Pass/Fail read the
// same way everywhere: a check/X glyph, not the generic dot.
DataTable.render.resultPill = function () {
    var GLYPHS = {
        pass: { cls: 'sr-state-success', icon: 'fa-check' },
        fail: { cls: 'sr-state-danger', icon: 'fa-xmark' },
    };
    return function (data, type) {
        if (type !== 'display' || !data) {
            return data;
        }
        var g = GLYPHS[String(data).toLowerCase()];
        var cls = 'sr-state-pill sr-state-pill-result' + (g ? ' sr-state-pill-glyph ' + g.cls : ' sr-state-neutral');
        var icon = g ? '<i class="fa ' + g.icon + '" aria-hidden="true"></i>' : '';
        return '<span class="' + cls + '">' + icon + data + '</span>';
    };
};

// Status is already server-escaped, localized text
// (get_custom_formatting_data_for_all_audits(), includes/functions.php) --
// this only adds the chip shell. It deliberately does NOT key a hue off the
// text itself (e.g. matching the English word "Overdue"): that text is
// translated per-locale, so a substring match would only ever work in
// English. A real per-status hue needs the raw, language-independent status
// code, not the display string a client-side renderer receives here.
//
// test_audit_is_overdue() (same PHP function) appends a literal
// " (<Overdue>)" suffix onto this SAME string when overdue -- stripped back
// out here so the pill only ever shows the workflow stage. The overdue fact
// itself now renders next to Next Test Date instead (DataTable.render.
// nextTestDatePill() below) -- it's a fact about that date, not about the
// audit's stage, and cramming both into one pill's text was what forced
// Status to 323px wide to begin with. L('Overdue') re-derives the exact
// suffix PHP appended (same $lang key both sides read), so this still works
// correctly whatever locale the page is rendered in -- only the 'display'
// render type is touched; sort/search/filter still see the original,
// un-split string PHP sent.
DataTable.render.statusPill = function () {
    return function (data, type) {
        if (type !== 'display' || !data) {
            return data;
        }
        var overdueSuffix = ' (' + L('Overdue') + ')';
        var isOverdue = data.length > overdueSuffix.length && data.slice(-overdueSuffix.length) === overdueSuffix;
        var label = isOverdue ? data.slice(0, -overdueSuffix.length) : data;
        // title carries the full (un-split) label for a viewer whose pill is
        // wrapped/clipped (#audits-table-card .sr-state-pill, _compliance.scss)
        // -- label is already HTML-attribute-safe (escapeHtml() also
        // neutralizes quote characters), so it's safe to reuse verbatim.
        return '<span class="sr-state-pill sr-state-neutral" title="' + label + '">' + label + '</span>';
    };
};

// Test Date (test_date, includes/functions.php) is blank until a result is
// actually recorded for that specific audit -- every open/active audit has
// no value yet. A bare empty cell reads as something missing; a neutral
// "Untested" chip reads as the true state instead.
DataTable.render.testDate = function () {
    return function (data, type) {
        if (type !== 'display') {
            return data;
        }
        if (!data) {
            return '<span class="sr-chip">' + L('Untested') + '</span>';
        }
        return data;
    };
};

// Minimal textContent-based HTML-escaper for a translated ($lang/L()) string
// interpolated into an attribute or inner HTML -- encode_js_lang_subset()
// (includes/functions.php) only hex-escapes enough to protect the <script>
// embedding boundary at parse time; the JS string value itself comes back as
// plain text once window._lang is parsed, so a translation containing a
// literal '"', '<', or '&' (any of the 39+ locale files, or a customer's own
// lang.<locale>.php override) is not attribute-safe on its own. Mirrors
// compliance-initiate-audits.js's own esc() helper.
function srEscapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s === null || s === undefined ? '' : String(s);
    return d.innerHTML;
}

// Manage Audits' Next Test Date (functions.php's 'framework_control_test_audit'
// catalog) -- same blank-as-"Untested" handling as testDate() above, plus a
// bare fa-bell icon (no chip/pill) after the date when the audit is overdue.
// Lives here rather than on Status: overdue-ness is a fact about THIS date
// (next_date has passed), not about the audit's workflow stage, so it reads
// next to the date it actually describes.
//
// row.is_overdue is a clean boolean get_data_for_datatable() (includes/
// functions.php) computes once per row via test_audit_is_overdue() and
// attaches alongside 'status'/'status_display' for the 3 Manage Audits views
// -- `row` is DataTables' own 3rd render argument (the full row object), so
// it's readable here even when the Status column itself isn't currently
// visible (Manage Audits' narrow-width tiers, display_audits(), includes/
// compliance.php, drop Status from view entirely, but the underlying row
// data is still fetched regardless of what's currently shown).
DataTable.render.nextTestDatePill = function () {
    return function (data, type, row) {
        if (type !== 'display') {
            return data;
        }
        if (!data) {
            return '<span class="sr-chip">' + L('Untested') + '</span>';
        }
        if (!row || !row.is_overdue) {
            return data;
        }
        var overdueLabel = srEscapeHtml(L('Overdue'));
        return data + ' <span class="sr-overdue-icon" role="img" title="' + overdueLabel +
            '" aria-label="' + overdueLabel + '"><i class="fa fa-bell" aria-hidden="true"></i></span>';
    };
};

DataTable.render.short_text = function (name) {
    return function (data, type, row) {
        //console.log(data, type, row);
        if (type === 'edit') {
            var id = row['id'];
            return `<input type='text' class='edited-field' id='${name}-${id}' name='${name}' placeholder='' value='${data}' style='width: 100%' />`; 
        }

        // Search, order and type can use the original data
        return data;
    };
};

DataTable.render.long_text = function (name) {
    return function (data, type, row) {
        //console.log(data, type, row);
        if (type === 'edit') {
            var id = row['id'];
            return `<textarea class='edited-field' id='${name}-${id}' name='${name}' placeholder='' rows='3' cols='50'>${data}</textarea>`; 
        }

        // Search, order and type can use the original data
        return data;
    };
};
