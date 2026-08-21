<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * SimpleRisk MCP server adapter (Spec 2 Plan C, Task 4).
 *
 * This is the ONLY file in SimpleRisk that references logiscape/mcp-sdk-php
 * (Mcp\...) types. It builds an Mcp\Server\McpServer, registers the
 * governed MCP tools, and serves them over the SDK's Streamable HTTP
 * transport (McpServer::runHttp() -- see
 * vendor/logiscape/mcp-sdk-php/src/Server/McpServer.php). All tool
 * BUSINESS logic lives in mcp_tools.php (Task 3); this adapter only wires
 * tool names/descriptions/JSON-Schemas to those functions and never
 * reimplements their behavior. Should the community SDK ever need to be
 * swapped (e.g. for an official Anthropic PHP SDK), this is the one file
 * that changes.
 *
 * API-key authentication and the `ai_context_access` permission gate are
 * NOT implemented here -- they live in the Task-5 HTTP entry point
 * (simplerisk/api/mcp/index.php), which authenticates and authorizes the
 * request BEFORE constructing/running this class. The SDK's own
 * withAuth()/TokenValidatorInterface hook (McpServer::withAuth(), for
 * bearer-token/OAuth resource servers) is deliberately not used: SimpleRisk
 * authenticates via API key + check_permission(), not OAuth, so wiring
 * withAuth() here would stand up a second, redundant gate ahead of the one
 * the entry point already enforces.
 */

require_once(realpath(__DIR__ . '/../mcp_tools.php'));
require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));

use Mcp\Server\McpServer;

/**
 * Thin SDK adapter: build + register tools + serve. No GRC business logic.
 */
class SimpleRiskMcpServer
{
    /** Server name advertised to MCP clients during initialization. */
    private const SERVER_NAME = 'simplerisk-mcp';

    /**
     * Build the McpServer instance and register the governed tools,
     * without serving/running it. Split out from run() so a construction
     * smoke test can exercise registration without blocking on a live HTTP
     * request (McpServer::runHttp() reads/writes the current HTTP request
     * and never returns under a real request).
     *
     * Tool callback signature per the installed SDK
     * (Mcp\Server\McpServer::tool(); grep the symbol rather than trusting a
     * line number -- an SDK bump moves them, as v1.7.4 -> v2.0.0 did):
     *   tool(string $name, string $description, callable $callback,
     *        ?string $title = null, ?array $icons = null,
     *        ?array $outputSchema = null, ?array $inputSchema = null,
     *        string $taskSupport = TaskSupport::FORBIDDEN, ...): self
     *
     * Every call below passes NAMED arguments, so the trailing parameters
     * v2.0.0 added -- and its reordering of $inputSchema behind $title /
     * $icons / $outputSchema -- do not affect this file.
     *
     * Callbacks here return json_encode() strings rather than raw arrays:
     * per the SDK's tool-result handling (the McpServerException::invalidToolResult
     * throw sites in src/Server/McpServer.php), an array/object result is
     * only accepted when $outputSchema is set
     * (it's used to populate structuredContent); without it, a non-string
     * result throws McpServerException::invalidToolResult(). Returning the
     * JSON-encoded string keeps every tool result plain TextContent and
     * avoids taking on structured-output schema maintenance for Task 4.
     */
    public function build(): McpServer
    {
        $server = new McpServer(self::SERVER_NAME);

        $server->tool(
            name: 'get_context',
            description: 'Fetch a node from the SimpleRisk Context Graph (a GRC object such as a risk, control, or asset) plus its first-order related neighbors. Read-only; results are scoped to the authenticated identity.',
            callback: static function (string $type, int $id): string {
                return json_encode(mcp_tool_get_context($type, $id), JSON_UNESCAPED_SLASHES);
            },
            inputSchema: [
                'properties' => [
                    'type' => [
                        'type' => 'string',
                        'description' => 'Context Graph node type, e.g. "risk", "control", or "asset".',
                    ],
                    'id' => [
                        'type' => 'integer',
                        'description' => 'The object ID within that type.',
                    ],
                ],
                'required' => ['type', 'id'],
            ],
        );

        $server->tool(
            name: 'get_org_profile',
            description: 'Fetch the organization Context Profile: the asked, derived, and authoritative facts SimpleRisk uses to ground AI-assisted GRC reasoning for the authenticated identity\'s organization.',
            callback: static function (): string {
                return json_encode(mcp_tool_get_org_profile(), JSON_UNESCAPED_SLASHES);
            },
        );

        $server->tool(
            name: 'propose_control_tests',
            description: 'Governed write tool: enqueue a background job that drafts AI-proposed control tests for a control. Never writes GRC data directly -- proposals land in ai_proposals for a human to review and approve.',
            callback: static function (int $control_id): string {
                return json_encode(mcp_tool_propose_control_tests($control_id), JSON_UNESCAPED_SLASHES);
            },
            inputSchema: [
                'properties' => [
                    'control_id' => [
                        'type' => 'integer',
                        'description' => 'The control ID to propose AI-drafted tests for.',
                    ],
                ],
                'required' => ['control_id'],
            ],
        );

        $server->tool(
            name: 'list_control_gaps',
            description: 'List controls that need attention — failing (status = Fail) or below their target maturity — with the control id to feed get_context/propose_control_tests. Read-only.',
            callback: static function (?int $framework_id = null, int $limit = 25): string {
                return json_encode(mcp_tool_list_control_gaps($framework_id, $limit), JSON_UNESCAPED_SLASHES);
            },
            inputSchema: [
                'properties' => [
                    'framework_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: restrict to controls mapped to this framework id.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows (default 25, capped at 100).',
                    ],
                ],
            ],
        );

