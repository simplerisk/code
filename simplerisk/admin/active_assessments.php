<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

    // Check if assessment extra is enabled
    if(assessments_extra())
    {
        // Include the assessments extra
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));
    }
    else
    {
        header("Location: ../index.php");
        exit(0);
    }
    
    // If we should delete an active assessment
    if (isset($_POST['delete_active_assessments']))
    {
        // Get the selected assessments
        $tokens = $_POST['tokens'];

        // Delete the assessments
        delete_active_questionnaires($tokens);

        // Display an alert
        set_alert(true, "good", "The assessment(s) were deleted successfully.");
        
        refresh();
    }
?>

<!doctype html>
<html>

  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

	display_bootstrap_javascript();
?>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>    
  </head>

  <body>
    
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

<?php
    display_license_check();
    view_top_menu("Configure");

        // Get any alert messages
        get_alert();
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("ActiveAssessments"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <?php display_active_assessments(); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
