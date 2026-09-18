<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Plan Projects (SR-2229): a thin shell. display_plan_projects() prints the
// two .sr-table-card grids and the sr-modal dialogs; js/simplerisk/pages/
// plan-projects.js renders everything from /api/v2/management/projects*.
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(
    // 'datatables' + 'colreorder' + 'datatables:rowreorder': the grid, its
    // column drag-reorder, and the priority drag handle. 'datetimerangepicker'
    // pulls moment.js for the due-date pill and the modal datepicker.
    // 'multiselect' backs Customization custom-field multiselects in the
    // modals. sr-row-actions-menu / sr-select / sr-faceted-picker are the
    // shared design-system widgets (row overflow menu, filter selects, the
    // Add risks picker). blockUI covers the bulk actions.
    ['blockUI', 'datatables', 'colreorder', 'datatables:rowreorder', 'datetimerangepicker', 'multiselect', 'CUSTOM:common.js', 'CUSTOM:sr-row-actions-menu.js', 'CUSTOM:sr-select.js', 'CUSTOM:sr-faceted-picker.js', 'CUSTOM:pages/plan-projects.js'],
    ['check_riskmanagement' => true],
    'PrioritizeForProjectPlanning',
    'RiskManagement',
    'PrioritizeForProjectPlanning',
    required_localization_keys: ['ThereAreRequiredFields', 'NSelected', 'Search', 'NoMatchingOptions']
);

display_plan_projects();
display_plan_projects_modals();

// Render the footer of the page. Please don't put code after this part.
render_footer();
