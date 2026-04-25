<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;

/**
 * @OA\Post(
 *     path="/exceptions/create",
 *     summary="Create a new policy or control exception.",
 *     operationId="createException",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"name", "owner"},
 *                 @OA\Property(
 *                     property="name",
 *                     type="string",
 *                     description="The name of the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="owner",
 *                     type="integer",
 *                     description="User ID of the exception owner."
 *                 ),
 *                 @OA\Property(
 *                     property="policy",
 *                     type="integer",
 *                     description="Policy document ID when creating a policy exception."
 *                 ),
 *                 @OA\Property(
 *                     property="framework",
 *                     type="integer",
 *                     description="ID of the framework associated with the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="control",
 *                     type="integer",
 *                     description="Control ID when creating a control exception."
 *                 ),
 *                 @OA\Property(
 *                     property="status",
 *                     type="string",
 *                     description="The status of the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="additional_stakeholders",
 *                     type="array",
 *                     description="User IDs of additional stakeholders.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(
 *                     property="creation_date",
 *                     type="string",
 *                     format="date",
 *                     description="The creation date of the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="review_frequency",
 *                     type="integer",
 *                     description="How frequently the exception should be reviewed (in days)."
 *                 ),
 *                 @OA\Property(
 *                     property="next_review_date",
 *                     type="string",
 *                     format="date",
 *                     description="The date of the next scheduled review."
 *                 ),
 *                 @OA\Property(
 *                     property="approval_date",
 *                     type="string",
 *                     format="date",
 *                     description="The date the exception was approved."
 *                 ),
 *                 @OA\Property(
 *                     property="approver",
 *                     type="integer",
 *                     description="User ID of the approver."
 *                 ),
 *                 @OA\Property(
 *                     property="approved",
 *                     type="boolean",
 *                     description="Whether the exception is approved."
 *                 ),
 *                 @OA\Property(
 *                     property="description",
 *                     type="string",
 *                     description="A description of the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="justification",
 *                     type="string",
 *                     description="The justification for the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="associated_risks",
 *                     type="array",
 *                     description="IDs of risks associated with the exception.",
 *                     @OA\Items(type="integer")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Exception created successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="approved", type="boolean"),
 *             @OA\Property(property="type", type="string", enum={"policy", "control"})
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiCreateException {}

/**
 * @OA\Post(
 *     path="/exceptions/update",
 *     summary="Update an existing exception.",
 *     operationId="updateException",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"exception_id"},
 *                 @OA\Property(
 *                     property="exception_id",
 *                     type="integer",
 *                     description="The ID of the exception to update."
 *                 ),
 *                 @OA\Property(
 *                     property="name",
 *                     type="string",
 *                     description="The name of the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="owner",
 *                     type="integer",
 *                     description="User ID of the exception owner."
 *                 ),
 *                 @OA\Property(
 *                     property="policy",
 *                     type="integer",
 *                     description="Policy document ID when updating a policy exception."
 *                 ),
 *                 @OA\Property(
 *                     property="framework",
 *                     type="integer",
 *                     description="ID of the framework associated with the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="control",
 *                     type="integer",
 *                     description="Control ID when updating a control exception."
 *                 ),
 *                 @OA\Property(
 *                     property="status",
 *                     type="string",
 *                     description="The status of the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="additional_stakeholders",
 *                     type="array",
 *                     description="User IDs of additional stakeholders.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(
 *                     property="creation_date",
 *                     type="string",
 *                     format="date",
 *                     description="The creation date of the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="review_frequency",
 *                     type="integer",
 *                     description="How frequently the exception should be reviewed (in days)."
 *                 ),
 *                 @OA\Property(
 *                     property="next_review_date",
 *                     type="string",
 *                     format="date",
 *                     description="The date of the next scheduled review."
 *                 ),
 *                 @OA\Property(
 *                     property="approval_date",
 *                     type="string",
 *                     format="date",
 *                     description="The date the exception was approved."
 *                 ),
 *                 @OA\Property(
 *                     property="approver",
 *                     type="integer",
 *                     description="User ID of the approver."
 *                 ),
 *                 @OA\Property(
 *                     property="approved",
 *                     type="boolean",
 *                     description="Whether the exception is approved."
 *                 ),
 *                 @OA\Property(
 *                     property="description",
 *                     type="string",
 *                     description="A description of the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="justification",
 *                     type="string",
 *                     description="The justification for the exception."
 *                 ),
 *                 @OA\Property(
 *                     property="associated_risks",
 *                     type="array",
 *                     description="IDs of risks associated with the exception.",
 *                     @OA\Items(type="integer")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Exception updated successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="approved_original", type="boolean"),
 *             @OA\Property(property="approved", type="boolean"),
 *             @OA\Property(property="type", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiUpdateException {}

/**
 * @OA\Post(
 *     path="/exceptions/delete",
 *     summary="Delete an exception.",
 *     operationId="deleteException",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"exception_id"},
 *                 @OA\Property(
 *                     property="exception_id",
 *                     type="integer",
 *                     description="The ID of the exception to delete."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Exception deleted successfully."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiDeleteException {}

/**
 * @OA\Post(
 *     path="/exceptions/approve",
 *     summary="Approve a pending exception.",
 *     operationId="approveException",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"exception_id"},
 *                 @OA\Property(
 *                     property="exception_id",
 *                     type="integer",
 *                     description="The ID of the exception to approve."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Exception approved successfully."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiApproveException {}

/**
 * @OA\Post(
 *     path="/exceptions/unapprove",
 *     summary="Revoke approval for an exception.",
 *     operationId="unapproveException",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"exception_id"},
 *                 @OA\Property(
 *                     property="exception_id",
 *                     type="integer",
 *                     description="The ID of the exception to unapprove."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Exception approval revoked successfully."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiUnapproveException {}

/**
 * @OA\Post(
 *     path="/exceptions/batch-delete",
 *     summary="Delete all exceptions under a policy or control, optionally filtered by approval status.",
 *     operationId="batchDeleteExceptions",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"parent_id", "type"},
 *                 @OA\Property(
 *                     property="parent_id",
 *                     type="integer",
 *                     description="The ID of the policy or control whose exceptions should be deleted."
 *                 ),
 *                 @OA\Property(
 *                     property="type",
 *                     type="string",
 *                     description="Whether to delete exceptions under a policy or control.",
 *                     enum={"policy", "control"}
 *                 ),
 *                 @OA\Property(
 *                     property="approved",
 *                     type="boolean",
 *                     description="Optional filter to delete only approved or unapproved exceptions."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Exceptions deleted successfully."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiBatchDeleteExceptions {}

/**
 * @OA\Get(
 *     path="/exceptions/tree",
 *     summary="Get exceptions as a treegrid structure.",
 *     operationId="exceptionsTree",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="type",
 *         in="query",
 *         description="The type of exceptions to retrieve.",
 *         required=true,
 *         @OA\Schema(
 *             type="string",
 *             enum={"policy", "control", "unapproved"}
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of treegrid row objects.",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiExceptionsTree {}

/**
 * @OA\Get(
 *     path="/exceptions/exception",
 *     summary="Get details for a single exception.",
 *     operationId="getException",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         description="The ID of the exception to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Exception object with all fields.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="additional_stakeholders", type="array", @OA\Items(type="integer")),
 *             @OA\Property(property="associated_risks", type="array", @OA\Items(type="integer")),
 *             additionalProperties=true
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiGetException {}

/**
 * @OA\Get(
 *     path="/exceptions/info",
 *     summary="Get a formatted exception for display including file download link.",
 *     operationId="getExceptionInfo",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         description="The ID of the exception to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="type",
 *         in="query",
 *         description="The type of exception.",
 *         required=true,
 *         @OA\Schema(
 *             type="string",
 *             enum={"policy", "control"}
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Formatted exception data object.",
 *         @OA\JsonContent(type="object", additionalProperties=true)
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiGetExceptionInfo {}

/**
 * @OA\Get(
 *     path="/exceptions/audit_log",
 *     summary="Get the audit log for exceptions.",
 *     operationId="exceptionsAuditLog",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
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
 *                 @OA\Property(property="message", type="string")
 *             )
 *         )
 *     )
 * )
 */
class OpenApiExceptionsAuditLog {}

/**
 * @OA\Get(
 *     path="/exceptions/status",
 *     summary="Get exception status summary information.",
 *     operationId="exceptionsStatus",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Status information object.",
 *         @OA\JsonContent(type="object", additionalProperties=true)
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiExceptionsStatus {}

/**
 * @OA\Get(
 *     path="/associated-exceptions/tree",
 *     summary="Get exceptions associated with a specific risk or control as a treegrid.",
 *     operationId="associatedExceptionsTree",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         description="The ID of the risk or control to retrieve associated exceptions for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="type",
 *         in="query",
 *         description="The type of parent entity (risk or control).",
 *         required=true,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of treegrid row objects.",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiAssociatedExceptionsTree {}

?>
