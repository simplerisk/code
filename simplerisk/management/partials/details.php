<?php
// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1") {
    header("Location: ../../index.php");
    exit(0);
}

// Enforce that the user has access to risk management
enforce_permission("riskmanagement");

?>
<div class="risk-details sr-risk-view-tabs position-relative">
    <nav class="nav nav-tabs">
        <a id="tab_details" data-bs-target="#details" class="nav-link active" data-bs-toggle="tab"><?php echo $escaper->escapeHtml($lang['Details']); ?></a>
        <a id="tab_mitigation" data-bs-target="#mitigation" class="nav-link" data-bs-toggle="tab"><?php echo $escaper->escapeHtml($lang['Mitigation']); ?></a>
        <a id="tab_review" data-bs-target="#review" class="nav-link" data-bs-toggle="tab"><?php echo $escaper->escapeHtml($lang['Review']); ?></a>
    </nav>
    <div class="tab-content">

        <div id="details" class="tab-pane active clearfix">
            <input type="hidden" class="risk_id" value="<?php echo $escaper->escapeHtml($id); ?>">
            <div id="risk-view-details-mount" data-risk-id="<?php echo $escaper->escapeHtml($id); ?>"
                 data-can-edit="<?php echo has_permission('modify_risks') ? '1' : '0'; ?>"></div>
<?php
    // The mount above is rendered EMPTY -- js/simplerisk/pages/risk-view-details.js
    // fills it. On a regular page load that module's own $(document).ready hook
    // does it, so nothing is needed here.
    //
    // On the AJAX path this whole partial is re-rendered server-side and dropped
    // into the page by risk.js with `$('.content-container', tabContainer).html(...)`
    // (Edit/Cancel Mitigation, Perform/Save/Cancel Review, View All Reviews,
    // Close Risk, Change Status). That replaces the #details pane with a fresh
    // empty mount long after document-ready fired, leaving the Details tab blank
    // until a full reload. Re-invoke the coordinator here so the re-render is
    // tied to the markup itself: jQuery's .html() executes injected <script>
    // tags, and several of those handlers never call callbackAfterRefreshTab(),
    // so a hook in that function would not cover them all.
    //
    // This is the mirror image of the $isAjax guards the Mitigation and Review
    // panes below use: they re-init on the NON-ajax path because risk.js already
    // re-inits them on the ajax one.
    if (isset($isAjax) && $isAjax) {
?>
            <script>
                $(function () {
                    if (window.RiskViewDetails) {
                        window.RiskViewDetails.render();
                    }
                });
            </script>
<?php
    }
?>
        </div>

        <div id="mitigation" class="tab-pane">
            <input type="hidden" class="risk_id" value="<?php echo $escaper->escapeHtml($id); ?>">
            <div id="risk-view-mitigation-mount" data-risk-id="<?php echo $escaper->escapeHtml($id); ?>"
                 data-can-edit="<?php echo has_permission('plan_mitigations') ? '1' : '0'; ?>"></div>
<?php
    // The mount above is rendered EMPTY -- js/simplerisk/pages/risk-view-mitigation.js
    // fills it. Same $isAjax re-init guard as the #details pane above (Phase 4a) --
    // see that pane's own comment for the full reasoning: risk.js's Review/Close
    // Risk/Change Status handlers re-render this WHOLE partial server-side and drop
    // it in with $('.content-container', tabContainer).html(...), which replaces this
    // mount too and leaves it empty until a full reload without this re-invocation.
    //
    // edit_mitigation_details()/view_mitigation_details() stay in the codebase
    // (still used elsewhere), just no longer called from this pane -- this mount
    // point replaces both, the same swap Phase 4a made for #details.
    if (isset($isAjax) && $isAjax) {
?>
            <script>
                $(function () {
                    if (window.RiskViewMitigation) {
                        window.RiskViewMitigation.render();
                    }
                });
            </script>
<?php
    }
?>
        </div>
        
        <div id="review" class="tab-pane">
            <input type="hidden" class="risk_id" value="<?php echo $escaper->escapeHtml($id); ?>">
            <div id="risk-view-review-mount" data-risk-id="<?php echo $escaper->escapeHtml($id); ?>"></div>
<?php
    // No data-can-edit attribute -- unlike modify_risks/plan_mitigations,
    // "can perform a review" is RISK-INSTANCE-SPECIFIC
    // (check_review_permission_by_risk_id($id), tiered by this risk's own
    // calculated level), not a plain session permission a static PHP
    // attribute can express. The coordinator gets it from the values
    // response's can_perform_review key instead (Task 1).
    //
    // Same $isAjax re-init guard as the #details/#mitigation panes -- see
    // #details' own comment for the full reasoning (risk.js's Close
    // Risk/Change Status/View All Reviews handlers re-render this WHOLE
    // partial server-side).
    if (isset($isAjax) && $isAjax) {
?>
            <script>
                $(function () {
                    if (window.RiskViewReview) {
                        window.RiskViewReview.render();
                    }
                });
            </script>
<?php
    }
?>
        </div>
    </div>
</div>

<input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">