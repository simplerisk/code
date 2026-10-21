<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/documents/create',
    summary: 'Create a new policy or procedure document.',
    operationId: 'createDocument',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['document_type', 'document_name'],
                    properties: [
                        new OA\Property(
                            property: 'document_type',
                            type: 'string',
                            description: 'The type of document to create.',
                            enum: ['policies', 'procedures'],
                        ),
                        new OA\Property(
                            property: 'document_name',
                            type: 'string',
                            description: 'The name of the document.',
                        ),
                        new OA\Property(
                            property: 'framework_ids',
                            type: 'array',
                            description: 'IDs of frameworks to associate with the document.',
                            items: new OA\Items(type: 'integer'),
                        ),
                        new OA\Property(
                            property: 'control_ids',
                            type: 'array',
                            description: 'IDs of controls to associate with the document.',
                            items: new OA\Items(type: 'integer'),
                        ),
                        new OA\Property(property: 'parent', type: 'integer', description: 'ID of the parent document.'),
                        new OA\Property(property: 'status', type: 'string', description: 'The status of the document.'),
                        new OA\Property(
                            property: 'creation_date',
                            type: 'string',
                            format: 'date',
                            description: 'The creation date of the document.',
                        ),
                        new OA\Property(
                            property: 'last_review_date',
                            type: 'string',
                            format: 'date',
                            description: 'The date the document was last reviewed.',
                        ),
                        new OA\Property(
                            property: 'review_frequency',
                            type: 'integer',
                            description: 'How frequently the document should be reviewed (in days).',
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
                            description: 'The date the document was approved.',
                        ),
                        new OA\Property(
                            property: 'document_owner',
                            type: 'integer',
                            description: 'User ID of the document owner.',
                        ),
                        new OA\Property(
                            property: 'additional_stakeholders',
                            type: 'array',
                            description: 'User IDs of additional stakeholders.',
                            items: new OA\Items(type: 'integer'),
                        ),
                        new OA\Property(property: 'approver', type: 'integer', description: 'User ID of the approver.'),
                        new OA\Property(
                            property: 'team_ids',
                            type: 'array',
                            description: 'IDs of teams associated with the document.',
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
            description: 'Document created successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'string'),
                    new OA\Property(property: 'document_type', type: 'string'),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: An error occurred while creating the document.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have governance or add_documentation permission.',
        ),
    ],
)]
class OpenApiCreateDocument {}

