
function verify_discard_or_delete_asset(action, _this) {

    var id = _this.data('id');

    if (id) {
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/assets/" + action + "_asset?id=" + id,
            headers: {
                'CSRF-TOKEN': csrfMagicToken
            },
            contentType: "application/json",
            success: function(data){
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }

                $.ajax({
                    type: "GET",
                    url: BASE_URL + "/api/assets/verified_asset_body",
                    headers: {
                        'CSRF-TOKEN': csrfMagicToken
                    },
                    contentType: "application/json",
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
