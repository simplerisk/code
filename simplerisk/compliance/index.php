<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'datetimerangepicker', 'CUSTOM:pages/compliance.js', 'CUSTOM:common.js'], ['check_compliance' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

    // Check if adding test
    if (isset($_POST['add_test'])) {

        $error = false;
        $error_msg = "";

        // check permission
        if (!isset($_SESSION["define_tests"]) || $_SESSION["define_tests"] != 1) {

            // display an alert
            set_alert(true, "bad", $lang['NoPermissionForThisAction']);

        } else {

            $tester = (int)$_POST['tester'];
            $additional_stakeholders = empty($_POST['additional_stakeholders_add']) ? "" : implode(",", $_POST['additional_stakeholders_add']);
            $test_frequency = (int)$_POST['test_frequency'];
            $last_date = get_standard_date_from_default_format($_POST['last_date']);
            $name = !empty($_POST['name']) ? trim($_POST['name']) : "";
            $objective = $_POST['objective'];
            $test_steps = $_POST['test_steps'];
            $approximate_time = !empty($_POST['approximate_time']) ? $_POST['approximate_time'] : 0;
            $expected_results = $_POST['expected_results'];
            $framework_control_id = (int)$_POST['framework_control_id'];
            $teams = isset($_POST['team']) ? array_filter($_POST['team'], 'ctype_digit') : [];
            $tags = empty($_POST['tags']) ? [] : $_POST['tags'];

            if (!$name) {

                $error = true;
                $error_msg = _lang('FieldRequired', array("field"=>$lang['TestName']));

            }

            if (!$last_date) {
                $last_date = "0000-00-00";
            } else {
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

                // display an alert
                set_alert(true, "good", $lang['TestSuccessCreated']);

            } else {

                // display an alert
                set_alert(true, "bad", $error_msg);

            }
        }
    }

    // Check if editing test
    if (isset($_POST['update_test'])) {

        $error = false;
        $error_msg = "";

        // check permission
        if (!isset($_SESSION["edit_tests"]) || $_SESSION["edit_tests"] != 1) {

            // display an alert
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
            
            $today_dt = strtotime(date('Ymd'));
            $tester = isset($_POST['tester']) ? (int)$_POST['tester'] : null;
            $teams = isset($_POST['team']) ? array_filter($_POST['team'], 'ctype_digit') : [];
            $additional_stakeholders = empty($_POST['additional_stakeholders_edit']) ? "" : implode(",", $_POST['additional_stakeholders_edit']);
            $test_frequency = (int)$_POST['test_frequency'];
            $last_date = get_standard_date_from_default_format($_POST['last_date']);
            $next_date = get_standard_date_from_default_format($_POST['next_date']);
            $name = !empty($_POST['name']) ? trim($_POST['name']) : "";
            $objective = $_POST['objective'];
            $test_steps = $_POST['test_steps'];
            $approximate_time = !empty($_POST['approximate_time']) ? (int)$_POST['approximate_time'] : 0;
            $expected_results = $_POST['expected_results'];
            $tags = empty($_POST['tags']) ? [] : $_POST['tags'];

            if (!$name) {

                $error = true;
                $error_msg = _lang('FieldRequired', array("field"=>$lang['TestName']));

            }

            if ($test_frequency < 0) {
                $error = true;
                $error_msg = $lang['InvalidTestFrequency'];
            }

            if ($approximate_time < 0) {
                $error = true;
                $error_msg = $lang['InvalidApproximateTime'];
            }

            if (!$last_date) {
                $last_date = false;
            } else {
                if (strtotime($last_date) > $today_dt) {
                    $error = true;
                    $error_msg = $lang['InvalidLastTestDate'];
                }
            }

            if (!$next_date) {
                $next_date = false;
            } else {
    //            if (strtotime($next_date) < $today_dt) {
    //                $error = true;
    //                $error_msg = $lang['InvalidNextTestDate'];
    //            }
            }
            
            if ($last_date && $next_date && strtotime($next_date) < strtotime($last_date)) {

                $error = true;
                $error_msg = $lang['InvalidNextTestDateLastTestDateOrder'];

            }

            if ($error !== true) {

                // Update a framework control test
                update_framework_control_test($test_id, $tester, $test_frequency, $name, $objective, $test_steps, $approximate_time, $expected_results, $last_date, $next_date, false, $additional_stakeholders, $teams, $tags);
                
                // display an alert
                set_alert(true, "good", $lang['TestSuccessUpdated']);

            } else {

                // display an alert
                set_alert(true, "bad", $error_msg);

            }
        }
    }

    // Check if deleting test
    if (isset($_POST['delete_test'])) {

        $error = false;
        $error_msg = "";

        // check permission
        if (!isset($_SESSION["delete_tests"]) || $_SESSION["delete_tests"] != 1) {

            // display an alert
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

            if ($error !== true) {

                // Delete a framework control
                delete_framework_control_test($test_id);

                // display an alert
                set_alert(true, "good", $lang['SuccessTestDeleted']);

            } else {

                // display an alert
                set_alert(true, "bad", $error_msg);

            }
        }
    }

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="define-tests-filter-part card-body my-2 border">
            <div class="row">
                <div class="col-md-4">
                    <h4><?= $escaper->escapeHtml($lang['ControlFramework']);?> :</h4>
                    <select id="filter_by_control_framework" class="form-select" multiple="multiple">
    <?php 

        $filter_by_control = array();

        if (isset($_POST['filter_by_control'])) {
            $filter_by_control = explode(",", $_POST['filter_by_control']);
        }

        if (in_array("-1", $filter_by_control) || count($filter_by_control)== 0) {
            echo "
                        <option selected value='-1'>{$escaper->escapeHtml($lang['Unassigned'])}</option>
            ";
        } else {
            echo "
                        <option value='-1'>{$escaper->escapeHtml($lang['Unassigned'])}</option>
            ";
        }

        $options = getAvailableControlFrameworkList(true);
        is_array($options) || $options = array();
        foreach ($options as $option) {
            if (in_array($option['value'], $filter_by_control) || count($filter_by_control)== 0) {
                echo "
                        <option selected value='" . (int)$option['value'] . "'>{$escaper->escapeHtml($option['name'])}</option>
                ";
            } else  {
                echo "
                        <option value='" . (int)$option['value'] . "'>{$escaper->escapeHtml($option['name'])}</option>
                ";
            }
        }
    ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <h4><?= $escaper->escapeHtml($lang['ControlFamily']); ?> :</h4>
                    <select id="filter_by_control_family" class="form-select" multiple="multiple">
    <?php 

        $filter_by_control_family = array();
        
        if (isset($_POST['filter_by_control_family'])) {
            $filter_by_control_family = explode(",", $_POST['filter_by_control_family']);
        }

        if (in_array("-1", $filter_by_control_family) || count($filter_by_control_family)== 0) {
            echo "
                        <option selected value='-1'>{$escaper->escapeHtml($lang['Unassigned'])}</option>
            ";
        } else  {
            echo "
                        <option value='-1'>{$escaper->escapeHtml($lang['Unassigned'])}</option>
            ";
        }

        $options = getAvailableControlFamilyList();  
        is_array($options) || $options = array();
        foreach ($options as $option) {
            if (in_array($option['value'], $filter_by_control_family) || count($filter_by_control)== 0) {
                echo "
                        <option selected value='" . (int)$option['value'] . "'>{$escaper->escapeHtml($option['name'])}</option>
                ";
            } else {
                echo "
                        <option value='" . (int)$option['value'] . "'>{$escaper->escapeHtml($option['name'])}</option>
                ";
            }
        } 
    ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <h4><?= $escaper->escapeHtml($lang['ControlName']); ?> :</h4>
    <?php 
        $filter_by_control_text = isset($_POST['filter_by_control_text']) ? $_POST['filter_by_control_text'] : ""; 
    ?>
                    <input type="text" class="form-control" id="filter_by_control_text" value="<?= $escaper->escapeHtml($filter_by_control_text);?>">
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card-body mb-2 border">
    <?php 
            display_framework_controls_in_compliance(); 
    ?>
        </div>
    </div>
</div>

<!-- MODEL WINDOW FOR ADDING TEST -->
<div id="test--add" class="modal fade" tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form class="" id="test-new-form" method="post" autocomplete="off">
                <input type="hidden" name="framework_control_id" value="">
                <input type="hidden" name="filter_by_control" value="">
                <input type="hidden" name="filter_by_control_family" value="">
                <input type="hidden" name="filter_by_control_text" value="">
                <input type="hidden" name="add_test" value="true">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $escaper->escapeHtml($lang['TestAddHeader']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestName']); ?><span class="required">*</span> :</label>
                            <input type="text" name="name" required value="" class="form-control" maxlength="1000" title="<?= $escaper->escapeHtml($lang['TestName']); ?>">
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Tester']); ?> :</label>
    <?php 
                            create_dropdown("enabled_users", NULL, "tester", false, false, false); 
    ?>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-6">
                            <label for="" style="width:100%"><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?> :</label>
    <?php 
                            create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders_add"); 
    ?>
                        </div>
                        <div class="col-6">
                            <label for="" style="width:100%"> <?= $escaper->escapeHtml($lang['Teams']); ?> :</label>
    <?php 
                            create_multiple_dropdown("team"); 
    ?>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestFrequency']); ?><small class="text-dark ms-1">(<?= $escaper->escapeHtml($lang['days']); ?>)</small> :</label>
                            <input type="number" min="0" max="2147483647" name="test_frequency" value="" class="form-control">
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['LastTestDate']); ?> :</label>
                            <input type="text" name="last_date" value="" class="form-control datepicker">
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Objective']); ?> :</label>
                            <textarea name="objective" id="add_objective" class="form-control" rows="3" style="max-width:100%;height:auto;"></textarea>
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestSteps']); ?> :</label>
                            <textarea name="test_steps" id="add_test_steps" class="form-control" rows="3" style="max-width:100%;height:auto;"></textarea>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ApproximateTime']); ?><small class="text-dark ms-1">(<?= $escaper->escapeHtml($lang['minutes']); ?>)</small> :</label>
                            <input type="number" min="0" max="2147483647" name="approximate_time" value="" class="form-control"> 
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ExpectedResults']); ?> :</label>
                            <textarea name="expected_results" id="add_expected_results" class="form-control" rows="4" style="max-width:100%;height:auto;"></textarea>
                        </div>
                    </div>
                    <div class="row form-group mb-0">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['Tags']); ?> :</label>
                            <select class="test_tags" readonly name="tags[]" multiple placeholder="<?= $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
                            <div class="text-danger"><?= $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" id="add_test" class="btn btn-danger"><?= $escaper->escapeHtml($lang['Add']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
    
