<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    $breadcrumb_title_key = "ViewTest";
    $active_sidebar_submenu = "ManageAudits";
    $active_sidebar_menu = "Compliance";
    // 'gridstack' + CUSTOM:common/risk-details-form.js: the create-risk
    // modal's Cards-based form engine (js/simplerisk/common/
    // risk-details-form.js, the same one management/review_risk.php's + Add
    // Risk modal and the standalone Submit Risk page use) -- the modal's own
    // shown.bs.modal handler below calls window.RiskDetailsForm.init()
    // against #testing-modal-new-risk-canvas. selectize/multiselect/WYSIWYG/
    // datetimerangepicker (already listed above for this page's own use)
    // double as this engine's field-widget dependencies -- gridstack is the
    // only one this page didn't already need for itself.
    // required_localization_keys: 'SubmitRisk' is read by this file's own
    // syncTestingNewRiskModalFooter() below as the fallback label for the
    // modal footer's Submit proxy button (window.RiskDetailsForm's embedded
    // .save-risk-form affordance is deliberately unlabeled).
    // CUSTOM:common/cvss-v2-scoring.js MUST precede CUSTOM:common/
    // risk-details-form.js: the RiskScoringMethod widget's CVSS holder
    // (Phase 4d-ii) calls window.CvssV2Scoring.loadFromHiddenFields()/
    // calculateCVSS() directly from its "Score with CVSS" click handler --
    // without this script this page's own "+ Add Risk" modal throws
    // TypeError: Cannot read properties of undefined (reading
    // 'loadFromHiddenFields') the moment CVSS is picked. Same insertion
    // management/index.php and management/view.php already carry.
    // CUSTOM:common/dread-scoring.js MUST precede CUSTOM:common/
    // risk-details-form.js for the same reason: the RiskScoringMethod
    // widget's DREAD holder (Phase 4d-iii) calls
    // window.DreadScoring.calculateDread() at BUILD time, the moment the
    // holder is constructed -- not only from a later change handler like
    // CVSS's own click-triggered call above -- so the script must already
    // be loaded before this page's own "+ Add Risk" modal's canvas is
    // first built, not merely by the time a user interacts with it.
    // CUSTOM:common/owasp-scoring.js MUST precede CUSTOM:common/
    // risk-details-form.js for the same reason as DREAD above: the
    // RiskScoringMethod widget's OWASP holder (Phase 4d-iv) calls
    // window.OwaspScoring.calculateOwasp() at BUILD time.
    // CUSTOM:common/classic-scoring.js MUST precede CUSTOM:common/
    // risk-details-form.js for the same reason: the RiskScoringMethod
    // widget's Classic holder (Risk Scoring -- Classic Inline) calls
    // window.ClassicScoring.calculateClassic() at BUILD time.
    // CUSTOM:common/contributing-risk-scoring.js MUST precede CUSTOM:common/
    // risk-details-form.js for the same reason as Classic/CVSS/DREAD/OWASP's
    // own scoring scripts: the RiskScoringMethod widget's Contributing Risk
    // holder calls window.ContributingRiskScoring.calculateContributingRisk()
    // at BUILD time, the moment the holder is constructed.
    render_header_and_sidebar(['blockUI', 'selectize', 'WYSIWYG', 'multiselect', 'datetimerangepicker', 'CUSTOM:common.js', 'CUSTOM:pages/risk.js', 'CUSTOM:cve_lookup.js', 'CUSTOM:pages/compliance.js', 'gridstack', 'CUSTOM:common/cvss-v2-scoring.js', 'CUSTOM:common/dread-scoring.js', 'CUSTOM:common/owasp-scoring.js', 'CUSTOM:common/classic-scoring.js', 'CUSTOM:common/contributing-risk-scoring.js', 'CUSTOM:common/risk-details-form.js'], ['check_compliance' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu, required_localization_keys: ['SubmitRisk']);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

    $test_audit_id  = (int)$_GET['id'];

    // If team separation is enabled
    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        
        if (!is_user_allowed_to_access($_SESSION['uid'], $test_audit_id, 'audit')) {
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForThisAudit']));
            refresh(build_url("compliance/audits.php"));
        }
    }

    // Check if a framework was updated
    if (isset($_POST['test_result'])) {

        // check permission
        // SR-2101: this submission also writes risk associations
        // (associate_exist_risk_ids / associate_new_risk_id /
        // remove_associated_risk, all handled inside submit_test_result())
        // just like view_test.php's past-audit handler does -- and that
        // handler requires riskmanagement in addition to modify_audits.
        // Without the same check here, a tester who has modify_audits but
        // had riskmanagement revoked could still POST a remembered risk id
        // and recreate a risk relationship the UI no longer shows them.
        if (!isset($_SESSION["modify_audits"]) || $_SESSION["modify_audits"] != 1 || !check_permission("riskmanagement")) {
            set_alert(true, "bad", $lang['NoPermissionForThisAction']);
            refresh();
        }

        // Process submitting test result
        if (submit_test_result()) {
            
            $closed_audit_status = get_setting("closed_audit_status");

            if ($_POST['status'] == $closed_audit_status) {
                refresh(build_url("compliance/audits.php"));
            } else {
                refresh();
            }
        }
    }

    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
