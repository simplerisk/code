$(document).ready(function(){
    $(".update-all").click(function(){
        var $forms = $(this).parents('.hero-unit').find('form');
        var assessments = [];
        var assessment_id;

        $forms.each(function(){
            assessment_id = $(this).find("[name=assessment_id]").val();
            var assessment = {
                question_id: $(this).find("[name=question_id]").val(),
                question: $(this).find("[name=question]").val(),
            };

            var $answers = $(this).find(".answers-table tbody");
            var answers = [];
            $answers.each(function(){
                if( !$(this).find('[name="answer[]"]').length ){
                    return;
                }

                answers.push({
                    answer: $(this).find('[name="answer[]"]').val(),
                    answer_id: $(this).find('[name="answer_id[]"]').val(),
                    submit_risk: $(this).find('[name="submit_risk[]"]').is(":checked") ? $(this).find('[name="submit_risk[]"]').val() : 0,
                    risk_subject: $(this).find('[name="risk_subject[]"]').val(),
//                    risk_score: $(this).find('[name="risk_score[]"]').val(),
                    risk_owner: $(this).find('[name="risk_owner[]"]').val(),

                    assets_asset_groups:$(this).find('select[name^="assets_asset_groups"]').val(),

                    assessment_scoring_id: $(this).find('[name="assessment_scoring_id[]"]').val(),
                    
                    scoring_method: $(this).find('[name="scoring_method[]"]').val(),
                    
                    likelihood: $(this).find('[name="likelihood[]"]').val(),
                    impact: $(this).find('[name="impact[]"]').val(),
                    AccessVector: $(this).find('[name="AccessVector[]"]').val(),
                    AccessComplexity: $(this).find('[name="AccessComplexity[]"]').val(),
                    Authentication: $(this).find('[name="Authentication[]"]').val(),
                    ConfImpact: $(this).find('[name="ConfImpact[]"]').val(),
                    IntegImpact: $(this).find('[name="IntegImpact[]"]').val(),
                    AvailImpact: $(this).find('[name="AvailImpact[]"]').val(),
                    Exploitability: $(this).find('[name="Exploitability[]"]').val(),
                    RemediationLevel: $(this).find('[name="RemediationLevel[]"]').val(),
                    ReportConfidence: $(this).find('[name="ReportConfidence[]"]').val(),
                    CollateralDamagePotential: $(this).find('[name="CollateralDamagePotential[]"]').val(),
                    TargetDistribution: $(this).find('[name="TargetDistribution[]"]').val(),
                    ConfidentialityRequirement: $(this).find('[name="ConfidentialityRequirement[]"]').val(),
                    IntegrityRequirement: $(this).find('[name="IntegrityRequirement[]"]').val(),
                    AvailabilityRequirement: $(this).find('[name="AvailabilityRequirement[]"]').val(),
                    
                    DREADDamage: $(this).find('[name="DREADDamage[]"]').val(),
                    DREADReproducibility: $(this).find('[name="DREADReproducibility[]"]').val(),
                    DREADExploitability: $(this).find('[name="DREADExploitability[]"]').val(),
                    DREADAffectedUsers: $(this).find('[name="DREADAffectedUsers[]"]').val(),
                    DREADDiscoverability: $(this).find('[name="DREADDiscoverability[]"]').val(),
                    
                    OWASPSkillLevel: $(this).find('[name="OWASPSkillLevel[]"]').val(),
                    OWASPMotive: $(this).find('[name="OWASPMotive[]"]').val(),
                    OWASPOpportunity: $(this).find('[name="OWASPOpportunity[]"]').val(),
                    OWASPSize: $(this).find('[name="OWASPSize[]"]').val(),
                    OWASPEaseOfDiscovery: $(this).find('[name="OWASPEaseOfDiscovery[]"]').val(),
                    OWASPEaseOfExploit: $(this).find('[name="OWASPEaseOfExploit[]"]').val(),
                    OWASPAwareness: $(this).find('[name="OWASPAwareness[]"]').val(),
                    OWASPIntrusionDetection: $(this).find('[name="OWASPIntrusionDetection[]"]').val(),
                    OWASPLossOfConfidentiality: $(this).find('[name="OWASPLossOfConfidentiality[]"]').val(),
                    OWASPLossOfIntegrity: $(this).find('[name="OWASPLossOfIntegrity[]"]').val(),
                    OWASPLossOfAvailability: $(this).find('[name="OWASPLossOfAvailability[]"]').val(),
                    OWASPLossOfAccountability: $(this).find('[name="OWASPLossOfAccountability[]"]').val(),
                    OWASPFinancialDamage: $(this).find('[name="OWASPFinancialDamage[]"]').val(),
                    OWASPReputationDamage: $(this).find('[name="OWASPReputationDamage[]"]').val(),
                    OWASPNonCompliance: $(this).find('[name="OWASPNonCompliance[]"]').val(),
                    OWASPPrivacyViolation: $(this).find('[name="OWASPPrivacyViolation[]"]').val(),
                    
                    Custom: $(this).find('[name="Custom[]"]').val(),
                    
                    ContributingLikelihood: $(this).find('[name="ContributingLikelihood[]"]').val(),
//                    ContributingImpacts: $(this).find('[name="ContributingImpacts[]"]').val(),
                });
            })
            
            assessment.answers = answers;
            assessments.push(assessment);
        })
        
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/assessment/update?assessment_id=" + assessment_id,
            data: {
                assessments: JSON.stringify(assessments)
            },
            success: function(data){
                if(data.status_message){
                    toastr.success(data.status_message);
                }
            },
            error: function(xhr,status,error){
                if(!retryCSRF(xhr, this))
                {
                }
            }
        })
    });
    
    /**
    * Change Event of Risk Scoring Method
    */
    $("body").on("change", ".risk-scoring-container .scoring-method, [name='scoring_method[]']", function(){
        var parents = $(this).parents(".risk-scoring-container");
        handleSelection($(this).val(), parents);
    })
    
    /**
    * events in clicking Score Using CVSS button of edit details page, muti tabs case
    */
    $('body').on('click', '[name=cvssSubmit]', function(e){
        e.preventDefault();
        var form = $(this).parents('.risk-scoring-container');
        popupcvss(form);
    })
    
    /**
    * events in clicking Score Using DREAD button of edit details page, muti tabs case
    */
    $('body').on('click', '[name=dreadSubmit]', function(e){
        e.preventDefault();
        var form = $(this).parents('.risk-scoring-container');
        popupdread(form);
    })
    
    /**
    * events in clicking Score Using OWASP button of edit details page, muti tabs case
    */
    $('body').on('click', '[name=owaspSubmit]', function(e){
        e.preventDefault();
        var form = $(this).parents('.risk-scoring-container');
        popupowasp(form);
    })

    /**
    * events in clicking Score Using Contributing Risk button of edit details page, muti tabs case
    */
    $('body').on('click', '[name=contributingRiskSubmit]', function(e){
        e.preventDefault();
        var form = $(this).parents('.risk-scoring-container');
        popupcontributingrisk(form);
    });
    
    if($.blockUI !== undefined){
        $.blockUI.defaults.css = {
            padding: 0,
            margin: 0,
            width: '30%',
            top: '40%',
            left: '35%',
            textAlign: 'center',
            cursor: 'wait'
        };
    }
   
})

