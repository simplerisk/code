<?php

// Include the language file
require_once(language_file());

function get_review_settings()
{
        global $lang;
	echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"review_settings\" method=\"post\" action=\"\">\n";
        $review_levels = get_review_levels();
        echo "<p>". $lang['IWantToReviewHighRiskEvery'] ." <input type=\"text\" name=\"high\" size=\"2\" value=\"". $review_levels[0]['value'] ."\" />". $lang['days'] .".</p>\n";
        echo "<p>". $lang['IWantToReviewMediumRiskEvery'] ." <input type=\"text\" name=\"medium\" size=\"2\" value=\"". $review_levels[1]['value'] ."\" />". $lang['days'] .".</p>\n";
        echo "<p>". $lang['IWantToReviewLowRiskEvery'] ." <input type=\"text\" name=\"low\" size=\"2\" value=\"". $review_levels[2]['value'] ."\" />". $lang['days'] .".</p>\n";
        echo "<input type=\"submit\" value=\"". $lang['Update'] ."\" name=\"update_review_settings\" />\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}


function get_add_remove_values()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"category\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['Category'] .":</h4>\n";
        echo $lang['AddNewCategoryNamed'] ." <input name=\"new_category\" type=\"text\" maxlength=\"50\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_category\" /><br />\n";
        echo $lang['DeleteCurrentCategoryNamed']. " "; 
        echo create_dropdown("category") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_category\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"team\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['Team'] .":</h4>\n";
        echo $lang['AddNewTeamNamed'] ." <input name=\"new_team\" type=\"text\" maxlength=\"50\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_team\" /><br />\n";
        echo $lang['DeleteCurrentTeamNamed']. " ";
        echo create_dropdown("team") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_team\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"technology\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['Technology'] .":</h4>\n";
        echo $lang['AddNewTechnologyNamed'] ." <input name=\"new_technology\" type=\"text\" maxlength=\"50\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_technology\" /><br />\n";
        echo $lang['DeleteCurrentTechnologyNamed']. " ";
        echo create_dropdown("technology") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_technology\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"location\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['SiteLocation'] .":</h4>\n";
        echo $lang['AddNewSiteLocationNamed'] ." <input name=\"new_location\" type=\"text\" maxlength=\"100\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_location\" /><br />\n";
        echo $lang['DeleteCurrentSiteLocationNamed']. " ";
        echo create_dropdown("location") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_location\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"regulation\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['ControlRegulation'] .":</h4>\n";
        echo $lang['AddNewControlRegulationNamed'] ." <input name=\"new_regulation\" type=\"text\" maxlength=\"50\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_regulation\" /><br />\n";
        echo $lang['DeleteCurrentControlRegulationNamed']. " ";
        echo create_dropdown("regulation") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_regulation\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div echo class=\"hero-unit\">\n";
        echo "<form name=\"planning_strategy\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['RiskPlanningStrategy'] .":</h4>\n";
        echo $lang['AddNewRiskPlanningStrategyNamed'] ." <input name=\"new_planning_strategy\" type=\"text\" maxlength=\"20\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_planning_strategy\" /><br />\n";
        echo $lang['DeleteCurrentRiskPlanningStrategyNamed']. " ";
        echo create_dropdown("planning_strategy") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_planning_strategy\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"close_reason\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['CloseReason'] .":</h4>\n";
        echo $lang['AddNewCloseReasonNamed'] ." <input name=\"new_close_reason\" type=\"text\" maxlength=\"20\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_close_reason\" /><br />\n";
        echo $lang['DeleteCurrentCloseReasonNamed']. " ";
        echo create_dropdown("close_reason") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_close_reason\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}


