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
    // Write the alert to the session
    $_SESSION['alert'] = $alert;
    $_SESSION['alert_type'] = $type;
    $_SESSION['alert_message'] = $message;
}

/***********************
 * FUNCTION: GET ALERT *
 ***********************/
function get_alert($returnHtml = false)
{
    global $escaper;
    
    $html = "";
    
    if (isset($_SESSION['alert']) && $_SESSION['alert'] == true)
    {
        if ($_SESSION['alert_type'] == "good")
        {
            $html .= "
                <div id=\"alert\" class=\"container-fluid\">
                    <div class=\"row-fluid\">
                        <div class=\"span10 greenalert\"><span><i class=\"fa fa-check\"></i>" . $escaper->escapeHtml($_SESSION['alert_message']) . "</span></div>
                    </div>
                </div>
            ";
        }
        else if ($_SESSION['alert_type'] == "bad")
        {
            $html .= "
                <div id=\"alert\" class=\"container-fluid\">
                    <div class=\"row-fluid\">
                        <div class=\"span10 redalert\"><span><i class=\"fa fa-check\"></i>" . $escaper->escapeHtml($_SESSION['alert_message']) . "</span></div>
                    </div>
                </div>
            ";
        }
    }

    // Clear the alert
    clear_alert();
    if($returnHtml){
        return $html;
    }else{
        echo $html;
    }
}

/*************************
 * FUNCTION: CLEAR ALERT *
 *************************/
function clear_alert()
{
    $_SESSION['alert'] = false;
    $_SESSION['alert_type'] = "";
    $_SESSION['alert_message'] = "";
}

?>
