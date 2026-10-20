<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/ui/layout',
    summary: 'Save a UI layout for the current user',
    operationId: 'saveUiLayout',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['layout_name'],
                    properties: [
                        new OA\Property(
                            property: 'layout_name',
                            type: 'string',
                            enum: ['risk_dashboard', 'dashboard_open', 'dashboard_close', 'compliance_dashboard'],
                            description: 'The name of the layout to save.',
                        ),
                        new OA\Property(
                            property: 'layout',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                            description: 'Array of widget placement objects. Each object should contain name, x, y, and optionally w, h, layout.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Layout saved successfully.'),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Missing required parameters or insufficient permission for the requested layout.',
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The user does not have the required permission to perform this action.',
        ),
    ],
)]
class OpenApiSaveUiLayout {}

#[OA\Get(
    path: '/ui/layout',
    summary: 'Get a UI layout for the current user',
    operationId: 'getUiLayout',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'layout_name',
            in: 'query',
            required: true,
            description: 'The name of the layout to retrieve.',
            schema: new OA\Schema(
                type: 'string',
                enum: ['risk_dashboard', 'dashboard_open', 'dashboard_close', 'compliance_dashboard'],
            ),
        ),
        new OA\Parameter(
            name: 'type',
            in: 'query',
            required: true,
            description: "Whether to retrieve the user's saved layout or the default layout.",
            schema: new OA\Schema(type: 'string', enum: ['saved', 'default']),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Layout retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(type: 'object'),
                        description: 'Array of widget placement objects.',
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Missing or invalid parameters, or insufficient permission for the requested layout.',
        ),
    ],
)]
class OpenApiGetUiLayout {}

#[OA\Get(
    path: '/ui/widget',
    summary: 'Get the rendered HTML for a UI widget',
    operationId: 'getUiWidget',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'layout_name',
            in: 'query',
            required: true,
            description: 'The name of the layout the widget belongs to.',
            schema: new OA\Schema(
                type: 'string',
                enum: ['risk_dashboard', 'dashboard_open', 'dashboard_close', 'compliance_dashboard'],
            ),
        ),
        new OA\Parameter(
            name: 'widget_name',
            in: 'query',
            required: true,
            description: "The widget to render. Available widgets depend on the chosen layout_name: 'risk_dashboard' supports chart_open_vs_closed, chart_mitigation_planned_vs_unplanned, chart_reviewed_vs_unreviewed, table_risks_by_month, WYSIWYG; 'dashboard_open' supports open_risk_level, open_status, open_site_location, open_risk_source, open_category, open_team, open_technology, open_owner, open_owners_manager, open_risk_scoring_method, WYSIWYG; 'dashboard_close' supports close_reason, WYSIWYG; 'compliance_dashboard' supports compliance_controls_by_framework_bar_chart, compliance_pass_fail_pie_chart.",
            schema: new OA\Schema(
                type: 'string',
                enum: [
                    'chart_open_vs_closed',
                    'chart_mitigation_planned_vs_unplanned',
                    'chart_reviewed_vs_unreviewed',
                    'table_risks_by_month',
                    'open_risk_level',
                    'open_status',
                    'open_site_location',
                    'open_risk_source',
                    'open_category',
                    'open_team',
                    'open_technology',
                    'open_owner',
                    'open_owners_manager',
                    'open_risk_scoring_method',
                    'close_reason',
                    'WYSIWYG',
                    'compliance_controls_by_framework_bar_chart',
                    'compliance_pass_fail_pie_chart',
                ],
            ),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Widget HTML rendered successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'string',
                        description: 'Rendered HTML content of the widget.',
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Invalid layout or widget name, or insufficient permission.',
        ),
    ],
)]
class OpenApiGetUiWidget {}

