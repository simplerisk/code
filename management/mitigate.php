          <div class="row-fluid">
            <div class="well">
              <?php view_top_table($id, $calculated_risk, $subject, $status, true); ?>
            </div>
            <div id="scoredetails" class="row-fluid" style="display: none;">
              <div class="well">
                  <?php
                        // Scoring method is Classic
                        if ($scoring_method == "1")
                        {
                                classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact);
                        }
                        // Scoring method is CVSS
                        else if ($scoring_method == "2")
                        {
                                cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
                        }
                        // Scoring method is DREAD
                        else if ($scoring_method == "3")
                        {
                                dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
                        }
                        // Scoring method is OWASP
                        else if ($scoring_method == "4")
                        {
                                owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation);
                        }
                        // Scoring method is Custom
                        else if ($scoring_method == "5")
                        {
                                custom_scoring_table($id, $custom);
                        }
                  ?>
              </div>
            </div>
            <div id="updatescore" class="row-fluid" style="display: none;">
              <div class="well">
                  <?php
                        // Scoring method is Classic
                        if ($scoring_method == "1")
                        {
                                edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact);
                        }
                        // Scoring method is CVSS
                        else if ($scoring_method == "2")
                        {
                                edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
                        }
                        // Scoring method is DREAD
                        else if ($scoring_method == "3")
                        {
                                edit_dread_score($DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
                        }
                        // Scoring method is OWASP
                        else if ($scoring_method == "4")
                        {
                                edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
                        }
                        // Scoring method is Custom
                        else if ($scoring_method == "5")
                        {
                                edit_custom_score($custom);
                        }
                  ?>
              </div>
            </div>
            <div class="well">
                <?php edit_mitigation_submission($planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="span6">
              <div class="well">
		<?php view_risk_details($submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes); ?>
              </div>
            </div>
            <div class="span6">
              <div class="well">
		<?php view_review_details($id, $review_date, $reviewer, $review, $next_step, $next_review, $comments); ?>
              </div>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <h4><?php echo $lang['Comments']; ?></h4>
              <?php get_comments($id); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <h4><?php echo $lang['AuditTrail']; ?></h4>
              <?php get_audit_trail($id); ?>
            </div>
          </div>
       
