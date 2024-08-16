<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['tabs:logic', 'multiselect', 'blockUI'], ['check_vm_configure' => true]);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/permissions.php'));

// If the Vulnerability Management Extra is enabled
if (vulnmgmt_extra())
{       
	// Load the Vulnerability Management Extra
	require_once(realpath(__DIR__ . '/../extras/vulnmgmt/index.php'));
}
else
{
	// Redirect them to the activation page
	header("Location: ../admin/vulnmgmt.php");
}

?>
<div class="row bg-white">
	<div class="col-12">
		<div id="appetite-tab-content" class="mt-2">
			<div class="status-tabs">
	<!-- If the Vulnerability Management Extra is enabled -->
	<?php if (vulnmgmt_extra()) { ?>
				<div>
					<nav class="nav nav-tabs">
						<a data-bs-target='#settings-tab-pane' data-bs-toggle='tab' class='nav-link active' data-status='0'><?= $escaper->escapeHtml($lang['Settings']) ?></a>
						<a data-bs-target='#schedule-tab-pane' data-bs-toggle='tab' class='nav-link' data-status='0'><?= $escaper->escapeHtml($lang['Schedule']) ?></a>
						<a data-bs-target='#log-tab-pane' data-bs-toggle='tab' class='nav-link' data-status='0'><?= $escaper->escapeHtml($lang['Log']) ?></a>
					</nav>
				</div>
	<?php } ?>
				<div class="tab-content my-2">
	<!-- If the Vulnerability Management Extra is enabled -->
	<?php if (vulnmgmt_extra()) { ?>
					<div id='settings-tab-pane' class='tab-pane active custom-treegrid-container card-body my-2 border'>
						<!-- Display the Settings page -->
						<?php display_vulnerability_management_configure_settings(); ?>
					</div>
					<div id='schedule-tab-pane' class='tab-pane custom-treegrid-container'>
						<!-- Display the Settings page -->
						<?php display_vulnerability_management_configure_schedule(); ?>
					</div>
					<div id='log-tab-pane' class='tab-pane custom-treegrid-container card-body my-2 border'>
						<!-- Display the Settings page -->
						<?php display_vulnerability_management_configure_log(); ?>
					</div>
	<?php } ?>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
// If vulnerability management is enabled
if (vulnmgmt_extra()){
	echo "<script src='../extras/vulnmgmt/js/vulnmgmt.js?" . current_version("app") . "'></script>";
}
?>
<script>
	function blockWithInfoMessage(message) {
		toastr.options = {
			"timeOut": "0",
			"extendedTimeOut": "0",
		}

		$('#vulnmgmt_wrapper').block({
			message: "<?php echo $escaper->escapeHtml($lang['Processing']); ?>",
			css: { border: '1px solid black' }
		});
		setTimeout(function(){ toastr.info(message); }, 1);
	}

	$(document).ready(function(){
		$("#platforms").multiselect({
			includeSelectAllOption: true,
			clearButton: true,
			filter: true,
			buttonWidth: '100%'
		});
		$("#sites").multiselect({
			includeSelectAllOption: true,
			clearButton: true,
			filter: true,
			buttonWidth: '100%'
		});
	}); 
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>