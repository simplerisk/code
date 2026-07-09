<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/********************************
 * FUNCTION: WITH ALERT SESSION *
 ********************************/
/**
 * Run an alert read-modify-write against $_SESSION even when the request has
 * already released its session lock.
 *
 * To avoid holding the session row's `SELECT ... FOR UPDATE` lock for an entire
 * request (which serializes a page's parallel same-session AJAX and caused the
 * SR-1691 contention), add_session_check() / head.php / the api path call
 * session_write_close() as soon as the request is authenticated. $_SESSION
 * stays *readable* after that, but writes no longer persist. Alert writes
 * happen after that point (an action handler, or footer.php's get_alert()
 * clear), so they must briefly re-acquire the lock.
 *
 * If the session was started this request and then closed, this re-opens it
 * (re-acquiring the row lock and re-reading the latest stored alerts so a
 * concurrent same-session request's alerts aren't clobbered), runs $mutator,
 * then re-closes. If the session is still active (login flow, or before the
 * post-auth close) $mutator runs in place and persists at request end. In a
 * sessionless context (CLI/cron) it is a transparent no-op wrapper.
 *
 * use_cookies=0 on the re-open: the SID cookie was already emitted by the
 * initial session_start(), so we must not resend it (which would warn once
 * page output has begun). session_start() resolves the SID from the existing
 * SimpleRisk cookie.
 */
function with_alert_session(callable $mutator): void {
    $reopened = false;
    if (session_status() === PHP_SESSION_NONE
        && (session_id() !== '' || !empty($_COOKIE[session_name()]))) {
        @session_start(['use_cookies' => 0]);
        $reopened = true;
    }

    $mutator();

    if ($reopened) {
        session_write_close();
    }
}

/***********************
 * FUNCTION: SET ALERT *
 ***********************/
// Callers must NOT pre-escape $message — get_alert(true) escapes every message
// with escapeHtml() at read time, so pre-escaping would produce double-encoded output.
function set_alert($alert = false, $type = "good", $message = "") {

    // Build the new alert
    $new_alert = [
        'alert' => $alert,
        'alert_type' => $type,
        'alert_message' => $message
    ];

    // Append under the (re-acquired) session lock so concurrent same-session
    // requests don't lose each other's alerts. Reading the latest stored
    // alerts inside the locked window keeps this lost-write-safe.
    with_alert_session(function () use ($new_alert) {

        // If we have existing alerts in the session
        if (isset($_SESSION['alerts']) && is_array($_SESSION['alerts'])) {
            $alerts = $_SESSION['alerts'];
        } else {
            $alerts = [];
        }

        // Add the new alert to the alerts array
        array_push($alerts, $new_alert);

        // If a session is set
        if (isset($_SESSION)) {
            $_SESSION['alerts'] = $alerts;
        }
    });

    write_debug_log("Core: [set_alert]: Alert with type '{$type}' and message '{$message}' was added.", 'debug');

}

/***********************
 * FUNCTION: GET ALERT *
 ***********************/
function get_alert($returnHtml = false, $plainText = false) {

    global $escaper;

    if (isset($_SESSION['alerts']) && is_array($_SESSION['alerts'])) {

        // If it has to return the alert data...
        if ($plainText) {

            $result = array();
            
            // ...we build the array with the required format(also, escaping the messages)...
            foreach ($_SESSION['alerts'] as $alert) {
                array_push($result, $escaper->escapeHtml($alert['alert_message']));
            }
            
            clear_alert();

            // ...and return it as a JSON string.
            return implode(", ", $result);           

        } else if ($returnHtml) {
            
            $result = array();
            
            // ...we build the array with the required format(also, escaping the messages)...
            foreach ($_SESSION['alerts'] as $alert) {
                array_push($result, array(
                    'alert_type' => ($alert['alert_type'] == "good" ? "success" : "error"),
                    'alert_message' => $escaper->escapeHtml($alert['alert_message'])
                ));                
            }
            
            clear_alert();

            // ...and return it as a JSON string.
            return json_encode($result);           
            
        } else if (isset($_SESSION['alerts']) && !empty($_SESSION['alerts'])) {

            // Print the script into the html output that'll show the alerts
            echo "
                <script>
                    $( document ).ready(function() {
            ";
            
            foreach ($_SESSION['alerts'] as $alert) {

                echo "
                        toastr." . ($alert['alert_type'] == "good" ? "success" : "error") . "('{$escaper->escapeHtml($alert['alert_message'])}');
                "; 

            }
            
            echo "
                    });
                </script>
            ";

            foreach ($_SESSION['alerts'] as $alert) {
                echo "
                <div class='hide hidden-alert-message' data-type='" . ($alert['alert_type'] == "good" ? "success" : "error") . "'>
                    {$escaper->escapeHtml($alert['alert_message'])}
                </div>
                ";
            }
        }
    }

    // Clear the alert
    clear_alert();

}

/*************************
 * FUNCTION: CLEAR ALERT *
 *************************/
function clear_alert() {

    // Persist the clear under the (re-acquired) session lock — get_alert()
    // calls this at footer render, after the post-auth session_write_close(),
    // so a plain assignment would not persist and the flash alert would show
    // again on the next page load.
    with_alert_session(function () {
        if (isset($_SESSION)) {
            $_SESSION['alerts'] = array();
        }
    });

}


/*************************************
 * FUNCTION: SETUP ALERT REQUIREMENTS*
 *************************************/
function setup_alert_requirements($path_to_root = "") {

    global $escaper;

    if ($path_to_root) {

        if ($path_to_root[strlen($path_to_root)-1] !== '/') {
            $path_to_root .= '/';
        }

        $path_to_root = $escaper->escapeHtml($path_to_root);

    }

    echo "
        <script src='{$path_to_root}vendor/node_modules/toastr/build/toastr.min.js?" . current_version("app") . "' defer id='script_toastr'></script>
        <script src='{$path_to_root}js/simplerisk/alert-helper.js?" . current_version("app") . "' defer></script>
    ";

    $timeOut = get_setting("alert_timeout");

    if ($timeOut || $timeOut === "0") {

        $timeOut = (int)$timeOut;

        echo "
        <script>
            $('#script_toastr').on('load', function() {
                toastr.options.timeOut = " . ($timeOut * 1000) . ";
        ";

        if ($timeOut === 0) { //otherwise we're using the default 2 seconds
            echo "
                toastr.options.extendedTimeOut = 0;
            ";
        }

        echo "
            });
        </script>
        ";

    }

    echo "
        <link rel='stylesheet' href='{$path_to_root}vendor/node_modules/toastr/build/toastr.min.css?" . current_version("app") . "' />
        <style>
            .toast-top-right {
                top: 75px;
                right: 12px;
            }
            #toast-container > div {
                opacity:1;
            }
        </style>
    ";
}

?>