<?php
	/* This Source Code Form is subject to the terms of the Mozilla Public
	* License, v. 2.0. If a copy of the MPL was not distributed with this
	* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Render the header and sidebar
	require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
	render_header_and_sidebar(['datatables', 'CUSTOM:common.js'], ['check_admin' => true], required_localization_keys: ['GenericDeleteItemConfirmation']);

	// Check if risks were deleted
	if (isset($_POST['delete_risks']) && isset($_POST['risks'])) {

		$risks = $_POST['risks'];

		// Delete the risks
		$success = delete_risks($risks);

		// If the risk delete was successful
		if ($success) {

			// Display an alert
			set_alert(true, "good", $lang['RisksDeletedSuccessfully']);

		} else {

			// Display an alert
			set_alert(true, "bad", $lang['ThereWasAProblemDeletingTheRisk']);

		}
	}

?>
<div class="row bg-white">
	<div class="col-12">
		<div>
			<div class="card-body my-2 border d-flex align-items-center alert alert-danger" role="alert">
				 <?= $escaper->escapeHtml($lang['DeletedRisksCannotBeRecovered']); ?>
			</div>
			<form id="delete_risks" method="post" action="" class='card-body my-2 border'>
				<input type="hidden" name="delete_risks"/>
				<button data-sr-role='dt-settings' data-sr-target='zero_config' type="button" class="btn btn-submit btn-delete float-end"><?= $escaper->escapeHtml($lang['Delete']); ?></button>
	<?php 
				get_delete_risk_table(); 
	?>
			</form>
		</div>
	</div>
</div>
<script type='text/javascript'>
	function checkAll(bx) {
		var cbs = document.getElementsByTagName('input');
		for(var i=0; i < cbs.length; i++) {
			if (cbs[i].type == 'checkbox') {
			cbs[i].checked = bx.checked;
			}
		}
	}
	$(function() {
		$('#zero_config').DataTable({
			serverSide: false,
			order: [[1, 'asc']],
			columnDefs: [{'targets': 0, 'orderable': false}],
		});

		$('.btn-delete').on('click', function () {

			// if no checkboxes are checked, show an alert
			if (!$('[name="risks[]"]:checked').length) {

				showAlertFromMessage("<?= $escaper->escapeHtml($lang['PleaseSelectAtLeastOneRiskToDelete']) ?>", false);

			// if checkboxes are checked, show a confirmation dialog
			} else {

				confirm(_lang['GenericDeleteItemConfirmation'], () => $('#delete_risks').trigger('submit'));

			}

		});

	});
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>