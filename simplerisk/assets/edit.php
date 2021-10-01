<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/assets.php'));
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
        "check_assets" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

?>
<!doctype html>
<html>

<head>
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
  <script src="../js/pages/asset.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>

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
  <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
  <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
  <?php
      setup_favicon("..");
      setup_alert_requirements("..");
  ?>
  <style>
    .tag-max-length-warning {
        margin-top: 0px;
    }
  </style>

</head>

<body>

  <?php
  view_top_menu("AssetManagement");

  // Get any alert messages
  get_alert();
  ?>
  <script>
    $(document).ready(function() {
        $('#edit-assets-table select:not(.multiselect):not(.selectize-marker)').change(updateAsset);

        var oldValue = "";
        $('#edit-assets-table textarea, #edit-assets-table input').bind('focusin', function(){
            oldValue = $(this).val();
        });
        $('#edit-assets-table textarea, #edit-assets-table input').bind('focusout', function(){
            var newValue = $(this).val();
            if(oldValue !== newValue){
                updateAsset(null, $(this));
            }
        });
    });
  </script>
  <div id="load" style="display:none;">Scanning IPs... Please wait.</div>
  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_asset_management_menu("EditAssets"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <div class="span12">
            <div class="hero-unit">
              <h4><?php echo $escaper->escapeHtml($lang['EditAssets']); ?></h4><br>
                <div class="asset-table-container">
                    <?php display_edit_asset_table(); ?>
                </div>
                    
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    $(document).ready(function() {
      changed_value = false;
      $('.multiselect').multiselect({
        buttonWidth: '200px', 
        maxHeight: 250, 
        enableFiltering: true,
        onChange: function(){
          changed_value = true;
        },
        onDropdownHide: function(event){
          if(changed_value == true) updateAsset(null, $(event.currentTarget).prev())
        }
      });
      
      $('.datepicker').datepicker({
         onSelect: function(date, datepciker){
             updateAsset(null, $("#"+datepciker.id));
         }
      });
    });
  </script>
  <?php display_set_default_date_format_script(); ?>
</body>

</html>
