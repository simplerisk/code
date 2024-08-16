<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar([], ['check_admin' => true], 'VulnerabilityManagementExtra', 'Configure', 'Extras');

checkUploadedFileSizeErrors();

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/vulnmgmt')))
{
    // Include the Vulnerability Management Extra
    require_once(realpath(__DIR__ . '/../extras/vulnmgmt/index.php'));

    // If the user wants to activate the extra
    if (isset($_POST['activate']))
    {
        // Enable the Vulnerability Management Extra
        enable_vulnmgmt_extra();
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate']))
    {
        // Disable the Vulnerability Management Extra
        disable_vulnmgmt_extra();
    }
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display($display = "")
{
    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/vulnmgmt')))
    {
        // But the extra is not activated
        if (!vulnmgmt_extra())
        {
            echo "<div class='card-body my-2 border'>";
            // If the extra is not restricted based on the install type
            if (!restricted_extra("vulnmgmt"))
            {
                echo "
                <div class='hero-unit'>
                    <form name='activate' method='post' action=''>
                        <input type='submit' value='" . $escaper->escapeHtml($lang['Activate']) . "' name='activate' class='btn btn-submit'/>
                    </form>
                </div>";
            }
            // The extra is restricted
            else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            echo "</div>";
        }
        // Once it has been activated
        else
        {
            // Include the Vulnerability Management Extra
            require_once(realpath(__DIR__ . '/../extras/vulnmgmt/index.php'));

            display_vulnmgmt();
        }
    }
    // Otherwise, the Extra does not exist
    else
    {
        echo "
            <div class='card-body my-2 border'>
                <a href='https://www.simplerisk.com/extras' target='_blank' class='text-info'>Purchase the Extra</a> 
            </div>";
    }
}
?>
<div class="row bg-white "> 
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