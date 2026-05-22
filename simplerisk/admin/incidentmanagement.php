<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar. Access opens to admin OR im_configure so
    // users reached the page from the Settings Hub's IM tile (which surfaces
    // to both permissions) can actually load the destination page.
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    require_once(realpath(__DIR__ . '/../includes/functions.php'));

    // Only request the Notification-specific WYSIWYG bundle when the
    // Notification Extra is installed and active — otherwise the
    // hugerte / editor.js script tags would 404 (and the template editor
    // markup isn't rendered anyway). The conditional avoids a console
    // 404 storm on instances where IM is configured but Notification is
    // not.
    $required_scripts = ['tabs:logic', 'multiselect', 'CUSTOM:common.js', 'JSLocalization'];
    if (function_exists('notification_extra') && notification_extra()) {
        $required_scripts[] = 'WYSIWYG:Notification';
    }

    render_header_and_sidebar(
        $required_scripts,
        ['check_any_of' => ['admin', 'im_configure']],
        required_localization_keys: ['TheNameOfAPlaybookActionCannotBeEmpty']
    );

    require_once(realpath(__DIR__ . '/../includes/permissions.php'));

    // Pull both Extras into scope at file level when present and active so
    // that file-scope variables (like the IM Extra's $tableConfig used by the
    // Add and Remove Values tab) land in the global scope rather than the
    // local scope of display(). Also makes render_template_editor_html()
    // available to the Notifications tab.
    $im_extra_installed       = is_dir(realpath(__DIR__ . '/../extras/incident_management'));
    $notification_extra_installed = is_dir(realpath(__DIR__ . '/../extras/notification'));
    if ($im_extra_installed && incident_management_extra()) {
        require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));
    }
    if ($notification_extra_installed && function_exists('notification_extra') && notification_extra()) {
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));
    }

    // Save handler for the Notifications tab. The display helper
    // (render_incident_management_notification_tab_content) reads from
    // get_setting('incident_management_notification_setting'), so we have to
    // persist the POST values before rendering or the page would show stale
    // state.
    if (function_exists('save_incident_notification_settings') && isset($_POST['save_incident_notifications'])) {
        save_incident_notification_settings();
        set_alert(true, "good", $GLOBALS['escaper']->escapeHtml($GLOBALS['lang']['NotificationSettingsUpdated']));
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    // @phan-suppress-next-line PhanRedefineFunction -- each admin page defines its own display() entry point
    function display() {
        global $lang, $escaper, $im_extra_installed;

        // Extra directory missing entirely — purchase prompt.
        if (!$im_extra_installed) {
            echo "
                <div class='card-body my-2 border'>
                    <a href='https://www.simplerisk.com/extras' target='_blank' class='text-info'>Purchase the Extra</a>
                </div>";
            return;
        }

        // Extra installed but not yet activated — show activate form.
        if (!incident_management_extra()) {
            echo "<div class='card-body my-2 border'>";
            if (!restricted_extra("incident_management")) {
                echo "
                    <form id='im_activate_extra_form' name='activate_extra' method='post' action=''>
                        <input type='hidden' name='extra_type' value='incident_management'/>
                        <input type='submit' value='" . $escaper->escapeHtml($lang['Activate']) . "' name='activate' class='btn btn-submit'/>
                    </form>
                ";
            } else {
                echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            }
            echo "</div>";
            return;
        }

        // Activated — render the deactivate header strip + the tabs. The
        // Notifications tab is conditional on the Notification Extra being
        // active: IM notification delivery (notify_incident_management_event)
        // short-circuits when notification_extra() is false, so without that
        // Extra the tab would let admins configure settings that silently
        // never fire. Hiding the tab is the clearer signal.
        $show_notifications_tab = function_exists('notification_extra')
            && notification_extra()
            && function_exists('render_template_editor_html')
            && function_exists('render_incident_management_notification_tab_content');

        echo "
            <div class='card-body my-2 border d-flex align-items-center justify-content-between'>
                <div>
                    <font color='green'><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font>
                    [" . incident_management_version() . "]
                </div>
                <form id='im_deactivate_form' name='deactivate' method='post' class='mb-0'>
                    <input type='hidden' name='extra_type' value='incident_management'/>
                    <input type='submit' name='deactivate' value='" . $escaper->escapeHtml($lang['Deactivate']) . "' class='btn btn-dark'/>
                </form>
            </div>
        ";

        echo "
            <div class='mt-2'>
                <nav class='nav nav-tabs'>
                    <a class='nav-link active' data-bs-target='#im-settings-tab' data-bs-toggle='tab'>" . $escaper->escapeHtml($lang['Settings']) . "</a>
                    <a class='nav-link' data-bs-target='#im-values-tab' data-bs-toggle='tab'>" . $escaper->escapeHtml($lang['AddAndRemoveValues']) . "</a>
                    <a class='nav-link' data-bs-target='#im-playbooks-tab' data-bs-toggle='tab'>" . $escaper->escapeHtml($lang['Playbooks']) . "</a>
        ";
        if ($show_notifications_tab) {
            echo "
                    <a class='nav-link' data-bs-target='#im-notifications-tab' data-bs-toggle='tab'>" . $escaper->escapeHtml($lang['Notifications']) . "</a>
            ";
        }
        echo "
                </nav>
            </div>
            <div class='tab-content'>
                <div id='im-settings-tab' class='tab-pane active'>
        ";
        display_incident_management_configure_settings();
        echo "
                </div>
                <div id='im-values-tab' class='tab-pane'>
        ";
        display_incident_management_configure_add_remove_values();
        echo "
                </div>
                <div id='im-playbooks-tab' class='tab-pane'>
        ";
        display_incident_management_configure_playbooks();
        echo "
                </div>
        ";
        if ($show_notifications_tab) {
            echo "
                <div id='im-notifications-tab' class='tab-pane'>
                    <form id='im_notifications_form' name='im_notifications' method='post' action=''>
                        <div class='card-body my-2 border'>
                            <div class='d-flex justify-content-between align-items-center mb-2'>
                                <h3 class='mb-0'>" . $escaper->escapeHtml($lang['IncidentManagementNotifications']) . "</h3>
                                <button type='submit' name='save_incident_notifications' value='1' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Save']) . "</button>
                            </div>
                            <div class='accordion notification-actions-accordion mt-2'>
            ";
            render_incident_management_notification_tab_content();
            echo "
                            </div>
                        </div>
                    </form>
                </div>
            ";
        }
        echo "
            </div>
        ";
    }

?>
<div class="row bg-white">
    <div class="col-12">
        <?php display(); ?>
    </div>
</div>
<?php if (function_exists('incident_management_extra') && incident_management_extra()): ?>
<script src="../extras/incident_management/js/incident_management.js?<?= current_version("app") ?>" defer></script>
<link rel="stylesheet" href="../extras/incident_management/css/incident_management.css?<?= current_version("app") ?>">
<?php endif; ?>
<?php if (function_exists('incident_management_extra') && incident_management_extra()
         && function_exists('notification_extra') && notification_extra()): ?>
<script>
    // Wire the WYSIWYG template editor up to each accordion item in the
    // Notifications tab. Same delegation pattern that display_notification()
    // emits inline — when a user expands an accordion header, the textarea
    // inside is upgraded to a HugeRTE editor instance.
    $(function () {
        $('body').on('click', '#im-notifications-tab .accordion-header button.accordion-button', function (event) {
            event.preventDefault();
            var collapsible = $(this).parent('.accordion-header').next('.accordion-collapse');
            var editor_textarea = collapsible.find('.editor textarea.template');
            if (!editor_textarea.data('active')) {
                init_editor(
                    '#' + editor_textarea.data('id'),
                    editor_textarea.data('variables'),
                    collapsible.find('.editor textarea.details_template').text(),
                    true
                );
                editor_textarea.data('active', true);
                editor_textarea.attr('name', editor_textarea.data('id'));
            }
        });
    });
</script>
<?php endif; ?>
<script>
    $(function() {
        // Hijack only the activate / deactivate forms so we can flip the
        // Extra state via the API and reload the page. The IM tab forms
        // (Settings / Notifications) must POST normally so their handlers
        // run server-side before render.
        $("form[name='activate_extra'], form[name='deactivate']").on("submit", function(e) {
            e.preventDefault();
            let formData = $(this).serialize();
            formData += "&" + $(this).find("input[type=submit]").attr('name') + "=" + encodeURIComponent($(this).find("input[type=submit]").val());

            $.ajax({
                url: BASE_URL + '/api/v2/admin/activate_deactivate_extra',
                type: 'POST',
                data: formData,
                success: function () {
                    window.location.reload();
                },
                error: function(xhr) {
                    if (!retryCSRF(xhr, this)) {
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
        });
    });
</script>
<script>
    <?php
        // Scope the double-submit guard to ONLY the synchronous, page-reloading
        // forms on this page. The IM admin page combines four tabs into one
        // URL (Settings / Add and Remove Values / Playbooks / Notifications),
        // and every playbook / playbook-action form submits via AJAX
        // (incident_management.js). With the unscoped guard, ANY form
        // submission — including a playbook AJAX submit — disables every
        // submit button on the page on a 1ms setTimeout. The AJAX flow
        // doesn't reload, so the disabled state never clears and subsequent
        // playbook-delete confirms find their Yes button frozen as disabled.
        // The AJAX forms already have their own per-handler `loading` flag
        // (incident_management.js:306) preventing double-submit.
        prevent_form_double_submit_script([
            'im_activate_extra_form',
            'im_deactivate_form',
            'im_notifications_form',
            'im_general_settings_form',
        ]);
    ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
