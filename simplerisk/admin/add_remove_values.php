<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(permissions: ['check_admin' => true]);

$customAddFunction_team = function($name) {
    // Insert a new team
    $teamId = add_name("team", $name);

    // Set all teams to admistrator users
    set_all_teams_to_administrators();

    // If the Organizational Hierarchy extra is turned on
    // the new teams should be assigned to the default business unit
    if (organizational_hierarchy_extra()) {
        // Include the Organizational Hierarchy Extra
        require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));

        assign_teams_to_default_business_unit();
    }
    
    return $teamId;
};

$customDeleteFunction_team = function($value) {
    // If team separation is enabled
    if (team_separation_extra()) {
        // Check if a risk is assigned to the team
        $delete = empty(get_risks_by_team($value));
    } else {
        $delete = true;
    }

    // If it is ok to delete the team
    if ($delete) {
        $delete_result = delete_value("team", $value);
        cleanup_after_delete("team");
        return $delete_result;
    } else {
        global $lang;
        // Display an alert
        set_alert(true, "bad", $lang['CantDeleteTeamItsInUseByARisk']);
        return false;
    }
};

$customDeleteFunction_technology = function($value) {
    $delete_result = delete_value("technology", $value);
    cleanup_after_delete("technology");
    return $delete_result;
};

$customDeleteFunction_location = function($value) {
    $delete_result = delete_value("location", $value);
    cleanup_after_delete("location");
    return $delete_result;
};

$customDeleteFunction_test_status = function($id) {

    $closed_audit_status = get_setting("closed_audit_status");
    // If Closed status
    if($id == $closed_audit_status) {
        global $lang;
        // Display an alert
        set_alert(true, "bad", $lang['TheClosedStatusCantBeDeleted']);
        return false;
    }
    // If status is not Closed
    else {
        return delete_value("test_status", $id);
    }
};

$customDeleteFunction_risk_grouping = function($value) {

    $db = db_open();

    // Get value of the default risk group
    $stmt = $db->prepare("SELECT `value` FROM `risk_grouping` WHERE `default` = 1");
    $stmt->execute();

    $default_risk_group_value = (int)$stmt->fetchColumn();

    db_close($db);

    if ($value === $default_risk_group_value) {
        global $lang;
        // Display an alert
        set_alert(true, "bad", $lang['CantDeleteTheDefaultRiskGrouping']);
        return false;
    }

    $delete_result = delete_value("risk_grouping", $value);
    cleanup_after_delete("risk_grouping");
    reassign_groupless_risk_catalogs($default_risk_group_value);

    return $delete_result;
};


$customAddFunction_threat_grouping = function($name) {
    $db = db_open();

    // Get the order for the last place...
    $stmt = $db->prepare("SELECT MAX(`order`) FROM `threat_grouping`");
    $stmt->execute();
    $max_order = $stmt->fetchColumn();
    $new_order = intval($max_order) + 1;

    // ...and add it there.
    $stmt = $db->prepare("INSERT INTO `threat_grouping` (`name`, `order`) VALUES (:name, :order)");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":order", $new_order, PDO::PARAM_INT);
    $stmt->execute();
    $threat_grouping_id = $db->lastInsertId();

    db_close($db);

    return $threat_grouping_id;
};

$customDeleteFunction_threat_grouping = function($value) {
    $db = db_open();

    // Get value of the default threat group
    $stmt = $db->prepare("SELECT `value` FROM `threat_grouping` WHERE `default` = 1");
    $stmt->execute();

    $default_threat_group_value = (int)$stmt->fetchColumn();

    db_close($db);

    if ($value === $default_threat_group_value) {
        global $lang;
        // Display an alert
        set_alert(true, "bad", $lang['CantDeleteTheDefaultThreatGrouping']);
        return false;
    }

    $delete_result = delete_value("threat_grouping", $value);
    cleanup_after_delete("threat_grouping");
    reassign_groupless_threat_catalogs($default_threat_group_value);

    return $delete_result;
};

