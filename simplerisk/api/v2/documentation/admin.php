<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

// These attributes describe the API contract statically — see comment in general.php.
// The "version" example below is a static placeholder; bump it at release time
// so the in-product Swagger UI and the published Postman collection both show
// a sensible default. The endpoint itself always uses the most recent release
// when called without a version argument, regardless of this example value.

#[OA\Get(
    path: '/admin/version',
    summary: 'List SimpleRisk version information',
    operationId: 'version',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'SimpleRisk version information'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not having admin privileges.'),
    ],
)]
class OpenApiAdminVersion {}

#[OA\Get(
    path: '/admin/version/app',
    summary: 'List SimpleRisk application version information',
    operationId: 'appVersion',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'SimpleRisk application version information'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not having admin privileges.'),
    ],
)]
class OpenApiAdminVersionApp {}

#[OA\Get(
    path: '/admin/version/db',
    summary: 'List SimpleRisk database version information',
    operationId: 'dbVersion',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'SimpleRisk database version information'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not having admin privileges.'),
    ],
)]
class OpenApiAdminVersionDB {}

// The "example" value below is a static placeholder so this annotation parses
// without a live SimpleRisk runtime (the prior implementation read the latest
// release from the global $releases set up by simplerisk/includes/upgrade.php).
// It is overwritten with the actual current release string at runtime by
// simplerisk/api/v2/documentation/index.php so the in-product Swagger UI keeps
// showing the live version. Update this placeholder at release time as a
// belt-and-suspenders fallback for offline tooling that doesn't run the
// runtime patch — i.e. the GitHub Actions Postman collection generator.
#[OA\Post(
    path: '/admin/upgrade/db',
    summary: 'Upgrade the SimpleRisk database',
    operationId: 'upgrade_db',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: false,
        description: "Upgrade the SimpleRisk database. With no version, the full upgrade chain runs from the database's current version.",
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'version',
                            type: 'string',
                            description: "Optional. When given, applies exactly that one release's migration -- a single hop, for targeting one migration during development. When OMITTED, the full chain runs from wherever the database actually is, finishing with the migration for the release currently in development if there is one; that is the mode to use for testing a release that has no version number yet.",
                            example: '20261020-001',
                            pattern: '^\\\\d{8}-\\\\d{3}$',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Upgrade successful. Also returned when the requested release is the newest one this code knows about and its migration deliberately does not advance the database version, because that release has not been cut yet; the status message says so.',
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Invalid version format, version not found, or no upgrade function exists for that release.',
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
        new OA\Response(
            response: 409,
            description: 'CONFLICT: another upgrade channel currently holds the instance-wide upgrade lock. Nothing was changed — retry once it completes. Distinct from 500 on purpose: this is an expected, self-clearing condition that hosted automation should retry rather than alert on.',
        ),
        new OA\Response(
            response: 500,
            description: 'The upgrade did not complete: the database user is missing required privileges, the migration raised an error, or it finished without advancing the database version. Detail is written to the server log rather than returned, since it can contain schema and path information.',
        ),
    ],
)]
class OpenApiAdminUpgradeDB {}

#[OA\Delete(
    path: '/admin/tag',
    summary: 'Delete tag',
    operationId: 'tagDelete',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            parameter: 'id',
            in: 'query',
            name: 'id',
            description: 'The id of the tag you would like to delete.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'SimpleRisk tag deleted'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiAdminTagsDelete {}

#[OA\Delete(
    path: '/admin/tag/all',
    summary: 'Delete all tags',
    operationId: 'allTagDelete',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            parameter: 'type',
            in: 'query',
            name: 'type',
            description: 'The type of tag you would like to delete.',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                enum: [
                    'risk',
                    'asset',
                    'test',
                    'test_audit',
                    'incident_management_destination',
                    'incident_management_source',
                    'questionnaire_pending_risk',
                    'questionnaire_risk',
                    'questionnaire_answer',
                    'all',
                ],
            ),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'SimpleRisk tags deleted'),
        new OA\Response(response: 400, description: 'Invalid type'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiAdminAllTagsDelete {}

#[OA\Post(
    path: '/admin/governance/documents/maptocontrols',
    summary: 'Initiate an analysis of all document content to controls',
    operationId: 'mapDocumentsToControls',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Mapped documents'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not having admin privileges.'),
    ],
)]
class OpenApiAdminMapDocumentsToControls {}

