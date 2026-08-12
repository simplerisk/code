<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * @OA\Get(
 *     path="/ai/recommendations",
 *     summary="Retrieve recommendations from Anthropic",
 *     operationId="artificialIntelligenceRecommendations",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="Anthropic recommendations",
 *     ),
 *     @OA\Response(
 *       response=503,
 *       description="SERVICE UNAVAILABLE: Unable to query Anthropic recommendations.",
 *     ),
 * )
 */
class artificialIntelligenceRecommendations {}

/**
 * @OA\Get(
 *     path="/ai/capabilities",
 *     summary="List the AI capabilities catalog with resolved state",
 *     operationId="apiV2AiCapabilitiesGet",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="The AI capabilities catalog.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="array", @OA\Items(type="object",
 *             @OA\Property(property="id", type="string"),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="description", type="string"),
 *             @OA\Property(property="surfaced_at", type="string"),
 *             @OA\Property(property="domain", type="string"),
 *             @OA\Property(property="tier", type="string", enum={"core","extra"}),
 *             @OA\Property(property="state", type="string", enum={"enabled","disabled","locked","needs_provider"}),
 *             @OA\Property(property="enabled", type="boolean"),
 *             @OA\Property(property="locked", type="boolean"),
 *             @OA\Property(property="always_on", type="boolean"),
 *             @OA\Property(property="extra_installed", type="boolean")
 *         )))
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */
class OpenApiAiCapabilitiesGet {}

/**
 * @OA\Patch(
 *     path="/ai/capabilities/{id}",
 *     summary="Enable or disable a single AI capability",
 *     operationId="apiV2AiCapabilityPatch",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The AI capability id (key from the capability registry).",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             @OA\Schema(
 *                 required={"enabled"},
 *                 @OA\Property(property="enabled", type="boolean", description="Whether the capability should be enabled.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Capability updated.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="id", type="string"),
 *             @OA\Property(property="enabled", type="boolean")
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: 'enabled' boolean is required."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have admin privileges."),
 *     @OA\Response(response=404, description="NOT FOUND: Unknown AI capability."),
 *     @OA\Response(response=409, description="CONFLICT: This capability requires the AI Extra to be active, or cannot be disabled."),
 * )
 */
class OpenApiAiCapabilityPatch {}

/**
 * @OA\Get(
 *     path="/ai/provider-url-check",
 *     summary="Check a candidate AI provider URL against the SSRF allow-list",
 *     description="Read-only validation used by the provider-configuration UI to warn before Save. Parses and allow-list-checks the URL only; it never makes an outbound request to the URL.",
 *     operationId="apiV2AiProviderUrlCheck",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="url",
 *         in="query",
 *         required=true,
 *         description="The candidate provider URL to validate.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The allow-list verdict for the supplied URL.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="url", type="string"),
 *             @OA\Property(property="host", type="string"),
 *             @OA\Property(property="scheme", type="string"),
 *             @OA\Property(property="allowed", type="boolean")
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: 'url' query parameter is required."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have admin privileges."),
 * )
 */
class OpenApiAiProviderUrlCheck {}

/**
 * @OA\Get(
 *     path="/ai/context",
 *     summary="Retrieve the structured AI Context Profile",
 *     description="Returns the Context Profile assembled into its three fact classes: 'asked' (schema-declared questionnaire answers saved via PATCH /ai/context), 'derived' (read live from other domains, e.g. frameworks_in_use), and 'authoritative' (read through from existing settings, e.g. risk appetite / appetite_band). See PATCH /ai/context for how 'asked' values are persisted.",
 *     operationId="apiV2AiContextQuestionsGet",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="The structured Context Profile.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="asked", type="object", description="Schema-declared questionnaire answers, keyed by question key (e.g. org_industry, org_size). Values come from the ai_context_<key> settings, decoded from their stored JSON representation."),
 *             @OA\Property(property="derived", type="object",
 *                 @OA\Property(property="frameworks_in_use", type="array", description="Frameworks the org has adopted (status=1), read live from the frameworks table.", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string")
 *                 ))
 *             ),
 *             @OA\Property(property="authoritative", type="object",
 *                 @OA\Property(property="appetite", type="object", description="The org's configured risk appetite, read through from the risk_appetite setting.",
 *                     @OA\Property(property="overall", type="number", nullable=true),
 *                     @OA\Property(property="by_category", type="object", nullable=true),
 *                     @OA\Property(property="by_team", type="object", nullable=true)
 *                 ),
 *                 @OA\Property(property="appetite_band", type="string", nullable=true, enum={"Cautious","Balanced","Aggressive"}, description="Qualitative band derived from the numeric overall appetite (display only).")
 *             ),
 *             @OA\Property(property="_meta", type="object", description="Bookkeeping timestamps for the profile answers.",
 *                 @OA\Property(property="last_saved", type="string", nullable=true),
 *                 @OA\Property(property="last_updated", type="string", nullable=true)
 *             )
 *         ))
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have admin privileges."),
 * )
 */