#[OA\Post(
    path: '/documents/update',
    summary: 'Update an existing policy or procedure document.',
    operationId: 'updateDocument',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['document_id'],
                    properties: [
                        new OA\Property(
                            property: 'document_id',
                            type: 'integer',
                            description: 'The ID of the document to update.',
                        ),
                        new OA\Property(
                            property: 'document_type',
                            type: 'string',
                            description: 'The type of document.',
                            enum: ['policies', 'procedures'],
                        ),
                        new OA\Property(
                            property: 'document_name',
                            type: 'string',
                            description: 'The name of the document.',
                        ),
                        new OA\Property(
                            property: 'framework_ids',
                            type: 'array',
                            description: 'IDs of frameworks to associate with the document.',
                            items: new OA\Items(type: 'integer'),
                        ),
                        new OA\Property(
                            property: 'control_ids',
                            type: 'array',
                            description: 'IDs of controls to associate with the document.',
                            items: new OA\Items(type: 'integer'),
                        ),
                        new OA\Property(property: 'parent', type: 'integer', description: 'ID of the parent document.'),
                        new OA\Property(property: 'status', type: 'string', description: 'The status of the document.'),
                        new OA\Property(
                            property: 'creation_date',
                            type: 'string',
                            format: 'date',
                            description: 'The creation date of the document.',
                        ),
                        new OA\Property(
                            property: 'last_review_date',
                            type: 'string',
                            format: 'date',
                            description: 'The date the document was last reviewed.',
                        ),
                        new OA\Property(
                            property: 'review_frequency',
                            type: 'integer',
                            description: 'How frequently the document should be reviewed (in days).',
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
                            description: 'The date the document was approved.',
                        ),
                        new OA\Property(
                            property: 'document_owner',
                            type: 'integer',
                            description: 'User ID of the document owner.',
                        ),
                        new OA\Property(
                            property: 'additional_stakeholders',
                            type: 'array',
                            description: 'User IDs of additional stakeholders.',
                            items: new OA\Items(type: 'integer'),
                        ),
                        new OA\Property(property: 'approver', type: 'integer', description: 'User ID of the approver.'),
                        new OA\Property(
                            property: 'team_ids',
                            type: 'array',
                            description: 'IDs of teams associated with the document.',
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
            description: 'Document updated successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [new OA\Property(property: 'document_type', type: 'string')],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: An error occurred while updating the document.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiUpdateDocument {}

#[OA\Post(
    path: '/documents/delete',
    summary: 'Delete a document (POST variant).',
    operationId: 'deleteDocumentPost',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['document_id'],
                    properties: [
                        new OA\Property(
                            property: 'document_id',
                            type: 'integer',
                            description: 'The ID of the document to delete.',
                        ),
                        new OA\Property(
                            property: 'version',
                            type: 'integer',
                            description: 'The version of the document to delete.',
                        ),
                        new OA\Property(
                            property: 'document_type',
                            type: 'string',
                            description: 'The type of document to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Document deleted successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [new OA\Property(property: 'document_type', type: 'string')],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiDeleteDocumentPost {}

#[OA\Post(
    path: '/documents/approve',
    summary: 'Approve a policy or procedure document.',
    operationId: 'approveDocument',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['document_id'],
                    properties: [
                        new OA\Property(
                            property: 'document_id',
                            type: 'integer',
                            description: 'The ID of the document to approve.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Document approved successfully.'),
        new OA\Response(response: 400, description: 'BAD REQUEST: An error occurred while approving the document.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiApproveDocument {}

#[OA\Post(
    path: '/documents/unapprove',
    summary: 'Unapprove a previously approved policy or procedure document.',
    operationId: 'unapproveDocument',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['document_id'],
                    properties: [
                        new OA\Property(
                            property: 'document_id',
                            type: 'integer',
                            description: 'The ID of the document to unapprove.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Document unapproved successfully.'),
        new OA\Response(response: 400, description: 'BAD REQUEST: An error occurred while unapproving the document.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiUnapproveDocument {}

#[OA\Post(
    path: '/documents/batch-approve',
    summary: 'Approve multiple policy or procedure documents at once.',
    operationId: 'batchApproveDocument',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['document_ids'],
                    properties: [
                        new OA\Property(
                            property: 'document_ids',
                            type: 'array',
                            description: 'The IDs of the documents to approve.',
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
            description: 'Documents approved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'approved',
                        type: 'integer',
                        description: 'The number of documents approved.',
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: An error occurred while approving the documents.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiBatchApproveDocument {}

#[OA\Post(
    path: '/documents/batch-delete',
    summary: 'Delete multiple policy or procedure documents at once.',
    operationId: 'batchDeleteDocument',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['document_ids'],
                    properties: [
                        new OA\Property(
                            property: 'document_ids',
                            type: 'array',
                            description: 'The IDs of the documents to delete.',
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
            description: 'Documents deleted successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'deleted',
                        type: 'integer',
                        description: 'The number of documents deleted.',
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: An error occurred while deleting the documents.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiBatchDeleteDocument {}

#[OA\Get(
    path: '/governance/documents/audit_log',
    summary: 'Get the audit log for Document Program documents.',
    operationId: 'documentsAuditLog',
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
                        new OA\Property(
                            property: 'timestamp_display',
                            type: 'string',
                            description: "Timestamp formatted per the instance's configured date/time display format.",
                        ),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'activity',
                            type: 'string',
                            description: 'Server-classified activity type.',
                            enum: [
                                'create',
                                'update',
                                'delete',
                                'delete_version',
                                'approve',
                                'unapprove',
                                'download',
                                'upload',
                                'other',
                            ],
                        ),
                        new OA\Property(
                            property: 'document_id',
                            type: 'integer',
                            description: '0 for a row that predates the write_log() +1000 fix.',
                        ),
                        new OA\Property(
                            property: 'document_name',
                            type: 'string',
                            nullable: true,
                            description: 'Null once the document has been deleted.',
                        ),
                        new OA\Property(property: 'user_id', type: 'integer'),
                        new OA\Property(property: 'user_name', type: 'string', nullable: true),
                    ],
                ),
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiDocumentsAuditLog {}

#[OA\Get(
    path: '/governance/documents/versions',
    summary: "Get the version history of a policy or procedure document's compliance file.",
    operationId: 'getDocumentVersions',
    tags: ['governance'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'document_id',
            in: 'query',
            description: 'The ID of the document whose version history is being requested.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Array of document file versions, most recent first.',
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'compliance_file_id', type: 'integer'),
                        new OA\Property(
                            property: 'is_current',
                            type: 'boolean',
                            description: "Whether this version is the document's current file.",
                        ),
                        new OA\Property(property: 'version', type: 'integer'),
                        new OA\Property(property: 'file_name', type: 'string'),
                        new OA\Property(property: 'unique_name', type: 'string'),
                        new OA\Property(property: 'file_size', type: 'integer'),
                        new OA\Property(property: 'uploaded_at', type: 'string'),
                        new OA\Property(property: 'uploaded_by', type: 'string', nullable: true),
                    ],
                ),
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: The document_id parameter is missing/invalid, or the user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiGetDocumentVersions {}

?>
