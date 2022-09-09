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

    checkUploadedFileSizeErrors();

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/import-export')))
    {
        // Include the Import-Export Extra
        require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate']))
        {
            // Enable the Import Export Extra
            enable_import_export_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate']))
        {
            // Disable the Import Export Extra
            disable_import_export_extra();
        }

        // If the user selected to import a CSV
        if (isset($_POST['import_csv']))
        {
            // Import the CSV file
            // $display = import_csv($_FILES['file']);
        }

        // If the user selected to do a combined export
        if (isset($_POST['combined_export']))
        {
            // Export the XLSX file
            export_xls("combined");
        }

        // If the user selected to do a combined export
        if (isset($_POST['risks_export']))
        {
            // Export the XLSX file
            export_xls("risks");
        }

        // If the user selected to do a combined export
        if (isset($_POST['mitigations_export']))
        {
            // Export the XLSX file
            export_xls("mitigations");
        }

        // If the user selected to do a combined export
        if (isset($_POST['reviews_export']))
        {
            // Export the XLSX file
            export_xls("reviews");
        }

        // If the user selected to do a combined export
        if (isset($_POST['assessments_export']))
        {
            // Export the XLSX file
            export_xls("assessments");
        }

        // If the user selected to do an asset export
        if (isset($_POST['assets_export']))
        {
            // Export the XLSX file
            export_xls("assets");
        }

        // If the user selected to do an asset group export
        if (isset($_POST['asset_groups_export']))
        {
            // Export the XLSX file
            export_xls("asset_groups");
        }

        // If the user selected to do a control export
        if (isset($_POST['controls_export']))
        {
            // Export the XLSX file
            export_xls("controls");
        }

        // If the user selected to do a user export
        if (isset($_POST['users_export']))
        {
            // Export the XLSX file
            export_xls("users");
        }

        // If the user selected to do a template groups export
        if (isset($_POST['template_groups_export']))
        {
            // Export the XLSX file
            export_xls("template_groups");
        }

        // If the user selected to do a control tests export
        if (isset($_POST['control_tests_export']))
        {
            // Export the XLSX file
            export_xls("control_tests");
        }
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    function display($display = "")
    {
        global $lang;
        global $escaper;

        // If the extra directory exists
        if (is_dir(realpath(__DIR__ . '/../extras/import-export')))
        {
            // But the extra is not activated
            if (!import_export_extra())
            {
                // If the extra is not restricted based on the install type
                if (!restricted_extra("importexport"))
                {
                    echo "<div class=\"hero-unit\">\n";
                    echo "<h4>" . $escaper->escapeHtml($lang['ImportExportExtra']) . "</h4>\n";
                    echo "<form id='activate_extra' name=\"activate\" method=\"post\" action=\"\">\n";
                    echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" /><br />";
                    echo "</form>\n";
                    echo "</div>\n";
                }
                // The extra is restricted
                else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            }
            // Once it has been activated
            else
            {
                // Include the Import-Export Extra
                require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

                display_import_export();

                display_import_export_selector();
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
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

	display_bootstrap_javascript();
?>
    <script src="../js/sorttable.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>
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
    <link rel="stylesheet" href="../css/settings_tabs.css?<?php echo current_version("app"); ?>">
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>
  </head>

  <body>

<?php
    display_license_check();
    view_top_menu("Configure");
?>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_configure_menu("ImportExport"); ?>
            </div>
            <div class="span9">
                <div class="row-fluid">
                    <div class="span12" id="import_export_wrapper">
                        <?php display(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="lang_SelectMappingToRemove" value="<?php echo $escaper->escapeHtml($lang["SelectMappingToRemove"]); ?>">
    <script type="">
        function blockWithInfoMessage(message) {
            toastr.options = {
                "timeOut": "0",
                "extendedTimeOut": "0",
            }

            $('#import_export_wrapper').block({
                message: "<?php echo $escaper->escapeHtml($lang['Processing']); ?>",
                css: { border: '1px solid black' }
            });
            setTimeout(function(){ toastr.info(message); }, 1);
        }
        $(document).ready(function(){
            $("#delete_mapping").click(function(e){
                e.preventDefault();
                var mapping_id = $("#import_export_mappings").val();
                
                if(!mapping_id){
                    alert($("#lang_SelectMappingToRemove").val());
                    return;
                }
                $.ajax({
                    method: "POST",
                    url: BASE_URL + "/api/management/impportexport/deleteMapping",
                    data: {id: mapping_id},
                    success: function(data){
                        document.location.reload();
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    }
                });
                
            });
            $('#import').submit(function(event) {
                if ($("#import input[type='file']").length && <?php echo $escaper->escapeHtml(get_setting('max_upload_size')); ?> <= $("#import input[type='file']")[0].files[0].size) {
                    toastr.error("<?php echo $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                    event.preventDefault();
                }
            });
            $("form[name='scf_mappings_install']").submit(function(evt) {
                blockWithInfoMessage("<?php echo $escaper->escapeHtml($lang['ActivatingSCFMappingMessage']); ?>");
                return true;
            });
            $("form[name='scf_mappings_uninstall']").submit(function(evt) {
                blockWithInfoMessage("<?php echo $escaper->escapeHtml($lang['DeactivatingSCFMappingMessage']); ?>");
                return true;
            });
        });

        <?php prevent_form_double_submit_script(['activate_extra', 'deactivate_extra']); ?>
    </script>
<?php
    // Get any alert messages
    get_alert();
?>
  </body>

</html>