?>
<div class="row bg-white">
    <div class="col-12 active-audit-test-container">
    <?php 
        display_testing(); 
    ?>
    </div>
</div>

<script>
    $(document).ready(function() {
        $("[name='team[]']").multiselect({enableFiltering: true, buttonWidth: '100%'});
        $(".datepicker").initAsDatePicker();

    <?php 
        if (!check_permission("riskmanagement")) { 
    ?>
        $(document).on("click", "#submit_test_result", function() {
            $('#edit-test').submit();
        });
    <?php 
        } else { 
    ?>

        // The associate_test hidden input is now appended by
        // syncTestingNewRiskModalCanvas() (below), which runs every time
        // window.RiskDetailsForm (re-)renders #testing-modal-new-risk-canvas
        // -- appending it here, synchronously at document.ready, would run
        // before the engine's async init() has rendered any form at all.

        $('#existing_risks').multiselect({
            enableFiltering: true,
            allSelectedText: "<?= $escaper->escapeHtml($lang['ALL']);?>",
            buttonWidth: '100%',
            maxHeight: 350,
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
        });

        //render multiselects which are not rendered yet after the page is loaded.
        //multiselects which were already rendered contain 'button.multiselect'.
        $(".multiselect:not(button)").multiselect({enableFiltering: true, buttonWidth: '100%', enableCaseInsensitiveFiltering: true,});
        
        $(document).on("click", "#submit_test_result", function() {
            var test_result = $("#test_result").val();
            if(test_result == "Fail") {
                $("#associate-risk").modal("show");
            } else {
                var origin_test_results = $("#origin_test_results").val();
                var risk_permission = $("#origin_test_results").attr("data-permission");
                if ((origin_test_results == "" || origin_test_results == "Fail") && (test_result == "Inconclusive" || test_result == "Pass") &&  risk_permission == 1 && $("#associate_exist_risk_ids").val() != "") {
                    $("#remove-associate-risk").modal("show");
                } else {
                    $('#edit-test').submit();
                }
            }
        });
        $(document).on("click", "#remove-associate-risk-yes", function() {
            $("#remove_associated_risk").val(1);
            $("#associate_exist_risk_ids").val("");
            $('#edit-test').submit();
        });
        $(document).on("click", "#remove-associate-risk-no", function() {
            $('#edit-test').submit();
        });
        $(document).on("click", ".associate_new_risk", function() {
            // No separate reset step is needed here any more:
            // window.RiskDetailsForm.init() (bound to #modal-new-risk's
            // shown.bs.modal below) is idempotent -- it tears down and
            // rebuilds the form fresh on every open -- so the modal already
            // renders clean without calling reset_new_risk_form(), which no
            // longer exists on this page now that display_add_risk() isn't
            // embedded here (that function was only ever defined inline by
            // display_add_risk() itself, includes/display.php).
            $("#modal-new-risk").modal('show');
            $("#associate-risk").modal("hide");
        });
        $(document).on("click", ".associate_existing_risk", function() {
            $("#modal-existing-risk").modal("show");
            $("#associate-risk").modal("hide");
        });
        $(document).on("click", "#add_existing_risks", function() {
            var risk_ids = $("#existing_risks").val().join(",");
            $("#associate_exist_risk_ids").val(risk_ids);
            $('form#edit-test').submit();
            $("#modal-existing-risk").modal("hide");
            return;
        });
        $(document).on("click", "#associate_no", function() {
            $('#edit-test').submit();
        });
        $(document).on("click", ".delete-risk", function() {
            var risk_id = $(this).attr("data-risk-id");
            var risk_ids = $("#associate_exist_risk_ids").val().split(",");
            var index = risk_ids.indexOf(risk_id);
            if (index !== -1) {
                risk_ids.splice(index, 1);
            }
            $("#associate_exist_risk_ids").val(risk_ids.join(","));
            $('form#edit-test').submit();
        });

        // ---- Create Risk modal (#modal-new-risk): footer + associate_test wiring ----
        //
        // Mirrors management/review_risk.php's + Add Risk modal
        // (js/simplerisk/pages/review-risk.js's syncAddRiskModalFooter() /
        // reviewRiskAddModalCanvasObserver): #modal-new-risk's body is an
        // empty canvas div that window.RiskDetailsForm.init() (embedded:
        // true) renders the Cards form into on open. That engine's embedded
        // affordance is a hidden .save-risk-form button with no visible
        // label and no instruction text -- the footer below is filled with a
        // visible proxy button that forwards its click to the real one,
        // since risk.js's save handler resolves the form via
        // $this.closest('form') and a button re-parented out of the form
        // would submit nothing.
        //
        // window.RiskDetailsForm.init() is async (it fetches
        // /ui/risk/template_groups before rendering anything), so neither the
        // form nor its .save-risk-form button exist in the DOM at the moment
        // shown.bs.modal fires. The MutationObserver below closes that gap.
        //
        // It fires ONCE per open, not once per template-group tab switch. It
        // watches the canvas with `{ childList: true }` and no `subtree`, so
        // the only mutation it can see is a change to the canvas's own direct
        // children -- which happens in exactly one place: renderTabs()'s
        // `container.empty().append($form)` on the initial render. Every later
        // tab switch goes through loadTemplateGroup()/buildCanvas(), which only
        // rewrite a NESTED canvas div inside the already-built $form and leave
        // the mount's direct children untouched. That single fire is
        // sufficient: the <form>, its hidden .save-risk-form affordance and the
        // associate_test hidden input are built/appended once and persist
        // across tab switches.
        function syncTestingNewRiskModalFooter() {

            var $modal = $('#modal-new-risk');
            var $footer = $modal.find('#testing-modal-new-risk-footer');

            if (!$footer.length) {
                return;
            }

            var $pane = $modal.find('.tab-pane.tab-data.active');
            if (!$pane.length) {
                $pane = $modal.find('.tab-data').first();
            }
            if (!$pane.length) {
                $pane = $modal;
            }

            var $realSave = $pane.find('.save-risk-form').first();

            if (!$realSave.length) {
                return;
            }

            var $hintSource = $pane.find('.risk-form-actions').prevAll('p').first();
            $footer.find('.sr-modal-hint').text($hintSource.text().trim());

            $footer.find('.testing-new-risk-footer-action').remove();

            var saveLabel = $realSave.text().trim();
            if (!saveLabel) {
                // See the comment above: the engine's hidden .save-risk-form
                // button carries no text of its own.
                saveLabel = _lang['SubmitRisk'];
            }
            $('<button>', {
                'type': 'button',
                'class': 'btn btn-submit testing-new-risk-footer-action',
                'text': saveLabel
            }).on('click', function () {
                $pane.find('.save-risk-form').first().trigger('click');
            }).appendTo($footer);
        }

        // Has to run off the observer rather than once at page load: the
        // engine renders (and re-renders, on every re-open) the whole form
        // asynchronously, so a document.ready-time append would run before
        // there is a <form> to append to.
        function syncTestingNewRiskModalCanvas() {
            $("#testing-modal-new-risk-canvas form").append("<input type='hidden' name='associate_test' value='1'>");
            syncTestingNewRiskModalFooter();
        }

        var testingNewRiskModalCanvasObserver = null;

        $('#modal-new-risk')
            .on('shown.bs.modal', function () {
                window.RiskDetailsForm.init('#testing-modal-new-risk-canvas', { embedded: true });

                var canvasEl = document.getElementById('testing-modal-new-risk-canvas');
                if (canvasEl && typeof MutationObserver !== 'undefined') {
                    if (testingNewRiskModalCanvasObserver) {
                        testingNewRiskModalCanvasObserver.disconnect();
                    }
                    testingNewRiskModalCanvasObserver = new MutationObserver(function () {
                        syncTestingNewRiskModalCanvas();
                    });
                    testingNewRiskModalCanvasObserver.observe(canvasEl, { childList: true });
                }
            })
            .on('hidden.bs.modal', function () {
                if (testingNewRiskModalCanvasObserver) {
                    testingNewRiskModalCanvasObserver.disconnect();
                    testingNewRiskModalCanvasObserver = null;
                }

                // Full teardown on close: every nested GridStack instance,
                // HugeRTE editor and pending timer the engine built for this
                // container. window.RiskDetailsForm.init() tears down and
                // rebuilds from scratch regardless (see the comment on the
                // .associate_new_risk handler above), but destroying here as
                // soon as the modal is gone avoids leaving a live instance
                // (and its window resize binding) around while hidden.
                window.RiskDetailsForm.destroy('#testing-modal-new-risk-canvas');
            });
    <?php
        }
    ?>
    });
