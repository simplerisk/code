<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar([], ['check_admin' => true]);

if(isset($_POST['submit_mysqlpath'])){
	update_setting('mysqldump_path', $_POST['mysqldump_path']);
	set_alert(true, "good", $lang['MysqldumpPathWasSavedSuccessfully']);
}

// If the user wants to disable the registration notice
if (isset($_POST['disable_registration_notice']))
{
	// Add a setting to disable the registration notice
	add_setting("disable_registration_notice", "true");

	// Set the registration notice to false
	$registration_notice = false;
}
// Otherwise
else
{
	// If SimpleRisk is already registered
	if (get_setting('registration_registered') == 1)
	{
		// Set the registration notice to false
		$registration_notice = false;
	}
	// If the registration notice has been disabled
	else if (get_setting("disable_registration_notice") == "true")
	{
		// Set the registration notice to false
		$registration_notice = false;
	}
	// Otherwise the registration notice is true
	else $registration_notice = true;
}

// If SimpleRisk is not registered
if (get_setting('registration_registered') == 0)
{
	// Set registered to false
	$registered = false;

	// If the user has sent registration information
	if (isset($_POST['register']))
	{
		// Get the posted values
		$name = (isset($_POST['name']) ? $_POST['name'] : "");
		$fname = (isset($_POST['fname']) ? $_POST['fname'] : "");
		$lname = (isset($_POST['lname']) ? $_POST['lname'] : "");
		$company = $_POST['company'];
		$title = $_POST['title'];
		$phone = $_POST['phone'];
		$email = $_POST['email'];

		// Add the registration
		$result = add_registration($name, $company, $title, $phone, $email, $fname, $lname);

		// If the registration failed
		if ($result == 0)
		{
			// Display an alert
			set_alert(true, "bad", "There was a problem registering your SimpleRisk instance.");
		}
		else
		{
			// Display an alert
			set_alert(true, "good", "SimpleRisk instance registered successfully.");

			// Set registered to true
			$registered = true;
		}
	}
}
// SimpleRisk is registered
else
{
	// Set registered to true
	$registered = true;

	// If the user has updated their registration information
	if (isset($_POST['register']))
	{
		// Get the posted values
		$name = (isset($_POST['name']) ? $_POST['name'] : "");
		$fname = (isset($_POST['fname']) ? $_POST['fname'] : "");
		$lname = (isset($_POST['lname']) ? $_POST['lname'] : "");
		$company = $_POST['company'];
		$title = $_POST['title'];
		$phone = $_POST['phone'];
		$email = $_POST['email'];

		// Update the registration
		$result = update_registration($name, $company, $title, $phone, $email, $fname, $lname);

		// If the registration failed
		if ($result == 0)
		{
			// Display an alert
			set_alert(true, "bad", "There was a problem updating your SimpleRisk instance.");
		}
		else
		{
			// Display an alert
			set_alert(true, "good", "SimpleRisk instance updated successfully.");
		}
	}
	// Otherwise get the registration values from the database
	else
	{
		$name = get_setting("registration_name");
		$fname = get_setting("registration_fname");
		$lname = get_setting("registration_lname");
		$company = get_setting("registration_company");
		$title = get_setting("registration_title");
		$phone = get_setting("registration_phone");
		$email = get_setting("registration_email");
	}

	// If the user wants to install the Upgrade Extra
	if (isset($_POST['get_upgrade_extra']))
	{
		// Download the extra
		$result = download_extra("upgrade");
	}
	// If the user wants to install the Authentication Extra
	else if (isset($_POST['get_authentication_extra']))
	{
		// Download the extra
		$result = download_extra("authentication");
	}
	// If the user wants to install the Encryption Extra
	else if (isset($_POST['get_encryption_extra']))
	{
		// Download the extra
		$result = download_extra("encryption");
	}
	// If the user wants to install the Import-Export Extra
	else if (isset($_POST['get_importexport_extra']))
	{
		// Download the extra
		$result = download_extra("import-export");
	}
	// If the user wants to install the Notification Extra
	else if (isset($_POST['get_notification_extra']))
	{
		// Download the extra
		$result = download_extra("notification");
	}
	// If the user wants to install the Separation Extra
	else if (isset($_POST['get_separation_extra']))
	{
		// Download the extra
		$result = download_extra("separation");
	}
	else if (isset($_POST['get_governance_extra']))
	{
		// Download the extra
		$result = download_extra("governance");
	}
	// If the user wants to install the Risk Assessments Extra
	else if (isset($_POST['get_assessments_extra']))
	{
		// Download the extra
		$result = download_extra("assessments");
	}
	// If the user wants to install the API Extra
	else if (isset($_POST['get_api_extra']))
	{
		// Download the extra
		$result = download_extra("api");
	}
	// If the user wants to install the ComplianceForge Extra
	else if (isset($_POST['get_complianceforge_extra']))
	{
		// Download the extra
		$result = download_extra("complianceforge");
	}
	// If the user wants to install the ComplianceForge SCF Extra
	else if (isset($_POST['get_complianceforge_scf_extra']))
	{
		// Download the extra
		$result = download_extra("complianceforgescf");
	}
	// If the user wants to install the Customization Extra
	else if (isset($_POST['get_customization_extra']))
	{
		// Download the extra
		$result = download_extra("customization");
	}
	// If the user wants to install the Advanced Search Extra
	else if (isset($_POST['get_advanced_search_extra']))
	{
		// Download the extra
		$result = download_extra("advanced_search");
	}
	// If the user wants to install the Jira Extra
	else if (isset($_POST['get_jira_extra']))
	{
		// Download the extra
		$result = download_extra("jira");
	}
	// If the user wants to install the UCF Extra
	else if (isset($_POST['get_ucf_extra']))
	{
		// Download the extra
		$result = download_extra("ucf");
	}
	// If the user wants to install the Org Hierarchy Extra
	else if (isset($_POST['get_organizational_hierarchy_extra']))
	{
		// Download the extra
		$result = download_extra("organizational_hierarchy");
	}
	// If the user wants to install the Incident Management Extra
	else if (isset($_POST['get_incident_management_extra']))
	{
		// Download the extra
		$result = download_extra("incident_management");
	}
	// If the user wants to install the Vulnerability Management Extra
	else if (isset($_POST['get_vulnmgmt_extra']))
	{
		// Download the extra
		$result = download_extra("vulnmgmt");
	}
}
?>
<div class="row bg-white">
	<div class="col-12">
		<div class="card-body my-2 border">
			<h4><?php echo $escaper->escapeHtml($lang['RegisterSimpleRisk']); ?></h4>
			<p><?php echo $escaper->escapeHtml($lang['RegistrationText']); ?></p>
