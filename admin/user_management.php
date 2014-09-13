<div class="row-fluid">
  <div class="span12">
    <div class="hero-unit">
      <form name="add_user" method="post" action="">
      <p>
      <h4><?php echo $lang['AddANewUser']; ?>:</h4>
<?php echo $lang['Type']; ?>: <select name="type" id="select" onChange="handleSelection(value)">
      <option selected value="1">SimpleRisk</option>
<?php
// If the custom authentication extra is enabeld
if (custom_authentication_extra())
{
// Display the LDAP option
echo "<option value=\"2\">LDAP</option>\n";
}
?>
      </select><br />
      <?php echo $lang['FullName']; ?>: <input name="name" type="text" maxlength="50" size="20" /><br />
      <?php echo $lang['EmailAddress']; ?>: <input name="email" type="text" maxlength="200" size="20" /><br />
      <?php echo $lang['Username']; ?>: <input name="new_user" type="text" maxlength="20" size="20" /><br />
<div id="simplerisk">
      <?php echo $lang['Password']; ?>: <input name="password" type="password" maxlength="50" size="20" autocomplete="off" /><br />
      <?php echo $lang['RepeatPassword']; ?>: <input name="repeat_password" type="password" maxlength="50" size="20" autocomplete="off" /><br />
</div>
      <h6><u><?php echo $lang['Teams']; ?></u></h6>
      <?php create_multiple_dropdown("team"); ?>
      <h6><u><?php echo $lang['UserResponsibilities']; ?></u></h6>
      <ul>
        <li><input name="submit_risks" type="checkbox" />&nbsp;<?php echo $lang['AbleToSubmitNewRisks']; ?></li>
        <li><input name="modify_risks" type="checkbox" />&nbsp;<?php echo $lang['AbleToModifyExistingRisks']; ?></li>
        <li><input name="close_risks" type="checkbox" />&nbsp;<?php echo $lang['AbleToCloseRisks']; ?></li>
        <li><input name="plan_mitigations" type="checkbox" />&nbsp;<?php echo $lang['AbleToPlanMitigations']; ?></li>
        <li><input name="review_low" type="checkbox" />&nbsp;<?php echo $lang['AbleToReviewLowRisks']; ?></li>
        <li><input name="review_medium" type="checkbox" />&nbsp;<?php echo $lang['AbleToReviewMediumRisks']; ?></li>
        <li><input name="review_high" type="checkbox" />&nbsp;<?php echo $lang['AbleToReviewHighRisks']; ?></li>
        <li><input name="admin" type="checkbox" />&nbsp;<?php echo $lang['AllowAccessToConfigureMenu']; ?></li>
      </ul>
      <h6><u><?php echo $lang['MultiFactorAuthentication']; ?></u></h6>
      <input type="radio" name="multi_factor" value="1" checked />&nbsp;<?php echo $lang['None']; ?><br />
<?php
// If the custom authentication extra is installed
if (custom_authentication_extra())
{
      // Include the custom authentication extra
      require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

      // Display the multi factor authentication options
      multi_factor_authentication_options(1);
}
?>
      <input type="submit" value="<?php echo $lang['Add']; ?>" name="add_user" /><br />
      </p>
      </form>
    </div>
    <div class="hero-unit">
      <form name="select_user" method="post" action="index.php?module=3&page=9">
      <p>
      <h4><?php echo $lang['ViewDetailsForUser']; ?>:</h4>
      <?php echo $lang['DetailsForUser']; ?> <?php create_dropdown("user"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Select']; ?>" name="select_user" />
      </p>
      </form>
    </div>
    <div class="hero-unit">
      <form name="enable_disable_user" method="post" action="">
      <p>
      <h4><?php echo $lang['EnableAndDisableUsers']; ?>:</h4>
<?php echo $lang['EnableAndDisableUsersHelp']; ?>.
</p>
<p>
      <?php echo $lang['DisableUser']; ?> <?php create_dropdown("enabled_users"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Disable']; ?>" name="disable_user" />
      </p>
      <p>
      <?php echo $lang['EnableUser']; ?> <?php create_dropdown("disabled_users"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Enable']; ?>" name="enable_user" />
      </p>
      </form>
    </div>
    <div class="hero-unit">
      <form name="delete_user" method="post" action="">
      <p>
      <h4><?php echo $lang['DeleteAnExistingUser']; ?>:</h4>
      <?php echo $lang['DeleteCurrentUser']; ?> <?php create_dropdown("user"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_user" />
      </p>
      </form>
    </div>
    <div class="hero-unit">
      <form name="password_reset" method="post" action="">
      <p>
      <h4><?php echo $lang['PasswordReset']; ?>:</h4>
      <?php echo $lang['SendPasswordResetEmailForUser']; ?> <?php create_dropdown("user"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Send']; ?>" name="password_reset" />
      </p>
      </form>
    </div>
  </div>
</div>
       