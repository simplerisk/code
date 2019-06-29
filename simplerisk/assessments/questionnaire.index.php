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
        if (is_valid_questionnaire_token($_GET['token']))
        {
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
  <script src="../js/jquery.min.js"></script>
  <script src="../js/jquery-ui.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/common.js"></script>
  <script src="../js/pages/assessment.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  <link rel="stylesheet" href="../css/jquery-ui.min.css">


  <link rel="stylesheet" href="../css/divshot-util.css">
  <link rel="stylesheet" href="../css/divshot-canvas.css">
  <link rel="stylesheet" href="../css/display.css">
  <link rel="stylesheet" href="../css/style.css">

  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/selectize.bootstrap3.css">
  <script src="../js/selectize.min.js"></script>
  <?php
      setup_alert_requirements("..");
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

</html>
