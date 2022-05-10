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
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

$admin = 0;
$default = 0;
// Check if save role responsibilites was submitted
if(isset($_POST['save_role_responsibilities']))
{
    $role_id = (int)$_POST['role'];
    $responsibilities = isset($_POST['permissions']) ? array_filter($_POST['permissions'], 'ctype_digit') : [];
    $admin = isset($_POST['admin']) ? '1' : '0';
    $default = isset($_POST['default']) ? '1' : '0';
    
    // Check if role was submitted
    if($role_id)
    {
        save_role_responsibilities($role_id, $admin, $default, $responsibilities);
        set_alert(true, "good", $lang['SavedSuccess']);
    }
    else
    {
        set_alert(true, "bad", "Role is required.");
    }
//    refresh();
}
//Check if adding role was submitted 
elseif(isset($_POST['add_role']))
{
    $role_name = $_POST['role_name'];
    if($role_name)
    {
        add_name("role", $role_name);
        set_alert(true, "good", $escaper->escapeHtml($lang['AddedSuccess']));
        refresh();
    }
}
//Check if deleting role was submitted 
elseif(isset($_POST['delete_role']))
{
    $role_id = $_POST['role'];
    if(!$role_id || $role_id==1)
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['CantDeleteAdministratorRole']));
    }
    else
    {
        delete_role($role_id);
        set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        refresh();
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

	<script>
    	$(document).ready(function(){

    		if ($("#admin").is(':checked')) {
        		check_indeterminate_checkboxes($('.permissions-widget #check_all'));
    	    	$(".permissions-widget input[type=checkbox]").prop("readonly", true);
    		}

    	    $("#role").change(function(){
    	        // If role is unselected, uncheck all responsibilities
    	        if(!$(this).val()) {
					$("#admin").prop("checked", false);
					$("#default").prop("checked", false);
					$("#admin").prop("readonly", false);

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
								$("#default").prop("checked", data.data.default);
								$("#admin").prop("readonly", data.data.value === '1');

    	                		update_widget(data.data.responsibilities);

    	                		if (data.data.admin) {
        	            	    	check_indeterminate_checkboxes($('.permissions-widget #check_all'));
        	            	    	$(".permissions-widget input[type=checkbox]").prop("readonly", true);
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
        	$("#admin_button").prop("disabled", $("#admin").prop("readonly"));
        }
	</script>

</head>
<body>


<?php
    display_license_check();

    view_top_menu("Configure");

    // Get any alert messages
    get_alert();
?>
<div class="container-fluid">
    <div class="row-fluid">
        <div class="span3">
            <?php view_configure_menu("RoleManagement"); ?>
        </div>
        <div class="span9">
            <div class="row-fluid">
                <div class="span12">
                    <div class="hero-unit">
                        <form method="post" action="">
                            <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td>
                                        <h4><u><?php echo $escaper->escapeHtml($lang['AddNewRole']); ?></u></h4>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input name="role_name" value="" class="form-field form-control" type="text" placeholder="<?php echo $escaper->escapeHtml($lang['RoleName']); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <button name="add_role"><?php echo $escaper->escapeHtml($lang['Add']) ?></button>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                    <div class="hero-unit">
                        <form method="post" action="">
                            <table border="0" cellspacing="0" cellpadding="0" >
                                <tr>
                                    <td colspan="2">
                                        <h4><u><?php echo $escaper->escapeHtml($lang['Role']); ?></u></h4>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <?php
//                                            create_dropdown("role", isset($_POST['role']) ? $_POST['role'] : "", "role", true, false, false, "required");
                                            create_dropdown("role", (isset($_POST['role']) ? $_POST['role'] : ""), "role", true, false, false, "required");
                                            if(isset($_POST['role']))
                                            {
                                                echo "<script>\n";
                                                    echo "$(document).ready(function(){\n";
                                                        echo "$(\"#role\").change();\n";
                                                    echo "})\n";
                                                echo "</script>\n";
                                            }
                                        ?>
                                        
                                    </td>
                                    <td valign="top">
                                        &nbsp;&nbsp;&nbsp;&nbsp;
                                        <button class="btn" name="delete_role" type="submit"><?php echo $escaper->escapeHtml($lang['Delete']); ?></button>
                                    </td>
                                </tr>
                                <tr>
                                	<td colspan="2">
                                        <input class="hidden-checkbox" type="checkbox" name="default" id="default" <?php if ($default) echo "checked='checked'";?>>
                                        <label for="default"><?php echo $escaper->escapeHtml($lang['DefaultUserRole']);?></label>
                                    </td>
                                </tr>
                                <tr>
                                	<td colspan="2">
                                        <input style="display:none" type="checkbox" name="admin" id="admin">
            							<button id="admin_button" type="button" class="btn btn-danger" data-grant="<?php echo $escaper->escapeHtml($lang['GrantAdmin']); ?>" data-remove="<?php echo $escaper->escapeHtml($lang['RemoveAdmin']); ?>" title="<?php echo $escaper->escapeHtml($lang['AdminRoleDescription']);?>"><?php echo $escaper->escapeHtml($lang['GrantAdmin']);?></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <h4><u><?php echo $escaper->escapeHtml($lang['UserResponsibilities']); ?></u></h4>
                                        <div class="permissions-widget">
                                            <ul>
                                                <li>
                                                    <input class="hidden-checkbox" type="checkbox" id="check_all">
                                                    <label for="check_all"><?php echo $escaper->escapeHtml($lang['CheckAll']); ?></label>
                                                    <ul>
<?php
   $permission_groups = get_grouped_permissions();
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
?>       
                                                                <li>
                                                                    <input class="hidden-checkbox permission" type="checkbox" name="permissions[]" id="<?php echo $permission_key;?>" value="<?php echo $permission_id;?>">
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

                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <button name="save_role_responsibilities"><?php echo $escaper->escapeHtml($lang['Save']) ?></button>
                                    </td>
                                </tr>
                            </table>
                            
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
