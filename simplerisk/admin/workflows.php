<?php
/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2026 to SimpleRisk, Inc and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['datatables', 'multiselect', 'datetimerangepicker', 'CUSTOM:common.js'], ['check_admin' => true], 'Workflows Extra', 'Configure', 'Extras');

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/workflows')))
{
    // Include the Workflows Extra
    require_once(realpath(__DIR__ . '/../extras/workflows/index.php'));

    // If the user wants to activate the extra
    if (isset($_POST['activate']))
    {
        enable_workflows_extra();
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate']))
    {
        disable_workflows_extra();
    }

    // If the Workflows Extra is enabled, process any incoming requests
    if (workflows_extra())
    {
        require_once(realpath(__DIR__ . '/../extras/workflows/includes/display.php'));
        process_workflows_extra_request();
    }
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display()
{
    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/workflows')))
    {
        // But the extra is not activated
        if (!workflows_extra())
        {
            echo "<div class='card-body my-2 border'>";
            // If the extra is not restricted based on the install type
            if (!restricted_extra("workflows"))
            {
                echo "
                    <form id='activate_extra' name='activate' method='post' action=''>
                        <input type='submit' value='" . $escaper->escapeHtml($lang['Activate']) . "' name='activate' class='btn btn-submit'/>
                    </form>";
            }
            // The extra is restricted
            else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            echo "</div>";
        }
        // Once it has been activated
        else
        {
            // Include the Workflows Extra
            require_once(realpath(__DIR__ . '/../extras/workflows/includes/display.php'));

            display_workflows_extra();
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
<div class="row bg-white">
    <div class="col-12">
        <?php display(); ?>
    </div>
    <script>
        <?php prevent_form_double_submit_script(['activate_extra', 'deactivate_extra']); ?>
    </script>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
