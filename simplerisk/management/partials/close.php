<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../../includes/display.php'));
    require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../../includes/permissions.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');
    
    // Add various security headers
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");

    // If we want to enable the Content Security Policy (CSP) - This may break Chrome
    if (csp_enabled())
    {
        // Add the Content-Security-Policy header
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' *.highcharts.com *.googleapis.com *.gstatic.com *.jquery.com;");
    }

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

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

    // Enforce that the user has access to risk management
    enforce_permission_riskmanagement();

    // Check if the user has access to close risks
    if (!isset($_SESSION["close_risks"]) || $_SESSION["close_risks"] != 1)
    {
        $close_risks = false;

    // Display an alert
    set_alert(true, "bad", "You do not have permission to close risks.  Any attempts to close risks will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");
    }
    else $close_risks = true;

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
            // If Risk ID exists.
            if(check_risk_by_id($id)){
                $status = $lang["RiskDisplayPermission"];
            }
            // If Risk ID does not exist.
            else{
                $status = $lang["RiskIdDoesNotExist"];
            }
            $subject = "N/A";
            $calculated_risk = "0.0";
        }
    }

?>
<div class="row-fluid">
    <div class="well">
      <form name="close_risk" method="post" action="">
        <h4><?php echo $escaper->escapeHtml($lang['CloseRisk']); ?></h4>
        <?php echo $escaper->escapeHtml($lang['Reason']); ?>: <?php create_dropdown("close_reason"); ?><br />
        <label><?php echo $escaper->escapeHtml($lang['CloseOutInformation']); ?></label>
        <textarea name="note" cols="50" rows="3" id="note"></textarea>
        <div class="form-actions">
          <button type="submit" name="submit" class="btn btn-primary save-close-risk"><?php echo $escaper->escapeHtml($lang['Submit']); ?></button>
          <input class="btn" value="<?php echo $escaper->escapeHtml($lang['Reset']); ?>" type="reset">
        </div>
      </form>
    </div>
</div>

        <input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">
        <input type="hidden" id="_lang_reopen_risk" value="<?php echo $lang['ReopenRisk']; ?>">
        <input type="hidden" id="_lang_close_risk" value="<?php echo $lang['CloseRisk']; ?>">
