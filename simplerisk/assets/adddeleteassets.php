<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/assets.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    add_security_headers();

    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    if (!isset($_SESSION))
    {
        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    require_once(language_file());

    require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

    function csrf_startup() {
        csrf_conf('rewrite-js', $_SESSION['base_url'].'/includes/csrf-magic/csrf-magic.js');
    }

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
        set_unauthenticated_redirect();
        header("Location: ../index.php");
        exit(0);
    }

    // Check if the user has access to manage assets
    if (!isset($_SESSION["asset"]) || $_SESSION["asset"] != 1)
    {
        header("Location: ../index.php");
        exit(0);
    }
    else $manage_assets = true;

    // Check if an asset was added
    if ((isset($_POST['add_asset'])) && $manage_assets)
    {
        $name = $_POST['asset_name'];
        $ip = $_POST['ip'];
        $value = $_POST['value'];
        $location = $_POST['location'];
        $team = $_POST['team'];
        $details = $_POST['details'];

        // Add the asset
        $success = add_asset($ip, $name, $value, $location, $team, $details, true);

        // If the asset add was successful
        if ($success)
        {
            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['AssetWasAddedSuccessfully']));
        }
        else
        {
            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['ThereWasAProblemAddingTheAsset']));
        }
        
        refresh();
    }

    // Check if assets were deleted
    $discard_all = isset($_POST['discard_all']);
    if ((isset($_POST['delete_all']) || $discard_all) && $manage_assets)
    {
        $assets = $_POST['assets'];

        // Delete the assets
        $success = delete_assets($assets);

        // If the asset delete was successful
        if ($success)
        {
            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($discard_all? $lang['AssetWasDiscardedSuccessfully']: $lang['AssetWasDeletedSuccessfully']));
        }
        else
        {
            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($discard_all ? $lang['ThereWasAProblemDiscardingTheAsset'] : $lang['ThereWasAProblemDeletingTheAsset']));
        }
    }

    // Check if assets were deleted
    if (isset($_POST['verify_all']) && $manage_assets)
    {
        $assets = $_POST['assets'];

        // Verify the assets
        $success = verify_assets($assets);

        // If the asset verification was successful
        if ($success)
        {
            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['AssetWasVerifiedSuccessfully']));
        }
        else
        {
            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['ThereWasAProblemVerifyingTheAsset']));
        }
    }


?>

<!doctype html>
<html>

<head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/pages/asset.js?<?php echo time() ?>"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">
    <link rel="stylesheet" href="../css/jquery-ui.min.css">


    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">
    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <?php
        setup_alert_requirements("..");
    ?>  

    <?php display_simple_autocomplete_script(get_unentered_assets()); ?>

</head>

<body>
    <?php
        view_top_menu("AssetManagement");

        // Get any alert messages
        get_alert();
    ?>
    <div id="load" style="display:none;">Scanning IPs... Please wait.</div>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_asset_management_menu("AddDeleteAssets"); ?>
            </div>
            <div class="span9">
                <div class="row-fluid">
                    <div class="span12">
                        <div class="hero-unit">
                            <h4><?php echo $escaper->escapeHTML($lang['AddANewAsset']); ?></h4>
                            <form name="add" method="post" action="" id="add-asset-container">
                                <?php
                                    display_add_asset();
                                ?>
                                <button type="submit" name="add_asset" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
                            </form>
                        </div>

                        <?php if (has_unverified_assets()) { ?>

                        <div id="unverified_assets" class="hero-unit">

                            <h4><?php echo $escaper->escapeHTML($lang['UnverifiedAssets']); ?></h4>
                            <form name="verify" method="post" action="">
                                <button type="submit" name="verify_all" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['VerifyAll']); ?></button>
                                <button type="submit" name="discard_all" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['DiscardAll']); ?></button>
                                <?php display_unverified_asset_table(); ?>
                                <button type="submit" name="verify_all" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['VerifyAll']); ?></button>
                                <button type="submit" name="discard_all" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['DiscardAll']); ?></button>
                            </form>

                        </div>

                        <?php } ?>
                        <div id="verified_asset_table_wrapper" class="hero-unit" <?php if (!has_verified_assets()) { ?> style="display:none"<?php } ?>>

                            <h4><?php echo $escaper->escapeHTML($lang['VerifiedAssets']); ?></h4>
                            <form name="delete" method="post" action="">
                                <button type="submit" name="delete_all" class="btn btn-primary" style="margin-bottom: 20px;"><?php echo $escaper->escapeHtml($lang['DeleteAll']); ?></button>
                                <?php display_asset_table(); ?>
                                <button type="submit" name="delete_all" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['DeleteAll']); ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        $( document ).ready(function() {
            $("button.verify-asset").click(function() {
                verify_discard_or_delete_asset("verify", $(this));
            });

            $("button.discard-asset").click(function() {
                verify_discard_or_delete_asset("discard", $(this));
            });

            $("button.delete-asset").click(function() {
                verify_discard_or_delete_asset("delete", $(this));
            });
        });
    </script>
    <style type="">
        textarea{
            width: 200px !important;
        }
    </style>
    <?php display_set_default_date_format_script(); ?>
</body>

</html>
