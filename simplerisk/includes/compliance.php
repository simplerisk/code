<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/bootstrap.php'));
require_once(realpath(__DIR__ . '/cvss.php'));
require_once(realpath(__DIR__ . '/services.php'));
require_once(realpath(__DIR__ . '/alerts.php'));
require_once(realpath(__DIR__ . '/extras.php'));
require_once(realpath(__DIR__ . '/audit_schedule.php'));
// notify_audit_awaiting_approval() (Phase 3b Task 5) calls
// create_notification_for_user_ids() -- declare the require directly here
// rather than relying on a caller (save_test_result()'s pending branch) to
// have pulled notifications.php into scope first.
require_once(realpath(__DIR__ . '/notifications.php'));

// Include the language file
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/******************************************************
 * FUNCTION: DISPLAY FRAMEWORK CONTROLS IN COMPLIANCE *
 * Renders the Define Tests grid's static ".sr-table-card" shell (design-  *
 * system.md §6): a toolbar (search + Framework/Family filters + the       *
 * primary Add Test action), a quick-filter chip row, the table skeleton,  *
 * three pre-rendered empty-state blocks (§10), and the footer skeleton.   *
 * All rows are fetched from POST /api/v2/compliance/tests_grid and       *
 * rendered client-side by js/simplerisk/pages/compliance-define-tests.js *
 * -- this function emits no inline <script> on purpose (a <script> tag   *
 * built from a PHP double-quoted string swallows any '$'-prefixed JS     *
 * variable via PHP interpolation; every behavior here lives in the       *
 * external JS file instead, where '$' is safe).                          *
 ******************************************************/
function display_framework_controls_in_compliance()
{

    global $lang, $escaper;

    $can_add = !empty($_SESSION['define_tests']);
    $can_edit = !empty($_SESSION['edit_tests']);
    $can_delete = !empty($_SESSION['delete_tests']);
    // can_retire_tests() (Task 1, this file) = edit_tests || delete_tests -- either
    // permission grants the softer "hide it" retire/restore action (Task 8).
    $can_retire = can_retire_tests();
    // Batch select is only worth offering when the user can act on a selection
    // -- with neither permission, the bulk bar would show nothing but disabled
    // stub buttons, so skip rendering the checkbox column entirely.
    $can_bulk_select = $can_retire || $can_delete;

    // AI-suggested tests gate (Spec 2 / design-system.md §3) -- the SINGLE gate
    // for every AI surface on this page. ai_capability_enabled('control_test_generation')
    // is extra-tier and default-off, so it resolves false whenever the AI Extra
    // is inactive, and the grid is then byte-identical to today: no ai_suggested
    // filter option, no suggestion rows, no Generate/Create/Review/Dismiss. The
    // grid endpoint (includes/compliance_grid.php) enforces the SAME gate server-
    // side; this flag only decides which chrome renders. require_once at the call
    // site per CLAUDE.md's cross-file-reachability rule -- artificial_intelligence.php
    // is not otherwise in compliance.php's include chain.
    require_once(realpath(__DIR__ . '/artificial_intelligence.php'));
    $ai_gen_enabled = ai_capability_enabled('control_test_generation');

    $frameworks = getAvailableControlFrameworkList(true);
    is_array($frameworks) || $frameworks = [];

    $families = getAvailableControlFamilyList();
    is_array($families) || $families = [];

    // Both selects are long (34 families, and a framework list that grows with
    // every import), and neither source guarantees an order -- so sort by name
    // here rather than making the user scan for one. Case-insensitive and
    // natural, so "ISO 27002" follows "ISO 27001" and doesn't sort as text
    // ahead of it.
    $sort_by_name = static function ($a, $b) {
        return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    };
    usort($frameworks, $sort_by_name);
    usort($families, $sort_by_name);

    // Distinct tags currently used on tests -- populates the chip-row "Tag"
    // filter select. Sourced from tags_taggees (type='test') joined to the
    // tags table and to framework_control_tests so only tags on an existing
    // test surface. Empty result => the select shows only its "Any tag" option.
    $test_tags = [];
    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT tg.tag
        FROM `tags_taggees` tt
            JOIN `tags` tg ON tg.id = tt.tag_id
            JOIN `framework_control_tests` fct ON fct.id = tt.taggee_id
        WHERE tt.type = 'test' AND tg.tag <> ''
        ORDER BY tg.tag
    ");
    $stmt->execute();
    $test_tags = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'tag');
    db_close($db);

    echo "
        <div class='sr-table-card' id='define-tests-grid'
            data-can-add='" . ($can_add ? 'true' : 'false') . "'
            data-can-edit='" . ($can_edit ? 'true' : 'false') . "'
            data-can-delete='" . ($can_delete ? 'true' : 'false') . "'
            data-can-retire='" . ($can_retire ? 'true' : 'false') . "'
            data-can-bulk-select='" . ($can_bulk_select ? 'true' : 'false') . "'
            data-ai-gen-enabled='" . ($ai_gen_enabled ? 'true' : 'false') . "'>
            <div class='sr-table-toolbar' id='define-tests-toolbar'>
                <div class='sr-table-title'>
                    {$escaper->escapeHtml($lang['DefineTests'])}
                    <span class='sr-table-count d-none' id='define-tests-count'></span>
                    <span class='sr-table-count sr-table-count--attn d-none' id='define-tests-overdue-pill'></span>
                </div>
                <div class='sr-table-tools'>
                    <div class='dt-search'>
                        <input type='search' id='define-tests-search' class='form-control' placeholder='{$escaper->escapeHtmlAttr($lang['SearchTestsPlaceholder'])}' aria-label='{$escaper->escapeHtmlAttr($lang['SearchTestsPlaceholder'])}'>
                    </div>
    ";
    if ($can_add) {
        echo "
                    <button type='button' class='btn btn-danger' id='define-tests-toolbar-add'>+ {$escaper->escapeHtml($lang['AddTest'])}</button>
        ";
    }
    echo "
                </div>
            </div>
    ";
    // Batch-select bulk bar (Task 8) -- replaces .sr-table-toolbar above once one
    // or more test-row checkboxes are checked (compliance-define-tests.js's
    // updateBulkBar()). Reassign tester / Set schedule are deliberately stubbed
    // (disabled, ComingSoon tooltip) -- only Retire/Delete are wired this task.
    echo "
            <div class='sr-bulk-bar d-none' id='define-tests-bulk-bar'>
                <button type='button' class='sr-bulk-clear' id='define-tests-bulk-clear' aria-label='{$escaper->escapeHtmlAttr($lang['Clear'])}'>&times;</button>
                <span class='sr-bulk-count' id='define-tests-bulk-count'></span>
                <div class='sr-bulk-actions'>
                    <button type='button' class='btn btn-outline-secondary btn-sm' id='define-tests-bulk-reassign' disabled title='{$escaper->escapeHtmlAttr($lang['ComingSoon'])}'>{$escaper->escapeHtml($lang['ReassignTester'])}</button>
                    <button type='button' class='btn btn-outline-secondary btn-sm' id='define-tests-bulk-schedule' disabled title='{$escaper->escapeHtmlAttr($lang['ComingSoon'])}'>{$escaper->escapeHtml($lang['SetSchedule'])}</button>
    ";
    if ($can_retire) {
        echo "
                    <button type='button' class='btn btn-outline-secondary btn-sm' id='define-tests-bulk-retire'>{$escaper->escapeHtml($lang['Retire'])}</button>
        ";
    }
    if ($can_delete) {
        echo "
                    <button type='button' class='btn btn-outline-danger btn-sm' id='define-tests-bulk-delete'>{$escaper->escapeHtml($lang['Delete'])}</button>
        ";
    }
    // "Create selected" approves the selected AI suggestion rows as-drafted. Only
    // rendered when the AI control-test capability is enabled (the same gate the
    // grid/filter/Generate use); starts hidden and is revealed by updateBulkBar()
    // (compliance-define-tests.js) only while the selection contains suggestions,
    // so a plain real-test selection never shows it.
    if ($ai_gen_enabled) {
        echo "
                    <button type='button' class='btn btn-outline-secondary btn-sm d-none' id='define-tests-bulk-create'>{$escaper->escapeHtml($lang['CreateSelected'])}</button>
        ";
    }
    echo "
                </div>
            </div>
            <!-- Every filter lives on this row, and only filters live here. The
                 toolbar above answers 'what is this and what can I do here'
                 (title, counts, search, Add test); this row answers 'narrow what
                 I'm seeing'. Frameworks/Families/Coverage used to sit upstairs
                 purely because they were built first -- a 3/6 split across two
                 rows on no principle a reader could perceive. Order is broad to
                 narrow: which CONTROLS (framework, family, coverage), then which
                 TESTS (status, result, tester, schedule, tag, show). -->
            <!-- Narrow widths (design-system.md 6b): nine selects wrap to as many
                 as five rows and push the first test row off the screen before the
                 table has done anything wrong. Below the compact tier this button
                 replaces the row and the row becomes its sheet. The COUNT is the
                 point -- a filtered view that looks unfiltered is how a user
                 concludes the data is missing -- so it is rendered by JS from the
                 same state the grid queries with, never from the markup. Hidden by
                 CSS at full width, where the row is simply on screen. -->
            <button type='button' class='sr-qf-toggle' id='define-tests-filters-toggle'
                    aria-expanded='false' aria-controls='define-tests-quickfilters'>
                <i class='fa fa-filter' aria-hidden='true'></i>
                <span>{$escaper->escapeHtml($lang['Filters'])}</span>
                <span class='sr-qf-toggle-count' id='define-tests-filters-count' hidden></span>
            </button>
            <div class='sr-table-quickfilters' id='define-tests-quickfilters'>
                <div class='sr-qf-selects'>
                    <select id='define-tests-framework-filter' class='form-select' multiple title='{$escaper->escapeHtmlAttr($lang['ControlFramework'])}' aria-label='{$escaper->escapeHtmlAttr($lang['ControlFramework'])}'>
    ";
    foreach ($frameworks as $framework) {
        echo "<option value='" . (int)$framework['value'] . "'>{$escaper->escapeHtml($framework['name'])}</option>";
    }
    echo "
                    </select>
                    <select id='define-tests-family-filter' class='form-select' multiple title='{$escaper->escapeHtmlAttr($lang['ControlFamily'])}' aria-label='{$escaper->escapeHtmlAttr($lang['ControlFamily'])}'>
    ";
    foreach ($families as $family) {
        echo "<option value='" . (int)$family['value'] . "'>{$escaper->escapeHtml($family['name'])}</option>";
    }
    echo "
                    </select>
                    <!-- Defaults to 'with' (All tests): a control with no test has
                         nothing to show in a grid OF TESTS, and on a real SCF import
                         the old 'all' default buried the handful of controls that
                         have tests under ~1,500 that don't. It is also what makes
                         every test-level filter narrow the control list -- 'with'
                         adds HAVING COUNT(t.id) > 0 to the candidate query
                         (resolve_candidate_control_ids(), includes/compliance_grid.php),
                         and that join already carries the retired-mode predicate,
                         so Retired only now returns just the controls that actually
                         hold a retired test. Coverage gaps stay one click away via
                         Untested / All controls. -->
                    <select id='define-tests-coverage-filter' class='form-select' title='{$escaper->escapeHtmlAttr($lang['Coverage'])}' aria-label='{$escaper->escapeHtmlAttr($lang['Coverage'])}'>
                        <option value='with' selected>{$escaper->escapeHtml($lang['CoverageWithTests'])}</option>
                        <option value='all'>{$escaper->escapeHtml($lang['CoverageAllControls'])}</option>
                        <option value='gaps'>{$escaper->escapeHtml($lang['UntestedControls'])}</option>
                    </select>
                    <!-- Status and Results replace the Overdue/Due-soon/Failing/Passing
                         chips: same four filters, but as two named dimensions instead of
                         four loose toggles whose grouping you had to infer. They drive
                         the SAME quick flags the insights tiles drill through to, so a
                         tile click and a select choice remain one mechanism. Results
                         carries Inconclusive and Not tested too -- the grid renders
                         those states, so a results filter that couldn't name them would
                         be lying by omission. -->
                    <select id='define-tests-status-filter' class='form-select' title='{$escaper->escapeHtmlAttr($lang['DueStatus'])}' aria-label='{$escaper->escapeHtmlAttr($lang['DueStatus'])}'>
                        <option value=''>{$escaper->escapeHtml($lang['AllStatuses'])}</option>
                        <option value='overdue'>{$escaper->escapeHtml($lang['Overdue'])}</option>
                        <option value='due_soon'>{$escaper->escapeHtml($lang['DueSoon'])}</option>
                        <option value='scheduled'>{$escaper->escapeHtml($lang['OnTrack'])}</option>
                    </select>
                    <select id='define-tests-result-filter' class='form-select' title='{$escaper->escapeHtmlAttr($lang['LastResult'])}' aria-label='{$escaper->escapeHtmlAttr($lang['LastResult'])}'>
                        <option value=''>{$escaper->escapeHtml($lang['AllResults'])}</option>
                        <option value='passing'>{$escaper->escapeHtml($lang['Passing'])}</option>
                        <option value='failing'>{$escaper->escapeHtml($lang['Failing'])}</option>
                        <option value='inconclusive'>{$escaper->escapeHtml($lang['Inconclusive'])}</option>
                        <option value='not_tested'>{$escaper->escapeHtml($lang['NotTested'])}</option>
                    </select>
                    <!-- Tester filter. Replaces the self-only My-tests chip: that
                         could only ever answer which tests are MINE, and nothing else
                         in the grid could answer which are someone else s -- search
                         included, since build_grid_search() covers test/control names
                         and framework references, not people. Options come from the
                         grid response and are scoped by the org hierarchy; see
                         get_define_tests_tester_options() in
                         includes/compliance_grid.php. -->
                    <select id='define-tests-tester-filter' class='form-select' title='{$escaper->escapeHtmlAttr($lang['Tester'])}' aria-label='{$escaper->escapeHtmlAttr($lang['Tester'])}'>
                        <option value=''>{$escaper->escapeHtml($lang['AllTesters'])}</option>
                    </select>
                    <select id='define-tests-schedule-filter' class='form-select' title='{$escaper->escapeHtmlAttr($lang['Schedule'])}' aria-label='{$escaper->escapeHtmlAttr($lang['Schedule'])}'>
                        <option value=''>{$escaper->escapeHtml($lang['AnySchedule'])}</option>
                        <option value='calendar'>{$escaper->escapeHtml($lang['ScheduleCalendar'])}</option>
                        <option value='interval'>{$escaper->escapeHtml($lang['ScheduleInterval'])}</option>
                        <option value='manual'>{$escaper->escapeHtml($lang['ScheduleManual'])}</option>
                    </select>
                    <select id='define-tests-tag-filter' class='form-select' title='{$escaper->escapeHtmlAttr($lang['Tags'])}' aria-label='{$escaper->escapeHtmlAttr($lang['Tags'])}'>
                        <option value=''>{$escaper->escapeHtml($lang['AnyTag'])}</option>
    ";
    foreach ($test_tags as $test_tag) {
        echo "<option value='{$escaper->escapeHtmlAttr($test_tag)}'>{$escaper->escapeHtml($test_tag)}</option>";
    }
    echo "
                    </select>
                    <!-- Defaults to 'active'. This is an operational surface -- what
                         do I have to run, what's late, what's failing -- and a retired
                         test is none of those. It also keeps the grid in step with the
                         insights band above it, whose tiles count ACTIVE tests only
                         (count_active_tests() etc., includes/compliance_grid.php); an
                         all-by-default grid would show 11 rows under a band reading 6
                         total. Nothing is buried by this: a control whose only
                         test is retired still counts as a coverage gap in the Untested
                         view and the Untested Controls tile, because both count active
                         tests. 'All tests' and 'Retired only' are one click away. -->
                    <!-- data-caption bakes the dimension name INTO the closed
                         control (srSelectRender(), compliance-define-tests.js):
                         it reads 'Tests: Active - 142' instead of just the
                         option, and appends the selected option's count. The
                         option labels are bare words (Active / All / Retired / AI
                         suggested), not 'Active tests', so the caption doesn't read
                         the redundant 'Tests: Active tests'. Only this
                         select opts in; the others are unaffected (the renderer
                         guards on data-caption's presence). The AI suggested option
                         is gated on the AI control-test capability
                         (design-system.md sections 3 and 5): it and its count exist
                         only when that capability is enabled. -->
                    <select id='define-tests-show-filter' class='form-select' data-caption='{$escaper->escapeHtmlAttr($lang['Tests'])}' title='{$escaper->escapeHtmlAttr($lang['Show'])}' aria-label='{$escaper->escapeHtmlAttr($lang['Show'])}'>
                        <option value='active' selected>{$escaper->escapeHtml($lang['Active'])}</option>
                        <option value='all'>{$escaper->escapeHtml($lang['All'])}</option>
                        <option value='retired_only'>{$escaper->escapeHtml($lang['Retired'])}</option>
                        " . ($ai_gen_enabled ? "<option value='ai_suggested'>{$escaper->escapeHtml($lang['AiSuggested'])}</option>" : "") . "
                    </select>
                </div>
            </div>
            <div class='sr-table-scroll'>
                <table class='sr-table' id='define-tests-table'>
                    <thead>
                        <tr>
                            <th class='sr-check-col'>" . ($can_bulk_select ? "<input type='checkbox' class='form-check-input' id='define-tests-select-all' aria-label='{$escaper->escapeHtmlAttr($lang['SelectAll'])}'>" : "") . "</th>
                            <th class='sr-caret-col'><span class='visually-hidden'>{$escaper->escapeHtml($lang['Expand'])}</span></th>
                            <!-- Control column: only present in the sorted (flat) view,
                                 where there are no group rows to say which control a
                                 test belongs to. Its header doubles as the way back --
                                 sorting BY control is the grouped view, so clicking it
                                 clears the sort rather than adding another ordering. -->
                            <th class='sr-control-col sr-sortable d-none' data-sort='control' role='columnheader' aria-sort='none' tabindex='0'>{$escaper->escapeHtml($lang['Control'])}<i class='fa sr-sort-icon' aria-hidden='true'></i></th>
                            <th class='sr-id-col sr-sortable' data-sort='id' role='columnheader' aria-sort='none' tabindex='0'>{$escaper->escapeHtml($lang['TestID'])}<i class='fa sr-sort-icon' aria-hidden='true'></i></th>
                            <th class='sr-name-col sr-sortable' data-sort='name' role='columnheader' aria-sort='none' tabindex='0'>{$escaper->escapeHtml($lang['TestName'])}<i class='fa sr-sort-icon' aria-hidden='true'></i></th>
                            <th class='sr-tester-col sr-sortable' data-sort='tester' role='columnheader' aria-sort='none' tabindex='0'>{$escaper->escapeHtml($lang['Tester'])}<i class='fa sr-sort-icon' aria-hidden='true'></i></th>
                            <th class='sr-sched-col sr-sortable' data-sort='schedule' role='columnheader' aria-sort='none' tabindex='0'>{$escaper->escapeHtml($lang['Schedule'])}<i class='fa sr-sort-icon' aria-hidden='true'></i></th>
                            <th class='sr-lastdate-col sr-sortable' data-sort='last_date' role='columnheader' aria-sort='none' tabindex='0'>{$escaper->escapeHtml($lang['LastTested'])}<i class='fa sr-sort-icon' aria-hidden='true'></i></th>
                            <th class='sr-result-col sr-sortable' data-sort='last_result' role='columnheader' aria-sort='none' tabindex='0'>{$escaper->escapeHtml($lang['LastResult'])}<i class='fa sr-sort-icon' aria-hidden='true'></i></th>
                            <th class='sr-due-col sr-sortable' data-sort='next_due' role='columnheader' aria-sort='none' tabindex='0'>{$escaper->escapeHtml($lang['NextDue'])}<i class='fa sr-sort-icon' aria-hidden='true'></i></th>
                            <th class='sr-actions-col'><span class='visually-hidden'>{$escaper->escapeHtml($lang['Actions'])}</span></th>
                        </tr>
                    </thead>
                    <tbody id='define-tests-tbody'></tbody>
                </table>
            </div>
            <div class='sr-table-empty d-none' id='define-tests-empty-nodata'>
                <div class='sr-table-empty-icon'><i class='fa fa-clipboard-list' aria-hidden='true'></i></div>
                <div class='sr-table-empty-title'>{$escaper->escapeHtml($lang['NoControlsDefinedYet'])}</div>
                <div class='sr-table-empty-body'>{$escaper->escapeHtml($lang['NoControlsDefinedYetBody'])}</div>
            </div>
            <div class='sr-table-empty d-none' id='define-tests-empty-noresults'>
                <div class='sr-table-empty-icon'><i class='fa fa-search' aria-hidden='true'></i></div>
                <div class='sr-table-empty-title'>{$escaper->escapeHtml($lang['NoTestsMatchFilters'])}</div>
                <div class='sr-table-empty-body'>{$escaper->escapeHtml($lang['NoTestsMatchFiltersBody'])}</div>
                <div class='sr-table-empty-action'><button type='button' class='btn btn-outline-secondary btn-sm' id='define-tests-clear-filters'>{$escaper->escapeHtml($lang['ClearFilters'])}</button></div>
            </div>
            <div class='sr-table-empty sr-table-empty-danger d-none' id='define-tests-empty-error'>
                <div class='sr-table-empty-icon'><i class='fa fa-exclamation-triangle' aria-hidden='true'></i></div>
                <div class='sr-table-empty-title'>{$escaper->escapeHtml($lang['CouldNotLoadTests'])}</div>
                <div class='sr-table-empty-body'>{$escaper->escapeHtml($lang['CouldNotLoadTestsBody'])}</div>
                <div class='sr-table-empty-action'><button type='button' class='btn btn-outline-secondary btn-sm' id='define-tests-retry'>{$escaper->escapeHtml($lang['Retry'])}</button></div>
            </div>
            <div class='sr-table-foot'>
                <div class='dt-info' id='define-tests-info'></div>
                <div class='sr-table-foot-right'>
                    <div class='dt-length'>
                        <label>{$escaper->escapeHtml($lang['Show'])}
                            <select id='define-tests-length' class='form-select'>
                                <option value='10'>10</option>
                                <option value='25' selected>25</option>
                                <option value='50'>50</option>
                                <option value='100'>100</option>
                                <option value='-1'>{$escaper->escapeHtml($lang['All'])}</option>
                            </select>
                        </label>
                    </div>
                    <div class='dt-paging' id='define-tests-pager'></div>
                </div>
            </div>
        </div>

        <!-- MODAL WINDOW FOR BULK RETIRE CONFIRM (Task 8) -->
        <div id='define-tests-bulk-retire-confirm' class='modal fade' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered'>
                <div class='modal-content'>
                    <div class='modal-body'>
                        <div class='form-group text-center'>
                            <h4 class='modal-title' id='define-tests-bulk-retire-confirm-text'></h4>
                        </div>
                        <div class='text-center project-delete-actions'>
                            <button type='button' class='btn btn-dark' data-bs-dismiss='modal' aria-hidden='true'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                            <button type='button' class='btn btn-submit' id='define-tests-bulk-retire-confirm-yes'>{$escaper->escapeHtml($lang['Yes'])}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL WINDOW FOR BULK DELETE CONFIRM (Task 8) -->
        <div id='define-tests-bulk-delete-confirm' class='modal fade' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered'>
                <div class='modal-content'>
                    <div class='modal-body'>
                        <div class='form-group text-center'>
                            <h4 class='modal-title' id='define-tests-bulk-delete-confirm-text'></h4>
                        </div>
                        <div class='text-center project-delete-actions'>
                            <button type='button' class='btn btn-dark' data-bs-dismiss='modal' aria-hidden='true'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                            <button type='button' class='btn btn-submit' id='define-tests-bulk-delete-confirm-yes'>{$escaper->escapeHtml($lang['Yes'])}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL WINDOW FOR REMOVE-FROM-CONTROL CONFIRM -->
        <!-- Detaching one (test, control) pairing, which is what a grid row
             actually is. Deliberately a confirm rather than an immediate action
             like retire/restore: it is reversible (re-apply the test), but the
             row vanishes from the group you were looking at, and the whole point
             of the copy is to say what SURVIVES -- the sentence naming the other
             controls is why the user can tell this apart from Delete. -->
        <div id='define-tests-remove-control-confirm' class='modal fade' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered'>
                <div class='modal-content'>
                    <div class='modal-body'>
                        <div class='form-group text-center'>
                            <h4 class='modal-title' id='define-tests-remove-control-confirm-text'></h4>
                            <p class='sr-confirm-sub' id='define-tests-remove-control-confirm-sub'></p>
                        </div>
                        <div class='text-center project-delete-actions'>
                            <button type='button' class='btn btn-dark' data-bs-dismiss='modal' aria-hidden='true'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                            <button type='button' class='btn btn-submit' id='define-tests-remove-control-confirm-yes'>{$escaper->escapeHtml($lang['RemoveTestFromControl'])}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL WINDOW FOR APPLY A COMMON TEST -->
        <!-- Applies one or more EXISTING tests to an additional control, i.e.
             makes them common tests. The select is populated client-side from
             GET /api/v2/compliance/tests (compliance-define-tests.js), which is
             already scoped to active (non-retired) tests. -->
        <div id='apply-common-test' class='modal fade sr-modal' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog modal-dialog-centered'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <span class='sr-modal-icon'><i class='fa fa-link' aria-hidden='true'></i></span>
                        <h4 class='modal-title'>{$escaper->escapeHtml($lang['ApplyCommonTests'])}</h4>
                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='{$escaper->escapeHtmlAttr($lang['Cancel'])}'></button>
                    </div>
                    <div class='modal-body'>
                        <section class='sr-qcard'>
                            <div class='sr-qcard-head'>
                                <span class='sr-qcard-icon'><i class='fa fa-vial-circle-check' aria-hidden='true'></i></span>
                                <h3>{$escaper->escapeHtml($lang['Tests'])}</h3>
                            </div>
                            <div class='sr-qcard-body'>
                                <div class='sr-qstack'>
                                    <div class='sr-qfield'>
                                        <label class='sr-qlabel' for='apply-common-test-select'>{$escaper->escapeHtml($lang['Tests'])}<span class='required'>*</span></label>
                                        <select multiple id='apply-common-test-select' class='form-select' title='{$escaper->escapeHtmlAttr($lang['SelectOneOrMoreTests'])}'></select>
                                        <span class='sr-qhint'>{$escaper->escapeHtml($lang['ApplyCommonTestHint'])}</span>
                                    </div>
                                </div>
                                <div class='text-danger small d-none' id='apply-common-test-error'></div>
                            </div>
                        </section>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-dark' data-bs-dismiss='modal'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                        <button type='button' class='btn btn-submit' id='apply-common-test-confirm'>{$escaper->escapeHtml($lang['Apply'])}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL WINDOW FOR TEST HISTORY -->
        <!-- The test's audit history: every run, newest first, each row deep-
             linking to view_test.php (truly-closed runs) or testing.php (open
             ones). Populated client-side from
             GET /api/v2/compliance/tests/{id}/audits (compliance-define-tests.js).
             Read-only -- initiating or editing a run happens on those pages. -->
        <!-- MODAL WINDOW FOR VIEW TEST (read-only) -->
        <!-- The whole test definition, readable by anyone who can see the row.
             Exists because the Edit modal is gated on edit_tests, which left a
             read-only compliance user able to see a test's NAME in the grid and
             nothing else -- not its objective, not its schedule, not who tests
             it. Fed by GET /api/v2/compliance/tests/{id} (compliance read
             permission + per-test access), so no new endpoint and no new gate. -->
        <div id='test-view' class='modal fade sr-modal' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <span class='sr-modal-icon'><i class='fa fa-eye' aria-hidden='true'></i></span>
                        <h4 class='modal-title' id='test-view-title'>{$escaper->escapeHtml($lang['ViewTest'])}</h4>
                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='{$escaper->escapeHtmlAttr($lang['Cancel'])}'></button>
                    </div>
                    <div class='modal-body'>
                        <div id='test-view-error' class='sr-view-error d-none'></div>
                        <div id='test-view-body'></div>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn sr-qcancel' data-bs-dismiss='modal'>{$escaper->escapeHtml($lang['Close'])}</button>
                    </div>
                </div>
            </div>
        </div>

        <div id='test-history' class='modal fade sr-modal' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog modal-dialog-centered'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <span class='sr-modal-icon'><i class='fa fa-clock-rotate-left' aria-hidden='true'></i></span>
                        <h4 class='modal-title'>{$escaper->escapeHtml($lang['History'])}</h4>
                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='{$escaper->escapeHtmlAttr($lang['Cancel'])}'></button>
                    </div>
                    <div class='modal-body'>
                        <section class='sr-qcard'>
                            <div class='sr-qcard-head'>
                                <span class='sr-qcard-icon'><i class='fa fa-vial' aria-hidden='true'></i></span>
                                <!-- The test's name -- filled from the API response,
                                     never from the grid row, so the modal states what
                                     the server actually resolved the id to. -->
                                <h3 class='sr-qcard-title' id='test-history-name'></h3>
                            </div>
                            <div class='sr-qcard-body'>
                                <div id='test-history-body'></div>
                                <div class='text-danger small d-none' id='test-history-error'></div>
                            </div>
                        </section>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-dark' data-bs-dismiss='modal'>{$escaper->escapeHtml($lang['Close'])}</button>
                    </div>
                </div>
            </div>
        </div>
    ";
}

