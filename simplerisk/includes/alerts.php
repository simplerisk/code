<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/***********************
 * FUNCTION: SET ALERT *
 ***********************/
function set_alert($alert = false, $type = "good", $message = "") {

    // If we have existing alerts in the session
    if (isset($_SESSION['alerts']) && is_array($_SESSION['alerts'])) {

        // Create an alerts array with the existing alerts
        $alerts = $_SESSION['alerts'];    

    // Otherwise create an empty alerts array
    } else {
        $alerts = [];
    }

    // Create the alert
    $alert = [
        'alert' => $alert,
        'alert_type' => $type,
        'alert_message' => $message
    ];
    
    // Add the new alert to the alerts array
    array_push($alerts, $alert);

    // If a session is set
    if (isset($_SESSION)) {

        // Update the session alerts to include all alerts
        $_SESSION['alerts'] = $alerts;

    }

    write_debug_log("Core: [set_alert]: Alert with type '{$type}' and message '{$message}' was added.");

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

    $_SESSION['alerts'] = array();
    
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