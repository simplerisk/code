<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));

require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

/*******************************************
 * FUNCTION: DISPLAY GRAPHIC TYPE DROPDOWN *
 *******************************************/
function display_graphic_type_dropdown($settings=[])
{
    global $escaper, $lang;

    $type = $escaper->escapeHtml(get_param("POST", "type", $settings?$settings["type"]:""));

    // Set the chart title
    $chart_title = $escaper->escapeHtml(get_param("POST", "chart_title", $settings?$settings["chart_title"]:""));

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span12'>".$escaper->escapeHtml($lang['Visualization'])."</div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span2 text-right'>".$escaper->escapeHtml($lang['Type']).":</div>\n";
    echo "    <div class='span10'>\n";
    echo "        <select id='type' name='type'>\n";
    echo "            <option value='area'" . (!$type || $type === 'area' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['AreaRange'])."</option>\n";
    echo "            <option value='line'" . ($type === 'line' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['Line'])."</option>\n";
    echo "            <option value='column'" . ($type === 'column' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['Bar'])."</option>\n";
    echo "        </select>\n";
    echo "    </div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span2 text-right'>".$escaper->escapeHtml($lang['Title']).":</div>\n";
    echo "    <div class='span4'>\n";
    echo "        <input type='text' name='chart_title' value='{$chart_title}' />\n";
    echo "    </div>\n";
    echo "</div>\n";
}


/****************************
 * FUNCTION: DISPLAY Y AXIS *
 ****************************/
function display_y_axis($settings=[])
{
    global $escaper, $lang;

    // set the Y axis
    $y_axis = $escaper->escapeHtml(get_param("POST", "y_axis", $settings?$settings["y_axis"]:""));

    // Set the y_axis_aggregation
    $y_axis_aggregation = $escaper->escapeHtml(get_param("POST", "y_axis_aggregation", $settings?$settings["y_axis_aggregation"]:""));

    // Set the y_axis_custom_label
    $y_axis_custom_label = $escaper->escapeHtml(get_param("POST", "y_axis_custom_label", $settings?$settings["y_axis_custom_label"]:""));

    // Set the risk_status
    $risk_status = $escaper->escapeHtml(get_param("POST", "risk_status", $settings?$settings["risk_status"]:""));

    // Set the risk_severity
    $default_severity = isset($settings["risk_severity"])?$settings["risk_severity"]:array('very_high' => 1, 'high' => 1, 'medium' => 1, 'low' => 1, 'insignificant' => 1);
    //$default_severity = array('very_high' => 1, 'high' => 1, 'medium' => 1, 'low' => 1, 'insignificant' => 1);
    $risk_severity = get_param("POST", "risk_severity", $default_severity);

    $very_high_display_name = get_risk_level_display_name('Very High');
    $high_display_name      = get_risk_level_display_name('High');
    $medium_display_name    = get_risk_level_display_name('Medium');
    $low_display_name       = get_risk_level_display_name('Low');
    $insignificant_display_name = get_risk_level_display_name('Insignificant');

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span12'>".$escaper->escapeHtml($lang['Y-Axis'])."</div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span2 text-right'>".$escaper->escapeHtml($lang['Aggregation']).":</div>\n";
    echo "    <div class='span4'>\n";
    echo "        <select id='y_axis_aggregation' name='y_axis_aggregation'>\n";
    echo "          <option value='average'" . (!$y_axis_aggregation || $y_axis_aggregation === 'average' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['Average'])."</option>\n";
    echo "          <option value='count'" . ($y_axis_aggregation === 'count' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['Count'])."</option>\n";
    echo "          <option value='total'" . ($y_axis_aggregation === 'total' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['CountTotal'])."</option>\n";
    echo "          <option value='max'" . ($y_axis_aggregation === 'max' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['Max'])."</option>\n";
    echo "          <option value='min'" . ($y_axis_aggregation === 'min' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['Min'])."</option>\n";
    echo "        </select>\n";
    echo "    </div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span2 text-right'>".$escaper->escapeHtml($lang['CustomLabel']).":</div>\n";
    echo "    <div class='span4'>\n";
    echo "        <input type='text' name='y_axis_custom_label' value='{$y_axis_custom_label}' />\n";
    echo "    </div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span2 text-right'>".$escaper->escapeHtml($lang['Y-AxisValue']).":</div>\n";
    echo "    <div class='span4'>\n";
    echo "        <select id='y_axis' name='y_axis'>\n";
    echo "          <option value='inherent_risk'" . ($y_axis === false || $y_axis === 'inherent_risk' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['InherentRisk'])."</option>\n";
    echo "          <option value='residual_risk'" . ($y_axis === false || $y_axis === 'residual_risk' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['ResidualRisk'])."</option>\n";
    echo "        </select>\n";
    echo "    </div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid form-inline'>\n";
    echo "    <div class='span2 text-right'>".$escaper->escapeHtml($lang['Status']).":</div>\n";
    echo "    <div class='span4'>\n";
    echo "        <label><input type='radio' name='risk_status' value='all'" . (!$risk_status || $risk_status === 'all' ? " checked='checked'" : "") . ">&nbsp;<strong>All</strong></label>\n";
    echo "        <label><input type='radio' name='risk_status' value='open'" . ($risk_status === 'open' ? " checked='checked'" : "") . ">&nbsp;<strong>Open</strong></label>\n";
    echo "        <label><input type='radio' name='risk_status' value='closed'" . ($risk_status === 'closed' ? " checked='checked'" : "") . ">&nbsp;<strong>Closed</strong></label>\n";
    echo "    </div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid form-inline'>\n";
    echo "    <div class='span2 text-right'>Severity:</div>\n";
    echo "    <div class='span4'>\n";
    echo "        <label><input type='checkbox' id='very_high' name='risk_severity[very_high]'" . (isset($risk_severity['very_high']) ? " checked='checked'" : "") . ">&nbsp;<strong>".$escaper->escapeHtml($very_high_display_name)."</strong></label>\n";
    echo "        <label><input type='checkbox' id='high' name='risk_severity[high]'" . (isset($risk_severity['high']) ? " checked='checked'" : "") . ">&nbsp;<strong>".$escaper->escapeHtml($high_display_name)."</strong></label>\n";
    echo "        <label><input type='checkbox' id='medium' name='risk_severity[medium]'" . (isset($risk_severity['medium']) ? " checked='checked'" : "") . ">&nbsp;<strong>".$escaper->escapeHtml($medium_display_name)."</strong></label>\n";
    echo "        <label><input type='checkbox' id='low' name='risk_severity[low]'" . (isset($risk_severity['low']) ? " checked='checked'" : "") . ">&nbsp;<strong>".$escaper->escapeHtml($low_display_name)."</strong></label>\n";
    echo "        <label><input type='checkbox' id='insignificant' name='risk_severity[insignificant]'" . (isset($risk_severity['insignificant']) ? " checked='checked'" : "") . ">&nbsp;<strong>".$escaper->escapeHtml($insignificant_display_name)."</strong></label>\n";
    echo "    </div>\n";
    echo "</div>\n";
}