/*******************************************************************
 * FUNCTION: GET TEST MODAL TEAM OPTIONS                            *
 * Team options for the Add/Edit Test modals, scoped to the         *
 * viewer's selected business unit when the Organizational          *
 * Hierarchy Extra is enabled -- for EVERY user, admins included.   *
 *                                                                  *
 * create_multiple_dropdown('team') would normally source           *
 * get_custom_table('team'), which has exactly this BU-scoped query *
 * but skips it for admins (SR-1954). That left these modals        *
 * self-contradictory: Tester, Additional Stakeholders and          *
 * Approvers all come from BU-scoped sources, so an admin saw three *
 * fields limited to their business unit next to a Team(s) field    *
 * offering every team in the instance -- and could put a test on a *
 * team outside the business unit the rest of the form was scoped   *
 * to.                                                              *
 *                                                                  *
 * Reuses the Extra's own                                           *
 * get_available_teams_for_selected_business_unit_of_user() rather  *
 * than repeating its SQL here, so there is one definition of       *
 * "teams in my business unit" to keep correct.                     *
 *                                                                  *
 * Returns NULL when the Extra is disabled, which is                *
 * create_multiple_dropdown()'s signal to fall back to every team   *
 * (its $options === NULL branch) -- i.e. no behaviour change for   *
 * instances without the Extra.                                     *
 *                                                                  *
 * NOTE: this is a local fix for these two modals. The global        *
 * helper's admin exemption is SR-1954 and still stands; when that  *
 * lands, this function can collapse back to a plain                *
 * create_multiple_dropdown('team') call.                           *
 *******************************************************************/
function get_test_modal_team_options() {

    if (!organizational_hierarchy_extra()) {
        return NULL;
    }

    require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));

    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        // No session user to resolve a business unit for: offer nothing rather
        // than falling back to every team, since the Extra IS enabled here.
        return [];
    }

    return get_available_teams_for_selected_business_unit_of_user($uid);
}

/******************************************************************************
 * FUNCTION: DISPLAY CONTROL PICKER MODAL                                      *
 *                                                                             *
 * The faceted picker behind the Add/Edit Test modals' Control Name field.      *
 *                                                                             *
 * WHY A DIALOG AND NOT A DROPDOWN: a real SCF import is ~1,500 controls. The   *
 * dropdown this replaces rendered every one of them into a 280px menu inside   *
 * a 640px modal, so each name wrapped to three lines and about five were       *
 * readable at a time. Past a few dozen options the question stops being        *
 * "which of these" and becomes "how do I get to the right neighbourhood",      *
 * which is a job for narrowing, not scrolling.                                 *
 *                                                                             *
 * Three steps, none of them required: framework, then family (re-counted       *
 * inside the chosen framework), then the controls that survive. Someone who    *
 * knows the number types it and never touches a facet.                         *
 *                                                                             *
 * Framework/family are a FILTER, not a tree -- most controls map into several  *
 * frameworks, so the same control is reachable by more than one path and is    *
 * still one control.                                                           *
 *                                                                             *
 * Generic `.sr-picker-*` naming, not `control-picker-*`: the pattern is        *
 * "choose from a roster too large to scroll", and the ids are the only         *
 * control-specific part. Facet OPTIONS are rendered server-side (escaped here,  *
 * from the same getAvailableControlFrameworkList()/getAvailableControlFamily-  *
 * List() the grid toolbar uses) so labels have one source; the client only      *
 * counts, filters and toggles them.                                            *
 *                                                                             *
 * Rendered at most once per page -- both callers (compliance/index.php and     *
 * compliance/audit_initiation.php) may render a test modal, and duplicate ids  *
 * would leave the second copy inert.                                           *
 ******************************************************************************/
function display_control_picker_modal() {
    global $lang, $escaper;

    static $already_rendered = false;
    if ($already_rendered) {
        return;
    }
    $already_rendered = true;

    $frameworks = getAvailableControlFrameworkList(true);
    is_array($frameworks) || $frameworks = [];

    $families = getAvailableControlFamilyList();
    is_array($families) || $families = [];

    // Same natural, case-insensitive sort the grid toolbar applies, so a
    // framework sits in the same place in both lists.
    $sort_by_name = static function ($a, $b) {
        return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    };
    usort($frameworks, $sort_by_name);
    usort($families, $sort_by_name);

    // data-picker-facet carries the id the roster's `family`/`frameworks` ids
    // are matched against; the count span is filled client-side.
    $facet_options = static function ($rows, $facet) use ($escaper) {
        $html = '';
        foreach ($rows as $row) {
            $id = (int)($row['value'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = (string)($row['name'] ?? '');
            $html .= "
                        <button type='button' class='sr-picker-facet' data-picker-facet='{$facet}' data-picker-value='{$id}'
                                aria-pressed='false' title='{$escaper->escapeHtmlAttr($name)}'>
                            <span class='sr-picker-facet-label'>{$escaper->escapeHtml($name)}</span>
                            <span class='sr-picker-facet-count'></span>
                        </button>";
        }

        return $html;
    };

    echo "
        <div id='control-picker' class='modal fade sr-modal sr-picker-modal' tabindex='-1' aria-hidden='true' aria-labelledby='control-picker-title'>
            <div class='modal-dialog modal-xl modal-dialog-centered'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <span class='sr-modal-icon'><i class='fa fa-list-check' aria-hidden='true'></i></span>
                        <h5 class='modal-title' id='control-picker-title'>{$escaper->escapeHtml($lang['ChooseControls'])}</h5>
                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='{$escaper->escapeHtmlAttr($lang['Close'])}'></button>
                    </div>

                    <div class='sr-picker-search'>
                        <i class='fa fa-magnifying-glass sr-picker-search-icon' aria-hidden='true'></i>
                        <input type='text' id='control-picker-search' class='sr-picker-search-input' autocomplete='off'
                               placeholder='{$escaper->escapeHtmlAttr($lang['SearchControlsPlaceholder'])}'
                               aria-label='{$escaper->escapeHtmlAttr($lang['SearchControlsPlaceholder'])}'>
                        <span class='sr-picker-scope' id='control-picker-scope'></span>
                    </div>

                    <div class='sr-picker-panes'>
                        <div class='sr-picker-pane sr-picker-pane--facet'>
                            <div class='sr-picker-pane-head'>
                                <span class='sr-picker-step'>1</span>
                                <span>{$escaper->escapeHtml($lang['Framework'])}</span>
                                <button type='button' class='sr-picker-clear' data-picker-clear='framework'>{$escaper->escapeHtml($lang['Clear'])}</button>
                            </div>
                            <div class='sr-picker-scroll' id='control-picker-frameworks'>
                                <button type='button' class='sr-picker-facet' data-picker-facet='framework' data-picker-value='' aria-pressed='true'>
                                    <span class='sr-picker-facet-label'>{$escaper->escapeHtml($lang['AllFrameworks'])}</span>
                                    <span class='sr-picker-facet-count'></span>
                                </button>"
                                .$facet_options($frameworks, 'framework')."
                            </div>
                        </div>

                        <div class='sr-picker-pane sr-picker-pane--facet'>
                            <div class='sr-picker-pane-head'>
                                <span class='sr-picker-step'>2</span>
                                <span>{$escaper->escapeHtml($lang['ControlFamily'])}</span>
                                <button type='button' class='sr-picker-clear' data-picker-clear='family'>{$escaper->escapeHtml($lang['Clear'])}</button>
                            </div>
                            <div class='sr-picker-scroll' id='control-picker-families'>
                                <button type='button' class='sr-picker-facet' data-picker-facet='family' data-picker-value='' aria-pressed='true'>
                                    <span class='sr-picker-facet-label'>{$escaper->escapeHtml($lang['AllFamilies'])}</span>
                                    <span class='sr-picker-facet-count'></span>
                                </button>"
                                .$facet_options($families, 'family')."
                            </div>
                        </div>

                        <div class='sr-picker-pane sr-picker-pane--list'>
                            <div class='sr-picker-pane-head'>
                                <span class='sr-picker-step'>3</span>
                                <span>{$escaper->escapeHtml($lang['Control'])}</span>
                                <span class='sr-picker-pane-count' id='control-picker-count'></span>
                            </div>
                            <div class='sr-picker-scroll' id='control-picker-list' role='listbox' aria-multiselectable='true'
                                 aria-label='{$escaper->escapeHtmlAttr($lang['Controls'])}'></div>
                        </div>

                        <div class='sr-picker-pane sr-picker-pane--selected'>
                            <div class='sr-picker-pane-head'>
                                <span>{$escaper->escapeHtml($lang['Selected'])}</span>
                                <span class='sr-picker-pane-count' id='control-picker-selected-count'></span>
                            </div>
                            <div class='sr-picker-scroll sr-picker-selected' id='control-picker-selected'></div>
                        </div>
                    </div>

                    <div class='modal-footer sr-picker-foot'>
                        <span class='sr-picker-hint'>{$escaper->escapeHtml($lang['PickerKeyboardHint'])}</span>
                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                        <button type='button' class='btn btn-submit' id='control-picker-commit'>{$escaper->escapeHtml($lang['UseTheseControls'])}</button>
                    </div>
                </div>
            </div>
        </div>
    ";
}

/***************************************
 * FUNCTION: DISPLAY UPDATE TEST MODAL *
 ***************************************/
function display_update_test_modal($where = "define_tests") {
    global $lang, $escaper;

    // Approvers multiselect roster (Phase 3a): same approve_tests-permission
    // filter as the Add modal's copy (compliance/index.php) -- computed
    // independently here rather than threaded through as a parameter, since
    // this function is already called with no roster context and each modal
    // builder in this codebase assembles its own dropdown options.
    $approver_options = array_map(fn($u) => ['value' => $u['value'], 'name' => $u['name']], get_users_with_permission('approve_tests'));

    echo "
        <!-- Uses the design-system modal shell (.sr-modal, scss/modules/_sr-modal.scss;
             design-system.md §8) -- same four .sr-qcard sections as the Add modal
             (compliance/index.php), so creating and editing a test read the same. The
             schedule-* JS hooks live on the .sr-qfield wrappers and still take .d-none
             exactly as before. -->
        <div id='test--update' class='modal fade sr-modal' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog modal-dialog-scrollable modal-dialog-centered'>
                <div class='modal-content'>
                    <form class='' id='update-test-form' method='post' autocomplete='off'>
                        <input type='hidden' name='test_id' value=''>
                        <input type='hidden' name='update_test' value='true'>
                        <input type='hidden' name='where' value='{$escaper->escapeHtml($where)}'>
                        <div class='modal-header'>
                            <span class='sr-modal-icon'><i class='fa fa-pen-to-square' aria-hidden='true'></i></span>
                            <h4 class='modal-title'>{$escaper->escapeHtml($lang['TestEditHeader'])}</h4>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body'>
                            <section class='sr-qcard'>
                                <div class='sr-qcard-head'>
                                    <span class='sr-qcard-icon'><i class='fa fa-id-card' aria-hidden='true'></i></span>
                                    <h3>{$escaper->escapeHtml($lang['Identity'])}</h3>
                                    <span class='sr-qcard-sub'>{$escaper->escapeHtml($lang['IdentitySectionHint'])}</span>
                                </div>
                                <div class='sr-qcard-body'>
                            <div class='sr-qgrid'>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['TestName'])}<span class='required'>*</span></label>
                                    <input type='text' name='name' required value='' class='form-control' maxlength='1000' title='{$escaper->escapeHtml($lang['TestName'])}'>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['Tester'])}<span class='required'>*</span></label>
    ";
                                    create_dropdown("enabled_users", NULL, "tester", false, false, false, "required title='{$escaper->escapeHtml($lang['Tester'])}'");
    echo "
                                </div>
                                <div class='sr-qfield sr-qfield--full'>
                                    <label class='sr-qlabel' for='edit_test_control'>{$escaper->escapeHtml($lang['ControlName'])}<span class='required'>*</span></label>
    ";
                                    // Net-new (Phase 4a, common tests): populated client-side
                                    // (compliance-define-tests.js) from the same full control
                                    // roster as the Add modal's #add_test_control
                                    // (compliance/index.php) -- a test maps to N controls, so
                                    // this is a required bootstrap-multiselect. Named
                                    // 'controls[]' directly (no '_add' suffix, unlike the Add
                                    // modal's copy) since this modal only renders one copy of
                                    // it -- same reasoning as the plain 'approvers' name below.
                                    // Rendered empty (no create_multiple_dropdown() call) since,
                                    // unlike approvers, the control roster isn't a
                                    // server-side-resolved option list threaded into this
                                    // function -- it's fetched client-side instead. Deliberately
                                    // NOT given the 'multiselect' class create_multiple_dropdown()
                                    // bakes into approvers/team/additional_stakeholders below --
                                    // that class is a blanket selector some callers (e.g.
                                    // resetForm(), js/simplerisk/common.js) use to
                                    // .multiselect('refresh') every match in a form, which would
                                    // force bootstrap-multiselect's default-options lazy-init
                                    // fallback on this select if it fired before the roster
                                    // loaded. openTestForEdit() (compliance.js) owns this select's
                                    // init/select lifecycle instead (see its own comments).
                                    //
                                    // No `required` either, for the same reason as the Add modal's
                                    // copy (compliance/index.php): the browser can't report a
                                    // violation on a control the picker hides, so it aborted the
                                    // submit before any handler ran and said nothing. The submit
                                    // handler (compliance.js) carries the rule and the message.
    echo "
                                    <select multiple name='controls[]' id='edit_test_control' class='form-select sr-picker-value' title='{$escaper->escapeHtml($lang['ControlName'])}'>
                                    </select>
                                    <span class='sr-qhint'>{$escaper->escapeHtml($lang['CommonTestControlsHint'])}</span>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['AdditionalStakeholders'])}</label>
    ";
                                    create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders");
    echo "
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['Teams'])}</label>
    ";
                                    // BU-scoped when the Org Hierarchy Extra is on; see
                                    // get_test_modal_team_options() above.
                                    create_multiple_dropdown("team", NULL, NULL, get_test_modal_team_options());
    echo "
                                </div>
                            </div>
                                </div>
                            </section>

                            <section class='sr-qcard'>
                                <div class='sr-qcard-head'>
                                    <span class='sr-qcard-icon'><i class='fa fa-calendar-days' aria-hidden='true'></i></span>
                                    <h3>{$escaper->escapeHtml($lang['Schedule'])}</h3>
                                    <span class='sr-qcard-sub'>{$escaper->escapeHtml($lang['WhenTheAuditInitiates'])}</span>
                                </div>
                                <div class='sr-qcard-body'>
                            <div class='sr-qgrid'>
                                <div class='sr-qfield sr-qfield--full schedule-mode-row'>
                                    <label class='sr-qlabel'>{$escaper->escapeHtml($lang['Mode'])}</label>
                                    <div class='sr-seg schedule-mode-group' role='group' aria-label='{$escaper->escapeHtmlAttr($lang['Schedule'])}'>
                                        <input type='radio' class='btn-check' name='schedule_type' id='edit_schedule_type_manual' value='manual' autocomplete='off'>
                                        <label class='btn' for='edit_schedule_type_manual'>{$escaper->escapeHtml($lang['ScheduleManual'])}</label>

                                        <input type='radio' class='btn-check' name='schedule_type' id='edit_schedule_type_interval' value='interval' autocomplete='off'>
                                        <label class='btn' for='edit_schedule_type_interval'>{$escaper->escapeHtml($lang['ScheduleInterval'])}</label>

                                        <input type='radio' class='btn-check' name='schedule_type' id='edit_schedule_type_calendar' value='calendar' autocomplete='off'>
                                        <label class='btn' for='edit_schedule_type_calendar'>{$escaper->escapeHtml($lang['ScheduleCalendar'])}</label>
                                    </div>
                                </div>
                                <div class='sr-qfield schedule-field-interval'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['TestFrequency'])} <span class='sr-qlabel-note'>({$escaper->escapeHtml($lang['days'])})</span></label>
                                    <input type='number' min='0' max='2147483647' name='test_frequency' value='' class='form-control'>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['LastTestDate'])}</label>
                                    <input type='text' name='last_date' value='' class='form-control datepicker'>
                                    <span class='sr-qhint'>{$escaper->escapeHtml($lang['LastTestDateAnchorHint'])}</span>
                                </div>
                                <div class='sr-qfield schedule-field-offset'>
                                    <label class='sr-qlabel' for='edit_audit_initiation_offset'>{$escaper->escapeHtml($lang['AuditLeadInDays'])}</label>
                                    <input type='number' name='audit_initiation_offset' id='edit_audit_initiation_offset' class='form-control' title='{$escaper->escapeHtml($lang['AuditLeadInDays'])}' min='0'>
                                    <span class='sr-qhint'>{$escaper->escapeHtml($lang['AuditInitiationOffset_explanation'])}</span>
                                </div>
                                <div class='sr-qfield sr-qfield--full schedule-field-noncalendar'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['NextTestDate'])}</label>
                                    <input type='text' name='next_date' value='' class='form-control datepicker'>
                                    <!-- Say which way the dependency runs. With a frequency set
                                         this field is an OUTPUT (last date + frequency,
                                         resolve_interval_next_date(), includes/audit_schedule.php),
                                         so a date typed here is replaced on save; without one it
                                         is authoritative. Previously the field silently lost
                                         whatever you typed with no explanation. -->
                                    <span class='sr-qhint'>{$escaper->escapeHtml($lang['NextTestDateDerivedHint'])}</span>
                                </div>
                                <div class='sr-qfield schedule-field-calendar'>
                                    <label class='sr-qlabel' for='edit_cadence_preset'>{$escaper->escapeHtml($lang['Cadence'])}<span class='required'>*</span></label>
                                    <select id='edit_cadence_preset' class='form-select cadence-preset'>
                                        <option value='daily' data-unit='day' data-interval='1'>{$escaper->escapeHtml($lang['Daily'])}</option>
                                        <option value='weekly' data-unit='week' data-interval='1'>{$escaper->escapeHtml($lang['Weekly'])}</option>
                                        <option value='biweekly' data-unit='week' data-interval='2'>{$escaper->escapeHtml($lang['CadenceBiweekly'])}</option>
                                        <option value='monthly' data-unit='month' data-interval='1' selected>{$escaper->escapeHtml($lang['Monthly'])}</option>
                                        <option value='quarterly' data-unit='month' data-interval='3'>{$escaper->escapeHtml($lang['Quarterly'])}</option>
                                        <option value='semiannually' data-unit='month' data-interval='6'>{$escaper->escapeHtml($lang['CadenceSemiAnnually'])}</option>
                                        <option value='annually' data-unit='year' data-interval='1'>{$escaper->escapeHtml($lang['Annually'])}</option>
                                        <option value='custom'>{$escaper->escapeHtml($lang['Custom'])}</option>
                                    </select>
                                    <input type='hidden' name='cadence_unit' class='cadence-unit-value' value='month'>
                                    <input type='hidden' name='cadence_interval' class='cadence-interval-value' value='1'>
                                </div>
                                <div class='sr-qfield schedule-field-calendar'>
                                    <label class='sr-qlabel' for='edit_cadence_anchor_date'>{$escaper->escapeHtml($lang['AnchorDate'])}<span class='required'>*</span></label>
                                    <input type='text' name='cadence_anchor_date' id='edit_cadence_anchor_date' class='form-control datepicker' title='{$escaper->escapeHtml($lang['AnchorDate'])}'>
                                </div>
                                <div class='sr-qfield schedule-field-calendar schedule-cadence-custom d-none'>
                                    <label class='sr-qlabel' for='edit_cadence_custom_interval'>{$escaper->escapeHtml($lang['Cadence'])} ({$escaper->escapeHtml($lang['Custom'])})<span class='required'>*</span></label>
                                    <input type='number' min='1' class='form-control cadence-custom-interval' id='edit_cadence_custom_interval' value='1'>
                                </div>
                                <div class='sr-qfield schedule-field-calendar schedule-cadence-custom d-none'>
                                    <label class='sr-qlabel' for='edit_cadence_custom_unit'>&nbsp;</label>
                                    <select id='edit_cadence_custom_unit' class='form-select cadence-custom-unit'>
                                        <option value='day'>{$escaper->escapeHtml($lang['Day'])}</option>
                                        <option value='week'>{$escaper->escapeHtml($lang['Week'])}</option>
                                        <option value='month' selected>{$escaper->escapeHtml($lang['Month'])}</option>
                                        <option value='year'>{$escaper->escapeHtml($lang['Year'])}</option>
                                    </select>
                                </div>
                                <div class='sr-qfield sr-qfield--full schedule-field-calendar'>
                                    <label class='sr-qlabel'>{$escaper->escapeHtml($lang['UpcomingOccurrences'])}</label>
                                    <div class='schedule-occurrences-list border rounded p-2' style='max-height:220px;overflow-y:auto;'></div>
                                    <div class='schedule-occurrences-empty text-muted small mt-1 d-none'>{$escaper->escapeHtml($lang['NoUpcomingOccurrences'])}</div>
                                    <div class='schedule-occurrences-error text-danger small mt-1 d-none'>{$escaper->escapeHtml($lang['FailedToLoadUpcomingOccurrences'])}</div>
                                </div>
                            </div>
                                </div>
                            </section>

                            <div class='d-none occurrence-row-template'>
                                <div class='occurrence-row d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom py-1'>
                                    <div class='occurrence-date-wrap'>
                                        <span class='occurrence-date fw-semibold'></span>
                                        <span class='badge bg-danger ms-1 occurrence-overdue d-none'>{$escaper->escapeHtml($lang['Overdue'])}</span>
                                    </div>
                                    <div class='occurrence-controls d-flex align-items-center gap-2'>
                                        <div class='form-check form-check-inline mb-0'>
                                            <input type='checkbox' class='form-check-input occurrence-skip'>
                                            <label class='form-check-label small'>{$escaper->escapeHtml($lang['SkipOccurrence'])}</label>
                                        </div>
                                        <input type='text' class='form-control form-control-sm occurrence-override datepicker' title='{$escaper->escapeHtmlAttr($lang['OverrideDate'])}' placeholder='{$escaper->escapeHtmlAttr($lang['OverrideDate'])}'>
                                    </div>
                                </div>
                            </div>
                            <input type='hidden' name='schedule_exceptions' class='schedule-exceptions-value' value=''>

                            <section class='sr-qcard'>
                                <div class='sr-qcard-head'>
                                    <span class='sr-qcard-icon'><i class='fa fa-list-check' aria-hidden='true'></i></span>
                                    <h3>{$escaper->escapeHtml($lang['ProcedureAndEvidence'])}</h3>
                                    <span class='sr-qcard-tag'>{$escaper->escapeHtml($lang['Optional'])}</span>
                                </div>
                                <div class='sr-qcard-body'>
                            <div class='sr-qgrid'>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for='edit_test_method'>{$escaper->escapeHtml($lang['TestMethod'])}</label>
                                    <select name='test_method' id='edit_test_method' class='form-select' title='{$escaper->escapeHtml($lang['TestMethod'])}'>
                                        <option value=''></option>
                                        <option value='inquiry'>{$escaper->escapeHtml($lang['TestMethodInquiry'])}</option>
                                        <option value='observation'>{$escaper->escapeHtml($lang['TestMethodObservation'])}</option>
                                        <option value='inspection'>{$escaper->escapeHtml($lang['TestMethodInspection'])}</option>
                                        <option value='reperformance'>{$escaper->escapeHtml($lang['TestMethodReperformance'])}</option>
                                    </select>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['Approvers'])}</label>
    ";
                                    // Rename 'approvers' here (unlike additional_stakeholders,
                                    // which uses the plain name in this modal and a
                                    // '_add'-suffixed one in the Add modal) since the API
                                    // already reads/writes the same 'approvers[]' field name
                                    // in both createTest and updateTest -- only the Add
                                    // modal's copy needs the id/name to diverge (see
                                    // compliance/index.php) to keep DOM ids unique for the
                                    // bootstrap-multiselect init.
                                    create_multiple_dropdown("enabled_users", NULL, "approvers", $approver_options);
    echo "
                                </div>
                                <!-- Standing rule, stated before the user can break it (amber
                                     advisory, not an error). The red #edit_sod_warning is the
                                     violation message and only appears once they do. -->
                                <div class='sr-qfield sr-qfield--full'>
                                    <div class='sr-qnote'>
                                        <i class='fa fa-triangle-exclamation sr-qnote-ico' aria-hidden='true'></i>
                                        <span>{$escaper->escapeHtml($lang['SeparationOfDutiesNote'])}</span>
                                    </div>
                                    <div class='text-danger small d-none sod-warning' id='edit_sod_warning'>{$escaper->escapeHtml($lang['TesterCannotBeApprover'])}</div>
                                </div>
                                <div class='sr-qfield sr-qfield--full'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['Objective'])}</label>
                                    <textarea name='objective' id='objective' class='form-control' rows='3' style='max-width:100%;height: auto;'></textarea>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['TestSteps'])}</label>
                                    <textarea name='test_steps' id='test_steps' class='form-control' rows='3' style='max-width:100%;height:auto;'></textarea>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['ExpectedResults'])}</label>
                                    <textarea name='expected_results' id='expected_results' class='form-control' rows='3' style='max-width:100%;height: auto;'></textarea>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['Sample'])}</label>
                                    <textarea name='sample' id='sample' class='form-control' rows='3' style='max-width:100%;height:auto;'></textarea>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['RequiredEvidence'])}</label>
                                    <textarea name='required_evidence' id='required_evidence' class='form-control' rows='3' style='max-width:100%;height:auto;'></textarea>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['ApproximateTime'])} <span class='sr-qlabel-note'>({$escaper->escapeHtml($lang['minutes'])})</span></label>
                                    <input type='number' min='0' max='2147483647' name='approximate_time' value='' class='form-control'>
                                </div>
                                <div class='sr-qfield'>
                                    <label class='sr-qlabel' for=''>{$escaper->escapeHtml($lang['Tags'])}</label>
                                    <select class='test_tags' readonly name='tags[]' multiple placeholder='{$escaper->escapeHtml($lang['AddOrSearchTags'])}'></select>
                                    <div class='tag-max-length-warning sr-qhint'>{$escaper->escapeHtml($lang['MaxTagLengthWarning'])}</div>
                                </div>
                            </div>
                                </div>
                            </section>
                        </div>
                        <div class='modal-footer'>
                            <!-- Editing a common test changes it for every control it's
                                 applied to; say so where the user commits, not after. -->
                            <span class='sr-modal-hint'>{$escaper->escapeHtml($lang['CommonTestEditScopeHint'])}</span>
                            <button type='button' class='btn btn-secondary' data-bs-dismiss='modal' aria-label='Close'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                            <button type='submit' id='update_test' class='btn btn-submit'>{$escaper->escapeHtml($lang['Update'])}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    ";
}

/*******************************************************
 * FUNCTION: GET TEST SCHEDULE EXCEPTIONS (engine map) *
 *******************************************************/
