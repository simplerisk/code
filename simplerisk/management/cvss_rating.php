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

<html>
<head>
<title>SimpleRisk CVSS Calculator</title>
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
<script language="javascript" src="../js/cvss_scoring.js?<?php echo current_version("app"); ?>" type="text/javascript"></script>
<script type="text/javascript" language="JavaScript">

  var parent_window = window.opener;
  $(document).ready(function(){
      getCVE();
  })
  // Get the CVE information

//    var AccessVector = parent_window.$("#AccessVector", parent_window.parentOfScores).val();
//    var AccessComplexity = parent_window.$("#AccessComplexity", parent_window.parentOfScores).val();

  function cvssSubmit() {
    if (parent_window && !parent_window.closed) {
        parent_window.$("#AccessVector", parent_window.parentOfScores).val( $("#AccessVector").val() )
        parent_window.$("#AccessComplexity", parent_window.parentOfScores).val( $("#AccessComplexity").val() )
        parent_window.$("#Authentication", parent_window.parentOfScores).val( $("#Authentication").val() )
        parent_window.$("#ConfImpact", parent_window.parentOfScores).val( $("#ConfImpact").val() )
        parent_window.$("#IntegImpact", parent_window.parentOfScores).val( $("#IntegImpact").val() )
        parent_window.$("#AvailImpact", parent_window.parentOfScores).val( $("#AvailImpact").val() )
        parent_window.$("#Exploitability", parent_window.parentOfScores).val( $("#Exploitability").val() )
        parent_window.$("#RemediationLevel", parent_window.parentOfScores).val( $("#RemediationLevel").val() )
        parent_window.$("#ReportConfidence", parent_window.parentOfScores).val( $("#ReportConfidence").val() )
        parent_window.$("#CollateralDamagePotential", parent_window.parentOfScores).val( $("#CollateralDamagePotential").val() )
        parent_window.$("#TargetDistribution", parent_window.parentOfScores).val( $("#TargetDistribution").val() )
        parent_window.$("#ConfidentialityRequirement", parent_window.parentOfScores).val( $("#ConfidentialityRequirement").val() )
        parent_window.$("#IntegrityRequirement", parent_window.parentOfScores).val( $("#IntegrityRequirement").val() )
        parent_window.$("#AvailabilityRequirement", parent_window.parentOfScores).val( $("#AvailabilityRequirement").val() )
    }
  }

  function closeWindow() {
    window.opener.closepopup();
  }

  function submitandclose() {
    cvssSubmit();
    closeWindow();
  }

</script>

</head>

<body topmargin="0" bottommargin="4" leftmargin="0" rightmargin="0" ><form name="frmCalc" method="post" action="" >

<table width="672" border="0" cellpadding="1" cellspacing="0">

<tr>
<td align="left" valign="top"  bgcolor="#6B7782" >
  <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#FFFFFF">
    <tr>
  <td align="center" background="../images/cal-bg-head.jpg" height="35"><span class="heading">SimpleRisk CVSS V2.0 Calculator</span></td>
</tr>


<tr>
  <td align="left"  height="8"></td>
  </tr>


<tr>
    <td align="left" style="padding-left:10px; padding-right:10px" height="35">This page provides a calculator for creating <A href="http://www.first.org/cvss/" target="_blank">CVSS</A> vulnerability severity scores.  The scores are computed in sequence such that the Base Score is used to calculate the Temporal Score and the Temporal Score is used to calculate the Environmental Score.</td>
