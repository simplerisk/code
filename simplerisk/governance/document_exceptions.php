<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
// Section-landing page (Governance > Define Exceptions): the submenu IS the
// current page, so breadcrumb_title_key equals active_sidebar_submenu --
// sidebar.php's own convention for not duplicating the leaf crumb. Matches
// compliance/index.php's identical pattern. Note the internal key is
// "DocumentExceptions" (matching sidebar.php's own submenu match value and
// $lang key), even though the page's own title reads "Define Exceptions".
$breadcrumb_title_key = "DocumentExceptions";
$active_sidebar_menu = "Governance";
$active_sidebar_submenu = "DocumentExceptions";
render_header_and_sidebar(['WYSIWYG', 'multiselect', 'datetimerangepicker', 'datatables', 'CUSTOM:common.js', 'CUSTOM:sr-faceted-picker.js', 'CUSTOM:sr-row-actions-menu.js', 'CUSTOM:sr-select.js', 'CUSTOM:sr-audit-trail.js', 'CUSTOM:pages/governance-exceptions.js', 'CUSTOM:pages/governance-exception-audit-trail.js',
    // The insights band below is a UILayout instance -- 'UILayoutWidget'
    // pulls in both the includes/Widgets/UILayout.php CLASS and the
    // Gridstack JS/CSS the band's tile rendering depends on. Same asset
    // governance/documentation.php's own insights band already declares.
    'UILayoutWidget'], ['check_governance' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../includes/governance.php'));

enforce_permission_exception('view');

// Row-action + bulk-select gates for the redesigned .sr-table-card grid
// below (design-system.md §6), same session-key convention documentation.php
// (Task 9) already uses for its own grid.
$can_add = !empty($_SESSION['create_exception']);
$can_edit = !empty($_SESSION['update_exception']);
$can_delete = !empty($_SESSION['delete_exception']);
$can_approve = !empty($_SESSION['approve_exception']);

if(isset($_POST['download_audit_log']))
{
    if(is_admin())
    {
        // If extra is activated, download audit logs
        if (import_export_extra())
        {
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
            download_audit_logs(get_param('post', 'days', 7), 'exception', $escaper->escapeHtml($lang['ExeptionAuditTrailReport']));
        }else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['YouCantDownloadBecauseImportExportExtraDisabled']));
            refresh();
        }
    }
    // If this is not admin user, disable download
    else
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['AdminPermissionRequired']));
        refresh();
    }
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
// @phan-suppress-next-line PhanRedefineFunction -- each page defines its own display() entry point
function display($display = "")
{
    global $lang;
    global $escaper;

    // If import/export extra is enabled and admin user, shows export audit log button
    if (import_export_extra() && is_admin())
    {
        // Include the Import-Export Extra
        require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

        display_audit_download_btn();
    }
}

?>
<link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">
<?php
   // Only id + subject are ever rendered below (the "Associated Risks"
   // picker's <option> labels) -- get_risks_subject_list() avoids decrypting
   // assessment/notes that this page never displays.
   $risks = get_risks_subject_list();
   require_once(realpath(__DIR__ . '/../includes/settings_catalog.php'));
   // Define Exceptions insights band -- same "Direction A" shape as
   // Document Program's band (design-system.md): two attention tiles
   // (Overdue for Review, Due Soon) then the grid's own three partitions
   // (Policy/Control/Pending Approval). Collapsible for the same reason
   // documentation.php's band is -- it introduces the grid below it rather
   // than being the content. Edit-layout shows the real control when the
   // Customization Extra is active, or the shared locked teaser otherwise
   // (customization_acquisition_state(), includes/settings_catalog.php).
   (new \includes\Widgets\UILayout('define_exceptions_insights', [
       'show_edit_layout' => true,
       'edit_layout_locked_state' => customization_acquisition_state(is_admin(), get_setting('registration_registered') == 1),
       'collapsible' => true,
   ]))->render();
?>
<!-- No .row/.col-12 wrapper: .sr-table-card renders as a DIRECT child of
     .content, matching every other direct-child-card surface (Manage/
     Initiate Audits, .sr-qform, .sr-aihub, .sr-connectivity-explorer,
     .sr-soa--selector) -- see scss/modules/_tables.scss's
     `.content:has(> .sr-table-card)` rule, which zeroes .content's own
     10px margin so the card's left edge lands flush with the page title/
     breadcrumb above it instead of re-inset ~10px by col-12's uncancelled
     padding. -->
