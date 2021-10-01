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

    $score_used = 'inherent';
    if (!empty($_GET['score_used']) && $_GET['score_used'] === 'residual') {
        $score_used = 'residual';
    }
    
    $next_review_date_uses = get_setting('next_review_date_uses');
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

    <?php
        // Use these HighCharts scripts
        $scripts = [
                'highcharts.js',
        ];

        // Display the highcharts javascript source
        display_highcharts_javascript($scripts);

?>

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
        <script>
            $(function() {
                $('#score_used_dropdown').change(function() {
                    $('#score_used').val(this.value);
                    $('#scoring_form').submit();
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
                    <?php view_reporting_menu("HighRiskReport"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <div class="span7">
                            <u><?php echo $escaper->escapeHtml($lang['RiskScoreUsing']); ?></u>&nbsp;
                            <select id="score_used_dropdown" class="form-field form-control" style="width:auto;">
                                <option value='inherent' <?php if ($score_used !== "residual") echo 'selected'; ?>><?php echo $escaper->escapeHtml($lang['InherentRisk']);?></option>
                                <option value='residual' <?php if ($score_used === "residual") echo 'selected'; ?>><?php echo $escaper->escapeHtml($lang['ResidualRisk']);?></option>
                            </select>
                            <form id="scoring_form" method="GET" style="display: none;">
                                <input type="hidden" id="score_used" name="score_used" />
                            </form>
                            <div class="well">
                                <?php
                                    $open = get_open_risks();
                                    $high = get_risk_count_of_risk_level('High', $score_used);
                                    $veryhigh = get_risk_count_of_risk_level('Very High', $score_used);

                                    // If there are open risks
                                    if ($open != 0) {
                                        $highpercent = 100*($high/$open);
                                        $veryhighpercent = 100*($veryhigh/$open);
                                    } else {
                                        $highpercent = 0;
                                        $veryhighpercent = 0;
                                    }

                                    echo "<h3>" . $escaper->escapeHtml(_lang('NumberOfOpenRisks', ['number' => $open], false)) . "</h3>";

                                    // If we have very high risks
                                    if ($veryhigh > 0) {
                                        $display_name = get_risk_level_display_name('Very High');
                                        echo "<h3>" . $escaper->escapeHtml(_lang('RiskNumberOfRiskLevel', ['display_name' => $display_name, 'number' => $veryhigh], false)) . "</h3>";
                                        echo "<h3>" . $escaper->escapeHtml(_lang('RiskPercentageOfRiskLevel', ['display_name' => $display_name, 'percentage' => round($veryhighpercent, 2)], false)) . "</h3>";
                                    }

                                    // If we have high risks
                                    if ($high > 0) {
                                        $display_name = get_risk_level_display_name('High');
                                        echo "<h3>" . $escaper->escapeHtml(_lang('RiskNumberOfRiskLevel', ['display_name' => $display_name, 'number' => $high], false)) . "</h3>";
                                        echo "<h3>" . $escaper->escapeHtml(_lang('RiskPercentageOfRiskLevel', ['display_name' => $display_name, 'percentage' => round($highpercent, 2)], false)) . "</h3>";
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="span5">
                            <div class="well">
                                <?php open_risk_level_pie($escaper->escapeHtml($lang['RiskLevel']), false, $score_used); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                        // Display the warning when the selected score is not matching with the 'next_review_date_uses' setting
                        if (($next_review_date_uses === 'ResidualRisk' ? 'residual' : 'inherent') !== $score_used) {
                            $warning = _lang('HighRiskReport_ScoreWarning', [
                                'score_used' => $lang[$score_used === 'inherent' ? 'InherentRisk' : 'ResidualRisk'],
                                'next_review_date_uses_name' => $lang['NextReviewDateUses'],
                                'management_review_header' => $lang['ManagementReview'],
                                'next_review_date_uses_value' => $lang[$next_review_date_uses]
                            ], false);
                            echo "<div class='high-risk-report-score-warning'>" . $escaper->escapeHtml($warning) . "</div>";
                        }
                    ?>
                    <table id="high-risk-datatable" width="100%" class="risk-datatable table table-bordered table-striped table-condensed">
                        <thead>
                            <tr>
                                <th data-name='id' align="left" width="50px" valign="top"><?php echo $escaper->escapeHtml($lang['ID']); ?></th>
                                <th data-name='risk_status' align="left" width="150px" valign="top"><?php echo $escaper->escapeHtml($lang['Status']); ?></th>
                                <th data-name='subject' align="left" width="300px" valign="top"><?php echo $escaper->escapeHtml($lang['Subject']); ?></th>
                                <th data-name='score' align="center" width="65px" valign="top"><?php echo $escaper->escapeHtml($lang[$score_used !== "residual" ? 'InherentRisk' : 'ResidualRisk']); ?></th>
                                <th data-name='submission_date' align="center" width="100px" valign="top"><?php echo $escaper->escapeHtml($lang['Submitted']); ?></th>
                                <th data-name='mitigation_planned' align="center" width="150px" valign="top"><?php echo $escaper->escapeHtml($lang['MitigationPlanned']); ?></th>
                                <th data-name='management_review' align="center" width="150px" valign="top"><?php echo $escaper->escapeHtml($lang['ManagementReview']); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                    <br>
                    <script>
                        var pageLength = 10;
                        var yes_str = '<?php echo $escaper->escapeHtml($lang['Yes']);?>';
                        var no_str = '<?php echo $escaper->escapeHtml($lang['No']);?>';
                        var PASTDUE_str = '<?php echo $escaper->escapeHtml($lang['PASTDUE']);?>';
                        $('#high-risk-datatable thead tr').clone(true).appendTo( '#high-risk-datatable thead' );
                        $('#high-risk-datatable thead tr:eq(1) th').each( function (i) {
                            var title = $(this).text();
                            var data_name = $(this).attr("data-name");
                            if(data_name == "mitigation_planned") {
                                $(this).html( '<select name="mitigation_planned"><option value="">--</option><option value="'+yes_str+'">'+yes_str+'</option><option value="'+no_str+'">'+no_str+'</option></select>' );
                            } else if(data_name == 'management_review') {
                                $(this).html( '<select name="management_review"><option value="">--</option><option value="'+yes_str+'">'+yes_str+'</option><option value="'+no_str+'">'+no_str+'</option><option value="'+PASTDUE_str+'">'+PASTDUE_str+'</option></select>' );
                            } else {
                            	$(this).html(''); // To clear the title out of the header cell
                            	$('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                            }
                     
                            $( 'input, select', this ).on( 'keyup change', function () {
                                if ( datatableInstance.column(i).search() !== this.value ) {
                                    datatableInstance.column(i).search( this.value ).draw();
                                }
                            } );
                        } );
                        var datatableInstance = $('#high-risk-datatable').DataTable({
                            //bFilter: false,
                            bLengthChange: false,
                            processing: true,
                            serverSide: true,
                            bSort: true,
                            orderCellsTop: true,
                            pagingType: "full_numbers",
                            pageLength: pageLength,
                            dom : "lrti<'#view-all.view-all'>p",
                            createdRow: function(row, data, index){
                                var background = $('.background-class', $(row)).data('background');
                                $(row).find('td').addClass(background)
                            },
                            order: [[3, 'DESC']],
                            ajax: {
                                url: BASE_URL + '/api/reports/high_risk?score_used=<?php echo $score_used; ?>',
                                type: "POST",
                                error: function(xhr,status,error){
                                    retryCSRF(xhr, this);
                                }
                            },
                            columnDefs : [
                                {
                                    'targets' : [3],
                                    'className' : 'risk-cell',
                                }
                            ]
                        });

                        // Add paginate options
                        datatableInstance.on('draw', function(e, settings){
                            $('.paginate_button.first').html('<i class="fa fa-chevron-left"></i><i class="fa fa-chevron-left"></i>');
                            $('.paginate_button.previous').html('<i class="fa fa-chevron-left"></i>');

                            $('.paginate_button.last').html('<i class="fa fa-chevron-right"></i><i class="fa fa-chevron-right"></i>');
                            $('.paginate_button.next').html('<i class="fa fa-chevron-right"></i>');
                        });

                        // Add all text to View All button on bottom
                        $('.view-all').html("<?php echo $escaper->escapeHtml($lang['ALL']); ?>");

                        // View All
                        $(".view-all").click(function(){
                            var oSettings =  datatableInstance.settings();
                            oSettings[0]._iDisplayLength = -1;
                            datatableInstance.draw();
                            $(this).addClass("current");
                        });

                        // Page event
                        $("body").on("click", "span > .paginate_button", function(){
                            var index = $(this).attr('aria-controls').replace("DataTables_Table_", "");

                            var oSettings =  datatableInstance.settings();
                            if(oSettings[0]._iDisplayLength == -1){
                                $(this).parents(".dataTables_wrapper").find('.view-all').removeClass('current');
                                oSettings[0]._iDisplayLength = pageLength;
                                datatableInstance.draw();
                            }
                        });
                    </script>
                </div>
            </div>
        </div>
    </body>

</html>
