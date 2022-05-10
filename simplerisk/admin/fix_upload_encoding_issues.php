<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../includes/datefix.php'));
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

    $simplerisk_max_upload_size = get_setting('max_upload_size');

    $affected_types = [];

    if (has_files_with_encoding_issues('risk')) {
        $affected_types[] = 'risk';
    }

    if (has_files_with_encoding_issues('compliance')) {
        $affected_types[] = 'compliance';
    }

    if (table_exists('questionnaire_files') && has_files_with_encoding_issues('questionnaire')) {
        $affected_types[] = 'questionnaire';
    }

    if (empty($affected_types)) {
        refresh("index.php");
    }
?>

<!doctype html>
<html>

    <head>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

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
    <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>

    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/style.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>
    <style>
        .table-striped tbody > tr > td a {
            text-transform: none;
            text-align: left;
        }

        .table-striped tbody > tr > td a:before {
            margin-right: 5px;
            font: normal normal normal 14px/1 FontAwesome !important;
            content: "\f08e";
        }
    </style>
    <script>
        function displayFileSize(label, size) {
            if (<?php echo $escaper->escapeHtml(get_setting('max_upload_size')); ?> > size) {
                label.attr("class","success");
            } else {
                label.attr("class","danger");
            }

            var iSize = (size / 1024);
            if (iSize / 1024 > 1) {
                if (((iSize / 1024) / 1024) > 1) {
                    iSize = (Math.round(((iSize / 1024) / 1024) * 100) / 100);
                    label.html("<?php echo $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Gb");
                } else {
                    iSize = (Math.round((iSize / 1024) * 100) / 100)
                    label.html("<?php echo $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize + "Mb");
                }
            } else {
                iSize = (Math.round(iSize * 100) / 100)
                label.html("<?php echo $escaper->escapeHtml($lang['FileSize'] . ": ") ?>" + iSize  + "kb");
            }
        }

    	$(document).ready(function() { 

            var fileAPISupported = typeof $("<input type='file'>").get(0).files != "undefined";

            if (fileAPISupported) {
                $('body').on('keydown paste focus', 'input.readonly', function(e){
                    e.preventDefault();
                    e.currentTarget.blur();
                });

                $('body').on('click', '.file-uploader input.readonly', function(){
                    $(this).parent().find("input[type=file]").trigger("click");
                });

                $('body').on('change', '.file-uploader input[type=file]', function(e){

                    if (!e.target.files[0])
                        return;

                    var fileName = e.target.files[0].name;
                    var fileNameBox = $(this).parent().find("input.readonly");
                    fileNameBox.val(fileName);
                    fileNameBox.attr('title', fileName);
                    displayFileSize($(this).parent().find("span.file-size label"), e.target.files[0].size);
                });
            } else { // If File API is not supported
                $("input.readonly").remove();
                $('#file-upload').prop('required',true);
            }
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
                  <?php view_configure_menu("FixFileEncodingIssues"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <div class="span12 ">
                            <p><?php echo $escaper->escapeHtml($lang['FixFileEncodingIssuesDisclaimer']); ?></p>
                            <h4><?php echo $escaper->escapeHtml($lang['MaximumUploadFileSize'] . ': ' . $simplerisk_max_upload_size . ' ' . $lang['Bytes']); ?>.</h4>
<?php
    // If the max upload size for SimpleRisk is bigger than the PHP max upload size
    if($simplerisk_max_upload_size > php_max_allowed_values()) {
        echo "<font style=\"color: red;\">" . $escaper->escapeHtml($lang['WarnPHPUploadSize']) . '</font><br />';
    }

    // If the max upload size for SimpleRisk is bigger than the MySQL max upload size
    if ($simplerisk_max_upload_size > mysql_max_allowed_values()) {
        echo "<font style=\"color: red;\">" . $escaper->escapeHtml($lang['WarnMySQLUploadSize']) . '</font><br />';
    }
?>
                        </div>
                    </div>
<?php foreach ($affected_types as $type) { ?>
                    <div class="row-fluid">
                        <div class="span12 ">
                            <h3>
								<?php echo $escaper->escapeHtml($lang['FileEncodingFixHeader_' . $type]); ?>
                            </h3>
                        </div>
                    </div>
                    <div class="row-fluid">
                        <div class="span12 ">
                            <?php display_file_encoding_issues($type); ?>
                        </div>
                    </div>
<?php } ?>
                </div>
            </div>
        </div>
        <?php display_set_default_date_format_script(); ?>
    </body>
</html>
