<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/bootstrap.php'));
require_once(realpath(__DIR__ . '/queues.php'));
require_once(realpath(__DIR__ . '/functions.php'));

// Require the composer autoload file
// This loads the PHPMailer library
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include the language file
require_once(language_file());

/*******************************
 * FUNCTION: GET MAIL SETTINGS *
 *******************************/
function get_mail_settings()
{
        // Open the database connection
        $db = db_open();

        // Get the risk levels
        $stmt = $db->prepare("SELECT * FROM settings WHERE name LIKE 'phpmailer_%'");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        $mail = [];

        // For each entry in the array
        foreach ($array as $value)
        {
                $mail[$value['name']] = $value['value'];
        }

        return $mail;
}

/**********************************
 * FUNCTION: UPDATE MAIL SETTINGS *
 **********************************/
function update_mail_settings($transport, $from_email, $from_name, $replyto_email, $replyto_name, $host, $smtpautotls, $smtpauth, $username, $password, $encryption, $port, $prepend) {

    // Open the database connection
    $db = db_open();

    // If the transport is sendmail or smtp
    if ($transport == "sendmail" || $transport == "smtp") {

        $current_transport = get_setting("phpmailer_transport");

        // If the current transport is not the same as the new transport
        if ($current_transport != $transport) {

            // Update the transport
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_transport'");
            $stmt->bindParam(":value", $transport, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_transport\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

        }
    }

	// If the from_email is valid
	if (preg_match("/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $from_email)) {

        $current_from_email = get_setting("phpmailer_from_email");

        // If the current from_email is not the same as the new from_email
        if ($current_from_email != $from_email) {

            // Update the from_email
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_from_email'");
            $stmt->bindParam(":value", $from_email, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_from_email\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

        }
	}

    $current_from_name = get_setting("phpmailer_from_name");

    // If the current from_name is not the same as the new from_name
    if ($current_from_name != $from_name) {
        
        // Update the from_name
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_from_name'");
        $stmt->bindParam(":value", $from_name, PDO::PARAM_STR, 200);
        $stmt->execute();
        
        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_from_name\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

    }

    // If the replyto_email is valid
	if (preg_match("/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $replyto_email)) {

        $current_replyto_email = get_setting("phpmailer_replyto_email");

        // If the current replyto_email is not the same as the new replyto_email
        if ($current_replyto_email != $replyto_email) {

            // Update the replyto_email
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_replyto_email'");
            $stmt->bindParam(":value", $replyto_email, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_replyto_email\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

        }
	}

    $current_replyto_name = get_setting("phpmailer_replyto_name");

    // If the current replyto_name is not the same as the new replyto_name
    if ($current_replyto_name != $replyto_name) {

        // Update the replyto_name
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_replyto_name'");
        $stmt->bindParam(":value", $replyto_name, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_replyto_name\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

    }

    $current_host = get_setting("phpmailer_host");
    
    // If the current host is not the same as the new host
    if ($current_host != $host) {

        // Update the host
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_host'");
        $stmt->bindParam(":value", $host, PDO::PARAM_STR, 200);
        $stmt->execute();
        
        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_host\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

    }


	// If the SMTP Auto TLS is either true or false
	if ($smtpautotls == "true" || $smtpautotls == "false") {

        $current_smtpautotls = get_setting("phpmailer_smtpautotls");

        // If the current SMTP Auto TLS is not the same as the new SMTP Auto TLS
        if ($current_smtpautotls != $smtpautotls) {

            // Update the SMTP Auto TLS
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_smtpautotls'");
            $stmt->bindParam(":value", $smtpautotls, PDO::PARAM_STR, 5);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_smtpautotls\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

        }
	}

	// If the SMTP Authentication is either true or false
	if ($smtpauth == "true" || $smtpauth == "false") {

        $current_smtpauth = get_setting("phpmailer_smtpauth");

        // If the current SMTP Authentication is not the same as the new SMTP Authentication
        if ($current_smtpauth != $smtpauth) {

            // Update the smtp authentication
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_smtpauth'");
            $stmt->bindParam(":value", $smtpauth, PDO::PARAM_STR, 5);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_smtpauth\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

        }
	}

    $current_username = get_setting("phpmailer_username");

    // If the current username is not the same as the new username
    if ($current_username != $username) {

        // Update the username
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_username'");
        $stmt->bindParam(":value", $username, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_username\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

    }

    // If the password is not empty
    if ($password != "") {

        $current_password = get_setting("phpmailer_password");

        // If the current password is not the same as the new password
        if ($current_password != $password) {

            // Update the value
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_password'");
            $stmt->bindParam(":value", $password, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_password\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

        }
    }

    // If the encryption is none or tls or ssl
    if ($encryption == "none" || $encryption == "tls" || $encryption == "ssl") {

        $current_encryption = get_setting("phpmailer_smtpsecure");

        // If the current encryption is not the same as the new encryption
        if ($current_encryption != $encryption) {

            // Update the encryption
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_smtpsecure'");
            $stmt->bindParam(":value", $encryption, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_smtpsecure\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

        }
    }

    // If the port is an integer value
    if (is_numeric($port)) {

        $current_port = get_setting("phpmailer_port");

        // If the current port is not the same as the new port
        if ($current_port != $port) {

            // Update the port
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_port'");
            $stmt->bindParam(":value", $port, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_port\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

        }
    }

    $current_prepend = get_setting("phpmailer_prepend");

    // If the current prepend is not the same as the new prepend
    if ($current_prepend != $prepend) {

        // Update the prepend
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_prepend'");
        $stmt->bindParam(":value", $prepend, PDO::PARAM_STR);
        $stmt->execute();

        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_prepend\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'] ?? 0, $message);

    }

    // Close the database connection
    db_close($db);

}

/*********************************
 * FUNCTION: SEND EMAIL          *
 * Will queue emails for sending *
 *********************************/
function send_email(PDO $db, $name, $email, $subject, $body, ?int $sender_uid = null): bool
{
    $queue_task_payload = [
        'triggered_at'    => time(),
        'recipient_name'  => $name,
        'recipient_email' => $email,
        'subject'         => $subject,
        'body'            => $body
    ];

    // Optionally record who triggered the send so that a later delivery
    // failure can be surfaced to that user rather than only to all admins.
    // The caller passes this explicitly (e.g. $_SESSION['uid'] in a browser
    // context) — never read from the session here, because this function is
    // also called from the session-less cron worker, where the session uid
    // would not identify the real sender.
    if ($sender_uid !== null) {
        $queue_task_payload['sender_uid'] = $sender_uid;
    }

    $queued = queue_task($db, 'core_email_send', $queue_task_payload, 100, 5, 3600);

    if (!$queued) {
        write_debug_log("Failed to queue email to {$email}", 'error');
    }

    return $queued;
}

// The retention window applied to every email-failure notification, in days.
// Admin-tunable at Settings -> Mail; the value below is the fallback when the
// setting has never been saved.
//
// Every one of these rows MUST carry an expires_at: the purge only sweeps
// recipient rows that are read or trashed, plus notifications whose expires_at
// has passed, so an unread, non-expiring failure notification would live in the
// table forever. That is why the retention is a floor-of-1-day setting rather
// than an on/off one — there is no valid "never expire" value.
//
// Seven days is long enough that an admin who is away for a few days still sees
// the alert, and short enough that a resolved outage's alerts age out on their
// own. On the pre-enqueue guard path the expiry doubles as a re-arm: that guard
// re-runs on every cron tick for a contact that is still broken, so once the
// row is reclaimed the next tick recreates it, turning a one-shot alert into a
// self-refreshing "this is still broken" reminder.
const EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_DEFAULT = 7;

// The accepted range, single-sourced: the settings form renders these as the
// input's min/max, the submitted value is validated against them, and a stored
// value is clamped to them on read. A lower bound of one day is a hard floor
// rather than a preference — anything below it writes an expiry the purge can
// reclaim before the notification has been seen. A year is far past any useful
// "someone should have looked at this by now" window, and the ceiling keeps a
// fat-fingered or hand-edited settings row from parking un-purgeable rows in
// the notifications table for a decade.
const EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_MIN = 1;
const EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_MAX = 365;

/**
 * Coerce a stored retention value into a usable number of days.
 *
 * Settings are TEXT, so this can be handed anything: an empty string (never
 * saved), a hand-edited 'abc', a negative, or a float-ish '7.9'. Anything that
 * is not a positive integer-ish value falls back to the default rather than
 * producing an expires_at in the past — which would make the notification
 * purgeable the moment it was written, i.e. an alert nobody ever sees.
 *
 * Pure.
 *
 * @param mixed $raw the raw setting value
 * @return int a day count between 1 and EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_MAX
 */
function normalize_email_failure_ttl_days($raw): int
{
    if (!is_numeric($raw)) {
        return EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_DEFAULT;
    }

    $days = (int)$raw;

    if ($days < EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_MIN) {
        return EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_DEFAULT;
    }

    return min($days, EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_MAX);
}

/**
 * An admin-submitted retention value, validated, or null when it falls outside
 * the accepted range.
 *
 * Deliberately stricter than normalize_email_failure_ttl_days(). A *stored*
 * value that has gone bad is silently replaced with the default, because a
 * notification with a conservative expiry beats one with a broken expiry. A
 * value someone just typed into the form is rejected and reported instead —
 * saving something other than what the admin entered, with no indication that
 * it happened, is its own bug.
 *
 * Pure.
 *
 * @param mixed $raw the submitted value
 * @return ?int the accepted day count, or null when the input is unusable
 */
function email_failure_ttl_days_from_input($raw): ?int
{
    if (!is_numeric($raw)) {
        return null;
    }

    // Refuse a fraction rather than truncating it. The form's number input
    // rejects '7.9' client-side, but a direct POST reaches here, and casting it
    // to 7 would save a value the admin never typed without saying so — the one
    // thing this function exists to avoid.
    if ((float)$raw !== floor((float)$raw)) {
        return null;
    }

    $days = (int)$raw;

    if ($days < EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_MIN || $days > EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_MAX) {
        return null;
    }

    return $days;
}

/**
 * Pure rate-limit decision for the Settings > Mail "send test email" throttle.
 *
 * Drops send timestamps older than $period seconds and reports whether another
 * send is permitted (fewer than $limit sends remain inside the window). Kept
 * side-effect-free — no session, no clock, no I/O — so the throttle decision can
 * be unit-tested directly (see tests/unit/TestMailThrottleTest.php); the caller
 * owns the session read/write and the actual send. `$now` is passed in for the
 * same reason email_failure_dedup_bucket() takes the hour: to make the window
 * boundary directly expressible in a test.
 *
 * @param array $sent_times prior send unix timestamps (non-numeric entries are ignored)
 * @param int   $now        current unix timestamp
 * @param int   $limit      maximum sends allowed within the window
 * @param int   $period     window length in seconds
 * @return array{recent: int[], allowed: bool} the surviving in-window timestamps
 *         (re-indexed, cast to int) and whether one more send is allowed
 */
function test_mail_throttle_evaluate(array $sent_times, int $now, int $limit, int $period): array
{
    $recent = array_values(array_map('intval', array_filter($sent_times, function ($sent_time) use ($now, $period) {
        return is_numeric($sent_time) && ($now - (int)$sent_time <= $period);
    })));

    return ['recent' => $recent, 'allowed' => count($recent) < $limit];
}

/**
 * The configured email-failure notification retention, in days.
 *
 * Takes the caller's PDO handle so the queue worker reuses its connection
 * instead of opening another one per failed task.
 *
 * @param ?PDO $db an open connection, or null to let get_setting() open one
 * @return int a day count between 1 and EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_MAX
 */
function email_failure_notification_ttl_days(?PDO $db = null): int
{
    return normalize_email_failure_ttl_days(
        get_setting(
            'email_failure_notification_ttl_days',
            EMAIL_FAILURE_NOTIFICATION_TTL_DAYS_DEFAULT,
            db: $db
        )
    );
}

/**
 * Whether an email-failure notification is aimed at one known sender rather
 * than broadcast to all admins.
 *
 * Extracted so the routing decision in create_email_failure_notification() and
 * the dedup-bucket key in email_failure_dedup_bucket() can never disagree about
 * what "has a sender" means — if they did, a notification would be bucketed
 * against one target and delivered to another, and the dedup would silently
 * stop working. 0 counts as "no sender": a cron-context send has no session, so
 * $_SESSION['uid'] is unset or 0, and there is no user 0 to notify.
 *
 * Pure.
 */
function is_email_failure_sender_targeted(?int $sender_uid): bool
{
    return $sender_uid !== null && $sender_uid > 0;
}

/**
 * The dedup key for a queued-delivery failure: one bucket per (target, hour).
 *
 * The task id used to be the key, which meant one notification row per failed
 * task. That is fine for one broken address and pathological for a systemic
 * fault: with SMTP down, a bulk send of 200 messages produces 200 doomed tasks,
 * each of which reaches terminal failure and mints its own never-expiring row —
 * fanned out to a recipient row per admin on the all-admin path. Bucketing by
 * the hour instead collapses that to one row per target per hour, so the volume
 * is bounded by elapsed time rather than by message count, and a week-long
 * outage cannot grow without limit.
 *
 * The trade-off this forces is in the wording, not here: because INSERT IGNORE
 * keeps the FIRST row inserted in a bucket, the surviving row cannot name a
 * single recipient without implying the others were delivered. Hence the
 * recipient-free body that points at the Queue Monitor, where every failed
 * task's payload — recipient address included — is listed individually.
 *
 * $hour is injectable so the bucketing is testable without waiting on the
 * clock; production callers omit it. Pure when $hour is supplied.
 */
function email_failure_dedup_bucket(?int $sender_uid, ?string $hour = null): string
{
    $hour   = $hour ?? date('Y-m-d-H');
    $target = is_email_failure_sender_targeted($sender_uid) ? "u{$sender_uid}" : 'all';

    return "{$target}:{$hour}";
}

/**
 * The absolute Queue Monitor URL to deep-link from a delivery-failure
 * notification, or null when one cannot be built safely.
 *
 * Deliberately takes the base URL as an argument instead of calling
 * get_base_url() / build_url(): those fall back to reconstructing the base from
 * $_SERVER when the simplerisk_base_url setting is empty, and this code path
 * runs inside the CLI queue worker where SERVER_NAME and DOCUMENT_ROOT do not
 * exist. That fallback would build a garbage URL AND persist it via
 * add_setting('simplerisk_base_url', ...), poisoning every URL the application
 * builds afterwards. So the caller passes the stored setting and this helper
 * refuses anything that is not already a usable absolute URL.
 *
 * Returning null (rather than a relative path) matters: create_notification*()
 * rejects the whole notification when is_safe_notification_link() fails, so a
 * bad link would not degrade the notification — it would delete it.
 *
 * Deterministic — same input, same output, no state of its own — but not pure
 * in the strict sense: the first call lazy-loads notifications.php for
 * is_safe_notification_link(). notifications.php is not globally loaded, and
 * this is the house pattern for reaching it (licensing.php and the notification
 * jobs require it the same way). Each entry point declares the require itself
 * rather than relying on a sibling having run first; require_once makes the
 * repeat a no-op.
 */
function queue_monitor_url_from_base(?string $base_url): ?string
{
    require_once(realpath(__DIR__ . '/notifications.php'));

    if ($base_url === null || trim($base_url) === '') {
        return null;
    }

    $url = rtrim(trim($base_url), '/') . '/admin/queue_monitor.php';

    // Same rule create_notification*() will apply, checked here so the caller
    // can fall back to no link instead of losing the notification.
    return is_safe_notification_link($url) ? $url : null;
}

/**
 * Surface a queued-email delivery failure through the in-app notification
 * center so it is not silent in the server log.
 *
 * Safe to call from the session-less cron worker: create_notification*() read
 * no $_SESSION, take created_by explicitly, and manage their own DB lifecycle.
 *
 * Called from the core_email_send job's on_terminal_failure hook, i.e. only
 * once the worker has exhausted its retries — NOT per attempt.
 *
 * Takes no recipient address and no task id on purpose. One notification stands
 * for every delivery failure aimed at the same target in the same hour (see
 * email_failure_dedup_bucket()), so naming one address would misrepresent the
 * rest; the per-task detail lives in the Queue Monitor and the server log,
 * which the body cites.
 *
 * A producer reporting something other than a queued delivery failure should
 * call create_email_failure_notification() below with its own wording instead
 * of reusing this one.
 *
 * @param ?PDO $db an open connection to reuse, or null to let
 *                 create_notification*() open and close its own (used by
 *                 pre-enqueue callers that hold no connection)
 * @param ?int $sender_uid the user to notify, or null/0 to notify all admins
 */
function notify_email_send_failure(?PDO $db, ?int $sender_uid): void
{
    // The escaping rule differs per field because the notification center renders
    // title and body through different client paths (notifications.js):
    //   - title is rendered with escapeHtml(item.title) only — no decode step —
    //     so the stored title must be RAW: _lang_raw().
    //   - body is rendered through stripHtml(item.body), whose innerHTML round-trip
    //     DECODES HTML entities once before display, and whose contract (see the
    //     "server-side purify_html() is the sanitization layer" note in
    //     notifications.js) is that the server has already made the body safe. So
    //     the stored body must be HTML-ESCAPED at source: _lang() escapes its
    //     params.
    // Neither string interpolates user data any more — the recipient address is
    // gone from the body — so there is nothing here for an attacker to reach the
    // innerHTML parse with. _lang() is still the correct call for the body: it
    // keeps the escape-at-source contract in force if a param is ever added.
    $title = _lang_raw('EmailSendFailedNotificationTitle');
    $body  = _lang('EmailSendFailedNotificationBody');

    // Deep-link the Queue Monitor, which lists each failed task and its payload
    // (recipient address included) — the detail this notification deliberately
    // no longer carries. Read the stored setting directly rather than through
    // get_base_url(); see queue_monitor_url_from_base() for why that matters on
    // this code path. get_setting() yields false when the row is missing or the
    // read errors, so normalize to null; a null link simply means no deep link.
    $base_url = get_setting('simplerisk_base_url', null, db: $db);
    $link     = queue_monitor_url_from_base(is_string($base_url) ? $base_url : null);

    create_email_failure_notification(
        $db,
        $sender_uid,
        $title,
        $body,
        email_failure_dedup_bucket($sender_uid),
        $link
    );
}

/**
 * The delivery mechanism behind notify_email_send_failure() and any other
 * producer that needs to surface an email problem through the notification
 * center.
 *
 * Exists separately because "a queued email could not be delivered" is not the
 * only such event. A pre-enqueue guard that refuses an unusable recipient never
 * queued anything and the mail transport is fine — telling that admin to "check
 * your mail settings" points them at the wrong remediation. Callers that are
 * not reporting a queued-delivery failure pass their own title and body here
 * instead of borrowing that wording.
 *
 * @param ?PDO $db an open connection to reuse, or null to let
 *                 create_notification*() open and close its own
 * @param ?int $sender_uid the user to notify, or null/0 to notify all admins
 * @param string $title MUST be raw — the notification center renders the title
 *                      through escapeHtml() with no decode step, so a
 *                      pre-escaped title shows literal entities
 * @param string $body MUST already be HTML-escaped by the caller, via _lang(),
 *                     which escapes its params. The body is rendered through
 *                     stripHtml(), whose innerHTML round-trip decodes entities
 *                     once, so an unescaped user-controlled value here is
 *                     stored XSS
 * @param ?string $dedup_token collapses repeat notifications for the same
 *                             target onto one row; no token means no dedup
 * @param ?string $link optional deep link for the notification
 */
function create_email_failure_notification(?PDO $db, ?int $sender_uid, string $title, string $body, ?string $dedup_token = null, ?string $link = null): void
{
    // notifications.php is not globally loaded; require it here (the sibling
    // notification jobs use the same lazy require). queue_monitor_url_from_base()
    // above declares the same require — deliberately, so neither function
    // depends on the other having run first. require_once makes the second a
    // no-op.
    require_once(realpath(__DIR__ . '/notifications.php'));

    // De-dupe repeated failure notifications for the same target into one row
    // via the UNIQUE external_guid. Queued-delivery failures bucket by (target,
    // hour) so a systemic fault cannot mint a row per message — see
    // email_failure_dedup_bucket(). A pre-enqueue guard whose recipient never
    // becomes valid is re-hit on every cron tick and passes a stable
    // per-recipient key instead, so one broken contact stays one row rather than
    // one row per hour. No token => no guid => no dedup.
    $guid = $dedup_token !== null ? "email_fail:{$dedup_token}" : null;

    // Always expire. Without this the row is unreclaimable: the retention purge
    // sweeps read or trashed recipient rows and notifications whose expires_at
    // has passed, and an unread failure notification is none of those.
    $expires_at = date('Y-m-d H:i:s', time() + (email_failure_notification_ttl_days($db) * 24 * 60 * 60));

    try {
        if (is_email_failure_sender_targeted($sender_uid)) {
            create_notification_for_user_ids(
                source:        'workflow',
                title:         $title,
                body:          $body,
                link:          $link,
                user_ids:      [$sender_uid],
                created_by:    null,
                expires_at:    $expires_at,
                external_guid: $guid,
                db:            $db
            );
        } else {
            create_notification(
                source:        'workflow',
                title:         $title,
                body:          $body,
                link:          $link,
                audience_type: 'all_admin',
                audience_id:   null,
                created_by:    null,
                expires_at:    $expires_at,
                external_guid: $guid,
                db:            $db
            );
        }
    } catch (\Throwable $e) {
        // Never let notification failure mask the original send failure.
        write_debug_log("create_email_failure_notification: could not create notification: " . $e->getMessage(), 'error');
    }
}

/**********************************
 * FUNCTION: SEND EMAIL IMMEDIATE *
 * Don't queue email, send now    *
 **********************************/
function send_email_immediate($name, $email, $subject, $body)
{
    $mail_settings = get_mail_settings();
    $transport = $mail_settings['phpmailer_transport'] ?? 'mail';
    $from_email = $mail_settings['phpmailer_from_email'] ?? '';
    $from_name = $mail_settings['phpmailer_from_name'] ?? '';
    $replyto_email = $mail_settings['phpmailer_replyto_email'] ?? $from_email;
    $replyto_name = $mail_settings['phpmailer_replyto_name'] ?? $from_name;
    $prepend = $mail_settings['phpmailer_prepend'] ?? '';
    $host = $mail_settings['phpmailer_host'] ?? '';
    $smtpautotls = $mail_settings['phpmailer_smtpautotls'] ?? 'true';
    $smtpauth = $mail_settings['phpmailer_smtpauth'] ?? 'false';
    $username = $mail_settings['phpmailer_username'] ?? '';
    $encryption = $mail_settings['phpmailer_smtpsecure'] ?? '';
    $port = $mail_settings['phpmailer_port'] ?? 25;

    if (!is_sendable_email_address($email)) {
        // A rejected/malformed address is a recoverable validation condition, not
        // a system error — warning, matching the pre-send guards that gate on the
        // same is_sendable_email_address() check.
        write_debug_log("Invalid email: $email", "warning");
        return false;
    }

    if (empty($subject) || empty($body)) {
        write_debug_log("Empty subject or body for email to $email", "error");
        return false;
    }

    // Decrypt password if Management Extra is enabled
    $password = $mail_settings['phpmailer_password'] ?? '';
    $management_extra_iv = get_setting("management_extra_iv");
    if ($management_extra_iv !== false && !empty($password)) {
        try {
            require_once(realpath(__DIR__ . "/../extras/management/index.php"));
            $iv = base64_decode($management_extra_iv);
            $password = openssl_decrypt($password, 'aes-256-cbc', MANAGEMENT_EXTRA_ENCRYPTION_KEY, 0, $iv);
            if (!$password) {
                write_debug_log("Failed to decrypt SMTP password for $username", "error");
                return false;
            }
        } catch (Exception $e) {
            write_debug_log("Exception decrypting SMTP password: " . $e->getMessage(), "error");
            return false;
        }
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from_email, $from_name);
        $mail->addReplyTo($replyto_email, $replyto_name);
        $mail->addAddress($email, $name);
        $mail->Subject = ($prepend ? $prepend . ' ' : '') . $subject;
        $mail->Sender = $from_email;
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        // Configure transport
        if ($transport === 'sendmail') {
            $mail->isSendmail();
        } elseif ($transport === 'smtp') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAutoTLS = ($smtpautotls !== 'false');
            $mail->SMTPKeepAlive = false;

            if ($smtpauth === 'true') {
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = $password;
                if ($encryption === 'tls') $mail->SMTPSecure = 'tls';
                elseif ($encryption === 'ssl') $mail->SMTPSecure = 'ssl';
            }
        }

        $mail->send();
        write_debug_log("Email successfully sent to $email with subject '$subject'", "info");
        return true;

    } catch (PHPMailer\PHPMailer\Exception $e) {
        write_debug_log("PHPMailer Exception sending email to $email: " . $e->getMessage(), "error");
        return false;
    } catch (Exception $e) {
        write_debug_log("General Exception sending email to $email: " . $e->getMessage(), "error");
        return false;
    }
}

/********************************
 * FUNCTION: PROCESS EMAIL TASK *
 ********************************/
function process_email_task($db, $task) {
    $payload = json_decode($task['payload'] ?? '', true);
    if (!is_array($payload) || !isset($payload['recipient_email'], $payload['recipient_name'])) {
        write_debug_log("Invalid email task payload: " . json_encode($task), 'error');
        $db->prepare("UPDATE queue_tasks SET status='failed', attempts=attempts+1, updated_at=NOW() WHERE id=?")
            ->execute([$task['id']]);
        return;
    }

    try {
        // Use the existing email logic, but move the PHPMailer creation here.
        send_email_immediate(
            $payload['recipient_name'],
            $payload['recipient_email'],
            $payload['subject'],
            $payload['body']
        );

        $db->prepare("UPDATE queue_tasks SET status='completed', updated_at=NOW() WHERE id=?")
            ->execute([$task['id']]);

    } catch (Exception $e) {
        $db->prepare("
            UPDATE queue_tasks 
            SET status='failed', attempts=attempts+1, updated_at=NOW() 
            WHERE id=?
        ")->execute([$task['id']]);

        write_debug_log("Email task failed: " . $e->getMessage(), 'error');
    }
}

?>