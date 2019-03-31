<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));
	require_once(realpath(__DIR__ . '/../includes/alerts.php'));

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

	// Check if access is authorized
	if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
	{
		header("Location: ../index.php");
		exit(0);
	}

    // Include the CSRF-magic library
    // Make sure it's called after the session is properly setup
    include_csrf_magic();

    $customAddFunction_team = function($name) {
        // Insert a new team
        $teamId = add_name("team", $name);

        // Set all teams to admistrator users
        set_all_teams_to_administrators();

        return $teamId;
    };

    $customDeleteFunction_team = function($name) {
        // If team separation is enabled
        if (team_separation_extra())
        {
            // Check if a risk is assigned to the team
            $delete = empty(get_risks_by_team($name));
        }
        else
        {
            $delete = true;
        }

        // If it is ok to delete the team
        if ($delete)
        {
            return delete_value("team", $name);
        }
        else
        {
            global $lang;
            // Display an alert
            set_alert(true, "bad", $lang['CantDeleteTeamItsInUseByARisk']);
            return false;
        }
    };

    $customDeleteFunction_test_status = function($id) {

        $closed_audit_status = get_setting("closed_audit_status");
        // If Closed status
        if($id == $closed_audit_status)
        {
            global $lang;
            // Display an alert
            set_alert(true, "bad", $lang['TheClosedStatusCantBeDeleted']);
            return false;
        }
        // If status is not Closed
        else
        {
            return delete_value("test_status", $id);
        }
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
        ),
        'location' => array(
            'headerKey' => 'SiteLocation',
            'lengthLimit' => 100,
        ),
        'regulation' => array(
            'headerKey' => 'ControlRegulation',
            'lengthLimit' => 50,
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
    );

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

        if (in_array($_POST['action'], array('add', 'update', 'delete'))
        && array_key_exists($_POST['table_name'], $tableConfig)) {
            $tableName = $_POST['table_name'];

            if (in_array($_POST['action'], array('add', 'update'))) {
                if (!isset($_POST['name']) || !trim($_POST['name'])) {
                    set_alert(true, "bad", $lang['YouNeedToSpecifyANameParameter']);
                    json_response(400, get_alert(true), NULL);
                } else {
                    $name = trim($_POST['name']);

                    $lengthLimit = $tableConfig[$tableName]['lengthLimit'];
                    // Size check
                    if (strlen($name) > $lengthLimit) {
                        // As we render the UI controls with the same limits we shouldn't see this message
                        set_alert(true, "bad", _lang('TheEnteredValueIsTooLong', ['limit' => $lengthLimit]));
                        json_response(400, get_alert(true), NULL);
                    }
                }
            }

            if (in_array($_POST['action'], array('update', 'delete'))) {
                if (!isset($_POST['id']) || !trim($_POST['id']) || !preg_match('/^\d+$/', trim($_POST['id']))) {
                    set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);
                    json_response(400, get_alert(true), NULL);
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

            // Return a JSON response
            json_response(200, get_alert(true), get_options_from_table($tableName));
            return;
        } else {
            // Didn't want to put anything informative here as this message will only be
            // seen if someone is calling this with forged data
            set_alert(true, "bad", $lang['MissingConfiguration']);

            // Return a JSON response
            json_response(400, get_alert(true), NULL);
            return;
        }
    }

?>

