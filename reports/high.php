<div class="row-fluid">
  <div class="span6">
    <div class="well">
      <?php
              $open = get_open_risks();
              $high = get_high_risks();
              
              // If there are not 0 open risks
              if ($open != 0)
              {
                      $percent = 100*($high/$open);
              }
              else $percent = 0;
      ?>
      <h3><?php echo $lang['TotalOpenRisks']; ?>: <?php echo $open; ?></h3>
      <h3><?php echo $lang['TotalHighRisks']; ?>: <?php echo $high; ?></h3>
      <h3><?php echo $lang['HighRiskPercentage']; ?>: <?php echo round($percent, 2); ?>%</h3>
    </div>
  </div>
  <div class="span6">
    <div class="well">
      <?php open_risk_level_pie($lang['RiskLevel']); ?>
    </div>
  </div>
</div>
<?php get_risk_table(20); ?>
        
