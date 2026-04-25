<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;
require_once(realpath(__DIR__ . '/../../../includes/upgrade.php'));

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

// Get the latest release version
global $releases;
$version = end($releases);
define('CURRENT_DB_VERSION', $version);
/**
 * @OA\Post(
 *      path="/admin/upgrade/db",
 *      summary="Upgrade the SimpleRisk database",
 *      operationId="upgrade_db",
 *      tags={"Administrator Operations"},
 *      security={{"ApiKeyAuth":{}}},
 *
 *      @OA\RequestBody(
 *          required=false,
 *          description="Upgrade the SimpleRisk database. If no version is provided, the latest available release will be used.",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(
 *                  type="object",
 *                  @OA\Property(
 *                      property="version",
 *                      type="string",
 *                      description="Optional target database version for the upgrade (format: YYYYMMDD-XXX). If omitted, the most recent release is used.",
 *                      example=CURRENT_DB_VERSION,
 *                      pattern="^\\d{8}-\\d{3}$"
 *                  )
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=200,
 *          description="Upgrade successful"
 *      ),
 *      @OA\Response(
 *          response=400,
 *          description="BAD REQUEST: Invalid version format or version not found."
 *      ),
 *      @OA\Response(
 *          response=403,
 *          description="FORBIDDEN: The user does not have the required permission to perform this action."
 *      )
 * )
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
 * @OA\Post(
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

/**
 * @OA\Post(
 *     path="/admin/write_debug_log",
 *     summary="Flush queued debug log messages to the Apache error log",
 *     operationId="writeDebugLog",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="Debug log messages written successfully.",
 *     ),
 * )
 */

class OpenApiAdminWriteDebugLog {}

/**
 * @OA\Get(
 *     path="/admin/users/all",
 *     summary="List all users",
 *     operationId="allUsers",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="List of all users",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="uid", type="integer", example=1),
 *                     @OA\Property(property="type", type="string", example="admin"),
 *                     @OA\Property(property="username", type="string", example="jsmith"),
 *                     @OA\Property(property="email", type="string", example="jsmith@example.com"),
 *                     @OA\Property(property="last_login", type="string", example="2026-01-15 10:30:00"),
 *                     @OA\Property(property="teams", type="string", example="1:2:3"),
 *                     @OA\Property(property="role", type="string", example="Administrator")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminUsersAll {}

/**
 * @OA\Get(
 *     path="/admin/users/enabled",
 *     summary="List all enabled users",
 *     operationId="enabledUsers",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="List of all enabled users",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="uid", type="integer", example=1),
 *                     @OA\Property(property="type", type="string", example="admin"),
 *                     @OA\Property(property="username", type="string", example="jsmith"),
 *                     @OA\Property(property="email", type="string", example="jsmith@example.com"),
 *                     @OA\Property(property="last_login", type="string", example="2026-01-15 10:30:00"),
 *                     @OA\Property(property="teams", type="string", example="1:2:3"),
 *                     @OA\Property(property="role", type="string", example="Administrator")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminUsersEnabled {}

/**
 * @OA\Get(
 *     path="/admin/users/disabled",
 *     summary="List all disabled users",
 *     operationId="disabledUsers",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="List of all disabled users",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="uid", type="integer", example=1),
 *                     @OA\Property(property="type", type="string", example="admin"),
 *                     @OA\Property(property="username", type="string", example="jsmith"),
 *                     @OA\Property(property="email", type="string", example="jsmith@example.com"),
 *                     @OA\Property(property="last_login", type="string", example="2026-01-15 10:30:00"),
 *                     @OA\Property(property="teams", type="string", example="1:2:3"),
 *                     @OA\Property(property="role", type="string", example="Administrator")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminUsersDisabled {}

/**
 * @OA\Post(
 *     path="/admin/fields/add",
 *     summary="Add a custom field",
 *     operationId="addCustomField",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Add a custom field (requires Customization Extra).",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"name", "field_type"},
 *                 @OA\Property(property="name", type="string", description="The name of the custom field."),
 *                 @OA\Property(property="field_type", type="string", description="The type of the custom field.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Custom field added successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminFieldsAdd {}

/**
 * @OA\Post(
 *     path="/admin/fields/delete",
 *     summary="Delete a custom field",
 *     operationId="deleteCustomField",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Delete a custom field (requires Customization Extra).",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"field_id"},
 *                 @OA\Property(property="field_id", type="integer", description="The ID of the custom field to delete.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Custom field deleted successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminFieldsDelete {}

/**
 * @OA\Get(
 *     path="/admin/fields/get",
 *     summary="Retrieve a custom field definition",
 *     operationId="getCustomField",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="field_id",
 *         in="query",
 *         description="The ID of the custom field to retrieve (requires Customization Extra).",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Custom field definition retrieved successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminFieldsGet {}

/**
 * @OA\Get(
 *     path="/admin/tables/fullData",
 *     summary="Retrieve unfiltered data from a SimpleRisk lookup table",
 *     operationId="getTableData",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="table",
 *         in="query",
 *         description="The table name to retrieve data from.",
 *         required=true,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Table data retrieved successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminTablesFullData {}

/**
 * @OA\Get(
 *     path="/admin/risk_catalog/datatable",
 *     summary="Get risk catalog items in DataTables format",
 *     operationId="riskCatalogDatatable",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="draw",
 *         in="query",
 *         description="DataTables draw counter.",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="DataTables-formatted risk catalog response.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=100),
 *             @OA\Property(property="recordsFiltered", type="integer", example=100),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 * )
 */

