<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['tabs:logic', 'easyui', 'datatables' ,'datatables:rowgroup', 'datatables:rowreorder', 'CUSTOM:common.js'], ['check_admin' => true]);

?>
<div class="row risk_thread_catalog">
    <div class="col-12">
        <div class="mt-2">
            <nav class="nav nav-tabs">
                <a class="nav-link active" id="riskcatalog-tab" data-bs-toggle="tab" data-bs-target="#riskcatalog" type="button" role="tab" aria-controls="riskcatalog" aria-selected="true"><?= $escaper->escapeHtml($lang['RiskCatalog']); ?></a>
                <a class="nav-link" id="threatcatalog-tab" data-bs-toggle="tab" data-bs-target="#threatcatalog" type="button" role="tab" aria-controls="threatcataloga" aria-selected="false"><?= $escaper->escapeHtml($lang['ThreatCatalog']); ?></a>
            </nav>
        </div>
        <div class="tab-content cust-tab-content" id="content" >
            <div class="tab-pane active settings_tab card-body my-2 border" id="riskcatalog" role="tabpanel" aria-labelledby="general-tab">
                <div class="text-end mb-1">
                    <button type="button" class="btn btn-dark add_risk_catalog"><?= $escaper->escapeHtml($lang['Add']); ?></button>
                </div>
                <table class="table table-bordered table-striped table-condensed" width="100%" id="risk_catalog" >
                    <thead>
                        <tr>
                            <th width="15%"><?= $escaper->escapeHtml($lang['RiskGrouping']);?></th>
                            <th width="10%"><?= $escaper->escapeHtml($lang['Risk']);?></th>
                            <th width="25%"><?= $escaper->escapeHtml($lang['RiskEvent']);?></th>
                            <th><?= $escaper->escapeHtml($lang['Description']);?></th>
                            <th width="10%"><?= $escaper->escapeHtml($lang['Function']);?></th>
                            <th width="10%" class="text-center"><?= $escaper->escapeHtml($lang['Actions']);?></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div class="tab-pane card-body my-2 border" id="threatcatalog" role="tabpanel" aria-labelledby="threatcatalog-tab">
                <div class="text-end mb-1">
                    <button type="button" class="btn btn-dark add_threat_catalog"><?= $escaper->escapeHtml($lang['Add']); ?></button>
                </div>
                <table class="table table-bordered table-striped table-condensed" width="100%" id="threat_catalog" >
                    <thead >
                        <tr>
                            <th width="15%"><?= $escaper->escapeHtml($lang['ThreatGrouping']);?></th>
                            <th width="10%"><?= $escaper->escapeHtml($lang['Threat']);?></th>
                            <th width="25%"><?= $escaper->escapeHtml($lang['ThreatEvent']);?></th>
                            <th><?= $escaper->escapeHtml($lang['Description']);?></th>
                            <th width="10%" class="text-center"><?= $escaper->escapeHtml($lang['Actions']);?></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR ADDING RISK CATALOG -->
<div id="risk-catalog--add" class="modal fade no-padding" tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog-scrollable modal-dialog-centered modal-dialog modal-md">
        <div class="modal-content">
            <form class="" id="risk_catalog_add_form" action="#" method="post" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $escaper->escapeHtml($lang['NewRisk']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['RiskGrouping']); ?> :</label>
    <?php 
                        create_dropdown("risk_grouping"); 
    ?>
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Risk']); ?><span class="required">*</span> :</label>
                        <input type="text" name="number" id="number" value="" class="form-control" autocomplete="off" maxlength="20" required title="<?= $escaper->escapeHtml($lang['Risk']); ?>">
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['RiskEvent']); ?><span class="required">*</span> :</label>
                        <input type="text" name="name" id="name" value="" class="form-control" autocomplete="off" maxlength="1000" required title="<?= $escaper->escapeHtml($lang['RiskEvent']); ?>">
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Description']); ?> :</label>
                        <textarea name="description" id="description" value="" class="form-control" rows="6"></textarea>
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Function']); ?> :</label>
    <?php 
                        create_dropdown("risk_function"); 
    ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR ADDING THREAT CATALOG -->
