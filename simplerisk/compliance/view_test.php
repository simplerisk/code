<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    $breadcrumb_title_key = "ViewTest";
    $active_sidebar_submenu = "PastAudits";
    $active_sidebar_menu = "Compliance";
    render_header_and_sidebar(['blockUI', 'selectize', 'WYSIWYG', 'multiselect', 'datetimerangepicker', 'CUSTOM:common.js', 'CUSTOM:pages/risk.js', 'CUSTOM:cve_lookup.js', 'CUSTOM:pages/compliance.js'], ['check_compliance' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu);

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
            refresh($_SESSION['base_url']."/compliance/past_audits.php");
        }
    }

    // Check if a framework was updated
    if (isset($_POST['update_associated_risks'])) {

        // check permission
        if (!isset($_SESSION["modify_audits"]) || $_SESSION["modify_audits"] != 1 || !check_permission("riskmanagement")) {
            set_alert(true, "bad", $lang['NoPermissionForThisAction']);
            refresh();
        }

        // Process submitting test result
        if (submit_test_result_to_risk()) {
            refresh();
        }
    }

    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
?>

<div class="row bg-white">
    <div class="col-12 past-audit-test-container">
    <?php 
        display_detail_test();
    ?>
    </div>
</div>

<?php 
    if (check_permission("riskmanagement")) { 
?>

<!-- MODEL WINDOW FOR SUBMIT RISK -->
<div id="modal-new-risk" class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="modal-new-risk" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['NewRisk']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="tab-content-container" class="tab-data" style="background-color:#fff;padding-top:20px;padding-right:20px;margin-bottom:15px">
    <?php
                    display_add_risk();
    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR SELECT EXISTING RISK -->
<div id="modal-existing-risk" class="modal hide fade in" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['ExistingRisk']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div>
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
                <button class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button id="add_existing_risks" class="btn btn-danger"><?= $escaper->escapeHtml($lang['Select']); ?></button>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {

        // Have to do the selector like this, because #risk-submit-form only returns a single result and sadly
        // this functionality is built in a way that there're duplicate IDs
        $("form[id='risk-submit-form']").append("<input type='hidden' name='associate_test' value='1'>");

        $(".datepicker").initAsDatePicker();

        $('#existing_risks').multiselect({
            enableFiltering: true,
            allSelectedText: "<?= $escaper->escapeHtml($lang['ALL']);?>",
            buttonWidth: '100%',
            maxHeight: 300,
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
        });

        $('#tab-content-container select.assets-asset-groups-select').each(function() {
            setupAssetsAssetGroupsWidget($(this));
        });
        
        //render multiselects which are not rendered yet after the page is loaded.
        //multiselects which were already rendered contain 'button.multiselect'.
        $(".multiselect:not(button)").multiselect({enableFiltering: true, buttonWidth: '100%', enableCaseInsensitiveFiltering: true,});

        $(document).on("click", "#submit_test_result", function() {
            if ($("#test_result").val() == "Fail") {
                $("#associate-risk").modal();
            } else {
                if ($("#origin_test_results").val() == "Fail" && $("#origin_test_results").attr("data-permission") == 1) {
                    $("#remove-associate-risk").modal();
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

            // Reset the form
            reset_new_risk_form("#reset_form");

            $("#modal-new-risk").modal("show");
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

        // If there're template tabs we have to separately initialize the WYSIWYG editors
        if ($("#template_group_id").length > 0) {
            // Since tinymce stores the editor instances indexed by the textarea's id we have to make sure it's unique(which it is NOT by default)
            // so we're appending the template's id to the textarea's ID to make it unique 

            $("[name='assessment']").each(function() {
                let template_group_id = $(this).closest('form').find('#template_group_id').val();
                $(this).attr('id', 'assessment_' + template_group_id);
                init_minimun_editor("#assessment_" + template_group_id);
            });

            $("[name='notes']").each(function() {
                let template_group_id = $(this).closest('form').find('#template_group_id').val();
                $(this).attr('id', 'notes_' + template_group_id);
                init_minimun_editor("#notes_" + template_group_id);
            });

        } else {
            // init tinyMCE WYSIWYG editor
            init_minimun_editor("#risk-submit-form [name=assessment]");
            init_minimun_editor("#risk-submit-form [name=notes]");
        }
    });
</script>
<?php
    } // End of permission check
?>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>