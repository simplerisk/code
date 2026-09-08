<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    // Section-landing page (Governance > Document Program): the submenu IS
    // the current page, so breadcrumb_title_key equals active_sidebar_submenu
    // -- sidebar.php's own convention for not duplicating the leaf crumb.
    // Matches compliance/index.php's identical pattern.
    $breadcrumb_title_key = "DocumentProgram";
    $active_sidebar_menu = "Governance";
    $active_sidebar_submenu = "DocumentProgram";
    render_header_and_sidebar(['datetimerangepicker', 'multiselect', 'blockUI', 'CUSTOM:common.js', 'CUSTOM:pages/governance.js', 'CUSTOM:sr-faceted-picker.js', 'CUSTOM:sr-row-actions-menu.js', 'CUSTOM:sr-select.js', 'CUSTOM:sr-audit-trail.js', 'CUSTOM:pages/governance-documents.js', 'CUSTOM:pages/governance-document-audit-trail.js', 'datatables',
        // The insights band below is a UILayout instance -- 'UILayoutWidget'
        // pulls in both the includes/Widgets/UILayout.php CLASS (header.php's
        // 'UILayoutWidget' case require_once's it; there is no Composer
        // autoload entry for it) and the Gridstack JS/CSS the band's tile
        // rendering depends on. Same asset governance/index.php and
        // compliance/index.php's own insights bands already declare.
        'UILayoutWidget'], ['check_any_of' => ['governance', 'view_documentation']], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu);

    // READ GATE: `governance` OR `view_documentation`. view_documentation's own
    // shipped description promises view access to Document Program "without
    // granting the rest of the Governance menu" -- which was untrue in both
    // directions while this page (and the two documents API read handlers)
    // checked `governance` alone: holding governance was sufficient, and
    // holding view_documentation alone reached nothing. Matches the OR-gate
    // get_document_types_api() already uses.

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    // compliance_file_download_denied() -- the bulk "Download selected" POST
    // handler below is the only reason this page needs it; nothing else here
    // pulls compliance.php into scope transitively.
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

    checkUploadedFileSizeErrors();

    // Row-action + bulk-select gates for the redesigned .sr-table-card grid
    // below (design-system.md §6). has_permission() reads the same
    // session-cached flag getTabularDocumentsResponse() (this page's own
    // AJAX handlers) checks via check_permission() -- the two are
    // functionally identical (has_permission() just skips the debug
    // logging), so this is a naming change, not a behavior change, EXCEPT
    // for the added is_admin() OR: neither has_permission() nor
    // check_permission() special-cases admin (they only read the
    // permission actually assigned to the user's role), so an admin who
    // wasn't individually granted these document permissions would
    // otherwise see the row actions/bulk bar disappear despite being able
    // to do everything elsewhere in the app.
    $can_add = is_admin() || has_permission('add_documentation');
    $can_edit = is_admin() || has_permission('modify_documentation');
    $can_delete = is_admin() || has_permission('delete_documentation');
    $can_approve = is_admin() || has_permission('approve_documentation');
    $can_view = is_admin() || has_permission('view_documentation');

    // Audit-trail export (Import/Export Extra), mirroring
    // governance/document_exceptions.php's own handler exactly -- same POST
    // key, same admin + extra gating, same log_type-scoped download.
    if (isset($_POST['download_audit_log']))
    {
        if (is_admin())
        {
            if (import_export_extra())
            {
                require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
                download_audit_logs(get_param('post', 'days', 7), 'document', $escaper->escapeHtml($lang['DocumentAuditTrailReport']));
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($lang['YouCantDownloadBecauseImportExportExtraDisabled']));
                refresh();
            }
        }
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['AdminPermissionRequired']));
            refresh();
        }
    }

    // Bulk "Download selected" (Import/Export Extra): zips the current file
    // of each selected document and streams it. import_export_extra() is
    // required unconditionally -- unlike the permission check below, there
    // is no is_admin() bypass for the Extra itself, matching every other
    // Extra-gated affordance on this page (the feature genuinely doesn't
    // work without it, regardless of who's asking). The permission check
    // IS the same is_admin()-bypassed one download_compliance_file() applies
    // per single file (includes/compliance.php) -- checked once here for
    // the whole batch since the decision never varies per document (see
    // stream_documents_zip()'s docblock in includes/governance.php).
    if (isset($_POST['download_selected_documents']))
    {
        if (!import_export_extra())
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['YouCantDownloadBecauseImportExportExtraDisabled']));
            refresh();
        }
        elseif (compliance_file_download_denied_for_download('documents', 'is_admin', 'check_permission_exception', 'check_permission'))
        {
            redirect_permission_denied('DownloadFilePermissionMessage', 'bulk documents download');
        }
        else
        {
            $bulk_download_truncated = false;
            $bulk_download_ids = normalize_bulk_download_ids($_POST['document_ids'] ?? null, $bulk_download_truncated);
            if ($bulk_download_truncated) {
                // Shown on the NEXT page load of this tab -- the download
                // itself streams into the hidden form's target="_blank" tab,
                // so this alert can't render inline the way a normal POST
                // response would (same architecture as the audit-log export
                // above: a background download, not a page navigation).
                set_alert(true, "bad", _lang_raw('DocumentsDownloadTruncated', array('limit' => GOVERNANCE_MAX_BULK_DOWNLOAD_IDS)));
            }
            if (empty($bulk_download_ids) || stream_documents_zip($bulk_download_ids) === false) {
                set_alert(true, "bad", $escaper->escapeHtml($lang['NoDownloadableDocumentsSelected']));
                refresh();
            }
        }
    }

// Document Program insights band -- one tile per seeded document type,
// each linking straight to the grid's own ?type=<slug> filter (matches the
// sibling define_frameworks_insights / define_tests_insights bands' "direct
// link, no ?insight= indirection" convention). Collapsible (the band costs
// ~120px above the grid it introduces); Edit-layout shows the real control
// when the Customization Extra is active, or the shared locked teaser
// otherwise (customization_acquisition_state(), includes/settings_catalog.php)
// -- same treatment as both sibling bands. Same OR-gate as the page itself
// (governance OR view_documentation) since the UILayout framework's
// required_permission field can't express an OR -- document_program_read_permitted()
// (includes/governance.php) is the single definition of that gate, shared with
// get_ui_widget_document_program_insights() in api/v2/includes/api.php, which
// enforces it on the render path.
if (document_program_read_permitted()) {
    require_once(realpath(__DIR__ . '/../includes/settings_catalog.php'));
    (new \includes\Widgets\UILayout('document_program_insights', [
        'show_edit_layout' => true,
        'edit_layout_locked_state' => customization_acquisition_state(is_admin(), get_setting('registration_registered') == 1),
        'collapsible' => true,
    ]))->render();
}
?>
<!-- No .row/.col-12 wrapper: .sr-table-card renders as a DIRECT child of
     .content, matching every other direct-child-card surface (Manage/
     Initiate Audits, .sr-qform, .sr-aihub, .sr-connectivity-explorer,
     .sr-soa--selector) -- see scss/modules/_tables.scss's
     `.content:has(> .sr-table-card)` rule, which zeroes .content's own
     10px margin so the card's left edge lands flush with the page title/
     breadcrumb above it instead of re-inset ~10px by col-12's uncancelled
     padding. -->
