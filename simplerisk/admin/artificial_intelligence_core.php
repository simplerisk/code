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
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <nav class="nav nav-tabs">
                <a data-bs-target="#provider-tab-pane" data-bs-toggle="tab" class="nav-link active"><?= $escaper->escapeHtml($lang['ProviderConfiguration']) ?></a>
                <a data-bs-target="#context-tab-pane" data-bs-toggle="tab" class="nav-link"><?= $escaper->escapeHtml($lang['ContextQuestions']) ?></a>
            </nav>
            <div class="tab-content my-2">
                <div id="provider-tab-pane" class="tab-pane active" role="tabpanel">
                    <?php display_ai_provider_configuration(); ?>
                </div>
                <div id="context-tab-pane" class="tab-pane" role="tabpanel">
                    <?php process_and_display_ai_context_questions(); ?>
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
