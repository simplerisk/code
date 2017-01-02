<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));
	require_once(realpath(__DIR__ . '/../includes/alerts.php'));

        // Include Zend Escaper for HTML Output Encoding
        require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
        $escaper = new Zend\Escaper\Escaper('utf-8');

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
		header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
        }

        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        if (!isset($_SESSION))
        {
        	session_name('SimpleRisk');
        	session_start();
        }

        // Include the language file
        require_once(language_file());

        require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

        // Check for session timeout or renegotiation
        session_check();

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

	// Check if access is authorized
	if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
	{
		header("Location: ../index.php");
		exit(0);
	}

        // Check if a new category was submitted
        if (isset($_POST['add_category']))
        {
                $name = $_POST['new_category'];

                // Insert a new category up to 50 chars
                add_name("category", $name, 50);

		// Display an alert
		set_alert(true, "good", "A new category was added successfully.");
        }

        // Check if the category update was submitted
        if (isset($_POST['update_category']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['update_category_name'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("category", $new_name, $value);

			// Display an alert
			set_alert(true, "good", "The category name was updated successfully.");
                }
        }

        // Check if a category was deleted
        if (isset($_POST['delete_category']))
        {
                $value = (int)$_POST['category'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("category", $value);

			// Display an alert
			set_alert(true, "good", "An existing category was removed successfully.");
                }
        }

        // Check if a new team was submitted
        if (isset($_POST['add_team']))
        {
                $name = $_POST['new_team'];

                // Insert a new team up to 50 chars
                add_name("team", $name, 50);

		// Display an alert
		set_alert(true, "good", "A new team was added successfully.");
        }

	// Check if the team update was submitted
	if (isset($_POST['update_team']))
	{
		$new_name = $_POST['new_name'];
		$value = (int)$_POST['update_team_name'];

		// Verify value is an integer
		if (is_int($value))
		{
			update_table("team", $new_name, $value);

			// Display an alert
			set_alert(true, "good", "The team name was updated successfully.");
		}
	}

        // Check if a team was deleted
        if (isset($_POST['delete_team']))
        {
                $value = (int)$_POST['team'];

                // Verify value is an integer
                if (is_int($value))
                {
			// If team separation is enabled
			if (team_separation_extra())
			{
				// Check if a risk is assigned to the team
				$risks = get_risks_by_team($value);

				// If the risks array is empty
				if (empty($risks))
				{
					$delete = true;
				}
				else
				{
					$delete = false;
				}
			}
			else
			{
				$delete = true;
			}

			// If it is ok to delete the team
			if ($delete)
			{
                        	delete_value("team", $value);

				// Display an alert
				set_alert(true, "good", "An existing team was removed successfully.");
			}
			else
			{
				// Display an alert
				set_alert(true, "bad", "Cannot delete this team because there are risks that are currently using it.");
			}
                }
        }

        // Check if a new technology was submitted
        if (isset($_POST['add_technology']))
        {
                $name = $_POST['new_technology'];

                // Insert a new technology up to 50 chars
                add_name("technology", $name, 50);

		// Display an alert
		set_alert(true, "good", "A new technology was added successfully.");
        }

        // Check if the technology update was submitted
        if (isset($_POST['update_technology']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['update_technology_name'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("technology", $new_name, $value);

			// Display an alert
			set_alert(true, "good", "The technology name was updated successfully.");
                }
        }

        // Check if a technology was deleted
        if (isset($_POST['delete_technology']))
        {
                $value = (int)$_POST['technology'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("technology", $value);

			// Display an alert
			set_alert(true, "good", "An existing technology was removed successfully.");
                }
        }

        // Check if a new location was submitted
        if (isset($_POST['add_location']))
        {
                $name = $_POST['new_location'];

                // Insert a new location up to 100 chars
                add_name("location", $name, 100);

		// Display an alert
		set_alert(true, "good", "A new location was added successfully.");
        }

        // Check if the location update was submitted
        if (isset($_POST['update_location']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['update_location_name'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("location", $new_name, $value);

			// Display an alert
			set_alert(true, "good", "The location name was updated successfully.");
                }
        }

        // Check if a location was deleted
        if (isset($_POST['delete_location']))
        {
                $value = (int)$_POST['location'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("location", $value);

			// Display an alert
			set_alert(true, "good", "An existing location was removed successfully.");
                }
        }

        // Check if a new control regulation was submitted
        if (isset($_POST['add_regulation']))
        {
                $name = $_POST['new_regulation'];

                // Insert a new regulation up to 50 chars
                add_name("regulation", $name, 50);

		// Display an alert
		set_alert(true, "good", "A new control regulation was added successfully.");
        }

        // Check if the regulation update was submitted
        if (isset($_POST['update_regulation']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['update_regulation_name'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("regulation", $new_name, $value);

			// Display an alert
			set_alert(true, "good", "The regulation name was updated successfully.");
                }
        }

        // Check if a control regulation was deleted
        if (isset($_POST['delete_regulation']))
        {
                $value = (int)$_POST['regulation'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("regulation", $value);

			// Display an alert
			set_alert(true, "good", "An existing control regulation was removed successfully.");
                }
        }

        // Check if a new planning strategy was submitted
        if (isset($_POST['add_planning_strategy']))
        {
                $name = $_POST['new_planning_strategy'];

                // Insert a new planning strategy up to 20 chars
                add_name("planning_strategy", $name, 20);

		// Display an alert
		set_alert(true, "good", "A new planning strategy was added successfully.");
        }

        // Check if the planning strategy update was submitted
        if (isset($_POST['update_planning_strategy']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['update_planning_strategy_name'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("planning_strategy", $new_name, $value);

			// Display an alert
			set_alert(true, "good", "The planning strategy name was updated successfully.");
                }
        }

        // Check if a planning strategy was deleted
        if (isset($_POST['delete_planning_strategy']))
        {
                $value = (int)$_POST['planning_strategy'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("planning_strategy", $value);

			// Display an alert
			set_alert(true, "good", "An existing planning strategy was removed successfully.");
                }
        }

        // Check if a new close reason was submitted
        if (isset($_POST['add_close_reason']))
        {
                $name = $_POST['new_close_reason'];

                // Insert a new close reason up to 50 chars
                add_name("close_reason", $name, 50);

		// Display an alert
		set_alert(true, "good", "A new close reason was added successfully.");
        }

        // Check if the close reason update was submitted
        if (isset($_POST['update_close_reason']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['update_close_reason_name'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("close_reason", $new_name, $value);

			// Display an alert
			set_alert(true, "good", "The close reason name was updated successfully.");
                }
        }

        // Check if a close reason was deleted
        if (isset($_POST['delete_close_reason']))
        {
                $value = (int)$_POST['close_reason'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("close_reason", $value);

			// Display an alert
			set_alert(true, "good", "An existing close reason was removed successfully.");
                }
        }

        // Check if a new status was submitted
        if (isset($_POST['add_status']))
        {
                $name = $_POST['new_status'];

                // Insert a new status up to 50 chars
                add_name("status", $name, 50);

		// Display an alert
		set_alert(true, "good", "A new status was added successfully.");
        }

        // Check if the status update was submitted
        if (isset($_POST['update_status']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['update_status_name'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("status", $new_name, $value);

			// Display an alert
			set_alert(true, "good", "The status name was updated successfully.");
                }
        }

        // Check if a status was deleted
        if (isset($_POST['delete_status']))
        {
                $value = (int)$_POST['status'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("status", $value);

			// Display an alert
			set_alert(true, "good", "An existing status was removed successfully.");
                }
        }

        // Check if a new source was submitted
        if (isset($_POST['add_source']))
        {
                $name = $_POST['new_source'];

                // Insert a new source up to 50 chars
                add_name("source", $name, 50);

		// Display an alert
		set_alert(true, "good", "A new source was added successfully.");
        }

        // Check if the source update was submitted
        if (isset($_POST['update_source']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['update_source_name'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("source", $new_name, $value);

			// Display an alert
			set_alert(true, "good", "The source name was updated successfully.");
                }
        }

        // Check if a source was deleted
        if (isset($_POST['delete_source']))
        {
                $value = (int)$_POST['source'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("source", $value);

			// Display an alert
			set_alert(true, "good", "An existing source was removed successfully.");
                }
        }
?>

<!doctype html>
<html>

  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">

    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
  </head>

  <body>

<?php
	view_top_menu("Configure");

	// Get any alert messages
	get_alert();
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("AddAndRemoveValues"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="category" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['Category']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewCategoryNamed']); ?> <input name="new_category" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_category" /><br />
		<?php echo $escaper->escapeHtml($lang['Change']); ?> <?php create_dropdown("category", NULL, "update_category_name"); ?> <?php echo $escaper->escapeHtml($lang['to']); ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_category" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteCurrentCategoryNamed']); ?> <?php create_dropdown("category"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_category" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="team" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['Team']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewTeamNamed']); ?> <input name="new_team" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_team" /><br />
		<?php echo $escaper->escapeHtml($lang['Change']); ?> <?php create_dropdown("team", NULL, "update_team_name"); ?> <?php echo $escaper->escapeHtml($lang['to']); ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_team" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteCurrentTeamNamed']); ?> <?php create_dropdown("team"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_team" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="technology" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['Technology']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewTechnologyNamed']); ?> <input name="new_technology" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_technology" /><br />
		<?php echo $escaper->escapeHtml($lang['Change']); ?> <?php create_dropdown("technology", NULL, "update_technology_name"); ?> <?php echo $escaper->escapeHtml($lang['to']); ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_technology" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteCurrentTechnologyNamed']); ?> <?php create_dropdown("technology"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_technology" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="location" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['SiteLocation']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewSiteLocationNamed']); ?> <input name="new_location" type="text" maxlength="100" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_location" /><br />
		<?php echo $escaper->escapeHtml($lang['Change']); ?> <?php create_dropdown("location", NULL, "update_location_name"); ?> <?php echo $escaper->escapeHtml($lang['to']); ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_location" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteCurrentSiteLocationNamed']); ?> <?php create_dropdown("location"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_location" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="regulation" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['ControlRegulation']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewControlRegulationNamed']); ?> <input name="new_regulation" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_regulation" /><br />
		<?php echo $escaper->escapeHtml($lang['Change']); ?> <?php create_dropdown("regulation", NULL, "update_regulation_name"); ?> <?php echo $escaper->escapeHtml($lang['to']); ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_regulation" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteCurrentControlRegulationNamed']); ?> <?php create_dropdown("regulation"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_regulation" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="planning_strategy" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['RiskPlanningStrategy']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewRiskPlanningStrategyNamed']); ?> <input name="new_planning_strategy" type="text" maxlength="20" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_planning_strategy" /><br />
		<?php echo $escaper->escapeHtml($lang['Change']); ?> <?php create_dropdown("planning_strategy", NULL, "update_planning_strategy_name"); ?> <?php echo $escaper->escapeHtml($lang['to']); ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_planning_strategy" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteCurrentRiskPlanningStrategyNamed']); ?> <?php create_dropdown("planning_strategy"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_planning_strategy" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="close_reason" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['CloseReason']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewCloseReasonNamed']); ?> <input name="new_close_reason" type="text" maxlength="20" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_close_reason" /><br />
		<?php echo $escaper->escapeHtml($lang['Change']); ?> <?php create_dropdown("close_reason", NULL, "update_close_reason_name"); ?> <?php echo $escaper->escapeHtml($lang['to']); ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_close_reason" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteCurrentCloseReasonNamed']); ?> <?php create_dropdown("close_reason"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_close_reason" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="status" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['Status']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewStatusNamed']); ?> <input name="new_status" type="text" maxlength="20" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_status" /><br />
                <?php echo $escaper->escapeHtml($lang['Change']); ?> <?php create_dropdown("status", NULL, "update_status_name"); ?> <?php echo $escaper->escapeHtml($lang['to']); ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_status" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteStatusNamed']); ?> <?php create_dropdown("status"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_status" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="source" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['RiskSource']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewSourceNamed']); ?> <input name="new_source" type="text" maxlength="20" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_source" /><br />
                <?php echo $escaper->escapeHtml($lang['Change']); ?> <?php create_dropdown("source", NULL, "update_source_name"); ?> <?php echo $escaper->escapeHtml($lang['to']); ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_source" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteSourceNamed']); ?> <?php create_dropdown("source"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_source" />
                </p>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
