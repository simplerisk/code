$.fn.extend({
    initAsInitiateAuditTreegrid: function() {
        this.treegrid({
            iconCls: 'icon-ok',
            animate: true,
            fitColumns: true,
            nowrap: true,
            collapsible: false,
            url: BASE_URL + '/api/compliance/initiate_audits',
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
        searchField: 'label'
    };
	// If options aren't provided, setup the selectize's preload to load them
	if (typeof options === 'undefined' || options.length == 0) {
		selectize_setup.preload = true;
		selectize_setup.load = function(query, callback) {
            if (query.length) return callback();
            
            $.ajax({
                url: BASE_URL + '/api/management/tag_options_of_types?type=' + tag_type,
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

$(function(){
    
    // Enable or disable the 'audit_initiation_offset' field based on whether the automatic audit initiation is enabled or disabled
    $("[name='auto_audit_initiation']").on("change", function() {
        let $this = $(this);
        $this.closest('.audit-initiation').find("input[type='number'][name='audit_initiation_offset']").attr("disabled", $this.val() === '0').attr("required", $this.val() === '1');

        if ($this.val() === '0') {

            // Clear the value of the 'audit_initiation_offset' field if automatic audit initiation is disabled
            $this.closest('.audit-initiation').find("input[type='number'][name='audit_initiation_offset']").val('');

            // Hide the required mark if automatic audit initiation is disabled
            $this.closest('.audit-initiation').find(".audit-initiation-offset-container span.required").addClass('d-none');

        } else {

            // Show the required mark if automatic audit initiation is enabled
            $this.closest('.audit-initiation').find(".audit-initiation-offset-container span.required").removeClass('d-none');

        }
    });

    
    $("#add_test").on("click", function() {

        $form = $("#test-new-form");

        // Check if the required fields have empty / trimmed empty values
        if (!checkAndSetValidation("#test-new-form")) {
            return false;
        }

        if ($("[name='auto_audit_initiation']:checked", $form).val() === '1') {

            let audit_initiation_offset = $("[name='audit_initiation_offset']", $form).val();
            let test_frequency = $("[name='test_frequency']", $form).val();

            if (audit_initiation_offset === '' || audit_initiation_offset < 0) {

                showAlertFromMessage(_lang["AuditInitiationOffsetMustBeANonNegativeValue"]);
                return false;

            } else if (test_frequency !== '' && Number(audit_initiation_offset) > Number(test_frequency)) {

                showAlertFromMessage(_lang["AuditInitiationOffsetMustBeLessThanOrEqualToTestFrequency"]);
                return false;

            }
        }
    });

    // Update test form event
    // This can be used for the following pages
    // the Compliance > Define Tests page and 
    // the Compliance > Initiate Audits page
    $(document).on("submit", "#update-test-form", function(event) {
        event.preventDefault();

        // Check if the required fields have empty / trimmed empty values
        if (!checkAndSetValidation(this)) {
            return;
        }

        if ($("[name='auto_audit_initiation']:checked", this).val() === '1') {

            let audit_initiation_offset = $("[name='audit_initiation_offset']", this).val();
            let test_frequency = $("[name='test_frequency']", this).val();

            if (audit_initiation_offset === '' || audit_initiation_offset < 0) {

                showAlertFromMessage(_lang["AuditInitiationOffsetMustBeANonNegativeValue"]);
                return;

            } else if (test_frequency !== '' && Number(audit_initiation_offset) > Number(test_frequency)) {

                showAlertFromMessage(_lang["AuditInitiationOffsetMustBeLessThanOrEqualToTestFrequency"]);
                return;

            }
        }

        // Variable for indicating where the update test form is submitted from
        // It can be either the Compliance > Define Tests page or the Compliance > Initiate Audits page
        // If the page is the Compliance > Define Tests page, the value is "define_tests"
        // If the page is the Compliance > Initiate Audits page, the value is "audit_initiation"
        let where = $('[name=where]', this).val();

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

                location.reload();
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
    // the edit test button on Compliance > Define Tests page and 
    // the test name link on the Compliance > Initiate Audits page
    $(document).on('click', '.edit-test, .test-name', function(event) {
        event.preventDefault();
            
        let test_id = $(this).data('id');

        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/compliance/test?id=" + test_id,
            success: function(result) {
                let data = result['data'];
                let modal = $('#test--update');
                
                $('[name=test_id]', modal).val(data['id']);
                $('[name=tester]', modal).val(data['tester']);
                $('#additional_stakeholders', modal).multiselect('deselectAll', false);
                $('#additional_stakeholders', modal).multiselect('select', data['additional_stakeholders']);

                $("[name='team[]']", modal).multiselect('deselectAll', false);
                $("[name='team[]']", modal).multiselect('select', data['teams']);

                $('[name=test_frequency]', modal).val(data['test_frequency']);

                // If the audit_initiation_offset is not null, auto_audit_initiation is true
                if (data['audit_initiation_offset'] != null) {

                    // Check the auto_audit_initiation true radio button
                    $('[name=auto_audit_initiation][value=\"1\"]', modal).prop('checked', true);

                    // Enable and require the audit_initiation_offset input
                    $('[name=audit_initiation_offset]', modal).prop('disabled', false);
                    $('[name=audit_initiation_offset]', modal).prop('required', true);

                    // Show the required mark
                    $('.audit-initiation .audit-initiation-offset-container span.required', modal).removeClass('d-none');

                    // Set the audit_initiation_offset value
                    $('[name=audit_initiation_offset]', modal).val(data['audit_initiation_offset']);

                // If the audit_initiation_offset is null, auto_audit_initiation is false
                } else {

                    // Check the auto_audit_initiation false radio button
                    $('[name=auto_audit_initiation][value=\"0\"]', modal).prop('checked', true);

                    // Disable and not require the audit_initiation_offset input
                    $('[name=audit_initiation_offset]', modal).prop('disabled', true);
                    $('[name=audit_initiation_offset]', modal).prop('required', false);

                    // Hide the required mark
                    $('.audit-initiation .audit-initiation-offset-container span.required', modal).addClass('d-none');

                    // Clear the audit_initiation_offset value
                    $('[name=audit_initiation_offset]', modal).val('');
                }

                $('[name=last_date]', modal).val(data['last_date']);
                $('[name=last_date]', modal).initAsDatePicker({maxDate: moment().format(default_date_format)});

                if (!data['last_date']) {
                    if (!data['next_date'] || (moment(data['next_date'], default_date_format)).isBefore(moment(), 'day')) {
                        $('[name=next_date]', modal).val(moment().format(default_date_format));
                    } else {
                        $('[name=next_date]', modal).val(data['next_date']);
                    }
                } else {
                    let next_date_obj = moment(data['last_date'], default_date_format).add(data['test_frequency'], 'days');
                    if (next_date_obj.isBefore(moment(), 'day')) {
                        $('[name=next_date]', modal).val(moment().format(default_date_format));
                    } else {
                        $('[name=next_date]', modal).val(next_date_obj.format(default_date_format));
                    }
                }

                let min_next_date = null;
                if(data['last_date'] != '') {
                    min_next_date = data['last_date'];
                } else {
                    min_next_date = moment().format(default_date_format);
                }
                $('[name=next_date]', modal).initAsDatePicker({minDate: min_next_date});

                $('[name=name]', modal).val(data['name']);
                $('[name=objective]', modal).val(data['objective']);
                $('[name=test_steps]', modal).val(data['test_steps']);
                $('[name=approximate_time]', modal).val(data['approximate_time']);
                $('[name=expected_results]', modal).val(data['expected_results']);

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

                modal.modal("show");
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    });
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