        $server->tool(
            name: 'list_tests_due',
            description: 'List control tests that are due — scope "upcoming" (due today or later) or "overdue" (past due) — with the test id and its control id. Read-only; scoped to the caller\'s teams.',
            callback: static function (string $scope = 'upcoming', int $limit = 25): string {
                return json_encode(mcp_tool_list_tests_due($scope, $limit), JSON_UNESCAPED_SLASHES);
            },
            inputSchema: [
                'properties' => [
                    'scope' => [
                        'type' => 'string',
                        'enum' => ['upcoming', 'overdue'],
                        'description' => 'Which tests to list: "upcoming" (due today or later) or "overdue" (past due).',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows (default 25, capped at 100).',
                    ],
                ],
                'required' => ['scope'],
            ],
        );

        $server->tool(
            name: 'list_highest_risks',
            description: 'List open risks ranked by calculated risk score, with each risk id (to feed get_context) plus subject, status, and owner. Read-only; scoped to the caller\'s teams.',
            callback: static function (int $limit = 25): string {
                return json_encode(mcp_tool_list_highest_risks($limit), JSON_UNESCAPED_SLASHES);
            },
            inputSchema: [
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows (default 25, capped at 100).',
                    ],
                ],
            ],
        );

        $server->tool(
            name: 'list_frameworks',
            description: 'List the active compliance/control frameworks (id + name) so an agent can walk the control catalog. Read-only.',
            callback: static function (int $limit = 100): string {
                return json_encode(mcp_tool_list_frameworks($limit), JSON_UNESCAPED_SLASHES);
            },
            inputSchema: [
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows (default 100, capped at 100).',
                    ],
                ],
            ],
        );

        $server->tool(
            name: 'list_controls',
            description: 'List the controls mapped to a framework (paged) with each control id, short name, family, and maturity. Read-only.',
            callback: static function (int $framework_id, int $limit = 25, int $offset = 0): string {
                return json_encode(mcp_tool_list_controls($framework_id, $limit, $offset), JSON_UNESCAPED_SLASHES);
            },
            inputSchema: [
                'properties' => [
                    'framework_id' => [
                        'type' => 'integer',
                        'description' => 'The framework id whose controls to list (from list_frameworks).',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows (default 25, capped at 100).',
                    ],
                    'offset' => [
                        'type' => 'integer',
                        'description' => 'Row offset for paging a large catalog (default 0).',
                    ],
                ],
                'required' => ['framework_id'],
            ],
        );

        return $server;
    }

    /**
     * Serve the MCP server over Streamable HTTP
     * (Mcp\Server\McpServer::runHttp()). This
     * is the live entry point invoked by the Task-5 HTTP entry point AFTER
     * it has authenticated the API key and enforced the ai_context_access
     * permission gate -- runHttp() reads the current HTTP request and
     * streams the JSON-RPC response; it does not return under a real
     * request, which is why the construction smoke test calls build()
     * instead of run().
     */
    public function run(): void
    {
        $this->build()->runHttp();
    }
}
