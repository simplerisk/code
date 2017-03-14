<?php
	/* This Source Code Form is subject to the terms of the Mozilla Public
 	 * License, v. 2.0. If a copy of the MPL was not distributed with this
 	 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

  // Include required functions file
  require_once(realpath(__DIR__ . '/../includes/functions.php'));
  require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));
	require_once(realpath(__DIR__ . '/../includes/messages.php'));
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

        require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

        // Check for session timeout or renegotiation
        session_check();

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

	// If the language was changed
	if (isset($_POST['change_language']))
	{
		$language = (int)$_POST['languages'];

		// If its not the default selection
		if ($language != 0)
		{
			// Update the language for the current user
			update_language($_SESSION['uid'], get_name_by_value("languages", $language));

			// Use the new language file
			require_once(language_file());

			// Display an alert
			set_alert(true, "good", "Your language was updated successfully.");
		}
		else
		{
			// Display an alert
			set_alert(true, "bad", "You need to select a valid language");
		}
	}

        // Include the language file
        require_once(language_file());

	// Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $username = $user_info['username'];
        $name = $user_info['name'];
        $email = $user_info['email'];
	$last_login = date(DATETIME, strtotime($user_info['last_login']));
	$teams = $user_info['teams'];
	$language = $user_info['lang'];
	$asset = $user_info['asset'];
	$assessments = $user_info['assessments'];
        $admin = $user_info['admin'];
	$review_veryhigh = $user_info['review_veryhigh'];
        $review_high = $user_info['review_high'];
        $review_medium = $user_info['review_medium'];
        $review_low = $user_info['review_low'];
	$review_insignificant = $user_info['review_insignificant'];
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
			// Check the password
			$error_code = valid_password($new_pass, $confirm_pass, $_SESSION['uid']);

                	// If the password is valid
                	if ($error_code == 1)
                	{
				// Get user old data
				$old_data = get_salt_and_password_by_user_id($_SESSION['uid']);

				// Add the old data to the pass_history table
				add_last_password_history($_SESSION["uid"], $old_data["salt"], $old_data["password"]);

                                // Generate the salt
                                $salt = generateSalt($user);

                                // Generate the password hash
                                $hash = generateHash($salt, $new_pass);

				// Update the password
				update_password($user, $hash);

                                // If the encryption extra is enabled
                                if (encryption_extra())
                                {
                                        // Load the extra
                                        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

                                        // Set the new encrypted password
                                        set_enc_pass($user, $new_pass, $_SESSION['encrypted_pass']);
                                }

				// Display an alert
				set_alert(true, "good", "Your password has been updated successfully!");

                        }
			else
			{
				// Display an alert
				//set_alert(true, "bad", password_error_message($error_code));
			}
                }
		else
		{
			// Display an alert
			set_alert(true, "bad", "You have entered your current password incorrectly.  Please try again.");
		}
        }
?>

<!doctype html>
<html>

  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">


    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

		<link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <script type="text/javascript">
      $(function(){
          $("#team").multiselect({
              allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllTeams']); ?>',
              includeSelectAllOption: true
          });
      });
    </script>
  </head>

  <body>
<?php
	view_top_menu("Configure");

	// Get any alert messages
	get_alert();
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="change_language" method="post" action="">
                <table border="0" cellspacing="0" cellpadding="0">
                  <tr><td colspan="2"><h4><?php echo $escaper->escapeHtml($lang['ProfileDetails']); ?></h4></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['FullName']); ?>:&nbsp;</td><td><input style="cursor: default;" name="name" type="text" maxlength="50" size="20" title="<?php echo $escaper->escapeHtml($name); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($name); ?>" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['EmailAddress']); ?>:&nbsp;</td><td><input style="cursor: default;" name="email" type="text" maxlength="200" size="20" title="<?php echo $escaper->escapeHtml($email); ?>"disabled="disabled" value="<?php echo $escaper->escapeHtml($email); ?>" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['Username']); ?>:&nbsp;</td><td><input style="cursor: default;" name="username" type="text" maxlength="20" size="20" title="<?php echo $escaper->escapeHtml($username); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($username); ?>" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['LastLogin']); ?>:&nbsp;</td><td><input style="cursor: default;" name="last_login" type="text" maxlength="20" size="20" title="<?php echo $escaper->escapeHtml($last_login); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($last_login); ?>" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['Language']); ?>:&nbsp;</td><td><?php create_dropdown("languages", get_value_by_name("languages", $language)); ?><input type="submit" name="change_language" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" /></td></tr>

		<?php
			// If the API Extra is enabled
			if (api_extra())
			{
				// Require the API Extra
				require_once(realpath(__DIR__ . '/../extras/api/index.php'));

				// Display the API Profile
				display_api_profile();
			}
		?>

                </table>
                </form>
                <h6><u><?php echo $escaper->escapeHtml($lang['Teams']); ?></u></h6>
                <?php create_multiple_dropdown("team", $teams); ?>
                <h6><u><?php echo $escaper->escapeHtml($lang['UserResponsibilities']); ?></u></h6>
		<table border="0" cellspacing="0" cellpadding="0">
		  <tr><td colspan="2"><?php echo $escaper->escapeHtml($lang['RiskManagement']); ?></td></tr>
		  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="submit_risks" type="checkbox"<?php if ($submit_risks) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToSubmitNewRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="modify_risks" type="checkbox"<?php if ($modify_risks) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToModifyExistingRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="close_risks" type="checkbox"<?php if ($close_risks) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToCloseRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="plan_mitigations" type="checkbox"<?php if ($plan_mitigations) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToPlanMitigations']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_insignificant" type="checkbox"<?php if ($review_insignificant) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewInsignificantRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_low" type="checkbox"<?php if ($review_low) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewLowRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_medium" type="checkbox"<?php if ($review_medium) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewMediumRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_high" type="checkbox"<?php if ($review_high) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewHighRisks']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="review_veryhigh" type="checkbox"<?php if ($review_veryhigh) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AbleToReviewVeryHighRisks']); ?></td></tr>
                  <tr><td colspan="2"><?php echo $escaper->escapeHtml($lang['AssetManagement']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="asset" type="checkbox"<?php if ($asset) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AllowAccessToAssetManagementMenu']); ?></td></tr>
                  
                  <tr><td colspan="2"><?php echo $escaper->escapeHtml($lang['Assessments']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="assessments" type="checkbox"<?php if ($assessments) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AllowAccessToAssessmentsMenu']); ?></td></tr>
                  
                  <tr><td colspan="2"><?php echo $escaper->escapeHtml($lang['Configure']); ?></td></tr>
                  <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td><input name="admin" type="checkbox"<?php if ($admin) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['AllowAccessToConfigureMenu']); ?></td></tr>
		</table>
              </div>
<?php
	if (isset($_SESSION['user_type']) && $_SESSION['user_type'] != "ldap")
	{
        	echo "<div class=\"hero-unit\">\n";
		echo "<form name=\"change_password\" method=\"post\" action=\"\">\n";
		echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
		echo "<tr><td colspan=\"2\"><h4>" . $escaper->escapeHtml($lang['ChangePassword']) . "</h4></td><tr>\n";
        
        $html = "";
        $resetRequestMessages = getPasswordReqeustMessages();
        if(count($resetRequestMessages)){
            $html .= "<p><b>Password should have the following requirements.</b></p>\n";
            $html .= "<ul>\n";
            foreach($resetRequestMessages as $resetRequestMessage){
                $html .= "<li>{$resetRequestMessage}</li>\n";
            }
            $html .= "</ul>\n";
        }
        echo $html;
        
		echo "<tr><td>" . $escaper->escapeHtml($lang['CurrentPassword']) . ":&nbsp</td><td><input maxlength=\"100\" name=\"current_pass\" id=\"current_pass\" class=\"input-medium\" type=\"password\" autocomplete=\"off\" /></td></tr>\n";
		echo "<tr><td>" . $escaper->escapeHtml($lang['NewPassword']) . ":&nbsp</td><td><input maxlength=\"100\" name=\"new_pass\" id=\"new_pass\" class=\"input-medium\" type=\"password\" autocomplete=\"off\" /></td></tr>\n";
		echo "<tr><td>" . $escaper->escapeHtml($lang['ConfirmPassword']) . ":&nbsp;</td><td><input maxlength=\"100\" name=\"confirm_pass\" id=\"confirm_pass\" class=\"input-medium\" type=\"password\" autocomplete=\"off\" /></td></tr>\n";
		echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                echo "<button type=\"submit\" name=\"change_password\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Submit']) . "</button>\n";
                echo "<input class=\"btn\" value=\"" . $escaper->escapeHtml($lang['Reset']) . "\" type=\"reset\">\n";
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
