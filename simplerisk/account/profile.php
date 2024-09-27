<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

$breadcrumb_title_key = "Profile Details";
render_header_and_sidebar(['CUSTOM:permissions-widget.js'], breadcrumb_title_key: $breadcrumb_title_key);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/messages.php'));

// If the language was changed
if (isset($_POST['change_language'])) {
    $language = (int)$_POST['languages'];

    // If its not the default selection
    if ($language != 0) {

        // Update the language for the current user
        update_language($_SESSION['uid'], get_name_by_value("languages", $language));

        // Use the new language file
        // Ignoring detections related to language files
        // @phan-suppress-next-line SecurityCheck-PathTraversal
        require_once(language_file());

        // Display an alert
        set_alert(true, "good", $lang['LanguageUpdated']);

    } else {

        // Display an alert
        set_alert(true, "bad", $lang['SelectValidLanguage']);
        // set_alert(true, "bad", "You need to select a valid language");

    }
}

//  If the user wants to enable or disable MFA
if (isset($_POST['mfa_disable']) || isset($_POST['mfa_enable'])) {

    // Redirect to the MFA configuration page
    header("Location: mfa.php");

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
$language = (string)$user_info['lang'];
$multi_factor = (int)$user_info['multi_factor'];
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
if (isset($_POST['change_password'])) {

    $team = $_SESSION["user"];
    $current_pass = $_POST['current_pass'];
    $new_pass = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    // If the user and current password are valid
    if (is_valid_user($team, $current_pass)) {

        // Check the password
        $error_code = valid_password($new_pass, $confirm_pass, $_SESSION['uid']);

        // If the password is valid
        if ($error_code == 1) {

            // Generate the salt
            $salt = generateSalt($team);

            // Generate the password hash
            $hash = generateHash($salt, $new_pass);
            
            // If it is possible to reuse password
            if(check_add_password_reuse_history($_SESSION["uid"], $hash)) {

                // Get user old data
                $old_data = get_salt_and_password_by_user_id($_SESSION['uid']);

                // Add the old data to the pass_history table
                add_last_password_history($_SESSION["uid"], $old_data["salt"], $old_data["password"]);

                // Update the password
                update_password($team, $hash);

                // Clean up other sessions of the user and roll the current session's id
                kill_other_sessions_of_current_user();

                // Expire any active password reset tokens for this user
                expire_reset_token_for_username($username);

                // Display an alert
                set_alert(true, "good", $lang['PasswordUpdated']);

            } else {

                set_alert(true, "bad", $lang['PasswordNoLongerUse']);

            }
        } else {

            // Display an alert
            //set_alert(true, "bad", password_error_message($error_code));

        }
    } else {

        // Display an alert
        set_alert(true, "bad", $lang['PasswordIncorrect']);

    }
}
    
// Check if a reset_custom_display_setting button is clicked
if(isset($_POST['reset_custom_display_settings'])) {

    reset_custom_display_settings();

    // Display an alert
    set_alert(true, "good", $lang['CustomResetSuccessMessage']);
    
}
?>
<div class="row">
    <div class="col-lg-6 d-flex flex-column my-2">
        <div class="card-body border profile-info-container flex-grow-0 m-b-5">
            <form name="change_language" method="post" action="">
                <div class="form-group">
                    <label><?= $escaper->escapeHtml($lang['FullName']); ?>:</label>
                    <input type="text" class="form-control" disabled value="<?= $escaper->escapeHtml($name); ?>"/>
                </div>
                <div class="form-group">
                    <label><?= $escaper->escapeHtml($lang['EmailAddress']); ?>:</label>
                    <input type="text" class="form-control" disabled value="<?= $escaper->escapeHtml($email); ?>"/>
                </div>
                <div class="form-group">
                    <label><?= $escaper->escapeHtml($lang['Username']); ?>:</label>
                    <input type="text" class="form-control" disabled value="<?= $escaper->escapeHtml($username); ?>"/>
                </div>
                <div class="form-group">
                    <label><?= $escaper->escapeHtml($lang['LastLogin']); ?>:</label>
                    <input type="text" class="form-control" disabled value="<?= $escaper->escapeHtml($last_login); ?>"/>
                </div>
                <div class="form-group">
                    <label><?= $escaper->escapeHtml($lang['Manager']); ?>:</label>
                    <input type="text" class="form-control" disabled value="<?= $escaper->escapeHtml($manager); ?>"/>
                </div>
                <div class="form-group">
                    <label><?= $escaper->escapeHtml($lang['Teams']); ?>:</label>
                    <div class="teams-group">
    <?php
        if ($teams) {

            $teams = array_map(function($team) use ($escaper) {
                return $escaper->escapeHtml($team);
            }, $teams);
            $names = array();
            $count = 0;
            $limit = 3;
            $limited = count($teams) > $limit;
                                
            foreach($teams as $team) {
                $names[] = $team;
                $count += 1;
                if ($count == $limit) break;
            }
                                
            if ($limited) {
                echo "
                        <div class='teams-limited'>" . 
                            implode("<br/>", $names) . "<br/>
                            <a class='btn btn-secondary show-more-teams float-end my-1'>Show more...</a>
                        </div>
                ";
            }

            echo "
                        <div class='teams-all'" . ($limited ? "style='display: none;'" : "") . ">" . 
                            implode("<br/>", $teams) . 
                            ($limited ? "<br/><a class='btn btn-secondary show-less-teams float-end my-1'>Show less...</a> " : "" ) . "
                        </div>
            ";

        } else {
            echo "
                        -
            ";
        }
    ?>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= $escaper->escapeHtml($lang['Role']); ?>:</label>
                    <input type="text" class="form-control" disabled value="<?= $escaper->escapeHtml($role); ?>"/>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-info-circle " style="color: #ed3139;" title="<?= $escaper->escapeHtml($lang['AdminRoleDescription']);?>"></i> <?= $escaper->escapeHtml($lang['Admin']); ?>:</label>
                    <input type="text" class="form-control" disabled value="<?= $escaper->escapeHtml(localized_yes_no($admin)); ?>"/>
                </div>
                <div class="form-group">
                    <label>MFA:</label>
                    <div class="w-100 row">
                        <div class="col-9">
                            <input type="text" class="form-control" disabled value="<?= ($multi_factor == 1 ? $escaper->escapeHtml($lang['Enabled']) : $escaper->escapeHtml($lang['Disabled'])); ?>"/>
                        </div>
                        <div class="col-3">
    <?php
        // If MFA is disabled for this user
        if ($multi_factor == 0) {
            // Display the button to enable MFA
            echo "
                            <input name='mfa_enable' type='submit' value='" . $escaper->escapeHtml($lang['EnableMFA']) . "' class='btn btn-submit w-100' />
            ";
        // If MFA is enabled for this user
        } else {
            // If we do not require MFA for all users
            if (!get_setting("mfa_required")) {
                // Display the button to disable MFA
                echo "
                            <input name='mfa_disable' type='submit' value='" . $escaper->escapeHtml($lang['DisableMFA']) . "' class='btn btn-dark w-100' />
                ";
            }
        }
    ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= $escaper->escapeHtml($lang['Language']); ?>:</label>
                    <div class="row w-100">
                        <div class="col-9">
                            <?php create_dropdown("languages", get_value_by_name("languages", $language)); ?>
                        </div>
                        <div class="col-3">
                            <input type="submit" name="change_language" value="<?= $escaper->escapeHtml($lang['Update']); ?>"  class="btn btn-submit w-100" />
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= $escaper->escapeHtml($lang['Display']); ?>:</label>
                    <input name="reset_custom_display_settings" value="<?= $lang['ResetCustomDisplaySettings']; ?>" class="btn btn-dark" type="submit" >
                </div>
            </form>
    <?php
        // If the API Extra is enabled
        if (api_extra()) {
            // Require the API Extra
            require_once(realpath(__DIR__ . '/../extras/api/index.php'));

            // Display the API Profile
            display_api_profile();
        }
    ?>
        </div>
    <?php

        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] != "ldap") {

            $html = "
        <div class='card-body border flex-grow-1'>
            ";

            $resetRequestMessages = getPasswordReqeustMessages();
            if(count($resetRequestMessages)) {

                $html .= "    
            <div>
                <h4>" . $escaper->escapeHtml($lang['PasswordRequirements']) . "</h4>
                <ul>
                ";

                foreach($resetRequestMessages as $resetRequestMessage) {

                    $html .= "
                    <li>{$resetRequestMessage}</li>
                    ";

                }

                $html .= "
                </ul>
            </div>
                ";

            }

            echo $html;

    ?>
            <div>
                <form class="form-horizontal" action="" method="post">
                    <h4><?= $escaper->escapeHtml($lang['ChangePassword']);?></h4>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['CurrentPassword']);?>:</label>
                        <input type="password" class="form-control" id="current_pass" name="current_pass"/>
                    </div> 
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['NewPassword']);?>:</label>
                        <input type="password" class="form-control" id="new_pass" name="new_pass"/>
                    </div>
                    <div class="form-group">
                        <label><?= $escaper->escapeHtml($lang['ConfirmPassword']);?>:</label>
                        <input type="password" class="form-control" id="confirm_pass" name="confirm_pass"/>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-submit" name="change_password" value="submit"><?= $escaper->escapeHtml($lang['Submit']);?></button>
                        <button type="reset" class="btn btn-dark"><?= $escaper->escapeHtml($lang['Reset']);?></button>
                    </div>
                </form>
            </div>
    <?php 
            echo "
        </div>
            ";
        } 
    ?>
    </div>
    <div class="col-lg-6 d-flex flex-column my-2">
        <div class="card-body border">
            <h4><?php echo $escaper->escapeHtml($lang['UserResponsibilities']); ?></h4>
            <div class="permissions-widget">
                <ul>
                    <li>
                        <input class="form-check-input" type="checkbox" id="check_all">
                        <label for="check_all" class="form-check-label mb-0">
                            &nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['CheckAll']); ?>    
                        </label>
                        <ul>
    <?php

        $permission_groups = get_grouped_permissions($user_id);

        foreach ($permission_groups as $permission_group_name => $permission_group) {

            $permission_group_id = $escaper->escapeHtml("pg-" . $permission_group[0]['permission_group_id']);
            $permission_group_name = $escaper->escapeHtml($permission_group_name);
            $permission_group_description = $escaper->escapeHtml($permission_group[0]['permission_group_description']);

    ?>       
                            <li>
                                <input class="form-check-input permission-group" type="checkbox" id="<?php echo $permission_group_id;?>">
                                <label for="<?php echo $permission_group_id;?>" title="<?php echo $permission_group_description;?>" class="form-check-label mb-0">
                                    &nbsp;&nbsp;<?php echo $permission_group_name;?>
                                </label>
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
                                            <input class="form-check-input permission" type="checkbox" name="permissions[]" id="<?php echo $permission_key;?>" value="<?php echo $permission_id;?>" <?php if ($selected) echo "checked='checked'";?>>
                                            <label for="<?php echo $permission_key;?>" title="<?php echo $permission_description;?>" class="form-check-label mb-0">
                                                &nbsp;&nbsp;<?php echo $permission_name;?>
                                            </label>
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
    </div>
</div>
<script type="text/javascript">
    $(document).ready(function() {

        // Configure the 'Back' button
        setTimeout(() => {
            $("div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu a").text("Back");
        }, 0);
        $("div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu a").attr("href", "javascript:history.go(-1)");

        $('.show-more-teams, .show-less-teams').click(function() {
            $('.teams-limited').toggle();
            $('.teams-all').toggle();
        });

        // Make User responsibilities checkboxes readonly
        make_checkboxes_readonly();

    });
</script>
<style>

    /* Only show the 'Back' button */
    div.page-breadcrumb ol.breadcrumb li.breadcrumb-item:not(.submenu) {
        display: none;
    }
    
    div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu::before {
        display: none;
    }

    .scroll {
        max-height:650px;
        overflow-y: scroll;
    }

    input[type=checkbox][readonly] + label {
        color: inherit;
    }

    .admin-info:before {
        font-family: "Font Awesome 6 Free";
        font-weight: "900";
        content: "\f05A";
        display: inline-block;
        padding-right: 3px;
        color: red;
    }

    input[type=checkbox][readonly] + label {
        color: inherit;
    }

</style>
<?php
	// Render the footer of the page. Please don't put code after this part.
	render_footer();
?>