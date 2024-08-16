<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/reporting.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Add various security headers
add_security_headers();

// Add the session
add_session_check();

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

// Set a global variable for the current app version, so we don't have to call a function every time
$current_app_version = current_version("app");

$custom_display_settings = $_SESSION['custom_display_settings'];
if(!is_array($custom_display_settings)){
	$custom_display_settings = array(
        'id',
        'subject',
        'calculated_risk',
        'submission_date',
        'mitigation_planned',
        'management_review'
	);
}

$status = isset($_GET["status"])?$_GET["status"]:0;
$group = isset($_GET["group"])?$_GET["group"]:"";
$sort = isset($_GET["sort"])?$_GET["sort"]:0;
$group_value = isset($_GET["group_value"])?rawurldecode($_GET["group_value"]):"";
$order_column = isset($_GET["order_column"])?$_GET["order_column"]:null;
$order_dir = isset($_GET["order_dir"])?$_GET["order_dir"]:"asc";
$column_filters = isset($_GET["column_filters"])?$_GET["column_filters"]:[];

?>
<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">
    <head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

        <!-- Favicon icon  -->
        <?php setup_favicon(".."); ?>
        
        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="../css/style.min.css?<?= $current_app_version ?>" />

        <!-- jQuery CSS -->
        <link rel="stylesheet" href="../vendor/node_modules/jquery-ui/dist/themes/base/jquery-ui.min.css?<?= $current_app_version ?>">

        <!-- extra css -->

        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

        <!-- jQuery Javascript -->
        <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>
        <script src="../vendor/node_modules/jquery-ui/dist/jquery-ui.min.js?<?= $current_app_version ?>" id="script_jqueryui"></script>

        <!-- Bootstrap tether Core JavaScript -->
        <script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>

        <script src="../js/simplerisk/common.js?<?= $current_app_version ?>"></script>

        <?php setup_alert_requirements(".."); ?>  

    </head>
    <body>
        <style>
            #risk-table-container{overflow: auto;}
        </style>
        <div class="print-by-group-page">
            <div class="print-by-group-page-body">

    <!-- Once it has been activated -->
    <?php if (import_export_extra()) { ?>

                <div class="card-body border my-2">
                    <div id="risk-table-container">
                        <?php get_risks_by_group($status, $group, $sort, $group_value, $custom_display_settings, $column_filters, $order_column, $order_dir); ?>
                    </div>
                </div>

    <?php } else { ?>

                <div class="card-body border my-2">
                    <?= $escaper->escapeHtml($lang['ImportExportIsDeactivated']) ?>
                </div>

    <?php } ?>
    
            </div>
        </div>
    </body>
</html>