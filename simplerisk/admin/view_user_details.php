<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['multiselect', 'CUSTOM:permissions-widget.js'], ['check_admin' => true], 'Update an Existing User', 'Configure', 'UserManagement');

// If a user was posted
if (isset($_POST['user']))
{
    // When an admin is editing itself should only be able to choose admin roles and can't remove it's own admin permission
    $admin_editing_itself = isset($_SESSION['admin']) && isset($_SESSION['uid']) && (int)$_SESSION['admin'] === 1 && (int)$_SESSION['uid'] === (int)$_POST['user'];
}
// Otherwise an admin is not editing itself
else $admin_editing_itself = false;

// If the user has been updated
if (isset($_POST['update_user']) && isset($_POST['user']))
{
    // Get the user ID
    $user_id = (int)$_POST['user'];
    
    // Verify the user ID is valid
    if ($user_id) {

        // Get the submitted values
        $lockout          = isset($_POST['lockout']) ? '1' : '0';
        $type             = $_POST['type'];
        $name             = $_POST['name'];
        $email            = $_POST['email'];
        $manager          = (int)$_POST['manager'];
        $teams            = isset($_POST['team']) ? array_filter($_POST['team'], 'ctype_digit') : [];
        $role_id          = (int)(isset($_POST['role']) ? $_POST['role'] : 0);
        $language         = get_name_by_value("languages", (int)$_POST['languages']);
        $multi_factor         = (int)(isset($_POST['multi_factor']) ? $_POST['multi_factor'] : 0);
        $change_password      = (int)(isset($_POST['change_password']) ? $_POST['change_password'] : 0);
        $admin            = isset($_POST['admin']) ? '1' : '0';

        $permissions            = isset($_POST['permissions']) ? array_filter($_POST['permissions'], 'ctype_digit') : [];

        $admin_role_issue = false;
        if ($admin_editing_itself) {
            // Get the new role
            $role = get_role($role_id);

            // If the role was changed to a non-admin role, the user removed its own admin permission or trying to lock itself out then we can't let this change be saved
            $admin_role_issue = !isset($role['admin']) || !$role['admin'] || !$admin || $lockout;
        }

        if (!$admin_role_issue) {
    // Verify that the email address is properly formatted
    if (filter_var($email, FILTER_VALIDATE_EMAIL))
    {
                // Change the type from a numeric to alpha
                switch($type){
                    case "1":
                        $type = "simplerisk";
                        break;
                    case "2":
                        $type = "ldap";
                        break;
                    case "3":
                        $type = "saml";
                        break;
                    default:
                        $type = "simplerisk";
                }
            
                // Update the user
                update_user($user_id, $lockout, $type, $name, $email, $teams, $role_id, $language, $admin,  $multi_factor, $change_password, $manager, $permissions);
            
                // Display an alert
                set_alert(true, "good", "The user was updated successfully.");
    }
    // Otherwise, the email address is invalid
    else
    {
                // Display an alert
        set_alert(true, "bad", "An invalid email address was specified. Please try again with a different email address.");
    }
        } else {
            set_alert(true, "bad", $lang['AdminSelfEditWarning']);
        }
    }
}

// if not selected user, page will be redirect
if (!isset($_POST['user'])){
    header('Location: ./user_management.php#manageusers');
}

