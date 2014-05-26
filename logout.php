<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/includes/functions.php'));
	require_once(realpath(__DIR__ . '/includes/authenticate.php'));

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
                header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
        }

	// Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
	}

	// Start session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
	session_start('SimpleRisk');

        // Include the language file
        require_once(language_file());

	// Audit log
	$risk_id = 1000;
	$message = "Username \"" . $_SESSION['user'] . "\" logged out successfully.";
	write_log($risk_id, $_SESSION['uid'], $message);

	// Deny access
	$_SESSION["access"] = "denied";

	// Reset the session data
	$_SESSION = array();

	// Send a Set-Cookie to invalidate the session cookie
	if (ini_get("session.use_cookies"))
	{
        	$params = session_get_cookie_params();
        	setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
	}

	// Destroy the session
	session_destroy();

	// Redirect to the index
	header( 'Location: index.php' );

?>
