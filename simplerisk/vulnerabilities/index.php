<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['tabs:logic'], ['check_vm_vulnerabilities' => true]);

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
					<nav class='nav nav-tabs'>
						<a data-bs-target='#triage-tab-pane' data-bs-toggle='tab' class='nav-link active' data-status='0'><?= $escaper->escapeHtml($lang['TriageVulnerabilities']) ?></a>
						<a data-bs-target='#risks-tab-pane' data-bs-toggle='tab' class='nav-link' data-status='0'><?= $escaper->escapeHtml($lang['ViewRisks']) ?></a>
					</nav>
				</div>
	<?php } ?>
	
				<div class="tab-content my-2">

	<!-- If the Vulnerability Management Extra is enabled -->
	<?php if (vulnmgmt_extra()) { ?>
					<div id='triage-tab-pane' class='tab-pane active custom-treegrid-container card-body my-2 border'>
						<!-- Display the Triage Vulnerabilities -->
						<?php show_all_triage(); ?>
					</div>
					<div id='risks-tab-pane' class='tab-pane custom-treegrid-container card-body my-2 border'>
						<!-- Display the View Risks page -->
						<?php show_all_risks(); ?>
					</div>
	<?php } ?>

				</div>
			</div>
		</div>
	</div>
</div>
<?php  
// If vulnerability management is enabled
if (vulnmgmt_extra())
{
	echo "   <script src='../extras/vulnmgmt/js/vulnmgmt.js?" . current_version("app") . "'></script>";
}
?>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>