<!-- Define Exceptions grid (design-system.md §6): a single client-
     rendered .sr-table-card replacing the old per-tab (Policy/Control/
             Unapproved) EasyUI treegrid trio. ExceptionsGrid
             (js/simplerisk/pages/governance-exceptions.js) fetches all three of
             GET /api/v2/exceptions/tree?type=policy|control|unapproved,
             merges them into one flat row set (the three types partition the
             full exception set with no overlap), and renders it client-side --
             no server-printed <table> rows. Lifecycle (Open/Closed,
             document_exceptions.status) and approval (Pending/Approved,
             .approved) are shown as two SEPARATE .sr-state-pill columns since
             they are genuinely different DB fields. The Columns picker
             (.sr-table-tools .colpicker/.colpanel, design-system.md §6c) lets
             a viewer hide the Owner/Framework-Control/Associated-Risks/
             Next-Review columns -- new to the design system as of this page;
             see design-system.md §6c for the pattern spec. -->
        <div class="sr-table-card" id="exceptions-grid"
            data-can-edit="<?= $can_edit ? 'true' : 'false'; ?>"
            data-can-delete="<?= $can_delete ? 'true' : 'false'; ?>"
            data-can-approve="<?= $can_approve ? 'true' : 'false'; ?>">
            <div class="sr-table-toolbar" id="exceptions-toolbar">
                <div class="sr-table-title">
                    <?= $escaper->escapeHtml($lang['DocumentExceptions']); ?>
                    <span class="sr-table-count d-none" id="exceptions-count"></span>
                </div>
                <div class="sr-table-tools">
                    <!-- Columns picker (design-system.md §6c, new pattern -- Task 11
                         reference implementation). .colpicker wraps a .filterbtn
                         trigger (styled like .sr-table-filter) and a .colpanel
                         dropdown of checkboxes; ExceptionsGrid toggles the matching
                         data-col="..." th/td display on change. -->
                    <div class="colpicker">
                        <button type="button" class="filterbtn sr-table-filter" id="exceptions-colpicker-btn" aria-haspopup="true" aria-expanded="false">
                            <i class="fa fa-table-columns" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['Columns']); ?>
                        </button>
                        <div class="colpanel d-none" id="exceptions-colpanel"></div>
                    </div>
        <?php if ($can_add) { ?>
                    <a href="#" role="button" class="btn btn-danger project--add" id="exception-add-btn">+ <?= $escaper->escapeHtml($lang['ExceptionAdd']); ?></a>
        <?php } ?>
                </div>
            </div>
            <!-- Filters row (design-system.md §6b/§6c): Type, Framework,
                 Control, Exception State, Approval Status, Risk. Options are
                 built client-side by ExceptionsGrid from the loaded rows
                 (renderFilterOptions() in governance-exceptions.js) --
                 same "derived from what's actually loaded, not every value
                 in the system" convention the Document Program grid and both
                 audit trails already use, rather than a separate lookup call
                 (get_risks() above is for the Add/Edit modal's own picker,
                 not this filter). Collapses behind the Filters·n toggle
                 below the compact tier (_tables.scss's .sr-qf-toggle rules
                 -- no bespoke CSS needed here).
            -->
            <button type="button" class="sr-qf-toggle" id="exceptions-filters-toggle" aria-expanded="false" aria-controls="exceptions-quickfilters">
                <i class="fa fa-filter" aria-hidden="true"></i>
                <span><?= $escaper->escapeHtml($lang['Filters']); ?></span>
                <span class="sr-qf-toggle-count" id="exceptions-filters-count" hidden></span>
            </button>
            <div class="sr-table-quickfilters" id="exceptions-quickfilters">
                <div class="sr-qf-selects">
                    <select id="exceptions-type-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['Type']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Type']); ?>"></select>
                    <select id="exceptions-framework-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['ControlFrameworks']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['ControlFrameworks']); ?>"></select>
                    <select id="exceptions-control-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['Control']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Control']); ?>"></select>
                    <select id="exceptions-state-filter" class="form-select" title="<?= $escaper->escapeHtmlAttr($lang['ExceptionStatus']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['ExceptionStatus']); ?>"></select>
                    <select id="exceptions-status-filter" class="form-select" title="<?= $escaper->escapeHtmlAttr($lang['ApprovalStatus']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['ApprovalStatus']); ?>"></select>
                    <select id="exceptions-risk-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['AssociatedRisks']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['AssociatedRisks']); ?>"></select>
                    <!-- Only the multi-selects (Type/Framework/Control/Risk) lack a
                         clickable "All X" row of their own -- a multi-select's
                         options are all plain toggles, same reason Document
                         Program's Type/Framework multi-selects don't have one
                         either. This button is the actual clear affordance for
                         the whole row, shown only once a filter is actually
                         narrowing the grid (see syncFilterCount() below). -->
                    <button type="button" class="btn btn-link btn-sm sr-qf-clear d-none" id="exceptions-filters-clear"><?= $escaper->escapeHtml($lang['ClearFilters']); ?></button>
                </div>
            </div>
        <?php if ($can_approve || $can_delete) { ?>
            <div class="sr-bulk-bar d-none" id="exceptions-bulk-bar">
                <button type="button" class="sr-bulk-clear" id="exceptions-bulk-clear" aria-label="<?= $escaper->escapeHtmlAttr($lang['Clear']); ?>">&times;</button>
                <span class="sr-bulk-count" id="exceptions-bulk-count"></span>
                <div class="sr-bulk-actions">
            <?php if ($can_approve) { ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="exceptions-bulk-approve"><?= $escaper->escapeHtml($lang['ApproveSelected']); ?></button>
            <?php } ?>
            <?php if ($can_delete) { ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="exceptions-bulk-delete"><?= $escaper->escapeHtml($lang['DeleteSelected']); ?></button>
            <?php } ?>
                </div>
            </div>
        <?php } ?>
            <div id="exceptions-body"></div>
        </div>
        <!-- AUDIT TRAIL (design-system.md §6/§7): a second .sr-table-card
             below the grid, reusing Document Program's identical shape and
             js/simplerisk/pages/governance-exception-audit-trail.js
             (js/simplerisk/pages/governance-document-audit-trail.js is the
             reference implementation) -- the toggle is a plain disclosure
             row, not Bootstrap's accordion chrome. No separate "view
             exception audit logs" permission exists (unlike Document
             Program's view_document_audit_logs), so this card is gated the
             same way the legacy accordion it replaces was: reachable
             whenever the page itself is (enforce_permission_exception('view')
             above), matching GET /api/v2/exceptions/audit_log's own gate. -->
        <div class="sr-table-card sr-audit-trail-card my-2" id="exception-audit-trail">
            <div class="sr-table-toolbar" id="exception-audit-trail-toolbar">
                <button type="button" class="sr-audit-trail-toggle" id="exception-audit-trail-toggle" aria-expanded="false" aria-controls="exception-audit-trail-collapse">
                    <i class="fa fa-chevron-right sr-audit-trail-caret" aria-hidden="true"></i>
                    <span class="sr-table-title"><?= $escaper->escapeHtml($lang['AuditTrail']); ?> <span class="sr-table-count d-none" id="exception-audit-trail-count"></span></span>
                </button>
                <div class="sr-table-tools">
                    <button type="button" class="sr-table-filter" id="exception-audit-trail-refresh" title="<?= $escaper->escapeHtmlAttr($lang['Refresh']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Refresh']); ?>"><i class="fa fa-sync" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="d-none" id="exception-audit-trail-collapse">
                <div class="sr-table-toolbar" id="exception-audit-trail-inner-toolbar">
                    <div class="sr-table-tools">
        <?php
            // Audit-trail export (Import/Export Extra) -- same shared
            // locked-teaser treatment ("show what's possible, mark what's
            // locked", scss/modules/_locked-affordance.scss) the Document
            // Program audit trail's own Export button uses, via the same
            // generic import_export_acquisition_state() helper the
            // dashboards' Export PDF button now uses too (includes/
            // settings_catalog.php) rather than a third copy of this
            // decision. #exception-audit-trail-export PROXIES a click onto
            // display_audit_download_btn()'s own hidden, unscoped trigger
            // (js/simplerisk/pages/governance-exception-audit-trail.js) --
            // the actual submission logic is untouched.
            if (is_admin()) {
                $exception_audit_export = import_export_acquisition_state(true, get_setting('registration_registered') == 1);

                if (!$exception_audit_export['locked']) {
                    require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
                    display_audit_download_btn();
            ?>
                        <button type="button" class="sr-table-filter" id="exception-audit-trail-export"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['Export']); ?></button>
            <?php
                } else {
                    $exception_audit_export_note = $exception_audit_export['note_key'] !== null ? ($lang[$exception_audit_export['note_key']] ?? '') : '';
                    $exception_audit_export_link_text = $exception_audit_export['link_key'] !== null ? ($lang[$exception_audit_export['link_key']] ?? '') : '';
                    $exception_audit_export_href = $exception_audit_export['unlock_href'];
            ?>
                        <span class="sr-locked" data-sr-locked-state="<?= $escaper->escapeHtmlAttr($exception_audit_export['state']); ?>">
                            <button type="button" class="sr-table-filter sr-locked--btn" disabled aria-disabled="true" title="<?= $escaper->escapeHtmlAttr($exception_audit_export_note); ?>">
                                <i class="fa-solid fa-file-excel" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['Export']); ?>
                                <span class="sr-locked-badge"><i class="fa fa-lock" aria-hidden="true"></i> <?= $escaper->escapeHtml($lang['LockedAffordanceBadge']); ?></span>
                            </button>
                    <?php if ($exception_audit_export_link_text && $exception_audit_export_href) { ?>
                            <a class="sr-locked-link" href="<?= $escaper->escapeHtmlAttr($exception_audit_export_href); ?>"<?= !empty($exception_audit_export['external']) ? ' target="_blank" rel="noopener"' : ''; ?> title="<?= $escaper->escapeHtmlAttr($exception_audit_export_note); ?>"><i class="fa fa-circle-info" aria-hidden="true"></i></a>
                    <?php } ?>
                        </span>
            <?php
                }
            }
        ?>
                    </div>
                </div>
                <div class="sr-table-quickfilters" id="exception-audit-trail-quickfilters">
                    <div class="sr-qf-selects" id="exception-audit-trail-filters">
                        <div class="audit-select-folder">
                            <select name="days" id="exception-audit-trail-range-filter" class="form-select" title="<?= $escaper->escapeHtmlAttr($lang['DateRange']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['DateRange']); ?>">
                                <option value="7" selected><?= $escaper->escapeHtml($lang['PastWeek']); ?></option>
                                <option value="30"><?= $escaper->escapeHtml($lang['PastMonth']); ?></option>
                                <option value="90"><?= $escaper->escapeHtml($lang['PastQuarter']); ?></option>
                                <option value="180"><?= $escaper->escapeHtml($lang['Past6Months']); ?></option>
                                <option value="365"><?= $escaper->escapeHtml($lang['PastYear']); ?></option>
                                <option value="36500"><?= $escaper->escapeHtml($lang['AllTime']); ?></option>
                            </select>
                        </div>
                        <select id="exception-audit-trail-exception-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['Exception']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Exception']); ?>"></select>
                        <select id="exception-audit-trail-activity-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['Activity']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Activity']); ?>"></select>
                        <select id="exception-audit-trail-user-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['User']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['User']); ?>"></select>
                        <button type="button" class="btn btn-link btn-sm sr-qf-clear d-none" id="exception-audit-trail-filters-clear"><?= $escaper->escapeHtml($lang['ClearFilters']); ?></button>
                    </div>
                </div>
                <div id="exception-audit-trail-body"></div>
            </div>
        </div>
