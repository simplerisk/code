<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'datepicker', 'easyui', 'datetimerangepicker', 'CUSTOM:pages/compliance.js', 'CUSTOM:pages/governance.js', 'CUSTOM:common.js'], ['check_compliance' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

    // Check if editing test
    if (isset($_POST['update_test'])) {

        $test_id = (int)$_POST['test_id'];
        $tester = (int)$_POST['tester'];
        $additional_stakeholders = empty($_POST['additional_stakeholders']) ? "" : implode(",", $_POST['additional_stakeholders']);
        $teams = isset($_POST['team']) ? $_POST['team'] : [];
        $test_frequency = (int)$_POST['test_frequency'];
        $last_date = get_standard_date_from_default_format($_POST['last_date']);
        $next_date = get_standard_date_from_default_format($_POST['next_date']);
        $name = $_POST['name'];
        $objective = $_POST['objective'];
        $test_steps = $_POST['test_steps'];
        $approximate_time = (int)($_POST['approximate_time']) ? $_POST['approximate_time'] : 0;
        $expected_results = $_POST['expected_results'];
        $tags = empty($_POST['tags']) ? [] : $_POST['tags'];
        
        // Update a framework control test
        update_framework_control_test($test_id, $tester, $test_frequency, $name, $objective, $test_steps, $approximate_time, $expected_results, $last_date, $next_date, false, $additional_stakeholders, $teams, $tags);
        
        set_alert(true, "good", $escaper->escapeHtml($lang['TestSuccessUpdated']));
        
        // Refresh current page
        refresh();

    }

    // Check if initiate framework or control or test
    if (isset($_GET['initiate'])) {

        $id = (int)$_GET['id'];
        $type = $escaper->escapeHtml($_GET['type']);
        
        if ($name = initiate_framework_control_tests($type, $id)) {

            if ($type == 'framework') {

                set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedAllTestsUnderFramework', ['framework' => $name])));

            } elseif ($type == 'control') {

                set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedAllTestsUnderControl', ['control' => $name])));

            } elseif ($type == 'test') {

                set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedTest', ['test' => $name])));

            }
        }
        
        // Go back to old page
        refresh(build_url("compliance/audit_initiation.php"));

    }
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
        
        $("body").on("click", ".test-name", function(e){
            e.preventDefault();
            
            var test_id = $(this).data('id');
            $.ajax({
                type: "GET",
                url: BASE_URL + "/api/compliance/test?id=" + test_id,
                success: function(result){
                    var data = result['data'];
                    var modal = $('#test--edit');
                    
                    $('[name=test_id]', modal).val(data['id']);
                    $('[name=tester]', modal).val(data['tester']);
                    $('#additional_stakeholders', modal).multiselect('deselectAll', false);
                    $('#additional_stakeholders', modal).multiselect('select', data['additional_stakeholders']);

                    $("[name='team[]']", modal).multiselect('deselectAll', false);
                    $("[name='team[]']", modal).multiselect('select', data['teams']);

                    $('[name=test_frequency]', modal).val(data['test_frequency']);
                    $('[name=last_date]', modal).val(data['last_date']);
                    $('[name=next_date]', modal).val(data['next_date']);
                    $('[name=name]', modal).val(data['name']);
                    $('[name=objective]', modal).val(data['objective']);
                    $('[name=test_steps]', modal).val(data['test_steps']);
                    $('[name=approximate_time]', modal).val(data['approximate_time']);
                    $('[name=expected_results]', modal).val(data['expected_results']);
                    $(".datepicker" , modal).initAsDatePicker();
                    $.each(data['tags'], function (i, item) {
                        $('[name=\'tags[]\']', modal).append($('<option>', { 
                            value: item,
                            text : item,
                            selected : true,
                        }));
                    });
                    var select = $('[name=\'tags[]\']', modal).selectize();
                    var selectize = select[0].selectize;
                    selectize.setValue(data['tags']);

					setEditorContent("objective", data['objective']);
					setEditorContent("test_steps", data['test_steps']);
					setEditorContent("expected_results", data['expected_results']);

                    modal.modal("show");
                }
            })
        })
        
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
        init_minimun_editor('#objective');
        init_minimun_editor('#test_steps');
        init_minimun_editor('#expected_results');

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

<!-- MODEL WINDOW FOR EDITING FRAMEWORK -->
<?php
    display_update_framework_modal('audit_initiation');
?>

<!-- MODEL WINDOW FOR UPDATING CONTROL -->
<?php
    display_update_control_modal('audit_initiation');
?>

<!-- MODEL WINDOW FOR EDITING TEST -->
<div id="test--edit" class="modal fade " tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form class="" id="update-test-form" method="post" autocomplete="off">
                <input type="hidden" name="test_id" value="">
                <input type="hidden" name="update_test" value="true">
                <div class="modal-header">
                    <h4 class="modal-title"><?= $escaper->escapeHtml($lang['TestEditHeader']); ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestName']); ?><span class="required">*</span> :</label>
                            <input type="text" name="name" required value="" class="form-control" maxlength="1000" title="<?= $escaper->escapeHtml($lang['TestName']); ?>">
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Tester']); ?> :</label>
    <?php 
                            create_dropdown("enabled_users", NULL, "tester", false, false, false); 
    ?>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?> :</label>
    <?php 
                            create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders"); 
    ?>
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Teams']); ?> :</label>
    <?php 
                            create_multiple_dropdown("team"); 
    ?>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestFrequency']); ?><small class="white-labels ms-1">(<?= $escaper->escapeHtml($lang['days']); ?>)</small> :</label>
                            <input type="number" min="0" max="2147483647" name="test_frequency" value="" class="form-control">
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['LastTestDate']); ?> :</label>
                            <input type="text" name="last_date" value="" class="form-control datepicker"> 
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['NextTestDate']); ?> :</label>
                            <input type="text" name="next_date" value="" class="form-control datepicker"> 
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Objective']); ?> :</label>
                            <textarea name="objective" id="objective" class="form-control" rows="3" style="max-width:100%;height: auto;"></textarea>
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestSteps']); ?> :</label>
                            <textarea name="test_steps" id="test_steps" class="form-control" rows="3" style="max-width:100%;height:auto;"></textarea>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ApproximateTime']); ?><small class="text-dark ms-1">(<?= $escaper->escapeHtml($lang['minutes']); ?>)</small> :</label>
                            <input type="number" min="0" max="2147483647" name="approximate_time" value="" class="form-control">
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ExpectedResults']); ?> :</label>
                            <textarea name="expected_results" id="expected_results" class="form-control" rows="3" style="max-width:100%;height: auto;"></textarea>
                        </div>
                    </div>
                    <div class="row form-group mb-0">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['Tags']); ?> :</label>
                            <select class="test_tags" readonly name="tags[]" multiple placeholder="<?= $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
                            <div class="tag-max-length-warning"><?= $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" id="update_test" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Update']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

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
        $('#framework--update, #control--update, #test--edit').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>