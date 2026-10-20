<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/reports/risk/average',
    summary: 'Get the average risk score by date',
    operationId: 'reportsriskaverage',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            parameter: 'risk_id',
            in: 'query',
            name: 'risk_id',
            description: 'The id of the risk you would like to retrieve details for. Will return all risks if no risk_id value is specified.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            parameter: 'type',
            in: 'query',
            name: 'type',
            description: 'Whether you would like results for inherent or residual risk.',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['inherent', 'residual']),
        ),
        new OA\Parameter(
            parameter: 'timeframe',
            in: 'query',
            name: 'timeframe',
            description: 'Whether you would like results displayed by day, month or year.',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['day', 'month', 'year']),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Average Risk Score'),
        new OA\Response(response: 204, description: 'NO CONTENT: Unable to find the requested data.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiReportsRiskAverage {}

#[OA\Get(
    path: '/reports/risk/opencount',
    summary: 'Get the count of risks by date',
    operationId: 'reportsriskopencount',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            parameter: 'timeframe',
            in: 'query',
            name: 'timeframe',
            description: 'Whether you would like results displayed by day, month or year.',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['day', 'month', 'year']),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Open Risk Count'),
        new OA\Response(response: 204, description: 'NO CONTENT: Unable to find the requested data.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiReportsRiskOpenCount {}

#[OA\Get(
    path: '/risk_levels',
    summary: 'Get all configured risk level definitions including name, value range, and color',
    operationId: 'riskLevels',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of risk level definitions',
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'value',
                            type: 'number',
                            format: 'float',
                            description: 'The threshold value for this risk level.',
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The display name of this risk level.',
                        ),
                        new OA\Property(
                            property: 'color',
                            type: 'string',
                            description: 'The hex color code associated with this risk level.',
                        ),
                    ],
                ),
            ),
        ),
    ],
)]
class OpenApiRiskLevels {}

#[OA\Get(
    path: '/reports/dynamic',
    summary: 'Get a list of risks with optional filtering and sorting',
    operationId: 'dynamicRisk',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            parameter: 'status',
            in: 'query',
            name: 'status',
            description: 'Filter risks by status.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            parameter: 'sort',
            in: 'query',
            name: 'sort',
            description: 'Sort order for the results.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            parameter: 'group',
            in: 'query',
            name: 'group',
            description: 'Grouping for the results.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            parameter: 'start',
            in: 'query',
            name: 'start',
            description: 'Paging offset.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            parameter: 'length',
            in: 'query',
            name: 'length',
            description: 'Number of records to return.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of risk objects',
            content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object')),
        ),
        new OA\Response(response: 401, description: 'UNAUTHORIZED: No permission to access this report.'),
    ],
)]
class OpenApiDynamicRisk {}

#[OA\Post(
    path: '/reports/dynamic',
    summary: 'Get risks in DataTables server-side format with advanced filtering',
    operationId: 'dynamicRiskForm',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'draw',
                            type: 'integer',
                            format: 'int32',
                            description: 'DataTables draw counter.',
                        ),
                        new OA\Property(
                            property: 'start',
                            type: 'integer',
                            format: 'int32',
                            description: 'Paging first record indicator.',
                        ),
                        new OA\Property(
                            property: 'length',
                            type: 'integer',
                            format: 'int32',
                            description: 'Number of records to return.',
                        ),
                        new OA\Property(
                            property: 'status',
                            type: 'integer',
                            format: 'int32',
                            description: 'Filter risks by status.',
                        ),
                        new OA\Property(
                            property: 'sort',
                            type: 'integer',
                            format: 'int32',
                            description: 'Sort order for the results.',
                        ),
                        new OA\Property(
                            property: 'group',
                            type: 'integer',
                            format: 'int32',
                            description: 'Grouping for the results.',
                        ),
                        new OA\Property(
                            property: 'columns',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables column definitions.',
                        ),
                        new OA\Property(
                            property: 'order',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables ordering parameters.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'DataTables server-side response',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'draw', type: 'integer'),
                    new OA\Property(property: 'recordsTotal', type: 'integer'),
                    new OA\Property(property: 'recordsFiltered', type: 'integer'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
    ],
)]
class OpenApiDynamicRiskForm {}

