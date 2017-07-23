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
        var name = $(this)[0].files[0].name;
        filesHtml += "<li >\
            <div class='file-name'>"+name+"</div>\
            <a href='#' class='remove-file' data-id='file-upload-"+filesLength+"'><i class='fa fa-remove'></i></a>\
        </li>";
        filesLength++;
    });
    $($parent).find('.file-list').html(filesHtml);
    var totalFilesLength = $('.exist-files > li', $parent).length + filesLength;
    if(totalFilesLength > 1){
        $msg = "<span class='file-count'>" + totalFilesLength + "</span> Files Added"; 
    }else{ 
        $msg = "<span class='file-count'>" + totalFilesLength + "</span> File Added"; 
    }
    $($parent).find('.file-count-html').html($msg);
    if(currentButtonId){
        $parent.prepend($('<input id="'+currentButtonId+'" name="file[]" class="hidden-file-upload active" type="file">'))
    }
    
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
    if (cve_id.match(pattern))
    {
        my_window = window.open('cvss_rating.php?cve_id='+ cve_id ,'popupwindow','width=850,height=680,menu=0,status=0');
    }
    else my_window = window.open('cvss_rating.php','popupwindow','width=850,height=680,menu=0,status=0');
    
}

/**
* popup when click "Score Using DREAD"
* 
*/
function popupdread(parent)
{
    parentOfScores = parent;
    my_window = window.open('dread_rating.php','popupwindow','width=660,height=500,menu=0,status=0');
}

/**
* popup when click "Score Using OWASP"
* 
*/
function popupowasp(parent)
{
    parentOfScores = parent;
    my_window = window.open('owasp_rating.php','popupwindow','width=665,height=570,menu=0,status=0');
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
    var chartObj = new Highcharts.Chart( {
        chart: {
            renderTo: renderTo,
            type: 'spline',
//            backgroundColor: {
//                linearGradient: [0, 0, 0, 400],
//               stops: stops,
//            },
        },
        title: {
            text: $('#_RiskScoringHistory').length ? $("#_RiskScoringHistory").val() : 'Risk Scoring History'
        },

        yAxis: {
            title: {
                text: $('#_RiskScore').length ? $('#_RiskScore').val() : "Risk Score"
            },
            min: 0, 
            max: 10,
            gridLineWidth: 0, 
            plotBands: plotBands,
        },
         xAxis: {
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
        },
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
            {name: $('#_RiskScore').length ? $('#_RiskScore').val() : "Risk Score" }
        ]

    });
    

    chartObj.showLoading('<img src="../images/progress.gif">');
    $.ajax({
        type: "GET",
        url: "../api/management/risk/scoring_history?id=" + risk_id,
        dataType: 'json',
        success: function(data){
            var histories = data.data;
            var chartData = [];
            for(var i=0; i<histories.length; i++){
                var date = new Date(histories[i].last_update.replace(/\s/, 'T'));
                chartData.push([date.getTime(), Number(histories[i].calculated_risk)]);
            }
            
            chartObj.series[0].setData(chartData)
            chartObj.hideLoading();
            
        },
        error: function(xhr,status,error){
            if(xhr.responseJSON && xhr.responseJSON.status_message){
                $('#show-alert').html(xhr.responseJSON.status_message);
            }
        }
    })
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
//        event.preventDefault();

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
            url: "../api/risk_levels",
            dataType: 'json',
            success: function(result){
                var risk_id = $('.large-text', tabContainer).html();
                $('.score-overtime-container', tabContainer).show();

                var risk_levels = result.data.risk_levels;
                
                riskScoringChart($('.socre-overtime-chart', tabContainer)[0], risk_id, risk_levels);

                $('.hide-score-overtime', tabContainer).show();
                $('.show-score-overtime', tabContainer).hide();
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
    
    if($("#tab-container .multiselect").length){
        $("#tab-container .multiselect").multiselect();
    }
    
})