class OpenApiAiContextQuestionsGet {}

/**
 * @OA\Patch(
 *     path="/ai/context",
 *     summary="Save the AI context questionnaire answers (auto-save)",
 *     description="Persists the supplied context answers as ai_context_* settings, stamps the save time, and (when the AI Extra is active) re-queues risk analysis against the updated context. Each answer is validated against the Context Profile schema (recognised key + type/enum-conformant value per get_ai_context_profile_schema()); unrecognised keys and values that fail validation are silently ignored rather than rejecting the whole request, so a partially-invalid auto-save still returns 200 and still persists its valid fields. See GET /ai/context for the saved values read back through the three-class profile.",
 *     operationId="apiV2AiContextPatch",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             @OA\Schema(
 *                 required={"answers"},
 *                 @OA\Property(property="answers", type="object", description="Map of context question key => answer (string or array of strings). Unknown keys are ignored.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Answers saved.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="last_saved", type="integer"),
 *             @OA\Property(property="last_saved_label", type="string")
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: 'answers' object is required."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have admin privileges."),
 * )
 */
class OpenApiAiContextPatch {}

/**
 * @OA\Get(
 *     path="/ai/context/{type}/{id}",
 *     summary="Retrieve the GRC context bundle for an entity",
 *     description="Returns the normalized context graph (focal entity, neighbors, edges, org context profile, and provenance metadata) for a single entity. Authorization applies L2 gate (caller's domain permission for the focal type) at the endpoint; L3/L4 neighbor scoping is applied per session inside ai_get_context().",
 *     operationId="apiV2AiContextGet",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="type",
 *         in="path",
 *         required=true,
 *         description="The focal entity type. One of: 'risk', 'asset', 'framework', 'control', 'test', 'document', 'exception', 'audit', 'risk_catalog', 'threat_catalog', 'vulnerability', 'assessment', 'incident', 'context_profile'. 'assessment', 'incident' and 'vulnerability' are Extra-backed (Assessments / Incident Management / Vulnerability Management respectively) and return 404 when the owning Extra is not active. 'context_profile' is an org-level singleton readable by any caller holding at least one graph domain permission, or an admin.",
 *         @OA\Schema(type="string", enum={"risk","asset","framework","control","test","document","exception","audit","risk_catalog","threat_catalog","vulnerability","assessment","incident","context_profile"})
 *     ),
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The entity id. For 'context_profile' (an org-level singleton with no per-id record), id is ignored — pass 0. For 'vulnerability', the id is composite: '<platform>_<id>' (e.g. 'qualys_5124'), because each vulnmgmt platform table has its own auto-increment id; valid platforms are insightvm, insightvmcloud, nexpose, qualys, tenable. All other entity types require a positive integer id.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="depth",
 *         in="query",
 *         required=false,
 *         description="Traversal depth. 1 returns first-order neighbors; 2 additionally walks one hop out from each neighbor. Values outside 1-2 are clamped rather than rejected. Defaults to 1.",
 *         @OA\Schema(type="integer", minimum=1, maximum=2, default=1)
 *     ),
 *     @OA\Parameter(
 *         name="expand",
 *         in="query",
 *         required=false,
 *         description="Raise the per-type neighbor cap for a single node type, used to drill into a clustered type (see meta.neighbor_summary). An unrecognized type is ignored rather than rejected.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="limit",
 *         in="query",
 *         required=false,
 *         description="The raised cap applied to the 'expand' type. Clamped to 1..500. Ignored without 'expand'.",
 *         @OA\Schema(type="integer", minimum=1, maximum=500)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The GRC context bundle for the entity.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="focal", type="object", description="The focal entity"),
 *             @OA\Property(property="nodes", type="array", description="Neighbor nodes, out to the requested depth. A node whose backing row was removed by an INNER/LEFT JOIN miss, or whose display name resolved empty, is silently omitted rather than returned as a null/blank node.", @OA\Items(type="object",
 *                 @OA\Property(property="type", type="string", description="Node type, e.g. 'control', 'risk'. See the 'type' path parameter's enum for the full list."),
 *                 @OA\Property(property="id", type="integer", description="The entity's raw database id."),
 *                 @OA\Property(property="node_id", type="string", description="Stable composite id, e.g. 'control_id_42'."),
 *                 @OA\Property(property="name", type="string", nullable=true, description="Display name."),
 *                 @OA\Property(property="platform", type="string", nullable=true, description="Vulnerability scanner platform slug (vulnerability nodes only; e.g. 'qualys')."),
 *                 @OA\Property(property="fields", type="object", description="Enriched, type-specific field data; the key set varies by node type. BREAKING CHANGE (this release): the 'control' type's fields were renamed maturity_name -> maturity_label and desired_maturity_name -> desired_maturity_label -- both now resolve the maturity level's human-readable label rather than the raw scale code.")
 *             )),
 *             @OA\Property(property="edges", type="array", description="Relationship edges between nodes.", @OA\Items(type="object",
 *                 @OA\Property(property="from", type="string", description="Source node's node_id."),
 *                 @OA\Property(property="to", type="string", description="Target node's node_id."),
 *                 @OA\Property(property="rel", type="string", description="Direction-stable relationship label, '<child>_of_<parent>' (e.g. 'control_of_framework', 'test_of_control'). Labels are CANONICAL PER TYPE-PAIR: the same physical relationship always yields the same label regardless of which end of the pair the walk started from. BREAKING CHANGE (this release): several rel values changed -- e.g. what was previously emitted as 'control_of_risk' is now 'risk_of_control' -- as the direction convention was made consistent across both walk directions for every pair. Declared canonical pairs: control_of_framework, control_of_document, control_of_asset, test_of_control, test_result_of_test, test_result_of_risk, test_result_of_audit, audit_of_test, audit_of_control, exception_of_control, exception_of_document, exception_of_framework, exception_of_risk, document_of_framework, risk_of_asset, risk_of_control, risk_catalog_of_risk, threat_catalog_of_risk, vulnerability_of_asset, vulnerability_of_risk, assessment_of_control, assessment_of_framework, incident_of_asset, incident_of_risk, self_assessment_result_of_control. An undeclared pair falls back to '<alphabetically-first-type>_of_<alphabetically-second-type>'.")
 *             )),
 *             @OA\Property(property="profile", type="object", description="Org-level AI context profile snapshot, attached to every bundle regardless of focal type.",
 *                 @OA\Property(property="answers", type="object", description="Map of context question key => saved answer."),
 *                 @OA\Property(property="risk_appetite", type="number", nullable=true, description="The org's configured risk appetite, or null if unset."),
 *                 @OA\Property(property="_meta", type="object", description="Bookkeeping timestamps for the profile answers (e.g. last_saved, last_updated).")
 *             ),
 *             @OA\Property(property="meta", type="object", description="Provenance metadata",
 *                 @OA\Property(property="depth", type="integer"),
 *                 @OA\Property(property="generated_at", type="integer", nullable=true),
 *                 @OA\Property(property="scope", type="object", description="The caller's permission scope applied to this bundle."),
 *                 @OA\Property(property="truncated", type="boolean", description="True if ANY neighbor type was clustered. Retained for backward compatibility; prefer neighbor_summary."),
 *                 @OA\Property(property="neighbor_summary", type="object", description="Per-node-type neighbor accounting. Keyed by node type; each value reports the true total visible to this caller, how many were returned, whether the type was clustered, and the ordering used to select them.",
 *                     @OA\AdditionalProperties(type="object",
 *                         @OA\Property(property="total", type="integer", description="Total neighbors of this type visible to the caller after permission and record-level scoping."),
 *                         @OA\Property(property="returned", type="integer", description="How many were included in 'nodes'."),
 *                         @OA\Property(property="clustered", type="boolean", description="True when total exceeded the cap and only the top-ranked subset was returned."),
 *                         @OA\Property(property="ranked_by", type="string", description="The ordering used, e.g. maturity_gap_desc, calculated_risk_desc, recent_failure_first, next_review_date_asc, severity_desc, name_asc.")
 *                     )
 *                 ),
 *                 @OA\Property(property="inactive_extras", type="array", description="Slugs of Extra-backed graph domains (e.g. 'assessments', 'incident_management') whose Extra is not active in this environment.", @OA\Items(type="string"))
 *             )
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: A positive integer id is required, or a vulnerability id was not of the form <platform>_<id>."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission for this entity type."),
 *     @OA\Response(response=404, description="NOT FOUND: Unknown context entity type, the owning Extra is not active, or no such entity."),
 * )
 */
