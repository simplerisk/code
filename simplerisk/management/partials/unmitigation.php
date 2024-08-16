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

?>
<div class="card-body my-2 border">
    <form name="unmitigation" method="post" action="">
        <button type="submit" name="submit" class="btn btn-submit save-unmitigation-risk"><?php echo $escaper->escapeHtml($lang['ResetMitigations']); ?></button>
    </form>
</div>
<input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">
<input type="hidden" id="_lang_reopen_risk" value="<?php echo $escaper->escapeHtml($lang['ReopenRisk']); ?>">
<input type="hidden" id="_lang_close_risk" value="<?php echo $escaper->escapeHtml($lang['CloseRisk']); ?>">