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
 *     path="/governance/controls/topdocuments",
 *     summary="Get the top documents for a control in SimpleRisk",
 *     operationId="controlTopDocuments",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the control you would like to retrieve the top documents for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Parameter(
 *       parameter="minimum_score",
 *       in="query",
 *       name="minimum_score",
 *       description="The minimum score you would like to consider a match.",
 *       required=false,
 *       @OA\Schema(
 *         type="float",
 *       ),
 *     ),
 *     @OA\Parameter(
 *         parameter="refresh",
 *         in="query",
 *         name="refresh",
 *         description="If set to 'true', the control to document matching will be refreshed. Accepts 'true' or 'false' as strings.",
 *         required=false,
 *         @OA\Schema(
 *             type="string",
 *             enum={"true", "false"},
 *         ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk control top documents",
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
class OpenApiControlsTopDocuments {}

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
 *     path="/governance/documents/controls",
 *     summary="Get the document to control mappings in SimpleRisk",
 *     operationId="documentsToControls",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="document_id",
 *       in="query",
 *       name="document_id",
 *       description="The id of the document you would like to retrieve the controls mappings for.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk document controls",
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
class OpenApiDocumentsToControls {}

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

/**
 * @OA\Get(
 *     path="/governance/documents/terms",
 *     summary="Get significant terms for a document in SimpleRisk",
 *     operationId="documentsTerms",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the document you would like to retrieve terms for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk document terms",
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
class OpenApiDocumentsTerms {}

/**
 * @OA\Get(
 *     path="/governance/documents/topcontrols",
 *     summary="Get the top controls for a document in SimpleRisk",
 *     operationId="documentsTopControls",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the document you would like to retrieve the top controls for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Parameter(
 *       parameter="minimum_score",
 *       in="query",
 *       name="minimum_score",
 *       description="The minimum score you would like to consider a match.",
 *       required=false,
 *       @OA\Schema(
 *         type="float",
 *       ),
 *     ),
 *     @OA\Parameter(
 *         parameter="refresh",
 *         in="query",
 *         name="refresh",
 *         description="If set to 'true', the document to control matching will be refreshed. Accepts 'true' or 'false' as strings.",
 *         required=false,
 *         @OA\Schema(
 *             type="string",
 *             enum={"true", "false"},
 *         ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk document top controls",
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
class OpenApiDocumentsTopControls {}

/**
 * @OA\Get(
 *     path="/governance/keywords",
 *     summary="Get keywords for Governance in SimpleRisk",
 *     operationId="keywords",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         parameter="type",
 *         in="query",
 *         name="type",
 *         description="The type of Governance keywords you would like to retrieve.",
 *         required=true,
 *         @OA\Schema(
 *             type="string",
 *             enum={"document", "control"},
 *         ),
 *     ),
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id you would like to retrieve keywords for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk Governance keywords retrieved",
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
class OpenApiKeywords {}

?>