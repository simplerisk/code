<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/assets.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../includes/governance.php'));
require_once(realpath(__DIR__ . '/../includes/compliance.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_compliance" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// Check if a framework was updated
if (isset($_POST['update_framework'])) {

    $framework_id = (int)$_POST['framework_id'];
    $parent       = (int)$_POST['parent'];
    $name         = $escaper->escapeHtml($_POST['framework_name']);
    $descripiton  = $escaper->escapeHtml($_POST['framework_description']);

    // Check if user has a permission to modify framework
    if(has_permission('modify_frameworks')){
        if (update_framework($framework_id, $name, $descripiton, $parent)) {
            set_alert(true, "good", $lang['FrameworkUpdated']);
        }
    } else {
        set_alert(true, "bad", $lang['NoModifyFrameworkPermission']);
    }

    refresh();
}

// Update if a control was updated
if (isset($_POST['update_control']))
{
  $control_id = (int)$_POST['control_id'];

  // If user has no permission to modify controls
  if(empty($_SESSION['modify_controls']))
  {
      // Display an alert
      set_alert(true, "bad", $escaper->escapeHtml($lang['NoModifyControlPermission']));
  }
  // Verify value is an integer
  elseif (is_int($control_id))
  {
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
  }
  // We should never get here as we bound the variable as an int
  else
  {
    // Display an alert
    set_alert(true, "bad", "The control ID was not a valid value.  Please try again.");
  }
  
  // Refresh current page
  refresh();
}

// Check if editing test
if(isset($_POST['update_test'])){
    $test_id        = (int)$_POST['test_id'];
    $tester         = (int)$_POST['tester'];
    $additional_stakeholders = empty($_POST['additional_stakeholders']) ? "" : implode(",", $_POST['additional_stakeholders']);
    $teams          = isset($_POST['team']) ? $_POST['team'] : [];
    $test_frequency = (int)$_POST['test_frequency'];
    $last_date      = get_standard_date_from_default_format($_POST['last_date']);
    $next_date      = get_standard_date_from_default_format($_POST['next_date']);
    $name           = $_POST['name'];
    $objective      = $_POST['objective'];
    $test_steps     = $_POST['test_steps'];
    $approximate_time = (int)($_POST['approximate_time']) ? $_POST['approximate_time'] : 0;
    $expected_results = $_POST['expected_results'];
    $tags           = empty($_POST['tags']) ? [] : $_POST['tags'];
    
    // Update a framework control test
    update_framework_control_test($test_id, $tester, $test_frequency, $name, $objective, $test_steps, $approximate_time, $expected_results, $last_date, $next_date, false, $additional_stakeholders, $teams, $tags);
    
    set_alert(true, "good", $escaper->escapeHtml($lang['TestSuccessUpdated']));
    
    // Refresh current page
    refresh();
}

// Check if initiate framework or control or test
if(isset($_GET['initiate']) ){
    $id     = (int)$_GET['id'];
    $type   = $escaper->escapeHtml($_GET['type']);
    
    if($name = initiate_framework_control_tests($type, $id)){
        if($type == 'framework'){
            set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedAllTestsUnderFramework', ['framework' => $name])));
        }elseif($type == 'control'){
            set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedAllTestsUnderControl', ['control' => $name])));
        }elseif($type == 'test'){
            set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedTest', ['test' => $name])));
        }
    }
    
    // Go back to old page
    refresh($_SESSION['base_url']."/compliance/audit_initiation.php");
}


?>
<!doctype html>
<html>

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);
?>
    <script src="../js/jquery.easyui.min.js?<?php echo current_version("app"); ?>"></script>
<?php
        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
    <script src="../js/pages/compliance.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>

    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

    <link rel="stylesheet" href="../css/easyui.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
    
    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
    <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>
    <style>
        #framework--update .modal-header, #control--update .modal-header, #test--edit .modal-header {
            color: #ffffff;
        }
    </style>
</head>

