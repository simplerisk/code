<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/bootstrap.php'));
    require_once(realpath(__DIR__ . '/../includes/upgrade.php'));
    // The job record. Declared here rather than relied on through upgrade.php,
    // per CLAUDE.md's function-reachability rule.
    require_once(realpath(__DIR__ . '/../includes/upgrade/job.php'));
    require_once(realpath(__DIR__ . '/../includes/upgrade/page.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION)) {
        // Start the session
        $parameters = [
            "lifetime" => 0,
            "path" => "/",
            "domain" => "",
            "secure" => isset($_SERVER["HTTPS"]),
            "httponly" => true,
            "samesite" => "Strict",
        ];
        session_set_cookie_params($parameters);

        session_name('SimpleRiskDBUpgrade');
        session_start();
    }

    // Include the language file
    require_once(language_file());
    
    // Set a global variable for the current app version, so we don't have to call a function every time
    $current_app_version = current_version("app");

    csrf_init();

    // Check for session timeout or renegotiation
    session_check();

    // ── The progress endpoints ─────────────────────────────────────────────
    //
    // This page used to run the whole upgrade inline and stream its output into
    // the response. Whether an operator saw any of that depended entirely on
    // the server: Apache's mod_deflate buffers text/html by default, nginx
    // buffers proxied responses, FastCGI buffers too, and none of it is
    // reliably defeatable from PHP. On a large database the page went blank for
    // exactly the part that takes longest, which reads as a hung upgrade.
    //
    // Now the schema upgrade is a job the page polls, the same shape the
    // one-click flow uses. Every poll response is small and complete, so no
    // buffer can hide it, and the run survives a closed tab.
    //
    // DELIBERATELY SCHEMA ONLY. This page has no orchestrator: it does not
    // download an Extra, take a backup, fetch a bundle or replace any files,
    // and it should not start now. It runs the release chain and the post-chain
    // conversions, which is what an operator who has already swapped the files
    // by hand needs it to do.
    if (isset($_GET['upgrade_status']))
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!isset($_SESSION['admin']) || $_SESSION['admin'] != "1") {
            http_response_code(403);
            echo json_encode(array('state' => 'forbidden'));
            exit(0);
        }

        // Release the session row lock before reading the record. These polls
        // fire every two seconds and SimpleRiskSessionHandler::read() holds
        // SELECT ... FOR UPDATE on the session row for the life of the request,
        // so without this they serialise against each other and against the
        // upgrade. The Extra's twin endpoint does the same, for the same reason.
        session_write_close();

        $job = upgrade_job_read();

        if ($job === null) {
            echo json_encode(array('state' => 'none'));
            exit(0);
        }

        // A run whose process died would otherwise leave the screen polling a
        // job that claims to be running for ever.
        if (upgrade_job_is_stale($job)) {
            $job['state']   = 'failed';
            $job['message'] = $lang['UpgradeJobStalled'] ?? 'The upgrade stopped responding. Check the server log before retrying.';
        }

        echo json_encode($job);
        exit(0);
    }

    // If the user requested a logout
    if (isset($_GET['logout']) && $_GET['logout'] == "true") {
        // Log the user out
        upgrade_logout();
    }

    // If the login form was posted
    if (isset($_POST['submit'])) {
        $user = $_POST['user'];
        $pass = $_POST['pass'];

        // INTENTIONAL DESIGN: This page uses a simplified authentication path (upgrade mode) that
        // differs from the standard login flow in the following ways:
        //
        // 1. LOCKOUT IS NOT ENFORCED: is_valid_user(..., true) and get_user_type(..., true) omit
        //    the lockout = 0 predicate. This is deliberate. A schema migration may alter the user
        //    table in a way that breaks normal login before the upgrade completes. If an admin's
        //    account were locked at that moment, they would have no way to recover the instance.
        //
        // 2. MFA IS NOT ENFORCED: The upgrade entrypoint does not call the standard login()
        //    pipeline and therefore does not trigger MFA verification. This is accepted for the
        //    same reason — MFA infrastructure may be unavailable or broken mid-upgrade.
        //
        // 3. FORCED PASSWORD CHANGE IS NOT ENFORCED: Accounts flagged for change_password are not
        //    redirected to reset_password.php. Completing a password change is not a meaningful
        //    prerequisite for running a database upgrade.
        //
        // 4. FAILED ATTEMPTS ARE NOT RECORDED: add_login_attempt_and_block() is not called on
        //    failure because the failed_login_attempts table and user table may be mid-migration
        //    and in an inconsistent state.
        //
        // This page uses a separate session name (SimpleRiskDBUpgrade) and is not linked from
        // anywhere in the normal application UI. It is only meaningful during a version upgrade.
        // These limitations are accepted tradeoffs for an emergency-recovery entrypoint.

        // If the user is valid
        if (is_valid_user($user, $pass, true)) {
            // Set the user permissions
            set_user_permissions($user, true);

            // Check if the user is an admin
            if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1") {
                // Grant access
                $_SESSION["access"] = "1";

            // The user is not an admin
            } else {
                // Display an alert
                set_alert(true, "bad", "You need to log in as an administrative user in order to upgrade the database.");
                // Deny access
                $_SESSION["access"] = "denied";
            }

        // The user was not valid
        } else {
            // If case sensitive usernames are enabled
            if (get_setting("strict_user_validation") != 0) {
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang["InvalidUsernameOrPasswordCaseSensitive"]));
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($lang["InvalidUsernameOrPassword"]));
            }
            
            // Deny access
            $_SESSION["access"] = "denied";
        }
    }
    // If an API key is set and is valid
    if (isset($_GET['key']) && check_valid_key($_GET['key'])) {
        // Grant access
        $_SESSION["access"] = "1";
        // API key is admin
        $_SESSION["admin"] = "1";
    }
