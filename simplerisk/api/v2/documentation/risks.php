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
 *     @OA\Response(response=400, description="BAD REQUEST: Missing or invalid ID, or the caller lacks the risk management permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
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
 *                 @OA\Property(property="team[]", type="array", description="Teams the new risk belongs to. Omitting it (or sending it empty) assigns NO team — it does not assign every team.", @OA\Items(type="integer")),
 *                 @OA\Property(property="technology[]", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="owner", type="integer"),
 *                 @OA\Property(property="manager", type="integer"),
 *                 @OA\Property(property="assessment", type="string"),
 *                 @OA\Property(property="notes", type="string"),
 *                 @OA\Property(property="tags[]", type="array", @OA\Items(type="string")),
 *                 @OA\Property(property="affected_assets", type="string", description="Comma-separated asset/asset-group NAMES (import-style callers)."),
 *                 @OA\Property(property="assets_asset_groups[]", type="array", description="Affected Assets widget tokens ('<id>_asset', '<id>_group', 'new_asset_<name>') as posted by the in-app risk form.", @OA\Items(type="string")),
 *                 @OA\Property(property="template_group_id", type="integer", description="Validated against the caller's own assigned template groups; an id the caller is not assigned to falls back to their default group."),
 *                 @OA\Property(property="associate_test", type="integer", description="Echoed back unchanged in the response. Set to 1 by Compliance's create-risk-from-a-failed-test flow so the client knows to associate the new risk with the test instead of navigating to it."),
 *                 @OA\Property(property="scoring_method", type="integer", description="1=Classic, 2=CVSS, 3=DREAD, 4=OWASP, 5=Custom, 6=Contributing Risk."),
 *                 @OA\Property(property="likelihood", type="integer"),
 *                 @OA\Property(property="impact", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk created successfully. `data` carries `risk_id` and the echoed `associate_test`. `status_message` always carries the localized success message; when `associate_test` was set it is instead a JSON-encoded array of `{alert_type, alert_message}` objects, the shape the in-app create-risk-from-a-failed-test client parses. The same message is additionally queued in the session for the in-app clients that redirect on success.",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="status", type="integer", example=200),
 *         @OA\Property(property="status_message", type="string", description="The localized success message, naming the risk that was created."),
 *         @OA\Property(
 *           property="data",
 *           type="object",
 *           @OA\Property(property="risk_id", type="integer", description="The display ID of the newly created risk (internal ID + 1000)."),
 *           @OA\Property(property="associate_test", type="integer", description="The posted associate_test value, echoed back unchanged.")
 *         )
 *       )
 *     ),
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
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, no mitigation found, or the caller lacks the risk management permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
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
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, no review found, or the caller lacks the risk management permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
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
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
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
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
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

/**
 * @OA\Get(
 *     path="/risks/{id}/mitigations/controls",
 *     summary="Get the controls attached to a risk's mitigation, with validation summary",
 *     operationId="getMitigationControlsList",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Mitigation controls returned successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID or insufficient permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: Team separation denies access to this risk."),
 * )
 */
class OpenApiGetMitigationControlsList {}

/**
 * @OA\Get(
 *     path="/risks/{id}/mitigations/controls/{control_id}/validation",
 *     summary="Get one control's validation details for a risk's mitigation",
 *     operationId="getMitigationControlValidation",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="control_id",
 *         in="path",
 *         required=true,
 *         description="The ID of the control.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Control validation returned successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, invalid control, or insufficient permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: Team separation denies access to this risk."),
 * )
 */
class OpenApiGetMitigationControlValidation {}

