<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// COMPLIANCE (CRUD)

/**
 * @OA\Get(
 *     path="/compliance/tests/{id}",
 *     summary="Get a test by ID",
 *     operationId="getTestById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the test to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Test retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="message", type="string", example="Test retrieved successfully."),
 *             @OA\Property(property="data", type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have compliance permission or does not have access to this test."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a test with the specified id.")
 * )
 */
class OpenApiGetTestById {}

/**
 * @OA\Post(
 *     path="/compliance/tests",
 *     summary="Create a new test",
 *     operationId="createTest",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"name","framework_control_id"},
 *                 @OA\Property(property="name", type="string", description="The name of the test."),
 *                 @OA\Property(property="framework_control_id", type="integer", description="The ID of the associated framework control."),
 *                 @OA\Property(property="tester", type="integer", description="User ID of the tester.", example=0),
 *                 @OA\Property(property="test_frequency", type="integer", description="Test frequency in days.", example=0),
 *                 @OA\Property(property="objective", type="string", description="The test objective."),
 *                 @OA\Property(property="test_steps", type="string", description="Steps to perform the test."),
 *                 @OA\Property(property="approximate_time", type="integer", description="Approximate time to complete in minutes.", example=0),
 *                 @OA\Property(property="expected_results", type="string", description="Expected results of the test."),
 *                 @OA\Property(property="additional_stakeholders", type="string", description="Comma-separated user IDs of additional stakeholders."),
 *                 @OA\Property(property="last_date", type="string", format="date", description="Last test date (YYYY-MM-DD)."),
 *                 @OA\Property(property="next_date", type="string", format="date", description="Next scheduled test date (YYYY-MM-DD)."),
 *                 @OA\Property(property="audit_initiation_offset", type="integer", description="Days before next_date to initiate the audit.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Test created successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=201),
 *             @OA\Property(property="message", type="string", example="Test created successfully."),
 *             @OA\Property(property="data", type="object", @OA\Property(property="id", type="integer", example=5))
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to define tests.")
 * )
 */
class OpenApiCreateTest {}

/**
 * @OA\Patch(
 *     path="/compliance/tests/{id}",
 *     summary="Update a test by ID",
 *     operationId="updateTestById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the test to update.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="name", type="string"),
 *                 @OA\Property(property="framework_control_id", type="integer"),
 *                 @OA\Property(property="tester", type="integer"),
 *                 @OA\Property(property="test_frequency", type="integer"),
 *                 @OA\Property(property="objective", type="string"),
 *                 @OA\Property(property="test_steps", type="string"),
 *                 @OA\Property(property="approximate_time", type="integer"),
 *                 @OA\Property(property="expected_results", type="string"),
 *                 @OA\Property(property="additional_stakeholders", type="string"),
 *                 @OA\Property(property="last_date", type="string", format="date"),
 *                 @OA\Property(property="next_date", type="string", format="date"),
 *                 @OA\Property(property="audit_initiation_offset", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Test updated successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to edit tests or does not have access to this test."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a test with the specified id.")
 * )
 */
class OpenApiUpdateTestById {}

/**
 * @OA\Delete(
 *     path="/compliance/tests/{id}",
 *     summary="Delete a test by ID",
 *     operationId="deleteTestById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the test to delete.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Test deleted successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to delete tests or does not have access to this test."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a test with the specified id.")
 * )
 */
class OpenApiDeleteTestById {}

/**
 * @OA\Get(
 *     path="/compliance/audits/{id}",
 *     summary="Get an audit by ID",
 *     operationId="getAuditById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the audit to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Audit retrieved successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="message", type="string", example="Audit retrieved successfully."),
 *             @OA\Property(property="data", type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have compliance permission or does not have access to this audit."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find an audit with the specified id.")
 * )
 */
class OpenApiGetAuditById {}

/**
 * @OA\Post(
 *     path="/compliance/audits",
 *     summary="Initiate a new audit from a test",
 *     operationId="createAudit",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"test_id"},
 *                 @OA\Property(property="test_id", type="integer", description="The ID of the test to initiate an audit for.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Audit initiated successfully.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=201),
 *             @OA\Property(property="message", type="string", example="Audit initiated successfully."),
 *             @OA\Property(property="data", type="object", @OA\Property(property="test_name", type="string", example="My Test"))
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to initiate audits or does not have access to this test."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a test with the specified id.")
 * )
 */