<!-- Document Program grid (design-system.md §6): a single client-
     rendered .sr-table-card holding every document type in one flat
     table, rather than the tab-per-category EasyUI treegrids this
     replaced. The insights band above narrows it to one seeded type at a
     time via a real ?type=<slug> link -- governance-documents.js reads it
     on load. DocumentProgramGrid also fetches GET /api/v2/governance/
     documents/treegrid?type= and populates the grid's tbody client-side;
     no server-printed <table> rows. -->
        <div class="sr-table-card" id="document-program-grid"
            data-can-add="<?= $can_add ? 'true' : 'false'; ?>"
            data-can-edit="<?= $can_edit ? 'true' : 'false'; ?>"
            data-can-delete="<?= $can_delete ? 'true' : 'false'; ?>"
            data-can-approve="<?= $can_approve ? 'true' : 'false'; ?>"
            data-can-view="<?= $can_view ? 'true' : 'false'; ?>">
            <div class="sr-table-toolbar" id="document-program-toolbar">
                <div class="sr-table-title">
                    <?= $escaper->escapeHtml($lang['DocumentProgram']); ?>
                    <span class="sr-table-count d-none" id="document-program-count"></span>
                </div>
                <div class="sr-table-tools">
                    <!-- Columns picker (design-system.md §6c). .colpicker wraps a
                         .filterbtn trigger and a .colpanel dropdown of checkboxes;
                         DocumentProgramGrid toggles the matching data-col="..."
                         th/td display on change -- mirrors Define Exceptions'
                         reference implementation exactly. -->
                    <div class="colpicker">
                        <button type="button" class="filterbtn sr-table-filter" id="document-program-colpicker-btn" aria-haspopup="true" aria-expanded="false">
                            <i class="fa fa-table-columns" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['Columns']); ?>
                        </button>
                        <div class="colpanel d-none" id="document-program-colpanel"></div>
                    </div>
    <?php if ($can_add) { ?>
                    <a class='btn btn-danger project--add' href='#' id='document-add-btn' role='button'>+ <?= $escaper->escapeHtml($lang['AddDocument']); ?></a>
    <?php } ?>
                </div>
            </div>
    <?php
        // "Download selected" (Import/Export Extra): same decision function
        // and .sr-locked teaser the audit trail's Export button uses further
        // down this page (framework_acquisition_path_states()['import']) --
        // passing the REAL is_admin() (not hardcoded true) so a non-admin
        // who can view documents but lacks the Extra sees the "ask your
        // admin" state instead of a link to an admin-only page they can't
        // reach, while an admin sees the real purchase/deactivated/
        // activated state with a working link.
        $can_bulk_download = $can_view;
        $document_bulk_download_state = null;
        if ($can_bulk_download) {
            $document_bulk_download_state = framework_acquisition_path_states(
                true, // not add_new_frameworks -- see the note on the SoA's identical call
                is_admin(),
                get_setting('registration_registered') == 1,
                false, false, // scf_installed/activated -- not this surface's route
                is_extra_installed('import-export'),
                import_export_extra()
            )['import'] ?? null;
        }
    ?>
    <?php if ($can_approve || $can_delete || $can_bulk_download) { ?>
            <div class="sr-bulk-bar d-none" id="document-program-bulk-bar">
                <button type="button" class="sr-bulk-clear" id="document-program-bulk-clear" aria-label="<?= $escaper->escapeHtmlAttr($lang['Clear']); ?>">&times;</button>
                <span class="sr-bulk-count" id="document-program-bulk-count"></span>
                <div class="sr-bulk-actions">
        <?php if ($can_approve) { ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="document-program-bulk-approve"><?= $escaper->escapeHtml($lang['ApproveSelected']); ?></button>
        <?php } ?>
        <?php if ($can_bulk_download && $document_bulk_download_state && !$document_bulk_download_state['locked']) { ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="document-program-bulk-download"><?= $escaper->escapeHtml($lang['DownloadSelected']); ?></button>
        <?php } elseif ($can_bulk_download && $document_bulk_download_state) {
                $bulk_download_note_key = $document_bulk_download_state['note_key'] ?? null;
                $bulk_download_link_key = $document_bulk_download_state['link_key'] ?? null;
                $bulk_download_note = $bulk_download_note_key !== null ? ($lang[$bulk_download_note_key] ?? '') : '';
                $bulk_download_link_text = $bulk_download_link_key !== null ? ($lang[$bulk_download_link_key] ?? '') : '';
                $bulk_download_href = $document_bulk_download_state['unlock_href'] ?? null;
        ?>
                    <span class="sr-locked" data-sr-locked-state="<?= $escaper->escapeHtmlAttr($document_bulk_download_state['state'] ?? ''); ?>">
                        <button type="button" class="btn btn-outline-secondary btn-sm sr-locked--btn" disabled aria-disabled="true" title="<?= $escaper->escapeHtmlAttr($bulk_download_note); ?>">
                            <?= $escaper->escapeHtml($lang['DownloadSelected']); ?>
                            <span class="sr-locked-badge"><i class="fa fa-lock" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['LockedAffordanceBadge']); ?></span>
                        </button>
            <?php if ($bulk_download_link_text && $bulk_download_href) { ?>
                        <a class="sr-locked-link" href="<?= $escaper->escapeHtmlAttr($bulk_download_href); ?>"<?= !empty($document_bulk_download_state['external']) ? ' target="_blank" rel="noopener"' : ''; ?> title="<?= $escaper->escapeHtmlAttr($bulk_download_note); ?>"><i class="fa fa-circle-info" aria-hidden="true"></i></a>
            <?php } ?>
                    </span>
        <?php } ?>
        <?php if ($can_delete) { ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="document-program-bulk-delete"><?= $escaper->escapeHtml($lang['DeleteSelected']); ?></button>
        <?php } ?>
                </div>
            </div>
            <!-- Static shell (gets csrf-magic's own output-buffer token
                 injection, same as display_audit_download_btn()'s form
                 above) -- JS fills #document-program-bulk-download-ids with
                 one hidden document_ids[] input per selected row right
                 before submit, since the selection is client-side state a
                 server-rendered form can't know in advance. -->
    <?php if ($can_bulk_download) { ?>
            <form action="" method="post" target="_blank" id="document-program-bulk-download-form" class="d-none">
                <input type="hidden" name="download_selected_documents" value="1">
                <span id="document-program-bulk-download-ids"></span>
            </form>
    <?php } ?>
    <?php } ?>
            <!-- Filters row (design-system.md §6b/§6c): Document Type, Framework,
                 Status, Approver, Next Review Date. Only filters live here --
                 search stays in the toolbar above. Simply on screen at full
                 width; collapses behind the Filters·n toggle below the compact
                 tier (see _tables.scss's .sr-qf-toggle rules -- no bespoke CSS
                 needed here, this reuses Define Tests' shipped pattern
                 verbatim). Options are built client-side by
                 DocumentProgramGrid from the loaded rows (Framework/Approver)
                 or a fixed enum (everything else) -- see renderFilterOptions()
                 in governance-documents.js. -->
            <button type="button" class="sr-qf-toggle" id="document-program-filters-toggle" aria-expanded="false" aria-controls="document-program-quickfilters">
                <i class="fa fa-filter" aria-hidden="true"></i>
                <span><?= $escaper->escapeHtml($lang['Filters']); ?></span>
                <span class="sr-qf-toggle-count" id="document-program-filters-count" hidden></span>
            </button>
            <div class="sr-table-quickfilters" id="document-program-quickfilters">
                <div class="sr-qf-selects">
                    <select id="document-program-type-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['DocumentType']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['DocumentType']); ?>"></select>
                    <select id="document-program-framework-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['ControlFrameworks']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['ControlFrameworks']); ?>"></select>
                    <!-- Status/Review's <option>s are entirely JS-built (like
                         Type/Framework/Approver above), not just count-
                         decorated: a value with zero matching documents is
                         dropped from the list rather than shown with a "0"
                         chip -- see renderFilterOptions() in
                         governance-documents.js. -->
                    <select id="document-program-status-filter" class="form-select" title="<?= $escaper->escapeHtmlAttr($lang['Status']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Status']); ?>"></select>
                    <select id="document-program-approver-filter" class="form-select" title="<?= $escaper->escapeHtmlAttr($lang['Approver']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Approver']); ?>"></select>
                    <select id="document-program-review-filter" class="form-select" title="<?= $escaper->escapeHtmlAttr($lang['NextReviewDate']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['NextReviewDate']); ?>"></select>
                    <!-- Only the two multi-selects lack a clickable "All X" row
                         of their own (a multi-select's options are all plain
                         toggles -- an "All types" entry among them would read
                         as just another type to pick, not a clear action, the
                         same reason compliance-define-tests.js's own framework/
                         family multi-selects don't have one either). This
                         button is the actual clear affordance for the whole
                         row, matching that page's #define-tests-clear-filters
                         -- placed last so it reads as "done picking, start
                         over" rather than interrupting the facet list, and
                         shown only once a filter is actually narrowing the
                         grid (see syncFilterCount() in governance-documents.js). -->
                    <button type="button" class="btn btn-link btn-sm sr-qf-clear d-none" id="document-program-filters-clear"><?= $escaper->escapeHtml($lang['ClearFilters']); ?></button>
                    <!-- Deep link to the Customization Extra's Document Types
                         manager -- lives beside the type filter since
                         that's the control it explains ("here's what these
                         values are and where to add more"), not in the
                         top .sr-table-tools row with Columns/Add Document. -->
                    <a href="<?= $escaper->escapeHtmlAttr(build_url('admin/customization.php?fgroup=document')); ?>" class="sr-manage-link"><i class="fa fa-gear" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['ManageDocumentTypes']); ?></a>
                </div>
            </div>
            <!-- DocumentProgramGrid (js/simplerisk/pages/governance-documents.js) builds the
                 <table class="sr-table">/thead/tbody, the three empty states, and
                 the .sr-table-foot (page-size + row-count + pager) fresh into this
                 container on every load/reload -- the same full-rebuild-per-load
                 shape js/simplerisk/pages/self-assessment.js's initSrTable()
                 already uses for its own .sr-table-card grids, rather than a
                 static PHP-printed skeleton DataTables reuses in place. DataTables'
                 OWN generated search box is relocated up into .sr-table-tools
                 above (relocateSearchIntoTools()), so there is no separate
                 server-rendered search input here. -->
            <div id="document-program-body"></div>
        </div>
        <!-- AUDIT TRAIL (design-system.md §6/§7): a second .sr-table-card below
             the grid, reusing the exact hand-built-<table>-handed-to-DataTables
             shape the Document Program grid above already uses (design-system.md
             §6), plus the same .sr-qf-selects filter-row idiom and an
             sr-state-pill (§7) for the Activity column instead of one sentence
             baked into the message. The toggle is a plain disclosure row
             (js/simplerisk/pages/governance-document-audit-trail.js), not
             Bootstrap's accordion chrome. Gated on its OWN permission --
             view_document_audit_logs OR admin -- separate from Document
             Program visibility itself: a user can see the grid without seeing
             who touched what. Points at the same
             GET /api/v2/governance/documents/audit_log Define Exceptions'
             accordion (governance/document_exceptions.php) also reads, now
             extended with document_id/document_name/user_id/user_name/activity
             (see get_documents_audit_log_api() in includes/api.php). -->
    <?php if (is_admin() || has_permission('view_document_audit_logs')) { ?>
        <div class="sr-table-card sr-audit-trail-card my-2" id="document-audit-trail">
            <div class="sr-table-toolbar" id="document-audit-trail-toolbar">
                <button type="button" class="sr-audit-trail-toggle" id="document-audit-trail-toggle" aria-expanded="false" aria-controls="document-audit-trail-collapse">
                    <i class="fa fa-chevron-right sr-audit-trail-caret" aria-hidden="true"></i>
                    <span class="sr-table-title"><?= $escaper->escapeHtml($lang['AuditTrail']); ?> <span class="sr-table-count d-none" id="document-audit-trail-count"></span></span>
                </button>
                <div class="sr-table-tools">
                    <button type="button" class="sr-table-filter" id="document-audit-trail-refresh" title="<?= $escaper->escapeHtmlAttr($lang['Refresh']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Refresh']); ?>"><i class="fa fa-sync" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="d-none" id="document-audit-trail-collapse">
                <!-- Search + Export: collapsed with the rest of the card body
                     (unlike Refresh above, which stays reachable even
                     collapsed) -- a second .sr-table-toolbar row, matching
                     the grid's own toolbar shape, so DataTables' relocated
                     search box and the export trigger read as one set. -->
                <div class="sr-table-toolbar" id="document-audit-trail-inner-toolbar">
                    <div class="sr-table-tools">
        <?php
            // Audit-trail export (Import/Export Extra). Live download
            // mechanism unchanged from the legacy accordion: same admin +
            // extra gate, same POST handler above, same download_form,
            // same CSRF token. The bare .audit-select-folder class below
            // (no styling reaches it without its old .audit-option-container
            // ancestor -- see scss/modules/_custom.scss) is ONLY there so
            // display_audit_download_btn()'s own unmodified jQuery
            // ($('[name=days]', '.audit-select-folder')) still finds the
            // date-range <select> and exports the range actually selected,
            // instead of always falling back to 7 days.
            //
            // display_audit_download_btn()'s own trigger is a shared,
            // UNSCOPED global button (.download-btn in scss/custom.scss --
            // a circular Excel-green icon that doesn't match this page's
            // button language and is used by other pages too, so it isn't
            // safe to restyle in place). #document-audit-trail-export below
            // is a page-styled `.sr-table-filter` button (same family as
            // Refresh/Columns) that PROXIES a click onto the real, hidden
            // trigger (js/simplerisk/pages/governance-document-audit-trail.js)
            // -- the actual submission logic is untouched, only what the
            // viewer sees and clicks changes. `.sr-audit-trail-card
            // .download-btn { display: none; }` (scss/modules/_governance.scss)
            // hides the shared trigger ONLY within this card, not globally.
            //
            // An admin who does NOT have the Extra gets the shared
            // "show what's possible, mark what's locked" teaser
            // (.sr-locked* component, scss/modules/_locked-affordance.scss)
            // instead of nothing -- the same decision function and copy the
            // Statement of Applicability's export buttons already use
            // (reports/statement_of_applicability.php), so this surface can't
            // drift into saying something different about the same Extra in
            // the same state. Only the 'import' route is asked for; the SCF
            // route is the Frameworks page's business, not this trail's.
            if (is_admin()) {
                $document_audit_export = framework_acquisition_path_states(
                    true, // not add_new_frameworks -- see the note on the SoA's identical call
                    true, // is_admin -- already checked above
                    get_setting('registration_registered') == 1,
                    false, false, // scf_installed/activated -- not this surface's route
                    is_extra_installed('import-export'),
                    import_export_extra()
                )['import'] ?? null;

                if ($document_audit_export && !$document_audit_export['locked']) {
                    require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
                    display_audit_download_btn();
            ?>
                        <button type="button" class="sr-table-filter" id="document-audit-trail-export"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['Export']); ?></button>
            <?php
                } elseif ($document_audit_export) {
                    $document_audit_export_note_key = $document_audit_export['note_key'] ?? null;
                    $document_audit_export_link_key = $document_audit_export['link_key'] ?? null;
                    $document_audit_export_note = $document_audit_export_note_key !== null ? ($lang[$document_audit_export_note_key] ?? '') : '';
                    $document_audit_export_link_text = $document_audit_export_link_key !== null ? ($lang[$document_audit_export_link_key] ?? '') : '';
                    $document_audit_export_href = $document_audit_export['unlock_href'] ?? null;
            ?>
                        <span class="sr-locked" data-sr-locked-state="<?= $escaper->escapeHtmlAttr($document_audit_export['state'] ?? ''); ?>">
                            <button type="button" class="sr-table-filter sr-locked--btn" disabled aria-disabled="true" title="<?= $escaper->escapeHtmlAttr($document_audit_export_note); ?>">
                                <i class="fa-solid fa-file-excel" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['Export']); ?>
                                <span class="sr-locked-badge"><i class="fa fa-lock" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['LockedAffordanceBadge']); ?></span>
                            </button>
                    <?php if ($document_audit_export_link_text && $document_audit_export_href) { ?>
                            <a class="sr-locked-link" href="<?= $escaper->escapeHtmlAttr($document_audit_export_href); ?>"<?= !empty($document_audit_export['external']) ? ' target="_blank" rel="noopener"' : ''; ?> title="<?= $escaper->escapeHtmlAttr($document_audit_export_note); ?>"><i class="fa fa-circle-info" aria-hidden="true"></i></a>
                    <?php } ?>
                        </span>
            <?php
                }
            }
        ?>
                    </div>
                </div>
                <div class="sr-table-quickfilters" id="document-audit-trail-quickfilters">
                    <div class="sr-qf-selects" id="document-audit-trail-filters">
                        <div class="audit-select-folder">
                            <select name="days" id="document-audit-trail-range-filter" class="form-select" title="<?= $escaper->escapeHtmlAttr($lang['DateRange']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['DateRange']); ?>">
                                <option value="7" selected><?= $escaper->escapeHtml($lang['PastWeek']); ?></option>
                                <option value="30"><?= $escaper->escapeHtml($lang['PastMonth']); ?></option>
                                <option value="90"><?= $escaper->escapeHtml($lang['PastQuarter']); ?></option>
                                <option value="180"><?= $escaper->escapeHtml($lang['Past6Months']); ?></option>
                                <option value="365"><?= $escaper->escapeHtml($lang['PastYear']); ?></option>
                                <option value="36500"><?= $escaper->escapeHtml($lang['AllTime']); ?></option>
                            </select>
                        </div>
                        <select id="document-audit-trail-document-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['DocumentName']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['DocumentName']); ?>"></select>
                        <select id="document-audit-trail-activity-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['Activity']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Activity']); ?>"></select>
                        <select id="document-audit-trail-user-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['User']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['User']); ?>"></select>
                        <button type="button" class="btn btn-link btn-sm sr-qf-clear d-none" id="document-audit-trail-filters-clear"><?= $escaper->escapeHtml($lang['ClearFilters']); ?></button>
                    </div>
                </div>
                <div id="document-audit-trail-body"></div>
            </div>
        </div>
    <?php } ?>

