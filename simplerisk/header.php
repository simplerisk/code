<?php
require_once(realpath(__DIR__ .'/head.php'));
// This file directly consumes helpers defined in includes/functions.php
// (resolve_required_localization_keys, build_js_lang_subset,
// encode_js_lang_subset, resolve_header_script_asset, write_debug_log). head.php
// already loads functions.php, but per the CLAUDE.md function-reachability rule a
// direct consumer declares its own require_once so an include-order change can't
// strip the chain (require_once makes the duplicate load a no-op).
require_once(realpath(__DIR__ . '/includes/functions.php'));
// The AI chat-icon/panel gates below call ai_provider_is_configured(), defined
// in Core includes/artificial_intelligence.php. header.php is included from many
// entry points and renders before sidebar.php loads that file, so require it
// directly here — every direct consumer declares its own require_once (per the
// CLAUDE.md function-reachability rule) rather than relying on include ordering.
require_once(realpath(__DIR__ . '/includes/artificial_intelligence.php'));

// Define the localization keys required by certain scripts and if there's a match in the requested scripts then the required localizations will be made available for the script to use
// In the script and the page using it you will be able to use _lang['localization_key'] in javascript.
$localization_required_by_scripts = [
    'CUSTOM:common.js' => ['Yes', 'Cancel', 'FieldRequired'],
    'EXTRA:JS:assessments:questionnaire_templates.js' => ['SelectedOnAnotherTab', 'ID', 'SelectedQuestions', 'SearchForQuestion', 'ConfirmDisableTabbedExperience', 'ConfirmDeleteTab', 'NewTab', 'Default', 'Actions', 'Required'],
    'CUSTOM:pages/plan-project.js' => ['AreYouSureYouWantToDeleteThisProject'],
    'datatables' => ['All', 'datatables_ShowAll', 'datatables_ShowLess', 'First', 'Previous', 'Next', 'Last'],
    'blockUI' => ['ProcessingPleaseWait'],
    'UILayoutWidget' => ['WidgetType_chart', 'WidgetType_table', 'WidgetType_WYSIWYG', 'WidgetType_kpi', 'WidgetType_whats_next'],
    'CUSTOM:pages/governance.js' => ['ExistingMappings', 'Unassigned', 'DocumentName', 'DocumentType', 'ControlFrameworks', 'Controls', 'CreationDate', 'ApprovalDate', 'Status', 'All', 'ExceptionName', 'ID', 'Description', 'Justification', 'NextReviewDate'],
    'CUSTOM:pages/governance-frameworks.js' => ['AllControls', 'UnassignedControls', 'ControlNumber', 'ControlName', 'ControlFamily', 'Owner', 'Maturity', 'Status', 'Pass', 'Fail', 'NotTested', 'BelowMaturity', 'NoOwner', 'Unassigned', 'ShowingXToYOfZ', 'Controls', 'SearchControls', 'Filters', 'ClearFilters', 'AddControl', 'NSelected', 'ControlClass', 'ControlPhase', 'ControlPriority', 'ControlType', 'AnyFamily', 'AnyOwner', 'AnyClass', 'AnyPhase', 'AnyPriority', 'AnyType', 'AnyStatus', 'Description', 'SupplementalGuidance', 'MitigationPercent', 'SelectAllN', 'SelectAll', 'Clear', 'DeleteSelectedControls',
        // Task 8: modal wiring (row-action labels, destructive-confirm titles, generic API-failure fallback)
        'Edit', 'Delete', 'RequestFailed', 'DeleteFrameworkTitle', 'DeleteControlTitle', 'DeleteControlsTitle',
        // Task 54: the bulk-delete confirmation states SERVER-RESOLVED numbers before
        // anything is committed, so it needs the three split sentences (both halves,
        // kept-only, removed-only), the nothing-left case, the in-flight placeholder,
        // and the result toast. Task 8's 'BulkDeleteAllFilteredUnsupported' and
        // 'ControlsDeleteResult' are GONE: the escalated case is supported now, and
        // the delete is one transactional call rather than N parallel ones, so
        // "{$ok} of {$total}" no longer describes anything that can happen.
        'DeleteControlsPreviewChecking', 'DeleteControlsPreviewSplit', 'DeleteControlsPreviewKeptOnly',
        'DeleteControlsPreviewRemovedOnly', 'DeleteControlsPreviewNone', 'ControlsDeletedResult',
        // Task 24: restored Clone row action (row-action label; success/error toast text
        // comes straight from the server's own status_message, same as every other control
        // CRUD action on this page) plus the pre-fill banner naming which control was cloned.
        'Clone', 'ClonedFromControlNotice', 'CloneOfControlTitle', 'NewControl',
        // Task 22: framework rail search -- showFrameworksEmptyState() swaps the shared #sr-fw-filtered tile's title/action between these two pairs depending on whether a status filter or a search caused the empty result.
        'NoFrameworksMatchFilter', 'ViewActiveFrameworks', 'NoFrameworksMatchSearch', 'ClearSearch',
        // Task 23: the row-expand caret (renderRow()) swapped its glyph text for an
        // icon-only .sr-group-caret button, so it needs its own accessible name.
        'Details',
        // Task 27: the rail's SCF-origin chip (railRow()) -- badge text + tooltip.
        'SCF', 'ScfOriginHint',
        // Task 34: the Maturity column's Below/At/Above chip (renderMaturity()),
        // the matching filter facet (its three option labels reuse the same
        // three keys) and its "Any maturity" placeholder, plus the drawer's
        // label for the exact current -> desired level pair. 'BelowMaturity'
        // and 'Maturity' are already registered above.
        'AtMaturity', 'AboveMaturity', 'AnyMaturity', 'ControlMaturity',
        // Task 36: the row-actions overflow toggle (rowActionsWrap()) is
        // icon-only, so it needs its own accessible name. Same key Define
        // Tests' identical toggle already uses -- reused, not re-added.
        'Actions',
        // Task 46: the control table's pager (renderPager()). Previous/Next
        // are the SAME two keys Define Tests' pager already uses -- reused,
        // not re-added -- alongside the new landmark label. ShowingXToYOfZ is
        // already registered above; the rows-per-page select's own labels are
        // server-rendered in governance/index.php.
        'Previous', 'Next', 'ControlsPagination',
        // Task 14: the Applicability column chip (renderApplicability()), the
        // matching filter facet, and the drawer's applicability record. The
        // column header, the facet's accessible name and three of the record's
        // labels reuse existing keys ('Applicability', 'Reason', 'Provider',
        // 'Justification') -- registered here, not re-added to lang.en.php.
        'Applicability', 'ApplicabilityApplicable', 'ApplicabilityNotApplicable', 'ApplicabilityInherited',
        'AnyApplicability', 'Reason', 'Provider', 'Justification',
        'ApplicabilityDecidedBy', 'ApplicabilityDecidedOn',
        // Task 15: bulk-set applicability from the selection bar. The bulk bar's
        // action label, the modal's scope note (two sentences -- one naming the
        // framework, one naming the population), the per-state hints, and the
        // two result toasts. The modal's own static labels are server-rendered
        // in governance/index.php; these are the strings the JS builds at
        // runtime.
        //
        // 'ChooseAReason' / 'ApplicabilityNoReason' are gone from this list: the
        // reason field became a CHECKBOX GROUP when reasons went multi-select,
        // and a checkbox group has no placeholder row to label. The keys stay in
        // lang.en.php -- they are generic enough to be reused, and retiring a
        // key costs 39 locales a Crowdin round trip to gain nothing.
        'SetApplicability', 'ApplicabilityScopeNote', 'ApplicabilityAppliesToSelected',
        'ApplicabilityAppliesToAllFiltered', 'ApplicabilityApplicableHint',
        'ApplicabilityNotApplicableHint', 'ApplicabilityInheritedHint',
        'ApplicabilitySetResult', 'ApplicabilityClearResult',
        // Task 60: the same modal, opened from a single control's row action.
        // The row button's own label reuses 'SetApplicability' above; these two
        // are the row-scoped title and population sentence, which name the
        // control so the modal cannot be read as acting on the checkbox
        // selection.
        // Task 63 adds the second population spelling, used only when other
        // controls are actually selected behind the row action.
        'SetApplicabilityForControl', 'ApplicabilityAppliesToControl',
        'ApplicabilityAppliesToControlNotSelection',
        // Task 17: the "Generate statement of applicability" header button. It
        // is shown only when exactly one framework is scoped -- the SoA is a
        // per-framework document and there is no cross-framework roll-up -- so
        // the label is the only string the JS needs for it.
        // Task 65 adds the short visible label; the full string above stays as
        // the button's title/aria-label, so both are needed.
        'GenerateStatementOfApplicability', 'GenerateSoa',
        // Task 53: the Mapped Assets widget's one runtime string -- the refusal
        // shown when a second asset row picks a maturity level another row
        // already holds. Same key js/simplerisk/pages/governance.js uses for
        // the identical guard on the pre-redesign page: reused, not re-added.
        'ExistingMappings',
        // Task 64: Clone framework -- the rail row action's label, the
        // pre-filled Add Framework modal's title and banner, and the seeded
        // name. 'Clone' is already registered above (the control row action)
        // and is deliberately NOT re-added; 'CloneFramework' is the rail
        // button's own title/aria-label, which has to name the object because
        // the rail and the control table both carry a Clone icon.
        'CloneFramework', 'CloneOfFrameworkTitle', 'ClonedFromFrameworkNotice',
        'CloneOfFrameworkName', 'NewFramework'],
    // Task 17: the Statement of Applicability report
    // (reports/statement_of_applicability.php). The page is a thin shell and
    // EVERY visible string is built by this script, so the whole document's
    // vocabulary is registered here: the cover, the six column headings, the
    // three applicability states, the four implementation values, the
    // missing-cover-fields prompt, the framework picker the Reporting Hub route
    // lands on, and the two explained refusals.
    'CUSTOM:pages/statement-of-applicability.js' => [
        'StatementOfApplicability', 'SoaGeneratedOn', 'Controls', 'Framework', 'Frameworks',
        'ApplicabilityApplicable', 'ApplicabilityNotApplicable', 'ApplicabilityInherited',
        'SoaExcludedCount', 'IsmsScopeStatement', 'DefaultInclusionJustification',
        'Reference', 'ControlName', 'Applicability', 'Justification', 'SoaImplemented', 'Evidence',
        'Yes', 'No', 'SoaImplementedPartial', 'NotApplicable',
        'Reason', 'Provider', 'ApplicabilityDecidedBy',
        'SoaMissingFieldsTitle', 'SoaMissingScopeStatement', 'SoaMissingInclusionJustification',
        'SoaEditFrameworkToAdd', 'SoaChooseFramework', 'SoaChooseFrameworkHint',
        // The framework picker: its sr-select search field, the launcher's
        // "Open" affordance, and the state where the roster itself is empty.
        // The two exports beside it: the spreadsheet, and the ONE PDF affordance
        // ('SoaPdf' -- just "PDF"), whose mechanism the framework's size picks
        // and whose label it does not. Registered unconditionally -- the labels
        // are just strings; whether the affordances are BUILT is decided by the
        // page's data-sr-soa-can-export attribute, not by this list.
        //
        // THE THREE ACTION LABELS ARE SYMMETRIC and namespaced to this launcher:
        // 'SoaOpen' / 'SoaXlsx' / 'SoaPdf' -- one word each, because the row itself
        // supplies the verb and 'SoaPdf' could not honestly carry one anyway: above
        // SOA_EXPORT_PDF_MAX_CONTROLS it opens a print view rather than downloading
        // a file. ('DownloadAsXLSX' still has a caller of its own in the Assessments
        // Extra, which is why that key is untouched.)
        'Search', 'SoaOpen', 'SoaXlsx', 'SoaPdf',
        'SoaNoFrameworks', 'SoaNoFrameworksHint',
        'SoaFrameworkInactiveTitle', 'SoaFrameworkInactiveBody', 'SoaFrameworkNotFoundBody',
        'SoaNoControls', 'SoaNoControlsHint', 'RequestFailed'],
    'CUSTOM:pages/compliance.js' => ['AuditInitiationOffsetMustBeANonNegativeValue', 'AuditInitiationOffsetMustBeLessThanOrEqualToTestFrequency', 'AnchorDateMustBeTodayOrLater', 'TestSuccessCreated', 'RequestFailed', 'SuggestionDismissFailed', 'AreYouSureYouWantToApproveThisAudit', 'RejectCommentRequired', 'AtLeastOneControlRequired', 'AddOrRemove', 'Remove', 'CreateTagX', 'DeleteTestUsedByNControls', 'NoControlsMatchFilters', 'NoControlsSelectedYet', 'AllControls', 'AddOrRemoveControls', 'ChooseControls', 'Selected'],
    'CUSTOM:pages/compliance-define-tests.js' => ['Frameworks', 'Test', 'Tests', 'AddTest', 'NotTested', 'Retired', 'Edit', 'Delete', 'ScheduleManual', 'Overdue', 'DueSoon', 'Failing', 'Passing', 'Scheduled', 'NoTestsForThisControl', 'ShowingXToYOfZ', 'Previous', 'Next', 'All', 'Pass', 'Fail', 'Inconclusive', 'Framework', 'Control', 'Reference', 'NoFrameworksMapped', 'CouldNotLoadTests', 'Objective', 'TestSteps', 'ExpectedResults', 'Tester', 'ApproximateTime', 'Tags', 'minutes', 'minute', 'Retire', 'Restore', 'Select', 'NSelected', 'ConfirmRetireSelectedTests', 'ConfirmDeleteSelectedTests', 'BulkPartialFailure', 'RequestFailed', 'TestMethod', 'TestMethodInquiry', 'TestMethodObservation', 'TestMethodInspection', 'TestMethodReperformance', 'Sample', 'RequiredEvidence', 'Approvers', 'AllFrameworks', 'AllFamilies', 'AllTesters', 'ScheduleCalendar', 'ScheduleInterval', 'OverdueByXDays', 'OverdueByOneDay', 'DueInXDays', 'DueTomorrow', 'DueToday', 'ScheduledForX', 'Common', 'Controls', 'Description', 'ValidatesAcrossMappedFrameworks', 'EditTest', 'Archived', 'ControlHasNoTestCoverage', 'AddTheFirstTest', 'ApplyCommonTests', 'SelectOneOrMoreTests', 'CommonTestApplied', 'CommonTestsApplied', 'CouldNotApplyCommonTest', 'History', 'Date', 'Result', 'Approval', 'InProgress', 'Approved', 'Pending', 'Rejected', 'ThisTestHasNotBeenRunYet', 'CouldNotLoadTestHistory', 'Open', 'RemoveFromThisControl', 'RemoveTestFromControlConfirm', 'RemoveTestFromControlStays', 'RemoveTestFromControlStaysOne', 'TestRemovedFromControl', 'CouldNotRemoveTestFromControl', 'BulkDeleteSharedTestsNote', 'BulkRetireSharedTestsNote', 'BulkDeleteOneSharedTestNote', 'BulkRetireOneSharedTestNote', 'ViewTest', 'CouldNotLoadTest', 'NotSpecified', 'Teams', 'LastTestDate', 'NextTestDate', 'AdditionalStakeholders', 'AuditInitiationOffset', 'Cadence', 'AnchorDate', 'Close', 'TestName', 'Schedule', 'Identity', 'ProcedureAndEvidence', 'SearchMappings', 'NoMatchingMappings', 'Actions', 'ShowFilters', 'HideFilters', 'Create', 'Dismiss', 'ReviewAndEdit', 'AiSuggested', 'GenerateTestsWithAI', 'TestCreatedFromSuggestion', 'SuggestionDismissed', 'TestGenerationQueued', 'Generating', 'TestGenerationComplete', 'TestGenerationStillRunning', 'TestGenerationNoNew'],
    'CUSTOM:pages/assessment.js' => ['SimpleriskUsers', 'AssessmentContacts'],
    'CUSTOM:dynamic.js' => ['Risk', 'Mitigation', 'Review', 'RiskScoring', 'Unassigned', 'RiskMapping', 'Remove', 'NoColumnsSelected'],
    'CUSTOM:pages/connectivity-visualizer.js' => ['SearchEntities', 'SearchEntitiesPlaceholder', 'ShowTypes', 'Depth', 'Inspector', 'Connections', 'NoConnectionsFound', 'CouldNotLoadGraph', 'CouldNotSearchEntities', 'ShowingTopNOfM', 'RankedByMaturityGap', 'RankedByRiskScore', 'RankedByRecentFailure', 'RankedByReviewDate', 'RankedBySeverity', 'RankedByName', 'RiskCatalog', 'ThreatCatalog', 'Vulnerability', 'Audit', 'TestResult', 'NodeTypeSelfAssessmentResult', 'Relationship', 'CurrentMaturity', 'DesiredMaturity', 'ControlFamily', 'ApprovalState', 'ApprovalStatus', 'Manager', 'Approver', 'Tester', 'AssetValuation', 'Verified', 'Risk', 'Asset', 'Framework', 'Control', 'Test', 'Document', 'Exception', 'Name', 'Type', 'Status', 'Approved', 'Owner', 'RequestFailed', 'All', 'Close', 'RelationshipOfType', 'MitigationPercent', 'Objective', 'TestSteps', 'ExpectedResults', 'DesiredFrequency', 'LastDate', 'LastResult', 'LastResultDate', 'CalculatedRisk', 'Justification', 'NextReviewDate', 'PercentComplete', 'Response', 'AssessmentDate', 'FrameworkName', 'Score', 'Playbook', 'Severity', 'NextDate', 'ControlID', 'TestID', 'Number', 'Grouping', 'Description', 'Hidden', 'RiskId', 'FirstFound', 'LastFound', 'Patchable', 'Solution', 'Platform', 'Breadcrumb', 'SelectANodeToInspect', 'HiddenUnreachableNodes', 'BrowsableEntityTypes', 'CountFloor', 'NoBrowsableTypes', 'AllTypes', 'FilterEntitiesPlaceholder', 'NoMatchingEntities', 'LoadMore', 'Loading', 'CouldNotLoadEntityCounts', 'CouldNotLoadEntities', 'ClearGraph'],
];

