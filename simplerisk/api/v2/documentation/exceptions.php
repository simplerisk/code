<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/exceptions/create',
    summary: 'Create a new policy or control exception.',
    operationId: 'createException',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name', 'owner'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', description: 'The name of the exception.'),
                        new OA\Property(
                            property: 'owner',
                            type: 'integer',
                            description: 'User ID of the exception owner.',
                        ),
                        new OA\Property(
                            property: 'policy',
                            type: 'integer',
                            description: 'Policy document ID when creating a policy exception.',
                        ),
                        new OA\Property(
                            property: 'framework',
                            type: 'integer',
                            description: 'ID of the framework associated with the exception.',
                        ),
                        new OA\Property(
                            property: 'control',
                            type: 'integer',
                            description: 'Control ID when creating a control exception.',
                        ),
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            description: 'The status of the exception.',
                        ),
                        new OA\Property(
                            property: 'additional_stakeholders',
                            type: 'array',
                            description: 'User IDs of additional stakeholders.',
                            items: new OA\Items(type: 'integer'),
                        ),
                        new OA\Property(
                            property: 'creation_date',
                            type: 'string',
                            format: 'date',
                            description: 'The creation date of the exception.',
                        ),
                        new OA\Property(
                            property: 'review_frequency',
                            type: 'integer',
                            description: 'How frequently the exception should be reviewed (in days).',
                        ),
                        new OA\Property(
                            property: 'next_review_date',
                            type: 'string',
                            format: 'date',
                            description: 'The date of the next scheduled review.',
                        ),
                        new OA\Property(
                            property: 'approval_date',
                            type: 'string',
                            format: 'date',
                            description: 'The date the exception was approved.',
                        ),
                        new OA\Property(property: 'approver', type: 'integer', description: 'User ID of the approver.'),
                        new OA\Property(
                            property: 'approved',
                            type: 'boolean',
                            description: 'Whether the exception is approved.',
                        ),
                        new OA\Property(
                            property: 'description',
                            type: 'string',
                            description: 'A description of the exception.',
                        ),
                        new OA\Property(
                            property: 'justification',
                            type: 'string',
                            description: 'The justification for the exception.',
                        ),
                        new OA\Property(
                            property: 'associated_risks',
                            type: 'array',
                            description: 'IDs of risks associated with the exception.',
                            items: new OA\Items(type: 'integer'),
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Exception created successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'approved', type: 'boolean'),
                    new OA\Property(property: 'type', type: 'string', enum: ['policy', 'control']),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiCreateException {}

#[OA\Post(
    path: '/exceptions/update',
    summary: 'Update an existing exception.',
    operationId: 'updateException',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['exception_id'],
                    properties: [
                        new OA\Property(
                            property: 'exception_id',
                            type: 'integer',
                            description: 'The ID of the exception to update.',
                        ),
                        new OA\Property(property: 'name', type: 'string', description: 'The name of the exception.'),
                        new OA\Property(
                            property: 'owner',
                            type: 'integer',
                            description: 'User ID of the exception owner.',
                        ),
                        new OA\Property(
                            property: 'policy',
                            type: 'integer',
                            description: 'Policy document ID when updating a policy exception.',
                        ),
                        new OA\Property(
                            property: 'framework',
                            type: 'integer',
                            description: 'ID of the framework associated with the exception.',
                        ),
                        new OA\Property(
                            property: 'control',
                            type: 'integer',
                            description: 'Control ID when updating a control exception.',
                        ),
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            description: 'The status of the exception.',
                        ),
                        new OA\Property(
                            property: 'additional_stakeholders',
                            type: 'array',
                            description: 'User IDs of additional stakeholders.',
                            items: new OA\Items(type: 'integer'),
                        ),
                        new OA\Property(
                            property: 'creation_date',
                            type: 'string',
                            format: 'date',
                            description: 'The creation date of the exception.',
                        ),
                        new OA\Property(
                            property: 'review_frequency',
                            type: 'integer',
                            description: 'How frequently the exception should be reviewed (in days).',
                        ),
                        new OA\Property(
                            property: 'next_review_date',
                            type: 'string',
                            format: 'date',
                            description: 'The date of the next scheduled review.',
                        ),
                        new OA\Property(
                            property: 'approval_date',
                            type: 'string',
                            format: 'date',
                            description: 'The date the exception was approved.',
                        ),
                        new OA\Property(property: 'approver', type: 'integer', description: 'User ID of the approver.'),
                        new OA\Property(
                            property: 'approved',
                            type: 'boolean',
                            description: 'Whether the exception is approved.',
                        ),
                        new OA\Property(
                            property: 'description',
                            type: 'string',
                            description: 'A description of the exception.',
                        ),
                        new OA\Property(
                            property: 'justification',
                            type: 'string',
                            description: 'The justification for the exception.',
                        ),
                        new OA\Property(
                            property: 'associated_risks',
                            type: 'array',
                            description: 'IDs of risks associated with the exception.',
                            items: new OA\Items(type: 'integer'),
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Exception updated successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'approved_original', type: 'boolean'),
                    new OA\Property(property: 'approved', type: 'boolean'),
                    new OA\Property(property: 'type', type: 'string'),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiUpdateException {}