class OpenApiAiContextGet {}

/**
 * @OA\Get(
 *     path="/ai/proposals",
 *     summary="List pending AI proposals for a target entity",
 *     description="Returns the pending AI proposals for the given target entity, filtered to only the proposals the caller is authorized to review (ai_proposal_can_review() — the proposal's capability must be registered and the caller must hold that capability's declared review permission). Proposals awaiting a reviewer without that permission are silently omitted rather than surfaced as an error.",
 *     operationId="apiV2AiProposalsGet",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="target_type",
 *         in="query",
 *         required=true,
 *         description="The proposal target's entity type (e.g. 'test').",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="target_id",
 *         in="query",
 *         required=true,
 *         description="The proposal target's entity id.",
 *         @OA\Schema(type="integer", minimum=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The pending, reviewable AI proposals for the target entity.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="proposals", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="capability", type="string"),
 *                 @OA\Property(property="target_type", type="string"),
 *                 @OA\Property(property="target_id", type="integer"),
 *                 @OA\Property(property="proposed_payload", type="object", description="The capability-specific proposed change, decoded from its stored JSON representation."),
 *                 @OA\Property(property="status", type="string", enum={"pending","approved","rejected","applied"}),
 *                 @OA\Property(property="model", type="string", nullable=true),
 *                 @OA\Property(property="prompt_fingerprint", type="string", nullable=true),
 *                 @OA\Property(property="source_context", type="object", nullable=true, description="Provenance context the proposal was generated from, decoded from its stored JSON representation."),
 *                 @OA\Property(property="confidence", type="number", nullable=true),
 *                 @OA\Property(property="created_at", type="string"),
 *                 @OA\Property(property="reviewer", type="integer", nullable=true),
 *                 @OA\Property(property="decided_at", type="string", nullable=true),
 *                 @OA\Property(property="applied_at", type="string", nullable=true)
 *             ))
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: target_type and a positive target_id are required."),
 * )
 */