/****************************
 * FUNCTION: DISPLAY X AXIS *
 ****************************/
function display_x_axis($settings=[])
{
    global $escaper, $lang;

    // Set the x_axis_aggregation
    $x_axis_aggregation = $escaper->escapeHtml(get_param("POST", "x_axis_aggregation", $settings?$settings["x_axis_aggregation"]:""));

    // set the X axis
    $x_axis = $escaper->escapeHtml(get_param("POST", "x_axis", $settings?$settings["x_axis"]:""));

    // Set the x_axis_custom_label
    $x_axis_custom_label = $escaper->escapeHtml(get_param("POST", "x_axis_custom_label", $settings?$settings["x_axis_custom_label"]:""));

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span12'>".$escaper->escapeHtml($lang['X-Axis'])."</div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span2 text-right'>".$escaper->escapeHtml($lang['Aggregation']).":</div>\n";
    echo "    <div class='span4'>\n";
    echo "        <select id='x_axis_aggregation' name='x_axis_aggregation'>\n";
    echo "          <option value='date'" . ($x_axis_aggregation === false || $x_axis_aggregation === 'date' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['Date'])."</option>\n";
    echo "        </select>\n";
    echo "    </div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span2 text-right'>".$escaper->escapeHtml($lang['CustomLabel']).":</div>\n";
    echo "    <div class='span4'>\n";
    echo "        <input type='text' name='x_axis_custom_label' value='{$x_axis_custom_label}' />\n";
    echo "    </div>\n";
    echo "</div>\n";

    echo "<div class='row-fluid'>\n";
    echo "    <div class='span2 text-right'>".$escaper->escapeHtml($lang['X-AxisValue']).":</div>\n";
    echo "    <div class='span4'>\n";
    echo "        <select id='x_axis' name='x_axis'>\n";
    echo "          <option value='day'" . ($x_axis === 'day' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['TimeDays'])."</option>\n";
    echo "          <option value='week'" . ($x_axis === 'week' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['TimeWeeks'])."</option>\n";
    echo "          <option value='month'" . ($x_axis === 'month' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['TimeMonths'])."</option>\n";
    echo "          <option value='year'" . ($x_axis === 'year' ? " selected='selected'" : "") . ">".$escaper->escapeHtml($lang['TimeYears'])."</option>\n";
    echo "        </select>\n";
    echo "    </div>\n";
    echo "</div>\n";
}
/**********************************************
 * FUNCTION: DISPLAY SAVE GRAPHICAL SELECTION *
 **********************************************/