function get_test_schedule_exceptions($test_id) {
    $db = db_open();
    $stmt = $db->prepare("SELECT occurrence_date, override_date, skipped FROM framework_control_test_schedule_exceptions WHERE test_id = :id");
    $stmt->bindParam(":id", $test_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $out = [];
    foreach ($rows as $r) {
        $out[$r['occurrence_date']] = [
            'override_date' => $r['override_date'],
            'skipped' => (bool)$r['skipped'],
        ];
    }
    return $out;
}

/*******************************************************
 * FUNCTION: SAVE TEST SCHEDULE EXCEPTIONS (replace)   *
 *******************************************************/
function save_test_schedule_exceptions($test_id, array $exceptions) {
    $db = db_open();
    $del = $db->prepare("DELETE FROM framework_control_test_schedule_exceptions WHERE test_id = :id");
    $del->bindParam(":id", $test_id, PDO::PARAM_INT);
    $del->execute();

    $ins = $db->prepare("INSERT INTO framework_control_test_schedule_exceptions (test_id, occurrence_date, override_date, skipped) VALUES (:tid, :occ, :ovr, :skip)");
    foreach ($exceptions as $occ => $ex) {
        $ovr  = !empty($ex['override_date']) ? $ex['override_date'] : null;
        $skip = !empty($ex['skipped']) ? 1 : 0;
        $ins->bindParam(":tid", $test_id, PDO::PARAM_INT);
        $ins->bindValue(":occ", $occ, PDO::PARAM_STR);
        $ins->bindValue(":ovr", $ovr, $ovr === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $ins->bindValue(":skip", $skip, PDO::PARAM_INT);
        $ins->execute();
    }
    db_close($db);
}

/*******************************************************
 * FUNCTION: COMPUTE TEST NEXT DATE                    *
 * Calendar -> engine; interval AND manual -> both     *
 * advance by last_date + test_frequency (preserves    *
 * pre-cadence behavior for legacy/backfilled tests).  *
 *******************************************************/
function compute_test_next_date(array $test, $on_or_after) {
    $type = $test['schedule_type'] ?? 'manual';

    if ($type === 'calendar' && !empty($test['cadence_anchor_date']) && !empty($test['cadence_unit']) && !empty($test['cadence_interval'])) {
        $exceptions = array_key_exists('_exceptions', $test) && $test['_exceptions'] !== null
            ? $test['_exceptions']
            : (isset($test['id']) ? get_test_schedule_exceptions((int)$test['id']) : []);
        $next = audit_schedule_next_occurrence(
            $test['cadence_anchor_date'], $test['cadence_unit'], (int)$test['cadence_interval'], $on_or_after, $exceptions
        );
        return $next ?? ($test['next_date'] ?? date('Y-m-d'));
    }

    // interval AND manual both advance by test_frequency from last_date
    // (preserves pre-cadence behavior for legacy/backfilled tests).
    $base = (!empty($test['last_date']) && $test['last_date'] !== '0000-00-00') ? $test['last_date'] : date('Y-m-d');
    $calc = date('Y-m-d', strtotime($base) + ((int)$test['test_frequency']) * 24 * 60 * 60);
    return ($calc < date('Y-m-d')) ? date('Y-m-d') : $calc;
}

/*******************************************************************
 * FUNCTION: COMPUTE CALENDAR NEXT DATE                            *
 * Thin wrapper around compute_test_next_date() for the calendar   *
 * schedule type, shared by add_/update_framework_control_test().  *
 * $test_id is omitted (null) on create, since the row does not    *
 * exist yet and there are no persisted exceptions to fetch — the  *
 * caller's seed $schedule_exceptions array is used instead.       *
 *******************************************************************/
function compute_calendar_next_date($schedule_type, $cadence_unit, $cadence_interval, $cadence_anchor_date, $schedule_exceptions, $test_id = null) {
    $test = [
        'schedule_type' => $schedule_type,
        'cadence_unit' => $cadence_unit,
        'cadence_interval' => $cadence_interval,
        'cadence_anchor_date' => $cadence_anchor_date,
        '_exceptions' => $schedule_exceptions,
    ];
    if ($test_id !== null) {
        $test['id'] = $test_id;
    }
    return compute_test_next_date($test, date('Y-m-d'));
}

/*******************************************************************
 * FUNCTION: VALIDATE TEST SCHEDULE FIELDS                         *
 * Shared server-side validation for the Add/Edit Test modals'     *
 * schedule fields (schedule_type allow-list, calendar cadence     *
 * completeness, past-anchor-date rejection, audit lead-in offset  *
 * bounds). Both createTest() and updateTestResponse()             *
 * (includes/api.php) call this after resolving their own "raw     *
 * submitted" vs "effective" (submitted-or-persisted, update only) *
 * values -- see the call sites for why the two differ; this       *
 * function only validates the already-resolved values handed to   *
 * it. Returns the first validation failure's ready-to-display,    *
 * translated but *unescaped* error message, or null when every    *
 * field is valid. Unescaped (not $escaper->escapeHtml()'d, and     *
 * built with _lang_raw() rather than _lang()) so each call site    *
 * can keep applying its own existing single escape step            *
 * (createTest() escapes at the json_response() call;             *
 * updateTestResponse() escapes once via set_alert()/get_alert(true))*
 * without double-escaping -- see the HTML Encoding double-escaping *
 * rule in CLAUDE.md. Does not mutate/reformat anything, matching  *
 * parse_test_schedule_fields()'s (includes/audit_schedule.php)    *
 * parse-only contract -- it stays out of that file because it     *
 * depends on $lang/_lang(), which would break that file's "pure,  *
 * no DB/HTTP" module contract.                                    *
 *                                                                  *
 * $fields keys:                                                   *
 *   raw_schedule_type            string|null  Submitted schedule_type as-is,  *
 *                                 before any persisted-row fallback; null      *
 *                                 means omitted from the request.             *
 *   effective_schedule_type      string|null  schedule_type actually in       *
 *                                 effect for this save: the submitted value,  *
 *                                 or (update only) the persisted value when   *
 *                                 omitted.                                    *
 *   cadence_unit                 string|null  Effective cadence unit.         *
 *   cadence_interval              int|null    Effective cadence interval.     *
 *   cadence_anchor_date          string|null  Effective cadence anchor date,  *
 *                                 ISO (Y-m-d).                                *
 *   cadence_anchor_date_submitted bool        Whether the anchor date was     *
 *                                 present in *this* request (vs falling back  *
 *                                 to a persisted value) -- the past-anchor    *
 *                                 check only re-validates a freshly submitted *
 *                                 anchor. Always true on create.              *
 *   test_frequency                int         Submitted test_frequency.      *
 *   audit_initiation_offset_raw   string      Submitted audit_initiation_    *
 *                                 offset, trimmed ('' means omitted).         *
 *******************************************************************/
function validate_test_schedule_fields(array $fields): ?string {
    global $lang;

    $raw_schedule_type = $fields['raw_schedule_type'] ?? null;
    if ($raw_schedule_type !== null && !in_array($raw_schedule_type, ['manual', 'interval', 'calendar'], true)) {
        return $lang['InvalidScheduleType'];
    }

    $effective_schedule_type = $fields['effective_schedule_type'] ?? null;

    if ($effective_schedule_type === 'calendar') {
        $cadence_unit = $fields['cadence_unit'] ?? null;
        $cadence_interval = $fields['cadence_interval'] ?? null;
        $cadence_anchor_date = $fields['cadence_anchor_date'] ?? null;

        if (empty($cadence_unit) || !in_array($cadence_unit, ['day', 'week', 'month', 'year'], true)) {
            return _lang_raw('FieldRequired', array("field" => $lang['Cadence']));
        } elseif (empty($cadence_interval) || $cadence_interval < 1) {
            return _lang_raw('FieldRequired', array("field" => $lang['Cadence']));
        } elseif (empty($cadence_anchor_date)) {
            return _lang_raw('FieldRequired', array("field" => $lang['AnchorDate']));
        }

        // Only re-validate a freshly submitted anchor -- an omitted anchor that
        // fell back to an already-persisted value (update only) isn't re-checked.
        if (!empty($fields['cadence_anchor_date_submitted']) && $cadence_anchor_date < date('Y-m-d')) {
            return $lang['AnchorDateMustBeTodayOrLater'];
        }
    }

    $test_frequency = $fields['test_frequency'] ?? 0;
    $audit_initiation_offset_raw = $fields['audit_initiation_offset_raw'] ?? '';

    if ($effective_schedule_type !== 'manual' && $audit_initiation_offset_raw !== '') {
        if ((int)$audit_initiation_offset_raw < 0) {
            return $lang['AuditInitiationOffsetMustBeANonNegativeValue'];
        } elseif ($effective_schedule_type === 'interval' && $test_frequency > 0 && (int)$audit_initiation_offset_raw > $test_frequency) {
            return $lang['AuditInitiationOffsetMustBeLessThanOrEqualToTestFrequency'];
        }
    }

    return null;
}

/*******************************************************************
 * FUNCTION: IS VALID TEST METHOD                                   *
 *                                                                    *
 * Validates that $token is either unset ("not yet chosen" sentinel,  *
 * covering both null and "") or one of the fixed test-method values  *
 * exposed on the Phase 3a test-definition form. There is no lookup   *
 * table backing this list — it's a small, fixed enum — so the        *
 * allow-list is hardcoded here rather than driven off the DB.        *
 *******************************************************************/
function is_valid_test_method($token) {
    return $token === null || $token === '' || in_array($token, ['inquiry', 'observation', 'inspection', 'reperformance'], true);
}

/*******************************************************************
 * FUNCTION: TEST TESTER CONFLICTS WITH APPROVERS                    *
 *                                                                    *
 * Segregation-of-duties guard: true when $tester also appears in     *
 * the $approvers list, i.e. the same person would both perform and   *
 * approve the test. Pure comparison only — callers decide what to    *
 * do with a conflict (block, warn, etc).                             *
 *******************************************************************/
function test_tester_conflicts_with_approvers($tester, array $approvers) {
    return in_array((int)$tester, array_map('intval', $approvers), true);
}

/*******************************************************************
 * FUNCTION: TEST TESTER VALID                                       *
 *                                                                    *
 * A test's tester must be a real, enabled user.                      *
 *                                                                    *
 * The Add Test form marks Tester required and defaults it to the     *
 * current user, so the UI always submits one -- which is exactly why *
 * this exists: the form was the ONLY thing enforcing it. Posting to  *
 * the create endpoint without a tester stored 0, giving the test an  *
 * owner that cannot be notified, cannot be filtered on, and does not *
 * exist. Update already refused a falsy tester; create did not, so   *
 * the same field was required on one path and optional on the other. *
 *                                                                    *
 * Existence is checked, not just non-zero: id 99999 is as unusable   *
 * as id 0, and a client that can omit the field can equally send a   *
 * stale one.                                                         *
 *                                                                    *
 * `enabled` mirrors the roster the form offers (create_dropdown's    *
 * "enabled_users"), so the API accepts exactly who the UI offers.    *
 *******************************************************************/
function test_tester_valid($tester) {
    $tester = (int)$tester;

    if ($tester <= 0) {
        return false;
    }

    $db = db_open();
    $stmt = $db->prepare("SELECT COUNT(*) FROM `user` WHERE `value` = :tester AND `enabled` = 1;");
    $stmt->bindParam(":tester", $tester, PDO::PARAM_INT);
    $stmt->execute();
    $exists = (int)$stmt->fetchColumn() > 0;
    db_close($db);

    return $exists;
}

/*******************************************************************
 * FUNCTION: TEST CONTROLS VALID                                     *
 *                                                                    *
 * Phase 4a (common tests): a test must map to at least one control.  *
 * True when $controls sanitizes down to one or more positive integer *
 * control ids. Pure validator -- callers decide what to do with an   *
 * invalid (empty) set.                                               *
 *******************************************************************/
function test_controls_valid($controls) {
    return count(sanitize_int_array($controls)) >= 1;
}

/*******************************************************************
 * FUNCTION: APPROVERS ALL HOLD THE approve_tests RESPONSIBILITY   *
 * Server-side enforcement of the approver roster gate (the modal  *
 * picker is filtered to approve_tests holders, but that is a UI   *
 * control only). Returns true when EVERY submitted approver id    *
 * belongs to a user that currently holds approve_tests. An empty  *
 * approver list is trivially eligible. Callers should only apply  *
 * this to FRESHLY-submitted approver lists -- never to a          *
 * passthrough of already-persisted approvers, since an approver   *
 * whose role later lost the responsibility would otherwise block  *
 * an unrelated update.                                            *
 *******************************************************************/
function approvers_all_hold_approve_tests(array $approvers) {
    $approvers = array_filter(array_map('intval', $approvers));
    if (empty($approvers)) {
        return true;
    }
    $eligible = array_map(function ($u) { return (int)$u['value']; }, get_users_with_permission('approve_tests'));
    return empty(array_diff($approvers, $eligible));
}

/*******************************************************************
 * PHASE 3b: AUDIT APPROVAL STATE MACHINE                            *
 *                                                                    *
 * The functions below are the pure state-machine core for the       *
 * audit approval workflow. They are deliberately AUTHZ-FREE: no      *
 * permission, approver-membership, or not-the-tester check is done   *
 * here. The caller (the API handler) is responsible for verifying    *
 * the acting user is allowed to approve/reject before calling        *
 * approve_audit()/reject_audit(). This keeps the state logic pure    *
 * DB + business-rule code that's straightforward to unit test.       *
 *******************************************************************/

/*******************************************************************
 * FUNCTION: AUDIT REQUIRES APPROVAL                                  *
 * True when the audit's parent test has one or more rows in the     *
 * framework_control_test_approvers junction, i.e. the test was       *
 * configured (Phase 3a) to require sign-off before a close sticks.   *
 *******************************************************************/
function audit_requires_approval($audit_id) {
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM `framework_control_test_approvers` a
        INNER JOIN `framework_control_test_audits` audit ON audit.test_id = a.test_id
        WHERE audit.id = :audit_id;
    ");
    $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return $count > 0;
}

/*******************************************************************
 * FUNCTION: USER IS APPROVER OF AUDIT                                *
 * True when $user_id is one of the approvers assigned (Phase 3a) to  *
 * the test that the given audit was initiated from.                  *
 *******************************************************************/
function user_is_approver_of_audit($audit_id, $user_id) {
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM `framework_control_test_approvers` a
        INNER JOIN `framework_control_test_audits` audit ON audit.test_id = a.test_id
        WHERE audit.id = :audit_id AND a.user_id = :user_id;
    ");
    $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return $count > 0;
}

/*******************************************************************
 * FUNCTION: GET AUDIT APPROVAL STATE                                 *
 * Returns the stored `approval_state` ('none'|'pending'|'approved'|  *
 * 'rejected') for the given audit. Returns 'none' if the audit row   *
 * can't be found (defensive -- the column itself is NOT NULL DEFAULT *
 * 'none' so a real row should never return anything else null).      *
 *******************************************************************/
function get_audit_approval_state($audit_id) {
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT `approval_state` FROM `framework_control_test_audits` WHERE `id` = :audit_id;");
    $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->execute();
    $state = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return ($state === false || $state === null || $state === '') ? 'none' : (string)$state;
}

/*******************************************************************
 * FUNCTION: AUDIT IS AWAITING APPROVAL                               *
 * True when the audit is sitting in the closed status AND its        *
 * approval_state is still 'pending' -- i.e. the tester submitted a    *
 * close but a required approver hasn't signed off yet.                *
 *******************************************************************/
function audit_is_awaiting_approval($audit_id) {
    $closed_audit_status = (int)get_setting("closed_audit_status");

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM `framework_control_test_audits`
        WHERE `id` = :audit_id AND `approval_state` = 'pending' AND `status` = :closed_status;
    ");
    $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":closed_status", $closed_audit_status, PDO::PARAM_INT);
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return $count > 0;
}

/*******************************************************************
 * FUNCTION: APPROVE AUDIT                                            *
 * Moves a 'pending' audit to 'approved' and runs the advance-on-close *
 * side effect that save_test_result()'s closed-status branch used to  *
 * run unconditionally (see the `if($status == $closed_audit_status)`  *
 * block above, ~line 2593) -- update_last_and_next_auditdate() is     *
 * called here, and ONLY here, exactly once, now that the close is     *
 * actually approved. The date passed is the audit's own recorded      *
 * test/last date (the `test_date` column of the joined test result,   *
 * returned by get_framework_control_test_audit_by_id() as             *
 * `results.test_date`) -- the same value save_test_result() would     *
 * have passed as $test_date.                                          *
 *                                                                      *
 * No-ops (returns false) unless approval_state is currently 'pending' *
 * so a double-approve, or an approve issued after a reject already    *
 * landed, can't double-advance the dates or corrupt history.          *
 *******************************************************************/
function approve_audit($audit_id, $user_id) {
    // Gate on the AWAITING state (pending AND status=closed), not the bare 'pending'
    // enum: after a reopen an approval-required audit is (status=in-progress, pending),
    // and approving that would run the advance-on-close on a NON-closed audit (a
    // premature advance, then a second advance when the tester actually closes) --
    // breaking advance-exactly-once. Only a genuinely-awaiting audit (the tester
    // submitted a closing result that's held) may be approved.
    if (!audit_is_awaiting_approval($audit_id)) {
        return false;
    }

    $test_audit = get_framework_control_test_audit_by_id($audit_id);
    $closed_audit_status = (int)get_setting("closed_audit_status");

    // Open the database connection
    $db = db_open();

    // Atomic awaiting->approved flip: the `AND approval_state='pending' AND status=closed`
    // clause makes the transition conditional so two concurrent approver sessions (not
    // serialized by the same-session DB lock) can't both pass the read-guard above and
    // each run the advance. Only the caller whose UPDATE actually changed the row
    // (rowCount 1) proceeds to update_last_and_next_auditdate() + the history insert --
    // advance-exactly-once, and never on a non-closed (reopened) audit.
    $stmt = $db->prepare("UPDATE `framework_control_test_audits` SET `approval_state`='approved' WHERE `id`=:audit_id AND `approval_state`='pending' AND `status`=:closed_status;");
    $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":closed_status", $closed_audit_status, PDO::PARAM_INT);
    $stmt->execute();
    $won = $stmt->rowCount() === 1;

    // Close the database connection
    db_close($db);

    if (!$won) {
        return false; // lost the race -- another request already transitioned this audit
    }

    // The held advance-on-close side effect -- runs exactly once, here.
    update_last_and_next_auditdate($audit_id, $test_audit['test_date']);

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        INSERT INTO `framework_control_test_audit_approvals`(`audit_id`, `decision`, `user_id`, `comment`, `created_at`)
        VALUES(:audit_id, 'approved', :user_id, NULL, NOW());
    ");
    $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    $user_name = get_user_name($user_id);
    $message = _lang_raw('AuditLog_TestAuditApproved', ['test_audit_name' => $test_audit['name'], 'test_audit_id' => $audit_id, 'user_name' => $user_name]);
    write_log((int)$audit_id + 1000, $user_id, $message, "test_audit");

    return true;
}

/*******************************************************************
 * FUNCTION: REJECT AUDIT                                             *
 * Moves a 'pending' audit to 'rejected' and reopens it -- reuses      *
 * update_test_audit_status($audit_id, 0), the exact status-reset      *
 * mechanism reopen_test_audit() itself calls (compliance.php:3200),   *
 * so a rejected audit lands in the same in-progress state a reopened  *
 * one would. reopen_test_audit() itself is NOT called, because it     *
 * writes its own "AuditLog_TestAuditReopen" history entry -- wrong    *
 * wording for a rejection; this function writes its own 'rejected'    *
 * entry (with the comment) below instead.                             *
 *                                                                      *
 * Does NOT call update_last_and_next_auditdate() -- a reject means    *
 * the close didn't stick, so the test/next dates must not advance.    *
 *                                                                      *
 * No-ops (returns false) unless approval_state is currently 'pending' *
 * AND a non-empty $comment was supplied.                              *
 *******************************************************************/
function reject_audit($audit_id, $user_id, $comment) {
    // Gate on the AWAITING state (pending AND status=closed), not the bare enum --
    // same tightening as approve_audit (only a genuinely-awaiting audit can be
    // rejected; a reopened pending audit isn't awaiting a decision).
    if (!audit_is_awaiting_approval($audit_id) || trim((string)$comment) === '') {
        return false;
    }

    $test_audit = get_framework_control_test_audit_by_id($audit_id);
    $closed_audit_status = (int)get_setting("closed_audit_status");

    // Open the database connection
    $db = db_open();

    // Atomic awaiting->rejected flip (same conditional-UPDATE guard as approve_audit):
    // only the caller whose UPDATE changed the row proceeds to the status reopen +
    // history insert, so a concurrent double-reject can't write two history rows.
    $stmt = $db->prepare("UPDATE `framework_control_test_audits` SET `approval_state`='rejected' WHERE `id`=:audit_id AND `approval_state`='pending' AND `status`=:closed_status;");
    $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":closed_status", $closed_audit_status, PDO::PARAM_INT);
    $stmt->execute();
    $won = $stmt->rowCount() === 1;

    // Close the database connection
    db_close($db);

    if (!$won) {
        return false; // lost the race -- another request already transitioned this audit
    }

    // Mirror reopen_test_audit()'s exact status-reset mechanism/value.
    update_test_audit_status($audit_id, 0);

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        INSERT INTO `framework_control_test_audit_approvals`(`audit_id`, `decision`, `user_id`, `comment`, `created_at`)
        VALUES(:audit_id, 'rejected', :user_id, :comment, NOW());
    ");
    $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":comment", $comment, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Best-effort mirror to the audit comment thread. save_test_comment() gates on
    // $_SESSION['comment_compliance'] (a UI/session-bound permission check) and
    // attributes the comment to $_SESSION['uid'] -- both may be absent or differ
    // from $user_id when this is called outside a full web session (e.g. from a
    // test, or a future non-interactive caller). If the gate no-ops, that's fine:
    // the framework_control_test_audit_approvals history row above is the
    // authoritative record of the rejection and its comment either way.
    save_test_comment($audit_id, $comment);

    $user_name = get_user_name($user_id);
    $message = _lang_raw('AuditLog_TestAuditRejected', ['test_audit_name' => $test_audit['name'], 'test_audit_id' => $audit_id, 'user_name' => $user_name, 'comment' => $comment]);
    write_log((int)$audit_id + 1000, $user_id, $message, "test_audit");

    return true;
}

/*******************************************************************
 * FUNCTION: GET AUDITS AWAITING APPROVAL                             *
 * The approver's pending-approval queue (Task 4): audits that are    *
 * status=closed_audit_status AND approval_state='pending' -- i.e.    *
 * the tester submitted a closing result but a required approver      *
 * hasn't signed off yet (save_test_result()'s hold branch, ~2875,    *
 * and audit_is_awaiting_approval() key off the same combination).    *
 * These are the "truly closed" predicate's carve-out: Task 4 keeps   *
 * them out of Past and in Active, and this helper is the queue an    *
 * approver-facing UI/API (Task 5/6, not built here) would list.      *
 *                                                                     *
 * When $approver_user_id is given, the result is scoped to audits    *
 * whose parent test lists that user in the                           *
 * framework_control_test_approvers junction (Phase 3a) -- "my        *
 * pending approvals" rather than every organization's queue. Pass    *
 * null (default) for the unscoped, all-approvers queue.              *
 *                                                                     *
 * Deliberately AUTHZ-FREE, same as approve_audit()/reject_audit()    *
 * above -- the caller is responsible for verifying the acting user   *
 * is allowed to see this list before calling it.                    *
 *******************************************************************/
