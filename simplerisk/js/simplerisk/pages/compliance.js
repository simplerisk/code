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

    $("#update_test").on("click", function() {

        $form = $("#test-edit-form");

        // Check if the required fields have empty / trimmed empty values
        if (!checkAndSetValidation("#test-edit-form")) {
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
});