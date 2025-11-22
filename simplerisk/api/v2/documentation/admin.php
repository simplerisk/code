<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;

/**
* @OA\Get(
*     path="/admin/version",
*     summary="List SimpleRisk version information",
*     operationId="version",
*     tags={"Administrator Operations"},
*     security={{"ApiKeyAuth":{}}},
*     @OA\Response(
*       response=200,
*       description="SimpleRisk version information",
*     ),
*     @OA\Response(
*       response=403,
*       description="FORBIDDEN: The user does not having admin privileges.",
*     ),
* )
*/

class OpenApiAdminVersion {}

/**
 * @OA\Get(
 *     path="/admin/version/app",
 *     summary="List SimpleRisk application version information",
 *     operationId="appVersion",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk application version information",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not having admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminVersionApp {}

/**
 * @OA\Get(
 *     path="/admin/version/db",
 *     summary="List SimpleRisk database version information",
 *     operationId="dbVersion",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk database version information",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not having admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminVersionDB {}

/**
 * @OA\Post(
 *      path="/admin/upgrade/db",
 *      summary="Upgrade the SimpleRisk database",
 *      operationId="upgrade_db",
 *      tags={"Administrator Operations"},
 *      security={{"ApiKeyAuth":{}}},
 *      @OA\RequestBody(
 *          required=true,
 *          description="Upgrade the SimpleRisk database",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(
 *                  type="object",
 *                  required={"version"},
 *                  @OA\Property(
 *                      property="version",
 *                       type="string",
 *                       description="The target database version for the upgrade (format: YYYYMMDD-XXX)",
 *                       example="20241209-001",
 *                       pattern="^\\d{8}-\\d{3}$"
 *                  )
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Upgrade successful",
 *      ),
 *     @OA\Response(
 *          response=400,
 *          description="BAD REQUEST: A file and tracking_id value are required.",
 *     ),
 *      @OA\Response(
 *          response=403,
 *          description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *      ),
 *  )
 */
class OpenApiAdminUpgradeDB {}

/**
 * @OA\Delete(
 *     path="/admin/tag",
 *     summary="Delete tag",
 *     operationId="tagDelete",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *        parameter="id",
 *        in="query",
 *        name="id",
 *        description="The id of the tag you would like to delete.",
 *        required=true,
 *        @OA\Schema(
 *          type="integer",
 *        ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk tag deleted",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAdminTagsDelete {}

/**
 * @OA\Delete(
 *     path="/admin/tag/all",
 *     summary="Delete all tags",
 *     operationId="allTagDelete",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *        parameter="type",
 *        in="query",
 *        name="type",
 *        description="The type of tag you would like to delete.",
 *        required=true,
 *        @OA\Schema(
 *          type="string",
 *          enum={"risk", "asset", "test", "test_audit", "incident_management_destination", "incident_management_source", "questionnaire_pending_risk", "questionnaire_risk", "questionnaire_answer", "all"},
 *        ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk tags deleted",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="Invalid type",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAdminAllTagsDelete {}

/**
 * @OA\Get(
 *     path="/admin/governance/documents/maptocontrols",
 *     summary="Initiate an analysis of all document content to controls",
 *     operationId="mapDocumentsToControls",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="Mapped documents",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not having admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminMapDocumentsToControls {}

/**
 * @OA\Get(
 *     path="/admin/queue",
 *     summary="List queued tasks with optional filters for task type and status",
 *     operationId="listQueueTasks",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="task_type",
 *         in="query",
 *         description="Filter results by the task type (e.g., 'ai_document_to_control_chunker', 'send_email', etc.)",
 *         required=false,
 *         @OA\Schema(type="string", example="")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Filter results by task status. Use 'all' (default) to return all statuses. For multiple statuses, provide a comma-separated list (e.g., 'pending,in_progress').",
 *         required=false,
 *         @OA\Schema(type="string", example="all"),
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="List of queue tasks (optionally filtered)",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status_code", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="count", type="integer", example=5),
 *                 @OA\Property(
 *                     property="filters",
 *                     type="object",
 *                     @OA\Property(property="task_type", type="string", example="ai_document_to_control_chunker"),
 *                     @OA\Property(property="status", type="string", example="pending,in_progress")
 *                 ),
 *                 @OA\Property(
 *                     property="items",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="id", type="integer", example=42),
 *                         @OA\Property(property="task_type", type="string", example="ai_document_to_control_chunker"),
 *                         @OA\Property(property="status", type="string", example="pending"),
 *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-06T15:32:00Z"),
 *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-06T16:05:00Z")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have admin privileges."
 *     ),
 * )
 */

class OpenApiAdminQueue {}

/**
 * @OA\Get(
 *     path="/admin/queue/promises",
 *     summary="List all promises associated with a specific queue task",
 *     operationId="listPromisesByQueueTask",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="queue_task_id",
 *         in="query",
 *         description="The ID of the queue task to retrieve promises for",
 *         required=true,
 *         @OA\Schema(type="integer", example=123)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="List of promises associated with the given queue_task_id",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=42),
 *                 @OA\Property(property="promise_type", type="string", example="document_review"),
 *                 @OA\Property(property="reference_id", type="integer", example=10),
 *                 @OA\Property(property="current_stage", type="string", example="finalize"),
 *                 @OA\Property(property="status", type="string", example="pending"),
 *                 @OA\Property(property="state", type="string", example="active"),
 *                 @OA\Property(property="queue_task_id", type="integer", example=123),
 *                 @OA\Property(property="depends_on", type="string", example="41,40"),
 *                 @OA\Property(property="payload", type="object", example={"document_id":174, "triggered_at":1762621556}),
 *                 @OA\Property(property="description", type="string", example="Promise to finalize document review"),
 *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-06T15:32:00Z"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-06T16:05:00Z")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Bad Request: Missing or invalid queue_task_id"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="No promises found for the specified queue_task_id"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have admin privileges."
 *     )
 * )
 */
class OpenApiAdminPromisesByTask {}

?>