function get_audits_awaiting_approval($approver_user_id = null) {
    $closed_audit_status = (int)get_setting("closed_audit_status");

    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT DISTINCT a.*
        FROM `framework_control_test_audits` a
    ";

    if ($approver_user_id !== null) {
        $sql .= " INNER JOIN `framework_control_test_approvers` fcta ON fcta.test_id = a.test_id AND fcta.user_id = :approver_user_id ";
    }

    $sql .= " WHERE a.status = :closed_status AND a.approval_state = 'pending' ORDER BY a.id;";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":closed_status", $closed_audit_status, PDO::PARAM_INT);
    if ($approver_user_id !== null) {
        $approver_user_id = (int)$approver_user_id;
        $stmt->bindParam(":approver_user_id", $approver_user_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $audits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $audits;
}

/*******************************************************************
 * FUNCTION: GET LATEST AUDIT REJECTION COMMENT (Phase 3b Task 6)     *
 * Read-only helper for the "Returned for rework" banner on the       *
 * audit detail page (display_testing()): returns the `comment` from  *
 * the most recent 'rejected' row in                                  *
 * `framework_control_test_audit_approvals` for this audit, or ''     *
 * if the audit has never been rejected (or the row's comment was     *
 * somehow empty). reject_audit() (above) is the sole writer of that   *
 * table's 'rejected' rows and enforces a non-empty comment, so in     *
 * practice this only returns '' for an audit with no rejection        *
 * history. The caller is responsible for HTML-escaping the returned   *
 * value exactly once before rendering it -- this is free-text user    *
 * input, not pre-escaped here.                                        *
 *******************************************************************/
function get_latest_audit_rejection_comment($audit_id) {
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT `comment`
        FROM `framework_control_test_audit_approvals`
        WHERE `audit_id` = :audit_id AND `decision` = 'rejected'
        ORDER BY `created_at` DESC, `id` DESC
        LIMIT 1;
    ");
    $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->execute();
    $comment = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return ($comment === false || $comment === null) ? '' : (string)$comment;
}

/*******************************************************************
 * FUNCTION: NOTIFY AUDIT AWAITING APPROVAL (Phase 3b Task 5)         *
 * The in-app-notification counterpart to save_test_result()'s        *
 * pending branch (~line 2947 as of this writing): when a tester's     *
 * closing submission lands an audit in approval_state='pending',      *
 * this notifies every approver configured on the audit's parent test  *
 * that a decision is waiting on them.                                 *
 *                                                                      *
 * source='workflow' -- the only NOTIFICATION_SOURCES value available  *
 * to Core runtime code (see includes/notifications.php). The link     *
 * points at the audit's testing page via build_url(), the same        *
 * base-URL-safe idiom used throughout compliance.php/api.php.         *
 *                                                                      *
 * Best-effort / silently no-ops when the audit can't be found or its   *
 * test has no configured approvers -- there is nothing actionable for *
 * the caller (save_test_result()) to do with a failure here, and a    *
 * test with zero approvers should never have reached the 'pending'    *
 * branch in the first place (audit_requires_approval() gates that).   *
 *******************************************************************/
function notify_audit_awaiting_approval($audit_id) {
    $audit = get_framework_control_test_audit_by_id($audit_id);
    if (empty($audit['id']) || empty($audit['test_id'])) {
        return;
    }

    $test = get_framework_control_test_by_id($audit['test_id']);
    $approver_ids = array_map('intval', $test['approvers'] ?? []);
    if (empty($approver_ids)) {
        return;
    }

    create_notification_for_user_ids(
        source:     'workflow',
        title:      _lang_raw('NotificationAuditAwaitingApprovalTitle', ['test_audit_name' => $audit['name']]),
        body:       _lang_raw('NotificationAuditAwaitingApprovalBody', ['test_audit_name' => $audit['name']]),
        link:       build_url("compliance/testing.php?id=" . $audit_id),
        user_ids:   $approver_ids,
        created_by: $_SESSION['uid'] ?? null,
        expires_at: null
    );
}

/*****************************************
 * FUNCTION: ADD FRAMEWORK CONTROLS TEST *
 *****************************************/
function add_framework_control_test($tester, $test_frequency, $name, $objective, $test_steps, $approximate_time, $expected_results, $framework_control_id, $additional_stakeholders = "", $last_date="0000-00-00", $next_date=false, $teams=[], $tags=[], $audit_initiation_offset = null, $schedule_type = null, $cadence_unit = null, $cadence_interval = null, $cadence_anchor_date = null, $schedule_exceptions = [], $test_method = null, $sample = null, $required_evidence = null, $approvers = [], $controls = []){

    // Sanitizing input that comes from the WYSIWYG editor or outside sources
    $objective = purify_html($objective);
    $test_steps = purify_html($test_steps);
    $expected_results = purify_html($expected_results);
    $sample = purify_html($sample);
    $required_evidence = purify_html($required_evidence);

    // Phase 4a (common tests) back-compat: non-API callers (extras, and any other
    // scalar-only caller) pass a single $framework_control_id and no $controls at
    // all. Treat an empty $controls plus a valid non-zero scalar as "one control"
    // so every create still writes exactly one test_control_map row, matching
    // today's single-control behavior for those callers without changing them.
    if (empty($controls) && !empty($framework_control_id) && ctype_digit((string)$framework_control_id)) {
        $controls = [$framework_control_id];
    }
    $controls = sanitize_int_array($controls);
    if (!empty($controls)) {
        // The junction is the source of truth once populated; keep the scalar
        // framework_control_id column synced to min(controls) as a dead-but-valid
        // safety net for any code that still reads it directly.
        $framework_control_id = min($controls);
    }

    if($next_date === false) {
        if ($schedule_type === 'calendar') {
            $next_date = compute_calendar_next_date($schedule_type, $cadence_unit, $cadence_interval, $cadence_anchor_date, $schedule_exceptions);
        } elseif (!$last_date || $last_date === "0000-00-00") {
            $next_date = date("Y-m-d");
        } else {
            $calc_next_date = date("Y-m-d", strtotime($last_date) + $test_frequency*24*60*60);
            if($calc_next_date < date("Y-m-d")){
                $next_date = date("Y-m-d");
            } else {
                $next_date = $calc_next_date;
            }
        }
    }

    // Open the database connection
    $db = db_open();

    $created_at = date("Y-m-d");

    // Create test
    $stmt = $db->prepare("INSERT INTO `framework_control_tests` (`tester`, `test_frequency`, `last_date`, `next_date`, `name`, `objective`, `test_steps`, `approximate_time`, `expected_results`, `framework_control_id`, `created_at`, `additional_stakeholders`, `audit_initiation_offset`, `schedule_type`, `cadence_unit`, `cadence_interval`, `cadence_anchor_date`, `test_method`, `sample`, `required_evidence`) VALUES (:tester, :test_frequency, :last_date, :next_date, :name, :objective, :test_steps, :approximate_time, :expected_results, :framework_control_id, :created_at, :additional_stakeholders, :audit_initiation_offset, :schedule_type, :cadence_unit, :cadence_interval, :cadence_anchor_date, :test_method, :sample, :required_evidence)");

    $stmt->bindParam(":tester", $tester, PDO::PARAM_INT);
    $stmt->bindParam(":test_frequency", $test_frequency, PDO::PARAM_INT);
    $stmt->bindParam(":last_date", $last_date, PDO::PARAM_STR);
    $stmt->bindParam(":next_date", $next_date, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 100);
    $stmt->bindParam(":objective", $objective, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":test_steps", $test_steps, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":approximate_time", $approximate_time, PDO::PARAM_INT);
    $stmt->bindParam(":expected_results", $expected_results, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":framework_control_id", $framework_control_id, PDO::PARAM_INT);
    $stmt->bindParam(":created_at", $created_at);
    $stmt->bindParam(":additional_stakeholders", $additional_stakeholders, PDO::PARAM_STR, 500);
    $stmt->bindValue(":audit_initiation_offset", $audit_initiation_offset, $audit_initiation_offset === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(":schedule_type", $schedule_type, $schedule_type === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":cadence_unit", $cadence_unit, $cadence_unit === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":cadence_interval", $cadence_interval, $cadence_interval === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(":cadence_anchor_date", $cadence_anchor_date, $cadence_anchor_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":test_method", $test_method, ($test_method === null || $test_method === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":sample", $sample, $sample === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":required_evidence", $required_evidence, $required_evidence === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $stmt->execute();

    $test_id = $db->lastInsertId();

    if ($test_id != 0) {
        updateTeamsOfItem($test_id, 'test', $teams);
        updateTagsOfType($test_id, 'test', $tags);
        save_test_schedule_exceptions($test_id, $schedule_exceptions);
        save_junction_values('framework_control_test_approvers', 'test_id', $test_id, 'user_id', sanitize_int_array($approvers));
        save_junction_values('test_control_map', 'test_id', $test_id, 'framework_control_id', $controls);
    }

    $message = _lang_raw('TestCreatedAuditLogMessage', array('test_name' => $name, 'test_id' => $test_id, 'user' => $_SESSION['user']));
    write_log((int)$test_id + 1000, $_SESSION['uid'] ?? 0, $message, "test");

    // Close the database connection
    db_close($db);

    trigger_workflow_event('test.created', [
        'test_id'    => $test_id,
        'control_id' => $framework_control_id,
        'tester'     => $tester,
    ]);

    return $test_id;
}

/********************************************
 * FUNCTION: UPDATE FRAMEWORK CONTROLS TEST *
 ********************************************/
function update_framework_control_test($test_id, $tester=false, $test_frequency=false, $name=false, $objective=false, $test_steps=false, $approximate_time=false, $expected_results=false, $last_date=false, $next_date=false, $framework_control_id=false, $additional_stakeholders=false, $teams=false, $tags=[], $audit_initiation_offset = false, $schedule_type = null, $cadence_unit = null, $cadence_interval = null, $cadence_anchor_date = null, $schedule_exceptions = null, $test_method = false, $sample = false, $required_evidence = false, $approvers = [], $controls = []){

    // Get test by test ID
    $test = get_framework_control_test_by_id($test_id);

    if($tester === false) $tester = $test['tester'];
    if($test_frequency === false) $test_frequency = $test['test_frequency'];
    if($name === false) $name = $test['name'];
    if($objective === false) $objective = $test['objective'];
    if($test_steps === false) $test_steps = $test['test_steps'];
    if($approximate_time === false) $approximate_time = $test['approximate_time'];
    if($expected_results === false) $expected_results = $test['expected_results'];
    if($last_date === false) $last_date = $test['last_date'];
    if($framework_control_id === false) $framework_control_id = $test['framework_control_id'];
    // `additional_stakeholders` is stored as a comma-joined string, but the
    // getter above (get_framework_control_test_by_id()) EXPLODES it into an
    // array — so the keep-existing fallback hands an array to a
    // PDO::PARAM_STR bind further down, which raises "Array to string
    // conversion" and answers 500.
    //
    // That made every PARTIAL update of a test that has any stakeholder fail:
    // PATCH /api/v2/compliance/tests/{id} documents "omitted field = keep
    // existing", and omitting this one was the single case where keeping the
    // existing value could not survive being written back. Re-joining restores
    // the round trip the column's own storage format implies. Applied to the
    // parameter rather than only to the fallback so a CALLER that passes an
    // array is handled the same way, which is the shape the getter would have
    // handed it in the first place.
    if($additional_stakeholders === false) $additional_stakeholders = $test['additional_stakeholders'];
    if(is_array($additional_stakeholders)) $additional_stakeholders = implode(",", $additional_stakeholders);
    if($teams === false) $teams = $test['teams'];
    if($audit_initiation_offset === false) $audit_initiation_offset = $test['audit_initiation_offset'];
    if($schedule_type === null) $schedule_type = $test['schedule_type'] ?? null;
    if($cadence_unit === null) $cadence_unit = $test['cadence_unit'] ?? null;
    if($cadence_interval === null) $cadence_interval = $test['cadence_interval'] ?? null;
    if($cadence_anchor_date === null) $cadence_anchor_date = $test['cadence_anchor_date'] ?? null;
    if($test_method === false) $test_method = $test['test_method'] ?? null;
    if($sample === false) $sample = $test['sample'] ?? null;
    if($required_evidence === false) $required_evidence = $test['required_evidence'] ?? null;

    // Phase 4a (common tests): $controls default ([]) means "keep existing" -- a
    // partial update must not wipe the test_control_map junction. Only a freshly
    // submitted, non-empty control set replaces the map and re-syncs the scalar
    // framework_control_id column to min(controls).
    $controls = sanitize_int_array($controls);
    if (!empty($controls)) {
        $framework_control_id = min($controls);
    }

    if($next_date === false) {
        if ($schedule_type === 'calendar') {
            $next_date = compute_calendar_next_date($schedule_type, $cadence_unit, $cadence_interval, $cadence_anchor_date, $schedule_exceptions, $test_id);
        } else {
            $next_date = $test['next_date'];
        }
    }

    // Sanitizing input that comes from the WYSIWYG editor or outside sources
    $objective = purify_html($objective);
    $test_steps = purify_html($test_steps);
    $expected_results = purify_html($expected_results);
    $sample = purify_html($sample);
    $required_evidence = purify_html($required_evidence);

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("UPDATE `framework_control_tests` SET `tester`=:tester, `test_frequency`=:test_frequency, `last_date`=:last_date, `next_date`=:next_date, `name`=:name, `objective`=:objective, `test_steps`=:test_steps, `approximate_time`=:approximate_time, `expected_results`=:expected_results, `framework_control_id`=:framework_control_id, `additional_stakeholders`=:additional_stakeholders, `audit_initiation_offset` = :audit_initiation_offset, `schedule_type`=:schedule_type, `cadence_unit`=:cadence_unit, `cadence_interval`=:cadence_interval, `cadence_anchor_date`=:cadence_anchor_date, `test_method`=:test_method, `sample`=:sample, `required_evidence`=:required_evidence WHERE id=:test_id; ");

    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->bindParam(":tester", $tester, PDO::PARAM_INT);
    $stmt->bindParam(":test_frequency", $test_frequency, PDO::PARAM_INT);
    $stmt->bindParam(":last_date", $last_date, PDO::PARAM_STR);
    $stmt->bindParam(":next_date", $next_date, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":objective", $objective, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":test_steps", $test_steps, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":approximate_time", $approximate_time, PDO::PARAM_INT);
    $stmt->bindParam(":expected_results", $expected_results, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":framework_control_id", $framework_control_id, PDO::PARAM_INT);
    $stmt->bindParam(":additional_stakeholders", $additional_stakeholders, PDO::PARAM_STR, 500);
    $stmt->bindValue(":audit_initiation_offset", $audit_initiation_offset, $audit_initiation_offset === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(":schedule_type", $schedule_type, $schedule_type === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":cadence_unit", $cadence_unit, $cadence_unit === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":cadence_interval", $cadence_interval, $cadence_interval === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(":cadence_anchor_date", $cadence_anchor_date, $cadence_anchor_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":test_method", $test_method, ($test_method === null || $test_method === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":sample", $sample, $sample === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":required_evidence", $required_evidence, $required_evidence === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    updateTeamsOfItem($test_id, 'test', $teams, false);
    updateTagsOfType($test_id, 'test', $tags);
    save_junction_values('framework_control_test_approvers', 'test_id', $test_id, 'user_id', sanitize_int_array($approvers));
    if (!empty($controls)) {
        // Non-empty $controls replaces the map. An empty/omitted $controls means
        // "keep existing" -- must NOT call save_junction_values() here, since an
        // empty $second_ids array there means "delete and don't reinsert".
        save_junction_values('test_control_map', 'test_id', $test_id, 'framework_control_id', $controls);
    }
    // null (default) = leave existing exceptions untouched; an explicit array
    // (including []) = replace with this set (empty clears them).
    if ($schedule_exceptions !== null) {
        save_test_schedule_exceptions($test_id, $schedule_exceptions);
    }

    $test_after = get_framework_control_test_by_id($test_id);
    
    $changes = get_changes('test', $test, $test_after);

    $message = _lang_raw('AuditLog_TestUpdated', array('test_name' => $name, 'test_id' => $test_id, 'user_name' => $_SESSION['user'], 'changes' => $changes));
    write_log((int)$test_id + 1000, $_SESSION['uid'] ?? 0, $message, "test");

    trigger_workflow_event('test.updated', [
        'test_id'    => $test_id,
        'control_id' => $framework_control_id,
        'tester'     => $tester,
    ]);

    return $test_id;
}

/******************************************************
 * FUNCTION: DELETE FRAMEWORK CONTROL TEST BY TEST ID *
 ******************************************************/
function delete_framework_control_test($test_id){

    $test = get_framework_control_test_by_id($test_id);

    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("DELETE FROM `framework_control_tests` WHERE id=:id;");
    $stmt->bindParam(":id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Remove teams of test
    updateTeamsOfItem($test_id, 'test', []);
    // Remove tags of test
    updateTagsOfType($test_id, 'test', []);
    // Remove approvers of test (junction cleanup, symmetric with teams/tags)
    save_junction_values('framework_control_test_approvers', 'test_id', $test_id, 'user_id', []);
    // Remove control map rows of test (junction cleanup, symmetric with approvers/teams/tags)
    save_junction_values('test_control_map', 'test_id', $test_id, 'framework_control_id', []);

    // Close the database connection
    db_close($db);

    $message = _lang('TestDeletedAuditLogMessage', array('test_name' => $test['name'], 'test_id' => $test_id, 'user' => $_SESSION['user']));
    write_log((int)$test_id + 1000, $_SESSION['uid'] ?? 0, $message, "test");

    return true;
}

/******************************************************
 * FUNCTION: RETIRE FRAMEWORK CONTROL TEST             *
 * Marks a test retired (soft-hidden) without deleting  *
 * it or its audit history. No-op if already retired.   *
 ******************************************************/
function retire_framework_control_test($test_id) {

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("UPDATE framework_control_tests SET retired_at = NOW() WHERE id = :id AND retired_at IS NULL");
    $stmt->bindParam(":id", $test_id, PDO::PARAM_INT);
    $stmt->execute();
    $newly_retired = ($stmt->rowCount() > 0);

    // Close the database connection
    db_close($db);

    // A retired test must stop driving audit work: close any audits still open
    // for it. This is what actually stops the Email Notification Extra's
    // reminders -- auto_run_audit_notification() selects on
    // `status <> closed_audit_status`, so closing the audit removes it from
    // that query at the source (no Extra-side change needed). Only runs on a
    // real state change so re-retiring an already-retired test is a no-op.
    if ($newly_retired) {
        close_open_audits_for_retired_test($test_id);
    }

    write_debug_log("Retired framework_control_test {$test_id}", "info");
}

/**********************************************************************
 * FUNCTION: CLOSE OPEN AUDITS FOR RETIRED TEST                        *
 * Closes every audit still open for a just-retired test, recording it *
 * as Inconclusive and appending an audit-trail note explaining that   *
 * the closure was automatic. Existing summary text is preserved (the  *
 * note is appended, never overwritten) so an auditor's in-progress    *
 * write-up survives. Returns the number of audits closed.             *
 **********************************************************************/
function close_open_audits_for_retired_test($test_id) {

    $closed_audit_status = get_setting("closed_audit_status");

    // Without a configured closed status there is nothing meaningful to set;
    // bail rather than guess a status value.
    if ($closed_audit_status === false || $closed_audit_status === "") {
        write_debug_log("close_open_audits_for_retired_test: no closed_audit_status configured; skipping test {$test_id}", "warning");
        return 0;
    }

    $db = db_open();

    // Every audit for this test that isn't already in the closed status.
    $stmt = $db->prepare("SELECT `id` FROM `framework_control_test_audits` WHERE `test_id` = :test_id AND `status` <> :closed_status");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->bindParam(":closed_status", $closed_audit_status, PDO::PARAM_INT);
    $stmt->execute();
    $audit_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!$audit_ids) {
        db_close($db);
        return 0;
    }

    // 'Inconclusive' is stored by NAME, not by id -- framework_control_test_
    // results.test_result is a varchar validated via
    // is_valid_test_result_name()/get_value_by_name('test_results', ...).
    $inconclusive = "Inconclusive";
    $note         = $GLOBALS['lang']['AuditAutoClosedTestRetired'];
    $today        = date("Y-m-d");
    $now          = date("Y-m-d H:i:s");
    // Retire is always user-initiated; fall back to 0 for a CLI/job context.
    $submitted_by = !empty($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;

    foreach ($audit_ids as $audit_id) {

        // initiate_test_audit() creates the results row up front, so this is
        // normally an UPDATE; the INSERT covers an audit whose row is missing.
        $has_row = $db->prepare("SELECT `id` FROM `framework_control_test_results` WHERE `test_audit_id` = :audit_id LIMIT 1");
        $has_row->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
        $has_row->execute();

        if ($has_row->fetchColumn()) {
            // Append the note to any existing summary rather than replacing it.
            $sql = "UPDATE `framework_control_test_results`
                    SET `test_result` = :test_result,
                        `summary` = TRIM(CONCAT(COALESCE(`summary`, ''), IF(COALESCE(`summary`, '') = '', '', '\n\n'), :note)),
                        `test_date` = :test_date,
                        `submitted_by` = :submitted_by,
                        `submission_date` = :submission_date
                    WHERE `test_audit_id` = :audit_id";
        } else {
            $sql = "INSERT INTO `framework_control_test_results`
                        (`test_audit_id`, `test_result`, `summary`, `test_date`, `submitted_by`, `submission_date`)
                    VALUES (:audit_id, :test_result, :note, :test_date, :submitted_by, :submission_date)";
        }

        $write = $db->prepare($sql);
        $write->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
        $write->bindParam(":test_result", $inconclusive, PDO::PARAM_STR);
        $write->bindParam(":note", $note, PDO::PARAM_STR);
        $write->bindParam(":test_date", $today, PDO::PARAM_STR);
        $write->bindParam(":submitted_by", $submitted_by, PDO::PARAM_INT);
        $write->bindParam(":submission_date", $now, PDO::PARAM_STR);
        $write->execute();

        // Flip the audit itself into the closed status.
        $close = $db->prepare("UPDATE `framework_control_test_audits` SET `status` = :closed_status WHERE `id` = :audit_id");
        $close->bindParam(":closed_status", $closed_audit_status, PDO::PARAM_INT);
        $close->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
        $close->execute();
    }

    db_close($db);

    $count = count($audit_ids);
    write_debug_log("Auto-closed {$count} open audit(s) as Inconclusive for retired framework_control_test {$test_id}", "info");

    return $count;
}

/******************************************************
 * FUNCTION: RESTORE FRAMEWORK CONTROL TEST            *
 * Clears the retired state, returning the test to the  *
 * active list.                                          *
 ******************************************************/
function restore_framework_control_test($test_id) {

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("UPDATE framework_control_tests SET retired_at = NULL WHERE id = :id");
    $stmt->bindParam(":id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    write_debug_log("Restored framework_control_test {$test_id}", "info");
}

/******************************************************
 * FUNCTION: CAN RETIRE TESTS                          *
 * Permission gate for the retire/restore action: either *
 * edit_tests or delete_tests grants it.                 *
 ******************************************************/
function can_retire_tests() {
    // Retire/restore is a reversible lifecycle action; allow it for anyone who can
    // edit OR delete tests. Route through check_permission() (strict == 1 + the
    // standard denial audit-log line) rather than a looser !empty() session read,
    // matching every other permission gate in the app.
    return check_permission('edit_tests') || check_permission('delete_tests');
}

/******************************************************
 * FUNCTION: GET TEST IDS FROM FRAMEWORK CONTROL TEST *
 ******************************************************/
function get_framework_control_test_ids(){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT `id` FROM `framework_control_tests`; ");
    $stmt->execute();

    $array = $stmt->fetchAll();

    // closed the database connection
    db_close($db);
    return $array;
}

/***********************************************************
 * FUNCTION: GET FRAMEWORK CONTROL TEST LIST BY CONTROL ID *
 ***********************************************************/
function get_framework_control_tests_by_control_id($framework_control_id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.*, t2.name tester_name, GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag) as tags
        FROM `framework_control_tests` t1
            JOIN `test_control_map` tcm ON tcm.test_id = t1.id
            LEFT JOIN `user` t2 ON t1.tester = t2.value
            LEFT JOIN tags_taggees tt ON tt.taggee_id = t1.id AND tt.type = 'test'
            LEFT JOIN tags tg on tg.id = tt.tag_id
        WHERE tcm.framework_control_id=:framework_control_id
        GROUP By t1.id
        ");
    $stmt->bindParam(":framework_control_id", $framework_control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array;
}

/***************************************************
 * FUNCTION: GET FRAMEWORK CONTROL TEST BY TEST ID *
 ***************************************************/
function get_framework_control_test_by_id($test_id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            `t1`.*,
            IF(t1.audit_initiation_offset IS NULL, 0, 1) AS auto_audit_initiation,
            `t2`.`name` tester_name,
            GROUP_CONCAT(DISTINCT `itt`.`team_id`) teams,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag) as tags,
            GROUP_CONCAT(DISTINCT fcta.user_id) approvers,
            GROUP_CONCAT(DISTINCT tcm.framework_control_id) controls
        FROM
            `framework_control_tests` t1
            LEFT JOIN `user` t2 ON t1.tester = t2.value
            LEFT JOIN `items_to_teams` itt ON `itt`.`item_id` = `t1`.`id` and `itt`.`type` = 'test'
            LEFT JOIN tags_taggees tt ON tt.taggee_id = t1.id AND tt.type = 'test'
            LEFT JOIN tags tg on tg.id = tt.tag_id
            LEFT JOIN framework_control_test_approvers fcta ON fcta.test_id = t1.id
            LEFT JOIN test_control_map tcm ON tcm.test_id = t1.id
        WHERE
            `t1`.`id`=:test_id;
    ");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the test in the array
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    if($test['additional_stakeholders']){
        $test['additional_stakeholders'] = explode(",", $test['additional_stakeholders']);
    }

    if($test['teams']){
        $test['teams'] = explode(",", $test['teams']);
    }
    if($test['tags']){
        $test['tags'] = explode(",", $test['tags']);
    }
    if($test['approvers']){
        $test['approvers'] = explode(",", $test['approvers']);
    } else {
        $test['approvers'] = [];
    }

    if($test['controls']){
        $test['controls'] = explode(",", $test['controls']);
    } else {
        $test['controls'] = [];
    }

    // Phase 4a (common tests): id -> short_name map for every mapped control.
    // Resolved via a small follow-up query rather than a second GROUP_CONCAT in
    // the query above -- avoids delimiter-collision and independent-ordering risk
    // between two parallel DISTINCT aggregates over the same one-to-many join.
    $test['control_names'] = [];
    if (!empty($test['controls'])) {
        $db = db_open();
        $placeholders = implode(',', array_fill(0, count($test['controls']), '?'));
        $names_stmt = $db->prepare("SELECT `id`, `short_name` FROM `framework_controls` WHERE `id` IN ({$placeholders})");
        foreach (array_values($test['controls']) as $index => $control_id) {
            $names_stmt->bindValue($index + 1, (int)$control_id, PDO::PARAM_INT);
        }
        $names_stmt->execute();
        foreach ($names_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $test['control_names'][$row['id']] = $row['short_name'];
        }
        db_close($db);
    }

    return $test;
}

/*********************************************************
 * FUNCTION: GET FRAMEWORK CONTROL TEST AUDIT BY TEST ID *
 *********************************************************/
function get_framework_control_test_audit_by_id($test_audit_id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT 
            audits.id,
            audits.test_id,
            audits.tester,
            audits.test_frequency,
            tests.last_date,
            tests.next_date,
            audits.name,
            audits.objective,
            audits.test_steps,
            audits.approximate_time,
            audits.expected_results,
            audits.framework_control_id,
            audits.desired_frequency,
            audits.status,
            audits.created_at,
            u.name tester_name,
            ctrl.short_name control_name,
            ctrl.control_owner,
            IFNULL(GROUP_CONCAT(DISTINCT fw.name), '') framework_name,
            results.id result_id,
            results.test_result,
            results.summary,
            results.test_date,
            results.submitted_by,
            results.submission_date,
            tests.additional_stakeholders,
            GROUP_CONCAT(DISTINCT `itt`.`team_id`) teams,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag) as tags,
            tests.audit_initiation_offset,
            GROUP_CONCAT(DISTINCT acm.framework_control_id) controls
        FROM `framework_control_test_audits` audits
            LEFT JOIN `user` u ON audits.tester = u.value
            LEFT JOIN `framework_controls` ctrl ON audits.framework_control_id = ctrl.id
            LEFT JOIN `framework_control_mappings` ctrl_m ON ctrl.id = ctrl_m.control_id
            LEFT JOIN `frameworks` fw ON ctrl_m.framework=fw.value
            LEFT JOIN `framework_control_test_results` results ON audits.id=results.test_audit_id
            LEFT JOIN `framework_control_tests` tests ON tests.id=audits.test_id
            LEFT JOIN `items_to_teams` itt ON `itt`.`item_id` = `audits`.`id` and `itt`.`type` = 'audit'
            LEFT JOIN `tags_taggees` tt ON tt.taggee_id = audits.id AND tt.type = 'test_audit'
            LEFT JOIN `tags` tg on tg.id = tt.tag_id
            LEFT JOIN `audit_control_map` acm ON acm.audit_id = audits.id

        WHERE audits.id=:test_audit_id and ctrl.deleted = 0
    ");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the test in the array
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    if($test['additional_stakeholders']){
        $test['additional_stakeholders'] = explode(",", $test['additional_stakeholders']);
    }

    if($test['framework_name']){
        $framework_names = explode(",", $test['framework_name']);
        $decrypted_framework_names = [];
        foreach($framework_names as $framework_name)
        {
            if($framework_name){
                $decrypted_framework_names[] = try_decrypt(trim($framework_name));
            }
        }
        $test['framework_name'] = implode(", ", $decrypted_framework_names);
    }

    if($test['teams']){
        $test['teams'] = explode(",", $test['teams']);
    }

    if($test['controls']){
        $test['controls'] = explode(",", $test['controls']);
    } else {
        $test['controls'] = [];
    }

    // Phase 4b (common tests): id -> short_name map for every control the
    // audit belongs to, resolved via audit_control_map. Mirrors the test
    // getter's control_names resolution (get_framework_control_test_by_id(),
    // ~1526-1542) -- a follow-up bound query rather than a second
    // GROUP_CONCAT in the query above, to avoid delimiter-collision and
    // independent-ordering risk between two parallel DISTINCT aggregates
    // over the same one-to-many join. The existing scalar `control_name`
    // (from the `ctrl` join above) is left untouched for back-compat -- it
    // stays the min-control name.
    $test['control_names'] = [];
    if (!empty($test['controls'])) {
        $db = db_open();
        $placeholders = implode(',', array_fill(0, count($test['controls']), '?'));
        $names_stmt = $db->prepare("SELECT `id`, `short_name` FROM `framework_controls` WHERE `id` IN ({$placeholders})");
        foreach (array_values($test['controls']) as $index => $control_id) {
            $names_stmt->bindValue($index + 1, (int)$control_id, PDO::PARAM_INT);
        }
        $names_stmt->execute();
        foreach ($names_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $test['control_names'][$row['id']] = $row['short_name'];
        }
        db_close($db);
    }

    return $test;
}

/********************************************************
 * FUNCTION: GET TEST AUDIT STATUS 						*
 * Returns the `test_status` id of the audit's status	*
 ********************************************************/
function get_test_audit_status($test_audit_id) {

    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("
        SELECT
            `status`
        FROM
            `framework_control_test_audits`
        WHERE
            `id` = :test_audit_id;
    ");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    $status = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return $status;
}
/************************************
 * FUNCTION: GET TEST AUDIT NAME	*
 ************************************/
function get_test_audit_name($test_audit_id) {
    
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("
        SELECT
            `name`
        FROM
            `framework_control_test_audits`
        WHERE
            `id` = :test_audit_id;
    ");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $name = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);
    
    return $name;
}

/*************************************
 * FUNCTION: DISPLAY INITIATE AUDITS *
 *************************************/
function display_initiate_audits() {

    global $lang, $escaper;
    
    echo "
		<div class='card-body border my-2'>
			<div id='filter-container'>
				<div class='row mb-3'>
					<div class='col-6'>
						<label>{$escaper->escapeHtml($lang['FilterByText'])} :</label>
						<input type='text' id='filter_by_text' class='form-control'>
					</div>
					<div class='col-6'>
						<label>{$escaper->escapeHtml($lang['TestFrequency'])} :</label>
						<input type='text' id='filter_by_frequency' class='form-control'>
					</div>
				</div>
				<div class='row'>
					<div class='col-6'>
						<label class='hide'>{$escaper->escapeHtml($lang['Status'])} :</label>
						<div class='span2 hide'>
							<div class='multiselect-content-container'>
	";
								create_multiple_dropdown("test_status", "all", "filter_by_status", NULL, false);
	echo "
							</div>
						</div>
					</div>
				</div>
				<div class='row'>
					<div class='col-6'>
						<label>{$escaper->escapeHtml($lang['Framework'])} :</label>
						<div class='multiselect-content-container'>
							<select id='filter_by_framework' class='' multiple=''>
	";

	echo "
								<option selected value='0'>{$escaper->escapeHtml($lang['Unassigned'])}</option>
	";
	$options = getAvailableControlFrameworkList(true);
	is_array($options) || $options = array();
	foreach($options as $option) {
		echo "
								<option selected value='{$escaper->escapeHtml($option['value'])}'>{$escaper->escapeHtml($option['name'])}</option>
		";
	}

	echo "
							</select>
						</div>
					</div>
					<div class='col-6'>
						<label>{$escaper->escapeHtml($lang['Control'])} :</label>
						<input type='text' id='filter_by_control' class='form-control'>
					</div>
				</div>
			</div>
		</div>
		<div class='card-body border my-2'>
			<table id='initiate_audit_treegrid'>
				<thead>
					<th data-options=\"field:'name'\" width='57%'>{$escaper->escapeHtml($lang['Name'])}</th>
					<th data-options=\"field:'test_frequency'\"  width='8%'>{$escaper->escapeHtml($lang['TestFrequency'])}</th>
					<th data-options=\"field:'last_audit_date'\"  width='10%'>{$escaper->escapeHtml($lang['LastAuditDate'])}</th>
					<th data-options=\"field:'next_audit_date'\"  width='10%'>{$escaper->escapeHtml($lang['NextAuditDate'])}</th>
					<th data-options=\"field:'status'\"  width='5%'>{$escaper->escapeHtml($lang['Status'])}</th>
					<th data-options=\"field:'action'\" width='10%'>&nbsp;</th>
				</thead>
			</table>
		</div>
        		
		<script>

            // Redraw Past Audit table
            function redraw(){
                $('#initiate_audit_treegrid').treegrid('reload');
            } 

            // timer identifier
            var typingTimer;                
            // time in ms (1 second)
            var doneTypingInterval = 1000;  

            $(document).ready(function(){
                $('#filter_by_framework').multiselect({
                    allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
                    enableFiltering: true,
                    maxHeight: 250,
                    includeSelectAllOption: true,
                    buttonWidth: '100%',
                    enableCaseInsensitiveFiltering: true,
                    onDropdownHide: function(){
                        redraw();
                    }
                });
                
                $('#filter_by_status').multiselect({
                    allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
                    includeSelectAllOption: true,
                    enableCaseInsensitiveFiltering: true,
                    onDropdownHide: function(){
                        redraw();
                    }
                });
                
                // Search filter event
                $('#filter_by_text').keyup(function(){
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(redraw, doneTypingInterval);
                });

                // Search filter event
                $('#filter_by_control').keyup(function(){
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(redraw, doneTypingInterval);
                });

                // Search filter event
                $('#filter_by_frequency').keyup(function(){
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(redraw, doneTypingInterval);
                });

                $('#initiate_audit_treegrid').initAsInitiateAuditTreegrid();
            });
        </script>
    ";

}

/***********************************
 * FUNCTION: DISPLAY ACTIVE AUDITS *
 ***********************************/
function display_active_audits() {

    global $lang, $escaper;
    
	echo "
		<div class='card-body border my-2'>
			<div class='row'>
				<div class='col-10'></div>
				<div class='col-2'>
					<div style='float: right;'>
	";
						render_column_selection_widget('active_audits');
	echo "
					</div>
				</div>
			</div>
			<div class='row'>
				<div class='col-12'>
	";
					render_view_table('active_audits');
	echo "
				</div>
			</div>
		</div>
        <script>
            $(function () {
                initializeMultiselect('.header_filter .multiselect', {
                    allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
					includeSelectAllOption: true,
					buttonWidth: '100%',
                    maxHeight: 400,
					enableCaseInsensitiveFiltering: true,
				});

                $('.header_filter [name=test_date].datepicker').initAsDateRangePicker();

                $('body').on('click', '.delete-btn', function() {
                    confirm('{$escaper->escapeJs($lang['AreYouSureYouWantToDeleteThisTest'])}', () => {
                        var id = $(this).data('id')
    
                        $.ajax({
                            type: 'POST',
                            url: BASE_URL + '/api/v2/compliance/delete_audit',
                            data : {
                                id: id
                            },
                            success: function(data){
                                if(data.status_message){
                                    showAlertsFromArray(data.status_message);
                                }
                                datatableInstances['active_audits'].ajax.reload(null, false);
                            },
                            error: function(xhr,status,error){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                                if(!retryCSRF(xhr, this))
                                {
                                }
                            }
                        });
                    });
				});
            });
        </script>
	";

}

/************************************
 * INITIATE FRAMEWORK CONTROL TESTS *
 ************************************/
function initiate_framework_control_tests($type, $id, $tags=[], &$new_audit_id=null){
    $initiated_audit_status = get_setting("initiated_audit_status") ? get_setting("initiated_audit_status") : 0;

     // If team separation is enabled
    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // It means that either the user is an admin
        // or everyone has access to the tests/audits.
        // It means we can treat Team Separation like it is disabled
        if (should_skip_test_and_audit_permission_check()) {
            $separation_enabled = false;
        } else {
            $separation_enabled = true;
            $compliance_separation_access_info = get_compliance_separation_access_info();
        }
    } else
        $separation_enabled = false;

    // Open the database connection
    $db = db_open();

    $name = null;
    switch($type){
        case "framework":
            if ($separation_enabled && !in_array($id, $compliance_separation_access_info['frameworks']))
                return false;
            
            $framework = get_framework($id);
            $name = $framework['name'];
            
            $child_frameworks = get_all_child_frameworks($id, 1);
            $framework_ids = array_merge(array($id), array_map(function($row){
                return $row['value'];
            }, $child_frameworks));

            // Phase 4b (common tests): route the test-selection through
            // test_control_map so a common test is found via ANY control it
            // maps to (not just its scalar framework_control_id), while
            // DISTINCT t1.id keeps a common test fanning into exactly one
            // initiate_test_audit() call even when several of its mapped
            // controls belong to this framework.
            $sql = "
                SELECT
                    DISTINCT t1.id
                FROM `framework_control_tests` t1
                    INNER JOIN `test_control_map` tcm ON tcm.test_id=t1.id
                    INNER JOIN `framework_controls` t2 ON t2.id=tcm.framework_control_id AND t2.deleted=0
                    INNER JOIN `framework_control_mappings` m ON t2.id=m.control_id
                WHERE FIND_IN_SET(m.framework, :framework_ids)
                    AND t1.`retired_at` IS NULL; ";

            $stmt = $db->prepare($sql);
            $framework_id_string = implode(",", $framework_ids);
            $stmt->bindParam(":framework_ids", $framework_id_string, PDO::PARAM_STR);
            $stmt->execute();

            $test_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            
            foreach($test_ids as $test_id){
                // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
                if ($separation_enabled && !in_array($test_id, $compliance_separation_access_info['framework_control_tests']))
                    continue;

                initiate_test_audit($test_id, $initiated_audit_status, $tags);
            }
        break;
        case "control":
            if ($separation_enabled && !in_array($id, $compliance_separation_access_info['framework_controls']))
                return false;

            $control = get_framework_control($id);
            $name = $control['short_name'];

            // Phase 4b (common tests): route through test_control_map (same
            // rationale as the framework case above) with DISTINCT so a
            // common test mapped to :control_id more than once still
            // produces one row.
            $stmt = $db->prepare("
                SELECT
                    DISTINCT t1.id
                FROM framework_control_tests t1
                    INNER JOIN test_control_map tcm ON tcm.test_id=t1.id
                    INNER JOIN framework_controls t2 ON t2.id=tcm.framework_control_id AND t2.deleted=0
                WHERE
                    t2.id=:control_id;
            ");
            $stmt->bindParam(":control_id", $id, PDO::PARAM_INT);
            $stmt->execute();

            $test_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            foreach($test_ids as $test_id){
                // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
                if ($separation_enabled && !in_array($test_id, $compliance_separation_access_info['framework_control_tests']))
                    continue;

                initiate_test_audit($test_id, $initiated_audit_status, $tags);
            }
        break;
        case "test":
            if ($separation_enabled && !in_array($id, $compliance_separation_access_info['framework_control_tests']))
                return false;

            // Capture the new audit id so the caller (API) can hand it back for a
            // one-click "Start test -> open the audit" flow.
            $name = initiate_test_audit($id, $initiated_audit_status, $tags, true, $new_audit_id);
        break;
    }

    // Close the database connection
    db_close($db);
    
    return $name;
}

function initiate_test_audit($test_id, $initiated_audit_status, $tags=[], $requested_from_ui=true, &$out_audit_id=null) {

    $test = get_framework_control_test_by_id($test_id);

    // A retired test must never spawn new audit work. The selection queries
    // that feed this function already filter on retired_at, but this is the
    // single choke point every initiate path (framework / control / test, UI
    // and programmatic) funnels through, so the guard lives here too -- a
    // retired test reached by any future caller still can't be initiated.
    if (!empty($test['retired_at'])) {
        write_debug_log("Refusing to initiate audit for retired framework_control_test {$test_id}", "notice");
        return false;
    }

    $name = $test['name'];

    // Open the database connection
    $db = db_open();

    // Phase 4b (common tests): one audit per test per open due-window,
    // across separate initiate calls (e.g. two control-button clicks that
    // both fan out to the same common test -- Task 3a's junction-routed
    // fan-out above is what makes that collision possible in the first
    // place). If this test already has an audit for the SAME next_date
    // window that is still open (not truly closed) or awaiting approval,
    // this initiate is a no-op: hand back the existing audit id and return
    // without inserting a duplicate audit/result/snapshot. A NEW due-window
    // (different next_date) or a fully truly-closed prior audit
    // (status=closed AND approval_state IN ('none','approved')) is NOT
    // suppressed -- matches Josh's decision that a new window still
    // initiates. Tests with no meaningful window can't be window-deduped, so
    // the guard is skipped entirely for them: next_date null/empty, OR the
    // zero-date sentinel '0000-00-00' (framework_control_tests.next_date is
    // NOT NULL, so a "no schedule" test -- e.g. manual schedule_type -- stores
    // '0000-00-00', not real NULL; without this a manual test would wrongly
    // window-dedup all its re-initiations against a single zero-date bucket).
    $next_date = $test['next_date'] ?? null;
    if (!empty($next_date) && $next_date !== '0000-00-00') {
        // Reuse the same "truly closed" predicate used throughout this file
        // (see get_framework_control_test_audits()'s Active/Past view wheres):
        // open/in-flight = status <> closed OR approval_state = 'pending'.
        $closed_audit_status = (int)get_setting("closed_audit_status");

        $stmt = $db->prepare("
            SELECT id FROM `framework_control_test_audits`
            WHERE test_id = :test_id AND next_date = :next_date
                AND (status <> :closed OR approval_state = 'pending')
            ORDER BY id DESC LIMIT 1;
        ");
        $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
        $stmt->bindParam(":next_date", $next_date, PDO::PARAM_STR);
        $stmt->bindParam(":closed", $closed_audit_status, PDO::PARAM_INT);
        $stmt->execute();
        $existing_audit_id = $stmt->fetchColumn();

        if ($existing_audit_id) {
            $out_audit_id = (int)$existing_audit_id;

            // Close the database connection
            db_close($db);

            return $name;
        }
    }

    $sql = "
        INSERT INTO
            `framework_control_test_audits`(test_id, tester, test_frequency, last_date, next_date, name, objective, test_steps, approximate_time, expected_results, framework_control_id, desired_frequency, status, created_at)
        SELECT
            t1.id as test_id, t1.tester, t1.test_frequency, t1.last_date, t1.next_date, t1.name, t1.objective, t1.test_steps, t1.approximate_time, t1.expected_results, t1.framework_control_id, t1.desired_frequency, {$initiated_audit_status} as status, NOW() as created_at
        FROM framework_control_tests t1
        WHERE
            t1.id=:test_id;
    ";

    // Create temp table from framework_control_test
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);

    $stmt->execute();

    $audit_id = $db->lastInsertId();

    // Phase 4b (common tests): snapshot the test's control set into
    // audit_control_map at initiation time, so the audit "belongs to" every
    // control the common test maps to -- not just the scalar
    // framework_control_id (already copied by the INSERT..SELECT above,
    // kept as the back-compat min-control column). $test['controls'] was
    // already loaded via get_framework_control_test_by_id() at the top of
    // this function. Fall back to the scalar framework_control_id for
    // pre-4a tests that have no test_control_map rows, so the snapshot is
    // never empty. Written on the already-open $db (not save_junction_values,
    // which opens its own connection) so it's transactionally coherent with
    // the audit insert above. Reachable from both the UI and cron initiate
    // paths, since both call initiate_test_audit().
    $snap = array_map('intval', $test['controls'] ?? []);
    $snap = array_values(array_filter($snap));
    if (empty($snap) && !empty($test['framework_control_id'])) {
        $snap = [(int)$test['framework_control_id']];
    }
    if (!empty($snap)) {
        // Positional placeholders (not a repeated named :aid) so the multi-row
        // INSERT works regardless of PDO::ATTR_EMULATE_PREPARES -- native MySQL
        // prepares reject a named placeholder reused across value groups.
        $value_groups = array_fill(0, count($snap), '(?, ?)');
        $params = [];
        foreach ($snap as $control_id) {
            $params[] = (int)$audit_id;
            $params[] = (int)$control_id;
        }
        $stmt = $db->prepare("
            INSERT IGNORE INTO `audit_control_map` (`audit_id`, `framework_control_id`)
            VALUES " . implode(', ', $value_groups) . ";
        ");
        $stmt->execute($params);
    }

    // Stamp approval_state on the new audit. Tests with >=1 configured approver
    // (Phase 3a) require sign-off before a close sticks -- start those audits
    // 'pending' so save_test_result()'s close branch (below, ~2851) knows to
    // hold rather than advance. Tests with no approvers keep the column's
    // 'none' default (today's behavior). Applies to both the UI and cron
    // initiation paths, since both call initiate_test_audit().
    if (count($test['approvers'] ?? []) >= 1) {
        $stmt = $db->prepare("UPDATE `framework_control_test_audits` SET `approval_state`='pending' WHERE `id`=:audit_id;");
        $stmt->bindParam(":audit_id", $audit_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Expose the new audit id to callers that want to navigate straight to it
    // (e.g. the dashboard's one-click "Start test"). Return stays the name for
    // backward compatibility with the existing alert-message callers.
    $out_audit_id = (int) $audit_id;

    $stmt = $db->prepare("INSERT INTO framework_control_test_results (`test_audit_id`) VALUES(:test_audit_id);");
    $stmt->bindParam(":test_audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->execute();
    $result_id = $db->lastInsertId();

    $stmt = $db->prepare("
        SELECT t1.* FROM `framework_control_test_results` t1
        INNER JOIN `framework_control_test_audits` t2 ON t1.test_audit_id = t2.id
        WHERE t2.test_id = :test_id AND t1.id != :result_id AND t1.test_result != 'Pass' 
        ORDER By id DESC LIMIT 0,1");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->bindParam(":result_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();
    $test_result = $stmt->fetch(PDO::FETCH_ASSOC);
    if($test_result){
        $risk_ids = get_test_result_to_risk_ids($test_result["id"]);
        foreach($risk_ids as $risk_id) {
            save_test_result_to_risk($result_id, $risk_id);
        }
    }

    updateTeamsOfItem($audit_id, 'audit', $test['teams']);

    // Add Tags to Test 
    $tags_current = getTagsOfTaggee($test_id, "test");
    $new_tags = array_unique(array_merge($tags, $tags_current));
    updateTagsOfType($audit_id, 'test_audit', $new_tags);

    // Close the database connection
    db_close($db);

    // Send the notification (no-op if notification extra is disabled)
    call_extra_function(
        'notification_extra',
        __DIR__ . '/../extras/notification/index.php',
        'notify_audit_initiate',
        [$audit_id, $requested_from_ui]
    );

    // If the initiate test audit was requested from the UI
    if ($requested_from_ui) {

        $message = "An active audit for \"{$test["name"]}\" was initiated by username \"" . $_SESSION['user'] . "\".";
        write_log((int)$test_id + 1000, $_SESSION['uid'] ?? 0, $message, "test");

    // If the initiate test audit was requested from an automated process
    } else {

        $message = "An active audit for \"{$test["name"]}\" was initiated.";
        write_debug_log($message, "info");
        write_log((int)$test_id + 1000, 0, $message, "test");

    }

    trigger_workflow_event('audit.initiated', [
        'audit_id'   => $audit_id,
        'test_id'    => $test_id,
        'control_id' => $test['framework_control_id'],
    ]);

    return $name;
}

/***********************************************
 * FUNCTION: GET FRAMEWORK CONTROL TEST AUDITS *
 ***********************************************/
function get_framework_control_test_audits($active, $columnName=false, $columnDir=false, $filters=false, $column_filters=[]){
    global $escaper;

    // Open the database connection
    $db = db_open();

    $select_background_class = $active ? "" : ", t8.background_class ";

    $sql = "
        SELECT t1.id, t1.test_id, t1.test_frequency, t7.last_date, t7.next_date, t1.name, t1.objective, t1.test_steps,
            t1.approximate_time, t1.expected_results, t1.framework_control_id, t1.desired_frequency, t1.status, t1.created_at,
            t2.name tester_name,
            -- Phase 4b: a common-test audit belongs to ALL its snapshot controls
            -- (audit_control_map), not just the scalar min-control t3 join. List
            -- every snapshot control's short_name via a correlated GROUP_CONCAT
            -- subquery under the GROUP BY t1.id so the audit stays ONE row.
            -- framework_name below stays scoped to the min control's frameworks
            -- (t3/t4), a documented Phase 4b limitation.
            (SELECT GROUP_CONCAT(DISTINCT fc_acm.short_name ORDER BY fc_acm.short_name ASC SEPARATOR ', ') FROM `audit_control_map` acm JOIN `framework_controls` fc_acm ON fc_acm.id = acm.framework_control_id WHERE acm.audit_id = t1.id) control_name,
            IFNULL(GROUP_CONCAT(DISTINCT t4.name), '') framework_name, t5.test_result,
            t5.summary, t5.submitted_by, t5.test_date, t5.submission_date, ifnull(t6.name, '--') audit_status_name, 
            t7.additional_stakeholders{$select_background_class},
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag) as tags
        FROM `framework_control_test_audits` t1
            LEFT JOIN `user` t2 ON t1.tester = t2.value
            LEFT JOIN `framework_controls` t3 ON t1.framework_control_id = t3.id 
            LEFT JOIN `framework_control_mappings` m ON t3.id=m.control_id
            LEFT JOIN `frameworks` t4 ON m.framework=t4.value AND t4.status=1
            LEFT JOIN `framework_control_mappings` m_1 ON t3.id=m_1.control_id
            LEFT JOIN `framework_control_test_results` t5 ON t1.id=t5.test_audit_id
            LEFT JOIN `test_status` t6 ON t1.status=t6.value
            LEFT JOIN `framework_control_tests` t7 ON t7.id=t1.test_id
            LEFT JOIN `test_results` t8 ON t8.name=t5.test_result
            LEFT JOIN `tags_taggees` tt ON tt.taggee_id = t1.id AND tt.type = 'test_audit'
            LEFT JOIN `tags` tg on tg.id = tt.tag_id
    ";

    $wheres = array();
    $havings = array();

    $closed_audit_status = get_setting("closed_audit_status");

    // Active audits
    if($active)
    {
        // "Truly closed" predicate (Task 4 of the Phase 3b audit approval
        // workflow), mirrored from get_wheres_for_view() in functions.php so
        // the UI datatable and this API-backing query agree: an audit whose
        // status column was set to closed by save_test_result() but whose
        // approval_state is still 'pending' (a required approver hasn't
        // signed off -- see audit_is_awaiting_approval()) is held awaiting
        // approval, not truly closed, so it stays in Active.
        $wheres[] = " (t1.status<>'".$closed_audit_status."' OR t1.approval_state='pending') ";
    }
    // Past audits
    else
    {
        // Mirrors the active-audits carve-out above: Past only shows audits
        // that are both status=closed AND truly closed (approval_state is
        // 'none' -- no approval required, today's behavior -- or 'approved'
        // -- signed off). A 'pending' closed audit stays out of Past until
        // approve_audit() flips it to 'approved'.
        $wheres[] = " (t1.status='".$closed_audit_status."' AND t1.approval_state IN ('none','approved')) ";
    }

    if($filters !== false){
        if(isset($filters['filter_control'])){
            if($filters['filter_control'])
            {
                foreach($filters['filter_control'] as &$val){
                    $val = (int)$val;
                }
                unset($val);

                $wheres[] = " t3.id IN (".implode(",", $filters['filter_control']).") ";
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }

        if(isset($filters['filter_test_result'])){
            if($filters['filter_test_result'])
            {
                foreach($filters['filter_test_result'] as &$val){
		          $val = (int)$val;
                }
                unset($val);

                if(in_array(0, $filters['filter_test_result'])) {
                    $wheres[] = " (t5.test_result='' OR t8.value IN (".implode(",", $filters['filter_test_result']).")) ";
                } else {
                    $wheres[] = " t8.value IN (".implode(",", $filters['filter_test_result']).") ";
                }
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }

        if(isset($filters['filter_framework'])){
            if($filters['filter_framework']){
                $framework_wheres = [];

                foreach($filters['filter_framework'] as $val){
                    if(!$val)
                        continue;
                    $val = (int)$val;
                    // If unassigned option.
                    if($val == -1)
                    {
                        $framework_wheres[] = "m.framework IS NULL";
                    }
                    else
                    {
                        $framework_wheres[] = "m_1.framework='{$val}'";
                    }
                }

                $wheres[] = "(". implode(" OR ", $framework_wheres) . ")";
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }

        if(isset($filters['filter_status'])){
            if($filters['filter_status'])
            {
                foreach($filters['filter_status'] as &$val){
                    $val = (int)$val;
                }
                unset($val);

                $wheres[] = " t1.status IN (".implode(",", $filters['filter_status']).") ";
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }
        if(!empty($filters['filter_start_audit_date'])){
            $wheres[] = " t1.last_date>=:filter_start_audit_date ";
        }
        if(!empty($filters['filter_end_audit_date'])){
            $wheres[] = " t1.last_date<=:filter_end_audit_date ";
        }
        if(isset($filters['filter_tester'])){
            if($filters['filter_tester'])
            {
                foreach($filters['filter_tester'] as &$val){
                    $val = (int)$val;
                }
                unset($val);

                $wheres[] = " t1.tester IN (".implode(",", $filters['filter_tester']).") ";
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }

        if (isset($filters['filter_testname'])) {
            if ($filters['filter_testname']) {
                foreach ($filters['filter_testname'] as &$val) {
                    $val = (int)$val;
                }
                unset($val);

                $wheres[] = " t7.id IN (".implode(",", $filters['filter_testname']).") ";
            } else {
                $wheres[] = " 0 ";
            }
        }

        if (isset($filters['filter_tags'])) {
            $tag_wheres = [];
            $tag_ids = [];
            if ($filters['filter_tags']) {
                foreach ($filters['filter_tags'] as $val) {
                    $val = (int)$val;
                    // If unassigned option.
                    if($val == -1)
                    {
                        $havings[] = "ISNULL(GROUP_CONCAT(DISTINCT tg.id))";
                    }
                    else
                    {
                        $tag_ids = $val;
                        $havings[] = "FIND_IN_SET({$val},GROUP_CONCAT(DISTINCT tg.id))"; 
                        //$tag_wheres[] = "tg.id='{$val}'";
                    }
                }
            } else {
                $wheres[] = " 0 ";
            }
        }
    }

    $bind_params = [];
    $manual_column_filters = [];
    foreach($column_filters as $name => $column_filter){
        if($name == "test_name"){
            $wheres[] = "t1.name LIKE :test_name ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "test_frequency"){
            $wheres[] = "t1.test_frequency LIKE :test_frequency ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "tester") {
            $wheres[] = "t2.name LIKE :tester ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "additional_stakeholders") {
            $wheres[] = "t7.additional_stakeholders LIKE :additional_stakeholders ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "objective") {
            $wheres[] = "t1.objective LIKE :objective ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "control_name") {
            $wheres[] = "t3.short_name LIKE :control_name ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "test_result") {
            $wheres[] = "t8.name LIKE :test_result ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "status") {
            $wheres[] = "t6.name LIKE :status ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "tags") {
            $wheres[] = "tg.tag LIKE :tags ";
            $bind_params[$name] = "%{$column_filter}%";
        } else {
            $manual_column_filters[$name] = $column_filter;
        }
    }

    $sql .= " WHERE t3.deleted = 0 AND ".implode(" AND ", $wheres);
    $sql .= " GROUP BY t1.id ";
    if(count($havings)) $sql .= " HAVING 1 AND (".implode(" OR ", $havings).")";
    
    if($columnName == "test_name"){
        $sql .= " ORDER BY t1.name {$columnDir} ";
    }
    elseif($columnName == "test_frequency"){
        $sql .= " ORDER BY t1.test_frequency {$columnDir} ";
    }
    elseif($columnName == "tester"){
        $sql .= " ORDER BY t2.name {$columnDir} ";
    }
    elseif($columnName == "objective"){
        $sql .= " ORDER BY t1.objective {$columnDir} ";
    }
    elseif($columnName == "control_name"){
        $sql .= " ORDER BY t3.short_name {$columnDir} ";
    }
    elseif($columnName == "framework_name"){
        $sql .= " ORDER BY GROUP_CONCAT(DISTINCT t4.name) {$columnDir} ";
    }
    elseif($columnName == "status"){
        $sql .= " ORDER BY t6.name {$columnDir} ";
    }
    elseif($columnName == "last_date"){
        $sql .= " ORDER BY t1.last_date {$columnDir}, t5.last_updated {$columnDir} ";
    }
    elseif($columnName == "next_date"){
        $sql .= " ORDER BY t1.next_date {$columnDir} ";
    }
    elseif($columnName == "test_result"){
        $sql .= " ORDER BY t5.test_result {$columnDir} ";
    }
    elseif($columnName == "additional_stakeholders"){
        $sql .= " ORDER BY t7.additional_stakeholders {$columnDir} ";
    }
    elseif($columnName == "tags"){
        $sql .= " ORDER BY tg.tag {$columnDir} ";
    }
    else{
        // Active audits
        if($active)
        {
            $sql .= " ORDER BY t1.created_at DESC ";
        }
        // Past audits
        else
        {
            $sql .= " ORDER BY t5.last_updated DESC ";
        }
    }

    $stmt = $db->prepare($sql);
    if($filters !== false){
        if(!empty($filters['filter_start_audit_date'])){
            $stmt->bindParam(":filter_start_audit_date", $filters['filter_start_audit_date'], PDO::PARAM_STR, 10);
        }
        if(!empty($filters['filter_end_audit_date'])){
            $stmt->bindParam(":filter_end_audit_date", $filters['filter_end_audit_date'], PDO::PARAM_STR, 10);
        }
    }
    foreach($bind_params as $name => $bind_param){
        $stmt->bindParam(":{$name}", $bind_param);
    }

    $stmt->execute();

    // Store tests in the array
    $test_audits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

     // If team separation is enabled
    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // It means that either the user is an admin
        // or everyone has access to the tests/audits.
        // It means we can treat Team Separation like it is disabled        
        $separation_enabled = !should_skip_test_and_audit_permission_check();
    } else
        $separation_enabled = false;

    $filtered_test_audits = array();

    foreach($test_audits as &$test_audit){
        if ($separation_enabled && !is_user_allowed_to_access($_SESSION['uid'], $test_audit['id'], 'audit'))
            continue;

        $framework_names = explode(",", $test_audit['framework_name']);
        $decrypted_framework_names = [];
        foreach($framework_names as $framework_name){
            if($framework_name){
                $decrypted_framework_names[] = try_decrypt(trim($framework_name));
            }
        }
        
        $test_audit['framework_name'] = implode(", ", $decrypted_framework_names);
        $test_audit['last_date'] = format_date($test_audit['last_date']);
        $test_audit['next_date'] = format_date($test_audit['next_date']);
        // Filter by search text
        if(
            empty($filters['filter_text']) 
            || (stripos($test_audit['name'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['last_date'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['control_name'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['framework_name'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['audit_status_name'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['test_result'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['objective'], $filters['filter_text']) !== false) 
        )
        {
            $success = true;
            foreach($manual_column_filters as $column_name => $val){
                if($column_name == "last_date") {
                    if( stripos($test_audit['last_date'], $val) === false ){
                        $success = false;
                        break;
                    }
                } else if($column_name == "next_date") {
                    if( stripos($test_audit['next_date'], $val) === false ){
                        $success = false;
                        break;
                    }
                } else if($column_name == "framework_name") {
                    if( stripos($test_audit['framework_name'], $val) === false ){
                        $success = false;
                        break;
                    }
                }
            }
            if($success) $filtered_test_audits[] = $test_audit;
        }
    }

    return $filtered_test_audits;
}

/*******************************
 * FUNCTION: SAVE TEST COMMENT *
 *******************************/
function save_test_comment($test_audit_id, $comment){
    $user    =  $_SESSION['uid'];
    
    // Make sure the user has permission to comment
    if($_SESSION["comment_compliance"] == 1) {

        // Open the database connection
        $db = db_open();
        
        $sql = "
            INSERT INTO `framework_control_test_comments`(`test_audit_id`, `user`, `comment`) VALUES(:test_audit_id, :user, :comment);
        ";

        $enc_comment = try_encrypt($comment);
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
        $stmt->bindParam(":comment", $enc_comment, PDO::PARAM_STR);
        $stmt->bindParam(":user", $user, PDO::PARAM_INT);
        
        // Insert a test result
        $stmt->execute();
        
        // Close the database connection
        db_close($db);
        
        // Send the notification (no-op if notification extra is disabled)
        call_extra_function(
            'notification_extra',
            __DIR__ . '/../extras/notification/index.php',
            'notify_audit_comment',
            [$test_audit_id, $comment]
        );

        set_alert(true, "good",  "Your comment has been successfully added to the audit.");
    }
    else {
        set_alert(true, "bad", "You do not have permission to add comments to audits.");
    }
}

/**********************************
 * FUNCTION: GET COMPLIANCE FILES *
 **********************************/
function get_compliance_files($ref_id, $ref_type){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.id, t1.ref_id, t1.ref_type, t1.name, t1.unique_name, t1.type, t1.
size, t1.timestamp, t1.user, t1.version FROM `compliance_files` t1 WHERE t1.`ref_id`=:ref_id and t1.`ref_type`=:ref_type;");
    $stmt->bindParam(":ref_id", $ref_id, PDO::PARAM_INT);
    $stmt->bindParam(":ref_type", $ref_type, PDO::PARAM_STR, 20);
    $stmt->execute();

    $files = $stmt->fetchAll();

    // Close the database connection
    db_close($db);
    
    return $files;
}

/*******************************************
 * FUNCTION: DISPLAY TESTING IN COMPLIANCE *
 *******************************************/
function display_testing() {

    global $lang, $escaper;
    
    $test_audit_id = (int)$_GET['id'];
    
    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
    if (!$test_audit['id']) {
        echo "
            <div class='card-body border my-2'>
                <strong>{$escaper->escapeHtml($lang['TestAuditDoesNotExist'])}</strong>
            </div>
        ";
        return;
    }

    // If test date is not set, set today as default
    $test_audit['test_date'] = format_date($test_audit['test_date'], date(get_default_date_format()));

    if (isset($_SESSION["modify_audits"]) && $_SESSION["modify_audits"] == 1) {
        $submit_button = "
            <button name='submit_test_result' id='submit_test_result' type='button' class='btn btn-submit'>{$escaper->escapeHtml($lang['Submit'])}</button>
        ";
    } else {
        $submit_button = "";
    }

    // Phase 3b Task 6: approval-workflow banners + approver controls.
    // Display-only gates -- approveAuditById()/rejectAuditById() (Task 5)
    // re-enforce every one of these checks server-side, so this section only
    // controls what's *shown*, never what's *allowed*.
    $uid = (int)($_SESSION['uid'] ?? 0);
    $is_awaiting_approval = audit_is_awaiting_approval($test_audit_id);
    $is_tester = ($uid > 0 && $uid === (int)$test_audit['tester']);
    $show_approval_actions = $is_awaiting_approval
        && !$is_tester
        && isset($_SESSION['approve_tests']) && $_SESSION['approve_tests'] == 1
        && user_is_approver_of_audit($test_audit_id, $uid);

    $approval_banner_html = "";
    $approval_actions_html = "";

    if ($show_approval_actions) {

        $reject_panel_id = "audit-reject-panel-{$test_audit_id}";

        $approval_actions_html = "
            <div class='audit-approval-actions d-inline-block'>
                <button type='button' class='btn btn-submit audit-approve-btn' data-id='{$test_audit_id}'>{$escaper->escapeHtml($lang['Approve'])}</button>
                <button type='button' class='btn btn-danger' data-bs-toggle='collapse' data-bs-target='#{$reject_panel_id}'>{$escaper->escapeHtml($lang['Reject'])}</button>
                <div class='collapse mt-2' id='{$reject_panel_id}'>
                    <div class='form-group text-start'>
                        <label>{$escaper->escapeHtml($lang['RejectReason'])} :</label>
                        <textarea class='form-control audit-reject-comment' id='audit-reject-comment-{$test_audit_id}' rows='3'></textarea>
                    </div>
                    <div class='text-end mt-2'>
                        <button type='button' class='btn btn-danger audit-reject-btn' data-id='{$test_audit_id}'>{$escaper->escapeHtml($lang['Reject'])}</button>
                    </div>
                </div>
            </div>
        ";

    } elseif ($is_awaiting_approval && $is_tester) {

        $approval_banner_html = "
            <div class='alert alert-info audit-awaiting-approval-banner' role='alert'>
                <strong>{$escaper->escapeHtml($lang['AwaitingApproval'])}:</strong> {$escaper->escapeHtml($lang['AwaitingApprovalBannerText'])}
            </div>
        ";

    } elseif (get_audit_approval_state($test_audit_id) === 'rejected') {

        // Free-text user input -- escape exactly once, here, immediately
        // before it reaches HTML output. Never pre-escaped upstream.
        $rejection_comment = $escaper->escapeHtml(get_latest_audit_rejection_comment($test_audit_id));

        $approval_banner_html = "
            <div class='alert alert-warning audit-rejected-banner' role='alert'>
                <strong>{$escaper->escapeHtml($lang['ReturnedForRework'])}:</strong> {$escaper->escapeHtml($lang['ReturnedForReworkBannerText'])}
                <p class='mb-0 mt-1'>{$rejection_comment}</p>
            </div>
        ";
    }

    $risk_ids = get_test_result_to_risk_ids($test_audit["result_id"]);
    $close_risks = isset($_SESSION["close_risks"]) ? $_SESSION["close_risks"] : 0;

    $tags_view = "";
    if ($test_audit['tags']) {
        foreach (explode(",", $test_audit['tags']) as $tag) {
            $tags_view .= "
                <button class='btn btn-secondary btn-sm' style='pointer-events: none;margin-right:2px;padding: 4px 12px;' role='button' aria-disabled='true'>{$escaper->escapeHtml($tag)}</button>
            ";
        }
    } else {
        $tags_view .= "--";
    }
  
    // $approval_banner_html is pre-built above, out of pieces that are each
    // escaped individually at construction time (or, for the rejection
    // comment, explicitly escaped exactly once) -- safe to echo as-is here.
    echo $approval_banner_html;

    // @phan-suppress-next-line SecurityCheck-XSS -- $close_risks is from $_SESSION (server-side, not user-controlled); all other values in this echo are escaped
    echo "
        <div class='card-body border my-2'>
            <form id='edit-test' class='' method='POST' enctype='multipart/form-data'>
                <input type='hidden' name='origin_test_results' id='origin_test_results' value='{$escaper->escapeHtml($test_audit['test_result'])}' data-permission='{$close_risks}'>
                <input type='hidden' name='remove_associated_risk' id='remove_associated_risk' value='0'>
                <input type='hidden' name='associate_new_risk_id' id='associate_new_risk_id' value=''>
                <input type='hidden' name='associate_exist_risk_ids' id='associate_exist_risk_ids' value='" . implode(",", array_map('intval', $risk_ids)) . "'>
                <h4>{$escaper->escapeHtml($test_audit['name'])}</h4>
                <div class='row'>
                    <div class='col-6'>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['AuditStatus'])} :</label>
    ";
                            create_dropdown("test_status", $test_audit['status'], "status", true, false, false, "", "--");
    echo "
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['TestResult'])} :</label>
    ";
                            create_dropdown("test_results", $test_audit['test_result'], "test_result", true, false, false, "", "--");
    echo "
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['Tester'])} :</label>
    ";
                            create_dropdown("enabled_users", $test_audit['tester'], "tester", true, false, false);
    echo "
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['TestDate'])} :</label>
                            <input name='test_date' value='{$escaper->escapeHtml($test_audit['test_date'])}' required class='datepicker form-control' type='text'>
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['Teams'])} :</label>
                            <div class='w-100'>
    ";
                                create_multiple_dropdown("team", $test_audit['teams']);
    echo "
                            </div>
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['Objective'])} :</label>
                            {$escaper->purifyHtml($test_audit['objective'] ? $test_audit['objective'] : "--")}
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['TestSteps'])} :</label>
                            {$escaper->purifyHtml($test_audit['test_steps'] ? $test_audit['test_steps'] : "--")}
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['Tags'])} :</label>
                            <select class='test_audit_tags form-select' readonly id='tags' name='tags[]' multiple placeholder={$escaper->escapeHtml($lang['TagsWidgetPlaceholder'])}>
    ";
    if ($test_audit['tags']) {
        foreach (explode(",", $test_audit['tags']) as $tag) {
            $tag = $escaper->escapeHtml($tag);
            echo "
                                <option selected value='{$tag}'>{$tag}</option>
            ";
        }
    }
    echo "
                            </select>
                        </div>
                    </div>
                    <div class='col-6'>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['Summary'])} :</label>
                            <textarea name='summary' class='form-control' style='width:100%'>{$escaper->escapeHtml($test_audit['summary'])}</textarea>
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['Attachment'])} :</label>
                            <div class='file-uploader'>
                                <label for='audit-file-upload' class='btn btn-primary'>{$escaper->escapeHtml($lang['ChooseFile'])}</label>
                                <span class='file-count-html'> <span class='file-count'>" . count(get_compliance_files($test_audit_id, "test_audit")) . "</span> {$escaper->escapeHtml($lang['FileAdded'])}</span>
                                <p><font size='2'><strong>Max " . round(get_setting('max_upload_size')/1024/1024) . " Mb</strong></font></p>
                                <ul class='exist-files'>
    ";
                                    display_compliance_files($test_audit_id, "test_audit");
    echo "
                                </ul>
                                <ul class='file-list'></ul>
                                <input type='file' id='audit-file-upload' name='file[]' class='d-none hidden-file-upload active' />
                            </div>
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['AdditionalStakeholders'])} :</label>
                            {$escaper->escapeHtml($test_audit['additional_stakeholders'] ? get_stakeholder_names($test_audit['additional_stakeholders']) : "--")}
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['ControlOwner'])} :</label>
                            {$escaper->escapeHtml($test_audit['control_owner'] ? get_name_by_value("user", $test_audit['control_owner']) : "--")}
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['ExpectedResults'])} :</label>
                            {$escaper->purifyHtml($test_audit['expected_results'] ? $test_audit['expected_results'] : "--")}
                        </div>
                        <div class='form-group'>
                            <label>{$escaper->escapeHtml($lang['ApproximateTime'])} :</label>" . 
                            (int)$test_audit['approximate_time'] . " {$escaper->escapeHtml($test_audit['approximate_time'] > 1 ? $lang['minutes'] : $lang['minute'])}
                        </div>
                    </div>
                </div>
                <div class='text-end'>
                    {$submit_button}
                    {$approval_actions_html}
                </div>
            </form>
        </div>
        <div class='accordion my-2'>
    ";
            // Display the Control Details
            display_test_audit_framework_control(!empty($test_audit['controls']) ? $test_audit['controls'] : [$test_audit['framework_control_id']]);

            // Only display the risks section if the user has the required permission
            if (check_permission("riskmanagement")) {
                // Display associated risks
                display_associated_risks($risk_ids);
            }

            // Display test audit comment
            display_test_audit_comment($test_audit_id);
            
            // Display test audit trail
            display_test_audit_trail($test_audit_id);
    echo "
        </div>
    ";
}