<!-- MODEL WINDOW FOR EDITING TEST -->
<div id="test--edit" class="modal fade" tabindex="-1" aria-labelledby="risk-catalog--add" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form class="" id="test-edit-form" method="post" autocomplete="off">
                <input type="hidden" name="test_id" value="">
                <input type="hidden" name="filter_by_control" value="">
                <input type="hidden" name="filter_by_control_family" value="">
                <input type="hidden" name="filter_by_control_text" value="">
                <input type="hidden" name="update_test" value="true">    
                <div class="modal-header">
                    <h5 class="modal-title"><?= $escaper->escapeHtml($lang['TestEditHeader']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestName']); ?><span class="required">*</span> :</label>
                            <input type="text" name="name" required value="" class="form-control" maxlength="1000" title="<?= $escaper->escapeHtml($lang['TestName']); ?>">
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Tester']); ?> :</label>
    <?php 
                            create_dropdown("enabled_users", NULL, "tester", false, false, false); 
    ?>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['AdditionalStakeholders']); ?> :</label>
    <?php 
                            create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders_edit"); 
    ?>
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Teams']); ?> :</label>
    <?php 
                            create_multiple_dropdown("team"); 
    ?>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestFrequency']); ?><small class="white-labels ms-1">(<?= $escaper->escapeHtml($lang['days']); ?>)</small> :</label>
                            <input type="number" min="0" max="2147483647" name="test_frequency" value="" class="form-control"> 
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['LastTestDate']); ?> :</label>
                            <input type="text" name="last_date" value="" class="form-control datepicker"> 
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['NextTestDate']); ?> :</label>
                            <input type="text" name="next_date" value="" class="form-control datepicker"> 
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['Objective']); ?> :</label>
                            <textarea name="objective" id="edit_objective" class="form-control" rows="3" style="max-width:100%;height: auto;"></textarea>
                        </div>
                        <div class="col-6">
                            <label for=""><?= $escaper->escapeHtml($lang['TestSteps']); ?> :</label>
                            <textarea name="test_steps" id="edit_test_steps" class="form-control" rows="3" style="max-width:100%;height:auto;"></textarea>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ApproximateTime']); ?><small class="text-dark ms-1">(<?= $escaper->escapeHtml($lang['minutes']); ?>)</small> :</label>
                            <input type="number" min="0" max="2147483647" name="approximate_time" value="" class="form-control"> 
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['ExpectedResults']); ?> :</label>
                            <textarea name="expected_results" id="edit_expected_results" class="form-control" rows="3" style="max-width:100%;height: auto;"></textarea>
                        </div>
                    </div>
                    <div class="row form-group mb-0">
                        <div class="col-12">
                            <label for=""><?= $escaper->escapeHtml($lang['Tags']); ?> :</label>
                            <select class="test_tags" readonly name="tags[]" multiple placeholder="<?= $escaper->escapeHtml($lang['TagsWidgetPlaceholder']);?>"></select>
                            <div class="text-danger" ><?= $escaper->escapeHtml($lang['MaxTagLengthWarning']);?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" id="update_test" class="btn btn-danger"><?= $escaper->escapeHtml($lang['Update']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
    