#[OA\Post(
    path: '/ui/default_layout',
    summary: 'Set or unset a saved layout as the default for the current user',
    operationId: 'updateUiDefaultLayout',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['layout_name', 'default'],
                    properties: [
                        new OA\Property(
                            property: 'layout_name',
                            type: 'string',
                            enum: ['risk_dashboard', 'dashboard_open', 'dashboard_close', 'compliance_dashboard'],
                            description: 'The name of the layout to update.',
                        ),
                        new OA\Property(
                            property: 'default',
                            type: 'boolean',
                            description: "Whether to set (true) or unset (false) the layout as the user's default.",
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Default layout status updated successfully.'),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Invalid parameters, insufficient permission, or attempting to set a non-custom layout as default.',
        ),
    ],
)]
class OpenApiUpdateUiDefaultLayout {}

#[OA\Post(
    path: '/ui/column_settings',
    summary: 'Save column display settings for a datatable view for the current user',
    operationId: 'saveUiColumnSettings',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['display_settings_view'],
                    properties: [
                        new OA\Property(
                            property: 'display_settings_view',
                            type: 'string',
                            description: "The view key identifying which datatable's column settings to save (e.g. 'asset_verified', 'active_audits', 'past_audits').",
                        ),
                        new OA\Property(
                            property: '...',
                            type: 'string',
                            description: 'One key per column to include in the view. Only keys matching valid field names for the given view are saved.',
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(response: 200, description: 'Column settings saved successfully.'),
        new OA\Response(response: 400, description: 'BAD REQUEST: Missing or invalid display_settings_view.'),
    ],
)]
class OpenApiSaveUiColumnSettings {}

#[OA\Get(
    path: '/reports/catalog',
    summary: "Return the Reports Hub catalog filtered to the current user's permissions",
    operationId: 'reportsCatalog',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Catalog entries visible to the authenticated user, each annotated with a favorited flag.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'reports',
                                type: 'array',
                                description: 'Catalog entries the user can access, in catalog order.',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'key',
                                            type: 'string',
                                            description: "Unique catalog key (e.g. 'dynamic_risk_report').",
                                        ),
                                        new OA\Property(
                                            property: 'label',
                                            type: 'string',
                                            description: 'Translated display label for the report.',
                                        ),
                                        new OA\Property(
                                            property: 'description',
                                            type: 'string',
                                            description: 'Translated description sentence for the report.',
                                        ),
                                        new OA\Property(
                                            property: 'path',
                                            type: 'string',
                                            description: "URL path relative to the simplerisk root (e.g. 'reports/dynamic_risk_report.php').",
                                        ),
                                        new OA\Property(
                                            property: 'kind',
                                            type: 'string',
                                            enum: ['report', 'dashboard'],
                                            description: 'Whether this entry is a report or a dashboard.',
                                        ),
                                        new OA\Property(
                                            property: 'tags',
                                            type: 'array',
                                            items: new OA\Items(type: 'string'),
                                            description: "Domain tags such as 'riskmanagement', 'compliance', 'governance', 'asset'.",
                                        ),
                                        new OA\Property(
                                            property: 'favorited',
                                            type: 'boolean',
                                            description: 'True when the authenticated user has starred this report.',
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
            response: 401,
            description: 'UNAUTHORIZED: request is not authenticated or session has no user.',
        ),
    ],
)]
class OpenApiReportsCatalog {}

#[OA\Get(
    path: '/reports/favorites',
    summary: "Return the authenticated user's favorited report keys",
    operationId: 'reportsFavoritesList',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Alphabetically sorted list of report_keys the user has favorited.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'favorites',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                description: 'Alphabetically sorted list of report_keys the user has favorited (may be empty).',
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 401,
            description: 'UNAUTHORIZED: request is not authenticated or session has no user.',
        ),
    ],
)]
class OpenApiReportsFavoritesList {}

