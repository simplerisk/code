$(document).ready(function () {
    $(".update-all").click(function () {
        var $forms = $(this).parents('.hero-unit').find('form');
        var assessments = [];
        var assessment_id;

        $forms.each(function () {
            assessment_id = $(this).find("[name=assessment_id]").val();
            var assessment = {
                question_id: $(this).find("[name=question_id]").val(),
                question: $(this).find("[name=question]").val(),
            };

            var $answers = $(this).find(".answers-table tbody");
            var answers = [];
            $answers.each(function () {
                if (!$(this).find('[name="answer[]"]').length) {
                    return;
                }

                answers.push({
                    answer: $(this).find('[name="answer[]"]').val(),
                    answer_id: $(this).find('[name="answer_id[]"]').val(),
                    submit_risk: $(this).find('[name="submit_risk[]"]').is(":checked") ? $(this).find('[name="submit_risk[]"]').val() : 0,
                    risk_subject: $(this).find('[name="risk_subject[]"]').val(),
                    //                    risk_score: $(this).find('[name="risk_score[]"]').val(),
                    risk_owner: $(this).find('[name="risk_owner[]"]').val(),

                    assets_asset_groups: $(this).find('select[name^="assets_asset_groups"]').val(),

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
            success: function (data) {
                if (data.status_message) {
                    toastr.success(data.status_message);
                }
            },
            error: function (xhr, status, error) {
                if (!retryCSRF(xhr, this)) {
                }
            }
        })
    });

    /**
    * Change Event of Risk Scoring Method
    */
    $(document).on("change", ".risk-scoring-container .scoring-method, [name='scoring_method[]']", function () {
        var parents = $(this).parents(".risk-scoring-container");
        handleSelection($(this).val(), parents);
    })

    /**
    * events in clicking Score Using CVSS button of edit details page, muti tabs case
    */
    $(document).on('click', '[name=cvssSubmit]', function (e) {
        e.preventDefault();
        var form = $(this).parents('.risk-scoring-container');
        popupcvss(form);
    })

    /**
    * events in clicking Score Using DREAD button of edit details page, muti tabs case
    */
    $(document).on('click', '[name=dreadSubmit]', function (e) {
        e.preventDefault();
        var form = $(this).parents('.risk-scoring-container');
        popupdread(form);
    })

    /**
    * events in clicking Score Using OWASP button of edit details page, muti tabs case
    */
    $(document).on('click', '[name=owaspSubmit]', function (e) {
        e.preventDefault();
        var form = $(this).parents('.risk-scoring-container');
        popupowasp(form);
    })

    /**
    * events in clicking Score Using Contributing Risk button of edit details page, muti tabs case
    */
    $(document).on('click', '[name=contributingRiskSubmit]', function (e) {
        e.preventDefault();
        var form = $(this).parents('.risk-scoring-container');
        popupcontributingrisk(form);
    });
})

/**
* Add a new answer row in Edit Assessment
* 
*/
function addRow(tableID) {
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
function deleteRow(tableID) {
    try {
        var table = document.getElementById(tableID);
        var rowCount = table.rows.length;
        if (rowCount > 5) {
            table.deleteRow(rowCount - 1);
            table.deleteRow(rowCount - 2);
        }
        else {
            alert("Cannot delete all the rows.");
        }
    } catch (e) {
        alert(e);
    }
}

function selectize_assessment_answer_affected_assets_widget(select_tag, options) {
    return select_tag.selectize({
        options: options,
        sortField: 'text',
        plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
        delimiter: ',',
        create: function (input) {
            return { id: 'new_asset_' + input, name: input };
        },
        persist: false,
        valueField: 'id',
        labelField: 'name',
        searchField: 'name',
        sortField: 'name',
        optgroups: [
            { class: 'asset', name: 'Standard Assets' },
            { class: 'group', name: 'Asset Groups' }
        ],
        optgroupField: 'class',
        optgroupLabelField: 'name',
        optgroupValueField: 'class',
        preload: true,
        render: {
            item: function (item, escape) {
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
        create: function (input) {
            return { id: input, name: input };
        },
        persist: false,
        valueField: 'id',
        labelField: 'name',
        searchField: 'name',
        sortField: 'name',
        optgroups: [
            { class: 'asset', name: 'Standard Assets' },
            { class: 'group', name: 'Asset Groups' }
        ],
        optgroupField: 'class',
        optgroupLabelField: 'name',
        optgroupValueField: 'class',
        preload: true,
        render: {
            item: function (item, escape) {
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
            item: function (item, escape) {
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
            { class: 'user', name: $("#_lang_SimpleriskUsers").length ? $("#_lang_SimpleriskUsers").val() : "Simplerisk Users" },
            { class: 'assessment', name: $("#_lang_AssessmentContacts").length ? $("#_lang_AssessmentContacts").val() : "Assessment Contacts" },
        ],
        optgroupField: 'class',
        optgroupLabelField: 'name',
        optgroupValueField: 'class',
        preload: true,
        render: {
            item: function (item, escape) {
                return '<div class="' + item.class + '">' + escape(item.name) + '</div>';
            }
        },
        onInitialize: function () {
            $(this.$input[0]).parent().find('.selectize-control div').block({ message: '<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>' });
        },
        load: function (query, callback) {
            if (query.length) return callback();
            var self = this;

            this.renderCache = {}
            var originEl = $(self.$input[0]);
            var selectedItems = originEl.data('items') ? originEl.data('items') : [];
            $.ajax({
                url: BASE_URL + '/api/assessment/contacts-users/options',
                type: 'GET',
                dataType: 'json',
                error: function () {
                    callback();
                },
                success: function (res) {
                    var data = res.data;
                    var selected_ids = [];
                    // Have to do it this way, because addition with simple addOption() will
                    // bug out when we deselect an option(it wouldn't be added back to the
                    // list of selectable items)
                    len = data.length;
                    for (var i = 0; i < len; i++) {
                        var item = data[i];
                        // Check if this element is selected one
                        for (var j = 0; j < selectedItems.length; j++) {
                            var selectedItem = selectedItems[j];
                            if (selectedItem.class == item.class && selectedItem.id == item.id) {
                                selected_ids.push(item.id + '_' + item.class);
                                break;
                            }
                        }

                        item.id += '_' + item.class;
                        self.registerOption(item);
                    }

                    if (selected_ids.length)
                        self.setValue(selected_ids);
                },
                complete: function () {
                    select_tag.parent().find('.selectize-control div').unblock({ message: null });
                }
            });
        }
    });
}
function setupQuestionnaireAssetsAssetGroupsWidget(select_tag) {

    if (!select_tag.length)
        return;

    var select = select_tag.selectize({
        sortField: 'text',
        plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
        delimiter: ',',
        create: function (input) {
            return { id: 'new_asset_' + input, name: input };
        },
        persist: false,
        valueField: 'id',
        labelField: 'name',
        searchField: 'name',
        sortField: 'name',
        optgroups: [
            { class: 'asset', name: 'Standard Assets' },
            { class: 'group', name: 'Asset Groups' }
        ],
        optgroupField: 'class',
        optgroupLabelField: 'name',
        optgroupValueField: 'class',
        preload: true,
        render: {
            item: function (item, escape) {
                return '<div class="' + item.class + '">' + escape(item.name) + '</div>';
            }
        },
        onInitialize: function () {
            select_tag.parent().find('.selectize-control div').block({ message: '<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>' });
        },
        load: function (query, callback) {
            if (query.length) return callback();
            $.ajax({
                url: BASE_URL + '/api/asset-group/options',
                type: 'GET',
                dataType: 'json',
                error: function () {
                    callback();
                },
                success: function (res) {
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
                complete: function () {
                    select_tag.parent().find('.selectize-control div').unblock({ message: null });
                }
            });
        }
    });
}

function redraw_control() {
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
        success: function (data) {
            if (data.status_message) {
                toastr.success(data.status_message);
            }
            if (data.controls) {
                var obj = $("#template_controls");
                $(obj).find("option").remove();
                $.each(data.controls, function (key, item) {
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
        error: function (xhr, status, error) {
            if (xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
            if (!retryCSRF(xhr, this)) {
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
        delimiter: ',',
        createFilter: function (input) { return input.length <= 255; },
        create: true,
        valueField: 'label',
        labelField: 'label',
        searchField: 'label'
    };
    // If options aren't provided, setup the selectize's preload to load them
    if (typeof options === 'undefined' || options.length == 0) {
        selectize_setup.preload = true;
        selectize_setup.load = function (query, callback) {
            if (query.length) return callback();

            $.ajax({
                url: BASE_URL + '/api/management/tag_options_of_types?type=risk,questionnaire_answer',
                type: 'GET',
                dataType: 'json',
                error: function () {
                    console.log('Error loading!');
                    callback();
                },
                success: function (res) {
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
        $('#questionnaire_edit_form select.assets-asset-groups-select-template').each(function (index, element) {
            $(element).attr('name', 'answers[assets_asset_groups][' + index + '][]');
        });

        $('#questionnaire_edit_form select.tags-template').each(function (index, element) {
            $(element).attr('name', 'answers[tags][' + index + '][]');
        });
    } else {
        $('#questionnaire_new_question_form select.assets-asset-groups-select').each(function (index, element) {
            $(element).attr('name', 'answers[assets_asset_groups][' + index + '][]');
        });

        $('#questionnaire_new_question_form select.tags').each(function (index, element) {
            $(element).attr('name', 'answers[tags][' + index + '][]');
        });
    }
}

function refreshSelectizeOptions(selector, data) {
    $(selector).each(function () {
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

/**
 * Logic for the tabbed templates to be able to go to the previous/next tabs
 */
$(document).on('click', '.navigation .prev_tab', function(event) {
    event.preventDefault();
    activate_tab($(this).closest('.tab-pane').prev().attr('id'));
});

$(document).on('click', '.navigation .next_tab', function(event) {
    event.preventDefault();
    activate_tab($(this).closest('.tab-pane').next().attr('id'));
});

function activate_tab(tab_id) {
    let nav_link = $('nav.nav.nav-tabs a.nav-link[data-bs-target=\'#' + tab_id + '\']');
    new bootstrap.Tab(nav_link).show();

    if (typeof(nav_link[0].scrollIntoViewIfNeeded) === 'function') {
        nav_link[0].scrollIntoViewIfNeeded({behavior:'smooth'});  
    } else {
        nav_link[0].scrollIntoView({behavior:'smooth'});
    }
}

