<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// @phan-suppress-next-line PhanUnreferencedUseNormal -- OA alias used in PHPDoc @OA annotations
use OpenApi\Annotations as OA;

/**
 * @OA\Post(
 *     path="/ui/layout",
 *     summary="Save a UI layout for the current user",
 *     operationId="saveUiLayout",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"layout_name"},
 *                 @OA\Property(property="layout_name", type="string", enum={"risk_dashboard", "dashboard_open", "dashboard_close", "compliance_dashboard"}, description="The name of the layout to save."),
 *                 @OA\Property(
 *                     property="layout",
 *                     type="array",
 *                     @OA\Items(type="object"),
 *                     description="Array of widget placement objects. Each object should contain name, x, y, and optionally w, h, layout."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Layout saved successfully.",
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: Missing required parameters or insufficient permission for the requested layout.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiSaveUiLayout {}

/**
 * @OA\Get(
 *     path="/ui/layout",
 *     summary="Get a UI layout for the current user",
 *     operationId="getUiLayout",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="layout_name",
 *         in="query",
 *         required=true,
 *         description="The name of the layout to retrieve.",
 *         @OA\Schema(type="string", enum={"risk_dashboard", "dashboard_open", "dashboard_close", "compliance_dashboard"})
 *     ),
 *     @OA\Parameter(
 *         name="type",
 *         in="query",
 *         required=true,
 *         description="Whether to retrieve the user's saved layout or the default layout.",
 *         @OA\Schema(type="string", enum={"saved", "default"})
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Layout retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"), description="Array of widget placement objects.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: Missing or invalid parameters, or insufficient permission for the requested layout.",
 *     ),
 * )
 */
class OpenApiGetUiLayout {}

/**
 * @OA\Get(
 *     path="/ui/widget",
 *     summary="Get the rendered HTML for a UI widget",
 *     operationId="getUiWidget",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="layout_name",
 *         in="query",
 *         required=true,
 *         description="The name of the layout the widget belongs to.",
 *         @OA\Schema(type="string", enum={"risk_dashboard", "dashboard_open", "dashboard_close", "compliance_dashboard"})
 *     ),
 *     @OA\Parameter(
 *         name="widget_name",
 *         in="query",
 *         required=true,
 *         description="The widget to render. Available widgets depend on the chosen layout_name: 'risk_dashboard' supports chart_open_vs_closed, chart_mitigation_planned_vs_unplanned, chart_reviewed_vs_unreviewed, table_risks_by_month, WYSIWYG; 'dashboard_open' supports open_risk_level, open_status, open_site_location, open_risk_source, open_category, open_team, open_technology, open_owner, open_owners_manager, open_risk_scoring_method, WYSIWYG; 'dashboard_close' supports close_reason, WYSIWYG; 'compliance_dashboard' supports compliance_controls_by_framework_bar_chart, compliance_pass_fail_pie_chart.",
 *         @OA\Schema(type="string", enum={"chart_open_vs_closed", "chart_mitigation_planned_vs_unplanned", "chart_reviewed_vs_unreviewed", "table_risks_by_month", "open_risk_level", "open_status", "open_site_location", "open_risk_source", "open_category", "open_team", "open_technology", "open_owner", "open_owners_manager", "open_risk_scoring_method", "close_reason", "WYSIWYG", "compliance_controls_by_framework_bar_chart", "compliance_pass_fail_pie_chart"})
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Widget HTML rendered successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="string", description="Rendered HTML content of the widget.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: Invalid layout or widget name, or insufficient permission.",
 *     ),
 * )
 */
class OpenApiGetUiWidget {}

/**
 * @OA\Post(
 *     path="/ui/default_layout",
 *     summary="Set or unset a saved layout as the default for the current user",
 *     operationId="updateUiDefaultLayout",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"layout_name", "default"},
 *                 @OA\Property(property="layout_name", type="string", enum={"risk_dashboard", "dashboard_open", "dashboard_close", "compliance_dashboard"}, description="The name of the layout to update."),
 *                 @OA\Property(property="default", type="boolean", description="Whether to set (true) or unset (false) the layout as the user's default.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Default layout status updated successfully.",
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: Invalid parameters, insufficient permission, or attempting to set a non-custom layout as default.",
 *     ),
 * )
 */
class OpenApiUpdateUiDefaultLayout {}

