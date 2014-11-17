<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 16/11/14
 * Time: 18:35
 */

###
# Basic Old Stuff
######
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');


// Add various security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// If we want to enable the Content Security Policy (CSP) - This may break Chrome
if (CSP_ENABLED == "true") {
    // Add the Content-Security-Policy header
    header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
}

// Session handler is database
if (USE_DATABASE_FOR_SESSIONS == "true") {
    session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
}

// Start session
session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
session_start('LessRisk');

// Include the language file
require_once(language_file());

###
# Twig Template engine
######
require_once(realpath(__DIR__ . '/../vendor/twig/twig/lib/Twig/Autoloader.php'));

Twig_Autoloader::register();

$loader = new Twig_Loader_Filesystem(realpath(__DIR__ . '/../templates'));
$twig = new Twig_Environment($loader, array(
    'cache' => realpath(__DIR__ . '/../cache'),
));

require_once(realpath(__DIR__ . '/twigvars.php'));