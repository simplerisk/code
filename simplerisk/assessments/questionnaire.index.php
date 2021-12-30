<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/assessments.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

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

csrf_init();

// Check for session timeout or renegotiation
session_check();

// If the assessments extra is enabled
if (assessments_extra())
{
    // Include the assessments extra
    require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    if(isset($_GET['action']) && $_GET['action']=="get_sub_questions_by_answer")
    {
        getAssessmentQuestionnaireQuestionsByAnswer();
        exit;
    }

    // If a token was sent
    if (isset($_GET['token']))
    {
        // If the token is valid
        if (is_valid_questionnaire_token($_GET['token'], true))
        {
            // To make sure the questionnaire processing has enough time
            set_time_limit(600);
            
            // Process action
            if(process_questionnaire_index()){
                refresh();
            }
            $display = true;
        }
        else
        {
            // Do not display the assessment questionnaire
            $display = false;

            // Set the alert message
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidTokenForQuestionnaire']));
        }
    }
    else
    {
        // Do not display the assessment questionnaire
        $display = false;

        // Set the alert message
        set_alert(true, "bad", $escaper->escapeHtml($lang['RequiredTokenForQuestionnaire']));
    }
}
else
{
    // Set the alert message
    set_alert(true, "bad", "You need to purchase the Risk Assessment Extra in order to use this functionality.");

    set_unauthenticated_redirect();
    header("Location: ../index.php");
    exit(0);
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

        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
  <script src="../js/common.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/pages/assessment.js?<?php echo current_version("app"); ?>"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/jquery-ui.min.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/style.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../vendor/components/font-awesome/css/all.min.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/assessments_tabs.css?<?php echo current_version("app"); ?>">
  <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>

  <?php
      setup_favicon("..");
      setup_alert_requirements("..");
      echo "<script>\n";
      echo "var BASE_URL = '". (isset($_SESSION['base_url']) ? $escaper->escapeHtml($_SESSION['base_url']) : "") ."'; \n";
      echo "</script>\n";
  ?>  
</head>

<body>
    <div class="navbar">
        <div class="navbar-inner">
            <div class="container">
                <a class="brand" href="http://www.simplerisk.com/"><img src='../images/logo@2x.png' alt='SimpleRisk' /></a>
            </div>
        </div>
    </div>

    <?php
        // Get any alert messages
        get_alert();
    ?>
    <div class="container">
        <div class="row-fluid">
            <div class="span12 questionnaire-response questionnaire-result-container">
                <?php if ($display) display_questionnaire_index(); ?>
            </div>
        </div>
    </div>
    <script type="">
        $(document).ready(function(){
            // reset handler that clears the form
            $('form button:reset').click(function () {
                $(this).parents('form')
                    .find(':radio, :checkbox').removeAttr('checked').end()
                    .find('textarea, :text, select').val('')
                return false;
            });
        });
    </script>
    <?php display_set_default_date_format_script(); ?>
</body>
<style>
  .tabs{background-color: #fff; }
</style>
</html>