/**************************************
 * FUNCTION: DISPLAY COMPLIANCE FILES *
 **************************************/
function display_compliance_files($ref_id, $ref_type){
    global $lang, $escaper;
    
    $files = get_compliance_files($ref_id, $ref_type);
    
    $html = "";
    
    foreach($files as $file){
        $html .= "
            <li>            
                <div class=\"file-name\"><a href=\"".build_url("compliance/download.php?id=".$escaper->escapeHtml($file['unique_name']))."\">".$escaper->escapeHtml($file['name'])."</a></div>
                <a href=\"#\" class=\"remove-file\" data-id=\"file-upload-0\"><i class=\"fa fa-times\"></i></a>
                <input name=\"unique_names[]\" value=\"". $escaper->escapeHtml($file['unique_name']) ."\" type=\"hidden\">
            </li>            
        ";
    }
    
    // @phan-suppress-next-line SecurityCheck-XSS -- build_url() called with hardcoded path; unique_name is pre-escaped
    echo $html;

    return count($files);
}

/**************************************
 * FUNCTION: DISPLAY TEST AUDIT TRAIL *
 **************************************/
function display_test_audit_trail($test_audit_id) {

    global $escaper, $lang;
    
    echo "
        <div class='accordion-item audit-trail--wrapper'>
            <h2 class='accordion-header comments-accordion-header'>
                <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#audit-trail-accordion-body'>{$escaper->escapeHtml($lang['AuditTrail'])}</button>
            </h2>
            <div id='audit-trail-accordion-body' class='accordion-collapse collapse'>
                <div class='accordion-body card-body'>
                    <div class='audit-trail'>
    ";
                        get_audit_trail_html($test_audit_id+1000, 36500, 'test_audit');
    echo "
                    </div>
                </div>
            </div>
        </div>
    ";
}

