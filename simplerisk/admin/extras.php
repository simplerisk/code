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
          <?php view_configure_menu("Extras"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <h4><?php echo $escaper->escapeHtml($lang['CustomExtras']); ?></h4>
                <p><?php echo $escaper->escapeHtml($lang['CustomExtrasText']); ?></p>
                <table width="100%" class="table table-bordered table-condensed">
                <thead>
                <tr>
                  <td width="155px"><b><u>Extra Name</u></b></td>
                  <td><b><u>Description</u></b></td>
                  <td width="60px"><b><u>Enabled</u></b></td>
                </tr>
                </thead>
                <tbody>
                <tr>
                  <td width="155px"><b>Custom Authentication</b></td>
                  <td>Currently provides support for Active Directory Authentication and Duo Security multi-factor authentication, but will have other custom authentication types in the future.</td>
                  <td width="60px"><?php echo (custom_authentication_extra() ? '<a href="authentication.php">Yes</a>' : '<a href="authentication.php">No</a>'); ?></td>
                </tr>
                <tr>
                  <td width="155px"><b>Encrypted Database</b></td>
                  <td>Encryption of sensitive text fields in the database.</td>
                  <td width="60px"><?php echo (encryption_extra() ? '<a href="encryption.php">Yes</a>' : '<a href="encryption.php">No</a>'); ?></td>
                </tr>
                <tr>
                  <td width="155px"><b>Import / Export</b></td>
                  <td>Enables the import and export of CSV files containing risk information.</td>
                  <td width="60px"><?php echo (import_export_extra() ? '<a href="importexport.php">Yes</a>' : '<a href="importexport.php">No</a>'); ?></td>
                </tr>
                <tr>
                  <td width="155px"><b>Notification</b></td>
                  <td>Sends email notifications when risks are submitted, updated, mitigated, or reviewed and may be run on a schedule to notify users of risks in the Unreviewed or Past Due state.</td>
                  <td width="60px"><?php echo (notification_extra() ? '<a href="notification.php">Yes</a>' : '<a href="notification.php">No</a>'); ?></td>
                </tr>
                <tr>
                  <td width="155px"><b>Team-Based Separation</b></td>
                  <td>Restriction of risk viewing to team members the risk is categorized as.</td>
                  <td width="60px"><?php echo (team_separation_extra() ? '<a href="separation.php">Yes</a>' : '<a href="separation.php">No</a>'); ?></td>
                </tr>
                <tr>
                  <td width="155px"><b>Assessments</b></td>
                  <td>Enables ability to create custom risk assessment forms and send them to users.</td>
                  <td width="60px"><?php echo (assessments_extra() ? '<a href="assessments.php">Yes</a>' : '<a href="assessments.php">No</a>'); ?></td>
                </tr>
                <tr>
                  <td width="155px"><b>API</b></td>
                  <td>Enables an API for integration of SimpleRisk with other tools and automation tasks.</td>
                  <td width="60px"><?php echo (api_extra() ? '<a href="api.php">Yes</a>' : '<a href="api.php">No</a>'); ?></td>
                </tr>
                <tbody>
                </table>
                <p>If you are interested in adding these or other custom functionality to your SimpleRisk installation, please send an e-mail to <a href="mailto:extras@simplerisk.org?Subject=Interest%20in%20SimpleRisk%20Extras" target="_top">extras@simplerisk.org</a>.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
