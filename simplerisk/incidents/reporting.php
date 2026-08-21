<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    $breadcrumb_title_key = "";
    $active_sidebar_menu = "Reporting";
    $active_sidebar_submenu = (isset($_GET['menu']) && $_GET['menu'] === 'incident_dashboard')
        ? 'Reporting_Dashboards'
        : 'Reporting_Reports';
    $active_sidebar_thirdmenu = '';
    $active_sidebar_forthmenu = '';

    // The incident dashboard renders the gridstack UILayout, which floats on the
    // gray page ground and self-aligns with Home-tuned negative margins. It must
    // NOT sit in the white `.bg-white` slab the other (datatable/chart) sub-pages
    // share — that slab + the negative margins make the grid poke out over the
    // edit bar. Swap in a transparent, margin-neutralized wrapper for it instead.
    $embed_dashboard = (isset($_GET['menu']) && $_GET['menu'] === 'incident_dashboard');

    // If a menu was provided
    if (isset($_GET['menu'])) {

        // If the page for the menu was displayed
        switch ($_GET['menu']) {
            // If the overview page was displayed
            case "overview":
                $breadcrumb_title_key = 'Overview';
                break;
            // If the incident trend page was displayed
            case "incident_trend":
                $breadcrumb_title_key = 'IncidentTrend';
                break;
            // If the dynamic incident report page was displayed
            case "dynamic_incident_report":
                $breadcrumb_title_key = 'DynamicIncidentReport';
                break;
            // If the lessons learned page was displayed
            case "lessons_learned":
                $breadcrumb_title_key = 'LessonsLearned';
                break;
            // If the incident dashboard page was displayed
            case "incident_dashboard":
                $breadcrumb_title_key = 'IncidentDashboard';
                break;
            // IF the overview page was displayed by default
            default:
                $breadcrumb_title_key = 'Overview';
                break;
        }
        
    // If no menu was provided
    } else {
        $breadcrumb_title_key = "Reporting";
    }
    render_header_and_sidebar(['datatables', 'tabs:logic', 'multiselect', 'blockUI', 'datetimerangepicker', 'chart.js', 'UILayoutWidget'], ['check_im_reporting' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu, $active_sidebar_thirdmenu, $active_sidebar_forthmenu);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));

    // If the Incident Management Extra is enabled
    if (incident_management_extra()) {       

        // Load the Incident Management Extra
        require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));

        process_incident_management();

    } else {

        // Redirect them to the activation page
        header("Location: ../admin/incidentmanagement.php");

    }

?>
<?php
    // If the Incident Management Extra is enabled
    if (incident_management_extra()) {
        // Include the incident management javascript file
        echo "<script src='../extras/incident_management/js/incident_management.js?" . current_version("app") . "' defer></script>";
        // Include the incident management css file
        echo "<link rel='stylesheet' href='../extras/incident_management/css/incident_management.css?" . current_version("app") . "'>";
    }
?>
<script>
	$(function() {
        $(".datepicker").initAsDateRangePicker();
	});