function display_test_audit_framework_control($controls) {

    if (!is_array($controls)) {
        $controls = $controls ? [$controls] : [];
    }

    foreach ($controls as $control_id) {
        display_single_framework_control((int)$control_id);
    }
}

/**
 * Render a single framework control's accordion block for the audit detail page.
 * The accordion DOM id/target are suffixed with the control id so multiple
 * blocks (one per snapshot control) don't collide their collapse toggles.
 */
function display_single_framework_control($framework_control_id) {

    if ($framework_control_id) {

        global $escaper, $lang;

        $control = get_framework_controls($framework_control_id);

        if (count($control)) {

            $control = $control[0];

            $accordion_body_id = "framework-control-accordion-body-" . (int)$framework_control_id;

            echo "
                <div class='accordion-item framework-control-wrapper'>
                    <h2 class='accordion-header'>
                        <button type='button' class='accordion-button' data-bs-toggle='collapse' data-bs-target='#{$accordion_body_id}'>{$escaper->escapeHtml($lang['ControlDetails'])}</button>
                    </h2>
                    <div id='{$accordion_body_id}' class='accordion-collapse collapse show'>
                        <div class='accordion-body card-body'>
                            <div class='row'>
                                <div class='col-12'>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['ControlLongName'])} :</label>
                                        {$escaper->escapeHtml($control['long_name'])}
                                    </div>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['ControlShortName'])} :</label>
                                        {$escaper->escapeHtml($control['short_name'])}
                                    </div>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['ControlNumber'])} :</label>
                                        {$escaper->escapeHtml($control['control_number'])}
                                    </div>
                                </div>
                            </div>
                            <div class='row'>
                                <div class='col-6'>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['ControlOwner'])} :</label>
                                        {$escaper->escapeHtml($control['control_owner_name'])}
                                    </div>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['ControlClass'])} :</label>
                                        {$escaper->escapeHtml($control['control_class_name'])}
                                    </div>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['ControlPhase'])} :</label>
                                        {$escaper->escapeHtml($control['control_phase_name'])}
                                    </div>
                                </div>
                                <div class='col-6'>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['ControlPriority'])} :</label>
                                        {$escaper->escapeHtml($control['control_priority_name'])}
                                    </div>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['ControlFamily'])} :</label>
                                        {$escaper->escapeHtml($control['family_short_name'])}
                                    </div>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['MitigationPercent'])} :</label>
                                        {$escaper->escapeHtml($control['mitigation_percent'])}
                                    </div>
                                </div>
                            </div>
                            <div class='row'>
                                <div class='col-12'>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['Description'])} :</label>
                                        {$escaper->purifyHtml($control['description'])}
                                    </div>
                                    <div class='form-group'>
                                        <label>{$escaper->escapeHtml($lang['SupplementalGuidance'])} :</label>
                                        {$escaper->purifyHtml($control['supplemental_guidance'])}
                                    </div>
                                </div>
                            </div>
            ";

            $mapped_frameworks = get_mapping_control_frameworks($control['id']);

            echo "
                            <div>
                                <label>{$escaper->escapeHtml($lang['MappedControlFrameworks'])} :</label>
                                <div class='bg-light p-3 border'>
                                    <table width='100%' class='table table-bordered mb-0'>
                                        <tr>
                                            <th width='50%'>{$escaper->escapeHtml($lang['Framework'])}</th>
                                            <th width='35%'>{$escaper->escapeHtml($lang['Control'])}</th>
                                        </tr>
            ";
            foreach ($mapped_frameworks as $framework) {
                echo "
                                        <tr>
                                            <td>{$escaper->escapeHtml($framework['framework_name'])}</td>
                                            <td>{$escaper->escapeHtml($framework['reference_name'])}</td>
                                        </tr>
                ";
            }
            echo "
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ";
        }
    }
}

/**************************************
 * FUNCTION: DISPLAY ASSOCIATED RISKS *
 **************************************/
function display_associated_risks($risk_ids) {

    global $escaper, $lang;

    $submit_risks = check_permission("submit_risks");
    $modify_risks = check_permission("modify_risks");

    echo "
        <div class='accordion-item risks--wrapper'>
            <h2 class='accordion-header'>
                <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#risks-accordion-body'>{$escaper->escapeHtml($lang['Risks'])}</button>
            </h2>
            <div id='risks-accordion-body' class='accordion-collapse collapse'>
                <div class='accordion-body card-body'>
                    " . (($submit_risks || $modify_risks) ? "<div class='form-group text-end'>
                        " . ($submit_risks ? "<button class='btn btn-submit associate_new_risk'>{$escaper->escapeHtml($lang['NewRisk'])}</button>" : "") . "
                        " . ($modify_risks ? "<button class='btn btn-primary associate_existing_risk'>{$escaper->escapeHtml($lang['ExistingRisk'])}</button>" : "") . "
                    </div>" : "") . "
                    <div class='bg-light border p-3'>
                        <table width='100%' class='table table-bordered mb-0 mapping_framework_table'>
                            <thead>
                                <tr>
                                    <th width='5%'>{$escaper->escapeHtml($lang['ID'])}</th>
                                    <th width='90%'>{$escaper->escapeHtml($lang['Subject'])}</th>
                                    " . ($modify_risks ? "<th>{$escaper->escapeHtml($lang["Actions"])}</th>" : "") . "
                                </tr>
                            </thead>
                            <tbody>
    ";

    foreach ($risk_ids as $key => $risk_id) {

        $risk = get_risk_by_id($risk_id + 1000);
        $no = $key + 1;
        $subject = try_decrypt($risk[0]['subject']);

        echo "
                                <tr>
                                    <td style='text-align:center'>
                                        <a class='open-in-new-tab' target='_blank' href='../management/view.php?id=" . ($risk_id + 1000) . "'>" . ($risk_id + 1000) . "</a>
                                    </td>
                                    <td>{$escaper->escapeHtml($subject)}</td>
                                    " . ($modify_risks ? "<td style='text-align:center'>
                                        <a href='javascript:void(0);' class='delete-risk' data-risk-id='{$risk_id}' data-risk-id='{$risk_id}' title='{$escaper->escapeHtml($lang["Delete"])}'><i class='fa fa-trash'></i></a>
                                    </td>" : "") . "
                                </tr>
        ";
    }

    echo "
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    ";
}

/****************************************
 * FUNCTION: DISPLAY TEST AUDIT COMMENT *
 ****************************************/
function display_test_audit_comment($test_audit_id) {

    global $escaper, $lang;
    
    $test_audit_id = (int)$test_audit_id;
    
    echo "
        <div class='accordion-item comments--wrapper'>
            <h2 class='accordion-header comments-accordion-header'>
                <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#comments-accordion-body'>{$escaper->escapeHtml($lang['Comments'])}</button>
            </h2>
            <div id='comments-accordion-body' class='accordion-collapse collapse'>
                <div class='accordion-body card-body'>
                    <form id='comment' class='comment-form' name='add_comment' method='post'>
                        <input type='hidden' name='id' value='{$test_audit_id}'>
                        <textarea name='comment' cols='50' rows='3' id='comment-text' class='form-control comment-text'></textarea>
                        <div class='form-actions mt-2 text-end' id='comment-div'>
                            <input class='btn btn-secondary' id='rest-btn' value='{$escaper->escapeHtml($lang['Reset'])}' type='reset' />
                            <button id='comment-submit' type='submit' name='submit' class='comment-submit btn btn-submit' >{$escaper->escapeHtml($lang['Submit'])}</button>
                        </div>
                    </form>
                    <div class='comments--list'>" . 
                        get_testing_comment_list($test_audit_id) . "
                    </div>
                </div>
            </div>
        </div>
       
        <script>            
            $('body').on('click', '.comment-submit', function(e) {
                e.preventDefault();
                var container = $('.comments--wrapper');
                
                if (!$('.comment-text', container).val()) {
                    $('.comment-text', container).focus();
                    return;
                }
                
                var getForm = $(this).parents('form', container);
                var form = new FormData($(getForm)[0]);

                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/v2/compliance/save_audit_comment',
                    data: form,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        $('.comments--list', container).html(data.data);
                        $('.comment-text', container).val('');
                        $('.comment-text', container).focus();
                        showAlertsFromArray(data.status_message);
                    },
                    error: function(xhr,status,error) {
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                        if (!retryCSRF(xhr, this)) {
                        }
                    }
                });
            });
        </script>
    ";
      
    return true;
}

/******************************************
 * FUNCTION: DISPLAY TESTING COMMENT LIST *
 ******************************************/
function get_testing_comment_list($test_audit_id) {

    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the comments
    $stmt = $db->prepare("
        SELECT 
            a.date, a.comment, b.name 
        FROM 
            framework_control_test_comments a 
            LEFT JOIN user b ON a.user = b.value 
        WHERE 
            a.test_audit_id=:test_audit_id 
        ORDER BY 
            a.date DESC
    ");

    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);

    $stmt->execute();

    // Store the list in the array
    $comments = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    $returnHTML = "";
    foreach ($comments as $comment) {

//        $text = try_decrypt($comment['comment']);
        $text = $comment['comment'];
        $date = date(get_default_datetime_format("g:i A T"), strtotime($comment['date']));
        $user = $comment['name'];
        
        if ($text != null) {
            $returnHTML .= "
                <p class='comment-block'>
                    <strong>{$escaper->escapeHtml($date)} by {$escaper->escapeHtml($user)}</strong><br />{$escaper->escapeHtml(try_decrypt($text))}
                </p>
            ";
        }
    }

    return $returnHTML;
    
}

/********************************************************************
 * FUNCTION: IS VALID TEST RESULT NAME                                *
 *                                                                    *
 * Validates that $name is either the empty string ("not yet set"     *
 * sentinel) or a row in the `test_results` lookup table. The table   *
 * is seeded once at install with Pass/Inconclusive/Fail and has no   *
 * admin UI to extend it, but driving validation off the live table   *
 * keeps this in lock-step with whatever rows actually exist.         *
 ********************************************************************/
function is_valid_test_result_name($name) {
    $name = (string)$name;
    if ($name === '') return true;
    return (bool)get_value_by_name('test_results', $name);
}

/********************************
 * FUNCTION: INSERT TEST RESULT *
 ********************************/
function save_test_result($test_audit_id, $status, $test_result, $tester, $test_date, $teams, $summary, $tags=[]) {

    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);

    $submitted_by = $_SESSION['uid'];

    // Open the database connection
    $db = db_open();

    $submission_date = date("Y-m-d H:i:s");

    // Check submitted result is existing
    if(!$test_audit['result_id']) {
        $sql = "INSERT INTO framework_control_test_results(`test_audit_id`, `test_result`, `summary`, `test_date`, `submitted_by`, `submission_date`) VALUES(:test_audit_id, :test_result, :summary, :test_date, :submitted_by, :submission_date);";
    } else {
        $sql = "UPDATE framework_control_test_results SET `test_result`=:test_result, `summary`=:summary, `test_date`=:test_date, `submitted_by`=:submitted_by, `submission_date`=:submission_date WHERE `test_audit_id`=:test_audit_id;";
    }

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":test_result", $test_result, PDO::PARAM_STR);
    $stmt->bindParam(":test_date", $test_date, PDO::PARAM_STR);
    $stmt->bindParam(":summary", $summary, PDO::PARAM_STR);
    $stmt->bindParam(":submitted_by", $submitted_by, PDO::PARAM_STR);
    $stmt->bindParam(":submission_date", $submission_date, PDO::PARAM_STR);
    $stmt->execute();

    // Update tester in test audit table
    $stmt = $db->prepare("UPDATE framework_control_test_audits SET `tester`=:tester WHERE `id`=:test_audit_id;");
    $stmt->bindParam(":tester", $tester, PDO::PARAM_INT);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Update teams of the active audit
    updateTeamsOfItem($test_audit_id, 'audit', $teams, false);

    // Update status in test_audit table
    update_test_audit_status($test_audit_id, $status);

    // Update tags of the active audit
    updateTagsOfType($test_audit_id, 'test_audit', $tags);

    $closed_audit_status = get_setting("closed_audit_status");

    // Check audit was closed
    if($status == $closed_audit_status) {

        // Held awaiting approval: the parent test has >=1 configured approver
        // (Phase 3a), so initiate_test_audit() stamped this audit 'pending'.
        // The tester just submitted a closing result, but the close doesn't
        // stick until an approver signs off -- do NOT advance last/next dates
        // here and do NOT write the "closed" log. update_test_audit_status()
        // above already set the audit's `status` column to closed; the
        // truly-closed predicate (Task 4) checks approval_state so a pending
        // audit still shows as awaiting approval rather than truly closed.
        // approve_audit() (Task 2) runs update_last_and_next_auditdate()
        // exactly once, later, when an approver actually approves.
        //
        // Hold for BOTH 'pending' and 'rejected': a 'rejected' audit is one an
        // approver returned for rework; when the tester reworks and resubmits a
        // closing result it must RE-ENTER the approval queue, not close. Flip it
        // back to 'pending' here so the reject -> rework -> re-approval loop
        // actually loops -- otherwise a rejected audit's resubmit would fall
        // through to the close branch and close WITHOUT re-approval (a bypass).
        $approval_state = get_audit_approval_state($test_audit_id);
        if ($approval_state === 'pending' || $approval_state === 'rejected') {
            if ($approval_state === 'rejected') {
                $db = db_open();
                $stmt = $db->prepare("UPDATE `framework_control_test_audits` SET `approval_state`='pending' WHERE `id`=:audit_id;");
                $stmt->bindParam(":audit_id", $test_audit_id, PDO::PARAM_INT);
                $stmt->execute();
                db_close($db);
            }

            $test_audit_after = get_framework_control_test_audit_by_id($test_audit_id);
            $changes = get_changes('audit', $test_audit, $test_audit_after);

            $message = _lang_raw('AuditLog_TestAuditAwaitingApproval', ['test_audit_name' => $test_audit["name"], 'test_audit_id' => $test_audit_id, 'user_name' => $_SESSION['user'], 'changes' => $changes]);
            write_log((int)$test_audit_id + 1000, $_SESSION['uid'] ?? 0, $message, "test_audit");

            // Notify every configured approver that a decision is waiting on them.
            notify_audit_awaiting_approval($test_audit_id);
        } else {
            // update last audit date and next audit date in test_audit table
            update_last_and_next_auditdate($test_audit_id, $test_date);

            $test_audit_after = get_framework_control_test_audit_by_id($test_audit_id);
            $changes = get_changes('audit', $test_audit, $test_audit_after);

            $message = _lang_raw('AuditLog_TestAuditClosed', ['test_audit_name' => $test_audit["name"], 'test_audit_id' => $test_audit_id, 'user_name' => $_SESSION['user'], 'changes' => $changes]);
            write_log((int)$test_audit_id + 1000, $_SESSION['uid'] ?? 0, $message, "test_audit");
        }
    } else {
        $test_audit_after = get_framework_control_test_audit_by_id($test_audit_id);
        $changes = get_changes('audit', $test_audit, $test_audit_after);

        $message = _lang_raw('AuditLog_TestAuditUpdated', ['test_audit_name' => $test_audit["name"], 'test_audit_id' => $test_audit_id, 'user_name' => $_SESSION['user'], 'changes' => $changes]);
        write_log((int)$test_audit_id + 1000, $_SESSION['uid'] ?? 0, $message, "test_audit");
    }

    trigger_workflow_event('audit.updated', [
        'audit_id' => $test_audit_id,
        'test_id'  => $test_audit['test_id'],
        'tester'   => $tester,
    ]);

    return true;
}

/**************************************
 * FUNCTION: UPDATE TEST AUDIT STATUS *
 **************************************/
function update_test_audit_status($test_audit_id, $status=0) {

    $old_status = get_test_audit_status($test_audit_id);

    // Open the database connection
    $db = db_open();

    // Update test audit status
    $stmt = $db->prepare("UPDATE `framework_control_test_audits` SET `status` = :status WHERE `id` = :test_audit_id;");
    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // If notification is enabled and the status changed
    if (notification_extra() && $old_status != $status)
    {
        // Include the notification extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_audit_status_change($test_audit_id, $old_status, $status);
    }
}

/***********************************************************
 * FUNCTION: UPDATE LAST DATE AND NEXT DATE IN AUDIT TABLE *
 ***********************************************************/
function update_last_and_next_auditdate($test_audit_id, $last_date)
{
    // Get test by ID
    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);

    // Load the parent test's schedule_type + cadence fields to determine how next_date advances.
    $test = get_framework_control_test_by_id($test_audit['test_id']);
    // Reflect the date being closed now: compute_test_next_date's interval branch bases its
    // calculation on $test['last_date'], and this call is what persists that new last_date.
    $test['last_date'] = $last_date;
    $test['test_frequency'] = $test_audit['test_frequency'];

    $next_date = compute_test_next_date($test, date("Y-m-d", strtotime($last_date . ' +1 day')));

    // Open the database connection
    $db = db_open();
    
    // NOTE: the audit row's own `next_date` is intentionally NOT updated here. It was
    // snapshotted at initiation (initiate_test_audit()) as the window this audit covers,
    // consistent with the other snapshotted audit fields (name, objective, tester,
    // test_frequency, desired_frequency) that are never re-synced from the parent test.
    // It doubles as the dedupe key in get_tests_to_auto_initiate() (ta.next_date =
    // t1.next_date); rolling it forward here made it collide with the parent test's newly
    // advanced next_date and permanently excluded the test from future auto-initiation.
    // Only `last_date` (when the audit was actually closed) is updated on the audit row.
    $sql = "UPDATE `framework_control_test_audits` SET `last_date`=:last_date WHERE id=:test_audit_id;";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":last_date", $last_date, PDO::PARAM_STR);

    // Update test status
    $stmt->execute();

    $sql = "UPDATE `framework_control_tests` t1 JOIN `framework_control_test_audits` t2 ON t1.id=t2.test_id SET t1.`last_date`=:last_date, t1.`next_date`=:next_date WHERE t2.id=:test_audit_id;";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":last_date", $last_date, PDO::PARAM_STR);
    $stmt->bindParam(":next_date", $next_date, PDO::PARAM_STR);
    
    // Update test status
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: DELETE COMPLIANCE FILE *
 ************************************/
