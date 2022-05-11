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

    // Include Laminas Escaper for HTML Output Encoding
    $escaper = new Laminas\Escaper\Escaper('utf-8');

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
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
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

<div class="row-fluid">
    <div class="well">
        <h4><?php echo $escaper->escapeHtml($lang['ReviewHistory']); ?></h4>
        <?php get_reviews($id); ?>
    </div>
</div>
