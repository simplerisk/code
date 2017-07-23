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

    if (isset($_POST['update_settings']))
    {
        // Display an alert
        set_alert(true, "good", "The settings were updated successfully.");

        $enable_popup = (isset($_POST['enable_popup'])) ? 1 : 0;
        update_setting("enable_popup", $enable_popup);
        $default_risk_score = $_POST['default_risk_score'];
        if (!(($default_risk_score >= 0) && ($default_risk_score <= 10)))
        {
            // Set the custom value to 10
            $default_risk_score = 10;
        }
        update_setting("default_risk_score", $default_risk_score);
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
          <?php view_configure_menu("Settings"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="settings" method="post" action="">
                    <br>
                    <p><input <?php if(get_setting('enable_popup') == 1){ echo "checked"; } ?> name="enable_popup" class="hidden-checkbox" size="2" value="90" id="enable_popup" type="checkbox">  <label for="enable_popup"  >&nbsp;&nbsp; <?php echo $escaper->escapeHtml($lang['EnablePopupWindowsForTextBoxes']); ?></label></p>
                    <p><?php echo $escaper->escapeHtml($lang['DefaultRiskScore']) ?>:&nbsp;&nbsp; <input value="<?php echo (get_setting('default_risk_score') ? get_setting('default_risk_score') : 10); ?>" name="default_risk_score" id="default_risk_score" type="number" min="0" max="10">  </p>
                    <br>
                    <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_settings" />

                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
