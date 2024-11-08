<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    $breadcrumb_title_key="";
    $active_sidebar_menu ="";
    $active_sidebar_submenu ="";

    // If a menu was provided
    if (isset($_GET['menu'])) {

        $active_sidebar_menu = "IncidentManagement";

        // If the pages in the third level was displayed, assigned the value for its parent page, reporting page to $active_sidebar_submenu
        $active_sidebar_submenu = "Reporting";

        // If the page for the menu was displayed
        switch ($_GET['menu']) {
            // If the overview page was displayed
            case "overview":
                $breadcrumb_title_key = 'Overview';
                break;
            // If the incident trend page was displayed
            case "incident_trend":
                $breadcrumb_title_key = 'IncidentTrend';
                break;
            // If the dynamic incident report page was displayed
            case "dynamic_incident_report":
                $breadcrumb_title_key = 'DynamicIncidentReport';
                break;
            // If the lessons learned page was displayed
            case "lessons_learned":
                $breadcrumb_title_key = 'LessonsLearned';
                break;
            // IF the overview page was displayed by default
            default:
                $breadcrumb_title_key = 'Overview';
                break;
        }
        
    // If no menu was provided
    } else {
        $breadcrumb_title_key = "Reporting";
    }
    render_header_and_sidebar(['datatables', 'tabs:logic', 'multiselect', 'blockUI', 'datetimerangepicker', 'chart.js'], ['check_im_reporting' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));

    // If the Incident Management Extra is enabled
    if (incident_management_extra()) {       

        // Load the Incident Management Extra
        require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));

        process_incident_management();

    } else {

        // Redirect them to the activation page
        header("Location: ../admin/incidentmanagement.php");

    }

?>
<?php
    // If the Incident Management Extra is enabled
    if (incident_management_extra()) {
        // Include the incident management javascript file
        echo "<script src='../extras/incident_management/js/incident_management.js?" . current_version("app") . "' defer></script>";
        // Include the incident management css file
        echo "<link rel='stylesheet' href='../extras/incident_management/css/incident_management.css?" . current_version("app") . "'>";
    }
?>
<script>
	$(function() {
        $(".datepicker").initAsDateRangePicker();
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
        if (isset($_GET['menu'])) {

            // Display the page for the menu
            switch ($_GET['menu']) {

                // Display the overview page
                case "overview":
                    display_incident_management_overview();
                    break;

                // Display the incident trend page
                case "incident_trend":
                    display_incident_management_incident_trend();
                    break;
                
                // Display the dynamic incident report page
                case "dynamic_incident_report":
                    display_incident_management_dynamic_incident_report();
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
            
        // If no menu was provided
        } else {

                    // Display the overview page by default
                    display_incident_management_overview();

        }
    ?>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>