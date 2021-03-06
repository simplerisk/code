/**
* When a file is added, should call this method
* 
* @param $parent
* @param currentButtonId: button ID for input[type=file].active
*/
function refreshFilelist($parent, currentButtonId){
    var files = $("input[type=file]", $parent);

    var filesHtml = "";
    var filesLength = 0;
    $(files).each(function() {
        if(!$(this)[0].files.length){
            return;
        }
        $(this).attr("id", "file-upload-"+filesLength)
        var name = escapeHtml($(this)[0].files[0].name);
        
        filesHtml += "<li >\
            <div class='file-name'>"+name+"</div>\
            <a href='#' class='remove-file' data-id='file-upload-"+filesLength+"'><i class='fa fa-remove'></i></a>\
        </li>";
        filesLength++;
    });
    $parent.find('.file-list').html(filesHtml);
    var totalFilesLength = $('.exist-files > li', $parent).length + filesLength;
    if(totalFilesLength > 1){
        $msg = "<span class='file-count'>" + totalFilesLength + "</span> Files Added"; 
    }else{ 
        $msg = "<span class='file-count'>" + totalFilesLength + "</span> File Added"; 
    }
    $parent.find('.file-count-html').html($msg);

    var name = $parent.find('.file_name').data('file');
    if(!name)
        name = "file";

    if(currentButtonId){
        $parent.prepend($('<input id="'+currentButtonId+'" name="'+name+'[]" class="hidden-file-upload active" type="file">'))
    }
    
}
/**
* HTMLSPECIALCHARS
* 
* @param text
*/
function escapeHtml(text) {
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
/**
* popup when click "Score Using CVSS"
* 
* @param parent
*/
function popupcvss(parent)
{
    parentOfScores = parent;
    
    var cve_id = $("#reference_id", parent).val();
    var pattern = /cve\-\d{4}-\d{4}/i;

    // If the field is a CVE ID
    if (cve_id !== undefined && cve_id.match(pattern))
    {
        my_window = window.open(BASE_URL + '/management/cvss_rating.php?cve_id='+ cve_id ,'popupwindow','width=850,height=680,menu=0,status=0');
    }
    else my_window = window.open(BASE_URL + '/management/cvss_rating.php','popupwindow','width=850,height=680,menu=0,status=0');
    
}

/**
* popup when click "Score Using DREAD"
* 
*/
function popupdread(parent)
{
    parentOfScores = parent;
    my_window = window.open(BASE_URL + '/management/dread_rating.php','popupwindow','width=660,height=500,menu=0,status=0');
}

/**
* popup when click "Score Using OWASP"
* 
*/
function popupowasp(parent)
{
    parentOfScores = parent;
    my_window = window.open(BASE_URL + '/management/owasp_rating.php','popupwindow','width=665,height=570,menu=0,status=0');
}

/**
* popup when click "Score Using Contributing Risk"
* 
*/
function popupcontributingrisk(parent)
{
    parentOfScores = parent;
    my_window = window.open(BASE_URL + '/management/contributingrisk_rating.php','popupwindow','width=665,height=570,menu=0,status=0');
}

function closepopup()
    {
    if(false == my_window.closed)
    {
        my_window.close ();
    }
    else
    {
        alert('Window already closed!');
    }
}

/**
* Create and Update the risk scoring chart
* 
* @param risk_id
*/
function riskScoringChart(renderTo, risk_id, risk_levels){
    var backgroundColor = "#f5f5f5";
    // Creates stops array
    var stops = [
        [0, backgroundColor],
    ];
    
    risk_levels.sort(function(a, b){
        if(Number(a.value) > Number(b.value) ){
            return -1;
        }
        if(Number(a.value) < Number(b.value) ){
            return 1;
        }
    })
    risk_levels.push({value: 0, color: "#fff"});
    
    var to = 10;
    var plotBands = [];
    for(var i=0; i<risk_levels.length; i++){
        var risk_level = risk_levels[i];
        plotBands.push({
            color: risk_level.color,
            to: to,
            from: Number(risk_level.value),
        })
        to = Number(risk_level.value);
    }
    // For all plots, change Date axis to local timezone
    Highcharts.setOptions({                                            
        global : {
            useUTC : false
        }
    });
    var chartObj = new Highcharts.Chart( {
        chart: {
            renderTo: renderTo,
            type: 'spline',
        },
        title: {
            text: $('#_RiskScoringHistory').length ? $("#_RiskScoringHistory").val() : 'Risk Scoring History'
        },
        yAxis: [{
            title: {
                text: $('#_RiskScore').length ? $('#_RiskScore').val() : "Risk Score"
            },
            min: 0, 
            max: 10,
            gridLineWidth: 0, 
            plotBands: plotBands,
        }],
        xAxis: [{
            type: 'datetime',
            dateTimeLabelFormats: { // don't display the dummy year
                millisecond: '%Y-%m-%d<br/>%H:%M:%S',
                second: '%Y-%m-%d<br/>%H:%M:%S',
                minute: '%Y-%m-%d<br/>%H:%M',
                hour: '%Y-%m-%d<br/>%H:%M',
                day: '%Y-%m-%d<br/>%H:%M',
                month: '%Y-%m-%d<br/>%H:%M',
                year: '%Y-%m-%d<br/>%H:%M'
            },
            title: {
                text: $("#_DateAndTime").val() ? $("#_DateAndTime").val() : "Date and time"
            }
        }],
        legend: {
            layout: 'vertical',
            align: 'right',
            verticalAlign: 'middle'
        },
        plotOptions: {
            spline: {
                marker: {
                    enabled: true
                }
            }                    
        },
        series: [
            {name: $('#_RiskScore').length ? $('#_RiskScore').val() : "Inherent Risk" },
            {name: $('#_ResidualRiskScore').length ? $('#_ResidualRiskScore').val() : "ResidualRisk Score" },
        ]

    });
    

    chartObj.showLoading('<img src="../images/progress.gif">');
    $.ajax({
        type: "GET",
        url: BASE_URL + "/api/management/risk/residual_scoring_history?id=" + risk_id,
        dataType: 'json',
        success: function(data){
            var residual_histories = data.data;
            var residualChartData = [];
            for(var i=0; i<residual_histories.length; i++){
                // var date = new Date(histories[i].last_update.replace(/\s/, 'T'));
                // Added the three lines below to make the timestamp work properly with Safari
                var parts = residual_histories[i].last_update.split(/[ \/:-]/g);
                var dateFormatted = parts[1] + "/" + parts[2] + "/" + parts[0] + " " + parts[3] + ":" + parts[4] + ":" + parts[5];
                var date = new Date(dateFormatted);
                residualChartData.push([date.getTime(), Number(residual_histories[i].residual_risk)]);
            }
            
            chartObj.series[1].setData(residualChartData)
            chartObj.hideLoading();
            
        },
        error: function(xhr,status,error){
            if(xhr.responseJSON && xhr.responseJSON.status_message){
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
        }
    })
    $.ajax({
        type: "GET",
        url: BASE_URL + "/api/management/risk/scoring_history?id=" + risk_id,
        dataType: 'json',
        success: function(data){
            var histories = data.data;
            var chartData = [];
            for(var i=0; i<histories.length; i++){
                // var date = new Date(histories[i].last_update.replace(/\s/, 'T'));
                // Added the three lines below to make the timestamp work properly with Safari
                var parts = histories[i].last_update.split(/[ \/:-]/g);
                var dateFormatted = parts[1] + "/" + parts[2] + "/" + parts[0] + " " + parts[3] + ":" + parts[4] + ":" + parts[5];
                var date = new Date(dateFormatted);
                chartData.push([date.getTime(), Number(histories[i].calculated_risk)]);
            }
            
            chartObj.series[0].setData(chartData)

            chartObj.hideLoading();
            
        },
        error: function(xhr,status,error){
            if(xhr.responseJSON && xhr.responseJSON.status_message){
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
        }
    })
}

function alert(message){
    var modal_container_id = "alert-modal";
    if(!$("#" + modal_container_id).length){
        var modal_html = '';
        modal_html += '<div id="' + modal_container_id + '" class="modal hide fade" tabindex="-1" role="dialog" aria-hidden="true">';
            modal_html += '<div class="modal-body">';

              modal_html += '<div class="form-group text-center message-container">';
                modal_html += '<label class="message">'+message+'</label>'
              modal_html += '</div>';

              modal_html += '<div class="form-group text-center">';
                modal_html += '<button class="btn btn-danger" data-dismiss="modal" aria-hidden="true">OK</button>';
              modal_html += '</div>';
            modal_html += '</div>';
        modal_html += '</div>';
        $("body").append(modal_html);
    }
    
    $("#" + modal_container_id+" .message").html(message);
    
    $("#" + modal_container_id).modal('show');
}

function confirm(message, callback){
    var modal_container_id = "confirm-modal";
    if(!$("#" + modal_container_id).length){
        var modal_html = '';
        modal_html += '<div id="' + modal_container_id + '" class="modal hide fade" tabindex="-1" role="dialog" aria-hidden="true">';
            modal_html += '<div class="modal-body">';

              modal_html += '<div class="form-group text-center message-container">';
                modal_html += '<label class="message">'+message+'</label>'
              modal_html += '</div>';

              modal_html += '<div class="form-group text-center">';
                modal_html += '<button class="btn btn-default " data-dismiss="modal" aria-hidden="true" >Cancel</button>';
                modal_html += '&nbsp;&nbsp;&nbsp;';
                modal_html += '<button class="btn btn-danger" data-dismiss="modal" aria-hidden="true" onclick="'+ callback +'">Yes</button>';
              modal_html += '</div>';
            modal_html += '</div>';
        modal_html += '</div>';
        $("body").append(modal_html);
    }
    
    $("#" + modal_container_id+" .message").html(message);
    
    $("#" + modal_container_id).modal('show');
}

function checkAndSetValidation(container)
{
    var issue_els = [];
    $("input, select, textarea", container).each(function(){
        if($(this).prop('required') && (!$.trim($(this).val()) || (Array.isArray($(this).val()) && $(this).val().length==0) ) ){
            issue_els.push($(this));
        }
    })
    // If issue elements exist, stop progress
    if(issue_els.length > 0)
    {
        var error_messages = [];
        issue_els.reverse();
        for(var key in issue_els){
            var issue_el = issue_els[key];
            
            if(issue_el.parent().hasClass("multiselect-native-select")){
                issue_el.parent().find("button.multiselect").addClass("error")
                issue_el.parent().find("button.multiselect").focus()
            }else{
                issue_el.addClass("error");
                issue_el.focus()
            }

            // We have to make sure that no html gets through to toastr as it's displaying what it gets 'as is';
            var escaped = $("<div/>").text(issue_el.attr("title")).html();
            var message = field_required_lang.replace("_XXX_", escaped);

            showAlertFromMessage(message, false)
        }
        return false;
    }
    else
    {
        return true;
    }
}

var loading={
    show:function(el)
    {
        this.getID(el).style.display='';
    },
    hide:function(el)
    {
        this.getID(el).style.display='none';
    },
    getID:function(el)
    {
        return document.getElementById(el);
    }
}

$(document).ready(function(){
    if(jQuery.ui !== undefined){
        jQuery.ui.autocomplete.prototype._resizeMenu = function () {
            var ul = this.menu.element;
            ul.outerWidth(this.element.outerWidth());
        }                
    }

    $(document).on('click', '.exist-files .remove-file', function(event) {
        event.preventDefault();
        var $parent = $(this).parents('.file-uploader');
        var fileCount = Number($parent.find('.file-count').html()) - 1
        $parent.find('.file-count').html(fileCount)
        $(this).parent().remove();
    })
    
    $(document).on('click', '.file-list .remove-file', function(event) {
        event.preventDefault();
        var id = $(this).data('id');
        var $parent = $(this).parents('.file-uploader');
        $("#"+id, $parent).remove();
        refreshFilelist($parent)
    })
    
    $(document).on('change', '.hidden-file-upload.active', function(event) {
        var $parent = $(this).parents('.file-uploader');
        $(this).removeClass("active")
        var currentButtonId = $(this).attr('id');
        
        refreshFilelist($parent, currentButtonId)

    });
    
    $('body').on('click', '.show-score-overtime', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.risk-session');
            
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/risk_levels",
            dataType: 'json',
            success: function(result){
                var risk_id = $('.large-text', tabContainer).html();
                $('.score-overtime-container', tabContainer).show();

                var risk_levels = result.data.risk_levels;
                riskScoringChart($('.score-overtime-chart', tabContainer)[0], risk_id, risk_levels);

                $('.hide-score-overtime', tabContainer).show();
                $('.show-score-overtime', tabContainer).hide();
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
        
        return false;
    })

    $('body').on('click', '.hide-score-overtime', function(e){
        e.preventDefault();

        var tabContainer = $(this).parents('.risk-session');
        var risk_id = $('.large-text', tabContainer).html();

        $('.score-overtime-container', tabContainer).hide();
        $('.hide-score-overtime', tabContainer).hide();
        $('.show-score-overtime', tabContainer).show();

        return false;
    })
    if($('#tab-container .datepicker').length){
        $('#tab-container .datepicker').datepicker();
    }
    
    if($("#tab-container .multiselect").length){
        $("#tab-container .multiselect").multiselect({buttonWidth: '100%'});
    }
    
})