<!doctype html>
<html>

    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
        <script src="../js/jquery.min.js"></script>
        <script src="../js/bootstrap.min.js"></script>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/bootstrap.css">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css">

        <link rel="stylesheet" href="../css/divshot-util.css">
        <link rel="stylesheet" href="../css/divshot-canvas.css">
        <link rel="stylesheet" href="../css/display.css">

        <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="../css/theme.css">
        <?php
            setup_alert_requirements("..");
        ?>
    </head>

    <body>

        <?php
            view_top_menu("Configure");

            // Get any alert messages
            get_alert();
        ?>
        <div class="container-fluid">
            <div class="row-fluid">
                <div class="span3">
                  <?php view_configure_menu("AddAndRemoveValues"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <b><?php echo $escaper->escapeHtml($lang['Select']) ?>: </b>
                        <select id="table-sections">
                            <?php
                                foreach($tableConfig as $table => $config){
                                    echo "<option value='".$table."'>". $escaper->escapeHtml($lang[$config['headerKey']]) ."</option>\n";
                                }
                            ?>
                        </select>
                    </div>
                    <div class="row-fluid">
                        <div id="crud-wrapper" class="span12">
                            <?php
                                $text_change = $escaper->escapeHtml($lang['Change']);
                                $text_to = $escaper->escapeHtml($lang['to']);
                                $text_update = $escaper->escapeHtml($lang['Update']);
                                $text_add = $escaper->escapeHtml($lang['Add']);
                                $text_delete = $escaper->escapeHtml($lang['Delete']);
                                $text_deleteItem = $escaper->escapeHtml($lang['DeleteItemNamed']);
                                $text_addItem = $escaper->escapeHtml($lang['AddNewItemNamed']);

                                foreach ($tableConfig as $table => $config) {
                                    if($table == "review")
                                    {
                                        $display = "display: block;";
                                    }
                                    else
                                    {
                                        $display = "display: none;";
                                    }
                                    echo "
                                    <div class='hero-unit' data-table_name='" . $table . "' style='{$display}'>\n
                                        <p>\n
                                            <h4>" . $escaper->escapeHtml($lang[$config['headerKey']]) . ":</h4>\n
                                            " . $text_addItem . ":&nbsp;&nbsp;<input id='" . $table . "_new' type='text' maxlength='" . $config['lengthLimit'] . "' size='20' />&nbsp;&nbsp;<input type='submit' value=" .  $text_add . " data-action='add' /><br />\n
                                            " . $text_change . "&nbsp;&nbsp;";
                                            create_dropdown($table, NULL, $table . "_update_from");
                                    echo $text_to . "&nbsp;<input id='" . $table . "_update_to' type='text' maxlength='" . $config['lengthLimit'] . "' size='20' />&nbsp;&nbsp;<input type='submit' value='" . $text_update . "' data-action='update' /><br />
                                            " . $text_deleteItem . ":&nbsp;&nbsp;";
                                            create_dropdown($table, NULL, $table . "_delete");
                                    echo "&nbsp;&nbsp;<input type='submit' value='" . $text_delete . "' data-action='delete' />
                                        </p>
                                    </div>";
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>

            function refreshDropdown(dropdown, data) {
                dropdown.empty();
                dropdown.append($('<option>', {
                    value: 0,
                    text : "--"
                }));
                $.each(data, function (i, item) {
                    dropdown.append($('<option>', {
                        value: item.value,
                        text : item.name
                    }));
                });
            }

            function crudAction() {

                var div = $(this).closest('div');
                if (div) {
                    var tableName = div.data('table_name');
                    var action = $(this).data('action');

                    if (tableName && action) {
                        $.ajax({
                            type: "POST",
                            url: window.location.href,
                            data: (function() {
                                var d = new Object();
                                d.table_name = tableName;
                                d.action = action;

                                switch(action) {
                                    case "add":
                                        d.name = div.find('#' + tableName + '_new').val();
                                        break;
                                    case "update":
                                        d.id = div.find('#' + tableName + '_update_from').val();
                                        d.name = div.find('#' + tableName + '_update_to').val();
                                        break;
                                    case "delete":
                                        d.id = div.find('#' + tableName + '_delete').val();
                                        break;
                                }

                                return d;
                            })(),

                            success: function(data){
                                if(data.status_message){
                                    showAlertsFromArray(data.status_message);
                                }

                                // Empty input boxes
                                div.find('#' + tableName + '_new').val("");
                                div.find('#' + tableName + '_update_to').val("");

                                // Refresh dropdowns
                                refreshDropdown(div.find('#' + tableName + '_update_from'), data.data);
                                refreshDropdown(div.find('#' + tableName + '_delete'), data.data);
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
                    }
                }
            }

            $(document).ready(function() {
                $('#crud-wrapper input[type="submit"]').click(crudAction);
                
                $('#table-sections').change(function(){
                    $("#crud-wrapper > .hero-unit").fadeOut(100);
                    $("#crud-wrapper > [data-table_name='"+$(this).val()+"']").fadeIn(1000);
                })
            });

        </script>
    </body>
</html>
