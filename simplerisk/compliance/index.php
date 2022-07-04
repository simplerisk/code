<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/assets.php'));
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

// Check if adding test
if(isset($_POST['add_test'])){
    $error = false;
    $error_msg = "";
    // check permission
    if(!isset($_SESSION["define_tests"]) || $_SESSION["define_tests"] != 1){
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
    } else {
        $tester                     = (int)$_POST['tester'];
        $additional_stakeholders    = empty($_POST['additional_stakeholders_add']) ? "" : implode(",", $_POST['additional_stakeholders_add']);
        $test_frequency             = (int)$_POST['test_frequency'];
        $last_date                  = get_standard_date_from_default_format($_POST['last_date']);
        $name                       = $_POST['name'];
        $objective                  = $_POST['objective'];
        $test_steps                 = $_POST['test_steps'];
        $approximate_time           = !empty($_POST['approximate_time']) ? $_POST['approximate_time'] : 0;
        $expected_results           = $_POST['expected_results'];
        $framework_control_id       = (int)$_POST['framework_control_id'];
        $teams                      = isset($_POST['team']) ? array_filter($_POST['team'], 'ctype_digit') : [];
        $tags                       = empty($_POST['tags']) ? [] : $_POST['tags'];

        if (!$last_date)
            $last_date = "0000-00-00";
        else {
            if ($last_date && strtotime($last_date) > strtotime(date('Ymd'))) {
                $error = true;
                $error_msg = $lang['InvalidLastTestDate'];
            }
        }
        if ($test_frequency < 0) {
            $error = true;
            $error_msg = $lang['InvalidTestFrequency'];
        }

        if ($approximate_time < 0) {
            $error = true;
            $error_msg = $lang['InvalidApproximateTime'];
        }
        if($error !== true) {
            // Add a framework control test
            add_framework_control_test($tester, $test_frequency, $name, $objective, $test_steps, $approximate_time, $expected_results, $framework_control_id, $additional_stakeholders, $last_date, false, $teams, $tags);
            set_alert(true, "good", $lang['TestSuccessCreated']);

        } else {
            set_alert(true, "bad", $error_msg);
        }
    }
}

// Check if editing test
if(isset($_POST['update_test'])){
    $error = false;
    $error_msg = "";
    // check permission
    if(!isset($_SESSION["edit_tests"]) || $_SESSION["edit_tests"] != 1){
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
    } else {
    
        $test_id                    = (int)$_POST['test_id'];

        // If team separation is enabled
        if (team_separation_extra()) {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
            if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test')) {
                $error = true;
                $error_msg = $lang['NoPermissionForThisTest'];
            }
        }
        
        $today_dt                   = strtotime(date('Ymd'));
        $tester                     = (int)$_POST['tester'];
        $teams                      = isset($_POST['team']) ? array_filter($_POST['team'], 'ctype_digit') : [];
        $additional_stakeholders    = empty($_POST['additional_stakeholders_edit']) ? "" : implode(",", $_POST['additional_stakeholders_edit']);
        $test_frequency             = (int)$_POST['test_frequency'];
        $last_date                  = get_standard_date_from_default_format($_POST['last_date']);
        $next_date                  = get_standard_date_from_default_format($_POST['next_date']);
        $name                       = $_POST['name'];
        $objective                  = $_POST['objective'];
        $test_steps                 = $_POST['test_steps'];
        $approximate_time           = !empty($_POST['approximate_time']) ? (int)$_POST['approximate_time'] : 0;
        $expected_results           = $_POST['expected_results'];
        $tags                       = empty($_POST['tags']) ? [] : $_POST['tags'];

        if ($test_frequency < 0) {
            $error = true;
            $error_msg = $lang['InvalidTestFrequency'];
        }

        if ($approximate_time < 0) {
            $error = true;
            $error_msg = $lang['InvalidApproximateTime'];
        }

        if (!$last_date)
            $last_date = false;
        else {
            if (strtotime($last_date) > $today_dt) {
                $error = true;
                $error_msg = $lang['InvalidLastTestDate'];
            }
        }

        if (!$next_date)
            $next_date = false;
        else {
//            if (strtotime($next_date) < $today_dt) {
//                $error = true;
//                $error_msg = $lang['InvalidNextTestDate'];
//            }
        }
        
        if ($last_date && $next_date && strtotime($next_date) < strtotime($last_date)) {
            $error = true;
            $error_msg = $lang['InvalidNextTestDateLastTestDateOrder'];
        }
        if($error !== true) {
            // Update a framework control test
            update_framework_control_test($test_id, $tester, $test_frequency, $name, $objective, $test_steps, $approximate_time, $expected_results, $last_date, $next_date, false, $additional_stakeholders, $teams, $tags);
            
            set_alert(true, "good", $lang['TestSuccessUpdated']);
        } else {
            set_alert(true, "bad", $error_msg);
        }
    }
}

