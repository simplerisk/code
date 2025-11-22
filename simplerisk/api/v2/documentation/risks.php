<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * @OA\Get(
 *     path="/risks",
 *     summary="List risks in SimpleRisk",
 *     operationId="risks",
 *     tags={"risk"},
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
 *         description="Payload from the “Affected Assets” widget (implementation-specific).",
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
 *         property="file",
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

?>