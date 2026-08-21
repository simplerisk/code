<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js', 'UILayoutWidget'], ['check_compliance' => true], active_sidebar_submenu: 'Reporting_Dashboards', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'ComplianceDashboard');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));
    require_once(realpath(__DIR__ . '/../includes/governance.php'));

    // Get all active frameworks for the filter dropdown
    $all_frameworks = array_values(get_frameworks(1));

    // Single-select framework filter. The whole dashboard is either "All Frameworks"
    // (the aggregate, default) or drilled into exactly one framework — never a
    // partial multi-select, which is what made the per-framework charts unreadable.
    //   - no / empty / non-numeric 'frameworks'  => All Frameworks (null filter)
    //   - a numeric id                            => that one framework
    $selected_fw_id = (isset($_GET['frameworks']) && ctype_digit((string)$_GET['frameworks'])) ? (int)$_GET['frameworks'] : null;

    // Resolve the selected framework's display name for the dynamic dashboard title.
    $framework_label = $lang['AllFrameworks'] ?? 'All Frameworks';
    if ($selected_fw_id !== null) {
        foreach ($all_frameworks as $fw) {
            if ((int)$fw['value'] === $selected_fw_id) {
                $framework_label = $fw['name'];
                break;
            }
        }
    }
    // e.g. "Compliance Dashboard: All Frameworks" or "Compliance Dashboard: ISO 27002 (2022)".
    // Colon-separated (not wrapped in parens) so a framework name that already
    // contains parens — "ISO 27002 (2022)" — doesn't produce nested "(( ))".
    $dashboard_title = ($lang['ComplianceDashboard'] ?? 'Compliance Dashboard') . ': ' . $framework_label;
?>
<!-- Global framework filter — a dashboard toolbar control that sits ABOVE the
     widget grid, not a card inside it (hoisted into the breadcrumb row below). -->
<div class="sr-dashfilter">
    <label class="sr-dashfilter__label" for="frameworks_select"><?= $escaper->escapeHtml($lang['Framework']); ?></label>
    <div class="sr-dashfilter__control">
        <select id="frameworks_select" class="form-select form-select-sm">
            <option value=""<?= $selected_fw_id === null ? ' selected' : '' ?>><?= $escaper->escapeHtml($lang['AllFrameworks'] ?? 'All Frameworks') ?></option>
<?php foreach ($all_frameworks as $fw): ?>
            <option value="<?= (int)$fw['value'] ?>"<?= $selected_fw_id === (int)$fw['value'] ? ' selected' : '' ?>><?= $escaper->escapeHtml($fw['name']) ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <form id="compliance_filter_form" method="GET" class="sr-dashfilter__form">
        <input type="hidden" name="frameworks" id="framework_options" value="<?= $selected_fw_id !== null ? (int)$selected_fw_id : '' ?>">
    </form>
</div>
<?php
    (new \includes\Widgets\UILayout('compliance_dashboard'))->render();
?>
<script>
$(function() {
    // Hoist the framework filter up into the shared page header's breadcrumb row,
    // right-aligned so it sits directly under the "Edit layout" button (which the
    // dashboard hoists into the title row). Mirrors UILayout's edit-layout hoist.
    var $sub = $('.page-breadcrumb .page-subtitle').first();
    var $filter = $('.sr-dashfilter').first();
    if ($sub.length && $filter.length) {
        $sub.addClass('page-subtitle--with-action');
        $sub.append($filter);
    }

    // Reflect the selected framework in the dashboard title. .text() sets text
    // content (never HTML) and the value is JSON-encoded server-side, so the
    // framework name can't inject markup.
    $('.page-breadcrumb .page-title').text(<?= json_encode($dashboard_title) ?>);

    // Single-select: submitting on change re-runs every widget with the one
    // selection (empty value = All Frameworks).
    $('#frameworks_select').on('change', function() {
        $('#framework_options').val($(this).val());
        $('#compliance_filter_form').submit();
    });
});
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
