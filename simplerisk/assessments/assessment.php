<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/assessments.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Include the language file
require_once(language_file());
csrf_init();

// If the assessments extra is enabled
if (assessments_extra())
{
  // Include the assessments extra
  require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

  // If a key was sent
  if (isset($_GET['key']))
  {
    // If the key is valid
    if (is_valid_assessment_key($_GET['key']))
    {
      // If an assessment was posted
      if (isset($_POST['action']) && $_POST['action'] == "submit")
      {
        // Process the assessment but do not redirect
        process_assessment(false);

        // Delete the used assessment key
        delete_assessment_key($_GET['key']);

        // Do not display the assessment questionnaire
        $display = false;
      }
      else
      {
        // Get the assessment information
        $assessment = get_assessment_by_key($_GET['key']);
        $assessment_id = $assessment['assessment_id'];

        // Display the assessment questionnaire
        $display = true;
      }
    }
    else
    {
      // Do not display the assessment questionnaire
      $display = false;

      // Set the alert message
      set_alert(true, "bad", "You need a valid key in order to display an assessment.");
    }
  }
  else
  {
    // Do not display the assessment questionnaire
    $display = false;

    // Set the alert message
    set_alert(true, "bad", "You need to send a key in order to display an assessment.");
  }
}
else
{
  // Do not display the assessment questionnaire
  $display = false;

  // Set the alert message
  set_alert(true, "bad", "You need to purchase the Risk Assessment Extra in order to use this functionality.");
}
?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
  <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
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
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/jquery-ui.min.css?<?php echo current_version("app"); ?>">


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
  <?php
  echo "<div class=\"navbar\">\n";
  echo "<div class=\"navbar-inner\">\n";
  echo "<div class=\"container\">\n";
  echo "<a class=\"brand\" href=\"http://www.simplerisk.com/\"><img src='../images/logo@2x.png' alt='SimpleRisk' /></a>\n";
  echo "</div>\n";
  echo "</div>\n";
  echo "</div>\n";

  // Get any alert messages
  get_alert();
  ?>
  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span12">
        <?php if ($display) display_view_assessment_questions($assessment_id); ?>
      </div>
    </div>
  </div>
    <?php display_set_default_date_format_script(); ?>
</body>

</html>