#[OA\Get(
    path: '/admin/queue',
    summary: 'List queued tasks with optional filters for task type and status',
    operationId: 'listQueueTasks',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'task_type',
            in: 'query',
            description: "Filter results by the task type (e.g., 'ai_document_to_control_chunker', 'send_email', etc.)",
            required: false,
            schema: new OA\Schema(type: 'string', example: ''),
        ),
        new OA\Parameter(
            name: 'status',
            in: 'query',
            description: "Filter results by task status. Use 'all' (default) to return all statuses. For multiple statuses, provide a comma-separated list (e.g., 'pending,in_progress').",
            required: false,
            schema: new OA\Schema(type: 'string', example: 'all'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'List of queue tasks (optionally filtered)',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status_code', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'count', type: 'integer', example: 5),
                            new OA\Property(
                                property: 'filters',
                                type: 'object',
                                properties: [
                                    new OA\Property(
                                        property: 'task_type',
                                        type: 'string',
                                        example: 'ai_document_to_control_chunker',
                                    ),
                                    new OA\Property(property: 'status', type: 'string', example: 'pending,in_progress'),
                                ],
                            ),
                            new OA\Property(
                                property: 'items',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 42),
                                        new OA\Property(
                                            property: 'task_type',
                                            type: 'string',
                                            example: 'ai_document_to_control_chunker',
                                        ),
                                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                        new OA\Property(
                                            property: 'created_at',
                                            type: 'string',
                                            format: 'date-time',
                                            example: '2025-11-06T15:32:00Z',
                                        ),
                                        new OA\Property(
                                            property: 'updated_at',
                                            type: 'string',
                                            format: 'date-time',
                                            example: '2025-11-06T16:05:00Z',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminQueue {}

#[OA\Get(
    path: '/admin/queue/promises',
    summary: 'List all promises associated with a specific queue task',
    operationId: 'listPromisesByQueueTask',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'queue_task_id',
            in: 'query',
            description: 'The ID of the queue task to retrieve promises for',
            required: true,
            schema: new OA\Schema(type: 'integer', example: 123),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'List of promises associated with the given queue_task_id',
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 42),
                        new OA\Property(property: 'promise_type', type: 'string', example: 'document_review'),
                        new OA\Property(property: 'reference_id', type: 'integer', example: 10),
                        new OA\Property(property: 'current_stage', type: 'string', example: 'finalize'),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'state', type: 'string', example: 'active'),
                        new OA\Property(property: 'queue_task_id', type: 'integer', example: 123),
                        new OA\Property(property: 'depends_on', type: 'string', example: '41,40'),
                        new OA\Property(
                            property: 'payload',
                            type: 'object',
                            example: ['document_id' => 174, 'triggered_at' => 1762621556],
                        ),
                        new OA\Property(
                            property: 'description',
                            type: 'string',
                            example: 'Promise to finalize document review',
                        ),
                        new OA\Property(
                            property: 'created_at',
                            type: 'string',
                            format: 'date-time',
                            example: '2025-11-06T15:32:00Z',
                        ),
                        new OA\Property(
                            property: 'updated_at',
                            type: 'string',
                            format: 'date-time',
                            example: '2025-11-06T16:05:00Z',
                        ),
                    ],
                ),
            ),
        ),
        new OA\Response(response: 400, description: 'Bad Request: Missing or invalid queue_task_id'),
        new OA\Response(response: 404, description: 'No promises found for the specified queue_task_id'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminPromisesByTask {}

#[OA\Post(
    path: '/admin/write_debug_log',
    summary: 'Flush queued debug log messages to the Apache error log',
    operationId: 'writeDebugLog',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Debug log messages written successfully.')],
)]
class OpenApiAdminWriteDebugLog {}

#[OA\Get(
    path: '/admin/users/all',
    summary: 'List all users',
    operationId: 'allUsers',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'List of all users',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uid', type: 'integer', example: 1),
                                new OA\Property(property: 'type', type: 'string', example: 'admin'),
                                new OA\Property(property: 'username', type: 'string', example: 'jsmith'),
                                new OA\Property(property: 'email', type: 'string', example: 'jsmith@example.com'),
                                new OA\Property(property: 'last_login', type: 'string', example: '2026-01-15 10:30:00'),
                                new OA\Property(property: 'teams', type: 'string', example: '1:2:3'),
                                new OA\Property(property: 'role', type: 'string', example: 'Administrator'),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminUsersAll {}

#[OA\Get(
    path: '/admin/users/enabled',
    summary: 'List all enabled users',
    operationId: 'enabledUsers',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'List of all enabled users',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uid', type: 'integer', example: 1),
                                new OA\Property(property: 'type', type: 'string', example: 'admin'),
                                new OA\Property(property: 'username', type: 'string', example: 'jsmith'),
                                new OA\Property(property: 'email', type: 'string', example: 'jsmith@example.com'),
                                new OA\Property(property: 'last_login', type: 'string', example: '2026-01-15 10:30:00'),
                                new OA\Property(property: 'teams', type: 'string', example: '1:2:3'),
                                new OA\Property(property: 'role', type: 'string', example: 'Administrator'),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminUsersEnabled {}

#[OA\Get(
    path: '/admin/users/disabled',
    summary: 'List all disabled users',
    operationId: 'disabledUsers',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'List of all disabled users',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uid', type: 'integer', example: 1),
                                new OA\Property(property: 'type', type: 'string', example: 'admin'),
                                new OA\Property(property: 'username', type: 'string', example: 'jsmith'),
                                new OA\Property(property: 'email', type: 'string', example: 'jsmith@example.com'),
                                new OA\Property(property: 'last_login', type: 'string', example: '2026-01-15 10:30:00'),
                                new OA\Property(property: 'teams', type: 'string', example: '1:2:3'),
                                new OA\Property(property: 'role', type: 'string', example: 'Administrator'),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminUsersDisabled {}

