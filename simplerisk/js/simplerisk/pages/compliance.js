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
jQuery(document).ready(function($){
    if($('select.test_tags').length > 0) createTagsInstance($('select.test_tags'));
    if($('select.test_audit_tags').length > 0) createTagsInstance($('select.test_audit_tags'), 'test_audit');
})