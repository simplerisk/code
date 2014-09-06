<div class="row-fluid">
  <div class="span12">
    <div class="hero-unit">
      <form name="impact" method="post" action="">
      <p>
      <h4><?php echo $lang['Impact']; ?>:</h4>
      <?php echo $lang['Change']; ?> <?php create_dropdown("impact") ?> <?php echo $lang['to']; ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Update']; ?>" name="update_impact" /></p>
      </form>
    </div>
    <div class="hero-unit">
      <form name="likelihood" method="post" action="">
      <p>
      <h4><?php echo $lang['Likelihood']; ?>:</h4>
      <?php echo $lang['Change']; ?> <?php create_dropdown("likelihood") ?> <?php echo $lang['to']; ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Update']; ?>" name="update_likelihood" /></p>
      </form>
    </div>
    <div class="hero-unit">
      <form name="mitigation_effort" method="post" action="">
      <p>
      <h4><?php echo $lang['MitigationEffort']; ?>:</h4>
      <?php echo $lang['Change']; ?> <?php create_dropdown("mitigation_effort") ?> <?php echo $lang['to']; ?> <input name="new_name" type="text" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Update']; ?>" name="update_mitigation_effort" /></p>
      </form>
    </div>
  </div>
</div>
        
