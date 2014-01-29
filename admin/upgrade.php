<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        require_once('../includes/functions.php');
        require_once('../includes/authenticate.php');
	require_once('../includes/config.php');

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
                header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
        }

        // Database version to upgrade
        $version_to_upgrade = "20131117-001";

        // Database version upgrading to
        $version_upgrading_to = "20131231-001";

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRiskDBUpgrade');

        // Check for session timeout or renegotiation
        session_check();

        // If the user requested a logout
        if (isset($_GET['logout']) && $_GET['logout'] == "true")
        {
        	// Deny access
        	$_SESSION["access"] = "denied";

        	// Reset the session data
        	$_SESSION = array();

        	// Send a Set-Cookie to invalidate the session cookie
        	if (isset($_COOKIES["session_name90"]))
        	{
                	$params = session_get_cookie_params();
                	setcookie(session_name(), '', 1, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
        	}

        	// Destroy the session
        	session_destroy();

        	// Redirect to the upgrade login form
        	header( 'Location: upgrade.php' );
	}

	// Default is no alert
	$alert = false;

        // If the login form was posted
        if (isset($_POST['submit']))
        {
		/*** NEED TO REMOVE AFTER 20131231-001 RELEASE ***/
		$db = db_open();
		$database = DB_DATABASE;
		// Check to see if the close_risks column already exists
		$stmt = $db->prepare("SELECT null FROM INFORMATION_SCHEMA.COLUMNS WHERE `table_schema` = :database AND `table_name` = 'user' AND `column_name` = 'close_risks';");
		$stmt->bindParam(":database", $database, PDO::PARAM_STR);
                $stmt->execute();
        	$array = $stmt->fetchAll();
        	// If the column does not already exist
        	if (empty($array))
        	{
			// Add the close_risks column
			$stmt = $db->prepare("ALTER TABLE `user` ADD `close_risks` TINYINT( 1 ) NOT NULL DEFAULT '1';");
			$stmt->execute();
        	}
		db_close($db);
		/*** END REMOVE ***/

                $user = $_POST['user'];
                $pass = $_POST['pass'];

                // If the user is valid
                if (is_valid_user($user, $pass))
                {
                        // Check if the user is an admin
                        if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
                        {
                                // Grant access
                                $_SESSION["access"] = "granted";
                        }
                        // The user is not an admin
                        else
                        {
				$alert = "bad";
                                $alert_message = "You need to log in as an administrative user in order to upgrade the database.";

                                // Deny access
                                $_SESSION["access"] = "denied";
                        }
                }
                // The user was not valid
                else
                {
			// Send an alert
			$alert = "bad";

                        // Invalid username or password
                        $alert_message = "Invalid username or password.";

                        // Deny access
                        $_SESSION["access"] = "denied";
                }
        }
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
  </head>

  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li>
                <a href="upgrade.php">Database Upgrade Script</a>
              </li>
              <li>
                <a href="upgrade.php?logout=true">Logout</a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
<?php
        if ($alert == "good")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 greenalert\">" . $alert_message . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $alert_message . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span12">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
<?php
	// If access was not granted display the login form
	if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
	{
      		echo "<p><label><u>Log In Here</u></label></p>\n";
      		echo "<form name=\"authenticate\" method=\"post\" action=\"\">\n";
      		echo "Username: <input class=\"input-medium\" name=\"user\" id=\"user\" type=\"text\" /><br />\n";
      		echo "Password: <input class=\"input-medium\" name=\"pass\" id=\"pass\" type=\"password\" />\n";
		echo "<br />\n";
      		echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">Login</button>\n";
      		echo "</form>\n";
	}
	// Otherwise access was granted so check if the user is an admin
	else if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
        {
		// If CONTINUE was not pressed
		if (!isset($_POST['upgrade_database']))
		{
			// Get the current application version
			$app_version = current_version("app");

			echo "The current application version is: " . $app_version . "<br />\n";

			// Get the current database version
			$db_version = current_version("db");

			echo "The current database version is: " . $db_version . "<br />\n";

			// If the version to upgrade is the current version
			if ($db_version == $version_to_upgrade)
			{
				echo "This script will ugprade your database from version " . $version_to_upgrade . " to the version that goes with these application files.  Click &quot;CONTINUE&quot; to proceed.<br />\n";
				echo "<br />\n";
				echo "<form name=\"upgrade_database\" method=\"post\" action=\"\">\n";
				echo "<button type=\"submit\" name=\"upgrade_database\" class=\"btn btn-primary\">CONTINUE</button>\n";
				echo "</form>\n";
			}
			// Otherwise if the db version matches the app version
			else if ($db_version == $app_version)
			{
				echo "Your database is already upgraded to the version that matches your application files.  No additional upgrade is necessary to make it work properly.<br />\n";
			}
			// Otherwise this is not the right database version to upgrade
			else
			{
				echo "This script was meant to upgrade database version " . $version_to_upgrade . " but your current database version is " . $db_version . ".  You will need to use a different database upgrade script instead.<br />\n";
			}
		}
		// Otherwise, CONTINUE was pressed
		else
		{
			// Connect to the database
			echo "Connecting to the SimpleRisk database.<br />\n";
			$db = db_open();

			echo "Beginning upgrade of SimpleRisk database.<br />\n";

			/****************************
                 	* DATABASE CHANGES GO HERE *
		 	****************************/

			// Create a new table to track control regulations
			echo "Creating a new table to track control regulations.<br />\n";
			$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `regulation` (`value` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(50) NOT NULL, PRIMARY KEY (`value`)) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;");
			$stmt->execute();

			// Populate the control regulation table
			echo "Populating the control regulation table.<br />\n";
			$stmt = $db->prepare("INSERT INTO regulation (`value`, `name`) VALUES (NULL, 'PCI DSS'), (NULL, 'Sarbanes-Oxley (SOX)'), (NULL, 'HIPAA'), (NULL, 'ISO 27001')");
			$stmt->execute();

			// Add columns to track control regulation and control number in risk table
			echo "Adding columns to track control regulation and control number in the risk table.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `risks` ADD `regulation` INT( 11 ) NULL DEFAULT NULL AFTER `reference_id`, ADD `control_number` VARCHAR( 20 ) NULL DEFAULT NULL AFTER `regulation` ;");
			$stmt->execute();

			// Add columns for DREAD, OWASP, and Custom risk rating
			echo "Adding columns for DREAD, OWASP, and Custom risk rating.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `risk_scoring`  ADD `DREAD_DamagePotential` INT NULL DEFAULT NULL,  ADD `DREAD_Reproducibility` INT NULL DEFAULT NULL,  ADD `DREAD_Exploitability` INT NULL DEFAULT NULL,  ADD `DREAD_AffectedUsers` INT NULL DEFAULT NULL,  ADD `DREAD_Discoverability` INT NULL DEFAULT NULL,  ADD `OWASP_SkillLevel` INT NULL DEFAULT NULL,  ADD `OWASP_Motive` INT NULL DEFAULT NULL,  ADD `OWASP_Opportunity` INT NULL DEFAULT NULL,  ADD `OWASP_Size` INT NULL DEFAULT NULL,  ADD `OWASP_EaseOfDiscovery` INT NULL DEFAULT NULL,  ADD `OWASP_EaseOfExploit` INT NULL DEFAULT NULL,  ADD `OWASP_Awareness` INT NULL DEFAULT NULL,  ADD `OWASP_IntrusionDetection` INT NULL DEFAULT NULL,  ADD `OWASP_LossOfConfidentiality` INT NULL DEFAULT NULL,  ADD `OWASP_LossOfIntegrity` INT NULL DEFAULT NULL,  ADD `OWASP_LossOfAvailability` INT NULL DEFAULT NULL,  ADD `OWASP_LossOfAccountability` INT NULL DEFAULT NULL,  ADD `OWASP_FinancialDamage` INT NULL DEFAULT NULL,  ADD `OWASP_ReputationDamage` INT NULL DEFAULT NULL,  ADD `OWASP_NonCompliance` INT NULL DEFAULT NULL,  ADD `OWASP_PrivacyViolation` INT NULL DEFAULT NULL,  ADD `Custom` FLOAT NULL DEFAULT NULL;");
			$stmt->execute();

			// Set default values for classic risk scoring
			echo "Setting default values for classic risk scoring.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `risk_scoring` CHANGE `CLASSIC_likelihood` `CLASSIC_likelihood` FLOAT NOT NULL DEFAULT '5', CHANGE `CLASSIC_impact` `CLASSIC_impact` FLOAT NOT NULL DEFAULT '5';");
			$stmt->execute();


                        // Set default values for cvss risk scoring
                        echo "Setting default values for cvss risk scoring.<br />\n";
                        $stmt = $db->prepare("ALTER TABLE `risk_scoring` CHANGE `CVSS_AccessVector` `CVSS_AccessVector` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'N', CHANGE `CVSS_AccessComplexity` `CVSS_AccessComplexity` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'L', CHANGE `CVSS_Authentication` `CVSS_Authentication` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'N', CHANGE `CVSS_ConfImpact` `CVSS_ConfImpact` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'C', CHANGE `CVSS_IntegImpact` `CVSS_IntegImpact` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'C', CHANGE `CVSS_AvailImpact` `CVSS_AvailImpact` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'C', CHANGE `CVSS_Exploitability` `CVSS_Exploitability` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'ND', CHANGE `CVSS_RemediationLevel` `CVSS_RemediationLevel` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'ND', CHANGE `CVSS_ReportConfidence` `CVSS_ReportConfidence` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'ND', CHANGE `CVSS_CollateralDamagePotential` `CVSS_CollateralDamagePotential` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'ND', CHANGE `CVSS_TargetDistribution` `CVSS_TargetDistribution` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'ND', CHANGE `CVSS_ConfidentialityRequirement` `CVSS_ConfidentialityRequirement` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'ND', CHANGE `CVSS_IntegrityRequirement` `CVSS_IntegrityRequirement` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'ND', CHANGE `CVSS_AvailabilityRequirement` `CVSS_AvailabilityRequirement` VARCHAR(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'ND';");
                        $stmt->execute();

                        // Set default values for dread risk scoring
                        echo "Setting default values for dread risk scoring.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `risk_scoring` CHANGE `DREAD_DamagePotential` `DREAD_DamagePotential` INT(11) NULL DEFAULT '10', CHANGE `DREAD_Reproducibility` `DREAD_Reproducibility` INT(11) NULL DEFAULT '10', CHANGE `DREAD_Exploitability` `DREAD_Exploitability` INT(11) NULL DEFAULT '10', CHANGE `DREAD_AffectedUsers` `DREAD_AffectedUsers` INT(11) NULL DEFAULT '10', CHANGE `DREAD_Discoverability` `DREAD_Discoverability` INT(11) NULL DEFAULT '10';");
                        $stmt->execute();

                        // Set default values for owasp risk scoring
                        echo "Setting default values for owasp risk scoring.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `risk_scoring` CHANGE `OWASP_SkillLevel` `OWASP_SkillLevel` INT(11) NULL DEFAULT '10', CHANGE `OWASP_Motive` `OWASP_Motive` INT(11) NULL DEFAULT '10', CHANGE `OWASP_Opportunity` `OWASP_Opportunity` INT(11) NULL DEFAULT '10', CHANGE `OWASP_Size` `OWASP_Size` INT(11) NULL DEFAULT '10', CHANGE `OWASP_EaseOfDiscovery` `OWASP_EaseOfDiscovery` INT(11) NULL DEFAULT '10', CHANGE `OWASP_EaseOfExploit` `OWASP_EaseOfExploit` INT(11) NULL DEFAULT '10', CHANGE `OWASP_Awareness` `OWASP_Awareness` INT(11) NULL DEFAULT '10', CHANGE `OWASP_IntrusionDetection` `OWASP_IntrusionDetection` INT(11) NULL DEFAULT '10', CHANGE `OWASP_LossOfConfidentiality` `OWASP_LossOfConfidentiality` INT(11) NULL DEFAULT '10', CHANGE `OWASP_LossOfIntegrity` `OWASP_LossOfIntegrity` INT(11) NULL DEFAULT '10', CHANGE `OWASP_LossOfAvailability` `OWASP_LossOfAvailability` INT(11) NULL DEFAULT '10', CHANGE `OWASP_LossOfAccountability` `OWASP_LossOfAccountability` INT(11) NULL DEFAULT '10', CHANGE `OWASP_FinancialDamage` `OWASP_FinancialDamage` INT(11) NULL DEFAULT '10', CHANGE `OWASP_ReputationDamage` `OWASP_ReputationDamage` INT(11) NULL DEFAULT '10', CHANGE `OWASP_NonCompliance` `OWASP_NonCompliance` INT(11) NULL DEFAULT '10', CHANGE `OWASP_PrivacyViolation` `OWASP_PrivacyViolation` INT(11) NULL DEFAULT '10';");
                        $stmt->execute();

                        // Set default values for custom risk scoring
                        echo "Setting default values for custom risk scoring.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `risk_scoring` CHANGE `Custom` `Custom` FLOAT NULL DEFAULT '10';");
                        $stmt->execute();

			// Increase settings to allow 40 character values
			echo "Increasing setting value size.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `settings` CHANGE `value` `value` VARCHAR( 40 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL ;");
			$stmt->execute();

			// Adding new user column to track multi factor authentication
			echo "Adding multi-factor tracking to user table.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `user` ADD `multi_factor` INT NOT NULL DEFAULT '1';");
			$stmt->execute();

			/************************
		 	 * END DATABASE CHANGES *
		 	 ************************/

			// Update the database version information
			echo "Updating the database version information.<br />\n";
			$stmt = $db->prepare("UPDATE `settings` SET `value` = '" . $version_upgrading_to . "' WHERE `settings`.`name` = 'db_version' AND `settings`.`value` = '" . $version_to_upgrade . "' LIMIT 1 ;");
			$stmt->execute();

			// Disconnect from the database
			echo "Disconnecting from the SimpleRisk database.<br />\n";
        		db_close($db);

			echo "SimpleRisk database upgrade is complete.<br />\n";
		}
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