class OpenApiAdminRiskCatalogDatatable {}

/**
 * @OA\Get(
 *     path="/admin/risk_catalog/detail",
 *     summary="Get details for a single risk catalog entry",
 *     operationId="riskCatalogDetail",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="risk_id",
 *         in="query",
 *         description="The ID of the risk catalog entry to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk catalog entry details retrieved successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminRiskCatalogDetail {}

/**
 * @OA\Post(
 *     path="/admin/risk_catalog/update_order",
 *     summary="Update the display order of risk catalog entries",
 *     operationId="updateRiskCatalogOrder",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Update the display order of risk catalog entries.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(
 *                     property="order",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="Ordered array of risk catalog entry IDs."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk catalog order updated successfully.",
 *     ),
 * )
 */

class OpenApiAdminRiskCatalogUpdateOrder {}

/**
 * @OA\Post(
 *     path="/admin/risk_catalog/add_risk_catalog",
 *     summary="Add a new risk catalog entry",
 *     operationId="addRiskCatalog",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Add a new risk catalog entry.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"name"},
 *                 @OA\Property(property="number", type="string", description="The catalog entry number."),
 *                 @OA\Property(property="risk_grouping", type="string", description="The grouping for the risk catalog entry."),
 *                 @OA\Property(property="name", type="string", description="The name of the risk catalog entry."),
 *                 @OA\Property(property="description", type="string", description="A description of the risk catalog entry."),
 *                 @OA\Property(property="risk_function", type="string", description="The risk function associated with the entry.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk catalog entry added successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminRiskCatalogAdd {}

/**
 * @OA\Post(
 *     path="/admin/risk_catalog/update_risk_catalog",
 *     summary="Update an existing risk catalog entry",
 *     operationId="updateRiskCatalog",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Update an existing risk catalog entry.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"id"},
 *                 @OA\Property(property="id", type="integer", description="The ID of the risk catalog entry to update."),
 *                 @OA\Property(property="number", type="string", description="The catalog entry number."),
 *                 @OA\Property(property="risk_grouping", type="string", description="The grouping for the risk catalog entry."),
 *                 @OA\Property(property="name", type="string", description="The name of the risk catalog entry."),
 *                 @OA\Property(property="description", type="string", description="A description of the risk catalog entry."),
 *                 @OA\Property(property="risk_function", type="string", description="The risk function associated with the entry.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk catalog entry updated successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminRiskCatalogUpdate {}

/**
 * @OA\Post(
 *     path="/admin/risk_catalog/delete_risk_catalog",
 *     summary="Delete a risk catalog entry",
 *     operationId="deleteRiskCatalog",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Delete a risk catalog entry.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"id"},
 *                 @OA\Property(property="id", type="integer", description="The ID of the risk catalog entry to delete.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk catalog entry deleted successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminRiskCatalogDelete {}

/**
 * @OA\Post(
 *     path="/admin/risk_catalog/swap_groups",
 *     summary="Swap the grouping of two risk catalog entries",
 *     operationId="swapRiskCatalogGroups",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Swap the grouping of two risk catalog entries.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(property="id1", type="integer", description="The ID of the first risk catalog entry."),
 *                 @OA\Property(property="id2", type="integer", description="The ID of the second risk catalog entry.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Risk catalog groups swapped successfully.",
 *     ),
 * )
 */

class OpenApiAdminRiskCatalogSwapGroups {}

/**
 * @OA\Get(
 *     path="/admin/threat_catalog/datatable",
 *     summary="Get threat catalog items in DataTables format",
 *     operationId="threatCatalogDatatable",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="draw",
 *         in="query",
 *         description="DataTables draw counter.",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="DataTables-formatted threat catalog response.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=100),
 *             @OA\Property(property="recordsFiltered", type="integer", example=100),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 * )
 */

class OpenApiAdminThreatCatalogDatatable {}

/**
 * @OA\Get(
 *     path="/admin/threat_catalog/detail",
 *     summary="Get details for a single threat catalog entry",
 *     operationId="threatCatalogDetail",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="threat_id",
 *         in="query",
 *         description="The ID of the threat catalog entry to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Threat catalog entry details retrieved successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminThreatCatalogDetail {}

/**
 * @OA\Post(
 *     path="/admin/threat_catalog/update_order",
 *     summary="Update the display order of threat catalog entries",
 *     operationId="updateThreatCatalogOrder",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Update the display order of threat catalog entries.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(
 *                     property="order",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="Ordered array of threat catalog entry IDs."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Threat catalog order updated successfully.",
 *     ),
 * )
 */

