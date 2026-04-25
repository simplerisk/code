<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['CUSTOM:selectlist.js', 'CUSTOM:common.js'], ['check_admin' => true]);

$team_length_limit = 50;

function add_team_for_management($name) {
    // Insert a new team.
    $team_id = add_name("team", $name);

    // Any new team should be assigned to all admin users.
    set_all_teams_to_administrators();

    // If the Organizational Hierarchy extra is enabled, assign to the default business unit.
    if (organizational_hierarchy_extra()) {
        require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
        assign_teams_to_default_business_unit();
    }

    return $team_id;
}

function delete_team_for_management($team_id) {
    global $lang;

    // If team separation is enabled and the team is in use by a risk, do not allow delete.
    if (team_separation_extra() && !empty(get_risks_by_team($team_id))) {
        set_alert(true, "bad", $lang['CantDeleteTeamItsInUseByARisk']);
        return false;
    }

    $delete_result = delete_value("team", $team_id);

    if ($delete_result) {
        cleanup_after_delete("team");
    }

    return $delete_result;
}

$teams = get_all_teams();
$selected_team_id = isset($_POST['membership_team']) && ctype_digit($_POST['membership_team']) ? (int)$_POST['membership_team'] : 0;

if (!$selected_team_id && !empty($teams)) {
    $selected_team_id = (int)$teams[0]['value'];
}

if (isset($_POST['action']) && $_POST['action'] == 'add_team') {
    $name = isset($_POST['team_new']) ? trim($_POST['team_new']) : '';

    if ($name === '') {
        set_alert(true, "bad", $lang['YouNeedToSpecifyANameParameter']);
    } elseif (strlen($name) > $team_length_limit) {
        set_alert(true, "bad", _lang('TheEnteredValueIsTooLong', ['limit' => $team_length_limit]));
    } else {
        $result = add_team_for_management($name);
        if ($result) {
            set_alert(true, "good", $lang['ANewItemWasAddedSuccessfully']);
        } else {
            set_alert(true, "bad", $lang['FailedToAddNewItem']);
        }
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'update_team') {
    $team_id = isset($_POST['team_update_from']) && ctype_digit($_POST['team_update_from']) ? (int)$_POST['team_update_from'] : 0;
    $name = isset($_POST['team_update_to']) ? trim($_POST['team_update_to']) : '';

    if (!$team_id) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);
    } elseif ($name === '') {
        set_alert(true, "bad", $lang['YouNeedToSpecifyANameParameter']);
    } elseif (strlen($name) > $team_length_limit) {
        set_alert(true, "bad", _lang('TheEnteredValueIsTooLong', ['limit' => $team_length_limit]));
    } else {
        $result = update_table("team", $name, $team_id);
        if ($result) {
            set_alert(true, "good", $lang['AnItemWasUpdatedSuccessfully']);
        } else {
            set_alert(true, "bad", $lang['FailedToUpdateItem']);
        }
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'delete_team') {
    $team_id = isset($_POST['team_delete']) && ctype_digit($_POST['team_delete']) ? (int)$_POST['team_delete'] : 0;

    if (!$team_id) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);
    } else {
        $result = delete_team_for_management($team_id);
        if ($result) {
            set_alert(true, "good", $lang['AnItemWasDeletedSuccessfully']);
        } else {
            set_alert(true, "bad", $lang['FailedToDeleteItem']);
        }
    }
}

if (isset($_POST['update_team_members'])) {
    if (!$selected_team_id) {
        set_alert(true, "bad", $lang['PleaseSelectATeam']);
    } else {
        $selected_user_ids = isset($_POST['selected_users']) ? array_values(array_filter($_POST['selected_users'], 'ctype_digit')) : [];
        $selected_user_ids = array_map('intval', $selected_user_ids);

        $all_users = get_non_admin_users_for_team_management($selected_team_id);
        $all_user_ids = array_map(static fn($u) => (int)$u['value'], $all_users);
        $selected_user_ids = array_values(array_intersect($selected_user_ids, $all_user_ids));

        update_team_membership_for_users($selected_team_id, $selected_user_ids, $all_user_ids);
        set_alert(true, "good", $lang['TheTeamMembersWereUpdatedSuccessfully']);
    }
}

$all_users = get_non_admin_users_for_team_management($selected_team_id);
$available_users = [];
$selected_users = [];

