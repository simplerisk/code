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
*     tags={"admin"},
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
 *     tags={"admin"},
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
 *     tags={"admin"},
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


?>