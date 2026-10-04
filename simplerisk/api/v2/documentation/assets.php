<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// @phan-suppress-next-line PhanUnreferencedUseNormal -- OA alias used in PHPDoc @OA annotations
use OpenApi\Annotations as OA;

// =====================================================================
// ASSETS CRUD API
// =====================================================================

/**
 * @OA\Get(
 *     path="/assets",
 *     summary="List assets with filters, sorting, paging and column selection",
 *     operationId="listAssets",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="verified", in="query", required=false, description="1/true = verified only, 0/false = unverified only, omitted = all. Counts ignore this filter.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="q", in="query", required=false, description="Case-insensitive substring match on asset name and IP address.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="location", in="query", required=false, description="Comma-separated site location IDs (any match).", @OA\Schema(type="string")),
 *     @OA\Parameter(name="team", in="query", required=false, description="Comma-separated team IDs (any match).", @OA\Schema(type="string")),
 *     @OA\Parameter(name="tag", in="query", required=false, description="Comma-separated tag IDs (any match).", @OA\Schema(type="string")),
 *     @OA\Parameter(name="group", in="query", required=false, description="Comma-separated asset group IDs (any match).", @OA\Schema(type="string")),
 *     @OA\Parameter(name="valuation", in="query", required=false, description="Comma-separated asset valuation IDs (asset_values ids; any match). At most 500 ids are used.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="categorization", in="query", required=false, description="Comma-separated FIPS 199 categorization level ids (1 Low, 2 Moderate, 3 High; any match). Unscored assets never match. Other ids are ignored.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="band", in="query", required=false, description="Comma-separated weighted band level ids (1 Low, 2 Moderate, 3 High; any match), computed with the current Asset Scoring settings. Unscored assets never match. Other ids are ignored.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="confidentiality", in="query", required=false, description="Comma-separated confidentiality rating ids (1 Low, 2 Moderate, 3 High, 0 Not applicable; any match), matched against the stored rating. An asset whose confidentiality is not set never matches. Other ids are ignored.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="integrity", in="query", required=false, description="Comma-separated integrity rating ids (1 Low, 2 Moderate, 3 High; any match), matched against the stored rating. An asset whose integrity is not set never matches. Other ids (0 included) are ignored.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="availability", in="query", required=false, description="Comma-separated availability rating ids (1 Low, 2 Moderate, 3 High; any match), matched against the stored rating. An asset whose availability is not set never matches. Other ids (0 included) are ignored. Every filter combines with the others (AND).", @OA\Schema(type="string")),
 *     @OA\Parameter(name="sort", in="query", required=false, description="Sort key: id, name (default), ip, value, verified, created, confidentiality, integrity, availability, fips_categorization, weighted_score, weighted_band or custom_field_ID. The Asset Scoring keys sort by level (not applicable below low) or by score; unscored assets sort last in both directions. Unknown keys fall back to name. Sorting is applied to the whole filtered set.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="dir", in="query", required=false, description="Sort direction.", @OA\Schema(type="string", enum={"asc","desc"})),
 *     @OA\Parameter(name="page", in="query", required=false, description="1-based page number (used with per_page).", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="per_page", in="query", required=false, description="Page size, maximum 500. 0 or omitted returns every matching asset.", @OA\Schema(type="integer", default=0)),
 *     @OA\Parameter(name="columns", in="query", required=false, description="Comma-separated keys to return (id is always included), for example name,ip,custom_field_3. Custom field values are returned as display labels, not stored ids. Unknown or deleted custom field keys are ignored. associated_risks returns [{id, display_id, subject}] (direct links only, only risks the caller may see; empty without the Risk Management permission) plus associated_risks_count (the risks the caller may see, counted with or without the Risk Management permission); mapped_controls returns [{id, short_name}] (direct links only; empty without the Governance permission) plus mapped_controls_count. Both are computed only when requested and are not sortable. tags returns the tag names joined by commas and, alongside it, tag_list: the same names as an array (a tag name may itself contain a comma, so use tag_list to separate tags). Omitted returns the full asset row.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="facet_counts", in="query", required=false, description="1/true adds facet_counts: per-value asset counts for the team, location and valuation facets, in that order, each counted inside the FIRST id of the facets before it (team, then location). The scope ignores the team/location/valuation filters but keeps q, verified, tag, group, categorization, band, confidentiality, integrity and availability. Team counts cover only teams the caller may see.", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Assets retrieved successfully.",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="assets", type="array", description="Asset rows (the full row, or the selected columns plus id).", @OA\Items(type="object",
 *                     @OA\Property(property="confidentiality", type="string", nullable=true, enum={"low","moderate","high","not_applicable"}, description="Asset Scoring selection; null when not set."),
 *                     @OA\Property(property="integrity", type="string", nullable=true, enum={"low","moderate","high"}, description="Asset Scoring selection; null when not set."),
 *                     @OA\Property(property="availability", type="string", nullable=true, enum={"low","moderate","high"}, description="Asset Scoring selection; null when not set."),
 *                     @OA\Property(property="fips_categorization", type="string", nullable=true, enum={"low","moderate","high"}, description="Computed FIPS 199 categorization (high-water mark); null until all three objectives are set."),
 *                     @OA\Property(property="weighted_score", type="string", nullable=true, description="Computed weighted score with two decimals, e.g. 2.00; null when unscored."),
 *                     @OA\Property(property="weighted_band", type="string", nullable=true, enum={"low","moderate","high"}, description="Band of the weighted score under the current thresholds; null when unscored.")
 *                 )),
 *                 @OA\Property(property="total", type="integer", description="Assets matching all filters (before paging)."),
 *                 @OA\Property(property="page", type="integer"),
 *                 @OA\Property(property="per_page", type="integer"),
 *                 @OA\Property(property="counts", type="object",
 *                     @OA\Property(property="all", type="integer"),
 *                     @OA\Property(property="verified", type="integer"),
 *                     @OA\Property(property="unverified", type="integer")
 *                 ),
 *                 @OA\Property(property="sort", type="object",
 *                     @OA\Property(property="key", type="string"),
 *                     @OA\Property(property="dir", type="string")
 *                 ),
 *                 @OA\Property(property="sortable_columns", type="array", @OA\Items(type="string")),
 *                 @OA\Property(property="facet_counts", type="object", description="Only with facet_counts=1. {team|location|valuation: {all: int, values: {id: count}}}")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 * )
 */
