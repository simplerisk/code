<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    $breadcrumb_title_key = 'Compare questionnaires.';
    $active_sidebar_menu = 'Assessments';
    $active_sidebar_submenu = 'QuestionnaireResults';

    render_header_and_sidebar(['selectize', 'CUSTOM:common.js', 'cve_lookup', 'CUSTOM:pages/assessment.js'], ["check_assessments" => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu);

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

    $success = true;

    if (empty($_GET["templates"])) {
        set_alert(true, "bad", $lang['TemplatesAreRequired']);
        $success = false;
    }

?>
<div class="row bg-white">
    <div class="col-12">
        <div class='card-body my-2 border'>
    <?php 
        if ($success) {
            display_compare_questionnaire_results($_GET["templates"]); 
        }
    ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>