?>
<!DOCTYPE html>
<html dir="ltr" lang="<?= $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?= $escaper->escapeHtml($_SESSION['lang']); ?>">
  <head>
    <title><?= isset($title) ? $title : 'SimpleRisk: Enterprise Risk Management Simplified';?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <!-- Favicon icon -->
    <?php setup_favicon("..");?>
    
    <!-- Bootstrap CSS -->
    <!-- Cache-bust on the app version AND the bundle's mtime, so a recompiled
         style.min.css (a CSS hotfix, or active development) is always served
         fresh even without an app-version bump. -->
    <link rel="stylesheet" href="../css/style.min.css?<?= $current_app_version ?>-<?= @filemtime(__DIR__ . '/css/style.min.css') ?>" />

    <!-- jQuery CSS -->
    <link rel="stylesheet" href="../vendor/node_modules/jquery-ui/dist/themes/base/jquery-ui.min.css?<?= $current_app_version ?>">
    
    <!-- extra css -->

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

  	<script type="text/javascript">
        var BASE_URL = '<?= $escaper->escapeHtml(rtrim(($_SESSION['base_url'] ?? get_setting("simplerisk_base_url")), '/'))?>';
        var CURRENCY = '<?= $escaper->escapeHtml(get_setting("currency") ?: "") ?>';
  	</script>

    <!-- Sidebar collapse state — set before paint so there's no flash-of-expanded -->
    <script>
      // Single source of truth for the auto-rail breakpoint; app-shell.js reads it.
      window.SR_AUTO_RAIL_MAX = 992;
      (function () {
        try {
          // Mirror app-shell.js effectiveState(): below the breakpoint default to
          // the icon rail (minimum); at/above it honour the stored preference.
          // Avoids a flash before app-shell.js runs.
          var stored = localStorage.getItem('sr_sidebar_state');
          var pref = (stored === 'expanded' || stored === 'rail' || stored === 'hidden') ? stored : null;
          var s = (window.innerWidth < window.SR_AUTO_RAIL_MAX) ? 'rail' : (pref || 'expanded');
          document.documentElement.setAttribute('data-sr-sidebar', s);
        } catch (e) {
          document.documentElement.setAttribute('data-sr-sidebar', 'expanded');
        }
      })();
    </script>

    <!-- All Jquery -->
    <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>
    <script src="../vendor/node_modules/jquery-ui/dist/jquery-ui.min.js?<?= $current_app_version ?>" id="script_jqueryui"></script>

    <!-- Bootstrap tether Core JavaScript -->
    <script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>
    <!--Wave Effects -->
    <script src="../js/simplerisk/theme/waves.js" defer></script>
    <!--Menu sidebar -->
    <script src="../js/simplerisk/theme/sidebarmenu.js" id="script_sidebarmenu" defer></script>
    <!--App shell (three-state sidebar collapse)-->
    <script src="../js/simplerisk/theme/app-shell.js" id="script_app_shell" defer></script>
    <!--Custom JavaScript -->
    <script src="../js/simplerisk/theme/theme.js" defer></script>
    <!-- In-app notifications -->
    <script src="../js/simplerisk/notifications.js?<?= $current_app_version ?>" defer></script>

