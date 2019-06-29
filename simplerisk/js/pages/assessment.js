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

function setupAssetsAssetGroupsWidget(select_tag) {
   
    if (!select_tag.length)
        return;
    
    var select = select_tag.selectize({
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
        },
        load: function(query, callback) {
            if (query.length) return callback();
            $.ajax({
                url: '/api/asset-group/options',
                type: 'GET',
                dataType: 'json',
                error: function() {
                    callback();
                },
                success: function(res) {
                    var data = res.data;
                    var control = select[0].selectize;
                    // Have to do it this way, because addition with simple addOption() will
                    // bug out when we deselect an option(it wouldn't be added back to the
                    // list of selectable items)
                    len = data.length;

                    for (var i = 0; i < len; i++) {
                        var item = data[i];
                        if (item.class == 'asset')
                            item.id = item.name;
                        else item.id = '[' + item.name + ']';
                        control.registerOption(item);
                    }
                },
            });
        }
    });
}
