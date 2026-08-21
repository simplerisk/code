<?php

/**
 * @phan-file-suppress PhanRedefineFunction
 *
 * The job record deliberately exists twice: includes/upgrade/job.php is
 * canonical, and extras/upgrade/includes/upgrade_job.php is an identical
 * fallback for the Cores that predate it -- the population the whole Upgrade
 * Extra exists to serve, and one where a bare require of a missing file is a
 * fatal mid-upgrade.
 *
 * Only ever ONE of them is live: every definition in both is wrapped in
 * function_exists(), and the Extra requires Core's copy first when it is there.
 * Phan does not model conditional definition, so it sees both bodies and
 * reports each as redefining the other, in both directions.
 *
 * Suppressed at file scope rather than removed, because the duplication is the
 * design. UpgradeJobRecordParityTest is what actually holds the two copies
 * together -- it asserts they define the same functions and name the same
 * record path, which is the property that matters and the one Phan cannot
 * check.
 */

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2025 to SimpleRisk, Inc and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * THE UPGRADE JOB RECORD                                           *
 *                                                                  *
 * Progress for a running upgrade, kept in a file.                  *
 *                                                                  *
 * CORE'S COPY, and the canonical one. The Upgrade Extra carries an *
 * identical copy because it runs against Cores that predate this   *
 * file; it requires this one when it exists and falls back to its  *
 * own when it does not. Every function here is wrapped in a        *
 * function_exists() guard, so whichever loads first wins and the   *
 * other is a no-op. UpgradeJobRecordParityTest keeps them from     *
 * drifting apart.                                                  *
 *                                                                  *
 * WHY A FILE, and not the obvious alternatives:                    *
 *                                                                  *
 *   * Not the database. The upgrade is CHANGING that schema --     *
 *     tables appear, columns move, the whole thing is converted to *
 *     a different charset. Progress for an operation cannot live   *
 *     inside the thing the operation is rewriting.                 *
 *   * Not a Core table. This Extra runs against Cores that predate *
 *     anything we could add, which is the constraint the rest of   *
 *     this Extra is built around.                                  *
 *   * Not the session. head.php closes the session after           *
 *     authentication, and a detached worker has no session at all. *
 *                                                                  *
 * WHY IT EXISTS AT ALL: the upgrade window used to be a single     *
 * streamed HTTP response. Whether an operator saw progress then    *
 * depended on the server -- Apache's mod_deflate buffers text/html *
 * by default, nginx buffers proxied responses, FastCGI buffers too *
 * -- and none of that is reliably defeatable from PHP, nor         *
 * something we can require a customer to reconfigure. A job the    *
 * browser polls needs no streaming: every response is small and    *
 * complete, so buffering cannot hide it. It also survives a closed *
 * tab, which a streamed response cannot.                           *
 *                                                                  *
 * A fixed filename rather than a job id in the URL: one instance   *
 * upgrades one at a time, and a caller-supplied id would be a path *
 * to validate for no benefit.                                      *
 ********************************************************************/

// Statements are the running commentary of the upgrade functions. Capped so a
// long chain cannot grow the record without bound; the tail is what an operator
// is reading, and the full transcript is in the debug log.
if (!defined('UPGRADE_JOB_MAX_STATEMENTS')) {
    define('UPGRADE_JOB_MAX_STATEMENTS', 400);
}

/**
 * Log, when there is a logger.
 *
 * Both copies of this file are documented as loadable on their own -- the
 * record's unit tests rely on it, and requiring functions.php at file scope
 * would run Core's bootstrap and emit page markup. write_debug_log() lives in
 * functions.php, so calling it bare made every one of those standalone loads a
 * fatal the moment anything went wrong: upgrade_job_start() logged BEFORE its
 * `return false`, so the failure path -- the one a test exercises on purpose --
 * died with "Call to undefined function" instead of returning false.
 */
