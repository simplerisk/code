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
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
add_session_check();

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

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
            set_alert(true, "good", $lang['LanguageUpdated']);
        }
        else
        {
            // Display an alert
            set_alert(true, "bad", $lang['SelectValidLanguage']);
            // set_alert(true, "bad", "You need to select a valid language");
        }
    }

    $user_id = $_SESSION['uid'];
    // Get the users information
    $user_info = get_user_by_id($user_id);
    $username = $user_info['username'];
    $name = $user_info['name'];
    $email = $user_info['email'];
    $manager = $user_info['manager'] ? get_user_name($user_info['manager']) : "-";
    $last_login = format_date($user_info['last_login']);
    $teams = get_names_by_multi_values('team', $user_info['teams'], true);
    $language = $user_info['lang'];
    $admin = $user_info['admin'];

    $role_id = $user_info['role_id'];
    
    if ($role_id) {
        $role = get_role($role_id);
        if ($role) {
            $role = $role['name'];
        }
    } else {
        $role = "-";
    }

    // Check if a new password was submitted
    if (isset($_POST['change_password']))
    {
        $team = $_SESSION["user"];
        $current_pass = $_POST['current_pass'];
        $new_pass = $_POST['new_pass'];
        $confirm_pass = $_POST['confirm_pass'];

        // If the user and current password are valid
        if (is_valid_user($team, $current_pass))
        {
            // Check the password
            $error_code = valid_password($new_pass, $confirm_pass, $_SESSION['uid']);

            // If the password is valid
            if ($error_code == 1)
            {
                // Generate the salt
                $salt = generateSalt($team);

                // Generate the password hash
                $hash = generateHash($salt, $new_pass);
                
                // If it is possible to reuse password
                if(check_add_password_reuse_history($_SESSION["uid"], $hash)){
                    // Get user old data
                    $old_data = get_salt_and_password_by_user_id($_SESSION['uid']);

                    // Add the old data to the pass_history table
                    add_last_password_history($_SESSION["uid"], $old_data["salt"], $old_data["password"]);

                    // Update the password
                    update_password($team, $hash);

                    // Clean up other sessions of the user and roll the current session's id
                    kill_other_sessions_of_current_user();

                    // Display an alert
                    set_alert(true, "good", $lang['PasswordUpdated']);
                }else{
                    set_alert(true, "bad", $lang['PasswordNoLongerUse']);
                }
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
            set_alert(true, "bad", $lang['PasswordIncorrect']);
        }
    }
    
    // Check if a reset_custom_display_setting button is clicked
    if(isset($_POST['reset_custom_display_settings'])){
        reset_custom_display_settings();
        // Display an alert
        set_alert(true, "good", $lang['CustomResetSuccessMessage']);
        
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

		// Use these jquery-ui scripts
		$scripts = [
			'jquery-ui.min.js',
		];

		// Include the jquery-ui javascript source
		display_jquery_ui_javascript($scripts);

		display_bootstrap_javascript();
	?>
    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/permissions-widget.js?<?php echo current_version("app"); ?>"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>    
    <script type="text/javascript">
        $(document).ready(function(){
        	$(".permissions-widget input[type=checkbox]").prop("readonly", true);

			$('.show-more-teams, .show-less-teams').click(function() {
				$('.teams-limited').toggle();
				$('.teams-all').toggle();
			});

        	
        });
    </script>
    
    <style>
        .profile-table {
            width: 100%;
        }
        .profile-table .profile-data {
            cursor: default;
            font-weight: bold;
            width: 90%;
        }
        
        .profile-table .profile-data-name {
            cursor: default;
            width: 10%;
        }
        
        .profile-table div.profile-data.teams {
            width: 50%;
        }
        
         .profile-table div.profile-data.teams a {
            font-weight: normal;
            cursor: pointer;
            font-size: 14px;
        }
        
        .profile-table td.profile-data-name.teams {
           vertical-align: top;
        }

        input[type=checkbox][readonly] + label {
            color: inherit;
        }
        
        .admin-info:before {
            font-family: "Font Awesome 5 Free";
            font-weight: "900";
            content: "\f05A";
            display: inline-block;
            padding-right: 3px;
            color: red;
        }
    </style>
  </head>

  <body>
<?php
    view_top_menu("Configure");

    // Get any alert messages
    get_alert();
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span12">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="change_language" method="post" action="">
                <table class="profile-table" border="0" cellspacing="0" cellpadding="0">
                  <tr><td colspan="2" class='profile-data-name'><h4><?php echo $escaper->escapeHtml($lang['ProfileDetails']); ?></h4></td></tr>
                  <tr><td class="profile-data-name"><?php echo $escaper->escapeHtml($lang['FullName']); ?>:&nbsp;</td><td class="profile-data"><?php echo $escaper->escapeHtml($name); ?></td></tr>
                  <tr><td class="profile-data-name"><?php echo $escaper->escapeHtml($lang['EmailAddress']); ?>:&nbsp;</td><td class="profile-data"><?php echo $escaper->escapeHtml($email); ?></td></tr>
                  <tr><td class="profile-data-name"><?php echo $escaper->escapeHtml($lang['Username']); ?>:&nbsp;</td><td class="profile-data"><?php echo $escaper->escapeHtml($username); ?></td></tr>
                  <tr><td class="profile-data-name"><?php echo $escaper->escapeHtml($lang['LastLogin']); ?>:&nbsp;</td><td class="profile-data"><?php echo $escaper->escapeHtml($last_login); ?></td></tr>
                  <tr><td class="profile-data-name"><?php echo $escaper->escapeHtml($lang['Manager']); ?>:</td><td class="profile-data"><?php echo $escaper->escapeHtml($manager); ?></td></tr>
                  <tr><td class="profile-data-name teams"><?php echo $escaper->escapeHtml($lang['Teams']); ?>:</td><td><div class="profile-data teams">
                  <?php
                    if ($teams) {
                        $teams = array_map(function($team) use ($escaper) {
                            return $escaper->escapeHtml($team);
                        }, $teams);
                        $names = array();
                        $count = 0;
                        $limit = 3;
                        $limited = count($teams) > $limit;
                        
                        foreach($teams as $team){
                            $names[] = $team;
                            $count += 1;
                            if ($count == $limit)
                                break;
                        }
                        
                        if ($limited) {
                            echo "<div class='teams-limited'>" . implode("<br/>", $names) . "<br/><a class='show-more-teams'>Show more...</a></div>";
                        }
                        echo "<div class='teams-all'" . ($limited ? "style='display: none;'" : "") . ">" . implode("<br/>", $teams) . ($limited ? "<br/><a class='show-less-teams'>Show less...</a> " : "" ) . "</div>";
                    } else {
                        echo "-";
                    }
                  ?></div></td></tr>
                  <tr><td class="profile-data-name"><?php echo $escaper->escapeHtml($lang['Role']); ?>:</td><td><div class="profile-data"><?php echo $escaper->escapeHtml($role); ?></div></td></tr>
                  <tr><td class="profile-data-name"><i class="admin-info"  title="<?php echo $escaper->escapeHtml($lang['AdminRoleDescription']);?>"></i><?php echo $escaper->escapeHtml($lang['Admin']); ?>:</td><td><div class="profile-data"><?php echo $escaper->escapeHtml(localized_yes_no($admin)); ?></div></td></tr>
                  <tr><td class="profile-data-name"><?php echo $escaper->escapeHtml($lang['Language']); ?>:&nbsp;</td><td><?php create_dropdown("languages", get_value_by_name("languages", $language)); ?><input type="submit" name="change_language" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" /></td></tr>
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
                <br>
                <form action="" method="POST">
                    <input name="reset_custom_display_settings" value="<?php echo $lang['ResetCustomDisplaySettings']; ?>" type="submit">
                </form>
                
                <h6>
                    <u><?php echo $escaper->escapeHtml($lang['UserResponsibilities']); ?></u>
                </h6>

                <div class="permissions-widget">
                    <ul>
                        <li>
                            <input class="hidden-checkbox" type="checkbox" id="check_all">
                            <label for="check_all"><?php echo $escaper->escapeHtml($lang['CheckAll']); ?></label>
                            <ul>
<?php
   $permission_groups = get_grouped_permissions($user_id);
   foreach ($permission_groups as $permission_group_name => $permission_group) {
       $permission_group_id = $escaper->escapeHtml("pg-" . $permission_group[0]['permission_group_id']);
       $permission_group_name = $escaper->escapeHtml($permission_group_name);
       $permission_group_description = $escaper->escapeHtml($permission_group[0]['permission_group_description']);
?>       
                                <li>
                                    <input class="hidden-checkbox permission-group" type="checkbox" id="<?php echo $permission_group_id;?>">
                                    <label for="<?php echo $permission_group_id;?>" title="<?php echo $permission_group_description;?>"><?php echo $permission_group_name;?></label>
                                    <ul>
<?php
       foreach ($permission_group as $permission) {
           $permission_id = $escaper->escapeHtml($permission['permission_id']);
           $permission_key = $escaper->escapeHtml($permission['key']);
           $permission_name = $escaper->escapeHtml($permission['permission_name']);
           $permission_description = $escaper->escapeHtml($permission['permission_description']);
           $selected = $permission['selected'];
?>
                                        <li>
                                            <input class="hidden-checkbox permission" type="checkbox" name="permissions[]" id="<?php echo $permission_key;?>" value="<?php echo $permission_id;?>" <?php if ($selected) echo "checked='checked'";?>>
                                            <label for="<?php echo $permission_key;?>" title="<?php echo $permission_description;?>"><?php echo $permission_name;?></label>
                                        </li>
<?php
       }
?>
                                	</ul>
                                </li>
<?php
   }
?>
                            </ul>
                        </li>
                    </ul>
                </div>
              </div>
<?php
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] != "ldap")
    {
            echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"change_password\" method=\"post\" action=\"\">\n";
        echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
        echo "<tr><td colspan=\"2\"><h4>" . $escaper->escapeHtml($lang['ChangePassword']) . "</h4></td></tr>\n";
        
        $html = "<tr><td colspan=\"2\">";
        $resetRequestMessages = getPasswordReqeustMessages();
        if(count($resetRequestMessages)){
            $html .= "<p><b>" . $escaper->escapeHtml($lang['PasswordRequirements']) . "</b></p>\n";
            $html .= "<ul>\n";
            foreach($resetRequestMessages as $resetRequestMessage){
                $html .= "<li>{$resetRequestMessage}</li>\n";
            }
            $html .= "</ul>\n";
        }
        $html .= "</td></tr>";
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
    <?php display_set_default_date_format_script(); ?>

  </body>

</html>
