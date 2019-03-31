<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));

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
</head>

<body>

<?php view_top_menu("Configure"); ?>

<div class="container-fluid">
  <div class="row-fluid">
    <div class="span3">
      <?php view_configure_menu("About"); ?>
    </div>
    <div class="span9">
      <div class="row-fluid">
        <div class="span12">
          <div class="hero-unit">
            <p>The use of this software is subject to the terms of the <a href="http://mozilla.org/MPL/2.0/" target="newwindow">Mozilla Public License, v. 2.0</a>.</p>
            <p><h4>Application Version</h4>
            <ul>
              <li>The latest Application version is <?php echo $escaper->escapeHtml(latest_version("app")); ?></li>
              <li>You are running Application version <?php echo $escaper->escapeHtml(current_version("app")); ?></li>
            </ul>
            </p>
            <p><h4>Database Version</h4>
            <ul>
              <li>The latest Database version is <?php echo $escaper->escapeHtml(latest_version("db")); ?></li>
              <li>You are running Database version <?php echo $escaper->escapeHtml(current_version("db")); ?></li>
            </ul>
            </p>
            <p>You can download the most recent code <a href="https://www.simplerisk.com/download" target="newwindow">here</a>.</p>
          </div>
        </div>
      </div>
      <div class="row-fluid">
        <div class="span6">
          <div class="hero-unit">
            <p><a href="http://www.joshsokol.com" target="newwindow">Josh Sokol</a> wrote this Risk Management system after being fed up with the high-priced alternatives out there.  When your only options are spending tens of thousands of dollars or using a spreadsheet, good risk management is simply unattainable.</p>
            <p>Josh lives in Austin, TX and has four little ones starving for his time and attention.  If this tool is useful to you and you want to encourage him to keep his attention fixed on developing new features for you, perhaps consider donating via the PayPal form on the right.  It&#39;s also good karma.</p>
          </div>
        </div>
        <div class="span6">
          <div class="hero-unit">
            <!-- START PAYPAL FORM -->
            <form action="https://www.paypal.com/cgi-bin/webscr" method="post" class="payformmargin">
            <input type="hidden" name="cmd" value="_xclick">
            <input type="hidden" name="business" value="josh@simplerisk.com">
            <input type="hidden" name="item_name" value="Donation for Risk Management Software">
            <input type="hidden" name="no_note" value="1">
            <input type="hidden" name="currency_code" value="USD">

            <table cellpadding="8" cellspacing="0" border="0"><tr><td valign="top" align="center" class="payformbox">

            <table cellpadding="3" cellspacing="0" border="0"><tr><td align="left">

            Enter amount:<br>
            <input type="text" name="amount" value="50.00" class="payform"><br>

            </td><td rowspan="3">

            <img src="../images/paypal-custom.gif" alt="Payments through Paypal"><br>

            </td></tr><tr><td align="left">

            <input type="hidden" name="on0" value="Project Details">

            Payment notes:<br>
            <textarea name="os0" rows="3" cols="17" class="payform"></textarea><br>

            </td></tr><tr><td align="left">

            <input type="submit" name="PaypalPayment" value="Send Payment" class="payformbutton"><br>

            </td></tr></table>
            </td></tr></table>

            </form>
            <!-- END PAYPAL FORM -->
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>

</html>
