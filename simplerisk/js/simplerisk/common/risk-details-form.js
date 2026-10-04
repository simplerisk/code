/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// ----------------------------------------------------------------------
// Risk details form -- the shared Cards/GridStack rendering engine.
//
// Extracted verbatim from js/simplerisk/pages/submit-risk.js, which is now a
// thin wrapper that calls into this module for the standalone Submit Risk
// page. The behavior is unchanged; what changed is that the engine no longer
// assumes one hardcoded container, so modal callers can render the same form
// into their own container.
//
// Public surface (window.RiskDetailsForm):
//   init(containerSelector, options)  -- build into that container
//                                        (options.profile selects the record
//                                        type; omitted = risk, see RISK_PROFILE)
//   destroy(containerSelector)        -- full teardown of that container
//   submit(containerSelector)         -- host-driven save; Promise<{ok, data}>
//   isDirty(containerSelector)        -- form differs from its built state
//
// Everything this module builds keeps the id SHAPE the standalone page has
// always used ('<prefix>-canvas', '-tabs', '-form', '-submit',
// '-template-group-id'); only the prefix is derived from the caller's
// container. See containerIdPrefix().
// ----------------------------------------------------------------------

(function () {
    'use strict';

    var CARD_LABELS = {
        general: _lang['CardGeneral'],
        classification: _lang['CardClassification'],
        scoring: _lang['CardScoring'],
        // The Assignment card reuses the pre-existing 'Assignment' key rather
        // than adding a Card-prefixed twin: Incident Management already labels
        // the very same owner/team/stakeholders grouping with it.
        assignment: _lang['Assignment'],
        additional_info: _lang['CardAdditionalInformation'],
        custom_fields: _lang['CardCustomFields'],
        // Mitigation tab (tab_index=2, Phase 4b-iii) card keys -- see
        // risk-details-view.js's identical entry for the full reasoning.
        strategy: _lang['CardMitigationStrategy'],
        solution: _lang['CardMitigationSolution'],
        controls: _lang['CardMitigationControls'],
        // Review tab (tab_index=3, Phase 4c-ii) -- see
        // risk-details-view.js's identical entry for the full reasoning.
        review: _lang['CardReview']
    };

    // Card header icon (Font Awesome), keyed identically to CARD_LABELS
    // above. Duplicated from customization-layout-editor.js's
    // CARD_DEFS_BY_CANVAS -- the canonical source -- the same
    // "duplicate, not shared module" pattern CARD_LABELS above already uses.
    // See risk-details-view.js's identical CARD_ICONS for the read-mode side
    // of this same duplication.
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

    // ------------------------------------------------------------------
    // Task 7: field-name -> widget-type lookup table.
    //
    // Core (is_basic=1) fields NEVER carry a real `type` (Task 2 confirmed:
    // every is_basic=1 row is type='' always, schema-level, in both the
    // Extra-on and no-Extra API branches) -- so core widget selection is by
    // NAME here, against the REAL current control each field's own
    // display_*_edit() function in includes/displayrisks.php renders today:
    //
    //   Subject                 -> includes/display.php: plain <input required>
    //   Category                -> display_category_edit(): create_dropdown('category')            => single <select>
    //   SiteLocation             -> display_location_edit(): create_multiple_dropdown(..., 'multiselect') => bootstrap-multiselect
    //   RiskSource               -> display_risk_source_edit(): create_dropdown('source')            => single <select>
    //   ExternalReferenceId      -> display_external_reference_id_edit(): plain <input>
    //   ControlRegulation        -> display_control_regulation_edit(): create_dropdown('frameworks') => single <select>
    //                               (NOT plain text -- the brief's own inventory list called this
    //                               one a "plain-text-input field"; reading the real function shows
    //                               it is a single <select> of frameworks, same shape as Category)
    //   ControlNumber            -> display_control_number_edit(): plain <input>
    //   Team                     -> display_team_edit(): create_multiple_dropdown(..., 'multiselect') => bootstrap-multiselect
    //   AdditionalStakeholders   -> display_additional_stakeholders_edit(): create_multiusers_dropdown(..., 'multiselect') => bootstrap-multiselect
    //   Owner                    -> display_owner_edit(): create_selectize_dropdown('enabled_users', ['name'=>'owner'])
    //                               => a SINGLE-value selectize dropdown, not bootstrap-multiselect and not
    //                               multi-valued -- an owner is one user. (Deviates from the brief's Step 2
    //                               example, which guessed bootstrap-multiselect for this field; the real
    //                               function reads differently and this table follows the real function,
    //                               per Task 2's own precedent of correcting guessed widget shapes.)
    //   OwnersManager            -> display_owners_manager_edit(): same create_selectize_dropdown() shape as Owner
    //   RiskAssessment           -> display_risk_assessment_title_edit(): <textarea>, wired to HugeRTE by
    //                               risk.js's init_minimun_editor('#assessment') on the legacy Add page today
    //   AdditionalNotes          -> display_additional_notes_edit(): same <textarea>+HugeRTE pattern for '#notes'
    //   SupportingDocumentation  -> display_supporting_documentation_add(): <input type='file'>
    //   Tags                     -> display_risk_tags_edit(): selectize, create:true, own AJAX options source
    //                               (/api/v2/management/tag_options_of_type) -- reused as-is below
    //   RiskMapping              -> display_risk_mapping_edit(): create_selectize_dropdown('risk_catalog') => grouped selectize
    //   ThreatMapping            -> display_threat_mapping_edit(): create_selectize_dropdown('threat_catalog') => grouped selectize
    //   Technology               -> display_technology_edit(): create_multiple_dropdown(..., 'multiselect') => bootstrap-multiselect
    //                               (the exact same mechanism as SiteLocation/Team above; options sourced from
    //                               get_options_from_table('technology'), already supported by that helper's own
    //                               table list -- includes/functions.php:6652)
    //   MitigationControls       -> Mitigation tab (tab_index=2), Phase 4b-ii. Legacy:
    //                               mitigation_controls_dropdown() (includes/display.php), a bootstrap-multiselect
    //                               like SiteLocation/Team/Technology above, PLUS print_mitigation_controls_table()'s
    //                               read-only accordion of per-selected-control detail cards underneath it. Both
    //                               halves are reproduced by the single 'mitigation-controls' widget below --
    //                               see buildMitigationControlsWidget()/initMitigationControlsField().
    //
    //   SubmissionDate / SubmittedBy: display_main_detail_fields_by_panel_add()'s real switch
    //   (includes/displayrisks.php:3267-3379) has NO case for either name -- both are set
    //   automatically (submission date to now(), submitted_by to the current user) and nothing
    //   renders for them on the real Add page today. 'skip' reproduces that exactly.
    //
    //   JiraIssueKey             -> display_jira_issue_key_edit() (includes/displayrisks.php):
    //                               plain <input type='text' name='jira_issue_key'> => 'text', the
    //                               same widget ExternalReferenceId/ControlNumber use. The field is
    //                               already Extra-gated server-side -- api_resolve_ui_risk_fields_for_group()
    //                               (api/v2/includes/api.php) drops it from the roster entirely when
    //                               !jira_extra(), the same live check the legacy function above makes
    //                               on every render -- so nothing client-side needs to re-check it; the
    //                               field simply never arrives here with the Extra off.
    //
    //   RiskScoringMethod -> 'scoring-method' (below). Phase 4d-i: the method-switching dropdown
    //   itself, plus two of its six methods (Classic, Custom) as real widgets. Phase 4d-ii/iii/iv
    //   added real widgets for CVSS, DREAD, and OWASP respectively. Contributing Risk is the one
    //   method still rendering a "not yet available" notice instead of a real control (its
    //   relational, not form-input, field shape needs its own future investigation). See
    //   buildScoringMethodWidget()/initScoringMethodField() below.
    //
    //   AffectedAssets -> the shared assets+asset-groups selectize every "affected assets"/"Mapped
    //   Assets" field in the product already uses (setupAssetsAssetGroupsWidgetForRisk(),
    //   common.js -- reused as-is here, NOT reimplemented; see buildAssetsAssetGroupsWidget()/
    //   initAssetsAssetGroupsField() below). Its own AJAX call (GET /api/v2/asset-group/options)
    //   already handles both fetching the roster AND pre-selecting the risk's current picks, keyed
    //   off instance.updateRiskId -- there is no generic get_options_from_table() source for it,
    //   which is exactly why it stayed a placeholder until now.
    var CORE_FIELD_WIDGETS = {
        Subject: 'text',
        Category: 'select',
        SiteLocation: 'multiselect',
        RiskSource: 'select',
        ExternalReferenceId: 'text',
        ControlRegulation: 'select',
        ControlNumber: 'text',
        JiraIssueKey: 'text',
        Team: 'multiselect',
        AdditionalStakeholders: 'multiselect',
        Owner: 'selectize-single',
        OwnersManager: 'selectize-single',
        RiskAssessment: 'richtext',
        AdditionalNotes: 'richtext',
        SupportingDocumentation: 'file',
        Tags: 'selectize-tags',
        RiskMapping: 'selectize-grouped',
        ThreatMapping: 'selectize-grouped',
        Technology: 'multiselect',
        MitigationControls: 'mitigation-controls',
        AffectedAssets: 'assets-asset-groups',
        RiskScoringMethod: 'scoring-method',
        SubmissionDate: 'skip',
        SubmittedBy: 'skip',

        // RiskScoringHistory: a read-only chart widget (buildScoringHistoryWidget(),
        // risk-details-view.js), never editable -- 'skip' the same way SubmissionDate/
        // SubmittedBy are (see risk_details_field_renders_no_control(), includes/
        // functions.php).
        RiskScoringHistory: 'skip',

        // Mitigation tab (tab_index=2, Phase 4b-iii). Widget shapes verified
        // against the real display_mitigation_*_edit() functions
        // (includes/displayrisks.php), the same way the Details-tab table
        // above was built:
        //   MitigationDate       -> display_mitigation_submission_date_edit(): a
        //                           disabled <input> the user can never change --
        //                           same 'skip' treatment as SubmissionDate/
        //                           SubmittedBy above (still resolved for read
        //                           mode; just renders no edit-mode control).
        //   MitigationSubmittedBy -> a later addition (20260925001 migration,
        //                           extras/customization/upgrade.php): no legacy
        //                           display_*_edit() function exists for it at
        //                           all (there never was a legacy edit-mode
        //                           control to match) -- 'skip', same as
        //                           MitigationDate, and added as its top-row
        //                           partner on the Strategy card so the two
        //                           read-only fields fill the same row instead
        //                           of leaving a half-width gap next to
        //                           MitigationPlanning.
        //   MitigationPlanning   -> display_mitigation_planning_date_edit(): a
        //                           .datepicker <input> => same 'date' widget.
        //   PlanningStrategy     -> display_mitigation_planning_strategy_edit():
        //                           create_dropdown('planning_strategy') => single <select>.
        //   MitigationEffort     -> display_mitigation_effort_edit():
        //                           create_dropdown('mitigation_effort') => single <select>.
        //   MitigationCost       -> display_mitigation_cost_edit():
        //                           create_asset_valuation_dropdown() => single <select>,
        //                           options sourced from get_options_from_table('asset_valuation')
        //                           (api_attach_ui_risk_field_options(), api/v2/includes/api.php).
        //   MitigationOwner      -> display_mitigation_owner_edit():
        //                           create_dropdown('enabled_users', ...) => single <select>
        //                           (a PLAIN dropdown, unlike Details' Owner field, which is
        //                           selectize -- this one genuinely renders a different control).
        //   MitigationTeam       -> display_mitigation_team_edit():
        //                           create_multiple_dropdown('team', ...) => bootstrap-multiselect,
        //                           same mechanism as Details' own Team field.
        //   MitigationPercent    -> display_mitigation_percent_edit(): <input type='number'> =>
        //                           reuses the 'text' widget (a plain input works functionally).
        //   CurrentSolution / SecurityRequirements / SecurityRecommendations ->
        //                           'richtext' (upgraded from a legacy plain <textarea> at the
        //                           user's request -- the legacy EDIT form never wired HugeRTE
        //                           to these three, but the legacy VIEW side already ran every
        //                           one of them through $escaper->purifyHtml() at its sink
        //                           (display_current_solution_view()/display_security_
        //                           requirements_view()/display_security_recommendations_view(),
        //                           includes/displayrisks.php, all three using the SAME
        //                           'rich-text-container' class RiskAssessment/AdditionalNotes'
        //                           view uses) -- so this only completes an editor the display
        //                           layer already expected, rather than introducing a new
        //                           content shape. Same widget/resolver pattern as RiskAssessment/
        //                           AdditionalNotes above: api_ui_mitigation_field_resolvers()
        //                           (api/v2/includes/api.php) adds a purify_html()'d
        //                           'display_html' key for read mode. All three are also
        //                           encrypted at rest (that resolver's try_decrypt() calls).
        //   MitigationSupportingDocumentation -> a file upload, like Details' own
        //                           SupportingDocumentation -- excluded in update mode by the
        //                           SAME generic 'file' + submitMode==='update' guard below, and
        //                           rendered read-mode via its own dedicated HTML passthrough
        //                           (risk-details-view.js).
        //   AcceptMitigation     -> absent from THIS table AND absent from this whole canvas:
        //                           moved out of the Cards field roster entirely (Extra-gated
        //                           the same way JiraIssueKey used to be off it, but
        //                           unconditionally rather than by Extra state --
        //                           risk_mitigation_field_is_positionable(), includes/
        //                           functions.php, api_resolve_ui_risk_fields_for_group()'s own
        //                           filter, api/v2/includes/api.php). It is now a top-level
        //                           Accept/Reject action next to Edit Mitigation
        //                           (risk-view-mitigation.js), not a field this engine ever sees.
        //   MitigationControlsList -> never reaches this table: it is a derived, non-positionable
        //                           view of MitigationControls (get_risk_mitigation_core_field_card_map()'s
        //                           own docblock) that never gets a card_key, so it never appears in the
        //                           field roster this table is consulted against.
        MitigationDate: 'skip',
        MitigationSubmittedBy: 'skip',
        MitigationPlanning: 'date',
        PlanningStrategy: 'select',
        MitigationEffort: 'select',
        MitigationCost: 'select',
        MitigationOwner: 'select',
        MitigationTeam: 'multiselect',
        MitigationPercent: 'text',
        CurrentSolution: 'richtext',
        SecurityRequirements: 'richtext',
        SecurityRecommendations: 'richtext',
        MitigationSupportingDocumentation: 'file',

        // Review tab (tab_index=3, Phase 4c-ii). Widget shapes verified
        // against the real display_review_edit()/display_next_step_edit()/
        // display_comments_edit() functions (includes/displayrisks.php):
        //   Review               -> display_review_edit(): create_dropdown('review', ...) =>
        //                           single <select>, same mechanism as PlanningStrategy above.
        //   NextStep             -> display_next_step_edit(): create_dropdown('next_step', ...)
        //                           PLUS a conditionally-shown Project selectize field when the
        //                           value is 2 ("Consider for Project") => its own
        //                           'next-step' widget (see buildNextStepWidget()/
        //                           initNextStepField() below), the same "one widget, multiple
        //                           internal parts" shape MitigationControls already uses.
        //   Comment              -> 'richtext' (upgraded from 'textarea' for the same reason
        //                           CurrentSolution/SecurityRequirements/SecurityRecommendations
        //                           were above): display_comments_edit() is a plain <textarea>,
        //                           but the legacy VIEW side (display_comments_view()) already
        //                           runs it through $escaper->purifyHtml() into the SAME
        //                           'rich-text-container' class -- and submit_management_review()
        //                           (includes/functions.php) already purify_html()'s every
        //                           incoming comment before encrypting it ("Sanitizing input
        //                           that comes from the WYSIWYG editor or outside sources"),
        //                           confirming existing stored reviews genuinely contain HTML.
        //                           A plain <textarea> showed that HTML as literal angle-bracket
        //                           source instead of rendering it -- both on read (the current-
        //                           review Card) and in the "View All Reviews" history modal.
        //                           api_ui_review_field_resolvers() (api/v2/includes/api.php)
        //                           now adds the same purify_html()'d 'display_html' key the
        //                           other three richtext fields use.
        //   ReviewDate/Reviewer  -> both plain echoed text in the legacy edit form (no control
        //                           at all) => 'skip', same treatment as SubmissionDate/
        //                           SubmittedBy/MitigationDate above.
        //   NextReviewDate       -> 'set-next-review-date', at the user's request: this field
        //                           and the former, separate 'SetNextReviewDate' field were
        //                           already the SAME concept wearing two names and showing in
        //                           two different modes -- NextReviewDate rendered the LAST
        //                           review's stored date in READ mode only (no edit-mode
        //                           control at all, previously 'skip'), while SetNextReviewDate
        //                           rendered the Yes/No-plus-override-date widget in EDIT mode
        //                           only (no read-mode resolver at all -- api_ui_review_field_
        //                           resolvers(), api/v2/includes/api.php, never had a
        //                           'set_next_review_date' entry, so fieldValueEntry() always
        //                           returned null for it in read mode). Combined onto this ONE
        //                           roster entry: 'next_review_date' (the resolver key
        //                           CORE_FIELD_FORM_NAMES already mapped NextReviewDate to)
        //                           still drives its read-mode display unchanged, and it now
        //                           also carries the override widget in edit mode.
        //                           buildSetNextReviewDateWidget()'s own kept name is
        //                           cosmetic -- the widget's radio/date input submit under
        //                           fixed names ('custom_date'/'next_review') that were NEVER
        //                           tied to which roster field hosted them (see that function's
        //                           own docblock), so hosting it under 'NextReviewDate' instead
        //                           needed no change to the widget itself, only to which name
        //                           populateFieldContent()'s generic _lang[field.name] label
        //                           now resolves for its header -- "Next Review Date" reads
        //                           more naturally there than "Set Next Review Date" did
        //                           anyway, and the sub-question ("Would you like to use a
        //                           different date instead?") is the widget's own separate,
        //                           unaffected label.
        Review: 'select',
        NextStep: 'next-step',
        Comment: 'richtext',
        ReviewDate: 'skip',
        Reviewer: 'skip',
        NextReviewDate: 'set-next-review-date'
    };

    // Custom (is_basic=0) fields DO carry a real `type` (Task 2 confirmed the
    // contrast against core fields), matching
    // display_custom_field_input_element() (extras/customization/index.php)
    // exactly. 'longtext' is a plain multi-line <textarea> there -- NOT
    // HugeRTE -- so it gets its own 'textarea' widget, distinct from
    // 'richtext' (which is reserved for RiskAssessment/AdditionalNotes, the
    // two fields real evidence shows ARE HugeRTE-backed today).
    var CUSTOM_FIELD_WIDGETS = {
        dropdown: 'select',
        multidropdown: 'multiselect',
        shorttext: 'text',
        longtext: 'textarea',
        date: 'date',
        user_multidropdown: 'multiselect',
        hyperlink: 'text'
    };

    // DOM `name` attributes, matching the real name each legacy display_*_edit()
    // function emits (see the lookup table above for the source function per
    // field) -- forward-compatible with a later task's form-submit wiring, even
    // though building that wiring is out of this task's scope. Multi-valued
    // widgets get a `[]` suffix appended by fieldFormName() below, the same way
    // create_multiple_dropdown()/create_multiusers_dropdown() append it themselves.
    var CORE_FIELD_FORM_NAMES = {
        Subject: 'subject',
        Category: 'category',
        SiteLocation: 'location',
        RiskSource: 'source',
        ExternalReferenceId: 'reference_id',
        ControlRegulation: 'regulation',
        ControlNumber: 'control_number',
        // Matches api_ui_core_field_resolvers()'s own resolver key
        // (api/v2/includes/api.php) and updateRisk()'s (includes/api.php)
        // $_POST read -- serves BOTH fieldFormName()'s submit-name purpose
        // and prefillLookupKey()'s read-mode lookup purpose, same as every
        // other entry here.
        JiraIssueKey: 'jira_issue_key',
        Team: 'team',
        AdditionalStakeholders: 'additional_stakeholders',
        Owner: 'owner',
        OwnersManager: 'manager',
        RiskAssessment: 'assessment',
        AdditionalNotes: 'notes',
        SupportingDocumentation: 'file',
        Tags: 'tags',
        RiskMapping: 'risk_catalog_mapping',
        ThreatMapping: 'threat_catalog_mapping',
        Technology: 'technology',
        MitigationControls: 'mitigation_controls',
        // Matches BOTH the name addRisk() (includes/api.php) reads off POST
        // (assets_asset_groups[]) AND the key api_ui_core_field_resolvers()
        // (api/v2/includes/api.php) uses in the read-mode values response --
        // this ONE map value serves fieldFormName()'s submit-name purpose and
        // prefillLookupKey()'s read-mode lookup purpose, same as every other
        // entry here (e.g. 'team').
        AffectedAssets: 'assets_asset_groups',

        // Matches api_ui_core_field_resolvers()'s own resolver key
        // (api/v2/includes/api.php) -- serves BOTH fieldFormName()'s
        // submit-name purpose and prefillLookupKey()'s read-mode lookup
        // purpose, same as every other entry here.
        RiskScoringMethod: 'scoring_method',

        // Mitigation tab (tab_index=2, Phase 4b-iii). Matches the SAME form
        // names api_ui_mitigation_field_resolvers() (api/v2/includes/api.php)
        // keys its {raw, display} response under, and saveMitigation()'s
        // (includes/api.php) $mitigation_fields allowlist expects on write.
        MitigationDate: 'submission_date',
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
        // Never submitted (excluded by the 'file' + submitMode==='update'
        // guard, same as SupportingDocumentation) -- named for parity/
        // debuggability only.
        MitigationSupportingDocumentation: 'mitigation_file',

        // Review tab (tab_index=3, Phase 4c-ii). Matches the form-name keys
        // api_ui_review_field_resolvers() uses.
        Review: 'review',
        NextStep: 'next_step',
        Comment: 'comments',
        ReviewDate: 'review_date',
        Reviewer: 'reviewer'
    };

    var MULTI_VALUE_WIDGETS = {
        multiselect: true,
        'selectize-tags': true,
        'selectize-grouped': true,
        file: true,
        'mitigation-controls': true,
        'assets-asset-groups': true
    };

    // ------------------------------------------------------------------
    // Profiles.
    //
    // Every risk-specific literal this engine used to read directly (the
    // field-name -> widget / form-name maps above, the card labels and icons,
    // the /ui/risk/* endpoints, the tag type, the always-required field names
    // and the submit URLs) is collected here as RISK_PROFILE. init() reads
    // `options.profile || RISK_PROFILE` once, so every existing caller -- which
    // passes no profile -- renders and submits exactly what it did before. A
    // caller for a different record type (the asset record modal) passes its
    // own profile; every key is optional and falls back to the risk value.
    //
    // Maps are selected per profile, never merged key by key: an asset
    // profile's `maps.formNames` REPLACES the risk map wholesale. The asset
    // fields Team/SiteLocation/Tags share their names with risk fields but
    // submit under different names, so a merged map would silently post the
    // risk name for them.
    //
    //   endpoints   {templateGroups, fields, layout, values} -- paths under
    //               /api/v2. `values` may contain '{id}' (or be a function of
    //               the id); the form engine never fetches it (callers pass
    //               prefillValues), it is here so one profile object serves
    //               both engines.
    //   maps        {widgets, customWidgets, formNames, cardLabels, cardIcons,
    //               multiValue, widgetFor}. widgetFor(field), optional: a
    //               widget type for this field instance (e.g. a read-only
    //               type when the payload says the caller may not choose),
    //               or null to use `widgets` as usual.
    //   tagType     the `type` sent to tag_options_of_type
    //   requiredNames  field names that are always required regardless of
    //               the stored `required` flag
    //   widgetRegistry {build(type, field, ctx), activate(type, $el, field,
    //               ctx), prefill(type, $el, value, ctx), readRender(type,
    //               entry, ctx)} -- consulted BEFORE the built-in widgets; a
    //               falsy return (build/readRender) or anything but `true`
    //               (activate/prefill) falls through to the built-ins.
    //               build/readRender must return a jQuery object or a DOM
    //               Node. A string is NEVER parsed as HTML: it is shown as a
    //               text node (with a console warning), so a hook cannot turn
    //               API data into markup by accident.
    //   submit      {createUrl, updateUrl(id), method, encode, serialize($form,
    //               {mode})} -- URLs are BASE_URL-relative. method/encode may
    //               be a string (both modes) or {create, update}; null keeps
    //               the risk defaults (POST+multipart create, PATCH+urlencoded
    //               update). encode: 'multipart' | 'urlencoded' | 'json'
    //               (json requests carry csrf-magic's token in the CSRF-TOKEN
    //               header, since csrf-magic never injects into a JSON body).
    //               serialize() may return null/undefined (default body), a
    //               string or FormData (sent as is), or a plain object (the
    //               body). A plain object's own `clear` array is taken out of
    //               the body and each name in it is sent as an explicit empty
    //               value ("clear this field"): urlencoded `name=`, multipart
    //               an empty part, json `[]` for a '[]'-suffixed name and ''
    //               otherwise. `{clear: [...]}` alone keeps the default body
    //               and adds the markers. This is how a composite widget
    //               whose empty state renders no named control (e.g. zero
    //               mapped-control rows) says "cleared" rather than
    //               "unchanged". (Without an adapter, a composite can instead
    //               render a hidden <input name="x[]" value=""> while empty --
    //               the same marker the multi-selects send.)
    //   inlineSaveUrl(id), onInlineSaved -- read by risk-details-view.js.
    //
    // Writes fail CLOSED for a non-risk profile: its submit.createUrl,
    // submit.updateUrl and inlineSaveUrl never fall back to the risk URLs. A
    // missing one makes submit() resolve {ok: false, error:
    // 'profile-missing-url'} (and the view offers no inline edit), with a
    // one-time console warning -- an asset form must never post to /risks.
    // ------------------------------------------------------------------
    var RISK_PROFILE = {
        endpoints: {
            templateGroups: '/ui/risk/template_groups',
            fields: '/ui/risk/fields',
            layout: '/ui/risk/layout',
            values: '/ui/risk/{id}/values'
        },
        maps: {
            widgets: CORE_FIELD_WIDGETS,
            customWidgets: CUSTOM_FIELD_WIDGETS,
            formNames: CORE_FIELD_FORM_NAMES,
            cardLabels: CARD_LABELS,
            cardIcons: CARD_ICONS,
            multiValue: MULTI_VALUE_WIDGETS
        },
        tagType: 'risk',
        requiredNames: ['Review', 'NextStep'],
        widgetRegistry: null,
        submit: {
            createUrl: '/api/v2/risks',
            updateUrl: function (id) { return '/api/v2/risks/' + id; },
            method: null,
            encode: null,
            serialize: null
        },
        inlineSaveUrl: function (id) { return '/api/v2/risks/' + id; },
        onInlineSaved: null
    };

    var resolvedProfiles = (typeof window.WeakMap === 'function') ? new window.WeakMap() : null;

    // Fills every key a caller's profile leaves out with the risk value.
    // Cached per profile object, so the per-field lookups below cost one
    // WeakMap hit rather than a rebuild.
    function resolveProfile(profile) {
        if (!profile || profile === RISK_PROFILE) {
            return RISK_PROFILE;
        }
        if (profile.__resolvedProfile) {
            return profile;
        }
        if (resolvedProfiles && resolvedProfiles.has(profile)) {
            return resolvedProfiles.get(profile);
        }
        var resolved = {
            __resolvedProfile: true,
            endpoints: $.extend({}, RISK_PROFILE.endpoints, profile.endpoints || {}),
            maps: $.extend({}, RISK_PROFILE.maps, profile.maps || {}),
            tagType: profile.tagType || RISK_PROFILE.tagType,
            requiredNames: Array.isArray(profile.requiredNames) ? profile.requiredNames : RISK_PROFILE.requiredNames,
            widgetRegistry: profile.widgetRegistry || null,
            // Fail closed: no risk URL is inherited (see the docblock above).
            submit: $.extend({ createUrl: null, updateUrl: null, method: null, encode: null, serialize: null }, profile.submit || {}),
            inlineSaveUrl: typeof profile.inlineSaveUrl === 'function' ? profile.inlineSaveUrl : null,
            onInlineSaved: typeof profile.onInlineSaved === 'function' ? profile.onInlineSaved : null
        };
        if (resolvedProfiles) {
            resolvedProfiles.set(profile, resolved);
        }
        return resolved;
    }

    var warnedOnce = {};

    function warnOnce(key, message) {
        if (warnedOnce[key] || typeof window.console === 'undefined') {
            return;
        }
        warnedOnce[key] = true;
        window.console.warn('RiskDetailsForm: ' + message);
    }

    // build/readRender results: jQuery or Node pass through; a string becomes
    // a text node (never parsed as HTML); anything else is ignored. Shared
    // with risk-details-view.js through RiskDetailsForm.normalizeWidgetNode.
    function normalizeWidgetNode(result, hookName) {
        if (!result) {
            return null;
        }
        if (result instanceof $) {
            return result;
        }
        if (typeof window.Node === 'function' && result instanceof window.Node) {
            return $(result);
        }
        if (typeof result === 'string') {
            warnOnce('string-' + hookName, 'widgetRegistry.' + hookName + ' returned a string; it is shown as text, never parsed as HTML. Return a jQuery object or a DOM Node.');
            return $(document.createTextNode(result));
        }
        warnOnce('type-' + hookName, 'widgetRegistry.' + hookName + ' returned neither a jQuery object nor a DOM Node; ignored.');
        return null;
    }

    // method/encode: a string for both modes, or {create, update}.
    function perModeSetting(setting, mode, fallback) {
        if (setting && typeof setting === 'object') {
            return setting[mode] || fallback;
        }
        return setting || fallback;
    }

    // ------------------------------------------------------------------
    // Render scale.
    //
    // The saved layout stores every pos_y/pos_h in the units the admin layout
    // editor designs in -- top-grid rows of 60px, nested field rows of 30px
    // (customization-layout-editor.js, and the same units
    // customization_card_height_for_field_count() seeds heights in,
    // includes/functions.php). This page renders that SAME geometry on a grid
    // LAYOUT_ROW_SCALE times finer: every pos_y/pos_h is multiplied by the
    // scale and the cell heights are divided by it, so the canvas before the
    // auto-fit sweep runs is pixel-for-pixel what the editor designed. The
    // scale changes one thing only -- what the sweep is able to round to
    // afterwards.
    //
    // Why that matters, measured live on the default layout. Growth is
    // quantised: growGridItemToFitContent() can only ever ask for a WHOLE
    // number of rows, so every item pays up to one row of rounding on top of
    // the content it really holds. At the editor's own 30px nested row, an
    // ordinary label+control field (59px of content, plus GridStack's 20px of
    // per-item margin = 79px) rounds up to 3 rows = 90px -- 11px of dead space
    // inside every single field, which together with the margin read as ~31px
    // of blank between one control and the next field's label, twenty times
    // down the page. At 10px it rounds to 80px and what is left is the 20px
    // margin that IS the designed gap. The top grid's 60px row cost the same
    // way at card scale: the General card needed 423px of real content and was
    // handed 480px.
    //
    // Only the RENDER scale changes. Both grids are staticGrid:true and this
    // page never writes geometry back, the API still speaks the editor's
    // units, and every saved pos_y/pos_h keeps its original meaning -- so no
    // stored geometry and no other consumer of it is affected. Keep the scale
    // an integer divisor of 30 so a nested row stays a whole number of pixels.
    var LAYOUT_ROW_SCALE = 3;
    var TOP_CELL_HEIGHT = 60 / LAYOUT_ROW_SCALE;
    var NESTED_CELL_HEIGHT = 30 / LAYOUT_ROW_SCALE;

    // A richtext field (HugeRTE, init_compact_editor() in js/WYSIWYG/editor.js)
    // always renders to the SAME settled height regardless of its content --
    // the editor body is a fixed 150px (overflow scrolls internally, it never
    // grows with what's typed), so label + compact toolbar + editor + chrome
    // converge on the same figure every time: measured live, both
    // RiskAssessment and AdditionalNotes settle at exactly 20 nested rows
    // (200px) after growGridItemToFitContent()'s auto-fit sweep grows them
    // from their nominal seeded pos_h (2 rows).
    //
    // Seeding that number as the field's INITIAL h here, rather than letting
    // the sweep discover it later via grid.update(), matters for any card
    // where OTHER fields are placed below a richtext one -- Additional
    // Information now leads with AdditionalNotes full width, followed by two
    // paired rows. A later grid.update() growing an already-placed item by
    // ~18 rows collides with whatever already occupies that space, and
    // GridStack's _fixCollisions (gridstack-engine.js) pushes the colliding
    // items down ONE AT A TIME rather than as a block -- which does not
    // preserve which two fields were paired on a row (measured live: Control
    // Regulation/Control Number and External Reference ID/Supporting
    // Documentation's row pairing came out scrambled, though each field kept
    // its own correct left/right column). Starting the item at its true
    // settled height means the sweep's own `neededRows <= node.h` check is
    // already satisfied, so grid.update() is never called and the collision
    // cascade never runs, for the common case. RiskAssessment is unaffected
    // either way -- it's always the last field in General -- but sizing it
    // right from the start is strictly more correct than growing into it.
    //
    // Only a genuinely oversized richtext control (custom CSS, a future
    // config change) would still need growGridItemToFitContent()'s sweep,
    // which remains untouched as the safety net for that case.
    var RICHTEXT_FIELD_INITIAL_ROWS = 20;

    // packRenderableFields() computes every field's row position in the
    // SAVED layout's own units (field.pos_h, a 30px-row count) BEFORE
    // renderFieldItem() ever multiplies by LAYOUT_ROW_SCALE -- so a sibling
    // field placed after a richtext one needs its cursor pushed down by the
    // same bump in THOSE units, or it lands at the nominal pos_y (e.g. 2)
    // while the richtext field it follows is actually occupying render-scale
    // rows out to RICHTEXT_FIELD_INITIAL_ROWS, colliding with it the moment
    // it's added -- the exact same GridStack _fixCollisions cascade this
    // exists to avoid, just moved from update()-time to addWidget()-time.
    // Derived with Math.ceil() from the one render-scale constant above
    // rather than a second hand-picked number, so the two can't drift apart.
    var RICHTEXT_FIELD_MIN_SAVED_ROWS = Math.ceil(RICHTEXT_FIELD_INITIAL_ROWS / LAYOUT_ROW_SCALE);

    // ------------------------------------------------------------------
    // Per-container state.
    //
    // Every piece of build state below used to be a module-level `var`, which
    // was correct while exactly one consumer existed (the standalone Submit
    // Risk page). This module is now shared by that page AND by modal callers,
    // so a single shared set of variables would let one container's teardown
    // null out the grid another container is still rendering into. Keyed by
    // the caller's own `containerSelector` instead, so two live containers
    // never see each other's grids, editors, popup bookkeeping or timers.
    //
    // Only ever reached through getOrCreateInstance()/destroy() -- no function
    // in this file may read build state off anything but the `instance` it was
    // handed.
    var instancesByContainer = {};

    // Used to namespace the per-instance window `resize` binding, so
    // destroy() can unbind exactly its own handler.
    var instanceCounter = 0;

    // Every id this module renders keeps the SHAPE it has always had
    // ('<prefix>-canvas', '-tabs', '-form', '-submit', '-template-group-id',
    // '-field-<name>'); only the prefix is now derived from the caller's
    // container instead of being the hardcoded literal 'submit-risk'.
    //
    // The trailing '-container' is stripped so the standalone page's
    // '#submit-risk-container' keeps producing exactly the ids it produced
    // before this module was extracted (#submit-risk-canvas,
    // #submit-risk-submit, #submit-risk-template-group-id) -- those ids are a
    // real contract, not an implementation detail: the Playwright page object
    // tests/web-e2e/src/page/management/submit-risk-view.page.ts locates the
    // canvas, the submit button and the hidden template-group input by them.
    // Every caller passes an id selector (the page and all three modals), so
    // stripping a leading '#' is all the sanitizing this needs.
    function containerIdPrefix(containerSelector) {
        return String(containerSelector).replace(/^#/, '').replace(/-container$/, '');
    }

    function getOrCreateInstance(containerSelector) {
        instancesByContainer[containerSelector] = instancesByContainer[containerSelector] || {
            containerSelector: containerSelector,
            idPrefix: containerIdPrefix(containerSelector),
            // Bumped by init(); every fetch callback compares the value it
            // captured against this before touching the DOM, so a response
            // that arrives after the container was torn down (or rebuilt)
            // renders nothing. See init().
            generation: 0,
            // False for the standalone page (which gets the sticky action
            // bar), true for a modal caller (which gets the hidden
            // .save-risk-form affordance risk.js's own handler drives).
            embedded: false,
            formName: null,
            prefillValues: null,
            topGrid: null,
            // Nested (per-card) GridStack instances built by the current
            // buildCanvas() call. Torn down and rebuilt on every tab switch --
            // see destroyCanvas().
            nestedGrids: [],
            // HugeRTE editor element ids created by the current buildCanvas()
            // call (RiskAssessment/AdditionalNotes). destroyCanvas() must
            // remove() these before the next tab switch empties the canvas --
            // GridStack.destroy() only unbinds GridStack's own handlers, it
            // knows nothing about HugeRTE, and a stale editor instance bound
            // to a now-removed textarea both leaks and collides on id if the
            // same field name reappears on re-render.
            activeEditorIds: [],
            // The card order the SAVED layout designed, captured at build time
            // as [{ el, y }] in the order buildCanvas() added the cards.
            // autoFitCanvas() re-seats the cards against this after growing
            // them, so the rendered order is the designed one rather than
            // whatever GridStack's collision push-down happened to produce.
            // Rebuilt on every tab switch.
            cardLayout: [],
            // One entry per CURRENTLY OPEN popup (a multiselect menu, a
            // selectize dropdown): { popup, nodes } where `nodes` are the
            // .grid-stack-item-content ancestors that popup is holding
            // un-clipped -- see holdClippingOpen().
            popupUnclips: [],
            // Set when a sweep was skipped because a popup was open, so the
            // close can run the one it owed.
            autoFitDeferred: false,
            // The template group whose fields/layout are currently displayed
            // -- mirrored into the hidden '<prefix>-template-group-id' input
            // (inside '<prefix>-form') on every tab switch, and read back off
            // that input by the submit path so the submitted risk lands in the
            // same template group the user is looking at.
            currentTemplateGroupId: null,
            // Auto-fit scheduling state (see scheduleAutoFit()).
            autoFitTimers: [],
            autoFitFrame: null,
            autoFitSoonTimer: null,
            autoFitSweeping: false,
            autoFitObserver: null,
            // Per-item "is this measurement stable yet" state for
            // growGridItemToFitContent()'s grow-commit gate -- see that
            // function's own comment. Keyed by the field/card's
            // '.grid-stack-item' element, which is discarded on every
            // rebuild (destroyCanvas()), so stale entries are simply
            // unreachable rather than needing explicit cleanup.
            growStabilityMap: new WeakMap(),
            // How many items growStabilityMap currently holds a PENDING
            // (unconfirmed) entry for -- a WeakMap has no size/iteration, so
            // this is the only way autoFitCanvas() can tell whether the sweep
            // it just ran left anything still awaiting its confirming second
            // pass. See autoFitCanvas()'s own comment for why that second
            // pass cannot always be assumed to happen on its own.
            growPendingCount: 0,
            // The resolved profile (RISK_PROFILE unless the caller passed
            // one) -- see RISK_PROFILE's own docblock.
            profile: RISK_PROFILE,
            // options.actionsBar === false: no sticky bar and no hidden
            // risk.js affordance; the host submits via submit().
            actionsBar: true,
            // Responsive mode (options.responsive) -- see
            // applyResponsiveColumns().
            responsive: false,
            responsiveBreakpoint: RESPONSIVE_BREAKPOINT,
            narrow: false,
            responsiveObserver: null,
            // isDirty() baseline: the serialized form at the end of the
            // build, plus each HugeRTE editor's content once initialized
            // (keyed by editor id). See formSnapshot().
            dirtySnapshot: null,
            editorBaselines: {},
            // In-flight submit() promise, so a double click returns the same
            // request instead of posting twice.
            pendingSubmit: null,
            // Set by the first trusted user interaction inside the container
            // (bindUserInteractionTracking()); until then the dirty baseline
            // follows the settling form.
            userInteracted: false,
            userInteractionListener: null,
            userInteractionElement: null,
            resizeNamespace: 'resize.riskDetailsForm' + (++instanceCounter)
        };
        return instancesByContainer[containerSelector];
    }

    function fetchJSON(path, params) {
        return $.ajax({
            url: BASE_URL + '/api/v2' + path,
            type: 'GET',
            data: params
        }).then(function (result) {
            return result.data;
        });
    }

    // Review and NextStep are always required when submitting a review --
    // hardcoded here the same way Subject's own synthetic entry hardcodes
    // required:1 (get_subject_synthetic_field_entry(), includes/
    // functions.php), at the user's explicit request to mirror that exact
    // precedent: unconditional, not the standard admin-configurable
    // 'required' flag every other Cards field uses. An admin's own
    // Customization layout editor checkbox for these two has no effect on
    // this -- same as Subject, which isn't even a positionable field there
    // at all. Every other field still goes through the normal DB-backed
    // flag, so this single function change is enough for both the '*'
    // label marker and the required-target switch in populateFieldContent()
    // below, which already read isFieldRequired(field) uniformly.
    // The always-required names come from the profile (requiredNames); the
    // risk profile's are Review and NextStep, as described above.
    function isFieldRequired(field, profile) {
        if (resolveProfile(profile).requiredNames.indexOf(field.name) !== -1) {
            return true;
        }
        return Number(field.required) === 1;
    }

    // `profile` is optional on these three (and on the public getWidgetType/
    // getFormName they back) -- omitted means the risk profile.
    function fieldWidgetType(field, profile) {
        var maps = resolveProfile(profile).maps;
        if (typeof maps.widgetFor === 'function') {
            var override = maps.widgetFor(field);
            if (override) {
                return override;
            }
        }
        if (Number(field.is_basic) === 1) {
            return (maps.widgets || {})[field.name] || null;
        }
        return (maps.customWidgets || {})[field.type] || null;
    }

    function fieldFormName(field, widgetType, profile) {
        var maps = resolveProfile(profile).maps;
        var base = (Number(field.is_basic) === 1)
            ? ((maps.formNames || {})[field.name] || field.name)
            : ('custom_field[' + field.id + ']');
        return (maps.multiValue || {})[widgetType] ? base + '[]' : base;
    }

    // Sanitized to be safe as a CSS id selector -- custom field names are
    // admin-authored free text and could contain spaces/punctuation that
    // would break init_compact_editor()'s '#id' selector.
    function fieldElementId(instance, field) {
        return instance.idPrefix + '-field-' + String(field.name).replace(/[^A-Za-z0-9_-]/g, '_');
    }

    // ------------------------------------------------------------------
    // Per-widget-type control builders. Each returns the CONTROL element
    // only (never the .sr-qfield wrapper or label -- populateFieldContent()
    // owns those, uniformly, for every widget type).
    // ------------------------------------------------------------------

    function buildTextInput(field, widgetType, profile) {
        return $('<input>').attr('type', 'text').addClass('form-control').attr('name', fieldFormName(field, widgetType, profile));
    }

    function buildTextarea(field, widgetType, profile) {
        return $('<textarea>').addClass('form-control').attr('rows', 3).attr('name', fieldFormName(field, widgetType, profile));
    }

    function buildRichTextField(instance, field, widgetType) {
        return $('<textarea>')
            .addClass('form-control')
            .attr('rows', 4)
            .attr('id', fieldElementId(instance, field))
            .attr('name', fieldFormName(field, widgetType, instance.profile));
    }

    function buildSelect(field, widgetType, profile) {
        var $select = $('<select>').addClass('form-select').attr('name', fieldFormName(field, widgetType, profile));
        // Matches create_dropdown()'s own default blank=true first option.
        $('<option>').val('').text('--').appendTo($select);
        (field.options || []).forEach(function (opt) {
            $('<option>').val(opt.value).text(opt.name).appendTo($select);
        });
        return $select;
    }

    function buildMultiselect(field, widgetType, profile) {
        var $select = $('<select>').addClass('form-select').attr('multiple', 'multiple').attr('name', fieldFormName(field, widgetType, profile));
        (field.options || []).forEach(function (opt) {
            $('<option>').val(opt.value).text(opt.name).appendTo($select);
        });
        return $select;
    }

    function buildSelectizeControl(field, widgetType, multiple, profile) {
        var $select = $('<select>').attr('name', fieldFormName(field, widgetType, profile));
        if (multiple) {
            $select.attr('multiple', 'multiple');
        }
        return $select;
    }

    function buildDateInput(field, widgetType, profile) {
        return $('<input>')
            .attr('type', 'text')
            .addClass('form-control datepicker')
            .attr('autocomplete', 'off')
            .attr('name', fieldFormName(field, widgetType, profile));
    }

    function buildFileInput(field, widgetType, profile) {
        return $('<input>').attr('type', 'file').attr('multiple', 'multiple').addClass('form-control').attr('name', fieldFormName(field, widgetType, profile));
    }

    // SupportingDocumentation (Details tab) / MitigationSupportingDocumentation
    // (Mitigation tab) -- the ONLY two 'file' fields, and the reason
    // populateFieldContent()'s own comment carves them out of its generic
    // "no file input in update mode" rule: instead of a plain <input
    // type=file> riding along in the big form's own PATCH (which cannot
    // carry it), update mode here talks to a dedicated multipart endpoint
    // (POST /risks/{id}/supporting-documentation or its /mitigations/
    // sibling, includes/api.php) that commits an add/remove IMMEDIATELY,
    // independent of the surrounding form's Save/Cancel -- the same
    // "independent of the big form" shape the Control Validation modal's
    // own dedicated endpoint already established this session, not a new
    // pattern. (Trade-off, deliberate: Cancel no longer un-does a file
    // change the way it un-does every OTHER field, because the legacy
    // monolithic form's single PATCH-everything-together save is exactly
    // what the redesign moved away from. If that trade-off turns out to
    // matter in practice, staging changes until the main Save click is a
    // follow-up, not a blocker for restoring basic upload/remove/download.)
    //
    // Only SupportingDocumentation has a 'create' mode at all -- a
    // mitigation cannot exist before its risk does, so
    // MitigationSupportingDocumentation is always 'update'. In 'create'
    // mode the file rides along in the SAME multipart POST that creates
    // the risk (addRisk(), includes/api.php) via the plain buildFileInput()
    // this function falls through to, gated on canSubmitRisk (submit_risks)
    // rather than left to render unconditionally -- the Submit Risk page
    // itself already enforces submit_risks before this canvas ever loads,
    // but the +Add Risk modal embedded in Review Risk (review-risk.js) is
    // reachable by anyone with the broader riskmanagement permission, which
    // does not imply submit_risks.
    function buildSupportingDocumentationWidget(instance, field, widgetType) {
        var isMitigation = (field.name === 'MitigationSupportingDocumentation');

        if (instance.submitMode !== 'update') {
            if (!instance.canSubmitRisk) {
                return $('<div>').addClass('sr-qfield-placeholder')
                    .append($('<span>', { 'class': 'sr-qfield-placeholder-text' }).text(_lang['SupportingDocumentationRequiresSubmitRisk']));
            }
            return buildFileInput(field, widgetType);
        }

        var canManage = isMitigation ? instance.canEditMitigation : instance.canModifyRisk;
        if (!canManage) {
            var hintKey = isMitigation ? 'MitigationSupportingDocumentationRequiresPlanMitigations' : 'SupportingDocumentationRequiresModifyRisks';
            return $('<div>').addClass('sr-qfield-placeholder')
                .append($('<span>', { 'class': 'sr-qfield-placeholder-text' }).text(_lang[hintKey]));
        }

        var endpoint = '/api/v2/risks/' + instance.updateRiskId + (isMitigation ? '/mitigations/supporting-documentation' : '/supporting-documentation');

        var $wrapper = $('<div>').addClass('sr-supporting-docs-field');
        var $list = $('<ul>').addClass('list-unstyled sr-supporting-docs-list');
        var $fileInput = $('<input>', { type: 'file', 'class': 'form-control' });
        $wrapper.append($list).append($fileInput);

        function renderList(files) {
            $list.empty();
            if (!files || !files.length) {
                // Same 'None' empty state the legacy read-mode passthrough
                // uses (supporting_documentation(), includes/functions.php)
                // -- without this an empty list rendered as literally
                // nothing, no different from the widget still loading or
                // having silently failed to fetch.
                $list.addClass('sr-supporting-docs-list--empty')
                    .append($('<li>', { 'class': 'sr-supporting-docs-empty', text: L('None') }));
                return;
            }
            $list.removeClass('sr-supporting-docs-list--empty');
            files.forEach(function (file) {
                var $li = $('<li>');
                // link-success -- the SAME Bootstrap utility class the legacy
                // supporting_documentation() read-mode passthrough already
                // uses for this exact link (includes/functions.php) -- and a
                // leading download icon, so the row reads as "click to
                // download" rather than plain text at a glance (confirmed by
                // the user: an unstyled <a> here was easy to mistake for a
                // label).
                // .append(string) is unsafe here -- jQuery parses a string
                // that "looks like HTML" instead of inserting it as literal
                // text, and file.name is an uploaded filename an admin (or
                // API caller) controls. A real text node, like .text() uses
                // internally, is never parsed.
                $('<a>', {
                    'class': 'link-success',
                    href: BASE_URL + '/management/download.php?id=' + encodeURIComponent(file.unique_name)
                })
                    .append($('<i>', { 'class': 'fa fa-download', 'aria-hidden': 'true' }))
                    .append(document.createTextNode(' ' + file.name))
                    .appendTo($li);
                $('<button>', {
                    type: 'button',
                    'class': 'sr-chip-remove',
                    'data-unique-name': file.unique_name,
                    'aria-label': L('Remove')
                }).html('&times;').appendTo($li);
                $list.append($li);
            });
        }

        function keptUniqueNames() {
            return $list.find('[data-unique-name]').map(function () {
                return $(this).attr('data-unique-name');
            }).get();
        }

        function save(file) {
            var fd = new FormData();
            keptUniqueNames().forEach(function (name) {
                fd.append('unique_names[]', name);
            });
            if (file) {
                fd.append('file', file);
            }
            $.ajax({
                url: BASE_URL + endpoint,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                cache: false
            }).done(function (res) {
                renderList(res && res.data ? res.data.files : []);
                $fileInput.val('');
            }).fail(function (xhr) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message, false);
                } else {
                    showAlertFromMessage(_lang['RequestFailed'], false);
                }
            });
        }

        // Removing a row commits immediately (see this function's own
        // docblock above) -- there is no separate "Save" for this widget.
        $list.on('click', '.sr-chip-remove', function () {
            $(this).closest('li').remove();
            save(null);
        });
        $fileInput.on('change', function () {
            var file = (this.files && this.files[0]) ? this.files[0] : null;
            if (file) {
                save(file);
            }
        });

        $.ajax({ url: BASE_URL + endpoint, type: 'GET', cache: false }).done(function (res) {
            renderList(res && res.data ? res.data.files : []);
        });

        return $wrapper;
    }

    // MitigationControls -- reuses the SAME shared faceted picker
    // (createFacetedPicker(), js/simplerisk/sr-faceted-picker.js) Document
    // Program's control_ids[] field uses, since the control roster is the
    // same hundreds+ scale (design-system.md §5's sizing rule -- roster
    // SIZE decides the widget, not field type). Replaces the old plain
    // bootstrap-multiselect AND its own read-only accordion table, which
    // used to be built and reactively re-fetched right here
    // (buildMitigationControlsAccordion()/initMitigationControlsField()) --
    // that table is now a single shared TOP-LEVEL widget
    // (RiskMitigationControls.renderWidget(), js/simplerisk/pages/
    // risk-mitigation-controls.js), mounted once by risk-view-mitigation.js
    // in both read and edit mode, not rebuilt per field here.
    //
    // The picker/chips half is built in js/simplerisk/pages/
    // risk-mitigation-controls.js (RiskMitigationControls.buildField()) --
    // this function's only job is handing it a correctly-named/id'd
    // `<select multiple>`, the same one buildMultiselect() would have
    // built, so fieldFormName()/fieldElementId() (this module's own
    // helpers, not exported) stay the single source of truth for both.
    // Selecting WHICH controls a mitigation attaches is governance-owned
    // (api_v2_governance_control_roster()'s docblock, api/v2/includes/
    // governance.php) -- a
    // riskmanagement-only viewer gets a plain note instead of the
    // interactive picker, the same show/hide-on-permission treatment
    // buildAcceptMitigationWidget() (risk-details-view.js) already uses.
    // No <select> is rendered at all for this viewer: mitigation_controls[]
    // is then simply absent from this field's own markup, so serialize()
    // never sends it and saveMitigation()'s partial-update filter leaves
    // the risk's existing control mappings untouched on save (see
    // update_mitigation()'s own array_key_exists('mitigation_controls', $post)
    // gate, includes/functions.php) -- a save from this viewer can't
    // accidentally clear controls it was never shown.
    function buildMitigationControlsWidget(instance, field, widgetType) {
        if (!instance.canSelectMitigationControls) {
            return $('<div>').addClass('sr-mitigation-controls-field')
                .append($('<div>').addClass('sr-qhint').text(_lang['MitigationControlsRequiresGovernance']));
        }

        var $select = $('<select>').addClass('form-select')
            .attr('multiple', 'multiple')
            .attr('id', fieldElementId(instance, field))
            .attr('name', fieldFormName(field, widgetType));

        var $field = RiskMitigationControls.buildField($select);

        // The Mitigation Controls TABLE lives in this SAME field, below the
        // picker -- design-system.md's "selector plus its own data,
        // together, full width" shape (RiskScoringMethod/RiskScoringHistory
        // on the Details tab; MitigationControls is forced full width the
        // same way, risk_details_field_forced_full_width()). Collapsed by
        // default; the GridStack grow/shrink wiring on shown.bs.collapse/
        // hidden.bs.collapse below is the exact pattern buildAdvanced()'s
        // CVSS "Advanced Metrics" accordion already uses (see that
        // function's own comment for the full reasoning) -- neither
        // direction of this accordion's height change is covered by the
        // canvas's own content-observer sweep on its own.
        var table = RiskMitigationControls.buildTableSection();
        table.$body.on('shown.bs.collapse', function () {
            scheduleAutoFit(instance);
        });
        table.$body.on('hidden.bs.collapse', function () {
            fitFieldAndCardToContent(instance, table.$body.closest('.grid-stack-item')[0]);
        });
        $field.append(table.$section);

        return $field;
    }

    // AffectedAssets -- the shared assets+asset-groups selectize
    // (setupAssetsAssetGroupsSelectize(), common.js, always loaded here via
    // the CUSTOM:common.js token every page rendering this engine already
    // carries) reused as-is. Unlike every other widget in this file, this
    // one is SELF-LOADING: its own `load` callback (common.js) fetches the
    // roster AND pre-selects the risk's current picks in one AJAX call, so
    // it needs no options attached to `field` and no applyPrefillValue()
    // case (there is deliberately none for 'assets-asset-groups' -- see that
    // function's switch). Returns the bare <select> directly, not a wrapper
    // -- required-field targeting (populateFieldContent()'s
    // isFieldRequired() switch) and carryRequiredTitleToSelectize() both
    // need the REAL control, the same trap MitigationControls/NextStep/
    // SetNextReviewDate already hit for their own wrapper elements.
    function buildAssetsAssetGroupsWidget(instance, field) {
        return $('<select>')
            .attr('id', fieldElementId(instance, field))
            .attr('name', 'assets_asset_groups[]')
            .addClass('assets-asset-groups-select')
            .attr('multiple', 'multiple')
            .attr('placeholder', _lang['AffectedAssetsWidgetPlaceholder']);
    }

    // risk_id: 0 for a not-yet-created risk (Submit Risk, submitMode
    // 'create') -- setupAssetsAssetGroupsWidgetForRisk()'s own documented
    // default for exactly this case. instance.updateRiskId is the real risk
    // id for the Details tab's Edit flow (submitMode 'update'), matching how
    // every other risk-scoped caller of this widget threads it through.
    //
    // setupAssetsAssetGroupsWidgetForRisk() (common.js) builds its OWN
    // .selectize({...}) call with no knowledge of this engine's GridStack
    // un-clipping system (holdClippingOpen()/releaseClipping()) -- unlike
    // initSelectizeSingleField()/initSelectizeTagsField()/
    // initSelectizeGroupedField() above, which each pass
    // onDropdownOpen/onDropdownClose directly into their OWN selectize
    // config. Without that, opening this dropdown grows the clipped
    // GridStack slot to fit the menu (pushing every field below it down),
    // and closing it never shrinks the slot back -- the fields below stay
    // pushed down. Rather than duplicate or modify the shared common.js
    // config (used by risk.js/governance.js too), bind the SAME two
    // handlers after the fact: selectize's onDropdownOpen/onDropdownClose
    // options are themselves just sugar for the 'dropdown_open'/
    // 'dropdown_close' events (confirmed in the vendored selectize.js
    // source), so this achieves the identical effect.
    function initAssetsAssetGroupsField(instance, $control) {
        if (typeof setupAssetsAssetGroupsWidgetForRisk !== 'function') {
            return;
        }
        var $selectized = setupAssetsAssetGroupsWidgetForRisk($control, instance.updateRiskId || 0);
        var selectizeApi = $selectized && $selectized[0] && $selectized[0].selectize;
        if (selectizeApi) {
            selectizeApi.on('dropdown_open', selectizePopupOpenHandler(instance));
            selectizeApi.on('dropdown_close', selectizePopupCloseHandler(instance));
        }
    }

    // RiskScoringMethod: one dropdown, 6 internal sub-blocks toggled by its
    // own change handler -- the exact "one widget, multiple internal parts"
    // shape NextStep/SetNextReviewDate already established, generalized to
    // 6 branches. Phase 4d-i built two real sub-widgets (Classic, Custom);
    // Phase 4d-ii/iii/iv added CVSS, DREAD, and OWASP; Phase 4d-v added
    // Contributing Risk, the last of the six -- every branch now renders
    // real inline content, so the shared "not yet available" notice
    // (ScoringNotYetAvailableInThisView) the card-level placeholder this
    // widget replaces used to show for the WHOLE card is no longer used
    // anywhere in this file.
    //
    // Holder class names (.classic-holder etc.) match the legacy markup's
    // own names on purpose, for continuity -- this engine shares no other
    // DOM with the legacy form, so it's cosmetic consistency only, not a
    // real coupling.
    var SCORING_METHOD_VALUES = {
        CLASSIC: '1',
        CVSS: '2',
        DREAD: '3',
        OWASP: '4',
        CUSTOM: '5',
        CONTRIBUTING_RISK: '6'
    };

    // Every sub-control across every scoring method gets the SAME
    // .sr-qfield (label-above-control, 5px gap) every other field on this
    // page uses -- without it, a holder's label(s) and control(s) are plain
    // block siblings with no gap CSS at all.
    //
    // `helpText`, when given, appends the same info-icon-plus-Bootstrap-
    // popover pattern extras/workflows/includes/display.php already uses
    // for its definition list (`data-bs-toggle="popover"`, hover+focus
    // trigger). Only CVSS's 14 metric selects (buildCvssScoreItem() below)
    // pass it -- every other scoringSubField() caller (Classic/Custom's
    // sub-fields) omits the argument and renders exactly as before.
    // Initialized here rather than via a page-level sweep because this
    // widget can be built more than once per page load (Reset Form/Save &
    // New rebuild the canvas, and an embedded "+ Add Risk" modal is a
    // second instance entirely) -- each icon wires its own popover the
    // moment it's created, with no dependency on when/whether a global init
    // pass runs afterward. bootstrap.Popover() only attaches event
    // listeners at construction time (it doesn't need the element attached
    // to the document yet), so this is safe to call before $control is
    // appended anywhere.
    function scoringSubField(labelText, $control, helpText) {
        var $label = $('<label>').addClass('sr-qlabel').text(labelText + ' ');
        if (helpText) {
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
                // customClass, not Bootstrap's default ~276px popover width --
                // these now describe every option's meaning, not just the
                // metric, and need the extra room to stay readable rather
                // than rendering as a tall, narrow column.
                new bootstrap.Popover($help[0], { customClass: 'sr-scoring-help-popover' });
            } catch (err) {
                // Guarded the same way extras/workflows/includes/display.php's
                // popover init is: one malformed popover can't throw and
                // abort the rest of this field's (or the whole canvas's)
                // build.
                console.error('Failed to initialize scoring help popover:', err);
            }
            $label.append($help);
        }
        return $('<div>').addClass('sr-qfield')
            .append($label)
            .append($control);
    }

    // The Field Roster this widget's CVSS holder/modal render -- kept as one
    // array here (not re-typed per function) since every function below
    // iterates the same 14 fields. Matches window.CvssV2Scoring.FIELD_NAMES
    // exactly (Task 1) -- not read from there directly, so this file has no
    // load-order dependency on cvss-v2-scoring.js merely to know the roster,
    // only to call its calculation functions.
    var CVSS_FIELDS = [
        { name: 'AccessVector', labelKey: 'AttackVector', blank: true },
        { name: 'AccessComplexity', labelKey: 'AttackComplexity', blank: true },
        { name: 'Authentication', labelKey: 'Authentication', blank: true },
        { name: 'ConfImpact', labelKey: 'ConfidentialityImpact', blank: true },
        { name: 'IntegImpact', labelKey: 'IntegrityImpact', blank: true },
        { name: 'AvailImpact', labelKey: 'AvailabilityImpact', blank: true },
        { name: 'Exploitability', labelKey: 'Exploitability', blank: false },
        { name: 'RemediationLevel', labelKey: 'RemediationLevel', blank: false },
        { name: 'ReportConfidence', labelKey: 'ReportConfidence', blank: false },
        { name: 'CollateralDamagePotential', labelKey: 'CollateralDamagePotential', blank: false },
        { name: 'TargetDistribution', labelKey: 'TargetDistribution', blank: false },
        { name: 'ConfidentialityRequirement', labelKey: 'ConfidentialityRequirement', blank: false },
        { name: 'IntegrityRequirement', labelKey: 'IntegrityRequirement', blank: false },
        { name: 'AvailabilityRequirement', labelKey: 'AvailabilityRequirement', blank: false }
    ];

    // The Field Roster this widget's DREAD holder renders -- 5 plain 0-10
    // numeric fields, no "blank" option (create_numeric_dropdown()'s own
    // $blank=false for all 5 in the legacy edit_dread_score() markup,
    // includes/display.php) and no per-field options payload (the range is
    // fixed and identical for every field, unlike CVSS's per-metric lookup-
    // table options).
    //
    // `name` is the real POST/wire identifier addRisk()/updateRisk() read
    // (includes/api.php: get_param("POST", 'DREADDamage') etc.) -- NOT the
    // bare metric name. 'DamagePotential' specifically maps to wire name
    // 'DREADDamage', not 'DREADDamagePotential'; the other four keep the
    // 'DREAD' prefix plus the bare name unchanged. `labelKey`/`helpKey`
    // stay the bare names ('DamagePotential'/'DamagePotentialHelp' etc.) --
    // those are $lang keys, an unrelated naming convention, not wire
    // identifiers. Matches window.DreadScoring.FIELD_NAMES exactly (Task 1).
    var DREAD_FIELDS = [
        { name: 'DREADDamage', labelKey: 'DamagePotential', helpKey: 'DamagePotentialHelp' },
        { name: 'DREADReproducibility', labelKey: 'Reproducibility', helpKey: 'ReproducibilityHelp' },
        { name: 'DREADExploitability', labelKey: 'Exploitability', helpKey: 'ExploitabilityHelp' },
        { name: 'DREADAffectedUsers', labelKey: 'AffectedUsers', helpKey: 'AffectedUsersHelp' },
        { name: 'DREADDiscoverability', labelKey: 'Discoverability', helpKey: 'DiscoverabilityHelp' }
    ];

    // One CVSS field, styled like every other sub-field on this page
    // (scoringSubField(): label-above-control, 5px gap). No hidden
    // companion -- the visible select IS the submitted field now that it
    // lives inside .cvss-holder, itself inside the canvas's own <form>
    // (see initScoringMethodField()'s updated docblock below for why the
    // old form= binding this used to need is gone).
    function buildCvssScoreItem(cvssField, optionsForField) {
        var $select = $('<select>').attr('id', cvssField.name).attr('name', cvssField.name).addClass('form-select');
        if (cvssField.blank) {
            $select.append($('<option>').val('').text('--'));
        }
        (optionsForField || []).forEach(function (opt) {
            $select.append($('<option>').val(opt.value).text(opt.name));
        });
        // 'Exploitability' is a genuine naming COLLISION with DREAD's own
        // field of the same labelKey -- both would otherwise derive the
        // same lang key ('ExploitabilityHelp'), and since lang.en.php can
        // only hold one value per key, DREAD's (added later in that file)
        // silently won for both. CVSSExploitabilityHelp is CVSS's own,
        // previously-dead copy of that text, now actually reachable.
        var helpKey = cvssField.labelKey === 'Exploitability' ? 'CVSSExploitabilityHelp' : cvssField.labelKey + 'Help';
        return scoringSubField(_lang[cvssField.labelKey], $select, _lang[helpKey]);
    }

    // One score-DISPLAY row (Base Score, Temporal Score, etc.) -- a plain
    // read-only <div>, never an input; CvssV2Scoring.calculateCVSS() writes
    // its textContent directly by id.
    //
    // `vectorId`, when given, adds a light CVSS v2 vector caption (e.g.
    // "AV:N/AC:L/Au:N") stacked directly under the row's OWN label, in the
    // same left-hand column -- not under the number on the right, which
    // read as an annotation on the wrong element and threw the row's own
    // left/right balance off (the label+caption column and the grey number
    // box beside it should read as roughly the same height, not a
    // two-line label competing with a one-line number). calculateCVSS()
    // writes the caption text, from CvssV2Scoring.computeVectors().
    // Distinct from `id` (the score's own element, e.g.
    // 'ExploitabilitySubscore') because the two never share a name:
    // calculateCVSS() writes the vector captions under a separate
    // '<Score>Vector' id scheme, so the two updates can never collide even
    // where a score's own element id doesn't match its label
    // (ExploitabilitySubscore's caption id is 'ExploitabilityScoreVector',
    // matching its labelKey instead).
    function buildCvssScoreDisplay(id, labelKey, vectorId) {
        var $label = $('<div>').addClass('sr-cvss-score-label-col')
            .append($('<label>').text(_lang[labelKey] + ':'));
        if (vectorId) {
            $label.append($('<div>').addClass('sr-cvss-vector').attr('id', vectorId));
        }
        return $('<div>').addClass('score-item mb-2 d-flex align-items-center')
            .append($label)
            .append($('<div>').addClass('score-value form-control text-end').attr('id', id).text('0'));
    }

    // ------------------------------------------------------------------
    // Live risk-level severity pill for the CVSS holder's right column.
    //
    // Reuses the shipped "severity -- solid" pill (design-system.md §7,
    // .sr-sev-pill, scss/modules/_tables.scss) and the same
    // sevPill()/paintSevPills() shape self-assessment.js and plan-projects.js
    // already use for a SAVED score -- this is the first LIVE, pre-save use
    // of that pattern, computed from the same /risk_levels thresholds a
    // saved risk's own pill is matched against, so the preview does not
    // diverge from what the risk actually gets once saved.
    // ------------------------------------------------------------------

    // Fetched once per page load (risk_levels rarely change and every CVSS
    // holder on the page would otherwise re-fetch identical data), not once
    // per canvas -- multiple RiskScoringMethod fields/canvases can exist on
    // one page (embedded "+ Add Risk" modals).
    var riskLevelsPromise = null;
    function fetchRiskLevels() {
        if (!riskLevelsPromise) {
            riskLevelsPromise = fetchJSON('/risk_levels').then(function (data) {
                return (data && data.risk_levels) || [];
            }).catch(function () {
                riskLevelsPromise = null; // allow a retry on the next call
                return [];
            });
        }
        return riskLevelsPromise;
    }

    // Same memoized-promise shape as fetchRiskLevels() above, for Classic's
    // own admin-configured formula (`risk_model`, and for model 6 the
    // custom_risk_model_values lookup grid -- see classic-scoring.js's own
    // docblock). Fetched once per page load and reused by every
    // .classic-holder on the page AND (Task 4) risk-details-view.js's
    // read-mode Classic card, exactly how fetchRiskLevels()'s own cached
    // promise already serves multiple consumers.
    var riskFormulaConfigPromise = null;
    function fetchRiskFormulaConfig() {
        if (!riskFormulaConfigPromise) {
            riskFormulaConfigPromise = fetchJSON('/riskformula/config').then(function (data) {
                return data || {};
            }).catch(function () {
                riskFormulaConfigPromise = null; // allow a retry on the next call
                return {};
            });
        }
        return riskFormulaConfigPromise;
    }

    // Same threshold match get_risk_level_name_from_levels() (includes/
    // functions.php) makes server-side: the highest-value level at or below
    // the score. `levels` need not be pre-sorted -- sort defensively, since
    // the API's own row order is not documented as sorted.
    function matchRiskLevel(score, levels) {
        var sorted = levels.slice().sort(function (a, b) { return Number(a.value) - Number(b.value); });
        var match = null;
        sorted.forEach(function (level) {
            if (Number(level.value) <= score) {
                match = level;
            }
        });
        return match;
    }

    // A hero tile (label / big score / level name), not the small inline
    // .sr-sev-pill self-assessment.js/plan-projects.js use in a table cell
    // -- this is the summary's own lead figure, not a row decoration, so it
    // gets the same "big colored number" treatment view_score_html()'s
    // .risk-square gives Inherent/Residual Risk at the top of the read view
    // (includes/display.php), reimplemented with this codebase's own
    // .sr-qform tokens (8px card radius, no inline hex) instead of copying
    // that older component's bare Bootstrap styling. The fill color is
    // applied via the style PROPERTY by paintCvssRiskLevelTile() below
    // (browser-validated, so an admin-configured color string can't break
    // out of an inline style), never interpolated into the markup. Nests
    // directly inside the CVSS Score card (buildCvssHolder() below) rather
    // than standing as its own separate card -- two bordered/headed cards
    // stacked with a gap between them ran noticeably taller than Base Score
    // Metrics opposite them, for no content reason; one card with the hero
    // figure up top and the 5-score breakdown as supporting detail beneath
    // it is both shorter and a better hierarchy (the number a reviewer
    // actually wants first, leading).
    // `idPrefix` defaults to 'Cvss' so every EXISTING call site (this
    // file's own buildCvssHolder(), and cve_lookup.js's two
    // window.CvssRiskLevelPill.update() calls, which pass no 3rd argument)
    // keeps producing the exact same ids as before -- #CvssRiskLevelTile
    // etc. -- with zero behavior change. A second holder (buildDreadHolder()
    // below) passes 'Dread' explicitly, producing #DreadRiskLevelTile/
    // #DreadRiskLevelScore/#DreadRiskLevelName: distinct ids, so both
    // holders' tiles can coexist in the DOM (as hidden siblings inside the
    // same .sr-scoring-method-field) without ever colliding, the same
    // id-collision discipline cvss-v2-scoring.js's holderEl() already
    // established for the 14 CVSS metric ids.
    function buildRiskLevelPill(idPrefix) {
        idPrefix = idPrefix || 'Cvss';
        return $('<div>').addClass('sr-cvss-risk-level-tile').attr('id', idPrefix + 'RiskLevelTile')
            .append($('<span>').addClass('sr-cvss-risk-level-label').text(_lang.RiskLevel))
            .append($('<span>').addClass('sr-cvss-risk-level-score').attr('id', idPrefix + 'RiskLevelScore').text('--'))
            .append($('<span>').addClass('sr-cvss-risk-level-name').attr('id', idPrefix + 'RiskLevelName'));
    }

    // Same luminance-based contrast pick as self-assessment.js's
    // paintSevPills(), applied to the tile instead of a page-wide
    // querySelectorAll sweep (a CVSS holder repaints its own tile on every
    // recalculation, not the whole page's).
    function paintCvssRiskLevelTile($tile, color) {
        if (!color) {
            return;
        }
        $tile.css('background-color', color);
        var probe = document.createElement('span');
        probe.style.color = color;
        document.body.appendChild(probe);
        var rgb = window.getComputedStyle(probe).color;
        document.body.removeChild(probe);
        var m = rgb.match(/\d+/g);
        if (m && m.length >= 3) {
            var lum = 0.299 * m[0] + 0.587 * m[1] + 0.114 * m[2];
            $tile.toggleClass('on-light', lum > 150);
        }
    }

    // Recomputes the tile from the holder's OWN current score (the same
    // cascading Base/Temporal/Environmental pick calculateCVSS() already
    // returns) against the cached risk_levels thresholds. Called after every
    // calculateCVSS() -- live edits and the initial prefill/build alike --
    // so the tile never shows a stale level for the fields currently on
    // screen.
    function updateRiskLevelPill($holder, score, idPrefix) {
        idPrefix = idPrefix || 'Cvss';
        var $tile = $holder.find('#' + idPrefix + 'RiskLevelTile');
        if (!$tile.length) {
            return;
        }
        var $score = $tile.find('#' + idPrefix + 'RiskLevelScore');
        var $name = $tile.find('#' + idPrefix + 'RiskLevelName');
        fetchRiskLevels().then(function (levels) {
            var level = matchRiskLevel(score, levels);
            $score.text(score);
            if (level) {
                $name.text(level.name);
                paintCvssRiskLevelTile($tile, level.color);
            } else {
                $name.text('');
                $tile.removeClass('on-light').css('background-color', '');
            }
        });
    }

    // Small bridge so cve_lookup.js (a separate file/module) can refresh the
    // pill after it seeds the 14 fields and calls
    // window.CvssV2Scoring.calculateCVSS() directly -- that call site has no
    // other way to reach this file's DOM-building helpers. cvss-v2-scoring.js
    // itself stays free of this dependency (Phase 4d-ii's own "pure DOM +
    // math, no dependency on risk-details-form.js or vice versa" rule) --
    // only cve_lookup.js, which already depends on this file for the canvas
    // that renders .cvss-holder in the first place, uses this.
    //
    // `match`/`paint` (Phase 4d-iii) extend the same bridge to
    // risk-details-view.js's read-mode CVSS card, which needs the SAME
    // risk_levels lookup and tile coloring but has no live .cvss-holder to
    // hand update() -- its tile is built once from a saved risk's already-
    // computed CurrentScore, never re-queried. `match` reuses fetchRiskLevels()'s
    // cached promise (one /risk_levels fetch serves every CVSS holder AND
    // every read-mode card on the page, not one each), `paint` reuses the
    // exact same luminance-based contrast pick so a read-mode tile and an
    // edit-mode tile showing the same level never disagree on legibility.
    window.CvssRiskLevelPill = {
        update: updateRiskLevelPill,
        match: function (score) {
            return fetchRiskLevels().then(function (levels) {
                return matchRiskLevel(score, levels);
            });
        },
        paint: paintCvssRiskLevelTile,
        // Phase 4d-iv: OWASP's own score computation needs the raw
        // risk_levels array itself (to average two SPECIFIC named tiers
        // together, not just match one against thresholds) -- both
        // buildOwaspHolder() (this file) and risk-details-view.js's
        // buildOwaspReadView() need this same cached data, so it's
        // exposed here rather than each making its own /risk_levels
        // fetch. Reuses fetchRiskLevels()'s own cached promise -- no new
        // network request, one fetch still serves every consumer.
        levels: fetchRiskLevels,
        // Task 3: Classic's own admin-configured formula config (risk_model
        // etc.), the same multi-consumer bridge shape as `levels` above --
        // buildClassicHolder() (this file) and Task 4's read-mode Classic
        // card both resolve this SAME cached promise.
        formulaConfig: fetchRiskFormulaConfig
    };

    // One DREAD field: a plain 0-10 <select>, no blank option, with a help
    // popover -- the same scoringSubField() helper CVSS's own fields use,
    // just without an options payload (the range is fixed).
    function buildDreadScoreItem(dreadField) {
        var $select = $('<select>').attr('id', dreadField.name).attr('name', dreadField.name).addClass('form-select');
        for (var value = 0; value <= 10; value++) {
            $select.append($('<option>').val(String(value)).text(String(value)));
        }
        return scoringSubField(_lang[dreadField.labelKey], $select, _lang[dreadField.helpKey]);
    }

    // Builds the DREAD holder's full inline content: a Score card (Risk
    // Level pill + the one average score) on the left, one combined
    // "DREAD Metrics" card on the right split into two internal stacked
    // columns -- the layout an approved interactive mockup confirmed (see
    // this phase's own spec). No accordion: DREAD's 5 fields have no
    // required-vs-optional split to justify one, unlike CVSS's Base/
    // Temporal/Environmental tiers. Called once per canvas from
    // buildScoringMethodWidget() below, the same place notYetAvailableHolder
    // ('dread-holder') used to sit.
    function buildDreadHolder(instance, field) {
        var fieldsByName = {};
        DREAD_FIELDS.forEach(function (dreadField) {
            fieldsByName[dreadField.name] = dreadField;
        });

        // 'DreadScoreFormula' caption -- same shape buildCvssScoreDisplay()'s
        // vectorId param uses, empty until dread-scoring.js's
        // calculateDread() writes it (build-time call below, then live on
        // every field change).
        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.DreadScore))
            .append(buildRiskLevelPill('Dread'))
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.DreadScore + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'DreadScoreFormula')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'DreadScore').text('0'))
            );

        // Column-major DOM order -- .sr-dread-metric-columns's 3-row,
        // auto-flow:column grid places the first 3 fields down column 1
        // (Damage/Reproducibility/Exploitability) then wraps into column 2
        // (AffectedUsers/Discoverability), landing each column-2 field on
        // the SAME row as its column-1 counterpart.
        var $metrics = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h6>').text(_lang.DreadMetrics))
            .append(
                $('<div>').addClass('sr-dread-metric-columns')
                    .append(['DREADDamage', 'DREADReproducibility', 'DREADExploitability', 'DREADAffectedUsers', 'DREADDiscoverability'].map(function (name) {
                        return buildDreadScoreItem(fieldsByName[name]);
                    }))
            );

        // Classed 'dread-holder' from birth (not only via the caller's own
        // later .addClass('dread-holder') chain in buildScoringMethodWidget()
        // -- that chain stays, now a harmless no-op re-add) so the build-time
        // compute below has an already-classed root to hand
        // calculateDread(): dread-scoring.js's holderEl() scopes every
        // lookup to '.dread-holder #id', and at build time this element is
        // still detached from the document, so it must already carry the
        // class itself and be passed explicitly as the query root -- a
        // document-wide query would find nothing (or, worse, some other
        // already-rendered .dread-holder elsewhere on the page) this early.
        // 'sr-dread-holder-row' weights the row 1:2 (Metrics card gets 2/3
        // width) -- .sr-cvss-metric-row's own 3-equal-column template was
        // built for CVSS's real 3-card rows; DREAD only ever places 2 cards
        // into it (see _questionnaire.scss's combined-selector override).
        var $holder = $('<div>').addClass('sr-cvss-metric-row dread-holder sr-dread-holder-row').append($summary).append($metrics);

        // Live recalculation on every field change, delegated on the holder
        // itself -- same wiring shape as buildCvssHolder()'s own listener.
        // $holder[0] passed as calculateDread()'s scoping root for
        // consistency with the build-time call below -- harmless here since
        // $holder is fully classed and normally already attached by the
        // time a user can trigger a change, but keeps both call sites in
        // this function independent of attachment timing.
        $holder.on('change', 'select', function () {
            if (window.DreadScoring) {
                var score = window.DreadScoring.calculateDread($holder[0]);
                updateRiskLevelPill($holder, score, 'Dread');
            }
        });

        // Build-time compute (Review Focus: a fresh holder's fields all
        // default to '0' -- no blank option -- and must show a real score
        // and pill from the first paint, not only after a user interacts).
        // $holder is still detached from the document at this point, so
        // calculateDread() is given $holder[0] itself as its scoping root
        // (see the class comment above) rather than relying on its default
        // document-wide lookup, which would find nothing this early.
        if (window.DreadScoring) {
            var initialScore = window.DreadScoring.calculateDread($holder[0]);
            updateRiskLevelPill($holder, initialScore, 'Dread');
        }

        return $holder;
    }

    // `name` is the real POST/wire identifier addRisk()/updateRisk() read
    // (includes/api.php: get_param("post", "OWASPSkillLevel") etc.) --
    // verified directly against that source (see this phase's own spec's
    // "Verified wire names" table), not update_owasp_score()'s own PHP
    // parameter names, which differ for 3 of these 16
    // (SkillLevel/EaseOfDiscovery/EaseOfExploit map to $OWASPSkill/
    // $OWASPDiscovery/$OWASPExploit there). `labelKey`/`helpKey` are
    // $lang keys, an unrelated naming convention -- always the bare
    // metric name. Matches window.OwaspScoring.FIELD_NAMES exactly.
    var OWASP_FIELDS = [
        { name: 'OWASPSkillLevel', labelKey: 'SkillLevel', helpKey: 'SkillLevelHelp' },
        { name: 'OWASPMotive', labelKey: 'Motive', helpKey: 'MotiveHelp' },
        { name: 'OWASPOpportunity', labelKey: 'Opportunity', helpKey: 'OpportunityHelp' },
        { name: 'OWASPSize', labelKey: 'Size', helpKey: 'SizeHelp' },
        { name: 'OWASPEaseOfDiscovery', labelKey: 'EaseOfDiscovery', helpKey: 'EaseOfDiscoveryHelp' },
        { name: 'OWASPEaseOfExploit', labelKey: 'EaseOfExploit', helpKey: 'EaseOfExploitHelp' },
        { name: 'OWASPAwareness', labelKey: 'Awareness', helpKey: 'AwarenessHelp' },
        { name: 'OWASPIntrusionDetection', labelKey: 'IntrusionDetection', helpKey: 'IntrusionDetectionHelp' },
        { name: 'OWASPLossOfConfidentiality', labelKey: 'LossOfConfidentiality', helpKey: 'LossOfConfidentialityHelp' },
        { name: 'OWASPLossOfIntegrity', labelKey: 'LossOfIntegrity', helpKey: 'LossOfIntegrityHelp' },
        { name: 'OWASPLossOfAvailability', labelKey: 'LossOfAvailability', helpKey: 'LossOfAvailabilityHelp' },
        { name: 'OWASPLossOfAccountability', labelKey: 'LossOfAccountability', helpKey: 'LossOfAccountabilityHelp' },
        { name: 'OWASPFinancialDamage', labelKey: 'FinancialDamage', helpKey: 'FinancialDamageHelp' },
        { name: 'OWASPReputationDamage', labelKey: 'ReputationDamage', helpKey: 'ReputationDamageHelp' },
        { name: 'OWASPNonCompliance', labelKey: 'NonCompliance', helpKey: 'NonComplianceHelp' },
        { name: 'OWASPPrivacyViolation', labelKey: 'PrivacyViolation', helpKey: 'PrivacyViolationHelp' }
    ];

    // One OWASP field: a plain 0-10 <select>, no blank option, with a
    // help popover -- same shape as buildDreadScoreItem().
    function buildOwaspScoreItem(owaspField) {
        var $select = $('<select>').attr('id', owaspField.name).attr('name', owaspField.name).addClass('form-select');
        for (var value = 0; value <= 10; value++) {
            $select.append($('<option>').val(String(value)).text(String(value)));
        }
        return scoringSubField(_lang[owaspField.labelKey], $select, _lang[owaspField.helpKey]);
    }

    // A named sub-group NESTED inside one of the Likelihood/Impact cards
    // -- unlike CVSS's own metricsSubGroup() (which makes each sub-group
    // its OWN separate .sr-cvss-metric-group card), OWASP's approved
    // mockup nests 2 sub-groups inside ONE outer card each. A bare <h6>
    // would trigger _questionnaire.scss's existing ".sr-cvss-metric-group
    // h5, h6" rule a SECOND time inside the same card (that rule assumes
    // exactly one heading per card -- the card's own title, bled to its
    // edges) -- 'sr-owasp-subgroup-head' is a plain, lighter heading
    // instead, distinct from the outer card's own <h5>. 'sr-qfield-mb'
    // (existing utility, margin-bottom: 16px) separates the two
    // sub-groups within the card.
    function owaspSubGroup(labelKey, descKey, names, fieldsByName) {
        return $('<div>').addClass('sr-qfield-mb')
            .append($('<div>').addClass('sr-owasp-subgroup-head').text(_lang[labelKey]))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang[descKey]))
            .append($('<div>').addClass('sr-qstack').append(names.map(function (name) {
                return buildOwaspScoreItem(fieldsByName[name]);
            })));
    }

    // Builds the OWASP holder's full inline content: a Score card (Risk
    // Level pill + the one severity-matrix score) | a Likelihood card
    // (Threat Agent Factors + Vulnerability Factors sub-groups) | an
    // Impact card (Technical Impact + Business Impact sub-groups) -- a
    // real 3-column row, the layout an approved interactive mockup
    // confirmed. No accordion: all 16 fields are required inputs to any
    // OWASP score. Called once per canvas from buildScoringMethodWidget()
    // below, the same place notYetAvailableHolder('owasp-holder') used to
    // sit.
    function buildOwaspHolder(instance, field) {
        var fieldsByName = {};
        OWASP_FIELDS.forEach(function (owaspField) {
            fieldsByName[owaspField.name] = owaspField;
        });

        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.OwaspScore))
            .append(buildRiskLevelPill('Owasp'))
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col').append($('<label>').text(_lang.OwaspScore + ':')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'OwaspScore').text('0'))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col').append($('<label>').text(_lang.Likelihood + ':')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'OwaspLikelihood').text('0'))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.ThreatAgentFactors + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'OwaspThreatAgentFactorsFormula')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'OwaspThreatAgentFactors').text('0'))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.VulnerabilityFactors + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'OwaspVulnerabilityFactorsFormula')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'OwaspVulnerabilityFactors').text('0'))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col').append($('<label>').text(_lang.Impact + ':')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'OwaspImpact').text('0'))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.TechnicalImpact + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'OwaspTechnicalImpactFormula')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'OwaspTechnicalImpact').text('0'))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.BusinessImpact + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'OwaspBusinessImpactFormula')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'OwaspBusinessImpact').text('0'))
            )
            // Read-mode parity: buildOwaspReadView() (risk-details-view.js)
            // already carries this link -- it was missing here on the edit
            // side. .sr-owasp-methodology-note's own CSS (_questionnaire.scss)
            // pushes it into the Score column's own open space below the
            // summary numbers (that column is shorter than the Likelihood/
            // Impact columns beside it), same as the read view.
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

        var $likelihoodCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.Likelihood))
            .append(owaspSubGroup('ThreatAgentFactors', 'ThreatAgentFactorsDescription', ['OWASPSkillLevel', 'OWASPMotive', 'OWASPOpportunity', 'OWASPSize'], fieldsByName))
            .append(owaspSubGroup('VulnerabilityFactors', 'VulnerabilityFactorsDescription', ['OWASPEaseOfDiscovery', 'OWASPEaseOfExploit', 'OWASPAwareness', 'OWASPIntrusionDetection'], fieldsByName));

        var $impactCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.Impact))
            .append(owaspSubGroup('TechnicalImpact', 'TechnicalImpactDescription', ['OWASPLossOfConfidentiality', 'OWASPLossOfIntegrity', 'OWASPLossOfAvailability', 'OWASPLossOfAccountability'], fieldsByName))
            .append(owaspSubGroup('BusinessImpact', 'BusinessImpactDescription', ['OWASPFinancialDamage', 'OWASPReputationDamage', 'OWASPNonCompliance', 'OWASPPrivacyViolation'], fieldsByName));

        // Classed 'owasp-holder' from birth -- same reasoning as
        // buildDreadHolder()'s own comment: the build-time compute below
        // needs an already-classed root to hand calculateOwasp() while
        // still detached from the document.
        var $holder = $('<div>').addClass('sr-cvss-metric-row owasp-holder').append($summary).append($likelihoodCard).append($impactCard);

        // Live recalculation on every field change. Unlike DREAD's fully
        // synchronous calculateDread(), OWASP's score depends on a live
        // risk_levels average for 3 of its 5 severity tiers -- window.
        // CvssRiskLevelPill.levels() resolves the SAME cached fetch every
        // other consumer of risk_levels on this page shares, so this
        // costs no extra request beyond the first.
        $holder.on('change', 'select', function () {
            if (window.OwaspScoring && window.CvssRiskLevelPill) {
                window.CvssRiskLevelPill.levels().then(function (levels) {
                    var score = window.OwaspScoring.calculateOwasp($holder[0], levels);
                    updateRiskLevelPill($holder, score, 'Owasp');
                });
            }
        });

        // Build-time compute (Review Focus: a fresh holder's fields all
        // default to '0' and must show a real score and pill without
        // requiring a user interaction first -- same reasoning as
        // buildDreadHolder()'s own build-time call, just asynchronous
        // here since even the all-zero case needs categoryFor() to run
        // before the score is known).
        if (window.OwaspScoring && window.CvssRiskLevelPill) {
            window.CvssRiskLevelPill.levels().then(function (levels) {
                var initialScore = window.OwaspScoring.calculateOwasp($holder[0], levels);
                updateRiskLevelPill($holder, initialScore, 'Owasp');
            });
        }

        return $holder;
    }

    // Builds the Classic holder's full inline content: a Score card (Risk
    // Level pill + the one server-computed score) | a Likelihood card | an
    // Impact card -- the SAME 3-column row shape buildOwaspHolder() above
    // uses, minus any sub-group breakdown (Classic has only the two raw
    // Likelihood/Impact inputs, nothing to decompose). Replaces the plain
    // 2-select .sr-qgrid pair this widget used to render for Classic --
    // Classic was the one scoring method left with no live score/pill at
    // all (see this phase's own spec). Unlike CVSS/DREAD/OWASP's fixed
    // client-computable formulas, Classic's score is itself
    // admin-configured (the `risk_model` setting, 1-6, plus a model-6
    // lookup grid) -- fetchRiskFormulaConfig()/window.CvssRiskLevelPill.
    // formulaConfig() above fetches that configuration once (cached, the
    // same memoized-promise shape fetchRiskLevels() already uses) and
    // classic-scoring.js's calculateClassic() replicates calculate_risk()'s
    // own PHP branching against it. Called once per canvas from
    // buildScoringMethodWidget() below, the same place the old inline
    // .sr-qgrid pair used to sit.
    function buildClassicHolder(instance, field) {
        // 'ClassicScoreFormula' caption -- same shape DreadScoreFormula/
        // buildCvssScoreDisplay()'s vectorId use, written by classic-
        // scoring.js's calculateClassic() (reusing the legacy
        // RISKClassicExp1-5 lang keys, model-keyed; model 6 gets no
        // formula -- see buildClassicReadView()'s identical comment,
        // risk-details-view.js).
        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.ClassicScore))
            .append(buildRiskLevelPill('Classic'))
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.ClassicScore + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'ClassicScoreFormula')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'ClassicScore').text('0'))
            );

        // Same options payload/wire name (`name="likelihood"`) the
        // pre-redesign .classic-holder always built -- only the `id`
        // attribute and the surrounding card markup are new here. An id is
        // required (not just the name) for classic-scoring.js's holderEl()
        // to find this select -- the same `.classic-holder #id` scoping
        // every other formula file's own holderEl() already uses.
        // 'likelihood' is a unique id on this canvas (no other field uses
        // it), so there's no collision risk in adding it.
        var $likelihoodSelect = $('<select>').addClass('form-select').attr('id', 'likelihood').attr('name', 'likelihood')
            .append($('<option>').val('').text('--'))
            .append((field.likelihood_options || []).map(function (opt) {
                return $('<option>').val(opt.value).text(opt.name);
            }));
        var $likelihoodCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.Likelihood))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.ClassicLikelihoodDescription))
            .append(scoringSubField(_lang['CurrentLikelihood'], $likelihoodSelect));

        // Same shape as the Likelihood select above, `id="impact"`.
        var $impactSelect = $('<select>').addClass('form-select').attr('id', 'impact').attr('name', 'impact')
            .append($('<option>').val('').text('--'))
            .append((field.impact_options || []).map(function (opt) {
                return $('<option>').val(opt.value).text(opt.name);
            }));
        var $impactCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.Impact))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.ClassicImpactDescription))
            .append(scoringSubField(_lang['CurrentImpact'], $impactSelect));

        // Classed 'classic-holder' from birth -- same reasoning as
        // buildDreadHolder()/buildOwaspHolder()'s own comments: the
        // build-time compute below needs an already-classed root to hand
        // calculateClassic() while this element is still detached from the
        // document (a document-wide query would find nothing, or worse,
        // some other already-rendered .classic-holder elsewhere on the
        // page, this early).
        var $holder = $('<div>').addClass('sr-cvss-metric-row classic-holder').append($summary).append($likelihoodCard).append($impactCard);

        // Live recalculation on every field change. Unlike DREAD's fully
        // synchronous calculateDread(), Classic's score formula is itself
        // admin-configured -- window.CvssRiskLevelPill.formulaConfig()
        // resolves the SAME cached /riskformula/config fetch every other
        // consumer on this page shares (Task 4's read-mode card), the same
        // "one fetch, multiple consumers" shape buildOwaspHolder() above
        // already established for risk_levels.
        $holder.on('change', 'select', function () {
            if (window.ClassicScoring && window.CvssRiskLevelPill) {
                window.CvssRiskLevelPill.formulaConfig().then(function (config) {
                    var score = window.ClassicScoring.calculateClassic($holder[0], config);
                    updateRiskLevelPill($holder, score, 'Classic');
                });
            }
        });

        // Build-time compute (Review Focus: a fresh holder's two selects
        // both default to the blank '--' option -- must show a real score
        // and pill from the first paint, not only after a user interacts,
        // same as every other holder's own build-time call).
        if (window.ClassicScoring && window.CvssRiskLevelPill) {
            window.CvssRiskLevelPill.formulaConfig().then(function (config) {
                var initialScore = window.ClassicScoring.calculateClassic($holder[0], config);
                updateRiskLevelPill($holder, initialScore, 'Classic');
            });
        }

        return $holder;
    }

    // One Contributing Risk factor row: Subject + Weight combined into one
    // label (Weight shown as a rounded percentage, matching the admin
    // config screen's own 0-1 fraction), Impact as an editable <select> --
    // no blank option, same "no blank state" shape DREAD's/OWASP's own 0-10
    // selects use. `id` stays bracket-free ('ContributingImpacts_' +
    // factor.id) so contributing-risk-scoring.js's holderEl() can look it
    // up with a plain CSS id selector -- the wire-level bracketed `name`
    // ("ContributingImpacts[5]") is a SEPARATE attribute, read via jQuery's
    // attribute-value selector in applyPrefillValue()'s restore case below
    // and in the test suite, never via id.
    function buildContributingRiskFactorItem(factor) {
        var $select = $('<select>')
            .attr('id', 'ContributingImpacts_' + factor.id)
            .attr('name', 'ContributingImpacts[' + factor.id + ']')
            .addClass('form-select');
        (factor.impact_options || []).forEach(function (opt) {
            $select.append($('<option>').val(opt.value).text(opt.name));
        });
        var weightPct = Math.round(Number(factor.weight) * 100);
        var labelText = factor.subject + ' (' + _lang.Weight + ': ' + weightPct + '%)';
        // No caption here -- the per-factor subscore + formula now live in
        // the Score card instead (buildContributingRiskHolder()'s own
        // per-factor summary row), the same "computed value lives in the
        // Score card, the editable control lives in its own metric card"
        // split OWASP's buildOwaspScoreItem() already has.
        return scoringSubField(labelText, $select);
    }

    // Builds the Contributing Risk holder's full inline content: a Score
    // card (Risk Level pill + the one weighted-sum score) | a Likelihood
    // card (the single shared ContributingLikelihood dropdown, plus a short
    // descriptive sentence) | a Contributing Risk card (one row per
    // CURRENTLY-CONFIGURED factor, plus its own short descriptive
    // sentence) -- the SAME 3-column row shape buildClassicHolder()/
    // buildOwaspHolder() above use. Unlike every fixed-roster holder above,
    // the factor list here is genuinely dynamic -- field.contributing_risks
    // (api_attach_ui_risk_field_options(), api/v2/includes/api.php) is
    // however many rows currently exist in the contributing_risks table,
    // and this function renders exactly that many, including zero (Review
    // Focus: a fresh install with no factors configured yet still renders
    // the card, just with no rows in it). Called once per canvas from
    // buildScoringMethodWidget() below, the same place
    // notYetAvailableHolder('contributing-risk-holder') used to sit.
    function buildContributingRiskHolder(instance, field) {
        var contributingRisks = field.contributing_risks || [];
        var likelihoodOptions = field.contributing_likelihood_options || [];

        // Score card lists the total, then Contributing Likelihood (its own
        // subscore row) and Contributing Risk (a SECOND subscore row -- the
        // sum of every factor's own term), with each factor nested ONE level
        // further under Contributing Risk -- the same "computed breakdown
        // lives in the Score card" shape buildOwaspHolder() above uses for
        // its own Likelihood/Impact + 4 named subgroups, just with ONE
        // nested group instead of two (Contributing Risk's score has no
        // second top-level term to group alongside it the way OWASP's
        // Impact does). 'sr-owasp-subscore-item--nested' (on top of the
        // shared 'sr-owasp-subscore-item' indent) doubles that indent for
        // the factor rows specifically, so they read as children of
        // Contributing Risk rather than siblings of it. Values + formulas
        // are written here by contributing-risk-scoring.js's
        // calculateContributingRisk() (live, on every field change); the
        // editable controls stay in their own metric cards below, unchanged.
        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.ContributingRiskScore))
            .append(buildRiskLevelPill('Contributing'))
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.ContributingRiskScore + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'ContributingRiskScoreFormula')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'ContributingScore').text('0'))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.ContributingLikelihood + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'ContributingLikelihoodSubscoreFormula')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'ContributingLikelihoodSubscore').text('0'))
            )
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(_lang.ContributingRisk + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'ContributingRiskSubtotalFormula')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'ContributingRiskSubtotal').text('0'))
            )
            .append(contributingRisks.map(function (factor) {
                return $('<div>').addClass('score-item mb-2 d-flex align-items-center sr-owasp-subscore-item sr-owasp-subscore-item--nested')
                    .append($('<div>').addClass('sr-cvss-score-label-col')
                        .append($('<label>').text(factor.subject + ':'))
                        .append($('<div>').addClass('sr-cvss-vector').attr('id', 'ContributingFactorSubscoreFormula_' + factor.id)))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'ContributingFactorSubscore_' + factor.id).text('0'));
            }));

        // No blank option -- the shared likelihood table always has at
        // least one row (seeded at install time from the Classic
        // likelihood table, includes/upgrade.php), so a real value is
        // always selectable from first paint.
        var $likelihoodSelect = $('<select>').addClass('form-select').attr('id', 'ContributingLikelihood').attr('name', 'ContributingLikelihood')
            .append(likelihoodOptions.map(function (opt) {
                return $('<option>').val(opt.value).text(opt.name);
            }));
        // scoringSubField() gives this select a real "Likelihood" label --
        // it had none before (a plain unlabeled .sr-qfield), unlike every
        // other scoring control on this page. The formula caption that used
        // to sit here moved to the Score card's new subscore row above.
        var $likelihoodCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.ContributingLikelihood))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.ContributingLikelihoodDescription))
            .append(scoringSubField(_lang.Likelihood, $likelihoodSelect));

        var $contributingCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.ContributingRisk))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.ContributingRiskDescription))
            .append($('<div>').addClass('sr-qstack').append(contributingRisks.map(function (factor) {
                return buildContributingRiskFactorItem(factor);
            })));

        // Classed 'contributing-risk-holder' from birth -- same reasoning
        // as every other holder's own comment above: the build-time
        // compute below needs an already-classed root while still detached
        // from the document.
        var $holder = $('<div>').addClass('sr-cvss-metric-row contributing-risk-holder').append($summary).append($likelihoodCard).append($contributingCard);

        // Live recalculation on every field change, delegated on the
        // holder itself -- same wiring shape as buildClassicHolder()'s own
        // listener. Fully synchronous (no risk_levels/formulaConfig
        // dependency, unlike OWASP/Classic) -- the config IS the field
        // object already in scope.
        $holder.on('change', 'select', function () {
            if (window.ContributingRiskScoring) {
                var score = window.ContributingRiskScoring.calculateContributingRisk($holder[0], field);
                updateRiskLevelPill($holder, score, 'Contributing');
            }
        });

        // Build-time compute (Review Focus: a fresh holder must show a
        // real score and pill from the first paint, same as every other
        // holder's own build-time call).
        if (window.ContributingRiskScoring) {
            var initialScore = window.ContributingRiskScoring.calculateContributingRisk($holder[0], field);
            updateRiskLevelPill($holder, initialScore, 'Contributing');
        }

        return $holder;
    }

    // Builds the CVSS holder's full inline content: an always-visible
    // 5-score summary, then Base Score Metrics (required by CVSS v2 --
    // Exploitability/Impact side by side) with Temporal/Environmental/
    // Impact-Modifiers (optional CVSS v2 refinements) collapsed below it in
    // a .sr-qaccordion. This field's content is free to arrange its OWN 14
    // fields however reads best -- unlike a card's top-level fields, it is
    // not itself individually positioned on the outer GridStack (only the
    // whole RiskScoringMethod field is, via renderFieldItem()'s
    // forceFullWidth), so nothing here needs to fit GridStack's column
    // math. Called once per canvas from buildScoringMethodWidget() below,
    // exactly where the old ensureCvssModal() call used to sit.
    function buildCvssHolder(instance, field) {
        var fieldsByName = {};
        CVSS_FIELDS.forEach(function (cvssField) {
            fieldsByName[cvssField.name] = cvssField;
        });

        // A vertical stack of fields (no inner 2-up grid) -- used for a
        // metrics sub-group that is itself already one column of a 2-up
        // .sr-qgrid (Base Score Metrics' Exploitability | Impact split), so
        // a further inner grid would cram 2 columns into what is already
        // half the row.
        function fieldStack(fieldNames) {
            return $('<div>').addClass('sr-qstack').append(fieldNames.map(function (fieldName) {
                return buildCvssScoreItem(fieldsByName[fieldName], field[fieldName + '_options']);
            }));
        }

        // Every metric group gets the same soft gray panel the CVSS Score
        // summary uses (.sr-cvss-metric-group, scss/modules/
        // _questionnaire.scss) -- so each reads as its own distinct group
        // instead of bare label:control rows floating on the card body.
        //
        // `descKey`, when given, renders one short intro sentence between
        // the header and the fields -- every group below has one. Not the
        // same job as the per-field help popovers (buildCvssScoreItem()):
        // this orients a user to what the GROUP as a whole is asking, the
        // popovers explain each INDIVIDUAL metric's options once they're
        // already looking at one.
        function metricsSubGroup(labelKey, fieldNames, descKey) {
            var $group = $('<div>').addClass('sr-cvss-metric-group')
                .append($('<h6>').text(_lang[labelKey]));
            if (descKey) {
                $group.append($('<p>').addClass('sr-cvss-metric-group-desc').text(_lang[descKey]));
            }
            return $group.append(fieldStack(fieldNames));
        }

        // Exploitability | Impact | CVSS Score, three equal-height cards in
        // one row (.sr-cvss-top-row -- CSS Grid's default stretch makes all
        // three match the tallest one's height with no manual sizing). No
        // separate "Base Score Metrics" umbrella heading above the first two
        // (dropped -- it threw off their otherwise-even height): each sub-
        // group's own header carries the "Base Score" context instead
        // ('BaseScoreExploitabilityMetrics'/'BaseScoreImpactMetrics', distinct
        // from the plain 'ExploitabilityMetrics'/'ImpactMetrics' keys the
        // legacy cvss_modal_content.php modal still uses as-is).
        var $exploitabilityMetrics = metricsSubGroup('BaseScoreExploitabilityMetrics',
            ['AccessVector', 'AccessComplexity', 'Authentication'], 'BaseScoreExploitabilityMetricsDescription');
        var $impactMetrics = metricsSubGroup('BaseScoreImpactMetrics',
            ['ConfImpact', 'IntegImpact', 'AvailImpact'], 'BaseScoreImpactMetricsDescription');

        // Same treatment for the three OPTIONAL groups once the Advanced
        // Metrics accordion (below) is expanded -- side by side instead of
        // stacked, now that the accordion itself is full width rather than
        // confined to a half-width column.
        var $temporalMetrics = metricsSubGroup('TemporalScoreMetrics',
            ['Exploitability', 'RemediationLevel', 'ReportConfidence'], 'TemporalScoreMetricsDescription');
        var $environmentalMetrics = metricsSubGroup('EnvironmentalScoreMetrics',
            ['CollateralDamagePotential', 'TargetDistribution'], 'EnvironmentalScoreMetricsDescription');
        var $impactModifiers = metricsSubGroup('ImpactSubscoreModifiers',
            ['ConfidentialityRequirement', 'IntegrityRequirement', 'AvailabilityRequirement'], 'ImpactSubscoreModifiersDescription');

        // Temporal/Environmental/Impact-Modifiers are genuine CVSS v2
        // OPTIONAL refinements on top of the required Base Score (CVSS v2's
        // own spec, mirrored by score_type()'s cascade in cvss-v2-
        // scoring.js/includes/cvss.php: Base alone is a complete, valid
        // score) -- design-system.md's "required first, optional collapsed"
        // layout principle, applied here. A lighter version of the
        // .sr-qaccordion shell (extras/assessments/index.php's own
        // instances) -- no icon tile/subtitle, since this collapses inside
        // an already-card-scoped field rather than standing as its own
        // top-level section. instance.idPrefix keeps the collapse target id
        // unique when more than one canvas/holder exists on a page (e.g. an
        // embedded "+ Add Risk" modal alongside the main page).
        var advancedBodyId = instance.idPrefix + '-cvss-advanced';
        // sr-qcard-head is what actually gives this header its flex layout
        // and centered vertical alignment (scss's .sr-qaccordion .sr-qacc-
        // head.accordion-button rule assumes it, matching the shipped
        // instances in extras/assessments/index.php, which all carry both
        // classes) -- omitting it left the header's icon-less content
        // unaligned. It's also what the chevron rule keys off of.
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

        // Neither direction of this accordion's height change is covered
        // by the existing content-observer sweep on its own. That sweep
        // (observeFieldContent()'s ResizeObserver) watches
        // .grid-stack-item-content -- a GridStack-managed, fixed-size box --
        // not the accordion body growing/shrinking INSIDE it, so a
        // collapse toggle here never fires it either way: opening left the
        // newly-revealed Temporal/Environmental/Impact-Modifiers content
        // clipped (confirmed live -- the field's h stayed at its collapsed
        // value after expanding), and collapsing never gave back the
        // grow-only sweep would refuse to reclaim anyway. Both are handled
        // explicitly here: 'shown.bs.collapse' asks for the SAME sweep
        // every other dynamic change on this canvas already uses
        // (scheduleAutoFit(), grow-only, safe to call anytime); 'hidden.bs.
        // collapse' uses shrinkGridItemToFitContent() (below), the
        // dedicated, narrowly-scoped counterpart for the one case that sweep
        // will never undo on its own. Bound here rather than at canvas
        // level because only this accordion's collapse needs it; $holder/
        // instance are captured by closure and resolved lazily -- both
        // handlers only run long after both are attached to the live
        // canvas.
        $advancedBody.on('shown.bs.collapse', function () {
            scheduleAutoFit(instance);
        });

        $advancedBody.on('hidden.bs.collapse', function () {
            fitFieldAndCardToContent(instance, $holder.closest('.grid-stack-item')[0]);
        });

        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.CVSSScore))
            .append(buildRiskLevelPill('Cvss'))
            .append(buildCvssScoreDisplay('BaseScore', 'BaseScore', 'BaseScoreVector'))
            .append(buildCvssScoreDisplay('ExploitabilitySubscore', 'ExploitabilityScore', 'ExploitabilityScoreVector'))
            .append(buildCvssScoreDisplay('ImpactSubscore', 'ImpactScore', 'ImpactScoreVector'))
            .append(buildCvssScoreDisplay('TemporalScore', 'TemporalScore', 'TemporalScoreVector'))
            .append(buildCvssScoreDisplay('EnvironmentalScore', 'EnvironmentalScore', 'EnvironmentalScoreVector'))
            // Legacy score.php's CVSS table (includes/display.php) links out
            // to the CVSS v2 spec; this card had no equivalent. Reuses
            // .sr-owasp-methodology-note's CSS as-is (_questionnaire.scss) --
            // despite the name, it's a plain "push to the bottom of a flex
            // score-summary column" rule with nothing OWASP-specific in it,
            // and $summary here is the same .sr-cvss-holder-summary shape
            // buildOwaspHolder()'s own note already targets.
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

        // CVSS Score | Exploitability | Impact: three equal-height cards in
        // one row (.sr-cvss-metric-row, shared with the Advanced Metrics
        // accordion's own three-across body below -- both are "several
        // .sr-cvss-metric-group-shaped cards side by side" and CSS Grid's
        // default stretch is what actually keeps a ROW's cards the same
        // height, not per-card sizing). CVSS Score leads -- the result a
        // reviewer wants first, leftmost -- with the two input sub-groups
        // after it.
        var $topRow = $('<div>').addClass('sr-cvss-metric-row sr-qfield-mb')
            .append($summary)
            .append($exploitabilityMetrics)
            .append($impactMetrics);

        // Advanced Metrics now spans the widget's full width (no longer
        // confined to a half-width left column), matching how its own body
        // lays its three groups out 3-across once expanded (see
        // $advancedBody above) rather than the old single narrow column.
        var $holder = $('<div>')
            .append($topRow)
            .append($advanced);

        // Live recalculation on every field change, delegated on the
        // holder itself so the binding survives being appended into the
        // page later. This is the ONLY place this widget recalculates from
        // now on -- there is no modal 'shown'/'show' event to also hook.
        // cvss-v2-scoring.js's holderEl() reads these same ids scoped to
        // the nearest .cvss-holder ancestor; this listener and that helper
        // must agree on scope. calculateCVSS() now returns the current
        // (cascading) score, which the risk-level pill needs too -- one
        // recalculation drives both the score displays and the pill so
        // they can never show two different risk pictures.
        $holder.on('change', 'select', function () {
            if (window.CvssV2Scoring) {
                var score = window.CvssV2Scoring.calculateCVSS();
                updateRiskLevelPill($holder, score, 'Cvss');
            }
        });

        return $holder;
    }

    // Custom's score IS the typed value -- no admin-configured formula, no
    // separate <method>-scoring.js calculation module the way Classic/CVSS/
    // DREAD/OWASP each have one. The one wrinkle is the fallback: Task 0
    // fixed update_risk_scoring()'s own Custom branch (includes/functions.php)
    // to fall back to get_setting('default_risk_score') -- not a hardcoded
    // 0 -- whenever the posted value is blank or outside 0-10, and this
    // client-side mirror must agree exactly or a fresh/edited-blank holder
    // would preview one score and save another. `config` is the SAME
    // /riskformula/config payload window.CvssRiskLevelPill.formulaConfig()
    // already caches for Classic's own fallback -- Custom reads its
    // `default_risk_score` field, nothing else from it.
    function customScoreFromInput(rawValue, config) {
        var n = Number(rawValue);
        if (rawValue === '' || !isFinite(n) || n < 0 || n > 10) {
            return Number(config.default_risk_score) || 0; // matches update_risk_scoring()'s own (Task 0-fixed) Custom branch, which now reads get_setting('default_risk_score') -- same mechanism Classic's own fallback already uses
        }
        return n;
    }

    // Builds the Custom holder's full inline content: a Score card (Risk
    // Level pill + the live-echoed score) | a wide Custom Value card, the
    // SAME .sr-dread-holder-row 1:2 ratio DREAD's own 2-card row already
    // uses (confirmed with the user) rather than a new ratio class for
    // identical behavior -- Custom, like DREAD, only ever places 2 cards
    // into .sr-cvss-metric-row's 3-equal-column template. Unlike every
    // other holder's Card-level heading + a separately-labeled sub-field
    // (buildClassicHolder()'s Likelihood card: "Likelihood" heading above
    // "Current Likelihood" label -- two genuinely different strings), the
    // Custom Value card holds exactly one field describing the same concept
    // its own heading names, so the numeric input is NOT re-wrapped in a
    // second scoringSubField() label that would just repeat "Custom Value"
    // a second time -- the card heading + description caption are the only
    // label it gets, same shape OWASP's/Classic's own description-caption
    // cards use minus the redundant sub-label. Called once per canvas from
    // buildScoringMethodWidget() below, the same place the old bare
    // <input type="number" name="Custom"> used to sit alone.
    function buildCustomHolder(instance, field) {
        var $summary = $('<div>').addClass('sr-cvss-holder-summary')
            .append($('<h5>').text(_lang.CustomScore))
            .append(buildRiskLevelPill('Custom'))
            .append(
                $('<div>').addClass('score-item mb-2 d-flex align-items-center')
                    .append($('<div>').addClass('sr-cvss-score-label-col').append($('<label>').text(_lang.CustomScore + ':')))
                    .append($('<div>').addClass('score-value form-control text-end').attr('id', 'CustomScore').text('0'))
            );

        // Same wire name/attributes (`name="Custom"`, 0-10 step 0.1) the
        // pre-redesign bare input always had -- only `id` (required for
        // this function's own $holder.find('#Custom') lookups below) and
        // the surrounding card markup are new.
        var $valueCard = $('<div>').addClass('sr-cvss-metric-group')
            .append($('<h5>').text(_lang.CustomValue))
            .append($('<div>').addClass('sr-owasp-subgroup-desc').text(_lang.CustomValueDescription))
            .append(
                $('<input>').attr('type', 'number').attr('id', 'Custom').attr('name', 'Custom')
                    .attr('min', '0').attr('max', '10').attr('step', '0.1')
                    .addClass('form-control')
            );

        // Classed 'custom-holder' from birth -- same reasoning as every
        // other holder's own comment above: the build-time compute below
        // needs an already-classed root while still detached from the
        // document.
        var $holder = $('<div>').addClass('sr-cvss-metric-row custom-holder sr-dread-holder-row').append($summary).append($valueCard);

        // Live recalculation on every keystroke ('input', not 'change' --
        // this is the same immediacy a typed number needs, unlike every
        // other holder's <select>-driven 'change' wiring). Delegated on the
        // holder itself for the same "survives being appended into the
        // page later" reasoning buildCvssHolder()'s own listener comment
        // gives. window.CvssRiskLevelPill.formulaConfig() resolves the
        // SAME cached /riskformula/config fetch buildClassicHolder() above
        // already shares -- one request still serves every consumer on the
        // page, Custom included.
        function recalculate() {
            window.CvssRiskLevelPill.formulaConfig().then(function (config) {
                var score = customScoreFromInput($holder.find('#Custom').val(), config);
                $holder.find('#CustomScore').text(score);
                updateRiskLevelPill($holder, score, 'Custom');
            });
        }

        $holder.on('input', '#Custom', recalculate);

        // Build-time: pre-fill the INPUT itself with config.default_risk_score
        // (not just the score preview) -- a fresh holder's field used to stay
        // blank while the preview alone showed the default, so what a user
        // saw on screen (e.g. "10 / High") disagreed with what an untouched
        // save actually persisted (0 / Low). Setting the input's real value
        // here means an untouched save round-trips exactly what was shown.
        // Only a STILL-BLANK input gets the default. formulaConfig() resolves
        // asynchronously (even from its cache, a .then runs as a microtask),
        // i.e. AFTER Edit Details on an existing Custom-scored risk has
        // synchronously restored the real stored value via applyPrefillValue()
        // -- an unconditional write here used to clobber that restored value
        // with default_risk_score, so reopening Edit Details showed (and a
        // second save persisted) the default instead of the stored score. A
        // fresh Submit Risk form starts blank, so its behaviour is unchanged.
        window.CvssRiskLevelPill.formulaConfig().then(function (config) {
            var $custom = $holder.find('#Custom');
            if ($custom.val() === '') {
                $custom.val(Number(config.default_risk_score) || 0);
            }
            recalculate();
        });

        return $holder;
    }

    function buildScoringMethodWidget(instance, field, widgetType) {
        // '.scoring-method-select' is a STABLE lookup handle, independent of
        // the `name` attribute -- syncScoringMethodVisibility() below toggles
        // `name` on/off this exact element (see that function's docblock for
        // why), so every OTHER lookup site (initScoringMethodField() above,
        // applyPrefillValue()'s 'scoring-method' case, and the required-field
        // target switch) must key off this class, never
        // `select[name="scoring_method"]` -- that selector goes stale the
        // instant an unbuilt method is picked.
        var $select = buildSelect(field, widgetType).addClass('scoring-method-select');

        // Inline 3-card row (Score | Likelihood | Impact) -- see
        // buildClassicHolder() above for the full docblock. Classed
        // 'classic-holder' from birth by that function; the .addClass()
        // here is a harmless no-op re-add, matching buildDreadHolder()'s/
        // buildOwaspHolder()'s own call sites just below.
        var $classicHolder = buildClassicHolder(instance, field).addClass('classic-holder').css('display', 'none');

        // Inline 2-card row (Score | Custom Value, 1:2 ratio) -- see
        // buildCustomHolder() above for the full docblock. Classed
        // 'custom-holder' from birth by that function; the .addClass() here
        // is a harmless no-op re-add, matching buildClassicHolder()'s/
        // buildDreadHolder()'s/buildOwaspHolder()'s own call sites just
        // above/below.
        var $customHolder = buildCustomHolder(instance, field).addClass('custom-holder').css('display', 'none');

        // Inline instead of a trigger + modal (this phase): the same four
        // metric groups and 5-score summary the modal used to hold render
        // directly here, matching how Classic/Custom already render inline.
        var $cvssHolder = buildCvssHolder(instance, field).addClass('cvss-holder').css('display', 'none');

        // .sr-qstack gives the top-level select and whichever holder is
        // showing the same 12px field-to-field gap .sr-qgrid uses elsewhere
        // -- without it those two also touch directly, same bug as above.
        return $('<div>').addClass('sr-scoring-method-field sr-qstack')
            .append($select)
            .append($classicHolder)
            .append($cvssHolder)
            .append(buildDreadHolder(instance, field).addClass('dread-holder').css('display', 'none'))
            .append(buildOwaspHolder(instance, field).addClass('owasp-holder').css('display', 'none'))
            .append($customHolder)
            .append(buildContributingRiskHolder(instance, field).addClass('contributing-risk-holder').css('display', 'none'));
    }

    function initScoringMethodField(instance, $control) {
        var $select = $control.find('.scoring-method-select');
        var HOLDERS = {
            CLASSIC: $control.find('.classic-holder'),
            CVSS: $control.find('.cvss-holder'),
            DREAD: $control.find('.dread-holder'),
            OWASP: $control.find('.owasp-holder'),
            CUSTOM: $control.find('.custom-holder'),
            CONTRIBUTING_RISK: $control.find('.contributing-risk-holder')
        };

        // No form=/cross-risk-bleed handling needed here (unlike the
        // modal-based version this replaced): the 14 CVSS selects now live
        // inside .cvss-holder, itself inside .sr-scoring-method-field, a
        // native descendant of this canvas's own <form> -- the same DOM
        // position Classic's/Custom's own sub-fields already occupy. A
        // canvas rebuild (Reset Form/Save & New) produces fresh, blank
        // selects with nothing external to leak from, exactly like those
        // two methods already behave.

        function syncScoringMethodVisibility() {
            var value = String($select.val());
            Object.keys(SCORING_METHOD_VALUES).forEach(function (key) {
                if (SCORING_METHOD_VALUES[key] === value) {
                    HOLDERS[key].show();
                } else {
                    HOLDERS[key].hide();
                }
            });

            // CRITICAL (final whole-plan review, Finding C1): updateRisk()/
            // addRisk() (includes/api.php) read `scoring_method` from POST
            // UNCONDITIONALLY whenever it's present, and always call
            // update_risk_scoring() (includes/functions.php), which does an
            // unconditional UPDATE of THAT METHOD'S ENTIRE COLUMN SET from
            // whatever sub-fields arrived in POST. A method that renders no
            // inputs for its sub-fields would, if this <select> stayed named
            // "scoring_method" while it was selected, let opening Edit
            // Details on ANY risk scored via that method and saving ANY
            // unrelated change (e.g. just the Subject) silently overwrite
            // that risk's real stored score with blanks/zeros. Before this
            // widget existed there was no `scoring_method` control on this
            // form at all, so POST omitted it and update_risk_scoring()'s
            // final `else { return false; }` branch safely no-opped --
            // adding this widget silently removed that protection.
            //
            // Fix: strip the `name` attribute whenever the current selection
            // is NOT one of the now all six methods this widget builds real
            // sub-fields for (Classic, Custom, CVSS, DREAD, OWASP, and
            // Contributing Risk), so jQuery's .serialize() drops the control
            // from the request body entirely and updateRisk()/addRisk()
            // never touch that method's write path. Restore the name when
            // one of those six is picked so they continue to submit
            // normally. This relies on '.scoring-method-select' (not a
            // name-based selector) for every OTHER lookup of this element --
            // see buildScoringMethodWidget()'s comment.
            if (value === SCORING_METHOD_VALUES.CLASSIC || value === SCORING_METHOD_VALUES.CUSTOM || value === SCORING_METHOD_VALUES.CVSS || value === SCORING_METHOD_VALUES.DREAD || value === SCORING_METHOD_VALUES.OWASP || value === SCORING_METHOD_VALUES.CONTRIBUTING_RISK) {
                $select.attr('name', 'scoring_method');
            } else {
                $select.removeAttr('name');
            }
        }

        // Switching methods can make this field either taller (e.g. Classic
        // -> CVSS, whose multi-card layout is far taller than Classic's
        // handful of fields) or shorter (the reverse) -- fitFieldAndCardToContent()
        // covers both directions in one animation-free pass, unlike the
        // general canvas sweep (scheduleAutoFit(), grow-only by design). Not
        // run on the initial syncScoringMethodVisibility() call below: that
        // call is part of the canvas's own initial build, whose sizing the
        // existing build-time growth sweep already owns, and this field's
        // '.grid-stack-item' may not even be attached yet at that point.
        $select.on('change', function () {
            syncScoringMethodVisibility();
            fitFieldAndCardToContent(instance, $select.closest('.grid-stack-item')[0]);
        });
        syncScoringMethodVisibility();
    }

    // NextStep's picker half is the SAME plain <select> buildSelect()
    // already builds for Category/RiskSource/PlanningStrategy above --
    // reused as-is. Alongside it sits a Project selectize field, shown only
    // when the picked NextStep value is 2 ("Consider for Project") --
    // matching display_next_step_edit()'s own project-holder div
    // (includes/displayrisks.php), toggled the SAME way that legacy markup
    // toggles it (display:none / not), just driven by this widget's own
    // change handler instead of a page-global one.
    //
    // The Project field's selectize `create` option matches
    // saveReview()'s own project-value contract EXACTLY (includes/api.php):
    // a numeric value is an existing project id; a string prefixed
    // 'new-projval-prfx-' creates a new one from the text after the prefix.
    // Getting this prefix wrong silently breaks new-project creation with
    // no client-side error -- saveReview() just fails the
    // ctype_digit((string)$project) check and the ThereWasAProblemWithAdding
    // ThereWasAProblemWithAddingTheProject alert fires.
    function buildNextStepWidget(instance, field, widgetType) {
        var $select = buildSelect(field, widgetType);

        var $projectSelect = $('<select>')
            .attr('id', fieldElementId(instance, field) + '-project')
            .attr('name', 'project')
            .css('display', 'none');

        // Existing projects to pick from -- api_attach_ui_risk_field_options()
        // (api/v2/includes/api.php) attaches this SECOND options list to the
        // NextStep field specifically, alongside its own ordinary `options`
        // (the outcome dropdown $select above already consumes via
        // buildSelect()). No blank first option here, unlike buildSelect()'s
        // own '--' convention -- selectize's own `placeholder` (below) covers
        // the empty state, matching display_next_step_edit()'s legacy
        // create_dropdown("projects", $project_id, "project", false) call,
        // whose trailing `false` means no blank option either.
        //
        // $.each(), not `(field.project_options || []).forEach(...)`: a PHP
        // array that is key-complete but iteration-order-scrambled (a real,
        // confirmed shape from get_options_from_table('projects')'s own
        // double-sort -- see api_attach_ui_risk_field_options()'s comment on
        // its array_values() call) encodes as a JSON OBJECT, not an array.
        // `.forEach` throws on a plain object and, uncaught here, aborted
        // buildCanvas()'s entire per-field loop partway through -- the
        // create-log-entry form rendered ONLY the NextStep field and
        // silently dropped every other Review-tab field. jQuery's $.each()
        // iterates an array OR a plain object uniformly, so this widget
        // degrades to "whatever order the object read in" instead of
        // crashing the whole canvas if that shape guarantee ever slips again.
        $.each(field.project_options || [], function (key, opt) {
            $('<option>').val(opt.value).text(opt.name).appendTo($projectSelect);
        });

        var $projectWrapper = $('<div>').addClass('sr-next-step-project-holder').css('display', 'none')
            .append($('<label>').addClass('sr-qlabel').text(_lang['ProjectName'] + ' '))
            .append($projectSelect);

        return $('<div>').addClass('sr-next-step-field')
            .append($select)
            .append($projectWrapper);
    }

    function initNextStepField(instance, $wrapper, field) {
        var $select = $wrapper.find('select').not('[name="project"]');
        var $projectSelect = $wrapper.find('select[name="project"]');
        var $projectWrapper = $wrapper.find('.sr-next-step-project-holder');

        $projectSelect.selectize({
            addPrecedence: true,
            placeholder: _lang['ReviewProjectSelectionPlaceholder'],
            sortField: 'value',
            // A function (rather than `true`) both enables creation AND
            // shapes the created option -- selectize's own createItem()
            // uses `settings.create` directly as the {value, text} builder
            // when it is a function (vendor/@selectize/selectize/dist/js/
            // selectize.js), so a separate `create: true` alongside it would
            // be dead, shadowed config (and trips ESLint's no-dupe-keys).
            create: function (input) {
                return { value: 'new-projval-prfx-' + input, text: input };
            },
            // Every OTHER selectize field on this canvas wires these two --
            // without them, this dropdown's open menu pushed Next Review
            // Date down instead of floating over it: selectizePopupOpenHandler()/
            // selectizePopupCloseHandler() are what call holdClippingOpen()/
            // releaseClipping() (this file, above), the mechanism that keeps
            // an open popup from being measured as real field content by the
            // auto-fit sweep (autoFitCanvas()'s own docblock has the full
            // "Team menu -> 2305px" story this field hit the identical way).
            onDropdownOpen: selectizePopupOpenHandler(instance),
            onDropdownClose: selectizePopupCloseHandler(instance)
        });

        function syncProjectVisibility() {
            if (String($select.val()) === '2') {
                $projectWrapper.show();
            } else {
                $projectWrapper.hide();
            }
        }

        // Toggling the project picker makes this field either taller
        // (revealed) or shorter (hidden again after being revealed) than
        // its current height -- fitFieldAndCardToContent() is the shared
        // helper for exactly this "can go either way" shape, same as
        // RiskScoringMethod's own dropdown switch above and the CVSS
        // holder's Advanced Metrics collapse. The general canvas sweep
        // (scheduleAutoFit(), grow-only by design) covers the reveal case
        // but not the hide case -- confirmed live: after revealing the
        // picker once, switching back to any other outcome left the field
        // at its grown height forever, since nothing ever shrinks it back
        // down on its own. A single scheduleAutoFitSoon() call was ALSO
        // not sufficient for the reveal case on its own: growGridItemToFit
        // Content()'s grow-only sweep only commits a new height once it has
        // measured the SAME candidate on two consecutive passes, and this
        // show()/hide() toggle is one atomic DOM change whose own
        // ResizeObserver reaction collapses into the same debounced timer
        // instead of following it as a genuinely later, second pass --
        // fitFieldAndCardToContent()'s direct, synchronous, animation-free
        // grid.update() has no such two-pass requirement, so it fixes both
        // directions in one call.
        $select.on('change', function () {
            syncProjectVisibility();
            fitFieldAndCardToContent(instance, $select.closest('.grid-stack-item')[0]);
        });
        syncProjectVisibility();
    }

    // Reproduces display_set_next_review_date_edit()'s radio-toggle-plus-
    // date-input shape (includes/displayrisks.php): "No" (default) means
    // accept the server-computed next-review date; "Yes" reveals a date
    // input overriding it. Hosted under the roster's 'NextReviewDate' field
    // (CORE_FIELD_WIDGETS' own comment on that field explains why -- this
    // used to be a separate 'SetNextReviewDate' field, merged into
    // NextReviewDate at the user's request), but its own function/variable
    // names below keep the old name -- purely a naming choice, not a
    // functional dependency: neither radio option nor the date input carries
    // this field's own CORE_FIELD_FORM_NAMES entry -- they submit as
    // 'custom_date' ('yes'/'no') and 'next_review' directly, matching
    // saveReview()'s own $_POST keys exactly (includes/api.php), regardless
    // of which roster field name hosts them.
    //
    // Final-review Finding 3: legacy prefilled the date input with the
    // computed default AND showed "Based on your Risk Score, your next
    // review date will be <date>" above the radios (using
    // $lang['BasedOnTheCurrentRiskScore']), which made an empty custom date
    // rare in practice. This widget must NOT reproduce the prefill half --
    // the design spec and Task 5's own SCENARIO-1 require the whole submit
    // form to open completely blank (an append-only log has no "current
    // review" to seed a control from). So the computed default is shown as
    // READ-ONLY INFORMATIONAL TEXT instead (instance.nextReviewDateDefault,
    // threaded in via RiskDetailsForm.init()'s options -- see init()) --
    // never written into $dateInput.val(). Do not "fix" this back to a
    // prefill; that would fail the blank-form assertion.
    // Redesigned at the user's request: the former "Would you like to use a
    // different date instead? [No] [Yes]" radio pair read as a mandatory
    // either/or CHOICE (and its bold .sr-qlabel question text competed with
    // the outer field label), when the real shape is a single OFF/ON
    // override -- accept the computed default (the common case), or turn on
    // a switch to pick a different one. A toggle switch is this canvas's
    // own established component for exactly that shape (design-system.md's
    // "Toggle switches" section; e.g. buildAdvanced()'s CVSS accordion
    // switch below), so this reuses that mechanic -- a checkbox styled as a
    // pill, $sr-important when checked -- rather than inventing a new
    // control. The default-date informational text now leads (directly
    // under the outer "Next Review Date" label, before the switch), since
    // it is the primary, always-relevant fact; the switch is the secondary,
    // occasional action. `name="custom_date"`/`value="yes"` still matches
    // saveReview()'s (includes/api.php) `$custom_date == "yes"` gate
    // exactly -- an UNCHECKED checkbox posts no `custom_date` key at all,
    // which the server already treats as "no" (its own falsy default), so
    // no server-side change was needed for this swap.
    function buildSetNextReviewDateWidget(instance, field, widgetType) {
        var idPrefix = fieldElementId(instance, field);

        // Informational only -- never the date input's `value`. Sourced from
        // a LIVE computation (get_next_review_default(), keyed off the
        // risk's current calculated/residual level, not its review history)
        // -- shown for every risk, including one with no prior review at
        // all. Hidden only on the '0000-00-00' sentinel the backend's own
        // fallback can return for an unresolved risk lookup.
        var $defaultDateText = $('<div>').addClass('sr-set-next-review-date-default form-text');
        var defaultDate = instance.nextReviewDateDefault;
        if (defaultDate && defaultDate.raw && defaultDate.raw !== '0000-00-00') {
            $defaultDateText.text(_lang['BasedOnTheCurrentRiskScore'] + (defaultDate.display || defaultDate.raw));
        } else {
            $defaultDateText.hide();
        }

        var $toggle = $('<input>').attr('type', 'checkbox').addClass('form-check-input')
            .attr('role', 'switch').attr('name', 'custom_date').attr('id', idPrefix + '-toggle').val('yes');
        // 'form-text' matches $defaultDateText's own typography exactly
        // (Bootstrap's shared .form-text rule) -- both lines are secondary,
        // supporting text in this widget (the computed default fact, then
        // the toggle to override it), so they read as one continuous
        // register rather than the switch's own label standing out at a
        // different size/weight/opacity than the line right above it.
        var $toggleLabel = $('<label>').addClass('form-check-label form-text')
            .attr('for', idPrefix + '-toggle').text(_lang['UseADifferentDate']);
        var $toggleRow = $('<div>').addClass('form-check form-switch sr-set-next-review-date-switch')
            .append($toggle).append($toggleLabel);

        // title carried unconditionally (not only when the field is admin-
        // marked required) -- see populateFieldContent()'s required-target
        // handling below: whether or not this field itself is marked
        // required, this input becomes required dynamically whenever the
        // switch is turned on (initSetNextReviewDateField()'s
        // syncRequiredAndVisibility()), so checkAndSetValidation()'s toast
        // needs a real field name to show in every case, not only the
        // admin-required one. _lang['NextReviewDate'] matches the roster
        // field this widget is now hosted under (see this function's own
        // docblock).
        var $dateInput = $('<input>').attr('type', 'text').addClass('datepicker form-control')
            .attr('name', 'next_review').attr('id', idPrefix + '-date')
            .attr('title', _lang['NextReviewDate']);

        var $dateWrapper = $('<div>').addClass('sr-set-next-review-date-holder').css('display', 'none')
            .append($dateInput);

        return $('<div>').addClass('sr-set-next-review-date-field')
            .append($defaultDateText)
            .append($toggleRow)
            .append($dateWrapper);
    }

    function initSetNextReviewDateField(instance, $wrapper) {
        var $toggle = $wrapper.find('input[name="custom_date"]');
        var $dateWrapper = $wrapper.find('.sr-set-next-review-date-holder');
        var $dateInput = $wrapper.find('input[name="next_review"]');

        // 'auto' (not the plugin's plain default, always 'down') lets
        // daterangepicker flip the calendar to open UPWARD instead when it
        // would otherwise render past the end of the page -- requested
        // after the calendar rendered low enough to need an extra scroll
        // to reach "Apply". Scoped to this ONE widget (initDateField()'s
        // own options param, unused by every other 'date' field on this
        // canvas) rather than a blanket change, since this is the one date
        // field whose card grows immediately above it right when the
        // calendar is about to open (the switch just revealed it), pushing
        // it lower than a normal, already-settled date field would sit.
        initDateField($dateInput, { drops: 'auto' });

        // Finding 3(b)/4: the date input is required ONLY while the switch
        // is ON -- and this is UNCONDITIONAL, independent of whether an
        // admin marked the SetNextReviewDate field itself required in the
        // Customization layout editor (populateFieldContent()'s generic
        // required-target wiring points at this same <input> purely so an
        // admin-required title lands on it; see that function's comment).
        // This is what stops saveReview() (includes/api.php) from silently
        // persisting next_review = '0000-00-00' when a user opts into a
        // custom date and leaves it blank -- checkAndSetValidation()'s
        // sweep can only catch that once `required` lands on this real
        // <input> rather than the wrapper <div> (Finding 4's own gap).
        // While the switch is OFF the field is hidden and inapplicable, so
        // required is cleared -- an admin-required SetNextReviewDate must
        // never block submission on the default (switch-off) choice.
        //
        // fitFieldAndCardToContent() (not scheduleAutoFitSoon()) for the
        // same reason initNextStepField()'s own Project-picker toggle
        // needs it: this field can go either way (grow to reveal the date
        // input, or shrink back down), and the general sweep is grow-only
        // -- confirmed live, the same "never shrinks back" symptom
        // reported for Next Step's picker before that fix.
        function syncRequiredAndVisibility() {
            var isOn = $toggle.prop('checked');
            if (isOn) {
                $dateWrapper.show();
            } else {
                $dateWrapper.hide();
            }
            $dateInput.prop('required', isOn);
        }

        // Fit-to-content only on a REAL user toggle, never on the initial
        // call below -- matching initNextStepField()'s own split (its
        // syncProjectVisibility() call at init time has no fit-to-content
        // either). At init the canvas's own build-time sweep is what sizes
        // every field correctly; calling fitFieldAndCardToContent() here
        // too would run it against a nested grid buildCanvas() may not have
        // finished populating yet.
        $toggle.on('change', function () {
            syncRequiredAndVisibility();
            fitFieldAndCardToContent(instance, $wrapper.closest('.grid-stack-item')[0]);
        });
        syncRequiredAndVisibility();
    }

    // What a profile's widgetRegistry hooks are handed as `ctx`: enough of the
    // instance to build, wire and size a custom widget without reaching into
    // this module's internals.
    function widgetContext(instance, field, widgetType) {
        return {
            profile: resolveProfile(instance.profile),
            idPrefix: instance.idPrefix,
            submitMode: instance.submitMode,
            recordId: instance.updateRiskId,
            prefillValues: instance.prefillValues || {},
            formName: fieldFormName(field, widgetType, instance.profile),
            elementId: fieldElementId(instance, field),
            initMultiselect: function ($select, onSelectionChange) {
                initMultiselectField(instance, $select, onSelectionChange);
            },
            selectizePopupHandlers: {
                onDropdownOpen: selectizePopupOpenHandler(instance),
                onDropdownClose: selectizePopupCloseHandler(instance)
            },
            fitToContent: function (el) {
                if (instance.nestedGrids) {
                    fitFieldAndCardToContent(instance, $(el).closest('.grid-stack-item')[0]);
                }
            },
            scheduleAutoFit: function () {
                if (instance.nestedGrids) {
                    scheduleAutoFitSoon(instance);
                }
            }
        };
    }

    function widgetRegistryOf(instance) {
        return resolveProfile(instance.profile).widgetRegistry;
    }

    function buildFieldControl(instance, field, widgetType) {
        var registry = widgetRegistryOf(instance);
        if (registry && typeof registry.build === 'function') {
            var $custom = normalizeWidgetNode(registry.build(widgetType, field, widgetContext(instance, field, widgetType)), 'build');
            if ($custom) {
                return $custom;
            }
        }
        var profile = instance.profile;
        switch (widgetType) {
            case 'text': return buildTextInput(field, widgetType, profile);
            case 'textarea': return buildTextarea(field, widgetType, profile);
            case 'richtext': return buildRichTextField(instance, field, widgetType);
            case 'select': return buildSelect(field, widgetType, profile);
            case 'multiselect': return buildMultiselect(field, widgetType, profile);
            case 'selectize-single': return buildSelectizeControl(field, widgetType, false, profile);
            case 'selectize-tags': return buildSelectizeControl(field, widgetType, true, profile);
            case 'selectize-grouped': return buildSelectizeControl(field, widgetType, true, profile);
            case 'date': return buildDateInput(field, widgetType, profile);
            case 'file':
                return (field.name === 'SupportingDocumentation' || field.name === 'MitigationSupportingDocumentation')
                    ? buildSupportingDocumentationWidget(instance, field, widgetType)
                    : buildFileInput(field, widgetType, profile);
            case 'mitigation-controls': return buildMitigationControlsWidget(instance, field, widgetType);
            case 'assets-asset-groups': return buildAssetsAssetGroupsWidget(instance, field);
            case 'scoring-method': return buildScoringMethodWidget(instance, field, widgetType);
            case 'next-step': return buildNextStepWidget(instance, field, widgetType);
            case 'set-next-review-date': return buildSetNextReviewDateWidget(instance, field, widgetType);
            default: return null;
        }
    }

    // ------------------------------------------------------------------
    // Post-attach initialization. These plugins need to run against a LIVE
    // DOM node, so the control must already be appended before calling any
    // of these (populateFieldContent() below guarantees that ordering).
    // ------------------------------------------------------------------

    // Renders a bootstrap-multiselect's current selection as removable chips
    // beneath its button -- the exact pattern renderSelectionChips() uses in
    // js/simplerisk/pages/compliance.js, replicated here rather than called
    // directly: compliance.js is a different page's script, not guaranteed to
    // be loaded wherever risk-details-form.js runs (CLAUDE.md's function-
    // reachability rule applies to JS page scripts too, not just PHP
    // requires). enableHTML is never set (design-system.md #14b) -- option
    // labels are user-authored (team/user names), so chip text is built with
    // jQuery `text:` only, never `.html()`.
    function renderSelectionChips($select) {
        if (!$select || !$select.length || !$select.data('multiselect')) {
            return;
        }

        var $container = $select.closest('.multiselect-native-select');
        if (!$container.length) {
            return;
        }

        var $field = $container.find('> .sr-chips-field');
        if (!$field.length) {
            $field = $('<div>', { 'class': 'sr-chips-field' }).appendTo($container);
        }

        var widget = $select.data('multiselect');
        var $btnGroup = (widget && widget.$container && widget.$container.length)
            ? widget.$container
            : $container.find('.btn-group').last();
        $container.find('.btn-group').not($btnGroup).remove();

        $field.find('> .sr-chip').remove();

        $select.find('option:selected').each(function () {
            var value = $(this).val();
            if (value === '' || value === null) {
                return;
            }

            var $chip = $('<span>', { 'class': 'sr-chip', text: $(this).text() });
            $('<button>', {
                type: 'button',
                'class': 'sr-chip-x',
                'aria-label': _lang['Remove'] || 'Remove'
            })
                .append($('<i>', { 'class': 'fa fa-xmark', 'aria-hidden': 'true' }))
                .on('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $select.multiselect('deselect', value);
                    renderSelectionChips($select);
                })
                .appendTo($chip);

            if ($btnGroup.length) {
                $chip.insertBefore($btnGroup);
            } else {
                $chip.appendTo($field);
            }
        });

        if ($btnGroup.length) {
            $field.append($btnGroup);
        }
    }

    // A popup widget (a bootstrap-multiselect menu, a selectize dropdown)
    // renders as a DESCENDANT of the field it belongs to, and every GridStack
    // slot on the way up is `overflow-x: hidden; overflow-y: auto`
    // (gridstack.css) -- so the menu is clipped to the ~70px field slot no
    // matter how tall it really is. Measured live on the Team field: an 84-
    // option menu rendered at its full 2305px with NO internal scroll of its
    // own, of which exactly 12px was hit-testable; zero options and no search
    // box could be reached.
    //
    // The auto-fit sweep cannot fix this and should not try: the menu is
    // `position: absolute`, so contentNaturalHeight() deliberately skips it,
    // and growing a card by 2305px for a menu that closes again would be
    // absurd. Instead, hold the clipping ancestors open for exactly as long as
    // the popup is open. `position: fixed` was tried first and is NOT a
    // substitute -- Bootstrap drives this menu through Popper, which owns the
    // element's `inset`/`transform`, so overriding its positioning scheme
    // moves the menu somewhere unrelated (verified live: 0 options reachable).
    //
    // This is the same class of bug the `.sr-qcard` rule in
    // scss/modules/_questionnaire.scss already carries a note about ("no
    // overflow:hidden -- it clipped the selectize dropdowns so multi-selects
    // could not be opened/picked"); GridStack's own per-item overflow re-
    // introduced it one level down.
    // Bookkeeping is per POPUP, not one shared list, and that is load-bearing.
    // Bootstrap fires `show.bs.dropdown` for a newly clicked menu BEFORE
    // `hide.bs.dropdown` for the one it replaces. With a single shared array
    // the sequence ran: unclip B, then A's hide handler restores "whatever the
    // array holds" -- which by then was B's ancestors. B was left clipped while
    // still open, i.e. the original bug, in the most ordinary flow there is:
    // the classification card puts Technology, Team and AdditionalStakeholders
    // one after another, and moving between them without first closing is how
    // that card gets filled in.
    //
    // Two open popups can also share an ancestor (the same card), so releasing
    // one must not re-clip a node another still needs.
    function holdClippingOpen(instance, popupEl, originEl) {
        if (!popupEl) {
            return;
        }
        releaseClipping(instance, popupEl); // a re-open should not stack duplicate entries

        var nodes = [];
        var node = (originEl || popupEl).parentElement;
        while (node && node !== document.body) {
            if (node.classList && node.classList.contains('grid-stack-item-content')) {
                nodes.push(node);
                node.style.overflow = 'visible';
            }
            node = node.parentElement;
        }
        instance.popupUnclips.push({ nodes: nodes, popup: popupEl });
    }

    function releaseClipping(instance, popupEl) {
        var index = -1;
        instance.popupUnclips.forEach(function (entry, at) {
            if (entry.popup === popupEl) {
                index = at;
            }
        });
        if (index === -1) {
            return;
        }

        var released = instance.popupUnclips.splice(index, 1)[0];
        released.nodes.forEach(function (node) {
            var stillNeeded = instance.popupUnclips.some(function (entry) {
                return entry.nodes.indexOf(node) !== -1;
            });
            if (!stillNeeded) {
                node.style.removeProperty('overflow');
            }
        });

        // The sweep is suspended while a popup is open (see autoFitCanvas());
        // closing the last one is when the work it skipped has to happen.
        if (!instance.popupUnclips.length && instance.autoFitDeferred) {
            instance.autoFitDeferred = false;
            scheduleAutoFitSoon(instance);
        }
    }

    function releaseAllPopupClipping(instance) {
        instance.popupUnclips.slice().forEach(function (entry) {
            releaseClipping(instance, entry.popup);
        });
        instance.popupUnclips = [];
    }

    // The menu bootstrap-multiselect built for the container an event fired
    // on. `event` is undefined when the plugin's widthSynchronizationMode
    // isn't 'never' (bootstrap-multiselect.js's buildContainer() then calls
    // onDropdownShow() with no arguments) -- 'never' is the vendored default
    // and this page doesn't override it, but guard anyway: without it a
    // future option change would throw here, silently breaking both the
    // clipping fix and the auto-fit deferral for that popup.
    function multiselectMenuOf(event) {
        var origin = event && (event.currentTarget || event.target);
        if (!origin || !origin.closest) {
            return null;
        }
        var group = origin.classList.contains('btn-group') ? origin : origin.closest('.btn-group');
        return group ? group.querySelector('.multiselect-container') : null;
    }

    // Without maxHeight (the plugin's default is null -- no cap at all) the
    // menu renders every option at full height, which is what produced the
    // 2305px Team menu above. Capping it gives the menu its own scrollbar,
    // which is both usable and small enough to sit inside the un-clipped card.
    var MULTISELECT_MAX_HEIGHT = 320;

    // `onSelectionChange`, when given, fires at the end of the plugin's own
    // onChange hook -- MitigationControls' accordion re-fetch is the only
    // caller today (initMitigationControlsField() below); every other
    // multiselect field omits it and behaves exactly as before.
    function initMultiselectField(instance, $select, onSelectionChange) {
        $select.multiselect({
            enableFiltering: true,
            enableCaseInsensitiveFiltering: true,
            buttonWidth: '100%',
            maxHeight: MULTISELECT_MAX_HEIGHT,
            buttonText: function () {
                return _lang['AddOrRemove'] || 'Add or remove…';
            },
            onDropdownShow: function (event) {
                var menu = multiselectMenuOf(event);
                holdClippingOpen(instance, menu, menu);
            },
            onDropdownHide: function (event) {
                releaseClipping(instance, multiselectMenuOf(event));
            },
            // A user's pick does NOT fire a native `change` on the underlying
            // <select> -- design-system.md #14b -- so chips are kept in step
            // through the plugin's own onChange hook, never a delegated
            // `change` listener.
            onChange: function ($option) {
                renderSelectionChips($option.closest('select'));
                // Chips wrap onto new lines as they accumulate, which makes the
                // field taller than the slot the load-time sweep sized for it.
                // The ResizeObserver below catches this too; scheduling here as
                // well makes the common case immediate rather than
                // observer-latency dependent.
                scheduleAutoFitSoon(instance);
                if (typeof onSelectionChange === 'function') {
                    onSelectionChange();
                }
            }
        });
        renderSelectionChips($select);
    }

    // Selectize renders its menu into `.selectize-dropdown` inside the same
    // `.selectize-control` wrapper -- so it sits inside the very same clipped
    // GridStack field slot the multiselect menu does, and needs the same
    // escape. (selectize's own `dropdownParent: 'body'` option exists, but it
    // also switches the menu's positioning from $control.position() to
    // $control.offset() -- a different code path in the vendored plugin --
    // where holding the clip open leaves positioning untouched.)
    // Curried on the instance: selectize hands its callbacks only the dropdown
    // element, so the owning instance has to be captured here rather than
    // recovered from the event.
    function selectizePopupOpenHandler(instance) {
        return function ($dropdown) {
            var dropdown = $dropdown && $dropdown.length ? $dropdown[0] : null;
            holdClippingOpen(instance, dropdown, dropdown);
        };
    }

    function selectizePopupCloseHandler(instance) {
        return function ($dropdown) {
            releaseClipping(instance, $dropdown && $dropdown.length ? $dropdown[0] : null);
        };
    }

    function initSelectizeSingleField(instance, $select, field) {
        var options = (field.options || []).map(function (opt) {
            return { value: String(opt.value), name: opt.name };
        });
        $select.selectize({
            plugins: ['remove_button'],
            valueField: 'value',
            labelField: 'name',
            searchField: 'name',
            create: false,
            persist: false,
            placeholder: _lang['UserDropdownPlaceholder'] || '',
            options: options,
            onDropdownOpen: selectizePopupOpenHandler(instance),
            onDropdownClose: selectizePopupCloseHandler(instance)
        });
    }

    function initSelectizeTagsField(instance, $select) {
        var tagType = resolveProfile(instance.profile).tagType;
        $select.selectize({
            plugins: ['remove_button', 'restore_on_backspace'],
            delimiter: '|',
            create: true,
            valueField: 'label',
            labelField: 'label',
            searchField: 'label',
            createFilter: function (input) { return input.length <= 255; },
            preload: true,
            placeholder: _lang['TagsWidgetPlaceholder'] || '',
            load: function (query, callback) {
                if (query.length) {
                    return callback();
                }
                $.ajax({
                    url: BASE_URL + '/api/v2/management/tag_options_of_type?type=' + encodeURIComponent(tagType),
                    type: 'GET',
                    dataType: 'json',
                    error: function () {
                        callback();
                    },
                    success: function (res) {
                        callback(res.data);
                    }
                });
            },
            onDropdownOpen: selectizePopupOpenHandler(instance),
            onDropdownClose: selectizePopupCloseHandler(instance)
        });
    }

    // RiskMapping/ThreatMapping: field.options comes back from
    // GET /ui/risk/fields keyed by group name (PHP's PDO::FETCH_GROUP shape,
    // JSON-encoded to a plain object) -- matching create_selectize_dropdown()'s
    // own grouped branch in includes/functions.php exactly.
    function initSelectizeGroupedField(instance, $select, field) {
        var groupedOptions = field.options || {};
        var flatOptions = [];
        var groups = [];

        Object.keys(groupedOptions).forEach(function (groupName) {
            var label = groupName ? groupName : (_lang['NoGroup'] || '');
            groups.push(label);
            (groupedOptions[groupName] || []).forEach(function (opt) {
                flatOptions.push({ class: label, value: String(opt.value), name: opt.name });
            });
        });

        $select.selectize({
            plugins: ['remove_button'],
            searchField: ['name', 'class'],
            valueField: 'value',
            labelField: 'name',
            create: false,
            persist: false,
            placeholder: field.name === 'RiskMapping'
                ? (_lang['RiskCatalogDropdownPlaceholder'] || '')
                : (_lang['ThreatCatalogDropdownPlaceholder'] || ''),
            options: flatOptions,
            optgroupField: 'class',
            optgroupLabelField: 'label',
            optgroupValueField: 'value',
            optgroups: groups.map(function (g) { return { value: g, label: g }; }),
            onDropdownOpen: selectizePopupOpenHandler(instance),
            onDropdownClose: selectizePopupCloseHandler(instance)
        });
    }

    // `options` is optional and passed straight through to daterangepicker
    // (header.php's initAsDatePicker() wrapper) -- only initSetNextReviewDate
    // Field() below currently uses it (`{drops: 'auto'}`), every other 'date'
    // widget on this canvas keeps the plugin's own plain default.
    function initDateField($input, options) {
        $input.initAsDatePicker(options || {});
    }

    // The onReady hook records the editor's content as it stands once
    // HugeRTE has initialized -- isDirty()'s baseline for this editor. It has
    // to be taken there rather than at build time: HugeRTE normalizes the
    // textarea's markup on init (wrapping bare text in <p>, say), so a
    // build-time copy would read as "changed" before anyone typed.
    function initRichTextField(instance, $textarea) {
        var id = $textarea.attr('id');
        init_compact_editor('#' + id, undefined, function (editor) {
            if (instance.editorBaselines) {
                instance.editorBaselines[id] = editor.getContent();
            }
        });
        instance.activeEditorIds.push(id);
    }

    // $wrapper is now live in the DOM (activateFieldWidget runs after
    // populateFieldContent()'s `$el.append($control)`) -- RiskMitigation
    // Controls.initField() renders the chips field's initial state and
    // wires the picker's open handler. No DataTable/accordion here anymore
    // (see buildMitigationControlsWidget()'s own docblock) -- the table
    // moved to a shared top-level widget that fetches its own data
    // independently of this field's live selection.
    function initMitigationControlsField(instance, $wrapper) {
        RiskMitigationControls.initField($wrapper);

        // Absent entirely for a viewer without canSelectMitigationControls
        // (buildMitigationControlsWidget() never builds it for them).
        var $body = $wrapper.find('.sr-mitigation-controls-accordion .accordion-collapse');
        if ($body.length) {
            RiskMitigationControls.initTableSection($body, instance.updateRiskId, instance.canEditMitigation);
        }
    }

    function activateFieldWidget(instance, $control, field, widgetType) {
        var registry = widgetRegistryOf(instance);
        if (registry && typeof registry.activate === 'function'
            && registry.activate(widgetType, $control, field, widgetContext(instance, field, widgetType)) === true) {
            return;
        }
        switch (widgetType) {
            case 'multiselect':
                initMultiselectField(instance, $control);
                break;
            case 'selectize-single':
                initSelectizeSingleField(instance, $control, field);
                break;
            case 'selectize-tags':
                initSelectizeTagsField(instance, $control);
                break;
            case 'selectize-grouped':
                initSelectizeGroupedField(instance, $control, field);
                break;
            case 'richtext':
                initRichTextField(instance, $control);
                break;
            case 'date':
                initDateField($control);
                break;
            case 'mitigation-controls':
                initMitigationControlsField(instance, $control);
                break;
            case 'assets-asset-groups':
                initAssetsAssetGroupsField(instance, $control);
                break;
            case 'scoring-method':
                initScoringMethodField(instance, $control);
                break;
            case 'next-step':
                initNextStepField(instance, $control, field);
                break;
            case 'set-next-review-date':
                initSetNextReviewDateField(instance, $control);
                break;
            // 'select', 'text', 'textarea', 'file': plain markup, no plugin to wire.
        }
    }

    // selectize does NOT leave `required` on the <select> it enhances. Its
    // refreshValidityState() (vendor/node_modules/@selectize/selectize) moves
    // the property onto its own `$control_input` -- the unnamed text input
    // inside `.selectize-input` -- whenever the selection is empty, and back
    // onto the hidden original as soon as something is picked, because the
    // hidden original cannot display a native validation bubble.
    //
    // checkAndSetValidation() therefore correctly finds that proxy and blocks
    // the submit, but builds its toast from `issue_el.attr("title")` -- and
    // the proxy carries no title, so the message read "field is required"
    // with the name missing (verified live). Copying the label onto it is all
    // that is needed; nothing else about selectize's own handling is touched.
    //
    // The `.error` border checkAndSetValidation() adds lands on that proxy
    // input, which selectize renders border-less inside its own control box,
    // so a required selectize field is reported by toast + focus rather than
    // by an outline. That matches what this call site had before the Cards
    // form: the legacy display_add_risk() markup only ever marked the plain
    // `subject` text input required, so no selectize field was outlined there
    // either.
    function carryRequiredTitleToSelectize($control, labelText) {
        var el = $control && $control.length ? $control[0] : null;
        if (!el || !el.selectize || !el.selectize.$control_input) {
            return;
        }
        el.selectize.$control_input.attr('title', labelText);
    }

    // Fills $el (always a live DOM node -- the GridStack field widget's own
    // content div, reused per Task 6's avoid-the-string-content pattern) with
    // the real .sr-qfield shape from design-system.md #5:
    //   <div class="sr-qfield">
    //     <label class="sr-qlabel">Category <span class="required">*</span></label>
    //     <select class="form-select" name="category"></select>
    //   </div>
    // Pre-fills $control with instance.prefillValues[key]. `key` is DELIBERATELY
    // NOT fieldFormName(field, widgetType): that function returns the DOM
    // `name` attribute for form SUBMISSION, which for a custom field is
    // 'custom_field[<id>]' (PHP array-submission syntax, with a '[]' suffix
    // appended for multi-valued widgets) -- awkward as a JSON object key and
    // not what Task 1's /ui/risk/{id}/values response uses. That endpoint
    // (and risk-details-view.js's fieldValueEntry(), the read-mode
    // counterpart of this lookup) keys custom fields as plain
    // 'custom_field_<id>' instead. This helper reproduces THAT convention,
    // not the submission-name one.
    //
    // A no-op when the field has no entry (a fresh/create-mode instance's
    // prefillValues is always {}).
    //
    // Must run AFTER activateFieldWidget(): selectize/bootstrap-multiselect
    // both replace or wrap the native control, and setting .val() on the
    // native element before the plugin attaches is either ignored (selectize
    // reads its options list at init time) or invisible (bootstrap-multiselect
    // builds its button/menu from the native selection at init time).
    function prefillLookupKey(field, profile) {
        return (Number(field.is_basic) === 1)
            ? ((resolveProfile(profile).maps.formNames || {})[field.name] || field.name)
            : ('custom_field_' + field.id);
    }

    function applyPrefillValue(instance, $control, field, widgetType) {
        var key = prefillLookupKey(field, instance.profile);
        if (!Object.prototype.hasOwnProperty.call(instance.prefillValues, key)) {
            return;
        }
        var value = instance.prefillValues[key];

        var registry = widgetRegistryOf(instance);
        if (registry && typeof registry.prefill === 'function'
            && registry.prefill(widgetType, $control, value, widgetContext(instance, field, widgetType)) === true) {
            return;
        }

        switch (widgetType) {
            case 'text':
            case 'textarea':
            case 'date':
                $control.val(value);
                break;
            case 'richtext':
                // HugeRTE owns the live editor instance by this point
                // (initRichTextField(), called from activateFieldWidget()
                // above); writing straight to the underlying <textarea> only
                // reaches the editor if it re-reads on init, which HugeRTE
                // does -- it initializes FROM the textarea's current value.
                // Setting .val() before HugeRTE's own init call is why this
                // still has to run inside populateFieldContent() rather than
                // after buildCanvas() returns.
                $control.val(value);
                break;
            case 'select':
                $control.val(String(value));
                break;
            case 'multiselect':
                $control.val((value || []).map(String));
                $control.multiselect('refresh');
                renderSelectionChips($control);
                break;
            case 'selectize-single':
                if ($control[0] && $control[0].selectize) {
                    $control[0].selectize.setValue(String(value), true);
                }
                break;
            case 'selectize-grouped':
                if ($control[0] && $control[0].selectize) {
                    $control[0].selectize.setValue((value || []).map(String), true);
                }
                break;
            case 'selectize-tags':
                // NOT setValue() here, unlike every other selectize case.
                // initSelectizeTagsField() configures Tags with preload:true
                // and an async AJAX load() (tag_options_of_type) -- unlike
                // Owner/RiskMapping/ThreatMapping, whose options are supplied
                // synchronously inline at .selectize() call time. This runs
                // (from populateFieldContent(), synchronously, right after
                // activateFieldWidget()) before that AJAX response can
                // possibly land. selectize's setValue() silently drops any
                // value that isn't already a known option -- verified live: it
                // does not even retroactively apply once the options DO load.
                // createItem() has no such dependency: it builds the item
                // straight from the input string via the same create:true
                // path a user's own typed tag takes, which is what Tags -- a
                // free-text field, not a closed enum -- needs anyway.
                if ($control[0] && $control[0].selectize) {
                    (value || []).forEach(function (v) {
                        $control[0].selectize.createItem(String(v), false);
                    });
                }
                break;
            case 'mitigation-controls':
                // $control is the WRAPPER div buildMitigationControlsWidget()
                // returns, not the <select> itself. RiskMitigationControls.
                // applyPrefill() waits on the roster fetch (kicked off by
                // initField(), already called by this point) before building
                // the <option>s it needs to resolve chip labels -- no
                // DataTable to reload anymore (see buildMitigationControls
                // Widget()'s own docblock).
                RiskMitigationControls.applyPrefill($control, value || []);
                break;
            case 'scoring-method':
                // NextStep/SetNextReviewDate (this widget's own structural
                // model -- see buildScoringMethodWidget()'s docblock) are
                // deliberately blank-on-open (create-log-entry semantics),
                // but RiskScoringMethod is a real STORED field on an existing
                // risk, so Edit Details must restore it -- same reasoning as
                // 'mitigation-controls' above. `value` here is already
                // instance.prefillValues['scoring_method'] (the switch's own
                // `key`/`value` lookup above resolves CORE_FIELD_FORM_NAMES.
                // RiskScoringMethod = 'scoring_method'). The three sub-values
                // below are read straight off instance.prefillValues by their
                // raw POST-field name (NOT via prefillLookupKey()/
                // CORE_FIELD_FORM_NAMES) because 'likelihood'/'impact'/
                // 'Custom' aren't separate field-roster entries -- they're
                // raw sibling keys api_ui_core_field_resolvers() (api.php)
                // exposes in the SAME /ui/risk/{id}/values response purely to
                // make this restore possible. A partial restore (method only)
                // would be actively wrong: it would show e.g. "Classic" while
                // leaving Likelihood/Impact blank, and unlike today's
                // fully-blank state (which update_risk_scoring() safely
                // no-ops on without a real scoring_method), a half-restored
                // form WOULD pass that check and silently overwrite the
                // stored Likelihood/Impact/Custom with blanks on save.
                var $methodSelect = $control.find('.scoring-method-select');
                $methodSelect.val(String(value));
                if (Object.prototype.hasOwnProperty.call(instance.prefillValues, 'likelihood')) {
                    $control.find('select[name="likelihood"]').val(String(instance.prefillValues.likelihood));
                }
                if (Object.prototype.hasOwnProperty.call(instance.prefillValues, 'impact')) {
                    $control.find('select[name="impact"]').val(String(instance.prefillValues.impact));
                }
                if (Object.prototype.hasOwnProperty.call(instance.prefillValues, 'Custom')) {
                    $control.find('input[name="Custom"]').val(String(instance.prefillValues.Custom));
                    // Recompute Custom's score/pill from the just-restored
                    // input -- without this, Edit Details on an existing
                    // Custom-scored risk would show buildCustomHolder()'s own
                    // build-time default_risk_score (computed against a
                    // still-blank input) until the user typed something,
                    // instead of the risk's real stored value. Guarded the
                    // same as the input restore just above, for consistency
                    // with the field-presence guards this function uses
                    // elsewhere (e.g. the CVSS/DREAD/OWASP forEach blocks
                    // below) -- with no 'Custom' key in prefillValues, the
                    // input stays at buildCustomHolder()'s own blank default
                    // and skipping the recompute lands on the same fallback
                    // value that build-time compute already wrote, so this
                    // is a pure consistency refactor, not a behavior change.
                    // window.CvssRiskLevelPill is always defined (declared
                    // earlier in this same file, unlike Classic's/CVSS's/
                    // DREAD's/OWASP's own separate calculation modules), so
                    // no extra module-existence guard is needed here.
                    window.CvssRiskLevelPill.formulaConfig().then(function (config) {
                        var restoredCustomScore = customScoreFromInput($control.find('input[name="Custom"]').val(), config);
                        $control.find('.custom-holder #CustomScore').text(restoredCustomScore);
                        updateRiskLevelPill($control.find('.custom-holder'), restoredCustomScore, 'Custom');
                    });
                }
                // Recompute Classic's score/pill from the just-restored
                // Likelihood/Impact selects -- same reasoning as the CVSS/
                // DREAD/OWASP restore blocks below (an explicit recompute
                // here, not just the $methodSelect.trigger('change') at the
                // bottom of this case, since that trigger only re-runs
                // syncScoringMethodVisibility(), not a score calculation).
                if (window.ClassicScoring && window.CvssRiskLevelPill) {
                    window.CvssRiskLevelPill.formulaConfig().then(function (config) {
                        var restoredClassicScore = window.ClassicScoring.calculateClassic($control.find('.classic-holder')[0], config);
                        updateRiskLevelPill($control.find('.classic-holder'), restoredClassicScore, 'Classic');
                    });
                }
                // Seed the CVSS holder's 14 visible selects directly from
                // the raw sibling resolvers so a stored CVSS score restores
                // on Edit Details -- same reasoning as likelihood/impact/
                // Custom above. $control.find(...) works here (unlike the
                // modal-based version this replaced): these selects now
                // live inside .cvss-holder, a descendant of $control. A
                // field with no key in instance.prefillValues is left at
                // its default (blank) option -- a real CVSS-scored risk can
                // have some of the 14 fields unset (create_cvss_dropdown()'s
                // $blank=true default), and this must not force a value
                // onto those.
                CVSS_FIELDS.forEach(function (cvssField) {
                    if (Object.prototype.hasOwnProperty.call(instance.prefillValues, cvssField.name)) {
                        $control.find('select[name="' + cvssField.name + '"]').val(String(instance.prefillValues[cvssField.name]));
                    }
                });
                if (window.CvssV2Scoring) {
                    var restoredScore = window.CvssV2Scoring.calculateCVSS();
                    updateRiskLevelPill($control.find('.cvss-holder'), restoredScore, 'Cvss');
                }
                // Seed the DREAD holder's 5 visible selects directly from
                // the raw sibling resolvers, same reasoning as the CVSS
                // block above. DREAD has no "blank" state (all 5 fields
                // always resolve to a stored 0-10 value once scored via
                // this method), so every key is expected to be present.
                DREAD_FIELDS.forEach(function (dreadField) {
                    if (Object.prototype.hasOwnProperty.call(instance.prefillValues, dreadField.name)) {
                        $control.find('select[name="' + dreadField.name + '"]').val(String(instance.prefillValues[dreadField.name]));
                    }
                });
                if (window.DreadScoring) {
                    var restoredDreadScore = window.DreadScoring.calculateDread($control.find('.dread-holder')[0]);
                    updateRiskLevelPill($control.find('.dread-holder'), restoredDreadScore, 'Dread');
                }
                // Seed the OWASP holder's 16 visible selects directly from
                // the raw sibling resolvers, same reasoning as the DREAD
                // block above.
                OWASP_FIELDS.forEach(function (owaspField) {
                    if (Object.prototype.hasOwnProperty.call(instance.prefillValues, owaspField.name)) {
                        $control.find('select[name="' + owaspField.name + '"]').val(String(instance.prefillValues[owaspField.name]));
                    }
                });
                if (window.OwaspScoring && window.CvssRiskLevelPill) {
                    window.CvssRiskLevelPill.levels().then(function (levels) {
                        var restoredOwaspScore = window.OwaspScoring.calculateOwasp($control.find('.owasp-holder')[0], levels);
                        updateRiskLevelPill($control.find('.owasp-holder'), restoredOwaspScore, 'Owasp');
                    });
                }
                // Seed the Contributing Risk holder's Likelihood select +
                // however many factor rows are currently rendered, from
                // the raw sibling resolvers. instance.prefillValues.
                // ContributingImpacts is an OBJECT (factor_id => impact),
                // unlike every other sibling resolver's flat scalar -- see
                // this phase's own spec. A factor id present in the stored
                // data but no longer rendered (deleted from
                // contributing_risks since this risk was scored, Review
                // Focus) has no select to seed -- $control.find(...) on a
                // name that doesn't exist returns an empty jQuery set, and
                // .val() on that is a silent no-op, same as every other
                // restore block's own "field not present" case.
                if (Object.prototype.hasOwnProperty.call(instance.prefillValues, 'ContributingLikelihood')) {
                    $control.find('select[name="ContributingLikelihood"]').val(String(instance.prefillValues.ContributingLikelihood));
                }
                var contributingImpacts = instance.prefillValues.ContributingImpacts || {};
                Object.keys(contributingImpacts).forEach(function (factorId) {
                    $control.find('select[name="ContributingImpacts[' + factorId + ']"]').val(String(contributingImpacts[factorId]));
                });
                if (window.ContributingRiskScoring) {
                    var restoredContributingScore = window.ContributingRiskScoring.calculateContributingRisk($control.find('.contributing-risk-holder')[0], field);
                    updateRiskLevelPill($control.find('.contributing-risk-holder'), restoredContributingScore, 'Contributing');
                }
                // Re-run the widget's own change handler so the correct
                // holder shows now that the real value is set -- initial
                // build-time sync ran before this function (applyPrefillValue()
                // runs AFTER activateFieldWidget(), see this function's own
                // docblock above), against a still-blank select.
                $methodSelect.trigger('change');
                break;
            case 'next-step':
                // Re-review carry-forward (risk-view-review.js's own
                // buildPrefillValues() docblock has the full reasoning): the
                // outcome picker only, matching the required-target switch's
                // own '.not(\'[name="project"]\')' exclusion above -- there
                // is no legacy analogue for restoring a previously-picked
                // PROJECT (that sub-field is new to this redesign), so
                // "Consider for Project" carries forward as an outcome, but
                // the project selection itself starts blank either way.
                // .trigger('change') is not optional: initNextStepField()'s
                // own handler (syncProjectVisibility() +
                // fitFieldAndCardToContent()) is what reveals the Project
                // sub-field and resizes the card when the carried-forward
                // value IS "Consider for Project" -- a bare .val() fires no
                // event on its own.
                $control.find('select').not('[name="project"]').val(String(value)).trigger('change');
                break;
            // 'file'/'skip': no prefillable value (a file input cannot be
            // set programmatically; 'skip' renders no control at all).
            // 'set-next-review-date': deliberately excluded -- Finding 3(a)'s
            // reasoning (risk-view-review.js) still holds even with Review/
            // NextStep/Comment now carried forward: a fresh review always
            // recomputes its own next-review default from the risk's
            // CURRENT score, never a prior review's saved custom override.
        }
    }

    function populateFieldContent(instance, $el, field) {
        $el.addClass('sr-qfield');

        var widgetType = fieldWidgetType(field, instance.profile);

        // PATCH /risks/{id} cannot accept multipart bodies (see updateRisk()'s
        // parse_non_post_body_into_post(), which handles JSON/urlencoded
        // only), so a plain file input rendered here in update mode would
        // silently fail to submit -- for an unmapped file field (there are
        // none today, but a future custom field type could add one) fall
        // through to the SAME placeholder path an unmapped field type uses,
        // rather than rendering a picker that cannot work. SupportingDocumentation/
        // MitigationSupportingDocumentation are the exception: they go through
        // buildSupportingDocumentationWidget() below instead, which routes
        // update mode to a dedicated multipart endpoint
        // (POST /risks/{id}/supporting-documentation or its /mitigations/
        // sibling) rather than the big form's own PATCH, so they keep their
        // real widgetType regardless of submitMode.
        if (widgetType === 'file' && instance.submitMode === 'update'
            && field.name !== 'SupportingDocumentation' && field.name !== 'MitigationSupportingDocumentation') {
            widgetType = null;
        }

        if (!widgetType) {
            // Out of this task's inventoried scope (JiraIssueKey, or an
            // unrecognized custom-field type) -- keep Task 6's placeholder
            // rather than guess at a control a user could submit garbage
            // through.
            $el.addClass('sr-qfield-placeholder');
            // Localized exactly like the real .sr-qlabel below. This branch used
            // to render `field.name` raw, which put literal "AffectedAssets" and
            // "JiraIssueKey" on the page -- in English, for every locale -- even
            // though both keys have existed in lang.en.php all along. A
            // placeholder is still user-facing text; only the CONTROL is
            // out of scope here, not the label.
            $('<span>').addClass('sr-qfield-placeholder-text')
                .text(_lang[field.name] || field.name)
                .appendTo($el);
            return;
        }

        if (widgetType === 'skip') {
            // SubmissionDate/SubmittedBy: real Add-flow behavior is to render
            // nothing (see the lookup table's docblock above).
            return;
        }

        var labelText = _lang[field.name] || field.name;
        var $label = $('<label>').addClass('sr-qlabel').text(labelText + ' ');
        if (isFieldRequired(field, instance.profile)) {
            $('<span>').addClass('required').text('*').appendTo($label);
        }
        $el.append($label);

        var $control = buildFieldControl(instance, field, widgetType);
        if (!$control) {
            return;
        }

        // Required-ness has to reach the DOM, not just the '*' marker above.
        // Every one of these modals submits through risk.js's addRisk(), which
        // gates on common.js's checkAndSetValidation(container) -- and that
        // helper finds its offenders with a plain `$("input, select, textarea",
        // container)` sweep filtered on `.prop('required')`. With the attribute
        // never set, the sweep matched nothing and an empty required field
        // sailed past the client-side check entirely: no `.error` highlight, no
        // focus on the first offender, no toast. (The server still refused it --
        // addRisk() in includes/api.php answers 400/SubjectRiskCannotBeEmpty --
        // so this was a lost affordance, not a data-integrity hole.) The legacy
        // display_add_risk() markup this engine replaced carried
        // `required name='subject' title='<Subject>'` for exactly this reason.
        //
        // `title` is not decorative either: checkAndSetValidation() builds its
        // toast as _lang['FieldRequired'].replace("{$field}", <title>), so an
        // untitled control produces a nameless " field is required" (verified
        // live). It gets the same localized label text the <label> above
        // renders, matching how
        // display.php sets `title='{$escaper->escapeHtml($lang[...])}'` on its
        // own required inputs.
        //
        // Set BEFORE activateFieldWidget() so it is present on the native
        // element at enhancement time. selectize and bootstrap-multiselect both
        // keep that original element in the DOM (hidden) and write the user's
        // selection back to it, so checkAndSetValidation()'s plain DOM query
        // still finds it and reads a truthful value -- and its
        // `.multiselect-native-select` parent branch is what puts the `.error`
        // class on the multiselect's visible button.
        //
        // 'mitigation-controls', 'next-step' and 'set-next-review-date' are
        // the three widget types whose $control is a WRAPPER div (picker(s)
        // + extra markup), not the native control itself --
        // checkAndSetValidation()'s plain `$("input, select, textarea", ...)`
        // sweep would never see `required` set on a <div>, so it has to land
        // on the real control inside instead. MitigationControls is never
        // seeded required today (get_risk_mitigation_core_field_card_map()'s
        // source rows all carry required=0), so that branch is currently
        // unreachable, but wrong-by-default would be a silent trap the
        // moment that changes -- confirmed for 'next-step'/
        // 'set-next-review-date' too during the final whole-plan review
        // (Finding 4): both were falling through to the generic `$control`
        // branch, landing `required` on a <div> that never validates.
        if (isFieldRequired(field, instance.profile)) {
            var $requiredTarget;
            switch (widgetType) {
                case 'mitigation-controls':
                    $requiredTarget = $control.find('select');
                    break;
                case 'next-step':
                    // The main outcome picker only -- NOT the conditionally-
                    // shown Project sub-control (a bare `select` selector
                    // would incorrectly catch that one too).
                    $requiredTarget = $control.find('select').not('[name="project"]');
                    break;
                case 'set-next-review-date':
                    // The radio group always has a value (one of "No"/"Yes"
                    // is checked by default), so a `required` on a radio
                    // input would never be the binding constraint -- the
                    // real gap is the DATE INPUT staying unvalidated. Target
                    // it for the TITLE here; initSetNextReviewDateField()'s
                    // syncRequiredAndVisibility() owns the actual dynamic
                    // `required` state (required only while "Yes" is
                    // selected), which it applies unconditionally and will
                    // immediately re-normalize whatever this block sets.
                    $requiredTarget = $control.find('input[name="next_review"]');
                    break;
                case 'scoring-method':
                    // The method picker only -- none of the six sub-controls
                    // are forced required by marking the FIELD required; each
                    // method's own real widget (built in 4d-ii/iii/iv) can add
                    // its own required sub-fields independently if it needs
                    // to, the same way SetNextReviewDate's date input manages
                    // its own dynamic required state separately from this.
                    $requiredTarget = $control.find('.scoring-method-select');
                    break;
                default:
                    $requiredTarget = $control;
            }
            $requiredTarget.attr('required', 'required').attr('title', labelText);
        }

        $el.append($control);
        activateFieldWidget(instance, $control, field, widgetType);
        applyPrefillValue(instance, $control, field, widgetType);

        if (isFieldRequired(field, instance.profile)) {
            carryRequiredTitleToSelectize($control, labelText);
        }
    }

    // A field whose widget type is 'skip' draws NOTHING at all --
    // SubmissionDate and SubmittedBy are both set automatically by the submit
    // handler and the real Add flow renders no control for either (see
    // CORE_FIELD_WIDGETS' docblock above). Giving one a grid slot anyway spends
    // its whole designed height on blank space. Measured live on the default
    // layout, the General card carried two such 60px voids -- one between
    // Subject and Site/Location, one between External Reference ID and Risk
    // Source -- each reading as ~96px of gap where an ordinary pair of fields
    // sits ~38px apart. That is the single largest contributor to "way too much
    // spacing between fields" on this page.
    //
    // Dropping them is not enough by itself: every other field keeps the pos_y
    // the saved layout gave it, so the hole simply stops being occupied and
    // stays exactly as tall. Rows are therefore re-seated -- but ONLY by space
    // that a dropped field actually vacated, which keeps this a removal rather
    // than a compaction:
    //   * a row that merely lost one of two side-by-side fields still occupies
    //     its designed height, because the surviving field still needs it;
    //   * a gap an admin deliberately left between two rows survives, because
    //     only space a dropped field held is ever reclaimed, never space that
    //     was empty to begin with.
    //
    // The reclaim is computed over ROW SPANS rather than over distinct pos_y
    // values, and that distinction is the whole of it. Keying on pos_y alone
    // treats "no field STARTS on this row" as "this row is free", which is only
    // true in a single column. The layout editor lets an admin put a tall field
    // in one column beside two short ones in another, and then a row whose only
    // starter is a dropped SubmissionDate/SubmittedBy still lies INSIDE the
    // tall field's span. Reclaiming it pulled everything below up into that
    // field, and GridStack silently displaced whatever collided -- a layout the
    // admin never asked for, on exactly the layouts this editor exists to make
    // possible. Not reachable on the default single-column stack, which is why
    // it survived the first three rounds of this page's layout work.
    //
    // So: a row is reclaimable when a dropped field covered it AND no surviving
    // field covers it. Each kept field then moves up by however many reclaimable
    // rows lie above it.
    //
    // Returns [{ field, y }] in row order, with `y` still in the SAVED layout's
    // 30px-row units -- renderFieldItem() applies LAYOUT_ROW_SCALE.
    function packRenderableFields(fields, profile) {
        // Row spans are walked one row at a time below, so a nonsense pos_h out
        // of the API cannot be allowed to drive that loop. Both layout sources
        // seed small integers (a chip is 2 rows), so this only ever trips on
        // malformed stored geometry.
        var MAX_FIELD_ROWS = 64;

        function rowSpan(field) {
            var y = Number(field.pos_y);
            var h = Number(field.pos_h);
            if (!isFinite(y) || y < 0) {
                y = 0;
            }
            if (!isFinite(h) || h < 1) {
                h = 1;
            }
            // A richtext field's real footprint is RICHTEXT_FIELD_MIN_SAVED_ROWS
            // regardless of its stored pos_h -- see that constant's own
            // docblock. Any field placed after it needs a cursor that already
            // accounts for the taller span, or it lands right where the
            // richtext field will actually still be, colliding with it the
            // moment renderFieldItem() adds it (renderFieldItem() seeds the
            // SAME minimum for the richtext field's own h, so the two agree).
            if (fieldWidgetType(field, profile) === 'richtext') {
                h = Math.max(h, RICHTEXT_FIELD_MIN_SAVED_ROWS);
            }
            return { top: Math.floor(y), bottom: Math.floor(y) + Math.min(Math.ceil(h), MAX_FIELD_ROWS) };
        }

        // Full width (spans the entire nested grid, customization_nested_
        // grid_columns()=6 -- see risk-details-view.js's identical check)
        // reserves rows for EVERY column; a half-width field only reserves
        // rows within its own column. 'full' is its own bucket key rather
        // than one more real pos_x value, so it can never collide with an
        // actual half-width column's own key.
        function columnKey(field) {
            return (Number(field.pos_w) >= 6) ? 'full' : String(Number(field.pos_x) || 0);
        }

        var kept = [];
        var vacated = {};
        var occupied = {};
        // The reclaim mechanism's mirror image: a richtext field's REAL span
        // (rowSpan(), above) can be taller than its stored pos_h, and every
        // field BELOW IT IN THE SAME COLUMN has to move down to make room --
        // inserted rows, recorded at the point they start (this field's
        // ORIGINAL stored bottom) rather than baked into any one field's y,
        // so a field between two richtext fields picks up both insertions
        // and a field above either picks up neither. Column-scoped (keyed by
        // columnKey(), same as occupied/vacated below): a HALF-WIDTH
        // richtext field's own reserved extra height has no reason to push
        // anything in the OTHER column, which shares only the same row
        // range, not the same content -- confirmed live as a real bug, not
        // a hypothetical: Review's Comment field (CORE_FIELD_WIDGETS'
        // 'richtext', kept half-width under Reviewer at the user's request,
        // unlike every OTHER richtext field on this canvas which is full
        // width) pushed NextStep -- a DIFFERENT column -- down by its own
        // entire reserved amount, when nothing about NextStep's own column
        // needed any extra room at all. A FULL-WIDTH richtext field still
        // reserves for every column via the 'full' bucket, same as before.
        // See renderFieldItem()'s identical Math.max(pos_h, RICHTEXT_FIELD_
        // MIN_SAVED_ROWS) -- computing both ends from the same constant is
        // what guarantees a field seeded immediately after a richtext one
        // in the SAME column lands exactly at its real bottom, with
        // GridStack never seeing an overlap to resolve on its own (see
        // RICHTEXT_FIELD_MIN_SAVED_ROWS's own docblock for why that matters).
        var insertionsByColumn = {};

        fields.forEach(function (field) {
            var span = rowSpan(field);
            var drawsNothing = fieldWidgetType(field, profile) === 'skip';
            var target = drawsNothing ? vacated : occupied;
            for (var row = span.top; row < span.bottom; row++) {
                target[row] = true;
            }
            if (!drawsNothing) {
                kept.push({ field: field, y: span.top });
            }
            if (fieldWidgetType(field, profile) === 'richtext') {
                var storedH = Number(field.pos_h);
                if (!isFinite(storedH) || storedH < 1) {
                    storedH = 1;
                }
                var amount = (span.bottom - span.top) - storedH;
                if (amount > 0) {
                    var key = columnKey(field);
                    insertionsByColumn[key] = insertionsByColumn[key] || [];
                    insertionsByColumn[key].push({ at: span.top + storedH, amount: amount });
                }
            }
        });

        // A row a surviving field still covers is not free, no matter which
        // column that field is in -- this is the spanning-field case above.
        var reclaimable = Object.keys(vacated)
            .map(Number)
            .filter(function (row) { return !occupied[row]; })
            .sort(function (a, b) { return a - b; });

        kept.forEach(function (entry) {
            var shift = 0;
            for (var i = 0; i < reclaimable.length && reclaimable[i] < entry.y; i++) {
                shift++;
            }
            var grow = 0;
            // A full-width richtext field's own insertion still applies to
            // every column (it visually pushes the whole row regardless of
            // column), so both this field's own column bucket AND 'full'
            // are consulted -- never the OTHER half-width column's bucket.
            (insertionsByColumn[columnKey(entry.field)] || []).forEach(function (insertion) {
                if (insertion.at <= entry.y) {
                    grow += insertion.amount;
                }
            });
            if (columnKey(entry.field) !== 'full') {
                (insertionsByColumn.full || []).forEach(function (insertion) {
                    if (insertion.at <= entry.y) {
                        grow += insertion.amount;
                    }
                });
            }
            // Clamped at 0 for safety only: `shift` counts reclaimable rows
            // strictly ABOVE this field, so it cannot legitimately exceed its
            // y, and a negative y is not something GridStack should be handed.
            entry.y = Math.max(0, entry.y - shift + grow);
        });

        kept.sort(function (a, b) { return a.y - b.y; });

        return kept;
    }

    // Every field -- required and optional alike -- renders directly into the
    // card's per-field GridStack (Task 6's mechanism, untouched), at the
    // field's own designed position, scaled onto this page's finer render grid
    // (see LAYOUT_ROW_SCALE). `posY` comes from packRenderableFields() rather
    // than straight off the field, so a row removed above it is already
    // accounted for.
    // RiskScoringMethod always renders full width (6 of the nested grid's 6
    // columns), regardless of its stored/fetched pos_x/pos_w. This is safe
    // to force unconditionally, unlike any other field: 'scoring' is a
    // dedicated one-field card_key (customization_card_key_order() /
    // FIELD_CARD_MAP, includes/functions.php) that no other field is ever
    // assigned to, so there is no sibling to collide with, on any
    // installation. Forced here rather than in the stored/seeded layout so
    // it applies without touching per-installation saved customization data
    // for every OTHER field -- the same reasoning that keeps Subject's own
    // full-width exception in get_subject_synthetic_field_entry() rather
    // than in its (nonexistent) stored row. CVSS's inline content (Phase
    // 4d-ii) is what needs the room: a left column of metric selects and a
    // right column with the live score summary.
    function isRiskScoringMethodField(field) {
        return field.name === 'RiskScoringMethod';
    }

    function renderFieldItem(instance, nestedGrid, field, posY) {
        var fieldId = String(field.id);
        var forceFullWidth = isRiskScoringMethodField(field);
        // Seed a richtext field at its known settled height up front -- see
        // RICHTEXT_FIELD_INITIAL_ROWS's own docblock for why growing into it
        // later (growGridItemToFitContent()) is unsafe when other fields sit
        // below it. Math.max, not a flat override: an admin who has already
        // sized the field taller than that in the layout editor keeps their
        // own value. Same RICHTEXT_FIELD_MIN_SAVED_ROWS packRenderableFields()
        // uses for this field's row span, so the two never disagree about how
        // much room it occupies.
        var savedH = (fieldWidgetType(field, instance.profile) === 'richtext')
            ? Math.max(Number(field.pos_h) || 0, RICHTEXT_FIELD_MIN_SAVED_ROWS)
            : field.pos_h;
        var fieldItem = nestedGrid.addWidget({
            x: forceFullWidth ? 0 : field.pos_x,
            y: posY * LAYOUT_ROW_SCALE,
            w: forceFullWidth ? 6 : field.pos_w,
            h: savedH * LAYOUT_ROW_SCALE,
            id: 'field-' + fieldId + '-' + field.name
        });

        var $content = $(fieldItem).find('.grid-stack-item-content')
            .attr('data-field-id', fieldId)
            .attr('data-field-name', field.name)
            .empty();

        populateFieldContent(instance, $content, field);
    }

    // ------------------------------------------------------------------
    // Auto-fit: grow a GridStack item until its content stops needing to
    // scroll.
    //
    // WHY this is needed and not just a belt-and-braces net. Both layout
    // sources size a card from a FLAT per-field formula -- n fields at 2
    // nested rows each, plus one top-grid row for the header
    // (customization_card_height_for_field_count(), includes/functions.php,
    // used by the no-Extra fallback and by the Customization Extra's
    // backfill). That formula budgets nothing for the card body's own 13px
    // padding, the card header's real rendered height, or GridStack's
    // per-item margin, and it assumes every field's control fits the 60px a
    // 2-row field slot gives it -- which a HugeRTE rich-text editor
    // (RiskAssessment/AdditionalNotes, 150px + toolbar) or a
    // bootstrap-multiselect with many chips does not. GridStack's
    // .grid-stack-item-content is `overflow-y: auto` (gridstack.css), so
    // whatever does not fit simply becomes an inner scrollbar. This pass
    // measures the real rendered overflow and grows the item instead.
    //
    // NOT GridStack's own resizeToContent()/sizeToContent: that helper
    // measures `.grid-stack-item-content`'s FIRST ELEMENT CHILD
    // (gridstack.js), which for our markup is the card's <div class=
    // "sr-qcard-head"> or the field's <label class="sr-qlabel"> -- not the
    // full card/field -- and it shrinks as readily as it grows. Adding a
    // wrapper div to satisfy it would change the .sr-qcard/.sr-qfield markup
    // contract in design-system.md #5. grid.update(el, {h}) is the same
    // public API it ultimately calls.
    //
    // GROW ONLY, never shrink: we only ever pass an h strictly greater than
    // the node's current h, so a card never drops below the height its saved
    // layout (or the synthesized fallback) configured for it, and repeat
    // passes converge instead of oscillating.
    // ------------------------------------------------------------------

    // How tall this item's content REALLY wants to be, measured off the laid-
    // out children rather than the container's own scrollHeight.
    //
    // scrollHeight is the obvious choice and it is wrong here. It reports the
    // ALLOTTED height whenever the content fits (so a pass can never tell
    // "fits exactly" from "has 40px of slack"), and -- measured live on this
    // page -- it over-reports on the very first pass, while a card is still
    // at its saved height and its own `overflow-y: auto` content box is
    // scrolling: the same label+input field that measures 59px once the card
    // has grown reported 76-94px on that first pass, which then locked the
    // field into a permanently over-sized 4 rows (grow-only never takes it
    // back). Summing the children's own boxes is stable across every pass
    // and across the card growing underneath them.
    function contentNaturalHeight(content) {
        var style = window.getComputedStyle(content);
        var natural = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
        var rowGap = parseFloat(style.rowGap) || 0;
        var counted = 0;

        Array.prototype.forEach.call(content.children, function (child) {
            var childStyle = window.getComputedStyle(child);
            // Out-of-flow children contribute nothing to the parent's height:
            // display:none is how HugeRTE parks the original <textarea> once
            // it has built its own editor chrome beside it.
            if (childStyle.display === 'none' || childStyle.position === 'absolute' || childStyle.position === 'fixed') {
                return;
            }
            var childHeight = child.getBoundingClientRect().height;

            // .sr-qfield is `display: flex; flex-direction: column`
            // (scss/modules/_questionnaire.scss) and the GridStack slot it
            // lives in has a FIXED height -- so a child that declares its own
            // height is a flex item that FLEX-SHRINKS to whatever the slot
            // currently allows. Measuring only that shrunk box would make this
            // pass answer "it fits" for precisely the content it exists to
            // catch: init_compact_editor() (js/WYSIWYG/editor.js) hands HugeRTE
            // `height: 150`, which HugeRTE writes as an inline `height: 150px`
            // on its .tox container -- and a 70px slot squeezes that to 46px
            // with a ZERO-height .tox-edit-area, i.e. a toolbar with no
            // typeable body, reporting no overflow the whole time (measured
            // live on this page). The author-specified inline height is the
            // un-shrunk size, so take whichever is larger; once the slot has
            // grown, the real box matches it and the pass converges.
            // getComputedStyle().height is no substitute -- for a flex item it
            // reports the USED (already-shrunk) value.
            var inlineHeight = child.style.height;
            if (inlineHeight && inlineHeight.slice(-2) === 'px') {
                var specifiedHeight = parseFloat(inlineHeight);
                if (specifiedHeight > childHeight) {
                    childHeight = specifiedHeight;
                }
            }

            // The same flex-shrink trap, for a child that never declares a
            // height of its own and so has no inline value to recover. A
            // bootstrap-multiselect grows by WRAPPING: selecting 25 Teams
            // through the real UI adds 25 .sr-chip elements inside
            // <span class="multiselect-native-select">, whose own content then
            // stands 2458px tall -- but as a flex item in a 70px slot its
            // border box still measured 154px, so the box alone says "fits"
            // (measured live). scrollHeight is the child's OWN content height
            // and is not affected by the slot squeezing it, so it recovers the
            // real figure. Taken only as a MAX, it cannot shrink anything: for
            // a child whose content fits, scrollHeight equals its content box
            // and the border box already covers it. (This is why the objection
            // to scrollHeight in the docblock above does not apply here -- that
            // is about the CONTAINER's scrollHeight reporting its allotted
            // height, a different measurement on a different element.)
            if (child.scrollHeight > childHeight) {
                childHeight = child.scrollHeight;
            }

            natural += childHeight
                + (parseFloat(childStyle.marginTop) || 0)
                + (parseFloat(childStyle.marginBottom) || 0);
            counted++;
        });

        if (counted > 1) {
            natural += rowGap * (counted - 1);
        }
        return natural;
    }

    function growGridItemToFitContent(instance, grid, itemEl) {
        var node = itemEl.gridstackNode;
        if (!node) {
            return false;
        }
        var content = itemEl.querySelector('.grid-stack-item-content');
        if (!content || !content.clientHeight) {
            return false; // 0 while hidden -- nothing meaningful to measure
        }
        var cellHeight = grid.getCellHeight(true);
        if (!cellHeight) {
            return false;
        }

        // Refuse to measure a grid that has not been laid out yet. GridStack
        // sizes a grid's columns from a ResizeObserver callback, and RO
        // callbacks are delivered AFTER every requestAnimationFrame callback in
        // the same frame -- so even the double-rAF below can land on a freshly
        // built nested grid whose items are still at a degenerate width. That
        // was measured on this page as a 31px-wide field slot instead of
        // ~1254px, which wraps "External Reference ID" onto three lines and
        // reads the field as 94px tall against the 59px it really is. Because
        // growth is one-way, a single such pass permanently over-sizes the
        // field -- and it is exactly why a REBUILD (tab switch) used to produce
        // different, larger card heights than the first load from identical API
        // data. The check is self-calibrating rather than a magic pixel count:
        // an item `w` columns wide in a `getColumn()`-column grid should occupy
        // roughly that fraction of the grid's own width, so anything under half
        // the expected share means the columns are not sized yet.
        // GridStack's per-item margin is a FIXED pixel cost, not a share of
        // the column, so it has to come out of the expectation before the ratio
        // is taken -- otherwise the test tightens as the column narrows and
        // eventually rejects a perfectly good measurement. A w=1 field in a
        // 240px 6-column grid really is only ~20px of content inside a ~40px
        // slot, a ratio of 0.5 that would trip a naive threshold and refuse to
        // measure that field forever (silently UNDER-sizing it, the harder
        // failure to notice). Read the horizontal chrome off the live element,
        // the same self-calibrating trick used for the vertical `chrome` below.
        var gridWidth = grid.el ? grid.el.clientWidth : 0;
        var columns = grid.getColumn();
        if (gridWidth && columns && node.w) {
            var horizontalChrome = Math.max(0, itemEl.offsetWidth - content.clientWidth);
            var expectedWidth = (gridWidth * (node.w / columns)) - horizontalChrome;
            if (expectedWidth > 0 && content.clientWidth < expectedWidth * 0.5) {
                return false;
            }
        }

        // The test above is purely RELATIVE, and that is a hole rather than a
        // nitpick: when a grid has not been laid out yet, the grid and its items
        // are narrow TOGETHER, so the item still occupies its proper share and
        // the check waves the bad measurement straight through. Measured live on
        // this page: the General card's nested grid reported clientWidth 186
        // against its real 1254, the External Reference ID slot 73 against 607
        // -- a share of exactly 1.0, indistinguishable from a settled layout.
        // "External Reference ID" wrapped onto three lines at 73px, the field
        // measured 94px tall against the 59px it really is, and because growth
        // is one-way that 40px of blank space under the card's last field was
        // permanent. It reproduced on roughly two page loads in three.
        //
        // So anchor the sanity check on something that CANNOT be degenerate:
        // the top grid's own element is an ordinary block child of the
        // '<prefix>-canvas' div and has its real width from the first layout,
        // GridStack or not. A nested grid fills the card that owns it, so it
        // should be about that card's share of the top grid -- which stays
        // correct for a half-width card in a side-by-side layout, where a flat
        // fraction of the canvas would refuse to measure that card forever.
        //
        // Refusing is always the safe direction here: every sweep is followed
        // by more (fonts.ready, the ResizeObserver, scheduleAutoFit), so a
        // refusal costs one pass, while a bad measurement is forever.
        //
        // The fixed-chrome subtraction is the same one the guard above makes,
        // and for the same reason: everything between the card's share of the
        // top grid and its nested grid's content box -- GridStack's per-item
        // margin, the card's border, the card body's padding -- is a FIXED
        // pixel cost, not a share of the column. Left in the expectation it
        // makes the test tighten as the card narrows, until a perfectly good
        // measurement is refused: an admin can persist a card at pos_w = 1
        // (normalize_customization_layout_cards() only clamps to max(1, ...)),
        // and a card that narrow is mostly chrome. Every field in it would
        // then be refused on every sweep forever and sit silently clipped at
        // its stored height -- the under-size failure, which is the harder one
        // to notice because nothing on the page says anything is wrong.
        var topGrid = instance.topGrid;
        if (topGrid && topGrid.el && topGrid.el.clientWidth) {
            var cardEl = (grid === topGrid) ? itemEl : grid.el.closest('.grid-stack-item');
            var cardNode = cardEl && cardEl.gridstackNode;
            if (cardNode && cardNode.w && topGrid.getColumn()) {
                var cardChrome = Math.max(0, cardEl.offsetWidth - gridWidth);
                var expectedGridWidth = (topGrid.el.clientWidth * (cardNode.w / topGrid.getColumn())) - cardChrome;
                if (expectedGridWidth > 0 && gridWidth < expectedGridWidth * 0.5) {
                    return false;
                }
            }
        }

        // Self-calibrating rather than hardcoded: whatever the item box has
        // that the content box does not (GridStack's per-item margin, the
        // content element's own border) has to be paid for out of the same
        // row budget. Reading it off the live element means a future change
        // to gridstack.css's margin can't silently under-size a card.
        var chrome = Math.max(0, itemEl.offsetHeight - content.clientHeight);
        var naturalHeight = contentNaturalHeight(content) + chrome;
        var neededRows = Math.ceil(naturalHeight / cellHeight);

        if (neededRows <= node.h) {
            if (instance.growStabilityMap.has(itemEl)) {
                instance.growStabilityMap.delete(itemEl);
                instance.growPendingCount--;
            }
            return false;
        }

        // The width checks above validate THIS item's own slot, but a
        // holder can contain its own deeper CSS grid/flex layout (e.g. the
        // Contributing Risk scoring holder's per-factor summary rows) whose
        // column tracks resolve on their own schedule, independent of the
        // slot's. Caught live: a factor row's formula text ("= 25% x 3 x 5 /
        // 5 (Extreme/Catastrophic)") measured at a column width of ~8px
        // instead of its settled ~143px, wrapping to 560px tall against a
        // real 56px -- inflating this field to 298 rows. Because growth is
        // one-way, that one bad pass stuck even though every later pass (the
        // very next sweep, ~180ms on) measured the row correctly: nothing
        // about the FIELD's own slot was ever unsettled, so the width guards
        // above had nothing to refuse.
        //
        // Requiring the SAME candidate height on two consecutive sweeps
        // before committing it catches this without knowing anything about
        // which holder or layout is involved: a transient mid-layout read
        // does not survive being asked twice, while genuinely new content
        // (an editor loading, a pick adding chips) measures the same on
        // back-to-back sweeps once it stops changing. The 16px tolerance
        // absorbs ordinary sub-pixel/font-metric jitter between two passes
        // of otherwise-identical content (confirmed live: 627px vs 648px for
        // the settled version of the same row above) without coming close to
        // masking a width-collapse spike, which was off by thousands of
        // pixels. Cost is at most one extra sweep interval -- the
        // ResizeObserver-driven sweep alone re-checks every ~120ms.
        //
        // That "the ResizeObserver alone re-checks" assumption is also the
        // one gap in this design: it holds only when SOMETHING keeps
        // resizing after this sweep, giving the observer a reason to fire
        // again. A popup-deferred sweep (autoFitCanvas()'s own popupUnclips
        // guard) can be the ONLY sweep a trigger ever produces -- picking 19
        // Technology options renders every chip while the dropdown is still
        // open (each pick's own scheduleAutoFitSoon() sweep bails out on the
        // open popup before it ever reaches this function), so by the time
        // the popup closes and releaseClipping() runs the sweep it owed, the
        // content is already fully settled and nothing resizes again
        // afterward to give the observer a second event. That first real
        // measurement sets a PENDING entry here and correctly refuses to
        // grow on it alone -- and then waits forever, since no second sweep
        // was ever coming. growPendingCount (autoFitCanvas()'s own comment)
        // is what closes that gap: leaving an entry pending here always
        // means growPendingCount ends the sweep above zero, so autoFitCanvas()
        // schedules the confirming pass itself instead of assuming an event
        // will supply one.
        var lastNatural = instance.growStabilityMap.get(itemEl);
        var hadPending = instance.growStabilityMap.has(itemEl);
        instance.growStabilityMap.set(itemEl, naturalHeight);
        if (lastNatural === undefined || Math.abs(naturalHeight - lastNatural) > 16) {
            if (!hadPending) {
                instance.growPendingCount++;
            }
            return false;
        }

        grid.update(itemEl, { h: neededRows });
        instance.growStabilityMap.delete(itemEl);
        instance.growPendingCount--;
        return true;
    }

    // Companion to growGridItemToFitContent(), grow-only by design for the
    // general content-observer sweep (see that function's own docblock).
    // This one SHRINKS -- deliberately bypassing that guard -- but only in
    // response to one specific, known, discrete event: the CVSS holder's
    // Advanced Metrics accordion collapsing (buildCvssHolder()'s own
    // 'hidden.bs.collapse' handler).
    //
    // Originally assumed the not-yet-laid-out window
    // growGridItemToFitContent()'s width/column sanity checks guard
    // against couldn't apply here, since the canvas has been rendered and
    // interactive for the whole time the accordion was open. Confirmed
    // wrong live on a busier canvas (Submit Risk: more fields/widgets
    // sharing the page, and this collapse can land while an UNRELATED
    // autoFitCanvas() sweep -- triggered by something else changing on the
    // same canvas -- is itself mid-measurement): both the field and card
    // shrank to a few pixels, well under their real content, on a rebuilt
    // page where nothing else should have been unsettled. Growth being
    // one-way is what makes an unsettled measurement there merely
    // self-correct on the next sweep; a shrink has no such safety net, so
    // reuses growGridItemToFitContent()'s own width-degeneracy guards
    // verbatim (same self-calibrating share-of-container checks, same
    // "refuse rather than guess" direction) instead of trusting every
    // call's measurement outright.
    function shrinkGridItemToFitContent(instance, grid, itemEl) {
        var node = itemEl.gridstackNode;
        if (!node) {
            return;
        }
        var content = itemEl.querySelector('.grid-stack-item-content');
        if (!content || !content.clientHeight) {
            return;
        }
        var cellHeight = grid.getCellHeight(true);
        if (!cellHeight) {
            return;
        }

        var gridWidth = grid.el ? grid.el.clientWidth : 0;
        var columns = grid.getColumn();
        if (gridWidth && columns && node.w) {
            var horizontalChrome = Math.max(0, itemEl.offsetWidth - content.clientWidth);
            var expectedWidth = (gridWidth * (node.w / columns)) - horizontalChrome;
            if (expectedWidth > 0 && content.clientWidth < expectedWidth * 0.5) {
                return;
            }
        }

        var topGrid = instance.topGrid;
        if (topGrid && topGrid.el && topGrid.el.clientWidth) {
            var cardEl = (grid === topGrid) ? itemEl : grid.el.closest('.grid-stack-item');
            var cardNode = cardEl && cardEl.gridstackNode;
            if (cardNode && cardNode.w && topGrid.getColumn()) {
                var cardChrome = Math.max(0, cardEl.offsetWidth - gridWidth);
                var expectedGridWidth = (topGrid.el.clientWidth * (cardNode.w / topGrid.getColumn())) - cardChrome;
                if (expectedGridWidth > 0 && gridWidth < expectedGridWidth * 0.5) {
                    return;
                }
            }
        }

        var chrome = Math.max(0, itemEl.offsetHeight - content.clientHeight);
        var neededRows = Math.ceil((contentNaturalHeight(content) + chrome) / cellHeight);
        if (neededRows === node.h) {
            return;
        }
        grid.update(itemEl, { h: neededRows });
    }

    // Both nested field grids AND the top card grid carry float:true (see
    // GridStack.init() calls below), which means a grown item pushes any
    // item below it further down through ordinary collision avoidance, but
    // NOTHING ever floats a later item back up on its own once the pusher
    // shrinks again -- that is what "float" means. reflowCards() solves
    // this for CARDS by re-deriving every card's y from its own tracked
    // DESIGNED position (instance.cardLayout); this is the FIELD-level
    // sibling, for the one shape a field-level shrink can leave broken that
    // cards never see: another field stacked directly below it in the SAME
    // COLUMN of the same nested grid, e.g. NextStep (Review card, left
    // column) sitting right above NextReviewDate. Confirmed live: revealing
    // the Next Step project picker pushes NextReviewDate down as expected,
    // but switching back to any other outcome shrunk NextStep's own item
    // correctly while leaving NextReviewDate stranded at its pushed-down y,
    // a permanent gap between them.
    //
    // Unlike reflowCards(), no separate "designed position" bookkeeping is
    // needed: within one column, items never change relative ORDER (nothing
    // here reorders NextStep and NextReviewDate relative to each other --
    // growth only ever pushes a later item further down), so simply
    // sorting the column's current items by their CURRENT y and restacking
    // them back-to-back from the topmost item's own (untouched) y is
    // exactly equivalent to restoring each one's designed offset from the
    // item above it. x is left alone throughout -- collision avoidance
    // only ever moves the pushed item's y, never its column.
    function reflowFieldColumn(nestedGrid, changedItemEl) {
        var changedNode = changedItemEl.gridstackNode;
        if (!changedNode) {
            return;
        }
        var columnX = changedNode.x;
        var items = nestedGrid.getGridItems().filter(function (el) {
            var n = el.gridstackNode;
            return n && n.x === columnX;
        });
        if (items.length < 2) {
            return;
        }
        items.sort(function (a, b) { return a.gridstackNode.y - b.gridstackNode.y; });

        var cursorY = items[0].gridstackNode.y;
        items.forEach(function (el) {
            var n = el.gridstackNode;
            if (n.y !== cursorY) {
                nestedGrid.update(el, { y: cursorY });
            }
            cursorY += n.h;
        });
    }

    // Re-fits one FIELD item and its owning CARD to their current content in
    // a single, animation-free pass -- shared by every discrete event on this
    // canvas whose height effect can go either way (grow OR shrink) rather
    // than only grow, so the general grow-only sweep (scheduleAutoFit(),
    // above) doesn't fit: the CVSS holder's Advanced Metrics accordion
    // collapsing/expanding, and the Risk Scoring Method dropdown switching
    // between methods whose widgets are very different heights (Classic's
    // handful of fields vs. CVSS's multi-card layout). Both call this with
    // the FIELD's own '.grid-stack-item' -- never a '.grid-stack-item-
    // content' or some other descendant.
    //
    // Disabling animation on both grids first is required, not an
    // optimization: every GridStack instance on this canvas carries the
    // 'grid-stack-animate' class (CSS transition on each item's top/height),
    // so shrinkGridItemToFitContent()'s grid.update() on the FIELD kicks off
    // a CSS-transitioned resize, and reading the CARD's needed height even a
    // frame or two later can still land mid-transition -- confirmed live by
    // instrumenting both calls, where the card-level read consistently saw
    // the field's PRE-resize height and only trimmed one row instead of the
    // ~35 it needed. Disabling animation removes the transition outright, so
    // grid.update() resolves its DOM/layout synchronously and the very next
    // measurement is correct -- no frame-timing to get right, and (unlike an
    // rAF-deferred version) unaffected by a backgrounded/occluded tab
    // suspending requestAnimationFrame.
    function fitFieldAndCardToContent(instance, itemEl) {
        if (!itemEl) {
            return;
        }
        var nestedGrid = instance.nestedGrids.filter(function (g) { return g.el.contains(itemEl); })[0];
        if (!nestedGrid) {
            return;
        }

        nestedGrid.setAnimation(false);
        if (instance.topGrid) {
            instance.topGrid.setAnimation(false);
        }
        shrinkGridItemToFitContent(instance, nestedGrid, itemEl);
        reflowFieldColumn(nestedGrid, itemEl);
        var cardItemEl = nestedGrid.el.closest('.grid-stack-item');
        if (cardItemEl && instance.topGrid) {
            shrinkGridItemToFitContent(instance, instance.topGrid, cardItemEl);
        }
        reflowCards(instance);
        nestedGrid.setAnimation(true);
        if (instance.topGrid) {
            instance.topGrid.setAnimation(true);
        }
    }

    // One full measure-and-grow sweep over the whole canvas.
    //
    // Fields first, then cards: a field that grows makes its card's nested
    // grid taller (GridStack recomputes the nested container's height on
    // update()), and that taller nested grid is what the card measurement
    // then has to see.
    //
    // Cards are swept in the order their SAVED layout designed, and then
    // re-seated by reflowCards() -- never left to GridStack's collision
    // push-down, which only moves a card that a grown card actually OVERLAPS.
    // A card that grows just far enough to meet the next card's saved y
    // without crossing it pushes nothing, so the cards below keep their saved
    // positions while the ones that were pushed do not, and the rendered order
    // stops matching the designed one. Measured live on the default layout
    // (four cards at saved y 0/4/8/12, every one of them pos_h=4): the Scoring
    // card rendered 2nd instead of last on some rebuilds and last on others,
    // from identical API data.
    function autoFitCanvas(instance) {
        if (!instance.topGrid) {
            return;
        }

        // Never measure a field while its popup is open, and this is a
        // correctness requirement rather than an optimisation.
        //
        // contentNaturalHeight()'s `position: absolute` skip only inspects the
        // measured slot's DIRECT children, but a popup is a GRANDCHILD: the
        // multiselect menu lives at
        // span.multiselect-native-select > .btn-group > .multiselect-container.
        // The counted child's scrollHeight therefore includes the whole open
        // menu -- and once a popup is held un-clipped (holdClippingOpen()) that
        // menu renders at its full height rather than being clipped away. Since
        // growth is one-way, a single sweep at that moment is permanent: ONE
        // real click on Team took the slot from 70px to 400px against 54px of
        // actual content, leaving a 346px blank gap forever (224px for a
        // selectize field). It was guaranteed rather than unlucky, because the
        // pick itself schedules a sweep ~120ms later, while the menu is still
        // open.
        //
        // Deferring rather than measuring-around-it is the more robust of the
        // two options: it needs no inventory of which descendants are popups,
        // and it stays correct for any widget added later. Nothing is lost by
        // waiting -- the chips this sweep would size for are behind the open
        // menu anyway, and releaseClipping() runs the owed sweep the moment the
        // last popup closes.
        // Pruning first keeps the suspension self-healing rather than hostage to
        // a close event always arriving: a popup that vanished without one
        // (a widget destroyed from under us, a plugin path that skips its own
        // callback) would otherwise suspend the sweep for the rest of the
        // page's life -- a silent permanent under-size. Safe against a
        // just-opened popup, because Bootstrap adds `.show` in the same
        // synchronous call that fires `show.bs.dropdown`, so no sweep can
        // observe the gap between the two.
        instance.popupUnclips.slice().forEach(function (entry) {
            var popup = entry.popup;
            if (!popup || !popup.isConnected || !popup.getClientRects().length) {
                releaseClipping(instance, popup);
            }
        });

        if (instance.popupUnclips.length) {
            instance.autoFitDeferred = true;
            return;
        }

        // Keep the dirty baseline following the settling widgets until the
        // user first interacts (see formSnapshot()'s docblock).
        if (!instance.userInteracted && instance.dirtySnapshot !== null) {
            instance.dirtySnapshot = formSnapshot(instance);
        }

        instance.autoFitSweeping = true;
        try {
            instance.nestedGrids.forEach(function (nestedGrid) {
                nestedGrid.getGridItems().forEach(function (fieldEl) {
                    observeFieldContent(instance, fieldEl.querySelector('.grid-stack-item-content'));
                    growGridItemToFitContent(instance, nestedGrid, fieldEl);
                });
            });

            instance.cardLayout.forEach(function (card) {
                growGridItemToFitContent(instance, instance.topGrid, card.el);
            });

            reflowCards(instance);
        } finally {
            instance.autoFitSweeping = false;
        }

        // growGridItemToFitContent()'s grow-commit gate needs a SECOND sweep
        // to measure the same candidate height before it grows anything (see
        // that function's own comment) -- ordinarily supplied by the
        // ResizeObserver firing again as content keeps settling. Nothing
        // guarantees that second event will actually come: a sweep this
        // function only reaches once a deferred popup closes (the guard
        // above) can be the LAST resize this canvas ever sees for that
        // content, because everything already finished rendering while the
        // popup was still open and blocking every earlier attempt. Without
        // this, that first, only measurement sits in growStabilityMap
        // forever, unconfirmed -- the field stays clipped at its old height
        // for the rest of the page's life. growPendingCount (own comment on
        // its declaration) is exactly "did this sweep leave anything
        // waiting on a confirmation pass" -- if so, schedule that pass
        // ourselves instead of hoping an event supplies one. Self-limiting:
        // once every pending item either commits or drops out, the count
        // returns to zero and this stops rescheduling itself.
        if (instance.growPendingCount > 0) {
            scheduleAutoFitSoon(instance);
        }
    }

    // Re-seat every card at the position its SAVED layout designed, top to
    // bottom, closing the gaps that growing them opened. This is what makes
    // the rendered order deterministic instead of a by-product of which cards
    // happened to collide (see autoFitCanvas()'s note).
    //
    // Cards sharing a saved y are one ROW and keep sharing a y here, so a
    // side-by-side layout (two w=6 cards at the same y, which the admin layout
    // editor can produce) survives; the row advances by its tallest card.
    //
    // A STAGGERED side-by-side layout -- A at y=0,x=0,w=6 beside B at
    // y=2,x=6,w=6 -- does not survive: B is a row of its own here and drops
    // below A instead of sitting alongside it. That is a deliberate trade, not
    // an oversight. The saved y offsets describe a layout in which every card
    // is the height the admin gave it, and this page's whole premise is that a
    // card grows to whatever its real content needs; once A is free to become
    // taller than its saved height, the 2-row overlap the stagger encodes has
    // no meaning to preserve, and any attempt to keep it either re-opens the
    // overlap-driven reordering reflowCards() exists to end, or silently
    // reintroduces the clipping the growth exists to avoid. Collapsing to a
    // deterministic stack is the honest outcome. Cards the admin placed at the
    // SAME y still share a row, which is how the editor expresses "side by
    // side".
    //
    // Assigning y in designed order never disturbs a card already placed: each
    // row is put immediately below the previous row's bottom, so it can only
    // ever collide with rows not yet placed, and those are assigned their own y
    // straight afterwards. With float:true the grid also never floats anything
    // back up. batchUpdate() keeps GridStack from reflowing between moves.
    function reflowCards(instance) {
        var topGrid = instance.topGrid;
        if (!topGrid || !instance.cardLayout.length) {
            return;
        }

        var rows = [];
        var byY = {};
        // Responsive mode's one-column state (applyResponsiveColumns()):
        // cards that share a saved row cannot sit side by side any more, so
        // every card is its own row, in designed reading order (y, then x).
        var layout = instance.cardLayout;
        if (instance.narrow) {
            layout = layout.slice().sort(function (a, b) {
                return (a.y - b.y) || ((a.x || 0) - (b.x || 0));
            }).map(function (card, index) {
                return { el: card.el, y: index };
            });
        }
        layout.forEach(function (card) {
            if (!card.el.gridstackNode) {
                return;
            }
            if (!byY[card.y]) {
                byY[card.y] = [];
                rows.push(card.y);
            }
            byY[card.y].push(card.el);
        });
        rows.sort(function (a, b) { return a - b; });

        topGrid.batchUpdate();
        try {
            var nextY = 0;
            rows.forEach(function (savedY) {
                var tallest = 0;
                byY[savedY].forEach(function (cardEl) {
                    topGrid.update(cardEl, { y: nextY });
                    tallest = Math.max(tallest, cardEl.gridstackNode.h);
                });
                nextY += tallest;
            });
        } finally {
            topGrid.batchUpdate(false);
        }
    }

    // The sweep has to re-run after the async widgets finish laying
    // themselves out: init_compact_editor() (js/WYSIWYG/editor.js) starts a
    // HugeRTE init that resolves on its own schedule and returns nothing to
    // await, and bootstrap-multiselect/selectize both re-render after their
    // own setup. The sweep is idempotent and grow-only, so simply re-running
    // it on a short bounded schedule converges without any risk of
    // oscillation.
    // The scheduling state these delays drive is per-instance (see
    // getOrCreateInstance()'s autoFitTimers/autoFitFrame/autoFitSoonTimer/
    // autoFitSweeping/autoFitObserver): two live containers must be able to
    // run their own sweeps without cancelling each other's timers.
    var AUTO_FIT_DELAYS = [150, 500, 1500];

    // Growth driven by what the USER does later -- picking 25 Teams wraps 25
    // chips onto new lines inside the field -- is not covered by the bounded
    // post-build schedule above, and it is invisible to an overflow check on
    // the CARD: the field's own fixed-height slot clips it silently, so the
    // card reports nothing. Measured live, 25 real picks overflowed the Team
    // slot by 2412px while the card read 0, and only a later window resize
    // (the one other sweep trigger) recovered it.
    //
    // Observing the field slot itself is useless -- GridStack pins its height,
    // which is precisely why the overflow is invisible. So observe the slot's
    // CHILDREN, whose boxes do change as content wraps. Re-observing on every
    // sweep picks up children a widget created after the field was first
    // rendered; observe() on an already-observed element is a no-op.
    //
    // No feedback loop, and the reason is the grow-only fixed point ALONE: once
    // everything fits, a sweep changes no box, so the observer stops firing.
    //
    // autoFitSweeping is NOT a second line of defence, whatever it looks like.
    // ResizeObserver delivers its callbacks after the current task, by which
    // time the synchronous sweep's `finally` has already cleared the flag -- so
    // it cannot actually suppress the sweep's own resizes, and nothing here may
    // be built on the assumption that it does. It only covers a callback that
    // lands mid-sweep via some other route.
    function scheduleAutoFitSoon(instance) {
        if (instance.autoFitSoonTimer !== null) {
            window.clearTimeout(instance.autoFitSoonTimer);
        }
        instance.autoFitSoonTimer = window.setTimeout(function () {
            instance.autoFitSoonTimer = null;
            autoFitCanvas(instance);
        }, 120);
    }

    function ensureAutoFitObserver(instance) {
        if (instance.autoFitObserver || typeof window.ResizeObserver !== 'function') {
            return;
        }
        instance.autoFitObserver = new window.ResizeObserver(function () {
            if (instance.autoFitSweeping) {
                return;
            }
            scheduleAutoFitSoon(instance);
        });
    }

    function observeFieldContent(instance, content) {
        ensureAutoFitObserver(instance);
        if (!instance.autoFitObserver || !content) {
            return;
        }
        Array.prototype.forEach.call(content.children, function (child) {
            instance.autoFitObserver.observe(child);
        });
    }

    function cancelScheduledAutoFit(instance) {
        instance.autoFitTimers.forEach(function (timer) {
            window.clearTimeout(timer);
        });
        instance.autoFitTimers = [];
        if (instance.autoFitFrame !== null) {
            window.cancelAnimationFrame(instance.autoFitFrame);
            instance.autoFitFrame = null;
        }
        if (instance.autoFitSoonTimer !== null) {
            window.clearTimeout(instance.autoFitSoonTimer);
            instance.autoFitSoonTimer = null;
        }
        if (instance.autoFitObserver) {
            instance.autoFitObserver.disconnect();
        }
    }

    // The FIRST pass must not run on a bare setTimeout(0), and that is
    // load-bearing rather than defensive: growth is one-way, so a single bad
    // measurement is permanent. GridStack sizes a grid's columns from a
    // ResizeObserver callback, which runs during the browser's rendering
    // steps -- after the macrotask a setTimeout(0) queued while buildCanvas()
    // was still running. Measured live on this page, a pass at that moment
    // sees every nested field slot 31px wide instead of ~1254px, which wraps
    // "External Reference ID" onto three lines and reads the field as 94px
    // tall against the 59px it really is -- permanently pinning it at 4 rows.
    // Two chained frames put the first pass after a complete render cycle, so
    // the grid it measures is the laid-out one.
    //
    // document.fonts.ready gates every pass for the same one-way reason: a
    // font swap mid-measurement changes every label's height.
    //
    // Every pass takes the SAME asynchronous route, whether or not the fonts
    // happen to be loaded already. Branching on `fonts.status` used to make the
    // two cases structurally different: on a first load the unresolved
    // fonts.ready promise pushed the pass past the render cycle as a side
    // effect, while on a REBUILD (a tab switch, where the fonts are long since
    // loaded) the same call ran autoFitCanvas() synchronously and measured a
    // nested grid GridStack had not sized yet. Because growth is one-way, that
    // difference was permanent, and it is why rebuilds produced taller and
    // run-to-run inconsistent cards from identical API data (general h=10 on
    // load vs 11-12 on rebuilds). One route for both, plus
    // growGridItemToFitContent()'s own laid-out-yet guard, removes the
    // asymmetry rather than relying on a side effect.
    function runAutoFitWhenFontsReady(instance) {
        var fonts = document.fonts;
        var ready = (fonts && fonts.ready && typeof fonts.ready.then === 'function')
            ? fonts.ready
            : window.Promise.resolve();

        ready.then(function () {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    autoFitCanvas(instance);
                });
            });
        });
    }

    function scheduleAutoFit(instance) {
        cancelScheduledAutoFit(instance);
        instance.autoFitFrame = window.requestAnimationFrame(function () {
            instance.autoFitFrame = window.requestAnimationFrame(function () {
                instance.autoFitFrame = null;
                runAutoFitWhenFontsReady(instance);
            });
        });
        AUTO_FIT_DELAYS.forEach(function (delay) {
            instance.autoFitTimers.push(window.setTimeout(function () {
                runAutoFitWhenFontsReady(instance);
            }, delay));
        });
    }

    // A narrower viewport re-wraps controls (a multiselect's chips, a long
    // label) and can make content that fit a moment ago overflow again.
    //
    // Bound per instance (and unbound by destroy()) rather than once at
    // module-evaluation time: the page-load binding this replaces could only
    // ever serve one canvas, and it could never be released -- a modal that
    // opened, rendered and closed would leave a handler sweeping destroyed
    // GridStack instances on every subsequent viewport change.
    function bindAutoFitResize(instance) {
        $(window).on(instance.resizeNamespace, function () {
            cancelScheduledAutoFit(instance);
            instance.autoFitTimers.push(window.setTimeout(function () {
                runAutoFitWhenFontsReady(instance);
            }, 200));
        });
    }

    // Real shipped sticky action bar (design-system.md #5's ".sr-qactions"
    // button-hierarchy pattern). The Submit Risk page (submitMode 'create',
    // never embedded -- the only caller that reaches this branch, since
    // Mitigation/Review's own coordinators render their own separate Cancel
    // button outside this function) gets all three weights: Reset Form
    // (ghost ".sr-qcancel"), Save & New (outline ".sr-qsave"), Save & View
    // (red fill ".sr-qsend", the highest weight -- this is the SAME submit
    // path/button id ('<prefix>-submit') that already existed before these
    // other two were added, just relabeled). Every other mode keeps the
    // original single-button bar unchanged, sourced from the container's own
    // data-submit-label attribute as before.
    function buildActionsBar(instance, container) {
        var $actions = $('<div>').addClass('sr-qactions');

        if (instance.submitMode === 'create') {
            var $resetBtn = $('<button>')
                .attr('type', 'button')
                .attr('id', instance.idPrefix + '-reset')
                .addClass('btn sr-qcancel')
                .text(_lang['ResetForm'])
                .on('click', function () {
                    resetRiskForm(instance);
                });
            var $saveAndNewBtn = $('<button>')
                .attr('type', 'button')
                .attr('id', instance.idPrefix + '-submit-and-new')
                .addClass('btn sr-qsave')
                .text(_lang['SaveAndNew'])
                .on('click', function () {
                    submitRiskCreateAndNew(instance);
                });
            var $saveAndViewBtn = $('<button>')
                .attr('type', 'button')
                .attr('id', instance.idPrefix + '-submit')
                .addClass('btn sr-qsend')
                .text(_lang['SaveAndView'])
                .on('click', function () {
                    submitRisk(instance);
                });
            $actions.append($resetBtn, $saveAndNewBtn, $saveAndViewBtn);
            return $actions;
        }

        // Cancel (ghost ".sr-qcancel", design-system.md #5's button-
        // hierarchy order -- lowest weight, leftmost) only when the caller
        // opted in via onCancel (see init()'s own comment on instance.
        // onCancel for why this is opt-in rather than unconditional).
        // risk-view-details.js is the first/only caller today: it used to
        // render its own separate Cancel button floating top-right of the
        // mount instead of in this bar -- consolidated into the one spot
        // design-system.md actually specifies for it.
        if (instance.onCancel) {
            var $cancelBtn = $('<button>')
                .attr('type', 'button')
                .attr('id', instance.idPrefix + '-cancel')
                .addClass('btn sr-qcancel')
                .text(_lang['Cancel'])
                .on('click', function () {
                    instance.onCancel();
                });
            $actions.append($cancelBtn);
        }

        var submitLabel = container.data('submit-label') || 'Submit';
        var $submitBtn = $('<button>')
            .attr('type', 'button')
            .attr('id', instance.idPrefix + '-submit')
            .addClass('btn sr-qsend')
            .text(submitLabel)
            .on('click', function () {
                submitRisk(instance);
            });
        $actions.append($submitBtn);
        return $actions;
    }

    // Whether the form currently holds any entered data -- used to decide if
    // Reset Form needs to confirm before discarding it. Checked against the
    // SAME FormData snapshot the real submit path takes (after
    // force_save_all_editors() syncs any open WYSIWYG editor back to its
    // textarea first), so "dirty" means "would actually submit something",
    // not just "some control's default value looks non-empty".
    function formHasAnyValue(formEl) {
        if (typeof force_save_all_editors === 'function') {
            force_save_all_editors();
        }

        var isDirty = false;
        new FormData(formEl).forEach(function (value) {
            if (isDirty) {
                return;
            }
            if (value instanceof File) {
                isDirty = value.size > 0 || value.name !== '';
            } else {
                isDirty = String(value).trim() !== '';
            }
        });
        return isDirty;
    }

    // Reset Form (Submit Risk page only, submitMode 'create'). An empty form
    // clears silently; a form with any entered data confirms first via
    // #reset-risk-form-modal (management/index.php, design-system.md #8's
    // "Confirm" type -- reversible-but-worth-a-beat, Esc/backdrop allowed).
    // The actual reset is a full destroy()+init() of the instance rather than
    // manually clearing each widget -- the same "tear down and rebuild clean"
    // approach every other coordinator in this project already uses for its
    // own Cancel flow, and the only reliable way to reset GridStack/selectize/
    // bootstrap-multiselect/WYSIWYG state together.
    // Reset Form / Save & New rebuild a fresh, empty create form. They have
    // always re-initialised with {embedded: false} only; the record-type
    // options (profile, actionsBar, responsive) now carry over too, so a
    // non-risk form stays that record type. For every risk caller these are
    // unset, so the options are exactly {embedded: false} as before.
    function preservedReinitOptions(instance) {
        var previous = instance.initOptions || {};
        var options = { embedded: false };
        ['profile', 'actionsBar', 'responsive', 'responsiveBreakpoint'].forEach(function (key) {
            if (previous[key] !== undefined) {
                options[key] = previous[key];
            }
        });
        return options;
    }

    function resetRiskForm(instance) {
        var formEl = document.getElementById(instance.idPrefix + '-form');
        if (!formEl) {
            return;
        }

        function doReset() {
            var containerSelector = instance.containerSelector;
            var reinitOptions = preservedReinitOptions(instance);
            destroy(containerSelector);
            init(containerSelector, reinitOptions);
        }

        if (!formHasAnyValue(formEl)) {
            doReset();
            return;
        }

        var $modal = $('#reset-risk-form-modal');
        if ($modal.length === 0) {
            // Defensive only -- the modal ships unconditionally on
            // management/index.php, the only page this branch is reachable
            // from. Never silently discard data if it somehow isn't there.
            return;
        }
        $modal.off('click.resetRiskForm', '#reset-risk-form-discard')
            .on('click.resetRiskForm', '#reset-risk-form-discard', function () {
                $modal.modal('hide');
                doReset();
            });
        $modal.modal('show');
    }

    // The embedded counterpart of the sticky action bar above.
    //
    // A modal caller does NOT submit through submitRisk() -- it submits
    // through the delegated `$('body').on('click', '.save-risk-form', ...)`
    // handler that already ships in js/simplerisk/pages/risk.js, and drives it
    // from its own modal footer button with a .trigger('click'). That handler
    // needs two real things to exist inside the rendered DOM:
    //
    //   * a `.save-risk-form` element to click -- addRisk($this) collects the
    //     body with `$this.closest('form')`;
    //   * a `.tab-data` ancestor -- addRisk() scopes its required-field
    //     validation with `$this.closest('.tab-data')`, and without it
    //     checkAndSetValidation() sees an empty scope and silently skips the
    //     client-side required-field highlighting. (Server-side validation
    //     still runs either way, so this is a client-side UX correctness
    //     issue, not a data-integrity one -- but it has to work.)
    //
    // renderTabs() puts `tab-data` on the <form> itself when embedded, so a
    // single element satisfies both `.closest()` calls.
    //
    // The button is never shown and deliberately carries NO click handler of
    // its own -- risk.js's delegated handler is the only thing that should
    // react to it. It hides through Bootstrap's own `d-none` utility class
    // alone, so no caller has to ship a page-specific rule to keep it hidden.
    // No reset control: the standalone page has no reset either, and the modal
    // callers' footer proxies already tolerate its absence.
    function buildEmbeddedSubmitAffordance() {
        return $('<div>').addClass('risk-form-actions d-none').append(
            $('<button>').attr('type', 'button').addClass('btn btn-submit save-risk-form')
        );
    }

    // Real, shipped tab component -- design-system.md §12's "Tabs" pattern
    // (underline style: active tab = charcoal text + 2px App Red underline).
    // .sr-tabs/.sr-tab/.sr-tab-count/.is-active already ship in
    // scss/modules/_tabs.scss and are the same markup admin/data_integrity.php
    // + js/simplerisk/pages/data-integrity.js use -- reused here rather than
    // inventing new class names.
    //
    // With `instance.pinnedTemplateGroupId` set the tab bar is omitted
    // entirely and the pinned group is the only one ever loaded. The tab bar
    // belongs to the CREATE flow, where picking a template group is part of
    // authoring a new risk. An EXISTING risk already has one, stored on the
    // row, and its custom-field values are resolved against it -- offering a
    // switcher mid-edit would let the user swap the field roster out from
    // under values that cannot follow it.
    //
    // Also omitted with a single group -- whether that's because the
    // Customization Extra is off (the no-Extra fallback always synthesizes
    // exactly one implicit "Default" group) or on with just the stock
    // Default template and no customer-created ones, which is most installs.
    // A tab bar with one tab offers no actual choice, just chrome -- and
    // `groups.length > 0` further down still auto-loads that single group
    // with no tab click needed to trigger it, so nothing else changes.
    function renderFormShell(container, groups, instance) {
        var tabBar = null;
        var showTabBar = !instance.pinnedTemplateGroupId && groups.length > 1;

        if (showTabBar) {
            tabBar = $('<div>').addClass('sr-tabs').attr('id', instance.idPrefix + '-tabs');

            groups.forEach(function (group, index) {
                var tab = $('<button>')
                    .attr('type', 'button')
                    .attr('data-template-group-id', group.id)
                    .addClass('sr-tab')
                    .toggleClass('is-active', index === 0)
                    .text(group.name);
                tabBar.append(tab);
            });

            tabBar.on('click', '.sr-tab', function () {
                var templateGroupId = $(this).attr('data-template-group-id');
                tabBar.find('.sr-tab').removeClass('is-active');
                $(this).addClass('is-active');
                loadTemplateGroup(instance, templateGroupId);
            });
        }

        // Every rendered field control lives inside this real <form> --
        // submitRisk() reads it back with a plain `new FormData(formEl)`
        // (the same mechanism the legacy addRisk() handler in
        // js/simplerisk/pages/risk.js used), which is what makes
        // multi-selects, selectize <select multiple>s, and the
        // SupportingDocumentation multi-file input serialize correctly
        // without hand-rolling per-widget-type collection logic here.
        // preventDefault on 'submit' guards against an accidental native
        // form submission (e.g. Enter in a text field) navigating the page.
        var $form = $('<form>')
            .attr('id', instance.idPrefix + '-form')
            // `tab-data` only when embedded -- it is what scopes
            // risk.js's addRisk() validation. See
            // buildEmbeddedSubmitAffordance(). The standalone page submits
            // through submitRisk() instead and must not opt into risk.js's
            // delegated handler.
            .toggleClass('tab-data', instance.embedded)
            .on('submit', function (e) { e.preventDefault(); });

        // Optional: lets an embedded caller give the rendered form a stable
        // `name` to address it by, alongside the derived id.
        if (instance.formName) {
            $form.attr('name', instance.formName);
        }

        $form.append(
            $('<input>').attr({ type: 'hidden', id: instance.idPrefix + '-template-group-id', name: 'template_group_id' })
        );
        if (tabBar) {
            $form.append(tabBar);
        }
        // Without a tab bar the canvas is the form's first visible child, but
        // needs no extra top spacing of its own: GridStack's per-card 10px
        // margin (buildCanvas()'s topGrid, `margin: '10px 0'`) plus whatever
        // the breadcrumb/modal header above already contributes lands at the
        // exact same gap the tab-bar version has (measured live: 32px
        // either way) -- adding more here would only widen it past that.
        $form.append($('<div>').attr('id', instance.idPrefix + '-canvas'));
        // actionsBar:false -- the host (a modal footer) drives save/cancel
        // through RiskDetailsForm.submit(), so neither the sticky bar nor the
        // hidden risk.js affordance is rendered.
        if (instance.actionsBar !== false) {
            $form.append(instance.embedded ? buildEmbeddedSubmitAffordance() : buildActionsBar(instance, container));
        }

        container.empty().append($form);

        if (instance.pinnedTemplateGroupId) {
            loadTemplateGroup(instance, instance.pinnedTemplateGroupId);
        } else if (groups.length > 0) {
            loadTemplateGroup(instance, groups[0].id);
        }
    }

    function loadTemplateGroup(instance, templateGroupId) {
        instance.currentTemplateGroupId = templateGroupId;
        $('#' + instance.idPrefix + '-template-group-id').val(templateGroupId);

        // Captured before the request goes out and re-checked on the way back:
        // a tab switch (or a destroy()/init() rebuild) while these two
        // requests are in flight would otherwise render the OLD group's fields
        // over the new one's canvas. See init().
        var generation = instance.generation;
        var endpoints = resolveProfile(instance.profile).endpoints;

        $.when(
            fetchJSON(endpoints.fields, { template_group_id: templateGroupId, tab_index: instance.tabIndex }),
            fetchJSON(endpoints.layout, { template_group_id: templateGroupId, tab_index: instance.tabIndex })
        ).done(function (fields, cards) {
            if (instance.generation !== generation) {
                return;
            }
            buildCanvas(instance, fields, cards);
        }).fail(function () {
            if (instance.generation !== generation) {
                return;
            }
            showAlertFromMessage(_lang['RequestFailed'], false);
        });
    }

    // ------------------------------------------------------------------
    // Submission -- the STANDALONE PAGE's path only (embedded: false).
    //
    // This is genuinely how the standalone Submit Risk page submits today: the
    // sticky action bar buildActionsBar() renders wires its one button
    // straight to this function. It is NOT a duplicate of risk.js's
    // `.save-risk-form` delegated handler -- they are two independent paths
    // for two different consumers, and js/simplerisk/pages/risk.js is not even
    // loaded on management/index.php.
    //
    // Embedded (modal) callers never reach this function: they get
    // buildEmbeddedSubmitAffordance()'s hidden `.save-risk-form` button
    // instead, and submit through risk.js's handler. Wiring both would give a
    // modal two competing submission paths for one click.
    //
    // Serializes every rendered field plus the hidden
    // template_group_id into POST /risks (addRisk(), api/v2/index.php),
    // then navigates to the new risk's detail page -- the same
    // management/view.php?id=<risk_id> target the legacy handler's own
    // client-side consumer (addRisk() in js/simplerisk/pages/risk.js,
    // reading response.data.risk_id) redirects to on success. The old
    // inline `<script>var global_risk_id=...;</script>` the pre-redesign
    // management/index.php echoed was dead output: it was emitted BEFORE
    // that handler's own ob_end_clean(), which discards it before
    // json_response() ever writes the real body -- risk.js never read that
    // global, it read the JSON envelope's data.risk_id, same as here.
    // ------------------------------------------------------------------
    // The jQuery.ajax() settings for one submission of this instance's form,
    // built from the profile's submit adapter. For the risk profile this is
    // byte-for-byte the request each path below always sent:
    //   create            POST  /api/v2/risks          multipart FormData
    //   update            PATCH instance.updateUrl || /api/v2/risks/{id}
    //                                                  urlencoded, with empty
    //                                                  multi-value markers
    //   create-log-entry  POST  instance.createUrl     urlencoded (caller-
    //                                                  supplied URL; not
    //                                                  profile-driven)
    // `mode` overrides instance.submitMode (Save & New posts a create).
    function buildSubmitRequest(instance, formEl, mode) {
        mode = mode || instance.submitMode;
        var adapter = resolveProfile(instance.profile).submit;
        var url;
        var method;
        var encode;

        if (mode === 'create-log-entry') {
            url = instance.createUrl;
            method = 'POST';
            encode = 'urlencoded';
        } else if (mode === 'update') {
            url = instance.updateUrl
                || (typeof adapter.updateUrl === 'function' ? adapter.updateUrl(instance.updateRiskId) : null);
            method = perModeSetting(adapter.method, 'update', 'PATCH');
            encode = perModeSetting(adapter.encode, 'update', 'urlencoded');
        } else {
            url = adapter.createUrl;
            method = perModeSetting(adapter.method, 'create', 'POST');
            encode = perModeSetting(adapter.encode, 'create', 'multipart');
        }

        // Fail closed (non-risk profiles only -- the risk profile always has
        // both URLs): no URL for this mode means no request at all.
        if (!url) {
            warnOnce('missing-url-' + mode, 'the profile has no submit URL for mode "' + mode + '"; nothing was sent.');
            return null;
        }

        var payload = (mode !== 'create-log-entry' && typeof adapter.serialize === 'function')
            ? adapter.serialize($(formEl), { mode: mode })
            : null;

        // A plain-object result's `clear` list is pulled out of the body and
        // sent as explicit empty markers (see the profile docblock). An
        // object that held nothing but `clear` keeps the default body.
        var clearNames = [];
        if (payload && $.isPlainObject(payload) && Array.isArray(payload.clear)) {
            clearNames = payload.clear;
            payload = $.extend({}, payload);
            delete payload.clear;
            if ($.isEmptyObject(payload)) {
                payload = null;
            }
        }

        var request = { type: method, url: BASE_URL + url };
        if (encode === 'multipart') {
            request.data = (payload instanceof FormData) ? payload : new FormData(formEl);
            clearNames.forEach(function (name) {
                request.data.append(name, '');
            });
            request.cache = false;
            request.contentType = false;
            request.processData = false;
        } else if (encode === 'json') {
            var body = (payload !== null && payload !== undefined) ? payload : formToObject(formEl);
            if (typeof body !== 'string') {
                clearNames.forEach(function (name) {
                    if (name.slice(-2) === '[]') {
                        body[name.slice(0, -2)] = [];
                    } else {
                        body[name] = '';
                    }
                });
            }
            request.data = (typeof body === 'string') ? body : JSON.stringify(body);
            request.cache = false;
            request.contentType = 'application/json';
            request.processData = false;
            // csrf-magic's XHR hook skips JSON bodies and PHP never parses
            // one into $_POST, so the token has to travel in the header
            // csrf_check() also accepts (same as manage-assets.js /
            // data-integrity.js's own JSON callers).
            request.headers = { 'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '' };
        } else {
            if (payload === null || payload === undefined) {
                request.data = serializeWithEmptyMultiValueMarkers(formEl);
            } else {
                request.data = (typeof payload === 'string') ? payload : $.param(payload);
            }
            if (clearNames.length) {
                var markers = clearNames.map(function (name) { return encodeURIComponent(name) + '='; }).join('&');
                request.data = request.data ? request.data + '&' + markers : markers;
            }
            request.cache = false;
        }
        return request;
    }

    // The 'json' encoding's default body: every named control, with a
    // '[]'-suffixed name collected into an array (present-but-empty when a
    // multi-select has nothing picked, the same "empty clears" convention
    // serializeWithEmptyMultiValueMarkers() applies to the urlencoded body).
    function formToObject(formEl) {
        var body = {};
        $(formEl).serializeArray().forEach(function (pair) {
            if (pair.name.slice(-2) === '[]') {
                var key = pair.name.slice(0, -2);
                (body[key] = body[key] || []).push(pair.value);
            } else {
                body[pair.name] = pair.value;
            }
        });
        $(formEl).find('select[multiple]').each(function () {
            if (this.name && this.name.slice(-2) === '[]') {
                var key = this.name.slice(0, -2);
                body[key] = body[key] || [];
            }
        });
        return body;
    }

    // The built-in buttons' send. A fail-closed profile (no URL for this
    // mode, see buildSubmitRequest()) yields an already-rejected promise, so
    // each caller's own fail/always handlers re-enable its button and show
    // the generic failure toast -- nothing is ever sent to a fallback URL.
    function sendSubmitRequest(instance, formEl, mode) {
        var request = buildSubmitRequest(instance, formEl, mode);
        if (!request) {
            return $.Deferred().reject({}).promise();
        }
        return $.ajax(request);
    }

    function showSubmitFailure(xhr) {
        if (xhr.responseJSON && xhr.responseJSON.status_message) {
            showAlertsFromArray(xhr.responseJSON.status_message, false);
        } else {
            showAlertFromMessage(_lang['RequestFailed'], false);
        }
    }

    function submitRisk(instance) {
        var $submitBtn = $('#' + instance.idPrefix + '-submit');
        if ($submitBtn.prop('disabled')) {
            return; // already submitting
        }

        var formEl = document.getElementById(instance.idPrefix + '-form');
        if (!formEl) {
            return;
        }

        // HugeRTE (RiskAssessment/AdditionalNotes) keeps its content in its
        // own editor instance and only writes back to the underlying
        // <textarea> on an explicit save -- force_save_all_editors()
        // (js/WYSIWYG/helpers.js, loaded via this page's 'WYSIWYG' script
        // token) triggers that write-back for every active editor before
        // the FormData snapshot below is taken.
        if (typeof force_save_all_editors === 'function') {
            force_save_all_editors();
        }

        // Client-side required-field validation, for BOTH submit modes.
        // Previously this only ran on the embedded (modal) path, via
        // risk.js's own '.save-risk-form' handler -- this standalone-page
        // path never called it, which is exactly the gap
        // project_add_risk_modal_phase3_followups.md's Item 2 tracked as a
        // Phase 3 followup ("standalone Submit Risk page skips client-side
        // required-field validation"). Adding it here closes that gap for
        // the standalone Submit Risk page AND this task's new update-mode
        // caller in one place, since both paths call this same function.
        if (typeof checkAndSetValidation === 'function' && !checkAndSetValidation($(formEl))) {
            return;
        }

        $submitBtn.prop('disabled', true);
        $.blockUI({ message: '<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>', baseZ: '10001' });

        if (instance.submitMode === 'update') {
            submitRiskUpdate(instance, formEl, $submitBtn);
        } else if (instance.submitMode === 'create-log-entry') {
            submitRiskCreateLogEntry(instance, formEl, $submitBtn);
        } else {
            submitRiskCreate(instance, formEl, $submitBtn);
        }
    }

    // The ORIGINAL create path -- POST /api/v2/risks, multipart (file
    // upload support), navigates to the new risk's detail page on success.
    // Unchanged from before this task except for being split out of
    // submitRisk() so update mode does not share its multipart/navigation
    // specifics.
    function submitRiskCreate(instance, formEl, $submitBtn) {
        sendSubmitRequest(instance, formEl, 'create').done(function (response) {
            // No success toast here on purpose. addRisk() returns its localized
            // success message ($lang['RiskSubmitSuccess']) in the response body
            // for the benefit of API callers, but it ALSO queues the same
            // message in the SESSION via set_alert(), and that session copy is
            // what management/view.php renders once we land on it. A toast
            // raised here would be destroyed by the navigation on the very next
            // line before anyone could read it, and would then be shown a
            // second time by the page we navigate to.
            var riskId = response && response.data ? response.data.risk_id : null;
            window.onbeforeunload = null;
            window.location.href = BASE_URL + '/management/view.php' + (riskId ? ('?id=' + riskId) : '');
        }).fail(function (xhr) {
            // addRisk()'s failure paths are NOT uniform: the alert-driven ones
            // (max tag length, invalid Jira issue key, upload failure, and the
            // submit_risk() failure) return get_alert(true), which is a
            // JSON-ENCODED ARRAY of {alert_type, alert_message} objects, while
            // others return a plain escaped string. showAlertsFromArray()
            // (js/simplerisk/alert-helper.js, loaded via footer.php ->
            // setup_alert_requirements()) is the helper that handles exactly
            // this dual shape: it JSON.parse()s and falls back to
            // showAlertFromMessage() when the payload isn't an alert array.
            // Passing the raw value to showAlertFromMessage() renders the JSON
            // itself in the toast.
            if (xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message, false);
            } else {
                showAlertFromMessage(_lang['RequestFailed'], false);
            }
            $submitBtn.prop('disabled', false);
        }).always(function () {
            $.unblockUI();
        });
    }

    // Save & New (Submit Risk page only, submitMode 'create'). Same POST as
    // submitRiskCreate() (the Save & View path), but does NOT navigate --
    // there is no destination page to carry addRisk()'s session-queued
    // success alert this time, so the response body's own status_message is
    // raised as a toast directly. On success the instance is torn down and
    // re-initialized fresh (matching resetRiskForm()'s own approach) so the
    // next risk starts from a genuinely blank form, not the just-submitted
    // one's leftover values.
    function submitRiskCreateAndNew(instance) {
        var $btn = $('#' + instance.idPrefix + '-submit-and-new');
        if ($btn.prop('disabled')) {
            return; // already submitting
        }

        var formEl = document.getElementById(instance.idPrefix + '-form');
        if (!formEl) {
            return;
        }

        if (typeof force_save_all_editors === 'function') {
            force_save_all_editors();
        }

        if (typeof checkAndSetValidation === 'function' && !checkAndSetValidation($(formEl))) {
            return;
        }

        $btn.prop('disabled', true);
        $.blockUI({ message: '<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>', baseZ: '10001' });

        var containerSelector = instance.containerSelector;

        sendSubmitRequest(instance, formEl, 'create').done(function (response) {
            var message = (response && response.status_message) ? response.status_message : _lang['RiskSubmitSuccess'];
            showAlertFromMessage(message, true);
            var reinitOptions = preservedReinitOptions(instance);
            destroy(containerSelector);
            init(containerSelector, reinitOptions);
        }).fail(function (xhr) {
            // Same dual-shape handling as submitRiskCreate()'s fail handler --
            // see its own comment for the full reasoning.
            if (xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message, false);
            } else {
                showAlertFromMessage(_lang['RequestFailed'], false);
            }
            $btn.prop('disabled', false);
        }).always(function () {
            $.unblockUI();
        });
    }

    // A <select multiple> with nothing selected submits NO key at all, so a
    // user who empties Team / Site Location / Stakeholders / Technology / Tags /
    // Risk or Threat Mapping down to zero selections produces a body in which
    // that field is simply absent. update_risk($id, true) reads absent as
    // "the caller did not name this field, leave it alone" -- correct for an
    // external PATCH caller, but here it silently discards the user's
    // deliberate clearing, and the old values come straight back on reload.
    //
    // Sending the field as present-but-empty is what says "clear this": the
    // server's own convention is that an explicit empty value clears while an
    // absent one preserves. Same fix, same reason, as risk.js's updateRisk()
    // on the legacy FormData path ("if (!form.has(obj.name)) form.append(...)",
    // js/simplerisk/pages/risk.js) -- expressed here against serialize()'s
    // urlencoded string instead of a FormData object.
    //
    // `select[multiple]` is exactly the set of multi-value controls this engine
    // renders (buildMultiselect() and buildSelectizeControl(..., true) both
    // produce one; bootstrap-multiselect and selectize each keep that original
    // select in the DOM and in sync, which is what serialize() reads). It also
    // naturally excludes the one other MULTI_VALUE_WIDGETS entry, `file`, whose
    // control is an <input type="file"> -- update mode never renders one, and an
    // empty marker for it would mean something quite different to the server.
    function serializeWithEmptyMultiValueMarkers(formEl) {
        var serialized = $(formEl).serialize();
        var markers = [];

        $(formEl).find('select[multiple]').each(function () {
            var name = this.name;
            if (!name || name.slice(-2) !== '[]') {
                return;
            }
            var value = $(this).val();
            if (value && value.length) {
                return; // something is selected; serialize() already carried it
            }
            markers.push(encodeURIComponent(name) + '=');
        });

        if (!markers.length) {
            return serialized;
        }
        return serialized ? serialized + '&' + markers.join('&') : markers.join('&');
    }

    // The Details-tab edit path this task adds -- PATCH /api/v2/risks/{id} by
    // default, urlencoded (NOT multipart: see the file-widget exclusion above
    // and this module's file-level docs). serialize() naturally excludes any
    // file input's content (jQuery's own documented behavior), which is
    // harmless here since update mode never renders one. Calls the caller's
    // onSaveSuccess instead of navigating -- the coordinator owns what
    // happens next (tear this instance down, re-fetch values, show read mode
    // again).
    //
    // `instance.updateUrl` (Phase 4b-iii) lets a caller target a DIFFERENT
    // resource than the risk itself -- the Mitigation tab's coordinator
    // (risk-view-mitigation.js) sets it to '/api/v2/risks/{id}/mitigations'
    // (saveMitigation(), includes/api.php), which is a genuinely different
    // endpoint from updateRisk(). Without this override every 'update' caller
    // would PATCH the risk, silently no-op every mitigation-only field name
    // (planning_strategy, mitigation_controls, ...) since updateRisk() simply
    // ignores keys it does not recognize -- confirmed live: the Mitigation
    // edit form submitted successfully (200) but never persisted anything
    // before this fix. Falls back to the original risk URL when unset, so
    // every pre-4b-iii caller (Details tab) is unaffected.
    function submitRiskUpdate(instance, formEl, $submitBtn) {
        sendSubmitRequest(instance, formEl, 'update').done(function (response) {
            if (instance.onSaveSuccess) {
                instance.onSaveSuccess(response);
            }
        }).fail(function (xhr) {
            if (xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message, false);
            } else {
                showAlertFromMessage(_lang['RequestFailed'], false);
            }
        }).always(function () {
            $submitBtn.prop('disabled', false);
            $.unblockUI();
        });
    }

    // The Review-tab submit path (Phase 4c-ii) -- POST to a caller-supplied
    // createUrl (saveReview(), /api/v2/risks/{id}/reviews), urlencoded, same
    // as submitRiskUpdate()'s multipart exclusion reasoning (this form never
    // renders a file input). Unlike BOTH other submit modes: never navigates
    // (like 'update'), but also never targets a fixed per-instance resource
    // id the way 'update' targets updateRiskId -- the caller supplies the
    // full createUrl directly, since this mode has no risk-scoped "the
    // record I am updating" concept to derive a URL from (submitting a
    // review always CREATES a new mgmt_reviews row; there is no id to PATCH).
    function submitRiskCreateLogEntry(instance, formEl, $submitBtn) {
        sendSubmitRequest(instance, formEl, 'create-log-entry').done(function (response) {
            if (instance.onSaveSuccess) {
                instance.onSaveSuccess(response);
            }
        }).fail(function (xhr) {
            if (xhr.responseJSON && xhr.responseJSON.status_message) {
                showAlertsFromArray(xhr.responseJSON.status_message, false);
            } else {
                showAlertFromMessage(_lang['RequestFailed'], false);
            }
        }).always(function () {
            $submitBtn.prop('disabled', false);
            $.unblockUI();
        });
    }

    // Tears down the previous tab's GridStack instances (and HugeRTE editor
    // instances -- see activeEditorIds above) before the DOM nodes they're
    // attached to are removed. GridStack.destroy(false) unbinds its own
    // event handlers/observers without touching the DOM -- the caller's
    // canvasEl.empty() (buildCanvas()) owns the actual node removal.
    // destroy_editor() (js/WYSIWYG/helpers.js, loaded globally via
    // header.php) is the app's own shared "destroy one editor by id" helper
    // -- reused here rather than hand-rolling the same hugerte call. It has
    // no existence guard of its own (hugerte.get(id).destroy() throws on a
    // stale/missing id), so the guard stays here.
    function destroyCanvas(instance) {
        // Before the grids go: a pending sweep would otherwise fire against
        // destroyed GridStack instances / detached nodes after a tab switch.
        cancelScheduledAutoFit(instance);
        // A popup open at teardown would otherwise leave its inline
        // `overflow: visible` on elements about to be discarded, and leave the
        // bookkeeping pointing at detached nodes.
        instance.autoFitDeferred = false;
        releaseAllPopupClipping(instance);

        instance.activeEditorIds.forEach(function (id) {
            if (typeof hugerte !== 'undefined' && hugerte.get(id)) {
                destroy_editor(id);
            }
        });
        instance.activeEditorIds = [];
        instance.editorBaselines = {};
        instance.dirtySnapshot = null;

        instance.nestedGrids.forEach(function (grid) {
            grid.destroy(false);
        });
        instance.nestedGrids = [];
        instance.cardLayout = [];
        if (instance.topGrid) {
            instance.topGrid.destroy(false);
            instance.topGrid = null;
        }
    }

    function buildCanvas(instance, fields, cards) {
        destroyCanvas(instance);

        var canvasEl = $('#' + instance.idPrefix + '-canvas');
        canvasEl.empty();

        var fieldsByCard = {};
        fields.forEach(function (field) {
            var key = field.card_key || 'custom_fields';
            (fieldsByCard[key] = fieldsByCard[key] || []).push(field);
        });

        var topGridEl = $('<div>').addClass('grid-stack sr-qform-top-grid');
        canvasEl.append(topGridEl);

        var maps = resolveProfile(instance.profile).maps;
        var cardIcons = maps.cardIcons || {};
        var cardLabels = maps.cardLabels || {};

        var topGrid = GridStack.init({
            column: 12,
            cellHeight: TOP_CELL_HEIGHT,
            float: true,
            // '<top/bottom> <left/right>' -- GridStack's own default margin
            // is 10px on ALL FOUR sides of every item (--gs-item-margin-*),
            // which is right for the vertical gap BETWEEN stacked cards but
            // wrong on the left/right: every top-level card is a single
            // full-width column (never side-by-side with a sibling), so that
            // horizontal margin only ever insets the card's own border 10px
            // from #submit-risk-container's edge, misaligning it against
            // every other (non-GridStack) element on the page that sits
            // flush with that same container. Left as GridStack's own
            // default on the NESTED per-card grid below (nestedGrid) --
            // fields WITHIN a card genuinely sit side-by-side and need that
            // horizontal gap.
            margin: '10px 0',
            staticGrid: true // read-only: this reproduces the saved layout,
                              // never an editor -- no drag, resize, or
                              // accept-widgets here.
        }, topGridEl[0]);
        instance.topGrid = topGrid;

        cards.forEach(function (card) {
            // Fields that draw nothing are dropped here, not given an empty
            // slot -- see packRenderableFields(). A card left with no drawable
            // field is therefore as invisible as a card with no field at all,
            // which matches customization-layout-editor.js's own
            // computed-not-stored empty-card visibility rule.
            var cardFields = packRenderableFields(fieldsByCard[card.card_key] || [], instance.profile);
            if (cardFields.length === 0) {
                return;
            }

            // Build the real card DOM off-tree, then move its CHILDREN into
            // GridStack's own auto-created .grid-stack-item-content div --
            // never pass a markup string as addWidget({content: ...}):
            // GridStack's default renderCB does `el.textContent = w.content`,
            // so a string renders as literal escaped text, not real DOM. This
            // mirrors buildCard()'s corrected `.append(widgetHtml.contents())`
            // pattern in customization-layout-editor.js.
            var widgetHtml = $('<div>').addClass('sr-qcard')
                .append($('<div>').addClass('sr-qcard-head')
                    .append($('<span>').addClass('sr-qcard-ico')
                        .append($('<i>').addClass('fa ' + (cardIcons[card.card_key] || 'fa-square'))))
                    .append($('<span>').addClass('sr-qcard-htext')
                        .append($('<h2>').text(cardLabels[card.card_key] || card.card_key))))
                .append($('<div>').addClass('sr-qcard-body')
                    .append($('<div>').addClass('grid-stack sr-qform-nested-grid').attr('data-card-key', card.card_key)));

            var item = topGrid.addWidget({
                x: card.pos_x,
                y: card.pos_y * LAYOUT_ROW_SCALE,
                w: card.pos_w,
                h: card.pos_h * LAYOUT_ROW_SCALE,
                id: card.card_key
            });
            $(item).attr('data-card-key', card.card_key);
            $(item).find('.grid-stack-item-content').addClass('sr-qcard').append(widgetHtml.contents());
            // The card's DESIGNED position, kept for reflowCards(). Read from
            // the API row rather than the live node, whose y moves as cards
            // grow -- and left in the saved layout's own units, because
            // reflowCards() only ever uses it to GROUP cards into rows and to
            // order those rows, never as a position it assigns.
            instance.cardLayout.push({ el: item, y: card.pos_y, x: Number(card.pos_x) || 0 });

            var nestedGrid = GridStack.init({
                column: 6,
                cellHeight: NESTED_CELL_HEIGHT,
                float: true,
                staticGrid: true
            }, $(item).find('.sr-qform-nested-grid')[0]);
            instance.nestedGrids.push(nestedGrid);

            // Every field -- required or optional -- renders through the SAME
            // path, into this card's per-field GridStack at its own designed
            // pos_x/pos_y/pos_w/pos_h. Both layout sources already supply real
            // positions for every field regardless of `required`
            // (get_active_fields() in extras/customization/index.php, and
            // api_synthesize_no_extra_risk_fields() in
            // api/v2/includes/api.php), and the admin layout editor positions
            // all of them on one nested grid -- so the old required-visible /
            // optional-collapsed split was a rendering-only choice layered on
            // top of a fully-positioned dataset, not a space constraint.
            // Required-ness now shows ONLY as the '*' marker
            // populateFieldContent() puts on the label.
            cardFields.forEach(function (entry) {
                renderFieldItem(instance, nestedGrid, entry.field, entry.y);
            });
        });

        // Responsive mode only (default off): a narrow host collapses the
        // grids to one column now, before the first sweep measures anything.
        instance.narrow = false;
        applyResponsiveColumns(instance);

        // Only AFTER every card exists: a card that has to grow pushes the
        // cards below it down, so the pass has to see the whole stack.
        scheduleAutoFit(instance);

        // The end of the build is the dirty-check baseline -- every field
        // has been built, activated and prefilled by now.
        takeDirtySnapshot(instance);
    }

    // ------------------------------------------------------------------
    // Dirty tracking (RiskDetailsForm.isDirty()).
    //
    // The baseline is the serialized form as it stood at the end of the
    // build, compared on demand -- so a field changed and then changed back
    // reads clean again. HugeRTE editors are compared separately against the
    // content each held once it initialized (initRichTextField()), because
    // their <textarea> only receives the editor's content on an explicit save.
    //
    // "The end of the build" is not one instant: several widgets keep
    // settling their own values afterwards, off promises and AJAX round trips
    // (the scoring holders fill their computed defaults from the cached
    // risk-formula config, the affected-assets picker selects the record's
    // picks once its roster lands). Measured on the create form, a snapshot
    // taken at the end of buildCanvas() already differed from the form a few
    // seconds later with nobody touching it. So the baseline keeps following
    // the form until the user's first real interaction with it (a trusted
    // pointer/key/input/change/paste event anywhere inside the container,
    // recorded in the capture phase by bindUserInteractionTracking()) -- up to
    // that moment every difference is the widgets settling, not an edit.
    // ------------------------------------------------------------------
    function formSnapshot(instance) {
        var formEl = document.getElementById(instance.idPrefix + '-form');
        if (!formEl) {
            return null;
        }
        var editorIds = instance.activeEditorIds || [];
        var serialized = $(formEl).find(':input').filter(function () {
            return editorIds.indexOf(this.id) === -1;
        }).serialize();
        // serialize() skips file inputs entirely, so a picked file would
        // read as clean without this: name + chosen file names per input.
        var files = $(formEl).find('input[type="file"]').map(function () {
            var names = Array.prototype.map.call(this.files || [], function (file) { return file.name; });
            return (this.name || this.id) + ':' + names.length + ':' + names.join('/');
        }).get().join('|');
        return serialized + '#files=' + files;
    }

    function takeDirtySnapshot(instance) {
        instance.userInteracted = false;
        instance.dirtySnapshot = formSnapshot(instance);
    }

    var USER_INTERACTION_EVENTS = ['focusin', 'mousedown', 'keydown', 'input', 'change', 'paste', 'drop'];

    function bindUserInteractionTracking(instance) {
        var el = $(instance.containerSelector)[0];
        if (!el) {
            return;
        }
        instance.userInteractionListener = function (e) {
            if (e.isTrusted && !instance.userInteracted) {
                // Freeze the baseline as it stood just before this first
                // interaction. focusin/mousedown/keydown precede any value
                // change, so the form is still the settled one; an input/
                // change arriving first already carries the user's value,
                // so the last refresh (the build, an auto-fit sweep, or an
                // isDirty() call) stands.
                if (e.type === 'focusin' || e.type === 'mousedown' || e.type === 'keydown') {
                    instance.dirtySnapshot = formSnapshot(instance);
                }
                instance.userInteracted = true;
            }
        };
        USER_INTERACTION_EVENTS.forEach(function (type) {
            el.addEventListener(type, instance.userInteractionListener, true);
        });
        instance.userInteractionElement = el;
    }

    function unbindUserInteractionTracking(instance) {
        if (!instance.userInteractionElement || !instance.userInteractionListener) {
            return;
        }
        USER_INTERACTION_EVENTS.forEach(function (type) {
            instance.userInteractionElement.removeEventListener(type, instance.userInteractionListener, true);
        });
        instance.userInteractionElement = null;
        instance.userInteractionListener = null;
    }

    function editorContentChanged(instance) {
        if (typeof hugerte === 'undefined') {
            return false;
        }
        return (instance.activeEditorIds || []).some(function (id) {
            var editor = hugerte.get(id);
            var baseline = instance.editorBaselines ? instance.editorBaselines[id] : undefined;
            if (!editor || !editor.initialized || baseline === undefined) {
                return false;
            }
            return editor.getContent() !== baseline;
        });
    }

    // ------------------------------------------------------------------
    // Responsive mode (options.responsive, default off). An embedded host
    // (the asset record modal) can be narrower than the half-width field
    // pairs the saved layout designs for. At or below the breakpoint the
    // top grid and every nested field grid switch to one column, keeping the
    // designed reading order (GridStack's 'list' layout); above it GridStack
    // restores the layout it cached on the way down. Measured on the
    // container this instance renders into, not the viewport.
    // ------------------------------------------------------------------
    var RESPONSIVE_BREAKPOINT = 700;

    function applyResponsiveColumns(instance) {
        if (!instance.responsive || !instance.topGrid) {
            return;
        }
        var el = $(instance.containerSelector)[0];
        var width = el ? el.clientWidth : 0;
        if (!width) {
            return;
        }
        var narrow = width <= instance.responsiveBreakpoint;
        if (narrow === instance.narrow) {
            return;
        }
        instance.narrow = narrow;
        $(el).toggleClass('sr-qform-narrow', narrow);
        instance.topGrid.column(narrow ? 1 : 12, narrow ? 'list' : undefined);
        instance.nestedGrids.forEach(function (grid) {
            grid.column(narrow ? 1 : 6, narrow ? 'list' : undefined);
        });
        reflowCards(instance);
        scheduleAutoFit(instance);
    }

    function bindResponsiveColumns(instance) {
        if (!instance.responsive || typeof window.ResizeObserver !== 'function') {
            return;
        }
        var el = $(instance.containerSelector)[0];
        if (!el) {
            return;
        }
        instance.responsiveObserver = new window.ResizeObserver(function () {
            applyResponsiveColumns(instance);
        });
        instance.responsiveObserver.observe(el);
    }

    // ------------------------------------------------------------------
    // Public entry points.
    // ------------------------------------------------------------------

    // GridStack.renderCB is a page-WIDE static hook: GridStack calls it for
    // every widget added to ANY grid on the page, not just the grid whose
    // owner installed it. includes/Widgets/UILayout.php installs one on every
    // page that renders a UILayoutWidget band (Review Risk's Insights band is
    // the first such page to also embed this form), and its non-custom branch
    // assumes every item carries a real UILayoutWidget shape (w.name +
    // w.layout), firing GET /api/v2/ui/widget?widget_name=<w.name>&layout_name=
    // <w.layout> for it.
    //
    // This module's own Cards/field items (the topGrid.addWidget() /
    // nestedGrid.addWidget() calls in buildCanvas()/renderFieldItem()) carry
    // neither. Left unwrapped, rendering the form on such a page fired that
    // fetch with widget_name=undefined once per Card/field item, and each
    // resulting 400's xhr `error` handler called showAlertsFromArray(),
    // spamming an error toast per item. Verified live on Review Risk before
    // this wrapper existed.
    //
    // This lives HERE rather than in any one caller's page script on purpose:
    // the items that confuse the hook are this module's, so every current and
    // future embedder is protected by construction instead of rediscovering
    // the same bug. Called unconditionally from init() -- it is always safe,
    // because it only ever short-circuits items that have NO .name and are not
    // .custom, which this engine's items always are and a real UILayoutWidget
    // item never is. A page with no UILayoutWidget band has no renderCB to
    // wrap and this is a no-op.
    //
    // Wrapped exactly once (idempotent via the marker property), delegating to
    // the ORIGINAL callback for anything that still looks like a real
    // UILayoutWidget item, so the host page's own widget rendering -- including
    // a re-render while this form is open -- is completely unaffected.
    function guardGridStackRenderCB() {
        if (typeof GridStack === 'undefined' || !GridStack.renderCB || GridStack.renderCB.__riskDetailsFormGuarded) {
            return;
        }
        var originalRenderCB = GridStack.renderCB;
        var guardedRenderCB = function (el, w) {
            if (!w || (typeof w.name === 'undefined' && !w.custom)) {
                return;
            }
            return originalRenderCB(el, w);
        };
        guardedRenderCB.__riskDetailsFormGuarded = true;
        GridStack.renderCB = guardedRenderCB;
    }

    // Build the tab bar + two-level Cards/GridStack canvas inside
    // `containerSelector`, plus -- when `options.embedded` is falsy -- the
    // standalone page's sticky action bar.
    //
    // options:
    //   embedded  {boolean} true for a modal caller: no sticky action bar, a
    //                       hidden `.save-risk-form` affordance and a
    //                       `tab-data` form instead, so risk.js's own
    //                       delegated handler owns submission.
    //   formName  {string}  optional `name` attribute for the rendered form.
    //   pinnedTemplateGroupId
    //             {string|number} render ONLY this template group: no tab bar,
    //                       and no /ui/risk/template_groups fetch at all. For
    //                       editing an EXISTING record, whose template group is
    //                       a property of the row rather than a choice the user
    //                       is still making -- see renderFormShell().
    //   profile   {object}  optional record-type profile (endpoints, maps,
    //                       tagType, requiredNames, widgetRegistry, submit,
    //                       ...). Omitted = RISK_PROFILE, i.e. exactly the
    //                       behaviour every risk caller has always had. See
    //                       RISK_PROFILE's docblock.
    //   actionsBar {boolean} false = render no action bar at all (neither
    //                       the sticky bar nor the hidden embedded
    //                       affordance); the host drives save through
    //                       RiskDetailsForm.submit() and cancel itself.
    //                       Default true.
    //   responsive {boolean} one-column grids while the container is at or
    //                       below `responsiveBreakpoint` px (default 700).
    //                       Default false. See applyResponsiveColumns().
    //
    // Idempotent per container: a second call on a container that already has
    // a live instance tears the old one down first, so a modal that is opened,
    // closed and opened again never stacks two canvases (or two sets of
    // GridStack instances, HugeRTE editors and sweep timers) on one container.
    function init(containerSelector, options) {
        options = options || {};
        var container = $(containerSelector);
        if (container.length === 0) {
            return;
        }

        destroy(containerSelector); // idempotent: no-op if nothing was built here yet

        // Before the first addWidget() this render will make -- see the
        // function's own comment for why this belongs to the engine rather
        // than to any one embedding page.
        guardGridStackRenderCB();

        var instance = getOrCreateInstance(containerSelector);
        instance.initOptions = options;
        instance.profile = resolveProfile(options.profile);
        instance.actionsBar = options.actionsBar !== false;
        instance.responsive = !!options.responsive;
        if (Number(options.responsiveBreakpoint) > 0) {
            instance.responsiveBreakpoint = Number(options.responsiveBreakpoint);
        }
        instance.embedded = !!options.embedded;
        instance.formName = options.formName || null;
        instance.prefillValues = options.prefillValues || {};
        instance.submitMode = (options.submitMode === 'update' || options.submitMode === 'create-log-entry')
            ? options.submitMode
            : 'create';
        instance.createUrl = options.createUrl || null;
        instance.updateRiskId = options.updateRiskId || null;
        instance.updateUrl = options.updateUrl || null;
        // Governance permission gate for the Mitigation tab's control-
        // selection field (buildMitigationControlsWidget(), below) --
        // mirrors the /ui/risk/{id}/mitigation-values response's own
        // 'can_select_mitigation_controls' flag (api/v2/includes/api.php).
        // Defaults false: every OTHER caller of this engine (Details/Review
        // tabs, the create-risk flow) never sets this option and must not
        // accidentally show a control-selection UI nobody checked
        // permissions for.
        instance.canSelectMitigationControls = !!options.canSelectMitigationControls;
        // Gates the Control Validation table's row edit-action specifically
        // (plan_mitigations) -- separate from canSelectMitigationControls
        // (governance), which gates the picker/table's existence at all.
        // See that flag's own docblock, api/v2/includes/api.php.
        instance.canEditMitigation = !!options.canEditMitigation;
        // Gates SupportingDocumentation (Details tab): submit_risks in
        // 'create' mode (Submit Risk / +Add Risk modal), modify_risks in
        // 'update' mode (Edit Risk) -- see buildSupportingDocumentationWidget()
        // below. Two separate flags, not one, because the two modes check
        // genuinely different session permissions.
        instance.canSubmitRisk = !!options.canSubmitRisk;
        instance.canModifyRisk = !!options.canModifyRisk;
        // Which /ui/risk/fields + /ui/risk/layout tab this canvas renders --
        // 1 (Details, the default every pre-4b-iii caller relies on), 2
        // (Mitigation, Phase 4b-iii's risk-view-mitigation.js coordinator)
        // or 3 (Review, Phase 4c-ii's risk-view-review.js coordinator).
        instance.tabIndex = options.tabIndex || 1;
        instance.onSaveSuccess = typeof options.onSaveSuccess === 'function' ? options.onSaveSuccess : null;
        // Opt-in: buildActionsBar() only renders a Cancel button in the
        // sticky action bar (submitMode 'update') when a caller supplies
        // this. Mitigation/Review's own coordinators render their own
        // separate Cancel button outside this engine (see buildActionsBar()'s
        // own comment) and don't pass it, so their bar is unaffected.
        instance.onCancel = typeof options.onCancel === 'function' ? options.onCancel : null;
        // Finding 3(a): the computed next-review date shown as read-only
        // informational text by buildSetNextReviewDateWidget() -- an
        // {raw, display} pair (the SAME shape every other resolved field
        // value uses) or null/undefined when the caller has none (e.g. a
        // risk with no prior review yet). Never fed into the date input's
        // `value` -- see that function's own comment.
        instance.nextReviewDateDefault = options.nextReviewDateDefault || null;
        instance.pinnedTemplateGroupId = (options.pinnedTemplateGroupId === undefined
            || options.pinnedTemplateGroupId === null
            || options.pinnedTemplateGroupId === '')
            ? null
            : String(options.pinnedTemplateGroupId);
        instance.generation += 1;
        var generation = instance.generation;

        bindAutoFitResize(instance);
        bindResponsiveColumns(instance);
        bindUserInteractionTracking(instance);

        // Pinned to one group: there is no group to CHOOSE, so the roster of
        // groups is not needed and the fetch is skipped entirely.
        // renderFormShell() loads the pinned group itself.
        if (instance.pinnedTemplateGroupId) {
            renderFormShell(container, [], instance);
            return;
        }

        // Every fetch callback re-checks the generation it was started under.
        // Without it, a template_groups response that lands after the
        // container was torn down (a modal closed mid-flight) would rebuild
        // the whole form into a container nobody is looking at -- or, worse,
        // over the top of a container a LATER init() has already rebuilt.
        fetchJSON(instance.profile.endpoints.templateGroups, {}).done(function (groups) {
            if (instance.generation !== generation) {
                return;
            }
            renderFormShell(container, groups, instance);
        }).fail(function () {
            if (instance.generation !== generation) {
                return;
            }
            showAlertFromMessage(_lang['RequestFailed'], false);
        });
    }

    // Full teardown for one container: every nested and the top-level
    // GridStack instance destroyed, every HugeRTE editor removed, every
    // pending timer/animation-frame cancelled, the ResizeObserver
    // disconnected, the window resize binding released, any held popup
    // clipping state restored, and the container emptied.
    //
    // A no-op for a container this module never built into, so a caller can
    // call it unconditionally on close.
    function destroy(containerSelector) {
        var instance = instancesByContainer[containerSelector];
        if (!instance) {
            return;
        }

        // Invalidate every in-flight fetch's callback before anything else, so
        // a response landing during teardown cannot render into the container
        // we are about to empty.
        instance.generation += 1;

        destroyCanvas(instance);

        unbindUserInteractionTracking(instance);
        if (instance.responsiveObserver) {
            instance.responsiveObserver.disconnect();
            instance.responsiveObserver = null;
        }

        if (instance.autoFitObserver) {
            instance.autoFitObserver.disconnect();
            instance.autoFitObserver = null;
        }
        $(window).off(instance.resizeNamespace);

        $(containerSelector).empty().removeClass('sr-qform-narrow');

        delete instancesByContainer[containerSelector];
    }

    // Builds ONE field control OUTSIDE this module's own multi-card
    // GridStack engine -- risk-details-view.js's Details-tab read mode uses
    // this pair (build, then activateStandaloneFieldControl below) for its
    // per-field inline-edit affordance (a single .sr-qfield's value swaps
    // for a control + Cancel/Save icons, without opening the whole Edit
    // Details form). Reuses this module's own widget knowledge (option
    // lists, selectize wiring, the field-name -> POST-param-name map) as the
    // single source of truth rather than a second, drifting copy of it in
    // that other file.
    //
    // Deliberately split from activation (below) rather than one combined
    // call: renderSelectionChips()'s own docblock above already establishes
    // that these plugins need to run against a LIVE DOM node -- selectize
    // marks a detached <select> "selectized" and hides it same as always,
    // but has no live parentNode to insert its own visible wrapper next to,
    // so the wrapper silently never appears (confirmed live: the field's
    // edit row showed only Cancel/Save, no control at all). The caller must
    // append this return value to the document, THEN call
    // activateStandaloneFieldControl() on it.
    //
    // A stable, recognizable id-namespace for every standalone control this
    // pair builds -- buildRichTextField() needs a real element id
    // (fieldElementId(instance, field) below), which only matters for
    // 'richtext' but is harmless to set unconditionally for every widget
    // type built this way.
    var STANDALONE_ID_PREFIX = 'sr-inline-edit';

    // Meaningful for any widget type whose activation (below) doesn't assume
    // the full form's surrounding chrome. Confirmed working standalone:
    // 'text'/'select'/'selectize-single' (Details tab's first inline-edit
    // rollout), 'multiselect'/'selectize-tags'/'selectize-grouped' (each
    // needs only its own options list + activateFieldWidget(), same as
    // those three), and 'richtext' (HugeRTE, via init_compact_editor() --
    // see activateStandaloneFieldControl()'s own docblock for the teardown
    // it needs that the others don't). 'file' (multipart upload, existing-
    // file list) is NOT included here -- extending inline-edit to it is
    // future work, not something this function silently half-supports today.
    // `profile` (optional, default the risk profile) selects the form-name
    // map and widget registry the control is built from -- the read view's
    // inline editor passes its own record type's profile through.
    function buildStandaloneFieldControl(field, widgetType, profile) {
        return buildFieldControl({ popupUnclips: [], idPrefix: STANDALONE_ID_PREFIX, profile: resolveProfile(profile) }, field, widgetType);
    }

    // Call AFTER $control (buildStandaloneFieldControl()'s return value) is
    // already attached to a live document -- see that function's own
    // docblock for why. A real GridStack instance's popupUnclips/
    // autoFitDeferred machinery (holdClippingOpen()/releaseClipping(),
    // scheduleAutoFitSoon()) exists to escape a clipped
    // .grid-stack-item-content ancestor -- this control is never mounted
    // inside one, so a bare `{ popupUnclips: [] }` stand-in is sufficient:
    // holdClippingOpen()'s own ancestor walk simply finds no such node and
    // no-ops, exactly as it would for any OTHER field not nested in a
    // clipped card.
    //
    // rawValue is `entry.raw` from the /ui/risk/{id}/values response
    // (risk-details-view.js) -- a plain string for a single-value field, an
    // array of ids/tag-text for a multi-value one (api_ui_core_field_
    // resolvers()'s own $multi()/'tags'/'risk_catalog_mapping'/
    // 'threat_catalog_mapping' resolvers, api/v2/includes/api.php, all
    // return `raw` in exactly this shape already). Prefilling reuses
    // applyPrefillValue() -- the SAME per-widget-type restore logic the full
    // Edit Details form uses for every field, keyed through a throwaway
    // instance whose `prefillValues` holds only this one field, rather than
    // a second, narrower copy of that widget-by-widget knowledge here.
    // Returns the throwaway instance activateFieldWidget() ran against --
    // 'richtext' is the one widget type here that registers real global
    // state (a HugeRTE editor instance, keyed by the control's element id,
    // pushed onto instance.activeEditorIds by initRichTextField()) that
    // outlives this function call and needs an explicit destroy later (see
    // destroyStandaloneFieldControl() below) -- every other widget type's
    // "activation" is just DOM decoration a plain .empty()/.remove() cleans
    // up for free. The caller only needs this instance to reach that array;
    // for every other widget type it is inert.
    function activateStandaloneFieldControl($control, field, widgetType, rawValue, profile) {
        var instance = { popupUnclips: [], prefillValues: {}, activeEditorIds: [], idPrefix: STANDALONE_ID_PREFIX, profile: resolveProfile(profile) };
        activateFieldWidget(instance, $control, field, widgetType);
        if (rawValue !== undefined && rawValue !== null) {
            instance.prefillValues[prefillLookupKey(field, instance.profile)] = rawValue;
            applyPrefillValue(instance, $control, field, widgetType);
        }
        return instance;
    }

    // Companion to activateStandaloneFieldControl() -- call once, whenever
    // the control it returned an instance for is about to be discarded
    // (Cancel, a successful Save, or the whole read-mode container being
    // torn down/rebuilt out from under a still-open editor). A no-op for
    // every widget type except 'richtext': destroy_editor() (js/WYSIWYG/
    // helpers.js, loaded globally via header.php) is the app's own shared
    // "destroy one HugeRTE instance by id" helper, reused here rather than
    // hand-rolling the same hugerte call -- see destroyCanvas()'s own
    // identical guard (hugerte.get(id).destroy() throws on a stale/missing
    // id) for why the existence check stays here too.
    function destroyStandaloneFieldControl(instance) {
        if (!instance || !instance.activeEditorIds) {
            return;
        }
        instance.activeEditorIds.forEach(function (id) {
            if (typeof hugerte !== 'undefined' && hugerte.get(id)) {
                destroy_editor(id);
            }
        });
        instance.activeEditorIds = [];
    }

    // Host-driven submit (a modal footer's Save, typically with actionsBar:
    // false). Runs the same client-side pieces the built-in buttons do --
    // HugeRTE write-back and checkAndSetValidation() -- then sends the
    // profile's submit request for the instance's submitMode and resolves:
    //   {ok: true,  data: <response body>}                on success
    //   {ok: false, data: <response JSON or null>, status} on a server error
    //                                                     (after the usual
    //                                                     error toast)
    //   {ok: false, data: null}                           when validation
    //                                                     blocked it, or no
    //                                                     form is rendered
    // Never navigates and never calls onSaveSuccess: what happens next is
    // the host's call. A second call while one is in flight returns the same
    // promise rather than posting twice. Always resolves, never rejects.
    function submit(containerSelector) {
        var instance = instancesByContainer[containerSelector];
        var formEl = instance ? document.getElementById(instance.idPrefix + '-form') : null;
        if (!instance || !formEl) {
            return window.Promise.resolve({ ok: false, data: null });
        }
        if (instance.pendingSubmit) {
            return instance.pendingSubmit;
        }

        if (typeof force_save_all_editors === 'function') {
            force_save_all_editors();
        }
        if (typeof checkAndSetValidation === 'function' && !checkAndSetValidation($(formEl))) {
            return window.Promise.resolve({ ok: false, data: null });
        }

        var request = buildSubmitRequest(instance, formEl);
        if (!request) {
            return window.Promise.resolve({ ok: false, data: null, error: 'profile-missing-url' });
        }
        instance.pendingSubmit = new window.Promise(function (resolve) {
            $.ajax(request).done(function (response) {
                instance.pendingSubmit = null;
                // What was just saved is the new clean state.
                markInstanceClean(instance);
                resolve({ ok: true, data: response });
            }).fail(function (xhr) {
                instance.pendingSubmit = null;
                showSubmitFailure(xhr);
                resolve({ ok: false, data: xhr.responseJSON || null, status: xhr.status });
            });
        });
        return instance.pendingSubmit;
    }

    // The form as it stands now becomes the clean baseline: the serialized
    // form, and every initialized editor's current content. Freezes the
    // baseline (it no longer follows settling widgets). submit() calls this
    // after a successful save; hosts call it via RiskDetailsForm.markClean().
    function markInstanceClean(instance) {
        instance.dirtySnapshot = formSnapshot(instance);
        instance.userInteracted = true;
        instance.editorBaselines = {};
        if (typeof hugerte !== 'undefined') {
            (instance.activeEditorIds || []).forEach(function (id) {
                var editor = hugerte.get(id);
                if (editor && editor.initialized) {
                    instance.editorBaselines[id] = editor.getContent();
                }
            });
        }
    }

    function markClean(containerSelector) {
        var instance = instancesByContainer[containerSelector];
        if (instance) {
            markInstanceClean(instance);
        }
    }

    // Whether the rendered form differs from the state it was built in (see
    // formSnapshot()). False for a container with no live form.
    function isDirty(containerSelector) {
        var instance = instancesByContainer[containerSelector];
        if (!instance || instance.dirtySnapshot === null) {
            return false;
        }
        if (!instance.userInteracted) {
            // Still settling (see formSnapshot()'s docblock): follow it.
            instance.dirtySnapshot = formSnapshot(instance);
        }
        if (formSnapshot(instance) !== instance.dirtySnapshot) {
            return true;
        }
        return editorContentChanged(instance);
    }

    window.RiskDetailsForm = {
        init: init,
        destroy: destroy,
        submit: submit,
        isDirty: isDirty,
        markClean: markClean,
        // Shared with risk-details-view.js (widgetRegistry.readRender).
        normalizeWidgetNode: normalizeWidgetNode,
        getWidgetType: fieldWidgetType,
        getFormName: fieldFormName,
        buildStandaloneFieldControl: buildStandaloneFieldControl,
        activateStandaloneFieldControl: activateStandaloneFieldControl,
        destroyStandaloneFieldControl: destroyStandaloneFieldControl,
        serializeWithEmptyMultiValueMarkers: serializeWithEmptyMultiValueMarkers
    };
})();
