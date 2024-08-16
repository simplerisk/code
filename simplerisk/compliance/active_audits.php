<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'CUSTOM:pages/compliance.js'], ['check_compliance' => true]);
// Include required functions file
require_once(realpath(__DIR__ . '/../includes/governance.php'));
require_once(realpath(__DIR__ . '/../includes/compliance.php'));

?>

<div class="row bg-white m-2 compliance-content-container content-margin-height">
    <div class="col-md-12" >
         <?php display_active_audits(); ?>
    </div>
</div>

<?php  
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>
