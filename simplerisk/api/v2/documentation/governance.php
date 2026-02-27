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
 *         parameter="id",
 *         in="query",
 *         name="id",
 *         description="The id of the control framework you would like to retrieve details for. Will return all control frameworks if no id is specified.",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         parameter="status",
 *         in="query",
 *         name="status",
 *         description="Use a status of 1 for enabled or 2 for disabled. Will default to enabled.",
 *         required=false,
 *         @OA\Schema(type="integer", enum={"1", "2"})
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk control frameworks",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="message", type="string", example="Success"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object", additionalProperties=true))
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a control framework with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
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
 *         parameter="id",
 *         in="query",
 *         name="id",
 *         description="The id of the framework you would like to retrieve associations for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk framework associations",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a framework with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
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
 *         parameter="id",
 *         in="query",
 *         name="id",
 *         description="The id of the control you would like to retrieve details for. Will return all controls if no id is specified.",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk controls",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a control with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
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
 *         parameter="id",
 *         in="query",
 *         name="id",
 *         description="The id of the control you would like to retrieve associations for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk control associations",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a control with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiControlsAssociations {}

/**
 * @OA\Get(
 *     path="/governance/controls/mapped-frameworks",
 *     summary="Get mapped framework controls for a governance control",
 *     description="Returns a list of framework control references mapped to a given governance control ID.",
 *     operationId="getControlMappedFrameworks",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="control_id",
 *         in="query",
 *         description="The ID of the governance control",
 *         required=true,
 *         @OA\Schema(type="integer", example=42)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successfully retrieved mapped controls",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Successfully retrieved mapped controls."),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="framework_name", type="string", example="NIST CSF"),
 *                     @OA\Property(property="reference_name", type="string", example="ID.AM-1"),
 *                     @OA\Property(property="reference_text", type="string", example="Physical devices and systems within the organization are inventoried")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="User does not have governance permissions",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="No permission for governance"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiGovernanceControlsMappings {}

/**
 * @OA\Get(
 *     path="/governance/controls/mapped-frameworks/count",
 *     summary="Get the count of mapped frameworks for a specific control",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="control_id",
 *         in="query",
 *         description="ID of the control to retrieve the mapped frameworks count for",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successfully retrieved mapped frameworks count",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="control_id", type="integer", example=123),
 *             @OA\Property(property="count", type="integer", example=12)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="User does not have governance permissions or invalid input",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="message", type="string", example="No permission for governance")
 *         )
 *     )
 * )
 */
class OpenApiGovernanceControlsMappingsCount {}

/**
 * @OA\Get(
 *     path="/governance/documents",
 *     summary="List documents in SimpleRisk",
 *     operationId="governanceDocuments",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         parameter="id",
 *         in="query",
 *         name="id",
 *         description="The id of the document you would like to retrieve details for. Will return all documents if no id is specified.",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk documents",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a document with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
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
 *         parameter="document_id",
 *         in="query",
 *         name="document_id",
 *         description="The id of the document you would like to delete.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         parameter="version",
 *         in="query",
 *         name="version",
 *         description="The version of the document you would like to delete.",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk documents delete successful",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Document deleted successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a document with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
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
 *         parameter="document_id",
 *         in="query",
 *         name="document_id",
 *         description="The id of the document you would like to retrieve the controls mappings for.",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk document controls",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a document with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
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
 *         parameter="id",
 *         in="query",
 *         name="id",
 *         description="The id of the document you would like to retrieve associations for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk document associations",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a document with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
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
 *         parameter="id",
 *         in="query",
 *         name="id",
 *         description="The id of the document you would like to retrieve terms for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk document terms",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a document with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiDocumentsTerms {}

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
 *         @OA\Schema(type="string", enum={"document", "control"})
 *     ),
 *     @OA\Parameter(
 *         parameter="id",
 *         in="query",
 *         name="id",
 *         description="The id you would like to retrieve keywords for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="SimpleRisk Governance keywords retrieved",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="NO CONTENT: Unable to find a document with the specified id."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=403),
 *             @OA\Property(property="message", type="string", example="Forbidden"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiKeywords {}

?>