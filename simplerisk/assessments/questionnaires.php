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

// Process actions on questionnaire pages
if($result = process_assessment_questionnaires()){
    // If want to refresh current page
    if($result === true){
        refresh();
    }
    // If wnat to go to returned url.
    else{
        refresh($result);
    }
}
?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>
    <script src="../js/jquery.blockUI.min.js"></script>
    <script src="../js/jquery.dataTables.js"></script>
    <script src="../js/common.js"></script>
    <script src="../js/pages/assessment.js"></script>
    <script src="../js/selectize.min.js"></script>
    
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.css">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">

    <link rel="stylesheet" href="../vendor/fortawesome/font-awesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/side-navigation.css">

    <link rel="stylesheet" href="../css/selectize.bootstrap3.css">

    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>
    <style>
        .btn[disabled] {
            background-color: #3a3a3a !important;
        }
    </style>
</head>

<body>

    <?php
        view_top_menu("Assessments");

        // Get any alerts
        get_alert();
    ?>
    <div id="load" style="display:none;"><?php echo $escaper->escapeHtml($lang['SendingPleaseWait']); ?></div>
    
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_assessments_menu("Questionnaires"); ?>
            </div>
            <div class="span9">
                <?php if(isset($_GET['action']) && $_GET['action']=="list"){ ?>
                    <div class="row-fluid text-right">
                        <a class="btn" href="questionnaires.php?action=add"><?php echo $escaper->escapeHtml($lang['Add']); ?></a>
                    </div>
                    <div class="row-fluid">
                        <?php display_questionnaires(); ?>
                    </div>
                <?php }elseif(isset($_GET['action']) && $_GET['action']=="add"){ ?>
                    <?php display_questionnaire_add(); ?>
                <?php }elseif(isset($_GET['action']) && $_GET['action']=="edit"){ ?>
                        <?php display_questionnaire_edit($_GET['id']); ?>
                <?php } ?>
            </div>
        </div>
    </div>
    <input type="hidden" id="_lang_SimpleriskUsers" value="<?php echo $escaper->escapeHtml($lang['SimpleriskUsers']) ?>">
    <input type="hidden" id="_lang_AssessmentContacts" value="<?php echo $escaper->escapeHtml($lang['AssessmentContacts']) ?>">
    <?php display_set_default_date_format_script(); ?>
</body>

</html>