<?php

// Normalize the two page-provided inputs so the logic below can treat them
// uniformly (either may be unset when a page needs no scripts/localization).
$required_scripts_or_css    = $required_scripts_or_css ?? [];
$required_localization_keys = $required_localization_keys ?? [];

// Expand the UILayoutWidget's script dependencies BEFORE resolving localization
// needs, so a dependency that itself needs localization (e.g. CUSTOM:common.js)
// is picked up. Later we could build real dependency management, but right now
// this hardcoded expansion is enough.
if (in_array('UILayoutWidget', $required_scripts_or_css)) {
    foreach (['gridstack', 'CUSTOM:common.js', 'WYSIWYG'] as $script_dependency) {
        if (!in_array($script_dependency, $required_scripts_or_css)) {
            $required_scripts_or_css[] = $script_dependency;
        }
    }
}

// Compute the final localization key set exactly once: the keys the page passed
// explicitly, merged with the keys registered for any requested script. (This
// supersedes the old 'JSLocalization' sentinel dance — the block below always
// emits, so there is no gate left to flip. Pages may still pass 'JSLocalization'
// in $required_scripts_or_css; it is now a harmless no-op in the switch below.)
$required_localization_keys = resolve_required_localization_keys(
    $required_scripts_or_css,
    $required_localization_keys,
    $localization_required_by_scripts
);

// Build the _lang subset (if any keys were requested). Values come from $lang
// (shipped translation files) and keys from a hardcoded map plus caller-supplied
// literal arrays — no request-controlled input reaches this sink.
$lang_json = null;
if (!empty($required_localization_keys)) {
    // Surface map/typo drift during development instead of silently shipping the
    // raw key name (e.g. "AreYouSureYouWantToDeleteThisProject") to the user.
    foreach ($required_localization_keys as $localization_key) {
        if (!isset($lang[$localization_key])) {
            write_debug_log("Localization requested for key '{$localization_key}' but it has no entry in \$lang; shipping the raw key name to JS", 'debug');
        }
    }

    // encode_js_lang_subset() applies the safe-for-HTML flags (hex-encoding
    // <, >, &, ', ") so a stray closing-tag/quote in a translated value can't
    // break out of the <script> or the JS string, and falls back to '{}' if
    // json_encode() ever fails — so the emitted _lang / window.L stay valid JS.
    // (escapeHtml() would be the wrong escaper here: it entity-encodes &, ', "
    // and would render literal "Users &amp; Access" in downstream textContent.)
    $lang_json = encode_js_lang_subset(build_js_lang_subset($required_localization_keys, $lang));
}

// Always emit the _lang baseline and the global L() accessor — even with no keys
// — so any consumer's _lang['X'] / L('X') degrades to undefined / the key name
// instead of throwing "ReferenceError: _lang is not defined" and aborting the
// rest of the inline script. This block is intentionally NOT deferred, so both
// globals exist before the deferred page scripts (rendered below) run.
?>
		<script type="text/javascript">
    		var _lang = <?= $lang_json ?? 'window._lang || {}' ?>;
    		window.L = window.L || function (k) { return (window._lang && window._lang[k]) || k; };
		</script>
<?php

