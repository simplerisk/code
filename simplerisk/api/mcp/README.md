# SimpleRisk MCP Server

An [MCP](https://modelcontextprotocol.io/) (Model Context Protocol) server that exposes SimpleRisk's GRC Context Graph and AI-governance tooling to MCP-capable clients (an MCP-aware LLM host, an agent framework, etc.). It is not a REST API — there is no OpenAPI/Swagger surface for it; this README is the reference.

## Endpoint

```
<base>/api/mcp/
```

`<base>` is the same SimpleRisk install root used by the v1/v2 REST APIs (e.g. `https://example.com/simplerisk`), so on a typical install the full URL is `https://example.com/simplerisk/api/mcp/`.

## Transport

Streamable HTTP, per the MCP SDK ([`logiscape/mcp-sdk-php`](https://github.com/logiscape/mcp-sdk-php), MIT-licensed, `Mcp\Server\McpServer::runHttp()`). The endpoint speaks JSON-RPC 2.0 over a single HTTP POST per SDK convention — point an MCP client's "Streamable HTTP" transport at the URL above.

## Authentication

Every request must carry a SimpleRisk API key in the `X-API-KEY` header:

```
X-API-KEY: <your-simplerisk-api-key>
```

This is the same key type used by the v2 REST API (**Admin → API Settings**). Authentication reuses the API Extra's `authenticate_key()` — session cookies and system tokens are not accepted; the MCP gate is API-key-only.

Beyond a valid key, the authenticated identity must also hold the **AI Context Access** permission (`ai_context_access`, under the "Artificial Intelligence" permission group in **Admin → User Roles**). This permission gates the entire MCP surface — every tool, read or write — on top of whatever record-level/team scoping already applies to that identity via the normal permission system.

### Error responses

| Condition | HTTP status | JSON-RPC error code |
|---|---|---|
| Missing/invalid API key | `401` | `-32001` |
| Valid key, missing `ai_context_access` permission | `403` | `-32002` |

Both are returned as a JSON-RPC 2.0 error object (`{"jsonrpc":"2.0","id":null,"error":{"code":...,"message":"..."}}`) — no MCP tool is ever reachable before both checks pass.

## Tools

All tools are scoped to the authenticated identity's existing SimpleRisk permissions and team/record-access rules — the MCP layer adds no god-mode access beyond what the API key's identity could already do through the normal application.

### `get_context(type, id, depth=1)`

Fetches a node from the SimpleRisk Context Graph — a GRC object such as a risk, control, or asset — plus its related neighbors up to `depth` relationship hops. Read-only.

| Arg | Type | Required | Description |
|---|---|---|---|
| `type` | string | yes | Context Graph node type, e.g. `"risk"`, `"control"`, `"asset"`. |
| `id` | integer | yes | The object ID within that type. |
| `depth` | integer | no (default `1`) | How many relationship hops to include around the node. |

### `get_org_profile()`

Fetches the organization's AI Context Profile — the asked, derived, and authoritative facts SimpleRisk uses to ground AI-assisted GRC reasoning. No arguments.

> **Authorization note (by design).** This tool returns the same organization-level grounding profile (sector, size band, frameworks-in-use, risk-appetite band — all organization configuration, not record-scoped data) to any identity holding `ai_context_access`. The standalone admin *configuration* endpoint (`GET /api/v2/ai/context`) reserves editing/viewing this data for admins, but the identical profile is already returned to non-admin, domain-permitted callers embedded in the `GET /api/v2/ai/context/{type}/{id}` context bundle (a deliberate design choice so an AI agent can read an object and its organizational grounding in one call). `ai_context_access` is a purpose-built grant an administrator issues specifically to enable AI-context tooling, so exposing that grounding profile to those identities is intentional and consistent with the context-bundle behavior — it is **not** a broadening of access to record-scoped or credentials-grade data.

### `propose_control_tests(control_id)`

Governed write tool: enqueues a background job that drafts AI-proposed control tests for a control. **Does not write GRC data directly** — the AI-drafted tests land in `ai_proposals` for a human to review and approve in SimpleRisk. Requires the `define_tests` permission in addition to `ai_context_access`, and requires the Control Test Generation AI capability to be enabled (**Admin → AI Settings**).

| Arg | Type | Required | Description |
|---|---|---|---|
| `control_id` | integer | yes | The control ID to propose AI-drafted tests for. |

### Discovery tools (read-only)

All discovery tools require the same `ai_context_access` gate as the other tools, and each additionally re-enforces the domain permission its object type needs — parity with the same check `get_context` applies when fetching that type: `governance` for control/framework tools, `compliance` for the test tool, `riskmanagement` for the risk tool. A caller lacking the domain permission gets `{"ok":false,"error":"forbidden"}`; discovery is never more permissive than fetch. On success they return `{ "ok": true, "items": [...], "meta": {...} }`. Every item leads with `type` + `id` (or `test_id` for tests) so it can be passed straight to `get_context` / `propose_control_tests`.

| Tool | Domain permission | Params | Returns |
|------|--------------------|--------|---------|
| `list_control_gaps` | `governance` | `framework_id?`, `limit?` | controls that are failing or below target maturity (`reason: fail` \| `maturity_gap`) |
| `list_tests_due` | `compliance` | `scope: upcoming\|overdue`, `limit?` | control tests due or overdue (team-scoped) |
| `list_highest_risks` | `riskmanagement` | `limit?` | open risks by calculated score (team-scoped; raw risk id) |
| `list_frameworks` | `governance` | — | active frameworks |
| `list_controls` | `governance` | `framework_id`, `limit?`, `offset?` | controls mapped to a framework (paged) |

Discovery is symmetric with fetch: team-scoped tools (`list_tests_due`, `list_highest_risks`) apply the same team-separation filter as `get_context`, so an agent can only discover objects it is allowed to open.