function delete_compliance_file($file_id){
    // Open the database connection
    $db = db_open();

    // Get the file from the database
    $stmt = $db->prepare("SELECT * FROM compliance_files WHERE id=:file_id; ");
    $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
    $stmt->execute();
    // Store the results in an array
    $file = $stmt->fetch();
    // If the array is empty
    if (!empty($file)) {
        // Audit log entry for deleting a file
        $message = "File \"" . $file['name'] . "\" was deleted by username \"" . $_SESSION['user'] . "\".";
        $log_type = $file['ref_type'];
        $ref_type = null;
        if ($ref_type == 'documents') {
            $log_type = 'document';
        } else if ($ref_type == 'exceptions') {
            $log_type = 'exception';
        }
        write_log($file['ref_id'] + 1000, $_SESSION['uid'] ?? 0, $message, $log_type);
    }

    // Delete a compliance file by file ID
    $stmt = $db->prepare("DELETE FROM `compliance_files` WHERE id=:file_id; ");
    $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/********************************
 * FUNCTION: SUBMIT TEST RESULT *
 ********************************/
function submit_test_result()
{
    global $escaper, $lang;

    $test_audit_id  = (int)$_GET['id'];
    $test_audit_status  = (int)$_POST['status'];
    $test_result    = isset($_POST['test_result']) ? (string)$_POST['test_result'] : '';
    $tester         = (int)$_POST['tester'];
    $test_date      = $_POST['test_date'];
    $teams          = isset($_POST['team']) ? $_POST['team'] : [];
    $summary        = $_POST['summary'];
    $tags           = isset($_POST['tags']) ? $_POST['tags'] : [];

    if(!$test_audit_id || !$tester || !$test_date)
    {
        set_alert(true, "bad", $lang['InvalidParams']);
        return false;
    }

    // Reject test_result values that aren't in the test_results lookup
    // table — guards against arbitrary strings (e.g. HTML/JS payloads)
    // being persisted and rendered back into compliance views.
    if (!is_valid_test_result_name($test_result))
    {
        set_alert(true, "bad", $lang['InvalidParams']);
        return false;
    }
    else
    {
        // Convert test_date to standard format 
        $test_date = get_standard_date_from_default_format($test_date);
        
        // Check if user already attached files 
        $unique_names = isset($_POST['unique_names']) ? $_POST['unique_names'] : [];
        
        // Get existing files
        $files = get_compliance_files($test_audit_id, "test_audit");
        
        // Delete files
        foreach($files as $file){
            // Check if file is deleted
            if(!in_array($file['unique_name'], $unique_names)){
                delete_compliance_file($file['id']);
            }
        }
    
        // If submitted files are existing, save files
        if(!empty($_FILES['file'])){
            $files = $_FILES['file'];
            list($status, $file_ids, $errors) = upload_compliance_files($test_audit_id, "test_audit", $files);
        }
        
        // Check if error was happen in uploading files
        if(!empty($errors)){
            $errors = array_unique($errors);
            set_alert(true, "bad", implode(", ", $errors));
            return false;
        }else{
            // Save a test result
            save_test_result($test_audit_id, $test_audit_status, $test_result, $tester, $test_date, $teams, $summary, $tags);
            $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
            $result_id = $test_audit["result_id"];


            // remove_associated_risk is a client-supplied flag, and the
            // only gate on this submission is modify_audits. The close_risks
            // permission is checked here as well as at the sink so a caller who
            // lacks it falls through to the normal association-update path
            // instead of silently no-op'ing the risk removal and dropping the
            // associations. (!empty() rather than a bare index: the field is
            // absent on submissions from pages that never render it.)
            if(!empty($_POST['remove_associated_risk']) && !close_risks_by_test_result_denied('check_permission')) {
                close_risks_by_test_result_id($result_id, $test_result);
                delete_test_result_to_risk_by_result_id($result_id);
            } else {
                // add existing risks
                $associate_exist_risk_ids = isset($_POST['associate_exist_risk_ids']) ? $_POST['associate_exist_risk_ids'] : "";
                delete_test_result_to_risk_by_result_id($result_id);
                if($associate_exist_risk_ids) {
                    $risk_ids = explode(",", $associate_exist_risk_ids);
                    foreach($risk_ids as $risk_id) {
                        save_test_result_to_risk($result_id, $risk_id);
                    }
                }

                // add new risk
                $associate_new_risk_id = isset($_POST['associate_new_risk_id']) ? $_POST['associate_new_risk_id'] : "";
                if($associate_new_risk_id) {
                    $new_risk_id = (int)$associate_new_risk_id - 1000;
                    save_test_result_to_risk($result_id, $new_risk_id);
                }
            }

             
          set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
            return true;
        }
    }
    
}

/****************************************
 * FUNCTION: SUBMIT TEST RESULT TO RISK *
 ****************************************/
function submit_test_result_to_risk()
{
    global $escaper, $lang;

    $test_audit_id  = (int)$_GET['id'];

    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
    $result_id = $test_audit["result_id"];

    // add existing risks
    $associate_exist_risk_ids = isset($_POST['associate_exist_risk_ids']) ? $_POST['associate_exist_risk_ids'] : "";
    delete_test_result_to_risk_by_result_id($result_id);
    if($associate_exist_risk_ids) {
        $risk_ids = explode(",", $associate_exist_risk_ids);
        foreach($risk_ids as $risk_id) {
            save_test_result_to_risk($result_id, $risk_id);
        }
    }

    // add new risk
    $associate_new_risk_id = isset($_POST['associate_new_risk_id']) ? $_POST['associate_new_risk_id'] : "";
    if($associate_new_risk_id) {
        $new_risk_id = (int)$associate_new_risk_id - 1000;
        save_test_result_to_risk($result_id, $new_risk_id);
    }
   
    set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
    return true;
}

/**************************************
 * FUNCTION: DOWNLOAD COMPLIANCE FILE *
 **************************************/
function download_compliance_file($unique_name)
{
    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the file from the database
    $stmt = $db->prepare("SELECT * FROM compliance_files WHERE BINARY unique_name=:unique_name");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->execute();

    // Store the results in an array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // If the array is empty
    if (empty($array))
    {
        // Do nothing
        exit;
    }
    else
    {
        // Enforce authorization at the download sink, which is
        // the only place that knows which module the file actually belongs to.
        // Both entry points (governance/download.php and compliance/download.php)
        // forward here, each gated on its own module only, so the coarse gate is
        // per-page and not per-file. Two checks apply:
        //   - The caller must hold the module permission that OWNS the ref_type
        //     (test_audit → compliance; documents/exceptions → governance).
        //     Without this a governance-only user can pull compliance audit
        //     evidence through governance/download.php by unique_name, and a
        //     compliance-only user can pull governance documents and exception
        //     attachments through compliance/download.php.
        //   - Exception attachments additionally require view_exception, mirroring
        //     the exception display API's check_permission_exception('view'); the
        //     coarse entry-point gate does not enforce it.
        // An unrecognised ref_type is denied rather than streamed, so a future
        // fourth ref_type cannot leak before someone maps its owner. The whole
        // deny decision (ref_type → owning module + granular permission → does
        // the caller hold them) is factored into a pure, unit-tested helper so
        // the mappings are locked against accidental regression; this
        // header()/exit() sink stays a thin wrapper.
        if (compliance_file_download_denied($array['ref_type'], 'check_permission_exception', 'check_permission'))
        {
            // Logs (warning) + sets the alert + redirects + exits.
            redirect_permission_denied('DownloadFilePermissionMessage', "compliance_files ref_type={$array['ref_type']} unique_name={$unique_name}");
        }

        header("Content-length: " . $array['size']);
        header("Content-type: " . $array['type']);
        header("Content-Disposition: attachment; filename=" . $escaper->escapeUrl($array['name']));
        echo $array['content'];
        exit;
    }
}

/*********************************
 * FUNCTION: DISPLAY PAST AUDITS *
 *********************************/
function display_past_audits() {

    global $lang, $escaper;

    echo "
        <div class='card-body border my-2'>
            <div class='row'>
                <div class='col-10'></div>
                <div class='col-2'>
                    <div style='float: right;'>
    ";
                        render_column_selection_widget('past_audits');
    echo "
                    </div>
                </div>
            </div>
            <div class='row'>
                <div class='col-12'>
    ";
                    render_view_table('past_audits');
    echo "
                </div>
            </div>
        </div>

        <script>
            $(function () {
                initializeMultiselect('.header_filter .multiselect', {
                    allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
					includeSelectAllOption: true,
					buttonWidth: '100%',
                    maxHeight: 400,
					enableCaseInsensitiveFiltering: true,
				});

                $('.header_filter [name=test_date].datepicker').initAsDateRangePicker();

                $('body').on('click', '.reopen', function(){
                    var id = $(this).data('id');
                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/v2/compliance/reopen_audit',
                        data:{
                            id: id
                        },
                        success: function(result){
                            if (result.status_message) {
                                showAlertsFromArray(result.status_message);
                            }
                            $('#past_audits_datatable').DataTable().draw();
                        },
                        error: function(xhr,status,error){
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                            if(!retryCSRF(xhr, this))
                            {
                            }
                        }
                    })
                });
            });
        </script>
    ";

}

/************************************
 * FUNCTION: DISPLAY TEST IN DETAIL *
 ************************************/
function display_detail_test() {

    global $lang, $escaper;
    
    $test_audit_id = (int)$_GET['id'];
    
    // Get test audit information
    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
    
    // Get attachement files
    $files = get_compliance_files($test_audit_id, "test_audit");

    // Get associated risk ids
    $risk_ids = get_test_result_to_risk_ids($test_audit["result_id"]);
    $tags_view = "";

    if ($test_audit['tags']) {
        foreach (explode(",", $test_audit['tags']) as $tag) {
            $tags_view .= "
                <button class='btn btn-secondary btn-sm' style='pointer-events: none;margin-right:2px;padding: 4px 12px;' role='button' aria-disabled='true'>{$escaper->escapeHtml($tag)}</button>
            ";
        }
    } else {
        $tags_view .= "--";
    }

    echo "
        <div class='card-body border my-2'>
            <div class='row' id='test_detail_information'>
                <div class='col-6'>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['TestName'])} :</label>
                        {$escaper->escapeHtml($test_audit['name'])}
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['Tester'])} :</label>
                        {$escaper->escapeHtml($test_audit['tester_name'])}
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['TestFrequency'])} :</label>" . 
                        (int)$test_audit['test_frequency'] . " {$escaper->escapeHtml($test_audit['test_frequency'] > 1 ? $lang['days'] : $lang['Day'])}
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['Objective'])} :</label>
                        {$escaper->purifyHtml($test_audit['objective'] ? $test_audit['objective'] : "--")}
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['TestSteps'])} :</label>
                        {$escaper->purifyHtml($test_audit['test_steps'] ? $test_audit['test_steps'] : "--")}
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['ApproximateTime'])} :</label>" . 
                        (int)$test_audit['approximate_time'] . " {$escaper->escapeHtml($test_audit['approximate_time'] > 1 ? $lang['minutes'] : $lang['minute'])}
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['Teams'])} :</label>" . 
                        ($test_audit['teams'] ? $escaper->escapeHtml(get_names_by_multi_values('team', $test_audit['teams'])) : "--") . "
                    </div>
                </div>
                <div class='col-6'>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['ExpectedResults'])} :</label>" . 
                        $escaper->purifyHtml($test_audit['expected_results'] ? $test_audit['expected_results'] : "--") . "
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['FrameworkName'])} :</label>
                        {$escaper->escapeHtml($test_audit['framework_name'])}
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['ControlName'])} :</label>
                        {$escaper->escapeHtml($test_audit['control_name'])}
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['ControlOwner'])} :</label>" . 
                        $escaper->escapeHtml(get_name_by_value("user", $test_audit['control_owner'])) . "
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['CreatedDate'])} :</label>" . 
                        $escaper->escapeHtml(format_date($test_audit['created_at'], "--")) . "
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['AdditionalStakeholders'])} :</label>" . 
                        $escaper->escapeHtml(get_stakeholder_names($test_audit['additional_stakeholders'])) . "
                    </div>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['Tags'])} :</label>
                        {$tags_view}
                    </div>
                </div>
            </div>
            <div class='row' id='test_result_information'>
                <div class='col-6'>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['TestResult'])} :</label>
                        {$escaper->escapeHtml($test_audit['test_result'] ? $test_audit['test_result'] : "--")}
                    </div>
                    <div class='form-group mb-0'>
                        <label>{$escaper->escapeHtml($lang['TestDate'])} :</label>
                        {$escaper->escapeHtml(format_date($test_audit['last_date'], "--"))}
                    </div>
                </div>
                <div class='col-6'>
                    <div class='form-group'>
                        <label>{$escaper->escapeHtml($lang['Summary'])} :</label>
                        {$escaper->escapeHtml($test_audit['summary'] ? $test_audit['summary'] : "--")}
                    </div>
                    <div class='form-group attachment-files-container mb-0'>
                        <label>{$escaper->escapeHtml($lang['AttachmentFiles'])} :</label>
                        <div>
    ";
    if ($files) {
        foreach ($files as $file) {
            
            // Validate the unique_name to prevent directory traversal attacks
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $file['unique_name'])) {
                continue; // skip invalid entries
            }

            // @phan-suppress-next-line SecurityCheck-XSS -- build_url() called with hardcoded path; unique_name is regex-validated and pre-escaped
            echo  "
                        <p>
                            <a href='" . build_url("compliance/download.php?id={$escaper->escapeHtml($file['unique_name'])}") . "' >{$escaper->escapeHtml($file['name'])}</a>
                        </p>
            ";
        }
    } else {
        echo "
                        <p>No files</p>
        ";
    }
    echo "
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <form id='edit-test' method='POST'>
            <input type='hidden' name='update_associated_risks' value='1'/>
            <input type='hidden' name='associate_new_risk_id' id='associate_new_risk_id' value=''>
            <input type='hidden' name='associate_exist_risk_ids' id='associate_exist_risk_ids' value='" . implode(",", $risk_ids) . "'>
        </form>
        <div class='accordion my-2'>
    ";
            // Display the Control Details
            display_test_audit_framework_control(!empty($test_audit['controls']) ? $test_audit['controls'] : [$test_audit['framework_control_id']]);

            // Only display the risks section if the user has the required permission
            if (check_permission("riskmanagement")) {
                // Display associated risks
                display_associated_risks($risk_ids);
            }

            // Display test audit comment
            display_test_audit_comment($test_audit_id);
            
            // Display test audit trail
            display_test_audit_trail($test_audit_id);
    echo "
        </div>
    ";
}

/*******************************
 * FUNCTION: DELETE TEST AUDIT *
 *******************************/
