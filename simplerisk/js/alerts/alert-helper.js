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