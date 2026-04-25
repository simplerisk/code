<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// =====================================================================
// RISKS CRUD API
// =====================================================================

/**
 * @OA\Get(
 *     path="/risks/{id}",
 *     summary="Get a risk by ID",
 *     operationId="getRisk",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk to retrieve.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Risk retrieved successfully.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="array", @OA\Items(type="object")))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing or invalid ID, or insufficient permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Risk ID not found."),
 * )
 */
class OpenApiGetRisk {}

/**
 * @OA\Post(
 *     path="/risks",
 *     summary="Create a new risk",
 *     operationId="createRisk",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"subject"},
 *                 @OA\Property(property="subject", type="string"),
 *                 @OA\Property(property="reference_id", type="string"),
 *                 @OA\Property(property="regulation", type="integer"),
 *                 @OA\Property(property="control_number", type="string"),
 *                 @OA\Property(property="location[]", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="source", type="integer"),
 *                 @OA\Property(property="category", type="integer"),
 *                 @OA\Property(property="team[]", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="technology[]", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="owner", type="integer"),
 *                 @OA\Property(property="manager", type="integer"),
 *                 @OA\Property(property="assessment", type="string"),
 *                 @OA\Property(property="notes", type="string"),
 *                 @OA\Property(property="tags[]", type="array", @OA\Items(type="string")),
 *                 @OA\Property(property="scoring_method", type="integer", description="1=Classic, 2=CVSS, 3=DREAD, 4=OWASP, 5=Custom, 6=Contributing Risk."),
 *                 @OA\Property(property="likelihood", type="integer"),
 *                 @OA\Property(property="impact", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Risk created successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Validation error or insufficient permission."),
 * )
 */
class OpenApiCreateRisk {}

/**
 * @OA\Patch(
 *     path="/risks/{id}",
 *     summary="Update an existing risk",
 *     operationId="updateRiskById",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk to update.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="subject", type="string"),
 *                 @OA\Property(property="reference_id", type="string"),
 *                 @OA\Property(property="regulation", type="integer"),
 *                 @OA\Property(property="control_number", type="string"),
 *                 @OA\Property(property="location[]", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="source", type="integer"),
 *                 @OA\Property(property="category", type="integer"),
 *                 @OA\Property(property="team[]", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="technology[]", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="owner", type="integer"),
 *                 @OA\Property(property="manager", type="integer"),
 *                 @OA\Property(property="assessment", type="string"),
 *                 @OA\Property(property="notes", type="string"),
 *                 @OA\Property(property="tags[]", type="array", @OA\Items(type="string")),
 *                 @OA\Property(property="scoring_method", type="integer"),
 *                 @OA\Property(property="likelihood", type="integer"),
 *                 @OA\Property(property="impact", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Risk updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, validation error, or insufficient permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Risk ID not found."),
 * )
 */
class OpenApiUpdateRiskById {}

/**
 * @OA\Get(
 *     path="/risks/{id}/mitigations",
 *     summary="Get the mitigation for a risk",
 *     operationId="getRiskMitigation",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Mitigation retrieved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, no mitigation found, or insufficient permission."),
 * )
 */
class OpenApiGetRiskMitigation {}

/**
 * @OA\Post(
 *     path="/risks/{id}/mitigations",
 *     summary="Add or update the mitigation for a risk",
 *     operationId="saveRiskMitigation",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="planning_strategy", type="integer"),
 *                 @OA\Property(property="mitigation_effort", type="integer"),
 *                 @OA\Property(property="mitigation_cost", type="integer"),
 *                 @OA\Property(property="mitigation_owner", type="integer"),
 *                 @OA\Property(property="mitigation_team[]", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="current_solution", type="string"),
 *                 @OA\Property(property="security_requirements", type="string"),
 *                 @OA\Property(property="security_recommendations", type="string"),
 *                 @OA\Property(property="planning_date", type="string", format="date")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Mitigation saved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, validation error, or insufficient permission."),
 * )
 */
class OpenApiSaveRiskMitigation {}

/**
 * @OA\Patch(
 *     path="/risks/{id}/mitigations",
 *     summary="Update the mitigation for a risk",
 *     operationId="updateRiskMitigation",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="planning_strategy", type="integer"),
 *                 @OA\Property(property="mitigation_effort", type="integer"),
 *                 @OA\Property(property="mitigation_cost", type="integer"),
 *                 @OA\Property(property="mitigation_owner", type="integer"),
 *                 @OA\Property(property="mitigation_team[]", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="current_solution", type="string"),
 *                 @OA\Property(property="security_requirements", type="string"),
 *                 @OA\Property(property="security_recommendations", type="string"),
 *                 @OA\Property(property="planning_date", type="string", format="date")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Mitigation updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, validation error, or insufficient permission."),
 * )
 */
class OpenApiUpdateRiskMitigation {}

/**
 * @OA\Get(
 *     path="/risks/{id}/reviews",
 *     summary="Get the latest management review for a risk",
 *     operationId="getRiskReview",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Review retrieved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, no review found, or insufficient permission."),
 * )
 */
class OpenApiGetRiskReview {}

/**
 * @OA\Post(
 *     path="/risks/{id}/reviews",
 *     summary="Add a management review for a risk",
 *     operationId="saveRiskReview",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"review", "next_step"},
 *                 @OA\Property(property="review", type="integer", description="Review result ID."),
 *                 @OA\Property(property="next_step", type="integer", description="Next step ID."),
 *                 @OA\Property(property="reviewer", type="integer"),
 *                 @OA\Property(property="next_review", type="string", format="date"),
 *                 @OA\Property(property="comments", type="string")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Review saved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, validation error, or insufficient permission."),
 * )
 */
