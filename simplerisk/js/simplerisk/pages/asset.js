var changed_value = false;

function updateAsset(e, self) {
    self || (self = $(this));

    var tr = self.closest('tr');
    if (tr) {
        var id = tr.data('id');
        if (id && !isNaN(id)) {
            if(self.hasClass('hasDatepicker'))
            {
                var fieldName = self.attr('name');
            }
            else
            {
                var fieldName = self.attr('id') ? self.attr('id') : self.attr('name');
            }
            var fieldValue = self.val();

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/assets/update_asset",
                data : {
                    id: id,
                    fieldName: fieldName,
                    fieldValue: fieldValue,
                },
                success: function(data){
                    changed_value = false;
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }
                    if (data.data) {
                        refreshSelectizeOptions(data.data)
                    }
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this)) {
                    	showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
        }
    }
}

function refreshSelectizeOptions(data) {
    $('.selectize-marker').each(function() {
        if (this.selectize) {
            // this.selectize.clearOptions();
            if (data && data.length) {
                this.selectize.addOption(data);
                this.selectize.refreshOptions(false);
            } else {
                this.selectize.close();
            }
        }
    });
}

var initialLoadStatus = 'None';

function initSelectizeOptions() {
    if (initialLoadStatus === 'None') {
        initialLoadStatus = 'Started';
        $.ajax({
            url: BASE_URL + '/api/management/tag_options_of_type?type=asset',
            type: 'GET',
            dataType: 'json',
            error: function() {
                console.log('Error loading!');
                callback();
            },
            success: function(res) {
                refreshSelectizeOptions(res.data)
                initialLoadStatus = 'Done';
            }
        });
    }
}

$(document).ready(function() {
    initSelectizeOptions();
});