class OpenApiAiProposalsGet {}

/**
 * @OA\Patch(
 *     path="/ai/proposals/{id}",
 *     summary="Approve or reject a single AI proposal",
 *     description="Applies a reviewer decision to a pending AI proposal. Authentication is enforced by the router; the per-proposal review permission is enforced inside approve/reject via ai_proposal_can_review() (-> 403). Approving dispatches the proposal capability's registered apply handler — the sole GRC write for the proposal — then marks the proposal applied and audit-logs it; a handler failure leaves the proposal pending (no partial write). Rejecting marks the proposal rejected and audit-logs it with no GRC write.",
 *     operationId="apiV2AiProposalPatch",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The AI proposal id.",
 *         @OA\Schema(type="integer", minimum=1)
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             @OA\Schema(
 *                 required={"decision"},
 *                 @OA\Property(property="decision", type="string", enum={"approve","reject"}, description="The reviewer's decision for this proposal.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Decision applied.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="decision", type="string", enum={"approve","reject"})
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: a positive proposal id and a 'decision' of 'approve' or 'reject' are required."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not hold the review permission for this proposal's capability."),
 *     @OA\Response(response=404, description="NOT FOUND: Unknown AI proposal."),
 *     @OA\Response(response=409, description="CONFLICT: The proposal is not pending (already approved, rejected, or applied)."),
 *     @OA\Response(response=422, description="UNPROCESSABLE ENTITY: No apply handler is registered for this proposal's capability, or the handler failed to apply the change (the proposal is reverted to pending)."),
 * )
 */