#[OA\Post(
    path: '/admin/fields/add',
    summary: 'Add a custom field',
    description: 'Admin-only. Only exists while the Customization Extra is active.',
    operationId: 'addCustomField',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Add a custom field.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name', 'field_type'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', description: 'The name of the custom field.'),
                        new OA\Property(
                            property: 'field_type',
                            type: 'string',
                            description: 'The type of the custom field.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Custom field added successfully.',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'status_message',
                        type: 'string',
                        example: 'Success: The item was added successfully.',
                    ),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        description: 'The newly created field. Only present on success.',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 42),
                            new OA\Property(property: 'name', type: 'string', example: 'Data classification'),
                            new OA\Property(property: 'type', type: 'string', example: 'dropdown'),
                            new OA\Property(property: 'required', type: 'integer', example: 0),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminFieldsAdd {}

#[OA\Post(
    path: '/admin/fields/delete',
    summary: 'Delete a custom field',
    description: 'Admin-only. Only exists while the Customization Extra is active.',
    operationId: 'deleteCustomField',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Delete a custom field.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['field_id'],
                    properties: [
                        new OA\Property(
                            property: 'field_id',
                            type: 'integer',
                            description: 'The ID of the custom field to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Custom field deleted successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminFieldsDelete {}

#[OA\Get(
    path: '/admin/fields/get',
    summary: 'Retrieve a custom field definition',
    description: 'Admin-only. Only exists while the Customization Extra is active.',
    operationId: 'getCustomField',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'field_id',
            in: 'query',
            description: 'The ID of the custom field to retrieve.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Custom field definition retrieved successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminFieldsGet {}

#[OA\Get(
    path: '/admin/fields/list',
    summary: 'List custom fields for a field group',
    description: 'Admin-only. Only exists while the Customization Extra is active.',
    operationId: 'listCustomFields',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'fgroup',
            in: 'query',
            description: 'The field group to list custom fields for.',
            required: true,
            schema: new OA\Schema(type: 'string', enum: ['asset', 'risk', 'project', 'framework', 'control']),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Array of custom field objects.'),
        new OA\Response(response: 400, description: 'BAD REQUEST: The fgroup parameter is missing or invalid.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminFieldsList {}

#[OA\Patch(
    path: '/admin/fields/{id}',
    summary: 'Update a custom field',
    description: 'Admin-only. Only exists while the Customization Extra is active.',
    operationId: 'updateCustomField',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            description: 'The ID of the custom field to update.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        description: "Update a custom field's name, required flag, and alphabetical order.",
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', description: 'The name of the custom field.'),
                        new OA\Property(
                            property: 'required',
                            type: 'integer',
                            enum: [0, 1],
                            description: 'Whether the field is required.',
                        ),
                        new OA\Property(
                            property: 'encryption',
                            type: 'integer',
                            enum: [0, 1],
                            description: 'Whether the field is encrypted. Omit to preserve the current value.',
                        ),
                        new OA\Property(
                            property: 'alphabetical_order',
                            type: 'integer',
                            enum: [0, 1],
                            description: "Whether the field's options are sorted alphabetically.",
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Custom field updated successfully.'),
        new OA\Response(response: 400, description: 'BAD REQUEST: The id or name parameter is missing.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 404, description: 'NOT FOUND: No custom field exists with the specified id.'),
    ],
)]
class OpenApiAdminFieldsUpdate {}

#[OA\Get(
    path: '/customization/templateGroups',
    summary: 'List template groups for a field group',
    description: 'Admin-only. Requires the Customization Extra to be active.',
    operationId: 'getTemplateGroups',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'fgroup',
            in: 'query',
            description: 'The field group to list template groups for.',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                enum: ['asset', 'risk', 'project', 'framework', 'control'],
                default: 'risk',
            ),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of template group objects.',
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Default'),
                        new OA\Property(property: 'fgroup', type: 'string', example: 'risk'),
                        new OA\Property(property: 'is_default', type: 'integer', example: 1),
                    ],
                ),
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: The fgroup parameter is invalid.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.',
        ),
    ],
)]
class OpenApiCustomizationTemplateGroupsList {}

#[OA\Post(
    path: '/customization/templateGroups',
    summary: 'Add a template group',
    description: 'Admin-only. Requires the Customization Extra to be active.',
    operationId: 'addTemplateGroup',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            maxLength: 100,
                            description: 'The name of the template group.',
                        ),
                        new OA\Property(
                            property: 'fgroup',
                            type: 'string',
                            enum: ['asset', 'risk', 'project', 'framework', 'control'],
                            default: 'risk',
                            description: 'The field group the template group belongs to.',
                        ),
                        new OA\Property(
                            property: 'clone_from',
                            type: 'integer',
                            description: 'Optional id of an existing template group in the same fgroup whose layout (its custom_template rows) is copied into the new group. Ignored when it belongs to a different fgroup; omit it to create an empty group.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: "The refreshed list of template groups for the given fgroup. When clone_from was given, the new group starts with a copy of that group's layout.",
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: The name is missing or longer than 100 characters, the fgroup is invalid, or a template group with that name already exists in the fgroup.',
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.',
        ),
    ],
)]
class OpenApiCustomizationTemplateGroupsAdd {}

