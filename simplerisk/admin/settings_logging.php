<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar([], ['check_admin' => true]);

    // If the Logging settings form was submitted
    if (isset($_POST['update_debug_settings'])) {

        // Update the critical logging
        $logging_critical = isset($_POST['logging_critical']) ? 1 : 0;
        $current_logging_critical = get_setting("logging_critical");
        if ($logging_critical != $current_logging_critical) {
            update_setting("logging_critical", $logging_critical);
        }

        // Update the error logging
        $logging_error = isset($_POST['logging_error']) ? 1 : 0;
        $current_logging_error = get_setting("logging_error");
        if ($logging_error != $current_logging_error) {
            update_setting("logging_error", $logging_error);
        }

        // Update the warning logging
        $logging_warning = isset($_POST['logging_warning']) ? 1 : 0;
        $current_logging_warning = get_setting("logging_warning");
        if ($logging_warning != $current_logging_warning) {
            update_setting("logging_warning", $logging_warning);
        }

        // Update the notice logging
        $logging_notice = isset($_POST['logging_notice']) ? 1 : 0;
        $current_logging_notice = get_setting("logging_notice");
        if ($logging_notice != $current_logging_notice) {
            update_setting("logging_notice", $logging_notice);
        }

        // Update the info logging
        $logging_info = isset($_POST['logging_info']) ? 1 : 0;
        $current_logging_info = get_setting("logging_info");
        if ($logging_info != $current_logging_info) {
            update_setting("logging_info", $logging_info);
        }

        // Update the debug logging
        $logging_debug = isset($_POST['logging_debug']) ? 1 : 0;
        $current_logging_debug = get_setting("logging_debug");
        if ($logging_debug != $current_logging_debug) {
            update_setting("logging_debug", $logging_debug);
        }

        // Display an alert
        set_alert(true, "good", "The settings were updated successfully.");
    }

    global $escaper, $lang;
?>
<div class="row">
    <div class="col-12">
        <form name="debug_settings" method="post" action="">
            <div class="card-body my-2 border">
                <div class="row">
                    <div class="col-8 form-group">
                        <div class="form-check mr-sm-4">
                            <input <?php if($escaper->escapeHtml(get_setting('logging_critical')) == 1){ echo "checked"; } ?> name="logging_critical" id="logging_critical" type="checkbox"  class="form-check-input" size="2" value="90">
                            <label class="form-check-label mb-0 ms-2"  for="logging_critical"><?= $escaper->escapeHtml($lang['EnableLoggingCritical']); ?></label>
                        </div>
                        <div class="form-check mr-sm-4">
                            <input <?php if($escaper->escapeHtml(get_setting('logging_error')) == 1){ echo "checked"; } ?> name="logging_error" id="logging_error" type="checkbox"  class="form-check-input" size="2" value="90">
                            <label class="form-check-label mb-0 ms-2"  for="logging_error"><?= $escaper->escapeHtml($lang['EnableLoggingError']); ?></label>
                        </div>
                        <div class="form-check mr-sm-4">
                            <input <?php if($escaper->escapeHtml(get_setting('logging_warning')) == 1){ echo "checked"; } ?> name="logging_warning" id="logging_warning" type="checkbox"  class="form-check-input" size="2" value="90">
                            <label class="form-check-label mb-0 ms-2"  for="logging_warning"><?= $escaper->escapeHtml($lang['EnableLoggingWarning']); ?></label>
                        </div>
                        <div class="form-check mr-sm-4">
                            <input <?php if($escaper->escapeHtml(get_setting('logging_notice')) == 1){ echo "checked"; } ?> name="logging_notice" id="logging_notice" type="checkbox"  class="form-check-input" size="2" value="90">
                            <label class="form-check-label mb-0 ms-2"  for="logging_notice"><?= $escaper->escapeHtml($lang['EnableLoggingNotice']); ?></label>
                        </div>
                        <div class="form-check mr-sm-4">
                            <input <?php if($escaper->escapeHtml(get_setting('logging_info')) == 1){ echo "checked"; } ?> name="logging_info" id="logging_info" type="checkbox"  class="form-check-input" size="2" value="90">
                            <label class="form-check-label mb-0 ms-2"  for="logging_info"><?= $escaper->escapeHtml($lang['EnableLoggingInfo']); ?></label>
                        </div>
                        <div class="form-check mr-sm-4">
                            <input <?php if($escaper->escapeHtml(get_setting('logging_debug')) == 1){ echo "checked"; } ?> name="logging_debug" id="logging_debug" type="checkbox"  class="form-check-input" size="2" value="90">
                            <label class="form-check-label mb-0 ms-2"  for="logging_debug"><?= $escaper->escapeHtml($lang['EnableLoggingDebug']); ?></label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <p><strong>Note: </strong>We no longer configure log file location and all logs will go into /var/log/simplerisk.log, if the file system permissions allow it, and the Apache error_log file if they do not.</p>
                </div>
                <div class="row">
                    <div class="col-6">
                        <button type="submit" name="update_debug_settings" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Update']); ?></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