class OpenApiCreateAudit {}

/**
 * @OA\Patch(
 *     path="/compliance/audits/{id}",
 *     summary="Update an audit result by ID",
 *     operationId="updateAuditById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the audit to update.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 @OA\Property(property="status", type="integer", description="The audit status ID."),
 *                 @OA\Property(property="test_result", type="string", description="The test result (e.g. Pass, Fail)."),
 *                 @OA\Property(property="tester", type="integer", description="User ID of the tester."),
 *                 @OA\Property(property="test_date", type="string", format="date", description="Date the test was performed (YYYY-MM-DD)."),
 *                 @OA\Property(property="summary", type="string", description="Summary of findings.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Audit updated successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to modify audits or does not have access to this audit."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find an audit with the specified id.")
 * )
 */
class OpenApiUpdateAuditById {}

/**
 * @OA\Delete(
 *     path="/compliance/audits/{id}",
 *     summary="Delete an audit by ID",
 *     operationId="deleteAuditById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the audit to delete.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Audit deleted successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to delete audits or does not have access to this audit."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find an audit with the specified id.")
 * )
 */
class OpenApiDeleteAuditById {}

// COMPLIANCE (LEGACY)

/**
 * @OA\Get(
 *     path="/compliance/tests",
 *     summary="List audit tests in SimpleRisk",
 *     operationId="complianceTests",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the test you would like to retrieve details for. Will return all tests if no id is specified.",
 *       required=false,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk audit tests",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find an audit test with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiComplianceTests {}

/**
 * @OA\Get(
 *     path="/compliance/tests/associations",
 *     summary="List test associations in SimpleRisk",
 *     operationId="testssAssociations",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *       parameter="id",
 *       in="query",
 *       name="id",
 *       description="The id of the test you would like to retrieve associations for.",
 *       required=true,
 *       @OA\Schema(
 *         type="integer",
 *       ),
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk test associations",
 *     ),
 *     @OA\Response(
 *       response=204,
 *       description="NO CONTENT: Unable to find a test with the specified id.",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not have the required permission to perform this action.",
 *     ),
 * )
 */
class OpenApiTestsAssociations {}

/**
 * @OA\Get(
 *     path="/compliance/tests/tags",
 *     summary="List compliance test tags",
 *     operationId="complianceTestTagsGet",
 *     tags={"compliance"},
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
 *       description="SimpleRisk compliance test tags",
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
class OpenApiComplianceTestTagsGet {}

/**
 * @OA\Get(
 *     path="/compliance/audits/tags",
 *     summary="List compliance audit tags",
 *     operationId="complianceAuditTagsGet",
 *     tags={"compliance"},
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
 *       description="SimpleRisk compliance audit tags",
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
class OpenApiComplianceAuditTagsGet {}

/**
 * @OA\Post(
 *     path="/compliance/define_tests",
 *     summary="Get a DataTables-formatted list of defined tests for a control",
 *     description="Get a DataTables-formatted list of defined tests for a control.",
 *     operationId="defineTests",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"control_id"},
 *             @OA\Property(property="draw", type="integer", description="DataTables draw counter", example=1),
 *             @OA\Property(property="start", type="integer", description="Paging first record indicator", example=0),
 *             @OA\Property(property="length", type="integer", description="Number of records to display", example=10),
 *             @OA\Property(property="control_id", type="integer", description="The ID of the control to retrieve tests for", example=1),
 *             @OA\Property(property="columns", type="array", description="Column definitions", @OA\Items(type="object", additionalProperties=true)),
 *             @OA\Property(property="order", type="array", description="Column ordering", @OA\Items(type="object", additionalProperties=true))
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="DataTables response with defined tests",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=100),
 *             @OA\Property(property="recordsFiltered", type="integer", example=100),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object", additionalProperties=true))
 *         )
 *     )
 * )
 */
class OpenApiDefineTests {}

