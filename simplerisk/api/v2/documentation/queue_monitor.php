<?php
// @phan-file-suppress PhanDeprecatedClass -- swagger-php 7 deprecates the OpenApi\Attributes classes (removed in 8.0); move these to OpenApi\Spec before upgrading to swagger-php 8

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

// These attributes describe the API contract statically — see comment in general.php.
// GET /admin/queue (the paged list) is documented in admin.php beside /admin/queue/promises.

#[OA\Get(
    path: '/admin/queue/summary',
    summary: 'Queue health summary',
    description: 'Worker liveness (cron_queue_worker and cron_promise_worker heartbeats), the age of the oldest pending task and failures in the last 24 hours. Per-status counts are returned by GET /admin/queue (status_counts).',
    operationId: 'queueMonitorSummary',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Queue summary.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'workers',
                                type: 'object',
                                description: 'Per worker type (cron_queue_worker, cron_promise_worker), across all of its running copies (QUEUE_WORKER_COUNT / PROMISE_WORKER_COUNT in config.php).',
                                properties: [
                                    new OA\Property(property: 'cron_queue_worker', ref: '#/components/schemas/QueueMonitorWorkerStatus'),
                                    new OA\Property(property: 'cron_promise_worker', ref: '#/components/schemas/QueueMonitorWorkerStatus'),
                                ],
                            ),
                            new OA\Property(property: 'oldest_pending_age_seconds', type: 'integer', nullable: true),
                            new OA\Property(property: 'failed_24h', type: 'integer'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 500, description: 'ERROR: The request could not be completed (the cause is logged, not returned).'),
    ],
)]
class OpenApiQueueMonitorSummary {}

#[OA\Schema(
    schema: 'QueueMonitorWorkerStatus',
    type: 'object',
    properties: [
        new OA\Property(property: 'alive', type: 'boolean', description: 'At least one worker of this type has sent a heartbeat in the last three minutes.'),
        new OA\Property(property: 'last_heartbeat', type: 'integer', nullable: true, description: 'Newest heartbeat of any worker of this type (unix time), or null.'),
        new OA\Property(property: 'workers_alive', type: 'integer', description: 'Workers of this type with a heartbeat in the last three minutes.', example: 2),
        new OA\Property(property: 'workers_configured', type: 'integer', description: 'Workers of this type the configuration allows (1 unless config.php raises it).', example: 3),
    ],
)]
class OpenApiQueueMonitorWorkerStatus {}

#[OA\Get(
    path: '/admin/queue/steps',
    summary: 'Steps (promises) of a queue task',
    description: 'The task steps in dependency order with status, duration, retry attempt and error. purged is true when the task has no step rows (retention pruning, or a single-step job that never creates any) so the list is empty. A task id that does not exist returns 404.',
    operationId: 'queueMonitorSteps',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'queue_task_id', in: 'query', description: 'The queue task id.', required: true, schema: new OA\Schema(type: 'integer', example: 123)),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Task steps.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'purged', type: 'boolean'),
                            new OA\Property(
                                property: 'steps',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'stage', type: 'string'),
                                        new OA\Property(property: 'status', type: 'string'),
                                        new OA\Property(property: 'state', type: 'string'),
                                        new OA\Property(property: 'created_at', type: 'string'),
                                        new OA\Property(property: 'updated_at', type: 'string'),
                                        new OA\Property(property: 'duration_seconds', type: 'integer', nullable: true),
                                        new OA\Property(property: 'retry_attempt', type: 'integer', nullable: true),
                                        new OA\Property(property: 'error', type: 'string', nullable: true),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: queue_task_id is missing or not a positive integer.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 404, description: 'NOT FOUND: No queue task with that id.'),
        new OA\Response(response: 500, description: 'ERROR: The request could not be completed (the cause is logged, not returned).'),
    ],
)]
class OpenApiQueueMonitorSteps {}

#[OA\Get(
    path: '/admin/queue/payload',
    summary: 'Redacted payload of a queue task',
    description: 'The task payload with values under secret-looking keys (password, token, api key, secret, authorization, cookie, ...) replaced by "[redacted]". A payload that is not valid JSON comes back as {_raw: text} with the same redaction applied textually.',
    operationId: 'queueMonitorPayload',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'queue_task_id', in: 'query', description: 'The queue task id.', required: true, schema: new OA\Schema(type: 'integer', example: 123)),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Redacted payload.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'queue_task_id', type: 'integer'),
                            new OA\Property(property: 'payload', type: 'object'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: queue_task_id is missing or not a positive integer.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 404, description: 'NOT FOUND: No queue task with that id.'),
        new OA\Response(response: 500, description: 'ERROR: The request could not be completed (the cause is logged, not returned).'),
    ],
)]
class OpenApiQueueMonitorPayload {}

