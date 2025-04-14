<?php
	/* This Source Code Form is subject to the terms of the Mozilla Public
	* License, v. 2.0. If a copy of the MPL was not distributed with this
	* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Render the header and sidebar
	require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
	render_header_and_sidebar(['tabs:logic'], ['check_admin' => true]);

	checkUploadedFileSizeErrors();

	// If the extra directory exists
	if (is_dir(realpath(__DIR__ . '/../extras/import-export'))) {

		// Include the Import-Export Extra
		require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

		// Allow this to run as long as necessary
		ini_set('max_execution_time', 0);

		// If the user wants to activate the extra
		if (isset($_POST['activate'])) {

			// Enable the Import Export Extra
			enable_import_export_extra();

		}

		// If the user wants to deactivate the extra
		if (isset($_POST['deactivate'])) {

			// Disable the Import Export Extra
			disable_import_export_extra();
			
		}

		// If the user selected to import a CSV
		if (isset($_POST['import_csv'])) {

			// Import the CSV file
			// $display = import_csv($_FILES['file']);

		}

		// If the user selected to do a combined export
		if (isset($_POST['combined_export'])) {

			// Export the XLSX file
			export_xls("combined");

		}

		// If the user selected to do a combined export
		if (isset($_POST['risks_export'])) {

			// Export the XLSX file
			export_xls("risks");

		}

		// If the user selected to do a combined export
		if (isset($_POST['mitigations_export'])) {

			// Export the XLSX file
			export_xls("mitigations");

		}

		// If the user selected to do a combined export
		if (isset($_POST['reviews_export'])) {

			// Export the XLSX file
			export_xls("reviews");

		}

		// If the user selected to do a combined export
		if (isset($_POST['assessments_export'])) {

			// Export the XLSX file
			export_xls("assessments");

		}

		// If the user selected to do an asset export
		if (isset($_POST['assets_export'])) {

			// Export the XLSX file
			export_xls("assets");

		}

		// If the user selected to do an asset group export
		if (isset($_POST['asset_groups_export'])) {

			// Export the XLSX file
			export_xls("asset_groups");

		}

		// If the user selected to do a control export
		if (isset($_POST['controls_export'])) {

			// Export the XLSX file
			export_xls("controls");

		}

		// If the user selected to do a user export
		if (isset($_POST['users_export'])) {

			// Export the XLSX file
			export_xls("users");

		}

		// If the user selected to do a template groups export
		if (isset($_POST['template_groups_export'])) {

			// Export the XLSX file
			export_xls("template_groups");

		}

		// If the user selected to do a control tests export
		if (isset($_POST['control_tests_export'])) {

			// Export the XLSX file
			export_xls("control_tests");

		}
	}

	/*********************
	 * FUNCTION: DISPLAY *
	 *********************/
	function display($display = "") {

		global $lang;
		global $escaper;

		// If the extra directory exists
		if (is_dir(realpath(__DIR__ . '/../extras/import-export'))) {

			// But the extra is not activated
			if (!import_export_extra()) {

				echo "
					<div class='row'>
						<div class='col-12'>
							<div class='card-body my-2 border'>
				";

				// If the extra is not restricted based on the install type
				if (!restricted_extra("importexport")) {

					echo "
								<form id='activate_extra' name='activate' method='post'  action=''>
									<div>
										<h4>{$escaper->escapeHtml($lang['ImportExportExtra'])}</h4>
										<input type='submit' value='{$escaper->escapeHtml($lang['Activate'])}' name='activate' class='btn btn-submit'/>
									</div>
								</from>
					";

				// The extra is restricted
				} else {
					echo 		$escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
				}

				echo "
							</div>
						</div>
					</div>
				";
				
			// Once it has been activated
			} else {
				
				// Include the Import-Export Extra
				require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

				display_import_export();

				display_import_export_selector();

			}
		
		// Otherwise, the Extra does not exist
		} else {

			echo "
					<div class='row'>
						<div class='col-12'>
							<div class='card-body my-2 border'>
								<a class='text-info' href='https://www.simplerisk.com/extras' target='_blank'>Purchase the Extra</a>
							</div>
						</div>
					</div>
			";

		}
	}

?>
<div class="row bg-white">
	<?php display(); ?>
</div>
<script>
	function blockWithInfoMessage(message) {
		toastr.options = {
			"timeOut": "0",
			"extendedTimeOut": "0",
		}

		$("#import_export_wrapper").block({
			message: "<?= $escaper->escapeHtml($lang['Processing']) ?>",
			css: { border: "1px solid black" }
		});
		setTimeout(function(){ toastr.info(message); }, 1);
	}
	$(document).ready(function() {
		$("#delete_mapping").click(function(e){
			e.preventDefault();
			var mapping_id = $("#import_export_mappings").val();
			
			if(!mapping_id){
				alert($("#lang_SelectMappingToRemove").val());
				return;
			}
			$.ajax({
				method: "POST",
				url: BASE_URL + "/api/management/impportexport/deleteMapping",
				data: {id: mapping_id},
				success: function(data){
					document.location.reload();
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
			
		});
		$("#import").submit(function(event) {
			if ($("#import input[type='file']").length && <?= $escaper->escapeHtml(get_setting('max_upload_size')) ?><= $("#import input[type='file']")[0].files[0].size) {
				showAlertFromMessage("<?= $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
				event.preventDefault();
			}
		});
		$("form[name='scf_mappings_install']").submit(function(evt) {
			blockWithInfoMessage("<?= $escaper->escapeHtml($lang['ActivatingSCFMappingMessage']) ?>");
			return true;
		});
		$("form[name='scf_mappings_uninstall']").submit(function(evt) {
			blockWithInfoMessage("<?= $escaper->escapeHtml($lang['DeactivatingSCFMappingMessage']) ?>");
			return true;
		});
	});
</script>
<script>
	<?php prevent_form_double_submit_script(['activate_extra', 'deactivate_extra']); ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>