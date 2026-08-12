<?php
require_once(realpath(__DIR__ .'/header.php'));
require_once(realpath(__DIR__ . '/includes/artificial_intelligence.php'));
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
                        <span class="sr-nav-ico"><i class="fas fa-landmark"></i></span>
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
                        <span class="sr-nav-ico"><i class="fas fa-triangle-exclamation"></i></span>
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
                        <span class="sr-nav-ico"><i class="fas fa-clipboard-check"></i></span>
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
                        <span class="sr-nav-ico"><i class="fas fa-server"></i></span>
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
                        <span class="sr-nav-ico"><i class="fas fa-bug"></i></span>
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['Vulnerabilities']);?></span>
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
                        <span class="sr-nav-ico"><i class="fas fa-fire"></i></span>
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['Incidents']);?></span>
                    </a>
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu =='IncidentManagement')?'in':''; ?>">
    <?php
            // If the user has asset management permissions
            if (check_permission("im_incidents")) {
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Preparation')?'active':''; ?>">
                            <a href="../incidents/index.php?menu=preparation" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Preparation']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Identification')?'active':''; ?>">
                            <a href="../incidents/index.php?menu=identification" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Identification']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Response')?'active':''; ?>">
                            <a href="../incidents/index.php?menu=response" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Response']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='LessonsLearned')?'active':''; ?>">
                            <a href="../incidents/index.php?menu=lessonslearned" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['LessonsLearned']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu =='Closed')?'active':''; ?>">
                            <a href="../incidents/index.php?menu=closed" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Closed']);?></span>
                            </a>
                        </li>
    <?php
            }
            // IM Configure has moved into the Settings Hub at /admin/index.php;
            // the tile links to admin/incidentmanagement.php, which renders
            // the Settings / Add and Remove Values / Playbooks / Notifications
            // tabs inline.
    ?>
                    </ul>
                </li>
    <?php
        }
        if (check_permission("ai_access")) {
            // Build the visible AI submenus from capability state.
            $ai_submenus = [];
            if (ai_capability_enabled('grc_recommendations')) {
                $ai_submenus[] = [
                    'submenu' => 'Recommendations', 'href' => '../artificial_intelligence/index.php',
                    'label' => $lang['Recommendations'],
                ];
            }
            if (ai_capability_enabled('document_templates')) {
                $ai_submenus[] = [
                    'submenu' => 'DocumentInstallation', 'href' => '../artificial_intelligence/documentation.php',
                    'label' => $lang['DocumentInstallation'],
                ];
            }
            // Only render the top-level menu when it has at least one submenu.
            if (!empty($ai_submenus)) {
    ?>
                <li class="sidebar-item <?= ($active_sidebar_menu == "ArtificialIntelligence") ? 'selected' : ''; ?>">
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu == 'ArtificialIntelligence') ? 'active' : ''; ?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="sr-nav-ico"><i class="fas fa-robot"></i></span>
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['AI']); ?></span>
                    </a>
                    <ul aria-expanded="false" class="collapse first-level <?= in_array($active_sidebar_submenu, ['Recommendations', 'DocumentInstallation']) ? 'in' : ''; ?>">
    <?php
                foreach ($ai_submenus as $sm) {
    ?>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == $sm['submenu']) ? 'active' : ''; ?>">
                            <a href="<?= $escaper->escapeHtmlAttr($sm['href']); ?>" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($sm['label']); ?></span>
                            </a>
                        </li>
    <?php
                }
    ?>
                    </ul>
                </li>
    <?php
            }
        }
    ?>
                <li class="sidebar-item <?= ($active_sidebar_menu =="Assessments")?'selected':''; ?>">
    <?php
        // If the user has assessments permissions
        if (isset($_SESSION["assessments"]) && $_SESSION["assessments"] == "1") {
    ?>
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu =='Assessments')?'active':''; ?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="sr-nav-ico"><i class="fas fa-list-check"></i></span>
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
<?php
    // Reporting top-level menu — shown to users with any of the four
    // module permissions. The two leaf links go to the Reports and
    // Dashboards hubs which fetch the per-user-filtered catalog via
    // GET /api/v2/reports/catalog and render tiles client-side.
    //
    // view_exception is included alongside the four: reports/index.php's own
    // hub_required gate (kept in lockstep with this condition) now accepts
    // it too, because the connectivity_visualizer catalog tile is reachable
    // by a view_exception-only caller. Without it here, that caller would
    // never see this menu at all and so could never navigate to the tile
    // they are entitled to see.
    if (!empty($_SESSION['riskmanagement']) || !empty($_SESSION['compliance']) ||
        !empty($_SESSION['governance']) || !empty($_SESSION['asset']) ||
        !empty($_SESSION['view_exception']) ||
        (incident_management_extra() && !empty($_SESSION['im_reporting']))) {
?>
                <li class="sidebar-item <?= ($active_sidebar_menu == 'Reporting') ? 'selected' : ''; ?>">
                    <a class="sidebar-link has-arrow waves-effect waves-dark <?= ($active_sidebar_menu == 'Reporting') ? 'active' : ''; ?>" href="javascript:void(0)" aria-expanded="false">
                        <span class="sr-nav-ico"><i class="fas fa-chart-column"></i></span>
                        <span class="hide-menu"><?= $escaper->escapeHtml($lang['Reporting']);?></span>
                    </a>
                    <ul aria-expanded="false" class="collapse first-level <?= ($active_sidebar_menu == 'Reporting') ? 'in' : ''; ?>">
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'Reporting_Dashboards') ? 'active' : ''; ?>">
                            <a href="../reports/dashboards.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Dashboards']);?></span>
                            </a>
                        </li>
                        <li class="sidebar-item <?= ($active_sidebar_submenu == 'Reporting_Reports') ? 'active' : ''; ?>">
                            <a href="../reports/index.php" class="sidebar-link">
                                <span class="hide-menu"><?= $escaper->escapeHtml($lang['Reports']);?></span>
                            </a>
                        </li>
                    </ul>
                </li>