// Include the required scripts and their css files
// Also setting defaults for certain scripts
foreach ($required_scripts_or_css as $required_script_or_css) {
        switch ($required_script_or_css) {
            case 'blockUI':
?>
    <script src="../vendor/node_modules/block-ui/jquery.blockUI.js?<?= $current_app_version ?>" id="script_blockui" defer></script>
    <script>
    	// Initialize the defaults for the blockUI when the script is loaded
    	$('#script_blockui').on('load', function () {
			$.blockUI.defaults.css = {
				padding: 0,
                margin: 0,
                width: '20%',
                top: '40%',
                left: '40%',
                textAlign: 'center',
                cursor: 'wait',
                color: 'var(--sr-light)',
				backgroundColor: 'rgba(0, 0, 0, 0)', // hide the background, so only the spinner is visible
			};

            $.blockUI.defaults.overlayCSS = { 
                backgroundColor: 'var(--sr-dark)', 
                opacity: 0.6, 
                cursor: 'wait' 
        	}

			$.blockUI.defaults.message = "<i class='fa fa-spinner fa-spin' style='font-size:24px;'></i>";

			// Store the original blockUI object, so we can still call it as a function and reassign its properties to the new implementation
		    var _original_blockui = $.blockUI;

			// Redefine the blockUI function call, so we can have different 'defaults' for it than for the block() function
            $.blockUI = function(options = {}){

                // Merge the defaults with the options provided in the function call
                const settings = $.extend(true, {
                    css: {
    					padding: 5,
    					border: '3px solid var(--sr-default)',
    					backgroundColor: 'var(--sr-dark)',
    				},
    				message: "<i class='fa fa-spinner fa-spin' style='font-size:24px; padding-right: 10px;'></i>" + _lang['ProcessingPleaseWait'],
                }, options);

                // Call the original blockUI function with the merged settings
				_original_blockui(settings);
            };

            // Assign the properties of the original to the new implementation
            Object.assign($.blockUI, _original_blockui);
		});

		$(document).on('submit', 'form.block-on-submit', function (e) {
			$.blockUI();
		});
	</script>
<?php 
            break;
        case 'selectize':
?>
    <script src="../vendor/node_modules/@selectize/selectize/dist/js/selectize.min.js?<?= $current_app_version ?>" id="script_selectize" defer></script>
    <link rel="stylesheet" href="../vendor/node_modules/@selectize/selectize/dist/css/selectize.bootstrap5.css?<?= $current_app_version ?>">
<?php 
            break;
        case "sorttable":
?>
    <script src="../vendor/node_modules/sorttable/sorttable.js?<?= $current_app_version ?>" id="script_sorttable" defer></script>
<?php
            break;
        case 'datatables':
?>
	<script src="../vendor/node_modules/datatables.net/js/dataTables.min.js?<?= $current_app_version ?>" defer></script>
	<script src="../vendor/node_modules/datatables.net-bs5/js/dataTables.bootstrap5.min.js?<?= $current_app_version ?>" id="script_datatables" defer></script>
	<script src="../js/simplerisk/dataTables.renderers.js?<?= $current_app_version ?>" id="script_datatables_renderers" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css?<?= $current_app_version ?>">
	<script>
    	// Initialize the defaults for the Datatable when the script is loaded
    	$('#script_datatables').on('load', function () {

    		// Readjust the columns on datatables when they are on a tab that was just shown
    		// It's required because when datatables are initialized while not shown the columns don't always line up properly with the headers 
    		$(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
    			$.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
			});

			Object.assign(DataTable.defaults, {
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, _lang['All']]],
                lengthChange: true,
                filter: true,
                processing: true,
        		serverSide: true,
                layout: {
                	topStart: 'pageLength',
<?php // Using PHP comments so it's not rendered into the page
                    // This is another way it could've been done. Leaving it here so you don't have to research it
                    //topEnd: () => $('<div>', {class: 'col-sm-12 col-md-12 settings'}),
?>
                    topEnd: {div: {className: 'col-sm-12 col-md-12 settings'}},
                    bottomStart: 'info',
                    bottomEnd: {
                    	className: 'd-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto paginate',
                    	features: [
                    		'paging',
                    		{div: {className: 'btn btn-primary shows'}},
<?php
            // Add the specification for the two extra required button for the Dynamic Risk Report
            if (in_array('CUSTOM:dynamic.js', $required_scripts_or_css)) {
?>
							{div: {className: 'print-by-group'}},
							{div: {className: 'download-by-group'}},
<?php
            }
?>
                		]
            		},
				},
				language: {
                	paginate: {
                		first: _lang['First'],
                		previous: _lang['Previous'],
                		next: _lang['Next'],
                		last: _lang['Last'],
                		
                	}
                }
            });

       		$(document).on('preInit.dt', function(e, settings) {
                var table = new $.fn.dataTable.Api(settings).table();

				// Get the ID of the datatable or create it if it doesn't have one             
             	var datatable_uuid = $(table.node()).uniqueId().attr('id');
             	
             	// Get the show all/less button
             	var button = $(table.node()).closest('div.dt-container').find('div.paginate > div.btn.shows');
             	
             	// Save the datatable's id on it, so it doesn't have to search for it in the onClick logic
             	button.data('td-id', datatable_uuid);
             	
             	// Create the localized Show All/Less divs that'll be shown based on the currently displayed result numbers
             	$('<span>').addClass('all').text(_lang['datatables_ShowAll']).prependTo(button);
             	$('<span>').addClass('less').text(_lang['datatables_ShowLess']).prependTo(button);
             	
             	// If there's a settings button for the datatable tagged with the "data-sr-role='dt-settings'" attribute 
             	// and has the [data-sr-target] attribute set then move the settings button to its designated place inside the
             	// datatable wrapper to make it look more like it's part of the datatable
             	$("[data-sr-role='dt-settings'][data-sr-target]").each(function() {
                    $(this).appendTo($('#' + $(this).data('sr-target')).closest('div.dt-container').find('div.settings'));
             	});
            });
                
			$(document).on('draw.dt', function (e, settings) {
                var api = new $.fn.dataTable.Api(settings);
             	var button = $(api.table().node()).closest('div.dt-container').find('div.paginate > div.btn.shows');
             	var info = api.page.info();

				// Toggle the 'all' class on when we're NOT displaying every results so we're showing the "Show All" button 
				button
				// Disable the button if there're less results than the page size(use d-none if you want to hide the button instead of disabling it)
				.toggleClass("disabled", info.recordsTotal < info.length)
				// Toggle the 'all' and 'less' classes('all' - display the "Show All" text, 'less' - display the "Show Less" text)
				.toggleClass("all", info.length != -1).toggleClass("less", info.length === -1);

<?php // Using PHP comments so it's not rendered into the page
				// Use $(this).data('dt-pageSize') in the button click logic if we should go back to the previous page size instead of the default 
				// if (info.length !== -1) {
				// 	button.data('dt-pageSize', info.length);
				// }
?>
            });

			// Switch between the default page size and the show all option on click            
            $('body').on('click', 'div.dt-container div.paginate > div.btn.shows', function(e) {
            	e.preventDefault();
            	var table = $('#' + $(this).data('td-id')).DataTable();
            	table.page.len(table.page.info().length === -1 ? DataTable.defaults.lengthMenu[0][0] : -1).draw();
            });
		});
	</script>
<?php 
            break;
        case 'datatables:rowgroup':
?>
	<script src="../vendor/node_modules/datatables.net-rowgroup/js/dataTables.rowGroup.min.js?<?= $current_app_version ?>" id="script_datatables_rowgroup" defer></script>
	<script src="../vendor/node_modules/datatables.net-rowgroup-bs5/js/rowGroup.bootstrap5.min.js?<?= $current_app_version ?>" id="script_datatables_rowgroup-bs5" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/datatables.net-rowgroup-bs5/css/rowGroup.bootstrap5.min.css?<?= $current_app_version ?>">
<?php 
            break;
            case 'datatables:rowreorder':
?>
	<script src="../vendor/node_modules/datatables.net-rowreorder/js/dataTables.rowReorder.min.js?<?= $current_app_version ?>" id="script_datatables_rowreorder" defer></script>
	<script src="../vendor/node_modules/datatables.net-rowreorder-bs5/js/rowReorder.bootstrap5.min.js?<?= $current_app_version ?>" id="script_datatables_rowreorder-bs5" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/datatables.net-rowreorder-bs5/css/rowReorder.bootstrap5.min.css?<?= $current_app_version ?>">
<?php
            break;
        case 'WYSIWYG':
?>
    <script src="../vendor/node_modules/hugerte/hugerte.min.js?<?= $current_app_version ?>" id="script_wysiwyg" defer></script>
	<script src="../js/WYSIWYG/editor.js?<?= $current_app_version ?>" id="script_wysiwyg_editor" defer></script>
	<script src="../js/WYSIWYG/helpers.js?<?= $current_app_version ?>" id="script_wysiwyg_helpers" defer></script>
	<link rel="stylesheet" href="../css/WYSIWYG/editor.css?<?= $current_app_version ?>">
<?php
            break;
        case 'WYSIWYG:Assessments':
?>
    <script src="../vendor/node_modules/hugerte/hugerte.min.js?<?= $current_app_version ?>" id="script_wysiwyg" defer></script>
	<script src="../extras/assessments/js/editor.js?<?= $current_app_version ?>" id="script_wysiwyg_editor" defer></script>
	<script src="../js/WYSIWYG/helpers.js?<?= $current_app_version ?>" id="script_wysiwyg_helpers" defer></script>
<?php
            break;
        case 'WYSIWYG:Notification':
?>
    <script src="../vendor/node_modules/hugerte/hugerte.min.js?<?= $current_app_version ?>" id="script_wysiwyg" defer></script>
	<script src="../extras/notification/js/editor.js?<?= $current_app_version ?>" id="script_wysiwyg_editor" defer></script>
	<script src="../js/WYSIWYG/helpers.js?<?= $current_app_version ?>" id="script_wysiwyg_helpers" defer></script>
