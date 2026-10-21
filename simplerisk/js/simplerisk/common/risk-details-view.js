/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// ----------------------------------------------------------------------
// Read-only Cards renderer for an existing risk's Details tab. Companion to
// risk-details-form.js (the EDIT-mode engine): this module never builds a
// form control, never submits anything -- it lays out label:value pairs in
// the same Card positions /ui/risk/layout describes, using the same
// .sr-qcard/.sr-qfield classes the edit-mode Cards use, so read and edit
// mode look like the same page. See design-system.md #5.
//
// Public surface (window.RiskDetailsView):
//   init(containerSelector, riskId, onLoaded)
//       -- fetch + render into that container. `onLoaded`, if given, is
//          called with the raw {fields, cards, values,
//          supportingDocumentationHtml} payload once the fetch actually
//          lands and renders (i.e. it is skipped the same way the render
//          itself is skipped when a later init()/destroy() on this
//          container superseded this fetch) -- callers can cache this to
//          re-render later via renderFromData() without a re-fetch.
//   renderFromData(containerSelector, data, riskId)
//       -- render already-fetched data (the exact shape passed to
//          onLoaded above) with no network call. For callers that already
//          hold a known-current copy, e.g. to redraw read mode after an
//          Edit-mode Cancel where nothing actually changed. `riskId` is the
//          same id the original init() call used -- callers already keep
//          it alongside the cached data (their own `{riskId, data}` cache
//          shape) for exactly this re-render.
//   destroy(containerSelector)       -- empty the container, drop instance state
//   renderFieldsGrid(fields, values, riskId)
//       -- render one card's worth of fields (sorted by pos_y/pos_x) into a
//          .sr-qgrid via the SAME renderFieldItem() this module's own
//          renderCards() uses. For a caller with its own one-off value set
//          that still wants to look like a genuine Cards field grid --
//          risk-view-review.js's "View All Reviews" history modal.
//   cardIcon(cardKey) / cardLabel(cardKey)
//       -- the same CARD_ICONS/CARD_LABELS lookups renderCards() uses, for
//          a caller building its own card header to match (same history
//          modal use case above).
//
// Every entry point takes an optional record-type `profile` (init()'s and
// renderFromData()'s options, renderFieldsGrid()'s 4th argument, cardIcon()/
// cardLabel()'s 2nd); omitted means the risk profile, i.e. exactly the
// behaviour every risk tab has always had. init() also takes
// honorCardWidths/responsive -- see its own docblock. Render state is per
// container (newRenderContext()), so two live views never share it.
// ----------------------------------------------------------------------