<script>

    // Fetch an exception's definition and open the "update exception" modal
    // populated with it. Shared by the row edit icon (ExceptionsGrid renders
    // `.exception--edit` client-side) and the ?exception_id= deep-link (e.g.
    // the governance dashboard's Expiring Exceptions list). Fully API-driven,
    // so it works regardless of what's currently loaded in the grid. The
    // endpoint enforces exception permission on its own. type is optional —
    // when omitted (deep-link) it is derived from the fetched record.
    function openExceptionForEdit(exception_id, type) {
        $("#exception-update-form [name='additional_stakeholders[]']").multiselect('deselectAll', false);
        $("#exception-update-form [name='associated_risks[]']").multiselect('deselectAll', false);
        // deselectAll(), like select(), fires no change event (design-system.md
        // §14b), so the chips need their own explicit re-render.
        renderMultiselectChips($("#exception-update-form [name='additional_stakeholders[]']"));
        renderMultiselectChips($("#exception-update-form [name='associated_risks[]']"));
        $("#exception-update-form .file-uploader input").val("");
        $("#exception-update-form #file-size").text("");

        $.ajax({
            url: BASE_URL + '/api/v2/exceptions/exception?id=' + exception_id,
            type: 'GET',
            success : function (res){
                var data = res.data;

                // Type: use the caller's value, else derive from the record
                // (control-linked → control, otherwise policy).
                var exception_type = type ? type : (data.control_framework_id ? 'control' : 'policy');

                // Unapprove button: shown for an approved exception, hidden otherwise
                // (equivalent to the old approved-tab vs unapproved-tab branch).
                if (data.approved) {
                    $("#exception--update #unapprove_exception").show();
                } else {
                    $("#exception--update #unapprove_exception").hide();
                }

                $("#exception-update-form [name=exception_type]").val(exception_type);

                $("#exception-update-form [name=exception_id]").val(exception_id);
                $("#exception-update-form [name=document_exceptions_status]").val(data.document_exceptions_status);
                $("#exception-update-form [name=name]").val(data.name);
                $("#exception-update-form [name=policy]").val(data.policy_document_id);
                $("#exception-update-form [name=framework]").val(data.framework_id);
                // The control's NAME isn't in this record (it's raw column
                // data, not the resolved shape /exceptions/info returns for
                // the View modal) -- look it up in the roster, waiting on the
                // fetch if it's still in flight ($.when() on an
                // already-resolved deferred resolves immediately, so this is
                // a no-op wait once the roster has loaded).
                var $controlSelect = $("#exception-update-form #control");
                $.when(exceptionControlsRosterPromise).done(function () {
                    var control = data.control_framework_id ? exceptionControlsById[String(data.control_framework_id)] : null;
                    if (control) {
                        $controlSelect.empty().append($('<option>', { value: control.id, text: control.short_name, selected: true }));
                    } else {
                        $controlSelect.empty().append($('<option>', { value: '0', text: '--', selected: true }));
                    }
                    renderExceptionControlChip($controlSelect);
                });
                $("#exception-update-form [name=owner]").val(data.owner);
                $("#exception-update-form [name='additional_stakeholders[]']").multiselect('select', data.additional_stakeholders);

                $("#exception-update-form [name='additional_stakeholders[]']").multiselect('updateButtonText');
                // Programmatic .multiselect('select', …) fires no change event
                // (design-system.md §14b), so each of these needs its own
                // explicit chip re-render -- left to onChange, an edited
                // exception would open showing none of its own stakeholder/
                // risk values.
                renderMultiselectChips($("#exception-update-form [name='additional_stakeholders[]']"));

                $("#exception-update-form [name='associated_risks[]']").multiselect('select', data.associated_risks);
                renderMultiselectChips($("#exception-update-form [name='associated_risks[]']"));
                $("#exception-update-form [name=creation_date]").val(data.creation_date);
                $("#exception-update-form [name=review_frequency]").val(data.review_frequency);
                $("#exception-update-form [name=next_review_date]").val(data.next_review_date);
                $("#exception-update-form [name=approval_date]").val(data.approval_date);
                $("#exception-update-form [name=approver]").val(data.approver);
                $("#exception-update-form [name=description]").val(data.description);
                $("#exception-update-form [name=justification]").val(data.justification);

                // set contents into the WYSIWYG editor dynamically.
                setEditorContent("update_description", data.description);
                setEditorContent("update_justification", data.justification);

                if (data.file_name) {
                    $("#exception-update-form input.readonly").val(data.file_name);
                    displayFileSize($("#exception-update-form #file-size"), data.file_size);
                }

                applyExceptionTypeVisibility($('#exception--update'));

                $("#exception--update").modal('show');
            }
        });
    }

    // Deep-link: open a specific exception's edit modal on load — e.g. the
    // governance dashboard's Expiring Exceptions list
    // (governance/document_exceptions.php?exception_id=N). No-op when the modal
    // is absent or the param is missing/non-numeric; the endpoint enforces
    // access. The modal's WYSIWYG editors initialise asynchronously, so wait
    // until they're live (bounded) before opening so setEditorContent() can run.
    $(function() {
        if (!$('#exception--update').length) return;
        var deepExceptionId = new URLSearchParams(window.location.search).get('exception_id');
        if (!(deepExceptionId && /^\d+$/.test(deepExceptionId))) return;

        var tries = 0;
        (function waitAndOpen() {
            var ready = ['update_description', 'update_justification'].every(function(id) {
                var ed = (typeof hugerte !== 'undefined') && hugerte.get(id);
                try { return !!(ed && ed.getBody && ed.getBody()); } catch (e) { return false; }
            });
            if (ready || tries >= 50) {
                openExceptionForEdit(deepExceptionId);
            } else {
                tries++;
                setTimeout(waitAndOpen, 100);
            }
        })();
    });

    // Row edit icon and the read-only view/info modal -- both delegated onto
    // document since ExceptionsGrid (js/simplerisk/pages/governance-exceptions.js)
    // rebuilds the grid's rows from scratch on every load/reload. Approve/
    // unapprove/delete (single row AND bulk-selected) are wired inside
    // ExceptionsGrid itself, next to the confirm modals they open -- see that
    // file for the .exception--approve/.exception--unapprove/.exception--delete/
    // #exceptions-bulk-approve/#exceptions-bulk-delete handlers.
    $(document).on('click', '.exception--edit', function(e) {
        e.preventDefault();
        openExceptionForEdit($(this).data('id'), $(this).data('type'));
    });

    $(document).on('click', '.exception--view', function(e) {
        e.preventDefault();
        var exception_id = $(this).data('id');
        var type = $(this).data('type');
        // Whether to show the exception's REAL approval_date/approver (already
        // approved) or the app's own account/today's-date preview of what
        // they'll become on approval (get_exception_for_display_api()'s
        // `approval` param) -- driven by the row's own approved state now
        // that this is a read-only view (Task 11 dropped the old "open this
        // same modal in approve mode" flow in favor of dedicated approve/
        // unapprove actions).
        var approved = $(this).data('approved') === true || $(this).data('approved') === 'true';

        $.ajax({
            url: BASE_URL + '/api/v2/exceptions/info',
            data: { id: exception_id, type: type, approval: approved ? 1 : 0 },
            type: 'GET',
            success : function (res){
                var data = res.data;

                $("#exception--view #name").html(data.name);
                $("#exception--view #type").html(data.type_text);
                if (data.type == 'policy') {
                    $("#exception--view #policy").html(data.policy_name);
                    $("#exception--view #policy").parent().show();
                    $("#exception--view #framework").parent().hide();
                    $("#exception--view #control").parent().hide();
                } else {
                    $("#exception--view #framework").html(data.framework_name);
                    $("#exception--view #framework").parent().show();
                    $("#exception--view #control").html(data.control_name);
                    $("#exception--view #control").parent().show();
                    $("#exception--view #policy").parent().hide();
                }

                $("#exception--view #document_exceptions_status").html(data.document_exceptions_status);
                $("#exception--view #owner").html(data.owner);
                $("#exception--view #additional_stakeholders").html(data.additional_stakeholders);
                $("#exception--view #associated_risks").html(data.associated_risks);
                $("#exception--view #creation_date").html(data.creation_date);
                $("#exception--view #review_frequency").html(data.review_frequency);
                $("#exception--view #next_review_date").html(data.next_review_date);
                $("#exception--view #approval_date").html(data.approval_date);
                $("#exception--view #approver").html(data.approver);
                $("#exception--view #description").html(data.description);
                $("#exception--view #justification").html(data.justification);
                $("#exception--view #file_download").html(data.file_download);

                $("#exception--view").modal('show');
            }
        });
    });

    //Refresh audit logs if the log section is not collapsed
    // if it is, mark it for refresh on the next time it's opened
    function refreshAuditLogsIfOpen() {
        if (!$(".accordion-header .accordion-button").hasClass(".collapsed")) {
            refreshAuditLogs();
        } else {
            $(".accordion-header .accordion-button").data('need-refresh', true);
        }
    }

    function refreshAuditLogs() {
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/exceptions/audit_log",
            data: {
                days: $('.audit-trail select.audit-select-days').val()
            },
            async: true,
            cache: false,
            success: function(data){
                var div = $("<div>");
                $.each( data.data, function( key, value ) {
                    div.append($("<p>" + value.timestamp + " > " + value.message + "</p>" ));
                });
                $('.audit-trail>div.audit-contents').html(div.html());
                $(".accordion-header .accordion-button").data('need-refresh', false);
            },
            error: function(xhr,status,error){
                if(!retryCSRF(xhr, this))
                {
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            }
        });
    }



    /**
     * Shows the Policy field OR the Control field (never both) per the
     * modal's #exception_type select -- design-system.md §11's read-view
     * rule ("never a greyed-out input") applies here to the FORM too: the
     * old mechanism showed both groups together and merely disabled
     * whichever one the user hadn't populated (a disabled <select> reads as
     * interactive-but-broken). Also clears the now-inactive group's value,
     * since a hidden-but-enabled field still submits via FormData -- unlike
     * the old disabled-field mechanism, where a disabled control is simply
     * omitted from the request. This is what keeps create_exception_api()'s/
     * update_exception_api()'s policy-XOR-control check satisfied.
     */
    function applyExceptionTypeVisibility(root) {
        var $type = root.find('[name="exception_type"]');
        if (!$type.length) {
            return;
        }
        var type = $type.val();
        root.find('[data-exception-type-field="policy"]').toggle(type === 'policy');
        root.find('[data-exception-type-field="control"]').toggle(type === 'control');
        if (type === 'control') {
            root.find('#policy').val('0');
        } else if (type === 'policy') {
            var $control = root.find('#control');
            $control.empty().append($('<option>', { value: '0', text: '--', selected: true }));
            root.find('#framework').val('0');
            renderExceptionControlChip($control);
        }
    }

    // renderMultiselectChips() -- shared with governance/documentation.php,
    // lives in js/simplerisk/common.js (both pages already load it).

    function displayFileSize(label, size) {
        if (<?= $escaper->escapeHtml(get_setting('max_upload_size')); ?> > size)
            label.attr("class","text-success");
        else
            label.attr("class","text-danger");

        var iSize = (size / 1024);
        if (iSize / 1024 > 1)
        {
            if (((iSize / 1024) / 1024) > 1)
            {
                iSize = (Math.round(((iSize / 1024) / 1024) * 100) / 100);
                label.html("<?= $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Gb");
            }
            else
            {
                iSize = (Math.round((iSize / 1024) * 100) / 100)
                label.html("<?= $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Mb");
            }
        }
        else
        {
            iSize = (Math.round(iSize * 100) / 100)
            label.html("<?= $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize  + "kb");
        }
    }
    // Full cross-framework control roster (design-system.md §14b), fetched
    // once and reused by every open of #exception-control-picker AND by
    // openExceptionForEdit()'s chip render -- replaces the old
    // load_framework_controls(), which re-fetched a framework-scoped slice
    // every time #framework changed. exceptionControlsRosterPromise lets a
    // caller that needs a control's NAME (openExceptionForEdit(), below)
    // wait for an in-flight fetch instead of racing it; $.when() on an
    // already-resolved deferred resolves immediately, so callers don't need
    // to know which case they're in.
    var exceptionControlsRoster = [];
    var exceptionControlsById = {};
    var exceptionControlsRosterPromise = null;
    function loadExceptionControlsRoster() {
        exceptionControlsRosterPromise = $.ajax({
            url: BASE_URL + '/api/v2/governance/controls/roster',
            type: 'GET',
        }).done(function (res) {
            exceptionControlsRoster = (res && res.data) ? res.data : [];
            exceptionControlsById = {};
            exceptionControlsRoster.forEach(function (c) {
                exceptionControlsById[String(c.id)] = c;
            });
        });
        return exceptionControlsRosterPromise;
    }

    /**
     * Renders the `control` <select>'s current single value as a chip
     * beside a "Choose a control…" button -- design-system.md §14b's
     * search+pick dialog (createFacetedPicker(), js/simplerisk/
     * sr-faceted-picker.js) in place of the flat, framework-scoped-but-still-
     * hundreds-to-low-thousands-row native <select>. Same shape as
     * governance/documentation.php's renderControlChips(), capped at one
     * chip since #control is single-value (singleSelect: true, see
     * getExceptionControlPicker() below) -- reimplemented locally, this page
     * doesn't load documentation.php's script. Safe to call repeatedly
     * (rebuilds the field's contents from the <select> each time).
     */
    function renderExceptionControlChip($select) {
        if (!$select.length) {
            return;
        }

        var $field = $select.next('.sr-chips-field');
        if (!$field.length) {
            $field = $('<div>', { 'class': 'sr-chips-field' }).insertAfter($select);
        }
        $field.empty();

        var $option = $select.find('option:selected');
        var value = $option.length ? String($option.val()) : '';
        // '0' is the "--" placeholder option applyExceptionTypeVisibility()/
        // openExceptionControlPicker()'s onCommit (and the modals' static
        // markup) always bake in -- not a real control.
        if (value && value !== '0') {
            // text:, never html: -- control names are user-authored
            // (design-system.md §14b's enableHTML warning applies here too).
            var $chip = $('<span>', { 'class': 'sr-chip', text: $option.text() });
            $('<button>', {
                type: 'button',
                'class': 'sr-chip-remove',
                'data-control-id': value,
                'aria-label': "<?= $escaper->escapeHtml($lang['Remove']); ?>",
                html: '&times;',
            }).appendTo($chip);
            $field.append($chip);
        }

        $('<button>', {
            type: 'button',
            'class': 'sr-chips-add',
            'data-control-picker-for': 'control',
            text: "<?= $escaper->escapeHtml($lang['ChooseAControl']); ?>",
        }).appendTo($field);
    }

    // Removing the chip is a direct edit of the field, not of the picker's
    // working copy -- it commits immediately, same as documentation.php's
    // equivalent handler. Resets to the '0' ("--") placeholder rather than
    // clearing the <select> outright, since that's what "nothing chosen"
    // already means for this field. Also clears the passenger #framework
    // field -- see openExceptionControlPicker()'s onCommit for why a
    // control's chosen framework_id travels with it rather than being its
    // own field. .closest('.sr-chips-field').prev('#control') stays scoped
    // to whichever modal the chip lives in -- see the picker-open handler
    // below for why a plain $('#control') lookup would be wrong here (the
    // Add and Update modals both have a #control field; that duplicate id
    // is pre-existing tech debt, out of scope for this fix).
    $(document).on('click', '.sr-chips-field .sr-chip-remove[data-control-id]', function() {
        var $select = $(this).closest('.sr-chips-field').prev('#control');
        $select.empty().append($('<option>', { value: '0', text: '--', selected: true }));
        $select.closest('.modal').find('#framework').val('0');
        renderExceptionControlChip($select);
        $select.trigger('change');
    });

    // Same duplicate-#control-id caveat as the remove handler above: resolve
    // the target select via the CLICKED button's own ancestor modal
    // (.closest('.modal')), never via a global '#control' / '#' + id lookup
    // -- the latter would always resolve to the Add modal's #control (first
    // in the DOM) even when the Update modal's button was the one clicked.
    $(document).on('click', '.sr-chips-add[data-control-picker-for="control"]', function() {
        var $select = $(this).closest('.modal').find('#control');
        if ($select.length) {
            openExceptionControlPicker($select);
        }
    });

    // The picker instance for #exception-control-picker. Created lazily on
    // first open, same as documentation.php's own getDocumentControlPicker()
    // -- one shared dialog, reused for whichever modal's #control field
    // opened it (openExceptionControlPicker() below is handed the already-
    // resolved <select>, so the shared dialog never itself has to
    // disambiguate the two #control elements).
    var exceptionControlPicker = null;

    /**
     * Lazily builds #exception-control-picker's shared-engine instance
     * (createFacetedPicker(), js/simplerisk/sr-faceted-picker.js). Framework
     * + family facets, same shape as compliance.js's getControlPicker() (the
     * reference implementation) -- the picker itself now does the
     * framework/family narrowing that the removed #framework <select> +
     * load_framework_controls() used to do a step earlier. singleSelect:
     * true, since `control` -- unlike Document Program's control_ids[] --
     * commits at most one value; the shared engine's toggle() replaces
     * rather than appends when this is set.
     */
    function getExceptionControlPicker() {
        if (!exceptionControlPicker) {
            exceptionControlPicker = createFacetedPicker({
                modalId: 'exception-control-picker',
                searchId: 'exception-control-picker-search',
                listId: 'exception-control-picker-list',
                selectedId: 'exception-control-picker-selected',
                countId: 'exception-control-picker-count',
                selectedCountId: 'exception-control-picker-selected-count',
                scopeId: 'exception-control-picker-scope',
                commitId: 'exception-control-picker-commit',
                facets: [
                    {
                        key: 'framework',
                        container: 'exception-control-picker-frameworks',
                        // A control maps into SEVERAL frameworks (framework_control_mappings),
                        // so this is a membership test, not a single value.
                        itemValues: function (c) { return (c.frameworks || []).map(Number); },
                    },
                    {
                        key: 'family',
                        container: 'exception-control-picker-families',
                        itemValues: function (c) { return [Number(c.family || 0)]; },
                    },
                ],
                singleSelect: true,
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
        return exceptionControlPicker;
    }

    /**
     * Opens #exception-control-picker for the given `control` <select>,
     * searching the full cross-framework roster (exceptionControlsRoster)
     * rather than whatever a framework-scoped <select> happened to already
     * hold. Mirrors documentation.php's openDocumentControlPicker().
     */
    function openExceptionControlPicker($select) {
        var current = String($select.val() || '0');

        getExceptionControlPicker().open({
            items: exceptionControlsRoster,
            chosen: current !== '0' ? [current] : [],
            onCommit: function(ids) {
                var id = ids.length ? String(ids[0]) : '';
                var control = id ? exceptionControlsById[id] : null;
                var $modal = $select.closest('.modal');
                if (control) {
                    $select.empty().append($('<option>', { value: control.id, text: control.short_name, selected: true }));
                    // framework_id is a passenger field on the exception, not
                    // an independent choice any more -- take the control's
                    // own first framework membership. The picker's Framework
                    // facet only narrows what the user searches through; it
                    // doesn't track which facet (if any) was active at the
                    // moment of commit, so a control mapped into several
                    // frameworks can't recover exactly which one the user had
                    // narrowed to. Good enough for a field that's display-only
                    // (get_exceptions_as_treegrid()'s framework_name
                    // resolution) rather than a hard identity constraint.
                    $modal.find('#framework').val((control.frameworks && control.frameworks.length) ? control.frameworks[0] : '0');
                } else {
                    $select.empty().append($('<option>', { value: '0', text: '--', selected: true }));
                    $modal.find('#framework').val('0');
                }
                renderExceptionControlChip($select);
                // Picking via the dialog sets .val() programmatically, which
                // fires no native 'change' event -- trigger one so any future
                // #control-scoped handler still runs, same as it did when a
                // user drove the native <select> directly.
                $select.trigger('change');
            },
        });
    }

     $(document).ready(function(){

        ExceptionsGrid.init();
        loadExceptionControlsRoster();

        if ($('#exception-audit-trail').length && window.ExceptionAuditTrail) {
            window.ExceptionAuditTrail.init();
        }

        $("#add_exception").click(function(event) {
            event.preventDefault();
            if ($('#file-upload')[0].files[0] && <?= $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload')[0].files[0].size) {
                showAlertFromMessage("<?= $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                return false;
            }
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/v2/exceptions/create",
                data: new FormData($('#exception-new-form')[0]),
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#exception--add').modal('hide');
                    $('#exception-new-form')[0].reset();
                    $('#exception-new-form #file-size').text("");
                    $("#exception-new-form [name='additional_stakeholders[]']").multiselect('select', []);
                    $("#exception-new-form [name='associated_risks[]']").multiselect('select', []);
                    renderMultiselectChips($("#exception-new-form [name='additional_stakeholders[]']"));
                    renderMultiselectChips($("#exception-new-form [name='associated_risks[]']"));

                    ExceptionsGrid.reload();

                    refreshAuditLogsIfOpen();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
            return false;
        });

        $("#update_exception").click(function(event) {
            event.preventDefault();
            if ($('#file-upload-update')[0].files[0] && <?= $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $('#file-upload-update')[0].files[0].size) {
                showAlertFromMessage("<?= $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                return false;
            }

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/v2/exceptions/update",
                data: new FormData($('#exception-update-form')[0]),
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#exception--update').modal('hide');
                    $('#exception-update-form')[0].reset();
                    $('#exception-update-form #file-size').text("");
                    $("#exception-update-form [name='additional_stakeholders[]']").multiselect('select', []);
                    $("#exception-update-form [name='associated_risks[]']").multiselect('select', []);
                    renderMultiselectChips($("#exception-update-form [name='additional_stakeholders[]']"));
                    renderMultiselectChips($("#exception-update-form [name='associated_risks[]']"));

                    // The flat grid re-fetches ALL types on every reload (unlike the
                    // old per-tab treegrids), so there's no need to track which
                    // specific tab/type changed here.
                    ExceptionsGrid.reload();

                    refreshAuditLogsIfOpen();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
            return false;
        });


        $("#unapprove_exception").click(function(event) {

            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/v2/exceptions/unapprove",
                data: new FormData($('#exception-update-form')[0]),
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data){
                    if(data.status_message){
                        showAlertsFromArray(data.status_message);
                    }

                    $('#exception--update').modal('hide');
                    $('#exception-update-form')[0].reset();
                    $('#exception-update-form #file-size').text("");
                    $("#exception-update-form [name='additional_stakeholders[]']").multiselect('select', []);
                    $("#exception-update-form [name='associated_risks[]']").multiselect('select', []);
                    renderMultiselectChips($("#exception-update-form [name='additional_stakeholders[]']"));
                    renderMultiselectChips($("#exception-update-form [name='associated_risks[]']"));

                    ExceptionsGrid.reload();

                    refreshAuditLogsIfOpen();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
            return false;
        });

        $('#exception--add').find('[name="exception_type"]').change(function() {applyExceptionTypeVisibility($('#exception--add'));});

        $('#exception--update').find('[name="exception_type"]').change(function() {applyExceptionTypeVisibility($('#exception--update'));});

        // Roster fields stay bootstrap-multiselect (tens-of-rows -- risks,
        // users; design-system.md §14b's sizing rule), styled per §14b's
        // chip pattern via renderMultiselectChips() -- onChange (not the
        // native 'change' event, which this plugin never fires on a pick)
        // keeps the chips in step.
        $("[name='additional_stakeholders[]']").multiselect({
            buttonWidth: '100%',
            onChange: function(option) {
                renderMultiselectChips($(option).closest('select'));
            },
        });
        $("[name='associated_risks[]']").multiselect({
            enableFiltering: true,
            buttonWidth: '100%',
            maxHeight: '400',
            onChange: function(option) {
                renderMultiselectChips($(option).closest('select'));
            },
        });

        // Initial chip render for every roster field -- so the "Add or
        // remove…" trigger is visible even before the user has picked
        // anything.
        renderMultiselectChips($("[name='additional_stakeholders[]'], [name='associated_risks[]']"));

        // Same for `control` -- each modal has its own #control field (the
        // duplicate id is pre-existing, see the picker handlers above), so
        // both need their own scoped render call rather than a single
        // $('#control').
        renderExceptionControlChip($('#exception--add').find('#control'));
        renderExceptionControlChip($('#exception--update').find('#control'));

        $("[name='approval_date']").initAsDatePicker({maxDate: new Date()});
        $("[name='creation_date']").initAsDatePicker({maxDate: new Date()});
        $("[name='next_review_date']").initAsDatePicker({minDate: new Date()});

        $("#exception-add-btn").click(function () {
            $("#exception--add .file-uploader input").val("");
            $("#exception--add #file-size").text("");
            $('#exception--add').modal('show');
        });

        //Have to remove the 'fade' class for the shown event to work for modals
        $('#exception--add, #exception--update, #exception--view').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
            applyExceptionTypeVisibility($(this));
        });

        $('.accordion-header .accordion-button').click(function(event) {
            event.preventDefault();
            
            if ($(".accordion-header .accordion-button").hasClass("collapsed") && $(".accordion-header .accordion-button").data('need-refresh')) {
                refreshAuditLogs();
            }

        });

        $('.refresh-audit-trail').click(function(event) {
            event.preventDefault();
            refreshAuditLogs();
        });

        $('.audit-trail select.audit-select-days').change(refreshAuditLogs);

        refreshAuditLogs();

        // file upload
        var fileAPISupported = typeof $("<input type='file'>").get(0).files != "undefined";

        if (fileAPISupported) {
            $("input.readonly").on('keydown paste focus', function(e){
                e.preventDefault();
                e.currentTarget.blur();
            });

            $("#exception-new-form input.readonly").click(function(){
                $("#file-upload").trigger("click");
            });

            $("#exception-update-form input.readonly").click(function(){
                $("#file-upload-update").trigger("click");
            });

            $('#file-upload').change(function(e){
                if (!e.target.files[0])
                    return;

                var fileName = e.target.files[0].name;
                $("#exception-new-form input.readonly").val(fileName);

                displayFileSize($("#exception-new-form #file-size"), e.target.files[0].size);

            });

            $('#file-upload-update').change(function(e){
                if (!e.target.files[0])
                    return;

                var fileName = e.target.files[0].name;
                $("#exception-update-form input.readonly").val(fileName);

                displayFileSize($("#exception-update-form #file-size"), e.target.files[0].size);

            });
        } else { // If File API is not supported
            $("input.readonly").remove();
            $('#file-upload').prop('required',true);
        }
        init_minimun_editor("#add_description");
        init_minimun_editor("#add_justification");
        init_minimun_editor("#update_description");
        init_minimun_editor("#update_justification");
    });
</script>

<?php if (check_permission_exception('create') || check_permission_exception('update')) { ?>
<!-- CONTROL PICKER (design-system.md §5/§14b), shared by the Add and Update
     exception forms' `control` field -- same shared engine (createFacetedPicker(),
     js/simplerisk/sr-faceted-picker.js) and the same Framework/Family facet
     shape as compliance/index.php's #control-picker (the reference
     implementation) and governance/documentation.php's #document-control-picker,
     instantiated here with singleSelect: true since `control` commits at most
     one value (see getExceptionControlPicker() above). ONE modal instance,
     reused for whichever form's #control field opened it --
     openExceptionControlPicker() above is handed the already-resolved
     <select>, so this dialog never itself has to disambiguate the two forms'
     (duplicate-id) #control elements. -->
<?php
    $exception_picker_frameworks = getAvailableControlFrameworkList(true);
    is_array($exception_picker_frameworks) || $exception_picker_frameworks = [];
    $exception_picker_families = getAvailableControlFamilyList();
    is_array($exception_picker_families) || $exception_picker_families = [];
    // Same natural, case-insensitive sort the picker's own frameworks/family
    // toolbar filters would use.
    $exception_picker_sort_by_name = static function ($a, $b) {
        return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    };
    usort($exception_picker_frameworks, $exception_picker_sort_by_name);
    usort($exception_picker_families, $exception_picker_sort_by_name);
    // data-picker-facet carries the id the roster's `family`/`frameworks` ids
    // are matched against; the count span is filled client-side.
    $exception_picker_facet_options = static function ($rows, $facet) use ($escaper) {
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
<div id="exception-control-picker" class="modal fade sr-modal sr-picker-modal" tabindex="-1" aria-hidden="true" aria-labelledby="exception-control-picker-title">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-list-check" aria-hidden="true"></i></span>
                <h5 class="modal-title" id="exception-control-picker-title"><?= $escaper->escapeHtml($lang['ChooseAControl']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']); ?>"></button>
            </div>
            <div class="sr-picker-search">
                <i class="fa fa-magnifying-glass sr-picker-search-icon" aria-hidden="true"></i>
                <input type="text" id="exception-control-picker-search" class="sr-picker-search-input" autocomplete="off"
                       placeholder="<?= $escaper->escapeHtmlAttr($lang['SearchControlsPlaceholder']); ?>"
                       aria-label="<?= $escaper->escapeHtmlAttr($lang['SearchControlsPlaceholder']); ?>">
                <span class="sr-picker-scope" id="exception-control-picker-scope"></span>
            </div>

            <div class="sr-picker-panes">
                <div class="sr-picker-pane sr-picker-pane--facet">
                    <div class="sr-picker-pane-head">
                        <span class="sr-picker-step">1</span>
                        <span><?= $escaper->escapeHtml($lang['Framework']); ?></span>
                        <button type="button" class="sr-picker-clear" data-picker-clear="framework"><?= $escaper->escapeHtml($lang['Clear']); ?></button>
                    </div>
                    <div class="sr-picker-scroll" id="exception-control-picker-frameworks">
                        <button type="button" class="sr-picker-facet" data-picker-facet="framework" data-picker-value="" aria-pressed="true">
                            <span class="sr-picker-facet-label"><?= $escaper->escapeHtml($lang['AllFrameworks']); ?></span>
                            <span class="sr-picker-facet-count"></span>
                        </button>
                        <?= $exception_picker_facet_options($exception_picker_frameworks, 'framework') ?>
                    </div>
                </div>

                <div class="sr-picker-pane sr-picker-pane--facet">
                    <div class="sr-picker-pane-head">
                        <span class="sr-picker-step">2</span>
                        <span><?= $escaper->escapeHtml($lang['ControlFamily']); ?></span>
                        <button type="button" class="sr-picker-clear" data-picker-clear="family"><?= $escaper->escapeHtml($lang['Clear']); ?></button>
                    </div>
                    <div class="sr-picker-scroll" id="exception-control-picker-families">
                        <button type="button" class="sr-picker-facet" data-picker-facet="family" data-picker-value="" aria-pressed="true">
                            <span class="sr-picker-facet-label"><?= $escaper->escapeHtml($lang['AllFamilies']); ?></span>
                            <span class="sr-picker-facet-count"></span>
                        </button>
                        <?= $exception_picker_facet_options($exception_picker_families, 'family') ?>
                    </div>
                </div>

                <div class="sr-picker-pane sr-picker-pane--list">
                    <div class="sr-picker-pane-head">
                        <span class="sr-picker-step">3</span>
                        <span><?= $escaper->escapeHtml($lang['Controls']); ?></span>
                        <span class="sr-picker-pane-count" id="exception-control-picker-count"></span>
                    </div>
                    <div class="sr-picker-scroll" id="exception-control-picker-list" role="listbox" aria-multiselectable="true"
                         aria-label="<?= $escaper->escapeHtmlAttr($lang['Controls']); ?>"></div>
                </div>

                <div class="sr-picker-pane sr-picker-pane--selected">
                    <div class="sr-picker-pane-head">
                        <span><?= $escaper->escapeHtml($lang['Selected']); ?></span>
                        <span class="sr-picker-pane-count" id="exception-control-picker-selected-count"></span>
                    </div>
                    <div class="sr-picker-scroll sr-picker-selected" id="exception-control-picker-selected"></div>
                </div>
            </div>

            <div class="modal-footer sr-picker-foot">
                <span class="sr-picker-hint"><?= $escaper->escapeHtml($lang['PickerKeyboardHint']); ?></span>
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-submit" id="exception-control-picker-commit"><?= $escaper->escapeHtml($lang['UseTheseControls']); ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

 <!-- MODAL WINDOW FOR ADDING EXCEPTION (design-system.md §8, Form-in-modal type).
      Sectioned into .sr-qcard groups answering: what the exception is (name,
      status, policy/framework/control), why it exists (the two rich-text
      fields), who's involved, its review cadence (plus associated risks --
      grouped here per the task brief rather than under "What", since it's the
      one field that doesn't fit either card cleanly), and its attachment. -->
<?php if (check_permission_exception('create')) { ?>
<div id="exception--add" class="modal fade sr-modal" tabindex="-1" role="dialog" aria-labelledby="exception--add-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="exception--add-title"><?= $escaper->escapeHtml($lang['ExceptionAdd']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cancel']); ?>"></button>
            </div>
            <div class="modal-body">
                <form id="exception-new-form" class="sr-qcard-form" action="#" method="POST" autocomplete="off">
                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-circle-info" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['ExceptionDetails']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ExceptionName']); ?><span class="required">*</span></label>
                                    <input type="text" required name="name" value="" class="form-control" autocomplete="off">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ExceptionStatus']); ?></label>
                                    <?php create_dropdown("document_exceptions_status", NULL, "document_exceptions_status", false); ?>
                                </div>
                                <!-- Exception Type: an explicit either/or toggle (design-system.md
                                     §11 -- never a greyed-out input) replacing the old mechanism
                                     of showing Policy AND Framework/Control together and merely
                                     DISABLING whichever the user hadn't picked
                                     (refresh_type_selects_display(), now applyExceptionTypeVisibility()
                                     below). The backend (create_exception_api()/update_exception_api(),
                                     includes/api.php) never reads this field -- it still derives
                                     "policy or control" from a policy-XOR-control check on the
                                     submitted `policy`/`control` values, exactly as before; this
                                     select only drives which of the two field groups below is
                                     shown, and applyExceptionTypeVisibility() clears the OTHER
                                     group's value on every change so the inactive group can never
                                     leak a stale value into that xor check. -->
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ExceptionType']); ?></label>
                                    <select name="exception_type" id="exception_type" class="form-select">
                                        <option value="policy"><?= $escaper->escapeHtml($lang['Policy']); ?></option>
                                        <option value="control"><?= $escaper->escapeHtml($lang['Control']); ?></option>
                                    </select>
                                </div>
                                <div class="sr-qfield" data-exception-type-field="policy">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Policy']); ?></label>
                                    <?php create_dropdown("policies", NULL, "policy", true); ?>
                                </div>
                                <div class="sr-qfield sr-qfield--full" data-exception-type-field="control">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Control']); ?></label>
                                    <!-- The <select> stays in the DOM as the form's real
                                         value; the chip + "Choose a control…" button beside
                                         it (rendered by renderExceptionControlChip(), inserted
                                         by JS) are its UI (design-system.md §14b -- roster size
                                         decides, not field type; the cross-framework control
                                         roster is ~1,500 rows). .sr-picker-value hides the raw
                                         <select> via the same CSS rule Document Program's
                                         control_ids[] picker uses. #framework is a hidden
                                         passenger field -- the picker's Framework facet lets the
                                         user narrow by framework, but the control THEY commit is
                                         still identified by its own id alone (framework_controls.id,
                                         a control can map into several frameworks via
                                         framework_control_mappings); onCommit below derives a
                                         framework_id from the chosen control's own membership,
                                         same as it always was, just no longer a separately-chosen
                                         field. -->
                                    <select id="control" name="control" class="form-field form-select sr-picker-value">
                                        <option value="0">--</option>
                                    </select>
                                    <input type="hidden" name="framework" id="framework" value="0">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-align-left" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['DescriptionJustification']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qstack">
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Description']); ?></label>
                                    <textarea name="description" value="" class="form-control" rows="6" id="add_description" style="width:100%;"></textarea>
                                </div>
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Justification']); ?></label>
                                    <textarea name="justification" value="" class="form-control" id="add_justification" rows="6" style="width:100%;"></textarea>
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
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ExceptionOwner']); ?></label>
                                    <?php create_dropdown("enabled_users", NULL, "owner", false, false, false); ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
                                    <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders"); ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Approver']); ?></label>
                                    <?php create_dropdown("enabled_users", NULL, "approver", true); ?>
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
                                    <input type="text" name="creation_date" value="<?= $escaper->escapeHtml(date(get_default_date_format())); ?>" class="form-control datepicker">
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
                                    <input type="text" name="next_review_date" value="" class="form-control datepicker">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ApprovalDate']); ?></label>
                                    <input type="text" name="approval_date" value="" class="form-control datepicker">
                                </div>
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['AssociatedRisks']); ?></label>
                                    <select name="associated_risks[]" multiple="true" class="form-select">
                                    <?php
                                        foreach ($risks as $risk) {
                                            $risk_id = $risk['id'];
                                            $subject = "(" . ($risk['id'] + 1000) . ") " . $risk['subject'];
                                            echo "<option value='{$risk_id}'>" . $escaper->escapeHTML($subject) . "</option>\n";
                                        }

                                    ?>
                                    </select>
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
                                        <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['File']); ?></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control readonly"/>
                                            <label for="file-upload" class="btn btn-submit m-r-10"><?= $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                                            <label class="text-dark align-self-center">Max <?= $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</label>
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
                <button type="button" id="add_exception" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php if (check_permission_exception('update')) { ?>
<!-- MODAL WINDOW FOR EDITING EXCEPTION (design-system.md §8, Form-in-modal type) --
     same section shape as #exception--add above. #unapprove_exception and the
     #unapprove_exception control (pre-existing, not introduced by this task)
     survives the restructure in the footer.

     The hidden `approved_original` checkbox that used to sit in the
     form-hidden-fields spot is GONE: update_exception_api() now reads the
     already-approved flag from the database rather than from the request, so a
     client-supplied copy of it was both redundant and, when omitted, a way to
     bypass the SR-19 next_review_date recalculation entirely. -->
<div id="exception--update" class="modal fade sr-modal" tabindex="-1" role="dialog" aria-labelledby="exception--update-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-pen" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="exception--update-title"><?= $escaper->escapeHtml($lang['ExceptionUpdate']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cancel']); ?>"></button>
            </div>
            <div class="modal-body">
                <form id="exception-update-form" class="sr-qcard-form" action="#" method="post" autocomplete="off">
                    <input type="hidden" class="exception_id" name="exception_id" value="">
                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-circle-info" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['ExceptionDetails']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ExceptionName']); ?><span class="required">*</span></label>
                                    <input type="text" required name="name" value="" class="form-control" autocomplete="off">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ExceptionStatus']); ?></label>
                                    <?php create_dropdown("document_exceptions_status", NULL, "document_exceptions_status", false); ?>
                                </div>
                                <!-- See the Add modal's identical field for the full
                                     reasoning comment (applyExceptionTypeVisibility()). -->
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ExceptionType']); ?></label>
                                    <select name="exception_type" id="exception_type" class="form-select">
                                        <option value="policy"><?= $escaper->escapeHtml($lang['Policy']); ?></option>
                                        <option value="control"><?= $escaper->escapeHtml($lang['Control']); ?></option>
                                    </select>
                                </div>
                                <div class="sr-qfield" data-exception-type-field="policy">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Policy']); ?></label>
                                    <?php create_dropdown("policies", NULL, "policy", true, false, false, "", "--", "0"); ?>
                                </div>
                                <div class="sr-qfield sr-qfield--full" data-exception-type-field="control">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Control']); ?></label>
                                    <!-- Same UI-backed <select> pattern as the Add modal's
                                         #control above -- see that field's comment. -->
                                    <select id="control" name="control" class="form-field form-control sr-picker-value">
                                        <option value="0">--</option>
                                    </select>
                                    <input type="hidden" name="framework" id="framework" value="0">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-align-left" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['DescriptionJustification']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qstack">
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Description']); ?></label>
                                    <textarea name="description" value="" class="form-control" id="update_description" rows="6" style="width:100%;"></textarea>
                                </div>
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Justification']); ?></label>
                                    <textarea name="justification" value="" id="update_justification" class="form-control" rows="6" style="width:100%;"></textarea>
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
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ExceptionOwner']); ?></label>
                                    <?php create_dropdown("enabled_users", NULL, "owner", false, false, false); ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
                                    <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders"); ?>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Approver']); ?></label>
                                    <?php create_dropdown("enabled_users", NULL, "approver", true, false, false, "", "--", "0"); ?>
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
                                    <input type="text" name="creation_date" value="" class="form-control datepicker">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ReviewFrequency']); ?></label>
                                    <div class="input-group">
                                        <input type="number" min="0" name="review_frequency" value="" class="form-control">
                                        <span class="input-group-text">(<?= $escaper->escapeHtml($lang['days']); ?>)</span>
                                    </div>
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['NextReviewDate']); ?></label>
                                    <input type="text" name="next_review_date" value="" class="form-control datepicker">
                                </div>
                                <div class="sr-qfield">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ApprovalDate']); ?></label>
                                    <input type="text" name="approval_date" value="" class="form-control datepicker">
                                </div>
                                <div class="sr-qfield sr-qfield--full">
                                    <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['AssociatedRisks']); ?></label>
                                    <select name="associated_risks[]" multiple="true" class="form-select">
                                    <?php
                                        foreach ($risks as $risk) {
                                            $risk_id = $risk['id'];
                                            $subject = "(" . ($risk['id'] + 1000) . ") " . $risk['subject'];
                                            echo "<option value='{$risk_id}'>" . $escaper->escapeHTML($subject) . "</option>\n";
                                        }
                                    ?>
                                    </select>
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
                                        <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['File']); ?></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control readonly"/>
                                            <label for="file-upload-update" class="btn btn-submit m-r-10"><?= $escaper->escapeHtml($lang['ChooseFile']) ?></label>
                                            <label class="text-dark align-self-center">Max <?= $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)); ?> Mb</label>
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
                <button type="button" id="unapprove_exception" class="btn btn-dark"><?= $escaper->escapeHtml($lang['Unapprove']); ?></button>
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" id="update_exception" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Update']); ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- MODAL WINDOW FOR DISPLAYING AN EXCEPTION (design-system.md §8 shell + §11
     detail-view rule: fields are values under a .sr-qlabel, never a greyed-out
     input). Section grouping mirrors the Add/Update modals above (Task N) so
     read and edit present as one object. IDs are unchanged from the legacy
     markup -- the .exception--view click handler above (and get_exception_for_display_api())
     targets them directly, including the .parent().show()/.hide() toggle
     between the Policy field and the Framework/Control pair, which now
     resolves to each field's .sr-qfield wrapper instead of its old .form-group. -->
