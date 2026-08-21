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
 *                 @OA\Property(property="status", type="integer", enum={1, 2}, description="Status: 1=active, 2=inactive. Defaults to 1.", example=1),
 *                 @OA\Property(property="scope_statement", type="string", description="Statement of Applicability: the scope this framework is certified against. RICH TEXT (HTML) — purified with HTMLPurifier on write, stored and returned as purified HTML. Render it raw; escaping it prints tags to the reader. Markup that renders as nothing (an emptied editor's `<p><br></p>`) is stored as an empty string."),
 *                 @OA\Property(property="default_inclusion_justification", type="string", description="Statement of Applicability: how inclusion was determined for controls that are applicable. Stored and returned as RAW PLAIN TEXT — escape it at your own rendering sink. Deliberately NOT rich text, unlike scope_statement: this sentence fills a table cell once per applicable control."),
 *                 @OA\Property(property="clone_from", type="integer", description="Clone: an existing framework whose control mappings (control, reference name, reference text and reference subject) the new framework is given. Applicability decisions are NOT copied — the clone starts with every control applicable, which is what makes it a fresh scoping canvas over the same control set. Every other field is an ordinary create parameter, so the caller decides the clone's name, description, parent, status and default inclusion justification.", example=0)
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
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="id", type="integer", example=5),
 *                 @OA\Property(property="mappings_copied", type="integer", description="How many control mappings were copied from clone_from. Always 0 when clone_from was not supplied.", example=0)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: The user does not have permission to add frameworks."),
 *     @OA\Response(response=404, description="NOT FOUND: The clone_from framework does not exist. Nothing was created."),
 *     @OA\Response(response=409, description="CONFLICT: A framework with that name already exists."),
 *     @OA\Response(response=500, description="The framework was created, but its control mappings could not be copied. The new framework's id is returned in data.")
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
 *                 @OA\Property(property="parent", type="integer", description="The new parent framework ID."),
 *                 @OA\Property(property="status", type="integer", enum={1, 2}, description="Status: 1=active, 2=inactive. Deactivating cascades to every descendant framework; activating also activates the ancestor chain. Omit the key to leave the status alone.", example=1),
 *                 @OA\Property(property="scope_statement", type="string", description="Statement of Applicability scope. Omit the key to leave the stored value alone; send an empty string to clear it. RICH TEXT (HTML) — purified on write and returned as purified HTML; render it raw."),
 *                 @OA\Property(property="default_inclusion_justification", type="string", description="Statement of Applicability inclusion justification. Omit the key to leave the stored value alone; send an empty string to clear it. RAW PLAIN TEXT — escape at your rendering sink.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Framework updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: No updatable fields were provided, a status other than 1 or 2 was sent, or a Statement of Applicability field exceeded 65535 bytes."),
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
 *                 @OA\Property(property="control_status", type="integer", example=1, description="0 = Fail, 1 = Pass, 2 = Not Tested"),
 *                 @OA\Property(property="family", type="integer", example=0),
 *                 @OA\Property(property="mitigation_percent", type="integer", example=0),
 *                 @OA\Property(property="map_framework_id[]", type="array", @OA\Items(type="integer"), description="Parallel to reference_name[]/reference_text[]/reference_subject[] -- one framework id per mapping row. A row with no framework id is skipped."),
 *                 @OA\Property(property="reference_name[]", type="array", @OA\Items(type="string"), description="The reference (clause number) this framework cites the control under. Parallel to map_framework_id[]. Part of the mapping's unique key, so a row's reference identifies which mapping is written. An element absent at a row's index is treated as an empty reference."),
 *                 @OA\Property(property="reference_text[]", type="array", @OA\Items(type="string"), nullable=true, description="The clause text. Parallel to map_framework_id[]. An element that is ABSENT (a shorter array, or the array omitted entirely) leaves the column unset for that row rather than storing an empty string."),
 *                 @OA\Property(property="reference_subject[]", type="array", @OA\Items(type="string"), nullable=true, description="The framework's own title for the control, as the Statement of Applicability prints it. Parallel to map_framework_id[]. An element that is ABSENT leaves the column unset for that row, and the Statement of Applicability falls back to the SimpleRisk control name."),
 *                 @OA\Property(property="asset_maturity[]", type="array", @OA\Items(type="integer"), description="Parallel to assets_asset_groups[] -- one maturity level per mapping row."),
 *                 @OA\Property(property="assets_asset_groups[]", type="array", @OA\Items(type="array", @OA\Items(type="string")), description="Parallel to asset_maturity[] -- each row is an array of '<id>_asset'/'<id>_group' values.")
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
 *                 @OA\Property(property="control_status", type="integer", description="0 = Fail, 1 = Pass, 2 = Not Tested"),
 *                 @OA\Property(property="family", type="integer"),
 *                 @OA\Property(property="mitigation_percent", type="integer"),
 *                 @OA\Property(property="control_type[]", type="array", @OA\Items(type="integer"), description="The control's type IDs. PRESERVE VS CLEAR: if this key is omitted from the request AND control_type_submitted is not sent, the control's existing types are left alone. Sending control_type[] -- with one or more values -- always replaces the existing set, marker or not. To CLEAR every type (the multiselect-deselect-all case, which submits no control_type[] key at all), you must send control_type_submitted with a truthy value; without it, omitting control_type[] is indistinguishable from 'not mentioned' and preserves the existing types."),
 *                 @OA\Property(property="control_type_submitted", type="string", description="Marker: send a truthy value alongside an omitted/empty control_type[] to explicitly clear all of the control's types. Only needed for the clear case -- see control_type[] above. Ignored otherwise."),
 *                 @OA\Property(property="map_framework_id[]", type="array", @OA\Items(type="integer"), description="Parallel to reference_name[]/reference_text[]/reference_subject[] -- one framework id per mapping row. PRESERVE VS CLEAR: this entire group (map_framework_id[], reference_name[], reference_text[], reference_subject[]) is read ONLY when map_frameworks_submitted is sent with a truthy value. Without that marker, the group is ignored outright and the control's existing framework mappings are left alone -- even if map_framework_id[] itself is present in the request. With the marker, the submitted rows become the complete set, replacing every existing mapping; sending the marker with no rows clears all framework mappings."),
 *                 @OA\Property(property="reference_name[]", type="array", @OA\Items(type="string"), description="Parallel to map_framework_id[]. See map_framework_id[] for the map_frameworks_submitted preserve/clear rule. Part of the mapping's unique key, so a row's reference identifies which mapping is written -- a different reference addresses a different mapping rather than renaming an existing one. An element absent at a row's index is treated as an empty reference."),
 *                 @OA\Property(property="reference_text[]", type="array", @OA\Items(type="string"), nullable=true, description="The clause text. Parallel to map_framework_id[]. See map_framework_id[] for the map_frameworks_submitted preserve/clear rule. PER-ELEMENT PRESERVE VS CLEAR: for a row that survives that rule, an element that is present but empty CLEARS the stored clause text, while an element that is ABSENT (a shorter array, or the array omitted entirely while map_framework_id[] is sent) leaves the stored clause text untouched."),
 *                 @OA\Property(property="reference_subject[]", type="array", @OA\Items(type="string"), nullable=true, description="The framework's own title for the control, as the Statement of Applicability prints it. Parallel to map_framework_id[]. See map_framework_id[] for the map_frameworks_submitted preserve/clear rule. PER-ELEMENT PRESERVE VS CLEAR: an element that is present but empty CLEARS the stored subject; an element that is ABSENT leaves it untouched."),
 *                 @OA\Property(property="map_frameworks_submitted", type="string", description="Marker: send a truthy value to have this request's map_framework_id[]/reference_name[]/reference_text[]/reference_subject[] group replace the control's framework mappings -- including replacing them with none if the group is empty. Omit it (or send it falsy) to leave existing framework mappings untouched regardless of what those fields contain."),
 *                 @OA\Property(property="asset_maturity[]", type="array", @OA\Items(type="integer"), description="Parallel to assets_asset_groups[] -- one maturity level per mapping row. PRESERVE VS CLEAR: this group is read ONLY when mapped_assets_submitted is sent with a truthy value. Without that marker, the group is ignored and the control's existing asset mappings are left alone -- even if asset_maturity[] itself is present in the request. With the marker, the submitted rows become the complete set, replacing every existing mapping; sending the marker with no rows clears all asset mappings."),
 *                 @OA\Property(property="assets_asset_groups[]", type="array", @OA\Items(type="array", @OA\Items(type="string")), description="Parallel to asset_maturity[] -- each row is an array of '<id>_asset'/'<id>_group' values. See asset_maturity[] for the mapped_assets_submitted preserve/clear rule."),
 *                 @OA\Property(property="mapped_assets_submitted", type="string", description="Marker: send a truthy value to have this request's asset_maturity[]/assets_asset_groups[] group replace the control's asset mappings -- including replacing them with none if the group is empty. Omit it (or send it falsy) to leave existing asset mappings untouched regardless of what those fields contain.")
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
 *         description="SimpleRisk framework associations. A row whose join target was missing (e.g. a mapping pointing at a deleted record) or whose display name resolved empty is silently dropped rather than returned with a null/blank name.",
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
 *     path="/governance/controls/table",
 *     summary="List controls for the client-rendered controls table",
 *     description="Returns shaped, filtered, sorted, and paginated control rows for the Define Control Frameworks page. Unlike GET /governance/controls, this endpoint never renders HTML -- every field is data.",
 *     operationId="governanceControlsTable",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="start", in="query", description="Zero-based offset of the first row to return. An offset past the end of the filtered result set is clamped onto the last page that exists; the offset actually used comes back as data.start.", required=false, @OA\Schema(type="integer", default=0)),
 *     @OA\Parameter(name="length", in="query", description="Page size, clamped to 1-200.", required=false, @OA\Schema(type="integer", default=25)),
 *     @OA\Parameter(name="sort", in="query", description="Sort column. One of: control_number, short_name, family_name, control_owner_name, control_maturity, control_status. Falls back to control_number.", required=false, @OA\Schema(type="string", default="control_number")),
 *     @OA\Parameter(name="dir", in="query", description="Sort direction.", required=false, @OA\Schema(type="string", enum={"asc","desc"}, default="asc")),
 *     @OA\Parameter(name="framework", in="query", description="Comma-separated framework IDs to filter by. -1 means Unassigned.", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="family", in="query", description="Comma-separated family IDs to filter by. -1 means Unassigned.", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="owner", in="query", description="Comma-separated control-owner user IDs to filter by. -1 means Unassigned.", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="class", in="query", description="Comma-separated control-class IDs to filter by. -1 means Unassigned.", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="phase", in="query", description="Comma-separated control-phase IDs to filter by. -1 means Unassigned.", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="priority", in="query", description="Comma-separated control-priority IDs to filter by. -1 means Unassigned.", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="type", in="query", description="Comma-separated control-type IDs to filter by. -1 means Unassigned.", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="status", in="query", description="Comma-separated test-status tokens to filter by: pass, fail, not_tested.", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="maturity", in="query", description="Comma-separated maturity-bucket tokens to filter by: below, at, above. The legacy spelling below_target is accepted as an alias for below.", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="applicability", in="query", description="Comma-separated applicability states to filter by: applicable, not_applicable, inherited. The legacy token `excluded` is accepted as an alias for `not_applicable,inherited`. IGNORED unless exactly one real framework is scoped -- applicability is per-framework, so outside one framework's scope there is no state to filter on.", required=false, @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Controls table page",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="total", type="integer", description="Total non-deleted controls, unfiltered."),
 *                 @OA\Property(property="filtered", type="integer", description="Row count after filters, before pagination."),
 *                 @OA\Property(property="start", type="integer", description="The offset the rows below were actually sliced at. Equals the requested start unless that offset is past the end of the filtered result set, in which case it is clamped onto the last page that exists."),
 *                 @OA\Property(property="length", type="integer", description="The page size actually applied, after the 1-200 clamp."),
 *                 @OA\Property(property="applicability_scoped", type="boolean", description="Whether applicability is answerable for this request -- true only when exactly one real framework is scoped. The applicability_* row fields below are present only when this is true; outside one framework's scope there is no single honest answer, so they are omitted rather than sent as null."),
 *                 @OA\Property(
 *                     property="rows",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="id", type="integer"),
 *                         @OA\Property(property="control_number", type="string"),
 *                         @OA\Property(property="short_name", type="string"),
 *                         @OA\Property(property="long_name", type="string"),
 *                         @OA\Property(property="family", type="integer"),
 *                         @OA\Property(property="control_class", type="integer"),
 *                         @OA\Property(property="control_phase", type="integer"),
 *                         @OA\Property(property="control_priority", type="integer"),
 *                         @OA\Property(property="control_owner", type="integer"),
 *                         @OA\Property(property="control_type_ids", type="array", @OA\Items(type="string")),
 *                         @OA\Property(property="frameworks", type="array", @OA\Items(type="string")),
 *                         @OA\Property(property="family_name", type="string"),
 *                         @OA\Property(property="control_class_name", type="string"),
 *                         @OA\Property(property="control_phase_name", type="string"),
 *                         @OA\Property(property="control_priority_name", type="string"),
 *                         @OA\Property(property="control_owner_name", type="string"),
 *                         @OA\Property(property="control_type_names", type="array", @OA\Items(type="string")),
 *                         @OA\Property(property="control_status", type="integer", description="0 = Fail, 1 = Pass, 2 = Not Tested"),
 *                         @OA\Property(property="control_maturity", type="integer"),
 *                         @OA\Property(property="desired_maturity", type="integer"),
 *                         @OA\Property(property="control_maturity_name", type="string", description="Display label for control_maturity. Admin-renameable; falls back to the numeric level when the value is outside the 0-5 scale."),
 *                         @OA\Property(property="desired_maturity_name", type="string", description="Display label for desired_maturity. Admin-renameable; falls back to the numeric level when the value is outside the 0-5 scale."),
 *                         @OA\Property(property="mitigation_percent", type="integer"),
 *                         @OA\Property(property="description_purified", type="string", description="HTML-purified description, safe to insert with .html()."),
 *                         @OA\Property(property="supplemental_guidance_purified", type="string", description="HTML-purified supplemental guidance, safe to insert with .html()."),
 *                         @OA\Property(property="applicability", type="string", enum={"applicable","not_applicable","inherited"}, description="The control's applicability within the scoped framework. Only present when applicability_scoped is true. `applicable` is the DEFAULT and is never stored -- a control with no decision row resolves to it."),
 *                         @OA\Property(property="applicability_reason", type="string", nullable=true, description="Name of the configurable reason on the decision, or null. PLAIN TEXT, returned raw -- render with .text()/textContent, never .html()."),
 *                         @OA\Property(property="applicability_narrative", type="string", nullable=true, description="The justification recorded with the deviation, or null for an applicable control. PLAIN TEXT, returned raw -- render with .text()/textContent, never .html()."),
 *                         @OA\Property(property="applicability_provider", type="string", nullable=true, description="Who performs an inherited control. Empty for a not_applicable decision, null when there is no decision. PLAIN TEXT, returned raw -- render with .text()/textContent, never .html()."),
 *                         @OA\Property(property="applicability_by", type="string", nullable=true, description="Username the decision is attributed to, or null when there is no decision."),
 *                         @OA\Property(property="applicability_at", type="string", nullable=true, description="When the decision was recorded, or null when there is no decision.")
 *                     )
 *                 )
 *             )
 *         )
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
class OpenApiGovernanceControlsTable {}

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
 *         description="SimpleRisk control associations (frameworks, tests, documents, risks). Every bucket now consistently returns control_id as an integer. A row whose join target was missing or whose display name resolved empty is silently dropped rather than returned with a null/blank name.",
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
 *         description="SimpleRisk document associations. A row whose join target was missing or whose display name resolved empty is silently dropped rather than returned with a null/blank name.",
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
 *         description="Use a status of 1 for enabled or 2 for disabled. Omit, or send an empty value, to return frameworks of every status.",
 *         required=false,
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
 * @OA\Get(
 *     path="/governance/frameworks/rail",
 *     summary="List frameworks for the client-rendered framework rail",
 *     description="Returns a flat, depth-annotated list of frameworks with a real per-framework control count, for the Define Control Frameworks page's rail. Unlike GET /governance/frameworks/treegrid (which stays reserved for the legacy easyui treegrids on the Document Program / Exceptions tabs), this endpoint carries only the fields the rail renders and never serializes HTML.",
 *     operationId="governanceFrameworksRail",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Use a status of 1 for enabled (default) or 2 for disabled. Send an empty value to return frameworks of every status.",
 *         required=false,
 *         @OA\Schema(type="integer", enum={1, 2})
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Framework rail rows",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="rows",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="value", type="integer", description="Framework id."),
 *                         @OA\Property(property="name", type="string"),
 *                         @OA\Property(property="depth", type="integer", description="Tree depth for rail indentation; 0 = top-level."),
 *                         @OA\Property(property="control_count", type="integer", description="Distinct non-deleted controls mapped to this framework.")
 *                     )
 *                 )
 *             )
 *         )
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
class OpenApiFrameworksRail {}

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
 *             @OA\Property(property="framework_ids", type="array", description="IDs of frameworks this control belongs to. Used ONLY when no map_framework_id[] rows are sent; the control is added to each framework under its own control_number as the reference.", @OA\Items(type="integer")),
 *             @OA\Property(property="map_framework_id[]", type="array", @OA\Items(type="integer"), description="One framework id per mapping row. Parallel to reference_name[] and reference_text[] -- the three arrays are paired BY INDEX. Takes precedence over framework_ids."),
 *             @OA\Property(property="reference_name[]", type="array", @OA\Items(type="string"), description="The reference (clause number) this framework cites the control under. Parallel to map_framework_id[]. Part of the mapping's unique key, so a row's reference identifies which mapping is being written."),
 *             @OA\Property(property="reference_text[]", type="array", @OA\Items(type="string"), description="The clause text. Parallel to map_framework_id[]. An element that is present but empty CLEARS the stored text; an element that is ABSENT (a shorter array, or the array omitted entirely) leaves the stored text untouched."),
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
 *             @OA\Property(property="framework_ids", type="array", description="IDs of frameworks this control belongs to. Used ONLY when no map_framework_id[] rows are sent. It asserts MEMBERSHIP and nothing more: a framework the control is already mapped to is left exactly as it is (its reference and clause text are not rewritten), and a framework it is not yet in gains a mapping under the control's own control_number.", @OA\Items(type="integer")),
 *             @OA\Property(property="map_framework_id[]", type="array", @OA\Items(type="integer"), description="One framework id per mapping row. Parallel to reference_name[] and reference_text[] -- the three arrays are paired BY INDEX. Takes precedence over framework_ids. This group only ADDS and UPDATES mappings; it never removes one (use the PATCH controls-by-id endpoint with map_frameworks_submitted for replace-the-whole-set semantics)."),
 *             @OA\Property(property="reference_name[]", type="array", @OA\Items(type="string"), description="The reference (clause number) this framework cites the control under. Parallel to map_framework_id[]. Part of the mapping's unique key, so a row's reference identifies which mapping is being written -- a different reference addresses a different mapping rather than renaming an existing one."),
 *             @OA\Property(property="reference_text[]", type="array", @OA\Items(type="string"), description="The clause text. Parallel to map_framework_id[]. An element that is present but empty CLEARS the stored text; an element that is ABSENT (a shorter array, or the array omitted entirely) leaves the stored text untouched."),
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

