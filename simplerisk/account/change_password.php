<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
$active_sidebar_menu = "Admin";
$active_sidebar_submenu ="UserManagement";
$title = 'SimpleRisk: Enterprise Risk Management Simplified';
require_once(realpath(__DIR__ . '/../sidebar.php'));

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

                // Expire any active password reset tokens for this user
                $user_info = get_user_by_id($_SESSION["uid"]);
                $username = $user_info['username'];
                expire_reset_token_for_username($username);

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
    <link rel="stylesheet" href="../css/theme.css?<?= $current_app_version ?>">
    <style>
    input[type="password"]{
        max-width: 50% !important;
    }
    </style>
    <div class="page-breadcrumb">
      <div class="row">
        <div class="col-12 d-flex no-block align-items-center">
          <h4 class="page-title"><?= $escaper->escapeHtml($lang['ProfileDetails']); ?></h4>
          <div class="ms-auto text-end">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../account/profile.php">Overview</a></li>
              </ol>
            </nav>
          </div>
        </div>
      </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <form class="form-horizontal" action="" method="post">
                        <div class="card-body">
                            <h4 class="card-title"><?= $escaper->escapeHtml($lang['ChangePassword']);?></h4>

                            <div>
                                <label><?= $escaper->escapeHtml($lang['CurrentPassword']);?></label>
                                <input type="password" class="form-control" id="current_pass" name="current_pass"/>
                            </div> 
                            <div>
                                <label><?=  $escaper->escapeHtml($lang['NewPassword']);?></label>
                                <input type="password" class="form-control" id="new_pass" name="new_pass"/>
                            </div>
                            <div class="form-group">
                                <label><?=  $escaper->escapeHtml($lang['ConfirmPassword']);?></label>
                                <input type="password" class="form-control" id="confirm_pass" name="confirm_pass"/>
                            </div>
                            <div class="form-group">
                                <div>
                                    <button type="submit" class="btn btn-primary" name="change_password" value="submit"><?= $escaper->escapeHtml($lang['Submit']);?></button>

                                    <button type="reset" class="btn"><?= $escaper->escapeHtml($lang['Reset']);?></button>
                                    
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
require_once(realpath(__DIR__ . '/../footer.php'));

get_alert();
setup_alert_requirements("..");
?> 