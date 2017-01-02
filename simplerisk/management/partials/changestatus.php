<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../../includes/display.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");

    // If we want to enable the Content Security Policy (CSP) - This may break Chrome
    if (CSP_ENABLED == "true")
    {
            // Add the Content-Security-Policy header
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
    }

    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
    session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        if (!isset($_SESSION))
        {
        	session_name('SimpleRisk');
        	session_start();
        }

    // Include the language file
    require_once(language_file());
    
    global $lang;

    require_once(realpath(__DIR__ . '/../../includes/csrf-magic/csrf-magic.php'));

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
        header("Location: ../../index.php");
        exit(0);
    }

    // Check if a risk ID was sent
    if (isset($_GET['id']) || isset($_POST['id']))
    {
        if (isset($_GET['id']))
        {
                // Test that the ID is a numeric value
                $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);
        }
        else if (isset($_POST['id']))
        {
                // Test that the ID is a numeric value
                $id = (is_numeric($_POST['id']) ? (int)$_POST['id'] : 0);
        }

// If team separation is enabled
        if (team_separation_extra())
        {
                //Include the team separation extra
                require_once(realpath(__DIR__ . '/../../extras/separation/index.php'));

                // If the user should not have access to the risk
                if (!extra_grant_access($_SESSION['uid'], $id))
                {
                        // Redirect back to the page the workflow started on
                        header("Location: " . $_SESSION["workflow_start"]);
                        exit(0);
                }
        }

        // Get the details of the risk
        $risk = get_risk_by_id($id);

        // If the risk was found use the values for the risk
        if (count($risk) != 0)
        {
                $status = $risk[0]['status'];
                $subject = $risk[0]['subject'];
                $calculated_risk = $risk[0]['calculated_risk'];
        }
        // If the risk was not found use null values
        else
        {
                $status = "Risk ID Does Not Exist";
                $subject = "N/A";
                $calculated_risk = "0.0";
        }
    }

    // Check if the status was updated and the user has the ability to modify the risk
    if (isset($_POST['update_status']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
    {
        $status_id = (int)$_POST['status'];

        // Get the name associated with the status
        $status = get_name_by_value("status", $status_id);

        // Display an alert
                set_alert(true, "good", "Your risk status has been successfully changed.");

        // Check that the id is a numeric value
        if (is_numeric($id))
        {
            // Update the status of the risk
            update_risk_status($id, $status);

            // Create the redirection location
            $url = "../view.php?id=" . $id;

            // Redirect to risk view page
            header("Location: " . $url);
        }
    }
?>

<div class="row-fluid">
    <div class="well">
      <form name="add_comment" method="post" action="">
        <?php
            echo $escaper->escapeHtml($lang['SetRiskStatusTo']);
            echo "&nbsp;&nbsp;";
            create_dropdown("status");
            echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Update']) . "\" name=\"update_status\" />\n";
        ?>
      </form>
    </div>
</div>

        <input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">
        <input type="hidden" id="_lang_reopen_risk" value="<?php echo $lang['ReopenRisk']; ?>">
        <input type="hidden" id="_lang_close_risk" value="<?php echo $lang['CloseRisk']; ?>">
