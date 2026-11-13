<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

global $lang, $escaper;

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

// Add and remove values (SR-2475). A static shell: lookup-values.js fills it from the
// v2 API (GET /admin/lookup-values/lists and .../lists/{list}/values; POST, PATCH and
// DELETE on .../values for add, rename and delete). The page's own admin gate below is
// not the control -- every endpoint checks for an administrator itself.
render_header_and_sidebar(['CUSTOM:pages/lookup-values.js'], ['check_admin' => true]);
?>
<div class="sr-lv" id="lookup-values" data-initial-list="<?= $escaper->escapeHtmlAttr(isset($_GET['list']) && is_string($_GET['list']) ? $_GET['list'] : '') ?>">
    <div class="sr-lv-layout">

        <!-- Left: the lists, grouped, with one live search over list names and the values in them -->
        <aside class="sr-lv-rail" aria-label="<?= $escaper->escapeHtmlAttr($lang['LookupLists']) ?>">
            <div class="sr-lv-search">
                <label class="sr-qlabel" for="lv-search"><?= $escaper->escapeHtml($lang['LookupFindAListOrValue']) ?></label>
                <input type="search" class="form-control" id="lv-search" maxlength="100" autocomplete="off">
            </div>

            <!-- Narrow widths: the rail collapses to this select -->
            <div class="sr-lv-select">
                <label class="sr-qlabel" for="lv-list-select"><?= $escaper->escapeHtml($lang['List']) ?></label>
                <select class="form-select" id="lv-list-select"></select>
            </div>

            <nav class="sr-lv-lists" id="lv-lists" aria-label="<?= $escaper->escapeHtmlAttr($lang['LookupLists']) ?>"></nav>
            <p class="sr-lv-nomatch d-none" id="lv-rail-empty"><?= $escaper->escapeHtml($lang['LookupNoListsMatch']) ?></p>
        </aside>

        <!-- Right: the selected list as a table card -->
        <section class="sr-table-card sr-lv-card" id="lv-card" aria-labelledby="lv-title">
            <div class="sr-table-toolbar">
                <div class="sr-lv-heading">
                    <div class="sr-table-title">
                        <span id="lv-title"></span>
                        <span class="sr-table-count" id="lv-count"></span>
                    </div>
                    <div class="sr-lv-sub" id="lv-subtitle"></div>
                </div>
                <div class="sr-table-tools">
                    <div class="dt-search sr-lv-filter">
                        <input type="search" id="lv-filter" maxlength="100" autocomplete="off"
                               placeholder="<?= $escaper->escapeHtmlAttr($lang['LookupFilterValues']) ?>"
                               aria-label="<?= $escaper->escapeHtmlAttr($lang['LookupFilterValues']) ?>">
                    </div>
                    <div class="sr-table-toolbar-actions">
                        <button type="button" class="btn btn-danger" id="lv-add-button">
                            <i class="fa fa-plus" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['LookupAddValue']) ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="visually-hidden" id="lv-announcer" aria-live="polite" aria-atomic="true"></div>

            <div class="sr-table-scroll">
                <table class="sr-table" id="lv-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= $escaper->escapeHtml($lang['Name']) ?></th>
                            <th scope="col" class="sr-lv-use-col"><?= $escaper->escapeHtml($lang['LookupInUse']) ?></th>
                            <th scope="col" class="sr-actions-col"><span class="visually-hidden"><?= $escaper->escapeHtml($lang['Actions']) ?></span></th>
                        </tr>
                    </thead>
                    <tbody id="lv-tbody"></tbody>
                </table>
            </div>

            <div class="sr-table-empty d-none" id="lv-empty">
                <div class="sr-table-empty-icon"><i class="fa fa-list" id="lv-empty-icon" aria-hidden="true"></i></div>
                <div class="sr-table-empty-title" id="lv-empty-title"></div>
                <div class="sr-table-empty-body" id="lv-empty-body"></div>
                <div class="sr-table-empty-action d-none" id="lv-empty-action-wrap">
                    <button type="button" class="btn btn-outline-secondary" id="lv-empty-action"></button>
                </div>
            </div>

            <!-- The dashed row: the keyboard path for adding several values in a row -->
            <div class="sr-lv-addrow">
                <div class="sr-lv-addbox">
                    <i class="fa fa-plus" aria-hidden="true"></i>
                    <label class="visually-hidden" for="lv-new"><?= $escaper->escapeHtml($lang['LookupNewValue']) ?></label>
                    <input type="text" id="lv-new" autocomplete="off" placeholder="<?= $escaper->escapeHtmlAttr($lang['LookupAddAValueAndPressEnter']) ?>">
                    <span class="sr-lv-counter" id="lv-new-counter"></span>
                </div>
                <div class="sr-lv-error d-none" id="lv-new-error" role="alert"></div>
            </div>

            <div class="sr-table-foot">
                <div class="sr-table-foot-left"><span class="dt-info" id="lv-foot-count"></span></div>
                <div class="sr-table-foot-right"><span class="sr-lv-foot-note"><?= $escaper->escapeHtml($lang['LookupChangesSaveAsYouGo']) ?></span></div>
            </div>
        </section>
    </div>

    <!-- One destructive-confirm for every delete (design-system §8): a plain confirm for a
         value nothing uses, with the usage warning and the optional "move the records" select
         for one in use. lookup-values.js sets the text. -->
    <div class="modal fade sr-modal" id="lv-delete-modal" tabindex="-1" aria-labelledby="lv-delete-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="sr-modal-icon sr-modal-icon--danger"><i class="fa fa-trash" aria-hidden="true"></i></span>
                    <h4 class="modal-title" id="lv-delete-title"></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"></button>
                </div>
                <div class="modal-body sr-qform">
                    <section class="sr-qcard">
                        <div class="sr-qcard-body">
                            <p class="mb-0" id="lv-delete-plain"></p>
                            <div class="sr-qnote d-none" id="lv-delete-note">
                                <i class="fa fa-triangle-exclamation sr-qnote-ico" aria-hidden="true"></i>
                                <span id="lv-delete-note-text"></span>
                            </div>
                            <div class="sr-qfield d-none" id="lv-delete-move">
                                <label class="sr-qlabel" for="lv-delete-target" id="lv-delete-target-label"></label>
                                <select class="form-select" id="lv-delete-target"></select>
                                <span class="sr-qhint" id="lv-delete-target-hint"></span>
                            </div>
                            <div class="alert alert-danger d-none mb-0" id="lv-delete-error" role="alert"></div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal" id="lv-delete-cancel"><?= $escaper->escapeHtml($lang['Cancel']) ?></button>
                    <button type="button" class="btn btn-danger" id="lv-delete-commit"><?= $escaper->escapeHtml($lang['LookupDeleteValue']) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
