function showAlertsFromArray(alertData) {
    if (alertData) {
        var alertData = JSON.parse(alertData);
        for (index = 0; index < alertData.length; ++index) {
            a = alertData[index];
            console.log(a);
            if (a["alert_type"] == "success")
                toastr.success(a["alert_message"]);
            else
                toastr.error(a["alert_message"]);
        }    
    }
}