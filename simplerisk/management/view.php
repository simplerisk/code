<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));

// Fetched BEFORE render_header_and_sidebar() (below) rather than where the
// pre-existing "if (isset($_GET['id']))" block used to do it further down --
// the breadcrumb leaf and page heading now show the risk's own name + ID
// (design-system.md §11's Record header pattern: "breadcrumb, name + ID")
// instead of a static "Risk Details" label, and render_header_and_sidebar()
// consumes $breadcrumb_title_key immediately. $id/$access/$risk computed
// here are the SAME variables the rest of this page already needed -- see
// the simplified "if (count($risk) != 0)" block below, which reuses them
// instead of re-running this exact query a second time.
$id = 0;
$access = false;
$risk = [];
if (isset($_GET['id']))
{
    // Test that the ID is a numeric value
    $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);

    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Do not allow the user to update the risk unless access is granted
        $access = extra_grant_access($_SESSION['uid'], $id);
    }
    // Otherwise, allow the user to update the risk
    else $access = true;

    // Get the details of the risk
    $risk = get_risk_by_id($id);
}

$breadcrumb_title_key = "RiskDetails";
// A bad/stale id (no matching risk row -- the same condition that drives
// $display_risk to false further down, see its own "risk not found"
// branch) shows in the title/breadcrumb too, not just the page body --
// sidebar.php resolves this exactly like the default above, as a $lang[]
// key name, falling back to the raw string if the key doesn't exist.
if (isset($_GET['id']) && count($risk) == 0) {
    $breadcrumb_title_key = "RiskIdDoesNotExist";
}
// Truncated to a length that reliably fits the breadcrumb strip -- the page
// heading (h4.page-title) shows the exact same string (both read from this
// one variable, sidebar.php), so this is a shared compromise rather than a
// breadcrumb-only truncation: long enough that most real subjects never hit
// it, short enough that the ones that do don't overflow the breadcrumb.
// try_decrypt() -- $risk[0]['subject'] is ciphertext when the Encrypted
// Database Extra is active (the same reason view_top_table(), includes/
// display.php, decrypts its own $subject parameter before ever displaying
// it); the later $subject = $risk[0]['subject'] assignment below only looks
// safe because IT never renders subject directly either -- every one of its
// own consumers decrypts it independently at their own point of use.
//
// Gated on $access, not just count($risk): get_risk_by_id() fetches the row
// unconditionally, before the Team Separation Extra's own
// extra_grant_access() check above has any say -- a user denied access to
// THIS risk (not on an assigned team) would otherwise still see its Subject
// here even though the Cards system's own /ui/risk/values endpoint
// (check_access_for_risk(), api/v2/includes/api.php) correctly refuses them
// the rest of the page. Same gate $mitigation/$mgmt_reviews already use
// below for the identical reason.
if (count($risk) != 0 && $access) {
    $breadcrumb_title_key = truncate_to(try_decrypt($risk[0]['subject']), 50) . " (#{$id})";
}

