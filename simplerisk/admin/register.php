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
		<!-- Where the upgrade progress card is moved when an upgrade starts. It is
		     emitted by the Upgrade Extra inside the half-width upgrade panel,
		     which is the right place for the button but far too narrow for a
		     step list -- and that panel's height is pinned to the registration
		     panel beside it, so a taller card overflowed it and spilled over the
		     section below. -->
		<div class="row" id="upgrade-progress-host"></div>
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
	var upgrade_card = $('#upgrade-progress');

	// Which finished steps the operator has expanded. Held outside renderJob()
	// because the whole card is redrawn on every poll, and a panel that closed
	// itself every two seconds would be unusable.
	var expandedSteps = {};

	$(document).on('click', '.sr-upgrade-toggle', function() {
		var key = $(this).data('step');
		expandedSteps[key] = !expandedSteps[key];
		if (lastJob) { renderJob(lastJob); }
	});

	// The most recent job, so a toggle can re-render without waiting for a poll.
	var lastJob = null;

	// Phase 1 -- self-updating the extra -- happens before the job exists, so
	// it has no step in the record to report against. It is rendered as a
	// synthetic first step so the screen reads as one sequence rather than
	// starting blank and jumping once phase 2 begins.
	//
	// Its streamed output is deliberately not displayed. It was only ever
	// readable when nothing buffered it, and the step's own state says the one
	// thing an operator needs from it.
	function renderExtraStep(state) {
		renderJob({
			state: 'running',
			steps: [{
				key: 'extra',
				label: '<?= $escaper->escapeJs($lang['UpgradeStepUpdatingUpgradeExtra'] ?? 'Updating the Upgrade Extra') ?>',
				state: state,
				detail: '',
				meta: ''
			}],
			statements: []
		});
	}

	// A whole re-render each poll. Five steps and a capped statement list are
	// cheap to redraw, and a full render cannot drift out of step with the job
	// the way incremental patching does.
	function renderJob(job) {
		lastJob = job;
		var steps = job.steps || [];
		var statements = job.statements || [];

		// Statements grouped by the step that produced them, so each one appears
		// under the work that emitted it rather than in a separate log.
		var byStep = {};
		$.each(statements, function(i, st) {
			var key = (st && st.step) ? st.step : '';
			(byStep[key] = byStep[key] || []).push(st.text);
		});

		var $list = $('#upgrade-progress-steps').empty();

		$.each(steps, function(i, step) {
			var state = step.state || 'todo';
			var $row = $('<li>').addClass('sr-upgrade-step is-' + state);

			// The mark is a second, non-colour signal: a done step reads as done
			// in grayscale too.
			var $mark = $('<span>').addClass('sr-upgrade-mark');
			if (state === 'done') {
				$mark.text('\u2713');
			} else if (state === 'running') {
				$mark.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
			} else if (state === 'skipped') {
				// A dash, not an empty circle: the step is settled, it just had
				// nothing to do. An unticked circle at the end of a finished run
				// reads as an upgrade that stalled.
				$mark.text('\u2013');
			} else {
				$mark.text('\u25cb');
			}

			var $body = $('<div>').addClass('sr-upgrade-body')
				.append($('<div>').addClass('sr-upgrade-label').text(step.label || ''));

			if (step.detail) {
				$body.append($('<div>').addClass('sr-upgrade-detail').text(step.detail));
			}

			// Only the running step shows its statements. A finished step's
			// output is in the log; keeping it on screen would bury the part an
			// operator is actually watching.
			var lines = byStep[step.key] || [];
			if (lines.length) {
				var $ul = $('<ul>').addClass('sr-upgrade-statements');
				$.each(lines, function(j, line) { $ul.append($('<li>').text(line)); });

				if (state === 'running') {
					// The running step shows its output as it arrives -- that is
					// what tells an operator the upgrade is alive.
					$body.append($ul);
					// Follow the tail without yanking the page around.
					setTimeout(function() { $ul.scrollTop($ul[0].scrollHeight); }, 0);
				} else {
					// A finished step keeps its output but folds it away. Left
					// open, several completed steps would bury whatever is
					// running; hidden entirely, there is no way to answer "what
					// did that actually do".
					var open = expandedSteps[step.key] === true;
					$body.append(
						$('<button>').attr('type', 'button')
							.addClass('sr-upgrade-toggle')
							.attr('aria-expanded', open ? 'true' : 'false')
							.data('step', step.key)
							.text(
								(open ? '\u25be ' : '\u25b8 ')
								+ '<?= $escaper->escapeJs($lang['UpgradeShowWhatItDid'] ?? 'Show what it did') ?>'
								+ ' (' + lines.length + ')'
							)
					);
					if (open) { $body.append($ul); }
				}
			}

			$row.append($mark).append($body)
				.append($('<div>').addClass('sr-upgrade-meta').text(step.meta || ''));

			$list.append($row);
		});

		// Run state, as a soft state pill from the shipped families.
		var $pill = $('#upgrade-progress-state').removeClass('sr-state-info sr-state-success sr-state-danger');
		if (job.state === 'done') {
			$pill.addClass('sr-state-success').text('<?= $escaper->escapeJs($lang['Complete']) ?>');
			$('#upgrade-progress-reattach').hide();
		} else if (job.state === 'failed' || job.state === 'refused') {
			$pill.addClass('sr-state-danger').text('<?= $escaper->escapeJs($lang['Failed']) ?>');
			$('#upgrade-progress-reattach').hide();
		} else {
			$pill.addClass('sr-state-info').text('<?= $escaper->escapeJs($lang['UpgradeInProgress'] ?? 'In progress') ?>');
			$('#upgrade-progress-reattach').show();
		}

		// The heading names the destination, which is the one thing an operator
		// wants confirmed before a long-running change to their instance.
		if (job.to_version) {
			$('#upgrade-progress-title').text(
				'<?= $escaper->escapeJs($lang['UpgradingTo'] ?? 'Upgrading to {version}') ?>'.replace('{version}', job.to_version)
			);
		}

		var started = job.started ? new Date(job.started * 1000).toLocaleTimeString() : '';
		$('#upgrade-progress-counts').text(
			'<?= $escaper->escapeJs($lang['UpgradeStatementsApplied'] ?? 'Started {started} · {count} statements applied') ?>'
				.replace('{started}', started)
				.replace('{count}', statements.length)
		);

		// The outcome, in words. A failure has to say why rather than just go
		// red -- and a successful run that changed nothing has to say THAT,
		// because a few ticks over a row of dashes does not explain itself.
		var $outcome = $('#upgrade-progress-outcome');
		if (job.message) {
			$outcome.text(job.message)
				.toggleClass('is-failure', job.state === 'failed' || job.state === 'refused')
				.show();
		} else {
			$outcome.hide();
		}
	}

	// The log download is the full transcript for a support case -- the
	// statements an operator needs are already on screen under their step.
	$(document).on('click', '#upgrade-download-log', function() {
		$.getJSON(BASE_URL + '/extras/upgrade/?upgrade_status=1').done(function(job) {
			var lines = $.map(job.statements || [], function(st) {
				return (st.step ? '[' + st.step + '] ' : '') + st.text;
			});
			var blob = new Blob([lines.join('\n')], {type: 'text/plain'});
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob);
			a.download = 'simplerisk-upgrade-' + (job.id || 'log') + '.txt';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			URL.revokeObjectURL(a.href);
		});
	});

	// Poll the job rather than read a streamed response.
	//
	// A streamed upgrade only reached the operator when nothing between PHP and
	// the browser buffered it, and Apache's mod_deflate buffers text/html by
	// default. Each poll is a small complete response, so no buffer can hide it,
	// and progress survives a reloaded or reopened tab.
	// The id of the job on disk before this click, so a finished record left by
	// an EARLIER upgrade is not mistaken for this one -- the poller starts before
	// the new job exists, and would otherwise immediately report that previous
	// run's result.
	var priorJobId = null;
	var waitedForJob = 0;
	var failedPolls = 0;

	function upgradeFailed(message) {
		renderJob({state: 'failed', message: message, steps: [], statements: []});
	}

	function pollUpgradeJob() {
		$.getJSON(BASE_URL + '/extras/upgrade/?upgrade_status=1')
			.done(function(job) {
				failedPolls = 0;
				var isThisRun = job && job.state !== 'none' && job.id !== priorJobId;

				if (!isThisRun) {
					// Bounded: an upgrade that never registers a job is a failure
					// to start, not something to poll for indefinitely.
					if (++waitedForJob > 30) {
						upgradeFailed('<?= $escaper->escapeJs($lang['UpdateFailed']) ?>');
						return;
					}
					setTimeout(pollUpgradeJob, 2000);
					return;
				}

				renderJob(job);

				if (job.state === 'running') {
					setTimeout(pollUpgradeJob, 2000);
				}
			})
			.fail(function(xhr) {
				// A restarting web server during the file swap makes a poll fail;
				// that is expected mid-upgrade, so keep polling. A refusal is not
				// transient and is reported.
				if (xhr.status === 403) {
					upgradeFailed('<?= $escaper->escapeJs($lang['UpdateFailed']) ?>');
					return;
				}

				// Bounded, like the "job never appeared" branch above. A poll
				// that keeps failing for a NON-transient reason -- a broken
				// server config, a fatal on every request, a proxy 502 that
				// never recovers -- would otherwise retry for ever and leave the
				// operator watching a spinner that can never resolve.
				if (++failedPolls > 15) {
					upgradeFailed('<?= $escaper->escapeJs($lang['UpgradeStatusUnreachable'] ?? 'Lost contact with the server while the upgrade was running. Check the SimpleRisk log; the upgrade may still be in progress.') ?>');
					return;
				}
				setTimeout(pollUpgradeJob, 4000);
			});
	}

	$(document).ready(function(){
		$('#app_upgrade').click(function() {
			$('#upgrade-progress-steps').empty();
			$('#upgrade-progress-counts').text('');

			// Move the card out of the narrow upgrade panel and into its own
			// full-width row. Guarded on the host existing: this same extra runs
			// against older cores whose register.php has no such row, and there
			// the card simply stays where it was rendered.
			var host = $('#upgrade-progress-host');
			if (host.length && !host.find('#upgrade-progress').length) {
				upgrade_card.appendTo($('<div class="col-12"></div>').appendTo(host));
			}

			// The panel's height is pinned to the registration panel beside it,
			// which is fine for a button and wrong for a growing step list.
			$('#upgrade-panel .hero-unit').css('height', '');

			upgrade_card.show();
			renderExtraStep('running');

			// The card sits below the two panels, so on most screens starting an
			// upgrade otherwise looks like nothing happened.
			if (upgrade_card[0] && upgrade_card[0].scrollIntoView) {
				upgrade_card[0].scrollIntoView({behavior: 'smooth', block: 'start'});
			}
			waitedForJob = 0;
			failedPolls = 0;
			priorJobId = null;
			$.getJSON(BASE_URL + '/extras/upgrade/?upgrade_status=1')
				.done(function(job) { if (job && job.id) { priorJobId = job.id; } });

			// Phase 1: ask the core API to update the upgrade extra only.
			// After this the new extra code is on disk; the next HTTP request
			// will load it — eliminating any dependency on this core version's
			// call_extra_api_functionality for the long-running backup/upgrade steps.
			$.ajax(BASE_URL + '/api/v2/one_click_upgrade', {
				method: 'POST',
				data: {data: 1, step: 'update_extra'},
				error: function(xhr, status, error) {
					if (!retryCSRF(xhr, this)) {
						if (xhr.responseJSON && xhr.responseJSON.status_message) {
							showAlertsFromArray(xhr.responseJSON.status_message);
						}
					}
				}
			})
			.done(function() {
				renderExtraStep('done');

				// Phase 2: run backup and upgrade directly inside the upgrade extra.
				// This POST goes to extras/upgrade/index.php which loads the freshly
				// downloaded extra code and runs everything in-process — no inner
				// HTTP sub-requests, no nginx proxy timeouts for connection 1.
				var nonce = $('input[name="upgrade_nonce"]').val();
				//
				// async: 1 asks the extra to start the upgrade, answer at once,
				// and let this page poll it. Without that flag the extra streams
				// instead -- which is what an older core's copy of this page
				// does, and why that flag exists rather than a version check.
				var start = $.ajax(BASE_URL + '/extras/upgrade/', {
					method: 'POST',
					dataType: 'json',
					data: {full_upgrade: 1, async: 1, upgrade_nonce: nonce},
					error: function(xhr, status, error) {
						if (!retryCSRF(xhr, this)) {
							if (xhr.responseJSON && xhr.responseJSON.status_message) {
								showAlertsFromArray(xhr.responseJSON.status_message);
							}
						}
					}
				});

				// Poll from here rather than from the start request's callback.
				//
				// The extra closes that response before doing the work, but
				// whether the BROWSER sees it close is not something we control:
				// a server with zlib.output_compression on rewrites the length we
				// declared, and the connection then stays open for the whole
				// upgrade. Polling on its own timer means progress appears either
				// way, which is the point -- this has to work without asking a
				// customer to change their server configuration.
				pollUpgradeJob();

				// The server's own answer, read rather than discarded.
				//
				// A lock-contended claim replies 200 {state:'refused',reason:...}
				// and an unwritable record replies 500 {state:'failed',message:...}.
				// Neither writes a job record, so with no handler here the poller
				// saw {state:'none'} for sixty seconds and then rendered a generic
				// "Update Failed" -- burying a precise reason the server had
				// already computed, on the recovery surface.
				start.done(function(job) {
					if (job && job.state === 'refused') {
						upgradeFailed(job.reason || '<?php echo $escaper->escapeJs($lang['UpgradeAlreadyRunning'] ?? 'An upgrade is already running on this instance.'); ?>');
					}
				});

				start.fail(function(xhr, status, errorMessage) {
					// Only a start that failed AND left no job behind is a real
					// failure; otherwise the poller already has it.
					$.getJSON(BASE_URL + '/extras/upgrade/?upgrade_status=1')
						.done(function(job) {
							if (job && job.state !== 'none') { return; }
							// The server's message beats "error (Internal Server
							// Error)" whenever it sent one.
							var body = xhr.responseJSON;
							if (body && (body.message || body.reason)) {
								upgradeFailed(body.message || body.reason);
								return;
							}
							upgradeFailed(status + ' (' + errorMessage + ')');
						});
				});
			})
			.fail(function(xhr, status, errorMessage) {
				if (retryCSRFCount > 5) {
					upgradeFailed(status + ' (' + errorMessage + ')');
				}
			});

		});
	});
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>