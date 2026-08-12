<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */



    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    // If the select_report form was posted
    $report = isset($_POST['report'])?(int)$_POST['report']:0;
    $sort_by = isset($_POST['sort_by'])?(int)$_POST['sort_by']:0;
    $projects = isset($_REQUEST['projects']) ? $_REQUEST['projects'] : [];
    $status = isset($_POST['status'])? (int)$_POST['status'] : 0;

    // Single source of truth for this report's required permissions, shared by
    // both the HTML render and the download branch so the two gates cannot drift
    // apart -- the drift between them (the download branch enforcing only
    // authentication) is exactly what allowed unauthorized report downloads.
    $report_permissions = ['check_riskmanagement' => true, 'check_governance' => true];

    if (import_export_extra()) {

        // Include the Import-Export Extra
        require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

        // if download request, download all risks
        if (isset($_GET['option']) && $_GET['option'] == "download") {

            global $escaper, $lang;
            // Include Laminas Escaper for HTML Output Encoding
            $escaper = new simpleriskEscaper();

            // Add various security headers
            add_security_headers();

            // The download branch must enforce the same permissions as the HTML
            // render below; a bare add_session_check() (authentication only) let
            // any logged-in user force-browse the download endpoint and pull the
            // report.
            add_session_check($report_permissions);

            // Include the SimpleRisk language file
            require_once(language_file());

            $control_framework = isset($_POST['control_framework']) ? $_POST['control_framework'] : [];
            $control_family = isset($_POST['control_family']) ? $_POST['control_family'] : [];
            $control_class = isset($_POST['control_class']) ? $_POST['control_class'] : [];
            $control_phase = isset($_POST['control_phase']) ? $_POST['control_phase'] : [];
            $control_priority = isset($_POST['control_priority']) ? $_POST['control_priority'] : [];
            $control_owner = isset($_POST['control_owner']) ? $_POST['control_owner'] : [];
            $filters = array(
            'control_framework' => $control_framework,
            'control_family' => $control_family,
            'control_class' => $control_class,
            'control_phase' => $control_phase,
            'control_priority' => $control_priority,
            'control_owner' => $control_owner,
            );
            download_risks_and_controls_report($report, $sort_by, $projects, $status, $filters);
        }
    }

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['multiselect','CUSTOM:dynamic.js'], $report_permissions, active_sidebar_submenu: 'Reporting_Reports', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'RisksAndControls');

?>
<div class="row bg-white">
    <div class="col-12 my-2">
        <form name="select_report" method="post" action="">
            <div class="accordion">
                <div class='accordion-item' id='filter-selections-container'>
                    <h2 class='accordion-header'>
                        <button type='button' class='accordion-button' data-bs-toggle='collapse' data-bs-target='#filter-selections-accordion-body'>
                            <?= $escaper->escapeHtml($lang['GroupAndFilteringSelections']) ?> 
                        </button>
                    </h2>
                    <div id='filter-selections-accordion-body' class='accordion-collapse collapse show'>
                        <div id="risks_and_controls_selections_container" class='accordion-body card-body'>
    <?php 
                            view_risks_and_controls_selections($report, $sort_by, $projects, $status);  
    ?>
    <?php 
        if ($report == 0) { 
                            view_controls_filter_selections();
        } 
    ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="col-12">
        <div class="card-body border">
    <?php
        // If the Import-Export Extra is installed
        if (is_dir(realpath(__DIR__ . '/../extras/import-export'))) {

            // And the Extra is activated
            if (import_export_extra()) {

	            // Include the Import-Export Extra
	            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
	            // Display the download link
            	display_download_link("risks-and-controls-report");

            }
        }
    ?>
        </div>
    </div>
    <div class="col-12 risks-and-controls-report">
        <div class="card-body border my-2">
    <?php 
            risks_and_control_table($report, $sort_by, $projects, $status); 
    ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>