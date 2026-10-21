<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/riskformula/add_impact',
    summary: 'Add a new impact level to the risk formula.',
    operationId: 'addImpact',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['value'],
                    properties: [
                        new OA\Property(
                            property: 'value',
                            type: 'integer',
                            description: 'The numeric value of the new impact level.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Impact level added successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiAddImpact {}

#[OA\Post(
    path: '/riskformula/delete_impact',
    summary: 'Delete an impact level from the risk formula.',
    operationId: 'deleteImpact',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['value'],
                    properties: [
                        new OA\Property(
                            property: 'value',
                            type: 'integer',
                            description: 'The numeric value of the impact level to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Impact level deleted successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiDeleteImpact {}

#[OA\Post(
    path: '/riskformula/add_likelihood',
    summary: 'Add a new likelihood level to the risk formula.',
    operationId: 'addLikelihood',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['value'],
                    properties: [
                        new OA\Property(
                            property: 'value',
                            type: 'integer',
                            description: 'The numeric value of the new likelihood level.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Likelihood level added successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiAddLikelihood {}

#[OA\Post(
    path: '/riskformula/delete_likelihood',
    summary: 'Delete a likelihood level from the risk formula.',
    operationId: 'deleteLikelihood',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['value'],
                    properties: [
                        new OA\Property(
                            property: 'value',
                            type: 'integer',
                            description: 'The numeric value of the likelihood level to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Likelihood level deleted successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiDeleteLikelihood {}

#[OA\Post(
    path: '/riskformula/update_impact_or_likelihood_name',
    summary: 'Update the display name for an impact or likelihood level.',
    operationId: 'updateImpactOrLikelihoodName',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['value', 'name', 'type'],
                    properties: [
                        new OA\Property(
                            property: 'value',
                            type: 'integer',
                            description: 'The numeric value of the impact or likelihood level.',
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The new display name for the level.',
                        ),
                        new OA\Property(
                            property: 'type',
                            type: 'string',
                            description: 'Whether to update an impact or likelihood level.',
                            enum: ['impact', 'likelihood'],
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Name updated successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'value', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'type', type: 'string'),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiUpdateImpactOrLikelihoodName {}

#[OA\Post(
    path: '/riskformula/update_custom_score',
    summary: 'Update the custom risk score for a specific impact-likelihood combination.',
    operationId: 'updateCustomScore',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['impact', 'likelihood', 'score'],
                    properties: [
                        new OA\Property(property: 'impact', type: 'integer', description: 'The impact level value.'),
                        new OA\Property(
                            property: 'likelihood',
                            type: 'integer',
                            description: 'The likelihood level value.',
                        ),
                        new OA\Property(
                            property: 'score',
                            type: 'number',
                            format: 'float',
                            minimum: 0,
                            maximum: 10,
                            description: 'The custom risk score for this impact-likelihood combination.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Custom score updated successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'impact', type: 'integer'),
                    new OA\Property(property: 'likelihood', type: 'integer'),
                    new OA\Property(property: 'score', type: 'number'),
                    new OA\Property(property: 'color', type: 'string'),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiUpdateCustomScore {}

#[OA\Post(
    path: '/risklevel/update',
    summary: 'Update a risk level property (value, color, or display name).',
    operationId: 'updateRiskLevel',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['level', 'field', 'value'],
                    properties: [
                        new OA\Property(
                            property: 'level',
                            type: 'integer',
                            description: 'The risk level to update. 0=Very Low, 1=Low, 2=Medium, 3=High, 4=Very High.',
                            enum: [0, 1, 2, 3, 4],
                        ),
                        new OA\Property(
                            property: 'field',
                            type: 'string',
                            description: 'The field to update.',
                            enum: ['value', 'color', 'display_name'],
                        ),
                        new OA\Property(
                            property: 'value',
                            type: 'string',
                            description: 'The new value for the specified field.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Risk level updated successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiUpdateRiskLevel {}

#[OA\Post(
    path: '/contributing_risks/add',
    summary: 'Add a new contributing risk likelihood or impact entry.',
    operationId: 'addContributingRisk',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['table', 'name'],
                    properties: [
                        new OA\Property(
                            property: 'table',
                            type: 'string',
                            description: 'The table to add the entry to.',
                            enum: ['likelihood', 'impact'],
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The name of the new contributing risk entry.',
                        ),
                        new OA\Property(
                            property: 'contributing_risks_id',
                            type: 'integer',
                            description: 'Optional ID of the parent contributing risk.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Contributing risk entry added successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiAddContributingRisk {}

#[OA\Post(
    path: '/contributing_risks/update/likelihood',
    summary: 'Update the name of a contributing risk likelihood level.',
    operationId: 'updateContributingRiskLikelihood',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['value', 'name'],
                    properties: [
                        new OA\Property(
                            property: 'value',
                            type: 'integer',
                            description: 'The value of the likelihood level to update.',
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The new name for the likelihood level.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Contributing risk likelihood name updated successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiUpdateContributingRiskLikelihood {}

#[OA\Post(
    path: '/contributing_risks/update/impact',
    summary: 'Update the name of a contributing risk impact level.',
    operationId: 'updateContributingRiskImpact',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['id', 'name'],
                    properties: [
                        new OA\Property(
                            property: 'id',
                            type: 'integer',
                            description: 'The ID of the contributing risk impact level to update.',
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            description: 'The new name for the impact level.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Contributing risk impact name updated successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiUpdateContributingRiskImpact {}

#[OA\Post(
    path: '/contributing_risks/delete/likelihood',
    summary: 'Delete a contributing risk likelihood level.',
    operationId: 'deleteContributingRiskLikelihood',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['value'],
                    properties: [
                        new OA\Property(
                            property: 'value',
                            type: 'integer',
                            description: 'The value of the contributing risk likelihood level to delete.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Contributing risk likelihood level deleted successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiDeleteContributingRiskLikelihood {}

#[OA\Post(
    path: '/contributing_risks/delete/impact',
    summary: 'Delete a contributing risk impact level.',
    operationId: 'deleteContributingRiskImpact',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['id', 'value', 'contributing_risks_id'],
                    properties: [
                        new OA\Property(
                            property: 'id',
                            type: 'integer',
                            description: 'The ID of the contributing risk impact level to delete.',
                        ),
                        new OA\Property(
                            property: 'value',
                            type: 'integer',
                            description: 'The value of the contributing risk impact level to delete.',
                        ),
                        new OA\Property(
                            property: 'contributing_risks_id',
                            type: 'integer',
                            description: 'The ID of the parent contributing risk.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Contributing risk impact level deleted successfully.'),
        new OA\Response(response: 403, description: 'FORBIDDEN: Admin privileges are required to perform this action.'),
    ],
)]
class OpenApiDeleteContributingRiskImpact {}

#[OA\Post(
    path: '/contributing_risks/table_list',
    summary: 'Get an HTML table listing all contributing risk likelihood or impact levels.',
    operationId: 'contributingRisksTableList',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['table'],
                    properties: [
                        new OA\Property(
                            property: 'table',
                            type: 'string',
                            description: 'The table to list entries from.',
                            enum: ['likelihood', 'impact'],
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'HTML table content listing contributing risk levels.',
            content: [new OA\MediaType(mediaType: 'text/html', schema: new OA\Schema(type: 'string'))],
        ),
    ],
)]
class OpenApiContributingRisksTableList {}

#[OA\Get(
    path: '/riskformula/config',
    summary: 'Get the current risk-scoring configuration for Classic scoring (risk_model, normalization, level counts, and the model-6 custom value grid).',
    operationId: 'getRiskFormulaConfig',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Current risk-scoring configuration.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'risk_model',
                        type: 'integer',
                        description: 'The admin-configured risk formula. 1-5 are arithmetic formulas; 6 is a custom lookup grid.',
                        enum: [1, 2, 3, 4, 5, 6],
                    ),
                    new OA\Property(
                        property: 'need_risk_score_normalization',
                        type: 'boolean',
                        description: 'Whether the raw formula result is rescaled onto a 0-10 scale.',
                    ),
                    new OA\Property(
                        property: 'likelihood_count',
                        type: 'integer',
                        description: 'The number of configured likelihood levels.',
                    ),
                    new OA\Property(
                        property: 'impact_count',
                        type: 'integer',
                        description: 'The number of configured impact levels.',
                    ),
                    new OA\Property(
                        property: 'default_risk_score',
                        type: 'number',
                        format: 'float',
                        description: "calculate_risk()'s own out-of-range fallback score (the default_risk_score setting), used when the supplied likelihood or impact falls outside the configured 1-N range.",
                    ),
                    new OA\Property(
                        property: 'custom_risk_model_values',
                        type: 'array',
                        description: 'The model-6 custom score grid. Only populated when risk_model is 6; an empty array otherwise.',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'impact', type: 'integer'),
                                new OA\Property(property: 'likelihood', type: 'integer'),
                                new OA\Property(property: 'value', type: 'number', format: 'float'),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: submit_risks or riskmanagement permission is required.',
        ),
    ],
)]
class OpenApiGetRiskFormulaConfig {}

#[OA\Get(
    path: '/cve/lookup',
    summary: 'Look up CVE details from the NVD (National Vulnerability Database) by CVE ID.',
    operationId: 'cveLookup',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'cve_id',
            in: 'query',
            description: 'The CVE identifier to look up (e.g. CVE-2021-44228).',
            required: true,
            schema: new OA\Schema(type: 'string', pattern: '^CVE-\\d{4}-\\d{4,}$', example: 'CVE-2021-44228'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'CVE detail object from NVD.',
            content: new OA\JsonContent(type: 'object', additionalProperties: true),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: Invalid CVE ID format.'),
    ],
)]
class OpenApiCveLookup {}

#[OA\Get(
    path: '/mitigation_controls/get_mitigation_control_info',
    summary: 'Get details for a mitigation control.',
    operationId: 'getMitigationControlInfo',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'control_id',
            in: 'query',
            description: 'The ID of the mitigation control to retrieve.',
            required: true,
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Mitigation control info object.',
            content: new OA\JsonContent(type: 'object', additionalProperties: true),
        ),
    ],
)]
class OpenApiGetMitigationControlInfo {}

#[OA\Post(
    path: '/likelihood_impact_chart/tooltip',
    summary: 'Get the tooltip content for a cell in the likelihood/impact chart.',
    operationId: 'likelihoodImpactTooltip',
    tags: ['risk_formula'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['likelihood', 'impact'],
                    properties: [
                        new OA\Property(
                            property: 'likelihood',
                            type: 'integer',
                            description: 'The likelihood level value.',
                        ),
                        new OA\Property(property: 'impact', type: 'integer', description: 'The impact level value.'),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Tooltip content for the specified cell.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'score', type: 'number'),
                    new OA\Property(property: 'color', type: 'string'),
                ],
            ),
        ),
    ],
)]
class OpenApiLikelihoodImpactTooltip {}

?>