</script>

    <?php 
        if (check_permission("riskmanagement")) { 
    ?>

<!-- MODEL WINDOW FOR ASSOCIATE RISK CONFIRM -->
<div id="associate-risk" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="associate-risk" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-body">
                <div class="form-group text-center">
                    <label for=""><?= $escaper->escapeHtml($lang['WouldYouLikeToAssociateThisFailedTestResultWithARisk']); ?></label>
                </div>
                <div class="form-group text-center">
                    <button id="" class="btn btn-primary associate_new_risk" aria-hidden="true"><?= $escaper->escapeHtml($lang['NewRisk']); ?></button>
                    <button id="" class="btn btn-primary associate_existing_risk" aria-hidden="true"><?= $escaper->escapeHtml($lang['ExistingRisk']); ?></button>
                    <button id="associate_no" class=" btn btn-danger"><?= $escaper->escapeHtml($lang['No']); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR SUBMIT RISK -->
<?php
    // sr-modal shell (design-system.md §8, "Form-in-modal") around an empty
    // canvas div that this page's own shown.bs.modal handler (above) hands to
    // window.RiskDetailsForm.init() (embedded: true) -- the reusable
    // Cards-form engine extracted from the standalone Submit Risk page
    // (js/simplerisk/common/risk-details-form.js), replacing the legacy
    // display_add_risk() embed. Mirrors includes/display.php's
    // #review-risk-add-modal shell exactly, except:
    //  - id stays 'modal-new-risk' (not a page-specific id) -- risk.js's
    //    addRisk() success handler hides this modal by that hardcoded id on
    //    the associate_test == 1 branch (js/simplerisk/pages/risk.js), and
    //    the .associate_new_risk trigger above already targets it.
    //  - no data-on-save='refresh-and-close': this modal's success path is
    //    the associate_test branch above, not the refresh-and-close
    //    convention review-risk-add-modal uses.
?>
<div class="modal fade sr-modal" id="modal-new-risk" tabindex="-1" aria-labelledby="modal-new-risk-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <span class="sr-modal-icon"><i class="fa fa-plus" aria-hidden="true"></i></span>
                <h4 class="modal-title" id="modal-new-risk-title"><?= $escaper->escapeHtml($lang['NewRisk']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $escaper->escapeHtml($lang['Close']); ?>"></button>
            </div>
            <div class="modal-body">
                <!-- .sr-qform is REQUIRED, not decorative -- see
                     management/index.php's #submit-risk-container comment.
                     Without it every card the embedded RiskDetailsForm
                     engine renders (not just OWASP's scoring cards) has no
                     background/border/shadow/header styling. -->
                <div id="testing-modal-new-risk-canvas" class="sr-qform"></div>
            </div>
            <div class="modal-footer" id="testing-modal-new-risk-footer">
                <span class="sr-modal-hint"></span>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR SELECT EXISTING RISK -->
<div id="modal-existing-risk" class="modal hide fade in" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['ExistingRisk']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for=""><?= $escaper->escapeHtml($lang['AvailableRisks']); ?></label>
    <?php 
        $risks = get_risks();
        $risk_options = [];
        foreach ($risks as $risk) {
            $risk_options[] = array("value" => $risk["id"], "name" => $risk["subject"]);
        }
        $risk_ids = get_test_result_to_risk_ids($test_audit["result_id"]);
                    
                    create_multiple_dropdown("existing_risks", $risk_ids, null, $risk_options);
    ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button id="add_existing_risks" type="button" class="btn btn-danger"><?= $escaper->escapeHtml($lang['Select']); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR REMOVE ASSOCIATE RISK CONFIRM -->
<div id="remove-associate-risk" class="modal hide fade in" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div class="form-group text-center">
                    <label for=""><?= $escaper->escapeHtml($lang['WouldYouLikeToCloseAllRisksAssociatedWithThisTest']); ?></label>
                </div>
                <div class="form-group text-center">
                    <button id="remove-associate-risk-yes" class="btn btn-primary"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    <button id="remove-associate-risk-no" class="btn btn-danger"><?= $escaper->escapeHtml($lang['No']); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php 
        } 
    ?>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>