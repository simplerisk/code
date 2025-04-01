<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
render_header_and_sidebar(['multiselect'], ['check_admin' => true]);

// Include the language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/artificial_intelligence')))
{
    // Include the Artificial Intelligence Extra
    require_once(realpath(__DIR__ . '/../extras/artificial_intelligence/index.php'));

    // If the user wants to activate the extra
    if (isset($_POST['activate']))
    {
        // Enable the Artificial Intelligence Extra
        enable_artificial_intelligence_extra();
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate']))
    {
        // Disable the Artificial Intelligence Extra
        disable_artificial_intelligence_extra();
    }

    // If the user updated the configuration
    if (isset($_POST['update_artificial_intelligence_settings']))
    {
        // Update the artificial intelligence settings
        update_artificial_intelligence_settings();
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
    if (is_dir(realpath(__DIR__ . '/../extras/artificial_intelligence')))
    {
        // But the extra is not activated
        if (!artificial_intelligence_extra())
        {
            echo "<div class='card-body my-2 border'>";
            // If the extra is not restricted based on the install type
            if (!restricted_extra("artificial_intelligence"))
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
            // Include the Notification Extra
            require_once(realpath(__DIR__ . '/../extras/artificial_intelligence/index.php'));

            display_artificial_intelligence();
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

        <div class='card-body my-2 border'>
            <form name='anthropic_settings' method='post' action=''>
                <div class='row'>
                    <div class='form-group'>
                        <label>Anthropic API Key:</label>
                        <div>To begin using an Anthropic API key:</div>
                        <div>
                            <ol>
                                <li>Create an account <a class="open-in-new-tab" href="https://console.anthropic.com/" target="_blank">here</a>.</li>
                                <li>Add credits to your account <a class="open-in-new-tab" href="https://console.anthropic.com/settings/billing" target="_blank">here</a>.  We recommend at least $40 so that you can take advantage of the Tier 2 limits.</li>
                                <li>Create an API key <a class="open-in-new-tab" href="https://console.anthropic.com/settings/keys" target="_blank">here.</a></li>
                                <li>Enter your API key in the input box below.</li>
                            </ol>
                        </div>
                        <?php display_anthropic_api_key_input() ?>
                    </div>
                </div>
            </form>
        </div>

<?php

    // And the AI extra is  activated
    if (artificial_intelligence_extra())
    {
            // Display the artificial intelligence settings
            display_artificial_intelligence_settings();
    }

    // If we have an Anthropic API key
    if (get_setting("anthropic_api_key") != false)
    {
        // Process the added/updated context
        $parameter_array = get_artificial_intelligence_context_parameter_array();
        $settings_prefix = "ai_context_";
        $parameter_array = update_posted_settings_values($parameter_array, $settings_prefix);

        // If this was a POST to update the AI context
        if (isset($_POST['save_ai_context']))
        {
            // Update a setting for the last time this prefix was updated
            $setting_name = $settings_prefix . "last_saved";
            update_setting($setting_name, time());
        }

        // Provide the user with the ability to add context
        display_artificial_intelligence_add_context($parameter_array);
    }
?>
    </div>
    <script>
        <?php prevent_form_double_submit_script(['activate_extra', 'deactivate_extra']); ?>
    </script>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>