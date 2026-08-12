<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// COMPLIANCE (CRUD)

/**
 * @OA\Get(
 *     path="/compliance/tests/{id}",
 *     summary="Get a test by ID",
 *     description="Returns the test, including controls (array of mapped control IDs, via the test_control_map junction -- a test maps to N controls) and control_names (control ID => short_name map for every mapped control).",
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
 *     description="Create a compliance test. Accepts the legacy interval fields (test_frequency, last_date, next_date) as well as the calendar-cadence schedule fields (schedule_type, cadence_unit, cadence_interval, cadence_anchor_date, schedule_exceptions) -- see updateTestSchedule (POST /compliance/update_test) for the same fields on an existing test. schedule_type may be omitted entirely (no cadence validation is applied and the legacy interval fields drive scheduling, same as before these fields existed); when supplied it must be one of manual/interval/calendar, and calendar requires a complete cadence with an anchor date that is today or later. A test maps to one or more controls via controls[]; a lone framework_control_id (no controls[] submitted) is accepted as a back-compat single-element set. At least one of controls[]/framework_control_id is required. The response's data.controls/data.control_names reflect the resolved set (framework_control_id is kept in sync as min(controls)).",
 *     operationId="createTest",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"name"},
 *                 @OA\Property(property="name", type="string", description="The name of the test."),
 *                 @OA\Property(property="framework_control_id", type="integer", description="Back-compat: the ID of a single associated framework control. Accepted when controls[] is omitted (normalized to a one-element controls set); ignored if controls[] is also submitted."),
 *                 @OA\Property(
 *                     property="controls",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="Control IDs this test covers (1+). Replaces the single framework_control_id; a lone framework_control_id is still accepted as a single-element set when controls[] is omitted. At least one of controls[]/framework_control_id is required."
 *                 ),
 *                 @OA\Property(property="tester", type="integer", description="User ID of the tester.", example=0),
 *                 @OA\Property(property="test_frequency", type="integer", description="Legacy interval test frequency in days.", example=0),
 *                 @OA\Property(property="objective", type="string", description="The test objective."),
 *                 @OA\Property(property="test_steps", type="string", description="Steps to perform the test."),
 *                 @OA\Property(property="approximate_time", type="integer", description="Approximate time to complete in minutes.", example=0),
 *                 @OA\Property(property="expected_results", type="string", description="Expected results of the test."),
 *                 @OA\Property(property="additional_stakeholders", type="string", description="Comma-separated user IDs of additional stakeholders."),
 *                 @OA\Property(property="teams", type="array", @OA\Items(type="integer"), description="Team IDs assigned to the test."),
 *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), description="Tags assigned to the test."),
 *                 @OA\Property(property="last_date", type="string", format="date", description="Last test date. Accepted as ISO YYYY-MM-DD or in this instance's configured display date format (Admin > Settings > Default Date Format, e.g. MM/DD/YYYY); stored as YYYY-MM-DD either way. A value that is neither is rejected with 400 rather than stored as a zero date."),
 *                 @OA\Property(property="next_date", type="string", format="date", description="Next scheduled test date; ignored (computed by the cadence engine) when schedule_type is calendar. Accepted as ISO YYYY-MM-DD or in this instance's configured display date format; a value that is neither is rejected with 400."),
 *                 @OA\Property(property="audit_initiation_offset", type="integer", description="Days before next_date to initiate the audit. Optional for Interval and Calendar schedule_type; ignored (forced to null) when schedule_type is manual."),
 *                 @OA\Property(property="schedule_type", type="string", enum={"manual","interval","calendar"}, description="The scheduling mode for this test. Omit to skip cadence validation entirely (legacy interval-only behavior)."),
 *                 @OA\Property(property="cadence_unit", type="string", enum={"day","week","month","year"}, description="Calendar cadence recurrence unit. Required when schedule_type is calendar."),
 *                 @OA\Property(property="cadence_interval", type="integer", description="Calendar cadence recurrence interval. Required when schedule_type is calendar."),
 *                 @OA\Property(property="cadence_anchor_date", type="string", format="date", description="Calendar cadence anchor date, must be today or later. Accepts ISO (YYYY-MM-DD) or this instance's configured display format. Required when schedule_type is calendar."),
 *                 @OA\Property(
 *                     property="schedule_exceptions",
 *                     type="array",
 *                     description="Per-occurrence overrides/skips for the calendar schedule. May be supplied as a JSON-encoded string or a native array.",
 *                     @OA\Items(
 *                         type="object",
 *                         required={"occurrence_date"},
 *                         @OA\Property(property="occurrence_date", type="string", format="date", description="The natural (un-overridden) occurrence date this exception applies to."),
 *                         @OA\Property(property="override_date", type="string", format="date", nullable=true, description="Replacement date for this occurrence, or null to keep the natural date."),
 *                         @OA\Property(property="skipped", type="boolean", description="True to skip this occurrence entirely.")
 *                     )
 *                 ),
 *                 @OA\Property(property="test_method", type="string", enum={"inquiry","observation","inspection","reperformance"}, nullable=true, description="How the test evidence is gathered."),
 *                 @OA\Property(property="sample", type="string", description="The sample selection/methodology used for the test."),
 *                 @OA\Property(property="required_evidence", type="string", description="The evidence required to satisfy the test."),
 *                 @OA\Property(
 *                     property="approvers",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="User IDs authorized to approve results of this test. Segregation-of-duties: the tester may not also be listed as an approver."
 *                 )
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
 *     @OA\Response(response=400, description="BAD REQUEST: Validation failed (missing name, no controls[]/framework_control_id resolving to at least one control, invalid schedule_type, incomplete calendar cadence, a past anchor date, an invalid test_method, an unparseable last_date/next_date/cadence_anchor_date, or the tester listed among approvers)."),
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
 *                 @OA\Property(property="framework_control_id", type="integer", description="Ignored on update: controls[] is the only way to change a test's control mapping on a PATCH. Omit controls[] to leave the currently-persisted control set untouched; a lone framework_control_id here does NOT change it (this prevents a GET->PATCH round-trip, which returns the scalar but not controls[], from silently collapsing a multi-control test to its min control)."),
 *                 @OA\Property(
 *                     property="controls",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="Control IDs this test covers (1+). Omit entirely to leave the currently-persisted controls untouched (a partial PATCH never silently clears them); supply to replace the full set. framework_control_id is kept in sync as min(controls)."
 *                 ),
 *                 @OA\Property(property="tester", type="integer"),
 *                 @OA\Property(property="test_frequency", type="integer"),
 *                 @OA\Property(property="objective", type="string"),
 *                 @OA\Property(property="test_steps", type="string"),
 *                 @OA\Property(property="approximate_time", type="integer"),
 *                 @OA\Property(property="expected_results", type="string"),
 *                 @OA\Property(property="additional_stakeholders", type="string"),
 *                 @OA\Property(property="last_date", type="string", format="date", description="Accepted as ISO YYYY-MM-DD or in this instance's configured display date format (e.g. MM/DD/YYYY); stored as YYYY-MM-DD either way. Omit to leave the existing value untouched; submit empty to clear it. A value that is neither format is rejected with 400 rather than stored as a zero date."),
 *                 @OA\Property(property="next_date", type="string", format="date", description="Accepted as ISO YYYY-MM-DD or in this instance's configured display date format; stored as YYYY-MM-DD either way. Omit to leave the existing value untouched (or, for a calendar schedule, to let the cadence engine recompute it); submit empty to clear it. A value that is neither format is rejected with 400."),
 *                 @OA\Property(property="audit_initiation_offset", type="integer"),
 *                 @OA\Property(property="test_method", type="string", enum={"inquiry","observation","inspection","reperformance"}, nullable=true, description="How the test evidence is gathered. Omit to leave the existing value untouched."),
 *                 @OA\Property(property="sample", type="string", description="The sample selection/methodology used for the test. Omit to leave the existing value untouched."),
 *                 @OA\Property(property="required_evidence", type="string", description="The evidence required to satisfy the test. Omit to leave the existing value untouched."),
 *                 @OA\Property(
 *                     property="approvers",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="User IDs authorized to approve results of this test. Omit entirely to leave the currently-persisted approvers untouched (a partial PATCH never silently clears them); supply (including []) to replace the full list. Segregation-of-duties: the effective tester (submitted, or currently-persisted if tester is omitted) may not also be listed as an approver."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Test updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Invalid test_method, an unparseable last_date/next_date, a freshly-submitted controls[] resolving to zero controls, or the effective tester is listed among approvers."),
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
 * @OA\Post(
 *     path="/compliance/tests/{id}/retire",
 *     summary="Retire a test (soft-hide without deleting it or its audit history)",
 *     operationId="retireTestById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the test to retire.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Test retired successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have edit_tests or delete_tests permission, or does not have access to this test."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a test with the specified id.")
 * )
 */
