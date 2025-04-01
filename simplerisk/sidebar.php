<?php  
require_once(realpath(__DIR__ .'/header.php'));
?>
<aside class="left-sidebar" data-sidebarbg="skin5">
    <!-- Sidebar scroll-->
    <div class="scroll-sidebar">
        <!-- Sidebar navigation-->
        <nav class="sidebar-nav">
            <ul id="sidebarnav">
                <li class="sidebar-item <?= ($active_sidebar_menu =="Governance")?'selected':''; ?>">
    <?php
        // If the user has governance permissions
        if (check_permission("governance")){ 
    ?>
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu =="Governance")?'active':''?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu"><?=  $escaper->escapeHtml($lang['Governance']);?></span>
                    </a>
    <?php
        }
    ?>
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu =='Governance')?'in':''; ?>">
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='index')?'active':''; ?>">
                            <a href="../governance/index.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['DefineControlFrameworks']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'DocumentProgram')?'active':''; ?>">
                            <a href="../governance/documentation.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['DocumentProgram']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item  <?= ($active_sidebar_submenu == 'DocumentExceptions')?'active':''; ?>">
                            <a href="../governance/document_exceptions.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['DocumentExceptions']);?></span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="sidebar-item  <?= ($active_sidebar_menu =="RiskManagement")?'selected':''; ?>">
    <?php
        // If the user has risk management permissions
        if (check_permission("riskmanagement")){ 
    ?>
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu =="RiskManagement")?'active':''?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['RiskManagement']);?></span>
                    </a>
    <?php
        }
    ?>  
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu =='RiskManagement')?'in':''; ?>">
    <?php
        if (check_permission("riskmanagement") && check_permission("submit_risks")) {
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'SubmitYourRisks')?'active':''; ?>">
                            <a href="../management/index.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['SubmitYourRisks']);?></span>
                            </a>
                        </li>
    <?php
        }
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'PlanYourMitigations')?'active':''; ?>">
                            <a href="../management/plan_mitigations.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['PlanYourMitigations']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'PerformManagementReviews')?'active':''; ?>">
                            <a href="../management/management_review.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['PerformManagementReviews']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item ">
                            <a href="../management/prioritize_planning.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['PrioritizeForProjectPlanning']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'ReviewRisksRegularly')?'active':''; ?>">
                            <a href="../management/review_risks.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ReviewRisksRegularly']);?></span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="sidebar-item <?= ($active_sidebar_menu =="Compliance")?'selected':''; ?>">
    <?php
        // If the user has compliance permissions
        if (check_permission("compliance")){ ?>
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu =="Compliance")?'active':''?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['Compliance']);?></span>
                    </a>
    <?php
        }
    ?>  
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu =='Compliance')?'in':''; ?>">
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'DefineTests')?'active':''; ?>">
                            <a href="../compliance/index.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['DefineTests']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../compliance/audit_initiation.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['InitiateAudits']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'ActiveAudits')?'active':''; ?>">
                            <a href="../compliance/active_audits.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ActiveAudits']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'PastAudits')?'active':''; ?>">
                            <a href="../compliance/past_audits.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['PastAudits']);?></span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="sidebar-item">
    <?php
        // If the user has asset management permissions
        if(isset($_SESSION["asset"]) && $_SESSION["asset"] == "1") { 
    ?>
                    <a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['AssetManagement']);?></span>
                    </a>
    <?php
        }
    ?>
                    <ul aria-expanded="false" class="collapse first-level">
                        <li class="sidebar-item">
                            <a href="../assets/index.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['AutomatedDiscovery']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../assets/manage_assets.php" class="sidebar-link ">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ManageAssets']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='ManageAssetGroups')?'active':''; ?>">
                            <a href="../assets/manage_asset_groups.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ManageAssetGroups']);?></span>
                            </a>
                        </li>
                    </ul>
                </li>
    <?php
        // If the VM Extra is enabled and the user has vulnerability management permissions
        if (vulnmgmt_extra() && check_permission("vm_vulnerabilities")) { 
    ?>
                <li class="sidebar-item <?= ($active_sidebar_menu =="vm_vulnerabilities")?'selected':''; ?>">
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu =="vm_vulnerabilities")?'active':''?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['VulnerabilityManagement']);?></span>
                    </a>
                    
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu =='vm_vulnerabilities')?'in':''; ?>">
    <?php
            // If the user has asset management permissions
            if (check_permission("vm_vulnerabilities")) { 
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Vulnerabilities')?'active':'';?>">
                            <a href="../vulnerabilities/index.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Vulnerabilities']);?></span>
                            </a>
                        </li>
    <?php
            }
            if (check_permission("vm_configure")) {
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Configure')?'active':''; ?>">
                            <a href="../vulnerabilities/configure.php" class="sidebar-link ">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Configure']);?></span>
                            </a>
                        </li>
    <?php
            }
    ?>
                    </ul>
                </li>
    <?php
        }
    ?>
    <?php
        // If the IM Extra is enabled and the user has incident management permissions
        if (incident_management_extra() && check_permission("im_incidents")) {
                // Include the incident management extra
    ?>
                <li class="sidebar-item <?= ($active_sidebar_menu =="IncidentManagement")?'selected':''; ?>">
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu =="IncidentManagement")?'active':''?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['IncidentManagement']);?></span>
                    </a>
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu =='IncidentManagement')?'in':''; ?>">
    <?php
            // If the user has asset management permissions
            if (check_permission("im_incidents")) {
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Incidents')?'active':''; ?>">
                            <a href="javascript:void(0)" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Incidents']);?></span>
                            </a>
                        </li>
                        <!-- the class 'detail-active' is used in breadcrumb part to determine if the detail page was displayed -->
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Preparation')?'active detail-active':''; ?>">
                            <a href="../incidents/index.php?menu=preparation" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['Preparation']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Identification')?'active detail-active':''; ?>">
                            <a href="../incidents/index.php?menu=identification" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['Identification']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Response')?'active detail-active':''; ?>">
                            <a href="../incidents/index.php?menu=response" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['Response']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='LessonsLearned')?'active detail-active':''; ?>">
                            <a href="../incidents/index.php?menu=lessonslearned" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['LessonsLearned']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Closed')?'active detail-active':''; ?>">
                            <a href="../incidents/index.php?menu=closed" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['Closed']);?></span>
                            </a>
                        </li>
    <?php
            }
            // If the user has asset management permissions
            if (check_permission("im_reporting")) {
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Reporting')?'active':''; ?>">
                            <a href="javascript:void(0)" class="sidebar-link ">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Reporting']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Overview')?'active':''; ?>">
                            <a href="../incidents/reporting.php?menu=overview" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['Overview']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='IncidentTrend')?'active':''; ?>">
                            <a href="../incidents/reporting.php?menu=incident_trend" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['IncidentTrend']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='DynamicIncidentReport')?'active':''; ?>">
                            <a href="../incidents/reporting.php?menu=dynamic_incident_report" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['DynamicIncidentReport']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='IM_Reporting_LessonsLearned')?'active':''; ?>">
                            <a href="../incidents/reporting.php?menu=lessons_learned" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['LessonsLearned']);?></span>
                            </a>
                        </li>
    <?php
            }
            // If the user has asset management permissions
            if (check_permission("im_configure")) { 
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='IM_Configure')?'active':''; ?>">
                            <a href="javascript:void(0)" class="sidebar-link ">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Configure']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Settings')?'active':''; ?>">
                            <a href="../incidents/configure.php?menu=settings" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['Settings']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='AddAndRemoveValues')?'active':''; ?>">
                            <a href="../incidents/configure.php?menu=add_remove_values" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['AddAndRemoveValues']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Playbooks')?'active':''; ?>">
                            <a href="../incidents/configure.php?menu=playbooks" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['Playbooks']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Notifications')?'active':''; ?>">
                            <a data-bs-target="#" onclick="location.href='../admin/notification.php'" class="sidebar-link cursor-pointer">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['Notifications']);?></span>
                            </a>
                        </li>
    <?php
            }
    ?>
                    </ul>
                </li>
    <?php
        }
        if (check_permission("ai_access")) {
    ?>
                <li class="sidebar-item <?= ($active_sidebar_menu =="ArtificialIntelligence")?'selected':''; ?>">
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu =='ArtificialIntelligence')?'active':''; ?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['ArtificialIntelligence']);?></span>
                    </a>
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu =='Recommendations')?'in':''; ?>">
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Recommendations')?'active':''; ?>">
                            <a href="../artificial_intelligence/index.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Recommendations']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Documentation')?'active':''; ?>">
                            <a href="../artificial_intelligence/documentation.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Documentation']);?></span>
                            </a>
                        </li>
                    </ul>
                </li>
    <?php
        }
    ?>
                <li class="sidebar-item <?= ($active_sidebar_menu =="Assessments")?'selected':''; ?>">
    <?php
        // If the user has assessments permissions
        if (isset($_SESSION["assessments"]) && $_SESSION["assessments"] == "1") {
    ?>
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu =='Assessments')?'active':''; ?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['Assessments']);?></span>
                    </a>
    <?php
        }
    ?>  
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu =='Assessments')?'in':''; ?>">
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='SelfAssessments')?'active':''; ?>">
                            <a href="../assessments/index.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['SelfAssessments']);?></span>
                            </a>
                        </li>
    <?php
        if (assessments_extra()) {
                        // Display the assessments extra menu 
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='contacts')?'active':''; ?>">
                            <a href="../assessments/contacts.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['AssessmentContacts']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../assessments/questionnaire_questions.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['QuestionnaireQuestions']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='QuestionnaireTemplates')?'active':''; ?>">
                            <a href="../assessments/questionnaire_templates.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['QuestionnaireTemplates']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Questionnaires')?'active':''; ?>">
                            <a href="../assessments/questionnaires.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Questionnaires']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='QuestionnaireResults')?'active':''; ?>">
                            <a href="../assessments/questionnaire_results.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['QuestionnaireResults']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='RiskAnalysis')?'active':''; ?>">
                            <a href="../assessments/questionnaire_risk_analysis.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['RiskAnalysis']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../assessments/importexport.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ImportExport']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='QuestionnaireAuditTrail')?'active':''; ?>">
                            <a href="../assessments/questionnaire_trail.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['QuestionnaireAuditTrail']);?></span>
                            </a>
                        </li>
    <?php
        }
    ?>
                    </ul>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link has-arrow  waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['Reporting']);?></span>
                    </a>
                    <ul aria-expanded="false" class="collapse first-level">
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Reporting_RiskManagement')?'active':''; ?>">
                            <a href="javascript:void(0)" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['RiskManagement']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/index.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['Overview']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/dashboard.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['RiskDashboard']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/risk_appetite.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['RiskAppetiteReport']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/trend.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['RiskTrend']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/dynamic_risk_report.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['DynamicRiskReport']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/graphical_risk_analysis.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['GraphicalRiskAnalysis']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/connectivity_visualizer.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['ConnectivityVisualizer']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/risk_average_baseline_metric.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['RiskAverageOverTime']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/likelihood_impact.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['LikelihoodImpact']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/riskadvice.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['RiskAdvice']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/risks_and_assets.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['RisksAndAssets']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/risks_and_controls.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['RisksAndControls']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/risks_and_issues.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['RisksAndIssues']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/my_open.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['AllOpenRisksAssignedToMeByRiskLevel']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/review_needed.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['AllOpenRisksNeedingReview']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/risks_open_by_team.php?id=true&risk_status=true&subject=true&calculated_risk=true&submission_date=true&team=true&mitigation_planned=true&management_review=true&owner=true&manager=true" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['AllOpenRisksByTeamByLevel']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/high.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['HighRiskReport']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/submitted_by_date.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['SubmittedRisksByDate']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/mitigations_by_date.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['MitigationsByDate']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/mgmt_reviews_by_date.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['ManagementReviewsByDate']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/closed_by_date.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['ClosedRisksByDate']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/recent_commented.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['CurrentRiskComments']);?></span>
                            </a>
                        </li>
    <?php
        if(!empty($_SESSION['compliance'])) { 
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Reporting_Compliance')?'active':''; ?>">
                            <a href="javascript:void(0)" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Compliance']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/dynamic_audit_report.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['DynamicAuditReport']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/audit_timeline.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['AuditTimeline']);?></span>
                            </a>
                        </li>
    <?php
        }
        if(!empty($_SESSION['governance']))
        { 
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Reporting_Governance')?'active':''; ?>">
                            <a href="javascript:void(0)" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Governance']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../reports/control_gap_analysis.php" class="sidebar-link">
                                <span class="hide-menu ps-4"><?= $escaper->escapeHtml($lang['ControlGapAnalysis']);?></span>
                            </a>
                        </li>
    <?php
        }
    ?>
                    </ul>
                </li>
                <li class="sidebar-item <?= ($active_sidebar_menu =="Configure")?'selected':''; ?>">
    <?php
        // If the user is logged in as an administrator
        if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1") { 
    ?>
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu =="Configure")?'active':''; ?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="hide-menu">Configure</span>
                    </a>
    <?php 
        }
    ?>
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu =='Configure')?'in':''; ?>">
    <?php
        if (getTypeOfColumn('mgmt_reviews', 'next_review') == 'varchar') { 
    ?>
                        <li class="sidebar-item">
                            <a href="../admin/fix_review_dates.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['FixReviewDates']);?></span>
                            </a>
                        </li>
    <?php
        }
        if (has_files_with_encoding_issues()) { 
    ?>
                        <li class="sidebar-item">
                            <a href="../admin/fix_upload_encoding_issues.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['FixFileEncodingIssues']);?></span>
                            </a>
                        </li>
    <?php
        }
    ?>
                        <li class="sidebar-item">
                            <a href="../admin/index.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Settings']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/content.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Content']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/risk_catalog.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['RiskAndThreatCatalog']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/configure_risk_formula.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ConfigureRiskFormula']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/review_settings.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ConfigureReviewSettings']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/add_remove_values.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['AddAndRemoveValues']);?></span>
                            </a>
                        </li>
    <?php
        if (organizational_hierarchy_extra()) { 
    ?>
                        <li class="sidebar-item">
                            <a href="../admin/organizational_hierarchy.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['OrganizationManagement']);?></span>
                            </a>
                        </li>
    <?php
        }
    ?>
                        <li class="sidebar-item">
                            <a href="../admin/role_management.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['RoleManagement']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'UserManagement')?'active':''; ?>">
                            <a href="../admin/user_management.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['UserManagement']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/custom_names.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['RedefineNamingConventions']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/assetvaluation.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['AssetValuation']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/delete_risks.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['DeleteRisks']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='AuditTrail')?'active':''; ?>">
                            <a href="../admin/audit_trail.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['AuditTrail']);?></span>
                            </a>
                        </li>
    <?php
        if (import_export_extra()) { 
    ?>
                        <li class="sidebar-item">
                            <a href="../admin/importexport.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ImportExport']);?></span>
                            </a>
                        </li>
    <?php
        }
        if (assessments_extra()) { 
    ?>
                        <li class="sidebar-item">
                            <a href="../admin/active_assessments.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ActiveAssessments']);?></span>
                            </a>
                        </li>
    <?php
        }
    ?>
                        <li class="sidebar-item  <?= ($active_sidebar_submenu == 'ArtificialIntelligence')?'active':''; ?>">
                            <a href="../admin/artificial_intelligence.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['ArtificialIntelligence']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item  <?= ($active_sidebar_submenu == 'Extras')?'active':''; ?>">
                            <a href="../admin/extras.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Extras']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/announcements.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Announcements']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/register.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['RegisterAndUpgrade']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/health_check.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['HealthCheck']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../admin/about.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['About']);?></span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
        <!-- End Sidebar navigation -->
    </div>
    <!-- End Sidebar scroll-->