class OpenApiAiProposalPatch {}

/**
 * @OA\Get(
 *     path="/ai/context/search",
 *     summary="Search entities for the context graph focal picker",
 *     description="Free-text lookup across the graph's focal entity types, scoped to the caller's domain permissions (L2/L3) and record-level team visibility (L4). Backs the Connectivity Visualizer's typeahead. Types whose display name is stored encrypted (risk, asset, framework) are matched after decryption over a bounded candidate scan (the 2000 most recently created rows of that type); a match older than that window will not be found. All other types are matched directly in SQL. The org-level 'context_profile' singleton is not searchable.",
 *     operationId="apiV2AiContextSearchGet",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="q",
 *         in="query",
 *         required=true,
 *         description="The search string. Minimum 2 characters.",
 *         @OA\Schema(type="string", minLength=2)
 *     ),
 *     @OA\Parameter(
 *         name="types",
 *         in="query",
 *         required=false,
 *         description="Comma-separated list of entity types to restrict the search to. Defaults to every searchable type the caller may read.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="limit",
 *         in="query",
 *         required=false,
 *         description="Maximum results to return. Clamped to 1..50.",
 *         @OA\Schema(type="integer", minimum=1, maximum=50, default=25)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Matching entities.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="results", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="type", type="string", description="The entity type, usable as the 'type' path parameter of /ai/context/{type}/{id}."),
 *                 @OA\Property(property="id", type="integer", description="The entity id, usable as the 'id' path parameter. For risks this is the RAW risks.id, matching what /ai/context/risk/{id} expects -- not the +1000 display id shown elsewhere in the SimpleRisk UI."),
 *                 @OA\Property(property="name", type="string", description="The entity's display name.")
 *             ))
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: A search query of at least 2 characters is required."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user holds no graph domain permission."),
 * )
 */
class OpenApiAiContextSearchGet {}

/**
 * @OA\Get(
 *     path="/ai/context/entity-counts",
 *     summary="Level-1 tile counts for the Connectivity Explorer canvas launchpad",
 *     description="Returns one entry per browsable entity type the caller holds the domain permission for (L2/L3). A type the caller cannot see is OMITTED from the result, never returned with a zero count, since a zero would still confirm the type exists and is empty. Each count is the number of ids visible to the caller after Team Separation (L4) is applied -- never a raw table row count, which would disclose scale to a caller with much narrower record-level visibility. The scan is chunked and each chunk is L4-filtered, accumulating VISIBLE ids until either AI_CONTEXT_BROWSE_COUNT_CAP (2000) of them have been found or AI_CONTEXT_BROWSE_SCAN_BUDGET (2000) underlying rows have been examined; 'capped' reports that the count is a floor for one of those two reasons. 'capped' false therefore means the count is exact and complete.",
 *     operationId="apiV2AiContextEntityCountsGet",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Tile counts for every browsable type the caller may see.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="array", @OA\Items(type="object",
 *             @OA\Property(property="type", type="string", description="A browsable entity type: 'control', 'test', 'document', 'exception', 'audit', 'risk_catalog', 'threat_catalog', 'risk', 'asset', or 'framework'."),
 *             @OA\Property(property="count", type="integer", description="The number of ids of this type visible to the caller, after Team Separation (L4). Bounded at 2000 -- see 'capped'."),
 *             @OA\Property(property="capped", type="boolean", description="True when the count is a floor rather than an exact total -- either 2000 VISIBLE ids were reached, or the 2000-row scan budget ran out first. The UI renders a capped count as '2,000+'. False means the count is exact and complete after L4.")
 *         )))
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user holds no graph domain permission."),
 * )
 */
