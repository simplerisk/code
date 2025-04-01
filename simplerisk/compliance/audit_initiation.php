<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'easyui', 'datetimerangepicker', 'CUSTOM:pages/compliance.js'], ['check_compliance' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

    // Check if a framework was updated
    if (isset($_POST['update_framework'])) {

        $framework_id = (int)$_POST['framework_id'];
        $parent       = (int)$_POST['parent'];
        $name         = $escaper->escapeHtml($_POST['framework_name']);
        $descripiton  = $_POST['framework_description'];

        // Check if user has a permission to modify framework
        if (has_permission('modify_frameworks')) {

            if (update_framework($framework_id, $name, $descripiton, $parent)) {

                set_alert(true, "good", $lang['FrameworkUpdated']);

            }

        } else {

            set_alert(true, "bad", $lang['NoModifyFrameworkPermission']);

        }

        refresh();

    }

    // Update if a control was updated
    if (isset($_POST['update_control'])) {

        $control_id = (int)$_POST['control_id'];

        // If user has no permission to modify controls
        if (empty($_SESSION['modify_controls'])) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoModifyControlPermission']));
        
        // Verify value is an integer
        } elseif (is_int($control_id)) {

            $control = array(
                'short_name' => isset($_POST['short_name']) ? $_POST['short_name'] : "",
                'long_name' => isset($_POST['long_name']) ? $_POST['long_name'] : "",
                'description' => isset($_POST['description']) ? $_POST['description'] : "",
                'supplemental_guidance' => isset($_POST['supplemental_guidance']) ? $_POST['supplemental_guidance'] : "",
                'framework_ids' => isset($_POST['frameworks']) ? $_POST['frameworks'] : [],
                'control_owner' => isset($_POST['control_owner']) ? (int)$_POST['control_owner'] : 0,
                'control_class' => isset($_POST['control_class']) ? (int)$_POST['control_class'] : 0,
                'control_phase' => isset($_POST['control_phase']) ? (int)$_POST['control_phase'] : 0,
                'control_number' => isset($_POST['control_number']) ? $_POST['control_number'] : "",
                'control_priority' => isset($_POST['control_priority']) ? (int)$_POST['control_priority'] : 0,
                'family' => isset($_POST['family']) ? (int)$_POST['family'] : 0
            );

            // Update the control
            update_framework_control($control_id, $control);

            // Display an alert
            set_alert(true, "good", "An existing control was updated successfully.");
        
        // We should never get here as we bound the variable as an int
        } else {

            // Display an alert
            set_alert(true, "bad", "The control ID was not a valid value.  Please try again.");

        }
    
        // Refresh current page
        refresh();

    }

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
        refresh($_SESSION['base_url']."/compliance/audit_initiation.php");

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
        $("body").on("click", ".framework-name", function(e){
            e.preventDefault();
            var framework_id = $(this).data("id")
            $.ajax({
                url: BASE_URL + '/api/governance/framework?framework_id=' + framework_id,
                type: 'GET',
                dataType: 'json',
                success : function (res){
                    var data = res.data;
                    
                    // Add parent framework dropdown
                    $.ajax({
                        url: BASE_URL + '/api/governance/selected_parent_frameworks_dropdown?child_id=' + framework_id,
                        type: 'GET',
                        success : function (res){
                            $("#framework--update .parent_frameworks_container").html(res.data.html)
                        }
                    });

                    $("#framework--update [name=framework_id]").val(framework_id);
                    $("#framework--update [name=framework_name]").val(data.framework.name);
                    $("#framework--update [name=framework_description]").val(data.framework.description);
                    $("#framework--update").modal("show");
                    tinyMCE.get('framework_description').setContent(data.framework.description);
                }
            });
        })

        $("body").on("click", ".control-name", function(e){
            e.preventDefault();
            var control_id = $(this).data("id")
            $.ajax({
                url: BASE_URL + '/api/governance/control?control_id=' + control_id,
                type: 'GET',
                dataType: 'json',
                success : function (res){
                    var data = res.data;
                    var control = data.control;
                    
                    var modal = $('#control--update');
                    $('.control_id', modal).val(control_id);
                    $('[name=short_name]', modal).val(control.short_name);
                    $('[name=long_name]', modal).val(control.long_name);
                    $('[name=description]', modal).val(control.description);
                    $('[name=supplemental_guidance]', modal).val(control.supplemental_guidance);
                    
                    $("#frameworks").multiselect('deselectAll', false);
                    $.each(control.framework_ids.split(","), function(i,e){
                        $("#frameworks option[value='" + e + "']").prop("selected", true);
                    });
                    $("#frameworks").multiselect('refresh');
                    
                    $('[name=control_class]', modal).val(Number(control.control_class) ? control.control_class : "");
                    $('[name=control_phase]', modal).val(Number(control.control_phase) ? control.control_phase : "");
                    $('[name=control_owner]', modal).val(Number(control.control_owner) ? control.control_owner : "");
                    $('[name=control_number]', modal).val(control.control_number);
                    $('[name=control_priority]', modal).val(Number(control.control_priority) ? control.control_priority : "");
                    $('[name=family]', modal).val(Number(control.family) ? control.family : "");
                    tinyMCE.get('control_description').setContent(control.description);
                    tinyMCE.get('supplemental_guidance').setContent(control.supplemental_guidance);
                    
                    modal.modal("show");
                }
            });
        })
        
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

                    tinyMCE.get('objective').setContent(data['objective']);
                    tinyMCE.get('test_steps').setContent(data['test_steps']);
                    tinyMCE.get('expected_results').setContent(data['expected_results']);

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
            let select = $('[name=\'tags[]\']', modal).selectize();
            let selectize = select[0].selectize;
            selectize.clear();

            modal.modal("show");

        });

        // Event when clicks Initiate Framework, Control, Test Audit button
        $("#tags--edit").on("click", "[name=continue_add_tags], [name=cancel_add_tags]", function(){
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
        init_minimun_editor('#framework_description');
        init_minimun_editor('#control_description');
        init_minimun_editor('#supplemental_guidance');
        init_minimun_editor('#objective');
        init_minimun_editor('#test_steps');
        init_minimun_editor('#expected_results');
        $("#update_framework").click(function(){
            $("#update-framework-form").submit();
        });
        $("#update_control").click(function(){
            $("#update-control-form").submit();
        });
        $("#update_test").click(function(){
            $("#update-test-form").submit();
        });
    })
