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
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_governance" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// Check if a new framework was submitted
if (isset($_POST['add_framework']))
{
  $name         = get_param("POST", "framework_name", "");
  $descripiton  = get_param("POST", "framework_description", "");
  $parent       = get_param("POST", "parent", "");

  // Check if the framework name is null
  if (isset($name) && $name == "")
  {
    // Display an alert
    set_alert(true, "bad", $escaper->escapeHtml($lang["FrameworkNameCantBeEmpty."]));
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
if (isset($_POST['update_framework'])) {
    $framework_id = get_param("POST", "framework_id", "");
    $name         = get_param("POST", "framework_name", "");
    $descripiton  = get_param("POST", "framework_description", "");
    $parent       = get_param("POST", "parent", "");

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
      set_alert(true, "bad", $lang['NoDeleteFrameworkPermission']);
    }
    // If the framework ID is 0 (ie. Unassigned Risks)
    elseif ($value == 0)
    {
      // Display an alert
        set_alert(true, "bad", $lang['CantDeleteUnassignedFramework']);
    }
    
    elseif ((complianceforge_scf_extra() ? (int)get_setting('complianceforge_scf_framework_id', 0) : 0) === $value) {
        set_alert(true, "bad", $lang['CantDeleteComplianceForgeSCFFramework']);
    }
    else
    {
      // If the ucf extra is enabled
      if (ucf_extra())
      {
          // Include the ucf extra
          require_once(realpath(__DIR__ . '/../extras/ucf/index.php'));

          // Disable the UCF framework
          disable_ucf_framework($value);
      }

      // If the complianceforge_scf extra is enabled
      if (complianceforge_scf_extra())
      {
          // Include the ucf extra
          require_once(realpath(__DIR__ . '/../extras/complianceforgescf/index.php'));

          // Disable the UCF framework
          disable_scf_frameworks($value);
      }

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

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
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
?>
  <script src="../js/jquery.draggable.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/jquery.droppable.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/treegrid-dnd.js?<?php echo current_version("app"); ?>"></script>
  <?php display_bootstrap_javascript(); ?>
  <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/pages/governance.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/common.js?<?php echo current_version("app"); ?>"></script>

  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/easyui.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/prioritize.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/style.css?<?php echo current_version("app"); ?>">

  <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

  <?php
      setup_favicon("..");
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
  <style>
    .control-content [class*=span]{line-height: 33px;}
    .control-content .top, .control-content .bottom{margin-left: 22px;}
    .control-content .top .span8, .control-content .bottom .span8{margin-left: 13px;}
  </style>
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
                $("#framework--update input").val();
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
                        if(data.framework.custom_values){
                          var custom_values = data.framework.custom_values;
                          for (var i=0; i<custom_values.length; i++) {
                            var field_id = custom_values[i].field_id;
                            var field_value = custom_values[i].value;
                            $("#framework--update [name='custom_field["+field_id+"]']").val(field_value);
                          }
                        }
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

            $('select[multiple]').multiselect({
                allSelectedText: '<?php echo $escaper->escapeHtml($lang['ALL']); ?>',
                enableFiltering: true,
                maxHeight: 250,
                buttonWidth: '100%',
                includeSelectAllOption: true,
                onDropdownHide: function(){
                    if(this.$select.attr('id') == 'filter_by_control_framework'){
                        rebuild_filters();
                    } else {
                        controlDatatable.draw();
                    }
                }
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
                  <li><a href="#active-frameworks" class="status" data-status="1"><?php echo $escaper->escapeHtml($lang['ActiveFrameworks']); ?> (<span id="active-frameworks-count"><?php echo get_frameworks_count(1) ?></span>)</a></li>
                  <li><a href="#inactive-frameworks" class="status" data-status="2"><?php echo $escaper->escapeHtml($lang['InactiveFrameworks']); ?> (<span id="inactive-frameworks-count"><?php echo get_frameworks_count(2) ?></span>)</a></li>
                </ul>

                  <div id="active-frameworks" class="custom-treegrid-container">
                        <?php get_framework_tabs(1) ?>
                  </div>
                  <div id="inactive-frameworks" class="custom-treegrid-container">
                        <?php get_framework_tabs(2) ?>
                  </div>
                  <?php
			// If there are no active frameworks
			if (get_frameworks_count(1) === "0")
			{
				// URL for the frameworks
				$url = "https://github.com/simplerisk/import-content/raw/master/Control%20Frameworks/frameworks.xml";

				// HTTP Options
				$opts = array(
					'ssl'=>array(
						'verify_peer'=>true,
						'verify_peer_name'=>true,
					),
					'http'=>array(
						'method'=>"GET",
						'header'=>"content-type: application/json\r\n",
					)
				);
				$context = stream_context_create($opts);

				$frameworks = @file_get_contents($url, false, $context);
				$frameworks_xml = simplexml_load_string($frameworks);

				echo "<h3>No frameworks?  No problem.</h3>\n";
				echo "<h4>Try one of the following ways to load frameworks into SimpleRisk:</h4>\n";
				echo "<ol>\n";
				echo "  <li>Click the plus (+) icon above to manually create a new framework.</li>\n";
				echo "  <li><a href=\"../admin/register.php\">Register</a> your SimpleRisk instance to download the free ComplianceForge SCF Extra and <a href=\"../admin/complianceforge_scf.php\">select from 148 different frameworks</a> that have been expertly mapped against 875 security and privacy controls.</li>\n";
				echo "  <li>Use the licensed <a href=\"../admin/content.php\">Import-Export Extra</a> to instantly install any of the following frameworks or import your own:\n";
				echo "    <ol style=\"list-style-type: disc;\">\n";

				// For each framework returned from GitHub
				foreach ($frameworks_xml as $framework_xml)
				{
					$name = $framework_xml->{"name"};
					echo "<li>" . $escaper->escapeHtml($name) . "</li>\n";
				}
				echo "    </ol>\n";
				echo "  </li>\n";
				echo "</ol>\n";
			}

                  ?>
              </div> <!-- status-tabs -->

            </div>
            <!-- Frameworks container Ends -->

            <!--  Controls container Begin -->
            <div id="controls-tab-content" class="tab-data hide">
                <div class="row-fluid">
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlClass']); ?>:</h4>
                            <?php create_multiple_dropdown("filter_by_control_class", "all", null, getAvailableControlClassList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); ?>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlPhase']); ?>:</h4>
                            <?php create_multiple_dropdown("filter_by_control_phase", "all", null, getAvailableControlPhaseList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); ?>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlFamily']); ?>:</h4>
                            <?php create_multiple_dropdown("filter_by_control_family", "all", null, getAvailableControlFamilyList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); ?>
                        </div>
                    </div>
                </div>
                <div class="row-fluid">
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlOwner']); ?>:</h4>
                            <?php create_multiple_dropdown("filter_by_control_owner", "all", null, getAvailableControlOwnerList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); ?>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlFramework']); ?>:</h4>
                            <?php create_multiple_dropdown("filter_by_control_framework", "all", null, getAvailableControlFrameworkList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); ?>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlPriority']); ?>:</h4>
                            <?php create_multiple_dropdown("filter_by_control_priority", "all", null, getAvailableControlPriorityList(), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); ?>
                        </div>
                    </div>
                </div>
                <div class="row-fluid">
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlType']); ?>:</h4>
                            <?php create_multiple_dropdown("filter_by_control_type", "all", null, get_options_from_table("control_type"), true, $escaper->escapeHtml($lang['Unassigned']), "-1"); ?>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlStatus']); ?>:</h4>
                            <select id="filter_by_control_status" class="form-field form-control" multiple="multiple">
                                <option selected value="1"><?php echo $escaper->escapeHtml($lang['Pass']);?></option>
                                <option selected value="0"><?php echo $escaper->escapeHtml($lang['Fail']);?></option>
                            </select>
                        </div>
                    </div>
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
                        <input type="hidden" id="unassigned_label" value="<?php echo $escaper->escapeHtml($lang['Unassigned']);?>">
                        <input type="hidden" id="existing_mappings" value="<?php echo $escaper->escapeHtml($lang["ExistingMappings"]);?>">
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

        //Have to remove the 'fade' class for the shown event to work for modals
        $('.modal').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
        });
    })
    function submit_controls_form(){
        document.controls_form.submit();
    }
  </script>
  
    <!-- MODEL WINDOW FOR ADDING FRAMEWORK -->
    <div id="framework--add" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="framework--add" aria-hidden="true">
        <form class="" id="framework--new" action="#" method="post" autocomplete="off">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['NewFramework']); ?></h4>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <?php display_add_framework();?>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" name="add_framework" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
            </div>

        </form>
    </div>

    <!-- MODEL WINDOW FOR EDITING FRAMEWORK -->
    <div id="framework--update" class="modal hide no-padding" tabindex="-1" role="dialog" aria-hidden="true">
        <form class="" action="#" method="post" autocomplete="off">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['EditFramework']); ?></h4>
            </div>

            <div class="modal-body">
                <input type="hidden" class="framework_id" name="framework_id" value=""> 
                <div class="form-group">
                    <?php display_add_framework();?>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" name="update_framework" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
            </div>

        </form>
    </div>

    <!-- MODEL WINDOW FOR FRAMEWORK DELETE CONFIRM -->
    <div id="framework--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="framework-delete-form" aria-hidden="true">
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
    <div id="control--add" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="control--add" aria-hidden="true" style="width:700px;">
        <form class="" id="add-control-form" action="#controls-tab" method="post" autocomplete="off">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['NewControl']); ?></h4>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <?php display_add_control();?>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" name="add_control" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
            </div>

        </form>
    </div>

    <!-- MODEL WINDOW FOR UPDATING CONTROL -->
    <div id="control--update" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="control--update" aria-hidden="true" style="width:700px;">
        <form class="" id="update-control-form" action="#controls-tab" method="post" autocomplete="off">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['EditControl']); ?></h4>
            </div>

            <div class="modal-body">
                <input type="hidden" class="control_id" name="control_id" value=""> 
                <div class="form-group">
                    <?php display_add_control();?>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" name="update_control" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
            </div>

        </form>
    </div>

    <!-- MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    <div id="control--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="control--delete" aria-hidden="true">
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
    <div id="add_mapping_row" class="hide">
    	<table>
            <tr>
                <td><?php create_dropdown("frameworks", NULL,"map_framework_id[]", true, false, false, "required"); ?></td>
                <td><input type="text" name="reference_name[]" value="" class="form-control" maxlength="100" required></td>
                <td><a href="javascript:void(0);" class="control-block--delete-mapping" title="<?php echo $escaper->escapeHtml($lang["Delete"]);?>"><i class="fa fa-trash"></i></a></td>
            </tr>
        </table>
    </div>
    <?php display_set_default_date_format_script(); ?>
</body>
</html>
