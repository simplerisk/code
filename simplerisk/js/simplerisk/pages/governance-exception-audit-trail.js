// ====================================================================
// Define Exceptions audit trail (design-system.md §6/§7) -- rebuilt from
// the legacy Bootstrap accordion (a single date-range <select> plus a
// <p>-per-row dump of "timestamp > message") into a second .sr-table-card
// below the Define Exceptions grid on the same page
// (governance/document_exceptions.php), reusing that grid's own shipped
// shell (design-system.md §6) and Document Program's identical audit trail
// as the reference -- same component, same markup shape, same relocation/
// sort-icon/empty-state helpers, adapted to Exception/Activity/User columns
// backed by GET /api/v2/exceptions/audit_log's now-structured response
// (exception_id/exception_name/user_id/user_name/activity -- see
// get_exceptions_audit_log_api() in includes/api.php).
//
// Gated server-side: governance/document_exceptions.php only renders
// #exception-audit-trail at all when the viewer is an admin or holds
// governance + the exception 'view' permission, so this module's init() is
// only ever called when the card exists in the DOM.
//
// The actual implementation is the shared createAuditTrail() factory
// (js/simplerisk/sr-audit-trail.js, loaded as CUSTOM:sr-audit-trail.js
// before this file) -- this is just its Define Exceptions configuration.
// ====================================================================
var ExceptionAuditTrail = createAuditTrail({
    idPrefix: 'exception-audit-trail',
    entityKey: 'exception',
    apiPath: BASE_URL + '/api/v2/exceptions/audit_log',
    allEntitiesLabelKey: 'AllExceptions',
    entityColumnLabelKey: 'Exception',
    // Activity column pill (design-system.md §7 "State -- soft"), keyed off
    // the server-classified `activity` field (classify_exception_audit_
    // activity() in includes/governance.php) rather than parsing the
    // message client-side. Five shapes only -- exceptions have no
    // delete_version/download/upload equivalent the way documents do.
    activityMeta: {
        // 'EncryptionBackupCreatedAt' -- see the identical comment in
        // governance-document-audit-trail.js's activityMeta.
        create:    { pillClass: 'sr-state-neutral', labelKey: 'EncryptionBackupCreatedAt' },
        update:    { pillClass: 'sr-state-neutral', labelKey: 'Updated' },
        delete:    { pillClass: 'sr-state-danger',  labelKey: 'Deleted' },
        approve:   { pillClass: 'sr-state-success', labelKey: 'Approved' },
        unapprove: { pillClass: 'sr-state-warning', labelKey: 'Unapproved' },
        other:     { pillClass: 'sr-state-neutral', labelKey: 'Activity' }
    }
});