#[OA\Patch(
    path: '/customization/templateGroups/{id}',
    summary: 'Rename a template group',
    description: 'Admin-only. Requires the Customization Extra to be active.',
    operationId: 'updateTemplateGroup',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            description: 'The ID of the template group to update.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The new name of the template group.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: "The refreshed list of template groups for the group's fgroup."),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: The name is missing, or another template group with that name already exists in the fgroup.',
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.',
        ),
        new OA\Response(response: 404, description: 'NOT FOUND: No template group exists with the specified id.'),
    ],
)]
class OpenApiCustomizationTemplateGroupsUpdate {}

#[OA\Delete(
    path: '/customization/templateGroups/{id}',
    summary: 'Delete a template group',
    description: "Admin-only. Requires the Customization Extra to be active. Also deletes any custom_template rows belonging to the group. Refuses to delete a fgroup's Default group.",
    operationId: 'deleteTemplateGroup',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            description: 'The ID of the template group to delete.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: "The refreshed list of template groups for the group's fgroup."),
        new OA\Response(
            response: 400,
            description: "BAD REQUEST: The specified group is the fgroup's Default group and cannot be deleted.",
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.',
        ),
        new OA\Response(response: 404, description: 'NOT FOUND: No template group exists with the specified id.'),
    ],
)]
class OpenApiCustomizationTemplateGroupsDelete {}

#[OA\Get(
    path: '/organizational_hierarchy/templateAssignments',
    summary: 'Get template-group to business-unit assignments for a field group',
    description: 'Admin-only. Requires the Customization Extra to be active.',
    operationId: 'getTemplateAssignments',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'fgroup',
            in: 'query',
            description: 'The field group to look up template groups for.',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                enum: ['asset', 'risk', 'project', 'framework', 'control'],
                default: 'risk',
            ),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'The business units available for assignment, and each template group in the fgroup with its currently assigned business_unit_ids.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'business_units',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'value', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Finance'),
                            ],
                        ),
                    ),
                    new OA\Property(
                        property: 'groups',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Default'),
                                new OA\Property(property: 'fgroup', type: 'string', example: 'risk'),
                                new OA\Property(
                                    property: 'business_unit_ids',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [1, 3],
                                ),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: The fgroup parameter is invalid.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have admin privileges, or the Customization Extra is not active.',
        ),
    ],
)]
class OpenApiCustomizationTemplateAssignmentsGet {}

#[OA\Post(
    path: '/organizational_hierarchy/templateAssignments',
    summary: 'Save template-group to business-unit assignments for a field group',
    description: "Admin-only. Requires the Customization Extra AND the Organizational Hierarchy Extra to both be active. IMPORTANT: this performs a full replace of every template group's business-unit assignment in the fgroup -- any group id in the fgroup that is missing from business_unit_ids has its assignments cleared, not left unchanged. Callers must always submit the complete current+edited mapping for every group in the fgroup, never a single-group delta.",
    operationId: 'saveTemplateAssignments',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['business_unit_ids'],
                    properties: [
                        new OA\Property(
                            property: 'fgroup',
                            type: 'string',
                            enum: ['asset', 'risk', 'project', 'framework', 'control'],
                            default: 'risk',
                            description: 'The field group whose template groups are being assigned.',
                        ),
                        new OA\Property(
                            property: 'business_unit_ids',
                            type: 'object',
                            description: "A map keyed by template_group_id whose values are lists of business unit ids -- form-encoded as business_unit_ids[1][]=3&business_unit_ids[1][]=5 to assign template group 1 to business units 3 and 5, with an empty list clearing a group. MUST include every template group id in the fgroup: any omitted group's assignments are wiped.",
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(type: 'integer'),
                                description: "Business unit ids assigned to the template group named by the key; an empty list clears that group's assignment.",
                            ),
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'The refreshed business units and template groups (with business_unit_ids) for the given fgroup.',
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: business_unit_ids is not an array/map.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have admin privileges, the Customization Extra is not active, or the Organizational Hierarchy Extra is not active.',
        ),
    ],
)]
class OpenApiCustomizationTemplateAssignmentsSave {}

#[OA\Get(
    path: '/admin/tables/fullData',
    summary: 'Retrieve unfiltered data from a SimpleRisk lookup table',
    operationId: 'getTableData',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'table',
            in: 'query',
            description: 'The table name to retrieve data from.',
            required: true,
            schema: new OA\Schema(type: 'string'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Table data retrieved successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminTablesFullData {}

#[OA\Get(
    path: '/admin/risk_catalog/datatable',
    summary: 'Get risk catalog items in DataTables format',
    operationId: 'riskCatalogDatatable',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'draw',
            in: 'query',
            description: 'DataTables draw counter.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'DataTables-formatted risk catalog response.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'draw', type: 'integer', example: 1),
                    new OA\Property(property: 'recordsTotal', type: 'integer', example: 100),
                    new OA\Property(property: 'recordsFiltered', type: 'integer', example: 100),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
    ],
)]
class OpenApiAdminRiskCatalogDatatable {}