class OpenApiRetireTestById {}

/**
 * @OA\Post(
 *     path="/compliance/tests/{id}/restore",
 *     summary="Restore a retired test to the active list",
 *     operationId="restoreTestById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the test to restore.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Test restored successfully."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have edit_tests or delete_tests permission, or does not have access to this test."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a test with the specified id.")
 * )
 */
class OpenApiRestoreTestById {}

/**
 * @OA\Get(
 *     path="/compliance/tests/{id}/audits",
 *     summary="Get the audit history (every run, newest first) for one test",
 *     operationId="getTestAuditHistoryById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the test whose audit history to retrieve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Test audit history retrieved successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="Test audit history retrieved successfully."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="test_id", type="integer", example=34),
 *                 @OA\Property(property="test_name", type="string", example="Quarterly backup restore test"),
 *                 @OA\Property(
 *                     property="audits",
 *                     type="array",
 *                     description="Every audit for the test, newest first, including any currently-open run.",
 *                     @OA\Items(
 *                         @OA\Property(property="audit_id", type="integer", example=812),
 *                         @OA\Property(property="date", type="string", description="The date the run happened, in the instance's date format.", example="06/30/2026"),
 *                         @OA\Property(property="result", type="string", nullable=true, description="The recorded result, or null when the run has none yet.", example="Pass"),
 *                         @OA\Property(property="result_family", type="string", enum={"success","danger","warning","neutral"}, example="success"),
 *                         @OA\Property(property="tester_name", type="string", example="Josh Sokol"),
 *                         @OA\Property(property="status_name", type="string", example="Closed"),
 *                         @OA\Property(property="approval_state", type="string", enum={"none","pending","approved","rejected"}, example="approved"),
 *                         @OA\Property(property="link", type="string", description="Deep link to the audit: the read-only view for a truly-closed audit, the editor otherwise.", example="../compliance/view_test.php?id=812")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: You need to specify an id parameter."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have compliance permission, or does not have access to this test."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a test with the specified id.")
 * )
 */