function display_save_graphic_selection()
{
    global $escaper, $lang;

    $selection_id = get_param("GET", "selection", "");
    $options = get_graphical_saved_selections($_SESSION['uid']);
    $private = $escaper->escapeHtml($lang['Private']);
    $public = $escaper->escapeHtml($lang['Public']);
    // Delete button
    if(!$selection_id || !$_SESSION['admin']){
        $style = "display: none;";
    }else{
        $style = "";
    }
    echo "
        <div class='row-fluid'>
            <div class='span12'>".$escaper->escapeHtml($lang['SaveSelections'])."</div>
        </div>

        <div class='row-fluid'>
            <div class='span2 text-right'>".$escaper->escapeHtml($lang['SavedSelections']).":</div>
            <div class='span7'>
                <select id='saved_selections' name='saved_selections'>
                    <option value=''>--</option>";
                foreach($options as $option)
                {
                    $selected = ($selection_id == $option['value'])?"selected":"";
                    echo "<option value='".$option['value']."' {$selected}>".$escaper->escapeHtml($option['name'])."</option>";
                }
        echo "   </select>
            </div>
            <div class='span1'>
                <button class='btn' id='delete_saved_selection' style='{$style}'>".$escaper->escapeHtml($lang['Delete'])."</button>
            </div>
        </div>
        <div class='row-fluid'>
            <div class='span2 text-right'>".$escaper->escapeHtml($lang['Type']).":</div>
            <div class='span2'>
                <select name='selection_type' title='". $escaper->escapeHtml($lang['PleaseSelectTypeForSaving']) ."'>
                    <option value=''>--</option>
                    <option value='public'>".$escaper->escapeHtml($lang['Public'])."</option>
                    <option value='private'>".$escaper->escapeHtml($lang['Private'])."</option>
                </select>
            </div>
            <div class='span1 text-right'>".$escaper->escapeHtml($lang['Name']).":</div>
            <div class='span4'>
                <input name='selection_name' type='text' placeholder='".$escaper->escapeHtml($lang['Name'])."' title='".$escaper->escapeHtml($lang['Name'])."' style='max-width: unset;'>
            </div>
            <div class='span2'><button class='btn' id='save_selection'>".$escaper->escapeHtml($lang['Save'])."</button></div>
        </div>\n";
    echo "

        <script>
            function setCookie(cname, cvalue, exdays) {
              const d = new Date();
              d.setTime(d.getTime() + (exdays*24*60*60*1000));
              let expires = 'expires='+ d.toUTCString();
              document.cookie = cname + '=' + cvalue + ';' + expires + ';path=/';
            }
            function deleteCookie(cname) {
              document.cookie = cname + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            }
            function getCookie(cname) {
              let name = cname + '=';
              let decodedCookie = decodeURIComponent(document.cookie);
              let ca = decodedCookie.split(';');
              for(let i = 0; i <ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') {
                  c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                  return c.substring(name.length, c.length);
                }
              }
              return '';
            }
            function delete_saved_selection()
            {
                var id = $('#saved_selections').val();
                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/reports/delete-graphical-selection',
                    data:{
                        id: id,
                    },
                    success: function(res){
                        document.location.href = BASE_URL + '/reports/graphical_risk_analysis.php';
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this)){
                            if(xhr.responseJSON && xhr.responseJSON.status_message) {
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    }
                });
            }
            $(document).ready(function(){
                $('#delete_saved_selection').click(function(e){
                    e.preventDefault();
                    confirm('{$escaper->escapeHtml($lang["AreYouSureYouWantToDeleteSelction"])}', 'delete_saved_selection()');
                });

                $('#save_selection').click(function(){
                    var graphic_form_data = $('#graphical_risk_analysis').serialize();

                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/reports/save-graphical-selections',
                        data: graphic_form_data,
                        success: function(res){
                            $('#saved_selections').append(new Option(res.data.name, res.data.value));
                            $('#saved_selections').val(res.data.value)
                            showAlertsFromArray(res.status_message);
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    
                    return false;
                })
                
                $('#saved_selections').change(function(){
                    var selection = $(this).val();
                    deleteCookie('load_selection');
                    if(selection){
                        document.location.href = BASE_URL + '/reports/graphical_risk_analysis.php?selection=' + selection;
                    } else {
                        document.location.href = BASE_URL + '/reports/graphical_risk_analysis.php';
                    }
                    return true;
                });
                if(!getCookie('load_selection') && $('#saved_selections').val()){
                    setCookie('load_selection', 'loaded', 1);
                    setTimeout(function(){
                        $('#graphical_risk_analysis').submit();
                    }, 10);
                }
            });

        </script>
    ";

}

/*********************************************
 * FUNCTION: DISPLAY GRAPHICAL RISK ANALYSIS *
 *********************************************/
