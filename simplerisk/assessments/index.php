<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/assessments.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_assessments" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// Check if we should add a pending risk
if (isset($_POST['add']))
{
    // Push the pending risk to a real risk
    push_pending_risk();
}

// Check if we should delete a pending risk
if (isset($_POST['delete']))
{
    // Get the risk id to delete
    $pending_risk_id = (int)$_POST['pending_risk_id'];

    // Delete the pending risk
    delete_pending_risk($pending_risk_id);

    // Set the alert message
    set_alert(true, "good", "The pending risk was deleted successfully.");
}

// If an assessment was posted
if (isset($_POST['action']) && $_POST['action'] == "submit")
{
  // Process the assessment
  process_assessment();
}

// If an assessment was sent
if (isset($_POST['send_assessment']))
{
  // If the assessments extra is enabled
  if (assessments_extra())
  {
    // Include the assessments extra
    require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    // Process the sent assessment
    process_sent_assessment();
  }
}

// If an action was sent
if (isset($_GET['action']))
{
  // If the action is create
  if ($_GET['action'] == "create")
  {
    // Use the Create Assessments menu
    $menu = "CreateAssessment";
  }
  // If the action is edit
  else if ($_GET['action'] == "edit")
  {
    // Use the Edit Assessments menu
    $menu = "EditAssessment";
  }
  // If the action is view
  else if ($_GET['action'] == "view")
  {
    // Use the Self Assessments menu
    $menu = "SelfAssessments";
  }
  // If the action is send
  else if ($_GET['action'] == "send")
  {
    // Use the Send Assessments menu
    $menu = "SendAssessment";
  }
}
// Otherwise
else
{
  // Use the Self Assessments menu
  $menu = "SelfAssessments";
}

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

        display_bootstrap_javascript();
?>
    <script src="../js/pages/assessment.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/common.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/cve_lookup.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.datetimepicker.full.min.js?<?php echo current_version("app"); ?>"></script>
    
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <meta name="google" content="notranslate">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/jquery-ui.min.css?<?php echo current_version("app"); ?>">
    
    <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/settings_tabs.css?<?php echo current_version("app"); ?>">
  
    <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
    <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
    <link rel="stylesheet" href="../css/jquery.datetimepicker.min.css?<?php echo current_version("app"); ?>">
  
  <?php
      setup_favicon("..");
      setup_alert_requirements("..");
  ?>  
</head>

<body>
  <?php
      view_top_menu("Assessments");

      // Get any alert messages
      get_alert();
  ?>
  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_assessments_menu($menu); ?>
      </div>
      <div class="span9">

        <?php
        // If the action was create
        if ((isset($_GET['action']) && $_GET['action'] == "create") || (isset($_POST['action']) && $_POST['action'] == "create"))
        {
          // If the assessments extra is enabled
          if (assessments_extra())
          {
            // Include the assessments extra
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Display the create assessments
            display_create_assessments();
          }
        }
        // If the action was edit
        else if ((isset($_GET['action']) && $_GET['action'] == "edit") || (isset($_POST['action']) && $_POST['action'] == "edit"))
        {
          // If the assessments extra is enabled
          if (assessments_extra())
          {
            // Include the assessments extra
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));
            
            // Display the edit assessments
            echo "<div id=\"edit-assessment-container\">";
            display_edit_assessments();
            echo "</div>";
          }
        }
        // If the action was view
        else if ((isset($_GET['action']) && $_GET['action'] == "view") || (isset($_POST['action']) && $_POST['action'] == "view"))
        {
          // Display the assessment questions
          display_view_assessment_questions();
        }
        // If the action was send
        else if ((isset($_GET['action']) && $_GET['action'] == "send") || (isset($_POST['action']) && $_POST['action'] == "send"))
        {
          // If the assessments extra is enabled
          if (assessments_extra())
          {
            // Include the assessments extra
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Display the send assessment options
            display_send_assessment_options();
          }
        }
        else
        {
          // Display the self assessments
          display_self_assessments();
        }
        ?>
      </div>
    </div>
  </div>
  <script>
      (function($) {

          var tabs =  $(".tabs li a");

          tabs.click(function() {
              var content = this.hash.replace('/','');
              tabs.removeClass("active");
              $(this).addClass("active");
              $("#content").find('.settings_tab').hide();
              $(content).fadeIn(200);
          });
      })(jQuery);
  </script>
  <?php display_set_default_date_format_script(); ?>
</body>

</html>
