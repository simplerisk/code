<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js', 'UILayoutWidget', 'multiselect'], ['check_compliance' => true], active_sidebar_submenu: 'Reporting_Compliance', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'ComplianceDashboard');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));
    require_once(realpath(__DIR__ . '/../includes/governance.php'));

    // Get all active frameworks for the filter dropdown
    $all_frameworks = array_values(get_frameworks(1));

    // Resolve selected framework IDs from GET param.
    // - No 'frameworks' key in URL (first load): default to all (null = no filter)
    // - Key present with IDs: filter to those IDs
    // - Key present but empty (user deselected all): return empty results
    if (!isset($_GET['frameworks'])) {
        // First load — select all in the multiselect, pass null to data functions
        $selected_fw_ids = array_column($all_frameworks, 'value');
        $filter_ids      = null;
    } else {
        $selected_fw_ids = array_values(array_filter(explode(',', $_GET['frameworks']), 'ctype_digit'));
        $selected_fw_ids = array_map('intval', $selected_fw_ids);
        $filter_ids      = $selected_fw_ids; // may be [] if user deselected all
    }

    // Current filter value for the hidden form field (comma-separated)
    $current_filter_value = implode(',', $selected_fw_ids);
?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body border my-2">

            <!-- Framework filter -->
            <div class="row mb-3">
                <div class="col-4">
                    <label><strong><?= $escaper->escapeHtml($lang['Framework']); ?> :</strong></label>
                    <?php create_multiple_dropdown('frameworks_select', $selected_fw_ids, 'frameworks_select', $all_frameworks); ?>
                    <form id="compliance_filter_form" method="GET">
                        <input type="hidden" name="frameworks" id="framework_options" value="<?= $escaper->escapeHtml($current_filter_value); ?>">
                    </form>
                </div>
            </div>
    <?php
            (new \includes\Widgets\UILayout('compliance_dashboard'))->render();
    ?>
        </div>
    </div>
</div>
<script>
$(function() {
    $('#frameworks_select').multiselect({
        allSelectedText: '<?= js_string_escape($lang['AllFrameworks'] ?? 'All Frameworks'); ?>',
        buttonWidth: '100%',
        includeSelectAllOption: true,
        enableCaseInsensitiveFiltering: true,
        onChange: submitFrameworkFilter,
        onSelectAll: submitFrameworkFilter,
        onDeselectAll: submitFrameworkFilter,
    });

    function submitFrameworkFilter() {
        var selected = [];
        $('#frameworks_select option:selected').each(function() {
            selected.push($(this).val());
        });
        $('#framework_options').val(selected.join(','));
        $('#compliance_filter_form').submit();
    }
});
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
