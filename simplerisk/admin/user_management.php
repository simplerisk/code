<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/messages.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// If we want to enable the Content Security Policy (CSP) - This may break Chrome
if (CSP_ENABLED == "true")
{
  // Add the Content-Security-Policy header
  header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
}

// Session handler is database
if (USE_DATABASE_FOR_SESSIONS == "true")
{
  session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
}

// Start the session
session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

if (!isset($_SESSION))
{
        session_name('SimpleRisk');
        session_start();
}

// Include the language file
require_once(language_file());

require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
  header("Location: ../index.php");
  exit(0);
}

// Check if access is authorized
if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
{
  header("Location: ../index.php");
  exit(0);
}

// Check if a new user was submitted
if (isset($_POST['add_user']))
{
  $type = $_POST['type'];
  $name = $_POST['name'];
  $email = $_POST['email'];
  $user = $_POST['new_user'];
  $pass = $_POST['password'];
  $repeat_pass = $_POST['repeat_password'];
  $teams = isset($_POST['team']) ? $_POST['team'] : array('none');
  $admin = isset($_POST['admin']) ? '1' : '0';
  $assessments = isset($_POST['assessments']) ? '1' : '0';
  $asset = isset($_POST['asset']) ? '1' : '0';
  $submit_risks = isset($_POST['submit_risks']) ? '1' : '0';
  $modify_risks = isset($_POST['modify_risks']) ? '1' : '0';
  $close_risks = isset($_POST['close_risks']) ? '1' : '0';
  $plan_mitigations = isset($_POST['plan_mitigations']) ? '1' : '0';
  $review_veryhigh = isset($_POST['review_veryhigh']) ? '1' : '0';
  $review_high = isset($_POST['review_high']) ? '1' : '0';
  $review_medium = isset($_POST['review_medium']) ? '1' : '0';
  $review_low = isset($_POST['review_low']) ? '1' : '0';
  $review_insignificant = isset($_POST['review_insignificant']) ? '1' : '0';
  $multi_factor = (int)$_POST['multi_factor'];

  // If the type is 1
  if ($type == "1")
  {
    // This is a local SimpleRisk user account
    $type = "simplerisk";

    // Check the password
    $error_code = valid_password($pass, $repeat_pass);
  }
  // If the type is 2
  else if ($type == "2")
  {
    // This is an LDAP user account
    $type = "ldap";

    // No password check required
    $error_code = 1;
  }
  // If the type is 3
  else if ($type == "3")
  {
    // This is a SAML user account
    $type = "saml";

    // No password check required
    $error_code = 1;
  }
  else
  {
    // This is an invalid type
    $type = "INVALID";

    // Return an error
    $error_code = 0;
  }

  // If the password is valid
  if ($error_code == 1)
  {
    // Verify that the user does not exist
    if (!user_exist($user))
    {
      // Verify that it is a valid username format
      if (valid_username($user))
      {
        // Create a unique salt for the user
        $salt = generate_token(20);

        // Hash the salt
        $salt_hash = '$2a$15$' . md5($salt);

        // Generate the password hash
        $hash = generateHash($salt_hash, $pass);

        // Create a boolean for all
        $all = false;

        // Create a boolean for none
        $none = false;

        // Initialize the team value as null
        $team = null;

        // Create the team value
        foreach ($teams as $value)
        {
          // If the selected value is all
          if ($value == "all") $all = true;

          // If the selected value is none
          if ($value == "none") $none = true;

          $team .= ":";
          $team .= $value;
          $team .= ":";
        }

        // If no value was submitted then default to none
        if ($value == "") $none = true;

        // If all was selected then assign all teams
        if ($all) $team = "all";

        // If none was selected then assign no teams
        if ($none) $team = "none";

        // Insert a new user
        add_user($type, $user, $email, $name, $salt, $hash, $team, $assessments, $asset, $admin, $review_veryhigh, $review_high, $review_medium, $review_low, $review_insignificant, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor);

        // If the encryption extra is enabled
        if (encryption_extra())
        {
          // Load the extra
          require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

          // Add the new encrypted user
          add_user_enc($pass, $salt, $user);
        }

        // Display an alert
        set_alert(true, "good", "The new user was added successfully.");
      }
      // Otherwise, an invalid username was specified
      else
      {
        // Display an alert
        set_alert(true, "bad", "An invalid username was specified.  Please try again with a different username.");
      }
    }
    // Otherwise, the user already exists
    else
    {
      // Display an alert
      set_alert(true, "bad", "The username already exists.  Please try again with a different username.");
    }
  }
  // Otherewise, an invalid password was specified
  else
  {
    // Display an alert
    //set_alert(true, "bad", password_error_message($error_code));
  }
}