class OpenApiListAssets {}

/**
 * @OA\Get(
 *     path="/assets/column-settings",
 *     summary="Get the caller's Manage assets column settings",
 *     description="Returns the caller's own saved column visibility/order (defaults when nothing is saved) plus every column they may enable. Saved keys that are no longer available (for example a deleted custom field) are dropped. Requires the asset permission.",
 *     operationId="getAssetColumnSettings",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Column settings retrieved successfully.",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="column_settings", type="object",
 *                     @OA\Property(property="columns", type="array", description="[key, 1 or 0] pairs; every available key appears once; name is always 1.", @OA\Items(type="array", @OA\Items(type="string"))),
 *                     @OA\Property(property="order", type="array", description="Display order; name is always first.", @OA\Items(type="string"))
 *                 ),
 *                 @OA\Property(property="available_columns", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="key", type="string"),
 *                     @OA\Property(property="label_key", type="string", nullable=true, description="Language key for core columns; null for custom fields."),
 *                     @OA\Property(property="label", type="string", description="Raw label text; escape once at render."),
 *                     @OA\Property(property="group", type="string", enum={"asset","custom"}),
 *                     @OA\Property(property="always_on", type="boolean")
 *                 )),
 *                 @OA\Property(property="default_columns", type="array", description="Keys shown when nothing is saved (name, value, fips_categorization, weighted_score; the two scoring keys only once the upgrade has added the scoring columns). A saved layout is returned exactly as saved: a column added after the save is off.", @OA\Items(type="string"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission.")
 * )
 */
class OpenApiGetAssetColumnSettings {}

/**
 * @OA\Put(
 *     path="/assets/column-settings",
 *     summary="Save the caller's Manage assets column settings",
 *     description="Replaces the caller's own column settings. Unknown or unavailable keys are dropped, name is forced on and first, and the stored value is returned. Requires the asset permission.",
 *     operationId="saveAssetColumnSettings",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="columns", type="array", @OA\Items(type="array", @OA\Items(type="string")), description="[key, 1 or 0] pairs"),
 *             @OA\Property(property="order", type="array", @OA\Items(type="string"))
 *         )
 *     ),
 *     @OA\Response(response=200, description="Saved. Same payload as GET /assets/column-settings."),
 *     @OA\Response(response=400, description="BAD REQUEST: data.error AssetColumnSettingsBodyInvalid; the body is not JSON or has neither columns nor order."),
 *     @OA\Response(response=500, description="The settings could not be stored because the SimpleRisk upgrade has not been run (data.error AssetColumnSettingsSaveFailed)."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission.")
 * )
 */
class OpenApiSaveAssetColumnSettings {}

/**
 * @OA\Post(
 *     path="/assets/bulk",
 *     summary="Run a bulk action on many assets",
 *     description="Applies one action to a set of assets, chosen either by explicit ids or by the same filter the Manage assets list uses (resolved under the caller's team scope). Each asset is processed independently and reported; the response is 200 even when some assets fail. Requires the asset permission plus the action's own permission: verify needs asset_verify, delete needs asset_delete, assign_teams needs asset_edit, add_to_group needs asset_group_edit. assign_teams adds the teams to each asset's existing teams (nothing is removed); under Team Separation a caller may only assign teams they belong to. At most 5000 assets per request, and at most 2000 for delete (every deleted asset is audited and fires the asset.deleted workflow event). The filter is validated strictly: an unknown key, a non-numeric id, a verified value other than 0, 1, true, false or the strings 0, 1, true, false (false means unverified, never no filter), a q that is not a string, or more than 500 values in one id list is rejected with 422 rather than ignored, and an empty filter is rejected; to act on every asset in the caller's scope send the filter {all: true} on its own. Send expected_count with the number of assets the client showed: when the selection now resolves to a different number the request is refused with 409 and nothing is changed. expected_count is required for a delete by filter (including all). If a chunk of a large delete fails, the assets already deleted are reported ok, that chunk's assets error (message Failed) and the rest error with message AssetBulkReasonNotAttempted. Errors carry a machine code in data.error (the language key of the translated status_message).",
 *     operationId="bulkAssetAction",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(type="object", required={"action"},
 *             @OA\Property(property="action", type="string", enum={"verify","delete","assign_teams","add_to_group"}),
 *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), description="Asset ids. Provide exactly one of ids or filter."),
 *             @OA\Property(property="filter", type="object", description="The GET /assets filters: verified (0 or 1), q (string), and id lists team, location, tag, group, valuation (arrays of at most 500 integers), categorization and band (Asset Scoring level ids 1 Low, 2 Moderate, 3 High), confidentiality, integrity and availability (rating ids 1 Low, 2 Moderate, 3 High, and 0 Not applicable for confidentiality only); an id a key does not offer is rejected with 422 AssetBulkFilterNotApplied. Or all: true on its own for every asset in scope. Unknown keys and empty filters are rejected.",
 *                 @OA\Property(property="all", type="boolean", enum={true}, description="Every asset in the caller's scope. Must be the only key."),
 *                 @OA\Property(property="verified", description="1, true, the string 1 or true for verified; 0, false, the string 0 or false for unverified."),
 *                 @OA\Property(property="q", type="string"),
 *                 @OA\Property(property="team", type="array", @OA\Items(type="integer"), maxItems=500),
 *                 @OA\Property(property="location", type="array", @OA\Items(type="integer"), maxItems=500),
 *                 @OA\Property(property="tag", type="array", @OA\Items(type="integer"), maxItems=500),
 *                 @OA\Property(property="group", type="array", @OA\Items(type="integer"), maxItems=500),
 *                 @OA\Property(property="valuation", type="array", @OA\Items(type="integer"), maxItems=500),
 *                 @OA\Property(property="categorization", type="array", @OA\Items(type="integer", minimum=1, maximum=3), description="FIPS 199 categorization level ids (any match; unscored assets never match)."),
 *                 @OA\Property(property="band", type="array", @OA\Items(type="integer", minimum=1, maximum=3), description="Weighted band level ids (any match; unscored assets never match)."),
 *                 @OA\Property(property="confidentiality", type="array", @OA\Items(type="integer", minimum=0, maximum=3), description="Confidentiality rating ids: 1 Low, 2 Moderate, 3 High, 0 Not applicable (any match; an unset rating never matches)."),
 *                 @OA\Property(property="integrity", type="array", @OA\Items(type="integer", minimum=1, maximum=3), description="Integrity rating ids (any match; an unset rating never matches)."),
 *                 @OA\Property(property="availability", type="array", @OA\Items(type="integer", minimum=1, maximum=3), description="Availability rating ids (any match; an unset rating never matches).")
 *             ),
 *             @OA\Property(property="expected_count", type="integer", minimum=0, description="The number of assets the client expects the selection to resolve to (the N of a Select all N). Required for delete by filter; optional otherwise. A different count is refused with 409 AssetBulkCountMismatch."),
 *             @OA\Property(property="params", type="object",
 *                 @OA\Property(property="team_ids", type="array", @OA\Items(type="integer"), description="Required for assign_teams."),
 *                 @OA\Property(property="group_id", type="integer", description="Required for add_to_group.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Processed. Check each result.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="results", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="status", type="string", enum={"ok","not_found","error"}, description="not_found covers both an asset that does not exist and one outside the caller's scope; the two are deliberately indistinguishable."),
 *                 @OA\Property(property="message", type="string")
 *             )),
 *             @OA\Property(property="summary", type="object",
 *                 @OA\Property(property="ok", type="integer"),
 *                 @OA\Property(property="failed", type="integer")
 *             )
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: data.error is AssetBulkBodyInvalid, AssetBulkActionRequired, AssetBulkSelectionRequired (not exactly one of ids or filter), AssetBulkIdsRequired, AssetBulkFilterInvalid (filter is not an object) or AssetBulkNoMatch (the selection matches no assets)."),
 *     @OA\Response(response=403, description="FORBIDDEN: Missing the permission for this action."),
 *     @OA\Response(response=409, description="CONFLICT: data.error AssetBulkCountMismatch; the selection resolves to data.actual assets, not the data.expected the client sent. Nothing was changed."),
 *     @OA\Response(response=422, description="UNPROCESSABLE: data.error is AssetBulkUnknownAction, AssetBulkTooManyAssets or AssetBulkTooManyToDelete (data.max gives the limit), AssetBulkIdsInvalid, AssetBulkFilterUnknownKey, AssetBulkFilterBadValue, AssetBulkFilterTooManyValues or AssetBulkFilterAllAlone or AssetBulkFilterNotApplied (data.key names the filter where relevant), AssetBulkFilterEmpty, AssetBulkExpectedCountInvalid, AssetBulkExpectedCountRequired, AssetBulkParamsInvalid, AssetBulkTeamsRequired, AssetBulkTeamsNotFound, AssetBulkTeamsNotMember or AssetBulkGroupNotFound."),
 * )
 */
