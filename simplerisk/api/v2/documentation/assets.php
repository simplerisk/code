<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;

/**
 *  @OA\Schema(
 *      schema="AssetUpdate",
 *      description="Schema for Asset update",
 *      allOf={
 *          @OA\Schema(
 *              required={"id", "edit_view"},
 *              @OA\Property(property="id", type="integer", example="5"),
 *              @OA\Property(property="edit_view", type="string", enum={"asset_verified", "asset_unverified"}),
 *          ),
 *          @OA\Schema(ref="#/components/schemas/AssetBase")
 *      }
 *  )
 */
class OpenApiAssetUpdateSchema {}

/**
 *  @OA\Schema(
 *      schema="AssetControlMapping",
 *      @OA\Property(property="control_maturity", type="integer"),
 *      @OA\Property(property="control_id", type="array", items={
 *              @OA\Items(type="integer"),
 *          }
 *      )
 *  )
 */
class OpenApiAssetControlMappingSchema {}

/**
 * @OA\Get(
 *     path="/assets",
 *     summary="List assets in SimpleRisk",
 *     operationId="assets",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
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
 *         enum={ "true", "false" },
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

/**
 * @OA\Get(
 *     path="/assets/associations",
 *     summary="List asset associations in SimpleRisk",
 *     operationId="assetsAssociations",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the asset you would like to retrieve associations for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk asset associations",
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
class OpenApiAssetsAssociations {}

/**
 * @OA\Get(
 *     path="/assets/tags",
 *     summary="List asset tags",
 *     operationId="assetsTagsGet",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *        parameter="id",
 *        in="query",
 *        name="id",
 *        description="The id of the tag you would like to retrieve details for. Will return all tags if no id is specified.",
 *        required=false,
 *        @OA\Schema(
 *          type="integer",
 *        ),
 *      ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk asset tags",
 *     ),
 *     @OA\Response(
 *        response=204,
 *        description="NO CONTENT: Unable to find a tag with the specified id.",
 *      ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAssetsTagsGet {}

/**
 *  @OA\Post(
 *      path="/assets/update_asset",
 *      summary="Update asset.",
 *      operationId="update_asset",
 *      tags={"asset", "need_explode_for_arrays"},
 *      security={{"ApiKeyAuth":{}}},
 *      @OA\RequestBody(
 *          required=true,
 *          description="Edited asset's details",
 *          @OA\MediaType(
 *              mediaType="multipart/form-data",
 *              @OA\Schema(ref="#/components/schemas/AssetUpdate")
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="SimpleRisk assets",
 *      ),
 *      @OA\Response(
 *          response=204,
 *          description="NO CONTENT: Unable to find an asset with the specified id.",
 *      ),
 *      @OA\Response(
 *          response=403,
 *          description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *      ),
 *  )
 */
class OpenApiUpdateAsset {}

?>