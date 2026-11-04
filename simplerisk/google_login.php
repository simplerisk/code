<?php

require_once __DIR__ . '/includes/google_oauth.php';

try {
    google_oauth_start_session();

    $config = google_oauth_config();

    // Generate unique values to protect the OAuth request.
    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(32));

    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_nonce'] = $nonce;



    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
        'prompt' => 'select_account',
    ];

    $authorization_url =
        'https://accounts.google.com/o/oauth2/v2/auth?' .
        http_build_query($params);

    google_oauth_redirect($authorization_url);

} catch (Throwable $e) {
    error_log('Google OAuth start error: ' . $e->getMessage());

    http_response_code(500);
    echo 'Google Login is not configured correctly. Please contact the administrator.';
}