<div id="threat-catalog--add" class="modal fade no-padding" tabindex="-1" aria-labelledby="threat-catalog--add" aria-hidden="true">
    <div class="modal-dialog-scrollable modal-dialog-centered modal-dialog modal-md">
        <div class="modal-content">
            <form class="" id="threat_catalog_add_form" action="#" method="post" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $escaper->escapeHtml($lang['NewThreat']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['ThreatGrouping']); ?> :</label>
    <?php 
                        create_dropdown("threat_grouping"); 
    ?>
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Threat']); ?><span class="required">*</span> :</label>
                        <input type="text" name="number" id="number" value="" class="form-control" autocomplete="off" maxlength="20" required title="<?= $escaper->escapeHtml($lang['Threat']); ?>">
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['ThreatEvent']); ?><span class="required">*</span> :</label>
                        <input type="text" name="name" id="name" value="" class="form-control" autocomplete="off" maxlength="1000" required title="<?= $escaper->escapeHtml($lang['ThreatEvent']); ?>">
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Description']); ?> :</label>
                        <textarea name="description" id="description" value="" class="form-control" rows="6"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR EDIT RISK CATALOG -->
<div id="risk-catalog--edit" class="modal fade no-padding" tabindex="-1" aria-labelledby="risk-catalog--edit" aria-hidden="true">
    <div class="modal-dialog-scrollable modal-dialog-centered modal-dialog modal-md">
        <div class="modal-content">
            <form class="" id="risk_catalog_edit_form" action="#" method="post" autocomplete="off">
                <input type="hidden" name="id" id="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $escaper->escapeHtml($lang['EditRisk']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['RiskGrouping']); ?> :</label>
    <?php 
                        create_dropdown("risk_grouping") 
    ?>
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Risk']); ?><span class="required">*</span> :</label>
                        <input type="text" name="number" id="number" value="" class="form-control" autocomplete="off" maxlength="20" required title="<?= $escaper->escapeHtml($lang['Risk']); ?>">
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['RiskEvent']); ?><span class="required">*</span> :</label>
                        <input type="text" name="name" id="name" value="" class="form-control" autocomplete="off" maxlength="1000" required title="<?= $escaper->escapeHtml($lang['RiskEvent']); ?>">
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Description']); ?> :</label>
                        <textarea name="description" id="description" value="" class="form-control" rows="6"></textarea>
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Function']); ?> :</label>
    <?php 
                        create_dropdown("risk_function") 
    ?>
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

<!-- MODEL WINDOW FOR EDIT THREAT CATALOG -->
<div id="threat-catalog--edit" class="modal fade no-padding" tabindex="-1"aria-labelledby="threat-catalog--edit" aria-hidden="true">
    <div class="modal-dialog-scrollable modal-dialog-centered modal-dialog modal-md">
        <div class="modal-content">        
            <form class="" id="threat_catalog_edit_form" action="#" method="post" autocomplete="off">
                <input type="hidden" name="id" id="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $escaper->escapeHtml($lang['EditThreat']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['ThreatGrouping']); ?> :</label>
    <?php 
                        create_dropdown("threat_grouping") 
    ?>
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Threat']); ?><span class="required">*</span> :</label>
                        <input type="text" name="number" id="number" value="" class="form-control" autocomplete="off" maxlength="20" required title="<?= $escaper->escapeHtml($lang['Threat']); ?>">
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['ThreatEvent']); ?><span class="required">*</span> :</label>
                        <input type="text" name="name" id="name" value="" class="form-control" autocomplete="off" maxlength="1000" required title="<?= $escaper->escapeHtml($lang['ThreatEvent']); ?>">
                    </div>
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Description']); ?> :</label>
                        <textarea name="description" id="description" value="" class="form-control" rows="6"></textarea>
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

<!-- MODEL WINDOW FOR RISK CATALOG DELETE CONFIRM -->
<div id="risk-catalog--delete" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="risk-catalog--delete" aria-hidden="true">
    <div class="modal-dialog-scrollable modal-dialog-centered modal-dialog modal-md">
        <div class="modal-content"> 
            <form class="" id="risk_catalog_delete_form" action="" method="post">
                <input type="hidden" class="delete-id" name="id" value="" />
                <div class="modal-body">
                    <div class="form-group text-center">
                        <label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisRiskCatalog']); ?></label>
                    </div>
                    <div class="form-group text-center control-delete-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="delete_control" class="delete_control btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR THREAT CATALOG DELETE CONFIRM -->
