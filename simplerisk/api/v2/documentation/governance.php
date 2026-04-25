<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// GOVERNANCE (CRUD)

/**
 * @OA\Get(
 *     path="/governance/frameworks/{id}",
 *     summary="Get a framework by ID",
 *     operationId="getFrameworkById",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the framework to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Framework retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="message", type="string", example="Framework retrieved successfully."),
 *             @OA\Property(property="data", type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have governance permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a framework with the specified id.")
 * )
 */
class OpenApiGetFrameworkById {}

/**
 * @OA\Post(
 *     path="/governance/frameworks",
 *     summary="Create a new framework",
 *     operationId="createFramework",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"name"},
 *                 @OA\Property(property="name", type="string", description="The name of the framework."),
 *                 @OA\Property(property="description", type="string", description="A description of the framework."),
 *                 @OA\Property(property="parent", type="integer", description="The parent framework ID (0 for no parent).", example=0),
 *                 @OA\Property(property="status", type="integer", description="Status: 1=enabled, 2=disabled.", example=1)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Framework created successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=201),
 *             @OA\Property(property="message", type="string", example="Framework created successfully."),
 *             @OA\Property(property="data", type="object", @OA\Property(property="id", type="integer", example=5))
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to add frameworks."),
 *     @OA\Response(response=409, description="CONFLICT: A framework with that name already exists.")
 * )
 */
class OpenApiCreateFramework {}

/**
 * @OA\Patch(
 *     path="/governance/frameworks/{id}",
 *     summary="Update a framework by ID",
 *     operationId="updateFrameworkById",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the framework to update.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="name", type="string", description="The new name for the framework."),
 *                 @OA\Property(property="description", type="string", description="The new description for the framework."),
 *                 @OA\Property(property="parent", type="integer", description="The new parent framework ID.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Framework updated successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to modify frameworks."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a framework with the specified id.")
 * )
 */
class OpenApiUpdateFrameworkById {}

/**
 * @OA\Delete(
 *     path="/governance/frameworks/{id}",
 *     summary="Delete a framework by ID",
 *     operationId="deleteFrameworkById",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the framework to delete.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Framework deleted successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to delete frameworks."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a framework with the specified id.")
 * )
 */
class OpenApiDeleteFrameworkById {}

/**
 * @OA\Get(
 *     path="/governance/controls/{id}",
 *     summary="Get a control by ID",
 *     operationId="getControlById",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the control to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Control retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="message", type="string", example="Control retrieved successfully."),
 *             @OA\Property(property="data", type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have governance permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a control with the specified id.")
 * )
 */
class OpenApiGetControlById {}

/**
 * @OA\Post(
 *     path="/governance/controls",
 *     summary="Create a new control",
 *     operationId="createControl",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"short_name"},
 *                 @OA\Property(property="short_name", type="string", description="The short name (required)."),
 *                 @OA\Property(property="long_name", type="string", description="The long name of the control."),
 *                 @OA\Property(property="description", type="string", description="A description of the control."),
 *                 @OA\Property(property="supplemental_guidance", type="string"),
 *                 @OA\Property(property="control_owner", type="integer", example=0),
 *                 @OA\Property(property="control_class", type="integer", example=0),
 *                 @OA\Property(property="control_phase", type="integer", example=0),
 *                 @OA\Property(property="control_number", type="string"),
 *                 @OA\Property(property="control_current_maturity", type="integer", example=0),
 *                 @OA\Property(property="control_desired_maturity", type="integer", example=0),
 *                 @OA\Property(property="control_priority", type="integer", example=0),
 *                 @OA\Property(property="control_status", type="integer", example=1),
 *                 @OA\Property(property="family", type="integer", example=0),
 *                 @OA\Property(property="mitigation_percent", type="integer", example=0)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Control created successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=201),
 *             @OA\Property(property="message", type="string", example="Control created successfully."),
 *             @OA\Property(property="data", type="object", @OA\Property(property="id", type="integer", example=10))
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to add controls.")
 * )
 */
class OpenApiCreateControl {}

