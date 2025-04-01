<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['datatables', 'CUSTOM:common.js'], ['check_admin' => true], required_localization_keys: ['GenericDeleteItemConfirmation']);

    // Check if assessment extra is enabled
    if (assessments_extra()) {

        // Include the assessments extra
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    } else {

        header("Location: ../index.php");
        exit(0);

    }
    
    // If we should delete an active assessment
    if (isset($_POST['delete_active_assessments'])) {

        // Get the selected assessments
        $tokens = $_POST['tokens'];

        // Delete the assessments
        delete_active_questionnaires($tokens);

        // Display an alert
        set_alert(true, "good", "The assessment(s) were deleted successfully.");
        
        refresh();

    }

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border d-flex align-items-center alert alert-danger" role="alert">
            Deleted Assessments Cannot Be Recovered
        </div>
        <div class='card-body my-2 border'>
    <?php 
            display_active_assessments(); 
    ?>
        </div>
    </div>
</div>
<script type='text/javascript'>

    function checkAll(bx) {
        var cbs = document.getElementsByTagName('input');
        for(var i=0; i < cbs.length; i++) {
            if (cbs[i].type == 'checkbox') {
                cbs[i].checked = bx.checked;
            }
        }
    }

    $(function () {
        
        $('#questionaires_table').DataTable({
            serverSide: false,
			order: [[1, 'asc']],
			columnDefs: [{'targets': 0, 'orderable': false}], 
        });

        $('.btn-delete').on('click', function () {

            // if no checkboxes are checked, show an alert
            if (!$('[name="tokens[]"]:checked').length) {

                showAlertFromMessage("<?= $escaper->escapeHtml($lang['PleaseSelectAtLeastOneAssessmentToDelete']) ?>", false);

            // if checkboxes are checked, show a confirmation dialog
            } else {

                confirm(_lang['GenericDeleteItemConfirmation'], () => $('#delete_active_assessments').trigger('submit'));

            }

        });

    });

</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>