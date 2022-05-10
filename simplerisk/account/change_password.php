<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
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
            if(check_add_password_reuse_history($_SESSION["uid"], $hash))
            {
                // Get user old data
                $old_data = get_salt_and_password_by_user_id($_SESSION['uid']);

                // Update the password
                update_password($team, $hash);

                // Add the old data to the pass_history table
                add_last_password_history($_SESSION["uid"], $old_data["salt"], $old_data["password"]);

                // Clean up other sessions of the user and roll the current session's id
                kill_other_sessions_of_current_user();

                // Display an alert
                set_alert(true, "good", $lang['PasswordUpdated']);

                // Redirect to the reports page
                header("Location: ../reports");
            }
            else
            {
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
        set_alert(true, "bad", "You have entered your current password incorrectly.  Please try again.");
    }
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

		display_bootstrap_javascript();
	?>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
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
</head>

<body>


    <?php
        view_top_menu("Configure");

        // Get any alert messages
        get_alert();
    ?>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span4 offset4">
                <div class="well">
                    <?php
                        //if (isset($_SESSION['user_type']) && $_SESSION['user_type'] != "ldap")
                        //{
                            echo "<div class=\"hero-unit\">\n";
                            echo "<form name=\"change_password\" method=\"post\" action=\"\">\n";
                            echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
                            echo "<tr><td colspan=\"2\"><h4>" . $escaper->escapeHtml($lang['ChangePassword']) . "</h4></td><tr>\n";
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
                    //    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
