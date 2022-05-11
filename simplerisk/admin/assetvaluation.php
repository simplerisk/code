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

    // Check if the default asset valuation was submitted
    if (isset($_POST['update_default_value']))
    {
        // If the currency is set and is not empty
        if (isset($_POST['currency']) && ($_POST['currency'] != ""))
        {
            // If the currency value is one character long
            if (strlen($_POST['currency']) <= 6)
            {
                // Update the currency
                update_setting("currency", $_POST['currency']);
            }
        }

        // If value is set and is numeric
        if (isset($_POST['value']) && is_numeric($_POST['value']))
        {
            $value = (int)$_POST['value'];

            // If the value is between 1 and 10
            if ($value >= 1 && $value <= 10)
            {
                // Update the default asset valuation
                update_default_asset_valuation($value);
            }
        }
    }

    // Check if the automatic asset valuation was submitted
    if (isset($_POST['update_auto_value']))
    {
        $min_value = $_POST['min_value'];
        $max_value = $_POST['max_value'];

        // If the minimum value is an integer >= 0
        if (is_numeric($min_value) && $min_value >= 0)
        {
            // If the maximum value is an integer
            if (is_numeric($max_value))
            {
                // Update the asset values
                $success = update_asset_values($min_value, $max_value);

                // If the update was successful
                if ($success)
                {
                    // Display an alert
                    set_alert(true, "good", "The asset valuation settings were updated successfully.");
                }
                else
                {
                    // Display an alert
                    set_alert(true, "bad", "There was an issue updating the asset valuation settings.");
                }
            }
            else
            {
                // Display an alert
                set_alert(true, "bad", "Please specify an integer for the maximum value.");
            }
        }
        else
        {
            // Display an alert
            set_alert(true, "bad", "Please specify an integer greater than or equal to zero for the minimum value.");
        }
    }

    // Check if the manual asset valuation was submitted
    if (isset($_POST['update_manual_value']))
    {
        // For each value range
        for ($i=1; $i<=10; $i++)
        {
            $valuation_level_name = $_POST["valuation_level_name_" . $i];
            if (strlen($valuation_level_name) > 100) {
                set_alert(true, "bad", _lang('ValuationLevelNameSizeError', array('valuation_level_name' => $valuation_level_name)));
                refresh();
            }
        }

        // For each value range
        for ($i=1; $i<=10; $i++)
        {
            $id = $i;
            $min_value = $_POST["min_value_" . $i];
            $max_value = $_POST["max_value_" . $i];
            $valuation_level_name = $_POST["valuation_level_name_" . $i];

            // If the min_value and max_value are numeric
            if (is_numeric($min_value) && is_numeric($max_value))
            {
                // Update the asset value
                $success = update_asset_value($id, $min_value, $max_value, $valuation_level_name);

                // If the update was successful
                if ($success)
                {
                    // Display an alert
                    set_alert(true, "good", "The asset valuation settings were updated successfully.");
                }
                else
                {
                    // Display an alert
                    set_alert(true, "bad", "There was an issue updating the asset valuation settings.");
                }
            }
        }
    }
?>

<!doctype html>
<html>

    <head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

	display_bootstrap_javascript();
?>
        <script language="javascript" src="../js/asset_valuation.js?<?php echo current_version("app"); ?>" type="text/javascript"></script>

        <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

        <?php $url = "<svg xmlns=\"http://www.w3.org/2000/svg\"><text x=\"5px\" y=\"20px\" font-size=\"15\" stroke=\"green\" fill=\"green\">" . get_setting("currency") . "</text></svg>"; ?>
        <style type="text/css">
            #dollarsign {
                background-image: url('data:image/svg+xml;base64,<?php echo base64_encode($url); ?>');
                background-repeat: no-repeat;
                background-color: white;
                background-position: left;
                padding-left: 35px;
            }
        </style>

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
                    <?php view_configure_menu("AssetValuation"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <div class="span12">
                            <div class="hero-unit">
                                <form name="automatic" method="post" action="">
                                    <h4><?php echo $escaper->escapeHtml($lang['AutomaticAssetValuation']); ?>:</h4>
                                    <table border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td><?php echo $escaper->escapeHtml($lang['MinimumValue']); ?>:&nbsp;</td>
                                            <td><input id="dollarsign" type="number" name="min_value" min="0" size="20" value="<?php echo asset_min_value(); ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo $escaper->escapeHtml($lang['MaximumValue']); ?>:&nbsp;</td>
                                            <td><input id="dollarsign" type="number" name="max_value" size="20" value="<?php echo asset_max_value(); ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2"><input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_auto_value" /></td>
                                        </tr>
                                    </table>
                                </form>
                            </div>
                            <div class="hero-unit">
                                <form name="manual" method="post" action="">
                                    <h4><?php echo $escaper->escapeHtml($lang['ManualAssetValuation']); ?>:</h4>
                                    <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_manual_value" />
                                    <?php display_asset_valuation_table(); ?>
                                    <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_manual_value" />
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
