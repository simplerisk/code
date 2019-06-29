<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../includes/governance.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

if (!isset($_SESSION))
{
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    session_name('SimpleRisk');
    session_start();
}

// Include the language file
require_once(language_file());

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
    set_unauthenticated_redirect();
    header("Location: ../index.php");
    exit(0);
}

// Include the CSRF-magic library
// Make sure it's called after the session is properly setup
include_csrf_magic();

// Enforce that the user has access to governance
enforce_permission_governance();

// Check if a new framework was submitted
if (isset($_POST['add_framework']))
{
  $name         = $_POST['new_framework'];
  $descripiton  = $_POST['new_framework_description'];
  $parent       = $_POST['parent'];

  // Check if the framework name is null
  if (isset($name) && $name == "")
  {
    // Display an alert
    set_alert(true, "bad", "The framework name cannot be empty.");
  }
  // Otherwise
  else
  {
    if(empty($_SESSION['add_new_frameworks']))
    {
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoAddFrameworkPermission']));
    }
    // Insert a new framework up to 100 chars
    elseif(add_framework($name, $descripiton, $parent)){
        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['FrameworkAdded']));
    }else{
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['FrameworkNameExist']));
    }

  }
  refresh();
}

// Check if a framework was updated
if (isset($_POST['update_framework']))
{
  $framework_id = (int)$_POST['framework_id'];
  $parent       = (int)$_POST['parent'];
  $name         = $_POST['framework_name'];
  $descripiton  = $_POST['framework_description'];

  // Check if the framework name is null
  if (isset($name) && $name == "")
  {
    // Display an alert
    set_alert(true, "bad", "The framework name cannot be empty.");
  }
  // Otherwise
  else
  {
    // Check if user has a permission to modify framework
    if(empty($_SESSION['modify_frameworks'])){
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoModifyFrameworkPermission']));
    }
    // Insert a new framework up to 100 chars
    elseif(update_framework($framework_id, $name, $descripiton, $parent))
    {
        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['FrameworkUpdated']));
    }
    else{
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['FrameworkNameExist']));
    }

  }
  refresh();
}

// Delete if a new framework was submitted
if (isset($_POST['delete_framework']))
{
  $value = (int)$_POST['framework_id'];

  // Verify value is an integer
  if (is_int($value))
  {
    // If user has no permission for modify frameworks
    if(empty($_SESSION['delete_frameworks']))
    {
      set_alert(true, "bad", $escaper->escapeHtml($lang['NoDeleteFrameworkPermission']));
    }
    // If the framework ID is 0 (ie. Unassigned Risks)
    elseif ($value == 0)
    {
      // Display an alert
      set_alert(true, "bad", "You cannot delete the Unassigned framework.  Sorry.");
    }
    else
    {
      // Delete the framework
      delete_frameworks($value);

      // Display an alert
      set_alert(true, "good", "An existing framework was deleted successfully.");
    }
  }
  // We should never get here as we bound the variable as an int
  else
  {
    // Display an alert
    set_alert(true, "bad", "The framework ID was not a valid value.  Please try again.");
  }
  
  refresh();
}

// Check if a new control was submitted
if (isset($_POST['add_control']))
{
  $control = array(
    'short_name' => isset($_POST['short_name']) ? $_POST['short_name'] : "",
    'long_name' => isset($_POST['long_name']) ? $_POST['long_name'] : "",
    'description' => isset($_POST['description']) ? $_POST['description'] : "",
    'supplemental_guidance' => isset($_POST['supplemental_guidance']) ? $_POST['supplemental_guidance'] : "",
    'framework_ids' => isset($_POST['add_framework_ids']) ? $_POST['add_framework_ids'] : "",
    'control_owner' => isset($_POST['control_owner']) ? (int)$_POST['control_owner'] : 0,
    'control_class' => isset($_POST['control_class']) ? (int)$_POST['control_class'] : 0,
    'control_phase' => isset($_POST['control_phase']) ? (int)$_POST['control_phase'] : 0,
    'control_number' => isset($_POST['control_number']) ? $_POST['control_number'] : "",
    'control_priority' => isset($_POST['control_priority']) ? (int)$_POST['control_priority'] : 0,
    'family' => isset($_POST['family']) ? (int)$_POST['family'] : 0,
    'mitigation_percent' => (isset($_POST['mitigation_percent']) && $_POST['mitigation_percent'] >= 0 && $_POST['mitigation_percent'] <= 100) ? (int)$_POST['mitigation_percent'] : 0
  );

  // Check if the control name is null
  if (!$control['short_name'])
  {
    // Display an alert
    set_alert(true, "bad", "The control name cannot be empty.");
  }
  // Otherwise
  else
  {
    // If user has no permission for add new controls
    if(empty($_SESSION['add_new_controls']))
    {
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoAddControlPermission']));
    }
    // Insert a new control up to 100 chars
    elseif(add_framework_control($control))
    {
        // Display an alert
        set_alert(true, "good", "A new control was added successfully.");
    }
    else
    {
        // Display an alert
        set_alert(true, "bad", "The control already exists.");
    }
  }
  
  // Refresh current page
  refresh();
}

