<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;

/**
 * @OA\Get(
 *     path="/assets",
 *     summary="List assets in SimpleRisk",
 *     operationId="assets",
 *     tags={"assets"},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the asset you would like to retrieve details for. Will return all assets if no id is specified.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Parameter(
 *       parameter="verified",
 *       in="query",
 *       name="verified",
 *       description="A true or false value for whether to return only verified assets.",
 *       required=false,
 *       @OA\Schema(
 *         type="string",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk assets",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find an asset with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */

class OpenApiAssets {}


?>