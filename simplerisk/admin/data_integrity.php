<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    global $lang, $escaper;

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    // Not a DataTables-driven grid -- the .sr-table markup below (checkbox
    // + action columns) is rendered by data-integrity.js itself, not the
    // datatable_ajax_uri convention used elsewhere, so the 'datatables'
    // module isn't loaded here. GET .../issues is capped server-side at
    // DATA_INTEGRITY_ISSUES_LIST_LIMIT (see get_open_data_integrity_issues())
    // rather than paginated.
    render_header_and_sidebar(['CUSTOM:pages/data-integrity.js'], ['check_admin' => true]);
?>

<div class="sr-table-card" id="data-integrity-grid">
    <div class="sr-tabs" id="data-integrity-tabs">
        <button type="button" class="sr-tab is-active" data-tab="invalid_text_encoding" id="data-integrity-tab-text">
            <?= $escaper->escapeHtml($lang['DataIntegrityTextEncoding']) ?>
            <span class="sr-tab-count d-none" id="data-integrity-tab-count-text"></span>
        </button>
        <button type="button" class="sr-tab" data-tab="file_content_mismatch" id="data-integrity-tab-file">
            <?= $escaper->escapeHtml($lang['DataIntegrityFileEncoding']) ?>
            <span class="sr-tab-count d-none" id="data-integrity-tab-count-file"></span>
        </button>
    </div>
    <div class="sr-table-toolbar" id="data-integrity-toolbar">
        <div class="sr-table-title">
            <?= $escaper->escapeHtml($lang['DataIntegrity']) ?>
            <span class="sr-table-count d-none" id="data-integrity-count"></span>
        </div>
        <div class="sr-table-tools">
            <button type="button" class="btn btn-danger" id="data-integrity-scan-now"><?= $escaper->escapeHtml($lang['ScanNow']) ?></button>
        </div>
    </div>
    <div class="sr-bulk-bar d-none" id="data-integrity-bulk-bar">
        <button type="button" class="sr-bulk-clear" id="data-integrity-bulk-clear" aria-label="<?= $escaper->escapeHtmlAttr($lang['Clear']) ?>">&times;</button>
        <span class="sr-bulk-count" id="data-integrity-bulk-count"></span>
        <div class="sr-bulk-actions">
            <button type="button" class="btn btn-danger btn-sm" id="data-integrity-bulk-repair"><?= $escaper->escapeHtml($lang['RepairSelected']) ?></button>
        </div>
    </div>
    <div class="sr-table-scroll" id="data-integrity-table-scroll">
        <table class="sr-table" id="data-integrity-table-text">
            <thead>
                <tr>
                    <th class="sr-check-col"><input type="checkbox" id="data-integrity-select-all"></th>
                    <th><?= $escaper->escapeHtml($lang['Location']) ?></th>
                    <th><?= $escaper->escapeHtml($lang['BrokenValue']) ?></th>
                    <th><?= $escaper->escapeHtml($lang['SuggestedFix']) ?></th>
                    <th><?= $escaper->escapeHtml($lang['Status']) ?></th>
                    <th class="sr-actions-col"></th>
                </tr>
            </thead>
            <tbody id="data-integrity-tbody-text"></tbody>
        </table>
        <table class="sr-table d-none" id="data-integrity-table-file">
            <thead>
                <tr>
                    <th><?= $escaper->escapeHtml($lang['Location']) ?></th>
                    <th><?= $escaper->escapeHtml($lang['File']) ?></th>
                    <th><?= $escaper->escapeHtml($lang['Status']) ?></th>
                    <th class="sr-actions-col"></th>
                </tr>
            </thead>
            <tbody id="data-integrity-tbody-file"></tbody>
        </table>
    </div>
    <div class="sr-table-empty d-none" id="data-integrity-empty">
        <div class="sr-table-empty-icon"><i class="fa fa-circle-check" aria-hidden="true"></i></div>
        <div class="sr-table-empty-title"><?= $escaper->escapeHtml($lang['DataIntegrityAllCaughtUpTitle']) ?></div>
        <div class="sr-table-empty-body"><?= $escaper->escapeHtml($lang['DataIntegrityAllCaughtUpBody']) ?></div>
    </div>
    <div class="sr-table-foot">
        <div class="sr-table-foot-left d-none" id="data-integrity-showing-capped"></div>
        <div class="sr-table-foot-right" id="data-integrity-foot-count"></div>
    </div>
</div>

<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