class OpenApiGetTestAuditHistoryById {}

/**
 * @OA\Delete(
 *     path="/compliance/tests/{id}/controls/{control_id}",
 *     summary="Remove a test from one control, without deleting the test",
 *     description="Deletes a single (test, control) mapping. A test shared across several controls stays on the others. Removing the test's last control is refused with a 409 -- a test must belong to at least one control, so retire or delete is the correct action there.",
 *     operationId="detachTestFromControl",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the test to remove from the control.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="control_id",
 *         in="path",
 *         description="The ID of the control to remove the test from.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Test removed from the control successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="Test removed from the control successfully.")
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: You need to specify an id parameter."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have edit_tests permission, or does not have access to this test."),
 *     @OA\Response(response=404, description="NOT FOUND: No such test, or the test is not mapped to that control."),
 *     @OA\Response(response=409, description="CONFLICT: A test must belong to at least one control.")
 * )
 */
class OpenApiDetachTestFromControl {}

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
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 additionalProperties=true,
 *                 @OA\Property(
 *                     property="audit",
 *                     type="object",
 *                     additionalProperties=true,
 *                     description="The audit record.",
 *                     @OA\Property(
 *                         property="controls",
 *                         type="array",
 *                         @OA\Items(type="integer"),
 *                         description="IDs of every control this audit belongs to. A common test can map to multiple controls; the set is frozen from the snapshot taken when the audit was initiated, so it does not change even if the source test's controls[] is edited afterward. audits.framework_control_id (kept for back-compat) is the minimum id in this set."
 *                     ),
 *                     @OA\Property(
 *                         property="control_names",
 *                         type="object",
 *                         description="Map of control ID to its short_name, for each control in controls.",
 *                         @OA\AdditionalProperties(type="string")
 *                     )
 *                 )
 *             )
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
 *             @OA\Property(property="data", type="object", @OA\Property(property="audit_id", type="integer", nullable=true, description="The id of the newly initiated audit when a single test is initiated; null for framework/control initiation.", example=42))
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
 *                 @OA\Property(property="test_date", type="string", format="date", description="Date the test was performed. Accepted as ISO YYYY-MM-DD or in this instance's configured display date format (Admin > Settings > Default Date Format, e.g. MM/DD/YYYY); stored as YYYY-MM-DD either way. Omit to leave the stored value untouched. A value that is neither format is rejected with 400 rather than stored as a zero date."),
 *                 @OA\Property(property="summary", type="string", description="Summary of findings.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Audit updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Invalid test_result, or an unparseable test_date."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to modify audits or does not have access to this audit."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find an audit with the specified id.")
 * )
 */