?>
<!DOCTYPE html>
<html dir="ltr" lang="en" xml:lang="en">
    <head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
          
        <!-- Favicon icon -->
        <?php setup_favicon("..");?>
        
        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="../css/style.min.css?<?= $current_app_version ?>" />

        <!-- jQuery CSS -->
        <link rel="stylesheet" href="../vendor/node_modules/jquery-ui/dist/themes/base/jquery-ui.min.css?<?= $current_app_version ?>">

        <!-- extra css -->

        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

        <!-- jQuery Javascript -->
        <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>
        <script src="../vendor/node_modules/jquery-ui/dist/jquery-ui.min.js?<?= $current_app_version ?>" id="script_jqueryui"></script>

        <!-- Bootstrap tether Core JavaScript -->
        <script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>

    </head>

    <body>
        <div class="preloader">
            <div class="lds-ripple">
                <div class="lds-pos"></div>
                <div class="lds-pos"></div>
            </div>
        </div>
        <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="none" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full" data-function="login">
            <header class="topbar" data-navbarbg="skin5">
                <nav class="navbar top-navbar navbar-expand-md navbar-dark justify-content-between">
                    <div class="navbar-header">
<?php
    // SAFE AGAINST A NOT-YET-UPGRADED DATABASE, which matters more here than
    // on any other caller: this page exists to run against one.
    //
    // On a release predating the branding work the `custom_logo` setting does
    // not exist, get_setting() returns false, and the SimpleRisk mark renders.
    // Because the <img> is then never emitted, custom_logo.php -- which reads
    // a table that release also lacks -- is never requested.
    display_brand_logo('../');
?>
                    </div>
                    <div class="navbar-content">
                        <ul class="nav"> 
                            <li>
                                <a class="text-white" href="upgrade.php">Database Upgrade Script</a>
                            </li>
                            <li>
                                <a class="text-white mx-4" href="upgrade.php?logout=true"><i class="fa fa-power-off m-r-5"></i>Logout</a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </header>
            <!-- ============================================================== -->
            <!-- Page wrapper  -->
            <div class="page-wrapper">
                <div class="scroll-content">
                    <div class="content-wrapper">
                        <!-- container - It's the direct container of all the -->
                        <div class="content container-fluid">
                            <div class="container login-form">
                                <div class="row">
                                    <div class="col-md-3"></div>
                                    <div class="col-md-6">

