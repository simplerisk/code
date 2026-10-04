// ====================================================================
// Risk view Audit Trail (design-system.md §6/§7) -- rebuilt from
// get_audit_trail_html() (includes/display.php), the legacy Bootstrap
// accordion body that dumped "{timestamp} > {message}" as plain <p> tags
// with no columns and no filters, into the same .sr-table-card shell
// Define Exceptions'/Document Program's audit trails use (management/
// partials/viewhtml.php), reusing this risk's own structured GET
// /api/v2/management/risk/auditLog (user_id/user_name/activity -- see
// get_risk_audit_log_api() in includes/api.php).
//
// No entityKey: unlike Define Exceptions/Document Program (list pages
// spanning many records, where the Entity column disambiguates rows), this
// card is already scoped to ONE risk -- an Entity column would just repeat
// this risk's own name on every row. createAuditTrail() (js/simplerisk/
// sr-audit-trail.js) treats entityKey as optional for exactly this case.
//
// Gated server-side: management/partials/viewhtml.php only renders
// #risk-audit-trail at all when $display_risk is true, so this module's
// init() is only ever called when the card exists in the DOM; the API
// endpoint itself re-checks riskmanagement permission + check_access_for_
// risk($id) (team separation) regardless.
//
// The actual implementation is the shared createAuditTrail() factory
// (js/simplerisk/sr-audit-trail.js, loaded as CUSTOM:sr-audit-trail.js
// before this file) -- this is just its risk-view configuration.
// ====================================================================
var RiskAuditTrail = createAuditTrail({
    idPrefix: 'risk-audit-trail',
    entityKey: null,
    apiPath: BASE_URL + '/api/v2/management/risk/auditLog?id=' + window.simplerisk_current_risk_id,
    // Several risk messages (Risk/Mitigation details updated) carry a real
    // field-by-field diff after the activity sentence -- get_risk_audit_
    // log_api() (includes/api.php) splits it out as `detail`; this renders
    // it under the activity pill rather than silently dropping it, matching
    // the level of detail the OLD get_audit_trail_html() dump showed.
    showMessageDetail: true,
    // No Export button (no inner-toolbar row to share with), so the search
    // box moves beside the filter selects instead -- see this option's own
    // comment in sr-audit-trail.js.
    searchBesideFilters: true,
    // Activity column pill (design-system.md §7 "State -- soft"), keyed off
    // the server-classified `activity` field (classify_risk_audit_activity()
    // in includes/functions.php) rather than parsing the message client-side.
    activityMeta: {
        // 'EncryptionBackupCreatedAt' -- see the identical comment in
        // governance-document-audit-trail.js's activityMeta.
        create:             { pillClass: 'sr-state-neutral', labelKey: 'EncryptionBackupCreatedAt' },
        update:             { pillClass: 'sr-state-neutral', labelKey: 'Updated' },
        close:              { pillClass: 'sr-state-neutral', labelKey: 'Closed' },
        reopen:             { pillClass: 'sr-state-info',    labelKey: 'Reopened' },
        review:             { pillClass: 'sr-state-success', labelKey: 'Reviewed' },
        delete:             { pillClass: 'sr-state-danger',  labelKey: 'Deleted' },
        comment:            { pillClass: 'sr-state-neutral', labelKey: 'Comment' },
        upload:             { pillClass: 'sr-state-info',    labelKey: 'Uploaded' },
        mitigation_accept:  { pillClass: 'sr-state-success', labelKey: 'MitigationAccepted' },
        mitigation_reject:  { pillClass: 'sr-state-warning', labelKey: 'Rejected' },
        other:              { pillClass: 'sr-state-neutral', labelKey: 'Activity' }
    }
});
