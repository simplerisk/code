<?php

require_once __DIR__ . '/includes/google_oauth.php';

/**
 * Make a secure HTTPS request to a Google endpoint.
 */
function google_oauth_request($url, $post_fields = null)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($post_fields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Google request failed: ' . $error);
    }

    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        $error_data = json_decode($response, true);

        $google_error = is_array($error_data)
            ? ($error_data['error'] ?? 'unknown_error')
            : 'unknown_error';

        $google_description = is_array($error_data)
            ? ($error_data['error_description'] ?? 'No description provided')
            : 'No description provided';

        throw new RuntimeException(
            'Google returned HTTP status ' . $status .
            ': ' . $google_error . ' - ' . $google_description
        );
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException('Invalid response from Google.');
    }

    return $data;
}

try {
    google_oauth_start_session();

    $config = google_oauth_config();

    // Check the OAuth state to prevent login CSRF.
    $expected_state = $_SESSION['google_oauth_state'] ?? '';
    $expected_nonce = $_SESSION['google_oauth_nonce'] ?? '';

    $received_state = $_GET['state'] ?? '';

    // Make these values single-use.
    unset(
        $_SESSION['google_oauth_state'],
        $_SESSION['google_oauth_nonce']
    );

    if (
        !is_string($received_state) ||
        $expected_state === '' ||
        !hash_equals($expected_state, $received_state)
    ) {
        throw new RuntimeException('Invalid OAuth state.');
    }

    if (!empty($_GET['error'])) {
        throw new RuntimeException('Google sign-in was cancelled or denied.');
    }

    $code = $_GET['code'] ?? '';

    if (!is_string($code) || $code === '') {
        throw new RuntimeException('Authorization code is missing.');
    }

    // Exchange the authorization code for Google's tokens.
    $tokens = google_oauth_request(
        'https://oauth2.googleapis.com/token',
        [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]
    );

    if (empty($tokens['id_token'])) {
        throw new RuntimeException('Google did not return an ID token.');
    }

    // Ask Google to validate the ID token and return its claims.
    $identity = google_oauth_request(
        'https://oauth2.googleapis.com/tokeninfo?' .
        http_build_query(['id_token' => $tokens['id_token']])
    );

    $issuer = $identity['iss'] ?? '';
    $email = $identity['email'] ?? '';
    $email_verified = filter_var(
        $identity['email_verified'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    );

    // Validate the token's audience, issuer, expiry and nonce.
    if (($identity['aud'] ?? '') !== $config['client_id']) {
        throw new RuntimeException('Google token audience did not match.');
    }

    if (!in_array(
        $issuer,
        ['accounts.google.com', 'https://accounts.google.com'],
        true
    )) {
        throw new RuntimeException('Invalid Google token issuer.');
    }

    if ((int)($identity['exp'] ?? 0) <= time()) {
        throw new RuntimeException('Google ID token has expired.');
    }

    if (
        $expected_nonce === '' ||
        !isset($identity['nonce']) ||
        !hash_equals($expected_nonce, $identity['nonce'])
    ) {
        throw new RuntimeException('Invalid Google login nonce.');
    }

    if (!$email_verified || empty($identity['sub'])) {
        throw new RuntimeException('Google email is not verified.');
    }

    // Match only an existing enabled, unlocked SimpleRisk account.
    $username = google_oauth_find_user_by_email($email);

    if ($username === false) {
        throw new RuntimeException(
            'No eligible SimpleRisk account matches this Google email.'
        );
    }

    // Establish the normal SimpleRisk user session.
    create_simplerisk_instance_id();
    set_user_permissions($username);
    simplerisk_license_check();

    $_SESSION['base_url'] = get_base_url();

    // Preserve SimpleRisk's existing MFA requirements.
    $multi_factor = (int)get_multi_factor_for_uid();

    if ($multi_factor === 0) {
        grant_access();
        select_redirect();
        exit;
    }

    if ($multi_factor === 1) {
        if (is_mfa_verified_for_uid()) {
            $_SESSION['access'] = 'mfa';
        } else {
            $_SESSION['access'] = 'mfa_verify';
        }

        google_oauth_redirect('index.php');
    }

    throw new RuntimeException('Unsupported multi-factor authentication setting.');

} catch (Throwable $e) {
    error_log('Google OAuth callback error: ' . $e->getMessage());

    http_response_code(400);
    echo 'Google Login failed. Please return to SimpleRisk and try again.';
}