/**
 * @OA\Patch(
 *     path="/governance/controls/{id}",
 *     summary="Update a control by ID",
 *     operationId="updateControlById",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the control to update.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="short_name", type="string"),
 *                 @OA\Property(property="long_name", type="string"),
 *                 @OA\Property(property="description", type="string"),
 *                 @OA\Property(property="supplemental_guidance", type="string"),
 *                 @OA\Property(property="control_owner", type="integer"),
 *                 @OA\Property(property="control_class", type="integer"),
 *                 @OA\Property(property="control_phase", type="integer"),
 *                 @OA\Property(property="control_number", type="string"),
 *                 @OA\Property(property="control_current_maturity", type="integer"),
 *                 @OA\Property(property="control_desired_maturity", type="integer"),
 *                 @OA\Property(property="control_priority", type="integer"),
 *                 @OA\Property(property="control_status", type="integer"),
 *                 @OA\Property(property="family", type="integer"),
 *                 @OA\Property(property="mitigation_percent", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Control updated successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to modify controls."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a control with the specified id.")
 * )
 */
class OpenApiUpdateControlById {}

/**
 * @OA\Delete(
 *     path="/governance/controls/{id}",
 *     summary="Delete a control by ID",
 *     operationId="deleteControlById",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the control to delete.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Control deleted successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to delete controls."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a control with the specified id.")
 * )
 */
class OpenApiDeleteControlById {}

// GOVERNANCE (LEGACY)

/**
 * @OA\Get(
 *     path="/governance/frameworks",
 *     summary="List control frameworks in SimpleRisk",
 *     operationId="governanceFrameworks",
 *     tags={"governance_crud"},
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
 *     tags={"governance_crud"},
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

/**
 * @OA\Get(
 *     path="/governance/frameworks/treegrid",
 *     summary="Get control frameworks as a treegrid structure",
 *     description="Get control frameworks as a treegrid structure with parent-child relationships.",
 *     operationId="frameworksTreegrid",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Use a status of 1 for enabled or 2 for disabled.",
 *         required=true,
 *         @OA\Schema(type="integer", enum={1, 2})
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of treegrid row objects representing the framework hierarchy",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
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
class OpenApiFrameworksTreegrid {}

/**
 * @OA\Post(
 *     path="/governance/documents/controls",
 *     summary="Get document-to-control mappings in DataTables format",
 *     description="Get document-to-control mappings in DataTables format.",
 *     operationId="documentsToControlsDatatable",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", description="DataTables draw counter", example=1),
 *             @OA\Property(property="start", type="integer", description="Paging first record indicator", example=0),
 *             @OA\Property(property="length", type="integer", description="Number of records to display", example=10),
 *             @OA\Property(property="columns", type="array", description="Column definitions", @OA\Items(type="object", additionalProperties=true)),
 *             @OA\Property(property="order", type="array", description="Column ordering", @OA\Items(type="object", additionalProperties=true)),
 *             @OA\Property(property="search", type="object", description="Global search value", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="DataTables response with document-to-control mapping data",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=100),
 *             @OA\Property(property="recordsFiltered", type="integer", example=100),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object", additionalProperties=true))
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
class OpenApiDocumentsToControlsDatatable {}

/**
 * @OA\Post(
 *     path="/governance/save_custom_documents_to_controls_display_settings",
 *     summary="Save custom column display settings for the documents-to-controls view",
 *     description="Save custom column display settings for the documents-to-controls view.",
 *     operationId="saveCustomDocumentsToControlsDisplaySettings",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="document_columns", type="array", description="List of document column identifiers to display", @OA\Items(type="string")),
 *             @OA\Property(property="control_columns", type="array", description="List of control column identifiers to display", @OA\Items(type="string")),
 *             @OA\Property(property="matching_columns", type="array", description="List of matching column identifiers to display", @OA\Items(type="string"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Display settings saved successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Display settings saved successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error saving display settings",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to save display settings"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiSaveCustomDocumentsToControlsDisplaySettings {}

/**
 * @OA\Get(
 *     path="/governance/tabular_documents",
 *     summary="Get governance documents in a tabular format",
 *     description="Get governance documents in a tabular format.",
 *     operationId="tabularDocuments",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Array of document objects in tabular format",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
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
class OpenApiTabularDocuments {}

/**
 * @OA\Post(
 *     path="/governance/update_framework_status",
 *     summary="Enable or disable a control framework",
 *     description="Enable or disable a control framework.",
 *     operationId="updateFrameworkStatus",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"framework_id", "status"},
 *             @OA\Property(property="framework_id", type="integer", description="The ID of the framework to update", example=1),
 *             @OA\Property(property="status", type="integer", description="Use 1 for enabled or 2 for disabled", enum={1, 2}, example=1)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Framework status updated successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Framework status updated successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error updating framework status",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to update framework status"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiUpdateFrameworkStatus {}

/**
 * @OA\Post(
 *     path="/governance/update_framework_parent",
 *     summary="Set the parent framework of a control framework",
 *     description="Set the parent framework of a control framework.",
 *     operationId="updateFrameworkParent",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"framework_id", "parent_id"},
 *             @OA\Property(property="framework_id", type="integer", description="The ID of the framework to update", example=1),
 *             @OA\Property(property="parent_id", type="integer", description="The ID of the parent framework. Use 0 for no parent.", example=0)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Framework parent updated successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Framework parent updated successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error updating framework parent",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to update framework parent"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiUpdateFrameworkParent {}

/**
 * @OA\Get(
 *     path="/governance/parent_frameworks_dropdown",
 *     summary="Get a list of frameworks for use as parent framework options in a dropdown",
 *     description="Get a list of frameworks for use as parent framework options in a dropdown.",
 *     operationId="parentFrameworksDropdown",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="HTML select options or array of framework options for the parent dropdown",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiParentFrameworksDropdown {}

/**
 * @OA\Get(
 *     path="/governance/selected_parent_frameworks_dropdown",
 *     summary="Get the currently selected parent framework dropdown option for a given framework",
 *     description="Get the currently selected parent framework dropdown option for a given framework.",
 *     operationId="selectedParentFrameworksDropdown",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="framework_id",
 *         in="query",
 *         description="The ID of the framework to retrieve the selected parent option for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="HTML option element representing the currently selected parent framework",
 *         @OA\JsonContent(
 *             type="object",
 *             additionalProperties=true
 *         )
 *     )
 * )
 */
