<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// =====================================================================
// PLAN PROJECTS GRID API (SR-2229)
// =====================================================================

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/management/projects',
    summary: 'Plan Projects grid: every project for one status chip, with counts, saved column settings and filter options',
    operationId: 'planProjectsGrid',
    tags: ['project'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'status',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['1', '2', '3', '4', 'all']),
            description: '1 Active, 2 On hold, 3 Completed, 4 Canceled, or all. Defaults to 1.',
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Grid payload',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'counts', type: 'object', description: 'Row counts keyed 1..4 and all'),
                    new OA\Property(
                        property: 'column_settings',
                        type: 'object',
                        nullable: true,
                        description: "The caller's saved user.custom_plan_projects_display_settings, decoded",
                    ),
                    new OA\Property(
                        property: 'active_columns',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'object'),
                        description: 'Customization-curated column vocabulary; null when the Extra is inactive',
                    ),
                    new OA\Property(property: 'filter_options', type: 'object'),
                    new OA\Property(property: 'show_all', type: 'boolean'),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'Caller lacks risk management permission',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 403),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
    ],
)]
class OpenApiPlanProjectsGrid {}

#[OA\Get(
    path: '/management/project/{id}/risks',
    summary: 'The Plan Projects expand-drawer: every risk assigned to one project',
    operationId: 'planProjectRisks',
    tags: ['project'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer'),
            description: 'The project id.',
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: "The project's risks",
            content: new OA\JsonContent(
                type: 'object',
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object'))],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'Caller lacks risk management permission',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 403),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
        new OA\Response(
            response: 404,
            description: 'Project not found',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 404),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
    ],
)]
class OpenApiPlanProjectRisks {}

#[OA\Get(
    path: '/management/projects/unassigned_risks',
    summary: 'The Plan Projects Unassigned Risks queue',
    operationId: 'planProjectsUnassignedRisks',
    tags: ['project'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Unassigned risks',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'show_all', type: 'boolean'),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'Caller lacks risk management permission',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 403),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
    ],
)]
class OpenApiPlanProjectsUnassignedRisks {}

#[OA\Post(
    path: '/management/projects/assign_risks',
    summary: 'Assign (or unassign, with project_id=0) one or more risks to a project',
    operationId: 'assignRisksToProject',
    tags: ['project'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'risk_ids',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            description: 'Internal risk ids (not the display id) to (re)assign.',
                        ),
                        new OA\Property(
                            property: 'project_id',
                            type: 'integer',
                            description: 'Target project id, or 0 to return the risks to Unassigned.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'At least one risk was assigned',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'assigned', type: 'array', items: new OA\Items(type: 'integer')),
                            new OA\Property(property: 'denied', type: 'array', items: new OA\Items(type: 'integer')),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'Missing or invalid risk_ids/project_id',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 400),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'Caller lacks riskmanagement, manage_projects or modify_risks, or every referenced risk was denied by Team Separation',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 403),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
        new OA\Response(
            response: 404,
            description: 'project_id does not exist',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 404),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
    ],
)]
class OpenApiAssignRisksToProject {}

#[OA\Post(
    path: '/management/projects/reorder',
    summary: 'Persist a drag-reordered project sequence within one status chip',
    operationId: 'reorderPlanProjects',
    tags: ['project'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'status',
                            type: 'integer',
                            enum: [1, 2, 3, 4],
                            description: 'The status chip being reordered.',
                        ),
                        new OA\Property(
                            property: 'project_ids',
                            type: 'array',
                            items: new OA\Items(type: 'integer'),
                            description: "Every project id currently in that status, in the desired new order. Must be exactly that status's id set -- a mismatch (stale client state) is rejected with 400.",
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Order saved',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: "Missing status/project_ids, or project_ids does not match the status's current id set",
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 400),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'Caller lacks riskmanagement or manage_projects',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 403),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
    ],
)]
class OpenApiReorderPlanProjects {}

#[OA\Post(
    path: '/management/projects/display_settings',
    summary: "Save the current user's column display settings for the Plan Projects grid",
    operationId: 'savePlanProjectsDisplaySettings',
    tags: ['project'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'columns',
                            type: 'array',
                            description: 'List of [column_name, visibility] pairs, e.g. [[due_date, 0], [risk_count, 1]]. column_name must match ^[A-Za-z0-9_]+$; visibility is the string 0 or 1.',
                            items: new OA\Items(type: 'array', items: new OA\Items(type: 'string')),
                        ),
                        new OA\Property(
                            property: 'order',
                            type: 'array',
                            description: 'Optional. Drag-reordered column sequence, left-to-right. Each name must match ^[A-Za-z0-9_]+$.',
                            items: new OA\Items(type: 'string'),
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Display settings saved',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'Missing or invalid columns/order',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 400),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'Caller lacks risk management permission',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 403),
                    new OA\Property(property: 'status_message', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'data', nullable: true),
                ],
            ),
        ),
    ],
)]
class OpenApiSavePlanProjectsDisplaySettings {}