/**
 * @OA\Post(
 *     path="/governance/documents/datatable",
 *     summary="Server-side DataTables feed for the Document Program report",
 *     description="Returns Document Program rows in DataTables server-side format. Gated on the governance permission (SR-1721).",
 *     operationId="governanceDocumentsDatatable",
 *     tags={"governance"},
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
class OpenApiGovernanceDocumentsDatatable {}

/**
 * @OA\Get(
 *     path="/governance/applicability/reasons",
 *     summary="List the configurable control-applicability reasons",
 *     description="Returns the customer-extendable reason picklist behind the Statement of Applicability. `applies_to` scopes a reason to the state it is offered for -- 'Performed by a third party' is an INHERITED reason, not an exclusion, because a cloud-hosted organisation does not exclude the controls its provider performs. An unrecognised applies_to value returns the full list rather than an error.",
 *     operationId="governanceApplicabilityReasons",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="applies_to", in="query", description="Scope the list to one state.", required=false, @OA\Schema(type="string", enum={"not_applicable","inherited"})),
 *     @OA\Response(
 *         response=200,
 *         description="The applicability reason list",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="reasons",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="value", type="integer"),
 *                         @OA\Property(property="name", type="string"),
 *                         @OA\Property(property="applies_to", type="string", enum={"not_applicable","inherited"})
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiGovernanceApplicabilityReasons {}

/**
 * @OA\Get(
 *     path="/governance/applicability",
 *     summary="Read the stored control-applicability decisions for a framework",
 *     description="Applicable is the DEFAULT and is never stored, so this returns DEVIATIONS ONLY -- a control with no entry in `decisions` is applicable. The payload echoes `default_state` so a client resolves an absent control the same way the server does. Omit control_ids to read every decision in the framework; that is cheap because only deviations are stored. Decisions whose control is no longer mapped into the framework are included: a decision goes dormant rather than being deleted, since an auditor may still ask about last year's exclusion. `narrative` and `provider` are plain text returned raw and must be rendered as text, never inserted as HTML.",
 *     operationId="governanceApplicabilityGet",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="framework", in="query", description="Framework id the decisions are scoped to.", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="control_ids", in="query", description="Comma-separated control ids to scope the read to, e.g. the page currently on screen. Omit for the whole framework.", required=false, @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="The framework's applicability deviations",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="framework", type="integer"),
 *                 @OA\Property(property="default_state", type="string", example="applicable", description="The state of every control NOT listed in decisions."),
 *                 @OA\Property(
 *                     property="decisions",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="id", type="integer"),
 *                         @OA\Property(property="framework", type="integer"),
 *                         @OA\Property(property="control_id", type="integer"),
 *                         @OA\Property(property="state", type="string", enum={"applicable","not_applicable","inherited"}, description="'applicable' appears only for a control that stored its OWN justification; a control with no row at all is applicable too, and is absent from this list."),
 *                         @OA\Property(property="reason_ids", type="array", @OA\Items(type="integer"), description="The taxonomy reasons attached to this decision. Multi-select; empty when none."),
 *                         @OA\Property(property="reason_names", type="array", @OA\Items(type="string"), description="The names of reason_ids, in the same order."),
 *                         @OA\Property(property="narrative", type="string", nullable=true, description="Plain text, returned raw. Render as text, never as HTML. NULL means no narrative was ever given -- deliberately distinct from an empty string."),
 *                         @OA\Property(property="provider", type="string", description="Plain text, returned raw. Empty unless state is inherited."),
 *                         @OA\Property(property="decided_by", type="integer"),
 *                         @OA\Property(property="decided_by_name", type="string"),
 *                         @OA\Property(property="decided_at", type="string", format="date-time")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: A framework id is required, or the selection is too large."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiGovernanceApplicabilityGet {}

/**
 * @OA\Post(
 *     path="/governance/applicability",
 *     summary="Set or clear control applicability across a selection",
 *     description="Bulk-set is the primary interaction, not a convenience -- nobody decides 1,535 SCF controls one row at a time. state 'not_applicable' requires at least one reason_id AND a narrative; 'inherited' requires a provider AND a narrative; 'applicable' requires neither -- it may carry its own reasons and/or narrative, and with NEITHER it CLEARS the row back to the framework default. ABSENT IS NOT EMPTY: a field the body omits (or sends as null) PRESERVES what is stored, while an explicit empty array or empty string CLEARS it -- so resetting a control to the framework default is {state:'applicable', narrative:'', reason_ids:[]}, and a bare {state:'applicable'} leaves a stored justification alone. A reason may only be used for the state its applies_to names. The write is all-or-nothing: every argument and every framework/control id is validated before anything is written, so a partly-invalid selection writes nothing. The selection is named EITHER as control_ids OR as all_filtered+filters -- never both -- because the control table pages, so a client holding one page cannot enumerate the whole filtered set it just asked to act on. Requires the modify_frameworks permission (spec D10) -- scoping a control out of a framework is a framework-scoping decision.",
 *     operationId="governanceApplicabilitySet",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"framework","state"},
 *             @OA\Property(property="framework", type="integer", description="Framework the decision is scoped to. The same control excluded from ISO 27001 is not thereby excluded from PCI DSS."),
 *             @OA\Property(property="control_ids", type="array", @OA\Items(type="integer"), description="Controls in the selection. Duplicates collapse. At most 5000. Required unless all_filtered is true; sending both is a 400."),
 *             @OA\Property(property="all_filtered", type="boolean", description="Act on every control the `filters` below match, instead of on a list of ids. Resolved server-side through the SAME pipeline GET /governance/controls/table uses, so the set written is exactly the set that endpoint's `filtered` count reported. Still capped at 5000 resolved controls."),
 *             @OA\Property(property="filters", type="object", description="Only read when all_filtered is true. The same query-parameter map GET /governance/controls/table takes (family, owner, class, phase, priority, type, status, maturity, applicability, text) as comma-separated strings. Any `framework` key here is OVERWRITTEN with the framework above, so ids can never be resolved out of one framework and recorded against another."),
 *             @OA\Property(property="state", type="string", enum={"applicable","not_applicable","inherited"}),
 *             @OA\Property(property="reason_ids", type="array", @OA\Items(type="integer"), nullable=true, description="Taxonomy reasons, MULTI-select. At least one is required for not_applicable; optional for inherited and applicable. Every id must be a reason whose applies_to matches state -- a mismatched one is refused, not dropped. Omit (or send null) to leave the stored reasons alone; send [] to remove them all. A scalar is a 400."),
 *             @OA\Property(property="narrative", type="string", nullable=true, description="Required for both deviations, optional for applicable. Plain text, max 65535 bytes. Omit (or send null) to leave the stored narrative alone; send '' to remove it."),
 *             @OA\Property(property="provider", type="string", nullable=true, description="Required for inherited; forced empty otherwise. Max 200 bytes. Omit (or send null) to leave the stored provider alone; send '' to remove it.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The decision was recorded (or cleared)",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="framework", type="integer"),
 *                 @OA\Property(property="state", type="string"),
 *                 @OA\Property(property="selected", type="integer", description="How many controls the decision was applied to. For an all_filtered request this is the RESOLVED count, not the size of anything the client sent."),
 *                 @OA\Property(property="updated", type="integer", description="Controls whose decision was written, plus rows removed. Equals `selected` for a deviation; for a reset it counts only the controls that actually held a decision. One call can do both, because the reset decision is made per control.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: the request or the deviation is not well formed (missing narrative, missing reason or provider, unknown framework or control, reason offered for a different state, both control_ids and all_filtered named, or an all_filtered selection that matches nothing).",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=400),
 *             @OA\Property(property="status_message", type="string"),
 *             @OA\Property(property="data", type="object", @OA\Property(property="error", type="string", example="invalid_request"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiGovernanceApplicabilitySet {}

/**
 * @OA\Post(
 *     path="/governance/controls/bulk-delete",
 *     summary="Delete controls in bulk, by id list or by filter",
 *     description="POST rather than DELETE because there is no addressable resource: the request names EITHER an explicit control_ids list OR all_filtered+filters, which the server resolves through the same pipeline GET /governance/controls/table uses -- so the set deleted is exactly the set that endpoint's `filtered` count reported. Naming both is a 400; they are two different populations and silently discarding one is the difference between deleting 25 controls and deleting 1,535. DELETING A CONTROL IS TWO OUTCOMES, NOT ONE: a control that carries any test history is retained in a deleted state (framework_controls.deleted = 1), a control with no tests is removed permanently along with its type / asset / asset-group mappings. Both counts are reported so a caller can state honestly what is about to happen. `confirm` IS THE INTERLOCK -- without it the endpoint resolves the set, reports the split, and writes NOTHING; only an explicit confirm:true deletes. The default is the safe direction on purpose, so a client that drops the flag previews instead of destroying. Deleting a control removes it from EVERY framework it is mapped to, so unlike POST /governance/applicability this endpoint does not require a framework. Requires BOTH the governance permission (which gates reading the controls a filtered selection resolves over) and delete_controls (which gates deleting a control at all).",
 *     operationId="governanceControlsBulkDelete",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="control_ids", type="array", @OA\Items(type="integer"), description="Controls to delete. Duplicates collapse. At most 2000. Required unless all_filtered is true; sending both is a 400."),
 *             @OA\Property(property="all_filtered", type="boolean", description="Delete every control the `filters` below match, instead of a list of ids. Resolved server-side. Still capped at 2000 resolved controls, and a filter matching nothing is a 400 rather than a cheerful 0."),
 *             @OA\Property(property="filters", type="object", description="Only read when all_filtered is true. The same query-parameter map GET /governance/controls/table takes (family, owner, class, phase, priority, type, status, maturity, applicability, text) as comma-separated strings."),
 *             @OA\Property(property="framework", type="integer", nullable=true, description="Optional. When a positive id is given it OVERWRITES any `framework` key in `filters`, so ids cannot be resolved out of a framework the caller did not scope. Omit it to resolve over the unscoped All-frameworks view -- deleting a control removes it from every framework anyway."),
 *             @OA\Property(property="confirm", type="boolean", description="Absent or false: PREVIEW. The set is resolved and the split reported; nothing is written, and the response's `deleted` is false. Only true performs the delete.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="The resolved set and its soft/hard split. Whether anything was written is reported by `deleted`.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="framework", type="integer", nullable=true),
 *                 @OA\Property(property="all_filtered", type="boolean"),
 *                 @OA\Property(property="resolved", type="integer", description="Distinct control ids named, or resolved from the filter. Never the size of anything the client sent for an all_filtered request."),
 *                 @OA\Property(property="found", type="integer", description="Of those, the ones that name a control that actually exists. Equals soft_delete + hard_delete."),
 *                 @OA\Property(property="soft_delete", type="integer", description="Controls carrying test history. These are RETAINED in a deleted state rather than removed."),
 *                 @OA\Property(property="hard_delete", type="integer", description="Controls with no test history. These are removed PERMANENTLY."),
 *                 @OA\Property(property="missing", type="integer", description="Ids naming no control -- a stale client selection, or one another session already removed. Neither retained nor removed, because there is nothing there."),
 *                 @OA\Property(property="deleted", type="boolean", description="false for a preview, true when the delete was performed.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: both control_ids and all_filtered named, an empty control_ids list, more than 2000 controls named or resolved, or an all_filtered selection that matches nothing.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=400),
 *             @OA\Property(property="status_message", type="string"),
 *             @OA\Property(property="data", type="object", @OA\Property(property="error", type="string", example="invalid_request"))
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     )
 * )
 */