class OpenApiUpdateAuditById {}

/**
 * @OA\Post(
 *     path="/compliance/audits/{id}/approve",
 *     summary="Approve a closed-but-pending audit (Phase 3b approval workflow)",
 *     description="Approves an audit that is being held awaiting sign-off (approval_state='pending') because its parent test has one or more configured approvers. Requires the approve_tests permission, that the caller is listed as an approver of the audit's test, that the caller is not also the audit's tester (segregation of duties), and that the audit is currently pending approval.",
 *     operationId="approveAuditById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the audit to approve.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Audit approved successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: No id parameter was specified."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have approve_tests permission, does not have access to this audit, is not a configured approver of this audit's test, or is the audit's own tester."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find an audit with the specified id."),
 *     @OA\Response(response=409, description="CONFLICT: The audit is not currently awaiting approval.")
 * )
 */
class OpenApiApproveAuditById {}

/**
 * @OA\Post(
 *     path="/compliance/audits/{id}/reject",
 *     summary="Reject a closed-but-pending audit (Phase 3b approval workflow)",
 *     description="Rejects an audit that is being held awaiting sign-off, reopening it (status reset to in-progress, approval_state='rejected') and notifying the tester with the required rejection comment. Same gates as approve: approve_tests permission, team access, configured approver, not the tester, and the audit must be currently pending approval.",
 *     operationId="rejectAuditById",
 *     tags={"compliance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="The ID of the audit to reject.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"comment"},
 *                 @OA\Property(property="comment", type="string", description="Required explanation of why the audit close is being rejected. Stored in the approval history and mirrored to the audit's comment thread.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Audit rejected successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: No id parameter was specified, or the comment field was missing/empty."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have approve_tests permission, does not have access to this audit, is not a configured approver of this audit's test, or is the audit's own tester."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find an audit with the specified id."),
 *     @OA\Response(response=409, description="CONFLICT: The audit is not currently awaiting approval.")
 * )
 */
