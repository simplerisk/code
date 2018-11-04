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
                    assets: $(this).find('[name="assets[]"]').val(),
                    
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
                });
            })
            
            assessment.answers = answers;
            assessments.push(assessment);
        })
        
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/assessment/update?assessment_id=" + assessment_id,
            data: JSON.stringify(assessments),
            headers: {
                'CSRF-TOKEN': csrfMagicToken
            },
            contentType: "application/json",
            success: function(data){
                if(data.status_message){
                    var messageHtml = '<div id="alert" class="container-fluid">'
                     +      '<div class="row-fluid">'
                     +          '<div class="span10 greenalert"><span><i class="fa fa-check"></i>' + data.status_message + '</span></div>'
                     +      '</div>'
                     + '</div>';
                     
                    $('#show-alert').html(messageHtml);
                    setTimeout(function(){
                        $("#alert").fadeOut('slow');
                    }, 5000);
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

    
})

/**
* Add a new answer row in Edit Assessment
* 
*/
function addRow(tableID){
    $("#" + tableID).append($("#adding_row").html());
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