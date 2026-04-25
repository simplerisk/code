<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;

// =====================================================================
// ASSETS CRUD API
// =====================================================================

/**
 * @OA\Get(
 *     path="/assets/{id}",
 *     summary="Get an asset by ID",
 *     operationId="getAssetById",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset to retrieve.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset retrieved successfully.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object", description="The asset object."))
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission or no access to this asset."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset ID not found."),
 * )
 */
class OpenApiGetAssetById {}

/**
 * @OA\Post(
 *     path="/assets",
 *     summary="Create a new asset",
 *     operationId="createAsset",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"name"},
 *                 @OA\Property(property="name", type="string", description="Asset name (must be unique)."),
 *                 @OA\Property(property="ip", type="string", description="IP address of the asset."),
 *                 @OA\Property(property="value", type="integer", description="Asset value (0-5)."),
 *                 @OA\Property(property="location[]", type="array", @OA\Items(type="integer"), description="Location IDs."),
 *                 @OA\Property(property="team[]", type="array", @OA\Items(type="integer"), description="Team IDs."),
 *                 @OA\Property(property="details", type="string", description="Asset details."),
 *                 @OA\Property(property="tags[]", type="array", @OA\Items(type="string"), description="Tags to apply."),
 *                 @OA\Property(property="verified", type="boolean", description="Whether the asset is verified."),
 *                 @OA\Property(property="associated_risks[]", type="array", @OA\Items(type="integer"), description="Risk IDs to associate."),
 *                 @OA\Property(property="control_maturity[]", type="array", @OA\Items(type="integer"), description="Control maturity values, paired by index with control_id[]."),
 *                 @OA\Property(property="control_id[]", type="array", @OA\Items(type="integer"), description="Control IDs, paired by index with control_maturity[].")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Asset created successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing name, duplicate name, or validation error."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 * )
 */
class OpenApiCreateAsset {}

/**
 * @OA\Patch(
 *     path="/assets/{id}",
 *     summary="Update an existing asset",
 *     operationId="updateAssetById",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset to update.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="name", type="string", description="Asset name."),
 *                 @OA\Property(property="ip", type="string", description="IP address of the asset."),
 *                 @OA\Property(property="value", type="integer", description="Asset value (0-5)."),
 *                 @OA\Property(property="location[]", type="array", @OA\Items(type="integer"), description="Location IDs."),
 *                 @OA\Property(property="team[]", type="array", @OA\Items(type="integer"), description="Team IDs."),
 *                 @OA\Property(property="details", type="string", description="Asset details."),
 *                 @OA\Property(property="tags[]", type="array", @OA\Items(type="string"), description="Tags to apply. Omit to preserve existing tags."),
 *                 @OA\Property(property="verified", type="boolean", description="Whether the asset is verified."),
 *                 @OA\Property(property="associated_risks[]", type="array", @OA\Items(type="integer"), description="Risk IDs to associate."),
 *                 @OA\Property(property="control_maturity[]", type="array", @OA\Items(type="integer"), description="Control maturity values, paired by index with control_id[]."),
 *                 @OA\Property(property="control_id[]", type="array", @OA\Items(type="integer"), description="Control IDs, paired by index with control_maturity[].")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Asset updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Validation error or duplicate name."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission or no access to this asset."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset ID not found."),
 * )
 */
class OpenApiUpdateAssetById {}

/**
 * @OA\Delete(
 *     path="/assets/{id}",
 *     summary="Delete an asset",
 *     operationId="deleteAssetById",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset to delete.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Asset deleted successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission or no access to this asset."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset ID not found."),
 * )
 */
class OpenApiDeleteAssetById {}

/**
 * @OA\Get(
 *     path="/assets/{id}/associations",
 *     summary="Get the risk associations for an asset",
 *     operationId="getAssetAssociationsById",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Associations retrieved successfully.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="risks", type="array", @OA\Items(type="object"), description="Risk objects associated with this asset.")
 *         ))
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission or no access to this asset."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset ID not found."),
 * )
 */
class OpenApiGetAssetAssociationsById {}

// =====================================================================
// ASSET GROUPS CRUD API
// =====================================================================

/**
 * @OA\Get(
 *     path="/asset-groups",
 *     summary="List all asset groups",
 *     operationId="listAssetGroups",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Asset groups retrieved successfully.",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="asset_groups", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string")
 *                 ))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 * )
 */
class OpenApiListAssetGroups {}

/**
 * @OA\Post(
 *     path="/asset-groups",
 *     summary="Create a new asset group",
 *     operationId="createAssetGroup",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"name"},
 *                 @OA\Property(property="name", type="string", description="Unique name for the asset group."),
 *                 @OA\Property(property="selected_assets[]", type="array", @OA\Items(type="integer"), description="Asset IDs to include in the group.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Asset group created successfully.", @OA\JsonContent(type="object", @OA\Property(property="data", type="object", @OA\Property(property="id", type="integer", description="ID of the new asset group.")))),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing name, duplicate name, or creation failed."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 * )
 */
class OpenApiCreateAssetGroup {}

/**
 * @OA\Get(
 *     path="/asset-groups/{id}",
 *     summary="Get an asset group by ID",
 *     operationId="getAssetGroupById",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset group.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset group retrieved successfully.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="asset_group", type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="name", type="string"),
 *                 @OA\Property(property="selected_assets", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string")
 *                 )),
 *                 @OA\Property(property="available_assets", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string")
 *                 ))
 *             )
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing ID."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset group ID not found."),
 * )
 */
class OpenApiGetAssetGroupById {}

/**
 * @OA\Patch(
 *     path="/asset-groups/{id}",
 *     summary="Update an asset group",
 *     operationId="updateAssetGroupById",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset group to update.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="name", type="string", description="New name for the group. Omit to keep existing name."),
 *                 @OA\Property(property="selected_assets[]", type="array", @OA\Items(type="integer"), description="Full replacement list of asset IDs. Omit to keep existing assets.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Asset group updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Duplicate name or update failed."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset group ID not found."),
 * )
 */
class OpenApiUpdateAssetGroupById {}

/**
 * @OA\Delete(
 *     path="/asset-groups/{id}",
 *     summary="Delete an asset group",
 *     operationId="deleteAssetGroupById",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset group to delete.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Asset group deleted successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Deletion failed."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset group ID not found."),
 * )
 */
class OpenApiDeleteAssetGroupById {}

/**
 * @OA\Get(
 *     path="/asset-groups/{id}/assets",
 *     summary="Get the full asset details for all assets in a group",
 *     operationId="getAssetGroupAssets",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset group.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Assets retrieved successfully.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="assets", type="array", @OA\Items(type="object"), description="Full asset objects belonging to the group.")
 *         ))
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset group ID not found."),
 * )
 */
class OpenApiGetAssetGroupAssets {}

/**
 * @OA\Post(
 *     path="/asset-groups/{id}/assets",
 *     summary="Add assets to an asset group",
 *     operationId="addAssetsToAssetGroup",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset group.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"asset_ids[]"},
 *                 @OA\Property(property="asset_ids[]", type="array", @OA\Items(type="integer"), description="IDs of assets to add to the group. Existing members are preserved.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Assets added to group successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing asset_ids or update failed."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset group ID not found."),
 * )
 */
class OpenApiAddAssetsToAssetGroup {}

/**
 * @OA\Delete(
 *     path="/asset-groups/{id}/assets/{asset_id}",
 *     summary="Remove an asset from an asset group",
 *     operationId="removeAssetFromAssetGroupById",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset group.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="asset_id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset to remove from the group.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Asset removed from group successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Removal failed."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset group ID not found."),
 * )
 */
class OpenApiRemoveAssetFromAssetGroupById {}

// =====================================================================
// ASSET MANAGEMENT (LEGACY)
// =====================================================================

/**
 *  @OA\Schema(
 *      schema="AssetUpdate",
 *      description="Schema for Asset update",
 *      allOf={
 *          @OA\Schema(
 *              type="object",
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
 *      type="object",
 *      @OA\Property(property="control_maturity", type="integer"),
 *      @OA\Property(
 *          property="control_id",
 *          type="array",
 *          @OA\Items(type="integer")
 *      )
 *  )
 */
