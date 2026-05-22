<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(
        ['blockUI', 'CUSTOM:common.js'],
        ['check_admin' => true],
        required_localization_keys: ['ConfirmDeleteBackup']
    );

    // If the Backups settings form was submitted
    if (isset($_POST['submit_backup']) || isset($_POST['submit_and_backup_now'])) {

        // Set the error to false
        $error = false;

        // Get the submitted backup_auto value
        $backup_auto = (isset($_POST['backup_auto']) ? "true" : "false");

        // If the backup_auto value has changed
        if ($backup_auto != get_setting("backup_auto")) {
            // Update the backup_auto setting
            update_setting("backup_auto", $backup_auto);
        }

        // Get the submitted backup_path value
        $backup_path = $_POST['backup_path'];

        // Remove any trailing slashes from the backup path
        $backup_path = rtrim($backup_path, "/");

        // To test the validity of a relative path we have to try and create the directory if it doesn't exist already
        // but if we only created it for the test it's better if we remove it after
        $test_created_backup_path = false;

        // If the backup directory does not exist
        if (!is_dir($backup_path)) {
            // If we could not create the backup directory
            if (!mkdir($backup_path)) {
                // We have a problem
                $error = true;

                // Write a message to the error log
                $message = "Unable to create a backup directory under " . $backup_path . ".";
                set_alert(true, "bad", $message);

            } else {
                $test_created_backup_path = true;
            }
        }

        // If we don't have an error
        if (!$error) {
            // If the backup_path value has changed
            if ($backup_path != get_setting("backup_path")) {
                // Get the actual path to the document root and backup directory
                $root_path = str_replace('/', '\\', realpath(__DIR__ . '/../'));
                $dir_path = str_replace('/', '\\', realpath($backup_path));

                // If the backup file is not in the web root
                if (strpos($dir_path, $root_path) === false && $dir_path != "") {
                    // Update the backup_path setting
                    update_setting("backup_path", $backup_path);
                } else {
                    // We have an error
                    $error = true;
                    set_alert(true, "bad", $lang['ForSecurityReasonsBackupOutsideWebRoot']);
                }
            }

            // Removing the backup directory after testing its validity
            if ($test_created_backup_path) {
                rmdir($backup_path);
            }

            // If we still don't have an error
            if (!$error) {
                // Get the submitted backup_schedule value
                $backup_schedule = $_POST['backup_schedule'];

                // If the backup_schedule value has changed
                if ($backup_schedule != get_setting("backup_schedule")) {
                    // If the backup schedule is hourly, daily, weekly or monthly
                    if ($backup_schedule == "hourly" || $backup_schedule == "daily" || $backup_schedule == "weekly" || $backup_schedule == "monthly") {
                        // Update the backup_schedule setting
                        update_setting("backup_schedule", $backup_schedule);
                    }
                }

                // Get the posted backup_remove value
                $backup_remove = (int)$_POST['backup_remove'];

                // If the backup_remove value has changed
                if ($backup_remove != get_setting("backup_remove")) {
                    // If the backup_remove value is an integer value
                    if (is_int($backup_remove)) {
                        // Update the backup_remove setting
                        update_setting("backup_remove", $backup_remove);
                    }
                }
            }
        }

        // If we still don't have an error
        if (!$error) {
            // Display an alert
            set_alert(true, "good", "The settings were updated successfully.");

            $message = _lang('BackupSettingsUpdated', ['user_name' => $_SESSION['name']], false);
            write_log(0, $_SESSION['uid'], $message, 'backup');

            // If we should also do a backup
            if (isset($_POST['submit_and_backup_now'])) {

                $message = _lang('BackupInitiatedByUser', ['user_name' => $_SESSION['name']], false);
                write_debug_log($message, 'notice');
                write_log(0, $_SESSION['uid'], $message, 'backup');

                // Increasing the time for timeout
                set_time_limit(600);

                require_once(realpath(__DIR__ . '/../cron/cron_backup.php'));
                do_backup(true);
            }
        }
    }

    if (isset($_POST['delete_backup_entry']) && !empty($_POST['delete_backup_entry'])) {
        // Open the database connection
        $db = db_open();

        // Get the list of expired backups
        $stmt = $db->prepare("SELECT * FROM `backups` WHERE random_id = :id LIMIT 1;");
        $stmt->bindParam(":id", $_POST['delete_backup_entry'], PDO::PARAM_STR);
        $stmt->execute();
        $backup_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($backup_to_delete)) {
            delete_backup($backup_to_delete, true);
        } else {
            set_alert(true, "bad", "Failed to delete backup.");
        }

        // Close the database connection
        db_close($db);
    }

    global $escaper, $lang;

    // Get the backup settings
    $backup_auto = get_setting('backup_auto');
    $backup_path= get_setting('backup_path');
    $phpExecutablePath = getPHPExecutableFromPath();
