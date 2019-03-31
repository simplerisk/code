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

// Enforce that the user has access to risk management
enforce_permission_riskmanagement();

// If the risks were saved to projects
if (isset($_POST['update_projects']))
{
  if (isset($_POST['risk_id']))
  {
    $risk_id = $_POST['risk_id'];
    $project_id = $_POST['project_id'];
    update_risk_project($project_id, $risk_id);  
  }
  // Display an alert
  set_alert(true, "good", "The risks were saved successfully to the projects.");
}


// If the order was updated
if (isset($_POST['update_order']))
{
  //foreach ($_POST['ids'] as $id)
  //{
    //$order = $_POST['priority'];
    $ids = $_POST['project_ids'];
    update_project_priority($ids);
    //update_project_order($ids);
  //}

  // Display an alert
  set_alert(true, "good", "The project order was updated successfully.");
}

// If the projects were saved to status
if (isset($_POST['update_project_status']))
{
  // For each project
  //foreach ($_POST['projects'] as $project_id)
  //{
    // Update its project status
    $status_id  = $_POST['status'];
    $project_id = $_POST['project_id'];
    update_project_status($status_id, $project_id);

    // If the project status is Completed (3)
    if ($status_id == 3)
    {
      // Close the risks associated with the project
      completed_project($project_id);
    }
    // Otherwise
    else
    {
      // Reopen the risks associated with the project
      incomplete_project($project_id);
    }
  //}

  // Display an alert
  set_alert(true, "good", "The project statuses were successfully updated.");
}

// Check if a new project was submitted
if (isset($_POST['add_project']))
{
  $name = $_POST['new_project'];

  // Check if the project name is null
  if (isset($name) && $name == "")
  {
    // Display an alert
    set_alert(true, "bad", "The project name cannot be empty.");
  }
  // Otherwise
  else
  {
    // Insert a new project up to 100 chars
    add_name("projects", try_encrypt($name), 100);

    // Display an alert
    set_alert(true, "good", "A new project was added successfully.");
  }
}

