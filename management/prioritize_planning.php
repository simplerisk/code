<div class="row-fluid">
  <div class="span12">
    <div class="hero-unit">
      <h4>1) <?php echo $lang['AddAndRemoveProjects']; ?></h4>
      <p><?php echo $lang['AddAndRemoveProjectsHelp']; ?>.</p>
      <form name="project" method="post" action="">
      <p>
      <?php echo $lang['AddNewProjectNamed']; ?> <input name="new_project" type="text" maxlength="100" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_project" /><br />
      <?php echo $lang['DeleteCurrentProjectNamed']; ?> <?php create_dropdown("projects"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_project" />
      </p>
      </form>
    </div>
    <div class="hero-unit">
      <h4>2) <?php echo $lang['AddUnassignedRisksToProjects']; ?></h4>
      <p><?php echo $lang['AddUnassignedRisksToProjectsHelp']; ?>.</p>
      <?php get_project_tabs() ?>
    </div>
    <div class="hero-unit">
      <h4>3) <?php echo $lang['PrioritizeProjects']; ?></h4>
      <p><?php echo $lang['PrioritizeProjectsHelp']; ?>.</p>
      <?php get_project_list(); ?>
    </div>
    <div class="hero-unit">
      <h4>4) <?php echo $lang['DetermineProjectStatus']; ?></h4>
      <p><?php echo $lang['ProjectStatusHelp']; ?></p>
      <?php get_project_status(); ?>
    </div>
  </div>
</div>
        