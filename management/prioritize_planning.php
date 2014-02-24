<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once('../includes/functions.php');
        require_once('../includes/authenticate.php');

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
                header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
        }

        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRisk');
        require_once('../includes/csrf-magic/csrf-magic.php');

        // Check for session timeout or renegotiation
        session_check();

	// Default is no alert
	$alert = false;

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

	// If the risks were saved to projects
	if (isset($_POST['update_projects']))
	{
		foreach ($_POST['ids'] as $risk_id)
                {
                        $project_id = $_POST['risk_' . $risk_id];
                        update_risk_project($project_id, $risk_id);
                }

		// There is an alert message
		$alert = "good";
		$alert_message = "The risks were saved successfully to the projects.";
        }


	// If the order was updated
	if (isset($_POST['update_order']))
	{
		foreach ($_POST['ids'] as $id)
		{
			$order = $_POST['order_' . $id];
			update_project_order($order, $id);
		}

                // There is an alert message
                $alert = "good";
                $alert_message = "The project order was updated successfully.";
	}

        // Check if a new project was submitted
        if (isset($_POST['add_project']))
        {
                $name = $_POST['new_project'];

                // Insert a new project up to 100 chars
                add_name("projects", $name, 100);

                // Audit log
                $risk_id = 1000;
                $message = "A new project was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

		// There is an alert message
                $alert = "good";
                $alert_message = "A new project was added successfully.";
        }

        // Check if a project was deleted
        if (isset($_POST['delete_project']))
        {
                $value = (int)$_POST['projects'];

                // Verify value is an integer
                if (is_int($value))
                {
			// If the project ID is 0 (ie. Unassigned Risks)
			if ($value == 0)
			{
				// There is an alert message
				$alert = "bad";
				$alert_message = "You cannot delete the Unassigned Risks project or we will have no place to put unassigned risks.  Sorry.";
			}
			// If the project has risks associated with it
			else if (project_has_risks($value))
			{
				// There is an alert message
				$alert = "bad";
				$alert_message = "You cannot delete a project that has risks assigned to it.  Drag the risks back to the Unassigned Risks tab, save it, and try again.";
			}
			else
			{
                        	delete_value("projects", $value);

                        	// Audit log
                        	$risk_id = 1000;
                        	$message = "An existing project was removed by the \"" . $_SESSION['user'] . "\" user.";
                        	write_log($risk_id, $_SESSION['uid'], $message);

				// There is an alert message
				$alert = "good";
                		$alert_message = "An existing project was deleted successfully.";
			}
                }
		// We should never get here as we bound the variable as an int
		else
		{
			// There is an alert message
			$alert = "bad";
			$alert_message = "The project ID was not a valid value.  Please try again.";
		}
        }

?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery-1.10.1.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css"> 
    <link rel="stylesheet" href="../css/prioritize.css">
    <link rel="stylesheet" href="../css/jquery-ui.min.css">
    <style>
<?php
	// Get the projects
        $projects = get_projects();

	// Get the total number of projects
	$count = count($projects);

	// Initialize the counter
	$counter = 1;

	// For each project created
        foreach ($projects as $project)
        {
		// Get the project ID
                $id = $project['value'];

		echo "#sortable-" . $id . " li";

                // If it's not the last one
                if ($counter != $count)
                {
                        echo ", ";
			$counter++;
                }
        }

	echo " { margin: 0 5px 5px 5px; padding: 5px; font-size: 0.75em; width: 120px; }\n";
?>
    </style>
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
                $id = $project['value'];

		echo "#sortable-" . $id;

                // If it's not the last one
                if ($counter != $count)
                {
                        echo ", ";
                        $counter++;
                }
	}

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
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">
    <link rel="stylesheet" href="../css/prioritize.css">
    <link rel="stylesheet" href="../css/jquery-ui.min.css">
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li>
                <a href="../index.php">Home</a> 
              </li>
              <li class="active">
                <a href="index.php">Risk Management</a> 
              </li>
              <li>
                <a href="../reports/index.php">Reporting</a> 
              </li>
<?php
if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
{
          echo "<li>\n";
          echo "<a href=\"../admin/index.php\">Configure</a>\n";
          echo "</li>\n";
}
          echo "</ul>\n";
          echo "</div>\n";

if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted")
{
          echo "<div class=\"btn-group pull-right\">\n";
          echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">".$_SESSION['name']."<span class=\"caret\"></span></a>\n";
          echo "<ul class=\"dropdown-menu\">\n";
          echo "<li>\n";
          echo "<a href=\"../account/profile.php\">My Profile</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"../logout.php\">Logout</a>\n";
          echo "</li>\n";
          echo "</ul>\n";
          echo "</div>\n";
}
?>
        </div>
      </div>
    </div>
<?php
        if ($alert == "good")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 greenalert\">" . $alert_message . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $alert_message . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <ul class="nav  nav-pills nav-stacked">
            <li>
              <a href="index.php">I. Submit Your Risks</a> 
            </li>
            <li>
              <a href="plan_mitigations.php">II. Plan Your Mitigations</a> 
            </li>
            <li>
              <a href="management_review.php">III. Perform Management Reviews</a> 
            </li>
            <li class="active">
              <a href="prioritize_planning.php">IV. Prioritize for Project Planning</a> 
            </li>
            <li>
              <a href="review_risks.php">V. Review Risks Regularly</a>     
            </li>
          </ul>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <h4>1) Add and Remove Projects</h4>
                <p>Add and remove projects in order to associate multiple risks together for prioritization.</p>
                <form name="project" method="post" action="">
                <p>
                Add new project named <input name="new_project" type="text" maxlength="100" size="20" />&nbsp;&nbsp;<input type="submit" value="Add" name="add_project" /><br />
                Delete current project named <?php create_dropdown("projects"); ?>&nbsp;&nbsp;<input type="submit" value="Delete" name="delete_project" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <h4>2) Add Unassigned Risks to Projects</h4>
                <p>Drag and drop unassigned risks marked for consideration as a project into the appropriate project tab.</p>
                <?php get_project_tabs() ?>
              </div>
              <div class="hero-unit">
                <h4>3) Prioritize Projects</h4>
                <p>Move projects around and change the order of prioritization.  Once finished, don't forget to press the &quot;Update&quot; button to save your changes.</p>
                <?php get_project_list(); ?>
              </div>
<!--
              <div class="hero-unit">
                <h4>4) Determine Project Status</h4>
                <p>Place projects into buckets based on their current status.</p>
                <?php get_project_status(); ?>
              </div>
-->
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
