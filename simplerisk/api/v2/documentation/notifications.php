<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

// =====================================================================
// NOTIFICATION SCHEMAS
// =====================================================================

#[OA\Schema(
    schema: 'Notification',
    required: ['id', 'title', 'body', 'source', 'is_promo', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 123),
        new OA\Property(property: 'title', type: 'string', example: 'Risk #123 needs quarterly review'),
        new OA\Property(property: 'body', type: 'string', example: '<p>Quarterly review past due...</p>'),
        new OA\Property(property: 'link', type: 'string', nullable: true),
        new OA\Property(property: 'source', type: 'string', enum: ['workflow', 'remote_promo']),
        new OA\Property(property: 'is_promo', type: 'boolean'),
        new OA\Property(property: 'read_at', type: 'string', nullable: true, format: 'date-time'),
        new OA\Property(property: 'deleted_at', type: 'string', nullable: true, format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
class OpenApiNotificationSchema {}

#[OA\Schema(
    schema: 'NotificationCounts',
    properties: [
        new OA\Property(property: 'unread', type: 'integer'),
        new OA\Property(property: 'all', type: 'integer'),
        new OA\Property(property: 'trash', type: 'integer'),
    ],
)]
class OpenApiNotificationCountsSchema {}

#[OA\Schema(
    schema: 'BulkIdsPayload',
    required: ['ids'],
    properties: [
        new OA\Property(
            property: 'ids',
            type: 'array',
            minItems: 1,
            maxItems: 100,
            items: new OA\Items(type: 'integer', minimum: 1),
        ),
    ],
)]
class OpenApiBulkIdsPayloadSchema {}

#[OA\Schema(
    schema: 'BulkUpdateResponse',
    properties: [
        new OA\Property(property: 'updated', type: 'integer'),
        new OA\Property(property: 'counts', ref: '#/components/schemas/NotificationCounts'),
    ],
)]
class OpenApiBulkUpdateResponseSchema {}

// =====================================================================
// NOTIFICATIONS API
// =====================================================================

#[OA\Get(
    path: '/notifications/counts',
    summary: 'Get notification counts for current user',
    operationId: 'getNotificationCounts',
    tags: ['notifications'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Notification counts for the authenticated user.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/NotificationCounts')],
            ),
        ),
        new OA\Response(response: 401, description: 'UNAUTHORIZED: Authentication required.'),
    ],
)]
class OpenApiGetNotificationCounts {}

#[OA\Get(
    path: '/notifications',
    summary: 'List notifications for current user',
    operationId: 'listNotifications',
    tags: ['notifications'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'tab',
            in: 'query',
            required: true,
            description: 'Which notification tab to retrieve.',
            schema: new OA\Schema(type: 'string', enum: ['unread', 'all', 'trash']),
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Maximum number of notifications to return (1-100, default 50).',
            schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50),
        ),
        new OA\Parameter(
            name: 'offset',
            in: 'query',
            required: false,
            description: 'Number of notifications to skip for pagination (default 0).',
            schema: new OA\Schema(type: 'integer', minimum: 0, default: 0),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Page of notifications for the authenticated user.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'items',
                                type: 'array',
                                items: new OA\Items(ref: '#/components/schemas/Notification'),
                            ),
                            new OA\Property(property: 'total', type: 'integer'),
                            new OA\Property(property: 'limit', type: 'integer'),
                            new OA\Property(property: 'offset', type: 'integer'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: Missing or invalid tab parameter.'),
        new OA\Response(response: 401, description: 'UNAUTHORIZED: Authentication required.'),
    ],
)]
class OpenApiListNotifications {}

#[OA\Post(
    path: '/notifications/mark-read',
    summary: 'Mark notifications as read for the current user',
    operationId: 'markNotificationsRead',
    tags: ['notifications'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: '#/components/schemas/BulkIdsPayload'),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Notifications marked as read.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/BulkUpdateResponse')],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: Missing or invalid ids payload.'),
        new OA\Response(response: 401, description: 'UNAUTHORIZED: Authentication required.'),
    ],
)]
class OpenApiMarkNotificationsRead {}

#[OA\Post(
    path: '/notifications/trash',
    summary: 'Move notifications to trash for the current user',
    operationId: 'trashNotifications',
    tags: ['notifications'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: '#/components/schemas/BulkIdsPayload'),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Notifications moved to trash.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/BulkUpdateResponse')],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: Missing or invalid ids payload.'),
        new OA\Response(response: 401, description: 'UNAUTHORIZED: Authentication required.'),
    ],
)]
class OpenApiTrashNotifications {}

#[OA\Post(
    path: '/notifications/restore',
    summary: 'Restore notifications from trash for the current user',
    operationId: 'restoreNotifications',
    tags: ['notifications'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: '#/components/schemas/BulkIdsPayload'),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Notifications restored from trash.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/BulkUpdateResponse')],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: Missing or invalid ids payload.'),
        new OA\Response(response: 401, description: 'UNAUTHORIZED: Authentication required.'),
    ],
)]
class OpenApiRestoreNotifications {}

?>