#[OA\Post(
    path: '/reports/favorites',
    summary: "Add a report to the current user's favorites",
    operationId: 'reportsFavoritesAdd',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['report_key'],
                    properties: [
                        new OA\Property(
                            property: 'report_key',
                            type: 'string',
                            maxLength: 64,
                            description: "The catalog key of the report to favorite (e.g. 'dynamic_risk_report'). Must match a key in the reports catalog.",
                        ),
                    ],
                ),
            ),
        ],
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Favorite recorded (or already existed). Returns the updated favorites list.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'favorites',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                description: 'Alphabetically sorted list of report_keys the user has favorited.',
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: report_key is missing, exceeds 64 chars, or is not a known catalog key.',
        ),
        new OA\Response(
            response: 401,
            description: 'UNAUTHORIZED: request is not authenticated or session has no user.',
        ),
        new OA\Response(response: 403, description: 'User does not have access to this report'),
    ],
)]
class OpenApiReportsFavoritesAdd {}

#[OA\Delete(
    path: '/reports/favorites/{report_key}',
    summary: "Remove a report from the current user's favorites",
    operationId: 'reportsFavoritesDelete',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'report_key',
            in: 'path',
            required: true,
            description: 'The catalog key of the report to un-favorite. Must be a known catalog key the user currently has access to. Idempotent — deleting a key that was never favorited is a safe no-op.',
            schema: new OA\Schema(type: 'string', maxLength: 64),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Favorite removed (or was not present). Returns the updated favorites list.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'favorites',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                description: 'Alphabetically sorted list of report_keys the user has favorited.',
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: report_key path parameter is empty, exceeds 64 chars, or is not a known catalog key.',
        ),
        new OA\Response(
            response: 401,
            description: 'UNAUTHORIZED: request is not authenticated or session has no user.',
        ),
        new OA\Response(response: 403, description: 'User does not have access to this report'),
    ],
)]
class OpenApiReportsFavoritesDelete {}

#[OA\Put(
    path: '/ui/getting_started/dismissals/{step_key}',
    summary: 'Dismiss a Getting Started step for the current user',
    operationId: 'gettingStartedDismiss',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'step_key',
            in: 'path',
            required: true,
            description: 'The Getting Started catalog step key to dismiss (e.g. submit_risks). Must be a known catalog key. Idempotent — dismissing an already-dismissed step is a safe no-op. Scoped to the session user.',
            schema: new OA\Schema(type: 'string', maxLength: 50),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Step dismissed for the current user (or was already dismissed).'),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: step_key is empty or not a known Getting Started catalog key.',
        ),
        new OA\Response(
            response: 401,
            description: 'UNAUTHORIZED: request is not authenticated or session has no user.',
        ),
    ],
)]
class OpenApiGettingStartedDismiss {}

#[OA\Delete(
    path: '/ui/getting_started/dismissals/{step_key}',
    summary: 'Restore (un-dismiss) a Getting Started step for the current user',
    operationId: 'gettingStartedRestore',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'step_key',
            in: 'path',
            required: true,
            description: 'The Getting Started catalog step key to restore. Must be a known catalog key. Idempotent — restoring a step that was never dismissed is a safe no-op. Scoped to the session user.',
            schema: new OA\Schema(type: 'string', maxLength: 50),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Step restored (or was not dismissed).'),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: step_key is empty or not a known Getting Started catalog key.',
        ),
        new OA\Response(
            response: 401,
            description: 'UNAUTHORIZED: request is not authenticated or session has no user.',
        ),
    ],
)]
class OpenApiGettingStartedRestore {}

#[OA\Get(
    path: '/ui/getting_started/dismissals',
    summary: "List the current user's dismissed Getting Started step keys",
    operationId: 'gettingStartedDismissals',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'The step keys the current user has dismissed.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Getting Started catalog step keys the user has dismissed.',
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 401,
            description: 'UNAUTHORIZED: request is not authenticated or session has no user.',
        ),
    ],
)]
class OpenApiGettingStartedDismissals {}

#[OA\Get(
    path: '/ui/risk/template_groups',
    summary: 'Get the risk template groups the current user may submit a risk under',
    operationId: 'getUiRiskTemplateGroups',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Template groups retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'is_default', type: 'integer', enum: [0, 1]),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The caller does not have the Submit Risks permission.'),
    ],
)]
class OpenApiGetUiRiskTemplateGroups {}