// Check if a userid was sent
if (isset($_POST['user']))
{
    // Get the user ID
    $user_id = (int)$_POST['user'];

    // Get the users information
    $user_info = get_user_by_id($user_id);
    
    if ($user_info) {
        $enabled = $user_info['enabled'];
        $lockout = $user_info['lockout'];
        $type = $user_info['type'];
        $username = $user_info['username'];
        $name = $user_info['name'];
        $email = $user_info['email'];
        $last_login = $user_info['last_login'];
        $language = (int)$user_info['lang'];
        $teams = $user_info['teams'];
        $role_id = $user_info['role_id'];
        $admin = $user_info['admin'];
        $manager = $user_info['manager'];
        $multi_factor = $user_info['multi_factor'];
        $change_password = $user_info['change_password'];
    } else {
        $user_id = "";
        $enabled = 0;
        $lockout = false;
        $type       = "N/A";
        $username   = "N/A";
        $name       = "N/A";
        $email      = "N/A";
        $last_login = "N/A";
        $language   = "N/A";
        $teams      = "none";
        $role_id    = "";
        $admin      = false;
        $manager      = false;
        $multi_factor       = 0;
    }
}
else
{
    $user_id = "";
    $enabled = 0;
    $lockout = false;
    $type       = "N/A";
    $username   = "N/A";
    $name       = "N/A";
    $email      = "N/A";
    $last_login = "N/A";
    $language   = "N/A";
    $teams      = "none";
    $role_id    = "";
    $admin      = false;
    $manager      = false;
    $multi_factor       = 0;
}
?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <div class="row">
                <form name="update_user" method="post" action="">
                    <input type="hidden" name="user" value="<?= isset($_POST['user']) ? (int)$_POST['user'] : '' ?>"/>
                    <div class="col-12">
                        <input class="form-check-input" name="lockout" id="lockout" type="checkbox"<?php if ($lockout) echo " checked" ?> />
                        <label class="form-check-label"  for="lockout">&nbsp;&nbsp;&nbsp; <?php echo $lang['AccountLockedOut']; ?></label>
                    </div>
                    <div class="col-12">
                        <input name="change_password" id="change_password" <?php if(isset($change_password) && $change_password == 1) echo "checked"; ?> class="form-check-input" type="checkbox" value="1" />
                        <label for="change_password" class="form-check-label">  &nbsp;&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['RequirePasswordChangeOnLogin']); ?> </label>
                    </div>
                    <div class="col-12">
                        <input name="multi_factor" id="multi_factor" <?php if(isset($multi_factor) && $multi_factor == 1) echo "checked"; if(get_setting("mfa_required")) echo "checked readonly=\"readonly\""; ?> class="form-check-input" type="checkbox" value="1" />
                        <label for="multi_factor" class="form-check-label">  &nbsp;&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?> </label>
                    </div>
                    <div class="col-12 my-2">
                        <tr>
                            <td><label><?php echo $escaper->escapeHtml($lang['Status']); ?>:&nbsp;</label></td>
                            <td><b><?php echo ($enabled == 1 ? $escaper->escapeHtml($lang['Enabled']) : $escaper->escapeHtml($lang['Disabled'])); ?></b></td>
                        </tr>
                    </div>
                    <div class="row">
                        <div class="form-group col-4">
                            <label><?php echo $escaper->escapeHtml($lang['Type']); ?>:</label>
                            <select name="type" id="select" class="form-select">
                                <option value="1"<?php echo ($type == "simplerisk" ? " selected" : ""); ?>>SimpleRisk</option>
    <?php
        // If the custom authentication extra is enabeld
        if (custom_authentication_extra())
        {
            // Display the LDAP option
            echo "
                                <option value='2'" . ($type == "ldap" ? " selected" : "") . ">LDAP</option>
            ";
                
            // Display the SAML option
            echo "
                                <option value='3'" . ($type == "saml" ? " selected" : "") . ">SAML</option>
            ";
        }
    ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-4">
                            <label><?php echo $escaper->escapeHtml($lang['FullName']); ?>:</label>
                            <input name="name" type="text" maxlength="50" size="20" value="<?php echo $escaper->escapeHtml($name); ?>" class="form-control"/>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-4">
                            <label><?php echo $escaper->escapeHtml($lang['EmailAddress']); ?>:</label>
                            <input name="email" type="email" maxlength="200" size="20" value="<?php echo $escaper->escapeHtml($email); ?>"  class="form-control"/>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-4">
                            <label><?php echo $escaper->escapeHtml($lang['Username']); ?>:</label>
                            <input style="cursor: default;" name="username" type="text" size="20" title="<?php echo $escaper->escapeHtml($username); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($username); ?>" class="form-control"/>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-4">
                            <label><?php echo $escaper->escapeHtml($lang['LastLogin']); ?>:</label>
                            <input style="cursor: default;" name="last_login" type="text" maxlength="20" size="20" title="<?php echo $escaper->escapeHtml($last_login); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($last_login); ?>" class="form-control"/>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-4">
                            <label><?php echo $escaper->escapeHtml($lang['Language']); ?>:</label>
                            <?php create_dropdown("languages", get_value_by_name("languages", $language)); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-4">
                            <label><?php echo $escaper->escapeHtml($lang['Manager']); ?>:</label>
                            <?php create_dropdown("enabled_users_all", $manager, "manager"); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-4">
                            <label><?php echo $escaper->escapeHtml($lang['Teams']); ?>:</label>
                            <?php create_multiple_dropdown("team", $teams, null, get_all_teams(), false, '', '', true, $admin ? 'disabled' : ''); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-4">
                            <label><?php echo $escaper->escapeHtml($lang['Role']); ?>:</label>
    <?php
        $db = db_open();

        $stmt = $db->prepare("SELECT * FROM `role` ORDER BY `value`;");
        $stmt->execute();
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        db_close($db);
            
        echo "
                            <select id='role' name='role' class='form-select' required>
                                <option value='0'>--</option>
        ";

        foreach ($roles as $option)
        {

            // When an admin is editing itself should only be able to choose admin roles
            $valid_option = !$admin_editing_itself || (int)$option['admin'] === 1;

            echo "
                                <option value='" . ($valid_option ? $option['value'] : '') . "' " . ($role_id === $option['value'] ? "selected" : "") . " " . ( !$valid_option ? "disabled" : "") . ">" . 
                                    $escaper->escapeHtml($option['name']) . "
                                </option>
            ";
        }

        echo "
                            </select>
        ";
    ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-8 admin-button">
    <?php
        if ($admin_editing_itself) {
            echo "
                            <span class='admin_selfediting_warning mb-2' style='color:#ed3139;'>{$escaper->escapeHtml($lang['AdminSelfEditWarning'])}</span>
            ";
        }
    ?>
                            <br>
                            <button id="admin_button" type="button" class="btn btn-dark" data-grant="<?php echo $escaper->escapeHtml($lang['GrantAdmin']); ?>" data-remove="<?php echo $escaper->escapeHtml($lang['RemoveAdmin']); ?>" title="<?php echo $escaper->escapeHtml($lang['AdminRoleDescription']);?>"><?php echo $admin ? $escaper->escapeHtml($lang['RemoveAdmin']) : $escaper->escapeHtml($lang['GrantAdmin']);?></button>
							<input type="checkbox" name="admin" id="admin" <?php if ($admin) echo "checked='checked'";?> <?php if ($role_id == 1) echo "readonly='readonly'";?>><br>
                            <div class="mt-2 col-6 form-group alert alert-danger admin-alert" role="alert">
                            	<?= $escaper->escapeHtml($lang['UserResponsibilitiesAndTeamsCannotBeEditedWhenTheUserIsAnAdmin']); ?>
                        	</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-8">
                            <label><?php echo $escaper->escapeHtml($lang['UserResponsibilities']); ?></label>
                            <div class="permissions-widget">
                                <ul>
                                    <li>
                                        <input class="form-check-input" type="checkbox" id="check_all">
                                        <label for="check_all" class="form-check-label">&nbsp;&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['CheckAll']); ?></label>
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
                                                <label for="<?php echo $permission_group_id;?>" title="<?php echo $permission_group_description;?>"class="form-check-label">&nbsp;&nbsp;&nbsp;<?php echo $permission_group_name;?></label>
                                                <ul>
    <?php
            foreach ($permission_group as $permission) {
                $permission_id = $escaper->escapeHtml($permission['permission_id']);
                $permission_key = $escaper->escapeHtml($permission['key']);
                $permission_name = $escaper->escapeHtml($permission['permission_name']);
                $permission_description = $escaper->escapeHtml($permission['permission_description']);
                $selected = (isset($permission['selected']) ? $permission['selected'] : null);
    ?>       
                                                    <li>
                                                        <input class="form-check-input permission" type="checkbox" name="permissions[]" id="<?php echo $permission_key;?>" value="<?php echo $permission_id;?>" <?php if ($selected) echo "checked='checked'";?>>
                                                        <label for="<?php echo $permission_key;?>" title="<?php echo $permission_description;?>" class="form-check-label">&nbsp;&nbsp;&nbsp;<?php echo $permission_name;?></label>
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
                    <div class="row">
                        <div class="col-6">
                            <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_user" class="btn btn-submit"/>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">

    var admin_editing_itself = <?= ($admin_editing_itself ? 'true' : 'false') ?>;

    $(document).ready(function(){

        $("#team").multiselect({
            allSelectedText: "<?= $escaper->escapeHtml($lang['AllTeams']) ?>",
            includeSelectAllOption: true,
            buttonWidth: "100%"
        });

        if ($("#admin").is(":checked")) {
            $(".permissions-widget #check_all").prop("checked", true);
            check_indeterminate_checkboxes($(".permissions-widget #check_all"));
            make_checkboxes_readonly();
        }
        
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
                $(".permissions-widget #check_all").prop("checked", true);
                check_indeterminate_checkboxes($(".permissions-widget #check_all"));
                make_checkboxes_readonly();
            } else {
                make_checkboxes_editable();
            }
            update_admin_button();
        });

        update_admin_button();
    });

    function update_admin_button() {
        admin = $("#admin").prop("checked");
        admin_button = $("#admin_button");
        remove_text = admin_button.data("remove");
        grant_text = admin_button.data("grant");

        $("#admin_button").text(admin ? remove_text : grant_text);
        $("#admin_button").prop("disabled", admin_editing_itself || $("#admin").prop("readonly"));
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
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>