class OpenApiRejectAuditById {}

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
 *       description="SimpleRisk test associations. controls[].test_id is now consistently an integer (previously a string echoing the raw request id). A row whose join target was missing or whose display name resolved empty is silently dropped rather than returned with a null/blank name.",
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
 *     path="/compliance/tests_grid",
 *     summary="Get the Define Tests grid data feed (controls + nested tests)",
 *     description="Returns a page of framework controls (filtered by framework/family/search/coverage/schedule/tag/quick flags), each with its nested list of tests enriched with schedule summary, overdue status, and last audit result. Backs the Define Tests redesign grid.",
 *     operationId="complianceTestsGrid",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="framework", type="array", @OA\Items(type="integer"), description="Framework ids to filter controls by (a control matches if mapped to any of these)."),
 *             @OA\Property(property="family", type="array", @OA\Items(type="integer"), description="Control family ids to filter by."),
 *             @OA\Property(property="search", type="string", description="Free-text search across test name, control number/short name/long name, and mapped framework reference name/text."),
 *             @OA\Property(property="coverage", type="string", enum={"with","all","gaps"}, description="'with' = only controls that have at least one active test; 'gaps' = only controls with zero active tests; 'all' (default) = no coverage filter."),
 *             @OA\Property(property="schedule", type="string", enum={"manual","interval","calendar"}, description="Filter tests to a single schedule_type."),
 *             @OA\Property(property="tag", type="string", description="Filter tests to those carrying this tag."),
 *             @OA\Property(
 *                 property="quick",
 *                 type="object",
 *                 description="Quick-filter toggles.",
 *                 @OA\Property(property="mine", type="boolean", description="Only tests where the current user is the tester."),
 *                 @OA\Property(property="overdue", type="boolean", description="Only tests whose next_date has passed."),
 *                 @OA\Property(property="due_soon", type="boolean", description="Only automated (interval/calendar) tests that are not overdue and whose audit-initiation lead-in window is open (today falls within [next_date - audit_initiation_offset, next_date])."),
 *                 @OA\Property(property="failing", type="boolean", description="Only tests whose most recent audit result is 'Fail'."),
 *                 @OA\Property(property="manual", type="boolean", description="Only tests with schedule_type='manual'."),
 *                 @OA\Property(property="untested", type="boolean", description="Only controls with zero active tests (equivalent to coverage='gaps')."),
 *                 @OA\Property(property="show_retired", type="boolean", description="Include retired tests (excluded by default).")
 *             ),
 *             @OA\Property(property="start", type="integer", description="Paging offset into the filtered control list.", example=0),
 *             @OA\Property(property="length", type="integer", description="Page size; -1 returns every matching control.", example=10)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Paged grid data.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="recordsTotal", type="integer", description="All non-deleted controls, ignoring every filter."),
 *                 @OA\Property(property="recordsFiltered", type="integer", description="Controls matching every filter, before pagination."),
 *                 @OA\Property(
 *                     property="controls",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="id", type="integer"),
 *                         @OA\Property(property="control_number", type="string", nullable=true),
 *                         @OA\Property(property="short_name", type="string"),
 *                         @OA\Property(property="long_name", type="string", nullable=true, description="Added for the Define Tests grid (Task 6) so the control's full display name is still findable/visible via the toolbar search now that the legacy page's dedicated long-name filter field is gone."),
 *                         @OA\Property(property="owner_name", type="string", nullable=true),
 *                         @OA\Property(property="framework_count", type="integer"),
 *                         @OA\Property(property="test_count", type="integer"),
 *                         @OA\Property(
 *                             property="tests",
 *                             type="array",
 *                             @OA\Items(
 *                                 type="object",
 *                                 @OA\Property(property="id", type="integer"),
 *                                 @OA\Property(property="name", type="string"),
 *                                 @OA\Property(property="tester_name", type="string", nullable=true),
 *                                 @OA\Property(property="schedule_type", type="string", enum={"manual","interval","calendar"}, description="Added for the Define Tests grid (Task 6) so the client can render a distinct 'Manual' next-due pill state -- a manual test still carries a next_date, so overdue/due-soon math alone can't distinguish it."),
 *                                 @OA\Property(property="schedule_summary", type="string", example="Every 3 Months"),
 *                                 @OA\Property(property="next_date", type="string", format="date"),
 *                                 @OA\Property(property="overdue", type="boolean"),
 *                                 @OA\Property(property="due_soon", type="boolean", description="Automated test, not overdue, and inside its audit-initiation lead-in window."),
 *                                 @OA\Property(property="last_result", type="string", nullable=true, example="Pass"),
 *                                 @OA\Property(property="last_result_family", type="string", enum={"success","danger","warning","neutral"}),
 *                                 @OA\Property(property="retired", type="boolean"),
 *                                 @OA\Property(property="approximate_time", type="integer"),
 *                                 @OA\Property(property="tags", type="array", @OA\Items(type="string"))
 *                             )
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have compliance permission.")
 * )
 */
class OpenApiComplianceTestsGrid {}

/**
 * @OA\Get(
 *     path="/compliance/control_mappings",
 *     summary="Get the framework mappings for a single control",
 *     description="Returns the control's description plus every framework mapping row (framework name, reference name/text) for the given control. Backs the Define Tests redesign's control-detail (SCF) expand.",
 *     operationId="complianceControlMappings",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="control_id",
 *         in="query",
 *         description="The ID of the control to retrieve mappings for.",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Control description and its framework mappings.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="description", type="string", description="The control's purified description."),
 *                 @OA\Property(
 *                     property="mappings",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="framework_name", type="string"),
 *                         @OA\Property(property="reference_name", type="string"),
 *                         @OA\Property(property="reference_text", type="string", nullable=true)
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: control_id is required."),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have compliance permission."),
 *     @OA\Response(response=404, description="NOT FOUND: Unable to find a control with the specified id.")
 * )
 */
class OpenApiComplianceControlMappings {}

/**
 * @OA\Get(
 *     path="/compliance/control_roster",
 *     summary="Get the lightweight control roster (id/number/name/description, plus family and framework ids)",
 *     description="Returns id, control_number, short_name, family and framework ids for every non-deleted control, with no test/last-result/tag enrichment. Backs the Define Tests test modals' control picker, whose framework and family columns filter client-side from these ids -- deliberately cheap (a plain SELECT plus one mappings query) since it doesn't need the full grid's enrichment/pagination. Framework and family NAMES are not included; the page renders both lists for its own toolbar filters.",
 *     operationId="complianceControlRoster",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Every non-deleted control's id/control_number/short_name, with its family id and the ids of the frameworks it maps into.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="control_number", type="string", nullable=true),
 *                     @OA\Property(property="short_name", type="string"),
 *                     @OA\Property(property="description", type="string", description="The control's description as one line of plain text (tags stripped, whitespace collapsed, capped at 300 characters) for the picker's hover."),
 *                     @OA\Property(property="family", type="integer", description="Family id, or 0 when the control has no family."),
 *                     @OA\Property(
 *                         property="frameworks",
 *                         type="array",
 *                         description="Ids of the frameworks this control maps into; empty when it maps to none.",
 *                         @OA\Items(type="integer")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have compliance permission.")
 * )
 */