</script>

<!-- MODEL WINDOW FOR EDITING FRAMEWORK -->
<div id="framework--update" class="modal fade " tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['FrameworkEditHeader']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="update-framework-form" class="" action="#" method="post" autocomplete="off">
                    <input type="hidden" class="framework_id" name="framework_id" value=""> 
                    <input type="hidden" name="update_framework" value="true"> 
                    <div class="form-group">
                        <label for=""><?= $escaper->escapeHtml($lang['FrameworkName']); ?></label>
                        <input type="text" required name="framework_name" value="" class="form-control" autocomplete="off">

                        <label for=""><?= $escaper->escapeHtml($lang['ParentFramework']); ?></label>
                        <div class="parent_frameworks_container">
                        </div>

                        <label for=""><?= $escaper->escapeHtml($lang['FrameworkDescription']); ?></label>
                        <textarea name="framework_description" id="framework_description" value="" class="form-control" rows="6" style="width:100%;"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" id="update_framework" class="btn btn-danger"><?= $escaper->escapeHtml($lang['Update']); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR UPDATING CONTROL -->
<div id="control--update" class="modal fade " tabindex="-1" aria-labelledby="update" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['ControlEditHeader']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="" id="update-control-form" method="post" autocomplete="off">
                    <input type="hidden" class="control_id" name="control_id" value=""> 
                    <input type="hidden" name="update_control" value="true"> 
                    <div class="row">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlShortName']); ?></label>
                            <input type="text" name="short_name" value="" class="form-control">
                        </div>
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlLongName']); ?></label>
                            <input type="text" name="long_name" value="" class="form-control">
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlDescription']); ?></label>
                            <textarea name="description" value="" id="control_description" class="form-control" rows="6"></textarea>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['SupplementalGuidance']); ?></label>
                            <textarea name="supplemental_guidance" id="supplemental_guidance" value="" class="form-control" rows="6"></textarea>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlOwner']); ?></label>
    <?php 
                            create_dropdown("enabled_users", NULL, "control_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); 
    ?>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlFrameworks']); ?></label>
    <?php 
                            create_multiple_dropdown("frameworks", NULL); 
    ?>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlClass']); ?></label>
    <?php 
                            create_dropdown("control_class", NULL, "control_class", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); 
    ?>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlPhase']); ?></label>
    <?php 
                            create_dropdown("control_phase", NULL, "control_phase", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); 
    ?>
                        </div>
                        <div class="col-sm-12 col-md-4">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlNumber']); ?></label>
                            <input type="text" name="control_number" value="" class="form-control">
                        </div>
                        <div class="col-sm-12 col-md-4">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlPriority']); ?></label>
    <?php 
                            create_dropdown("control_priority", NULL, "control_priority", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); 
    ?>
                        </div>
                        <div class="col-sm-12 col-md-4">
                            <label for=""><?= $escaper->escapeHtml($lang['ControlFamily']); ?></label>
    <?php 
                            create_dropdown("family", NULL, "family", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); 
    ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" id="update_control" class="btn btn-danger"><?= $escaper->escapeHtml($lang['Update']); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR EDITING TEST -->
<div id="test--edit" class="modal fade " tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['TestEditHeader']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="" id="update-test-form" method="post" autocomplete="off">
                    <input type="hidden" name="test_id" value="">
                    <input type="hidden" name="update_test" value="true">
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestName']); ?></label>
                            <input type="text" name="name" value="" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Tester']); ?></label>
    <?php 
                            create_dropdown("enabled_users", NULL, "tester", false, false, false); 
    ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="" style="width:100%"><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
    <?php 
                            create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders"); 
    ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for=""  style="width:100%"><?= $escaper->escapeHtml($lang['Teams']); ?></label>
    <?php 
                            create_multiple_dropdown("team"); 
    ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestFrequency']); ?></label>
                            <input type="number" name="test_frequency" value="" class="form-control">
                            <span class="white-labels">(<?= $escaper->escapeHtml($lang['days']); ?>)</span>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['LastTestDate']); ?></label>
                            <input type="text" name="last_date" value="" class="form-control datepicker"> 
                        </div>
                        <div class="col-sm-12 col-md-12">
                            <label for=""><?= $escaper->escapeHtml($lang['NextTestDate']); ?></label>
                            <input type="text" name="next_date" value="" class="form-control datepicker"> 
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Objective']); ?></label>
                            <textarea name="objective" id="objective" class="form-control" rows="6" style="width:100%;"></textarea>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestSteps']); ?></label>
                            <textarea name="test_steps" id="test_steps" class="form-control" rows="6" style="width:100%;"></textarea>
                        </div>
                        <div class="col-sm-12 col-md-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ApproximateTime']); ?></label>
                            <input type="number" name="approximate_time" value="" class="form-control"> <span class="white-labels">(<?= $escaper->escapeHtml($lang['minutes']); ?>)</span>
                        </div>
                        <div class="col-sm-12 col-md-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ExpectedResults']); ?></label>
                            <textarea name="expected_results" id="expected_results" class="form-control" rows="6"></textarea>
                        </div>
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['Tags']); ?></label>
                            <select class="test_tags" readonly name="tags[]" multiple placeholder="<?= $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
                            <div class="tag-max-length-warning" style="margin-top:-10px"><?= $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
               <button type="button" name="cancel_add_tags" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
               <button type="button" id="update_test" class="btn btn-danger"><?= $escaper->escapeHtml($lang['Update']); ?></button>
            </div>
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
                        <label for=""><?= $escaper->escapeHtml($lang['Tags']); ?></label>
                        <select class="test_tags" readonly name="tags[]" multiple placeholder="<?= $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
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
<script>
    $( document ).ready(function() {
        $("#additional_stakeholders").multiselect();

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