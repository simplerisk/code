<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;

/**
 * @OA\Get(
 *     path="/reports/risk/average",
 *     summary="Get the average risk score by date",
 *     operationId="reportsriskaverage",
 *     tags={"reports"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="risk_id",
 *       in="query",
 *       name="risk_id",
 *       description="The id of the risk you would like to retrieve details for. Will return all risks if no risk_id value is specified.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Parameter(
 *         parameter="type",
 *         in="query",
 *         name="type",
 *         description="Whether you would like results for inherent or residual risk.",
 *         required=false,
 *         @OA\Schema(
 *           type="string",
 *           enum={ "inherent", "residual" },
 *         ),
 *       ),
 *      @OA\Parameter(
 *        parameter="timeframe",
 *        in="query",
 *        name="timeframe",
 *        description="Whether you would like results displayed by day, month or year.",
 *        required=false,
 *        @OA\Schema(
 *          type="string",
 *          enum={ "day", "month", "year" },
 *        ),
 *      ),
 *     @OA\Response(
 *       response=200,
 *       description="Average Risk Score",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find the requested data.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiReportsRiskAverage {}

/**
 * @OA\Get(
 *     path="/reports/risk/opencount",
 *     summary="Get the count of risks by date",
 *     operationId="reportsriskopencount",
 *     tags={"reports"},
 *     security={{"ApiKeyAuth":{}}},
 *      @OA\Parameter(
 *        parameter="timeframe",
 *        in="query",
 *        name="timeframe",
 *        description="Whether you would like results displayed by day, month or year.",
 *        required=false,
 *        @OA\Schema(
 *          type="string",
 *          enum={ "day", "month", "year" },
 *        ),
 *      ),
 *     @OA\Response(
 *       response=200,
 *       description="Open Risk Count",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find the requested data.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiReportsRiskOpenCount {}

?>