<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar([], ['check_admin' => true], 'IncidentManagementExtra', 'Configure', 'Extras');

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/incident_management'))) {
        // Include the Incident Management Extra
        require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate'])) {
            // Enable the Incident Management Extra
            enable_incident_management_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate'])) {
            // Disable the Incident Management Extra
            disable_incident_management_extra();
        }
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    function display() {
        global $lang;
        global $escaper;

        // If the extra directory exists
        if (is_dir(realpath(__DIR__ . '/../extras/incident_management'))) {
            // But the extra is not activated
            if (!incident_management_extra()) {
                echo "<div class='card-body my-2 border'>";
                // If the extra is not restricted based on the install type
                if (!restricted_extra("incident_management")) {
                    echo "<form name=\"activate_extra\" method=\"post\" action=\"\">\n";
                    echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" class=\"btn btn-submit\"/><br />\n";
                    echo "</form>\n";
                } // The extra is restricted
                else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
                echo "</div>";

            } else { // Once it has been activated

                // Include the Incident Management Extra
                require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));

                echo "
                <div class='card-body my-2 border'>
                    <form name=\"deactivate\" method=\"post\">
                        <font color=\"green\">
                            <b>" . $escaper->escapeHtml($lang['Activated']) . "</b>
                        </font>
                        [" . incident_management_version() . "]&nbsp;&nbsp;
                        <input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" class=\"btn btn-dark\"/>
                    </form>
                </div>";
            }
        } else { // Otherwise, the Extra does not exist
            echo "
                <div class='card-body my-2 border'>
                    <a href='https://www.simplerisk.com/extras' target='_blank' class='text-info'>Purchase the Extra</a>
                </div>";
        }
    }

?>
<div class="row bg-white"> 
    <div class="col-12">
        <?php display(); ?>
    </div>
    <script>
        <?php prevent_form_double_submit_script(); ?>
    </script>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>