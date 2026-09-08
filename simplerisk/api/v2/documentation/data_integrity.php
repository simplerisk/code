<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// @phan-suppress-next-line PhanUnreferencedUseNormal -- OA alias used in PHPDoc @OA annotations
use OpenApi\Annotations as OA;

// Annotations describe the API contract statically — see comment in general.php.

/**
 * @OA\Get(
 *     path="/admin/data-integrity/issues",
 *     summary="List open Data Integrity issues",
 *     description="Returns every 'open' row from the Data Integrity staging table, optionally filtered by issue_type. Each entry carries repair_mode ('inline_text_edit' -- broken_value/suggested_value are populated for an editable-fix UI, or 'link_to_record' -- no suggested value is possible, apply is not available for that issue). broken_value/suggested_value are decrypted for display when the source column is encrypted-capable.",
 *     operationId="listDataIntegrityIssues",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="issue_type",
 *         in="query",
 *         description="Filter results to a single detector's issue_type (e.g. 'invalid_text_encoding', 'file_content_mismatch').",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="List of open Data Integrity issues.",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="issues",
 *                     type="array",
 *                     description="Open issues, capped at DATA_INTEGRITY_ISSUES_LIST_LIMIT rows.",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="id", type="integer"),
 *                         @OA\Property(property="issue_type", type="string"),
 *                         @OA\Property(property="table_name", type="string"),
 *                         @OA\Property(property="column_name", type="string"),
 *                         @OA\Property(property="record_id", type="string"),
 *                         @OA\Property(property="broken_value", type="string", nullable=true),
 *                         @OA\Property(property="suggested_value", type="string", nullable=true),
 *                         @OA\Property(property="status", type="string", example="open"),
 *                         @OA\Property(property="detected_at", type="string", format="date-time"),
 *                         @OA\Property(property="repair_mode", type="string", enum={"inline_text_edit", "link_to_record"}, nullable=true)
 *                     )
 *                 ),
 *                 @OA\Property(property="total", type="integer", description="True count of open issues (may exceed issues.length when truncated by the cap).")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */

class OpenApiDataIntegrityIssuesList {}

/**
 * @OA\Patch(
 *     path="/admin/data-integrity/issues/{id}",
 *     summary="Apply a repair to a single Data Integrity issue",
 *     description="Writes the given value back to the source table/column and resolves the staged issue. Only issue types with an apply_fn (repair_mode 'inline_text_edit') can be repaired this way -- issues with repair_mode 'link_to_record' have no apply_fn and always answer 400.",
 *     operationId="applyDataIntegrityIssueRepair",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the open Data Integrity issue to repair.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"value"},
 *                 @OA\Property(property="value", type="string", description="The corrected value to write back to the source table/column.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Repair applied and the issue was resolved.",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="status", type="string", example="resolved")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: This issue type has no applicable repair, or no value was provided.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="NOT FOUND: No open issue with that id.",
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="ERROR: The repair could not be applied.",
 *     ),
 * )
 */

class OpenApiDataIntegrityIssueApply {}

/**
 * @OA\Post(
 *     path="/admin/data-integrity/issues/bulk-repair",
 *     summary="Apply repairs to many Data Integrity issues at once",
 *     description="Applies a repair value to each listed issue independently -- a per-issue failure (unknown id, no apply_fn, missing value) does not stop the batch; each result reports whether that individual repair was applied.",
 *     operationId="bulkRepairDataIntegrityIssues",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             @OA\Schema(
 *                 required={"repairs"},
 *                 @OA\Property(
 *                     property="repairs",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="id", type="integer", description="The ID of the open Data Integrity issue to repair."),
 *                         @OA\Property(property="value", type="string", description="The corrected value to write back to the source table/column.")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Per-issue repair results.",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="applied", type="boolean")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: No repairs provided.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */

class OpenApiDataIntegrityIssuesBulkRepair {}

/**
 * @OA\Post(
 *     path="/admin/data-integrity/scan",
 *     summary="Trigger an on-demand Data Integrity scan",
 *     description="Enqueues a core_data_integrity_scan queue task rather than scanning synchronously -- the scan itself runs on the next queue worker tick. Answers 409 instead of enqueueing a duplicate when a scan is already pending or in progress.",
 *     operationId="triggerDataIntegrityScan",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Scan queued.",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="message", type="string", example="Scan queued.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 *     @OA\Response(
 *         response=409,
 *         description="CONFLICT: A scan is already in progress.",
 *     ),
 * )
 */

class OpenApiDataIntegrityScanTrigger {}
