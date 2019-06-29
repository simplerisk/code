<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/messages.php'));
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

require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

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

// Check if save role responsibilites was submitted
if(isset($_POST['save_role_responsibilities']))
{
    $role = (int)$_POST['role'];
    $responsibilities = empty($_POST['responsibilities']) ? [] : array_keys($_POST['responsibilities']); 
    
    // Check if role was submitted
    if($role)
    {
        save_role_responsibilities($role, $responsibilities);
        set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
    }
    else
    {
        set_alert(true, "bad", "Role is required.");
    }
//    refresh();
}
//Check if adding role was submitted 
elseif(isset($_POST['add_role']))
{
    $role_name = $_POST['role_name'];
    if($role_name)
    {
        add_name("role", $role_name);
        set_alert(true, "good", $escaper->escapeHtml($lang['AddedSuccess']));
        refresh();
    }
}
//Check if deleting role was submitted 
elseif(isset($_POST['delete_role']))
{
    $role_id = $_POST['role'];
    if(!$role_id || $role_id==1)
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['CantDeleteAdministratorRole']));
    }
    else
    {
        delete_role($role_id);
        set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        refresh();
    }
}

?>
<!doctype html>
<html>

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>
    <script src="../js/pages/role_management.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">

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
            <?php view_configure_menu("RoleManagement"); ?>
        </div>
        <div class="span9">
            <div class="row-fluid">
                <div class="span12">
                    <div class="hero-unit">
                        <form method="post" action="">
                            <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td>
                                        <h4><u><?php echo $escaper->escapeHtml($lang['AddNewRole']); ?></u></h4>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input name="role_name" value="" class="form-field form-control" type="text" placeholder="<?php echo $escaper->escapeHtml($lang['RoleName']); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <button name="add_role"><?php echo $escaper->escapeHtml($lang['Add']) ?></button>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                    <div class="hero-unit">
                        <form method="post" action="">
                            <table border="0" cellspacing="0" cellpadding="0" >
                                <tr>
                                    <td colspan="2">
                                        <h4><u><?php echo $escaper->escapeHtml($lang['Role']); ?></u></h4>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <?php