/**
 * @OA\Post(
 *     path="/risks/{id}/mitigations/controls/{control_id}/validation",
 *     summary="Save one control's validation details for a risk's mitigation",
 *     operationId="saveMitigationControlValidation",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="control_id",
 *         in="path",
 *         required=true,
 *         description="The ID of the control.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(property="validation_details", type="string", description="Validation notes for this control."),
 *                 @OA\Property(property="validation_owner", type="integer", description="User ID of the validation owner."),
 *                 @OA\Property(property="validation_mitigation_percent", type="integer", description="Mitigation percent achieved by this control (0-100)."),
 *                 @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"), description="IDs of previously-uploaded evidence files to keep; omitted existing files are deleted."),
 *                 @OA\Property(property="artifact_file", type="string", format="binary", description="Optional new evidence file to upload.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Control validation saved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, control not attached to this mitigation, insufficient permission, or upload error."),
 *     @OA\Response(response=403, description="FORBIDDEN: Team separation denies access to this risk."),
 * )
 */
class OpenApiSaveMitigationControlValidation {}

/**
 * @OA\Get(
 *     path="/risks/{id}/supporting-documentation",
 *     summary="List a risk's supporting documentation files (Details tab)",
 *     operationId="getSupportingDocumentation",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Supporting documentation files returned successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID or insufficient permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: Team separation denies access to this risk."),
 * )
 */
class OpenApiGetSupportingDocumentation {}

/**
 * @OA\Post(
 *     path="/risks/{id}/supporting-documentation",
 *     summary="Upload or remove a risk's supporting documentation files (Details tab)",
 *     operationId="saveSupportingDocumentation",
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
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(property="unique_names", type="array", @OA\Items(type="string"), description="Unique names of previously-uploaded files to keep; omitted existing files are deleted."),
 *                 @OA\Property(property="file", type="string", format="binary", description="Optional new file to upload.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Supporting documentation saved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, insufficient permission, or upload error."),
 *     @OA\Response(response=403, description="FORBIDDEN: Team separation denies access to this risk."),
 * )
 */
class OpenApiSaveSupportingDocumentation {}

/**
 * @OA\Get(
 *     path="/risks/{id}/mitigations/supporting-documentation",
 *     summary="List a mitigation's supporting documentation files (Mitigation tab)",
 *     operationId="getMitigationSupportingDocumentation",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Mitigation supporting documentation files returned successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID or insufficient permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: Team separation denies access to this risk."),
 * )
 */
class OpenApiGetMitigationSupportingDocumentation {}

/**
 * @OA\Post(
 *     path="/risks/{id}/mitigations/supporting-documentation",
 *     summary="Upload or remove a mitigation's supporting documentation files (Mitigation tab)",
 *     operationId="saveMitigationSupportingDocumentation",
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
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(property="unique_names", type="array", @OA\Items(type="string"), description="Unique names of previously-uploaded files to keep; omitted existing files are deleted."),
 *                 @OA\Property(property="file", type="string", format="binary", description="Optional new file to upload.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Mitigation supporting documentation saved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID, insufficient permission, or upload error."),
 *     @OA\Response(response=403, description="FORBIDDEN: Team separation denies access to this risk."),
 * )
 */
class OpenApiSaveMitigationSupportingDocumentation {}

/**
 * @OA\Post(
 *     path="/risks/batch-comment",
 *     summary="Add the same comment to multiple risks at once",
 *     operationId="batchCommentRisks",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"risk_ids", "comment"},
 *                 @OA\Property(
 *                     property="risk_ids",
 *                     type="array",
 *                     description="The IDs of the risks to comment on.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(property="comment", type="string", description="The comment text to add to every risk.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Comments processed.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="processed", type="integer", description="Number of risks the comment was added to."),
 *             @OA\Property(property="denied", type="integer", description="Number of risks skipped because the caller lacks per-risk access to them."),
 *             @OA\Property(property="total", type="integer", description="Number of ids submitted, after the request-size cap."),
 *             @OA\Property(property="truncated", type="boolean", description="Whether more ids were submitted than the cap allows.")
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing comment or risk_ids."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller holds no comment_risk_management permission at all."),
 * )
 */
class OpenApiBatchCommentRisks {}