class OpenApiSaveRiskReview {}

/**
 * @OA\Get(
 *     path="/risks/{id}/scoring-history",
 *     summary="Get the inherent risk scoring history for a risk",
 *     operationId="getRiskScoringHistory",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Scoring history retrieved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Access denied or missing ID."),
 * )
 */
class OpenApiGetRiskScoringHistory {}

/**
 * @OA\Get(
 *     path="/risks/{id}/residual-scoring-history",
 *     summary="Get the residual risk scoring history for a risk",
 *     operationId="getRiskResidualScoringHistory",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Residual scoring history retrieved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Access denied or missing ID."),
 * )
 */
class OpenApiGetRiskResidualScoringHistory {}

/**
 * @OA\Post(
 *     path="/risks/{id}/reopen",
 *     summary="Reopen a closed risk",
 *     operationId="reopenRiskById",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk to reopen.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Risk reopened successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID or insufficient permission."),
 * )
 */
class OpenApiReopenRiskById {}

/**
 * @OA\Get(
 *     path="/risks/{id}/comments",
 *     summary="Get the comments for a risk",
 *     operationId="getRiskComments",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Comments retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="date", type="string", description="Date and time the comment was posted."),
 *                 @OA\Property(property="user", type="string", description="Full name of the user who posted the comment."),
 *                 @OA\Property(property="comment", type="string", description="The comment text.")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing or invalid ID."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission or no access to this risk."),
 * )
 */
class OpenApiGetRiskComments {}

/**
 * @OA\Post(
 *     path="/risks/{id}/comments",
 *     summary="Add a comment to a risk",
 *     operationId="addRiskComment",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"comment"},
 *                 @OA\Property(property="comment", type="string", description="The comment text to add.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Comment saved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID/comment or insufficient permission."),
 * )
 */
class OpenApiAddRiskComment {}

/**
 * @OA\Post(
 *     path="/risks/{id}/accept-mitigation",
 *     summary="Accept or reject the mitigation for a risk",
 *     operationId="acceptRiskMitigation",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"accept"},
 *                 @OA\Property(property="accept", type="integer", description="1 to accept the mitigation, 0 to reject.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Mitigation acceptance status updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, invalid value, or insufficient permission."),
 * )
 */
class OpenApiAcceptRiskMitigation {}

// =====================================================================
// RISK OPERATIONS (LEGACY)
// =====================================================================

