<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
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

    // If the days value is post
    if (isset($_GET['days']))
    {
        $days = (int)$_GET['days'];
    }
    // Otherwise use a week
    else $days = 7;
    
    if(isset($_POST['download_audit_log']))
    {
        if(is_admin())
        {
            // If extra is activated, download audit logs
            if (import_export_extra())
            {
                require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
            
                download_audit_logs($days);
            }else{
                set_alert(true, "bad", $escaper->escapeHtml($lang['YouCantDownloadBecauseImportExportExtraDisabled']));
                refresh();
            }
        }
        // If this is not admin user, disable download
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['AdminPermissionRequired']));
            refresh();
        }
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    function display()
    {
        global $lang;
        global $escaper;

        // If import/export extra is enabled and admin user, shows export audit log button
        if (import_export_extra() && is_admin())
        {
            // Include the Import-Export Extra
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

            display_audit_download_btn();
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
        <?php
            // Get any alert messages
            get_alert();
        ?>

      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("AuditTrail"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="well">
              <h4><?php echo $escaper->escapeHtml($lang['AuditTrail']); ?></h4>
              <div class="audit-option-container">
                  <div class="audit-select-folder">
                      <select name="days" id="days" >
                        <option value="7"<?php echo ($days == 7) ? " selected" : ""; ?>>Past Week</option>
                        <option value="30"<?php echo ($days == 30) ? " selected" : ""; ?>>Past Month</option>
                        <option value="90"<?php echo ($days == 90) ? " selected" : ""; ?>>Past Quarter</option>
                        <option value="180"<?php echo ($days == 180) ? " selected" : ""; ?>>Past 6 Months</option>
                        <option value="365"<?php echo ($days == 365) ? " selected" : ""; ?>>Past Year</option>
                        <option value="36500"<?php echo ($days == 36500) ? " selected" : ""; ?>>All Time</option>
                      </select>
                  </div>
                  <?php
                    display();
                  ?>
                  <div class="clearfix"></div>
              </div>
              <?php get_audit_trail_html(NULL, $days); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script type="">
        $(document).ready(function(){
            $("#days").change(function(){
                var days = $(this).val();
                document.location.href = BASE_URL + "/admin/audit_trail.php?days="+ days;
            })
        })
    </script>
</body>

</html>