<!-- MODAL WINDOW FOR APPROVE / UNAPPROVE DOCUMENT (design-system.md §8, Confirm
     type -- unapprove reverses approve, so neither is destructive). Shared by
     the row-level approve/unapprove icon and the bulk bar's "Approve selected"
     action; js/simplerisk/pages/governance-documents.js swaps the icon/title/body/
     primary-button text and the POST target (document approve/unapprove vs
     batch-approve) based on which action opened it. -->
<div id="document-approve-confirm" class="modal fade sr-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon" id="document-approve-confirm-icon"><i class="fa fa-check" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="document-approve-confirm-title"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cancel']); ?>"></button>
            </div>
            <div class="modal-body">
                <section class="sr-qcard">
                    <div class="sr-qcard-body">
                        <p id="document-approve-confirm-body" class="mb-0"></p>
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-submit" id="document-approve-confirm-yes"></button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR BULK-DELETE SELECTED DOCUMENTS (design-system.md §8,
     Destructive type). The row-level delete icon keeps using the legacy
     #document-delete-modal below, which supports deleting a single file
     version -- a bulk selection has no single "version" to target, so bulk
     delete always removes the whole document, looping
     POST /api/v2/documents/delete once per selected id (document_id only,
     no version) the same way js/simplerisk/pages/governance-exceptions.js's
     bulk delete loops POST /exceptions/delete. -->
