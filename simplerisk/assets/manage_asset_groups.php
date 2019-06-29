<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/assets.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    require_once(language_file());

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
        set_unauthenticated_redirect();
        header("Location: ../index.php");
        exit(0);
    }

    // Check if the user has access to manage assets
    if (!isset($_SESSION["asset"]) || $_SESSION["asset"] != 1)
    {
        header("Location: ../index.php");
        exit(0);
    }

    // Include the CSRF-magic library
    // Make sure it's called after the session is properly setup
    include_csrf_magic();

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">
    <head>
        <script src="../js/jquery.min.js"></script>
        <script src="../js/jquery.easyui.min.js"></script>
        <script src="../js/jquery-ui.min.js"></script>
        <script src="../js/bootstrap.min.js"></script>

        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/bootstrap.css">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css">
        <link rel="stylesheet" href="../css/jquery.dataTables.css">
        <link rel="stylesheet" href="../css/bootstrap-multiselect.css">
        <link rel="stylesheet" href="../css/prioritize.css">
        <link rel="stylesheet" href="../css/divshot-util.css">
        <link rel="stylesheet" href="../css/divshot-canvas.css">
        <link rel="stylesheet" href="../css/display.css">
        <link rel="stylesheet" href="../css/style.css">
        <link rel="stylesheet" href="../css/easyui.css">

        <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="../css/theme.css">

        <?php
            setup_alert_requirements("..");
        ?>

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
                $("#asset-group-create-btn").click(function(event) {
                    event.preventDefault();

                    $('#asset-group-new-form .select-list-selected select option').remove();
                    $('#asset-group-new-form .select-list-available select option').remove();

                    $.ajax({
                        url: BASE_URL + '/api/assets/options',
                        type: 'GET',
                        success : function (response){
                            addOptions($('#asset-group-new-form .select-list-available select'), response.data);
                            $("#asset-group--create").modal();
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
                    var data = new FormData($('#asset-group-delete-form')[0]);

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
                        }
                    });

                    return false;
                });

                $("#asset-remove-form").submit(function(event) {
                    event.preventDefault();

                    var data = new FormData($('#asset-remove-form')[0]);
                    var asset_group_id = $("#asset-remove-form [name='asset_group_id']").val();
                    var asset_id = $("#asset-remove-form [name='asset_id']").val();

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

                            $("#asset-group--update").modal();
                        }
                    });
                });

                $(document).on('click', '.asset-group--delete', function() {
                    $("#asset-group-delete-form [name='asset_group_id']").val($(this).data("id"));
                    $("#asset-group--delete").modal();
                });

                $(document).on('click', '.asset--remove', function() {
                    $("#asset-remove-form [name='asset_group_id']").val($(this).data('asset-group-id'));
                    $("#asset-remove-form [name='asset_id']").val($(this).data('asset-id'));
                    $("#asset--remove").modal();
                });

                $(".asset-groups-table").treegrid('resize');

                //Have to remove the 'fade' class for the shown event to work for modals
                $('#asset-group--create, #asset-group--update').on('shown.bs.modal', function() {
                    $(this).find('.modal-body').scrollTop(0);
                });

                $('.btnRight').click(function (e) {
                    var selectedOpts = $(this).parent().parent().find('.select-list-selected select option:selected');
                    if (selectedOpts.length == 0) {
                        //alert("Nothing to move.");
                        e.preventDefault();
                    }

                    var target = $(this).parent().parent().find('.select-list-available select');
                    target.append($(selectedOpts).clone());
                    sortOptions(target);

                    $(selectedOpts).remove();
                    e.preventDefault();
                });

                $('.btnAllRight').click(function (e) {
                    var selectedOpts = $(this).parent().parent().find('.select-list-selected select option');
                    if (selectedOpts.length == 0) {
                        //alert("Nothing to move.");
                        e.preventDefault();
                    }

                    var target = $(this).parent().parent().find('.select-list-available select');
                    target.append($(selectedOpts).clone());
                    sortOptions(target);

                    $(selectedOpts).remove();
                    e.preventDefault();
                });

                $('.btnLeft').click(function (e) {
                    var selectedOpts = $(this).parent().parent().find('.select-list-available select option:selected');
                    if (selectedOpts.length == 0) {
                        //alert("Nothing to move.");
                        e.preventDefault();
                    }

                    var target = $(this).parent().parent().find('.select-list-selected select');
                    target.append($(selectedOpts).clone());
                    sortOptions(target);

                    $(selectedOpts).remove();
                    e.preventDefault();
                });

                $('.btnAllLeft').click(function (e) {
                    var selectedOpts = $(this).parent().parent().find('.select-list-available select option');
                    if (selectedOpts.length == 0) {
                        //alert("Nothing to move.");
                        e.preventDefault();
                    }

                    var target = $(this).parent().parent().find('.select-list-selected select');
                    target.append($(selectedOpts).clone());
                    sortOptions(target);

                    $(selectedOpts).remove();
                    e.preventDefault();
                });
            });
        </script>
    </head>

    <body>

          <?php
              view_top_menu("AssetManagement");

              // Get any alert messages
              get_alert();
          ?>

        <div class="container-fluid">
            <div class="row-fluid">
                <div class="span3">
                    <?php view_asset_management_menu("ManageAssetGroups"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <div class="span12">
                            <div class="status-tabs" >
                                <a href="#asset-groups--create" id="asset-group-create-btn" role="button" class="project--add"><i class="fa fa-plus"></i></a>
                                <ul class="clearfix tabs-nav">
                                    <li><a href="#asset-groups" class="status" data-status="asset-groups"><?php echo $escaper->escapeHtml($lang['AssetGroups']); ?> (<span id="asset-groups-count">0</span>)</a></li>
                                </ul>
                                <div id="asset-groups" class="custom-treegrid-container">
                                    <?php get_asset_groups_table(); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL WINDOW FOR ADDING ASSET GROUP -->
        <div id="asset-group--create" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="asset-groups--create" aria-hidden="true">
            <form id="asset-group-new-form" action="#" method="POST" autocomplete="off">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['AssetGroupCreate']); ?></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for=""><?php echo $escaper->escapeHtml($lang['AssetGroupName']); ?></label>
                        <input type="text" required name="name" value="" class="form-control" autocomplete="off">

                        <div class="select-list-wrapper" >
                            <div class="select-list-selected">
                                <label for=""><?php echo $escaper->escapeHtml($lang['SelectedAssets']); ?></label>
                                <select name="selected-asset-groups" multiple="multiple" class="form-control">
                                </select>
                            </div>

                            <div class="select-list-arrows text-center">
                                <input type='button' value='>>' class="btn btn-default btnAllRight" /><br />
                                <input type='button' value='>' class="btn btn-default btnRight" /><br />
                                <input type='button' value='<' class="btn btn-default btnLeft" /><br />
                                <input type='button' value='<<' class="btn btn-default btnAllLeft" />
                            </div>

                            <div class="select-list-available">
                                <label for=""><?php echo $escaper->escapeHtml($lang['AvailableAssets']); ?></label>
                                <select multiple="multiple" class="form-control">
                                </select>
                            </div>

                            <div class="clearfix"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
                </div>
            </form>
        </div>

        <!-- MODAL WINDOW FOR EDITING AN ASSET GROUP -->
        <div id="asset-group--update" class="modal hide no-padding" tabindex="-1" role="dialog" aria-hidden="true">
            <form id="asset-group-update-form" class="" action="#" method="post" autocomplete="off">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['AssetGroupUpdate']); ?></h4>
                </div>
                <input type="hidden" class="asset_group_id" name="asset_group_id" value="">

                <div class="modal-body">
                    <div class="form-group">
                        <label for=""><?php echo $escaper->escapeHtml($lang['AssetGroupName']); ?></label>
                        <input type="text" required name="name" value="" class="form-control" autocomplete="off">

                        <div class="select-list-wrapper" >
                            <div class="select-list-selected">
                                <label for=""><?php echo $escaper->escapeHtml($lang['SelectedAssets']); ?></label>
                                <select name="selected-asset-groups" multiple="multiple" class="form-control">
                                </select>
                            </div>

                            <div class="select-list-arrows text-center">
                                <input type='button' value='>>' class="btn btn-default btnAllRight" /><br />
                                <input type='button' value='>' class="btn btn-default btnRight" /><br />
                                <input type='button' value='<' class="btn btn-default btnLeft" /><br />
                                <input type='button' value='<<' class="btn btn-default btnAllLeft" />
                            </div>

                            <div class="select-list-available">
                                <label for=""><?php echo $escaper->escapeHtml($lang['AvailableAssets']); ?></label>
                                <select multiple="multiple" class="form-control">
                                </select>
                            </div>

                            <div class="clearfix"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
                </div>
            </form>
        </div>

        <!-- MODAL WINDOW FOR ASSET GROUP DELETE CONFIRM -->
        <div id="asset-group--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="asset-group-delete-form" aria-hidden="true">
            <div class="modal-body">

                <form class="" id="asset-group-delete-form" action="" method="post">
                    <div class="form-group text-center">
                        <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisAssetGroup']); ?></label>
                        <input type="hidden" name="asset_group_id" value="" />
                    </div>

                    <div class="form-group text-center">
                        <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL WINDOW FOR ASSET REMOVAL CONFIRM -->
        <div id="asset--remove" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="asset-remove-form" aria-hidden="true">
            <div class="modal-body">

                <form class="" id="asset-remove-form" action="" method="post">
                    <div class="form-group text-center">
                        <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToRemoveThisAsset']); ?></label>
                        <input type="hidden" name="asset_group_id" value="" />
                        <input type="hidden" name="asset_id" value="" />
                    </div>

                    <div class="form-group text-center">
                        <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </form>
            </div>
        </div>        
        
        
        <?php display_set_default_date_format_script(); ?>
    </body>

</html>
