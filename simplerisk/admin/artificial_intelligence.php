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
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
// @phan-suppress-next-line PhanRedefineFunction -- each admin page defines its own display() entry point
function display($display = "")
{
    global $lang;
    global $escaper;

    // ── State detection ───────────────────────────────────────────────────
    $extra_installed = is_dir(realpath(__DIR__ . '/../extras/artificial_intelligence'));
    $extra_active    = $extra_installed && artificial_intelligence_extra();
    $restricted      = $extra_installed && !$extra_active && restricted_extra("artificial_intelligence");

    // ── Unified Extra header ──────────────────────────────────────────────
    // Replaces the stock page header on this page (suppressed via
    // body:has(.sr-ai-exthead) in _ai_catalog.scss) so the title/breadcrumb
    // aren't duplicated by the activation status. Renders in every state; the
    // shared tabbed hub below always shows (the catalog surfaces locked
    // upsell cards until the Extra is activated).
    if ($extra_active)
    {
        $status = "<span class='sr-ai-exthead-status'>" . $escaper->escapeHtml($lang['Activated']) . "</span>"
                . "<span class='sr-ai-exthead-version'>" . $escaper->escapeHtml($lang['Version'] ?? 'Version') . " " . $escaper->escapeHtml(artificial_intelligence_version()) . "</span>";
        $desc   = "";
        $action = "<form id='deactivate_extra' name='deactivate' method='post'><button type='submit' name='deactivate' class='sr-ai-btn'>" . $escaper->escapeHtml($lang['Deactivate']) . "</button></form>";
    }
    else
    {
        $status = "<span class='sr-ai-exthead-status'>" . $escaper->escapeHtml($lang['AIExtraNotActivated'] ?? 'Not activated') . "</span>";
        $desc   = "<p class='sr-ai-exthead-desc'>" . $escaper->escapeHtml($lang['AIExtraValueProp'] ?? 'Enhanced AI — FAIR risk quantification, document and control assistance, and the AI chat assistant.') . "</p>";
        if (!$extra_installed)
            $action = "<a href='https://www.simplerisk.com/extras' target='_blank' class='sr-ai-btn sr-ai-btn-primary'>" . $escaper->escapeHtml($lang['PurchaseTheExtra']) . "</a>";
        elseif ($restricted)
            $action = "<span class='sr-ai-exthead-note'>" . $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']) . "</span>";
        else
            $action = "<form id='activate_extra' name='activate' method='post'><button type='submit' name='activate' class='sr-ai-btn sr-ai-btn-primary'>" . $escaper->escapeHtml($lang['Activate']) . "</button></form>";
    }

    echo "
        <div class='sr-ai-exthead" . ($extra_active ? "" : " inactive") . "'>
            <nav class='sr-ai-exthead-crumbs'>
                <a href='../admin/index.php'>" . $escaper->escapeHtml($lang['Settings']) . "</a><span class='sep'>&rsaquo;</span><span class='cur'>" . $escaper->escapeHtml($lang['ArtificialIntelligenceExtra']) . "</span>
            </nav>
            <div class='sr-ai-exthead-main'>
                <span class='sr-ai-exthead-dot'></span>
                <div class='sr-ai-exthead-text'>
                    <div class='sr-ai-exthead-titlerow'>
                        <h1 class='sr-ai-exthead-title'>" . $escaper->escapeHtml($lang['ArtificialIntelligenceExtra']) . "</h1>
                        {$status}
                    </div>
                    {$desc}
                </div>
                <div class='sr-ai-exthead-action'>{$action}</div>
            </div>
        </div>";
?>
    <div class="sr-aihub">
        <nav class="nav nav-tabs">
            <a data-bs-target="#provider-tab-pane" data-bs-toggle="tab" class="nav-link active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg><?= $escaper->escapeHtml($lang['ProviderConfiguration']) ?></a>
            <a data-bs-target="#context-tab-pane" data-bs-toggle="tab" class="nav-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75"/><circle cx="12" cy="17.25" r="1.1" fill="currentColor" stroke="none"/></svg><?= $escaper->escapeHtml($lang['ContextQuestions']) ?></a>
            <a data-bs-target="#capabilities-tab-pane" data-bs-toggle="tab" class="nav-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><?= $escaper->escapeHtml($lang['AICapabilitiesCatalog']) ?></a>
        </nav>
        <div class="tab-content my-2">
            <div id="provider-tab-pane" class="tab-pane active" role="tabpanel">
                <?php display_ai_provider_configuration(); ?>
            </div>
            <div id="context-tab-pane" class="tab-pane" role="tabpanel">
                <?php process_and_display_ai_context_questions(); ?>
            </div>
            <div id="capabilities-tab-pane" class="tab-pane" role="tabpanel">
                <?php display_ai_capabilities_catalog(); ?>
            </div>
        </div>
    </div>
<?php
}
?>
<div class="row">
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