<?php if ($can_delete) { ?>
<div id="document-bulk-delete-confirm" class="modal fade sr-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon" id="document-bulk-delete-confirm-icon"><i class="fa fa-trash" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="document-bulk-delete-confirm-title"><?= $escaper->escapeHtml($lang['DeleteSelected']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cancel']); ?>"></button>
            </div>
            <div class="modal-body">
                <section class="sr-qcard">
                    <div class="sr-qcard-body">
                        <p id="document-bulk-delete-confirm-body" class="mb-0"></p>
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-danger" id="document-bulk-delete-confirm-yes"><?= $escaper->escapeHtml($lang['Delete']); ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- CONTROLS PICKER (design-system.md §5/§14b) shared by both the Add and
     Edit document forms' control_ids[] field. framework_ids[]/team_ids[]/
     additional_stakeholders[] stay on bootstrap-multiselect (tens-of-rows
     rosters -- frameworks, teams, users) as the document's OWN standalone
     attributes; a framework's control roster can run into the hundreds or
     low thousands (§14b's sizing rule), so control_ids[] gets a search+pick
     dialog instead of a dropdown menu, the same shape as
     includes/compliance.php's display_control_picker_modal() (the reference
     implementation) -- Framework + Control Family facets over the FULL
     cross-framework roster, backed by the same shared engine,
     createFacetedPicker() (js/simplerisk/sr-faceted-picker.js). This picker
     no longer depends on framework_ids[] to narrow what it searches (see
     loadDocumentControlsRoster()/getDocumentControlPicker() below) -- the
     document's own Frameworks field and the picker's Framework facet are
     now two independent choices, same as Define Exceptions'
     #exception-control-picker. renderControlChips() below is still a
     page-local reimplementation (this page doesn't load compliance.js's
     controlsRoster/controlsById roster), but openDocumentControlPicker()
     (below) is a thin wrapper that calls the shared engine directly, not a
     copy of it. -->
<?php
    $document_picker_frameworks = getAvailableControlFrameworkList(true);
    is_array($document_picker_frameworks) || $document_picker_frameworks = [];
    $document_picker_families = getAvailableControlFamilyList();
    is_array($document_picker_families) || $document_picker_families = [];
    $document_picker_sort_by_name = static function ($a, $b) {
        return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    };
    usort($document_picker_frameworks, $document_picker_sort_by_name);
    usort($document_picker_families, $document_picker_sort_by_name);
    // data-picker-facet carries the id the roster's `family`/`frameworks` ids
    // are matched against; the count span is filled client-side.
    $document_picker_facet_options = static function ($rows, $facet) use ($escaper) {
        $html = '';
        foreach ($rows as $row) {
            $id = (int)($row['value'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = (string)($row['name'] ?? '');
            $html .= "
                        <button type='button' class='sr-picker-facet' data-picker-facet='{$facet}' data-picker-value='{$id}'
                                aria-pressed='false' title='" . $escaper->escapeHtmlAttr($name) . "'>
                            <span class='sr-picker-facet-label'>" . $escaper->escapeHtml($name) . "</span>
                            <span class='sr-picker-facet-count'></span>
                        </button>";
        }
        return $html;
    };
?>
<div id="document-control-picker" class="modal fade sr-modal sr-picker-modal" tabindex="-1" aria-hidden="true" aria-labelledby="document-control-picker-title">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-list-check" aria-hidden="true"></i></span>
                <h5 class="modal-title" id="document-control-picker-title"><?= $escaper->escapeHtml($lang['ChooseControls']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']); ?>"></button>
            </div>
            <div class="sr-picker-search">
                <i class="fa fa-magnifying-glass sr-picker-search-icon" aria-hidden="true"></i>
                <input type="text" id="document-control-picker-search" class="sr-picker-search-input" autocomplete="off"
                       placeholder="<?= $escaper->escapeHtmlAttr($lang['SearchControlsPlaceholder']); ?>"
                       aria-label="<?= $escaper->escapeHtmlAttr($lang['SearchControlsPlaceholder']); ?>">
                <span class="sr-picker-scope" id="document-control-picker-scope"></span>
            </div>

            <div class="sr-picker-panes">
                <div class="sr-picker-pane sr-picker-pane--facet">
                    <div class="sr-picker-pane-head">
                        <span class="sr-picker-step">1</span>
                        <span><?= $escaper->escapeHtml($lang['Framework']); ?></span>
                        <button type="button" class="sr-picker-clear" data-picker-clear="framework"><?= $escaper->escapeHtml($lang['Clear']); ?></button>
                    </div>
                    <div class="sr-picker-scroll" id="document-control-picker-frameworks">
                        <button type="button" class="sr-picker-facet" data-picker-facet="framework" data-picker-value="" aria-pressed="true">
                            <span class="sr-picker-facet-label"><?= $escaper->escapeHtml($lang['AllFrameworks']); ?></span>
                            <span class="sr-picker-facet-count"></span>
                        </button>
                        <?= $document_picker_facet_options($document_picker_frameworks, 'framework') ?>
                    </div>
                </div>

                <div class="sr-picker-pane sr-picker-pane--facet">
                    <div class="sr-picker-pane-head">
                        <span class="sr-picker-step">2</span>
                        <span><?= $escaper->escapeHtml($lang['ControlFamily']); ?></span>
                        <button type="button" class="sr-picker-clear" data-picker-clear="family"><?= $escaper->escapeHtml($lang['Clear']); ?></button>
                    </div>
                    <div class="sr-picker-scroll" id="document-control-picker-families">
                        <button type="button" class="sr-picker-facet" data-picker-facet="family" data-picker-value="" aria-pressed="true">
                            <span class="sr-picker-facet-label"><?= $escaper->escapeHtml($lang['AllFamilies']); ?></span>
                            <span class="sr-picker-facet-count"></span>
                        </button>
                        <?= $document_picker_facet_options($document_picker_families, 'family') ?>
                    </div>
                </div>

                <div class="sr-picker-pane sr-picker-pane--list">
                    <div class="sr-picker-pane-head">
                        <span class="sr-picker-step">3</span>
                        <span><?= $escaper->escapeHtml($lang['Controls']); ?></span>
                        <span class="sr-picker-pane-count" id="document-control-picker-count"></span>
                    </div>
                    <div class="sr-picker-scroll" id="document-control-picker-list" role="listbox" aria-multiselectable="true"
                         aria-label="<?= $escaper->escapeHtmlAttr($lang['Controls']); ?>"></div>
                </div>

                <div class="sr-picker-pane sr-picker-pane--selected">
                    <div class="sr-picker-pane-head">
                        <span><?= $escaper->escapeHtml($lang['Selected']); ?></span>
                        <span class="sr-picker-pane-count" id="document-control-picker-selected-count"></span>
                    </div>
                    <div class="sr-picker-scroll sr-picker-selected" id="document-control-picker-selected"></div>
                </div>
            </div>

            <div class="modal-footer sr-picker-foot">
                <span class="sr-picker-hint"><?= $escaper->escapeHtml($lang['PickerKeyboardHint']); ?></span>
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-submit" id="document-control-picker-commit"><?= $escaper->escapeHtml($lang['UseTheseControls']); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR ADDING DOCUMENT (design-system.md §8, Form-in-modal type) -->
<div id="document-program--add" class="modal fade sr-modal" tabindex="-1" role="dialog" aria-labelledby="document-program--add-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-file-lines" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="document-program--add-title"><?= $escaper->escapeHtml($lang['AddDocument']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cancel']); ?>"></button>
            </div>
            <div class="modal-body">
                <form id="add-document-form" class="sr-qcard-form" action="#" method="post" autocomplete="off" enctype="multipart/form-data">
                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-file-lines" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['DocumentDetails']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['DocumentType']); ?><span class="required">*</span></label>
                                    <!-- Rendered from the `document_types` lookup (get_document_types())
                                         rather than four hardcoded <option>s, so a category added via
                                         Add/Remove Values can actually be assigned to a document. Option
                                         values stay the stored category NAME; see
                                         document_type_options_html() in includes/governance.php. -->
                                    <select required class="document_type form-select" name="document_type">
                                        <?= document_type_options_html(); /* @phan-suppress-current-line SecurityCheck-XSS -- escaped inside document_type_options_html() */ ?>
                                    </select>
                                    <span class="sr-qhint"><?= $escaper->escapeHtml($lang['NeedANewCategoryManageDocumentTypes']); ?> <a href="<?= $escaper->escapeHtmlAttr(build_url('admin/customization.php?fgroup=document')); ?>" target="_blank"><?= $escaper->escapeHtml($lang['ManageDocumentTypes']); ?> &rarr;</a></span>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['DocumentName']); ?><span class="required">*</span></label>
                                    <input required type="text" name="document_name" id="document_name" value="" class="form-control" />
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ParentDocument']); ?></label>
                                    <div class="parent_documents_container">
                                        <select class="form-select">
                                            <option>--</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['DocumentStatus']); ?></label>
    <?php
                                    create_dropdown("document_status", "1", "status", false, false, false);
    ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-diagram-project" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['WhereItApplies']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Frameworks']); ?></label>
    <?php
                                    create_multiple_dropdown("frameworks", NULL, "framework_ids");
    ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Controls']); ?></label>
                                    <select multiple="multiple" id="add_document_control_ids" name="control_ids[]" class="form-select sr-picker-value"></select>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-users" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['WhoIsInvolved']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['DocumentOwner']); ?></label>
    <?php
                                    create_dropdown("enabled_users", NULL, "document_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0);
    ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
    <?php
                                    create_multiusers_dropdown("additional_stakeholders");
    ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Team']); ?></label>
    <?php
                                    create_multiple_dropdown("team", NULL, "team_ids");
    ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Approver']); ?></label>
    <?php
                                    create_dropdown("enabled_users", NULL, "approver", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0);
    ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-calendar-check" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['ReviewCadence']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['CreationDate']); ?></label>
                                    <input type="text" class="form-control datepicker" name="creation_date" value="<?= $escaper->escapeHtml(date(get_default_date_format())); ?>">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['LastReview']); ?></label>
                                    <input type="text" class="form-control datepicker" name="last_review_date">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ReviewFrequency']); ?></label>
                                    <div class="input-group">
                                        <input type="number" min="0" name="review_frequency" value="0" class="form-control">
                                        <span class="input-group-text">(<?= $escaper->escapeHtml($lang['days']); ?>) </span>
                                    </div>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['NextReviewDate']); ?></label>
                                    <input type="text" class="form-control datepicker" name="next_review_date">
                                </div>
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ApprovalDate']); ?></label>
                                    <input type="text" class="form-control datepicker" name="approval_date">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-paperclip" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['Attachment']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qstack">
                                <div class="sr-qfield sr-qfield--full">
                                    <div class="file-uploader">
                                        <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['File']); ?><span class="required">*</span></label>
                                        <div class="input-group">
                                            <input required type="text" class="form-control readonly" style="width: 50%; margin-bottom: 0px; cursor: default;"/>
                                            <label for="file-upload" class="btn btn-submit m-r-10"><?= $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                                            <label class="align-self-center">Max <?= $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</label>
                                        </div>
                                        <input type="file" id="file-upload" name="file[]" class="d-none" />
                                        <label id="file-size" for="" class="d-none"></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" form="add-document-form" class="btn btn-submit" id="add_document"><?= $escaper->escapeHtml($lang['Add']); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR UPDATING DOCUMENT (design-system.md §8, Form-in-modal type) -->
<div id="document-update-modal" class="modal fade sr-modal" tabindex="-1" role="dialog" aria-labelledby="document-update-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-pen" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="document-update-modal-title"><?= $escaper->escapeHtml($lang['EditDocument']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cancel']); ?>"></button>
            </div>
            <div class="modal-body">
                <form id="update-document-form" class="sr-qcard-form" action="#" method="post" autocomplete="off" enctype="multipart/form-data">
                    <input type="hidden" name="document_id" value="">
                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-file-lines" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['DocumentDetails']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['DocumentType']); ?><span class="required">*</span></label>
                                    <!-- Dynamic category list -- see the Add modal's twin above. -->
                                    <select required="" class="document_type form-select" name="document_type">
                                        <?= document_type_options_html(); /* @phan-suppress-current-line SecurityCheck-XSS -- escaped inside document_type_options_html() */ ?>
                                    </select>
                                    <span class="sr-qhint"><?= $escaper->escapeHtml($lang['NeedANewCategoryManageDocumentTypes']); ?> <a href="<?= $escaper->escapeHtmlAttr(build_url('admin/customization.php?fgroup=document')); ?>" target="_blank"><?= $escaper->escapeHtml($lang['ManageDocumentTypes']); ?> &rarr;</a></span>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['DocumentName']); ?><span class="required">*</span></label>
                                    <input required="" type="text" name="document_name" id="document_name" value="" class="form-control" />
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ParentDocument']); ?></label>
                                    <div class="parent_documents_container">
                                        <select class="form-select">
                                            <option>--</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['DocumentStatus']); ?></label>
    <?php
                                    create_dropdown("document_status", NULL, "status", false, false, false);
    ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-diagram-project" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['WhereItApplies']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Frameworks']); ?></label>
    <?php
                                    create_multiple_dropdown("frameworks", NULL, "framework_ids");
    ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Controls']); ?></label>
                                    <select multiple="multiple" id="update_document_control_ids" name="control_ids[]" class="form-select sr-picker-value"></select>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-users" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['WhoIsInvolved']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['DocumentOwner']); ?></label>
    <?php
                                    create_dropdown("enabled_users", NULL, "document_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0);
    ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
    <?php
                                    create_multiusers_dropdown("additional_stakeholders");
    ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Team']); ?></label>
    <?php
                                    create_multiple_dropdown("team", NULL, "team_ids");
    ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Approver']); ?></label>
    <?php
                                    create_dropdown("enabled_users", NULL, "approver", true, false, false, "", $escaper->escapeHtml($lang['Unassigned']),0);
    ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-calendar-check" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['ReviewCadence']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['CreationDate']); ?></label>
                                    <input type="text" class="form-control datepicker" name="creation_date">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['LastReview']); ?></label>
                                    <input type="text" class="form-control datepicker" name="last_review_date">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ReviewFrequency']); ?></label>
                                    <div class="input-group">
                                        <input type="number" min="0" name="review_frequency" value="0" class="form-control">
                                        <span class="input-group-text">(<?= $escaper->escapeHtml($lang['days']); ?>) </span>
                                    </div>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['NextReviewDate']); ?></label>
                                    <input type="text" class="form-control datepicker" name="next_review_date">
                                </div>
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ApprovalDate']); ?></label>
                                    <input type="text" class="form-control datepicker" name="approval_date">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-paperclip" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['Attachment']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qstack">
                                <div class="sr-qfield sr-qfield--full">
                                    <div class="file-uploader">
                                        <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['File']); ?><span class="required">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control readonly" style="width: 50%; margin-bottom: 0px; cursor: default;"/>
                                            <label for="file-upload-update" class="btn btn-submit m-r-10"><?= $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                                            <label class="align-self-center" size="2">Max <?= $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</label>
                                        </div>
                                        <input type="file" id="file-upload-update" name="file[]" class="d-none" />
                                        <label id="file-size" for="" class="d-none"></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-submit" id="update_document"><?= $escaper->escapeHtml($lang['Update']); ?></button>
            </div>
        </div>
    </div>