function get_user_management()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"add_user\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['AddANewUser'] .":</h4>\n";
        echo $lang['Type'] .": <select name=\"type\" id=\"select\" onChange=\"handleSelection(value)\">\n";
        echo "<option selected value=\"1\">SimpleRisk</option>\n";

        // If the custom authentication extra is enabeld
        if (custom_authentication_extra())
        {
                // Display the LDAP option
                echo "<option value=\"2\">LDAP</option>\n";
        }   
        echo "</select><br />\n";
        echo $lang['FullName'] .": <input name=\"name\" type=\"text\" maxlength=\"50\" size=\"20\" /><br />\n";
        echo $lang['EmailAddress'] .": <input name=\"email\" type=\"text\" maxlength=\"200\" size=\"20\" /><br />\n";
        echo $lang['Username'] .": <input name=\"new_user\" type=\"text\" maxlength=\"20\" size=\"20\" /><br />\n";
        echo "<div id=\"simplerisk\">\n";
        echo $lang['Password'] .": <input name=\"password\" type=\"password\" maxlength=\"50\" size=\"20\" autocomplete=\"off\" /><br />\n";
        echo $lang['RepeatPassword'] .": <input name=\"repeat_password\" type=\"password\" maxlength=\"50\" size=\"20\" autocomplete=\"off\" /><br />\n";
        echo "</div>\n";
        echo "<h6><u>". $lang['Teams'] ."</u></h6>\n";
        echo create_multiple_dropdown("team");
        echo "<h6><u>". $lang['UserResponsibilities'] ."</u></h6>\n";
        echo "<ul>\n";
        echo "<li><input name=\"submit_risks\" type=\"checkbox\" />&nbsp;". $lang['AbleToSubmitNewRisks'] ."</li>\n";
        echo "<li><input name=\"modify_risks\" type=\"checkbox\" />&nbsp;". $lang['AbleToModifyExistingRisks'] ."</li>\n";
        echo "<li><input name=\"close_risks\" type=\"checkbox\" />&nbsp;". $lang['AbleToCloseRisks'] ."</li>\n";
        echo "<li><input name=\"plan_mitigations\" type=\"checkbox\" />&nbsp;". $lang['AbleToPlanMitigations'] ."</li>\n";
        echo "<li><input name=\"review_low\" type=\"checkbox\" />&nbsp;". $lang['AbleToReviewLowRisks'] ."</li>\n";
        echo "<li><input name=\"review_medium\" type=\"checkbox\" />&nbsp;". $lang['AbleToReviewMediumRisks'] ."</li>\n";
        echo "<li><input name=\"review_high\" type=\"checkbox\" />&nbsp;". $lang['AbleToReviewHighRisks'] ."</li>\n";
        echo "<li><input name=\"admin\" type=\"checkbox\" />&nbsp;". $lang['AllowAccessToConfigureMenu'] ."</li>\n";
        echo "</ul>\n";
        echo "<h6><u>". $lang['MultiFactorAuthentication'] ."</u></h6>\n";
        echo "<input type=\"radio\" name=\"multi_factor\" value=\"1\" checked />&nbsp;". $lang['None'] ."<br />\n";
        // If the custom authentication extra is installed
        if (custom_authentication_extra())
        {
                // Include the custom authentication extra
                require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

                // Display the multi factor authentication options
                echo multi_factor_authentication_options(1);
        }

        echo "<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_user\" /><br />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"select_user\" method=\"post\" action=\"index.php?module=3&page=9\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['ViewDetailsForUser'] .":</h4>\n";
        echo $lang['DetailsForUser']. ' ';
        echo create_dropdown("user") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Select'] ."\" name=\"select_user\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"enable_disable_user\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['EnableAndDisableUsers'] .":</h4>\n";
        echo $lang['EnableAndDisableUsersHelp'] .".\n";
        echo "</p>\n";
        echo "<p>\n";
        echo $lang['DisableUser']. ' ';
        echo create_dropdown("enabled_users") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Disable'] ."\" name=\"disable_user\" />\n";
        echo "</p>\n";
        echo "<p>\n";
        echo $lang['EnableUser']. ' ';
        echo create_dropdown("disabled_users") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Enable'] ."\" name=\"enable_user\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"delete_user\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['DeleteAnExistingUser'] .":</h4>\n";
        echo $lang['DeleteCurrentUser']. ' ';
        echo create_dropdown("user") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_user\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"password_reset\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['PasswordReset'] .":</h4>\n";
        echo $lang['SendPasswordResetEmailForUser']. ' ';
        echo create_dropdown("user") ."&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Send'] ."\" name=\"password_reset\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}