class OpenApiAdminThreatCatalogUpdateOrder {}

/**
 * @OA\Post(
 *     path="/admin/threat_catalog/add_threat_catalog",
 *     summary="Add a new threat catalog entry",
 *     operationId="addThreatCatalog",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Add a new threat catalog entry.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"name"},
 *                 @OA\Property(property="number", type="string", description="The catalog entry number."),
 *                 @OA\Property(property="threat_grouping", type="string", description="The grouping for the threat catalog entry."),
 *                 @OA\Property(property="name", type="string", description="The name of the threat catalog entry."),
 *                 @OA\Property(property="description", type="string", description="A description of the threat catalog entry.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Threat catalog entry added successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminThreatCatalogAdd {}

/**
 * @OA\Post(
 *     path="/admin/threat_catalog/update_threat_catalog",
 *     summary="Update an existing threat catalog entry",
 *     operationId="updateThreatCatalog",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Update an existing threat catalog entry.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"id"},
 *                 @OA\Property(property="id", type="integer", description="The ID of the threat catalog entry to update."),
 *                 @OA\Property(property="number", type="string", description="The catalog entry number."),
 *                 @OA\Property(property="threat_grouping", type="string", description="The grouping for the threat catalog entry."),
 *                 @OA\Property(property="name", type="string", description="The name of the threat catalog entry."),
 *                 @OA\Property(property="description", type="string", description="A description of the threat catalog entry.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Threat catalog entry updated successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminThreatCatalogUpdate {}

/**
 * @OA\Post(
 *     path="/admin/threat_catalog/delete_threat_catalog",
 *     summary="Delete a threat catalog entry",
 *     operationId="deleteThreatCatalog",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Delete a threat catalog entry.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"id"},
 *                 @OA\Property(property="id", type="integer", description="The ID of the threat catalog entry to delete.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Threat catalog entry deleted successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminThreatCatalogDelete {}

/**
 * @OA\Post(
 *     path="/admin/column_settings/save_column_settings",
 *     summary="Save column selection display settings for a datatable view",
 *     operationId="saveColumnSettings",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Save column selection display settings for a datatable view.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"view"},
 *                 @OA\Property(property="view", type="string", description="The datatable view identifier."),
 *                 @OA\Property(property="columns", type="array", @OA\Items(type="string"), description="Array of column identifiers to display.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Column settings saved successfully.",
 *     ),
 * )
 */

class OpenApiAdminSaveColumnSettings {}

/**
 * @OA\Post(
 *     path="/admin/incidentmanagement",
 *     summary="Enable or disable the Incident Management Extra",
 *     operationId="incidentManagement",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Enable or disable the Incident Management Extra.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(
 *                     property="action",
 *                     type="string",
 *                     enum={"enable", "disable"},
 *                     description="The action to perform on the Incident Management Extra."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Incident Management Extra action performed successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminIncidentManagement {}

/**
 * @OA\Get(
 *     path="/complianceforgescf/status",
 *     summary="Get the status of the ComplianceForge SCF Extra",
 *     operationId="complianceforgescfStatus",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="ComplianceForge SCF Extra status retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="enabled", type="boolean", example=true)
 *         )
 *     ),
 * )
 */

class OpenApiComplianceforgescfStatus {}

/**
 * @OA\Get(
 *     path="/complianceforgescf/enable",
 *     summary="Enable the ComplianceForge SCF Extra",
 *     operationId="complianceforgescfEnable",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="ComplianceForge SCF Extra enabled successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiComplianceforgescfEnable {}

/**
 * @OA\Get(
 *     path="/complianceforgescf/disable",
 *     summary="Disable the ComplianceForge SCF Extra",
 *     operationId="complianceforgescfDisable",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="ComplianceForge SCF Extra disabled successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiComplianceforgescfDisable {}

/**
 * @OA\Post(
 *     path="/one_click_upgrade",
 *     summary="Trigger a one-click upgrade of the SimpleRisk application",
 *     operationId="oneClickUpgrade",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="One-click upgrade triggered successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiOneClickUpgrade {}

/**
 * @OA\Get(
 *     path="/role_responsibilities/get_responsibilities",
 *     summary="Get the responsibilities and permissions assigned to a role",
 *     operationId="getResponsibilitiesByRoleId",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="role_id",
 *         in="query",
 *         description="The ID of the role to retrieve responsibilities for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Role responsibilities retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="admin", type="boolean", example=false),
 *             @OA\Property(property="default", type="boolean", example=false),
 *             @OA\Property(property="value", type="string", example="analyst"),
 *             @OA\Property(
 *                 property="responsibilities",
 *                 type="array",
 *                 @OA\Items(type="object")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiRoleResponsibilitiesGet {}

?>