<?php
            break;

        // make a "select2" that is a searchable select element.
        case 'select2':
?>
	<script src="../vendor/node_modules/select2/dist/js/select2.min.js?<?= $current_app_version ?>" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/select2/dist/css/select2.min.css?<?= $current_app_version ?>">
<?php 
            break;
        case 'multiselect':
?>
	<script src="../vendor/node_modules/bootstrap-multiselect/dist/js/bootstrap-multiselect.min.js?<?= $current_app_version ?>" id="script_multiselect" defer></script>
	<link rel="stylesheet" href="../vendor/node_modules/bootstrap-multiselect/dist/css/bootstrap-multiselect.min.css?<?= $current_app_version ?>">
	<script>
        // Initialize the defaults when the script is loaded
        $('#script_multiselect').on('load', function () {
            // A supposed workaround to make the multiselect widget work with bootstrap 5
            // (it only supports bootstrap versions up to bootstrap 3)
            $.fn.multiselect.Constructor.prototype.defaults.buttonClass = 'form-select';
            $.fn.multiselect.Constructor.prototype.defaults.templates.button = '<button type="button" class="multiselect dropdown-toggle form-control" data-bs-toggle="dropdown"><span class="multiselect-selected-text"></span></button>';

            // Move the dropdown of a multiselect in the datatable filter part to outside of the datatable scrollable container so that it doesn't get cut off when there isn't any row in the datatable
            // We should implement this in a way that we can have a global and page-specific settings for the multiselect and we should be able to override the global settings with the page-specific ones
            const globalSettings = {
                onDropdownShown: function (event) {

                    // Global logic for showing the dropdown

                    // Check if the multiselect is inside `.header_filter` of a datatables
                    if (!$(event.target).closest('.header_filter').length) {
                        return; // Skip if not in `.header_filter`
                    }

                    var _dropdown = $(event.target).next('.multiselect-container');

                    // Check if the dropdown is already moved
                    if (!_dropdown.attr('data-associated')) {
                        // Assign a unique identifier to track the dropdown
                        var dropdownId = 'dropdown-' + Math.random().toString(36).substr(2, 9);
                        _dropdown.attr('data-associated', dropdownId);
                        $(event.target).attr('data-dropdown-id', dropdownId);
                    }

                    // Move the dropdown to the `.dt-layout-full` container that contains the datatable and multiselect
                    // This is to prevent the dropdown from being cut off by the datatable scrollable container
                    
                    $(event.target).closest('.dt-layout-full').append(_dropdown);

                    // Adjust position
                    var offset = $(event.target).offset();
                    _dropdown.css({
                        top: offset.top + $(event.target).outerHeight(),
                        left: offset.left,
                        position: 'absolute',
                        zIndex: 1050
                    });
                    
                },
                onDropdownHidden: function (event) {

                    // Global logic for hiding the dropdown

                    // Check if the multiselect is inside `.header_filter` of a datatables
                    if (!$(event.target).closest('.header_filter').length) {
                        return; // Skip if not in `.header_filter`
                    }

                    // Get the dropdown ID from the target element
                    var dropdownId = $(event.target).attr('data-dropdown-id');

                    if (dropdownId) {
                        // Find the corresponding dropdown by its `data-associated` attribute
                        var _dropdown = $(event.target).closest('.dt-layout-full').find('.multiselect-container[data-associated="' + dropdownId + '"]');

                        // Move it back to the original position
                        if (_dropdown.length) {
                            $(event.target).after(_dropdown);
                        }
                    }
                }
            };

            // Wrapper function for initializing multiselect with combined settings
            window.initializeMultiselect = function (selector, pageSettings = {}) {

                // Combine global settings with page-specific settings
                const finalSettings = {
                    ...globalSettings,
                    ...pageSettings,
                    onDropdownShown: function (event) {
                        // Call both global and page-specific handlers
                        if (typeof globalSettings.onDropdownShown === 'function') {
                            globalSettings.onDropdownShown.call(this, event);
                        }
                        if (typeof pageSettings.onDropdownShown === 'function') {
                            pageSettings.onDropdownShown.call(this, event);
                        }
                    },
                    onDropdownHidden: function (event) {
                        // Call both global and page-specific handlers
                        if (typeof globalSettings.onDropdownHidden === 'function') {
                            globalSettings.onDropdownHidden.call(this, event);
                        }
                        if (typeof pageSettings.onDropdownHidden === 'function') {
                            pageSettings.onDropdownHidden.call(this, event);
                        }
                    }
                };

                // Initialize the multiselect
                $(selector).multiselect(finalSettings);
                
            };

<?php // Using PHP comments so it's not rendered into the page
    		// Please don't remove the commented part yet, we'll see if it'll be needed for making the multiselect work
          	/*$(document).on('click','.multiselect',function(){
            	// $(this).parent().addClass('open');
              	// $(this).parent().toggleClass('open')
          	});
          	$(document).click(function (event) {
              	var $target = $(event.target);
              	if (!$target.closest('.multiselect-native-select').find('.btn-group').length && $('.multiselect-native-select').find('.btn-group').hasClass("open")) {
                	$('.multiselect-native-select').find('.btn-group').removeClass('open');
              	}
          	});*/
?>
		});
	</script>
<?php 
            break;
        case 'bootstrap-table':
?>
            <script src="../vendor/node_modules/bootstrap-table/dist/bootstrap-table.min.js?<?= $current_app_version ?>" id="script_bootstrap_table" defer></script>
            <link rel="stylesheet" href="../vendor/node_modules/bootstrap-table/dist/bootstrap-table.min.css?<?= $current_app_version ?>">
<?php
            break;
        case 'cve_lookup':
?>
	<script src="../js/simplerisk/cve_lookup.js?<?= $current_app_version ?>" defer></script>
<?php 
            break;
        case 'easyui':
?>
    <script src="../vendor/simplerisk/jeasyui/jquery.easyui.min.js?<?= $current_app_version ?>" id="script_easyui" defer></script>
    <link rel="stylesheet" href="../vendor/simplerisk/jeasyui/themes/default/easyui.css?<?= $current_app_version ?>">
<?php 
            break;
        case 'easyui:treegrid':
    ?>
    <script src="../vendor/simplerisk/jeasyui/jquery.easyui.min.js?<?= $current_app_version ?>" id="script_easyui" defer></script>
    <link rel="stylesheet" href="../vendor/simplerisk/jeasyui/themes/default/datagrid.css?<?= $current_app_version ?>">
    <link rel="stylesheet" href="../vendor/simplerisk/jeasyui/themes/default/tree.css?<?= $current_app_version ?>">
<?php 
            break;
        case 'easyui:dnd':
?>
	<script src="../vendor/simplerisk/jeasyui/plugins/treegrid-dnd.js?<?= $current_app_version ?>" defer></script>
    <script src="../vendor/simplerisk/jeasyui/plugins/jquery.draggable.js?<?= $current_app_version ?>" defer></script>
	<script src="../vendor/simplerisk/jeasyui/plugins/jquery.droppable.js?<?= $current_app_version ?>" defer></script>

	<!-- Adding this empty style tag here to prevent easyui to create the rules for the treegrid drag&drop -->
	<style id="treegrid-dnd-style"></style>
<?php 
            break;
        case 'easyui:filter':
?>
	<script src="../vendor/simplerisk/jeasyui/plugins/datagrid-filter.js?<?= $current_app_version ?>"  id="script_easyui_filter" defer></script>
    <script>
        $(function () {
            $.fn.datagrid.defaults.filters.select = {
                init: function(container, options){

                    // Remove old select if exists
                    container.empty();

                    var select = $('<select class="form-select" style="width:100%;" name="' + options.name + '"></select>').appendTo(container)
                        .on('change', function () {
                            if (typeof options.onChange === 'function') {
                                options.onChange($(this).val());
                            }
                        });

                    if (options.url) {
                        $.ajax({
                            url: options.url,
                            method: 'GET',
                            dataType: 'json',
                            success: function(data) {
                                let items = data.data;
                                select.empty();
                        
                                if (options.defaultOption) {
                                    $('<option>', {
                                        value: options.defaultOption.value,
                                        text: options.defaultOption.name
                                    }).appendTo(select);
                                }
                        
                                $.each(items, function(_, item){
                                    $('<option>', {
                                        value: item.value,
                                        text: item.name
                                    }).appendTo(select);
                                });
                            }
                        });
                    } else if (options.data) {
                        $.each(options.data, function(_, item){
                            $('<option>', {
                                value: item.value,
                                text: item.name
                            }).appendTo(select);
                        });
                    }

                    return select;
                },
                getValue: function(target){
                    return $(target).val();
                },
                setValue: function(target, value){
                    $(target).val(value);
                },
                resize: function(target, width){
                    $(target).width(width);
                }
            };
        });
    </script>
