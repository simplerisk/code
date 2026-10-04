<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// @phan-suppress-next-line PhanUnreferencedUseNormal -- OA alias used in PHPDoc @OA annotations
use OpenApi\Annotations as OA;

/**
 *   @OA\SecurityScheme(
 *     securityScheme="ApiKeyAuth",
 *     in="header",
 *     name="X-API-KEY",
 *     type="apiKey"
 *   )
 */

class OpenApiSecurity {}

/**
 * @OA\Put(
 *     path="/account/password",
 *     summary="Change the calling user's own password",
 *     operationId="changeOwnPassword",
 *     tags={"Security"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *       required=true,
 *       @OA\MediaType(
 *         mediaType="application/x-www-form-urlencoded",
 *         @OA\Schema(
 *           required={"current_password", "new_password", "confirm_password"},
 *           @OA\Property(property="current_password", type="string", format="password"),
 *           @OA\Property(property="new_password", type="string", format="password"),
 *           @OA\Property(property="confirm_password", type="string", format="password"),
 *           @OA\Property(property="mfa_code", type="string", description="Required when the calling user has MFA enabled (see GET /account/profile's mfa_enabled)."),
 *         ),
 *       ),
 *     ),
 *     @OA\Response(response=200, description="Password changed; other sessions for this user are terminated."),
 *     @OA\Response(response=400, description="Validation failure -- mismatched confirmation, policy violation (specific reason in status_message), or reused password."),
 *     @OA\Response(response=401, description="Incorrect current password, or a missing/invalid MFA code when MFA is enabled."),
 * )
 */

class OpenApiProfilePasswordUpdate {}

/**
 * @OA\Post(
 *     path="/account/api-key",
 *     summary="Generate (or rotate) the calling user's own API key",
 *     operationId="rotateOwnApiKey",
 *     tags={"Security"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *       required=false,
 *       @OA\MediaType(
 *         mediaType="application/x-www-form-urlencoded",
 *         @OA\Schema(
 *           @OA\Property(property="mfa_code", type="string", description="Required when the calling user has MFA enabled."),
 *         ),
 *       ),
 *     ),
 *     @OA\Response(response=200, description="A new API key was generated (replacing any existing one), returned once in this response."),
 *     @OA\Response(response=401, description="A missing/invalid MFA code when MFA is enabled."),
 * )
 * @OA\Delete(
 *     path="/account/api-key",
 *     summary="Revoke the calling user's own API key",
 *     operationId="revokeOwnApiKey",
 *     tags={"Security"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *       required=false,
 *       @OA\MediaType(
 *         mediaType="application/x-www-form-urlencoded",
 *         @OA\Schema(
 *           @OA\Property(property="mfa_code", type="string", description="Required when the calling user has MFA enabled."),
 *         ),
 *       ),
 *     ),
 *     @OA\Response(response=200, description="The API key was revoked."),
 *     @OA\Response(response=401, description="A missing/invalid MFA code when MFA is enabled."),
 * )
 */

class OpenApiProfileApiKey {}

?>