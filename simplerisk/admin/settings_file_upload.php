<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'CUSTOM:common.js'], ['check_admin' => true]);

    // Check if a new file type was submitted
    if (isset($_POST['add_file_type'])) {

        $name = !empty($_POST['new_file_type']) ? trim($_POST['new_file_type']) : "";
        $extension = !empty($_POST['file_type_ext']) ? trim($_POST['file_type_ext']) : "";

        // Check if the file extension is valid
        if (!preg_match('/^[a-zA-Z0-9]+$/', $extension)) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['PleaseEnterAValidExtension']));
            header("Location: settings_file_upload.php");
            exit();

        }

        // Check if the file extension has a valid length
        if (strlen($extension) > 6) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['TheFileTypeExtensionIsTooLong']));
            header("Location: settings_file_upload.php");
            exit();

        }

        // Insert a new file type (250 chars) with extension (10 chars)
        $success = add_file_type($name, $extension);

        // If the add was successful
        if ($success) {
            // Display an alert
            set_alert(true, "good", "A new upload file type was added successfully.");
        }
    }

    // Check if a file type was deleted
    if (isset($_POST['delete_file_type'])) {
        $value = (int)$_POST['file_types'];

        // Check if the file type is selected
        if (!$value) {
            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['PleaseSelectAFileTypeBeforeDeleting']));
            header("Location: settings_file_upload.php");
            exit();
        }

        // Verify value is an integer
        if (is_int($value)) {

            // Delete the file type
            delete_value("file_types", $value);

            // Cleanup any references to the deleted file type
            cleanup_after_delete("file_types");

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['AnExistingUploadFileTypeWasRemovedSuccessfully']));

        }
    }

    // Check if a file type extension was deleted
    if (isset($_POST['delete_file_extension'])) {

        $value = (int)$_POST['file_type_extensions'];

        // Check if a file type extension is selected
        if (!$value) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['PleaseSelectAnExtensionBeforeDeleting']));

            header("Location: settings_file_upload.php");
            exit();

        }

        // Verify value is an integer
        if (is_int($value)) {

            // Delete the file type extension
            delete_value("file_type_extensions", $value);

            // Cleanup any references to the deleted file type extension
            cleanup_after_delete("file_type_extensions");

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['AnExistingUploadFileExtensionWasRemovedSuccessfully']));

        }
    }

    // Check if the maximum file upload size was updated
    if (isset($_POST['update_max_upload_size'])) {
        // A value above what PHP or MySQL can take is refused, not stored with a warning
        [$saved, $message_key] = update_max_upload_size($_POST['size'] ?? '');

        // The text is escaped once, when the alert is read
        set_alert(true, $saved ? "good" : "bad", $lang[$message_key]);
    }

    // Get the max upload size setting
    $simplerisk_max_upload_size = get_setting('max_upload_size');

    global $escaper, $lang;
?>
<div class="row">
    <div class="col-6">
        <div class="card-body my-2 border">
            <h4 class="page-title"><?= $escaper->escapeHtml($lang['AllowedFileTypes']);?></h4>
            <form id="add_file_type_form" name="filetypes" method="post" action="">
                <input type="hidden" name="add_file_type" value="true"/>
                <div class="row" style="align-items:flex-end">
                    <div class="col-8">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['AddNewFileTypeOf']); ?><span class="required">*</span> :</label>
                            <input name="new_file_type" type="text" maxlength="250" size="10" class="form-control" required title="<?= $escaper->escapeHtml($lang['FileType']); ?>"/>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['WithExtension']); ?><span class="required">*</span> :</label>
                            <input name="file_type_ext" type="text" maxlength="6" size="10" class="form-control"  required title="<?= $escaper->escapeHtml($lang['FileExtension']); ?>"/>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                           <input type="submit" value="<?= $escaper->escapeHtml($lang['Add']); ?>" class="btn btn-submit form-control">
                        </div>
                    </div>
                </div>
            </form>
            <form id="delete_file_type_form" method="post" action="">
                <input type="hidden" name="delete_file_type" value="true">
                <div class="row" style="align-items:flex-end">
                    <div class="col-8">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['DeleteCurrentFileTypeOf']); ?><span class="required">*</span> :</label>
<?php
                            create_dropdown("file_types", display_empty_name: false);