$active_sidebar_menu ="RiskManagement";
// 'ReviewRisk' -- the real sidebar submenu identifier sidebar.php's own
// li checks against ($active_sidebar_submenu == 'ReviewRisk') and links to
// (management/review_risk.php) -- NOT 'ReviewRisksRegularly', which matches
// no sidebar item at all. Since nothing matched, sidebar.php's own
// unmatched-submenu fallback rendered the breadcrumb's middle segment from
// a raw $lang['ReviewRisksRegularly'] lookup ("Review Regularly") instead
// of the real "Review Risk" sidebar link text, and left that sidebar item
// itself unhighlighted while viewing a risk. management_review()/
// planned_mitigation() (includes/functions.php) build ?active= links to
// this page with the same corrected default, for the same reason.
$active_sidebar_submenu = isset($_GET['active']) ? $_GET['active'] : "ReviewRisk";
// CUSTOM:common/classic-scoring.js MUST precede CUSTOM:common/
// risk-details-form.js for the same reason as CVSS/DREAD/OWASP's own
// scoring scripts: the RiskScoringMethod widget's Classic holder (Risk
// Scoring -- Classic Inline) calls window.ClassicScoring.calculateClassic()
// at BUILD time, the moment the holder is constructed.
// CUSTOM:common/contributing-risk-scoring.js MUST precede CUSTOM:common/
// risk-details-form.js for the same reason as Classic/CVSS/DREAD/OWASP's
// own scoring scripts: the RiskScoringMethod widget's Contributing Risk
// holder calls window.ContributingRiskScoring.calculateContributingRisk()
// at BUILD time, the moment the holder is constructed.
// CUSTOM:sr-select.js/CUSTOM:sr-audit-trail.js MUST precede CUSTOM:pages/
// risk-audit-trail.js: the former defines window.srSelectEnhance and the
// createAuditTrail() factory that file's own top-level `var RiskAuditTrail =
// createAuditTrail({...})` calls immediately on load (same load-order
// requirement governance/document_exceptions.php's identical script list
// already establishes for Define Exceptions' own audit trail).
// CUSTOM:sr-row-actions-menu.js: the row-actions overflow disclosure
// (js/simplerisk/sr-row-actions-menu.js) needed by the Control Validation
// table's view/edit actions (.sr-row-actions-wrap/.sr-row-actions-toggle
// markup, _tables.scss's sr-row-actions-overflow-tier mixin) on touch/
// narrow viewports -- same token Compliance/Governance/Plan Projects'
// own row-actions-menu pages load. Placed before CUSTOM:pages/
// risk-mitigation-controls.js, which calls SRRowActionsMenu.bind().
render_header_and_sidebar(['blockUI', 'tabs:logic', 'selectize', 'datatables', 'chart.js', 'WYSIWYG', 'multiselect', 'gridstack', 'CUSTOM:common.js', 'CUSTOM:common/cvss-v2-scoring.js', 'CUSTOM:common/dread-scoring.js', 'CUSTOM:common/owasp-scoring.js', 'CUSTOM:common/classic-scoring.js', 'CUSTOM:common/contributing-risk-scoring.js', 'CUSTOM:common/risk-details-form.js', 'CUSTOM:common/risk-details-view.js', 'CUSTOM:pages/risk-view-details.js', 'CUSTOM:sr-faceted-picker.js', 'CUSTOM:sr-row-actions-menu.js', 'CUSTOM:pages/risk-mitigation-controls.js', 'CUSTOM:pages/risk-view-mitigation.js', 'CUSTOM:pages/risk-view-review.js', 'CUSTOM:pages/risk.js', 'CUSTOM:sr-select.js', 'CUSTOM:sr-audit-trail.js', 'CUSTOM:pages/risk-audit-trail.js', 'CUSTOM:cve_lookup.js', 'datetimerangepicker', 'JSLocalization', 'easyui:treegrid', 'EXTRA:JS:artificial_intelligence:ai-chat.js'], ['check_riskmanagement' => true, 'show_ai_chat' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu, required_localization_keys: ['MitigationPlanned']);

// $id/$access/$risk were already computed above (before
// render_header_and_sidebar(), which needs the risk's subject for the
// breadcrumb) -- reused here rather than re-running get_risk_by_id() a
// second time for the exact same id.
// If the risk was found use the values for the risk
if (count($risk) != 0)
{
    $submitted_by = $risk[0]['submitted_by'];
    $status = $risk[0]['status'];
    $subject = $risk[0]['subject'];
    $reference_id = $risk[0]['reference_id'];
    $regulation = $risk[0]['regulation'];
    $control_number = $risk[0]['control_number'];
    $location = $risk[0]['location'];
    $source = $risk[0]['source'];
    $category = $risk[0]['category'];
    $team = $risk[0]['team'];
    $additional_stakeholders = $risk[0]['additional_stakeholders'];
    $technology = $risk[0]['technology'];
    $owner = $risk[0]['owner'];
    $manager = $risk[0]['manager'];
    $assessment = $risk[0]['assessment'];
    $notes = $risk[0]['notes'];
    $jira_issue_key = jira_extra() ? $risk[0]['jira_issue_key'] : "";
    $submission_date = $risk[0]['submission_date'];
    $risk_tags = $risk[0]['risk_tags'];
    $mitigation_id = $risk[0]['mitigation_id'];
    $mgmt_review = $risk[0]['mgmt_review'];
    $calculated_risk = $risk[0]['calculated_risk'];
    $residual_risk = $risk[0]['residual_risk'];
    $next_review = $risk[0]['next_review'];
    $color = get_risk_color($calculated_risk);
    $residual_color = get_risk_color($residual_risk);
    $risk_level = get_risk_level_name($calculated_risk);
    $residual_risk_level = get_risk_level_name($residual_risk);
    $scoring_method = $risk[0]['scoring_method'];
    $CLASSIC_likelihood = $risk[0]['CLASSIC_likelihood'];
    $CLASSIC_impact = $risk[0]['CLASSIC_impact'];
    $AccessVector = $risk[0]['CVSS_AccessVector'];
    $AccessComplexity = $risk[0]['CVSS_AccessComplexity'];
    $Authentication = $risk[0]['CVSS_Authentication'];
    $ConfImpact = $risk[0]['CVSS_ConfImpact'];
    $IntegImpact = $risk[0]['CVSS_IntegImpact'];
    $AvailImpact = $risk[0]['CVSS_AvailImpact'];
    $Exploitability = $risk[0]['CVSS_Exploitability'];
    $RemediationLevel = $risk[0]['CVSS_RemediationLevel'];
    $ReportConfidence = $risk[0]['CVSS_ReportConfidence'];
    $CollateralDamagePotential = $risk[0]['CVSS_CollateralDamagePotential'];
    $TargetDistribution = $risk[0]['CVSS_TargetDistribution'];
    $ConfidentialityRequirement = $risk[0]['CVSS_ConfidentialityRequirement'];
    $IntegrityRequirement = $risk[0]['CVSS_IntegrityRequirement'];
    $AvailabilityRequirement = $risk[0]['CVSS_AvailabilityRequirement'];
    $DREADDamagePotential = $risk[0]['DREAD_DamagePotential'];
    $DREADReproducibility = $risk[0]['DREAD_Reproducibility'];
    $DREADExploitability = $risk[0]['DREAD_Exploitability'];
    $DREADAffectedUsers = $risk[0]['DREAD_AffectedUsers'];
    $DREADDiscoverability = $risk[0]['DREAD_Discoverability'];
    $OWASPSkillLevel = $risk[0]['OWASP_SkillLevel'];
    $OWASPMotive = $risk[0]['OWASP_Motive'];
    $OWASPOpportunity = $risk[0]['OWASP_Opportunity'];
    $OWASPSize = $risk[0]['OWASP_Size'];
    $OWASPEaseOfDiscovery = $risk[0]['OWASP_EaseOfDiscovery'];
    $OWASPEaseOfExploit = $risk[0]['OWASP_EaseOfExploit'];
    $OWASPAwareness = $risk[0]['OWASP_Awareness'];
    $OWASPIntrusionDetection = $risk[0]['OWASP_IntrusionDetection'];
    $OWASPLossOfConfidentiality = $risk[0]['OWASP_LossOfConfidentiality'];
    $OWASPLossOfIntegrity = $risk[0]['OWASP_LossOfIntegrity'];
    $OWASPLossOfAvailability = $risk[0]['OWASP_LossOfAvailability'];
    $OWASPLossOfAccountability = $risk[0]['OWASP_LossOfAccountability'];
    $OWASPFinancialDamage = $risk[0]['OWASP_FinancialDamage'];
    $OWASPReputationDamage = $risk[0]['OWASP_ReputationDamage'];
    $OWASPNonCompliance = $risk[0]['OWASP_NonCompliance'];
    $OWASPPrivacyViolation = $risk[0]['OWASP_PrivacyViolation'];
    $custom = $risk[0]['Custom'];
    $risk_catalog_mapping = $risk[0]['risk_catalog_mapping'];
    $threat_catalog_mapping = $risk[0]['threat_catalog_mapping'];
    $template_group_id  = $risk[0]['template_group_id'];

    $ContributingLikelihood = $risk[0]['Contributing_Likelihood'];
    $contributing_risks_impacts = $risk[0]['Contributing_Risks_Impacts'];
    if($contributing_risks_impacts){
        $ContributingImpacts = get_contributing_impacts_by_subjectimpact_values($contributing_risks_impacts);
    }else{
        $ContributingImpacts = [];
    }
    $display_risk = true;

    $submission_date = format_date($submission_date, "N/A");

    // Get the mitigation for the risk
    $mitigation = get_mitigation_by_id($id);

    // If a mitigation exists for the risk and the user is allowed to access
    if ($mitigation == true && $access)
    {
        // Set the mitigation values
        // @phan-suppress-next-line PhanTypeMismatchDimFetch -- get_mitigation_by_id() returns array of mitigation rows when truthy
        $mitigation_id = $mitigation[0]['mitigation_id'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_date = $mitigation[0]['submission_date'];
        $mitigation_date = format_date($mitigation_date);
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $planning_strategy = $mitigation[0]['planning_strategy'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_effort = $mitigation[0]['mitigation_effort'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_cost = $mitigation[0]['mitigation_cost'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_owner = $mitigation[0]['mitigation_owner'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_team = $mitigation[0]['mitigation_team'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $current_solution = $mitigation[0]['current_solution'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $security_requirements = $mitigation[0]['security_requirements'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $security_recommendations = $mitigation[0]['security_recommendations'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $planning_date = format_date($mitigation[0]['planning_date']);
        $mitigation_percent = (isset($mitigation[0]['mitigation_percent']) && $mitigation[0]['mitigation_percent'] >= 0 && $mitigation[0]['mitigation_percent'] <= 100) ? $mitigation[0]['mitigation_percent'] : 0;
        $mitigation_controls = isset($mitigation[0]['mitigation_controls']) ? $mitigation[0]['mitigation_controls'] : "";
    }
    // Otherwise
    else
    {
        // Set the values to empty
        $mitigation_id = "";
        $mitigation_date = "N/A";
        $mitigation_date = "";
        $planning_strategy = "";
        $mitigation_effort = "";
        $mitigation_cost = 1;
        $mitigation_owner = $owner;
        $mitigation_team = $team;
        $current_solution = "";
        $security_requirements = "";
        $security_recommendations = "";
        $planning_date = "";
        $mitigation_percent = 0;
        $mitigation_controls = "";
    }

    // Get the management reviews for the risk
    $mgmt_reviews = get_review_by_id($id);
    // If a mitigation exists for this risk and the user is allowed to access
    if ($mgmt_reviews && $access)
    {
        // Set the mitigation values
        $review_date = $mgmt_reviews[0]['submission_date'];
        $review_date = date(get_default_datetime_format("g:i A T"), strtotime($review_date));

        $review = $mgmt_reviews[0]['review'];
        $review_id = $mgmt_reviews[0]['id'];
        $next_step = $mgmt_reviews[0]['next_step'];

        // If next_review_date_uses setting is Residual Risk.
        if(get_setting('next_review_date_uses') == "ResidualRisk")
        {
            $next_review = next_review($residual_risk_level, $id-1000, $next_review, false);
        }
        // If next_review_date_uses setting is Inherent Risk.
        else
        {
            $next_review = next_review($risk_level, $id-1000, $next_review, false);
        }

        $reviewer = $mgmt_reviews[0]['reviewer'];
        $comments = $mgmt_reviews[0]['comments'];
    }
    else
    // Otherwise
    {
        // Set the values to empty
        $review_date = "N/A";
        $review = "";
        $review_id = "";
        $next_step = "";
        $next_review = "";
        $reviewer = "";
        $comments = "";
    }
}
// If the risk was not found use null values
elseif (isset($_GET['id']))
{
    $subject = "N/A";
    $display_risk = false;
}
// if ID is not set
else
{
    $id = "";
    $subject = "N/A";
    $display_risk = false;
}

?>
<?php if (isset($id) && $id > 0): ?>
<script>window.simplerisk_current_risk_id = <?= (int)$id ?>;</script>
<?php endif; ?>
<div class="row bg-white">
    <div class="col-12">
        <div class='tab-data hide'></div>
        <div class='tab-data'>
    <?php

        $action = isset($_GET['action']) ? $_GET['action'] : "";

        if($display_risk == true)  {
            include(realpath(__DIR__ . '/partials/viewhtml.php'));
        } else {
            // Full-page "couldn't load" empty state (design-system.md §10),
            // reusing the shipped .sr-table-card/.sr-table-empty shell
            // rather than the bare, unstyled <div class='card-body border'>
            // fragment this used to be -- .sr-table-empty's own styling
            // (scss/modules/_tables.scss) is a descendant rule scoped to
            // .sr-table-card, so it needs that ancestor even outside an
            // actual table. "Couldn't load" (soft danger tint), not "no
            // data yet"/"no results": the risk genuinely failed to load
            // (deleted, or a bad/stale id), so the one action is a way
            // back to the risk list, not a create/clear-filters affordance.
            echo "
            <div class='sr-table-card sr-table-card--no-border'>
                <div class='sr-table-empty sr-table-empty-danger'>
                    <div class='sr-table-empty-icon'><i class='fa fa-triangle-exclamation' aria-hidden='true'></i></div>
                    <div class='sr-table-empty-title'>" . $escaper->escapeHtml($lang["RiskIdDoesNotExist"]) . "</div>
                    <div class='sr-table-empty-body'>" . $escaper->escapeHtml($lang["RiskIdDoesNotExistBody"]) . "</div>
                    <div class='sr-table-empty-action'><a href='review_risk.php' class='btn btn-outline-secondary btn-sm'>" . $escaper->escapeHtml($lang["ReviewRisk"]) . "</a></div>
                </div>
            </div>
            ";
        }

    ?>
        </div>
    </div>
</div>

    <?php /* AI accordion moved to management/partials/viewhtml.php */ ?>

<script>

    $(document).ready(function() {
        
        setupAssetsAssetGroupsViewWidget($('select.assets-asset-groups-select-disabled'));
        
        /**
        * Change Event of Risk Scoring Method
        * 
        */
        $('body').on('change', '[name=scoring_method]', function(e){
            e.preventDefault();
            var formContainer = $(this).parents('form');
            handleSelection($(this).val(), formContainer);
        })

        // Audit Trail (design-system.md §6/§7, js/simplerisk/pages/risk-
        // audit-trail.js) -- same init-if-present guard governance/
        // document_exceptions.php uses for window.ExceptionAuditTrail.
        // #risk-audit-trail is only ever absent when $display_risk is
        // false (management/partials/viewhtml.php).
        if ($('#risk-audit-trail').length && window.RiskAuditTrail) {
            window.RiskAuditTrail.init();
        }

        // Safety net for header.php's sitewide 'tabs:logic' script (loaded
        // earlier in the page, so its own $(function(){...}) ready handler
        // runs BEFORE this one -- jQuery fires ready callbacks in
        // registration order). That script unconditionally clears .active
        // from every nav-tabs link/tab-pane SITEWIDE on load, then restores
        // only ONE tab hierarchy: the one location.hash points into, or (no
        // hash) whichever `nav.nav-tabs` is first in DOM order. Any hash
        // that isn't empty and doesn't resolve to something inside Details/
        // Mitigation/Review's own tab structure -- a stale bookmark/shared
        // link from before Associated Exceptions' sub-tabs stopped writing
        // their own hash into the URL (management/partials/viewhtml.php),
        // or simply a URL to a page anchor that doesn't exist here -- takes
        // neither branch, so nothing ever re-activates Details/Mitigation/
        // Review and their content visibly disappears. If that's happened,
        // explicitly activate Details, the same fallback tabs:logic's own
        // "no active tab -> activate the leftmost one" logic would have
        // reached for if it had found this tab group at all.
        if (!$('.risk-details .nav-tabs .nav-link.active').length) {
            $('.risk-details .nav-tabs a[data-bs-toggle="tab"]').first().tab('show');
        }

    });
    
    $(document).click(function (event) {
        var $target = $(event.target);
        if (!$target.closest('.multiselect-native-select').find('.btn-group').length && $('.multiselect-native-select').find('.btn-group').hasClass("open")) {
              $('.multiselect-native-select').find('.btn-group').removeClass('open');
        }
    });
    $( function() {
       
        $("#comment-submit").attr('disabled','disabled');
        $("#cancel_disable").attr('disabled','disabled');
        $("#rest-btn").attr('disabled','disabled');

        $("#comment-text").click(function(){
            $("#comment-submit").removeAttr('disabled');
            $("#rest-btn").removeAttr('disabled');
        });

        $("#comment-submit").click(function(){
            var submitbutton = document.getElementById("comment-text").value;
            if(submitbutton == ''){
                $("#comment-submit").attr('disabled','disabled');
                $("#rest-btn").attr('disabled','disabled');
            }
        });

        $("#rest-btn").click(function(){
            $("#comment-submit").attr('disabled','disabled');
        });
       
        $(".active-textfield").click(function(){
            $("#cancel_disable").removeAttr('disabled');
        });
        
        $("select").change(function changeOption(){
            $("#cancel_disable").removeAttr('disabled');
        });

        $(".datepicker").initAsDatePicker();

        //render multiselects which are not rendered yet after the page is loaded.
        //multiselects which were already rendered contain 'button.multiselect'.
        $(".multiselect:not(button)").multiselect({enableFiltering: true, buttonWidth: '100%', enableCaseInsensitiveFiltering: true,});

    });
</script>
<?php  
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>