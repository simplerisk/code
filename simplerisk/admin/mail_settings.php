<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));
	require_once(realpath(__DIR__ . '/../includes/mail.php'));
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

	// Check if the mail settings were submitted
	if (isset($_POST['submit_mail']))
	{
		// Get the posted values
		$transport = $_POST['transport'];
		$from_email = $_POST['from_email'];
		$from_name = $_POST['from_name'];
		$replyto_email = $_POST['replyto_email'];
		$replyto_name = $_POST['replyto_name'];
		$host = $_POST['host'];
		$smtpauth = (isset($_POST['smtpauth'])) ? "true" : "false";
		$username = $_POST['username'];
		$password = $_POST['password'];
		$encryption = $_POST['encryption'];
		$port = $_POST['port'];

		// Update the mail settings
		update_mail_settings($transport, $from_email, $from_name, $replyto_email, $replyto_name, $host, $smtpauth, $username, $password, $encryption, $port);

		// Display an alert
		set_alert(true, "good", "Mail settings were updated successfully.");
	}

	// Get the mail settings
	$mail = get_mail_settings();
	$transport = $mail['phpmailer_transport'];
	$from_email = $mail['phpmailer_from_email'];
	$from_name = $mail['phpmailer_from_name'];
	$replyto_email = $mail['phpmailer_replyto_email'];
	$replyto_name = $mail['phpmailer_replyto_name'];
	$host = $mail['phpmailer_host'];
	$smtpauth = $mail['phpmailer_smtpauth'];
	$username = $mail['phpmailer_username'];
	$password = $mail['phpmailer_password'];
	$encryption = $mail['phpmailer_smtpsecure'];
	$port = $mail['phpmailer_port'];
?>

<!doctype html>
<html>

  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <script>
      function dropdown_transport()
      {
        smtp = document.getElementsByClassName("smtp");
        smtpauth = document.getElementsByClassName("smtpauth");
        dropdown = document.getElementById("transport");
        if(dropdown.options[dropdown.selectedIndex].text == "smtp")
        {
          for(i=0; i<smtp.length; i++)
          {
            smtp[i].style.display = "";
          }

          checkbox = document.getElementById("smtpauth");
          if(checkbox.checked)
          {
            for(i=0; i<smtpauth.length; i++)
            {
              smtpauth[i].style.display = "";
            }
          }
        }
        else
        {
          for(i=0; i<smtp.length; i++)
          {
            smtp[i].style.display = "none";
          }

          for(i=0; i<smtpauth.length; i++)
          {
            smtpauth[i].style.display = "none";
          }
        }
      }

      function checkbox_smtpauth()
      {
        elements = document.getElementsByClassName("smtpauth");
        checkbox = document.getElementById("smtpauth");
        if(checkbox.checked)
        {
          for(i=0; i<elements.length; i++)
          {
            elements[i].style.display = "";
          }
        }
        else
        {
          for(i=0; i<elements.length; i++)
          {
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

<?php
	view_top_menu("Configure");

	// Get any alert messages
	get_alert();
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("MailSettings"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="mail_settings" method="post" action="">
                  <table name="mail" id="mail" border="0">
                    <tr>
                      <td><?php echo $escaper->escapeHTML($lang['TransportAgent']); ?>:&nbsp;&nbsp;</td>
                      <td>
                        <select name="transport" id="transport" onchange="javascript: dropdown_transport()">
                          <option value="sendmail"<?php echo ($transport=="sendmail") ? " selected" : ""; ?>>sendmail</option>
                          <option value="smtp"<?php echo ($transport=="smtp") ? " selected" : ""; ?>>smtp</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><?php echo $escaper->escapeHTML($lang['FromName']); ?>:&nbsp;&nbsp;</td>
                      <td><input type="text" name="from_name" value="<?php echo $escaper->escapeHTML($from_name); ?>" /></td>
                    </tr>
                    <tr>
                      <td><?php echo $escaper->escapeHTML($lang['FromEmail']); ?>:&nbsp;&nbsp;</td>
                      <td><input type="text" name="from_email" value="<?php echo $escaper->escapeHTML($from_email); ?>" /></td>
                    </tr>
                    <tr>
                      <td><?php echo $escaper->escapeHTML($lang['ReplyToName']); ?>:&nbsp;&nbsp;</td>
                      <td><input type="text" name="replyto_name" value="<?php echo $escaper->escapeHTML($replyto_name); ?>" /></td>
                    </tr>
                    <tr>
                      <td><?php echo $escaper->escapeHTML($lang['ReplyToEmail']); ?>:&nbsp;&nbsp;</td>
                      <td><input type="text" name="replyto_email" value="<?php echo $escaper->escapeHTML($replyto_email); ?>" /></td>
                    </tr>
                    <tr class="smtp"<?php echo ($transport=="sendmail") ? " style=\"display: none;\"" : "" ?>>
                      <td><?php echo $escaper->escapeHTML($lang['Host']); ?>:&nbsp;&nbsp;</td>
                      <td><input type="text" name="host" value="<?php echo $escaper->escapeHTML($host); ?>" /></td>
                    </tr>
                    <tr class="smtp"<?php echo ($transport=="sendmail") ? " style=\"display: none;\"" : "" ?>>
                      <td><?php echo $escaper->escapeHTML($lang['Port']); ?>:&nbsp;&nbsp;</td>
                      <td><input type="number" name="port" value="<?php echo $escaper->escapeHTML($port); ?>" /></td>
                    </tr>
                    <tr class="smtp"<?php echo ($transport=="sendmail") ? " style=\"display: none;\"" : "" ?>>
                      <td><?php echo $escaper->escapeHTML($lang['SMTPAuthentication']); ?>:&nbsp;&nbsp;</td>
                      <td><input type="checkbox" name="smtpauth" id="smtpauth" onchange="javascript: checkbox_smtpauth()" <?php echo ($smtpauth == "true") ? "checked=\"yes\" " : ""?>/></td>
                    </tr>
                    <tr class="smtpauth"<?php echo ($transport=="sendmail" || $smtpauth=="false") ? " style=\"display: none;\"" : "" ?>>
                      <td><?php echo $escaper->escapeHTML($lang['Username']); ?>:&nbsp;&nbsp;</td>
                      <td><input type="text" name="username" value="<?php echo $escaper->escapeHTML($username); ?>" /></td>
                    </tr>
                    <tr class="smtpauth"<?php echo ($transport=="sendmail" || $smtpauth=="false") ? " style=\"display: none;\"" : "" ?>>
                      <td><?php echo $escaper->escapeHTML($lang['Password']); ?>:&nbsp;&nbsp;</td>
                      <td><input type="password" name="password" value="" placeholder="Change Current Value" /></td>
                    </tr>
                    <tr class="smtpauth"<?php echo ($transport=="sendmail" || $smtpauth=="false") ? " style=\"display: none;\"" : "" ?>>
                      <td><?php echo $escaper->escapeHTML($lang['Encryption']); ?>:&nbsp;&nbsp;</td>
                      <td>
                        <select name="encryption" id="encryption">
                          <option value="none"<?php echo ($encryption=="none") ? " selected" : ""; ?>>None</option>
                          <option value="tls"<?php echo ($encryption=="tls") ? " selected" : ""; ?>>TLS</option>
                          <option value="ssl"<?php echo ($encryption=="ssl") ? " selected" : ""; ?>>SSL</option>
                        </select>
                      </td>
                    </tr>
                  </table>
                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Submit']); ?>" name="submit_mail" />
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
