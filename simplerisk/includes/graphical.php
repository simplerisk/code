<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));

// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/*******************************************
 * FUNCTION: DISPLAY GRAPHIC TYPE DROPDOWN *
 *******************************************/
function display_graphic_type_dropdown($settings=[]) {

    global $escaper, $lang;

    $type = $escaper->escapeHtml(get_param("POST", "type", $settings?$settings["type"]:""));

    // Set the chart title
    $chart_title = $escaper->escapeHtml(get_param("POST", "chart_title", $settings?$settings["chart_title"]:""));

    echo "
        <h4><u>Visualization</u></h4>
        <div class='row'>
            <div class='col-6'>
                <label>Type :</label>
                <select id='type' name='type' class='form-select'>
                    <option value='area'" . (!$type || $type === 'area' ? " selected='selected'" : "") . ">Area Range</option>
                    <option value='line'" . ($type === 'line' ? " selected='selected'" : "") . ">Line</option>
                    <option value='column'" . ($type === 'column' ? " selected='selected'" : "") . ">Bar</option>
                </select>
            </div>
            <div class='col-6'>
                <label>Title :</label>
                <input type='text' name='chart_title' value='{$chart_title}' class='form-control'/>
            </div>
        </div>
    ";

}


/****************************
 * FUNCTION: DISPLAY Y AXIS *
 ****************************/
function display_y_axis($settings=[]) {

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

    echo "
        <h4 class='mt-3'><u>Y-Axis</u></h4>
        <div class='row form-group'>
            <div class='col-6'>
                <label>Aggregation :</label>
                <select id='y_axis_aggregation' name='y_axis_aggregation' class='form-select'>
                    <option value='average'" . (!$y_axis_aggregation || $y_axis_aggregation === 'average' ? " selected='selected'" : "") . ">Average</option>
                    <option value='count'" . ($y_axis_aggregation === 'count' ? " selected='selected'" : "") . ">Count</option>
                    <option value='total'" . ($y_axis_aggregation === 'total' ? " selected='selected'" : "") . ">Count Total</option>
                    <option value='max'" . ($y_axis_aggregation === 'max' ? " selected='selected'" : "") . ">Max</option>
                    <option value='min'" . ($y_axis_aggregation === 'min' ? " selected='selected'" : "") . ">Min</option>
                </select>
            </div>
            <div class='col-6'>
                <label>Custom Label :</label>
                <input type='text' name='y_axis_custom_label' value='{$y_axis_custom_label}' class='form-control'/>
            </div>
        </div>
        <div class='row'>
            <div class='col-6'>
                <label>Y-Axis Value :</label>
                <select id='y_axis' name='y_axis' class='form-select'>
                    <option value='inherent_risk'" . ($y_axis === false || $y_axis === 'inherent_risk' ? " selected='selected'" : "") . ">Inherent Risk</option>
                    <option value='residual_risk'" . ($y_axis === false || $y_axis === 'residual_risk' ? " selected='selected'" : "") . ">Residual Risk</option>
                </select>
            </div>
        </div>
        <div class='mt-3'>
            <h4><u>Status</u></h4>
            <div class='form-check'>
                <input type='radio' name='risk_status' id='risk_status1' class='form-check-input me-2' value='all'" . (!$risk_status || $risk_status === 'all' ? " checked='checked'" : "") . "><label for='risk_status1'>All</label>
            </div>
            <div class='form-check'>
                <input type='radio' name='risk_status' id='risk_status2' class='form-check-input me-2' value='open'" . ($risk_status === 'open' ? " checked='checked'" : "") . "><label for='risk_status2'>Open</label>
            </div>
            <div class='form-check'>
                <input type='radio' name='risk_status' id='risk_status3' class='form-check-input me-2' value='closed'" . ($risk_status === 'closed' ? " checked='checked'" : "") . "><label for='risk_status3'>Closed</label>
            </div>
        </div>
        <div class='mt-3'>
            <h4><u>Severity</u></h4>
            <div class='form-check'>
                <input type='checkbox' id='very_high' class='form-check-input me-2' name='risk_severity[very_high]'" . (isset($risk_severity['very_high']) ? " checked='checked'" : "") . "><label for='very_high'>Very High</label>
            </div>
            <div class='form-check'>
                <input type='checkbox' id='high' class='form-check-input me-2' name='risk_severity[high]'" . (isset($risk_severity['high']) ? " checked='checked'" : "") . "><label for='high'>High</label>
            </div>
            <div class='form-check'>
                <input type='checkbox' id='medium' class='form-check-input me-2' name='risk_severity[medium]'" . (isset($risk_severity['medium']) ? " checked='checked'" : "") . "><label for='medium'>Medium</label>
            </div>
            <div class='form-check'>
                <input type='checkbox' id='low' class='form-check-input me-2' name='risk_severity[low]'" . (isset($risk_severity['low']) ? " checked='checked'" : "") . "><label for='low'>Low</label>
            </div>
            <div class='form-check'>
                <input type='checkbox' id='insignificant' class='form-check-input me-2' name='risk_severity[insignificant]'" . (isset($risk_severity['insignificant']) ? " checked='checked'" : "") . "><label for='insignificant'>Insignificant</label>
            </div>
        </div>
    ";

}

