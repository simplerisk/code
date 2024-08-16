<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar([], ['check_admin' => true], 'Team-Based Separation Extra', 'Configure', 'Extras');

// If the extra directory exists
if (is_dir(realpath(__DIR__ . '/../extras/separation')))
{
    // Include the Separation Extra
    require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

    // If the user wants to activate the extra
    if (isset($_POST['activate']))
    {
        // Enable the Separation Extra
        enable_team_separation_extra();
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate']))
    {
        // Disable the Separation Extra
        disable_team_separation_extra();
    }
    
    // If the user wants to update permissions for risk
    if(isset($_POST['update_permissions'])){
        $permissions = array(
            'allow_owner_to_risk'                               => isset($_POST['allow_owner_to_risk']) ? 1 : 0,
            'allow_ownermanager_to_risk'                        => isset($_POST['allow_ownermanager_to_risk']) ? 1 : 0,
            'allow_submitter_to_risk'                           => isset($_POST['allow_submitter_to_risk']) ? 1 : 0,
            'allow_team_member_to_risk'                         => isset($_POST['allow_team_member_to_risk']) ? 1 : 0,
            'allow_stakeholder_to_risk'                         => isset($_POST['allow_stakeholder_to_risk']) ? 1 : 0,
            'allow_all_to_risk_noassign_team'                   => isset($_POST['allow_all_to_risk_noassign_team']) ? 1 : 0,

            'allow_control_owner_to_see_test_and_audit'         => isset($_POST['allow_control_owner_to_see_test_and_audit']) ? 1 : 0,
            'allow_tester_to_see_test_and_audit'                => isset($_POST['allow_tester_to_see_test_and_audit']) ? 1 : 0,
            'allow_stakeholders_to_see_test_and_audit'          => isset($_POST['allow_stakeholders_to_see_test_and_audit']) ? 1 : 0,
            'allow_team_members_to_see_test_and_audit'          => isset($_POST['allow_team_members_to_see_test_and_audit']) ? 1 : 0,
            'allow_everyone_to_see_test_and_audit'              => isset($_POST['allow_everyone_to_see_test_and_audit']) ? 1 : 0,

            'allow_all_to_asset_noassign_team'                  => isset($_POST['allow_all_to_asset_noassign_team']) ? 1 : 0,

            'allow_document_owner_to_see_documents'             => isset($_POST['allow_document_owner_to_see_documents']) ? 1 : 0,
            'allow_document_owners_manager_to_see_documents'    => isset($_POST['allow_document_owners_manager_to_see_documents']) ? 1 : 0,
            'allow_approver_to_see_documents'                   => isset($_POST['allow_approver_to_see_documents']) ? 1 : 0,
            'allow_stakeholders_to_see_documents'               => isset($_POST['allow_stakeholders_to_see_documents']) ? 1 : 0,
            'allow_team_to_see_documents'                       => isset($_POST['allow_team_to_see_documents']) ? 1 : 0,
            'allow_all_to_document_noassign_team'               => isset($_POST['allow_all_to_document_noassign_team']) ? 1 : 0,
            'allow_all_to_see_document'                         => isset($_POST['allow_all_to_see_document']) ? 1 : 0,
        );
        update_permission_settings($permissions);
        set_alert(true, "good", $lang['SavedSuccess']);
    }
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display()
{
    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/separation')))
    {
        // But the extra is not activated
        if (!team_separation_extra())
        {
            echo "<div class='card-body my-2 border'>";
            // If the extra is not restricted based on the install type
            if (!restricted_extra("separation"))
            {
                echo "
                    <form name='activate_extra' method='post' action=''>
                        <input type='submit' value='" . $escaper->escapeHtml($lang['Activate']) . "' name='activate' class='btn btn-submit'/>
                    </form>";
            }
            // The extra is restricted
            else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            echo "</div>";
        }
        // Once it has been activated
        else
        {
            // Include the Team Separation Extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            display_team_separation();
        }
    }
    // Otherwise, the Extra does not exist
    else
    {
        echo "
            <div class='card-body my-2 border'>
                <a href='https://www.simplerisk.com/extras' target='_blank' class='text-info'>Purchase the Extra</a>
            </div>";
    }
}

?>
<div class="row bg-white "> 
    <div class="col-12">
        <?php display(); ?>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>