#[OA\Get(
    path: '/admin/risk_catalog/detail',
    summary: 'Get details for a single risk catalog entry',
    operationId: 'riskCatalogDetail',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'risk_id',
            in: 'query',
            description: 'The ID of the risk catalog entry to retrieve.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Risk catalog entry details retrieved successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminRiskCatalogDetail {}

#[OA\Post(
    path: '/admin/risk_catalog/update_order',
    summary: 'Update the display order of risk catalog entries',
    operationId: 'updateRiskCatalogOrder',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Update the display order of risk catalog entries.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'order',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            description: 'Ordered array of risk catalog entry IDs.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [new OA\Response(response: 200, description: 'Risk catalog order updated successfully.')],
)]
class OpenApiAdminRiskCatalogUpdateOrder {}

#[OA\Post(
    path: '/admin/risk_catalog/add_risk_catalog',
    summary: 'Add a new risk catalog entry',
    operationId: 'addRiskCatalog',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Add a new risk catalog entry.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'number', type: 'string', description: 'The catalog entry number.'),
                        new OA\Property(
                            property: 'risk_grouping',
                            type: 'string',
                            description: 'The grouping for the risk catalog entry.',
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The name of the risk catalog entry.',
                        ),
                        new OA\Property(
                            property: 'description',
                            type: 'string',
                            description: 'A description of the risk catalog entry.',
                        ),
                        new OA\Property(
                            property: 'risk_function',
                            type: 'string',
                            description: 'The risk function associated with the entry.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Risk catalog entry added successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminRiskCatalogAdd {}

#[OA\Post(
    path: '/admin/risk_catalog/update_risk_catalog',
    summary: 'Update an existing risk catalog entry',
    operationId: 'updateRiskCatalog',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Update an existing risk catalog entry.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['id'],
                    properties: [
                        new OA\Property(
                            property: 'id',
                            type: 'integer',
                            description: 'The ID of the risk catalog entry to update.',
                        ),
                        new OA\Property(property: 'number', type: 'string', description: 'The catalog entry number.'),
                        new OA\Property(
                            property: 'risk_grouping',
                            type: 'string',
                            description: 'The grouping for the risk catalog entry.',
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The name of the risk catalog entry.',
                        ),
                        new OA\Property(
                            property: 'description',
                            type: 'string',
                            description: 'A description of the risk catalog entry.',
                        ),
                        new OA\Property(
                            property: 'risk_function',
                            type: 'string',
                            description: 'The risk function associated with the entry.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Risk catalog entry updated successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminRiskCatalogUpdate {}

#[OA\Post(
    path: '/admin/risk_catalog/delete_risk_catalog',
    summary: 'Delete a risk catalog entry',
    operationId: 'deleteRiskCatalog',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Delete a risk catalog entry.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['id'],
                    properties: [
                        new OA\Property(
                            property: 'id',
                            type: 'integer',
                            description: 'The ID of the risk catalog entry to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Risk catalog entry deleted successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminRiskCatalogDelete {}

#[OA\Post(
    path: '/admin/risk_catalog/swap_groups',
    summary: 'Swap the grouping of two risk catalog entries',
    operationId: 'swapRiskCatalogGroups',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Swap the grouping of two risk catalog entries.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'id1',
                            type: 'integer',
                            description: 'The ID of the first risk catalog entry.',
                        ),
                        new OA\Property(
                            property: 'id2',
                            type: 'integer',
                            description: 'The ID of the second risk catalog entry.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [new OA\Response(response: 200, description: 'Risk catalog groups swapped successfully.')],
)]
class OpenApiAdminRiskCatalogSwapGroups {}

#[OA\Get(
    path: '/admin/threat_catalog/datatable',
    summary: 'Get threat catalog items in DataTables format',
    operationId: 'threatCatalogDatatable',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'draw',
            in: 'query',
            description: 'DataTables draw counter.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'DataTables-formatted threat catalog response.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'draw', type: 'integer', example: 1),
                    new OA\Property(property: 'recordsTotal', type: 'integer', example: 100),
                    new OA\Property(property: 'recordsFiltered', type: 'integer', example: 100),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
    ],
)]
class OpenApiAdminThreatCatalogDatatable {}

#[OA\Get(
    path: '/admin/threat_catalog/detail',
    summary: 'Get details for a single threat catalog entry',
    operationId: 'threatCatalogDetail',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'threat_id',
            in: 'query',
            description: 'The ID of the threat catalog entry to retrieve.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Threat catalog entry details retrieved successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminThreatCatalogDetail {}

#[OA\Post(
    path: '/admin/threat_catalog/update_order',
    summary: 'Update the display order of threat catalog entries',
    operationId: 'updateThreatCatalogOrder',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Update the display order of threat catalog entries.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'order',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            description: 'Ordered array of threat catalog entry IDs.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [new OA\Response(response: 200, description: 'Threat catalog order updated successfully.')],
)]
class OpenApiAdminThreatCatalogUpdateOrder {}

