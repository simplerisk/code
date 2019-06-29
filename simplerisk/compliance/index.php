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

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();


if (!isset($_SESSION))
{
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    session_name('SimpleRisk');
    session_start();
}

// Include the language file
require_once(language_file());

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
  set_unauthenticated_redirect();
  header("Location: ../index.php");
  exit(0);
}

// Include the CSRF-magic library
// Make sure it's called after the session is properly setup
include_csrf_magic();

// Enforce that the user has access to compliance
enforce_permission_compliance();

// Check if adding test
if(isset($_POST['add_test'])){
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
    $teams                      = isset($_POST['team']) ? $_POST['team'] : [];

    if (!$last_date)
        $last_date = "0000-00-00";
    else {
        if ($last_date && strtotime($last_date) > strtotime(date('Ymd'))) {
            set_alert(true, "bad", $lang['InvalidLastTestDate']);
            refresh();
        }
    }

    if ($test_frequency < 0) {
        set_alert(true, "bad", $lang['InvalidTestFrequency']);
        refresh();
    }

    if ($approximate_time < 0) {
        set_alert(true, "bad", $lang['InvalidApproximateTime']);
        refresh();
    }

    // Add a framework control test
    add_framework_control_test($tester, $test_frequency, $name, $objective, $test_steps, $approximate_time, $expected_results, $framework_control_id, $additional_stakeholders, $last_date, false, $teams);
    
    set_alert(true, "good", $lang['TestSuccessCreated']);
    
    // Refresh current page
    refresh();
}

// Check if editing test
if(isset($_POST['update_test'])){
    
    $test_id                    = (int)$_POST['test_id'];

    // If team separation is enabled
    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test')) {
            set_alert(true, "bad", $lang['NoPermissionForThisTest']);
            refresh();
        }
    }
    
    $today_dt                   = strtotime(date('Ymd'));
    $tester                     = (int)$_POST['tester'];
    $teams                      = isset($_POST['team']) ? $_POST['team'] : [];
    $additional_stakeholders    = empty($_POST['additional_stakeholders_edit']) ? "" : implode(",", $_POST['additional_stakeholders_edit']);
    $test_frequency             = (int)$_POST['test_frequency'];
    $last_date                  = get_standard_date_from_default_format($_POST['last_date']);
    $next_date                  = get_standard_date_from_default_format($_POST['next_date']);
    $name                       = $_POST['name'];
    $objective                  = $_POST['objective'];
    $test_steps                 = $_POST['test_steps'];
    $approximate_time           = !empty($_POST['approximate_time']) ? (int)$_POST['approximate_time'] : 0;
    $expected_results           = $_POST['expected_results'];

    if ($test_frequency < 0) {
        set_alert(true, "bad", $lang['InvalidTestFrequency']);
        //$test_frequency = false;
        refresh();
    }

    if ($approximate_time < 0) {
        set_alert(true, "bad", $lang['InvalidApproximateTime']);
        //$approximate_time = false;
        refresh();
    }

    if (!$last_date)
        $last_date = false;
    else {
        if (strtotime($last_date) > $today_dt) {
            set_alert(true, "bad", $lang['InvalidLastTestDate']);
            //$last_date = false;
            refresh();
        }
    }

    if (!$next_date)
        $next_date = false;
    else {
        if (strtotime($next_date) < $today_dt) {
            set_alert(true, "bad", $lang['InvalidNextTestDate']);
            //$next_date = false;
            refresh();
        }
    }

    if ($last_date && $next_date && strtotime($next_date) < strtotime($last_date)) {
            set_alert(true, "bad", $lang['InvalidNextTestDateLastTestDateOrder']);
            //$next_date = false;
            //$last_date = false;
            refresh();
    }

    // Update a framework control test
    update_framework_control_test($test_id, $tester, $test_frequency, $name, $objective, $test_steps, $approximate_time, $expected_results, $last_date, $next_date, false, $additional_stakeholders, $teams);
    
    set_alert(true, "good", $lang['TestSuccessUpdated']);
    
    // Refresh current page
    refresh();
}

