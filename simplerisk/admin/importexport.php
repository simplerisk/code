<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));

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

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
        set_unauthenticated_redirect();
        header("Location: ../index.php");
        exit(0);

    }

    checkUploadedFileSizeErrors();

    // Check if access is authorized
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
    {
            header("Location: ../index.php");
            exit(0);
    }

    // Include the CSRF-magic library
    // Make sure it's called after the session is properly setup
    include_csrf_magic();

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
            // Export the CSV file
            export_csv("combined");
        }

        // If the user selected to do a combined export
        if (isset($_POST['risks_export']))
        {
            // Export the CSV file
            export_csv("risks");
        }

        // If the user selected to do a combined export
        if (isset($_POST['mitigations_export']))
        {
            // Export the CSV file
            export_csv("mitigations");
        }

        // If the user selected to do a combined export
        if (isset($_POST['reviews_export']))
        {
            // Export the CSV file
            export_csv("reviews");
        }

        // If the user selected to do a combined export
        if (isset($_POST['assessments_export']))
        {
            // Export the CSV file
            export_csv("assessments");
        }

        // If the user selected to do an asset export
        if (isset($_POST['assets_export']))
        {
            // Export the CSV file
            export_csv("assets");
        }

        // If the user selected to do an asset group export
        if (isset($_POST['asset_groups_export']))
        {
            // Export the CSV file
            export_csv("asset_groups");
        }

        // If the user selected to do a control export
        if (isset($_POST['controls_export']))
        {
            // Export the CSV file
            export_csv("controls");
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
                    echo "<form name=\"activate\" method=\"post\" action=\"\">\n";
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
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/sorttable.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">

    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/settings_tabs.css">
    <?php
        setup_alert_requirements("..");
    ?>     
  </head>

  <body>

<?php
    view_top_menu("Configure");
?>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_configure_menu("ImportExport"); ?>
            </div>
            <div class="span9">
                <div class="row-fluid">
                    <div class="span12">
                        <?php display(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="lang_SelectMappingToRemove" value="<?php echo $escaper->escapeHtml($lang["SelectMappingToRemove"]); ?>">
    <script type="">
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
                if ($("#import input[type='file']").length && <?php echo get_setting('max_upload_size'); ?> <= $("#import input[type='file']")[0].files[0].size) {
                    toastr.error("<?php echo $escaper->escapeHtml($lang['FileIsTooBigToUpload']) ?>");
                    event.preventDefault();
                }
            });
        });

        <?php prevent_form_double_submit_script(); ?>
    </script>
<?php
    // Get any alert messages
    get_alert();
?>
  </body>

</html>
