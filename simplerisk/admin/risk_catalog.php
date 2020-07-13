<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
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

    // Include the CSRF-magic library
    // Make sure it's called after the session is properly setup
    include_csrf_magic();

    // Check if access is authorized
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
    {
            header("Location: ../index.php");
            exit(0);
    }

?>

<!doctype html>
<html>

<head>
<meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<title>SimpleRisk: Enterprise Risk Management Simplified</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
<script src="../js/jquery.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
<script src="../js/jquery.dataTables.js"></script>
<script src="../js/dataTables.rowReorder.min.js"></script>
<link rel="stylesheet" href="../css/bootstrap.css">
<link rel="stylesheet" href="../css/bootstrap-responsive.css">
<link rel="stylesheet" href="../css/jquery.dataTables.css">
<link rel="stylesheet" href="../css/rowReorder.dataTables.min.css">

<link rel="stylesheet" href="../css/divshot-util.css">
<link rel="stylesheet" href="../css/divshot-canvas.css">
<link rel="stylesheet" href="../css/display.css">

<link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
<link rel="stylesheet" href="../css/theme.css">
<?php
    setup_favicon("..");
    setup_alert_requirements("..");
?>
<script>
var $table;
 $(document).ready(function(){
    var reorder = false;
    var pageLength = 1;
    $table = $('.risk-datatable').DataTable({
        bFilter: false,
        bLengthChange: false,
        processing: true,
        serverSide: true,
        bSort: true,
        paging: false,
        ordering: false,
        rowReorder: {
          update: false
        },
        ajax: {
            url: BASE_URL + '/api/admin/risk_catalog/datatable',
            data: function(d){
                d.reorder = reorder;
            },
            complete: function(response){
                reorder = false;
            }
        }
    });
    function redraw(){
        $(".risk-datatable").DataTable().draw();
    } 
    $table.on( 'row-reorder', function ( e, diff, edit ) {
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
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });
    $(document).on('click', '.edit_catalog', function(event) {
        event.preventDefault();
        var modal = $('#catalog--edit');
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
	$(document).on('click', '.delete_catalog', function(event) {
		event.preventDefault();
		var risk_id = $(this).attr('data-id');
		var modal = $('#catalog--delete');
		$('[name=id]', modal).val(risk_id);
		$(modal).modal('show');
	});

	// Add risk catalog form event
    $("#catalog_add_form").submit(function(){
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
                $('#catalog--add').modal('hide');
                $table.ajax.reload(null, false);
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
    $("#catalog_edit_form").submit(function(){
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
                $('#catalog--edit').modal('hide');
                $table.ajax.reload(null, false);
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
    $("#catalog_delete_form").submit(function(){
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
                $('#catalog--delete').modal('hide');
                $table.ajax.reload(null, false);
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
</head>

<body>

<?php
view_top_menu("Configure");

// Get any alert messages
get_alert();
?>
	<div class="container-fluid">
		<div class="row-fluid">
			<div class="span3">
				<?php view_configure_menu("RiskCatalog"); ?>
			</div>
			<div class="span9">
				<div class="row-fluid">
					<div class="span12">
						<div class="hero-unit">
							<div class="row-fluid">
								<div class="span10">
									<h4><?php echo $escaper->escapeHtml($lang['RiskCatalog']); ?></h4>
								</div>
								<div class="span2 text-right">
									<a href="#catalog--add" role="button" data-toggle="modal" class="btn"><?php echo $escaper->escapeHtml($lang['Add']); ?></a>
								</div>
							</div>
							<table class="table risk-datatable table-bordered table-striped table-condensed  " width="100%" id="{$tableID}" >
								<thead >
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
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- MODEL WINDOW FOR ADDING RISK CATALOG -->
	<div id="catalog--add" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="catalog--add" aria-hidden="true">
		<form class="" id="catalog_add_form" action="#" method="post" autocomplete="off">
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
	<!-- MODEL WINDOW FOR EDIT RISK CATALOG -->
	<div id="catalog--edit" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="catalog--edit" aria-hidden="true">
		<form class="" id="catalog_edit_form" action="#" method="post" autocomplete="off">
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
    <!-- MODEL WINDOW FOR RISK CATALOG DELETE CONFIRM -->
    <div id="catalog--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="control--delete" aria-hidden="true">
        <div class="modal-body">

            <form class="" id="catalog_delete_form" action="" method="post">
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

</body>
</html>
