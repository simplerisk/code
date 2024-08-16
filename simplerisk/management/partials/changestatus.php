<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../../includes/display.php'));
    require_once(realpath(__DIR__ . '/../../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

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

        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    // Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());
    
    global $lang;

    csrf_init();

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
    {
        header("Location: ../../index.php");
        exit(0);
    }

    // Enforce that the user has access to risk management
    enforce_permission("riskmanagement");

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
<div class="card-body my-2 border">
    <form name="add_comment" method="post" action="">
        <div class="row">
            <div class="col-6">
    <?php
        echo "
                <div class='row align-items-center'>
                    <div class='col-4'>
                        <label>" . $escaper->escapeHtml($lang['SetRiskStatusTo']) . ":</label>
                    </div>
        ";

        $options = get_options_from_table('status');
        array_unshift($options, ['value'=>'', 'name'=>'--']);

        echo "
                    <div class='col-8'>
                        <select id='status' name='status' class='form-field form-select'>
        ";

        foreach ($options as $key => $option)
        {
            if (!(isset($_SESSION["close_risks"]) && $_SESSION["close_risks"] == 1) && $option['name'] === "Closed") continue;

            echo "
                            <option value='" . $escaper->escapeHtml($option['value']) . "'>" . $escaper->escapeHtml($option['name']) . "</option>
            ";
        }

        echo "
                        </select>
                    </div>
                </div>
            </div>
            <div class='col-6'>
                <input type='submit' class='btn btn-submit' value='" . $escaper->escapeHtml($lang['Update']) . "' name='update_status' />
            </div>
        ";
    ?>
        </div>
    </form>
</div>
<input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">
<input type="hidden" id="_lang_reopen_risk" value="<?php echo $escaper->escapeHtml($lang['ReopenRisk']); ?>">
<input type="hidden" id="_lang_close_risk" value="<?php echo $escaper->escapeHtml($lang['CloseRisk']); ?>">