if (!function_exists('upgrade_job_log')) {
    function upgrade_job_log($message, $level = 'info')
    {
        if (function_exists('write_debug_log')) {
            write_debug_log($message, $level);
        }
    }
}

/**
 * Where the record lives. The system temp directory is outside the web root on
 * every supported layout, so the record is not fetchable over HTTP even though
 * it is not itself secret.
 */
if (!function_exists('upgrade_job_path')) {
    function upgrade_job_path()
    {
        return rtrim(sys_get_temp_dir(), '/') . '/simplerisk-upgrade-job.json';
    }
}

/**
 * Read the record, or null when no upgrade has been started.
 *
 * Returns null rather than throwing on unreadable/corrupt JSON: a status poll
 * that cannot parse the file should report "nothing running", not take the page
 * down while an upgrade is in flight.
 */
if (!function_exists('upgrade_job_read')) {
    function upgrade_job_read()
    {
        $path = upgrade_job_path();
        if (!is_file($path)) {
            return null;
        }

        // Before is_link(), not after it.
        //
        // PHP caches stat and lstat results per request, and a long upgrade
        // calls this dozens of times in ONE request (every step, detail, meta
        // and statement). A file swapped for a symlink mid-run would otherwise
        // be missed by a cached lstat, and the ownership check below would then
        // follow the link to its target.
        clearstatcache(true, $path);

        // A link here would be replaced by the next write (rename does not
        // follow one), but reading through it first would hand the status
        // endpoint the contents of whatever it points at. Cheap to refuse.
        if (is_link($path)) {
            upgrade_job_log('UPGRADE JOB: the job record path is a symlink; ignoring it.', 'warning');
            return null;
        }

        // And it has to be OURS.
        //
        // The path is fixed and predictable, and the system temp directory is
        // usually world-writable. A local account can therefore pre-create a
        // plain file there before this instance ever writes one. That is worse
        // than it sounds: in a sticky directory rename() onto a file owned by
        // someone else fails with EPERM, so every write would fail, and a
        // caller seeing a readable record where its own write failed would
        // report "an upgrade is already running" for ever -- a permanent denial
        // of the recovery path. The contents would also be echoed to the
        // administrator by the status endpoint as if this application had
        // written them.
        //
        // Refusing a record we do not own costs nothing when it is ours.
        $owner = @fileowner($path);
        if ($owner !== false && function_exists('posix_geteuid') && $owner !== posix_geteuid()) {
            upgrade_job_log('UPGRADE JOB: the job record is owned by another account; ignoring it.', 'error');
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $job = json_decode($raw, true);

        return is_array($job) ? $job : null;
    }
}

/**
 * Write the record.
 *
 * Written to a temporary file and renamed, because the poller reads this while
 * the worker writes it. rename() is atomic within a filesystem, so a reader
 * either sees the previous record or the next one, never a half-written one.
 */
if (!function_exists('upgrade_job_write')) {
    function upgrade_job_write($job)
    {
        $path = upgrade_job_path();
        $job['updated'] = time();

        $encoded = json_encode($job, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return false;
        }

        // The TEMP file is the symlink vector here, not the final path.
        //
        // Measured rather than assumed: rename() REPLACES a symlink sitting at
        // the destination instead of following it, so the well-known final
        // filename cannot be used to write through. file_put_contents(), on the
        // other hand, follows one -- and the old temp name was
        // "<path>.<pid>.tmp", which any local account can predict well enough to
        // pre-create as a link to something the web user may write.
        //
        // Two changes close that without inventing a private directory:
        //   * an unpredictable suffix, so the name cannot be pre-placed;
        //   * fopen() in 'x' mode (O_CREAT|O_EXCL), which REFUSES to open a path
        //     that already exists at all -- including a symlink -- so even a
        //     guessed name cannot be substituted in the gap before the write.
        $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';

        $handle = @fopen($temp, 'xb');
        if ($handle === false) {
            return false;
        }

        // Owner-only BEFORE any content is written, so the record is never
        // briefly readable at the umask default (commonly 0644). The HTTP
        // status endpoint admin-gates this data because it names tables and
        // columns as migrations run; leaving the file world-readable would put
        // the same schema detail within reach of any local account that can
        // read the shared temp directory -- the gate at one layer and not the
        // other.
        @chmod($temp, 0600);

        $written = fwrite($handle, $encoded);
        fclose($handle);

        // fwrite() can write FEWER bytes than asked and return that smaller
        // count rather than false -- under disk pressure, for instance. The
        // file_put_contents() this replaced looped internally until the whole
        // buffer was out; a single fwrite() does not, so a short write would
        // publish a truncated record that json_decode() then rejects, which
        // upgrade_job_read() maps to "no job at all".
        if ($written === false || $written < strlen($encoded)) {
            @unlink($temp);
            return false;
        }

        if (!@rename($temp, $path)) {
            @unlink($temp);
            return false;
        }

        return true;
    }
}

/**
 * The steps an operator watches, in the order run_one_click_upgrade_direct()
 * performs them.
 *
 * Localised through $lang with an English fallback per key, because this Extra
 * runs against Cores old enough to predate any of these strings -- a missing key
 * must degrade to readable English, not to an empty step label.
 */
if (!function_exists('upgrade_job_step_labels')) {
    function upgrade_job_step_labels()
    {
        global $lang;

        // Dedicated step-label keys, NOT the *Started event announcements.
        //
        // BackupStart is 'Backup Started.', UpdateStart is 'Update Started.',
        // UpdateDatabaseStarted is 'Database Update Started.' -- past-tense
        // sentences with a trailing full stop, written to be streamed into a
        // log. As step labels they render as "Backup Started. ✓" beside a tick,
        // and the noun phrases in the ?? fallbacks -- the labels actually
        // designed for this list -- were dead on every install, because those
        // keys all exist.
        return array(
            'check'    => $lang['UpgradeStepCheckingVersions'] ?? 'Checking versions',
            'correct'  => $lang['UpgradeCheckingInstance']     ?? 'Checking this instance',
            'backup'   => $lang['UpgradeStepBackingUp']        ?? 'Backing up',
            'files'    => $lang['UpgradeStepUpgradingFiles']   ?? 'Upgrading application files',
            'database' => $lang['UpgradeStepUpgradingDatabase'] ?? 'Upgrading the database',
            'extras'   => $lang['UpgradeStepUpgradingExtras']  ?? 'Upgrading installed extras',
        );
    }
}

/**
 * Claim the right to start an upgrade, or report that one is already running.
 *
 * The header says "one instance upgrades one at a time", and until this that was
 * an assumption rather than something enforced. Starting was a plain
 * check-then-act -- read the record, see nothing running, write a new one -- so
 * two requests arriving together (a double click, two admins, a retry) could
 * both read "idle" before either wrote, and both go on to extract a bundle over
 * the live tree and run the same DDL against the same database concurrently.
 * The migrations are idempotent when run in SEQUENCE; that is not the same
 * promise as safe in PARALLEL, where two processes can pass the same
 * table_exists() guard and both issue the ALTER.
 *
 * A named MySQL advisory lock held across the check AND the write closes that
 * window -- the same mechanism the Assessments and Vulnerability Management
 * Extras already use for their own single-run guards.
 *
 * @param callable $claim Runs while the lock is held; its return value is
 *                        passed back. Called only if no job is already running.
 * @return mixed The claim's return value, or false when a job is in flight or
 *               the lock could not be taken.
 */
if (!function_exists('upgrade_job_claim')) {
    function upgrade_job_claim(callable $claim, &$reason = null)
    {
        $reason = 'started';
        // A MySQL advisory lock, matching how the Assessments and Vulnerability
        // Management Extras already serialise their own single-run operations.
        //
        // A file lock would need a well-known path in the shared temp
        // directory, and a well-known path there can be pre-created by any
        // local account as a symlink that fopen() will follow. (tmpfile() does
        // not help: it mints a RANDOM name, so two processes would each lock
        // their own file and never contend -- a lock needs a shared name, which
        // is precisely what makes a file lock awkward to place safely.)
        // GET_LOCK needs no file at all, and it is named, so it also holds
        // across multiple web workers -- and across nodes sharing a database,
        // which a file lock on local disk never could.
        //
        // Held for the CLAIM only, not the whole upgrade. This exists to make
        // "is one running?" and "start one" atomic; after that the job record's
        // own 'running' state is what turns a second request away, and it
        // survives the process the way a connection-scoped lock cannot.
        // db_open()/db_close() live in Core's functions.php. Declared here per
        // CLAUDE.md rather than relied upon through extras/upgrade/index.php
        // having loaded it first, so a future include reorder or a new caller
        // cannot turn these into a fatal.
        //
        // Inside the function, NOT at file scope: requiring functions.php is not
        // side-effect free -- it runs core's bootstrap and emits page markup --
        // so doing it on include would stop this file being loadable on its own,
        // which the record's own unit tests rely on. Only the claim needs a
        // database; everything else here is filesystem work.
        $upgrade_job_functions = realpath(__DIR__ . '/../functions.php');
        if ($upgrade_job_functions !== false) {
            require_once($upgrade_job_functions);
        }

        // Note this is the shared connection, not a private one -- db_open()
        // returns a singleton. That is fine for what this guards: the racing
        // callers are separate HTTP requests, so separate processes with
        // separate connections, which is exactly where GET_LOCK excludes. Two
        // claims inside ONE process would not exclude (MySQL grants a
        // re-entrant lock to the same session), and there is no such caller.
        $lock_db = db_open();
        if (!$lock_db) {
            // Refuse rather than proceed unserialised: an upgrade that cannot
            // prove it is alone is exactly the one not to start.
            upgrade_job_log('UPGRADE JOB: could not open a connection to take the upgrade lock; refusing to start.', 'error');
            return false;
        }

        // Only the ACQUISITION is guarded.
        //
        // The claim runs outside this catch on purpose. upgrade_job_start()
        // writes a file and calls random_bytes(), and an exception from it is
        // not a lock-acquisition failure -- but reporting it as one returns the
        // same false that means "an upgrade is already running", so every
        // caller then guesses the reason by re-reading the record and tells the
        // operator their temp directory is unwritable. with_upgrade_lock() gets
        // this right and documents why; this function used to do the opposite.
        $acquired = false;

        try {
            $stmt = $lock_db->query("SELECT GET_LOCK('simplerisk_upgrade', 0) AS acquired");
            $row  = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            $acquired = ((int)($row['acquired'] ?? 0) === 1);
        } catch (\Throwable $e) {
            upgrade_job_log('UPGRADE JOB: taking the upgrade lock failed: ' . $e->getMessage(), 'error');
            $acquired = false;
        }

        if (!$acquired) {
            // Lock contention, NOT an unwritable record.
            //
            // The distinction matters to the operator. Callers used to infer
            // the reason for a false by re-reading the record, and when the
            // record said nothing they told the operator the system temp
            // directory was unwritable -- which, since the management and v2
            // channels now hold this lock for a whole chain WITHOUT writing a
            // record, is exactly what an admin whose click loses a race with
            // fleet automation would be told. Wrong subsystem, on the recovery
            // surface.
            $reason = 'lock_contended';
            db_close($lock_db);
            return false;
        }

        try {
            $running = upgrade_job_read();
            if (is_array($running)
                && ($running['state'] ?? '') === 'running'
                && !upgrade_job_is_stale($running)) {
                $reason = 'already_running';
                return false;
            }

            return $claim();
        } finally {
            // RELEASE_LOCK is what actually releases it, and it must run.
            //
            // Not "the connection closes anyway": db_close() is a no-op in this
            // codebase -- it nulls its own parameter and the line that clears
            // the global is commented out -- and db_open() hands back a shared
            // singleton rather than a fresh connection. So the session outlives
            // this function, and a lock left held here would stay held for the
            // rest of the request.
            try { $lock_db->query("SELECT RELEASE_LOCK('simplerisk_upgrade')"); } catch (\Throwable $e) {}
            db_close($lock_db);
        }
    }
}

/**
 * Begin a job, replacing any previous one.
 *
 * @param string $from_version The application version at the start.
 * @param array  $steps        Ordered [key => label]. All start 'todo' -- the
 *                             runner marks the first one running when it
 *                             actually begins it, so a job that dies before
 *                             starting never claims work it did not do.
 */
if (!function_exists('upgrade_job_start')) {
    function upgrade_job_start($from_version, $steps)
    {
        $rows = array();
        foreach ($steps as $key => $label) {
            // 'meta' is the right-hand value on the step row -- a size, a
            // duration, "release 2 of 2". It answers "how much" while the label
            // answers "what", so the two never compete for the same space.
            $rows[] = array('key' => $key, 'label' => $label, 'state' => 'todo', 'detail' => '', 'meta' => '', 'started' => 0);
        }

        $job = array(
            // Lets the client tell a fresh run from the tail of an old one.
            'id'           => bin2hex(random_bytes(8)),
            'started'      => time(),
            'from_version' => (string)$from_version,
            'to_version'   => '',
            'state'        => 'running',
            'message'      => '',
            'steps'        => $rows,
            'statements'   => array(),
        );

        // The write's result is the return value, not an afterthought.
        //
        // Discarding it let a failed persist look exactly like a successful
        // start, and the consequence was not cosmetic: rename() onto a path
        // owned by another account fails with EPERM in a sticky directory
        // (/tmp is 1777 on essentially every layout), so a single planted file
        // at this well-known path would make EVERY write fail. The record would
        // then never say "running", upgrade_job_read() would keep answering
        // "no job", and the next request's claim would sail through and start a
        // SECOND concurrent upgrade -- defeating the very guard this file
        // exists to provide, silently.
        if (!upgrade_job_write($job)) {
            upgrade_job_log('UPGRADE JOB: could not persist the upgrade job record; refusing to start an untracked upgrade.', 'error');
            return false;
        }

        return $job;
    }
}

/** Mark a step running, and the one before it done. */
if (!function_exists('upgrade_job_step')) {
    function upgrade_job_step($key, $detail = '')
    {
        $job = upgrade_job_read();
        if ($job === null) {
            return;
        }

        foreach ($job['steps'] as $i => $step) {
            if ($step['key'] === $key) {
                $job['steps'][$i]['state']   = 'running';
                $job['steps'][$i]['detail']  = (string)$detail;
                $job['steps'][$i]['started'] = time();
            } elseif ($step['state'] === 'running') {
                $job['steps'][$i]['state'] = 'done';

                // A step that finished without saying anything about itself
                // gets its own duration, so every completed row carries a
                // right-hand value rather than a ragged mix of some and none.
                //
                // Both keys are read defensively. The record on disk can have
                // been written by an OLDER copy of this Extra -- one that never
                // wrote 'started' -- and a half-finished upgrade whose Extra was
                // replaced mid-run is exactly the situation this file exists to
                // report on, so it must not fatal on the shape it finds.
                $started = (int)($job['steps'][$i]['started'] ?? 0);

                if (($job['steps'][$i]['meta'] ?? '') === '' && $started > 0) {
                    $job['steps'][$i]['meta'] = max(0, time() - $started) . 's';
                }
            }
        }

        upgrade_job_write($job);
    }
}

/**
 * Bytes as an operator reads them -- "184 MB", not "192937984".
 *
 * Lives here rather than leaning on a core helper because this extra runs
 * against releases that may not have one, and the step metadata is its only
 * caller.
 */
if (!function_exists('upgrade_human_bytes')) {
    function upgrade_human_bytes($bytes)
    {
        $bytes = (float)$bytes;
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $unit = 0;
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        // One decimal below 10 so "1.4 GB" does not read as "1 GB", none above
        // it where the extra digit is noise.
        return (($bytes < 10 && $unit > 0) ? round($bytes, 1) : round($bytes)) . ' ' . $units[$unit];
    }
}

/**
 * Set the right-hand value on a step -- "184 MB", "release 2 of 2".
 *
 * Separate from detail(): detail describes what is happening inside the step
 * and sits under the label; meta is the quantity and sits opposite it.
 */
if (!function_exists('upgrade_job_meta')) {
    function upgrade_job_meta($key, $meta)
    {
        $job = upgrade_job_read();
        if ($job === null) {
            return;
        }

        foreach ($job['steps'] as $i => $step) {
            if ($step['key'] === $key) {
                $job['steps'][$i]['meta'] = (string)$meta;
                break;
            }
        }

        upgrade_job_write($job);
    }
}

/** Attach detail to the running step without changing its state. */
if (!function_exists('upgrade_job_detail')) {
    function upgrade_job_detail($detail)
    {
        $job = upgrade_job_read();
        if ($job === null) {
            return;
        }

        foreach ($job['steps'] as $i => $step) {
            if ($step['state'] === 'running') {
                $job['steps'][$i]['detail'] = (string)$detail;
                break;
            }
        }

        upgrade_job_write($job);
    }
}

/**
 * Record one line of upgrade commentary.
 *
 * The upgrade functions narrate themselves with echo and there are ninety-eight
 * of them; this is fed by an output-buffer callback rather than by editing any
 * of them. See upgrade_job_capture_output().
 */
if (!function_exists('upgrade_job_statement')) {
    function upgrade_job_statement($text)
    {
        $text = trim(html_entity_decode(strip_tags(str_replace(array('<br />', '<br>', '<br/>'), "\n", (string)$text)), ENT_QUOTES));
        if ($text === '') {
            return;
        }

        $job = upgrade_job_read();
        if ($job === null) {
            return;
        }

        // Tagged with the step that produced them. The screen shows statements
        // UNDER the running step rather than in a separate log, so each one has
        // to know where it belongs -- and the full list still reads as a
        // transcript for the log download.
        $running = '';
        foreach ($job['steps'] as $step) {
            if ($step['state'] === 'running') {
                $running = $step['key'];
                break;
            }
        }

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $job['statements'][] = array('step' => $running, 'text' => $line);
            }
        }

        $overflow = count($job['statements']) - UPGRADE_JOB_MAX_STATEMENTS;
        if ($overflow > 0) {
            $job['statements'] = array_slice($job['statements'], $overflow);
        }

        upgrade_job_write($job);
    }
}

