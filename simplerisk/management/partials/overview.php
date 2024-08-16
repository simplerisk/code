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
    <div class="card-body my-2 border">
        <div class="row">
            <div class="col-12">
                <?php view_top_table($id, $calculated_risk, $subject, $status, true, $mitigation_percent, $display_risk); ?>
            </div>
        </div>
    </div>
    <div class="row mt-2">
        <div class="col-12">
            <div class="accordion">
    <?php
                // Risk soring form
                include(realpath(__DIR__ . '/score.php'));

                // Show visualization of risk score
                include(realpath(__DIR__ . '/score-overtime.php'));
    ?>
            </div>
        </div>
    </div>
</div>