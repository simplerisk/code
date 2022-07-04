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
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// If the General tab was submitted
if (isset($_POST['update_general_settings']))
{
    // Set the error to false
    $error = false;

    // Update the setting to enable pop-up windows for text boxes
    $enable_popup = (isset($_POST['enable_popup'])) ? 1 : 0;
    $current_enable_popup = get_setting("enable_popup");
    if ($enable_popup != $current_enable_popup)
    {
        update_setting("enable_popup", $enable_popup);
    }

    // Update the 'Automatically verify new assets' setting
    $auto_verify_new_assets = (isset($_POST['auto_verify_new_assets'])) ? 1 : 0;
    $current_auto_verify_new_assets = get_setting("auto_verify_new_assets");
    if ($auto_verify_new_assets != $current_auto_verify_new_assets)
    {
        update_setting("auto_verify_new_assets", $auto_verify_new_assets);
    }

    // Update the 'Document Exception update resets its approval' setting
    $exception_update_resets_approval = (isset($_POST['exception_update_resets_approval'])) ? 1 : 0;
    if ($exception_update_resets_approval != get_setting("exception_update_resets_approval"))
    {
        update_setting("exception_update_resets_approval", $exception_update_resets_approval);
    }

    // Update the 'Require a Risk Mapping for all risks' setting
    $risk_mapping_required = (isset($_POST['risk_mapping_required'])) ? 1 : 0;
    if ($risk_mapping_required != get_setting("risk_mapping_required"))
    {
        update_setting("risk_mapping_required", $risk_mapping_required);
    }

    // Update the alert timeout
    $alert_timeout = $_POST['alert_timeout'];
    if ($alert_timeout != get_setting("alert_timeout")) {
        // Update the base url
        update_setting("alert_timeout", $alert_timeout);
    }

    // Update the setting to show all risks for plan projects
    $plan_projects_show_all = (isset($_POST['plan_projects_show_all'])) ? 1 : 0;
    $current_plan_projects_show_all = get_setting("plan_projects_show_all");
    if ($plan_projects_show_all != $current_plan_projects_show_all)
    {
        update_setting("plan_projects_show_all", $plan_projects_show_all);
    }

    // Update the default language setting
    $default_language = get_name_by_value("languages", (int)$_POST['languages']);
    $current_default_language = get_setting("default_language");
    if ($default_language != $current_default_language)
    {
        update_setting("default_language", $default_language);
    }

    // Update the default timezone setting
    $default_timezone = $_POST['default_timezone'];
    $current_default_timezone = get_setting("default_timezone");
    if ($default_timezone != $current_default_timezone)
    {
        update_setting("default_timezone", $default_timezone);
    }

    // Update the default date format setting
    $default_date_format = $_POST['default_date_format'];
    $current_default_date_format = get_setting("default_date_format");
    if ($default_date_format != $current_default_date_format)
    {
        update_setting("default_date_format", $default_date_format);
    }

    // Update the default risk score setting
    $default_risk_score = (float)$_POST['default_risk_score'];
    $current_default_risk_score = get_setting("default_risk_score");
    if ($default_risk_score != $current_default_risk_score)
    {
        // If the default risk score is a numeric value between 0 and 10
        if (is_numeric($default_risk_score) && ($default_risk_score >= 0) && ($default_risk_score <= 10))
        {
            update_setting("default_risk_score", $default_risk_score);
        }
    }

    // Update the default risk score setting
    $maximum_risk_subject_length = (float)$_POST['maximum_risk_subject_length'];
    $current_maximum_risk_subject_length = get_setting("maximum_risk_subject_length");
    if ($maximum_risk_subject_length != $current_maximum_risk_subject_length) {
        // If the maximum_risk_subject_length is a numeric value between 0 and 1000
        if (is_numeric($maximum_risk_subject_length) && ($maximum_risk_subject_length > 0) && ($maximum_risk_subject_length <= 1000)) {
            update_setting("maximum_risk_subject_length", $maximum_risk_subject_length);
        }
    }

    // Update the default closed audit status setting
    $default_closed_audit_status = (int)$_POST['closed_audit_status'];
    $current_default_closed_audit_status = get_setting("closed_audit_status");
    if ($default_closed_audit_status != $current_default_closed_audit_status)
    {
        // If the default closed audit status is empty
        if (empty($default_closed_audit_status))
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['ClosedAuditStatusIsRequired']));
            $error = true;
        }
        else
        {
            update_setting("closed_audit_status", $default_closed_audit_status);
        }
    }

    // Update the default initiated audit status setting
    $default_initiated_audit_status = (int)$_POST['initiated_audit_status'];
    $current_default_initiated_audit_status = get_setting("initiated_audit_status");
    if ($default_initiated_audit_status != $current_default_initiated_audit_status)
    {
        update_setting("initiated_audit_status", $default_initiated_audit_status);
    }

    // Update the default currency setting
    $default_currency = $_POST['default_currency'];
    $current_default_currency = get_setting("currency");
    if ($default_currency != $current_default_currency)
    {
        // If the default currency is not empty
        if ($default_currency != "")
        {
            // If the default currency value is less than or equal to six characters long
            if (strlen($default_currency) <= 6)
            {
                // Update the currency
                update_setting("currency", $default_currency);
            }
        }
    }

    // Update the default asset valuation setting
    $default_asset_valuation = (int)$_POST['default_asset_valuation'];
    $current_default_asset_valuation = get_setting("default_asset_valuation");
    if ($default_asset_valuation != $current_default_asset_valuation)
    {
        // If the default asset valuation is numeric
        if (is_numeric($default_asset_valuation))
        {
            // If the default asset valuation is between 1 and 10
            if ($default_asset_valuation >= 1 && $default_asset_valuation <= 10)
            {
                // Update the default asset valuation
                update_setting("default_asset_valuation", $default_asset_valuation);
            }
        }
    }

    // Update the default user role setting
    $default_user_role = (int)$_POST['default_user_role'];
    $current_default_user_role = get_setting("default_user_role");
    if ($default_user_role != $current_default_user_role)
    {
        // Update the default user role
        update_setting("default_user_role", $default_user_role);
    }

    // Update the default current maturity setting
    $default_current_maturity = (int)$_POST['default_current_maturity'];
    $current_default_current_maturity = get_setting("default_current_maturity");
    if ($default_current_maturity != $current_default_current_maturity)
    {
        // Update the default current maturity
        update_setting("default_current_maturity", $default_current_maturity);
    }

    // Update the default desired maturity setting
    $default_desired_maturity = (int)$_POST['default_desired_maturity'];
    $current_default_desired_maturity = get_setting("default_desired_maturity");
    if ($default_desired_maturity != $current_default_desired_maturity)
    {
        // Update the default desired maturity
        update_setting("default_desired_maturity", $default_desired_maturity);
    }

    // Update the next review date setting
    $next_review_date_uses = $_POST['next_review_date_uses'];
    $current_next_review_date_uses = get_setting("next_review_date_uses");
    if ($next_review_date_uses != $current_next_review_date_uses)
    {
        // Update the default user role
        update_setting("next_review_date_uses", $next_review_date_uses);
    }

	// Update the highcharts delivery method setting
	$highcharts_delivery_method = $_POST['highcharts_delivery_method'];
	$current_highcharts_delivery_method = get_setting("highcharts_delivery_method");
	if ($highcharts_delivery_method != $current_highcharts_delivery_method)
	{
		// If the highcharts delivery method is cdn or local
		if ($highcharts_delivery_method == "cdn" || $highcharts_delivery_method == "local")
		{
			update_setting("highcharts_delivery_method", $highcharts_delivery_method);
		}
	}

        // Update the jquery delivery method setting
        $jquery_delivery_method = $_POST['jquery_delivery_method'];
        $current_jquery_delivery_method = get_setting("jquery_delivery_method");
        if ($jquery_delivery_method != $current_jquery_delivery_method)
        {
            // If the jquery delivery method is cdn or local
            if ($jquery_delivery_method == "cdn" || $jquery_delivery_method == "local")
            {
                update_setting("jquery_delivery_method", $jquery_delivery_method);
            }
        }

        // Update the bootstrap delivery method setting
        $bootstrap_delivery_method = $_POST['bootstrap_delivery_method'];
        $current_bootstrap_delivery_method = get_setting("bootstrap_delivery_method");
        if ($bootstrap_delivery_method != $current_bootstrap_delivery_method)
        {
            // If the bootstrap delivery method is cdn or local
            if ($bootstrap_delivery_method == "cdn" || $bootstrap_delivery_method == "local")
            {
                update_setting("bootstrap_delivery_method", $bootstrap_delivery_method);
            }
        }

        // Update the base url
        $simplerisk_base_url = $_POST['simplerisk_base_url'];
        $current_simplerisk_base_url = get_setting("simplerisk_base_url");
        if ($simplerisk_base_url != $current_simplerisk_base_url)
        {
            // If the base url is not empty
            if ($simplerisk_base_url != "" && is_valid_base_url($simplerisk_base_url))
            {
                // Update the base url
                update_setting("simplerisk_base_url", $simplerisk_base_url);

                $_SESSION['base_url'] = $simplerisk_base_url;
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidSimpleriskBaseUrl']));
                $error = true;
            }
        }

        // Update the Risk Appetite
        $risk_appetite = (float)$_POST['risk_appetite'];
        if ($risk_appetite != get_setting("risk_appetite") && $risk_appetite != "")
        {
            // Update the Risk Appetite
            update_setting("risk_appetite", $risk_appetite);
        }

        // If all setting values were saved successfully
        if (!$error)
        {
            // Display an alert
            set_alert(true, "good", "The settings were updated successfully.");
        }
    }

    // Check if a new file type was submitted
    if (isset($_POST['add_file_type']))
    {
        $name = $_POST['new_file_type'];
        $extension = $_POST['file_type_ext'];

        // Insert a new file type (250 chars) with extension (10 chars)
        $success = add_file_type($name, $extension);

        // If the add was successful
        if ($success)
        {
            // Display an alert
            set_alert(true, "good", "A new upload file type was added successfully.");
        }
    }

    // Check if a file type was deleted
    if (isset($_POST['delete_file_type']))
    {
               $value = (int)$_POST['file_types'];

                // Verify value is an integer
                if (is_int($value))
                {
                           delete_value("file_types", $value);

                           // Display an alert
                           set_alert(true, "good", "An existing upload file type was removed successfully.");
                }
        }

        // Check if a file type extension was deleted
        if (isset($_POST['delete_file_extension']))
        {
                $value = (int)$_POST['file_type_extensions'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("file_type_extensions", $value);

                        // Display an alert
                        set_alert(true, "good", "An existing upload file extension was removed successfully.");
                }
        }

        // Check if the maximum file upload size was updated
        if (isset($_POST['update_max_upload_size']))
        {
                   // Verify value is a numeric value
                   if (is_numeric($_POST['size']))
                   {
                           update_setting('max_upload_size', $_POST['size']);

                           // Get the currently set max upload size for SimpleRisk
                           $simplerisk_max_upload_size = get_setting('max_upload_size');

                           // If the max upload size for SimpleRisk is bigger than the PHP max upload size
                           if ($simplerisk_max_upload_size > php_max_allowed_values())
                           {
                                   // Display an alert
                                   set_alert(true, "bad", $escaper->escapeHtml($lang['WarnPHPUploadSize']));
                           }
                           // If the max upload size for SimpleRisk is bigger than the MySQL max_allowed_packet
                           else if ($simplerisk_max_upload_size > mysql_max_allowed_values())
                           {
                                   // Display an alert
                                   set_alert(true, "bad", $escaper->escapeHtml($lang['WarnMySQLUploadSize']));
                           }
                           else
                           {
                                   // Display an alert
                                   set_alert(true, "good", "The maximum upload file size was updated successfully.");
                           }
                   }
                   else
                   {
                           // Display an alert
                           set_alert(true, "bad", "The maximum upload file size needs to be an integer value.");
                   }
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
        if (isset($_POST['test_mail_configuration']))
        {
                // Set up the test email
                $name = "SimpleRisk Test";
                $email = $_POST['email'];
                $subject = "SimpleRisk Test Email";
                $full_message = "This is a test email from SimpleRisk.";

                // Send the e-mail
                send_email($name, $email, $subject, $full_message);

                // Display an alert
                set_alert(true, "good", "A test email has been sent using the current settings.");
        }

	// If the Backups tab was submitted
    if (isset($_POST['submit_backup']) || isset($_POST['submit_and_backup_now'])) {

		// Set the error to false
		$error = false;

		// Get the submitted backup_auto value
		$backup_auto = (isset($_POST['backup_auto']) ? "true" : "false");

		// If the backup_auto value has changed
		if ($backup_auto != get_setting("backup_auto")) {
			// Update the backup_auto setting
			update_setting("backup_auto", $backup_auto);
		}

		// Get the submitted backup_path value
		$backup_path = $_POST['backup_path'];

		// Remove any trailing slashes from the backup path
		$backup_path = rtrim($backup_path, "/");

		// If the backup_path value has changed
		if ($backup_path != get_setting("backup_path")) {
			// Get the actual path to the document root and backup directory
			$root_path = str_replace('/', '\\', realpath(__DIR__ . '/../'));
			$dir_path = str_replace('/', '\\', $backup_path);

			// If the backup file is not in the web root
			if (strpos($dir_path, $root_path) === false && $dir_path != "") {
				// Update the backup_path setting
				update_setting("backup_path", $backup_path);
			} else {
				// We have an error
				$error = true;
    			set_alert(true, "bad", $escaper->escapeHtml($lang['ForSecurityReasonsBackupOutsideWebRoot']));
			}
		}

		// Get the submitted backup_schedule value
		$backup_schedule = $_POST['backup_schedule'];

		// If the backup_schedule value has changed
		if ($backup_schedule != get_setting("backup_schedule")) {
			// If the backup schedule is hourly, daily, weekly or monthly
			if ($backup_schedule == "hourly" || $backup_schedule == "daily" || $backup_schedule == "weekly" || $backup_schedule == "monthly") {
				// Update the backup_schedule setting
				update_setting("backup_schedule", $backup_schedule);
			}
		}

		// Get the posted backup_remove value
		$backup_remove = (int)$_POST['backup_remove'];

		// If the backup_remove value has changed
		if ($backup_remove != get_setting("backup_remove")) {
			// If the backup_remove value is an integer value
			if (is_int($backup_remove)) {
				// Update the backup_remove setting
				update_setting("backup_remove", $backup_remove);
			}
		}

		// If we don't have an error
		if (!$error) {
			// Display an alert
        	set_alert(true, "good", "The settings were updated successfully.");
        	
        	$message = _lang('BackupSettingsUpdated', ['user_name' => $_SESSION['name']], false); 
        	write_log(0, $_SESSION['uid'], $message, 'backup');
        	
        	// If we should also do a backup
        	if (isset($_POST['submit_and_backup_now'])) {

        	    $message = _lang('BackupInitiatedByUser', ['user_name' => $_SESSION['name']], false);
        	    write_debug_log($message);
        	    write_log(0, $_SESSION['uid'], $message, 'backup');

        	    // Increasing the time for timeout
        	    set_time_limit(600);

        	    require_once(realpath(__DIR__ . '/../cron/cron_backup.php'));
        	    do_backup(true);
        	}
		}
	}
        
        // If the Security tab was submitted
        if (isset($_POST['update_security_settings']))
        {
                // Set the error to false
                $error = false;

        // Update the session activity timeout setting
        $session_activity_timeout = (int)$_POST['session_activity_timeout'];

        // If the session_activity_timeout value is at least 5 minutes
        if ($session_activity_timeout >= 300)
        {
            $current_session_activity_timeout = get_setting("session_activity_timeout");
            if ($session_activity_timeout != $current_session_activity_timeout)
            {
                update_setting("session_activity_timeout", $session_activity_timeout);
            }
        }
        else
        {
            $error = true;
            set_alert(true, "bad", "We do not recommend setting a session activity timeout less than 300 seconds.");
        }

        // Update the session absolute timeout setting
        $session_absolute_timeout = (int)$_POST['session_absolute_timeout'];

        // If the session_absolute_timeout value is less than the session_activity_timeout
        if ($session_absolute_timeout > get_setting("session_activity_timeout"))
        {
            $current_session_absolute_timeout = get_setting("session_absolute_timeout");
            if ($session_absolute_timeout != $current_session_absolute_timeout)
            {
                update_setting("session_absolute_timeout", $session_absolute_timeout);
            }
        }
        else
        {
            $error = true;
            set_alert(true, "bad", "The session absolute timeout should be more than the session activity timeout.");
        }

        // Update the content security policy
        $content_security_policy = isset($_POST['content_security_policy']) ? 1 : 0;
        $current_content_security_policy = get_setting("content_security_policy");
        if ($content_security_policy != $current_content_security_policy)
        {
            update_setting("content_security_policy", $content_security_policy);
        }

        // Update the SSL certificate check
        $ssl_certificate_check = isset($_POST['ssl_certificate_check']) ? 1 : 0;
        $current_ssl_certificate_check = get_setting("ssl_certificate_check");
        if ($ssl_certificate_check != $current_ssl_certificate_check)
        {
            update_setting("ssl_certificate_check", $ssl_certificate_check);
        }

        // Update the proxy settings
        $proxy_web_requests = isset($_POST['proxy_web_requests']) ? 1 : 0;
        $current_proxy_web_requests = get_setting("proxy_web_requests");
        if ($proxy_web_requests != $current_proxy_web_requests)
        {
            update_setting("proxy_web_requests", $proxy_web_requests);
        }

        // If proxy web requests is enabled
        if ($proxy_web_requests)
        {
            // Get the new proxy values
            $proxy_authenticated = isset($_POST['proxy_authenticated']) ? 1 : 0;
            $proxy_verify_ssl_certificate = isset($_POST['proxy_verify_ssl_certificate']) ? 1 : 0;
            $proxy_host = isset($_POST['proxy_host']) ? $_POST['proxy_host'] : "";
            $proxy_port = isset($_POST['proxy_port']) ? $_POST['proxy_port'] : "";
            $proxy_user = isset($_POST['proxy_user']) ? $_POST['proxy_user'] : "";
            $proxy_pass = isset($_POST['proxy_pass']) ? $_POST['proxy_pass'] : "";

            // Get the current proxy values
            $current_proxy_authenticated = get_setting("proxy_authenticated");
            $current_proxy_verify_ssl_certificate = get_setting("proxy_verify_ssl_certificate");
            $current_proxy_host = get_setting("proxy_host");
            $current_proxy_port = get_setting("proxy_port");
            $current_proxy_user = get_setting("proxy_user");
            $current_proxy_pass = get_setting("proxy_pass");

            // Update the proxy settings
            if ($proxy_authenticated != $current_proxy_authenticated)
            {
                update_setting("proxy_authenticated", $proxy_authenticated);
            }

            if ($proxy_verify_ssl_certificate != $current_proxy_verify_ssl_certificate)
            {
                update_setting("proxy_verify_ssl_certificate", $proxy_verify_ssl_certificate);
            }

            if ($proxy_host != $current_proxy_host)
            {
		// If this is a valid IP or domain name
		if (filter_var($proxy_host, FILTER_VALIDATE_IP) || filter_var($proxy_host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))
		{
                    update_setting("proxy_host", $proxy_host);
		}
            }

            if ($proxy_port != $current_proxy_port)
            {
		// Set the minimum and maximum port range
		$options = array("options" => array("min_range"=>0, "max_range"=>65535));

		// If this is a valid integer value
		if (filter_var($proxy_port, FILTER_VALIDATE_INT, $options))
		{
                    update_setting("proxy_port", $proxy_port);
		}
            }

            if ($proxy_user != $current_proxy_user)
            {
                update_setting("proxy_user", $proxy_user);
            }

            // If the proxy password has been changed
            if ($proxy_pass != "XXXXXXXXXX" && $proxy_pass != $current_proxy_pass)
            {
                update_setting("proxy_pass", $proxy_pass);
            }
        }

                // If all setting values were saved successfully
                if (!$error)
                {
                        // Display an alert
                        set_alert(true, "good", "The settings were updated successfully.");
                }
    }

    // If the Debug tab was submitted
    if (isset($_POST['update_debug_settings']))
    {
        // Set the error to false
        $error = false;

        // Update the debug logging
        $debug_logging = isset($_POST['debug_logging']) ? 1 : 0;
        $current_debug_logging = get_setting("debug_logging");
        if ($debug_logging != $current_debug_logging)
        {
            update_setting("debug_logging", $debug_logging);
        }

        // Update the debug log file
        $debug_log_file = $_POST['debug_log_file'];
        $root_path = str_replace('/', '\\', $_SERVER["DOCUMENT_ROOT"]);
        $log_path = str_replace('/', '\\', realpath(dirname($debug_log_file)));
        if(strpos($log_path, $root_path) === false && $log_path != "") {
            $current_debug_log_file = get_setting("debug_log_file");
            if ($debug_log_file != $current_debug_log_file)
            {
                update_setting("debug_log_file", $debug_log_file);
            }
        } elseif($log_path == "") {
            $error = true;
            set_alert(true, "bad", "No such directory.");
        } else {
            $error = true;
            set_alert(true, "bad", "Cannot be write log file to the web root directory.");
        }

        // If all setting values were saved successfully
        if (!$error)
        {
                // Display an alert
                set_alert(true, "good", "The settings were updated successfully.");
        }
    }

    // Get the max upload size setting
    $simplerisk_max_upload_size = get_setting('max_upload_size');
?>

<!doctype html>
<html>

  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
    <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>

    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" type="text/css" href="../css/jquery-ui.min.css?<?php echo current_version("app"); ?>" />
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/settings_tabs.css?<?php echo current_version("app"); ?>">
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>    
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
  </head>

  <body>

<?php
    display_license_check();

    view_top_menu("Configure");

    // Get any alert messages
    get_alert();
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("Settings"); ?>
        </div>
        <div class="span9">
          <div class="wrap">
            <ul class="tabs group">
              <li><a class="active" href="#/general"><?php echo $escaper->escapeHtml($lang['General']); ?></a></li>
              <li><a href="#/files"><?php echo $escaper->escapeHtml($lang['FileUpload']); ?></a></li>
              <li><a href="#/mail"><?php echo $escaper->escapeHtml($lang['Mail']); ?></a></li>
              <li><a href="#/backups"><?php echo $escaper->escapeHtml($lang['Backups']); ?></a></li>
              <li><a href="#/security"><?php echo $escaper->escapeHtml($lang['Security']); ?></a></li>
              <li><a href="#/debug"><?php echo $escaper->escapeHtml($lang['Debugging']); ?></a></li>
              <!--<li><a href="#/extras">Extras</a></li>-->
            </ul>
            <div id="content">
              <form name="settings" method="post" action="">

              <!-- General Setting Tab -->
              <div id="general" class="settings_tab">
                <form name="general_settings" method="post" action="">
                  <table border="1" width="600" cellpadding="10px">
                    <tbody>
                    <tr>
                      <td>
                        <table border="0" width="100%">
                          <tbody>
                            <tr>
                              <td colspan="2"><u><strong><?php echo $escaper->escapeHtml($lang['UserInterface']); ?></strong></u></td>
                            </tr>
                            <tr>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('enable_popup')) == 1){ echo "checked"; } ?> name="enable_popup" class="hidden-checkbox" size="2" value="90" id="enable_popup" type="checkbox">  <label for="enable_popup"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['EnablePopupWindowsForTextBoxes']); ?></label></td>
                            </tr>
                            <tr>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('plan_projects_show_all')) == 1){ echo "checked"; } ?> name="plan_projects_show_all" class="hidden-checkbox" size="2" value="90" id="plan_projects_show_all" type="checkbox">  <label for="plan_projects_show_all"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['ShowAllRisksForPlanProjects']); ?></label></td>
                            </tr>
                            <tr>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('auto_verify_new_assets')) == 1){ echo "checked"; } ?> name="auto_verify_new_assets" class="hidden-checkbox" size="2" value="90" id="auto_verify_new_assets" type="checkbox">  <label for="auto_verify_new_assets"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['AutomaticallyVerifyNewAssets']); ?></label></td>
                            </tr>
                            <tr>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('exception_update_resets_approval')) == 1){ echo "checked"; } ?> name="exception_update_resets_approval" class="hidden-checkbox" size="2" value="90" id="exception_update_resets_approval" type="checkbox"><label for="exception_update_resets_approval">&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['ExceptionUpdateResetsApproval']); ?></label></td>
                            </tr>
                            <tr>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('risk_mapping_required')) == 1){ echo "checked"; } ?> name="risk_mapping_required" class="hidden-checkbox" size="2" value="90" id="risk_mapping_required" type="checkbox"><label for="risk_mapping_required">&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['RequireRiskMappingForAllRisks']); ?></label></td>
                            </tr>
                            <tr>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['AlertTimeout']); ?>:</td>
                              <td>
                                <?php
                                    echo "<select id=\"alert_timeout\" name=\"alert_timeout\" class=\"form-field\" style=\"width:auto;\">\n";

                                    // Create the list of possible timeouts
                                    $possible_timeouts = array(
                                        "5"     => _lang('TimeoutXSeconds', array('timeout' => '5')),
                                        "10"    => _lang('TimeoutXSeconds', array('timeout' => '10')),
                                        "15"    => _lang('TimeoutXSeconds', array('timeout' => '15')),
                                        "30"    => _lang('TimeoutXSeconds', array('timeout' => '30')),
                                        "60"    => _lang('TimeoutXSeconds', array('timeout' => '60')),
                                        "0"     => $lang['StayUntilClicked'],
                                    );

                                    // Get the current value
                                    $alert_timeout = get_setting("alert_timeout", "5");

                                    // For each possible timeout
                                    foreach($possible_timeouts as $key => $value)
                                    {
                                        echo "<option value=\"" . $key . "\"" . ($key == $alert_timeout ? " selected" : "") . ">" . $escaper->escapeHtml($value) . "</option>\n";
                                    }

                                    echo "</select>\n";
                                ?>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                    </tbody>
                  </table>
                    <br />
                    <table border="1" width="600" cellpadding="10px">
                    <tbody>
                    <tr>
                      <td>
                        <table border="0" width="100%">
                          <tbody>
                            <tr>
                              <td colspan="2"><u><strong><?php echo $escaper->escapeHtml($lang['DefaultValues']); ?></strong></u></td>
                            </tr>
                            <tr>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['DefaultLanguage']); ?>:</td>
                              <td><?php create_dropdown("languages", get_value_by_name("languages", $escaper->escapeHtml(get_setting("default_language"))), null, false); ?></td>
                            </tr>
                            <tr>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['DefaultTimezone']); ?>:</td>
                              <td>
                                <?php
                                    echo "<select id=\"default_timezone\" name=\"default_timezone\" class=\"form-field\" style=\"width:auto;\">\n";

                                    // Get the list of timezones
                                    $timezones = timezone_list();

                                    // Get the defeault timezone
                                    $default_timezone = $escaper->escapeHtml(get_setting("default_timezone"));

                                    // For each timezone
                                    foreach($timezones as $key => $value)
                                    {
                                        echo "<option value=\"" . $key . "\"" . ($key == $default_timezone ? " selected" : "") . ">" . $value . "</option>\n";
                                    }

                                    echo "</select>\n";
                                ?>
                              </td>
                            </tr>
                            <tr>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['DefaultDateFormat']); ?>:</td>
                              <td>
                                <?php
                                    // Get the defeault date format
                                    $default_date_format = $escaper->escapeHtml(get_setting("default_date_format"));

                                    create_dropdown("date_formats", $default_date_format, "default_date_format", false);
                                ?>
                              </td>
                            </tr>
                            <tr>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['DefaultRiskScore']); ?>:</td>
                              <td><input value="<?php echo $escaper->escapeHtml(get_setting('default_risk_score')); ?>" name="default_risk_score" id="default_risk_score" type="number" min="0" step="0.1" max="10" /></td>
                            </tr>
                            <tr>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['MaximumRiskSubjectLength']); ?>:</td>
                              <td><input value="<?php echo $escaper->escapeHtml(get_setting('maximum_risk_subject_length')); ?>" name="maximum_risk_subject_length" id="maximum_risk_subject_length" type="number" min="1" step="1" max="1000" /></td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['DefaultInitiatedAuditStatus']) ?>:</td>
                              <td><?php create_dropdown("test_status", $escaper->escapeHtml(get_setting("initiated_audit_status")), "initiated_audit_status", true, false, false, "", "--", 0); ?></td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['DefaultClosedAuditStatus']) ?>:</td>
                              <td><?php create_dropdown("test_status", $escaper->escapeHtml(get_setting("closed_audit_status")), "closed_audit_status", false, false, false, "required"); ?></td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['DefaultCurrencySymbol']) ?>:</td>
                              <td><input type="text" name="default_currency" maxlength="3" value="<?php echo $escaper->escapeHtml(get_setting("currency")); ?>" /></td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['DefaultAssetValuation']) ?>:</td>
                              <td>
                                <?php
                                  // Get the default asset valuation
                                  $default = get_default_asset_valuation();

                                  // Create the asset valuation dropdown
                                  create_asset_valuation_dropdown("default_asset_valuation", $default);
                                ?>
                              </td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['DefaultUserRole']) ?>:</td>
                              <td>
                                <?php
                                  // Create role dropdown
                                  create_dropdown("role", $escaper->escapeHtml(get_setting("default_user_role")), "default_user_role");
                                ?>
                              </td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['DefaultCurrentMaturity']) ?>:</td>
                              <td>
                                <?php
                                  // Create default current maturity dropdown
                                  create_dropdown("control_maturity", $escaper->escapeHtml(get_setting("default_current_maturity")), "default_current_maturity", false);
                                ?>
                              </td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['DefaultDesiredMaturity']) ?>:</td>
                              <td>
                                <?php
                                  // Create default desired maturity dropdown
                                  create_dropdown("control_maturity", $escaper->escapeHtml(get_setting("default_desired_maturity")), "default_desired_maturity", false);
                                ?>
                              </td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['NextReviewDateUses']) ?>:</td>
                              <td>
                                <select name="next_review_date_uses">
                                    <option value="InherentRisk" <?php echo $escaper->escapeHtml(get_setting("next_review_date_uses")) == "InherentRisk" ? "selected" : ""; ?> ><?php echo $escaper->escapeHtml($lang['InherentRisk']); ?></option>
                                    <option value="ResidualRisk" <?php echo $escaper->escapeHtml(get_setting("next_review_date_uses")) == "ResidualRisk" ? "selected" : ""; ?>><?php echo $escaper->escapeHtml($lang['ResidualRisk']); ?></option>
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['HighChartsDeliveryMethod']) ?>:</td>
                              <td>
                                <select name="highcharts_delivery_method">
                                    <option value="cdn" <?php echo $escaper->escapeHtml(get_setting("highcharts_delivery_method")) == "cdn" ? "selected" : ""; ?> >HighCharts CDN</option>
                                    <option value="local" <?php echo $escaper->escapeHtml(get_setting("highcharts_delivery_method")) == "local" ? "selected" : ""; ?> >Local</option>
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['jQueryDeliveryMethod']) ?>:</td>
                              <td>
                                <select name="jquery_delivery_method">
                                    <option value="cdn" <?php echo $escaper->escapeHtml(get_setting("jquery_delivery_method")) == "cdn" ? "selected" : ""; ?> >jQuery CDN</option>
                                    <option value="local" <?php echo $escaper->escapeHtml(get_setting("jquery_delivery_method")) == "local" ? "selected" : ""; ?> >Local</option>
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['BootstrapDeliveryMethod']) ?>:</td>
                              <td>
                                <select name="bootstrap_delivery_method">
                                    <option value="cdn" <?php echo $escaper->escapeHtml(get_setting("bootstrap_delivery_method")) == "cdn" ? "selected" : ""; ?> >jsDelivr CDN</option>
                                    <option value="local" <?php echo $escaper->escapeHtml(get_setting("bootstrap_delivery_method")) == "local" ? "selected" : ""; ?> >Local</option>
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['SimpleriskBaseUrl']) ?>:</td>
                              <td><input type="text" name="simplerisk_base_url" value="<?php echo $escaper->escapeHtml(get_setting("simplerisk_base_url")); ?>" /></td>
                            </tr>
                            <tr> 
                              <td><?php echo $escaper->escapeHtml($lang['RiskAppetite']) ?>:</td>
                              <td class="risk-cell sorting_1">
                                <?php $risk_appetite = (float)get_setting("risk_appetite", 0);?>
                                <div>
                                    <span id="risk_appetite_display" style="border:0; font-weight:bold;"><?php echo $escaper->escapeHtml($risk_appetite); ?></span>
                                    <span id="risk_appetite_color" class="risk-color" style="background-color:#ff0000"></span>
                                </div>
                              </td>
                            </tr>
                            <tr>
                              <td colspan="2">
                                <input type="hidden" id="risk_appetite" name="risk_appetite" value="<?php echo $escaper->escapeHtml($risk_appetite); ?>">
                                <?php
                                    $risk_levels = get_risk_levels();

                                    if ((int)$risk_levels[0]['value'] > 0) {
                                        array_unshift($risk_levels, array('value' => 0.0, 'name' => 'Insignificant', 'color' => 'white', 'display_name' => $lang['Insignificant']));
                                    }

                                    $ranges = [];
                                    $number_of_levels = count($risk_levels);
                                    foreach($risk_levels as $key => $level) {
                                        $next_key = ($key + 1 < $number_of_levels) ? $key + 1 : null;
                                        $ranges[] = array('display_name' => $level['display_name'],
                                                            'color' => $level['color'],
                                                            'range' => [(int)$level['value'], $next_key ? $risk_levels[$next_key]['value'] - 0.1 : 9999]);
                                    }

                                    foreach($ranges as $key => $range) {
                                        if ($key == 0)
                                            $slider_bg_grad = "{$range['color']} " . ($range['range'][1] * 10) . "%";
                                        elseif ($key == count($ranges) - 1) {
                                            $slider_bg_grad .= ", {$range['color']} " . ($ranges[$key - 1]['range'][1] * 10) . "%, {$range['color']} 100%";
                                        } else {
                                            $slider_bg_grad .= ", {$range['color']} " . ($ranges[$key - 1]['range'][1] * 10) . "%, {$range['color']} " . ($range['range'][1] * 10) . "%";
                                        }
                                    }
                                ?>
                                <div id="slider" style="margin-top: 10px; background-image: linear-gradient(90deg, <?php echo $slider_bg_grad; ?>); background-size: 100% 100%;"></div>
                                  <script>
                                    <?php echo "var ranges = " . json_encode($ranges) . ";"; ?>

                                    function handleValueInRange(value) {
                                        var index, len;
                                        for (index = 0, len = ranges.length; index < len; ++index) {
                                            if (ranges[index]['range'][0] <= value && ranges[index]['range'][1] >= value) {
                                                $("#risk_appetite_display").text(ranges[index]['display_name'] + " (" + value + ")");
                                                $("#risk_appetite").val(value);
                                                $("#risk_appetite_color").css('background-color', ranges[index]['color']);

                                                return;
                                            }
                                        }
                                    }

                                    $(document).ready(function() {
                                      $("#slider").slider({
                                        value:<?php echo $risk_appetite * 10; ?>,
                                        min: 0,
                                        max: 100,
                                        step: 1,
                                        create: function() {
                                          handleValueInRange($("#slider").slider("value") / 10);
                                        },
                                        slide: function(event, ui) {
                                            handleValueInRange(ui.value / 10);
                                        }
                                      });
                                    });
                                </script>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                    </tbody>
                    </table>
                    <br />
                    <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_general_settings" />
                </form>
              </div>

              <!-- File Upload Setting Tab -->
              <div id="files" style="display: none;" class="settings_tab">
                    <table border="1" width="600" cellpadding="10px">
                    <tbody>
                    <tr>
                      <td>
                        <form name="filetypes" method="post" action="">
                        <table border="0" width="100%">
                          <tbody>
                            <tr>
                              <td><h4><?php echo $escaper->escapeHtml($lang['AllowedFileTypes']); ?>:</h4></td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['AddNewFileTypeOf']); ?> <input name="new_file_type" type="text" maxlength="250" size="10" style="width: 200px;" />&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['WithExtension']); ?>&nbsp;&nbsp;<input name="file_type_ext" type="text" maxlength="10" size="10" style="width: 50px;" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_file_type" /></td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['DeleteCurrentFileTypeOf']); ?> <?php create_dropdown("file_types"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_file_type" /></td>
                            </tr>
                            <tr>
                              <td><?php echo $escaper->escapeHtml($lang['DeleteCurrentExtensionOf']); ?> <?php create_dropdown("file_type_extensions"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_file_extension" /></td>
                            </tr>
                          </tbody>
                        </table>
                        </form>
                      </td>
                    </tr>
                    </tbody>
                    </table>
                    <br />
                    <table border="1" width="600" cellpadding="10px">
                    <tbody>
                    <tr>
                      <td>
                        <form name="filesize" method="post" action="">
                        <table border="0" width="100%">
                          <tbody>
                            <tr>
                              <td><h4><?php echo $escaper->escapeHtml($lang['MaximumUploadFileSize']); ?>:</h4></td>
                            </tr>
                            <tr>
                              <td><input name="size" type="number" maxlength="50" size="20" value="<?php echo $escaper->escapeHtml(get_setting('max_upload_size')); ?>" />&nbsp;<?php echo $escaper->escapeHtml($lang['Bytes']); ?><br />
                <?php
                        // If the max upload size for SimpleRisk is bigger than the PHP max upload size
                        if($simplerisk_max_upload_size > php_max_allowed_values())
                        {
                                echo "<font style=\"color: red;\">" . $escaper->escapeHtml($lang['WarnPHPUploadSize']) . '<br />';
                        }

                        // If the max upload size for SimpleRisk is bigger than the MySQL max upload size
                        if ($simplerisk_max_upload_size > mysql_max_allowed_values())
                        {
                                echo "<font style=\"color: red;\">" . $escaper->escapeHtml($lang['WarnMySQLUploadSize']) . '<br />';
                        }
                ?>
                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_max_upload_size" /></td>
                            </tr>
                          </tbody>
                        </table>
                        </form>
                      </td>
                    </tr>
                    </tbody>
                    </table>
              </div>

              <!-- Mail Setting Tab -->
              <div id="mail" style="display: none;" class="settings_tab">
                <form name="mail_settings" method="post" action="">
        <?php
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
                  <table border="1" width="600" cellpadding="10px">
                  <tbody>
                  <tr>
                    <td>
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
                          <td><input type="email" name="from_email" value="<?php echo $escaper->escapeHTML($from_email); ?>" /></td>
                        </tr>
                        <tr>
                          <td><?php echo $escaper->escapeHTML($lang['ReplyToName']); ?>:&nbsp;&nbsp;</td>
                          <td><input type="text" name="replyto_name" value="<?php echo $escaper->escapeHTML($replyto_name); ?>" /></td>
                        </tr>
                        <tr>
                          <td><?php echo $escaper->escapeHTML($lang['ReplyToEmail']); ?>:&nbsp;&nbsp;</td>
                          <td><input type="email" name="replyto_email" value="<?php echo $escaper->escapeHTML($replyto_email); ?>" /></td>
                        </tr>
                        <tr>
                          <td><?php echo $escaper->escapeHTML($lang['Prepend']); ?>:&nbsp;&nbsp;</td>
                          <td><input type="text" name="prepend" value="<?php echo $escaper->escapeHTML($prepend); ?>" /></td>
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
                          <td colspan="2"><input type="checkbox" name="smtpautotls" id="smtpautotls" <?php echo ($smtpautotls == "true") ? "checked=\"yes\" " : ""?>/>&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['EnableTLSEncryptionAutomaticallyIfAServerSupportsIt']); ?></td>
                        </tr>
                        <tr class="smtp"<?php echo ($transport=="sendmail") ? " style=\"display: none;\"" : "" ?>>
                          <td colspan="2"><input type="checkbox" name="smtpauth" id="smtpauth" onchange="javascript: checkbox_smtpauth()" <?php echo ($smtpauth == "true") ? "checked=\"yes\" " : ""?>/>&nbsp;&nbsp;<?php echo $escaper->escapeHTML($lang['SMTPAuthentication']); ?></td>
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
                  </td>
                </tr>
                </tbody>
                </table>

                <br />

                <table border="1" width="600" cellpadding="10px">
                <tbody>
                  <tr>
                    <td>
                      <table name="mail" id="mail" border="0">
                        <tr>
                          <td>
                            <u><strong><?php echo $escaper->escapeHtml($lang['TestMailSettings']); ?></strong></u></td>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <input type="text" name="email" size="50" placeholder="<?php echo $escaper->escapeHtml($lang['EmailAddress']); ?>"/>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <button type="submit" name="test_mail_configuration" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['Send']); ?></button>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </tbody>
                </table>

                </form>
              </div>


              <!-- Backups Setting Tab -->
              <div id="backups" style="display: none;" class="settings_tab">
        <?php
                // Get the backup settings
		$backup_auto = get_setting('backup_auto');
		$backup_path= get_setting('backup_path');
		$phpExecutablePath = getPHPExecutableFromPath();
        ?>
                  <table border="1" width="800" cellpadding="10px">
                  <tbody>
                  <tr>
                    <td>
                      <table name="backup" id="backup" border="0">
            			<tr><td><h4><u><?php echo $escaper->escapeHtml($lang['Instructions']); ?></u></h4></td></tr>
            			<tr><td><?php echo $escaper->escapeHtml($lang['PlaceTheFollowingInYourCrontabToRunAutomatically']); ?>:</td></tr>
            			<tr><td>* * * * * <?php echo $escaper->escapeHtml($phpExecutablePath ? $phpExecutablePath : $lang['PathToPhpExecutable']); ?> <?php echo (strncasecmp(PHP_OS, 'WIN', 3) == 0 ? "" : "-f") ?> <?php echo realpath(__DIR__ . '/../cron/cron.php'); ?> > /dev/null 2>&1</td></tr>
                      </table>
                    </td>
                  </tr>
                  </tbody>
                  </table>

                  <br />

                  <table border="1" width="800" cellpadding="10px">
                  <tbody>
                  <tr>
                    <td>
                      <form name="backups_settings" method="post" action="">
                      <table name="backup" id="backup" border="0">
                        <tr>
                          <td colspan="2"><input type="checkbox" name="backup_auto" id="backup_auto" <?php echo ($backup_auto == "true") ? "checked=\"yes\" " : ""?>/>&nbsp;&nbsp;<?php echo $escaper->escapeHTML($lang['AutomaticallyBackupThisSimpleRiskInstance']); ?></td>
                        </tr>
                        <tr>
                        <td colspan="2"><font style="color: red;"><?php echo $escaper->escapeHtml($lang['ForSecurityReasonsBackupOutsideWebRoot']); ?></font></td>
                        </tr>
						<tr>
                          <td><?php echo $escaper->escapeHtml($lang['BackupLocation']); ?>:</td>
                          <td><input name="backup_path" id="backup_path" type="text" size="50px" value="<?php echo $escaper->escapeHtml($backup_path); ?>" /></td>
                        </tr>
						<tr>
			  			  <td colspan="2"><?php echo $escaper->escapeHTML($lang['BackupSchedule']); ?>:&nbsp;&nbsp;
                            <select id="backup_schedule" name="backup_schedule" style="width: 100px !important; min-width: 100px; max-width: 100px;">
                              <option value="hourly"<?php echo (get_setting('backup_schedule') == "hourly") ? " selected" : ""; ?>><?php echo $escaper->escapeHTML($lang['Hourly']); ?></option>
            			      <option value="daily"<?php echo (get_setting('backup_schedule') == "daily") ? " selected" : ""; ?>><?php echo $escaper->escapeHTML($lang['Daily']); ?></option>
            			      <option value="weekly"<?php echo (get_setting('backup_schedule') == "weekly") ? " selected" : ""; ?>><?php echo $escaper->escapeHTML($lang['Weekly']); ?></option>
                              <option value="monthly"<?php echo (get_setting('backup_schedule') == "monthly") ? " selected" : ""; ?>><?php echo $escaper->escapeHTML($lang['Monthly']); ?></option>
                            </select>
                          </td>
                        </tr>
						<tr>
			  			  <td colspan="2">
			  				<?php echo $escaper->escapeHTML($lang['RemoveBackupsAfter']); ?>&nbsp;<input type="number" name="backup_remove" id="backup_remove" value="<?php echo $escaper->escapeHTML(get_setting('backup_remove')); ?>" min="1" max="365" style="width: 75px !important; min-width: 75px; max-width: 75px;"/>&nbsp;<?php echo $escaper->escapeHTML($lang['days']); ?>
			  			  </td>
			  			</tr>
		      		  </table>
                      <br />
                      <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Save']); ?>" name="submit_backup" />
                      <input type="submit" value="<?php echo $escaper->escapeHtml($lang['SaveAndBackupNow']); ?>" name="submit_and_backup_now" />
                      </form>
                      
                      <script>
                          function blockWithInfoMessage(message) {
                              toastr.options = {
                                  "timeOut": "0",
                                  "extendedTimeOut": "0",
                              }
    
                              $('body').block({
                            	  baseZ: 100000, // So the block covers the header too thats z-index is 99999
                                  message: "<?php echo $escaper->escapeHtml($lang['Processing']); ?>",
                                  css: { border: '1px solid black' }
                              });
                              setTimeout(function(){ toastr.info(message); }, 1);
                          }
                        $(document).ready(function() {
                            $("input[name='submit_and_backup_now']").click(function(evt) {
                                blockWithInfoMessage("<?php echo $escaper->escapeHtml($lang['BackupInitiated']); ?>");
                                return true;
                            });
                        });
                      </script>
                    </td>
                  </tr>
                  </tbody>
                  </table>

                  <br />

                  <table border="1" width="800" cellpadding="10px">
                  <tbody>
                  <tr>
                    <td>

                      <?php
				// Open the database connection
				$db = db_open();

				// Get the list of backups ordered by timestamp
				$stmt = $db->prepare("SELECT * FROM `backups` ORDER BY `timestamp` DESC;");
				$stmt->execute();
				$backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

				// Close the database connection
				db_close($db);
                      ?>


                      <table name="backups" id="backups" border="0">
                        <tr><td colspan='5'><h4><u><?php echo $escaper->escapeHtml($lang['Backups']); ?></u></h4></td></tr>
                        <tr>
                        <td colspan="5"><font style="color: red;"><?php echo $escaper->escapeHtml($lang['PrivateTmpMessage']); ?></font></td>
                        </tr>
			<tr>
			<td><u><?php echo $escaper->escapeHtml($lang['BackupDate']); ?></u></td>
                          <td width="20px">&nbsp;</td>

                      <?php
				// If this is not a hosted customer
				if (get_setting('hosting_tier') == false)
				{
					echo "<td><u>" . $escaper->escapeHtml($lang['ApplicationBackup']) . "</u></td>\n";
					echo "<td width=\"20px\">&nbsp;</td>\n";
				}
				else
				{
					echo "<td width=\"0px\">&nbsp;</td>\n";
					echo "<td width=\"0px\">&nbsp;</td>\n";
				}
                      ?>
                          <td><u><?php echo $escaper->escapeHtml($lang['DatabaseBackup']); ?></u></td>
                        </tr>

                      <?php
				// For each backup
				foreach ($backups as $backup)
				{
					// Display the backup information
					echo "<tr>\n";
					echo "  <td>" . $escaper->escapeHtml($backup['timestamp']) . "</td>\n";
					echo "  <td>&nbsp;</td>\n";
					
					// If this is not a hosted customer
                                	if (get_setting('hosting_tier') == false)
                                	{
						// Display the Download link for the application backup
						echo "<td><a target=\"_blank\" href=\"download_backup.php?type=app&id=" . $escaper->escapeHtml($backup['random_id']) . "\">" . $escaper->escapeHtml($lang['Download']) . "</a></td>\n";
						echo "  <td>&nbsp;</td>\n";
                                	}
					// If this is a hosted customer
                                	else
                                	{
						// Do not display a Download link for the application backup
                                        	echo "<td width=\"0px\">&nbsp;</td>\n";
                                        	echo "<td width=\"0px\">&nbsp;</td>\n";
                                	}

					echo "<td><a target=\"_blank\" href=\"download_backup.php?type=db&id=" . $escaper->escapeHtml($backup['random_id']) . "\">" . $escaper->escapeHtml($lang['Download']) . "</a></td>\n";
					echo "</tr>\n";
				}
                      ?>

                      </table>
                    </td>
                  </tr>
                  </tbody>
		  </table>

              </div>

              <!-- Security Setting Tab -->
              <div id="security" style="display: none;" class="settings_tab">
                <form name="security_settings" method="post" action="">
                    <table border="1" width="600" cellpadding="10px">
                    <tbody>
                    <tr>
                      <td>
                        <table border="0" width="100%">
                          <tbody>
                        <tr>
                          <td colspan="2"><u><strong><?php echo $escaper->escapeHtml($lang['UserSessions']); ?></strong></u></td>
                        </tr>
                        <tr>
                          <td width="300px"><?php echo $escaper->escapeHtml($lang['SessionActivityTimeout']) . " (" . $escaper->escapeHtml($lang["seconds"]) . ")"; ?>:</td>
                          <td><input name="session_activity_timeout" id="session_activity_timeout" type="number" min="0" size="20px" value="<?php echo $escaper->escapeHtml(get_setting("session_activity_timeout")); ?>" /></td>
                        </tr>
                        <tr>
                          <td width="300px"><?php echo $escaper->escapeHtml($lang['SessionAbsoluteTimeout']) . " (" . $escaper->escapeHtml($lang["seconds"]) . ")"; ?>:</td>
                          <td><input name="session_absolute_timeout" id="session_absolute_timeout" type="number" min="0" size="20px" value="<?php echo $escaper->escapeHtml(get_setting("session_absolute_timeout")); ?>" /></td>
                        </tr>
                      </tbody>
                    </table>
                      </td>
                    </tr>
                    </tbody>
                    </table>
                    <br />
                    <table border="1" width="600" cellpadding="10px">
                    <tbody>
                    <tr>
                      <td>
                        <table border="0" width="100%">
                          <tbody>
                            <tr>
                              <td colspan="2"><u><strong><?php echo $escaper->escapeHtml($lang['Security']); ?></strong></u></td>
                            </tr>
                            <tr>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('content_security_policy')) == 1){ echo "checked"; } ?> name="content_security_policy" class="hidden-checkbox" size="2" value="90" id="content_security_policy" type="checkbox">  <label for="content_security_policy"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['EnableCSP']); ?></label></td>
                            </tr>
                            <tr>
                                <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('ssl_certificate_check')) == 1){ echo "checked"; } ?> name="ssl_certificate_check" class="hidden-checkbox" size="2" value="90" id="ssl_certificate_check" type="checkbox">  <label for="ssl_certificate_check"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['EnableSSLCertificateCheck']); ?></label></td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                    </tbody>
                    </table>
                    <br />
                    <table border="1" width="600" cellpadding="10px">
                    <tbody>
                    <tr>
                      <td>
                        <table border="0" width="100%">
                          <tbody>
                            <tr>
                              <td colspan="2"><u><strong><?php echo $escaper->escapeHtml($lang['Proxy']); ?></strong></u></td>
                            </tr>
                            <tr>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('proxy_web_requests')) == 1){ echo "checked"; } ?> name="proxy_web_requests" class="hidden-checkbox" id="proxy_web_requests_checkbox" type="checkbox" onclick="update_proxy()">  <label for="proxy_web_requests_checkbox"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['ProxyWebRequests']); ?></label></td>
                            </tr>
                            <tr id="proxy_verify_ssl_certificate_row" <?php echo (get_setting('proxy_web_requests') != 1 ? " style='display: none;'" : ""); ?>>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('proxy_verify_ssl_certificate')) == 1){ echo "checked"; } ?> name="proxy_verify_ssl_certificate" class="hidden-checkbox" id="proxy_verify_ssl_certificate_checkbox" type="checkbox" onclick="update_proxy()">  <label for="proxy_verify_ssl_certificate_checkbox"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['VerifySSLCertificate']); ?></label></td>
                            </tr>
                            <tr id="proxy_host_row" <?php echo (get_setting('proxy_web_requests') != 1 ? " style='display: none;'" : ""); ?>>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['ProxyHostname']); ?>:</td>
                              <td><input name="proxy_host" id="proxy_host" type="text" size="20px" value="<?php echo $escaper->escapeHtml(get_setting("proxy_host")); ?>" /></td>
                            </tr>
                            <tr id="proxy_port_row" <?php echo (get_setting('proxy_web_requests') != 1 ? " style='display: none;'" : ""); ?>>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['ProxyPort']); ?>:</td>
                              <td><input name="proxy_port" id="proxy_port" type="number" min="0" size="20px" value="<?php echo $escaper->escapeHtml(get_setting("proxy_port")); ?>" /></td>
                            </tr>
                            <tr id="proxy_authenticated_row" <?php echo (get_setting('proxy_web_requests') != 1 ? " style='display: none;'" : ""); ?>>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('proxy_authenticated')) == 1){ echo "checked"; } ?> name="proxy_authenticated" class="hidden-checkbox" id="proxy_authenticated_checkbox" type="checkbox" onclick="update_proxy()" >  <label for="proxy_authenticated_checkbox"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['AuthenticatedProxy']); ?></label></td>
                            </tr>
                            <tr id="proxy_user_row" <?php echo (get_setting('proxy_web_requests') != 1 || get_setting('proxy_authenticated') != 1 ? " style='display: none;'" : ""); ?>>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['ProxyUsername']); ?>:</td>
                              <td><input name="proxy_user" id="proxy_user" type="text" size="20px" value="<?php echo $escaper->escapeHtml(get_setting("proxy_user")); ?>" /></td>
                            </tr>
                            <tr id="proxy_pass_row" <?php echo (get_setting('proxy_web_requests') != 1 || get_setting('proxy_authenticated') != 1 ? " style='display: none;'" : ""); ?>>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['ProxyPassword']); ?>:</td>
                              <td><input name="proxy_pass" id="proxy_pass" type="password" size="20px" value="XXXXXXXXXX" /></td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                    </tbody>
                    </table>
                    <br />
                    <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_security_settings" />
                </form>
              </div>

              <!-- Debug Setting Tab -->
              <div id="debug" style="display: none;" class="settings_tab">
                <form name="debug_settings" method="post" action="">
                    <table border="1" width="600" cellpadding="10px">
                    <tbody>
                    <tr>
                      <td>
                        <table border="0" width="100%">
                          <tbody>
                            <tr>
                              <td colspan="2"><u><strong><?php echo $escaper->escapeHtml($lang['Debugging']); ?></strong></u></td>
                            </tr>
                            <tr>
                              <td colspan="2"><input <?php if($escaper->escapeHtml(get_setting('debug_logging')) == 1){ echo "checked"; } ?> name="debug_logging" class="hidden-checkbox" size="2" value="90" id="debug_logging" type="checkbox">  <label for="debug_logging"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['EnableDebugLogging']); ?></label></td>
                            </tr>
                            <tr>
                              <td width="300px"><?php echo $escaper->escapeHtml($lang['DebugLogFile']); ?>:</td>
                              <td><input name="debug_log_file" id="debug_log_file" type="text" size="20px" value="<?php echo $escaper->escapeHtml(get_setting("debug_log_file")); ?>" /></td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                    </tbody>
                    </table>
                    <br />
                    <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_debug_settings" />
                </form>
              </div>

              <!-- Extras Setting Tab -->
              <!--<div id="extras" style="display: none;">
                Extra Text
              </div>-->

          </div>
        </div>
      </div>
    </div>
    <script>
        (function($) {

        var tabs =  $(".tabs li a");
  
        tabs.click(function() {
                var content = this.hash.replace('/','');
                tabs.removeClass("active");
                $(this).addClass("active");
                $("#content").find('.settings_tab').hide();
                $(content).fadeIn(200);
        });

        })(jQuery);

        function update_proxy()
        {
          var proxy_web_requests_checkbox = document.getElementById("proxy_web_requests_checkbox");
          var proxy_verify_ssl_certificate_checkbox = document.getElementById("proxy_verify_ssl_certificate_checkbox");
          var proxy_verify_ssl_certificate_row = document.getElementById("proxy_verify_ssl_certificate_row");
          var proxy_authenticated_row = document.getElementById("proxy_authenticated_row");
          var proxy_authenticated_checkbox = document.getElementById("proxy_authenticated_checkbox");
          var proxy_host_row = document.getElementById("proxy_host_row");
          var proxy_port_row = document.getElementById("proxy_port_row");
          var proxy_user_row = document.getElementById("proxy_user_row");
          var proxy_pass_row = document.getElementById("proxy_pass_row");

          if (proxy_web_requests_checkbox.checked == true)
          {
            proxy_verify_ssl_certificate_row.style.display = "";
            proxy_host_row.style.display = "";
            proxy_port_row.style.display = "";
            proxy_authenticated_row.style.display = "";

            if (proxy_authenticated_checkbox.checked == true)
            {
              proxy_user_row.style.display = "";
              proxy_pass_row.style.display = "";
            }
            else
            {
              proxy_user_row.style.display = "none";
              proxy_pass_row.style.display = "none";
            }
          }
          else
          {
            proxy_verify_ssl_certificate_row.style.display = "none";
            proxy_host_row.style.display = "none";
            proxy_port_row.style.display = "none";
            proxy_authenticated_row.style.display = "none";
            proxy_user_row.style.display = "none";
            proxy_pass_row.style.display = "none";
          }
        }
    </script>
  </body>
</html>
