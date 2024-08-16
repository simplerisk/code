<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;

/**
* @OA\Get(
*     path="/admin/version",
*     summary="List SimpleRisk version information",
*     operationId="version",
*     tags={"Administrator Operations"},
*     security={{"ApiKeyAuth":{}}},
*     @OA\Response(
*       response=200,
*       description="SimpleRisk version information",
*     ),
*     @OA\Response(
*       response=403,
*       description="FORBIDDEN: The user does not having admin privileges.",
*     ),
* )
*/

class OpenApiAdminVersion {}

/**
 * @OA\Get(
 *     path="/admin/version/app",
 *     summary="List SimpleRisk application version information",
 *     operationId="appVersion",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk application version information",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not having admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminVersionApp {}

/**
 * @OA\Get(
 *     path="/admin/version/db",
 *     summary="List SimpleRisk database version information",
 *     operationId="dbVersion",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk database version information",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not having admin privileges.",
 *     ),
 * )
 */

class OpenApiAdminVersionDB {}

/**
 * @OA\Delete(
 *     path="/admin/tag",
 *     summary="Delete tag",
 *     operationId="tagDelete",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *        parameter="id",
 *        in="query",
 *        name="id",
 *        description="The id of the tag you would like to delete.",
 *        required=true,
 *        @OA\Schema(
 *          type="integer",
 *        ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk tag deleted",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAdminTagsDelete {}

/**
 * @OA\Delete(
 *     path="/admin/tag/all",
 *     summary="Delete all tags",
 *     operationId="allTagDelete",
 *     tags={"Administrator Operations"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *        parameter="type",
 *        in="query",
 *        name="type",
 *        description="The type of tag you would like to delete.",
 *        required=true,
 *        @OA\Schema(
 *          type="string",
 *          enum={"risk", "asset", "test", "test_audit", "incident_management_destination", "incident_management_source", "questionnaire_pending_risk", "questionnaire_risk", "questionnaire_answer", "all"},
 *        ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk tags deleted",
 *     ),
 *     @OA\Response(
 *       response=400,
 *       description="Invalid type",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAdminAllTagsDelete {}

?>