#[OA\Post(
    path: '/admin/threat_catalog/add_threat_catalog',
    summary: 'Add a new threat catalog entry',
    operationId: 'addThreatCatalog',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Add a new threat catalog entry.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'number', type: 'string', description: 'The catalog entry number.'),
                        new OA\Property(
                            property: 'threat_grouping',
                            type: 'string',
                            description: 'The grouping for the threat catalog entry.',
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The name of the threat catalog entry.',
                        ),
                        new OA\Property(
                            property: 'description',
                            type: 'string',
                            description: 'A description of the threat catalog entry.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Threat catalog entry added successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminThreatCatalogAdd {}

#[OA\Post(
    path: '/admin/threat_catalog/update_threat_catalog',
    summary: 'Update an existing threat catalog entry',
    operationId: 'updateThreatCatalog',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Update an existing threat catalog entry.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['id'],
                    properties: [
                        new OA\Property(
                            property: 'id',
                            type: 'integer',
                            description: 'The ID of the threat catalog entry to update.',
                        ),
                        new OA\Property(property: 'number', type: 'string', description: 'The catalog entry number.'),
                        new OA\Property(
                            property: 'threat_grouping',
                            type: 'string',
                            description: 'The grouping for the threat catalog entry.',
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The name of the threat catalog entry.',
                        ),
                        new OA\Property(
                            property: 'description',
                            type: 'string',
                            description: 'A description of the threat catalog entry.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Threat catalog entry updated successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminThreatCatalogUpdate {}

#[OA\Post(
    path: '/admin/threat_catalog/delete_threat_catalog',
    summary: 'Delete a threat catalog entry',
    operationId: 'deleteThreatCatalog',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Delete a threat catalog entry.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['id'],
                    properties: [
                        new OA\Property(
                            property: 'id',
                            type: 'integer',
                            description: 'The ID of the threat catalog entry to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Threat catalog entry deleted successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminThreatCatalogDelete {}

#[OA\Post(
    path: '/admin/column_settings/save_column_settings',
    summary: 'Save column selection display settings for a datatable view',
    operationId: 'saveColumnSettings',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Save column selection display settings for a datatable view.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['view'],
                    properties: [
                        new OA\Property(
                            property: 'view',
                            type: 'string',
                            description: 'The datatable view identifier.',
                        ),
                        new OA\Property(
                            property: 'columns',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            description: 'Array of column identifiers to display.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [new OA\Response(response: 200, description: 'Column settings saved successfully.')],
)]
class OpenApiAdminSaveColumnSettings {}

#[OA\Post(
    path: '/admin/incidentmanagement',
    summary: 'Enable or disable the Incident Management Extra',
    operationId: 'incidentManagement',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        description: 'Enable or disable the Incident Management Extra.',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'action',
                            type: 'string',
                            enum: ['enable', 'disable'],
                            description: 'The action to perform on the Incident Management Extra.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Incident Management Extra action performed successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminIncidentManagement {}

#[OA\Get(
    path: '/complianceforgescf/status',
    summary: 'Get the status of the Secure Controls Framework (SCF) Extra',
    operationId: 'complianceforgescfStatus',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Secure Controls Framework (SCF) Extra status retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [new OA\Property(property: 'enabled', type: 'boolean', example: true)],
            ),
        ),
    ],
)]
class OpenApiComplianceforgescfStatus {}

#[OA\Post(
    path: '/complianceforgescf/enable',
    summary: 'Enable the Secure Controls Framework (SCF) Extra',
    operationId: 'complianceforgescfEnable',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Secure Controls Framework (SCF) Extra enabled successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiComplianceforgescfEnable {}

#[OA\Post(
    path: '/complianceforgescf/disable',
    summary: 'Disable the Secure Controls Framework (SCF) Extra',
    operationId: 'complianceforgescfDisable',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Secure Controls Framework (SCF) Extra disabled successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiComplianceforgescfDisable {}

#[OA\Post(
    path: '/one_click_upgrade',
    summary: 'Trigger a one-click upgrade of the SimpleRisk application',
    operationId: 'oneClickUpgrade',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'One-click upgrade triggered successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiOneClickUpgrade {}