<body>

    <?php
        view_top_menu("Compliance");

        // Get any alert messages
        get_alert();
    ?>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_compliance_menu("InitialAudits"); ?>
            </div>
            <div class="span9 compliance-content-container content-margin-height">                
                <div class="row-fluid">
                    <div class="span12">
                        <div class="custom-treegrid-container" id="initiate-audits">
                            <?php display_initiate_audits(); ?>
                        </div>
                    </div>
                </div>
                <br>
            </div>
        </div>
    </div>
    
    <script type="">
        $(document).ready(function(){
            $( window ).resize(function() {
                $('#initiate_audit_treegrid').datagrid('resize',{
                  width: $("#initiate-audits").width()
                });
            });
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
                        $("#framework--update").modal();
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
                        
                        modal.modal();
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
                        $(".datepicker" , modal).datepicker();
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
                        
                        modal.modal();
                    }
                })
            })
            
            // Event when clicks Initiate Framework, Control, Test Audit button
            $('body').on("click", ".initiate-framework-audit-btn, .initiate-control-audit-btn, .initiate-test-btn", function(){
                if($(this).hasClass("initiate-framework-audit-btn")){
                    var type = "framework";
                }else if($(this).hasClass("initiate-control-audit-btn")){
                    var type = "control";
                }else if($(this).hasClass("initiate-test-btn")){
                    var type = "test";
                }
                var modal = $('#tags--edit');
                $('[name=audit_type]', modal).val(type);
                $('[name=id]', modal).val($(this).data('id'));
                modal.modal();
                return;
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
            })

        })
    </script>

    <!-- MODEL WINDOW FOR EDITING FRAMEWORK -->
    <div id="framework--update" class="modal hide" tabindex="-1" role="dialog" aria-hidden="true">
        <form class="" action="#" method="post" autocomplete="off">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['FrameworkEditHeader']); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" class="framework_id" name="framework_id" value=""> 
                <div class="form-group">
                    <label for=""><?php echo $escaper->escapeHtml($lang['FrameworkName']); ?></label>
                    <input type="text" required name="framework_name" value="" class="form-control" autocomplete="off">

                    <label for=""><?php echo $escaper->escapeHtml($lang['ParentFramework']); ?></label>
                    <div class="parent_frameworks_container">
                    </div>

                    <label for=""><?php echo $escaper->escapeHtml($lang['FrameworkDescription']); ?></label>
                    <textarea name="framework_description" value="" class="form-control" rows="6" style="width:100%;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" name="update_framework" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
            </div>
        </form>
    </div>

    <!-- MODEL WINDOW FOR UPDATING CONTROL -->
    <div id="control--update" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="control--update" aria-hidden="true">
      <form class="" id="control--new" action="#controls-tab" method="post" autocomplete="off">
        <input type="hidden" class="control_id" name="control_id" value=""> 
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['ControlEditHeader']); ?></h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for=""><?php echo $escaper->escapeHtml($lang['ControlShortName']); ?></label>
            <input type="text" name="short_name" value="" class="form-control">
            
            <label for=""><?php echo $escaper->escapeHtml($lang['ControlLongName']); ?></label>
            <input type="text" name="long_name" value="" class="form-control">
            
            <label for=""><?php echo $escaper->escapeHtml($lang['ControlDescription']); ?></label>
            <textarea name="description" value="" class="form-control" rows="6" style="width:100%;"></textarea>
            
            <label for=""><?php echo $escaper->escapeHtml($lang['SupplementalGuidance']); ?></label>
            <textarea name="supplemental_guidance" value="" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['ControlOwner']); ?></label>
            <?php create_dropdown("enabled_users", NULL, "control_owner", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['ControlFrameworks']); ?></label>
            <?php create_multiple_dropdown("frameworks", NULL); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['ControlClass']); ?></label>
            <?php create_dropdown("control_class", NULL, "control_class", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['ControlPhase']); ?></label>
            <?php create_dropdown("control_phase", NULL, "control_phase", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['ControlNumber']); ?></label>
            <input type="text" name="control_number" value="" class="form-control">

            <label for=""><?php echo $escaper->escapeHtml($lang['ControlPriority']); ?></label>
            <?php create_dropdown("control_priority", NULL, "control_priority", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['ControlFamily']); ?></label>
            <?php create_dropdown("family", NULL, "family", true, false, false, "", $escaper->escapeHtml($lang['Unassigned'])); ?>
          </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="update_control" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
        </div>
      </form>
    </div>
    
    <!-- MODEL WINDOW FOR EDITING TEST -->
    <div id="test--edit" class="modal hide" tabindex="-1" role="dialog" aria-hidden="true">
      <form class="" id="test-edit-form" method="post" autocomplete="off">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['TestEditHeader']); ?></h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for=""><?php echo $escaper->escapeHtml($lang['TestName']); ?></label>
            <input type="text" name="name" value="" class="form-control">

            <label for=""><?php echo $escaper->escapeHtml($lang['Tester']); ?></label>
            <?php create_dropdown("enabled_users", NULL, "tester", false, false, false); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
            <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['Teams']); ?></label>
            <?php create_multiple_dropdown("team"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestFrequency']); ?></label>
            <input type="number" name="test_frequency" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span>
            
            <label for=""><?php echo $escaper->escapeHtml($lang['LastTestDate']); ?></label>
            <input type="text" name="last_date" value="" class="form-control datepicker"> 
            
            <label for=""><?php echo $escaper->escapeHtml($lang['NextTestDate']); ?></label>
            <input type="text" name="next_date" value="" class="form-control datepicker"> 
            
            <label for=""><?php echo $escaper->escapeHtml($lang['Objective']); ?></label>
            <textarea name="objective" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestSteps']); ?></label>
            <textarea name="test_steps" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['ApproximateTime']); ?></label>
            <input type="number" name="approximate_time" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['minutes']); ?>)</span>

            <label for=""><?php echo $escaper->escapeHtml($lang['ExpectedResults']); ?></label>
            <textarea name="expected_results" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['Tags']); ?></label>
            <select class="test_tags" readonly name="tags[]" multiple placeholder="<?php echo $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
            <div class="tag-max-length-warning" style="margin-top:-10px"><?php echo $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></div>

            <input type="hidden" name="test_id" value="">

          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
          <button type="submit" name="update_test" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
        </div>
      </form>
    </div>

    <!-- MODEL WINDOW FOR ADD TAGS TO TEST -->
    <div id="tags--edit" class="modal hide" tabindex="-1" role="dialog" aria-hidden="true">
      <form class="" id="tags-edit-form" method="post" autocomplete="off">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['AddTagsToTestAudit']); ?></h4>
        </div>
        <div class="modal-body">
          <div class="form-group" style="margin-bottom: 100px;">
            <label for=""><?php echo $escaper->escapeHtml($lang['Tags']); ?></label>
            <select class="test_tags" readonly name="tags[]" multiple placeholder="<?php echo $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
            <div class="tag-max-length-warning" style="margin-top:-10px"><?php echo $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></div>

            <input type="hidden" name="audit_type" value="">
            <input type="hidden" name="id" value="">
          </div>
        </div>
        <div class="modal-footer">
          <button name="cancel_add_tags" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
          <button name="continue_add_tags" class="btn btn-danger" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Continue']); ?></button>
        </div>
      </form>
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
    <?php display_set_default_date_format_script(); ?>
</body>
</html>
