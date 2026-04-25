<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;

/**
 * @OA\Post(
 *     path="/documents/create",
 *     summary="Create a new policy or procedure document.",
 *     operationId="createDocument",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"document_type", "document_name"},
 *                 @OA\Property(
 *                     property="document_type",
 *                     type="string",
 *                     description="The type of document to create.",
 *                     enum={"policies", "procedures"}
 *                 ),
 *                 @OA\Property(
 *                     property="document_name",
 *                     type="string",
 *                     description="The name of the document."
 *                 ),
 *                 @OA\Property(
 *                     property="framework_ids",
 *                     type="array",
 *                     description="IDs of frameworks to associate with the document.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(
 *                     property="control_ids",
 *                     type="array",
 *                     description="IDs of controls to associate with the document.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(
 *                     property="parent",
 *                     type="integer",
 *                     description="ID of the parent document."
 *                 ),
 *                 @OA\Property(
 *                     property="status",
 *                     type="string",
 *                     description="The status of the document."
 *                 ),
 *                 @OA\Property(
 *                     property="creation_date",
 *                     type="string",
 *                     format="date",
 *                     description="The creation date of the document."
 *                 ),
 *                 @OA\Property(
 *                     property="last_review_date",
 *                     type="string",
 *                     format="date",
 *                     description="The date the document was last reviewed."
 *                 ),
 *                 @OA\Property(
 *                     property="review_frequency",
 *                     type="integer",
 *                     description="How frequently the document should be reviewed (in days)."
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
 *                     description="The date the document was approved."
 *                 ),
 *                 @OA\Property(
 *                     property="document_owner",
 *                     type="integer",
 *                     description="User ID of the document owner."
 *                 ),
 *                 @OA\Property(
 *                     property="additional_stakeholders",
 *                     type="array",
 *                     description="User IDs of additional stakeholders.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(
 *                     property="approver",
 *                     type="integer",
 *                     description="User ID of the approver."
 *                 ),
 *                 @OA\Property(
 *                     property="team_ids",
 *                     type="array",
 *                     description="IDs of teams associated with the document.",
 *                     @OA\Items(type="integer")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Document created successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string"),
 *             @OA\Property(property="document_type", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: An error occurred while creating the document."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have governance or add_documentation permission."
 *     )
 * )
 */
class OpenApiCreateDocument {}

/**
 * @OA\Post(
 *     path="/documents/update",
 *     summary="Update an existing policy or procedure document.",
 *     operationId="updateDocument",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"document_id"},
 *                 @OA\Property(
 *                     property="document_id",
 *                     type="integer",
 *                     description="The ID of the document to update."
 *                 ),
 *                 @OA\Property(
 *                     property="document_type",
 *                     type="string",
 *                     description="The type of document.",
 *                     enum={"policies", "procedures"}
 *                 ),
 *                 @OA\Property(
 *                     property="document_name",
 *                     type="string",
 *                     description="The name of the document."
 *                 ),
 *                 @OA\Property(
 *                     property="framework_ids",
 *                     type="array",
 *                     description="IDs of frameworks to associate with the document.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(
 *                     property="control_ids",
 *                     type="array",
 *                     description="IDs of controls to associate with the document.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(
 *                     property="parent",
 *                     type="integer",
 *                     description="ID of the parent document."
 *                 ),
 *                 @OA\Property(
 *                     property="status",
 *                     type="string",
 *                     description="The status of the document."
 *                 ),
 *                 @OA\Property(
 *                     property="creation_date",
 *                     type="string",
 *                     format="date",
 *                     description="The creation date of the document."
 *                 ),
 *                 @OA\Property(
 *                     property="last_review_date",
 *                     type="string",
 *                     format="date",
 *                     description="The date the document was last reviewed."
 *                 ),
 *                 @OA\Property(
 *                     property="review_frequency",
 *                     type="integer",
 *                     description="How frequently the document should be reviewed (in days)."
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
 *                     description="The date the document was approved."
 *                 ),
 *                 @OA\Property(
 *                     property="document_owner",
 *                     type="integer",
 *                     description="User ID of the document owner."
 *                 ),
 *                 @OA\Property(
 *                     property="additional_stakeholders",
 *                     type="array",
 *                     description="User IDs of additional stakeholders.",
 *                     @OA\Items(type="integer")
 *                 ),
 *                 @OA\Property(
 *                     property="approver",
 *                     type="integer",
 *                     description="User ID of the approver."
 *                 ),
 *                 @OA\Property(
 *                     property="team_ids",
 *                     type="array",
 *                     description="IDs of teams associated with the document.",
 *                     @OA\Items(type="integer")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Document updated successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="document_type", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: An error occurred while updating the document."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiUpdateDocument {}

/**
 * @OA\Post(
 *     path="/documents/delete",
 *     summary="Delete a document (POST variant).",
 *     operationId="deleteDocumentPost",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"document_id"},
 *                 @OA\Property(
 *                     property="document_id",
 *                     type="integer",
 *                     description="The ID of the document to delete."
 *                 ),
 *                 @OA\Property(
 *                     property="version",
 *                     type="integer",
 *                     description="The version of the document to delete."
 *                 ),
 *                 @OA\Property(
 *                     property="document_type",
 *                     type="string",
 *                     description="The type of document to delete."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Document deleted successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="document_type", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiDeleteDocumentPost {}

?>