// Check if deleting test
if(isset($_POST['delete_test'])){
    $test_id = (int)$_POST['test_id'];

    // If team separation is enabled
    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test')) {
            set_alert(true, "bad", $lang['NoPermissionForThisTest']);
            refresh();
        }
    }

    // Add a framework control
    delete_framework_control_test($test_id);
    
    set_alert(true, "good", $lang['SuccessTestDeleted']);
    
    // Refresh current page
    refresh();
}

?>

<!doctype html>
<html>

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.dataTables.js"></script>
    <script src="../js/pages/compliance.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>

    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <?php
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
                    <div class="span12">
                        <span><?php echo $escaper->escapeHtml($lang['ControlFramework']); ?>: &nbsp;</span>
                        <select id="filter_by_control_framework" class="form-field form-control" multiple="multiple">
                            <?php 
                                echo "<option selected value=\"-1\">".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                                $options = getAvailableControlFrameworkList();
                                is_array($options) || $options = array();
                                foreach($options as $option){
                                    echo "<option selected value=\"".(int)$option['value']."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                                }
                            ?>
                        </select>
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
            <input type="text" name="name" required="" value="" class="form-control">

            <label for=""><?php echo $escaper->escapeHtml($lang['Tester']); ?></label>
            <?php create_dropdown("enabled_users", NULL, "tester", false, false, false); ?>
            
            <label for=""><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
            <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders_add"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['Teams']); ?></label>
            <?php create_multiple_dropdown("team"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestFrequency']); ?></label>
            <input type="number" min="0" name="test_frequency" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span>
            
            <label for=""><?php echo $escaper->escapeHtml($lang['LastTestDate']); ?></label>
            <input type="text" name="last_date" value="" class="form-control datepicker">

            <label for=""><?php echo $escaper->escapeHtml($lang['Objective']); ?></label>
            <textarea name="objective" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestSteps']); ?></label>
            <textarea name="test_steps" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['ApproximateTime']); ?></label>
            <input type="number" min="0" name="approximate_time" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['minutes']); ?>)</span>

            <label for=""><?php echo $escaper->escapeHtml($lang['ExpectedResults']); ?></label>
            <textarea name="expected_results" class="form-control" rows="6" style="width:100%;"></textarea>

            <input type="hidden" name="framework_control_id" value="">

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
            <input type="text" name="name" required="" value="" class="form-control">

            <label for=""><?php echo $escaper->escapeHtml($lang['Tester']); ?></label>
            <?php create_dropdown("enabled_users", NULL, "tester", false, false, false); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
            <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders_edit"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['Teams']); ?></label>
            <?php create_multiple_dropdown("team"); ?>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestFrequency']); ?></label>
            <input type="number" min="0" name="test_frequency" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span>
            
            <label for=""><?php echo $escaper->escapeHtml($lang['LastTestDate']); ?></label>
            <input type="text" name="last_date" value="" class="form-control datepicker"> 
            
            <label for=""><?php echo $escaper->escapeHtml($lang['NextTestDate']); ?></label>
            <input type="text" name="next_date" value="" class="form-control datepicker"> 
            
            <label for=""><?php echo $escaper->escapeHtml($lang['Objective']); ?></label>
            <textarea name="objective" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['TestSteps']); ?></label>
            <textarea name="test_steps" class="form-control" rows="6" style="width:100%;"></textarea>

            <label for=""><?php echo $escaper->escapeHtml($lang['ApproximateTime']); ?></label>
            <input type="number" min="0" name="approximate_time" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['minutes']); ?>)</span>

            <label for=""><?php echo $escaper->escapeHtml($lang['ExpectedResults']); ?></label>
            <textarea name="expected_results" class="form-control" rows="6" style="width:100%;"></textarea>

            <input type="hidden" name="test_id" value="">

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
        });
    </script>

    <?php display_set_default_date_format_script(); ?>
</body>
</html>
