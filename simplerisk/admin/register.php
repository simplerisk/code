<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/licensing.php'));
render_header_and_sidebar([], ['check_admin' => true]);

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

		// Register with the licensing service (retries on 409 collision)
		$result = licensing_register_with_retry([
			'fname'   => $fname,
			'lname'   => $lname,
			'company' => $company,
			'title'   => $title,
			'phone'   => $phone,
			'email'   => $email,
		]);

		if ($result['ok']) {
			// Persist identity and credentials
			update_or_insert_setting('instance_id',      $result['instance_id']);
			update_or_insert_setting('services_api_key', $result['services_api_key']);

			// Persist the registrant contact details
			update_or_insert_setting('registration_name',    $name);
			update_or_insert_setting('registration_fname',   $fname);
			update_or_insert_setting('registration_lname',   $lname);
			update_or_insert_setting('registration_company', $company);
			update_or_insert_setting('registration_title',   $title);
			update_or_insert_setting('registration_phone',   $phone);
			update_or_insert_setting('registration_email',   $email);
			update_or_insert_setting('registration_registered', 1);

			write_debug_log("Registration successful: {$result['instance_id']}", 'notice');

			// Warm the local entitlements cache for the new identity so paid
			// extras are immediately installable on the next page load.
			// Up to 5s extra latency on the registration page — acceptable
			// trade-off vs the alternative (cache cold for up to 24h until the
			// next daily cron).
			license_check_daily();

			// If this is not a hosted instance, download the Upgrade Extra
			if (get_setting('hosting_tier') == false) {
				$dl = download_extra("upgrade");
				if (!is_string($dl)) {
					$err = is_array($dl) ? ($dl['reason'] ?? $dl['error'] ?? 'unknown') : 'unknown';
					write_debug_log("Failed to download 'upgrade' Extra after registration: {$err}", 'warning');
				}
			}

			set_alert(true, "good", $lang['RegistrationSuccessful']);

			// Set registered to true
			$registered = true;
		} else {
			write_debug_log("Registration failed: {$result['error']}", 'warning');
			set_alert(true, "bad", $lang['FailedToRegisterInstance']);
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

		// Push updated identity to the licensing service
		$result = licensing_instance_update([
			'instance_id'      => get_setting('instance_id'),
			'services_api_key' => get_setting('services_api_key'),
			'fname'            => $fname,
			'lname'            => $lname,
			'company'          => $company,
			'title'            => $title,
			'phone'            => $phone,
			'email'            => $email,
		]);

		if ($result['ok']) {
			// Persist the updated contact details locally
			update_or_insert_setting('registration_name',    $name);
			update_or_insert_setting('registration_fname',   $fname);
			update_or_insert_setting('registration_lname',   $lname);
			update_or_insert_setting('registration_company', $company);
			update_or_insert_setting('registration_title',   $title);
			update_or_insert_setting('registration_phone',   $phone);
			update_or_insert_setting('registration_email',   $email);

			write_debug_log("Instance info updated", 'notice');
			set_alert(true, "good", $lang['InstanceInformationUpdated']);
		} else {
			write_debug_log("Instance info update failed: {$result['error']}", 'warning');
			set_alert(true, "bad", $lang['FailedToUpdateInstance']);
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

	// Map POST keys to extra short-names so the error-checking logic is written once.
	$extra_download_map = [
		'get_upgrade_extra'                    => 'upgrade',
		'get_authentication_extra'             => 'authentication',
		'get_encryption_extra'                 => 'encryption',
		'get_importexport_extra'               => 'import-export',
		'get_notification_extra'               => 'notification',
		'get_separation_extra'                 => 'separation',
		'get_assessments_extra'                => 'assessments',
		'get_api_extra'                        => 'api',
		'get_complianceforge_scf_extra'        => 'complianceforgescf',
		'get_customization_extra'              => 'customization',
		'get_advanced_search_extra'            => 'advanced_search',
		'get_jira_extra'                       => 'jira',
		'get_ucf_extra'                        => 'ucf',
		'get_organizational_hierarchy_extra'   => 'organizational_hierarchy',
		'get_incident_management_extra'        => 'incident_management',
		'get_vulnmgmt_extra'                   => 'vulnmgmt',
		'get_workflows_extra'                  => 'workflows',
		'get_artificial_intelligence_extra'    => 'artificial_intelligence',
	];

	$extra_name_to_download = null;
	foreach ($extra_download_map as $post_key => $extra_short_name) {
		if (isset($_POST[$post_key])) {
			$extra_name_to_download = $extra_short_name;
			break;
		}
	}

	if ($extra_name_to_download !== null) {
		$result = download_extra($extra_name_to_download);
		if (!is_string($result)) {
			$err = is_array($result) ? ($result['reason'] ?? $result['error'] ?? 'unknown') : 'unknown';
			write_debug_log("Failed to download '{$extra_name_to_download}' Extra from register page: {$err}", 'warning');
			set_alert(true, "bad", $lang['FailedToDownloadExtra']);
		}
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
			<?php // Two-column grid: the label column auto-sizes to the widest label
			      // (max-content), so values align with no per-row guesswork; column-gap
			      // gives an even label/value gap on every row. ?>
			<div class="sr-info-grid" style="display: grid; grid-template-columns: max-content 1fr; column-gap: 1.5rem; row-gap: 0.4rem; align-items: baseline;">
				<label class="mb-0"><?= $escaper->escapeHtml($lang['InstanceID']); ?>:</label><span><?= $escaper->escapeHtml(get_setting("instance_id")); ?></span>
<?php
			// Read the latest-version cache directly (settings.latest_version_data,
			// written by the core_version_check job) so this page NEVER makes a
			// network call — latest_version(..., false) would fall through to a
			// live feed fetch when the cache is cold. An empty cache yields '',
			// which version_update_status() reports as 'unknown' (current only).
			$sr_latest = json_decode((string)get_setting('latest_version_data', false, false), true);
			$sr_latest = is_array($sr_latest) ? $sr_latest : [];
			$sr_version_rows = [
				['label' => $lang['ApplicationVersion'], 'current' => (string)current_version('app'), 'latest' => (string)($sr_latest['app'] ?? '')],
				['label' => $lang['DatabaseVersion'],    'current' => (string)current_version('db'),  'latest' => (string)($sr_latest['db'] ?? '')],
			];
			foreach ($sr_version_rows as $sr_row) {
				$sr_status = version_update_status($sr_row['current'], $sr_row['latest']);
?>
				<label class="mb-0"><?= $escaper->escapeHtml($sr_row['label']); ?>:</label><span><?= $escaper->escapeHtml($sr_row['current']); ?><?php if ($sr_status !== 'unknown') { ?><span class="text-muted mx-2">(<?= _lang('LatestIsVersion', ['version' => $sr_row['latest']]); ?>)</span><?php if ($sr_status === 'up_to_date') { ?><span class="badge bg-success"><?= $escaper->escapeHtml($lang['UpToDate']); ?></span><?php } else { ?><span class="badge bg-warning text-dark"><?= $escaper->escapeHtml($lang['UpdateAvailable']); ?></span><?php } ?><?php } ?></span>
<?php } ?>
			</div>
		</div>
		
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
	var progress_window = $('.progress-window');

	// Returns an onprogress handler with its own position counter so that
	// two sequential streaming AJAX calls can both append to progress_window
	// without interfering with each other's byte offsets.
	function makeProgressHandler() {
		var responseLen = false;
		return function(e) {
			var this_response, response = e.currentTarget.response;
			if (response.indexOf('__csrf_magic') > -1) { return; }
			if (responseLen === false) {
				this_response = response;
				responseLen = response.length;
			} else {
				this_response = response.substring(responseLen);
				responseLen = response.length;
			}
			progress_window.append('<div>' + this_response + '</div>');
			progress_window.animate({ scrollTop: 9999 });
		};
	}

	$(document).ready(function(){
		$('#app_upgrade').click(function() {
			progress_window.html('');
			$('.progress-wrapper').show();
			$('#upgrade-panel .hero-unit').height($('#register-panel .hero-unit').height());

			// Phase 1: ask the core API to update the upgrade extra only.
			// After this the new extra code is on disk; the next HTTP request
			// will load it — eliminating any dependency on this core version's
			// call_extra_api_functionality for the long-running backup/upgrade steps.
			$.ajax(BASE_URL + '/api/v2/one_click_upgrade', {
				method: 'POST',
				data: {data: 1, step: 'update_extra'},
				xhrFields: { onprogress: makeProgressHandler() },
				error: function(xhr, status, error) {
					if (!retryCSRF(xhr, this)) {
						if (xhr.responseJSON && xhr.responseJSON.status_message) {
							showAlertsFromArray(xhr.responseJSON.status_message);
						}
					}
				}
			})
			.done(function() {
				// Phase 2: run backup and upgrade directly inside the upgrade extra.
				// This POST goes to extras/upgrade/index.php which loads the freshly
				// downloaded extra code and runs everything in-process — no inner
				// HTTP sub-requests, no nginx proxy timeouts for connection 1.
				var nonce = $('input[name="upgrade_nonce"]').val();
				$.ajax(BASE_URL + '/extras/upgrade/', {
					method: 'POST',
					data: {full_upgrade: 1, upgrade_nonce: nonce},
					xhrFields: { onprogress: makeProgressHandler() },
					error: function(xhr, status, error) {
						if (!retryCSRF(xhr, this)) {
							if (xhr.responseJSON && xhr.responseJSON.status_message) {
								showAlertsFromArray(xhr.responseJSON.status_message);
							}
						}
					}
				})
				.fail(function(xhr, status, errorMessage) {
					progress_window.append('<div style="color: orangered"><?= $lang['UpdateFailed'] ?></div>');
					progress_window.append('<div style="color: orangered">' + status + ' (' + errorMessage + ')</div>');
					progress_window.animate({ scrollTop: 9999 });
				});
			})
			.fail(function(xhr, status, errorMessage) {
				if (retryCSRFCount > 5) {
					progress_window.append('<div style="color: orangered"><?= $lang['UpdateFailed'] ?></div>');
					progress_window.append('<div style="color: orangered">' + status + ' (' + errorMessage + ')</div>');
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