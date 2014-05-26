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
<title>SimpleRisk OWASP Calculator</title>
<link rel="stylesheet" type="text/css" href="../css/style.css">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="../css/front-style.css" rel="stylesheet" type="text/css">
<script language="javascript" src="../js/basescript.js" type="text/javascript"></script>
<script language="javascript" src="../js/owasp_scoring.js" type="text/javascript"></script>
<script type="text/javascript" language="JavaScript">
  <!--
  var parent_window = window.opener;

  function owaspSubmit() {
    if (parent_window && !parent_window.closed) {
      parent_window.document.getElementById('OWASPSkillLevel').value=this.document.getElementById('SkillLevel').value;
      parent_window.document.getElementById('OWASPMotive').value=this.document.getElementById('Motive').value;
      parent_window.document.getElementById('OWASPOpportunity').value=this.document.getElementById('Opportunity').value;
      parent_window.document.getElementById('OWASPSize').value=this.document.getElementById('Size').value;
      parent_window.document.getElementById('OWASPEaseOfDiscovery').value=this.document.getElementById('EaseOfDiscovery').value;
      parent_window.document.getElementById('OWASPEaseOfExploit').value=this.document.getElementById('EaseOfExploit').value;
      parent_window.document.getElementById('OWASPAwareness').value=this.document.getElementById('Awareness').value;
      parent_window.document.getElementById('OWASPIntrusionDetection').value=this.document.getElementById('IntrusionDetection').value;
      parent_window.document.getElementById('OWASPLossOfConfidentiality').value=this.document.getElementById('LossOfConfidentiality').value;
      parent_window.document.getElementById('OWASPLossOfIntegrity').value=this.document.getElementById('LossOfIntegrity').value;
      parent_window.document.getElementById('OWASPLossOfAvailability').value=this.document.getElementById('LossOfAvailability').value;
      parent_window.document.getElementById('OWASPLossOfAccountability').value=this.document.getElementById('LossOfAccountability').value;
      parent_window.document.getElementById('OWASPFinancialDamage').value=this.document.getElementById('FinancialDamage').value;
      parent_window.document.getElementById('OWASPReputationDamage').value=this.document.getElementById('ReputationDamage').value;
      parent_window.document.getElementById('OWASPNonCompliance').value=this.document.getElementById('NonCompliance').value;
      parent_window.document.getElementById('OWASPPrivacyViolation').value=this.document.getElementById('PrivacyViolation').value;
    }
  }

  function closeWindow() {
    window.opener.closepopup();
  }

  function submitandclose() {
    owaspSubmit();
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
	  <td align="center" background="../images/cal-bg-head.jpg" height="35"><span class="heading">SimpleRisk OWASP Calculator</span></td>
    </tr>
    
    
    <tr>
	  <td align="left"  height="8"></td>
	  </tr>
    
    
	<tr>
    	<td align="left" style="padding-left:10px; padding-right:10px" height="35">This page provides a calculator for creating <a href="https://www.owasp.org/index.php/OWASP_Risk_Rating_Methodology" target="_blank">OWASP</a> vulnerability severity scores.  You'll need to gather information about the threat agent involved, the attack they're using, the vulnerability involved, and the impact of a successful exploit on your business.  There may be multiple possible groups of attackers, or even multiple possible business impacts.  In general, it's best to err on the side of caution by using the worst-case option, as that will result in the highest overall risk. </td>
    </tr>
	<tr>
	  <td align="left"  height="8"></td>
	  </tr>
        <tr>
          <td><table border="0" cellspacing="0" cellpadding="0">
            <tr>
              <td valign="top">
              <table width="336" border="0" align="right" cellpadding="0" cellspacing="0">

                  <tr bordercolor="#CCCCCC">
                    <td background="../images/cal-bg.jpg"><span class="style2" style="background-repeat:no-repeat">&nbsp;&nbsp;OWASP Score</span></td>
                  </tr>
                  <tr>
                    <td  style="padding-left:5px; padding-right:5px;" ><table width="100%" border="0" cellpadding="1" cellspacing="1">
                      <tr>
                        <td>Overall Likelihood
                        <td ><div id="Likelihood">10</div></td>
                      </tr>
                      <tr>
                        <td style="padding-left:10px;">Threat Agent Factors</td>
                        <td ><div id="ThreatAgentScore">10</div></td>
                      </tr>
                      <tr>
                        <td  style="padding-left:10px;">Vulnerability Factors</td>
                        <td ><div id="VulnerabilityScore">10</div></td>
                      </tr>
                      <tr>
                        <td>Overall Impact 
                        <td ><div id="Impact">10</div></td>
                      </tr>
                      <tr>
                        <td style="padding-left:10px;">Technical Impact</td>
                        <td ><div id="TechnicalScore">10</div></td>
                      </tr>
                      <tr>
                        <td  style="padding-left:10px;">Business Impact</td>
                        <td ><div id="BusinessScore">10</div></td>
                      </tr>
                      <tr>
                        <td class="style1"><strong>Overall OWASP Score</strong></td>
                        <td ><div id="OverallScore">10</div></td>
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
                      <?php view_owasp_help(); ?>
                    </td>
                  </tr>
              </table></td>
              <td background="../images/separetor.jpg" ><img src="../images/separetor.jpg"></td>
              <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="0">
                  <tr>
                    <td valign="top"><table width="336" border="0" cellpadding="0" cellspacing="0">
                        <tr bordercolor="#CCCCCC">
                          <td width="329" background="../images/cal-bg.jpg" bgcolor="#E6E2E1" class="style2"  style="background-repeat:no-repeat">&nbsp;&nbsp;&nbsp;Likelihood</td>
                        </tr>
                        <tr>
                          <td style="padding-left:5px;" ><table width="100%"  border="0" cellpadding="1" cellspacing="1" >
                            <tr>
                              <td colspan="2" class="style1">Threat Agent Factors</td>
                            </tr>
                            <tr>
                              <td width="117">Skill Level</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("SkillLevel", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('SkillLevelHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Motive</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("Motive", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('MotiveHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Opportunity</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("Opportunity", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('OpportunityHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Size</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("Size", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('SizeHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td colspan="2" class="style1">Vulnerability Factors</td>
                            </tr>
                            <tr>
                              <td width="117">Ease of Discovery</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("EaseOfDiscovery", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('EaseOfDiscoveryHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Ease of Exploit</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("EaseOfExploit", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('EaseOfExploitHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Awareness</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("Awareness", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('AwarenessHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Intrusion Detection</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("IntrusionDetection", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('IntrusionDetectionHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                          </table></td>
                        </tr>
                        <tr bordercolor="#CCCCCC">
                          <td background="../images/cal-bg.jpg" bgcolor="#E6E2E1"class="style2"  style="background-repeat:no-repeat">&nbsp;&nbsp;&nbsp;Impact</td>
                        </tr>
                        <tr>
                          <td style="padding-left:5px;" ><table width="100%"  border="0" cellpadding="1" cellspacing="1" >
                            <tr>
                              <td colspan="2" class="style1">Technical Impact</td>
                            </tr>
                            <tr>
                              <td width="117">Loss of Confidentiality</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("LossOfConfidentiality", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('LossOfConfidentialityHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Loss of Integrity</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("LossOfIntegrity", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('LossOfIntegrityHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Loss of Availability</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("LossOfAvailability", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('LossOfAvailabilityHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Loss of Accountability</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("LossOfAccountability", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('LossOfAccountabilityHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td colspan="2" class="style1">Business Impact</td>
                            </tr>
                            <tr>
                              <td width="117">Financial Damage</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("FinancialDamage", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('FinancialDamageHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Reputation Damage</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("ReputationDamage", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('ReputationDamageHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Non-Compliance</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("NonCompliance", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('NonComplianceHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Privacy Violation</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("PrivacyViolation", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('PrivacyViolationHelp');"></td>
                                </tr>
                              </table>
                              </td>
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
                      <input type="button" name="owaspSubmit" id="owaspSubmit" value="Submit" onclick="javascript: submitandclose();" />
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