function delete_test_audit($test_audit_id) {

    // Open the database connection
    $db = db_open();

    // Delete test audit
    $stmt = $db->prepare("DELETE FROM `framework_control_test_audits` WHERE `id`=:test_audit_id;");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete test audit
    $stmt = $db->prepare("DELETE FROM `framework_control_test_comments` WHERE `test_audit_id`=:test_audit_id;");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete test audit
    $stmt = $db->prepare("
        DELETE t1,t2 FROM `framework_control_test_results_to_risks` t1 LEFT JOIN `framework_control_test_results` t2 ON t2.id = t1.test_results_id WHERE t2.`test_audit_id`=:test_audit_id");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete test audit's teams
    updateTeamsOfItem($test_audit_id, 'audit', []);

    // Remove tags of test audit
    updateTagsOfType($test_audit_id, 'test_audit', []);

    // Close the database connection
    db_close($db);

    $message = _lang_raw('TestAuditDeleteAuditTrailMessage', array('test_audit_id' => $test_audit_id, 'user' => $_SESSION['user']));
    write_log((int)$test_audit_id + 1000, $_SESSION['uid'] ?? 0, $message, "test_audit");

    return true;
}

/*******************************
 * FUNCTION: REOPEN TEST AUDIT *
 *******************************/
function reopen_test_audit($test_audit_id)
{
    // Set test audit status to undefined
    update_test_audit_status($test_audit_id, 0);

    // Carry-forward fix (Phase 3b Task 5) -- approval-bypass close: without
    // this, reopening an already-'approved' (or 'rejected') audit leaves
    // approval_state at its old value. approve_audit()/reject_audit() only
    // hold a close via save_test_result()'s `get_audit_approval_state()===
    // 'pending'` check (~line 2987) -- an audit sitting at 'approved' fails
    // that check, so the tester's *next* resubmitted close would fall straight
    // into the "truly closed" branch and skip re-approval entirely. Reset to
    // 'pending' when the parent test currently has >=1 configured approver
    // (audit_requires_approval() -- re-derived from the test's live approver
    // roster, not the audit's stale past state) so the next close is correctly
    // re-gated; reset to 'none' otherwise so an audit whose test no longer
    // requires approval doesn't get stuck waiting on a sign-off nobody can give.
    $new_approval_state = audit_requires_approval($test_audit_id) ? 'pending' : 'none';
    $db = db_open();
    $stmt = $db->prepare("UPDATE `framework_control_test_audits` SET `approval_state` = :state WHERE `id` = :id;");
    $stmt->bindParam(":state", $new_approval_state, PDO::PARAM_STR);
    $stmt->bindParam(":id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();
    db_close($db);

    $test_audit_name = get_test_audit_name($test_audit_id);
    $message = _lang_raw('AuditLog_TestAuditReopen', ['test_audit_name' => $test_audit_name, 'test_audit_id' => $test_audit_id, 'user_name' => $_SESSION['user']]);
    write_log((int)$test_audit_id + 1000, $_SESSION['uid'] ?? 0, $message, "test_audit");

    return true;
}

/******************************************************
 * FUNCTION: GET FRAMEWORKS FROM INITIATE AUDITS PAGE *
 ******************************************************/
function get_initiate_frameworks_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control) {

    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t1.*, 
            GROUP_CONCAT(DISTINCT t2.short_name SEPARATOR ',') control_names,
            GROUP_CONCAT(DISTINCT t2.desired_frequency SEPARATOR ',') control_desired_frequencies,
            GROUP_CONCAT(DISTINCT t2.last_audit_date SEPARATOR ',') control_last_audit_dates,
            GROUP_CONCAT(DISTINCT t2.next_audit_date SEPARATOR ',') control_next_audit_dates,
            GROUP_CONCAT(DISTINCT t3.name SEPARATOR ',') test_names,
            GROUP_CONCAT(DISTINCT t3.test_frequency SEPARATOR ',') test_test_frequencies,
            GROUP_CONCAT(DISTINCT t3.last_date SEPARATOR ',') test_last_audit_dates,
            GROUP_CONCAT(DISTINCT t3.next_date SEPARATOR ',') test_next_audit_dates
        FROM `frameworks` t1
            LEFT JOIN `framework_control_mappings` m on t1.value=m.framework
            LEFT JOIN `framework_controls` t2 on m.control_id=t2.id AND t2.deleted=0
            LEFT JOIN `test_control_map` tcm3 on tcm3.framework_control_id=t2.id
            LEFT JOIN `framework_control_tests` t3 on t3.id=tcm3.test_id
        WHERE
            t1.status=1 AND t3.id IS NOT NULL
    ";
    
    $where = [];
    
    if ($filter_by_frequency) {
        $where[] = "(t1.desired_frequency = :filter_by_frequency OR t2.desired_frequency = :filter_by_frequency OR t3.test_frequency = :filter_by_frequency)";
    }

    if ($filter_by_status) {
        
    }
//    if($filter_by_framework){
//        $where[] = "FIND_IN_SET(t1.value, :filter_by_framework)";
//    }else{
//        $where[] = "0";
//    }
    if ($filter_by_control) {
        $where[] = "t2.short_name like :filter_by_control";
    }

    if ($where) {
        $sql .= " AND ". implode(" AND ", $where);
    }
    
    $sql .= " GROUP BY t1.value ";

    $stmt = $db->prepare($sql);
    
    if ($filter_by_frequency) {
        $stmt->bindParam(":filter_by_frequency", $filter_by_frequency, PDO::PARAM_STR);
    }

    if ($filter_by_status) {
        
    }
//    if($filter_by_framework){
//        $framework_ids = implode(",", $filter_by_framework);
//        $stmt->bindParam(":filter_by_framework", $framework_ids, PDO::PARAM_STR);
//    }
    if ($filter_by_control) {
        $filter_by_control = "%{$filter_by_control}%";
        $stmt->bindParam(":filter_by_control", $filter_by_control, PDO::PARAM_STR);
    }

    $stmt->execute();
    // Store the list in the array
    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Close the database connection
    db_close($db);

    $filtered_frameworks = [];

    $all_frameworks = get_frameworks(1);
    foreach ($frameworks as $framework) {
//        $framework['name'] = try_decrypt($framework['name']);
//        if(!$filter_by_text || stripos($framework['name'], $filter_by_text) !== false 
        if (!$filter_by_text 
            || stripos($framework['desired_frequency'] ?? '', $filter_by_text) !== false 
            || stripos($framework['last_audit_date'] ?? '', $filter_by_text) !== false 
            || stripos($framework['next_audit_date'] ?? '', $filter_by_text) !== false 
            
            || stripos($framework['control_names'] ?? '', $filter_by_text) !== false 
            || stripos($framework['control_desired_frequencies'] ?? '', $filter_by_text) !== false 
            || stripos($framework['control_last_audit_dates'] ?? '', $filter_by_text) !== false 
            || stripos($framework['control_next_audit_dates'] ?? '', $filter_by_text) !== false 
            
            || stripos($framework['test_names'] ?? '', $filter_by_text) !== false 
            || stripos($framework['test_test_frequencies'] ?? '', $filter_by_text) !== false 
            || stripos($framework['test_last_audit_dates'] ?? '', $filter_by_text) !== false 
            || stripos($framework['test_next_audit_dates'] ?? '', $filter_by_text) !== false 
        ) {

            $filtered = true;
//            $filtered_frameworks[] = $framework;

        } else {

            $filtered = false;

        }

        $parent_frameworks = array();
        get_parent_frameworks($all_frameworks, $framework['value'], $parent_frameworks);
        foreach ($parent_frameworks as $parent_framework) {

            if ($filtered || stripos($parent_framework['name'] ?? '', $filter_by_text) !== false ) {

                $filtered_frameworks[] = $parent_framework;

            }
        }
    }
    
    $results = array();
    $ids = array();
    // Get unique array
    foreach ($filtered_frameworks as $filtered_framework) {
        if (!in_array($filtered_framework['value'], $ids) && in_array($filtered_framework['value'], $filter_by_framework)) {
            $results[] = $filtered_framework;
            $ids[] = $filtered_framework['value'];
        }
    }
    
    return $results;

}

/****************************************************
 * FUNCTION: GET CONTROLS FROM INITIATE AUDITS PAGE *
 ****************************************************/
function get_initiate_controls_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control, $framework_id=null) {

    global $escaper;
    $current_framework = get_framework($framework_id);

    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t2.*,
            :framework_name framework_name,
            :framework_desired_frequency framework_desired_frequency,
            :framework_last_audit_date framework_last_audit_date,
            :framework_next_audit_date framework_next_audit_date,
            GROUP_CONCAT(DISTINCT t2.short_name SEPARATOR ',') control_names,
            GROUP_CONCAT(DISTINCT t2.desired_frequency SEPARATOR ',') control_desired_frequencies,
            GROUP_CONCAT(DISTINCT t2.last_audit_date SEPARATOR ',') control_last_audit_dates,
            GROUP_CONCAT(DISTINCT t2.next_audit_date SEPARATOR ',') control_next_audit_dates,
            GROUP_CONCAT(DISTINCT t3.name SEPARATOR ',') test_names,
            GROUP_CONCAT(DISTINCT t3.test_frequency SEPARATOR ',') test_test_frequencies,
            GROUP_CONCAT(DISTINCT t3.last_date SEPARATOR ',') test_last_audit_dates,
            GROUP_CONCAT(DISTINCT t3.next_date SEPARATOR ',') test_next_audit_dates
        FROM `frameworks` t1
            INNER JOIN `framework_control_mappings` m on t1.value=m.framework
            INNER JOIN `framework_controls` t2 on m.control_id=t2.id AND t2.deleted=0
            LEFT JOIN `test_control_map` tcm3 on tcm3.framework_control_id=t2.id
            LEFT JOIN `framework_control_tests` t3 on t3.id=tcm3.test_id
        WHERE
            t1.status=1 AND t3.id IS NOT NULL
    ";
    
    $where = [];
    
    if ($filter_by_frequency) {
        $where[] = "(t1.desired_frequency = :filter_by_frequency OR t2.desired_frequency = :filter_by_frequency OR t3.test_frequency = :filter_by_frequency)";
    }

    if ($filter_by_status) {
        
    }

    if ($filter_by_framework) {

        $where[] = "FIND_IN_SET(t1.value, :filter_by_framework)";

    } else {

        $where[] = "0";

    }

    if ($filter_by_control) {

        $where[] = "t2.short_name like :filter_by_control";

    }
    
    if ($framework_id) {

        $child_frameworks = get_all_child_frameworks($framework_id, 1, false);
        
        $selected_framework_ids = array_map(function($row){
            return $row['value'];
        }, $child_frameworks);
        
        array_push($selected_framework_ids, $framework_id);
        $selected_framework_ids = implode(",", $selected_framework_ids);
        
        $where[] = "FIND_IN_SET(t1.value, :selected_framework_ids)";

    }

    if ($where) {

        $sql .= " AND ". implode(" AND ", $where);

    }
    
    $sql .= " GROUP BY t2.id ";

    $stmt = $db->prepare($sql);

    $stmt->bindParam(":framework_name", $current_framework['name'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_desired_frequency", $current_framework['desired_frequency'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_last_audit_date", $current_framework['last_audit_date'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_next_audit_date", $current_framework['next_audit_date'], PDO::PARAM_STR);

    //    if($filter_by_text){
//        $filter_by_text = "%{$filter_by_text}%";
//        $stmt->bindParam(":filter_by_text", $filter_by_text, PDO::PARAM_STR);
//    }

    if ($filter_by_frequency) {

        $stmt->bindParam(":filter_by_frequency", $filter_by_frequency, PDO::PARAM_STR);

    }

    if($filter_by_status){
        
    }

    if ($filter_by_framework) {

        $framework_ids = implode(",", $filter_by_framework);
        $stmt->bindParam(":filter_by_framework", $framework_ids, PDO::PARAM_STR);

    }

    if ($filter_by_control) {

        $filter_by_control = "%{$filter_by_control}%";
        $stmt->bindParam(":filter_by_control", $filter_by_control, PDO::PARAM_STR);

    }

    if ($framework_id) {

        $stmt->bindParam(":selected_framework_ids", $selected_framework_ids, PDO::PARAM_STR);

    }

    $stmt->execute();
    // Store the list in the array
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    $filtered_controls = [];
    foreach ($controls as $control) {
        if (!$filter_by_text || stripos($current_framework['name'] ?? '', $filter_by_text) !== false 
            || stripos($control['framework_desired_frequency'] ?? '', $filter_by_text) !== false 
            || stripos($control['framework_last_audit_date'] ?? '', $filter_by_text) !== false 
            || stripos($control['framework_next_audit_date'] ?? '', $filter_by_text) !== false 
            
            || stripos($control['control_names'] ?? '', $filter_by_text) !== false 
            || stripos($control['control_desired_frequencies'] ?? '', $filter_by_text) !== false 
            || stripos($control['control_last_audit_dates'] ?? '', $filter_by_text) !== false 
            || stripos($control['control_next_audit_dates'] ?? '', $filter_by_text) !== false 
            
            || stripos($control['test_names'] ?? '', $filter_by_text) !== false 
            || stripos($control['test_test_frequencies'] ?? '', $filter_by_text) !== false 
            || stripos($control['test_last_audit_dates'] ?? '', $filter_by_text) !== false 
            || stripos($control['test_next_audit_dates'] ?? '', $filter_by_text) !== false 
        ) {

            $filtered_controls[] = $control;

        }
    }
    
    return $filtered_controls;

}

/*************************************************
 * FUNCTION: GET TESTS FROM INITIATE AUDITS PAGE *
 *************************************************/
function get_initiate_tests_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control, $framework_id, $control_id) {

    $current_framework = get_framework($framework_id);

    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t3.*,
            :framework_name framework_name,
            :framework_desired_frequency framework_desired_frequency,
            :framework_last_audit_date framework_last_audit_date,
            :framework_next_audit_date framework_next_audit_date,
            GROUP_CONCAT(DISTINCT t2.short_name SEPARATOR ',') control_names,
            GROUP_CONCAT(DISTINCT t2.desired_frequency SEPARATOR ',') control_desired_frequencies,
            GROUP_CONCAT(DISTINCT t2.last_audit_date SEPARATOR ',') control_last_audit_dates,
            GROUP_CONCAT(DISTINCT t2.next_audit_date SEPARATOR ',') control_next_audit_dates,
            GROUP_CONCAT(DISTINCT t3.name SEPARATOR ',') test_names,
            GROUP_CONCAT(DISTINCT t3.test_frequency SEPARATOR ',') test_test_frequencies,
            GROUP_CONCAT(DISTINCT t3.last_date SEPARATOR ',') test_last_audit_dates,
            GROUP_CONCAT(DISTINCT t3.next_date SEPARATOR ',') test_next_audit_dates
        FROM `frameworks` t1
            INNER JOIN `framework_control_mappings` m on t1.value=m.framework
            INNER JOIN `framework_controls` t2 on m.control_id=t2.id AND t2.deleted=0
            INNER JOIN `test_control_map` tcm3 on tcm3.framework_control_id=t2.id
            INNER JOIN `framework_control_tests` t3 on t3.id=tcm3.test_id
        WHERE
            t1.status=1 AND t2.id=:control_id
    ";

    $where = [];
    
    if ($filter_by_frequency) {

        $where[] = "(t1.desired_frequency = :filter_by_frequency OR t2.desired_frequency = :filter_by_frequency OR t3.test_frequency = :filter_by_frequency)";

    }

    if ($filter_by_status) {
        
    }

    if ($filter_by_framework) {

        $where[] = "FIND_IN_SET(t1.value, :filter_by_framework)";

    } else {

        $where[] = "0";

    }

    if ($filter_by_control) {

        $where[] = "t2.short_name like :filter_by_control";

    }
    
    if ($where) {

        $sql .= " AND ". implode(" AND ", $where);

    }
    
    $sql .= " GROUP BY t3.id ";

    $stmt = $db->prepare($sql);

    $stmt->bindParam(":framework_name", $current_framework['name'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_desired_frequency", $current_framework['desired_frequency'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_last_audit_date", $current_framework['last_audit_date'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_next_audit_date", $current_framework['next_audit_date'], PDO::PARAM_STR);

    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);

    if ($filter_by_frequency) {

        $stmt->bindParam(":filter_by_frequency", $filter_by_frequency, PDO::PARAM_STR);

    }

    if ($filter_by_status) {
        
    }

    if ($filter_by_framework) {

        $framework_ids = implode(",", $filter_by_framework);
        $stmt->bindParam(":filter_by_framework", $framework_ids, PDO::PARAM_STR);

    }

    if ($filter_by_control) {

        $filter_by_control = "%{$filter_by_control}%";
        $stmt->bindParam(":filter_by_control", $filter_by_control, PDO::PARAM_STR);

    }

    $stmt->execute();
    // Store the list in the array
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    $filtered_tests = [];
    foreach ($tests as $test) {

        if (!$filter_by_text || stripos($current_framework['name'] ?? '', $filter_by_text) !== false 
            || stripos($test['framework_desired_frequency'] ?? '', $filter_by_text) !== false 
            || stripos($test['framework_last_audit_date'] ?? '', $filter_by_text) !== false 
            || stripos($test['framework_next_audit_date'] ?? '', $filter_by_text) !== false 
            
            || stripos($test['control_names'] ?? '', $filter_by_text) !== false 
            || stripos($test['control_desired_frequencies'] ?? '', $filter_by_text) !== false 
            || stripos($test['control_last_audit_dates'] ?? '', $filter_by_text) !== false 
            || stripos($test['control_next_audit_dates'] ?? '', $filter_by_text) !== false 
            
            || stripos($test['test_names'] ?? '', $filter_by_text) !== false 
            || stripos($test['test_test_frequencies'] ?? '', $filter_by_text) !== false 
            || stripos($test['test_last_audit_dates'] ?? '', $filter_by_text) !== false 
            || stripos($test['test_next_audit_dates'] ?? '', $filter_by_text) !== false 
        ) {

            $filtered_tests[] = $test;

        }
    }
    
    return $filtered_tests;

}

/*******************************************************************
 * FUNCTION: GET INITIATE UNASSIGNED CONTROLS BY FILTER            *
 * Returns framework controls that have no framework mapping but    *
 * have at least one test defined, applying optional filters.       *
 *******************************************************************/
function get_initiate_unassigned_controls_by_filter($filter_by_text, $filter_by_frequency, $filter_by_control) {

    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t2.*,
            GROUP_CONCAT(DISTINCT t3.name SEPARATOR ',') test_names,
            GROUP_CONCAT(DISTINCT t3.test_frequency SEPARATOR ',') test_test_frequencies,
            GROUP_CONCAT(DISTINCT t3.last_date SEPARATOR ',') test_last_audit_dates,
            GROUP_CONCAT(DISTINCT t3.next_date SEPARATOR ',') test_next_audit_dates
        FROM `framework_controls` t2
            LEFT JOIN `framework_control_mappings` m ON m.control_id = t2.id
            LEFT JOIN `test_control_map` tcm3 ON tcm3.framework_control_id = t2.id
            LEFT JOIN `framework_control_tests` t3 ON t3.id = tcm3.test_id
        WHERE t2.deleted = 0
            AND m.control_id IS NULL
            AND t3.id IS NOT NULL
    ";

    $where = [];

    if ($filter_by_frequency) {
        $where[] = "(t2.desired_frequency = :filter_by_frequency OR t3.test_frequency = :filter_by_frequency)";
    }

    if ($filter_by_control) {
        $where[] = "t2.short_name LIKE :filter_by_control";
    }

    if ($where) {
        $sql .= " AND " . implode(" AND ", $where);
    }

    $sql .= " GROUP BY t2.id ";

    $stmt = $db->prepare($sql);

    if ($filter_by_frequency) {
        $stmt->bindParam(":filter_by_frequency", $filter_by_frequency, PDO::PARAM_STR);
    }

    if ($filter_by_control) {
        $filter_by_control = "%{$filter_by_control}%";
        $stmt->bindParam(":filter_by_control", $filter_by_control, PDO::PARAM_STR);
    }

    $stmt->execute();
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Apply post-fetch text filter (matches against control and test fields)
    if (!$filter_by_text) {
        return $controls;
    }

    $filtered = [];
    foreach ($controls as $control) {
        if (stripos($control['short_name'] ?? '', $filter_by_text) !== false
            || stripos($control['desired_frequency'] ?? '', $filter_by_text) !== false
            || stripos($control['last_audit_date'] ?? '', $filter_by_text) !== false
            || stripos($control['next_audit_date'] ?? '', $filter_by_text) !== false
            || stripos($control['test_names'] ?? '', $filter_by_text) !== false
            || stripos($control['test_test_frequencies'] ?? '', $filter_by_text) !== false
            || stripos($control['test_last_audit_dates'] ?? '', $filter_by_text) !== false
            || stripos($control['test_next_audit_dates'] ?? '', $filter_by_text) !== false
        ) {
            $filtered[] = $control;
        }
    }

    return $filtered;

}

/*******************************************************************
 * FUNCTION: GET INITIATE UNASSIGNED TESTS BY CONTROL              *
 * Returns all tests for a frameworkless control (one with no       *
 * framework_control_mappings row). The LEFT JOIN + IS NULL guard   *
 * verifies the control is still frameworkless at query time, so a  *
 * crafted ?id=control_0_N request for a framework-assigned control *
 * returns no results rather than leaking them through this path.   *
 *******************************************************************/
function get_initiate_unassigned_tests_by_control($control_id) {

    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t3.*
        FROM `framework_controls` t2
            INNER JOIN `test_control_map` tcm3 ON tcm3.framework_control_id = t2.id
            INNER JOIN `framework_control_tests` t3 ON t3.id = tcm3.test_id
            LEFT JOIN `framework_control_mappings` m ON m.control_id = t2.id
        WHERE t2.id = :control_id
            AND t2.deleted = 0
            AND m.control_id IS NULL
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $tests;

}

/****************************************
 * FUNCTION: GET AUDIT TESTS            *
 * DEFAULT SORT: SOONEST NEXT TEST DATE *
 ****************************************/
function get_audit_tests($order_field=false, $order_dir=false)
{
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t1.id, t1.name, t1.last_date, t1.next_date, IFNULL(GROUP_CONCAT(DISTINCT t3.name), '') framework_names,
        (SELECT tr.test_result
        FROM `framework_control_test_audits` ta
        LEFT JOIN `framework_control_test_results` tr ON ta.id=tr.test_audit_id 
        WHERE  ta.test_id = t1.id 
        ORDER BY tr.last_updated DESC LIMIT 1 ) last_test_result 
        FROM `framework_control_tests` t1
            INNER JOIN `test_control_map` tcm ON tcm.test_id=t1.id
            INNER JOIN `framework_controls` t2 ON tcm.framework_control_id=t2.id
            LEFT JOIN `framework_control_mappings` m ON t2.id=m.control_id
            LEFT JOIN `frameworks` t3 ON m.framework=t3.value
        WHERE t3.status=1
            AND t1.`retired_at` IS NULL
        GROUP BY t1.id
    ";
    
    switch($order_field)
    {
        case "test_name":
            $sql .= " ORDER BY t1.name {$order_dir} ";
        break;
        case "associated_frameworks":
            // If encryption extra is disabled, sort by query
            if(!encryption_extra())
            {
                $sql .= " ORDER BY framework_names {$order_dir} ";
            }
        break;
        case "last_test_date":
            $sql .= " ORDER BY t1.last_date {$order_dir} ";
        break;
        case "next_test_date":
            $sql .= " ORDER BY t1.next_date {$order_dir} ";
        break;
        case "last_test_result":
            $sql .= " ORDER BY last_test_result {$order_dir} ";
        break;
    }
    $sql .= ";";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();

    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // closed the database connection
    db_close($db);
    
    // If team separation is enabled
    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        
        if (!should_skip_test_and_audit_permission_check()) {
            $tests = array_filter($tests, function ($test) {
                return is_user_allowed_to_access($_SESSION['uid'], $test['id'], 'test');
            });
        }
    }

    if(encryption_extra())
    {
        // Decrypt associtated framework names
        foreach($tests as &$row){
            $framework_names = explode(",", $row['framework_names']);
            $decrypted_framework_names = [];
            foreach($framework_names as $framework_name)
            {
                if($framework_name){
                    $decrypted_framework_names[] = try_decrypt(trim($framework_name));
                }
            }
            $row['framework_names'] = implode(", ", $decrypted_framework_names);
        }
        
        // If encryption extra is enabled and sort field is Associated Frameworks, sort by manually
        if($order_field == "associated_frameworks")
        {
            usort($tests, function($a, $b) use ($order_dir)
                {
                    $aValue = trim($a['framework_names']);
                    $bValue = trim($b['framework_names']);
                    
                    if($order_dir == 'asc'){
                        return strcasecmp($aValue, $bValue);
                    }else{
                        return strcasecmp($bValue, $aValue);
                    }
                }
            );
        }
    }
    
    return $tests;
}

/****************************************
 * FUNCTION: INSERT TEST RESULT TO RISK *
 ***************************************/
function save_test_result_to_risk($result_id, $risk_id) {

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("INSERT INTO framework_control_test_results_to_risks (`test_results_id`, `risk_id`) VALUES(:test_results_id, :risk_id);");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    return true;
}
/****************************************
 * FUNCTION: DELETE TEST RESULT TO RISK *
 ***************************************/
function delete_test_result_to_risk($result_id, $risk_id) {

    // Open the database connection
    $db = db_open();

    // delete existing risk association
    $stmt = $db->prepare("DELETE FROM framework_control_test_results_to_risks WHERE `test_results_id` = :test_results_id AND `risk_id` = :risk_id;");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    return true;
}
/*****************************************************
 * FUNCTION: DELETE TEST RESULT TO RISK BY RESULT ID *
 *****************************************************/
function delete_test_result_to_risk_by_result_id($result_id) {

    // Open the database connection
    $db = db_open();

    // delete existing risk association
    $stmt = $db->prepare("DELETE FROM framework_control_test_results_to_risks WHERE `test_results_id` = :test_results_id;");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    return true;
}
/*****************************************
 * FUNCTION: GET TEST RESULT TO RISK IDs *
 *****************************************/
function get_test_result_to_risk_ids($result_id) {

    // Open the database connection
    $db = db_open();

    // delete existing risk association
    $stmt = $db->prepare("SELECT * FROM framework_control_test_results_to_risks WHERE `test_results_id` = :test_results_id");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    $risk_ids = [];
    foreach($results as $row){
        $risk_ids[] = $row["risk_id"];
    }

    return $risk_ids;
}

/*******************************************
 * FUNCTION: CLOSE RISKS BY TEST RESULT ID *
 *******************************************/
function close_risks_by_test_result_id($result_id, $test_result) {

    // Enforce close_risks at the sink. The submission flow that reaches
    // here is gated only on modify_audits, and the branch that calls this is
    // driven by the client-supplied remove_associated_risk flag — the close_risks
    // gate exists only in the page JavaScript. Checking here rather than only at
    // the caller covers submit_test_result() and any future caller automatically.
    if (close_risks_by_test_result_denied('check_permission'))
    {
        write_debug_log("The currently authenticated session attempted to close the risks associated with test result ID \"{$result_id}\" without the 'close_risks' permission. No risks were closed.", "warning");
        return false;
    }

    // Open the database connection
    $db = db_open();

    // delete existing risk association
    $stmt = $db->prepare("SELECT * FROM framework_control_test_results_to_risks WHERE `test_results_id` = :test_results_id");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    foreach($results as $row){
        $id = (int)$row['risk_id'] + 1000;
        $status = "Closed";
        $close_reason = 1;
        $note = "Risk was closed when the \"" . $result_id . "\" test was marked as \"" . $test_result . "\".";

        // Close the risk
        close_risk($id, $_SESSION['uid'], $status, $close_reason, $note);
    }

    return true;
}

/**************************************************
 * FUNCTION: CHECK PERMISSION FOR COMPLIANCE FILE *
 **************************************************/
function check_permission_for_compliance_file($unique_name) {

    // If team separation is enabled
    if (team_separation_extra()) {

        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Open the database connection
        $db = db_open();

        $sql = "
            SELECT 
                cf.id
            FROM 
                compliance_files cf
                JOIN framework_control_test_audits a ON cf.ref_id = a.id
                LEFT JOIN `framework_controls` fc ON a.framework_control_id = fc.id
                LEFT JOIN `framework_control_tests` fct ON fct.id = a.test_id   
                LEFT JOIN `items_to_teams` i2t ON i2t.item_id = a.id and i2t.type = 'audit'
            WHERE 
                cf.unique_name = :unique_name 
                AND cf.ref_type = 'test_audit'" . 
                get_user_teams_query_for_tests_and_audits("a", false, true) . "
            LIMIT 1;
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Close the database connection
        db_close($db);

        // Check if the user has permission to access the file
        if ($result) {

            return true; // User has permission to access the file

        } else {

            return false; // User does not have permission to access the file

        }

    } else {

        return true; // If team separation is not enabled, allow access by default
        
    }
}

/*********************************************
 * FUNCTION: GET TESTS TO AUTO INITIATE      *
 *********************************************/
function get_tests_to_auto_initiate() {
    
    // Open the database connection
    $db = db_open();

    // Automated (interval/calendar) tests only -- 'manual' schedule_type tests are never
    // auto-initiated. The next_date >= CURDATE() guard is intentionally dropped so that
    // past-due windows are still selected (catch-up), rather than silently skipped forever.
    // Dedupe is keyed on the window itself (ta.next_date = t1.next_date) so ANY existing
    // audit for that window -- whether auto-initiated or manually created -- blocks a
    // duplicate, regardless of when that audit was created.
    $sql = "
        SELECT t1.*
        FROM
            `framework_control_tests` t1
        WHERE
            t1.schedule_type IN ('interval','calendar')
            AND t1.next_date IS NOT NULL
            AND DATE_SUB(t1.next_date, INTERVAL COALESCE(t1.audit_initiation_offset, 0) DAY) <= CURDATE()
            AND NOT EXISTS (
                SELECT 1
                FROM
                    `framework_control_test_audits` ta
                WHERE
                    ta.test_id = t1.id
                    AND ta.next_date = t1.next_date
            );
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // closed the database connection
    db_close($db);

    return $tests;
}

/*********************************************
 * FUNCTION: RUN AUTO INITIATE TEST CRON JOB *
 *********************************************/
function run_auto_initiate_test_cron() {
    
    $initiated_audit_status = get_setting("initiated_audit_status") ? get_setting("initiated_audit_status") : 0;

    // get all tests that need to be initiated automatically 
    // which have audit_initiation_offset not null and today should be between next_audit_date - audit_initiation_offset and next_audit_date and there is no initiated audit between next-audit_date - audit_initiation_offset and next_audit_date
    $tests = get_tests_to_auto_initiate(); 
    foreach ($tests as $test) {
        initiate_test_audit($test['id'], $initiated_audit_status, [], false);
    }
}

/**************************************************************
 * FUNCTION: GET FRAMEWORK CONTROLS WITH TEST STATUS COUNTS   *
 * Returns an array with framework names and counts of         *
 * passing and failing controls based on their most recent    *
 * test result                                                 *
 **************************************************************/
function get_framework_controls_test_status_counts($framework_ids = null) {
    // null  = no filter (all active frameworks)
    // []    = explicit empty selection (return nothing)
    // [ids] = filter to those framework IDs
    if ($framework_ids !== null && empty($framework_ids)) {
        return [];
    }

    // Open the database connection
    $db = db_open();

    // Build optional IN clause for framework filtering
    $fw_clause = '';
    if (!empty($framework_ids)) {
        $placeholders = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$placeholders})";
    }

    // Query to get controls by framework with passing/failing test counts
    // Uses the most recent test result for each control
    $stmt = $db->prepare("
        SELECT
            f.value as framework_id,
            f.name as framework_name,
            COUNT(DISTINCT fc.id) as total_controls,
            COUNT(DISTINCT CASE WHEN latest_test.test_result = 'Pass' THEN fc.id END) as passing_controls,
            COUNT(DISTINCT CASE WHEN latest_test.test_result = 'Fail' THEN fc.id END) as failing_controls
        FROM frameworks f
        INNER JOIN framework_control_mappings fcm ON f.value = fcm.framework
        INNER JOIN framework_controls fc ON fcm.control_id = fc.id AND fc.deleted = 0
        LEFT JOIN (
            SELECT
                acm1.framework_control_id,
                tr1.test_result
            FROM audit_control_map acm1
            INNER JOIN framework_control_test_audits ta1 ON ta1.id = acm1.audit_id
            INNER JOIN framework_control_test_results tr1 ON ta1.id = tr1.test_audit_id
            WHERE tr1.test_result IN ('Pass', 'Fail')
            AND tr1.submission_date = (
                SELECT MAX(tr2.submission_date)
                FROM audit_control_map acm2
                INNER JOIN framework_control_test_audits ta2 ON ta2.id = acm2.audit_id
                INNER JOIN framework_control_test_results tr2 ON ta2.id = tr2.test_audit_id
                WHERE acm2.framework_control_id = acm1.framework_control_id
                AND tr2.test_result IN ('Pass', 'Fail')
            )
        ) latest_test ON fc.id = latest_test.framework_control_id
        WHERE f.status = 1 {$fw_clause}
        GROUP BY f.value, f.name
        ORDER BY f.name ASC
    ");

    if (!empty($framework_ids)) {
        $stmt->execute(array_values($framework_ids));
    } else {
        $stmt->execute();
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $results;
}

/****************************************************************
 * FUNCTION: GET CONTROL PASS/FAIL COUNTS BY ATTRIBUTE          *
 * Pass/fail control counts grouped by a control attribute      *
 * (domain/family, class, phase, priority, current maturity).   *
 * Each attribute is an id on framework_controls that references *
 * a same-named lookup table (value/name). Uses the latest       *
 * Pass/Fail result per control. $framework_ids: null=all active,*
 * []=none, [ids]=filter. Returns rows with group_id/group_name/ *
 * passing_controls/failing_controls for groups that have at     *
 * least one tested (pass/fail) control.                         *
 ****************************************************************/
function get_control_pass_fail_counts_by_attribute($attribute, $framework_ids = null) {
    // Whitelist attribute -> (column, lookup table). Fixed set, never user input,
    // so safe to interpolate as SQL identifiers.
    $allowed = ['family', 'control_class', 'control_phase', 'control_priority', 'control_maturity'];
    if (!in_array($attribute, $allowed, true)) {
        return [];
    }
    $col = $attribute;      // column on framework_controls
    $lookup = $attribute;   // same-named lookup table (value/name)

    if ($framework_ids !== null && empty($framework_ids)) {
        return [];
    }

    // Ordering: domain (family) reads best alphabetically; the ordinal
    // attributes (maturity, priority, phase, class) read best in their defined
    // lookup order (e.g. Not Performed -> Optimizing), not alphabetically.
    $order = ($attribute === 'family') ? 'l.name ASC' : 'l.value ASC';

    $db = db_open();

    $fw_clause = '';
    if (!empty($framework_ids)) {
        $placeholders = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$placeholders})";
    }

    // Lookup-driven (LEFT JOIN from the lookup table) so EVERY defined level of
    // the attribute appears as a bar — including levels no control currently
    // sits at (e.g. all six maturity levels even when every control is still
    // "Not Performed"). Controls are scoped to the selected frameworks via the
    // IN-subquery; na = a scoped control whose latest status isn't Pass/Fail.
    $stmt = $db->prepare("
        SELECT
            l.value AS group_id,
            l.name  AS group_name,
            COUNT(DISTINCT CASE WHEN latest_test.test_result = 'Pass' THEN fc.id END) AS passing_controls,
            COUNT(DISTINCT CASE WHEN latest_test.test_result = 'Fail' THEN fc.id END) AS failing_controls,
            COUNT(DISTINCT CASE WHEN fc.id IS NOT NULL AND latest_test.test_result IS NULL THEN fc.id END) AS na_controls
        FROM `{$lookup}` l
        LEFT JOIN framework_controls fc
            ON fc.`{$col}` = l.value
            AND fc.deleted = 0
            AND fc.id IN (
                SELECT fcm.control_id
                FROM framework_control_mappings fcm
                INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1 {$fw_clause}
            )
        LEFT JOIN (
            SELECT acm1.framework_control_id, tr1.test_result
            FROM audit_control_map acm1
            INNER JOIN framework_control_test_audits ta1 ON ta1.id = acm1.audit_id
            INNER JOIN framework_control_test_results tr1 ON ta1.id = tr1.test_audit_id
            WHERE tr1.test_result IN ('Pass', 'Fail')
            AND tr1.submission_date = (
                SELECT MAX(tr2.submission_date)
                FROM audit_control_map acm2
                INNER JOIN framework_control_test_audits ta2 ON ta2.id = acm2.audit_id
                INNER JOIN framework_control_test_results tr2 ON ta2.id = tr2.test_audit_id
                WHERE acm2.framework_control_id = acm1.framework_control_id
                AND tr2.test_result IN ('Pass', 'Fail')
            )
        ) latest_test ON fc.id = latest_test.framework_control_id
        GROUP BY l.value, l.name
        ORDER BY {$order}
    ");

    if (!empty($framework_ids)) {
        $stmt->execute(array_values($framework_ids));
    } else {
        $stmt->execute();
    }
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return $results;
}

/****************************************************************
 * FUNCTION: GET COMPLIANCE PASS/FAIL/NA TOTALS                 *
 * Distinct control counts across the scoped frameworks, split  *
 * into passing / failing / na (latest status is neither Pass   *
 * nor Fail — never tested, inconclusive, or blank). DISTINCT so *
 * a control mapped to several frameworks isn't double-counted   *
 * under "All Frameworks"; passing+failing+na therefore equals   *
 * get_compliance_total_controls() for the same scope.          *
 * $framework_ids: null=all active, []=none, [id]=that one.      *
 ****************************************************************/
function get_compliance_pass_fail_na_totals($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) {
        return ['passing' => 0, 'failing' => 0, 'na' => 0];
    }

    $db = db_open();
    $fw_clause = '';
    if (!empty($framework_ids)) {
        $placeholders = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$placeholders})";
    }
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN latest_test.test_result = 'Pass' THEN fc.id END) AS passing,
            COUNT(DISTINCT CASE WHEN latest_test.test_result = 'Fail' THEN fc.id END) AS failing,
            COUNT(DISTINCT CASE WHEN latest_test.test_result IS NULL THEN fc.id END) AS na
        FROM frameworks f
        INNER JOIN framework_control_mappings fcm ON f.value = fcm.framework
        INNER JOIN framework_controls fc ON fcm.control_id = fc.id AND fc.deleted = 0
        LEFT JOIN (
            SELECT acm1.framework_control_id, tr1.test_result
            FROM audit_control_map acm1
            INNER JOIN framework_control_test_audits ta1 ON ta1.id = acm1.audit_id
            INNER JOIN framework_control_test_results tr1 ON ta1.id = tr1.test_audit_id
            WHERE tr1.test_result IN ('Pass', 'Fail')
            AND tr1.submission_date = (
                SELECT MAX(tr2.submission_date)
                FROM audit_control_map acm2
                INNER JOIN framework_control_test_audits ta2 ON ta2.id = acm2.audit_id
                INNER JOIN framework_control_test_results tr2 ON ta2.id = tr2.test_audit_id
                WHERE acm2.framework_control_id = acm1.framework_control_id
                AND tr2.test_result IN ('Pass', 'Fail')
            )
        ) latest_test ON fc.id = latest_test.framework_control_id
        WHERE f.status = 1 {$fw_clause}
    ");
    if (!empty($framework_ids)) {
        $stmt->execute(array_values($framework_ids));
    } else {
        $stmt->execute();
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);

    return [
        'passing' => (int) ($row['passing'] ?? 0),
        'failing' => (int) ($row['failing'] ?? 0),
        'na'      => (int) ($row['na'] ?? 0),
    ];
}

/****************************************************************
 * FUNCTION: GET COMPLIANCE TOTAL CONTROLS                      *
 * Distinct control count across the active frameworks, scoped  *
 * by the compliance dashboard's single-select framework filter *
 * ($framework_ids: null=all active, []=none, [id]=that one).   *
 * DISTINCT so a control mapped to several frameworks isn't      *
 * double-counted under "All Frameworks".                       *
 ****************************************************************/
function get_compliance_total_controls($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) {
        return 0;
    }

    $db = db_open();
    $fw_clause = '';
    if (!empty($framework_ids)) {
        $placeholders = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$placeholders})";
    }
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT fc.id)
        FROM frameworks f
        INNER JOIN framework_control_mappings fcm ON f.value = fcm.framework
        INNER JOIN framework_controls fc ON fcm.control_id = fc.id AND fc.deleted = 0
        WHERE f.status = 1 {$fw_clause}
    ");
    if (!empty($framework_ids)) {
        $stmt->execute(array_values($framework_ids));
    } else {
        $stmt->execute();
    }
    $count = (int) $stmt->fetchColumn();
    db_close($db);
    return $count;
}

/****************************************************************
 * FUNCTION: GET COMPLIANCE OVERDUE TESTS COUNT                 *
 * Control test audits whose next scheduled date is in the past *
 * (next_date < today) — the actionable "these assessments are  *
 * late" number. Scoped by the compliance dashboard's single-   *
 * select framework filter ($framework_ids: null=all, []=none,  *
 * [id]=that one). COUNT(DISTINCT ta.id) so an audit whose       *
 * control maps to several in-scope frameworks isn't counted    *
 * more than once.                                              *
 ****************************************************************/
function get_compliance_overdue_tests_count($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) {
        return 0;
    }

    // Optional framework scope: only audits that map (via audit_control_map) to
    // a control in a selected framework. Null (e.g. the Home dashboard, no
    // selector) = all. The fc/audit_control_map join is ONLY load-bearing for
    // this scope filter (no per-control fan-out of the result), so it's built
    // exclusively inside $fw_join; COUNT(DISTINCT ta.id) keeps a common-test
    // audit whose several snapshot controls all land in scope counted once.
    $fw_join = $fw_clause = '';
    $params = [];
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_join = "INNER JOIN audit_control_map acm ON acm.audit_id = ta.id
                    INNER JOIN framework_controls fc ON fc.id = acm.framework_control_id AND fc.deleted = 0
                    INNER JOIN framework_control_mappings fcm ON fcm.control_id = fc.id
                    INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1";
        $fw_clause = "AND f.value IN ({$ph})";
        $params = array_values($framework_ids);
    }

    $db = db_open();
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ta.id)
        FROM framework_control_test_audits ta
        {$fw_join}
        WHERE ta.next_date IS NOT NULL
        AND ta.next_date != '0000-00-00'
        AND ta.next_date < CURDATE()
        {$fw_clause}
    ");
    $stmt->execute($params);
    $count = (int) $stmt->fetchColumn();
    db_close($db);
    return $count;
}

/************************************************************
 * FUNCTION: GET FRAMEWORK CONTROLS PASS RATE BY MONTH     *
 * Returns monthly pass rate (%) per framework over the    *
 * last $months months.                                    *
 * Result shape:                                           *
 *   [framework_name => [month => pass_rate_pct], ...]     *
 * month format: 'YYYY-MM'                                 *
 ************************************************************/
function get_framework_controls_pass_rate_by_month($months = 12, $framework_ids = null) {
    // null  = no filter (all active frameworks)
    // []    = explicit empty selection (return nothing)
    // [ids] = filter to those framework IDs
    if ($framework_ids !== null && empty($framework_ids)) {
        return [];
    }

    $db = db_open();

    // Build optional IN clause for framework filtering
    $fw_clause = '';
    if (!empty($framework_ids)) {
        $placeholders = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$placeholders})";
    }

    $stmt = $db->prepare("
        SELECT
            f.name AS framework_name,
            DATE_FORMAT(tr.submission_date, '%Y-%m') AS month,
            COUNT(DISTINCT CASE WHEN tr.test_result = 'Pass' THEN fc.id END) AS passing_controls,
            COUNT(DISTINCT fc.id) AS tested_controls
        FROM frameworks f
        INNER JOIN framework_control_mappings fcm ON f.value = fcm.framework
        INNER JOIN framework_controls fc ON fcm.control_id = fc.id AND fc.deleted = 0
        INNER JOIN audit_control_map acm ON acm.framework_control_id = fc.id
        INNER JOIN framework_control_test_audits ta ON ta.id = acm.audit_id
        INNER JOIN framework_control_test_results tr ON ta.id = tr.test_audit_id
            AND tr.test_result IN ('Pass', 'Fail')
        WHERE f.status = 1
            AND tr.submission_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            {$fw_clause}
        GROUP BY f.value, f.name, month
        ORDER BY f.name ASC, month ASC
    ");

    $params = array_merge([$months], !empty($framework_ids) ? array_values($framework_ids) : []);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    // Restructure into [framework => [month => pass_rate%]]
    $result = [];
    foreach ($rows as $row) {
        $fw      = $row['framework_name'];
        $mo      = $row['month'];
        $tested  = (int)$row['tested_controls'];
        $passing = (int)$row['passing_controls'];
        if (!isset($result[$fw])) {
            $result[$fw] = [];
        }
        $result[$fw][$mo] = $tested > 0 ? round(100 * $passing / $tested, 1) : 0.0;
    }

    return $result;
}

/*************************************************************
 * FUNCTION: GET AUDIT REMEDIATION CYCLE TIME BY FRAMEWORK *
 *                                                          *
 * Returns the average number of days from audit initiation *
 * (created_at) to result submission (submission_date) for  *
 * all closed audits, grouped by framework.                 *
 *                                                          *
 * Audits without a recorded test result are excluded.      *
 *                                                          *
 * $framework_ids:                                          *
 *   null  = no filter (all active frameworks)              *
 *   []    = explicit empty selection (return [])           *
 *   [ids] = filter to those framework IDs                  *
 *                                                          *
 * Returns array of:                                        *
 *   ['framework_name' => string, 'avg_days' => float,      *
 *    'audit_count'    => int]                              *
 *************************************************************/
function get_audit_remediation_cycle_time_by_framework($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) {
        return [];
    }

    $closed_status = (int)get_setting('closed_audit_status');

    $db = db_open();

    // Build optional IN clause for framework filtering
    $fw_clause = '';
    if (!empty($framework_ids)) {
        $placeholders = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$placeholders})";
    }

    // Route audit->control attribution through audit_control_map so a common-test
    // audit's cycle time counts under EVERY framework one of its snapshot
    // controls belongs to. The inner SELECT DISTINCT collapses a single audit
    // down to one row per (audit, framework) pair BEFORE aggregating — a common
    // test whose 2 snapshot controls both sit under the same framework must not
    // have that audit's DATEDIFF averaged in twice within that framework's group.
    $stmt = $db->prepare("
        SELECT
            audit_days.framework_name AS framework_name,
            ROUND(AVG(audit_days.days), 1) AS avg_days,
            COUNT(DISTINCT audit_days.audit_id) AS audit_count
        FROM (
            SELECT DISTINCT
                fcta.id AS audit_id,
                f.value AS framework_value,
                f.name AS framework_name,
                DATEDIFF(fctr.submission_date, fcta.created_at) AS days
            FROM framework_control_test_audits fcta
            INNER JOIN framework_control_test_results fctr ON fctr.test_audit_id = fcta.id
                AND fctr.submission_date IS NOT NULL
            INNER JOIN audit_control_map acm ON acm.audit_id = fcta.id
            INNER JOIN framework_controls fc ON fc.id = acm.framework_control_id AND fc.deleted = 0
            INNER JOIN framework_control_mappings fcm ON fcm.control_id = fc.id
            INNER JOIN frameworks f ON f.value = fcm.framework AND f.status = 1
            WHERE fcta.status = ?
                AND fcta.created_at IS NOT NULL
                {$fw_clause}
        ) audit_days
        GROUP BY audit_days.framework_value, audit_days.framework_name
        ORDER BY avg_days DESC
    ");

    $params = array_merge([$closed_status], !empty($framework_ids) ? array_values($framework_ids) : []);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    return $rows;
}

?>