class OpenApiBulkAssetAction {}

/**
 * @OA\Schema(
 *     schema="AssetDiscoveryRun",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="range", type="string", example="192.0.2.0/24"),
 *     @OA\Property(property="resolve_names", type="boolean"),
 *     @OA\Property(property="add_as_verified", type="integer", enum={0,1}, description="Whether discovered assets are added verified. Set from the requester's asset_verify permission (or the auto-verify setting) when the run was started; never taken from the request."),
 *     @OA\Property(property="team_ids", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="status", type="string", enum={"queued","running","completed","failed","cancelled"}),
 *     @OA\Property(property="total_hosts", type="integer"),
 *     @OA\Property(property="hosts_scanned", type="integer"),
 *     @OA\Property(property="live_hosts", type="integer"),
 *     @OA\Property(property="new_assets", type="integer"),
 *     @OA\Property(property="error", type="string", description="A translated, generic reason the run failed; empty unless status is failed."),
 *     @OA\Property(property="created_by", type="integer"),
 *     @OA\Property(property="created_by_name", type="string"),
 *     @OA\Property(property="created_at", type="string"),
 *     @OA\Property(property="started_at", type="string"),
 *     @OA\Property(property="finished_at", type="string"),
 *     @OA\Property(property="created_display", type="string", description="created_at in the instance's date format."),
 *     @OA\Property(property="started_display", type="string"),
 *     @OA\Property(property="finished_display", type="string"),
 *     @OA\Property(property="probe_method", type="string", enum={"", "icmp_dgram", "icmp_raw", "ping_binary", "tcp"}, description="How the worker probed hosts: an unprivileged ICMP ping socket, a raw ICMP socket, the ping command, or TCP connects. Empty until the run starts."),
 *     @OA\Property(property="probe_method_label", type="string", description="The translated name of probe_method (empty until the run starts)."),
 *     @OA\Property(property="tcp_ports", type="string", description="The run's TCP ports (comma-separated): the per-run override, or the default ports a TCP-probed run used. Empty otherwise.")
 * )
 */
class OpenApiAssetDiscoveryRunSchema {}