// Check if deleting test
if(isset($_POST['delete_test'])){
    $error = false;
    $error_msg = "";
    // check permission
    if(!isset($_SESSION["delete_tests"]) || $_SESSION["delete_tests"] != 1){
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
    } else {
        $test_id = (int)$_POST['test_id'];

        // If team separation is enabled
        if (team_separation_extra()) {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
            if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test')) {
                $error = true;
                $error_msg = $lang['NoPermissionForThisTest'];
            }
        }
        if($error !== true) {
            // Add a framework control
            delete_framework_control_test($test_id);
            set_alert(true, "good", $lang['SuccessTestDeleted']);
        } else {
            set_alert(true, "bad", $error_msg);
        }
    }
}

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

        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
    <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/pages/compliance.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>

    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
    <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>    
    <style>
        #test--add .modal-header, #test--edit .modal-header {
            color: #ffffff;
        }
        
        .delete-row, .edit-test {
            cursor: pointer;
        }
    </style>
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
                <?php view_compliance_menu("DefineTests"); ?>
            </div>
            <div class="span9 compliance-content-container">
                <div class="row-fluid">
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlFramework']);?>:</h4>
                            <select id="filter_by_control_framework" class="form-field form-control" multiple="multiple">
                                <?php 
                                    $filter_by_control = array();
                                    if(isset($_POST['filter_by_control'])) $filter_by_control = explode(",",$_POST['filter_by_control']);
                                    if(in_array("-1",$filter_by_control) || count($filter_by_control)== 0) 
                                        echo "<option selected value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                    else 
                                        echo "<option value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                    $options = getAvailableControlFrameworkList(true);
                                    is_array($options) || $options = array();
                                    foreach($options as $option){
                                        if(in_array($option['value'],$filter_by_control) || count($filter_by_control)== 0)
                                            echo "<option selected value=\"".(int)$option['value']."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                        else 
                                            echo "<option value=\"".(int)$option['value']."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlFamily']); ?>:</h4>
                            <select id="filter_by_control_family" class="" multiple="multiple">
                                <?php 
                                    $filter_by_control_family = array();
                                    if(isset($_POST['filter_by_control_family'])) $filter_by_control_family = explode(",",$_POST['filter_by_control_family']);
                                    if(in_array("-1",$filter_by_control_family) || count($filter_by_control_family)== 0) 
                                        echo "<option selected value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                    else 
                                        echo "<option value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";

                                    $options = getAvailableControlFamilyList();  
                                    is_array($options) || $options = array();
                                    foreach($options as $option){
                                        if(in_array($option['value'],$filter_by_control_family) || count($filter_by_control)== 0)
                                            echo "<option selected value=\"".(int)$option['value']."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                        else 
                                            echo "<option value=\"".(int)$option['value']."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                    } 
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="well">
                            <h4><?php echo $escaper->escapeHtml($lang['ControlName']); ?>:</h4>
                            <?php $filter_by_control_text = isset($_POST['filter_by_control_text'])?$_POST['filter_by_control_text']:""; ?>
                            <input type="text" class="form-control" id="filter_by_control_text" value="<?php echo $escaper->escapeHtml($filter_by_control_text);?>">
                        </div>
                    </div>
                </div>
                <div class="row-fluid">
                    <div class="span12">
                        <?php display_framework_controls_in_compliance(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODEL WINDOW FOR ADDING TEST -->
    <div id="test--add" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="test--add" aria-hidden="true">
      <form class="" id="test-new-form" method="post" autocomplete="off">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['TestAddHeader']); ?></h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for=""><?php echo $escaper->escapeHtml($lang['TestName']); ?></label>
            <input type="text" name="name" required="" value="" class="form-control" maxlength="1000">

            <label for=""><?php echo $escaper->escapeHtml($lang['Tester']); ?></label>
            <?php create_dropdown("enabled_users", NULL, "tester", false, false, false); ?>
            
            <label for=""><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
            <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders_add"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['Teams']); ?></label>
            <?php create_multiple_dropdown("team"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestFrequency']); ?></label>
            <input type="number" min="0" max="2147483647" name="test_frequency" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span>
            
            <label for=""><?php echo $escaper->escapeHtml($lang['LastTestDate']); ?></label>
            <input type="text" name="last_date" value="" class="form-control datepicker">

            <label for=""><?php echo $escaper->escapeHtml($lang['Objective']); ?></label>
            <textarea name="objective" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestSteps']); ?></label>
            <textarea name="test_steps" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['ApproximateTime']); ?></label>
            <input type="number" min="0" max="2147483647" name="approximate_time" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['minutes']); ?>)</span>

            <label for=""><?php echo $escaper->escapeHtml($lang['ExpectedResults']); ?></label>
            <textarea name="expected_results" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['Tags']); ?></label>
            <select class="test_tags" readonly name="tags[]" multiple placeholder="<?php echo $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
            <div class="tag-max-length-warning" style="margin-top:-10px"><?php echo $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></div>

            <input type="hidden" name="framework_control_id" value="">
            <input type="hidden" name="filter_by_control" value="">
            <input type="hidden" name="filter_by_control_family" value="">
            <input type="hidden" name="filter_by_control_text" value="">

          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
          <button type="submit" name="add_test" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
        </div>
      </form>
    </div>
    
    <!-- MODEL WINDOW FOR EDITING TEST -->
    <div id="test--edit" class="modal hide" tabindex="-1" role="dialog" aria-hidden="true">
      <form class="" id="test-edit-form" method="post" autocomplete="off">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['TestEditHeader']); ?></h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for=""><?php echo $escaper->escapeHtml($lang['TestName']); ?></label>
            <input type="text" name="name" required="" value="" class="form-control" maxlength="1000">

            <label for=""><?php echo $escaper->escapeHtml($lang['Tester']); ?></label>
            <?php create_dropdown("enabled_users", NULL, "tester", false, false, false); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
            <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders_edit"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['Teams']); ?></label>
            <?php create_multiple_dropdown("team"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestFrequency']); ?></label>
            <input type="number" min="0" max="2147483647" name="test_frequency" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span>
            
            <label for=""><?php echo $escaper->escapeHtml($lang['LastTestDate']); ?></label>
            <input type="text" name="last_date" value="" class="form-control datepicker"> 
            
            <label for=""><?php echo $escaper->escapeHtml($lang['NextTestDate']); ?></label>
            <input type="text" name="next_date" value="" class="form-control datepicker"> 
            
            <label for=""><?php echo $escaper->escapeHtml($lang['Objective']); ?></label>
            <textarea name="objective" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestSteps']); ?></label>
            <textarea name="test_steps" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['ApproximateTime']); ?></label>
            <input type="number" min="0" max="2147483647" name="approximate_time" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['minutes']); ?>)</span>

            <label for=""><?php echo $escaper->escapeHtml($lang['ExpectedResults']); ?></label>
            <textarea name="expected_results" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['Tags']); ?></label>
            <select class="test_tags" readonly name="tags[]" multiple placeholder="<?php echo $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
            <div class="tag-max-length-warning" style="margin-top:-10px"><?php echo $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></div>

            <input type="hidden" name="test_id" value="">
            <input type="hidden" name="filter_by_control" value="">
            <input type="hidden" name="filter_by_control_family" value="">
            <input type="hidden" name="filter_by_control_text" value="">

          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
          <button type="submit" name="update_test" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
        </div>
      </form>
    </div>
    
    <!-- MODEL WINDOW FOR PROJECT DELETE CONFIRM -->
    <div id="test--delete" class="modal hide fade" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-body">

        <form class="" action="" method="post">
          <div class="form-group text-center">
            <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisTest']); ?></label>
            <input type="hidden" name="test_id" value="" />
            <input type="hidden" name="filter_by_control" value="">
            <input type="hidden" name="filter_by_control_family" value="">
            <input type="hidden" name="filter_by_control_text" value="">
          </div>

          <div class="form-group text-center project-delete-actions">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" name="delete_test" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
          </div>
        </form>

      </div>
    </div>

    <script>
        $( document ).ready(function() {
            $("#additional_stakeholders_add").multiselect();
            $("#additional_stakeholders_edit").multiselect();
            $("[name='team[]']").multiselect();

            //Have to remove the 'fade' class for the shown event to work for modals
            $('#test--add, #test--edit').on('shown.bs.modal', function() {
                $(this).find('.modal-body').scrollTop(0);
            });
            $("form").submit(function(e){
                $("input[name=filter_by_control]").val($("#filter_by_control_framework").val());
                $("input[name=filter_by_control_family]").val($("#filter_by_control_family").val());
                $("input[name=filter_by_control_text]").val($("#filter_by_control_text").val());
                return true;
            });
        });
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }
    </script>

    <?php display_set_default_date_format_script(); ?>
</body>
</html>