class OpenApiComplianceControlRoster {}

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
 * @OA\Post(
 *     path="/compliance/update_test",
 *     summary="Update a compliance test, including its audit cadence schedule",
 *     description="Update a compliance test. Accepts the legacy interval fields (test_frequency, last_date, next_date) as well as the calendar-cadence schedule fields (schedule_type, cadence_unit, cadence_interval, cadence_anchor_date, schedule_exceptions). schedule_exceptions may be omitted (existing exceptions are left untouched) or supplied as a JSON-encoded array (including an empty array, which clears them).",
 *     operationId="updateTestSchedule",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 required={"test_id","name","tester"},
 *                 @OA\Property(property="test_id", type="integer", description="The ID of the test to update."),
 *                 @OA\Property(property="name", type="string", description="The name of the test."),
 *                 @OA\Property(property="tester", type="integer", description="User ID of the tester."),
 *                 @OA\Property(property="test_frequency", type="integer", description="Legacy interval test frequency in days."),
 *                 @OA\Property(property="objective", type="string", description="The test objective."),
 *                 @OA\Property(property="test_steps", type="string", description="Steps to perform the test."),
 *                 @OA\Property(property="approximate_time", type="integer", description="Approximate time to complete in minutes."),
 *                 @OA\Property(property="expected_results", type="string", description="Expected results of the test."),
 *                 @OA\Property(property="additional_stakeholders", type="array", @OA\Items(type="integer"), description="User IDs of additional stakeholders."),
 *                 @OA\Property(property="team", type="array", @OA\Items(type="integer"), description="Team IDs assigned to the test."),
 *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), description="Tags assigned to the test."),
 *                 @OA\Property(property="last_date", type="string", format="date", description="Last test date. Accepts ISO (YYYY-MM-DD) or this instance's configured display format. Omit to keep the stored value; submit an empty string to clear it."),
 *                 @OA\Property(property="next_date", type="string", format="date", description="Next scheduled test date. Accepts ISO (YYYY-MM-DD) or this instance's configured display format. Omitting it or submitting an empty string means 'do not set it explicitly' — but that does NOT guarantee the stored value survives, because this endpoint always resolves next_date from the schedule: when schedule_type is calendar the cadence engine recomputes it, and on the interval path it is recomputed as last_date + test_frequency days whenever this request supplies a non-empty last_date and a test_frequency above 0. The stored value is left alone only when there is nothing to project from (no last_date in this request, or test_frequency 0) and none was submitted."),
 *                 @OA\Property(property="audit_initiation_offset", type="integer", description="Days before next_date to initiate the audit. Optional for Interval and Calendar schedule_type; ignored (forced to null) when schedule_type is manual."),
 *                 @OA\Property(property="schedule_type", type="string", enum={"manual","interval","calendar"}, description="The scheduling mode for this test."),
 *                 @OA\Property(property="cadence_unit", type="string", enum={"day","week","month","year"}, description="Calendar cadence recurrence unit. Required when schedule_type is calendar."),
 *                 @OA\Property(property="cadence_interval", type="integer", description="Calendar cadence recurrence interval. Required when schedule_type is calendar."),
 *                 @OA\Property(property="cadence_anchor_date", type="string", format="date", description="Calendar cadence anchor date, must be today or later. Accepts ISO (YYYY-MM-DD) or this instance's configured display format. Required when schedule_type is calendar."),
 *                 @OA\Property(
 *                     property="schedule_exceptions",
 *                     type="array",
 *                     description="Per-occurrence overrides/skips for the calendar schedule. Omit to leave existing exceptions untouched; supply (including []) to replace them.",
 *                     @OA\Items(
 *                         type="object",
 *                         required={"occurrence_date"},
 *                         @OA\Property(property="occurrence_date", type="string", format="date", description="The natural (un-overridden) occurrence date this exception applies to."),
 *                         @OA\Property(property="override_date", type="string", format="date", nullable=true, description="Replacement date for this occurrence, or null to keep the natural date."),
 *                         @OA\Property(property="skipped", type="boolean", description="True to skip this occurrence entirely.")
 *                     )
 *                 ),
 *                 @OA\Property(property="test_method", type="string", enum={"inquiry","observation","inspection","reperformance"}, nullable=true, description="How the test evidence is gathered."),
 *                 @OA\Property(property="sample", type="string", description="The sample selection/methodology used for the test."),
 *                 @OA\Property(property="required_evidence", type="string", description="The evidence required to satisfy the test."),
 *                 @OA\Property(
 *                     property="approvers",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="User IDs authorized to approve results of this test. The edit form submits the full multi-select, so this always replaces the persisted list. Segregation-of-duties: the tester may not also be listed as an approver."
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Test updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Validation failed (including an invalid test_method, the tester listed among approvers, or a last_date / next_date / cadence_anchor_date that is not a real date in either accepted format), or the user does not have permission to edit tests / access this test.")
 * )
 */
