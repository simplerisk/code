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
        session_start('SimpleRisk');

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

        // Check if the risk level update was submitted
        if (isset($_POST['update_risk_levels']))
        {
		$veryhigh = $_POST['veryhigh'];
                $high = $_POST['high'];
                $medium = $_POST['medium'];
                $low = $_POST['low'];
                $risk_model = (int)$_POST['risk_models'];

                // Check if all values are integers
                if (is_numeric($veryhigh) && is_numeric($high) && is_numeric($medium) && is_numeric($low) && is_int($risk_model))
                {
                        // Check if low < medium < high < very high
                        if (($low < $medium) && ($medium < $high) && ($high < $veryhigh))
                        {
                                // Update the risk level
                                update_risk_levels($veryhigh, $high, $medium, $low);

				// Risk model should be between 1 and 5
				if ((1 <= $risk_model) && ($risk_model <= 5))
				{
					// Update the risk model
					update_risk_model($risk_model);

					// Display an alert
					set_alert(true, "good", "The configuration was updated successfully.");
				}
                        	// Otherwise, there was a problem
                        	else
                        	{
					// Display an alert
					set_alert(true, "bad", "The risk formula submitted was an invalid value.");
                        	}
                        }
			// Otherwise, there was a problem
			else
			{
				// Display an alert
				set_alert(true, "bad", "Your LOW risk needs to be less than your MEDIUM risk which needs to be less than your HIGH risk which needs to be less than your VERY HIGH risk.");
			}
                }
		// Otherwise, there was a problem
		else
		{
			// Display an alert
			set_alert(true, "bad", "One of the submitted risk values is not a numeric value.");
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
    <style type="text../css">.text-rotation {display: block; -webkit-transform: rotate(-90deg); -moz-transform: rotate(-90deg);}</style>

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
          <?php view_configure_menu("ConfigureRiskFormula"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <h4><?php echo $escaper->escapeHtml($lang['MyClassicRiskFormulaIs']); ?>:</h4>

                <form name="risk_levels" method="post" action="">
                <p><?php echo $escaper->escapeHtml($lang['RISK']); ?> = <?php create_dropdown("risk_models", get_setting("risk_model")) ?></p>

                <?php $risk_levels = get_risk_levels(); ?>

		<p><?php echo $escaper->escapeHtml($lang['IConsiderVeryHighRiskToBeAnythingGreaterThan']); ?>: <input type="text" name="veryhigh" size="2" value="<?php echo $escaper->escapeHtml($risk_levels[3]['value']); ?>" /></p>
                <p><?php echo $escaper->escapeHtml($lang['IConsiderHighRiskToBeLessThanAboveButGreaterThan']); ?>: <input type="text" name="high" size="2" value="<?php echo $escaper->escapeHtml($risk_levels[2]['value']); ?>" /></p>
                <p><?php echo $escaper->escapeHtml($lang['IConsiderMediumRiskToBeLessThanAboveButGreaterThan']); ?>: <input type="text" name="medium" size="2" value="<?php echo $escaper->escapeHtml($risk_levels[1]['value']); ?>" /></p>
                <p><?php echo $escaper->escapeHtml($lang['IConsiderlowRiskToBeLessThanAboveButGreaterThan']); ?>: <input type="text" name="low" size="2" value="<?php echo $escaper->escapeHtml($risk_levels[0]['value']); ?>" /></p>

                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_risk_levels" />

                </form>

                <?php create_risk_table(); ?>

                <?php echo "<p><font size=\"1\">* " . $escaper->escapeHtml($lang['AllRiskScoresAreAdjusted']) . "</font></p>"; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
