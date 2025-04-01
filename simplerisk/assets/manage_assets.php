<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'tabs:logic', 'datetimerangepicker', 'CUSTOM:pages/asset.js', 'CUSTOM:common.js'], ['check_assets' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/assets.php'));

    $control_options = array_map(function($control) {
        return array(
            'value' => $control['id'],
            'name' => $control['short_name'],
        );
    }, get_framework_controls_by_filter("all", "all", "all", "all", "all", "all", "all", "all", "", "all"));
?>
<div class="row my-2">
    <div class="col-12">
        <div>
            <nav class="nav nav-tabs">
                <a class="btn btn-primary" data-bs-target="#create_popup_modal-asset_verified" data-bs-toggle="modal"><i class="fa fa-plus"></i></a>
                <a class="nav-link active" data-bs-target="#verified_assets" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['VerifiedAssets']); ?></a>
                <a class="nav-link" data-bs-target="#unverified_assets" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['UnverifiedAssets']); ?></a>
            </nav>
        </div>
        <div class="tab-content">
            <div class="tab-pane active card-body border mt-2" id="verified_assets" tabindex="0">
                <div class="hero-unit" data-view="asset_verified">
                    <div class="row">
                        <div class="col-10">
                            <button data-action="delete" class="btn btn-primary asset-view-action"><?= $escaper->escapeHtml($lang['DeleteAll']); ?></button>
                        </div>
                        <div class="col-2">
                            <div style="float: right;">
                                <?php render_column_selection_widget('asset_verified'); ?>
                            </div>        
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <?php render_view_table('asset_verified'); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button data-action="delete" class="btn btn-primary asset-view-action"><?= $escaper->escapeHtml($lang['DeleteAll']); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane card-body border mt-2" id="unverified_assets" tabindex="0">
                <div data-view="asset_unverified">                        
                    <div class="row">
                        <div class="col-10">
                            <button data-action="verify" class="btn btn-primary asset-view-action"><?= $escaper->escapeHtml($lang['VerifyAll']); ?></button>
                            <button data-action="discard" class="btn btn-primary asset-view-action"><?= $escaper->escapeHtml($lang['DiscardAll']); ?></button>
                        </div>
                        <div class="col-2">
                            <div style="float: right;">
                                <?php render_column_selection_widget('asset_unverified'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <?php render_view_table('asset_unverified'); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button data-action="verify" class="btn btn-primary asset-view-action"><?= $escaper->escapeHtml($lang['VerifyAll']); ?></button>
                            <button data-action="discard" class="btn btn-primary asset-view-action"><?= $escaper->escapeHtml($lang['DiscardAll']); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_create_modal('asset_verified'); ?>

<div id="confirm-view-action" class="modal " tabindex="-1" role="dialog">
    <div class='modal-dialog modal-md modal-dialog-centered'>
        <div class='modal-content'>
            <div class='modal-header'>
                <h4 class='modal-title'><?= $escaper->escapeHtml($lang['Confirmation']); ?></h4>
                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
            </div>
            <div class="modal-body">
                <div class="form-group text-center">
                    <label for="" class="confirm-message verify"><?= $escaper->escapeHtml($lang['ConfirmVerifyAllAssets']); ?></label>
                    <label for="" class="confirm-message discard"><?= $escaper->escapeHtml($lang['ConfirmDiscardAllAssets']); ?></label>
                    <label for="" class="confirm-message delete"><?= $escaper->escapeHtml($lang['ConfirmDeleteAllAssets']); ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button class="btn btn-submit proceed" data-action="" data-view=""><?= $escaper->escapeHtml($lang['Yes']); ?></button>
            </div>
        </div>
    </div>
</div>
<div id="add_control_row" class="hide">
    <table>
        <tr>
            <td><?php create_dropdown("control_maturity", rename: "control_maturity[]", blank: false, customHtml: "required"); ?></td>
            <td><?php create_multiple_dropdown("control_id", customHtml: "required", blankText: $lang['NoneSelected'], blankValue: 0, options: $control_options, additionalClasses: 'edit_input'); ?></td>
            <td class="text-center">
                <input type='text' name='mapped_controls[]' style='display: none'/>
                <a href="javascript:void(0);" class="control-block--delete-mapping" title="<?= $escaper->escapeHtml($lang["Delete"]);?>"><i class="fa fa-trash"></i></a>
            </td>
        </tr>
    </table>
</div>
<script>
    $(function() {
        $('.datepicker').initAsDatePicker();

        // Event handler for the row level actions
        $('body').on('click', 'button.asset-row-action', function(e) {
            e.preventDefault();

            var _this = $(this);
            var id = _this.closest('span').data('id');
            var action = _this.data('action');
            var view = _this.closest('table').data('view');

            // If the action is delete, we need to show the popup to confirm the action
            if (action == 'delete') {

                confirm("<?= $escaper->escapeHtml($lang["AreYouSureYouWantToDeleteSelction"]) ?>", () => {
                    handle_asset_row_action(id, action, view);
                });

            // If the action isn't delete, we don't need to show the popup, just call the handler
            } else {

                handle_asset_row_action(id, action, view); 

            }
        
        });

        // Event handler for the asset row action
        function handle_asset_row_action(id, action, view) {

            $.blockUI({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/v2/assets/view/action",
                data: {
                    id: id,
                    action: action,
                    view: view
                },
                success: function(data) {
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    // In case of editing the API call returns with the edited asset's data
                    // we have to populate the popup's fields and show it to the user
                    if (action == 'edit') {
                        // Iterate through all the fields in the form and populate them
                        $('select.edit_input, input.edit_input, textarea.edit_input, table.mapping_control_table', $(`#edit_popup_modal-${view}`)).each(function() {
                            var tag = $(this);

                            // Theoretically it's already uppercase, so it's just to make sure
                            var tagName = tag[0].tagName.toUpperCase();

                            // Remove the trailing '[]' from the name of multiselects
                            var name = tag.attr("name").replace(/[\[\]']+/g,'');

                            var value = (data.data[name] !== undefined ? data.data[name] : false);

                            if (tagName == 'SELECT' && tag.hasClass('selectized')) {
                                tag[0].selectize.setValue(value ? value : []);
                            } else if (tagName == 'SELECT' && tag.hasClass('multiselect')) {

                                // Have to do this in case the value is empty to deselect the previous selection
                                // in the other cases it's enough to set the value as empty, but for multiselect it just doesn't...
                                tag.find('option:selected').each(function() {
                                    $(this).prop('selected', false);
                                })
                                tag.multiselect('refresh');

                                if (value) {
                                    tag.multiselect('select', value);
                                }
                            } else if (tagName == 'TABLE' && tag.hasClass('mapping_control_table')){
                                let mapping_control_table_container = tag.find('tbody');
                                mapping_control_table_container.html('');
                                value.forEach((mapping) => {
                                    let mapping_row = $($("#add_control_row table tr:first-child").parent().html());

                                    mapping_row.find("select[name='control_maturity[]']").val(mapping['control_maturity']);
                                    mapping_row.find("select[name='control_id[]']").val(mapping['control_id']);
                                
                                    mapping_row.find("select[name='control_id[]']").multiselect({buttonWidth: '100%', maxHeight: 250,enableFiltering: true});
                                    
                                    mapping_control_table_container.append(mapping_row);
                                });

                            } else {
                                tag.val(value ? value : '');
                            }
                        });
                        $("select.mapped_control").multiselect({buttonWidth: 260, maxHeight: 250,enableFiltering: true});
                        if(data.data['details'] != undefined) {
                            if(view == 'asset_verified') tinyMCE.get("edit_details-asset_verified-asset_fields").setContent(data.data['details']);
                            if(view == 'asset_unverified') tinyMCE.get("edit_details-asset_unverified-asset_fields").setContent(data.data['details']);
                        }

                        $.unblockUI();
                        $(`#edit_popup_modal-${view}`).modal("show");
                    } else {
                        // Adding this one-fire event to make sure we only unblock the UI when the datatable is done refreshing
                        datatableInstances[view].one('xhr', function (e, settings, json) {
                            $.unblockUI();
                        });
                        
                        // Re-draw the view
                        datatableInstances[view].draw();

                        if (action == 'verify') {
                            // In case of verification have to re-draw the other table too as the verified asset might appear there
                            datatableInstances['asset_verified'].draw();
                        }
                    }
                },
                error: function(xhr,status,error){
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                    if(!retryCSRF(xhr, this)) {}
                },
                complete: function() { }
            });
        }

        // Event handler for the view level actions
        $('body').on('click', 'button.asset-view-action', function(e) {
            e.preventDefault();

            var _this = $(this);
            var proceed_button = $('div#confirm-view-action button.proceed');
            var action = _this.data('action');

            // Set the required data on the 'Yes' button so the event handler knows which action got confirmed 
            proceed_button.data('action', action);
            proceed_button.data('view', _this.closest('div.hero-unit').data('view'));

            // Show the right confirmation question
            $('#confirm-view-action label.confirm-message').hide();
            $('#confirm-view-action label.' + action).show();
            
            // Show the confirmation window
            $('#confirm-view-action').modal('show');
        });
        
        // Handle the view level actions once they're confirmed
        $('body').on('click', 'div#confirm-view-action button.proceed', function(e) {
            e.preventDefault();
            $('#confirm-view-action').modal('hide');

            var _this = $(this);
            var action = _this.data('action');
            var view = _this.data('view');

            $.blockUI({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/v2/assets/view/action",
                data: {
                    action: action,
                    all: true
                },
                success: function(data) {
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    // Re-draw the view
                    datatableInstances[view].draw();

                    if (action == 'verify') {
                        // In case of verification have to re-draw the other table too as the verified asset might appear there
                        datatableInstances['asset_verified'].draw();
                    }
                    
                },
                error: function(xhr,status,error){
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                    if(!retryCSRF(xhr, this)) {}
                },
                complete: function() {
                    $.unblockUI();
                }
            });
        });
        // Event handler for add control actions
        $('body').on('click', 'button.add-control', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            // To get the html of the <tr> tag
            $(".mapping_control_table tbody", form).append($("#add_control_row table tr:first-child").parent().html());
            $(".mapping_control_table tbody select[name='control_id[]']", form).multiselect({buttonWidth: '100%', maxHeight: 250, enableFiltering: true});
        });

        $('body').on('click', '.control-block--delete-mapping', function(e) {
            e.preventDefault();
            $(this).closest("tr").remove();
        });
        // init tinyMCE WYSIWYG editor
        init_minimun_editor('#create_details-asset_verified-asset_fields');
        init_minimun_editor('#edit_details-asset_unverified-asset_fields');
        init_minimun_editor('#edit_details-asset_verified-asset_fields');
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>