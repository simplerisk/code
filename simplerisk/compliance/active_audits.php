<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'CUSTOM:pages/compliance.js', 'CUSTOM:common.js'], ['check_compliance' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

?>
<div class="row bg-white">
    <div class="col-12">
    <?php 
        display_active_audits(); 
    ?>
    </div>
</div>
<script>
    $(function() {

        // display custom display settings when clicking the setting cog button
        $("[data-bs-target='#setting_modal-active_audits']").on('click', function() {

            // Set false to all checkboxes
            $(`form#custom_display_settings-active_audits [type='checkbox']`).prop('checked', false);

            // Set true to checkboxes that are in the custom_display_settings array
            custom_display_settings.map((e) => {
                return $(`form#custom_display_settings-active_audits [name='${e}']`).prop('checked', true);
            });
            
        });

    });
</script>
<?php  
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>