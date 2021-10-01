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

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/complianceforgescf')))
    {
        // Include the ComplianceForge Extra
        require_once(realpath(__DIR__ . '/../extras/complianceforgescf/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate']))
        {
            // Enable the ComplianceForge SCF Extra
            enable_complianceforge_scf_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate']))
        {
            // Disable the ComplianceForge SCF Extra
            disable_complianceforge_scf_extra();
        }
    }

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display()                                    
{
    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/complianceforgescf')))
    {
        // But the extra is not activated
        if (!complianceforge_scf_extra())
        {
            echo "<form name=\"activate\" method=\"post\" action=\"\">\n";
            echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" /><br />\n";
            echo "</form>\n";
        }
        // Once it has been activated
        else
        {
            // Include the Assessments Extra
            require_once(realpath(__DIR__ . '/../extras/complianceforgescf/index.php'));

            display_complianceforge_scf();
        }
    }
    // Otherwise, the Extra does not exist
    else
    {
        echo "<a href=\"https://www.simplerisk.com/extras\" target=\"_blank\">Purchase the Extra</a>\n";
    }
}

?>

<!doctype html>
<html>

  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
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
    <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>
    <link rel="stylesheet" type="text/css" href="../css/jquery-ui.min.css?<?php echo current_version("app"); ?>" />

    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

    <script type="text/javascript" src="../js/jquery.tree.min.js"></script>
    <link rel="stylesheet" type="text/css" href="../css/jquery.tree.min.css?<?php echo current_version("app"); ?>" />
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>
    <script type="text/javascript">
        function blockWithInfoMessage(message) {
            toastr.options = {
                "timeOut": "0",
                "extendedTimeOut": "0",
            }

            $('#SCF_wrapper').block({
                message: "<?php echo $escaper->escapeHtml($lang['Processing']); ?>",
                css: { border: '1px solid black' }
            });
            setTimeout(function(){ toastr.info(message); }, 1);
        }

        $(function(){
            $("#complianceforge_frameworks").multiselect({
                allSelectedText: "<?php echo $escaper->escapeHtml($lang['AllFrameworks']); ?>",
                includeSelectAllOption: true
            });

            $("form[name='activate']").submit(function(evt) {
                blockWithInfoMessage("<?php echo $escaper->escapeHtml($lang['ActivatingSCFMessage']); ?>");
                return true;
            });

            $("form[name='deactivate']").submit(function(evt) {
                blockWithInfoMessage("<?php echo $escaper->escapeHtml($lang['DeactivatingSCFMessage']); ?>");
                return true;
            });

            $("form[name='refresh_complianceforge_scf'], form[name='complianceforge_scf_frameworks']").submit(function(evt) {
                blockWithInfoMessage("<?php echo $escaper->escapeHtml($lang['UpdatingSCFMessage']); ?>");
                return true;
            });
        });
    </script>
  </head>

  <body>

<?php
    display_license_check();

    view_top_menu("Configure");

    // Get any alert messages
    get_alert();
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("Extras"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div id="SCF_wrapper" class="hero-unit">
                <h4>ComplianceForge SCF Extra</h4>
                <?php display(); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php display_set_default_date_format_script(); ?>
    <script>
        <?php prevent_form_double_submit_script(); ?>
    </script>    
  </body>

</html>
