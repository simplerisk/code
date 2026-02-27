<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/queues.php'));

// Require the composer autoload file
// This loads the PHPMailer library
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include the language file
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

/*********************************
 * FUNCTION: SEND EMAIL          *
 * Will queue emails for sending *
 *********************************/
function send_email(PDO $db, $name, $email, $subject, $body)
{
    $queue_task_payload = [
        'triggered_at'    => time(),
        'recipient_name'  => $name,
        'recipient_email' => $email,
        'subject'         => $subject,
        'body'            => $body
    ];

    if (!queue_task($db, 'core_email_send', $queue_task_payload, 100, 5, 3600)) {
        write_debug_log("Failed to queue email to {$email}", 'error');
    }
}

/**********************************
 * FUNCTION: SEND EMAIL IMMEDIATE *
 * Don't queue email, send now    *
 **********************************/
function send_email_immediate($name, $email, $subject, $body)
{
    $mail_settings = get_mail_settings();
    $transport = $mail_settings['phpmailer_transport'] ?? 'mail';
    $from_email = $mail_settings['phpmailer_from_email'] ?? '';
    $from_name = $mail_settings['phpmailer_from_name'] ?? '';
    $replyto_email = $mail_settings['phpmailer_replyto_email'] ?? $from_email;
    $replyto_name = $mail_settings['phpmailer_replyto_name'] ?? $from_name;
    $prepend = $mail_settings['phpmailer_prepend'] ?? '';
    $host = $mail_settings['phpmailer_host'] ?? '';
    $smtpautotls = $mail_settings['phpmailer_smtpautotls'] ?? 'true';
    $smtpauth = $mail_settings['phpmailer_smtpauth'] ?? 'false';
    $username = $mail_settings['phpmailer_username'] ?? '';
    $encryption = $mail_settings['phpmailer_smtpsecure'] ?? '';
    $port = $mail_settings['phpmailer_port'] ?? 25;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        write_debug_log("Invalid email: $email", "error");
        return false;
    }

    if (empty($subject) || empty($body)) {
        write_debug_log("Empty subject or body for email to $email", "error");
        return false;
    }

    // Decrypt password if Management Extra is enabled
    $password = $mail_settings['phpmailer_password'] ?? '';
    $management_extra_iv = get_setting("management_extra_iv");
    if ($management_extra_iv !== false && !empty($password)) {
        try {
            require_once(realpath(__DIR__ . "/../extras/management/index.php"));
            $iv = base64_decode($management_extra_iv);
            $password = openssl_decrypt($password, 'aes-256-cbc', MANAGEMENT_EXTRA_ENCRYPTION_KEY, 0, $iv);
            if (!$password) {
                write_debug_log("Failed to decrypt SMTP password for $username", "error");
                return false;
            }
        } catch (Exception $e) {
            write_debug_log("Exception decrypting SMTP password: " . $e->getMessage(), "error");
            return false;
        }
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from_email, $from_name);
        $mail->addReplyTo($replyto_email, $replyto_name);
        $mail->addAddress($email, $name);
        $mail->Subject = ($prepend ? $prepend . ' ' : '') . $subject;
        $mail->Sender = $from_email;
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        // Configure transport
        if ($transport === 'sendmail') {
            $mail->isSendmail();
        } elseif ($transport === 'smtp') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAutoTLS = ($smtpautotls !== 'false');
            $mail->SMTPKeepAlive = false;

            if ($smtpauth === 'true') {
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = $password;
                if ($encryption === 'tls') $mail->SMTPSecure = 'tls';
                elseif ($encryption === 'ssl') $mail->SMTPSecure = 'ssl';
            }
        }

        $mail->send();
        write_debug_log("Email successfully sent to $email with subject '$subject'", "info");
        return true;

    } catch (PHPMailer\PHPMailer\Exception $e) {
        write_debug_log("PHPMailer Exception sending email to $email: " . $e->getMessage(), "error");
        return false;
    } catch (Exception $e) {
        write_debug_log("General Exception sending email to $email: " . $e->getMessage(), "error");
        return false;
    }
}

/********************************
 * FUNCTION: PROCESS EMAIL TASK *
 ********************************/
function process_email_task($db, $task) {
    $payload = json_decode($task['payload'] ?? '', true);
    if (!is_array($payload) || !isset($payload['recipient_email'], $payload['recipient_name'])) {
        write_debug_log("Invalid email task payload: " . json_encode($task), 'error');
        $db->prepare("UPDATE queue_tasks SET status='failed', attempts=attempts+1, updated_at=NOW() WHERE id=?")
            ->execute([$task['id']]);
        return;
    }

    try {
        // Use the existing email logic, but move the PHPMailer creation here.
        send_email_immediate(
            $payload['recipient_name'],
            $payload['recipient_email'],
            $payload['subject'],
            $payload['body']
        );

        $db->prepare("UPDATE queue_tasks SET status='completed', updated_at=NOW() WHERE id=?")
            ->execute([$task['id']]);

    } catch (Exception $e) {
        $db->prepare("
            UPDATE queue_tasks 
            SET status='failed', attempts=attempts+1, updated_at=NOW() 
            WHERE id=?
        ")->execute([$task['id']]);

        write_debug_log("Email task failed: " . $e->getMessage(), 'error');
    }
}

?>