<div id="exception--view" class="modal fade sr-modal" tabindex="-1" role="dialog" aria-labelledby="name" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-eye" aria-hidden="true"></i></span>
                <h4 id="name" class="modal-title"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Close']); ?>"></button>
            </div>
            <div class="modal-body">
                <section class="sr-qcard">
                    <div class="sr-qcard-head">
                        <span class="sr-qcard-icon"><i class="fa fa-circle-info" aria-hidden="true"></i></span>
                        <h3><?= $escaper->escapeHtml($lang['ExceptionDetails']); ?></h3>
                    </div>
                    <div class="sr-qcard-body">
                        <div class="sr-qgrid">
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['ExceptionType']); ?></label>
                                <div id="type"></div>
                            </div>
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['Policy']); ?></label>
                                <div id="policy"></div>
                            </div>
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['FrameworkName']); ?></label>
                                <div id="framework"></div>
                            </div>
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['ControlName']); ?></label>
                                <div id="control"></div>
                            </div>
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['ExceptionStatus']); ?></label>
                                <div id="document_exceptions_status"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="sr-qcard">
                    <div class="sr-qcard-head">
                        <span class="sr-qcard-icon"><i class="fa fa-align-left" aria-hidden="true"></i></span>
                        <h3><?= $escaper->escapeHtml($lang['DescriptionJustification']); ?></h3>
                    </div>
                    <div class="sr-qcard-body">
                        <div class="sr-qstack">
                            <div class="sr-qfield sr-qfield--full">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['Description']); ?></label>
                                <div id="description"></div>
                            </div>
                            <div class="sr-qfield sr-qfield--full">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['Justification']); ?></label>
                                <div id="justification"></div>
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
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['ExceptionOwner']); ?></label>
                                <div id="owner"></div>
                            </div>
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
                                <div id="additional_stakeholders"></div>
                            </div>
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['Approver']); ?></label>
                                <div id="approver"></div>
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
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['CreationDate']); ?></label>
                                <div id="creation_date"></div>
                            </div>
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['ReviewFrequency']); ?></label>
                                <div><span id="review_frequency"></span> <?= $escaper->escapeHtml($lang['days']); ?></div>
                            </div>
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['NextReviewDate']); ?></label>
                                <div id="next_review_date"></div>
                            </div>
                            <div class="sr-qfield">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['ApprovalDate']); ?></label>
                                <div id="approval_date"></div>
                            </div>
                            <div class="sr-qfield sr-qfield--full">
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['AssociatedRisks']); ?></label>
                                <div id="associated_risks"></div>
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
                                <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['File']); ?></label>
                                <div id="file_download"></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Close']); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- APPROVE / UNAPPROVE EXCEPTION CONFIRM (design-system.md §8, Confirm type --
     unapprove reverses approve, so neither is destructive). Shared by the row-
     level approve/unapprove icon and the bulk bar's "Approve selected" action;
     js/simplerisk/pages/governance-exceptions.js swaps the icon/title/body/primary-
     button text and the POST target (exception approve/unapprove vs
     batch-approve) based on which action opened it -- same shape as
     documentation.php's #document-approve-confirm (Task 9). -->
