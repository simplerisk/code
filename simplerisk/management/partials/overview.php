<?php
// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
{
  header("Location: ../../index.php");
  exit(0);
}

// Enforce that the user has access to risk management
enforce_permission("riskmanagement");

?>
<div class="risk-session overview clearfix">
    <div class="row">
        <div class="col-12">
            <?php view_top_table($id, $calculated_risk, $subject, $status, true, $mitigation_percent, $display_risk); ?>
        </div>
    </div>
    <?php
        // The "View Risk Scoring Details" (score.php) and "Show Risk Score
        // Over Time" (score-overtime.php, removed earlier) expanders that
        // used to render here in a shared accordion wrapper are both
        // retired -- their functionality (per-method score breakdown with
        // formula captions, method switching, and the inherent/residual
        // history chart) now lives inline in the Details tab's Scoring
        // card (risk-details-view.js's renderCards()/buildScoringMethod*/
        // buildScoringHistoryWidget()). See score.php's own removal
        // commit for the gap analysis that cleared it for removal.
    ?>
</div>