/**
 * Route everything echoed from here on into the job record.
 *
 * The chunk size of 1 is the point: it makes PHP invoke the callback on EVERY
 * output call rather than when a buffer fills, so each echo in an upgrade
 * function becomes its own statement as it happens. Without it the commentary
 * would arrive in one lump when the buffer flushed, which is the same silence
 * the streamed window had.
 *
 * Returns '' so the output is swallowed: a detached worker has nobody to write
 * to, and the record is the only consumer.
 */
if (!function_exists('upgrade_job_capture_output')) {
    function upgrade_job_capture_output()
    {
        ob_start(function ($chunk) {
            upgrade_job_statement($chunk);
            return '';
        }, 1);
    }
}

/** Finish the job: state is 'done', 'failed' or 'refused'. */
if (!function_exists('upgrade_job_finish')) {
    function upgrade_job_finish($state, $message = '', $to_version = '')
    {
        $job = upgrade_job_read();
        if ($job === null) {
            return;
        }

        // First terminal state wins.
        //
        // The callers are layered: the runner closes the job when it decides the
        // upgrade failed, and the caller that invoked it closes it again when it
        // returns normally -- which it does, since a reported failure is a
        // return, not an exception. Without this the second call would overwrite
        // 'failed' with 'done' and the operator's screen would show a successful
        // upgrade that did not happen. The shutdown guard relies on the same
        // rule from the other direction.
        if (($job['state'] ?? 'running') !== 'running') {
            return;
        }

        foreach ($job['steps'] as $i => $step) {
            if ($step['state'] === 'running') {
                // A refusal or failure leaves the step where it stopped rather
                // than claiming it finished.
                $job['steps'][$i]['state'] = ($state === 'done') ? 'done' : $step['state'];
                continue;
            }

            // A step that was never reached on a SUCCESSFUL run was not needed
            // -- an instance already on the current release does no file swap
            // and no database upgrade. Leaving those rows sitting unticked read
            // as an upgrade that stalled two steps in, so they are marked
            // skipped and say why. On a failed run they stay 'todo', because
            // there they genuinely did not happen.
            if ($state === 'done' && $step['state'] === 'todo') {
                $job['steps'][$i]['state'] = 'skipped';
                if (($job['steps'][$i]['meta'] ?? '') === '') {
                    $job['steps'][$i]['meta'] = $GLOBALS['lang']['UpgradeStepNotNeeded'] ?? 'Not needed';
                }
            }
        }

        $job['state']   = $state;
        $job['message'] = (string)$message;
        if ($to_version !== '') {
            $job['to_version'] = (string)$to_version;
        }

        upgrade_job_write($job);
    }
}