#[OA\Get(
    path: '/ui/risk/fields',
    summary: "Get the active risk field roster for a template group's Cards tab",
    operationId: 'getUiRiskFields',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'template_group_id',
            in: 'query',
            required: false,
            description: "The risk template group to resolve the field roster for. Falls back to the caller's own default group when omitted or when the caller isn't a member of the requested group.",
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            name: 'tab_index',
            in: 'query',
            required: false,
            description: "Which Cards tab's field roster to return: 1 = Details (default), 2 = Mitigation.",
            schema: new OA\Schema(type: 'integer', enum: [1, 2]),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Field roster retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(type: 'object'),
                        description: 'Active fields for the resolved template group and tab, each with its name, label, and option data.',
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The caller does not have the Submit Risks or Risk Management permission.',
        ),
    ],
)]
class OpenApiGetUiRiskFields {}

#[OA\Get(
    path: '/ui/risk/layout',
    summary: "Get the Cards layout (card/field positions) for a template group's tab",
    operationId: 'getUiRiskLayout',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'template_group_id',
            in: 'query',
            required: false,
            description: "The risk template group to resolve the layout for. Falls back to the caller's own default group when omitted or when the caller isn't a member of the requested group.",
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            name: 'tab_index',
            in: 'query',
            required: false,
            description: "Which Cards tab's layout to return: 1 = Details (default), 2 = Mitigation, 3 = Review.",
            schema: new OA\Schema(type: 'integer', enum: [1, 2, 3]),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Layout retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(type: 'object'),
                        description: 'Card placement objects (card_key, position, size) for the resolved template group and tab.',
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The caller does not have the Submit Risks or Risk Management permission.',
        ),
    ],
)]
class OpenApiGetUiRiskLayout {}

#[OA\Get(
    path: '/ui/risk/{id}/values',
    summary: "Get a risk's Details-tab field values for the Cards view",
    operationId: 'getUiRiskValues',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the risk.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Risk values retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'template_group_id',
                        type: 'integer',
                        description: "The risk's own template group -- the field/layout roster this response's values were resolved against.",
                    ),
                    new OA\Property(
                        property: 'values',
                        type: 'object',
                        description: 'Map of field name to {raw, display} value, covering both core and custom fields.',
                    ),
                    new OA\Property(
                        property: 'supporting_documentation_html',
                        type: 'string',
                        description: 'Server-rendered supporting-documentation file list.',
                    ),
                    new OA\Property(
                        property: 'risk_summary',
                        type: 'object',
                        description: 'Inherent and residual calculated risk score, level name, and color.',
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Missing ID or insufficient Risk Management permission.',
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The caller does not have access to this risk.'),
        new OA\Response(response: 404, description: 'NOT FOUND: Risk ID not found.'),
    ],
)]
class OpenApiGetUiRiskValues {}

#[OA\Get(
    path: '/ui/risk/{id}/mitigation-values',
    summary: "Get a risk's Mitigation-tab field values for the Cards view",
    operationId: 'getUiRiskMitigationValues',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the risk.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Mitigation values retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'values',
                        type: 'object',
                        description: "Map of field name to {raw, display} value for the risk's mitigation record.",
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Missing ID or insufficient Risk Management permission.',
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The caller does not have access to this risk.'),
        new OA\Response(response: 404, description: 'NOT FOUND: Risk ID not found.'),
    ],
)]
class OpenApiGetUiRiskMitigationValues {}

#[OA\Get(
    path: '/ui/risk/{id}/review-values',
    summary: "Get a risk's Review-tab field values for the Cards view",
    operationId: 'getUiRiskReviewValues',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the risk.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Review values retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'values',
                        type: 'object',
                        description: "Map of field name to {raw, display} value for the risk's current/most recent management review.",
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Missing ID or insufficient Risk Management permission.',
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The caller does not have access to this risk.'),
        new OA\Response(response: 404, description: 'NOT FOUND: Risk ID not found.'),
    ],
)]
class OpenApiGetUiRiskReviewValues {}

