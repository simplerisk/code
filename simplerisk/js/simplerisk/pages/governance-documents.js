// ====================================================================
// Document Program grid (design-system.md §6) -- Task 9.
//
// A single client-rendered .sr-table-card, one flat table for every
// document type. The insights band above it (governance/documentation.php's
// UILayout('document_program_insights'), tiles rendered by
// get_ui_widget_document_program_insights() in api/v2/includes/api.php)
// narrows it by type via real ?type=<slug> links -- init() below reads that
// param on load. Replaces the old per-tab EasyUI treegrid and, later, the
// tab-strip redesign.
//
// Split out of js/simplerisk/pages/governance.js (which still owns the
// Define Control Frameworks page's JS, and the .document--delete /
// .framework-block--edit handlers documentation.php's own inline script
// also depends on) once this feature pushed that file meaningfully past
// the ~1500-line split threshold -- see task-9-report.md.
//
// Rows are rebuilt from scratch on every load/reload -- the same shape
// js/simplerisk/pages/self-assessment.js's initSrTable() already uses
// for its own .sr-table-card grids -- rather than a static PHP-printed
// <table> DataTables re-uses in place. A classic (non-serverSide)
// DataTable supplies sort/search/paging chrome over the rendered rows;
// GET /api/v2/governance/documents/treegrid?type= is the only fetch.
// ====================================================================
var DocumentProgramGrid = (function ($) {
    'use strict';

    // Status pill family (design-system.md §7 "State -- soft"): keyed off the
    // STABLE numeric document_status_id the API now returns (Task 9's
    // extension to get_documents_as_treegrid()), never off the DB-stored
    // label text, so a custom/renamed status label can't break the mapping.
    var DOCUMENT_STATUS_META = {
        1: { pillClass: 'sr-state-neutral', labelKey: 'Draft' },
        2: { pillClass: 'sr-state-info', labelKey: 'InReview' },
        3: { pillClass: 'sr-state-success', labelKey: 'Approved' }
    };

    // document_type is the raw slug the row was fetched/stored under
    // (policies/guidelines/standards/procedures) -- localize it for display,
    // and this object's own key order fixes the filter-card row's display
    // order, rather than showing the slug verbatim.
    var DOCUMENT_TYPE_LABEL_KEYS = {
        policies: 'Policies',
        guidelines: 'Guidelines',
        standards: 'Standards',
        procedures: 'Procedures'
    };

    // Used by the grid's DocumentType column: the stock four categories get
    // their translated label via DOCUMENT_TYPE_LABEL_KEYS; any other category
    // (admin-added or renamed via Add/Remove Values) has no
    // lang key of its own, so its stored name IS the display label.
    function categoryLabel(name) {
        var key = DOCUMENT_TYPE_LABEL_KEYS[name];
        return key ? L(key) : (name || '');
    }

    var dt = null;
    var perms = { canEdit: false, canDelete: false, canApprove: false, canView: false };
    var selectedIds = {};
    var approveModalMode = null; // 'approve' | 'unapprove' | 'batch-approve'
    var approveModalIds = [];
    var bulkDeleteIds = [];

    // The full unfiltered, already-flattened row set from the last fetch.
    // GET /api/v2/governance/documents/treegrid always fetches everything now
    // (no server-side ?type= scoping) -- every filter (type/framework/status/
    // approver/next-review bucket, plus DataTables' own text search) narrows
    // this client-side, matching the grid's existing "rebuild fresh on every
    // render" shape rather than adding a second, server-scoped data path.
    var allRows = [];

    // Filters row state (design-system.md §6b/§6c reference: Define Tests).
    // type/framework are multi-select (OR within a facet); status/approver/
    // review are single-select. Seeded once from the URL at init() -- see
    // readFiltersFromUrl() -- so the insights band's KPI tile links
    // (documentation.php?type=<slug>) and any bookmarked/shared filtered
    // link land pre-filtered on the first render.
    var filters = { type: [], framework: [], status: '', approver: '', review: '' };

    // Columns picker (design-system.md §6c, Define Exceptions' reference
    // implementation). Every togglable column defaults to visible -- the
    // picker is for decluttering, never an opt-in the page ships collapsed.
    var TOGGLE_COLUMNS = ['document_type', 'frameworks', 'controls', 'submitter', 'approver', 'approval_date', 'updated_by', 'creation_date', 'last_review', 'next_review'];
    var columnVisible = {
        document_type: true, frameworks: true, controls: true, submitter: true, approver: true,
        approval_date: true, updated_by: true, creation_date: true, last_review: true, next_review: true
    };

    function columnLabel(col) {
        switch (col) {
            case 'document_type': return L('DocumentType');
            case 'frameworks': return L('ControlFrameworks');
            case 'controls': return L('Controls');
            case 'submitter': return L('Submitter');
            case 'approver': return L('Approver');
            case 'approval_date': return L('ApprovalDate');
            case 'updated_by': return L('UpdatedBy');
            case 'creation_date': return L('CreationDate');
            case 'last_review': return L('LastReviewDate');
            case 'next_review': return L('NextReviewDate');
            default: return col;
        }
    }

    function esc(value) {
        return escapeHtml(value === undefined || value === null ? '' : String(value));
    }

    // Walks the treegrid response's nested `children` arrays (a document's
    // parent/child mapping -- see the Add/Edit modal's Parent Document field)
    // into one flat, depth-tagged list. The grid is a plain DataTables table
    // now, with no EasyUI-style expand/collapse, so a child document is shown
    // indented under its parent rather than silently dropped because it
    // isn't at the top level of the response.
    function flattenDocumentTree(nodes, depth) {
        var out = [];
        (nodes || []).forEach(function (node) {
            var copy = $.extend({}, node);
            var children = copy.children;
            delete copy.children;
            copy._depth = depth || 0;
            out.push(copy);
            if (children && children.length) {
                out = out.concat(flattenDocumentTree(children, (depth || 0) + 1));
            }
        });
        return out;
    }

    // The next-review date lane is always a status, never a plain fact:
    // overdue/due-soon/on-track each borrow the matching danger/warning/
    // success .sr-state-* tint via the --overdue/--due-soon/--ok chip
    // modifiers. On-track (7+ days out) is affirmatively green rather than
    // falling back to the neutral base chip -- that read as "nothing to
    // report" and made a genuinely fine date look identical to a date with
    // no status at all.
    function reviewChipHtml(doc) {
        if (!doc.next_review_date || doc.next_review_date === '0000-00-00') {
            return '<span class="sr-cell-dash">&mdash;</span>';
        }
        var modifierClass = ' sr-date-chip--ok';
        var icon = 'fa-calendar-check';
        if (doc.next_review_status === 'overdue') {
            modifierClass = ' sr-date-chip--overdue';
            icon = 'fa-triangle-exclamation';
        } else if (doc.next_review_status === 'due_soon') {
            modifierClass = ' sr-date-chip--due-soon';
            icon = 'fa-calendar-days';
        }
        return '<span class="sr-date-chip' + modifierClass + '"><i class="fa ' + icon + '" aria-hidden="true"></i><span>' +
            esc(doc.next_review_date) + '</span></span>';
    }

    // SR-180: renders each of a document's linked controls (control_links,
    // added by get_documents_as_treegrid() in governance.php) as a hyperlink
    // back to its record on Define Control Frameworks, using the confirmed
    // real deep-link shape (governance/index.php?control_id=N -- the same
    // convention the governance dashboard's Failing Controls widget already
    // uses). Both `name` and `url` are escaped here even though `url` is
    // server-built from a hardcoded path plus an (int)-cast id (no
    // user-controlled data) -- CLAUDE.md's rule is every field gets escaped
    // once at the render sink, not "escape only where a threat model requires
    // it", so this stays consistent with every other field in this file.
    function controlLinksHtml(doc) {
        return (doc.control_links || []).map(function (control) {
            return '<a class="ctrl-link text-info" href="' + esc(control.url) + '">' + esc(control.name) + '</a>';
        }).join(', ');
    }

    // 1536 -> "1.5 KB", matching the everyday-usage (1000-based) convention
    // most file managers use, not the binary (1024-based) KiB one -- the
    // exact byte count is never what a viewer is actually asking for.
    function formatFileSize(bytes) {
        var n = parseInt(bytes, 10);
        if (!n || n <= 0) {
            return '';
        }
        if (n < 1000) {
            return n + ' B';
        }
        if (n < 1000000) {
            return (n / 1000).toFixed(1) + ' KB';
        }
        return (n / 1000000).toFixed(1) + ' MB';
    }

    function fetchDocumentVersions(documentId) {
        // GET -- csrf-magic only gates form-urlencoded/FormData POST bodies,
        // matching compliance-define-tests.js's identical fetchControlMappings().
        return $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/governance/documents/versions',
            data: { document_id: documentId }
        });
    }

    function renderVersionHistoryError($panel) {
        $panel.html('<div class="sr-table-empty-body">' + esc(L('CouldNotLoadVersionHistory')) + '</div>');
    }

    // rows: get_document_versions_api()'s response, oldest version first
    // (matches get_document_versions_by_id()'s own ORDER BY t2.version).
    // Shown newest-first here since that's the direction a viewer actually
    // scans a history list.
    function renderVersionHistory($panel, documentId, rows) {
        if (!rows || !rows.length) {
            $panel.html('<div class="sr-table-empty-body">' + esc(L('CouldNotLoadVersionHistory')) + '</div>');
            return;
        }
        var sorted = rows.slice().reverse();
        // 'EncryptionStatusVersion' -- reused rather than a fresh 'Version'
        // key: same English text, and lang.en.php is append-only so a
        // needless Crowdin duplicate can't be cleaned up later without
        // orphaning translations. The key name is encryption-flavored but
        // the lookup is generic (L() resolves any key by string).
        var html = '<table class="sr-version-history-table"><thead><tr>' +
            '<th>' + esc(L('EncryptionStatusVersion')) + '</th>' +
            '<th>' + esc(L('FileName')) + '</th>' +
            '<th>' + esc(L('FileSize')) + '</th>' +
            '<th>' + esc(L('CreationDate')) + '</th>' +
            '<th>' + esc(L('UploadedBy')) + '</th>' +
            '<th class="sr-actions-col"><span class="visually-hidden">' + esc(L('Actions')) + '</span></th>' +
        '</tr></thead><tbody>';

        sorted.forEach(function (row) {
            var downloadHref = BASE_URL + '/governance/download.php?id=' + encodeURIComponent(row.unique_name || '');
            var actions = '<a class="sr-row-action" href="' + downloadHref + '" title="' + esc(L('Download')) + '" aria-label="' + esc(L('Download')) + '"><i class="fa fa-download" aria-hidden="true"></i></a>';
            // No delete action for the current version -- see the identical
            // guard in delete_document() (includes/governance.php); this is
            // the client-side half of the same rule, not a separate decision.
            if (perms.canDelete && !row.is_current) {
                actions += '<button type="button" class="sr-row-action sr-row-action-danger document--delete" data-id="' + esc(documentId) + '" data-version="' + esc(row.version) + '" data-bs-toggle="modal" data-bs-target="#document-delete-modal" title="' + esc(L('DeleteVersion')) + '" aria-label="' + esc(L('DeleteVersion')) + '"><i class="fa fa-trash" aria-hidden="true"></i></button>';
            }
            html += '<tr>' +
                '<td>' + esc(row.version) + (row.is_current ? '<span class="sr-version-current-badge">' + esc(L('Current')) + '</span>' : '') + '</td>' +
                '<td>' + esc(row.file_name) + '</td>' +
                '<td>' + esc(formatFileSize(row.file_size)) + '</td>' +
                '<td>' + esc(row.uploaded_at) + '</td>' +
                '<td>' + (row.uploaded_by ? esc(row.uploaded_by) : '<span class="sr-cell-dash">&mdash;</span>') + '</td>' +
                // .sr-row-action alone (not wrapped in .sr-row-actions) --
                // the wrapper's hover/focus-reveal behavior belongs to the
                // main grid's own dense rows; a handful of infrequent rows
                // inside an already-opened detail panel should just show
                // their actions plainly.
                '<td class="sr-actions-col"><span class="sr-version-actions">' + actions + '</span></td>' +
            '</tr>';
        });

        html += '</tbody></table>';
        $panel.html(html);
    }

    // Toggles the version-history row directly under $docRow (always its
    // next sibling, per rowHtml()). Same lazy-fetch/re-expand-without-
    // refetch contract as compliance-define-tests.js's toggleControlDetail().
    // Unlike compliance-define-tests.js's identical-in-spirit
    // toggleControlDetail() (a hand-rolled table with no library involved),
    // this grid hands its rows to a REAL DataTables instance (dt), which
    // sorts/pages/searches by treating every <tbody> <tr> as one of its own
    // data rows. A hand-built sibling <tr> for the detail panel (Define
    // Tests' approach) would confuse that model -- DataTables would count
    // it as an extra row, and a sort/page could separate it from its
    // parent. DataTables' own row().child() API is the correct mechanism
    // here: it manages the wrapper row/cell/colspan itself and keeps the
    // child correctly paired with its parent through every redraw.
    // row.child()'s own show/hide (no args after the first .child($panel))
    // is what gives this its lazy-fetch-once-then-reuse behavior for free --
    // hide() detaches the child from view without discarding the content
    // DataTables is holding for it, so re-expanding later just re-shows the
    // same already-rendered panel instead of re-fetching.
    function toggleVersionHistory($docRow) {
        if (!dt) {
            return;
        }
        var row = dt.row($docRow);
        if (!row.length) {
            return;
        }

        // Both the row AND the caret button carry aria-expanded -- the
        // button's is its own accessible state (it's the actual control a
        // keyboard/AT user activates); the row's is what the CSS rotation
        // rule keys off (tr.sr-doc-row[aria-expanded="true"] .sr-group-caret,
        // scss/modules/_governance.scss), since the button alone rotating
        // itself via its own attribute would still need a selector scoped
        // to this row and not Define Control Frameworks' .sr-group-row.
        var expanded = $docRow.attr('aria-expanded') === 'true';
        if (expanded) {
            $docRow.attr('aria-expanded', 'false');
            $docRow.find('> td .sr-group-caret').attr('aria-expanded', 'false');
            row.child.hide();
            return;
        }
        $docRow.attr('aria-expanded', 'true');
        $docRow.find('> td .sr-group-caret').attr('aria-expanded', 'true');

        if (row.child() && row.child().length) {
            row.child.show();
            return;
        }

        // sr-version-history-panel alongside the shared sr-expand-panel:
        // that shared class is deliberately plain white everywhere else
        // (Define Tests' own comment on it explains why -- a grey fill
        // read as a stray box there) -- this second, page-scoped class is
        // how the version history panel gets its own distinct tint
        // (scss/modules/_governance.scss) without touching that shared
        // default for every other consumer.
        var $panel = $('<div class="sr-expand-panel sr-version-history-panel"></div>');
        row.child($panel).show();
        var documentId = $docRow.data('id');

        fetchDocumentVersions(documentId)
            .done(function (result) {
                renderVersionHistory($panel, documentId, (result && result.data) ? result.data : []);
            })
            .fail(function () {
                renderVersionHistoryError($panel);
            });
    }

    // Row actions cluster. Reuses the REAL document--edit / document--delete
    // triggers (documentation.php's own openDocumentForEdit() delegated
    // handler, and the .document--delete handler in js/simplerisk/pages/
    // governance.js) -- not the .framework-block--edit/.framework-block--delete
    // classes the old get_documents_as_treegrid() 'actions' HTML wired by
    // mistake (those open the FRAMEWORK edit modal, not the document one).
    //
    // Wrapped in the shipped .sr-row-actions-wrap/.sr-row-actions-toggle/
    // .sr-row-actions disclosure (_tables.scss, design-system.md §6/§6b) --
    // matching Manage Audits, Define Tests, and Governance Frameworks/Control
    // Catalog. ONE DOM, two presentations: at full width the cluster shows
    // inline and reveals on row hover (toggle is display:none); below the
    // compact-tier breakpoint (and under `(hover: none)`, i.e. touch) the
    // toggle takes over and pops this same cluster as a menu -- see
    // governance-frameworks.js's rowActionsWrap() docblock for why rendering
    // the cluster WITHOUT the toggle leaves it unreachable on touch, not just
    // hidden. The open/close/orient behavior is shared via
    // js/simplerisk/sr-row-actions-menu.js (SRRowActionsMenu.bind(), wired
    // once in init() below).
    function rowActionsHtml(doc) {
        var actionsLabel = L('Actions');
        var html = '<span class="sr-row-actions-wrap"><button type="button" class="sr-row-actions-toggle" aria-expanded="false" aria-haspopup="true" aria-label="' + esc(actionsLabel) + '" title="' + esc(actionsLabel) + '"><i class="fa fa-ellipsis" aria-hidden="true"></i></button><span class="sr-row-actions">';
        if (perms.canView) {
            // The document name cell is plain text (rowHtml()) -- this row
            // action is the only download entry point, gated the same way
            // "Able to View Documentation" gates seeing the row at all.
            var downloadHref = BASE_URL + '/governance/download.php?id=' + encodeURIComponent(doc.unique_name || '');
            html += '<a class="sr-row-action" href="' + downloadHref + '" title="' + esc(L('Download')) + '" aria-label="' + esc(L('Download')) + '"><i class="fa fa-download" aria-hidden="true"></i></a>';
        }
        if (perms.canEdit) {
            html += '<button type="button" class="sr-row-action document--edit" data-id="' + esc(doc.id) + '" title="' + esc(L('Edit')) + '" aria-label="' + esc(L('Edit')) + '"><i class="fa fa-edit" aria-hidden="true"></i></button>';
        }
        if (perms.canApprove) {
            // Approve stays offered even when already Approved (data-id +
            // approve_document() are identical either way -- it's the same
            // "change to approved, log it, refresh approval date + next
            // review date" action, not a separate renewal concept) so a
            // document nearing its review date can be re-approved directly,
            // instead of an Unapprove-then-Approve round trip that would
            // briefly leave it non-approved and log two entries instead of
            // one clean re-approval.
            if (doc.document_status_id === 3) {
                html += '<button type="button" class="sr-row-action document--unapprove" data-id="' + esc(doc.id) + '" data-name="' + esc(doc.document_name) + '" title="' + esc(L('Unapprove')) + '" aria-label="' + esc(L('Unapprove')) + '"><i class="fa fa-rotate-left" aria-hidden="true"></i></button>';
            }
            html += '<button type="button" class="sr-row-action document--approve" data-id="' + esc(doc.id) + '" data-name="' + esc(doc.document_name) + '" title="' + esc(L('Approve')) + '" aria-label="' + esc(L('Approve')) + '"><i class="fa fa-check" aria-hidden="true"></i></button>';
        }
        if (perms.canDelete) {
            // No data-version: this is the row's PRIMARY delete action --
            // the whole document, every version, the works
            // (delete_document()'s $version=null branch, includes/
            // governance.php). It used to carry the document's current
            // file_version here, which made delete_document($id, $version)
            // take the SINGLE-VERSION branch instead: the compliance_files
            // row for the current version silently disappeared while the
            // documents row itself (and its now-dangling file_id) survived
            // -- "Delete" looked like it worked but never actually removed
            // the document. Deleting one specific historical version now
            // has its own, correctly-scoped action in the version-history
            // row expander (toggleVersionHistory()) instead.
            html += '<button type="button" class="sr-row-action sr-row-action-danger document--delete" data-id="' + esc(doc.id) + '" data-type="' + esc(doc.document_type || '') + '" data-bs-toggle="modal" data-bs-target="#document-delete-modal" title="' + esc(L('Delete')) + '" aria-label="' + esc(L('Delete')) + '"><i class="fa fa-trash" aria-hidden="true"></i></button>';
        }
        html += '</span></span>';
        return html;
    }

    function rowHtml(doc) {
        var statusMeta = DOCUMENT_STATUS_META[doc.document_status_id] || DOCUMENT_STATUS_META[1];
        var typeLabel = categoryLabel(doc.document_type);
        // Indent a child document under its parent (see flattenDocumentTree()).
        var nameStyle = doc._depth ? ' style="padding-left:' + (14 + doc._depth * 18) + 'px"' : '';

        // SR-180: framework names on their own -- linked controls moved into
        // their own optional Controls column (below) rather than doubling up
        // in this cell too.
        var frameworkCellHtml = doc.framework_names ? esc(doc.framework_names) : '<span class="sr-cell-dash">&mdash;</span>';
        var controlLinksMarkup = controlLinksHtml(doc);
        var controlsCellHtml = controlLinksMarkup || '<span class="sr-cell-dash">&mdash;</span>';

        var submitterText = doc.submitted_by ? esc(doc.submitted_by) : '<span class="sr-cell-dash">&mdash;</span>';
        var approvalDateText = (doc.approval_date && doc.approval_date !== '0000-00-00')
            ? esc(doc.approval_date) : '<span class="sr-cell-dash">&mdash;</span>';
        var updatedByText = doc.updated_by ? esc(doc.updated_by) : '<span class="sr-cell-dash">&mdash;</span>';
        var creationDateText = (doc.creation_date && doc.creation_date !== '0000-00-00')
            ? esc(doc.creation_date) : '<span class="sr-cell-dash">&mdash;</span>';

        var lastReviewText = (doc.last_review_date && doc.last_review_date !== '0000-00-00')
            ? esc(doc.last_review_date) : '<span class="sr-cell-dash">&mdash;</span>';

        return '<tr class="sr-doc-row" data-id="' + esc(doc.id) + '" aria-expanded="false">' +
            '<td class="sr-check-col">' + ((perms.canApprove || perms.canDelete || perms.canView) ? '<input type="checkbox" class="form-check-input sr-row-check" data-id="' + esc(doc.id) + '" aria-label="' + esc(L('Select')) + '">' : '') + '</td>' +
            // Caret + name share one cell (Define Control Frameworks'
            // governance-frameworks.js own layout: the caret sits directly
            // in front of the record's name, not in a separate leading
            // column of its own). Wrapped in .sr-doc-namecell, NOT the
            // shipped .sr-group-main -- that flex row is `flex-wrap: wrap`,
            // built for Define Tests' multi-chip group row (caret + number
            // chip + name + framework-count chip) where wrapping the whole
            // cluster as a unit is the point. With only two children here,
            // the same wrap rule sent a merely-long document name to a
            // second line under the caret instead of just letting the text
            // itself wrap/truncate in place -- .sr-doc-namecell is the same
            // align-items:center + gap shape with nowrap instead.
            // sr-doc-name-col/sr-doc-status-col: no data-col (these two are
            // permanently visible, never part of TOGGLE_COLUMNS), but the
            // queue tier (_governance.scss, design-system.md 6b) needs a
            // stable hook to grid-place them -- the name cell's own inline
            // nameStyle indent must survive alongside the new class.
            '<td class="sr-doc-name-col"' + nameStyle + '><span class="sr-doc-namecell"><button type="button" class="sr-group-caret" aria-expanded="false" aria-label="' + esc(L('VersionHistory')) + '"><i class="fa fa-chevron-right" aria-hidden="true"></i></button><span>' + esc(doc.document_name) + '</span></span></td>' +
            '<td data-col="document_type">' + esc(typeLabel) + '</td>' +
            '<td data-col="frameworks">' + frameworkCellHtml + '</td>' +
            '<td data-col="controls">' + controlsCellHtml + '</td>' +
            '<td class="sr-doc-status-col"><span class="sr-state-pill ' + statusMeta.pillClass + '">' + esc(L(statusMeta.labelKey)) + '</span></td>' +
            '<td data-col="submitter">' + submitterText + '</td>' +
            '<td data-col="approver">' + (doc.approver_name ? esc(doc.approver_name) : '<span class="sr-cell-dash">&mdash;</span>') + '</td>' +
            '<td data-col="approval_date">' + approvalDateText + '</td>' +
            '<td data-col="updated_by">' + updatedByText + '</td>' +
            '<td data-col="creation_date">' + creationDateText + '</td>' +
            '<td data-col="last_review">' + lastReviewText + '</td>' +
            '<td data-col="next_review">' + reviewChipHtml(doc) + '</td>' +
            '<td class="sr-actions-col">' + rowActionsHtml(doc) + '</td>' +
        '</tr>';
    }

    function theadHtml() {
        return '<tr>' +
            '<th class="sr-check-col sr-no-sort">' + ((perms.canApprove || perms.canDelete || perms.canView) ? '<input type="checkbox" class="form-check-input" id="document-program-select-all" aria-label="' + esc(L('SelectAll')) + '">' : '') + '</th>' +
            '<th>' + esc(L('DocumentName')) + '</th>' +
            '<th data-col="document_type">' + esc(L('DocumentType')) + '</th>' +
            '<th data-col="frameworks">' + esc(L('ControlFrameworks')) + '</th>' +
            '<th data-col="controls">' + esc(L('Controls')) + '</th>' +
            '<th>' + esc(L('Status')) + '</th>' +
            '<th data-col="submitter">' + esc(L('Submitter')) + '</th>' +
            '<th data-col="approver">' + esc(L('Approver')) + '</th>' +
            '<th data-col="approval_date" class="sr-col-date">' + esc(L('ApprovalDate')) + '</th>' +
            '<th data-col="updated_by">' + esc(L('UpdatedBy')) + '</th>' +
            '<th data-col="creation_date" class="sr-col-date">' + esc(L('CreationDate')) + '</th>' +
            '<th data-col="last_review" class="sr-col-date">' + esc(L('LastReviewDate')) + '</th>' +
            '<th data-col="next_review">' + esc(L('NextReviewDate')) + '</th>' +
            '<th class="sr-actions-col sr-no-sort"><span class="visually-hidden">' + esc(L('Actions')) + '</span></th>' +
        '</tr>';
    }

    // Move DataTables' own generated search box up into the toolbar tools
    // (left of the Add button), the same relocation self-assessment.js's
    // relocateSearchIntoTools() performs -- moving the node keeps its event
    // bindings, so no separate wiring is needed.
    function relocateSearchIntoTools() {
        var $filter = $('#document-program-body .dt-search, #document-program-body .dataTables_filter').first();
        var $tools = $('#document-program-toolbar .sr-table-tools');
        if ($filter.length && $tools.length) {
            // renderBody() replaces #document-program-body -- and therefore
            // DataTables itself -- on every filter change, approve/delete,
            // etc., so this runs again on every one of those too. The BOX
            // this moves lives outside #document-program-body once relocated
            // here, so it survives that replacement untouched; without
            // clearing it first, each re-render prepended a second, third,
            // fourth... search box into the toolbar instead of replacing the
            // one already there.
            $tools.find('.dt-search, .dataTables_filter').remove();
            $filter.find('input[type="search"]').attr('placeholder', L('SearchDocumentsPlaceholder')).attr('aria-label', L('SearchDocumentsPlaceholder'));
            $tools.prepend($filter);
        }
    }

    function showEmptyState(kind) {
        $('#document-program-empty-nodata, #document-program-empty-noresults, #document-program-empty-error').addClass('d-none');
        if (kind) {
            $('#document-program-empty-' + kind).removeClass('d-none');
        }
    }

    function updateBulkBar() {
        var $bar = $('#document-program-bulk-bar');
        if (!$bar.length) {
            return;
        }
        var n = Object.keys(selectedIds).length;
        var $toolbar = $('#document-program-toolbar');
        if (n > 0) {
            $('#document-program-bulk-count').text(String(L('NSelected')).replace('{n}', n));
            $bar.removeClass('d-none');
            $toolbar.hide();
        } else {
            $bar.addClass('d-none');
            $toolbar.show();
        }
    }

    function syncCheckboxes() {
        $('#document-program-table .sr-row-check').each(function () {
            this.checked = !!selectedIds[$(this).data('id')];
        });
        var $all = $('#document-program-select-all');
        if ($all.length) {
            var $visible = $('#document-program-table tbody tr:visible .sr-row-check');
            var total = $visible.length;
            var checked = $visible.filter(function () { return this.checked; }).length;
            $all.prop('checked', total > 0 && checked === total);
            $all.prop('indeterminate', checked > 0 && checked < total);
        }
    }

    // Swaps DataTables' own bundled up/down-caret sort indicator
    // (.dt-column-order's :before/:after pseudo-elements) for the same
    // FontAwesome 'fa-sort'/'fa-arrow-up-short-wide'/'fa-arrow-down-wide-short'
    // glyph Compliance's tables use (compliance-initiate-audits.js's
    // syncSortIcons(), compliance.php's syncAuditsSortIcons()), so this grid
    // reads the same sort affordance. 'sr-sort-native-off' is what
    // _tables.scss keys the native-caret suppression off (a class on the
    // element itself, not an ID-scoped ancestor selector) -- this table has
    // no scrollX header clone to worry about, unlike Manage Audits/Initiate
    // Audits, so there is only ever one header to sync.
    function syncSortIcons() {
        $('#document-program-table thead th[data-dt-column]').each(function () {
            var $th = $(this);
            if (!$th.is('.dt-orderable-asc, .dt-orderable-desc')) { return; }
            var $order = $th.find('.dt-column-order').addClass('sr-sort-native-off');
            if (!$order.length) { return; }
            $th.addClass('sr-sortable');
            var $icon = $order.find('.sr-sort-icon');
            if (!$icon.length) {
                $icon = $('<i>', { 'class': 'fa sr-sort-icon', 'aria-hidden': 'true' }).appendTo($order);
            }
            var sort = $th.attr('aria-sort');
            $th.toggleClass('is-sorted', sort === 'ascending' || sort === 'descending');
            $icon
                .removeClass('fa-arrow-up-short-wide fa-arrow-down-wide-short fa-sort')
                .addClass(sort === 'ascending' ? 'fa-arrow-up-short-wide' : sort === 'descending' ? 'fa-arrow-down-wide-short' : 'fa-sort');
        });
    }

    function wireSelection(tableEl) {
        selectedIds = {};
        updateBulkBar();
        $(tableEl).on('change', '.sr-row-check', function () {
            var id = $(this).data('id');
            if (this.checked) {
                selectedIds[id] = true;
            } else {
                delete selectedIds[id];
            }
            syncCheckboxes();
            updateBulkBar();
        });
    }

    // Builds the whole card body (table + empty states) fresh, then hands the
    // fresh <table> node to a classic client-side DataTable for sort/search/
    // paging chrome. columnDefs disables ordering on the select and actions
    // columns only -- every data column sorts (design-system.md §6). `rows`
    // is already flat and already filtered (see applyFiltersAndRender()) --
    // this function only ever renders what should be visible.
    function renderBody(rows) {
        var html =
            '<div class="sr-table-scroll">' +
                '<table class="sr-table" id="document-program-table">' +
                    '<thead>' + theadHtml() + '</thead>' +
                    '<tbody>' + rows.map(rowHtml).join('') + '</tbody>' +
                '</table>' +
            '</div>' +
            '<div class="sr-table-empty d-none" id="document-program-empty-nodata">' +
                '<div class="sr-table-empty-icon"><i class="fa fa-file-lines" aria-hidden="true"></i></div>' +
                '<div class="sr-table-empty-title">' + esc(L('NoDocumentsYet')) + '</div>' +
                '<div class="sr-table-empty-body">' + esc(L('NoDocumentsYetBody')) + '</div>' +
            '</div>' +
            '<div class="sr-table-empty d-none" id="document-program-empty-noresults">' +
                '<div class="sr-table-empty-icon"><i class="fa fa-search" aria-hidden="true"></i></div>' +
                '<div class="sr-table-empty-title">' + esc(L('NoDocumentsMatchFilters')) + '</div>' +
                '<div class="sr-table-empty-body">' + esc(L('NoDocumentsMatchFiltersBody')) + '</div>' +
                '<div class="sr-table-empty-action"><button type="button" class="btn btn-outline-secondary btn-sm" id="document-program-clear-search">' + esc(L('ClearFilters')) + '</button></div>' +
            '</div>';

        $('#document-program-body').html(html);

        var $count = $('#document-program-count');
        if (rows.length > 0) {
            $count.text(rows.length).removeClass('d-none');
        } else {
            $count.addClass('d-none');
        }

        dt = null;
        if (!rows.length) {
            showEmptyState('nodata');
            $('#document-program-bulk-bar').addClass('d-none');
            $('#document-program-toolbar').show();
            return;
        }
        showEmptyState(null);

        if (!$.fn.DataTable) {
            return;
        }
        var tableEl = document.getElementById('document-program-table');
        dt = $(tableEl).DataTable({
            serverSide: false,
            processing: false,
            // DataTables' default autoWidth computes+caches each column's
            // pixel width ONCE at init, based on whatever columns are
            // visible at that moment -- it has no idea the compact/queue
            // responsive tiers (scss/modules/_governance.scss) exist and
            // never recalculates when they hide a column via CSS on
            // browser resize, so the table stayed pinned at its original
            // (all-columns) width with a blank gap where the hidden ones
            // used to be. false lets the browser's own native table-layout
            // handle it instead, which reflows naturally with the DOM/CSS
            // on every resize like any other table.
            autoWidth: false,
            // Footer left group (page-size + row-count together) / right group
            // (pager alone) per design-system.md §6's documented .sr-table-foot
            // split; the search box ('f') renders outside it and is relocated
            // into the toolbar immediately below.
            dom: 'rt<"sr-table-foot"<"sr-table-foot-left"li><"sr-table-foot-right"p>>f',
            pagingType: 'simple_numbers',
            pageLength: 25,
            // Matches Compliance's page-size pill wording ("Show 25") --
            // DataTables' own built-in default is '_MENU_ _ENTRIES_ per page',
            // which renders as a second, separate label inside the same
            // .dt-length pill and widens it unnecessarily.
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, L('All')]],
            // Next Review Date, ascending -- this ONE target has to stay a
            // numeric column index (DataTables' `order` option, unlike
            // columnDefs.targets below, doesn't accept a class selector).
            // Currently column 12 (check, name (with the caret embedded in
            // it, not its own column), type, frameworks, controls, status,
            // submitter, approver, approval_date, updated_by, creation_date,
            // last_review, THEN next_review). Update this if a column is
            // ever added/removed/reordered ahead of it in theadHtml()/
            // rowHtml().
            order: [[12, 'asc']],
            columnDefs: [
                // Class-based targeting (not column-index arrays): every
                // earlier version of this config used numeric `targets`,
                // and adding one optional column (Approval Date, Controls,
                // Submitter, Updated By, Creation Date, the caret column --
                // six separate times across one session) meant recomputing
                // every index by hand each time, which is exactly the kind
                // of silent-drift bug class this file's own Last Review
                // Date fix (below) exists to prevent. `sr-no-sort`/
                // `sr-col-date` on the <th>s in theadHtml() are the only
                // thing these rules need to stay in sync with.
                { orderable: false, targets: '.sr-no-sort' },
                // Approval Date, Creation Date and Last Review Date render
                // as plain date strings, unlike every other date-ish column
                // here (Next Review Date wraps its date in a .sr-date-chip
                // span) -- DataTables' own type auto-detection reads bare
                // text as dt-type-date and applies its built-in right-align
                // + reversed header (icon before the label instead of
                // after), which looked broken sitting next to every
                // left-aligned, icon-after column. Forcing 'string' keeps
                // all three visually consistent with their siblings instead
                // of introducing DataTables' date styling for just these.
                { type: 'string', targets: '.sr-col-date' }
            ],
            // Seeds the search box from ?search= (a bookmarked/shared link
            // lands already filtered) -- see the search.dt handler below,
            // which keeps the URL in sync as the user types.
            search: { search: new URLSearchParams(window.location.search).get('search') || '' },
            language: {
                paginate: { previous: L('Previous'), next: L('Next') },
                lengthMenu: L('Show') + ' _MENU_'
            }
        });
        relocateSearchIntoTools();

        dt.on('draw', function () {
            showEmptyState(dt.rows({ search: 'applied' }).count() === 0 ? 'noresults' : null);
            syncCheckboxes();
            syncSortIcons();
        });
        // Keeps the current search shareable/bookmarkable. history.replaceState
        // (not pushState) -- typing in the search box refines the same view,
        // not a new page in browser history.
        dt.on('search.dt', function () {
            if (!window.history.replaceState) {
                return;
            }
            var params = new URLSearchParams(window.location.search);
            var term = dt.search();
            if (term) { params.set('search', term); } else { params.delete('search'); }
            var query = params.toString();
            window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
        });
        syncSortIcons();

        wireSelection(tableEl);
        applyColumnVisibility();
    }

    // Applies the current columnVisible state to the rendered table's
    // th/td[data-col] cells (design-system.md §6c). Re-run after every full
    // re-render since there's no DOM diff to preserve visibility through.
    function applyColumnVisibility() {
        TOGGLE_COLUMNS.forEach(function (col) {
            $('#document-program-table [data-col="' + col + '"]').toggle(!!columnVisible[col]);
        });
    }

    function renderColpanel() {
        var html = TOGGLE_COLUMNS.map(function (col) {
            return '<label class="colpanel-item"><input type="checkbox" data-col="' + esc(col) + '"' + (columnVisible[col] ? ' checked' : '') + '> ' + esc(columnLabel(col)) + '</label>';
        }).join('');
        $('#document-program-colpanel').html(html);
    }

    function renderError() {
        $('#document-program-body').html(
            '<div class="sr-table-empty sr-table-empty-danger" id="document-program-empty-error">' +
                '<div class="sr-table-empty-icon"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i></div>' +
                '<div class="sr-table-empty-title">' + esc(L('CouldNotLoadDocuments')) + '</div>' +
                '<div class="sr-table-empty-body">' + esc(L('CouldNotLoadDocumentsBody')) + '</div>' +
                '<div class="sr-table-empty-action"><button type="button" class="btn btn-outline-secondary btn-sm" id="document-program-retry">' + esc(L('Retry')) + '</button></div>' +
            '</div>'
        );
        $('#document-program-count').addClass('d-none');
        $('#document-program-bulk-bar').addClass('d-none');
        $('#document-program-toolbar').show();
        dt = null;
    }

    // Always fetches every document -- filtering (type/framework/status/
    // approver/next-review, plus DataTables' own text search) is entirely
    // client-side now (see the filters/allRows declarations above), so the
    // grid only ever needs the one unfiltered fetch per load/reload.
    function fetchAndRender() {
        $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/governance/documents/treegrid?type=',
            dataType: 'json',
            success: function (response) {
                allRows = flattenDocumentTree(response || [], 0);
                // applyFiltersAndRender() calls renderFilterOptions() itself
                // (see its own comment) -- a separate call here would just
                // build the same filter options twice on every load.
                applyFiltersAndRender();
            },
            error: function (xhr, status, error) {
                if (!retryCSRF(xhr, this)) {
                    renderError();
                }
            }
        });
    }

    // A document passes when it matches every ACTIVE facet (AND across
    // facets, OR within a multi-select facet's own chosen values) -- the
    // same semantics activeFilterCount()/filtersActive() document for
    // compliance-define-tests.js's filter row.
    //
    // `except` skips one facet's own clause -- the faceted-count machinery
    // below needs "does this doc match every filter EXCEPT the one whose
    // options I'm currently counting", so a document with 0 remaining
    // matches under every OTHER active filter still counts toward that
    // facet's own options (an Approver select's roster describes every
    // approver, so the count for a facet has to be computed with that
    // facet's own clause held out, not applied twice).
    function rowPassesFilters(doc, except) {
        if (except !== 'type' && filters.type.length && filters.type.indexOf(doc.document_type) === -1) {
            return false;
        }
        if (except !== 'framework' && filters.framework.length) {
            var names = String(doc.framework_names || '').split(',').map(function (s) { return s.trim(); });
            var matches = filters.framework.some(function (fw) { return names.indexOf(fw) !== -1; });
            if (!matches) {
                return false;
            }
        }
        if (except !== 'status' && filters.status && String(doc.document_status_id) !== filters.status) {
            return false;
        }
        if (except !== 'approver' && filters.approver && (doc.approver_name || '') !== filters.approver) {
            return false;
        }
        if (except !== 'review' && filters.review) {
            var hasDate = doc.next_review_date && doc.next_review_date !== '0000-00-00';
            if (filters.review === 'none') {
                if (hasDate) { return false; }
            } else if (!hasDate || (doc.next_review_status || 'ok') !== filters.review) {
                return false;
            }
        }
        return true;
    }

    // Counts documents matching every facet except `except` (that facet's
    // own clause is held out so its own options can still be counted --
    // see rowPassesFilters()'s comment) into the same five shapes
    // renderFilterOptions() renders every select from: called once per
    // facet, each with a different `except`, so Type/Framework/Approver/
    // Status/Review each get counted against "every filter but my own".
    function computeFacetCounts(rows, except) {
        var typeCounts = {}, frameworkCounts = {}, approverCounts = {};
        var statusCounts = { 1: 0, 2: 0, 3: 0 };
        var reviewCounts = { overdue: 0, due_soon: 0, ok: 0, none: 0 };

        // Zero-seed Type/Framework/Approver from the FULL roster (unfiltered
        // `rows`) before the filtered pass below increments them -- without
        // this, a value with zero matches under the current filters is
        // simply absent from the map, renderFilterOptions() can't tell "0
        // documents" apart from "not part of the dataset at all", and a
        // value that should read as a legitimate 0 (and be droppable/kept
        // per its own selected-state rule) would just never appear in the
        // roster to begin with. Status/Review are fixed enums and were
        // already zero-init'd above for the same reason.
        rows.forEach(function (doc) {
            if (doc.document_type) { typeCounts[doc.document_type] = typeCounts[doc.document_type] || 0; }
            String(doc.framework_names || '').split(',').forEach(function (name) {
                name = name.trim();
                if (name) { frameworkCounts[name] = frameworkCounts[name] || 0; }
            });
            if (doc.approver_name) { approverCounts[doc.approver_name] = approverCounts[doc.approver_name] || 0; }
        });

        rows.filter(function (doc) { return rowPassesFilters(doc, except); }).forEach(function (doc) {
            if (doc.document_type) { typeCounts[doc.document_type] = (typeCounts[doc.document_type] || 0) + 1; }
            String(doc.framework_names || '').split(',').forEach(function (name) {
                name = name.trim();
                if (name) { frameworkCounts[name] = (frameworkCounts[name] || 0) + 1; }
            });
            if (doc.approver_name) { approverCounts[doc.approver_name] = (approverCounts[doc.approver_name] || 0) + 1; }
            statusCounts[doc.document_status_id] = (statusCounts[doc.document_status_id] || 0) + 1;
            var hasDate = doc.next_review_date && doc.next_review_date !== '0000-00-00';
            reviewCounts[hasDate ? (doc.next_review_status || 'ok') : 'none']++;
        });
        return { typeCounts: typeCounts, frameworkCounts: frameworkCounts, approverCounts: approverCounts, statusCounts: statusCounts, reviewCounts: reviewCounts };
    }

    function applyFiltersAndRender() {
        renderBody(allRows.filter(function (doc) { return rowPassesFilters(doc, null); }));
        renderFilterOptions();
        syncFilterCount();
    }

    // Rebuilds every filter <option> list from the CURRENT filter
    // combination, each facet counted with its own clause excluded (per
    // computeFacetCounts()) -- so picking Status=In Review immediately
    // drops an Approver who has no in-review documents from that select
    // entirely, rather than leaving him listed with his global (all-
    // statuses) count standing as if he were still a live narrowing choice.
    // Run on every filter change AND the initial fetch (both funnel through
    // applyFiltersAndRender()) -- unlike a roster that's only supposed to
    // describe "what values exist in the dataset" (which would stay fixed
    // across a filter change), what's WORTH SHOWING as a next click is
    // itself a function of what's already picked, so it has to be rebuilt
    // every time that changes, the same as the grid rows themselves are.
    //
    // A value already part of the CURRENT selection for its own facet is
    // always kept regardless of its count (`isSelected` below) -- computing
    // Approver's own options already excludes Approver's own filter clause,
    // so this only matters for the pathological case of a selection that's
    // become unreachable under the OTHER filters; keeping it visible (with
    // its honest "0") lets the viewer see and clear it rather than have it
    // silently vanish out of a select still reporting it as chosen.
    function renderFilterOptions() {
        var typeCounts = computeFacetCounts(allRows, 'type').typeCounts;
        var frameworkCounts = computeFacetCounts(allRows, 'framework').frameworkCounts;
        var approverCounts = computeFacetCounts(allRows, 'approver').approverCounts;
        var statusCounts = computeFacetCounts(allRows, 'status').statusCounts;
        var reviewCounts = computeFacetCounts(allRows, 'review').reviewCounts;

        // Document Type: the four seeded slugs first (fixed, translated
        // order), then any custom types actually present -- mirrors the
        // insights band's own seeded-vs-custom split. computeFacetCounts()
        // zero-seeds every slug that exists ANYWHERE in allRows (not just
        // under the current filters), so hasOwnProperty here still reflects
        // the whole dataset's roster even though the VALUES are faceted.
        var isTypeSelected = function (slug) { return filters.type.indexOf(slug) !== -1; };
        var typeSlugs = Object.keys(DOCUMENT_TYPE_LABEL_KEYS).filter(function (slug) { return typeCounts.hasOwnProperty(slug); });
        Object.keys(typeCounts).forEach(function (slug) {
            if (typeSlugs.indexOf(slug) === -1) { typeSlugs.push(slug); }
        });
        typeSlugs = typeSlugs.filter(function (slug) { return typeCounts[slug] > 0 || isTypeSelected(slug); });
        // "All types"/"All frameworks" (value="") is a real, clickable
        // option -- selected whenever nothing else is (readFiltersFromControls()
        // always treats "" as meaning no filter, so an untouched select and
        // one with only "" checked are the same state). See
        // normalizeMultiValue() for how picking it clears every other
        // checked box (and vice versa).
        var $typeFilter = $('#document-program-type-filter');
        $typeFilter.html(
            '<option value=""' + (filters.type.length === 0 ? ' selected' : '') + '>' + esc(L('AllTypes')) + '</option>' +
            typeSlugs.map(function (slug) {
                return '<option value="' + esc(slug) + '"' + (isTypeSelected(slug) ? ' selected' : '') + ' data-count="' + typeCounts[slug] + '">' + esc(categoryLabel(slug)) + '</option>';
            }).join('')
        );

        var isFrameworkSelected = function (name) { return filters.framework.indexOf(name) !== -1; };
        var frameworkNames = Object.keys(frameworkCounts)
            .filter(function (name) { return frameworkCounts[name] > 0 || isFrameworkSelected(name); })
            .sort();
        var $frameworkFilter = $('#document-program-framework-filter');
        $frameworkFilter.html(
            '<option value=""' + (filters.framework.length === 0 ? ' selected' : '') + '>' + esc(L('AllFrameworks')) + '</option>' +
            frameworkNames.map(function (name) {
                return '<option value="' + esc(name) + '"' + (isFrameworkSelected(name) ? ' selected' : '') + ' data-count="' + frameworkCounts[name] + '">' + esc(name) + '</option>';
            }).join('')
        );

        var approverNames = Object.keys(approverCounts)
            .filter(function (name) { return approverCounts[name] > 0 || name === filters.approver; })
            .sort();
        var $approverFilter = $('#document-program-approver-filter');
        $approverFilter.html(
            '<option value="">' + esc(L('AllApprovers')) + '</option>' +
            approverNames.map(function (name) {
                return '<option value="' + esc(name) + '" data-count="' + approverCounts[name] + '">' + esc(name) + '</option>';
            }).join('')
        );
        $approverFilter.val(filters.approver);

        // Status/Review: a zero-count value is dropped from the list
        // entirely rather than shown with a "0" chip -- same treatment as
        // Type/Framework/Approver above, unless it's the facet's own active
        // selection (see the function-level comment).
        var $statusFilter = $('#document-program-status-filter');
        $statusFilter.html(
            '<option value=""' + (filters.status === '' ? ' selected' : '') + '>' + esc(L('AllStatuses')) + '</option>' +
            [1, 2, 3].filter(function (id) { return statusCounts[id] > 0 || filters.status === String(id); }).map(function (id) {
                return '<option value="' + id + '"' + (filters.status === String(id) ? ' selected' : '') + ' data-count="' + statusCounts[id] + '">' + esc(L(DOCUMENT_STATUS_META[id].labelKey)) + '</option>';
            }).join('')
        );

        var $reviewFilter = $('#document-program-review-filter');
        var reviewOptions = [
            { value: 'overdue', labelKey: 'Overdue' },
            { value: 'due_soon', labelKey: 'DueSoon' },
            { value: 'ok', labelKey: 'OnTrack' },
            { value: 'none', labelKey: 'NotScheduled' }
        ];
        $reviewFilter.html(
            '<option value=""' + (filters.review === '' ? ' selected' : '') + '>' + esc(L('NextReviewDate')) + '</option>' +
            reviewOptions.filter(function (opt) { return reviewCounts[opt.value] > 0 || filters.review === opt.value; }).map(function (opt) {
                return '<option value="' + opt.value + '"' + (filters.review === opt.value ? ' selected' : '') + ' data-count="' + reviewCounts[opt.value] + '">' + esc(L(opt.labelKey)) + '</option>';
            }).join('')
        );

        if (window.srSelectRender) {
            window.srSelectRender($typeFilter);
            window.srSelectRender($frameworkFilter);
            window.srSelectRender($approverFilter);
            window.srSelectRender($statusFilter);
            window.srSelectRender($reviewFilter);
        }
    }

    function activeFilterCount() {
        var n = 0;
        if (filters.type.length) { n++; }
        if (filters.framework.length) { n++; }
        if (filters.status) { n++; }
        if (filters.approver) { n++; }
        if (filters.review) { n++; }
        return n;
    }

    function syncFilterCount() {
        var count = activeFilterCount();
        $('#document-program-filters-count').text(count ? String(count) : '').prop('hidden', !count);
        $('#document-program-filters-toggle').toggleClass('has-filters', count > 0);
        // The multi-selects (Type/Framework) have no clickable "All X" row of
        // their own -- a multi-select's options are all plain toggles, so an
        // "All types" entry among them would read as just another type to
        // pick, not a clear action (same reason compliance-define-tests.js's
        // own framework/family multi-selects don't have one either). This
        // button is the actual clear affordance, shown only once a filter is
        // actually narrowing the grid.
        $('#document-program-filters-clear').toggleClass('d-none', count === 0);
    }

    // Type/Framework's own last-known effective selection (never contains
    // "" -- see normalizeMultiValue() below), so a later change on that same
    // select can tell "All was just turned on" apart from "something else
    // was just turned on while All was already checked". Kept outside
    // `filters` because `filters.type`/`.framework` themselves never hold
    // "" either -- these exist purely to remember what changed.
    var previousTypeValue = [];
    var previousFrameworkValue = [];

    // Reconciles a multi-select's raw .val() (which can hold "" -- the "All
    // ..." option -- alongside real values, something sr-select.js's own
    // per-option toggle has no concept of clearing) into the actual filter
    // value, and corrects the <select>'s own selected state + the sr-select
    // skin to visibly match:
    //   - "All" just turned on (wasn't in `previous`) -> it wins alone,
    //     clearing every other checked box (picking "All" IS "start over").
    //   - anything else (a specific value just turned on/off while "All"
    //     was already checked, or a normal pick with "All" never involved)
    //     -> "All" is dropped/never applied; the specific picks stand.
    //   - nothing left checked either way -> falls back to "All" alone,
    //     since an empty multi-select and "All" explicitly checked are the
    //     same filter state and should look the same too.
    // Racing sr-select.js's own click handler to intercept the "All" row's
    // click itself doesn't work: that handler runs first (it's bound
    // directly on the popup, not delegated from document) and rebuilds
    // every option row via srSelectRender() as part of its own toggle,
    // detaching the very node a document-level delegated click handler
    // would need to match against. Reading the settled .val() from the
    // 'change' event this function is called from sidesteps that race
    // entirely -- 'change' targets the native <select> itself, which is
    // never rebuilt.
    function normalizeMultiValue($select, previous) {
        var raw = $select.val() || [];
        var hasAll = raw.indexOf('') !== -1;
        var hadAll = previous.indexOf('') !== -1;
        var result;
        if (hasAll && !hadAll) {
            result = [''];
        } else {
            result = raw.filter(function (v) { return v !== ''; });
            if (!result.length) {
                result = [''];
            }
        }
        var changed = false;
        $select.find('option').each(function () {
            var shouldBeSelected = result.indexOf(this.value) !== -1;
            if (this.selected !== shouldBeSelected) {
                this.selected = shouldBeSelected;
                changed = true;
            }
        });
        if (changed && window.srSelectRender) {
            window.srSelectRender($select);
        }
        return result;
    }

    // Pulls the current <select> values into `filters`. Single-selects come
    // back from jQuery's .val() as a plain string; Status/Approver/Review's
    // own neutral "All ..." option is already value="", so no stripping is
    // needed there the way Type/Framework need normalizeMultiValue().
    function readFiltersFromControls() {
        previousTypeValue = normalizeMultiValue($('#document-program-type-filter'), previousTypeValue);
        previousFrameworkValue = normalizeMultiValue($('#document-program-framework-filter'), previousFrameworkValue);
        filters.type = previousTypeValue.filter(function (v) { return v !== ''; });
        filters.framework = previousFrameworkValue.filter(function (v) { return v !== ''; });
        filters.status = $('#document-program-status-filter').val() || '';
        filters.approver = $('#document-program-approver-filter').val() || '';
        filters.review = $('#document-program-review-filter').val() || '';
    }

    // Resets every facet back to "all" and re-syncs the <select> controls
    // (native value + the sr-select skin) to match -- mirrors
    // compliance-define-tests.js's #define-tests-clear-filters exactly,
    // just scoped to this page's five facets instead of nine.
    function resetFilters() {
        filters = { type: [], framework: [], status: '', approver: '', review: '' };
        previousTypeValue = [''];
        previousFrameworkValue = [''];
        // Type/Framework select their own "All ..." option (value="")
        // rather than an empty selection -- an untouched select and one
        // with only "All" checked are the same filter state, but should
        // look the same too.
        $('#document-program-type-filter').val(['']);
        $('#document-program-framework-filter').val(['']);
        $('#document-program-status-filter').val('');
        $('#document-program-approver-filter').val('');
        $('#document-program-review-filter').val('');
        if (window.srSelectRender) {
            ['#document-program-type-filter', '#document-program-framework-filter', '#document-program-status-filter', '#document-program-approver-filter', '#document-program-review-filter']
                .forEach(function (id) { window.srSelectRender($(id)); });
        }
    }

    // Applies ?type=/?framework=/?status=/?approver=/?review= (comma-lists
    // for the multi-selects) to `filters` and the <select> controls
    // BEFORE the first fetch -- an insights-band KPI tile link
    // (documentation.php?type=<slug>) or any bookmarked/shared filtered
    // link lands pre-filtered on the very first render. Unknown/malformed
    // values are dropped rather than guessed at.
    function readFiltersFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var type = params.get('type');
        if (type) { filters.type = type.split(',').filter(Boolean); }
        var framework = params.get('framework');
        if (framework) { filters.framework = framework.split(',').filter(Boolean); }
        var status = params.get('status');
        if (status && ['1', '2', '3'].indexOf(status) !== -1) { filters.status = status; }
        var approver = params.get('approver');
        if (approver) { filters.approver = approver; }
        var review = params.get('review');
        if (review && ['overdue', 'due_soon', 'ok', 'none'].indexOf(review) !== -1) { filters.review = review; }

        $('#document-program-status-filter').val(filters.status);
        $('#document-program-review-filter').val(filters.review);
        // type/framework/approver <option> lists don't exist yet at this
        // point (they're built from allRows in renderFilterOptions(), which
        // reads filters.type/.framework/.approver back out to mark the
        // right <option>s selected) -- nothing more to sync here, other
        // than seeding normalizeMultiValue()'s own "previous" memory so the
        // FIRST change on either select compares against the URL's initial
        // state rather than an assumed empty one.
        previousTypeValue = filters.type.length ? filters.type.slice() : [''];
        previousFrameworkValue = filters.framework.length ? filters.framework.slice() : [''];
    }

    // Keeps the current filter state shareable/bookmarkable. history.
    // replaceState (not pushState) -- a filter change is a refinement of
    // the same view, not a new page in browser history.
    function writeFiltersToUrl() {
        if (!window.history.replaceState) {
            return;
        }
        // Starts from the CURRENT URL (not a blank one) and touches only
        // this function's own keys, so it can never clobber ?search=, which
        // the DataTables search.dt handler (see below) keeps in sync
        // independently -- whichever of the two fires last must not erase
        // the other's contribution.
        var params = new URLSearchParams(window.location.search);
        ['type', 'framework', 'status', 'approver', 'review'].forEach(function (key) { params.delete(key); });
        if (filters.type.length) { params.set('type', filters.type.join(',')); }
        if (filters.framework.length) { params.set('framework', filters.framework.join(',')); }
        if (filters.status) { params.set('status', filters.status); }
        if (filters.approver) { params.set('approver', filters.approver); }
        if (filters.review) { params.set('review', filters.review); }
        var query = params.toString();
        window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
    }

    function load() {
        fetchAndRender();
    }

    function reload() {
        fetchAndRender();
    }

    function openApproveConfirm(mode, ids, name) {
        if (!ids || !ids.length) {
            return;
        }
        approveModalMode = mode;
        approveModalIds = ids;
        var title, body, primaryLabel, iconClass;
        if (mode === 'unapprove') {
            title = L('UnapproveDocumentQuestion');
            body = String(L('UnapproveDocumentConfirmBody')).replace('{name}', name || '');
            primaryLabel = L('Unapprove');
            iconClass = 'fa fa-rotate-left';
        } else if (mode === 'batch-approve') {
            title = String(L('ApproveSelectedDocumentsQuestion')).replace('{n}', ids.length);
            body = String(L('ApproveSelectedDocumentsConfirmBody')).replace('{n}', ids.length);
            primaryLabel = L('ApproveSelected');
            iconClass = 'fa fa-check-double';
        } else {
            title = L('ApproveDocumentQuestion');
            body = String(L('ApproveDocumentConfirmBody')).replace('{name}', name || '');
            primaryLabel = L('Approve');
            iconClass = 'fa fa-check';
        }
        $('#document-approve-confirm-icon').html('<i class="' + iconClass + '" aria-hidden="true"></i>');
        $('#document-approve-confirm-title').text(title);
        $('#document-approve-confirm-body').text(body);
        $('#document-approve-confirm-yes').text(primaryLabel);
        $('#document-approve-confirm').modal('show');
    }

    function openBulkDeleteConfirm(ids) {
        if (!ids || !ids.length) {
            return;
        }
        bulkDeleteIds = ids;
        var body = ids.length > 1 ? L('AreYouSureYouWantToDeleteTheseDocuments') :
            String(L('AreYouSureYouWantToDeleteThisDocument'));
        $('#document-bulk-delete-confirm-body').text(body);
        $('#document-bulk-delete-confirm').modal('show');
    }

    function init() {
        var $grid = $('#document-program-grid');
        perms = {
            canEdit: $grid.data('can-edit') === true,
            canDelete: $grid.data('can-delete') === true,
            canApprove: $grid.data('can-approve') === true,
            canView: $grid.data('can-view') === true
        };

        // Row-actions compact-tier disclosure (design-system.md §6/§6b), shared
        // with Manage Audits/Define Tests/Governance Frameworks via
        // js/simplerisk/sr-row-actions-menu.js. Delegated from the grid itself
        // (not the rebuilt tbody), so one bind() survives every load()/reload.
        SRRowActionsMenu.bind({
            container: $grid,
            scope: $grid,
            namespace: 'docprogram'
        });

        // Version-history row expander (design-system.md §6). Delegated on
        // the caret specifically, not the whole row -- the row's other
        // interactive elements (the checkbox, the row-actions menu) must
        // not also toggle it.
        $(document).on('click', '.sr-doc-row .sr-group-caret', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleVersionHistory($(this).closest('tr.sr-doc-row'));
        });

        $(document).on('click', '.document--approve', function (e) {
            e.preventDefault();
            openApproveConfirm('approve', [$(this).data('id')], $(this).data('name'));
        });
        $(document).on('click', '.document--unapprove', function (e) {
            e.preventDefault();
            openApproveConfirm('unapprove', [$(this).data('id')], $(this).data('name'));
        });
        $(document).on('click', '#document-program-bulk-approve', function (e) {
            e.preventDefault();
            openApproveConfirm('batch-approve', Object.keys(selectedIds), null);
        });

        $(document).on('click', '#document-approve-confirm-yes', function () {
            var mode = approveModalMode;
            var ids = approveModalIds;
            if (!ids || !ids.length) {
                return;
            }
            var $yes = $(this);
            $yes.prop('disabled', true);

            function onSuccess(data) {
                if (data.status_message) {
                    showAlertsFromArray(data.status_message);
                }
                $yes.prop('disabled', false);
                $('#document-approve-confirm').modal('hide');
                reload();
            }
            function onError(xhr, status, error) {
                $yes.prop('disabled', false);
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            }

            if (mode === 'unapprove') {
                $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/documents/unapprove', data: { document_id: ids[0] }, success: onSuccess, error: onError });
            } else if (mode === 'batch-approve') {
                $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/documents/batch-approve', data: { document_ids: ids }, success: onSuccess, error: onError });
            } else {
                $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/documents/approve', data: { document_id: ids[0] }, success: onSuccess, error: onError });
            }
        });

        // Streams a zip via governance/documentation.php's
        // download_selected_documents POST handler (includes/governance.php's
        // stream_documents_zip()) -- not a JSON API call, since the response
        // is a binary file. #document-program-bulk-download-form is the
        // static server-rendered shell (gets csrf-magic's own output-buffer
        // token injection); this only ever fills in the dynamic
        // document_ids[] inputs a server-rendered form can't know in advance.
        $(document).on('click', '#document-program-bulk-download', function (e) {
            e.preventDefault();
            var ids = Object.keys(selectedIds);
            if (!ids.length) {
                return;
            }
            var $ids = $('#document-program-bulk-download-ids').empty();
            ids.forEach(function (id) {
                $ids.append($('<input>', { type: 'hidden', name: 'document_ids[]', value: id }));
            });
            document.getElementById('document-program-bulk-download-form').submit();
        });

        $(document).on('click', '#document-program-bulk-delete', function (e) {
            e.preventDefault();
            openBulkDeleteConfirm(Object.keys(selectedIds));
        });

        $(document).on('click', '#document-bulk-delete-confirm-yes', function () {
            var ids = bulkDeleteIds;
            if (!ids || !ids.length) {
                return;
            }
            var $yes = $(this);
            $yes.prop('disabled', true);

            function deleteOne(id) {
                // No `version` -- a bulk selection always removes the whole
                // document, never a single uploaded version (see the modal's
                // PHP comment in governance/documentation.php).
                return $.ajax({ type: 'POST', url: BASE_URL + '/api/v2/documents/delete', data: { document_id: id } });
            }

            if (ids.length === 1) {
                deleteOne(ids[0]).done(function (data) {
                    if (data.status_message) {
                        showAlertsFromArray(data.status_message);
                    }
                }).fail(function (xhr, status, error) {
                    if (!retryCSRF(xhr, this)) {
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }).always(function () {
                    $yes.prop('disabled', false);
                    $('#document-bulk-delete-confirm').modal('hide');
                    selectedIds = {};
                    reload();
                });
                return;
            }

            // Bulk delete: loop the single-delete endpoint once per selected id
            // (mirrors governance-exceptions.js's bulk delete -- there's no
            // arbitrary-id-array batch-delete endpoint for documents either),
            // then show ONE aggregate toast rather than one per looped
            // request. Each deleteOne() promise is wrapped in its own
            // deferred that always resolves -- $.when.apply() rejects as soon
            // as ANY input promise rejects, which would abandon the count
            // before the other requests finish; wrapping lets every request
            // complete and be tallied, so a partial failure is reported
            // honestly instead of showing the same success toast either way.
            var failedCount = 0;
            var settled = ids.map(function (id) {
                var d = $.Deferred();
                deleteOne(id)
                    .fail(function () { failedCount++; })
                    .always(function () { d.resolve(); });
                return d.promise();
            });
            $.when.apply($, settled).always(function () {
                $yes.prop('disabled', false);
                $('#document-bulk-delete-confirm').modal('hide');
                if (failedCount > 0) {
                    showAlertFromMessage(L('SomeDocumentsNotDeleted'), false);
                } else {
                    showAlertFromMessage(L('DocumentsDeleted'), true);
                }
                selectedIds = {};
                reload();
            });
        });

        $(document).on('change', '#document-program-select-all', function () {
            var checked = this.checked;
            $('#document-program-table tbody tr:visible .sr-row-check').each(function () {
                var id = $(this).data('id');
                if (checked) {
                    selectedIds[id] = true;
                } else {
                    delete selectedIds[id];
                }
            });
            syncCheckboxes();
            updateBulkBar();
        });

        $(document).on('click', '#document-program-bulk-clear', function () {
            selectedIds = {};
            syncCheckboxes();
            updateBulkBar();
        });

        $(document).on('click', '#document-program-retry', function () {
            reload();
        });
        // "No results match filters" empty state's action -- clears BOTH the
        // text search and the five facet filters, matching Define Tests'
        // own #define-tests-clear-filters scope (its resetFilters() clears
        // everything, not just the search box).
        $(document).on('click', '#document-program-clear-search', function () {
            resetFilters();
            applyFiltersAndRender();
            writeFiltersToUrl();
            if (dt) {
                dt.search('').draw();
            }
        });

        // Filters row (design-system.md §6b/§6c). Every select re-applies
        // client-side (allRows is already loaded -- no re-fetch needed) and
        // syncs the URL so the current view stays shareable/bookmarkable.
        $(document).on('change', '#document-program-type-filter, #document-program-framework-filter, #document-program-status-filter, #document-program-approver-filter, #document-program-review-filter', function () {
            readFiltersFromControls();
            applyFiltersAndRender();
            writeFiltersToUrl();
        });

        // The dedicated "Clear filters" affordance next to the Filters·n
        // badge (see syncFilterCount()) -- resets the five facets only,
        // leaving the free-text search alone: this button lives beside the
        // facet selects, not the search box, so clearing it shouldn't also
        // wipe a search term the user typed on purpose.
        $(document).on('click', '#document-program-filters-clear', function () {
            resetFilters();
            applyFiltersAndRender();
            writeFiltersToUrl();
        });

        // Narrow-width filter sheet (design-system.md §6b) -- inert at full
        // width, where the row is simply on screen (matches Define Tests'
        // #define-tests-filters-toggle exactly).
        $(document).on('click', '#document-program-filters-toggle', function () {
            var $toggle = $(this);
            var open = $('#document-program-quickfilters').toggleClass('is-open').hasClass('is-open');
            $toggle
                .attr('aria-expanded', open ? 'true' : 'false')
                .attr('title', open ? (L('HideFilters') || 'Hide filters') : (L('ShowFilters') || 'Show filters'));
        });

        // Columns picker (design-system.md §6c).
        $(document).on('click', '#document-program-colpicker-btn', function (e) {
            e.stopPropagation();
            $('#document-program-colpanel').toggleClass('d-none');
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.colpicker').length) {
                $('#document-program-colpanel').addClass('d-none');
            }
        });
        $(document).on('change', '#document-program-colpanel input[type="checkbox"]', function () {
            var col = $(this).data('col');
            columnVisible[col] = this.checked;
            applyColumnVisibility();
        });

        renderColpanel();
        // ?type=/?framework=/?status=/?approver=/?review= come from the
        // insights band's KPI tiles (documentation.php?type=<slug>, a real
        // link) or a bookmarked/shared filtered link -- read BEFORE enhancing
        // the static (Status/Review) selects below, so their very first
        // sr-select render already reflects the right value rather than
        // needing a second forced re-sync.
        readFiltersFromUrl();

        // Draws the custom listboxes (js/simplerisk/sr-select.js,
        // design-system.md's toolbar filter look) over every native filter
        // select. Done before the first fetch so the toolbar never flashes
        // native controls (matches compliance-define-tests.js's own
        // ordering) -- Document Type/Framework/Approver render placeholder-
        // only until renderFilterOptions() populates their real <option>s
        // and force-re-syncs them.
        if (window.srSelectEnhance) {
            window.srSelectEnhance($('#document-program-type-filter'), L('AllTypes'));
            window.srSelectEnhance($('#document-program-framework-filter'), L('AllFrameworks'));
            window.srSelectEnhance($('#document-program-status-filter'));
            window.srSelectEnhance($('#document-program-approver-filter'));
            window.srSelectEnhance($('#document-program-review-filter'));
        }

        load();
    }

    return { init: init, load: load, reload: reload };
})(jQuery);