<?php
    }
?>
            </ul>
        </nav>
        <!-- End Sidebar navigation -->
    </div>
    <!-- End Sidebar scroll-->
    <!-- App footer (copyright) pinned to the bottom of the sidebar (charcoal).
         Hidden outright in the rail and hidden states, and auto-hidden when the nav
         would otherwise collide with it (see updateFooterFit() in app-shell.js). -->
    <div class="sr-sidebar-footer">
        <div class="sr-sf-copy"><?= $escaper->escapeHtml(sprintf($lang['FooterCopyright'], date('Y'))) ?></div>
    </div>
</aside>
<!-- End Left Sidebar - style you can find in sidebar.scss  -->

<?php
    // Detect whether the current page is a Settings Hub destination so the
    // breadcrumb can show "Settings > <tile>" (and link back to the Hub).
    // The helper is decoupled from rendering: it returns label keys; this
    // file resolves them through $lang before emitting them.
    require_once(realpath(__DIR__ . '/includes/settings_catalog.php'));
    $hub_current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $hub_current_menu   = isset($_GET['menu']) ? (string)$_GET['menu'] : null;
    $hub_breadcrumb     = find_settings_hub_breadcrumb($hub_current_script, $hub_current_menu);

    // Settings Hub destinations get the page title + breadcrumb populated
    // automatically from the catalog: if the destination admin page didn't
    // pass a $breadcrumb_title_key (many older admin pages don't), use the
    // matched catalog tile's leaf label as the H4 page title. The
    // right-aligned "Settings > Tile Name" breadcrumb is then triggered
    // by the existing !empty($breadcrumb_title_key) gate below.
    if ($hub_breadcrumb !== null && empty($breadcrumb_title_key)) {
        $breadcrumb_title_key = $hub_breadcrumb['leaf_label'];
    }
?>
<!-- ============================================================== -->
<!-- Page wrapper  -->
<div class="page-wrapper">
    <div class="scroll-content">
        <div class="content-wrapper">
            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12">
                        <div class="page-head d-flex align-items-center">
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
                        <!-- Right-aligned slot for a page-level primary action; the dashboard
                             "Edit layout" control hoists itself here on load. Empty otherwise. -->
                        <div class="page-actions ms-auto"></div>
                        </div><!-- /.page-head -->
    <?php
        if (!empty($breadcrumb_title_key)) {
    ?>
                        <div class="page-subtitle">
    <?php
            if ($hub_breadcrumb !== null) {
                // Configure Hub destination — render "Settings > [Sub-hub heading >] Tile Name".
                // The first segment always links to /admin/index.php; for IM sub-hub
                // destinations, the middle segment links to the bookmarkable
                // ?section=incident_management URL.
    ?>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item">
                                        <a href="../admin/index.php"><?= $escaper->escapeHtml($lang['Settings']) ?></a>
                                    </li>
    <?php           if (isset($hub_breadcrumb['sub_hub'])): ?>
                                    <li class="breadcrumb-item">
                                        <a href="<?= $escaper->escapeHtmlAttr('../admin/index.php?section=' . $hub_breadcrumb['sub_hub']['section_key']) ?>">
                                            <?= $escaper->escapeHtml($lang[$hub_breadcrumb['sub_hub']['heading_lang_key']] ?? $hub_breadcrumb['sub_hub']['heading_lang_key']) ?>
                                        </a>
                                    </li>
    <?php           endif; ?>
                                    <li class="breadcrumb-item active" aria-current="page">
                                        <?= $escaper->escapeHtml($lang[$hub_breadcrumb['leaf_label']] ?? $hub_breadcrumb['leaf_label']) ?>
                                    </li>
                                </ol>
                            </nav>
    <?php
            } elseif (!empty($active_sidebar_menu) && !empty($active_sidebar_submenu)) {
    ?>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item menu"></li>
                                    <li class="breadcrumb-item submenu"><a href="#"></a></li>
                                    <li class="breadcrumb-item thirdmenu d-none"><a href="#"></a></li>
    <?php
        // Render the final title crumb ONLY when it adds a level beyond the
        // submenu. A section-landing page (e.g. Compliance > Define Tests, or the
        // Incident Management list views) sets $breadcrumb_title_key equal to
        // $active_sidebar_submenu because the submenu IS the current page -- in
        // that case the submenu crumb above is already the leaf, so appending the
        // title here would duplicate it ("... > Define Tests > Define Tests").
        // Leaf pages set a distinct title (submenu "PastAudits" > title "ViewTest")
        // and still render the third crumb as before.
        if ($active_sidebar_submenu !== $breadcrumb_title_key) {
    ?>
                                    <li class="breadcrumb-item">
                                        <!-- according to the existence of '$lang[$breadcrumb_title_key]'. -->
                                        <?=$escaper->escapeHtml($lang[$breadcrumb_title_key] ?? $breadcrumb_title_key)?>
                                    </li>
    <?php
        }
    ?>
                                </ol>
                            </nav>
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
                if (<?= !empty($active_sidebar_forthmenu) ? 'true' : 'false' ?>) {
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
    ?>
                        </div><!-- /.page-subtitle -->
    <?php
        }
    ?>
                    </div>
                </div>
            </div>
            <!-- container - It's the direct container of all the -->
            <div class="content container-fluid">