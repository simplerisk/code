<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/cvss.php'));
require_once(realpath(__DIR__ . '/services.php'));
require_once(realpath(__DIR__ . '/alerts.php'));

// Include the language file
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

/******************************************************
 * FUNCTION: DISPLAY FRAMEWORK CONTROLS IN COMPLIANCE *
 ******************************************************/
function display_framework_controls_in_compliance()
{
    global $lang, $escaper;

    $tableID = "framework-controls";

    echo "
        <table width=\"100%\" id=\"{$tableID}\" >
            <thead class='hide'>
                <tr >
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            var datatableInstance = $('#{$tableID}').DataTable({
                scrollX: true,
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                pagingType: \"full_numbers\",
                dom : \"flrtip\",
                pageLength: pageLength,
                dom : \"flrti<'#view-all.view-all'>p\",
                ajax: {
                    url: BASE_URL + '/api/compliance/define_tests',
                    data: function(d){
                        d.control_framework = $(\"#filter_by_control_framework\").val();
                        d.control_family = $(\"#filter_by_control_family\").val();
                        d.control_name = $(\"#filter_by_control_text\").val();
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
            })
            
            // View All
            $(\"#filter_by_control_framework, #filter_by_control_family, #filter_by_control_text\").change(function(){
                $(\"#{$tableID}\").DataTable().draw();
            })

            $('#filter_by_control_framework').multiselect({
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                enableFiltering: true,
                maxHeight: 250,
                buttonWidth: '100%',
                includeSelectAllOption: true,
                enableCaseInsensitiveFiltering: true,
            });
            $('#filter_by_control_family').multiselect({
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                enableFiltering: true,
                maxHeight: 250,
                buttonWidth: '100%',
                includeSelectAllOption: true,
                enableCaseInsensitiveFiltering: true,
            });
           
            $(\"body\").on(\"click\", \".add-test\", function(){
                $(\"#test-new-form\")[0].reset();
                $(\"[name=framework_control_id]\", $(\"#test-new-form\")).val($(this).data('control-id'));
                \$(\"#test-new-form .datepicker\").datepicker({maxDate: new Date});
            })
            
            $(\"body\").on(\"click\", \".delete-row\", function(){
                var testId = $(this).data('id');
                $(\"#test--delete [name=test_id]\").val(testId);
                $('#test--delete').modal();
            })
            
            $(\"body\").on(\"click\", \".edit-test\", function(){
                var testId = $(this).data('id');
                \$.ajax({
                    type: \"GET\",
                    url: BASE_URL + \"/api/compliance/test?id=\" + testId,
                    success: function(result){
                        var data = result['data'];
                        var form = \$('#test-edit-form');
                        $('[name=test_id]', form).val(data['id']);
                        $('[name=tester]', form).val(data['tester']);

                        $('#additional_stakeholders_edit', form).multiselect('deselectAll', false);
                        $('#additional_stakeholders_edit', form).multiselect('select', data['additional_stakeholders']);

                        $('[name=\'team[]\']', form).multiselect('deselectAll', false);
                        $('[name=\'team[]\']', form).multiselect('select', data['teams']);

                        $('[name=test_frequency]', form).val(data['test_frequency']);
                        $('[name=last_date]', form).val(data['last_date']);
                        $('[name=next_date]', form).val(data['next_date']);
                        $('[name=name]', form).val(data['name']);
                        $('[name=objective]', form).val(data['objective']);
                        $('[name=test_steps]', form).val(data['test_steps']);
                        $('[name=approximate_time]', form).val(data['approximate_time']);
                        $('[name=expected_results]', form).val(data['expected_results']);
                        $('[name=last_date]', form).datepicker({maxDate: new Date});
                        if(data['last_date'] != '') {
                            min_next_date = new Date(data['last_date']);
                        } else min_next_date = null;
                        $('[name=next_date]', form).datepicker({minDate: min_next_date});
                        $.each(data['tags'], function (i, item) {
                            $('[name=\'tags[]\']', form).append($('<option>', { 
                                value: item,
                                text : item,
                                selected : true,
                            }));
                        });
                        var select = $('[name=\'tags[]\']', form).selectize();
                        var selectize = select[0].selectize;
                        selectize.setValue(data['tags']);

                        $('#test--edit').modal();
                    },
                    error: function(xhr,status,error){
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                })
            })
        </script>
    ";
}

/*****************************************
 * FUNCTION: ADD FRAMEWORK CONTROLS TEST *
 *****************************************/
function add_framework_control_test($tester, $test_frequency, $name, $objective, $test_steps, $approximate_time, $expected_results, $framework_control_id, $additional_stakeholders = "", $last_date="0000-00-00", $next_date=false, $teams=[], $tags=[]){
    if($next_date === false) {
        if (!$last_date || $last_date === "0000-00-00") {
            $next_date = date("Y-m-d");
        } else {
            $calc_next_date = date("Y-m-d", strtotime($last_date) + $test_frequency*24*60*60);
            if($calc_next_date < date("Y-m-d")){
                $next_date = date("Y-m-d");
            } else {
                $next_date = $calc_next_date;
            }
        }
    }

    // Open the database connection
    $db = db_open();

    $created_at = date("Y-m-d");

    // Create test
    $stmt = $db->prepare("INSERT INTO `framework_control_tests` (`tester`, `test_frequency`, `last_date`, `next_date`, `name`, `objective`, `test_steps`, `approximate_time`, `expected_results`, `framework_control_id`, `created_at`, `additional_stakeholders`) VALUES (:tester, :test_frequency, :last_date, :next_date, :name, :objective, :test_steps, :approximate_time, :expected_results, :framework_control_id, :created_at, :additional_stakeholders)");
    
    $stmt->bindParam(":tester", $tester, PDO::PARAM_INT);
    $stmt->bindParam(":test_frequency", $test_frequency, PDO::PARAM_INT);
    $stmt->bindParam(":last_date", $last_date, PDO::PARAM_STR);
    $stmt->bindParam(":next_date", $next_date, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 100);
    $stmt->bindParam(":objective", $objective, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":test_steps", $test_steps, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":approximate_time", $approximate_time, PDO::PARAM_INT);
    $stmt->bindParam(":expected_results", $expected_results, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":framework_control_id", $framework_control_id, PDO::PARAM_INT);
    $stmt->bindParam(":created_at", $created_at);
    $stmt->bindParam(":additional_stakeholders", $additional_stakeholders, PDO::PARAM_STR, 500);

    $stmt->execute();

    $test_id = $db->lastInsertId();

    if ($test_id != 0) {
        updateTagsOfType($test_id, 'test', $tags);
    }

    $message = _lang('TestCreatedAuditLogMessage', array('test_name' => $name, 'test_id' => $test_id, 'user' => $_SESSION['user']), false);
    write_log((int)$test_id + 1000, $_SESSION['uid'], $message, "test");

    updateTeamsOfItem($test_id, 'test', $teams);

    // Close the database connection
    db_close($db);

    return $test_id;
}

/********************************************
 * FUNCTION: UPDATE FRAMEWORK CONTROLS TEST *
 ********************************************/
function update_framework_control_test($test_id, $tester=false, $test_frequency=false, $name=false, $objective=false, $test_steps=false, $approximate_time=false, $expected_results=false, $last_date=false, $next_date=false, $framework_control_id=false, $additional_stakeholders=false, $teams=false, $tags=[]){

    // Get test by test ID
    $test = get_framework_control_test_by_id($test_id);

    if($tester === false) $tester = $test['tester'];
    if($test_frequency === false) $test_frequency = $test['test_frequency'];
    if($name === false) $name = $test['name'];
    if($objective === false) $objective = $test['objective'];
    if($test_steps === false) $test_steps = $test['test_steps'];
    if($approximate_time === false) $approximate_time = $test['approximate_time'];
    if($expected_results === false) $expected_results = $test['expected_results'];
    if($last_date === false) $last_date = $test['last_date'];
    if($next_date === false) $next_date = $test['next_date'];
    if($framework_control_id === false) $framework_control_id = $test['framework_control_id'];
    if($additional_stakeholders === false) $additional_stakeholders = $test['additional_stakeholders'];
    if($teams === false) $teams = $test['teams'];

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("UPDATE `framework_control_tests` SET `tester`=:tester, `test_frequency`=:test_frequency, `last_date`=:last_date, `next_date`=:next_date, `name`=:name, `objective`=:objective, `test_steps`=:test_steps, `approximate_time`=:approximate_time, `expected_results`=:expected_results, `framework_control_id`=:framework_control_id, `additional_stakeholders`=:additional_stakeholders WHERE id=:test_id; ");

    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->bindParam(":tester", $tester, PDO::PARAM_INT);
    $stmt->bindParam(":test_frequency", $test_frequency, PDO::PARAM_INT);
    $stmt->bindParam(":last_date", $last_date, PDO::PARAM_STR);
    $stmt->bindParam(":next_date", $next_date, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":objective", $objective, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":test_steps", $test_steps, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":approximate_time", $approximate_time, PDO::PARAM_INT);
    $stmt->bindParam(":expected_results", $expected_results, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":framework_control_id", $framework_control_id, PDO::PARAM_INT);
    $stmt->bindParam(":additional_stakeholders", $additional_stakeholders, PDO::PARAM_STR, 500);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    updateTeamsOfItem($test_id, 'test', $teams, false);
    updateTagsOfType($test_id, 'test', $tags);

    $test_after = get_framework_control_test_by_id($test_id);
    
    $changes = get_changes('test', $test, $test_after);

    $message = _lang('AuditLog_TestUpdated', array('test_name' => $name, 'test_id' => $test_id, 'user_name' => $_SESSION['user'], 'changes' => $changes), false);
    write_log((int)$test_id + 1000, $_SESSION['uid'], $message, "test");


    return $test_id;
}

/******************************************************
 * FUNCTION: DELETE FRAMEWORK CONTROL TEST BY TEST ID *
 ******************************************************/
function delete_framework_control_test($test_id){

    $test = get_framework_control_test_by_id($test_id);

    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("DELETE FROM `framework_control_tests` WHERE id=:id;");
    $stmt->bindParam(":id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Remove teams of test
    updateTeamsOfItem($test_id, 'test', []);
    
    // Close the database connection
    db_close($db);

    $message = _lang('TestDeletedAuditLogMessage', array('test_name' => $test['name'], 'test_id' => $test_id, 'user' => $_SESSION['user']));
    write_log((int)$test_id + 1000, $_SESSION['uid'], $message, "test");
    
    return true;
}

/******************************************************
 * FUNCTION: GET TEST IDS FROM FRAMEWORK CONTROL TEST *
 ******************************************************/
function get_framework_control_test_ids(){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT `id` FROM `framework_control_tests`; ");
    $stmt->execute();

    $array = $stmt->fetchAll();

    // closed the database connection
    db_close($db);
    return $array;
}

/***********************************************************
 * FUNCTION: GET FRAMEWORK CONTROL TEST LIST BY CONTROL ID *
 ***********************************************************/
function get_framework_control_tests_by_control_id($framework_control_id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.*, t2.name tester_name, GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag) as tags
        FROM `framework_control_tests` t1
            LEFT JOIN `user` t2 ON t1.tester = t2.value
            LEFT JOIN tags_taggees tt ON tt.taggee_id = t1.id AND tt.type = 'test'
            LEFT JOIN tags tg on tg.id = tt.tag_id
        WHERE t1.framework_control_id=:framework_control_id
        GROUP By t1.id
        ");
    $stmt->bindParam(":framework_control_id", $framework_control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array;
}

/***************************************************
 * FUNCTION: GET FRAMEWORK CONTROL TEST BY TEST ID *
 ***************************************************/
function get_framework_control_test_by_id($test_id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            `t1`.*,
            `t2`.`name` tester_name,
            GROUP_CONCAT(DISTINCT `itt`.`team_id`) teams,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag) as tags
        FROM
            `framework_control_tests` t1
            LEFT JOIN `user` t2 ON t1.tester = t2.value
            LEFT JOIN `items_to_teams` itt ON `itt`.`item_id` = `t1`.`id` and `itt`.`type` = 'test'
            LEFT JOIN tags_taggees tt ON tt.taggee_id = t1.id AND tt.type = 'test'
            LEFT JOIN tags tg on tg.id = tt.tag_id
        WHERE
            `t1`.`id`=:test_id;
    ");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the test in the array
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    if($test['additional_stakeholders']){
        $test['additional_stakeholders'] = explode(",", $test['additional_stakeholders']);
    }
    
    if($test['teams']){
        $test['teams'] = explode(",", $test['teams']);
    }
    if($test['tags']){
        $test['tags'] = explode(",", $test['tags']);
    }

    return $test;
} 

/*********************************************************
 * FUNCTION: GET FRAMEWORK CONTROL TEST AUDIT BY TEST ID *
 *********************************************************/
function get_framework_control_test_audit_by_id($test_audit_id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT 
            audits.id,
            audits.test_id,
            audits.tester,
            audits.test_frequency,
            tests.last_date,
            tests.next_date,
            audits.name,
            audits.objective,
            audits.test_steps,
            audits.approximate_time,
            audits.expected_results,
            audits.framework_control_id,
            audits.desired_frequency,
            audits.status,
            audits.created_at,
            u.name tester_name,
            ctrl.short_name control_name,
            ctrl.control_owner,
            IFNULL(GROUP_CONCAT(DISTINCT fw.name), '') framework_name,
            results.id result_id,
            results.test_result,
            results.summary,
            results.test_date,
            results.submitted_by,
            results.submission_date,
            tests.additional_stakeholders,
            GROUP_CONCAT(DISTINCT `itt`.`team_id`) teams,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag) as tags
        FROM `framework_control_test_audits` audits
            LEFT JOIN `user` u ON audits.tester = u.value
            LEFT JOIN `framework_controls` ctrl ON audits.framework_control_id = ctrl.id 
            LEFT JOIN `framework_control_mappings` ctrl_m ON ctrl.id = ctrl_m.control_id
            LEFT JOIN `frameworks` fw ON ctrl_m.framework=fw.value
            LEFT JOIN `framework_control_test_results` results ON audits.id=results.test_audit_id
            LEFT JOIN `framework_control_tests` tests ON tests.id=audits.test_id
            LEFT JOIN `items_to_teams` itt ON `itt`.`item_id` = `audits`.`id` and `itt`.`type` = 'audit'
            LEFT JOIN `tags_taggees` tt ON tt.taggee_id = audits.id AND tt.type = 'test_audit'
            LEFT JOIN `tags` tg on tg.id = tt.tag_id

        WHERE audits.id=:test_audit_id and ctrl.deleted = 0
    ");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the test in the array
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    if($test['additional_stakeholders']){
        $test['additional_stakeholders'] = explode(",", $test['additional_stakeholders']);
    }

    if($test['framework_name']){
        $framework_names = explode(",", $test['framework_name']);
        $decrypted_framework_names = [];
        foreach($framework_names as $framework_name)
        {
            if($framework_name){
                $decrypted_framework_names[] = try_decrypt(trim($framework_name));
            }
        }
        $test['framework_name'] = implode(", ", $decrypted_framework_names);
    }

    if($test['teams']){
        $test['teams'] = explode(",", $test['teams']);
    }

    return $test;
}

/********************************************************
 * FUNCTION: GET TEST AUDIT STATUS 						*
 * Returns the `test_status` id of the audit's status	*
 ********************************************************/
function get_test_audit_status($test_audit_id) {

    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("
        SELECT
            `status`
        FROM
            `framework_control_test_audits`
        WHERE
            `id` = :test_audit_id;
    ");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    $status = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return $status;
}
/************************************
 * FUNCTION: GET TEST AUDIT NAME	*
 ************************************/
function get_test_audit_name($test_audit_id) {
    
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("
        SELECT
            `name`
        FROM
            `framework_control_test_audits`
        WHERE
            `id` = :test_audit_id;
    ");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $name = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);
    
    return $name;
}

/*************************************
 * FUNCTION: DISPLAY INITIATE AUDITS *
 *************************************/
function display_initiate_audits()
{
    global $lang, $escaper;
    
    echo "
        <div id='filter-container'>
            <div class='row-fluid'>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['FilterByText']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span2'>
                <input type='text' id='filter_by_text' class='form-control'>
                </div>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['TestFrequency']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span2'>
                    <input type='text' id='filter_by_frequency' class='form-control'>
                </div>
                <div class='span2 hide' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Status']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span2 hide'>
                    <div class='multiselect-content-container'>";
                    create_multiple_dropdown("test_status", "all", "filter_by_status", NULL, false);
                echo "</div>
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Framework']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span2'>
                    <div class='multiselect-content-container'>
                        <select id='filter_by_framework' class='' multiple=''>\n";
                            $options = getAvailableControlFrameworkList(true);
                            is_array($options) || $options = array();
                            foreach($options as $option){
                                echo "<option selected value=\"".$escaper->escapeHtml($option['value'])."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                            }
                        echo "</select>
                    </div>
                </div>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Control']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span2'>
                    <input type='text' id='filter_by_control' class='form-control'>
                </div>
                
            </div>
        </div>
    ";
    
    echo "
        <script>
            // Redraw Past Audit table
            function redraw(){
                $('#initiate_audit_treegrid').treegrid('reload');
            } 
            
            // timer identifier
            var typingTimer;                
            // time in ms (1 second)
            var doneTypingInterval = 1000;  

            $('#filter_by_framework').multiselect({
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                enableFiltering: true,
                maxHeight: 250,
                includeSelectAllOption: true,
                buttonWidth: '100%',
                enableCaseInsensitiveFiltering: true,
                onDropdownHide: function(){
                    redraw();
                }
            });
            
            $('#filter_by_status').multiselect({
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                includeSelectAllOption: true,
                enableCaseInsensitiveFiltering: true,
                onDropdownHide: function(){
                    redraw();
                }
            });
            
            // Search filter event
            $('#filter_by_text').keyup(function(){
                clearTimeout(typingTimer);
                typingTimer = setTimeout(redraw, doneTypingInterval);
            });

            // Search filter event
            $('#filter_by_control').keyup(function(){
                clearTimeout(typingTimer);
                typingTimer = setTimeout(redraw, doneTypingInterval);
            });

            // Search filter event
            $('#filter_by_frequency').keyup(function(){
                clearTimeout(typingTimer);
                typingTimer = setTimeout(redraw, doneTypingInterval);
            });

        </script>
    ";
    echo "<table id=\"initiate_audit_treegrid\" class='easyui-treegrid' 
            data-options=\"
                iconCls: 'icon-ok',
                animate: true,
                collapsible: false,
                fitColumns: true,
                url: '".$_SESSION['base_url']."/api/compliance/initiate_audits',
                onBeforeLoad: function(row, param){
                    param.filter_by_text = $('#filter_by_text').val();
                    param.filter_by_status = $('#filter_by_status').val();
                    param.filter_by_frequency = $('#filter_by_frequency').val();
                    param.filter_by_framework = $('#filter_by_framework').val();
                    param.filter_by_control = $('#filter_by_control').val();
                },
                method: 'get',
                idField: 'id',
                treeField: 'name',
                scrollbarSize: 0
            \">";
    echo "<thead>";
    echo "<th data-options=\"field:'name'\" style='width: 220px'>".$escaper->escapeHtml($lang['Name'])."</th>";
    echo "<th data-options=\"field:'test_frequency'\" >".$escaper->escapeHtml($lang['TestFrequency'])."</th>";
    echo "<th data-options=\"field:'last_audit_date'\" >".$escaper->escapeHtml($lang['LastAuditDate'])."</th>";
    echo "<th data-options=\"field:'next_audit_date'\" >".$escaper->escapeHtml($lang['NextAuditDate'])."</th>";
    echo "<th data-options=\"field:'status'\" >".$escaper->escapeHtml($lang['Status'])."</th>";
    echo "<th data-options=\"field:'action'\" >&nbsp;</th>";
    echo "</thead>\n";
    echo "</table>";
    
}

/***********************************
 * FUNCTION: DISPLAY ACTIVE AUDITS *
 ***********************************/
function display_active_audits(){
    global $lang, $escaper;
    
    $tableID = "active-audits";
    $column_settings = isset($_SESSION['custom_audits_columns'])?$_SESSION['custom_audits_columns']:array();
    if(count($column_settings) == 0) $column_settings = array("test_name","test_frequency","tester","additional_stakeholders","objective","control_name","framework_name","tags","status","last_date","next_date");
    $tags = [];
    foreach(getTagsOfType("test") as $tag) {
        $tags[] = array('name' => $escaper->escapeHtml($tag['tag']), 'value' => (int)$tag['id']);
    }
    echo "
        <div id='filter-container' class='well'>
            <div class='row-fluid'>
                <div class='span1' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Framework']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span3'>
                    <div class='multiselect-content-container'>
                        <select id='filter_by_framework' multiple=''>
                            <option selected value='-1'>".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                            $options = getHasBeenAuditFrameworkList();
                            is_array($options) || $options = array();
                            foreach($options as $option){
                                echo "<option selected value=\"".$escaper->escapeHtml($option['value'])."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                            }
                        echo "</select>
                    </div>
                </div>
                <div class='span1' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Status']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span3'>
                    <div class='multiselect-content-container'>";
                    create_multiple_dropdown("test_status", "all", "filter_by_status", NULL, true, $escaper->escapeHtml($lang['Unassigned']), "0");
                echo "</div>
                </div>
                <div class='span1' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Tags']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span3'>
                    <div class='multiselect-content-container'>";
                    create_multiple_dropdown("tags", "all", "filter_by_tags", $tags, true, $escaper->escapeHtml($lang['Unassigned']), "-1");
                echo "</div>
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span1' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Tester']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span3'>
                    <div class='multiselect-content-container'>";
                        create_multiple_dropdown("enabled_users", "all", "filter_by_tester");
                echo "</div>
                </div>
                <div class='span1' align='right' style='font-size: 15px'>
                    <strong>".$escaper->escapeHtml($lang['TestName']).":</strong>
                </div>
                <div class='span3'>
                    <div class='multiselect-content-container'>";
                        $selected = isset($_GET['test_id']) ? array($_GET['test_id']) : 'all';
                        create_multiple_dropdown("framework_control_tests", $selected, "filter_by_testname");
                echo "</div>
                </div>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['FilterByText']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span2'>
                    <input type='text' id='filter_by_text' class='form-control'>
                </div>
            </div>
            <div class='well' id='column-selections-container'>
                <h4 class='collapsible--toggle clearfix'>
                    <span><i class='fa fa-caret-right'></i>".$escaper->escapeHtml($lang['ColumnSelections'])."</span>
                </h4>
                <div class='collapsible' style='display: none;'>
                    <div class='row-fluid'>
                        <div class='span4'>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"test_name\" id=\"checkbox_test_name\" ".(in_array("test_name",$column_settings)?"checked":"")."/>
                            <label for=\"checkbox_test_name\"> ". $escaper->escapeHtml($lang['TestName']) ."</label>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"test_frequency\" id=\"checkbox_test_frequency\" ".(in_array("test_frequency",$column_settings)?"checked":"")."/>
                            <label for=\"checkbox_test_frequency\"> ". $escaper->escapeHtml($lang['TestFrequency']) ."</label>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"tester\" id=\"checkbox_tester\" ".(in_array("tester",$column_settings)?"checked":"")."/> 
                            <label for=\"checkbox_tester\"> ". $escaper->escapeHtml($lang['Tester']) ."</label>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"additional_stakeholders\" id=\"checkbox_additional_stakeholders\" ".(in_array("additional_stakeholders",$column_settings)?"checked":"")."/>
                            <label for=\"checkbox_additional_stakeholders\"> ". $escaper->escapeHtml($lang['AdditionalStakeholders']) ."</label>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"objective\" id=\"checkbox_objective\" ".(in_array("objective",$column_settings)?"checked":"")."/>
                            <label for=\"checkbox_objective\"> ". $escaper->escapeHtml($lang['Objective']) ."</label>
                        </div>
                        <div class='span4'>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"control_name\" id=\"checkbox_control_name\" ".(in_array("control_name",$column_settings)?"checked":"")."/>
                            <label for=\"checkbox_control_name\"> ". $escaper->escapeHtml($lang['ControlName']) ."</label>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"framework_name\" id=\"checkbox_framework_name\" ".(in_array("framework_name",$column_settings)?"checked":"")."/>
                            <label for=\"checkbox_framework_name\"> ". $escaper->escapeHtml($lang['FrameworkName']) ."</label>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"tags\" id=\"checkbox_tags\" ".(in_array("tags", $column_settings)?"checked":"")."/>
                            <label for=\"checkbox_tags\"> ". $escaper->escapeHtml($lang['Tags']) ."</label>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"status\" id=\"checkbox_status\" ".(in_array("status",$column_settings)?"checked":"")."/> 
                            <label for=\"checkbox_status\"> ". $escaper->escapeHtml($lang['Status']) ."</label>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"last_date\" id=\"checkbox_last_date\" ".(in_array("last_date",$column_settings)?"checked":"")."/>
                            <label for=\"checkbox_last_date\"> ". $escaper->escapeHtml($lang['LastAuditDate']) ."</label>
                        </div>
                        <div class='span4'>
                            <input class=\"hidden-checkbox\" type=\"checkbox\" name=\"next_date\" id=\"checkbox_next_date\" ".(in_array("next_date",$column_settings)?"checked":"")."/>
                            <label for=\"checkbox_next_date\"> ". $escaper->escapeHtml($lang['NextAuditDate']) ."</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <table id=\"{$tableID}\" width=\"100%\" class=\"risk-datatable table table-bordered table-striped table-condensed\">
            <thead >
                <tr >
                    <th data-name='test_name' valign=\"top\">".$escaper->escapeHtml($lang['TestName'])."</th>
                    <th data-name='test_frequency' valign=\"top\">".$escaper->escapeHtml($lang['TestFrequency'])."</th>
                    <th data-name='tester' valign=\"top\">".$escaper->escapeHtml($lang['Tester'])."</th>
                    <th data-name='additional_stakeholders' valign=\"top\">".$escaper->escapeHtml($lang['AdditionalStakeholders'])."</th>
                    <th data-name='objective' valign=\"top\">".$escaper->escapeHtml($lang['Objective'])."</th>
                    <th data-name='control_name' valign=\"top\">".$escaper->escapeHtml($lang['ControlName'])."</th>
                    <th data-name='framework_name' valign=\"top\">".$escaper->escapeHtml($lang['FrameworkName'])."</th>
                    <th data-name='tags' valign=\"top\">".$escaper->escapeHtml($lang['Tags'])."</th>
                    <th data-name='status' valign=\"top\">".$escaper->escapeHtml($lang['Status'])."</th>
                    <th data-name='test_date' valign=\"top\">".$escaper->escapeHtml($lang['TestDate'])."</th>
                    <th data-name='last_date' valign=\"top\">".$escaper->escapeHtml($lang['LastAuditDate'])."</th>
                    <th data-name='next_date' valign=\"top\">".$escaper->escapeHtml($lang['NextAuditDate'])."</th>
                    <th data-name='actions' valign=\"top\"></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
    <script>
        $(document).ready(function(){
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            var columnOptions = [];
            $('#filter-container input.hidden-checkbox').each(function(index){
                if(!$(this).is(':checked')) columnOptions.push(index);
            });
            $('#{$tableID} thead tr').clone(true).appendTo( '#{$tableID} thead');
            $('#{$tableID} thead tr:eq(1) th').each( function (i) {
                var title = $(this).text();
                var data_name = $(this).attr('data-name');
                if(data_name != 'actions') {
                    if(data_name == 'test_frequency') {
                        $(this).html(''); // To clear the title out of the header cell
                        $('<input type=\"number\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    } else {
                        $(this).html(''); // To clear the title out of the header cell
                        $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    }
                }
         
                $( 'input, select', this ).on( 'keyup change', function () {
                    if ( datatableInstance.column(i).search() !== this.value ) {
                        datatableInstance.column(i).search( this.value ).draw();
                    }
                });
            });
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: true,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                orderCellsTop: true,
                columnDefs : [
                    {
                        'targets' : columnOptions,
                        'visible' : false
                    },
                    {
                        'targets' : [-1],
                        'orderable': false,
                        'className' : 'vcenter'
                    }
                ],
                pagingType: \"full_numbers\",
                pageLength: pageLength,
                dom : \"lrti<'#view-all.view-all'>p\",
                createdRow: function(row, data, index){
                    var background = $('.background-class', $(row)).data('background');
                    $(row).find('td').addClass(background)
                },
                ajax: {
                    url: BASE_URL + '/api/compliance/active_audits',
                    type: 'POST',
                    data: function(d){
                        d.filter_text = \$(\"#filter_by_text\").val();
                        d.filter_framework  = \$(\"#filter_by_framework\").val();
                        d.filter_status  = \$(\"#filter_by_status\").val();
                        d.filter_tester  = \$(\"#filter_by_tester\").val();
                        d.filter_testname  = \$(\"#filter_by_testname\").val();
                        d.filter_tags  = \$(\"#filter_by_tags\").val();
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
            })
            
            $('body').on('click', '.delete-btn', function(){
                var id = $(this).data('id')

                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/compliance/delete_audit',
                    data : {
                        id: id
                    },
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }
                        datatableInstance.ajax.reload(null, false);
                    },
                    error: function(xhr,status,error){
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                        if(!retryCSRF(xhr, this))
                        {
                        }
                    }
                });
            })

            // Redraw Past Audit table
            function redrawActiveAudits(){
                $(\"#{$tableID}\").DataTable().draw();
            } 
            
            // timer identifier
            var typingTimer;                
            // time in ms (1 second)
            var doneTypingInterval = 1000;  
            $('.multiselect-content-container select').multiselect({
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                includeSelectAllOption: true,
                buttonWidth: '100%',
                enableCaseInsensitiveFiltering: true,
            });

            // Search filter event
            $('#filter_by_text').keyup(function(){
                clearTimeout(typingTimer);
                typingTimer = setTimeout(redrawActiveAudits, doneTypingInterval);
            });

            $('.multiselect-content-container select').change(function(){
                redrawActiveAudits();
            });

            $('#column-selections-container').on('click', '.collapsible--toggle span', function(event) {
                event.preventDefault();
                $(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
                $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
            });
            $(\"#filter-container .hidden-checkbox\").click(function(e){
                
                var column = datatableInstance.column(\"th[data-name='\"+ $(this).attr('name') +\"']\");
                if($(this).is(':checked')){
                    column.visible(true);
                    // The TH element to show filter html
                    var targetTH = $(\"tr.filter th[data-name='\"+ $(this).attr('name') +\"']\", datatableInstance.table().header());

                    // If this element was hidden on loading, add filter content to the TH element and create multi dropdown
                    if($(\".hidden-container\", column.header()).length > 0)
                    {
                        targetTH.html($(\".hidden-container\", column.header()).html());
                        //createMultiSelectColumnFilter(datatableInstance, targetTH);
                        $(\".hidden-container\", column.header()).remove();
                    }
                }else{
                    column.visible(false);
                }
                
                var checkBoxes = $(\"#filter-container .hidden-checkbox\");
                var viewColumns = [];
                checkBoxes.each(function(){
                    if($(this).is(':checked'))
                        viewColumns.push($(this).attr('name'));
                })
                $.ajax({
                    type: \"POST\",
                    url: BASE_URL + \"/api/set_custom_audits_column\",
                    data: {
                        columns: viewColumns,
                    },
                    success: function(data){
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                        }
                    }
                });
            })
        });
    </script>
    ";
}

/************************************
 * INITIATE FRAMEWORK CONTROL TESTS *
 ************************************/
function initiate_framework_control_tests($type, $id, $tags=[]){
    $initiated_audit_status = get_setting("initiated_audit_status") ? get_setting("initiated_audit_status") : 0;

     // If team separation is enabled
    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // It means that either the user is an admin
        // or everyone has access to the tests/audits.
        // It means we can treat Team Separation like it is disabled
        if (should_skip_test_and_audit_permission_check()) {
            $separation_enabled = false;
        } else {
            $separation_enabled = true;
            $compliance_separation_access_info = get_compliance_separation_access_info();
        }
    } else
        $separation_enabled = false;

    // Open the database connection
    $db = db_open();

    switch($type){
        case "framework":
            if ($separation_enabled && !in_array($id, $compliance_separation_access_info['frameworks']))
                return false;
            
            $framework = get_framework($id);
            $name = $framework['name'];
            
            $child_frameworks = get_all_child_frameworks($id, 1);
            $framework_ids = array_merge(array($id), array_map(function($row){
                return $row['value'];
            }, $child_frameworks));

            $sql = "
                SELECT
                    DISTINCT t1.id
                FROM `framework_control_tests` t1
                    INNER JOIN `framework_controls` t2 ON t1.framework_control_id=t2.id AND t2.deleted=0
                    INNER JOIN `framework_control_mappings` m ON t2.id=m.control_id
                WHERE FIND_IN_SET(m.framework, :framework_ids); ";

            $stmt = $db->prepare($sql);
            $framework_id_string = implode(",", $framework_ids);
            $stmt->bindParam(":framework_ids", $framework_id_string, PDO::PARAM_STR);
            $stmt->execute();

            $test_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            
            foreach($test_ids as $test_id){
                if ($separation_enabled && !in_array($test_id, $compliance_separation_access_info['framework_control_tests']))
                    continue;

                initiate_test_audit($test_id, $initiated_audit_status, $tags);
            }
        break;
        case "control":
            if ($separation_enabled && !in_array($id, $compliance_separation_access_info['framework_controls']))
                return false;

            $control = get_framework_control($id);
            $name = $control['short_name'];

            $stmt = $db->prepare("
                SELECT 
                    t1.id
                FROM framework_control_tests t1
                    INNER JOIN framework_controls t2 ON t1.framework_control_id=t2.id AND t2.deleted=0
                WHERE
                    t2.id=:control_id;
            ");
            $stmt->bindParam(":control_id", $id, PDO::PARAM_INT);
            $stmt->execute();

            $test_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            foreach($test_ids as $test_id){
                if ($separation_enabled && !in_array($test_id, $compliance_separation_access_info['framework_control_tests']))
                    continue;
                
                initiate_test_audit($test_id, $initiated_audit_status, $tags);
            }
        break;
        case "test":
            if ($separation_enabled && !in_array($id, $compliance_separation_access_info['framework_control_tests']))
                return false;

            $name = initiate_test_audit($id, $initiated_audit_status, $tags);
        break;
    }

    // Close the database connection
    db_close($db);
    
    return $name;
}

function initiate_test_audit($test_id, $initiated_audit_status, $tags=[]) {

    $test = get_framework_control_test_by_id($test_id);
    $name = $test['name'];

    // Open the database connection
    $db = db_open();

    $sql = "
        INSERT INTO
            `framework_control_test_audits`(test_id, tester, test_frequency, last_date, next_date, name, objective, test_steps, approximate_time, expected_results, framework_control_id, desired_frequency, status, created_at)
        SELECT
            t1.id as test_id, t1.tester, t1.test_frequency, t1.last_date, t1.next_date, t1.name, t1.objective, t1.test_steps, t1.approximate_time, t1.expected_results, t1.framework_control_id, t1.desired_frequency, {$initiated_audit_status} as status, '".date("Y-m-d H:i:s")."' as created_at
        FROM framework_control_tests t1
        WHERE
            t1.id=:test_id;
    ";

    // Create temp table from framework_control_test
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);

    $stmt->execute();

    $audit_id = $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO framework_control_test_results (`test_audit_id`) VALUES(:test_audit_id);");
    $stmt->bindParam(":test_audit_id", $audit_id, PDO::PARAM_INT);
    $stmt->execute();
    $result_id = $db->lastInsertId();

    $stmt = $db->prepare("
        SELECT t1.* FROM `framework_control_test_results` t1
        INNER JOIN `framework_control_test_audits` t2 ON t1.test_audit_id = t2.id
        WHERE t2.test_id = :test_id AND t1.id != :result_id AND t1.test_result != 'Pass' 
        ORDER By id DESC LIMIT 0,1");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->bindParam(":result_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();
    $test_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $risk_ids = get_test_result_to_risk_ids($test_result["id"]);
    foreach($risk_ids as $risk_id) {
        save_test_result_to_risk($result_id, $risk_id);
    }

    updateTeamsOfItem($audit_id, 'audit', $test['teams']);

    // Add Tags to Test 
    $tags_current = getTagsOfTaggee($test_id, "test");
    $new_tags = array_unique(array_merge($tags, $tags_current));
    updateTagsOfType($audit_id, 'test_audit', $new_tags);

    // Close the database connection
    db_close($db);

    $message = "An active audit for \"{$test["name"]}\" was initiated by username \"" . $_SESSION['user'] . "\".";
    write_log((int)$test_id + 1000, $_SESSION['uid'], $message, "test");
    
    return $name;
}

/***********************************************
 * FUNCTION: GET FRAMEWORK CONTROL TEST AUDITS *
 ***********************************************/
function get_framework_control_test_audits($active, $columnName=false, $columnDir=false, $filters=false, $column_filters=[]){
    global $escaper;

    // Open the database connection
    $db = db_open();

    $select_background_class = $active ? "" : ", t8.background_class ";

    $sql = "
        SELECT t1.id, t1.test_id, t1.test_frequency, t7.last_date, t7.next_date, t1.name, t1.objective, t1.test_steps,
            t1.approximate_time, t1.expected_results, t1.framework_control_id, t1.desired_frequency, t1.status, t1.created_at,
            t2.name tester_name, t3.short_name control_name, IFNULL(GROUP_CONCAT(DISTINCT t4.name), '') framework_name, t5.test_result,
            t5.summary, t5.submitted_by, t5.test_date, t5.submission_date, ifnull(t6.name, '--') audit_status_name, 
            t7.additional_stakeholders{$select_background_class},
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag) as tags
        FROM `framework_control_test_audits` t1
            LEFT JOIN `user` t2 ON t1.tester = t2.value
            LEFT JOIN `framework_controls` t3 ON t1.framework_control_id = t3.id 
            LEFT JOIN `framework_control_mappings` m ON t3.id=m.control_id
            LEFT JOIN `frameworks` t4 ON m.framework=t4.value AND t4.status=1
            LEFT JOIN `framework_control_mappings` m_1 ON t3.id=m_1.control_id
            LEFT JOIN `framework_control_test_results` t5 ON t1.id=t5.test_audit_id
            LEFT JOIN `test_status` t6 ON t1.status=t6.value
            LEFT JOIN `framework_control_tests` t7 ON t7.id=t1.test_id
            LEFT JOIN `test_results` t8 ON t8.name=t5.test_result
            LEFT JOIN `tags_taggees` tt ON tt.taggee_id = t1.id AND tt.type = 'test_audit'
            LEFT JOIN `tags` tg on tg.id = tt.tag_id
    ";

    $wheres = array();
    $havings = array();

    $closed_audit_status = get_setting("closed_audit_status");

    // Active audits
    if($active)
    {
        $wheres[] = " t1.status<>'".$closed_audit_status."' ";
    }
    // Past audits
    else
    {
        $wheres[] = " t1.status='".$closed_audit_status."' ";
    }

    if($filters !== false){
        if(isset($filters['filter_control'])){
            if($filters['filter_control'])
            {
                foreach($filters['filter_control'] as &$val){
                    $val = (int)$val;
                }
                unset($val);

                $wheres[] = " t3.id IN (".implode(",", $filters['filter_control']).") ";
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }

        if(isset($filters['filter_test_result'])){
            if($filters['filter_test_result'])
            {
                foreach($filters['filter_test_result'] as &$val){
		          $val = (int)$val;
                }
                unset($val);

                if(in_array(0, $filters['filter_test_result'])) {
                    $wheres[] = " (t5.test_result='' OR t8.value IN (".implode(",", $filters['filter_test_result']).")) ";
                } else {
                    $wheres[] = " t8.value IN (".implode(",", $filters['filter_test_result']).") ";
                }
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }

        if(isset($filters['filter_framework'])){
            if($filters['filter_framework']){
                $framework_wheres = [];

                foreach($filters['filter_framework'] as $val){
                    if(!$val)
                        continue;
                    $val = (int)$val;
                    // If unassigned option.
                    if($val == -1)
                    {
                        $framework_wheres[] = "m.framework IS NULL";
                    }
                    else
                    {
                        $framework_wheres[] = "m_1.framework='{$val}'";
                    }
                }

                $wheres[] = "(". implode(" OR ", $framework_wheres) . ")";
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }

        if(isset($filters['filter_status'])){
            if($filters['filter_status'])
            {
                foreach($filters['filter_status'] as &$val){
                    $val = (int)$val;
                }
                unset($val);

                $wheres[] = " t1.status IN (".implode(",", $filters['filter_status']).") ";
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }
        if(!empty($filters['filter_start_audit_date'])){
            $wheres[] = " t1.last_date>=:filter_start_audit_date ";
        }
        if(!empty($filters['filter_end_audit_date'])){
            $wheres[] = " t1.last_date<=:filter_end_audit_date ";
        }
        if(isset($filters['filter_tester'])){
            if($filters['filter_tester'])
            {
                foreach($filters['filter_tester'] as &$val){
                    $val = (int)$val;
                }
                unset($val);

                $wheres[] = " t1.tester IN (".implode(",", $filters['filter_tester']).") ";
            }
            else
            {
                $wheres[] = " 0 ";
            }
        }

        if (isset($filters['filter_testname'])) {
            if ($filters['filter_testname']) {
                foreach ($filters['filter_testname'] as &$val) {
                    $val = (int)$val;
                }
                unset($val);

                $wheres[] = " t7.id IN (".implode(",", $filters['filter_testname']).") ";
            } else {
                $wheres[] = " 0 ";
            }
        }

        if (isset($filters['filter_tags'])) {
            $tag_wheres = [];
            $tag_ids = [];
            if ($filters['filter_tags']) {
                foreach ($filters['filter_tags'] as $val) {
                    $val = (int)$val;
                    // If unassigned option.
                    if($val == -1)
                    {
                        $havings[] = "ISNULL(GROUP_CONCAT(DISTINCT tg.id))";
                    }
                    else
                    {
                        $tag_ids = $val;
                        $havings[] = "FIND_IN_SET({$val},GROUP_CONCAT(DISTINCT tg.id))"; 
                        //$tag_wheres[] = "tg.id='{$val}'";
                    }
                }
            } else {
                $wheres[] = " 0 ";
            }
        }
    }

    $bind_params = [];
    $manual_column_filters = [];
    foreach($column_filters as $name => $column_filter){
        if($name == "test_name"){
            $wheres[] = "t1.name LIKE :test_name ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "test_frequency"){
            $wheres[] = "t1.test_frequency LIKE :test_frequency ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "tester") {
            $wheres[] = "t2.name LIKE :tester ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "additional_stakeholders") {
            $wheres[] = "t7.additional_stakeholders LIKE :additional_stakeholders ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "objective") {
            $wheres[] = "t1.objective LIKE :objective ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "control_name") {
            $wheres[] = "t3.short_name LIKE :control_name ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "test_result") {
            $wheres[] = "t8.name LIKE :test_result ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "status") {
            $wheres[] = "t6.name LIKE :status ";
            $bind_params[$name] = "%{$column_filter}%";
        } else {
            $manual_column_filters[$name] = $column_filter;
        }
    }

    $sql .= " WHERE t3.deleted = 0 AND ".implode(" AND ", $wheres);
    $sql .= " GROUP BY t1.id ";
    if(count($havings)) $sql .= " HAVING 1 AND (".implode(" OR ", $havings).")";
    
    if($columnName == "test_name"){
        $sql .= " ORDER BY t1.name {$columnDir} ";
    }
    elseif($columnName == "test_frequency"){
        $sql .= " ORDER BY t1.test_frequency {$columnDir} ";
    }
    elseif($columnName == "tester"){
        $sql .= " ORDER BY t2.name {$columnDir} ";
    }
    elseif($columnName == "objective"){
        $sql .= " ORDER BY t1.objective {$columnDir} ";
    }
    elseif($columnName == "control_name"){
        $sql .= " ORDER BY t3.short_name {$columnDir} ";
    }
    elseif($columnName == "framework_name"){
        $sql .= " ORDER BY GROUP_CONCAT(DISTINCT t4.name) {$columnDir} ";
    }
    elseif($columnName == "status"){
        $sql .= " ORDER BY t6.name {$columnDir} ";
    }
    elseif($columnName == "last_date"){
        $sql .= " ORDER BY t1.last_date {$columnDir}, t5.last_updated {$columnDir} ";
    }
    elseif($columnName == "next_date"){
        $sql .= " ORDER BY t1.next_date {$columnDir} ";
    }
    elseif($columnName == "test_result"){
        $sql .= " ORDER BY t5.test_result {$columnDir} ";
    }
    elseif($columnName == "additional_stakeholders"){
        $sql .= " ORDER BY t7.additional_stakeholders {$columnDir} ";
    }
    else{
        // Active audits
        if($active)
        {
            $sql .= " ORDER BY t1.created_at DESC ";
        }
        // Past audits
        else
        {
            $sql .= " ORDER BY t5.last_updated DESC ";
        }
    }

    $stmt = $db->prepare($sql);
    if($filters !== false){
        if(!empty($filters['filter_start_audit_date'])){
            $stmt->bindParam(":filter_start_audit_date", $filters['filter_start_audit_date'], PDO::PARAM_STR, 10);
        }
        if(!empty($filters['filter_end_audit_date'])){
            $stmt->bindParam(":filter_end_audit_date", $filters['filter_end_audit_date'], PDO::PARAM_STR, 10);
        }
    }
    foreach($bind_params as $name => $bind_param){
        $stmt->bindParam(":{$name}", $bind_param);
    }

    $stmt->execute();

    // Store tests in the array
    $test_audits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

     // If team separation is enabled
    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // It means that either the user is an admin
        // or everyone has access to the tests/audits.
        // It means we can treat Team Separation like it is disabled        
        $separation_enabled = !should_skip_test_and_audit_permission_check();
    } else
        $separation_enabled = false;

    $filtered_test_audits = array();

    foreach($test_audits as &$test_audit){
        if ($separation_enabled && !is_user_allowed_to_access($_SESSION['uid'], $test_audit['id'], 'audit'))
            continue;

        $framework_names = explode(",", $test_audit['framework_name']);
        $decrypted_framework_names = [];
        foreach($framework_names as $framework_name){
            if($framework_name){
                $decrypted_framework_names[] = try_decrypt(trim($framework_name));
            }
        }
        
        $test_audit['framework_name'] = implode(", ", $decrypted_framework_names);
        $test_audit['last_date'] = format_date($test_audit['last_date']);
        $test_audit['next_date'] = format_date($test_audit['next_date']);
        // Filter by search text
        if(
            empty($filters['filter_text']) 
            || (stripos($test_audit['name'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['last_date'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['control_name'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['framework_name'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['audit_status_name'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['test_result'], $filters['filter_text']) !== false) 
            || (stripos($test_audit['objective'], $filters['filter_text']) !== false) 
        )
        {
            $success = true;
            foreach($manual_column_filters as $column_name => $val){
                if($column_name == "last_date") {
                    if( stripos($test_audit['last_date'], $val) === false ){
                        $success = false;
                        break;
                    }
                } else if($column_name == "next_date") {
                    if( stripos($test_audit['next_date'], $val) === false ){
                        $success = false;
                        break;
                    }
                } else if($column_name == "framework_name") {
                    if( stripos($test_audit['framework_name'], $val) === false ){
                        $success = false;
                        break;
                    }
                }
            }
            if($success) $filtered_test_audits[] = $test_audit;
        }
    }

    return $filtered_test_audits;
}

/*******************************
 * FUNCTION: SAVE TEST COMMENT *
 *******************************/
function save_test_comment($test_audit_id, $comment){
    $user    =  $_SESSION['uid'];
    
    // Make sure the user has permission to comment
    if($_SESSION["comment_compliance"] == 1) {

        // Open the database connection
        $db = db_open();
        
        $sql = "
            INSERT INTO `framework_control_test_comments`(`test_audit_id`, `user`, `comment`) VALUES(:test_audit_id, :user, :comment);
        ";

        $enc_comment = try_encrypt($comment);
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
        $stmt->bindParam(":comment", $enc_comment, PDO::PARAM_STR);
        $stmt->bindParam(":user", $user, PDO::PARAM_INT);
        
        // Insert a test result
        $stmt->execute();
        
        // Close the database connection
        db_close($db);
        
        // If notification is enabled
        if (notification_extra())
        {
            // Include the notification extra
            require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

            // Send the notification
            notify_audit_comment($test_audit_id, $comment);
        }        

        set_alert(true, "good",  "Your comment has been successfully added to the audit.");
    }
    else {
        set_alert(true, "bad", "You do not have permission to add comments to audits.");
    }
}

/**********************************
 * FUNCTION: GET COMPLIANCE FILES *
 **********************************/
function get_compliance_files($ref_id, $ref_type){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.* FROM `compliance_files` t1 WHERE t1.`ref_id`=:ref_id and t1.`ref_type`=:ref_type;");
    $stmt->bindParam(":ref_id", $ref_id, PDO::PARAM_INT);
    $stmt->bindParam(":ref_type", $ref_type, PDO::PARAM_STR, 20);
    $stmt->execute();

    $files = $stmt->fetchAll();

    // Close the database connection
    db_close($db);
    
    return $files;
}

/*******************************************
 * FUNCTION: DISPLAY TESTING IN COMPLIANCE *
 *******************************************/
function display_testing()
{
    global $lang, $escaper;
    
    $test_audit_id = (int)$_GET['id'];
    
    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
    if(!$test_audit['id']){
        echo $escaper->escapeHtml($lang['TestAuditDoesNotExist']);
        return;
    }

    // If test date is not set, set today as default
    $test_audit['test_date'] = format_date($test_audit['test_date'], date(get_default_date_format()));
    if(isset($_SESSION["modify_audits"]) && $_SESSION["modify_audits"] == 1){
        $submit_button = "<button name='submit_test_result'  id='submit_test_result' type='button'>".$escaper->escapeHtml($lang['Submit'])."</button>";
    } else $submit_button = "";
    $risk_ids = get_test_result_to_risk_ids($test_audit["result_id"]);
    $close_risks = isset($_SESSION["close_risks"])?$_SESSION["close_risks"]:0;

    $tags_view = "";
    if ($test_audit['tags']) {
        foreach(explode(",", $test_audit['tags']) as $tag) {
            $tags_view .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin-right:2px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
        }
    } else {
        $tags_view .= "--";
    }
  
    echo "
        <form id='edit-test' class='well' method='POST' enctype='multipart/form-data'>
            <h4>".$escaper->escapeHtml($test_audit['name'])."</h4>
            <input name='origin_test_results' id='origin_test_results' value='".$test_audit['test_result']."' type='hidden' data-permission='".$close_risks."'>
            <input name='remove_associated_risk' id='remove_associated_risk' value='0' type='hidden'>
            <input name='associate_new_risk_id' id='associate_new_risk_id' value='' type='hidden'>
            <input name='associate_exist_risk_ids' id='associate_exist_risk_ids' value='".implode(",", $risk_ids)."' type='hidden'>
            <table width='100%'>
                <tr>
                    <td width='50%' valign='top'>
                        <table width='100%'>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['AuditStatus']).":&nbsp;&nbsp;</td>
                                <td>";
                                    create_dropdown("test_status", $test_audit['status'], "status", true, false, false, "", "--");
                                    
                                echo "
                                </td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['TestResult']).":&nbsp;&nbsp;</td>
                                <td>";
                                    create_dropdown("test_results", $test_audit['test_result'], "test_result", true, false, false, "", "--");
                                echo "
                                </td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['Tester']).":&nbsp;&nbsp;</td>
                                <td>";
                                    create_dropdown("enabled_users", $test_audit['tester'], "tester", false, false, false);
                                echo "
                                </td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['TestDate']).":&nbsp;&nbsp;</td>
                                <td>
                                    <input name='test_date' value='{$test_audit['test_date']}' required class='datepicker form-control' type='text'>
                                </td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['Teams']).":&nbsp;&nbsp;</td>
                                <td>";
                                    create_multiple_dropdown("team", $test_audit['teams']);
                                echo "
                                </td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['Objective']).":&nbsp;&nbsp;</td>
                                <td align='left'>".$escaper->escapeHtml($test_audit['objective'] ? $test_audit['objective'] : "--")."</td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['TestSteps']).":&nbsp;&nbsp;</td>
                                <td align='left'>".$escaper->escapeHtml($test_audit['test_steps'] ? $test_audit['test_steps'] : "--")."</td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['Tags']).":&nbsp;&nbsp;</td>
                                <td align='left'>
                                    <select style='width:300px' class=\"test_audit_tags\" readonly id=\"tags\" name=\"tags[]\" multiple placeholder=".$escaper->escapeHtml($lang['TagsWidgetPlaceholder']).">";
                                    if ($test_audit['tags']) {
                                        foreach(explode(",", $test_audit['tags']) as $tag) {
                                            $tag = $escaper->escapeHtml($tag);
                                            echo "<option selected value='{$tag}'>{$tag}</option>";
                                        }
                                    }
                                    echo "
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td>
                        <table width='100%'>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['Summary']).":&nbsp;&nbsp;</td>
                                <td>
                                    <textarea name='summary' class='form-control' style='width:100%'>".$escaper->escapeHtml($test_audit['summary'])."</textarea>
                                </td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['Attachment']).":&nbsp;&nbsp;</td>
                                <td>
                                     <div class=\"file-uploader\">
                                        <label for=\"audit-file-upload\" class=\"btn\">".$escaper->escapeHtml($lang['ChooseFile'])."</label>
                                        <span class=\"file-count-html\"> <span class=\"file-count\">".count(get_compliance_files($test_audit_id, "test_audit"))."</span> ".$escaper->escapeHtml($lang['FileAdded'])."</span>
                                        <p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>
                                        <ul class=\"exist-files\">
                                            ";
                                            display_compliance_files($test_audit_id, "test_audit");
                                        echo "
                                        </ul>
                                        <ul class=\"file-list\">
                                            
                                        </ul>
                                        <input type=\"file\" id=\"audit-file-upload\" name=\"file[]\" class=\"hidden-file-upload active\" />
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['AdditionalStakeholders']).":&nbsp;&nbsp;</td>
                                <td align='left'>".$escaper->escapeHtml($test_audit['additional_stakeholders'] ? get_stakeholder_names($test_audit['additional_stakeholders']) : "--")."</td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['ControlOwner']).":&nbsp;&nbsp;</td>
                                <td align='left'>".$escaper->escapeHtml($test_audit['control_owner'] ? get_name_by_value("user", $test_audit['control_owner']) : "--")."</td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['ExpectedResults']).":&nbsp;&nbsp;</td>
                                <td align='left'>".$escaper->escapeHtml($test_audit['expected_results'] ? $test_audit['expected_results'] : "--")."</td>
                            </tr>
                            <tr>
                                <td valign='top'>".$escaper->escapeHtml($lang['ApproximateTime']).":&nbsp;&nbsp;</td>
                                <td align='left'>".(int)$test_audit['approximate_time']. " " .$escaper->escapeHtml($test_audit['approximate_time'] > 1 ? $lang['minutes'] : $lang['minute'])."</td>
                            </tr>
                        </table>                    
                    </td>
                </tr>
                <tr>
                    <td align='right' colspan='2'>".$submit_button."</td>
                </tr>
            </table>

        </form>
    ";
    
    // Display associated risks
    display_associated_risks($risk_ids);

    // Display test audit comment
    display_test_audit_comment($test_audit_id);
    
    // Display test audit trail
    display_test_audit_trail($test_audit_id);
    
    echo "
        <script>
            $( document ).ready(function() {
                $(\"[name='team[]']\").multiselect({enableFiltering: true, buttonWidth: '300px'});
                // $(\".multiselect\").multiselect({buttonWidth: '100%'});
                // $(\".datepicker\").datepicker();
            });
        </script>
    ";
}

/**************************************
 * FUNCTION: DISPLAY COMPLIANCE FILES *
 **************************************/
function display_compliance_files($ref_id, $ref_type){
    global $lang, $escaper;
    
    $files = get_compliance_files($ref_id, $ref_type);
    
    $html = "";
    
    foreach($files as $file){
        $html .= "
            <li>            
                <div class=\"file-name\"><a href=\"".$_SESSION['base_url']."/compliance/download.php?id=".$escaper->escapeHtml($file['unique_name'])."\">".$escaper->escapeHtml($file['name'])."</a></div>
                <a href=\"#\" class=\"remove-file\" data-id=\"file-upload-0\"><i class=\"fa fa-times\"></i></a>
                <input name=\"unique_names[]\" value=\"". $escaper->escapeHtml($file['unique_name']) ."\" type=\"hidden\">
            </li>            
        ";
    }
    
    echo $html;
    
    return count($files);
}

/**************************************
 * FUNCTION: DISPLAY TEST AUDIT TRAIL *
 **************************************/
function display_test_audit_trail($test_audit_id)
{
    global $escaper, $lang;
    
    echo "
        <div class=\"row-fluid comments--wrapper\" >
            <div class=\"well\" >
                <h4 class=\"collapsible--toggle\"><span><i class=\"fa fa-caret-right\"></i>".$escaper->escapeHtml($lang['AuditTrail'])."</span></h4>
                <div class=\"collapsible\" style='display:none'>
                    <div class=\"row-fluid\">
                        <div class=\"span12 audit-trail\">";
                            get_audit_trail_html($test_audit_id+1000, 36500, 'test_audit');
                        echo "</div>
                    </div>
                </div>
            </div>
        </div>
    ";
}

function display_test_audit_framework_control($framework_control_id) {
    if ($framework_control_id) {
        global $escaper, $lang;
        $control = get_framework_controls($framework_control_id);
        if(count($control)){
            $control = $control[0];
            echo "
            <div class='row-fluid framework-control-wrapper'>
                <div class='well'>
                    <h4 class='collapsible--toggle'><span><i class='fa fa-caret-down'></i>".$escaper->escapeHtml($lang['ControlDetails'])."</span></h4>
                    <div class='framework-control collapsible'>
                        <table width='100%'>
                            <tr>
                                <td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlLongName'])."</strong>: </td>
                                <td colspan='5'>".$escaper->escapeHtml($control['long_name'])."</td>
                            </tr>
                            <tr>
                                <td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlShortName'])."</strong>: </td>
                                <td width='57%' colspan='3'>".$escaper->escapeHtml($control['short_name'])."</td>
                                <td width='13%' align='right' ><strong>".$escaper->escapeHtml($lang['ControlOwner'])."</strong>: </td>
                                <td width='17%'>".$escaper->escapeHtml($control['control_owner_name'])."</td>
                            </tr>
                            <tr>
                                <td  align='right'><strong>".$escaper->escapeHtml($lang['ControlClass'])."</strong>: </td>
                                <td>".$escaper->escapeHtml($control['control_class_name'])."</td>
                                <td  align='right'><strong>".$escaper->escapeHtml($lang['ControlPhase'])."</strong>: </td>
                                <td>".$escaper->escapeHtml($control['control_phase_name'])."</td>
                                <td  align='right'><strong>".$escaper->escapeHtml($lang['ControlNumber'])."</strong>: </td>
                                <td>".$escaper->escapeHtml($control['control_number'])."</td>
                            </tr>
                            <tr>
                                <td align='right'><strong>".$escaper->escapeHtml($lang['ControlPriority'])."</strong>: </td>
                                <td>".$escaper->escapeHtml($control['control_priority_name'])."</td>
                                <td width='200px' align='right'><strong>".$escaper->escapeHtml($lang['ControlFamily'])."</strong>: </td>
                                <td>".$escaper->escapeHtml($control['family_short_name'])."</td>
                                <td width='200px' align='right'><strong>".$escaper->escapeHtml($lang['MitigationPercent'])."</strong>: </td>
                                <td>".$escaper->escapeHtml($control['mitigation_percent'])."%</td>
                            </tr>
                            <tr>
                                <td align='right'><strong>".$escaper->escapeHtml($lang['Description'])."</strong>: </td>
                                <td colspan='5'>".$escaper->escapeHtml($control['description'])."</td>
                            </tr>
                            <tr>
                                <td align='right'><strong>".$escaper->escapeHtml($lang['SupplementalGuidance'])."</strong>: </td>
                                <td colspan='5'>".$escaper->escapeHtml($control['supplemental_guidance'])."</td>
                            </tr>
                        </table>\n";
        
                $mapped_frameworks = get_mapping_control_frameworks($control['id']);
                echo "
                            <div class='container-fluid'>
                                <div class='well'>
                                    <h5><span>".$escaper->escapeHtml($lang['MappedControlFrameworks'])."</span></h5>
                                    <table width='100%' class='table table-bordered'>
                                        <tr>
                                            <th width='50%'>".$escaper->escapeHtml($lang['Framework'])."</th>
                                            <th width='35%'>".$escaper->escapeHtml($lang['Control'])."</th>
                                        </tr>";
                foreach ($mapped_frameworks as $framework){
                    echo "
                                        <tr>
                                            <td>".$escaper->escapeHtml($framework['framework_name'])."</td>
                                            <td>".$escaper->escapeHtml($framework['reference_name'])."</td>
                                        </tr>";
                }
                echo "
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>";
            }
        }
}

/**************************************
 * FUNCTION: DISPLAY ASSOCIATED RISKS *
 **************************************/
function display_associated_risks($risk_ids){
    global $escaper, $lang;

    echo "
        <div class=\"row-fluid comments--wrapper\" >
            <div class=\"well\" >
                <h4 class=\"collapsible--toggle\">
                    <span><i class=\"fa fa-caret-right\"></i>".$escaper->escapeHtml($lang['Risks'])."</span>
                </h4>
                <div class=\"collapsible\" style='display:none'>
                    <div class=\"row-fluid\">
                        <div class=\"span12 text-right\" style='padding:0 20px;'>
                            <button class=\"btn btn-default associate_new_risk\">".$escaper->escapeHtml($lang['NewRisk'])."</button>
                            <button class=\"btn btn-default associate_existing_risk\">".$escaper->escapeHtml($lang['ExistingRisk'])."</button>
                        </div>
                    </div>
                    <div class=\"row-fluid\">
                        <div class=\"span12 audit-trail\">
                            <table width='100%' class='table table-bordered mapping_framework_table'>
                                <thead>
                                    <tr>
                                        <th width='5%'>".$escaper->escapeHtml($lang['ID'])."</th>
                                        <th width='90%'>".$escaper->escapeHtml($lang['Subject'])."</th>
                                        <th>".$escaper->escapeHtml($lang["Actions"])."</th>
                                    </tr>
                                </thead>
                                <tbody>";
                            foreach ($risk_ids as $key => $risk_id) {
                                $risk = get_risk_by_id($risk_id + 1000);
                                $no = $key + 1;
                                $subject = try_decrypt($risk[0]['subject']);
                                echo "<tr>
                                    <td style='text-align:center'>".($risk_id + 1000)."</td>
                                    <td>".$escaper->escapeHtml($subject)."</td>
                                    <td style='text-align:center'><a href='javascript:void(0);' class='delete-risk' data-risk-id='".$risk_id."' data-risk-id='".$risk_id."' title='".$escaper->escapeHtml($lang["Delete"])."'><i class='fa fa-trash'></i></a></td>
                                </tr>";
                            }

                        echo " </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    ";
}

/****************************************
 * FUNCTION: DISPLAY TEST AUDIT COMMENT *
 ****************************************/
function display_test_audit_comment($test_audit_id)
{
    global $escaper, $lang;
    
    $test_audit_id = (int)$test_audit_id;
    
    echo "
        <div class=\"row-fluid comments--wrapper\">

            <div class=\"well\">
                <h4 class=\"collapsible--toggle clearfix\">
                    <span><i class=\"fa  fa-caret-right\"></i>".$escaper->escapeHtml($lang['Comments'])."</span>
                    <a href=\"#\" class=\"add-comments pull-right\"><i class=\"fa fa-plus\"></i></a>
                </h4>

                <div class=\"collapsible\" style='display:none'>
                    <div class=\"row-fluid\">
                        <div class=\"span12\">

                            <form id=\"comment\" class=\"comment-form\" name=\"add_comment\" method=\"post\">
                                <input type='hidden' name='id' value='{$test_audit_id}'>
                                <textarea style=\"width: 100%; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box;\" name=\"comment\" cols=\"50\" rows=\"3\" id=\"comment-text\" class=\"comment-text\"></textarea>
                                <div class=\"form-actions text-right\" id=\"comment-div\">
                                    <input class=\"btn\" id=\"rest-btn\" value=\"".$escaper->escapeHtml($lang['Reset'])."\" type=\"reset\" />
                                    <button id=\"comment-submit\" type=\"submit\" name=\"submit\" class=\"comment-submit btn btn-primary\" >".$escaper->escapeHtml($lang['Submit'])."</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class=\"row-fluid\">
                        <div class=\"span12\">
                            <div class=\"comments--list clearfix\">
                                ".get_testing_comment_list($test_audit_id)."
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    ";
    
    echo "
        <script>

            \$('body').on('click', '.collapsible--toggle span', function(event) {
                event.preventDefault();
                var container = \$(this).parents('.comments--wrapper');
                \$(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
                \$(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                if($('.collapsible', container).is(':visible') && $('.add-comments', container).hasClass('rotate')){
                    $('.add-comments', container).click()
                }
            });

            $('body').on('click', '.add-comments', function(event) {
                event.preventDefault();
                var container = \$(this).parents('.comments--wrapper');
                if(!$('.collapsible', container).is(':visible')){
                    $(this).parents('.collapsible--toggle').next('.collapsible').slideDown('400');
                    $(this).parent().find('span i').removeClass('fa-caret-right');
                    $(this).parent().find('span i').addClass('fa-caret-down');
                }
                $(this).toggleClass('rotate');
                $('.comment-form', container).fadeToggle('100');
            });
            
            $('body').on('click', '.comment-submit', function(e){
                e.preventDefault();
                var container = $('.comments--wrapper');
                
                if(!$('.comment-text', container).val()){
                    $('.comment-text', container).focus();
                    return;
                }
                
                var risk_id = $('.large-text', container).html();
                
                var getForm = \$(this).parents('form', container);
                var form = new FormData($(getForm)[0]);

                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/compliance/save_audit_comment',
                    data: form,
                    contentType: false,
                    processData: false,
                    success: function(data){
                        $('.comments--list', container).html(data.data);
                        $('.comment-text', container).val('');
                        $('.comment-text', container).focus();
                        showAlertsFromArray(data.status_message);
                    },
                    error: function(xhr,status,error){
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                        if(!retryCSRF(xhr, this))
                        {
                        }
                    }
                })
            })
        
        </script>
    ";
      
    return true;
}

/******************************************
 * FUNCTION: DISPLAY TESTING COMMENT LIST *
 ******************************************/
function get_testing_comment_list($test_audit_id)
{
    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the comments
    $stmt = $db->prepare("SELECT a.date, a.comment, b.name FROM framework_control_test_comments a LEFT JOIN user b ON a.user = b.value WHERE a.test_audit_id=:test_audit_id ORDER BY a.date DESC");

    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);

    $stmt->execute();

    // Store the list in the array
    $comments = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    $returnHTML = "";
    foreach ($comments as $comment)
    {
//        $text = try_decrypt($comment['comment']);
        $text = $comment['comment'];
        $date = date(get_default_datetime_format("g:i A T"), strtotime($comment['date']));
        $user = $comment['name'];
        
        if($text != null){
            $returnHTML .= "<p class=\"comment-block\">\n";
            $returnHTML .= "<b>" . $escaper->escapeHtml($date) ." by ". $escaper->escapeHtml($user) ."</b><br />\n";
            $returnHTML .= $escaper->escapeHtml(try_decrypt($text));
            $returnHTML .= "</p>\n";
        }
    }

    return $returnHTML;
    
}

/********************************
 * FUNCTION: INSERT TEST RESULT *
 ********************************/
function save_test_result($test_audit_id, $status, $test_result, $tester, $test_date, $teams, $summary, $tags=[]) {

    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);

    $submitted_by = $_SESSION['uid'];

    // Open the database connection
    $db = db_open();

    $submission_date = date("Y-m-d H:i:s");

    // Check submitted result is existing
    if(!$test_audit['result_id']) {
        $sql = "INSERT INTO framework_control_test_results(`test_audit_id`, `test_result`, `summary`, `test_date`, `submitted_by`, `submission_date`) VALUES(:test_audit_id, :test_result, :summary, :test_date, :submitted_by, :submission_date);";
    } else {
        $sql = "UPDATE framework_control_test_results SET `test_result`=:test_result, `summary`=:summary, `test_date`=:test_date, `submitted_by`=:submitted_by, `submission_date`=:submission_date WHERE `test_audit_id`=:test_audit_id;";
    }

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":test_result", $test_result, PDO::PARAM_STR);
    $stmt->bindParam(":test_date", $test_date, PDO::PARAM_STR);
    $stmt->bindParam(":summary", $summary, PDO::PARAM_STR);
    $stmt->bindParam(":submitted_by", $submitted_by, PDO::PARAM_STR);
    $stmt->bindParam(":submission_date", $submission_date, PDO::PARAM_STR);
    $stmt->execute();

    // Update tester in test audit table
    $stmt = $db->prepare("UPDATE framework_control_test_audits SET `tester`=:tester WHERE `id`=:test_audit_id;");
    $stmt->bindParam(":tester", $tester, PDO::PARAM_INT);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Update teams of the active audit
    updateTeamsOfItem($test_audit_id, 'audit', $teams, false);

    // Update status in test_audit table
    update_test_audit_status($test_audit_id, $status);

    // Update tags of the active audit
    updateTagsOfType($test_audit_id, 'test_audit', $tags);

    $closed_audit_status = get_setting("closed_audit_status");

    // Check audit was closed
    if($status == $closed_audit_status) {
        // update last audit date and next audit date in test_audit table
        update_last_and_next_auditdate($test_audit_id, $test_date);

        $test_audit_after = get_framework_control_test_audit_by_id($test_audit_id);
        $changes = get_changes('audit', $test_audit, $test_audit_after);

        $message = _lang('AuditLog_TestAuditClosed', ['test_audit_name' => $test_audit["name"], 'test_audit_id' => $test_audit_id, 'user_name' => $_SESSION['user'], 'changes' => $changes], false);
        write_log((int)$test_audit_id + 1000, $_SESSION['uid'], $message, "test_audit");
    } else {
        $test_audit_after = get_framework_control_test_audit_by_id($test_audit_id);
        $changes = get_changes('audit', $test_audit, $test_audit_after);

        $message = _lang('AuditLog_TestAuditUpdated', ['test_audit_name' => $test_audit["name"], 'test_audit_id' => $test_audit_id, 'user_name' => $_SESSION['user'], 'changes' => $changes], false);
        write_log((int)$test_audit_id + 1000, $_SESSION['uid'], $message, "test_audit");
    }

    return true;
}

/**************************************
 * FUNCTION: UPDATE TEST AUDIT STATUS *
 **************************************/
function update_test_audit_status($test_audit_id, $status=0) {

    $old_status = get_test_audit_status($test_audit_id);

    // Open the database connection
    $db = db_open();

    // Update test audit status
    $stmt = $db->prepare("UPDATE `framework_control_test_audits` SET `status` = :status WHERE `id` = :test_audit_id;");
    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // If notification is enabled and the status changed
    if (notification_extra() && $old_status != $status)
    {
        // Include the notification extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_audit_status_change($test_audit_id, $old_status, $status);
    }
}

/***********************************************************
 * FUNCTION: UPDATE LAST DATE AND NEXT DATE IN AUDIT TABLE *
 ***********************************************************/
function update_last_and_next_auditdate($test_audit_id, $last_date)
{
    // Get test by ID
    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
    $next_date = date("Y-m-d", strtotime($last_date) + $test_audit['test_frequency']*24*60*60);
    if($next_date < date("Y-m-d")){
        $next_date = date("Y-m-d");
    }

    // Open the database connection
    $db = db_open();
    
    $sql = "UPDATE `framework_control_test_audits` SET `last_date`=:last_date, `next_date`=:next_date WHERE id=:test_audit_id;";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":last_date", $last_date, PDO::PARAM_STR);
    $stmt->bindParam(":next_date", $next_date, PDO::PARAM_STR);
    
    // Update test status
    $stmt->execute();
    
    $sql = "UPDATE `framework_control_tests` t1 JOIN `framework_control_test_audits` t2 ON t1.id=t2.test_id SET t1.`last_date`=:last_date, t1.`next_date`=:next_date WHERE t2.id=:test_audit_id;";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->bindParam(":last_date", $last_date, PDO::PARAM_STR);
    $stmt->bindParam(":next_date", $next_date, PDO::PARAM_STR);
    
    // Update test status
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: DELETE COMPLIANCE FILE *
 ************************************/
function delete_compliance_file($file_id){
    // Open the database connection
    $db = db_open();

    // Delete a compliance file by file ID
    $stmt = $db->prepare("DELETE FROM `compliance_files` WHERE id=:file_id; ");
    $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/********************************
 * FUNCTION: SUBMIT TEST RESULT *
 ********************************/
function submit_test_result()
{
    global $escaper, $lang;

    $test_audit_id  = (int)$_GET['id'];
    $test_audit_status  = (int)$_POST['status'];
    $test_result    = $_POST['test_result'];
    $tester         = (int)$_POST['tester'];
    $test_date      = $_POST['test_date'];
    $teams          = isset($_POST['team']) ? $_POST['team'] : [];
    $summary        = $_POST['summary'];
    $tags           = isset($_POST['tags']) ? $_POST['tags'] : [];
    
    if(!$test_audit_id || !$tester || !$test_date)
    {
        set_alert(true, "bad", $lang['InvalidParams']);
        return false;
    }
    else
    {
        // Convert test_date to standard format 
        $test_date = get_standard_date_from_default_format($test_date);
        
        // Check if user already attached files 
        $unique_names = isset($_POST['unique_names']) ? $_POST['unique_names'] : [];
        
        // Get existing files
        $files = get_compliance_files($test_audit_id, "test_audit");
        
        // Delete files
        foreach($files as $file){
            // Check if file is deleted
            if(!in_array($file['unique_name'], $unique_names)){
                delete_compliance_file($file['id']);
            }
        }
    
        // If submitted files are existing, save files
        if(!empty($_FILES['file'])){
            $files = $_FILES['file'];
            list($status, $file_ids, $errors) = upload_compliance_files($test_audit_id, "test_audit", $files);
        }
        
        // Check if error was happen in uploading files
        if(!empty($errors)){
            $errors = array_unique($errors);
            set_alert(true, "bad", implode(", ", $errors));
            return false;
        }else{
            // Save a test result
            save_test_result($test_audit_id, $test_audit_status, $test_result, $tester, $test_date, $teams, $summary, $tags);
            $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
            $result_id = $test_audit["result_id"];


            if($_POST['remove_associated_risk']) {
                close_risks_by_test_result_id($result_id, $test_result);
                delete_test_result_to_risk_by_result_id($result_id);
            } else {
                // add existing risks
                $associate_exist_risk_ids = isset($_POST['associate_exist_risk_ids']) ? $_POST['associate_exist_risk_ids'] : "";
                delete_test_result_to_risk_by_result_id($result_id);
                if($associate_exist_risk_ids) {
                    $risk_ids = explode(",", $associate_exist_risk_ids);
                    foreach($risk_ids as $risk_id) {
                        save_test_result_to_risk($result_id, $risk_id);
                    }
                }

                // add new risk
                $associate_new_risk_id = isset($_POST['associate_new_risk_id']) ? $_POST['associate_new_risk_id'] : "";
                if($associate_new_risk_id) {
                    $new_risk_id = (int)$associate_new_risk_id - 1000;
                    save_test_result_to_risk($result_id, $new_risk_id);
                }
            }

             
          set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
            return true;
        }
    }
    
}

/****************************************
 * FUNCTION: SUBMIT TEST RESULT TO RISK *
 ****************************************/
function submit_test_result_to_risk()
{
    global $escaper, $lang;

    $test_audit_id  = (int)$_GET['id'];

    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
    $result_id = $test_audit["result_id"];

    // add existing risks
    $associate_exist_risk_ids = isset($_POST['associate_exist_risk_ids']) ? $_POST['associate_exist_risk_ids'] : "";
    delete_test_result_to_risk_by_result_id($result_id);
    if($associate_exist_risk_ids) {
        $risk_ids = explode(",", $associate_exist_risk_ids);
        foreach($risk_ids as $risk_id) {
            save_test_result_to_risk($result_id, $risk_id);
        }
    }

    // add new risk
    $associate_new_risk_id = isset($_POST['associate_new_risk_id']) ? $_POST['associate_new_risk_id'] : "";
    if($associate_new_risk_id) {
        $new_risk_id = (int)$associate_new_risk_id - 1000;
        save_test_result_to_risk($result_id, $new_risk_id);
    }
   
    set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
    return true;
}

/**************************************
 * FUNCTION: DOWNLOAD COMPLIANCE FILE *
 **************************************/
function download_compliance_file($unique_name)
{
    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the file from the database
    $stmt = $db->prepare("SELECT * FROM compliance_files WHERE BINARY unique_name=:unique_name");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->execute();

    // Store the results in an array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // If the array is empty
    if (empty($array))
    {
        // Do nothing
        exit;
    }
    else
    {
        header("Content-length: " . $array['size']);
        header("Content-type: " . $array['type']);
        header("Content-Disposition: attachment; filename=" . $escaper->escapeUrl($array['name']));
        echo $array['content'];
        exit;
    }
}

/*********************************
 * FUNCTION: DISPLAY PAST AUDITS *
 *********************************/
function display_past_audits()
{
    global $lang, $escaper;

    $tableID = "past-audits";
    $tags = [];
    foreach(getTagsOfType("test") as $tag) {
        $tags[] = array('name' => $escaper->escapeHtml($tag['tag']), 'value' => (int)$tag['id']);
    }

    echo "
        <div id='filter-container' class='well'>
            <div class=\"row-fluid\">
                <div class=\"span12\">
                    <a href=\"javascript:;\" onclick=\"javascript: $('#filter-container').remove();\"><img src=\"../images/X-100.png\" width=\"10\" height=\"10\" align=\"right\" /></a>
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['TestResult']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span3'>";
                    create_multiple_dropdown("test_results_filter", "all", "filter_by_test_result", null, true, "--");
    echo "
                </div>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Control']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span4'>";
                    $options = getHasBeenAuditFrameworkControlList();
                    create_multiple_dropdown("framework_controls", "all", "filter_by_control", $options);
    echo "      </div>
            </div>
            <div class='row-fluid'>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['TestName']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span3'>
                    <div class='multiselect-content-container'>";
    $selected = isset($_GET['test_id']) ? array($_GET['test_id']) : 'all';
    create_multiple_dropdown("framework_control_tests", $selected, "filter_by_testname");
    echo "
                    </div>
                </div>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Framework']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span4'>
                    <div class='multiselect-content-container'>
                        <select id='filter_by_framework' class='' multiple=''>
                            <option selected value='-1'>".$escaper->escapeHtml($lang['Unassigned'])."</option>\n";
                            $options = getHasBeenAuditFrameworkList();
                            is_array($options) || $options = array();
                            foreach($options as $option){
                                echo "<option selected value=\"".$escaper->escapeHtml($option['value'])."\">".$escaper->escapeHtml($option['name'])."</option>\n";
                            }
                        echo "</select>
                    </div>
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['FilterByText']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span3'>
                    <input type='text' id='filter_by_text' class='form-control'>
                </div>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['Tags']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span4'>";
                    create_multiple_dropdown("tags", "all", "filter_by_tags", $tags, true, $escaper->escapeHtml($lang['Unassigned']), "-1");
                echo "
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span2' align='right'>
                    <strong>".$escaper->escapeHtml($lang['AuditDate']).":&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class='span10'>
                    <input type='text' id='start_audit_date' class='form-control' placeholder='".$escaper->escapeHtml($lang['StartDate'])."'>
                    &nbsp;&nbsp;~&nbsp;&nbsp;
                    <input type='text' id='end_audit_date' class='form-control' placeholder='".$escaper->escapeHtml($lang['EndDate'])."'>
                </div>
            </div>
        </div>

        <table id=\"{$tableID}\" width=\"100%\" class=\"risk-datatable table table-bordered table-striped table-condensed\">
            <thead >
                <tr >
                    <th data-name='test_name' valign=\"top\">".$escaper->escapeHtml($lang['TestName'])."</th>
                    <th data-name='last_date' valign=\"top\">".$escaper->escapeHtml($lang['AuditDate'])."</th>
                    <th data-name='control_name' valign=\"top\">".$escaper->escapeHtml($lang['ControlName'])."</th>
                    <th data-name='framework_name' valign=\"top\">".$escaper->escapeHtml($lang['FrameworkName'])."</th>
                    <th data-name='tags' valign=\"top\">".$escaper->escapeHtml($lang['Tags'])."</th>
                    <th data-name='status' valign=\"top\">".$escaper->escapeHtml($lang['Status'])."</th>
                    <th data-name='test_result' valign=\"top\">".$escaper->escapeHtml($lang['TestResult'])."</th>
                    <th data-name='actions' valign=\"top\"></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            $('#filter_by_framework, #filter_by_testname').multiselect({
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                maxHeight: 250,
                buttonWidth: '100%',
                includeSelectAllOption: true,
                enableCaseInsensitiveFiltering: true,
            });

            $('#filter_by_control').multiselect({
                enableFiltering: true,
                enableCaseInsensitiveFiltering: true,
                filterPlaceholder: '".$escaper->escapeHtml($lang["SelectForControls"])."',
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                maxHeight: 250,
                buttonWidth: '100%',
                includeSelectAllOption: true
            });

            $('#filter_by_test_result, #filter_by_tags').multiselect({
                allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                maxHeight: 250,
                buttonWidth: '100%',
                includeSelectAllOption: true
            });

            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            $('#{$tableID} thead tr').clone(true).appendTo( '#{$tableID} thead');
            $('#{$tableID} thead tr:eq(1) th').each( function (i) {
                var title = $(this).text();
                var data_name = $(this).attr('data-name');
                if(data_name != 'actions') {
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                }
         
                $( 'input, select', this ).on( 'keyup change', function () {
                    if ( datatableInstance.column(i).search() !== this.value ) {
                        datatableInstance.column(i).search( this.value ).draw();
                    }
                });
            });
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: true,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                orderCellsTop: true,
                pagingType: \"full_numbers\",
                pageLength: pageLength,
                dom : \"lrti<'#view-all.view-all'>p\",
                order: [],
                createdRow: function(row, data, index){
                    var background = $('.background-class', $(row)).data('background');
                    $(row).find('td').addClass(background)
                },
                columnDefs : [
                    {
                        'targets' : [-1],
                        'orderable': false,
                        'className' : 'vcenter'
                    }
                ],
                ajax: {
                    url: BASE_URL + '/api/compliance/past_audits',
                    type: 'POST',
                    data: function(d){
                        d.filter_text = \$(\"#filter_by_text\").val();
                        d.filter_control    = \$(\"#filter_by_control\").val();
                        d.filter_test_result = \$(\"#filter_by_test_result\").val();
                        d.filter_framework  = \$(\"#filter_by_framework\").val();
                        d.filter_start_audit_date   = \$(\"#start_audit_date\").val();
                        d.filter_end_audit_date     = \$(\"#end_audit_date\").val();
                        d.filter_testname = \$(\"#filter_by_testname\").val();
                        d.filter_tags = \$(\"#filter_by_tags\").val();
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
            })
            
            $('body').on('click', '.reopen', function(){
                var id = $(this).data('id');
                \$.ajax({
                    type: \"POST\",
                    url: BASE_URL + \"/api/compliance/reopen_audit\",
                    data:{
                        id: id
                    },
                    success: function(result){
                        $('#{$tableID}').DataTable().draw();
                    },
                    error: function(xhr,status,error){
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                        if(!retryCSRF(xhr, this))
                        {
                        }
                    }
                })
            })
            
            // Redraw Past Audit table
            function redrawPastAudits(){
                $(\"#{$tableID}\").DataTable().draw();
            } 
            
            // timer identifier
            var typingTimer;
            // time in ms (1 second)
            var doneTypingInterval = 1000;

            $('#start_audit_date, #end_audit_date').datepicker();
            $('select[multiple=multiple]').change(function(){
                redrawPastAudits();
            });
            // Search filter event
            $('#filter_by_text').keyup(function(){
                clearTimeout(typingTimer);
                typingTimer = setTimeout(redrawPastAudits, doneTypingInterval);
            });
            $('#start_audit_date').change(function(){
                clearTimeout(typingTimer);
                typingTimer = setTimeout(redrawPastAudits, doneTypingInterval);
            });
            $('#end_audit_date').change(function(){
                clearTimeout(typingTimer);
                typingTimer = setTimeout(redrawPastAudits, doneTypingInterval);
            });
        </script>
    ";
}

/************************************
 * FUNCTION: DISPLAY TEST IN DETAIL *
 ************************************/
function display_detail_test()
{
    global $lang, $escaper;
    
    $test_audit_id = (int)$_GET['id'];
    
    // Get test audit information
    $test_audit = get_framework_control_test_audit_by_id($test_audit_id);
    
    // Get attachement files
    $files = get_compliance_files($test_audit_id, "test_audit");

    // Get associated risk ids
    $risk_ids = get_test_result_to_risk_ids($test_audit["result_id"]);
    $tags_view = "";
    if ($test_audit['tags']) {
        foreach(explode(",", $test_audit['tags']) as $tag) {
            $tags_view .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin-right:2px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
        }
    } else {
        $tags_view .= "--";
    }

    echo "
        <div class='well' >
            <table width='100%' id='test_detail_information'>
                <tr>
                    <td width='50%' valign='top'>
                        <table width='100%'>
                            <tr>
                                <td valign='top' class='text-right' width='200px'><strong>".$escaper->escapeHtml($lang['TestName']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml($test_audit['name'])."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['Tester']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml($test_audit['tester_name'])."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['TestFrequency']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".(int)$test_audit['test_frequency']. " " .$escaper->escapeHtml($test_audit['test_frequency'] > 1 ? $lang['days'] : $lang['Day'])."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['Objective']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml($test_audit['objective'] ? $test_audit['objective'] : "--")."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['TestSteps']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml($test_audit['test_steps'] ? $test_audit['test_steps'] : "--")."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['ApproximateTime']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".(int)$test_audit['approximate_time']. " " .$escaper->escapeHtml($test_audit['approximate_time'] > 1 ? $lang['minutes'] : $lang['minute'])."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['Teams']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".($test_audit['teams'] ? $escaper->escapeHtml(get_names_by_multi_values('team', $test_audit['teams'])) : "--")."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['Tags']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$tags_view."
                                </td>
                            </tr>
                        </table>                    
                    </td>
                    <td valign='top'>
                        <table width='100%'>
                            <tr>
                                <td valign='top' class='text-right' width='200px'><strong>".$escaper->escapeHtml($lang['ExpectedResults']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml($test_audit['expected_results'] ? $test_audit['expected_results'] : "--")."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['FrameworkName']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml($test_audit['framework_name'])."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['ControlName']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml($test_audit['control_name'])."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['ControlOwner']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml(get_name_by_value("user", $test_audit['control_owner']))."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['DesiredFrequency']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".(int)$test_audit['desired_frequency']. " " .$escaper->escapeHtml($test_audit['test_frequency'] > 1 ? $lang['days'] : $lang['Day'])."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['CreatedDate']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml(format_date($test_audit['created_at'], "--"))."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['AdditionalStakeholders']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml(get_stakeholder_names($test_audit['additional_stakeholders']))."
                                </td>
                            </tr>
                        </table>                    
                    </td>
                </tr>
            </table>
            <!-- Test Result -->
            <table width='100%' id='test_result_information' style='margin-top: 15px'>
                <tr>
                    <td width='50%' valign='top'>
                        <table width='100%'>
                            <tr>
                                <td valign='top' class='text-right' width='200px'><strong>".$escaper->escapeHtml($lang['TestResult']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml($test_audit['test_result'] ? $test_audit['test_result'] : "--")."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right'><strong>".$escaper->escapeHtml($lang['TestDate']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml(format_date($test_audit['last_date'], "--"))."
                                </td>
                            </tr>

                        </table>                    
                    </td>
                    <td valign='top'>
                        <table width='100%'>
                            <tr>
                                <td valign='top' class='text-right' width='200px'><strong>".$escaper->escapeHtml($lang['Summary']).":&nbsp;&nbsp;</strong></td>
                                <td>
                                    ".$escaper->escapeHtml($test_audit['summary'] ? $test_audit['summary'] : "--")."
                                </td>
                            </tr>
                            <tr>
                                <td valign='top' class='text-right' width='200px'><strong>".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp;</strong></td>
                                <td>
                            ";
                                if($files){
                                    foreach($files as $file){
                                        echo  "
                                            <p>            
                                                <a href=\"".$_SESSION['base_url']."/compliance/download.php?id=".$file['unique_name']."\" >".$escaper->escapeHtml($file['name'])."</a>
                                            </p>
                                        ";
                                    }
                                }
                                else
                                {
                                    echo "<p>No files</p>";
                                }
                            echo "
                                </td>
                            </tr>
                        </table>                    
                    </td>
                </tr>
            </table>

        </div>
        <form id='edit-test' method='POST'>
            <input name='update_associated_risks' value='1' type='hidden'/>
            <input name='associate_new_risk_id' id='associate_new_risk_id' value='' type='hidden'>
            <input name='associate_exist_risk_ids' id='associate_exist_risk_ids' value='".implode(",", $risk_ids)."' type='hidden'>
        </form>

    ";

    // Display the Control Details
    display_test_audit_framework_control($test_audit['framework_control_id']);
    
    // Display associated risks
    display_associated_risks($risk_ids);

    // Display test audit comment
    display_test_audit_comment($test_audit_id);
    
    // Display test audit trail
    display_test_audit_trail($test_audit_id);
}

/*******************************
 * FUNCTION: DELETE TEST AUDIT *
 *******************************/
function delete_test_audit($test_audit_id) {

    // Open the database connection
    $db = db_open();

    // Delete test audit
    $stmt = $db->prepare("DELETE FROM `framework_control_test_audits` WHERE `id`=:test_audit_id;");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete test audit
    $stmt = $db->prepare("DELETE FROM `framework_control_test_comments` WHERE `test_audit_id`=:test_audit_id;");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete test audit
    $stmt = $db->prepare("
        DELETE t1,t2 FROM `framework_control_test_results_to_risks` t1 LEFT JOIN `framework_control_test_results` t2 ON t2.id = t1.test_results_id WHERE t2.`test_audit_id`=:test_audit_id");
    $stmt->bindParam(":test_audit_id", $test_audit_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete test audit's teams
    updateTeamsOfItem($test_audit_id, 'audit', []);

    // Close the database connection
    db_close($db);

    $message = _lang('TestAuditDeleteAuditTrailMessage', array('test_audit_id' => $test_audit_id, 'user' => $_SESSION['user']), false);
    write_log((int)$test_audit_id + 1000, $_SESSION['uid'], $message, "test_audit");

    return true;
}

/*******************************
 * FUNCTION: REOPEN TEST AUDIT *
 *******************************/
function reopen_test_audit($test_audit_id)
{
    // Set test audit status to undefined
    update_test_audit_status($test_audit_id, 0);
    
    $test_audit_name = get_test_audit_name($test_audit_id);
    $message = _lang('AuditLog_TestAuditReopen', ['test_audit_name' => $test_audit_name, 'test_audit_id' => $test_audit_id, 'user_name' => $_SESSION['user']], false);
    write_log((int)$test_audit_id + 1000, $_SESSION['uid'], $message, "test_audit");

    return true;
}

/******************************************************
 * FUNCTION: GET FRAMEWORKS FROM INITIATE AUDITS PAGE *
 ******************************************************/
function get_initiate_frameworks_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control)
{
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t1.*, 
            GROUP_CONCAT(DISTINCT t2.short_name SEPARATOR ',') control_names,
            GROUP_CONCAT(DISTINCT t2.desired_frequency SEPARATOR ',') control_desired_frequencies,
            GROUP_CONCAT(DISTINCT t2.last_audit_date SEPARATOR ',') control_last_audit_dates,
            GROUP_CONCAT(DISTINCT t2.next_audit_date SEPARATOR ',') control_next_audit_dates,
            GROUP_CONCAT(DISTINCT t3.name SEPARATOR ',') test_names,
            GROUP_CONCAT(DISTINCT t3.test_frequency SEPARATOR ',') test_test_frequencies,
            GROUP_CONCAT(DISTINCT t3.last_date SEPARATOR ',') test_last_audit_dates,
            GROUP_CONCAT(DISTINCT t3.next_date SEPARATOR ',') test_next_audit_dates
        FROM `frameworks` t1 
            LEFT JOIN `framework_control_mappings` m on t1.value=m.framework
            LEFT JOIN `framework_controls` t2 on m.control_id=t2.id AND t2.deleted=0
            LEFT JOIN `framework_control_tests` t3 on t3.framework_control_id=t2.id
        WHERE
            t1.status=1 AND t3.id IS NOT NULL
    ";
    
    $where = [];
    
    if($filter_by_frequency){
        $where[] = "(t1.desired_frequency = :filter_by_frequency OR t2.desired_frequency = :filter_by_frequency OR t3.test_frequency = :filter_by_frequency)";
    }
    if($filter_by_status){
        
    }
//    if($filter_by_framework){
//        $where[] = "FIND_IN_SET(t1.value, :filter_by_framework)";
//    }else{
//        $where[] = "0";
//    }
    if($filter_by_control){
        $where[] = "t2.short_name like :filter_by_control";
    }

    if($where){
        $sql .= " AND ". implode(" AND ", $where);
    }
    
    $sql .= " GROUP BY t1.value ";

    $stmt = $db->prepare($sql);
    
    if($filter_by_frequency){
        $stmt->bindParam(":filter_by_frequency", $filter_by_frequency, PDO::PARAM_STR);
    }
    if($filter_by_status){
        
    }
//    if($filter_by_framework){
//        $framework_ids = implode(",", $filter_by_framework);
//        $stmt->bindParam(":filter_by_framework", $framework_ids, PDO::PARAM_STR);
//    }
    if($filter_by_control){
        $filter_by_control = "%{$filter_by_control}%";
        $stmt->bindParam(":filter_by_control", $filter_by_control, PDO::PARAM_STR);
    }

    $stmt->execute();
    // Store the list in the array
    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Close the database connection
    db_close($db);
    $filtered_frameworks = [];

    $all_frameworks = get_frameworks(1);
    foreach($frameworks as $framework){
//        $framework['name'] = try_decrypt($framework['name']);
//        if(!$filter_by_text || stripos($framework['name'], $filter_by_text) !== false 
        if(!$filter_by_text 
            || stripos($framework['desired_frequency'], $filter_by_text) !== false 
            || stripos($framework['last_audit_date'], $filter_by_text) !== false 
            || stripos($framework['next_audit_date'], $filter_by_text) !== false 
            
            || stripos($framework['control_names'], $filter_by_text) !== false 
            || stripos($framework['control_desired_frequencies'], $filter_by_text) !== false 
            || stripos($framework['control_last_audit_dates'], $filter_by_text) !== false 
            || stripos($framework['control_next_audit_dates'], $filter_by_text) !== false 
            
            || stripos($framework['test_names'], $filter_by_text) !== false 
            || stripos($framework['test_test_frequencies'], $filter_by_text) !== false 
            || stripos($framework['test_last_audit_dates'], $filter_by_text) !== false 
            || stripos($framework['test_next_audit_dates'], $filter_by_text) !== false 
        ){
            $filtered = true;
//            $filtered_frameworks[] = $framework;
        }
        else{
            $filtered = false;
        }
        

        $parent_frameworks = array();
        get_parent_frameworks($all_frameworks, $framework['value'], $parent_frameworks);
        foreach($parent_frameworks as $parent_framework){
            if($filtered || stripos($parent_framework['name'], $filter_by_text) !== false ){
                $filtered_frameworks[] = $parent_framework;
            }
        }
        
    }
    
    
    $results = array();
    $ids = array();
    // Get unique array
    foreach($filtered_frameworks as $filtered_framework){
        if(!in_array($filtered_framework['value'], $ids) && in_array($filtered_framework['value'], $filter_by_framework))
        {
            $results[] = $filtered_framework;
            $ids[] = $filtered_framework['value'];
        }
    }
    
    return $results;

}

/****************************************************
 * FUNCTION: GET CONTROLS FROM INITIATE AUDITS PAGE *
 ****************************************************/
function get_initiate_controls_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control, $framework_id=null)
{
    global $escaper;
    $current_framework = get_framework($framework_id);

    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t2.*,
            :framework_name framework_name,
            :framework_desired_frequency framework_desired_frequency,
            :framework_last_audit_date framework_last_audit_date,
            :framework_next_audit_date framework_next_audit_date,
            GROUP_CONCAT(DISTINCT t2.short_name SEPARATOR ',') control_names,
            GROUP_CONCAT(DISTINCT t2.desired_frequency SEPARATOR ',') control_desired_frequencies,
            GROUP_CONCAT(DISTINCT t2.last_audit_date SEPARATOR ',') control_last_audit_dates,
            GROUP_CONCAT(DISTINCT t2.next_audit_date SEPARATOR ',') control_next_audit_dates,
            GROUP_CONCAT(DISTINCT t3.name SEPARATOR ',') test_names,
            GROUP_CONCAT(DISTINCT t3.test_frequency SEPARATOR ',') test_test_frequencies,
            GROUP_CONCAT(DISTINCT t3.last_date SEPARATOR ',') test_last_audit_dates,
            GROUP_CONCAT(DISTINCT t3.next_date SEPARATOR ',') test_next_audit_dates
        FROM `frameworks` t1 
            INNER JOIN `framework_control_mappings` m on t1.value=m.framework
            INNER JOIN `framework_controls` t2 on m.control_id=t2.id AND t2.deleted=0
            LEFT JOIN `framework_control_tests` t3 on t3.framework_control_id=t2.id
        WHERE
            t1.status=1 AND t3.id IS NOT NULL
    ";
    
    $where = [];
    
    if($filter_by_frequency){
        $where[] = "(t1.desired_frequency = :filter_by_frequency OR t2.desired_frequency = :filter_by_frequency OR t3.test_frequency = :filter_by_frequency)";
    }
    if($filter_by_status){
        
    }
    if($filter_by_framework){
        $where[] = "FIND_IN_SET(t1.value, :filter_by_framework)";
    }else{
        $where[] = "0";
    }
    if($filter_by_control){
        $where[] = "t2.short_name like :filter_by_control";
    }
    
    if($framework_id){
        $child_frameworks = get_all_child_frameworks($framework_id, 1, false);
        
        $selected_framework_ids = array_map(function($row){
            return $row['value'];
        }, $child_frameworks);
        
        array_push($selected_framework_ids, $framework_id);
        $selected_framework_ids = implode(",", $selected_framework_ids);
        
        $where[] = "FIND_IN_SET(t1.value, :selected_framework_ids)";
    }

    if($where){
        $sql .= " AND ". implode(" AND ", $where);
    }
    
    $sql .= " GROUP BY t2.id ";

    $stmt = $db->prepare($sql);

    $stmt->bindParam(":framework_name", $current_framework['name'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_desired_frequency", $current_framework['desired_frequency'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_last_audit_date", $current_framework['last_audit_date'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_next_audit_date", $current_framework['next_audit_date'], PDO::PARAM_STR);

    //    if($filter_by_text){
//        $filter_by_text = "%{$filter_by_text}%";
//        $stmt->bindParam(":filter_by_text", $filter_by_text, PDO::PARAM_STR);
//    }
    if($filter_by_frequency){
        $stmt->bindParam(":filter_by_frequency", $filter_by_frequency, PDO::PARAM_STR);
    }
    if($filter_by_status){
        
    }
    if($filter_by_framework){
        $framework_ids = implode(",", $filter_by_framework);
        $stmt->bindParam(":filter_by_framework", $framework_ids, PDO::PARAM_STR);
    }
    if($filter_by_control){
        $filter_by_control = "%{$filter_by_control}%";
        $stmt->bindParam(":filter_by_control", $filter_by_control, PDO::PARAM_STR);
    }
    if($framework_id){
        $stmt->bindParam(":selected_framework_ids", $selected_framework_ids, PDO::PARAM_STR);
    }

    $stmt->execute();
    // Store the list in the array
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    $filtered_controls = [];
    foreach($controls as $control){
        if(!$filter_by_text || stripos($current_framework['name'], $filter_by_text) !== false 
            || stripos($control['framework_desired_frequency'], $filter_by_text) !== false 
            || stripos($control['framework_last_audit_date'], $filter_by_text) !== false 
            || stripos($control['framework_next_audit_date'], $filter_by_text) !== false 
            
            || stripos($control['control_names'], $filter_by_text) !== false 
            || stripos($control['control_desired_frequencies'], $filter_by_text) !== false 
            || stripos($control['control_last_audit_dates'], $filter_by_text) !== false 
            || stripos($control['control_next_audit_dates'], $filter_by_text) !== false 
            
            || stripos($control['test_names'], $filter_by_text) !== false 
            || stripos($control['test_test_frequencies'], $filter_by_text) !== false 
            || stripos($control['test_last_audit_dates'], $filter_by_text) !== false 
            || stripos($control['test_next_audit_dates'], $filter_by_text) !== false 
        ){
            $filtered_controls[] = $control;
        }
    }
    
    return $filtered_controls;
}

/*************************************************
 * FUNCTION: GET TESTS FROM INITIATE AUDITS PAGE *
 *************************************************/
function get_initiate_tests_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control, $framework_id, $control_id)
{
    $current_framework = get_framework($framework_id);

    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t3.*,
            :framework_name framework_name,
            :framework_desired_frequency framework_desired_frequency,
            :framework_last_audit_date framework_last_audit_date,
            :framework_next_audit_date framework_next_audit_date,
            GROUP_CONCAT(DISTINCT t2.short_name SEPARATOR ',') control_names,
            GROUP_CONCAT(DISTINCT t2.desired_frequency SEPARATOR ',') control_desired_frequencies,
            GROUP_CONCAT(DISTINCT t2.last_audit_date SEPARATOR ',') control_last_audit_dates,
            GROUP_CONCAT(DISTINCT t2.next_audit_date SEPARATOR ',') control_next_audit_dates,
            GROUP_CONCAT(DISTINCT t3.name SEPARATOR ',') test_names,
            GROUP_CONCAT(DISTINCT t3.test_frequency SEPARATOR ',') test_test_frequencies,
            GROUP_CONCAT(DISTINCT t3.last_date SEPARATOR ',') test_last_audit_dates,
            GROUP_CONCAT(DISTINCT t3.next_date SEPARATOR ',') test_next_audit_dates
        FROM `frameworks` t1 
            INNER JOIN `framework_control_mappings` m on t1.value=m.framework
            INNER JOIN `framework_controls` t2 on m.control_id=t2.id AND t2.deleted=0
            INNER JOIN `framework_control_tests` t3 on t3.framework_control_id=t2.id
        WHERE
            t1.status=1 AND t2.id=:control_id
    ";

    $where = [];
    
    if($filter_by_frequency){
        $where[] = "(t1.desired_frequency = :filter_by_frequency OR t2.desired_frequency = :filter_by_frequency OR t3.test_frequency = :filter_by_frequency)";
    }
    if($filter_by_status){
        
    }
    if($filter_by_framework){
        $where[] = "FIND_IN_SET(t1.value, :filter_by_framework)";
    }else{
        $where[] = "0";
    }
    if($filter_by_control){
        $where[] = "t2.short_name like :filter_by_control";
    }
    
    if($where){
        $sql .= " AND ". implode(" AND ", $where);
    }
    
    $sql .= " GROUP BY t3.id ";

    $stmt = $db->prepare($sql);

    $stmt->bindParam(":framework_name", $current_framework['name'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_desired_frequency", $current_framework['desired_frequency'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_last_audit_date", $current_framework['last_audit_date'], PDO::PARAM_STR);
    $stmt->bindParam(":framework_next_audit_date", $current_framework['next_audit_date'], PDO::PARAM_STR);

    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    if($filter_by_frequency){
        $stmt->bindParam(":filter_by_frequency", $filter_by_frequency, PDO::PARAM_STR);
    }
    if($filter_by_status){
        
    }
    if($filter_by_framework){
        $framework_ids = implode(",", $filter_by_framework);
        $stmt->bindParam(":filter_by_framework", $framework_ids, PDO::PARAM_STR);
    }
    if($filter_by_control){
        $filter_by_control = "%{$filter_by_control}%";
        $stmt->bindParam(":filter_by_control", $filter_by_control, PDO::PARAM_STR);
    }

    $stmt->execute();
    // Store the list in the array
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    $filtered_tests = [];
    foreach($tests as $test){
        if(!$filter_by_text || stripos($current_framework['name'], $filter_by_text) !== false 
            || stripos($test['framework_desired_frequency'], $filter_by_text) !== false 
            || stripos($test['framework_last_audit_date'], $filter_by_text) !== false 
            || stripos($test['framework_next_audit_date'], $filter_by_text) !== false 
            
            || stripos($test['control_names'], $filter_by_text) !== false 
            || stripos($test['control_desired_frequencies'], $filter_by_text) !== false 
            || stripos($test['control_last_audit_dates'], $filter_by_text) !== false 
            || stripos($test['control_next_audit_dates'], $filter_by_text) !== false 
            
            || stripos($test['test_names'], $filter_by_text) !== false 
            || stripos($test['test_test_frequencies'], $filter_by_text) !== false 
            || stripos($test['test_last_audit_dates'], $filter_by_text) !== false 
            || stripos($test['test_next_audit_dates'], $filter_by_text) !== false 
        ){
            $filtered_tests[] = $test;
        }
    }
    
    return $filtered_tests;
}

/****************************************
 * FUNCTION: GET AUDIT TESTS            *
 * DEFAULT SORT: SOONEST NEXT TEST DATE *
 ****************************************/
function get_audit_tests($order_field=false, $order_dir=false)
{
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t1.id, t1.name, t1.last_date, t1.next_date, IFNULL(GROUP_CONCAT(DISTINCT t3.name), '') framework_names,
        (SELECT tr.test_result
        FROM `framework_control_test_audits` ta
        LEFT JOIN `framework_control_test_results` tr ON ta.id=tr.test_audit_id 
        WHERE  ta.test_id = t1.id 
        ORDER BY tr.last_updated DESC LIMIT 1 ) last_test_result 
        FROM `framework_control_tests` t1
            INNER JOIN `framework_controls` t2 ON t1.framework_control_id=t2.id
            LEFT JOIN `framework_control_mappings` m ON t2.id=m.control_id
            LEFT JOIN `frameworks` t3 ON m.framework=t3.value
        WHERE t3.status=1
        GROUP BY t1.id
    ";
    
    switch($order_field)
    {
        case "test_name";
            $sql .= " ORDER BY t1.name {$order_dir} ";
        break;
        case "associated_frameworks";
            // If encryption extra is disabled, sort by query
            if(!encryption_extra())
            {
                $sql .= " ORDER BY framework_names {$order_dir} ";
            }
        break;
        case "last_test_date";
            $sql .= " ORDER BY t1.last_date {$order_dir} ";
        break;
        case "next_test_date";
            $sql .= " ORDER BY t1.next_date {$order_dir} ";
        break;
        case "last_test_result";
            $sql .= " ORDER BY last_test_result {$order_dir} ";
        break;
    }
    $sql .= ";";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();

    $tests = $stmt->fetchAll();

    // closed the database connection
    db_close($db);
    
    // If team separation is enabled
    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        
        if (!should_skip_test_and_audit_permission_check()) {
            $tests = array_filter($tests, function ($test) {
                return is_user_allowed_to_access($_SESSION['uid'], $test['id'], 'test');
            });
        }
    }

    if(encryption_extra())
    {
        // Decrypt associtated framework names
        foreach($tests as &$row){
            $framework_names = explode(",", $row['framework_names']);
            $decrypted_framework_names = [];
            foreach($framework_names as $framework_name)
            {
                if($framework_name){
                    $decrypted_framework_names[] = try_decrypt(trim($framework_name));
                }
            }
            $row['framework_names'] = implode(", ", $decrypted_framework_names);
        }
        
        // If encryption extra is enabled and sort field is Associated Frameworks, sort by manually
        if($order_field == "associated_frameworks")
        {
            usort($tests, function($a, $b) use ($order_dir)
                {
                    $aValue = trim($a['framework_names']);
                    $bValue = trim($b['framework_names']);
                    
                    if($order_dir == 'asc'){
                        return strcasecmp($aValue, $bValue);
                    }else{
                        return strcasecmp($bValue, $aValue);
                    }
                }
            );
        }
    }
    
    return $tests;
}

/****************************************
 * FUNCTION: INSERT TEST RESULT TO RISK *
 ***************************************/
function save_test_result_to_risk($result_id, $risk_id) {

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("INSERT INTO framework_control_test_results_to_risks (`test_results_id`, `risk_id`) VALUES(:test_results_id, :risk_id);");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    return true;
}
/****************************************
 * FUNCTION: DELETE TEST RESULT TO RISK *
 ***************************************/
function delete_test_result_to_risk($result_id, $risk_id) {

    // Open the database connection
    $db = db_open();

    // delete existing risk association
    $stmt = $db->prepare("DELETE FROM framework_control_test_results_to_risks WHERE `test_results_id` = :test_results_id AND `risk_id` = :risk_id;");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    return true;
}
/*****************************************************
 * FUNCTION: DELETE TEST RESULT TO RISK BY RESULT ID *
 *****************************************************/
function delete_test_result_to_risk_by_result_id($result_id) {

    // Open the database connection
    $db = db_open();

    // delete existing risk association
    $stmt = $db->prepare("DELETE FROM framework_control_test_results_to_risks WHERE `test_results_id` = :test_results_id;");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    return true;
}
/*****************************************
 * FUNCTION: GET TEST RESULT TO RISK IDs *
 *****************************************/
function get_test_result_to_risk_ids($result_id) {

    // Open the database connection
    $db = db_open();

    // delete existing risk association
    $stmt = $db->prepare("SELECT * FROM framework_control_test_results_to_risks WHERE `test_results_id` = :test_results_id");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    $risk_ids = [];
    foreach($results as $row){
        $risk_ids[] = $row["risk_id"];
    }

    return $risk_ids;
}

/*******************************************
 * FUNCTION: CLOSE RISKS BY TEST RESULT ID *
 *******************************************/
function close_risks_by_test_result_id($result_id, $test_result) {

    // Open the database connection
    $db = db_open();

    // delete existing risk association
    $stmt = $db->prepare("SELECT * FROM framework_control_test_results_to_risks WHERE `test_results_id` = :test_results_id");
    $stmt->bindParam(":test_results_id", $result_id, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    foreach($results as $row){
        $id = (int)$row['risk_id'] + 1000;
        $status = "Closed";
        $close_reason = 1;
        $note = "Risk was closed when the \"" . $result_id . "\" test was marked as \"" . $test_result . "\".";

        // Close the risk
        close_risk($id, $_SESSION['uid'], $status, $close_reason, $note);
    }

    return true;
}
?>
