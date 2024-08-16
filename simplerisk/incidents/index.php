<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

render_header_and_sidebar(['tabs:logic', 'datatables', 'multiselect', 'selectize', 'blockUI', 'datetimerangepicker'], ['check_im' => true]);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/permissions.php'));

// If the Incident Management Extra is enabled
if (incident_management_extra())
{       
	// Load the Incident Management Extra
	require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));

	process_incident_management();
}
else
{
	// Redirect them to the activation page
	header("Location: ../admin/incidentmanagement.php");
}

if(isset($_GET["action"]) && $_GET["action"] == "download"){
	if(isset($_GET["id"])) download_evidence_file($_GET["id"]);
}

?>
<?php
// If the Incident Management Extra is enabled
if (incident_management_extra()){
	// Include the incident management javascript file
	echo "<script src='../extras/incident_management/js/incident_management.js?" . current_version("app") . "' defer></script>";
	// Include the incident management css file
	echo "<link rel='stylesheet' href='../extras/incident_management/css/incident_management.css?" . current_version("app") . "'>";
}
?>
<script>
	var simplerisk = {
		incident: "<?php echo $lang['Incident']; ?>",
		newincident: "<?php echo $lang['NewIncident']; ?>"
	}
	
	var max_upload_size = "<?php echo $escaper->escapeJs(get_setting('max_upload_size', 0)); ?>";
	var fileTooBigMessage = "<?php echo $escaper->escapeJs($lang['FileIsTooBigToUpload']); ?>"; 
	var fileSizeLabel = "<?php echo $escaper->escapeJs($lang['FileSize']);?>"; 

	$(function () {
		// Load the datepicker
		$(".datepicker").datepicker({ dateFormat: '<?= get_default_date_format_for_datepicker() ?>' });

		// Load the datetimepicker
		$(".datetimepicker").initAsDateTimePicker();
	});
</script>
<div class="row bg-white ">
	<div class="col-12">
		<div id="appetite-tab-content">
			<div class="status-tabs">
				<div class="tab-content my-2">
						<!-- Display the Incidents -->
						<?php
                            // If a menu was provided
                            if (isset($_GET['menu']))
                            {
                                // Display the page for the menu
                                switch ($_GET['menu'])
                                {
                                    // Display the preparation page
                                    case "preparation":
                                        display_preparation();
                                        break;
                                    // Display the identification page
                                    case "identification":
                                        display_identification();
                                        break;
                                    // Display the response page
                                    case "response":
                                        display_response();
                                        break;
                                    // Display the lessons learned page
                                    case "lessonslearned":
                                        display_lessons_learned();
                                        break;
                                    // Display the closed page
                                    case "closed":
                                        display_closed();
                                        break;
                                    // Display the preparation page by default
                                    default:
                                        display_preparation();
                                        break;
                                }
                            }
                            // If no menu was provided
                            else
                            {
                                // Display the preparation page by default
                                display_preparation();
                            }
                        ?>
				</div>
			</div>
		</div>
	</div>
</div>
<?php display_set_default_date_format_script(); ?>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>