function display_graphical_risk_analysis()
{
    // If the values were posted
    if (isset($_POST['type']) && isset($_POST['x_axis']) && isset($_POST['y_axis']))
    {
        $type = $_POST['type'];
        $x_axis = $_POST['x_axis'];
        $y_axis = $_POST['y_axis'];

        // If we have valid values
        if (valid_graphical_risk_analysis($type, $x_axis, $y_axis))
        {
            // Display the chart
            display_graphical_risk_analysis_chart($type, $x_axis, $y_axis);
        } else {
            echo "invalid";
        }
    }
}

/****************************************************
 *  FUNCTION: DISPLAY GRAPHICAL RISK ANALYSIS CHART *
 ****************************************************/
function display_graphical_risk_analysis_chart($type, $x_axis, $y_axis)
{
    global $escaper;

    // Create the new chart
    $chart = new Highchart(0,0);
    $chart->includeExtraScripts();

    // Tell the chart which div to render to
    $chart->chart->renderTo = "graphical_risk_analysis_chart";

    // Set the timezone to the one configured for SimpleRisk
    $chart->chart->time->useUTC = false;
    $chart->chart->time->timezone = get_setting("default_timezone");

    // Set the chart type
    //$chart->chart->type = $type;

    // If a chart title was posted
    if (isset($_POST['chart_title']))
    {
        // Set the chart title
        $chart_title = $escaper->escapeHtml($_POST['chart_title']);
        $chart->title->text = $chart_title;
    }

    // Switch based on the chart type
    switch ($type)
    {
        case "area":
        case "line":
        case "column":
            display_graphical_risk_analysis_line_chart($chart, $type, $x_axis, $y_axis);
            break;
        default:
            break;
    }

    $chart->legend->enabled = false;
    $chart->credits->enabled = false;

    // Print the chart scripts
    $chart->printScripts();

    // Print the chart
    echo "<div id=\"graphical_risk_analysis_chart\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("graphical_risk_analysis_chart");
    echo "</script>";
}

/**************************************************************
 *  FUNCTION: DISPLAY GRAPHICAL RISK ANALYSIS AREARANGE CHART *
 **************************************************************/
