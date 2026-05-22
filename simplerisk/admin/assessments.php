<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar. Assessments consolidates the former
// "Active Assessments" page as an inline tab, so we pull in tabs:logic,
// datatables (for the active questionnaires table), and CUSTOM:common.js
// (for the confirm/showAlertFromMessage helpers the Active tab JS uses).
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(
    ['WYSIWYG:Assessments', 'tabs:logic', 'datatables', 'CUSTOM:common.js'],
    ['check_admin' => true],
    'AssessmentsExtra', 'Configure', 'Extras',
    required_localization_keys: ['GenericDeleteItemConfirmation']
);

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/assessments')))
{
    // Include the Assessment Extra
    require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    // If the user wants to activate the extra
    if (isset($_POST['activate']))
    {
        // Enable the Assessments Extra
        enable_assessments_extra();
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate']))
    {
        // Disable the Assessments Extra
        disable_assessments_extra();
    }

    // If the user updated the configuration
    if (isset($_POST['submit']))
    {
        // Update the assessment configuration
        update_assessment_config();
        set_alert(true, "good", $escaper->escapeHtml($lang['AssessmentSettingsUpdatedSuccessfully']));
    }

    // Bulk-delete active questionnaires (formerly handled by
    // admin/active_assessments.php; now POSTs back here since the Active
    // Assessments tab is rendered as part of this page).
    if (isset($_POST['delete_active_assessments']) && assessments_extra())
    {
        // Get the selected assessments
        $tokens = $_POST['tokens'] ?? [];

        // Delete the assessments
        delete_active_questionnaires($tokens);

        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['TheAssessmentsWereDeletedSuccessfully']));

        refresh();
    }
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
// @phan-suppress-next-line PhanRedefineFunction -- each admin page defines its own display() entry point
function display()
{
    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/assessments')))
    {
        // But the extra is not activated
        if (!assessments_extra())
        {
            echo "<div class='card-body my-2 border'>";
            // If the extra is not restricted based on the install type
            if (!restricted_extra("riskassessment"))
            {
                echo "
                    <form name='activate' method='post' action=''>
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
            // Include the Assessments Extra
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Render the Settings + Active Assessments tabs. The Settings
            // pane hosts the existing Assessments configure UI (and the
            // activated/deactivate header + modal that display_assessments()
            // renders); the Active pane hosts the former
            // admin/active_assessments.php body via display_assessments_active().
            require_once(realpath(__DIR__ . '/../extras/assessments/includes/display.php'));
?>
            <div class="row bg-white">
                <div class="col-12">
                    <div class="card-body my-2 border">
                        <nav class="nav nav-tabs">
                            <a data-bs-target="#settings-tab-pane" data-bs-toggle="tab" class="nav-link active"><?= $escaper->escapeHtml($lang['Settings']) ?></a>
                            <a data-bs-target="#active-tab-pane" data-bs-toggle="tab" class="nav-link"><?= $escaper->escapeHtml($lang['ActiveAssessments']) ?></a>
                        </nav>
                        <div class="tab-content my-2">
                            <div id="settings-tab-pane" class="tab-pane active" role="tabpanel">
                                <?php display_assessments(); ?>
                            </div>
                            <div id="active-tab-pane" class="tab-pane" role="tabpanel">
                                <?php display_assessments_active(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
<?php
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
        <?php prevent_form_double_submit_script(); ?>
    </script>
</div>
<script>
    // When landing on the page with #active-tab-pane in the URL (e.g.
    // via the admin/active_assessments.php redirect shim that preserves
    // legacy bookmarks), activate the Active Assessments tab so the
    // user sees the right content.
    $(function () {
        if (window.location.hash === '#active-tab-pane') {
            var trigger = document.querySelector('a[data-bs-target="#active-tab-pane"]');
            if (trigger && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }
        }
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