class OpenApiSelectedParentFrameworksDropdown {}

/**
 * @OA\Get(
 *     path="/governance/control",
 *     summary="Get details for a single control",
 *     description="Get details for a single control.",
 *     operationId="getControl",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="control_id",
 *         in="query",
 *         description="The ID of the control to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Control object with all fields",
 *         @OA\JsonContent(
 *             type="object",
 *             additionalProperties=true
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
class OpenApiGetControl {}

/**
 * @OA\Get(
 *     path="/governance/framework",
 *     summary="Get details for a single framework",
 *     description="Get details for a single framework.",
 *     operationId="getFramework",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="framework_id",
 *         in="query",
 *         description="The ID of the framework to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Framework object",
 *         @OA\JsonContent(
 *             type="object",
 *             additionalProperties=true
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
class OpenApiGetFramework {}

/**
 * @OA\Post(
 *     path="/governance/update_framework",
 *     summary="Update an existing control framework",
 *     description="Update an existing control framework.",
 *     operationId="updateFramework",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"framework_id"},
 *             @OA\Property(property="framework_id", type="integer", description="The ID of the framework to update", example=1),
 *             @OA\Property(property="name", type="string", description="The name of the framework", example="NIST CSF"),
 *             @OA\Property(property="description", type="string", description="A description of the framework"),
 *             @OA\Property(property="status", type="integer", description="Use 1 for enabled or 2 for disabled", enum={1, 2}, example=1),
 *             @OA\Property(property="parent", type="integer", description="The ID of the parent framework. Use 0 for no parent.", example=0)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Framework updated successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Framework updated successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error updating framework",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to update framework"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiUpdateFramework {}

/**
 * @OA\Get(
 *     path="/governance/parent_documents_dropdown",
 *     summary="Get a list of documents for use as parent document options in a dropdown",
 *     description="Get a list of documents for use as parent document options in a dropdown.",
 *     operationId="parentDocumentsDropdown",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="HTML select options for the parent document dropdown",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiParentDocumentsDropdown {}

/**
 * @OA\Get(
 *     path="/governance/document",
 *     summary="Get details for a single document",
 *     description="Get details for a single document.",
 *     operationId="getDocument",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="document_id",
 *         in="query",
 *         description="The ID of the document to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Document object",
 *         @OA\JsonContent(
 *             type="object",
 *             additionalProperties=true
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
class OpenApiGetDocument {}

/**
 * @OA\Get(
 *     path="/governance/selected_parent_documents_dropdown",
 *     summary="Get the currently selected parent document dropdown for a given document",
 *     description="Get the currently selected parent document dropdown for a given document.",
 *     operationId="selectedParentDocumentsDropdown",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="document_id",
 *         in="query",
 *         description="The ID of the document to retrieve the selected parent option for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="HTML option element representing the currently selected parent document",
 *         @OA\JsonContent(
 *             type="object",
 *             additionalProperties=true
 *         )
 *     )
 * )
 */