/**
 * @OA\Post(
 *     path="/risks/batch-reassign-owner",
 *     summary="Reassign the same owner on multiple risks at once",
 *     operationId="batchReassignRiskOwner",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"risk_ids", "owner"},
 *                 @OA\Property(
 *                     property="risk_ids",
 *                     type="array",
 *                     description="The IDs of the risks to reassign.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(property="owner", type="integer", description="The uid of the new owner for every risk.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Owner reassignments processed.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="processed", type="integer", description="Number of risks reassigned."),
 *             @OA\Property(property="denied", type="integer", description="Number of risks skipped because the caller lacks per-risk access to them."),
 *             @OA\Property(property="not_found", type="integer", description="Number of ids that did not resolve to an existing risk."),
 *             @OA\Property(property="total", type="integer", description="Number of ids submitted, after the request-size cap."),
 *             @OA\Property(property="truncated", type="boolean", description="Whether more ids were submitted than the cap allows.")
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing risk_ids."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller holds no modify_risks permission at all."),
 * )
 */
class OpenApiBatchReassignRiskOwner {}

/**
 * @OA\Post(
 *     path="/risks/batch-reassign-mitigation-owner",
 *     summary="Reassign the same mitigation owner on multiple risks at once",
 *     operationId="batchReassignMitigationOwner",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"risk_ids", "mitigation_owner"},
 *                 @OA\Property(
 *                     property="risk_ids",
 *                     type="array",
 *                     description="The IDs of the risks whose mitigation owner should be reassigned.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(property="mitigation_owner", type="integer", description="The uid of the new mitigation owner for every risk.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Mitigation owner reassignments processed.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="processed", type="integer", description="Number of risks reassigned."),
 *             @OA\Property(property="denied", type="integer", description="Number of risks skipped because the caller lacks per-risk access to them."),
 *             @OA\Property(property="not_found", type="integer", description="Number of ids that did not resolve to an existing risk."),
 *             @OA\Property(property="total", type="integer", description="Number of ids submitted, after the request-size cap."),
 *             @OA\Property(property="truncated", type="boolean", description="Whether more ids were submitted than the cap allows.")
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing risk_ids."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller holds no plan_mitigations permission at all."),
 * )
 */
class OpenApiBatchReassignMitigationOwner {}

/**
 * @OA\Post(
 *     path="/risks/batch-update-status",
 *     summary="Set the same status on multiple risks at once",
 *     operationId="batchUpdateRiskStatus",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"risk_ids", "status"},
 *                 @OA\Property(
 *                     property="risk_ids",
 *                     type="array",
 *                     description="The IDs of the risks to update.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(property="status", type="integer", description="The `value` column of the status table row to apply to every risk.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Status updates processed.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="processed", type="integer", description="Number of risks updated."),
 *             @OA\Property(property="denied", type="integer", description="Number of risks skipped because the caller lacks per-risk access to them."),
 *             @OA\Property(property="total", type="integer", description="Number of ids submitted, after the request-size cap."),
 *             @OA\Property(property="truncated", type="boolean", description="Whether more ids were submitted than the cap allows.")
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing risk_ids."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller holds no modify_risks permission at all."),
 * )
 */
class OpenApiBatchUpdateRiskStatus {}

/**
 * @OA\Post(
 *     path="/risks/batch-close",
 *     summary="Close multiple risks at once",
 *     operationId="batchCloseRisks",
 *     tags={"risk_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"risk_ids"},
 *                 @OA\Property(
 *                     property="risk_ids",
 *                     type="array",
 *                     description="The IDs of the risks to close.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(property="close_reason", type="string", description="The close reason applied to every risk."),
 *                 @OA\Property(property="note", type="string", description="The close-out note applied to every risk.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Risks closed.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="processed", type="integer", description="Number of risks closed."),
 *             @OA\Property(property="denied", type="integer", description="Number of risks skipped because the caller lacks per-risk access to them."),
 *             @OA\Property(property="total", type="integer", description="Number of ids submitted, after the request-size cap."),
 *             @OA\Property(property="truncated", type="boolean", description="Whether more ids were submitted than the cap allows.")
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing risk_ids."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller holds no close_risks permission at all."),
 * )
 */
