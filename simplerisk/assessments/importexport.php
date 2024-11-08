<?php

    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG:Assessments', 'sorttable', 'multiselect', 'CUSTOM:common.js', 'cve_lookup', 'CUSTOM:pages/assessment.js'], ["check_assessments" => true]);

    // Check if assessment extra is enabled
    if (assessments_extra()) {

        // Include the assessments extra
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    } else {

        header("Location: ../index.php");
        exit(0);

    }

    // Process assessment import
    if (process_assessment_import()) {
        refresh();
    }

?>
<div class="row bg-white">
    <div class="col-12">
        <div class='hero-unit card-body border my-2'>
    <?php 
            display_import_of_assessment(); 
    ?>
        </div>
        <div class='hero-unit card-body border my-2'>
    <?php 
            display_export_of_assessment(); 
    ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>