class OpenApiGovernanceControlsBulkDelete {}

/**
 * @OA\Get(
 *     path="/governance/soa",
 *     summary="The Statement of Applicability for one framework",
 *     description="The document ISO/IEC 27001:2022 clause 6.1.3(d) asks for: every control in the framework, whether it is applicable, the justification for its inclusion or exclusion, and its implementation status. PER-FRAMEWORK BY DEFINITION -- applicability is stored per (framework, control_id), the same control excluded from ISO 27001 is not thereby excluded from PCI DSS, and there is no Applicable-in-N-of-M roll-up across frameworks; a request naming no framework is a 400 rather than an invented cross-framework answer. APPLICABLE IS THE DEFAULT AND IS NEVER STORED, so this iterates the framework's CONTROLS and resolves each one's state -- it does not iterate the applicability table, which holds deviations only and would yield a document listing nothing but the exclusions. THE POPULATION IS THE CONTROLS PAGE'S: rows resolve through the same pipeline GET /governance/controls/table uses, so the count on this cover equals the count on that grid for the same framework, and the cover's totals are counted from the printed rows rather than by a separate aggregate. `implemented` is DERIVED on every read from the control's LIVE (non-retired) tests and their latest results -- never stored, and never from maturity, which measures ambition rather than effectiveness. Six values: `yes` (every live test passed), `partial` (mixed), `no` (nothing passed and at least one failed), `no_test_defined` (a governance gap -- no live test exists), `never_run` (an operational gap -- tests exist, none has produced a verdict), and `not_applicable`. A separate boolean `implemented_overdue` COMPOSES with `yes`/`partial`/`no` when a contributing test is past its own scheduled date; it is not a seventh value. The rule is deliberately ASYMMETRIC: unresolved evidence blocks `yes` but does not rescue a control from `no`, because missing evidence must never improve the reported position. Soft-deleted controls are absent, as they are from the controls page. Text fields -- control names, narratives, justifications, provider and evidence -- are PLAIN TEXT returned RAW and must be rendered as text, never inserted as HTML. TWO FIELDS IN THIS RESPONSE ARE STORED AS RICH TEXT, and they are delivered differently from each other, deliberately. The cover's `scope_statement` is returned AS purified HTML and must be rendered raw. `evidence_items[].required_evidence` is a rich-text column too, but is PROJECTED TO PLAIN TEXT before it leaves the server (soa_plain_text_or_null(), includes/soa.php): what you receive is newline-separated lines, with each list item already prefixed `&#8226; `, and never markup -- so it is rendered as text like every other field here, and a client that inserted it as HTML would be inserting escaped entities. The projection is not cosmetic: this value lands in a dense table cell that three sinks render, one of them a spreadsheet, and the PDF writer joins the cell with `&lt;br&gt;` precisely to avoid an element per line. On the two cover statements, null means the field was never set and '' means it was deliberately cleared; the report prompts on either, but the distinction is preserved rather than coerced. Requires the governance permission. The REPORT IS CORE and is not Extra-gated; only its export is, which is what `can_export` advertises.",
 *     operationId="governanceSoa",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="framework", in="query", description="Framework the statement is made about. Required, and must be an ACTIVE framework.", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="The Statement of Applicability",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="status", type="integer", example=200),
 *             @OA\Property(property="status_message", type="string", example="SUCCESS"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="cover",
 *                     type="object",
 *                     description="The document's cover block. Its counts are derived from the rows below, so they cannot contradict the table they introduce.",
 *                     @OA\Property(property="framework", type="integer"),
 *                     @OA\Property(property="framework_name", type="string", description="Plain text, returned raw. Decrypted where the Encrypted Database Extra is active."),
 *                     @OA\Property(property="framework_status", type="integer", example=1, description="Always 1 (active) on a successful response; an inactive framework is refused with a 409."),
 *                     @OA\Property(property="scope_statement", type="string", nullable=true, description="The scope the framework is certified against. RICH TEXT (HTML), purified on the way out as well as on write — render it raw. null = never set (the report prompts); '' = deliberately cleared, which also covers markup that renders as nothing."),
 *                     @OA\Property(property="default_inclusion_justification", type="string", nullable=true, description="Fills the Justification column for every plainly-applicable control. Plain text, returned raw. null = never set; '' = deliberately cleared."),
 *                     @OA\Property(property="total", type="integer", description="Controls in the framework. Equals the controls page's `filtered` count for the same framework with no filters applied."),
 *                     @OA\Property(property="applicable", type="integer"),
 *                     @OA\Property(property="not_applicable", type="integer"),
 *                     @OA\Property(property="inherited", type="integer"),
 *                     @OA\Property(property="excluded", type="integer", description="not_applicable + inherited -- both stored deviations, matching what the controls page's Excluded insight tile counts. The two are also reported separately, because folding them together would make the statement factually wrong: an organization does not EXCLUDE the controls a provider performs for it."),
 *                     @OA\Property(property="generated_at", type="string", format="date-time", description="When this response was assembled. The document is built on every read, so this is the only honest timestamp.")
 *                 ),
 *                 @OA\Property(
 *                     property="rows",
 *                     type="array",
 *                     description="One row per control, in natural control-number order.",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="control_id", type="integer"),
 *                         @OA\Property(property="ref", type="string", description="The control number, e.g. A.5.1. Plain text, returned raw."),
 *                         @OA\Property(property="name", type="string", description="The control's short name. Plain text, returned raw."),
 *                         @OA\Property(property="applicable", type="string", enum={"applicable","not_applicable","inherited"}, description="`applicable` is the DEFAULT and is never stored -- a control with no decision row resolves to it."),
 *                         @OA\Property(property="justification", type="string", description="Never blank by construction: a deviation carries its own mandatory narrative; an applicable control cites its linked risks if it has any; otherwise the framework's default inclusion justification fills the cell. Plain text, returned raw."),
 *                         @OA\Property(property="implemented", type="string", enum={"yes","partial","no","not_applicable"}, description="DERIVED on every read, never stored. A failed test yields `no` and outranks maturity; an UNTESTED control does not, because absence of evidence is not evidence of absence. Below a set target yields `partial`; a desired maturity of 0 means no target. `not_applicable` only for an excluded control -- an inherited control is applicable and does have an implementation status."),
 *                         @OA\Property(property="evidence", type="string", description="Governing documents mapped to the control, comma-joined. CONFIRMED mappings only (selected=1) -- unreviewed TF-IDF and AI candidate matches are excluded, because presenting a machine's guesses to an auditor as the organization's governing documentation would be a misstatement. Plain text, returned raw."),
 *                         @OA\Property(property="reason", type="string", nullable=true, description="Name of the configurable reason on the decision. null for an applicable control."),
 *                         @OA\Property(property="provider", type="string", nullable=true, description="Who performs an inherited control. Plain text, returned raw."),
 *                         @OA\Property(property="decided_by", type="string", nullable=true, description="Username the deviation is attributed to. A justification an auditor cannot attribute is an assertion, not a record."),
 *                         @OA\Property(property="decided_at", type="string", nullable=true, format="date-time")
 *                     )
 *                 ),
 *                 @OA\Property(property="can_export", type="boolean", description="Whether the Import/Export Extra is active. ADVISORY -- it exists so the page renders the export button only where it would work. The export endpoint enforces the same check independently, so ignoring this flag gains a caller nothing.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: A framework is required. A Statement of Applicability is a per-framework document."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: The user does not have the required permission to perform this action."
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="NOT FOUND: That framework does not exist."
 *     ),
 *     @OA\Response(
 *         response=409,
 *         description="CONFLICT: That framework is inactive. A Statement of Applicability states the scope an organization currently operates under, and a deactivated framework has been withdrawn from the program."
 *     )
 * )
 */