</tr>
<tr>
  <td align="left"  height="8"></td>
  </tr>
    <tr>
      <td><table border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td valign="top">
          <table width="500" border="0" align="right" cellpadding="0" cellspacing="0">

              <tr bordercolor="#CCCCCC">
                <td background="../images/cal-bg.jpg"><span class="style2" style="background-repeat:no-repeat">&nbsp;&nbsp;CVSS Score</span></td>
              </tr>
              <tr>
                <td  style="padding-left:5px; padding-right:5px;" ><table width="100%" border="0" cellpadding="1" cellspacing="1">
                  <tr>
                    <td >CVSS Base Score</td>
                    <td ><div id="BaseScore">0</div></td>
                  </tr>
                  <tr>
                    <td style="padding-left:10px;">Impact&nbsp;Subscore</td>
                    <td ><div id="ImpactSubscore">0</div></td>
                  </tr>
                  <tr>
                    <td  style="padding-left:10px;"> Exploitability&nbsp;Subscore</td>
                    <td ><div id="ExploitabilitySubscore">0</div></td>
                  </tr>
                  <tr>
                    <td>CVSS&nbsp;Temporal&nbsp;Score</td>
                    <td ><div id="TemporalScore">0</div></td>
                  </tr>
                  <tr>
                    <td height="20">CVSS&nbsp;Environmental&nbsp;Score</td>
                    <td ><div id="EnvironmentalScore">0</div></td>
                  </tr>
                </table></td>
              </tr>
              <tr>
                <td height="4"></td>
              </tr>
              <tr bordercolor="#CCCCCC">
                <td background="../images/cal-bg.jpg"><span class="style2" style="background-repeat:no-repeat">&nbsp;&nbsp;Help Desk</span></td>
              </tr>
              <tr>
                <td  style="padding-left:5px; padding-right:5px;" >
                  <?php view_cvss_help(); ?>
                </td>
              </tr>
          </table></td>
          <td background="../images/separetor.jpg" ><img src="../images/separetor.jpg"></td>
          <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td valign="top"><table width="336" border="0" cellpadding="0" cellspacing="0">
                    <tr bordercolor="#CCCCCC">
                      <td width="329" background="../images/cal-bg.jpg" bgcolor="#E6E2E1" class="style2"  style="background-repeat:no-repeat">&nbsp; Base Score Metrics</td>
                    </tr>
                    <tr>
                      <td style="padding-left:5px;" ><table width="100%"  border="0" cellpadding="1" cellspacing="1" >
                        <tr>
                          <td colspan="2" class="style1">Exploitability Metrics</td>
                        </tr>
                        <tr>
                          <td width="117">Attack Vector</td>
                          <td width="119"><table border="0" cellspacing="0" cellpadding="0">
                            <tr>
                              <td>
                                <?php create_cvss_dropdown("AccessVector") ?>
                              </td>
                              <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('AccessVectorHelp');"></td>
                            </tr>
                          </table></td>
                        </tr>
                        <tr>
                          <td>Attack Complexity</td>
                          <td class=""><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("AccessComplexity") ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('AccessComplexityHelp');"></td>
                              </tr>
                            </table></td>
                        </tr>
                        <tr>
                          <td>Authentication</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("Authentication") ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('AuthenticationHelp');"></td>
                              </tr>
                            </table></td>
                        </tr>
                        <tr>
                          <td colspan="2" class="style1">Impact Metrics</td>
                        </tr>
                        <tr>
                          <td>Confidentiality Impact</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("ConfImpact") ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('ConfImpactHelp');"></td>
                              </tr>
                            </table></td>
                        </tr>
                        <tr>
                          <td>Integrity Impact</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("IntegImpact") ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('IntegImpactHelp');"></td>
                              </tr>
                            </table></td>
                        </tr>
                        <tr>
                          <td>Availability Impact<br></td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("AvailImpact") ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('AvailImpactHelp');"></td>
                              </tr>
                            </table></td>
                        </tr>
                      </table></td>
                    </tr>
                    <tr bordercolor="#CCCCCC">
                      <td background="../images/cal-bg.jpg" bgcolor="#E6E2E1"class="style2"  style="background-repeat:no-repeat">&nbsp;&nbsp;&nbsp;Temporal Score Metrics</td>
                    </tr>
                    <tr>
                      <td  style="padding-left:5px;" ><table width="100%" border="0" cellspacing="1" cellpadding="1">
                        <tr>
                          <td> Exploitability</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("Exploitability", NULL, false) ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('ExploitabilityHelp');"></td>
                              </tr>
                          </table></td>
                        </tr>
                        <tr>
                          <td> Remediation Level</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("RemediationLevel", NULL, false) ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('RemediationLevelHelp');"></td>
                              </tr>
                          </table></td>
                        </tr>
                        <tr>
                          <td>Report Confidence</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("ReportConfidence", NULL, false) ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('ReportConfidenceHelp');"></td>
                              </tr>
                          </table></td>
                        </tr>
                      </table></td>
                    </tr>
                    <tr bordercolor="#CCCCCC">
                      <td  background="../images/cal-bg.jpg" class="style2"><span class="style2" style="background-repeat:no-repeat">&nbsp;&nbsp;&nbsp;Environmental Score Metrics</span></td>
                    </tr>
                    <tr>
                      <td style="padding-left:5px;"><table width="100%" border="0" cellspacing="1" cellpadding="1">
                        <tr>
                          <td>Collateral Damage Potential</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("CollateralDamagePotential", NULL, false) ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('CollateralDamagePotentialHelp');" /></td>
                              </tr>
                          </table></td>
                        </tr>
                        <tr>
                          <td> Target Distribution</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("TargetDistribution", NULL, false) ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('TargetDistributionHelp');" /></td>
                              </tr>
                          </table></td>
                        </tr>
                        <tr>
                          <td colspan="2" class="style1"><strong>Impact Subscore Modifiers</strong></td>
                        </tr>
                        <tr>
                          <td> Confidentiality Requirement</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("ConfidentialityRequirement", NULL, false) ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('ConfidentialityRequirementHelp');" /></td>
                              </tr>
                          </table></td>
                        </tr>
                        <tr>
                          <td> Integrity Requirement</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("IntegrityRequirement", NULL, false) ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('IntegrityRequirementHelp');" /></td>
                              </tr>
                          </table></td>
                        </tr>
                        <tr>
                          <td> Availability Requirement</td>
                          <td><table border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td>
                                  <?php create_cvss_dropdown("AvailabilityRequirement", NULL, false) ?>
                                </td>
                                <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('AvailabilityRequirementHelp');" /></td>
                              </tr>
                          </table></td>
                        </tr>
                      </table></td>
                    </tr>
                    <tr>
                      <td height="5"></td>
                    </tr>
                </table></td>
              </tr>
              <tr>
                <td align="center">
<!--
                  <input name="btnCalculate" type="image" id="btnCalculate" src="../images/cal-cvss.jpg"><br />
-->
                  <input type="button" name="cvssSubmit" id="cvssSubmit" value="Submit" onclick="javascript: submitandclose();" />
                </td>
              </tr>
              <tr>
                <td align="center" height="5"></td>
              </tr>
          </table></td>
        </tr>
      </table></td>
    </tr>
  </table></td>
</tr>
</table>
</form>
</body>
</html>
