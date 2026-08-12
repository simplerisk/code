<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/********************************************************************
 * THE DATABASE UPGRADE PAGE'S RUNNER                               *
 *                                                                  *
 * admin/upgrade.php's Continue button, as functions rather than as *
 * code inline in the page.                                         *
 *                                                                  *
 * Separate file because the page itself is not includable: its     *
 * top-level code authenticates, handles a login POST and emits a   *
 * whole HTML document, so anything wanting these functions -- a    *
 * test, the validation harness -- would have to run all of that    *
 * first. CLAUDE.md's rule for exactly this shape is to lift the    *
 * logic out of the side-effect wrapper and leave the wrapper thin. *
 *                                                                  *
 * SCHEMA ONLY, and deliberately. This page has no orchestrator: it *
 * downloads no Extra, takes no backup, fetches no bundle and       *
 * replaces no files. It runs the release chain and the post-chain  *
 * conversions, which is what an operator who has already swapped   *
 * the files by hand needs.                                         *
 ********************************************************************/

// Its own requires, per CLAUDE.md's function-reachability rule. Every function
// here calls into job.php (upgrade_job_claim/start/read/step/finish/...) and
// upgrade.php (upgrade_database), and this file reaches them today only because
// admin/upgrade.php happens to require both first. A reorder there, or any
// second consumer, would turn that into a fatal "Call to undefined function"
// mid-upgrade -- on the recovery page, with the shutdown guard not yet
// registered, so the job record would be left saying "running" for ever.
// require_once makes the duplicate include from admin/upgrade.php a no-op.
require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../upgrade.php'));
require_once(realpath(__DIR__ . '/job.php'));

/**
 * Start the schema upgrade as a job, render the card, and run it detached.
 *
 * Three things have to happen in this order, and the order is the whole design:
 *
 *   1. Claim the job. Atomically, so two operators pressing Continue at the
 *      same moment cannot both start a chain. The claim also refuses when the
 *      Upgrade Extra's one-click flow is already running -- they share one
 *      record and one lock precisely so they cannot collide.
 *   2. Send the page, complete, and close the connection. The browser needs the
 *      progress card in front of it BEFORE the work starts, or there is nothing
 *      to watch.
 *   3. Do the work with nobody listening, writing progress into the record.
 */
function start_database_upgrade_job()
{
    global $escaper, $lang;


    // Schema only. No Extra, no backup, no bundle, no files -- see the comment
    // on the status endpoint above.
    $steps = array(
        'versions' => $lang['UpgradeStepCheckingVersions'] ?? 'Checking versions',
        'schema'   => $lang['UpgradeStepUpgradingDatabase'] ?? 'Upgrading the database schema',
        'converge' => $lang['UpgradeStepFinalising'] ?? 'Applying the post-upgrade conversions',
    );

    $from_version = current_version("db");

    $claim_reason = 'started';
    $claimed = upgrade_job_claim(function () use ($from_version, $steps) {
        return upgrade_job_start($from_version, $steps);
    }, $claim_reason);

    // upgrade_job_start() returns false when the record could not be written,
    // and the claim returns false when one is already in flight. Both mean "do
    // not run", and both must SAY so -- a blank card that never progresses is
    // how an operator concludes the upgrade is broken.
    if ($claimed === false) {
        // The reason comes from the claim itself. Inferring it from the record
        // told an operator whose click lost a race with the management channel
        // -- which holds this lock for a whole chain and writes no record --
        // that their temp directory was unwritable.
        $running = upgrade_job_read();
        $already = ($claim_reason === 'lock_contended' || $claim_reason === 'already_running')
            || (is_array($running)
                && ($running['state'] ?? '') === 'running'
                && !upgrade_job_is_stale($running));

        echo "<div class='sr-qform sr-upgrade'><div class='sr-qcard'><div class='sr-qcard-body'>";
        echo "<p class='sr-upgrade-outcome is-failure'>" . $escaper->escapeHtml(
            $already
                ? ($lang['UpgradeAlreadyRunning'] ?? 'An upgrade is already running on this instance.')
                : ($lang['UpgradeJobUnwritable'] ?? 'The upgrade could not start because its progress record could not be written.')
        ) . "</p>";
        echo "</div></div></div>";
        return;
    }

    // The card the poller fills in. Rendered empty on purpose: the very first
    // poll paints the steps, so there is exactly one renderer rather than one
    // here and another in JavaScript that can disagree with it.
    render_database_upgrade_card();

    // Do NOT detach here. The card is only half the page: the poller that fills
    // it in is emitted further down the document, and closing the response at
    // this point would discard it -- leaving the operator an empty card with no
    // JavaScript attached, which never updates. That is the exact failure this
    // whole change exists to remove, so the detach waits until the page has
    // finished rendering. See finish_database_upgrade_page().
    database_upgrade_job_was_claimed(true);
}

/**
 * Close the response and do the work. Called from the very BOTTOM of
 * admin/upgrade.php, after every byte of the page -- including the poller --
 * has been emitted.
 */
function finish_database_upgrade_page()
{
    if (!database_upgrade_job_was_claimed()) {
        return;
    }

    // fastcgi_finish_request() is the clean way and exists under PHP-FPM. Under
    // mod_php it does not, so fall back to closing the connection by hand:
    // Content-Length plus a flush is what tells the client the response is
    // complete, and ignore_user_abort() keeps the script alive once it is.
    //
    // Either way the work below runs with nobody listening, which is what makes
    // the progress card a viewer rather than the process.
    ignore_user_abort(true);
    session_write_close();

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        // Only when a buffer is actually active. admin/upgrade.php starts none,
        // so with output_buffering=Off ob_get_length() returns false and this
        // would emit an empty Content-Length plus an ob_end_flush() warning
        // into the page; with the php.ini-production default of 4096 the head
        // has already auto-flushed, so the length would describe a fraction of
        // what was sent and truncate the document. Without a buffer there is
        // nothing to close by hand -- ignore_user_abort() plus a flush is the
        // whole of what we can do, and the client already has the full page.
        if (ob_get_level() > 0) {
            header('Connection: close');
            header('Content-Length: ' . ob_get_length());
            ob_end_flush();
        }
        @ob_flush();
        flush();
    }

    run_database_upgrade_job();
}

