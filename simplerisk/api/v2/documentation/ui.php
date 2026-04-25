<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

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
 *                 @OA\Property(property="layout_name", type="string", enum={"overview", "dashboard_open", "dashboard_close", "compliance_dashboard"}, description="The name of the layout to save."),
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
 *         @OA\Schema(type="string", enum={"overview", "dashboard_open", "dashboard_close", "compliance_dashboard"})
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
 *         @OA\Schema(type="string", enum={"overview", "dashboard_open", "dashboard_close", "compliance_dashboard"})
 *     ),
 *     @OA\Parameter(
 *         name="widget_name",
 *         in="query",
 *         required=true,
 *         description="The widget to render. Available widgets depend on the chosen layout_name: 'overview' supports chart_open_vs_closed, chart_mitigation_planned_vs_unplanned, chart_reviewed_vs_unreviewed, table_risks_by_month, WYSIWYG; 'dashboard_open' supports open_risk_level, open_status, open_site_location, open_risk_source, open_category, open_team, open_technology, open_owner, open_owners_manager, open_risk_scoring_method, WYSIWYG; 'dashboard_close' supports close_reason, WYSIWYG; 'compliance_dashboard' supports compliance_controls_by_framework_bar_chart, compliance_pass_fail_pie_chart.",
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
 *                 @OA\Property(property="layout_name", type="string", enum={"overview", "dashboard_open", "dashboard_close", "compliance_dashboard"}, description="The name of the layout to update."),
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

?>
