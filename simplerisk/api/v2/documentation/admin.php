<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// @phan-suppress-next-line PhanUnreferencedUseNormal -- OA alias used in PHPDoc @OA annotations
use OpenApi\Annotations as OA;

// Annotations describe the API contract statically — see comment in general.php.
// The "version" example below is a static placeholder; bump it at release time
// so the in-product Swagger UI and the published Postman collection both show
// a sensible default. The endpoint itself always uses the most recent release
// when called without a version argument, regardless of this example value.

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

// The "example" value below is a static placeholder so this annotation parses
// without a live SimpleRisk runtime (the prior implementation read the latest
// release from the global $releases set up by simplerisk/includes/upgrade.php).
// It is overwritten with the actual current release string at runtime by
// simplerisk/api/v2/documentation/index.php so the in-product Swagger UI keeps
// showing the live version. Update this placeholder at release time as a
// belt-and-suspenders fallback for offline tooling that doesn't run the
// runtime patch — i.e. the GitHub Actions Postman collection generator.
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
 *          description="Upgrade the SimpleRisk database. With no version, the full upgrade chain runs from the database's current version.",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(
 *                  type="object",
 *                  @OA\Property(
 *                      property="version",
 *                      type="string",
 *                      description="Optional. When given, applies exactly that one release's migration -- a single hop, for targeting one migration during development. When OMITTED, the full chain runs from wherever the database actually is, finishing with the migration for the release currently in development if there is one; that is the mode to use for testing a release that has no version number yet.",
 *                      example="20260909-001",
 *                      pattern="^\\d{8}-\\d{3}$"
 *                  )
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=200,
 *          description="Upgrade successful. Also returned when the requested release is the newest one this code knows about and its migration deliberately does not advance the database version, because that release has not been cut yet; the status message says so."
 *      ),
 *      @OA\Response(
 *          response=400,
 *          description="BAD REQUEST: Invalid version format, version not found, or no upgrade function exists for that release."
 *      ),
 *      @OA\Response(
 *          response=403,
 *          description="FORBIDDEN: The user does not have the required permission to perform this action."
 *      ),
 *      @OA\Response(
 *          response=409,
 *          description="CONFLICT: another upgrade channel currently holds the instance-wide upgrade lock. Nothing was changed — retry once it completes. Distinct from 500 on purpose: this is an expected, self-clearing condition that hosted automation should retry rather than alert on."
 *      ),
 *      @OA\Response(
 *          response=500,
 *          description="The upgrade did not complete: the database user is missing required privileges, the migration raised an error, or it finished without advancing the database version. Detail is written to the server log rather than returned, since it can contain schema and path information."
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
 *     description="Admin-only. Only exists while the Customization Extra is active.",
 *     operationId="addCustomField",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Add a custom field.",
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
 *       @OA\JsonContent(
 *           @OA\Property(property="status_message", type="string", example="Success: The item was added successfully."),
 *           @OA\Property(
 *               property="data",
 *               type="object",
 *               description="The newly created field. Only present on success.",
 *               @OA\Property(property="id", type="integer", example=42),
 *               @OA\Property(property="name", type="string", example="Data classification"),
 *               @OA\Property(property="type", type="string", example="dropdown"),
 *               @OA\Property(property="required", type="integer", example=0)
 *           )
 *       )
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
 *     description="Admin-only. Only exists while the Customization Extra is active.",
 *     operationId="deleteCustomField",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Delete a custom field.",
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
 *     description="Admin-only. Only exists while the Customization Extra is active.",
 *     operationId="getCustomField",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="field_id",
 *         in="query",
 *         description="The ID of the custom field to retrieve.",
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
 *     path="/admin/fields/list",
 *     summary="List custom fields for a field group",
 *     description="Admin-only. Only exists while the Customization Extra is active.",
 *     operationId="listCustomFields",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="fgroup",
 *         in="query",
 *         description="The field group to list custom fields for.",
 *         required=true,
 *         @OA\Schema(type="string", enum={"asset", "risk", "project", "framework", "control"})
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Array of custom field objects.",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="BAD REQUEST: The fgroup parameter is missing or invalid.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminFieldsList {}

/**
 * @OA\Patch(
 *     path="/admin/fields/{id}",
 *     summary="Update a custom field",
 *     description="Admin-only. Only exists while the Customization Extra is active.",
 *     operationId="updateCustomField",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the custom field to update.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         description="Update a custom field's name, required flag, and alphabetical order.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"name"},
 *                 @OA\Property(property="name", type="string", description="The name of the custom field."),
 *                 @OA\Property(property="required", type="integer", enum={0, 1}, description="Whether the field is required."),
 *                 @OA\Property(property="encryption", type="integer", enum={0, 1}, description="Whether the field is encrypted. Omit to preserve the current value."),
 *                 @OA\Property(property="alphabetical_order", type="integer", enum={0, 1}, description="Whether the field's options are sorted alphabetically.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Custom field updated successfully.",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="BAD REQUEST: The id or name parameter is missing.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 *     @OA\Response(
 *       response=404,
 *       description="NOT FOUND: No custom field exists with the specified id.",
 *     ),
 * )
 */

class OpenApiAdminFieldsUpdate {}

/**
 * @OA\Get(
 *     path="/customization/templateGroups",
 *     summary="List template groups for a field group",
 *     description="Admin-only. Requires the Customization Extra to be active.",
 *     operationId="getTemplateGroups",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="fgroup",
 *         in="query",
 *         description="The field group to list template groups for.",
 *         required=false,
 *         @OA\Schema(type="string", enum={"asset", "risk", "project", "framework", "control"}, default="risk")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of template group objects.",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="Default"),
 *                 @OA\Property(property="fgroup", type="string", example="risk"),
 *                 @OA\Property(property="is_default", type="integer", example=1)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="BAD REQUEST: The fgroup parameter is invalid.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.",
 *     ),
 * )
 */

class OpenApiCustomizationTemplateGroupsList {}

/**
 * @OA\Post(
 *     path="/customization/templateGroups",
 *     summary="Add a template group",
 *     description="Admin-only. Requires the Customization Extra to be active.",
 *     operationId="addTemplateGroup",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"name"},
 *                 @OA\Property(property="name", type="string", maxLength=100, description="The name of the template group."),
 *                 @OA\Property(property="fgroup", type="string", enum={"asset", "risk", "project", "framework", "control"}, default="risk", description="The field group the template group belongs to."),
 *                 @OA\Property(property="clone_from", type="integer", description="Optional id of an existing template group in the same fgroup whose layout (its custom_template rows) is copied into the new group. Ignored when it belongs to a different fgroup; omit it to create an empty group.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The refreshed list of template groups for the given fgroup. When clone_from was given, the new group starts with a copy of that group's layout.",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="BAD REQUEST: The name is missing or longer than 100 characters, the fgroup is invalid, or a template group with that name already exists in the fgroup.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.",
 *     ),
 * )
 */

class OpenApiCustomizationTemplateGroupsAdd {}

/**
 * @OA\Patch(
 *     path="/customization/templateGroups/{id}",
 *     summary="Rename a template group",
 *     description="Admin-only. Requires the Customization Extra to be active.",
 *     operationId="updateTemplateGroup",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the template group to update.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"name"},
 *                 @OA\Property(property="name", type="string", description="The new name of the template group.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The refreshed list of template groups for the group's fgroup.",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="BAD REQUEST: The name is missing, or another template group with that name already exists in the fgroup.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.",
 *     ),
 *     @OA\Response(
 *       response=404,
 *       description="NOT FOUND: No template group exists with the specified id.",
 *     ),
 * )
 */

class OpenApiCustomizationTemplateGroupsUpdate {}

/**
 * @OA\Delete(
 *     path="/customization/templateGroups/{id}",
 *     summary="Delete a template group",
 *     description="Admin-only. Requires the Customization Extra to be active. Also deletes any custom_template rows belonging to the group. Refuses to delete a fgroup's Default group.",
 *     operationId="deleteTemplateGroup",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the template group to delete.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The refreshed list of template groups for the group's fgroup.",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="BAD REQUEST: The specified group is the fgroup's Default group and cannot be deleted.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.",
 *     ),
 *     @OA\Response(
 *       response=404,
 *       description="NOT FOUND: No template group exists with the specified id.",
 *     ),
 * )
 */

class OpenApiCustomizationTemplateGroupsDelete {}

/**
 * @OA\Get(
 *     path="/organizational_hierarchy/templateAssignments",
 *     summary="Get template-group to business-unit assignments for a field group",
 *     description="Admin-only. Requires the Customization Extra to be active.",
 *     operationId="getTemplateAssignments",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="fgroup",
 *         in="query",
 *         description="The field group to look up template groups for.",
 *         required=false,
 *         @OA\Schema(type="string", enum={"asset", "risk", "project", "framework", "control"}, default="risk")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The business units available for assignment, and each template group in the fgroup with its currently assigned business_unit_ids.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="business_units",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="value", type="integer", example=1),
 *                     @OA\Property(property="name", type="string", example="Finance")
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="groups",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="name", type="string", example="Default"),
 *                     @OA\Property(property="fgroup", type="string", example="risk"),
 *                     @OA\Property(
 *                         property="business_unit_ids",
 *                         type="array",
 *                         @OA\Items(type="integer"),
 *                         example={1, 3}
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="BAD REQUEST: The fgroup parameter is invalid.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.",
 *     ),
 * )
 */

class OpenApiCustomizationTemplateAssignmentsGet {}

/**
 * @OA\Post(
 *     path="/organizational_hierarchy/templateAssignments",
 *     summary="Save template-group to business-unit assignments for a field group",
 *     description="Admin-only. Requires the Customization Extra AND the Organizational Hierarchy Extra to both be active. IMPORTANT: this performs a full replace of every template group's business-unit assignment in the fgroup -- any group id in the fgroup that is missing from business_unit_ids has its assignments cleared, not left unchanged. Callers must always submit the complete current+edited mapping for every group in the fgroup, never a single-group delta.",
 *     operationId="saveTemplateAssignments",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"business_unit_ids"},
 *                 @OA\Property(property="fgroup", type="string", enum={"asset", "risk", "project", "framework", "control"}, default="risk", description="The field group whose template groups are being assigned."),
 *                 @OA\Property(
 *                     property="business_unit_ids",
 *                     type="object",
 *                     description="A map keyed by template_group_id whose values are lists of business unit ids -- form-encoded as business_unit_ids[1][]=3&business_unit_ids[1][]=5 to assign template group 1 to business units 3 and 5, with an empty list clearing a group. MUST include every template group id in the fgroup: any omitted group's assignments are wiped.",
 *                     @OA\AdditionalProperties(type="array", @OA\Items(type="integer"), description="Business unit ids assigned to the template group named by the key; an empty list clears that group's assignment.")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The refreshed business units and template groups (with business_unit_ids) for the given fgroup.",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="BAD REQUEST: business_unit_ids is not an array/map.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges, the Customization Extra is not active, or the Organizational Hierarchy Extra is not active.",
 *     ),
 * )
 */

class OpenApiCustomizationTemplateAssignmentsSave {}

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
 *     summary="Get the status of the Secure Controls Framework (SCF) Extra",
 *     operationId="complianceforgescfStatus",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Secure Controls Framework (SCF) Extra status retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="enabled", type="boolean", example=true)
 *         )
 *     ),
 * )
 */