/**
 * @OA\Post(
 *     path="/ui/column_settings",
 *     summary="Save column display settings for a datatable view for the current user",
 *     operationId="saveUiColumnSettings",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"display_settings_view"},
 *                 @OA\Property(property="display_settings_view", type="string", description="The view key identifying which datatable's column settings to save (e.g. 'asset_verified', 'active_audits', 'past_audits')."),
 *                 @OA\Property(property="...", type="string", description="One key per column to include in the view. Only keys matching valid field names for the given view are saved.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Column settings saved successfully.",
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: Missing or invalid display_settings_view.",
 *     ),
 * )
 */
class OpenApiSaveUiColumnSettings {}

/**
 * @OA\Get(
 *     path="/reports/catalog",
 *     summary="Return the Reports Hub catalog filtered to the current user's permissions",
 *     operationId="reportsCatalog",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Catalog entries visible to the authenticated user, each annotated with a favorited flag.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="reports",
 *                     type="array",
 *                     description="Catalog entries the user can access, in catalog order.",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="key",         type="string",  description="Unique catalog key (e.g. 'dynamic_risk_report')."),
 *                         @OA\Property(property="label",       type="string",  description="Translated display label for the report."),
 *                         @OA\Property(property="description", type="string",  description="Translated description sentence for the report."),
 *                         @OA\Property(property="path",        type="string",  description="URL path relative to the simplerisk root (e.g. 'reports/dynamic_risk_report.php')."),
 *                         @OA\Property(property="kind",        type="string",  enum={"report", "dashboard"}, description="Whether this entry is a report or a dashboard."),
 *                         @OA\Property(property="tags",        type="array",   @OA\Items(type="string"), description="Domain tags such as 'riskmanagement', 'compliance', 'governance', 'asset'."),
 *                         @OA\Property(property="favorited",   type="boolean", description="True when the authenticated user has starred this report.")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="UNAUTHORIZED: request is not authenticated or session has no user.",
 *     ),
 * )
 */
class OpenApiReportsCatalog {}

/**
 * @OA\Get(
 *     path="/reports/favorites",
 *     summary="Return the authenticated user's favorited report keys",
 *     operationId="reportsFavoritesList",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Alphabetically sorted list of report_keys the user has favorited.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="favorites",
 *                     type="array",
 *                     @OA\Items(type="string"),
 *                     description="Alphabetically sorted list of report_keys the user has favorited (may be empty)."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="UNAUTHORIZED: request is not authenticated or session has no user.",
 *     ),
 * )
 */
class OpenApiReportsFavoritesList {}

/**
 * @OA\Post(
 *     path="/reports/favorites",
 *     summary="Add a report to the current user's favorites",
 *     operationId="reportsFavoritesAdd",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             @OA\Schema(
 *                 required={"report_key"},
 *                 @OA\Property(
 *                     property="report_key",
 *                     type="string",
 *                     maxLength=64,
 *                     description="The catalog key of the report to favorite (e.g. 'dynamic_risk_report'). Must match a key in the reports catalog."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Favorite recorded (or already existed). Returns the updated favorites list.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="favorites",
 *                     type="array",
 *                     @OA\Items(type="string"),
 *                     description="Alphabetically sorted list of report_keys the user has favorited."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: report_key is missing, exceeds 64 chars, or is not a known catalog key.",
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="UNAUTHORIZED: request is not authenticated or session has no user.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="User does not have access to this report",
 *     ),
 * )
 */
class OpenApiReportsFavoritesAdd {}

/**
 * @OA\Delete(
 *     path="/reports/favorites/{report_key}",
 *     summary="Remove a report from the current user's favorites",
 *     operationId="reportsFavoritesDelete",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="report_key",
 *         in="path",
 *         required=true,
 *         description="The catalog key of the report to un-favorite. Must be a known catalog key the user currently has access to. Idempotent — deleting a key that was never favorited is a safe no-op.",
 *         @OA\Schema(type="string", maxLength=64)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Favorite removed (or was not present). Returns the updated favorites list.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="favorites",
 *                     type="array",
 *                     @OA\Items(type="string"),
 *                     description="Alphabetically sorted list of report_keys the user has favorited."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: report_key path parameter is empty, exceeds 64 chars, or is not a known catalog key.",
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="UNAUTHORIZED: request is not authenticated or session has no user.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="User does not have access to this report",
 *     ),
 * )
 */
