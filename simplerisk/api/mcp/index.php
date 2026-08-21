<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * SimpleRisk MCP server HTTP entry point (Spec 2 Plan C, Task 5).
 *
 * Bootstraps SimpleRisk core, authenticates the caller by API key (reusing
 * the API Extra's authenticate_key() -- see the note below), gates on the
 * 'ai_context_access' permission (added in Task 2), and only THEN hands off
 * to the MCP SDK adapter (Task 4, includes/Mcp/SimpleRiskMcpServer.php).
 * No MCP tool is ever reachable before both the auth check and the
 * permission gate pass.
 *
 * Why authenticate_key() over api_v2_is_authenticated(): MCP clients are
 * machine integrations (an MCP-capable LLM host), not browser sessions --
 * api_v2_is_authenticated()'s session-cookie and system-token fallbacks
 * exist for the REST API's mixed browser/integration callers and don't
 * apply here. Key-only auth keeps the MCP gate to one deterministic path:
 * a valid X-API-KEY, or 401. authenticate_key() (extras/api/index.php)
 * fully populates $_SESSION -- uid, admin flag, and one boolean per
 * permission (including ai_context_access) -- so the subsequent
 * check_permission() gate reads real per-identity state, not a stub.
 */

// Core bootstrap chain (mirrors simplerisk/api/v2/index.php:16-23, adapted
// for this file's depth: __DIR__ is simplerisk/api/mcp, so ../../ reaches
// the simplerisk/ root).
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/permissions.php')); // check_permission() is called directly below
require_once(realpath(__DIR__ . '/../../includes/api.php')); // core API helpers; also force-loads $lang (language_file(true)) before session auth
require_once(realpath(__DIR__ . '/../../includes/services.php'));
require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));

// MCP-specific includes: tool-handler functions (Task 3) + the SDK adapter (Task 4).
require_once(realpath(__DIR__ . '/../../includes/mcp_tools.php'));
require_once(realpath(__DIR__ . '/../../includes/Mcp/SimpleRiskMcpServer.php'));

// Add security headers. content_type is left off (false): the MCP SDK's
// runHttp() owns the response Content-Type for a successful dispatch, and
// the auth/gate failure paths below set their own 'application/json'
// header explicitly. CSP is left off (false), matching the v2 API's JSON
// endpoint convention -- there is no HTML response for a CSP to protect.
add_security_headers(true, true, true, false, false);

// Include the language file. By this point includes/api.php has already
// force-loaded $lang via language_file(true) regardless of session state,
// so $lang['...'] lookups below are safe even for an unauthenticated caller.
require_once(language_file());

// -----------------------------------------------------------------------
// Authenticate: API key only, via the API Extra's authenticate_key().
// -----------------------------------------------------------------------
$mcp_authenticated = false;

if (function_exists('api_extra') && api_extra())
{
    // Pulls in authenticate_key() -- defined in extras/api/index.php,
    // required transitively by extras/api/includes/api.php.
    $api_extra_includes = realpath(__DIR__ . '/../../extras/api/includes/api.php');

    if ($api_extra_includes !== false)
    {
        require_once($api_extra_includes);
    }

    if (function_exists('authenticate_key') && authenticate_key() !== false)
    {
        $mcp_authenticated = true;
    }
}

if (!$mcp_authenticated)
{
    write_debug_log("[MCP] Unauthenticated access attempt to the SimpleRisk MCP server.", "notice");

    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => null,
        'error' => [
            'code' => -32001,
            'message' => $lang['UnauthenticatedAccessInAPI'],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// -----------------------------------------------------------------------
// Gate: the 'ai_context_access' permission (Task 2). Every MCP tool --
// read or governed-write -- requires this on top of a valid API key.
// -----------------------------------------------------------------------
if (!check_permission('ai_context_access'))
{
    // check_permission() already write_debug_log()s the denial at 'info'.
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => null,
        'error' => [
            'code' => -32002,
            'message' => $lang['UnauthorizedAccessInAPI'],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// -----------------------------------------------------------------------
// Dispatch: hand off to the MCP SDK adapter. run() -> McpServer::runHttp()
// reads php://input, streams the JSON-RPC response, and does not return
// under a real HTTP request.
// -----------------------------------------------------------------------
(new SimpleRiskMcpServer())->run();
