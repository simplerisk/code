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

	// Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $username = $user_info['username'];
        $name = $user_info['name'];
        $email = $user_info['email'];
        $last_login = $user_info['last_login'];
	$teams = $user_info['teams'];
        $admin = $user_info['admin'];
        $review_high = $user_info['review_high'];
        $review_medium = $user_info['review_medium'];
        $review_low = $user_info['review_low'];
        $submit_risks = $user_info['submit_risks'];
        $modify_risks = $user_info['modify_risks'];
        $plan_mitigations = $user_info['plan_mitigations'];
	$close_risks = $user_info['close_risks'];

        // Check if a new password was submitted
        if (isset($_POST['change_password']))
        {
		$user = $_SESSION["user"];
                $current_pass = $_POST['current_pass'];
		$new_pass = $_POST['new_pass'];
                $confirm_pass = $_POST['confirm_pass'];

		// If the user and current password are valid
		if (is_valid_user($user, $current_pass))
		{
                	// Verify that the two passwords are the same
                	if ("$new_pass" == "$confirm_pass")
                	{
                                // Generate the salt
                                $salt = generateSalt($user);

                                // Generate the password hash
                                $hash = generateHash($salt, $new_pass);

				// Update the password
				update_password($user, $hash);

                		// Audit log
                		$risk_id = 1000;
                		$message = "Password was modified for the \"" . $_SESSION['user'] . "\" user.";
                		write_log($risk_id, $_SESSION['uid'], $message);

				// Send an alert
				$alert = "good";
				$alert_message = "Your password has been updated successfully!";

                        }
			else
			{
				// Send an alert
				$alert = "bad";
				$alert_message = "The new password entered does not match the confirm password entered.  Please try again.";
			}
                }
		else
		{
			// Send an alert
			$alert = "bad";
			$alert_message = "You have entered your current password incorrectly.  Please try again.";
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
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li class="active">
                <a href="../index.php">Home</a> 
              </li>
              <li>
                <a href="../management/index.php">Risk Management</a> 
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
          echo "<a href=\"profile.php\">My Profile</a>\n";
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
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <h4>Profile Details</h4>
                Full Name: <input name="name" type="text" maxlength="50" size="20" disabled="disabled" value="<?php echo $name; ?>" /><br />
                E-mail Address: <input name="email" type="text" maxlength="200" size="20" disabled="disabled" value="<?php echo $email; ?>" /><br />
                Username: <input name="username" type="text" maxlength="20" size="20" disabled="disabled" value="<?php echo $username; ?>" /><br />
                Last Login: <input name="last_login" type="text" maxlength="20" size="20" disabled="disabled" value="<?php echo $last_login; ?>" /><br />
                <h6><u>Team(s)</u></h6>
                <?php create_multiple_dropdown("team", $teams); ?>
                <h6><u>User Responsibilities</u></h6>
                <ul>
                  <li><input name="submit_risks" type="checkbox"<?php if ($submit_risks) echo " checked" ?> />&nbsp;Able to Submit New Risks</li>
                  <li><input name="modify_risks" type="checkbox"<?php if ($modify_risks) echo " checked" ?> />&nbsp;Able to Modify Existing Risks</li>
                  <li><input name="close_risks" type="checkbox"<?php if ($close_risks) echo " checked" ?> />&nbsp;Able to Close Risks</li>
                  <li><input name="plan_mitigations" type="checkbox"<?php if ($plan_mitigations) echo " checked" ?> />&nbsp;Able to Plan Mitigations</li>
                  <li><input name="review_low" type="checkbox"<?php if ($review_low) echo " checked" ?> />&nbsp;Able to Review Low Risks</li>
                  <li><input name="review_medium" type="checkbox"<?php if ($review_medium) echo " checked" ?> />&nbsp;Able to Review Medium Risks</li>
                  <li><input name="review_high" type="checkbox"<?php if ($review_high) echo " checked" ?> />&nbsp;Able to Review High Risks</li>
                  <li><input name="admin" type="checkbox"<?php if ($admin) echo " checked" ?> />&nbsp;Allow Access to &quot;Configure&quot; Menu</li>
                </ul>
              </div>
<?php
	if (isset($_SESSION['user_type']) && $_SESSION['user_type'] != "ldap")
	{
        	echo "<div class=\"hero-unit\">\n";
                echo "<h4>Change Password</h4><br />\n";
                echo "<form name=\"change_password\" method=\"post\" action=\"\">\n";
                echo "Current Password: <input maxlength=\"100\" name=\"current_pass\" id=\"current_pass\" class=\"input-medium\" type=\"password\" autocomplete=\"off\" /><br />\n";
		echo "New Password: <input maxlength=\"100\" name=\"new_pass\" id=\"new_pass\" class=\"input-medium\" type=\"password\" autocomplete=\"off\" /><br />\n";
		echo "Confirm Password: <input maxlength=\"100\" name=\"confirm_pass\" id=\"confirm_pass\" class=\"input-medium\" type=\"password\" autocomplete=\"off\" /><br />\n";
                echo "<div class=\"form-actions\">\n";
                echo "<button type=\"submit\" name=\"change_password\" class=\"btn btn-primary\">Submit</button>\n";
                echo "<input class=\"btn\" value=\"Reset\" type=\"reset\">\n";
                echo "</div>\n";
                echo "</form>\n";
                echo "</div>\n";
	}
?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