class OpenApiBatchCloseRisks {}

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
 *       response=400,
 *       description="BAD REQUEST: Missing ID, or the caller lacks the risk management permission.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The caller does not have access to this risk.",
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
 *                 @OA\Property(property="team", type="array", description="Teams the new risk belongs to. Omitting it (or sending it empty) assigns NO team — it does not assign every team.", @OA\Items(type="integer", format="int32")),
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
 *       description="Risk created successfully. Served by the same handler as POST /risks, so the envelope is identical.",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="status", type="integer", example=200),
 *         @OA\Property(property="status_message", type="string", description="The localized success message, naming the risk that was created. When associate_test was posted this is instead a JSON-encoded array of alert objects."),
 *         @OA\Property(
 *           property="data",
 *           type="object",
 *           @OA\Property(property="risk_id", type="integer", description="The display ID of the newly created risk (internal ID + 1000)."),
 *           @OA\Property(property="associate_test", type="integer", description="The posted associate_test value, echoed back unchanged.")
 *         )
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
 *     description="Partial update: any field omitted from the request keeps its stored value. To clear a field, send it explicitly with an empty value (e.g. `tags[]=`) — omitting it preserves rather than clears. `subject` must always be supplied. Also served as PATCH /risks/{id}.",
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
 *       response=400,
 *       description="BAD REQUEST: Missing ID, or the caller lacks the risk management permission.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The caller does not have access to this risk.",
 *     ),
 * )
 */
class OpenApiViewMitigation {}

/**
 * @OA\Post(
 *     path="/management/mitigation/add",
 *     summary="Add or update a mitigation for a risk",
 *     description="When the risk already has a mitigation this is a partial update: any field omitted from the request keeps its stored value. To clear a field, send it explicitly with an empty value — omitting it preserves rather than clears. When the risk has no mitigation yet, omitted fields take their defaults, since there is no stored value to preserve. Also served as PATCH /risks/{id}/mitigations.",
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
 *       response=400,
 *       description="BAD REQUEST: Missing ID, or the caller lacks the risk management permission.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The caller does not have access to this risk.",
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
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
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
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
 * )
 */
class OpenApiResidualScoringHistory {}

/**
 * @OA\Post(
 *     path="/management/risk/scoring-method",
 *     summary="Change a risk's scoring method and recompute its score",
 *     description="Changes the scoring method (Classic / CVSS / DREAD / OWASP / Custom / Contributing Risk) for an existing risk and recomputes the calculated risk against the values already stored for that method on the risk record. Requires the modify_risks permission and team-separation visibility into the risk.",
 *     operationId="updateRiskScoringMethod",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"id", "scoring_method"},
 *                 @OA\Property(property="id", type="integer", format="int32", description="External risk ID (internal ID + 1000)."),
 *                 @OA\Property(property="scoring_method", type="integer", description="Numeric scoring method: 1=Classic, 2=CVSS, 3=DREAD, 4=OWASP, 5=Custom, 6=Contributing Risk.", enum={1, 2, 3, 4, 5, 6})
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Scoring method changed successfully. Returns the rendered score-overview tab HTML.",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="Missing or invalid id / scoring_method, or caller lacks the modify_risks permission or team-separation visibility into the risk.",
 *     ),
 *     @OA\Response(
 *       response=404,
 *       description="No risk exists with the supplied id.",
 *     ),
 * )
 */
