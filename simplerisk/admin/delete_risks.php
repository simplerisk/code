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

// Check if risks were deleted
if (isset($_POST['delete_risks']) && isset($_POST['risks']))
{
  $risks = $_POST['risks'];

  // Delete the risks
  $success = delete_risks($risks);

  // If the risk delete was successful
  if ($success)
  {
    // Display an alert
    set_alert(true, "good", $lang['RisksDeletedSuccessfully']);
  }
  else
  {
    // Display an alert
    set_alert(true, "bad", $lang['ThereWasAProblemDeletingTheRisk']);
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
  <script type="text/javascript">
  function checkAll(bx) {
    var cbs = document.getElementsByTagName('input');
    for(var i=0; i < cbs.length; i++) {
      if (cbs[i].type == 'checkbox') {
        cbs[i].checked = bx.checked;
      }
    }
  }
  </script>

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
        <?php view_configure_menu("DeleteRisks"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <div class="span12">
            <div class="hero-unit">
              <form name="delete_risks" method="post" action="">
                <p>
                  <h4><?php echo $escaper->escapeHtml($lang['DeleteRisks']); ?></h4>
                  <?php echo $escaper->escapeHtml($lang['DeletedRisksCannotBeRecovered']); ?>
                </p>
                <p><button type="submit" name="delete_risks" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['Delete']); ?></button></p>
                <?php get_delete_risk_table(); ?>
                <p><button type="submit" name="delete_risks" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['Delete']); ?></button></p>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