function get_custom_names()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"impact\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['Impact'] .":</h4>\n";
        echo $lang['Change']. ' ';
        echo create_dropdown("impact"). ' ';
        echo $lang['to'] ." <input name=\"new_name\" type=\"text\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Update'] ."\" name=\"update_impact\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"likelihood\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['Likelihood'] .":</h4>\n";
        echo $lang['Change']. ' ';
        echo create_dropdown("likelihood"). ' ';
        echo $lang['to'] ." <input name=\"new_name\" type=\"text\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Update'] ."\" name=\"update_likelihood\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"mitigation_effort\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>". $lang['MitigationEffort'] .":</h4>\n";
        echo $lang['Change']. ' ';
        echo create_dropdown("mitigation_effort"). ' ';
        echo $lang['to'] ." <input name=\"new_name\" type=\"text\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Update'] ."\" name=\"update_mitigation_effort\" />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}


function get_admin_audit_trail()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<h4>". $lang['AuditTrail'] ."</h4>\n";
        get_audit_trail();
        echo "</div>\n";
        echo "</div>\n";
}

function get_extras()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>Custom Extras</h4>\n";
        echo "<p>It would be awesome if everything were free, right?  Hopefully the core SimpleRisk platform is able to serve all of your risk management needs.  But, if you find yourself still wanting more functionality, we&#39;ve developed a series of &quot;Extras&quot; that will do just that for just a few hundred bucks each for a perpetual license.\n";
        echo "</p>\n";
        echo "<table width=\"100%\" class=\"table table-bordered table-condensed\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<td width=\"155px\"><b><u>Extra Name</u></b></td>\n";
        echo "<td><b><u>Description</u></b></td>\n";
        echo "<td width=\"60px\"><b><u>Enabled</u></b></td>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";
        echo "<tr>\n";
        echo "<td width=\"155px\"><b>Custom Authentication</b></td>\n";
        echo "<td>Currently provides support for Active Directory Authentication and Duo Security multi-factor authentication, but will have other custom authentication types in the future.</td>\n";
        echo "<td width=\"60px\">". (custom_authentication_extra() ? 'Yes' : 'No') ."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td width=\"155px\"><b>Team-Based Separation</b></td>\n";
        echo "<td>Restriction of risk viewing to team members the risk is categorized as.</td>\n";
        echo "<td width=\"60px\">". (team_separation_extra() ? 'Yes' : 'No') ."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td width=\"155px\"><b>Notifications</b></td>\n";
        echo "<td>Sends email notifications when risks are submitted, updated, mitigated, or reviewed and may be run on a schedule to notify users of risks in the Unreviewed or Past Due state.</td>\n";
        echo "<td width=\"60px\">". (notification_extra() ? 'Yes' : 'No') ."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td width=\"155px\"><b>Encrypted Database</b></td>\n";
        echo "<td>Encryption of sensitive text fields in the database.</td>\n";
        echo "<td width=\"60px\">". (encryption_extra() ? 'Yes' : 'No') ."</td>\n";
        echo "</tr>\n";
        echo "<tbody>\n";
        echo "</table>\n";
        echo "<p>If you are interested in adding these or other custom functionality to your SimpleRisk installation, please send an e-mail to <a href=\"mailto:extras@simplerisk.org?Subject=Interest%20in%20SimpleRisk%20Extras\" target=\"_top\">extras@simplerisk.org</a>.</p>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}


function get_admin_announcements()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>SimpleRisk Announcements</h4>\n";
        echo "<p>\n";
        echo get_announcements();
        echo "</p>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}

