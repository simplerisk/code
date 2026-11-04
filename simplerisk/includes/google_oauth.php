<?php

/**
 * Shared Google OAuth helpers for SimpleRisk.
 */

// Load SimpleRisk's normal application dependencies.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/authenticate.php';
require_once __DIR__ . '/alerts.php';
require_once __DIR__ . '/extras.php';
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Load local Google credentials.
 * This file is ignored by Git and must never be committed.
 */
function google_oauth_config()
{
    $config_file = __DIR__ . '/google_oauth.local.php';

    if (!file_exists($config_file)) {
        throw new RuntimeException(
            'Google OAuth is not configured. Create includes/google_oauth.local.php.'
        );
    }

    $config = require $config_file;

    if (
        !is_array($config) ||
        empty($config['client_id']) ||
        empty($config['client_secret']) ||
        empty($config['redirect_uri'])
    ) {
        throw new RuntimeException('Google OAuth configuration is incomplete.');
    }

    return $config;
}

/**
 * Start the existing SimpleRisk session.
 */
function google_oauth_start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (use_database_for_sessions()) {
        SimpleRiskSessionHandler::register();
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('SimpleRisk');

    if (function_exists('sess_gc')) {
        sess_gc(1440);
    }

    session_start();

    if (function_exists('add_security_headers')) {
        add_security_headers();
    }
}

/**
 * Find an existing, enabled, unlocked SimpleRisk account by email.
 * LDAP and SAML accounts are deliberately excluded.
 */
function google_oauth_find_user_by_email($email)
{
    $email = trim($email);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $db = db_open();

    try {
        $stmt = $db->prepare("
            SELECT username
            FROM `user`
            WHERE enabled = 1
              AND lockout = 0
              AND type = 'simplerisk'
              AND LOWER(CONVERT(email USING utf8mb4)) = LOWER(:email)
            LIMIT 1
        ");

        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ? $user['username'] : false;
    } finally {
        db_close($db);
    }
}

/**
 * Redirect and stop execution.
 */
function google_oauth_redirect($url)
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header('Location: ' . $url);
    exit;
}
