<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['easyui', 'easyui:treegrid', 'CUSTOM:selectlist.js'], ['check_assets' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/assets.php'));

?>
<div class="row bg-white">
    <div class="col-12 my-2">
        <div>
            <nav class="nav nav-tabs">
                <a class="btn btn-primary" id="asset-group-create-btn"><i class="fa fa-plus"></i></a>
                <a class="nav-link active" id="asset-groups-tab" data-bs-target="#tb-asset-groups" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['AssetGroups']); ?> (<span id="asset-groups-count">0</span>)</a>
            </nav>
        </div>
        <div class="tab-content">
            <div class="tab-pane active card-body border mt-2" id="tb-asset-groups" role="tabpanel" aria-labelledby="asset-groups-tab">
                <div class="row">
                    <div id="asset-groups" class="col-12 custom-treegrid-container manage-asset-groups-table-container">
                         <?php get_asset_groups_table(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR ADDING ASSET GROUP -->
<div id="asset-group--create" class="modal fade" tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="asset-group-new-form" action="#" method="POST" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $escaper->escapeHtml($lang['NewAssetGroup']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['AssetGroupName']); ?><span class="required">*</span> :</label>
                        <input type="text" required name="name" value="" class="form-control" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <div class="select-list-wrapper" >
                            <div class="select-list-available">
                                <label for=""><?= $escaper->escapeHtml($lang['AvailableAssets']); ?> :</label>
                                <select multiple="multiple" class="form-control">
                                </select>
                            </div>
                            <div class="select-list-arrows text-center">
                                <input type='button' value='&gt;&gt;' class="btn btn-secondary btnAllRight" /><br />
                                <input type='button' value='&gt;' class="btn btn-secondary btnRight" /><br />
                                <input type='button' value='&lt;' class="btn btn-secondary btnLeft" /><br />
                                <input type='button' value='&lt;&lt;' class="btn btn-secondary btnAllLeft" />
                            </div>
                            <div class="select-list-selected">
                                <label for=""><?= $escaper->escapeHtml($lang['SelectedAssets']); ?> :</label>
                                <select name="selected-asset-groups" multiple="multiple" class="form-control">
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"> <?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR EDITING AN ASSET GROUP -->
<div id="asset-group--update" class="modal fade" tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="asset-group-update-form" class="" action="#" method="post" autocomplete="off">
                <input type="hidden" class="asset_group_id" name="asset_group_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $escaper->escapeHtml($lang['AssetGroupUpdate']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['AssetGroupName']); ?><span class="required">*</span> :</label>
                        <input type="text" required name="name" value="" class="form-control" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <div class="select-list-wrapper" >
                            <div class="select-list-available">
                                <label for=""><?= $escaper->escapeHtml($lang['AvailableAssets']); ?> :</label>
                                <select multiple="multiple" class="form-control">
                                </select>
                            </div>
                            <div class="select-list-arrows text-center">
                                <input type='button' value='&gt;&gt;' class="btn btn-secondary btnAllRight" /><br />
                                <input type='button' value='&gt;' class="btn btn-secondary btnRight" /><br />
                                <input type='button' value='&lt;' class="btn btn-secondary btnLeft" /><br />
                                <input type='button' value='&lt;&lt;' class="btn btn-secondary btnAllLeft" />
                            </div>
                            <div class="select-list-selected">
                                <label for=""><?= $escaper->escapeHtml($lang['SelectedAssets']); ?> :</label>
                                <select name="selected-asset-groups" multiple="multiple" class="form-control">
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Update']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR ASSET GROUP DELETE CONFIRM -->
<div id="asset-group--delete" class="modal fade" tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form class="" id="asset-group-delete-form" action="" method="post">
                <div class="modal-body">
                    <div class="form-group text-center">
                        <label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisAssetGroup']); ?></label>
                        <input type="hidden" name="asset_group_id" value="" />
                    </div>
                    <div class="form-group text-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR ASSET REMOVAL CONFIRM -->
<div id="asset--remove" class="modal fade" tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form class="" id="asset-remove-form" action="" method="post">
                <div class="modal-body">
                    <div class="form-group text-center">
                        <label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToRemoveThisAsset']); ?></label>
                        <input type="hidden" name="asset_group_id" value="" />
                        <input type="hidden" name="asset_id" value="" />
                    </div>
                    <div class="form-group text-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    .asset-group--update, .asset-group--delete, .asset--remove {
        cursor: pointer;
    }

    td[field='actions'], td[field='actions']>div,
    .actions-cell, .actions-cell a {
        vertical-align: bottom;
    }

    .actions-cell a {
        padding-right: 5px;
    }

    #asset-group--create .modal-header, #asset-group--update .modal-header {
        color: #ffffff;
    }

    #asset-groups--view .modal-body h4 {
        text-decoration: underline;
    }

    .no-padding {
        padding: 0px;
    }

    .datagrid-btable, .datagrid-header-inner, .datagrid-htable {
        width : 100%;
    }
