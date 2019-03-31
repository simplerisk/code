<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/assets.php'));
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

// Check if the user has access to manage assets
if (!isset($_SESSION["asset"]) || $_SESSION["asset"] != 1)
{
  header("Location: ../index.php");
  exit(0);
}
else $manage_assets = true;

// Check if an asset search was submitted
if ((isset($_POST['search'])) && $manage_assets)
{
  $range = $_POST['range'];
  $AvailableIPs = discover_assets($range);

  // If the IP was not in a recognizable format
  if ($AvailableIPs === false)
  {
    // Display an alert
    set_alert(true, "bad", $escaper->escapeHtml($lang['IPFormatNotRecognized']));
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
  <?php
    setup_alert_requirements("..");
  ?>

  <script type="text/javascript">
  var loading={
      ajax:function(st)
      {
        this.show('load');
      },
      show:function(el)
      {
        this.getID(el).style.display='';
      },
      getID:function(el)
      {
        return document.getElementById(el);
      }
    }
  </script>
</head>

<body>


  <?php
  view_top_menu("AssetManagement");

  // Get any alert messages
  get_alert();
  ?>
  <div id="load" style="display:none;">Scanning IPs... Please wait.</div>
  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_asset_management_menu("AutomatedDiscovery"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <div class="span12">
            <div class="hero-unit">
              <h4><?php echo $escaper->escapeHtml($lang['AutomatedDiscovery']); ?></h4>
              <p><?php echo $escaper->escapeHtml($lang['AutomatedDiscoveryHelp']); ?></p>
              <ul>
                <li>192.168.0.1</li>
                <!-- 192.168.0.0/24<br />-->
                <li>192.168.0.1-192.168.0.255</li>
              </ul>
            </p>
            <form name="discover_assets" method="post" action="" enctype="multipart/form-data" onsubmit="return loading.ajax()">
              <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="100px"><?php echo $escaper->escapeHtml($lang['IPRange']); ?>:</td>
                  <td><input maxlength="100" name="range" id="range" class="input-medium" type="text"></td>
                </tr>
              </table>

              <div class="form-actions">
                <button type="submit" name="search" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['Search']); ?></button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>

</html>