/**
* Add a new answer row in Edit Assessment
* 
*/
function addRow(tableID){
    var target = $("#" + tableID);
    target.append($("#adding_row").html());
    var select = target.find("select.assets_asset_groups_template");
    select.toggleClass('assets_asset_groups_template assets-asset-groups-select ');
    select.attr('name', 'assets_asset_groups[' + target.find("select[name^='assets_asset_groups']").length + '][]');
    selectize_assessment_answer_affected_assets_widget(select, assets_and_asset_groups);
}

/**
* Delete last answer row in Edit Assessment
* 
*/
function deleteRow(tableID){
    try {
        var table = document.getElementById(tableID);
        var rowCount = table.rows.length;
        if (rowCount > 5) {
            table.deleteRow(rowCount-1);
            table.deleteRow(rowCount-2);
        }
        else {
            alert("Cannot delete all the rows.");
        }
    }catch(e) {
        alert(e);
    }
}

function selectize_assessment_answer_affected_assets_widget(select_tag, options) {
    return select_tag.selectize({
        options: options,
        sortField: 'text',
        plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
        delimiter: ',',
        create: function (input){
            return { id:'new_asset_' + input, name:input };
        },
        persist: false,
        valueField: 'id',
        labelField: 'name',
        searchField: 'name',
        sortField: 'name',
        optgroups: [
            {class: 'asset', name: 'Standard Assets'},
            {class: 'group', name: 'Asset Groups'}
        ],
        optgroupField: 'class',
        optgroupLabelField: 'name',
        optgroupValueField: 'class',
        preload: true,
        render: {
            item: function(item, escape) {
                return '<div class="' + item.class + '">' + escape(item.name) + '</div>';
            }
        }
    });
}

