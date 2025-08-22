<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));

// Require the composer autoload file
// This loads the PHPMailer library
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include the language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

/*******************************
 * FUNCTION: GET MAIL SETTINGS *
 *******************************/
function get_mail_settings()
{
        // Open the database connection
        $db = db_open();

        // Get the risk levels
        $stmt = $db->prepare("SELECT * FROM settings WHERE name LIKE 'phpmailer_%'");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // For each entry in the array
        foreach ($array as $value)
        {
                $mail[$value['name']] = $value['value'];
        }

        return $mail;
}

/**********************************
 * FUNCTION: UPDATE MAIL SETTINGS *
 **********************************/
function update_mail_settings($transport, $from_email, $from_name, $replyto_email, $replyto_name, $host, $smtpautotls, $smtpauth, $username, $password, $encryption, $port, $prepend) {

    // Open the database connection
    $db = db_open();

    // If the transport is sendmail or smtp
    if ($transport == "sendmail" || $transport == "smtp") {

        $current_transport = get_setting("phpmailer_transport");

        // If the current transport is not the same as the new transport
        if ($current_transport != $transport) {

            // Update the transport
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_transport'");
            $stmt->bindParam(":value", $transport, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_transport\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

        }
    }

	// If the from_email is valid
	if (preg_match("/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $from_email)) {

        $current_from_email = get_setting("phpmailer_from_email");

        // If the current from_email is not the same as the new from_email
        if ($current_from_email != $from_email) {

            // Update the from_email
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_from_email'");
            $stmt->bindParam(":value", $from_email, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_from_email\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

        }
	}

    $current_from_name = get_setting("phpmailer_from_name");

    // If the current from_name is not the same as the new from_name
    if ($current_from_name != $from_name) {
        
        // Update the from_name
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_from_name'");
        $stmt->bindParam(":value", $from_name, PDO::PARAM_STR, 200);
        $stmt->execute();
        
        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_from_name\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

    }

    // If the replyto_email is valid
	if (preg_match("/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $replyto_email)) {

        $current_replyto_email = get_setting("phpmailer_replyto_email");

        // If the current replyto_email is not the same as the new replyto_email
        if ($current_replyto_email != $replyto_email) {

            // Update the replyto_email
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_replyto_email'");
            $stmt->bindParam(":value", $replyto_email, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_replyto_email\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

        }
	}

    $current_replyto_name = get_setting("phpmailer_replyto_name");

    // If the current replyto_name is not the same as the new replyto_name
    if ($current_replyto_name != $replyto_name) {

        // Update the replyto_name
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_replyto_name'");
        $stmt->bindParam(":value", $replyto_name, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_replyto_name\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

    }

    $current_host = get_setting("phpmailer_host");
    
    // If the current host is not the same as the new host
    if ($current_host != $host) {

        // Update the host
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_host'");
        $stmt->bindParam(":value", $host, PDO::PARAM_STR, 200);
        $stmt->execute();
        
        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_host\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

    }


	// If the SMTP Auto TLS is either true or false
	if ($smtpautotls == "true" || $smtpautotls == "false") {

        $current_smtpautotls = get_setting("phpmailer_smtpautotls");

        // If the current SMTP Auto TLS is not the same as the new SMTP Auto TLS
        if ($current_smtpautotls != $smtpautotls) {

            // Update the SMTP Auto TLS
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_smtpautotls'");
            $stmt->bindParam(":value", $smtpautotls, PDO::PARAM_STR, 5);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_smtpautotls\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

        }
	}

	// If the SMTP Authentication is either true or false
	if ($smtpauth == "true" || $smtpauth == "false") {

        $current_smtpauth = get_setting("phpmailer_smtpauth");

        // If the current SMTP Authentication is not the same as the new SMTP Authentication
        if ($current_smtpauth != $smtpauth) {

            // Update the smtp authentication
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_smtpauth'");
            $stmt->bindParam(":value", $smtpauth, PDO::PARAM_STR, 5);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_smtpauth\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

        }
	}

    $current_username = get_setting("phpmailer_username");

    // If the current username is not the same as the new username
    if ($current_username != $username) {

        // Update the username
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_username'");
        $stmt->bindParam(":value", $username, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_username\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

    }

    // If the password is not empty
    if ($password != "") {

        $current_password = get_setting("phpmailer_password");

        // If the current password is not the same as the new password
        if ($current_password != $password) {

            // Update the value
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_password'");
            $stmt->bindParam(":value", $password, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_password\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

        }
    }

    // If the encryption is none or tls or ssl
    if ($encryption == "none" || $encryption == "tls" || $encryption == "ssl") {

        $current_encryption = get_setting("phpmailer_smtpsecure");

        // If the current encryption is not the same as the new encryption
        if ($current_encryption != $encryption) {

            // Update the encryption
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_smtpsecure'");
            $stmt->bindParam(":value", $encryption, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_smtpsecure\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

        }
    }

    // If the port is an integer value
    if (is_numeric($port)) {

        $current_port = get_setting("phpmailer_port");

        // If the current port is not the same as the new port
        if ($current_port != $port) {

            // Update the port
            $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_port'");
            $stmt->bindParam(":value", $port, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Add an audit log entry for the change
            $risk_id = 1000;
            $message = "A setting value named \"phpmailer_port\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

        }
    }

    $current_prepend = get_setting("phpmailer_prepend");

    // If the current prepend is not the same as the new prepend
    if ($current_prepend != $prepend) {

        // Update the prepend
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_prepend'");
        $stmt->bindParam(":value", $prepend, PDO::PARAM_STR);
        $stmt->execute();

        // Add an audit log entry for the change
        $risk_id = 1000;
        $message = "A setting value named \"phpmailer_prepend\" was updated by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

    }

    // Close the database connection
    db_close($db);

}

/************************
 * FUNCTION: SEND EMAIL *
 ************************/
function send_email($name, $email, $subject, $body)
{
	// Get the mail settings
	$mail = get_mail_settings();
    $transport = $mail['phpmailer_transport'];
    $from_email = $mail['phpmailer_from_email'];
    $from_name = $mail['phpmailer_from_name'];
    $replyto_email = $mail['phpmailer_replyto_email'];
    $replyto_name = $mail['phpmailer_replyto_name'];
    $prepend = $mail['phpmailer_prepend'];
    $host = $mail['phpmailer_host'];
	$smtpautotls = $mail['phpmailer_smtpautotls'];
	$smtpauth = $mail['phpmailer_smtpauth'];
    $username = $mail['phpmailer_username'];
    $encryption = $mail['phpmailer_smtpsecure'];
    $port = $mail['phpmailer_port'];

    // Get a Management Extra IV from the settings table
    $management_extra_iv = get_setting("management_extra_iv");

    // If we have a value, that means this is a hosted instance with SMTP email
    if ($management_extra_iv !== false)
    {
        // Load the Management Extra
        require_once(realpath(__DIR__ . "/../extras/management/index.php"));

        // We need to base64 decode the IV
        $iv = base64_decode($management_extra_iv);

        // Decrypt the phpmailer password
        $phpmailer_password = $mail['phpmailer_password'];
        $password = openssl_decrypt($phpmailer_password, 'aes-256-cbc', MANAGEMENT_EXTRA_ENCRYPTION_KEY, 0, $iv);
    }
    // Otherwise use the phpmailer_password value
    else $password = $mail['phpmailer_password'];


	// Create a new PHPMailer instance
	$mail = new PHPMailer\PHPMailer\PHPMailer;

	// If SMTP auto TLS is disabled
	if ($smtpautotls == "false")
	{
		// Disable SMTP auto TLS
		$mail->SMTPAutoTLS = false;
	}

	// Set the character set to UTF-8
	$mail->CharSet = 'UTF-8';

	// Set who the message is to be sent from
	$mail->setFrom($from_email, $from_name);

	// Set an alternative reply-to address
	$mail->addReplyTo($replyto_email, $replyto_name);

	// Add a recipient
	$mail->addAddress($email, $name);

	// Add a CC
	// $mail->addCC('cc@example.com');

	// Add a BCC
	// $mail->addBCC('bcc@example.com');

	// Set the subject line
    $mail->Subject = ($prepend && strlen($prepend) > 0 ? $prepend . " " : "") . $subject;

	// Set the email format to HTML
	$mail->isHTML(true);

	// Message body in HTML
	$mail->Body = $body;

	// Message body in plain text for non-HTML mail clients
	//$mail->AltBody = 'This is a plain-text message body';

	// Read an HTML message body from an external file, convert referenced images to embedded
	// convert HTML into basic plain-text alternative body
	// $mail->msgHTML(file_get_contents('contents.html'), dirname(__FILE__));

	// Attach an image file
	// $mail->addAttachment('images/phpmailer_mini.png');
	// Add attachments with an optional name
	// $mail->addAttachment('/tmp/image.jpg', 'new.jpg');

	// If the transport is sendmail
	if ($transport == "sendmail")
	{
		// Set PHPMailer to use the sendmail transport
		$mail->isSendmail();
	}
	// If the transport is smtp
	else if ($transport == "smtp")
	{
		// Set PHPMailer to use the smtp transport
		$mail->isSMTP();

		// Specify the main SMTP server
		// Could be a semi-colon separated list
		$mail->Host = $host;

		// TCP port to connect to
		$mail->Port = $port;

		// If SMTP authentication is enabled
		if ($smtpauth == "true")
		{
			// Enable SMTP authentication
			$mail->SMTPAuth = true;

			// SMTP username
			$mail->Username = $username;

			// SMTP password
			$mail->Password = $password;

			// If the encryption is tls
			if ($encryption == "tls")
			{
				// Enable TLS encryption
				$mail->SMTPSecure = 'tls';
			}
			// Otherwise, if the encryption is ssl
			else if ($encryption == "ssl")
			{
				// Enable SSL encryption
				$mail->SMTPSecure = 'ssl';
			}
		}
	}

	// Send the message, check for errors
	if (!$mail->send())
	{
		// Log any errors to the debug log
		write_debug_log("Mailer Error: " . $mail->ErrorInfo);
	}
}

?>