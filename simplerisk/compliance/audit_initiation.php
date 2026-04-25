<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'easyui', 'datetimerangepicker', 'CUSTOM:pages/compliance.js', 'CUSTOM:pages/governance.js', 'CUSTOM:common.js'], ['check_compliance' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="custom-treegrid-container" id="initiate-audits">
    <?php 
            display_initiate_audits(); 
    ?>
        </div>
    </div>
</div>
<script>
    $(document).ready(function(){

    <?php 
        if (customization_extra()) {
    ?>
        $('.datepicker').initAsDatePicker();
        $("select[id^='custom_field'].multiselect").multiselect({buttonWidth: '300px', enableFiltering: true, enableCaseInsensitiveFiltering: true});
    <?php 
        }
    ?>
        
        // Event when clicks Initiate Framework, Control, Test Audit button
        $('body').on("click", ".initiate-framework-audit-btn, .initiate-control-audit-btn, .initiate-test-btn", function() {

            let type = "";
            if ($(this).hasClass("initiate-framework-audit-btn")) {
                type = "framework";
            } else if ($(this).hasClass("initiate-control-audit-btn")) {
                type = "control";
            } else if ($(this).hasClass("initiate-test-btn")) {
                type = "test";
            }

            let modal = $('#tags--edit');
            $('[name=audit_type]', modal).val(type);
            $('[name=id]', modal).val($(this).data('id'));

            // Clear tags selectize in the tags edit modal
            let select = $('[name="tags[]"]', modal).selectize();
            let selectize = select[0].selectize;
            selectize.clear();

            modal.modal("show");

        });

        // Event when clicks Continue button on the Tags Edit modal
        $("#tags--edit").on("click", "[name=continue_add_tags]", function(){
            var type = $("#tags--edit [name=audit_type]").val();
            var id = $("#tags--edit [name=id]").val();
            var tags = $("#tags--edit [name='tags[]']").val();
            if($(this).attr("name") == "continue_add_tags") {
                var tags = $("#tags--edit [name='tags[]']").val();
            } else {
                var tags = [];
            }
            var select = $("#tags--edit [name='tags[]']").selectize();
            var selectize = select[0].selectize;
            selectize.clear();
        
            $.ajax({
                url: BASE_URL + '/api/compliance/audit_initiation/initiate',
                type: 'POST',
                data: {
                    type: type, // control, test
                    tags: tags, // selected tags
                    id: id,
                },
                success : function (res){
                    if(res.status_message){
                        showAlertsFromArray(res.status_message);
                    }
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                }
            });
        });

        // Initialize the WYSIWYG editor for the update framework modal
        $("#framework--update [name=framework_description]").attr("id", "update_framework_description");
        init_minimun_editor('#update_framework_description');

        // Initialize the WYSIWYG editor for the update control modal
        $("#control--update [name=description]").attr("id", "update_control_description");
        init_minimun_editor('#update_control_description');
        $("#control--update [name=supplemental_guidance]").attr("id", "update_supplemental_guidance");
        init_minimun_editor('#update_supplemental_guidance');

        // Initialize the WYSIWYG editor for the update test modal
        $("#test--update [name=objective]").attr("id", "update_objective");
        init_minimun_editor('#update_objective');
        $("#test--update [name=test_steps]").attr("id", "update_test_steps");
        init_minimun_editor('#update_test_steps');
        $("#test--update [name=expected_results]").attr("id", "update_expected_results");
        init_minimun_editor('#update_expected_results');

        // Initialize the multiselect for the update control modal
        $("select[name='control_type[]'").multiselect({
        	allSelectedText: '<?= $escaper->escapeHtml($lang['ALL']); ?>',
            enableFiltering: true,
            maxHeight: 250,
            buttonWidth: '100%',
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
        });

        $("#update_test").on("click", function() {

            // Check if the required fields have empty / trimmed empty values
            if (!checkAndSetValidation("#update-test-form")) {
                return false;
            }

        });
    });
</script>

<!-- MODAL WINDOW FOR UPDATING FRAMEWORK -->
<?php
    display_update_framework_modal('audit_initiation');
?>

<!-- MODAL WINDOW FOR UPDATING CONTROL -->
<?php
    display_update_control_modal('audit_initiation');
?>

<!-- MODAL WINDOW FOR UPDATING TEST -->
<?php
    display_update_test_modal('audit_initiation');
?>

<!-- MODEL WINDOW FOR ADD TAGS TO TEST -->
<div id="tags--edit" class="modal fade " tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form class="" id="tags-edit-form" method="post" autocomplete="off">
                <input type="hidden" name="audit_type" value="">
                <input type="hidden" name="id" value="">
                <div class="modal-header">
                    <h4 class="modal-title"><?= $escaper->escapeHtml($lang['AddTagsToTestAudit']); ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['Tags']); ?> :</label>
                        <select class="test_audit_test_tags" readonly name="tags[]" multiple placeholder="<?= $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
                        <div class="tag-max-length-warning"><?= $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" name="cancel_add_tags" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button name="continue_add_tags" class="btn btn-danger" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Continue']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
    // Display the add mapping and asset rows
    // These are used in the control add and update modals
    display_add_mapping_row();
    display_add_asset_row();
?>
<script>
    $( document ).ready(function() {

        // Initialize the datepicker
        $("#additional_stakeholders").multiselect({
            buttonWidth: '100%'
        });

        $("[name='team[]']").multiselect({
            buttonWidth: '100%'
        });

        //Have to remove the 'fade' class for the shown event to work for modals
        $('#framework--update, #control--update, #test--update').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>