/**
 * @OA\Get(
 *     path="/compliance/test",
 *     summary="Get details for a single compliance test",
 *     description="Get details for a single compliance test.",
 *     operationId="getTest",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="test_id",
 *         in="query",
 *         description="The ID of the test to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Test details object",
 *         @OA\JsonContent(
 *             type="object",
 *             additionalProperties=true
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="User does not have compliance permissions",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="No permission for compliance"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiGetTest {}

/**
 * @OA\Get(
 *     path="/compliance/initiate_audits",
 *     summary="Get a list of tests available to be initiated as audits",
 *     description="Get a list of tests available to be initiated as audits.",
 *     operationId="initiateAudits",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Array of test objects available for audit initiation",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="User does not have compliance permissions",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="No permission for compliance"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiInitiateAudits {}

/**
 * @OA\Post(
 *     path="/compliance/active_audits",
 *     summary="Get active test audits in DataTables format",
 *     description="Get active test audits in DataTables format.",
 *     operationId="activeAudits",
 *     tags={"compliance"},
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
 *             @OA\Property(property="filter", type="object", description="Additional filter parameters", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="DataTables response with active audits",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=100),
 *             @OA\Property(property="recordsFiltered", type="integer", example=100),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object", additionalProperties=true))
 *         )
 *     )
 * )
 */
class OpenApiActiveAudits {}

/**
 * @OA\Post(
 *     path="/compliance/save_audit_comment",
 *     summary="Save a comment on a test audit",
 *     description="Save a comment on a test audit.",
 *     operationId="saveAuditComment",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"audit_id", "comment"},
 *             @OA\Property(property="audit_id", type="integer", description="The ID of the audit to comment on", example=1),
 *             @OA\Property(property="comment", type="string", description="The comment text to save"),
 *             @OA\Property(property="test_result", type="string", description="The result of the test (e.g. pass, fail)")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Audit comment saved successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Audit comment saved successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error saving audit comment",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to save audit comment"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiSaveAuditComment {}

/**
 * @OA\Post(
 *     path="/compliance/past_audits",
 *     summary="Get completed/past test audits in DataTables format",
 *     description="Get completed/past test audits in DataTables format.",
 *     operationId="pastAudits",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", description="DataTables draw counter", example=1),
 *             @OA\Property(property="start", type="integer", description="Paging first record indicator", example=0),
 *             @OA\Property(property="length", type="integer", description="Number of records to display", example=10),
 *             @OA\Property(property="columns", type="array", description="Column definitions", @OA\Items(type="object", additionalProperties=true)),
 *             @OA\Property(property="order", type="array", description="Column ordering", @OA\Items(type="object", additionalProperties=true))
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="DataTables response with past audits",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=100),
 *             @OA\Property(property="recordsFiltered", type="integer", example=100),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object", additionalProperties=true))
 *         )
 *     )
 * )
 */
class OpenApiPastAudits {}

/**
 * @OA\Post(
 *     path="/compliance/reopen_audit",
 *     summary="Reopen a closed test audit",
 *     description="Reopen a closed test audit.",
 *     operationId="reopenAudit",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"audit_id"},
 *             @OA\Property(property="audit_id", type="integer", description="The ID of the audit to reopen", example=1)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Audit reopened successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Audit reopened successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error reopening audit",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to reopen audit"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiReopenAudit {}

/**
 * @OA\Post(
 *     path="/compliance/audit_initiation/initiate",
 *     summary="Initiate audits for one or more framework control tests",
 *     description="Initiate audits for one or more framework control tests.",
 *     operationId="initiateAudit",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"test_ids"},
 *             @OA\Property(property="test_ids", type="array", description="IDs of the tests to initiate audits for", @OA\Items(type="integer")),
 *             @OA\Property(property="frequency", type="string", description="Frequency at which the audit should recur")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Audits initiated successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Audits initiated successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error initiating audits",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to initiate audits"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiInitiateAudit {}

/**
 * @OA\Get(
 *     path="/compliance/audit_timeline",
 *     summary="Get the audit timeline data for calendar display",
 *     description="Get the audit timeline data for calendar display.",
 *     operationId="auditTimeline",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Array of audit timeline event objects",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object", additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="User does not have compliance permissions",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="No permission for compliance"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiAuditTimeline {}

/**
 * @OA\Post(
 *     path="/compliance/delete_audit",
 *     summary="Delete a test audit",
 *     description="Delete a test audit.",
 *     operationId="deleteAudit",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"audit_id"},
 *             @OA\Property(property="audit_id", type="integer", description="The ID of the audit to delete", example=1)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Audit deleted successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Audit deleted successfully"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error deleting audit",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Unable to delete audit"),
 *             @OA\Property(property="data", type="object", nullable=true, additionalProperties=true)
 *         )
 *     )
 * )
 */
class OpenApiDeleteAudit {}

?>