function display_graphical_risk_analysis_line_chart($chart, $type, $x_axis, $y_axis)
{
    global $escaper;

    $chart->chart->zoomType = "x";
    // Set the plot options
    $chart->plotOptions->series->marker->enabled = false;
    $chart->plotOptions->series->marker->lineWidth = "2";
    $chart->plotOptions->series->marker->symbol = "circle";
    $chart->plotOptions->series->marker->states->hover->enabled = true;
    $chart->plotOptions->series->marker->states->hover->fillColor = "white";
    $chart->plotOptions->series->marker->states->hover->lineColor = "black";
    $chart->plotOptions->series->marker->states->hover->lineWidth = "2";

    if($type == "area") {
        $color_alpha = "0.5";
    } else {
        $chart->tooltip = array(
            'crosshairs' => true,
            'shared' => true,
        );
        $color_alpha = "1";
    }


    $risk_status = get_param("POST", "risk_status", "all");
    $y_axis_aggregation = get_param("POST", "y_axis_aggregation", "average");


    // Switch on the x-axis value
    switch($x_axis)
    {
        case "day":
            $x_axis_title = "Time (Days)";
            $timeframe = "day";
            break;
        case "week":
            $x_axis_title = "Time (Weeks)";
            $timeframe = "week";
            break;
        case "month":
            $x_axis_title = "Time (Months)";
            $timeframe ="month";
            break;
        case "year":
            $x_axis_title = "Time (Years)";
            $timeframe = "year";
            break;
        default:
            $timeframe = null;
            $x_axis_title = null;
            break;
    }

    // If a chart x_axis_custom_label was posted
    if (isset($_POST['x_axis_custom_label']))
    {
        // Set the chart x_axis_custom_label
        $x_axis_custom_label = $escaper->escapeHtml($_POST['x_axis_custom_label']);
        $chart->xAxis->title->text = $x_axis_custom_label;
    }

    $chart->xAxis->type = "datetime";
    $chart->xAxis->dateTimeLabelFormats = array(
        "day" => "%Y-%m-%d",
        "month" => "%b %Y",
    );

    // Switch on the y-axis values
    switch ($risk_status)
    {
        case "open":
            $y_axis_title = "Open Risk Count";
            $opened_risks = get_risks_array_for_graphical($timeframe, "open");
            $closed_risks = [];
            $date_arr = isset($opened_risks['date']) ? $opened_risks['date'] : [];
            break;
        case "closed":
            $y_axis_title = "Closed Risk Count";
            $opened_risks = [];
            $closed_risks = get_risks_array_for_graphical($timeframe, "closed");
            $date_arr = isset($closed_risks['date']) ? $closed_risks['date'] : [];
            break;
        case "all":
            $y_axis_title = "Total Risk Count";
            $opened_risks = get_risks_array_for_graphical($timeframe, "open");
            $closed_risks = get_risks_array_for_graphical($timeframe, "closed");
            $date_arr = isset($opened_risks['date']) ? $opened_risks['date'] : [];
            break;
        default:
            break;
    }

    // If the opened risks array is empty
    if (!count($date_arr))
    {
        $opened_risk_data[] = array("No Data Available", 0);
    }
    // Otherwise
    else
    {
        $opened_data = 0;
        $closed_data = 0;
        foreach ($date_arr as $key => $row) {
            switch($y_axis_aggregation){
                case "average":
                    $open_sum = isset($opened_risks['sum'][$key])?$opened_risks['sum'][$key]:0;
                    $open_count = isset($opened_risks['count'][$key])?$opened_risks['count'][$key]:1;
                    $opened_data = round($open_sum / $open_count);
                    $close_sum = isset($closed_risks['sum'][$key])?$closed_risks['sum'][$key]:0;
                    $close_count = isset($closed_risks['count'][$key])?$closed_risks['count'][$key]:1;
                    $closed_data = round($close_sum / $close_count);
                    break;
                case "count":
                    $open_value = isset($opened_risks['count'][$key])?$opened_risks['count'][$key]:0;
                    $opened_data = $open_value;
                    $close_value = isset($closed_risks['count'][$key])?$closed_risks['count'][$key]:0;
                    $closed_data = $close_value;
                    break;
                case "total":
                    $open_value = isset($opened_risks['count'][$key])?$opened_risks['count'][$key]:0;
                    $opened_data += $open_value;
                    $close_value = isset($closed_risks['count'][$key])?$closed_risks['count'][$key]:0;
                    $closed_data += $close_value;
                    break;
                case "max":
                    $open_value = isset($opened_risks['max'][$key])?$opened_risks['max'][$key]:0;
                    $opened_data = floatval($open_value);
                    $close_value = isset($closed_risks['max'][$key])?$closed_risks['max'][$key]:0;
                    $closed_data = floatval($close_value);
                    break;
                case "min":
                    $open_value = isset($opened_risks['min'][$key])?$opened_risks['min'][$key]:0;
                    $opened_data = floatval($open_value);
                    $close_value = isset($closed_risks['min'][$key])?$closed_risks['min'][$key]:0;
                    $closed_data = floatval($close_value);
                    break;
            }
            if($x_axis == "week") {
                $date_arr = explode("-", $row);
                $year = $date_arr[0];
                $week = $date_arr[1];
                $date = strtotime(date("Y-m-d", strtotime($year.'W'.sprintf("%02d",$week))));
            } else {
                $date = strtotime($row);
            }

            // Create the data arrays
            $opened_risk_data[] = array($date * 1000, $opened_data);
            $closed_risk_data[] = array($date * 1000, $closed_data);
            $trend_data[] = array($date * 1000, $opened_data - $closed_data);

        }
        $open_risks_series = array(
            'type' => $type,
            'name' => "Opened Risks",
            'color' => "rgba(255, 0, 0, {$color_alpha})",
            //'lineWidth' => "1",
            'data' => empty($opened_risk_data) ? [] : $opened_risk_data
        );
        $closed_risks_series = array(
            'type' => $type,
            'name' => "Closed Risks",
            'color' => "rgba(0, 0, 255, {$color_alpha})",
            //'lineWidth' => "2",
            'data' => empty($closed_risk_data) ? [] : $closed_risk_data
        );
        $trend_risks_series = array(
            'type' => $type,
            'name' => "Trend",
            'color' => "rgba(0, 0, 0, {$color_alpha})",
            //'lineWidth' => "2",
            'data' => empty($trend_data) ? [] : $trend_data
        );


        // Switch on the y-axis values
        switch ($risk_status)
        {
            case "open":
                $chart->series = array($open_risks_series);
                break;
            case "closed":
                $chart->series = array($closed_risks_series);
                break;
            case "all":
                if($y_axis_aggregation == "count" || $y_axis_aggregation == "total")
                    $chart->series = array($open_risks_series, $closed_risks_series, $trend_risks_series);
                else 
                    $chart->series = array($open_risks_series, $closed_risks_series);
                break;
            default:
                break;
        }

    }

    // If a chart y_axis_custom_label was posted
    if (isset($_POST['y_axis_custom_label']))
    {
        // Set the chart y_axis_custom_label
        $y_axis_custom_label = $escaper->escapeHtml($_POST['y_axis_custom_label']);
        $chart->yAxis->title->text = $y_axis_custom_label;
    }

    //$chart->yAxis->min = 0;
    //$chart->yAxis->gridLineWidth = 0;
}
/**************************************************************
 *  FUNCTION: DISPLAY GRAPHICAL RISK ANALYSIS AREARANGE CHART *
 **************************************************************/