#[OA\Get(
    path: '/ui/risk/{id}/review-history',
    summary: "Get a risk's full management review history for the Cards view",
    operationId: 'getUiRiskReviewHistory',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the risk.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Review history retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'reviews',
                                type: 'array',
                                items: new OA\Items(type: 'object'),
                                description: 'One entry per past review (id plus resolved field values), newest first. Empty when the risk has no reviews yet.',
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 400,
            description: 'BAD REQUEST: Missing ID or insufficient Risk Management permission.',
        ),
        new OA\Response(response: 403, description: 'FORBIDDEN: The caller does not have access to this risk.'),
    ],
)]
class OpenApiGetUiRiskReviewHistory {}

#[OA\Get(
    path: '/ui/asset/template_groups',
    summary: 'Get the asset template groups the current user may create an asset under',
    operationId: 'getUiAssetTemplateGroups',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Template groups retrieved successfully. Without the Customization Extra a single default group is returned.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'is_default', type: 'integer', enum: [0, 1]),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The caller does not have the Asset Management permission.',
        ),
    ],
)]
class OpenApiGetUiAssetTemplateGroups {}

#[OA\Get(
    path: '/ui/asset/fields',
    summary: "Get the asset field roster for the asset record's Cards layout",
    description: "Every field carries a card_key (general, assignment, classification, scoring, additional_info or custom_fields) and a position in its card. Fields the stored template has not placed are placed in the response only; nothing is saved. Without the Customization Extra the core asset fields are returned with their default placement. AssetName is always present and required. Select-shaped fields carry `options` ({value, name}); AssetValuation also carries `default_value`, MappedControls carries `maturity_options` and `can_select_controls` (true only with the Governance permission, which the control picker needs; without it the client shows the mapping read-only and does not send it), AssociatedRisks carries `can_select_risks` (true only with the Risk Management permission) and `options` listing only the risks the caller may see; without the permission `options` is empty and the client shows the count read-only and does not send associated_risks, and, for AssetScoring (the Scoring card's composite; never required), `scoring` {weights {confidentiality, integrity, availability}, values {low, moderate, high}, thresholds {moderate, high} (integers in hundredths: 1.67 is 167), defaults {confidentiality, integrity, availability} (level codes or null; they pre-fill the Add form only)}. AssetScoring is left out until the database upgrade has added the scoring columns.",
    operationId: 'getUiAssetFields',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'template_group_id',
            in: 'query',
            required: false,
            description: "The asset template group to resolve the roster for. Falls back to the caller's default asset group when omitted or when the caller may not use the requested group. Ignored when asset_id is sent.",
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            name: 'asset_id',
            in: 'query',
            required: false,
            description: "Resolve the roster for this asset's own template group (use it when viewing or editing an existing asset). Gated like /ui/asset/{id}/values: an asset the caller cannot access returns the same 404 as one that does not exist.",
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Field roster retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(type: 'object'),
                        description: 'Fields with id, name, type, is_basic, required, removable, card_key, pos_x, pos_y, pos_w, pos_h and, where applicable, options (and scoring for AssetScoring).',
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The caller does not have the Asset Management permission.',
        ),
        new OA\Response(
            response: 404,
            description: 'NOT FOUND: asset_id was sent and that asset does not exist or the caller may not access it (one response for both).',
        ),
    ],
)]
class OpenApiGetUiAssetFields {}

