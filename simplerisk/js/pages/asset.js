
function verify_discard_or_delete_asset(action, _this) {

    var id = _this.data('id');

    if (id) {
        $.blockUI({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/assets/" + action + "_asset?id=" + id,
            success: function(data){
                if (action == "verify") {
                    verifyMessage = data.status_message
                    $.ajax({
                        type: "GET",
                        url: BASE_URL + "/api/assets/verified_asset_body",
                        success: function(data){
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }
                            if (verifyMessage) {
                                showAlertsFromArray(verifyMessage);
                            }
                            //Display the verified list
                            $('#verified_asset_table').find('tbody').html(data.data);
                            $('#verified_asset_table_wrapper').show();

                            //Add the onClick event handlers to the delete buttons
                            $("#verified_asset_table button.delete-asset").click(function() {
                                verify_discard_or_delete_asset("delete", $(this));
                            });

                            // Hide the table for the verified assets if there aren't any
                            if ($('#verified_asset_table tbody tr').length == 0) {
                                $('#verified_asset_table_wrapper').hide();

                                // Hide the "Edit Assets" if there's no unverified asset either
                                if ($('#unverified_asset_table tbody tr').length == 0) {
                                    $('#EditAssets').hide();
                                }
                            }
                        },
                        error: function(xhr,status,error){
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                            if(!retryCSRF(xhr, this))
                            {
                            }
                        },
                        complete: function() {
                            // Doing it here, so the UI is not "jumping"
                            var tr = _this.closest('tr');
                            if (tr)
                                tr.remove();

                            if ($('#unverified_asset_table tbody tr').length == 0) {
                                $('#unverified_assets').hide();
                            }

                            $.unblockUI();
                        }
                    });
                } else {
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }
                }
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
                if(!retryCSRF(xhr, this))
                {
                }
            },
            complete: function() {
                // Doing it here, so the UI is not "jumping"
                if (action != "verify") {
                    var tr = _this.closest('tr');
                    if (tr)
                        tr.remove();

                    if ($('#unverified_asset_table tbody tr').length == 0) {
                        $('#unverified_assets').hide();
                    }

                    // Hide the table for the verified assets if there aren't any
                    if ($('#verified_asset_table tbody tr').length == 0) {
                        $('#verified_asset_table_wrapper').hide();

                        // Hide the "Edit Assets" if there's no unverified asset either
                        if ($('#unverified_asset_table tbody tr').length == 0) {
                            $('#EditAssets').hide();
                        }
                    }

                    $.unblockUI();
                }
            }
        });
    }
}


function verify_discard_or_delete_all_assets(action, _this) {

    var _form = _this.closest('form');
    var ids = [];
    var verifyMessage = false;
    $('[name="assets[]"]', _form).each(function (e) {
        ids.push($(this).val());
    });

    $.blockUI({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
    $.ajax({
        type: "POST",
        url: BASE_URL + "/api/assets/" + action + "_assets",
        data: {ids:JSON.stringify(ids)},
        success: function(data){
            if(data.status_message){
                if (action == "verify")
                    verifyMessage = data.status_message;
                else
                    showAlertsFromArray(data.status_message);
            }

            if (action == "delete") {// delete all
                $('#verified_asset_table_wrapper').hide();
                _form.find("tr").remove();
                // Hide the table for the verified assets if there aren't any
                // Hide the "Edit Assets" if there's no unverified asset either
                if ($('#unverified_asset_table tbody tr').length == 0) {
                    $('#EditAssets').hide();
                }
            } else {
                if (action == "discard") { // discard all
                    $('#unverified_assets').hide();
                    _form.find("tr").remove();
                    // Hide the table for the verified assets if there aren't any
                    if ($('#verified_asset_table tbody tr').length == 0) {
                        $('#EditAssets').hide();
                    }
                } else { //verify all
                    //location.reload(true);
                    $.ajax({
                        type: "GET",
                        url: BASE_URL + "/api/assets/verified_asset_body",
                        success: function(data){
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }
                            if (verifyMessage) {
                                showAlertsFromArray(verifyMessage);
                            }

                            //Display the verified list
                            $('#verified_asset_table').find('tbody').html(data.data);
                            $('#verified_asset_table_wrapper').show();

                            $('#unverified_assets').hide();
                            _form.find("tr").remove();

                            //Add the onClick event handlers to the delete buttons
                            $("#verified_asset_table button.delete-asset").click(function() {
                                verify_discard_or_delete_asset("delete", $(this));
                            });
                        },
                        error: function(xhr,status,error){
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                            if(!retryCSRF(xhr, this))
                            {
                            }
                        },
                        complete: function(xhr,status) {
                            $.unblockUI();
                        }
                    });
                }
            }
        },
        error: function(xhr,status,error){
            if(xhr.responseJSON && xhr.responseJSON.status_message){
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
            if(!retryCSRF(xhr, this))
            {
            }
        },
        complete: function() {
            console.log(action);
            if (action != "verify") {
                $.unblockUI();
            }
        }
    });
}

function refreshVerifiedAssets() {
                    $.ajax({
                    type: "GET",
                    url: BASE_URL + "/api/assets/verified_asset_body",
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }
                        //Display the verified list
                        $('#verified_asset_table').find('tbody').html(data.data);
                        $('#verified_asset_table_wrapper').show();

                        //Add the onClick event handlers to the delete buttons
                        $("#verified_asset_table button.delete-asset").click(function() {
                            verify_discard_or_delete_asset("delete", $(this));
                        });

                        // Hide the table for the verified assets if there aren't any
                        if ($('#verified_asset_table tbody tr').length == 0) {
                            $('#verified_asset_table_wrapper').hide();

                            // Hide the "Edit Assets" if there's no unverified asset either
                            if ($('#unverified_asset_table tbody tr').length == 0) {
                                $('#EditAssets').hide();
                            }
                        }
                    },
                    error: function(xhr,status,error){
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                        if(!retryCSRF(xhr, this))
                        {
                        }
                    },
                    complete: function() {
                        // Doing it here, so the UI is not "jumping"
                        if (action != "delete") {
                            var tr = _this.closest('tr');
                            if (tr)
                                tr.remove();
                        }

                        if ($('#unverified_asset_table tbody tr').length == 0) {
                            $('#unverified_assets').remove();
                        }
                    }
                });
}



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
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }
                    if (data.data) {
                        refreshSelectizeOptions(data.data)
                    }
                },
                error: function(xhr,status,error){
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                    if(!retryCSRF(xhr, this))
                    {
                    }
                }
            });
        }
    }
}

function refreshSelectizeOptions(data) {
    $('.selectize-marker').each(function() {
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

var initialLoadStatus = 'None';

function initSelectizeOptions() {
    if (initialLoadStatus === 'None') {
        initialLoadStatus = 'Started';
        $.ajax({
            url: '/api/management/tag_options_of_type?type=asset',
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