<div id="threat-catalog--delete" class="modal fade" tabindex="-1"  aria-labelledby="threat-catalog--delete" aria-hidden="true">
    <div class="modal-dialog-scrollable modal-dialog-centered modal-dialog modal-md">
        <div class="modal-content"> 
            <form class="" id="threat_catalog_delete_form" action="" method="post">
                <input type="hidden" class="delete-id" name="id" value="" />
                <div class="modal-body">
                    <div class="form-group text-center">
                        <label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisThreatCatalogItem']); ?></label>
                    </div>
                    <div class="form-group text-center control-delete-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="delete_control" class="delete_control btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    #risk_catalog_wrapper .paginate, #threat_catalog_wrapper .paginate {
        display: none;
    }
</style> 
<script>

    var risk_table;
    var threat_table;

    function swap_groups_async(type, group1_id, group2_id) {
        $.ajax({
            url: BASE_URL + '/api/admin/risk_catalog/swap_groups',
            type: 'POST',
            data: {
                type: type,
                group1_id: group1_id,
                group2_id: group2_id
            },
            success : function (result){
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }
                if (type == 'risk') {
                    risk_table.ajax.reload(null, false);
                } else {
                    threat_table.ajax.reload(null, false);
                }
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }

    $(document).ready(function(){

        /******RISK CATALOG DATATABLE CONFIGURATION******/

        // GET THE RISK DATATABLE
        risk_table = $('#risk_catalog').DataTable({
            ajax: BASE_URL + '/api/admin/risk_catalog/datatable',
            bSort: true,
            paging: false,
            ordering: false,
            columns: [
                {
                    data: 'group_name',
                    visible: false
                },
                {
                    data: 'number',
                    render: function(data, type, row, meta) {
                    return '<div class="d-flex align-items-center"><span class="grippy"></span>' + data + '</div>';
                    }
                },
                {
                    data: 'name'
                },
                {
                    data: 'description'
                },
                {
                    data: 'function_name'
                },
                {
                    data: 'actions',
                    render: function(data, type, row, meta) {
                        return '<div class="text-center">' + data + '</div>';
                    }
                },
            ],
            createdRow: function (row, data, dataIndex) {
                $(row).addClass('data-row');
            },
            rowGroup: {
                dataSrc: 'group_name',
                startRender: function ( rows, group ) {
                    if (!group || group.length == 0) {
                        // If there's no group for the row then place it in the 'groupless' section
                        group = "<?= $escaper->escapeHtml('[' . $lang['NoGroup'] . ']') ?>";
                        return $('<tr/>')
                            .append( '<td colspan="5">' + group + ' (' + rows.count() + ')</td>' );
                    } else {

                        // Get the first row of the group to get some additional info for rendering
                        var row = rows.row(function ( idx, data, node ) {
                            return data.group_name === group;
                        }).data();
                        
                        return $("<tr data-group_id= '" + row.group_id + "'/>")
                            .append('<td colspan="4">' + group + ' (' + rows.count() + ')</td>' )
                            .append(`
                                <td>
                                    <div class='text-center'>
                                        <button type='button' class='btn btn-secondary btn-small move-group move-group-up'><i class='fas fa-arrow-up'></i></button>
                                        <button type='button' class='btn btn-secondary btn-small move-group move-group-down'><i class='fas fa-arrow-down'></i></button>
                                    </div>
                                </td>
                            `);
                    }
                }
            },
            rowReorder: {
                snapX: true,
                update: false,
                selector: 'tr td span.grippy'
            },
        });

        // REORDER THE RISK TABLE
        risk_table.on( 'row-reorder', function ( e, diff, edit ) {
            var orders = []; 
            for ( var i=0, ien=diff.length ; i<ien ; i++ ) {
                var newPosition = diff[i].newPosition;
                var id = diff[i].node.id;
                orders.push([id,newPosition]);
            }
            $.ajax({
                url: BASE_URL + '/api/admin/risk_catalog/update_order',
                type: 'POST',
                data: {orders: orders},
                success : function (result){
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }

                    risk_table.ajax.reload(null, false);
                },
                error: function(xhr,status,error){
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
        });

        // Remove unnecessary buttons
        risk_table.on('draw', function () {
            $('#riskcatalog tr.dtrg-group.dtrg-start:first button.move-group-up').remove();
            $('#riskcatalog tr.dtrg-group.dtrg-start:last button.move-group-down').remove();
            $('#riskcatalog div.paginate div.btn.shows').remove();
        });

        $(document).on('click', '#riskcatalog .move-group-up', function(event) {
            var this_group = $(this).closest('tr');
            var previous_group = this_group.prevAll("#riskcatalog tr.dtrg-group.dtrg-start:first");

            if (previous_group.length) {
                swap_groups_async('risk', this_group.data('group_id'), previous_group.data('group_id'))
            }
        });

        $(document).on('click', '#riskcatalog .move-group-down', function(event) {
            var this_group = $(this).closest('tr');
            var next_group = this_group.nextAll("#riskcatalog tr.dtrg-group.dtrg-start:first");

            if (next_group.length) {
                swap_groups_async('risk', this_group.data('group_id'), next_group.data('group_id'))
            }
        });

        /******THREAT CATALOG DATATABLE CONFIGURATION******/
        
        threat_table = $('#threat_catalog').DataTable({
            ajax: BASE_URL + '/api/admin/threat_catalog/datatable',
            bSort: true,
            paging: false,
            ordering: false,
            columns: [
                {
                    data: 'group_name',
                    visible: false
                },
                {
                    data: 'number',
                    render: function(data, type, row, meta) {
                    return '<div class="d-flex align-items-center"><span class="grippy"></span>' + data + '</div>';
                    }
                },
                {
                    data: 'name'
                },
                {
                    data: 'description'
                },
                {
                    data: 'actions',
                    render: function(data, type, row, meta) {
                        return '<div class="text-center">' + data + '</div>';
                    }
                },
            ],
            createdRow: function (row, data, dataIndex) {
                $(row).addClass('data-row');
            },
            rowGroup: {
                dataSrc: 'group_name',
                startRender: function ( rows, group ) {
                    if (!group || group.length == 0) {
                        // If there's no group for the row then place it in the 'groupless' section
                        group = "<?= $escaper->escapeHtml('[' . $lang['NoGroup'] . ']') ?>";
                        return $('<tr/>')
                            .append( '<td colspan="4">' + group + ' (' + rows.count() + ')</td>' );
                    } else {

                        // Get the first row of the group to get some additional info for rendering
                        var row = rows.row(function ( idx, data, node ) {
                            return data.group_name === group;
                        }).data();
                        
                        return $("<tr data-group_id= '" + row.group_id + "'/>")
                            .append('<td colspan="3">' + group + ' (' + rows.count() + ')</td>' )
                            .append(`
                                <td>
                                    <div class='text-center'>
                                        <button type='button' class='btn btn-secondary btn-small move-group move-group-up'><i class='fas fa-arrow-up'></i></button>
                                        <button type='button' class='btn btn-secondary btn-small move-group move-group-down'><i class='fas fa-arrow-down'></i></button>
                                    </div>
                                </td>
                            `);
                    }
                }
            },
            rowReorder: {
                snapX: true,
                update: false,
                selector: 'tr td span.grippy'
            },
        });

        // REORDER THE THREAT TABLE
        threat_table.on( 'row-reorder', function ( e, diff, edit ) {
            var orders = []; 
            for ( var i=0, ien=diff.length ; i<ien ; i++ ) {
                var newPosition = diff[i].newPosition;
                var id = diff[i].node.id;
                orders.push([id,newPosition]);
            }
            $.ajax({
                url: BASE_URL + '/api/admin/threat_catalog/update_order',
                type: 'POST',
                data: {orders: orders},
                success : function (result){
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }

                    threat_table.ajax.reload(null, false);
                },
                error: function(xhr,status,error){
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
        });

        // Remove unnecessary buttons
        threat_table.on('draw', function () {
            $('#threatcatalog tr.dtrg-group.dtrg-start:first button.move-group-up').remove();
            $('#threatcatalog tr.dtrg-group.dtrg-start:last button.move-group-down').remove();
            $('#threatcatalog div.paginate div.btn.shows').remove();
        });

        $(document).on('click', '#threatcatalog .move-group-up', function(event) {
            var this_group = $(this).closest('tr');
            var previous_group = this_group.prevAll("#threatcatalog tr.dtrg-group.dtrg-start:first");

            if (previous_group.length) {
                swap_groups_async('threat', this_group.data('group_id'), previous_group.data('group_id'))
            }
        });

        $(document).on('click', '#threatcatalog .move-group-down', function(event) {
            var this_group = $(this).closest('tr');
            var next_group = this_group.nextAll("#threatcatalog tr.dtrg-group.dtrg-start:first");

            if (next_group.length) {
                swap_groups_async('threat', this_group.data('group_id'), next_group.data('group_id'))
            }
        });

        // EDIT RISK CATALOG
        $(document).on('click', '.edit_risk_catalog', function(event) {
            event.preventDefault();
            var modal = $('#risk-catalog--edit');
            var risk_id  = $(this).attr('data-id');
            $.ajax({
                url: BASE_URL + '/api/admin/risk_catalog/detail?risk_id=' + risk_id,
                type: 'GET',
                dataType: 'json',
                success : function (res){
                    var data = res.data;
                    var risk = data.risk;
                    
                    $('[name=id]', modal).val(risk_id);
                    $('[name=risk_grouping]', modal).val(risk.grouping);
                    $('[name=number]', modal).val(risk.number);
                    $('[name=name]', modal).val(risk.name);
                    $('[name=description]', modal).val(risk.description);
                    $('[name=risk_function]', modal).val(risk.function);
                    $(modal).modal('show');
                }
            });
        });

        // EDIT THREAT CATALOG
        $(document).on('click', '.edit_threat_catalog', function(event) {
            event.preventDefault();
            var modal = $('#threat-catalog--edit');
            var threat_id  = $(this).attr('data-id');
            $.ajax({
                url: BASE_URL + '/api/admin/threat_catalog/detail?threat_id=' + threat_id,
                type: 'GET',
                dataType: 'json',
                success : function (res){
                    var data = res.data;
                    var threat = data.threat;
                    
                    $('[name=id]', modal).val(threat_id);
                    $('[name=threat_grouping]', modal).val(threat.grouping);
                    $('[name=number]', modal).val(threat.number);
                    $('[name=name]', modal).val(threat.name);
                    $('[name=description]', modal).val(threat.description);
                    $(modal).modal('show');
                }
            });
        });

        // DELETE RISK CATALOG
        $(document).on('click', '.delete_risk_catalog', function(event) {
            event.preventDefault();
            var risk_id = $(this).attr('data-id');
            var modal = $('#risk-catalog--delete');
            $('[name=id]', modal).val(risk_id);
            $(modal).modal('show');
        });

        // DELETE THREAT CATALOG
        $(document).on('click', '.delete_threat_catalog', function(event) {
            event.preventDefault();
            var threat_id = $(this).attr('data-id');
            var modal = $('#threat-catalog--delete');
            $('[name=id]', modal).val(threat_id);
            $('#threat-catalog--delete').modal('show');
        });

        // Open add risk catalog modal
        $(document).on('click', '.add_risk_catalog', function() {

            // Reset the add risk catalog form
            resetForm('#risk_catalog_add_form', false);

            // Show the add risk catalog modal
            $('#risk-catalog--add').modal('show');

        });

        // the variable which is used for preventing the form from double submitting
        var loading = false;

        // Add risk catalog form event
        $("#risk_catalog_add_form").submit(function(){
        
            // prevent the form from submitting
            event.preventDefault();

            // if not received ajax response, don't submit again
            if (loading) {
                return
            }

            // Check empty/trimmed empty valiation for the required fields 
			if (!checkAndSetValidation(this)) {
				return;
			}

            var form = $(this);
            var form_data = new FormData(form[0]);

            // the ajax request is sent
            loading = true;

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/admin/risk_catalog/add_risk_catalog",
                data: form_data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(result){
                    var data = result.data;
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }
                    form[0].reset();
                    $("[name=risk_grouping]", form).prop('selectedIndex',0);
                    $("[name=risk_function]", form).prop('selectedIndex',0);
                    $('#risk-catalog--add').modal('hide');
                    risk_table.ajax.reload(null, false);

                    // the response is received
                    loading = false;

                }
            })
            .fail(function(xhr, textStatus){
                if(!retryCSRF(xhr, this))
                {
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }

                // the response is received
                loading = false;
                
            });
            return false;
        });

        // Open add threat catalog modal
        $(document).on('click', '.add_threat_catalog', function() {

            // Reset the add risk catalog form
            resetForm('#threat_catalog_add_form', false);

            // Show the add risk catalog modal
            $('#threat-catalog--add').modal('show');

        });

        // Add threat catalog form event
        $("#threat_catalog_add_form").submit(function(){

            // prevent the form from submitting
            event.preventDefault();

            // if not received ajax response, don't submit again
            if (loading) {
                return
            }
            
            // Check empty/trimmed empty valiation for the required fields 
            if (!checkAndSetValidation(this)) {
                return;
            }

            var form = $(this);
            var form_data = new FormData(form[0]);

            // the ajax request is sent
            loading = true;

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/admin/threat_catalog/add_threat_catalog",
                data: form_data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(result){
                    var data = result.data;
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }
                    form[0].reset();
                    $("[name=threat_grouping]", form).prop('selectedIndex',0);
                    $('#threat-catalog--add').modal('hide');
                    threat_table.ajax.reload(null, false);

                    // the response is received
                    loading = false;

                }
            })
            .fail(function(xhr, textStatus){
                if(!retryCSRF(xhr, this))
                {
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }

                // the response is received
                loading = false;

            });
            return false;
        });

        // edit risk catalog form event
        $("#risk_catalog_edit_form").submit(function(event){
            
            event.preventDefault();

            // Check empty/trimmed empty valiation for the required fields 
            if (!checkAndSetValidation(this)) {
                return;
            }
            
            var form = $(this);
            var form_data = new FormData(form[0]);
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/admin/risk_catalog/update_risk_catalog",
                data: form_data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(result){
                    var data = result.data;
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }
                    form[0].reset();
                    $('#risk-catalog--edit').modal('hide');
                    risk_table.ajax.reload(null, false);
                }
            })
            .fail(function(xhr, textStatus){
                if(!retryCSRF(xhr, this))
                {
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
            return false;
        });

        // edit threat catalog form event
        $("#threat_catalog_edit_form").submit(function(event){
            
            event.preventDefault();

			// Check empty/trimmed empty valiation for the required fields 
			if (!checkAndSetValidation(this)) {
				return;
			}

            var form = $(this);
            var form_data = new FormData(form[0]);
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/admin/threat_catalog/update_threat_catalog",
                data: form_data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(result){
                    var data = result.data;
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }
                    form[0].reset();
                    $('#threat-catalog--edit').modal('hide');
                    threat_table.ajax.reload(null, false);
                }
            })
            .fail(function(xhr, textStatus){
                if(!retryCSRF(xhr, this))
                {
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
            return false;
        });

        // delete risk catalog form event
        $("#risk_catalog_delete_form").submit(function(){
            var form = $(this);
            var form_data = new FormData(form[0]);
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/admin/risk_catalog/delete_risk_catalog",
                data: form_data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(result){
                    var data = result.data;
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }
                    form[0].reset();
                    $('#risk-catalog--delete').modal('hide');
                    risk_table.ajax.reload(null, false);
                }
            })
            .fail(function(xhr, textStatus){
                if(!retryCSRF(xhr, this))
                {
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
            return false;
        });

        // delete threat catalog form event
        $("#threat_catalog_delete_form").submit(function(){
            var form = $(this);
            var form_data = new FormData(form[0]);
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/admin/threat_catalog/delete_threat_catalog",
                data: form_data,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(result){
                    var data = result.data;
                    if(result.status_message){
                        showAlertsFromArray(result.status_message);
                    }
                    form[0].reset();
                    $('#threat-catalog--delete').modal('hide');
                    threat_table.ajax.reload(null, false);
                }
            })
            .fail(function(xhr, textStatus){
                if(!retryCSRF(xhr, this))
                {
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
            return false;
        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>