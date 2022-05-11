<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_riskmanagement" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

?>

<!doctype html>
<html>
<head>
<title>SimpleRisk Contributing Risk Calculator</title>
<link rel="stylesheet" type="text/css" href="../css/style.css?<?php echo current_version("app"); ?>">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="../css/front-style.css?<?php echo current_version("app"); ?>" rel="stylesheet" type="text/css">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);
?>
<script language="javascript" src="../js/basescript.js?<?php echo current_version("app"); ?>" type="text/javascript"></script>
<script language="javascript" src="../js/contributingrisk_scoring.js?<?php echo current_version("app"); ?>" type="text/javascript"></script>


<link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

<script type="text/javascript" language="JavaScript">

  <!--
  var parent_window = window.opener;
  
  $(document).ready(function(){
      // Initialize values for elements

      $("#contributing_likelihood").val(parent_window.$("#contributing_likelihood", parent_window.parentOfScores).val());

      parent_window.$(".contributing-impact", parent_window.parentOfScores).each(function(){
          $("#" + $(this).attr("id")).val($(this).val())
      })
      
      updateScore();
      
      $("#contributing_likelihood, .contributing_impact select").change(function(){
          updateScore();
      })
  })

  function contributingRiskSubmit() {
    if (parent_window && !parent_window.closed) {
        parent_window.$("#contributing_likelihood", parent_window.parentOfScores).val( $("#contributing_likelihood").val() );
        
        $(".contributing-risk-table td > select").each(function(){
            parent_window.$("#"+$(this).attr("id"), parent_window.parentOfScores).val( $(this).val() )
        })
    }
  }

  function closeWindow() {
    window.opener.closepopup();
  }

  function submitandclose() {
    contributingRiskSubmit();
    closeWindow();
  }

  // -->
</script>
</head>

<body topmargin="0" bottommargin="4" leftmargin="0" rightmargin="0" >
    <form name="frmCalc" method="post" action="" >

        <table width="645" border="0" cellpadding="1" cellspacing="0" align="center">

          <tr>
            <td align="left" valign="top"  bgcolor="#6B7782" >
              <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#FFFFFF">
                <tr>
                  <td align="center" background="../images/cal-bg-head.jpg" height="35"><span class="heading"><?php echo $escaper->escapeHtml($lang["SimpleriskContributingRiskCalculator"]); ?></span></td>
                </tr>

                <tr>
                  <td align="left"  height="8"></td>
                </tr>
                <tr>
                    <td align="left" style="padding-left:10px; padding-right:10px" height="35"><?php echo $escaper->escapeHtml($lang["ContributingRiskCalendarDescription"]) ?></td>
                </tr>
                <tr>
                  <td align="left"  height="8"></td>
                </tr>
                <tr>
                  <td>
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                          <td valign="top">
                              <table width="100%" border="0" align="right" cellpadding="0" cellspacing="0">
                                  <tr bordercolor="#CCCCCC">
                                    <td background="../images/cal-bg.jpg" style="background-repeat:no-repeat" bgcolor="#E6E2E1"><span class="style2" >&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang["ContributingRiskScore"]) ?></span></td>
                                  </tr>
                                  <tr>
                                    <td  style="padding-left:5px; padding-right:5px;" ><table width="100%" border="0" cellpadding="1" cellspacing="1">
                                      <tr>
                                        <td class="style1"><strong><?php echo $escaper->escapeHtml($lang["OverallContributingRiskScore"]) ?></strong></td>
                                        <td ><div id="OverallScore">10</div></td>
                                      </tr>
                                    </table></td>
                                  </tr>
                                  <tr>
                                    <td height="4"></td>
                                  </tr>
                               </table>
                            </td>
                          </tr>
                          <tr>
                              <td valign="top">
                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                  <tr>
                                    <td valign="top"><table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr bordercolor="#CCCCCC">
                                          <td width="329" background="../images/cal-bg.jpg" bgcolor="#E6E2E1" class="style2"  style="background-repeat:no-repeat">&nbsp;&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['Likelihood']) ?></td>
                                        </tr>
                                        <tr>
                                            <td style="padding:20px 5px;" align="center">
                                                <?php create_dropdown("contributing_risks_likelihood", NULL, "contributing_likelihood", false); ?>
                                            </td>
                                        </tr>
                                        <tr bordercolor="#CCCCCC">
                                          <td background="../images/cal-bg.jpg" bgcolor="#E6E2E1"class="style2"  style="background-repeat:no-repeat">&nbsp;&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang["ContributingRisk"]) ?></td>
                                        </tr>
                                        <tr>
                                          <td style="padding:15px;" >
                                              <?php display_contributing_risk_from_calculator(); ?>
                                          </td>
                                        </tr>
                                        <tr>
                                          <td height="5"></td>
                                        </tr>
                                    </table></td>
                                  </tr>
                                  <tr>
                                    <td align="center" style="padding-left:15px;padding-right:15px;">
                                      <input type="button" name="contributingRiskSubmit" id="contributingRiskSubmit" value="Submit" onclick="javascript: submitandclose();" />
                                    </td>
                                  </tr>
                                  <tr>
                                    <td align="center" height="5"></td>
                                  </tr>
                                </table>
                              </td>
                        </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
    </form>
</body>
</html>