class OpenApiAiContextEntityCountsGet {}

/**
 * @OA\Get(
 *     path="/ai/context/entities",
 *     summary="Level-2 browsable entity list for the Connectivity Explorer canvas launchpad",
 *     description="Returns one page of a single browsable entity type, optionally filtered by a case-insensitive substring. Unlike GET /ai/context/search, an empty 'filter' is valid and intentional -- it is the browse case this endpoint exists for, so there is no 2-character minimum. Team Separation (L4) is applied AFTER the substring filter, never before it. Every signal in the response -- the results, 'has_more' and 'next_cursor' -- is derived only from rows that survived L4, so a filter matching only records the caller may not see is indistinguishable from a filter matching nothing at all. Pages are filled by reading the underlying list in bounded chunks and L4-filtering each chunk until 'limit' visible rows have accumulated, so a page is normally full; it is short only when the list ended or the per-request scan budget (2000 underlying rows) ran out, and the latter is reported honestly as has_more true. Ordering is by name then id ascending for plaintext-name types (matched and sorted in SQL); encrypted-name types (risk, asset, framework) cannot be filtered or ordered in SQL, so they are matched and sorted in PHP over the same 2000-row bounded candidate scan GET /ai/context/search already uses for those types.",
 *     operationId="apiV2AiContextEntitiesGet",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="type",
 *         in="query",
 *         required=true,
 *         description="The browsable entity type to list. One of: 'control', 'test', 'document', 'exception', 'audit', 'risk_catalog', 'threat_catalog', 'risk', 'asset', 'framework' (the same set GET /ai/context/entity-counts reports). Any other value -- including a valid focal type that simply isn't browsable, e.g. 'assessment' -- is a 400, not a 404, since the browsable type list is already public via entity-counts.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="filter",
 *         in="query",
 *         required=false,
 *         description="Optional case-insensitive substring to match against the entity's display name. Empty (the default) returns the unfiltered browse list -- there is no minimum length, unlike the 'q' parameter of /ai/context/search.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="cursor",
 *         in="query",
 *         required=false,
 *         description="Opaque continuation cursor: pass back the 'next_cursor' from the previous response, verbatim. Omit it for the first page. It is deliberately opaque rather than a numeric offset -- a numeric offset differenced across pages would disclose how many records L4 hid between two visible ones, which is the enumeration oracle this endpoint is built to avoid. Do not construct or do arithmetic on it; an unrecognised value simply restarts from the first page.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="limit",
 *         in="query",
 *         required=false,
 *         description="Page size, counted in VISIBLE (post-L4) rows. Clamped to 1..100. Defaults to 50.",
 *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="One page of matching, visible entities.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="results", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="type", type="string", description="Echoes the requested 'type'."),
 *                 @OA\Property(property="id", type="integer", description="The entity id, usable as the 'id' path parameter of /ai/context/{type}/{id}. For risk this is the RAW risks.id, matching GET /ai/context/search's convention -- not the +1000 display id shown elsewhere in the SimpleRisk UI."),
 *                 @OA\Property(property="name", type="string", description="The entity's display name.")
 *             )),
 *             @OA\Property(property="next_cursor", type="string", nullable=true, description="Opaque cursor to pass as 'cursor' on the next request. Null exactly when 'has_more' is false. It names the last VISIBLE row you were handed, so it encodes nothing you were not already shown."),
 *             @OA\Property(property="has_more", type="boolean", description="True only when a further VISIBLE row was actually found beyond this page, or when the per-request scan budget ran out before the underlying list did. It is never true merely because rows the caller may not see remain. False means there is genuinely nothing more for this caller.")
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: 'type' must be one of the browsable entity types."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have the required permission to perform this action."),
 * )
 */
class OpenApiAiContextEntitiesGet {}

?>