<!-- MODEL WINDOW FOR PROJECT DELETE CONFIRM -->
<div id="test--delete" class="modal fade" tabindex="-1" aria-labelledby="test--delete" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form class="" action="" method="post">
                <input type="hidden" name="test_id" value="" />
                <input type="hidden" name="filter_by_control" value="">
                <input type="hidden" name="filter_by_control_family" value="">
                <input type="hidden" name="filter_by_control_text" value="">
                <div class="modal-body">
                    <div class="form-group text-center">
                        <h4 class="modal-title"><?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisTest']); ?></h4>
                    </div>
                    <div class="text-center project-delete-actions">
                        <button type="button" class="btn btn-dark" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="delete_test" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Yes']); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {

        $("#additional_stakeholders_add").multiselect({
            buttonWidth: '100%'
        });

        $("#additional_stakeholders_edit").multiselect({
            buttonWidth: '100%'
        });

        $("[name='team[]']").multiselect({
            buttonWidth: '100%'
        });

        //Have to remove the 'fade' class for the shown event to work for modals
        $('#test--add, #test--edit').on('shown.bs.modal', function() {
            $(this).find('.modal-body').scrollTop(0);
        });

        $("form").submit(function(e) {
            $("input[name=filter_by_control]").val($("#filter_by_control_framework").val());
            $("input[name=filter_by_control_family]").val($("#filter_by_control_family").val());
            $("input[name=filter_by_control_text]").val($("#filter_by_control_text").val());
            return true;
        });

        $('#filter_by_control_framework').multiselect({
            allSelectedText: '<?= $escaper->escapeHtml($lang['ALL'])?>',
            enableFiltering: true,
            maxHeight: 250,
            buttonWidth: '100%',
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
        });

        $('#filter_by_control_family').multiselect({
            allSelectedText: '<?= $escaper->escapeHtml($lang['ALL'])?>',
            enableFiltering: true,
            maxHeight: 250,
            buttonWidth: '100%',
            includeSelectAllOption: true,
            enableCaseInsensitiveFiltering: true,
        });

        // init tinyMCE WYSIWYG editor
        init_minimun_editor('#add_objective');
        init_minimun_editor('#add_test_steps');
        init_minimun_editor('#add_expected_results');
        init_minimun_editor('#edit_objective');
        init_minimun_editor('#edit_test_steps');
        init_minimun_editor('#edit_expected_results');

        $("#add_test").on("click", function() {

            // Check if the required fields have empty / trimmed empty values
            if (!checkAndSetValidation("#test-new-form")) {
                return false;
            }

        });

        $("#update_test").on("click", function() {

            // Check if the required fields have empty / trimmed empty values
            if (!checkAndSetValidation("#test-edit-form")) {
                return false;
            }

        });
        
    });

    if ( window.history.replaceState ) {

        window.history.replaceState( null, null, window.location.href );
        
    }

</script>
<script>
    <?php prevent_form_double_submit_script(['test-new-form', 'test-edit-form']); ?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>