<?php 
            break;
        case 'datetimerangepicker':
?>
	<script type="text/javascript" src="../vendor/node_modules/moment/min/moment.min.js?<?= $current_app_version ?>" id="script_moment" defer></script>
	<script type="text/javascript" src="../vendor/node_modules/daterangepicker/daterangepicker.js?<?= $current_app_version ?>" id="script_daterangepicker" defer></script>
	<link rel="stylesheet" type="text/css" href="../vendor/node_modules/daterangepicker/daterangepicker.css?<?= $current_app_version ?>" />

	<script>
        var default_date_format = '<?=$escaper->escapeHtml(get_default_date_format_for_js())?>';
        var default_datetime_format = '<?=$escaper->escapeHtml(get_default_datetime_format_for_js())?>';

      	// Initialize the defaults when the script is loaded
      	$('#script_daterangepicker').on('load', function () {

            // Defaults that are the same for every date/datetime/range widget
            $.fn.daterangepicker.defaultOptions = {
                "buttonClasses": "btn btn-sm",
                "applyButtonClasses": "btn-submit",
                "cancelClass": "btn-secondary",
            	locale: {
                	"separator": " - ", // added between the two dates in a daterange
                    // cancel button is used to clear the value so the button label should be changed into 'Clear'.
                    "cancelLabel": 'Clear'
                },
                // indicates whether the date range picker should automatically update the value of the <input> element it's attached to at initialization and when the selected dates change.
                // if this value is true, the datepicker initially shows the current date value and if false, the datepicker initially shows empty.
                // if we set this value to false, we should use 'apply.daterangepicker', 'cancel.daterangepicker' event to update the value of the datepicker and trigger 'change' event.
                "autoUpdateInput": false,

            }

            $.fn.extend({
            	/**
            	* Adding date/datetime/range related initialization functions to JQuery.
            	*
            	* Using Using Object.assign() this way for additional options to make sure
            	* that we can have default options that can be overridden and default options that can't.
            	*
            	* Object.assign({defaults that can be changed}, {additional options}, {defaults that can't be changed})
            	*/
            	initAsDatePicker: function(options = {}) {
            		this.daterangepicker(Object.assign({locale:{"format": default_date_format}}, options, {"timePicker": false, "singleDatePicker": true}));
                    //When the Apply and Clear buttons are clicked, set the value of the date picker input.
                    attachApplyAndCancelEventHandler($(this), 'date', false);
            	},
            	initAsDateTimePicker: function(options = {}) {
            		this.daterangepicker(Object.assign({locale:{"format": default_datetime_format}}, options, {"timePicker": true, "singleDatePicker": true}));
                    //When the Apply and Clear buttons are clicked, set the value of the date picker input.
                    attachApplyAndCancelEventHandler($(this), 'time', false);
            	},
            	initAsDateRangePicker: function(options = {}) {
            		this.daterangepicker(Object.assign({locale:{"format": default_date_format}}, options, {"timePicker": false, "singleDatePicker": false}));
                    //When the Apply and Clear buttons are clicked, set the value of the date picker input.
                    attachApplyAndCancelEventHandler($(this), 'date', true);

            	},
            	initAsDateTimeRangePicker: function(options = {}) {
            		this.daterangepicker(Object.assign({locale:{"format": default_datetime_format}}, options, {"timePicker": true, "singleDatePicker": false}));
                    //When the Apply and Clear buttons are clicked, set the value of the date picker input.
                    attachApplyAndCancelEventHandler($(this), 'time', true);
            	}
            });
        });

        // attach event handlers for clicking the Apply and Cancel buttons to the element
        // element: datepicker element
        // type = 'date' or 'time' => datepicker or datetimepicker
        // range = true or false => rangepicker true or false
        function attachApplyAndCancelEventHandler(element, type = 'date', range = false) {

            let default_input_format = '';
            if (type == 'date') {
                default_input_format = default_date_format;
            } else if (type == 'time') {
                default_input_format = default_datetime_format;
            }

            // trigerred when the apply button is clicked.
            $(element).on("apply.daterangepicker", function(ev, picker) {

                if (!range) {
                    $(this).val(picker.startDate.format(default_input_format));
                } else {
                    $(this).val(picker.startDate.format(default_input_format) + ' - ' + picker.endDate.format(default_input_format));
                }

                // if 'autoUpdateInput' is false, the 'change' event is not triggerred automatically even if the value of the datepicker is changed through 'apply.daterangepicker'.
                $(this).trigger('change');

            });

            // trigerred when the cancel button is clicked.
            $(element).on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');

                // if 'autoUpdateInput' is false, the 'change' event is not triggerred automatically even if the value of the datepicker is changed through 'cancel.daterangepicker'.
                $(this).trigger('change');
            });
        }
	</script>
<?php 
            break;
        case 'chart.js':
?>
    <script src="../vendor/node_modules/chart.js/dist/chart.umd.js?<?= $current_app_version ?>" id="script_chartjs" defer></script>
<?php 
            break;
        case 'graphology':
?>
            <script type="text/javascript" src="../vendor/node_modules/sigma/dist/sigma.min.js?<?= $current_app_version ?>" id="script_sigma" defer></script>
            <script type="text/javascript" src="../vendor/node_modules/graphology/dist/graphology.umd.min.js?<?= $current_app_version ?>" id="script_graphology" defer></script>
            <!-- graphology-layout and graphology-layout-forceatlas2 individually publish no browser
                 build, but graphology-library (the official aggregate package from the same
                 maintainers) does -- an upstream-built dist/graphology-library.min.js with a
                 `browser` field. It bundles both, exposed as window.graphologyLibrary.layout
                 (circular/circlepack/random/rotation) and window.graphologyLibrary.layoutForceAtlas2
                 (assign/inferSettings). See simplerisk/js/simplerisk/pages/connectivity-visualizer.js
                 for the actual call shape. -->
            <script type="text/javascript" src="../vendor/node_modules/graphology-library/dist/graphology-library.min.js?<?= $current_app_version ?>" id="script_graphology_library" defer></script>
<?php
            break;
        case 'tabs:logic':
?>

  	<script>
        // Change hash on changing tab
        //$(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
        $(document).on('click', 'nav a[data-bs-toggle="tab"]', function (e) {
        	let hash = $(this).data('bs-target');
            window.location.hash = hash.replace('#', '');
            
            // scrolling to the top so it doesn't jump to the tab's content when clicking the tab
            $('.content-wrapper')[0].scrollIntoView();
        });
  	
    	$(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
        	$('.content-wrapper')[0].scrollIntoView();
	    });

    	$(function() {

 			// Deactivate all the tabs, but mark the intended active tabs as primary-tabs so later we can activate them
 			// It's needed so the onshow events are executed
        	$('div.tab-pane.active').removeClass('active');
        	$('nav.nav.nav-tabs a.nav-link.active').removeClass('active').addClass('primary-tab');		
		
			// ^ means starting, meaning only match the first hash
            var hash = location.hash.replace(/^#/, '');
            if (hash) {
            	// get the parent tab panes up to the body tag so we can go and activate them so the
            	// path to the required tab is activated
            	let parents = $('.nav-tabs a[data-bs-target="#' + hash + '"]').parents('.tab-pane');

            	// Activate the 'path' to the requested tab in a reverse order
            	// originally the 'parents()' function gets the parents of the requested tab in an order
            	// <requested tab> -->> <body>
            	// but we need them activated <body> -->> <requested tab>   
            	parents.reverse().each((i, el) => $('.nav-tabs a[data-bs-target="#' + $(el).attr('id') + '"]').tab('show'));
            	
            	// Activate the tab itself
                $('.nav-tabs a[data-bs-target="#' + hash + '"]').tab('show');
            }

        	// Add a tab activation listener that checks for tab headers(inside of the tab that just got activated)
        	// that has no tab marked as active and activates the leftmost tab 
            $(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
            	// remove the marker from this tab
            	$(this).removeClass('primary-tab');
            	// get the tab header in this tab's content
            	let inner_nav = $($(this).data('bs-target') + ' nav.nav.nav-tabs').first();
            	
            	// get the list of tabs that are marked as primary-tab
				let primary_tab = inner_nav.find('a.nav-link.primary-tab');
				// if there's any, activate the first one
                if (primary_tab.length != 0){
                	primary_tab.first().tab('show');
                } else if (inner_nav.find('.active').length == 0){
                	// if there's no tab marked as primary-tab and there's no active one either, then activate the leftmost tab
                	inner_nav.find('a[data-bs-toggle="tab"]').first().tab('show');
                }
            });

        	// Check if there's a tab header without an active tab and mark the leftmost active
        	// the above event handler will handle the inner tabs if there are any
        	// this part is just there to kick off that logic
        	let inner_nav = $((hash ? `#${hash} `:'') + 'nav.nav.nav-tabs').first();
        	// get the list of tabs that are marked as primary-tab
			let primary_tab = inner_nav.find('a.nav-link.primary-tab');
			// if there's any, activate the first one
            if (primary_tab.length != 0){
            	primary_tab.first().tab('show');
            } else if (inner_nav.find('.active').length == 0){
            	// if there's no tab marked as primary-tab and there's no active one either, then activate the leftmost tab
            	inner_nav.find('a[data-bs-toggle="tab"]').first().tab('show');
            }
            
        	$(document).on('shown.bs.tab', 'nav a[data-bs-toggle="tab"]', function (e) {
        		$('.content-wrapper')[0].scrollIntoView();
<?php
            if (in_array('easyui:treegrid', $required_scripts_or_css)) {
?>
            		if ($.fn.treegrid) {
            			$('table.easyui-treegrid', $($(this).data('bs-target'))).each(function() {$(this).treegrid("resize");});
            		}
<?php
            }
?>
	   		});
		});
	</script>