?>
<div class="row">
    <div class="col-12">
        <div class="card-body my-2 border">
            <h4 class="page-title"><?= $escaper->escapeHtml($lang['Instructions']); ?></h4>
            <div class="row">
                <div class="col-12">
                    <?= $escaper->escapeHtml($lang['PlaceTheFollowingInYourCrontabToRunAutomatically']); ?> :
                    <br>
                    * * * * * <?= $escaper->escapeHtml($phpExecutablePath ? $phpExecutablePath : $lang['PathToPhpExecutable']); ?> <?= (strncasecmp(PHP_OS, 'WIN', 3) == 0 ? "" : "-f") ?> <?= realpath(__DIR__ . '/../cron/cron.php'); ?> > /dev/null 2>&1
                </div>
            </div>
        </div>
        <form name="backups_settings" method="post" action="" class="block-on-submit">
            <div class="card-body my-2 border">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-check mr-sm-2">
                            <input type="checkbox" name="backup_auto" id="backup_auto" <?= ($backup_auto == "true") ? "checked='yes' " : ""?> class="form-check-input" >
                            <label class="form-check-label mb-0 ms-2" for="backup_auto"><?= $escaper->escapeHTML($lang['AutomaticallyBackupThisSimpleRiskInstance']); ?></label>
                            <p class="ms-2 text-danger"><?= $escaper->escapeHtml($lang['ForSecurityReasonsBackupOutsideWebRoot']); ?></p>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['BackupLocation']); ?> :</label>
                                    <input type="text" name="backup_path" value="<?= $escaper->escapeHtml($backup_path); ?>" class="form-control"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHTML($lang['BackupSchedule']); ?> :</label>
                                    <select name="backup_schedule" id="backup_schedule" onchange="javascript: dropdown_transport()" class="form-select">
                                        <option value="hourly"<?= (get_setting('backup_schedule') == "hourly") ? " selected" : ""; ?>><?= $escaper->escapeHTML($lang['Hourly']); ?></option>
                                        <option value="daily"<?= (get_setting('backup_schedule') == "daily") ? " selected" : ""; ?>><?= $escaper->escapeHTML($lang['Daily']); ?></option>
                                        <option value="weekly"<?= (get_setting('backup_schedule') == "weekly") ? " selected" : ""; ?>><?= $escaper->escapeHTML($lang['Weekly']); ?></option>
                                        <option value="monthly"<?= (get_setting('backup_schedule') == "monthly") ? " selected" : ""; ?>><?= $escaper->escapeHTML($lang['Monthly']); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row" style="align-items:flex-end">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHTML($lang['RemoveBackupsAfter']); ?></label>
                                    <input value="<?= $escaper->escapeHTML(get_setting('backup_remove')); ?>" name="backup_remove" id="backup_remove" type="number"min="1" max="365" class="form-control"/>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="form-group"><?= $escaper->escapeHTML($lang['days']); ?></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="">
                                   <button type="submit" name="submit_backup" class="btn btn-dark"><?= $escaper->escapeHtml($lang['Save']); ?></button>
                                   <button type="submit" name="submit_and_backup_now" class="btn btn-submit"><?= $escaper->escapeHtml($lang['SaveAndBackupNow']); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="card-body my-2 border backups-list">
