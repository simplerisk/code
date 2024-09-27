<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * @OA\Get(
 *     path="/ai/recommendations",
 *     summary="Retrieve recommendations from Anthropic",
 *     operationId="artificialIntelligenceRecommendations",
 *     tags={"Artificial Intelligence"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *       response=200,
 *       description="Anthropic recommendations",
 *     ),
 *     @OA\Response(
 *       response=503,
 *       description="SERVICE UNAVAILABLE: Unable to query Anthropic recommendations.",
 *     ),
 * )
 */
class artificialIntelligenceRecommendations {}

?>