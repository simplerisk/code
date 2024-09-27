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

// Add various security headers

require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'tabs:logic', 'CUSTOM:pages/assessment.js'], ['check_assessments' => true], '', $active_sidebar_menu, $active_sidebar_submenu);

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

  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

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

  ?>
  <div class="row-fluid">
    <div class="span12">
      <?php if ($display) display_view_assessment_questions($assessment_id); ?>
    </div>
  </div>
</body>

</html>