/**
 * @OA\Post(
 *     path="/assets/discovery-runs",
 *     summary="Start a background asset discovery run",
 *     description="Queues a scan of an IPv4 range. The whole range must lie inside the ranges the system administrator allows in config.php ($asset_discovery_allowed_ranges); without that list nothing can be scanned. Each address that answers the probe is added as an asset (named by its reverse DNS name when resolve_names is on, otherwise by its address); addresses and names that already exist as assets are skipped. The scan runs in the background queue. Requires the asset and asset_discovery permissions. Whether new assets are added verified is decided from the caller's asset_verify permission and the auto-verify setting; any verified value in the body is ignored. Under Team Separation a non-admin may only assign teams they belong to. A user may have at most 3 queued or running runs.",
 *     operationId="createAssetDiscoveryRun",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(type="object", required={"range"},
 *             @OA\Property(property="range", type="string", description="One IPv4 address, a start-end range (a.b.c.d-e.f.g.h) or an IPv4 CIDR block; at most 65536 addresses.", example="192.0.2.0/24"),
 *             @OA\Property(property="resolve_names", type="boolean", default=true, description="Look up host names by reverse DNS."),
 *             @OA\Property(property="team_ids", type="array", @OA\Items(type="integer"), description="Teams to assign every new asset to."),
 *             @OA\Property(property="tcp_ports", type="string", description="Optional comma-separated TCP ports (1-65535, at most 20) for this run, used only when the worker probes with TCP connects. Blank uses the administrator's default ports.", example="22,443,3389")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Queued.", @OA\JsonContent(type="object", @OA\Property(property="data", type="object", @OA\Property(property="run", ref="#/components/schemas/AssetDiscoveryRun")))),
 *     @OA\Response(response=403, description="FORBIDDEN: Missing the asset or asset_discovery permission."),
 *     @OA\Response(response=422, description="UNPROCESSABLE: data.error is DiscoveryRangeInvalid, DiscoveryRangeTooLarge (data.max gives the limit), DiscoveryRangeReserved (the range touches 0.0.0.0/8, 127.0.0.0/8, 169.254.0.0/16, 224.0.0.0/4 or 240.0.0.0/4), DiscoveryResolveNamesInvalid, DiscoveryTeamsInvalid, DiscoveryPortsInvalid (tcp_ports is not a list of at most 20 ports between 1 and 65535), DiscoveryNotConfigured (the system administrator has not set $asset_discovery_allowed_ranges in config.php, so nothing may be scanned) or DiscoveryRangeNotAllowed (some address of the range is outside those allowed ranges)."),
 *     @OA\Response(response=429, description="TOO MANY REQUESTS: the caller already has 3 queued or running runs (data.error DiscoveryTooManyActiveRuns), or the instance already has 10 (DiscoveryTooManyActiveRunsInstance); data.max gives the limit."),
 *     @OA\Response(response=500, description="The run could not be queued (data.error DiscoveryRunQueueFailed)."),
 * )
 */
class OpenApiCreateAssetDiscoveryRun {}

/**
 * @OA\Get(
 *     path="/assets/discovery-runs",
 *     summary="List asset discovery runs",
 *     description="Recent discovery runs, newest first. Requires the asset and asset_discovery permissions. Under Team Separation a non-admin sees only their own runs.",
 *     operationId="listAssetDiscoveryRuns",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="per_page", in="query", required=false, description="1 to 50.", @OA\Schema(type="integer", default=10)),
 *     @OA\Response(response=200, description="The runs.", @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *         @OA\Property(property="runs", type="array", @OA\Items(ref="#/components/schemas/AssetDiscoveryRun")),
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="active", type="integer", description="How many of the visible runs are queued or running."),
 *         @OA\Property(property="page", type="integer"),
 *         @OA\Property(property="per_page", type="integer")
 *     ))),
 *     @OA\Response(response=403, description="FORBIDDEN: Missing the asset or asset_discovery permission."),
 * )
 */
class OpenApiListAssetDiscoveryRuns {}

/**
 * @OA\Get(
 *     path="/assets/discovery-runs/capabilities",
 *     summary="Get the probe method asset discovery uses",
 *     description="Which probe method a new discovery run would use and which methods are available. The data comes from the background worker's own detection when it recorded one in the last 7 days (source worker); otherwise it is detected in the web server's process (source web), which can differ from the worker's, because the two may run as different users with different groups and capabilities. Requires the asset and asset_discovery permissions.",
 *     operationId="getAssetDiscoveryCapabilities",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="The probe capabilities.", @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *         @OA\Property(property="method", type="string", enum={"icmp_dgram", "icmp_raw", "ping_binary", "tcp"}, description="The method a new run would use (best available, or the forced method when it is available)."),
 *         @OA\Property(property="method_label", type="string", description="The translated name of method."),
 *         @OA\Property(property="methods_available", type="array", @OA\Items(type="string")),
 *         @OA\Property(property="forced_method", type="string", description="The asset_discovery_probe_method setting: auto, or a forced method."),
 *         @OA\Property(property="reasons", type="array", @OA\Items(type="string"), description="Why methods are unavailable, as method:code (for example icmp_raw:no_cap_net_raw). Administrators only; empty for other callers."),
 *         @OA\Property(property="source", type="string", enum={"worker", "web"}),
 *         @OA\Property(property="detected_at", type="string", description="When the worker detected it (empty for source web)."),
 *         @OA\Property(property="tcp_ports", type="string", description="The default TCP ports for the TCP probe."),
 *         @OA\Property(property="tcp_ports_max", type="integer", description="Most ports a run may probe."),
 *         @OA\Property(property="can_edit_ports", type="boolean", description="Whether the caller may change the default ports (administrators)."),
 *         @OA\Property(property="configured", type="boolean", description="Whether config.php allows any discovery target ($asset_discovery_allowed_ranges). When false, every run is refused."),
 *         @OA\Property(property="allowed_ranges", type="array", @OA\Items(type="string"), description="The IPv4 addresses and CIDR blocks discovery may scan (normalised).")
 *     ))),
 *     @OA\Response(response=403, description="FORBIDDEN: Missing the asset or asset_discovery permission."),
 * )
 */
class OpenApiGetAssetDiscoveryCapabilities {}

/**
 * @OA\Get(
 *     path="/assets/discovery-runs/{id}",
 *     summary="Get an asset discovery run",
 *     operationId="getAssetDiscoveryRun",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="The run.", @OA\JsonContent(type="object", @OA\Property(property="data", type="object", @OA\Property(property="run", ref="#/components/schemas/AssetDiscoveryRun")))),
 *     @OA\Response(response=403, description="FORBIDDEN: Missing the asset or asset_discovery permission."),
 *     @OA\Response(response=404, description="NOT FOUND: No such run, or it is outside the caller's scope."),
 * )
 */
class OpenApiGetAssetDiscoveryRun {}

