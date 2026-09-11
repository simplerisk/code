// ====================================================================
// Document Program audit trail (design-system.md §6/§7) -- rebuilt from
// the legacy Bootstrap accordion (a single date-range <select> plus a
// <p>-per-row dump of "timestamp > message") into a second .sr-table-card
// below the Document Program grid on the same page
// (governance/documentation.php), reusing that grid's own shipped shell
// (design-system.md §6) and adding real Document/Activity columns backed
// by GET /api/v2/governance/documents/audit_log's now-structured response
// (document_id/document_name/user_id/user_name/activity -- see
// get_documents_audit_log_api() in includes/api.php).
//
// Gated server-side: governance/documentation.php only renders
// #document-audit-trail at all when the viewer is an admin or holds the
// view_document_audit_logs permission, so this module's init() is only
// ever called when the card exists in the DOM.
//
// The actual implementation is the shared createAuditTrail() factory
// (js/simplerisk/sr-audit-trail.js, loaded as CUSTOM:sr-audit-trail.js
// before this file) -- this is just its Document Program configuration.
// ====================================================================
var DocumentAuditTrail = createAuditTrail({
    idPrefix: 'document-audit-trail',
    entityKey: 'document',
    apiPath: BASE_URL + '/api/v2/governance/documents/audit_log',
    allEntitiesLabelKey: 'AllDocuments',
    entityColumnLabelKey: 'Document',
    // Activity column pill (design-system.md §7 "State -- soft"), keyed off
    // the server-classified `activity` field (classify_document_audit_activity()
    // in includes/governance.php) rather than parsing the message client-side.
    activityMeta: {
        // 'EncryptionBackupCreatedAt' -- reused rather than a fresh 'Created'
        // key: same English text, and lang.en.php is append-only so a
        // needless Crowdin duplicate can't be cleaned up later without
        // orphaning translations. The key name is encryption-flavored but
        // the lookup is generic (window.L() resolves any key by string).
        create:     { pillClass: 'sr-state-neutral', labelKey: 'EncryptionBackupCreatedAt' },
        update:     { pillClass: 'sr-state-neutral', labelKey: 'Updated' },
        delete:     { pillClass: 'sr-state-danger',  labelKey: 'Deleted' },
        delete_version: { pillClass: 'sr-state-warning', labelKey: 'DeletedVersion' },
        approve:    { pillClass: 'sr-state-success', labelKey: 'Approved' },
        unapprove:  { pillClass: 'sr-state-warning', labelKey: 'Unapproved' },
        download:   { pillClass: 'sr-state-info',    labelKey: 'Downloaded' },
        upload:     { pillClass: 'sr-state-info',    labelKey: 'Uploaded' },
        other:      { pillClass: 'sr-state-neutral', labelKey: 'Activity' }
    }
});