/**
 * @OA\Get(
 *     path="/risks",
 *     summary="List risks in SimpleRisk",
 *     operationId="risks",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the risk you would like to retrieve details for. Will return all risks if no id is specified.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk risks",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a risk with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiRisks {}

/**
 * @OA\Get(
 *     path="/risks/associations",
 *     summary="List risk associations in SimpleRisk",
 *     operationId="risksAssociations",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the risk you would like to retrieve associations for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk risk associations",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a risk with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiRisksAssociations {}

/**
 * @OA\Get(
 *     path="/risks/tags",
 *     summary="List risk tags",
 *     operationId="risksTagsGet",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *        parameter="id",
 *        in="query",
 *        name="id",
 *        description="The id of the tag you would like to retrieve details for. Will return all tags if no id is specified.",
 *        required=false,
 *        @OA\Schema(
 *          type="integer",
 *        ),
 *      ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk risk tags",
 *     ),
 *     @OA\Response(
 *        response=204,
 *        description="NO CONTENT: Unable to find a tag with the specified id.",
 *      ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiRisksTagsGet {}

/**
 * Risk submit request body.
 *
 * @OA\Schema(
 *     schema="RiskSubmit",
 *     required={"subject"},
 *     @OA\Property(
 *         property="subject",
 *         type="string",
 *         description="Risk subject (must be non-empty)."
 *     ),
 *
 *     @OA\Property(
 *         property="risk_catalog_mapping",
 *         type="array",
 *         description="Risk catalog mapping values (IDs).",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(
 *         property="threat_catalog_mapping",
 *         type="array",
 *         description="Threat catalog mapping values (IDs).",
 *         @OA\Items(type="string")
 *     ),
 *
 *     @OA\Property(property="reference_id", type="string", nullable=true),
 *     @OA\Property(property="regulation", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="control_number", type="string", nullable=true),
 *
 *     @OA\Property(
 *         property="location",
 *         type="array",
 *         description="One or more locations; will be joined into a comma-separated string.",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(property="source", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="category", type="integer", format="int32", nullable=true),
 *
 *     @OA\Property(
 *         property="team",
 *         type="array",
 *         description="Team IDs assigned to this risk.",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(
 *         property="technology",
 *         type="array",
 *         description="Technology IDs assigned to this risk.",
 *         @OA\Items(type="string")
 *     ),
 *
 *     @OA\Property(property="owner", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="manager", type="integer", format="int32", nullable=true),
 *
 *     @OA\Property(property="assessment", type="string", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *
 *     @OA\Property(
 *         property="assets_asset_groups",
 *         type="array",
 *         description="Payload from the Affected Assets widget (implementation-specific).",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(
 *         property="additional_stakeholders",
 *         type="array",
 *         description="Additional stakeholder identifiers.",
 *         @OA\Items(type="string")
 *     ),
 *
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         description="Tags to attach to the risk. Each tag must be ≤ 255 characters.",
 *         @OA\Items(type="string", maxLength=255)
 *     ),
 *
 *     @OA\Property(
 *         property="template_group_id",
 *         type="string",
 *         nullable=true,
 *         description="Template group identifier (used if customization extra is enabled)."
 *     ),
 *
 *     @OA\Property(
 *         property="jira_issue_key",
 *         type="string",
 *         nullable=true,
 *         description="Optional Jira issue key. Validated only if Jira integration is enabled."
 *     ),
 *
 *     @OA\Property(
 *         property="scoring_method",
 *         type="integer",
 *         format="int32",
 *         nullable=true,
 *         description="1=Classic, 2=CVSS, 3=DREAD, 4=OWASP, 5=Custom, 6=Contributing Risk. 0/omitted = defaults to 'Custom' score of '10'."
 *     ),
 *
 *     @OA\Property(property="likelihood", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="impact", type="integer", format="int32", nullable=true),
 *
 *     @OA\Property(property="AccessVector", type="string", nullable=true),
 *     @OA\Property(property="AccessComplexity", type="string", nullable=true),
 *     @OA\Property(property="Authentication", type="string", nullable=true),
 *     @OA\Property(property="ConfImpact", type="string", nullable=true),
 *     @OA\Property(property="IntegImpact", type="string", nullable=true),
 *     @OA\Property(property="AvailImpact", type="string", nullable=true),
 *     @OA\Property(property="Exploitability", type="string", nullable=true),
 *     @OA\Property(property="RemediationLevel", type="string", nullable=true),
 *     @OA\Property(property="ReportConfidence", type="string", nullable=true),
 *     @OA\Property(property="CollateralDamagePotential", type="string", nullable=true),
 *     @OA\Property(property="TargetDistribution", type="string", nullable=true),
 *     @OA\Property(property="ConfidentialityRequirement", type="string", nullable=true),
 *     @OA\Property(property="IntegrityRequirement", type="string", nullable=true),
 *     @OA\Property(property="AvailabilityRequirement", type="string", nullable=true),
 *
 *     @OA\Property(property="DREADDamage", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="DREADReproducibility", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="DREADExploitability", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="DREADAffectedUsers", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="DREADDiscoverability", type="integer", format="int32", nullable=true),
 *
 *     @OA\Property(property="OWASPSkillLevel", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPMotive", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPOpportunity", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPSize", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPEaseOfDiscovery", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPEaseOfExploit", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPAwareness", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPIntrusionDetection", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPLossOfConfidentiality", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPLossOfIntegrity", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPLossOfAvailability", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPLossOfAccountability", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPFinancialDamage", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPReputationDamage", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPNonCompliance", type="integer", format="int32", nullable=true),
 *     @OA\Property(property="OWASPPrivacyViolation", type="integer", format="int32", nullable=true),
 *
 *     @OA\Property(property="Custom", type="number", format="float", nullable=true),
 *
 *     @OA\Property(property="ContributingLikelihood", type="integer", format="int32", nullable=true),
 *     @OA\Property(
 *         property="ContributingImpacts",
 *         type="array",
 *         nullable=true,
 *         @OA\Items(type="string")
 *     ),
 *
 *     @OA\Property(
 *         property="associate_test",
 *         type="integer",
 *         format="int32",
 *         nullable=true,
 *         description="Identifier of a test to associate with the new risk (0 for none)."
 *     ),
 *
 *     @OA\Property(
 *         property="file[]",
 *         type="array",
 *         description="One or more files to upload and attach to the risk.",
 *         @OA\Items(type="string", format="binary")
 *     )
 * )
 */
class OpenApiRiskSubmitSchema {}

/**
 * Risk submit response data.
 *
 * @OA\Schema(
 *     schema="RiskSubmitData",
 *     required={"risk_id", "associate_test"},
 *     @OA\Property(
 *         property="risk_id",
 *         type="integer",
 *         description="External risk identifier (internal ID + 1000).",
 *         example=1234
 *     ),
 *     @OA\Property(
 *         property="associate_test",
 *         type="integer",
 *         format="int32",
 *         description="Test association flag or identifier.",
 *         example=0
 *     )
 * )
 */
class OpenApiRiskSubmitDataSchema {}

/**
 * Envelope used by API v2 responses for risk submit.
 *
 * @OA\Schema(
 *     schema="RiskSubmitResponse",
 *     allOf={
 *         @OA\Schema(
 *             required={"status_code", "status_message"},
 *             @OA\Property(property="status_code", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS")
 *         ),
 *         @OA\Schema(
 *             @OA\Property(
 *                 property="data",
 *                 oneOf={
 *                     @OA\Schema(ref="#/components/schemas/RiskSubmitData"),
 *                     @OA\Schema(type="null")
 *                 }
 *             )
 *         )
 *     }
 * )
 */
class OpenApiRiskSubmitResponseSchema {}

/**
 * Submit a new risk.
 *
 * @OA\Post(
 *     path="/risks/submit",
 *     summary="Submit a new risk",
 *     operationId="api_v2_risk_submit",
 *     tags={"risk", "need_explode_for_arrays"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Risk details, scoring parameters, optional Jira key, assets, tags, and files.",
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(ref="#/components/schemas/RiskSubmit")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Risk created successfully.",
 *         @OA\JsonContent(ref="#/components/schemas/RiskSubmitResponse")
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Bad request – validation error, failed Jira validation, scoring error, or file upload error.",
 *         @OA\JsonContent(ref="#/components/schemas/RiskSubmitResponse")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized – user does not have permission to submit risks.",
 *         @OA\JsonContent(ref="#/components/schemas/RiskSubmitResponse")
 *     )
 * )
 */
