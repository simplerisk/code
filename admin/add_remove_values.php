<div class="row-fluid">
<div class="span12">
  <div class="hero-unit">
    <form name="category" method="post" action="">
    <p>
    <h4><?php echo $lang['Category']; ?>:</h4>
    <?php echo $lang['AddNewCategoryNamed']; ?> <input name="new_category" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_category" /><br />
    <?php echo $lang['DeleteCurrentCategoryNamed']; ?> <?php create_dropdown("category"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_category" />
    </p>
    </form>
  </div>
  <div class="hero-unit">
    <form name="team" method="post" action="">
    <p>
    <h4><?php echo $lang['Team']; ?>:</h4>
    <?php echo $lang['AddNewTeamNamed']; ?> <input name="new_team" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_team" /><br />
    <?php echo $lang['DeleteCurrentTeamNamed']; ?> <?php create_dropdown("team"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_team" />
    </p>
    </form>
  </div>
  <div class="hero-unit">
    <form name="technology" method="post" action="">
    <p>
    <h4><?php echo $lang['Technology']; ?>:</h4>
    <?php echo $lang['AddNewTechnologyNamed']; ?> <input name="new_technology" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_technology" /><br />
    <?php echo $lang['DeleteCurrentTechnologyNamed']; ?> <?php create_dropdown("technology"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_technology" />
    </p>
    </form>
  </div>
  <div class="hero-unit">
    <form name="location" method="post" action="">
    <p>
    <h4><?php echo $lang['SiteLocation']; ?>:</h4>
    <?php echo $lang['AddNewSiteLocationNamed']; ?> <input name="new_location" type="text" maxlength="100" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_location" /><br />
    <?php echo $lang['DeleteCurrentSiteLocationNamed']; ?> <?php create_dropdown("location"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_location" />
    </p>
    </form>
  </div>
  <div class="hero-unit">
    <form name="regulation" method="post" action="">
    <p>
    <h4><?php echo $lang['ControlRegulation']; ?>:</h4>
    <?php echo $lang['AddNewControlRegulationNamed']; ?> <input name="new_regulation" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_regulation" /><br />
    <?php echo $lang['DeleteCurrentControlRegulationNamed']; ?> <?php create_dropdown("regulation"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_regulation" />
    </p>
    </form>
  </div>
  <div class="hero-unit">
    <form name="planning_strategy" method="post" action="">
    <p>
    <h4><?php echo $lang['RiskPlanningStrategy']; ?>:</h4>
    <?php echo $lang['AddNewRiskPlanningStrategyNamed']; ?> <input name="new_planning_strategy" type="text" maxlength="20" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_planning_strategy" /><br />
    <?php echo $lang['DeleteCurrentRiskPlanningStrategyNamed']; ?> <?php create_dropdown("planning_strategy"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_planning_strategy" />
    </p>
    </form>
  </div>
  <div class="hero-unit">
    <form name="close_reason" method="post" action="">
    <p>
    <h4><?php echo $lang['CloseReason']; ?>:</h4>
    <?php echo $lang['AddNewCloseReasonNamed']; ?> <input name="new_close_reason" type="text" maxlength="20" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_close_reason" /><br />
    <?php echo $lang['DeleteCurrentCloseReasonNamed']; ?> <?php create_dropdown("close_reason"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_close_reason" />
    </p>
    </form>
  </div>
</div>
</div>
        
