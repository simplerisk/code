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

// Record the page the workflow started from as a session variable
$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];

$risk_appetite = get_setting("risk_appetite", 0);

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">
    <head>
        <script src="../js/jquery.min.js"></script>
        <script src="../js/jquery.easyui.min.js"></script>
        <script src="../js/jquery-ui.min.js"></script>
        <script src="../js/bootstrap.min.js"></script>
        <script src="../js/jquery.dataTables.js"></script>

        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/easyui.css">
        <link rel="stylesheet" href="../css/bootstrap.css">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css">
        <link rel="stylesheet" href="../css/jquery.dataTables.css">
        <link rel="stylesheet" href="../css/prioritize.css">
        <link rel="stylesheet" href="../css/divshot-util.css">
        <link rel="stylesheet" href="../css/divshot-canvas.css">
        <link rel="stylesheet" href="../css/display.css">
        <link rel="stylesheet" href="../css/style.css">

        <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="../css/theme.css">

        <?php
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
                var appetite_type = $this.data('type');
                var riskDatatable = $this.DataTable({
                    scrollX: true,
                    bFilter: false,
                    bLengthChange: false,
                    processing: true,
                    serverSide: true,
                    bSort: true,
                    pagingType: "full_numbers",
                    dom : "flrti<'.download-by-group'><'#view-all-"+ id +".view-all'>p",
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
                        $this.addClass("current");
                    } else {
                        oSettings[0]._iDisplayLength = -1;
                        riskDataTables[id].draw()
                        $this.removeClass('current');
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