class OpenApiRiskSubmit {}

/**
 * @OA\Get(
 *     path="/whoami",
 *     summary="Return the username and uid of the currently authenticated user",
 *     operationId="whoami",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="Authenticated user identity",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="username", type="string", description="The username of the authenticated user."),
 *         @OA\Property(property="uid", type="integer", description="The user ID of the authenticated user.")
 *       )
 *     ),
 * )
 */
class OpenApiWhoami {}

/**
 * @OA\Get(
 *     path="/management/risk/view",
 *     summary="Get details for a risk by its ID",
 *     operationId="viewRisk",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The external risk ID (internal ID + 1000).",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk detail object",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="status", type="string"),
 *         @OA\Property(property="subject", type="string"),
 *         @OA\Property(property="reference_id", type="string"),
 *         @OA\Property(property="regulation", type="integer"),
 *         @OA\Property(property="control_number", type="string"),
 *         @OA\Property(property="location", type="string"),
 *         @OA\Property(property="source", type="integer"),
 *         @OA\Property(property="category", type="integer"),
 *         @OA\Property(property="team", type="string"),
 *         @OA\Property(property="technology", type="string"),
 *         @OA\Property(property="owner", type="integer"),
 *         @OA\Property(property="manager", type="integer"),
 *         @OA\Property(property="assessment", type="string"),
 *         @OA\Property(property="notes", type="string"),
 *         @OA\Property(property="submission_date", type="string", format="date-time"),
 *         @OA\Property(property="last_update", type="string", format="date-time"),
 *         @OA\Property(property="review_date", type="string", format="date"),
 *         @OA\Property(property="close_date", type="string", format="date-time", nullable=true),
 *         @OA\Property(property="calculated_risk", type="number", format="float"),
 *         @OA\Property(property="residual_risk", type="number", format="float")
 *       )
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to view this risk.",
 *     ),
 * )
 */
class OpenApiViewRisk {}

/**
 * @OA\Post(
 *     path="/management/risk/add",
 *     summary="Submit a new risk (legacy endpoint)",
 *     operationId="addRisk",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"subject"},
 *                 @OA\Property(property="subject", type="string", description="Risk subject (required)."),
 *                 @OA\Property(property="reference_id", type="string"),
 *                 @OA\Property(property="regulation", type="integer", format="int32"),
 *                 @OA\Property(property="control_number", type="string"),
 *                 @OA\Property(property="location", type="array", @OA\Items(type="string")),
 *                 @OA\Property(property="source", type="integer", format="int32"),
 *                 @OA\Property(property="category", type="integer", format="int32"),
 *                 @OA\Property(property="team", type="array", @OA\Items(type="integer", format="int32")),
 *                 @OA\Property(property="technology", type="array", @OA\Items(type="integer", format="int32")),
 *                 @OA\Property(property="owner", type="integer", format="int32"),
 *                 @OA\Property(property="manager", type="integer", format="int32"),
 *                 @OA\Property(property="assessment", type="string"),
 *                 @OA\Property(property="notes", type="string"),
 *                 @OA\Property(property="tags", type="array", @OA\Items(type="string")),
 *                 @OA\Property(
 *                     property="scoring_method",
 *                     type="integer",
 *                     format="int32",
 *                     enum={1, 2, 3, 4, 5, 6},
 *                     description="1=Classic, 2=CVSS, 3=DREAD, 4=OWASP, 5=Custom, 6=Contributing"
 *                 ),
 *                 @OA\Property(property="likelihood", type="integer", format="int32"),
 *                 @OA\Property(property="impact", type="integer", format="int32"),
 *                 @OA\Property(property="AccessVector", type="string"),
 *                 @OA\Property(property="AccessComplexity", type="string"),
 *                 @OA\Property(property="Authentication", type="string"),
 *                 @OA\Property(property="ConfImpact", type="string"),
 *                 @OA\Property(property="IntegImpact", type="string"),
 *                 @OA\Property(property="AvailImpact", type="string"),
 *                 @OA\Property(property="Exploitability", type="string"),
 *                 @OA\Property(property="RemediationLevel", type="string"),
 *                 @OA\Property(property="ReportConfidence", type="string"),
 *                 @OA\Property(property="CollateralDamagePotential", type="string"),
 *                 @OA\Property(property="TargetDistribution", type="string"),
 *                 @OA\Property(property="ConfidentialityRequirement", type="string"),
 *                 @OA\Property(property="IntegrityRequirement", type="string"),
 *                 @OA\Property(property="AvailabilityRequirement", type="string"),
 *                 @OA\Property(property="DREADDamage", type="integer", format="int32"),
 *                 @OA\Property(property="DREADReproducibility", type="integer", format="int32"),
 *                 @OA\Property(property="DREADExploitability", type="integer", format="int32"),
 *                 @OA\Property(property="DREADAffectedUsers", type="integer", format="int32"),
 *                 @OA\Property(property="DREADDiscoverability", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPSkillLevel", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPMotive", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPOpportunity", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPSize", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPEaseOfDiscovery", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPEaseOfExploit", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPAwareness", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPIntrusionDetection", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPLossOfConfidentiality", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPLossOfIntegrity", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPLossOfAvailability", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPLossOfAccountability", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPFinancialDamage", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPReputationDamage", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPNonCompliance", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPPrivacyViolation", type="integer", format="int32"),
 *                 @OA\Property(property="Custom", type="number", format="float")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk created successfully.",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="risk_id", type="integer", description="The ID of the newly created risk.")
 *       )
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to add risks.",
 *     ),
 * )
 */
