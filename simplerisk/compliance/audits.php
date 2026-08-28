<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    $breadcrumb_title_key = "ManageAudits";
    $active_sidebar_menu = "Compliance";
    $active_sidebar_submenu = "ManageAudits";
    // 'Untested' -- rendered client-side by DataTable.render.testDate()
    // (dataTables.renderers.js) whenever the 'test_date' field's value is
    // blank (an audit with no result recorded yet).
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'datetimerangepicker', 'CUSTOM:sr-select.js', 'CUSTOM:sr-row-actions-menu.js', 'CUSTOM:pages/compliance.js', 'CUSTOM:common.js'], ['check_compliance' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu, '', '', null, ['Untested', 'ShowingXToYOfZ']);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

?>
<?php
    // No .row/.col-12 grid wrapper: Bootstrap's row applies a negative
    // horizontal margin that .content's own edge cancels out, but col-12's
    // matching positive padding is NOT cancelled again for a plain child
    // (there's no second nested row to offset it) -- so the card renders
    // ~10px narrower than .content, indented from the page title/breadcrumb
    // above it instead of flush with them. Rendering the card as a direct
    // child of .content avoids that inset entirely. .sr-table-card floats on
    // the page's gray ground ($sr-default) per the design system's "cards on
    // gray, never a white slab" rule -- so nothing here supplies a bg-white;
    // .content already provides the gray background.
    display_audits();
?>
<script>
    $(function() {

        // display custom display settings when clicking the setting cog button
        $("[data-bs-target='#setting_modal-all_audits']").on('click', function() {

            // Set false to all checkboxes
            $(`form#custom_display_settings-all_audits [type='checkbox']`).prop('checked', false);

            // Set true to checkboxes that are in the custom_display_settings array
            custom_display_settings_all_audits.map((e) => {
                return $(`form#custom_display_settings-all_audits [name='${e}']`).prop('checked', true);
            });

        });

    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