#[OA\Get(
    path: '/role_responsibilities/get_responsibilities',
    summary: 'Get the responsibilities and permissions assigned to a role',
    operationId: 'getResponsibilitiesByRoleId',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'role_id',
            in: 'query',
            description: 'The ID of the role to retrieve responsibilities for.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Role responsibilities retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'admin', type: 'boolean', example: false),
                    new OA\Property(property: 'default', type: 'boolean', example: false),
                    new OA\Property(property: 'value', type: 'string', example: 'analyst'),
                    new OA\Property(property: 'responsibilities', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiRoleResponsibilitiesGet {}

#[OA\Get(
    path: '/admin/settings/catalog',
    summary: 'List the Settings Hub catalog',
    description: 'Returns every Settings Hub tile visible to the current admin.',
    operationId: 'adminSettingsCatalog',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Catalog payload',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'OK'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'tiles',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'key', type: 'string', example: 'user_management'),
                                        new OA\Property(property: 'label', type: 'string', example: 'User Management'),
                                        new OA\Property(property: 'description', type: 'string'),
                                        new OA\Property(
                                            property: 'path',
                                            type: 'string',
                                            example: 'admin/user_management.php',
                                        ),
                                        new OA\Property(
                                            property: 'tags',
                                            type: 'array',
                                            items: new OA\Items(type: 'string'),
                                        ),
                                        new OA\Property(property: 'favorited', type: 'boolean'),
                                        new OA\Property(
                                            property: 'state',
                                            type: 'string',
                                            enum: [
                                                'activated',
                                                'deactivated',
                                                'uninstalled',
                                                'ready_to_download',
                                                'purchase',
                                            ],
                                            description: "Activation state for Extras tiles. Non-Extras tiles always report 'activated'. Tiles in 'uninstalled' state are upgraded to 'ready_to_download' or 'purchase' by a deferred GET /admin/settings/extras/licenses call.",
                                        ),
                                        new OA\Property(
                                            property: 'extra_name',
                                            type: 'string',
                                            nullable: true,
                                            description: "Canonical Extra slug (e.g. 'jira', 'vulnmgmt'). Non-null on every Extras-tag tile; null on non-Extras tiles.",
                                            example: 'jira',
                                        ),
                                        new OA\Property(
                                            property: 'sub_hub',
                                            type: 'object',
                                            nullable: true,
                                            description: "If present, clicking this tile in 'activated' state enters a sub-hub view in the Settings Hub instead of navigating. Currently used only by incident_management_activation.",
                                            properties: [
                                                new OA\Property(
                                                    property: 'section_key',
                                                    type: 'string',
                                                    example: 'incident_management',
                                                ),
                                                new OA\Property(
                                                    property: 'heading',
                                                    type: 'string',
                                                    description: 'Localized heading for the sub-hub view',
                                                    example: 'Incident Management',
                                                ),
                                                new OA\Property(
                                                    property: 'tiles',
                                                    type: 'array',
                                                    items: new OA\Items(
                                                        type: 'object',
                                                        properties: [
                                                            new OA\Property(
                                                                property: 'key',
                                                                type: 'string',
                                                                example: 'example_sub_tile',
                                                            ),
                                                            new OA\Property(
                                                                property: 'label',
                                                                type: 'string',
                                                                description: 'Localized sub-tile label',
                                                                example: 'Settings',
                                                            ),
                                                            new OA\Property(
                                                                property: 'path',
                                                                type: 'string',
                                                                description: 'Path relative to simplerisk/, may include query string',
                                                                example: 'admin/example.php?tab=settings',
                                                            ),
                                                        ],
                                                    ),
                                                ),
                                            ],
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'Not authenticated'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminSettingsCatalog {}

#[OA\Get(
    path: '/admin/settings/extras/licenses',
    summary: 'List Extras the customer is licensed for',
    description: "Returns the Extra slugs the current customer has purchased. Used by the Settings Hub to upgrade uninstalled tiles from 'Checking…' to 'Ready to Download' or 'Purchase'.",
    operationId: 'adminSettingsExtrasLicenses',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'License list',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'OK'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'licensed',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                example: ['jira', 'incident_management'],
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'Not authenticated'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminSettingsExtrasLicenses {}

#[OA\Post(
    path: '/admin/license/refresh',
    summary: 'Force an immediate license check against the licensing service',
    description: 'Synchronously runs /license/check and rewrites the local entitlements cache (settings.license_check_response), instead of waiting for the daily core_license_check queue job. The on-demand counterpart to that job — use it after purchasing or renewing an Extra to pull fresh entitlements right away. On a transport failure or non-200 from the service the prior cache is left untouched and a 503 is returned. Requires admin.',
    operationId: 'adminLicenseRefresh',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'License cache refreshed',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'OK'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'enforcement_level',
                                type: 'string',
                                enum: ['normal', 'lock_extras', 'remove_extras', 'anonymous'],
                                example: 'normal',
                            ),
                            new OA\Property(
                                property: 'licensed',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                example: ['encryption', 'upgrade', 'complianceforgescf'],
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'Not authenticated'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(
            response: 503,
            description: 'Licensing service unreachable or returned non-200; the cache was not refreshed. Retry shortly.',
        ),
    ],
)]
class OpenApiAdminLicenseRefresh {}