//                                            create_dropdown("role", isset($_POST['role']) ? $_POST['role'] : "", "role", true, false, false, "required");
                                            create_dropdown("role", (isset($_POST['role']) ? $_POST['role'] : ""), "role", true, false, false, "required");
                                            if(isset($_POST['role']))
                                            {
                                                echo "<script>\n";
                                                    echo "$(document).ready(function(){\n";
                                                        echo "$(\"#role\").change();\n";
                                                    echo "})\n";
                                                echo "</script>\n";
                                            }
                                        ?>
                                        
                                    </td>
                                    <td valign="top">
                                        &nbsp;&nbsp;&nbsp;&nbsp;
                                        <button class="btn" name="delete_role" type="submit"><?php echo $escaper->escapeHtml($lang['Delete']); ?></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <h4><u><?php echo $escaper->escapeHtml($lang['UserResponsibilities']); ?></u></h4>
                                        <ul class="checklist">
                                          <li><input name="check_all" class="hidden-checkbox" id="check_all" type="checkbox" onclick="checkAll(this)" /> <label for="check_all"> <?php echo $escaper->escapeHtml($lang['CheckAll']); ?> </label> </li>
                                          <li>
                                            <ul>
                                                <li><input class="hidden-checkbox" id="check_governance" name="check_governance" type="checkbox" > <label for="check_governance"><?php echo $escaper->escapeHtml($lang['CheckAllGovernance']); ?></label></li>
                                                <li>
                                                    <ul>
                                                        <li><input class="hidden-checkbox" id="governance" name="responsibilities[governance]" type="checkbox" /> <label for="governance"><?php echo $escaper->escapeHtml($lang['AllowAccessToGovernanceMenu']); ?></label></li>
                                                        
                                                        <li><input class="hidden-checkbox" id="add_new_frameworks" name="responsibilities[add_new_frameworks]" type="checkbox" /> <label for="add_new_frameworks"><?php echo $escaper->escapeHtml($lang['AbleToAddNewFrameworks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="modify_frameworks" name="responsibilities[modify_frameworks]" type="checkbox" /> <label for="modify_frameworks"><?php echo $escaper->escapeHtml($lang['AbleToModifyExistingFrameworks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="delete_frameworks" name="responsibilities[delete_frameworks]" type="checkbox" /> <label for="delete_frameworks"><?php echo $escaper->escapeHtml($lang['AbleToDeleteExistingFrameworks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="add_new_controls" name="responsibilities[add_new_controls]" type="checkbox" /> <label for="add_new_controls"><?php echo $escaper->escapeHtml($lang['AbleToAddNewControls']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="modify_controls" name="responsibilities[modify_controls]" type="checkbox" /> <label for="modify_controls"><?php echo $escaper->escapeHtml($lang['AbleToModifyExistingControls']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="delete_controls" name="responsibilities[delete_controls]" type="checkbox" /> <label for="delete_controls"><?php echo $escaper->escapeHtml($lang['AbleToDeleteExistingControls']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="add_documentation" name="responsibilities[add_documentation]" type="checkbox" /> <label for="add_documentation"><?php echo $escaper->escapeHtml($lang['AbleToAddDocumentation']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="modify_documentation" name="responsibilities[modify_documentation]" type="checkbox" /> <label for="modify_documentation"><?php echo $escaper->escapeHtml($lang['AbleToModifyDocumentation']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="delete_documentation" name="responsibilities[delete_documentation]" type="checkbox" /> <label for="delete_documentation"><?php echo $escaper->escapeHtml($lang['AbleToDeleteDocumentation']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="view_exception" name="responsibilities[view_exception]" type="checkbox" /> <label for="view_exception"><?php echo $escaper->escapeHtml($lang['AbleToViewDocumentException']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="create_exception" name="responsibilities[create_exception]" type="checkbox" /> <label for="create_exception"><?php echo $escaper->escapeHtml($lang['AbleToCreateDocumentException']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="update_exception" name="responsibilities[update_exception]" type="checkbox" /> <label for="update_exception"><?php echo $escaper->escapeHtml($lang['AbleToUpdateDocumentException']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="delete_exception" name="responsibilities[delete_exception]" type="checkbox" /> <label for="delete_exception"><?php echo $escaper->escapeHtml($lang['AbleToDeleteDocumentException']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="approve_exception" name="responsibilities[approve_exception]" type="checkbox" /> <label for="approve_exception"><?php echo $escaper->escapeHtml($lang['AbleToApproveDocumentException']); ?></label></li>
                                                    </ul>
                                                </li>
                                            </ul>
                                            <ul>
                                                <li><input class="hidden-checkbox" id="check_risk_mgmt" name="check_risk_mgmt" type="checkbox" > <label for="check_risk_mgmt"><?php echo $escaper->escapeHtml($lang['CheckAllRiskMgmt']); ?></label></li>
                                                <li>
                                                    <ul>
                                                        <li><input class="hidden-checkbox" id="riskmanagement" name="responsibilities[riskmanagement]" type="checkbox" /> <label for="riskmanagement"><?php echo $escaper->escapeHtml($lang['AllowAccessToRiskManagementMenu']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="submit_risks" name="responsibilities[submit_risks]" type="checkbox" />   <label for="submit_risks"><?php echo $escaper->escapeHtml($lang['AbleToSubmitNewRisks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="modify_risks" name="responsibilities[modify_risks]" type="checkbox" />   <label for="modify_risks"><?php echo $escaper->escapeHtml($lang['AbleToModifyExistingRisks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="close_risks" name="responsibilities[close_risks]" type="checkbox" />    <label for="close_risks"><?php echo $escaper->escapeHtml($lang['AbleToCloseRisks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="plan_mitigations" name="responsibilities[plan_mitigations]" type="checkbox" />  <label for="plan_mitigations"><?php echo $escaper->escapeHtml($lang['AbleToPlanMitigations']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="accept_mitigation" name="responsibilities[accept_mitigation]" type="checkbox" />  <label for="accept_mitigation"><?php echo $escaper->escapeHtml($lang['AbleToAcceptMitigations']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="review_insignificant" name="responsibilities[review_insignificant]" type="checkbox" />  <label for="review_insignificant"><?php echo $escaper->escapeHtml($lang['AbleToReviewInsignificantRisks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="review_low" name="responsibilities[review_low]" type="checkbox" />  <label for="review_low"><?php echo $escaper->escapeHtml($lang['AbleToReviewLowRisks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="review_medium" name="responsibilities[review_medium]" type="checkbox" />  <label for="review_medium"><?php echo $escaper->escapeHtml($lang['AbleToReviewMediumRisks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="review_high" name="responsibilities[review_high]" type="checkbox" />  <label for="review_high"><?php echo $escaper->escapeHtml($lang['AbleToReviewHighRisks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="review_veryhigh" name="responsibilities[review_veryhigh]" type="checkbox" />  <label for="review_veryhigh"><?php echo $escaper->escapeHtml($lang['AbleToReviewVeryHighRisks']); ?></label></li>
                                                        <li><input class="hidden-checkbox" id="comment_risk_management" name="responsibilities[comment_risk_management]" type="checkbox" /> <label for="comment_risk_management"><?php echo $escaper->escapeHtml($lang['AbleToCommentRiskManagement']); ?></label></li>
                                                    </ul>
                                                </li>
                                            </ul>
                                            <ul>
                                                <li><input class="hidden-checkbox" id="check_compliance" name="check_compliance" type="checkbox" > <label for="check_compliance"><?php echo $escaper->escapeHtml($lang['CheckAllCompliance']); ?></label></li>
                                                <li>
                                                    <ul>
                                                    <li><input class="hidden-checkbox" id="compliance" name="responsibilities[compliance]" type="checkbox" /> <label for="compliance"><?php echo $escaper->escapeHtml($lang['AllowAccessToComplianceMenu']); ?></label></li>
                                                    <li><input class="hidden-checkbox" id="comment_compliance" name="responsibilities[comment_compliance]" type="checkbox" /> <label for="comment_compliance"><?php echo $escaper->escapeHtml($lang['AbleToCommentCompliance']); ?></label></li>
                                                  </ul>
                                                </li>
                                            </ul>
                                          </li>
                                          <li>
                                                <ul>
                                                  <li><input class="hidden-checkbox" id="check_asset_mgmt" name="check_asset_mgmt" type="checkbox"  /> <label for="check_asset_mgmt"><?php echo $escaper->escapeHtml($lang['CheckAllAssetMgmt']); ?></label></li>
                                                  <li>
                                                      <ul>
                                                        <li><input class="hidden-checkbox" id="asset" name="responsibilities[asset]" type="checkbox" /> <label for="asset"><?php echo $escaper->escapeHtml($lang['AllowAccessToAssetManagementMenu']); ?></label></li>
                                                      </ul>
                                                  </li>
                                                </ul>
                                          </li>    
                                          <li>
                                                <ul>
                                                  <li><input class="hidden-checkbox" id="check_assessments" name="check_assessments" type="checkbox"  /> <label for="check_assessments"><?php echo $escaper->escapeHtml($lang['CheckAllAssessments']); ?></label></li>
                                                  <li>
                                                      <ul>
                                                        <li><input class="hidden-checkbox" id="assessments" name="responsibilities[assessments]" type="checkbox" /> <label for="assessments"><?php echo $escaper->escapeHtml($lang['AllowAccessToAssessmentsMenu']); ?></label></li>
                                                      </ul>
                                                  </li>
                                                </ul>
                                          </li>
                                          <li>
                                                <ul>
                                                  <li><input class="hidden-checkbox" id="check_configure" name="check_configure" type="checkbox"  /> <label for="check_configure"><?php echo $escaper->escapeHtml($lang['CheckAllConfigure']); ?></label></li>
                                                  <li>
                                                      <ul>
                                                        <li><input class="hidden-checkbox" id="admin" name="responsibilities[admin]" type="checkbox" /> <label for="admin"><?php echo $escaper->escapeHtml($lang['AllowAccessToConfigureMenu']); ?></label></li>
                                                      </ul>
                                                  </li>

                                                </ul>
                                          </li>
                                        </ul>

                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <button name="save_role_responsibilities"><?php echo $escaper->escapeHtml($lang['Save']) ?></button>
                                    </td>
                                </tr>
                            </table>
                            
                        </form>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<?php display_set_default_date_format_script(); ?>
</body>

</html>