<?php
    // Open the database connection
    $db = db_open();
    // Get the list of backups ordered by timestamp
    $stmt = $db->prepare("SELECT * FROM `backups` ORDER BY `timestamp` DESC;");
    $stmt->execute();
    $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Close the database connection
    db_close($db);
?>
            <h4 class="page-title mb-1"><?= $escaper->escapeHtml($lang['Backups']); ?></h4>
            <p style="color: red;"><?= $escaper->escapeHtml($lang['PrivateTmpMessage']); ?></p>
            <table class="table border m-b-0">
               <thead>
                    <tr>
                        <td>
                            <u><?= $escaper->escapeHtml($lang['BackupDate']); ?></u>
                        </td>
                        <td width="20px">&nbsp;</td>
<?php
    // If this is not a hosted customer
    if (get_setting('hosting_tier') == false) {
        echo "
                        <td><u>{$escaper->escapeHtml($lang['ApplicationBackup'])}</u></td>
                        <td width='20px'>&nbsp;</td>
        ";
    } else {
        echo "
                        <td width='0px'>&nbsp;</td>
                        <td width='0px'>&nbsp;</td>
        ";
    }
?>
                        <td>
                            <u><?= $escaper->escapeHtml($lang['DatabaseBackup']); ?></u>
                        </td>
	                           	<td>
                            <u><?= $escaper->escapeHtml($lang['Actions']); ?></u>
                        </td>
                    </tr>
                </thead>
                <tbody>
<?php
    // For each backup
    foreach ($backups as $backup) {

        // Display the backup information
        echo "
                    <tr>
                        <td>{$escaper->escapeHtml($backup['timestamp'])}</td>
                        <td>&nbsp;</td>
        ";

        // If this is not a hosted customer
        if (get_setting('hosting_tier') == false) {

            // Check if the file exists
            if (file_exists($backup['app_zip_file_name'])) {

                // Display the Download link for the application backup
                echo "
                            <td><a target='_blank' href='download_backup.php?type=app&id={$escaper->escapeHtml($backup['random_id'])}'>{$escaper->escapeHtml($lang['Download'])}</a></td>
                            <td>&nbsp;</td>
                ";
            } else {
                // Display a warning if the file doesn't exist for some reason
                echo "
                            <td><div class='missing-file-warning'>{$escaper->escapeHtml($lang['MissingFile'])}</div></td>
                            <td>&nbsp;</td>
                ";
            }
        } else {

            // If this is a hosted customer
            // Do not display a Download link for the application backup
            echo "
                        <td width='0px'>&nbsp;</td>
                        <td width='0px'>&nbsp;</td>
            ";

        }

        // Check if the file exists
        if (file_exists($backup['db_zip_file_name'])) {
            echo "
                            <td><a target='_blank' href='download_backup.php?type=db&id={$escaper->escapeHtml($backup['random_id'])}'>{$escaper->escapeHtml($lang['Download'])}</a></td>
            ";
        } else {
            // Display a warning if the file doesn't exist for some reason
            echo "
                            <td><div class='missing-file-warning'>{$escaper->escapeHtml($lang['MissingFile'])}</div></td>
            ";
        }
        echo "
                            <td><button type='button' class='btn btn-important btn-xs delete-backup-entry' data-id='{$escaper->escapeHtml($backup['random_id'])}'>{$escaper->escapeHtml($lang['Delete'])}</button></td>
                        </tr>
        ";
    }
?>
                </tbody>
            </table>
        </div>
        <form name="backup_delete_form" method="post" action="">
            <input type="hidden" name="delete_backup_entry" value=""/>
        </form>
    </div>
</div>
<script>
    $(function() {
        $(document).on('click', 'button.delete-backup-entry', function(e) {e.stopPropagation(); confirm(_lang['ConfirmDeleteBackup'], () => {
            let form = $("form[name='backup_delete_form']");
            form.find("input[name='delete_backup_entry']").val($(this).attr('data-id'));
            form.submit();
        })});
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