class OpenApiAddRisk {}

/**
 * @OA\Post(
 *     path="/management/risk/update",
 *     summary="Update an existing risk",
 *     operationId="updateRisk",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"id", "subject"},
 *                 @OA\Property(property="id", type="integer", format="int32", description="External risk ID (internal ID + 1000)."),
 *                 @OA\Property(property="subject", type="string", description="Risk subject (required)."),
 *                 @OA\Property(property="reference_id", type="string"),
 *                 @OA\Property(property="regulation", type="integer", format="int32"),
 *                 @OA\Property(property="control_number", type="string"),
 *                 @OA\Property(property="location", type="array", @OA\Items(type="string")),
 *                 @OA\Property(property="source", type="integer", format="int32"),
 *                 @OA\Property(property="category", type="integer", format="int32"),
 *                 @OA\Property(property="team", type="array", @OA\Items(type="integer", format="int32")),
 *                 @OA\Property(property="technology", type="array", @OA\Items(type="integer", format="int32")),
 *                 @OA\Property(property="owner", type="integer", format="int32"),
 *                 @OA\Property(property="manager", type="integer", format="int32"),
 *                 @OA\Property(property="assessment", type="string"),
 *                 @OA\Property(property="notes", type="string"),
 *                 @OA\Property(property="tags", type="array", @OA\Items(type="string")),
 *                 @OA\Property(
 *                     property="scoring_method",
 *                     type="integer",
 *                     format="int32",
 *                     enum={1, 2, 3, 4, 5, 6},
 *                     description="1=Classic, 2=CVSS, 3=DREAD, 4=OWASP, 5=Custom, 6=Contributing"
 *                 ),
 *                 @OA\Property(property="likelihood", type="integer", format="int32"),
 *                 @OA\Property(property="impact", type="integer", format="int32"),
 *                 @OA\Property(property="AccessVector", type="string"),
 *                 @OA\Property(property="AccessComplexity", type="string"),
 *                 @OA\Property(property="Authentication", type="string"),
 *                 @OA\Property(property="ConfImpact", type="string"),
 *                 @OA\Property(property="IntegImpact", type="string"),
 *                 @OA\Property(property="AvailImpact", type="string"),
 *                 @OA\Property(property="Exploitability", type="string"),
 *                 @OA\Property(property="RemediationLevel", type="string"),
 *                 @OA\Property(property="ReportConfidence", type="string"),
 *                 @OA\Property(property="CollateralDamagePotential", type="string"),
 *                 @OA\Property(property="TargetDistribution", type="string"),
 *                 @OA\Property(property="ConfidentialityRequirement", type="string"),
 *                 @OA\Property(property="IntegrityRequirement", type="string"),
 *                 @OA\Property(property="AvailabilityRequirement", type="string"),
 *                 @OA\Property(property="DREADDamage", type="integer", format="int32"),
 *                 @OA\Property(property="DREADReproducibility", type="integer", format="int32"),
 *                 @OA\Property(property="DREADExploitability", type="integer", format="int32"),
 *                 @OA\Property(property="DREADAffectedUsers", type="integer", format="int32"),
 *                 @OA\Property(property="DREADDiscoverability", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPSkillLevel", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPMotive", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPOpportunity", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPSize", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPEaseOfDiscovery", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPEaseOfExploit", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPAwareness", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPIntrusionDetection", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPLossOfConfidentiality", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPLossOfIntegrity", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPLossOfAvailability", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPLossOfAccountability", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPFinancialDamage", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPReputationDamage", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPNonCompliance", type="integer", format="int32"),
 *                 @OA\Property(property="OWASPPrivacyViolation", type="integer", format="int32"),
 *                 @OA\Property(property="Custom", type="number", format="float")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk updated successfully.",
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to update risks.",
 *     ),
 * )
 */
class OpenApiUpdateRisk {}

/**
 * @OA\Get(
 *     path="/management/mitigation/view",
 *     summary="View mitigation details for a risk",
 *     operationId="viewMitigation",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The external risk ID (internal ID + 1000).",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Mitigation details",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="planning_date", type="string", format="date"),
 *         @OA\Property(property="strategy", type="integer"),
 *         @OA\Property(property="effort", type="integer"),
 *         @OA\Property(property="cost", type="integer"),
 *         @OA\Property(property="owner", type="integer"),
 *         @OA\Property(property="team", type="string"),
 *         @OA\Property(property="current_solution", type="string"),
 *         @OA\Property(property="security_requirements", type="string"),
 *         @OA\Property(property="supporting_files", type="array", @OA\Items(type="object"))
 *       )
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to view this mitigation.",
 *     ),
 * )
 */
class OpenApiViewMitigation {}

