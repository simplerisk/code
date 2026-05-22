<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
render_header_and_sidebar(
    ['multiselect', 'tabs:logic'],
    ['check_admin' => true],
    'ArtificialIntelligenceExtra', 'Configure', 'Extras'
);

// Include the language file
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
        artificial_intelligence_process_settings();
    }
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
// @phan-suppress-next-line PhanRedefineFunction -- each admin page defines its own display() entry point
function display($display = "")
{
    global $lang;
    global $escaper;

    // If the extra directory does not exist, point to the purchase page.
    if (!is_dir(realpath(__DIR__ . '/../extras/artificial_intelligence')))
    {
        echo "
            <div class='card-body my-2 border'>
                <a href='https://www.simplerisk.com/extras' target='_blank' class='text-info'>" . $escaper->escapeHtml($lang['PurchaseTheExtra']) . "</a>
            </div>";
        return;
    }

    // Inactive — render activation form (or restricted-extra message).
    // The Configure Hub's activation-modal flow is the primary path; this
    // form remains as the fallback for direct-URL visitors.
    if (!artificial_intelligence_extra())
    {
        echo "<div class='card-body my-2 border'>";
        if (!restricted_extra("artificial_intelligence"))
        {
            echo "
                <form id='activate_extra' name='activate' method='post' action=''>
                    <input type='submit' value='" . $escaper->escapeHtml($lang['Activate']) . "' name='activate' class='btn btn-submit'/>
                </form>";
        }
        else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
        echo "</div>";
        return;
    }

    // Active — render the activated-status header (with deactivate button)
    // above the three tabs: Provider Configuration, Context Questions, Settings.
    require_once(realpath(__DIR__ . '/../extras/artificial_intelligence/index.php'));
    display_artificial_intelligence();
?>
    <div class="card-body my-2 border">
        <nav class="nav nav-tabs">
            <a data-bs-target="#provider-tab-pane" data-bs-toggle="tab" class="nav-link active"><?= $escaper->escapeHtml($lang['ProviderConfiguration']) ?></a>
            <a data-bs-target="#context-tab-pane" data-bs-toggle="tab" class="nav-link"><?= $escaper->escapeHtml($lang['ContextQuestions']) ?></a>
            <a data-bs-target="#settings-tab-pane" data-bs-toggle="tab" class="nav-link"><?= $escaper->escapeHtml($lang['Settings']) ?></a>
        </nav>
        <div class="tab-content my-2">
            <div id="provider-tab-pane" class="tab-pane active" role="tabpanel">
                <?php display_ai_provider_configuration(); ?>
            </div>
            <div id="context-tab-pane" class="tab-pane" role="tabpanel">
                <?php process_and_display_ai_context_questions(); ?>
            </div>
            <div id="settings-tab-pane" class="tab-pane" role="tabpanel">
                <?php
                    // Surface the same provider-not-configured warning the Context tab uses.
                    if (!ai_provider_is_configured()) {
                        display_ai_provider_not_configured_warning();
                    }
                    display_artificial_intelligence_settings();
                ?>
            </div>
        </div>
    </div>
<?php
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