</script>
<div class="row <?= $embed_dashboard ? 'sr-dash-embed' : 'bg-white' ?>">
	<div class="col-12">
		<div id="appetite-tab-content">
			<div class="status-tabs">
				<div class="tab-content">
    <!-- Display the Reporting -->
    <?php

        // If a menu was provided
        if (isset($_GET['menu'])) {

            // Display the page for the menu
            switch ($_GET['menu']) {

                // Display the overview page
                case "overview":
                    display_incident_management_overview();
                    break;

                // Display the incident trend page
                case "incident_trend":
                    display_incident_management_incident_trend();
                    break;
                
                // Display the dynamic incident report page
                case "dynamic_incident_report":
                    display_incident_management_dynamic_incident_report();
                    break;

                // Display the lessons learned page
                case "lessons_learned":
                    display_incident_management_reporting_lessons_learned();
                    break;

                // Display the incident dashboard page
                case "incident_dashboard":
                    // Dashboard date-range toolbar (mirrors the governance framework
                    // filter): a preset dropdown + a custom from/to picker, hoisted by
                    // the JS below into the breadcrumb row under "Edit layout". The
                    // selection rides in the URL (im_range / im_from / im_to) and every
                    // incident widget's query scopes to it via im_dashboard_date_where().
                    $im_range_sel = preg_replace('/[^a-z0-9]/', '', strtolower((string)($_GET['im_range'] ?? 'all')));
                    if ($im_range_sel === '') { $im_range_sel = 'all'; }
                    $im_from_sel = (isset($_GET['im_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['im_from'])) ? $_GET['im_from'] : '';
                    $im_to_sel   = (isset($_GET['im_to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['im_to']))   ? $_GET['im_to']   : '';
                    $im_range_options = [
                        'all'    => $lang['AllTime'],
                        '7d'     => $lang['Last7Days'],
                        '30d'    => $lang['Last30Days'],
                        '90d'    => $lang['Last90Days'],
                        'ytd'    => $lang['YearToDate'],
                        'custom' => $lang['CustomRange'],
                    ];
                    echo "<div class='sr-dashfilter' id='im_dashfilter'>"
                       . "<label class='sr-dashfilter__label' for='im_range_select'>" . $escaper->escapeHtml($lang['DateRange']) . "</label>"
                       . "<div class='sr-dashfilter__control'><select id='im_range_select' class='form-select form-select-sm'>";
                    foreach ($im_range_options as $val => $label) {
                        echo "<option value='" . $escaper->escapeHtml($val) . "'" . ($val === $im_range_sel ? ' selected' : '') . ">" . $escaper->escapeHtml($label) . "</option>";
                    }
                    echo "</select></div>"
                       . "<div class='sr-dashfilter__custom' id='im_range_custom'" . ($im_range_sel === 'custom' ? '' : " style='display:none;'") . ">"
                       . "<input type='date' id='im_from_input' class='form-control form-control-sm' value='" . $escaper->escapeHtml($im_from_sel) . "'>"
                       . "<span class='sr-dashfilter__dash'>&ndash;</span>"
                       . "<input type='date' id='im_to_input' class='form-control form-control-sm' value='" . $escaper->escapeHtml($im_to_sel) . "'>"
                       . "</div>"
                       . "<form id='im_range_form' method='GET' class='sr-dashfilter__form'>"
                       . "<input type='hidden' name='menu' value='incident_dashboard'>"
                       . "<input type='hidden' name='im_range' id='im_range_hidden' value='" . $escaper->escapeHtml($im_range_sel) . "'>"
                       . "<input type='hidden' name='im_from' id='im_from_hidden' value='" . $escaper->escapeHtml($im_from_sel) . "'>"
                       . "<input type='hidden' name='im_to' id='im_to_hidden' value='" . $escaper->escapeHtml($im_to_sel) . "'>"
                       . "</form></div>";

                    (new \includes\Widgets\UILayout('incident_dashboard'))->render();

                    echo <<<'SCRIPT'
                    <script>
                    $(function() {
                        // Hoist the range filter up into the breadcrumb row, right-aligned
                        // under the Edit-layout button (mirrors the governance filter).
                        var $sub = $('.page-breadcrumb .page-subtitle').first();
                        var $filter = $('#im_dashfilter').first();
                        if ($sub.length && $filter.length) {
                            $sub.addClass('page-subtitle--with-action');
                            $sub.append($filter);
                        }
                        function submitRange() {
                            var v = $('#im_range_select').val();
                            $('#im_range_hidden').val(v);
                            if (v === 'custom') {
                                $('#im_from_hidden').val($('#im_from_input').val());
                                $('#im_to_hidden').val($('#im_to_input').val());
                            } else {
                                $('#im_from_hidden').val('');
                                $('#im_to_hidden').val('');
                            }
                            $('#im_range_form').submit();
                        }
                        $('#im_range_select').on('change', function() {
                            if ($(this).val() === 'custom') {
                                $('#im_range_custom').show();  // wait for both dates
                            } else {
                                $('#im_range_custom').hide();
                                submitRange();
                            }
                        });
                        $('#im_from_input, #im_to_input').on('change', function() {
                            if ($('#im_from_input').val() && $('#im_to_input').val()) {
                                submitRange();
                            }
                        });
                    });
                    </script>
                    SCRIPT;
                    break;

                // Display the overview page by default
                default:
                    display_incident_management_overview();
                    break;

            }
            
        // If no menu was provided
        } else {

                    // Display the overview page by default
                    display_incident_management_overview();

        }
    ?>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>