/****************************
 * FUNCTION: DISPLAY X AXIS *
 ****************************/
function display_x_axis($settings=[]) {

    global $escaper, $lang;

    // Set the x_axis_aggregation
    $x_axis_aggregation = $escaper->escapeHtml(get_param("POST", "x_axis_aggregation", $settings?$settings["x_axis_aggregation"]:""));

    // set the X axis
    $x_axis = $escaper->escapeHtml(get_param("POST", "x_axis", $settings?$settings["x_axis"]:""));

    // Set the x_axis_custom_label
    $x_axis_custom_label = $escaper->escapeHtml(get_param("POST", "x_axis_custom_label", $settings?$settings["x_axis_custom_label"]:""));

    echo "
        <h4 class='mt-3'><u>X-Axis</u></h4>
        <div class='row form-group'>
            <div class='col-6'>
                <label>Aggregation :</label>
                <select id='x_axis_aggregation' name='x_axis_aggregation' class='form-select'>
                    <option value='date'" . ($x_axis_aggregation === false || $x_axis_aggregation === 'date' ? " selected='selected'" : "") . ">Date</option>
                </select>
            </div>
            <div class='col-6'>
                <label>Custom Label :</label>
                <input type='text' name='x_axis_custom_label' value='{$x_axis_custom_label}' class='form-control'/>
            </div>
        </div>
        <div class='row'>
            <div class='col-6'>
                <label>X-Axis Value :</label>
                <select id='x_axis' name='x_axis' class='form-select'>
                    <option value='day'" . ($x_axis === 'day' ? " selected='selected'" : "") . ">Time (Days)</option>
                    <option value='week'" . ($x_axis === 'week' ? " selected='selected'" : "") . ">Time (Weeks)</option>
                    <option value='month'" . ($x_axis === 'month' ? " selected='selected'" : "") . ">Time (Months)</option>
                    <option value='year'" . ($x_axis === 'year' ? " selected='selected'" : "") . ">Time (Years)</option>
                </select>
            </div>
        </div>
    ";

}
/**********************************************
 * FUNCTION: DISPLAY SAVE GRAPHICAL SELECTION *
 **********************************************/