// Check if a user was enabled
if (isset($_POST['enable_user']))
{
  $value = (int)$_POST['disabled_users'];

  // Verify value is an integer
  if (is_int($value))
  {
    enable_user($value);

    // Display an alert
    set_alert(true, "good", "The user was enabled successfully.");
  }
}

// Check if a user was disabled
if (isset($_POST['disable_user']))
{
  $value = (int)$_POST['enabled_users'];

  // Verify value is an integer
  if (is_int($value))
  {
    disable_user($value);

    // Display an alert
    set_alert(true, "good", "The user was disabled successfully.");
  }

}

// Check if a user was deleted
if (isset($_POST['delete_user']))
{
  $value = (int)$_POST['user'];

  // Verify value is an integer
  if (is_int($value))
  {
    delete_value("user", $value);

    // If the encryption extra is enabled
    if (encryption_extra())
    {
      // Load the extra
      require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

      // Delete the value from the user_enc table
      delete_user_enc($value);

      // Check to see if all users have now been activated
      check_all_activated();
    }

    // Display an alert
    set_alert(true, "good", "The existing user was deleted successfully.");
  }
}

// Check if a password reset was requested
if (isset($_POST['password_reset']))
{
  $value = (int)$_POST['user'];

  // Verify value is an integer
  if (is_int($value))
  {
    password_reset_by_userid($value);

    // Display an alert
    set_alert(true, "good", "A password reset email was sent to the user.");
  }
}

// Check if a password policy update was requested
if (isset($_POST['password_policy_update']))
{
  $strict_user_validation = (isset($_POST['strict_user_validation'])) ? 1 : 0;
  $pass_policy_enabled = (isset($_POST['pass_policy_enabled'])) ? 1 : 0;
  $min_characters = (int)$_POST['min_characters'];
  $alpha_required = (isset($_POST['alpha_required'])) ? 1 : 0;
  $upper_required = (isset($_POST['upper_required'])) ? 1 : 0;
  $lower_required = (isset($_POST['lower_required'])) ? 1 : 0;
  $digits_required = (isset($_POST['digits_required'])) ? 1 : 0;
  $special_required = (isset($_POST['special_required'])) ? 1 : 0;

  $pass_policy_attempt_lockout =(int)$_POST['pass_policy_attempt_lockout'];
  $pass_policy_attempt_lockout_time = (int)$_POST['pass_policy_attempt_lockout_time'];
  $pass_policy_min_age = (int)$_POST['pass_policy_min_age'];
  $pass_policy_max_age = (int)$_POST['pass_policy_max_age'];

  update_password_policy($strict_user_validation, $pass_policy_enabled, $min_characters, $alpha_required, $upper_required, $lower_required, $digits_required, $special_required, $pass_policy_attempt_lockout, $pass_policy_attempt_lockout_time, $pass_policy_min_age, $pass_policy_max_age);

  // Display an alert
  set_alert(true, "good", "The settings were updated successfully.");
}

?>

<!doctype html>
<html>

