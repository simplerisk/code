<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(
        ['blockUI', 'selectize', 'datatables', 'datetimerangepicker', 'WYSIWYG', 'multiselect',
         // Task 10: the insights band is a UILayout instance -- 'UILayoutWidget'
         // is what pulls in both the includes/Widgets/UILayout.php CLASS
         // (header.php's 'UILayoutWidget' case require_once's it; there is no
         // Composer autoload entry for it, so omitting this asset is a runtime
         // "Class not found" fatal, not just a missing script) and the
         // Gridstack JS/CSS the band's drag/resize + tile rendering depend on.
         // Same asset the sibling define_tests_insights band's page
         // (compliance/index.php) already declares.
         'UILayoutWidget',
         'CUSTOM:pages/governance-frameworks.js', 'CUSTOM:common.js', 'JSLocalization'],
        ['check_governance' => true]
    );

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/extras.php'));

    // NOTE: The add_framework / delete_framework / delete_control / delete_controls
    // POST handlers that used to live here were removed along with the tab strips
    // that were their only triggers. The modals that submitted to them (framework--add,
    // framework--update, framework--delete, control--add, control--update,
    // control--delete, controls--delete) are rendered below on the sr-modal shell
    // (design-system.md §8) and wired to the v2 CRUD endpoints by
    // js/simplerisk/pages/governance-frameworks.js (Task 8).
?>
<script>

    /* Client-side affordance gating (Task 58) -------------------------------
     *
     * The page's own permission bits, server-resolved once, for
     * governance-frameworks.js to decide which affordances to RENDER.
     *
     * This is the same channel the pre-redesign page used, generalised: the
     * old governance/index.php inlined has_permission('modify_frameworks')
     * straight into an initAsFrameworkTreegrid() argument, and
     * assessments/contacts.php still ships the same shape one bit at a time
     * (a `let assessment_add_contact_permission = ...` echoed per permission).
     * A named object beats six loose globals here because this page has six
     * distinct bits and a JS file that has to reason about all of them.
     *
     * (Do NOT write a short-echo tag inside this comment to illustrate the
     * point -- PHP tokenises the whole file before JS ever sees it, so an
     * opening tag inside a /* *\/ block is still an opening tag, and the
     * illustrative one that first sat on the line above 500'd the page.)
     *
     * PURELY ADDITIVE. Every endpoint behind every one of these affordances
     * enforces the same permission server-side (api_v2_check_permission() /
     * check_permission() in the v2 handlers) -- this exists so a user who
     * cannot do a thing is not shown the button for it and then handed a 403
     * toast, which is what the redesign regressed. Nothing here is a security
     * control and nothing server-side may ever be relaxed because of it.
     *
     * Booleans, not the raw session values: has_permission() returns a real
     * bool, and `? 'true' : 'false'` keeps this a JS literal rather than
     * echoing anything session-derived into the script context.
     */
    window.SR_GOV_PERMS = {
        add_new_frameworks: <?= has_permission('add_new_frameworks') ? 'true' : 'false' ?>,
        modify_frameworks:  <?= has_permission('modify_frameworks')  ? 'true' : 'false' ?>,
        delete_frameworks:  <?= has_permission('delete_frameworks')  ? 'true' : 'false' ?>,
        add_new_controls:   <?= has_permission('add_new_controls')   ? 'true' : 'false' ?>,
        modify_controls:    <?= has_permission('modify_controls')    ? 'true' : 'false' ?>,
        delete_controls:    <?= has_permission('delete_controls')    ? 'true' : 'false' ?>
    };

    // Set current mouse position
    var mouseX, mouseY;
    $(document).mousemove(function(e) {mouseX = e.pageX;mouseY = e.pageY;}).mouseover();

    $(document).ready(function() {

    <?php 
        if (customization_extra()) {
    ?>
        $('.datepicker').initAsDatePicker();
        $("select[id^='custom_field'].multiselect").multiselect({buttonWidth: '300px', enableFiltering: true, enableCaseInsensitiveFiltering: true});
    <?php 
        }
    ?>
        // NOTE: the old #controls-tab-content select[multiple] multiselect() init
        // (filter_by_control_class/phase/family/owner/framework/priority/type,
        // wired to rebuild_filters()/controlDatatable.draw() -- both globals from
        // governance.js, which this page no longer loads) was removed here: those
        // filter dropdowns and #controls-tab-content itself don't exist in the
        // master-detail markup above. The control_type[] multiselect just below
        // is kept -- it targets the still-present control--add/control--update
        // modals' Control Type field, not the removed filters.
        $("select[name='control_type[]'").multiselect({
        	allSelectedText: '<?= $escaper->escapeHtml($lang['ALL']); ?>',
            enableFiltering: true,
            maxHeight: 250,
            buttonWidth: '100%',
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
        });

        // Compact editor (design-system.md §14b): the 18-button
        // init_minimun_editor() toolbar wraps to ~200px of chrome in this
        // modal's half-width sr-qcard columns, leaving almost nothing to
        // write in. init_compact_editor() trims the toolbar to fit.
        $("#framework--add [name=framework_description]").attr("id", "add_framework_description");
        init_compact_editor('#add_framework_description');
        $("#framework--update [name=framework_description]").attr("id", "update_framework_description");
        init_compact_editor('#update_framework_description');

        // The SoA scope statement is rich text too -- one paragraph of free
        // prose, in practice "the ISMS covers:" plus a list of sites, which a
        // plain box could only express as run-on lines. The ids come from
        // display_framework_scope_statement_edit()'s $id_prefix, so there is
        // nothing to stamp on here (unlike the description above, whose ids
        // predate that prefix). The Initiate Audits page renders no SoA card,
        // so it has nothing to initialise and hugerte.init() on a selector that
        // matches nothing is a no-op either way.
        init_compact_editor('#add_scope_statement');
        init_compact_editor('#update_scope_statement');

        // Add WYSIWYG editor to control modal
        $("#control--add [name=description]").attr("id", "add_control_description");
        init_compact_editor('#add_control_description');
        $("#control--add [name=supplemental_guidance]").attr("id", "add_supplemental_guidance");
        init_compact_editor('#add_supplemental_guidance');
        $("#control--update [name=description]").attr("id", "update_control_description");
        init_compact_editor('#update_control_description');
        $("#control--update [name=supplemental_guidance]").attr("id", "update_supplemental_guidance");
        init_compact_editor('#update_supplemental_guidance');
    });

	$(document).on('show.bs.modal', '#framework--add', function(e) {
		$.ajax({
            url: BASE_URL + '/api/v2/governance/parent_frameworks_dropdown?status=1',
            type: 'GET',
            async: false,
            success : function (res){
                // The container carries the id its injected <select> is to be
                // given (data-sr-field-id, display_framework_parent_edit()).
                // The endpoint's HTML cannot carry it: the same response feeds
                // the Edit modal too, and its response shape is a published
                // v1+v2 contract.
                var $container = $("#framework--add .parent_frameworks_container");
                $container.html(res.data.html)
                    .find('select[name="parent"]').attr('id', $container.data('sr-field-id'));
            }
        });
	});

    // Destructive-confirms (design-system.md §8): focus Cancel on open, never
    // the solid-red verb button, so a stray Enter keypress can't submit the
    // delete. Applies to all three delete modals -- framework--delete,
    // control--delete, controls--delete -- built on the same shell.
    $(document).on('shown.bs.modal', '#framework--delete, #control--delete, #controls--delete', function () {
        $(this).find('.btn-dark[data-bs-dismiss="modal"]').trigger('focus');
    });