/**
 * @OA\Delete(
 *     path="/assets/discovery-runs/{id}",
 *     summary="Cancel an asset discovery run (the run is kept, not deleted)",
 *     description="Despite the DELETE verb this CANCELS the run: the run record is kept (status cancelled) and stays in the run list and GET /assets/discovery-runs/{id}; nothing is removed. A queued run stops before it starts and a running scan stops at its next checkpoint. Assets already added stay. Only the user who started the run or an admin may cancel it. A run that already finished answers 409.",
 *     operationId="cancelAssetDiscoveryRun",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Cancelled.", @OA\JsonContent(type="object", @OA\Property(property="data", type="object", @OA\Property(property="run", ref="#/components/schemas/AssetDiscoveryRun")))),
 *     @OA\Response(response=403, description="FORBIDDEN: Missing the permission, or the run belongs to another user."),
 *     @OA\Response(response=404, description="NOT FOUND: No such run, or it is outside the caller's scope."),
 *     @OA\Response(response=409, description="CONFLICT: The run already finished (data.error DiscoveryRunAlreadyFinished)."),
 * )
 */
class OpenApiCancelAssetDiscoveryRun {}

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
 *                 @OA\Property(property="verified", type="boolean", description="Honoured only for a user with asset_verify: 1 or true creates a verified asset, 0 or false an unverified one. When omitted, or sent by a user without asset_verify, the default applies: verified for a user with asset_verify or when automatic verification of new assets is on, otherwise unverified."),
 *                 @OA\Property(property="confidentiality", type="string", enum={"low", "moderate", "high", "not_applicable", ""}, description="FIPS 199 potential impact on confidentiality. Omitted or empty leaves it not set (the API never applies the Preferences default for new assets). Any other value is a 400 and nothing is created."),
 *                 @OA\Property(property="integrity", type="string", enum={"low", "moderate", "high", ""}, description="FIPS 199 potential impact on integrity. Omitted or empty leaves it not set. not_applicable is not accepted here."),
 *                 @OA\Property(property="availability", type="string", enum={"low", "moderate", "high", ""}, description="FIPS 199 potential impact on availability. Omitted or empty leaves it not set. not_applicable is not accepted here."),
 *                 @OA\Property(property="associated_risks[]", type="array", @OA\Items(type="integer"), description="Risk IDs to associate. Requires the Risk Management permission."),
 *                 @OA\Property(property="control_maturity[]", type="array", @OA\Items(type="integer"), description="Control maturity values, paired by index with control_id[]."),
 *                 @OA\Property(property="control_id[]", type="array", @OA\Items(type="string"), description="Control IDs for the maturity at the same index: one ID, a comma-separated list, or control_id[<index>][] for several."),
 *                 @OA\Property(property="mapped_controls[]", type="array", @OA\Items(type="string"), description="Alternative to the index pairs: one JSON object per maturity with a control_maturity number and a control_id array of control IDs. A control may be mapped at one maturity only."),
 *                 @OA\Property(property="template_group_id", type="integer", description="Asset template group to file the asset under (Customization Extra). Falls back to the default asset group when the caller may not use the requested group."),
 *                 @OA\Property(property="custom_field[<id>]", type="string", description="Custom field value keyed by custom field ID (send an array for multi-value fields). Only fields of the asset's template group are stored.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Asset created successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing name, duplicate name, a control mapped at two maturities, a confidentiality, integrity or availability value the objective does not accept, or validation error."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission, the body carries mapped-controls input (mapped_controls, control_maturity or control_id) and the caller lacks the Governance permission, or the body carries associated_risks and the caller lacks the Risk Management permission. Nothing is created."),
 *     @OA\Response(response=503, description="SERVICE UNAVAILABLE: The body carries confidentiality, integrity or availability but the SimpleRisk database upgrade that adds Asset Scoring has not run yet (data.error AssetScoringUpgradePending). Nothing is created. A body without them is unaffected."),
 * )
 */
class OpenApiCreateAsset {}

/**
 * @OA\Patch(
 *     path="/assets/{id}",
 *     summary="Update an existing asset",
 *     description="Partial update: any field omitted from the request keeps its stored value, including `tags[]`, `associated_risks[]`, the control mapping and custom fields. To clear a field, send it explicitly with an empty value (e.g. `ip=`, `details=`, `location[]=`, `associated_risks[]=`, `mapped_controls[]=`) — omitting it preserves rather than clears. The name cannot be blank. Requires the asset permission plus asset_edit, except a request whose only field is verified (verify or unverify), which requires asset_verify instead. A verified value is honoured only for a user with asset_verify. A verified asset whose name or IP address is changed by a user without asset_verify goes back to unverified. An asset the caller cannot access returns exactly the same 404 response as an asset that does not exist.",
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
 *                 @OA\Property(property="team[]", type="array", @OA\Items(type="integer"), description="Team IDs. teams[] is accepted as an alias."),
 *                 @OA\Property(property="details", type="string", description="Asset details."),
 *                 @OA\Property(property="tags[]", type="array", @OA\Items(type="string"), description="Tags to apply. Omit to preserve existing tags."),
 *                 @OA\Property(property="verified", type="boolean", description="Honoured only for a user with asset_verify. 1, true or on verifies the asset; 0, false or off returns it to unverified; any other value is ignored."),
 *                 @OA\Property(property="confidentiality", type="string", enum={"low", "moderate", "high", "not_applicable", ""}, description="FIPS 199 potential impact on confidentiality. An empty value clears it (not set). Any other value is a 400. Needs asset_edit, also when sent beside verified. Each change is written to the asset's audit trail."),
 *                 @OA\Property(property="integrity", type="string", enum={"low", "moderate", "high", ""}, description="FIPS 199 potential impact on integrity. An empty value clears it. not_applicable is not accepted here."),
 *                 @OA\Property(property="availability", type="string", enum={"low", "moderate", "high", ""}, description="FIPS 199 potential impact on availability. An empty value clears it. not_applicable is not accepted here."),
 *                 @OA\Property(property="associated_risks[]", type="array", @OA\Items(type="integer"), description="Risk IDs to associate. Requires the Risk Management permission. Risks the caller cannot see are neither added nor removed."),
 *                 @OA\Property(property="control_maturity[]", type="array", @OA\Items(type="integer"), description="Control maturity values, paired by index with control_id[]. Replaces the whole mapping."),
 *                 @OA\Property(property="control_id[]", type="array", @OA\Items(type="string"), description="Control IDs for the maturity at the same index: one ID, a comma-separated list, or control_id[<index>][] for several."),
 *                 @OA\Property(property="mapped_controls[]", type="array", @OA\Items(type="string"), description="Alternative to the index pairs: one JSON object per maturity with a control_maturity number and a control_id array of control IDs. Replaces the whole mapping; send mapped_controls[]= with no value to clear it. A control may be mapped at one maturity only."),
 *                 @OA\Property(property="custom_field[<id>]", type="string", description="Custom field value keyed by custom field ID (send an array for multi-value fields). Only fields of the asset's template group are stored; other custom fields keep their values.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Asset updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Blank name, duplicate name, a control mapped at two maturities, a required custom field sent empty, a confidentiality, integrity or availability value the objective does not accept (nothing is written), or validation error."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission, or the body carries mapped-controls input (mapped_controls, control_maturity or control_id, including the clear marker) and the caller lacks the Governance permission, or the body carries associated_risks (including the clear marker) and the caller lacks the Risk Management permission. Nothing is written."),
 *     @OA\Response(response=404, description="NOT FOUND: The asset does not exist or the caller may not access it (one response for both)."),
 *     @OA\Response(response=503, description="SERVICE UNAVAILABLE: The body carries confidentiality, integrity or availability (a clear included) but the SimpleRisk database upgrade that adds Asset Scoring has not run yet (data.error AssetScoringUpgradePending). Nothing is written. A body without them is unaffected."),
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
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 *     @OA\Response(response=404, description="NOT FOUND: The asset does not exist or the caller may not access it (one response for both)."),
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
 *             @OA\Property(property="risks", type="array", @OA\Items(type="object"), description="Risk objects associated with this asset that the caller may see (Team Separation). Empty without the Risk Management permission.")
 *         ))
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission or no access to this asset."),
 *     @OA\Response(response=404, description="NOT FOUND: Asset ID not found."),
 * )
 */
