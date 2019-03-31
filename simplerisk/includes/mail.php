<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));

// Include the language file
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

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
function update_mail_settings($transport, $from_email, $from_name, $replyto_email, $replyto_name, $host, $smtpautotls, $smtpauth, $username, $password, $encryption, $port, $prepend)
{
    // Open the database connection
    $db = db_open();

    // If the transport is sendmail or smtp
    if ($transport == "sendmail" || $transport == "smtp")
    {
        // Update the transport
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_transport'");
        $stmt->bindParam(":value", $transport, PDO::PARAM_STR, 200);
        $stmt->execute();
    }

	// If the from_email is valid
	if (preg_match("/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $from_email))
	{
        // Update the from_email
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_from_email'");
        $stmt->bindParam(":value", $from_email, PDO::PARAM_STR, 200);
        $stmt->execute();
	}

    // Update the from_name
    $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_from_name'");
    $stmt->bindParam(":value", $from_name, PDO::PARAM_STR, 200);
    $stmt->execute();

        // If the replyto_email is valid
	if (preg_match("/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $replyto_email))
    {
        // Update the replyto_email
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_replyto_email'");
        $stmt->bindParam(":value", $replyto_email, PDO::PARAM_STR, 200);
        $stmt->execute();
	}

    // Update the replyto_name
    $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_replyto_name'");
    $stmt->bindParam(":value", $replyto_name, PDO::PARAM_STR, 200);
    $stmt->execute();

    // Update the host
    $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_host'");
    $stmt->bindParam(":value", $host, PDO::PARAM_STR, 200);
    $stmt->execute();

	// If the SMTP Auto TLS is either true or false
	if ($smtpautotls == "true" || $smtpautotls == "false")
	{
		// Update the SMTP Auto TLS
		$stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_smtpautotls'");
		$stmt->bindParam(":value", $smtpautotls, PDO::PARAM_STR, 5);
		$stmt->execute();
	}

	// If the SMTP Authentication is either true or false
	if ($smtpauth == "true" || $smtpauth == "false")
	{
		// Update the smtp authentication
		$stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_smtpauth'");
		$stmt->bindParam(":value", $smtpauth, PDO::PARAM_STR, 5);
		$stmt->execute();
	}

    // Update the username
    $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_username'");
    $stmt->bindParam(":value", $username, PDO::PARAM_STR, 200);
    $stmt->execute();

    // If the password is not empty
    if ($password != "")
    {
        // Update the value
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_password'");
        $stmt->bindParam(":value", $password, PDO::PARAM_STR, 200);
        $stmt->execute();
    }

    // If the encryption is none or tls or ssl
    if ($encryption == "none" || $encryption == "tls" || $encryption == "ssl")
    {
        // Update the encryption
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_smtpsecure'");
        $stmt->bindParam(":value", $encryption, PDO::PARAM_STR, 200);
        $stmt->execute();
    }

    // If the port is an integer value
    if (is_numeric($port))
    {
        // Update the port
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_port'");
        $stmt->bindParam(":value", $port, PDO::PARAM_STR, 200);
        $stmt->execute();
    }

    // Update the prepend
    $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='phpmailer_prepend'");
    $stmt->bindParam(":value", $prepend, PDO::PARAM_STR);
    $stmt->execute();

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
    $password = $mail['phpmailer_password'];
    $encryption = $mail['phpmailer_smtpsecure'];
    $port = $mail['phpmailer_port'];

	// Load the PHPMailer library
	require_once(realpath(__DIR__ . '/PHPMailer/src/PHPMailer.php'));
	require_once(realpath(__DIR__ . '/PHPMailer/src/SMTP.php'));
	require_once(realpath(__DIR__ . '/PHPMailer/src/Exception.php'));

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
