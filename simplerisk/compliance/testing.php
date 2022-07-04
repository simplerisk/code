<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../includes/governance.php'));
require_once(realpath(__DIR__ . '/../includes/compliance.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_compliance" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

$test_audit_id  = (int)$_GET['id'];

// If team separation is enabled
if (team_separation_extra()) {
    //Include the team separation extra
    require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
    
    if (!is_user_allowed_to_access($_SESSION['uid'], $test_audit_id, 'audit')) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForThisAudit']));
        refresh($_SESSION['base_url']."/compliance/active_audits.php");
    }
}

// Check if a framework was updated
if (isset($_POST['test_result']))
{
    // check permission
    if(!isset($_SESSION["modify_audits"]) || $_SESSION["modify_audits"] != 1){
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
        refresh();
    }
    // Process submitting test result
    if(submit_test_result()){
        $closed_audit_status = get_setting("closed_audit_status");
        if($_POST['status'] == $closed_audit_status)
        {
            refresh($_SESSION['base_url']."/compliance/active_audits.php");
        }
        else
        {
            refresh();
        }
    }
}

$test_audit = get_framework_control_test_audit_by_id($test_audit_id);

?>
<!doctype html>
<html>

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);
?>
    <script src="../js/jquery.easyui.min.js?<?php echo current_version("app"); ?>"></script>
<?php
        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/cve_lookup.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/basescript.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/common.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/pages/risk.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/pages/compliance.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>

    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

    <link rel="stylesheet" href="../css/easyui.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
    
    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
    <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>    
    
</head>

<body>

    <?php
        view_top_menu("Compliance");

        // Get any alert messages
        get_alert();
    ?>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_compliance_menu("ActiveAudits"); ?>
            </div>
            <div class="span9 compliance-content-container content-margin-height">
                <div class="row-fluid">
                    <div class="span12">
                        <?php display_testing(); ?>
                    </div>
                </div>
                <br>
            </div>
        </div>
    </div>

    <!-- MODEL WINDOW FOR ASSOCIATE RISK CONFIRM -->
    <div id="associate-risk" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="associate-risk" aria-hidden="true">
        <div class="modal-body">
            <div class="form-group text-center">
                <label for=""><?php echo $escaper->escapeHtml($lang['WouldYouLikeToAssociateThisFailedTestResultWithARisk']); ?></label>
            </div>

            <div class="form-group text-center">
                <button id="" class="btn btn-default associate_new_risk" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['NewRisk']); ?></button>
                <button id="" class="btn btn-default associate_existing_risk" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['ExistingRisk']); ?></button>
                <button id="associate_no" class=" btn btn-danger"><?php echo $escaper->escapeHtml($lang['No']); ?></button>
            </div>
        </div>
    </div>

    <!-- MODEL WINDOW FOR SUBMIT RISK -->
    <div id="modal-new-risk" class="modal hide fade no-padding in" tabindex="-1" role="dialog" aria-labelledby="associate-risk" aria-hidden="true" style="width: 1000px;margin-left:-500px;">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['NewRisk']); ?></h4>
        </div>
        <div class="modal-body">
            <div id="tab-content-container"  style="background-color:#fff;padding-top:20px;padding-right:20px;margin-bottom:15px">
            <?php add_risk_details();?>
            </div>
        </div>
    </div>

    <!-- MODEL WINDOW FOR SELECT EXISTING RISK -->
    <div id="modal-existing-risk" class="modal hide fade no-padding in" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['ExistingRisk']); ?></h4>
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

    <!-- MODEL WINDOW FOR REMOVE ASSOCIATE RISK CONFIRM -->
    <div id="remove-associate-risk" class="modal hide fade in" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-body">
            <div class="form-group text-center">
                <label for=""><?php echo $escaper->escapeHtml($lang['WouldYouLikeToCloseAllRisksAssociatedWithThisTest']); ?></label>
            </div>

            <div class="form-group text-center">
                <button id="remove-associate-risk-yes" class="btn btn-default"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                <button id="remove-associate-risk-no" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['No']); ?></button>
            </div>
        </div>
    </div>

    <style>
        #modal-new-risk .span8{width:80%;}
        #modal-new-risk .span10{width:100%;}
        span5.left-panel {width:50%;}
    </style>

    <?php display_set_default_date_format_script(); ?>
    </body>
    <script>
        $(document).ready(function(){
            $(".datepicker").datepicker();
            $('#tab-content-container select.assets-asset-groups-select').each(function() {
                setupAssetsAssetGroupsWidget($(this));
            });
            $("#risk-submit-form").append("<input type='hidden' name='associate_test' value='1'>");
            $('#existing_risks').multiselect({
                enableFiltering: true,
                allSelectedText: "<?php echo $escaper->escapeHtml($lang['ALL']);?>",
                buttonWidth: '100%',
                includeSelectAllOption: true,
                enableCaseInsensitiveFiltering: true,
            });

            $(document).on("click", "#submit_test_result", function(){
                var test_result = $("#test_result").val();
                if(test_result == "Fail") {
                    $("#associate-risk").modal();
                } else {
                    var origin_test_results = $("#origin_test_results").val();
                    var risk_permission = $("#origin_test_results").attr("data-permission");
                    if((origin_test_results == "" || origin_test_results == "Fail") && (test_result == "Inconclusive" || test_result == "Pass") &&  risk_permission == 1 && $("#associate_exist_risk_ids").val() != "") {
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
                $("#modal-new-risk").modal();
                $("#associate-risk").modal("hide");
            });
            $(document).on("click", ".associate_existing_risk", function(){
                $("#modal-existing-risk").modal();
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


        });
    </script>
</html>
