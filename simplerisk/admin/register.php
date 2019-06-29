<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));
	require_once(realpath(__DIR__ . '/../includes/alerts.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

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
        set_unauthenticated_redirect();
        header("Location: ../index.php");
        exit(0);
    }

    // Check if access is authorized
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
    {
        header("Location: ../index.php");
        exit(0);
    }
    
    
    if(isset($_POST['submit_mysqlpath'])){
        update_setting('mysqldump_path', $_POST['mysqldump_path']);
        set_alert(true, "good", $lang['MysqldumpPathWasSavedSuccessfully']);
    }

	// If the user wants to disable the registration notice
	if (isset($_POST['disable_registration_notice']))
	{
		// Add a setting to disable the registration notice
		add_setting("disable_registration_notice", "true");

		// Set the registration notice to false
		$registration_notice = false;
	}
	// Otherwise
	else
	{
		// If SimpleRisk is already registered
		if (get_setting('registration_registered') == 1)
        {
            // Set the registration notice to false
            $registration_notice = false;
        }
		// If the registration notice has been disabled
		else if (get_setting("disable_registration_notice") == "true")
		{
			// Set the registration notice to false
			$registration_notice = false;
		}
		// Otherwise the registration notice is true
		else $registration_notice = true;
	}

	// If SimpleRisk is not registered
	if (get_setting('registration_registered') == 0)
	{
		// Set registered to false
		$registered = false;

		// If the user has sent registration information
		if (isset($_POST['register']))
		{
			// Get the posted values
			$name = (isset($_POST['name']) ? $_POST['name'] : "");
			$fname = (isset($_POST['fname']) ? $_POST['fname'] : "");
			$lname = (isset($_POST['lname']) ? $_POST['lname'] : "");
			$company = $_POST['company'];
			$title = $_POST['title'];
			$phone = $_POST['phone'];
			$email = $_POST['email'];

			// Add the registration
			$result = add_registration($name, $company, $title, $phone, $email, $fname, $lname);

			// If the registration failed
			if ($result == 0)
			{
				// Display an alert
				set_alert(true, "bad", "There was a problem registering your SimpleRisk instance.");
			}
			else
			{
				// Display an alert
				set_alert(true, "good", "SimpleRisk instance registered successfully.");

				// Set registered to true
				$registered = true;
			}
		}
	}
	// SimpleRisk is registered
	else
	{
		// Set registered to true
		$registered = true;

		// If the user has updated their registration information
		if (isset($_POST['register']))
		{
			// Get the posted values
			$name = (isset($_POST['name']) ? $_POST['name'] : "");
			$fname = (isset($_POST['fname']) ? $_POST['fname'] : "");
			$lname = (isset($_POST['lname']) ? $_POST['lname'] : "");
			$company = $_POST['company'];
			$title = $_POST['title'];
			$phone = $_POST['phone'];
			$email = $_POST['email'];

			// Update the registration
			$result = update_registration($name, $company, $title, $phone, $email, $fname, $lname);

			// If the registration failed
			if ($result == 0)
			{
				// Display an alert
				set_alert(true, "bad", "There was a problem updating your SimpleRisk instance.");
			}
			else
			{
				// Display an alert
				set_alert(true, "good", "SimpleRisk instance updated successfully.");
			}
		}
		// Otherwise get the registration values from the database
		else
		{
			$name = get_setting("registration_name");
			$fname = get_setting("registration_fname");
			$lname = get_setting("registration_lname");
			$company = get_setting("registration_company");
			$title = get_setting("registration_title");
			$phone = get_setting("registration_phone");
			$email = get_setting("registration_email");
		}

		// If the user wants to install the Upgrade Extra
		if (isset($_POST['get_upgrade_extra']))
		{
            // Download the extra
            $result = download_extra("upgrade");
		}
		// If the user wants to install the Authentication Extra
		else if (isset($_POST['get_authentication_extra']))
		{
			// Download the extra
			$result = download_extra("authentication");
		}
		// If the user wants to install the Encryption Extra
		else if (isset($_POST['get_encryption_extra']))
		{
            // Download the extra
            $result = download_extra("encryption");
		}
		// If the user wants to install the Import-Export Extra
		else if (isset($_POST['get_importexport_extra']))
		{
            // Download the extra
            $result = download_extra("import-export");
		}
		// If the user wants to install the Notification Extra
		else if (isset($_POST['get_notification_extra']))
		{
            // Download the extra
            $result = download_extra("notification");
		}
		// If the user wants to install the Separation Extra
		else if (isset($_POST['get_separation_extra']))
		{
            // Download the extra
            $result = download_extra("separation");
		}
		else if (isset($_POST['get_governance_extra']))
		{
			// Download the extra
			$result = download_extra("governance");
		}
        // If the user wants to install the Risk Assessments Extra
        else if (isset($_POST['get_assessments_extra']))
        {
            // Download the extra
            $result = download_extra("assessments");
        }
        // If the user wants to install the API Extra
        else if (isset($_POST['get_api_extra']))
        {
            // Download the extra
            $result = download_extra("api");
        }
        // If the user wants to install the ComplianceForge Extra
        else if (isset($_POST['get_complianceforge_extra']))
        {
            // Download the extra
            $result = download_extra("complianceforge");
        }
        // If the user wants to install the ComplianceForge SCF Extra
        else if (isset($_POST['get_complianceforge_scf_extra']))
        {
            // Download the extra
            $result = download_extra("complianceforgescf");
        }
        // If the user wants to install the Customization Extra
        else if (isset($_POST['get_customization_extra']))
        {
            // Download the extra
            $result = download_extra("customization");
        }
        // If the user wants to install the Advanced Search Extra
        else if (isset($_POST['get_advanced_search_extra']))
        {
            // Download the extra
            $result = download_extra("advanced_search");
        }
	}
?>

<!doctype html>
<html>

  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/paypal.css">

    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    
    <?php
        setup_alert_requirements("..");
    ?>
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
          <?php view_configure_menu("Register"); ?>
        </div>
        <div class="span9">
            <div class="row-fluid">
                <div class="span12">
                    <div class="hero-unit">
                        <p><h4><?php echo $escaper->escapeHtml($lang['RegisterSimpleRisk']); ?></h4></p>
                        <p><?php echo $escaper->escapeHtml($lang['RegistrationText']); ?></p>
                        <?php
                            if ($registration_notice === true)
                            {
                                echo "<p><form name=\"no_message\" method=\"post\" action=\"\"><input type=\"submit\" name=\"disable_registration_notice\" value=\"" . $escaper->escapeHtml($lang['DisableRegistrationNotice']) . "\" /></form></p>\n";
                            }
                        ?>
                    </div>
                </div>
            </div>
            <div class="row-fluid">
                <div class="span12">
                    <div class="hero-unit">
                        <font size="3"><b>Instance ID:</b>&nbsp;<?php echo $escaper->escapeHtml(get_setting("instance_id")); ?></font>
                    </div>
                </div>
            </div>
          
            <?php if(!is_process("mysqldump")){ ?>
                <div class="row-fluid">
                    <div class="span12">
                        <div class="hero-unit">
                            <p></p>
                            <h4>Set Mysql Service Path</h4>
                            <form method="POST" action="">
                                <table name="mail" id="mail" border="0" width="100%">
                                    <tbody>
                                        <tr>
                                            <td width="140px">Mysqldump Path: &nbsp;</td>
                                            <td><input  name="mysqldump_path" value="<?php echo $escaper->escapeHtml(get_setting('mysqldump_path')); ?>" type="text"></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <input value="Submit" name="submit_mysqlpath" type="submit">
                            </form>
                        </div>
                    </div>
                </div>
            <?php } ?>
            
            <div class="row-fluid">
                <div class="span6">
                  <div class="hero-unit">
                    <p><h4><?php echo $escaper->escapeHtml($lang['RegistrationInformation']); ?></h4></p>
                    <form name="register" method="post" action="">
		                <?php
			                // If the instance is not registered
			                if (!$registered)
			                {
				                // Display the registration table
				                display_registration_table_edit();
			                }
			                // The instance is registered
			                else
			                {
				                // The user wants to update the registration
				                if (isset($_POST['update']))
				                {
					                // Display the editable registration table
					                display_registration_table_edit($name, $company, $title, $phone, $email, $fname, $lname);
				                }
				                else
				                {
					                // Display the registration table
					                display_registration_table($name, $company, $title, $phone, $email, $fname, $lname);
				                }
			                }
		                ?>
                    </form>
                  </div>
                </div>
                <div class="span6">
                    <div class="hero-unit">
                        <p><h4><?php echo $escaper->escapeHtml($lang['UpgradeSimpleRisk']); ?></h4></p>
                        <?php
	                        // If the instance is not registered
	                        if (!$registered)
	                        {
		                        echo "Please register in order to be able to use the easy upgrade feature.";
	                        }
	                        // The instance is registered
	                        else
	                        {
		                        display_upgrade();
	                        }
                        ?>
                    </div>
                </div>
            </div>
            <div class="row-fluid">
                <div class="span12">
                    <div class="hero-unit">
                        <?php
                            // If the instance is not registered
                            if (!$registered)
                            {
                                echo "Please register in order to be able to use the easy upgrade feature.";
                            }
                            // The instance is registered
                            else
                            {
                                display_upgrade_extras();
                            }
                        ?>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>
  </body>

</html>