class OpenApiReportsFavoritesDelete {}

/**
 * @OA\Put(
 *     path="/ui/getting_started/dismissals/{step_key}",
 *     summary="Dismiss a Getting Started step for the current user",
 *     operationId="gettingStartedDismiss",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="step_key",
 *         in="path",
 *         required=true,
 *         description="The Getting Started catalog step key to dismiss (e.g. submit_risks). Must be a known catalog key. Idempotent — dismissing an already-dismissed step is a safe no-op. Scoped to the session user.",
 *         @OA\Schema(type="string", maxLength=50)
 *     ),
 *     @OA\Response(response=200, description="Step dismissed for the current user (or was already dismissed)."),
 *     @OA\Response(response=400, description="BAD REQUEST: step_key is empty or not a known Getting Started catalog key."),
 *     @OA\Response(response=401, description="UNAUTHORIZED: request is not authenticated or session has no user."),
 * )
 */
class OpenApiGettingStartedDismiss {}

/**
 * @OA\Delete(
 *     path="/ui/getting_started/dismissals/{step_key}",
 *     summary="Restore (un-dismiss) a Getting Started step for the current user",
 *     operationId="gettingStartedRestore",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="step_key",
 *         in="path",
 *         required=true,
 *         description="The Getting Started catalog step key to restore. Must be a known catalog key. Idempotent — restoring a step that was never dismissed is a safe no-op. Scoped to the session user.",
 *         @OA\Schema(type="string", maxLength=50)
 *     ),
 *     @OA\Response(response=200, description="Step restored (or was not dismissed)."),
 *     @OA\Response(response=400, description="BAD REQUEST: step_key is empty or not a known Getting Started catalog key."),
 *     @OA\Response(response=401, description="UNAUTHORIZED: request is not authenticated or session has no user."),
 * )
 */
class OpenApiGettingStartedRestore {}

/**
 * @OA\Get(
 *     path="/ui/getting_started/dismissals",
 *     summary="List the current user's dismissed Getting Started step keys",
 *     operationId="gettingStartedDismissals",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="The step keys the current user has dismissed.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(type="string"),
 *                 description="Getting Started catalog step keys the user has dismissed."
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="UNAUTHORIZED: request is not authenticated or session has no user."),
 * )
 */
class OpenApiGettingStartedDismissals {}

/**
 * @OA\Get(
 *     path="/ui/risk/template_groups",
 *     summary="Get the risk template groups the current user may submit a risk under",
 *     operationId="getUiRiskTemplateGroups",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Template groups retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string"),
 *                     @OA\Property(property="is_default", type="integer", enum={0, 1})
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have the Submit Risks permission."),
 * )
 */
class OpenApiGetUiRiskTemplateGroups {}

/**
 * @OA\Get(
 *     path="/ui/risk/fields",
 *     summary="Get the active risk field roster for a template group's Cards tab",
 *     operationId="getUiRiskFields",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="template_group_id",
 *         in="query",
 *         required=false,
 *         description="The risk template group to resolve the field roster for. Falls back to the caller's own default group when omitted or when the caller isn't a member of the requested group.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="tab_index",
 *         in="query",
 *         required=false,
 *         description="Which Cards tab's field roster to return: 1 = Details (default), 2 = Mitigation.",
 *         @OA\Schema(type="integer", enum={1, 2})
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Field roster retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"), description="Active fields for the resolved template group and tab, each with its name, label, and option data.")
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have the Submit Risks or Risk Management permission."),
 * )
 */
class OpenApiGetUiRiskFields {}

/**
 * @OA\Get(
 *     path="/ui/risk/layout",
 *     summary="Get the Cards layout (card/field positions) for a template group's tab",
 *     operationId="getUiRiskLayout",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="template_group_id",
 *         in="query",
 *         required=false,
 *         description="The risk template group to resolve the layout for. Falls back to the caller's own default group when omitted or when the caller isn't a member of the requested group.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="tab_index",
 *         in="query",
 *         required=false,
 *         description="Which Cards tab's layout to return: 1 = Details (default), 2 = Mitigation, 3 = Review.",
 *         @OA\Schema(type="integer", enum={1, 2, 3})
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Layout retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"), description="Card placement objects (card_key, position, size) for the resolved template group and tab.")
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have the Submit Risks or Risk Management permission."),
 * )
 */
class OpenApiGetUiRiskLayout {}

