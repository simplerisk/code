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
add_session_check();

// Include the CSRF Magic library
include_csrf_magic();


// Include the SimpleRisk language file
require_once(language_file());

// Record the page the workflow started from as a session variable
$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];
?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

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

	display_bootstrap_javascript();
?>
    <script src="../js/sorttable.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/obsolete.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
    
    
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">

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
        view_top_menu("Reporting");
        // Get any alert messages
        get_alert();
    ?>
    <script>
        $(document).ready(function(){
            $('#risk-datatable thead tr').clone(true).appendTo( '#risk-datatable thead');
            $('#risk-datatable thead tr:eq(1) th').each( function (i) {
                var title = $(this).text();
                var data_name = $(this).attr("data-name");
                if(data_name == "mitigation_planned") {
                    $(this).html( '<select name="mitigation_planned"><option value="">--</option><option value="yes">Yes</option><option value="no">No</option></select>' );
                } else if(data_name == "management_review") {
                    $(this).html( '<select name="management_review"><option value="">--</option><option value="yes">Yes</option><option value="no">No</option></select>' );
                } else {
                	$(this).html(''); // To clear the title out of the header cell
                	$('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                }
         
                $( 'input, select', this ).on( 'keyup change', function () {
                    if ( riskTable.column(i).search() !== this.value ) {
                        riskTable.column(i).search( this.value ).draw();
                    }
                });
            });
            var riskTable = $('#risk-datatable').DataTable( {
                //bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                orderCellsTop: true,
                pagingType: "full_numbers",
                dom : "lrti<'#view-all.view-all'>p",
                pageLength: 10,
                ajax: {
                    url: BASE_URL + '/api/reports/recent_commented_risk',
                    type: "POST",
                    error: function(xhr,status,error){
                        retryCSRF(xhr, this);
                    }
                },
                order: [[5, 'desc']],
                columnDefs : [
                    {
                        'targets' : [3,4],
                        'className' : 'risk-cell',
                    }
                ]
            });
            // Add paginate options
            riskTable.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class="fa fa-chevron-left"></i><i class="fa fa-chevron-left"></i>');
                $('.paginate_button.previous').html('<i class="fa fa-chevron-left"></i>');

                $('.paginate_button.last').html('<i class="fa fa-chevron-right"></i><i class="fa fa-chevron-right"></i>');
                $('.paginate_button.next').html('<i class="fa fa-chevron-right"></i>');
            });
            // Add all text to View All button on bottom
            $('.view-all').html("<?php echo $escaper->escapeHtml($lang['ALL']); ?>").click(function(){
                var oSettings =  riskTable.settings();
                oSettings[0]._iDisplayLength = -1;
                riskTable.draw();
                $(this).addClass("current");
            });

            // Page event
            $("body").on("click", "span > .paginate_button", function(){
                var index = $(this).attr('aria-controls').replace("DataTables_Table_", "");

                var oSettings =  riskTable.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(".dataTables_wrapper").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    riskTable.draw();
                }
            });
        });
    </script>
    <div class="container-fluid">
        <div class="row-fluid">
             <div class="span3">
                <?php view_reporting_menu("CurrentRiskComments"); ?>
            </div>
            <div class="span9">
                <div class="row-fluid"><p><?php echo $escaper->escapeHtml($lang['ReportRecentCommentedHelp']); ?>.</p></div>
                <table id="risk-datatable" width="100%" class="risk-datatable table table-bordered table-striped table-condensed">
                    <thead>
                        <tr>
                            <th data-name='id' align="left" width="50px" valign="top"><?php echo $escaper->escapeHtml($lang['ID']); ?></th>
                            <th data-name='risk_status' align="left" width="150px" valign="top"><?php echo $escaper->escapeHtml($lang['Status']); ?></th>
                            <th data-name='subject' align="left" width="300px" valign="top"><?php echo $escaper->escapeHtml($lang['Subject']); ?></th>
                            <th data-name='score' align="center" width="80px" valign="top"><?php echo $escaper->escapeHtml($lang['InherentRisk']); ?></th>
                            <th data-name='residual_risk' align=\"center\" width="80px"  valign=\"top\"><?php echo $escaper->escapeHtml($lang['ResidualRisk']); ?></th>
                            <th data-name='comment_date' align="center" width="150px" valign="top"><?php echo $escaper->escapeHtml($lang['CommentDate']); ?></th>
                            <th data-name='comment' align="center" width="150px" valign="top"><?php echo $escaper->escapeHtml($lang['Comment']); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
