<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

?>

<!doctype html>
<html>

<head>
<meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<title>SimpleRisk: Enterprise Risk Management Simplified</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);
?>
<script src="../js/jquery.easyui.min.js?<?php echo current_version("app"); ?>"></script>
<?php
        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];      

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
<script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
<script src="../js/dataTables.rowReorder.min.js?<?php echo current_version("app"); ?>"></script>
<script src="../js/dataTables.rowGroup.min.js?<?php echo current_version("app"); ?>"></script>

<link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/rowReorder.dataTables.min.css?<?php echo current_version("app"); ?>">

<link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">

<link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/settings_tabs.css?<?php echo current_version("app"); ?>">

<?php
    setup_favicon("..");
    setup_alert_requirements("..");
?>
<script>

var $risk_table;
var $threat_table;

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
                $risk_table.ajax.reload(null, false);
            } else {
                $threat_table.ajax.reload(null, false);
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
    $risk_table = $('#risk_catalog').DataTable({
        ajax: BASE_URL + '/api/admin/risk_catalog/datatable',
        bFilter: false,
        bLengthChange: false,
        processing: true,
        serverSide: true,
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
	               return '<span class="grippy"></span>' + data;
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
                data: 'actions'
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
                    group = "<?php echo $escaper->escapeHtml('[' . $lang['NoGroup'] . ']'); ?>";
                    return $('<tr/>')
                        .append( '<td colspan="5">' + group + ' (' + rows.count() + ')</td>' );
                } else {

                    // Get the first row of the group to get some additional info for rendering
                    var row = rows.row(function ( idx, data, node ) {
                        return data.group_name === group;
                    }).data();
                    
                    return $("<tr data-group_id= '" + row.group_id + "'/>")
                        .append('<td colspan="4">' + group + ' (' + rows.count() + ')</td>' )
                        .append("<td><button type='button' class='btn btn-default btn-small move-group move-group-up'><i class='fas fa-arrow-up'></i></button><button type='button' class='btn btn-default btn-small move-group move-group-down'><i class='fas fa-arrow-down'></i></button></td>");
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
    $risk_table.on( 'row-reorder', function ( e, diff, edit ) {
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

                $risk_table.ajax.reload(null, false);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });

    // Remove unnecessary buttons
    $risk_table.on('draw', function () {
    	$('#riskcatalog tr.dtrg-group.dtrg-start:first button.move-group-up').remove();
    	$('#riskcatalog tr.dtrg-group.dtrg-start:last button.move-group-down').remove();
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
    
    $threat_table = $('#threat_catalog').DataTable({
        ajax: BASE_URL + '/api/admin/threat_catalog/datatable',
        bFilter: false,
        bLengthChange: false,
        processing: true,
        serverSide: true,
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
                   return '<span class="grippy"></span>' + data;
                }
            },
            {
                data: 'name'
            },
            {
                data: 'description'
            },
            {
                data: 'actions'
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
                    group = "<?php echo $escaper->escapeHtml('[' . $lang['NoGroup'] . ']'); ?>";
                    return $('<tr/>')
                        .append( '<td colspan="4">' + group + ' (' + rows.count() + ')</td>' );
                } else {

                    // Get the first row of the group to get some additional info for rendering
                    var row = rows.row(function ( idx, data, node ) {
                        return data.group_name === group;
                    }).data();
                    
                    return $("<tr data-group_id= '" + row.group_id + "'/>")
                        .append('<td colspan="3">' + group + ' (' + rows.count() + ')</td>' )
                        .append("<td><button type='button' class='btn btn-default btn-small move-group move-group-up'><i class='fas fa-arrow-up'></i></button><button type='button' class='btn btn-default btn-small move-group move-group-down'><i class='fas fa-arrow-down'></i></button></td>");
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
    $threat_table.on( 'row-reorder', function ( e, diff, edit ) {
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

                $threat_table.ajax.reload(null, false);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });

    // Remove unnecessary buttons
    $threat_table.on('draw', function () {
        $('#threatcatalog tr.dtrg-group.dtrg-start:first button.move-group-up').remove();
        $('#threatcatalog tr.dtrg-group.dtrg-start:last button.move-group-down').remove();
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
        $(modal).modal('show');
    });

    // Add risk catalog form event
    $("#risk_catalog_add_form").submit(function(){
        var form = $(this);
        var form_data = new FormData(form[0]);
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
                $risk_table.ajax.reload(null, false);
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

    // Add threat catalog form event
    $("#threat_catalog_add_form").submit(function(){
        var form = $(this);
        var form_data = new FormData(form[0]);
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
                $threat_table.ajax.reload(null, false);
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

    // edit risk catalog form event
    $("#risk_catalog_edit_form").submit(function(){
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
                $risk_table.ajax.reload(null, false);
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
    $("#threat_catalog_edit_form").submit(function(){
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
                $threat_table.ajax.reload(null, false);
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
                $risk_table.ajax.reload(null, false);
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
                $threat_table.ajax.reload(null, false);
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

    var tabs =  $(".tabs li a");

    tabs.click(function() {
        var content = this.hash.replace('/','');
        tabs.removeClass("active");
        $(this).addClass("active");
        $("#content").find('.settings_tab').hide();
        $(content).fadeIn(200);
    });

});
</script>
</head>

<body>

<?php
display_license_check();

view_top_menu("Configure");

// Get any alert messages
get_alert();
?>
	<div class="container-fluid">
		<div class="row-fluid">
			<div class="span3">
				<?php view_configure_menu("RiskAndThreatCatalog"); ?>
			</div>
			<div class="span9">
				<div class="row-fluid">
					<div class="span12">
						<div class="hero-unit">
							<div class="row-fluid">
								<div class="span12">
									<div class="wrap risk_thread_catalog">
										<ul class="tabs group">
											<li><a class="active" href="#/riskcatalog"><?php echo $escaper->escapeHtml($lang['RiskCatalog']); ?></a></li>
											<li><a href="#/threatcatalog"><?php echo $escaper->escapeHtml($lang['ThreatCatalog']); ?></a></li>
										</ul>
										<div id="content">
											<div id="riskcatalog" class="settings_tab">
												<div class="span12 text-right">
													<a href="#risk-catalog--add" role="button" data-toggle="modal" class="btn add"><?php echo $escaper->escapeHtml($lang['Add']); ?></a>
												</div>
												<table class="table risk-datatable table-bordered table-striped table-condensed  " width="100%" id="risk_catalog" >
    												<thead>
        												<tr>
        													<th width="15%"><?php echo $escaper->escapeHtml($lang['RiskGrouping']);?></th>
        													<th width="10%"><?php echo $escaper->escapeHtml($lang['Risk']);?></th>
        													<th width="25%"><?php echo $escaper->escapeHtml($lang['RiskEvent']);?></th>
        													<th width="30%"><?php echo $escaper->escapeHtml($lang['Description']);?></th>
        													<th width="10%"><?php echo $escaper->escapeHtml($lang['Function']);?></th>
        													<th width="10%"><?php echo $escaper->escapeHtml($lang['Actions']);?></th>
        												</tr>
    												</thead>
    												<tbody>
    												</tbody>
												</table>
											</div>
											<div id="threatcatalog" class="settings_tab" style="display: none;">
                                                <div class="span12 text-right">
                                                    <a href="#threat-catalog--add" role="button" data-toggle="modal" class="btn add"><?php echo $escaper->escapeHtml($lang['Add']); ?></a>
                                                </div>
                                                <table class="table risk-datatable table-bordered table-striped table-condensed  " width="100%" id="threat_catalog" >
                                                    <thead >
                                                        <tr>
                                                            <th width="15%"><?php echo $escaper->escapeHtml($lang['ThreatGrouping']);?></th>
                                                            <th width="10%"><?php echo $escaper->escapeHtml($lang['Threat']);?></th>
                                                            <th width="25%"><?php echo $escaper->escapeHtml($lang['ThreatEvent']);?></th>
                                                            <th width="30%"><?php echo $escaper->escapeHtml($lang['Description']);?></th>
                                                            <th width="10%"><?php echo $escaper->escapeHtml($lang['Actions']);?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    </tbody>
                                                </table>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- MODEL WINDOW FOR ADDING RISK CATALOG -->
	<div id="risk-catalog--add" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="risk-catalog--add" aria-hidden="true">
		<form class="" id="risk_catalog_add_form" action="#" method="post" autocomplete="off">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['NewRisk']); ?></h4>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label for=""><?php echo $escaper->escapeHtml($lang['RiskGrouping']); ?></label>
					<?php create_dropdown("risk_grouping"); ?>
					<label for=""><?php echo $escaper->escapeHtml($lang['Risk']); ?></label>
					<input type="text" name="number" id="number" value="" class="form-control" autocomplete="off" maxlength="20" required>
					<label for=""><?php echo $escaper->escapeHtml($lang['RiskEvent']); ?></label>
					<input type="text" name="name" id="name" value="" class="form-control" autocomplete="off" maxlength="1000" required>
					<label for=""><?php echo $escaper->escapeHtml($lang['Description']); ?></label>
					<textarea name="description" id="description" value="" class="form-control" rows="6" style="width:100%;"></textarea>
					<label for=""><?php echo $escaper->escapeHtml($lang['Function']); ?></label>
					<?php create_dropdown("risk_function"); ?>
				</div>
			</div>
			<div class="modal-footer">
				<button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
				<button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
			</div>
		</form>
	</div>
        <!-- MODEL WINDOW FOR ADDING THREAT CATALOG -->
        <div id="threat-catalog--add" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="threat-catalog--add" aria-hidden="true">
                <form class="" id="threat_catalog_add_form" action="#" method="post" autocomplete="off">
                        <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['NewThreat']); ?></h4>
                        </div>
                        <div class="modal-body">
                                <div class="form-group">
                                        <label for=""><?php echo $escaper->escapeHtml($lang['ThreatGrouping']); ?></label>
                                        <?php create_dropdown("threat_grouping"); ?>
                                        <label for=""><?php echo $escaper->escapeHtml($lang['Threat']); ?></label>
                                        <input type="text" name="number" id="number" value="" class="form-control" autocomplete="off" maxlength="20" required>
                                        <label for=""><?php echo $escaper->escapeHtml($lang['ThreatEvent']); ?></label>
                                        <input type="text" name="name" id="name" value="" class="form-control" autocomplete="off" maxlength="1000" required>
                                        <label for=""><?php echo $escaper->escapeHtml($lang['Description']); ?></label>
                                        <textarea name="description" id="description" value="" class="form-control" rows="6" style="width:100%;"></textarea>
                                </div>
                        </div>
                        <div class="modal-footer">
                                <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                                <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
                        </div>
                </form>
        </div>
	<!-- MODEL WINDOW FOR EDIT RISK CATALOG -->
	<div id="risk-catalog--edit" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="risk-catalog--edit" aria-hidden="true">
		<form class="" id="risk_catalog_edit_form" action="#" method="post" autocomplete="off">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['EditRisk']); ?></h4>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label for=""><?php echo $escaper->escapeHtml($lang['RiskGrouping']); ?></label>
					<?php create_dropdown("risk_grouping") ?>
					<label for=""><?php echo $escaper->escapeHtml($lang['Risk']); ?></label>
					<input type="text" name="number" id="number" value="" class="form-control" autocomplete="off" maxlength="20" required>
					<label for=""><?php echo $escaper->escapeHtml($lang['RiskEvent']); ?></label>
					<input type="text" name="name" id="name" value="" class="form-control" autocomplete="off" maxlength="1000" required>
					<label for=""><?php echo $escaper->escapeHtml($lang['Description']); ?></label>
					<textarea name="description" id="description" value="" class="form-control" rows="6" style="width:100%;"></textarea>
					<label for=""><?php echo $escaper->escapeHtml($lang['Function']); ?></label>
					<?php create_dropdown("risk_function") ?>
					<input type="hidden" name="id" id="id" value="">
				</div>
			</div>
			<div class="modal-footer">
				<button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
				<button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
			</div>
		</form>
	</div>
        <!-- MODEL WINDOW FOR EDIT THREAT CATALOG -->
        <div id="threat-catalog--edit" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="threat-catalog--edit" aria-hidden="true">
                <form class="" id="threat_catalog_edit_form" action="#" method="post" autocomplete="off">
                        <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['EditThreat']); ?></h4>
                        </div>
                        <div class="modal-body">
                                <div class="form-group">
                                        <label for=""><?php echo $escaper->escapeHtml($lang['ThreatGrouping']); ?></label>
                                        <?php create_dropdown("threat_grouping") ?>
                                        <label for=""><?php echo $escaper->escapeHtml($lang['Threat']); ?></label>
                                        <input type="text" name="number" id="number" value="" class="form-control" autocomplete="off" maxlength="20" required>
                                        <label for=""><?php echo $escaper->escapeHtml($lang['ThreatEvent']); ?></label>
                                        <input type="text" name="name" id="name" value="" class="form-control" autocomplete="off" maxlength="1000" required>
                                        <label for=""><?php echo $escaper->escapeHtml($lang['Description']); ?></label>
                                        <textarea name="description" id="description" value="" class="form-control" rows="6" style="width:100%;"></textarea>
                                        <input type="hidden" name="id" id="id" value="">
                                </div>
                        </div>
                        <div class="modal-footer">
                                <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                                <button type="submit" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
                        </div>
                </form>
        </div>
    <!-- MODEL WINDOW FOR RISK CATALOG DELETE CONFIRM -->
    <div id="risk-catalog--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby=risk-catalog--delete" aria-hidden="true">
        <div class="modal-body">

            <form class="" id="risk_catalog_delete_form" action="" method="post">
                <div class="form-group text-center">
                    <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisRiskCatalog']); ?></label>
                    <input type="hidden" class="delete-id" name="id" value="" />
                </div>

                <div class="form-group text-center control-delete-actions">
                    <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" name="delete_control" class="delete_control btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                </div>
            </form>

        </div>
    </div>
    <!-- MODEL WINDOW FOR THREAT CATALOG DELETE CONFIRM -->
    <div id="threat-catalog--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="threat-catalog--delete" aria-hidden="true">
        <div class="modal-body">

            <form class="" id="threat_catalog_delete_form" action="" method="post">
                <div class="form-group text-center">
                    <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisThreatCatalogItem']); ?></label>
                    <input type="hidden" class="delete-id" name="id" value="" />
                </div>

                <div class="form-group text-center control-delete-actions">
                    <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" name="delete_control" class="delete_control btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                </div>
            </form>

        </div>
    </div>

</body>
</html>