if ($selected_team_id) {
    foreach ($all_users as $user) {
        $user_id = (int)$user['value'];
        $user_data = [
            'value' => $user_id,
            'name' => $user['username'],
        ];

        $user_teams = get_user_teams($user_id);
        if (!$user_teams) {
            $user_teams = [];
        }

        $user_team_ids = array_map('intval', $user_teams);
        if (in_array($selected_team_id, $user_team_ids)) {
            $selected_users[] = $user_data;
        } else {
            $available_users[] = $user_data;
        }
    }
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
            <h4><?= $escaper->escapeHtml($lang['TeamManagement']); ?></h4>
            <form name="team_crud" method="post" action="">
                <input type="hidden" name="action" value=""/>
                <div class="row form-group align-items-end">
                    <div class="col-md-4">
                        <label><?= $escaper->escapeHtml($lang['AddNewItemNamed']); ?> :</label>
                        <input name="team_new" type="text" maxlength="<?= $escaper->escapeHtml($team_length_limit); ?>" class="form-control"/>
                    </div>
                    <div class="col-md-1">
                        <input type="submit" value="<?= $escaper->escapeHtml($lang['Add']); ?>" name="add_team" class="btn btn-submit"/>
                    </div>
                </div>
                <div class="row form-group align-items-end">
                    <div class="col-md-2">
                        <label><?= $escaper->escapeHtml($lang['Change']); ?> :</label>
    <?php 
                        create_dropdown("team", null, "team_update_from"); 
    ?>
                    </div>
                    <div class="col-md-2">
                        <label><?= $escaper->escapeHtml($lang['to']); ?> :</label>
                        <input name="team_update_to" type="text" maxlength="<?= $escaper->escapeHtml($team_length_limit); ?>" class="form-control"/>
                    </div>
                    <div class="col-md-1">
                        <input type="submit" value="<?= $escaper->escapeHtml($lang['Update']); ?>" name="update_team" class="btn btn-submit"/>
                    </div>
                </div>
                <div class="row form-group align-items-end mb-0">
                    <div class="col-md-4">
                        <label><?= $escaper->escapeHtml($lang['DeleteItemNamed']); ?> :</label>
    <?php 
                        create_dropdown("team", null, "team_delete"); 
    ?>
                    </div>
                    <div class="col-md-1">
                        <input type="submit" value="<?= $escaper->escapeHtml($lang['Delete']); ?>" name="delete_team" class="btn btn-primary"/>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body my-2 border">
            <h4><?= $escaper->escapeHtml($lang['ManageUsersInTeam']); ?></h4>
            <form id="team-selection-form" method="post" action="" class="mb-3">
                <div class="row form-group align-items-end">
                    <div class="col-md-4">
                        <label><?= $escaper->escapeHtml($lang['Team']); ?> :</label>
    <?php 
                        create_dropdown("team", $selected_team_id, "membership_team", false); 
    ?>
                    </div>
                </div>
            </form>

            <form id="team-members-form" method="post" action="">
                <input type="hidden" name="update_team_members" value="1"/>
                <input type="hidden" name="membership_team" value="<?= $escaper->escapeHtml($selected_team_id); ?>"/>
                <div class="select-list-wrapper row">
                    <div class="select-list-available col-4">
                        <label><?= $escaper->escapeHtml($lang['AvailableUsers']); ?> :</label>
                        <select multiple="multiple" class="form-control" size="15">
<?php
    foreach ($available_users as $available_user) {
?>
                            <option value="<?= (int)$available_user['value']; ?>"><?= $escaper->escapeHtml($available_user['name']); ?></option>
<?php
    }
?>
                        </select>
                    </div>
                    <div class="select-list-arrows d-flex flex-column align-items-center col-1">
                        <input type='button' value='&gt;&gt;' class="btn btn-secondary btnAllRight" />
                        <input type='button' value='&gt;' class="btn btn-secondary btnRight" />
                        <input type='button' value='&lt;' class="btn btn-secondary btnLeft" />
                        <input type='button' value='&lt;&lt;' class="btn btn-secondary btnAllLeft" />
                    </div>
                    <div class="select-list-selected col-4">
                        <label><?= $escaper->escapeHtml($lang['TeamMembers']); ?> :</label>
                        <select name="selected_users[]" id="selected-users" multiple="multiple" class="form-control" size="15">
<?php
    foreach ($selected_users as $selected_user) {
?>
                            <option value="<?= (int)$selected_user['value']; ?>"><?= $escaper->escapeHtml($selected_user['name']); ?></option>
<?php
    }
?>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <input type="submit" value="<?= $escaper->escapeHtml($lang['Update']); ?>" name="update_team_members" class="btn btn-submit"/>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        $("[name='team_crud'] [type='submit']").click(function(event) {
            event.preventDefault();

            // Set the hidden action input based on which button was clicked.
            var action = $(this).attr("name");
            $("[name='team_crud'] [name='action']").val(action);

            // When deleting, show a confirmation dialog before submitting the form
            if (action == "delete_team") {
                // Display a confirmation dialog
                confirm("<?= $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteSelction']); ?>", () => {
                    $(this)[0].form.submit();
                });
            // For add and update actions, submit the form directly without confirmation
            } else {
                $(this)[0].form.submit();
            }

        });
        $("#membership_team").change(function() {
            $("#team-selection-form").submit();
        });

        $("#team-members-form").submit(function() {
            // Prevent the form submission.
            event.preventDefault();

            // Before submitting the form, select all options in the selected users list to ensure they are included in the POST data.
            $("#selected-users option").prop("selected", true);

            // Submit the form using a native javascript
            $(this)[0].submit();
        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>