class OpenApiSelectedParentDocumentsDropdown {}

/**
 * @OA\Get(
 *     path="/governance/related_controls_by_framework_ids",
 *     summary="Get controls related to one or more frameworks",
 *     description="Get controls related to one or more frameworks.",
 *     operationId="relatedControlsByFrameworkIds",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="framework_ids",
 *         in="query",
 *         description="One or more framework IDs to retrieve related controls for.",
 *         required=true,
 *         @OA\Schema(type="array", @OA\Items(type="integer"))
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of control objects related to the specified frameworks",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiRelatedControlsByFrameworkIds {}

/**
 * @OA\Get(
 *     path="/governance/rebuild_control_filters",
 *     summary="Get control filter options based on selected frameworks",
 *     description="Get control filter options based on selected frameworks.",
 *     operationId="rebuildControlFilters",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="framework_ids",
 *         in="query",
 *         description="One or more framework IDs to build filter options for.",
 *         required=false,
 *         @OA\Schema(type="array", @OA\Items(type="integer"))
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Filter HTML or JSON options based on selected frameworks",
 *         @OA\JsonContent(
 *             type="object",
 *             additionalProperties=true
 *         )
 *     )
 * )
 */
class OpenApiRebuildControlFilters {}

/**
 * @OA\Post(
 *     path="/governance/add_control",
 *     summary="Add a new control",
 *     description="Add a new control.",
 *     operationId="addControl",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"short_name"},
 *             @OA\Property(property="short_name", type="string", description="Short name for the control", example="AC-1"),
 *             @OA\Property(property="long_name", type="string", description="Full descriptive name for the control"),
 *             @OA\Property(property="description", type="string", description="Description of the control"),
 *             @OA\Property(property="supplemental_guidance", type="string", description="Supplemental guidance for implementing the control"),
 *             @OA\Property(property="framework_ids", type="array", description="IDs of frameworks this control belongs to", @OA\Items(type="integer")),
 *             @OA\Property(property="control_number", type="string", description="Control reference number", example="AC-1"),
 *             @OA\Property(property="control_class", type="integer", description="ID of the control class"),
 *             @OA\Property(property="control_phase", type="integer", description="ID of the control phase"),
 *             @OA\Property(property="control_owner", type="integer", description="User ID of the control owner"),
 *             @OA\Property(property="control_priority", type="integer", description="ID of the control priority"),
 *             @OA\Property(property="family", type="integer", description="ID of the control family"),
 *             @OA\Property(property="mitigation_percent", type="integer", description="Mitigation percentage (0-100)", example=50)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Control added successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Control added successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error adding control",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to add control"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiAddControl {}

/**
 * @OA\Post(
 *     path="/governance/update_control",
 *     summary="Update an existing control",
 *     description="Update an existing control.",
 *     operationId="updateControl",
 *     tags={"governance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"control_id"},
 *             @OA\Property(property="control_id", type="integer", description="The ID of the control to update", example=1),
 *             @OA\Property(property="short_name", type="string", description="Short name for the control", example="AC-1"),
 *             @OA\Property(property="long_name", type="string", description="Full descriptive name for the control"),
 *             @OA\Property(property="description", type="string", description="Description of the control"),
 *             @OA\Property(property="supplemental_guidance", type="string", description="Supplemental guidance for implementing the control"),
 *             @OA\Property(property="framework_ids", type="array", description="IDs of frameworks this control belongs to", @OA\Items(type="integer")),
 *             @OA\Property(property="control_number", type="string", description="Control reference number", example="AC-1"),
 *             @OA\Property(property="control_class", type="integer", description="ID of the control class"),
 *             @OA\Property(property="control_phase", type="integer", description="ID of the control phase"),
 *             @OA\Property(property="control_owner", type="integer", description="User ID of the control owner"),
 *             @OA\Property(property="control_priority", type="integer", description="ID of the control priority"),
 *             @OA\Property(property="family", type="integer", description="ID of the control family"),
 *             @OA\Property(property="mitigation_percent", type="integer", description="Mitigation percentage (0-100)", example=50)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Control updated successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Control updated successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error updating control",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to update control"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiUpdateControl {}

?>
