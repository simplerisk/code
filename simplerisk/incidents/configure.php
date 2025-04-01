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

        // If the pages in the third level was displayed, assigned the value for its parent page, configure page to $active_sidebar_submenu
        $active_sidebar_submenu = "IM_Configure";

        // If the page for the menu was displayed
        switch ($_GET['menu']) {

            // If the setting page was displayed
            case "settings":
                $breadcrumb_title_key = 'Settings';
                break;

            // If the add and remove values page was displayed
            case "add_remove_values":
                $breadcrumb_title_key = 'AddAndRemoveValues';
                break;

            // If the playbook page was displayed
            case "playbooks":
                $breadcrumb_title_key = 'Playbooks';
                break;

            // IF the setting page was displayed by default
            default:
                $breadcrumb_title_key = 'Settings';
                break;

        }
        
    // If no menu was provided
    } else {

        $breadcrumb_title_key = "Configure";

    }

    render_header_and_sidebar(['tabs:logic', 'multiselect', 'CUSTOM:common.js', 'JSLocalization'], ['check_im_configure' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu, required_localization_keys: ['TheNameOfAPlaybookActionCannotBeEmpty']);

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
<div class="row bg-white">
    <div class="col-12">
        <div id="appetite-tab-content">
            <div class="status-tabs">
                <div class="tab-content">
    <!-- Display the Configuration -->
    <?php
        // If a menu was provided
        if (isset($_GET['menu'])) {

            // Display the page for the menu
            switch ($_GET['menu']) {

                // Display the settings page
                case "settings":
                    display_incident_management_configure_settings();
                    break;

                // Display the add and remove values page
                case "add_remove_values":
                    display_incident_management_configure_add_remove_values();
                    break;

                // Display the playbooks page
                case "playbooks":
                    display_incident_management_configure_playbooks();
                    break;

                // Display the settings page by default
                default:
                    display_incident_management_configure_settings();
                    break;
                    
            }
            
        // If no menu was provided
        } else {

            // Display the settings by default
            display_incident_management_configure_settings();

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