<?php 
            break;

        case 'editable':
?>
    <script type="text/javascript">
    
        function resizable(el, factor) {
            var int = Number(factor) || 7.6;
            function resize() {el.width((el.val().length + 1) * int);}
            var e = ["keyup", "keypress", "focus", "blur", "change"];
            for (var i in e)
                el.on(e[i], resize);
            resize();
        }

        $(document).ready(function(){
            $("input.editable").each(function(){
                resizable($(this));
            });
                
            $("body").on("click", "span.editable", function() {
                $(this).hide();
                $(this).parent().find("input").show().select();
            });
                
            $("body").on("blur", "input.editable", function(){
                let input_value = $(this).val();
                if(!input_value || !input_value.trim()) return false;
                var label = $(this).parent().find("span.editable");
                $(this).hide();
                label.text(input_value);
                label.attr("title", input_value);
                label.show();
            });
        });
    </script>
<?php
            break;
        case 'gridstack':
?>
	<script type="text/javascript" src="../vendor/node_modules/gridstack/dist/gridstack-all.js?<?= $current_app_version ?>" id="script_gridstack" defer></script>
	<link rel="stylesheet" type="text/css" href="../vendor/node_modules/gridstack/dist/gridstack.min.css?<?= $current_app_version ?>" />
<?php
            break;

        case 'UILayoutWidget':
            require_once(realpath(__DIR__ . '/includes/Widgets/UILayout.php'));
            break;
        default:
            // Custom (CUSTOM:*) and extra (EXTRA:JS:* / EXTRA:CSS:*) assets.
            // resolve_header_script_asset() applies the path-charset guard and
            // requires a real .css for the CSS branch (a prior copy-paste
            // required .js, so the stylesheet loader was effectively dead).
            $asset = resolve_header_script_asset($required_script_or_css);
            if ($asset !== null && $asset['type'] === 'js') {
?>
		<script src="<?= $asset['path'] ?>?<?= $current_app_version ?>" defer></script>
<?php       // Extra stylesheet
            } elseif ($asset !== null) {
?>
		<link rel="stylesheet" href="<?= $asset['path'] ?>?<?= $current_app_version ?>">
<?php
            }
            break;
        }
    }
  	
?>
  	<script>
    	$(function() {
        	
        	// It's required because bootstrap's modal windows need to be nested under an element
        	// where none of the parents have 'fixed' or 'relative' set, so moving them under the <body>
        	// is the best option to make them work no matter where they were defined
        	$("div.modal")/*.detach()*/.appendTo("body");
    	});
	</script>
  </head>
    <!-- CSS only -->
  <body>
    <div class="preloader">
      <div class="lds-ripple">
        <div class="lds-pos"></div>
        <div class="lds-pos"></div>
      </div>
    </div>
    <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="full" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full">
      <header class="topbar" data-navbarbg="skin5">
        <nav class="navbar top-navbar navbar-expand navbar-dark">
          <div class="navbar-header" data-logobg="skin5">
            <!-- ============================================================== -->
            <!-- Hamburger (expand <-> rail) + wordmark. Shown at every width so
                 the rail can always be expanded; the legacy mobile off-canvas
                 toggler is unused (the sidebar is a persistent rail, not a drawer). -->
            <!-- ============================================================== -->
            <!-- aria-label is kept in sync with the effective state by app-shell.js
                 (applyState): "collapse" when expanded, "expand" in rail/hidden, so
                 the accessible name always matches what the next activation does. -->
            <button type="button" class="sr-hamburger d-inline-flex align-items-center justify-content-center"
                    id="sr-hamburger"
                    aria-label="<?= $escaper->escapeHtmlAttr($lang['CollapseSidebar']) ?>"
                    data-label-collapse="<?= $escaper->escapeHtmlAttr($lang['CollapseSidebar']) ?>"
                    data-label-expand="<?= $escaper->escapeHtmlAttr($lang['ExpandSidebar']) ?>"
                    aria-pressed="false">
              <i class="fas fa-bars"></i>
            </button>
<?php
            // A logo uploaded through the Customization Extra replaces the
            // wordmark here as well as on the login screen (SR-556). Same
            // asset, same parameterless endpoint -- an authenticated-only copy
            // would mean a second endpoint serving identical public branding.
            //
            // Decided from a SETTINGS read, never a blob query, so the shell
            // that renders on every authenticated page never pulls an image out
            // of the database.
            $custom_logo_src = get_custom_logo_src('../');
            if ($custom_logo_src !== '') {
?>
            <a class="navbar-brand sr-wordmark sr-wordmark--custom" href="../reports/home.php" title="SimpleRisk">
              <img class="sr-brand-customlogo" src="<?= $escaper->escapeHtmlAttr($custom_logo_src) ?>" alt="<?= $escaper->escapeHtmlAttr($lang['OrganizationLogo']) ?>" />
            </a>
<?php
            } else {
?>
            <a class="navbar-brand sr-wordmark" href="../reports/home.php" title="SimpleRisk">
              <img class="sr-brand-logo" src="../images/simplerisk-logo-icon.png" alt="SimpleRisk" />
              <span class="sr-brand-text"><span class="s">Simple</span><span class="r">Risk</span></span>
            </a>
<?php
            }
?>
          </div>
          <div class="navbar-collapse collapse show" id="navbarSupportedContent" data-navbarbg="skin5">
            <ul class="navbar-nav float-start me-auto">
              <!-- Search -->
              <?php
if (!advanced_search_extra()) { ?>
				<li class="nav-item dropdown nav-item-search">
            		<div class="nav-link">
            			<div class="search-box">
            				<form action="../management/view.php" method="get" autocomplete="off">
            					<button class="search-button" type="button"><i class="fas fa-search align-middle"></i></button>
        	    				<input type="text" class="search-input" name="id" placeholder="ID#" />
    	    				</form>
            			</div>
            		</div>
            	</li>
<?php } else{
    require_once(realpath(__DIR__ . '/extras/advanced_search/index.php'));
    render_advanced_search();
}?>
            </ul>
           
            <!-- Right side toggle and nav items -->
            <ul class="navbar-nav float-end">
<?php if (!empty($permissions['show_ai_chat']) && artificial_intelligence_extra() && function_exists('ai_provider_is_configured') && ai_provider_is_configured()): ?>
              <?php require_once(realpath(__DIR__ . '/extras/artificial_intelligence/includes/chat.php')); ai_render_chat_icon(); ?>