</style>
<script>
    function sortOptions(select) {
        var options = $(select).find('option');
        var arr = options.map(function(_, o) {
            return {
                t: $(o).text(),
                v: o.value
            };
        }).get();
        arr.sort(function(o1, o2) {
            return o1.t > o2.t ? 1 : o1.t < o2.t ? -1 : 0;
        });
        options.each(function(i, o) {
            o.value = arr[i].v;
            $(o).text(arr[i].t);
        });
    }
    function addOptions(select, data) {
        for (let i = 0, len = data.length; i < len; ++i) {
            let o = data[i];
            select.append($("<option title='" + o.name + "' value='" + o.id + "'>" + o.name + "</option>"));
        }
    }

    $(document).ready(function(){

        // variable which is used to prevent multiple form submissions
        var loading = false;

        $("#asset-group-create-btn").click(function(event) {
            event.preventDefault();

            $('#asset-group-new-form .select-list-selected select option').remove();
            $('#asset-group-new-form .select-list-available select option').remove();

            $.ajax({
                url: BASE_URL + '/api/assets/options',
                type: 'GET',
                success : function (response){
                    addOptions($('#asset-group-new-form .select-list-available select'), response.data);
                    $("#asset-group--create").modal('show');
                }
            });
        });

        $("#asset-group-new-form").submit(function(event) {
            event.preventDefault();
            var data = new FormData($('#asset-group-new-form')[0]);

            //adding the ids of the selected assets
            $('#asset-group-new-form .select-list-selected select option').each(function() {
                data.append('selected_assets[]', $(this).val());
            });

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/asset-group/create",
                data: data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#asset-group--create').modal('hide');
                    $('#asset-group-new-form')[0].reset();

                    var tree = $('#asset-groups-table');
                    tree.treegrid('options').animate = false;
                    tree.treegrid('reload');
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
            return false;
        });

        $("#asset-group-update-form").submit(function(event) {
            event.preventDefault();
            var data = new FormData($('#asset-group-update-form')[0]);

            //adding the ids of the selected assets
            $('#asset-group-update-form .select-list-selected select option').each(function() {
                data.append('selected_assets[]', $(this).val());
            });

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/asset-group/update",
                data: data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#asset-group--update').modal('hide');
                    $('#asset-group-update-form')[0].reset();

                    var tree = $('#asset-groups-table');
                    tree.treegrid('options').animate = false;
                    tree.treegrid('reload');
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });

            return false;
        });

        $("#asset-group-delete-form").submit(function(event) {
            event.preventDefault();

            // prevent multiple form submissions
            if (loading) {
                return;
            }

            var data = new FormData($('#asset-group-delete-form')[0]);
            
            // set the loading to true to prevent form submission
            loading = true;

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/asset-group/delete",
                data: data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#asset-group--delete').modal('hide');

                    // set loading to false to allow form submission
                    loading = false;

                    $('#asset-group-delete-form')[0].reset();

                    var tree = $('#asset-groups-table');
                    tree.treegrid('options').animate = false;
                    tree.treegrid('reload');
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }

                    // set loading to false to allow form submission
                    loading = false;

                }
            });

            return false;
        });

        $("#asset-remove-form").submit(function(event) {
            event.preventDefault();

            // prevent multiple form submissions
            if (loading) {
                return;
            }

            var data = new FormData($('#asset-remove-form')[0]);
            var asset_group_id = $("#asset-remove-form [name='asset_group_id']").val();
            var asset_id = $("#asset-remove-form [name='asset_id']").val();

            // set the loading to true to prevent form submission
            loading = true;

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/asset-group/remove_asset",
                data: data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#asset--remove').modal('hide');

                    // set loading to false to allow form submission
                    loading = false;

                    $('#asset-remove-form')[0].reset();

                    $("tr[node-id='" + asset_id + "-" + asset_group_id + "']").remove();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }

                    // set loading to false to allow form submission
                    loading = false;

                }
            });

            return false;
        });

        $(document).on('click', '.asset-group--update', function() {
            var asset_group_id = $(this).data("id");

            $('#asset-group-update-form .select-list-selected select option').remove();
            $('#asset-group-update-form .select-list-available select option').remove();

            $.ajax({
                url: BASE_URL + '/api/asset-group/info?id=' + asset_group_id,
                type: 'GET',
                success : function (response) {
                    var data = response.data;

                    $("#asset-group-update-form [name='asset_group_id']").val(asset_group_id);
                    $("#asset-group-update-form [name='name']").val(data.name);

                    addOptions($('#asset-group-update-form .select-list-selected select'), data.selected_assets);
                    addOptions($('#asset-group-update-form .select-list-available select'), data.available_assets);

                    $("#asset-group--update").modal('show');
                }
            });
        });

        $(document).on('click', '.asset-group--delete', function() {
            $("#asset-group-delete-form [name='asset_group_id']").val($(this).data("id"));
            $("#asset-group--delete").modal('show');
        });

        $(document).on('click', '.asset--remove', function() {
            $("#asset-remove-form [name='asset_group_id']").val($(this).data('asset-group-id'));
            $("#asset-remove-form [name='asset_id']").val($(this).data('asset-id'));
            $("#asset--remove").modal('show');
        });

        // $(".asset-groups-table").treegrid('resize');

        //Have to remove the 'fade' class for the shown event to work for modals
        $('#asset-group--create, #asset-group--update').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
        });

    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>