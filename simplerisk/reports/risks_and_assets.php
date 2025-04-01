<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    // If the select_report form was posted
    if (isset($_REQUEST['report'])) {
        $report = (int)$_REQUEST['report'];
    } else {
        $report = 0;
    }

    $sort_by = isset($_REQUEST['sort_by']) ? (int)$_REQUEST['sort_by'] : 0;
    $asset_tags = isset($_REQUEST['asset_tags']) ? $_REQUEST['asset_tags'] : [];

    if (!is_array($asset_tags)) {
        $asset_tags = json_decode($asset_tags, true);
    }

    if (!isset($_REQUEST['report'])) {
        $asset_tags = "all";
    }

    $projects = isset($_REQUEST['projects']) ? $_REQUEST['projects'] : [];

    if (!import_export_extra() || !(isset($_GET['option']) && $_GET['option'] == "download")) {

        // Render the header and sidebar
        require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
        render_header_and_sidebar(['multiselect','CUSTOM:dynamic.js'], required_localization_keys: ['All'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'RisksAndAssets');

    } else {

        global $escaper, $lang;

        // Include Laminas Escaper for HTML Output Encoding
        $escaper = new simpleriskEscaper();

        // Add various security headers
        add_security_headers();

        add_session_check();

        // Include the SimpleRisk language file
        require_once(language_file());

        if (import_export_extra()) {
            // Include the Import-Export Extra
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

            // if download request, download all risks
            if (isset($_GET['option']) && $_GET['option'] == "download") {
                download_risks_and_assets_report($report, $sort_by, $asset_tags, $projects);
            }
        }
    }

?>    
<div class="row bg-white">
    <div class="col-12 my-2">
        <div id="selections">
    <?php 
            view_risks_and_assets_selections($report, $sort_by, $asset_tags, $projects); 
    ?>
        </div>
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
                display_download_link("risks-and-assets-report");
            }
        }
    ?>
        </div>
    </div>
    <div class="col-12 my-2">
        <div id="risks_and_assets_table_container" class="card-body border">
    <?php 
            risks_and_assets_table($report, $sort_by, $asset_tags, $projects); 
    ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>