#[OA\Post(
    path: '/admin/queue/cancel',
    summary: 'Cancel queue tasks',
    description: 'Cancels pending tasks immediately; a running task is marked canceled and shows as stopping until its in-flight step ends. Target tasks either by ids or by the current filter (all_filtered with filter, the same keys as GET /admin/queue). At most 500 tasks per request. A task that cannot be actioned is reported as skipped with a reason (wrong_state, not_found, not_actionable, not_restartable, already_restarted, stopping or error); the request itself still succeeds.',
    operationId: 'queueMonitorCancel',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), example: [101, 102]),
                new OA\Property(property: 'all_filtered', type: 'boolean', description: 'Apply to every task matching filter instead of ids.'),
                new OA\Property(property: 'filter', type: 'object', description: 'q, status, task_type, date_from and date_to as on GET /admin/queue, plus optional max_id (only tasks with id <= max_id; send the list response max_id so tasks queued after the count are not touched).'),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Per-task results.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'results',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'result', type: 'string', description: 'applied or skipped.'),
                                        new OA\Property(property: 'reason', type: 'string', nullable: true, description: 'Why a task was skipped.'),
                                    ],
                                ),
                            ),
                            new OA\Property(property: 'applied', type: 'integer'),
                            new OA\Property(property: 'skipped', type: 'integer'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: No tasks were selected or matched (data.code no_targets), or more than 500 (data.code too_many_targets).',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 400),
                    new OA\Property(property: 'status_message', type: 'string'),
                    new OA\Property(property: 'data', type: 'object', properties: [new OA\Property(property: 'code', type: 'string', description: 'no_targets or too_many_targets.', example: 'too_many_targets')]),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 500, description: 'ERROR: The request could not be completed (the cause is logged, not returned).'),
    ],
)]
class OpenApiQueueMonitorCancel {}

#[OA\Post(
    path: '/admin/queue/restart',
    summary: 'Restart failed or canceled queue tasks',
    description: 'Creates a fresh pending task from each failed or canceled task (result carries new_id). A task can be restarted once; a second attempt is skipped with already_restarted, and an all_filtered restart never targets tasks that were already restarted. Targeting, caps and result shape are the same as POST /admin/queue/cancel.',
    operationId: 'queueMonitorRestart',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), example: [101, 102]),
                new OA\Property(property: 'all_filtered', type: 'boolean'),
                new OA\Property(property: 'filter', type: 'object'),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Per-task results (applied entries include new_id).',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'results',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'result', type: 'string'),
                                        new OA\Property(property: 'reason', type: 'string', nullable: true),
                                        new OA\Property(property: 'new_id', type: 'integer', nullable: true),
                                    ],
                                ),
                            ),
                            new OA\Property(property: 'applied', type: 'integer'),
                            new OA\Property(property: 'skipped', type: 'integer'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: No tasks were selected or matched (data.code no_targets), or more than 500 (data.code too_many_targets).',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 400),
                    new OA\Property(property: 'status_message', type: 'string'),
                    new OA\Property(property: 'data', type: 'object', properties: [new OA\Property(property: 'code', type: 'string', description: 'no_targets or too_many_targets.', example: 'too_many_targets')]),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 500, description: 'ERROR: The request could not be completed (the cause is logged, not returned).'),
    ],
)]
class OpenApiQueueMonitorRestart {}

#[OA\Post(
    path: '/admin/queue/run-now',
    summary: 'Run pending queue tasks now',
    description: 'Raises pending tasks to the highest priority so the next worker pass picks them first. Targeting, caps and result shape are the same as POST /admin/queue/cancel.',
    operationId: 'queueMonitorRunNow',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), example: [101, 102]),
                new OA\Property(property: 'all_filtered', type: 'boolean'),
                new OA\Property(property: 'filter', type: 'object'),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Per-task results.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'results',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'result', type: 'string'),
                                        new OA\Property(property: 'reason', type: 'string', nullable: true),
                                    ],
                                ),
                            ),
                            new OA\Property(property: 'applied', type: 'integer'),
                            new OA\Property(property: 'skipped', type: 'integer'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: No tasks were selected or matched (data.code no_targets), or more than 500 (data.code too_many_targets).',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 400),
                    new OA\Property(property: 'status_message', type: 'string'),
                    new OA\Property(property: 'data', type: 'object', properties: [new OA\Property(property: 'code', type: 'string', description: 'no_targets or too_many_targets.', example: 'too_many_targets')]),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 500, description: 'ERROR: The request could not be completed (the cause is logged, not returned).'),
    ],
)]
class OpenApiQueueMonitorRunNow {}