function display_graphical_risk_analysis_area_chart($chart, $type, $x_axis, $y_axis)
{
    global $escaper;

    $chart->chart->zoomType = "x";
    // Set the plot options
    $chart->plotOptions->series->marker->enabled = false;
    $chart->plotOptions->series->marker->lineWidth = "2";
    $chart->plotOptions->series->marker->symbol = "circle";
    // $chart->plotOptions->series->marker->states->hover->enabled = true;
    // $chart->plotOptions->series->marker->states->hover->fillColor = "white";
    // $chart->plotOptions->series->marker->states->hover->lineColor = "black";
    // $chart->plotOptions->series->marker->states->hover->lineWidth = "2";

    $chart->tooltip = array(
        'crosshairs' => true,
        'shared' => true,
    );

    $risk_status = get_param("POST", "risk_status", "all");
    $y_axis_aggregation = get_param("POST", "y_axis_aggregation", "average");


    // Switch on the x-axis value
    switch($x_axis)
    {
        case "day":
            $x_axis_title = "Time (Days)";
            $timeframe = "day";
            break;
        case "week":
            $x_axis_title = "Time (Weeks)";
            $timeframe = "week";
            break;
        case "month":
            $x_axis_title = "Time (Months)";
            $timeframe ="month";
            break;
        case "year":
            $x_axis_title = "Time (Years)";
            $timeframe = "year";
            break;
        default:
            $timeframe = null;
            $x_axis_title = null;
            break;
    }

    // If a chart x_axis_custom_label was posted
    if (isset($_POST['x_axis_custom_label']))
    {
        // Set the chart x_axis_custom_label
        $x_axis_custom_label = $escaper->escapeHtml($_POST['x_axis_custom_label']);
        $chart->xAxis->title->text = $x_axis_custom_label;
    }

    $chart->xAxis->type = "datetime";
    $chart->xAxis->dateTimeLabelFormats = array(
        "day" => "%Y-%m-%d",
        "month" => "%b %Y",
    );

    // Switch on the y-axis values
    switch ($risk_status)
    {
        case "open":
            $y_axis_title = "Open Risk Count";
            $opened_risks = get_risks_array_for_graphical($timeframe, "open");
            $closed_risks = [];
            $date_arr = isset($opened_risks['date']) ? $opened_risks['date'] : [];
            break;
        case "closed":
            $y_axis_title = "Closed Risk Count";
            $opened_risks = [];
            $closed_risks = get_risks_array_for_graphical($timeframe, "closed");
            $date_arr = isset($closed_risks['date']) ? $closed_risks['date'] : [];
            break;
        case "all":
            $y_axis_title = "Total Risk Count";
            $opened_risks = get_risks_array_for_graphical($timeframe, "open");
            $closed_risks = get_risks_array_for_graphical($timeframe, "closed");
            $date_arr = isset($opened_risks['date']) ? $opened_risks['date'] : [];
            break;
        default:
            break;
    }

    // If the opened risks array is empty
    if (!count($date_arr))
    {
        $opened_risk_data[] = array("No Data Available", 0);
    }
    // Otherwise
    else
    {
        $opened_data = 0;
        $closed_data = 0;
        foreach ($date_arr as $key => $row) {
            switch($y_axis_aggregation){
                case "average":
                    $open_sum = isset($opened_risks['sum'][$key])?$opened_risks['sum'][$key]:0;
                    $open_count = isset($opened_risks['count'][$key])?$opened_risks['count'][$key]:1;
                    $opened_data = round($open_sum / $open_count);
                    $close_sum = isset($closed_risks['sum'][$key])?$closed_risks['sum'][$key]:0;
                    $close_count = isset($closed_risks['count'][$key])?$closed_risks['count'][$key]:1;
                    $closed_data = round($close_sum / $close_count);
                    break;
                case "count":
                    $open_value = isset($opened_risks['count'][$key])?$opened_risks['count'][$key]:0;
                    $opened_data = $open_value;
                    $close_value = isset($closed_risks['count'][$key])?$closed_risks['count'][$key]:0;
                    $closed_data = $close_value;
                    break;
                case "total":
                    $open_value = isset($opened_risks['count'][$key])?$opened_risks['count'][$key]:0;
                    $opened_data += $open_value;
                    $close_value = isset($closed_risks['count'][$key])?$closed_risks['count'][$key]:0;
                    $closed_data += $close_value;
                    break;
                case "max":
                    $open_value = isset($opened_risks['max'][$key])?$opened_risks['max'][$key]:0;
                    $opened_data = floatval($open_value);
                    $close_value = isset($closed_risks['max'][$key])?$closed_risks['max'][$key]:0;
                    $closed_data = floatval($close_value);
                    break;
                case "min":
                    $open_value = isset($opened_risks['min'][$key])?$opened_risks['min'][$key]:0;
                    $opened_data = floatval($open_value);
                    $close_value = isset($closed_risks['min'][$key])?$closed_risks['min'][$key]:0;
                    $closed_data = floatval($close_value);
                    break;
            }
            if($x_axis == "week") {
                $date_arr = explode("-", $row);
                $year = $date_arr[0];
                $week = $date_arr[1];
                $date = strtotime(date("Y-m-d", strtotime($year.'W'.sprintf("%02d",$week))));
            } else {
                $date = strtotime($row);
            }

            // Create the data arrays
            $opened_risk_data[] = array($date * 1000, $opened_data);
            $closed_risk_data[] = array($date * 1000, $closed_data);
            $trend_data[] = array($date * 1000, $opened_data - $closed_data);

        }
        $open_risks_series = array(
            'type' => $type,
            'name' => "Opened Risks",
            'color' => "rgba(255, 0, 0, 0.5)",
            'lineWidth' => "2",
            'data' => empty($opened_risk_data) ? [] : $opened_risk_data
        );
        $closed_risks_series = array(
            'type' => $type,
            'name' => "Closed Risks",
            'color' => "rgba(0, 0, 255, 0.5)",
            'lineWidth' => "2",
            'data' => empty($closed_risk_data) ? [] : $closed_risk_data
        );
        $trend_risks_series = array(
            'type' => $type,
            'name' => "Trend",
            'color' => "#000000",
            'lineWidth' => "2",
            'data' => empty($trend_data) ? [] : $trend_data
        );


        // Switch on the y-axis values
        switch ($risk_status)
        {
            case "open":
                $chart->series = array($open_risks_series);
                break;
            case "closed":
                $chart->series = array($closed_risks_series);
                break;
            case "all":
                $chart->series = array($open_risks_series, $closed_risks_series);
                break;
            default:
                break;
        }

    }

    // If a chart y_axis_custom_label was posted
    if (isset($_POST['y_axis_custom_label']))
    {
        // Set the chart y_axis_custom_label
        $y_axis_custom_label = $escaper->escapeHtml($_POST['y_axis_custom_label']);
        $chart->yAxis->title->text = $y_axis_custom_label;
    }

    //$chart->yAxis->min = 0;
    //$chart->yAxis->gridLineWidth = 0;
}