// The configuration the page rendering is based on
// for custom functions you can either use anonymous functions(see examples defined above)
// or existing functions(the name should be passed as a string).
$tableConfig = array(
    'review' => array(
        'headerKey' => 'Review',
        'lengthLimit' => 50,
    ),
    'next_step' => array(
        'headerKey' => 'NextStep',
        'lengthLimit' => 50,
    ),
    'category' => array(
        'headerKey' => 'Category',
        'lengthLimit' => 50,
    ),
    'team' => array(
        'headerKey' => 'Team',
        'lengthLimit' => 50,
        'customAddFunction' => $customAddFunction_team,
        'customDeleteFunction' => $customDeleteFunction_team,
    ),
    'technology' => array(
        'headerKey' => 'Technology',
        'lengthLimit' => 50,
        'customDeleteFunction' => $customDeleteFunction_technology,
    ),
    'location' => array(
        'headerKey' => 'SiteLocation',
        'lengthLimit' => 100,
        'customDeleteFunction' => $customDeleteFunction_location,
    ),
    'planning_strategy' => array(
        'headerKey' => 'RiskPlanningStrategy',
        'lengthLimit' => 20,
    ),
    'close_reason' => array(
        'headerKey' => 'CloseReason',
        'lengthLimit' => 50,
    ),
    'status' => array(
        'headerKey' => 'Status',
        'lengthLimit' => 50,
    ),
    'source' => array(
        'headerKey' => 'RiskSource',
        'lengthLimit' => 50,
    ),
    'control_class' => array(
        'headerKey' => 'ControlClass',
        'lengthLimit' => 20, //can be more
    ),
    'control_phase' => array(
        'headerKey' => 'ControlPhase',
        'lengthLimit' => 200, //can be more
    ),
    'control_priority' => array(
        'headerKey' => 'ControlPriority',
        'lengthLimit' => 20, //can be more
    ),
    'family' => array(
        'headerKey' => 'ControlFamily',
        'lengthLimit' => 100,
        'customAddFunction' => 'add_family',
        'customUpdateFunction' => 'update_family',
        'customDeleteFunction' => 'delete_family',
    ),
    'test_status' => array(
        'headerKey' => 'AuditStatus',
        'lengthLimit' => 100,
        'customDeleteFunction' => $customDeleteFunction_test_status,
    ),
    'risk_grouping' => array(
        'headerKey' => 'RiskGroupings',
        'lengthLimit' => 50,
        'customAddFunction' => 'add_risk_grouping',
        'customDeleteFunction' => $customDeleteFunction_risk_grouping,
    ),
    'risk_function' => array(
        'headerKey' => 'RiskFunctions',
        'lengthLimit' => 50,
    ),
    'threat_grouping' => array(
            'headerKey' => 'ThreatGroupings',
            'lengthLimit' => 50,
            'customAddFunction' => $customAddFunction_threat_grouping,
            'customDeleteFunction' => $customDeleteFunction_threat_grouping,
    ),
    'document_status' => array(
        'headerKey' => 'DocumentStatus',
        'lengthLimit' => 100,
    ),
    'document_exceptions_status' => array(
        'headerKey' => 'ExceptionStatus',
        'lengthLimit' => 100,
    ),
);

