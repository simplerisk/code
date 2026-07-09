<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    require_once(realpath(__DIR__ . '/../includes/functions.php'));

    // Note: no `active_sidebar_menu` argument — there is no Settings
    // entry in the sidebar to highlight (it will be deleted in Task 11;
    // admins reach this page via the header settings cog). Omitting the
    // argument leaves all sidebar top-menus un-highlighted, which is
    // correct.
    // Settings Hub access opens to admin OR vm_configure OR im_configure.
    // Different permissions surface different tiles (filtered server-side by
    // required_permissions on each catalog entry); user_can_access_settings_hub()
    // is the canonical list of permissions that grant Hub access.
    render_header_and_sidebar(
        ['blockUI', 'EXTRA:JS:encryption:encryption-controls.js', 'CUSTOM:settings-hub.js'],
        ['check_any_of' => ['admin', 'vm_configure', 'im_configure']],
        required_localization_keys: [
            'LoadingReports', 'FailedToLoadReports', 'Retry',
            'AddToFavorites', 'RemoveFromFavorites', 'Favorites',
            'System', 'Customization', 'UsersAndAccess', 'DataManagement',
            'Maintenance', 'Extras',
            'All', 'NoReportsMatch', 'ClearFilters',
            'Cards', 'List',
            'Configure', 'Settings', 'Security', 'Preferences', 'HealthCheck', 'About', 'RegisterAndUpgrade',
            'Active', 'Purchase',
            'Disabled', 'StateReadyToDownload', 'StateChecking', 'StateRegistrationRequired',
            'ActivateExtraTitle', 'ActivateExtraBody', 'Activate', 'Cancel', 'ActivateExtraError',
            'DeactivateExtraTitle', 'DeactivateExtraBody', 'Deactivate', 'DeactivateExtraError',
            'EncryptionRequiredGrantLabel',
            'EncryptionInFlightTitle', 'EncryptionInFlightBody', 'OK',
            'EncryptionActivationInProgress',
            'EncryptionActivationFailedTitle', 'EncryptionActivationFailedBody',
            'EncryptionDeactivationInProgress',
            'EncryptionDeactivationFailedTitle', 'EncryptionDeactivationFailedBody',
            'RestoreFromBackup', 'RestoreFromBackupNotYetAvailable', 'ContactSupport',
            'EncryptionRestoreTitle', 'EncryptionRestoreBody',
            'EncryptionRestoreInProgress', 'EncryptionRestoreEnqueueFailed',
            'EncryptionStageStarting', 'EncryptionStageProgress', 'EncryptionStageAllDone',
            'EncryptionStageEncryptTable', 'EncryptionStageDecryptTable',
            'Close',
            'InstallExtraTitle', 'InstallExtraBody', 'Install', 'InstallExtraError', 'ExtraInstallDisabledByEnforcement',
            'PurchaseExtraTitle', 'PurchaseExtraBody', 'ViewExtras',
            'Processing',
            'CouldNotReachServicesApi',
            'BackToConfigure',
            'IncidentManagement', 'AddAndRemoveValues', 'Playbooks', 'Notifications',
        ]
    );

    global $escaper, $lang;
?>

<div class="hub" data-hub-kind="settings">
    <div class="hub__controls">
        <div class="hub__search">
            <input type="search"
                   placeholder="<?= $escaper->escapeHtmlAttr($lang['SearchConfigure']) ?>"
                   aria-label="<?= $escaper->escapeHtmlAttr($lang['SearchConfigure']) ?>" />
        </div>
        <div class="hub__chips" role="group">
            <button type="button" data-chip="all"           aria-pressed="true"><?= $escaper->escapeHtml($lang['All']) ?></button>
            <button type="button" data-chip="favorites"     aria-pressed="false">★ <?= $escaper->escapeHtml($lang['Favorites']) ?></button>
            <button type="button" data-chip="system"        aria-pressed="false"><?= $escaper->escapeHtml($lang['System']) ?></button>
            <button type="button" data-chip="customization" aria-pressed="false"><?= $escaper->escapeHtml($lang['Customization']) ?></button>
            <button type="button" data-chip="users"         aria-pressed="false"><?= $escaper->escapeHtml($lang['UsersAndAccess']) ?></button>
            <button type="button" data-chip="data"          aria-pressed="false"><?= $escaper->escapeHtml($lang['DataManagement']) ?></button>
            <button type="button" data-chip="maintenance"   aria-pressed="false"><?= $escaper->escapeHtml($lang['Maintenance']) ?></button>
            <button type="button" data-chip="extras"        aria-pressed="false"><?= $escaper->escapeHtml($lang['Extras']) ?></button>
        </div>
        <div class="hub__view-toggle" role="group" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cards']) ?> / <?= $escaper->escapeHtmlAttr($lang['List']) ?>">
            <button type="button" data-view="cards" aria-pressed="true"  aria-label="<?= $escaper->escapeHtmlAttr($lang['Cards']) ?>" title="<?= $escaper->escapeHtmlAttr($lang['Cards']) ?>"><i class="fas fa-th-large" aria-hidden="true"></i></button>
            <button type="button" data-view="list"  aria-pressed="false" aria-label="<?= $escaper->escapeHtmlAttr($lang['List']) ?>"  title="<?= $escaper->escapeHtmlAttr($lang['List']) ?>"><i class="fas fa-list" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="hub__main"></div>
</div>

<?php render_footer(); ?>
