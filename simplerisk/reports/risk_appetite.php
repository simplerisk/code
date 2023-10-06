<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
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

// Record the page the workflow started from as a session variable
$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];

$risk_appetite = get_setting("risk_appetite", 0);

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">
    <head>
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);
?>
	<script src="../js/jquery.easyui.min.js?<?php echo current_version("app"); ?>"></script>
<?php

        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
        <script src="../vendor/node_modules/datatables.net/js/jquery.dataTables.min.js?<?php echo current_version("app"); ?>"></script>

        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/easyui.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/prioritize.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../vendor/node_modules/datatables.net-dt/css/jquery.dataTables.min.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/style.css?<?php echo current_version("app"); ?>">

        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

        <?php
            setup_favicon("..");
            setup_alert_requirements("..");
        ?>

        <style>
            .status-tabs .tabs-nav {
                margin-left: 20px;
            }
        </style>
        <script>
            function activateDatatable(id) {
                var $this = $("#" + id);
                //$('#'+id+' thead .filter').show();
                $('#'+id+' thead tr').clone(true).appendTo( '#'+id+' thead' );
                $('#'+id+' thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
             
                    $( 'input', this ).on( 'keyup change', function () {
                        if ( riskDatatable.column(i).search() !== this.value ) {
                            riskDatatable.column(i).search( this.value ).draw();
                        }
                    } );
                } );
                var appetite_type = $this.data('type');
                var riskDatatable = $this.DataTable({
                    scrollX: true,
                    //bFilter: false,
                    bLengthChange: false,
                    processing: true,
                    serverSide: true,
                    bSort: true,
                    orderCellsTop: true,
                    pagingType: "full_numbers",
                    dom : "lrti<'.download-by-group'><'#view-all-"+ id +".view-all'>p",
                    ajax: {
                        url: BASE_URL + '/api/reports/appetite?type=' + appetite_type,
                        type: "get"
                    },
                    order: [[2, 'desc']],
                    columnDefs : [
                        {
                            'targets' : [0],
                            'width' : '5%',                            
                        },
                        {
                            "targets" : [-1, -2],
                            "className" : "risk-cell",
                            'width' : '10%'
                        }
                    ]
                });

                riskDatatable.on('draw', function(e, settings){
                    if(settings._iDisplayLength == -1){
                        $("#" + settings.sTableId + "_wrapper").find(".paginate_button.current").removeClass("current");
                    }
                    $('.paginate_button.first').html('<i class="fa fa-chevron-left"></i><i class="fa fa-chevron-left"></i>');
                    $('.paginate_button.previous').html('<i class="fa fa-chevron-left"></i>');

                    $('.paginate_button.last').html('<i class="fa fa-chevron-right"></i><i class="fa fa-chevron-right"></i>');
                    $('.paginate_button.next').html('<i class="fa fa-chevron-right"></i>');
                });

                riskDataTables[id] = riskDatatable;

                $('.view-all').html("<?php echo $escaper->escapeHtml($lang['All']); ?>");

                $(".view-all").click(function(){
                    var $this = $(this);
                    var id = $(this).attr('id').replace("view-all-", "");
                    var oSettings =  riskDataTables[id].settings();
                    if(oSettings[0]._iDisplayLength == -1){
                        oSettings[0]._iDisplayLength = 10;
                        riskDataTables[id].draw()
                        $this.removeClass("current");
                    } else {
                        oSettings[0]._iDisplayLength = -1;
                        riskDataTables[id].draw()
                        $this.addClass('current');
                    }
                });
            }

            var riskDataTables = ["outside-appetite-table"];
            $(document).ready(function(){
                $('#within-appetite').show();

                activateDatatable("outside-appetite-table");
 
                var $tabs = $("#appetite-tab-content").tabs({
                    activate: function(event, ui){
                        datatableId = $(".table-container", ui.newPanel).data('id');
                        if (!(datatableId in riskDataTables)){
                            activateDatatable(datatableId);
                        }
                    }
                });

                $("body").on("click", "a.paginate_button", function(){
                    var id = $(this).attr('aria-controls');
                    var oSettings =  riskDataTables[id].settings();
                    if(oSettings[0]._iDisplayLength == -1){
                        $(this).parents(".dataTables_wrapper").find('.view-all').removeClass('current');
                        oSettings[0]._iDisplayLength = 10;
                        riskDataTables[id].draw()
                    }
                });
            });
        </script>
    </head>

    <body>

              
          <?php
              view_top_menu("Reporting");

              // Get any alert messages
              get_alert();
          ?>


        <div class="container-fluid">
            <div class="row-fluid">
                <div class="span3">
                    <?php view_reporting_menu("RiskAppetiteReport"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <div class="span12">
                            <div id="appetite-tab-content">
                                <div class="status-tabs">
                                    <ul class="clearfix tabs-nav">
                                        <li><a href="#outside-appetite" class="status"><?php echo $escaper->escapeHtml($lang['OutsideAppetite']); ?> (<?php echo $escaper->escapeHtml("> {$risk_appetite}"); ?>)</a></li>
                                        <li><a href="#within-appetite" class="status"><?php echo $escaper->escapeHtml($lang['WithinAppetite']); ?> (<?php echo $escaper->escapeHtml("<= {$risk_appetite}"); ?>)</a></li>
                                    </ul>
                                    
                                    <div class="content">
                                        <div id="outside-appetite" class="custom-treegrid-container" style="display: none">
                                            <?php display_appetite_datatable(false); ?>
                                        </div>
                                        <div id="within-appetite" class="custom-treegrid-container" style="display: none">
                                            <?php display_appetite_datatable(); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php display_set_default_date_format_script(); ?>
    </body>

</html>