function get_admin_about()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<p>The use of this software is subject to the terms of the <a href=\"http://mozilla.org/MPL/2.0/\" target=\"newwindow\">Mozilla Public License, v. 2.0</a>.</p>\n";
        echo "<p><h4>Application Version</h4>\n";
        echo "<ul>\n";
        echo "<li>The latest Application version is ". latest_version("app") ."</li>\n";
        echo "<li>You are running Application version ". current_version("app") ."</li>\n";
        echo "</ul>\n";
        echo "</p>\n";
        echo "<p><h4>Database Version</h4>\n";
        echo "<ul>\n";
        echo "<li>The latest Database version is ". latest_version("db") ."</li>\n";
        echo "<li>You are running Database version ". current_version("db") ."</li>\n";
        echo "</ul>\n";
        echo "</p>\n";
        echo "<p>You can download the most recent code <a href=\"https://simplerisk.it/downloads\" target=\"newwindow\">here</a>.</p>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span6\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<p><a href=\"http://www.joshsokol.com\" target=\"newwindow\">Josh Sokol</a> wrote this Risk Management system after being fed up with the high-priced alternatives out there.  When your only options are spending tens of thousands of dollars or using a spreadsheet, good risk management is simply unattainable.</p>\n";
        echo "<p>Josh lives in Austin, TX and has four little ones starving for his time and attention.  If this tool is useful to you and you want to encourage him to keep his attention fixed on developing new features for you, perhaps you should consider donating via the PayPal form on the right.  It&#39;s also good karma.</p>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"span6\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<!-- START PAYPAL FORM -->\n";
        echo "<form action=\"https://www.paypal.com/cgi-bin/webscr\" method=\"post\" class=\"payformmargin\">\n";
        echo "<input type=\"hidden\" name=\"cmd\" value=\"_xclick\">\n";
        echo "<input type=\"hidden\" name=\"business\" value=\"josh@simplerisk.org\">\n";
        echo "<input type=\"hidden\" name=\"item_name\" value=\"Donation for Risk Management Software\">\n";
        echo "<input type=\"hidden\" name=\"no_note\" value=\"1\">\n";
        echo "<input type=\"hidden\" name=\"currency_code\" value=\"USD\">\n";
        echo "<table cellpadding=\"8\" cellspacing=\"0\" border=\"0\"><tr><td valign=\"top\" align=\"center\" class=\"payformbox\">\n";
        echo "<table cellpadding=\"3\" cellspacing=\"0\" border=\"0\"><tr><td align=\"left\">\n";
        echo "Enter amount:<br>\n";
        echo "<input type=\"text\" name=\"amount\" value=\"50.00\" class=\"payform\"><br>\n";
        echo "</td><td rowspan=\"3\">\n";
        echo "<img src=\"../images/paypal-custom.gif\" alt=\"Payments through Paypal\"><br>\n";
        echo "</td></tr><tr><td align=\"left\">\n";
        echo "<input type=\"hidden\" name=\"on0\" value=\"Project Details\">\n";
        echo "Payment notes:<br>\n";
        echo "<textarea name=\"os0\" rows=\"3\" cols=\"17\" class=\"payform\"></textarea><br>\n";
        echo "</td></tr><tr><td align=\"left\">\n";
        echo "<input type=\"submit\" name=\"PaypalPayment\" value=\"Send Payment\" class=\"payformbutton\"><br>\n";
        echo "</td></tr></table>\n";
        echo "</td></tr></table>\n";
        echo "</form>\n";
        echo "<!-- END PAYPAL FORM -->\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}