/**
 * @OA\Post(
 *     path="/management/mitigation/add",
 *     summary="Add or update a mitigation for a risk",
 *     operationId="saveMitigation",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"id"},
 *                 @OA\Property(property="id", type="integer", format="int32", description="External risk ID (internal ID + 1000)."),
 *                 @OA\Property(property="planning_strategy", type="integer", format="int32"),
 *                 @OA\Property(property="mitigation_effort", type="integer", format="int32"),
 *                 @OA\Property(property="mitigation_cost", type="integer", format="int32"),
 *                 @OA\Property(property="mitigation_owner", type="integer", format="int32"),
 *                 @OA\Property(property="mitigation_team", type="array", @OA\Items(type="integer", format="int32")),
 *                 @OA\Property(property="current_solution", type="string"),
 *                 @OA\Property(property="security_requirements", type="string"),
 *                 @OA\Property(property="security_recommendations", type="string"),
 *                 @OA\Property(property="planning_date", type="string", format="date"),
 *                 @OA\Property(property="mitigation_percent", type="integer", format="int32", minimum=0, maximum=100)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Mitigation saved successfully.",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="risk_id", type="integer", description="The external risk ID.")
 *       )
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to save this mitigation.",
 *     ),
 * )
 */
class OpenApiSaveMitigation {}

/**
 * @OA\Get(
 *     path="/management/review/view",
 *     summary="View the latest management review for a risk",
 *     operationId="viewReview",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The external risk ID (internal ID + 1000).",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Management review details",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="submission_date", type="string", format="date-time"),
 *         @OA\Property(property="reviewer", type="integer"),
 *         @OA\Property(property="review", type="integer"),
 *         @OA\Property(property="next_step", type="integer"),
 *         @OA\Property(property="next_review", type="string", format="date"),
 *         @OA\Property(property="comments", type="string")
 *       )
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to view this review.",
 *     ),
 * )
 */
class OpenApiViewReview {}

/**
 * @OA\Post(
 *     path="/management/review/add",
 *     summary="Add a management review for a risk",
 *     operationId="saveReview",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"id"},
 *                 @OA\Property(property="id", type="integer", format="int32", description="External risk ID (internal ID + 1000)."),
 *                 @OA\Property(property="review", type="integer", format="int32"),
 *                 @OA\Property(property="next_step", type="integer", format="int32"),
 *                 @OA\Property(property="comments", type="string")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Review saved successfully.",
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to add a review.",
 *     ),
 * )
 */
class OpenApiSaveReview {}

/**
 * @OA\Get(
 *     path="/management/risk/scoring_history",
 *     summary="Get inherent risk scoring history for a risk or all risks",
 *     operationId="scoringHistory",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="External risk ID (internal ID + 1000). Returns history for all risks if omitted.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Inherent risk scoring history",
 *       @OA\JsonContent(
 *         type="array",
 *         @OA\Items(
 *           type="object",
 *           @OA\Property(property="risk_id", type="integer"),
 *           @OA\Property(property="calculated_risk", type="number", format="float"),
 *           @OA\Property(property="last_update", type="string", format="date-time")
 *         )
 *       )
 *     ),
 * )
 */
class OpenApiScoringHistory {}

/**
 * @OA\Get(
 *     path="/management/risk/residual_scoring_history",
 *     summary="Get residual risk scoring history for a risk or all risks",
 *     operationId="residualScoringHistory",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="External risk ID (internal ID + 1000). Returns history for all risks if omitted.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Residual risk scoring history",
 *       @OA\JsonContent(
 *         type="array",
 *         @OA\Items(
 *           type="object",
 *           @OA\Property(property="risk_id", type="integer"),
 *           @OA\Property(property="residual_risk", type="number", format="float"),
 *           @OA\Property(property="last_update", type="string", format="date-time")
 *         )
 *       )
 *     ),
 * )
 */
class OpenApiResidualScoringHistory {}

/**
 * @OA\Post(
 *     path="/management/risk/reopen",
 *     summary="Reopen a closed risk",
 *     operationId="reopenRisk",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"id"},
 *                 @OA\Property(property="id", type="integer", format="int32", description="External risk ID (internal ID + 1000).")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk reopened successfully.",
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to reopen this risk.",
 *     ),
 * )
 */
class OpenApiReopenRisk {}

/**
 * @OA\Post(
 *     path="/management/risk/saveSubject",
 *     summary="Update the subject/title of a risk",
 *     operationId="saveRiskSubject",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"id", "subject"},
 *                 @OA\Property(property="id", type="integer", format="int32", description="The risk ID."),
 *                 @OA\Property(property="subject", type="string", description="The new subject/title for the risk.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Subject updated successfully.",
 *     ),
 * )
 */
class OpenApiSaveRiskSubject {}

/**
 * @OA\Post(
 *     path="/management/risk/saveComment",
 *     summary="Add a comment to a risk",
 *     operationId="saveRiskComment",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"id", "comment"},
 *                 @OA\Property(property="id", type="integer", format="int32", description="External risk ID (internal ID + 1000)."),
 *                 @OA\Property(property="comment", type="string", description="The comment text to add.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Comment saved successfully.",
 *     ),
 * )
 */
class OpenApiSaveRiskComment {}

/**
 * @OA\Post(
 *     path="/management/risk/accept_mitigation",
 *     summary="Accept (or un-accept) the mitigation for a risk",
 *     operationId="acceptMitigation",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"id"},
 *                 @OA\Property(property="id", type="integer", format="int32", description="External risk ID (internal ID + 1000)."),
 *                 @OA\Property(property="accept", type="boolean", description="True to accept, false to un-accept the mitigation.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Mitigation acceptance status updated successfully.",
 *     ),
 * )
 */
class OpenApiAcceptMitigation {}

