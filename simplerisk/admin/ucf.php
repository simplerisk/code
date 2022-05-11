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
    if (is_dir(realpath(__DIR__ . '/../extras/ucf'))) {
        // Include the UCF Extra
        require_once(realpath(__DIR__ . '/../extras/ucf/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate'])) {
            // Enable the UCF Extra
            enable_ucf_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate'])) {
            // Disable the UCF Extra
            disable_ucf_extra();
        }

	// If the user wants to update the connection settings
	if (isset($_POST['update_connection_settings']))
	{
		update_ucf_connection_settings();
	}

	// If the user wants to enable ad lists
	if (isset($_POST['ucf_ad_list_enable']))
	{
		enable_ucf_ad_lists();
	}

        // If the user wants to disable ad lists
        if (isset($_POST['ucf_ad_list_disable']))
        {
		disable_ucf_ad_lists();
        }

	// If the user wants to enable authority documents
	if (isset($_POST['ucf_authority_documents_enable']))
	{
		enable_ucf_authority_documents();
	}

	// If the user wants to disable authority documents
	if (isset($_POST['ucf_authority_documents_disable']))
	{
		disable_ucf_authority_documents();
	}

/*
	// If the user wants to install UCF frameworks
	if (isset($_POST['install_frameworks']))
	{
		install_ucf_frameworks();
	}

	// If the user wants to uninstall UCF frameworks
	if (isset($_POST['uninstall_frameworks']))
	{
		uninstall_ucf_frameworks();
	}
*/
    }

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display()                                    
{
    global $lang;
    global $escaper;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/ucf')))
    {
        // But the extra is not activated
        if (!ucf_extra())
        {
                // If the extra is not restricted based on the install type
                if (!restricted_extra("ucf"))
                {
                    echo "<form name=\"activate_extra\" method=\"post\" action=\"\">\n";
                    echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" /><br />\n";
                    echo "</form>\n";
                }
                else // The extra is restricted
                    echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
        }
        else
        { // Once it has been activated
                // Include the UCF Extra
                require_once(realpath(__DIR__ . '/../extras/ucf/index.php'));

                echo "
                    <form name=\"deactivate\" method=\"post\">
                        <font color=\"green\">
                            <b>" . $escaper->escapeHtml($lang['Activated']) . "</b>
                        </font> [" . ucf_version() . "]
                        &nbsp;&nbsp;
                        <input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" />
                    </form>\n";

                display_ucf_extra_options();
        }
    }
    else
    { // Otherwise, the Extra does not exist
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

    <style type="text/css">
	.ad-lists-box-1,
	.ad-lists-box-2,
        .authority-document-box-1,
	.authority-document-box-2 {
	    float: left;
	    width: 100%;
	}

	#ucf_authority_documents_enabled, #ucf_ad_list_disabled, #ucf_ad_list_enabled, #ucf_authority_documents_disabled {
	    height: 140px;
	    padding: 0;
	}

	.subject-info-arrows {
	    float: middle;
	    width: 100%;
	}

	.btn-default {
	    color: #333;
	    background-color: #fff;
	    border-color: #ccc;
	}

	.btn {
	    display: inline-block;
	    padding: 6px 12px;
	    font-size: 14px;
	    font-weight: 400;
	    line-height: 1.42857143;
	    text-align: center;
	    white-space: nowrap;
	    vertical-align: middle;
	    user-select: none;
	    background-image: none;
	    border: 1px solid transparent;
	    border-radius: 4px;
    </style>

    <script type="text/javascript" src="../js/jquery.tree.min.js?<?php echo current_version("app"); ?>"></script>
    <link rel="stylesheet" type="text/css" href="../css/jquery.tree.min.css?<?php echo current_version("app"); ?>" />
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>
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
              <div class="hero-unit">
                <h4>Unified Compliance Framework (UCF) Extra</h4>
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
<!--
    <script type="text/javascript">
 function strDes(a, b) {
   if (a.value>b.value) return 1;
   else if (a.value<b.value) return -1;
   else return 0;
 }

console.clear();
(function () {
    $('#btnRight').click(function (e) {
        var selectedOpts = $('#lstBox1 option:selected');
        if (selectedOpts.length == 0) {
            toastr.error('Nothing to move.');
            e.preventDefault();
        }
	
	$('#lstBox2').append($(selectedOpts).clone());
	$(selectedOpts).remove();
	var form = document.getElementsByName("ucf_ad_lists");
	form[0].submit();
	e.preventDefault();
    });

    $('#btnAllRight').click(function (e) {
        var selectedOpts = $('#lstBox1 option');
        if (selectedOpts.length == 0) {
            toastr.error('Nothing to move.');
            e.preventDefault();
        }

        $('#lstBox2').append($(selectedOpts).clone());
        $(selectedOpts).remove();
        e.preventDefault();
    });

    $('#btnLeft').click(function (e) {
        var selectedOpts = $('#lstBox2 option:selected');
        if (selectedOpts.length == 0) {
	    toastr.error('Nothing to move.');
            e.preventDefault();
        }

        $('#lstBox1').append($(selectedOpts).clone());
        $(selectedOpts).remove();
        e.preventDefault();
    });

    $('#btnAllLeft').click(function (e) {
        var selectedOpts = $('#lstBox2 option');
        if (selectedOpts.length == 0) {
	    toastr.error('Nothing to move.');
            e.preventDefault();
        }

        $('#lstBox1').append($(selectedOpts).clone());
        $(selectedOpts).remove();
        e.preventDefault();
    });
}(jQuery));
    </script>
-->
  </body>

</html>