/*******************************************
 * FUNCTION: VALID GRAPHICAL RISK ANALYSIS *
 *******************************************/
function valid_graphical_risk_analysis($type, $x_axis, $y_axis)
{
        // If we have valid values
        if (valid_graphical_risk_analysis_type($type) &&
            valid_graphical_risk_analysis_x_axis($x_axis) &&
            valid_graphical_risk_analysis_y_axis($y_axis))
        {
            return true;
        }
        else return false;
}

/*************************************************
 * FUNCTION: VALID GRAPHICAL RISK ANALYSIS TYPE  *
 * @param $type                                  *
 * @return bool                                  *
 *************************************************/
function valid_graphical_risk_analysis_type($type): bool
{
    // Switch on the type
    switch ($type)
    {
        case "area":
        case "line":
        case "column":
            return true;
            break;
        default:
            return false;
    }
}

/**************************************************
 * FUNCTION: VALID GRAPHICAL RISK ANALYSIS X AXIS *
 * @param $x_axis                                 *
 * @return bool                                   *
 **************************************************/
function valid_graphical_risk_analysis_x_axis($x_axis): bool
{
    // Switch on the x_axis
    switch ($x_axis)
    {
        case "day":
        case "week":
        case "month":
        case "quarter":
        case "year":
            return true;
            break;
        default:
            return false;
    }
}

/**************************************************
 * FUNCTION: VALID GRAPHICAL RISK ANALYSIS Y AXIS *
 * @param $y_axis                                 *
 * @return bool                                   *
 **************************************************/
function valid_graphical_risk_analysis_y_axis($y_axis): bool
{
    // Switch on the y_axis
    switch ($y_axis)
    {
        case "inherent_risk":
        case "residual_risk":
            return true;
            break;
        default:
            return false;
    }
}
/***************************************
 * FUNCTION: RISK ARRAYS FOR GRAPHICAL *
 ***************************************/