class OpenApiUpdateTestSchedule {}

/**
 * @OA\Get(
 *     path="/compliance/test",
 *     summary="Get details for a single compliance test, including its audit cadence schedule",
 *     description="Get details for a single compliance test, including the calendar-cadence schedule fields (schedule_type, cadence_unit, cadence_interval, cadence_anchor_date) and the persisted per-occurrence schedule_exceptions map, keyed by occurrence_date.",
 *     operationId="getTest",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
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
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="message", type="string", example="success"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 additionalProperties=true,
 *                 @OA\Property(property="schedule_type", type="string", enum={"manual","interval","calendar"}, nullable=true),
 *                 @OA\Property(property="cadence_unit", type="string", enum={"day","week","month","year"}, nullable=true),
 *                 @OA\Property(property="cadence_interval", type="integer", nullable=true),
 *                 @OA\Property(property="cadence_anchor_date", type="string", format="date", nullable=true),
 *                 @OA\Property(
 *                     property="schedule_exceptions",
 *                     type="object",
 *                     description="Persisted per-occurrence exceptions, keyed by occurrence_date.",
 *                     @OA\AdditionalProperties(
 *                         type="object",
 *                         @OA\Property(property="override_date", type="string", format="date", nullable=true),
 *                         @OA\Property(property="skipped", type="boolean")
 *                     )
 *                 ),
 *                 @OA\Property(property="test_method", type="string", enum={"inquiry","observation","inspection","reperformance"}, nullable=true),
 *                 @OA\Property(property="sample", type="string", description="Purified rich-text sample selection/methodology."),
 *                 @OA\Property(property="required_evidence", type="string", description="Purified rich-text required evidence."),
 *                 @OA\Property(property="approvers", type="array", @OA\Items(type="integer"), description="User IDs authorized to approve results of this test."),
 *                 @OA\Property(
 *                     property="approver_names",
 *                     type="object",
 *                     description="Approver user IDs mapped to display names, keyed by user id.",
 *                     @OA\AdditionalProperties(type="string")
 *                 )
 *             )
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
 *     path="/compliance/approver_roster",
 *     summary="Get the compliance test approver roster (value/name)",
 *     description="Returns value/name for every enabled user holding the approve_tests permission. Backs the Phase 3a test-definition form's approver multi-select. Gated on define_tests (not approve_tests) -- the caller building/editing a test needs to see who the eligible approvers are without needing to be one themselves.",
 *     operationId="complianceApproverRoster",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Every enabled approve_tests user's value/name.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="success"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="value", type="integer", description="The user's id."),
 *                     @OA\Property(property="name", type="string", description="The user's display name.")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to define tests.")
 * )
 */
class OpenApiComplianceApproverRoster {}

