<?php

/**
 * MCP Authentication Configuration
 * 
 * (c) 2025 Logiscape LLC <https://logiscape.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    logiscape/mcp-sdk-php
 * @author     Josh Abbott <https://joshabbott.com>
 * @copyright  Logiscape LLC
 * @license    MIT License
 * @link       https://github.com/logiscape/mcp-sdk-php
 */

/**
 * Authentication Algorithm Configuration
 * 
 * Choose ONE of the following configurations:
 * 
 * Option A: HS256 (Symmetric) - Uses a shared secret
 *   - Simpler setup, good for testing
 *   - Both issuer and validator share the same secret
 *   - Use MCP_JWT_SECRET with your shared secret
 * 
 * Option B: RS256 (Asymmetric) with JWKS - Uses public/private key pair
 *   - More secure, recommended for production
 *   - Used by Auth0, Okta, Keycloak, and most OAuth providers
 *   - Validator fetches public keys from JWKS endpoint
 *   - Use MCP_JWKS_URI to point to the authorization server's JWKS endpoint
 */

// === ALGORITHM SELECTION ===
// Set to 'HS256' for shared secret or 'RS256' for JWKS-based validation
define('MCP_JWT_ALGORITHM', 'RS256');

// === COMMON CONFIGURATION ===
// The authorization server's issuer URL (must match 'iss' claim in tokens)
// For Auth0: https://YOUR_TENANT.auth0.com/
define('MCP_AUTH_ISSUER', 'https://example_auth_server.com/');

// Your MCP server's resource identifier (must match 'aud' claim in tokens)
// If using Auth0 this should match the API Identifier
define('MCP_RESOURCE_ID', 'https://yoursite.com/server_auth.php');

// === HS256 CONFIGURATION ===
// Only used when MCP_JWT_ALGORITHM is 'HS256'
// For Auth0 HS256: Use your Application's Client Secret
define('MCP_JWT_SECRET', 'your-hs256-secret-here');

// === RS256 CONFIGURATION ===
// Only used when MCP_JWT_ALGORITHM is 'RS256'
// For Auth0: https://YOUR_TENANT.auth0.com/.well-known/jwks.json
define('MCP_JWKS_URI', 'https://example_auth_server.com/.well-known/jwks.json');