class OpenApiGetAssetAssociationsById {}

/**
 * @OA\Get(
 *     path="/assets/{id}/audit-trail",
 *     summary="Get the audit trail of one asset",
 *     description="The asset's audit-log entries, newest first (at most 500). Every string is RAW, unescaped text: insert it with text setters only. An asset the caller cannot access returns exactly the same 404 response as an asset that does not exist.",
 *     operationId="getAssetAuditTrail",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="The ID of the asset.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="days",
 *         in="query",
 *         required=false,
 *         description="Look-back window in days: 7, 30, 90 or 365. Any other value uses 365.",
 *         @OA\Schema(type="integer", enum={7, 30, 90, 365}, default=365)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Audit trail retrieved successfully.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="days", type="integer", description="The window that was applied."),
 *             @OA\Property(property="entries", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="timestamp", type="string", description="Stored timestamp (Y-m-d H:i:s)."),
 *                 @OA\Property(property="timestamp_display", type="string", description="Timestamp in the configured date/time format."),
 *                 @OA\Property(property="message", type="string", description="The log message as plain text."),
 *                 @OA\Property(property="user_id", type="integer"),
 *                 @OA\Property(property="user_name", type="string", nullable=true, description="The acting user's name, or null when that user no longer exists.")
 *             ))
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: Missing or invalid asset ID."),
 *     @OA\Response(response=403, description="FORBIDDEN: No asset permission."),
 *     @OA\Response(response=404, description="NOT FOUND: The asset does not exist or the caller may not access it (one response for both)."),
 * )
 */
class OpenApiGetAssetAuditTrail {}

// =====================================================================
// ASSET GROUPS CRUD API
// =====================================================================

/**
 * @OA\Get(
 *     path="/asset-groups",
 *     summary="List asset groups, optionally searched, paged and with per-group aggregates",
 *     operationId="listAssetGroups",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="q", in="query", required=false, description="Case-insensitive substring match on the group name.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="page", in="query", required=false, description="1-based page number (used with per_page).", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="per_page", in="query", required=false, description="Page size, maximum 100. 0 or omitted returns every matching group.", @OA\Schema(type="integer", default=0)),
 *     @OA\Parameter(name="sort", in="query", required=false, description="name (default; in SQL), or one of the aggregates: highest_fips_categorization, highest_weighted_score, highest_weighted_band (level rank / numeric score), locations, tags (the group's first value alphabetically). A group with no value there sorts last in both directions; ties fall back to the group name. Any other value sorts by name.", @OA\Schema(type="string", enum={"name","highest_fips_categorization","highest_weighted_score","highest_weighted_band","locations","tags"}, default="name")),
 *     @OA\Parameter(name="dir", in="query", required=false, description="Sort direction.", @OA\Schema(type="string", enum={"asc","desc"})),
 *     @OA\Parameter(name="team", in="query", required=false, description="Comma-separated team ids: groups with a member the caller can see that carries any of them. Ids that are not digits are dropped (at most 500).", @OA\Schema(type="string")),
 *     @OA\Parameter(name="location", in="query", required=false, description="Comma-separated location ids: groups with a visible member at any of them.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="tag", in="query", required=false, description="Comma-separated tag ids: groups with a visible member carrying any of them.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="categorization", in="query", required=false, description="Comma-separated Asset Scoring level ids (1 Low, 2 Moderate, 3 High): groups whose highest FIPS categorization is one of them. A group with no scored member never matches. Other ids are dropped.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="band", in="query", required=false, description="Comma-separated level ids (1-3): groups whose highest weighted band is one of them. A group with no scored member never matches.", @OA\Schema(type="string")),
 *     @OA\Parameter(name="aggregates", in="query", required=false, description="1/true adds asset_count, max_valuation, teams, risk_count and the three highest_* Asset Scoring results to each group, computed over the members and risks the caller may see.", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Asset groups retrieved successfully.",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="asset_groups", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string"),
 *                     @OA\Property(property="asset_count", type="integer", description="With aggregates: members the caller can see."),
 *                     @OA\Property(property="max_valuation", type="integer", nullable=true, description="With aggregates: asset_values id of the highest-valued visible member."),
 *                     @OA\Property(property="teams", type="array", @OA\Items(type="integer"), description="With aggregates: ids of the caller's visible teams carried by any visible member."),
 *                     @OA\Property(property="risk_count", type="integer", nullable=true, description="With aggregates: risks linked to the group that the caller may see; null without the Risk Management permission."),
 *                     @OA\Property(property="highest_fips_categorization", type="string", nullable=true, enum={"low","moderate","high"}, description="With aggregates: the highest FIPS 199 categorization among the visible members that are scored (all three of confidentiality, integrity and availability set); unscored members are ignored; null when none is scored or before the upgrade has added the scoring columns."),
 *                     @OA\Property(property="highest_weighted_score", type="string", nullable=true, example="2.67", description="With aggregates: the highest weighted score (two decimals) among the visible scored members; null when none is scored."),
 *                     @OA\Property(property="highest_weighted_band", type="string", nullable=true, enum={"low","moderate","high"}, description="With aggregates: the band of highest_weighted_score under the current band thresholds; null when none is scored."),
 *                     @OA\Property(property="locations", type="array", @OA\Items(type="integer"), description="With aggregates: ids of the distinct locations of the visible members, alphabetically by name."),
 *                     @OA\Property(property="tags", type="array", @OA\Items(type="integer"), description="With aggregates: ids of the distinct tags on the visible members, alphabetically.")
 *                 )),
 *                 @OA\Property(property="total", type="integer", description="Groups matching q and the filters (before paging)."),
 *                 @OA\Property(property="page", type="integer"),
 *                 @OA\Property(property="per_page", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 * )
 */