class OpenApiUpdateRiskScoringMethod {}

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
 * @OA\Get(
 *     path="/management/risk/auditLog",
 *     summary="Get the structured audit log for a single risk.",
 *     description="Returns Timestamp/Activity/User rows for one risk's own audit history (log_type risk/jira), classified server-side into a short activity key. Requires the riskmanagement permission and access to the risk (team separation).",
 *     operationId="riskAuditLog",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         in="query",
 *         name="id",
 *         required=true,
 *         description="The risk ID (1000-padded display id).",
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\Parameter(
 *         name="days",
 *         in="query",
 *         description="Number of days of audit log history to retrieve. Defaults to 7.",
 *         required=false,
 *         @OA\Schema(type="integer", default=7)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of audit log entries.",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="timestamp", type="string"),
 *                 @OA\Property(property="timestamp_display", type="string"),
 *                 @OA\Property(property="message", type="string"),
 *                 @OA\Property(property="detail", type="string", nullable=true, description="Field-by-field change detail parsed out of the message, when present."),
 *                 @OA\Property(property="activity", type="string"),
 *                 @OA\Property(property="user_id", type="integer"),
 *                 @OA\Property(property="user_name", type="string")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: Missing id, or user lacks riskmanagement permission/access to the risk.",
 *     ),
 * )
 */
class OpenApiRiskAuditLog {}

/**
 * @OA\Post(
 *     path="/management/risk/setProjectToRisk",
 *     summary="Associate a risk with a project",
 *     description="Sets the project association on the given risk. Used by the Risk Management workflow when a management review concludes that a risk should be tracked under a specific project. Requires the modify_risks permission and access to the risk.",
 *     operationId="setProjectToRisk",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         in="query",
 *         name="id",
 *         required=true,
 *         description="The risk ID (1000-padded display id).",
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"project_id"},
 *                 @OA\Property(property="project_id", type="integer", format="int32", description="The project ID to associate with this risk.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Project association set successfully.",
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: Missing id, missing/invalid project_id, or user lacks modify_risks permission/access to the risk.",
 *     ),
 * )
 */
class OpenApiSetProjectToRisk {}

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
 *     path="/risk_management/review_risk",
 *     summary="Get the unified Review Risk action queue (needs mitigation or review) in DataTables format",
 *     operationId="reviewRiskDatatable",
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
 *                 @OA\Property(property="action_type", type="string", enum={"all","mitigation","review"}, description="Action Type chip filter. Defaults to 'all'."),
 *                 @OA\Property(property="my_action_items", type="string", enum={"0","1"}, description="Whether to restrict results to rows the current user can act on. Defaults to '1'."),
 *                 @OA\Property(property="owner_filter", type="array", @OA\Items(type="string"), description="Secondary filters panel: restrict to rows whose owner exactly matches any of these values (from the filter_options endpoint's owners list). Omit or send empty for no filter."),
 *                 @OA\Property(property="team_filter", type="array", @OA\Items(type="string"), description="Secondary filters panel: restrict to rows whose team exactly matches any of these values (from the filter_options endpoint's teams list). Omit or send empty for no filter."),
 *                 @OA\Property(property="risk_level_filter", type="array", @OA\Items(type="string"), description="Secondary filters panel: restrict to rows whose risk level exactly matches any of these values (from the filter_options endpoint's levels list). Omit or send empty for no filter."),
 *                 @OA\Property(property="reviewer_filter", type="array", @OA\Items(type="string"), description="Secondary filters panel: restrict to rows whose reviewer exactly matches any of these values (from the filter_options endpoint's reviewers list). Omit or send empty for no filter.")
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
class OpenApiReviewRiskDatatable {}

