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
<title>SimpleRisk DREAD Calculator</title>
<link rel="stylesheet" type="text/css" href="../css/style.css">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="../css/front-style.css" rel="stylesheet" type="text/css">
<script language="javascript" src="../js/basescript.js" type="text/javascript"></script>
<script language="javascript" src="../js/dread_scoring.js" type="text/javascript"></script>
<script type="text/javascript" language="JavaScript">
  <!--
  var parent_window = window.opener;

  function dreadSubmit() {
    if (parent_window && !parent_window.closed) {
      parent_window.document.getElementById('DREADDamage').value=this.document.getElementById('DamagePotential').value;
      parent_window.document.getElementById('DREADReproducibility').value=this.document.getElementById('Reproducibility').value;
      parent_window.document.getElementById('DREADExploitability').value=this.document.getElementById('Exploitability').value;
      parent_window.document.getElementById('DREADAffectedUsers').value=this.document.getElementById('AffectedUsers').value;
      parent_window.document.getElementById('DREADDiscoverability').value=this.document.getElementById('Discoverability').value;
    }
  }

  function closeWindow() {
    window.opener.closepopup();
  }

  function submitandclose() {
    dreadSubmit();
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
	  <td align="center" background="../images/cal-bg-head.jpg" height="35"><span class="heading">SimpleRisk DREAD Calculator</span></td>
    </tr>
    
    
    <tr>
	  <td align="left"  height="8"></td>
	  </tr>
    
    
	<tr>
    	<td align="left" style="padding-left:10px; padding-right:10px" height="35">This page provides a calculator for creating <a href="http://en.wikipedia.org/wiki/DREAD:_Risk_assessment_model" target="_blank">DREAD</a> vulnerability severity scores.  DREAD is a classification scheme for quantifying, comparing and prioritizing the amount of risk presented by each evaluated threat. The DREAD acronym is formed from the first letter of each category below.  DREAD modeling influences the thinking behind setting the risk rating, and is also used directly to sort the risks. The DREAD algorithm, shown below, is used to compute a risk value, which is an average of all five categories.</td>
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
                    <td background="../images/cal-bg.jpg"><span class="style2" style="background-repeat:no-repeat">&nbsp;&nbsp;DREAD Score</span></td>
                  </tr>
                  <tr>
                    <td  style="padding-left:5px; padding-right:5px;" ><table width="100%" border="0" cellpadding="1" cellspacing="1">
                      <tr>
                        <td >Damage Potential</td>
                        <td ><div id="DamagePotentialScore">10</div></td>
                      </tr>
                      <tr>
                        <td>Reproducibility</td>
                        <td ><div id="ReproducibilityScore">10</div></td>
                      </tr>
                      <tr>
                        <td>Exploitability</td>
                        <td ><div id="ExploitabilityScore">10</div></td>
                      </tr>
                      <tr>
                        <td>Affected Users</td>
                        <td ><div id="AffectedUsersScore">10</div></td>
                      </tr>
                      <tr>
                        <td>Discoverability</td>
                        <td ><div id="DiscoverabilityScore">10</div></td>
                      </tr>
                      <tr>
                        <td class="style1"><strong>Overall&nbsp;DREAD&nbsp;Score</strong></td>
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
                      <?php view_dread_help(); ?> 
                    </td>
                  </tr>
              </table></td>
              <td background="../images/separetor.jpg" ><img src="../images/separetor.jpg"></td>
              <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="0">
                  <tr>
                    <td valign="top"><table width="336" border="0" cellpadding="0" cellspacing="0">
                        <tr bordercolor="#CCCCCC">
                          <td width="329" background="../images/cal-bg.jpg" bgcolor="#E6E2E1" class="style2"  style="background-repeat:no-repeat">&nbsp; Categories</td>
                        </tr>
                        <tr>
                          <td style="padding-left:5px;" >
                            <table width="100%"  border="0" cellpadding="1" cellspacing="1" >
                            <tr>
                              <td width="117">Damage Potential</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("DamagePotential", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('DamagePotentialHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Reproducibility</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("Reproducibility", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('ReproducibilityHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Exploitability</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("Exploitability", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('ExploitabilityHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Affected Users</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("AffectedUsers", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('AffectedUsersHelp');"></td>
                                </tr>
                              </table>
                              </td>
                            </tr>
                            <tr>
                              <td width="117">Discoverability</td>
                              <td width="119">
                              <table border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                  <td>
                                    <?php create_numeric_dropdown("Discoverability", 10, false) ?>
                                  </td>
                                  <td><img src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onClick="javascript:showHelp('DiscoverabilityHelp');"></td>
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
                      <input type="button" name="dreadSubmit" id="dreadSubmit" value="Submit" onclick="javascript: submitandclose();" />
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
