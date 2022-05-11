<?php
// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/display.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

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
global $lang;

csrf_init();

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
{
  header("Location: ../../index.php");
  exit(0);
}

// Enforce that the user has access to risk management
enforce_permission("riskmanagement");

?>

    <div id="tabs" class="risk-details">
      <div class="row-fluid tab-wrapper">
        <ul class="tabs-nav clearfix">
          <li><a id="tab_details" href="#tabs1"><?php echo $escaper->escapeHtml($lang['Details']); ?></a></li>
          <li><a id="tab_mitigation" href="#tabs2"><?php echo $escaper->escapeHtml($lang['Mitigation']); ?></a></li>
          <li><a id="tab_review" class="tabList" href="#tabs3"><?php echo $escaper->escapeHtml($lang['Review']); ?></a></li>
        </ul>

        <div class="row-fluid">
          <div class="span12">
            <div id="tabs1" class=" tabs1 risk-tab">
              <form name="details" method="post" action="" enctype="multipart/form-data">
                <?php if(@$isAjax && has_permission("modify_risks") && (!isset($action) || $action != 'editdetail')): ?>
                    <!-- Edit th risk details-->
                    <div class="tabs--action">
                        <button type="button" name="edit_details" class="btn on-view"><?php echo $escaper->escapeHtml($lang['EditDetails']); ?></button>
                        <!--<a href="/management/view.php?id=3472&amp;type=0" class="btn cancel-edit on-edit" >Cancel</a>
                        <button type="button" name="update_details" class="btn btn-danger save-details on-edit">Save Details</button>-->
                    </div>
                <?php endif; ?>

                <?php
                // If the user has selected to edit the risk and has permission to edit the risk
                if ((isset($_POST['edit_details']) || (isset($action) && $action == 'editdetail')) && has_permission("modify_risks"))
                {
                  edit_risk_details($id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes,  $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $risk_tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping, $template_group_id);
                }
                // Otherwise we are just viewing the risk
                else
                {
                  view_risk_details($id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes,  $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $risk_tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping, $template_group_id);
                }
                ?>
                <input type="hidden" class="risk_id" value="<?php echo $escaper->escapeHtml($id); ?>">
              </form>
            </div>
            <div id="tabs2" class="tabs2 risk-tab">

              <form name="mitigation" method="post" action="" enctype="multipart/form-data">
                <?php if(@$isAjax && has_permission("plan_mitigations") && (!isset($action) || $action!="editmitigation")): ?>
                    <!-- Edit mitigation -->
                    <div class="tabs--action">
                        <button type="button" name="edit_mitigation" class="btn"><?php echo $escaper->escapeHtml($lang['EditMitigation']); ?></button>
                    </div>
                <?php endif; ?>

                <?php
                // If the user has selected to edit the mitigation and they have permission to edit the mitigation
                if ((isset($_POST['edit_mitigation']) || (isset($action) && $action == 'editmitigation')) && has_permission("plan_mitigations"))
                {
                  edit_mitigation_details($id, $mitigation_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $template_group_id);
                }
                // Otherwise we are just viewing the mitigation
                else
                {
                  view_mitigation_details($id, $mitigation_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $template_group_id);
                }
                ?>
              </form>
            </div>
            <div id="tabs3" class="tabs3 risk-tab">
<!--                      <form name="review" method="post" action="">-->
                <?php
		    // Check the review permissions for this risk id
		    $edit = check_review_permission_by_risk_id($id);

		    // If the user is trying to perform a review and the user has the right permission
                    if (isset($action) && $action == 'editreview' && $edit){
                        $default_next_review = get_next_review_default($id-1000);
                        edit_review_submission($id, $review_id, $review, $next_step, $next_review, $comments, $default_next_review, $template_group_id);
                    }
		else{
                        view_review_details($id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comments, $template_group_id);
                    }
                ?>
<!--                      </form>-->
            </div>
          </div>
        </div>
      </div>
      <div class="row-fluid comments--wrapper">

        <div class="well">
          <h4 class="collapsible--toggle clearfix">
              <span><i class="fa  fa-caret-right"></i><?php echo $escaper->escapeHtml($lang['Comments']); ?></span>
              <a href="#" class="add-comments pull-right"><i class="fa fa-plus"></i></a>
          </h4>

          <div class="collapsible">
            <div class="row-fluid">
              <div class="span12">

                  <form id="comment" class="comment-form" name="add_comment" method="post" action="/management/comment.php?id=<?php echo $id; ?>">
                  <textarea style="width: 100%; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box;" name="comment" cols="50" rows="3" id="comment-text" class="comment-text"></textarea>
                  <div class="form-actions text-right" id="comment-div">
                      <input class="btn" id="rest-btn" value="<?php echo $escaper->escapeHtml($lang['Reset']); ?>" type="reset" />
                    <button id="comment-submit" type="submit" name="submit" class="comment-submit btn btn-primary" ><?php echo $escaper->escapeHtml($lang['Submit']); ?></button>
                  </div>
                </form>
              </div>
            </div>

            <div class="row-fluid">
              <div class="span12">
                <div class="comments--list clearfix">
                    <?php
                        include(realpath(__DIR__ . '/comments-list.php'));
                    ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row-fluid">
          <div class="well">
            <h4 class="collapsible--toggle"><span><i class="fa fa-caret-right"></i><?php echo $escaper->escapeHtml($lang['AuditTrail']); ?></span></h4>
            <div class="collapsible">
              <div class="row-fluid">
                <div class="span12 audit-trail">
                  <?php get_audit_trail_html($id, 36500, ['risk', 'jira']); ?>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">
    <input type="hidden" id="_lang_reopen_risk" value="<?php echo $escaper->escapeHtml($lang['ReopenRisk']); ?>">
    <input type="hidden" id="_lang_close_risk" value="<?php echo $escaper->escapeHtml($lang['CloseRisk']); ?>">
    <input type="hidden" id="_lang_accepting" value="<?php echo $escaper->escapeHtml($lang['Accepting']); ?>">
    <input type="hidden" id="_lang_accept_mitigation" value="<?php echo $escaper->escapeHtml($lang['AcceptMitigation']); ?>">
    <input type="hidden" id="_lang_rejecting" value="<?php echo $escaper->escapeHtml($lang['Rejecting']); ?>">
    <input type="hidden" id="_lang_reject_mitigation" value="<?php echo $escaper->escapeHtml($lang['RejectMitigation']); ?>">
    
