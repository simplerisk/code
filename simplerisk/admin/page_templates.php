<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Include required functions file
	require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));
	require_once(realpath(__DIR__ . '/../includes/alerts.php'));
	require_once(realpath(__DIR__ . '/../includes/fields.php'));

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

    // Check if a new review was submitted
    if (isset($_POST['create_field']))
    {
        $name = $_POST['name'];
	$type = $_POST['type'];

	// Create the new field
	if (create_field($name, $type))
	{
		// Audit log
		$risk_id = 1000;
		$message = "A custom field named \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
		write_log($risk_id, $_SESSION['uid'], $message);

        	// Display an alert
        	set_alert(true, "good", "The new custom field was created successfully.");
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
        <link rel="stylesheet" href="../css/style.css">

        <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="../css/theme.css">
	<script src="../js/pages/templates.js"></script>
    </head>

    <body>

        <?php
            view_top_menu("Configure");

            // Get any alert messages
            get_alert();
        ?>

  <div class="tabs new-tabs planning-tabs">
    <div class="container-fluid">

      <div class="row-fluid">

        <div class="span3"> </div>
        <div class="span9">

          <div class="tab-append">
            <div class="tab selected form-tab tab-show current-projects-tab" data-content="#custom-fields-tab-content"><div><span><?php echo $escaper->escapeHtml($lang['CustomFields']); ?></span></div></div>
            <div class="tab form-tab tab-show controls-tab" data-content="#controls-tab-content"><div><span><?php echo $escaper->escapehtml($lang['Controls']); ?></span></div></div>
          </div>

        </div>

      </div>

    </div>
  </div>

        <div class="container-fluid">
            <div class="row-fluid">
                <div class="span3">
                  <?php view_configure_menu("PageTemplates"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <div class="span12">
                            <!-- Custom Fields container begin -->
                            <div id="custom-fields-tab-content" class="plan-projects tab-data">
                                <div class="hero-unit">
                                    <form name="field_create_form" method="post" action="">
                                        <p>
					    <?php
					    echo "<h4>" . $escaper->escapeHtml($lang['AddACustomField']) . ":</h4>\n";
					    echo "<table>\n";
					    echo "<tr>\n";
					    echo "<td>" . $escaper->escapeHtml($lang['FieldName']) . ":&nbsp;</td>\n";
					    echo "<td><input name=\"name\" type=\"text\" maxlength=\"100\" size=\"20\" /></td>\n";
					    echo "</tr>\n";
					    echo "<tr>\n";
					    echo "<td>" . $escaper->escapeHtml($lang['FieldType']) . ":&nbsp;</td>\n";
					    echo "<td>\n";
					    display_field_type_dropdown();
					    echo "</td>\n";
					    echo "</tr>\n";
					    echo "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>\n";
					    echo "<tr>\n";
					    echo "<td><input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Add']) . "\" name=\"create_field\" /></td>\n";
					    echo "<td>&nbsp;</td>\n";
					    echo "</tr>\n";
                                            echo "</table>\n";
					    ?>
                                        </p>
                                    </form>
                                </div>
                                <div class="hero-unit">
                                    <form name="field_delete_form" method="post" action="">
                                        <p>
                                            <?php
                                            echo "<h4>" . $escaper->escapeHtml($lang['CustomFields']) . ":</h4>\n";
                                            echo "<table>\n";
                                            echo "<tr>\n";
                                            echo "<td>" . $escaper->escapeHtml($lang['FieldName']) . ":&nbsp;</td>\n";
                                            echo "<td>\n";
                                            display_field_name_dropdown();
                                            echo "</td>\n";
                                            echo "<td>&nbsp;&nbsp;<a href=\"javascript:delete_field();\" data-id=\"2\"><i class=\"fa fa-trash\"></i></a></td>\n";
                                            //echo "<td>&nbsp;&nbsp;<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Delete']) . "\" name=\"delete_field\" /></td>\n";
                                            echo "</tr>\n";
                                            echo "</table>\n";
                                            ?>
                                        </p>
                                    </form>
                                </div>
                            </div>
                            <!-- Custom Fields container ends -->
                            <!-- Next container begin -->
                            <div id="control-tab-content" class="plan-projects tab-data">
                            </div>
                            <!-- Next container ends -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>

</html>