<?php endif; ?>
			  <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle waves-effect waves-dark"
                   href="#"
                   id="2"
                   role="button"
                   data-bs-toggle="dropdown"
                   aria-expanded="false"
                   title="<?= $escaper->escapeHtmlAttr($lang['Help']) ?>"
                   aria-label="<?= $escaper->escapeHtmlAttr($lang['Help']) ?>">
                  <i class="font-24 far fa-question-circle align-middle"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end animated" aria-labelledby="2">
                  
                  <!-- User Guide -->
                  <li><a class="dropdown-item" href="https://www.simplerisk.com/support/user-guide" target="_blank"><i class="fas fa-book-reader me-1 ms-1"></i><?= $escaper->escapeHtml($lang['UserGuide']);?></a></li>

                  <!-- Administrator Guide -->
                  <li><a class="dropdown-item" href="https://www.simplerisk.com/support/admin-guide" target="_blank"><i class="fas fa-user-shield me-1 ms-1"></i><?= $escaper->escapeHtml($lang['AdministratorGuide']);?></a></li>

                  <!-- API Documentation -->
                  <li><a class="dropdown-item" href="<?php echo /* @phan-suppress-current-line SecurityCheck-XSS -- build_url() called with hardcoded path; base URL is admin-configured */ build_url("api/v2/documentation.php");?>" target="_blank"><i class="fas fa-info-circle me-1 ms-1"></i><?= $escaper->escapeHtml($lang['APIDocumentation']);?></a></li>
                  
                  <!-- How-To Videos -->
                  <li><a class="dropdown-item" href="https://www.youtube.com/playlist?list=PLD9huGT2L0QFhvMoj7d8c4oDS5sFkWkUX" target="_blank"><i class="fas fa-video me-1 ms-1"></i><?= $escaper->escapeHtml($lang['HowToVideos']);?></a></li>
                  
                  <!-- FAQs -->
                  <li><a class="dropdown-item" href="https://www.simplerisk.com/support/faqs" target="_blank"><i class="fas fa-question-circle me-1 ms-1"></i><?= $escaper->escapeHtml($lang['FAQs']);?></a></li>

                  <!-- Whats New -->
                  <li><a class="dropdown-item" href="https://github.com/simplerisk/documentation/raw/master/SimpleRisk%20Release%20Notes%20<?= $escaper->escapeHtml(get_latest_app_version());?>.pdf" target="_blank"><i class="fas fa-link me-1 ms-1"></i><?= $escaper->escapeHtml($lang['WhatsNew']);?></a></li>

                  <!-- Roadmap -->
                  <li><a class="dropdown-item" href="https://www.simplerisk.com/support/roadmap" target="_blank"><i class="fas fa-map me-1 ms-1"></i><?= $escaper->escapeHtml($lang['Roadmap']);?></a></li>

                  <!-- Support Portal -->
                  <li><a class="dropdown-item" href="https://www.simplerisk.com/support/portal" target="_blank"><i class="fas fa-cloud me-1 ms-1"></i><?= $escaper->escapeHtml($lang['SupportPortal']);?></a></li>

                  <!-- Web Support -->
                  <li><a class="dropdown-item" href="https://support.simplerisk.com/tickets" target="_blank"><i class="fas fa-ticket-alt me-1 ms-1"></i><?= $escaper->escapeHtml($lang['WebSupport']);?></a></li>
                  
                  <!-- Email Support -->
                  <li><a class="dropdown-item" href="mailto: support@simplerisk.com" target="_blank"><i class="fas fa-envelope me-1 ms-1"></i><?= $escaper->escapeHtml($lang['EmailSupport']);?></a></li>

                  <!-- Phone Support: reserved for customers with a paid Extra. The
                       portal/web/email items above are standard support for everyone;
                       phone support is an entitlement of a paid purchase. -->
<?php
                  require_once(realpath(__DIR__ . '/includes/extras.php'));
                  if (has_paid_extra()):
?>
                  <li><a class="dropdown-item" href="https://www.simplerisk.com/schedule/support" target="_blank"><i class="fas fa-phone me-1 ms-1"></i><?= $escaper->escapeHtml($lang['PhoneSupport']);?></a></li>
<?php endif; ?>
                </ul>
              </li>
              <!-- End of Help dropdown -->

              <!-- Settings Hub cog (admin OR vm_configure OR im_configure) -->
<?php
    require_once(realpath(__DIR__ . '/includes/settings_catalog.php'));
    if (user_can_access_settings_hub()):
?>
              <li class="nav-item">
                <a class="nav-link waves-effect waves-dark"
                   href="../admin/index.php"
                   title="<?= $escaper->escapeHtmlAttr($lang['Settings']) ?>"
                   aria-label="<?= $escaper->escapeHtmlAttr($lang['Settings']) ?>">
                  <i class="font-24 fas fa-cog align-middle"></i>
                </a>
              </li>
<?php endif; ?>

              <!-- Notifications bell -->
              <li class="nav-item">
                <a class="nav-link waves-effect waves-dark notifications-toggle"
                   href="javascript:void(0)"
                   title="<?= $escaper->escapeHtmlAttr($lang['Notifications']) ?>"
                   aria-label="<?= $escaper->escapeHtmlAttr($lang['Notifications']) ?>"
                   data-label-notifications="<?= $escaper->escapeHtmlAttr($lang['Notifications']) ?>"
                   data-label-unread="<?= $escaper->escapeHtmlAttr($lang['Unread']) ?>"
                   data-label-all="<?= $escaper->escapeHtmlAttr($lang['All']) ?>"
                   data-label-trash="<?= $escaper->escapeHtmlAttr($lang['Trash']) ?>"
                   data-label-selectall="<?= $escaper->escapeHtmlAttr($lang['SelectAll']) ?>"
                   data-label-markread="<?= $escaper->escapeHtmlAttr($lang['MarkRead']) ?>"
                   data-label-delete="<?= $escaper->escapeHtmlAttr($lang['Delete']) ?>"
                   data-label-restore="<?= $escaper->escapeHtmlAttr($lang['Restore']) ?>"
                   data-label-nonotifications="<?= $escaper->escapeHtmlAttr($lang['NoNotifications']) ?>"
                   data-label-nothingintrash="<?= $escaper->escapeHtmlAttr($lang['NothingInTrash']) ?>"
                   data-label-view="<?= $escaper->escapeHtmlAttr($lang['View']) ?>"
                   data-label-close="<?= $escaper->escapeHtmlAttr($lang['Close']) ?>"
                   data-label-promo="<?= $escaper->escapeHtmlAttr($lang['Promo']) ?>"
                   data-label-timeseconds="<?= $escaper->escapeHtmlAttr($lang['TimeSeconds']) ?>"
                   data-label-timeminutes="<?= $escaper->escapeHtmlAttr($lang['TimeMinutes']) ?>"
                   data-label-timehours="<?= $escaper->escapeHtmlAttr($lang['TimeHours']) ?>"
                   data-label-timedayunit="<?= $escaper->escapeHtmlAttr($lang['TimeDayUnit']) ?>">
                  <i class="font-24 fas fa-bell align-middle"></i>
                  <span class="notifications-badge" hidden>0</span>
                </a>
              </li>
              <!-- End of Notifications bell -->

              <!-- Profile dropdown menu -->
<?php
    // Avatar initials from the logged-in user's name (falls back to the person
    // glyph when no name is available). build_profile_initials() is defined in
    // renderutils.php — require it so the call is reachable regardless of the
    // include chain.
    require_once(realpath(__DIR__ . '/includes/renderutils.php'));
    $sr_profile_name = trim((string)($_SESSION['name'] ?? ''));
    $sr_initials     = build_profile_initials($sr_profile_name);
?>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle waves-effect waves-dark"
                   role="button"
                   data-bs-toggle="dropdown"
                   title="<?= $escaper->escapeHtmlAttr($sr_profile_name !== '' ? $sr_profile_name : $lang['Profile']) ?>"
                   aria-label="<?= $escaper->escapeHtmlAttr($lang['Profile']) ?>">
<?php if ($sr_initials !== ''): ?>
                  <span class="sr-avatar"><?= $escaper->escapeHtml($sr_initials) ?></span>
<?php else: ?>
                  <i class="display-7 mdi mdi-account align-middle"></i>
<?php endif; ?>
                </a>
		        <ul class="dropdown-menu dropdown-menu-end animated">
			      <li><a class="dropdown-item" href="../account/profile.php"><i class="fa fa-user me-1 ms-1"></i> <?= $escaper->escapeHtml($lang['MyProfile']);?></a></li>
<?php
                    if (organizational_hierarchy_extra()) {
                        require_once(realpath(__DIR__) . '/extras/organizational_hierarchy/index.php');
                        render_business_unit_selection_menu();
                    }
?>
	              <li><a class="dropdown-item" href="../logout.php"><i class="fa fa-power-off me-1 ms-1"></i><?= $escaper->escapeHtml($lang['Logout']);?></a></li>
                </ul>
              </li>
              <!-- End of Profile dropdown menu -->
              
              
            </ul>
          </div>
        </nav>
      </header>
<?php if (!empty($permissions['show_ai_chat']) && artificial_intelligence_extra() && function_exists('ai_provider_is_configured') && ai_provider_is_configured() && function_exists('ai_render_chat_panel')): ?>
      <?php ai_render_chat_panel(); ?>
<?php endif; ?>

      <div id="load" style="display:none;"><?=$escaper->escapeHtml($lang['SendingRequestPleaseWait'])?></div>
