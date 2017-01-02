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

        // Check if a new file type was submitted
        if (isset($_POST['add_file_type']))
        {
                $name = $_POST['new_file_type'];

                // Insert a new file type up to 100 chars
                add_name("file_types", $name, 100);

		// Display an alert
		set_alert(true, "good", "A new upload file type was added successfully.");
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

	// Check if the maximum file upload size was updated
	if (isset($_POST['update_max_upload_size']))
	{
		// Verify value is a numeric value
		if (is_numeric($_POST['size']))
		{
			update_setting('max_upload_size', $_POST['size']);

			// Display an alert
			set_alert(true, "good", "The maximum upload file size was updated successfully.");
		}
		else
		{
			// Display an alert
			set_alert(true, "bad", "The maximum upload file size needs to be an integer value.");
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
          <?php view_configure_menu("FileUploadSettings"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="filetypes" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['AllowedFileTypes']); ?>:</h4>
                <?php echo $escaper->escapeHtml($lang['AddNewFileTypeOf']); ?> <input name="new_file_type" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Add']); ?>" name="add_file_type" /><br />
                <?php echo $escaper->escapeHtml($lang['DeleteCurrentFileTypeOf']); ?> <?php create_dropdown("file_types"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Delete']); ?>" name="delete_file_type" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="filesize" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['MaximumUploadFileSize']); ?>:</h4>
                <input name="size" type="number" maxlength="50" size="20" value="<?php echo get_setting('max_upload_size'); ?>" />&nbsp;<?php echo $escaper->escapeHtml($lang['Bytes']); ?><br />
                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_max_upload_size" />
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