/**
 * @OA\Post(
 *     path="/compliance/schedule_preview",
 *     summary="Preview the occurrence dates a calendar audit cadence schedule would produce",
 *     description="Computes the effective occurrence dates for a candidate calendar cadence schedule (cadence unit/interval/anchor plus optional per-occurrence exceptions) over a date range, without persisting anything. Backs the live preview in the test schedule editor.",
 *     operationId="getSchedulePreview",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"cadence_unit","cadence_interval","cadence_anchor_date"},
 *             @OA\Property(property="cadence_unit", type="string", enum={"day","week","month","year"}, description="Recurrence unit."),
 *             @OA\Property(property="cadence_interval", type="integer", description="Recurrence interval, in units of cadence_unit."),
 *             @OA\Property(property="cadence_anchor_date", type="string", format="date", description="Anchor date (YYYY-MM-DD) the recurrence is computed from."),
 *             @OA\Property(property="start", type="string", format="date", description="Start of the preview range (YYYY-MM-DD). Defaults to today."),
 *             @OA\Property(property="end", type="string", format="date", description="End of the preview range (YYYY-MM-DD). Defaults to two years from today."),
 *             @OA\Property(
 *                 property="schedule_exceptions",
 *                 type="array",
 *                 description="Per-occurrence overrides/skips to apply before filtering to the preview range.",
 *                 @OA\Items(
 *                     type="object",
 *                     required={"occurrence_date"},
 *                     @OA\Property(property="occurrence_date", type="string", format="date"),
 *                     @OA\Property(property="override_date", type="string", format="date", nullable=true),
 *                     @OA\Property(property="skipped", type="boolean")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Computed occurrence dates within the preview range.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="message", type="string", example="success"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="occurrences", type="array", @OA\Items(type="string", format="date"), example={"2026-01-01","2026-04-01","2026-07-01","2026-10-01"})
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: The user does not have compliance permission.")
 * )
 */
class OpenApiGetSchedulePreview {}

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

/**
 * @OA\Post(
 *     path="/compliance/audits/active/datatable",
 *     summary="Server-side DataTables feed for the Active Audits view",
 *     description="Returns Active Audits rows in DataTables server-side format. Gated on the compliance permission (SR-1721).",
 *     operationId="complianceActiveAuditsDatatable",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         description="Standard DataTables server-side request parameters (draw, start, length, order, columns, search).",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(property="draw", type="integer", example=1),
 *                 @OA\Property(property="start", type="integer", example=0),
 *                 @OA\Property(property="length", type="integer", example=10)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="OK: DataTables server-side response.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=42),
 *             @OA\Property(property="recordsFiltered", type="integer", example=42),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiComplianceActiveAuditsDatatable {}

/**
 * @OA\Post(
 *     path="/compliance/audits/past/datatable",
 *     summary="Server-side DataTables feed for the Past Audits view",
 *     description="Returns Past Audits rows in DataTables server-side format. Gated on the compliance permission (SR-1721).",
 *     operationId="compliancePastAuditsDatatable",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         description="Standard DataTables server-side request parameters (draw, start, length, order, columns, search).",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(property="draw", type="integer", example=1),
 *                 @OA\Property(property="start", type="integer", example=0),
 *                 @OA\Property(property="length", type="integer", example=10)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="OK: DataTables server-side response.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=42),
 *             @OA\Property(property="recordsFiltered", type="integer", example=42),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiCompliancePastAuditsDatatable {}

/**
 * @OA\Post(
 *     path="/compliance/audits/report/datatable",
 *     summary="Server-side DataTables feed for the Dynamic Audit Report",
 *     description="Returns Dynamic Audit Report rows in DataTables server-side format. Gated on the compliance permission (SR-1721).",
 *     operationId="complianceDynamicAuditReportDatatable",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         description="Standard DataTables server-side request parameters (draw, start, length, order, columns, search).",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(property="draw", type="integer", example=1),
 *                 @OA\Property(property="start", type="integer", example=0),
 *                 @OA\Property(property="length", type="integer", example=10)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="OK: DataTables server-side response.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=42),
 *             @OA\Property(property="recordsFiltered", type="integer", example=42),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiComplianceDynamicAuditReportDatatable {}

/**
 * @OA\Post(
 *     path="/compliance/audits/timeline/datatable",
 *     summary="Server-side DataTables feed for the Audit Timeline report",
 *     description="Returns Audit Timeline rows in DataTables server-side format. Gated on the compliance permission (SR-1721).",
 *     operationId="complianceAuditTimelineDatatable",
 *     tags={"compliance"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         description="Standard DataTables server-side request parameters (draw, start, length, order, columns, search).",
 *         @OA\MediaType(
 *             mediaType="application/x-www-form-urlencoded",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(property="draw", type="integer", example=1),
 *                 @OA\Property(property="start", type="integer", example=0),
 *                 @OA\Property(property="length", type="integer", example=10)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="OK: DataTables server-side response.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="draw", type="integer", example=1),
 *             @OA\Property(property="recordsTotal", type="integer", example=42),
 *             @OA\Property(property="recordsFiltered", type="integer", example=42),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiComplianceAuditTimelineDatatable {}

?>
