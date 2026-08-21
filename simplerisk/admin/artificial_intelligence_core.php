<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar. Needs tabs:logic for the Provider /
// Context Questions tabs; multiselect for the provider form fields.
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
render_header_and_sidebar(
    ['multiselect', 'tabs:logic'],
    ['check_admin' => true],
    'ArtificialIntelligence', '', 'System'
);

// Include the language file
require_once(language_file());

?>
<div class="row">
    <div class="col-12">
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
    </div>
    <script>
        <?php prevent_form_double_submit_script(); ?>
    </script>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