</aside>
<!-- End Left Sidebar - style you can find in sidebar.scss  -->

<!-- ============================================================== -->
<!-- Page wrapper  -->
<div class="page-wrapper">
    <div class="scroll-content">
        <div class="content-wrapper">
            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12 d-flex no-block align-items-center">
                        <h4 class="page-title">
    <?php
        if (!empty($breadcrumb_title_key)) {
            // according to the existence of '$lang[$breadcrumb_title_key]'.
            echo            $escaper->escapeHtml($lang[$breadcrumb_title_key] ?? $breadcrumb_title_key);
        } else {
    ?>
<script>
    // Run this script after the Sidebar's script is loaded
    $('#script_sidebarmenu').on('load', function () {
        $(function() {
            // Set the page title to the text of the selected submenu
            $('div.page-breadcrumb h4.page-title').text($('ul.first-level a.sidebar-link.active > span').text());
        });
    });
</script>
    <?php
        }
    ?>
                        </h4>
    <?php
        if (!empty($breadcrumb_title_key)) {
            if (!empty($active_sidebar_menu) && !empty($active_sidebar_submenu)) {
    ?>
                        <div class="ms-auto text-end">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item menu"></li>
                                    <li class="breadcrumb-item submenu"><a href="#"></a></li>
                                    <li class="breadcrumb-item thirdmenu d-none"><a href="#"></a></li>
                                    <li class="breadcrumb-item">
                                        <!-- according to the existence of '$lang[$breadcrumb_title_key]'. -->
                                        <?=$escaper->escapeHtml($lang[$breadcrumb_title_key] ?? $breadcrumb_title_key)?>
                                    </li>
                                </ol>
                            </nav>
                        </div>
<script>
    // Run this script after the Sidebar's script is loaded
    $('#script_sidebarmenu').on('load', function () {
        $(function() {

            // Set first breadcrumb's text of the selected menu's text

            // in case the selected menu exists
            if ($('li.sidebar-item.selected > a.sidebar-link.active').length) {

                $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.menu').text($('li.sidebar-item.selected > a.sidebar-link.active > span').text());

            // in case the selected menu doesn't exist, especially for a unlive page.
            } else {

                $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.menu').text('<?= $escaper->escapeHtml($lang[$active_sidebar_menu] ?? $active_sidebar_menu) ?>');
                
            }
            
            // in case the selected submenu exists
            if ($('ul.first-level li.sidebar-item.active a.sidebar-link').length) {

                // in case that the page in the forth level was displayed
                if ($('ul.first-level li.sidebar-item.detail-active').length) {
                    // Set the second breadcrumb's text and href using the selected submenu's text and href
                    $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu > a').attr('href', $('ul.first-level li.sidebar-item.active a.sidebar-link').eq(0).attr('href'));
                    $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu > a').text($('ul.first-level li.sidebar-item.active a.sidebar-link > span').eq(0).text());

                    // Set the third breadcrumb's text and href using the selected submenu's text and href
                    $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.thirdmenu').removeClass('d-none');
                    $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.thirdmenu > a').attr('href', $('ul.first-level li.sidebar-item.active a.sidebar-link').eq(1).attr('href'));
                    $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.thirdmenu > a').text($('ul.first-level li.sidebar-item.active a.sidebar-link > span').eq(1).text());
                
                } else {
                    $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu > a').attr('href', $('ul.first-level li.sidebar-item.active a.sidebar-link').eq(0).attr('href'));
                    $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu > a').text($('ul.first-level li.sidebar-item.active a.sidebar-link > span').eq(0).text());
                }

            // in case the selected submenu doesn't exist, especially for a unlive page.
            } else {

                $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu > a').attr('href', '#');
                $('div.page-breadcrumb ol.breadcrumb li.breadcrumb-item.submenu > a').text('<?= $escaper->escapeHtml($lang[$active_sidebar_submenu] ?? $active_sidebar_submenu) ?>');

            }

        });
    });
</script>
    <?php
            }
        }
    ?>
                    </div>
                </div>
            </div>
            <!-- container - It's the direct container of all the -->
            <div class="content container-fluid">