/**
 * @OA\Get(
 *     path="/management/tag_options_of_type",
 *     summary="Get tag options for a specific taggable type",
 *     operationId="getTagOptionsOfType",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="type",
 *       in="query",
 *       name="type",
 *       description="The taggable type to retrieve options for.",
 *       required=true,
 *       @OA\Schema(
 *         type="string",
 *         enum={"risk", "asset", "test", "test_audit"}
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Array of tag options",
 *       @OA\JsonContent(
 *         type="array",
 *         @OA\Items(
 *           type="object",
 *           @OA\Property(property="id", type="integer"),
 *           @OA\Property(property="tag", type="string")
 *         )
 *       )
 *     ),
 * )
 */
class OpenApiGetTagOptionsOfType {}

/**
 * @OA\Get(
 *     path="/management/tag_options_of_types",
 *     summary="Get tag options for multiple taggable types",
 *     operationId="getTagOptionsOfTypes",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="types",
 *       in="query",
 *       name="types",
 *       description="Array of taggable type strings to retrieve options for.",
 *       required=false,
 *       @OA\Schema(
 *         type="array",
 *         @OA\Items(type="string")
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Object keyed by type, each value is an array of tag options.",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\AdditionalProperties(
 *           type="array",
 *           @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="tag", type="string")
 *           )
 *         )
 *       )
 *     ),
 * )
 */
class OpenApiGetTagOptionsOfTypes {}

/**
 * @OA\Get(
 *     path="/user/manager",
 *     summary="Get the manager of a given user",
 *     operationId="getManagerByUser",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The user ID to look up the manager for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Manager user ID",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="manager", type="integer", description="The user ID of the manager.")
 *       )
 *     ),
 * )
 */
class OpenApiGetManagerByUser {}

/**
 * @OA\Post(
 *     path="/management/project/add",
 *     summary="Create a new risk management project",
 *     operationId="addProject",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"new_project"},
 *                 @OA\Property(property="new_project", type="string", description="The name of the new project."),
 *                 @OA\Property(property="due_date", type="string", format="date"),
 *                 @OA\Property(property="consultant", type="integer", format="int32"),
 *                 @OA\Property(property="business_owner", type="integer", format="int32"),
 *                 @OA\Property(property="data_classification", type="integer", format="int32")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Project created successfully.",
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to create projects.",
 *     ),
 * )
 */
class OpenApiAddProject {}

/**
 * @OA\Post(
 *     path="/management/project/delete",
 *     summary="Delete a risk management project",
 *     operationId="deleteProject",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"project_id"},
 *                 @OA\Property(property="project_id", type="integer", format="int32", description="The ID of the project to delete.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Project deleted successfully.",
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to delete projects.",
 *     ),
 * )
 */
class OpenApiDeleteProject {}

/**
 * @OA\Post(
 *     path="/management/project/update",
 *     summary="Assign or reassign a risk to a project",
 *     operationId="updateProjectRisk",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"risk_id", "project_id"},
 *                 @OA\Property(property="risk_id", type="integer", format="int32", description="The risk ID to assign."),
 *                 @OA\Property(property="project_id", type="integer", format="int32", description="The project ID to assign the risk to.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk assigned to project successfully.",
 *     ),
 * )
 */
class OpenApiUpdateProjectRisk {}

/**
 * @OA\Post(
 *     path="/management/project/edit",
 *     summary="Edit the details of a project",
 *     operationId="editProject",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"project_id"},
 *                 @OA\Property(property="project_id", type="integer", format="int32", description="The ID of the project to edit."),
 *                 @OA\Property(property="name", type="string"),
 *                 @OA\Property(property="due_date", type="string", format="date"),
 *                 @OA\Property(property="consultant", type="integer", format="int32"),
 *                 @OA\Property(property="business_owner", type="integer", format="int32"),
 *                 @OA\Property(property="data_classification", type="integer", format="int32")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Project updated successfully.",
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No permission to edit projects.",
 *     ),
 * )
 */
class OpenApiEditProject {}

/**
 * @OA\Post(
 *     path="/management/project/update_status",
 *     summary="Update the status of a project",
 *     operationId="updateProjectStatus",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"status", "project_id"},
 *                 @OA\Property(property="status", type="integer", format="int32", description="The new status value for the project."),
 *                 @OA\Property(property="project_id", type="integer", format="int32", description="The ID of the project to update.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Project status updated successfully.",
 *     ),
 * )
 */
class OpenApiUpdateProjectStatus {}

/**
 * @OA\Post(
 *     path="/management/project/update_order",
 *     summary="Update the display order of projects",
 *     operationId="updateProjectOrder",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(
 *                     property="project_ids",
 *                     type="array",
 *                     description="Ordered array of project IDs representing the desired display order.",
 *                     @OA\Items(type="integer", format="int32")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Project order updated successfully.",
 *     ),
 * )
 */
class OpenApiUpdateProjectOrder {}

/**
 * @OA\Get(
 *     path="/management/project/detail",
 *     summary="Get details for a project",
 *     operationId="projectDetail",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="project_id",
 *       in="query",
 *       name="project_id",
 *       description="The ID of the project to retrieve.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Project details",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="due_date", type="string", format="date"),
 *         @OA\Property(property="consultant", type="integer"),
 *         @OA\Property(property="business_owner", type="integer"),
 *         @OA\Property(property="data_classification", type="integer")
 *       )
 *     ),
 * )
 */
