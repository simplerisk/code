<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/includes/functions.php'));
    require_once(realpath(__DIR__ . '/includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/includes/alerts.php'));
        
    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

        // Add various security headers
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
            {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        session_name('SimpleRisk');
        session_start();
    }

        // Include the language file
        require_once(language_file());

    // Log the user out
    logout();

    // Redirect to the index
    header( 'Location: index.php' );

?>