<?php if ($can_approve) { ?>
<div id="exception-approve-confirm" class="modal fade sr-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon" id="exception-approve-confirm-icon"><i class="fa fa-check" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="exception-approve-confirm-title"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cancel']); ?>"></button>
            </div>
            <div class="modal-body">
                <section class="sr-qcard">
                    <div class="sr-qcard-body">
                        <p id="exception-approve-confirm-body" class="mb-0"></p>
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-submit" id="exception-approve-confirm-yes"></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- DELETE EXCEPTION CONFIRM (design-system.md §8, Confirm type -- destructive).
     Shared by the row-level delete icon (one exception_id) and the bulk bar's
     "Delete selected" action (a set of ids, deleted with one
     POST /exceptions/delete per id -- see the comment in
     js/simplerisk/pages/governance-exceptions.js on why the existing parent-
     scoped POST /exceptions/batch-delete endpoint doesn't fit an arbitrary
     multi-row selection). -->
<?php if ($can_delete) { ?>
<div id="exception-delete-confirm" class="modal fade sr-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon" id="exception-delete-confirm-icon"><i class="fa fa-trash" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="exception-delete-confirm-title"></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtmlAttr($lang['Cancel']); ?>"></button>
            </div>
            <div class="modal-body">
                <section class="sr-qcard">
                    <div class="sr-qcard-body">
                        <p id="exception-delete-confirm-body" class="mb-0"></p>
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" class="btn btn-danger" id="exception-delete-confirm-yes"><?= $escaper->escapeHtml($lang['Delete']); ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>