</div>
    
<!-- MODEL WINDOW FOR DOCUMENT DELETE CONFIRM -->
<div class="modal hide" id="document-delete-modal" tabindex="-1" aria-labelledby="document-delete-modal" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <form id="delete-document-form" action="" method="post">
                    <div class="form-group text-center">
                        <label for=""><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisDocument']); ?></label>
                        <input type="hidden" class="document_id" name="document_id" value="" />
                        <input type="hidden" class="version" name="version" value="" />
                        <input type="hidden" class="document_type" name="document_type" value="" />
                    </div>
                    <div class="form-group text-center control-delete-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="delete_document" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    function displayFileSize(label, size) {

        if (<?= $escaper->escapeHtml(get_setting('max_upload_size')); ?> > size) {

            label.attr("class","text-success");

        } else {

            label.attr("class","text-danger");

        }

        var iSize = (size / 1024);

        if (iSize / 1024 > 1) {

            if (((iSize / 1024) / 1024) > 1) {

                iSize = (Math.round(((iSize / 1024) / 1024) * 100) / 100);
                label.html("<?= $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Gb");

            } else {
                iSize = (Math.round((iSize / 1024) * 100) / 100)
                label.html("<?= $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Mb");

            }

        } else {

            iSize = (Math.round(iSize * 100) / 100)
            label.html("<?= $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize  + "kb");

        }
    }
    
    // Sets controls picker options by framework ids. $controls is looked up by
    // NAME, scoped to the modal $frameworks lives in -- both modals' control_ids[]
    // selects share the "control_ids" id pattern the rest of this page's dropdown
    // helpers already produce for framework_ids[]/team_ids[]/additional_stakeholders[]
    // (create_multiple_dropdown()/create_multiusers_dropdown() default their id to
    // the field name), so an id-based lookup would always resolve to the Add
    // modal's copy even when called from the Edit modal.
    // Full cross-framework control roster (design-system.md §14b), fetched
    // once and reused by every open of #document-control-picker AND by
    // openDocumentForEdit()'s chip render -- replaces the old
    // sets_controls_by_framework_ids(), which re-fetched a framework-scoped
    // slice every time framework_ids[] changed. control_ids[] no longer
    // depends on framework_ids[] at all (see the picker markup comment
    // above). documentControlsRosterPromise lets a caller that needs a
    // control's NAME (openDocumentForEdit(), below) wait for an in-flight
    // fetch instead of racing it.
    var documentControlsRoster = [];
    var documentControlsById = {};
    var documentControlsRosterPromise = null;
    function loadDocumentControlsRoster() {
        documentControlsRosterPromise = $.ajax({
            url: BASE_URL + '/api/v2/governance/controls/roster',
            type: 'GET',
        }).done(function (res) {
            documentControlsRoster = (res && res.data) ? res.data : [];
            documentControlsById = {};
            documentControlsRoster.forEach(function (c) {
                documentControlsById[String(c.id)] = c;
            });
        });
        return documentControlsRosterPromise;
    }

    /**
     * Builds control_ids[]'s <option>s directly from the roster for the
     * given ids (a document's existing control_ids on edit, or [] to clear)
     * -- replaces sets_controls_by_framework_ids()'s AJAX-per-change
     * roundtrip now that the roster is fetched once, up front. Built via
     * $('<option>', {text: ...}) rather than string concatenation --
     * the roster endpoint returns short_name RAW, unescaped (same
     * convention as compliance.js's own controlsRoster), so this is the
     * one place responsible for escaping it before it reaches the DOM.
     */
    function populateControlOptionsFromIds($select, ids) {
        var idStrings = (ids || []).map(String);
        $select.empty();
        idStrings.forEach(function (id) {
            var control = documentControlsById[id];
            if (control) {
                $select.append($('<option>', { value: control.id, text: control.short_name, selected: true }));
            }
        });
        renderControlChips($select);
    }

    // renderMultiselectChips() -- shared with governance/document_exceptions.php,
    // lives in js/simplerisk/common.js (both pages already load it).

    /**
     * Renders control_ids[]'s current selection as removable chips inside a
     * .sr-chips-field, with a trailing "Add or remove controls…" button that
     * opens #document-control-picker -- the same shape as
     * js/simplerisk/pages/compliance.js's renderControlChips()/
     * applyControlSelection() for its own (much larger) control roster field,
     * reimplemented locally since this page doesn't load compliance.js. Safe to
     * call repeatedly (rebuilds the field's contents from the <select> each time).
     */
    function renderControlChips($select) {
        if (!$select.length) {
            return;
        }

        var $field = $select.next('.sr-chips-field');
        if (!$field.length) {
            $field = $('<div>', { 'class': 'sr-chips-field' }).insertAfter($select);
        }
        $field.empty();

        $select.find('option:selected').each(function() {
            var $option = $(this);
            // text:, never html: -- control/framework names are user-authored
            // (design-system.md §14b's enableHTML warning applies here too).
            var $chip = $('<span>', { 'class': 'sr-chip', text: $option.text() });
            $('<button>', {
                type: 'button',
                'class': 'sr-chip-remove',
                'data-control-id': $option.val(),
                'aria-label': "<?= $escaper->escapeHtml($lang['Remove']); ?>",
                html: '&times;',
            }).appendTo($chip);
            $field.append($chip);
        });

        $('<button>', {
            type: 'button',
            'class': 'sr-chips-add',
            'data-control-picker-for': $select.attr('id'),
            text: "<?= $escaper->escapeHtml($lang['AddOrRemoveControls']); ?>",
        }).appendTo($field);
    }

    // Removing a chip is a direct edit of the field, not of the picker's working
    // copy -- it commits immediately, the way removing a framework_ids[]/team_ids[]/
    // additional_stakeholders[] chip already does via bootstrap-multiselect's own
    // deselect.
    $(document).on('click', '.sr-chips-field .sr-chip-remove[data-control-id]', function() {
        var $select = $(this).closest('.sr-chips-field').prev('select');
        var removedId = String($(this).attr('data-control-id'));
        $select.find('option').filter(function() {
            return String(this.value) === removedId;
        }).prop('selected', false);
        renderControlChips($select);
    });

    $(document).on('click', '.sr-chips-add[data-control-picker-for]', function() {
        var $select = $('#' + $(this).attr('data-control-picker-for'));
        if ($select.length) {
            openDocumentControlPicker($select);
        }
    });

    // The picker instance for #document-control-picker. Created lazily on
    // first open, same as js/simplerisk/pages/compliance.js's own
    // getControlPicker() -- the modal only exists on pages that render this
    // form, and creating it eagerly would bind handlers to nothing everywhere
    // else.
    var documentControlPicker = null;

    /**
     * Lazily builds #document-control-picker's shared-engine instance
     * (createFacetedPicker(), js/simplerisk/sr-faceted-picker.js -- the same
     * search/select/commit/backdrop-stacking engine, and now the same
     * Framework + Control Family facets, as js/simplerisk/pages/
     * compliance.js's getControlPicker(), the reference implementation).
     * control_ids[] stays genuinely multi-value (a document can map to many
     * controls) -- singleSelect is left falsy, same as Document Program's
     * control_ids[] always was.
     */
    function getDocumentControlPicker() {
        if (!documentControlPicker) {
            documentControlPicker = createFacetedPicker({
                modalId: 'document-control-picker',
                searchId: 'document-control-picker-search',
                listId: 'document-control-picker-list',
                selectedId: 'document-control-picker-selected',
                countId: 'document-control-picker-count',
                selectedCountId: 'document-control-picker-selected-count',
                scopeId: 'document-control-picker-scope',
                commitId: 'document-control-picker-commit',
                facets: [
                    {
                        key: 'framework',
                        container: 'document-control-picker-frameworks',
                        // A control maps into SEVERAL frameworks (framework_control_mappings),
                        // so this is a membership test, not a single value.
                        itemValues: function (c) { return (c.frameworks || []).map(Number); },
                    },
                    {
                        key: 'family',
                        container: 'document-control-picker-families',
                        itemValues: function (c) { return [Number(c.family || 0)]; },
                    },
                ],
                itemId: function(c) { return c.id; },
                itemNumber: function(c) { return c.control_number || ''; },
                itemName: function(c) { return c.short_name || ''; },
                itemHover: function(c) { return c.description ? (c.short_name + '\n\n' + c.description) : c.short_name; },
                searchText: function(c) { return (c.control_number || '') + ' ' + (c.short_name || ''); },
                emptyText: "<?= $escaper->escapeHtml($lang['NoControlsMatchFilters']); ?>",
                nothingSelectedText: "<?= $escaper->escapeHtml($lang['NoControlsSelectedYet']); ?>",
                allScopeText: "<?= $escaper->escapeHtml($lang['AllControls']); ?>",
                removeLabel: "<?= $escaper->escapeHtml($lang['Remove']); ?>",
            });
        }
        return documentControlPicker;
    }

    /**
     * Opens #document-control-picker for the given control_ids[] <select>,
     * searching the full cross-framework roster (documentControlsRoster)
     * rather than whatever a framework-scoped <select> happened to already
     * hold. onCommit rebuilds the <select>'s <option>s from the picker's
     * chosen ids via populateControlOptionsFromIds() -- the roster is
     * already loaded by the time this dialog can be open, so no wait needed
     * here (unlike openDocumentForEdit()'s earlier lookup).
     */
    function openDocumentControlPicker($select) {
        getDocumentControlPicker().open({
            items: documentControlsRoster,
            chosen: ($select.val() || []).map(String),
            onCommit: function(ids) {
                // $select.val(ids) alone only works when every id already
                // has an <option> in the DOM -- true when the roster was
                // pre-populated framework-scoped, no longer true now that
                // #control_ids[] starts empty and only ever holds the
                // currently-chosen controls' <option>s.
                populateControlOptionsFromIds($select, ids);
            },
        });
    }

    $(document).ready(function() {
        DocumentProgramGrid.init();
        loadDocumentControlsRoster();

        // Document Program audit trail (design-system.md §6/§7):
        // js/simplerisk/pages/governance-document-audit-trail.js. Only
        // present when the view_document_audit_logs/admin gate in
        // governance/documentation.php rendered the card.
        if ($('#document-audit-trail').length && window.DocumentAuditTrail) {
            window.DocumentAuditTrail.init();
        }

        $("body").on("click", "#document-add-btn", function() {
            // reset the form
            $("#document-program--add form").trigger('reset');
            // re-draw the multiselects as they ARE reset, but their texts still display the previous selections
            $('#document-program--add form span.multiselect-native-select select[multiple]').multiselect('updateButtonText');
            $('#document-program--add form span.multiselect-native-select select[multiple]').multiselect('deselectAll', false);
            // deselectAll() fires no change event, so the chips need their own
            // explicit re-render (design-system.md §14b).
            renderMultiselectChips($('#document-program--add form span.multiselect-native-select select[multiple]'));
            // control_ids[] isn't a bootstrap-multiselect (see the picker markup/
            // comment above the modals) -- its stale options (from a previous Add
            // session's framework pick) don't get cleared by the reset()/
            // deselectAll() calls above, so drop them explicitly.
            $('#document-program--add [name="control_ids[]"]').empty();
            renderControlChips($('#document-program--add [name="control_ids[]"]'));
            // remove the options from the parent selector dropdown
            $('div.parent_documents_container select').find('option').remove().end().append('<option value="0">--</option>');

            $('#document-program--add .file-uploader input').val('');
            $('#document-program--add #file-size').html('');
            // show the modal window
            $("#document-program--add").modal("show");
        });

        //Have to remove the 'fade' class for the shown event to work for modals
        $('#document-program--add, #document-update-modal').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
        });

        // Build multiselect. control_ids[] is excluded here -- it's the
        // #document-control-picker field (design-system.md §14b sizing rule), not
        // a bootstrap-multiselect; see the picker markup/comment above the modals.
        // framework_ids[]/team_ids[]/additional_stakeholders[] stay bootstrap-
        // multiselect (tens-of-rows rosters), styled per §14b's chip pattern via
        // renderMultiselectChips() -- onChange (not the native 'change' event,
        // which this plugin never fires on a pick) keeps the chips in step.
        // framework_ids[] no longer scopes control_ids[] -- the control
        // picker facets by framework independently now (see the picker
        // markup comment above), so this no longer needs an onDropdownHide
        // handler to re-fetch a framework-scoped control roster.
        $("[name='framework_ids[]'], [name='team_ids[]']").multiselect({
            enableFiltering: true,
            enableCaseInsensitiveFiltering: true,
            buttonWidth: '100%',
            maxHeight: 150,
//                dropUp: true,
            onChange: function(option) {
                renderMultiselectChips($(option).closest('select'));
            },
        });


        $(".datepicker").initAsDatePicker();

        $("[name='framework_ids[]'], [name='additional_stakeholders[]']").multiselect({
            buttonWidth: '100%',
            onChange: function(option) {
                renderMultiselectChips($(option).closest('select'));
            },
        });

        // Initial chip render for every roster field -- so the "Add or remove…"
        // triggers are visible even before the user has picked anything.
        renderMultiselectChips($("[name='framework_ids[]'], [name='team_ids[]'], [name='additional_stakeholders[]']"));
        renderControlChips($('#add_document_control_ids'));
        renderControlChips($('#update_document_control_ids'));

        $("#document-program--add .document_type").change(function() {
            $parent = $(this).parents(".modal");
            $.ajax({
                url: BASE_URL + '/api/v2/governance/parent_documents_dropdown?type=' + encodeURI($(this).val()),
                type: 'GET',
                success : function (res) {
                    $(".parent_documents_container", $parent).html(res.data.html)
                }
            });
        });

        $("#document-update-modal .document_type").change(function() {
            $parent = $(this).parents(".modal");
            var document_id = $("[name=document_id]", $parent).val();
            $.ajax({
                url: BASE_URL + '/api/v2/governance/selected_parent_documents_dropdown?type=' + encodeURI($(this).val()) + "&child_id=" + document_id,
                type: 'GET',
                success : function (res) {
                    $(".parent_documents_container", $parent).html(res.data.html)
                }
            });
        });

        // Fetch a document's definition and open the "update document" modal populated
        // with it. Shared by the edit-document click handler and the ?document_id=
        // deep-link (e.g. the governance dashboard's Policies for Review list). The
        // endpoint enforces governance permission on its own.
        function openDocumentForEdit(document_id) {
            // control_ids[] isn't a bootstrap-multiselect -- clear its stale
            // options/chips directly; populateControlOptionsFromIds() (below)
            // rebuilds both from the fetched document's own data anyway.
            $("#document-update-modal [name='control_ids[]']").empty();
            renderControlChips($("#document-update-modal [name='control_ids[]']"));
            $("#document-update-modal [name='framework_ids[]']").multiselect("deselectAll", false);
            $("#document-update-modal [name='additional_stakeholders[]']").multiselect("deselectAll", false);
            $("#document-update-modal [name='team_ids[]']").multiselect("deselectAll", false);
            // deselectAll(), like select(), fires no change event.
            renderMultiselectChips($("#document-update-modal [name='framework_ids[]']"));
            renderMultiselectChips($("#document-update-modal [name='additional_stakeholders[]']"));
            renderMultiselectChips($("#document-update-modal [name='team_ids[]']"));

            $('#document-update-modal .file-uploader input').val('');
            $('#document-update-modal #file-size').html('');

            $.ajax({
                url: BASE_URL + '/api/v2/governance/document?id=' + document_id,
                type: 'GET',
                success : function (res) {
                    var data = res.data;
                    $.ajax({
                        url: BASE_URL + '/api/v2/governance/selected_parent_documents_dropdown?type=' + encodeURI(data.document_type) + '&child_id=' + document_id,
                        type: 'GET',
                        success : function (res){
                            $("#document-update-modal .parent_documents_container").html(res.data.html)
                        }
                    });
                    $("#document-update-modal [name=document_id]").val(data.id);
                    $("#document-update-modal [name=document_type]").val(data.document_type);
                    $("#document-update-modal [name=document_name]").val(data.document_name);
                    // Programmatic .multiselect('select', …) fires no change event
                    // (design-system.md §14b), so each of these three needs its own
                    // explicit chip re-render -- left to onChange, an edited
                    // document would open showing none of its own framework/team/
                    // stakeholder values.
                    $("#document-update-modal [name='framework_ids[]']").multiselect('select', data.framework_ids);
                    renderMultiselectChips($("#document-update-modal [name='framework_ids[]']"));
                    // Wait for the roster if it's still in flight ($.when()
                    // on an already-resolved deferred resolves immediately,
                    // so this is a no-op wait once the roster has loaded).
                    $.when(documentControlsRosterPromise).done(function () {
                        populateControlOptionsFromIds($("#document-update-modal [name='control_ids[]']"), data.control_ids);
                    });
                    $("#document-update-modal [name=creation_date]").val(data.creation_date);
                    $("#document-update-modal [name=last_review_date]").val(data.last_review_date);
                    $("#document-update-modal [name=review_frequency]").val(data.review_frequency);
                    $("#document-update-modal [name=next_review_date]").val(data.next_review_date);
                    $("#document-update-modal [name=approval_date]").val(data.approval_date);
                    $("#document-update-modal [name=status]").val(data.status);
                    $("#document-update-modal [name=document_owner]").val(data.document_owner);
                    $("#document-update-modal [name='additional_stakeholders[]']").multiselect('select', data.additional_stakeholders);
                    renderMultiselectChips($("#document-update-modal [name='additional_stakeholders[]']"));
                    $("#document-update-modal [name=approver]").val(data.approver);
                    $("#document-update-modal [name='team_ids[]']").multiselect('select', data.team_ids);
                    renderMultiselectChips($("#document-update-modal [name='team_ids[]']"));
                    if (data.file_name) {
                        $('#document-update-modal .file-uploader input.readonly').val(data.file_name);
                        displayFileSize($("#document-update-modal #file-size"), data.file_size);
                    }
                    $("#document-update-modal").modal("show");
                }
            });
        }

        $("body").on("click", ".document--edit", function() {
            openDocumentForEdit($(this).data("id"));
        });

        // Deep-link: open a specific document's edit modal on load — e.g. the
        // governance dashboard's Policies for Review list
        // (governance/documentation.php?document_id=N). No-op when the modal is
        // absent or the param is missing/non-numeric; the endpoint enforces access.
        $(function() {
            if (!$('#document-update-modal').length) return;
            var deepDocumentId = new URLSearchParams(window.location.search).get('document_id');
            if (!(deepDocumentId && /^\d+$/.test(deepDocumentId))) return;
            openDocumentForEdit(deepDocumentId);
        });

        var fileAPISupported = typeof $("<input type='file'>").get(0).files != "undefined";

        if (fileAPISupported) {
            $("input.readonly").on('keydown paste focus', function(e){
                e.preventDefault();
                e.currentTarget.blur();
            });

            $("#add-document-form input.readonly").click(function(){
                $("#file-upload").trigger("click");
            });

            $("#update-document-form input.readonly").click(function(){
                $("#file-upload-update").trigger("click");
            });

            $('#file-upload').change(function(e){
                if (!e.target.files[0]) {
                    return;
                }

                var fileName = e.target.files[0].name;
                $("#add-document-form input.readonly").val(fileName);

                displayFileSize($("#add-document-form #file-size"), e.target.files[0].size);

            });

            $('#file-upload-update').change(function(e){
                if (!e.target.files[0]) {
                    return;
                }

                var fileName = e.target.files[0].name;
                $("#update-document-form input.readonly").val(fileName);

                displayFileSize($("#update-document-form #file-size"), e.target.files[0].size);

            });

            $("#add-document-form").on('submit', function(event) { 
                event.preventDefault();
                if ($('#file-upload')[0].files[0] && <?= $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload')[0].files[0].size) {
                    showAlertFromMessage("<?= $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                    return false;
                }

                // Show spinner before API call
                $.blockUI({
                    message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>',
                    baseZ: 1100,
                });

                // A <select multiple name="control_ids[]"> serializes into FormData as one
                // control_ids[] entry per selected option, which can exceed PHP's
                // max_input_vars ceiling (default 1000) once a framework contributes
                // hundreds of controls -- the POST body is silently truncated past that
                // point, dropping trailing fields with no error. Collapse the selection to
                // a single comma-separated field instead, so the POST field count no
                // longer scales with the number of selected controls.
                var addFormData = new FormData($('#add-document-form')[0]);
                addFormData.delete('control_ids[]');
                addFormData.set('control_ids', ($("#add-document-form [name='control_ids[]']").val() || []).join(','));

                $.ajax({
                    type: "POST",
                    url: BASE_URL + "/api/v2/documents/create",
                    data: addFormData,
                    async: true,
                    cache: false,
                    contentType: false,
                    processData: false,
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }

                        $('#document-program--add').modal('hide');
                        $('#add-document-form')[0].reset();
                        $('#add-document-form #file-size').text("");
                        $("#add-document-form [name='framework_ids[]']").multiselect('select', []);
                        renderMultiselectChips($("#add-document-form [name='framework_ids[]']"));
                        // control_ids[] isn't a bootstrap-multiselect -- clear its
                        // options/chips directly.
                        $("#add-document-form [name='control_ids[]']").empty();
                        renderControlChips($("#add-document-form [name='control_ids[]']"));
                        $("#add-document-form [name='additional_stakeholders[]']").multiselect('select', []);
                        renderMultiselectChips($("#add-document-form [name='additional_stakeholders[]']"));
                        $("#add-document-form [name='document_owner[]']").multiselect('select', []);
                        $("#add-document-form [name='team_ids[]']").multiselect('select', []);
                        renderMultiselectChips($("#add-document-form [name='team_ids[]']"));

                        // Hide spinner after API call
                        $.unblockUI();

                        // Reload the ONE grid, whichever type it's currently
                        // showing -- the mutated document surfaces immediately
                        // whether the active tab IS its type or is the
                        // all-types Document Hierarchy view.
                        DocumentProgramGrid.reload();
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                        
                        // Hide spinner after API call
                        $.unblockUI();

                    }
                });
                return false;
            });

            $("#update_document").click(function(event) {
                event.preventDefault();
                if ($('#file-upload-update')[0].files[0] && <?= $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload-update')[0].files[0].size) {
                    showAlertFromMessage("<?= $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                    return false;
                }

                // Show spinner before API call
                $.blockUI({
                    message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>',
                    baseZ: 1100,
                });

                // See the matching comment on the add-document submit handler above:
                // collapse the multi-select's array serialization to a single
                // comma-separated field so the POST field count doesn't scale with the
                // number of selected controls.
                var updateFormData = new FormData($('#update-document-form')[0]);
                updateFormData.delete('control_ids[]');
                updateFormData.set('control_ids', ($("#update-document-form [name='control_ids[]']").val() || []).join(','));

                $.ajax({
                    type: "POST",
                    url: BASE_URL + "/api/v2/documents/update",
                    data: updateFormData,
                    async: true,
                    cache: false,
                    contentType: false,
                    processData: false,
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }

                        $('#document-update-modal').modal('hide');
                        $('#update-document-form')[0].reset();
                        $('#update-document-form #file-size').text("");
                        $("#update-document-form [name='framework_ids[]']").multiselect('select', []);
                        renderMultiselectChips($("#update-document-form [name='framework_ids[]']"));
                        // control_ids[] isn't a bootstrap-multiselect -- clear its
                        // options/chips directly.
                        $("#update-document-form [name='control_ids[]']").empty();
                        renderControlChips($("#update-document-form [name='control_ids[]']"));
                        $("#update-document-form [name='additional_stakeholders[]']").multiselect('select', []);
                        renderMultiselectChips($("#update-document-form [name='additional_stakeholders[]']"));
                        $("#update-document-form [name='document_owner[]']").multiselect('select', []);
                        $("#update-document-form [name='team_ids[]']").multiselect('select', []);
                        renderMultiselectChips($("#update-document-form [name='team_ids[]']"));

                        // Hide spinner after API call
                        $.unblockUI();

                        // Reload the ONE grid, whichever type it's currently
                        // showing -- the mutated document surfaces immediately
                        // whether the active tab IS its type or is the
                        // all-types Document Hierarchy view.
                        DocumentProgramGrid.reload();
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }

                        // Hide spinner after API call
                        $.unblockUI();
                        
                    }
                });
                return false;
            });

            // variable which is used to prevent multiple form submissions
            var loading = false;
            $("#delete-document-form").submit(function(event) {
                event.preventDefault();

                // prevent multiple form submissions
                if (loading) {
                    return;
                }

                loading = true;
                $.ajax({
                    type: "POST",
                    url: BASE_URL + "/api/v2/documents/delete",
                    data: new FormData($('#delete-document-form')[0]),
                    async: true,
                    cache: false,
                    contentType: false,
                    processData: false,
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }

                        $('#document-delete-modal').modal('hide');

                        // set loading to false to allow form submission
                        loading = false;

                        // Reload the ONE grid, whichever type it's currently
                        // showing -- the mutated document surfaces immediately
                        // whether the active tab IS its type or is the
                        // all-types Document Hierarchy view.
                        DocumentProgramGrid.reload();
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }

                        // set loading to false to allow form submission
                        loading = false;
                    }
                });
                return false;
            });
        } else { // If File API is not supported
            $("input.readonly").remove();
            $('#file-upload').prop('required',true);
        }

        $("body").on("change keyup", "input[name=review_frequency], input[name=last_review_date]", function(){
            var form = $(this).closest("form");
            var last_review_date = $(form).find("input[name=last_review_date]").val();
            var review_frequency = $(form).find("input[name=review_frequency]").val();
            if(last_review_date != "" && review_frequency != ""){
                var next_review_date = new Date(last_review_date);
                next_review_date.setDate(next_review_date.getDate() + parseInt(review_frequency));
                var next_review_date_str = $.datepicker.formatDate('<?= /* @phan-suppress-current-line SecurityCheck-XSS -- get_default_date_format_for_datepicker() returns admin-configured date format string with no user input */ get_default_date_format_for_datepicker() ?>', next_review_date);
                $(form).find("input[name=next_review_date]").val(next_review_date_str);
            }
            return true;
        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>