#[OA\Post(
    path: '/admin/queue/delete',
    summary: 'Delete finished queue tasks',
    description: 'Permanently deletes completed, failed or canceled tasks and their step records; this cannot be undone. Pending and running tasks are never deleted: an id in another state is skipped with wrong_state, and an all_filtered delete only ever matches completed, failed and canceled tasks whatever statuses the filter names (a filter naming none of them matches nothing and returns 400 no_targets). Tasks of the Encrypted Database Extra and task types whose finished history is kept as program state (ai_risk_fair_analyze) are skipped with not_actionable and are not counted by all_filtered. A task with a step still running is skipped with stopping (a canceled task) or steps_running. Targeting by ids or all_filtered with filter (including max_id), the 500 cap and the result shape are the same as POST /admin/queue/cancel.',
    operationId: 'queueMonitorDelete',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), example: [101, 102]),
                new OA\Property(property: 'all_filtered', type: 'boolean', description: 'Apply to every deletable task matching filter instead of ids.'),
                new OA\Property(property: 'filter', type: 'object', description: 'q, status, task_type, date_from and date_to as on GET /admin/queue, plus optional max_id. The statuses are narrowed to completed, failed and canceled.'),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Per-task results.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'results',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'result', type: 'string', description: 'applied (deleted) or skipped.'),
                                        new OA\Property(property: 'reason', type: 'string', nullable: true, description: 'Why a task was skipped: wrong_state, not_found, not_actionable, stopping, steps_running or error.'),
                                    ],
                                ),
                            ),
                            new OA\Property(property: 'applied', type: 'integer'),
                            new OA\Property(property: 'skipped', type: 'integer'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: No tasks were selected or matched (data.code no_targets), or more than 500 (data.code too_many_targets).',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 400),
                    new OA\Property(property: 'status_message', type: 'string'),
                    new OA\Property(property: 'data', type: 'object', properties: [new OA\Property(property: 'code', type: 'string', description: 'no_targets or too_many_targets.', example: 'no_targets')]),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 500, description: 'ERROR: The request could not be completed (the cause is logged, not returned).'),
    ],
)]
class OpenApiQueueMonitorDelete {}

#[OA\Get(
    path: '/admin/queue/settings',
    summary: 'Queue retention settings',
    description: 'How many days finished tasks are kept. 0 means never prune. Returns the defaults (30 completed, 90 failed) until the settings are saved.',
    operationId: 'queueMonitorGetSettings',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Retention windows in days.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'completed_days', type: 'integer', example: 30),
                            new OA\Property(property: 'failed_days', type: 'integer', example: 90),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 500, description: 'ERROR: The request could not be completed (the cause is logged, not returned).'),
    ],
)]
class OpenApiQueueMonitorGetSettings {}

#[OA\Post(
    path: '/admin/queue/settings',
    summary: 'Save queue retention settings',
    description: 'Both values are required whole numbers from 0 to 3650 (0 keeps tasks forever). Nothing is saved unless both are valid.',
    operationId: 'queueMonitorSaveSettings',
    tags: ['Administrator Operations'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['completed_days', 'failed_days'],
            properties: [
                new OA\Property(property: 'completed_days', type: 'integer', minimum: 0, maximum: 3650, example: 30),
                new OA\Property(property: 'failed_days', type: 'integer', minimum: 0, maximum: 3650, example: 90),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'The saved values.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'status', type: 'integer', example: 200),
                    new OA\Property(property: 'status_message', type: 'string', example: 'SUCCESS'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'completed_days', type: 'integer'),
                            new OA\Property(property: 'failed_days', type: 'integer'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: A value is missing, not a whole number, or outside 0 to 3650.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: The user does not have admin privileges.'),
        new OA\Response(response: 500, description: 'ERROR: The request could not be completed (the cause is logged, not returned).'),
    ],
)]
class OpenApiQueueMonitorSaveSettings {}