function get_risks_array_for_graphical($timeframe, $risk_status)
{
    global $lang;
    global $escaper;

    if (!team_separation_extra()){
        $separation_query = "";
    } else {
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $separation_query = " AND ". get_user_teams_query("a");
    }

    if (team_separation_extra()) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $team_separation_enabled = true;
    } else
        $team_separation_enabled = false;

    $y_axis = get_param("POST", "y_axis", "inherent_risk");
    $default_severity = array('very_high' => 1, 'high' => 1, 'medium' => 1, 'low' => 1, 'insignificant' => 1);
    $risk_severity = get_param("POST", "risk_severity", $default_severity);

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $risk_levels = get_risk_levels();
    $low = $risk_levels[0]["value"];
    $medium = $risk_levels[1]["value"];
    $high = $risk_levels[2]["value"];
    $very_high = $risk_levels[3]["value"];


    $where = "";
    if($risk_status == "closed") {
        $where .= " AND a.status = 'Closed'";
        $order = "closure_date";
    } else {
        $where .= " AND a.status != 'Closed'";
        $order = "submission_date";
    }
    $where .= $separation_query;
        // Query the database
        $sql = "
            SELECT a.id, a.submission_date, b.calculated_risk,
            ROUND((b.calculated_risk - (b.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) AS residual_risk,
            IFNULL(c.closure_date, NOW()) closure_date
            FROM `risks` a
            LEFT JOIN `risk_scoring` b ON a.id = b.id
            LEFT JOIN `mitigations` p ON a.id = p.risk_id
            LEFT JOIN `mitigation_to_controls` mtc ON p.id = mtc.mitigation_id 
            LEFT JOIN `framework_controls` fc ON mtc.control_id=fc.id AND fc.deleted=0
            LEFT JOIN `closures` c ON a.close_id=c.id
            LEFT JOIN `risk_to_team` rtt on a.id=rtt.risk_id
            LEFT JOIN `risk_to_additional_stakeholder` rtas on a.id=rtas.risk_id
            WHERE 1 {$where}
            GROUP BY a.id
            ORDER BY {$order};";
    $stmt = $db->prepare($sql);
    $stmt->execute();


    // Store the list in the array
    $array = $stmt->fetchAll();
    // Close the database connection
    db_close($db);

    // Set the defaults
    $counter = -1;
    $current_date = "";
    $risks = array();
    $min_score = 0;
    $max_score = 0;

    // For each row
    foreach ($array as $key=>$row)
    {
        if($risk_status == "closed") $compare_date = $row['closure_date'];
        else if($risk_status == "open") $compare_date = $row['submission_date'];
        // If the timeframe is by day
        if ($timeframe === "day")
        {
            // Set the date to the day
            $date = date('Y-m-d', strtotime($compare_date));
        }
        // If the timeframe is by week
        else if ($timeframe === "week")
        {
            // Set the date to the month
            $date = date('o-W', strtotime($compare_date));
        }
        // If the timeframe is by month
        else if ($timeframe === "month")
        {
            // Set the date to the month
            $date = date('Y-m', strtotime($compare_date));
        }
        // If the timeframe is by year
        else if ($timeframe === "year")
        {
            // Set the date to the year
            $date = date('Y', strtotime($compare_date));
        }
        $risk_score = $y_axis == "inherent_risk"?$row['calculated_risk']:$row['residual_risk'];

        $filtered = false;
        if(isset($risk_severity["very_high"]) && $risk_score >= $very_high){
            $filtered = true;
        }
        if(isset($risk_severity["high"]) && $risk_score < $very_high && $risk_score >= $high){
            $filtered = true;
        }
        if(isset($risk_severity["medium"]) && $risk_score < $high && $risk_score >= $medium){
            $filtered = true;
        }
        if(isset($risk_severity["low"]) && $risk_score < $medium && $risk_score >= $low){
            $filtered = true;
        }
        if(isset($risk_severity["insignificant"]) && $risk_score < $low){
            $filtered = true;
        }
        if($filtered == false) continue;

        // If the date is different from the current date
        if ($current_date != $date)
        {
            // Increment the counter
            $counter++;

            // Set the current date
            $current_date = $date;

            // Add the date
            $risks['date'][$counter] = $current_date;

            // Set the open count to 1
            $risks['count'][$counter] = 1;

            // If this is the first entry
            if ($counter == 0)
            {
                // Set the open total to 1
                $risks['total'][$counter] = 1;
            } else {
                $risks['total'][$counter] = $risks['total'][$counter-1] + 1;
            }

            $min_score = $risk_score;
            $max_score = $risk_score;
            $risks['sum'][$counter] = $risk_score;
        }
        // Otherwise, if the date is the same
        else
        {
            // Increment the open count
            $risks['count'][$counter]++;

            // Update the open total
            $risks['total'][$counter]++;

            if($min_score >= $risk_score) $min_score = $risk_score;
            if($max_score <= $risk_score) $max_score = $risk_score;
            $risks['sum'][$counter] += $risk_score;

        }
        $risks['min'][$counter] = $min_score;
        $risks['max'][$counter] = $max_score;
    }

    // Return the open date array
    //return array($risks['date'], $risks['count']);
    return $risks;

}

?>
