<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['multiselect'], ['check_admin' => true], 'Unified Compliance Framework (UCF) Extra', 'Configure', 'Extras');

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/ucf'))) {
    // Include the UCF Extra
    require_once(realpath(__DIR__ . '/../extras/ucf/index.php'));

    // If the user wants to activate the extra
    if (isset($_POST['activate'])) {
        // Enable the UCF Extra
        enable_ucf_extra();
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate'])) {
        // Disable the UCF Extra
        disable_ucf_extra();
    }

// If the user wants to update the connection settings
if (isset($_POST['update_connection_settings']))
{
    update_ucf_connection_settings();
}

// If the user wants to enable ad lists
if (isset($_POST['ucf_ad_list_enable']))
{
    enable_ucf_ad_lists();
}

    // If the user wants to disable ad lists
    if (isset($_POST['ucf_ad_list_disable']))
    {
    disable_ucf_ad_lists();
    }

// If the user wants to enable authority documents
if (isset($_POST['ucf_authority_documents_enable']))
{
    enable_ucf_authority_documents();
}

// If the user wants to disable authority documents
if (isset($_POST['ucf_authority_documents_disable']))
{
    disable_ucf_authority_documents();
}

/*
// If the user wants to install UCF frameworks
if (isset($_POST['install_frameworks']))
{
    install_ucf_frameworks();
}

// If the user wants to uninstall UCF frameworks
if (isset($_POST['uninstall_frameworks']))
{
    uninstall_ucf_frameworks();
}
*/
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display()                                    
{
    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/ucf')))
    {
        // But the extra is not activated
        if (!ucf_extra())
        {
            echo "<div class='card-body my-2 border'>";
            // If the extra is not restricted based on the install type
            if (!restricted_extra("ucf"))
            {
                echo "
                <form name='activate_extra' method='post' action=''>
                    <input type='submit' value='" . $escaper->escapeHtml($lang['Activate']) . "' name='activate'  class='btn btn-submit'/>
                </form>";
            }
            // The extra is restricted
            else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            echo "</div>";
        }
        else
        { // Once it has been activated
                // Include the UCF Extra
                require_once(realpath(__DIR__ . '/../extras/ucf/index.php'));

                echo "
                    <div class='card-body my-2 border'>
                        <form name='deactivate' method='post'>
                            <font color='green'>
                                <b>" . $escaper->escapeHtml($lang['Activated']) . "</b>
                            </font>
                            [" . ucf_version() . "]&nbsp;&nbsp;
                            <input type='submit' name='deactivate' value='" . $escaper->escapeHtml($lang['Deactivate']) . "' class='btn btn-dark'/>
                        </form>
                    </div>";

                display_ucf_extra_options();
        }
    }
    else
    { // Otherwise, the Extra does not exist
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
    <script type="">
        <?php prevent_form_double_submit_script(); ?>
    </script>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>