?>
                        </div>
                    </div>
                    <div class="col-2"></div>
                    <div class="col-2">
                        <div class="form-group">
                           <input type="submit" value="<?= $escaper->escapeHtml($lang['Delete']); ?>" class="btn btn-dark form-control" style="margin-top: 30px;">
                        </div>
                    </div>
                </div>
            </form>
            <form id="delete_file_extension_form" method="post" action="">
                <input type="hidden" name="delete_file_extension" value="true"/>
                <div class="row" style="align-items:flex-end">
                    <div class="col-8">
                        <div class="">
                            <label><?= $escaper->escapeHtml($lang['DeleteCurrentExtensionOf']); ?><span class="required">*</span> :</label>
<?php
                            create_dropdown("file_type_extensions", display_empty_name: false);
?>
                        </div>
                    </div>
                    <div class="col-2"></div>
                    <div class="col-2">
                        <div class="">
                           <input type="submit" value="<?= $escaper->escapeHtml($lang['Delete']); ?>" class="btn btn-dark form-control" style="margin-top: 30px;">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-6 d-flex flex-column">
        <div class="card-body my-2 border flex-grow-1">
            <h4 class="page-title"><?= $escaper->escapeHtml($lang['MaximumUploadFileSize']);?></h4>
            <form method="post" action="">
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                           <input name="size" type="number" maxlength="50" size="20" value="<?= $escaper->escapeHtml(get_setting('max_upload_size')); ?>" class="form-control"/>
                        </div>
                    </div>
                    <div class="col-1">
                        <div class="form-group"><?= $escaper->escapeHtml($lang['Bytes']); ?></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
<?php
    // A value saved before the check existed, or one that PHP or MySQL has since fallen below
    $effective_max_upload_size = max_upload_size_effective();
    if ($effective_max_upload_size < (int)$simplerisk_max_upload_size) {
        echo "
                        <p class='text-danger'>{$escaper->escapeHtml($lang['MaxUploadSizeCapped'])} {$escaper->escapeHtml(number_format($effective_max_upload_size))}</p>
        ";
    }
    // The limits the saved value is above, by the same rule that refuses a new value
    foreach (max_upload_size_exceeded_limits($simplerisk_max_upload_size) as $warning_key) {
        echo "
                        <p class='text-danger'>{$escaper->escapeHtml($lang[$warning_key])}</p>
        ";
    }
?>
                        <input type="submit" value="<?= $escaper->escapeHtml($lang['Update']); ?>" name="update_max_upload_size" class="btn btn-submit"/>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {

        $("#add_file_type_form").submit(function() {

            // Prevent the form from submitting
            event.preventDefault();

            // Check if the elements are empty or trimmed empty
            if (!checkAndSetValidation(this)) {
                return;
            }

            // Check if the file type extension is valid.
            const file_type_extension = $("[name='file_type_ext']", this).val();

            // Regex to validate a file extension format
            const regex = /^[a-zA-Z0-9]+$/;

            if (!regex.test(file_type_extension)) {
                showAlertFromMessage("<?= $escaper->escapeHtml($lang['PleaseEnterAValidExtension']); ?>");
                return;
            }

            // Check if the file type extension has a valid length
            if (file_type_extension.length > 6) {
                showAlertFromMessage("<?= $escaper->escapeHtml($lang['TheFileTypeExtensionIsTooLong']); ?>");
                return;
            }

            // Submit the form using native javascript
            $(this)[0].submit();

        });

        $("#delete_file_type_form").submit(function() {

            // Prevent the form from submitting
            event.preventDefault();

            // Check if the elements are empty or trimmed empty
            if (!$("#file_types", this).val()) {
                showAlertFromMessage("<?= $escaper->escapeHtml($lang['PleaseSelectAFileTypeBeforeDeleting']); ?>");
                return;
            }

            // Display the confirmation dialog
            confirm("<?= $escaper->escapeJs($lang['AreYouSureYouWantToDeleteThisFileType']); ?>", () => {

                // Submit the form using native javascript
                $(this)[0].submit();

            });
        });

        $("#delete_file_extension_form").submit(function() {

            // Prevent the form from submitting.
            event.preventDefault();

            // Check if the file type extension is selected.
            if (!$("#file_type_extensions", this).val()) {
                showAlertFromMessage("<?= $escaper->escapeHtml($lang['PleaseSelectAnExtensionBeforeDeleting']); ?>");
                return;
            }

            // Display a confirmation dialog.
            confirm("<?= $escaper->escapeJs($lang['AreYouSureYouWantToDeleteThisExtension']); ?>", () => {
                // Submit the form using native javascript.
                $(this)[0].submit();
            })

        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
