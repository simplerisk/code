<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['multiselect'], ['check_admin' => true], 'JiraExtra', 'Configure', 'Extras');

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/jira'))) {
        // Include the Jira Extra
        require_once(realpath(__DIR__ . '/../extras/jira/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate'])) {
            // Enable the Jira Extra
            enable_jira_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate'])) {
            // Disable the Jira Extra
            disable_jira_extra();
        }
        
        if (isset($_POST['update_connection_settings'])) {
            jira_update_connection_settings();
        }

        if (isset($_POST['update_project_synchronization_settings'])) {
            jira_update_project_synchronization_settings();
        }

        if (isset($_POST['update_general_synchronization_settings'])) {
            jira_update_general_synchronization_settings();
        }
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    function display() {
        global $lang;
        global $escaper;

        // If the extra directory exists
        if (is_dir(realpath(__DIR__ . '/../extras/jira'))) {
            // But the extra is not activated
            if (!jira_extra()) {
                echo "<div class='card-body my-2 border'>";
                // If the extra is not restricted based on the install type
                if (!restricted_extra("jira")) {
                    echo "
                        <form name='activate_extra' method='post' action=''>
                            <input type='submit' value='{$escaper->escapeHtml($lang['Activate'])}' name='activate' class='btn btn-submit'/>
                            <br />
                        </form>
                    ";
                } else {// The extra is restricted
                    echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
                }
                echo "</div>";
            } else { // Once it has been activated
                // Include the Jira Extra
                require_once(realpath(__DIR__ . '/../extras/jira/index.php'));

                echo "
                    <div class='card-body my-2 border'>
                        <form name='deactivate' method='post'>
                            <font color='green'>
                                <b>{$escaper->escapeHtml($lang['Activated'])}</b>
                            </font>
                            [" . jira_version() . "]&nbsp;&nbsp;
                            <input type='submit' name='deactivate' value='{$escaper->escapeHtml($lang['Deactivate'])}' class='btn btn-dark'/>
                        </form>
                    </div>";

                display_jira_extra_options();
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
</div>
<script>
    <?php prevent_form_double_submit_script(); ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>