<head>
  <script src="../js/jquery.min.js"></script>
  <script src="../js/jquery-ui.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/bootstrap-multiselect.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  <link rel="stylesheet" href="../css/bootstrap-multiselect.css">
  <script type="text/javascript">
  $(function(){
    $("#team").multiselect({
      allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllTeams']); ?>',
      includeSelectAllOption: true
    });
  });
  </script>
  <script type="text/javascript">
  function handleSelection(choice) {
    elements = document.getElementsByClassName("ldap_pass");
    if (choice=="1") {
      for(i=0; i<elements.length; i++) {
        elements[i].style.display = "";
      }
    }
    if (choice=="2") {
      for(i=0; i<elements.length; i++) {
        elements[i].style.display = "none";
      }
    }
    if (choice=="3") {
      for(i=0; i<elements.length; i++) {
        elements[i].style.display = "none";
      }
    }
  }
  </script>

  <link rel="stylesheet" href="../css/divshot-util.css">
  <link rel="stylesheet" href="../css/divshot-canvas.css">
  <link rel="stylesheet" href="../css/display.css">

  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">
</head>

<body>
  <script type="text/javascript">
  function checkAll(bx) {
    var cbs = document.getElementsByTagName('input');
    for(var i=0; i < cbs.length; i++) {
      if (cbs[i].type == 'checkbox') {
        cbs[i].checked = bx.checked;
      }
    }
  }

  function checkAllRiskMgmt(bx) {
    if (document.getElementsByName("check_risk_mgmt")[0].checked == true) {
      document.getElementsByName("submit_risks")[0].checked = true;
      document.getElementsByName("modify_risks")[0].checked = true;
      document.getElementsByName("close_risks")[0].checked = true;
      document.getElementsByName("plan_mitigations")[0].checked = true;
      document.getElementsByName("review_insignificant")[0].checked = true;
      document.getElementsByName("review_low")[0].checked = true;
      document.getElementsByName("review_medium")[0].checked = true;
      document.getElementsByName("review_high")[0].checked = true;
      document.getElementsByName("review_veryhigh")[0].checked = true;
    }
    else {
      document.getElementsByName("submit_risks")[0].checked = false;
      document.getElementsByName("modify_risks")[0].checked = false;
      document.getElementsByName("close_risks")[0].checked = false;
      document.getElementsByName("plan_mitigations")[0].checked = false;
      document.getElementsByName("review_insignificant")[0].checked = false;
      document.getElementsByName("review_low")[0].checked = false;
      document.getElementsByName("review_medium")[0].checked = false;
      document.getElementsByName("review_high")[0].checked = false;
      document.getElementsByName("review_veryhigh")[0].checked = false;
    }
  }

  function checkAllAssetMgmt(bx) {
    if (document.getElementsByName("check_asset_mgmt")[0].checked == true) {
      document.getElementsByName("asset")[0].checked = true;
    }
    else {
      document.getElementsByName("asset")[0].checked = false;
    }
  }

  function checkAllAssessments(bx) {
    if (document.getElementsByName("check_assessments")[0].checked == true) {
      document.getElementsByName("assessments")[0].checked = true;
    }
    else {
      document.getElementsByName("assessments")[0].checked = false;
    }
  }

  function checkAllConfigure(bx) {
    if (document.getElementsByName("check_configure")[0].checked == true) {
      document.getElementsByName("admin")[0].checked = true;
    }
    else {
      document.getElementsByName("admin")[0].checked = false;
    }
  }
  </script>

  <?php
  view_top_menu("Configure");

  // Get any alert messages
  get_alert();
  ?>
  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_configure_menu("UserManagement"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <div class="span12">
            <div class="hero-unit">
              <form name="add_user" method="post" action="">
                <table border="0" cellspacing="0" cellpadding="0">
                  <tr><td colspan="2"><h4><?php echo $escaper->escapeHtml($lang['AddANewUser']); ?>:</h4></td></tr>
                  <tr>
                    <td><?php echo $escaper->escapeHtml($lang['Type']); ?>:&nbsp;</td>
                    <td>
                      <select name="type" id="select" onChange="handleSelection(value)">
                        <option selected value="1">SimpleRisk</option>
                        <?php
                        // If the custom authentication extra is enabeld
                        if (custom_authentication_extra())
                        {
                          // Display the LDAP option
                          echo "<option value=\"2\">LDAP</option>\n";

                          // Display the SAML option
                          echo "<option value=\"3\">SAML</option>\n";
                        }
                        ?>
                      </select>
                    </td>
                  </tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['FullName']); ?>:&nbsp;</td><td><input name="name" type="text" maxlength="50" size="20" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['EmailAddress']); ?>:&nbsp;</td><td><input name="email" type="text" maxlength="200" size="20" /></td></tr>
                  <tr><td><?php echo $escaper->escapeHtml($lang['Username']); ?>:&nbsp;</td><td><input name="new_user" type="text" maxlength="200" size="20" /></td></tr>
                  <tr class="ldap_pass"><td><?php echo $escaper->escapeHtml($lang['Password']); ?>:&nbsp;</td><td><input name="password" type="password" maxlength="50" size="20" autocomplete="off" /></td></tr>
                  <tr class="ldap_pass"><td><?php echo $escaper->escapeHtml($lang['RepeatPassword']); ?>:&nbsp;</td><td><input name="repeat_password" type="password" maxlength="50" size="20" autocomplete="off" /></td></tr>
                </table>
                <h6><u><?php echo $escaper->escapeHtml($lang['Teams']); ?></u></h6>
                <?php create_multiple_dropdown("team"); ?>
                <h6><u><?php echo $escaper->escapeHtml($lang['UserResponsibilities']); ?></u></h6>
                <table border="0" cellspacing="0" cellpadding="0">

                  <td>

                    <ul class="checklist">
                      <li><input name="check_all" class="hidden-checkbox" id="check_all" type="checkbox" onclick="checkAll(this)" /> <label for="check_all"> <?php echo $escaper->escapeHtml($lang['CheckAll']); ?> </label> </li>

                      <ul>
                        <li><input class="hidden-checkbox" id="check_risk_mgmt" name="check_risk_mgmt" type="checkbox" onclick="checkAllRiskMgmt(this)"> <label for="check_risk_mgmt"><?php echo $escaper->escapeHtml($lang['CheckAllRiskMgmt']); ?></label></li>
                        <ul>
                        <li><input class="hidden-checkbox" id="submit_risks" name="submit_risks" type="checkbox" />   <label for="submit_risks"><?php echo $escaper->escapeHtml($lang['AbleToSubmitNewRisks']); ?></label></li>
                        <li><input class="hidden-checkbox" id="modify_risks" name="modify_risks" type="checkbox" />   <label for="modify_risks"><?php echo $escaper->escapeHtml($lang['AbleToModifyExistingRisks']); ?></label></li>
                        <li><input class="hidden-checkbox" id="close_risks" name="close_risks" type="checkbox" />    <label for="close_risks"><?php echo $escaper->escapeHtml($lang['AbleToCloseRisks']); ?></label></li>
                        <li><input class="hidden-checkbox" id="plan_mitigations" name="plan_mitigations" type="checkbox" />  <label for="plan_mitigations"><?php echo $escaper->escapeHtml($lang['AbleToPlanMitigations']); ?></label></li>
                        <li><input class="hidden-checkbox" id="review_insignificant" name="review_insignificant" type="checkbox" />  <label for="review_insignificant"><?php echo $escaper->escapeHtml($lang['AbleToReviewInsignificantRisks']); ?></label></li>
                        <li><input class="hidden-checkbox" id="review_low" name="review_low" type="checkbox" />  <label for="review_low"><?php echo $escaper->escapeHtml($lang['AbleToReviewLowRisks']); ?></label></li>
                        <li><input class="hidden-checkbox" id="review_medium" name="review_medium" type="checkbox" />  <label for="review_medium"><?php echo $escaper->escapeHtml($lang['AbleToReviewMediumRisks']); ?></label></li>
                        <li><input class="hidden-checkbox" id="review_high" name="review_high" type="checkbox" />  <label for="review_high"><?php echo $escaper->escapeHtml($lang['AbleToReviewHighRisks']); ?></label></li>
                        <li><input class="hidden-checkbox" id="review_veryhigh" name="review_veryhigh" type="checkbox" />  <label for="review_veryhigh"><?php echo $escaper->escapeHtml($lang['AbleToReviewVeryHighRisks']); ?></label></li>
                      </ul>
                    </ul>
                    <ul>
                      <li><input class="hidden-checkbox" id="check_asset_mgmt" name="check_asset_mgmt" type="checkbox" onclick="checkAllAssetMgmt(this)" /> <label for="check_asset_mgmt"><?php echo $escaper->escapeHtml($lang['CheckAllAssetMgmt']); ?></label></li>
                      <ul>
                        <li><input class="hidden-checkbox" id="asset" name="asset" type="checkbox" /> <label for="asset"><?php echo $escaper->escapeHtml($lang['AllowAccessToAssetManagementMenu']); ?></label></li>
                      </ul>
                    </ul>
                    <ul>
                      <li><input class="hidden-checkbox" id="check_assessments" name="check_assessments" type="checkbox" onclick="checkAllAssessments(this)" /> <label for="check_assessments"><?php echo $escaper->escapeHtml($lang['CheckAllAssessments']); ?></label></li>
                      <ul>
                        <li><input class="hidden-checkbox" id="assessments" name="assessments" type="checkbox" /> <label for="assessments"><?php echo $escaper->escapeHtml($lang['AllowAccessToAssessmentsMenu']); ?></label></li>
                      </ul>
                    </ul>
                    <ul>
                      <li><input class="hidden-checkbox" id="check_configure" name="check_configure" type="checkbox" onclick="checkAllConfigure(this)" /> <label for="check_configure"><?php echo $escaper->escapeHtml($lang['CheckAllConfigure']); ?></label></li>
                      <ul>
                        <li><input class="hidden-checkbox" id="admin" name="admin" type="checkbox" /> <label for="admin"><?php echo $escaper->escapeHtml($lang['AllowAccessToConfigureMenu']); ?></label></li>
                      </ul>

                    </ul>
                  </ul>

                  </td>
                </table>
                <h6><u><?php echo $escaper->escapeHtml($lang['MultiFactorAuthentication']); ?></u></h6>
                <input class="hidden-radio" id="none" type="radio" name="multi_factor" value="1" checked /><label for="none">&nbsp;<?php echo $escaper->escapeHtml($lang['None']); ?></label>
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
                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_user" /><br />
              </p>
            </form>
          </div>
          <div class="hero-unit">
            <form name="select_user" method="post" action="view_user_details.php">
              <p>
                <h4><?php echo $escaper->escapeHtml($lang['ViewDetailsForUser']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['DetailsForUser']); ?> <?php create_dropdown("user"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Select']); ?>" name="select_user" />
              </p>
            </form>
          </div>
          <div class="hero-unit">
            <form name="enable_disable_user" method="post" action="">
              <p>
                <h4><?php echo $escaper->escapeHtml($lang['EnableAndDisableUsers']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['EnableAndDisableUsersHelp']); ?>.
              </p>
              <p>
                <?php echo $escaper->escapeHtml($lang['DisableUser']); ?> <?php create_dropdown("enabled_users"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Disable']); ?>" name="disable_user" />
              </p>
              <p>
                <?php echo $escaper->escapeHtml($lang['EnableUser']); ?> <?php create_dropdown("disabled_users"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Enable']); ?>" name="enable_user" />
              </p>
            </form>
          </div>
          <div class="hero-unit">
            <form name="delete_user" method="post" action="">
              <p>
                <h4><?php echo $escaper->escapeHtml($lang['DeleteAnExistingUser']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['DeleteCurrentUser']); ?> <?php create_dropdown("user"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_user" />
              </p>
            </form>
          </div>
          <div class="hero-unit">
            <form name="password_reset" method="post" action="">
              <p>
                <h4><?php echo $escaper->escapeHtml($lang['PasswordReset']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['SendPasswordResetEmailForUser']); ?> <?php create_dropdown("user"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Send']); ?>" name="password_reset" />
              </p>
            </form>
          </div>
          <div class="hero-unit">
            <form name="password_policy" method="post" action="">
              <p><h4><?php echo $escaper->escapeHtml($lang['UserPolicy']); ?>:</h4></p>
              <p><input class="hidden-checkbox" type="checkbox" id="strict_user_validation" name="strict_user_validation"<?php if (get_setting('strict_user_validation') == 1) echo " checked" ?> /><label for="strict_user_validation">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['UseCaseSensitiveValidationOfUsername']); ?></label></p>

              <br />
              <p><h4><?php echo $escaper->escapeHtml($lang['AccountLockoutPolicy']); ?>:</h4></p>

              <p><?php echo $escaper->escapeHtml($lang['MaximumAttemptsLockout']); ?>:&nbsp;&nbsp;<input style="width:50px" type="number" id="pass_policy_attempt_lockout" name="pass_policy_attempt_lockout" min="0" maxlength="2" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_attempt_lockout')); ?>"/> <?php echo $escaper->escapeHtml($lang['attempts']); ?>&nbsp;&nbsp;[0 = Lockout Disabled]</p>

              <p><?php echo $escaper->escapeHtml($lang['MaximumAttemptsLockoutTime']); ?>:&nbsp;&nbsp;<input style="width:50px" type="number" id="pass_policy_attempt_lockout_time" name="pass_policy_attempt_lockout_time" min="0" maxlength="2" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_attempt_lockout_time')); ?>"/> <?php echo $escaper->escapeHtml($lang['minutes']); ?>.&nbsp;&nbsp;[0 = Manual Enable Required]</p>

              <br />
              <p><h4><?php echo $escaper->escapeHtml($lang['PasswordPolicy']); ?>:</h4></p>

              <p><input class="hidden-checkbox" type="checkbox" id="pass_policy_enabled" name="pass_policy_enabled"<?php if (get_setting('pass_policy_enabled') == 1) echo " checked" ?> /><label for="pass_policy_enabled">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['Enabled']); ?></label></p>

              <p><?php echo $escaper->escapeHtml($lang['MinimumNumberOfCharacters']); ?> <input style="width:50px" type="number" id="min_characters" name="min_characters" min="1" max="50" maxlength="2" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_min_chars')); ?>"/> [1-50]</p>

              <p><input type="checkbox" class="hidden-checkbox" id="alpha_required" name="alpha_required"<?php if (get_setting('pass_policy_alpha_required') == 1) echo " checked" ?>  /><label for="alpha_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireAlphaCharacter']); ?></label></p>

              <p><input type="checkbox" class="hidden-checkbox" id="upper_required" name="upper_required"<?php if (get_setting('pass_policy_upper_required') == 1) echo " checked" ?>  /><label for="upper_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireUpperCaseCharacter']); ?></label></p>

              <p><input type="checkbox" class="hidden-checkbox" id="lower_required" name="lower_required"<?php if (get_setting('pass_policy_lower_required') == 1) echo " checked" ?>  /><label for="lower_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireLowerCaseCharacter']); ?></label></p>

              <p><input type="checkbox" class="hidden-checkbox" id="digits_required" name="digits_required"<?php if (get_setting('pass_policy_digits_required') == 1) echo " checked" ?>  /><label for="digits_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireNumericCharacter']); ?></label></p>

              <p><input type="checkbox" class="hidden-checkbox" id="special_required" name="special_required"<?php if (get_setting('pass_policy_special_required') == 1) echo " checked" ?> /><label for="special_required">&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['RequireSpecialCharacter']); ?></p>
              
              <p><?php echo $escaper->escapeHtml($lang['MinimumPasswordAge']); ?>:&nbsp;&nbsp;<input style="width:50px" type="number" id="pass_policy_min_age" name="pass_policy_min_age" min="0" maxlength="4" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_min_age')); ?>"/> <?php echo $escaper->escapeHtml($lang['days']); ?>.&nbsp;&nbsp;[0 = Min Age Disabled]</p>

              <p><?php echo $escaper->escapeHtml($lang['MaximumPasswordAge']); ?>:&nbsp;&nbsp;<input style="width:50px" type="number" id="pass_policy_max_age" name="pass_policy_max_age" min="0" maxlength="4" size="2" value="<?php echo $escaper->escapeHtml(get_setting('pass_policy_max_age')); ?>"/> <?php echo $escaper->escapeHtml($lang['days']); ?>.&nbsp;&nbsp;[0 = Max Age Disabled]</p>

              <p><input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="password_policy_update" /></p>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>

</html>
