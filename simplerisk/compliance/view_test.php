<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
$breadcrumb_title_key = "ViewTest";
$active_sidebar_submenu = "PastAudits";
$active_sidebar_menu = "Compliance";
render_header_and_sidebar(['blockUI', 'selectize', 'WYSIWYG', 'multiselect', 'CUSTOM:pages/risk.js', 'CUSTOM:pages/compliance.js'], ['check_compliance' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu);
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
if (isset($_POST['update_associated_risks']))
{
    // check permission
    if(!isset($_SESSION["modify_audits"]) || $_SESSION["modify_audits"] != 1){
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
        refresh();
    }
    // Process submitting test result
    if(submit_test_result_to_risk()){
        refresh();
    }
}

$test_audit = get_framework_control_test_audit_by_id($test_audit_id);


?>

<div class="row bg-white m-2">
    <div class="col-12">
        <?php display_detail_test(); ?>
    </div>
    </div>
</div>
    
    <!-- MODEL WINDOW FOR SUBMIT RISK -->
    <div id="modal-new-risk" class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="modal-new-risk" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['NewRisk']); ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="tab-content-container" class="tab-data" style="background-color:#fff;padding-top:20px;padding-right:20px;margin-bottom:15px">
                        <?php add_risk_details();?>
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
                    <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['ExistingRisk']); ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding-bottom:250px">
                    <div class="form-group">
                        <label for=""><?php echo $escaper->escapeHtml($lang['AvailableRisks']); ?></label>
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
                    <?php
                    ?>

                </div>
                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button id="add_existing_risks" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Select']); ?></button>
                </div>
            </div>
        </div>
    </div>

    </body>
    <script>
        $(document).ready(function(){
            $("#risk-submit-form").append("<input type='hidden' name='associate_test' value='1'>");
            $('#existing_risks').multiselect({
                enableFiltering: true,
                allSelectedText: "<?php echo $escaper->escapeHtml($lang['ALL']);?>",
                buttonWidth: '100%',
                includeSelectAllOption: true,
                enableCaseInsensitiveFiltering: true,
            });

            $(document).on("click", "#submit_test_result", function(){
                if($("#test_result").val() == "Fail") {
                    $("#associate-risk").modal();
                } else {
                    if($("#origin_test_results").val() == "Fail" && $("#origin_test_results").attr("data-permission") == 1) {
                        $("#remove-associate-risk").modal();
                    } else{
                        $('#edit-test').submit();
                    }
                }
            });
            $(document).on("click", "#remove-associate-risk-yes", function(){
                $("#remove_associated_risk").val(1);
                $("#associate_exist_risk_ids").val("");
                $('#edit-test').submit();
            });
            $(document).on("click", "#remove-associate-risk-no", function(){
                $('#edit-test').submit();
            });
            $(document).on("click", ".associate_new_risk", function(){
                $("#modal-new-risk").modal("show");
                $("#associate-risk").modal("hide");
            });
            $(document).on("click", ".associate_existing_risk", function(){
                $("#modal-existing-risk").modal("show");
                $("#associate-risk").modal("hide");
            });
            $(document).on("click", "#add_existing_risks", function(){
                var risk_ids = $("#existing_risks").val().join(",");
                $("#associate_exist_risk_ids").val(risk_ids);
                $('form#edit-test').submit();
                $("#modal-existing-risk").modal("hide");
                return;
            });
            $(document).on("click", "#associate_no", function(){
                $('#edit-test').submit();
            });
            $(document).on("click", ".delete-risk", function(){
                var risk_id = $(this).attr("data-risk-id");
                var risk_ids = $("#associate_exist_risk_ids").val().split(",");
                var index = risk_ids.indexOf(risk_id);
                if (index !== -1) {
                    risk_ids.splice(index, 1);
                }
                $("#associate_exist_risk_ids").val(risk_ids.join(","));
                $('form#edit-test').submit();
            });

            // Add WYSIWYG editor to risk detail
            init_minimun_editor("#risk-submit-form [name=assessment]");
            init_minimun_editor("#risk-submit-form [name=notes]");
        });
    </script>
<?php  
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>
