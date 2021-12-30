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
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

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
            $role_id          = (int)$_POST['role'];
            $language         = get_name_by_value("languages", (int)$_POST['languages']);
            $multi_factor         = (int)$_POST['multi_factor'];
            $change_password      = (int)(isset($_POST['change_password']) ? $_POST['change_password'] : 0);
            $admin            = isset($_POST['admin']) ? '1' : '0';

            $permissions            = isset($_POST['permissions']) ? array_filter($_POST['permissions'], 'ctype_digit') : [];

            $admin_role_issue = false;
            if ($admin_editing_itself) {
                // Get the new role
                $role = get_role($role_id);

                // If the role was changed to a non-admin role, the user removed its own admin permission or trying to lock itself out then we can't let this change be saved
                $admin_role_issue = !$role['admin'] || !$admin || $lockout;
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
          $language = $user_info['lang'];
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
          $multi_factor       = 1;
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
      $multi_factor       = 1;
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
    <script type="text/javascript">
        $(function(){
            $("#team").multiselect({
                allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllTeams']); ?>',
                includeSelectAllOption: true
            });
        });
    </script>

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

<script type="text/javascript">

	var admin_editing_itself = <?php echo $admin_editing_itself ? 'true' : 'false';?>;
    $(document).ready(function(){

    	if ($("#admin").is(':checked')) {
    		//check_indeterminate_checkboxes($('.permissions-widget #check_all'));
        	$(".permissions-widget input[type=checkbox]").prop("readonly", true);
    	}
    	
        $("#role").change(function(){
            // If role is unselected, uncheck all responsibilities
            if(!$(this).val()) {
    			$("#admin").prop("checked", false);
    			$("#default").prop("checked", false);
    			$("#admin").prop("readonly", false);
                $("#team").multiselect("enable");
    
    		    $(".permissions-widget input[type=checkbox]").each(function() {
    		    	$this = $(this);
    		    	$this.prop("checked", false);
    		    	$this.prop("readonly", false);
    		    	$this.prop("indeterminate", false);
    		    });
    			
                check_indeterminate_checkboxes($('.permissions-widget #check_all'));
                update_admin_button();
            } else {
            	$("#admin").prop("checked", false);
                $.ajax({
                    type: "GET",
                    url: BASE_URL + "/api/role_responsibilities/get_responsibilities",
                    data: {
                        role_id: $(this).val()
                    },
                    success: function(data) {
    
    					if (data.data) {
    						
    						$("#admin").prop("checked", data.data.admin);
    						$("#admin").prop("readonly", data.data.value === '1');
    
                    		update_widget(data.data.responsibilities);
    
                    		if (data.data.admin) {
    	            	    	check_indeterminate_checkboxes($('.permissions-widget #check_all'));
    	            	    	$(".permissions-widget input[type=checkbox]").prop("readonly", true);
    
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
        });

        $("#admin_button").click(function(){
            $("#admin").prop("checked", !$("#admin").prop("checked"));
    	    if ($("#admin").prop("checked")) {
    	    	$(".permissions-widget input[type=checkbox]").prop("checked", true);
    	    	check_indeterminate_checkboxes($('.permissions-widget #check_all'));
    	    	$(".permissions-widget input[type=checkbox]").prop("readonly", true);
    	    } else {
    	    	$(".permissions-widget input[type=checkbox]").prop("readonly", false);
    	    }
    	    update_admin_button();
        });

        update_admin_button();
    });

    function update_admin_button() {
        admin = $("#admin").prop("checked");
    	admin_button = $("#admin_button");
		remove_text = admin_button.data('remove');
		grant_text = admin_button.data('grant');

    	$("#admin_button").text(admin ? remove_text : grant_text);
    	$("#admin_button").prop("disabled", admin_editing_itself || $("#admin").prop("readonly"));
		$("#team").multiselect(admin ? 'disable' : 'enable');
        $("#team").prop("disabled", false);
		if (admin) {
			$("#team").multiselect("selectAll", false);
			$("#team").multiselect("refresh");
		}
    }
    
</script>

    <?php
        display_license_check();

        view_top_menu("Configure");

        // Get any alert messages
        get_alert();
    ?>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_configure_menu("UserManagement"); ?>
            </div>
            <div class="span9">
                <div class="row-fluid">
                    <div class="span12">
                        <div class="hero-unit">
                           <a type="button" class="btn" href="./user_management.php#manageusers"/>
                              <?php echo $escaper->escapeHtml($lang['Back']); ?>
                           </a><br />
                            <form name="update_user" method="post" action="">
                                <input name="user" type="hidden" value="<?php echo $escaper->escapeHtml($user_id); ?>" />
                                <table border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td colspan="2"><h4>Update an Existing User:</h4></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><input class="hidden-checkbox" name="lockout" id="lockout" type="checkbox"<?php if ($lockout) echo " checked" ?> /> <label for="lockout">  &nbsp;&nbsp;&nbsp; <?php echo $lang['AccountLockedOut']; ?></label> </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">                                
                                            <input name="change_password" id="change_password" <?php if(isset($change_password) && $change_password == 1) echo "checked"; ?> class="hidden-checkbox" type="checkbox" value="1" />  <label for="change_password">  &nbsp;&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['RequirePasswordChangeOnLogin']); ?> </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php echo $escaper->escapeHtml($lang['Status']); ?>:&nbsp;</td>
                                        <td><b><?php echo ($enabled == 1 ? $escaper->escapeHtml($lang['Enabled']) : $escaper->escapeHtml($lang['Disabled'])); ?></b></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo $escaper->escapeHtml($lang['Type']); ?>:&nbsp;</td>
                                        <td>
                                        <select name="type" id="select">
                                        <option value="1"<?php echo ($type == "simplerisk" ? " selected" : ""); ?>>SimpleRisk</option>
                                        <?php
                                        // If the custom authentication extra is enabeld
                                        if (custom_authentication_extra())
                                        {
                                            // Display the LDAP option
                                            echo "<option value=\"2\"" . ($type == "ldap" ? " selected" : "") . ">LDAP</option>\n";

                                            // Display the SAML option
                                            echo "<option value=\"3\"" . ($type == "saml" ? " selected" : "") . ">SAML</option>\n";
                                        }
                                        ?>
                                        </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php echo $escaper->escapeHtml($lang['FullName']); ?>:&nbsp;</td>
                                        <td><input name="name" type="text" maxlength="50" size="20" value="<?php echo $escaper->escapeHtml($name); ?>" /></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo $escaper->escapeHtml($lang['EmailAddress']); ?>:&nbsp;</td>
                                        <td><input name="email" type="email" maxlength="200" size="20" value="<?php echo $escaper->escapeHtml($email); ?>" /></td>
                                        </tr>
                                    <tr>
                                        <td><?php echo $escaper->escapeHtml($lang['Username']); ?>:&nbsp;</td>
                                        <td><input style="cursor: default;" name="username" type="text" size="20" title="<?php echo $escaper->escapeHtml($username); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($username); ?>" /></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo $escaper->escapeHtml($lang['LastLogin']); ?>:&nbsp;</td>
                                        <td><input style="cursor: default;" name="last_login" type="text" maxlength="20" size="20" title="<?php echo $escaper->escapeHtml($last_login); ?>" disabled="disabled" value="<?php echo $escaper->escapeHtml($last_login); ?>" /></td>
                                        </tr>
                                    <tr>
                                        <td><?php echo $escaper->escapeHtml($lang['Language']); ?>:&nbsp;</td>
                                        <td><?php create_dropdown("languages", get_value_by_name("languages", $language)); ?></td>
                                    </tr>
                                </table>

                                <h6>
                                    <u><?php echo $escaper->escapeHtml($lang['Manager']); ?></u>
                                </h6>
                                <?php create_dropdown("enabled_users_all", $manager, "manager"); ?>

                                <h6>
                                    <u><?php echo $escaper->escapeHtml($lang['Teams']); ?></u>
                                </h6>
                                <?php create_multiple_dropdown("team", $teams, null, get_all_teams(), false, '', '', true, $admin ? 'disabled' : ''); ?>

                                <h6><u><?php echo $escaper->escapeHtml($lang['Role']); ?></u></h6>
                                <?php
                                    $db = db_open();

                                    $stmt = $db->prepare("SELECT * FROM `role` ORDER BY `value`;");
                                    $stmt->execute();
                                    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    db_close($db);

                                    echo "<select id='role' name='role' class='form-field' style='width:auto;' required>";
                                    echo "<option value='0'>--</option>";
                                    foreach ($roles as $option)
                                    {
                                        // When an admin is editing itself should only be able to choose admin roles
                                        $valid_option = !$admin_editing_itself || (int)$option['admin'] === 1;
                                        echo "<option value='" . ($valid_option ? $option['value'] : '') . "' " . ($role_id === $option['value'] ? "selected" : "") . " " . ( !$valid_option ? "disabled" : "") . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
                                    }
                                    echo "</select>";
                                    if ($admin_editing_itself) {
                                        echo "<br/><span class='admin_selfediting_warning'>{$escaper->escapeHtml($lang['AdminSelfEditWarning'])}</span>";
                                    }
                                ?>

								<br/>
                                <input style="display:none" type="checkbox" name="admin" id="admin" <?php if ($admin) echo "checked='checked'";?> <?php if ($role_id == 1) echo "readonly='readonly'";?>>
								<button id="admin_button" type="button" class="btn btn-danger" data-grant="<?php echo $escaper->escapeHtml($lang['GrantAdmin']); ?>" data-remove="<?php echo $escaper->escapeHtml($lang['RemoveAdmin']); ?>" title="<?php echo $escaper->escapeHtml($lang['AdminRoleDescription']);?>"><?php echo $admin ? $escaper->escapeHtml($lang['RemoveAdmin']) : $escaper->escapeHtml($lang['GrantAdmin']);?></button>

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
                                           $selected = (isset($permission['selected']) ? $permission['selected'] : null);
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

                                <h6>
                                    <u><?php echo $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?></u>
                                </h6>
                                <input type="radio" name="multi_factor" value="1"<?php if ($multi_factor == 1) echo " checked" ?> />&nbsp;<?php echo $escaper->escapeHtml($lang['None']); ?><br />
                                <?php
                                    // If the custom authentication extra is installed
                                    if (custom_authentication_extra())
                                    {
                                        // Include the custom authentication extra
                                        require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

                                        // Display the multi factor authentication options
                                        multi_factor_authentication_options($multi_factor);
                                    }
                                ?>
                                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_user" /><br />
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php display_set_default_date_format_script(); ?>
  </body>

</html>