#[OA\Get(
    path: '/reports/appetite',
    summary: 'Get risks for the risk appetite report',
    operationId: 'appetiteReport',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            parameter: 'type',
            in: 'query',
            name: 'type',
            description: 'Whether to retrieve risks within appetite (in) or outside appetite (out).',
            required: true,
            schema: new OA\Schema(type: 'string', enum: ['in', 'out']),
        ),
        new OA\Parameter(
            parameter: 'start',
            in: 'query',
            name: 'start',
            description: 'Paging offset.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            parameter: 'length',
            in: 'query',
            name: 'length',
            description: 'Number of records to return.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            parameter: 'draw',
            in: 'query',
            name: 'draw',
            description: 'DataTables draw counter.',
            required: false,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'DataTables-format response for the appetite report',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'draw', type: 'integer'),
                    new OA\Property(property: 'recordsTotal', type: 'integer'),
                    new OA\Property(property: 'recordsFiltered', type: 'integer'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
    ],
)]
class OpenApiAppetiteReport {}

#[OA\Get(
    path: '/audit_logs',
    summary: 'Get audit log entries',
    operationId: 'getAuditLogs',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            parameter: 'days',
            in: 'query',
            name: 'days',
            description: 'Number of days of audit log history to retrieve. Defaults to 7.',
            required: false,
            schema: new OA\Schema(type: 'integer', default: 7),
        ),
        new OA\Parameter(
            parameter: 'log_type',
            in: 'query',
            name: 'log_type',
            description: 'Comma-separated list of log types to filter results by.',
            required: false,
            schema: new OA\Schema(type: 'string'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of audit log entries',
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'risk_id', type: 'integer', nullable: true),
                    ],
                ),
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: Administrator privileges are required to access audit logs.',
        ),
    ],
)]
class OpenApiGetAuditLogs {}

#[OA\Post(
    path: '/reports/high_risk',
    summary: 'Get high-risk items in DataTables format',
    operationId: 'highRiskReport',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'draw',
                            type: 'integer',
                            format: 'int32',
                            description: 'DataTables draw counter.',
                        ),
                        new OA\Property(
                            property: 'start',
                            type: 'integer',
                            format: 'int32',
                            description: 'Paging first record indicator.',
                        ),
                        new OA\Property(
                            property: 'length',
                            type: 'integer',
                            format: 'int32',
                            description: 'Number of records to return.',
                        ),
                        new OA\Property(
                            property: 'columns',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables column definitions.',
                        ),
                        new OA\Property(
                            property: 'order',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables ordering parameters.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'DataTables server-side response',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'draw', type: 'integer'),
                    new OA\Property(property: 'recordsTotal', type: 'integer'),
                    new OA\Property(property: 'recordsFiltered', type: 'integer'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
    ],
)]
class OpenApiHighRiskReport {}

#[OA\Post(
    path: '/reports/user_management_reports',
    summary: 'Get user management report data in DataTables format',
    operationId: 'userManagementReports',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'draw',
                            type: 'integer',
                            format: 'int32',
                            description: 'DataTables draw counter.',
                        ),
                        new OA\Property(
                            property: 'start',
                            type: 'integer',
                            format: 'int32',
                            description: 'Paging first record indicator.',
                        ),
                        new OA\Property(
                            property: 'length',
                            type: 'integer',
                            format: 'int32',
                            description: 'Number of records to return.',
                        ),
                        new OA\Property(
                            property: 'columns',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables column definitions.',
                        ),
                        new OA\Property(
                            property: 'order',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables ordering parameters.',
                        ),
                        new OA\Property(
                            property: 'report_type',
                            type: 'string',
                            description: 'The type of user management report to retrieve.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'DataTables server-side response',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'draw', type: 'integer'),
                    new OA\Property(property: 'recordsTotal', type: 'integer'),
                    new OA\Property(property: 'recordsFiltered', type: 'integer'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
    ],
)]
class OpenApiUserManagementReports {}

#[OA\Get(
    path: '/reports/user_management_reports_unique_column_data',
    summary: 'Get unique column values for user management report filtering',
    operationId: 'userManagementReportsUniqueColumnData',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            parameter: 'column',
            in: 'query',
            name: 'column',
            description: 'The column name to retrieve unique values for.',
            required: true,
            schema: new OA\Schema(type: 'string'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of unique values for the specified column',
            content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
)]
class OpenApiUserManagementReportsUniqueColumnData {}