#[OA\Post(
    path: '/exceptions/delete',
    summary: 'Delete an exception.',
    operationId: 'deleteException',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['exception_id'],
                    properties: [
                        new OA\Property(
                            property: 'exception_id',
                            type: 'integer',
                            description: 'The ID of the exception to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Exception deleted successfully.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiDeleteException {}

#[OA\Post(
    path: '/exceptions/approve',
    summary: 'Approve a pending exception.',
    operationId: 'approveException',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['exception_id'],
                    properties: [
                        new OA\Property(
                            property: 'exception_id',
                            type: 'integer',
                            description: 'The ID of the exception to approve.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Exception approved successfully.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiApproveException {}

#[OA\Post(
    path: '/exceptions/unapprove',
    summary: 'Revoke approval for an exception.',
    operationId: 'unapproveException',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['exception_id'],
                    properties: [
                        new OA\Property(
                            property: 'exception_id',
                            type: 'integer',
                            description: 'The ID of the exception to unapprove.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Exception approval revoked successfully.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiUnapproveException {}

#[OA\Post(
    path: '/exceptions/batch-approve',
    summary: 'Approve multiple exceptions at once.',
    operationId: 'batchApproveExceptions',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['exception_ids'],
                    properties: [
                        new OA\Property(
                            property: 'exception_ids',
                            type: 'array',
                            description: 'The IDs of the exceptions to approve.',
                            items: new OA\Items(type: 'integer'),
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Exceptions approved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'approved',
                        type: 'integer',
                        description: 'The number of exceptions approved.',
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: An error occurred while approving the exceptions.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiBatchApproveExceptions {}

#[OA\Post(
    path: '/exceptions/batch-delete',
    summary: 'Delete all exceptions under a policy or control, optionally filtered by approval status.',
    operationId: 'batchDeleteExceptions',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['parent_id', 'type'],
                    properties: [
                        new OA\Property(
                            property: 'parent_id',
                            type: 'integer',
                            description: 'The ID of the policy or control whose exceptions should be deleted.',
                        ),
                        new OA\Property(
                            property: 'type',
                            type: 'string',
                            description: 'Whether to delete exceptions under a policy or control.',
                            enum: ['policy', 'control'],
                        ),
                        new OA\Property(
                            property: 'approved',
                            type: 'boolean',
                            description: 'Optional filter to delete only approved or unapproved exceptions.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Exceptions deleted successfully.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiBatchDeleteExceptions {}

#[OA\Post(
    path: '/exceptions/batch-delete-ids',
    summary: 'Delete an arbitrary set of exceptions by id in one request.',
    operationId: 'batchDeleteExceptionsByIds',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['exception_ids'],
                    properties: [
                        new OA\Property(
                            property: 'exception_ids',
                            type: 'array',
                            description: 'The IDs of the exceptions to delete.',
                            items: new OA\Items(type: 'integer'),
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Exceptions deleted successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'deleted',
                        type: 'integer',
                        description: 'The number of exceptions deleted.',
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: An error occurred while deleting the exceptions.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiBatchDeleteExceptionsByIds {}

#[OA\Get(
    path: '/exceptions/tree',
    summary: 'Get exceptions as a treegrid structure.',
    operationId: 'exceptionsTree',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'type',
            in: 'query',
            description: 'The type of exceptions to retrieve.',
            required: true,
            schema: new OA\Schema(type: 'string', enum: ['policy', 'control', 'unapproved']),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of treegrid row objects.',
            content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object', additionalProperties: true)),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiExceptionsTree {}

#[OA\Get(
    path: '/exceptions/exception',
    summary: 'Get details for a single exception.',
    operationId: 'getException',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'query',
            description: 'The ID of the exception to retrieve.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Exception object with all fields.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'additional_stakeholders',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                    ),
                    new OA\Property(property: 'associated_risks', type: 'array', items: new OA\Items(type: 'integer')),
                ],
                additionalProperties: true,
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiGetException {}

#[OA\Get(
    path: '/exceptions/info',
    summary: 'Get a formatted exception for display including file download link.',
    operationId: 'getExceptionInfo',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'query',
            description: 'The ID of the exception to retrieve.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            name: 'type',
            in: 'query',
            description: 'The type of exception.',
            required: true,
            schema: new OA\Schema(type: 'string', enum: ['policy', 'control']),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Formatted exception data object.',
            content: new OA\JsonContent(type: 'object', additionalProperties: true),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiGetExceptionInfo {}

#[OA\Get(
    path: '/exceptions/audit_log',
    summary: 'Get the audit log for exceptions.',
    operationId: 'exceptionsAuditLog',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'days',
            in: 'query',
            description: 'Number of days of audit log history to retrieve. Defaults to 7.',
            required: false,
            schema: new OA\Schema(type: 'integer', default: 7),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of audit log entries.',
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'timestamp', type: 'string'),
                        new OA\Property(property: 'message', type: 'string'),
                    ],
                ),
            ),
        ),
    ],
)]
class OpenApiExceptionsAuditLog {}

#[OA\Get(
    path: '/exceptions/status',
    summary: 'Get exception status summary information.',
    operationId: 'exceptionsStatus',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Status information object.',
            content: new OA\JsonContent(type: 'object', additionalProperties: true),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiExceptionsStatus {}

#[OA\Get(
    path: '/associated-exceptions/tree',
    summary: 'Get exceptions associated with a specific risk or control as a treegrid.',
    operationId: 'associatedExceptionsTree',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'query',
            description: 'The ID of the risk or control to retrieve associated exceptions for.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            name: 'type',
            in: 'query',
            description: 'The type of parent entity (risk or control).',
            required: true,
            schema: new OA\Schema(type: 'string'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of treegrid row objects.',
            content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object', additionalProperties: true)),
        ),
    ],
)]
class OpenApiAssociatedExceptionsTree {}

#[OA\Post(
    path: '/exceptions/datatable',
    summary: 'Server-side DataTables feed for the Exception report',
    description: 'Returns Exception rows in DataTables server-side format. Gated on the governance permission plus the exception view permission (SR-1721).',
    operationId: 'exceptionsDatatable',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        description: 'Standard DataTables server-side request parameters (draw, start, length, order, columns, search).',
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'draw', type: 'integer', example: 1),
                        new OA\Property(property: 'start', type: 'integer', example: 0),
                        new OA\Property(property: 'length', type: 'integer', example: 10),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'OK: DataTables server-side response.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'draw', type: 'integer', example: 1),
                    new OA\Property(property: 'recordsTotal', type: 'integer', example: 42),
                    new OA\Property(property: 'recordsFiltered', type: 'integer', example: 42),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiExceptionsDatatable {}

?>