class OpenApiComplianceforgescfStatus {}

/**
 * @OA\Post(
 *     path="/complianceforgescf/enable",
 *     summary="Enable the Secure Controls Framework (SCF) Extra",
 *     operationId="complianceforgescfEnable",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="Secure Controls Framework (SCF) Extra enabled successfully.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiComplianceforgescfEnable {}

/**
 * @OA\Post(
 *     path="/complianceforgescf/disable",
 *     summary="Disable the Secure Controls Framework (SCF) Extra",
 *     operationId="complianceforgescfDisable",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="Secure Controls Framework (SCF) Extra disabled successfully.",
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

/**
 * @OA\Get(
 *     path="/admin/settings/catalog",
 *     summary="List the Settings Hub catalog",
 *     description="Returns every Settings Hub tile visible to the current admin.",
 *     operationId="adminSettingsCatalog",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Catalog payload",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="OK"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="tiles", type="array",
 *                     @OA\Items(
 *                         @OA\Property(property="key", type="string", example="user_management"),
 *                         @OA\Property(property="label", type="string", example="User Management"),
 *                         @OA\Property(property="description", type="string"),
 *                         @OA\Property(property="path", type="string", example="admin/user_management.php"),
 *                         @OA\Property(property="tags", type="array", @OA\Items(type="string")),
 *                         @OA\Property(property="favorited", type="boolean"),
 *                         @OA\Property(
 *                             property="state",
 *                             type="string",
 *                             enum={"activated","deactivated","uninstalled","ready_to_download","purchase"},
 *                             description="Activation state for Extras tiles. Non-Extras tiles always report 'activated'. Tiles in 'uninstalled' state are upgraded to 'ready_to_download' or 'purchase' by a deferred GET /admin/settings/extras/licenses call."
 *                         ),
 *                         @OA\Property(
 *                             property="extra_name",
 *                             type="string",
 *                             nullable=true,
 *                             description="Canonical Extra slug (e.g. 'jira', 'vulnmgmt'). Non-null on every Extras-tag tile; null on non-Extras tiles.",
 *                             example="jira"
 *                         ),
 *                         @OA\Property(
 *                             property="sub_hub",
 *                             type="object",
 *                             nullable=true,
 *                             description="If present, clicking this tile in 'activated' state enters a sub-hub view in the Settings Hub instead of navigating. Currently used only by incident_management_activation.",
 *                             @OA\Property(property="section_key", type="string", example="incident_management"),
 *                             @OA\Property(property="heading", type="string", description="Localized heading for the sub-hub view", example="Incident Management"),
 *                             @OA\Property(
 *                                 property="tiles",
 *                                 type="array",
 *                                 @OA\Items(
 *                                     type="object",
 *                                     @OA\Property(property="key", type="string", example="example_sub_tile"),
 *                                     @OA\Property(property="label", type="string", description="Localized sub-tile label", example="Settings"),
 *                                     @OA\Property(property="path", type="string", description="Path relative to simplerisk/, may include query string", example="admin/example.php?tab=settings")
 *                                 )
 *                             )
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="Not authenticated",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminSettingsCatalog {}

/**
 * @OA\Get(
 *     path="/admin/settings/extras/licenses",
 *     summary="List Extras the customer is licensed for",
 *     description="Returns the Extra slugs the current customer has purchased. Used by the Settings Hub to upgrade uninstalled tiles from 'Checking…' to 'Ready to Download' or 'Purchase'.",
 *     operationId="adminSettingsExtrasLicenses",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="License list",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="OK"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="licensed",
 *                     type="array",
 *                     @OA\Items(type="string"),
 *                     example={"jira","incident_management"}
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="Not authenticated",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminSettingsExtrasLicenses {}

/**
 * @OA\Post(
 *     path="/admin/license/refresh",
 *     summary="Force an immediate license check against the licensing service",
 *     description="Synchronously runs /license/check and rewrites the local entitlements cache (settings.license_check_response), instead of waiting for the daily core_license_check queue job. The on-demand counterpart to that job — use it after purchasing or renewing an Extra to pull fresh entitlements right away. On a transport failure or non-200 from the service the prior cache is left untouched and a 503 is returned. Requires admin.",
 *     operationId="adminLicenseRefresh",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="License cache refreshed",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="OK"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="enforcement_level", type="string", enum={"normal","lock_extras","remove_extras","anonymous"}, example="normal"),
 *                 @OA\Property(
 *                     property="licensed",
 *                     type="array",
 *                     @OA\Items(type="string"),
 *                     example={"encryption","upgrade","complianceforgescf"}
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="Not authenticated",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 *     @OA\Response(
 *       response=503,
 *       description="Licensing service unreachable or returned non-200; the cache was not refreshed. Retry shortly.",
 *     ),
 * )
 */