(function () {
    'use strict';

    var CARD_LABELS = {
        general: _lang['CardGeneral'],
        classification: _lang['CardClassification'],
        scoring: _lang['CardScoring'],
        assignment: _lang['Assignment'],
        additional_info: _lang['CardAdditionalInformation'],
        custom_fields: _lang['CardCustomFields'],
        // Mitigation tab (tab_index=2, Phase 4b-iii) card keys
        // (customization_mitigation_cards_layout_card_keys(),
        // includes/functions.php) -- 'assignment' and 'custom_fields' above
        // are shared with Details, so only the 3 Mitigation-only cards need
        // their own label here.
        strategy: _lang['CardMitigationStrategy'],
        solution: _lang['CardMitigationSolution'],
        controls: _lang['CardMitigationControls'],
        // Review tab (tab_index=3, Phase 4c-ii) -- the single curated card
        // customization_review_cards_layout_card_keys() (includes/functions.php)
        // establishes ('review', plus the shared 'custom_fields' catch-all
        // above). 'CardReview' already existed in lang.en.php (used by
        // customization-layout-editor.js's own card picker) but was never
        // wired into either Cards renderer's CARD_LABELS map until now --
        // without this entry the card title fell through to the raw
        // 'review' card_key string.
        review: _lang['CardReview']
    };

    // Card header icon (Font Awesome), keyed identically to CARD_LABELS
    // above. Duplicated from customization-layout-editor.js's
    // CARD_DEFS_BY_CANVAS -- the canonical source, since that editor is
    // where an admin picks/reorders these cards and needs the full
    // key+label+icon triple -- the same "duplicate, not shared module"
    // pattern CARD_LABELS above already uses (see risk-details-form.js's
    // identical CARD_ICONS for the edit-mode side of this same duplication).
    var CARD_ICONS = {
        general: 'fa-circle-info',
        classification: 'fa-tags',
        scoring: 'fa-gauge',
        assignment: 'fa-users',
        additional_info: 'fa-file-lines',
        custom_fields: 'fa-puzzle-piece',
        strategy: 'fa-diagram-project',
        solution: 'fa-file-lines',
        controls: 'fa-shield-halved',
        review: 'fa-clipboard-check'
    };

    // Per-field inline-edit rollout, Details tab only (options.inlineEditable
    // gate below). First pass ("start with the simple single value fields")
    // covered plain text, a native dropdown, and a single-pick selectize
    // (Owner/Owner's Manager). This set now ALSO covers the multi-value
    // widgets (multiselect: Site/Location/Team/Additional Stakeholders/
    // Technology; selectize-tags: Tags; selectize-grouped: Risk/Threat
    // Mapping) -- each swaps in the SAME way, using RiskDetailsForm's own
    // buildStandaloneFieldControl()/activateStandaloneFieldControl(), and
    // Cancel/Save wraps the control in a real <form> so
    // serializeWithEmptyMultiValueMarkers() can turn "the user cleared every
    // selection" into an explicit present-but-empty PATCH field (see the
    // Save handler below for why an absent one would silently fail to
    // clear). richtext, file, and the composite widgets (mitigation-
    // controls/assets-asset-groups/scoring-method/next-step/set-next-
    // review-date) are still NOT in this set -- file needs multipart + an
    // existing-file list, and a composite widget IS the reason it has its
    // own widget type; each is future work, not something this pass
    // silently half-supports. 'richtext' (Risk Assessment/Additional Notes)
    // IS in this set -- HugeRTE mounts inline the same way selectize/
    // bootstrap-multiselect do, via RiskDetailsForm.buildStandaloneFieldControl()/
    // activateStandaloneFieldControl(); its own docblock explains the one
    // thing richtext needs that the others don't (an explicit destroy of
    // the live editor instance before its DOM node is discarded) -- see
    // openInlineEditInstances below for how that gets called.
    // A profile may add its OWN composite types on top of this set
    // (profile.inlineWidgetTypes, see the profiles docblock below); the risk
    // profile adds none.
    var SIMPLE_INLINE_EDIT_WIDGET_TYPES = {
        text: true,
        select: true,
        'selectize-single': true,
        multiselect: true,
        'selectize-tags': true,
        'selectize-grouped': true,
        richtext: true
    };

    // ------------------------------------------------------------------
    // Per-instance render state.
    //
    // Three pieces of render state used to be module globals -- the bag of
    // open inline-edit controls, the "is this render inline-editable" flag
    // and the SupportingDocumentation HTML passthrough
    // (window.__riskDetailsViewSupportingDocumentationHtml). That was safe
    // while each render finished synchronously and only one card stack was
    // editable, but two live views on one page (a record modal over a list,
    // the print view's three tabs) could read each other's values. They now
    // live on the per-container instance below, and every render path is
    // handed that instance (its "render context") explicitly.
    //
    //   openInlineEditInstances  every inline-edit control currently open in
    //       THIS card stack -- a bag of activateStandaloneFieldControl()
    //       return values. Only 'richtext' entries ever hold anything
    //       destroyStandaloneFieldControl() acts on (every other widget
    //       type's entry is inert), but this stays generic: a field's own
    //       Cancel/Save handler destroys+removes its OWN entry in the normal
    //       case, but a DIFFERENT field's save (or any other trigger that
    //       calls renderCards() again) can wipe the container out from under
    //       a still-open editor first. destroyOpenInlineEditInstances() is
    //       the safety net for exactly that.
    //   inlineEditable  set once per renderCards() call (init()'s fetch, or
    //       a cache-only renderFromData() Cancel). Only ever true for the
    //       Details tab (risk-view-details.js is the one coordinator that
    //       passes options.inlineEditable/renderFromData's own inlineEditable
    //       argument).
    //   supportingDocumentationHtml  the values response's server-rendered
    //       SupportingDocumentation HTML, for renderFieldItem().
    // ------------------------------------------------------------------
    var instancesByContainer = {};

    function newRenderContext(containerSelector, profile) {
        return {
            isViewRenderContext: true,
            containerSelector: containerSelector,
            generation: 0,
            // The caller's profile as given (handed on to RiskDetailsForm's
            // standalone widgets) and resolved against RISK_PROFILE.
            rawProfile: profile || null,
            profile: resolveViewProfile(profile),
            openInlineEditInstances: [],
            inlineEditable: false,
            supportingDocumentationHtml: '',
            honorCardWidths: false,
            responsive: false,
            // Arguments of the last init(), so a profile without its own
            // onInlineSaved can re-fetch after an inline save.
            initArgs: null
        };
    }

    function getOrCreateInstance(containerSelector) {
        instancesByContainer[containerSelector] = instancesByContainer[containerSelector] || newRenderContext(containerSelector, null);
        return instancesByContainer[containerSelector];
    }

    // renderFieldsGrid()'s public callers pass nothing, or {profile}; the
    // internal render path passes the instance itself.
    function toRenderContext(ctx) {
        if (ctx && ctx.isViewRenderContext) {
            return ctx;
        }
        return newRenderContext(null, ctx && ctx.profile);
    }

    function destroyOpenInlineEditInstances(ctx) {
        ctx.openInlineEditInstances.forEach(function (instance) {
            if (window.RiskDetailsForm && typeof window.RiskDetailsForm.destroyStandaloneFieldControl === 'function') {
                window.RiskDetailsForm.destroyStandaloneFieldControl(instance);
            }
        });
        ctx.openInlineEditInstances = [];
    }

    function fetchJSON(path, params) {
        return $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2' + path,
            data: params,
            dataType: 'json'
        }).then(function (response) {
            return response.data;
        });
    }

    // Name-to-formName table matching risk-details-form.js's
    // CORE_FIELD_FORM_NAMES exactly -- this must stay in sync with that
    // table, both derive from the same field roster. Keys are the field
    // `name`s /ui/risk/fields returns; values are the keys
    // api_get_ui_risk_values() (Task 1) resolves them under in `values`.
    var CORE_FIELD_FORM_NAMES = {
        Subject: 'subject',
        Category: 'category',
        SiteLocation: 'location',
        RiskSource: 'source',
        ExternalReferenceId: 'reference_id',
        ControlRegulation: 'regulation',
        ControlNumber: 'control_number',
        // Matches api_ui_core_field_resolvers()'s own resolver key
        // (api/v2/includes/api.php), same as risk-details-form.js's
        // identical CORE_FIELD_FORM_NAMES entry.
        JiraIssueKey: 'jira_issue_key',
        Team: 'team',
        AdditionalStakeholders: 'additional_stakeholders',
        Owner: 'owner',
        OwnersManager: 'manager',
        RiskAssessment: 'assessment',
        AdditionalNotes: 'notes',
        Tags: 'tags',
        RiskMapping: 'risk_catalog_mapping',
        ThreatMapping: 'threat_catalog_mapping',
        Technology: 'technology',
        MitigationControls: 'mitigation_controls',
        // Matches api_ui_core_field_resolvers()'s own resolver key
        // (api/v2/includes/api.php) -- a plain comma-joined, group-name-
        // bracketed display string, same shape Team/Technology already
        // render.
        AffectedAssets: 'assets_asset_groups',
        // Matches api_ui_core_field_resolvers()'s own resolver key
        // (api/v2/includes/api.php).
        RiskScoringMethod: 'scoring_method',
        SubmissionDate: 'submission_date',
        SubmittedBy: 'submitted_by',

        // Mitigation tab (tab_index=2, Phase 4b-iii) -- matches
        // risk-details-form.js's own CORE_FIELD_FORM_NAMES entries and
        // api_ui_mitigation_field_resolvers()'s (api/v2/includes/api.php)
        // response keys exactly. MitigationDate resolves here even though
        // the edit engine renders no control for it ('skip'): unlike the
        // Details tab's SubmissionDate/SubmittedBy (also 'skip' there), this
        // module renders every resolved field as an ordinary label:value row
        // regardless of whether edit mode draws a control for it.
        MitigationDate: 'submission_date',
        // Added as MitigationDate's top-row partner on the Strategy card
        // (20260925001 migration, extras/customization/upgrade.php) -- same
        // 'skip'-in-edit-mode, resolved-in-read-mode shape as MitigationDate.
        MitigationSubmittedBy: 'submitted_by',
        MitigationPlanning: 'planning_date',
        PlanningStrategy: 'planning_strategy',
        MitigationEffort: 'mitigation_effort',
        MitigationCost: 'mitigation_cost',
        MitigationOwner: 'mitigation_owner',
        MitigationTeam: 'mitigation_team',
        MitigationPercent: 'mitigation_percent',
        CurrentSolution: 'current_solution',
        SecurityRequirements: 'security_requirements',
        SecurityRecommendations: 'security_recommendations',

        // Review tab (tab_index=3, Phase 4c-ii) -- matches the form-name
        // keys api_ui_review_field_resolvers() (api/v2/includes/api.php)
        // uses, the same table risk-details-form.js's own
        // CORE_FIELD_FORM_NAMES entry for this tab uses. NextReviewDate is
        // resolved here (unlike the edit engine, which has no entry for it
        // -- it renders no control) because this module renders every
        // resolved field as an ordinary label:value row regardless of
        // whether edit mode draws a control for it, same reasoning as
        // MitigationDate above. SetNextReviewDate has NO entry here on
        // purpose: it is a create-only override input (Task 3) with no
        // persisted "current value" api_ui_review_field_resolvers() could
        // ever resolve -- fieldValueEntry() returns null for it and
        // renderFieldItem() skips the row, the same silent-skip
        // renderFieldItem()'s own comment documents for an unrecognized
        // field.
        Review: 'review',
        NextStep: 'next_step',
        Comment: 'comments',
        ReviewDate: 'review_date',
        Reviewer: 'reviewer',
        NextReviewDate: 'next_review_date'
    };

    // Fields with no server-side value resolution (api_ui_core_field_resolvers()
    // deliberately omits them) render the SAME placeholder chip
    // risk-details-form.js's edit mode renders for them -- see that module's
    // populateFieldContent() docblock for why each one is out of scope.
    // Currently empty: AffectedAssets, RiskScoringMethod and JiraIssueKey
    // resolve through the generic fieldValueEntry() path below via
    // CORE_FIELD_FORM_NAMES. AcceptMitigation is no longer relevant here at
    // all -- it moved out of the Cards field roster entirely (a top-level
    // Accept/Reject action widget next to Edit Mitigation now,
    // risk-view-mitigation.js's own renderAcceptMitigationWidget() calling
    // buildAcceptMitigationWidget() below directly) -- so it never reaches
    // renderFieldItem() to hit this map or any other branch. Kept (rather
    // than removed outright) as the landing spot for whatever core field is
    // next out of scope for a real widget.
    var PLACEHOLDER_FIELD_NAMES = {};

    function fieldValueEntry(values, field, profile) {
        if (field.is_basic) {
            var formName = (resolveViewProfile(profile).maps.formNames || {})[field.name];
            return formName ? values[formName] : null;
        }
        return values['custom_field_' + field.id] || null;
    }

    // ------------------------------------------------------------------
    // Profiles -- the read-mode half of risk-details-form.js's RISK_PROFILE
    // (see that docblock for the full key list). This engine reads:
    //   endpoints {fields, layout, values}  values may hold '{id}' or be a
    //                                       function of the id
    //   maps {formNames, cardLabels, cardIcons}  formNames here is the
    //                                       values-response key per field,
    //                                       as above
    //   widgetRegistry.readRender(type, entry, ctx)  a node/jQuery to show
    //                                       as the value, or falsy to fall
    //                                       through to the built-in rendering
    //                                       (a string is shown as TEXT, never
    //                                       parsed as HTML)
    //   inlineSaveUrl(id)                   BASE_URL-relative PATCH target of
    //                                       the inline editor. Fails closed:
    //                                       a non-risk profile without one
    //                                       gets no inline edit at all
    //   onInlineSaved(field, response, ctx) what to do after an inline save;
    //                                       the risk default refreshes the
    //                                       Details tab and record header
    //   onInlineSaveFailed(field, xhr, ctx) optional; return true when the
    //                                       profile handled a failed inline
    //                                       save itself: the engine then shows
    //                                       no toast AND leaves Save disabled,
    //                                       so the profile must close or
    //                                       redraw the row. A hook that throws
    //                                       counts as not handled
    //   inlineWidgetTypes {type: true}      optional; composite widget types
    //                                       the profile's own registry can
    //                                       edit in place, on top of
    //                                       SIMPLE_INLINE_EDIT_WIDGET_TYPES
    //                                       (the asset profile's Asset
    //                                       Scoring selects)
    //   inlineSerialize(form, {field, widgetType})  optional; the inline
    //                                       PATCH body as a urlencoded
    //                                       string, or null for the default
    //                                       (serializeWithEmptyMultiValue
    //                                       Markers() of the field's form).
    //                                       A hook that throws counts as null
    // The whole caller profile is also handed to RiskDetailsForm's
    // standalone widgets, so the inline editor builds and names controls
    // from the same maps the record's edit form does. Maps are replaced
    // wholesale, never merged key by key.
    // ------------------------------------------------------------------
    var RISK_PROFILE = {
        endpoints: {
            templateGroups: '/ui/risk/template_groups',
            fields: '/ui/risk/fields',
            layout: '/ui/risk/layout',
            values: '/ui/risk/{id}/values'
        },
        maps: {
            formNames: CORE_FIELD_FORM_NAMES,
            cardLabels: CARD_LABELS,
            cardIcons: CARD_ICONS
        },
        widgetRegistry: null,
        inlineSaveUrl: function (id) { return '/api/v2/risks/' + id; },
        onInlineSaved: riskInlineSaved,
        inlineWidgetTypes: {},
        inlineSerialize: null
    };

    var resolvedViewProfiles = (typeof window.WeakMap === 'function') ? new window.WeakMap() : null;

    function resolveViewProfile(profile) {
        if (!profile || profile === RISK_PROFILE) {
            return RISK_PROFILE;
        }
        if (profile.__resolvedViewProfile) {
            return profile;
        }
        if (resolvedViewProfiles && resolvedViewProfiles.has(profile)) {
            return resolvedViewProfiles.get(profile);
        }
        var resolved = {
            __resolvedViewProfile: true,
            endpoints: $.extend({}, RISK_PROFILE.endpoints, profile.endpoints || {}),
            maps: $.extend({}, RISK_PROFILE.maps, profile.maps || {}),
            widgetRegistry: profile.widgetRegistry || null,
            // Fails closed: a non-risk profile never inherits the risk
            // PATCH URL -- without its own, the view offers no inline edit.
            inlineSaveUrl: typeof profile.inlineSaveUrl === 'function' ? profile.inlineSaveUrl : null,
            // A non-risk profile without its own hook re-fetches its own view
            // (see the inline Save handler) rather than inheriting the risk
            // page's refresh.
            onInlineSaved: typeof profile.onInlineSaved === 'function' ? profile.onInlineSaved : null,
            // Optional; see the inline Save handler's .fail().
            onInlineSaveFailed: typeof profile.onInlineSaveFailed === 'function' ? profile.onInlineSaveFailed : null,
            // Optional {widgetType: true}: composite types the profile's own
            // registry edits in place. Never inherited from the risk profile.
            inlineWidgetTypes: (profile.inlineWidgetTypes && typeof profile.inlineWidgetTypes === 'object') ? profile.inlineWidgetTypes : {},
            // Optional; see the inline Save handler.
            inlineSerialize: typeof profile.inlineSerialize === 'function' ? profile.inlineSerialize : null
        };
        if (resolvedViewProfiles) {
            resolvedViewProfiles.set(profile, resolved);
        }
        return resolved;
    }

    var warnedOnce = {};

    function warnOnce(key, message) {
        if (warnedOnce[key] || typeof window.console === 'undefined') {
            return;
        }
        warnedOnce[key] = true;
        window.console.warn('RiskDetailsView: ' + message);
    }

    function endpointPath(path, id) {
        if (typeof path === 'function') {
            return path(id);
        }
        return String(path).replace('{id}', encodeURIComponent(id));
    }

    // The risk profile's onInlineSaved -- the Details tab's refresh, exactly
    // as the inline Save handler has always done it.
    function riskInlineSaved(field, response, ctx) {
        var riskId = ctx.recordId;
        // Full re-fetch+re-render, same hook risk.js's own Subject save
        // already calls (see that file's updateSubject() success handler) --
        // guarantees the read-mode cache (risk-view-details.js's own
        // cachedViewData) is refreshed too, so a LATER Edit Details -> Cancel
        // does not regress this field back to its pre-save value. This tab is
        // the only caller that ever renders inline-editable, so
        // window.RiskViewDetails is guaranteed to exist here.
        if (window.RiskViewDetails && typeof window.RiskViewDetails.render === 'function') {
            window.RiskViewDetails.render();
        }
        // Subject is ALSO shown in the top record-header bar (view_top_table(),
        // includes/display.php, rendered via management/partials/overview.php),
        // a separate fetch from this Cards mount that the re-render above
        // never touches -- without this, saving Subject from THIS editor left
        // the header showing the stale pre-save text until a full page
        // reload. Mirrors risk.js's own updateSubject(), which refreshes
        // .overview-container the same way in the other direction.
        if (field.name === 'Subject') {
            $.get(BASE_URL + '/api/v2/management/risk/overview?id=' + riskId).done(function (overviewData) {
                $('.overview-container').html(overviewData.data);
            });
        }
    }

    // ------------------------------------------------------------------
    // CVSS (Phase 4d-iii): the read-mode counterpart of risk-details-
    // form.js's edit-mode buildCvssHolder() -- same card shapes
    // (.sr-cvss-holder-summary/.sr-cvss-metric-group/.sr-cvss-advanced,
    // scss/modules/_questionnaire.scss), same 5 computed scores and live
    // Risk Level tile, just label:value text instead of selects. Only
    // rendered when the RiskScoringMethod entry's raw value is '2' (CVSS)
    // -- every other method keeps the plain "<Method> (...)" text the
    // generic label:value path above already renders; this is appended
    // BELOW that line, not a replacement for it.
    //
    // Deliberately does NOT reuse buildCvssHolder() itself: that function
    // builds live <select>s wired to GridStack grow/shrink machinery this
    // module has no equivalent of (read mode is a plain flow layout, no
    // per-field auto-fit) -- reimplementing the (much simpler) static
    // half here is less code than threading a "read-only" mode through
    // every one of that function's DOM-building/event-wiring helpers.
    //
    // Matches CVSS_FIELDS (risk-details-form.js) exactly -- must stay in
    // sync with that roster, the same relationship CORE_FIELD_FORM_NAMES
    // above has with its own edit-mode counterpart.
    var CVSS_METRIC_LABELS = [
        { name: 'AccessVector', labelKey: 'AttackVector' },
        { name: 'AccessComplexity', labelKey: 'AttackComplexity' },
        { name: 'Authentication', labelKey: 'Authentication' },
        { name: 'ConfImpact', labelKey: 'ConfidentialityImpact' },
        { name: 'IntegImpact', labelKey: 'IntegrityImpact' },
        { name: 'AvailImpact', labelKey: 'AvailabilityImpact' },
        { name: 'Exploitability', labelKey: 'Exploitability' },
        { name: 'RemediationLevel', labelKey: 'RemediationLevel' },
        { name: 'ReportConfidence', labelKey: 'ReportConfidence' },
        { name: 'CollateralDamagePotential', labelKey: 'CollateralDamagePotential' },
        { name: 'TargetDistribution', labelKey: 'TargetDistribution' },
        { name: 'ConfidentialityRequirement', labelKey: 'ConfidentialityRequirement' },
        { name: 'IntegrityRequirement', labelKey: 'IntegrityRequirement' },
        { name: 'AvailabilityRequirement', labelKey: 'AvailabilityRequirement' }
    ];

    // api_resolve_cvss_field_options() (api/v2/includes/api.php) attaches
    // field['{Metric}_options'] -- [{value, name}, ...] -- to the
    // RiskScoringMethod field row for the edit-mode dropdowns' own option
    // lists. Reused here rather than a second lookup mechanism: the raw
    // abbreviation ('N', 'C', ...) this risk stored has no human-readable
    // label of its own anywhere else in the payload (see the resolver
    // functions in api_ui_core_field_resolvers() -- 'display' for each of
    // the 14 metrics is just the raw value again, by design, since their
    // ONLY previous consumer was applyPrefillValue() feeding a <select>
    // that already knows its own option labels).
    function cvssOptionLabel(field, metricName, rawValue) {
        if (!rawValue) {
            return '--';
        }
        var options = field[metricName + '_options'] || [];
        var match = options.filter(function (opt) { return String(opt.value) === String(rawValue); })[0];
        return match ? match.name : rawValue;
    }

    // The same info-icon-plus-Bootstrap-popover pattern scoringSubField()
    // (risk-details-form.js) uses in edit mode, reused here now that read
    // mode also shows help text -- previously omitted on purpose ("read
    // mode's value is already fixed"), reversed per user request. `helpKey`
    // resolution stays per-caller (CVSS derives `<labelKey>Help` inline,
    // same as buildCvssScoreItem() already does; DREAD/OWASP pass their own
    // already-established `<labelKey>Help` keys) -- this helper only
    // builds and wires the icon itself.
    function metricHelpIcon(helpText) {
        if (!helpText) {
            return $();
        }
        var $help = $('<i>')
            .addClass('fa fa-info-circle text-muted sr-scoring-help-icon')
            .attr({
                'data-bs-toggle': 'popover',
                'data-bs-trigger': 'hover focus',
                'data-bs-placement': 'top',
                'data-bs-content': helpText,
                tabindex: '0'
            });
        try {
            new bootstrap.Popover($help[0], { customClass: 'sr-scoring-help-popover' });
        } catch (err) {
            console.error('Failed to initialize scoring help popover:', err);
        }
        return $help;
    }

    function cvssMetricValueRow(labelKey, displayText) {
        // 'Exploitability' is a genuine naming COLLISION with DREAD's own
        // field of the same labelKey -- both would otherwise derive the
        // same lang key ('ExploitabilityHelp'), and since lang.en.php can
        // only hold one value per key, DREAD's (added later in that file)
        // silently won for both. CVSSExploitabilityHelp is CVSS's own,
        // previously-dead copy of that text, now actually reachable.
        var helpKey = labelKey === 'Exploitability' ? 'CVSSExploitabilityHelp' : labelKey + 'Help';
        return $('<div>').addClass('sr-qfield')
            .append($('<label>').addClass('sr-qlabel').text(_lang[labelKey] + ' ').append(metricHelpIcon(_lang[helpKey])))
            .append($('<div>').addClass('sr-qfield-value').text(displayText));
    }

    // Same soft gray panel shape buildCvssHolder()'s metricsSubGroup()
    // gives its edit-mode groups. Each row's own help popover (added via
    // cvssMetricValueRow()'s metricHelpIcon() call) mirrors edit mode's
    // scoringSubField() treatment -- read mode's value is fixed, but the
    // definition of what the value MEANS is still useful without
    // switching to Edit Details.
    function cvssMetricGroup(labelKey, metricNames, field, rawValues) {
        var $stack = $('<div>').addClass('sr-qstack').append(metricNames.map(function (name) {
            var meta = CVSS_METRIC_LABELS.filter(function (m) { return m.name === name; })[0];
            return cvssMetricValueRow(meta.labelKey, cvssOptionLabel(field, name, rawValues[name]));
        }));
        return $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h6>').text(_lang[labelKey]))
            .append($stack);
    }

    // Matches buildCvssScoreDisplay()'s exact markup (risk-details-form.js)
    // -- `vector`, a light CVSS v2 vector caption (e.g. "AV:N/AC:L/Au:N"),
    // stacks directly under the row's own label in the same left-hand
    // column, not under the number on the right (see that function's own
    // comment for why). Static here -- read mode has nothing that could
    // change it, unlike the edit-mode widget's live recompute.
    function cvssScoreRow(labelKey, value, vector) {
        var $label = $('<div>').addClass('sr-cvss-score-label-col')
            .append($('<label>').text(_lang[labelKey] + ':'));
        if (vector) {
            $label.append($('<div>').addClass('sr-cvss-vector').text(vector));
        }
        return $('<div>').addClass('score-item mb-2 d-flex align-items-center')
            .append($label)
            .append($('<div>').addClass('score-value form-control text-end').text(value));
    }

    // A static counterpart of buildRiskLevelPill()/updateRiskLevelPill()
    // (risk-details-form.js -- shared by both CVSS and DREAD since Phase
    // 4d-iii): built once from the risk's own already-
    // computed CurrentScore, painted once via window.CvssRiskLevelPill's
    // shared match()/paint() bridge (that module's own /risk_levels fetch
    // is cached, so this costs no extra request beyond what an edit-mode
    // canvas on the same page already made or will make). No live re-
    // computation -- read mode has nothing that could change the score.
    function cvssRiskLevelTile(score) {
        var $name = $('<span>').addClass('sr-cvss-risk-level-name');
        var $tile = $('<div>').addClass('sr-cvss-risk-level-tile')
            .append($('<span>').addClass('sr-cvss-risk-level-label').text(_lang.RiskLevel))
            .append($('<span>').addClass('sr-cvss-risk-level-score').text(score))
            .append($name);
        if (window.CvssRiskLevelPill && window.CvssRiskLevelPill.match) {
            window.CvssRiskLevelPill.match(score).then(function (level) {
                if (level) {
                    $name.text(level.name);
                    window.CvssRiskLevelPill.paint($tile, level.color);
                }
            });
        }
        return $tile;
    }

    var cvssReadViewAdvancedCounter = 0;

    // Full assembly -- CVSS Score/Risk Level | Base Score Exploitability |
    // Base Score Impact in one row, then the Advanced Metrics accordion,
    // matching buildCvssHolder()'s own $topRow/$advanced shape and CSS
    // classes exactly (scss/modules/_questionnaire.scss's .sr-cvss-* rules
    // are scoped under .sr-qform, which risk-view-details.js's own
    // $formContainer already provides -- see that file's comment on why
    // that class is required there).
    function buildCvssReadView(field, values) {
        if (!window.CvssV2Scoring) {
            return null; // cvss-v2-scoring.js failed to load -- fail closed
                          // rather than render a card with no scores.
        }

        var rawValues = {};
        CVSS_METRIC_LABELS.forEach(function (m) {
            var entry = values[m.name];
            rawValues[m.name] = (entry && entry.raw) || '';
        });
        var scores = window.CvssV2Scoring.computeScores(rawValues);
        var vectors = window.CvssV2Scoring.computeVectors(rawValues);

        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.CVSSScore))
            .append(cvssRiskLevelTile(scores.CurrentScore))
            .append(cvssScoreRow('BaseScore', scores.BaseScore, vectors.Base))
            .append(cvssScoreRow('ExploitabilityScore', scores.ExploitabilitySubscore, vectors.Exploitability))
            .append(cvssScoreRow('ImpactScore', scores.ImpactSubscore, vectors.Impact))
            .append(cvssScoreRow('TemporalScore', scores.TemporalScore, vectors.Temporal))
            .append(cvssScoreRow('EnvironmentalScore', scores.EnvironmentalScore, vectors.Environmental))
            // Legacy score.php's CVSS table (includes/display.php) links out
            // to the CVSS v2 spec; this read-mode card had no equivalent.
            // Same .sr-owasp-methodology-note reuse as buildCvssHolder()'s
            // identical addition (risk-details-form.js) -- see that
            // function's own comment for why the class name is shared
            // despite reading "owasp".
            .append(
                $('<div>').addClass('sr-owasp-methodology-note')
                    .append(document.createTextNode(_lang.CvssMethodologyNote + ' '))
                    .append(
                        $('<a>').attr({
                            href: 'https://www.first.org/cvss/v2/guide',
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        }).text(_lang.Here)
                    )
                    .append(document.createTextNode('.'))
            );

        var $exploitabilityMetrics = cvssMetricGroup('BaseScoreExploitabilityMetrics',
            ['AccessVector', 'AccessComplexity', 'Authentication'], field, rawValues);
        var $impactMetrics = cvssMetricGroup('BaseScoreImpactMetrics',
            ['ConfImpact', 'IntegImpact', 'AvailImpact'], field, rawValues);

        var $topRow = $('<div>').addClass('sr-cvss-metric-row sr-qfield-mb')
            .append($summary).append($exploitabilityMetrics).append($impactMetrics);

        var $temporalMetrics = cvssMetricGroup('TemporalScoreMetrics',
            ['Exploitability', 'RemediationLevel', 'ReportConfidence'], field, rawValues);
        var $environmentalMetrics = cvssMetricGroup('EnvironmentalScoreMetrics',
            ['CollateralDamagePotential', 'TargetDistribution'], field, rawValues);
        var $impactModifiers = cvssMetricGroup('ImpactSubscoreModifiers',
            ['ConfidentialityRequirement', 'IntegrityRequirement', 'AvailabilityRequirement'], field, rawValues);

        var advancedBodyId = 'cvss-view-advanced-' + (++cvssReadViewAdvancedCounter);
        var $advancedBody = $('<div>').attr('id', advancedBodyId).addClass('accordion-collapse collapse')
            .append(
                $('<div>').addClass('sr-cvss-advanced-body')
                    .append(
                        $('<div>').addClass('sr-cvss-metric-row')
                            .append($temporalMetrics)
                            .append($environmentalMetrics)
                            .append($impactModifiers)
                    )
            );
        var $advanced = $('<div>').addClass('sr-qaccordion sr-cvss-advanced')
            .append(
                $('<button>').attr('type', 'button').addClass('sr-qcard-head sr-qacc-head accordion-button collapsed')
                    .attr({ 'data-bs-toggle': 'collapse', 'data-bs-target': '#' + advancedBodyId, 'aria-expanded': 'false' })
                    .append($('<span>').addClass('sr-qcard-htext').append($('<h6>').text(_lang.AdvancedMetrics)))
                    .append($('<span>').addClass('sr-qoptional').text(_lang.Optional))
            )
            .append($advancedBody);

        // No shrink/grow wiring on the accordion toggle (unlike
        // buildCvssHolder()'s 'hidden.bs.collapse' handler) -- this tree
        // lives in plain document flow, not a GridStack item, so Bootstrap's
        // own collapse transition resizes its container correctly with no
        // help needed.
        //
        // 'sr-qform' here is a SCOPING HOOK, not a real form -- every
        // .sr-cvss-*/.sr-qaccordion rule this card needs (scss/modules/
        // _questionnaire.scss) is nested under `.sr-qform { ... }`, unlike
        // .sr-qcard/.sr-qlabel/.sr-qfield-value, which ALSO have unscoped
        // copies in _sr-modal.scss specifically so risk-details-view.js's
        // read mode (no .sr-qform ancestor -- confirmed live, this module's
        // mount is a bare div) can use them. The CVSS block never got that
        // second copy, so without this class every one of this card's own
        // rules -- the metric-group panels, the risk-level tile, the
        // Advanced Metrics accordion's chevron -- silently fails to match
        // and the card renders as unstyled text. '.sr-cvss-view.sr-qform'
        // below (same file) undoes the one part of .sr-qform not wanted
        // here: 72px of bottom padding reserved for an edit form's sticky
        // action bar, which read mode has none of.
        return $('<div>').addClass('sr-cvss-holder sr-cvss-view sr-qform').append($topRow).append($advanced);
    }

    // DREAD's read-mode counterpart of buildDreadHolder() (risk-details-
    // form.js) -- same 2-card shape (Score card + one Metrics card split
    // 3-and-2), just label:value text instead of selects. Only rendered
    // when the RiskScoringMethod entry's raw value is '3' (DREAD). Reuses
    // window.CvssRiskLevelPill's match()/paint() bridge exactly as
    // buildCvssReadView() does -- no new pill implementation, no new
    // /risk_levels fetch.
    function buildDreadReadView(field, values) {
        function dreadRawValue(name) {
            var entry = values[name];
            return (entry && entry.raw) || '0';
        }

        // dreadRawValue()'s argument is the API resolver key (Task 4) --
        // the DREAD-prefixed POST names, not the bare metric names (see
        // Global Constraints). dreadMetricRow()'s first argument below is
        // a DIFFERENT thing -- a $lang label key -- and stays bare.
        var damagePotential = dreadRawValue('DREADDamage');
        var reproducibility = dreadRawValue('DREADReproducibility');
        var exploitability = dreadRawValue('DREADExploitability');
        var affectedUsers = dreadRawValue('DREADAffectedUsers');
        var discoverability = dreadRawValue('DREADDiscoverability');

        var score = Math.round(
            ((Number(damagePotential) + Number(reproducibility) + Number(exploitability)
                + Number(affectedUsers) + Number(discoverability)) / 5) * 100
        ) / 100;

        function dreadMetricRow(labelKey, value) {
            return $('<div>').addClass('sr-qfield')
                .append($('<label>').addClass('sr-qlabel').text(_lang[labelKey] + ' ').append(metricHelpIcon(_lang[labelKey + 'Help'])))
                .append($('<div>').addClass('sr-qfield-value').text(value));
        }

        // Same ".sr-cvss-vector under the score label" caption
        // cvssScoreRow()/owaspScoreRow() above already use -- DREAD's score
        // is a plain 5-term average (dread-scoring.js's own module
        // docblock), so unlike OWASP there is exactly one formula for the
        // whole card, not one per sub-group.
        var $label = $('<div>').addClass('sr-cvss-score-label-col')
            .append($('<label>').text(_lang.DreadScore + ':'))
            .append($('<div>').addClass('sr-cvss-vector').text(
                _lang.DreadScoreFormula
                    .replace('{a}', damagePotential).replace('{b}', reproducibility).replace('{c}', exploitability)
                    .replace('{d}', affectedUsers).replace('{e}', discoverability)
            ));

        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.DreadScore))
            .append(cvssRiskLevelTile(score))
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($label)
                    .append($('<div>').addClass('score-value form-control text-end').text(score))
            );

        // Column-major DOM order -- see buildDreadHolder()'s own comment
        // (risk-details-form.js) and _questionnaire.scss's
        // .sr-dread-metric-columns rule for why this lands Discoverability
        // on the same row as Reproducibility (and AffectedUsers on the same
        // row as DamagePotential), not two independently-heighted stacks.
        var $metrics = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h6>').text(_lang.DreadMetrics))
            .append(
                $('<div>').addClass('sr-dread-metric-columns')
                    .append(dreadMetricRow('DamagePotential', damagePotential))
                    .append(dreadMetricRow('Reproducibility', reproducibility))
                    .append(dreadMetricRow('Exploitability', exploitability))
                    .append(dreadMetricRow('AffectedUsers', affectedUsers))
                    .append(dreadMetricRow('Discoverability', discoverability))
            );

        // 'sr-dread-holder-row' weights the row 1:2 (Metrics card gets 2/3
        // width) -- see buildDreadHolder()'s own comment (risk-details-
        // form.js) and _questionnaire.scss's combined-selector override.
        //
        // 'sr-qform' here is a SCOPING HOOK, not a real form -- same
        // reasoning buildCvssReadView()'s own identical comment gives (this
        // file, ~line 400): every .sr-cvss-*/.sr-dread-* rule this card
        // needs is nested under `.sr-qform { ... }` in _questionnaire.scss.
        // It sits on the OUTER wrapper only, with the grid-layout classes
        // on a NESTED CHILD -- never on the same element -- because a
        // descendant selector (`.sr-qform .sr-cvss-metric-row`) can only
        // match an ANCESTOR/descendant pair, never a class applied to
        // itself. Putting sr-qform on #risk-details-view-container instead
        // (this file's own container, risk-view-details.js) was tried and
        // reverted: that container is the root of the WHOLE read-mode
        // Details tab, not just this card, so it dragged every
        // .sr-qform-scoped rule (card shadows/padding/margins) onto every
        // OTHER read-mode card too, including Classic/Custom's, which have
        // nothing to do with this fix. '.sr-cvss-view.sr-qform'
        // (_questionnaire.scss) already neutralizes the one unwanted side
        // effect (72px of bottom padding meant for an edit form's sticky
        // action bar) for anything carrying both classes together -- exactly
        // what this wrapper does.
        var $row = $('<div>').addClass('sr-cvss-metric-row sr-dread-holder-row').append($summary).append($metrics);
        return $('<div>').addClass('sr-cvss-holder sr-cvss-view sr-qform').append($row);
    }

    // Same LOW/MEDIUM/HIGH categorization owasp-scoring.js's own
    // categoryFor() uses -- duplicated here (not reused) for the same
    // reason buildDreadReadView() duplicates dread-scoring.js's formula
    // instead of calling it: read mode has no live .owasp-holder DOM to
    // read selects from, only raw API values.
    function owaspCategory(avg) {
        if (avg < 3) { return 'LOW'; }
        if (avg < 6) { return 'MEDIUM'; }
        return 'HIGH';
    }

    // Same averaging + 5-branch severity matrix owasp-scoring.js's own
    // averageNamedLevels()/severityScore() compute -- duplicated for the
    // same reason as owaspCategory() above.
    function owaspAverageNamedLevels(names, levels) {
        var matching = levels.filter(function (level) {
            return names.indexOf(level.name) !== -1;
        });
        if (!matching.length) {
            return 0;
        }
        var sum = matching.reduce(function (acc, level) {
            return acc + Number(level.value);
        }, 0);
        return Math.round((sum / matching.length) * 100) / 100;
    }

    function owaspSeverityScore(likelihoodCategory, impactCategory, levels) {
        if (likelihoodCategory === 'LOW' && impactCategory === 'LOW') {
            return 0;
        }
        if (likelihoodCategory === 'HIGH' && impactCategory === 'HIGH') {
            return 10;
        }
        if ((likelihoodCategory === 'LOW' && impactCategory === 'MEDIUM') || (likelihoodCategory === 'MEDIUM' && impactCategory === 'LOW')) {
            return owaspAverageNamedLevels(['Low', 'Medium'], levels);
        }
        if ((likelihoodCategory === 'LOW' && impactCategory === 'HIGH') || (likelihoodCategory === 'MEDIUM' && impactCategory === 'MEDIUM') || (likelihoodCategory === 'HIGH' && impactCategory === 'LOW')) {
            return owaspAverageNamedLevels(['Medium', 'High'], levels);
        }
        return owaspAverageNamedLevels(['High', 'Very High'], levels);
    }

    // DREAD's/CVSS's read-mode cards compute their score fully
    // synchronously (no risk_levels dependency in the formula itself).
    // OWASP's 3 middle severity tiers genuinely need the live risk_levels
    // average, so this returns the card structure immediately with a
    // placeholder score/tile, then patches BOTH the score text and the
    // tile's own name/color together, atomically, once the lookup
    // resolves (Review Focus: never patch one without the other, or a
    // slow resolution could show the right number with the wrong color,
    // or vice versa).
    function buildOwaspReadView(field, values) {
        function owaspRawValue(name) {
            var entry = values[name];
            return Number((entry && entry.raw) || 0);
        }

        var raw = {
            skillLevel: owaspRawValue('OWASPSkillLevel'),
            motive: owaspRawValue('OWASPMotive'),
            opportunity: owaspRawValue('OWASPOpportunity'),
            size: owaspRawValue('OWASPSize'),
            easeOfDiscovery: owaspRawValue('OWASPEaseOfDiscovery'),
            easeOfExploit: owaspRawValue('OWASPEaseOfExploit'),
            awareness: owaspRawValue('OWASPAwareness'),
            intrusionDetection: owaspRawValue('OWASPIntrusionDetection'),
            lossOfConfidentiality: owaspRawValue('OWASPLossOfConfidentiality'),
            lossOfIntegrity: owaspRawValue('OWASPLossOfIntegrity'),
            lossOfAvailability: owaspRawValue('OWASPLossOfAvailability'),
            lossOfAccountability: owaspRawValue('OWASPLossOfAccountability'),
            financialDamage: owaspRawValue('OWASPFinancialDamage'),
            reputationDamage: owaspRawValue('OWASPReputationDamage'),
            nonCompliance: owaspRawValue('OWASPNonCompliance'),
            privacyViolation: owaspRawValue('OWASPPrivacyViolation')
        };

        var threatAgent = (raw.skillLevel + raw.motive + raw.opportunity + raw.size) / 4;
        var vulnerability = (raw.easeOfDiscovery + raw.easeOfExploit + raw.awareness + raw.intrusionDetection) / 4;
        var likelihoodAvg = (threatAgent + vulnerability) / 2;
        var likelihoodCategory = owaspCategory(likelihoodAvg);

        var technicalImpact = (raw.lossOfConfidentiality + raw.lossOfIntegrity + raw.lossOfAvailability + raw.lossOfAccountability) / 4;
        var businessImpact = (raw.financialDamage + raw.reputationDamage + raw.nonCompliance + raw.privacyViolation) / 4;
        var impactAvg = (technicalImpact + businessImpact) / 2;
        var impactCategory = owaspCategory(impactAvg);

        function owaspMetricRow(labelKey, value) {
            return $('<div>').addClass('sr-qfield')
                .append($('<label>').addClass('sr-qlabel').text(_lang[labelKey] + ' ').append(metricHelpIcon(_lang[labelKey + 'Help'])))
                .append($('<div>').addClass('sr-qfield-value').text(value));
        }

        var $name = $('<span>').addClass('sr-cvss-risk-level-name');
        var $scoreSpan = $('<span>').addClass('sr-cvss-risk-level-score').text('0');
        var $tile = $('<div>').addClass('sr-cvss-risk-level-tile')
            .append($('<span>').addClass('sr-cvss-risk-level-label').text(_lang.RiskLevel))
            .append($scoreSpan)
            .append($name);
        var $scoreValue = $('<div>').addClass('score-value form-control text-end').text('0');

        if (window.CvssRiskLevelPill && window.CvssRiskLevelPill.levels) {
            window.CvssRiskLevelPill.levels().then(function (levels) {
                var score = owaspSeverityScore(likelihoodCategory, impactCategory, levels);
                window.CvssRiskLevelPill.match(score).then(function (level) {
                    $scoreSpan.text(score);
                    $scoreValue.text(score);
                    if (level) {
                        $name.text(level.name);
                        window.CvssRiskLevelPill.paint($tile, level.color);
                    }
                });
            });
        }

        // Live calculation formula for one of the 4 named factors, matching
        // the legacy risk-scoring-details panel's "= ( a + b + c + d ) / 4"
        // text (includes/display.php) -- shown as a caption under the
        // factor's OWN score row below, the same relationship CVSS's
        // .sr-cvss-vector caption has to its score label (cvssScoreRow()
        // above), reusing that same class rather than a new one.
        function owaspFormulaText(a, b, c, d) {
            return _lang.OwaspSubgroupFormula
                .replace('{a}', a).replace('{b}', b).replace('{c}', c).replace('{d}', d);
        }

        // Same shape as cvssScoreRow() above (label [+ optional caption] on
        // the left, value on the right) -- OWASP's own version, since its
        // score rows are plain '.score-item's built inline rather than
        // through that CVSS-specific helper.
        function owaspScoreRow(labelKey, value, extraClass, formula) {
            var $label = $('<div>').addClass('sr-cvss-score-label-col')
                .append($('<label>').text(_lang[labelKey] + ':'));
            if (formula) {
                $label.append($('<div>').addClass('sr-cvss-vector').text(formula));
            }
            return $('<div>').addClass('score-item mb-2 d-flex align-items-center' + (extraClass ? ' ' + extraClass : ''))
                .append($label)
                .append($('<div>').addClass('score-value form-control text-end').text(value));
        }

        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.OwaspScore))
            .append($tile)
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col').append($('<label>').text(_lang.OwaspScore + ':')))
                    .append($scoreValue)
            )
            .append(owaspScoreRow('Likelihood', String(Math.round(likelihoodAvg * 100) / 100)))
            .append(owaspScoreRow(
                'ThreatAgentFactors', String(Math.round(threatAgent * 100) / 100), 'sr-owasp-subscore-item',
                owaspFormulaText(raw.skillLevel, raw.motive, raw.opportunity, raw.size)
            ))
            .append(owaspScoreRow(
                'VulnerabilityFactors', String(Math.round(vulnerability * 100) / 100), 'sr-owasp-subscore-item',
                owaspFormulaText(raw.easeOfDiscovery, raw.easeOfExploit, raw.awareness, raw.intrusionDetection)
            ))
            .append(owaspScoreRow('Impact', String(Math.round(impactAvg * 100) / 100)))
            .append(owaspScoreRow(
                'TechnicalImpact', String(Math.round(technicalImpact * 100) / 100), 'sr-owasp-subscore-item',
                owaspFormulaText(raw.lossOfConfidentiality, raw.lossOfIntegrity, raw.lossOfAvailability, raw.lossOfAccountability)
            ))
            .append(owaspScoreRow(
                'BusinessImpact', String(Math.round(businessImpact * 100) / 100), 'sr-owasp-subscore-item',
                owaspFormulaText(raw.financialDamage, raw.reputationDamage, raw.nonCompliance, raw.privacyViolation)
            ))
            // Sits in the OWASP Score column's own open space below the
            // summary numbers -- that column is shorter than the Likelihood/
            // Impact columns beside it (align-items:stretch on their shared
            // grid row just reserves the extra height, it doesn't fill it),
            // matching the same note the legacy risk-scoring-details panel
            // shows (includes/display.php) but through a real lang lookup
            // instead of that panel's hardcoded English string.
            .append(
                $('<div>').addClass('sr-owasp-methodology-note')
                    .append(document.createTextNode(_lang.OwaspMethodologyNote + ' '))
                    .append(
                        $('<a>').attr({
                            href: 'https://owasp.org/www-community/OWASP_Risk_Rating_Methodology',
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        }).text(_lang.Here)
                    )
                    .append(document.createTextNode('.'))
            );

        function owaspReadSubGroup(labelKey, descKey, rows) {
            return $('<div>').addClass('sr-qfield-mb')
                .append($('<div>').addClass('sr-owasp-subgroup-head').text(_lang[labelKey]))
                .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang[descKey]))
                .append($('<div>').addClass('sr-qstack').append(rows));
        }

        var $likelihoodCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.Likelihood))
            .append(owaspReadSubGroup('ThreatAgentFactors', 'ThreatAgentFactorsDescription', [
                owaspMetricRow('SkillLevel', raw.skillLevel),
                owaspMetricRow('Motive', raw.motive),
                owaspMetricRow('Opportunity', raw.opportunity),
                owaspMetricRow('Size', raw.size)
            ]))
            .append(owaspReadSubGroup('VulnerabilityFactors', 'VulnerabilityFactorsDescription', [
                owaspMetricRow('EaseOfDiscovery', raw.easeOfDiscovery),
                owaspMetricRow('EaseOfExploit', raw.easeOfExploit),
                owaspMetricRow('Awareness', raw.awareness),
                owaspMetricRow('IntrusionDetection', raw.intrusionDetection)
            ]));

        var $impactCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.Impact))
            .append(owaspReadSubGroup('TechnicalImpact', 'TechnicalImpactDescription', [
                owaspMetricRow('LossOfConfidentiality', raw.lossOfConfidentiality),
                owaspMetricRow('LossOfIntegrity', raw.lossOfIntegrity),
                owaspMetricRow('LossOfAvailability', raw.lossOfAvailability),
                owaspMetricRow('LossOfAccountability', raw.lossOfAccountability)
            ]))
            .append(owaspReadSubGroup('BusinessImpact', 'BusinessImpactDescription', [
                owaspMetricRow('FinancialDamage', raw.financialDamage),
                owaspMetricRow('ReputationDamage', raw.reputationDamage),
                owaspMetricRow('NonCompliance', raw.nonCompliance),
                owaspMetricRow('PrivacyViolation', raw.privacyViolation)
            ]));

        // Same reasoning as buildDreadReadView()'s own identical comment
        // above -- sr-qform on the OUTER wrapper only, grid-layout classes
        // on a NESTED CHILD.
        var $row = $('<div>').addClass('sr-cvss-metric-row sr-owasp-view').append($summary).append($likelihoodCard).append($impactCard);
        return $('<div>').addClass('sr-cvss-holder sr-cvss-view sr-qform').append($row);
    }

    // Classic's read-mode counterpart of buildClassicHolder() (risk-
    // details-form.js) -- same 3-card shape (Score | Likelihood | Impact,
    // no sub-groups), just label:value text instead of the two live
    // <select>s. Only rendered when the RiskScoringMethod entry's raw
    // value is '1' (Classic, SCORING_METHOD_VALUES.CLASSIC in that file).
    // Reuses window.CvssRiskLevelPill's match()/paint() bridge exactly as
    // every other read view does -- no per-model special case: Classic's
    // score is just a number like any other, with no independently-stored
    // per-cell color (see classic-scoring.js's own module docblock).
    //
    // Duplicates classic-scoring.js's calculateClassic() formula locally
    // rather than calling it -- the same "duplicate, don't reuse the DOM-
    // based edit function" convention buildDreadReadView()/
    // buildOwaspReadView() already establish above: read mode has no live
    // .classic-holder DOM to read <select> values from, only the raw
    // 'likelihood'/'impact' resolver values (api_ui_core_field_resolvers(),
    // api/v2/includes/api.php) already sitting in `values`.
    function classicCalculateRawScore(likelihood, impact, riskModel) {
        switch (riskModel) {
            case 1: return (likelihood * impact) + (2 * impact);
            case 2: return (likelihood * impact) + impact;
            case 3: return likelihood * impact;
            case 4: return (likelihood * impact) + likelihood;
            case 5: return (likelihood * impact) + (2 * likelihood);
            default: return 0;
        }
    }

    function classicCalculateMaxRawScore(likelihoodCount, impactCount, riskModel) {
        switch (riskModel) {
            case 1: return (likelihoodCount * impactCount) + (2 * impactCount);
            case 2: return (likelihoodCount * impactCount) + impactCount;
            case 3: return likelihoodCount * impactCount;
            case 4: return (likelihoodCount * impactCount) + likelihoodCount;
            case 5: return (likelihoodCount * impactCount) + (2 * likelihoodCount);
            default: return 10;
        }
    }

    // Mirrors calculate_risk()'s own range guard (includes/functions.php:
    // 7417: `in_array($impact, range(1, $count_of_impacts)) &&
    // in_array($likelihood, range(1, $count_of_likelihoods))`) -- 1-indexed,
    // inclusive of both ends. Same helper classic-scoring.js's own
    // inRange() computes, duplicated here per this file's "duplicate, don't
    // reuse the DOM-based edit function" convention (see the read-mode
    // comment above classicCalculateRawScore()).
    function classicInRange(value, count) {
        return value >= 1 && value <= count;
    }

    // Same branching classic-scoring.js's calculateClassic() performs: a
    // range guard wrapping the WHOLE formula first (out of range for either
    // value -> default_risk_score, matching calculate_risk()'s own `else`
    // fallback exactly -- functions.php:7460-7463); then, in range, model 6
    // (a stored-grid lookup against custom_risk_model_values, on an
    // already-0-10 scale, normalization skipped entirely) vs. models 1-5
    // (the arithmetic formula above, then the same unconditional
    // normalization step calculate_risk() applies server-side) -- see that
    // file's own module docblock for why model 6 skips the normalization
    // helper rather than routing through it.
    function classicScoreFromConfig(likelihood, impact, config) {
        var risk;
        if (!classicInRange(impact, config.impact_count) || !classicInRange(likelihood, config.likelihood_count)) {
            risk = Number(config.default_risk_score) || 0;
        } else if (config.risk_model === 6) {
            var cell = (config.custom_risk_model_values || []).filter(function (c) {
                return Number(c.impact) === impact && Number(c.likelihood) === likelihood;
            })[0];
            risk = cell ? Number(cell.value) : 0;
        } else {
            risk = classicCalculateRawScore(likelihood, impact, config.risk_model);
            if (config.need_risk_score_normalization) {
                var max = classicCalculateMaxRawScore(config.likelihood_count, config.impact_count, config.risk_model);
                risk = max ? risk * (10 / max) : risk;
            }
        }
        return Math.round(risk * 100) / 100;
    }

    // Classic's score is admin-configured (the same /riskformula/config
    // fetch buildClassicHolder() uses, via window.CvssRiskLevelPill.
    // formulaConfig() -- one cached fetch, shared with every other
    // consumer on the page), so -- same reasoning as buildOwaspReadView()'s
    // own identical async-patch shape above -- this returns the card
    // structure immediately with a placeholder score/tile, then patches
    // BOTH the score text and the tile's own name/color together,
    // atomically, once the config (and the risk_levels match() it feeds
    // into) resolves.
    function buildClassicReadView(field, values) {
        var likelihoodEntry = values.likelihood;
        var impactEntry = values.impact;
        var likelihood = Number((likelihoodEntry && likelihoodEntry.raw) || 0);
        var impact = Number((impactEntry && impactEntry.raw) || 0);
        var likelihoodDisplay = (likelihoodEntry && likelihoodEntry.display) || '';
        var impactDisplay = (impactEntry && impactEntry.display) || '';

        function classicMetricRow(labelKey, value) {
            return $('<div>').addClass('sr-qfield')
                .append($('<label>').addClass('sr-qlabel').text(_lang[labelKey] + ' '))
                .append($('<div>').addClass('sr-qfield-value').text(value));
        }

        var $name = $('<span>').addClass('sr-cvss-risk-level-name');
        var $scoreSpan = $('<span>').addClass('sr-cvss-risk-level-score').text('0');
        var $tile = $('<div>').addClass('sr-cvss-risk-level-tile')
            .append($('<span>').addClass('sr-cvss-risk-level-label').text(_lang.RiskLevel))
            .append($scoreSpan)
            .append($name);
        var $scoreValue = $('<div>').addClass('score-value form-control text-end').text('0');

        // Empty until the async formulaConfig() below resolves and knows
        // which risk_model is configured -- same "build now, patch once
        // resolved" shape the score/pill themselves already use just above.
        // Reuses the legacy RISKClassicExp1-5 lang keys (the same symbolic
        // formula text includes/display.php's classic_scoring_table() has
        // always shown, un-substituted -- unlike OWASP/DREAD's captions,
        // this isn't plugging in live numbers, since risk_model 1-5 don't
        // have a fixed set of named terms to substitute). Model 6 (a custom
        // lookup grid) has no closed-form formula, so it's left blank.
        var $formula = $('<div>').addClass('sr-cvss-vector');

        if (window.CvssRiskLevelPill && window.CvssRiskLevelPill.formulaConfig) {
            window.CvssRiskLevelPill.formulaConfig().then(function (config) {
                var score = classicScoreFromConfig(likelihood, impact, config);
                window.CvssRiskLevelPill.match(score).then(function (level) {
                    $scoreSpan.text(score);
                    $scoreValue.text(score);
                    if (level) {
                        $name.text(level.name);
                        window.CvssRiskLevelPill.paint($tile, level.color);
                    }
                });
                if (config.risk_model >= 1 && config.risk_model <= 5) {
                    // score.php parity: append the SAME "x (10/N)" denominator
                    // the edit-side calculateClassic() (classic-scoring.js)
                    // now writes, computed live (classicCalculateMaxRawScore())
                    // rather than the legacy table's hardcoded 35/30/25/30/35
                    // -- see that file's own comment for why the hardcoded
                    // version is wrong under a customized likelihood/impact
                    // scale.
                    var formulaText = _lang['RISKClassicExp' + config.risk_model];
                    if (config.need_risk_score_normalization) {
                        var maxRawScore = classicCalculateMaxRawScore(config.likelihood_count, config.impact_count, config.risk_model);
                        formulaText += ' x (10/' + maxRawScore + ')';
                    }
                    $formula.text(formulaText);
                }
            });
        }

        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.ClassicScore))
            .append($tile)
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col').append($('<label>').text(_lang.ClassicScore + ':')).append($formula))
                    .append($scoreValue)
            );

        var $likelihoodCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.Likelihood))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.ClassicLikelihoodDescription))
            .append(classicMetricRow('CurrentLikelihood', likelihoodDisplay));

        var $impactCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.Impact))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.ClassicImpactDescription))
            .append(classicMetricRow('CurrentImpact', impactDisplay));

        // Same reasoning as buildDreadReadView()'s/buildOwaspReadView()'s
        // own identical comments above -- sr-qform on the OUTER wrapper
        // only, the grid-layout marker class ('sr-classic-view', this
        // card's own distinct name in the sr-owasp-view/sr-dread-holder-row
        // naming family) on a NESTED CHILD, never both on the same
        // element.
        var $row = $('<div>').addClass('sr-cvss-metric-row sr-classic-view').append($summary).append($likelihoodCard).append($impactCard);
        return $('<div>').addClass('sr-cvss-holder sr-cvss-view sr-qform').append($row);
    }

    // One Contributing Risk factor row, read-mode: Subject + Weight
    // combined into one label (SAME shape buildContributingRiskFactorItem()
    // uses in edit mode, risk-details-form.js), Impact shown as its
    // human-readable option name (falling back to the raw stored value if
    // the option no longer exists in this factor's current impact_options
    // -- a factor's impact ROSTER can also change over time, independent
    // of the factor itself being deleted).
    function contributingRiskMaxOptionValue(options) {
        return (options || []).reduce(function (max, opt) {
            return Math.max(max, Number(opt.value));
        }, 0);
    }

    // Read-mode counterpart of contributing-risk-scoring.js's own
    // maxOptionName() -- see that function's comment for why (score.php
    // parity: the legacy table named the max option, not just its number).
    function contributingRiskMaxOptionName(options) {
        var max = contributingRiskMaxOptionValue(options);
        var match = (options || []).filter(function (opt) {
            return Number(opt.value) === max;
        })[0];
        return match ? match.name : '';
    }

    // No caption here -- the per-factor subscore + formula now live in the
    // Score card instead (buildContributingRiskReadView()'s own per-factor
    // summary row), the same "computed value in the Score card, plain value
    // in its own metric card" split owaspScoreRow()/owaspMetricRow() already
    // have for OWASP.
    function contributingRiskFactorRow(factor, impactValue) {
        var weightPct = Math.round(Number(factor.weight) * 100);
        var impactOption = (factor.impact_options || []).filter(function (opt) {
            return String(opt.value) === String(impactValue);
        })[0];
        var impactDisplay = impactOption ? impactOption.name : String(impactValue);
        return $('<div>').addClass('sr-qfield')
            .append($('<label>').addClass('sr-qlabel').text(factor.subject + ' (' + _lang.Weight + ': ' + weightPct + '%) '))
            .append($('<div>').addClass('sr-qfield-value').text(impactDisplay));
    }

    // Read mode has no live .contributing-risk-holder DOM to read selects
    // from, only raw API values -- same reasoning buildDreadReadView()'s
    // own comment gives for duplicating dread-scoring.js's formula instead
    // of calling it. Fully synchronous (no risk_levels dependency), same
    // shape as buildDreadReadView() -- cvssRiskLevelTile(score) shows the
    // score immediately and patches name/color asynchronously.
    function buildContributingRiskReadView(field, values) {
        var contributingRisks = field.contributing_risks || [];
        var likelihoodOptions = field.contributing_likelihood_options || [];

        var likelihoodEntry = values.ContributingLikelihood;
        var likelihoodValue = Number((likelihoodEntry && likelihoodEntry.raw) || 0);
        var impactsEntry = values.ContributingImpacts;
        var impactsByFactorId = (impactsEntry && impactsEntry.raw) || {};

        var maxLikelihood = contributingRiskMaxOptionValue(likelihoodOptions);
        var likelihoodSum = maxLikelihood ? (likelihoodValue * 5 / maxLikelihood) : 0;

        var impactSum = contributingRisks.reduce(function (sum, factor) {
            var impactValue = Number(impactsByFactorId[factor.id]) || 0;
            var maxImpact = contributingRiskMaxOptionValue(factor.impact_options);
            if (!maxImpact) {
                return sum;
            }
            return sum + Number(factor.weight) * (impactValue * 5 / maxImpact);
        }, 0);

        var score = Math.round((likelihoodSum + impactSum) * 100) / 100;

        // Score card lists the total, then Contributing Likelihood and
        // Contributing Risk (the sum of every factor's term) as subscore
        // rows, with each factor nested one level further under
        // Contributing Risk -- same 2-level shape
        // buildContributingRiskHolder()'s (risk-details-form.js) identical
        // summary rows use; see that function's own comment.
        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.ContributingRiskScore))
            .append(cvssRiskLevelTile(score))
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.ContributingRiskScore + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').text(
                            _lang.ContributingRiskScoreFormula
                                .replace('{likelihood}', Math.round(likelihoodSum * 100) / 100)
                                .replace('{contributing}', Math.round(impactSum * 100) / 100)
                        )))
                    .append($('<div>').addClass('score-value form-control text-end').text(score))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.ContributingLikelihood + ':'))
                        .append(maxLikelihood ? $('<div>').addClass('sr-cvss-vector').text(
                            _lang.ContributingLikelihoodFormula.replace('{value}', likelihoodValue).replace('{max}', maxLikelihood)
                                .replace('{maxName}', contributingRiskMaxOptionName(likelihoodOptions))
                        ) : null))
                    .append($('<div>').addClass('score-value form-control text-end').text(Math.round(likelihoodSum * 100) / 100))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.ContributingRisk + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').text(_lang.ContributingRiskSubtotalFormula)))
                    .append($('<div>').addClass('score-value form-control text-end').text(Math.round(impactSum * 100) / 100))
            )
            .append(contributingRisks.map(function (factor) {
                var impactValue = Number(impactsByFactorId[factor.id]) || 0;
                var maxImpact = contributingRiskMaxOptionValue(factor.impact_options);
                var factorTerm = maxImpact ? (Number(factor.weight) * (impactValue * 5 / maxImpact)) : 0;
                var weightPct = Math.round(Number(factor.weight) * 100);
                return $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item sr-owasp-subscore-item--nested')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(factor.subject + ':'))
                        .append(maxImpact ? $('<div>').addClass('sr-cvss-vector').text(
                            _lang.ContributingFactorFormula.replace('{weight}', weightPct).replace('{impact}', impactValue).replace('{max}', maxImpact)
                                .replace('{maxName}', contributingRiskMaxOptionName(factor.impact_options))
                        ) : null))
                    .append($('<div>').addClass('score-value form-control text-end').text(Math.round(factorTerm * 100) / 100));
            }));

        // scoringSubField-style label ('Likelihood') -- see
        // buildContributingRiskHolder()'s identical comment (risk-details-
        // form.js) for why this field had none before.
        var likelihoodOption = likelihoodOptions.filter(function (opt) {
            return String(opt.value) === String(likelihoodValue);
        })[0];
        var $likelihoodCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.ContributingLikelihood))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.ContributingLikelihoodDescription))
            .append($('<div>').addClass('sr-qfield')
                .append($('<label>').addClass('sr-qlabel').text(_lang.Likelihood + ' '))
                .append($('<div>').addClass('sr-qfield-value').text(likelihoodOption ? likelihoodOption.name : String(likelihoodValue))));

        // '.sr-qstack' (12px gap, _sr-modal.scss) -- missing here before,
        // unlike buildContributingRiskHolder()'s identical edit-mode card
        // (risk-details-form.js), which is why consecutive factor rows read
        // as visually merged (a label right under the previous row's value
        // with no gap between them).
        var $contributingCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.ContributingRisk))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.ContributingRiskDescription))
            .append($('<div>').addClass('sr-qstack').append(contributingRisks.map(function (factor) {
                return contributingRiskFactorRow(factor, impactsByFactorId[factor.id]);
            })));

        // Same reasoning as buildClassicReadView()'s/buildDreadReadView()'s
        // own identical comments -- sr-qform on the OUTER wrapper only, the
        // grid-layout marker class ('sr-contributing-risk-view') on a
        // NESTED CHILD, never both on the same element.
        var $row = $('<div>').addClass('sr-cvss-metric-row sr-contributing-risk-view').append($summary).append($likelihoodCard).append($contributingCard);
        return $('<div>').addClass('sr-cvss-holder sr-cvss-view sr-qform').append($row);
    }
    // ------------------------------------------------------------------

    // Custom's read-mode counterpart of buildCustomHolder() (risk-details-
    // form.js) -- same 2-card shape (Score card + one Custom Value card,
    // 1:2 ratio via 'sr-dread-holder-row', the same ratio class
    // buildDreadReadView() above and buildCustomHolder()'s own edit-mode
    // holder both use), just label:value text instead of a live input.
    // Only rendered when the RiskScoringMethod entry's raw value is '5'
    // (Custom -- SCORING_METHOD_VALUES.CUSTOM, risk-details-form.js).
    //
    // No fallback/formula logic here, unlike buildDreadReadView()'s
    // duplicated averaging math: Custom's score IS the stored value
    // (buildCustomHolder()'s own docblock), and Task 0 already fixed
    // update_risk_scoring()'s Custom branch (includes/functions.php) to
    // fall back to get_setting('default_risk_score') server-side whenever
    // the submitted value was blank or out of range -- so the 'Custom'
    // resolver's raw value (api/v2/includes/api.php) is already the fully-
    // resolved number by the time it reaches here. This just displays it.
    //
    // Reuses window.CvssRiskLevelPill's match()/paint() bridge via
    // cvssRiskLevelTile() exactly as buildDreadReadView() does -- no new
    // pill implementation, no new /risk_levels fetch.
    function buildCustomReadView(field, values) {
        var customEntry = values.Custom;
        var score = Number((customEntry && customEntry.raw) || 0);

        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.CustomScore))
            .append(cvssRiskLevelTile(score))
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col').append($('<label>').text(_lang.CustomScore + ':')))
                    .append($('<div>').addClass('score-value form-control text-end').text(score))
            );

        var $valueCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.CustomValue))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.CustomValueDescription))
            .append($('<div>').addClass('sr-qfield-value').text(score));

        // 'sr-dread-holder-row' weights the row 1:2 (Value card gets 2/3
        // width) -- see buildDreadReadView()'s own comment above and
        // buildCustomHolder()'s own comment (risk-details-form.js) for why
        // this is the shared ratio class rather than a new one.
        //
        // 'sr-qform' here is a SCOPING HOOK, not a real form -- same
        // reasoning buildDreadReadView()'s/buildClassicReadView()'s own
        // identical comments above give: every .sr-cvss-*/.sr-dread-* rule
        // this card needs is nested under `.sr-qform { ... }` in
        // _questionnaire.scss. It sits on the OUTER wrapper only, with the
        // grid-layout classes on a NESTED CHILD -- never on the same
        // element, because a descendant selector
        // (`.sr-qform .sr-cvss-metric-row`) can only match an
        // ancestor/descendant pair, never a class applied to itself.
        var $row = $('<div>').addClass('sr-cvss-metric-row sr-dread-holder-row').append($summary).append($valueCard);
        return $('<div>').addClass('sr-cvss-holder sr-cvss-view sr-qform').append($row);
    }
    // ------------------------------------------------------------------

    // `ctx` is the render context (the view instance, or toRenderContext()'s
    // default for a public renderFieldsGrid() caller) -- see
    // newRenderContext().
    function renderFieldItem(field, values, riskId, ctx) {
        ctx = toRenderContext(ctx);
        var $item = $('<div>').addClass('sr-qfield');

        // Spans both .sr-qgrid columns whenever the field's OWN stored/
        // synthesized geometry says full width (pos_w=6, the full nested
        // grid -- customization_nested_grid_columns(), includes/functions.php),
        // rather than a hardcoded per-field-name list. This is what the admin
        // Cards editor and risk-details-form.js's GridStack rendering already
        // do; risk-details-view.js used to be the one renderer in the family
        // that ignored pos_w and hardcoded 'RiskScoringMethod' by name --
        // which meant Subject (get_subject_synthetic_field_entry(), always
        // full width) and Risk Assessment (now full width by default under
        // Subject, get_risk_details_core_field_card_map()) both silently
        // rendered at half width here despite their real geometry. Driving it
        // from pos_w fixes both and needs no update the next time a field's
        // default width changes.
        //
        // RiskScoringMethod needs its own read-mode card
        // (buildClassicReadView()/buildCvssReadView()/etc. below) to have the
        // full card width regardless of what pos_w says, so it keeps an
        // explicit OR here rather than relying solely on stored geometry.
        // 6 is the full nested grid width (customization_nested_grid_columns(),
        // includes/functions.php) -- hardcoded here the same way
        // risk-details-form.js's own forceFullWidth branch does, since that
        // value is not exposed as a shared JS constant across these modules.
        if (field.name === 'RiskScoringMethod' || Number(field.pos_w) >= 6) {
            $item.addClass('sr-qfield--full');
        } else {
            // Explicit column, not just append-order + CSS auto-flow: every
            // OTHER card here has fields alternating x0/x3 in perfect
            // lockstep with array order, so auto-flow's "next item -> next
            // cell" happened to reproduce the real layout by coincidence --
            // until the Review card's genuinely ASYMMETRIC layout
            // (get_review_card_field_positions(), includes/functions.php: 5
            // fields in column 0, 2 in column 3) broke that coincidence.
            // Confirmed live on risk #1001: NextReviewDate (pos_x=0, stored
            // under NextStep in the SAME column) rendered instead beside
            // NextStep in column 3, because sorted-by-(pos_y,pos_x) append
            // order happened to land it as the "next" item in a plain
            // 2-per-row auto-flow -- append order is not the same thing as
            // column membership once a card's columns hold different
            // numbers of fields. pos_x is authoritative; CSS grid-column
            // pins each field to its real column regardless of append
            // order or a sparse column's own gaps.
            $item.css('grid-column', (Number(field.pos_x) || 0) === 0 ? '1' : '2');
        }

        // Explicit row for the same reason -- CSS grid row TRACKS still
        // auto-size to their tallest occupant across both columns (so a
        // rich-content field in one column still only pushes the rows truly
        // below it, never rows in a column that has nothing at that line),
        // but WHICH track a field lands in must come from its own pos_y,
        // not auto-flow's append-order guess. Every field seeded by this
        // family's own layout code uses pos_h=2 uniformly (confirmed across
        // Details/Mitigation/Review's real stored geometry), so pos_y/2 is
        // a clean 0-based row index.
        var posY = Number(field.pos_y);
        if (isFinite(posY)) {
            $item.css('grid-row', String(Math.floor(posY / 2) + 1));
        }

        if (field.name === 'RiskScoringHistory') {
            // Real, admin-manageable field (addable/removable/movable via
            // the Customization Extra's Cards editor -- see
            // upgrade_customization_extra_20260925001()'s own docblock,
            // extras/customization/upgrade.php, for why it wasn't before)
            // whose value is a chart widget, not a stored/resolved value --
            // same "bypass the generic label:value path" shape as the
            // SupportingDocumentation branch just below, except the widget
            // is built client-side (buildScoringHistoryWidget()) rather
            // than passed through as server-rendered HTML. No label row:
            // the widget draws its own header (the collapse toggle).
            return $item.append(buildScoringHistoryWidget(riskId));
        }

        if (field.name === 'SupportingDocumentation' || field.name === 'MitigationSupportingDocumentation') {
            // Server-rendered HTML passthrough -- see Task 1's
            // api_get_ui_risk_values() docblock for why this ONE field
            // bypasses the generic label:value text path. Phase 4b-iii's
            // MitigationSupportingDocumentation (api_get_ui_risk_mitigation_
            // values()) reuses the SAME passthrough -- each tab's field
            // roster only ever contains ONE such field. Per render context,
            // so two views on one page never show each other's list.
            var $label = $('<label>').addClass('sr-qlabel').text((_lang[field.name] || field.name) + ' ');
            $item.append($label);
            $item.append($('<div>').html(ctx.supportingDocumentationHtml || ''));
            return $item;
        }

        // The brief's condition here was `PLACEHOLDER_FIELD_NAMES[field.name]
        // || (!field.is_basic && false)`. `anything && false` is always
        // false, so that second clause can never be true and the `||`
        // collapses to just the first operand -- simplified here to drop the
        // always-false clause rather than ship dead code.
        if (PLACEHOLDER_FIELD_NAMES[field.name]) {
            $item.addClass('sr-qfield-placeholder');
            $('<span>').addClass('sr-qfield-placeholder-text')
                .text(_lang[field.name] || field.name)
                .appendTo($item);
            return $item;
        }

        var entry = fieldValueEntry(values, field, ctx.profile);
        if (!entry) {
            return null; // SubmissionDate/SubmittedBy always resolve; a
                          // missing entry here means an unrecognized field --
                          // skip rather than render a broken row.
        }

        var $label = $('<label>').addClass('sr-qlabel').text((_lang[field.name] || field.name) + ' ');
        var $value = $('<div>').addClass('sr-qfield-value');

        // `.text()` is the default for EVERY field and stays that way -- a value
        // is plain text unless the server explicitly says otherwise. The rich
        // text (WYSIWYG) fields, Risk Assessment and Additional Notes, store
        // HTML, so api_ui_core_field_resolvers() hands those two an extra
        // `display_html` key holding the value run through the same
        // HTMLPurifier pass the legacy read view applied at its sink. That one
        // key is the opt-in: present -> render as markup (otherwise the user
        // sees literal "<p>"/"<ul>" tags), absent -> render as text. Same
        // narrow, server-declared passthrough shape as
        // supporting_documentation_html above.
        // A multi-value field (Team/Site Location/Technology/Additional
        // Stakeholders/Tags/Risk Mapping/Threat Mapping) carries an extra
        // `names` array alongside `display` (api_ui_core_field_resolvers(),
        // api/v2/includes/api.php) -- one chip per name, read-only (no
        // .sr-chip-x remove button, unlike the editable .sr-chips-field this
        // reuses the chip look from). Falls through to the plain-text branch
        // below when the field has no selections at all, so an empty value
        // still renders as an empty row rather than a stray empty chip row.
        //
        // design-system.md §11: "a missing value is an em dash, never a
        // blank box" -- same rule and .is-empty color Governance's own
        // detail views already apply (dt()/richBlock()/textBlock(),
        // governance-frameworks.js). Only the chips branch is exempt: an
        // empty selection there falls through to the plain-text branch
        // below instead (per the comment above), so it already gets the
        // em dash the same way any other empty field does.
        var isEmptyValue;
        // The form engine's view of this field's widget type, under the same
        // profile -- drives both a profile's readRender hook and the inline
        // editor below. `formProfile` is the caller's own profile object
        // (undefined for risk, so RiskDetailsForm uses its own RISK_PROFILE).
        var formProfile = ctx.rawProfile || undefined;
        var inlineWidgetType = window.RiskDetailsForm && window.RiskDetailsForm.getWidgetType(field, formProfile);
        var readRegistry = ctx.profile.widgetRegistry;
        // Must be a jQuery object or a DOM Node; a string is shown as text,
        // never parsed as HTML (RiskDetailsForm.normalizeWidgetNode()).
        var $customValue = (readRegistry && typeof readRegistry.readRender === 'function' && window.RiskDetailsForm)
            ? window.RiskDetailsForm.normalizeWidgetNode(
                readRegistry.readRender(inlineWidgetType, entry, { field: field, values: values, recordId: riskId, profile: ctx.profile }),
                'readRender')
            : null;
        // AffectedAssets is a MIXED roster (an asset and an asset group are
        // different entities, sharing no table -- get_assets_and_asset_
        // groups_of_type(), includes/assets.php), unlike every other
        // multi-value field's single flat name list. Its own `items` array
        // (api_ui_core_field_resolvers()'s 'assets_asset_groups' resolver,
        // api/v2/includes/api.php) carries a `class` ('asset'/'group') per
        // selection so each chip can say which it is -- the SAME distinction
        // the plain-string `display` already drew with `[bracket]` notation
        // for a group (get_assets_and_asset_groups_of_type_as_string()), just
        // as an icon instead of punctuation now that this renders as chips.
        // Deliberately no color difference: chips stay neutral (design-
        // system.md #11's "reuse the chip system" rule) and the icon alone
        // carries the distinction, the same restraint every other read-mode
        // chip already keeps.
        if ($customValue) {
            $value.append($customValue);
            isEmptyValue = false;
        } else if (field.name === 'AffectedAssets' && Array.isArray(entry.items) && entry.items.length > 0) {
            $value.addClass('sr-qfield-value--chips');
            entry.items.forEach(function (item) {
                var isGroup = item.class === 'group';
                var $chip = $('<span>').addClass('sr-chip');
                $('<i>', {
                    'class': 'fa ' + (isGroup ? 'fa-layer-group' : 'fa-cube') + ' sr-chip-icon',
                    'aria-hidden': 'true',
                    title: isGroup ? _lang['AssetGroup'] : _lang['Asset']
                }).appendTo($chip);
                $chip.append(document.createTextNode(item.name));
                $chip.appendTo($value);
            });
            isEmptyValue = false;
        } else if (Array.isArray(entry.names) && entry.names.length > 0) {
            $value.addClass('sr-qfield-value--chips');
            entry.names.forEach(function (name) {
                $('<span>').addClass('sr-chip').text(name).appendTo($value);
            });
            isEmptyValue = false;
        } else if (typeof entry.display_html === 'string') {
            isEmptyValue = !entry.display_html.trim();
            if (isEmptyValue) {
                $value.text('—');
            } else {
                $value.html(entry.display_html);
            }
        } else if (field.name === 'RiskScoringMethod' && (entry.raw === '1' || entry.raw === '2' || entry.raw === '3' || entry.raw === '4' || entry.raw === '5' || entry.raw === '6')) {
            // The generic "CVSS (Base Score: 7.5)" parenthetical (scoring_
            // method resolver, api/v2/includes/api.php) is redundant once
            // the full CVSS card below shows all 5 scores -- strip it back
            // to the bare method name. Classic's OWN parenthetical
            // ("Classic (Current Likelihood: X, Current Impact: Y)") gets
            // the same treatment now that the full Classic card below
            // restates those two values itself -- unlike before this task,
            // when it had no equivalent read-mode card and stayed
            // untouched. Custom's own parenthetical ("Custom (Score: X)")
            // gets the same treatment now that the full Custom card below
            // restates that value itself. Contributing Risk's own
            // parenthetical ("Contributing Risk (Score: X)") gets the same
            // treatment now that the full Contributing Risk card below
            // restates that value itself (Phase 4d-v).
            var strippedText = (entry.display || '').replace(/\s*\([^)]*\)\s*$/, '');
            isEmptyValue = !strippedText;
            $value.text(isEmptyValue ? '—' : strippedText);
        } else {
            isEmptyValue = !entry.display;
            $value.text(isEmptyValue ? '—' : entry.display);
        }
        $value.toggleClass('is-empty', isEmptyValue);

        $item.append($label);

        // Subject ALSO has its own dedicated inline editor (the legacy
        // record header's hover-revealed pencil icon, view_top_table() +
        // risk.js's updateSubject()) -- this used to exclude it here to
        // avoid two competing affordances for one field, but a user with
        // modify_risks expects an edit control next to EVERY field in this
        // card, not just the ones that don't happen to also appear in the
        // header above. The backend already supports it: updateRisk()
        // (includes/api.php), the PATCH /api/v2/risks/{id} endpoint every
        // OTHER simple field here already saves through, treats `subject`
        // as an ordinary optional field. The save handler below refreshes
        // .overview-container too (see its own comment) so both editors
        // stay in sync regardless of which one saved.
        var profileInlineTypes = ctx.profile.inlineWidgetTypes || {};
        var isInlineEditableField = ctx.inlineEditable
            && (SIMPLE_INLINE_EDIT_WIDGET_TYPES[inlineWidgetType]
                || (Object.prototype.hasOwnProperty.call(profileInlineTypes, inlineWidgetType)
                    && profileInlineTypes[inlineWidgetType] === true));
        if (isInlineEditableField && typeof ctx.profile.inlineSaveUrl !== 'function') {
            // Fail closed -- see resolveViewProfile().
            warnOnce('missing-inline-save-url', 'the profile has no inlineSaveUrl; inline edit is disabled.');
            isInlineEditableField = false;
        }

        if (!isInlineEditableField) {
            $item.append($value);
        } else {
            $item.addClass('sr-qfield--inline-editable');

            var $editBtn = $('<button>', {
                type: 'button',
                'class': 'sr-row-action sr-qfield-edit-btn',
                title: _lang['Edit'],
                'aria-label': _lang['Edit']
            }).append($('<i>', { 'class': 'fa fa-edit', 'aria-hidden': 'true' }));

            var $staticRow = $('<div>').addClass('sr-qfield-value-row').append($value).append($editBtn);
            var $editRow = $('<div>').addClass('sr-qfield-value-row sr-qfield-edit d-none');
            $item.append($staticRow).append($editRow);

            // Built lazily, on first click -- not up front for every field on
            // every render -- so a read-mode page with a dozen inline-
            // editable fields never pays selectize's init cost for the ones
            // nobody edits this visit. A fresh control (and a fresh
            // selectize instance, for Owner/OwnersManager) is built on EVERY
            // click rather than reused across edit/cancel/edit cycles;
            // $editRow.empty() below discards the previous one via plain DOM
            // removal, which is a clean teardown for a bare selectize
            // instance with no external state to leak (unlike the full
            // form's GridStack-integrated fields, which persist for the
            // form's whole lifetime and so get a real destroy() path
            // instead).
            $editBtn.on('click', function (e) {
                e.preventDefault();

                var $control = window.RiskDetailsForm.buildStandaloneFieldControl(field, inlineWidgetType, formProfile);
                if (!$control) {
                    return;
                }
                $control.addClass('sr-qfield-edit-control');

                var $cancelBtn = $('<button>', {
                    type: 'button',
                    'class': 'sr-row-action cancel-edit-field',
                    title: _lang['Cancel'],
                    'aria-label': _lang['Cancel']
                }).append($('<i>', { 'class': 'fa fa-xmark', 'aria-hidden': 'true' }));
                var $saveBtn = $('<button>', {
                    type: 'button',
                    'class': 'sr-row-action save-edit-field',
                    title: _lang['Save'],
                    'aria-label': _lang['Save']
                }).append($('<i>', { 'class': 'fa fa-check', 'aria-hidden': 'true' }));

                // A real <form>, not a bare control -- a multi-value widget
                // (multiselect/selectize-tags/selectize-grouped) needs
                // serializeWithEmptyMultiValueMarkers() (risk-details-form.js)
                // to turn "the user cleared every selection" into an
                // explicit present-but-empty field the server's own PATCH
                // convention reads as "clear this", rather than an absent
                // one it reads as "leave alone" (a <select multiple> with
                // nothing checked submits no key at all otherwise) -- that
                // helper takes a real form element, the same shape the full
                // Edit Details form already feeds it. `submit` is caught and
                // routed to Save so Enter-to-submit inside the text widget
                // does the expected thing instead of reloading the page.
                var $form = $('<form>')
                    .addClass('sr-qfield-edit-form')
                    .append($control)
                    .append($('<div>').addClass('sr-inline-edit-actions').append($cancelBtn).append($saveBtn))
                    .on('submit', function (ev) {
                        ev.preventDefault();
                        $saveBtn.trigger('click');
                    });

                $editRow.empty().append($form).removeClass('d-none');
                $staticRow.addClass('d-none');

                // Activation (selectize/bootstrap-multiselect/HugeRTE) must
                // run AFTER $control is attached above -- see
                // buildStandaloneFieldControl()'s own docblock
                // (risk-details-form.js) for why a detached <select> leaves
                // selectize's visible wrapper silently missing (HugeRTE has
                // a different, milder version of the same live-DOM
                // requirement -- see activateStandaloneFieldControl()'s own
                // docblock). Tracked in ctx.openInlineEditInstances so a
                // richtext field's live editor gets destroyed before its
                // DOM node does, on whichever path closes this edit row
                // first (Cancel below, a successful Save, or an unrelated
                // renderCards() call wiping this whole container -- see
                // that function's own top).
                var activatedInstance = window.RiskDetailsForm.activateStandaloneFieldControl($control, field, inlineWidgetType, entry.raw, formProfile);
                ctx.openInlineEditInstances.push(activatedInstance);

                function closeEditRow() {
                    window.RiskDetailsForm.destroyStandaloneFieldControl(activatedInstance);
                    var at = ctx.openInlineEditInstances.indexOf(activatedInstance);
                    if (at !== -1) {
                        ctx.openInlineEditInstances.splice(at, 1);
                    }
                    $editRow.addClass('d-none').empty();
                    $staticRow.removeClass('d-none');
                }

                $cancelBtn.on('click', function (ev) {
                    ev.preventDefault();
                    closeEditRow();
                });

                $saveBtn.on('click', function (ev) {
                    ev.preventDefault();

                    $saveBtn.prop('disabled', true);
                    // A profile may build the body itself (the asset
                    // profile sends only the changed Asset Scoring
                    // objectives); null, or a hook that throws, gets the
                    // default. The risk profile has no such hook.
                    var body = null;
                    if (typeof ctx.profile.inlineSerialize === 'function') {
                        try {
                            body = ctx.profile.inlineSerialize($form[0], { field: field, widgetType: inlineWidgetType });
                        } catch (err) {
                            if (window.console) {
                                window.console.error('RiskDetailsView: inlineSerialize threw', err);
                            }
                            body = null;
                        }
                    }
                    if (typeof body !== 'string') {
                        body = window.RiskDetailsForm.serializeWithEmptyMultiValueMarkers($form[0]);
                    }
                    $.ajax({
                        type: 'PATCH',
                        url: BASE_URL + ctx.profile.inlineSaveUrl(riskId),
                        data: body,
                        cache: false
                    }).done(function (response) {
                        // The profile decides what a saved field refreshes
                        // (riskInlineSaved() for risk). A profile with no hook
                        // of its own re-runs this view's last init(), so the
                        // saved value is what the card shows next.
                        var saved = ctx.profile.onInlineSaved;
                        if (typeof saved === 'function') {
                            saved(field, response, { recordId: riskId, containerSelector: ctx.containerSelector });
                        } else if (ctx.initArgs) {
                            init.apply(null, ctx.initArgs);
                        } else {
                            // Nothing to re-fetch with (rendered via
                            // renderFromData() only): at least close the row
                            // rather than leave it open with Save disabled.
                            closeEditRow();
                        }
                    }).fail(function (xhr) {
                        // A profile may take a failure over (the asset
                        // record closes when the asset has gone); returning
                        // true skips the default toast. The risk profile has
                        // no such hook.
                        var failedHook = ctx.profile.onInlineSaveFailed;
                        if (typeof failedHook === 'function') {
                            var handled = false;
                            try {
                                handled = failedHook(field, xhr, { recordId: riskId, containerSelector: ctx.containerSelector }) === true;
                            } catch (err) {
                                if (window.console) {
                                    window.console.error('RiskDetailsView: onInlineSaveFailed threw', err);
                                }
                            }
                            if (handled) {
                                return;
                            }
                        }
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message, false);
                        } else {
                            showAlertFromMessage(_lang['RequestFailed'], false);
                        }
                        $saveBtn.prop('disabled', false);
                    });
                });
            });
        }

        // MitigationControls used to also build a read-only accordion+table
        // here (per-selected-control validation detail). That's now a
        // single shared TOP-LEVEL widget (RiskMitigationControls.renderWidget(),
        // js/simplerisk/pages/risk-mitigation-controls.js), mounted once by
        // risk-view-mitigation.js's coordinator in both read and edit mode --
        // not duplicated per-field here and in risk-details-form.js. This
        // field item is now just the plain label:value short-name summary,
        // same generic path every other field uses.

        // Same additive shape as MitigationControls just above: the plain
        // "CVSS (Base Score: 7.5)" text the generic path already rendered
        // stays as-is, the full card layout is appended below it. raw is a
        // string ('1'..'6', see SCORING_METHOD_VALUES.CVSS in
        // risk-details-form.js) -- '2' is CVSS.
        if (field.name === 'RiskScoringMethod' && entry.raw === '1') {
            var $classicView = buildClassicReadView(field, values);
            if ($classicView) {
                $item.append($classicView);
            }
        } else if (field.name === 'RiskScoringMethod' && entry.raw === '2') {
            var $cvssView = buildCvssReadView(field, values);
            if ($cvssView) {
                $item.append($cvssView);
            }
        } else if (field.name === 'RiskScoringMethod' && entry.raw === '3') {
            var $dreadView = buildDreadReadView(field, values);
            if ($dreadView) {
                $item.append($dreadView);
            }
        } else if (field.name === 'RiskScoringMethod' && entry.raw === '4') {
            var $owaspView = buildOwaspReadView(field, values);
            if ($owaspView) {
                $item.append($owaspView);
            }
        } else if (field.name === 'RiskScoringMethod' && entry.raw === '5') {
            var $customView = buildCustomReadView(field, values);
            if ($customView) {
                $item.append($customView);
            }
        } else if (field.name === 'RiskScoringMethod' && entry.raw === '6') {
            var $contributingRiskView = buildContributingRiskReadView(field, values);
            if ($contributingRiskView) {
                $item.append($contributingRiskView);
            }
        }

        return $item;
    }

    // ------------------------------------------------------------------
    // "Risk Scoring History" widget (Scoring card, Details tab). Replaces
    // the legacy page-level "Show Risk Score Over Time" expander
    // (management/partials/score-overtime.php, includes/display.php's
    // score_over_time()), retired by this same change -- a chart showing
    // the risk's own inherent/residual score over time belongs beside the
    // rest of its scoring detail, not as a separate page-level section.
    //
    // Shell: collapsed-by-default nested accordion, the same .sr-qaccordion/
    // .sr-qacc-head.accordion-button shell and <h6>-only header (no icon
    // tile) as risk-details-form.js's buildAdvanced() (CVSS's own "Advanced
    // Metrics" accordion) -- that pattern is for a section collapsing
    // *inside* an already-card-scoped area, exactly this widget's situation,
    // as opposed to the Audit Trail's top-level `.sr-audit-trail-toggle`
    // disclosure (design-system.md §6d), which is for a whole independent
    // page section. The chevron custom properties this reuses are supplied
    // by the `.sr-qcards-stack .sr-qaccordion .sr-qacc-head.accordion-
    // button` rule added alongside the collapsible-cards feature above (see
    // that rule's own comment in _questionnaire.scss).
    //
    // Chart interior: design-system.md's coverage-map marks chart interiors
    // PENDING (owned by a future `dataviz` skill that does not exist in
    // this repo yet), so this does not invent a new look where the system
    // already has one -- both the two-line chart shape (inherent/residual
    // over time, Y-axis-is-score/max-10) AND the stacked-fill risk-level
    // background bands come from the legacy implementation being retired
    // (score_over_time()/create_chartjs_line_code()/create_background_
    // dataset(), includes/display.php + includes/reporting.php).
    //
    // Two fixes on top of that legacy shape:
    // 1. Draw order -- the legacy PHP draws the bands as regular 'line'
    //    datasets with `fill: true`. That was tried here too (both dataset-
    //    array orderings), and Chart.js did not reliably draw the two score
    //    lines above the filled bands either way -- confirmed live, lines
    //    invisible under the bands regardless of array position.
    //    scoringHistoryBandsPlugin() replaces that with a `beforeDraw`
    //    canvas plugin instead: the whole plugin phase runs before any
    //    dataset is drawn, every time, so the bands are unambiguously the
    //    bottom-most layer and every real dataset (including the lines' own
    //    white halos, see haloedLineDatasets()) draws over them.
    // 2. Band labels moved out of the hover tooltip into a side legend --
    //    the legacy chart put every dataset's `label` (including each risk
    //    level's name) into one shared per-dataset tooltip, so hovering the
    //    chart mixed "Inherent Risk: 4.5" together with every risk-level
    //    band name at that same x position, reading as confusing/redundant.
    //    Bands are no longer datasets at all now (see fix 1), so there's
    //    nothing left to filter out of the tooltip on their account --
    //    `tooltipExempt` now only hides the lines' own halo copies (see
    //    haloedLineDatasets()). buildScoringHistoryLegend() renders each
    //    band's name+color beside the chart instead, as a real specified
    //    component (design-system.md §7 "severity -- solid", .sr-sev-pill)
    //    painted through the same window.CvssRiskLevelPill.paint() bridge
    //    cvssRiskLevelTile() above already uses (config-driven color via
    //    the style property, never interpolated into markup, luminance-
    //    picked .on-light text), each row centered on its own band's real
    //    pixel position (positionScoringHistoryLegend()).
    //
    // Also corrected to Chart.js 4's options.plugins.legend shape (the
    // legacy PHP emits a stray top-level options.legend, a harmless no-op
    // left over from an older Chart.js major version) and fetches
    // risk_levels from the live /risk_levels endpoint via window.
    // CvssRiskLevelPill.levels() (the same cached bridge every other
    // scoring holder on this page already uses) instead of a server-
    // rendered inline <script>.
    //
    // Lazy: the two /reports/risk/average calls + risk_levels only fire on
    // the widget's first expand (Bootstrap's 'show.bs.collapse'), matching
    // the Audit Trail pattern's own "fetch lazily" rule -- a risk that's
    // never expanded costs nothing beyond the (already-cached) template
    // rendering.
    // ------------------------------------------------------------------

    // Shared by scoringHistoryBandsPlugin() (the fill geometry) and
    // positionScoringHistoryLegend() (the legend row alignment) -- both
    // need the SAME [top, bottom] value range per band, highest level
    // first, ending with a synthetic "Insignificant" band filling the
    // remainder down to 0 (create_background_dataset()'s own convention,
    // includes/reporting.php -- risk_levels has no row for it, since it's
    // not a real configured level, just "below the lowest one"). Omitted
    // entirely when the lowest configured level's own threshold is already
    // 0 -- a zero-height band has no stripe to show in the chart and no
    // meaningful position for a legend pill (confirmed live: an instance
    // with no headroom below its lowest level rendered an unstyled,
    // invisible-on-white "Insignificant" row collapsed to the axis floor).
    //
    // The symmetric case on the OTHER end: the highest configured level
    // (e.g. "Very High") has no ceiling of its own in risk_levels -- it's
    // "this value and above" -- so its band's `top` is `axisMax`, not a
    // hardcoded 10. `axisMax` (computed by the caller from the system's
    // actual scoring configuration, not from the data -- see
    // loadScoringHistoryChart()'s own comment) is the real theoretical
    // ceiling a score can reach.
    //
    // A level whose OWN configured threshold sits AT or ABOVE axisMax is
    // reported live as a real case (a "Very High" floor of 10.1 with
    // normalization on, so nothing can ever reach it, let alone anything
    // above it) -- clamping its `top` to axisMax alone isn't enough, since
    // that produces a band whose `bottom` (10.1) is past its own clamped
    // `top` (10): an inverted, negative-height band that still rendered a
    // phantom legend pill collapsed onto the next band's. Each band's `top`
    // is clamped to `axisMax` as the loop runs, and a band is only kept
    // when that clamped top is still above its own bottom -- an unreachable
    // level is dropped entirely, and the next (reachable) level's band
    // naturally absorbs the space up to axisMax instead.
    function computeLevelBands(levels, axisMax) {
        var sorted = levels.slice().sort(function (a, b) { return Number(b.value) - Number(a.value); });
        var bands = [];
        var top = axisMax;
        sorted.forEach(function (level) {
            var bottom = Number(level.value);
            var clampedTop = Math.min(top, axisMax);
            if (clampedTop > bottom) {
                bands.push({ name: level.name, color: level.color, top: clampedTop, bottom: bottom });
            }
            top = bottom;
        });
        var insignificantTop = Math.min(top, axisMax);
        if (insignificantTop > 0) {
            bands.push({ name: _lang.Insignificant, color: '#FFFFFF', top: insignificantTop, bottom: 0 });
        }
        return bands;
    }

    // Paints the risk-level bands as plain filled rectangles directly on
    // the canvas in `beforeDraw`, rather than as extra 'line' datasets with
    // `fill: true` the way the legacy create_background_dataset() (includes/
    // reporting.php) does it. That dataset-based technique was tried first
    // here and DIDN'T hold up: Chart.js's line/area rendering order for
    // multiple filled datasets did not reliably put later array entries on
    // top of earlier ones the way plain element z-order does -- the two
    // score lines stayed hidden under the bands regardless of which end of
    // the datasets array they were listed at (confirmed live both ways). A
    // `beforeDraw` plugin hook is unambiguous: the whole plugin phase runs
    // BEFORE any dataset (line or otherwise) is drawn, every time, so the
    // bands are always the bottom-most thing on the canvas and every real
    // dataset -- including the lines' own white halos -- draws over them
    // by construction, not by hoping the array order wins a tiebreak.
    function scoringHistoryBandsPlugin(bands) {
        return {
            id: 'scoringHistoryBands',
            beforeDraw: function (chart) {
                var yScale = chart.scales.y;
                var xScale = chart.scales.x;
                if (!yScale || !xScale) {
                    return;
                }
                var ctx = chart.ctx;
                ctx.save();
                bands.forEach(function (band) {
                    var topPx = yScale.getPixelForValue(band.top);
                    var bottomPx = yScale.getPixelForValue(band.bottom);
                    ctx.fillStyle = band.color;
                    ctx.fillRect(xScale.left, topPx, xScale.width, bottomPx - topPx);
                });
                ctx.restore();
            }
        };
    }

    // A plain evenly-spaced legend list doesn't say which color goes with
    // which part of the chart when the bands themselves are uneven heights
    // (a wide "Low" band, a narrow "Very High" one). Reading each band's
    // real screen position off the live chart's own y-scale
    // (getPixelForValue(), the same coordinate space Chart.js just drew the
    // fill in) and centering each pill on its band's vertical midpoint is
    // what actually ties a legend entry to "this stripe of color", rather
    // than just listing the levels in order. Chart.js re-lays-out the
    // y-scale on resize, so this is called again from options.onResize --
    // which also fires once SYNCHRONOUSLY from inside the `new Chart(...)`
    // constructor below, before `chart.scales.y` is built yet (confirmed
    // live: a TypeError from that first call otherwise). No-op until the
    // scale exists; the explicit call right after construction (and every
    // later real resize) runs once it does.
    function positionScoringHistoryLegend($legend, chart, bands) {
        var yScale = chart.scales && chart.scales.y;
        if (!yScale) {
            return;
        }
        var containerHeight = $legend.height();
        $legend.find('.sr-scoring-history-legend-row').each(function (i) {
            var band = bands[i];
            var centerPx = (yScale.getPixelForValue(band.top) + yScale.getPixelForValue(band.bottom)) / 2;
            // A narrow band (e.g. a "Very High" that's only 1-2 score
            // points wide) centers its pill close enough to the top or
            // bottom edge that the pill's own rendered height -- taller
            // than the band's few pixels -- would otherwise poke out past
            // the legend column entirely (confirmed live). Clamp the center
            // so the pill's own box always stays inside [0, containerHeight]
            // rather than assuming every band has room for it.
            var halfHeight = $(this).outerHeight() / 2;
            centerPx = Math.max(halfHeight, Math.min(containerHeight - halfHeight, centerPx));
            $(this).css('top', centerPx + 'px');
        });
    }

    function buildScoringHistoryLegend(bands) {
        var $legend = $('<div>').addClass('sr-scoring-history-legend');
        bands.forEach(function (band) {
            var $pill = $('<span>').addClass('sr-sev-pill sr-sev-pill-sm').text(band.name);
            if (window.CvssRiskLevelPill) {
                window.CvssRiskLevelPill.paint($pill, band.color);
            }
            $legend.append($('<div>').addClass('sr-scoring-history-legend-row').append($pill));
        });
        return $legend;
    }

    // A solid-fill band can be any of red/orange/yellow/green, so no single
    // line color reads clearly against all of them -- a 2px charcoal line
    // sitting at the very top of a red band (this risk pinned at the
    // maximum score for most of its history, say) was reported as
    // essentially invisible. The fix is the standard halo technique: a
    // slightly wider black "outline" copy of the line drawn immediately
    // before the real colored line (same points, no fill, thicker, no
    // dash), so the real line always sits on a dark contrast edge
    // regardless of what background color is behind it -- a thin 1px
    // margin on each side, not a heavy outline. `tooltipExempt` keeps the
    // halo out of the hover (it would otherwise duplicate the real line's
    // own row).
    function haloedLineDatasets(label, data, color, styleExtras) {
        return [
            {
                data: data,
                fill: false,
                borderColor: '#000000',
                borderWidth: (styleExtras.borderWidth || 2) + 2,
                tension: styleExtras.tension,
                pointRadius: 0,
                tooltipExempt: true
            },
            $.extend({ label: label, data: data, fill: false, borderColor: color, pointBackgroundColor: color }, styleExtras)
        ];
    }

    // Legacy create_chartjs_line_code() (includes/reporting.php) put a bare
    // save-as-PNG icon under every chart it rendered, this one included. A
    // dropdown was tried first here (structured for a future second export
    // type), but with only the one action it's just an extra click for no
    // reason -- a single icon button that acts immediately, same as the
    // legacy one did. `.sr-scoring-history-download` (_questionnaire.scss)
    // is a plain ghost icon button in the same spirit as the app shell's
    // own header icon buttons (design-system.md §12: "uniform size, rounded
    // hover") -- muted by default, no border, a soft hover fill -- rather
    // than a bordered `.btn`, since this is a quiet secondary affordance on
    // a card, not a primary action. Floats over the WIDGET's own
    // bottom-right corner (position set on .sr-scoring-history-widget
    // itself, not just the chart), so it reads as belonging to the whole
    // card rather than just the canvas.
    function buildScoringHistoryDownloadButton(chart, riskId) {
        var $button = $('<button>', { type: 'button', title: _lang.DownloadChartAsImage })
            .addClass('sr-scoring-history-download')
            .append($('<i>').addClass('fa fa-download').attr('aria-hidden', 'true'))
            .append($('<span>').addClass('visually-hidden').text(_lang.DownloadChartAsImage));
        $button.on('click', function () {
            var link = document.createElement('a');
            link.href = chart.toBase64Image();
            link.download = 'risk-scoring-history-' + riskId + '.png';
            link.click();
        });
        return $button;
    }

    function loadScoringHistoryChart(riskId, $canvas, $status, $row, $legendSlot) {
        $status.text(_lang.Loading);

        $.when(
            fetchJSON('/reports/risk/average', { risk_id: riskId, type: 'inherent', timeframe: 'day' }),
            fetchJSON('/reports/risk/average', { risk_id: riskId, type: 'residual', timeframe: 'day' }),
            (window.CvssRiskLevelPill ? window.CvssRiskLevelPill.levels() : $.Deferred().resolve([]).promise()),
            (window.CvssRiskLevelPill ? window.CvssRiskLevelPill.formulaConfig() : $.Deferred().resolve({}).promise())
        ).done(function (inherentResult, residualResult, levels, formulaConfig) {
            var labels = (inherentResult && inherentResult.dates) || [];
            var inherentAverages = (inherentResult && inherentResult.averages) || [];
            var residualAverages = (residualResult && residualResult.averages) || [];

            if (!labels.length) {
                $status.text(_lang.NoDataAvailable);
                return;
            }

            // The theoretical ceiling a score can ever reach -- NOT "10, or
            // higher if the data happens to exceed it". calculate_risk()
            // (includes/functions.php) normalizes every Classic score onto
            // a 0-10 scale whenever `need_risk_score_normalization` is on
            // (the system default), in which case 10 genuinely is the
            // ceiling for every scoring method, CVSS/DREAD/OWASP/
            // Contributing Risk included -- they're all naturally bounded
            // near 10 already. With normalization OFF, Classic's own raw
            // likelihood x impact formula (classicCalculateMaxRawScore(),
            // mirroring calculate_maximum_risk_score()) can genuinely
            // exceed 10, and THAT'S the real ceiling. A configured risk
            // level threshold sitting above whichever of these applies
            // (e.g. "Very High" at 10.1 under normalization) is simply
            // unreachable and has no business stretching the chart --
            // reported live as exactly this case. `formulaConfig` is the
            // same cached /riskformula/config window.CvssRiskLevelPill
            // bridge buildClassicReadView() already uses; classicCalculate
            // MaxRawScore() already defaults to 10 for model 6 (no case in
            // its switch), matching calculate_maximum_risk_score()'s own
            // fallback.
            var axisMax = formulaConfig.need_risk_score_normalization
                ? 10
                : classicCalculateMaxRawScore(formulaConfig.likelihood_count, formulaConfig.impact_count, formulaConfig.risk_model);
            var bands = computeLevelBands(levels || [], axisMax);

            // Each line's white halo goes right before the real line, so
            // the line draws on top of its own halo; the bands themselves
            // are no longer datasets at all (see scoringHistoryBandsPlugin()'s
            // own comment) so there's nothing else to order against here.
            // Inherent vs Residual are differentiated by line style + point
            // shape (not just color) so they stay distinguishable even when
            // the two scores are identical and the lines fully overlap --
            // the common case for a risk with no mitigation yet.
            var datasets = haloedLineDatasets(_lang.InherentRisk, inherentAverages, '#3A3A3A' /* $sr-primary */, {
                borderWidth: 2,
                tension: 0.1,
                pointStyle: 'circle',
                pointRadius: 3
            }).concat(haloedLineDatasets(_lang.ResidualRisk, residualAverages, '#27a9e3' /* $info / $cyan */, {
                borderDash: [6, 4],
                borderWidth: 2,
                tension: 0.1,
                pointStyle: 'rectRot',
                pointRadius: 4
            }));

            $legendSlot.empty().append(buildScoringHistoryLegend(bands));

            // Reveal the row (canvas + legend) BEFORE constructing the
            // chart -- Chart.js measures its container's real layout box at
            // construction time, and a `display: none` ancestor (the row
            // starts hidden behind the loading status) lays out as 0x0,
            // which would make every getPixelForValue() call below garbage
            // for the legend's initial positioning.
            $status.hide();
            $row.show();

            var chart = new Chart($canvas[0].getContext('2d'), {
                type: 'line',
                data: { labels: labels, datasets: datasets },
                // Per-chart plugin (not registered globally), so it only
                // ever paints this one chart's own bands.
                plugins: [scoringHistoryBandsPlugin(bands)],
                options: {
                    responsive: true,
                    // The wrapper div gives Chart.js a fixed CSS height
                    // (.sr-scoring-history-chart) precisely so it can fill
                    // that box exactly -- the default aspect-ratio-based
                    // sizing (maintainAspectRatio: true) computes its own
                    // height from width instead, leaving dead space on a
                    // wide card.
                    maintainAspectRatio: false,
                    // Chart.js re-lays-out the y-scale (new pixel-per-unit)
                    // on every resize -- without this the legend rows stay
                    // pinned to their FIRST-render positions and drift out
                    // of alignment with their bands as the card is resized.
                    // Takes the chart instance from Chart.js's own first
                    // callback argument, not the outer `chart` var -- this
                    // can fire synchronously during the `new Chart(...)`
                    // constructor call below, before that assignment has
                    // completed.
                    onResize: function (chartInstance) {
                        positionScoringHistoryLegend($legendSlot, chartInstance, bands);
                    },
                    plugins: {
                        title: { display: false },
                        // The two lines' own names are legible from the
                        // legend column beside the chart when it's the
                        // risk-level key; Chart.js's built-in legend would
                        // duplicate that role for just 2 items, so it stays
                        // off and the tooltip carries the per-line label.
                        legend: { display: false },
                        tooltip: {
                            // Only the two score lines get a hover row -- the
                            // background bands' own labels (risk-level names)
                            // live in the side legend instead, not stacked
                            // into the same hover as the scores. `filter`
                            // (not just a blank `label`) is what actually
                            // removes the row -- a label callback alone still
                            // renders an (empty) row per matched dataset.
                            filter: function (tooltipItem) {
                                return !tooltipItem.dataset.tooltipExempt;
                            },
                            callbacks: {
                                label: function (tooltipItem) {
                                    return tooltipItem.dataset.label + ': ' + tooltipItem.formattedValue;
                                }
                            }
                        }
                    },
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { display: true, title: { display: true, text: _lang.Date } },
                        y: { display: true, title: { display: true, text: _lang.RiskScore }, beginAtZero: true, max: axisMax }
                    }
                }
            });

            // Chart.js's `onResize` above is not guaranteed to fire on the
            // very first layout in every version -- position once
            // explicitly right after construction as well.
            positionScoringHistoryLegend($legendSlot, chart, bands);

            // Appended into the collapsible BODY (not the outer widget) so
            // it hides/shows with Bootstrap's own collapse mechanism --
            // it has no reason to be visible while the chart underneath it
            // is hidden. Still positions against the widget's own corner
            // (position: absolute resolves to the nearest POSITIONED
            // ancestor regardless of which DOM level it's appended at --
            // .sr-scoring-history-widget is that ancestor either way, see
            // its own CSS).
            $row.closest('.sr-scoring-history-body').append(buildScoringHistoryDownloadButton(chart, riskId));
        }).fail(function () {
            $status.text(_lang.RequestFailed);
        });
    }

    function buildScoringHistoryWidget(riskId) {
        var bodyId = 'sr-scoring-history-' + riskId + '-body';
        var $canvas = $('<canvas>');
        var $status = $('<div>').addClass('sr-scoring-history-status');
        var $legendSlot = $('<div>').addClass('sr-scoring-history-legend-slot');
        var $chartWrap = $('<div>').addClass('sr-scoring-history-chart').append($canvas);
        var $row = $('<div>').addClass('sr-scoring-history-row').append($chartWrap).append($legendSlot).hide();
        var $body = $('<div>', { id: bodyId }).addClass('accordion-collapse collapse sr-scoring-history-body')
            .append($status)
            .append($row);

        var $head = $('<button>').attr('type', 'button').addClass('sr-qcard-head sr-qacc-head accordion-button collapsed')
            .attr({ 'data-bs-toggle': 'collapse', 'data-bs-target': '#' + bodyId, 'aria-expanded': 'false', 'aria-controls': bodyId })
            .append($('<span>').addClass('sr-qcard-htext').append($('<h6>').text(_lang.RiskScoringHistory)));

        var loaded = false;
        $body.on('show.bs.collapse', function () {
            if (loaded) {
                return;
            }
            loaded = true;
            loadScoringHistoryChart(riskId, $canvas, $status, $row, $legendSlot);
        });

        return $('<div>').addClass('sr-qaccordion sr-scoring-history-widget').append($head).append($body);
    }

    // Accept/Reject Mitigation -- a top-level action on the Mitigation tab
    // (risk-view-mitigation.js, next to Edit Mitigation), not a Cards field:
    // it fires its own AJAX call the instant a button is clicked,
    // independent of the surrounding tab's read/edit toggle or Save button,
    // and it reads as the mitigation's APPROVAL STATUS rather than one more
    // planning field -- which is also why it is no longer positionable in
    // the Cards field roster at all (risk_mitigation_field_is_positionable(),
    // includes/functions.php). The legacy display_accept_mitigation_view()
    // (includes/displayrisks.php) is the only rendering this ever had --
    // there is no edit-mode counterpart (risk_mitigation_field_renders_
    // no_control() confirms it) -- so risk-details-form.js's edit mode
    // calls this SAME function too, via window.RiskDetailsView, rather than
    // building a second copy.
    //
    // Returns { $text, $actions } rather than one combined element: the
    // caller places them separately -- $actions (the button(s), or nothing
    // for a no-permission viewer) sits next to Edit Mitigation/Cancel in the
    // tab's own action row, while $text (the potentially long "accepted by
    // X on Y" sentence, or nothing if no one has accepted) reads as its own
    // status line rather than being crammed into that same corner.
    //
    // `data` is api_get_ui_risk_mitigation_values()'s own 'accept_mitigation'
    // key: { can_accept, current_user_accepted, html }. `can_accept` mirrors
    // the legacy view function's `!empty($_SESSION['accept_mitigation'])`
    // gate -- a no-permission viewer sees only the accepted-by sentence
    // (or nothing, if empty), matching that function's own `hide`-class row.
    //
    // No confirmation dialog on click, matching the legacy inline handler
    // (includes/displayrisks.php) exactly -- accepting/rejecting is a quick,
    // reversible toggle, not a destructive action.
    function buildAcceptMitigationWidget(riskId, data) {
        data = data || {};
        var $text = $('<div>').addClass('accept-mitigation-text sr-qhint')
            .html(data.html || '')
            .toggleClass('hide', !data.html);
        var $actions = $('<span>').addClass('accept-mitigation-actions');

        if (!data.can_accept) {
            return { $text: $text, $actions: $actions };
        }

        var accepted = !!data.current_user_accepted;
        var $acceptBtn = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-submit accept-mitigation-btn' })
            .text(_lang['AcceptMitigation'])
            .toggle(!accepted);
        var $rejectBtn = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-primary reject-mitigation-btn' })
            .text(_lang['RejectMitigation'])
            .toggle(accepted);

        function post(accept) {
            $acceptBtn.prop('disabled', true);
            $rejectBtn.prop('disabled', true);
            (accept ? $acceptBtn : $rejectBtn).text(accept ? _lang['Accepting'] : _lang['Rejecting']);

            $.ajax({
                type: 'POST',
                // id is a QUERY param, not a POST field -- acceptMitigationForm()
                // (includes/api.php) reads it off $_GET['id'] specifically
                // (there is no {id} route segment), matching the legacy
                // inline handler's own call shape exactly.
                url: BASE_URL + '/api/v2/management/risk/accept_mitigation?id=' + encodeURIComponent(riskId),
                data: { accept: accept ? 1 : 0 },
                dataType: 'json'
            }).done(function (response) {
                var html = response.data && response.data.accept_mitigation_text;
                $text.html(html || '').toggleClass('hide', !html);
                $acceptBtn.toggle(!accept);
                $rejectBtn.toggle(accept);
            }).fail(function (xhr) {
                showAlertsFromArray(xhr.responseJSON && xhr.responseJSON.status_message);
            }).always(function () {
                $acceptBtn.prop('disabled', false).text(_lang['AcceptMitigation']);
                $rejectBtn.prop('disabled', false).text(_lang['RejectMitigation']);
            });
        }

        $acceptBtn.on('click', function () { post(true); });
        $rejectBtn.on('click', function () { post(false); });

        $actions.append($acceptBtn, $rejectBtn);
        return { $text: $text, $actions: $actions };
    }

    // Sorts one card's worth of fields by (pos_y, pos_x) -- `fields` arrives
    // in the API's OWN array order (get_active_fields()'s ORDER BY,
    // extras/customization/index.php, sorts by the legacy panel_name/
    // ordering columns, not by the Cards layout's pos_y/pos_x) -- then
    // renders each via renderFieldItem() into a .sr-qgrid. This is the SAME
    // unit renderCards() below builds per card; factored out and exposed
    // (RiskDetailsView.renderFieldsGrid) so an external caller with its own
    // one-off value set -- risk-view-review.js's "View All Reviews" history
    // modal, one .sr-qgrid per historical review, same 'review' card's
    // field roster but a DIFFERENT review's resolved values each time --
    // renders genuinely the same field list/order/widgets as the live
    // Review card above it, not a hand-rolled lookalike that silently
    // drifts the next time a Review-tab field or its resolver changes.
    // Two independently-flowing flex columns (.sr-qgrid--columns) instead of
    // CSS Grid's shared row tracks -- see that class's own docblock
    // (_questionnaire.scss) for the root cause this fixes. Deliberately NOT
    // the default for every card: plain shared-row CSS Grid is what keeps
    // an intentional pairing (e.g. Mitigation's PlanningStrategy sitting
    // beside Planned Mitigation Date on the SAME visual row) aligned when
    // every paired field is a similar, fixed height -- switching that card
    // to independent columns too was tried and reverted, because it let
    // the two columns drift out of alignment (confirmed live: Mitigation
    // Effort and Mitigation Cost, previously same row, landed on visibly
    // different rows once Planning Strategy's own empty value made the
    // right column shorter than the left one at that point). Also unsafe
    // for a card with a full-width field: it can't sit inside either
    // column div while still spanning the row, since renderFieldItem()'s
    // sr-qfield--full branch has no meaning once the parent stops being
    // display:grid. So this path is scoped narrowly, to the one shape that
    // actually breaks under shared rows: a HALF-WIDTH field whose resolved
    // value is richtext (display_html) sharing a row with anything else --
    // richtext content wraps to a variable number of lines, so it is the
    // only kind of field whose height can differ enough from a fixed
    // dropdown/date/text row-mate to matter. Today that is Comment on the
    // Review card (the one half-width richtext field on this whole canvas
    // -- every other richtext field is forced full width); a future field
    // sharing that shape gets the fix automatically, everything else keeps
    // its row alignment exactly as before.
    function renderFieldsAsIndependentColumns(sorted, values, riskId, ctx) {
        var $qgrid = $('<div>').addClass('sr-qgrid sr-qgrid--columns');
        var $left = $('<div>').addClass('sr-qgrid-col');
        var $right = $('<div>').addClass('sr-qgrid-col');
        sorted.forEach(function (field) {
            var $rendered = renderFieldItem(field, values, riskId, ctx);
            if (!$rendered) {
                return;
            }
            var $column = (Number(field.pos_x) || 0) === 0 ? $left : $right;
            $column.append($rendered);
        });
        $qgrid.append($left, $right);
        return $qgrid;
    }

    // `ctx` (optional): the render context. A public caller may pass nothing
    // (risk profile, not inline-editable) or {profile}.
    function renderFieldsGrid(fields, values, riskId, ctx) {
        ctx = toRenderContext(ctx);
        var sorted = fields.slice().sort(function (a, b) {
            var ay = Number(a.pos_y) || 0;
            var by = Number(b.pos_y) || 0;
            if (ay !== by) {
                return ay - by;
            }
            return (Number(a.pos_x) || 0) - (Number(b.pos_x) || 0);
        });

        var hasFullWidthField = sorted.some(function (field) {
            return field.name === 'RiskScoringMethod' || Number(field.pos_w) >= 6;
        });
        var hasHalfWidthRichtextField = sorted.some(function (field) {
            if (field.name === 'RiskScoringMethod' || Number(field.pos_w) >= 6) {
                return false;
            }
            var entry = fieldValueEntry(values, field, ctx.profile);
            return !!entry && typeof entry.display_html === 'string';
        });
        if (hasHalfWidthRichtextField && !hasFullWidthField) {
            return renderFieldsAsIndependentColumns(sorted, values, riskId, ctx);
        }

        var $qgrid = $('<div>').addClass('sr-qgrid');
        sorted.forEach(function (field) {
            var $rendered = renderFieldItem(field, values, riskId, ctx);
            if ($rendered) {
                $qgrid.append($rendered);
            }
        });
        return $qgrid;
    }

    function renderCards(container, fields, cards, values, supportingDocumentationHtml, riskId, inlineEditable, ctx) {
        // Before this container's old content is discarded below -- any
        // still-open inline-edit control (most likely a richtext one; see
        // newRenderContext()'s openInlineEditInstances) needs its live editor
        // instance destroyed first, not just its DOM node dropped.
        destroyOpenInlineEditInstances(ctx);

        ctx.supportingDocumentationHtml = supportingDocumentationHtml;
        ctx.inlineEditable = !!inlineEditable;
        var cardIcons = ctx.profile.maps.cardIcons || {};
        var cardLabels = ctx.profile.maps.cardLabels || {};

        var fieldsByCard = {};
        fields.forEach(function (field) {
            var key = field.card_key || 'general';
            fieldsByCard[key] = fieldsByCard[key] || [];
            fieldsByCard[key].push(field);
        });

        // .sr-qgrid is reserved for the fields grid INSIDE a card (see
        // _sr-modal.scss), not a cards-list container, so this outer wrapper
        // gets its own feature-scoped class instead: `.sr-qcards-stack` is the
        // grid that puts a 16px gap between the cards. That gap deliberately
        // does NOT live on the shared bare `.sr-qcard` rule -- that rule is
        // used by ~10 other files whose cards are already direct children of
        // a `gap: 13px` grid, and a margin there double-spaces them.
        // Prefix for each card's collapse-target id, so Details/Mitigation/
        // Review -- which all route through this one shared function -- never
        // collide even though the underlying `cards` rosters can repeat the
        // same card_key (e.g. a future 'assignment' card on more than one
        // tab). `container`'s id is unique per tab (see the VIEW_CONTAINER_
        // SELECTOR constants in risk-view-details.js/mitigation.js/review.js),
        // so it's a safe, already-unique prefix -- no idPrefix option needed.
        var idPrefix = container.attr('id') || 'sr-qcards';

        var $cardsList = $('<div>').addClass('sr-qcards-stack');

        // honorCardWidths (default off = the single column every risk tab
        // has always rendered): lay the cards on a 12-column CSS grid at
        // their saved pos_x/pos_w, in saved reading order (pos_y, then
        // pos_x), so two half-width cards sit side by side. CSS grid's own
        // auto-placement stacks a card whose column is already taken on the
        // next row. The column span goes through a custom property so the
        // host's container query (_sr-modal.scss) can collapse everything to
        // one column at <=700px without fighting an inline style.
        var orderedCards = cards;
        if (ctx.honorCardWidths) {
            $cardsList.addClass('sr-qcards-stack--grid');
            orderedCards = cards.slice().sort(function (a, b) {
                return ((Number(a.pos_y) || 0) - (Number(b.pos_y) || 0)) || ((Number(a.pos_x) || 0) - (Number(b.pos_x) || 0));
            });
        }
        // responsive (default off): the nested two-column field grids become
        // one column at <=700px of container width (same container query).
        if (ctx.responsive) {
            $cardsList.addClass('sr-qcards-stack--responsive');
        }

        orderedCards.forEach(function (card) {
            var cardFields = fieldsByCard[card.card_key] || [];
            // A card with no fields currently assigned to it renders nothing
            // -- the SAME "computed, not stored" empty-card visibility rule
            // risk-details-form.js's edit-mode buildCanvas() and the admin
            // Cards layout editor already both apply (this read-only
            // renderer was the one place in the family that didn't yet).
            // 'custom_fields' is the card this actually matters for in
            // practice (its custom_template_card row is seeded for every
            // template group up front, per customization_cards_layout_
            // card_keys(), whether or not an admin has ever put a field in
            // it), but the check is generic, not custom_fields-specific --
            // any curated card an admin has emptied out reads the same way:
            // gone, not an empty shell (confirmed by the user: Mitigation
            // and Review both showed a bare "Custom Fields" card with
            // nothing in it when no custom field existed yet).
            if (!cardFields.length) {
                return;
            }
            // `sr-qaccordion` is required here, not decorative: the chevron
            // custom properties (--bs-accordion-btn-icon and friends) are
            // only defined by the `.sr-qaccordion .sr-qacc-head.accordion-
            // button` rule (modules/_questionnaire.scss) -- a descendant
            // selector, so SOME ancestor must carry this class or the
            // toggle renders with no arrow at all (see that rule's own
            // comment for the full history). $card is already the button's
            // parent, so adding the class here (rather than a new wrapper
            // div, as the CVSS Advanced Metrics precedent uses) satisfies it
            // for free.
            var $card = $('<div>').addClass('sr-qcard sr-qaccordion');
            if (ctx.honorCardWidths) {
                var cardX = Math.min(Math.max(Number(card.pos_x) || 0, 0), 11);
                var cardW = Math.min(Math.max(Number(card.pos_w) || 12, 1), 12 - cardX);
                $card[0].style.setProperty('--sr-qcard-col', (cardX + 1) + ' / span ' + cardW);
            }
            var bodyId = idPrefix + '-' + card.card_key + '-collapse';

            // Same .sr-qcard-ico/.sr-qcard-htext>h2 markup shape as
            // risk-details-form.js's edit-mode buildCanvas() -- see
            // sr-qcard-head-styled (scss/mixins/qcard-head.scss) for why this
            // read-mode card stack now shares that rule instead of the
            // older, unrelated bare .sr-qcard-head/.sr-qcard-title rule
            // (_sr-modal.scss) ~10 other pages still use.
            //
            // The head is now a real <button> instead of a plain <div> so
            // each card can collapse/expand -- same `.sr-qacc-head.accordion-
            // button` pattern the CVSS "Advanced Metrics" accordion already
            // ships in risk-details-form.js's buildAdvanced() (design-system.
            // md §5's "Optional / advanced accordion"). That precedent's
            // GridStack height-sync concern (shown.bs.collapse/hidden.bs.
            // collapse listeners re-running the auto-fit sweep) doesn't apply
            // here -- this read-mode stack is plain CSS grid, not GridStack,
            // so a bare Bootstrap collapse needs no extra wiring. Expanded by
            // default (aria-expanded=true, no `collapsed` class, body starts
            // with `show`): the ask was the *ability* to collapse, not a
            // collapsed-by-default state.
            var $head = $('<button>').attr({
                type: 'button',
                'data-bs-toggle': 'collapse',
                'data-bs-target': '#' + bodyId,
                'aria-expanded': 'true',
                'aria-controls': bodyId
            }).addClass('sr-qcard-head sr-qacc-head accordion-button');
            $('<span>').addClass('sr-qcard-ico')
                .append($('<i>').addClass('fa ' + (cardIcons[card.card_key] || 'fa-square')))
                .appendTo($head);
            $('<span>').addClass('sr-qcard-htext')
                .append($('<h2>').text(cardLabels[card.card_key] || card.card_key))
                .appendTo($head);
            $card.append($head);

            var $qgrid = renderFieldsGrid(cardFields, values, riskId, ctx);
            var $body = $('<div>', { id: bodyId }).addClass('sr-qcard-body accordion-collapse collapse show').append($qgrid);

            $card.append($body);

            $cardsList.append($card);
        });

        // Either opt-in mode needs a size container for its container
        // query; without them the DOM is exactly what it has always been.
        if (ctx.honorCardWidths || ctx.responsive) {
            container.empty().append($('<div>').addClass('sr-qcards-host').append($cardsList));
        } else {
            container.empty().append($cardsList);
        }
    }

    // `options.tabIndex` (1 = Details, the default every pre-4b-iii caller
    // relies on; 2 = Mitigation, Phase 4b-iii; 3 = Review, Phase 4c-ii),
    // `options.valuesPath` (defaults to the Details tab's own
    // '/ui/risk/{id}/values' -- Mitigation's coordinator passes
    // '/ui/risk/{id}/mitigation-values', Review's '/ui/risk/{id}/review-values'
    // instead) and `options.supportingDocumentationHtmlKey` (defaults to
    // 'supporting_documentation_html'; Mitigation's response carries its
    // single such field under 'mitigation_supporting_documentation_html'
    // instead) let this ONE engine serve all three tabs without a second
    // copy of this module. Review's full history list is a separate,
    // on-demand "View All Reviews" modal (risk-view-review.js) rather than
    // anything this card renderer threads through -- see that module's own
    // comment for why (the previous always-inline history dump this
    // replaced).
    //
    // `options.inlineEditable` (default false) gates the per-field inline
    // edit affordance (a hover-revealed pencil icon that swaps ONE field's
    // value for a control + Cancel/Save icons, PATCHing just that field back
    // -- see renderFieldItem()'s own inline-edit block below). Only
    // risk-view-details.js's coordinator passes it true (computed from its
    // mount's own `can-edit` data attribute), so Mitigation/Review renders of
    // this same shared engine never show it -- this is Details-tab-only for
    // now, matching the user-approved rollout ("start with the simple single
    // value fields" on the Details tab).
    //
    // New, all optional (defaults keep every risk tab unchanged):
    //   profile          record-type profile (see RISK_PROFILE above); its
    //                    endpoints.values supplies the default valuesPath
    //   honorCardWidths  lay cards out at their saved pos_x/pos_w on a
    //                    12-column grid instead of one column
    //   responsive       nested field grids go one column at <=700px of
    //                    container width
    function init(containerSelector, riskId, onLoaded, options) {
        options = options || {};
        var profile = resolveViewProfile(options.profile);
        var tabIndex = options.tabIndex || 1;
        var valuesPath = options.valuesPath || endpointPath(profile.endpoints.values, riskId);
        var supportingDocumentationHtmlKey = options.supportingDocumentationHtmlKey || 'supporting_documentation_html';

        var container = $(containerSelector);
        if (container.length === 0) {
            return;
        }

        destroy(containerSelector);

        var instance = getOrCreateInstance(containerSelector);
        applyRenderOptions(instance, options);
        instance.initArgs = [containerSelector, riskId, onLoaded, options];
        instance.generation += 1;
        var generation = instance.generation;

        function failed() {
            if (instance.generation !== generation) {
                return;
            }
            showAlertFromMessage(_lang['RequestFailed'], false);
        }

        // Deliberately sequential rather than one parallel $.when of all three.
        // /ui/risk/fields and /ui/risk/layout are per-template-group, and the
        // group that matters is THIS RISK's -- which is only known once the
        // values endpoint comes back. Asking for them up front meant passing
        // template_group_id: '', which resolves to the VIEWER's default group;
        // on a multi-template-group install (Customization Extra) that laid
        // one group's field roster over another group's values, since both
        // values endpoints resolve their per-group data (custom fields for
        // Details, the Mitigation field roster itself) against the risk's own
        // group. One extra round trip is the cost of them agreeing.
        fetchJSON(valuesPath, {}).done(function (valuesResponse) {
            if (instance.generation !== generation) {
                return;
            }

            // Falls back to '' -- the viewer's default group -- only if the
            // server did not name one, which is the pre-existing behaviour.
            var templateGroupId = valuesResponse.template_group_id || '';

            $.when(
                fetchJSON(profile.endpoints.fields, { template_group_id: templateGroupId, tab_index: tabIndex }),
                fetchJSON(profile.endpoints.layout, { template_group_id: templateGroupId, tab_index: tabIndex })
            ).done(function (fields, cards) {
                if (instance.generation !== generation) {
                    return;
                }
                var data = {
                    fields: fields,
                    cards: cards,
                    values: valuesResponse.values,
                    supportingDocumentationHtml: valuesResponse[supportingDocumentationHtmlKey],
                    // Generic passthrough of the values endpoint's own
                    // top-level keys, verbatim (snake_case, matching the raw
                    // API response) -- NOT renamed/interpreted, so a caller
                    // that reads them off onLoaded()'s `data` (and off the
                    // identical object renderReadMode() caches for a later
                    // Cancel) gets exactly what the endpoint returned.
                    // Undefined on tabs whose endpoint has no such key (Details/
                    // Mitigation have no can_perform_review) -- harmless, since
                    // only the Review tab's coordinator (risk-view-review.js,
                    // Phase 4c-ii) reads it. can_perform_review is RISK-
                    // INSTANCE-SPECIFIC (check_review_permission_by_risk_id(),
                    // tiered by the risk's own calculated level) -- there is no
                    // static page attribute it could come from instead.
                    can_perform_review: valuesResponse.can_perform_review,
                    template_group_id: valuesResponse.template_group_id,
                    // Same generic passthrough as the two keys above --
                    // undefined on tabs whose endpoint has no such key
                    // (Mitigation/Review). Only the Details tab's coordinator
                    // (risk-view-details.js) reads it, to patch the page-
                    // header Inherent/Residual Risk tiles after a save (see
                    // api_get_ui_risk_values()'s own docblock for why that
                    // patch has to happen from here).
                    risk_summary: valuesResponse.risk_summary,
                    // Same generic passthrough -- undefined on Details/Review
                    // (only Mitigation's values endpoint returns it). Read
                    // directly by risk-view-mitigation.js's own
                    // renderAcceptMitigationWidget(), not by anything in
                    // this file -- AcceptMitigation is a top-level tab
                    // action, not a card field (see risk-details-form.js's
                    // MitigationControls docblock for the parallel
                    // reasoning).
                    accept_mitigation: valuesResponse.accept_mitigation,
                    // Same generic passthrough. Read by risk-view-
                    // mitigation.js to decide whether to append the
                    // Mitigation Controls table section into the
                    // 'controls' card this file renders -- see that file's
                    // own comment for why the table itself is appended
                    // from OUTSIDE renderCards()/renderFieldItem() rather
                    // than threaded through them.
                    can_select_mitigation_controls: valuesResponse.can_select_mitigation_controls,
                    // Same generic passthrough -- gates the table's row
                    // edit-action specifically (plan_mitigations), separate
                    // from can_select_mitigation_controls (governance,
                    // which controls whether the table/picker are shown at
                    // all). See that key's own docblock, api/v2/includes/api.php.
                    can_edit_mitigation: valuesResponse.can_edit_mitigation
                };
                renderCards(container, data.fields, data.cards, data.values, data.supportingDocumentationHtml, riskId, options.inlineEditable, instance);
                if (typeof onLoaded === 'function') {
                    onLoaded(data);
                }
            }).fail(failed);
        }).fail(failed);
    }

    // Renders a previously-cached payload (the exact object handed to
    // init()'s onLoaded) with no fetch. Does not touch instance/generation
    // state -- there is no in-flight async work to invalidate here, unlike
    // init(). Callers that also want to STOP a still-in-flight init() fetch
    // from clobbering this render afterward should call destroy() first (or
    // rely on their own coordinator-level guard, e.g. risk-view-details.js).
    //
    // `options` (optional): {profile, honorCardWidths, responsive}, as for
    // init(). Omitted, a container still holding a live instance keeps that
    // instance's settings; a destroyed/never-built one renders as risk.
    function renderFromData(containerSelector, data, riskId, inlineEditable, options) {
        var container = $(containerSelector);
        if (container.length === 0 || !data) {
            return;
        }
        var instance = getOrCreateInstance(containerSelector);
        if (options) {
            applyRenderOptions(instance, options);
        }
        renderCards(container, data.fields, data.cards, data.values, data.supportingDocumentationHtml, riskId, inlineEditable, instance);
    }

    function applyRenderOptions(instance, options) {
        instance.rawProfile = options.profile || null;
        instance.profile = resolveViewProfile(options.profile);
        instance.honorCardWidths = !!options.honorCardWidths;
        instance.responsive = !!options.responsive;
    }

    function destroy(containerSelector) {
        var instance = instancesByContainer[containerSelector];
        if (!instance) {
            return;
        }
        destroyOpenInlineEditInstances(instance);
        instance.generation += 1;
        $(containerSelector).empty();
        delete instancesByContainer[containerSelector];
    }

    window.RiskDetailsView = {
        init: init,
        renderFromData: renderFromData,
        destroy: destroy,
        // Exposed so risk-view-mitigation.js's coordinator can build the
        // Accept/Reject widget itself (a top-level tab action, not a Cards
        // field -- see this function's own docblock) without this module
        // reaching into a DOM structure it doesn't own. Mirrors the existing
        // reverse dependency this pair of engines already has
        // (risk-details-view.js itself calls window.CvssRiskLevelPill,
        // defined in risk-details-form.js), so this is not a new coupling
        // shape, just the same one in a new direction.
        buildAcceptMitigationWidget: buildAcceptMitigationWidget,
        // Exposed for the same reason as buildAcceptMitigationWidget above:
        // risk-view-review.js's "View All Reviews" history modal needs to
        // render one .sr-qgrid per historical review using the SAME field
        // roster/order/widgets (renderFieldItem()) the live Review card
        // above it already uses, rather than a hand-rolled lookalike. See
        // renderFieldsGrid()'s own docblock.
        renderFieldsGrid: renderFieldsGrid,
        // `profile` optional (default risk), as everywhere else here.
        cardIcon: function (cardKey, profile) { return resolveViewProfile(profile).maps.cardIcons[cardKey] || 'fa-square'; },
        cardLabel: function (cardKey, profile) { return resolveViewProfile(profile).maps.cardLabels[cardKey] || cardKey; }
    };
})();