if (isset($_POST['add_remove_values_input']) && ($_POST['add_remove_values_input'] == "add_remove_values")) {

    if (in_array($_POST['action'], array('add', 'update', 'delete'))
    && array_key_exists($_POST['table_name'], $tableConfig)) {
        $tableName = $_POST['table_name'];

        if (in_array($_POST['action'], array('add', 'update'))) {
            if (!isset($_POST['name']) || !trim($_POST['name'])) {
                set_alert(true, "bad", $lang['YouNeedToSpecifyANameParameter']);
            } else {
                $name = trim($_POST['name']);

                $lengthLimit = $tableConfig[$tableName]['lengthLimit'];
                // Size check
                if (strlen($name) > $lengthLimit) {
                    // As we render the UI controls with the same limits we shouldn't see this message
                    set_alert(true, "bad", _lang('TheEnteredValueIsTooLong', ['limit' => $lengthLimit]));
                }
            }
        }

        if (in_array($_POST['action'], array('update', 'delete'))) {
            if (!isset($_POST['id']) || !preg_match('/^\d+$/', trim($_POST['id']))) {
                set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);
            } else {
                $id = (int)trim($_POST['id']);
            }
        }

        switch ($_POST['action']) {
            case "add":
                // If the custom add function is set
                if (array_key_exists('customAddFunction', $tableConfig[$tableName])) {
                    // Call it with the required parameters
                    $result = $tableConfig[$tableName]['customAddFunction']($name);
                } else {
                    // Insert a new item
                    $result = add_name($tableName, $name);
                }

                // Display an alert
                if ($result)
                    set_alert(true, "good", $lang['ANewItemWasAddedSuccessfully']);
                else
                    set_alert(true, "bad", $lang['FailedToAddNewItem']);

                break;

            case "update":
                // If the custom update function is set
                if (array_key_exists('customUpdateFunction', $tableConfig[$tableName])) {
                    // Call it with the required parameters
                    $result = $tableConfig[$tableName]['customUpdateFunction']($id, $name);
                } else {
                    $result = update_table($tableName, $name, $id);
                }

                // Display an alert
                if ($result)
                    set_alert(true, "good", $lang['AnItemWasUpdatedSuccessfully']);
                else
                    set_alert(true, "bad", $lang['FailedToUpdateItem']);

                break;

            case "delete":
                // If the custom delete function is set
                if (array_key_exists('customDeleteFunction', $tableConfig[$tableName])) {
                    // Call it with the required parameters
                    $result = $tableConfig[$tableName]['customDeleteFunction']($id);
                } else {
                    $result = delete_value($tableName, $id);
                }

                // Display an alert
                if ($result)
                    set_alert(true, "good", $lang['AnItemWasDeletedSuccessfully']);
                else
                    set_alert(true, "bad", $lang['FailedToDeleteItem']);

                break;
        }
    } else {
        // Didn't want to put anything informative here as this message will only be
        // seen if someone is calling this with forged data
        set_alert(true, "bad", $lang['MissingConfiguration']);
    }
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <form action="" name="add_remove_values" method="post">
                <input type="hidden" value="add_remove_values" name="add_remove_values_input"/>
                <input type="hidden" value="" name="table_name"/>
                <input type="hidden" value="" name="action"/>
                <input type="hidden" value="" name="id"/>
                <input type="hidden" value="" name="name"/>
                <div class="row" >
                    <div class="col-md-3 form-group pb-4">
                        <select id="table-sections" class="form-select" name="table-sections">
    <?php
        foreach($tableConfig as $table => $config){
            echo "
                            <option value='" . $table . "'" . ((isset($_POST['table-sections']) && $_POST['table-sections'] == $table) ? " selected" : "") . ">" . $escaper->escapeHtml($lang[$config['headerKey']]) . "</option>
            ";
        }
    ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div id="crud-wrapper" class="col-md-12">
    <?php

        $text_change = $escaper->escapeHtml($lang['Change']);
        $text_to = $escaper->escapeHtml($lang['to']);
        $text_update = $escaper->escapeHtml($lang['Update']);
        $text_add = $escaper->escapeHtml($lang['Add']);
        $text_delete = $escaper->escapeHtml($lang['Delete']);
        $text_deleteItem = $escaper->escapeHtml($lang['DeleteItemNamed']);
        $text_addItem = $escaper->escapeHtml($lang['AddNewItemNamed']);

        foreach ($tableConfig as $table => $config) {
            if (isset($_POST['table-sections'])) {
                if ($_POST['table-sections'] == $table) {
                    $display = "display: block;";
                } else {
                    $display = "display: none;";
                }
            } else {
                if($table == "review") {
                    $display = "display: block;";
                } else {
                    $display = "display: none;";
                }
            }
            echo '
                        <div class="row">
                            <div class="hero-unit" data-table_name="' . $table . '" style="' . $display . '">
                                <h4>' . $escaper->escapeHtml($lang[$config['headerKey']]) . '</h4>
                                <div class="row" style="align-items:flex-end">
                                    <div class="col-md-6 form-group">
                                        <label>' . $text_addItem . ':</label>
                                        <input id="' . $table . '_new" type="text" maxlength="' . $config['lengthLimit'] .'" size="20" class="form-control"/>
                                    </div>
                                    <div class="col-md-1 form-group">
                                        <input type="submit" value="' .  $text_add . ' " data-action="add" class="btn btn-submit form-control"/>
                                    </div>
                                </div>
                                <div class="row" style="align-items:flex-end">
                                    <div class="col-md-3 form-group">
                                        <label>' . $text_change . ':</label>
            ';
                                        create_dropdown($table, NULL, $table . "_update_from");
            echo'
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>' . $text_to . ':</label>
                                        <input id="' . $table . '_update_to" type="text" maxlength="' . $config['lengthLimit'] . '" size="20"  class="form-control"/>
                                    </div>
                                    <div class="col-md-1 form-group">
                                        <input type="submit" value="' . $text_update . '" data-action="update"  class="btn btn-submit form-control"/>
                                    </div>
                                </div>
                                <div class="row" style="align-items:flex-end">
                                    <div class="col-md-6">
                                        <label>' . $text_deleteItem . ':</label>
            ';
                                        create_dropdown($table, NULL, $table . "_delete");
            echo '
                                    </div>
                                    <div class="col-md-1">
                                        <input type="submit" value="' . $text_delete . '" data-action="delete" class="btn btn-submit form-control"/>
                                    </div>
                                </div>
                            </div>
                        </div>
            ';
        }
    ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function refreshDropdown(dropdown, data) {
        dropdown.empty();
        dropdown.append($('<option>', {
            value: 0,
            text : '--'
        }));
        $.each(data, function (i, item) {
            dropdown.append($('<option>', {
                value: item.value,
                text : item.name
            }));
        });
    }

    function crudAction() {
        event.preventDefault();
        var div = $(this).closest('.hero-unit');
        if (div) {
            let tableName = div.data('table_name');
            let action = $(this).data('action');
            $('[name="table_name"]').val(tableName);
            $('[name="action"]').val(action);

            switch(action) {
                case 'add':
                    $('[name="name"]').val(div.find('#' + tableName + '_new').val());
                    break;
                case 'update':
                    $('[name="id"]').val(div.find('#' + tableName + '_update_from').val());
                    $('[name="name"]').val(div.find('#' + tableName + '_update_to').val());
                    break;
                case 'delete':
                    $('[name="id"]').val(div.find('#' + tableName + '_delete').val());
                    break;
            }
            if (tableName && action) {
                $('[name="add_remove_values"]').submit();
            }
        }
    }

    $(document).ready(function() {
        $('#crud-wrapper input[type="submit"]').click(crudAction);
        
        $('#table-sections').change(function(){
            $('#crud-wrapper .hero-unit').fadeOut(100);
            $('#crud-wrapper [data-table_name="'+$(this).val()+'"]').fadeIn(1000);
        })
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>