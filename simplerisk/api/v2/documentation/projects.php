<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// =====================================================================
// PLAN PROJECTS GRID API (SR-2229)
// =====================================================================

/**
 * @OA\Get(
 *     path="/management/projects",
 *     summary="Plan Projects grid: every project for one status chip, with counts, saved column settings and filter options",
 *     operationId="planProjectsGrid",
 *     tags={"project"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"1","2","3","4","all"}), description="1 Active, 2 On hold, 3 Completed, 4 Canceled, or all. Defaults to 1."),
 *     @OA\Response(response=200, description="Grid payload",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="counts", type="object", description="Row counts keyed 1..4 and all"),
 *             @OA\Property(property="column_settings", type="object", nullable=true, description="The caller's saved user.custom_plan_projects_display_settings, decoded"),
 *             @OA\Property(property="active_columns", type="array", nullable=true, @OA\Items(type="object"), description="Customization-curated column vocabulary; null when the Extra is inactive"),
 *             @OA\Property(property="filter_options", type="object"),
 *             @OA\Property(property="show_all", type="boolean")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Caller lacks risk management permission",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiPlanProjectsGrid {}

/**
 * @OA\Get(
 *     path="/management/project/{id}/risks",
 *     summary="The Plan Projects expand-drawer: every risk assigned to one project",
 *     operationId="planProjectRisks",
 *     tags={"project"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), description="The project id."),
 *     @OA\Response(response=200, description="The project's risks",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(response=403, description="Caller lacks risk management permission",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=404, description="Project not found",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=404),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiPlanProjectRisks {}

/**
 * @OA\Get(
 *     path="/management/projects/unassigned_risks",
 *     summary="The Plan Projects Unassigned Risks queue",
 *     operationId="planProjectsUnassignedRisks",
 *     tags={"project"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Unassigned risks",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="show_all", type="boolean")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Caller lacks risk management permission",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiPlanProjectsUnassignedRisks {}

/**
 * @OA\Post(
 *     path="/management/projects/assign_risks",
 *     summary="Assign (or unassign, with project_id=0) one or more risks to a project",
 *     operationId="assignRisksToProject",
 *     tags={"project"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="risk_ids", type="array", @OA\Items(type="integer"), description="Internal risk ids (not the display id) to (re)assign."),
 *                 @OA\Property(property="project_id", type="integer", description="Target project id, or 0 to return the risks to Unassigned.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="At least one risk was assigned",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="assigned", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="denied", type="array", @OA\Items(type="integer"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Missing or invalid risk_ids/project_id",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=400),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=403, description="Caller lacks riskmanagement, manage_projects or modify_risks, or every referenced risk was denied by Team Separation",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=404, description="project_id does not exist",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=404),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiAssignRisksToProject {}

/**
 * @OA\Post(
 *     path="/management/projects/reorder",
 *     summary="Persist a drag-reordered project sequence within one status chip",
 *     operationId="reorderPlanProjects",
 *     tags={"project"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="status", type="integer", enum={1,2,3,4}, description="The status chip being reordered."),
 *                 @OA\Property(property="project_ids", type="array", @OA\Items(type="integer"), description="Every project id currently in that status, in the desired new order. Must be exactly that status's id set -- a mismatch (stale client state) is rejected with 400.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Order saved",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=400, description="Missing status/project_ids, or project_ids does not match the status's current id set",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=400),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=403, description="Caller lacks riskmanagement or manage_projects",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiReorderPlanProjects {}

/**
 * @OA\Post(
 *     path="/management/projects/display_settings",
 *     summary="Save the current user's column display settings for the Plan Projects grid",
 *     operationId="savePlanProjectsDisplaySettings",
 *     tags={"project"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="columns", type="array", description="List of [column_name, visibility] pairs, e.g. [[due_date, 0], [risk_count, 1]]. column_name must match ^[A-Za-z0-9_]+$; visibility is the string 0 or 1.", @OA\Items(type="array", @OA\Items(type="string"))),
 *                 @OA\Property(property="order", type="array", description="Optional. Drag-reordered column sequence, left-to-right. Each name must match ^[A-Za-z0-9_]+$.", @OA\Items(type="string"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Display settings saved",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=400, description="Missing or invalid columns/order",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=400),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=403, description="Caller lacks risk management permission",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="status_message", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="data", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiSavePlanProjectsDisplaySettings {}
