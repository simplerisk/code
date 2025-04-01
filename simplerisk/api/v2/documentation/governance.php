<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * @OA\Get(
 *     path="/governance/frameworks",
 *     summary="List control frameworks in SimpleRisk",
 *     operationId="governanceFrameworks",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the control framework you would like to retrieve details for. Will return all control frameworks if no id is specified.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Parameter(
 *       parameter="status",
 *       in="query",
 *       name="status",
 *       description="Use a status of 1 for enabled or 2 for disabled. Will default to enabled.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *         enum={"1", "2"},
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk control frameworks",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a control framework with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiGovernanceFrameworks {}

/**
 * @OA\Get(
 *     path="/governance/frameworks/associations",
 *     summary="List framework associations in SimpleRisk",
 *     operationId="frameworksAssociations",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the framework you would like to retrieve associations for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk framework associations",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a framework with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiFrameworksAssociations {}

/**
 * @OA\Get(
 *     path="/governance/controls",
 *     summary="List controls in SimpleRisk",
 *     operationId="governanceControls",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the control you would like to retrieve details for. Will return all controls if no id is specified.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk controls",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a control with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiGovernanceControls {}

/**
 * @OA\Get(
 *     path="/governance/controls/associations",
 *     summary="List control associations in SimpleRisk",
 *     operationId="controlsAssociations",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the control you would like to retrieve associations for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk control associations",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a control with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiControlsAssociations {}

/**
 * @OA\Get(
 *     path="/governance/documents",
 *     summary="List documents in SimpleRisk",
 *     operationId="governanceDocuments",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the document you would like to retrieve details for. Will return all documents if no id is specified.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk documents",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a document with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiGovernanceDocuments {}

/**
 * @OA\Delete(
 *     path="/governance/documents",
 *     summary="Delete documents in SimpleRisk",
 *     operationId="governanceDocumentsDelete",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *        parameter="document_id",
 *        in="query",
 *        name="document_id",
 *        description="The id of the document you would like to delete.",
 *        required=true,
 *        @OA\Schema(
 *          type="integer",
 *        ),
 *     ),
 *     @OA\Parameter(
 *         parameter="version",
 *         in="query",
 *         name="version",
 *         description="The versionof the document you would like to delete.",
 *         required=false,
 *         @OA\Schema(
 *           type="integer",
 *         ),
 *      ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk documents delete successful",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a document with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiGovernanceDocumentsDelete {}

/**
 * @OA\Get(
 *     path="/governance/documents/associations",
 *     summary="List document associations in SimpleRisk",
 *     operationId="documentsAssociations",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the document you would like to retrieve associations for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk document associations",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a document with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiDocumentsAssociations {}

?>