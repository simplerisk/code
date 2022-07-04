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
require_once(realpath(__DIR__ . '/../includes/reporting.php'));
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

// Set the default tab values
$addusers_tab = true;
$manageusers_tab = false;
$usersettings_tab = false;
$userreports_tab = false;


$separation = team_separation_extra();
if ($separation) {
    require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
}

// Check if a new user was submitted
if (isset($_POST['add_user']))
{
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

    $multi_factor = (int)$_POST['multi_factor'];
    $change_password = (int)(isset($_POST['change_password']) ? $_POST['change_password'] : 0);

    $permissions            = isset($_POST['permissions']) ? array_filter($_POST['permissions'], 'ctype_digit') : [];

    // If the type is 1
    if ($type == "1")
    {
        // This is a local SimpleRisk user account
        $type = "simplerisk";

        // Check the password
        $error_code = valid_password($pass, $repeat_pass);
    }
    // If the type is 2
    else if ($type == "2")
    {
        // This is an LDAP user account
        $type = "ldap";

        // No password check required
        $error_code = 1;
    }
    // If the type is 3
    else if ($type == "3")
    {
        // This is a SAML user account
        $type = "saml";

        // No password check required
        $error_code = 1;
    }
    else
    {
        // This is an invalid type
        $type = "INVALID";

        // Return an error
        $error_code = 0;
    }

    // If the password is valid
    if ($error_code == 1)
    {
	// Verify that the email address is properly formatted
	if (filter_var($email, FILTER_VALIDATE_EMAIL))
	{
            // Verify that the user does not exist
            if (!user_exist($user))
            {
                // Verify that it is a valid username format
                if (valid_username($user))
                {
                    // Create a unique salt for the user
                    $salt = generate_token(20);

                    // Hash the salt
                    $salt_hash = '$2a$15$' . md5($salt);

                    // Generate the password hash
                    $hash = generateHash($salt_hash, $pass);

                    // Insert a new user
                    $user_id = add_user($type, $user, $email, $name, $salt, $hash, $teams, $role_id, $admin, $multi_factor, $change_password, $manager, $permissions);

		    // If the encryption extra is enabled
                    if (encryption_extra())
                    {
                        // Load the extra
                        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

                        // If the encryption method is mcrypt
                        if (isset($_SESSION['encryption_method']) && $_SESSION['encryption_method'] == "mcrypt")
                        {
                            // Add the new encrypted user
                            add_user_enc($pass, $salt, $user);
                        }
                    }

                    // If ths customization extra is enabled, add new user to custom field as user multi dropdown
                    if(customization_extra())
                    {
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
                }
                // Otherwise, an invalid username was specified
                else
                {
                    // Display an alert
                    set_alert(true, "bad", "An invalid username was specified.  Please try again with a different username.");
                }
            }
            // Otherwise, the user already exists
            else
            {
                // Display an alert
                set_alert(true, "bad", "The username already exists.  Please try again with a different username.");
            }
	}
        // Otherwise, the email address is invalid
	else
	{
            // Display an alert
	    set_alert(true, "bad", "An invalid email address was specified.  Please try again with a different email address.");
	}
    }
    // Otherewise, an invalid password was specified
    else
    {
        // Display an alert
        //set_alert(true, "bad", password_error_message($error_code));
    }
}

// Check if a user was enabled
if (isset($_POST['enable_user']))
{
    // Set the selected tab
    $addusers_tab = false;
    $manageusers_tab = true;

    $value = (int)$_POST['disabled_users_all'];

    // Verify value is an integer
    if (is_int($value) && $value > 0)
    {
        enable_user($value);

        // Display an alert
        set_alert(true, "good", "The user was enabled successfully.");
    } else {
        set_alert(true, "bad", $lang['PleaseSelectUser']);
    }
}

// Check if a user was disabled
if (isset($_POST['disable_user']))
{
    // Set the selected tab
    $addusers_tab = false;
    $manageusers_tab = true;

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
if (isset($_POST['delete_user']))
{
    // Set the selected tab
    $addusers_tab = false;
    $manageusers_tab = true;

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

            // If the encryption extra is enabled
            if (encryption_extra())
            {
                // Load the extra
                require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

                // If the encryption method is mcrypt
                if (isset($_SESSION['encryption_method']) && $_SESSION['encryption_method'] == "mcrypt")
                {
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
if (isset($_POST['password_reset']))
{
    // Set the selected tab
    $addusers_tab = false;
    $manageusers_tab = true;

    // Get the POSTed user ID
    $value = (int)$_POST['user'];

    // Verify value is an integer
    if (is_int($value) && $value > 0)
    {
        // Open the database connection
        $db = db_open();

        // Get any password resets for this user in the past 10 minutes
        $stmt = $db->prepare("SELECT * FROM password_reset pw LEFT JOIN user u ON pw.username = u.username WHERE pw.timestamp >= NOW() - INTERVAL 10 MINUTE AND u.value=:value;");
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Close the database connection
        db_close($db);

        // If we have password resets in the past 10 minutes
        if (count($results) != 0)
        {
            set_alert(true, "bad", $lang['PasswordResetRequestsExceeded']);
        }
        else
        {
            password_reset_by_userid($value);

            // Display an alert
            set_alert(true, "good", "A password reset email was sent to the user.");
        }
    } else {
        set_alert(true, "bad", $lang['PleaseSelectUser']);
    }
}

// Check if a password policy update was requested
if (isset($_POST['password_policy_update']))
{
    // Set the selected tab
    $addusers_tab = false;
    $usersettings_tab = true;

    $strict_user_validation = (isset($_POST['strict_user_validation'])) ? 1 : 0;
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

    update_password_policy($strict_user_validation, $pass_policy_enabled, $min_characters, $alpha_required, $upper_required, $lower_required, $digits_required, $special_required, $pass_policy_attempt_lockout, $pass_policy_attempt_lockout_time, $pass_policy_min_age, $pass_policy_max_age, $pass_policy_reuse_limit);

    // Display an alert
    set_alert(true, "good", "The settings were updated successfully.");
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
    <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/permissions-widget.js?<?php echo current_version("app"); ?>"></script>
    
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/settings_tabs.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">
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

        	$("#team").multiselect({
                allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllTeams']); ?>',
                includeSelectAllOption: true,
                enableCaseInsensitiveFiltering: true,
            });

            var $tabs = $("#main").tabs({
                active: $('.tabs a.active').parent().index(),
            	show: { effect: "fade", duration: 200 },
                beforeActivate: function(event, ui){
                	ui.oldTab.find('a').removeClass("active");
                	ui.newTab.find('a').addClass("active");
                },
                activate: function(event, ui){
                	var default_report_table = <?php if ($separation) { ?>'users_of_teams-table'<?php } else { ?>'users_of_permissions-table'<?php } ?>;
                	// Activating the default report when the 'User Reports' tab clicked the first time
                	// the default depends on whether the team separation extra is turned on or not
                	if (ui.newTab.find('a').attr('href') === '#userreports' && !(default_report_table in reportDatatables)) {
                		activateDatatable(default_report_table);	
                	}
                }
            });
            if(window.location.hash == "#manageusers"){
                $("#main").tabs({ active: 1 });
            }

            $('#report_displayed_dropdown').change(function() {
                datatableWrapperId = this.value + '-report';
            	datatableId = this.value + '-table';
    
            	$("#userreports .report").hide();
            	$("#" + datatableWrapperId).show();
        	
                if (!(datatableId in reportDatatables)) {
                    activateDatatable(datatableId);
                } else {
                    // Need it because if the table is redrawn due to the filtering logic while it's not the active tab
                    // then the header columns need to be re-adjusted
                	reportDatatables[datatableId].columns.adjust();
                }
            });
    
            // It's required to make the view switch back from "View All" to the paginated view when a paginate button is clicked
            $("body").on("click", "a.paginate_button", function() {
                var id = $(this).attr('aria-controls');
                var oSettings =  reportDatatables[id].settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(".dataTables_wrapper").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = 10;
                    reportDatatables[id].draw()
                }
            });

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
        	$("#admin_button").prop("disabled", $("#admin").prop("readonly"));
            $("#team").multiselect(admin ? 'disable' : 'enable');
            $("#team").prop("disabled", false);
            if (admin) {
                $("#team").multiselect("selectAll", false);
                $("#team").multiselect("refresh");
            }
        }

        function handleSelection(choice) {
            elements = document.getElementsByClassName("ldap_pass");
            if (choice=="1") {
                for(i=0; i<elements.length; i++) {
                    elements[i].style.display = "";
                }
            }
            if (choice=="2") {
                for(i=0; i<elements.length; i++) {
                    elements[i].style.display = "none";
                }
            }
            if (choice=="3") {
                for(i=0; i<elements.length; i++) {
                    elements[i].style.display = "none";
                }
            }
        }
        
        function update_proxy()
        {
          var proxy_web_requests_checkbox = document.getElementById("proxy_web_requests_checkbox");
          var proxy_verify_ssl_certificate_checkbox = document.getElementById("proxy_verify_ssl_certificate_checkbox");
          var proxy_verify_ssl_certificate_row = document.getElementById("proxy_verify_ssl_certificate_row");
          var proxy_authenticated_row = document.getElementById("proxy_authenticated_row");
          var proxy_authenticated_checkbox = document.getElementById("proxy_authenticated_checkbox");
          var proxy_host_row = document.getElementById("proxy_host_row");
          var proxy_port_row = document.getElementById("proxy_port_row");
          var proxy_user_row = document.getElementById("proxy_user_row");
          var proxy_pass_row = document.getElementById("proxy_pass_row");
    
          if (proxy_web_requests_checkbox.checked == true)
          {
            proxy_verify_ssl_certificate_row.style.display = "";
            proxy_host_row.style.display = "";
            proxy_port_row.style.display = "";
            proxy_authenticated_row.style.display = "";
    
            if (proxy_authenticated_checkbox.checked == true)
            {
              proxy_user_row.style.display = "";
              proxy_pass_row.style.display = "";
            }
            else
            {
              proxy_user_row.style.display = "none";
              proxy_pass_row.style.display = "none";
            }
          }
          else
          {
            proxy_verify_ssl_certificate_row.style.display = "none";
            proxy_host_row.style.display = "none";
            proxy_port_row.style.display = "none";
            proxy_authenticated_row.style.display = "none";
            proxy_user_row.style.display = "none";
            proxy_pass_row.style.display = "none";
          }
        }

    	var filterSubmitTimer = [];
        var reportDatatables = ["users_of_teams-table"];

        function activateDatatable(id) {
            var $this = $("#" + id);
            var type = $this.data('type');

            var reportDatatable = $this.DataTable({
                scrollX: true,
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                bSortCellsTop: true,
                pagingType: "full_numbers",
                dom : "flrti<'.download-by-group'><'#view-all-"+ id +".view-all'>p",
                order: [[0, 'asc']],
                columnDefs : [{
                    "targets" : [-1],
                    "orderable": false
                }],
                deferLoading:true, // We only load the data when everything has been setup properly
                ajax: {
                    url: BASE_URL + '/api/reports/user_management_reports',
                    type: "POST",
                    data: function(d) {
                        d.type = type;
                        d.columnFilters = {};
                        $('select.column-filter-dropdown', reportDatatables[id].table().header()).each(function(){
                            d.columnFilters[$(this).data('name')] = $(this).val();
                        });
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
                        dataType: 'json',
                        success: function(data){
                            var header = self.api().table().header();

                            $("tr.filter th", header).each(function(){
                                var column = $(this);
                                var columnName = column.data('name').toLowerCase();

                                var options = data[columnName];

    							options.sort(function(o1, o2) {
                            	  	var t1 = o1.text.toLowerCase(), t2 = o2.text.toLowerCase();
                            	  	return t1 > t2 ? 1 : t1 < t2 ? -1 : 0;
                            	});
                                column.html("");

                                var select = $("<select class='column-filter-dropdown' data-table='" + id + "'data-name='" + columnName + "' multiple></select>").appendTo(column);

                                // Have to add this as it is possible to have users without a team and we want to filter on those
                                if (id === 'users_of_teams-table' && columnName === 'teams') {
                                	select.append($("<option value='-1' selected><?php echo $escaper->escapeHtml($lang['UsersWithoutTeam']); ?></option>"));
                                } else
                                
                                // Have to add this as it is possible to have users without a team and we want to filter on those
                                if (id === 'teams_of_users-table' && columnName === 'teams') {
                                	select.append($("<option value='-1' selected><?php echo $escaper->escapeHtml($lang['UsersWithoutTeam']); ?></option>"));
                                } else

                                // Have to add this as it is possible to have users without a permission and we want to filter on those
                                if (id === 'users_of_permissions-table' && columnName === 'permissions') {
                                	select.append($("<option value='-1' selected><?php echo $escaper->escapeHtml($lang['UsersWithoutPermission']); ?></option>"));
                                } else
                                    
                                // Have to add this as it is possible to have users without a permission and we want to filter on those
                                if (id === 'permissions_of_users-table' && columnName === 'permissions') {
                                	select.append($("<option value='-1' selected><?php echo $escaper->escapeHtml($lang['UsersWithoutPermission']); ?></option>"));
                                } else

								// Have to add this as it is possible to have users without a role or a role without a user assigned and we want to filter on those
                                if (id === 'users_of_roles-table') {
                                    if (columnName === 'roles') {
                                		select.append($("<option value='-1' selected><?php echo $escaper->escapeHtml($lang['NoRole']); ?></option>"));
                                    } else {
                                    	select.append($("<option value='-1' selected><?php echo $escaper->escapeHtml($lang['NoUser']); ?></option>"));
                                    }
                                }

                                $.each(options, function(i, item) {
                                	select.append($("<option value='" + item.value + "' selected>" + item.text + "</option>"));
                                });
                            });
                            
                            $("tr.filter", header).show();

                            // Have to throttle the refreshing of the datatable to let the users select more than one option from the filters per refresh
                            var throttledDatatableRefresh = function() {
                            	var table = $(this.$select).data('table');
                            	clearTimeout(filterSubmitTimer[table]);
                            	filterSubmitTimer[table] = setTimeout(function() {
                                	// To close the dropdowns on re-draw as for some reason it's unchecking the checkboxes when re-drawing the table
                                	// This is just a visual thing as the state of those options won't become unchecked
                            		$('div.table-container[data-id=' + table + '] div.btn-group.open').removeClass('open');
    
                            		clearTimeout(filterSubmitTimer[table]);
                                	reportDatatables[table].draw();
                                }, 2000);
                            }
                            
                            $('.column-filter-dropdown', header).multiselect({
                                enableFiltering: true,
                                buttonWidth: '100%',
                                maxHeight: 150,
                                numberDisplayed: 1,
                                enableCaseInsensitiveFiltering: true,
                                allSelectedText: '<?php echo $escaper->escapeHtml($lang['All']); ?>',
                                includeSelectAllOption: true,
                                onChange: throttledDatatableRefresh,
                                onSelectAll: throttledDatatableRefresh,
                                onDeselectAll: throttledDatatableRefresh
                            });

    						// When the filters are created, we're drawing the table
                            reportDatatable.draw();
                        },
                        error: function(xhr, status, error) {
                            if(!retryCSRF(xhr, this)) {}
                        }
                    });
                }
            });
    
            reportDatatable.on('draw', function(e, settings){
                if(settings._iDisplayLength == -1){
                    $("#" + settings.sTableId + "_wrapper").find(".paginate_button.current").removeClass("current");
                }
                $('.paginate_button.first').html('<i class="fa fa-chevron-left"></i><i class="fa fa-chevron-left"></i>');
                $('.paginate_button.previous').html('<i class="fa fa-chevron-left"></i>');
    
                $('.paginate_button.last').html('<i class="fa fa-chevron-right"></i><i class="fa fa-chevron-right"></i>');
                $('.paginate_button.next').html('<i class="fa fa-chevron-right"></i>');
            });
    
            reportDatatables[id] = reportDatatable;
    
            $('#view-all-' + id).html("<?php echo $escaper->escapeHtml($lang['All']); ?>");
    
            $('#view-all-' + id).click(function(){
                var $this = $(this);
    
                var id = $(this).attr('id').replace("view-all-", "");
                var oSettings =  reportDatatables[id].settings();
                if(oSettings[0]._iDisplayLength == -1){
                    oSettings[0]._iDisplayLength = 10;
                    reportDatatables[id].draw();
                    $this.removeClass("current");
                } else {
                    oSettings[0]._iDisplayLength = -1;
                    reportDatatables[id].draw();
                    $this.addClass('current');
                }
            });
        }

    </script>
    
	<style>
	   .dataTables_scrollHead {overflow: visible !important;}
	   
	   #report_displayed_dropdown {
	       padding: 5px;
	       margin-left: 10px;
	   }
	   
	   .tabs li:focus {
	       outline: none;
	   }

	</style>
	
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
            <?php view_configure_menu("UserManagement"); ?>
        </div>
        <div class="span9">
          <div id="main" class="wrap">
            <ul class="tabs group">
              <li><a <?php echo ($addusers_tab ? "class=\"active\"" : ""); ?> href="#addusers"><?php echo $escaper->escapeHtml($lang['AddUsers']); ?></a></li>
              <li><a <?php echo ($manageusers_tab ? "class=\"active\"" : ""); ?> href="#manageusers"><?php echo $escaper->escapeHtml($lang['ManageUsers']); ?></a></li>
              <li><a <?php echo ($usersettings_tab ? "class=\"active\"" : ""); ?> href="#usersettings"><?php echo $escaper->escapeHtml($lang['UserSettings']); ?></a></li>
              <li><a <?php echo ($userreports_tab ? "class=\"active\"" : ""); ?> href="#userreports"><?php echo $escaper->escapeHtml($lang['UserReports']); ?></a></li>
            </ul>
            <div id="content">

              <!-- Add Users Tab -->
              <div id="addusers" <?php echo ($addusers_tab ? "" : "style=\"display: none;\""); ?> class="settings_tab">

                <table border="1" width="600" cellpadding="10px">
                  <tbody>
                    <tr>
                      <td>

                        <form name="add_user" method="post" action="">
                            <table border="0" cellspacing="0" cellpadding="0">
                                <tr><td colspan="2"><h4><?php echo $escaper->escapeHtml($lang['AddANewUser']); ?>:</h4></td></tr>
                                <tr>
                                    <td><?php echo $escaper->escapeHtml($lang['Type']); ?>:&nbsp;</td>
                                    <td>
                                        <select name="type" id="select" onChange="handleSelection(value)">
                                            <option selected value="1">SimpleRisk</option>
                                            <?php
                                                // If the custom authentication extra is enabeld
                                                if (custom_authentication_extra())
                                                {
                                                    // Display the LDAP option
                                                    echo "<option value=\"2\">LDAP</option>\n";

                                                    // Display the SAML option
                                                    echo "<option value=\"3\">SAML</option>\n";
                                                }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr><td><?php echo $escaper->escapeHtml($lang['FullName']); ?>:&nbsp;</td><td><input name="name" type="text" maxlength="50" size="20" value="<?php echo isset($name) ? $escaper->escapeHtml($name) : "" ?>" /></td></tr>
                                <tr><td><?php echo $escaper->escapeHtml($lang['EmailAddress']); ?>:&nbsp;</td><td><input name="email" type="email" maxlength="200" value="<?php echo isset($email) ? $escaper->escapeHtml($email) : "" ?>" size="20" /></td></tr>
                                <tr><td><?php echo $escaper->escapeHtml($lang['Username']); ?>:&nbsp;</td><td><input name="new_user" type="text" maxlength="200" value="<?php echo isset($user) ? $escaper->escapeHtml($user) : "" ?>" size="20" /></td></tr>
                                <tr class="ldap_pass"><td><?php echo $escaper->escapeHtml($lang['Password']); ?>:&nbsp;</td><td><input name="password" type="password" maxlength="50" size="20" autocomplete="off" /></td></tr>
                                <tr class="ldap_pass"><td><?php echo $escaper->escapeHtml($lang['RepeatPassword']); ?>:&nbsp;</td><td><input name="repeat_password" type="password" maxlength="50" size="20" autocomplete="off" /></td></tr>
                            </table>
                            <div>
                                <input name="change_password" id="change_password" <?php if(isset($change_password) && $change_password == 1) echo "checked"; ?> class="hidden-checkbox" type="checkbox" value="1" />  <label for="change_password">  &nbsp;&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['RequirePasswordChangeOnLogin']); ?> </label> 
                            </div>

                            <h6>
                                <u><?php echo $escaper->escapeHtml($lang['Manager']); ?></u>
                            </h6>
                            <?php create_dropdown("enabled_users_all", "", "manager"); ?>

                            <h6><u><?php echo $escaper->escapeHtml($lang['Teams']); ?></u></h6>
                            <?php create_multiple_dropdown("team", null, null, get_all_teams()); ?>

                            <h6><u><?php echo $escaper->escapeHtml($lang['Role']); ?></u></h6>
                            <?php create_dropdown("role", get_setting('default_user_role')); ?>

							<br/>
                            <input style="display:none" type="checkbox" name="admin" id="admin">
							<button id="admin_button" type="button" class="btn btn-danger" data-grant="<?php echo $escaper->escapeHtml($lang['GrantAdmin']); ?>" data-remove="<?php echo $escaper->escapeHtml($lang['RemoveAdmin']); ?>" title="<?php echo $escaper->escapeHtml($lang['AdminRoleDescription']);?>"><?php echo $escaper->escapeHtml($lang['GrantAdmin']);?></button>

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
                            <h6><u><?php echo $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?></u></h6>
                            <input id="none" type="radio" name="multi_factor" value="1" checked />&nbsp;<?php echo $escaper->escapeHtml($lang['None']); ?><br />
                            <?php
                                // If the custom authentication extra is installed
                                if (custom_authentication_extra())
                                {
                                    // Include the custom authentication extra
                                    require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

                                    // Display the multi factor authentication options
                                    multi_factor_authentication_options(1);

                                }
                            ?>
                            <br />
                            <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_user" /><br />
                        </form>

                      </td>
                    </tr>
                  </tbody>
                </table>

              </div>

              <!-- Manage Users Tab -->
              <div id="manageusers" <?php echo ($manageusers_tab ? "" : "style=\"display: none;\""); ?> class="settings_tab">

                <table border="1" width="600" cellpadding="10px">
                  <tbody>
                    <tr>
                      <td>
                        <form name="select_user" method="post" action="view_user_details.php">
                                <h4><?php echo $escaper->escapeHtml($lang['ViewDetailsForUser']); ?>:</h4>
                                <?php echo $escaper->escapeHtml($lang['DetailsForUser']); ?> <?php create_dropdown('enabled_users_all', null, 'user'); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Select']); ?>" name="select_user" />
                        </form>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <br />

                <table border="1" width="600" cellpadding="10px">
                  <tbody>
                    <tr>
                      <td>
                        <form name="enable_disable_user" method="post" action="">
                                <h4><?php echo $escaper->escapeHtml($lang['EnableAndDisableUsers']); ?>:</h4>
                                <?php echo $escaper->escapeHtml($lang['EnableAndDisableUsersHelp']); ?>.
				<br /><br />
                                <?php echo $escaper->escapeHtml($lang['DisableUser']); ?> <?php create_dropdown("enabled_users_all"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Disable']); ?>" name="disable_user" />
				<br />
                                <?php echo $escaper->escapeHtml($lang['EnableUser']); ?> <?php create_dropdown("disabled_users_all"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Enable']); ?>" name="enable_user" />
                        </form>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <br />

                <table border="1" width="600" cellpadding="10px">
                  <tbody>
                    <tr>    
                      <td>  
                        <form name="delete_user" method="post" action="">
                                <h4><?php echo $escaper->escapeHtml($lang['DeleteAnExistingUser']); ?>:</h4>
                                <?php echo $escaper->escapeHtml($lang['DeleteCurrentUser']); ?> <?php create_dropdown("user"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_user" />
                        </form>
                      </td>
                    </tr>
                  </tbody>
                </table>  

                <br />

                <table border="1" width="600" cellpadding="10px">
                  <tbody>
                    <tr>
                      <td>
                        <form name="password_reset" method="post" action="">
                                <h4><?php echo $escaper->escapeHtml($lang['PasswordReset']); ?>:</h4>
                                <?php echo $escaper->escapeHtml($lang['SendPasswordResetEmailForUser']); ?> <?php create_dropdown("user"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Send']); ?>" name="password_reset" />
                        </form>
                      </td>
                    </tr>
                  </tbody>
                </table> 

              </div>

              <!-- User Settings Tab -->
              <div id="usersettings" <?php echo ($usersettings_tab ? "" : "style=\"display: none;\""); ?> class="settings_tab">

                <form name="password_policy" method="post" action="">

                <table border="1" width="600" cellpadding="10px">
                  <tbody>
                    <tr>
                      <td>
                            <h4><?php echo $escaper->escapeHtml($lang['UserPolicy']); ?>:</h4>
                            <input class="hidden-checkbox" type="checkbox" id="strict_user_validation" name="strict_user_validation"<?php if (get_setting('strict_user_validation') == 1) echo " checked" ?> /><label for="strict_user_validation">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['UseCaseSensitiveValidationOfUsername']); ?></label>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <br />

                <table border="1" width="600" cellpadding="10px">
                  <tbody>
                    <tr>
                      <td>
                            <h4><?php echo $escaper->escapeHtml($lang['AccountLockoutPolicy']); ?>:</h4>

                            <?php echo $escaper->escapeHtml($lang['MaximumAttemptsLockout']); ?>:&nbsp;&nbsp;<input style="width:50px" type="number" id="pass_policy_attempt_lockout" name="pass_policy_attempt_lockout" min="0" maxlength="2" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_attempt_lockout')); ?>"/> <?php echo $escaper->escapeHtml($lang['attempts']); ?>&nbsp;&nbsp;[0 = Lockout Disabled]

                            <?php echo $escaper->escapeHtml($lang['MaximumAttemptsLockoutTime']); ?>:&nbsp;&nbsp;<input style="width:50px" type="number" id="pass_policy_attempt_lockout_time" name="pass_policy_attempt_lockout_time" min="0" maxlength="2" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_attempt_lockout_time')); ?>"/> <?php echo $escaper->escapeHtml($lang['minutes']); ?>.&nbsp;&nbsp;[0 = Manual Enable Required]
                      </td>
                    </tr>
                  </tbody>
                </table>

                <br />

                <table border="1" width="600" cellpadding="10px">
                  <tbody>
                    <tr>
                      <td>
                            <h4><?php echo $escaper->escapeHtml($lang['PasswordPolicy']); ?>:</h4>

                            <input class="hidden-checkbox" type="checkbox" id="pass_policy_enabled" name="pass_policy_enabled"<?php if (get_setting('pass_policy_enabled') == 1) echo " checked" ?> /><label for="pass_policy_enabled">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['Enabled']); ?></label>

                            <?php echo $escaper->escapeHtml($lang['MinimumNumberOfCharacters']); ?> <input style="width:50px" type="number" id="min_characters" name="min_characters" min="1" max="50" maxlength="2" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_min_chars')); ?>"/> [1-50]

                            <input type="checkbox" class="hidden-checkbox" id="alpha_required" name="alpha_required"<?php if (get_setting('pass_policy_alpha_required') == 1) echo " checked" ?>  /><label for="alpha_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireAlphaCharacter']); ?></label>

                            <input type="checkbox" class="hidden-checkbox" id="upper_required" name="upper_required"<?php if (get_setting('pass_policy_upper_required') == 1) echo " checked" ?>  /><label for="upper_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireUpperCaseCharacter']); ?></label>

                            <input type="checkbox" class="hidden-checkbox" id="lower_required" name="lower_required"<?php if (get_setting('pass_policy_lower_required') == 1) echo " checked" ?>  /><label for="lower_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireLowerCaseCharacter']); ?></label>

                            <input type="checkbox" class="hidden-checkbox" id="digits_required" name="digits_required"<?php if (get_setting('pass_policy_digits_required') == 1) echo " checked" ?>  /><label for="digits_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireNumericCharacter']); ?></label>

                            <input type="checkbox" class="hidden-checkbox" id="special_required" name="special_required"<?php if (get_setting('pass_policy_special_required') == 1) echo " checked" ?> /><label for="special_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireSpecialCharacter']); ?></label>

                            <?php echo $escaper->escapeHtml($lang['MinimumPasswordAge']); ?>:&nbsp;&nbsp;<input style="width:50px" type="number" id="pass_policy_min_age" name="pass_policy_min_age" min="0" maxlength="4" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_min_age')); ?>"/> <?php echo $escaper->escapeHtml($lang['days']); ?>.&nbsp;&nbsp;[0 = Min Age Disabled]<br/>

                            <?php echo $escaper->escapeHtml($lang['MaximumPasswordAge']); ?>:&nbsp;&nbsp;<input style="width:50px" type="number" id="pass_policy_max_age" name="pass_policy_max_age" min="0" maxlength="4" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_max_age')); ?>"/> <?php echo $escaper->escapeHtml($lang['days']); ?>.&nbsp;&nbsp;[0 = Max Age Disabled]<br/>

                            <?php echo $escaper->escapeHtml($lang['RememberTheLast']); ?>&nbsp;&nbsp;<input class="text-right" style="width:70px" type="number" id="pass_policy_reuse_limit" name="pass_policy_reuse_limit" min="0" maxlength="4" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_reuse_limit')); ?>"/> <?php echo $escaper->escapeHtml($lang['Passwords']); ?>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <br />

                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="password_policy_update" />

                </form>

              </div>
              
              <!-- User Reports Tab -->
              <div id="userreports" <?php echo ($userreports_tab ? "" : "style=\"display: none;\""); ?> class="reports_tab">

					<u><?php echo $escaper->escapeHtml($lang['ReportDisplayed']); ?></u>:
                    <select id="report_displayed_dropdown" class="form-field form-control" style="width:auto !important;">
                    <?php if ($separation) { ?>
                        <option value='users_of_teams' selected><?php echo $escaper->escapeHtml($lang['UsersOfTeams']);?></option>
                        <option value='teams_of_users'><?php echo $escaper->escapeHtml($lang['TeamsOfUsers']);?></option>
					<?php } ?>
                        <option value='users_of_permissions' <?php if (!$separation) { ?>selected<?php } ?>><?php echo $escaper->escapeHtml($lang['UsersOfPermissions']);?></option>
                        <option value='permissions_of_users'><?php echo $escaper->escapeHtml($lang['PermissionsOfUsers']);?></option>
                        <option value='users_of_roles'><?php echo $escaper->escapeHtml($lang['UsersOfRoles']);?></option>
                    </select>

					<?php if ($separation) { ?>
                    <div id="users_of_teams-report" class="report">
                        <?php display_user_management_reports_datatable('users_of_teams'); ?>
                    </div>

                    <div id="teams_of_users-report" style="display: none" class="report">
                        <?php display_user_management_reports_datatable('teams_of_users'); ?>
                    </div>
					<?php } ?>
					
                    <div id="users_of_permissions-report" class="report" <?php if ($separation) { ?>style="display: none"<?php } ?>>
                        <?php display_user_management_reports_datatable('users_of_permissions'); ?>
                    </div>

                    <div id="permissions_of_users-report" class="report" style="display: none">
                        <?php display_user_management_reports_datatable('permissions_of_users'); ?>
                    </div>

                    <div id="users_of_roles-report" class="report" style="display: none">
                        <?php display_user_management_reports_datatable('users_of_roles'); ?>
                    </div>
					
              </div>
            </div>
          </div>


        </div>
    </div>
</div>
<?php display_set_default_date_format_script(); ?>



</body>

</html>
