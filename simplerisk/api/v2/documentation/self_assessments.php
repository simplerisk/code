<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

// =====================================================================
// SELF-ASSESSMENTS API (core, SCF-based)
// =====================================================================

#[OA\Get(
    path: '/self-assessments/frameworks',
    summary: 'List SCF frameworks available for self-assessment, with question counts',
    operationId: 'apiV2SelfAssessmentFrameworks',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'scope',
            in: 'query',
            required: false,
            description: "'enabled' (governance permission required) or 'all' (default). Non-governance users never see the `enabled` flag on scope=all results.",
            schema: new OA\Schema(type: 'string', enum: ['enabled', 'all']),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Framework list.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'frameworks',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'scf_source_id', type: 'integer'),
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'question_count', type: 'integer'),
                                        new OA\Property(
                                            property: 'enabled',
                                            type: 'boolean',
                                            description: 'Only present for governance-permitted users.',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: Insufficient permission, or governance permission required for scope=enabled.',
        ),
        new OA\Response(response: 409, description: 'CONFLICT: SCF is not installed.'),
    ],
)]
class OpenApiSelfAssessmentFrameworks {}

#[OA\Get(
    path: '/self-assessments/control-results',
    summary: 'List control responses across completed self-assessments (Failed Controls tab), paginated and filterable by response status',
    operationId: 'apiV2SelfAssessmentControlResults',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'status',
            in: 'query',
            required: false,
            description: "Filter on the response value. 'all' returns every answered (pass/fail/na) response. Defaults to 'fail'.",
            schema: new OA\Schema(type: 'string', enum: ['pass', 'fail', 'na', 'all'], default: 'fail'),
        ),
        new OA\Parameter(
            name: 'page',
            in: 'query',
            required: false,
            description: '1-based page number. Defaults to 1.',
            schema: new OA\Schema(type: 'integer', default: 1),
        ),
        new OA\Parameter(
            name: 'per_page',
            in: 'query',
            required: false,
            description: 'Rows per page, clamped to [1, 200]. Defaults to 25.',
            schema: new OA\Schema(type: 'integer', default: 25),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Paginated control results.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'control_results',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'assessment_id', type: 'integer'),
                                        new OA\Property(property: 'assessment_date', type: 'string', format: 'date'),
                                        new OA\Property(property: 'framework', type: 'string'),
                                        new OA\Property(property: 'control_number', type: 'string'),
                                        new OA\Property(property: 'short_name', type: 'string'),
                                        new OA\Property(property: 'question', type: 'string'),
                                        new OA\Property(
                                            property: 'answer',
                                            type: 'string',
                                            enum: ['pass', 'fail', 'na'],
                                        ),
                                        new OA\Property(
                                            property: 'control_status',
                                            type: 'integer',
                                            description: 'Native control status, where applicable (0 = Fail, 1 = Pass, 2 = Not Tested).',
                                        ),
                                    ],
                                ),
                            ),
                            new OA\Property(
                                property: 'total',
                                type: 'integer',
                                description: 'Total matching rows across all pages.',
                            ),
                            new OA\Property(property: 'page', type: 'integer'),
                            new OA\Property(property: 'per_page', type: 'integer'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: Insufficient permission.'),
    ],
)]
class OpenApiSelfAssessmentControlResults {}

#[OA\Get(
    path: '/self-assessments/pending-risks',
    summary: 'List pending risks generated by self-assessments, with their failed control numbers',
    operationId: 'apiV2SelfAssessmentPendingRisks',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Pending risk list.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'pending_risks',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'subject', type: 'string'),
                                        new OA\Property(property: 'score', type: 'integer'),
                                        new OA\Property(property: 'comment', type: 'string'),
                                        new OA\Property(property: 'risk_catalog_id', type: 'integer'),
                                        new OA\Property(property: 'self_assessment_id', type: 'integer'),
                                        new OA\Property(property: 'assessment_name', type: 'string'),
                                        new OA\Property(property: 'framework_name', type: 'string'),
                                        new OA\Property(
                                            property: 'failed_controls',
                                            type: 'string',
                                            description: 'Comma-separated SCF control numbers that generated this pending risk.',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: Insufficient permission.'),
    ],
)]
class OpenApiSelfAssessmentPendingRisks {}

