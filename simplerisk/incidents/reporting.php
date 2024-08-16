<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['datatables', 'tabs:logic', 'multiselect', 'chart.js'], ['check_im_reporting' => true]);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/permissions.php'));

// If the Incident Management Extra is enabled
if (incident_management_extra())
{       
	// Load the Incident Management Extra
	require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));

	process_incident_management();
}else{
	// Redirect them to the activation page
	header("Location: ../admin/incidentmanagement.php");
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
	$(function () {
		$(".datepicker").datepicker({dateFormat: '<?= get_default_date_format_for_datepicker() ?>'});
	});
</script>
<div class="row bg-white">
	<div class="col-12">
		<div id="appetite-tab-content">
			<div class="status-tabs">
				<div class="tab-content">
                    <!-- Display the Reporting -->
                    <?php
                    // If a menu was provided
                    if (isset($_GET['menu']))
                    {
                        // Display the page for the menu
                        switch ($_GET['menu'])
                        {
                            // Display the overview page
                            case "overview":
                                display_incident_management_overview();
                                break;
                            // Display the incident trend page
                            case "incident_trend":
                                display_incident_management_incident_trend();
                                break;
                            // Display the lessons learned page
                            case "lessons_learned":
                                display_incident_management_reporting_lessons_learned();
                                break;
                            // Display the overview page by default
                            default:
                                display_incident_management_overview();
                                break;
                        }
                    }
                    // If no menu was provided
                    else
                    {
                        // Display the overview page by default
                        display_incident_management_overview();
                    }
                    ?>
				</div>
			</div>
		</div>
	</div>
</div>
<?php display_set_default_date_format_script();  ?>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>