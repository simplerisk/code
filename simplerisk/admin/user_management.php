<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['tabs:logic', 'multiselect', 'datatables', 'CUSTOM:permissions-widget.js'], ['check_admin' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/messages.php'));
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    $default_role_id = get_default_role_id();

    $separation = team_separation_extra();
    if ($separation) {
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
    }

    // Check if a new user was submitted
    if (isset($_POST['add_user'])) {
        $type = $_POST['type'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $user = $_POST['new_user'];
        $pass = $_POST['password'];
        $manager = (int)$_POST['manager'];

        $repeat_pass = $_POST['repeat_password'];
        $teams = isset($_POST['team']) ? array_filter($_POST['team'], 'ctype_digit') : [];
        $role_id = (int)$_POST['role'];
        
        $admin = isset($_POST['admin']) ? '1' : '0';

        $multi_factor = isset($_POST['multi_factor']) ? 1 : 0;
        $change_password = (int)(isset($_POST['change_password']) ? $_POST['change_password'] : 0);

        $permissions = isset($_POST['permissions']) ? array_filter($_POST['permissions'], 'ctype_digit') : [];

        // If the type is 1
        if ($type == "1") {

            // This is a local SimpleRisk user account
            $type = "simplerisk";

            // Check the password
            $error_code = valid_password($pass, $repeat_pass);
        
            // If the type is 2
        } else if ($type == "2") {

            // This is an LDAP user account
            $type = "ldap";

            // No password check required
            $error_code = 1;
            
        // If the type is 3
        } else if ($type == "3") {

            // This is a SAML user account
            $type = "saml";

            // No password check required
            $error_code = 1;

        } else {

            // This is an invalid type
            $type = "INVALID";

            // Return an error
            $error_code = 0;

        }

        // If the password is valid
        if ($error_code == 1) {

            // Verify that the email address is properly formatted
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

                // Verify that the email does not exist
                if (!email_exist($email)) {

                    // Verify that the user does not exist
                    if (!user_exist($user)) {

                        // Verify that it is a valid username format
                        if (valid_username($user)) {

                            // Create a unique salt for the user
                            $salt = generate_token(20);

                            // Hash the salt
                            $salt_hash = '$2a$15$' . md5($salt);

                            // Generate the password hash
                            $hash = generateHash($salt_hash, $pass);

                            // Insert a new user
                            $user_id = add_user($type, $user, $email, $name, $salt, $hash, $teams, $role_id, $admin, $multi_factor, $change_password, $manager, $permissions);

                            // If the encryption extra is enabled
                            if (encryption_extra()) {

                                // Load the extra
                                require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

                                // If the encryption method is mcrypt
                                if (isset($_SESSION['encryption_method']) && $_SESSION['encryption_method'] == "mcrypt") {

                                    // Add the new encrypted user
                                    add_user_enc($pass, $salt, $user);

                                }
                            }

                            // If ths customization extra is enabled, add new user to custom field as user multi dropdown
                            if(customization_extra()) {

                                // Include the extra
                                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                                add_user_to_custom_fields($user_id);

                            }

                            // Clear values
                            $name = "";
                            $email = "";
                            $user = "";
                            $change_password = 0;

                            // Display an alert
                            set_alert(true, "good", "The new user was added successfully.");

                        // Otherwise, an invalid username was specified
                        } else {

                            // Display an alert
                            set_alert(true, "bad", "An invalid username was specified.  Please try again with a different username.");

                        }
                        
                    // Otherwise, the user already exists
                    } else {

                        // Display an alert
                        set_alert(true, "bad", "The username already exists.  Please try again with a different username.");

                    }
                    
                // Otherwise, the email already exists
                } else {
                    
                    // Display an alert
                    set_alert(true, "bad", "The email already exists.  Please try again with a different email.");

                }
                
            // Otherwise, the email address is invalid
            } else {

                // Display an alert
                set_alert(true, "bad", "An invalid email address was specified.  Please try again with a different email address.");

            }
            
        // Otherewise, an invalid password was specified
        } else {
            // Display an alert
            //set_alert(true, "bad", password_error_message($error_code));
        }
    }

    // Check if a user was enabled
    if (isset($_POST['enable_user'])) {

        $value = (int)$_POST['disabled_users_all'];

        // Verify value is an integer
        if (is_int($value) && $value > 0) {

            enable_user($value);

            // Display an alert
            set_alert(true, "good", "The user was enabled successfully.");

        } else {

            set_alert(true, "bad", $lang['PleaseSelectUser']);

        }
    }

    // Check if a user was disabled
    if (isset($_POST['disable_user'])) {

        $value = (int)$_POST['enabled_users_all'];

        if ($_SESSION['admin'] && $value === (int)$_SESSION['uid']) {

            set_alert(true, "bad", $lang['AdminCantDisableItself']);

        } else {

            if ($value > 0) {

                // Disabling user
                disable_user($value);
                // Killing its active sessions
                kill_sessions_of_user($value);
                // Display an alert
                set_alert(true, "good", "The user was disabled successfully.");

            } else {

                set_alert(true, "bad", $lang['PleaseSelectUser']);

            }
        }
    }

    // Check if a user was deleted
    if (isset($_POST['delete_user'])) {

        $value = (int)$_POST['user'];

        // An admin user can't delete itself
        if ($_SESSION['admin'] && $value === (int)$_SESSION['uid']) {

            set_alert(true, "bad", $lang['AdminCantDeleteItself']);

        } else {

            if ($value > 0) {

                // Delete the user
                delete_value("user", $value);

                // Remove the leftover associations in the related junction tables
                cleanup_after_delete("user");

                // Delete the user from the user_mfa table
                mfa_delete_userid($value);

                // If the encryption extra is enabled
                if (encryption_extra()) {

                    // Load the extra
                    require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

                    // If the encryption method is mcrypt
                    if (isset($_SESSION['encryption_method']) && $_SESSION['encryption_method'] == "mcrypt") {

                        // Delete the value from the user_enc table
                        delete_user_enc($value);

                        // Check to see if all users have now been activated
                        check_all_activated();

                    }
                }

                // Killing its active sessions
                kill_sessions_of_user($value);
                
                // Display an alert
                set_alert(true, "good", "The existing user was deleted successfully.");

            } else {

                set_alert(true, "bad", $lang['PleaseSelectUser']);

            }
        }
    }

    // Check if a password reset was requested
    if (isset($_POST['password_reset'])) {

        // Get the POSTed user ID
        $value = (int)$_POST['user'];

        // Verify value is an integer
        if (is_int($value) && $value > 0) {

            // Open the database connection
            $db = db_open();

            // Get any password resets for this user in the past 10 minutes
            $stmt = $db->prepare("
                SELECT 
                    * 
                FROM 
                    password_reset pw 
                    LEFT JOIN user u ON pw.username = u.username 
                WHERE 
                    pw.timestamp >= NOW() - INTERVAL 10 MINUTE AND u.value=:value;
            ");
            $stmt->bindParam(":value", $value, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Close the database connection
            db_close($db);

            // If we have password resets in the past 10 minutes
            if (count($results) != 0) {

                set_alert(true, "bad", $lang['PasswordResetRequestsExceeded']);

            } else {

                password_reset_by_userid($value);

                // Display an alert
                set_alert(true, "good", "A password reset email was sent to the user.");

            }

        } else {

            set_alert(true, "bad", $lang['PleaseSelectUser']);

        }
    }

    // Check if a MFA reset was requested
    if (isset($_POST['mfa_reset'])) {

        // Get the user to reset MFA for
        $user_id = isset($_POST['user']) ? $_POST['user'] : null;

        // Set reset_mfa to false
        $reset_mfa = false;

        // If the user has MFA enabled
        if (mfa_enabled_for_uid($_SESSION['uid'])) {

            // If the user's MFA token is valid
            if (does_mfa_token_match()) {

                // If MFA is enabled for the selected user
                if (mfa_enabled_for_uid($user_id)) {

                    // We should reset the MFA
                    $reset_mfa = true;

                // MFA is not enabled for the selected user
                } else {

                    // Display an alert
                    set_alert(true, "bad", $lang['MFANotEnabledForUser']);

                }
                
            // An invalid MFA token was provided
            } else {

                // Display an alert
                set_alert(true, "bad", $lang['MFAVerificationFailed']);

            }
        
        // The user does not have MFA enabled so just reset it
        } else {

            // If MFA is enabled for the selected user
            if (mfa_enabled_for_uid($user_id)) {

                // We should reset the MFA
                $reset_mfa = true;

            // MFA is not enabled for the selected user
            } else {

                // Display an alert
                set_alert(true, "bad", $lang['MFANotEnabledForUser']);

            }
        }

        // If we passed the reset MFA check
        if ($reset_mfa) {

            // Reset the MFA for the user
            mfa_delete_userid($user_id);

            // Display an alert
            set_alert(true, "good", $lang['MFAResetSuccessful']);

        }
    }

    // Check if a password policy update was requested
    if (isset($_POST['password_policy_update'])) {
        $strict_user_validation = (isset($_POST['strict_user_validation'])) ? 1 : 0;
        $mfa_required = (isset($_POST['mfa_required'])) ? 1 : 0;
        $pass_policy_enabled = (isset($_POST['pass_policy_enabled'])) ? 1 : 0;
        $min_characters = (int)$_POST['min_characters'];
        $alpha_required = (isset($_POST['alpha_required'])) ? 1 : 0;
        $upper_required = (isset($_POST['upper_required'])) ? 1 : 0;
        $lower_required = (isset($_POST['lower_required'])) ? 1 : 0;
        $digits_required = (isset($_POST['digits_required'])) ? 1 : 0;
        $special_required = (isset($_POST['special_required'])) ? 1 : 0;

        $pass_policy_attempt_lockout =(int)$_POST['pass_policy_attempt_lockout'];
        $pass_policy_attempt_lockout_time = (int)$_POST['pass_policy_attempt_lockout_time'];
        $pass_policy_min_age = (int)$_POST['pass_policy_min_age'];
        $pass_policy_max_age = (int)$_POST['pass_policy_max_age'];
        $pass_policy_reuse_limit = (int)$_POST['pass_policy_reuse_limit'];

        update_password_policy($strict_user_validation, $mfa_required, $pass_policy_enabled, $min_characters, $alpha_required, $upper_required, $lower_required, $digits_required, $special_required, $pass_policy_attempt_lockout, $pass_policy_attempt_lockout_time, $pass_policy_min_age, $pass_policy_max_age, $pass_policy_reuse_limit);

        // Display an alert
        set_alert(true, "good", "The settings were updated successfully.");
    }

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="mt-2">
            <nav class="nav nav-tabs">
                <a class="nav-link active" id="addusers-tab" data-bs-toggle="tab" data-bs-target="#addusers" type="button" role="tab" aria-controls="addusers" aria-selected="true">
                    <?= $escaper->escapeHtml($lang['AddUsers']); ?> 
                </a>
                <a class="nav-link" id="manageusers-tab" data-bs-toggle="tab" data-bs-target="#manageusers" type="button" role="tab" aria-controls="manageusers" aria-selected="false">
                    <?= $escaper->escapeHtml($lang['ManageUsers']); ?>
                </a>
                <a class="nav-link" id="usersettings-tab" data-bs-toggle="tab" data-bs-target="#usersettings" type="button" role="tab" aria-controls="usersettings" aria-selected="false">
                    <?= $escaper->escapeHtml($lang['UserSettings']); ?>
                </a>
                <a class="nav-link" id="userreports-tab" data-bs-toggle="tab" data-bs-target="#userreports" type="button" role="tab" aria-controls="userreports" aria-selected="false">
                    <?= $escaper->escapeHtml($lang['UserReports']); ?>
                </a>
            </nav>
        </div>
        <div class="tab-content cust-tab-content" id="content" >
            <div class="tab-pane active risk-levels-container settings_tab" id="addusers" role="tabpanel" aria-labelledby="addusers-tab">
                <div class="card-body my-2 border">
                    <form name="add_user" method="post" action="">
                        <h4><?= $escaper->escapeHtml($lang['AddANewUser']); ?></h4>
                        <div class="row">
                            <div class="col-md-8"> 
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['Type']); ?> :</label>
                                    <select name="type" id="select" class="form-select">
                                        <option selected value="1">SimpleRisk</option>
    <?php
        // If the custom authentication extra is enabeld
        if (custom_authentication_extra()) {
            // Display the LDAP option
            echo "
                                        <option value='2'>LDAP</option>
            ";

            // Display the SAML option
            echo "
                                        <option value='3'>SAML</option>
            ";
        }
    ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['FullName']); ?> :</label>
                                    <input name="name" type="text" maxlength="50" size="20" value="<?= isset($name) ? $escaper->escapeHtml($name) : "" ?>" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['EmailAddress']); ?> :</label>
                                    <input name="email" type="email" maxlength="200" value="<?= isset($email) ? $escaper->escapeHtml($email) : "" ?>" size="20" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['Username']); ?> :</label>
                                    <input name="new_user" type="text" maxlength="200" value="<?= isset($user) ? $escaper->escapeHtml($user) : "" ?>" size="20" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['Password']); ?> :</label>
                                    <input name="password" type="password" maxlength="50" size="20" autocomplete="off" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['RepeatPassword']); ?> :</label>
                                    <input name="repeat_password" type="password" maxlength="50" size="20" autocomplete="off" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <input name="multi_factor" id="multi_factor" <?php if(isset($multi_factor) && $multi_factor == 1) echo "checked"; ?> <?php if(get_setting("mfa_required")) echo "checked readonly=\"readonly\""; ?> class="form-check-input" type="checkbox" value="1" />  <label for="multi_factor" class="form-check-label mb-0">  &nbsp;&nbsp;&nbsp; <?= $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?> </label> 
                                </div>
                                <div class="form-group">
                                    <input name="change_password" id="change_password" <?php if(isset($change_password) && $change_password == 1) echo "checked"; ?> class="form-check-input" type="checkbox" value="1" />  <label for="change_password" class="form-check-label mb-0">  &nbsp;&nbsp;&nbsp; <?= $escaper->escapeHtml($lang['RequirePasswordChangeOnLogin']); ?> </label> 
                                </div>
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['Manager']); ?> :</label>
    <?php 
                                    create_dropdown("enabled_users_all", "", "manager"); 
    ?>
                                </div>
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['Teams']); ?> :</label>
    <?php 
                                    create_multiple_dropdown("team", null, null, get_all_teams()); 
    ?>
                                </div>
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['Role']); ?> :</label>
    <?php 
                                    create_dropdown("role", $default_role_id); 
        if ($default_role_id) {
            echo "
                                    <script>
                                        $(document).ready(function() {
                                            get_responsibilities({$default_role_id});
                                        });
                                    </script>
            ";
        }
    ?>
                                </div>
                                <div class="form-group admin-button">
                                    <button id="admin_button" type="button" class="btn btn-dark" data-grant="<?= $escaper->escapeHtml($lang['GrantAdmin']); ?>" data-remove="<?= $escaper->escapeHtml($lang['RemoveAdmin']); ?>" title="<?= $escaper->escapeHtml($lang['AdminRoleDescription']);?>"><?= $escaper->escapeHtml($lang['GrantAdmin']);?></button>
                                    <input type="checkbox" name="admin" id="admin">
									<div class="mt-2 col-12 form-group alert alert-danger admin-alert" role="alert">
                            			<?= $escaper->escapeHtml($lang['UserResponsibilitiesAndTeamsCannotBeEditedWhenTheUserIsGoingToBeAnAdmin']); ?>
                        			</div>
                                </div>
                                <h4><?= $escaper->escapeHtml($lang['UserResponsibilities']); ?> :</h4>
                                <div class="form-check">
                                    <div class="permissions-widget">
                                        <ul>
                                            <li>
                                                <input class="form-check-input" type="checkbox" id="check_all">
                                                <label for="check_all" class="form-check-label mb-0"> &nbsp;&nbsp;&nbsp; <?= $escaper->escapeHtml($lang['CheckAll']); ?></label>
                                                <ul>
    <?php
        $permission_groups = get_grouped_permissions();

        foreach ($permission_groups as $permission_group_name => $permission_group) {
        $permission_group_id = $escaper->escapeHtml("pg-" . $permission_group[0]['permission_group_id']);
        $permission_group_name = $escaper->escapeHtml($permission_group_name);
        $permission_group_description = $escaper->escapeHtml($permission_group[0]['permission_group_description']);
    ?>       
                                                    <li>
                                                        <input class="form-check-input permission-group" type="checkbox" id="<?= $permission_group_id;?>">
                                                        <label for="<?= $permission_group_id;?>" title="<?= $permission_group_description;?>" class="form-check-label mb-0"> &nbsp;&nbsp;&nbsp; <?= $permission_group_name;?></label>
                                                        <ul>
    <?php
            foreach ($permission_group as $permission) {
                $permission_id = $escaper->escapeHtml($permission['permission_id']);
                $permission_key = $escaper->escapeHtml($permission['key']);
                $permission_name = $escaper->escapeHtml($permission['permission_name']);
                $permission_description = $escaper->escapeHtml($permission['permission_description']);
    ?>       
                                                            <li>
                                                                <input class="form-check-input permission" type="checkbox" name="permissions[]" id="<?= $permission_key;?>" value="<?= $permission_id;?>">
                                                                <label for="<?= $permission_key;?>" title="<?= $permission_description;?>" class="form-check-label mb-0"> &nbsp;&nbsp;&nbsp; <?= $permission_name;?></label>
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
                                <div class="mt-3">
                                    <input type="submit" value="<?= $escaper->escapeHtml($lang['Add']); ?>" name="add_user" class="btn btn-submit"/>
                                </div>
                            </div>
                        </div>  
                    </form>
                </div>
            </div>
            <div class="tab-pane settings_tab" id="manageusers" role="tabpanel" aria-labelledby="manageusers-tab">
                <div class="card-body my-2 border">
                    <form name="view_user_details" method="post" action="view_user_details.php">
                        <h4><?= $escaper->escapeHtml($lang['ViewDetailsForUser']); ?></h4>
                        <div class="row" style="align-items:flex-end">
                            <div class="col-md-4">
                                <label><?= $escaper->escapeHtml($lang['DetailsForUser']); ?> :</label>
    <?php 
                                create_dropdown('enabled_users_all', null, 'user'); 
    ?>
                            </div>
                            <div class="col-md-2">
                                <input type="submit" value="<?= $escaper->escapeHtml($lang['Select']); ?>" name="select_user" class="btn btn-dark"/>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-body my-2 border">
                    <form name="enable_disable_user" method="post" action="">
                        <h4><?= $escaper->escapeHtml($lang['EnableAndDisableUsers']); ?></h4>
                        <p><?= $escaper->escapeHtml($lang['EnableAndDisableUsersHelp']); ?>.</p>
                        <div class="row" style="align-items:flex-end">
                            <div class="form-group col-md-4">
                                <label> <?= $escaper->escapeHtml($lang['DisableUser']); ?> :</label>
    <?php 
                                create_dropdown("enabled_users_all"); 
    ?>
                            </div>
                            <div class="form-group col-md-2">
                                <input type="submit" value="<?= $escaper->escapeHtml($lang['Disable']); ?>" name="disable_user" class="btn btn-submit"/>
                            </div>
                        </div>
                        <div class="row" style="align-items:flex-end">
                            <div class="col-md-4">
                                <label> <?= $escaper->escapeHtml($lang['EnableUser']); ?> :</label>
    <?php 
                                create_dropdown("disabled_users_all"); 
    ?>
                            </div>
                            <div class="col-md-2">
                                <input type="submit" value="<?= $escaper->escapeHtml($lang['Enable']); ?>" name="enable_user" class="btn btn-submit"/>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-body my-2 border">
                    <form name="delete_user" method="post" action="">
                        <h4><?= $escaper->escapeHtml($lang['DeleteAnExistingUser']); ?></h4>
                        <div class="row" style="align-items:flex-end">
                            <div class="col-md-4">
                                <label><?= $escaper->escapeHtml($lang['DeleteCurrentUser']); ?> :</label>
    <?php 
                                create_dropdown("user"); 
    ?>
                            </div>
                            <div class="col-md-2">
                                <input type="submit" value="<?= $escaper->escapeHtml($lang['Delete']); ?>" name="delete_user" class="btn btn-submit"/>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-body my-2 border">
                    <form name="password_reset" method="post" action="">
                        <h4><?= $escaper->escapeHtml($lang['PasswordReset']); ?></h4>
                        <div class="row" style="align-items:flex-end">
                            <div class="col-md-4">
                                <label><?= $escaper->escapeHtml($lang['SendPasswordResetEmailForUser']); ?> :</label>
    <?php 
                                create_dropdown("user"); 
    ?>
                            </div>
                            <div class="col-md-2">
                                <input type="submit" value="<?= $escaper->escapeHtml($lang['Send']); ?>" name="password_reset" class="btn btn-submit"/>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-body my-2 border">
                    <form name="mfa_reset" method="post" action="">
                        <h4><?= $escaper->escapeHtml($lang['MFAReset']); ?></h4>
                        <div class="row" style="align-items:flex-end">
                            <div class="form-group col-md-4">
                                <label><?= $escaper->escapeHtml($lang['PerformMFAResetForUser']); ?> :</label>
    <?php 
                                create_dropdown("user"); 
    ?>
                            </div>
                        </div>
<?php
    // If the user has MFA enabled display the token input
    if (mfa_enabled_for_uid($_SESSION['uid'])) {
?>
                        <div class="row" style="align-items:flex-end">
                            <div class="form-group col-md-4">
                                <label><?= $escaper->escapeHtml($lang['MFAToken']); ?> :</label>
                                <input name='mfa_token' type='number' minlength='6' maxlength='6' autofocus='autofocus' class='form-control m-r-20'/>
                            </div>
                        </div>
<?php
    }
?>
                        <div class="row" style="align-items:flex-end">
                            <div class="col-md-2">
                                <input type="submit" value="<?= $escaper->escapeHtml($lang['ResetMFA']); ?>" name="mfa_reset" class="btn btn-submit"/>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="tab-pane settings_tab" id="usersettings" role="tabpanel" aria-labelledby="usersettings-tab">
                <form name="password_policy" method="post" action="">
                    <div class="row">
                        <div class="col-6 d-flex">
                            <div class="card-body my-2 border">
                                <h4><?= $escaper->escapeHtml($lang['UserPolicy']); ?></h4>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="strict_user_validation" name="strict_user_validation"<?php if (get_setting('strict_user_validation') == 1) echo " checked" ?> />
                                    <label class="form-check-label mb-0">&nbsp;&nbsp;&nbsp;<?= $escaper->escapeHtml($lang['UseCaseSensitiveValidationOfUsername']); ?></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="mfa_required" name="mfa_required"<?php if (get_setting('mfa_required') == 1) echo " checked" ?> />
                                    <label for="mfa_required" class="form-check-label mb-0">&nbsp;&nbsp;&nbsp;<?= $escaper->escapeHtml($lang['RequireMFAForAllUsers']); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card-body my-2 border">
                                <h4><?= $escaper->escapeHtml($lang['AccountLockoutPolicy']); ?></h4>
                                <div class="form-group">
                                    <label class="d-flex align-items-center">
                                        <?= $escaper->escapeHtml($lang['MaximumAttemptsLockout']); ?>:&nbsp;&nbsp;
                                        <input class="form-control" style="width:60px" type="number" id="pass_policy_attempt_lockout" name="pass_policy_attempt_lockout" min="0" maxlength="2" size="2" value="<?= $escaper->escapeHtml(get_setting('pass_policy_attempt_lockout')); ?>"/> 
                                        <?= $escaper->escapeHtml($lang['attempts']); ?>.&nbsp;&nbsp;[0 = Lockout Disabled]
                                    </label>
                                </div>
                                <div>
                                    <label class="d-flex align-items-center">
                                        <?= $escaper->escapeHtml($lang['MaximumAttemptsLockoutTime']); ?>:&nbsp;&nbsp;
                                        <input class="form-control" style="width:60px" type="number" id="pass_policy_attempt_lockout_time" name="pass_policy_attempt_lockout_time" min="0" maxlength="2" size="2" value="<?= $escaper->escapeHtml(get_setting('pass_policy_attempt_lockout_time')); ?>"/> 
                                        <?= $escaper->escapeHtml($lang['minutes']); ?>.&nbsp;&nbsp;[0 = Manual Enable Required]
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body mb-2 border">
                        <h4><?= $escaper->escapeHtml($lang['PasswordPolicy']); ?></h4>
                        <div class="form-check col-md-6">
                            <input class="form-check-input" type="checkbox" id="pass_policy_enabled" name="pass_policy_enabled"<?php if (get_setting('pass_policy_enabled') == 1) echo " checked" ?> />
                            <label for="pass_policy_enabled" class="form-check-label mb-0">&nbsp;&nbsp;<?= $escaper->escapeHtml($lang['Enabled']); ?></label>
                        </div>
                        <div class="form-check col-md-6 my-2">
                            <label class="d-flex align-items-center">
                                <?= $escaper->escapeHtml($lang['MinimumNumberOfCharacters']); ?>:&nbsp;&nbsp;
                                <input class="form-control" style="width: 10%" type="number" id="min_characters" name="min_characters" min="1" max="50" maxlength="2" size="2" value="<?= $escaper->escapeHtml(get_setting('pass_policy_min_chars')); ?>"/> [1-50]
                            </label>
                        </div>
                        <div class="form-check col-md-6">
                            <input class="form-check-input" type="checkbox"  id="alpha_required" name="alpha_required"<?php if (get_setting('pass_policy_alpha_required') == 1) echo " checked" ?>  />
                            <label for="alpha_required" class="form-check-label">
                                &nbsp;&nbsp;<?= $escaper->escapeHtml($lang['RequireAlphaCharacter']); ?></label>
                        </div>
                        <div class="form-check col-md-6">
                            <input class="form-check-input" type="checkbox" id="upper_required" name="upper_required"<?php if (get_setting('pass_policy_upper_required') == 1) echo " checked" ?>  />
                            <label for="upper_required" class="form-check-label">&nbsp;&nbsp;<?= $escaper->escapeHtml($lang['RequireUpperCaseCharacter']); ?></label>
                        </div>
                        <div class="form-check col-md-6">
                            <input class="form-check-input" type="checkbox" id="lower_required" name="lower_required"<?php if (get_setting('pass_policy_lower_required') == 1) echo " checked" ?>  />
                            <label for="lower_required" class="form-check-label">&nbsp;&nbsp;<?= $escaper->escapeHtml($lang['RequireLowerCaseCharacter']); ?></label>
                        </div>
                        <div class="form-check col-md-6">
                            <input class="form-check-input" type="checkbox" id="digits_required" name="digits_required"<?php if (get_setting('pass_policy_digits_required') == 1) echo " checked" ?>  />
                            <label for="digits_required" class="form-check-label">&nbsp;&nbsp;<?= $escaper->escapeHtml($lang['RequireNumericCharacter']); ?></label>
                        </div>
                        <div class="form-check col-md-6">
                            <input class="form-check-input" type="checkbox" id="special_required" name="special_required"<?php if (get_setting('pass_policy_special_required') == 1) echo " checked" ?> />
                            <label for="special_required" class="form-check-label">&nbsp;&nbsp;<?= $escaper->escapeHtml($lang['RequireSpecialCharacter']); ?></label>
                        </div>
                        <div class="form-check  col-md-6 my-2">
                            <label class="d-flex align-items-center">
                                <?= $escaper->escapeHtml($lang['MinimumPasswordAge']); ?>:&nbsp;&nbsp;
                                <input class="form-control" style="width:10%" type="number" id="pass_policy_min_age" name="pass_policy_min_age" min="0" maxlength="4" size="2" value="<?= $escaper->escapeHtml(get_setting('pass_policy_min_age')); ?>"/> <?= $escaper->escapeHtml($lang['days']); ?>.&nbsp;&nbsp;
                                [0 = Min Age Disabled]
                            </label>
                        </div>
                        <div class="form-check col-md-6 my-2">
                            <label class="d-flex align-items-center">
                                <?= $escaper->escapeHtml($lang['MaximumPasswordAge']); ?>:&nbsp;&nbsp;
                                <input class="form-control" style="width:10%" type="number" id="pass_policy_max_age" name="pass_policy_max_age" min="0" maxlength="4" size="2" value="<?= $escaper->escapeHtml(get_setting('pass_policy_max_age')); ?>"/> <?= $escaper->escapeHtml($lang['days']); ?>.&nbsp;&nbsp;
                                [0 = Max Age Disabled]
                            </label>
                        </div>
                        <div class="form-check col-md-6 my-2">
                            <label class="d-flex align-items-center">
                                <?= $escaper->escapeHtml($lang['RememberTheLast']); ?>&nbsp;&nbsp;
                                <input class="form-control" class="text-right" style="width:12%" type="number" id="pass_policy_reuse_limit" name="pass_policy_reuse_limit" min="0" maxlength="4" size="2" value="<?= $escaper->escapeHtml(get_setting('pass_policy_reuse_limit')); ?>"/> 
                                <?= $escaper->escapeHtml($lang['Passwords']); ?>
                            </label>
                        </div>
                    </div>
                    <div class="card-body mb-2 border">
                        <input type="submit" value="<?= $escaper->escapeHtml($lang['Update']); ?>" name="password_policy_update" class="btn btn-submit"/>
                    </div>
                </form>
            </div>
            <div class="tab-pane" id="userreports" role="tabpanel" aria-labelledby="userreports-tab">
                <div class="card-body my-2 border">
                    <div data-sr-role='dt-settings' data-sr-target='users_of_teams-table' class="d-flex align-items-center float-end">
                        <label style="width: 200px;"><?= $escaper->escapeHtml($lang['ReportDisplayed']); ?> :</label>
                        <select id="report_displayed_dropdown" class="form-select">
    <?php if ($separation) { ?>
                            <option value='users_of_teams' selected><?= $escaper->escapeHtml($lang['UsersOfTeams']);?></option>
                            <option value='teams_of_users'><?= $escaper->escapeHtml($lang['TeamsOfUsers']);?></option>
    <?php } ?>
                            <option value='users_of_permissions' <?php if (!$separation) { ?>selected<?php } ?>><?= $escaper->escapeHtml($lang['UsersOfPermissions']);?></option>
                            <option value='permissions_of_users'><?= $escaper->escapeHtml($lang['PermissionsOfUsers']);?></option>
                            <option value='users_of_roles'><?= $escaper->escapeHtml($lang['UsersOfRoles']);?></option>
                        </select>
                    </div>
                    <div class="col-12">
    <?php if ($separation) { ?>
                        <div id="users_of_teams-report" class="report">
    <?php 
                            display_user_management_reports_datatable('users_of_teams'); 
    ?>
                        </div>
    
                        <div id="teams_of_users-report" style="display: none" class="report">
    <?php 
                            display_user_management_reports_datatable('teams_of_users'); 
    ?>
                        </div>
    <?php } ?>
                        
                        <div id="users_of_permissions-report" class="report" <?php if ($separation) { ?>style="display: none"<?php } ?>>
    <?php 
                            display_user_management_reports_datatable('users_of_permissions'); 
    ?>
                        </div>
    
                        <div id="permissions_of_users-report" class="report" style="display: none">
    <?php 
                            display_user_management_reports_datatable('permissions_of_users'); 
    ?>
                        </div>
    
                        <div id="users_of_roles-report" class="report" style="display: none">
    <?php 
                            display_user_management_reports_datatable('users_of_roles'); 
    ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    var reportDatatables = ["users_of_teams-table", "teams_of_users-table", "users_of_permissions-table", "permissions_of_users-table", "users_of_roles-table"];

    $(document).ready(function(){

        $("#team").multiselect({
            allSelectedText: "<?= $escaper->escapeHtml($lang['AllTeams']) ?>",
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
            buttonWidth: "100%"
        });

        if ($("#admin").is(":checked")) {
            check_indeterminate_checkboxes($(".permissions-widget #check_all"));
            make_checkboxes_readonly();
        }

        update_admin_button();
        
        $("#role").change(function(){
            // If role is unselected, uncheck all responsibilities
            if(!$(this).val()) {
                $("#admin").prop("checked", false);
                $("#default").prop("checked", false);
                $("#admin").prop("readonly", false);
                $("#team").multiselect("enable");
    
                $(".permissions-widget input[type=checkbox]").prop("checked", false);
                $(".permissions-widget input[type=checkbox]").prop("indeterminate", false);
                make_checkboxes_editable();
                
                update_admin_button();
            } else {
                
                $("#admin").prop("checked", false);
                
                get_responsibilities($(this).val());
                
            }
        });
        
        $("#admin_button").click(function(){
            $("#admin").prop("checked", !$("#admin").prop("checked"));
            if ($("#admin").prop("checked")) {
                $(".permissions-widget input[type=checkbox]").prop("checked", true);
                check_indeterminate_checkboxes($(".permissions-widget #check_all"));
                make_checkboxes_readonly();
            } else {
                make_checkboxes_editable();
            }
            update_admin_button();
        });

        if ($("#report_displayed_dropdown").val()) {
            let report_displayed_val = $("#report_displayed_dropdown").val();
            activateDatatable(`${report_displayed_val}-table`);
        }

        $("#report_displayed_dropdown").change(function() {
            datatableWrapperId = this.value + "-report";
            datatableId = this.value + "-table";

            $("#userreports .report").hide();
            $("#" + datatableWrapperId).show();

            if (!(datatableId in reportDatatables)) {
                activateDatatable(datatableId);
            } else {
                // Need it because if the table is redrawn due to the filtering logic while it\'s not the active tab
                // then the header columns need to be re-adjusted
                reportDatatables[datatableId].columns.adjust();
            }

            // Move the Report Displayed to the top right of the CORRECT datatable
            $("[data-sr-role='dt-settings']").attr("data-sr-target", datatableId);
            $("[data-sr-role='dt-settings']").appendTo($('#' + datatableId).closest('div.dt-container').find('div.settings'));

        });

        $("[name=select_user]").click(function(event){
            var user_id = $("[name=view_user_details]").find("[name=user]").val();
            if(user_id) return $("[name=view_user_details]").submit();
            else {
                showAlertFromMessage("<?= $escaper->escapeHtml($lang['PleaseSelectUser']) ?>", false);
                return false;
            }
        });

    });

    function update_admin_button() {
        admin = $("#admin").prop("checked");
        admin_button = $("#admin_button");
        remove_text = admin_button.data("remove");
        grant_text = admin_button.data("grant");

        $("#admin_button").text(admin ? remove_text : grant_text);
        $("#admin_button").prop("disabled", $("#admin").prop("readonly"));
        $("#team").multiselect(admin ? "disable" : "enable");
        $("#team").prop("disabled", false);
        if (admin) {
            $("#team").multiselect("selectAll", false);
            $("#team").multiselect("refresh");
        }
    }
    
    function get_responsibilities(role_id) {
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/role_responsibilities/get_responsibilities",
            data: {
                role_id: role_id
            },
            success: function(data) {

                if (data.data) {
                    
                    $("#admin").prop("checked", data.data.admin);
                    $("#admin").prop("readonly", data.data.value == "1");

                    update_widget(data.data.responsibilities);

                    if (data.data.admin) {
                        check_indeterminate_checkboxes($(".permissions-widget #check_all"));
                        make_checkboxes_readonly();

                        // Set all teams
                        $("#team").multiselect("selectAll", false);
                        $("#team").multiselect("refresh");
                        $("#team").multiselect("disable");
                        $("#team").prop("disabled", false);
                    } else {
                        $("#team").multiselect("enable");
                    }
                    update_admin_button();
                }
            },
            error: function(xhr,status,error) {
                if(xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }

    var filterSubmitTimer = [];


    function activateDatatable(id) {
        var $this = $("#" + id);
        var type = $this.data("type");

        var reportDatatable = $this.DataTable({
            scrollX: true,
            bSort: true,
            bSortCellsTop: true,
            order: [[0, "asc"]],
            columnDefs : [{
                "targets" : [-1],
                "orderable": false
            }],
            ajax: {
                url: BASE_URL + "/api/reports/user_management_reports",
                type: "POST",
                data: function(d) {
                    d.type = type;
                    d.columnFilters = {};
                    if(typeof(reportDatatables[id]?.table()?.header()) !== 'undefined') {
                        $("select.column-filter-dropdown", reportDatatables[id].table().header()).each(function(){
                            d.columnFilters[$(this).data("name")] = $(this).val();
                        });
                    }
                },
                error: function(xhr, status, error){
                    if(!retryCSRF(xhr, this)) {}
                }
            },
            initComplete: function(){
                var self = this;
                    
                $.ajax({
                    type: "GET",
                    url: BASE_URL + "/api/reports/user_management_reports_unique_column_data?type=" + type,
                    dataType: "json",
                    success: function(data){
                        var header = self.api().table().header();

                        $("tr.filter th", header).each(function(){
                            var column = $(this);
                            var columnName = column.data("name").toLowerCase();

                            var options = data[columnName];

                            options.sort(function(o1, o2) {
                                var t1 = o1.text.toLowerCase(), t2 = o2.text.toLowerCase();
                                return t1 > t2 ? 1 : t1 < t2 ? -1 : 0;
                            });
                            column.html("");

                            var select = $("<select class=\'column-filter-dropdown form-select\' data-table=\'" + id + "\'data-name=\'" + columnName + "\' multiple></select>").appendTo(column);

                            // Have to add this as it is possible to have users without a team and we want to filter on those
                            if (id === "users_of_teams-table" && columnName === "teams") {
                                select.append($("<option value=\'-1\' selected><?= $escaper->escapeHtml($lang['UsersWithoutTeam']) ?></option>"));
                            } else
                            
                            // Have to add this as it is possible to have users without a team and we want to filter on those
                            if (id === "teams_of_users-table" && columnName === "teams") {
                                select.append($("<option value=\'-1\' selected><?= $escaper->escapeHtml($lang['UsersWithoutTeam']) ?></option>"));
                            } else

                            // Have to add this as it is possible to have users without a permission and we want to filter on those
                            if (id === "users_of_permissions-table" && columnName === "permissions") {
                                select.append($("<option value=\'-1\' selected><?= $escaper->escapeHtml($lang['UsersWithoutPermission']) ?></option>"));
                            } else
                                
                            // Have to add this as it is possible to have users without a permission and we want to filter on those
                            if (id === "permissions_of_users-table" && columnName === "permissions") {
                                select.append($("<option value=\'-1\' selected><?= $escaper->escapeHtml($lang['UsersWithoutPermission']) ?></option>"));
                            } else

                            // Have to add this as it is possible to have users without a role or a role without a user assigned and we want to filter on those
                            if (id === "users_of_roles-table") {
                                if (columnName === "roles") {
                                    select.append($("<option value=\'-1\' selected><?= $escaper->escapeHtml($lang['NoRole']) ?></option>"));
                                } else {
                                    select.append($("<option value=\'-1\' selected><?= $escaper->escapeHtml($lang['NoUser']) ?></option>"));
                                }
                            }

                            $.each(options, function(i, item) {
                                select.append($("<option value=\'" + item.value + "\' selected>" + item.text + "</option>"));
                            });
                        });
                        
                        $("tr.filter", header).show();

                        // Have to throttle the refreshing of the datatable to let the users select more than one option from the filters per refresh
                        var throttledDatatableRefresh = function() {
                            var table = $(this.$select).data("table");
                            clearTimeout(filterSubmitTimer[table]);
                            filterSubmitTimer[table] = setTimeout(function() {
                                // To close the dropdowns on re-draw as for some reason it\'s unchecking the checkboxes when re-drawing the table
                                // This is just a visual thing as the state of those options won\'t become unchecked
                                $("div.table-container[data-id=" + table + "] div.btn-group.open").removeClass("open");

                                clearTimeout(filterSubmitTimer[table]);
                                reportDatatables[table].draw();
                            }, 2000);
                        }
                        
                        $(".column-filter-dropdown", header).multiselect({
                            enableFiltering: true,
                            buttonWidth: "100%",
                            maxHeight: 150,
                            numberDisplayed: 1,
                            enableCaseInsensitiveFiltering: true,
                            allSelectedText: "<?= $escaper->escapeHtml($lang['All']) ?>",
                            includeSelectAllOption: true,
                            onChange: throttledDatatableRefresh,
                            onSelectAll: throttledDatatableRefresh,
                            onDeselectAll: throttledDatatableRefresh
                        });

                        // When the filters are created, we\'re drawing the table
                        reportDatatable.draw();
                    },
                    error: function(xhr, status, error) {
                        if(!retryCSRF(xhr, this)) {}
                    }
                });
            }
        });

        reportDatatables[id] = reportDatatable;

    }
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>