<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/includes/functions.php'));
require_once(realpath(__DIR__ . '/includes/authenticate.php'));
require_once(realpath(__DIR__ . '/includes/display.php'));
require_once(realpath(__DIR__ . '/includes/alerts.php'));
require_once(realpath(__DIR__ . '/includes/extras.php'));
require_once(realpath(__DIR__ . '/vendor/autoload.php'));

global $escaper, $lang, $current_app_version;
// Include Laminas Escaper for HTML Output Encoding
// $escaper = new Laminas\Escaper\Escaper('utf-8');
$escaper = new simpleriskEscaper();


// Add various security headers
add_security_headers();

add_session_check($permissions ?? []);

// Include the CSRF Magic library
include_csrf_magic();

// Release the session lock now that the request is authenticated and CSRF is
// initialized. The DB session handler holds a row-level FOR UPDATE lock from
// read() until the session is written/closed; holding it for the whole page
// render serializes a page's parallel same-session AJAX (and concurrent tabs)
// on the one session row, which under load piled up to 50-110s waits and 500s
// (SR-1691). $_SESSION stays readable for the rest of the render; the few
// paths that still write it (set_alert/clear_alert) re-acquire the lock
// briefly via with_alert_session().
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Include the SimpleRisk language file
require_once(language_file());

// Set a global variable for the current app version, so we don't have to call a function every time
$current_app_version = current_version("app");

// TODO remove it oncwe every page is updated to use the render_header_and_sidebar() function
$active_sidebar_submenu = $active_sidebar_submenu ?? '';
?>