#[OA\Get(
    path: '/ui/asset/layout',
    summary: "Get the card tiles of the asset record's Cards layout",
    description: 'One tile per asset card with its position and the number of fields it holds. A card with no stored tile gets one below the lowest stored tile in the response only; nothing is saved.',
    operationId: 'getUiAssetLayout',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'template_group_id',
            in: 'query',
            required: false,
            description: "The asset template group to resolve the layout for. Falls back to the caller's default asset group when omitted or when the caller may not use the requested group. Ignored when asset_id is sent.",
            schema: new OA\Schema(type: 'integer'),
        ),
        new OA\Parameter(
            name: 'asset_id',
            in: 'query',
            required: false,
            description: "Resolve the layout for this asset's own template group. Gated like /ui/asset/{id}/values: an asset the caller cannot access returns the same 404 as one that does not exist.",
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Layout retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'card_key',
                                    type: 'string',
                                    enum: [
                                        'general',
                                        'assignment',
                                        'classification',
                                        'scoring',
                                        'additional_info',
                                        'custom_fields',
                                    ],
                                ),
                                new OA\Property(property: 'pos_x', type: 'integer'),
                                new OA\Property(property: 'pos_y', type: 'integer'),
                                new OA\Property(property: 'pos_w', type: 'integer'),
                                new OA\Property(property: 'pos_h', type: 'integer'),
                                new OA\Property(property: 'field_count', type: 'integer'),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The caller does not have the Asset Management permission.',
        ),
        new OA\Response(
            response: 404,
            description: 'NOT FOUND: asset_id was sent and that asset does not exist or the caller may not access it (one response for both).',
        ),
    ],
)]
class OpenApiGetUiAssetLayout {}

#[OA\Get(
    path: '/ui/asset/{id}/values',
    summary: "Get one asset's field values for the asset record",
    description: "Values are keyed by the names PATCH /assets/{id} accepts (name, ip, value, location, team, details, tags, mapped_controls, associated_risks, custom_field_<id>) plus verified and created (raw Y-m-d H:i:s, display in the configured date format); each is {raw, display}. location, team and associated_risks also carry names, a list of {id, name} objects; tags carries names as plain strings; mapped_controls carries items ({control_maturity, maturity_name, controls: [{id, name}]}). `scoring` (present once the database upgrade has added the scoring columns) is {raw: {confidentiality, integrity, availability} (low, moderate or high, not_applicable for confidentiality only, or null when not set), display: '', result: {scored, categorization, score, band}}: result is computed from the current Preferences; scored is false and the other three are null unless all three are answered; score is a string with two decimals. Every display and name is RAW, unescaped text: insert it with text or attribute setters only. details.display is the stored rich text as-is; details.display_html is the purified HTML to render. associated_risks also carries count (how many associated risks the caller may see). Associated risks are limited to the risks the caller may see; without the Risk Management permission raw, display and names are empty and only count is set. Control names need the Governance permission. An asset the caller cannot access returns exactly the same 404 response as an asset that does not exist.",
    operationId: 'getUiAssetValues',
    tags: ['ui'],
    security: [['ApiKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'The ID of the asset.',
            schema: new OA\Schema(type: 'integer'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Asset values retrieved successfully.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'template_group_id',
                                type: 'integer',
                                description: "The asset's own template group: request fields and layout for this group.",
                            ),
                            new OA\Property(
                                property: 'values',
                                type: 'object',
                                description: 'Field name to {raw, display}.',
                            ),
                            new OA\Property(
                                property: 'can_edit',
                                type: 'boolean',
                                description: 'The caller holds asset_edit.',
                            ),
                            new OA\Property(
                                property: 'can_verify',
                                type: 'boolean',
                                description: 'The caller holds asset_verify.',
                            ),
                            new OA\Property(
                                property: 'can_delete',
                                type: 'boolean',
                                description: 'The caller holds asset_delete.',
                            ),
                        ],
                    ),
                ],
            ),
        ),
        new OA\Response(response: 400, description: 'BAD REQUEST: Missing or invalid ID.'),
        new OA\Response(
            response: 403,
            description: 'FORBIDDEN: The caller does not have the Asset Management permission.',
        ),
        new OA\Response(
            response: 404,
            description: 'NOT FOUND: The asset does not exist or the caller may not access it (one response for both).',
        ),
    ],
)]
class OpenApiGetUiAssetValues {}

?>
