<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/***********************
 * FUNCTION: SET ALERT *
 ***********************/
function set_alert($alert = false, $type = "good", $message = "")
{
    // create array to keep the alerts in
    $alerts = array();    

    // Get it from the session if there's already something in it
    if (isset($_SESSION['alerts']) && is_array($_SESSION['alerts'])) {
        $alerts = $_SESSION['alerts'];    
    }
    
    // Add the new alert
    array_push($alerts, array(
            'alert' => $alert, 
            'alert_type' => $type,
            'alert_message' => $message
        ));
    
    $_SESSION['alerts'] = $alerts;
}

/***********************
 * FUNCTION: GET ALERT *
 ***********************/
function get_alert($returnHtml = false)
{
    global $escaper;

    if (isset($_SESSION['alerts']) && is_array($_SESSION['alerts']))
    {
        // If it has to return the alert data...
        if($returnHtml){
            
            $result = array();
            
            // ...we build the array with the required format(also, escaping the messages)...
            foreach($_SESSION['alerts'] as $alert) {
                array_push($result, array(
                    'alert_type' => ($alert['alert_type'] == "good"?"success":"error"),
                    'alert_message' => $escaper->escapeHtml($alert['alert_message'])
                ));                
            }
            
            clear_alert();
            // ...and return it as a JSON string.
            return json_encode($result);           
            
        } elseif (isset($_SESSION['alerts']) && !empty($_SESSION['alerts'])) {
            // Print the script into the html output that'll show the alerts
            echo "
                <script>
                    $( document ).ready(function() {";
            
            foreach($_SESSION['alerts'] as $alert) {
                echo "
                        toastr." . ($alert['alert_type'] == "good"?"success":"error") . "('" . $escaper->escapeHtml($alert['alert_message']) . "');";            
            }
            
            echo "
                    });\n
                </script>";
        }
    }

    // Clear the alert
    clear_alert();
}

/*************************
 * FUNCTION: CLEAR ALERT *
 *************************/
function clear_alert()
{
    $_SESSION['alerts'] = array();
}


/*************************************
 * FUNCTION: SETUP ALERT REQUIREMENTS*
 *************************************/
function setup_alert_requirements($path_to_root = "")
{
    global $escaper;

    if ($path_to_root) {
        if($path_to_root[strlen($path_to_root)-1] !== '/') {
            $path_to_root .= '/';
        }
        $path_to_root = $escaper->escapeHtml($path_to_root);
    }

    echo "<script src='{$path_to_root}js/alerts/toastr.min.js'></script>\n";
    echo "<script src='{$path_to_root}js/alerts/alert-helper.js'></script>\n";
    $timeOut = get_setting("alert_timeout");
    if ($timeOut || $timeOut === "0") {
        $timeOut = (int)$timeOut;
        echo "<script>\n";
        echo "    toastr.options.timeOut = " . ($timeOut * 1000) . ";\n";
        if ($timeOut === 0) { //otherwise we're using the default 2 seconds
            echo "    toastr.options.extendedTimeOut = 0;\n";
        }
        echo "</script>\n";
    }

    echo "<link rel='stylesheet' href='{$path_to_root}css/toastr.min.css' />\n";
    echo "<style>\n";
    echo "    .toast-top-right {\n";
    echo "        top: 75px;\n";
    echo "        right: 12px;\n";
    echo "    }\n";
    echo "    #toast-container > div {\n";
    echo "        opacity:1;\n";
    echo "    }\n";
    echo "</style>\n";
}

?>