#[OA\Post(
    path: '/self-assessments/pending-risks/{id}/push',
    summary: 'Push a self-assessment-generated pending risk to a real risk',
    operationId: 'apiV2SelfAssessmentPushRisk',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the pending risk to push.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    requestBody: new OA\RequestBody(
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'owner',
                            type: 'integer',
                            description: 'User ID to assign as risk owner.',
                        ),
                        new OA\Property(
                            property: 'submission_date',
                            type: 'string',
                            format: 'date',
                            description: 'Submission date. Defaults to today when omitted.',
                        ),
                        new OA\Property(
                            property: 'notes',
                            type: 'string',
                            description: 'Additional notes for the risk.',
                        ),
                        new OA\Property(
                            property: 'assets_asset_groups',
                            type: 'string',
                            description: 'Comma-separated asset/asset-group identifiers.',
                        ),
                        new OA\Property(
                            property: 'scoring',
                            type: 'object',
                            description: 'Flat scoring payload keyed like the legacy risk-submission scoring fields (scoring_method, CLASSIC_likelihood, ..., ContributingImpacts).',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Risk created from the pending risk.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [new OA\Property(property: 'risk_id', type: 'integer')],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: Empty subject or risk creation failed.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: Insufficient permission (requires both assessments and submit_risks).',
        ),
        new OA\Response(response: 404, description: 'NOT FOUND: Pending risk ID not found.'),
    ],
)]
class OpenApiSelfAssessmentPushRisk {}

#[OA\Delete(
    path: '/self-assessments/pending-risks/{id}',
    summary: 'Delete a self-assessment-generated pending risk without pushing it to a real risk',
    operationId: 'apiV2SelfAssessmentDeletePendingRisk',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the pending risk to delete.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Pending risk deleted successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Insufficient permission.'),
    ],
)]
class OpenApiSelfAssessmentDeletePendingRisk {}

#[OA\Get(
    path: '/self-assessments',
    summary: 'List self-assessment runs',
    operationId: 'apiV2SelfAssessmentsList',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Self-assessment run list.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'self_assessments',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'scf_source_id', type: 'integer'),
                                        new OA\Property(property: 'framework_name', type: 'string'),
                                        new OA\Property(
                                            property: 'status',
                                            type: 'string',
                                            enum: ['in_progress', 'completed'],
                                        ),
                                        new OA\Property(property: 'started_by', type: 'integer'),
                                        new OA\Property(property: 'started_by_name', type: 'string'),
                                        new OA\Property(property: 'answered_count', type: 'integer'),
                                        new OA\Property(property: 'failed_count', type: 'integer'),
                                        new OA\Property(property: 'total_count', type: 'integer'),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: Insufficient permission.'),
    ],
)]
class OpenApiSelfAssessmentsList {}

#[OA\Post(
    path: '/self-assessments',
    summary: 'Start a self-assessment run for a framework',
    operationId: 'apiV2SelfAssessmentCreate',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['scf_source_id'],
                    properties: [
                        new OA\Property(
                            property: 'scf_source_id',
                            type: 'integer',
                            description: 'The SCF authoritative source to assess against.',
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: "Run name. Defaults to '<framework name> — <today's date>' when omitted.",
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Self-assessment run created.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'assessment', type: 'object'),
                            new OA\Property(property: 'controls', type: 'array', items: new OA\Items(type: 'object')),
                            new OA\Property(property: 'responses', type: 'object'),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Missing scf_source_id, or invalid framework / no controls.',
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: Insufficient permission.'),
        new OA\Response(response: 409, description: 'CONFLICT: SCF is not installed.'),
        new OA\Response(response: 500, description: 'INTERNAL SERVER ERROR: Failed to create self-assessment.'),
    ],
)]
class OpenApiSelfAssessmentCreate {}