class OpenApiListAssetGroups {}

/**
 * @OA\Get(
 *     path="/asset-groups/column-settings",
 *     summary="Get the caller's Asset groups column settings",
 *     description="Returns the caller's own saved column visibility/order for the Asset groups table on Manage assets (defaults when nothing is saved), plus every column they may enable. Stored separately from GET /assets/column-settings. linked risks (risk_count) is offered only with the Risk Management permission and the three highest_* Asset Scoring columns only once the upgrade has added the scoring columns. Requires the asset permission.",
 *     operationId="getAssetGroupColumnSettings",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Column settings retrieved successfully.",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="column_settings", type="object",
 *                     @OA\Property(property="columns", type="array", description="[key, 1 or 0] pairs; every available key appears once; name is always 1.", @OA\Items(type="array", @OA\Items(type="string"))),
 *                     @OA\Property(property="order", type="array", description="Display order; name is always first.", @OA\Items(type="string"))
 *                 ),
 *                 @OA\Property(property="available_columns", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="key", type="string", enum={"name","asset_count","max_valuation","highest_fips_categorization","highest_weighted_score","highest_weighted_band","risk_count","teams","locations","tags"}),
 *                     @OA\Property(property="label_key", type="string", description="Language key of the label."),
 *                     @OA\Property(property="label", type="string", description="Raw label text; escape once at render."),
 *                     @OA\Property(property="group", type="string", enum={"group"}),
 *                     @OA\Property(property="always_on", type="boolean")
 *                 )),
 *                 @OA\Property(property="default_columns", type="array", description="Keys shown when nothing is saved: name, asset_count, max_valuation, highest_fips_categorization, highest_weighted_score, highest_weighted_band, risk_count (each only when offered to the caller). teams, locations and tags are selectable but not defaults. A saved layout is returned exactly as saved: a column added after the save is off.", @OA\Items(type="string"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="UNAUTHORIZED: Missing or invalid API key."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission.")
 * )
 */
class OpenApiGetAssetGroupColumnSettings {}

/**
 * @OA\Put(
 *     path="/asset-groups/column-settings",
 *     summary="Save the caller's Asset groups column settings",
 *     description="Replaces the caller's own Asset groups column settings. Unknown or unavailable keys are dropped, name is forced on and first, and the stored value is returned. Does not change the Manage assets (GET /assets/column-settings) layout. Requires the asset permission.",
 *     operationId="saveAssetGroupColumnSettings",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="columns", type="array", @OA\Items(type="array", @OA\Items(type="string")), description="[key, 1 or 0] pairs"),
 *             @OA\Property(property="order", type="array", @OA\Items(type="string"))
 *         )
 *     ),
 *     @OA\Response(response=200, description="Saved. Same payload as GET /asset-groups/column-settings."),
 *     @OA\Response(response=400, description="BAD REQUEST: data.error AssetColumnSettingsBodyInvalid; the body is not JSON or has neither columns nor order."),
 *     @OA\Response(response=401, description="UNAUTHORIZED: Missing or invalid API key."),
 *     @OA\Response(response=403, description="FORBIDDEN: Insufficient permission."),
 *     @OA\Response(response=500, description="The settings could not be stored because the SimpleRisk upgrade has not been run (data.error AssetColumnSettingsSaveFailed).")
 * )
 */
class OpenApiSaveAssetGroupColumnSettings {}