/**
 * @OA\Post(
 *     path="/risk_management/review_risk/filtered_ids",
 *     summary="Resolve every risk id matching the Review Risk page's current filter set, across every page ('Select all N')",
 *     description="Unlike the DataTables endpoint above, which returns one page, this applies the identical filter decision (review_risk_evaluate_row()/review_risk_filter_ids(), includes/reporting.php) across the WHOLE matching set and returns every id -- so a caller can select every row a filter matches, not just the ones currently rendered. Refused with 400 when the match count exceeds REVIEW_RISK_SELECT_ALL_MAX (500, includes/api.php) -- narrow the filter and retry, since every subsequent bulk action fires one request per selected id rather than a single atomic bulk write.",
 *     operationId="reviewRiskFilteredIds",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="action_type", type="string", enum={"all","mitigation","review"}, description="Action Type chip filter. Defaults to 'all'."),
 *                 @OA\Property(property="my_action_items", type="string", enum={"0","1"}, description="Whether to restrict results to rows the current user can act on. Defaults to '1'."),
 *                 @OA\Property(property="status_scope", type="string", enum={"all","open","closed"}, description="All/Open/Closed status-scope toolbar control. Defaults to 'open'."),
 *                 @OA\Property(property="due_status", type="string", enum={"all","unreviewed","past_due","due_soon"}, description="Due-status toolbar control. Defaults to 'all'."),
 *                 @OA\Property(property="user_filter", type="array", @OA\Items(type="string"), description="Secondary filters panel: restrict to rows whose merged user roles (owner/manager/submitter/mitigation owner/reviewer/stakeholders) exactly match any of these values. Omit or send empty for no filter."),
 *                 @OA\Property(property="team_filter", type="array", @OA\Items(type="string"), description="Secondary filters panel: restrict to rows whose merged team/mitigation-team values exactly match any of these values. Omit or send empty for no filter."),
 *                 @OA\Property(property="risk_level_filter", type="array", @OA\Items(type="string"), description="Secondary filters panel: restrict to rows whose risk level exactly matches any of these values. Omit or send empty for no filter."),
 *                 @OA\Property(property="search", type="string", description="Global search term, matched the same way the toolbar search box's term is matched (subject, id/display-id, owner, category, tags, location, control number, reference id, submitted by, technology).")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Every matching risk id",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="ids", type="array", @OA\Items(type="integer"), description="Raw risk ids (NOT the +1000 display id) matching the given filters, across every page."),
 *         @OA\Property(property="total", type="integer", description="count(ids).")
 *       )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="The filter matches more risks than REVIEW_RISK_SELECT_ALL_MAX allows",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=400),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Caller lacks risk management permission",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string"), example={"You have no permission for risk management."}),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiReviewRiskFilteredIds {}

/**
 * @OA\Get(
 *     path="/risk_management/review_risk/filter_options",
 *     summary="Get the Owner/Team/Risk Level/Reviewer option lists for the Review Risk page's secondary filters panel",
 *     description="Owner, Team, and Reviewer lists are Org-Hierarchy-scoped the same way as every other enabled_users/team dropdown in the app (get_options_from_table() -> get_custom_table()); a non-admin caller under the Organizational Hierarchy Extra only sees their selected business unit's users/teams. Risk Level options come from the flat, unscoped risk_levels config table.",
 *     operationId="reviewRiskFilterOptions",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="Filter option lists",
 *       @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="owners", type="array", description="Org-Hierarchy-scoped enabled users, reduced to {value, name} -- never the full user row (which carries the password hash/salt).", @OA\Items(type="object", @OA\Property(property="value", type="integer"), @OA\Property(property="name", type="string"))),
 *         @OA\Property(property="teams", type="array", description="Org-Hierarchy-scoped teams, reduced to {value, name}.", @OA\Items(type="object", @OA\Property(property="value", type="integer"), @OA\Property(property="name", type="string"))),
 *         @OA\Property(property="levels", type="array", description="risk_levels rows reduced to {value, name, display_name, color}.", @OA\Items(type="object", @OA\Property(property="value", type="string"), @OA\Property(property="name", type="string"), @OA\Property(property="display_name", type="string"), @OA\Property(property="color", type="string"))),
 *         @OA\Property(property="reviewers", type="array", description="Same Org-Hierarchy-scoped {value, name} user list as 'owners' -- the Reviewer filter draws from the same user pool.", @OA\Items(type="object", @OA\Property(property="value", type="integer"), @OA\Property(property="name", type="string"))),
 *         @OA\Property(property="statuses", type="array", description="Risk status options reduced to {value, name}.", @OA\Items(type="object", @OA\Property(property="value", type="integer"), @OA\Property(property="name", type="string"))),
 *         @OA\Property(
 *             property="column_settings",
 *             type="object",
 *             nullable=true,
 *             description="The caller's own saved Review Risk column-picker state (user.custom_review_risk_display_settings), decoded. Null when the caller has never saved one. Same shape the save_custom_review_risk_display_settings endpoint stores.",
 *             @OA\Property(property="columns", type="array", description="List of [column_name, visibility] pairs.", @OA\Items(type="array", @OA\Items(type="string"))),
 *             @OA\Property(property="order", type="array", description="Saved left-to-right column order. Absent when no drag-reorder has been saved.", @OA\Items(type="string"))
 *         ),
 *         @OA\Property(
 *             property="active_columns",
 *             type="array",
 *             nullable=true,
 *             description="The site's Customization-Extra-active risk field set, resolved into the Review Risk grid's own column vocabulary (build_active_review_risk_columns()). NULL when the Customization Extra is not active, in which case the client keeps its built-in static column list instead. When non-null it REPLACES that static list: any built-in column absent from this array is neither offered in the Columns picker nor rendered.",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="key", type="string", description="The column's data-col key -- a basic field's resolved db-column name (e.g. team, category) or custom_field_{id} for a Customization Extra custom field."),
 *                 @OA\Property(property="label", type="string", description="Already-HTML-escaped display label for the column's Columns-picker checkbox."),
 *                 @OA\Property(property="group", type="string", enum={"RiskColumns", "MitigationColumns", "ReviewColumns"}, description="Which Columns-picker section the column belongs to, mapped from the field's Customization Extra tab_index.")
 *             )
 *         )
 *       )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Caller lacks risk management permission",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string"), example={"You have no permission for risk management."}),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiReviewRiskFilterOptions {}

/**
 * @OA\Post(
 *     path="/risk_management/save_custom_review_risk_display_settings",
 *     summary="Save the current user's column display settings for the Review Risk page",
 *     description="Persists the caller's column-picker preferences for the unified Review Risk action queue as a single flat list -- unlike the retired per-page display-settings endpoints this replaces, this is one column set, not a risk/mitigation/review three-way split, since Review Risk has one unified table.",
 *     operationId="saveCustomReviewRiskDisplaySettings",
 *     tags={"risk"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(
 *                     property="columns",
 *                     type="array",
 *                     description="List of [column_name, visibility] pairs, e.g. [[risk_score, 1], [team, 0]]. column_name must match ^[A-Za-z0-9_]+$; visibility is the string 0 or 1.",
 *                     @OA\Items(type="array", @OA\Items(type="string"))
 *                 ),
 *                 @OA\Property(
 *                     property="order",
 *                     type="array",
 *                     description="Optional. The caller's drag-reordered column sequence, as a flat list of column names in desired left-to-right order, e.g. [team, risk_score, responsible]. Each name must match ^[A-Za-z0-9_]+$ (custom_review_risk_column_order_is_valid()) -- an invalid entry fails the whole request with a 400, the same way an invalid columns entry does. The stored settings blob is REPLACED on every save, so a request that omits this key stores no order at all (clearing any previously saved one); send columns and order together to keep both. Stored names that no longer resolve to a real column are ignored when the order is applied.",
 *                     @OA\Items(type="string")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Display settings saved successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string"), example={"Successfully saved."}),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Missing or invalid columns/order",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=400),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string"), example={"No Data Available"}),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Caller lacks risk management permission",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string"), example={"You have no permission for risk management."}),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiSaveCustomReviewRiskDisplaySettings {}

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