#[OA\Get(
    path: '/self-assessments/{id}',
    summary: 'Get a self-assessment run, its framework controls, and any responses recorded so far',
    operationId: 'apiV2SelfAssessmentGet',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the self-assessment run.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Self-assessment run detail.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'assessment', type: 'object'),
                            new OA\Property(
                                property: 'controls',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'control_id', type: 'integer'),
                                        new OA\Property(property: 'control_number', type: 'string'),
                                        new OA\Property(property: 'short_name', type: 'string'),
                                        new OA\Property(property: 'domain', type: 'string'),
                                        new OA\Property(property: 'question', type: 'string'),
                                        new OA\Property(property: 'weighting', type: 'integer'),
                                    ],
                                ),
                            ),
                            new OA\Property(
                                property: 'responses',
                                type: 'object',
                                description: 'Keyed by control_id, each value an object with response and comment.',
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: Insufficient permission.'),
        new OA\Response(response: 404, description: 'NOT FOUND: Self-assessment ID not found.'),
    ],
)]
class OpenApiSelfAssessmentGet {}

#[OA\Patch(
    path: '/self-assessments/{id}/responses',
    summary: 'Save (upsert) responses for a self-assessment run that has not yet been completed',
    operationId: 'apiV2SelfAssessmentSaveResponses',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the self-assessment run.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['responses'],
                    properties: [
                        new OA\Property(
                            property: 'responses',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                required: ['control_id'],
                                properties: [
                                    new OA\Property(property: 'control_id', type: 'integer'),
                                    new OA\Property(
                                        property: 'response',
                                        type: 'string',
                                        enum: ['', 'pass', 'fail', 'na'],
                                        description: 'Omit (or send null) to leave the stored answer unchanged. Send an empty string to reset the control to unanswered.',
                                    ),
                                    new OA\Property(
                                        property: 'comment',
                                        type: 'string',
                                        description: 'Omit (or send null) to leave the stored comment unchanged. Send an empty string to clear it.',
                                    ),
                                ],
                            ),
                            description: "Each item is a partial update: only the fields present are written, and an omitted field keeps its stored value. Items not belonging to this run's framework, or carrying an invalid response value, are silently skipped.",
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Responses saved.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'saved',
                                type: 'integer',
                                description: 'Count of responses actually written; may be less than the count submitted if some were skipped.',
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: responses is not an array.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Insufficient permission.'),
        new OA\Response(response: 404, description: 'NOT FOUND: Self-assessment ID not found.'),
        new OA\Response(response: 409, description: 'CONFLICT: Assessment already completed.'),
    ],
)]
class OpenApiSelfAssessmentSaveResponses {}

#[OA\Post(
    path: '/self-assessments/{id}/complete',
    summary: 'Complete a self-assessment run, generating pending risks from any failed controls',
    operationId: 'apiV2SelfAssessmentComplete',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the self-assessment run.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Assessment completed.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'pending_risks',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'pending_risk_id', type: 'integer'),
                                        new OA\Property(property: 'risk_catalog_id', type: 'integer'),
                                        new OA\Property(property: 'number', type: 'string'),
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'score', type: 'integer'),
                                        new OA\Property(
                                            property: 'control_ids',
                                            type: 'array',
                                            items: new OA\Items(type: 'integer'),
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: Insufficient permission.'),
        new OA\Response(response: 404, description: 'NOT FOUND: Self-assessment ID not found.'),
        new OA\Response(response: 409, description: 'CONFLICT: Already completed.'),
    ],
)]
class OpenApiSelfAssessmentComplete {}

#[OA\Delete(
    path: '/self-assessments/{id}',
    summary: 'Delete a self-assessment run and its responses',
    operationId: 'apiV2SelfAssessmentDelete',
    tags: ['self_assessments'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the self-assessment run to delete.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Self-assessment run deleted successfully. Any pending risks it already generated are intentionally retained.',
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: Insufficient permission.'),
        new OA\Response(response: 404, description: 'NOT FOUND: Self-assessment ID not found.'),
    ],
)]
class OpenApiSelfAssessmentDelete {}

?>