/** Did this request claim a job? Reads the flag start_...() sets. */
function database_upgrade_job_was_claimed($set = null)
{
    static $claimed = false;
    if ($set !== null) { $claimed = (bool)$set; }
    return $claimed;
}

/**
 * The work itself. Nothing here writes to the response -- it is already closed.
 */
function run_database_upgrade_job()
{
    global $lang;

    // A fatal, an exit() inside a helper, or an uncaught throwable would
    // otherwise leave the record saying 'running' for ever and the operator
    // watching a screen that never resolves. The guard closes it on every one
    // of those paths; it is a no-op once the job is already terminal.
    upgrade_job_guard_shutdown();

    upgrade_job_step('versions');
    $from_version = current_version("db");
    $app_version  = current_version("app");
    upgrade_job_detail($from_version . ' to ' . $app_version);

    if ($from_version === $app_version) {
        // Not "nothing happened": the chain still runs, because a release under
        // development has an upgrade function for the CURRENT version and
        // running it is how in-flight schema changes get applied.
        upgrade_job_detail($lang['UpgradeDatabaseAlreadyCurrent'] ?? 'The database is already on the application version.');
    }

    upgrade_job_step('schema');

    // The release functions narrate themselves with echo. Capturing that turns
    // each line into a statement under the running step, which is what the card
    // shows -- and is why none of the 98 upgrade functions had to be touched.
    // ob_start(callback, 1) fires the callback per output call, so each echo
    // becomes its own statement. Closed explicitly rather than left to the end
    // of the request: the steps below must not have their own output swallowed
    // into the schema step's statement list.
    $capture_depth = ob_get_level();
    upgrade_job_capture_output();
    $ran = null;
    try {
        // The return value, not a version comparison. upgrade_database()
        // refuses when another channel holds the lock, and on a database that
        // is already at the app version a refusal and a completed run produce
        // identical versions -- so inferring the outcome told the operator "the
        // database upgrade completed" for a run in which nothing happened.
        $ran = upgrade_database();
    } finally {
        // Bounded rather than while-true, so an upgrade function that opened a
        // buffer and never closed it cannot unwind past this one.
        $guard = 0;
        while (ob_get_level() > $capture_depth && $guard++ < 32) {
            ob_end_flush();
        }
    }

    upgrade_job_step('converge');

    // upgrade_database() runs the post-chain conversions and the standing
    // integrity checks itself, once, after the chain returns. This step exists
    // to SAY so rather than to repeat them: a step that silently does nothing
    // reads as a step that failed.
    $reached = current_version("db");

    if ($ran === false) {
        upgrade_job_finish(
            'failed',
            $lang['UpgradeAlreadyRunning'] ?? 'An upgrade is already running on this instance.',
            $reached
        );
        return;
    }

    // The same predicate the other two drivers use -- upgrade_database()'s
    // post-chain gate and the Extra's arrival check both accept "already on the
    // newest known release" as arrival. A bare equality here would report a
    // completed upgrade as stopped-short the moment APP_VERSION moves ahead of
    // the last $releases entry, and re-introduces exactly the per-driver drift
    // this change exists to end.
    $arrived = ($reached == $app_version)
        || is_newest_known_release($reached, $GLOBALS['releases'] ?? array());

    if ($arrived) {
        upgrade_job_detail($lang['UpgradeConversionsApplied'] ?? 'Engine and character-set conversions applied.');
        upgrade_job_finish('done', $lang['UpgradeDatabaseComplete'] ?? 'The database upgrade completed.', $reached);
    } else {
        $stopped = $lang['UpgradeStoppedShort'] ?? 'The upgrade stopped at {reached} without reaching {app}. See the server log.';
        upgrade_job_finish(
            'failed',
            str_replace(array('{reached}', '{app}'), array($reached, $app_version), $stopped),
            $reached
        );
    }
}

/** The empty card. Everything inside it is drawn by the poller. */
function render_database_upgrade_card()
{
    global $escaper, $lang;
?>
                                        <div class="sr-qform sr-upgrade" id="db-upgrade-card">
                                        <div class="sr-qcard">
                                            <div class="sr-qcard-head">
                                                <span class="sr-qcard-htext">
                                                    <h2><?php echo $escaper->escapeHtml($lang['UpgradeDatabaseHeading'] ?? 'Upgrading the database'); ?></h2>
                                                </span>
                                                <span class="sr-state-pill sr-state-info" id="db-upgrade-state"><?php echo $escaper->escapeHtml($lang['UpgradeStateRunning'] ?? 'Running'); ?></span>
                                            </div>
                                            <div class="sr-qcard-body">
                                                <ul class="sr-upgrade-steps" id="db-upgrade-steps"></ul>
                                                <p class="sr-upgrade-outcome" id="db-upgrade-outcome" style="display:none;"></p>
                                                <p class="sr-upgrade-reattach"><?php echo $escaper->escapeHtml($lang['UpgradeSafeToClose'] ?? 'Safe to close this window. The upgrade continues on the server and this page will reattach.'); ?></p>
                                                <div class="sr-upgrade-foot">
                                                    <span class="sr-upgrade-counts" id="db-upgrade-counts"></span>
                                                </div>
                                            </div>
                                        </div>
                                        </div>
<?php
}