#[OA\Get(
    path: '/admin/licenses',
    summary: 'Per-Extra license overview for the Licenses page',
    description: 'Returns the local license overview for every available Extra: classification (licensed/expired/unlicensed), localized description, license status and start/end dates. Reads the cache only — never the network. Requires admin.',
    operationId: 'adminLicenses',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'License overview',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'OK'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'enforcement_level', type: 'string', example: 'normal'),
                            new OA\Property(
                                property: 'extras',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(
                                            property: 'short_name',
                                            type: 'string',
                                            example: 'authentication',
                                        ),
                                        new OA\Property(
                                            property: 'name',
                                            type: 'string',
                                            example: 'Custom Authentication Extra',
                                        ),
                                        new OA\Property(property: 'description', type: 'string'),
                                        new OA\Property(
                                            property: 'classification',
                                            type: 'string',
                                            enum: ['licensed', 'expired', 'unlicensed'],
                                        ),
                                        new OA\Property(property: 'is_free', type: 'boolean', example: false),
                                        new OA\Property(
                                            property: 'status',
                                            type: 'string',
                                            nullable: true,
                                            example: 'active',
                                        ),
                                        new OA\Property(
                                            property: 'start_date',
                                            type: 'string',
                                            nullable: true,
                                            example: '2026-05-20 19:57:59',
                                        ),
                                        new OA\Property(
                                            property: 'end_date',
                                            type: 'string',
                                            nullable: true,
                                            example: '2026-06-01 20:27:00',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'Not authenticated'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminLicenses {}

#[OA\Post(
    path: '/admin/settings/favorites',
    summary: "Add a Settings Hub tile to the caller's favorites",
    operationId: 'adminSettingsFavoriteAdd',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['key'],
            properties: [
                new OA\Property(
                    property: 'key',
                    type: 'string',
                    description: 'Catalog entry key',
                    example: 'user_management',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Favorite added (idempotent).'),
        new OA\Response(response: 400, description: 'Missing or unknown key.'),
        new OA\Response(response: 401, description: 'UNAUTHORIZED: No session user.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminSettingsFavoriteAdd {}

#[OA\Delete(
    path: '/admin/settings/favorites/{key}',
    summary: "Remove a Settings Hub tile from the caller's favorites",
    operationId: 'adminSettingsFavoriteRemove',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'key',
            in: 'path',
            required: true,
            description: 'Catalog entry key',
            schema: new OA\Schema(type: 'string', example: 'user_management'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Favorite removed (idempotent).'),
        new OA\Response(response: 401, description: 'UNAUTHORIZED: No session user.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminSettingsFavoriteRemove {}

#[OA\Post(
    path: '/admin/extras/install',
    summary: 'Install a SimpleRisk Extra',
    description: 'Downloads and unpacks the named Extra into simplerisk/extras/<name>/. Activation is a separate step (POST /admin/activate_deactivate_extra). Requires admin.',
    operationId: 'adminExtrasInstall',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    description: "Canonical Extra slug, e.g. 'jira', 'vulnmgmt'",
                    example: 'jira',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Install succeeded',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [new OA\Property(property: 'installed', type: 'boolean', example: true)],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'Missing or unknown name'),
        new OA\Response(response: 401, description: 'Not authenticated'),
        new OA\Response(
            response: 403,
            description: 'Forbidden: not admin, or installation is disabled by the current license enforcement state, or the download was rejected for licensing/auth reasons.',
        ),
        new OA\Response(
            response: 412,
            description: 'Precondition failed: this instance cannot accept the Extra build on offer. The licensing service serves only the newest build of an Extra, so an instance behind the newest release must upgrade SimpleRisk first. Returned when SimpleRisk is not on the latest release, a database migration is still pending, the build offered is not compatible with this release, its version could not be verified, or no compatibility data was available to judge it. Nothing is installed and any existing copy of the Extra is left untouched; status_message carries the specific reason.',
        ),
        new OA\Response(
            response: 503,
            description: 'License state is unknown (cold cache or transient failure); retry shortly.',
        ),
        new OA\Response(response: 500, description: 'Download or unpack failed'),
    ],
)]
class OpenApiAdminExtrasInstall {}

#[OA\Post(
    path: '/admin/reset_registration',
    summary: "Clear this instance's local registration state",
    description: 'Deletes the local instance_id, services_api_key, and license_check_response cache row. Does NOT contact the licensing service. After calling this endpoint, an admin re-registers the instance by submitting the standard /admin/register.php form, which then performs the actual /register call. Used to recover from a corrupted local identity.',
    operationId: 'resetRegistration',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Local registration state cleared',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Local registration state cleared. Re-register at /admin/register.php to obtain a new instance_id.',
                    ),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'registered',
                                type: 'boolean',
                                example: false,
                                description: 'Always false — the endpoint only clears state; the admin must re-register separately.',
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
    ],
)]
class OpenApiAdminResetRegistration {}

#[OA\Get(
    path: '/account/profile',
    summary: "Get the calling user's own profile",
    operationId: 'getOwnProfile',
    tags: ['User Profile'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: "The calling user's account details, MFA/language/display state, and permission grants. Always scoped to the caller -- there is no id parameter.",
        ),
    ],
)]
class OpenApiProfileGet {}

#[OA\Patch(
    path: '/account/profile',
    summary: "Update the calling user's language preference",
    operationId: 'patchOwnProfile',
    tags: ['User Profile'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'language',
                            type: 'integer',
                            description: "A language id from GET /profile's languages list. This is the only field this endpoint accepts -- every other profile field is admin-managed.",
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Language updated.'),
        new OA\Response(response: 400, description: 'Missing or unknown language id.'),
    ],
)]
class OpenApiProfilePatch {}

#[OA\Post(
    path: '/account/reset-display-settings',
    summary: "Reset the calling user's custom table/dashboard display settings to the default",
    operationId: 'resetOwnDisplaySettings',
    tags: ['User Profile'],
    security: [['ApiKeyAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Display settings reset.')],
)]
class OpenApiProfileResetDisplaySettings {}

?>