function get_view_user_details($user_id, $type, $name, $email, $username, $last_login, $language, $teams, $submit_risks, $modify_risks, $close_risks, $plan_mitigations,
$review_low, $review_medium, $review_high, $admin, $multi_factor)
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"update_user\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo "<h4>Update an Existing User:</h4>\n";
        echo "<input name=\"user\" type=\"hidden\" value=\"". $user_id ."\" />\n";
        echo $lang['Type']. ": <input style=\"cursor: default;\" name=\"type\" type=\"text\" maxlength=\"20\" size=\"20\" title=\"". $type ."\" disabled=\"disabled\" value=\"". $type ."\" /><br />\n";
        echo $lang['FullName']. ": <input name=\"name\" type=\"text\" maxlength=\"50\" size=\"20\" value=\"". htmlentities(stripslashes($name), ENT_QUOTES, 'UTF-8', false) ."\" /><br />\n";
        echo $lang['EmailAddress']. ": <input name=\"email\" type=\"text\" maxlength=\"200\" size=\"20\" value=\"". htmlentities($email, ENT_QUOTES, 'UTF-8', false) ."\" /><br />\n";
        echo $lang['Username']. ": <input style=\"cursor: default;\" name=\"username\" type=\"text\" maxlength=\"20\" size=\"20\" title=\"". htmlentities(stripslashes($username), ENT_QUOTES, 'UTF-8', false) ."\" disabled=\"disabled\" value=\"". htmlentities(stripslashes($username), ENT_QUOTES, 'UTF-8', false) ."\" /><br />\n";
        echo $lang['LastLogin']. ": <input style=\"cursor: default;\" name=\"last_login\" type=\"text\" maxlength=\"20\" size=\"20\" title=\"". $last_login ."\" disabled=\"disabled\" value=\"". $last_login ."\" /><br />\n";
        echo $lang['Language']. ": ";
        echo create_dropdown("languages", get_value_by_name("languages", $language));
        echo "<h6><u>". $lang['Teams'] ."</u></h6>\n";
        echo create_multiple_dropdown("team", $teams);
        echo "<h6><u>". $lang['UserResponsibilities'] ."</u></h6>\n";
        echo "<ul>\n";
        echo "<li><input name=\"submit_risks\" type=\"checkbox\"". ($submit_risks ? 'checked' : '') ."/>&nbsp;". $lang['AbleToSubmitNewRisks'] ."</li>\n";
        echo "<li><input name=\"modify_risks\" type=\"checkbox\"". ($modify_risks ? 'checked' : '') ."/>&nbsp;". $lang['AbleToModifyExistingRisks'] ."</li>\n";
        echo "<li><input name=\"close_risks\" type=\"checkbox\"". ($close_risks ? 'checked' : '') ."/>&nbsp;". $lang['AbleToCloseRisks'] ."</li>\n";
        echo "<li><input name=\"plan_mitigations\" type=\"checkbox\"". ($plan_mitigations ? 'checked' : '') ."/>&nbsp;". $lang['AbleToPlanMitigations'] ."</li>\n";
        echo "<li><input name=\"review_low\" type=\"checkbox\"". ($review_low ? 'checked' : '') ."/>&nbsp;". $lang['AbleToReviewLowRisks'] ."</li>\n";
        echo "<li><input name=\"review_medium\" type=\"checkbox\"". ($review_medium ? 'checked' : '') ."/>&nbsp;". $lang['AbleToReviewMediumRisks'] ."</li>\n";
        echo "<li><input name=\"review_high\" type=\"checkbox\"". ($review_high ? 'checked' : '') ."/>&nbsp;". $lang['AbleToReviewHighRisks'] ."</li>\n";
        echo "<li><input name=\"admin\" type=\"checkbox\"". ($admin ? 'checked' : '') ."/>&nbsp;". $lang['AllowAccessToConfigureMenu'] ."</li>\n";
        echo "</ul>\n";
        echo "<h6><u>". $lang['MultiFactorAuthentication'] ."</u></h6>\n";
        echo "<input type=\"radio\" name=\"multi_factor\" value=\"1\"". ($multi_factor == 1 ? 'checked' : '') ."/>&nbsp;". $lang['None'] ."<br />\n";
        // If the custom authentication extra is installed ? 'checked' : '')
        if (custom_authentication_extra())
        {
                // Include the custom authentication extra
                require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));
                // Display the multi factor authentication options
                multi_factor_authentication_options($multi_factor);
        }
        echo "<input type=\"submit\" value=\"". $lang['Update'] ."\" name=\"update_user\" /><br />\n";
        echo "</p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}

?>