function display_save_graphic_selection() {

    global $escaper, $lang;

    $selection_id = get_param("GET", "selection", "");
    $options = get_graphical_saved_selections($_SESSION['uid']);
    $private = $escaper->escapeHtml($lang['Private']);
    $public = $escaper->escapeHtml($lang['Public']);

    // Delete button
    if (!$selection_id || !$_SESSION['admin']) {
        $style = "display: none;";
    } else {
        $style = "";
    }

    echo "
        <h4 class='mt-3'><u>{$escaper->escapeHtml($lang['SaveSelections'])}</u></h4>
        <div class='row align-items-end form-group'>
            <div class='col-4'>
                <label>{$escaper->escapeHtml($lang['SavedSelections'])} :</label>
                <select id='saved_selections' name='saved_selections' class='form-select'>
                    <option value=''>--</option>
    ";
    foreach ($options as $option) {
        $selected = ($selection_id == $option['value']) ? "selected" : "";
        echo "
                    <option value='{$option['value']}' {$selected}>{$escaper->escapeHtml($option['name'])}</option>
        ";
    }
    echo "
                </select>
            </div>
            <div class='col-2'>
                <button class='btn btn-primary' id='delete_saved_selection' style='{$style}'>{$escaper->escapeHtml($lang['Delete'])}</button>
            </div>
        </div>
        <div class='row align-items-end form-group'>
            <div class='col-4'>
                <label>{$escaper->escapeHtml($lang['Type'])}<span class='required'>*</span> :</label>
                <select name='selection_type' title='{$escaper->escapeHtml($lang['Type'])}' class='form-select' required>
                    <option value=''>--</option>
                    <option value='public'>{$escaper->escapeHtml($lang['Public'])}</option>
                    <option value='private'>{$escaper->escapeHtml($lang['Private'])}</option>
                </select>
            </div>
            <div class='col-4'>
                <label>{$escaper->escapeHtml($lang['Name'])}<span class='required'>*</span> :</label>
                <input name='selection_name' type='text' placeholder='{$escaper->escapeHtml($lang['Name'])}' title='{$escaper->escapeHtml($lang['Name'])}' style='max-width: unset;' class='form-control' required>
            </div>
            <div class='col-4'>
                <button class='btn btn-submit' id='save_selection'>{$escaper->escapeHtml($lang['Save'])}</button>
            </div>
        </div>

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
            function delete_saved_selection() {
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
                    confirm('{$escaper->escapeHtml($lang["AreYouSureYouWantToDeleteSelction"])}', delete_saved_selection);
                });
                $('#save_selection').click(function(){

                    // Check if the required fields are empty or trimmed empty
                    if (!checkAndSetValidation('#graphical_risk_analysis')) {
                        return false;
                    }

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
function display_graphical_risk_analysis() {

    // If the values were posted
    if (isset($_POST['type']) && isset($_POST['x_axis']) && isset($_POST['y_axis'])) {

        $type = $_POST['type'];
        $x_axis = $_POST['x_axis'];
        $y_axis = $_POST['y_axis'];

        // If we have valid values
        if (valid_graphical_risk_analysis($type, $x_axis, $y_axis)) {
            // Display the chart
            display_graphical_risk_analysis_chart($type, $x_axis, $y_axis);
        } else {
            echo "
                <strong>invalid</strong>
            ";
        }
    }
}

/****************************************************
 *  FUNCTION: DISPLAY GRAPHICAL RISK ANALYSIS CHART *
 ****************************************************/
function display_graphical_risk_analysis_chart() {

    global $lang, $escaper;

    // Get the values that were POSTed
    $type = isset($_POST['type']) ? $_POST['type'] : "area";
    $title = isset($_POST['chart_title']) ? str_replace("'", "\'", $_POST['chart_title']) : "";
    $x_axis_title = isset($_POST['x_axis_custom_label']) ? str_replace("'", "\'", $_POST['x_axis_custom_label']) : "";
    $y_axis_title = isset($_POST['y_axis_custom_label']) ? str_replace("'", "\'", $_POST['y_axis_custom_label']) : "";

    // Get graphical risk analysis data
    $results = get_graphical_risk_analysis_data();
    $labels = isset($results['labels']) ? $results['labels'] : [];
    $datasets = isset($results['datasets']) ? $results['datasets'] : [];

    // Set the element id for the chart
    $element_id = "graphical_risk_analysis_chart";

    // Switch based on the chart type
    switch ($type)
    {
        case "area":
        case "line":
            create_chartjs_line_code($title, $element_id, $labels, $datasets, "", $x_axis_title, $y_axis_title);
            break;
        case "column":
            create_chartjs_bar_code($title, $element_id, $labels, $datasets, $x_axis_title, $y_axis_title);
            break;
        default:
            break;
    }
}

/***********************************************
 *  FUNCTION: GET GRAPHICAL RISK ANALYSIS DATA *
 ***********************************************/
function get_graphical_risk_analysis_data() {

    // Create an empty array for the datasets
    $datasets = [];

    // Get the parameters that were POSTed
    $risk_status = get_param("POST", "risk_status", "all");
    $type = get_param("POST", "type", "area");
    $x_axis = isset($_POST['x_axis']) ? $_POST['x_axis'] : "";

    // Set the timeframe based on the x_axis value provided
    switch($x_axis) {
        case "day":
            $timeframe = "day";
            break;
        case "week":
            $timeframe = "week";
            break;
        case "month":
            $timeframe ="month";
            break;
        case "year":
            $timeframe = "year";
            break;
        default:
            $timeframe = null;
            break;
    }

    // If the type is area
    if ($type == "area") {
        // Set the fill to true
        $fill = "true";
    // Otherwise do not fill
    } else {
        $fill = "false";
    }

    // Switch on the y-axis values
    switch ($risk_status) {
        case "open":
            // Get the opened risks dataset
            $opened_risks = get_risks_array_for_graphical($timeframe, "open");
            $label = $opened_risks['label'];
            $dates = $opened_risks['dates'];
            $data = $opened_risks['data'];

            // Add it to the array of datasets
            $datasets[] = [
                "dates" => $dates,
                "label" => $label,
                "data" => $data,
                "fill" => $fill
            ];
            break;
        case "closed":
            // Get the closed risks dataset
            $closed_risks = get_risks_array_for_graphical($timeframe, "closed");
            $label = $closed_risks['label'];
            $dates = $closed_risks['dates'];
            $data = $closed_risks['data'];

            // Add it to the array of datasets
            $datasets[] = [
                "label" => $label,
                "data" => $data,
                "fill" => $fill
            ];
            //$date_arr = isset($closed_risks['date']) ? $closed_risks['date'] : [];
            break;
        case "all":
            // Get the opened risks dataset
            $opened_risks = get_risks_array_for_graphical($timeframe, "open");
            $label = $opened_risks['label'];
            $data = $opened_risks['data'];
            $opened_dates = $opened_risks['dates'];

            // Add it to the array of datasets
            $datasets[] = [
                "label" => $label,
                "data" => $data,
                "fill" => $fill
            ];

            // Get the closed risks dataset
            $closed_risks = get_risks_array_for_graphical($timeframe, "closed");
            $label = $closed_risks['label'];
            $data = $closed_risks['data'];
            $closed_dates = $closed_risks['dates'];

            // Iterate through the dates
            foreach ($opened_dates as $opened_date) {
                // If the label is not in the closed label array
                if (!in_array($opened_date, $closed_dates)) {
                    // Append a 0 to the front of the closed risk data
                    array_unshift($data, "0");
                }
            }

            // Add it to the array of datasets
            $datasets[] = [
                "label" => $label,
                "data" => $data,
                "fill" => $fill
            ];
            break;
        default:
            break;
    }

    // Return the array of datasets
    return [
        "labels" => $opened_dates,
        "datasets" => $datasets
    ];
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
    global $lang, $escaper;

    // Get the values POSTed from the Graphical Risk Analysis report
    $y_axis = get_param("POST", "y_axis", "inherent_risk");
    $y_axis_aggregation = get_param("POST", "y_axis_aggregation", "average");
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

    // initializing data for a returned dataset
    $dataset_label = "";
    $labels = [];
    $data = [];

    // Get the scoring field to use based on the y_axis value
    switch ($y_axis)
    {
        case "inherent_risk":
            $scoring_field = "calculated_risk";
            break;
        case "residual_risk":
            $scoring_field = "residual_risk";
            break;
        default:
            $scoring_field = "calculated_risk";
    }

    // Set the custom WHERE value and datefield to use
    switch ($risk_status)
    {
        case "open":
            $dataset_label = str_replace("'", "\'", $lang['OpenRisks']);
            $datefield = "submission_date";
            $where_query = "WHERE a.status != 'Closed'";
            break;
        case "closed":
            $dataset_label = str_replace("'", "\'", $lang['ClosedRisks']);
            $datefield = "closure_date";
            $where_query = "WHERE a.status = 'Closed'";
            break;
        default:
            $dataset_label = "";
            $datefield = "submission_date";
            $where_query = "WHERE 1";
    }

    // Create the order and group queries
    $order_query = "ORDER BY {$datefield}";
    $group_query = "GROUP BY a.id";

    // Set the date format based on the selected timeframe
    switch ($timeframe)
    {
        case "day":
            $date_format = get_default_date_format();
            break;
        case "week":
            $date_format = 'o-W';
            break;
        case "month":
            $date_format = 'Y-m';
            break;
        case "year":
            $date_format = 'Y';
            break;
        default:
    }

    // If the Team Separation Extra is enabled
    if (team_separation_extra())
    {
        // Add the separation query to the where query
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $separation_query = " AND ". get_user_teams_query("a");
        $where_query .= $separation_query;
    }

    // Create the SQL query
    $sql = "
        SELECT
            a.id,
            a.submission_date,
            b.calculated_risk,
            ROUND((b.calculated_risk - (b.calculated_risk * IF(IFNULL(p.mitigation_percent,0) > 0, p.mitigation_percent, IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) AS residual_risk,
            IFNULL(c.closure_date, NOW()) closure_date
        FROM `risks` a
            LEFT JOIN `risk_scoring` b ON a.id = b.id
            LEFT JOIN `mitigations` p ON a.id = p.risk_id
            LEFT JOIN `mitigation_to_controls` mtc ON p.id = mtc.mitigation_id 
            LEFT JOIN `framework_controls` fc ON mtc.control_id=fc.id AND fc.deleted=0
            LEFT JOIN `closures` c ON a.close_id=c.id
            LEFT JOIN `risk_to_team` rtt on a.id=rtt.risk_id
            LEFT JOIN `risk_to_additional_stakeholder` rtas on a.id=rtas.risk_id
        {$where_query}
        {$group_query}
        {$order_query}
    ";

    // Run the database query and get the results
    $stmt = $db->prepare($sql);
    $stmt->execute();

    // NOTE: Returned array contains id, submission_date, calculated_risk, residual_risk and closure_date values
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // If the array is not empty
    if (!empty($array))
    {
        // Set the initial values
        $current_time = time();
        $dates = [];
        $count = [];
        $sum = [];
        $min = [];
        $max = [];
        $average = [];
        $total = [];
        $selected_date = "";
        $index = -1;
        $labels = [];
        $data = [];

        // Get the appropriate start date based on the selected timeframe
        switch ($timeframe)
        {
            case "day":
                $date = strtotime($array[0][$datefield]);
                break;
            case "week":
                $date = strtotime($array[0][$datefield]);
                break;
            case "month":
                // Use the first day of the month
                $date = strtotime(date('Y-m-01', strtotime($array[0][$datefield])));
                break;
            case "year":
                // Use the first day of the year
                $date = strtotime(date('Y-01-01', strtotime($array[0][$datefield])));
                break;
        }

        // Iterate through the array
        foreach ($array as $key=>$row)
        {
            // Get the date in the array
            $array_date = date($date_format, strtotime($row[$datefield]));

            // Get the score and determine if this risk should be filtered or not
            $risk_score = $array[$key][$scoring_field];
            $filtered = false;
            if(!isset($risk_severity["very_high"]) && $risk_score >= $very_high){
                $filtered = true;
            }
            if(!isset($risk_severity["high"]) && $risk_score < $very_high && $risk_score >= $high){
                $filtered = true;
            }
            if(!isset($risk_severity["medium"]) && $risk_score < $high && $risk_score >= $medium){
                $filtered = true;
            }
            if(!isset($risk_severity["low"]) && $risk_score < $medium && $risk_score >= $low){
                $filtered = true;
            }
            if(!isset($risk_severity["insignificant"]) && $risk_score < $low){
                $filtered = true;
            }

            // If this is a date we haven't seen yet
            if ($selected_date !== $array_date)
            {
                // Increment the index
                $index++;

                // If this risk is not filtered
                if (!$filtered)
                {
                    // Set the count value at the index to 1
                    $count[$index] = 1;

                    // If this is the first index then set the total to 1
                    // Otherwise set it to the previous index total + 1
                    $total[$index] = ($index === 0) ? $total[$index] = 1 : $total[$index-1] + 1;

                    // Set the values to the appropriate risk score
                    $sum[$index] = $array[$key][$scoring_field];
                    $min[$index] = $array[$key][$scoring_field];
                    $max[$index] = $array[$key][$scoring_field];
                    $average[$index] = $sum[$index] / $count[$index];

                }
                // If this risk is filtered
                else
                {
                    // Set the count value at the index to 0
                    $count[$index] = 0;

                    // If this is the first index then set the total to 0
                    // Otherwise set it to the previous index total
                    $total[$index] = ($index === 0) ? $total[$index] = 0 : $total[$index-1];

                    // Set the values to zero
                    $sum[$index] = 0;
                    $min[$index] = 0;
                    $max[$index] = 0;
                    $average[$index] = 0;
                }

                // Add the date to the dates array at the index
                $dates[$index] = date($date_format, strtotime($array[$key][$datefield]));

                // Set the selected date to the array date
                $selected_date = $array_date;

            }
            // If this is a date we have already seen
            else
            {
                // If this risk is not filtered
                // No need to do any of this if the risk is filtered
                if (!$filtered)
                {
                    // Our index will remain the same because it is the same date
                    // Increment the count value at the index
                    $count[$index] += 1;

                    // Increment the total value at the index
                    $total[$index] += 1;

                    // Add the calculated/residual risk to the values at the index
                    $sum[$index] += $array[$key][$scoring_field];
                    $min[$index] = ($min[$index] < $array[$key][$scoring_field]) ? $min[$index] : $array[$key][$scoring_field];
                    $max[$index] = ($max[$index] > $array[$key][$scoring_field]) ? $max[$index] : $array[$key][$scoring_field];
                    $average[$index] = $sum[$index] / $count[$index];
                }
            }
        }

        // For each date from the start date until today
        while ($date <= $current_time)
        {
            // Add the date to the labels array
            $labels[] = date($date_format, $date);

            // Search the dates array for the selected date and get the index
            $index = array_search(date($date_format, $date), $dates);

            // If the current date is in the risks array
            if ($index !== false)
            {
                // Add the data based on the y axis aggregation value
                switch ($y_axis_aggregation)
                {
                    case "average":
                        $data[] = $average[$index];
                        break;
                    case "count":
                        $data[] = $count[$index];
                        break;
                    case "total":
                        $data[] = $total[$index];
                        break;
                    case "max":
                        $data[] = $max[$index];
                        break;
                    case "min":
                        $data[] = $min[$index];
                        break;
                    default:
                        $data[] = $average[$index];
                }
            }
            // If the current date is not in the risks array
            else{
                // Add the data based on the y axis aggregation vlue
                switch ($y_axis_aggregation)
                {
                    case "average":
                        // We found no risks so the average is 0
                        $data[] = 0;
                        break;
                    case "count":
                        // The count has not changed from the previous index
                        $data[] = end($data);
                        break;
                    case "total":
                        // The total has not changed from the previous index
                        $data[] = end($data);
                        break;
                    case "max":
                        // We found no risks so the max is 0
                        $data[] = 0;
                        break;
                    case "min":
                        // We found no risks so the min is 0
                        $data[] = 0;
                        break;
                    default:
                        // We found no risks so the default is 0
                        $data[] = 0;
                }
            }

            // Increment the date one timeframe
            $date = strtotime("+1 {$timeframe}", $date);
        }
    }

    // Create the dataset
    $dataset = [
        "label" => $dataset_label,
        "dates" => $labels,
        "data" => $data
    ];

    // Return the dataset
    return $dataset;

    /*
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
*/
}

?>