class OpenApiGovernanceSoa {}

/**
 * @OA\Get(
 *     path="/governance/soa/export",
 *     summary="Download the Statement of Applicability as a file",
 *     description="The SAME document GET /governance/soa returns, rendered as a file: an XLSX spreadsheet or a PDF. Streams a binary body -- on success the response is the file, not JSON. THE POPULATION AND THE COUNTS ARE THE ENDPOINT ABOVE'S, from the same single build: the export does not run a query of its own, so the spreadsheet, the PDF and the document on screen cannot disagree about how many controls a framework has or what any of them says. Columns are the document's, in its order: Reference, Control Name, Applicability, Justification, Implementation Status, Evidence -- six, always, followed by Appendix A (every control's justification in full) and Appendix R (the remediation plan behind every failing control). The Risk Register, Control Owner and Review Cadence columns a `detailed` variant used to append are gone; the risk traceability survives inside Appendix R's entries, which name the risk id, the planning date, the mitigation owner and the percentage. With the decision's attribution (decided by, reason, provider) folded into the Justification cell exactly as the screen folds it, so an auditor holding the download and the page can reconcile them line for line. REQUIRES THE IMPORT/EXPORT EXTRA, enforced here and not only by the page declining to render the button: a caller who knows the URL gets a 403, not a document. The report itself is Core and is not Extra-gated. Requires the governance permission -- an export is a READ of the document, so it does not additionally require modify_frameworks.",
 *     operationId="governanceSoaExport",
 *     tags={"governance_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="framework", in="query", description="Framework the statement is made about. Required, and must be an ACTIVE framework.", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="format", in="query", description="Rendering to produce. An unrecognized value is a 400 rather than a silent fallback to xlsx.", required=false, @OA\Schema(type="string", enum={"xlsx","pdf"}, default="xlsx")),
 *     @OA\Response(
 *         response=200,
 *         description="The Statement of Applicability as a file. Content-Disposition is an attachment whose filename is built from the framework name; the name is sanitized before it reaches the header.",
 *         @OA\MediaType(
 *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
 *             @OA\Schema(type="string", format="binary")
 *         ),
 *         @OA\MediaType(
 *             mediaType="application/pdf",
 *             @OA\Schema(type="string", format="binary")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: no framework was named (a Statement of Applicability is a per-framework document), or `format` is not one of xlsx, pdf. The rejected value is never echoed back.")
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="FORBIDDEN: the user lacks the governance permission, or the Import/Export Extra -- which this export requires -- is not active."
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="NOT FOUND: That framework does not exist."
 *     ),
 *     @OA\Response(
 *         response=409,
 *         description="CONFLICT: That framework is inactive. A Statement of Applicability states the scope an organization currently operates under, and a deactivated framework has been withdrawn from the program."
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="INTERNAL SERVER ERROR: the Import/Export Extra is active but predates this export and does not provide it."
 *     )
 * )
 */
class OpenApiGovernanceSoaExport {}

?>
