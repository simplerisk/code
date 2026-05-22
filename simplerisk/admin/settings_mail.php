<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'CUSTOM:common.js'], ['check_admin' => true]);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/mail.php'));

    // Check if the mail settings were submitted
    if (isset($_POST['submit_mail'])) {
        // Get the posted values
        $transport = $_POST['transport'];
        $from_email = $_POST['from_email'];
        $from_name = $_POST['from_name'];
        $replyto_email = $_POST['replyto_email'];
        $replyto_name = $_POST['replyto_name'];
        $prepend = $_POST['prepend'];
        $host = $_POST['host'];
        $smtpautotls = (isset($_POST['smtpautotls'])) ? "true" : "false";
        $smtpauth = (isset($_POST['smtpauth'])) ? "true" : "false";
        $username = $_POST['username'];
        $password = $_POST['password'];
        $encryption = $_POST['encryption'];
        $port = $_POST['port'];

        // Update the mail settings
        update_mail_settings($transport, $from_email, $from_name, $replyto_email, $replyto_name, $host, $smtpautotls, $smtpauth, $username, $password, $encryption, $port, $prepend);

        // Display an alert
        set_alert(true, "good", "Mail settings were updated successfully.");
    }

    // Check if the mail test was submitted
    if (isset($_POST['test_mail_configuration'])) {

        // Set up the test email
        $name = "SimpleRisk Test";
        $email = !empty($_POST['email']) ? trim($_POST['email']) : "";
        $subject = "SimpleRisk Test Email";
        $full_message = "This is a test email from SimpleRisk.";
        $now = time();

        // Check if the email address is not empty
        if (empty($email)) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['PleaseEnterAnEmailAddressBeforeSendingATestEmail']));
            header("Location: settings_mail.php");
            exit();

        }

        // Limit test mail sending to 5 per hour.
        $test_mail_rate_limit = 5;
        $test_mail_period = 3600;

        // Get the test mail sent times from the session, ensuring it's an array
        if (isset($_SESSION['test_mail_sent_times'])) {
            $test_mail_sent_times = is_array($_SESSION['test_mail_sent_times']) ? $_SESSION['test_mail_sent_times'] : [];
        } else {
            $test_mail_sent_times = [];
        }

        if (empty($test_mail_sent_times) && isset($_SESSION['test_mail_sent']) && is_numeric($_SESSION['test_mail_sent'])) {
            $test_mail_sent_times[] = intval($_SESSION['test_mail_sent']);
        }

        // Drop entries older than the period.
        $test_mail_sent_times = array_values(array_filter($test_mail_sent_times, function ($sent_time) use ($now, $test_mail_period) {
            return is_numeric($sent_time) && ($now - intval($sent_time) <= $test_mail_period);
        }));

        if (count($test_mail_sent_times) < $test_mail_rate_limit) {
            // Send the e-mail
            send_email_immediate($name, $email, $subject, $full_message);

            $test_mail_sent_times[] = $now;
            $_SESSION['test_mail_sent_times'] = $test_mail_sent_times;
            $_SESSION['test_mail_sent'] = $now;

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['ATestEmailHasBeenSentUsingTheCurrentSettings']));
        } else {
            set_alert(true, "bad", $escaper->escapeHtml($lang['LimitedPeriodTestmailMessage']));
        }
    }

    global $escaper, $lang;

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
?>
<div class="row">
    <div class="col-12">
        <form name="mail_settings" method="post" action="">
            <div class="card-body my-2 border">
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['TransportAgent']); ?> :</label>
                            <select name="transport" id="transport" onchange="javascript: dropdown_transport()" class="form-select">
                                <option value="sendmail"<?= ($transport=="sendmail") ? " selected" : ""; ?>>sendmail</option>
                                <option value="smtp"<?= ($transport=="smtp") ? " selected" : ""; ?>>smtp</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['Prepend']); ?> :</label>
                            <input type="text" name="prepend" value="<?= $escaper->escapeHTML($prepend); ?>" class="form-control"/>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['FromName']); ?> :</label>
                            <input type="text" name="from_name" value="<?= $escaper->escapeHTML($from_name); ?>" class="form-control"/>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['ReplyToName']); ?> :</label>
                            <input type="text" name="replyto_name" value="<?= $escaper->escapeHTML($replyto_name); ?>" class="form-control"/>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['FromEmail']); ?> :</label>
                            <input type="email" name="from_email" value="<?= $escaper->escapeHTML($from_email); ?>" class="form-control"/>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['ReplyToEmail']); ?> :</label>
                            <input type="email" name="replyto_email" value="<?= $escaper->escapeHTML($replyto_email); ?>" class="form-control"/>
                        </div>
                    </div>
                </div>
                <div class="row smtp"<?= ($transport=="sendmail") ? " style='display: none;'" : "" ?>>
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['Host']); ?> :</label>
                            <input type="text" name="host" value="<?= $escaper->escapeHTML($host); ?>" class="form-control"/>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['Port']); ?> :</label>
                            <input type="number" name="port" value="<?= $escaper->escapeHTML($port); ?>" class="form-control"/>
                        </div>
                    </div>
                </div>
                <div class="row smtp"<?= ($transport=="sendmail") ? " style='display: none;'" : "" ?>>
                    <div class="col-6">
                        <div class="form-check mr-sm-2 form-group">
                            <input  type="checkbox" name="smtpautotls" id="smtpautotls" <?= ($smtpautotls == "true") ? "checked='yes' " : ""?> class="form-check-input"/>
                            <label class="form-check-label mb-0"><?= $escaper->escapeHtml($lang['EnableTLSEncryptionAutomaticallyIfAServerSupportsIt']); ?></label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check mr-sm-2 form-group">
                            <input  type="checkbox" name="smtpauth" id="smtpauth" onchange="javascript: checkbox_smtpauth()" <?= ($smtpauth == "true") ? "checked='yes' " : ""?> class="form-check-input"/>
                           <label class="form-check-label mb-0"><?= $escaper->escapeHTML($lang['SMTPAuthentication']); ?></label>
                        </div>
                    </div>
                </div>
                <div class="row smtpauth"<?= ($transport=="sendmail" || $smtpauth=="false") ? " style='display: none;'" : "" ?>>
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['Username']); ?> :</label>
                            <input type="text" name="username" value="<?= $escaper->escapeHTML($username); ?>" class="form-control"/>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['Password']); ?> :</label>
                            <input type="password" name="password" value="" placeholder="Change Current Value" class="form-control"/>
                        </div>
                    </div>
                </div>
                 <div class="row smtpauth"<?= ($transport=="sendmail" || $smtpauth=="false") ? " style='display: none;'" : "" ?>>
                    <div class="col-6">
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['Encryption']); ?> :</label>
                            <select name="encryption" id="encryption" class="form-select">
                                <option value="none"<?= ($encryption=="none") ? " selected" : ""; ?>>None</option>
                                <option value="tls"<?= ($encryption=="tls") ? " selected" : ""; ?>>TLS</option>
                                <option value="ssl"<?= ($encryption=="ssl") ? " selected" : ""; ?>>SSL</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <input type="submit" value="<?= $escaper->escapeHtml($lang['Submit']); ?>" name="submit_mail" class="btn btn-submit"/>
                    </div>
                </div>
            </div>
            <div class="card-body my-2 border">
                <u><strong><?= $escaper->escapeHtml($lang['TestMailSettings']); ?></strong></u>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <input type="email" name="email" size="50" placeholder="<?= $escaper->escapeHtml($lang['EmailAddress']); ?>" class="form-control"/>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="">
                           <button type="submit" name="test_mail_configuration" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Send']); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    $(document).ready(function() {
        $("[name='mail_settings']").submit(function() {

            // Prevent the form from submitting.
            event.preventDefault();

            // Get the submit button that triggered the event
            const submit_button = $(document.activeElement);
            const submit_button_name = submit_button.attr("name");
            const submit_button_value = submit_button.val();

            // Check if the test mail configuration button was clicked
            if (submit_button_name === "test_mail_configuration") {
                // Check if the email address is empty
                if (!$("[name='email']").val().trim()) {
                    showAlertFromMessage("<?= $escaper->escapeHtml($lang['PleaseEnterAnEmailAddressBeforeSendingATestEmail']); ?>");
                    return;
                }
            }

            // Append the clicked button's name and value to the form data
            $('<input>').attr({ type: 'hidden', name: submit_button_name, value: submit_button_value }).appendTo($(this));

            // Submit the form using native javascript.
            $(this)[0].submit();

        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
