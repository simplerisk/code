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

<div class="accordion-item">
    <h2 class="accordion-header">
        <button id="show" type='button' class='accordion-button collapsed show-score-overtime' data-bs-toggle='collapse' data-bs-target='#score-overtime-container-accordion-body'><?= $escaper->escapeHtml($lang['ShowRiskScoreOverTime']); ?></button>
        <button id="hide" type='button' style="display: none;" class='accordion-button hide-score-overtime' data-bs-toggle='collapse' data-bs-target='#score-overtime-container-accordion-body'><?= $escaper->escapeHtml($lang['HideRiskScoreOverTime']); ?></button>
    </h2>
    <div id="score-overtime-container-accordion-body" class="accordion-collapse collapse">
        <div class="score-overtime-container accordion-body">
            <div class="well">
                <?php score_over_time(); ?>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="_RiskScoringHistory" value="<?php echo $escaper->escapeHtml($lang['RiskScoringHistory']); ?>">
<input type="hidden" id="_RiskScore" value="<?php echo $escaper->escapeHtml($lang['InherentRisk']); ?>">
<input type="hidden" id="_ResidualRiskScore" value="<?php echo $escaper->escapeHtml($lang['ResidualRisk']); ?>">
<input type="hidden" id="_DateAndTime" value="<?php echo $escaper->escapeHtml($lang['DateAndTime']); ?>">