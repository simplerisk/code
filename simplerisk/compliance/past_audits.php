<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'datetimerangepicker', 'CUSTOM:pages/compliance.js'], ['check_compliance' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

?>
<div class="row bg-white">
    <div class="col-12">
    <?php 
        display_past_audits(); 
    ?>
    </div>
</div>
<?php
	// Render the footer of the page. Please don't put code after this part.
    render_footer();
?>