class OpenApiProjectDetail {}

/**
 * @OA\Post(
 *     path="/risk_management/plan_mitigation",
 *     summary="Get risks pending mitigation in DataTables format",
 *     operationId="planMitigationDatatable",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="draw", type="integer", format="int32", description="DataTables draw counter."),
 *                 @OA\Property(property="start", type="integer", format="int32", description="Paging first record indicator."),
 *                 @OA\Property(property="length", type="integer", format="int32", description="Number of records to return."),
 *                 @OA\Property(property="columns", type="array", @OA\Items(type="object"), description="DataTables column definitions."),
 *                 @OA\Property(property="order", type="array", @OA\Items(type="object"), description="DataTables ordering parameters.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="DataTables server-side response",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="draw", type="integer"),
 *         @OA\Property(property="recordsTotal", type="integer"),
 *         @OA\Property(property="recordsFiltered", type="integer"),
 *         @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *       )
 *     ),
 * )
 */
class OpenApiPlanMitigationDatatable {}

/**
 * @OA\Post(
 *     path="/risk_management/managment_review",
 *     summary="Get risks pending management review in DataTables format",
 *     operationId="managementReviewDatatable",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="draw", type="integer", format="int32", description="DataTables draw counter."),
 *                 @OA\Property(property="start", type="integer", format="int32", description="Paging first record indicator."),
 *                 @OA\Property(property="length", type="integer", format="int32", description="Number of records to return."),
 *                 @OA\Property(property="columns", type="array", @OA\Items(type="object"), description="DataTables column definitions."),
 *                 @OA\Property(property="order", type="array", @OA\Items(type="object"), description="DataTables ordering parameters.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="DataTables server-side response",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="draw", type="integer"),
 *         @OA\Property(property="recordsTotal", type="integer"),
 *         @OA\Property(property="recordsFiltered", type="integer"),
 *         @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *       )
 *     ),
 * )
 */
class OpenApiManagementReviewDatatable {}

/**
 * @OA\Post(
 *     path="/risk_management/review_risks",
 *     summary="Get risks for periodic review in DataTables format",
 *     operationId="reviewRisksDatatable",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="draw", type="integer", format="int32", description="DataTables draw counter."),
 *                 @OA\Property(property="start", type="integer", format="int32", description="Paging first record indicator."),
 *                 @OA\Property(property="length", type="integer", format="int32", description="Number of records to return."),
 *                 @OA\Property(property="columns", type="array", @OA\Items(type="object"), description="DataTables column definitions."),
 *                 @OA\Property(property="order", type="array", @OA\Items(type="object"), description="DataTables ordering parameters.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="DataTables server-side response",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="draw", type="integer"),
 *         @OA\Property(property="recordsTotal", type="integer"),
 *         @OA\Property(property="recordsFiltered", type="integer"),
 *         @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *       )
 *     ),
 * )
 */
class OpenApiReviewRisksDatatable {}

/**
 * @OA\Get(
 *     path="/risk_management/review_date_issues",
 *     summary="Get risks that have review date formatting issues",
 *     operationId="reviewDateIssues",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="Array of risks with review date issues",
 *       @OA\JsonContent(
 *         type="array",
 *         @OA\Items(type="object")
 *       )
 *     ),
 * )
 */
class OpenApiReviewDateIssues {}

/**
 * @OA\Post(
 *     path="/datatable/framework_controls",
 *     summary="Get framework controls in DataTables format",
 *     operationId="frameworkControlsDatatable",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="draw", type="integer", format="int32", description="DataTables draw counter."),
 *                 @OA\Property(property="start", type="integer", format="int32", description="Paging first record indicator."),
 *                 @OA\Property(property="length", type="integer", format="int32", description="Number of records to return."),
 *                 @OA\Property(property="columns", type="array", @OA\Items(type="object"), description="DataTables column definitions."),
 *                 @OA\Property(property="order", type="array", @OA\Items(type="object"), description="DataTables ordering parameters."),
 *                 @OA\Property(property="framework_id", type="integer", format="int32", description="ID of the framework to retrieve controls for.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="DataTables server-side response",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="draw", type="integer"),
 *         @OA\Property(property="recordsTotal", type="integer"),
 *         @OA\Property(property="recordsFiltered", type="integer"),
 *         @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *       )
 *     ),
 * )
 */
class OpenApiFrameworkControlsDatatable {}

/**
 * @OA\Post(
 *     path="/datatable/mitigation_controls",
 *     summary="Get controls for a mitigation in DataTables format",
 *     operationId="mitigationControlsDatatable",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="draw", type="integer", format="int32", description="DataTables draw counter."),
 *                 @OA\Property(property="start", type="integer", format="int32", description="Paging first record indicator."),
 *                 @OA\Property(property="length", type="integer", format="int32", description="Number of records to return."),
 *                 @OA\Property(property="columns", type="array", @OA\Items(type="object"), description="DataTables column definitions."),
 *                 @OA\Property(property="order", type="array", @OA\Items(type="object"), description="DataTables ordering parameters."),
 *                 @OA\Property(property="mitigation_id", type="integer", format="int32", description="ID of the mitigation to retrieve controls for.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="DataTables server-side response",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="draw", type="integer"),
 *         @OA\Property(property="recordsTotal", type="integer"),
 *         @OA\Property(property="recordsFiltered", type="integer"),
 *         @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *       )
 *     ),
 * )
 */
class OpenApiMitigationControlsDatatable {}

?>