// Check if a project was deleted
if (isset($_POST['delete_project']))
{
  $value = (int)$_POST['project_id'];

  // Verify value is an integer
  if (is_int($value))
  {
    // If the project ID is 0 (ie. Unassigned Risks)
    if ($value == 0)
    {
      // Display an alert
      set_alert(true, "bad", "You cannot delete the Unassigned Risks project or we will have no place to put unassigned risks.  Sorry.");
    }
    else
    {
      // Get the risks associated with the project
      $risks = get_project_risks($value);

      // For each associated risk
      foreach ($risks as $risk)
      {
        // Set the project ID for the risk to unassigned (0)
        update_risk_project(0, $risk['id']);
      }

      // Delete the project
      delete_value("projects", $value);

      // Display an alert
      set_alert(true, "good", "An existing project was deleted successfully.");
    }
  }
  // We should never get here as we bound the variable as an int
  else
  {
    // Display an alert
    set_alert(true, "bad", "The project ID was not a valid value.  Please try again.");
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // The request is using the POST method
    json_response(200, get_alert(true), null);
}

?>

<!doctype html>
<html>

<head>
  <script src="../js/jquery.min.js"></script>
  <script src="../js/jquery-ui.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/plan-project.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  <link rel="stylesheet" href="../css/prioritize.css">


  <link rel="stylesheet" href="../css/divshot-util.css">
  <link rel="stylesheet" href="../css/divshot-canvas.css">
  <link rel="stylesheet" href="../css/display.css">

  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">

  <?php
      setup_alert_requirements("..");
  ?>
  <?php
  // Get the projects
  $projects = get_projects();

  // Get the total number of projects
  $count = count($projects);

  // Initialize the counter
  $counter = 1;

  ?>
  <script>
  $(function() {
    <?php
    echo "$( \"";

    // Initialize the counter
    $counter = 1;

    // For each project created
    foreach ($projects as $project)
    {
      // Get the project ID
      $id = (int)$project['value'];

      echo "#sortable-" . $id;

      // If it's not the last one
      if ($counter != $count)
      {
        echo ", ";
        $counter++;
      }
    }

    echo ", #statussortable-1, #statussortable-2, #statussortable-3, #statussortable-4";
    echo "\" ).sortable().disableSelection();\n";
    ?>
    var $tabs = $( "#tabs" ).tabs();
    var $tab_items = $( "ul:first li", $tabs ).droppable({
      accept: ".connectedSortable li",
      hoverClass: "ui-state-hover",
      drop: function( event, ui ) {
        var $item = $( this );
        var $list = $( $item.find( "a" ).attr( "href" ) )
        .find( ".connectedSortable" );
        ui.draggable.hide( "slow", function() {
          $tabs.tabs( "option", "active", $tab_items.index( $item ) );
          $( this ).appendTo( $list ).show( "slow" );
        });
        $list.each(function() {
          // Get the project ID that was just dropped into
          var id = $(this).attr("id");
          var part = id.split("-");
          var project_id = part[1];

          // Get the risk ID that was just dropped
          var dragged_risk_id = $(ui.draggable).attr("id");

          // Risk name to update
          var risk_name = "risk_" + dragged_risk_id;

          // Update the risk input with the proper value
          document.getElementsByName(risk_name)[0].value = project_id;
        });
      }
    });

    var $statustabs = $( "#statustabs" ).tabs();
    var $status_tab_items = $( "ul:first li", $statustabs ).droppable({
      accept: ".connectedSortable li",
      hoverClass: "ui-state-hover",
      drop: function( event, ui ) {
        var $item = $( this );
        var $list = $( $item.find( "a" ).attr( "href" ) )
        .find( ".connectedSortable" );
        ui.draggable.hide( "slow", function() {
          $statustabs.tabs( "option", "active", $status_tab_items.index( $item ) );
          $( this ).appendTo( $list ).show( "slow" );
        });
        $list.each(function() {
          // Get the status ID that was just dropped into
          var id = $(this).attr("id");
          var part = id.split("-");
          var project_id = part[1];

          // Get the project ID that was just dropped
          var dragged_project_id = $(ui.draggable).attr("id");

          // Project name to update
          var project_name = "project_" + dragged_project_id;

          // Update the risk input with the proper value
          document.getElementsByName(project_name)[0].value = project_id;
        });
      }
    });

  });
  </script>
  <script>
  $(function() {
    $( "#prioritize" ).sortable({
      update: function(event, ui)
      {
        // Create an array with the new order
        var order = $( "#prioritize" ).sortable('toArray');

        for(var key in order) {
          var val = order[key];
          var part = val.split("_");

          // Update each hidden field used to store the list item position
          document.getElementById("order"+part[1]).value = key;
        }
      }
    });

    $( "#prioritize" ).disableSelection();
  });
  </script>
</head>

<body>


  <?php
  view_top_menu("RiskManagement");

  // Get any alert messages
  get_alert();
  ?>
  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_risk_management_menu("PrioritizeForProjectPlanning"); ?>
      </div>
      <div class="span9" id="project-container">
        <div class="row-fluid">
          <div class="span12">
            <!-- Container Bigins  -->

            <!-- <div class="status1">asdasdasdas</div> -->

            <div id="tabs">

              <div class="status-tabs">

                <a href="#project--add" role="button" data-toggle="modal" class="project--add"><i class="fa fa-plus"></i></a>

                <ul class="clearfix tabs-nav">
                  <li><a href="#active-projects" class="status" data-status="1">Active Projects (<?php get_projects_count(1) ?>)</a></li>
                  <li><a href="#on-hold-projects" class="status" data-status="2">On Hold Projects (<?php get_projects_count(2) ?>)</a></li>
                  <li><a href="#closed-projects" class="status" data-status="3">Com Projects (<?php get_projects_count(3) ?>)</a></li>
                  <li><a href="#canceled-projects" class="status" data-status="4">Canceled Projects (<?php get_projects_count(4) ?>)</a></li>
                </ul>

                <ul class="project-headers clearfix">
                  <li class="project-block--priority white-labels">Priority</li>
                  <li class="project-block--name white-labels">Project Name</li>
                  <li class="project-block--risks white-labels">Risk</li>
                </ul>

              </div> <!-- status-tabs -->

              <div id="active-projects" class="sortable">
                    <?php get_project_tabs(1) ?>
              </div>
              <div id="on-hold-projects" class="sortable">
                    <?php get_project_tabs(2) ?>
              </div>
              <div id="closed-projects" class="sortable">
                    <?php get_project_tabs(3) ?>
              </div>

              <div id="canceled-projects" class="sortable">
                    <?php get_project_tabs(4) ?>
              </div>
            </div>

            <!-- Container Ends  -->
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- MODEL WINDOW FOR ADDING PROJECT -->

<div id="project--add" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="project--add" aria-hidden="true">
  <div class="modal-body">

    <form class="" id="project--new" action="#" method="post">
      <div class="form-group">
        <label for="">New Project Name</label>
        <input type="text" name="project--name" id="project--name" value="" class="form-control">
      </div>

      <div class="form-group">
        <button class="btn btn-danger" data-dismiss="modal" aria-hidden="true">Cancel</button>
        <button type="submit" class="btn btn-default">Add</button>
      </div>
    </form>

  </div>
</div>


<script type="template" id="project-template">

  <div class="project-block clearfix">

    <div class="project-block--header clearfix">
        <div class="project-block--priority pull-left">{{PRIORITY}}</div>
        <div class="project-block--name pull-left">{{NAME}}</div>
        <div class="project-block--risks pull-left"><a href="#" class="view--risks">View Risks</a> <a href="#" class="project-block--delete pull-right"><i class="fa fa-trash"></i></a></div>
    </div><!-- POJECT-BLOCK--HEADER-->

    <div class="risks">

    </div><!-- RISKS -->

  </div><!-- PROJECT-BLOCK-->

</script>

<?php display_set_default_date_format_script(); ?>
</body>

</html>