<?php if ($registration_notice === true) { ?>
			<form name="no_message" method="post" action="">
				<input type="submit" name="disable_registration_notice" value="<?= $escaper->escapeHtml($lang['DisableRegistrationNotice']); ?>" class="btn btn-submit"/>
			</form>
<?php } ?>
		</div>
		<div class="card-body my-2 border">
			<label class="m-r-10">Instance ID:</label><span><?= $escaper->escapeHtml(get_setting("instance_id")); ?></span>
		</div>

<?php if(!is_process("mysqldump")) { ?>
		<div class="card-body my-2 border">
			<h4>Set Mysql Service Path</h4>
			<form method="POST" action="">
				<div class="form-group col-6">
					<label>Mysqldump Path: &nbsp;</label>
					<input  name="mysqldump_path" value="<?php echo $escaper->escapeHtml(get_setting('mysqldump_path')); ?>" type="text" class="form-control">
				</div>
				<input value="Submit" name="submit_mysqlpath" type="submit" class="btn btn-submit">
			</form>
		</div>
<?php } ?>
		
		<div class="row my-2">
			<div class="col-6">
				<div class="card-body border" id="register-panel">
					<div class="hero-unit">
						<h4><?= $escaper->escapeHtml($lang['RegistrationInformation']); ?></h4>
						<form name="register" method="post" action="">
				<?php
					// If the instance is not registered
					if (!$registered)
					{
							// Display the registration table
							display_registration_table_edit();
					}
					// The instance is registered
					else
					{
						// The user wants to update the registration
						if (isset($_POST['update']))
						{
							// Display the editable registration table
							display_registration_table_edit($name, $company, $title, $phone, $email, $fname, $lname);
						}
						else
						{
							// Display the registration table
							display_registration_table($name, $company, $title, $phone, $email, $fname, $lname);
						}
					}
				?>
						</form>	
					</div>
				</div>
			</div>
			<div class="col-6 d-flex flex-column">
				<div class="card-body border flex-grow-1" id="upgrade-panel">
					<div class="hero-unit">
						<h4><?php echo $escaper->escapeHtml($lang['UpgradeSimpleRisk']); ?></h4>
						<?php
							// If the instance is not registered
							if (!$registered)
							{
								echo "Please register in order to be able to use the easy upgrade feature.";
							}
							// The instance is registered
							else
							{
								display_upgrade();
							}
						?>
					</div>
				</div>
			</div>
		</div>
		<div class="card-body my-2 border">
			<div class="hero-unit">
		<?php
			// If the instance is not registered
			if (!$registered)
			{
				echo "Please register in order to be able to use the easy upgrade feature.";
			}
			// The instance is registered
			else
			{
				core_display_upgrade_extras();
			}
		?>
			</div>
		</div>
	</div>
</div>
<script type='text/javascript'>
	var last_response_len = false;
	var progress_window = $('.progress-window');
	
	$(document).ready(function(){
		$('#app_upgrade').click(function() {
			progress_window.html('');
			$('.progress-wrapper').show();
			$('#upgrade-panel .hero-unit').height($('#register-panel .hero-unit').height());
			
			$.ajax(BASE_URL + '/api/one_click_upgrade', {
				method: 'POST',
				data: {data: 1},
				xhrFields: {
					onprogress: function(e)
					{
						var this_response, response = e.currentTarget.response;
						if(response.indexOf('__csrf_magic') > -1){
							return;
						}
						
						if(last_response_len === false)
						{
							this_response = response;
							last_response_len = response.length;
						}
						
						else
						{
							this_response = response.substring(last_response_len);
							last_response_len = response.length;
						}
						progress_window.append('<div style=\"\">' + this_response + '</div>');
						progress_window.animate({ scrollTop: 9999 });
					}
				},
				error: function(xhr,status,error){
					if(!retryCSRF(xhr, this))
					{
						if(xhr.responseJSON && xhr.responseJSON.status_message){
							showAlertsFromArray(xhr.responseJSON.status_message);
						}
					}
					
				}
			})
			.done(function(data)
			{
				/*progress_window.append('<div style=\"color: limegreen\"><?= $lang['UpdateSuccessful'] ?></div>');
				progress_window.animate({ scrollTop: 9999 });*/
			})
			.fail(function(xhr, status, errorMessage)
			{
				if(retryCSRFCount > 5){
					progress_window.append('<div style=\"color: orangered\"><?= $lang['UpdateFailed'] ?></div>');
					progress_window.append('<div style=\"color: orangered\">' + status +  '(' + errorMessage + ')</div>');
					progress_window.animate({ scrollTop: 9999 });
				}
				
			});
	
		});
	});
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>