<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
        include_once(realpath(__DIR__ . '/includes.php'));


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

	// Default is no alert
	$alert = false;

        // Check if access is authorized
        if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
        {
                header("Location: ../index.php");
                exit(0);
        }

        // Check if the risk level update was submitted
        if (isset($_POST['update_risk_levels']))
        {
                $high = (float)$_POST['high'];
                $medium = (float)$_POST['medium'];
                $low = (float)$_POST['low'];
                $risk_model = (int)$_POST['risk_models'];

                // Check if all values are integers
                if (is_float($high) && is_float($medium) && is_float($low) && is_int($risk_model))
                {
                        // Check if low < medium < high
                        if (($low < $medium) && ($medium < $high))
                        {
                                // Update the risk level
                                update_risk_levels($high, $medium, $low);

                		// Audit log
                		$risk_id = 1000;
                		$message = "Risk level scoring was modified by the \"" . $_SESSION['user'] . "\" user.";
                		write_log($risk_id, $_SESSION['uid'], $message);

				// TODO: This message will never be seen because of the alert condition for the risk model.
				$alert = "good";
				$alert_message = "The configuration was updated successfully.";
                        }
			// Otherwise, there was a problem
			else
			{
				// There is an alert message
				$alert = "bad";
				$alert_message = "Your LOW risk needs to be less than your MEDIUM risk which needs to be less than your HIGH risk.";
			}

                        // Risk model should be between 1 and 5
                        if ((1 <= $risk_model) && ($risk_model <= 5))
                        {
                                // Update the risk model
                                update_risk_model($risk_model);

                                // Audit log
                                $risk_id = 1000;
                                $message = "The risk formula was modified by the \"" . $_SESSION['user'] . "\" user.";
                                write_log($risk_id, $_SESSION['uid'], $message);

				// There is an alert message
				$alert = "good";
				$alert_message = "The configuration was updated successfully.";
                        }
			// Otherwise, there was a problem
			else
			{
				$alert = "good";
				$alert_message = "The risk formula submitted was an invalid value.";
			}
                }
        }

        // Check if the risk level update was submitted
        if (isset($_POST['update_review_settings']))
        {
                $high = (int)$_POST['high'];
                $medium = (int)$_POST['medium'];
                $low = (int)$_POST['low'];

                // Check if all values are integers
                if (is_int($high) && is_int($medium) && is_int($low))
                {
                        // Update the review settings
                        update_review_settings($high, $medium, $low);

                        // Audit log
                        $risk_id = 1000;
                        $message = "The review settings were modified by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

      $alert = "good";
      $alert_message = "The review settings have been updated successfully!";
                }
    // NOTE: This will never trigger as we bind $high, $medium, and $low to integer values
    else
    {
      $alert = "bad";
      $alert_message = "One of your review settings is not an integer value.  Please try again.";
    }
        }

        // Check if a new category was submitted
        if (isset($_POST['add_category']))
        {
                $name = $_POST['new_category'];

                // Insert a new category up to 50 chars
                add_name("category", $name, 50);

                // Audit log
                $risk_id = 1000;
                $message = "A new category was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

    // There is an alert message
    $alert = "good";
    $alert_message = "A new category was added successfully.";
        }

        // Check if a category was deleted
        if (isset($_POST['delete_category']))
        {
                $value = (int)$_POST['category'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("category", $value);

                  // Audit log
                  $risk_id = 1000;
                  $message = "An existing category was removed by the \"" . $_SESSION['user'] . "\" user.";
                  write_log($risk_id, $_SESSION['uid'], $message);

                  // There is an alert message
                  $alert = "good";
                  $alert_message = "An existing category was removed successfully.";
                }
        }

        // Check if a new team was submitted
        if (isset($_POST['add_team']))
        {
                $name = $_POST['new_team'];

                // Insert a new team up to 50 chars
                add_name("team", $name, 50);

                // Audit log
                $risk_id = 1000;
                $message = "A new team was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new team was added successfully.";
        }

        // Check if a team was deleted
        if (isset($_POST['delete_team']))
        {
                $value = (int)$_POST['team'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("team", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing team was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing team was removed successfully.";
                }
        }

        // Check if a new technology was submitted
        if (isset($_POST['add_technology']))
        {
                $name = $_POST['new_technology'];

                // Insert a new technology up to 50 chars
                add_name("technology", $name, 50);

                // Audit log
                $risk_id = 1000;
                $message = "A new technology was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new technology was added successfully.";
        }

        // Check if a technology was deleted
        if (isset($_POST['delete_technology']))
        {
                $value = (int)$_POST['technology'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("technology", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing technology was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing technology was removed successfully.";
                }
        }

        // Check if a new location was submitted
        if (isset($_POST['add_location']))
        {
                $name = $_POST['new_location'];

                // Insert a new location up to 100 chars
                add_name("location", $name, 100);

                // Audit log
                $risk_id = 1000;
                $message = "A new location was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new location was added successfully.";
        }

        // Check if a location was deleted
        if (isset($_POST['delete_location']))
        {
                $value = (int)$_POST['location'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("location", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing location was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing location was removed successfully.";
                }
        }

        // Check if a new control regulation was submitted
        if (isset($_POST['add_regulation']))
        {
                $name = $_POST['new_regulation'];

                // Insert a new regulation up to 50 chars
                add_name("regulation", $name, 50);

                // Audit log
                $risk_id = 1000;
                $message = "A new control regulation was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new control regulation was added successfully.";
        }

        // Check if a control regulation was deleted
        if (isset($_POST['delete_regulation']))
        {
                $value = (int)$_POST['regulation'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("regulation", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing control regulation was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing control regulation was removed successfully.";
                }
        }

        // Check if a new planning strategy was submitted
        if (isset($_POST['add_planning_strategy']))
        {
                $name = $_POST['new_planning_strategy'];

                // Insert a new planning strategy up to 20 chars
                add_name("planning_strategy", $name, 20);

                // Audit log
                $risk_id = 1000;
                $message = "A new planning strategy was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new planning strategy was added successfully.";
        }

        // Check if a planning strategy was deleted
        if (isset($_POST['delete_planning_strategy']))
        {
                $value = (int)$_POST['planning_strategy'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("planning_strategy", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing planning strategy was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing planning strategy was removed successfully.";
                }
        }

        // Check if a new close reason was submitted
        if (isset($_POST['add_close_reason']))
        {
                $name = $_POST['new_close_reason'];

                // Insert a new close reason up to 50 chars
                add_name("close_reason", $name, 50);
                
                // Audit log
                $risk_id = 1000;
                $message = "A new close reason was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new close reason was added successfully.";
        }
                        
        // Check if a close reason was deleted
        if (isset($_POST['delete_close_reason']))
        {
                $value = (int)$_POST['close_reason'];
        
                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("close_reason", $value);
                
                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing close reason was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing close reason was removed successfully.";
                }
        }

        // Check if a new user was submitted
        if (isset($_POST['add_user']))
        {
    $type = $_POST['type'];
                $name = addslashes($_POST['name']);
    $email = addslashes($_POST['email']);
                $user = addslashes($_POST['new_user']);
                $pass = $_POST['password'];
                $repeat_pass = $_POST['repeat_password'];
    $teams = $_POST['team'];
                $admin = isset($_POST['admin']) ? '1' : '0';
    $submit_risks = isset($_POST['submit_risks']) ? '1' : '0';
    $modify_risks = isset($_POST['modify_risks']) ? '1' : '0';
    $close_risks = isset($_POST['close_risks']) ? '1' : '0';
    $plan_mitigations = isset($_POST['plan_mitigations']) ? '1' : '0';
                $review_high = isset($_POST['review_high']) ? '1' : '0';
                $review_medium = isset($_POST['review_medium']) ? '1' : '0';
                $review_low = isset($_POST['review_low']) ? '1' : '0';
    $multi_factor = (int)$_POST['multi_factor'];

    // If the type is 1
    if ($type == "1")
    {
      $type = "simplerisk";
    }
    // If the type is 2
    else if ($type == "2")
    {
      $type = "ldap";
    }
    else $type = "INVALID";

                // Verify that the two passwords are the same
                if ("$pass" == "$repeat_pass")
                {
                        // Verify that the user does not exist
                        if (!user_exist($user))
                        {
        // Create a unique salt for the user
                                $salt = generate_token(20);

        // Hash the salt
        $salt_hash = '$2a$15$' . md5($salt);

        // Generate the password hash
        $hash = generateHash($salt_hash, $pass);

        // Create a boolean for all
        $all = false;

        // Create a boolean for none
        $none = false;

        // Create the team value
        foreach ($teams as $value)
        {
          // If the selected value is all
          if ($value == "all") $all = true;

          // If the selected value is none
          if ($value == "none") $none = true;

          $team .= ":";
          $team .= $value;
          $team .= ":";
        }

        // If no value was submitted then default to none
        if ($value == "") $none = true;

        // If all was selected then assign all teams
        if ($all) $team = "all";

        // If none was selected then assign no teams
        if ($none) $team = "none";

                                // Insert a new user
                                add_user($type, $user, $email, $name, $salt, $hash, $team, $admin, $review_high, $review_medium, $review_low, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor);

                          // Audit log
                          $risk_id = 1000;
                          $message = "A new user was added by the \"" . $_SESSION['user'] . "\" user.";
                          write_log($risk_id, $_SESSION['uid'], $message);

        $alert = "good";
        $alert_message = "The new user was added successfully.";
                        }
      // Otherwise, the user already exists
      else
      {
        $alert = "bad";
        $alert_message = "The username already exists.  Please try again with a different username.";
      }
                }
    // Otherewise, the two passwords are different
    else
    {
        $alert = "bad";
        $alert_message = "The password and repeat password entered were different.  Please try again.";
    }
        }

  // Check if a user was enabled
  if (isset($_POST['enable_user']))
  {
                $value = (int)$_POST['disabled_users'];

                // Verify value is an integer
                if (is_int($value))
                {
                        enable_user($value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "A user was enabled by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "The user was enabled successfully.";
                }
  }

  // Check if a user was disabled
  if (isset($_POST['disable_user']))
  {
                $value = (int)$_POST['enabled_users'];

                // Verify value is an integer
                if (is_int($value))
                {
                        disable_user($value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "A user was disabled by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "The user was disabled successfully.";
                }

  }

        // Check if a user was deleted
        if (isset($_POST['delete_user']))
        {
                $value = (int)$_POST['user'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("user", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing user was deleted by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

      // There is an alert message
      $alert = "good";
      $alert_message = "The existing user was deleted successfully.";
                }
        }

  // Check if a password reset was requeted
        if (isset($_POST['password_reset']))
  {
    $value = (int)$_POST['user'];

                // Verify value is an integer
                if (is_int($value))
                {
                        password_reset_by_userid($value);
              
                        // Audit log
                        $risk_id = 1000;
                       $message = "A password reset request was submitted by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);


                        // There is an alert message
                        $alert = "good";
                        $alert_message = "A password reset email was sent to the user.";
                }
  }

  // Check if the impact update was submitted
        if (isset($_POST['update_impact']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['impact'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("impact", $new_name, $value);

                        // Audit log
                        $risk_id = 1000;
                       $message = "The impact naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

      // There is an alert message
      $alert = "good";
      $alert_message = "The impact naming convention was updated successfully.";
                }
        }

        // Check if the likelihood update was submitted
        if (isset($_POST['update_likelihood']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['likelihood'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("likelihood", $new_name, $value);

                        // Audit log
                        $risk_id = 1000;
                       $message = "The likelihood naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

      // There is an alert message
                        $alert = "good";
                        $alert_message = "The likelihood naming convention was updated successfully.";
                }
        }

        // Check if the mitigation effort update was submitted
        if (isset($_POST['update_mitigation_effort']))
        {
                $new_name = $_POST['new_name'];
                $value = (int)$_POST['mitigation_effort'];

                // Verify value is an integer
                if (is_int($value))
                {
                        update_table("mitigation_effort", $new_name, $value);

                        // Audit log
                        $risk_id = 1000;
                       $message = "The mitigation effort naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

      // There is an alert message
                        $alert = "good";
                        $alert_message = "The mitigation effort naming convention was updated successfully.";
                }
        }

        // If the user has been updated
    if (isset($_POST['update_user']) && isset($_POST['user']))
    {
            // Get the user ID
            $user_id = (int)$_POST['user'];

        // Verify the user ID value is an integer
        if (is_int($user_id))
        {
            // Get the submitted values
            $name = $_POST['name'];
            $email = $_POST['email'];
            $teams = $_POST['team'];
            $language = get_name_by_value("languages", (int)$_POST['languages']);
                    $admin = isset($_POST['admin']) ? '1' : '0';
                    $submit_risks = isset($_POST['submit_risks']) ? '1' : '0';
                    $modify_risks = isset($_POST['modify_risks']) ? '1' : '0';
            $close_risks = isset($_POST['close_risks']) ? '1' : '0';
                    $plan_mitigations = isset($_POST['plan_mitigations']) ? '1' : '0';
                    $review_high = isset($_POST['review_high']) ? '1' : '0';
                    $review_medium = isset($_POST['review_medium']) ? '1' : '0';
                    $review_low = isset($_POST['review_low']) ? '1' : '0';
            $multi_factor = (int)$_POST['multi_factor'];

                        // Create a boolean for all
                        $all = false;

                        // Create a boolean for none
                        $none = false;

            // Set the team to empty to start
            $team = "";

                        // Create the team value
                        foreach ($teams as $value)
                        {
                                // If the selected value is all
                                if ($value == "all") $all = true;

                                // If the selected value is none
                                if ($value == "none") $none = true;

                                $team .= ":";
                                $team .= $value;
                                $team .= ":";
                        }

                        // If all was selected then assign all teams
                        if ($all) $team = "all";

                        // If none was selected then assign no teams
                        if ($none) $team = "none";
                                    // Update the user
            update_user($user_id, $name, $email, $team, $language, $admin, $review_high, $review_medium, $review_low, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing user was modified by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);
                        
            $alert = "good";
                        $alert_message = "The user was updated successfully.";
        }
    }

        // Check if a userid was sent
        if (isset($_POST['user']))
        {
            // Get the user ID
            $user_id = (int)$_POST['user'];
            
                // Get the users information
                $user_info = get_user_by_id($user_id);
        $type = $user_info['type'];
                $username = $user_info['username'];
                $name = $user_info['name'];
                $email = $user_info['email'];
                $last_login = $user_info['last_login'];
        $language = $user_info['lang'];
        $teams = $user_info['teams'];
                $admin = $user_info['admin'];
                $review_high = $user_info['review_high'];
                $review_medium = $user_info['review_medium'];
                $review_low = $user_info['review_low'];
                $submit_risks = $user_info['submit_risks'];
                $modify_risks = $user_info['modify_risks'];
        $close_risks = $user_info['close_risks'];
                $plan_mitigations = $user_info['plan_mitigations'];
        $multi_factor = $user_info['multi_factor'];
        }
    else
    {
        $user_id = "";
                $type = "N/A";
                $username = "N/A";
                $name = "N/A";
                $email = "N/A";
                $last_login = "N/A";
                $teams = "none";
                $admin = false;
                $review_high = false;
                $review_medium = false;
                $review_low = false;
                $submit_risks = false;
                $modify_risks = false;
                $close_risks = false;
                $plan_mitigations = false;
                $multi_factor = 1;
    }

?>

<!doctype html>
<html>
  
  <head>
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/bootstrap-responsive.css"> 
    <style type="text../css">.text-rotation {display: block; -webkit-transform: rotate(-90deg); -moz-transform: rotate(-90deg);}</style>
  </head>
  
  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/bootstrap-responsive.css">
    <link rel="stylesheet" href="css/divshot-util.css">
    <link rel="stylesheet" href="css/divshot-canvas.css">
    <link rel="stylesheet" href="css/display.css">
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li>
                <a href="index.php?module=0"><?php echo $lang['Home']; ?></a> 
              </li>
              <li>
                <a href="index.php?module=1"><?php echo $lang['RiskManagement']; ?></a> 
              </li>
              <li>
                <a href="index.php?module=2"><?php echo $lang['Reporting']; ?></a> 
              </li>
              <li class="active">
                <a href="index.php?module=3"><?php echo $lang['Configure']; ?></a>
              </li>
            </ul>
          </div>
<?php
if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted")
{
          echo "<div class=\"btn-group pull-right\">\n";
          echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">".$_SESSION['name']."<span class=\"caret\"></span></a>\n";
          echo "<ul class=\"dropdown-menu\">\n";
          echo "<li>\n";
          echo "<a href=\"index.php?module=4\">". $lang['MyProfile'] ."</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"logout.php\">". $lang['Logout'] ."</a>\n";
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
            <li <?php if(!isset($_GET['page'])) { ?>class="active"<? } ?>>
              <a href="index.php?module=3"><?php echo $lang['ConfigureRiskFormula']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == '1') { ?>class="active"<? } ?>>
              <a href="index.php?module=3&page=1"><?php echo $lang['ConfigureReviewSettings']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '2') { ?>class="active"<? } ?>>
              <a href="index.php?module=3&page=2"><?php echo $lang['AddAndRemoveValues']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == '3' || $_GET['page'] == '9') { ?>class="active"<? } ?>>
              <a href="index.php?module=3&page=3"><?php echo $lang['UserManagement']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == '4') { ?>class="active"<? } ?>>
              <a href="index.php?module=3&page=4"><?php echo $lang['RedefineNamingConventions']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == '5') { ?>class="active"<? } ?>>
              <a href="index.php?module=3&page=5"><?php echo $lang['AuditTrail']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '6') { ?>class="active"<? } ?>>
              <a href="index.php?module=3&page=6"><?php echo $lang['Extras']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '7') { ?>class="active"<? } ?>>
              <a href="index.php?module=3&page=7"><?php echo $lang['Announcements']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '8') { ?>class="active"<? } ?>>
              <a href="index.php?module=3&page=8"><?php echo $lang['About']; ?></a>        
            </li>
          </ul>
        </div>
        <div class="span9">
          <?php 
            if(!isset($_GET['page'])) {
          ?>
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <h4><?php echo $lang['MyClassicRiskFormulaIs']; ?>:</h4>

                <form name="risk_levels" method="post" action="">
                <p><?php echo $lang['RISK']; ?> = <?php create_dropdown("risk_models", get_setting("risk_model")) ?></p>

                <?php $risk_levels = get_risk_levels(); ?>

                <p><?php echo $lang['IConsiderHighRiskToBeAnythingGreaterThan']; ?>: <input type="text" name="high" size="2" value="<?php echo $risk_levels[2]['value']; ?>" /></p>
                <p><?php echo $lang['IConsiderMediumRiskToBeLessThanAboveButGreaterThan']; ?>: <input type="text" name="medium" size="2" value="<?php echo $risk_levels[1]['value']; ?>" /></p>
                <p><?php echo $lang['IConsiderlowRiskToBeLessThanAboveButGreaterThan']; ?>: <input type="text" name="low" size="2" value="<?php echo $risk_levels[0]['value']; ?>" /></p>

                <input type="submit" value="<?php echo $lang['Update']; ?>" name="update_risk_levels" />

                </form>

                <?php create_risk_table(); ?>

                <?php echo "<p><font size=\"1\">* " . $lang['AllRiskScoresAreAdjusted'] . "</font></p>"; ?>
              </div>
            </div>
          </div>
          <?php 
            } else {
                switch ($_GET['page']) {
                    case 1:
                      get_review_settings(); 
                      break;
                    case 2: 
                      get_add_remove_values(); 
                      break;
                    case 3:
                      get_user_management();
                      break;
                    case 4:
                      get_custom_names();
                      break;
                    case 5:
                      get_admin_audit_trail();
                      break;
                    case 6:
                      get_extras();
                      break;
                    case 7:
                      get_admin_announcements();
                      break;
                    case 8:
                      get_admin_about();
                      break;
                    case 9:
                      get_view_user_details($user_id, $type, $name, $email, $username, $last_login, $language, $teams, $submit_risks, $modify_risks, $close_risks, $plan_mitigations,
$review_low, $review_medium, $review_high, $admin, $multi_factor);
                      break;
                    default:
                      break;
                }
            }
          ?>
        </div>
      </div>
    </div>
  </body>

</html>