/**
 * Guarantee the job ends in a terminal state, however the process ends.
 *
 * upgrade_job_finish() at the end of the runner is not enough. Several things
 * the upgrade calls end the process where they stand -- the PHP-floor refusal
 * returns through a core JSON responder that exits, and a fatal error or an
 * uncaught throwable does the same -- and every one of those skips the finish
 * call. The record is then left saying "running", which the page believes,
 * so a refusal an operator should see in seconds reads as a hang until the
 * staleness window expires.
 *
 * A shutdown function runs on all of those paths. It closes the job only when
 * something else has not already closed it, so a normal finish still wins.
 */
if (!function_exists('upgrade_job_guard_shutdown')) {
    function upgrade_job_guard_shutdown()
    {
        register_shutdown_function(function () {
            // Flush the capture buffer first: whatever was echoed immediately
            // before the exit is usually the reason for it, and it becomes the
            // message below.
            $guard = 0;
            while (ob_get_level() > 0 && $guard++ < 32) {
                @ob_end_flush();
            }

            $job = upgrade_job_read();
            if (!is_array($job) || ($job['state'] ?? '') !== 'running') {
                return;
            }

            $fatal = error_get_last();
            if ($fatal !== null && in_array($fatal['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
                $message = $fatal['message'];
            } elseif (!empty($job['statements'])) {
                // The last thing said before stopping. For a refusal that is the
                // refusal itself, which is exactly what should be shown -- but
                // core's refusals come back as a JSON envelope, and an operator
                // should be shown the sentence, not the envelope.
                $last = end($job['statements']);
                $message = is_array($last) ? ($last['text'] ?? '') : (string)$last;
                $decoded = json_decode($message, true);
                if (is_array($decoded) && !empty($decoded['status_message'])) {
                    $message = $decoded['status_message'];
                }
            } else {
                $message = 'The upgrade ended before it finished.';
            }

            upgrade_job_finish('failed', $message);
        });
    }
}

/**
 * Is the recorded job still being worked on?
 *
 * A worker that dies without finishing would otherwise leave the client polling
 * a job that says "running" forever, so a record nothing has touched for a while
 * is reported as stale. The window is generous because a database backup on a
 * large instance legitimately produces no output for minutes.
 */
if (!function_exists('upgrade_job_is_stale')) {
    function upgrade_job_is_stale($job, $seconds = 900)
    {
        if (!is_array($job) || ($job['state'] ?? '') !== 'running') {
            return false;
        }

        return (time() - (int)($job['updated'] ?? 0)) > $seconds;
    }
}