</script>
<?php
    // Define Control Frameworks insights band (Task 10) -- renders for every
    // governance user (Core). Unlike the sibling define_tests_insights band
    // (compliance/index.php), this one sits above a page with its own rail:
    // governance-frameworks.js re-fetches every tile whenever the rail's
    // selected framework changes (window.srRefreshLayoutWidgets()), so a band
    // reading "All frameworks" totals can never sit above a table scoped to
    // one. The Edit-layout control is shown only when the Customization
    // extra is enabled (page-local gate; the UILayout framework itself does
    // not gate it) -- matches the sibling band exactly.
    if (check_permission('governance')) {
        // Collapsible here (unlike Home, where the layout IS the page): this
        // band introduces the master-detail panes below it rather than being
        // the content, and its ~120px is roughly two table rows on a
        // 1366x768 laptop.
        (new \includes\Widgets\UILayout('define_frameworks_insights', [
            'show_edit_layout' => customization_extra(),
            'collapsible' => true,
        ]))->render();
    }
?>
<div class="row">
  <div class="col-12">
    <!-- BOTH tier classes ship ON (Task 45, extended by Task 48). The
         responsive tier is measured, not declared: governance-frameworks.js's
         evaluateResponsiveTiers() compares the control table's required width
         against the room the pane has -- twice, once for the full column set
         and once for the folded one -- and RELAXES these classes as far as each
         measurement says is safe. Shipping both on makes the reachable state
         the default at the BOTTOM of the ladder: before the first measurement,
         and with no JS at all, the rows are stacked and the row actions are a
         ⋯ menu inside the scroller, rather than a cluster -- or a toggle --
         that may be hanging outside it. See the note at the top of
         scss/modules/_governance-frameworks.scss. -->
    <div class="sr-fw-panes sr-fw-compact sr-fw-queue">
      <div class="sr-table-card" id="sr-fw-rail">
        <div class="sr-table-toolbar">
          <!-- .sr-title-count-group baseline-aligns the title and count chip
               without touching .sr-table-toolbar's shared align-items:center
               -- see _governance-frameworks.scss's #sr-fw-rail
               .sr-title-count-group rule (same fix, same wrapper class, as
               the controls pane's #sr-ctl-toolbar header). -->
          <div class="sr-title-count-group">
            <h2 class="sr-table-title"><?= $escaper->escapeHtml($lang['Frameworks']) ?></h2>
            <span class="sr-table-count" id="sr-fw-count">0</span>
          </div>
        </div>
        <div class="sr-fw-rail-tools">
          <input type="search" id="sr-fw-search" class="form-control"
                 placeholder="<?= $escaper->escapeHtmlAttr($lang['SearchFrameworks']) ?>">
          <select id="sr-fw-status" class="form-select">
            <option value="1"><?= $escaper->escapeHtml($lang['Active']) ?></option>
            <option value="2"><?= $escaper->escapeHtml($lang['Inactive']) ?></option>
            <option value=""><?= $escaper->escapeHtml($lang['All']) ?></option>
          </select>
        </div>
        <!-- design-system.md §4.2: "+ Add framework is a dashed .sr-qaddrow,
             not a solid button, so the page's single $sr-important fill goes
             to Add control." Opens framework--add directly (Bootstrap's
             native data-bs-toggle/target) -- no bespoke JS needed to show it.

             ABSENT without `add_new_frameworks`, never disabled (Task 58,
             following Task 15's rule for the bulk applicability button): a
             disabled control says "this action exists here and is merely
             unavailable", which is the wrong sentence for a permission the
             user does not hold and cannot grant themselves. POST
             /api/v2/governance/frameworks (createFrameworkCrud(), api.php)
             enforces the same permission server-side; this only stops the
             page offering a button whose only outcome is a 403 toast.

             Task 26: the trigger now asks HOW, when there is more than one
             answer. display_framework_acquisition_chooser() (includes/
             governance.php) owns the whole decision -- including rendering
             nothing at all without `add_new_frameworks`, which is why the
             has_permission() branch that used to be here is gone rather than
             duplicated. The trigger stays a dashed .sr-qaddrow either way:
             the page spends its one $sr-important fill on "+ Add control"
             (design-system.md §4.2), and a chooser is not a reason to spend
             it twice. -->
<?php   display_framework_acquisition_chooser('sr-fw-add', 'sr-qaddrow', true); ?>
        <ul class="sr-fw-list" id="sr-fw-list"></ul>
        <!-- Empty states (Task 9, design-system.md §10): "no frameworks yet"
             restores what the removed blocking third-party-fetch block used
             to tell a new customer -- how to obtain frameworks -- as three
             static routes instead of a network call in the render path.
             "Couldn't load" is loadFrameworks()'s own fail path, kept
             visually distinct (danger tint, Retry) so a fetch failure never
             reads as "you have zero frameworks". -->
        <div class="sr-table-empty d-none" id="sr-fw-empty">
          <div class="sr-table-empty-icon"><i class="fa fa-sitemap" aria-hidden="true"></i></div>
          <div class="sr-table-empty-title"><?= $escaper->escapeHtml($lang['NoFrameworksYet']) ?></div>
          <div class="sr-table-empty-body"><?= $escaper->escapeHtml($lang['FrameworksYouAddWillAppearHere']) ?></div>
          <!-- Same gate as the rail's own + Add framework above (Task 58), and
               since Task 26 the SAME CHOOSER: the empty tile and the populated
               rail offer identical acquisition routes, because "how do I get a
               framework" has one answer set and a customer meeting it for the
               first time on the empty tile should not be shown a different one.
               Only the trigger's chrome differs, as it already did.

               The two ungated onboarding links that used to sit below (Go to
               SCF / Import-Export Extra) are GONE, not moved-and-kept: they
               rendered for every customer, including those with neither Extra
               installed, which is precisely the "affordance for a disabled
               Extra" this page's rule forbids. Their gated equivalents are
               chooser routes now.

               "Register your instance" stays, because it is not an Extra
               affordance -- it is how an administrator OBTAINS the Extras the
               other two routes need -- but it is now admin-gated, since
               admin/register.php is check_admin and a non-admin following it
               would only be bounced. -->
          <div class="sr-table-empty-action">
<?php       display_framework_acquisition_chooser('sr-fw-empty-add', 'btn btn-submit'); ?>
          </div>
<?php     if (is_admin()) { ?>
          <div class="sr-fw-empty-links">
            <a href="../admin/register.php"><?= $escaper->escapeHtml($lang['RegisterYourInstance']) ?></a>
          </div>
<?php     } ?>
        </div>
        <!-- "No frameworks match this status" (Task 9 review fix): shown
             instead of the onboarding tile above whenever the user has
             EXPLICITLY switched the status dropdown away from Active and
             that status has nothing -- the client already knows this is
             filter-driven, so it isn't guessing the way a first load on the
             default Active status legitimately has to (the endpoint's
             totalCount is scoped to the requested status alone, so a first
             load can't tell "no frameworks at all" from "none active").

             Task 22 reuses this SAME tile for the rail search box's own "no
             matches" result rather than adding a fourth rail empty state --
             governance-frameworks.js's showFrameworksEmptyState() swaps the
             title (#sr-fw-filtered-title) and which action button is visible
             depending on whether a status filter or a search caused the
             empty result; both button ids are always in the DOM, exactly one
             visible at a time. -->
        <div class="sr-table-empty d-none" id="sr-fw-filtered">
          <div class="sr-table-empty-icon"><i class="fa fa-magnifying-glass" aria-hidden="true"></i></div>
          <div class="sr-table-empty-title" id="sr-fw-filtered-title"><?= $escaper->escapeHtml($lang['NoFrameworksMatchFilter']) ?></div>
          <div class="sr-table-empty-body"><?= $escaper->escapeHtml($lang['NoTestsMatchFiltersBody']) ?></div>
          <div class="sr-table-empty-action">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="sr-fw-view-active"><?= $escaper->escapeHtml($lang['ViewActiveFrameworks']) ?></button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="sr-fw-clear-search"><?= $escaper->escapeHtml($lang['ClearSearch']) ?></button>
          </div>
        </div>
        <div class="sr-table-empty sr-table-empty-danger d-none" id="sr-fw-error">
          <div class="sr-table-empty-icon"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i></div>
          <div class="sr-table-empty-title"><?= $escaper->escapeHtml($lang['CouldNotLoadFrameworks']) ?></div>
          <div class="sr-table-empty-body"><?= $escaper->escapeHtml($lang['CouldNotLoadTestsBody']) ?></div>
          <div class="sr-table-empty-action">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="sr-fw-retry"><?= $escaper->escapeHtml($lang['Retry']) ?></button>
          </div>
        </div>
      </div>

      <div class="sr-table-card" id="sr-ctl-table">
        <div class="sr-table-toolbar" id="sr-ctl-toolbar"></div>
        <div class="sr-bulk-bar d-none" id="sr-ctl-bulk"></div>
        <div class="sr-table-scroll">
          <table class="sr-table"><thead id="sr-ctl-thead"></thead><tbody id="sr-ctl-tbody"></tbody></table>
        </div>
        <!-- Empty states (Task 9, design-system.md §10): the intent (no data
             yet / no results / couldn't load) is decided client-side, from
             the response -- governance-frameworks.js's showControlsEmptyState()
             toggles which of these three is shown and hides .sr-table-scroll +
             .sr-table-foot while any of them is visible. The shipped
             .sr-table-empty family (_tables.scss) is reused as-is; nothing
             page-local is invented for the shell itself. -->
        <div class="sr-table-empty d-none" id="sr-ctl-empty-nodata">
          <div class="sr-table-empty-icon"><i class="fa fa-shield-halved" aria-hidden="true"></i></div>
          <div class="sr-table-empty-title"><?= $escaper->escapeHtml($lang['NoControlsDefinedYet']) ?></div>
          <div class="sr-table-empty-body"><?= $escaper->escapeHtml($lang['ControlsYouAddWillAppearHere']) ?></div>
          <!-- Absent without `add_new_controls` (Task 58) -- the same gate the
               toolbar's + Add control carries, applied here because this
               button opens the SAME #control--add modal and posts to the same
               POST /api/v2/governance/controls (createControlCrud(), api.php).
               The tile keeps its title and body: "no controls defined yet" is
               still true and still worth saying. -->
          <div class="sr-table-empty-action">
<?php       if (has_permission('add_new_controls')) { ?>
            <button type="button" class="btn btn-submit" id="sr-ctl-empty-add"><?= $escaper->escapeHtml($lang['AddControl']) ?></button>
<?php       } ?>
          </div>
        </div>
        <div class="sr-table-empty d-none" id="sr-ctl-empty-noresults">
          <div class="sr-table-empty-icon"><i class="fa fa-magnifying-glass" aria-hidden="true"></i></div>
          <div class="sr-table-empty-title"><?= $escaper->escapeHtml($lang['NoControlsMatch']) ?></div>
          <div class="sr-table-empty-body"><?= $escaper->escapeHtml($lang['NoTestsMatchFiltersBody']) ?></div>
          <div class="sr-table-empty-action">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="sr-ctl-empty-clear"><?= $escaper->escapeHtml($lang['ClearFilters']) ?></button>
          </div>
        </div>
        <div class="sr-table-empty sr-table-empty-danger d-none" id="sr-ctl-empty-error">
          <div class="sr-table-empty-icon"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i></div>
          <div class="sr-table-empty-title"><?= $escaper->escapeHtml($lang['CouldNotLoadControls']) ?></div>
          <div class="sr-table-empty-body"><?= $escaper->escapeHtml($lang['CouldNotLoadTestsBody']) ?></div>
          <div class="sr-table-empty-action">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="sr-ctl-empty-retry"><?= $escaper->escapeHtml($lang['Retry']) ?></button>
          </div>
        </div>
        <!-- Footer zone (Task 46): the SHIPPED .sr-table-foot / .dt-info /
             .sr-table-foot-right / .dt-length / .dt-paging shape from
             _tables.scss, identical to the one Define Tests renders
             (includes/compliance.php) -- so the row-count text, the
             rows-per-page select and the pager all pick up the existing
             styling with no page-local CSS at all. Server-rendered rather
             than JS-built because the two static labels ("Show", the page
             sizes) are exactly the kind of user-facing text that belongs in
             a $lang lookup at render time; governance-frameworks.js only
             fills #sr-ctl-info and #sr-ctl-pager. -->
        <div class="sr-table-foot" id="sr-ctl-foot">
          <div class="dt-info" id="sr-ctl-info"></div>
          <div class="sr-table-foot-right">
            <div class="dt-length">
              <label for="sr-ctl-length"><?= $escaper->escapeHtml($lang['Show']) ?>
                <select id="sr-ctl-length" class="form-select">
                  <option value="10">10</option>
                  <option value="25" selected>25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="200">200</option>
                  <!-- "All" (Task 47). It is offerable now, and offerable with
                       no warning attached, because the render cost that ruled it
                       out is gone: the table VIRTUALIZES this option
                       (governance-frameworks.js), so the number of <tr>s in the
                       DOM is a function of the viewport rather than of the
                       result set, and the rows themselves arrive in 200-row
                       chunks as they are scrolled to -- which is the endpoint's
                       own existing length clamp, so the response stays bounded
                       however many controls the install has. Task 46 capped this
                       select at 200 for a measured reason (a 1,552-row render
                       cost 2.3s and put a 94,282px tbody in the document); that
                       reason no longer holds, and the cap was the thing being
                       worked around rather than the answer. Every other size
                       here is unchanged and still server-paged. -->
                  <option value="all"><?= $escaper->escapeHtml($lang['All']) ?></option>
                </select>
              </label>
            </div>
            <div class="dt-paging" id="sr-ctl-pager"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODEL WINDOW FOR ADDING FRAMEWORK -->
<div class="modal fade sr-modal" id="framework--add" tabindex="-1" aria-labelledby="framework--add-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-sitemap" aria-hidden="true"></i></span>
                <h5 class="modal-title" id="framework--add-title"><?= $escaper->escapeHtml($lang['NewFramework']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none sr-modal-inline-error" role="alert"></div>
                <!-- Task 64: Clone pre-fill notice -- the framework twin of the Add
                     Control modal's banner further down. Hidden for a plain
                     "+ Add framework"; shown and populated by openFrameworkForClone()
                     (governance-frameworks.js) naming the source framework, how many
                     control mappings ride along, and -- the part the fields cannot say
                     for themselves -- that the empty scope statement is deliberate.
                     Set and cleared in ONE place, the #framework--add show.bs.modal
                     delegate, for the same reason the control banner is. -->
                <div class="alert alert-info d-none sr-clone-banner" role="alert"></div>
                <form id="framework-create-form" action="#" method="post" autocomplete="off">
    <?php
                    // The third argument is what makes this the CREATE form: the
                    // SoA card's default inclusion justification is prefilled with
                    // its default sentence here and nowhere else. The Edit modal
                    // below renders the same markup unseeded --
                    // display_framework_inclusion_justification_edit() has the why.
                    //
                    // A native $form[0].reset() after a successful create (submit-
                    // FrameworkAdd(), governance-frameworks.js) restores a control
                    // to its HTML default, so the seed comes back for the next
                    // framework. That is the reason it is rendered server-side
                    // rather than assigned by JS on modal show.
                    //
                    // The fourth argument namespaces this modal's field ids.
                    // display_add_framework() renders the same markup into the
                    // Edit modal further down this page, so without it every id
                    // appears twice and each modal's <label for> resolves to the
                    // FIRST match — the Add modal's copy. See that function.
                    display_add_framework(true, true, true, 'add_');
    ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" form="framework-create-form" name="add_framework" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR EDITING FRAMEWORK -->
<?php
    display_update_framework_modal('governance');
?>

<!-- MODEL WINDOW FOR FRAMEWORK DELETE CONFIRM -->
<!-- Destructive-confirm (design-system.md §8): names the object, amber
     .sr-qnote consequence, Esc + backdrop disabled (data-bs-backdrop='static'
     data-bs-keyboard='false'), focus on Cancel (governance-frameworks.js
     'shown.bs.modal' handler), solid-red verb button. modal-dark removed --
     that was the legacy dark-header treatment this variant replaces. -->
<div class="modal fade sr-modal" id="framework--delete" tabindex="-1" aria-labelledby="framework--delete-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form class="" id="framework-delete-form" action="" method="post">
                <input type="hidden" class="delete-id" name="framework_id" value="" />
                <div class="modal-header">
                    <span class="sr-modal-icon sr-modal-icon--danger"><i class="fa fa-trash" aria-hidden="true"></i></span>
                    <h4 class="modal-title" id="framework--delete-title"></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="sr-qnote">
                        <i class="fa fa-triangle-exclamation sr-qnote-ico" aria-hidden="true"></i>
                        <span><?= $escaper->escapeHtml($lang['DeleteFrameworkConsequence']); ?> <?= $escaper->escapeHtml($lang['DeleteCannotBeUndone']); ?></span>
                    </div>
                    <div class="alert alert-danger d-none sr-modal-inline-error" role="alert"></div>
                </div>
                <div class="modal-footer">
<?php // No aria-hidden here: this Cancel is the focus target when the confirm
      // opens (the shown.bs.modal handler in governance-frameworks.js), and an
      // element hidden from assistive technology must never receive focus. The
      // attribute is left in place on the non-confirm modals, whose Cancel is
      // not focused -- narrowing this to the buttons the change actually
      // touches rather than sweeping a repo-wide pattern.
?>
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" name="delete_framework" class="delete_project btn btn-submit"><?= $escaper->escapeHtml($lang['Delete']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
<div class="modal fade sr-modal" id="control--delete" tabindex="-1" aria-labelledby="control--delete-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form class="" id="control--delete-form" action="" method="post">
                <input type="hidden" class="delete-id" name="control_id" value="" />
                <div class="modal-header">
                    <span class="sr-modal-icon sr-modal-icon--danger"><i class="fa fa-trash" aria-hidden="true"></i></span>
                    <h4 class="modal-title" id="control--delete-title"></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="sr-qnote">
                        <i class="fa fa-triangle-exclamation sr-qnote-ico" aria-hidden="true"></i>
                        <span><?= $escaper->escapeHtml($lang['DeleteControlConsequence']); ?> <?= $escaper->escapeHtml($lang['DeleteCannotBeUndone']); ?></span>
                    </div>
                    <div class="alert alert-danger d-none sr-modal-inline-error" role="alert"></div>
                </div>
                <div class="modal-footer">
<?php // No aria-hidden here: this Cancel is the focus target when the confirm
      // opens (the shown.bs.modal handler in governance-frameworks.js), and an
      // element hidden from assistive technology must never receive focus. The
      // attribute is left in place on the non-confirm modals, whose Cancel is
      // not focused -- narrowing this to the buttons the change actually
      // touches rather than sweeping a repo-wide pattern.
?>
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" name="delete_control" form="control--delete-form" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Delete']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade sr-modal" id="controls--delete" tabindex="-1" aria-labelledby="controls--delete-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="controls--delete-form" action="" method="post">
                <input type="hidden" class="delete-ids" name="control_ids" value=""/>
                <div class="modal-header">
                    <span class="sr-modal-icon sr-modal-icon--danger"><i class="fa fa-trash" aria-hidden="true"></i></span>
                    <h4 class="modal-title" id="controls--delete-title"></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- The consequence line is FIXED (it is true of every
                         delete: a control is removed from every framework it is
                         mapped to). The line under it is RESOLVED SERVER-SIDE
                         and filled in by governance-frameworks.js's
                         renderControlsDeleteScope() before the Delete button
                         becomes clickable.

                         Every resolved sentence carries "This can't be
                         undone." the same as its two sibling delete modals --
                         a control with test history is soft-deleted
                         (deleted = 1) rather than removed outright, but that
                         is a retention mechanism for test-history integrity,
                         not a recovery path: nothing anywhere flips
                         framework_controls.deleted back to 0, only a TEST can
                         be restored. So there is no case where "can't be
                         undone" is false here. What genuinely varies with the
                         selection, and is worth telling the user, is whether
                         test history survives for audit purposes -- that is
                         an ADDITIONAL sentence appended when it applies, never
                         a substitute for the can't-be-undone warning. (An
                         earlier version of this comment argued the opposite;
                         it was wrong -- see Task 55.)

                         Goes in with .text(): it carries counts only, but the
                         rule on this page is that JS-filled copy is text. -->
                    <div class="sr-qnote">
                        <i class="fa fa-triangle-exclamation sr-qnote-ico" aria-hidden="true"></i>
                        <span>
                            <span><?= $escaper->escapeHtml($lang['DeleteControlsConsequence']); ?></span>
                            <span id="sr-ctl-delete-split"><?= $escaper->escapeHtml($lang['DeleteControlsPreviewChecking']); ?></span>
                        </span>
                    </div>
                    <div class="alert alert-danger d-none sr-modal-inline-error" role="alert"></div>
                </div>
                <div class="modal-footer">
<?php // No aria-hidden here: this Cancel is the focus target when the confirm
      // opens (the shown.bs.modal handler in governance-frameworks.js), and an
      // element hidden from assistive technology must never receive focus. The
      // attribute is left in place on the non-confirm modals, whose Cancel is
      // not focused -- narrowing this to the buttons the change actually
      // touches rather than sweeping a repo-wide pattern.
?>
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <!-- Disabled until the preview resolves. A destructive verb
                         that is clickable while the sentence above still reads
                         "Checking..." invites a confirm of something the user
                         has not been told yet. -->
                    <button type="submit" name="delete_controls" class="btn btn-submit" disabled><?= $escaper->escapeHtml($lang['Delete']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL WINDOW FOR BULK-SETTING APPLICABILITY (Task 15) ---------------------
     The interaction the Statement of Applicability stands on: nobody decides
     1,535 SCF controls one row at a time. Opened from the selection bulk bar,
     it records ONE decision against every control in the selection.

     THE RULE THE WHOLE FORM IS SHAPED BY, restated for the SoA
     audit-readiness work (spec 4):

         ABSENCE OF A ROW MEANS APPLICABLE. A ROW IS NOT REQUIRED TO MEAN IT.

     Applicable is still the default and clearing still means deleting the row,
     but an applicable control MAY now carry a row of its own holding its
     reasons and its justification. ISO/IEC 27001 clause 6.1.3(d) asks for a
     justification per control for INCLUSION as much as for exclusion, and until
     this every applicable control printed the framework's identical
     default_inclusion_justification -- ninety identical sentences, which is
     what invites an auditor to ask whether anyone considered the controls one
     at a time.

     So all three states offer reasons and a justification, and what differs is
     what they REQUIRE:

         applicable      both optional; leaving both empty deletes the row and
                         falls back to the framework default
         not_applicable  reason AND justification required
         inherited       provider AND justification required, reason optional

     Both deviations still require a justification -- which is what makes an
     unjustified exclusion impossible by construction rather than something a
     report has to go looking for. Making it optional for INCLUSION is exactly
     the change that could have softened that, so the rule is stated per state
     below (data-sr-appl-required) rather than left implicit.

     `required` is set and cleared by governance-frameworks.js as the state
     changes, never hardcoded here: a `required` textarea belonging to a hidden
     field would block submit with a validation bubble pointing at nothing. -->
<div class="modal fade sr-modal" id="applicability--set" tabindex="-1" aria-labelledby="applicability--set-title" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="applicability-set-form" action="#" method="post" autocomplete="off">
                <div class="modal-header">
                    <span class="sr-modal-icon"><i class="fa fa-scale-balanced" aria-hidden="true"></i></span>
                    <h5 class="modal-title" id="applicability--set-title"><?= $escaper->escapeHtml($lang['SetApplicability']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Amber .sr-qnote: says WHICH framework the decision is
                         scoped to and WHICH population it will be written to
                         (this page's selection, or every row the current filter
                         matches). Both sentences are filled in by
                         governance-frameworks.js's renderApplicabilityScope()
                         -- the framework name and the count are only known
                         client-side -- and both go in with .text(), never
                         .html(): a framework name is user-authored. -->
                    <div class="sr-qnote">
                        <i class="fa fa-circle-info sr-qnote-ico" aria-hidden="true"></i>
                        <span>
                            <span id="appl-scope-framework"></span>
                            <span id="appl-scope-population"></span>
                        </span>
                    </div>
                    <div class="alert alert-danger d-none sr-modal-inline-error" role="alert"></div>
                    <section class="sr-qcard">
                        <div class="sr-qcard-head">
                            <span class="sr-qcard-icon"><i class="fa fa-gavel" aria-hidden="true"></i></span>
                            <h3><?= $escaper->escapeHtml($lang['ApplicabilityDecision']); ?></h3>
                        </div>
                        <div class="sr-qcard-body">
                            <div class="sr-qgrid">
                                <div class="sr-qfield sr-qfield--full">
                                    <!-- A <span>, not a <label for>. Pointing the
                                         field label at the first radio put TWO
                                         `for="appl-state-applicable"` labels on one
                                         input, which gave that radio the accessible
                                         name "Applicability Applicable" and made
                                         `label[for=...]` ambiguous for anything
                                         addressing the segmented control. The group
                                         is named through aria-labelledby instead,
                                         which is what a radio GROUP wants. -->
                                    <span class="sr-qlabel" id="appl-state-label"><?= $escaper->escapeHtml($lang['Applicability']); ?></span>
                                    <!-- The SHIPPED .sr-seg segmented control
                                         (_sr-modal.scss) on Bootstrap .btn-check
                                         radios -- one choice, not three actions.
                                         No new component and no new SCSS. -->
                                    <div class="sr-seg" id="appl-state" role="radiogroup" aria-labelledby="appl-state-label">
                                        <input type="radio" class="btn-check" name="applicability_state" id="appl-state-applicable" value="applicable" checked>
                                        <label class="btn" for="appl-state-applicable"><?= $escaper->escapeHtml($lang['ApplicabilityApplicable']); ?></label>
                                        <input type="radio" class="btn-check" name="applicability_state" id="appl-state-not-applicable" value="not_applicable">
                                        <label class="btn" for="appl-state-not-applicable"><?= $escaper->escapeHtml($lang['ApplicabilityNotApplicable']); ?></label>
                                        <input type="radio" class="btn-check" name="applicability_state" id="appl-state-inherited" value="inherited">
                                        <label class="btn" for="appl-state-inherited"><?= $escaper->escapeHtml($lang['ApplicabilityInherited']); ?></label>
                                    </div>
                                    <div class="sr-qhint" id="appl-state-hint"></div>
                                </div>
                                <!-- data-sr-appl-for names the states each field is
                                     OFFERED for; data-sr-appl-required names the
                                     ones that REQUIRE it. The JS shows/hides and
                                     requires/un-requires from those two attributes
                                     rather than from per-field branches that could
                                     disagree with the server's own
                                     assert_applicability_requirements(). Two
                                     attributes rather than one because the two sets
                                     stopped coinciding when the applicable path
                                     started offering fields it does not demand. -->
                                <!-- Offered for ALL THREE states, required for one:
                                     an exclusion needs a picklist AND a narrative (a
                                     picklist alone is uselessly vague, a narrative
                                     alone yields 93 differently-worded
                                     justifications auditors notice), while an
                                     inheritance carries its meaning in the provider
                                     and an inclusion may be justified by prose
                                     alone. The required marker is moved onto and off
                                     this label by the JS to match.

                                     A CHECKBOX GROUP, not a <select multiple>.
                                     Reasons became multi-select with the join table,
                                     and the roster is four to six rows per state --
                                     small enough that every option can be on screen
                                     at once. design-system.md 14b sends a roster of
                                     TENS to bootstrap-multiselect and HUNDREDS to
                                     the faceted picker; at four options a widget
                                     whose whole value is filtering and a menu is
                                     ceremony over a list, and ctrl-clicking a native
                                     multiple <select> is a gesture users do not
                                     find. No new SCSS either -- Bootstrap's own
                                     .form-check inside the shipped .sr-qfield.

                                     Options are LOADED from
                                     GET /governance/applicability/reasons?applies_to=
                                     and scoped by applies_to: a reason offered for
                                     "inherited" must not be selectable as an
                                     exclusion, or the SoA would read "Excluded
                                     because: performed by a third party" -- the
                                     factually-wrong row the two separate states
                                     exist to prevent. The server enforces the same
                                     rule on write, so the scoping here is the
                                     affordance, not the control.

                                     Reason names are CUSTOMER-EXTENDABLE DB rows
                                     (the control_class option-table pattern), so
                                     every label goes in with .text().

                                     The group is a <div>, so its name comes from
                                     aria-labelledby rather than from a <label for>
                                     pointing at something that is not a control. -->
                                <div class="sr-qfield sr-qfield--full d-none"
                                     data-sr-appl-for="applicable not_applicable inherited"
                                     data-sr-appl-required="not_applicable">
                                    <span class="sr-qlabel" id="appl-reason-label"><?= $escaper->escapeHtml($lang['ApplicabilityReasons']); ?><span class="required d-none">*</span></span>
                                    <!-- .sr-chips-field is the SHIPPED box every
                                         multi-value field in this design system
                                         wears (35px min-height, 6px radius,
                                         $gray-400 border) -- design-system.md 14b
                                         asks a new multi-value widget to match it,
                                         and wearing the class IS matching it, with
                                         no new SCSS to compile. Its default is a
                                         wrapping ROW, which suits short chips; the
                                         two Bootstrap utilities turn it into a
                                         column, because a reason reads as a
                                         sentence ("Legal or regulatory requirement
                                         does not apply") and six of those wrapped
                                         across a row is a puzzle. -->
                                    <div id="appl-reason" class="sr-chips-field flex-column align-items-start" role="group" aria-labelledby="appl-reason-label"></div>
                                    <div class="sr-qhint"><?= $escaper->escapeHtml($lang['ApplicabilityReasonsHint']); ?></div>
                                </div>
                                <div class="sr-qfield sr-qfield--full d-none" data-sr-appl-for="inherited" data-sr-appl-required="inherited">
                                    <label class="sr-qlabel" for="appl-provider"><?= $escaper->escapeHtml($lang['Provider']); ?><span class="required d-none">*</span></label>
                                    <input type="text" id="appl-provider" name="provider" class="form-control" maxlength="200" autocomplete="off">
                                    <div class="sr-qhint"><?= $escaper->escapeHtml($lang['ApplicabilityProviderHint']); ?></div>
                                </div>
                                <!-- Offered for all three, required for the two
                                     DEVIATIONS only. Optional for an inclusion
                                     because forcing prose onto every applicable
                                     control produces filler, which makes the
                                     document worse rather than more complete;
                                     required for both deviations because that is
                                     what makes an unjustified exclusion impossible
                                     by construction. -->
                                <div class="sr-qfield sr-qfield--full d-none"
                                     data-sr-appl-for="applicable not_applicable inherited"
                                     data-sr-appl-required="not_applicable inherited">
                                    <label class="sr-qlabel" for="appl-narrative"><?= $escaper->escapeHtml($lang['Justification']); ?><span class="required d-none">*</span></label>
                                    <textarea id="appl-narrative" name="narrative" class="form-control" rows="4" maxlength="65535"></textarea>
                                    <div class="sr-qhint"><?= $escaper->escapeHtml($lang['ApplicabilityNarrativeHint']); ?></div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" id="appl-save" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Save']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR ADDING CONTROL -->
<div class="modal fade sr-modal" id="control--add" tabindex="-1" aria-labelledby="control--add-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-shield-halved" aria-hidden="true"></i></span>
                <h5 class="modal-title" id="control--add-title"><?= $escaper->escapeHtml($lang['NewControl']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none sr-modal-inline-error" role="alert"></div>
                <!-- Task 24: Clone pre-fill notice -- hidden for a plain "+ Add control";
                     shown and populated by openControlForClone() (governance-frameworks.js)
                     naming the source control, so the user isn't left guessing what carried
                     over. Cleared back to d-none/empty whenever this modal opens for a plain
                     add (see the '#sr-ctl-add, #sr-ctl-empty-add' handler). -->
                <div class="alert alert-info d-none sr-clone-banner" role="alert"></div>
                <form id="add-control-form" action="#controls-tab" method="post" autocomplete="off">
    <?php
                    // 'add_' namespaces this modal's field ids — the Edit
                    // control modal below renders the same markup. See
                    // display_add_control().
                    display_add_control('add_');
    ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" id="add_control" form="add-control-form" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Add']); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR UPDATING CONTROL -->
<?php
    display_update_control_modal('governance');
?>
<?php
    // Display the add mapping and asset rows
    // These are used in the control add and update modals
    display_add_mapping_row();
    display_add_asset_row();
?>
<?php
    // prevent_form_double_submit_script(['framework-delete-form', 'control--delete-form'])
    // used to run here for the legacy native-POST + full-page-reload submits.
    // Removed (Task 8): that helper's own submit handler
    // (includes/functions.php) disables EVERY input[type=submit]/
    // button[type=submit] ON THE WHOLE PAGE, not just the submitted form's --
    // harmless when a reload immediately followed (the old flow), but this
    // page's forms now submit via AJAX and never reload, so deleting a
    // single framework or control would have permanently disabled every
    // other Save/Add/Delete button on the page. governance-frameworks.js's
    // setBusy() replaces it: it disables (and re-enables) only the ONE
    // button whose own request is in flight, matching design-system.md §8's
    // "A form/async primary disables ... during the API call."
?>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>