/**
 * @OA\Get(
 *     path="/ui/risk/{id}/values",
 *     summary="Get a risk's Details-tab field values for the Cards view",
 *     operationId="getUiRiskValues",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Risk values retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="template_group_id", type="integer", description="The risk's own template group -- the field/layout roster this response's values were resolved against."),
 *             @OA\Property(property="values", type="object", description="Map of field name to {raw, display} value, covering both core and custom fields."),
 *             @OA\Property(property="supporting_documentation_html", type="string", description="Server-rendered supporting-documentation file list."),
 *             @OA\Property(property="risk_summary", type="object", description="Inherent and residual calculated risk score, level name, and color.")
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID or insufficient Risk Management permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
 *     @OA\Response(response=404, description="NOT FOUND: Risk ID not found."),
 * )
 */
class OpenApiGetUiRiskValues {}

/**
 * @OA\Get(
 *     path="/ui/risk/{id}/mitigation-values",
 *     summary="Get a risk's Mitigation-tab field values for the Cards view",
 *     operationId="getUiRiskMitigationValues",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Mitigation values retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="values", type="object", description="Map of field name to {raw, display} value for the risk's mitigation record.")
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID or insufficient Risk Management permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
 *     @OA\Response(response=404, description="NOT FOUND: Risk ID not found."),
 * )
 */
class OpenApiGetUiRiskMitigationValues {}

/**
 * @OA\Get(
 *     path="/ui/risk/{id}/review-values",
 *     summary="Get a risk's Review-tab field values for the Cards view",
 *     operationId="getUiRiskReviewValues",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Review values retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="values", type="object", description="Map of field name to {raw, display} value for the risk's current/most recent management review.")
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID or insufficient Risk Management permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
 *     @OA\Response(response=404, description="NOT FOUND: Risk ID not found."),
 * )
 */
class OpenApiGetUiRiskReviewValues {}

/**
 * @OA\Get(
 *     path="/ui/risk/{id}/review-history",
 *     summary="Get a risk's full management review history for the Cards view",
 *     operationId="getUiRiskReviewHistory",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the risk.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Review history retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="reviews",
 *                     type="array",
 *                     @OA\Items(type="object"),
 *                     description="One entry per past review (id plus resolved field values), newest first. Empty when the risk has no reviews yet."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID or insufficient Risk Management permission."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have access to this risk."),
 * )
 */
class OpenApiGetUiRiskReviewHistory {}

/**
 * @OA\Get(
 *     path="/ui/asset/template_groups",
 *     summary="Get the asset template groups the current user may create an asset under",
 *     operationId="getUiAssetTemplateGroups",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Template groups retrieved successfully. Without the Customization Extra a single default group is returned.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string"),
 *                     @OA\Property(property="is_default", type="integer", enum={0, 1})
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have the Asset Management permission."),
 * )
 */
class OpenApiGetUiAssetTemplateGroups {}

/**
 * @OA\Get(
 *     path="/ui/asset/fields",
 *     summary="Get the asset field roster for the asset record's Cards layout",
 *     description="Every field carries a card_key (general, assignment, classification, scoring, additional_info or custom_fields) and a position in its card. Fields the stored template has not placed are placed in the response only; nothing is saved. Without the Customization Extra the core asset fields are returned with their default placement. AssetName is always present and required. Select-shaped fields carry `options` ({value, name}); AssetValuation also carries `default_value`, MappedControls carries `maturity_options` and `can_select_controls` (true only with the Governance permission, which the control picker needs; without it the client shows the mapping read-only and does not send it), AssociatedRisks carries `can_select_risks` (true only with the Risk Management permission) and `options` listing only the risks the caller may see; without the permission `options` is empty and the client shows the count read-only and does not send associated_risks, and, for AssetScoring (the Scoring card's composite; never required), `scoring` {weights {confidentiality, integrity, availability}, values {low, moderate, high}, thresholds {moderate, high} (integers in hundredths: 1.67 is 167), defaults {confidentiality, integrity, availability} (level codes or null; they pre-fill the Add form only)}. AssetScoring is left out until the database upgrade has added the scoring columns.",
 *     operationId="getUiAssetFields",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="template_group_id",
 *         in="query",
 *         required=false,
 *         description="The asset template group to resolve the roster for. Falls back to the caller's default asset group when omitted or when the caller may not use the requested group. Ignored when asset_id is sent.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="asset_id",
 *         in="query",
 *         required=false,
 *         description="Resolve the roster for this asset's own template group (use it when viewing or editing an existing asset). Gated like /ui/asset/{id}/values: an asset the caller cannot access returns the same 404 as one that does not exist.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Field roster retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"), description="Fields with id, name, type, is_basic, required, removable, card_key, pos_x, pos_y, pos_w, pos_h and, where applicable, options (and scoring for AssetScoring).")
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have the Asset Management permission."),
 *     @OA\Response(response=404, description="NOT FOUND: asset_id was sent and that asset does not exist or the caller may not access it (one response for both)."),
 * )
 */