class OpenApiAdminLicenseRefresh {}

/**
 * @OA\Get(
 *     path="/admin/licenses",
 *     summary="Per-Extra license overview for the Licenses page",
 *     description="Returns the local license overview for every available Extra: classification (licensed/expired/unlicensed), localized description, license status and start/end dates. Reads the cache only — never the network. Requires admin.",
 *     operationId="adminLicenses",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="License overview",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="OK"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="enforcement_level", type="string", example="normal"),
 *                 @OA\Property(
 *                     property="extras",
 *                     type="array",
 *                     @OA\Items(
 *                         @OA\Property(property="short_name", type="string", example="authentication"),
 *                         @OA\Property(property="name", type="string", example="Custom Authentication Extra"),
 *                         @OA\Property(property="description", type="string"),
 *                         @OA\Property(property="classification", type="string", enum={"licensed","expired","unlicensed"}),
 *                         @OA\Property(property="is_free", type="boolean", example=false),
 *                         @OA\Property(property="status", type="string", nullable=true, example="active"),
 *                         @OA\Property(property="start_date", type="string", nullable=true, example="2026-05-20 19:57:59"),
 *                         @OA\Property(property="end_date", type="string", nullable=true, example="2026-06-01 20:27:00")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have admin privileges."),
 * )
 */

