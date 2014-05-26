<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
                header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
        }

        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRisk');

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
?>

<html>
<head>
<title>SimpleRisk CVSS Calculator</title>
<link rel="stylesheet" type="text/css" href="../css/style.css">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="../css/front-style.css" rel="stylesheet" type="text/css">
<script language="javascript" src="../js/basescript.js" type="text/javascript"></script>
<script language="javascript" src="../js/cvss_scoring.js" type="text/javascript"></script>
<script type="text/javascript" language="JavaScript">
  <!--
  var parent_window = window.opener;

  function cvssSubmit() {
    if (parent_window && !parent_window.closed) {
      parent_window.document.getElementById('AccessVector').value=this.document.getElementById('AccessVector').value;
      parent_window.document.getElementById('AccessComplexity').value=this.document.getElementById('AccessComplexity').value;
      parent_window.document.getElementById('Authentication').value=this.document.getElementById('Authentication').value;
      parent_window.document.getElementById('ConfImpact').value=this.document.getElementById('ConfImpact').value;
      parent_window.document.getElementById('IntegImpact').value=this.document.getElementById('IntegImpact').value;
      parent_window.document.getElementById('AvailImpact').value=this.document.getElementById('AvailImpact').value;
      parent_window.document.getElementById('Exploitability').value=this.document.getElementById('Exploitability').value;
      parent_window.document.getElementById('RemediationLevel').value=this.document.getElementById('RemediationLevel').value;
      parent_window.document.getElementById('ReportConfidence').value=this.document.getElementById('ReportConfidence').value;
      parent_window.document.getElementById('CollateralDamagePotential').value=this.document.getElementById('CollateralDamagePotential').value;
      parent_window.document.getElementById('TargetDistribution').value=this.document.getElementById('TargetDistribution').value;
      parent_window.document.getElementById('ConfidentialityRequirement').value=this.document.getElementById('ConfidentialityRequirement').value;
      parent_window.document.getElementById('IntegrityRequirement').value=this.document.getElementById('IntegrityRequirement').value;
      parent_window.document.getElementById('AvailabilityRequirement').value=this.document.getElementById('AvailabilityRequirement').value;
    }
  }

  function closeWindow() {
    window.opener.closepopup();
  }

  function submitandclose() {
    cvssSubmit();
    closeWindow();
  }

  // -->
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
