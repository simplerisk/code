<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG:Assessments', 'multiselect', 'CUSTOM:common.js', 'cve_lookup', 'CUSTOM:pages/assessment.js', 'datetimerangepicker'], ["check_assessments" => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/assessments.php'));

    // Check if assessment extra is enabled
    if (assessments_extra()) {

        // Include the assessments extra
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    } else {

        header("Location: ../index.php");
        exit(0);

    }

    // Process actions on questionnaire pages
    if (process_questionnaire_pending_risks()) {
        refresh();
    }
?>
<div class="row">
   <div class="col-12">
       <div class="card-body my-2 border">
    <?php 
            display_questionnaire_risk_analysis(); 
    ?>
       </div>
   </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>