class OpenApiAdminLicenses {}

/**
 * @OA\Post(
 *     path="/admin/settings/favorites",
 *     summary="Add a Settings Hub tile to the caller's favorites",
 *     operationId="adminSettingsFavoriteAdd",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"key"},
 *             @OA\Property(property="key", type="string", description="Catalog entry key", example="user_management")
 *         )
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Favorite added (idempotent).",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="Missing or unknown key.",
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No session user.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminSettingsFavoriteAdd {}

/**
 * @OA\Delete(
 *     path="/admin/settings/favorites/{key}",
 *     summary="Remove a Settings Hub tile from the caller's favorites",
 *     operationId="adminSettingsFavoriteRemove",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="key",
 *         in="path",
 *         required=true,
 *         description="Catalog entry key",
 *         @OA\Schema(type="string", example="user_management")
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="Favorite removed (idempotent).",
 *     ),
 *     @OA\Response(
 *       response=401,
 *       description="UNAUTHORIZED: No session user.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminSettingsFavoriteRemove {}

/**
 * @OA\Post(
 *     path="/admin/extras/install",
 *     summary="Install a SimpleRisk Extra",
 *     description="Downloads and unpacks the named Extra into simplerisk/extras/<name>/. Activation is a separate step (POST /admin/activate_deactivate_extra). Requires admin.",
 *     operationId="adminExtrasInstall",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name"},
 *             @OA\Property(property="name", type="string", description="Canonical Extra slug, e.g. 'jira', 'vulnmgmt'", example="jira")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Install succeeded",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="installed", type="boolean", example=true)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Missing or unknown name"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Forbidden: not admin, or installation is disabled by the current license enforcement state, or the download was rejected for licensing/auth reasons."),
 *     @OA\Response(response=412, description="Precondition failed: this instance cannot accept the Extra build on offer. The licensing service serves only the newest build of an Extra, so an instance behind the newest release must upgrade SimpleRisk first. Returned when SimpleRisk is not on the latest release, a database migration is still pending, the build offered is not compatible with this release, its version could not be verified, or no compatibility data was available to judge it. Nothing is installed and any existing copy of the Extra is left untouched; status_message carries the specific reason."),
 *     @OA\Response(response=503, description="License state is unknown (cold cache or transient failure); retry shortly."),
 *     @OA\Response(response=500, description="Download or unpack failed")
 * )
 */

class OpenApiAdminExtrasInstall {}

/**
 * @OA\Post(
 *     path="/admin/reset_registration",
 *     summary="Clear this instance's local registration state",
 *     description="Deletes the local instance_id, services_api_key, and license_check_response cache row. Does NOT contact the licensing service. After calling this endpoint, an admin re-registers the instance by submitting the standard /admin/register.php form, which then performs the actual /register call. Used to recover from a corrupted local identity.",
 *     operationId="resetRegistration",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Local registration state cleared",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="message", type="string", example="Local registration state cleared. Re-register at /admin/register.php to obtain a new instance_id."),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="registered", type="boolean", example=false, description="Always false — the endpoint only clears state; the admin must re-register separately.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have admin privileges.")
 * )
 */

class OpenApiAdminResetRegistration {}

?>