/**
 * @OA\Post(
 *     path="/asset-groups/bulk",
 *     summary="Delete many asset groups",
 *     description="Deletes asset groups chosen either by explicit ids or by the same filter the Asset groups list uses (q, team, location, tag, categorization, band; resolved over the members the caller can see), or every group with the filter {all: true} on its own. Each group is deleted exactly as DELETE /asset-groups/{id} deletes it: the group, its memberships and its risk and control links go and one audit line is written per group; the member assets themselves are never deleted. Each id is processed independently and reported: ok, not_found (no such group) or error; the response is 200 even when some fail. Requires the asset and asset_group_delete permissions (403 otherwise). At most 2000 groups per request. The filter is validated strictly like POST /assets/bulk's: an unknown key, a non-numeric id, a level id outside 1-3 or an empty filter is rejected with 422 rather than ignored. A delete by filter must send expected_count, the number of groups the client showed; when the selection now resolves to a different number the request is refused with 409 and nothing is deleted. Errors carry a machine code in data.error (the language key of the translated status_message).",
 *     operationId="bulkDeleteAssetGroups",
 *     tags={"asset_crud"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(type="object", required={"action"},
 *             @OA\Property(property="action", type="string", enum={"delete"}),
 *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), description="Group ids (send ids or filter, not both)."),
 *             @OA\Property(property="filter", type="object", description="The Asset groups list filter: q (string), team / location / tag (id lists), categorization / band (level ids 1-3); or {all: true} alone.",
 *                 @OA\Property(property="q", type="string"),
 *                 @OA\Property(property="team", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="location", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="tag", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="categorization", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="band", type="array", @OA\Items(type="integer")),
 *                 @OA\Property(property="all", type="boolean")
 *             ),
 *             @OA\Property(property="expected_count", type="integer", description="Required with filter: the number of groups the client expects to delete.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Processed. One result per group.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="results", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="status", type="string", enum={"ok","not_found","error"})
 *             )),
 *             @OA\Property(property="summary", type="object",
 *                 @OA\Property(property="ok", type="integer"),
 *                 @OA\Property(property="failed", type="integer")
 *             )
 *         ))
 *     ),
 *     @OA\Response(response=400, description="BAD REQUEST: not a JSON body, no action, no selection or both, an empty id list, or nothing matches (AssetGroupBulkNoMatch)."),
 *     @OA\Response(response=401, description="UNAUTHORIZED: Missing or invalid API key."),
 *     @OA\Response(response=403, description="FORBIDDEN: Requires the asset and asset_group_delete permissions."),
 *     @OA\Response(response=409, description="CONFLICT: expected_count differs from the groups the filter matches now (AssetGroupBulkCountMismatch); nothing was deleted."),
 *     @OA\Response(response=422, description="UNPROCESSABLE: unknown action, an id that is not a whole number, more than 2000 groups (AssetGroupBulkTooManyToDelete), or a filter that is not valid as sent."),
 * )
 */
class OpenApiBulkDeleteAssetGroups {}

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
 *     @OA\Response(response=400, description="BAD REQUEST: Missing name, duplicate name (data.error = duplicate_name), or creation failed. Asset ids the caller cannot access are ignored."),
 *     @OA\Response(response=403, description="FORBIDDEN: Requires the asset and asset_group_create permissions."),
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
 *                 @OA\Property(property="selected_assets[]", type="array", @OA\Items(type="integer"), description="Full replacement list of asset IDs. Omit to keep existing assets; send an empty JSON array to remove every member the caller can see. Members the caller cannot see are always kept.")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Asset group updated successfully."),
 *     @OA\Response(response=400, description="BAD REQUEST: Duplicate name (data.error = duplicate_name) or update failed."),
 *     @OA\Response(response=403, description="FORBIDDEN: Requires the asset and asset_group_edit permissions."),
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
 *     @OA\Response(response=200, description="Asset group deleted successfully. Member assets are not deleted."),
 *     @OA\Response(response=400, description="BAD REQUEST: Deletion failed."),
 *     @OA\Response(response=403, description="FORBIDDEN: Requires the asset and asset_group_delete permissions."),
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
 *     @OA\Parameter(name="per_page", in="query", required=false, description="Page size, maximum 500. When set, assets are {id, name, ip, value, verified, fips_categorization, weighted_score, weighted_band} (the three scoring results null when the asset is not scored), sorted by sort/dir over every visible member before paging, and the response adds total, page and per_page. 0 or omitted returns every full asset object.", @OA\Schema(type="integer", default=0)),
 *     @OA\Parameter(name="page", in="query", required=false, description="1-based page number (used with per_page).", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="sort", in="query", required=false, description="With per_page: name (default), value (the valuation level's rank, not its id or label), fips_categorization, weighted_score or weighted_band. Members with no value for the key (unscored, or a valuation matching no level) sort last in both directions; ties fall back to the name, ascending. Any other value sorts by name.", @OA\Schema(type="string", enum={"name","value","fips_categorization","weighted_score","weighted_band"}, default="name")),
 *     @OA\Parameter(name="dir", in="query", required=false, description="With per_page: sort direction; anything but desc is asc.", @OA\Schema(type="string", enum={"asc","desc"}, default="asc")),
 *     @OA\Response(
 *         response=200,
 *         description="Assets retrieved successfully. Only assets the caller can access are returned.",
 *         @OA\JsonContent(type="object", @OA\Property(property="data", type="object",
 *             @OA\Property(property="assets", type="array", @OA\Items(type="object"), description="Assets belonging to the group."),
 *             @OA\Property(property="total", type="integer", description="With per_page: visible members in the group.")
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
 *     @OA\Response(response=403, description="FORBIDDEN: Requires the asset and asset_group_edit permissions."),
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
 *     @OA\Response(response=403, description="FORBIDDEN: Requires the asset and asset_group_edit permissions, and access to the asset."),
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
 *       description="SimpleRisk asset associations: data.risks lists the associated risks the caller may see (Team Separation); it is empty without the Risk Management permission.",
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
 *      description="Requires the asset and asset_edit permissions. A verified asset whose name or IP address is changed by a user without asset_verify goes back to unverified.",
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
 *         description="FORBIDDEN: Requires the asset and asset_group_create permissions.",
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
 *         description="FORBIDDEN: Requires the asset and asset_group_edit permissions.",
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
 *         description="FORBIDDEN: Requires the asset and asset_group_delete permissions.",
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
 *         description="FORBIDDEN: Requires the asset and asset_group_edit permissions.",
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

/**
 * @OA\Get(
 *     path="/asset-group/options_by_control",
 *     summary="Get asset and asset-group options associated with a control, formatted for a dropdown/select.",
 *     description="Returns the combined list of assets and asset groups associated with the given control id. Used by the governance UI to populate asset-group selectors that filter by a specific control. Requires either the asset or governance permission.",
 *     operationId="getAssetGroupOptionsByControl",
 *     tags={"asset"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         in="query",
 *         name="control_id",
 *         description="The control id to filter associated assets and asset groups by.",
 *         required=true,
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\Parameter(
 *         in="query",
 *         name="control_maturity",
 *         description="Optional control maturity level filter.",
 *         required=false,
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Array of combined asset and asset-group option objects.",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(type="object"),
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="BAD REQUEST: User lacks the asset or governance permission required to read this list.",
 *     ),
 * )
 */
class OpenApiGetAssetGroupOptionsByControl {}

?>