<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    // Standard page-head for a section-landing page: breadcrumb "Compliance >
    // Define Tests", the left-nav Define Tests leaf highlighted, and the H4 page
    // title. $active_sidebar_submenu and $breadcrumb_title_key are BOTH 'DefineTests'
    // on purpose -- the submenu IS the current page, so sidebar.php renders the
    // submenu as the breadcrumb leaf and (because title === submenu) skips the
    // redundant final crumb rather than duplicating it. A deeper leaf page would
    // instead pass a distinct title (e.g. view_test.php: submenu 'PastAudits',
    // title 'ViewTest' -> "Compliance > Past Audits > View Test").
    $breadcrumb_title_key = "DefineTests";
    $active_sidebar_menu = "Compliance";
    $active_sidebar_submenu = "DefineTests";
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'datetimerangepicker', 'UILayoutWidget', 'CUSTOM:pages/compliance.js', 'CUSTOM:pages/compliance-define-tests.js', 'CUSTOM:common.js'], ['check_compliance' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

    // The legacy native (full-page-reload) POST add-test handler that used to live
    // here (isset($_POST['add_test'])) was removed as part of the Define Tests
    // redesign's Issue 6 fix: the Add Test modal (#test--add / #test-new-form
    // below) is AJAX-only now -- both a click and an Enter keypress submit via
    // POST /api/v2/compliance/tests (createTest(), includes/api.php), handled by
    // js/simplerisk/pages/compliance.js. #add_test is type="button" (not
    // type="submit") and there is no longer an add_test-named field anywhere in
    // #test-new-form, so this form can no longer natively POST here at all --
    // confirmed via grep that #test-new-form / add_test are referenced only by
    // this file and compliance.js (no other page reuses this modal or field name).

    // Check if deleting test
    if (isset($_POST['delete_test'])) {

        $error = false;
        $error_msg = "";

        // check permission
        if (!isset($_SESSION["delete_tests"]) || $_SESSION["delete_tests"] != 1) {

            // display an alert
            set_alert(true, "bad", $lang['NoPermissionForThisAction']);

        } else {

            $test_id = (int)$_POST['test_id'];

            // If team separation is enabled
            if (team_separation_extra()) {

                //Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test')) {

                    $error = true;
                    $error_msg = $lang['NoPermissionForThisTest'];

                }
            }

            if ($error !== true) {

                // Delete a framework control
                delete_framework_control_test($test_id);

                // display an alert
                set_alert(true, "good", $lang['SuccessTestDeleted']);

            } else {

                // display an alert
                set_alert(true, "bad", $error_msg);

            }
        }
    }

    // Compliance insights band (Phase 2) — renders for every compliance user
    // (Core). The Edit-layout control is shown only when the Customization extra
    // is enabled (page-local gate; the UILayout framework itself does not gate it).
    if (check_permission('compliance')) {
        // Collapsible here (unlike Home, where the layout IS the page): this band
        // introduces the grid below it rather than being the content, and its
        // ~120px is roughly two table rows on a 1366x768 laptop.
        (new \includes\Widgets\UILayout('define_tests_insights', [
            'show_edit_layout' => customization_extra(),
            'collapsible' => true,
        ]))->render();
    }
?>
<div class="row">
    <div class="col-12">
    <?php
            // The Define Tests grid is now a client-rendered ".sr-table-card"
            // that floats on the page's gray ground ($sr-default) per the design
            // system's "cards on gray, never a white slab" rule -- so this row
            // carries no bg-white; .content already supplies the gray background.
            // (design-system.md §6): search, the Framework/Family filters, and
            // the quick-filter chip row all live inside the shell this renders,
            // and js/simplerisk/pages/compliance-define-tests.js fetches
            // POST /api/v2/compliance/tests_grid to populate it. No extra
            // card-body wrapper here -- .sr-table-card already supplies its own
            // border/shadow/radius.
            display_framework_controls_in_compliance();
    ?>
    </div>