class OpenApiAssetControlMappingSchema {}

/**
 * @OA\Get(
 *     path="/assets",
 *     summary="List assets in SimpleRisk",
 *     operationId="assets",
 *     tags={"asset_crud"},
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

/**
 * @OA\Post(
 *     path="/assets/create_asset",
 *     summary="Create a new asset (legacy form-style endpoint).",
 *     operationId="createAssetLegacy",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Create a new asset (legacy form-style endpoint).",
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"asset_name"},
 *                 @OA\Property(property="asset_name", type="string", description="The name of the asset."),
 *                 @OA\Property(property="ip", type="string", description="The IP address of the asset."),
 *                 @OA\Property(property="value", type="integer", description="Asset value: 0=Low, 1=Medium, 2=High, 3=Very High."),
 *                 @OA\Property(property="location", type="integer", description="The location id of the asset."),
 *                 @OA\Property(property="team", type="array", @OA\Items(type="integer"), description="Array of team ids associated with the asset."),
 *                 @OA\Property(property="details", type="string", description="Additional details about the asset."),
 *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), description="Array of tags for the asset."),
 *                 @OA\Property(property="verified", type="boolean", description="Whether the asset is verified."),
 *                 @OA\Property(property="created_at", type="string", format="date", description="The creation date of the asset."),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset created successfully.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiCreateAssetLegacy {}

/**
 * @OA\Post(
 *     path="/assets/view/asset_data",
 *     summary="Retrieve asset data for a DataTables view, supporting pagination and filtering.",
 *     operationId="assetsViewData",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="DataTables request parameters.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(property="draw", type="integer", description="DataTables draw counter."),
 *                 @OA\Property(property="start", type="integer", description="Paging first record indicator."),
 *                 @OA\Property(property="length", type="integer", description="Number of records to return."),
 *                 @OA\Property(property="verified", type="string", enum={"true", "false"}, description="Filter assets by verified status."),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="DataTables-formatted asset data.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer"),
 *             @OA\Property(property="recordsTotal", type="integer"),
 *             @OA\Property(property="recordsFiltered", type="integer"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *         )
 *     ),
 * )
 */
class OpenApiAssetsViewData {}

/**
 * @OA\Post(
 *     path="/assets/view/action",
 *     summary="Perform a bulk action on assets from the asset view.",
 *     operationId="assetsViewAction",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Bulk action parameters.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"asset_ids"},
 *                 @OA\Property(property="action", type="string", enum={"delete", "verify", "unverify"}, description="The bulk action to perform on the selected assets."),
 *                 @OA\Property(property="asset_ids", type="array", @OA\Items(type="integer"), description="Array of asset ids to act upon."),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Bulk action performed successfully.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAssetsViewAction {}

/**
 * @OA\Get(
 *     path="/assets/options",
 *     summary="Get a list of assets for use in dropdown/select fields.",
 *     operationId="getAssetOptions",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         in="query",
 *         name="verified",
 *         description="Filter assets by verified status.",
 *         required=false,
 *         @OA\Schema(
 *             type="string",
 *             enum={"true", "false"},
 *         ),
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of asset option objects.",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="name", type="string"),
 *             )
 *         )
 *     ),
 * )
 */
class OpenApiGetAssetOptions {}