// Delete if a delete control was submitted
if (isset($_POST['delete_control']))
{
  $value = (int)$_POST['control_id'];

  // If user has no permission for delete controls
  if(empty($_SESSION['delete_controls']))
  {
      // Display an alert
      set_alert(true, "bad", $escaper->escapeHtml($lang['NoDeleteControlPermission']));
  }
  // Verify value is an integer
  elseif (is_int($value))
  {
      // Delete the control
      delete_framework_control($value);

      // Display an alert
      set_alert(true, "good", "An existing control was deleted successfully.");
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

// If delete controls were submitted
if (isset($_POST['delete_controls']))
{
  $control_ids = $_POST['control_ids'];

  // If user has no permission for delete controls
  if(empty($_SESSION['delete_controls']))
  {
      // Display an alert
      set_alert(true, "bad", $escaper->escapeHtml($lang['NoDeleteControlPermission']));
  }
  // Verify control ids for deleting was submitted
  elseif (is_array($control_ids))
  {
      foreach($control_ids as $control_id){
          // Delete the control
          delete_framework_control($control_id);
      }

      // Display an alert
      set_alert(true, "good", "An selected controls were deleted successfully.");
  }
  // We should never get here as we bound the variable as an int
  else
  {
    // Display an alert
    set_alert(true, "bad", "Nothing controls for deleting were selected.");
  }
  
  // Refresh current page
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
        'framework_ids' => isset($_POST['frameworks']) ? $_POST['frameworks'] : "",
        'control_owner' => isset($_POST['control_owner']) ? (int)$_POST['control_owner'] : 0,
        'control_class' => isset($_POST['control_class']) ? (int)$_POST['control_class'] : 0,
        'control_phase' => isset($_POST['control_phase']) ? (int)$_POST['control_phase'] : 0,
        'control_number' => isset($_POST['control_number']) ? $_POST['control_number'] : "",
        'control_priority' => isset($_POST['control_priority']) ? (int)$_POST['control_priority'] : 0,
        'family' => isset($_POST['family']) ? (int)$_POST['family'] : 0,
        'mitigation_percent' => (isset($_POST['mitigation_percent']) && $_POST['mitigation_percent'] >= 0 && $_POST['mitigation_percent'] <= 100) ? (int)$_POST['mitigation_percent'] : 0
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

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
  <script src="../js/jquery.min.js"></script>
  <script src="../js/jquery.easyui.min.js"></script>
  <script src="../js/jquery-ui.min.js"></script>
  <script src="../js/jquery.draggable.js"></script>
  <script src="../js/jquery.droppable.js"></script>
  <script src="../js/treegrid-dnd.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/bootstrap-multiselect.js"></script>
  <script src="../js/jquery.dataTables.js"></script>
  <script src="../js/pages/governance.js"></script>
  <script src="../js/common.js"></script>

  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/easyui.css">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  <link rel="stylesheet" href="../css/jquery.dataTables.css">
  <link rel="stylesheet" href="../css/bootstrap-multiselect.css">
  <link rel="stylesheet" href="../css/prioritize.css">
  <link rel="stylesheet" href="../css/divshot-util.css">
  <link rel="stylesheet" href="../css/divshot-canvas.css">
  <link rel="stylesheet" href="../css/display.css">
  <link rel="stylesheet" href="../css/style.css">

  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">

  <?php
      setup_alert_requirements("..");
  ?>
  <?php
    // Get the frameworks
    $frameworks = get_frameworks();

    // Get the total number of frameworks
    $count = count($frameworks);

    // Initialize the counter
    $counter = 1;

  ?>
    <script>
        // Set current mouse position
        var mouseX, mouseY;
        $(document).mousemove(function(e) {
            mouseX = e.pageX;
            mouseY = e.pageY;
        }).mouseover(); 

        $(document).ready(function(){
            var $tabs = $( "#frameworks-tab-content, #controls-tab-content" ).tabs({
                activate: function(event, ui){
                    fixTreeGridCollapsableColumn();
                    $(".framework-table").treegrid('resize');
                }
            })
            
            $("#framework-add-btn").click(function(){
                $.ajax({
                    url: BASE_URL + '/api/governance/parent_frameworks_dropdown?status=1',
                    type: 'GET',
                    success : function (res){
                        $("#framework--add .parent_frameworks_container").html(res.data.html)
                    }
                });
            })
            
            $("body").on("click", ".framework-block--edit", function(){
                var framework_id = $(this).data("id");
                $.ajax({
                    url: BASE_URL + '/api/governance/framework?framework_id=' + framework_id,
                    type: 'GET',
                    success : function (res){
                        var data = res.data;
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
            
            var tabContentId = document.location.hash ? document.location.hash : "#frameworks-tab";
            tabContentId += "-content";
            $(".tab-show").removeClass("selected");
            
            $(".tab-show[data-content='"+ tabContentId +"']").addClass("selected");
            $(".tab-data").addClass("hide");
            $(tabContentId).removeClass("hide");
            $(".framework-table").treegrid('resize');

            $(function(){
                // Create multiselect instance in Updating modal of framework control 
                $("#frameworks").multiselect({
                    allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllFrameworks']); ?>',
                    includeSelectAllOption: true
                });

                // Create multiselect instance in Adding modal of framework control 
                $("#add_framework_ids").multiselect({
                    allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllFrameworks']); ?>',
                    includeSelectAllOption: true
                });

                $("#filter_by_control_owner").multiselect({
                    allSelectedText: '<?php echo $escaper->escapeHtml($lang['ALL']); ?>',
                    includeSelectAllOption: true
                });

                $("#filter_by_control_class").multiselect({
                    allSelectedText: '<?php echo $escaper->escapeHtml($lang['ALL']); ?>',
                    includeSelectAllOption: true
                });

                $("#filter_by_control_phase").multiselect({
                    allSelectedText: '<?php echo $escaper->escapeHtml($lang['ALL']); ?>',
                    includeSelectAllOption: true
                });

                $("#filter_by_control_family").multiselect({
                    allSelectedText: '<?php echo $escaper->escapeHtml($lang['ALL']); ?>',
                    includeSelectAllOption: true
                });

                $("#filter_by_control_framework").multiselect({
                    allSelectedText: '<?php echo $escaper->escapeHtml($lang['ALL']); ?>',
                    includeSelectAllOption: true
                });

                $("#filter_by_control_priority").multiselect({
                    allSelectedText: '<?php echo $escaper->escapeHtml($lang['ALL']); ?>',
                    includeSelectAllOption: true
                });
            });
        
        });
    </script>
</head>

<body>

       
  <?php
      view_top_menu("Governance");

      // Get any alert messages
      get_alert();
  ?>

  <div class="tabs new-tabs planning-tabs">
    <div class="container-fluid">

      <div class="row-fluid">

        <div class="span3"> </div>
        <div class="span9">

          <div class="tab-append">
            <div class="tab selected form-tab tab-show" data-content="#frameworks-tab-content"><div><span><?php echo $escaper->escapeHtml($lang['Frameworks']); ?></span></div></div>
            <div class="tab form-tab tab-show controls-tab" data-content="#controls-tab-content"><div><span><?php echo $escaper->escapehtml($lang['Controls']); ?></span></div></div>
          </div>

        </div>

      </div>

    </div>
  </div>

  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_governance_menu("DefineControlFrameworks"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <div class="span12">
            <!--  Frameworks container Begin -->
            <div id="frameworks-tab-content" class="plan-projects tab-data hide">

              <div class="status-tabs" >

                <a href="#framework--add" id="framework-add-btn" role="button" data-toggle="modal" class="project--add"><i class="fa fa-plus"></i></a>

                <ul class="clearfix tabs-nav">
                  <li><a href="#active-frameworks" class="status" data-status="1"><?php echo $escaper->escapeHtml($lang['ActiveFrameworks']); ?> (<span id="active-frameworks-count"><?php get_frameworks_count(1) ?></span>)</a></li>
                  <li><a href="#inactive-frameworks" class="status" data-status="2"><?php echo $escaper->escapeHtml($lang['InactiveFrameworks']); ?> (<span id="inactive-frameworks-count"><?php get_frameworks_count(2) ?></span>)</a></li>
                </ul>

                  <div id="active-frameworks" class="custom-treegrid-container">
                        <?php get_framework_tabs(1) ?>
                  </div>
                  <div id="inactive-frameworks" class="custom-treegrid-container">
                        <?php get_framework_tabs(2) ?>
                  </div>
              </div> <!-- status-tabs -->

            </div>
            <!-- Frameworks container Ends -->

            <!--  Controls container Begin -->
            <div id="controls-tab-content" class="tab-data hide">
                <div class="row-fluid">
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlClass']); ?>:</h4>
                            <select id="filter_by_control_class" class="" multiple="multiple">
                                <?php 
                                    echo "<option selected value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                    $options = getAvailableControlClassList();
                                    is_array($options) || $options = array();
                                    foreach($options as $option){
                                        echo "<option selected value=\"".$escaper->escapeHtml($option['value'])."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                    } 
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlPhase']); ?>:</h4>
                            <select id="filter_by_control_phase" class="" multiple="multiple">
                                <?php 
                                    echo "<option selected value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                    $options = getAvailableControlPhaseList();
                                    is_array($options) || $options = array();
                                    foreach($options as $option){
                                        echo "<option selected value=\"".$escaper->escapeHtml($option['value'])."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                    } 
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlFamily']); ?>:</h4>
                            <select id="filter_by_control_family" class="" multiple="multiple">
                                <?php 
                                    echo "<option selected value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                    $options = getAvailableControlFamilyList();  
                                    is_array($options) || $options = array();
                                    foreach($options as $option){
                                        echo "<option selected value=\"".$escaper->escapeHtml($option['value'])."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                    } 
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row-fluid">
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlOwner']); ?>:</h4>
                            <select id="filter_by_control_owner" class="form-field form-control" multiple="multiple">
                                <?php 
                                    echo "<option selected value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                    $options = getAvailableControlOwnerList();  
                                    is_array($options) || $options = array();
                                    foreach($options as $option){
                                        echo "<option selected value=\"".$escaper->escapeHtml($option['value'])."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlFramework']); ?>:</h4>
                            <select id="filter_by_control_framework" class="form-field form-control" multiple="multiple">
                                <?php 
                                    echo "<option selected value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                    $options = getAvailableControlFrameworkList();  
                                    is_array($options) || $options = array();
                                    foreach($options as $option){
                                        echo "<option selected value=\"".$escaper->escapeHtml($option['value'])."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                    } 
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlPriority']); ?>:</h4>
                            <select id="filter_by_control_priority" class="form-field form-control" multiple="multiple">
                                <?php 
                                    echo "<option selected value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                    $options = getAvailableControlPriorityList();
                                    is_array($options) || $options = array();
                                    foreach($options as $option){
                                        echo "<option selected value=\"".$escaper->escapeHtml($option['value'])."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row-fluid">
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['FilterByText']); ?>:</h4>
                            <input type="text" class="form-control" id="filter_by_control_text">
                        </div>
                    </div>
                </div>
                
                <form action="" name="controls_form" method="POST" id="controls-form">
                    <div class="status-tabs" >
                        <a href="#control--add" role="button" data-toggle="modal" class="control--add"><i class="fa fa-plus"></i></a>
                        <ul class="clearfix tabs-nav">
                            <li><a href="#active-controls" class="status" data-status="1"><?php echo $escaper->escapeHtml($lang['Controls']); ?> <span id="controls_count"></span></a></li>
                        </ul>
                        <input type="hidden" name="delete_controls" value="1">
                        <button type="submit" id="delete-controls-btn" class="btn"><?php echo $escaper->escapeHtml($lang['DeleteControls']) ?></button>
                    </div> <!-- status-tabs -->

                    <table id="active-controls" class="" width="100%">
                        <thead style='display:none;'>
                            <tr>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </form>
            
            </div>
            <!-- Controls container Ends -->
            
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <script type="">
    $(document).ready(function(){
        $("body").on("click", "#active-controls .checkbox-in-div input[type=checkbox]", function(){
            if(this.checked)
                $(this).closest(".control-block--header").addClass("selected-background");
            else
                $(this).closest(".control-block--header").removeClass("selected-background");
        })
        
        $("#controls-form").submit(function(){
            if($("#active-controls .checkbox-in-div input[type=checkbox]:checked").length > 0){
                confirm("<?php echo $escaper->escapeHtml($lang["AreYouSureYouWantToDeleteControls"]); ?>", "submit_controls_form()");
                return false;
            }else{
                alert("<?php echo $escaper->escapeHtml($lang['SelectControlsToDelete']); ?>")
                return false;
            }
        })
    })
    function submit_controls_form(){
        document.controls_form.submit();
    }
  </script>
  
    <!-- MODEL WINDOW FOR ADDING FRAMEWORK -->
    <div id="framework--add" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="framework--add" aria-hidden="true">
      <div class="modal-body">

        <form class="" id="framework--new" action="#" method="post" autocomplete="off">
          <div class="form-group">
            <label for=""><?php echo $escaper->escapeHtml($lang['NewFrameworkName']); ?></label>
            <input type="text" required name="new_framework" id="new_framework" value="" class="form-control" autocomplete="off">
            <label for=""><?php echo $escaper->escapeHtml($lang['ParentFramework']); ?></label>
            <div class="parent_frameworks_container">
            </div>
            
            <label for=""><?php echo $escaper->escapeHtml($lang['NewFrameworkDescription']); ?></label>
            <textarea name="new_framework_description" id="new_framework_description" value="" class="form-control" rows="6" style="width:100%;"></textarea>
          </div>
          
          <div class="form-group text-right">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="add_framework" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
          </div>
        </form>

      </div>
    </div>

    <!-- MODEL WINDOW FOR EDITING FRAMEWORK -->
    <div id="framework--update" class="modal hide fade" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-body">

        <form class="" action="#" method="post" autocomplete="off">
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

            <div class="form-group text-right">
                <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" name="update_framework" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
            </div>
        </form>

      </div>
    </div>

    <!-- MODEL WINDOW FOR PROJECT DELETE CONFIRM -->
    <div id="framework--delete" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="framework-delete-form" aria-hidden="true">
      <div class="modal-body">

        <form class="" id="framework-delete-form" action="" method="post">
          <div class="form-group text-center">
            <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisFramework']); ?></label>
            <input type="hidden" class="delete-id" name="framework_id" value="" />
          </div>

          <div class="form-group text-center project-delete-actions">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="delete_framework" class="delete_project btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
          </div>
        </form>

      </div>
    </div>

    <!-- MODEL WINDOW FOR ADDING CONTROL -->
    <div id="control--add" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="control--add" aria-hidden="true">
      <div class="modal-body">

        <form class="" id="control--new" action="#controls-tab" method="post" autocomplete="off">
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
            <?php create_multiple_dropdown("frameworks", NULL, "add_framework_ids"); ?>

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

            <label for=""><?php echo $escaper->escapeHtml($lang['MitigationPercent']); ?></label>
            <input type="number" min="0" max="100" name="mitigation_percent" value="" class="form-control">
          </div>
          
          <div class="form-group text-right">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="add_control" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
          </div>
        </form>

      </div>
    </div>

    <!-- MODEL WINDOW FOR UPDATING CONTROL -->
    <div id="control--update" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="control--update" aria-hidden="true">
      <div class="modal-body">
        <form class="" id="update-control-form" action="#controls-tab" method="post" autocomplete="off">
            <input type="hidden" class="control_id" name="control_id" value=""> 
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

            <label for=""><?php echo $escaper->escapeHtml($lang['MitigationPercent']); ?></label>
            <input type="number" min="0" max="100" name="mitigation_percent" value="" class="form-control">
          </div>
          
          <div class="form-group text-right">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="update_control" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
          </div>
        </form>

      </div>
    </div>

    <!-- MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    <div id="control--delete" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="control--delete" aria-hidden="true">
      <div class="modal-body">

        <form class="" id="control--delete" action="" method="post">
          <div class="form-group text-center">
            <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisControl']); ?></label>
            <input type="hidden" class="delete-id" name="control_id" value="" />
          </div>

          <div class="form-group text-center control-delete-actions">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="delete_control" class="delete_control btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
          </div>
        </form>

      </div>
    </div>

    <?php display_set_default_date_format_script(); ?>
</body>

</html>
