<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['tabs:logic', 'selectize', 'blockUI'], ['check_admin' => true], 'Custom Authentication Extra', 'Configure', 'Extras');

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/authentication')))
{
    // Include the Authentication Extra
    require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));
    
    // If the user updated the configuration
    if (isset($_POST['update_settings']) || isset($_POST['update_ldap']) || isset($_POST['update_saml']))
    {
        // Update the authentication configuration
        update_authentication_config();

        set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));

        refresh();
    }

    // If the user wants to activate the extra
    if (isset($_POST['activate']))
    {
        // Enable the Authentication Extra
        enable_authentication_extra();
        refresh();
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate']))
    {
        // Disable the Authentication Extra
        disable_authentication_extra();
        refresh();
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
    if (is_dir(realpath(__DIR__ . '/../extras/authentication')))
    {
        // If the extra is not activated
        if (!custom_authentication_extra())
        {
            echo "<div class='card-body my-2 border'>";
            // If the extra is not restricted based on the install type
            if (!restricted_extra("customauth"))
            {
                echo "
                <form id='activate_extra' name='activate_extra' method='post' action=''>
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
            // Include the Authentication Extra
            require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));
            display_authentication();
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
        <?php prevent_form_double_submit_script(['activate_extra', 'deactivate_extra']);?>
    </script>
</div>
<?php
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>