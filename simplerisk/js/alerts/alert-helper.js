function showAlertsFromArray(alertData, success) {
    if (alertData) {
        try {
            var alertData = JSON.parse(alertData);
            for (index = 0; index < alertData.length; ++index) {
                a = alertData[index];
                if (a["alert_type"] == "success")
                    toastr.success(a["alert_message"]);
                else
                    toastr.error(a["alert_message"]);
            }
        } catch(e) {
            showAlertFromMessage(alertData, success)
        }
    }
}

function showAlertFromMessage(message, success){
    if(success)
    {
        toastr.success(message);
    }
    else
    {
        toastr.error(message);
    }
}

function showAlertsFromHiddenArray(res)
{
    $tempDiv = $('<div class="hide"></div>').html($.parseHTML(res));
    var objs = $('.hidden-alert-message', $tempDiv);
    
    objs.each(function(){
        var alert_message = $(this).html();
        var success = $(this).data('type') == "success" ? true : false;
        showAlertFromMessage(alert_message, success);
    })

    $tempDiv.remove();
}