<?php
    // If access was not granted display the login form
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1") {
    
                                        // Display the login form
                                        display_login_form();
    
    // Otherwise access was granted so check if the user is an admin
    } else if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1") {

        // If CONTINUE was not pressed
        if (!isset($_POST['upgrade_database'])) {
            
                                        // Display the upgrade information
                                        display_upgrade_info();

        // Otherwise, CONTINUE was pressed
        } else {

                                                // Start the job, detach, and let the page poll it.
                                                start_database_upgrade_job();
        }
    }
?>
                                    </div>
                                    <div class="col-md-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
        // Get any alert messages
        get_alert();
        setup_alert_requirements("..");
?>
    	<script>
        	$(function() {
        		// Fading out the preloader once everything is done rendering
        		$(".preloader").fadeOut();
            });

		// ── The progress poller ─────────────────────────────────────────
		//
		// Only runs when the card is on the page, i.e. after Continue was
		// pressed. Deliberately a poller and not a stream: a stream can be
		// swallowed whole by mod_deflate, nginx proxy buffering or FastCGI,
		// none of which a customer can be asked to reconfigure before they
		// are allowed to upgrade. Small complete responses cannot be hidden
		// by a buffer.
		//
		// The same shape and the same markup as the one-click screen in
		// admin/register.php, so an operator who has seen one recognises the
		// other. Deliberately NOT shared code: this page runs mid-upgrade on
		// a Core whose files may have just been replaced, and a shared asset
		// is one more thing that has to be in step.
		$(function() {
			var $card = $('#db-upgrade-card');
			if (!$card.length) { return; }

			var expandedSteps = {};
			var failures = 0;
			var waitedForJob = 0;

			$(document).on('click', '.sr-upgrade-toggle', function() {
				var key = $(this).data('step');
				expandedSteps[key] = !expandedSteps[key];
				if (lastJob) { renderJob(lastJob); }
			});

			var lastJob = null;

			function renderJob(job) {
				lastJob = job;
				var steps = job.steps || [];
				var statements = job.statements || [];

				var byStep = {};
				$.each(statements, function(i, st) {
					var key = (st && st.step) ? st.step : '';
					(byStep[key] = byStep[key] || []).push(st.text);
				});

				var $list = $('#db-upgrade-steps').empty();

				$.each(steps, function(i, step) {
					var state = step.state || 'todo';
					var $row = $('<li>').addClass('sr-upgrade-step is-' + state);

					// A second, non-colour signal, so a done step reads as done
					// in grayscale too.
					var $mark = $('<span>').addClass('sr-upgrade-mark');
					if (state === 'done') {
						$mark.text('\u2713');
					} else if (state === 'running') {
						$mark.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
					} else if (state === 'skipped') {
						// A dash, not an empty circle: the step is settled, it
						// just had nothing to do.
						$mark.text('\u2013');
					} else {
						$mark.text('\u25cb');
					}

					var $body = $('<div>').addClass('sr-upgrade-body')
						.append($('<div>').addClass('sr-upgrade-label').text(step.label || ''));

					if (step.detail) {
						$body.append($('<div>').addClass('sr-upgrade-detail').text(step.detail));
					}

					var lines = byStep[step.key] || [];
					if (lines.length) {
						var $ul = $('<ul>').addClass('sr-upgrade-statements');
						$.each(lines, function(j, line) { $ul.append($('<li>').text(line)); });

						if (state === 'running') {
							$body.append($ul);
							setTimeout(function() { $ul.scrollTop($ul[0].scrollHeight); }, 0);
						} else {
							var open = expandedSteps[step.key] === true;
							$body.append(
								$('<button>').attr('type', 'button')
									.addClass('sr-upgrade-toggle')
									.attr('data-step', step.key)
									.text(open ? '<?php echo $escaper->escapeJs($lang['UpgradeHideWhatItDid'] ?? 'Hide what it did'); ?>'
									           : '<?php echo $escaper->escapeJs($lang['UpgradeShowWhatItDid'] ?? 'Show what it did'); ?>')
							);
							if (open) { $body.append($ul); }
						}
					}

					$row.append($mark).append($body);
					if (step.meta) {
						$row.append($('<span>').addClass('sr-upgrade-meta').text(step.meta));
					}
					$list.append($row);
				});

				var done = $.grep(steps, function(s) { return s.state === 'done' || s.state === 'skipped'; }).length;
				$('#db-upgrade-counts').text(done + ' / ' + steps.length);

				// removeClass() with no argument strips everything, so the
				// base class has to go back on with the state class -- the
				// .sr-state-* families style the pill, .sr-state-pill gives it
				// its shape.
				var $pill = $('#db-upgrade-state').removeClass();
				if (job.state === 'running') {
					$pill.addClass('sr-state-pill sr-state-info').text('<?php echo $escaper->escapeJs($lang['UpgradeStateRunning'] ?? 'Running'); ?>');
				} else if (job.state === 'done') {
					$pill.addClass('sr-state-pill sr-state-success').text('<?php echo $escaper->escapeJs($lang['Complete']); ?>');
				} else {
					$pill.addClass('sr-state-pill sr-state-danger').text('<?php echo $escaper->escapeJs($lang['Failed']); ?>');
				}

				// The conclusion in words. A run that changed nothing needs this
				// as much as a failure does -- ticks over a row of dashes do not
				// say "you were already current".
				if (job.message) {
					$('#db-upgrade-outcome')
						.toggleClass('is-failure', job.state !== 'done')
						.text(job.message)
						.show();
				}
			}

			function poll() {
				$.getJSON('upgrade.php?upgrade_status=1')
					.done(function(job) {
						failures = 0;
						// Bounded, like admin/register.php. An unbounded retry here
						// is the spinner-that-never-resolves this whole change
						// exists to remove: if the job record was never written,
						// the page would poll every two seconds for ever.
						if (!job || job.state === 'none') {
							if (++waitedForJob > 30) {
								$('#db-upgrade-outcome').addClass('is-failure')
									.text('<?php echo $escaper->escapeJs($lang['UpgradeJobUnwritable'] ?? 'The upgrade could not start because its progress record could not be written.'); ?>')
									.show();
								return;
							}
							setTimeout(poll, 2000); return;
						}
						renderJob(job);
						// Keep polling only while there is something to watch.
						if (job.state === 'running') { setTimeout(poll, 2000); }
					})
					.fail(function(xhr) {
						// A 403 is an answer, not a hiccup: the session went
						// away. Saying so beats a spinner that never resolves.
						if (xhr && xhr.status === 403) {
							$('#db-upgrade-outcome').addClass('is-failure')
								.text('<?php echo $escaper->escapeJs($lang['UpgradeSessionExpired'] ?? 'Your session expired. Sign in again to see the upgrade.'); ?>')
								.show();
							return;
						}
						// Anything else is treated as transient -- the web
						// server is genuinely restarting during some upgrades --
						// but not for ever.
						if (++failures > 30) {
							$('#db-upgrade-outcome').addClass('is-failure')
								.text('<?php echo $escaper->escapeJs($lang['UpgradeLostContact'] ?? 'Lost contact with the server. Reload this page to reattach to the upgrade.'); ?>')
								.show();
							return;
						}
						setTimeout(poll, 3000);
					});
			}

			poll();
		});
    	</script>
    </body>
</html>
<?php
    // The page is fully emitted -- card, poller and all. NOW close the response
    // and run the upgrade with nobody listening.
    //
    // A no-op unless CONTINUE was pressed and the job was claimed. Deliberately
    // last: detaching any earlier discards everything after it, which is what
    // left the progress card on screen with no JavaScript attached to it.
    finish_database_upgrade_page();
?>