/**
 * @OA\Post(
 *     path="/assets/create",
 *     summary="Create a new asset.",
 *     operationId="createAssetLegacyAlt",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="New asset details.",
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"asset_name"},
 *                 @OA\Property(property="asset_name", type="string", description="The name of the asset."),
 *                 @OA\Property(property="ip", type="string", description="The IP address of the asset."),
 *                 @OA\Property(property="value", type="integer", description="Asset value: 0=Low, 1=Medium, 2=High, 3=Very High."),
 *                 @OA\Property(property="location", type="integer", description="The location id of the asset."),
 *                 @OA\Property(property="team", type="array", @OA\Items(type="integer"), description="Array of team ids associated with the asset."),
 *                 @OA\Property(property="details", type="string", description="Additional details about the asset."),
 *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), description="Array of tags for the asset."),
 *                 @OA\Property(property="verified", type="boolean", description="Whether the asset is verified."),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset created successfully.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiCreateAssetLegacyAlt {}

/**
 * @OA\Post(
 *     path="/assets/delete",
 *     summary="Delete an asset.",
 *     operationId="deleteAsset",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Id of the asset to delete.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"id"},
 *                 @OA\Property(property="id", type="integer", description="The id of the asset to delete."),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset deleted successfully.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiDeleteAsset {}

/**
 * @OA\Post(
 *     path="/asset-group/create",
 *     summary="Create a new asset group.",
 *     operationId="assetGroupCreate",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="New asset group details.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"name"},
 *                 @OA\Property(property="name", type="string", description="The name of the asset group."),
 *                 @OA\Property(property="selected_assets", type="array", @OA\Items(type="integer"), description="Array of asset ids to include in the group."),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset group created successfully.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAssetGroupCreate {}

/**
 * @OA\Post(
 *     path="/asset-group/update",
 *     summary="Update an existing asset group.",
 *     operationId="assetGroupUpdate",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Updated asset group details.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"asset_group_id", "name"},
 *                 @OA\Property(property="asset_group_id", type="integer", description="The id of the asset group to update."),
 *                 @OA\Property(property="name", type="string", description="The updated name of the asset group."),
 *                 @OA\Property(property="selected_assets", type="array", @OA\Items(type="integer"), description="Array of asset ids to associate with the group."),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset group updated successfully.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAssetGroupUpdate {}

/**
 * @OA\Post(
 *     path="/asset-group/delete",
 *     summary="Delete an asset group.",
 *     operationId="assetGroupDelete",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Id of the asset group to delete.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"asset_group_id"},
 *                 @OA\Property(property="asset_group_id", type="integer", description="The id of the asset group to delete."),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset group deleted successfully.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAssetGroupDelete {}

/**
 * @OA\Post(
 *     path="/asset-group/remove_asset",
 *     summary="Remove an asset from an asset group.",
 *     operationId="assetGroupRemoveAsset",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Asset group and asset ids.",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"asset_group_id", "asset_id"},
 *                 @OA\Property(property="asset_group_id", type="integer", description="The id of the asset group."),
 *                 @OA\Property(property="asset_id", type="integer", description="The id of the asset to remove from the group."),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset removed from group successfully.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAssetGroupRemoveAsset {}

/**
 * @OA\Get(
 *     path="/asset-group/tree",
 *     summary="Get asset groups as a treegrid structure.",
 *     operationId="assetGroupTree",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         in="query",
 *         name="page",
 *         description="The page number for pagination.",
 *         required=true,
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\Parameter(
 *         in="query",
 *         name="rows",
 *         description="The number of rows per page.",
 *         required=true,
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\Parameter(
 *         in="query",
 *         name="id",
 *         description="If provided, returns the children of the specified asset group.",
 *         required=false,
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Treegrid-formatted asset group data.",
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAssetGroupTree {}

/**
 * @OA\Get(
 *     path="/asset-group/info",
 *     summary="Get details for an asset group including its current and available assets.",
 *     operationId="assetGroupInfo",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         in="query",
 *         name="id",
 *         description="The id of the asset group to retrieve details for.",
 *         required=true,
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Asset group details.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="selected_assets", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="available_assets", type="array", @OA\Items(type="object")),
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiAssetGroupInfo {}

/**
 * @OA\Get(
 *     path="/asset-group/options",
 *     summary="Get a list of asset groups for use in dropdown/select fields.",
 *     operationId="getAssetGroupOptions",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         in="query",
 *         name="id",
 *         description="Optional asset group id to filter results.",
 *         required=false,
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\Parameter(
 *         in="query",
 *         name="type",
 *         description="Optional type filter for asset group options.",
 *         required=false,
 *         @OA\Schema(type="string"),
 *     ),
 *     @OA\Parameter(
 *         in="query",
 *         name="selected_only",
 *         description="If true, return only selected asset groups.",
 *         required=false,
 *         @OA\Schema(type="boolean"),
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of asset group option objects.",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object"),
 *         )
 *     ),
 * )
 */
class OpenApiGetAssetGroupOptions {}

?>