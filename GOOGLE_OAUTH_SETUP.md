# Google OAuth Login – SimpleRisk

## Overview

This feature adds Google OAuth 2.0 login to SimpleRisk while
preserving the existing username and password authentication.

Users can sign in using their Google account if its verified
email address matches an existing eligible SimpleRisk account.

## 1. Google Cloud Configuration

1. Open https://console.cloud.google.com/
2. Create or select a Google Cloud project.
3. Configure the OAuth consent screen.
4. Select External as the audience if required.
5. Add your Google account under Test users while the application
   is in Testing mode.
6. Open Google Auth Platform → Clients.
7. Create an OAuth client with application type Web application.
8. Add this authorized redirect URI:

   http://localhost/simplerisk/google_callback.php

9. Create the client and copy the Client ID and Client Secret.

## 2. Local Configuration

Create this file:

simplerisk/includes/google_oauth.local.php

Add the following PHP configuration, replacing the placeholders
with your own Google OAuth credentials:

    <?php

    return [
        'client_id' => 'YOUR_GOOGLE_CLIENT_ID',
        'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
        'redirect_uri' => 'http://localhost/simplerisk/google_callback.php',
    ];

Important:
- Do not commit this file.
- Keep the Client Secret private.
- The local configuration file is excluded through .gitignore.

## 3. Requirements

- PHP with cURL support enabled.
- A working SimpleRisk installation and database.
- A Google Cloud OAuth web application client.
- Internet connectivity to Google's OAuth endpoints.

## 4. Run the Application

Start Apache and MySQL, then open:

http://localhost/simplerisk/

The login page provides:
- Existing username and password login.
- Continue with Google.

## 5. Authentication Flow

1. The user selects Continue with Google.
2. SimpleRisk generates OAuth state and nonce values.
3. The browser redirects to Google's authorization page.
4. Google authenticates the user and redirects to the callback.
5. SimpleRisk validates the OAuth state and exchanges the
   authorization code for tokens.
6. The Google identity is validated, including audience,
   issuer, expiration, nonce and verified email.
7. SimpleRisk looks for an existing eligible account matching
   the verified Google email.
8. SimpleRisk establishes the normal authenticated session.

## 6. Account and MFA Handling

- Google login does not automatically create SimpleRisk accounts.
- Only existing enabled, unlocked SimpleRisk accounts are eligible.
- The account email must match the verified Google email.
- Existing username and password login remains available.
- Existing SimpleRisk MFA requirements are preserved.

## 7. Testing

Test the following scenarios:

1. Sign in using a valid Google account linked to an eligible
   SimpleRisk account.
2. Confirm successful redirection to the SimpleRisk dashboard.
3. Sign in using the existing username and password method.
4. Try a Google account whose email does not match an eligible
   SimpleRisk account.
5. Cancel the Google sign-in process.
6. Verify that OAuth failures do not expose credentials or
   sensitive technical details to the user.
7. If MFA is enabled, verify that the existing MFA flow is
   followed.

## 8. Security Considerations

- OAuth state and nonce values are generated using secure random
  bytes and checked during callback processing.
- State and nonce values are single-use.
- HTTPS certificate verification is enabled for Google requests.
- Google email verification is required.
- OAuth credentials are stored locally and excluded from Git.
- Detailed errors are written to the server error log while
  generic errors are displayed to users.