function selectize_pending_risk_affected_assets_widget(select_tag, options) {
    var select = select_tag.selectize({
        options: options,
        sortField: 'text',
        plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
        delimiter: ',',
        create: function (input){
            return { id:input, name:input };
        },
        persist: false,
        valueField: 'id',
        labelField: 'name',
        searchField: 'name',
        sortField: 'name',
        optgroups: [
            {class: 'asset', name: 'Standard Assets'},
            {class: 'group', name: 'Asset Groups'}
        ],
        optgroupField: 'class',
        optgroupLabelField: 'name',
        optgroupValueField: 'class',
        preload: true,
        render: {
            item: function(item, escape) {
                return '<div class="' + item.class + '">' + escape(item.name) + '</div>';
            }
        }
    });
}

function setupQuestionnaireContactUserViewWidget(select_tag) {
    
    if (!select_tag.length)
        return;
    
    var select = select_tag.selectize({
        sortField: 'text',
        disabled: true,
        render: {
            item: function(item, escape) {
                return '<div class="' + item.class + '">' + escape(item.text) + '</div>';
            }
        }
    });
    
    select[0].selectize.disable();
    select_tag.parent().find('.selectize-control div').removeClass('disabled');
}    

function setupQuestionnaireContactUserWidget(select_tag) {

    if (!select_tag.length)
        return;
    
    var select = select_tag.selectize({
        sortField: 'text',
        plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
        delimiter: ',',
        create: false,
        persist: false,
        valueField: 'id',
        labelField: 'name',
        searchField: 'name',
        sortField: 'name',
        optgroups: [
            {class: 'user', name: $("#_lang_SimpleriskUsers").length ? $("#_lang_SimpleriskUsers").val() : "Simplerisk Users" },
            {class: 'assessment', name: $("#_lang_AssessmentContacts").length ? $("#_lang_AssessmentContacts").val() : "Assessment Contacts" },
        ],
        optgroupField: 'class',
        optgroupLabelField: 'name',
        optgroupValueField: 'class',
        preload: true,
        render: {
            item: function(item, escape) {
                return '<div class="' + item.class + '">' + escape(item.name) + '</div>';
            }
        },
        onInitialize: function() {
            $(this.$input[0]).parent().find('.selectize-control div').block({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
        },
        load: function(query, callback) {
            if (query.length) return callback();
            var self = this;
            
            this.renderCache = {}
            var originEl = $(self.$input[0]);
            var selectedItems = originEl.data('items') ? originEl.data('items') : [];
            $.ajax({
                url: BASE_URL + '/api/assessment/contacts-users/options',
                type: 'GET',
                dataType: 'json',
                error: function() {
                    callback();
                },
                success: function(res) {
                    var data = res.data;
                    var selected_ids = [];
                    // Have to do it this way, because addition with simple addOption() will
                    // bug out when we deselect an option(it wouldn't be added back to the
                    // list of selectable items)
                    len = data.length;
                    for (var i = 0; i < len; i++) {
                        var item = data[i];
                        // Check if this element is selected one
                        for(var j = 0; j < selectedItems.length; j++){
                            var selectedItem = selectedItems[j];
                            if(selectedItem.class == item.class && selectedItem.id == item.id){
                                selected_ids.push(item.id + '_' + item.class);
                                break;
                            }
                        }

                        item.id += '_' + item.class;
                        self.addOption(item);
                    }
                    
                    if (selected_ids.length)
                        self.setValue(selected_ids);
                },
                complete: function() {
                    originEl.parent().find('.selectize-control div').unblock({message:null});
                }
            });
        }
    });        
}
    function setupQuestionnaireAssetsAssetGroupsWidget(select_tag, risk_id) {

        // Giving a default value here because IE can't handle
        // function parameter default values...
        risk_id = risk_id || 0;
        
        if (!select_tag.length)
            return;
        
        var select = select_tag.selectize({
            sortField: 'text',
            plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
            delimiter: ',',
            create: function (input){
                return { id:'new_asset_' + input, name:input };
            },
            persist: false,
            valueField: 'id',
            labelField: 'name',
            searchField: 'name',
            sortField: 'name',
            optgroups: [
                {class: 'asset', name: 'Standard Assets'},
                {class: 'group', name: 'Asset Groups'}
            ],
            optgroupField: 'class',
            optgroupLabelField: 'name',
            optgroupValueField: 'class',
            preload: true,
            render: {
                item: function(item, escape) {
                    return '<div class="' + item.class + '">' + escape(item.name) + '</div>';
                }
            },
            onInitialize: function() {
                if (risk_id != 0)
                    select_tag.parent().find('.selectize-control div').block({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
            },
            load: function(query, callback) {
                if (query.length) return callback();
                $.ajax({
                    url: BASE_URL + '/api/asset-group/options?risk_id=' + risk_id,
                    type: 'GET',
                    dataType: 'json',
                    error: function() {
                        callback();
                    },
                    success: function(res) {
                        var data = res.data;
                        var control = select[0].selectize;
                        var selected_ids = [];
                        // Have to do it this way, because addition with simple addOption() will
                        // bug out when we deselect an option(it wouldn't be added back to the
                        // list of selectable items)
                        len = data.length;
                        for (var i = 0; i < len; i++) {
                            var item = data[i];
                            item.id += '_' + item.class;
                            control.registerOption(item);
                            if (item.selected == '1') {
                                selected_ids.push(item.id);
                            }
                        }
                        if (selected_ids.length)
                            control.setValue(selected_ids);
                    },
                    complete: function() {
                        select_tag.parent().find('.selectize-control div').unblock({message:null});
                    }
                });
            }
        });        
    }

function redraw_control(){
    var template_framework = $("#template_framework").val();
    $('.risk-scoring-container').block({
        message: 'Processing',
        css: { border: '1px solid black', background: '#ffffff' }
    });
    $.ajax({
        type: "POST",
        url: BASE_URL + "/api/assessment/questionnaire/template/controls",
        data: {
            template_framework: template_framework
        },
        dataType: "json",
        success: function(data){
            if(data.status_message){
                toastr.success(data.status_message);
            }
            if(data.controls){
                var obj = $("#template_controls");
                $(obj).find("option").remove();
                $.each(data.controls, function(key, item) {
                    var $option = $("<option/>", {
                        value: item.id,
                        text: item.short_name,
                        selected: true,
                    });
                    $(obj).append($option);
                });
                $(obj).multiselect('rebuild');
            }
            $('.risk-scoring-container').unblock();
        },
        error: function(xhr,status,error){
            if(xhr.responseJSON && xhr.responseJSON.status_message){
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
            if(!retryCSRF(xhr, this))
            {
            }
            $('.risk-scoring-container').unblock();
        }
    })
}

// This function can handle using the preloaded options or if that parameter isn't provided
// or empty it can load the available tags
function createTagsInstance(tag, options) {

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
                url: BASE_URL + '/api/management/tag_options_of_types?type=risk,questionnaire_answer',
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

// Updates the indexes of the answer tags/asset groups
// In case of editing a question we only have to update the indexes for newly created answers
// existing ones have ids
function refresh_questionnaire_question_selectize_widget_names(edit) {
	if (edit) {
	    $('#questionnaire_edit_form select.assets-asset-groups-select-template').each(function(index, element){
	        $(element).attr('name', 'answers[assets_asset_groups][' + index + '][]');
	    });

	    $('#questionnaire_edit_form select.tags-template').each(function(index, element){
	        $(element).attr('name', 'answers[tags][' + index + '][]');
	    });
	} else {
	    $('#questionnaire_new_question_form select.assets-asset-groups-select').each(function(index, element){
	        $(element).attr('name', 'answers[assets_asset_groups][' + index + '][]');
	    });

	    $('#questionnaire_new_question_form select.tags').each(function(index, element){
	        $(element).attr('name', 'answers[tags][' + index + '][]');
	    });
	}
}

function refreshSelectizeOptions(selector, data) {
    $(selector).each(function() {
        if (this.selectize) {
            this.selectize.clearOptions();
            if (data && data.length) {
                this.selectize.addOption(data);
                this.selectize.refreshOptions(false);
            } else {
                this.selectize.close();
            }
        }
    });
}

/************ Script for Questionnaire Template ****************/
var table_str;
var tab_cnt = 1;
var tabs;
var dropOptions = {
    tolerance: 'pointer',
    drop: function( event, ui ) {
        var item = $( this );
        var list = $(item.find('a.tab_link').attr('href')).find('.selected_questions .question_list');
        var label = item.find('a.tab_link .tab-label').text();
        var message = "The question has been moved to the '"+label+"' tab."

        ui.draggable.hide('fast', function() {
            // tabs.tabs('option', 'active', tab_items.index(item) );
            $( this ).css('width','100%').appendTo( list ).show('fast');
            active_tab_func();
            showAlertFromMessage(message, true);
        });
    }
};
$(document).ready(function(){
    table_str = $('#selected_questions_list').html();
    tabs = $('#template-tabs').tabs({
        activate: active_tab_func,
    });
    initSortable();
    // click create template tab
    $('#create-template-tab').click(function() {create_template_tab();});
    // click disable tab event
    $('#disable_tab').click(function(){
        $("#disable_tab").hide();
        $("#create-template-tab").fadeIn();
        var active_ids = []; // selected question ids in active tabs
        var data_ids = []; // selected question ids in not active tabs
        $('ul.selected_questions').each(function(index, obj) {
            if(index ==0) return;
            $(obj).find('li.selected_question').each(function(i, obj) {
                $('#tab-content-1 .question_list').append(obj);
            });
        });
        // remove other tabs without first
        $("ul.tabs-nav li:not(:eq(0))").remove();
        $(".template_tab_contents:not(:eq(0))").remove();
        // hide tabs
        var active_index = $("#template-tabs li.ui-tabs-active").index();
        if(active_index != 0) tabs.tabs('option', 'active', 0);
        else active_tab_func();
        $('#selected_questions_list').append($('#tab-content-1').html());
        $('#tab-content-1').html("");
        $('#template-tabs').fadeOut();
        initSortable();
        tab_cnt = 1;
    });

    $('#template-tabs').on('click', '.edit-tab-name', function(){
        $(this).hide();
        $(this).parent().find('span.tab-label').hide();
        $(this).parent().find('input.tab-name').show().select();
    });
    // tab remove event
    $('#template-tabs').on('click', '.remove-tab', function(){
        var tab_index = $(this).parent().find('a.tab_link').attr('data-index');
        var element_id = 'tab-content-' + tab_index;
        tabs.tabs('option', 'active', 0);
        $('#'+element_id).remove();
        $(this).parent().remove();
    });
    // space key press
    $('#template-tabs').on('keyup', '.tab-name', function(e){
        var text_val = $(this).val();
        if(e.keyCode == 32) {
            var pos = $(this)[0].selectionStart;
            var new_value = [text_val.slice(0, pos), ' ', text_val.slice(pos)].join('');
            $(this).val(new_value);
        }
        return true;
    });
    $('#template-tabs').on('blur change', '.tab-name', function(){
        if(!$(this).val()) return false;
        var label = $(this).parent().find('span.tab-label');
        $(this).hide();
        label.text($(this).val());
        label.show();
        $(this).closest('li').find('.edit-tab-name').show()
    });
    // click add tab
    $('#tab-add-btn').click(function(){add_template_tab();});
    var tab_items = $('#template-tabs ul:first li').droppable(dropOptions);

    // save questionnaire template
    $('.questionnaire_template_form').submit(function(){
        $('.hero-unit:first').block({
            message: 'Processing',
            css: { border: '1px solid black', background: '#ffffff' }
        });
        var selected_questions = [];
        $('ul.selected_questions').each(function(i, obj) {
            var tab_questions = [];
            $(this).find('li.selected_question').each(function(i, obj) {
                var data_id = $(this).attr('data-id');
                tab_questions.push(data_id);
            });
            selected_questions.push(tab_questions);
        });
        var form = $(this);
        var form_data = new FormData(form[0]);
        if($('#disable_tab').is(':hidden')) form_data.append('disable_tab',1);
        else form_data.append('disable_tab',0);

        for (var i = 0; i < selected_questions.length; i++) {
            form_data.append('selected_questions[]', selected_questions[i]);
        }
        $.ajax({
            type: 'POST',
            url: BASE_URL + '/api/assessment/questionnaire/template_questions/save_template',
            data: form_data,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(result){
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }
                $('.hero-unit:first').unblock();
                location.reload();
            }
        })
        .fail(function(xhr, textStatus){
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });

        return false;
    });
});
// initSort
function initSortable(){
    $('.sortable').sortable({
        items: 'li:not(.list-header)',
        cursor: 'move',
    });
}
function create_template_tab(tab_id){
    if(typeof(tab_id) == 'undefined') {
        tab_id = '';
    }
    $("#create-template-tab").hide();
    $("#disable_tab").fadeIn();
    $('#tab-content-1').append($('#selected_questions_list').html());
    $('#selected_questions_list').html("");
    $('#template-tabs').fadeIn();
    initSortable();
    $('#template-tabs ul.tabs-nav li:first').attr('data-id', tab_id);
    return true;
}
// active tab event
function active_tab_func(event, ui){
    var active_index = $("#template-tabs li.ui-tabs-active").index();
    var active_ids = []; // selected question ids in active tabs
    var data_ids = []; // selected question ids in not active tabs
    $('ul.selected_questions').each(function(index, obj) {
        $(obj).find('li.selected_question').each(function(i, obj) {
            if(index == active_index) {
                active_ids.push($(this).attr('data-id'));
            } else {
                data_ids.push($(this).attr('data-id'));
            }
        });
    });
    $('.hero-unit:first').block();
    $.ajax({
        url: BASE_URL + '/api/assessment/questionnaire/template_questions/questions_list',
        type: 'POST',
        data: {
            selected_ids : data_ids,
            active_ids : active_ids,
        },
        success : function (response) {
            $('#template_questions').html(response).multiselect('rebuild');
            $('.hero-unit:first').unblock();
        },
        error: function(xhr,status,error) {
            if(xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
        }
    });
    return true;
}
function add_template_tab(tab_name, tab_id){
    tab_cnt++;
    if(tab_cnt > 8) {
        return false;
    }
    if(typeof(tab_name) == 'undefined') {
        tab_name = tab_name_str + '(' + tab_cnt + ')';
    }
    if(typeof(tab_id) == 'undefined') {
        tab_id = '';
    }
    var element_id = 'tab-content-' + tab_cnt;
    var newstring = `
        <li data-id="`+tab_id+`">
            <a class="tab_link" href="#`+element_id+`" data-index="`+tab_cnt+`">
                <span class="tab-label">`+tab_name+`</span>
                <input type="text" name="tab_name[]" class="tab-name" value="`+tab_name+`">
            </a>
            <a class="edit-tab-name" href="#"><i class="fa fa-edit"></i></a>
            <a class="remove-tab" href="#"><i class="fa fa-trash"></i></a>
        </li>
    `;
    $('#template-tabs .tabs-nav').append(newstring);
    $('#template-tabs').append('<div id="'+element_id+'" class="template_tab_contents"></div>');
    $('#'+element_id).append(table_str);

    $('#template-tabs').tabs('refresh');

    initSortable();

    tab_items = $('#template-tabs ul:first li').droppable(dropOptions);
    return true;
}
// redraw selected questions when change dropdown
function redraw_selected_questions() {
    // tabs.tabs({active: 0});
    var active_index = $("#template-tabs li.ui-tabs-active").index();
    var selected_ids = $('#template_questions').val();
    var template_id = $("#template_id").val();
    $.ajax({
        url: BASE_URL + '/api/assessment/questionnaire/template_questions/selected_questions',
        type: 'POST',
        data: {
            template_id : template_id,
            selected_ids : selected_ids,
        },
        success : function (response) {
            $('ul.selected_questions').eq(active_index).find('.question_list').html(response);
            initSortable();
        },
        error: function(xhr,status,error) {
            if(xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
        }
    });
}
// draw selected questions by tab
function draw_selected_questions(tab_id, tab_index){
    var template_id = $("#template_id").val();
    $.ajax({
        url: BASE_URL + '/api/assessment/questionnaire/template_questions/selected_questions_tab',
        type: 'POST',
        data: {
            template_id : template_id,
            tab_id : tab_id,
        },
        success : function (response) {
            $('ul.selected_questions').eq(tab_index-1).find('.question_list').html(response);
            initSortable();
        },
        error: function(xhr,status,error) {
            if(xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
        }
    });
}