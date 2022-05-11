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

// Process actions on questionnaire template pages
if(process_assessment_questionnaire_templates()){
    refresh();
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
?>
    <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>
    <?php display_bootstrap_javascript(); ?>
    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/pages/assessment.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/dataTables.rowReorder.min.js?<?php echo current_version("app"); ?>"></script>

    
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <meta name="google" content="notranslate" />
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/rowReorder.dataTables.min.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/prioritize.css?<?php echo current_version("app"); ?>">

    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>
</head>

<body>

    <?php
        view_top_menu("Assessments");

        // Get any alerts
        get_alert();
    ?>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_assessments_menu("QuestionnaireTemplates"); ?>
            </div>
            <div class="span9">
                <?php if(isset($_GET['action']) && $_GET['action']=="template_list"){ ?>
                    <div class="row-fluid text-right">
                        <a class="btn" href="#aseessment-questionnaire-template--add" role="button" data-toggle="modal"><?php echo $escaper->escapeHtml($lang['Add']); ?></a>
                    </div>
                    <div class="row-fluid">
                        <?php display_questionnaire_templates(); ?>
                    </div>
                <?php }elseif(isset($_GET['action']) && $_GET['action']=="add_template"){ ?>
                    <div class="hero-unit">
                        <?php display_questionnaire_template_add(); ?>
                    </div>
                <?php }elseif(isset($_GET['action']) && $_GET['action']=="add_template_from_scf"){ ?>
                    <div class="hero-unit">
                        <?php display_questionnaire_template_add_from_scf(); ?>
                    </div>
                <?php }elseif(isset($_GET['action']) && $_GET['action']=="edit_template"){ ?>
                    <div class="hero-unit">
                        <?php display_questionnaire_template_edit($_GET['id']); ?>
                    </div>
                <?php } ?>


            </div>
        </div>
    </div>
    <div id="aseessment-questionnaire-template--add" class="modal hide fade" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-body">

        <form class="" action="" method="post">
          <div class="form-group text-center">
            <label for=""><?php echo $escaper->escapeHtml($lang['WouldYouLikeSimpleRiskToAutomaticallyGenerate']);?></label>
          </div>
          <input type="hidden" name="template_id" value="" />
          <div class="form-group text-center ">
            <a class="delete_control btn btn-danger" href="questionnaire_templates.php?action=add_template_from_scf"><?php echo $escaper->escapeHtml($lang['Yes']);?></a>
            <a class="btn btn-default" href="questionnaire_templates.php?action=add_template"><?php echo $escaper->escapeHtml($lang['No']);?></a>
          </div>
        </form>
      </div>
    </div>

</body>

</html>