</div>

<?php
    // Approvers multiselect roster (Phase 3a): every user holding the
    // approve_tests permission, in the {value, name} shape create_multiple_dropdown()
    // expects as its $options 4th arg -- passing $options explicitly (rather than
    // NULL) skips that function's default full-user-table load, which is how this
    // list ends up filtered to just the approve_tests roster instead of every
    // enabled user (contrast with the Tester/Additional Stakeholders fields above,
    // which intentionally do use the full roster).
    $approver_options = array_map(fn($u) => ['value' => $u['value'], 'name' => $u['name']], get_users_with_permission('approve_tests'));
?>
<!-- MODEL WINDOW FOR ADDING TEST -->
<!-- Uses the design-system modal shell (.sr-modal, scss/modules/_sr-modal.scss;
     design-system.md §8): the body is a grey canvas holding titled .sr-qcard
     sections, so this long form reads as four groups rather than fourteen
     stacked rows. The schedule-* class hooks the JS toggles on
     (updateScheduleMode()/cadence handlers, js/simplerisk/pages/compliance.js)
     moved onto the .sr-qfield wrappers -- they still take .d-none the same way,
     just at field granularity instead of whole Bootstrap rows. -->
<div id="test--add" class="modal fade sr-modal" tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form class="" id="test-new-form" method="post" autocomplete="off">
                <div class="modal-header">
                    <span class="sr-modal-icon"><i class="fa fa-vial" aria-hidden="true"></i></span>
                    <h5 class="modal-title"><?= $escaper->escapeHtml($lang['TestAddHeader']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-id-card" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['Identity']); ?></h3>
                            <span class="sr-qcard-sub"><?= $escaper->escapeHtml($lang['IdentitySectionHint']); ?></span>
                        </div>
                        <div class="sr-qcard-body">
                    <div class="sr-qgrid">
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['TestName']); ?><span class="required">*</span></label>
                            <input type="text" name="name" required value="" class="form-control" maxlength="1000" title="<?= $escaper->escapeHtml($lang['TestName']); ?>">
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Tester']); ?><span class="required">*</span></label>
    <?php
                            create_dropdown("enabled_users", NULL, "tester", false, false, false, "required title='{$escaper->escapeHtml($lang['Tester'])}'");
    ?>
                        </div>
                        <div class="sr-qfield sr-qfield--full">
                            <label class="sr-qlabel" for="add_test_control"><?= $escaper->escapeHtml($lang['ControlName']); ?><span class="required">*</span></label>
                            <!-- Populated client-side (compliance-define-tests.js) from the full
                                 control roster (GET /api/v2/compliance/control_roster) into a
                                 required bootstrap-multiselect -- Phase 4a (common tests) lets one
                                 test map to N controls, so this is no longer the old single-select
                                 + disabled-select/hidden-input lock trick. Opened via a control
                                 group's "+ Add test" button pre-selects that one control
                                 (openAddTestModal(), js/simplerisk/pages/compliance.js) but leaves
                                 it editable -- the user may add more; opened via the toolbar's
                                 primary Add Test action it starts empty. Named "controls_add[]"
                                 (mirroring approvers_add[] below) so its DOM id stays unique from
                                 the Edit modal's "controls[]" copy (includes/compliance.php) for
                                 bootstrap-multiselect's per-modal init; remapped to "controls[]" on
                                 submit (compliance.js) to match createTest()'s (includes/api.php)
                                 contract. Deliberately NOT given the "multiselect" class other
                                 create_multiple_dropdown()-rendered fields carry (e.g. approvers_add
                                 below): that class is resetForm()'s (js/simplerisk/common.js) signal
                                 to blindly call .multiselect('refresh') on every match in the form,
                                 which would force-initialize this one with default (unfiltered,
                                 unstyled) options via bootstrap-multiselect's lazy-init fallback if
                                 called before the roster has loaded -- openAddTestModal()
                                 (compliance.js) owns this select's init/select lifecycle instead,
                                 deliberately deferring first init until the roster's real options
                                 exist (see its own comments). -->
                            <!-- No `required` here, deliberately. The picker hides this select
                                 behind the chips box, and the browser refuses to report a
                                 constraint violation on a control it cannot focus: it aborts the
                                 submit and logs "An invalid form control with name='controls[]' is
                                 not focusable", so the submit event never fires and NO handler runs
                                 -- clicking Add with no control did nothing at all, silently. The
                                 rule is enforced where it can actually speak: the click handler
                                 (compliance.js) shows AtLeastOneControlRequired, and createTest()
                                 (includes/api.php) enforces it authoritatively. The label keeps its
                                 required marker. -->
                            <select multiple name="controls_add[]" id="add_test_control" class="form-select sr-picker-value" title="<?= $escaper->escapeHtml($lang['ControlName']); ?>">
                            </select>
                            <span class="sr-qhint"><?= $escaper->escapeHtml($lang['CommonTestControlsHint']); ?></span>
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
    <?php
                            create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders_add");
    ?>
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Teams']); ?></label>
    <?php
                            // BU-scoped when the Org Hierarchy Extra is on --
                            // get_test_modal_team_options() (includes/compliance.php),
                            // which this page already requires. Without it an admin got
                            // every team here beside a BU-limited Tester/Approvers.
                            create_multiple_dropdown("team", NULL, NULL, get_test_modal_team_options());
    ?>
                        </div>
                    </div>
                        </div>
                    </section>

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-calendar-days" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['Schedule']); ?></h3>
                            <span class="sr-qcard-sub"><?= $escaper->escapeHtml($lang['WhenTheAuditInitiates']); ?></span>
                        </div>
                        <div class="sr-qcard-body">
                    <div class="sr-qgrid">
                        <div class="sr-qfield sr-qfield--full schedule-mode-row">
                            <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['Mode']); ?></label>
                            <div class="sr-seg schedule-mode-group" role="group" aria-label="<?= $escaper->escapeHtmlAttr($lang['Schedule']); ?>">
                                <input type="radio" class="btn-check" name="schedule_type" id="add_schedule_type_manual" value="manual" autocomplete="off">
                                <label class="btn" for="add_schedule_type_manual"><?= $escaper->escapeHtml($lang['ScheduleManual']); ?></label>

                                <input type="radio" class="btn-check" name="schedule_type" id="add_schedule_type_interval" value="interval" autocomplete="off">
                                <label class="btn" for="add_schedule_type_interval"><?= $escaper->escapeHtml($lang['ScheduleInterval']); ?></label>

                                <input type="radio" class="btn-check" name="schedule_type" id="add_schedule_type_calendar" value="calendar" autocomplete="off" checked>
                                <label class="btn" for="add_schedule_type_calendar"><?= $escaper->escapeHtml($lang['ScheduleCalendar']); ?></label>
                            </div>
                        </div>
                        <div class="sr-qfield schedule-field-interval">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['TestFrequency']); ?> <span class="sr-qlabel-note">(<?= $escaper->escapeHtml($lang['days']); ?>)</span></label>
                            <input type="number" min="0" max="2147483647" name="test_frequency" value="" class="form-control">
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['LastTestDate']); ?></label>
                            <input type="text" name="last_date" value="" class="form-control datepicker">
                            <span class="sr-qhint"><?= $escaper->escapeHtml($lang['LastTestDateAnchorHint']); ?></span>
                        </div>
                        <div class="sr-qfield schedule-field-offset">
                            <label class="sr-qlabel" for="add_audit_initiation_offset"><?= $escaper->escapeHtml($lang['AuditLeadInDays']); ?></label>
                            <input type="number" name="audit_initiation_offset" id="add_audit_initiation_offset" class="form-control" title="<?= $escaper->escapeHtml($lang['AuditLeadInDays']); ?>" min="0">
                            <span class="sr-qhint"><?= $escaper->escapeHtml($lang['AuditInitiationOffset_explanation']); ?></span>
                        </div>
                        <div class="sr-qfield schedule-field-calendar">
                            <label class="sr-qlabel" for="add_cadence_preset"><?= $escaper->escapeHtml($lang['Cadence']); ?><span class="required">*</span></label>
                            <select id="add_cadence_preset" class="form-select cadence-preset">
                                <option value="daily" data-unit="day" data-interval="1"><?= $escaper->escapeHtml($lang['Daily']); ?></option>
                                <option value="weekly" data-unit="week" data-interval="1"><?= $escaper->escapeHtml($lang['Weekly']); ?></option>
                                <option value="biweekly" data-unit="week" data-interval="2"><?= $escaper->escapeHtml($lang['CadenceBiweekly']); ?></option>
                                <option value="monthly" data-unit="month" data-interval="1" selected><?= $escaper->escapeHtml($lang['Monthly']); ?></option>
                                <option value="quarterly" data-unit="month" data-interval="3"><?= $escaper->escapeHtml($lang['Quarterly']); ?></option>
                                <option value="semiannually" data-unit="month" data-interval="6"><?= $escaper->escapeHtml($lang['CadenceSemiAnnually']); ?></option>
                                <option value="annually" data-unit="year" data-interval="1"><?= $escaper->escapeHtml($lang['Annually']); ?></option>
                                <option value="custom"><?= $escaper->escapeHtml($lang['Custom']); ?></option>
                            </select>
                            <input type="hidden" name="cadence_unit" class="cadence-unit-value" value="month">
                            <input type="hidden" name="cadence_interval" class="cadence-interval-value" value="1">
                        </div>
                        <div class="sr-qfield schedule-field-calendar">
                            <label class="sr-qlabel" for="add_cadence_anchor_date"><?= $escaper->escapeHtml($lang['AnchorDate']); ?><span class="required">*</span></label>
                            <input type="text" name="cadence_anchor_date" id="add_cadence_anchor_date" class="form-control datepicker" title="<?= $escaper->escapeHtml($lang['AnchorDate']); ?>" value="<?= $escaper->escapeHtmlAttr(format_date(date('Y-m-d'))); ?>">
                        </div>
                        <div class="sr-qfield schedule-field-calendar schedule-cadence-custom d-none">
                            <label class="sr-qlabel" for="add_cadence_custom_interval"><?= $escaper->escapeHtml($lang['Cadence']); ?> (<?= $escaper->escapeHtml($lang['Custom']); ?>)<span class="required">*</span></label>
                            <input type="number" min="1" class="form-control cadence-custom-interval" id="add_cadence_custom_interval" value="1">
                        </div>
                        <div class="sr-qfield schedule-field-calendar schedule-cadence-custom d-none">
                            <label class="sr-qlabel" for="add_cadence_custom_unit">&nbsp;</label>
                            <select id="add_cadence_custom_unit" class="form-select cadence-custom-unit">
                                <option value="day"><?= $escaper->escapeHtml($lang['Day']); ?></option>
                                <option value="week"><?= $escaper->escapeHtml($lang['Week']); ?></option>
                                <option value="month" selected><?= $escaper->escapeHtml($lang['Month']); ?></option>
                                <option value="year"><?= $escaper->escapeHtml($lang['Year']); ?></option>
                            </select>
                        </div>
                        <div class="sr-qfield sr-qfield--full schedule-field-calendar">
                            <label class="sr-qlabel"><?= $escaper->escapeHtml($lang['UpcomingOccurrences']); ?></label>
                            <div class="schedule-occurrences-list border rounded p-2" style="max-height:220px;overflow-y:auto;"></div>
                            <div class="schedule-occurrences-empty text-muted small mt-1 d-none"><?= $escaper->escapeHtml($lang['NoUpcomingOccurrences']); ?></div>
                            <div class="schedule-occurrences-error text-danger small mt-1 d-none"><?= $escaper->escapeHtml($lang['FailedToLoadUpcomingOccurrences']); ?></div>
                        </div>
                    </div>
                        </div>
                    </section>

                    <div class="d-none occurrence-row-template">
                        <div class="occurrence-row d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom py-1">
                            <div class="occurrence-date-wrap">
                                <span class="occurrence-date fw-semibold"></span>
                                <span class="badge bg-danger ms-1 occurrence-overdue d-none"><?= $escaper->escapeHtml($lang['Overdue']); ?></span>
                            </div>
                            <div class="occurrence-controls d-flex align-items-center gap-2">
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" class="form-check-input occurrence-skip">
                                    <label class="form-check-label small"><?= $escaper->escapeHtml($lang['SkipOccurrence']); ?></label>
                                </div>
                                <input type="text" class="form-control form-control-sm occurrence-override datepicker" title="<?= $escaper->escapeHtmlAttr($lang['OverrideDate']); ?>" placeholder="<?= $escaper->escapeHtmlAttr($lang['OverrideDate']); ?>">
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="schedule_exceptions" class="schedule-exceptions-value" value="">

                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-list-check" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['ProcedureAndEvidence']); ?></h3>
                            <span class="sr-qcard-tag"><?= $escaper->escapeHtml($lang['Optional']); ?></span>
                        </div>
                        <div class="sr-qcard-body">
                    <div class="sr-qgrid">
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for="add_test_method"><?= $escaper->escapeHtml($lang['TestMethod']); ?></label>
                            <select name="test_method" id="add_test_method" class="form-select" title="<?= $escaper->escapeHtml($lang['TestMethod']); ?>">
                                <option value=""></option>
                                <option value="inquiry"><?= $escaper->escapeHtml($lang['TestMethodInquiry']); ?></option>
                                <option value="observation"><?= $escaper->escapeHtml($lang['TestMethodObservation']); ?></option>
                                <option value="inspection"><?= $escaper->escapeHtml($lang['TestMethodInspection']); ?></option>
                                <option value="reperformance"><?= $escaper->escapeHtml($lang['TestMethodReperformance']); ?></option>
                            </select>
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Approvers']); ?></label>
    <?php
                            // Same-named "approvers[]" would collide (duplicate DOM id)
                            // with the Edit modal's copy (includes/compliance.php) -- rename
                            // to "approvers_add" here (mirroring the
                            // additional_stakeholders_add/additional_stakeholders split
                            // above) so both selects get their own unique id for the
                            // per-modal bootstrap-multiselect init and get remapped back
                            // to the "approvers[]" the API expects at submit time
                            // (js/simplerisk/pages/compliance.js).
                            create_multiple_dropdown("enabled_users", NULL, "approvers_add", $approver_options);
    ?>
                        </div>
                        <!-- Standing rule, stated before the user can break it (amber
                             advisory, not an error). The red #add_sod_warning below is the
                             violation message and only appears once they do. -->
                        <div class="sr-qfield sr-qfield--full">
                            <div class="sr-qnote">
                                <i class="fa fa-triangle-exclamation sr-qnote-ico" aria-hidden="true"></i>
                                <span><?= $escaper->escapeHtml($lang['SeparationOfDutiesNote']); ?></span>
                            </div>
                            <div class="text-danger small d-none sod-warning" id="add_sod_warning"><?= $escaper->escapeHtml($lang['TesterCannotBeApprover']); ?></div>
                        </div>
                        <div class="sr-qfield sr-qfield--full">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Objective']); ?></label>
                            <textarea name="objective" id="add_objective" class="form-control" rows="3" style="max-width:100%;height:auto;"></textarea>
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['TestSteps']); ?></label>
                            <textarea name="test_steps" id="add_test_steps" class="form-control" rows="3" style="max-width:100%;height:auto;"></textarea>
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ExpectedResults']); ?></label>
                            <textarea name="expected_results" id="add_expected_results" class="form-control" rows="3" style="max-width:100%;height:auto;"></textarea>
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Sample']); ?></label>
                            <textarea name="sample" id="add_sample" class="form-control" rows="3" style="max-width:100%;height:auto;"></textarea>
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['RequiredEvidence']); ?></label>
                            <textarea name="required_evidence" id="add_required_evidence" class="form-control" rows="3" style="max-width:100%;height:auto;"></textarea>
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['ApproximateTime']); ?> <span class="sr-qlabel-note">(<?= $escaper->escapeHtml($lang['minutes']); ?>)</span></label>
                            <input type="number" min="0" max="2147483647" name="approximate_time" value="" class="form-control">
                        </div>
                        <div class="sr-qfield">
                            <label class="sr-qlabel" for=""><?= $escaper->escapeHtml($lang['Tags']); ?></label>
                            <select class="test_tags" readonly name="tags[]" multiple placeholder="<?= $escaper->escapeHtml($lang["AddOrSearchTags"]);?>"></select>
                            <!-- A standing limit, not an error: muted like any other
                                 field hint until the user actually exceeds it. -->
                            <span class="sr-qhint"><?= $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></span>
                        </div>
                    </div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <!-- AJAX add (Issue 6, js/simplerisk/pages/compliance.js) -- type="button"
                         (not "submit"), mirroring #test-delete-confirm-yes below: this is no
                         longer the form's implicit-submission default button, so it never
                         triggers a native full-page-reload POST. Both a click on this button
                         and an Enter keypress anywhere in #test-new-form route through the
                         same AJAX POST /api/v2/compliance/tests handler (createTest(),
                         includes/api.php) -- see the 'keydown' handler bound on #test-new-form
                         in compliance.js. -->
                    <button type="button" id="add_test" class="btn btn-danger"><?= $escaper->escapeHtml($lang['Add']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR UPDATING TEST -->
<?php
    display_update_test_modal('define_tests');

    // The Control Name field's faceted picker, shared by the Add and Edit test
    // modals on this page (renders once; see display_control_picker_modal()).
    display_control_picker_modal();
?>

<!-- MODEL WINDOW FOR PROJECT DELETE CONFIRM -->
<div id="test--delete" class="modal fade" tabindex="-1" aria-labelledby="test--delete" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form class="" action="" method="post">
                <input type="hidden" name="test_id" value="" />
                <div class="modal-body">
                    <div class="form-group text-center">
                        <h4 class="modal-title"><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisTest']); ?></h4>
                        <!-- Blast radius. A test is now shared across controls, so
                             deleting from one row can remove it from controls that
                             aren't on screen. Filled in by the .delete-row handler
                             (compliance.js) only when the test is on more than one
                             control; empty and hidden otherwise. -->
                        <p class="sr-confirm-sub" id="test-delete-scope"></p>
                    </div>
                    <div class="text-center project-delete-actions">
                        <button type="button" class="btn btn-dark" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <!-- AJAX delete (Issue 6, js/simplerisk/pages/compliance-define-tests.js) --
                             type="button" (not "submit") so this never falls back to the form's
                             native full-page-reload POST; the click handler calls
                             DELETE /api/v2/compliance/tests/{id} and re-renders the grid in place. -->
                        <button type="button" id="test-delete-confirm-yes" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {

        // buttonText: the chips rendered underneath carry the selection, so the
        // button itself only opens the picker (chipSelectButtonText(),
        // js/simplerisk/pages/compliance.js) instead of repeating the values.
        var chipMultiselectOptions = {
            buttonWidth: '100%',
            buttonText: (typeof chipSelectButtonText === 'function') ? chipSelectButtonText : undefined,
            // Keeps the chips in step with what the user picks in the dropdown
            // (a pick doesn't surface as a change event on the <select>).
            onChange: (typeof chipSelectOnChange === 'function') ? chipSelectOnChange : undefined
        };

        $("#additional_stakeholders_add").multiselect(chipMultiselectOptions);

        $("#additional_stakeholders").multiselect(chipMultiselectOptions);

        $("[name='team[]']").multiselect(chipMultiselectOptions);

        // Approvers (Phase 3a) -- same add/edit unique-id split as
        // additional_stakeholders_add/additional_stakeholders above.
        $("#approvers_add").multiselect(chipMultiselectOptions);

        $("#approvers").multiselect(chipMultiselectOptions);

        // Show each of these selections as removable chips under its button
        // (the artifact's treatment for controls/approvers/teams/stakeholders).
        // The control selects are handled by populateControlMultiselect()
        // (compliance.js) instead, since they're rebuilt when the roster lands.
        if (typeof attachSelectionChips === 'function') {
            $.each(['#additional_stakeholders_add', '#additional_stakeholders', "[name='team[]']", '#approvers_add', '#approvers'], function (i, selector) {
                attachSelectionChips($(selector));
            });
        }

        // Cadence anchor date (Add Test modal) — a static field (not cloned per
        // row like the occurrence-override inputs), so it's initialized once
        // here rather than per-fetch like the Edit modal's copy. Calendar
        // schedules can't anchor to the past, so the picker itself won't offer
        // an earlier date (the client/server validation is the authoritative
        // gate; this is just a UX nudge).
        //
        // openAddTestModal() (compliance.js) re-applies this on every open,
        // because its own maxDate sweep over the form's other datepickers runs
        // after this one -- see the exclusion note there.
        $("#add_cadence_anchor_date").initAsDatePicker({minDate: moment().format(default_date_format)});

        //Have to remove the 'fade' class for the shown event to work for modals
        $('#test--add, #test--update').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
        });

        // The Define Tests grid's own Framework/Family filters and its
        // Add Test control select are initialized by
        // js/simplerisk/pages/compliance-define-tests.js, not here -- the
        // old #filter_by_control_framework/#filter_by_control_family
        // selects (and the hidden-field sync that persisted their state
        // across a full-page form reload) no longer exist; the grid is
        // fetched client-side instead.

        // init WYSIWYG editor
        // Compact (see init_compact_editor, js/WYSIWYG/editor.js): five editors
        // share this modal, and the minimum editor's toolbar wraps to five rows
        // in a half-width card column.
        init_compact_editor('#add_objective');
        init_compact_editor('#add_test_steps');
        init_compact_editor('#add_expected_results');
        init_compact_editor('#add_sample');
        init_compact_editor('#add_required_evidence');

        // Initialize the WYSIWYG editor for the update test modal
        $("#test--update [name=objective]").attr("id", "update_objective");
        init_compact_editor('#update_objective');
        $("#test--update [name=test_steps]").attr("id", "update_test_steps");
        init_compact_editor('#update_test_steps');
        $("#test--update [name=expected_results]").attr("id", "update_expected_results");
        init_compact_editor('#update_expected_results');
        $("#test--update [name=sample]").attr("id", "update_sample");
        init_compact_editor('#update_sample');
        $("#test--update [name=required_evidence]").attr("id", "update_required_evidence");
        init_compact_editor('#update_required_evidence');

        // Client-side SoD (segregation-of-duties) hint (Phase 3a): reset to
        // hidden on load -- the modals aren't populated yet, so there's
        // nothing to conflict on. The server (test_tester_conflicts_with_approvers(),
        // includes/compliance.php) still enforces this authoritatively with a
        // 400; this is a non-blocking UX nudge only.
        if (typeof updateSodWarning === 'function') {
            updateSodWarning($('#test--add'));
            updateSodWarning($('#test--update'));
        }

    });

    if ( window.history.replaceState ) {

        window.history.replaceState( null, null, window.location.href );
        
    }

</script>
<script>
    <?php prevent_form_double_submit_script(['test-new-form', 'update-test-form']); ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>