class OpenApiGetUiAssetFields {}

/**
 * @OA\Get(
 *     path="/ui/asset/layout",
 *     summary="Get the card tiles of the asset record's Cards layout",
 *     description="One tile per asset card with its position and the number of fields it holds. A card with no stored tile gets one below the lowest stored tile in the response only; nothing is saved.",
 *     operationId="getUiAssetLayout",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="template_group_id",
 *         in="query",
 *         required=false,
 *         description="The asset template group to resolve the layout for. Falls back to the caller's default asset group when omitted or when the caller may not use the requested group. Ignored when asset_id is sent.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="asset_id",
 *         in="query",
 *         required=false,
 *         description="Resolve the layout for this asset's own template group. Gated like /ui/asset/{id}/values: an asset the caller cannot access returns the same 404 as one that does not exist.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Layout retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="card_key", type="string", enum={"general", "assignment", "classification", "scoring", "additional_info", "custom_fields"}),
 *                 @OA\Property(property="pos_x", type="integer"),
 *                 @OA\Property(property="pos_y", type="integer"),
 *                 @OA\Property(property="pos_w", type="integer"),
 *                 @OA\Property(property="pos_h", type="integer"),
 *                 @OA\Property(property="field_count", type="integer")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have the Asset Management permission."),
 *     @OA\Response(response=404, description="NOT FOUND: asset_id was sent and that asset does not exist or the caller may not access it (one response for both)."),
 * )
 */
class OpenApiGetUiAssetLayout {}

/**
 * @OA\Get(
 *     path="/ui/asset/{id}/values",
 *     summary="Get one asset's field values for the asset record",
 *     description="Values are keyed by the names PATCH /assets/{id} accepts (name, ip, value, location, team, details, tags, mapped_controls, associated_risks, custom_field_<id>) plus verified and created (raw Y-m-d H:i:s, display in the configured date format); each is {raw, display}. location, team and associated_risks also carry names, a list of {id, name} objects; tags carries names as plain strings; mapped_controls carries items ({control_maturity, maturity_name, controls: [{id, name}]}). `scoring` (present once the database upgrade has added the scoring columns) is {raw: {confidentiality, integrity, availability} (low, moderate or high, not_applicable for confidentiality only, or null when not set), display: '', result: {scored, categorization, score, band}}: result is computed from the current Preferences; scored is false and the other three are null unless all three are answered; score is a string with two decimals. Every display and name is RAW, unescaped text: insert it with text or attribute setters only. details.display is the stored rich text as-is; details.display_html is the purified HTML to render. associated_risks also carries count (how many associated risks the caller may see). Associated risks are limited to the risks the caller may see; without the Risk Management permission raw, display and names are empty and only count is set. Control names need the Governance permission. An asset the caller cannot access returns exactly the same 404 response as an asset that does not exist.",
 *     operationId="getUiAssetValues",
 *     tags={"ui"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset values retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="template_group_id", type="integer", description="The asset's own template group: request fields and layout for this group."),
 *                 @OA\Property(property="values", type="object", description="Field name to {raw, display}."),
 *                 @OA\Property(property="can_edit", type="boolean", description="The caller holds asset_edit."),
 *                 @OA\Property(property="can_verify", type="boolean", description="The caller holds asset_verify."),
 *                 @OA\Property(property="can_delete", type="boolean", description="The caller holds asset_delete.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing or invalid ID."),
 *     @OA\Response(response=403, description="FORBIDDEN: The caller does not have the Asset Management permission."),
 *     @OA\Response(response=404, description="NOT FOUND: The asset does not exist or the caller may not access it (one response for both)."),
 * )
 */
class OpenApiGetUiAssetValues {}

?>