#[OA\Post(
    path: '/reports/my_open_risk',
    summary: "Get the authenticated user's open risks in DataTables format",
    operationId: 'myOpenRisk',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'draw',
                            type: 'integer',
                            format: 'int32',
                            description: 'DataTables draw counter.',
                        ),
                        new OA\Property(
                            property: 'start',
                            type: 'integer',
                            format: 'int32',
                            description: 'Paging first record indicator.',
                        ),
                        new OA\Property(
                            property: 'length',
                            type: 'integer',
                            format: 'int32',
                            description: 'Number of records to return.',
                        ),
                        new OA\Property(
                            property: 'columns',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables column definitions.',
                        ),
                        new OA\Property(
                            property: 'order',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables ordering parameters.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'DataTables server-side response',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'draw', type: 'integer'),
                    new OA\Property(property: 'recordsTotal', type: 'integer'),
                    new OA\Property(property: 'recordsFiltered', type: 'integer'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
    ],
)]
class OpenApiMyOpenRisk {}

#[OA\Post(
    path: '/reports/recent_commented_risk',
    summary: 'Get recently commented risks in DataTables format',
    operationId: 'recentCommentedRisk',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'draw',
                            type: 'integer',
                            format: 'int32',
                            description: 'DataTables draw counter.',
                        ),
                        new OA\Property(
                            property: 'start',
                            type: 'integer',
                            format: 'int32',
                            description: 'Paging first record indicator.',
                        ),
                        new OA\Property(
                            property: 'length',
                            type: 'integer',
                            format: 'int32',
                            description: 'Number of records to return.',
                        ),
                        new OA\Property(
                            property: 'columns',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables column definitions.',
                        ),
                        new OA\Property(
                            property: 'order',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'DataTables ordering parameters.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'DataTables server-side response',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'draw', type: 'integer'),
                    new OA\Property(property: 'recordsTotal', type: 'integer'),
                    new OA\Property(property: 'recordsFiltered', type: 'integer'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
    ],
)]
class OpenApiRecentCommentedRisk {}

#[OA\Get(
    path: '/reports/governance/control_gap_analysis',
    summary: 'Get the control gap analysis report data',
    operationId: 'controlGapAnalysis',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Control gap analysis report data',
            content: new OA\JsonContent(type: 'object'),
        ),
    ],
)]
class OpenApiControlGapAnalysis {}

#[OA\Post(
    path: '/reports/dynamic_unique_column_data',
    summary: 'Get unique column values for dynamic risk report filtering',
    operationId: 'dynamicRiskUniqueColumnData',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['column'],
                    properties: [
                        new OA\Property(
                            property: 'column',
                            type: 'string',
                            description: 'The column name to retrieve unique values for.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of unique values for the specified column',
            content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
)]
class OpenApiDynamicRiskUniqueColumnData {}

#[OA\Post(
    path: '/reports/save-dynamic-selections',
    summary: 'Save a named dynamic risk report filter selection',
    operationId: 'saveDynamicSelections',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The name to save this filter selection under.',
                        ),
                        new OA\Property(
                            property: 'filters',
                            type: 'object',
                            description: 'The filter criteria to save.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [new OA\Response(response: 200, description: 'Dynamic selection saved successfully.')],
)]
class OpenApiSaveDynamicSelections {}

#[OA\Post(
    path: '/reports/delete-dynamic-selection',
    summary: 'Delete a saved dynamic risk report filter selection',
    operationId: 'deleteDynamicSelection',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['id'],
                    properties: [
                        new OA\Property(
                            property: 'id',
                            type: 'integer',
                            format: 'int32',
                            description: 'The ID of the saved selection to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [new OA\Response(response: 200, description: 'Dynamic selection deleted successfully.')],
)]
class OpenApiDeleteDynamicSelection {}

#[OA\Post(
    path: '/reports/save-graphical-selections',
    summary: 'Save a named graphical report filter selection',
    operationId: 'saveGraphicalSelections',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The name to save this filter selection under.',
                        ),
                        new OA\Property(
                            property: 'filters',
                            type: 'object',
                            description: 'The filter criteria to save.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [new OA\Response(response: 200, description: 'Graphical selection saved successfully.')],
)]
class OpenApiSaveGraphicalSelections {}

#[OA\Post(
    path: '/reports/delete-graphical-selection',
    summary: 'Delete a saved graphical report filter selection',
    operationId: 'deleteGraphicalSelection',
    tags: ['reports'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['id'],
                    properties: [
                        new OA\Property(
                            property: 'id',
                            type: 'integer',
                            format: 'int32',
                            description: 'The ID of the saved selection to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [new OA\Response(response: 200, description: 'Graphical selection deleted successfully.')],
)]
class OpenApiDeleteGraphicalSelection {}

?>
