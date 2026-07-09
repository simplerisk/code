<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));

// If the extra directory is missing entirely, fall through to the same
// "Purchase the Extra" link the legacy page rendered — the Encryption Extra
// may not be present on every distribution.
if (!is_dir(realpath(__DIR__ . '/../extras/encryption'))) {
    render_header_and_sidebar([], ['check_admin' => true], 'Encrypted Database Extra', 'Configure', 'Extras');
    echo "<div class='card-body my-2 border'>";
    echo "  <a href=\"https://www.simplerisk.com/extras\" target=\"_blank\" class='text-info'>Purchase the Extra</a>";
    echo "</div>";
    render_footer();
    return;
}

require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

// Page is only meaningful when the Extra is active. When it isn't, send the
// admin back to the Configure Hub where the activation tile lives. The
// activation flow runs through settings-hub.js → encryption-controls.js;
// this page does not host an activation form.
if (!encryption_extra()) {
    global $lang;
    set_alert(true, "good", $lang['EncryptionInactiveRedirect']);
    header('Location: /admin/');
    exit;
}

// Render with encryption-controls.js loaded — the page wires its two buttons
// (Deactivate, Restore) to SimpleRiskEncryption inside display_encryption().
render_header_and_sidebar(
    ['blockUI', 'EXTRA:JS:encryption:encryption-controls.js'],
    ['check_admin' => true],
    'Encrypted Database Extra',
    'Configure',
    'Extras',
    required_localization_keys: [
        'Cancel', 'Deactivate', 'Processing', 'RestoreFromBackup',
        'ContactSupport',
        'DeactivateExtraTitle', 'DeactivateExtraBody', 'DeactivateExtraError',
        'EncryptedDatabaseExtra',
        'EncryptionDeactivationInProgress',
        'EncryptionDeactivationFailedTitle', 'EncryptionDeactivationFailedBody',
        'EncryptionActivationFailedTitle', 'EncryptionActivationFailedBody',
        'EncryptionRestoreInProgress', 'EncryptionRestoreEnqueueFailed',
        'EncryptionRestoreTitle', 'EncryptionRestoreBodyWhy', 'EncryptionRestoreBodyWhat',
        'Continue',
        'EncryptionStageStarting', 'EncryptionStageProgress', 'EncryptionStageAllDone',
        'EncryptionStageEncryptTable', 'EncryptionStageDecryptTable',
        'Close',
        'EncryptionDeleteBackupTitle', 'EncryptionDeleteBackupBodyWhy', 'EncryptionDeleteBackupBodyWhat',
        'EncryptionDeleteBackupError',
        'EncryptionModalWhyLabel', 'EncryptionModalWhatHappensLabel',
        'EncryptionBackupDownloadTooltip',
        'EncryptionFieldsPanelHeading', 'EncryptionFieldsHelp',
        'EncryptionFieldsTableColumn', 'EncryptionFieldsFieldColumn', 'EncryptionFieldsEncryptedColumn',
        'EncryptionFieldsNoneFound', 'EncryptionFieldsLockTooltipPrefix',
    ]
);
?>
<div class="row bg-white">
    <div class="col-12">
        <?php display_encryption(); ?>
    </div>
</div>
<?php render_footer(); ?>
