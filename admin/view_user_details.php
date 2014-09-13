          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="update_user" method="post" action="">
                <p>
                <h4>Update an Existing User:</h4>
                <input name="user" type="hidden" value="<?php echo $user_id; ?>" />
		<?php echo $lang['Type']; ?>: <input style="cursor: default;" name="type" type="text" maxlength="20" size="20" title="<?php echo $type; ?>" disabled="disabled" value="<?php echo $type; ?>" /><br />
                <?php echo $lang['FullName']; ?>: <input name="name" type="text" maxlength="50" size="20" value="<?php echo htmlentities($name, ENT_QUOTES, 'UTF-8', false); ?>" /><br />
                <?php echo $lang['EmailAddress']; ?>: <input name="email" type="text" maxlength="200" size="20" value="<?php echo htmlentities($email, ENT_QUOTES, 'UTF-8', false); ?>" /><br />
                <?php echo $lang['Username']; ?>: <input style="cursor: default;" name="username" type="text" maxlength="20" size="20" title="<?php echo htmlentities($username, ENT_QUOTES, 'UTF-8', false); ?>" disabled="disabled" value="<?php echo htmlentities($username, ENT_QUOTES, 'UTF-8', false); ?>" /><br />
		<?php echo $lang['LastLogin']; ?>: <input style="cursor: default;" name="last_login" type="text" maxlength="20" size="20" title="<?php echo $last_login; ?>" disabled="disabled" value="<?php echo $last_login; ?>" /><br />
                <?php echo $lang['Language']; ?>: <?php create_dropdown("languages", get_value_by_name("languages", $language)); ?>
                <h6><u><?php echo $lang['Teams']; ?></u></h6>
                <?php create_multiple_dropdown("team", $teams); ?>
                <h6><u><?php echo $lang['UserResponsibilities']; ?></u></h6>
                <ul>
                  <li><input name="submit_risks" type="checkbox"<?php if ($submit_risks) echo " checked" ?> />&nbsp;<?php echo $lang['AbleToSubmitNewRisks']; ?></li>
                  <li><input name="modify_risks" type="checkbox"<?php if ($modify_risks) echo " checked" ?> />&nbsp;<?php echo $lang['AbleToModifyExistingRisks']; ?></li>
                  <li><input name="close_risks" type="checkbox"<?php if ($close_risks) echo " checked" ?> />&nbsp;<?php echo $lang['AbleToCloseRisks']; ?></li>
                  <li><input name="plan_mitigations" type="checkbox"<?php if ($plan_mitigations) echo " checked" ?> />&nbsp;<?php echo $lang['AbleToPlanMitigations']; ?></li>
                  <li><input name="review_low" type="checkbox"<?php if ($review_low) echo " checked" ?> />&nbsp;<?php echo $lang['AbleToReviewLowRisks']; ?></li>
                  <li><input name="review_medium" type="checkbox"<?php if ($review_medium) echo " checked" ?> />&nbsp;<?php echo $lang['AbleToReviewMediumRisks']; ?></li>
                  <li><input name="review_high" type="checkbox"<?php if ($review_high) echo " checked" ?> />&nbsp;<?php echo $lang['AbleToReviewHighRisks']; ?></li>
                  <li><input name="admin" type="checkbox"<?php if ($admin) echo " checked" ?> />&nbsp;<?php echo $lang['AllowAccessToConfigureMenu']; ?></li>
                </ul>
                <h6><u><?php echo $lang['MultiFactorAuthentication']; ?></u></h6>
                <input type="radio" name="multi_factor" value="1"<?php if ($multi_factor == 1) echo " checked" ?> />&nbsp;<?php echo $lang['None']; ?><br />
<?php
	// If the custom authentication extra is installed
	if (custom_authentication_extra())
	{
                // Include the custom authentication extra
                require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

		// Display the multi factor authentication options
		multi_factor_authentication_options($multi_factor);
	}
?>
                <input type="submit" value="<?php echo $lang['Update']; ?>" name="update_user" /><br />
                </p>
                </form>
              </div>
            </div>
          </div>
        