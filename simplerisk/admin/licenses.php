<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    render_header_and_sidebar(
        ['blockUI', 'CUSTOM:licenses.js'],
        ['check_admin' => true],
        required_localization_keys: [
            'LoadingLicenseData', 'Active', 'Free', 'Expired', 'Unlicensed',
            'NoLicensedExtras', 'NoExpiredExtras', 'NoUnlicensedExtras',
            'LicenseStateUnknownRetryShortly', 'StartDate', 'EndDate', 'Unlimited',
        ]
    );

    global $escaper, $lang;
?>
<div id="licenses-page" class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <label class="mb-0"><?php echo $escaper->escapeHtml($lang['InstanceID']); ?>:</label>
            <span class="ms-2"><?php echo $escaper->escapeHtml(get_setting("instance_id")); ?></span>
        </div>
        <button type="button" id="refresh-licenses-btn" class="btn btn-primary">
            <?php echo $escaper->escapeHtml($lang['RefreshLicenses']); ?>
        </button>
    </div>

    <nav class="nav nav-tabs" role="tablist">
        <a class="nav-link active" id="licensed-tab" data-bs-toggle="tab" data-bs-target="#licensed-pane" role="tab" aria-controls="licensed-pane" aria-selected="true">
            <?php echo $escaper->escapeHtml($lang['Active']); ?> <span class="badge bg-secondary" data-count="licensed">0</span>
        </a>
        <a class="nav-link" id="expired-tab" data-bs-toggle="tab" data-bs-target="#expired-pane" role="tab" aria-controls="expired-pane" aria-selected="false">
            <?php echo $escaper->escapeHtml($lang['Expired']); ?> <span class="badge bg-secondary" data-count="expired">0</span>
        </a>
        <a class="nav-link" id="unlicensed-tab" data-bs-toggle="tab" data-bs-target="#unlicensed-pane" role="tab" aria-controls="unlicensed-pane" aria-selected="false">
            <?php echo $escaper->escapeHtml($lang['Unlicensed']); ?> <span class="badge bg-secondary" data-count="unlicensed">0</span>
        </a>
    </nav>

    <div id="licenses-error" class="alert alert-warning mt-2 d-none" role="alert"></div>

    <div class="tab-content my-2">
        <?php foreach (['licensed' => 'licensed-pane', 'expired' => 'expired-pane', 'unlicensed' => 'unlicensed-pane'] as $tab => $pane): ?>
        <div id="<?php echo $escaper->escapeHtml($pane); ?>" class="tab-pane<?php echo $tab === 'licensed' ? ' active' : ''; ?>" role="tabpanel">
            <table class="table table-striped border">
                <thead>
                    <tr>
                        <th><?php echo $escaper->escapeHtml($lang['ExtraName']); ?></th>
                        <th><?php echo $escaper->escapeHtml($lang['Description']); ?></th>
                        <th><?php echo $escaper->escapeHtml($lang['License']); ?></th>
                    </tr>
                </thead>
                <tbody data-rows="<?php echo $escaper->escapeHtml($tab); ?>"></tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
    render_footer();
?>
