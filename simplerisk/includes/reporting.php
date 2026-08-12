<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));
// audit_history_link_page() -- the shared audit deep-link rule used by
// get_home_recent_failures_items(). Declared directly rather than relied on
// transitively: a caller must load the file defining the helper it calls.
require_once(realpath(__DIR__ . '/compliance_grid.php'));
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
// get_*_connectivity_for_*() moved out of this file into entity_graph.php.
// No function in that file is called from HERE any more -- this require is
// a deliberate compatibility shim for any out-of-tree caller that still
// reaches those walkers transitively through reporting.php (which is
// broadly included) rather than declaring entity_graph.php directly. Every
// in-tree consumer found during this move declares its own require_once
// per CLAUDE.md's function-reachability rule and does not depend on this.
require_once(realpath(__DIR__ . '/entity_graph.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new simpleriskEscaper();

/***********************************
 * FUNCTION SUGGESTED COLORS ARRAY *
 ***********************************/
function suggested_colors_array() {

    // Create an array of suggested colors to use
    $suggested_colors_array = [
        '#4572A7',
        '#AA4643',
        '#89A54E',
        '#80699B',
        '#3D96AE',
        '#DB843D',
        '#92A8CD',
        '#A47D7C',
        '#B5CA92'
    ];

    // Return the array
    return $suggested_colors_array;
    
}

/********************************************************************************
 * FUNCTION: CREATE_CHARTJS_PIE_CODE                                            *
 * $title should be the title to display at the top of the pie chart            *
 * $element_id should be a unique element id on the page for the pie chart      *
 * $array should be a multi-dimensional array containing the following indexes: *
 * $array[]['label'] - The label to apply to the pie slice                      *
 * $array[]['data'] - The data to apply to the pie slice                        *
 * $array[]['color'] - The color to apply to the pie slice                      *
 * $array[]['url'] - The URL to apply when the pie slice is clicked             *
 * $width - The width of the created canvas                                     *
 * $height - The height of the created canvas                                   *
 ********************************************************************************/
function create_chartjs_pie_code($title = "", $element_id = "", $array = [], $width = null, $height = null) {

    // Encode for safe embedding — json_encode handles all special characters
    $title_json = json_encode((string)$title);
    $title_html = htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $labels_raw = [];
    $data_raw   = [];

    // If the array is not empty
    if (!empty($array)) {

        // Collect raw values — json_encode handles all escaping
        foreach ($array as $row) {
            $labels_raw[] = $row['label'];
            $data_raw[]   = $row['data'];
        }
    }

    // If the labels and data are not empty
    if (!empty($labels_raw) && !empty($data_raw)) {

        // Encode as JSON arrays for safe JS embedding
        $labels_json = json_encode($labels_raw);
        $data_json   = json_encode($data_raw);

        // Get the background color value
        $backgroundColor = get_background_colors($array);

        // Get the URL switch code
        $url_switch_code = get_url_switch_code($array);

        // Set the width
        if (is_null($width)) {
            $width = "";
        } else {
            $width = "width: {$width};";
        }

        // Set the height
        if (is_null($height)) {
            $height = "";
        } else {
            $height = "height: {$height};";
        }

        // The good/bad binary pies (a self-evident two-slice red/green split) hide
        // the legend — each slice's label + value is already in the hover tooltip,
        // so the legend above the chart just wastes vertical space. Other pies keep
        // theirs (their many categories need the color key).
        $no_legend_pies = ['open_closed_pie', 'open_review_pie', 'open_mitigation_pie', 'compliance_pass_fail_pie_chart', 'governance_control_status_pie_chart', 'governance_current_control_maturity_pie_chart', 'im_by_severity', 'im_by_status', 'im_by_attack_vector', 'im_by_source'];
        $legend_display = in_array($element_id, $no_legend_pies, true) ? 'false' : 'true';

        // Pies framed by a dashboard widget show their title in the widget header,
        // so they suppress the duplicate in-canvas Chart.js title.
        $framed_pies = ['compliance_pass_fail_pie_chart', 'governance_control_status_pie_chart', 'governance_current_control_maturity_pie_chart', 'im_by_severity', 'im_by_status', 'im_by_attack_vector', 'im_by_source'];
        $pie_title_display = in_array($element_id, $framed_pies, true) ? 'false' : 'true';

        // Some pies read best as a full breakdown — hovering ANY slice should show
        // every category (e.g. Passing / Failing / N/A, or each maturity level) with
        // its count plus a Total footer, not just the one slice under the cursor.
        // Override the tooltip to list all slices in one popup.
        $all_status_tooltip_pies = ['compliance_pass_fail_pie_chart', 'governance_control_status_pie_chart', 'governance_current_control_maturity_pie_chart', 'im_by_severity', 'im_by_status', 'im_by_attack_vector', 'im_by_source'];
        $total_label_json = json_encode((($GLOBALS['lang']['Total'] ?? 'Total')) . ': ');
        $tooltip_config = in_array($element_id, $all_status_tooltip_pies, true) ? "
                                tooltip: {
                                    displayColors: false,
                                    callbacks: {
                                        title: function() { return {$title_json}; },
                                        label: function(context) {
                                            var d = context.chart.data;
                                            var active = context.dataIndex;
                                            // Mark the hovered slice's row with a ▶ so it's obvious
                                            // which slice the tooltip is anchored on; pad the others
                                            // to keep the values aligned.
                                            return d.labels.map(function(l, i) {
                                                var line = l + ': ' + d.datasets[0].data[i];
                                                return (i === active ? '▶ ' : '   ') + line;
                                            });
                                        },
                                        footer: function(items) {
                                            var d = items[0].chart.data.datasets[0].data;
                                            var sum = d.reduce(function(a, b) { return a + (Number(b) || 0); }, 0);
                                            return {$total_label_json} + sum;
                                        }
                                    }
                                }," : "";

        echo "
            <canvas id='{$element_id}'></canvas>
            <div class='save_as_image'>
                <i class='far fa-save' id='{$element_id}_save'></i>
            </div>
            <script>
                $(function() {
                    data = {
                        labels: {$labels_json},
                        datasets: [{
                            data: {$data_json},
                            {$backgroundColor}
                            // Hovered slice lifts out and separates with a white
                            // gap, so the one under the cursor clearly stands out.
                            hoverOffset: 14,
                            hoverBorderColor: '#ffffff',
                            hoverBorderWidth: 2,
                        }],
                    };
                    config = {
                        type: 'pie',
                        data: data,
                        options: {
                            plugins: {
                                legend: {
                                    display: {$legend_display},
                                },
                                title: {
                                    display: {$pie_title_display},
                                    text: {$title_json},
                                },
                                {$tooltip_config}
                            },
                        },
                    };

                    ctx = document.getElementById('{$element_id}').getContext('2d');
    
                    {$element_id}_chart = new Chart(ctx, config);

                    const unassigned_index = data.labels.indexOf('Unassigned');

                    if (unassigned_index > -1) {

                        // Set 'Unassigned' slice color to gray
                        {$element_id}_chart.data.datasets[0].backgroundColor[unassigned_index] = '#9E9E9E';
                        {$element_id}_chart.update();

                    }

                    // Enable download of chart as an image
                    document.getElementById('{$element_id}_save').addEventListener('click',function(){
                        var {$element_id}_link = document.createElement('a');
                        {$element_id}_link.href = {$element_id}_chart.toBase64Image();
                        {$element_id}_link.download = '{$element_id}.png';
                        {$element_id}_link.click();
                    });
                    
                    // Redirect to another page when clicked
                    var {$element_id}_canvas = document.getElementById('{$element_id}');
                    {$element_id}_canvas.onclick = function(e) {
                        {$element_id}_chartInstance = Chart.getChart({$element_id}_canvas);
                        var slice = {$element_id}_chartInstance.getElementsAtEventForMode(e, 'nearest', {intersect: true}, true);
                        if (!slice.length) return; // Return if not clicked on a slice
                        index = slice[0].index;
                        label = {$element_id}_chartInstance.data.labels[index];
                        {$url_switch_code}
                    }
                });
            </script>
        ";
    } else {
        echo "
            <div class='d-flex flex-column text-center'>
                <strong class='mb-3'>{$title_html}</strong>
                <strong>No Data Available</strong>
            </div>
        ";
    }
}

/********************************************************************************
 * FUNCTION: CREATE_CHARTJS_MULTI_SERIES_PIE_CODE                                          *
 * $title should be the title to display at the top of the donut chart          *
 * $element_id should be a unique element id on the page for the donut chart    *
 * $array should be a multi-dimensional array containing the following indexes: *
 * $array[]['label'] - The label to apply to the pie slice                      *
 * $array[]['data'] - The data to apply to the pie slice                        *
 * $array[]['color'] - The color to apply to the pie slice                      *
 * $array[]['url'] - The URL to apply when the pie slice is clicked             *
 * $width - The width of the created canvas                                     *
 * $height - The height of the created canvas                                   *
 ********************************************************************************/
function create_chartjs_multi_series_pie_code($title = "", $element_id = "", $dataset_labels = [], $array = [], $width = null, $height = null)
{
    // Encode for safe JS/HTML embedding
    $title_json = json_encode((string)$title);
    $title_html = htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Set the width
    if (is_null($width))
    {
        $width = "";
    }
    else $width = "width: {$width};";

    // Set the height
    if (is_null($height))
    {
        $height = "";
    }
    else $height = "height: {$height};";

    // If the array is not empty
    if (!empty($array))
    {
        // Begin the script
        echo "
            <div style='{$width}{$height}'>
                <canvas id='{$element_id}'></canvas>
                <div class='d-flex justify-content-end align-items-center'>
                    <i class='far fa-save' id='{$element_id}_save'></i>
                </div>
            </div>
            <script>
                $(function () {
        ";

        $datasets_json_array = [];

        // For each dataset in the array
        foreach($array as $index=>$dataset)
        {
            // If the dataset is not empty
            if (!empty($dataset))
            {
                // Reset the label, data and colors arrays
                $slice_labels = [];
                $data = [];
                $colors = [];

                // Create the individual arrays for the dataset
                foreach ($dataset as $row)
                {
                    $slice_labels[] = $row['label'];
                    $data[] = $row['data'];
                    $colors[] = $row['color'];
                }

                $slice_labels_json = '[]';
                $dataset_json = '{}';

                // If the data is not empty
                if (!empty($data))
                {
                    // Encode as JSON arrays for safe JS embedding
                    $slice_labels_json = json_encode(array_values($slice_labels));
                    $data_json         = json_encode(array_values($data));
                    $color_json        = json_encode(array_values($colors));

                    // Add the data and colors
                    $dataset_json = "{\n";
                    $dataset_json .= "data: {$data_json},\n";
                    $dataset_json .= "backgroundColor: {$color_json},\n";
                    $dataset_json .= "}\n";
                }

                // Add the json to an array of dataset json
                $datasets_json_array[] = $dataset_json;

                // If this is the inside pie
                if ($index === "inside")
                {
                    echo "
                    var insidePieLabels = {$slice_labels_json};
                    ";
                }
                // If this is the outside pie
                else if ($index === "outside")
                {
                    echo "
                    var outsidePieLabels = {$slice_labels_json};
                    ";
                }
            }
        }

        // Create the datasets json
        $datasets_json = "[" . implode(',', $datasets_json_array) . "]";

        echo "    
                    data = {
                        datasets: {$datasets_json}
                    };
    
                    config = {
                        type: 'pie',
                        data: data,
                        options: {
                            responsive: true,
                            legend: {
                                display: false,
                            },
                            plugins: {
                                title: {
                                    display: true,
                                    text: {$title_json},
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (tooltipItem) {
                                            var datasetIndex = tooltipItem.datasetIndex;
                                            var dataIndex = tooltipItem.dataIndex;
                                            var value = tooltipItem.formattedValue;
                                            var datasetLabel = tooltipItem.dataset.label;
                                            var sliceLabel;

                                            if (datasetIndex === 0) {
                                                sliceLabel = outsidePieLabels[dataIndex];
                                            } else {
                                                sliceLabel = insidePieLabels[dataIndex];
                                            }
                                            return sliceLabel + ': ' + value;
                                        }
                                    }
                                }
                            },
                        },
                    };
                    ctx = document.getElementById('{$element_id}');

                    {$element_id}_chart = new Chart(ctx, config);
                
                    // Enable download of chart as an image
                    document.getElementById('{$element_id}_save').addEventListener('click',function(){
                        var {$element_id}_link = document.createElement('a');
                        {$element_id}_link.href = {$element_id}_chart.toBase64Image();
                        {$element_id}_link.download = '{$element_id}.png';
                        {$element_id}_link.click();
                    });
                });
            </script>
    ";
    }
    else
    {
        echo "
            <div class='d-flex flex-column text-center'>
                <strong class='mb-3'>{$title_html}</strong>
                <strong>No Data Available</strong>
            </div>
        ";
    }
}

/********************************************************************************
 * FUNCTION: CREATE_CHARTJS_LINE_CODE                                            *
 * $title should be the title to display at the top of the pie chart            *
 * $element_id should be a unique element id on the page for the pie chart      *
 * $array should be a multi-dimensional array containing the following indexes: *
 * $array[]['label'] - The label to apply to the pie slice                      *
 * $array[]['data'] - The data to apply to the pie slice                        *
 * $array[]['color'] - The color to apply to the pie slice                      *
 * $array[]['url'] - The URL to apply when the pie slice is clicked             *
 * $width - The width of the created canvas                                     *
 * $height - The height of the created canvas                                   *
 ********************************************************************************/
function create_chartjs_line_code($title = "", $element_id = "", $labels = [], $datasets = [], $tooltip = "", $x_axis_title = null, $y_axis_title = null, $y_axis_max = null, $width = null, $height = null) {

    // Encode for safe JS/HTML embedding
    $title_json       = json_encode((string)$title);
    $title_html       = htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $x_axis_title_json = json_encode((string)$x_axis_title);

    // If the labels and datasets are not empty
    if (!empty($labels) && !empty($datasets)) {

        // Set the width
        if (is_null($width)) {
            $width = "";
        } else {
            $width = "width: {$width};";
        }

        // Set the height
        if (is_null($height)) {
            $height = "";
        } else {
            $height = "height: {$height};";
        }

        echo "
            <div style='{$width}{$height}'>
                <canvas id='{$element_id}'></canvas>
                <div class='d-flex justify-content-end align-items-center'>
                    <i class='far fa-save' id='{$element_id}_save'></i>
                </div>
            </div>
            <script>
                $(function() {
        ";

        // Encode labels as a JSON array for safe JS embedding
        $labels_json = json_encode(array_values($labels));

        // Begin the data
        echo "
                    data = {
                        labels: {$labels_json},
                        datasets: [
        ";

        // For each of the datasets provided
        foreach ($datasets as $dataset) {

            // Get the values for the dataset
            $label = (isset($dataset['label']) ? "label: " . json_encode((string)$dataset['label']) . "," : "");
            $data = implode(",", $dataset['data']);
            $display = (isset($dataset['display']) ? "display: {$dataset['display']}," : "");
            $fill = (isset($dataset['fill']) ? "fill: {$dataset['fill']}," : "");
            $borderColor = (isset($dataset['borderColor']) ? "borderColor: '{$dataset['borderColor']}'," : "");
            $backgroundColor = (isset($dataset['backgroundColor']) ? "backgroundColor: '{$dataset['backgroundColor']}'," : "");
            $borderWidth = (isset($dataset['borderWidth']) ? "borderWidth: '{$dataset['borderWidth']}'," : "");
            $tension = (isset($dataset['tension']) ? "tension: '{$dataset['tension']}'," : "");

            echo "
                            {
                                {$label}
                                data: [{$data}],
                                {$display}
                                {$fill}
                                {$borderColor}
                                {$backgroundColor}
                                {$borderWidth}
                                {$tension}
                            },
            ";
        }

        // End the data
        echo "
                        ]
                    };
        ";

        // Get the y axis values
        $y_axis = get_y_axis_code($y_axis_title, $y_axis_max);

        echo "
                    config = {
                        type: 'line',
                        data: data,
                        options: {
                            responsive: true,
                            legend: {
                                display: false,
                            },
                            plugins: {
                                title: {
                                    display: true,
                                    text: {$title_json},
                                },
                                {$tooltip}
                            },
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            scales: {
                                x: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: {$x_axis_title_json}
                                    }
                                },
                                {$y_axis}
                            },
                            elements: {
                                point: {
                                    radius: 0
                                }
                            }
                        },
                    };
                    ctx = document.getElementById('{$element_id}').getContext('2d');

                    {$element_id}_chart = new Chart(ctx, config);
                    
                    // Enable download of chart as an image
                    document.getElementById('{$element_id}_save').addEventListener('click',function(){
                        var {$element_id}_link = document.createElement('a');
                        {$element_id}_link.href = {$element_id}_chart.toBase64Image();
                        {$element_id}_link.download = '{$element_id}.png';
                        {$element_id}_link.click();
                    });
                });
            </script>
        ";
    } else {
        echo "
            <div class='d-flex flex-column text-center'>
                <strong class='mb-3'>{$title_html}</strong>
                <strong>No Data Available</strong>
            </div>
        ";
    }
}

/********************************************************************************
 * FUNCTION: CREATE_CHARTJS_BAR_CODE                                            *
 * $title should be the title to display at the top of the pie chart            *
 * $element_id should be a unique element id on the page for the pie chart      *
 * $array should be a multi-dimensional array containing the following indexes: *
 * $array[]['label'] - The label to apply to the pie slice                      *
 * $array[]['data'] - The data to apply to the pie slice                        *
 * $array[]['color'] - The color to apply to the pie slice                      *
 * $array[]['url'] - The URL to apply when the pie slice is clicked             *
 * $width - The width of the created canvas                                     *
 * $height - The height of the created canvas                                   *
 ********************************************************************************/
function create_chartjs_bar_code($title = "", $element_id = "", $labels = [], $datasets = [], $x_axis_title = null, $y_axis_title = null, $width = null, $height = null, $stacked = false, $horizontal = false, $scrollable = false, $show_legend = true, $total_tooltip = false, $hide_gridlines = false)
{

    global $lang, $escaper;

    // Encode for safe JS/HTML embedding
    $title_json       = json_encode((string)$title);
    $title_html       = htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $x_axis_title_json = json_encode((string)$x_axis_title);
    $y_axis_title_json = json_encode((string)$y_axis_title);

    $x_stacked = $stacked ? "\n stacked: true," : "";
    $y_stacked = $stacked ? "\n stacked: true," : "";
    // Horizontal bars (indexAxis 'y'): categories run down the Y axis, values along X.
    $index_axis = $horizontal ? "indexAxis: 'y'," : "";
    // The 'index' interaction mode measures cursor distance along ONE axis to pick
    // the nearest category. That axis must match the CATEGORY axis: y for
    // horizontal bars, x for vertical. Without this it defaults to x, so on a
    // horizontal chart every hover collapses to the first bar (all bars share x=0).
    $interaction_axis = $horizontal ? "axis: 'y'," : "axis: 'x',";

    // Dashboard chart widgets show their title in the widget header (the .sr-widget
    // frame), so those suppress the duplicate in-canvas Chart.js title.
    $framed_charts = ['compliance_controls_by_domain', 'compliance_controls_by_class',
        'compliance_controls_by_phase', 'compliance_controls_by_priority',
        'compliance_controls_by_maturity', 'compliance_control_status_over_time_chart',
        'governance_controls_by_domain', 'governance_controls_by_class',
        'governance_controls_by_phase', 'governance_controls_by_priority',
        'governance_controls_by_maturity', 'governance_framework_maturity_stacked_bar_chart',
        'im_ttd_team_chart', 'im_ttd_source_chart', 'im_ttd_attack_vector_chart'];
    $is_framed = in_array($element_id, $framed_charts, true);
    $title_display = $is_framed ? 'false' : 'true';

    // maintainAspectRatio must be OFF (canvas fills its container height) for both:
    //   - scrollable charts (many categories → tall sizer wrapper), and
    //   - framed charts (they fill the header-shortened widget body).
    // Otherwise a fixed aspect ratio would over/underflow the body.
    $maintain_aspect = ($scrollable || $is_framed) ? "maintainAspectRatio: false," : "";

    // Optional legend suppression (the series colours are self-evident and also
    // appear in the tooltip, so the legend is just wasted space on some charts).
    $legend_line = $show_legend ? "" : "legend: { display: false },";

    // Optional gridline suppression, per axis: pass true to hide both, 'x' to hide
    // only the x-axis grid (vertical lines), or 'y' to hide only the y-axis grid
    // (horizontal lines) — cleaner look on narrow/scrollable charts.
    $hide_x_grid = ($hide_gridlines === true || $hide_gridlines === 'x');
    $hide_y_grid = ($hide_gridlines === true || $hide_gridlines === 'y');
    $x_grid_line = $hide_x_grid ? "grid: { display: false }," : "";
    $y_grid_line = $hide_y_grid ? "grid: { display: false }," : "";

    // Optional tooltip footer showing the stacked total for the hovered category
    // (interaction mode is 'index', so every dataset's slice is in the tooltip).
    // The value lives on the value axis: x for horizontal bars, y for vertical.
    $val_key = $horizontal ? 'x' : 'y';
    $total_label_json = json_encode(($lang['Total'] ?? 'Total') . ': ');
    $tooltip_line = $total_tooltip ? "
                                tooltip: {
                                    callbacks: {
                                        footer: function(items) {
                                            var sum = 0;
                                            items.forEach(function(i) { sum += (i.parsed && typeof i.parsed.{$val_key} === 'number') ? i.parsed.{$val_key} : 0; });
                                            return {$total_label_json} + sum;
                                        }
                                    }
                                }," : "";

    // If the labels and datasets are not empty
    if (!empty($labels) && !empty($datasets))
    {
        // Set the width
        if (is_null($width))
        {
            $width = "";
        }
        else $width = "width: {$width};";

        // Set the height
        if (is_null($height))
        {
            $height = "";
        }
        else $height = "height: {$height};";

        // For scrollable horizontal charts, size the canvas to the category count
        // (~26px/row + room for title & axis) inside a vertical-scroll wrapper.
        if ($scrollable) {
            $sizer_height = (count($labels) * 26) + 70;
            $canvas_html = "<div class='sr-chart-vscroll'><div class='sr-chart-vscroll__sizer' style='height:{$sizer_height}px;'><canvas id='{$element_id}'></canvas></div></div>";
        } else {
            $canvas_html = "<canvas id='{$element_id}'></canvas>";
        }

        echo "
            {$canvas_html}
            <div class='save_as_image'>
                <i class='far fa-save' id='{$element_id}_save'></i>
            </div>
            <script>
                $(function() {
        ";

        // Encode labels as a JSON array for safe JS embedding
        $labels_json = json_encode(array_values($labels));

        // Begin the data
        echo "
                    data = {
                        labels: {$labels_json},
                        datasets: [
        ";

        // For each of the datasets provided
        foreach ($datasets as $dataset)
        {
            // Get the values for the dataset
            $label_json = json_encode((string)$dataset['label']);
            $data_json = json_encode(array_values($dataset['data']));
            $backgroundColor = isset($dataset['backgroundColor']) ? $dataset['backgroundColor'] : null;
            $bar_thickness_line = $stacked ? "" : "
                                barThickness: 5,
            ";

            echo "
                            {
                                label: {$label_json},
                                data: {$data_json},
                                {$bar_thickness_line}
            ";

            if ($backgroundColor) {
                $bg_json = json_encode((string)$backgroundColor);
                echo "
                                backgroundColor: {$bg_json},
                ";
            }
            
            echo "
                            },
            ";
        }

        // End the data
        echo "
                        ]
                    };
        ";

        echo "
                    config = {
                        type: 'bar',
                        data: data,
                        options: {
                            responsive: true,
                            {$maintain_aspect}
                            {$index_axis}
                            plugins: {
                                title: {
                                    display: {$title_display},
                                    text: {$title_json},
                                },
                                {$legend_line}
                                {$tooltip_line}
                            },
                            interaction: {
                                mode: 'index',
                                intersect: false,
                                {$interaction_axis}
                            },
                            scales: {
                                x: {
                                    display: true,{$x_stacked}
                                    beginAtZero: true,
                                    {$x_grid_line}
                                    title: {
                                        display: true,
                                        text: {$x_axis_title_json}
                                    }
                                },
                                y: {
                                    display: true,{$y_stacked}
                                    {$y_grid_line}
                                    title: {
                                        display: true,
                                        text: {$y_axis_title_json}
                                    },
                                    beginAtZero: true
                                }
                            }
                        },
                    };
                    ctx = document.getElementById('{$element_id}').getContext('2d');

                    {$element_id}_chart = new Chart(ctx, config);
                    
                    // Enable download of chart as an image
                    document.getElementById('{$element_id}_save').addEventListener('click',function(){
                        var {$element_id}_link = document.createElement('a');
                        {$element_id}_link.href = {$element_id}_chart.toBase64Image();
                        {$element_id}_link.download = '{$element_id}.png';
                        {$element_id}_link.click();
                    });
                });
            </script>
    ";
    }
    else
    {
        echo "
            <div class='d-flex flex-column text-center'>
                <strong class='mb-3'>{$title_html}</strong>
                <strong>{$escaper->escapeHtml($lang['NoDataAvailable'])}</strong>
            </div>
        ";
    }
}

/********************************************************************************
 * FUNCTION: CREATE_CHARTJS_RADAR_CODE                                          *
 * $title should be the title to display at the top of the pie chart            *
 * $element_id should be a unique element id on the page for the pie chart      *
 * $array should be a multi-dimensional array containing the following indexes: *
 * $array[]['label'] - The label to apply to the pie slice                      *
 * $array[]['data'] - The data to apply to the pie slice                        *
 * $array[]['color'] - The color to apply to the pie slice                      *
 * $array[]['url'] - The URL to apply when the pie slice is clicked             *
 * $width - The width of the created canvas                                     *
 * $height - The height of the created canvas                                   *
 ********************************************************************************/
function create_chartjs_radar_code($title = "", $element_id = "", $labels = [], $datasets = [], $width = null, $height = null) {

    // Encode for safe JS/HTML embedding
    $title_json = json_encode((string)$title);
    $title_html = htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // If the labels and datasets are not empty
    if (!empty($labels) && !empty($datasets)) {

        // Set the width
        if (is_null($width)) {
            $width = "";
        } else {
            $width = "width: {$width};";
        }

        // Set the height
        if (is_null($height)) {
            $height = "";
        } else {
            $height = "height: {$height};";
        }

        echo "
            <div style='{$width}{$height}'>
                <canvas id='{$element_id}'></canvas>
                <div class='d-flex justify-content-end align-items-center'>
                    <i class='far fa-save' id='{$element_id}_save'></i>
                </div>
            </div>
            <script>
                $(function() {
        ";

        // Get the maturity levels
        $control_maturity_levels = get_options_from_table("control_maturity");
        echo "
                    var maturity_levels = {
        ";
        foreach($control_maturity_levels as $maturity_level) {
            $ml_value_json = json_encode((int)$maturity_level['value']);
            $ml_name_json  = json_encode((string)$maturity_level['name']);
            echo "
                        {$ml_value_json}: {$ml_name_json},
            ";
        }
        echo "
                    }
        ";

        // Encode labels as a JSON array for safe JS embedding
        $labels_json = json_encode(array_values($labels));

        // Begin the data
        echo "
                    data = {
                        labels: {$labels_json},
                        datasets: [
        ";

        // For each of the datasets provided
        foreach ($datasets as $dataset) {
            // Get the values for the dataset
            $label_json = json_encode((string)$dataset['label']);
            $data = implode(",", $dataset['data']);

            echo "
                            {
                                label: {$label_json},
                                data: [{$data}],
                                fill: true
                            },
            ";
        }

        // End the data
        echo "
                        ]
                    };
        ";

        echo "
                    config = {
                        type: 'radar',
                        data: data,
                        options: {
                            responsive: true,
                            scales: {
                                r: {
                                    min: 0,
                                    max: 5,
                                    ticks: {
                                        stepSize: 1,
                                        beginAtZero: true,
                                        callback: function(value, index, values) {
                                            // console.log(value);
                                            return maturity_levels[value] + ' (' + value + ')';
                                        }
                                    }
                                }
                            },
                            plugins: {
                                title: {
                                    display: true,
                                    text: {$title_json},
                                },
                                tooltip: {
                                    mode: 'index'
                                }
                            },
                            elements: {
                                line: {
                                    borderWidth: 3
                                }
                            }
                        },
                    };
                    ctx = document.getElementById('{$element_id}').getContext('2d');

                    {$element_id}_chart = new Chart(ctx, config);
                    
                    // Enable download of chart as an image
                    document.getElementById('{$element_id}_save').addEventListener('click',function(){
                        var {$element_id}_link = document.createElement('a');
                        {$element_id}_link.href = {$element_id}_chart.toBase64Image();
                        {$element_id}_link.download = '{$element_id}.png';
                        {$element_id}_link.click();
                    });
                });
            </script>
        ";

    } else {

        echo "
            <div class='d-flex flex-column text-center'>
                <strong class='mb-3'>{$title_html}</strong>
                <strong>No Data Available</strong>
            </div>
        ";

    }
}

/********************************************************************************
 * FUNCTION: CREATE_CHARTJS_BUBBLE_CODE                                         *
 * $title should be the title to display at the top of the pie chart            *
 * $element_id should be a unique element id on the page for the pie chart      *
 * $array should be a multi-dimensional array containing the following indexes: *
 * $array[]['label'] - The label to apply to the pie slice                      *
 * $array[]['data'] - The data to apply to the pie slice                        *
 * $array[]['color'] - The color to apply to the pie slice                      *
 * $array[]['url'] - The URL to apply when the pie slice is clicked             *
 * $width - The width of the created canvas                                     *
 * $height - The height of the created canvas                                   *
 ********************************************************************************/
function create_chartjs_bubble_code($title = "", $element_id = "", $datasets = [], $tooltip = [], $x_axis_title = null, $y_axis_title = null, $width = null, $height = null) {

    // Encode for safe JS/HTML embedding
    $title_json        = json_encode((string)$title);
    $title_html        = htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $x_axis_title_json = json_encode((string)$x_axis_title);
    $y_axis_title_json = json_encode((string)$y_axis_title);

    // If the datasets are not empty
    if (!empty($datasets)) {
        // Set the width
        if (is_null($width)) {
            $width = "";
        } else {
            $width = "width: {$width};";
        }

        // Set the height
        if (is_null($height)) {
            $height = "";
        } else {
            $height = "height: {$height};";
        }

        // Create empty arrays
        $colors = [];
        $ids = [];
        $subjects = [];
        $counts = [];

        echo "
            <div style='{$width}{$height}'>
                <canvas id='{$element_id}'></canvas>
                <div class='d-flex justify-content-end align-items-center'>
                    <i class='far fa-save' id='{$element_id}_save'></i>
                </div>
            </div>
            <script>
                $(function () {
        ";

        // Begin the data
        echo "
                    data = {
                        datasets: [
        ";

        $scores = [];
        $counts = [];
        $colors = [];
        $ids = [];
        $subjects = [];

        // For each of the datasets provided
        foreach ($datasets as $dataset) {

            // Get the values for the dataset
            $x = $dataset['x'];
            $y = $dataset['y'];
            $r = $dataset['r'];
            $scores[] = $dataset['label'];
            $counts[] = $dataset['count'];
            $colors[] = $dataset['color'];

            // Add the ids to the ids array as a string
            $ids[] = "[" . implode(",", $dataset['ids']) . "]";

            // Encode subjects as a JSON array
            $subjects[] = json_encode(array_values($dataset['subjects']));

            echo "
                            {
                                data: [{
                                    x: {$x},
                                    y: {$y},
                                    r: {$r},
                                }],
                            },
            ";
        }

        // End the data
        echo "
                        ]
                    };
        ";

        // Create javascript variables for the extra data using json_encode for safe embedding
        $scores_json   = json_encode(array_values($scores));
        $colors_json   = json_encode(array_values($colors));
        $counts_json   = json_encode(array_values($counts));
        echo "
                    var scores = {$scores_json};
                    var colors = {$colors_json};
                    var counts = {$counts_json};
                    var ids = [" . implode(",", $ids) . "];
                    var subjects = [" . implode(",", $subjects) . "];
        ";

        // Get the likelihood options
        $likelihood_names = array_column(get_options_from_table("likelihood"), 'name');
        $likelihoods_json = json_encode(array_values($likelihood_names));
        echo "
                    var likelihoods = {$likelihoods_json};
        ";

        // Get the impact options
        $impact_names  = array_column(get_options_from_table("impact"), 'name');
        $impacts_json  = json_encode(array_values($impact_names));
        echo "
                    var impacts = {$impacts_json};

                    config = {
                        type: 'bubble',
                        data: data,
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: {$title_json},
                                },
                                legend: false,
                                {$tooltip}
                            },
                            scales: {
                                x: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: {$x_axis_title_json}
                                    },
                                    ticks: {
                                        beginAtZero: true,
                                        stepSize: 1,
                                        callback: function(value, index, ticks) {
                                            return likelihoods[value-1];
                                        }
                                    }
                                },
                                y: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: {$y_axis_title_json}
                                    },
                                    ticks: {
                                        beginAtZero: true,
                                        stepSize: 1,
                                        callback: function(value, index, ticks) {
                                            return impacts[value-1];
                                        }
                                    }
                                }
                            }
                        },
                    };
                    ctx = document.getElementById('{$element_id}').getContext('2d');

                    {$element_id}_chart = new Chart(ctx, config);
                    
                    // For each dataset in the chart
                    var datasets = {$element_id}_chart.config.data.datasets;
                    for (i=0; i<{$element_id}_chart.config.data.datasets.length; i++)
                    {
                        // Get the dataset
                        dataset = {$element_id}_chart.config.data.datasets[i]
                        //console.log(dataset);
                        
                        // Get the color for the dataset
                        color = colors[i];
                        
                        // Get the label for the dataset
                        label = dataset.label;
                        
                        // Update the bubble colors
                        {$element_id}_chart.config.data.datasets[i].backgroundColor = color;
                        {$element_id}_chart.config.data.datasets[i].borderColor = '#000000';
                        //console.log({$element_id}_chart.config.data.datasets[i]);
                    }
                    {$element_id}_chart.update();
                    
                    // Enable download of chart as an image
                    document.getElementById('{$element_id}_save').addEventListener('click',function(){
                        var {$element_id}_link = document.createElement('a');
                        {$element_id}_link.href = {$element_id}_chart.toBase64Image();
                        {$element_id}_link.download = '{$element_id}.png';
                        {$element_id}_link.click();
                    });
                });
            </script>
        ";
    } else {
        echo "
            <div class='d-flex flex-column text-center'>
                <strong class='mb-3'>{$title_html}</strong>
                <strong>No Data Available</strong>
            </div>
        ";
    }
}

/****************************************************************
 * FUNCTION: CREATE BACKGROUND DATASET                          *
 * Create datasets to fill the background with the risk colors. *
 ****************************************************************/
function create_background_dataset($count)
{
    global $lang, $escaper;

    // Create an empty datasets array
    $datasets = [];

    // Get the risk levels
    $risk_levels = get_risk_levels();

    // Start with the highest risk level first
    $risk_levels = array_reverse($risk_levels);

    // Set the current risk level to 10
    $current_risk_level = 10;
    $data = [];

    // Create a dataset for each risk level
    foreach($risk_levels as $risk_level)
    {
        // Create an empty data array
        $data = [];

        // Create an array populated with the risk level for each count
        for ($i=0; $i<$count; $i++)
        {
            // Set it to the current risk level
            $data[] = $current_risk_level;
        }

        $dataset = [
            "label" => "{$risk_level['display_name']}",
            "data" => $data,
            "fill" => "true",
            "borderColor" => "{$risk_level['color']}",
            "backgroundColor" => "{$risk_level['color']}",
        ];

        // Update the current risk level
        $current_risk_level = $risk_level['value'];

        // Add the dataset to the datasets array
        $datasets[] = $dataset;
    }

    // We need to create the insignificant data
    for ($i=0; $i<$count; $i++)
    {
        // Set it to the current risk level
        $data[$i] = $current_risk_level;
    }

    // Add an insignificant dataset to the datasets array
    $dataset = [
        "label" => "{$escaper->escapeHtml($lang['Insignificant'])}",
        "data" => $data,
        "fill" => "true",
        "borderColor" => "#FFFFFF",
        "backgroundColor" => "#FFFFFF",
    ];
    $datasets[] = $dataset;

    // Reorder the datasets by the lowest level first
    $datasets = array_reverse($datasets);

    // Return the datasets
    return $datasets;
}

/*************************************************************
 * FUNCTION: GET Y AXIS CODE                                 *
 * This function will take in the y axis values and generate *
 * the json for the y axis.
 *************************************************************/
function get_y_axis_code($y_axis_title = null, $y_axis_max = null)
{
    // If the y axis max is not null
    if (!is_null($y_axis_max))
    {
        // Create the y axis max
        $y_axis_max = "max: {$y_axis_max}";
    }
    else $y_axis_max = "";

    // JSON-encode the title so it is safely embedded in the JS string context,
    // matching create_chartjs_bar_code()/create_chartjs_bubble_code(). A raw
    // interpolation here allowed a </script> breakout (stored XSS).
    $y_axis_title_json = json_encode((string)$y_axis_title);

    // Create the y axis code
    $y_axis_code = "
    y: {
        display: true,
            title: {
                display: true,
                text: {$y_axis_title_json}
        },
        beginAtZero: true,
        {$y_axis_max}
    }
    ";

    // Return the y axis code
    return $y_axis_code;
}

/*******************************************************************
 * FUNCTION: GET URL SWITCH CODE                                   *
 * This function takes in an array used for chart.js and generates *
 * a switch statement so each pie slice can have a unique URL.     *
 *******************************************************************/
function get_url_switch_code($array) {

    // If the array is empty
    if (empty($array)) {
        // Return an empty string
        return "";
    // Otherwise create the url switch code
    } else {
        // Begin the URL switch code
        $url_switch_code = "switch(label){\n";

        // For each element in the array
        foreach ($array as $row) {
            // If we have a label and url
            if (isset($row['label']) && isset($row['url'])) {
                // Encode label so the case value matches the JSON-decoded label from the chart
                $label_json = json_encode((string)$row['label']);
                $url = $row['url'];

                // Create the case statement
                $url_switch_code .= "  case {$label_json}:\n";

                // Create the window open statement
                $url_switch_code .= "    window.open('{$url}', '_self');\n";

                // Create the break statement
                $url_switch_code .= "    break;\n";
            }
        }

        // End the URL switch code
        $url_switch_code .= "}\n\n";

        // Return the URL switch code
        return $url_switch_code;
    }
}

/********************************************************************
 * FUNCTION: GET BACKGROUND COLORS                                  *
 *  This function takes in an array used for chart.js and generates *
 *  the backgroundColor parameter if colors were provided.          *
 ********************************************************************/
function get_background_colors($array) {

    // If the array contains colors
    if (isset($array[0]['color'])) {

        $colors = [];

        // For each item in the array
        foreach ($array as $row) {
            // Add the item to the colors array
            $colors[] = $row['color'];
        }

        // Create a CSV string of the colors
        $colors = "'" . implode("','", $colors) . "'";

        // Return the backgroundColor value
        return "backgroundColor: [{$colors}],";

    // If no colors were set return an empty string
    } else {
        return "";
    }
}

/****************************
 * FUNCTION: GET OPEN RISKS *
 ****************************/
function get_open_risks($teams = false)
{
    $teams_query = generate_teams_query($teams, "rtt.team_id");

    // If team separation is not enabled
    if (!team_separation_extra())
    {
        // Open the database connection
        $db = db_open();

        $sql = "
            SELECT
                `rsk`.`id`
            FROM
                `risks` rsk
                LEFT JOIN `risk_to_team` rtt ON `rsk`.`id`=`rtt`.`risk_id`
            WHERE
                `rsk`.`status` != 'Closed'
                AND {$teams_query}
            GROUP BY
                `rsk`.`id`;";

        // Query the database
        $stmt = $db->prepare($sql);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);
    }
    // Otherwise team separation is enabled
    else
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the open risks stripped
        $array = strip_get_open_risks($teams);
    }

    return count($array);
}

/******************************
 * FUNCTION: GET CLOSED RISKS *
 ******************************/
function get_closed_risks($teams = false)
{
    $teams_query = generate_teams_query($teams, "rtt.team_id");
    
    // If team separation is not enabled
    if (!team_separation_extra())
    {
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT a.id FROM `risks` a LEFT JOIN risk_to_team rtt ON a.id=rtt.risk_id WHERE a.status = \"Closed\" AND {$teams_query} GROUP BY a.id; ");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);
    }
    // Otherwise team separation is enabled
    else    
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the closed risks stripped
        $array = strip_get_closed_risks($teams);
    }

    return count($array);
}

/*********************************************
 * FUNCTION: HOME RISK SEPARATION SQL         *
 *********************************************/
// Returns [FROM-join fragment, WHERE-AND fragment] that scope a `risks rsk`
// query to the current user's teams when the Team Separation Extra is active
// (empty strings otherwise). Mirrors the separation handling in
// get_risk_count_of_risk_level() so the home risk widgets never count risks the
// user isn't permitted to see.
function home_risk_separation_sql()
{
    if (!team_separation_extra()) {
        return ['', ''];
    }

    // Include the team separation extra
    require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

    $from = "
        LEFT JOIN `risk_to_team` rtt ON `rsk`.`id` = `rtt`.`risk_id`
        LEFT JOIN `risk_to_additional_stakeholder` rtas ON `rsk`.`id` = `rtas`.`risk_id`
    ";
    $where = " AND " . get_user_teams_query("rsk");

    return [$from, $where];
}

/**********************************************
 * FUNCTION: GET UNMITIGATED OPEN RISK COUNT  *
 **********************************************/
// Open risks with no planned mitigation. Mirrors the 'Unplanned' branch of
// open_mitigation_pie() (mitigation_id = 0). Scoped to the user's teams when
// Team Separation is active.
function get_unmitigated_open_risk_count()
{
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT `rsk`.`id`)
        FROM `risks` rsk
        {$sep_from}
        WHERE `rsk`.`status` != 'Closed'
        AND `rsk`.`mitigation_id` = 0
        {$sep_where};
    ");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();
    db_close($db);
    return $count;
}

/*********************************************
 * FUNCTION: GET UNREVIEWED OPEN RISK COUNT  *
 *********************************************/
// Open risks with no management review. Mirrors the 'Unreviewed' branch of
// open_review_pie() (mgmt_review = 0). Scoped to the user's teams when Team
// Separation is active.
function get_unreviewed_open_risk_count()
{
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT `rsk`.`id`)
        FROM `risks` rsk
        {$sep_from}
        WHERE `rsk`.`status` != 'Closed'
        AND `rsk`.`mgmt_review` = 0
        {$sep_where};
    ");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();
    db_close($db);
    return $count;
}

/********************************************************************************************
 * FUNCTION: GET RISK COUNT OF RISK LEVEL                                                   *
 * Gets the number of risks of a risk level using the provided scoring                      *
 * $risk_level: possible values are 'Insignificant', 'Low', 'Medium', 'High', 'Very High'   *
 * $scoring: what score should be used 'inherent'(default) or 'residual'                    *
 ********************************************************************************************/
function get_risk_count_of_risk_level($risk_level, $scoring='inherent') {

    // Open the database connection
    $db = db_open();

    switch ($risk_level) {
        case "Insignificant":
                $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'Low';");
                $stmt->execute();

                $from = 0;
                $to = $stmt->fetchColumn();
            break;
        case "Low":
                $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'Low' OR name = 'Medium' ORDER BY value ASC;");
                $stmt->execute();
                $array = $stmt->fetchAll();

                $from = $array[0]['value'];
                $to = $array[1]['value'];
            break;
        case "Medium":
                $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'Medium' OR name = 'High' ORDER BY value ASC;");
                $stmt->execute();
                $array = $stmt->fetchAll();

                $from = $array[0]['value'];
                $to = $array[1]['value'];
            break;
        case "High":
                $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'High' OR name = 'Very High' ORDER BY value ASC;");
                $stmt->execute();
                $array = $stmt->fetchAll();

                $from = $array[0]['value'];
                $to = $array[1]['value'];
            break;
        case "Very High":
                $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'Very High';");
                $stmt->execute();

                $from = $stmt->fetchColumn();
                $to = 99;
            break;
    }

    $separation_query_where = "";
    $separation_query_from = "";

    if (team_separation_extra()) {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $separation_query_where = " AND ". get_user_teams_query("rsk");
        $separation_query_from = "
            LEFT JOIN `risk_to_team` rtt ON `rsk`.`id` = `rtt`.`risk_id`
            LEFT JOIN `risk_to_additional_stakeholder` rtas ON `rsk`.`id` = `rtas`.`risk_id`
        ";
    }

    // Build the queries based on the score we should use
    if ($scoring=='inherent') {
        $sql = "
            SELECT
                COUNT(distinct `rsk`.`id`)
            FROM
                `risk_scoring` scoring
                LEFT JOIN `risks` rsk ON `scoring`.`id` = `rsk`.`id`
                {$separation_query_from}
            WHERE
                `rsk`.`status` != 'Closed'
                ". $separation_query_where ."
                AND `scoring`.`calculated_risk` >= :from
                AND `scoring`.`calculated_risk` < :to;
        ";
    } else {
        $sql = "
            SELECT
                COUNT(1) AS num
            FROM (
                SELECT
                    ROUND(scoring.calculated_risk - (scoring.calculated_risk * IF(IFNULL(mitg.mitigation_percent,0) > 0, mitg.mitigation_percent, IFNULL(MAX(ctrl.mitigation_percent), 0)) / 100), 2) AS residual_risk
                FROM `risk_scoring` scoring
                    JOIN `risks` rsk ON `scoring`.`id` = `rsk`.`id`
                    LEFT JOIN `mitigations` mitg ON `rsk`.`id` = `mitg`.`risk_id`
                    LEFT JOIN `mitigation_to_controls` mtc ON `mitg`.`id` = `mtc`.`mitigation_id`
                    LEFT JOIN `framework_controls` ctrl ON `mtc`.`control_id`=`ctrl`.`id` AND `ctrl`.`deleted` = 0
                    {$separation_query_from}
                WHERE
                    `rsk`.`status` != 'Closed'
                    {$separation_query_where}
                GROUP BY
                    `rsk`.`id`
                HAVING
                    `residual_risk` >= :from
                    AND `residual_risk` < :to
                ) as a
        ";
    }

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":from", $from, PDO::PARAM_STR);
    $stmt->bindParam(":to", $to, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return $count;
}

/****************************
 * FUNCTION: GET RISK TREND *
 ****************************/
function get_risk_trend($title = null, $labels = [], $datasets = []) {

    global $lang, $escaper;

    // Get the opened risks array by month
    $opened_risks = get_opened_risks_array("day");

    $open_dates = empty($opened_risks[0]) ? [] : $opened_risks[0];
    $open_counts = empty($opened_risks[1]) ? [] : $opened_risks[1];

    // Get the closed risks array by month
    $closed_risks = get_closed_risks_array("day");

    $close_dates = empty($closed_risks[0]) ? [] : $closed_risks[0];
    $close_counts = empty($closed_risks[1]) ? [] : $closed_risks[1];

    // The following variables need to be initialized as an error occurs when they aren't.
    $open_risks_dataset = [];
    $closed_risks_dataset = [];
    $trend_dataset = [];

    // If the opened risks array is not empty
    if (!empty($opened_risks[0])) {

        // Setting a minimum date so we don't display data that's older
        // but we still use open/close numbers from those dates
        $min_date = strtotime("1970-01-01");

        // Set the initial values
        $date = strtotime($open_dates[0]);
        $current_time = time();

        $opened_sum = 0;
        $closed_sum = 0;
        $opened_risk_data = [];
        $closed_risk_data = [];
        $trend_data = [];

        // if the original start date of the report would be before 2000-01-01 then ignore those and search for the first valid date
        // but keep track of the opened/closed risks before so the numbers are properly accounted for
        // even if those dates aren't displayed on the chart
        if ($date < $min_date) {

            foreach ($open_dates as $position => $open_date) {

                $date = strtotime($open_date);

                if ($date < $min_date) {
                    $opened_sum += $open_counts[$position];
                } else {
                    break;
                }
            }

            foreach ($close_dates as $position => $close_date) {
                
                if (strtotime($close_date) < $date) {
                    $closed_sum += $close_counts[$position];
                } else {
                    break;
                }
            }
        }

        // For each date from the start date until today
        while ($date <= $current_time) {

            // Add the date to the labels array
            $labels[] = date(get_default_date_format(), $date);

            // Search the open risks array
            $opened_search = array_search(date("Y-m-d", $date), $open_dates);

            // If the current date is in the opened array
            if ($opened_search !== false) {

                $count = $open_counts[$opened_search];
                $opened_sum += $count;

            }

            // Search the closed array for the value
            $closed_search = array_search(date("Y-m-d", $date), $close_dates);

            // If the current date is in the closed array
            if ($closed_search !== false) {

                $count = $close_counts[$closed_search];
                $closed_sum += $count;

            }

            // Create the data arrays
            $opened_risk_data[] = $opened_sum;
            $closed_risk_data[] = $closed_sum;
            $trend_data[] = $opened_sum - $closed_sum;

            // Increment the date one day
            $date = strtotime("+1 day", $date);

        }

        // Create the open risks dataset
        $open_risks_dataset = [
            "label" => "Opened Risks",
            "data" => $opened_risk_data,
            "fill" => "false",
            "borderColor" => "red",
            "borderWidth" => "1",
            "tension" => "0.1"
        ];

        // Create the closed risks dataset
        $closed_risks_dataset = [
            "label" => "Closed Risks",
            "data" => $closed_risk_data,
            "fill" => "false",
            "borderColor" => "blue",
            "borderWidth" => "1",
            "tension" => "0.1"
        ];

        // Create the trend dataset
        $trend_dataset = [
            "label" => "Trend",
            "data" => $trend_data,
            "fill" => "false",
            "borderColor" => "#000000",
            "borderWidth" => "1",
            "tension" => "0.1"
        ];

    }

    // Create an array of the combined datasets
    $datasets = [
        $open_risks_dataset,
        $closed_risks_dataset,
        $trend_dataset
    ];

    // Create the Chart.js line chart
    $element_id = "risk_trend_chart";
    $x_axis_title = $lang['Date'];
    $y_axis_title = $lang['Count'];
    create_chartjs_line_code($title, $element_id, $labels, $datasets, "", $x_axis_title, $y_axis_title);

}

/******************************
 * FUNCTION: GET REPORT DASHBOARD DROPDOWN SCRIPT *
 ******************************/
function get_report_dashboard_dropdown_script() {

    global $lang, $escaper;

    echo "
        <script type='text/javascript'>
            function submitForm() {
                var brands = $('#teams option:selected');
                var selected = [];
                $(brands).each(function(index, brand){
                    selected.push($(this).val());
                });
                
                $('#team_options').val(selected.join(','));
                $('#risks_dashboard_form').submit();
            }

            $(function(){
                $('#teams').multiselect({
                    allSelectedText: '{$escaper->escapeHtml($lang['AllTeams'])}',
                    buttonWidth: '100%',
                    includeSelectAllOption: true,
                    onChange: submitForm,
                    onSelectAll: submitForm,
                    onDeselectAll: submitForm,
                    enableCaseInsensitiveFiltering: true,
                });
            });
        </script>
    ";

}

/**********************************
 * FUNCTION: OPEN RISK LEVEL PIE *
 * $teams: ex: 1:2:3:4
 **********************************/
function open_risk_level_pie($title = null, $element_id = "open_risk_level_pie", $teams = false, $score_used='inherent') {

    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("
        SELECT 
            * 
        from 
            `risk_levels` 
        ORDER BY 
            value DESC
    ");

    $stmt->execute();

    $risk_levels = $stmt->fetchAll();

    $veryhigh = $risk_levels[0]['value'];
    $high = $risk_levels[1]['value'];
    $medium = $risk_levels[2]['value'];
    $low = $risk_levels[3]['value'];

    $very_high_display_name = $risk_levels[0]['display_name'];
    $high_display_name = $risk_levels[1]['display_name'];
    $medium_display_name = $risk_levels[2]['display_name'];
    $low_display_name = $risk_levels[3]['display_name'];
    $insignificant_display_name = $lang['Insignificant'];

    $teams_query = generate_teams_query($teams, "rtt.team_id");

    // Build the query parts related to whether we have separation enabled or not
    $separation_query_where = "";
    $separation_query_from = "";

    if (team_separation_extra()) {

        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        $separation_query_where = " AND ". get_user_teams_query("rsk");
        $separation_query_from = "
            LEFT JOIN `risk_to_additional_stakeholder` rtas ON `rsk`.`id` = `rtas`.`risk_id`
        ";

    }

    // Build the inner query that's querying the scores the user requested
    if ($score_used=='inherent') {
        $inner_query = "
            SELECT
                `scoring`.`calculated_risk` as score
            FROM 
                `risk_scoring` scoring
                JOIN `risks` rsk ON `scoring`.`id` = `rsk`.`id`
                LEFT JOIN `risk_to_team` rtt ON `rsk`.`id` = `rtt`.`risk_id`
                {$separation_query_from}
            WHERE
                `rsk`.`status` != 'Closed'
                AND {$teams_query}
                {$separation_query_where}
            GROUP BY
                `rsk`.`id`
        ";
    } else {
        $inner_query = "
            SELECT
                ROUND(`scoring`.`calculated_risk` - (`scoring`.`calculated_risk` * GREATEST(IFNULL(`mtg`.`mitigation_percent`,0), IFNULL(MAX(`ctrl`.`mitigation_percent`), 0)) / 100), 2) AS score
            FROM 
                `risk_scoring` scoring
                JOIN `risks` rsk ON `scoring`.`id` = `rsk`.`id`
                LEFT JOIN `risk_to_team` rtt ON `rsk`.`id` = `rtt`.`risk_id`
                LEFT JOIN `mitigations` mtg ON `rsk`.`id` = `mtg`.`risk_id`
                LEFT JOIN `mitigation_to_controls` mtc ON `mtg`.`id` = `mtc`.`mitigation_id`
                LEFT JOIN `framework_controls` ctrl ON `mtc`.`control_id`=`ctrl`.`id` AND `ctrl`.`deleted`=0
                {$separation_query_from}
            WHERE
                `rsk`.`status` != 'Closed'
                AND {$teams_query}
                {$separation_query_where}
            GROUP BY
                `rsk`.`id`
        ";
    }

    // Assemble the final query
    $sql = "
        SELECT
            `score`,
            COUNT(*) AS num,
            CASE
                WHEN `score` >= :veryhigh THEN :very_high_display_name
                WHEN `score` < :veryhigh AND `score` >= :high THEN :high_display_name
                WHEN `score` < :high AND `score` >= :medium THEN :medium_display_name
                WHEN `score` < :medium AND `score` >= :low THEN :low_display_name
                WHEN `score` < :low AND `score` >= 0 THEN :insignificant_display_name
            END AS level
        FROM
            ({$inner_query}) AS innr
        GROUP BY
            `level`
        ORDER BY
            `score` DESC;
    ";

    $stmt = $db->prepare($sql);

    $stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR);
    $stmt->bindParam(":high", $high, PDO::PARAM_STR);
    $stmt->bindParam(":medium", $medium, PDO::PARAM_STR);
    $stmt->bindParam(":low", $low, PDO::PARAM_STR);

    $stmt->bindParam(":very_high_display_name", $very_high_display_name, PDO::PARAM_STR);
    $stmt->bindParam(":high_display_name", $high_display_name, PDO::PARAM_STR);
    $stmt->bindParam(":medium_display_name", $medium_display_name, PDO::PARAM_STR);
    $stmt->bindParam(":low_display_name", $low_display_name, PDO::PARAM_STR);
    $stmt->bindParam(":insignificant_display_name", $insignificant_display_name, PDO::PARAM_STR);

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // For each row in the array
    foreach ($array as $index=>$row) {

        // Add the pie chart indexes
        $array[$index]['label'] = $row['level'];
        $array[$index]['data'] = $row['num'];
        $array[$index]['color'] = get_risk_color_from_levels($row['score'], $risk_levels);
        $array[$index]['url'] = "dynamic_risk_report.php?status=0&group=1&sort=0";

    }

    // Create the Chart.js pie chart
    create_chartjs_pie_code($title, $element_id, $array);

}

/**********************************
 * FUNCTION: OPEN RISK STATUS PIE *
 **********************************/
function open_risk_status_pie($array, $title = null, $teams = false) {

    // $data needs to be initialized as an error occurs when it isn't.
    $data = [];

    // If the array is not empty
    if (!empty($array)) {

        // Set the sort value
        $sort = "status";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

    }

    // For each row in the array
    foreach ($data as $index=>$row) {

        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=0&group=2&sort=0';

    }

    // Create the Chart.js pie chart
    $element_id = "open_risk_status_pie";
    create_chartjs_pie_code($title, $element_id, $data);

}

/************************************
 * FUNCTION: CLOSED RISK REASON PIE *
 ************************************/
function closed_risk_reason_pie($title = null, $teams = false) {

    $teams_query = generate_teams_query($teams, "rtt.team_id");

    // Open the database connection
    $db = db_open();

    // If the team separation extra is not enabled
    if (!team_separation_extra()) {

        // Query the database
        $stmt = $db->prepare("
            SELECT 
                name, COUNT(*) as num 
            FROM 
                (
                    SELECT 
                        a.close_reason, b.name, MAX(closure_date) 
                    FROM 
                        `risks` c 
                        JOIN `closures` a ON c.close_id = a.id 
                        JOIN `close_reason` b ON a.close_reason = b.value 
                        LEFT JOIN risk_to_team rtt ON c.id = rtt.risk_id 
                    WHERE 
                        c.status = 'Closed' AND {$teams_query} 
                    GROUP BY 
                        a.risk_id 
                    ORDER BY 
                        b.name DESC
                ) AS close 
            GROUP BY 
                name 
            ORDER BY 
                COUNT(*) DESC;
        ");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {

        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Query the database
        $array = strip_no_access_risk_pie('close_reason', $teams);

    }

    // Close the database connection
    db_close($db);

    $data = [];

    // If the array is not empty
    if (!empty($array)) {

        // Create the data array
        foreach ($array as $row) {
            $data[] = array($row['name'], (int)$row['num']);
        }

    }

    // For each row in the array
    foreach ($data as $index=>$row) {

        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=1&group=0&sort=0';

    }

    // Create the Chart.js pie chart
    $element_id = "closed_risk_reason_pie";
    create_chartjs_pie_code($title, $element_id, $data);
    
}

/************************************
 * FUNCTION: OPEN RISK LOCATION PIE *
 ************************************/
function open_risk_location_pie($array, $title = null) {

    global $escaper, $lang;

    // $data needs to be initialized as an error occurs when it isn't.
    $data = [];

    // If the array is empty
    if (!empty($array)) {

        // Set the sort value
        $sort = "location";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

    }

    // For each row in the array
    foreach ($data as $index=>$row) {

        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=0&sort=0';

    }

    // Create the Chart.js pie chart
    $element_id = "open_risk_location_pie";
    create_chartjs_pie_code($title, $element_id, $data);

}

/**********************************
 * FUNCTION: OPEN RISK SOURCE PIE *
 **********************************/
function open_risk_source_pie($array, $title = null) {

    // $data needs to be initialized as an error occurs when it isn't.
    $data = [];

    // If the array is not empty
    if (!empty($array)) {

        // Set the sort value
        $sort = "source";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

    }

    // For each row in the array
    foreach ($data as $index=>$row) {

        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=0&group=4&sort=0';

    }

    // Create the Chart.js pie chart
    $element_id = "open_risk_source_pie";
    create_chartjs_pie_code($title, $element_id, $data);

}

/************************************
 * FUNCTION: OPEN RISK CATEGORY PIE *
 ************************************/
function open_risk_category_pie($array, $title = null) {

    // $data needs to be initialized as an error occurs when it isn't.
    $data = [];

    // If the array is not empty
    if (!empty($array)) {

        // Set the sort value
        $sort = "category";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

    }

    // For each row in the array
    foreach ($data as $index=>$row) {

        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=0&group=5&sort=0';

    }

    // Create the Chart.js pie chart
    $element_id = "open_risk_category_pie";
    create_chartjs_pie_code($title, $element_id, $data);

}

/********************************
 * FUNCTION: OPEN RISK TEAM PIE *
 ********************************/
function open_risk_team_pie($array, $title = null) {

    // $data needs to be initialized as an error occurs when it isn't.
    $data = [];

    // If the array is not empty
    if (!empty($array)) {

        // Set the sort value
        $sort = "team";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

    }

    // For each row in the array
    foreach ($data as $index=>$row) {
        
        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=0&group=6&sort=0';

    }

    // Create the Chart.js pie chart
    $element_id = "open_risk_team_pie";
    create_chartjs_pie_code($title, $element_id, $data);

}

/**************************************
 * FUNCTION: OPEN RISK TECHNOLOGY PIE *
 **************************************/
function open_risk_technology_pie($array, $title = null) {

    // $data needs to be initialized as an error occurs when it isn't.
    $data = [];

    // If the array is not empty
    if (!empty($array)) {

        // Set the sort value
        $sort = "technology";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

    }

    // For each row in the array
    foreach ($data as $index=>$row) {

        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=0&group=7&sort=0';

    }

    // Create the Chart.js pie chart
    $element_id = "open_risk_technology_pie";
    create_chartjs_pie_code($title, $element_id, $data);

}

/**************************************
 * FUNCTION: OPEN RISK OWNER PIE *
 **************************************/
function open_risk_owner_pie($array, $title = null) {

    // $data needs to be initialized as an error occurs when it isn't.
    $data = [];

    // If the array is not empty
    if (!empty($array)) {

        // Set the sort value
        $sort = "owner";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

    }

    // For each row in the array
    foreach ($data as $index=>$row) {

        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=0&group=8&sort=0';

    }

    // Create the Chart.js pie chart
    $element_id = "open_risk_owner_pie";
    create_chartjs_pie_code($title, $element_id, $data);

}

/******************************************
 * FUNCTION: OPEN RISK OWNERS MANAGER PIE *
 ******************************************/
function open_risk_owners_manager_pie($array, $title = null) {

    // $data needs to be initialized as an error occurs when it isn't.
    $data = [];

    // If the array is not empty
    if (!empty($array)) {

        // Set the sort value
        $sort = "manager";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

    }

    // For each row in the array
    foreach ($data as $index=>$row) {

        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=0&group=9&sort=0';
        
    }

    // Create the Chart.js pie chart
    $element_id = "open_risk_owners_manager_pie";
    create_chartjs_pie_code($title, $element_id, $data);

}

/******************************************
 * FUNCTION: OPEN RISK SCORING METHOD PIE *
 ******************************************/
function open_risk_scoring_method_pie($array, $title = null) {

    // $data needs to be initialized as an error occurs when it isn't.
    $data = [];
    
    // If the array is not empty
    if (!empty($array)) {

        // Set the sort value
        $sort = "scoring_method";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

    }

    // For each row in the array
    foreach ($data as $index=>$row) {

        // Add the properly formatted data
        $data[$index]['label'] = $row[0];
        $data[$index]['data'] = $row[1];
        $data[$index]['url'] = 'dynamic_risk_report.php?status=0&group=10&sort=0';

    }

    // Create the Chart.js pie chart
    $element_id = "open_risk_scoring_method_pie";
    create_chartjs_pie_code($title, $element_id, $data);

}

/*********************************
 * FUNCTION: OPEN MITIGATION PIE *
 *********************************/
function open_mitigation_pie($title = null) {

    // Create an element id to use for this chart
    $element_id = "open_mitigation_pie";

    // If team separation is not enabled
    if (!team_separation_extra()) {

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("
            SELECT 
                id, 
                CASE 
                    WHEN mitigation_id = 0 THEN 'Unplanned' 
                    WHEN mitigation_id != 0 THEN 'Planned' 
                END AS name 
            FROM 
                `risks` 
            WHERE 
                status != 'Closed' 
            ORDER BY 
                name
        ");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Otherwise team separation is enabled
    } else {

        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the open mitigation pie with risks stripped
        $array = strip_open_mitigation_pie();

    }

    // Set the defaults
    $current_type = "";
    $grouped_array = array();
    $counter = -1;

    foreach ($array as $row) {

        // If the row name is not the current row
        if ($row['name'] != $current_type) {

            // Increment the counter
            $counter = $counter + 1;

            // Add the value to the grouped array
            $grouped_array[$counter]['label'] = $row['name'];
            $grouped_array[$counter]['data'] = 1;

            // Set the current type
            $current_type = $row['name'];

        } else {

            if (!isset($grouped_array[$counter]['data'])) {
                $grouped_array[$counter]['data'] = 0;
            }

            // Add the value to the grouped array
            $grouped_array[$counter]['label'] = $row['name'];
            $grouped_array[$counter]['data'] = $grouped_array[$counter]['data'] + 1;

        }
    }

    $array = $grouped_array;

    // For each row in the array
    foreach ($array as $index=>$row) {

        // Add the color and url to the labels
        switch($row['label']) {

            case "Planned":
                $array[$index]['color'] = '#51A351';
                $array[$index]['url'] = 'dynamic_risk_report.php?status=2&group=2&sort=0';
                break;
            case "Unplanned":
                $array[$index]['color'] = '#ed3139';
                $array[$index]['url'] = 'dynamic_risk_report.php?status=2&group=2&sort=0';
                break;
            default:
                $array[$index]['color'] = null;
                $array[$index]['url'] = null;
                break;

        }
    }

    // Create the Chart.js pie chart
    create_chartjs_pie_code($title, $element_id, $array);

}

/*****************************
 * FUNCTION: OPEN REVIEW PIE *
 *****************************/
function open_review_pie($title = null) {

    // Create an element id to use for this chart
    $element_id = "open_review_pie";

    // If team separation is not enabled
    if (!team_separation_extra()) {

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("
            SELECT 
                id, 
                CASE 
                    WHEN mgmt_review = 0 THEN 'Unreviewed' 
                    WHEN mgmt_review != 0 THEN 'Reviewed' 
                END AS name 
            FROM 
                `risks` 
            WHERE 
                status != 'Closed' 
            ORDER BY 
                name
        ");

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Otherwise team separation is enabled
    } else {

        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the open review pie with risks stripped
        $array = strip_open_review_pie();

    }

    // Set the defaults
    $current_type = "";
    $grouped_array = array();
    $counter = -1;

    foreach ($array as $row) {

        // If the row name is not the current row
        if ($row['name'] != $current_type) {

            // Increment the counter
            $counter = $counter + 1;

            // Add the value to the grouped array
            $grouped_array[$counter]['label'] = $row['name'];
            $grouped_array[$counter]['data'] = 1;

            // Set the current type
            $current_type = $row['name'];

        } else {

            if (!isset($grouped_array[$counter]['data'])) {
                $grouped_array[$counter]['data'] = 0;
            }

            // Add the value to the grouped array
            $grouped_array[$counter]['label'] = $row['name'];
            $grouped_array[$counter]['data'] = $grouped_array[$counter]['data'] + 1;

        }
    }

    $array = $grouped_array;

    // For each row in the array
    foreach ($array as $index=>$row) {

        // Add the color and url to the labels
        switch($row['label']) {

            case "Reviewed":
                $array[$index]['color'] = '#51A351';
                $array[$index]['url'] = 'dynamic_risk_report.php?status=2&group=2&sort=0';
                break;
            case "Unreviewed":
                $array[$index]['color'] = '#ed3139';
                $array[$index]['url'] = 'dynamic_risk_report.php?status=2&group=2&sort=0';
                break;
            default:
                $array[$index]['color'] = null;
                $array[$index]['url'] = null;
                break;

        }
    }

    // Create the Chart.js pie chart
    create_chartjs_pie_code($title, $element_id, $array);

}

/*****************************
 * FUNCTION: OPEN CLOSED PIE *
 *****************************/
function open_closed_pie($title = null) {

    // Create an element id to use for this chart
    $element_id = "open_closed_pie";

    // If team separation is not enabled
    if (!team_separation_extra()) {

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("
            SELECT 
                id, 
                CASE 
                    WHEN status = \"Closed\" THEN 'Closed' 
                    WHEN status != \"Closed\" THEN 'Open' 
                END AS name 
            FROM 
                `risks` 
            ORDER BY 
                name
        ");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Otherwise team separation is enabled
    } else {

        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the open pie with risks stripped
        $array = strip_open_closed_pie();

    }

    // Set the defaults
    $current_type = "";
    $grouped_array = array();
    $counter = -1;

    foreach ($array as $row) {

        // If the row name is not the current row
        if ($row['name'] != $current_type) {

            // Increment the counter
            $counter = $counter + 1;

            // Add the value to the grouped array
            $grouped_array[$counter]['label'] = $row['name'];
            $grouped_array[$counter]['data'] = 1;

            // Set the current type
            $current_type = $row['name'];
            
        } else {

            if (!isset($grouped_array[$counter]['data'])) {
                $grouped_array[$counter]['data'] = 0;
            }

            // Add the value to the grouped array
            $grouped_array[$counter]['label'] = $row['name'];
            $grouped_array[$counter]['data'] = $grouped_array[$counter]['data'] + 1;

        }
    }

    $array = $grouped_array;

    // For each row in the array
    foreach ($array as $index=>$row) {

        // Add the color and url to the labels
        switch($row['label']) {
            case "Open":
                $array[$index]['color'] = '#ed3139';
                $array[$index]['url'] = 'dynamic_risk_report.php?status=2&group=2&sort=0';
                break;
            case "Closed":
                $array[$index]['color'] = '#51A351';
                $array[$index]['url'] = 'dynamic_risk_report.php?status=2&group=2&sort=0';
                break;
            default:
                $array[$index]['color'] = null;
                $array[$index]['url'] = null;
                break;
        }
    }

    // Create the Chart.js pie chart
    create_chartjs_pie_code($title, $element_id, $array);

}

/************************************
 * FUNCTION: GET MY OPEN TABLE *
 ************************************/
function get_my_open_table() {

    global $lang;
    global $escaper;

    echo "
        <table id='my-risk-datatable' class='risk-datatable table table-bordered table-striped table-condensed'>
            <thead>
                <tr>
                    <th data-name='id' align='left' width='50px' valign='top'>{$escaper->escapeHtml($lang['ID'])}</th>
                    <th data-name='risk_status' align='left' width='150px' valign='top'>{$escaper->escapeHtml($lang['Status'])}</th>
                    <th data-name='subject' align='left' width='300px' valign='top'>{$escaper->escapeHtml($lang['Subject'])}</th>
                    <th data-name='score' align='center' width='80px' valign='top'>{$escaper->escapeHtml($lang['InherentRisk'])}</th>
                    <th data-name='submission_date' align='center' width='150px' valign='top'>{$escaper->escapeHtml($lang['Submitted'])}</th>
                    <th data-name='mitigation_planned' align='center' width='150px' valign='top'>{$escaper->escapeHtml($lang['MitigationPlanned'])}</th>
                    <th data-name='management_review' align='center' width='160px' valign='top'>{$escaper->escapeHtml($lang['ManagementReview'])}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <script>
            $(document).ready(function(){
                var yes_str = '{$escaper->escapeHtml($lang['Yes'])}';
                var no_str = '{$escaper->escapeHtml($lang['No'])}';
                var PASTDUE_str = '{$escaper->escapeHtml($lang['PASTDUE'])}';
                $('#my-risk-datatable thead tr').clone(true).appendTo( '#my-risk-datatable thead');
                $('#my-risk-datatable thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    var data_name = $(this).attr('data-name');
                    if(data_name == 'mitigation_planned') {
                        $(this).html( `<select name='mitigation_planned' class='form-control'><option value=''>--</option><option value='` + yes_str + `'>` + yes_str + `</option><option value='` + no_str + `'>` + no_str + '</option></select>' );
                    } else if(data_name == 'management_review') {
                        $(this).html( `<select name='management_review' class='form-control'><option value=''>--</option><option value='` + yes_str + `'>` + yes_str + `</option><option value='` + no_str + `'>` + no_str + `</option><option value='` + PASTDUE_str + `'>` + PASTDUE_str + '</option></select>' );
                    } else {
                        $(this).html(''); // To clear the title out of the header cell
                        $(`<input type='text' class='form-control'>`).attr('name', title).attr('placeholder', title).appendTo($(this));
                    }
            
                    $( 'input, select', this ).on( 'keyup change', function () {
                        if ( riskTable.column(i).search() !== this.value ) {
                            riskTable.column(i).search( this.value ).draw();
                        }
                    });
                });
                var riskTable = $('#my-risk-datatable').DataTable( {
                    ordering: true,
                    orderCellsTop: true,
                    ajax: {
                        url: BASE_URL + '/api/v2/reports/my_open_risk',
                        type: 'POST',
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
            });
        </script>
    ";
}

/*************************************
 * FUNCTION: GET REVIEW NEEDED TABLE *
 *************************************/
function get_review_needed_table() {

    global $lang;
    global $escaper;

    // Get risks marked as consider for projects
    $risks = get_risks(3);

    // Initialize the reviews array
    $reviews = array();

    // Start with an empty review status;
    $review_status = "";

    foreach ($risks as $key => $risk) {

        $risk_id = $risk['id'];
        $subject = $risk['subject'];
        $status = $risk['status'];
        $calculated_risk = $risk['calculated_risk'];
        $color = get_risk_color($risk['calculated_risk']);
        $risk_level = get_risk_level_name($risk['calculated_risk']);
        $residual_risk_level = get_risk_level_name($risk['residual_risk']);
        $dayssince = $risk['days_open'];

        // If next_review_date_uses setting is Residual Risk.
        if (get_setting('next_review_date_uses') == "ResidualRisk") {
           
            $next_review = next_review($residual_risk_level, $risk_id, $risk['next_review'], false);
            $next_review_html = next_review($residual_risk_level, $risk_id, $risk['next_review']);
        
        // If next_review_date_uses setting is Inherent Risk.
        } else {

            $next_review = next_review($risk_level, $risk_id, $risk['next_review'], false);
            $next_review_html = next_review($risk_level, $risk_id, $risk['next_review']);

        }

        // If we have a new review status and its not a date
        if (($next_review != $review_status) && (!preg_match('/\d{4}/', $next_review))) {

            // If its not the first risk
            if ($review_status != "") {

                // End the previous table
                echo "
                        </tbody>
                    </table>
                ";

            }

            // Set the new review status
            $review_status = $next_review;

            // Start the new table
            echo "
                <table class='table table-bordered table-condensed sortable risk-table table-striped'>
                    <thead>
                        <tr>
                            <th bgcolor='#0088CC' colspan='6'><center>{$escaper->escapeHtml($review_status)}</center></th>
                        </tr>
                        <tr>
                            <th align='left' width='50px'>{$escaper->escapeHtml($lang['ID'])}</th>
                            <th align='left' width='150px'>{$escaper->escapeHtml($lang['Status'])}</th>
                            <th align='left' width='300px'>{$escaper->escapeHtml($lang['Subject'])}</th>
                            <th align='center' width='100px'>{$escaper->escapeHtml($lang['Risk'])}</th>
                            <th align='center' width='100px'>{$escaper->escapeHtml($lang['DaysOpen'])}</th>
                            <th align='center' width='150px'>{$escaper->escapeHtml($lang['NextReviewDate'])}</th>
                        </tr>
                    </thead>
                    <tbody>
            ";
        }

        // If the review status is not a date
        if (!preg_match('/\d{4}/', $next_review)) {
            echo "
                        <tr>
                            <td align='left' width='50px'><a class='open-in-new-tab' href='../management/view.php?id={$escaper->escapeHtml(convert_to_risk_id($risk_id))}'>{$escaper->escapeHtml(convert_to_risk_id($risk_id))}</a></td>
                            <td align='left' width='150px'>{$escaper->escapeHtml($status)}</td>
                            <td align='left' width='300px'>{$escaper->escapeHtml($subject)}</td>
                            <td align='center' class='risk-cell' bgcolor='{$escaper->escapeHtml($color)}' width='100px'>
                                <div class='risk-cell-holder'>{$escaper->escapeHtml($calculated_risk)}<span class='risk-color' style='background-color:{$escaper->escapeCssColor($color)}'></span></div>
                            </td>
                            <td align='center' width='100px'>{$escaper->escapeHtml($dayssince)}</td>
                            <td align='center' width='150px'>{$next_review_html}</td>
                        </tr>
            ";
        }

        // We need to close the table that is open after listing all the risks
        if ($review_status != "" && $key == count($risks) - 1) {
            // End the previous table
            echo "
                        </tbody>
                    </table>
            ";
        }
    }
    echo "
                    <script>
                        $(document).ready(function() {
                            $('.risk-table').each(function(i) {
                                $(this).find('thead tr:eq(1)').clone(true).appendTo($(this).find('thead'));
                                $(this).find('thead tr:eq(2) th').each(function(i) {
                                    var title = $(this).text();
                                    $(this).html(''); // To clear the title out of the header cell
                                    $('<input type=\"text\">').addClass('form-control').attr('name', title).attr('placeholder', title).appendTo($(this));
                                    $( 'input, select', this ).on('keyup change', function() {
                                        if ( riskTable.column(i).search() !== this.value ) {
                                            riskTable.column(i).search( this.value ).draw();
                                        }
                                    });
                                });
                                var riskTable = $(this).DataTable({
                                    paging: false,
                                    orderCellsTop: true,
                                    fixedHeader: true,
                                    serverSide: false
                                });
                            });

                        });
                    </script>
    ";
}

/************************************
 * FUNCTION: GET HIGH RISK REPORT TABLE *
 ************************************/
function get_high_risk_report_table()
{
    global $lang;
    global $escaper;
    global $score_used;

    echo "
        <table id='high-risk-datatable' width='100%' class='risk-datatable table table-bordered table-striped table-condensed'>
            <thead>
                <tr>
                    <th data-name='id' align='left' width='50px' valign='top'>{$escaper->escapeHtml($lang['ID'])}</th>
                    <th data-name='risk_status' align='left' width='150px' valign='top'>{$escaper->escapeHtml($lang['Status'])}</th>
                    <th data-name='subject' align='left' width='300px' valign='top'>{$escaper->escapeHtml($lang['Subject'])}</th>
                    <th data-name='score' align='center' width='65px' valign='top'>{$escaper->escapeHtml($lang['InherentRisk'])}</th>
                    <th data-name='submission_date' align='center' width='100px' valign='top'>{$escaper->escapeHtml($lang['Submitted'])}</th>
                    <th data-name='mitigation_planned' align='center' width='150px' valign='top'>{$escaper->escapeHtml($lang['MitigationPlanned'])}</th>
                    <th data-name='management_review' align='center' width='150px' valign='top'>{$escaper->escapeHtml($lang['ManagementReview'])}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <script>
            $(function() {
                var yes_str = '{$escaper->escapeHtml($lang['Yes'])}';
                var no_str = '{$escaper->escapeHtml($lang['No'])}';
                var PASTDUE_str = '{$escaper->escapeHtml($lang['PASTDUE'])}';
                $('#high-risk-datatable thead tr').clone(true).appendTo( '#high-risk-datatable thead' );
                $('#high-risk-datatable thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    var data_name = $(this).attr('data-name');
                    if(data_name == 'mitigation_planned') {
                        $(this).html( '<select name=\"mitigation_planned\" class=\"form-control\"><option value=\"\">--</option><option value=\"' + yes_str+ '\">' + yes_str + '</option><option value=\"' + no_str + '\">' + no_str + '</option></select>');
                    } else if(data_name == 'management_review') {
                        $(this).html( '<select name=\"management_review\" class=\"form-control\"><option value=\"\">--</option><option value=\"'+yes_str+'\">' + yes_str + '</option><option value=\"'+no_str+'\">' + no_str + '</option><option value=\"' + PASTDUE_str + '\">' + PASTDUE_str + '</option></select>');
                    } else {
                        $(this).html(''); // To clear the title out of the header cell
                        $('<input type=\"text\" class=\"form-control\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    }
            
                    $( 'input, select', this ).on( 'keyup change', function () {
                        if ( datatableInstance.column(i).search() !== this.value ) {
                            datatableInstance.column(i).search( this.value ).draw();
                        }
                    } );
                } );
                var datatableInstance = $('#high-risk-datatable').DataTable({
                    ordering: true,
                    orderCellsTop: true,
                    createdRow: function(row, data, index){
                        var background = $('.background-class', $(row)).data('background');
                        $(row).find('td').addClass(background)
                    },
                    order: [[3, 'DESC']],
                    ajax: {
                        url: BASE_URL + '/api/v2/reports/high_risk?score_used={$score_used}',
                        type: 'POST',
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
            });
        </script>
    ";
}

/************************************
 * FUNCTION: GET MY OPEN TABLE *
 ************************************/
function get_recent_commented_table() {

    global $lang;
    global $escaper;

    echo "
        <table id='risk-datatable' width='100%' class='risk-datatable table table-bordered table-striped table-condensed'>
            <thead>
                <tr>
                    <th data-name='id' align='left' width='50px' valign='top'>{$escaper->escapeHtml($lang['ID'])}</th>
                    <th data-name='risk_status' align='left' width='150px' valign='top'>{$escaper->escapeHtml($lang['Status'])}</th>
                    <th data-name='subject' align='left' width='300px' valign='top'>{$escaper->escapeHtml($lang['Subject'])}</th>
                    <th data-name='score' align='center' width='80px' valign='top'>{$escaper->escapeHtml($lang['InherentRisk'])}</th>
                    <th data-name='residual_risk' align='center' width='80px' valign='top'>{$escaper->escapeHtml($lang['ResidualRisk'])}</th>
                    <th data-name='comment_date' align='center' width='150px' valign='top'>{$escaper->escapeHtml($lang['CommentDate'])}</th>
                    <th data-name='comment' align='center' width='150px' valign='top'>{$escaper->escapeHtml($lang['Comment'])}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

        <script>
            $(document).ready(function(){
                $('#risk-datatable thead tr').clone(true).appendTo( '#risk-datatable thead');
                $('#risk-datatable thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    var data_name = $(this).attr('data-name');
                    if(data_name == 'mitigation_planned') {
                        $(this).html( `<select name='mitigation_planned' class='form-control'><option value=''>--</option><option value='yes'>Yes</option><option value='no'>No</option></select>` );
                    } else if(data_name == 'management_review') {
                        $(this).html( `<select name='management_review' class='form-control'><option value=''>--</option><option value='yes'>Yes</option><option value='no'>No</option></select>`);
                    } else {
                        $(this).html(''); // To clear the title out of the header cell
                        $(`<input type='text' class='form-control'>`).attr('name', title).attr('placeholder', title).appendTo($(this));
                    }
            
                    $( 'input, select', this ).on( 'keyup change', function () {
                        if ( riskTable.column(i).search() !== this.value ) {
                            riskTable.column(i).search( this.value ).draw();
                        }
                    });
                });
                var riskTable = $('#risk-datatable').DataTable( {
                    ordering: true,
                    orderCellsTop: true,
                    ajax: {
                        url: BASE_URL + '/api/v2/reports/recent_commented_risk',
                        type: 'POST',
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
            });
        </script>
    ";
    
}

/************************************
 * FUNCTION: RISKS AND ASSETS TABLE *
 ************************************/
function risks_and_assets_table($report, $sort_by, $asset_tags_in_array, $projects_in_array) {

    global $lang;
    global $escaper;

    // If team separation is enabled
    if (team_separation_extra()) {

        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Just setting it true so we can later remove the risks
        // the user doesn't have permission to.
        // It's because all grouping type has different logic where its risk id is stored
        $separation = true;

    } else {
        $separation = false;
    }

    $risk_levels = get_risk_levels();

    $rows = get_risks_and_assets_rows($report, $sort_by, $asset_tags_in_array, $projects_in_array);

    // If risks by asset
    if ($report == 0) {
        foreach ($rows as $gr_id => $group) {

            if ($separation) {
                $tmp = [];
                
                foreach ($group as $key => $item) {
                    if (extra_grant_access($_SESSION['uid'], (int)$item['risk_id'] + 1000)) {
                        $tmp[] = $item;
                    }
                }

                if (empty($tmp)) {
                    continue;
                }

                $group = $tmp;
            }
            
            $total_calculated_risk = 0;
            $total_residual_risk = 0;
            $array_residual_risk = [];

            $risk_html = "";
            foreach ($group as $row) {

                // Get the variables for the row
                $risk_id = (int)$row['risk_id'];
                $risk_location = (isset($row['risk_location']) ? $row['risk_location'] : "N/A");
                $risk_teams = (isset($row['risk_teams']) ? $row['risk_teams'] : "N/A");
                $status = $row['status'];
                $subject = try_decrypt($row['subject']);
                $calculated_risk = $row['calculated_risk'];
                $color1 = get_risk_color_from_levels($calculated_risk, $risk_levels);
                $dayssince = $row['days_open'];
                $residual_risk = round($row['calculated_risk'] * (100-$row['mitigation_percent']) / 100, 2);
                $color2 = get_risk_color_from_levels($residual_risk, $risk_levels);
                $mitigation_percent = $row['mitigation_percent'];

                // Get the variables for total
                $total_calculated_risk += $row['calculated_risk'];
                $total_residual_risk += $residual_risk;
                $array_residual_risk[] = $residual_risk;

                // Display the individual asset/asset group information
                $risk_html .= "
                    <tr>
                        <td style='width: 100px; min-width: 100px;' align='left'>
                            <a class='open-in-new-tab' target='_blank' href='../management/view.php?id=" . $escaper->escapeHtml(convert_to_risk_id($risk_id)) . "'>" . $escaper->escapeHtml(convert_to_risk_id($risk_id)) . "</a>
                        </td>
                        <td align='left' width='150px'>" . $escaper->escapeHtml($status) . "</td>
                        <td align='left' width='300px'>" . $escaper->escapeHtml($subject) . "</td>
                        <td align='left' width='200px'>" . $escaper->escapeHtml($risk_location) . "</td>
                        <td align='left' width='200px'>" . $escaper->escapeHtml($risk_teams) . "</td>
                        <td align='center' class='risk-cell' bgcolor='" . $escaper->escapeHtml($color1) . "' width='100px'>
                            <div class='risk-cell-holder'>" . 
                                $escaper->escapeHtml($calculated_risk) . "<span class='risk-color' style='background-color:" . $escaper->escapeCssColor($color1) . "'></span>
                            </div>
                        </td>
                        <td align='center' class='risk-cell' bgcolor='" . $escaper->escapeHtml($color2) . "' width='100px'>
                            <div class='risk-cell-holder'>" . 
                                $escaper->escapeHtml($residual_risk) . "<span class='risk-color' style='background-color:" . $escaper->escapeCssColor($color2) . "'></span>
                            </div>
                        </td>
                        <td align='center' width='100px'>" . $escaper->escapeHtml($mitigation_percent) . " %</td>
                        <td align='center' width='100px'>" . $escaper->escapeHtml($dayssince) . "</td>
                    </tr>
                ";
            }

            $average_calculated_risk = round($total_calculated_risk / count($group),2);
            $average_residual_risk = round($total_residual_risk / count($group),2);

            preg_match('/^([\d]+)_(group|asset)$/', $gr_id, $matches);
            list(, $id, $type) = $matches;

            $name = $type == 'asset' ? try_decrypt($group[0]['name']) : $group[0]['name'];
            $calculated_risk = $group[0]['calculated_risk'];
            $color = get_risk_color_from_levels($calculated_risk, $risk_levels);
            $tags = $group[0]['tags'];
            
            // Display the table header
            echo "
                <table class='table table-bordered table-condensed sortable mb-2'>
                    <thead>
                        <tr>
            ";
            if ($type == 'asset') {
                $asset_value = $group[0]['asset_value'];
                $asset_location = isset($group[0]['asset_location']) ? $group[0]['asset_location'] : "N/A";
                $asset_teams = isset($group[0]['asset_teams']) ? $group[0]['asset_teams'] : "N/A";
                echo "
                            <th style='background-color: " . $escaper->escapeCssColor($color) . "' colspan='9'>
                                <center>
                                    " . $escaper->escapeHtml($lang['AssetName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($name) . "<br />
                                    " . $escaper->escapeHtml($lang['AssetTags']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($tags) . "<br />
                                    " . $escaper->escapeHtml($lang['AssetValue']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml(get_asset_value_by_id($asset_value)) . "<br />
                                    " . $escaper->escapeHtml($lang['HighestInherentRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($calculated_risk) ."<br />
                                    " . $escaper->escapeHtml($lang['AverageInherentRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($average_calculated_risk) ."<br />
                                    " . $escaper->escapeHtml($lang['HighestResidualRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml(max($array_residual_risk)) ."<br />
                                    " . $escaper->escapeHtml($lang['AverageResidualRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($average_residual_risk) ."<br />
                                    " . $escaper->escapeHtml($lang['AssetSiteLocation']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_location) . "<br />
                                    " . $escaper->escapeHtml($lang['AssetTeams']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_teams) . "<br />
                                </center>
                            </th>
                ";
            } else {
                $max_value = $group[0]['max_value'];
                echo "
                            <th style='background-color: " .$escaper->escapeCssColor($color). "' colspan='9'>
                                <center>
                                    " . $escaper->escapeHtml($lang['AssetGroupName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($name) . "<br />
                                    " . $escaper->escapeHtml($lang['AssetTags']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($tags) . "<br />
                                    " . $escaper->escapeHtml($lang['GroupMaximumQuantitativeLoss']) . ":&nbsp;&nbsp;$" . $escaper->escapeHtml(number_format($max_value)) . "<br />
                                    " . $escaper->escapeHtml($lang['HighestInherentRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($calculated_risk) ."<br />
                                    " . $escaper->escapeHtml($lang['AverageInherentRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($average_calculated_risk) ."<br />
                                    " . $escaper->escapeHtml($lang['HighestResidualRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml(max($array_residual_risk)) ."<br />
                                    " . $escaper->escapeHtml($lang['AverageResidualRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($average_residual_risk) ."<br />
                                </center>
                            </th>
                ";
            }
            echo "
                        </tr>
                        <tr>
                            <th style='width: 100px; min-width: 100px;' align='left'>" . $escaper->escapeHtml($lang['ID']) . "</th>
                            <th align='left' width='150px'>" . $escaper->escapeHtml($lang['Status']) . "</th>
                            <th align='left' width='300px'>" . $escaper->escapeHtml($lang['Subject']) . "</th>
                            <th align='left' width='50px'>" . $escaper->escapeHtml($lang['SiteLocation']) . "</th>
                            <th align='left' width='50px'>" . $escaper->escapeHtml($lang['Teams']) . "</th>
                            <th align='left' width='100px'>" . $escaper->escapeHtml($lang['InherentRisk']) . "</th>
                            <th align='left' width='100px'>" . $escaper->escapeHtml($lang['ResidualRisk']) . "</th>
                            <th align='left' width='100px'>" . $escaper->escapeHtml($lang['MitigationPercent']) . "</th>
                            <th align='left' width='100px'>" . $escaper->escapeHtml($lang['DaysOpen']) . "</th>
                        </tr>
                    </thead>
                    <tbody>" . 
                        $risk_html . "
                    </tbody>
                </table>
            ";
        }
        
    // If assets by risk
    } elseif ($report == 1) {
        foreach ($rows as $risk_id => $group) {

            $status = $group[0]['status'];
            $subject = try_decrypt($group[0]['subject']);
            $calculated_risk = $group[0]['calculated_risk'];

            // Get the risk's asset valuation
            $asset_valuation = asset_valuation_for_risk_id($risk_id);

            // Get the risk color
            $color = get_risk_color_from_levels($calculated_risk, $risk_levels);
            $level_name = get_risk_level_name_from_levels($calculated_risk, $risk_levels);

            // Display the table header
            echo "
                <table class='table table-bordered table-condensed sortable mb-2'>
                    <thead>
                        <tr>
                            <th style='background-color:" . $escaper->escapeCssColor($color) . "' bgcolor='" . $escaper->escapeHtml($color) . "' colspan='7'>
                                <center>
                                    <font color='#000000'>
                                        " . $escaper->escapeHtml($lang['RiskId']) . ":&nbsp;&nbsp;<a class='open-in-new-tab' target='_blank' href='../management/view.php?id=" . $escaper->escapeHtml(convert_to_risk_id($risk_id)) . "' style='color:#000000'>" . $escaper->escapeHtml(convert_to_risk_id($risk_id)) . "</a>
                                        <br />" . $escaper->escapeHtml($lang['Subject']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($subject) . "
                                        <br />" . $escaper->escapeHtml($lang['InherentRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($calculated_risk) . "&nbsp;&nbsp;(" . $escaper->escapeHtml($level_name) . ")
                                    </font>
                                </center>
                            </th>
                        </tr>
                        <tr>
                            <th align='left' width='30%'>" . $escaper->escapeHtml($lang['AssetName']) . "</th>
                            <th align='left' width='10%'>" . $escaper->escapeHtml($lang['IPAddress']) . "</th>
                            <th align='left' width='12%'>" . $escaper->escapeHtml($lang['SiteLocation']) . "</th>
                            <th align='left' width='12%'>" . $escaper->escapeHtml($lang['Teams']) . "</th>
                            <th align='left' width='12%'>" . $escaper->escapeHtml($lang['AssetTags']) . "</th>
                            <th align='left' width='12%'>" . $escaper->escapeHtml($lang['AssetGroups']) . "</th>
                            <th align='left' width='12%'>" . $escaper->escapeHtml($lang['AssetValuation']) . "</th>
                        </tr>
                    </thead>
                    <tbody>
            ";

            foreach ($group as $row) {
                // Get the variables for the row
                $asset_id = (int)$row['asset_id'];
                $asset_ip = (isset($row['asset_ip']) ? try_decrypt($row['asset_ip']) : "N/A");
                $asset_ip = ($asset_ip != "" ? $asset_ip : "N/A");
                $asset_name = (isset($row['asset_name']) ? try_decrypt($row['asset_name']) : "N/A");
                $asset_value = $row['asset_value'];
                $asset_location = isset($row['asset_location']) ? $row['asset_location'] : "N/A";
                $asset_teams = isset($row['asset_teams']) ? $row['asset_teams'] : "N/A";
                $tags = isset($row['tags']) ? $row['tags'] : "N/A";
                $asset_groups = isset($row['asset_groups']) ? $row['asset_groups'] : "N/A";

                // Display the individual asset information
                echo "
                        <tr>
                            <td align='left'>" . $escaper->escapeHtml($asset_name) . "</td>
                            <td align='left'>" . $escaper->escapeHtml($asset_ip) . "</td>
                            <td align='left'>" . $escaper->escapeHtml($asset_location) . "</td>
                            <td align='left'>" . $escaper->escapeHtml($asset_teams) . "</td>
                            <td align='left'>" . $escaper->escapeHtml($tags) . "</td>
                            <td align='left'>" . $escaper->escapeHtml($asset_groups) . "</td>
                            <td align='left'>" . $escaper->escapeHtml(get_asset_value_by_id($asset_value)) . "</td>
                        </tr>
                ";
            }

            echo "
                        <tr>
                            <td style='background-color:" . $escaper->escapeCssColor($color) . "' bgcolor='" . $escaper->escapeHtml($color) . "' colspan='7'></td>
                        </tr>
                        <tr>
                            <td style='background-color: lightgrey' align='left' width='50px' colspan='6'><b>" . $escaper->escapeHtml($lang['MaximumQuantitativeLoss']) . "</b></td>
                            <td style='background-color: lightgrey' align='left' width='50px'><b>$" . $escaper->escapeHtml(number_format($asset_valuation)) . "</b></td>
                        </tr>
                    </tbody>
                </table>
            ";
        }
    }
}
/************************************************
 * FUNCTION: RETURN RISKS AND ASSETS REPORT SQL *
 ************************************************/
function get_risks_and_assets_rows($report, $sort_by, $asset_tags_in_array, $projects_in_array)
{
    global $lang;
    if($asset_tags_in_array == "all") {
        $tags = get_options_from_table("asset_tags");
        $asset_tags_in_array = array_map(function($tag){ return $tag["value"];}, $tags);
        $asset_tags_in_array[] = "-1";
    }
    $asset_tags = implode(",", $asset_tags_in_array);

    $projects = implode(",", $projects_in_array);

    // If team separation is enabled
    if (team_separation_extra())
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Just setting it true so we can later remove the risks
        // the user doesn't have permission to.
        // It's because all grouping type has different logic where its risk id is stored
        $separation = true;
    } else {
        $separation = false;
    }
    // Query the database
    $db = db_open();

    if($report == 0){
        $where_in_string = "WHERE 1";
        $bind_params = [];
        if($asset_tags){
            $wheres = [];
            $wheres[] = " FIND_IN_SET(tg.id, :asset_tags) ";
            $bind_params[":asset_tags"] = $asset_tags;
            if(in_array(-1, $asset_tags_in_array)){
                $wheres[] = " tg.id IS NULL ";
            }
            $where_in_string .= " AND (" . implode(" OR", $wheres) . " ) ";
        }
        if($projects){
            $wheres = [];
            $wheres[] = " FIND_IN_SET(u.project_id, :projects) ";
            $bind_params[":projects"] = $projects;
            if(in_array(-1, $projects_in_array)){
                $wheres[] = " p.value IS NULL ";
            }
            $where_in_string .= " AND (" . implode(" OR", $wheres) . " ) ";
        }
        $sql = "
            SELECT
                CONCAT(u.id, '_', u._type) AS gr_id,
                GROUP_CONCAT(DISTINCT `t`.`name` SEPARATOR ', ') AS asset_teams,
                `loc`.`name` AS asset_location,
                rsk_loc.name AS risk_location,
                GROUP_CONCAT(DISTINCT rsk_team.name SEPARATOR ', ') AS risk_teams,
                GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag ASC SEPARATOR ',') as tags,
                IF(IFNULL(m.mitigation_percent, 0) > 0, m.mitigation_percent, IFNULL(MAX(fc.mitigation_percent), 0)) AS mitigation_percent,
                p.name AS project, 
                p.status AS project_status_value,
                CASE p.status
                    WHEN 1 THEN '".$lang['ActiveProjects']."'
                    WHEN 2 THEN '".$lang['OnHoldProjects']."'
                    WHEN 3 THEN '".$lang['CompletedProjects']."'
                    WHEN 4 THEN '".$lang['CanceledProjects']."'
                END project_status,
                u.*
            FROM (
                SELECT
                    a.id AS id,
                    r.id AS risk_id,
                    a.id AS asset_id,
                    a.name AS name,
                    a.name AS asset_name,
                    a.value AS asset_value,
                    av.max_value AS max_value,
                    a.location AS asst_location,
                    a.teams AS asst_teams,
                    r.status,
                    r.subject,
                    r.submission_date,
                    r.project_id,
                    rs.calculated_risk,
                    rr.next_review,
                    DATEDIFF(IF(r.status != 'Closed', NOW(), o.closure_date) , r.submission_date) days_open,
                    'asset' AS _type
                FROM
                    risks_to_assets rta
                    LEFT JOIN assets a ON rta.asset_id = a.id
                    INNER JOIN `asset_values` av ON `a`.`value` = `av`.`id`
                    LEFT JOIN risks r ON rta.risk_id = r.id
                    LEFT JOIN closures o ON r.close_id = o.id
                    LEFT JOIN risk_scoring rs ON r.id = rs.id
                    LEFT JOIN mgmt_reviews rr ON r.mgmt_review = rr.id
                WHERE
                    status != 'Closed'
                    UNION ALL
                SELECT
                    a.id AS id,
                    r.id AS risk_id,
                    a.id AS asset_id,
                    a.name AS name,
                    a.name AS asset_name,
                    a.value AS asset_value,
                    av.max_value AS max_value,
                    a.location AS asst_location,
                    a.teams AS asst_teams,
                    r.status,
                    r.subject,
                    r.submission_date,
                    r.project_id,
                    rs.calculated_risk,
                    rr.next_review,
                    DATEDIFF(IF(r.status != 'Closed', NOW(), o.closure_date), r.submission_date) days_open,
                    'asset' AS _type
                FROM
                    risks_to_asset_groups rtag
                    INNER JOIN asset_groups ag ON ag.id = rtag.asset_group_id
                    INNER JOIN assets_asset_groups aag ON aag.asset_group_id = ag.id
                    INNER JOIN assets a ON aag.asset_id = a.id
                    INNER JOIN asset_values av ON a.value = av.id
                    LEFT JOIN risks r ON rtag.risk_id = r.id
                    LEFT JOIN closures o ON r.close_id = o.id
                    LEFT JOIN risk_scoring rs ON r.id = rs.id
                    LEFT JOIN mgmt_reviews rr ON r.mgmt_review = rr.id
                WHERE
                    r.status != 'Closed'
                UNION ALL
                SELECT
                    ag.id AS id,
                    r.id AS risk_id,
                    a.id AS asset_id,
                    ag.name AS name,
                    a.name AS asset_name,
                    null AS asset_value,
                    SUM(`av`.`max_value`) as max_value,
                    null AS asst_location,
                    null AS asst_teams,
                    r.status,
                    r.subject,
                    r.submission_date,
                    r.project_id,
                    rs.calculated_risk,
                    rr.next_review,
                    DATEDIFF(IF(r.status != 'Closed', NOW(), o.closure_date) , r.submission_date) days_open,
                    'group' AS _type
                FROM
                    risks_to_asset_groups rtag
                    LEFT JOIN asset_groups ag ON ag.id = rtag.asset_group_id
                    LEFT JOIN risks r ON rtag.risk_id = r.id
                    LEFT JOIN closures o ON r.close_id = o.id
                    LEFT JOIN risk_scoring rs ON r.id = rs.id
                    LEFT JOIN mgmt_reviews rr ON r.mgmt_review = rr.id
                    LEFT JOIN `assets_asset_groups` aag on aag.asset_group_id = ag.id
                    INNER JOIN `assets` a ON `aag`.`asset_id` = `a`.`id`
                    INNER JOIN `asset_values` av ON `a`.`value` = `av`.`id`
                WHERE
                    status != 'Closed'
                GROUP BY
                    name, risk_id
            ) u
                LEFT JOIN `location` loc ON `loc`.`value` = `u`.`asst_location`
                LEFT JOIN `team` t ON FIND_IN_SET(`t`.`value`, `u`.`asst_teams`)
                LEFT JOIN risk_to_location rtl on u.risk_id = rtl.risk_id
                LEFT JOIN location rsk_loc on rtl.location_id = rsk_loc.value
                LEFT JOIN risk_to_team rtt on u.risk_id = rtt.risk_id
                LEFT JOIN team rsk_team on rtt.team_id = rsk_team.value
                LEFT JOIN tags_taggees tt ON tt.taggee_id = u.asset_id AND tt.type = 'asset'
                LEFT JOIN tags tg on tg.id = tt.tag_id
                LEFT JOIN mitigations m ON u.risk_id=m.risk_id
                LEFT JOIN mitigation_to_controls mtc ON m.id=mtc.mitigation_id
                LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                LEFT JOIN projects p FORCE INDEX(PRIMARY) ON u.project_id = p.value
        {$where_in_string}
            GROUP BY
                gr_id, u.risk_id
            ORDER BY
                u.max_value DESC,
                u.name,
                u.calculated_risk DESC,
                u.risk_id;
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($bind_params);

        // Store the results in the rows array
        $rows = $stmt->fetchAll(PDO::FETCH_GROUP);

        if($sort_by == 0){
            uasort($rows, function($a, $b) {
                 return strcmp(try_decrypt($a[0]['asset_name']), try_decrypt($b[0]['asset_name']));
            });
        } else {
            uasort($rows, function($a, $b) {
                 return ($a[0]['calculated_risk'] > $b[0]['calculated_risk'])?-1:1;
            });
        }
    } else {
        $where_in_string = $separation ? get_user_teams_query("rsk", true) : " WHERE 1 ";

        $bind_params = [];
        if($asset_tags){
            $wheres = [];
            $wheres[] = " FIND_IN_SET(tg.id, :asset_tags) ";
            $bind_params[":asset_tags"] = $asset_tags;
            if(in_array(-1, $asset_tags_in_array)){
                $wheres[] = " tg.id IS NULL ";
            }
            $where_in_string .= " AND (" . implode(" OR", $wheres) . " ) ";
        }
        if($projects){
            $wheres = [];
            $wheres[] = " FIND_IN_SET(u.project_id, :projects) ";
            $bind_params[":projects"] = $projects;
            if(in_array(-1, $projects_in_array)){
                $wheres[] = " p.value IS NULL ";
            }
            $where_in_string .= " AND (" . implode(" OR", $wheres) . " ) ";
        }

        $sql = "
            SELECT
                `u`.`risk_id`,
                `u`.`asset_id`,
                `u`.`asset_ip`,
                `u`.`asset_name`,
                `u`.`asset_value`,
                `loc`.`name` as asset_location,
                `u`.`status`,
                `u`.`subject`,
                `u`.`calculated_risk`,
                `u`.`calculated_risk`,
                p.name AS project, 
                p.status AS project_status_value,
                CASE p.status
                    WHEN 1 THEN '".$lang['ActiveProjects']."'
                    WHEN 2 THEN '".$lang['OnHoldProjects']."'
                    WHEN 3 THEN '".$lang['CompletedProjects']."'
                    WHEN 4 THEN '".$lang['CanceledProjects']."'
                END project_status,
                GROUP_CONCAT(DISTINCT `t`.`name` SEPARATOR ', ') AS asset_teams,
                GROUP_CONCAT(DISTINCT `ag`.`name` SEPARATOR ', ') AS asset_groups,
                GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag ASC SEPARATOR ',') as tags
            from (
                SELECT
                    `r`.`id` as risk_id,
                    `asst`.`id` AS asset_id,
                    `asst`.`ip` AS asset_ip,
                    `asst`.`name` AS asset_name,
                    `asst`.`value` AS asset_value,
                    `asst`.`location` AS asst_location,
                    `asst`.`teams` AS asst_teams,
                    `r`.`status`,
                    `r`.`subject`,
                    `r`.`project_id`,
                    `rs`.`calculated_risk`
                FROM
                    `risks_to_assets` rta
                    INNER JOIN `assets` asst ON `rta`.`asset_id` = `asst`.`id`
                    INNER JOIN `risks` r ON `rta`.`risk_id` = `r`.`id`
                    INNER JOIN `risk_scoring` rs ON `r`.`id` = `rs`.`id`
                WHERE
                    `r`.`status` != 'Closed'
                UNION ALL
                SELECT
                    `r`.`id` as risk_id,
                    `asst`.`id` AS asset_id,
                    `asst`.`ip` AS asset_ip,
                    `asst`.`name` AS asset_name,
                    `asst`.`value` AS asset_value,
                    `asst`.`location` AS asst_location,
                    `asst`.`teams` AS asst_teams,
                    `r`.`status`,
                    `r`.`subject`,
                    `r`.`project_id`,
                    `rs`.`calculated_risk`
                FROM
                    `risks_to_asset_groups` rtag
                    INNER JOIN `assets_asset_groups` aag ON `aag`.`asset_group_id` = `rtag`.`asset_group_id`
                    LEFT JOIN `assets` asst ON `aag`.`asset_id` = `asst`.`id`
                    LEFT JOIN `risks` r ON `rtag`.`risk_id` = `r`.`id`
                    LEFT JOIN `risk_scoring` rs ON `r`.`id` = `rs`.`id`
                WHERE
                    `r`.`status` != 'Closed'
                ) u
                INNER JOIN `risks` rsk ON `rsk`.`id` = `u`.`risk_id`
                LEFT JOIN `location` loc ON `loc`.`value` = `u`.`asst_location`
                LEFT JOIN `team` t ON FIND_IN_SET(`t`.`value`, `u`.`asst_teams`)
                LEFT JOIN `risk_to_team` rtt ON u.risk_id = `rtt`.`risk_id`
                LEFT JOIN `risk_to_additional_stakeholder` rtas ON u.risk_id = `rtas`.`risk_id`
                LEFT JOIN `assets_asset_groups` aag ON `aag`.`asset_id` = `u`.`asset_id`
                LEFT JOIN `asset_groups` ag ON `ag`.`id` = `aag`.`asset_group_id`
                LEFT JOIN `tags_taggees` tt ON tt.taggee_id = `u`.`asset_id` AND tt.type = 'asset'
                LEFT JOIN `tags` tg on tg.id = tt.tag_id
                LEFT JOIN projects p FORCE INDEX(PRIMARY) ON u.project_id = p.value
            {$where_in_string}
            GROUP BY
                `u`.`risk_id`, `u`.`asset_id`
            ORDER BY
                `u`.`calculated_risk` DESC,
                `u`.`risk_id`,
                `u`.`asset_value` DESC,
                `u`.`asset_id`;
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($bind_params);

        // Store the results in the rows array
        $rows = $stmt->fetchAll(PDO::FETCH_GROUP);
    }

    // Close the database
    db_close($db);
    return $rows;
}
/************************************
 * FUNCTION: RISKS AND ISSUES TABLE *
 ************************************/
function risks_and_issues_table($risk_tags, $start_date, $end_date) {

    global $lang;
    global $escaper;

    echo "
        <div>
            <div class='d-flex align-items-center mb-3'>
                <label class='mb-0' style='width: 100px;'>{$escaper->escapeHtml($lang['Trend'])} :</label>
                <span>{$escaper->escapeHtml($lang['Increasing'])}</span><span class='m-r-20 m-l-10'>&#8593;</span>
                <span>{$escaper->escapeHtml($lang['Decreasing'])}</span><span class='m-r-20 m-l-10'>&#8595;</span>
                <span>{$escaper->escapeHtml($lang['NoChange'])}</span><span class='m-r-20 m-l-10'>&#8596;</span>
            </div>
            <div class='d-flex align-items-center mb-3'>
                <label class='mb-0' style='width: 100px;'>{$escaper->escapeHtml($lang['Status'])} :</label>
    ";

    $risk_levels = get_risk_levels();

    foreach (array_reverse($risk_levels) as $level) {
        echo "
                <span class='risk-color1' style='width:20px; height: 20px; position: relative; display:block; float:left; border: 1px solid; background-color:{$escaper->escapeCssColor($level['color'])}'></span>
                <span class='m-r-20 m-l-10'>({$escaper->escapeHtml($level['display_name'])})</span>
        ";
    }
    echo "
                <span class='risk-color1' style='width:20px; height: 20px; position: relative; display:block; float:left; border: 1px solid; background-color: white'></span>
                <span class='m-r-20 m-l-10'>({$escaper->escapeHtml($lang['Insignificant'])})</span>
            </div>
        </div>
    ";

    $rows = get_risks_and_issues_rows($risk_tags, $start_date, $end_date);

    echo "
        <table class='table table-bordered table-condensed mb-0' style='table-layout:fixed;'>
            <thead>
                <tr>
                    <th width='10%'>{$escaper->escapeHtml($lang['Category'])}</th>
                    <th width='8%'>{$escaper->escapeHtml($lang['Status'])}</th>
                    <th width='8%'>{$escaper->escapeHtml($lang['Trend'])}</th>
                    <th width='74%'>{$escaper->escapeHtml($lang['Details'])}</th>
                </tr>
            </thead>
            <tbody>
    ";

    $categories = [];
    foreach ($rows as $risk) {
        $categories[$risk['category']][] = $risk;
    }

    foreach ($rows as $index => $risk) {
        $color = get_risk_color($risk['residual_risk']);
        $risk_id = $risk['id'] + 1000;
        $trend = "";
        if ($risk['residual_risk_start'] == $risk['residual_risk_end']) {
            $trend = "&#8596;";
        } else if ($risk['residual_risk_start'] < $risk['residual_risk_end']) {
            $trend = "&#8593;";
        } else {
            $trend = "&#8595;";
        }
        $details = "
                        <a class='open-in-new-tab font-22' href='../management/view.php?id={$escaper->escapeHtml($risk_id)}' target='_blank'>{$risk_id} : {$escaper->escapeHtml(try_decrypt($risk['subject']))}</a>
                        <ul>
        ";
        if ($risk['assessment']) {
            $details .= "
                            <li>" . $escaper->purifyHtml(try_decrypt($risk['assessment'])) . "</li>
            ";
        }
        if ($risk['notes']) {
            $details .= "
                            <li>" . $escaper->purifyHtml(try_decrypt($risk['notes'])) . "</li>
            ";
        }

        $comments = get_comments($risk_id, false);
        // @phan-suppress-next-line PhanTypeMismatchArgumentInternal -- get_comments($_, false) returns array; Phan's union return type includes 'true' from the html branch
        if (count($comments) > 0) {
            foreach ($comments as $comment) {
                $details .= "
                            <li>" . format_date($comment['date']) . " [ {$escaper->escapeHtml($comment['name'])} ] : " . $escaper->purifyHtml(try_decrypt($comment['comment'])) . "</li>
                ";
            }
        }
        $details .= "
                        </ul>
        ";
        echo "
                <tr>
        ";
        if ($index == 0 || $rows[$index-1]['category'] != $risk['category']) {
            echo "
                    <td rowspan='" . count($categories[$risk['category']]) . "'>{$escaper->escapeHtml($risk['category_name'])}</td>
            ";
        } 
        // @phan-suppress-next-line SecurityCheck-XSS -- $trend is hardcoded HTML entities; $color is escaped; $details values are escaped/purified
        echo "
                    <td style='background-color:{$escaper->escapeCssColor($color)}'></td>
                    <td style='text-align:center; font-weight:bold; font-size: 30px;'>{$trend}</td>
                    <td style='word-wrap: break-word;'>{$details}</td>
                </tr>
        ";
    }
    echo "
            </tbody>
        </table>
    ";
}

/************************************************
 * FUNCTION: RETURN RISKS AND ISSUES REPORT SQL *
 ************************************************/
function get_risks_and_issues_rows($risk_tags_in_array, $start_date, $end_date)
{
    global $lang;
    if($risk_tags_in_array == "all") {
        $tags = get_options_from_table("asset_tags");
        $risk_tags_in_array = array_map(function($tag){ return $tag["value"];}, $tags);
        $risk_tags_in_array[] = "-1";
    }
    $risk_tags = implode(",", $risk_tags_in_array);

    // Query the database
    $db = db_open();

    $where_in_string = "WHERE 1";
    $bind_params = [];
    if($risk_tags){
        $wheres = [];
        $wheres[] = " FIND_IN_SET(t.tag_id, :risk_tags) ";
        $bind_params[":risk_tags"] = $risk_tags;
        if(in_array(-1, $risk_tags_in_array)){
            $wheres[] = " t.tag_id IS NULL ";
        }
        $where_in_string .= " AND (" . implode(" OR", $wheres) . " ) ";
    }
    $start_date = date("Y-m-d", strtotime($start_date));
    $end_date = date("Y-m-d", strtotime($end_date));
    // If team separation is enabled
    if (team_separation_extra())
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        $where_in_string .= " AND ".get_user_teams_query("a");
    }

    // Only open risks
    $where_in_string .= " AND a.status != 'Closed'";

    $sql = "
        SELECT 
            a.*, b.calculated_risk, c.name category_name,
            ROUND((b.calculated_risk - (b.calculated_risk * IF(IFNULL(p.mitigation_percent,0) > 0, p.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2) AS residual_risk,
            IFNULL(rsh_s.residual_risk, rsh_e.residual_risk) residual_risk_start,
            rsh_e.residual_risk residual_risk_end
        FROM risks a
        LEFT JOIN risk_scoring b ON a.id = b.id
        LEFT JOIN category c ON a.category = c.value
        LEFT JOIN tags_taggees t ON a.id = t.taggee_id and type = 'risk'
        LEFT JOIN mitigations p ON a.id = p.risk_id
        LEFT JOIN mitigation_to_controls mtc ON p.id = mtc.mitigation_id 
        LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0 
        LEFT JOIN risk_to_additional_stakeholder rtas ON a.id=rtas.risk_id 
        LEFT JOIN risk_to_team rtt on a.id = rtt.risk_id
        LEFT JOIN residual_risk_scoring_history rsh_s ON rsh_s.id = 
            (SELECT id FROM residual_risk_scoring_history sh WHERE sh.risk_id = a.id AND DATE(sh.last_update) >= '{$start_date}' ORDER BY sh.last_update ASC LIMIT 1)
        LEFT JOIN residual_risk_scoring_history rsh_e ON rsh_e.id = 
            (SELECT id FROM residual_risk_scoring_history sh WHERE sh.risk_id = a.id AND DATE(sh.last_update) <= '{$end_date}' ORDER BY sh.last_update DESC LIMIT 1)
        {$where_in_string}
        GROUP BY
            a.id
        ORDER By a.category, ROUND((b.calculated_risk - (b.calculated_risk * IF(IFNULL(p.mitigation_percent,0) > 0, p.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2) DESC, a.submission_date
    ";
    $stmt = $db->prepare($sql);
    foreach($bind_params as $key => $value){
        $stmt->bindParam($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    // Store the results in the rows array
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database
    db_close($db);
    return $rows;
}

/*********************************************
 * FUNCTION: GET GROUP_NAME FOR DYNAMIC RISK *
 *********************************************/
function get_group_name_for_dynamic_risk($group, $sort_name)
{
    // If you want to add a new field for grouping, you have to add it to the risks_unique_column_query_select() function as well
    // Check the group
    switch ($group)
    {
        // None
        case 0:
            $order_query = " ORDER BY " . $sort_name;
            $group_name = "none";
            break;
        // Risk Level
        case 1:
            $order_query = " ORDER BY " . $sort_name;
            $group_name = "risk_level";
            break;
        // Status
        case 2:
            $order_query = " ORDER BY a.status," . $sort_name;
            $group_name = "status";
            break;
        // Site/Location
//        case 3:
//            $order_query = " ORDER BY location," . $sort_name;
//            $group_name = "location";
//            break;
        // Source
        case 4:
            $order_query = " ORDER BY source," . $sort_name;
            $group_name = "source";
            break;
        // Category
        case 5:
            $order_query = " ORDER BY category," . $sort_name;
            $group_name = "category";
            break;
        // Team
        case 6:
            $order_query = " ORDER BY team," . $sort_name;
            $group_name = "team";
            break;
        // Technology
        case 7:
            $order_query = " ORDER BY technology," . $sort_name;
            $group_name = "technology";
            break;
        // Owner
        case 8:
            $order_query = " ORDER BY owner," . $sort_name;
            $group_name = "owner";
            break;
        // Owners Manager
        case 9:
            $order_query = " ORDER BY manager," . $sort_name;
            $group_name = "manager";
            break;
        // Risk Scoring Method
        case 10:
            $order_query = " ORDER BY scoring_method," . $sort_name;
            $group_name = "scoring_method";
            break;
        // Regulation
        case 11:
            $order_query = " ORDER BY regulation," . $sort_name;
            $group_name = "regulation";
            break;
        // Project
        case 12:
            $order_query = " ORDER BY project," . $sort_name;
            $group_name = "project";
            break;
        // Next Step
        case 13:
            $order_query = " ORDER BY next_step," . $sort_name;
            $group_name = "next_step";
            break;
        // Month Submitted
        case 14:
            $order_query = " ORDER BY submission_date DESC," . $sort_name;
            $group_name = "month_submitted";
            break;
        // Default to calculated risk
        default:
            $order_query = " ORDER BY " . $sort_name;
            $group_name = "none";
            break;
    }
    if($sort_name == "none")
        $order_query = "";
    return [$group_name, $order_query];
}

/*********************************************
 * FUNCTION: GET GROUP_NAME FOR DYNAMIC RISK *
 *********************************************/
function get_group_query_for_dynamic_risk($group, &$group_value_from_db, $rename_alias="t1.")
{
    global $lang, $escaper;
    list($group_name, $order_query) = get_group_name_for_dynamic_risk($group, "");

    if($group_name == "none")
    {
        $group_query = " 1 ";
    }
    elseif($group_name == "month_submitted")
    {
        // If month_submit is empty value, set empty string
        if(!$group_value_from_db || stripos($group_value_from_db, "0000-00") !== false)
        {
            $group_value_from_db = "";
        }
        else
        {
            $group_value_from_db =  date('Y-m', strtotime($group_value_from_db))."%"; 
        }
        
        $group_field_name = $rename_alias."submission_date";
        
        $group_query = " {$group_field_name} like :group_value OR :group_value = '' AND ({$group_field_name} IS NULL OR {$group_field_name} = '0000-00-00') ";
    }
    elseif(in_array($group_name, ["location", "team", "technology"]))
    {
        $field_name = $group_name."_values";
        $group_query = " ( FIND_IN_SET(:group_value, {$rename_alias}{$field_name}) OR (:group_value = '' AND {$rename_alias}{$field_name} IS NULL) ) ";
    }
    else
    {
        switch($group_name)
        {
            case "risk_level":
                $group_value_from_db = get_risk_level_name($group_value_from_db);
                $group_field_name = " {$rename_alias}risk_level_name";
            break;

            default:
                $group_field_name = " {$rename_alias}{$group_name} ";
            break;
        }
        $group_query = " ({$group_field_name} = :group_value OR :group_value = '' AND {$group_field_name} IS NULL) ";
    }
    
    $group_query = "(". $group_query .")";

    return $group_query;
}

/********************************
 * FUNCTION: GET RISKS BY TABLE *
 ********************************/
function get_risks_by_table($status, $sort=0, $group=0, $table_columns=[]) {

    global $lang;
    global $escaper;
    
    $rowCount = 0;
    
    // Get group name from $group
    list($group_name, $order_query) = get_group_name_for_dynamic_risk($group, "");
    
    echo "
        <style>
            #risk-table-container .multiselect-native-select {
                max-width: 600px;
                display: block;
            }
        </style>
    ";
    
    // If Group By is not selected or Import/Export extra is disabled, hide download button by group
    if ($group_name == "none" || !import_export_extra()) {

        echo "
        <style>
            .download-by-group {
                display: none;
            }
        </style>
        ";
    }

    // If Import/Export extra is disabled, hide print button by group
    if (!import_export_extra()) {

        echo "
        <style>
            .print-by-group {
                display: none;
            }
        </style>
        ";
    }
    
    // If the group name is none
    if ($group_name == "none") {

        // Display the table header
        echo "
        <table name='risks' id='risks' data-group='' class='table risk-datatable table-bordered table-striped table-condensed table-margin-top mb-0' style='width: 100%'>
            <thead>
                <tr class='main'>
        ";
                    // Header columns go here
                    get_header_columns(false, $table_columns);
        echo "
                </tr>
                <tr class='filter'>
        ";
                    // Header columns go here
                    get_header_columns(false, $table_columns);
        echo "
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        ";

    } else {

        // In getting table structures, disregard column_filters
        $risks = get_risks_only_dynamic($need_total_count=false, $status, $sort, 0, [], $rowCount, 0, -1);
        $displayed_group_names = [];

        // If the group name is risk_level, we should display the risk groups tables in descending order
        // so that the most critical risks are on top (Very High -> Insignificant).
        if ($group_name == "risk_level") {

            usort($risks, function($a, $b) {

                $calculated_risk_a = $a['calculated_risk'];
                $calculated_risk_b = $b['calculated_risk'];

                // Compare the risk levels
                if ($calculated_risk_a == $calculated_risk_b) {
                    return 0;
                }
                
                return ($calculated_risk_a < $calculated_risk_b) ? 1 : -1;

            });
        }

        // For each risk in the risks array
        foreach ($risks as $risk) {

            // We only need these for grouping, it's a waste of time to calculate the rest.
            // In case you add a new grouping add to this list
            // so the $group_value = ${$group_name}; expression can get its value        
            $status = $risk['status'];
            $scoring_method = get_scoring_method_name($risk['scoring_method']);
            $risk_level = get_risk_level_name($risk['calculated_risk']);
            $location = $risk['location'];
            $source = $risk['source'];
            $category = $risk['category'];
            $team = $risk['team'];
            $technology = $risk['technology'];
            $owner = $risk['owner'];
            $manager = $risk['manager'];
            $regulation = try_decrypt($risk['regulation']);
            $project = try_decrypt($risk['project']);
            $next_step = $risk['next_step'];    

            if (!$risk['submission_date'] || stripos($risk['submission_date'], "0000-00-00") !== false) {
                // Set the review date to empty
                $month_submitted = $lang['Unassigned'];
            } else {
                $month_submitted = date('Y F', strtotime($risk['submission_date']));
            }

            // If the group name is not none
            if ($group_name != "none") {

                $initial_group_value = trim(${$group_name} ?? '');
                
                // Check comma splitted group
                if ($group_name == "team" || $group_name == "technology") {

                    if ($initial_group_value) {
                        $group_values_including_empty = str_getcsv($initial_group_value, ',', '"', '');
                        $group_values = [];
                        foreach ($group_values_including_empty as $val) {
                            // Remove empty values from group_values
                            if ($val) {
                                $group_values[] = $val;
                            }
                        }
                    } else {
                        $group_values = [""];
                    }

                } else {
                    $group_values = [$initial_group_value];
                }

                //$group_value = $group_values[0];

                foreach($group_values as $group_value) {

                    switch ($group_name) {
                        case "risk_level":
                            $group_value_from_db = $risk['calculated_risk'];
                            break;
                        case "month_submitted":
                            $group_value_from_db = $risk['submission_date'];
                            break;
                        // Comma splitted group
                        case "team":
                        case "technology":
                            $group_value_from_db = get_value_by_name($group_name, $group_value);
                            break;
                        default:
                            $group_value_from_db = $risk[$group_name];
                            break;
                    }
                    
                    // If the selected group value is empty
                    if ($group_value == "") {
                        // Current group is Unassigned
                        $group_value = $lang['Unassigned'];
                    }

                    // If the group is not the current group
                    // if ($group_value != $current_group && !in_array($group_value, $displayed_group_names))
                    if (!in_array($group_value, $displayed_group_names)) {

// If this is not the first group
//                        if ($current_group != "")
//                        {
//                                echo "</tbody>\n";
//                            echo "</table>\n";
//                            echo "<br />\n";
//                        }

                        $displayed_group_names[] = $group_value;

                        $length = count($table_columns);
                        
                        // Display the table header.
                        // Hoisted so the suppression below sits directly above
                        // the flagged escape — `@phan-suppress-next-line` only
                        // covers the immediately following line and can't
                        // reach into a multi-line echo string.
                        // @phan-suppress-next-line SecurityCheck-DoubleEscaped -- $group_value_from_db is raw DB data assigned in the switch above (from $risk[$group_name] / $risk['calculated_risk'] / $risk['submission_date'] / get_value_by_name); Phan's array-taint plugin flags it because other fields in the same $risk array are pre-escaped (encryption_order via next_review in get_risks_only_dynamic).
                        $escaped_group_value_from_db = $escaper->escapeHtml($group_value_from_db);
                        echo "
        <table data-group='{$escaped_group_value_from_db}' class='table risk-datatable table-bordered table-striped table-condensed  table-margin-top' style='width: 100%'>
            <thead data-group-header-title='{$escaper->escapeHtml($group_value)}' data-group-header-colspan='{$length}'>
                <tr class='main'>
                        ";

                    // Header columns go here
                    get_header_columns(false, $table_columns);

                        echo "
                </tr>
                <tr class='filter'>
                        ";

                    // Header columns go here
                    get_header_columns(false, $table_columns);

                        echo "
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
                        ";
                    }
                }
            }
        }
    }
}

/********************************
 * FUNCTION: GET RISKS BY GROUP *
 ********************************/
function get_risks_by_group($status, $group, $sort, $group_value, $display_columns, $column_filters=[], $orderColumnName=null, $orderDir="asc")
{
    global $lang, $escaper;
    $rowCount = 0;

    // Get group name from $group
    list($group_name, $order_query) = get_group_name_for_dynamic_risk($group, "");

    $displayed_group_names = [];
    // If this is download by group value, set query
    if($group_name != "none" && $group_value !== NULL)
    {
        if($group_name == "month_submitted"){
            if (!$group_value || stripos($group_value, "0000-00-00") !== false)
            {
                // Set the review date to empty
                $group_value = "";
            }
            else
            {
                $group_value = date('Y F', strtotime($group_value)); 
            }
        }else{
            switch($group_name){
                case "risk_level":
                    $group_value = get_risk_level_name($group_value);
                break;
            }
        }
    }
    $risks = risks_query($status, $sort, $group, $column_filters, $rowCount, 0, -1, $group_value, "", [], $orderColumnName, $orderDir);
    // if ($group_value == "")
    // {
    //     // Current group is Unassigned
    //     $group_value = $lang['Unassigned'];
    // }
    // Display the table header
    $str = "<table class=\"table risk-datatable table-bordered table-striped table-condensed  table-margin-top\" style='width: 100%'>\n";
    $str .= "<thead>\n";
    if ($group_name != "none"){
        $length = count($display_columns);
        $display_group_name = get_group_name_from_value($group, $group_value);
        $str .= "<tr>\n";
        $str .= "<th bgcolor=\"#0088CC\" colspan=\"{$length}\"><center>". $escaper->escapeHtml($display_group_name) ."</center></th>\n";
        $str .= "</tr>\n";
    }
    $str .= "<tr class='main'>\n";
    $str .= get_print_header_columns($display_columns);
    $str .= "</tr>\n";
    $str .= "</thead>\n";
    $str .= "<tbody>\n";
    $risk_levels = get_risk_levels();
    $rowCount = 0;
    $tr = array();
    foreach($risks as $index=>$row){
        $row['id'] = (int)$row['id'] + 1000;
        
        $tags = "";
        if ($row['risk_tags']) {
            foreach(str_getcsv($row['risk_tags'], ',', '"', '') as $tag) {
                // @phan-suppress-next-line SecurityCheck-DoubleEscaped -- $tag is raw text from str_getcsv on $row['risk_tags']; flagged only because other keys in $row (e.g. encryption_order from get_risks_only_dynamic) are pre-escaped, contaminating Phan's array-taint analysis.
                $tags .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin: 1px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
            }
        }

        $data_row = [];

        foreach ($display_columns as $column) {
            if(stripos($column, "custom_field_") === false){
                switch ($column) {
                    default:
                        if(array_key_exists($column, $row)) {
                            // @phan-suppress-next-line SecurityCheck-DoubleEscaped -- default-case columns ($row[$column]) are raw DB data; flagged due to Phan's array-taint plugin merging taint across keys.
                            $data_row[] = $escaper->escapeHtml($row[$column]);
                        } else {
                            $data_row[] = "";
                        }
                        break;
                    case 'id':
                        // @phan-suppress-next-line SecurityCheck-DoubleEscaped -- $row['id'] is integer (int-cast and offset earlier in this loop); flagged due to Phan's array-taint plugin merging taint across keys.
                        $data_row[] = "<a class='text-info' href=\"../management/view.php?id=" . $escaper->escapeHtml($row['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($row['id'])."</a>";
                        break;
                    case 'risk_status':
                        // @phan-suppress-next-line SecurityCheck-DoubleEscaped -- $row['status'] is raw DB data; flagged due to Phan's array-taint plugin merging taint across keys.
                        $data_row[] = $escaper->escapeHtml($row['status']);
                        break;
                    case 'closure_date':
                        $data_row[] = $escaper->escapeHtml(format_datetime($row['closure_date'], "", "H:i"));
                        break;
                    case 'risk_tags':
                        $data_row[] = $tags;
                        break;
                    case 'submission_date':
                        $data_row[] = $escaper->escapeHtml(format_datetime($row['submission_date'], "", "H:i"));
                        break;
                    case 'affected_assets':
                        $data_row[] = "<div class='affected-asset-cell'>{$row['affected_assets']}</div>";
                        break;
                    case 'mitigation_planned':
                        $data_row[] = planned_mitigation($row['id'], $row['mitigation_id']);
                        break;
                    case 'management_review':
                        $data_row[] = management_review($row['id'], $row['mgmt_review'], $row['next_review_date']);
                        break;
                    case 'comments':
                    case 'risk_assessment':
                    case 'additional_notes':
                    case 'current_solution':
                    case 'security_recommendations':
                    case 'security_requirements':
                        $data_row[] = $escaper->purifyHtml($row[$column]);
                        break;
                    case "calculated_risk":
                    case "calculated_risk_30":
                    case "calculated_risk_60":
                    case "calculated_risk_90":
                    case "residual_risk":
                    case "residual_risk_30":
                    case "residual_risk_60":
                    case "residual_risk_90":
                        $color = get_risk_color_from_levels($row[$column], $risk_levels);
                        // @phan-suppress-next-line SecurityCheck-DoubleEscaped -- $row[$column] is a raw numeric risk score from the DB; flagged due to Phan's array-taint plugin merging taint across keys.
                        $data_row[] = "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($row[$column]) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeCssColor($color) . "\"></span></div></div>";
                        break;
                }
            } else if(customization_extra()) {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                $field_id = str_replace("custom_field_", "", $column);
                $custom_values = getCustomFieldValuesByRiskId($row['id']);
                $custom_data_row = "";
                foreach($custom_values as $custom_value)
                {
                    // Check if this custom value is for the active field
                    if($custom_value['field_id'] == $field_id){
                        $custom_data_row = get_custom_field_name_by_value($field_id, $custom_value['field_type'], $custom_value['encryption'], $custom_value['value']);
                        break;
                    }
                }
                $data_row[] = $custom_data_row;
                $row["custom_field_".$field_id] = strip_tags($custom_data_row);
            }
        }
        $td = "";
        foreach($data_row as $col){
            $td .= "<td class=\"risk-cell\">".$col."</td>\n";
        }
        $tr[] = array(
            'td' => $td,
            'risk' => $row,
        );
    }
    if(($pos = stripos($orderColumnName, "custom_field_")) !== false){
        usort($tr, function($a, $b) use ($orderDir, $orderColumnName){
            // For identical custom fields we're sorting on the id, so the results' order is not changing
            if ($a['risk'][$orderColumnName] === $b['risk'][$orderColumnName]) {
                return (int)$a['risk']['id'] - (int)$b['risk']['id'];
            }
            if($orderDir == "asc") {
                return strcmp($a['risk'][$orderColumnName], $b['risk'][$orderColumnName]);
            } else {
                return strcmp($b['risk'][$orderColumnName], $a['risk'][$orderColumnName]);
            }
        });
    }
    foreach($tr as $index=>$row){
        $class = $index%2?"odd":"even";
        $str .= "<tr class='{$class}'>\n";
        $str .= $row['td'];
        $str .= "</tr>\n";
    }
    // End the table
    $str .= "</tbody>\n";
    $str .= "</table>\n";
    $str .= "<br />\n";
    // @phan-suppress-next-line SecurityCheck-XSS -- all values in $str are escaped via escapeHtml()/purifyHtml() throughout the building loop
	echo $str;
}

/********************************
 * FUNCTION: GET HEADER COLUMNS *
 ********************************/
function get_print_header_columns($columns)
{
    global $lang, $escaper;
	$str = "";
    foreach($columns as $column){
        if(stripos($column, "custom_field_") === false){
            $name = get_label_by_risk_field_name($column);
            $str .= "<th class='{$column}' data-name='{$column}' align=\"left\" >". $name ."</th>\n";
        } else {
            // If customization extra is enabled, includes customization fields 
            if(customization_extra()){
                $custom_cols = "";
                
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $label = $escaper->escapeHtml($custom_field['name']);
                $str .= "<th data-name='".$column."' align=\"left\" valign=\"top\">".$label."</th>";
            }
        }
    }
    
    
    return $str;
}

/********************************
 * FUNCTION: GET HEADER COLUMNS *
 ********************************/
function get_header_columns($hide, $selected_columns=[]) {

    global $lang;
    global $escaper;

    if ($hide) {
        $display = "display: none;";
    } else {
        $display = "display: table-cell;";
    }

    foreach ($selected_columns as $column=>$status) {
        if (stripos($column, "custom_field_") === false) {
            $name = get_label_by_risk_field_name($column);
            echo "
                <th class='{$column}' data-name='{$column}' " . ($status == true ? "" : "style='{$display}' ") . "align='left' >{$name}</th>
            "; 
        } else {
            // If customization extra is enabled, includes customization fields 
            if (customization_extra()) {
                $custom_cols = "";
                
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $label = $escaper->escapeHtml($custom_field['name']);
                echo "
                <th class='custom_field_{$field_id}' data-name='{$column}' align='left' width='50px' valign='top'>{$label}</th>
                ";
            }
        }
    }
}

/**********************************
 * FUNCTION: TABLE OF RISK BY TEAM *
 *********************************/
function risk_table_open_by_team($selected_columns=[]) {

    global $lang;
    global $escaper;

    // Display the table header
    echo "
        <table data-group='' class='table risk-datatable table-bordered table-striped table-condensed table-margin-top' style='width: 100%'>
            <thead>
                <tr class='main'>
    ";
                    // Header columns go here
                    get_header_columns(false, $selected_columns);
    echo "
                </tr>
                <tr class='filter'>
    ";
                    // Header columns go here
                    get_header_columns(false, $selected_columns);
    echo "
                </tr>
            </thead>
            <tbody>
    ";
    
    // End the table
    echo "
            </tbody>
        </table>
    ";

}

/**********************************
 * FUNCTION: RISKS BY MONTH TABLE *
 **********************************/
function risks_by_month_table() {

    global $escaper;
    global $lang;

    // Get the opened risks array by month
    $opened_risks = get_opened_risks_array("month");
    $open_date = $opened_risks[0];
    $open_count = $opened_risks[1];

    // Get the closed risks array by month
    $closed_risks = get_closed_risks_array("month");

    $close_date = $closed_risks[0];
    $close_count = $closed_risks[1];

    $open = [];
    $close = [];
    $total = [];
    $total_open_risks = [];

    // Wrap the 14-column table so it scrolls (both axes) inside the widget
    // instead of overflowing the frame: horizontally on narrower tiles, and
    // vertically when the tile is shorter than the 5-row table. max-height:100%
    // bounds it to the widget body height so it never spills past the border.
    echo "
        <div class='table-responsive' style='max-height:100%;overflow:auto;'>
        <table class='sr-riskmonth'>
            <thead>
                <tr>
                    <th class='sr-riskmonth__corner'></th>
    ";

    // For each of the past 12 months
    for ($i = 12; $i >= 0; $i--) {

        // Stack the year over the month so each column is only as wide as the
        // month abbreviation — condenses 13 columns to fit without scrolling.
        $mon = date('M', strtotime("first day of -$i month"));
        $yr  = date('Y', strtotime("first day of -$i month"));

        echo "
                    <th><span class='sr-riskmonth__yr'>{$escaper->escapeHtml($yr)}</span><span class='sr-riskmonth__mon'>{$escaper->escapeHtml($mon)}</span></th>
        ";

    }

    echo "
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope='row'>{$escaper->escapeHtml($lang['OpenedRisks'])}</th>
    ";

    // For each of the past 12 months
    for ($i = 12; $i >= 0; $i--) {

        // Get the month
        $month = date('Y-m', strtotime("first day of -$i month"));
        
        // Search the open risks array
        $key = array_search($month, $open_date);

        // If no result was found or the key is null
        if ($key === false || is_null($key)) {

            // Set the value to 0
            $open[$i] = 0;
            
        // Otherwise, use the value found
        } else {
            
            $open[$i] = $open_count[$key];

        }

        echo "
                    <td>{$escaper->escapeHtml($open[$i])}</td>
        ";
    }

    echo "
                </tr>
                <tr>
                    <th scope='row'>{$escaper->escapeHtml($lang['ClosedRisks'])}</th>
    ";

    // For each of the past 12 months
    for ($i = 12; $i >= 0; $i--) {

        // Get the month
        $month = date('Y-m', strtotime("first day of -$i month"));

        // Search the closed risks array
        $key = array_search($month, $close_date);

        // If no result was found or the key is null
        if ($key === false || is_null($key)) {

            // Set the value to 0
            $close[$i] = 0;

        // Otherwise, use the value found
        } else {

            $close[$i] = $close_count[$key];

        }

        echo "
                    <td>{$escaper->escapeHtml($close[$i])}</td>
        ";

    }

    echo "
                </tr>
                <tr>
                    <th scope='row'>{$escaper->escapeHtml($lang['RiskTrend'])}</th>
    ";

    // For each of the past 12 months
    for ($i = 12; $i >= 0; $i--) {

        // Subtract the open number from the closed number
        // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset -- $open/$close populated by prior loops over the same $i range
        $total[$i] = $open[$i] - $close[$i];

        // If the total is positive
        if ($total[$i] > 0) {

            // A net rise in open risks this month is bad — colour it red.
            $total_string = "<span class='sr-riskmonth__delta sr-riskmonth__delta--up'>+{$total[$i]}</span>";

        // If the total is negative
        } else if ($total[$i] < 0) {

            // A net decrease is good — colour it green.
            $total_string = "<span class='sr-riskmonth__delta sr-riskmonth__delta--down'>{$total[$i]}</span>";

        // Otherwise the total is 0
        } else {
            $total_string = $total[$i];
        }

        echo "
                    <td>{$total_string}</td>
        ";
    }

    // Reverse the total array
    $total = array_reverse($total);

    // Get the number of open risks
    $open_risks_today = get_open_risks();

    // Start the total open risks array with the open risks today
    $total_open_risks[] = $open_risks_today;

    // For each of the past 12 months
    for ($i=1; $i<=12; $i++) {

        $total_open_risks[$i] = $total_open_risks[$i-1] - $total[$i-1];

    }

    // Reverse the total open risks array
    $total_open_risks = array_reverse($total_open_risks);
    
    echo "
                </tr>
                <tr class='sr-riskmonth__total-row'>
                    <th scope='row'>{$escaper->escapeHtml($lang['TotalOpenRisks'])}</th>
    ";

    // For each of the past 12 months
    for ($i = 0; $i <= 12; $i++) {

        // Get the total number of risks
        $total = $total_open_risks[$i];

        echo "
                    <td>{$escaper->escapeHtml($total)}</td>
        ";

    }

    echo "
                </tr>
            </tbody>
        </table>
        </div>
    ";

}

/*************************************
 * FUNCTION: RETURN REISKS QUERY SQL *
 *************************************/
function risks_query_select($column_filters=[])
{
    global $lang;

    $currency = get_currency_symbol(true);

    $query = "
        a.id, 
        a.status, 
        a.subject, 
        a.reference_id, 
        a.control_number, 
        a.submission_date, 
        a.last_update, 
        a.review_date, 
        a.mgmt_review,
        a.assessment AS risk_assessment, 
        a.notes AS additional_notes, 
        b.scoring_method, 
        b.calculated_risk, 
        b.CLASSIC_likelihood AS CLASSIC_likelihood_value, 
        CONCAT('[',b.CLASSIC_likelihood,'] ', likelihood.name) AS CLASSIC_likelihood,
        b.CLASSIC_impact AS CLASSIC_impact_value, 
        CONCAT('[',b.CLASSIC_impact,'] ', impact.name) AS CLASSIC_impact,
        b.CVSS_AccessVector, 
        b.CVSS_AccessComplexity, 
        b.CVSS_Authentication, 
        b.CVSS_ConfImpact, 
        b.CVSS_IntegImpact, 
        b.CVSS_AvailImpact, 
        b.CVSS_Exploitability, 
        b.CVSS_RemediationLevel, 
        b.CVSS_ReportConfidence , 
        b.CVSS_CollateralDamagePotential, 
        b.CVSS_TargetDistribution, 
        b.CVSS_ConfidentialityRequirement, 
        b.CVSS_IntegrityRequirement, 
        b.CVSS_AvailabilityRequirement, 
        b.DREAD_DamagePotential, 
        b.DREAD_Reproducibility, 
        b.DREAD_Exploitability, 
        b.DREAD_AffectedUsers, 
        b.DREAD_Discoverability, 
        b.OWASP_SkillLevel,
        b.OWASP_Motive,
        b.OWASP_Opportunity, 
        b.OWASP_Size, 
        b.OWASP_EaseOfDiscovery, 
        b.OWASP_EaseOfExploit, 
        b.OWASP_Awareness, 
        b.OWASP_IntrusionDetection, 
        b.OWASP_LossOfConfidentiality, 
        b.OWASP_LossOfIntegrity, 
        b.OWASP_LossOfAvailability, 
        b.OWASP_LossOfAccountability, 
        b.OWASP_FinancialDamage, 
        b.OWASP_ReputationDamage, 
        b.OWASP_NonCompliance, 
        b.OWASP_PrivacyViolation, 
        b.Custom, 
        p.mitigation_percent,
        ROUND((b.calculated_risk - (b.calculated_risk * IF(IFNULL(p.mitigation_percent,0) > 0, p.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2) AS residual_risk,

       CASE 
            WHEN DATEDIFF(NOW(), `a`.`submission_date`) < 30 THEN '--'
            WHEN NOT(ISNULL(`rsh_lua_30`.`calculated_risk`)) THEN `rsh_lua_30`.`calculated_risk`
            WHEN NOT(ISNULL(`rsh_lua_60`.`calculated_risk`)) THEN `rsh_lua_60`.`calculated_risk`
            WHEN NOT(ISNULL(`rsh_lua_90`.`calculated_risk`)) THEN `rsh_lua_90`.`calculated_risk`
            ELSE `b`.`calculated_risk`
        END AS calculated_risk_30,
        CASE 
            WHEN DATEDIFF(NOW(), `a`.`submission_date`) < 60 THEN '--'
            WHEN NOT(ISNULL(`rsh_lua_60`.`calculated_risk`)) THEN `rsh_lua_60`.`calculated_risk`
            WHEN NOT(ISNULL(`rsh_lua_90`.`calculated_risk`)) THEN `rsh_lua_90`.`calculated_risk`
            ELSE `b`.`calculated_risk`
        END AS calculated_risk_60,
        CASE 
            WHEN DATEDIFF(NOW(), `a`.`submission_date`) < 90 THEN '--'
            WHEN NOT(ISNULL(`rsh_lua_90`.`calculated_risk`)) THEN `rsh_lua_90`.`calculated_risk`
            ELSE `b`.`calculated_risk`
        END AS calculated_risk_90,        
        CASE 
            WHEN DATEDIFF(NOW(), `a`.`submission_date`) < 30 THEN '--'
            WHEN NOT(ISNULL(`rrsh_lua_30`.`residual_risk`)) THEN `rrsh_lua_30`.`residual_risk`
            WHEN NOT(ISNULL(`rrsh_lua_60`.`residual_risk`)) THEN `rrsh_lua_60`.`residual_risk`
            WHEN NOT(ISNULL(`rrsh_lua_90`.`residual_risk`)) THEN `rrsh_lua_90`.`residual_risk`
            ELSE ROUND((`b`.`calculated_risk` - (`b`.`calculated_risk` * IF(IFNULL(p.mitigation_percent,0) > 0, p.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2)
        END AS residual_risk_30,
        CASE 
            WHEN DATEDIFF(NOW(), `a`.`submission_date`) < 60 THEN '--'
            WHEN NOT(ISNULL(`rrsh_lua_60`.`residual_risk`)) THEN `rrsh_lua_60`.`residual_risk`
            WHEN NOT(ISNULL(`rrsh_lua_90`.`residual_risk`)) THEN `rrsh_lua_90`.`residual_risk`
            ELSE ROUND((`b`.`calculated_risk` - (`b`.`calculated_risk` * IF(IFNULL(p.mitigation_percent,0) > 0, p.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2)
        END AS residual_risk_60,
        CASE 
            WHEN DATEDIFF(NOW(), `a`.`submission_date`) < 90 THEN '--'
            WHEN NOT(ISNULL(`rrsh_lua_90`.`residual_risk`)) THEN `rrsh_lua_90`.`residual_risk`
            ELSE ROUND((`b`.`calculated_risk` - (`b`.`calculated_risk` * IF(IFNULL(p.mitigation_percent,0) > 0, p.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2)
        END AS residual_risk_90,

        `associated_rc_entries`.`risk_mapping_risk_event` AS risk_mapping,
        GROUP_CONCAT(DISTINCT `tc`.`name` SEPARATOR ',') AS threat_mapping,

        (
            SELECT
                GROUP_CONCAT(DISTINCT location.name SEPARATOR ',')
            FROM
                location, risk_to_location rtl
            WHERE
                rtl.risk_id=a.id AND rtl.location_id=location.value
        ) AS location,

        d.name AS category, 

        (
            SELECT
                GROUP_CONCAT(DISTINCT team.name  SEPARATOR ',')
            FROM
                team, risk_to_team rtt
            WHERE
                rtt.risk_id=a.id AND rtt.team_id=team.value
        ) AS team,

        (
            SELECT
                GROUP_CONCAT(DISTINCT team.value  SEPARATOR ',')
            FROM
                team, risk_to_team rtt
            WHERE
                rtt.risk_id=a.id AND rtt.team_id=team.value
        ) AS team_values,


        (
            SELECT
                GROUP_CONCAT(DISTINCT u.name SEPARATOR ',')
            FROM
                user u, risk_to_additional_stakeholder rtas
            WHERE
                rtas.risk_id=a.id AND rtas.user_id=u.value
        ) AS additional_stakeholders,


        (
            SELECT
                GROUP_CONCAT(DISTINCT tech.name SEPARATOR ',')
            FROM
                technology tech, risk_to_technology rttg
            WHERE
                rttg.risk_id=a.id AND rttg.technology_id=tech.value
        ) AS technology,

        (
            SELECT
                GROUP_CONCAT(DISTINCT tech.value SEPARATOR ',')
            FROM
                technology tech, risk_to_technology rttg
            WHERE
                rttg.risk_id=a.id AND rttg.technology_id=tech.value
        ) AS technology_values,

        g.name AS owner, 
        h.name AS manager, 
        i.name AS submitted_by,
        j.name AS regulation, 
        a.regulation regulation_id, 
        k.name AS project, 
        k.status AS project_status_value,
        CASE k.status
            WHEN 1 THEN '".$lang['ActiveProjects']."'
            WHEN 2 THEN '".$lang['OnHoldProjects']."'
            WHEN 3 THEN '".$lang['CompletedProjects']."'
            WHEN 4 THEN '".$lang['CanceledProjects']."'
        END project_status,
        a.project_id,
        lu.name AS reviewer,
        rw.name AS review, 
        l.next_review, 
        l.comments, 
        m.name AS next_step, 
        (
            SELECT
                GROUP_CONCAT(DISTINCT rta.asset_id SEPARATOR ',')
            FROM
                risks_to_assets rta
            WHERE
                rta.risk_id=a.id
        ) AS affected_assets,
        (
            SELECT
                GROUP_CONCAT(DISTINCT rtag.asset_group_id SEPARATOR ',')
            FROM
                risks_to_asset_groups rtag
            WHERE
                rtag.risk_id=a.id
        ) AS affected_asset_groups,
        
        o.closure_date, 
        cu.name AS closed_by, 
        cr.name as close_reason, 
        o.note AS close_out, 
        q.name AS planning_strategy,
        p.planning_date, 
        r.name AS mitigation_effort, 
        s.min_value AS mitigation_min_cost, 
        s.max_value AS mitigation_max_cost, 
        s.valuation_level_name, 
        t.name AS mitigation_owner,
        IF(s.valuation_level_name IS NULL OR s.valuation_level_name='',
            CONCAT('{$currency}', s.min_value, ' to {$currency}', s.max_value),
            CONCAT('{$currency}', s.min_value, ' to {$currency}', s.max_value, '(', s.valuation_level_name, ')')
          ) mitigation_cost,
        (
            SELECT
                GROUP_CONCAT(DISTINCT team.name SEPARATOR ',')
            FROM
                team, mitigation_to_team mtt 
            WHERE
                mtt.mitigation_id=p.id AND mtt.team_id=team.value
        ) AS mitigation_team,

        EXISTS(select 1 from mitigation_accept_users mau WHERE a.id=mau.risk_id) AS mitigation_accepted, 
        p.submission_date AS mitigation_date, 
        
        (
            SELECT
                GROUP_CONCAT(DISTINCT fc.short_name SEPARATOR ',')
            FROM
                `mitigation_to_controls` mtc INNER JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            WHERE
                mtc.mitigation_id=p.id 
        ) AS mitigation_control_names,
        
        
        v.name AS source, 
        p.id mitigation_id, 
        p.current_solution,
        p.security_recommendations, 
        p.security_requirements, 
        ifnull((SELECT IF(display_name='', name, display_name) FROM `risk_levels` WHERE value-b.calculated_risk<=0.00001 ORDER BY value DESC LIMIT 1), '{$lang['Insignificant']}') as risk_level_name,

        (
            SELECT
                GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR '|')
            FROM
                tags t, tags_taggees tt 
            WHERE
                tt.tag_id = t.id AND tt.taggee_id=a.id AND tt.type='risk'
        ) AS risk_tags,
        DATEDIFF(IF(a.status != 'Closed', NOW(), o.closure_date) , a.submission_date) days_open,
        `associated_rc_entries`.`risk_mapping_risk_grouping`,
        `associated_rc_entries`.`risk_mapping_risk`,
        `associated_rc_entries`.`risk_mapping_description`,
        `associated_rc_entries`.`risk_mapping_function`,
    ";
    $contributing_risks = get_contributing_risks();
    foreach($contributing_risks as $contributing_risk){
        $id = $contributing_risk['id'];
        $query .= "CONCAT('[ ',`cri_data_{$id}`.`value`,' ] ', `cri_data_{$id}`.`name`) AS Contributing_Impact_{$id}, \n";
        $query .= "`cri_data_{$id}`.`value` AS Contributing_Impact_{$id}_value, \n";
    }
    $query .= "CONCAT('[ ',cr_likelihood.value,' ] ', cr_likelihood.name) AS Contributing_Likelihood, cr_likelihood.value AS Contributing_Likelihood_value";

    // If customization extra is enabled, add custom fields 
    if(customization_extra())
    {
        foreach($column_filters as $key => $column_filter)
        {
            if($column_filter && stripos($key, "custom_field_") !== false)
            {
                $custom_field_id = (int)str_replace("custom_field_", "", $key);
                
                if($custom_field_id)
                {
                    $field_alias = $table_alias = "custom_field_".$custom_field_id;
                    
                    $query .= ", {$table_alias}.value AS {$field_alias} ";
                }
            }
        }
    }

    return $query;
}

/*************************************
 * FUNCTION: RETURN REISKS QUERY SQL *
 *************************************/
function risks_unique_column_query_select()
{
    global $lang;

    $delimiter = "---";
    $currency = get_currency_symbol(true);

    return "
        /*Risk columns*/
        a.id, 
        a.status,
        `associated_rc_entries`.`risk_mapping_risk_event` AS risk_mapping,
        GROUP_CONCAT(DISTINCT CONCAT(tc.name, '{$delimiter}', tc.id)  SEPARATOR '|') AS threat_mapping,
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(t.tag, '{$delimiter}', t.id) ORDER BY t.tag ASC SEPARATOR '|')
            FROM
                tags t, tags_taggees tt 
            WHERE
                tt.tag_id = t.id AND tt.taggee_id=a.id AND tt.type='risk'
        ) AS risk_tags,
        
        CONCAT(i.name, '{$delimiter}', i.value) AS submitted_by_for_dropdown,
        CONCAT(v.name, '{$delimiter}', v.value) AS source_for_dropdown, 
        CONCAT(d.name, '{$delimiter}', d.value) AS category_for_dropdown,
        CONCAT(k.name, '{$delimiter}', k.value) AS project_for_dropdown, 
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(location.name, '{$delimiter}', location.value) SEPARATOR '|')
            FROM
                location, risk_to_location rtl
            WHERE
                rtl.risk_id=a.id AND rtl.location_id=location.value
        ) AS location,
        CONCAT(j.name, '{$delimiter}', j.value) AS regulation_for_dropdown, 
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(assets.name, '{$delimiter}', assets.id) SEPARATOR ', ')
            FROM
                assets, risks_to_assets rta
            WHERE
                rta.risk_id=a.id AND rta.asset_id=assets.id
        ) AS affected_assets,
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(asset_groups.name, '{$delimiter}', asset_groups.id) SEPARATOR ', ')
            FROM
                asset_groups, risks_to_asset_groups rtag
            WHERE
                rtag.risk_id=a.id AND rtag.asset_group_id=asset_groups.id
        ) AS affected_asset_groups,
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(tech.name, '{$delimiter}', tech.value) SEPARATOR '|')
            FROM
                technology tech, risk_to_technology rttg
            WHERE
                rttg.risk_id=a.id AND rttg.technology_id=tech.value
        ) AS technology,
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(team.name, '{$delimiter}', team.value)  SEPARATOR '|')
            FROM
                team, risk_to_team rtt
            WHERE
                rtt.risk_id=a.id AND rtt.team_id=team.value
        ) AS team,
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(u.name, '{$delimiter}', u.value) SEPARATOR '|')
            FROM
                user u, risk_to_additional_stakeholder rtas
            WHERE
                rtas.risk_id=a.id AND rtas.user_id=u.value
        ) AS additional_stakeholders,
        CONCAT(g.name, '{$delimiter}', g.value) AS owner_for_dropdown, 
        CONCAT(h.name, '{$delimiter}', h.value) AS manager_for_dropdown,
        CONCAT(cu.name, '{$delimiter}', cu.value) AS closed_by_for_dropdown,
        CONCAT(cr.name, '{$delimiter}', cr.value) AS close_reason_for_dropdown,

        /*Mitigation columns*/
        CONCAT(r.name, '{$delimiter}', r.value) AS mitigation_effort_for_dropdown, 
        IF(s.valuation_level_name IS NULL OR s.valuation_level_name='',
            CONCAT('{$currency}', s.min_value, ' to {$currency}', s.max_value, '{$delimiter}', s.min_value, '-', s.max_value),
            CONCAT('{$currency}', s.min_value, ' to {$currency}', s.max_value, '(', s.valuation_level_name, ')', '{$delimiter}', s.min_value, '-', s.max_value)
          ) mitigation_cost,
        CONCAT(t.name, '{$delimiter}', t.value) AS mitigation_owner_for_dropdown,
        CONCAT(q.name, '{$delimiter}', q.value) AS planning_strategy_for_dropdown,        
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(team.name, '{$delimiter}', team.value) SEPARATOR '|')
            FROM
                team, mitigation_to_team mtt 
            WHERE
                mtt.mitigation_id=p.id AND mtt.team_id=team.value
        ) AS mitigation_team,
        GROUP_CONCAT(DISTINCT CONCAT(fc.short_name, '{$delimiter}', fc.id) SEPARATOR '|') mitigation_control_names,

        /*Review columns*/
        CONCAT(lu.name, '{$delimiter}', lu.value) AS reviewer_for_dropdown, 
        CONCAT(rw.name, '{$delimiter}', rw.value) AS review_for_dropdown, 
        CONCAT(m.name, '{$delimiter}', m.value) AS next_step_for_dropdown, 

        /*Risk scoring columns*/
        b.scoring_method,

        /*Risk mapping columns*/
        `associated_rc_entries`.`risk_mapping_risk_grouping`,
        `associated_rc_entries`.`risk_mapping_risk`,
        `associated_rc_entries`.`risk_mapping_function`,

        /*Required for grouping*/
        ifnull((SELECT IF(display_name='', name, display_name) FROM `risk_levels` WHERE value-b.calculated_risk<=0.00001 ORDER BY value DESC LIMIT 1), '{$lang['Insignificant']}') as risk_level_name,
        a.submission_date,
        v.name AS source, 
        d.name AS category,
        (
        	SELECT
        		GROUP_CONCAT(DISTINCT team.value  SEPARATOR ',')
        	FROM
        		team, risk_to_team rtt
        	WHERE
        		rtt.risk_id=a.id AND rtt.team_id=team.value
        ) AS team_values,
        (
        	SELECT
        		GROUP_CONCAT(DISTINCT tech.value SEPARATOR ',')
        	FROM
        		technology tech, risk_to_technology rttg
        	WHERE
        		rttg.risk_id=a.id AND rttg.technology_id=tech.value
        ) AS technology_values,
        g.name AS owner,
        h.name AS manager, 
        j.name AS regulation,
        k.name AS project,
        m.name AS next_step
    ";
}

/*************************************
 * FUNCTION: RETURN REISKS QUERY SQL *
 *************************************/
function risks_query_from($column_filters=[], $risks_by_team=0, $orderColumnName="", $query_type = 1)
{
    $query = "
            risks a
            LEFT JOIN risk_scoring b ON a.id = b.id
            LEFT JOIN category d ON a.category = d.value
            LEFT JOIN user g ON a.owner = g.value
            LEFT JOIN user h ON a.manager = h.value
            LEFT JOIN user i ON a.submitted_by = i.value
            LEFT JOIN frameworks j ON a.regulation = j.value
            LEFT JOIN projects k ON a.project_id = k.value
            LEFT JOIN mgmt_reviews l ON a.mgmt_review = l.id
            LEFT JOIN user lu ON l.reviewer = lu.value
            LEFT JOIN next_step m ON l.next_step = m.value
            LEFT JOIN review rw ON l.review = rw.value
            LEFT JOIN closures o ON a.close_id = o.id
            LEFT JOIN user cu ON o.user_id = cu.value
            LEFT JOIN close_reason cr ON cr.value = o.close_reason
            LEFT JOIN mitigations p ON a.id = p.risk_id
            LEFT JOIN `mitigation_to_controls` mtc ON p.id = mtc.mitigation_id
            LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            LEFT JOIN planning_strategy q ON p.planning_strategy = q.value
            LEFT JOIN mitigation_effort r ON p.mitigation_effort = r.value
            LEFT JOIN asset_values s ON p.mitigation_cost = s.id
            LEFT JOIN user t ON p.mitigation_owner = t.value
            LEFT JOIN source v ON a.source = v.value
            LEFT JOIN threat_catalog_mappings tcm ON a.id = tcm.risk_id
            LEFT JOIN threat_catalog tc ON tcm.threat_catalog_id = tc.id
            LEFT JOIN `temp_associated_risk_catalog_entries` associated_rc_entries ON `associated_rc_entries`.`risk_id` = `a`.`id`
    ";

    if ($query_type != 3) {
        $query .= "

            LEFT JOIN mitigation_accept_users mau ON a.id=mau.risk_id

            LEFT JOIN `temp_rsh_last_update_age` rsh_lua_30 ON `rsh_lua_30`.`risk_id` = `a`.`id` AND `rsh_lua_30`.`age_range` = '30-60'
            LEFT JOIN `temp_rsh_last_update_age` rsh_lua_60 ON `rsh_lua_60`.`risk_id` = `a`.`id` AND `rsh_lua_60`.`age_range` = '60-90'
            LEFT JOIN `temp_rsh_last_update_age` rsh_lua_90 ON `rsh_lua_90`.`risk_id` = `a`.`id` AND `rsh_lua_90`.`age_range` = '90+'

            LEFT JOIN `temp_rrsh_last_update_age` rrsh_lua_30 ON `rrsh_lua_30`.`risk_id` = `a`.`id` AND `rrsh_lua_30`.`age_range` = '30-60'
            LEFT JOIN `temp_rrsh_last_update_age` rrsh_lua_60 ON `rrsh_lua_60`.`risk_id` = `a`.`id` AND `rrsh_lua_60`.`age_range` = '60-90'
            LEFT JOIN `temp_rrsh_last_update_age` rrsh_lua_90 ON `rrsh_lua_90`.`risk_id` = `a`.`id` AND `rrsh_lua_90`.`age_range` = '90+'
            
        ";
    
        if(!empty($column_filters['location'])){
            $query .= "
            LEFT JOIN risk_to_location rtl ON a.id=rtl.risk_id
            ";
        }
        if(!empty($column_filters['technology'])){
            $query .= "
            LEFT JOIN risk_to_technology rttg ON a.id=rttg.risk_id
            ";
        }
        if(!empty($column_filters['mitigation_team'])){
            $query .= "
            LEFT JOIN mitigation_to_team mtt ON p.id=mtt.mitigation_id
            ";
        }
        if(!empty($column_filters['risk_tags'])){
            $query .= "
            LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id AND tt.type = 'risk'
            ";
        }
        if(!empty($column_filters['affected_assets'])){
            $query .= "
            LEFT JOIN risks_to_assets rta ON a.id = rta.risk_id
            LEFT JOIN risks_to_asset_groups rtag ON a.id = rtag.risk_id
            ";
        }
    
        $contributing_risks = get_contributing_risks();
        foreach($contributing_risks as $contributing_risk) {
            $id = $contributing_risk['id'];
            $query .= "
            LEFT JOIN `temp_contributing_risk_impact_data` cri_data_{$id} ON `cri_data_{$id}`.`risk_scoring_id` = `a`.`id` AND `cri_data_{$id}`.`contributing_risks_id` = {$id}
            ";
        }
    
        $query .= "
            LEFT JOIN contributing_risks_likelihood cr_likelihood ON cr_likelihood.value = b.Contributing_Likelihood
            LEFT JOIN likelihood ON likelihood.value = b.CLASSIC_likelihood
            LEFT JOIN impact ON impact.value = b.CLASSIC_impact
        ";
        
        // If customization extra is enabled, set join tables for custom filters
        if(customization_extra())
        {
            $join_custom_table = false;
            foreach($column_filters as $key => $column_filter)
            {
                if($column_filter && stripos($key, "custom_field_") !== false)
                {
                    $custom_field_id = (int)str_replace("custom_field_", "", $key);
                    
                    if($custom_field_id)
                    {
                        $table_alias = "custom_field_".$custom_field_id;
    
                        $query .= "
                            LEFT JOIN custom_risk_data {$table_alias} ON a.id={$table_alias}.risk_id AND {$table_alias}.field_id={$custom_field_id} AND ( {$table_alias}.review_id=0 OR {$table_alias}.review_id=a.mgmt_review )
                        ";
    
                        if($table_alias == $orderColumnName) $join_custom_table = true;
                    }
                }
            }
            if(!$join_custom_table && stripos((string)$orderColumnName, "custom_field_") !== false){
                $custom_field_id = (int)str_replace("custom_field_", "", $orderColumnName);
                if($custom_field_id)
                {
                    $table_alias = "custom_field_".$custom_field_id;
    
                    $query .= "
                        LEFT JOIN custom_risk_data {$table_alias} ON a.id={$table_alias}.risk_id AND {$table_alias}.field_id={$custom_field_id} AND ( {$table_alias}.review_id=0 OR {$table_alias}.review_id=a.mgmt_review )
                    ";
    
                }
            }
        }
    }
    
    // If the team separation extra is enabled
    $team_separation_extra = team_separation_extra();
    if(!empty($column_filters['team']) || $team_separation_extra || $risks_by_team){
        $query .= "
            LEFT JOIN risk_to_team rtt ON a.id=rtt.risk_id
        ";
    }
    if(!empty($column_filters['additional_stakeholders']) || $team_separation_extra){
        $query .= "
            LEFT JOIN risk_to_additional_stakeholder rtas ON a.id=rtas.risk_id
        ";
    }

     return $query;
}

/**************************************
 * FUNCTION: RETURN DYNAMIC RISKS SQL *
 * query_type: 
 *      1: dynamic risk
 *      3: unique column
 **************************************/
function make_full_risks_sql($query_type, $status, $sort, $group, $column_filters=[], &$group_value_from_db="", &$custom_query="", &$bind_params=[], $having_query="", $orderColumnName="", $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[], $force_user_id=null)
{
    $delimiter = "---";

    $orderDir = strtolower($orderDir) == "asc" ? "ASC" : "DESC";
    // Check the status
    switch ($status)
    {
        // Open risks
        case 0:
                $status_query = " AND a.status != \"Closed\" ";
                break;

        // Closed risks
        case 1:
                $status_query = " AND a.status = \"Closed\" ";
                break;

        // All risks
        case 2:
                $status_query = " AND 1 ";
                break;

        // Default to open risks
        default:
                $status_query = " AND a.status != \"Closed\" ";
                break;
    }

    // If this is risks_by_teams page
    if ($risks_by_team)
    {
        $team_querys = array();
        
        $params = array();

        // If at least one team was selected
        if($teams) {

            $team_filter = [];

            if (($position = array_search(0, $teams)) !== false) {
                unset($teams[$position]);
                $team_filter []= "rtt.team_id IS NULL";
            }

            // Sanitize input data
            $teams = sanitize_int_array($teams);
            
            // Make sure there's data left after the sanitization
            if (!empty($teams)) {
                $team_filter []= "rtt.team_id IN (" . implode(",", $teams) . ")";
            }
            
            // If there's anything to filter on
            if (!empty($team_filter)) {
                $team_querys []= "(" . implode(" OR ", $team_filter) . ")";
            }
        }

        // If at least one owner was selected
        if($owners){
            $teamsArray = array();
            foreach($owners as $owner){
                $bind_name = "param".count($params);
                $params[$bind_name] = $owner;
                $teamsArray[] = "a.owner = :". $bind_name;
            }
            $team_query_string = "(".implode(" OR ", $teamsArray).")";
            array_push($team_querys, $team_query_string);
        }
                        
        // If at least one owner's manager was selected
        if($ownersmanagers ){
            $teamsArray = array();
            foreach($ownersmanagers as $ownersmanager){
                $bind_name = "param".count($params);
                $params[$bind_name] = $ownersmanager;
                $teamsArray[] = "a.manager = :". $bind_name;
            }
            $team_query_string = "(".implode(" OR ", $teamsArray).")";
            array_push($team_querys, $team_query_string);
        }
        $team_query = implode(" AND ", $team_querys);
        if($team_query){
            $custom_query .= " AND (". $team_query . ")";
        }else{
            $custom_query .= " AND 0 ";
        }
        $bind_params = array_merge($bind_params, $params);
    }
    
    if($custom_query){
        $status_query .= $custom_query;
    }

    $orderDir = (strtolower($orderDir) == "asc") ? "ASC" : "DESC";
    $sort_name = "none";


    switch ($orderColumnName)
    {
        case "id":
            $sort_name = " id {$orderDir} ";
            break;
        case "risk_status":
            $sort_name = " status {$orderDir} ";
            break;
        case "subject":
            // If the encryption extra is enabled, sort by order_by_subject field
            if (encryption_extra())
            {
                $sort_name = " a.order_by_subject {$orderDir} ";
            }
            else
            {
                $sort_name = " a.subject {$orderDir} ";
            }

            break;
        case "mitigation_planned":
            $sort_name = " mitigation_id {$orderDir} ";
            break;
        case "mitigation_effort":
            $sort_name = " r.value {$orderDir} ";
            break;
        case "mitigation_cost":
            $sort_name = " mitigation_min_cost {$orderDir} ";
            break;
        case "mitigation_controls":
            $sort_name = " mitigation_control_names {$orderDir} ";
            break;
        case "next_review_date":
            $sort_name = " next_review {$orderDir} ";
            break;
        case "closed_by":
        case "close_reason":
        case "close_out":
            $sort_name = " {$orderColumnName} {$orderDir} ";
            break;
        case "comments":
            if (!encryption_extra())
            {
                $sort_name = " l.comments {$orderDir} ";
            }
            break;
        // Add fields here that are sorted in code to prevent adding sorting logic for them into the query
        case "management_review":
        case "":
        case null:
            $sort_name = "none";
            break;
        default:
            if (preg_match('/^[A-Za-z0-9_]+$/',$orderColumnName)){
                if(stripos($orderColumnName, "custom_field_") !== false){
                    $sort_name = " `{$orderColumnName}`.value {$orderDir} ";
                } else if(stripos($orderColumnName, "Contributing_Impact_") !== false) {
                    $impact_id = str_ireplace("Contributing_Impact_", "", $orderColumnName);
                    $sort_name = " cs_impacts_{$impact_id}.name {$orderDir} ";
                } else if(stripos($orderColumnName, "CLASSIC_") !== false || stripos($orderColumnName, "CVSS_") !== false || stripos($orderColumnName, "DREAD_") !== false || stripos($orderColumnName, "OWASP_") !== false || stripos($orderColumnName, "Contributing_") !== false) {
                    $sort_name = " b.`{$orderColumnName}` {$orderDir} ";
                } else if(stripos($orderColumnName, "calculated_risk_") !== false || stripos($orderColumnName, "residual_risk_") !== false) {
                    $sort_name = "`{$orderColumnName}`+0 {$orderDir} ";
                } else {
                    $orderColumnName = sqli_filter($orderColumnName);
                    $sort_name = "`{$orderColumnName}` {$orderDir}";
                }
            }
            break;
    }
    
    list($group_name, $order_query) = get_group_name_for_dynamic_risk($group, $sort_name);

    $filter_query = "";
    
    // If the team separation extra is enabled
    if (team_separation_extra())
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the separation query string
        $separation_query = get_user_teams_query("a", false, true, force_user_id: $force_user_id);

        $filter_query .= $separation_query;
    }

    /*
     Added temporary tables to replace some joins that had an impact on performance.
     
     `rrsh_last_update_age_base` - gathering the base data of `residual_risk_scoring_history`and adding additional information like the days since
     the last update(age) and in which age range the history is in.
     `rrsh_last_update_age` - Using the `rrsh_last_update_age_base` table's data but only keeps the latest entry in each range for each risk
     `rsh_last_update_age_base` and `rsh_last_update_age` does the same with the `risk_scoring_history` data
     `risk_scoring_history` - created to replace joining `contributing_risks_impact` and `risk_scoring_contributing_impacts` tables to reduce
     main query's runtime
     */
    // Adding a unique key at the end of the temporary tables so if multiple requests come in they're not deleting eachother's temporary tables
    $unique_key = '_' . time() . '_' . generate_token(5);
    
    $create_temporary_tables = "
        CREATE TABLE `temp_rrsh_last_update_age_base{$unique_key}`
        SELECT
            CASE
                WHEN `lua`.`age` < 30 THEN '-30'
                WHEN `lua`.`age` >= 30 AND `lua`.`age` < 60 THEN '30-60'
                WHEN `lua`.`age` >= 60 AND `lua`.`age` < 90 THEN '60-90'
                WHEN `lua`.`age` >= 90 THEN '90+'
            END AS age_range,
            `lua`.*
        FROM
            (/*(1) Select the base data from the `residual_risk_scoring_history` table plus calculate the age of each entry*/
                SELECT
                    `sh`.`id` AS id,
                    `sh`.`risk_id` AS risk_id,
                    `sh`.`last_update` AS last_update,
                    DATEDIFF(NOW(), `sh`.`last_update`) AS age,
                    `sh`.`residual_risk` AS residual_risk
                FROM
                    `residual_risk_scoring_history` sh
                GROUP BY
                    `sh`.`last_update`
            ) lua;
        /*(4) Create the 'temporary' table for data from the `residual_risk_scoring_history` table*/
        CREATE TABLE `temp_rrsh_last_update_age{$unique_key}`(
            PRIMARY KEY(`risk_id`, `age_range`),
            INDEX (`age_range`, `risk_id`)
        )
        /*(3) Select the latest entry for each age range*/
        SELECT
            `lua`.*
        FROM
            `temp_rrsh_last_update_age_base{$unique_key}` lua
            LEFT JOIN `temp_rrsh_last_update_age_base{$unique_key}` lua2 ON `lua`.`risk_id` = `lua2`.`risk_id` AND `lua`.`age_range` = `lua2`.`age_range` AND `lua`.`last_update` < `lua2`.`last_update`
        WHERE
            `lua2`.`id` IS NULL;
        CREATE TABLE `temp_rsh_last_update_age_base{$unique_key}`
        SELECT
            CASE
                WHEN `lua`.`age` < 30 THEN '-30'
                WHEN `lua`.`age` >= 30 AND `lua`.`age` < 60 THEN '30-60'
                WHEN `lua`.`age` >= 60 AND `lua`.`age` < 90 THEN '60-90'
                WHEN `lua`.`age` >= 90 THEN '90+'
            END AS age_range,
            `lua`.*
        FROM
            (/*(1) Select the base data from the `risk_scoring_history` table plus calculate the age of each entry*/
                SELECT
                    `sh`.`id` AS id,
                    `sh`.`risk_id` AS risk_id,
                    `sh`.`last_update` AS last_update,
                    DATEDIFF(NOW(), `sh`.`last_update`) AS age,
                    `sh`.`calculated_risk` AS calculated_risk
                FROM
                    `risk_scoring_history` sh
                GROUP BY
                    `sh`.`last_update`
            ) lua;
            
        /*(4) Create the 'temporary' table for data from the `risk_scoring_history` table*/
        CREATE TABLE `temp_rsh_last_update_age{$unique_key}`(
            PRIMARY KEY(`risk_id`, `age_range`),
            INDEX (`age_range`, `risk_id`)
        )
        SELECT
            `lua`.*
        FROM
            `temp_rsh_last_update_age_base{$unique_key}` lua
            LEFT JOIN `temp_rsh_last_update_age_base{$unique_key}` lua2 ON `lua`.`risk_id` = `lua2`.`risk_id` AND `lua`.`age_range` = `lua2`.`age_range` AND `lua`.`last_update` < `lua2`.`last_update`
        WHERE
            `lua2`.`id` IS NULL;

        /*Create the 'temporary' table for data from the `risk_scoring_contributing_impacts` and `contributing_risks_impact` tables*/
        CREATE TABLE `temp_contributing_risk_impact_data{$unique_key}`(
            PRIMARY KEY(`risk_scoring_id`, `contributing_risks_id`),
            INDEX (`contributing_risks_id`, `risk_scoring_id`)
        )
        SELECT
            `rs_impacts`.`risk_scoring_id`,
            `cs_impacts`.`contributing_risks_id`,
            `cs_impacts`.`value`,
            `cs_impacts`.`name`
        FROM
        	`risk_scoring_contributing_impacts` rs_impacts
          	LEFT JOIN `contributing_risks_impact` cs_impacts ON `cs_impacts`.`value` = `rs_impacts`.`impact` AND `cs_impacts`.`contributing_risks_id` = `rs_impacts`.`contributing_risk_id`;

        /*Create temporary table for the risk catalog entries grouped by the risk id for easier querying*/
        CREATE TABLE `temp_associated_risk_catalog_entries{$unique_key}`(
            PRIMARY KEY(`risk_id`)
        )
    ";

    // The data in the temp_associated_risk_catalog_entries temporary table depends on the query type
    if ($query_type == 3) { // column unique data
        $create_temporary_tables .= "
        SELECT
            `rsk`.`id` AS risk_id,
            GROUP_CONCAT(DISTINCT CONCAT(`rg`.`name`, '{$delimiter}', `rg`.`value`)  SEPARATOR '|') AS risk_mapping_risk_grouping,
            GROUP_CONCAT(DISTINCT CONCAT(`rc`.`number`, '{$delimiter}', `rc`.`id`)  SEPARATOR '|') AS risk_mapping_risk,
            GROUP_CONCAT(DISTINCT CONCAT(`rc`.`name`, '{$delimiter}', `rc`.`id`)  SEPARATOR '|') AS risk_mapping_risk_event,
            GROUP_CONCAT(DISTINCT CONCAT(`rf`.`name`, '{$delimiter}', `rf`.`value`)  SEPARATOR '|') AS risk_mapping_function
        FROM
            `risks` rsk
            LEFT JOIN `risk_catalog_mappings` rcm ON rcm.risk_id = rsk.id
            LEFT JOIN `risk_catalog` rc ON rc.id = rcm.risk_catalog_id
            LEFT JOIN `risk_grouping` rg ON `rc`.`grouping` = `rg`.`value`
            LEFT JOIN `risk_function` rf ON `rc`.`function` = `rf`.`value`
        GROUP BY
            `rsk`.`id`;
        ";
    } else {
        $create_temporary_tables .= "
        SELECT
            `rsk`.`id` AS risk_id,
            GROUP_CONCAT(DISTINCT `rc`.`id` SEPARATOR ',') AS risk_mapping_risk_catalog_ids,
            GROUP_CONCAT(`rg`.`name` SEPARATOR ', ') AS risk_mapping_risk_grouping,
            GROUP_CONCAT(DISTINCT `rg`.`value` SEPARATOR ',') AS risk_mapping_risk_grouping_ids,
            GROUP_CONCAT(`rc`.`number` SEPARATOR ', ') AS risk_mapping_risk,
            GROUP_CONCAT(`rc`.`name` SEPARATOR ', ') AS risk_mapping_risk_event,
            GROUP_CONCAT(`rc`.`description` SEPARATOR ', ') AS risk_mapping_description,
            GROUP_CONCAT(`rf`.`name` SEPARATOR ', ') AS risk_mapping_function,
            GROUP_CONCAT(DISTINCT `rf`.`value` SEPARATOR ',') AS risk_mapping_function_ids
        FROM
            `risk_catalog` rc
            LEFT JOIN `risk_grouping` rg ON `rc`.`grouping` = `rg`.`value`
            LEFT JOIN `risk_function` rf ON `rc`.`function` = `rf`.`value`
            LEFT JOIN `risk_catalog_mappings` rcm ON rcm.risk_catalog_id = rc.id
            LEFT JOIN `risks` rsk ON rsk.id = rcm.risk_id
        GROUP BY
            `rsk`.`id`;
        ";
    }

    /**
    * Query Type = 1
    *   Return total count
    */
    if($query_type == 1){
        $query = " SELECT SQL_CALC_FOUND_ROWS ".risks_query_select($column_filters);
    }
    /**
    * Query Type = 2
    *   No return total count
    */
    elseif($query_type == 2){
        $query = " SELECT ".risks_query_select($column_filters);
    }
    /**
    * Query Type = 3
    *   Unique column filter
    */
    elseif($query_type == 3){
        $query = " SELECT ".risks_unique_column_query_select();
    }
    
    $having_query .= " AND ".get_group_query_for_dynamic_risk($group, $group_value_from_db, "");

    $query .= " FROM ".risks_query_from($column_filters, $risks_by_team, $orderColumnName, $query_type)."\n"
        ." WHERE 1 "
        .$filter_query."\n" 
        .$status_query."\n"
        ." GROUP BY a.id "
        ." HAVING 1 "
        .$having_query."\n"
        .$order_query."\n"
    ;

    // Adding the unique key to the table names in the query
    $query = str_replace('temp_rrsh_last_update_age', "temp_rrsh_last_update_age{$unique_key}", $query);
    $query = str_replace('temp_rsh_last_update_age', "temp_rsh_last_update_age{$unique_key}", $query);
    $query = str_replace('temp_contributing_risk_impact_data', "temp_contributing_risk_impact_data{$unique_key}", $query);
    $query = str_replace('temp_associated_risk_catalog_entries', "temp_associated_risk_catalog_entries{$unique_key}", $query);

    $drop_temporary_tables = "
        /*Drop 'temporary' tables.*/
        DROP TABLE IF EXISTS `temp_rrsh_last_update_age{$unique_key}`;
        DROP TABLE IF EXISTS `temp_rsh_last_update_age{$unique_key}`;
        DROP TABLE IF EXISTS `temp_rrsh_last_update_age_base{$unique_key}`;
        DROP TABLE IF EXISTS `temp_rsh_last_update_age_base{$unique_key}`;
        DROP TABLE IF EXISTS `temp_contributing_risk_impact_data{$unique_key}`;
        DROP TABLE IF EXISTS `temp_associated_risk_catalog_entries{$unique_key}`;
    ";

    return [
        $query,
        $group_name,
        $create_temporary_tables,
        $drop_temporary_tables
    ];
}

/*********************************************
 * FUNCTION: RETURN QUERY TYPE BY PARAMETERS *
 *********************************************/
function get_query_type($need_total_count)
{
    if($need_total_count)
    {
        $query_type = 1;
    }
    else
    {
        $query_type = 2;
    }
    
    return $query_type;
}

/******************************************
 * FUNCTION: GET DATA FOR ONLY DYNAMIC RISK
 ******************************************/
function get_risks_only_dynamic($need_total_count, $status, $sort, $group, $column_filters, &$rowCount, $start=0, $length=10, $group_value_from_db="", $custom_query="", $bind_params=[], $orderColumnName=null, $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[], $force_user_id=null)
{
    global $lang;

    // Allow this to run as long as necessary
    ini_set('max_execution_time', 0);

    // Constants for encrypt column names
    $encrypt_column_names = ["subject", "risk_assessment", "additional_notes", "current_solution", "security_requirements", "security_recommendations", "comments"];
    
    // Requested encrypt column names
    $requested_manual_column_filters = [];
    if($orderColumnName == "management_review") $requested_manual_column_filters['mgmt_review'] = "";

    $havings = [];
    $having_query = "";
    $custom_date_filter = [];
    $date_fields = array("submission_date", "review_date", "planning_date", "mitigation_date", "closure_date");
    // If Column filters exist, make where query
    if($column_filters)
    {
        $wheres = [];
        foreach($column_filters as $name => $column_filter)
        {
            if(!$column_filter) continue;
            // Reject any filter key that is not a plain SQL identifier. The loop body
            // interpolates $name (and substrings of $name) into SQL identifier
            // positions in several branches, where bind parameters cannot help.
            if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) continue;
            $empty_filter = false;
            // If encryption extra is enabled and Column is a encrypted field
            if((encryption_extra() && in_array($name, $encrypt_column_names)) || $name == "next_review_date" || $name == "management_review" || $name == "id" || $name == "project_status" || in_array($name, $date_fields))
            {
                $requested_manual_column_filters[$name] = $column_filter;
            }
            elseif($name == "mitigation_planned")
            {
                if($column_filter[0] == "_empty") {
                    $column_filter[0] = "Mg==";
                    $empty_filter = true;
                }
                $column_filter = array_map("base64_decode", $column_filter);
                $mitigation_wheres = [];
                // If mitigation planned is YES
                if(in_array(1, $column_filter))
                {
                    $mitigation_wheres[] = " p.id IS NOT NULL ";
                }
                // If mitigation planned is NO
                if(in_array(2, $column_filter))
                {
                    $mitigation_wheres[] = " p.id IS NULL ";
                }
                $wheres[] = " (". implode(" OR ", $mitigation_wheres) . ") ";
            }
            elseif($name == "mitigation_accepted")
            {
                if($column_filter[0] == "_empty") {
                    $column_filter[0] = "Mg==";
                    $empty_filter = true;
                }
                $column_filter = array_map("base64_decode", $column_filter);
                $mitigation_wheres = [];
                // If mitigation accepted is YES
                if(in_array(1, $column_filter))
                {
                    $mitigation_wheres[] = " mau.risk_id IS NOT NULL ";
                }
                // If mitigation accepted is NO
                if(in_array(2, $column_filter))
                {
                    $mitigation_wheres[] = " mau.risk_id IS NULL ";
                }
                $wheres[] = " (". implode(" OR ", $mitigation_wheres) . ") ";
            }
            elseif($name == "affected_assets")
            {
                $asset_filters = $group_filters = [];
                if($column_filter[0] == "_empty") {
                    $column_filter[0] = "-asset";
                    $empty_filter = true;
                }
                
                // Get asset and asset group values from column filter values
                foreach($column_filter as $value)
                {
                    if(stripos($value, "asset") !== false)
                    {
                        $asset_filters[] = base64_decode(trim(str_replace("-asset", "", $value)));
                    }
                    elseif(stripos($value, "group") !== false)
                    {
                        $group_filters[] = base64_decode(trim(str_replace("-group", "", $value)));
                    }
                }
                
                $affected_assets_or_wheres = [];
                
                // Create query by asset filters
                if(count($asset_filters) > 0)
                {
                    $bind_param_name = "column_filter_". md5("affected_assets");
                    if($empty_filter) $affected_assets_or_wheres[] = "(FIND_IN_SET(rta.asset_id, :{$bind_param_name}) OR rta.asset_id IS NULL)";
                    else $affected_assets_or_wheres[] = " FIND_IN_SET(rta.asset_id, :{$bind_param_name}) ";
                    $bind_params[$bind_param_name] = implode(",", $asset_filters);
                }
                
                // Create query asset_group filters
                if(count($group_filters) > 0)
                {
                    $bind_param_name = "column_filter_". md5("affected_asset_groups");
                    $affected_assets_or_wheres[] = " FIND_IN_SET(rtag.asset_group_id, :{$bind_param_name}) ";
                    $bind_params[$bind_param_name] = implode(",", $group_filters);
                    $bind_param_name = "column_filter_". md5("affected_asset_ids_from_groups");
                    $affected_assets_or_wheres[] = " FIND_IN_SET(rta.asset_id, :{$bind_param_name}) ";
                    $asset_ids = get_asset_ids_from_groups($group_filters);
                    $bind_params[$bind_param_name] = implode(",", $asset_ids);
                }
                
                if(count($affected_assets_or_wheres) > 0)
                    $wheres[] = " ( " . implode(" OR ", $affected_assets_or_wheres) . " ) ";
                
            }
            elseif($name == "team") {

                $team_filter = [];
                if($column_filter[0] == "_empty") {
                    unset($column_filter[0]);
                    $team_filter []= "rtt.team_id IS NULL";
                }
                
                // Sanitize input data
                $column_filter = sanitize_int_array(array_map("base64_decode", $column_filter));

                if (!empty($column_filter)) {
                    $team_filter []= "rtt.team_id IN (" . implode(",", $column_filter) . ")";
                }
                
                if (!empty($team_filter)) {
                    $wheres []= "(" . implode(" OR ", $team_filter) . ")";
                }
            }
            else
            {
                // If column filter is array, decode base64 filter values
                if(is_array($column_filter))
                {
                    if($column_filter[0] == "_empty") {
                        unset($column_filter[0]);
                        $empty_filter = true;
                    }
                    $column_filter = array_map("base64_decode", $column_filter);

                    // For the status we need the original value there so we can escape the ',' character
                    if (!in_array($name, ['risk_status'])) {
                        $column_filter = implode(",", $column_filter);
                    }
                }

                $bind_param_name = "column_filter_". md5($name);

                switch($name){
                    // case "id":
                    //     $wheres[] = " a.id+1000 = :{$bind_param_name} ";
                    //     $bind_params[$bind_param_name] = $column_filter;
                    // break;
                    case "risk_status":
                        // What we're doing here is that we're replacing , with | in both the status and in the statuses we're looking for
                        // because FIND_IN_SET() was interpreting the , in the second parameter as a separator
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(REPLACE(a.status, ',', '|'), :{$bind_param_name}) OR a.status IS NULL)";
                        else $wheres[] = " FIND_IN_SET(REPLACE(a.status, ',', '|'), :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = is_array($column_filter) ? implode(",", array_map(fn($e) => str_replace(',', '|', $e), $column_filter)) : $column_filter;
                    break;
                    case "risk_assessment":
                        $wheres[] = " a.assessment like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "additional_notes":
                        $wheres[] = " a.notes like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "reference_id":
                    case "control_number":
                    case "subject":
                        $wheres[] = " a.{$name} like :".$bind_param_name;
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "current_solution":
                    case "security_requirements":
                    case "security_recommendations":
                    case "mitigation_percent":
                        $wheres[] = " p.{$name} like :".$bind_param_name;
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "regulation":
                        if($empty_filter) {
                            // It's possible that the regulation is not empty, just points to an invalid/missing framework, in which case it is considered unassigned
                            $wheres[] = " (FIND_IN_SET(a.regulation, :{$bind_param_name}) OR j.value is null) ";
                            $column_filter .= ",0";
                        } else {
                            $wheres[] = " FIND_IN_SET(a.regulation, :{$bind_param_name}) ";
                        }
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "source":
                    case "category":
                    case "owner":
                    case "manager":
                        $wheres[] = " FIND_IN_SET(a.{$name}, :{$bind_param_name}) ";
                        if($empty_filter) $column_filter .= ",0";
                        $bind_params[$bind_param_name] = $column_filter;
                    case "submitted_by":
                        // We should also display risks whose submitter is deleted when selecting Unassigned
                        if ($empty_filter) {
                            $wheres[] = " (FIND_IN_SET(a.{$name}, :{$bind_param_name}) OR i.value IS NULL) ";
                        } else {
                            $wheres[] = " FIND_IN_SET(a.{$name}, :{$bind_param_name}) ";
                        }
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "reviewer":
                        if($empty_filter) $wheres[] = " (FIND_IN_SET(l.{$name}, :{$bind_param_name}) OR l.{$name} IS NULL)";
                        else $wheres[] = " FIND_IN_SET(l.{$name}, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "review":
                        if($empty_filter) $wheres[] = " (FIND_IN_SET(l.{$name}, :{$bind_param_name}) OR l.{$name} IS NULL)";
                        else $wheres[] = " FIND_IN_SET(l.{$name}, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "next_step":
                        if($empty_filter) $wheres[] = " (FIND_IN_SET(l.{$name}, :{$bind_param_name}) OR l.{$name} IS NULL)";
                        else $wheres[] = " FIND_IN_SET(l.{$name}, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "project":
                        $wheres[] = " FIND_IN_SET(a.project_id, :{$bind_param_name}) ";
                        if($empty_filter) $column_filter .= ",0";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "location":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(rtl.location_id, :{$bind_param_name}) OR rtl.location_id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(rtl.location_id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "additional_stakeholders":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(rtas.user_id, :{$bind_param_name}) OR rtas.user_id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(rtas.user_id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "technology":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(rttg.technology_id, :{$bind_param_name}) OR rttg.technology_id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(rttg.technology_id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "risk_tags":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(tt.tag_id, :{$bind_param_name}) OR tt.tag_id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(tt.tag_id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "scoring_method":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(b.scoring_method, :{$bind_param_name}) OR b.scoring_method IS NULL)";
                        else $wheres[] = " FIND_IN_SET(b.scoring_method, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "planning_strategy":
                    case "mitigation_effort":
                    case "mitigation_owner":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(p.{$name}, :{$bind_param_name}) OR p.{$name} IS NULL)";
                        else $wheres[] = " FIND_IN_SET(p.{$name}, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "mitigation_cost":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(CONCAT(s.min_value, '-', s.max_value), :{$bind_param_name}) OR s.min_value IS NULL OR s.max_value IS NULL)";
                        else $wheres[] = " FIND_IN_SET(CONCAT(s.min_value, '-', s.max_value), :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "mitigation_team":
                        if($empty_filter) $wheres[] = $wheres[] = "( FIND_IN_SET(mtt.team_id, :{$bind_param_name}) OR mtt.team_id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(mtt.team_id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "mitigation_controls":
                        if($empty_filter) $wheres[] = "( FIND_IN_SET(mtc.control_id, :{$bind_param_name}) OR mtc.control_id IS NULL) ";
                        else $wheres[] = " FIND_IN_SET(mtc.control_id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "submission_date":
                    case "review_date":
                        $wheres[] = " a.{$name} like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "planning_date":
                        $wheres[] = " p.{$name} like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "closure_date":
                        $wheres[] = " o.{$name} like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "mitigation_date":
                        $wheres[] = " p.submission_date like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "comments":
                        $wheres[] = " l.comments like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "calculated_risk":
                    case "residual_risk":
                    case "days_open":
                    case "calculated_risk_30":
                    case "calculated_risk_60":
                    case "calculated_risk_90":
                    case "residual_risk_30":
                    case "residual_risk_60":
                    case "residual_risk_90":
                        $operator = get_operator_from_value($column_filters[$name."_operator"]);
                        $havings[] = " {$name} {$operator} :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "classic_likelihood":
                        $operator = get_operator_from_value($column_filters[$name."_operator"]);
                        $havings[] = " CLASSIC_likelihood_value {$operator} :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "classic_impact":
                        $operator = get_operator_from_value($column_filters[$name."_operator"]);
                        $havings[] = " CLASSIC_impact_value {$operator} :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "close_reason":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(o.close_reason, :{$bind_param_name}) OR o.close_reason IS NULL)";
                        else $wheres[] = " FIND_IN_SET(o.close_reason, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "close_out":
                        $wheres[] = " o.note like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "closed_by":
                        if($empty_filter) $wheres[] = " (FIND_IN_SET(o.user_id, :{$bind_param_name}) OR o.user_id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(o.user_id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;

                    case "threat_mapping":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(tcm.threat_catalog_id, :{$bind_param_name}) OR tcm.threat_catalog_id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(tcm.threat_catalog_id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    /*
                     * Had to solve the filtering for the Risk Category fields this way as they're trying to find multiple IDs in a list of IDs.
                     * Probably can be reworked once the risk's 'risk_catalog_mapping' field will be replaced by a junction table.
                     **/
                    case "risk_mapping":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(rcm.risk_catalog_id, :{$bind_param_name}) OR rcm.risk_catalog_id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(rcm.risk_catalog_id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "risk_mapping_risk":
                        $wheres[] = "(" . ($empty_filter ? "`a`.`id` NOT IN (SELECT DISTINCT `risk_id` FROM `risk_catalog_mappings`) OR " : "") . "
                            (
                                SELECT
                                    COUNT(5)
                                FROM `risk_catalog` rc
                                INNER JOIN `risk_catalog_mappings` rcm ON rc.id = rcm.risk_catalog_id
                                WHERE
                                    `rc`.`id` IN (" . implode(',', array_map('intval', explode(',', $column_filter))) . ")
                                    AND rcm.risk_id = `a`.`id`
                            ) > 0)";
                    break;

                    case "risk_mapping_risk_grouping":
                        
                        $wheres[] = "(" . ($empty_filter ? "`a`.`id` NOT IN (SELECT DISTINCT `risk_id` FROM `risk_catalog_mappings`) OR " : "") . "
                            (
                                SELECT
                                    COUNT(5)
                                FROM `risk_grouping` rg
                                WHERE
                                    `rg`.`value` IN (" . implode(',', array_map('intval', explode(',', $column_filter))) . ")
                                    AND FIND_IN_SET(`rg`.`value`, `associated_rc_entries`.`risk_mapping_risk_grouping_ids`)
                            ) > 0)";
                    break;
                        
                    case "risk_mapping_function":
                        $wheres[] = "(" . ($empty_filter ? "`a`.`id` NOT IN (SELECT DISTINCT `risk_id` FROM `risk_catalog_mappings`) OR " : "") . "
                            (
                                SELECT
                                    COUNT(5)
                                FROM `risk_function` rf
                                WHERE
                                    `rf`.`value` IN (" . implode(',', array_map('intval', explode(',', $column_filter))) . ")
                                    AND FIND_IN_SET(`rf`.`value`, `associated_rc_entries`.`risk_mapping_function_ids`)
                            ) > 0)";
                    break;

                    case "risk_mapping_description":
                        $havings[] = " {$name} like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;

                    default:
//                        $wheres[]
                    break;
                }
                if(stripos($name, "Contributing_Impact_") !== false) {
                    if(stripos($name, "_operator") === false) {
                        $impact_id = str_ireplace("Contributing_Impact_", "", $name);
                        $operator = get_operator_from_value($column_filters[$name."_operator"]);
                        $havings[] = " Contributing_Impact_{$impact_id}_value {$operator} :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = $column_filter;
                    }
                } else if(strtolower($name) == "contributing_likelihood"){
                    $operator = get_operator_from_value($column_filters[$name."_operator"]);
                    $havings[] = " Contributing_Likelihood_value {$operator} :{$bind_param_name} ";
                    $bind_params[$bind_param_name] = $column_filter;
                } else if(stripos($name, "CVSS_") !== false || stripos($name, "DREAD_") !== false || stripos($name, "OWASP_") !== false) {
                    $wheres[] = " b.{$name} like :{$bind_param_name} ";
                    $bind_params[$bind_param_name] = "%{$column_filter}%";
                }
            }
        }
        // If customization extra is enabled, add queries for custom fields
        if(customization_extra())
        {
            // Include the extra
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
            
            $active_fields = get_all_fields();
            foreach($active_fields as $active_field)
            {
                $empty_filter = false;
                // If this is an active custom field and related filter was submitted, add where condition
                if($active_field['is_basic'] == 0 && !empty($column_filters["custom_field_".$active_field['id']]))
                {
                    $custom_column_name = $custom_table_alias = "custom_field_".$active_field['id'];
                    $column_filter = $column_filters["custom_field_".$active_field['id']];
                    if($active_field['encryption'])
                    {
                        $requested_manual_column_filters[$custom_column_name] = $column_filter;
                    }
                    elseif($active_field['type'] == "dropdown")
                    {
                        // If column filter is array, decode base64 filter values
                        if(is_array($column_filter))
                        {
                            if($column_filter[0] == "_empty") {
                                $column_filter[0] = "MA==";
                                $empty_filter = true;
                            }
                            $column_filter = array_map("base64_decode", $column_filter);
                            $column_filter = implode(",", $column_filter);

                            $bind_param_name = "custom_field_column_filter_".$active_field['id'];
                            if($empty_filter) $wheres[] = "( FIND_IN_SET({$custom_table_alias}.value, :{$bind_param_name}) OR {$custom_table_alias}.value IS NULL)";
                            else $wheres[] = " FIND_IN_SET({$custom_table_alias}.value, :{$bind_param_name}) ";
                            $bind_params[$bind_param_name] = $column_filter;
                        }

                    }
                    elseif($active_field['type'] == "multidropdown" || $active_field['type'] == "user_multidropdown")
                    {
                        // If column filter is array, decode base64 filter values
                        if(is_array($column_filter))
                        {
                            if($column_filter[0] == "_empty") {
                                $column_filter[0] = "MA==";
                                $empty_filter = true;
                            }
                            $column_filter = array_map("base64_decode", $column_filter);
                            
                            $orWheres = [];
                            foreach($column_filter as $fitler_value)
                            {
                                $bind_param_name = "custom_field_column_filter_".$active_field['id'].md5($fitler_value);
                                if($empty_filter) $orWheres[] = " (FIND_IN_SET(:{$bind_param_name}, {$custom_table_alias}.value) OR {$custom_table_alias}.value IS NULL)";
                                else $orWheres[] = " FIND_IN_SET(:{$bind_param_name}, {$custom_table_alias}.value) ";
                                $bind_params[$bind_param_name] = $fitler_value;
                            }
                            
                            $wheres[] = " (" . implode(" OR ", $orWheres) . ") ";

                        }
                    }
                    elseif($active_field['type'] == "shorttext" || $active_field['type'] == "longtext")
                    {
                        $bind_param_name = "custom_field_column_filter_".$active_field['id'];
//                        $wheres[] = " CAST( {$custom_table_alias}.value AS TEXT ) LIKE :{$bind_param_name} ";
                        $wheres[] = " CONVERT( {$custom_table_alias}.value USING utf8 ) LIKE :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%".$column_filter."%";
                    }
                    elseif($active_field['type'] == "date"){
                        $custom_date_filter[$custom_column_name] = true;
                        $requested_manual_column_filters[$custom_column_name] = $column_filter;
                    }
                }
            }
        }
        
        if(count($wheres)) $custom_query .= " AND ". implode(" AND ", $wheres);
        if(count($havings)) $having_query .= " AND ". implode(" AND ", $havings);
    }
    $encryption_order = false;
    if(encryption_extra()&&($orderColumnName == "regulation" || $orderColumnName == "subject" || $orderColumnName == "project" || $orderColumnName == "security_requirements" || $orderColumnName == "next_review_date" || $orderColumnName == "comments" || $orderColumnName == "risk_assessment" || $orderColumnName == "additional_notes" || $orderColumnName == "current_solution" || $orderColumnName == "security_recommendations")){
        $encryption_order = true;
        if(isset($column_filters[$orderColumnName])) 
            $requested_manual_column_filters[$orderColumnName] = $column_filters[$orderColumnName];
        else 
            $requested_manual_column_filters[$orderColumnName] = "";
    }
    $query_type = get_query_type($need_total_count);
    
    list($query, $group_name, $create_temporary_tables, $drop_temporary_tables) = make_full_risks_sql($query_type, $status, $sort, $group, $column_filters, $group_value_from_db, $custom_query, $bind_params, $having_query, $orderColumnName, $orderDir, $risks_by_team, $teams, $owners, $ownersmanagers, force_user_id: $force_user_id);

    $start = (int)$start;
    $length = (int)$length;
    
    // Query the database
    $db = db_open();

    // Have to separately create the required temporary tables
    $stmt = $db->prepare($create_temporary_tables);
    $stmt->execute();

    $stmt = $db->prepare($query);
    //$stmt->bindParam(":orderColumnName", $orderColumnName);

    if($group_name != "none"){
        $stmt->bindParam(":group_value", $group_value_from_db, PDO::PARAM_STR);
    }
    
    if($bind_params){
        // Set params for teams, owners, owner managers
        foreach($bind_params as $bind_name => $custom_param)
        {
            $stmt->bindParam(":".$bind_name, $bind_params[$bind_name]);
        }
    }

    $stmt->execute();
    
    // Store the results in the risks array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $rowCount = $stmt->fetchColumn();

    $filtered_risks = [];
    
    $review_levels = get_review_levels();

    // If we're ordering by the 'management_review' column
    if ($orderColumnName === 'management_review') {
        // Calculate the 'management_review' values
        foreach($risks as &$risk) {

            $risk_level = get_risk_level_name($risk['calculated_risk']);
            $residual_risk_level = get_risk_level_name($risk['residual_risk']);

            // If next_review_date_uses setting is Residual Risk.
            if(get_setting('next_review_date_uses') == "ResidualRisk")
            {
                $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
            }
            // If next_review_date_uses setting is Inherent Risk.
            else
            {
                $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
            }         
            
            $management_review = management_review(convert_to_risk_id($risk['id']), $risk['mgmt_review'], $next_review, $is_html = false);
            
            $risk['management_review_text'] = $management_review;
        }
        unset($risk);

        // Sorting by the management review text as the normal 'management_review' field contains html
        usort($risks, function($a, $b) use ($orderDir){
            // For identical management reviews we're sorting on the id, so the results' order is not changing
            if ($a['management_review_text'] === $b['management_review_text']) {
                return (int)$a['id'] - (int)$b['id'];
            }
            if($orderDir == "asc") {
                return strcasecmp($a['management_review_text'], $b['management_review_text']);
            } else {
                return strcasecmp($b['management_review_text'], $a['management_review_text']);
            }
        });
    }

    foreach($risks as $risk)
    {
        $success = true;
        foreach($requested_manual_column_filters as $column_name => $val){
            if(stripos($column_name, "custom_field") !== false)
            {
                if(isset($custom_date_filter[$column_name]) && $custom_date_filter[$column_name] == true){
                    $date_str = format_datetime($risk[$column_name],"","");
                    if(stripos($date_str, $val) === false) {
                        $success = false;
                        break;
                    }
                }
                elseif(empty($risk[$column_name]) || (stripos(try_decrypt($risk[$column_name]), $val) === false)) {
                    $success = false;
                    break;
                }
            }
            elseif($column_name == "id")
            {
                if( stripos($risk['id'] + 1000, $val) === false ){
                    $success = false;
                    break;
                }
            }
            elseif($column_name == "subject" || $column_name == "current_solution" || $column_name == "security_recommendations" || $column_name == "security_requirements" || $column_name == "risk_assessment" || $column_name == "additional_notes" || $column_name == "comments")
            {
                if($val != "" &&  stripos(try_decrypt($risk[$column_name]), $val) === false ){
                    $success = false;
                    break;
                }
                if($encryption_order == true) {
                    $risk['encryption_order'] = try_decrypt($risk[$column_name]);
                }
            }
            elseif($column_name == "next_review_date")
            {
                $risk_level = get_risk_level_name($risk['calculated_risk']);
                $residual_risk_level = get_risk_level_name($risk['residual_risk']);

                // If next_review_date_uses setting is Residual Risk.
                if(get_setting('next_review_date_uses') == "ResidualRisk")
                {
                    $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }
                // If next_review_date_uses setting is Inherent Risk.
                else
                {
                    $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }
                
                if($val != "" && stripos($next_review, $val) === false ){
                    $success = false;
                    break;
                }
                if($encryption_order == true) {
                    $risk['encryption_order'] = ($next_review);
                }
            }
            elseif(in_array($column_name,$date_fields))
            {
                if($column_name == "submission_date" || $column_name == "review_date"){
                    $date_str = format_datetime($risk[$column_name],"","H:i");
                } else {
                    $date_str = format_datetime($risk[$column_name],"","");
                }
                if( stripos($date_str, $val) === false ){
                    $success = false;
                    break;
                }
            }
            elseif($column_name == "management_review")
            {
                if($val[0] == "_empty") {$val[0] = "MA==";}
                $risk_level = get_risk_level_name($risk['calculated_risk']);
                $residual_risk_level = get_risk_level_name($risk['residual_risk']);

                // If next_review_date_uses setting is Residual Risk.
                if(get_setting('next_review_date_uses') == "ResidualRisk")
                {
                    $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }
                // If next_review_date_uses setting is Inherent Risk.
                else
                {
                    $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }         
                
                $management_review = management_review(convert_to_risk_id($risk['id']), $risk['mgmt_review'], $next_review, $is_html = false);
                
                $available_review_texts = [
                    "0" => $lang['Unassigned'],
                    "1" => $lang['Yes'],
                    "2" => $lang['No'],
                    "3" => $lang['PASTDUE'],
                ];
                
                $filter_texts = array_map(function($value) use ($available_review_texts){
                    return $available_review_texts[$value];
                }, array_map("base64_decode", $val));
                if( in_array($management_review, $filter_texts) === false ){
                    $success = false;
                    break;
                }
            }
            elseif($column_name == "regulation" || $column_name == "project"){
                $risk['encryption_order'] = try_decrypt($risk[$column_name]);
            }
            elseif($column_name == "project_status")
            {
                if($val != "" && stripos($risk['project_status'], $val) === false ){
                    $success = false;
                    break;
                }
            }
            elseif($column_name == "owner" || $column_name == "manager" || $column_name == "submitted_by" || $column_name == "mitigation_owner")
            {
                if(array_search($risk[$column_name], array_map("base64_decode", $val)) === false){
                    $success = false;
                    break;
                }
            }
        }
        if($success) $filtered_risks[] = $risk;
    }

    if($encryption_order != false) {
        usort($filtered_risks, function($a, $b) use ($orderDir) {
            if($orderDir == "asc") 
                return strcasecmp($a['encryption_order'] ?? '', $b['encryption_order'] ?? '');
            else 
                return strcasecmp($b['encryption_order'] ?? '', $a['encryption_order'] ?? '');
        });
    }

    $risks_by_page = [];
    
    if($length == -1)
    {
        $risks_by_page = $filtered_risks;
    }
    else
    {
        for($i=$start; $i<count($filtered_risks) && $i<$start + $length; $i++){
            $risks_by_page[] = $filtered_risks[$i];
        }
    }
    $rowCount = count($filtered_risks);
    $filtered_risks = $risks_by_page;

    // Do the cleanup of tables that might have been left behind because of failed queries(and the drops not being able to run)
    temp_table_cleanup('dynamic_risk_report');

    // Have to separately drop this request's temporary tables
    $stmt = $db->prepare($drop_temporary_tables);
    $stmt->execute();

    db_close($db);

    return $filtered_risks;
}

/*************************
 * FUNCTION: RISKS QUERY *
 *************************/
function risks_query($status, $sort, $group, $column_filters, &$rowCount, $start=0, $length=10, $group_value_from_db="", $custom_query="", $bind_params=array(), $orderColumnName=null, $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[])
{
    global $lang, $escaper;

    $risks = get_risks_only_dynamic($need_total_count=true, $status, $sort, $group, $column_filters, $rowCount, $start, $length, $group_value_from_db, $custom_query, $bind_params, $orderColumnName, $orderDir, $risks_by_team, $teams, $owners, $ownersmanagers);
    
    // Get group name from $group
    list($group_name, $order_query) = get_group_name_for_dynamic_risk($group, "");
    
    // Initialize the data array
    $data = array();
    
    $risk_levels = get_risk_levels();
    $review_levels = get_review_levels();


    // For each risk in the risks array
    foreach($risks as $risk){
        $risk_id = (int)$risk['id'];

        $row = array();
        foreach ($risk as $field => $value) {
            switch ($field) {
                default:
                    $row[$field] = $value;
                    ${$field} = $value;
                    break;
                case 'subject':
                case 'regulation':
                case 'project':
                case 'comments':
                case 'risk_assessment':
                case 'additional_notes':
                case 'current_solution':
                case 'security_recommendations':
                case 'security_requirements':
                    $row[$field] = try_decrypt($risk[$field]);
                    break;
                case 'review_date':
                    $review_date = $risk['review_date'];
                    // If the risk hasn't been reviewed yet
                    if ($review_date == "0000-00-00 00:00:00")
                    {
                        // Set the review date to empty
                        $review_date = "";
                    } else $review_date = date(get_default_datetime_format("H:i"), strtotime($review_date));
                    $row['review_date'] = $review_date;
                    break;
                case 'scoring_method':
                    $row['scoring_method'] = $scoring_method = get_scoring_method_name($risk['scoring_method']);
                    break;
                case 'affected_assets':
                    // If the affected assets or affected asset groups is not empty
                    if ($risk['affected_assets'] || $risk['affected_asset_groups'])
                    {
                        // Do a lookup for the list of affected assets
                        $affected_assets = implode('', get_list_of_asset_and_asset_group_names($risk_id + 1000, true));
                    }
                    else $affected_assets = "";
                    $row['affected_assets'] = $affected_assets;
                    break;
                case 'planning_date':
                    $row['planning_date']  =  format_date($risk['planning_date']);
                    break;
                case 'mitigation_accepted':
                    // Store the raw localized label; consumers (api.php, get_risks_by_group)
                    // are responsible for HTML-escaping at output time.
                    $row['mitigation_accepted'] = $risk['mitigation_accepted'] ? $lang['Yes'] : $lang['No'];
                    break;
                case 'mitigation_date':
                    $row['mitigation_date'] = format_date($risk['mitigation_date']);
                    break;
            }
        }
        $row['mitigation_controls'] = $risk['mitigation_control_names'];
        $regulation = $risk['regulation'];
        $project = $risk['project'];

        $row['risk_level'] = $risk_level = get_risk_level_name_from_levels($risk['calculated_risk'], $risk_levels);
        $row['residual_risk_level'] = get_risk_level_name_from_levels($risk['residual_risk'], $risk_levels);

        // If next_review_date_uses setting is Residual Risk.
        if(get_setting('next_review_date_uses') == "ResidualRisk")
        {
            $next_review_date = next_review($row['residual_risk_level'], $risk_id, $risk['next_review'], false, $review_levels);
            $next_review_date_html = next_review($row['residual_risk_level'], $risk_id, $risk['next_review'], true, $review_levels);
        }
        // If next_review_date_uses setting is Inherent Risk.
        else
        {
            $next_review_date = next_review($row['risk_level'], $risk_id, $risk['next_review'], false, $review_levels);
            $next_review_date_html = next_review($row['risk_level'], $risk_id, $risk['next_review'], true, $review_levels);
        }

        $row['next_review_date'] = $next_review_date;
        $row['next_review_date_html'] = $next_review_date_html;

        if (!$risk['submission_date'] || stripos($risk['submission_date'], "0000-00-00") !== false)
        {
            // Set the review date to empty
            $month_submitted = $lang['Unassigned'];
        }
        else
        {
            $month_submitted = date('Y F', strtotime($risk['submission_date']));
        }
        $row['month_submitted'] = $month_submitted;


        // If the group name is not none
        if ($group_name != "none")
        {
            $group_value = ${$group_name};

            // If the selected group value is empty
            if ($group_value == "")
            {
                // Current group is Unassigned
                $group_value = $lang['Unassigned'];
            }
        }
        else $group_value = $group_name;
        $row['group_value'] = $group_value;

        // Create the new data array
        $data[] = $row;
    }

    // Return the data array
    return $data;
}

/************************************************
 * FUNCTION: GET DYANMICRISK UNIQUE COLUMN DATA *
 ************************************************/
function get_dynamicrisk_unique_column_data($status, $group, $group_value_from_db="", $custom_query="", $bind_params=array(), $orderColumnName=null, $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[])
{
    // Allow this to run as long as necessary
    ini_set('max_execution_time', 0);

    list($query, $group_name, $create_temporary_tables, $drop_temporary_tables) = make_full_risks_sql(3, $status, -1, $group, [], $group_value_from_db, $custom_query, $bind_params, "");

    // Query the database
    $db = db_open();

    // Have to separately create the required temporary tables
    $stmt = $db->prepare($create_temporary_tables);
    $stmt->execute();

    $stmt = $db->prepare($query);

    if($group_name != "none"){
        $stmt->bindParam(":group_value", $group_value_from_db, PDO::PARAM_STR);
    }

    if($custom_query){
        // Set params for teams, owners, owner managers
        for($i=0; $i<count($bind_params); $i++){
            $stmt->bindParam(":param".$i, $bind_params[$i]);
        }
    }
    $stmt->execute();
    // Store the results in the risks array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Do the cleanup of tables that might have been left behind because of failed queries(and the drops not being able to run)
    temp_table_cleanup('dynamic_risk_report');

    // Have to separately drop this request's temporary tables
    $stmt = $db->prepare($drop_temporary_tables);
    $stmt->execute();

    db_close($db);
    
    // Initialize the data array
    $data = array();

    // For each risk in the risks array
    foreach($risks as $risk){

        $data[] = array(
            // Risk columns
            "id" => $risk['id'],
            "risk_status" => $risk['status'],
            "risk_mapping" =>  $risk['risk_mapping'],
            "threat_mapping" =>  $risk['threat_mapping'],
            "risk_tags" => $risk["risk_tags"],
            "submitted_by" => $risk["submitted_by_for_dropdown"],
            "source" =>  $risk['source_for_dropdown'],
            "category" => $risk['category_for_dropdown'],
            "project" => $risk["project_for_dropdown"],
            "location" => $risk['location'],
            "regulation" => $risk["regulation_for_dropdown"],
            "affected_assets" => $risk["affected_assets"],
            "affected_asset_groups" => $risk["affected_asset_groups"],
            "technology" => $risk["technology"],
            "team" => $risk['team'],
            "additional_stakeholders" => $risk['additional_stakeholders'],
            "owner" => $risk["owner_for_dropdown"],
            "manager" => $risk["manager_for_dropdown"],
            "closed_by" => $risk["closed_by_for_dropdown"],
            "close_reason" => $risk["close_reason_for_dropdown"],
            
            // Mitigation columns
            "mitigation_effort" => $risk["mitigation_effort_for_dropdown"],
            "mitigation_cost" => $risk["mitigation_cost"],
            "mitigation_owner" => $risk["mitigation_owner_for_dropdown"],
            "planning_strategy" => $risk["planning_strategy_for_dropdown"],
            "mitigation_team" => $risk["mitigation_team"],
            "mitigation_controls" => $risk["mitigation_control_names"],
            
            // Review columns
            "reviewer" => $risk["reviewer_for_dropdown"],
            "review" => $risk["review_for_dropdown"],
            "next_step" => $risk["next_step_for_dropdown"],
            
            // Risk scoring columns
            "scoring_method" => $risk['scoring_method'],
            
            // Risk mapping columns
            "risk_mapping_risk_grouping" => $risk["risk_mapping_risk_grouping"],
            "risk_mapping_risk" => $risk["risk_mapping_risk"],
            "risk_mapping_function" => $risk["risk_mapping_function"],
        );
    }

    // Return the data array
    return $data;
}

/***************************
 * FUNCTION: GET PIE ARRAY *
 ***************************/
function get_pie_array($filter = null, $teams = false)
{
    $teams_query = generate_teams_query($teams, "rtt.team_id");
    
    // Open the database connection
    $db = db_open();

    // Check the filter for the query to use
    switch($filter)
    {
       case 'status':
            $field = "status";
            $stmt = $db->prepare("SELECT a.id, a.status FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id WHERE a.status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY a.status DESC");
            $stmt->execute();
            break;
        case 'location':
            $field = "name";
            $stmt = $db->prepare("SELECT a.id, b.name location FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `risk_to_location` rtl ON a.id=rtl.risk_id LEFT JOIN `location` b ON rtl.location_id=b.value  WHERE a.status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'source':
            $field = "name";
            $stmt = $db->prepare("SELECT a.id, b.name FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `source` b ON a.source = b.value WHERE status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'category':
            $field = "name";
            $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `category` b ON a.category = b.value WHERE status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'team':
            $field = "name";
            $stmt = $db->prepare("SELECT a.id, b.name team FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `team` b ON rtt.team_id=b.value WHERE a.status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'technology':
            $field = "name";
            $stmt = $db->prepare("SELECT a.id, b.name technology FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `risk_to_technology` rttg ON a.id=rttg.risk_id LEFT JOIN `technology` b ON rttg.technology_id=b.value WHERE status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'owner':
            $field = "name";
            $stmt = $db->prepare("SELECT a.id, b.name FROM `risks` a LEFT JOIN `user` b ON a.owner = b.value WHERE status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'manager':
            $field = "name";
            $stmt = $db->prepare("SELECT a.id, b.name FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `user` b ON a.manager = b.value WHERE status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'scoring_method':
            $field = "name";
            $stmt = $db->prepare("SELECT a.id, CASE WHEN scoring_method = 6 THEN 'Contributing Risk' WHEN scoring_method = 5 THEN 'Custom' WHEN scoring_method = 4 THEN 'OWASP' WHEN scoring_method = 3 THEN 'DREAD' WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS name, COUNT(*) AS num FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `risk_scoring` b ON a.id = b.id WHERE a.status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.scoring_method DESC");
            $stmt->execute();
            break;
        case 'close_reason':
            $field = "name";
            $stmt = $db->prepare("SELECT a.close_reason, a.risk_id as id, b.name, MAX(closure_date) FROM `closures` a JOIN `close_reason` b ON a.close_reason = b.value JOIN `risks` c ON a.risk_id = c.id LEFT JOIN `risk_to_team` rtt ON c.id=rtt.risk_id WHERE c.status = \"Closed\" AND {$teams_query} GROUP BY a.risk_id ORDER BY name DESC;");
            $stmt->execute();
            break;
        default:
            $stmt = $db->prepare("SELECT a.id, a.status, GROUP_CONCAT(DISTINCT b.name separator '; ') AS location, c.name AS source, d.name AS category, GROUP_CONCAT(DISTINCT e.name SEPARATOR ', ') AS team, GROUP_CONCAT(DISTINCT f.name SEPARATOR ', ') AS technology, g.name AS owner, h.name AS manager, CASE WHEN scoring_method = 6 THEN 'Contributing Risk' WHEN scoring_method = 5 THEN 'Custom' WHEN scoring_method = 4 THEN 'OWASP' WHEN scoring_method = 3 THEN 'DREAD' WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS scoring_method FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `team` e ON rtt.team_id=e.value LEFT JOIN `risk_to_location` rtl ON a.id=rtl.risk_id LEFT JOIN `location` b ON rtl.location_id=b.value LEFT JOIN `source` c ON a.source = c.value LEFT JOIN `category` d ON a.category = d.value LEFT JOIN risk_to_technology rttg ON a.id=rttg.risk_id LEFT JOIN `technology` f ON rttg.technology_id=f.value LEFT JOIN `user` g ON a.owner = g.value LEFT JOIN `user` h ON a.manager = h.value LEFT JOIN `risk_scoring` i ON a.id = i.id WHERE a.status != \"Closed\" AND {$teams_query} GROUP BY a.id; ");
            $stmt->execute();
            break;
    }

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Strip out risks the user should not have access to
        $array = strip_no_access_risks($array);
    }

    return $array;
}

/************************
 * FUNCTION: SORT ARRAY *
 ************************/
function sort_array($array, $sort)
{
    // Create the sort array
    $sortArray = array();

    // For each risk in the array
    foreach ($array as $risk)
    {
        // For each key value pair in the risk
        foreach ($risk as $key=>$value)
        {
            // If the key is not yet set in the sort array
            if (!isset($sortArray[$key]))
            {
                // Create a new array at that key
                $sortArray[$key] = array();
            }
            // Set the key to the value
            $sortArray[$key][] = $value;
        }
    }
    
    // Sort the array based on the sort value provided
    array_multisort($sortArray[$sort],SORT_ASC,$array);

    // Return the sorted array
    return $array;
}

/********************************
 * FUNCTION: COUNT ARRAY VALUES *
 ********************************/
function count_array_values($array, $sort)
{
    global $lang;

    // Initialize the value and count
    $value = "";
    $value_count = 1;
    $value_array = [];
    $data = [];

    // Count the number of risks for each value
    foreach ($array as $risk)
    {
        // Get the current value
        $current_value = $risk[$sort];
        if ($current_value == null) $current_value = $lang['Unassigned'];

        // If the value is not new
        if ($current_value == $value)
        {
            $value_count++;
        }
        else
        {
            // If the value is not empty
            if ($value != "")
            {
                // Add the previous value to the array
                $value_array[] = array($sort=>$value, 'num'=>$value_count);
            }

            // Set the new value and reset the count
            $value = $current_value;
            $value_count = 1;
        }
    }

    // Update the final value
    $value_array[] = array($sort=>$value, 'num'=>$value_count);

    // Create the data array
    foreach ($value_array as $row)
    {
        $data[] = array($row[$sort], (int)$row['num']);
    }

    return $data;
}

/************************************
 * FUNCTION: GET OPENED RISKS ARRAY *
 ************************************/
function get_opened_risks_array($timeframe) {

    // If team separation is not enabled
    if (!team_separation_extra()) {

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("
            SELECT 
                id, submission_date 
            FROM 
                risks 
            ORDER BY 
                submission_date;
        ");

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Otherwise team separation is enabled
    } else {

        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the opened risks stripped
        $array = strip_get_opened_risks_array();

    }

    // Set the defaults
    $counter = -1;
    $current_date = "";
    $open_date = array();
    $open_count = array();
    $open_total = array();

    // For each row
    foreach ($array as $key=>$row) {

        $date = '';

        // If the timeframe is by day
        if ($timeframe === "day") {

            // Set the date to the day
            $date = date('Y-m-d', strtotime($row['submission_date']));

        // If the timeframe is by month
        } else if ($timeframe === "month") {

            // Set the date to the month
            $date = date('Y-m', strtotime($row['submission_date']));

        // If the timeframe is by year
        } else if ($timeframe === "year") {

            // Set the date to the year
            $date = date('Y', strtotime($row['submission_date']));

        }

        // If the date is different from the current date
        if ($current_date != $date) {

            // Increment the counter
            $counter = $counter + 1;

            // Set the current date
            $current_date = $date;

            // Add the date
            $open_date[$counter] = $current_date;

            // Set the open count to 1
            $open_count[$counter] = 1;

            // If this is the first entry
            if ($counter == 0) {

                // Set the open total to 1
                $open_total[$counter] = 1;

            // Otherwise, add the value of this row to the previous value
            } else {

                // @phan-suppress-next-line PhanTypeInvalidDimOffset -- $counter > 0 guarantees $open_total[$counter-1] was set in a prior iteration
                $open_total[$counter] = $open_total[$counter-1] + 1;

            }

        // Otherwise, if the date is the same
        } else {

            // Increment the open count
            // @phan-suppress-next-line PhanTypeInvalidDimOffset -- the $current_date != $date branch above set this index in a prior iteration
            $open_count[$counter] = $open_count[$counter] + 1;

            // Update the open total
            // @phan-suppress-next-line PhanTypeInvalidDimOffset -- prior iteration set this index
            $open_total[$counter] = $open_total[$counter] + 1;

        }
    }

    // Return the open date array
    return array($open_date, $open_count);

}

/************************************
 * FUNCTION: GET CLOSED RISKS ARRAY *
 ************************************/
function get_closed_risks_array($timeframe) {

    // If team separation is not enabled
    if (!team_separation_extra()) {

        // Open the database connection
        $db = db_open();

        // Query the database
        //$stmt = $db->prepare("SELECT a.risk_id as id, a.closure_date, c.status FROM closures a LEFT JOIN risks c ON a.risk_id=c.id WHERE a.closure_date=(SELECT max(b.closure_date) FROM closures b WHERE a.risk_id=b.risk_id) AND c.status='Closed' GROUP BY a.risk_id ORDER BY closure_date;");
        $stmt = $db->prepare("
            SELECT 
                t1.id, 
                IFNULL(t2.closure_date, NOW()) closure_date, 
                t1.status 
            FROM 
                `risks` t1 
                LEFT JOIN `closures` t2 ON t1.close_id=t2.id
            WHERE 
                t1.status = 'Closed' 
            ORDER BY 
                IFNULL(t2.closure_date, NOW());
        ");

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Otherwise team separation is enabled
    } else {

        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the closed risks stripped
        $array = strip_get_closed_risks_array();

    }

    // Set the defaults
    $counter = -1;
    $current_date = "";
    $close_date = array();
    $close_count = array();
    $close_total = array();

    // For each row
    foreach ($array as $key=>$row) {

        $date = '';

        // If the timeframe is by day
        if ($timeframe === "day") {

            // Set the date to the day
            $date = date('Y-m-d', strtotime($row['closure_date']));
        
        // If the timeframe is by month
        } else if ($timeframe === "month") {

            // Set the date to the month
            $date = date('Y-m', strtotime($row['closure_date']));

        // If the timeframe is by year
        } else if ($timeframe === "year") {

            // Set the date to the year
            $date = date('Y', strtotime($row['closure_date']));

        }

        // If the date is different from the current date
        if ($current_date != $date) {

            // Increment the counter
            $counter = $counter + 1;

            // Set the current date
            $current_date = $date;

            // Add the date
            $close_date[$counter] = $current_date;

            // Set the close count to 1
            $close_count[$counter] = 1;

            // If this is the first entry
            if ($counter == 0) {

                // Set the close total to 1
                $close_total[$counter] = 1;

            // Otherwise, add the value of this row to the previous value
            } else {

                // @phan-suppress-next-line PhanTypeInvalidDimOffset -- $counter > 0 guarantees $close_total[$counter-1] was set in a prior iteration
                $close_total[$counter] = $close_total[$counter-1] + 1;

            }

        // Otherwise, if the date is the same
        } else {

            // Increment the closed count
            // @phan-suppress-next-line PhanTypeInvalidDimOffset -- prior iteration set this index
            $close_count[$counter] = $close_count[$counter] + 1;

            // Update the close total
            // @phan-suppress-next-line PhanTypeInvalidDimOffset -- prior iteration set this index
            $close_total[$counter] = $close_total[$counter] + 1;

        }
    }
    
    // Return the close date array
    return array($close_date, $close_count);

}

/****************************************
 * FUNCTION: ENCODE DATA BEFORE DISPLAY *
 ****************************************/
function encode_data_before_display($array)
{
    global $escaper;

    // Create a data array
    $data = array();

    // For each element in the array
    foreach ($array as $element)
    {
        $name = $element[0];
        $count = $element[1];
        $data[] = array($name, $count);
    }

    // Return the data array
    return $data;
}

/************************************
 * FUNCTION: RISKS AND CONTROLS TABLE *
 ************************************/
function risks_and_control_table($report, $sort_by, $projects, $status) {

    global $lang;
    global $escaper;

    if (count($_POST) > 3) {
        $control_framework = isset($_POST['control_framework']) ? $_POST['control_framework'] : [];
        $control_family = isset($_POST['control_family']) ? $_POST['control_family'] : [];
        $control_class = isset($_POST['control_class']) ? $_POST['control_class'] : [];
        $control_phase = isset($_POST['control_phase']) ? $_POST['control_phase'] : [];
        $control_priority = isset($_POST['control_priority']) ? $_POST['control_priority'] : [];
        $control_owner = isset($_POST['control_owner']) ? $_POST['control_owner'] : [];
    } else {
        $control_framework = "all";
        $control_family = "all";
        $control_class = "all";
        $control_phase = "all";
        $control_priority = "all";
        $control_owner = "all";
    }

    $data = array();


    $filters = array(
      'control_framework' => $control_framework,
      'control_family' => $control_family,
      'control_class' => $control_class,
      'control_phase' => $control_phase,
      'control_priority' => $control_priority,
      'control_owner' => $control_owner,
    );

    $rows = get_risks_and_controls_rows($report, $sort_by, $projects, $status, $filters);
    

    // If team separation is enabled
    if (team_separation_extra()) {

        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Just setting it true so we can later remove the risks
        // the user doesn't have permission to.
        // It's because all grouping type has different logic where its risk id is stored
        $separation = true;

    } else {
        $separation = false;
    }
    
    foreach ($rows as $gr_id => $row) {

        if ($separation) {
            $risks = strip_no_access_risks($row);
        } else {
            $risks = $row;
        }

        // Risks by Controls
        if ( $report == 0 ) {
            $header_color = get_risk_color($risks[0]['calculated_risk']);
            $control_frameworks = get_mapping_control_frameworks($gr_id);
            if (count($control_frameworks)) {
                $cf_table = "
                    <table border='1px' class='table table-bordered mb-2' style='background-color:{$escaper->escapeCssColor($header_color)}'>
                        <tr>
                            <th width='50%' style='background-color:{$escaper->escapeCssColor($header_color)}'>{$escaper->escapeHtml($lang['Framework'])}</th>
                            <th width='35%' style='background-color:{$escaper->escapeCssColor($header_color)}'>{$escaper->escapeHtml($lang['Control'])}</th>
                        </tr>
                ";
                foreach ($control_frameworks as $framework) {
                    $cf_table .= "
                        <tr>
                            <td style='background-color:{$escaper->escapeCssColor($header_color)}'>{$escaper->escapeHtml($framework['framework_name'])}</td>
                            <td style='background-color:{$escaper->escapeCssColor($header_color)}'>{$escaper->escapeHtml($framework['reference_name'])}</td>
                        </tr>
                    ";
                }
                $cf_table .= "
                    </table>
                ";
            } else {
                $cf_table = "";
            }
            $control_detail = "
                    <div class='moreellipses hide'>" . 
                        $escaper->escapeHtml($lang['ControlNumber']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_number']) . "</br>" . 
                        $escaper->escapeHtml($lang['ControlFrameworks']) . ":&nbsp;&nbsp;" . $cf_table. "</br>" . 
                        $escaper->escapeHtml($lang['ControlFamily']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_family_name']) . "</br>" . 
                        $escaper->escapeHtml($lang['ControlClass']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_class_name']) . "</br>" . 
                        $escaper->escapeHtml($lang['ControlPhase']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_phase_name']) . "</br>" . 
                        $escaper->escapeHtml($lang['ControlPriority']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_priority_name']) . "</br>" . 
                        $escaper->escapeHtml($lang['MitigationPercent']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['mitigation_percent']) . " %</br>" . 
                        $escaper->escapeHtml($lang['ControlOwner']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_owner_name']) . "</br>" . 
                        $escaper->escapeHtml($lang['Description']) . ":&nbsp;&nbsp;" . $escaper->purifyHtml($risks[0]['control_description']) . "</br>" . 
                        $escaper->escapeHtml($lang['SupplementalGuidance']) . ":&nbsp;&nbsp;" . $escaper->purifyHtml($risks[0]['supplemental_guidance']) . "
                    </div>
                    </br><a href='javascript:void(0)' class='morelink'>" . $escaper->escapeHtml($lang['ShowMore']) . "</a>
            ";

            echo "
                    <table class='table table-bordered table-condensed sortable mb-2'>
                        <thead>
                            <tr>
                                <th colspan='7' style='background-color:" . $escaper->escapeCssColor($header_color) . "'>
                                    <center>" . 
                                        $escaper->escapeHtml($lang['ControlLongName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_long_name']) . "</br>" . 
                                        $escaper->escapeHtml($lang['ControlShortName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_short_name']) . "</br>" . 
                                        $escaper->escapeHtml($lang['ControlRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['calculated_risk']) . 
                                        $control_detail . "
                                    </center>
                                </th>
                            </tr>
                            <tr>
                                <th style='width: 100px; min-width: 100px;' align='left'>" . $escaper->escapeHtml($lang['ID']) . "</th>
                                <th align='left' width='150px'>" . $escaper->escapeHtml($lang['Status']) . "</th>
                                <th align='left' width='300px'>" . $escaper->escapeHtml($lang['Subject']) . "</th>
                                <th align='left' width='200px'>" . $escaper->escapeHtml($lang['SiteLocation']) . "</th>
                                <th align='left' width='200px'>" . $escaper->escapeHtml($lang['Team']) . "</th>
                                <th align='left' width='100px'>" . $escaper->escapeHtml($lang['InherentRisk']) . "</th>
                                <th align='left' width='100px'>" . $escaper->escapeHtml($lang['DaysOpen']) . "</th>
                            </tr>
                        </thead>
                ";

            foreach ($risks as $risk) {

                $risk_id = convert_to_risk_id($risk['id']);
                $status = $risk['status'];
                $subject = try_decrypt($risk['subject']);
                $location = (!empty($risk['location']) ? $risk['location'] : "N/A");
                $team = (!empty($risk['team']) ? $risk['team'] : "N/A");

                $calculated_risk = $risk['calculated_risk'];
                $color = get_risk_color($calculated_risk);
                $dayssince = dayssince($risk['submission_date']);
                $dayssince = $risk['days_open'];
                
                // Display the individual asset information
                echo "
                        <tbody>
                            <tr>
                                <td style='width: 100px; min-width: 100px;' align='left'><a class='open-in-new-tab' href='../management/view.php?id=" . $risk_id . "'>" . $risk_id . "</a></td>
                                <td align='left' width='150px'>" . $escaper->escapeHtml($status) . "</td>
                                <td align='left' width='300px'>" . $escaper->escapeHtml($subject) . "</td>
                                <td align='left' width='200px'>" . $escaper->escapeHtml($location) . "</td>
                                <td align='left' width='200px'>" . $escaper->escapeHtml($team) . "</td>
                                <td align='center' class='risk-cell' bgcolor='" . $escaper->escapeHtml($color) . "' width='100px'>
                                    <div class='risk-cell-holder'>" . 
                                        $escaper->escapeHtml($calculated_risk) . "<span class='risk-color' style='background-color:" . $escaper->escapeCssColor($color) . "'></span>
                                    </div>
                                </td>
                                <td align='center' width='100px'>" . $dayssince . "</td>
                            </tr>
                ";
            }

            // End the last table
            echo "
                        </tbody>
                    </table>
            ";
        
        // Controls by Risks
        } else if ($report == 1) {

            // Get the variables for the row
            $origin_risk_id = $risks[0]['id'];
            $risk_id = convert_to_risk_id($origin_risk_id);
            $status = $risks[0]['status'];
            $subject = try_decrypt($risks[0]['subject']);
            $calculated_risk = $risks[0]['calculated_risk'];
            
            // Get the risk color
            $color = get_risk_color($calculated_risk);

            echo "
                <table width='100%' class='table table-bordered table-condensed mb-2' role='grid' style='width: 100%;'>
                    <tbody>
                        <tr>
                            <th style='background-color:{$escaper->escapeCssColor($color)};' bgcolor='{$escaper->escapeHtml($color)}' colspan='5'>
                                <center>
                                    <font color='#000000'>
                                        {$escaper->escapeHtml($lang['RiskId'])}:&nbsp;&nbsp;
                                        <a class='open-in-new-tab' href='../management/view.php?id={$escaper->escapeHtml($risk_id)}' style='color:#000000'>{$escaper->escapeHtml($risk_id)}</a>
                                        <br />{$escaper->escapeHtml($lang['Subject'])}:&nbsp;&nbsp;{$escaper->escapeHtml($subject)}
                                        <br />{$escaper->escapeHtml($lang['InherentRisk'])}:&nbsp;&nbsp;{$escaper->escapeHtml($calculated_risk)}&nbsp;&nbsp;({$escaper->escapeHtml(get_risk_level_name($calculated_risk))})
                                        <br />{$escaper->escapeHtml($lang['Status'])}:&nbsp;&nbsp;{$escaper->escapeHtml($status)}
                                    </font>
                                </center>
                            </th>
                        </tr>
                        <tr role='row' style='height: 0px;'>
                            <th class='dt-ordering-asc' aria-controls='mitigation-controls-table140955b56e1c6c5879' rowspan='1' colspan='1' style='width: 0px; padding-top: 0px; padding-bottom: 0px; border-top-width: 0px; border-bottom-width: 0px; height: 0px;' aria-sort='ascending' aria-label='&amp;nbsp;: activate to sort column descending'>
                                <div class='dt-sizing' style='height:0;overflow:hidden;'>&nbsp;
                                </div>
                            </th>
                        </tr>
                    ";

            foreach ($risks as $gr_id => $control) {
                $control_id = $control['control_id'];
                $control_long_name = $control['control_long_name'];
                $control_long_name = $control['control_long_name'];
                echo '
                        <tr role="row" class="odd">
                            <td class="sorting_1">
                                <div class="control-block item-block clearfix">
                                    <div class="control-block--header clearfix" data-project="">
                                        <a href="#" id="show-' . $origin_risk_id . '-' . $control_id . '" class="show-score" data-control-id="'. $escaper->escapeHtml($control_id) .'" data-risk-id="'. (int)$origin_risk_id .'"  onclick="" style="color: #3f3f3f;"> 
                                            <i class="fa fa-caret-right"></i>&nbsp; 
                                            <strong>' . $escaper->escapeHtml($lang['ControlLongName']) . '</strong>: &nbsp; &nbsp;'. $escaper->escapeHtml($control_long_name) .'
                                        </a>
                                        <a href="#" id="hide-' . $origin_risk_id . '-' . $control_id . '" class="hide-score" style="display: none;color: #3f3f3f; float: left; padding-bottom: 10px;" data-control-id="'. $escaper->escapeHtml($control_id) .'" data-risk-id="'. (int)$origin_risk_id .'" > 
                                            <i class="fa fa-caret-down"></i> &nbsp; 
                                            <strong>' . $escaper->escapeHtml($lang['ControlLongName']) . '</strong>: &nbsp; &nbsp; &nbsp;'. $escaper->escapeHtml($control_long_name) .'
                                        </a>
                                        <div class="control-block--row" id="control-content-' . $origin_risk_id . '-' . $control_id . '" style="display:none"></div>
                                        <input type="text" name="scroll_top" id="scroll_top" style="display:none" value="">
                                    </div>
                                </div>
                            </td>
                        </tr>
                ';
            }

            echo "
                    </tbody>
                </table>
            ";
            
        }
    }

    echo '
        <script>
            var moretext = "' . $escaper->escapeHtml($lang['ShowMore']) . '";
            var lesstext = "' . $escaper->escapeHtml($lang['ShowLess']) . '";
            $(document).ready( function(){
                $(".hide-score").css("display","none");
                $(".show-score").click(function(e){
                    e.preventDefault()
                    var control_id = $(this).data("control-id")
                    var risk_id = $(this).data("risk-id")
                    showControlDetails(control_id, risk_id)
                });
                
                $(".hide-score").click(function(e){
                    e.preventDefault()
                    var control_id = $(this).data("control-id")
                    var risk_id = $(this).data("risk-id")
                    hideControlDetails(control_id, risk_id)
                });
                $(".morelink").click(function(){
                    if($(this).hasClass("less")) {
                        $(this).removeClass("less");
                        $(this).html(moretext);
                    } else {
                        $(this).addClass("less");
                        $(this).html(lesstext);
                    }
                    $(this).parent().find(".moreellipses").toggle();
                    // $(this).prev().toggle();
                    return false;
                });
            });
            
            function showControlDetails( control_id , risk_id ){
            
                $("#show-"+risk_id + "-" +control_id).hide();
                $("#hide-"+risk_id + "-" +control_id).css("display","block");
                $("#control-content-"+risk_id + "-" +control_id).css("display","block");
                var height = $(window).scrollTop();
                
                $.ajax({
                    url: BASE_URL + "/api/v2/mitigation_controls/get_mitigation_control_info",
                    data: { "control_id": control_id, "scroll_top": height },
                    success: function(response){
                        $("#control-content-"+risk_id + "-" +control_id).html(response.data["control_info"]);
                        $("#scroll_top").val(response.data["scroll_top"]);
                    }
                });
            }
            
            function hideControlDetails( control_id , risk_id ){
                var scroll_top = $("#scroll_top").val();
                $("#hide-"+risk_id + "-" +control_id).css("display","none");
                $("#show-"+risk_id + "-" +control_id).show();
                $("#control-content-"+risk_id + "-" +control_id).css("display","none");
            }
            
      </script>
    ';

}
/**************************************************
 * FUNCTION: RETURN RISKS AND CONTROLS REPORT SQL *
 **************************************************/
function get_risks_and_controls_rows($report, $sort_by, $projects, $status, $filters)
{

    $control_framework = $filters['control_framework'];
    $control_family = $filters['control_family'];
    $control_class = $filters['control_class'];
    $control_phase = $filters['control_phase'];
    $control_priority = $filters['control_priority'];
    $control_owner = $filters['control_owner'];
    // Open the database
    $db = db_open();
    $order = "c.calculated_risk DESC";
    $params = [];

    $select = '';

    switch($status) {
        case 0: // Open
            $where_sql = " AND b.status != 'Closed' ";
            break;
        case 1: // Closed
            $where_sql = " AND b.status = 'Closed' ";
            break;
        case 2:
        default: // All status
            $where_sql = " ";
            break;
    }

    if($projects && is_array($projects)){
        $ids = [];
        $clauses = [];
        foreach($projects as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $clauses[] = "(b.project_id IS NULL OR b.project_id='')";
                }
                else
                {
                    $ids[] = $val;
                }
            }
        }
        if ($ids) {
            $in = build_in_clause($ids, "project", $params);
            $clauses[] = "b.project_id IN ($in)";
        }
        if ($clauses) {
            $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
        } else {
            $where_sql .= " AND 0 ";
        }
    }

    // Risks by Controls
    if($report == 0)
    {
        $select = "SELECT fc.id gr_id, b.*, c.calculated_risk, fc.short_name control_short_name, fc.long_name control_long_name, fc.id control_id
                , fc.control_number, fc.mitigation_percent, fc.description control_description, fc.supplemental_guidance, GROUP_CONCAT(DISTINCT f.name) framework_names, cc.name control_class_name
                , cph.name control_phase_name, cpr.name control_priority_name, cf.name control_family_name, cu.name control_owner_name
                , GROUP_CONCAT(DISTINCT l.name) location
                , GROUP_CONCAT(DISTINCT t.name) team
                , DATEDIFF(IF(b.status != 'Closed', NOW(), o.closure_date) , b.submission_date) days_open
        ";
        if($sort_by == 0) $order = "fc.long_name";
        else $order = "c.calculated_risk DESC";

        // If control class ID is requested.
        if($control_class && is_array($control_class)){
            $ids = [];
            $clauses = [];
            foreach($control_class as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $clauses[] = "(cc.value IS NULL OR cc.value='')";
                    }
                    else
                    {
                        $ids[] = $val;
                    }
                }
            }
            if ($ids) {
                $in = build_in_clause($ids, "control_class", $params);
                $clauses[] = "cc.value IN ($in)";
            }
            if ($clauses) {
                $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
            } else {
                $where_sql .= " AND 0 ";
            }
        }
        elseif($control_class == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }

        // If control phase ID is requested.
        if($control_phase && is_array($control_phase)){
            $ids = [];
            $clauses = [];
            foreach($control_phase as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $clauses[] = "(cph.value IS NULL OR cph.value='')";
                    }
                    else
                    {
                        $ids[] = $val;
                    }
                }
            }
            if ($ids) {
                $in = build_in_clause($ids, "control_phase", $params);
                $clauses[] = "cph.value IN ($in)";
            }
            if ($clauses) {
                $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
            } else {
                $where_sql .= " AND 0 ";
            }
        }
        elseif($control_phase == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }

        // If control priority ID is requested.
        if($control_priority && is_array($control_priority)){
            $ids = [];
            $clauses = [];
            foreach($control_priority as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $clauses[] = "(cpr.value IS NULL OR cpr.value='')";
                    }
                    else
                    {
                        $ids[] = $val;
                    }
                }
            }
            if ($ids) {
                $in = build_in_clause($ids, "control_priority", $params);
                $clauses[] = "cpr.value IN ($in)";
            }
            if ($clauses) {
                $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
            } else {
                $where_sql .= " AND 0 ";
            }
        }
        elseif($control_priority == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }

        // If control family ID is requested.
        if($control_family && is_array($control_family)){
            $ids = [];
            $clauses = [];
            foreach($control_family as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $clauses[] = "(cf.value IS NULL OR cf.value='')";
                    }
                    else
                    {
                        $ids[] = $val;
                    }
                }
            }
            if ($ids) {
                $in = build_in_clause($ids, "control_family", $params);
                $clauses[] = "cf.value IN ($in)";
            }
            if ($clauses) {
                $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
            } else {
                $where_sql .= " AND 0 ";
            }
        }
        elseif($control_family == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }

        // If control owner ID is requested.
        if($control_owner && is_array($control_owner)){
            $ids = [];
            $clauses = [];
            foreach($control_owner as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $clauses[] = "(cu.value IS NULL OR cu.value='')";
                    }
                    else
                    {
                        $ids[] = $val;
                    }
                }
            }
            if ($ids) {
                $in = build_in_clause($ids, "control_owner", $params);
                $clauses[] = "cu.value IN ($in)";
            }
            if ($clauses) {
                $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
            } else {
                $where_sql .= " AND 0 ";
            }
        }
        elseif($control_owner == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }

        // If control framework ID is requested.
        if($control_framework && is_array($control_framework)){
            $ids = [];
            $clauses = [];
            foreach($control_framework as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $clauses[] = "m.control_id IS NULL";
                    }
                    else
                    {
                        $ids[] = $val;
                    }
                }
            }
            if ($ids) {
                $in = build_in_clause($ids, "control_framework", $params);
                $clauses[] = "m_1.framework IN ($in)";
            }
            if ($clauses) {
                $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
            } else {
                $where_sql .= " AND 0 ";
            }
        }
        elseif($control_framework == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }

    }
    // Controls by Risks
    elseif($report == 1)
    {
        $select = "SELECT b.id gr_id, b.*, c.calculated_risk, fc.short_name control_short_name, fc.long_name control_long_name, fc.id control_id
                , GROUP_CONCAT(DISTINCT l.name) location
                , GROUP_CONCAT(DISTINCT t.name) team
                , DATEDIFF(IF(b.status != 'Closed', NOW(), o.closure_date) , b.submission_date) days_open
        ";
    }
    // Check the report
    $query = $select."
        FROM mitigations a 
            INNER JOIN risks b ON a.risk_id = b.id 
            INNER JOIN `mitigation_to_controls` mtc ON a.id=mtc.mitigation_id
            INNER JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            LEFT JOIN risk_scoring c ON b.id = c.id 
            LEFT JOIN risk_to_location rtl ON b.id=rtl.risk_id
            LEFT JOIN location l ON rtl.location_id=l.value
            LEFT JOIN risk_to_team rtt ON b.id=rtt.risk_id
            LEFT JOIN team t ON rtt.team_id=t.value
            LEFT JOIN closures o ON b.close_id = o.id
            LEFT JOIN `framework_control_mappings` m on fc.id=m.control_id
            LEFT JOIN `framework_control_mappings` m_1 on fc.id=m_1.control_id
            LEFT JOIN `frameworks` f on m.framework=f.value AND f.status=1
            LEFT JOIN `control_phase` cph on fc.control_phase=cph.value
            LEFT JOIN `control_class` cc on fc.control_class=cc.value
            LEFT JOIN `control_priority` cpr on fc.control_priority=cpr.value
            LEFT JOIN `family` cf on fc.family=cf.value
            LEFT JOIN `user` cu on fc.control_owner=cu.value
            LEFT JOIN projects p FORCE INDEX(PRIMARY) ON b.project_id = p.value
        WHERE 1 {$where_sql}
           GROUP BY 
            b.id, fc.id
    ORDER BY
        {$order}, c.calculated_risk DESC
        ;
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_INT);
    }
    $stmt->execute();
    // Store the results in the rows array
    $rows = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

    // Close the database
    db_close($db);

    return $rows;
}
/*******************************
 * FUNCTION: GET CONTROLS NAME *
 *******************************/
function get_control_number( $control_numbers )
{
    if ( $control_numbers ) {

        $control_number = str_getcsv($control_numbers, ',', '"', '');
        return $control_number;

    } else {
        return false;
    }
    
}

/**********************************
 * FUNCTION: GET IMPACTS COUNT *
 **********************************/
function get_impacts_count()
{
    $db = db_open();
    $stmt = $db->prepare("SELECT count(*) as count FROM impact");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return count($array)?intval($array[0]['count']):0;
}

/**********************************
 * FUNCTION: GET LIKELIHOODS COUNT *
 **********************************/
function get_likelihoods_count()
{
    $db = db_open();
    $stmt = $db->prepare("SELECT count(*) as count FROM likelihood");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return count($array)?intval($array[0]['count']):0;
}

function get_risks_by_appetite($type, $start, $length, $orderColumn, $orderDir, $column_filters = []) {

    if (!team_separation_extra()) 
    {
        $separation_query = "";
    } 
    else 
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        $separation_query = " AND ". get_user_teams_query("a");
    }
    $bind_params = [];
    $manual_column_filters = [];
    $having_query = "";
    foreach($column_filters as $name => $column_filter){
        if($name == "calculated_risk"){
            $separation_query .= " AND b.calculated_risk LIKE :calculated_risk ";
            $bind_params[$name] = "%{$column_filter}%";
        } elseif($name == "residual_risk"){
            $having_query .= " AND residual_risk LIKE :residual_risk ";
            $bind_params[$name] = "%{$column_filter}%";
        } else{
            $manual_column_filters[$name] = $column_filter;
        }
    }

    $orderColumns = ['id', 'subject', 'calculated_risk', 'residual_risk'];
    $orderColumn = $orderColumns[$orderColumn];
    if($orderColumn == "subject" && encryption_extra()) $orderColumn = "order_by_subject";

    // Make the big query
    $query = "
        SELECT
            a.id,
            a.subject,
            b.calculated_risk,
            ROUND(b.calculated_risk - (b.calculated_risk * IF(IFNULL(p.mitigation_percent,0) > 0, p.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100), 2) as residual_risk
        FROM
            risks a
            LEFT JOIN risk_scoring b ON a.id = b.id
            LEFT JOIN mitigations p ON a.id = p.risk_id
            LEFT JOIN `mitigation_to_controls` mtc ON p.id = mtc.mitigation_id 
            LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0 
            LEFT JOIN `risk_to_team` rtt on a.id=rtt.risk_id
            LEFT JOIN `risk_to_additional_stakeholder` rtas on a.id=rtas.risk_id
        WHERE a.status != \"Closed\"
            {$separation_query}
        GROUP BY
            a.id
        HAVING
            " . ($type === 'out' ? "residual_risk > :risk_appetite" : "residual_risk <= :risk_appetite") . "
            {$having_query}
        ORDER BY
           {$orderColumn} {$orderDir} 
        ";

    $limitQuery = $length == -1 ? "" : "Limit {$start}, {$length}";

    $query = "
        SELECT SQL_CALC_FOUND_ROWS t1.*
        FROM (
            {$query}
        ) t1
    ";
    if(!$manual_column_filters)  $query .= $limitQuery;
    $risk_appetite = get_setting("risk_appetite", 0);

    // Query the database
    $db = db_open();

    $stmt = $db->prepare($query);
    $stmt->bindParam(":risk_appetite", $risk_appetite, PDO::PARAM_STR);
    foreach($bind_params as $name => $bind_param){
        $stmt->bindParam(":{$name}", $bind_param);
    }

    $stmt->execute();

    // Store the results in the risks array
    $risks = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $rowCount = $stmt->fetchColumn();

    db_close($db);
    $risk_levels = get_risk_levels();

    $data = array();
    
    foreach($risks as $risk){
        $risk_id = (int)$risk['id'] + 1000;
        $subject = try_decrypt($risk['subject']);
        $calculated_risk = (float)$risk['calculated_risk'];
        $residual_risk = (float)$risk['residual_risk'];
        $success = true;
        foreach($manual_column_filters as $column_name => $val){
            if($column_name == "id") {
                if( stripos($risk_id, $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "subject") {
                if( stripos($subject, $val) === false ){
                    $success = false;
                    break;
                }
            }
        }
        if($success){
            $color = get_risk_color_from_levels($risk['calculated_risk'], $risk_levels);
            $residual_color = get_risk_color_from_levels($risk['residual_risk'], $risk_levels);

            // Create the new data array
            $data[] = array(
                "id" => $risk_id,
                "subject" => $subject,
                "calculated_risk" => $calculated_risk,
                "residual_risk" => $residual_risk,
                "color" => $color,
                "residual_color" => $residual_color,
            );
        }
    }
    if($manual_column_filters){
        $datas_by_page = [];
        if($length == -1)
        {
            $datas_by_page = $data;
        }
        else
        {
            for($i=$start; $i<count($data) && $i<$start + $length; $i++){
                $datas_by_page[] = $data[$i];
            }
        }
        $rowCount = count($data);
    } else {
        $datas_by_page = $data;
    }
    

    // Return the data array
    return array(
        "data" => $datas_by_page,
        "recordsTotal" => $rowCount,
        "recordsFiltered" => count($datas_by_page),
    );
}

function display_appetite_datatable($within=true) {

    global $lang, $escaper;

    $type = ($within ? "in" : "out");
    $tableID = ($within ? "within" : "outside") . "-appetite-table";

    echo "
        <div class='table-container' data-id='{$tableID}'>
            <table id='{$tableID}' width='100%' data-type='{$type}' class='risk-datatable table table-bordered table-striped table-condensed'>
                <thead>
                    <tr>
                        <th data-name='id' align='left' valign='top'>{$escaper->escapeHtml($lang['ID'])}</th>
                        <th data-name='subject' align='left' valign='top'>{$escaper->escapeHtml($lang['Subject'])}</th>
                        <th data-name='calculated_risk' align='center' valign='top'>{$escaper->escapeHtml($lang['InherentRisk'])}</th>
                        <th data-name='residual_risk' align='center' valign='top'>{$escaper->escapeHtml($lang['ResidualRisk'])}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    ";

}

function display_appetite_datatable_script() {
    echo "
        <script>
            function activateDatatable(id) {
                var raw_table = $('#' + id);
                //$('#'+id+' thead .filter').show();
                $('#'+id+' thead tr').clone(true).appendTo( '#'+id+' thead' );
                $('#'+id+' thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\" class=\"form-control\">').attr('name', title).attr('placeholder', title).appendTo($(this));
            
                    $( 'input', this ).on( 'keyup change', function () {
                        if ( riskDatatable.column(i).search() !== this.value ) {
                            riskDatatable.column(i).search( this.value ).draw();
                        }
                    } );
                } );
                var appetite_type = raw_table.data('type');
                var riskDatatable = raw_table.DataTable({
                    scrollX: true,
                    ordering: true,
                    orderCellsTop: true,
                    ajax: {
                        url: BASE_URL + '/api/v2/reports/appetite?type=' + appetite_type,
                        type: 'get'
                    },
                    order: [[2, 'desc']],
                    columnDefs : [
                        {
                            'targets' : [0],
                            'width' : '10%',                            
                        },
                        {
                            'targets' : [-1, -2],
                            'className' : 'risk-cell',
                            'width' : '15%'
                        }
                    ]
                });
            }

            $(document).ready(function(){
                activateDatatable('outside-appetite-table');
                activateDatatable('within-appetite-table');
            });
        </script>
    ";

}

function display_user_management_reports_datatable($type) {
    
    global $lang, $escaper;
    
    echo "
        <div class='table-container' data-id='{$type}-table'>
            <table id='{$type}-table' width='100%' data-type='{$type}' class='table risk-datatable table-bordered table-striped table-condensed table-margin-top' style='width: 100%'>
                <thead>
                    <tr>";
    if ($type === "users_of_teams") {
        echo "
                        <th data-name='name' align='left' valign='top' width='20%'>" . $escaper->escapeHtml($lang['TeamNames']) . "</th>
                        <th data-name='users' align='left' valign='top' width='80%'>" . $escaper->escapeHtml($lang['UsersHeader']) . "</th>";
    } elseif ($type === "teams_of_users") {
        echo "
                        <th data-name='name' align='left' valign='top' width='20%'>" . $escaper->escapeHtml($lang['Name']) . "</th>
                        <th data-name='username' align='left' valign='top' width='10%'>" . $escaper->escapeHtml($lang['Username']) . "</th>
                        <th data-name='status' align='left' valign='top' width='10%'>" . $escaper->escapeHtml($lang['Status']) . "</th>
                        <th data-name='teams' align='left' valign='top' width='60%'>" . $escaper->escapeHtml($lang['TeamsHeader']) . "</th>";
    } elseif ($type === "users_of_permissions") {
        echo "
                        <th data-name='name' align='left' valign='top' width='20%'>" . $escaper->escapeHtml($lang['Permissions']) . "</th>
                        <th data-name='users' align='left' valign='top' width='80%'>" . $escaper->escapeHtml($lang['UsersHeader']) . "</th>";
    } elseif ($type === "permissions_of_users") {
        echo "
                        <th data-name='name' align='left' valign='top' width='20%'>" . $escaper->escapeHtml($lang['Name']) . "</th>
                        <th data-name='username' align='left' valign='top' width='10%'>" . $escaper->escapeHtml($lang['Username']) . "</th>
                        <th data-name='status' align='left' valign='top' width='10%'>" . $escaper->escapeHtml($lang['Status']) . "</th>
                        <th data-name='permissions' align='left' valign='top' width='60%'>" . $escaper->escapeHtml($lang['Permissions']) . "</th>";
    } elseif ($type === "users_of_roles") {
        echo "
                        <th data-name='name' align='left' valign='top' width='20%'>" . $escaper->escapeHtml($lang['Roles']) . "</th>
                        <th data-name='users' align='left' valign='top' width='80%'>" . $escaper->escapeHtml($lang['UsersHeader']) . "</th>";
    }

    echo "
                    </tr>
                    <tr class='filter' style='display: none'>";
    if ($type === "users_of_teams") {
        echo "
                        <th data-name='teams' align='left' valign='top' width='20%'></th>
                        <th data-name='users' align='left' valign='top' width='80%'></th>";
    } elseif ($type === "teams_of_users") {
        echo "
                        <th data-name='users' align='left' valign='top' width='20%'></th>
                        <th data-name='usernames' align='left' valign='top' width='10%'></th>
                        <th data-name='statuses' align='left' valign='top' width='10%'></th>
                        <th data-name='teams' align='left' valign='top' width='60%'></th>";
    } elseif ($type === "users_of_permissions") {
        echo "
                        <th data-name='permissions' align='left' valign='top' width='20%'></th>
                        <th data-name='users' align='left' valign='top' width='80%'></th>";
    } elseif ($type === "permissions_of_users") {
        echo "
                        <th data-name='users' align='left' valign='top' width='20%'></th>
                        <th data-name='usernames' align='left' valign='top' width='10%'></th>
                        <th data-name='statuses' align='left' valign='top' width='10%'></th>
                        <th data-name='permissions' align='left' valign='top' width='60%'></th>";
    } elseif ($type === "users_of_roles") {
        echo "
                        <th data-name='roles' align='left' valign='top' width='20%'></th>
                        <th data-name='users' align='left' valign='top' width='80%'></th>";
    }
    
    echo "
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    ";
}

function get_user_management_reports_report_data($type, $mode = 'normal', $start = 0, $length = -1, $orderColumn = 0, $orderDir = 'asc', $filters = []) {
    
    $separation = team_separation_extra();
    
    if ($separation && ($type === 'users_of_teams' || $type === 'teams_of_users')) {
        return get_user_management_reports_report_data_separation($type, $mode, $start, $length, $orderColumn, $orderDir, $filters);
    }

    // $orderColumns = array(
    //     'users_of_permissions' => [''], // No ordering on the permission names as they're added to the results from PHP code so it's ordered there
    //     'permissions_of_users' => ['`u`.`name`', '`u`.`username`', '`u`.`enabled`'],
    //     'users_of_roles' => ['`users_roles`.`r_name`']
    // );
    $orderColumns = array(
        'users_of_permissions' => [2], // No ordering on the permission names as they're added to the results from PHP code so it's ordered there
        'permissions_of_users' => [2, 3, 4],
        'users_of_roles' => [2]
    );
    $orderColumn = $orderColumns[$type][$orderColumn];

    $query = '';

    if ($type === "permissions_of_users") {

        $filter_where_part = "";

        // If we're requesting just the names
        if ($mode === 'normal') {

            if (!empty($filters) && isset($filters['users']) && isset($filters['usernames']) && isset($filters['statuses']) && isset($filters['permissions'])) {

                $filter_where_part .= "WHERE
                    `u`.`value` IN (" . implode(',', array_map('intval', $filters['users'])) . ")
                    AND `u`.`value` IN (" . implode(',', array_map('intval', $filters['usernames'])) . ")
                    AND `u`.`enabled` IN (" . implode(',', array_map('intval', $filters['statuses'])) . ")";

                $filter_where_parts = [];

                // If the permission filter for '<No Permission>' is set, meaning we should display users who have no permission
                // the placeholder value must be removed and the filter condition added
                if (($key = array_search(-1, $filters['permissions'])) !== false) {
                    unset($filters['permissions'][$key]);
                    $filter_where_parts[] = '`perms`.`name` IS NULL';
                }
                if (count($filters['permissions'])) {
                    $filter_where_parts[] = "`perms`.`id` IN (" . implode(',', array_map('intval', $filters['permissions'])) . ")";                    
                }
                $filter_where_part .= "
                    AND (" . implode(' OR ', $filter_where_parts) . ")";
                
                // Generating the group_concat this way to make sure to only display the permissions that are filtered for
                $permissions_select = "GROUP_CONCAT(`perms`.`name` ORDER BY `perms`.`name` ASC SEPARATOR ', ') AS permissions";
                    

            } else {
                // If there's a filter that has no item selected then we're not returning a single result
                return array(
                    "data" => [],
                    "recordsTotal" => 0,
                    "recordsFiltered" => 0,
                );
            }
        } else { // If we're requesting all the data to be able to populate the unique table columns
            $permissions_select = "
                    CONCAT(
                        '[',
                        IF(
                            `perms`.`id` IS NOT NULL,
                            GROUP_CONCAT(
                                JSON_OBJECT(
                                    'value', `perms`.`id`,
                                    'name', `perms`.`name`
                                )
                                SEPARATOR ','
                            ),
                            ''
                        ),
                        ']'
                    ) AS permissions
                ";
        }
        
        $query = "
            SELECT
                `u`.`value` AS value,
                `u`.`name` AS name,
                `u`.`username` AS username,
                `u`.`enabled` AS status,
                {$permissions_select}
            FROM
                `user` u
                LEFT JOIN `permission_to_user` p2u ON `u`.`value` = `p2u`.`user_id`
                LEFT JOIN `permissions` perms ON `p2u`.`permission_id` = `perms`.`id`
                {$filter_where_part}
            GROUP BY
                `u`.`value`
            ORDER BY
               :orderColumn {$orderDir}
        ";

    } elseif ($type === "users_of_permissions") {

        $filter_where_part = "";
        // If we're requesting just the names
        if ($mode === 'normal') {
            
            if (!empty($filters) && isset($filters['permissions']) && isset($filters['users'])) {

                $users_select = "GROUP_CONCAT(DISTINCT concat(`u`.`name`, '(', `u`.`username`, ')') SEPARATOR ', ') AS users";

                $permission_filter_parts = [];
                // Removing the unnecessary marker value(-1)
                if (($key = array_search(-1, $filters['permissions'])) !== false) {
                    unset($filters['permissions'][$key]);
                    $permission_filter_parts[] = "`perms`.`id` IS NULL";
                }
                
                if ($filters['permissions']) {
                    $permission_filter_parts[] = "`perms`.`id` IN (" . implode(",", array_map('intval', $filters['permissions'])) . ")";
                }

                $filter_where_part = "
                    WHERE
                        (" . implode(' OR ', $permission_filter_parts) . ")
                        AND `u`.`value` IN (" . implode(',', array_map('intval', $filters['users'])) . ")";

            } else {
                // If there's a filter that has no item selected then we're not returning a single result
                return array(
                    "data" => [],
                    "recordsTotal" => 0,
                    "recordsFiltered" => 0,
                );
            }
        } else { // If we're requesting all the data to be able to populate the unique table columns
            $users_select = "
                CONCAT(
                    '[',
                    IF(
                        `u`.`name` IS NOT NULL,
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'value', `u`.`value`,
                                'name', `u`.`name`
                            )
                            SEPARATOR ','
                        ),
                        ''
                    ),
                    ']'
                ) AS users
            ";
        }

        $query = "
            SELECT
                `perms`.`id`,
                `perms`.`name`,
                {$users_select}
            FROM
            	`user` u
            	LEFT JOIN permission_to_user p2u ON u.value = p2u.user_id
                LEFT JOIN permissions perms ON p2u.permission_id = perms.id
            {$filter_where_part}
            GROUP BY
                `perms`.`name`
            ORDER BY
               :orderColumn {$orderDir}
        ";
                    
    } elseif ($type === "users_of_roles") {

        $filter_where_part = "";
        
        // If we're requesting just the names
        if ($mode === 'normal') {
            $users_select = "GROUP_CONCAT(DISTINCT concat(`users_roles`.`name`, '(', `users_roles`.`username`, ')') SEPARATOR ', ') AS users";
            
            if (!empty($filters) && isset($filters['roles']) && isset($filters['users'])) {
                
                $filter_where_part = "
                    WHERE
                        ";
                
                $filter_for_users_without_roles = false;
                $filter_for_roles_without_users = false;
                
                // Removing the unnecessary marker value(-1)
                if (($key = array_search(-1, $filters['roles'])) !== false) {
                    unset($filters['roles'][$key]);
                    $filter_for_users_without_roles = true;
                }
                
                if (($key = array_search(-1, $filters['users'])) !== false) {
                    unset($filters['users'][$key]);
                    $filter_for_roles_without_users = true;
                }
                
                // Create the filtering query parts accordingly
                if ($filters['roles']) {
                    $filter_where_part .= "
                        (`users_roles`.`r_value` IN (" . implode(',', array_map('intval', $filters['roles'])) . ") " . ($filter_for_roles_without_users ? "OR `users_roles`.`r_value` IS NULL" : "") . ")";
                } else {
                    $filter_where_part .= "`users_roles`.`r_value` IS NULL";
                }

                if ($filters['users']) {
                    $filter_where_part .= "
                        AND (`users_roles`.`value` IN (" . implode(',', array_map('intval', $filters['users'])) . ")" . ($filter_for_users_without_roles ? "OR `users_roles`.`value` IS NULL" : "") . ")";
                } else {
                    $filter_where_part .= " AND `users_roles`.`value` IS NULL";
                }
            } else {
                // If there's a filter that has no item selected then we're not returning a single result
                return array(
                    "data" => [],
                    "recordsTotal" => 0,
                    "recordsFiltered" => 0,
                );
            }
        } else { // If we're requesting all the data to be able to populate the unique table columns
            $users_select = "
                CONCAT(
                    '[',
                    IF(
                        `users_roles`.`name` IS NOT NULL,
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'value', `users_roles`.`value`,
                                'name', `users_roles`.`name`
                            )
                            SEPARATOR ','
                        ),
                        ''
                    ),
                    ']'
                ) AS users";
        }

        $query = "
            SELECT
                `users_roles`.`r_value` AS value,
                `users_roles`.`r_name` AS name,
                {$users_select}
            FROM 
                (SELECT `role`.`value` AS r_value, `role`.`name` AS r_name, `user`.* FROM `role` LEFT JOIN `user` ON `role`.`value` = `user`.`role_id`
                UNION ALL
                SELECT `role`.`value` AS r_value, `role`.`name` AS r_name, `user`.* FROM `role` RIGHT JOIN `user` ON `role`.`value` = `user`.`role_id` WHERE `role`.`value` IS NULL) users_roles
            {$filter_where_part}
            GROUP BY
                `users_roles`.`r_name`
            ORDER BY
               :orderColumn {$orderDir}
        ";
    }
    $db = db_open();
    
    if ($mode === 'normal') {
        $limitQuery = $length == -1 ? "" : "Limit :start, :length";
        
        $query = "
            SELECT SQL_CALC_FOUND_ROWS t1.*
            FROM (
                {$query}
            ) t1
            {$limitQuery}
        ";

        $db = db_open();

        $stmt = $db->prepare($query);
        $stmt->bindParam(":orderColumn", $orderColumn, PDO::PARAM_INT);
        if($length != -1){
            $stmt->bindParam(":start", $start, PDO::PARAM_INT);
            $stmt->bindParam(":length", $length, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        // Store the results in an array
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT FOUND_ROWS();");
        $stmt->execute();
        $rowCount = $stmt->fetchColumn();
        
        db_close($db);
        
        // Return the result with the additional data
        return array(
            "data" => $data,
            "recordsTotal" => $rowCount,
            "recordsFiltered" => count($data),
        );
    } else { // If we just need the raw data to be able to populate the unique column filters

        $stmt = $db->prepare($query);
        $stmt->bindParam(":orderColumn", $orderColumn, PDO::PARAM_INT);
        $stmt->execute();
        
        // Store the results in an array
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        db_close($db);
        
        // Return the raw result array
        return $data;
    }
}

/***************************************************
 * FUNCTION: DISPLAY CONTROL MATURITY SPIDER CHART *
 ***************************************************/
function display_control_maturity_spider_chart($framework_id) {
    
	global $escaper, $lang;

	// Get the control gap information for this framework
	$control_gaps = get_control_gaps($framework_id, "all_maturity", "control_family", "asc");

	// Create an empty current category
	$current_category = "null";

	// Create an empty categories array
	$categories = [];

	// Create an empty categories count array
	$categories_count = [];

	// Create an empty categories current maturity sum array
	$categories_current_maturity_sum = [];

	// Create an empty categories desired maturity sum array
	$categories_desired_maturity_sum = [];

	// Get the list of control gaps
	foreach ($control_gaps as $value) {

	    // Normalise to empty string if null so comparisons and array keys work consistently
	    $value['family_short_name'] = $value['family_short_name'] ?? '';

		// If this is not the current category
		if ($value['family_short_name'] != $current_category) {

			// Add the family to the category array
			$categories[] = $value['family_short_name'];

			// Set the count for this family to one
			// @phan-suppress-next-line PhanTypeInvalidDimOffset -- arrays are populated keyed by family_short_name
			$categories_count[$value['family_short_name']] = 1;

			// Put the first value in the categories current maturity sum array
			// @phan-suppress-next-line PhanTypeInvalidDimOffset
			$categories_current_maturity_sum[$value['family_short_name']] = $value['control_maturity'];

			// Put the first value in the categories desired maturity sum array
			// @phan-suppress-next-line PhanTypeInvalidDimOffset
			$categories_desired_maturity_sum[$value['family_short_name']] = $value['desired_maturity'];

			// Set the new current category
			$current_category = $value['family_short_name'];

        // If the category hasn't changed
		} else {

			// Increment the count
			// @phan-suppress-next-line PhanTypeInvalidDimOffset,PhanTypePossiblyInvalidDimOffset -- prior iteration in the if-branch set this key
			$categories_count[$value['family_short_name']] = $categories_count[$value['family_short_name']] + 1;

			// Increment the current maturity sum
			// @phan-suppress-next-line PhanTypeInvalidDimOffset,PhanTypePossiblyInvalidDimOffset
			$categories_current_maturity_sum[$value['family_short_name']] = $categories_current_maturity_sum[$value['family_short_name']] + $value['control_maturity'];

			// Increment the desired maturity sum
			// @phan-suppress-next-line PhanTypeInvalidDimOffset,PhanTypePossiblyInvalidDimOffset
			$categories_desired_maturity_sum[$value['family_short_name']] = $categories_desired_maturity_sum[$value['family_short_name']] + $value['desired_maturity'];

		}

	}

	// Create the empty data arrays
	$categories_current_maturity_average = [];
	$categories_desired_maturity_average = [];

	// For each category
	foreach ($categories as $key => $value) {

		// Average = sum / value
		// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset -- $categories built in lockstep with the sum/count arrays above
		$current_maturity_average = $categories_current_maturity_sum[$value] / $categories_count[$value];
		// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
		$desired_maturity_average = $categories_desired_maturity_sum[$value] / $categories_count[$value];
		$categories_current_maturity_average[] = round($current_maturity_average, 1);
		$categories_desired_maturity_average[] = round($desired_maturity_average, 1);

	}

	// Create the Current Maturity dataset
    $current_maturity_dataset = [
        "label" => $lang['CurrentControlMaturity'],
        "data" => empty($categories_current_maturity_average) ? [] : $categories_current_maturity_average,
    ];

	// Create the Desired Maturity dataset
    $desired_maturity_dataset = [
        "label" => $lang['DesiredControlMaturity'],
        "data" => empty($categories_desired_maturity_average) ? [] : $categories_desired_maturity_average,
    ];

    // Create the combined datasets array
    $datasets = [
        $current_maturity_dataset,
        $desired_maturity_dataset
    ];

    $title = $lang['CurrentVsDesiredMaturity'];
    $element_id = "control_maturity_spider_chart";
    create_chartjs_radar_code($title, $element_id, $categories, $datasets);

}

/*********************************************
 * FUNCTION: GET GROUP NAME FROM GROUP VALUE *
 *********************************************/
function get_group_name_from_value($group, $group_value)
{
    global $escaper, $lang;
    // Check the group
    switch ($group)
    {
        default:
            $group_name = $group_value;
            break;
        // Team
        case 6:
            $group_name = get_table_value_by_id("team", $group_value);
            break;
        // Technology
        case 7:
            $group_name = get_table_value_by_id("technology", $group_value);
            break;
        // Risk Scoring Method
        case 10:
            $group_name = get_table_value_by_id("scoring_methods", $group_value);
            break;
        case 11: // Regulation
        case 12: // Project
            $group_name = try_decrypt($group_value);
            break;
    }
    return $group_name?$group_name:$lang['Unassigned'];
}

/************************************
 * FUNCTION: ASSETS AND CONTROLS TABLE *
 ************************************/
function assets_and_controls_table($report, $sort_by) {

    global $lang;
    global $escaper;

    if (count($_POST) > 2) {
        $control_framework = isset($_POST['control_framework']) ? $_POST['control_framework'] : [];
        $control_family = isset($_POST['control_family']) ? $_POST['control_family'] : [];
        $control_class = isset($_POST['control_class']) ? $_POST['control_class'] : [];
        $control_phase = isset($_POST['control_phase']) ? $_POST['control_phase'] : [];
        $control_priority = isset($_POST['control_priority']) ? $_POST['control_priority'] : [];
        $control_owner = isset($_POST['control_owner']) ? $_POST['control_owner'] : [];
    } else {
        $control_framework = "all";
        $control_family = "all";
        $control_class = "all";
        $control_phase = "all";
        $control_priority = "all";
        $control_owner = "all";
    }

    $filters = array(
        'control_framework' => $control_framework,
        'control_family' => $control_family,
        'control_class' => $control_class,
        'control_phase' => $control_phase,
        'control_priority' => $control_priority,
        'control_owner' => $control_owner,
    );

    $rows = get_assets_and_controls_rows($report, $sort_by, $filters);

    // Controls by Asset (report == 1)
    if ($report == 1) {
        foreach ($rows as $asset_id => $group) {
            if (empty($group)) continue;

            $asset = $group[0];
            $asset_name = try_decrypt($asset['asset_name']);
            $asset_tags = isset($asset['asset_tags']) ? $asset['asset_tags'] : "N/A";
            $asset_value = isset($asset['asset_value']) ? get_asset_value_by_id($asset['asset_value']) : "N/A";
            $asset_location = isset($asset['asset_location']) ? $asset['asset_location'] : "N/A";
            $asset_teams = isset($asset['asset_teams']) ? $asset['asset_teams'] : "N/A";

            echo "
                <table class='table table-bordered table-condensed sortable mb-2'>
                    <thead>
                        <tr>
                            <th style='background-color: #e3e3e3' colspan='4'>
                                <center>
                                    " . $escaper->escapeHtml($lang['AssetName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_name) . "<br />
                                    " . $escaper->escapeHtml($lang['AssetTags']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_tags) . "<br />
                                    " . $escaper->escapeHtml($lang['AssetValue']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_value) . "<br />
                                    " . $escaper->escapeHtml($lang['AssetSiteLocation']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_location) . "<br />
                                    " . $escaper->escapeHtml($lang['AssetTeams']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_teams) . "<br />
                                </center>
                            </th>
                        </tr>
                        <tr>
                            <th align='left' width='40%'>" . $escaper->escapeHtml($lang['Control']) . "</th>
                            <th align='left' width='20%'>" . $escaper->escapeHtml($lang['CurrentMaturity']) . "</th>
                            <th align='left' width='20%'>" . $escaper->escapeHtml($lang['CurrentControlMaturity']) . "</th>
                            <th align='left' width='20%'>" . $escaper->escapeHtml($lang['DesiredControlMaturity']) . "</th>
                        </tr>
                    </thead>
                    <tbody>
            ";

            foreach ($group as $control) {
                $control_id = $control['control_id'];
                $control_long_name = $control['control_long_name'];
                $current_maturity = isset($control['current_maturity']) ? $control['current_maturity'] : "N/A";
                $control_current_maturity = isset($control['control_maturity_name']) ? $control['control_maturity_name'] : "N/A";
                $control_desired_maturity = isset($control['desired_maturity_name']) ? $control['desired_maturity_name'] : "N/A";

                echo '
                    <tr role="row">
                        <td>
                            <div class="control-block item-block clearfix">
                                <div class="control-block--header clearfix">
                                    <a href="#" id="show-asset-' . $escaper->escapeHtml($escaper->escapeJS($asset_id)) . '-' . $escaper->escapeHtml($escaper->escapeJS($control_id)) . '" class="show-control-score" data-control-id="'. $escaper->escapeHtml($escaper->escapeJS($control_id)) .'" data-asset-id="'. $escaper->escapeHtml($escaper->escapeJS($asset_id)) .'" style="color: #3f3f3f;"> 
                                        <i class="fa fa-caret-right"></i>&nbsp; 
                                        <strong>' . $escaper->escapeHtml($lang['ControlLongName']) . '</strong>: &nbsp; &nbsp;'. $escaper->escapeHtml($control_long_name) .'
                                    </a>
                                    <a href="#" id="hide-asset-' . $escaper->escapeHtml($asset_id) . '-' . $escaper->escapeHtml($control_id) . '" class="hide-control-score" style="display: none; color: #3f3f3f; float: left; padding-bottom: 10px;" data-control-id="'. $escaper->escapeHtml($control_id) .'" data-asset-id="'. (int)$asset_id .'"> 
                                        <i class="fa fa-caret-down"></i> &nbsp; 
                                        <strong>' . $escaper->escapeHtml($lang['ControlLongName']) . '</strong>: &nbsp; &nbsp; &nbsp;'. $escaper->escapeHtml($control_long_name) .'
                                    </a>
                                    <div class="control-block--row" id="control-content-asset-' . $escaper->escapeHtml($asset_id) . '-' . $escaper->escapeHtml($control_id) . '" style="display:none"></div>
                                </div>
                            </div>
                        </td>
                        <td align="left">' . $escaper->escapeHtml($current_maturity) . '</td>
                        <td align="left">' . $escaper->escapeHtml($control_current_maturity) . '</td>
                        <td align="left">' . $escaper->escapeHtml($control_desired_maturity) . '</td>
                    </tr>
                ';
            }

            echo "
                    </tbody>
                </table>
            ";
        }

    // Assets by Control (report == 0)
    } else {
        foreach ($rows as $control_id => $group) {
            if (empty($group)) continue;

            $control = $group[0];
            $control_long_name = $control['control_long_name'];
            $control_short_name = $control['control_short_name'];
            $control_number = $control['control_number'];
            $control_current_maturity = isset($control['control_maturity_name']) ? $control['control_maturity_name'] : "N/A";
            $control_desired_maturity = isset($control['desired_maturity_name']) ? $control['desired_maturity_name'] : "N/A";
            
            // Get control frameworks for the table
            $control_frameworks = get_mapping_control_frameworks($control_id);
            $cf_table = "";
            if (count($control_frameworks)) {
                $cf_table = "
                    <table border='1px' class='table table-bordered mb-2' style='background-color:#e3e3e3'>
                        <tr>
                            <th width='50%' style='background-color:#e3e3e3'>{$escaper->escapeHtml($lang['Framework'])}</th>
                            <th width='35%' style='background-color:#e3e3e3'>{$escaper->escapeHtml($lang['Control'])}</th>
                        </tr>
                ";
                foreach ($control_frameworks as $framework) {
                    $cf_table .= "
                        <tr>
                            <td style='background-color:#e3e3e3'>{$escaper->escapeHtml($framework['framework_name'])}</td>
                            <td style='background-color:#e3e3e3'>{$escaper->escapeHtml($framework['reference_name'])}</td>
                        </tr>
                    ";
                }
                $cf_table .= "
                    </table>
                ";
            }

            $control_detail = "
                <div class='moreellipses hide'>" . 
                    $escaper->escapeHtml($lang['ControlNumber']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control_number) . "</br>" . 
                    $escaper->escapeHtml($lang['ControlFrameworks']) . ":&nbsp;&nbsp;" . $cf_table. "</br>" . 
                    $escaper->escapeHtml($lang['ControlFamily']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control['control_family_name']) . "</br>" . 
                    $escaper->escapeHtml($lang['ControlClass']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control['control_class_name']) . "</br>" . 
                    $escaper->escapeHtml($lang['ControlPhase']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control['control_phase_name']) . "</br>" . 
                    $escaper->escapeHtml($lang['ControlPriority']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control['control_priority_name']) . "</br>" . 
                    $escaper->escapeHtml($lang['MitigationPercent']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control['mitigation_percent']) . " %</br>" . 
                    $escaper->escapeHtml($lang['ControlOwner']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control['control_owner_name']) . "</br>" . 
                    $escaper->escapeHtml($lang['Description']) . ":&nbsp;&nbsp;" . $escaper->purifyHtml($control['control_description']) . "</br>" . 
                    $escaper->escapeHtml($lang['SupplementalGuidance']) . ":&nbsp;&nbsp;" . $escaper->purifyHtml($control['supplemental_guidance']) . "
                </div>
                </br><a href='javascript:void(0)' class='morelink'>" . $escaper->escapeHtml($lang['ShowMore']) . "</a>
            ";

            echo "
                <table class='table table-bordered table-condensed sortable mb-2'>
                    <thead>
                        <tr>
                            <th colspan='7' style='background-color: #e3e3e3'>
                                <center>" . 
                                    $escaper->escapeHtml($lang['ControlLongName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control_long_name) . "</br>" . 
                                    $escaper->escapeHtml($lang['ControlShortName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control_short_name) . "</br>" . 
                                    $escaper->escapeHtml($lang['ControlNumber']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control_number) . "</br>" . 
                                    $escaper->escapeHtml($lang['CurrentControlMaturity']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control_current_maturity) . "</br>" . 
                                    $escaper->escapeHtml($lang['DesiredControlMaturity']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($control_desired_maturity) . 
                                    $control_detail . "
                                </center>
                            </th>
                        </tr>
                        <tr>
                            <th align='left' width='20%'>" . $escaper->escapeHtml($lang['AssetName']) . "</th>
                            <th align='left' width='10%'>" . $escaper->escapeHtml($lang['IPAddress']) . "</th>
                            <th align='left' width='15%'>" . $escaper->escapeHtml($lang['SiteLocation']) . "</th>
                            <th align='left' width='15%'>" . $escaper->escapeHtml($lang['Teams']) . "</th>
                            <th align='left' width='15%'>" . $escaper->escapeHtml($lang['AssetTags']) . "</th>
                            <th align='left' width='10%'>" . $escaper->escapeHtml($lang['AssetValuation']) . "</th>
                            <th align='left' width='15%'>" . $escaper->escapeHtml($lang['CurrentMaturity']) . "</th>
                        </tr>
                    </thead>
                    <tbody>
            ";

            foreach ($group as $asset) {
                $asset_name = try_decrypt($asset['asset_name']);
                $asset_ip = (isset($asset['asset_ip']) ? try_decrypt($asset['asset_ip']) : "N/A");
                $asset_ip = ($asset_ip != "" ? $asset_ip : "N/A");
                $asset_location = isset($asset['asset_location']) ? $asset['asset_location'] : "N/A";
                $asset_teams = isset($asset['asset_teams']) ? $asset['asset_teams'] : "N/A";
                $asset_tags = isset($asset['asset_tags']) ? $asset['asset_tags'] : "N/A";
                $asset_value = isset($asset['asset_value']) ? get_asset_value_by_id($asset['asset_value']) : "N/A";
                $current_maturity = isset($asset['current_maturity']) ? $asset['current_maturity'] : "N/A";

                echo "
                    <tr>
                        <td align='left'>" . $escaper->escapeHtml($asset_name) . "</td>
                        <td align='left'>" . $escaper->escapeHtml($asset_ip) . "</td>
                        <td align='left'>" . $escaper->escapeHtml($asset_location) . "</td>
                        <td align='left'>" . $escaper->escapeHtml($asset_teams) . "</td>
                        <td align='left'>" . $escaper->escapeHtml($asset_tags) . "</td>
                        <td align='left'>" . $escaper->escapeHtml($asset_value) . "</td>
                        <td align='left'>" . $escaper->escapeHtml($current_maturity) . "</td>
                    </tr>
                ";
            }

            echo "
                    </tbody>
                </table>
            ";
        }
    }

    echo '
        <script>
            var moretext = "' . $escaper->escapeHtml($lang['ShowMore']) . '";
            var lesstext = "' . $escaper->escapeHtml($lang['ShowLess']) . '";
            $(document).ready(function(){
                $(".hide-control-score").css("display","none");
                $(".show-control-score").click(function(e){
                    e.preventDefault();
                    var control_id = $(this).data("control-id");
                    var asset_id = $(this).data("asset-id");
                    showAssetControlDetails(control_id, asset_id);
                });
                
                $(".hide-control-score").click(function(e){
                    e.preventDefault();
                    var control_id = $(this).data("control-id");
                    var asset_id = $(this).data("asset-id");
                    hideAssetControlDetails(control_id, asset_id);
                });
                $(".morelink").click(function(){
                    if($(this).hasClass("less")) {
                        $(this).removeClass("less");
                        $(this).html(moretext);
                    } else {
                        $(this).addClass("less");
                        $(this).html(lesstext);
                    }
                    $(this).parent().find(".moreellipses").toggle();
                    return false;
                });
            });
            
            function showAssetControlDetails(control_id, asset_id){
                $("#show-asset-"+asset_id + "-" +control_id).hide();
                $("#hide-asset-"+asset_id + "-" +control_id).css("display","block");
                $("#control-content-asset-"+asset_id + "-" +control_id).css("display","block");
                var height = $(window).scrollTop();
                
                $.ajax({
                    url: BASE_URL + "/api/v2/mitigation_controls/get_mitigation_control_info",
                    data: { "control_id": control_id, "scroll_top": height },
                    success: function(response){
                        // control_info is a server-rendered HTML table. All user-supplied fields are
                        // escaped server-side via escapeHtml() or purifyHtml() (HTMLPurifier) before
                        // being included in the response. Using .html() here is intentional and safe.
                        $("#control-content-asset-"+asset_id + "-" +control_id).html(response.data["control_info"]);
                    }
                });
            }
            
            function hideAssetControlDetails(control_id, asset_id){
                $("#hide-asset-"+asset_id + "-" +control_id).css("display","none");
                $("#show-asset-"+asset_id + "-" +control_id).show();
                $("#control-content-asset-"+asset_id + "-" +control_id).css("display","none");
            }
        </script>
    ';
}

/*****************************************************
 * FUNCTION: BUILD IN CLAUSE FOR ASSETS AND CONTROLS *
 *****************************************************/
function build_in_clause(array $values, string $prefix, array &$params): string
{
    $placeholders = [];

    foreach ($values as $i => $val) {
        $key = ":{$prefix}_{$i}";
        $placeholders[] = $key;
        $params[$key] = (int)$val;
    }

    return implode(',', $placeholders);
}

/***********************************************
 * FUNCTION: GET ASSETS AND CONTROLS ROWS SQL *
 ***********************************************/
function get_assets_and_controls_rows($report, $sort_by, $filters) {

    $control_framework = $filters['control_framework'];
    $control_family = $filters['control_family'];
    $control_class = $filters['control_class'];
    $control_phase = $filters['control_phase'];
    $control_priority = $filters['control_priority'];
    $control_owner = $filters['control_owner'];

    // Open the database
    $db = db_open();

    $params = [];
    $where_sql = " WHERE fc.deleted = 0 ";

    // If control framework is requested
    if ($control_framework && is_array($control_framework)) {

        $ids = [];
        $clauses = [];

        foreach ($control_framework as $val) {
            $val = (int)$val;
            if ($val) {
                if ($val == -1) {
                    $clauses[] = "(fcm.framework IS NULL OR fcm.framework='')";
                } else {
                    $ids[] = $val;
                }
            }
        }

        if ($ids) {
            $in = build_in_clause($ids, "control_framework", $params);
            $clauses[] = "fcm.framework IN ($in)";
        }

        if ($clauses) {
            $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
        } else {
            $where_sql .= " AND 0 ";
        }
    } elseif ($control_framework != "all") {
        $where_sql .= " AND 0 ";
    }

    // If control class ID is requested
    if ($control_class && is_array($control_class)) {

        $ids = [];
        $clauses = [];

        foreach ($control_class as $val) {
            $val = (int)$val;
            if ($val) {
                if ($val == -1) {
                    $clauses[] = "(cc.value IS NULL OR cc.value='')";
                } else {
                    $ids[] = $val;
                }
            }
        }

        if ($ids) {
            $in = build_in_clause($ids, "control_class", $params);
            $clauses[] = "cc.value IN ($in)";
        }

        if ($clauses) {
            $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
        } else {
            $where_sql .= " AND 0 ";
        }

    } elseif ($control_class != "all") {
        $where_sql .= " AND 0 ";
    }

    // If control phase ID is requested
    if ($control_phase && is_array($control_phase)) {

        $ids = [];
        $clauses = [];

        foreach ($control_phase as $val) {
            $val = (int)$val;
            if ($val) {
                if ($val == -1) {
                    $clauses[] = "(cph.value IS NULL OR cph.value='')";
                } else {
                    $ids[] = $val;
                }
            }
        }
        
        if ($ids) {
            $in = build_in_clause($ids, "control_phase", $params);
            $clauses[] = "cph.value IN ($in)";
        }

        if ($clauses) {
            $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
        } else {
            $where_sql .= " AND 0 ";
        }

    } elseif ($control_phase != "all") {
        $where_sql .= " AND 0 ";
    }

    // If control priority ID is requested
    if ($control_priority && is_array($control_priority)) {

        $ids = [];
        $clauses = [];

        foreach ($control_priority as $val) {
            $val = (int)$val;
            if ($val) {
                if ($val == -1) {
                    $clauses[] = "(cpr.value IS NULL OR cpr.value='')";
                } else {
                    $ids[] = $val;
                }
            }
        }
                
        if ($ids) {
            $in = build_in_clause($ids, "control_priority", $params);
            $clauses[] = "cpr.value IN ($in)";
        }

        if ($clauses) {
            $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
        } else {
            $where_sql .= " AND 0 ";
        }

    } elseif ($control_priority != "all") {
        $where_sql .= " AND 0 ";
    }

    // If control family ID is requested
    if ($control_family && is_array($control_family)) {

        $ids = [];
        $clauses = [];

        foreach ($control_family as $val) {
            $val = (int)$val;
            if ($val) {
                if ($val == -1) {
                    $clauses[] = "(cf.value IS NULL OR cf.value='')";
                } else {
                    $ids[] = $val;
                }
            }
        }
                        
        if ($ids) {
            $in = build_in_clause($ids, "control_family", $params);
            $clauses[] = "cf.value IN ($in)";
        }

        if ($clauses) {
            $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
        } else {
            $where_sql .= " AND 0 ";
        }

    } elseif ($control_family != "all") {
        $where_sql .= " AND 0 ";
    }

    // If control owner ID is requested
    if ($control_owner && is_array($control_owner)) {

        $ids = [];
        $clauses = [];

        foreach ($control_owner as $val) {
            $val = (int)$val;
            if ($val) {
                if ($val == -1) {
                    $clauses[] = "(cu.value IS NULL OR cu.value='')";
                } else {
                    $ids[] = $val;
                }
            }
        }
                                
        if ($ids) {
            $in = build_in_clause($ids, "control_owner", $params);
            $clauses[] = "cu.value IN ($in)";
        }

        if ($clauses) {
            $where_sql .= " AND (" . implode(" OR ", $clauses) . ")";
        } else {
            $where_sql .= " AND 0 ";
        }

    } elseif ($control_owner != "all") {
        $where_sql .= " AND 0 ";
    }

    // Assets by Control (report == 0)
    if ($report == 0) {
        if ($sort_by == 0) {
            $order = "fc.short_name ASC";
        } else {
            $order = "fc.control_number ASC";
        }

        $sql = "
            SELECT 
                fc.id AS control_id,
                fc.short_name AS control_short_name,
                fc.long_name AS control_long_name,
                fc.control_number,
                fc.description AS control_description,
                fc.supplemental_guidance,
                fc.mitigation_percent,
                cm.name AS control_maturity_name,
                dm.name AS desired_maturity_name,
                cc.name AS control_class_name,
                cph.name AS control_phase_name,
                cpr.name AS control_priority_name,
                cf.name AS control_family_name,
                cu.name AS control_owner_name,
                a.id AS asset_id,
                a.name AS asset_name,
                a.ip AS asset_ip,
                a.value AS asset_value,
                loc.name AS asset_location,
                GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS asset_teams,
                GROUP_CONCAT(DISTINCT tg.tag SEPARATOR ', ') AS asset_tags,
                GROUP_CONCAT(DISTINCT cta_cm.name SEPARATOR ', ') AS current_maturity
            FROM framework_controls fc
                LEFT JOIN framework_control_mappings fcm ON fc.id = fcm.control_id
                LEFT JOIN control_class cc ON fc.control_class = cc.value
                LEFT JOIN control_phase cph ON fc.control_phase = cph.value
                LEFT JOIN control_priority cpr ON fc.control_priority = cpr.value
                LEFT JOIN family cf ON fc.family = cf.value
                LEFT JOIN user cu ON fc.control_owner = cu.value
                LEFT JOIN control_maturity cm ON fc.control_maturity = cm.value
                LEFT JOIN control_maturity dm ON fc.desired_maturity = dm.value
                INNER JOIN control_to_assets cta ON fc.id = cta.control_id
                INNER JOIN assets a ON cta.asset_id = a.id
                LEFT JOIN location loc ON a.location = loc.value
                LEFT JOIN team t ON FIND_IN_SET(t.value, a.teams)
                LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id AND tt.type = 'asset'
                LEFT JOIN tags tg ON tg.id = tt.tag_id
                LEFT JOIN control_maturity cta_cm ON cta.control_maturity = cta_cm.value
            {$where_sql}
            GROUP BY fc.id, a.id
            ORDER BY {$order}, a.name ASC
        ";

        $stmt = $db->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by control_id
        $rows = [];
        foreach ($results as $row) {
            $control_id = $row['control_id'];
            if (!isset($rows[$control_id])) {
                $rows[$control_id] = [];
            }
            $rows[$control_id][] = $row;
        }

    // Controls by Asset (report == 1)
    } else {
        $sql = "
            SELECT 
                a.id AS asset_id,
                a.name AS asset_name,
                a.ip AS asset_ip,
                a.value AS asset_value,
                loc.name AS asset_location,
                GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS asset_teams,
                GROUP_CONCAT(DISTINCT tg.tag SEPARATOR ', ') AS asset_tags,
                fc.id AS control_id,
                fc.short_name AS control_short_name,
                fc.long_name AS control_long_name,
                fc.control_number,
                fc.description AS control_description,
                fc.supplemental_guidance,
                fc.mitigation_percent,
                cm.name AS control_maturity_name,
                dm.name AS desired_maturity_name,
                cc.name AS control_class_name,
                cph.name AS control_phase_name,
                cpr.name AS control_priority_name,
                cf.name AS control_family_name,
                cu.name AS control_owner_name,
                GROUP_CONCAT(DISTINCT cta_cm.name SEPARATOR ', ') AS current_maturity
            FROM assets a
                INNER JOIN control_to_assets cta ON a.id = cta.asset_id
                INNER JOIN framework_controls fc ON cta.control_id = fc.id
                LEFT JOIN framework_control_mappings fcm ON fc.id = fcm.control_id
                LEFT JOIN control_class cc ON fc.control_class = cc.value
                LEFT JOIN control_phase cph ON fc.control_phase = cph.value
                LEFT JOIN control_priority cpr ON fc.control_priority = cpr.value
                LEFT JOIN family cf ON fc.family = cf.value
                LEFT JOIN user cu ON fc.control_owner = cu.value
                LEFT JOIN control_maturity cm ON fc.control_maturity = cm.value
                LEFT JOIN control_maturity dm ON fc.desired_maturity = dm.value
                LEFT JOIN location loc ON a.location = loc.value
                LEFT JOIN team t ON FIND_IN_SET(t.value, a.teams)
                LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id AND tt.type = 'asset'
                LEFT JOIN tags tg ON tg.id = tt.tag_id
                LEFT JOIN control_maturity cta_cm ON cta.control_maturity = cta_cm.value
            {$where_sql}
            GROUP BY a.id, fc.id
            ORDER BY a.name ASC, fc.short_name ASC
        ";

        $stmt = $db->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by asset_id
        $rows = [];
        foreach ($results as $row) {
            $asset_id = $row['asset_id'];
            if (!isset($rows[$asset_id])) {
                $rows[$asset_id] = [];
            }
            $rows[$asset_id][] = $row;
        }
    }

    // Close the database connection
    db_close($db);

    return $rows;
}

/*******************************************************************************************************
 * FUNCTION: GET DYNAMIC RISKS TABLE STRING
 * this function is to get DRR tables string 
 * and it comes from the download_risks_by_table() of the export functionality on the DRR page
 * $option = 'html' corresponses to the former's $option = 'download' 
 * Not sure $option and $download_group_value are necessary yet but it's better that we leave them now 
 *******************************************************************************************************/
function get_dynamic_risks_table_string($filter_status, $group, $sort, $download_group_value="", $selected_columns=[], $column_filters=[], $force_user_id = null) {

    global $lang;
    global $escaper;
    
    // We should assign all the inputted parameters to this function 
    // because they are all used in the get_dynamic_risks_data_for_output function
    list($xlsHeader, $xlsRows, $grouped) = get_dynamic_risks_data_for_output($filter_status, $group, $sort, $download_group_value="", $selected_columns, $column_filters, $order_column=null, $order_dir="asc", $option="html", force_user_id: $force_user_id);

    /***********Generate Table String**************/
    $table_string = "
        <table class='table table-striped table-bordered table-hover' id='dynamic_risks_table'>
    ";
    // Store the number of empty rows we added so we can account for them when calculating the position of the cells we want to merge 
    $empty_rows = 0;

    // Save column count so it only has to be calculated once
    $columnCount = count($xlsHeader);

    foreach ($xlsRows as $position => $xlsRow) {

        // If grouped and it's a non-array row then it's a group header
        if ($grouped && !is_array($xlsRow)) {

            // Add an empty row before the group header if it's not the FIRST group
            if ($position > 0) {
                $table_string .= "
            <tr><td colspan = '{$columnCount}'></td></tr>
                ";
                $empty_rows += 1;
            }

            // create the group header with the centered style
            $table_string .= "
            <tr><td align='center' colspan = '{$columnCount}'>{$xlsRow}</td></tr>
            ";
                
        } else {

            $table_string .= "
            <tr>
            ";

            // If the row is an array, it means it's a risk row
            foreach ($xlsRow as $cell) {

                // If the cell is empty
                if ($cell == null || $cell == "") {
                    $cell = "&nbsp;"; // Replace it with a non-breaking space
                }

                // Escape the cell value
                $cell = $escaper->escapeHtml($cell);

                // Add the cell to the row string
                $table_string .= "
                <td>{$cell}</td>
                ";

            }

            // Close the row
            $table_string .= "
            </tr>
            ";

        }
    }

    // Close the table
    $table_string .= "
        </table>
    ";

    return $table_string;

}

/**********************************************************************
 * FUNCTION: GET DYNAMIC RISKS TABLE STRING FROM SAVED SELECTIONS
 * This function is to get DRR tables string from saved selections
 * It is not implemented yet, but it is a placeholder for future use
 **********************************************************************/
function get_dynamic_risks_table_string_from_saved_selections($selection_id, $force_user_id = null) {

    global $lang;
    global $escaper;

    // Allow this to run as long as necessary
    ini_set('max_execution_time', 0);

    // Open the database connection
    $db = db_open();

    // Get the saved selection
    $stmt = $db->prepare("SELECT * FROM dynamic_saved_selections WHERE value = :selection_id;");
    $stmt->bindParam(":selection_id", $selection_id, PDO::PARAM_INT);
    $stmt->execute();
    $selection = $stmt->fetch(PDO::FETCH_ASSOC);

    // If the selection is not found
    if (!$selection) {
        return "<p class='text-danger'>{$lang['SelectionNotFound']}</p>";
    }

    // Get the filter status, group, sort, and download group value from the selection
    $custom_selection_settings = json_decode($selection['custom_selection_settings'], true);
    if ($custom_selection_settings) {
        $filter_status = $custom_selection_settings['status'] ?? 0;
        $group = $custom_selection_settings['group'] ?? 0;
        $sort = $custom_selection_settings['sort'] ?? 0;
    } else {
        // Default values if custom settings are not set
        $filter_status = 0;
        $group = 0;
        $sort = 0;
    }

    // Get the selected columns and column filters from the selection
    $selected_columns_original = json_decode($selection['custom_display_settings'], true);
    $column_filters_original = json_decode($selection['custom_column_filters'], true);

    $selected_columns = [];
    foreach ($selected_columns_original as $selected_column) {
        $selected_columns[$selected_column] = true;
    }

    $column_filters = [];
    foreach ($column_filters_original as $column_filter) {
        if (isset($column_filter[0]) && isset($column_filter[1])) {
            $key = $column_filter[0];
            $value = $column_filter[1];
    
            $column_filters[$key] = $value;
        }
    }

    // Call the function to get the dynamic risks table string
    return get_dynamic_risks_table_string($filter_status, $group, $sort, "", $selected_columns, $column_filters, force_user_id: $force_user_id);
}

/************************************
 * FUNCTION: GET MTTR BY TEAM       *
 * Returns avg days-to-close for    *
 * closed risks, grouped by team.   *
 ************************************/
function get_mttr_by_team($teams = false) {
    $teams_query = generate_teams_query($teams, "rtt.team_id");
    $db = db_open();
    $stmt = $db->prepare("
        SELECT
            IFNULL(t.name, 'Unassigned') AS label,
            ROUND(AVG(DATEDIFF(o.closure_date, a.submission_date)), 1) AS avg_days
        FROM risks a
        LEFT JOIN closures o ON a.close_id = o.id
        LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
        LEFT JOIN team t ON rtt.team_id = t.value
        WHERE a.status = 'Closed' AND o.id IS NOT NULL AND {$teams_query}
        GROUP BY t.value, t.name
        HAVING avg_days IS NOT NULL
        ORDER BY avg_days DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return $results;
}

/****************************************
 * FUNCTION: GET MTTR BY CATEGORY       *
 * Returns avg days-to-close for        *
 * closed risks, grouped by category.   *
 ****************************************/
function get_mttr_by_category($teams = false) {
    $teams_query = generate_teams_query($teams, "rtt.team_id");
    $db = db_open();
    $stmt = $db->prepare("
        SELECT
            IFNULL(c.name, 'Unassigned') AS label,
            ROUND(AVG(DATEDIFF(o.closure_date, a.submission_date)), 1) AS avg_days
        FROM risks a
        LEFT JOIN closures o ON a.close_id = o.id
        LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
        LEFT JOIN category c ON a.category = c.value
        WHERE a.status = 'Closed' AND o.id IS NOT NULL AND {$teams_query}
        GROUP BY a.category, c.name
        HAVING avg_days IS NOT NULL
        ORDER BY avg_days DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return $results;
}

/******************************************
 * FUNCTION: GET MTTR BY RISK LEVEL       *
 * Returns avg days-to-close for          *
 * closed risks, grouped by risk level.   *
 ******************************************/
function get_mttr_by_risk_level($teams = false) {
    $teams_query = generate_teams_query($teams, "rtt.team_id");
    $db = db_open();
    $stmt = $db->prepare("
        SELECT
            IFNULL(
                (SELECT IF(display_name='', name, display_name) FROM risk_levels
                 WHERE value - rs.calculated_risk <= 0.00001 ORDER BY value DESC LIMIT 1),
                'Insignificant'
            ) AS label,
            ROUND(AVG(DATEDIFF(o.closure_date, a.submission_date)), 1) AS avg_days
        FROM risks a
        LEFT JOIN closures o ON a.close_id = o.id
        LEFT JOIN risk_scoring rs ON a.id = rs.id
        LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
        WHERE a.status = 'Closed' AND o.id IS NOT NULL AND {$teams_query}
        GROUP BY label
        HAVING avg_days IS NOT NULL
        ORDER BY avg_days DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return $results;
}

/*****************************************************
 * FUNCTION: GET RISK EXPOSURE BY DIMENSION          *
 * Returns SUM(calculated_risk) for open risks       *
 * grouped by 'team', 'category', or 'location'.    *
 *****************************************************/
function get_risk_exposure_by_dimension($dimension, $teams = false) {
    $teams_query = generate_teams_query($teams, "rtt.team_id");
    $db = db_open();

    switch ($dimension) {
        case 'team':
            $stmt = $db->prepare("
                SELECT IFNULL(t.name, 'Unassigned') AS label,
                       ROUND(SUM(rs.calculated_risk), 2) AS total_exposure
                FROM risks a
                LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
                LEFT JOIN team t ON rtt.team_id = t.value
                LEFT JOIN risk_scoring rs ON a.id = rs.id
                WHERE a.status != 'Closed' AND {$teams_query}
                GROUP BY t.value, t.name
                ORDER BY total_exposure DESC
            ");
            break;
        case 'category':
            $stmt = $db->prepare("
                SELECT IFNULL(c.name, 'Unassigned') AS label,
                       ROUND(SUM(rs.calculated_risk), 2) AS total_exposure
                FROM risks a
                LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
                LEFT JOIN category c ON a.category = c.value
                LEFT JOIN risk_scoring rs ON a.id = rs.id
                WHERE a.status != 'Closed' AND {$teams_query}
                GROUP BY a.category, c.name
                ORDER BY total_exposure DESC
            ");
            break;
        case 'location':
            $stmt = $db->prepare("
                SELECT IFNULL(l.name, 'Unassigned') AS label,
                       ROUND(SUM(rs.calculated_risk), 2) AS total_exposure
                FROM risks a
                LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
                LEFT JOIN risk_to_location rtl ON a.id = rtl.risk_id
                LEFT JOIN location l ON rtl.location_id = l.value
                LEFT JOIN risk_scoring rs ON a.id = rs.id
                WHERE a.status != 'Closed' AND {$teams_query}
                GROUP BY l.value, l.name
                ORDER BY total_exposure DESC
            ");
            break;
        default:
            db_close($db);
            return [];
    }

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return $results;
}

/***********************************************
 * FUNCTION: OPEN RISK TEAM EXPOSURE PIE       *
 * Score-weighted (SUM of calculated_risk)     *
 * pie chart instead of count-based.           *
 ***********************************************/
function open_risk_team_exposure_pie($teams = false, $title = null) {
    global $escaper;
    $rows = get_risk_exposure_by_dimension('team', $teams);
    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'label' => $escaper->escapeHtml($row['label']),
            'data'  => (float)$row['total_exposure'],
            'url'   => 'dynamic_risk_report.php?status=0&group=6&sort=0',
        ];
    }
    create_chartjs_pie_code($title, 'open_risk_team_exposure_pie', $data);
}

/***************************************************
 * FUNCTION: OPEN RISK CATEGORY EXPOSURE PIE       *
 ***************************************************/
function open_risk_category_exposure_pie($teams = false, $title = null) {
    global $escaper;
    $rows = get_risk_exposure_by_dimension('category', $teams);
    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'label' => $escaper->escapeHtml($row['label']),
            'data'  => (float)$row['total_exposure'],
            'url'   => 'dynamic_risk_report.php?status=0&group=5&sort=0',
        ];
    }
    create_chartjs_pie_code($title, 'open_risk_category_exposure_pie', $data);
}

/***************************************************
 * FUNCTION: OPEN RISK LOCATION EXPOSURE PIE       *
 ***************************************************/
function open_risk_location_exposure_pie($teams = false, $title = null) {
    global $escaper;
    $rows = get_risk_exposure_by_dimension('location', $teams);
    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'label' => $escaper->escapeHtml($row['label']),
            'data'  => (float)$row['total_exposure'],
            'url'   => 'dynamic_risk_report.php?status=0&sort=0',
        ];
    }
    create_chartjs_pie_code($title, 'open_risk_location_exposure_pie', $data);
}

/*****************************************************
 * FUNCTION: GET SLA BREACH DATA                     *
 * Counts open risks within / past SLA per level.    *
 * Default thresholds (days): VH=30, H=60, M=90,    *
 * Low=180, Insignificant=365.                       *
 *****************************************************/
function get_sla_breach_data($teams = false) {
    $teams_query = generate_teams_query($teams, "rtt.team_id");
    $db = db_open();
    $stmt = $db->prepare("
        SELECT
            IFNULL(
                (SELECT IF(rl.display_name='', rl.name, rl.display_name) FROM risk_levels rl
                 WHERE rl.value - rs.calculated_risk <= 0.00001 ORDER BY rl.value DESC LIMIT 1),
                'Insignificant'
            ) AS risk_level_display,
            IFNULL(
                (SELECT rl2.name FROM risk_levels rl2
                 WHERE rl2.value - rs.calculated_risk <= 0.00001 ORDER BY rl2.value DESC LIMIT 1),
                'Insignificant'
            ) AS risk_level_key,
            DATEDIFF(NOW(), a.submission_date) AS days_open
        FROM risks a
        LEFT JOIN risk_scoring rs ON a.id = rs.id
        LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
        WHERE a.status != 'Closed' AND {$teams_query}
        GROUP BY a.id
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    // Build thresholds dynamically from the configured risk levels so that
    // custom level names and settings are respected.
    $defaults_by_name = ['Very High' => 30, 'High' => 60, 'Medium' => 90, 'Low' => 180];
    $all_risk_levels  = get_risk_levels();
    $thresholds = ['Insignificant' => 365];
    foreach ($all_risk_levels as $rl) {
        $key = 'sla_threshold_' . strtolower(str_replace(' ', '_', $rl['name']));
        $thresholds[$rl['name']] = (int)get_setting($key, $defaults_by_name[$rl['name']] ?? 90);
    }

    $within = [];
    $breached = [];

    foreach ($rows as $row) {
        $display = $row['risk_level_display'];
        $key     = $row['risk_level_key'];
        $limit   = $thresholds[$key] ?? 365;

        if ((int)$row['days_open'] > $limit) {
            $breached[$display] = ($breached[$display] ?? 0) + 1;
        } else {
            $within[$display] = ($within[$display] ?? 0) + 1;
        }
    }

    return ['within' => $within, 'breached' => $breached];
}

/*************************************************************
 * FUNCTION: OPEN RISK SLA STATUS                           *
 * Stacked bar chart: within SLA (green) + breached (red)  *
 * per risk level, ordered highest to lowest.              *
 *************************************************************/
function open_risk_sla_status($teams = false, $title = null) {
    global $escaper, $lang;

    $sla_data    = get_sla_breach_data($teams);
    $risk_levels = array_reverse(get_risk_levels()); // highest first

    // Build ordered label list and per-dataset value arrays
    $labels        = [];
    $within_values = [];
    $breach_values = [];

    foreach ($risk_levels as $rl) {
        $display         = $rl['display_name'] !== '' ? $rl['display_name'] : $rl['name'];
        $labels[]        = $display;
        $within_values[] = $sla_data['within'][$display] ?? ($sla_data['within'][$rl['name']] ?? 0);
        $breach_values[] = $sla_data['breached'][$display] ?? ($sla_data['breached'][$rl['name']] ?? 0);
    }

    // Only render if there is any data
    $has_data = array_sum($within_values) + array_sum($breach_values) > 0;

    $element_id     = 'open_risk_sla_status';
    $title_html     = $escaper->escapeHtml((string)$title);
    $title_json     = json_encode((string)$title);
    $labels_json    = json_encode(array_values($labels));
    $within_json    = json_encode(array_values($within_values));
    $breach_json    = json_encode(array_values($breach_values));
    $within_label   = $escaper->escapeJS($lang['WithinSLA']);
    $breach_label   = $escaper->escapeJS($lang['SLABreached']);
    $x_title        = $escaper->escapeJS($lang['RiskLevel']);
    $y_title        = $escaper->escapeJS($lang['NumberOfRisks']);

    if ($has_data) {
        echo "
            <div>
                <canvas id='{$element_id}'></canvas>
                <div class='d-flex justify-content-end align-items-center'>
                    <i class='far fa-save' id='{$element_id}_save'></i>
                </div>
            </div>
            <script>
                $(function() {
                    var data = {
                        labels: {$labels_json},
                        datasets: [
                            {
                                label: '{$within_label}',
                                data: {$within_json},
                                backgroundColor: '#27a9e3',
                                stack: 'sla',
                            },
                            {
                                label: '{$breach_label}',
                                data: {$breach_json},
                                backgroundColor: '#ed3139',
                                stack: 'sla',
                            }
                        ]
                    };
                    var config = {
                        type: 'bar',
                        data: data,
                        options: {
                            responsive: true,
                            plugins: {
                                title: { display: true, text: {$title_json} },
                                legend: { display: true }
                            },
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                x: {
                                    stacked: true,
                                    title: { display: true, text: '{$x_title}' }
                                },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    title: { display: true, text: '{$y_title}' }
                                }
                            }
                        }
                    };
                    var ctx = document.getElementById('{$element_id}').getContext('2d');
                    var {$element_id}_chart = new Chart(ctx, config);
                    document.getElementById('{$element_id}_save').addEventListener('click', function() {
                        var link = document.createElement('a');
                        link.href = {$element_id}_chart.toBase64Image();
                        link.download = '{$element_id}.png';
                        link.click();
                    });
                });
            </script>
        ";
    } else {
        echo "
            <div class='d-flex flex-column text-center'>
                <strong class='mb-3'>{$title_html}</strong>
                <strong>" . $escaper->escapeHtml($lang['NoDataAvailable']) . "</strong>
            </div>
        ";
    }
}

/********************************************************
 * FUNCTION: COMPLIANCE CONTROLS BY FRAMEWORK BAR CHART *
 ********************************************************/
// Resolve the compliance dashboard's single-select framework filter from the
// request: no / empty / non-numeric 'frameworks' => null (All Frameworks); a
// numeric id => [id]. Shared by every compliance dashboard widget so the one
// selection drives them all consistently.
function compliance_dashboard_framework_filter() {
    return (isset($_GET['frameworks']) && ctype_digit((string)$_GET['frameworks'])) ? [(int)$_GET['frameworks']] : null;
}

// Governance dashboard single-select framework scope (mirrors the compliance
// helper): null = All Frameworks, [id] = that one framework.
function governance_dashboard_framework_filter() {
    return (isset($_GET['frameworks']) && ctype_digit((string)$_GET['frameworks'])) ? [(int)$_GET['frameworks']] : null;
}

// Control IDs mapped to the compliance dashboard's selected framework, or null
// when All Frameworks (no scoping needed). Used to scope KPIs whose source
// functions don't take a framework argument (Open Audits / Tests Due Soon), so
// the whole compliance KPI row honors the single-select consistently.
function compliance_dashboard_framework_control_ids() {
    $fw = compliance_dashboard_framework_filter();
    if (empty($fw)) { return null; }
    $db = db_open();
    $stmt = $db->prepare("SELECT DISTINCT `control_id` FROM `framework_control_mappings` WHERE `framework` = :f");
    $stmt->bindValue(':f', (int)$fw[0], PDO::PARAM_INT);
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    db_close($db);
    return array_map('intval', $ids);
}

// Filter a list of rows (each carrying `framework_control_id`) down to the
// compliance dashboard's selected framework. No-op (returns the rows unchanged)
// when All Frameworks is selected.
function compliance_scope_rows_by_control($rows) {
    $ids = compliance_dashboard_framework_control_ids();
    if ($ids === null || !is_array($rows)) { return $rows; }
    $set = array_flip($ids);
    return array_values(array_filter($rows, fn($r) => isset($set[(int)($r['framework_control_id'] ?? -1)])));
}

// Render a horizontal stacked "controls pass/fail by <attribute>" bar chart — one
// bar per attribute group (domain/family, class, phase, priority, maturity), each
// a green-passing / red-failing stack. Framework-scoped via the dashboard's
// single-select. $title_key is a $lang key.
function compliance_controls_pass_fail_by_chart($attribute, $element_id, $title_key) {
    global $lang;

    $rows = get_control_pass_fail_counts_by_attribute($attribute, compliance_dashboard_framework_filter());

    $labels = $passing = $failing = $na = [];
    foreach ($rows as $r) {
        $p = (int) $r['passing_controls'];
        $f = (int) $r['failing_controls'];
        // Controls in this group whose latest status isn't Pass or Fail
        // (never tested, inconclusive, or blank) — shown so the bar totals
        // reflect every control, not just the assessed ones.
        $n = (int) ($r['na_controls'] ?? 0);
        // For the domain (family) breakdown, omit domains that contain no controls
        // at all — they'd only add empty bars to the (scrollable) chart. Other
        // attributes (e.g. maturity) keep every defined level.
        if ($attribute === 'family' && ($p + $f + $n) === 0) {
            continue;
        }
        $labels[]  = $r['group_name'];
        $passing[] = $p;
        $failing[] = $f;
        $na[]      = $n;
    }
    $datasets = [
        ['label' => $lang['PassingControls'], 'data' => $passing, 'backgroundColor' => '#51A351'],
        ['label' => $lang['FailingControls'], 'data' => $failing, 'backgroundColor' => '#ed3139'],
        ['label' => $lang['NotApplicable'], 'data' => $na, 'backgroundColor' => '#b0b7be'],
    ];
    // Horizontal (indexAxis 'y') + stacked: pass/fail/na stacked in one bar per
    // group. Only scroll when there are enough bars to need it (e.g. ~33 domains);
    // shorter attributes (class/phase/priority/maturity) fill the widget body and
    // centre cleanly with no scrollbar gutter. Legend suppressed (colours are
    // self-evident + in the tooltip); the tooltip footer shows the group total.
    $scrollable = count($labels) > 12;
    create_chartjs_bar_code($lang[$title_key] ?? $title_key, $element_id, $labels, $datasets,
        $lang['NumberOfControls'] ?? 'Number of Controls', '', null, null, true, true, $scrollable, false, true, 'y');
}

// Render a vertical stacked bar of passing vs failing control counts per month
// over the last $months, framework-scoped via the dashboard's single-select. Each
// month reflects the latest Pass/Fail result per control as of that month's end
// (reconstructed live from test-result dates — no snapshot warm-up).
function compliance_control_status_over_time_chart($months = 12) {
    global $lang;

    $filter_ids = compliance_dashboard_framework_filter();
    $fw_clause = '';
    $params = [];
    if (!empty($filter_ids)) {
        $ph = implode(',', array_fill(0, count($filter_ids), '?'));
        $fw_clause = "AND f.value IN ({$ph})";
        $params = array_values($filter_ids);
    }

    // Total controls in scope — the constant denominator each month is drawn
    // against, so the N/A (not-yet-assessed) band fills the gap up to the full
    // control count and every stacked bar reaches the same height.
    $total_controls = get_compliance_total_controls($filter_ids);

    $db = db_open();
    $stmt = $db->prepare("
        SELECT fc.id AS control, DATE(tr.submission_date) AS d,
               CASE WHEN tr.test_result = 'Pass' THEN 1 ELSE 0 END AS pass
        FROM framework_controls fc
        INNER JOIN framework_control_mappings fcm ON fc.id = fcm.control_id
        INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1 {$fw_clause}
        INNER JOIN audit_control_map acm ON acm.framework_control_id = fc.id
        INNER JOIN framework_control_test_audits ta ON ta.id = acm.audit_id
        INNER JOIN framework_control_test_results tr ON tr.test_audit_id = ta.id
        WHERE fc.deleted = 0 AND tr.test_result IN ('Pass', 'Fail')
        ORDER BY tr.submission_date, tr.id;
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    // Month-end axis (oldest -> newest).
    $labels = [];
    $month_ends = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime("first day of -{$i} month");
        $labels[]     = date('M Y', $ts);
        $month_ends[] = date('Y-m-t', $ts);
    }

    $passing = [];
    $failing = [];
    $na = [];
    foreach ($month_ends as $end) {
        $latest = [];
        foreach ($rows as $r) {
            if ($r['d'] <= $end) {
                $c = $r['control'];
                if (!isset($latest[$c]) || $r['d'] >= $latest[$c]['d']) {
                    $latest[$c] = $r;
                }
            }
        }
        $p = 0; $fl = 0;
        foreach ($latest as $l) { if ($l['pass']) { $p++; } else { $fl++; } }
        $passing[] = $p;
        $failing[] = $fl;
        // Everything not yet assessed as of this month-end fills up to the
        // total control count (clamped at 0 in case of mid-window control churn).
        $na[]      = max(0, $total_controls - $p - $fl);
    }

    $datasets = [
        ['label' => $lang['PassingControls'], 'data' => $passing, 'backgroundColor' => '#51A351'],
        ['label' => $lang['FailingControls'], 'data' => $failing, 'backgroundColor' => '#ed3139'],
        ['label' => $lang['NotApplicable'], 'data' => $na, 'backgroundColor' => '#b0b7be'],
    ];
    // Vertical stacked bar: months across, pass/fail/na stacked. Legend
    // suppressed to match the other status charts (colours are self-evident and
    // in the tooltip); tooltip footer shows the month's total control count.
    create_chartjs_bar_code($lang['ControlStatusOverTime'] ?? 'Control Status Over Time',
        'compliance_control_status_over_time_chart', $labels, $datasets,
        $lang['Month'] ?? 'Month', $lang['NumberOfControls'] ?? 'Number of Controls', null, null, true, false, false, false, true, 'x');
}

function compliance_controls_by_framework_bar_chart() {
    global $escaper, $lang;

    // Get all active frameworks for the filter dropdown
    $all_frameworks = array_values(get_frameworks(1));

    // Resolve selected framework IDs from GET param.
    // - No 'frameworks' key in URL (first load): default to all (null = no filter)
    // - Key present with IDs: filter to those IDs
    // - Key present but empty (user deselected all): return empty results
    if (!isset($_GET['frameworks'])) {
        // First load — select all in the multiselect, pass null to data functions
        $selected_fw_ids = array_column($all_frameworks, 'value');
        $filter_ids      = null;
    } else {
        $selected_fw_ids = array_values(array_filter(explode(',', $_GET['frameworks']), 'ctype_digit'));
        $selected_fw_ids = array_map('intval', $selected_fw_ids);
        $filter_ids      = $selected_fw_ids; // may be [] if user deselected all
    }
    
    // --- Snapshot bar chart data ---
    $framework_data = get_framework_controls_test_status_counts($filter_ids);

    $snap_labels  = [];
    $passing_data = [];
    $failing_data = [];
    foreach ($framework_data as $framework) {
        $snap_labels[]  = $escaper->escapeHtml(try_decrypt($framework['framework_name']));
        $passing_data[] = (int)$framework['passing_controls'];
        $failing_data[] = (int)$framework['failing_controls'];
    }
    $snap_datasets = [
        [
            'label'           => $escaper->escapeHtml($lang['PassingControls']),
            'data'            => $passing_data,
            'backgroundColor' => '#27a9e3',
        ],
        [
            'label'           => $escaper->escapeHtml($lang['FailingControls']),
            'data'            => $failing_data,
            'backgroundColor' => '#ed3139',
        ],
    ];
    create_chartjs_bar_code(
        $lang['ControlsByFramework'],
        'compliance_controls_by_framework_bar_chart',
        $snap_labels,
        $snap_datasets,
        $lang['Framework'],
        $lang['NumberOfControls']
    );
}

function compliance_pass_rate_trend_line_chart() {
    global $escaper, $lang;

    // Get all active frameworks for the filter dropdown
    $all_frameworks = array_values(get_frameworks(1));

    // Resolve selected framework IDs from GET param.
    // - No 'frameworks' key in URL (first load): default to all (null = no filter)
    // - Key present with IDs: filter to those IDs
    // - Key present but empty (user deselected all): return empty results
    if (!isset($_GET['frameworks'])) {
        // First load — select all in the multiselect, pass null to data functions
        $selected_fw_ids = array_column($all_frameworks, 'value');
        $filter_ids      = null;
    } else {
        $selected_fw_ids = array_values(array_filter(explode(',', $_GET['frameworks']), 'ctype_digit'));
        $selected_fw_ids = array_map('intval', $selected_fw_ids);
        $filter_ids      = $selected_fw_ids; // may be [] if user deselected all
    }

    // --- Monthly pass rate trend chart data ---
    $trend_by_framework = get_framework_controls_pass_rate_by_month(12, $filter_ids);

    $all_months = [];
    foreach ($trend_by_framework as $fw_months) {
        $all_months = array_merge($all_months, array_keys($fw_months));
    }
    $all_months = array_unique($all_months);
    sort($all_months);

    $trend_labels = array_map(fn($m) => date('M Y', strtotime($m . '-01')), $all_months);

    $palette = ['#4472C4', '#ED7D31', '#A9D18E', '#FFC000', '#5B9BD5', '#70AD47', '#ed3139', '#7030A0'];
    $trend_datasets = [];
    $ci = 0;
    foreach ($trend_by_framework as $fw_name => $fw_months) {
        $color = $palette[$ci % count($palette)];
        $ci++;
        $trend_datasets[] = [
            'label'           => $fw_name,
            'data'            => array_map(fn($m) => $fw_months[$m] ?? 'null', $all_months),
            'borderColor'     => $color,
            'backgroundColor' => $color,
            'fill'            => 'false',
            'tension'         => '0.3',
        ];
    }

    create_chartjs_line_code(
        $lang['ControlPassRateTrend'],
        'compliance_pass_rate_trend_chart',
        $trend_labels,
        $trend_datasets,
        '',
        $lang['Month'] ?? 'Month',
        $lang['PassRatePercent'],
        100
    );
}

/********************************************
 * FUNCTION: COMPLIANCE PASS/FAIL PIE CHART *
 ********************************************/
function compliance_pass_fail_pie_chart() {
    global $escaper, $lang;

    // Single-select framework scope (null = All Frameworks, [id] = one framework).
    // Uses the shared helper so an empty 'frameworks=' (All Frameworks) is read as
    // null, not as "deselected all" — the old multiselect parse turned the empty
    // value into [] and left this pie blank on All Frameworks.
    $filter_ids = compliance_dashboard_framework_filter();

    // --- Overall pass/fail/na counts across the scoped frameworks ---
    // DISTINCT-control aggregate (passing+failing+na == total controls in scope),
    // so the pie reconciles exactly with the Total Controls KPI and doesn't
    // double-count controls shared across frameworks under "All Frameworks".
    $totals = get_compliance_pass_fail_na_totals($filter_ids);
    $total_passing = $totals['passing'];
    $total_failing = $totals['failing'];
    $total_na      = $totals['na'];

    $overall_pass_fail_data = [
        [
            'label' => $lang['PassingControls'],
            'data' => $total_passing,
            'color' => '#51A351',
        ],
        [
            'label' => $lang['FailingControls'],
            'data' => $total_failing,
            'color' => '#ed3139',
        ],
        [
            'label' => $lang['NotApplicable'],
            'data' => $total_na,
            'color' => '#b0b7be',
        ],
    ];

    create_chartjs_pie_code(
        $lang['ControlStatus'],
        'compliance_pass_fail_pie_chart',
        $overall_pass_fail_data
    );
}

/***********************************************************
 * FUNCTION: GOVERNANCE CURRENT CONTROL MATURITY PIE CHART *
 ***********************************************************/
function governance_current_control_maturity_pie_chart() {
    global $escaper, $lang;

    $control_maturity_data = get_control_current_maturity_counts(governance_dashboard_framework_filter());

    // Maturity is an ORDERED scale, so it earns a sequential ramp, not random
    // categorical colors — and it's the same $info cyan family as the maturity
    // pills and radar: light cyan = low maturity → dark cyan = high maturity, so
    // the color encodes progress. Levels 0–5 map by value; "Unassigned" (outside
    // the scale) stays neutral gray.
    $maturity_ramp = [
        0 => '#dceffb', // Not Performed — lightest
        1 => '#a9dcf3',
        2 => '#6ec6ec',
        3 => '#27a9e3', // $info cyan (mid)
        4 => '#1c85b6',
        5 => '#12617f', // Optimizing — darkest
    ];
    $unassigned_color = '#b0b7be';

    $current_maturity_pie_data = [];
    foreach ($control_maturity_data as $maturity) {
        $value = $maturity['maturity_value'];
        $color = ($value !== null && isset($maturity_ramp[(int)$value]))
            ? $maturity_ramp[(int)$value]
            : $unassigned_color;

        $current_maturity_pie_data[] = [
            'label' => $maturity['maturity_name'],
            'data' => (int)$maturity['control_count'],
            'color' => $color,
        ];
    }

    create_chartjs_pie_code(
        $lang['CurrentControlMaturity'],
        'governance_current_control_maturity_pie_chart',
        $current_maturity_pie_data
    );
}

/*************************************************************************
 * FUNCTION: GOVERNANCE CURRENT-VS-DESIRED MATURITY RADAR (by family)   *
 *************************************************************************/
// Governance dashboard radar of average current vs desired control maturity per
// control family, framework-scoped by the dashboard's single-select (aggregates
// across all controls under "All Frameworks"). Mirrors the Control Gap Analysis
// report's spider chart. The framing widget supplies the header title, so the
// chart itself carries none.
function governance_control_maturity_gap_radar_chart() {
    global $lang, $escaper;

    $rows = get_governance_maturity_gap_by_family(governance_dashboard_framework_filter());

    if (empty($rows)) {
        echo "<div class='sr-whatsnext sr-whatsnext--empty'>"
           . "<span class='sr-whatsnext__empty-title'>" . $escaper->escapeHtml($lang['NoDataAvailable']) . "</span>"
           . "</div>";
        return;
    }

    $labels = $current = $desired = [];
    foreach ($rows as $r) {
        $labels[]  = $r['family'];
        $current[] = $r['current'];
        $desired[] = $r['desired'];
    }

    $labels_json   = json_encode(array_values($labels));
    $current_json  = json_encode(array_values($current));
    $desired_json  = json_encode(array_values($desired));
    $current_label = json_encode($lang['CurrentControlMaturity']);
    $desired_label = json_encode($lang['DesiredControlMaturity']);
    $element_id    = 'governance_control_maturity_gap_radar_chart';

    // Maturity is a progress metric → one hue, $info cyan (#27a9e3), deliberately
    // distinct from the pass/fail green/red elsewhere on this dashboard and clear
    // of the red accent. Current = solid filled area; Desired = dashed outline
    // (the "target envelope"). No legend — the two lines are self-evident and the
    // framed widget header supplies the title. Scale fixed to the 0–5 maturity range.
    echo "
        <div class='sr-chart-fill'><canvas id='{$element_id}'></canvas></div>
        <script>
            $(function() {
                var ctx = document.getElementById('{$element_id}').getContext('2d');
                new Chart(ctx, {
                    type: 'radar',
                    data: {
                        labels: {$labels_json},
                        datasets: [
                            // Desired = the target reference, a filled neutral-gray
                            // area drawn underneath (clearer than the old dashed line).
                            {
                                label: {$desired_label},
                                data: {$desired_json},
                                backgroundColor: 'rgba(108, 117, 125, 0.28)',
                                borderColor: '#6c757d',
                                borderWidth: 2,
                                pointBackgroundColor: '#6c757d',
                                pointRadius: 3,
                                fill: true
                            },
                            // Current = the achievement, saturated cyan drawn on top.
                            {
                                label: {$current_label},
                                data: {$current_json},
                                backgroundColor: 'rgba(39, 169, 227, 0.35)',
                                borderColor: '#27a9e3',
                                borderWidth: 2.5,
                                pointBackgroundColor: '#27a9e3',
                                pointRadius: 3,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        // Hover anywhere: 'nearest' finds the closest point, which
                        // tells us the family (spoke) under the cursor; the tooltip
                        // callback then shows BOTH current and desired for that spoke.
                        // ('index' can't be used here — it mis-anchors by cursor
                        // x-position on a radial chart.)
                        interaction: { mode: 'nearest', intersect: false },
                        scales: {
                            r: { min: 0, max: 5, ticks: { stepSize: 1 } }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                mode: 'nearest',
                                intersect: false,
                                displayColors: false,
                                callbacks: {
                                    title: function(items) { return items.length ? items[0].label : ''; },
                                    label: function(context) {
                                        var d = context.chart.data;
                                        var i = context.dataIndex;
                                        // datasets[1] = current (cyan), datasets[0] = desired (gray).
                                        return [
                                            d.datasets[1].label + ': ' + d.datasets[1].data[i],
                                            d.datasets[0].label + ': ' + d.datasets[0].data[i]
                                        ];
                                    }
                                }
                            }
                        }
                    }
                });
            });
        </script>
    ";
}

/*******************************************************
 * FUNCTION: MATURITY PILL CLASS (level -> CSS class) *
 *******************************************************/
// Map a maturity level onto its .sr-maturity-pill--<level> shade modifier. The
// ramp itself lives in SCSS ($sr-maturity-ramp, scss/modules/_home.scss) rather
// than being built here as an inline style=, so the governance dashboard's
// maturity-gap tables (this file) and the Define Control Frameworks controls
// table (js/simplerisk/pages/governance-frameworks.js, which builds the same
// class name client-side) share one definition of what each level looks like.
//
// The scale is a fixed 0–5 (admin/custom_names.php only renames control_maturity
// rows, it cannot add one), so --na is defensive rather than a live case.
//
// Pure: no DB, no globals, no output. The return value is attribute-safe by
// construction: $value is int-cast and range-checked to 0–5 before it reaches
// the class template, so the result is always one of seven fixed strings — the
// caller does not need to escape it.
function maturity_pill_class($value) {
    // A non-numeric value is NOT level 0 -- a blanket (int) cast would turn
    // null / '' / garbage into the lightest shade and claim "Not Performed",
    // and it would also diverge from the JS mirror, where parseInt() yields
    // NaN for all three. Guard first, then cast.
    if (!is_numeric($value)) {
        return 'sr-maturity-pill--na';
    }
    $value = (int) $value;
    return ($value >= 0 && $value <= 5) ? "sr-maturity-pill--{$value}" : 'sr-maturity-pill--na';
}

// Render one maturity-level pill for the gap tables. Filled with the level's
// shade from the sequential cyan ramp — which MUST match the pie ramp in
// governance_current_control_maturity_pie_chart() so color means the same level
// everywhere. Charcoal text on the lighter shades, white on the darkest two.
// Both the fill and that text colour now come from the level's SCSS modifier
// class (see maturity_pill_class()); this used to inline them via
// escapeCssColor(), which existed only because escapeCss() mangles a hex literal
// (`#` -> `\23`). With no colour in the markup there is nothing left to escape.
function maturity_gap_pill_html($escaper, $value, $name) {
    $class = maturity_pill_class($value);
    return "<span class='sr-maturity-pill {$class}'>" . $escaper->escapeHtml($name) . "</span>";
}

/*************************************************************************
 * FUNCTION: GOVERNANCE MATURITY-GAP TABLE (below/at/above desired)     *
 *************************************************************************/
// Governance dashboard compact table of controls in a maturity-gap bucket
// (below/at/above their desired maturity), framework-scoped. Each row deep-links
// to the control editor (../governance/index.php?control_id=N — the same target
// as the Failing Controls list). $bucket: 'below' | 'at' | 'above'.
function governance_maturity_gap_table($bucket) {
    global $lang, $escaper;

    $rows = get_governance_maturity_gap_items($bucket, governance_dashboard_framework_filter());

    if (empty($rows)) {
        echo "<div class='sr-whatsnext sr-whatsnext--empty'>"
           . "<span class='sr-whatsnext__empty-title'>" . $escaper->escapeHtml($lang['NoDataAvailable']) . "</span>"
           . "</div>";
        return;
    }

    $html = "<div class='sr-gap-table-scroll'>"
          . "<table class='sr-gap-table'>"
          . "<thead><tr>"
          . "<th>" . $escaper->escapeHtml($lang['Control']) . "</th>"
          . "<th>" . $escaper->escapeHtml($lang['Maturity']) . "</th>"
          . "</tr></thead><tbody>";

    foreach ($rows as $r) {
        $href_attr = $escaper->escapeHtmlAttr('../governance/index.php?control_id=' . (int) $r['id']);

        // Control cell renders as two lines: the control ID on top, the short name
        // below. SCF-style short_names embed the number ("AAT-26.1: …"), so strip
        // that prefix off the name to avoid repeating the ID; when there's no
        // explicit number but the name has a "CODE: rest" shape, split it out.
        $num  = trim((string) ($r['control_number'] ?? ''));
        $name = trim((string) ($r['short_name'] ?? ''));
        if ($num !== '' && stripos($name, $num) === 0) {
            $name = ltrim(ltrim(substr($name, strlen($num))), ": \t");
        } elseif ($num === '' && preg_match('/^(\S+):\s*(.+)$/', $name, $m)) {
            $num  = $m[1];
            $name = $m[2];
        }
        $id_html   = $num !== '' ? "<span class='sr-gap-id'>" . $escaper->escapeHtml($num) . "</span>" : '';
        $name_html = "<span class='sr-gap-name'>" . $escaper->escapeHtml($name) . "</span>";

        // Maturity pills carry the SAME sequential cyan ramp as the maturity pie —
        // light = low level, dark = high — so color encodes the level. Both pills
        // are filled by their level's shade; current → desired reads by position +
        // arrow. So Below-maturity pairs run light→dark, At-maturity is two equal
        // shades, Above-maturity runs dark→light.
        $cur_pill = maturity_gap_pill_html($escaper, (int) $r['current_maturity'], (string) ($r['current_maturity_name'] ?? $r['current_maturity']));
        $des_pill = maturity_gap_pill_html($escaper, (int) $r['desired_maturity'], (string) ($r['desired_maturity_name'] ?? $r['desired_maturity']));

        $html .= "<tr>"
               . "<td class='sr-gap-control'><a class='sr-gap-link' href='{$href_attr}'>{$id_html}{$name_html}</a></td>"
               . "<td class='sr-gap-maturity'>{$cur_pill}"
               . "<span class='sr-maturity-arrow' aria-hidden='true'>&rarr;</span>{$des_pill}</td>"
               . "</tr>";
    }

    $html .= "</tbody></table></div>";
    echo $html;
}

/***********************************************************
 * FUNCTION: GOVERNANCE DESIRED CONTROL MATURITY PIE CHART *
 ***********************************************************/
function governance_framework_maturity_stacked_bar_chart() {
    global $escaper, $lang;

    $stacked_chart_data = get_framework_controls_maturity_stacked_chart_data(governance_dashboard_framework_filter());
    $suggested_colors = suggested_colors_array();
    $framework_maturity_bar_labels = $stacked_chart_data['labels'];
    $framework_maturity_bar_datasets = [];
    foreach ($stacked_chart_data['maturity_order'] as $mIndex => $maturity_name) {
        $color = ($maturity_name === 'Not Set') ? '#808080' : $suggested_colors[$mIndex % count($suggested_colors)];
        $framework_maturity_bar_datasets[] = [
            'label' => $maturity_name,
            'data' => $stacked_chart_data['counts_by_maturity'][$maturity_name],
            'backgroundColor' => $color,
        ];
    }

    create_chartjs_bar_code(
        $lang['GovernanceControlsByFrameworkMaturityStacked'],
        'governance_framework_maturity_stacked_bar_chart',
        $framework_maturity_bar_labels,
        $framework_maturity_bar_datasets,
        $lang['Framework'],
        $lang['NumberOfControls'],
        null,
        null,
        true
    );
}

/****************************************************
 * FUNCTION: GOVERNANCE CONTROL STATUS PIE CHART     *
 * Overall pass/fail/not-tested distinct-control     *
 * counts across the scoped frameworks. Mirrors      *
 * compliance_pass_fail_pie_chart().                 *
 ****************************************************/
function governance_control_status_pie_chart() {
    global $lang;

    $totals = get_governance_control_status_totals(governance_dashboard_framework_filter());

    $data = [
        ['label' => $lang['Pass'],      'data' => $totals['passing'],    'color' => '#51A351'],
        ['label' => $lang['Fail'],      'data' => $totals['failing'],    'color' => '#ed3139'],
        ['label' => $lang['NotTested'], 'data' => $totals['not_tested'], 'color' => '#b0b7be'],
    ];

    create_chartjs_pie_code($lang['ControlStatus'], 'governance_control_status_pie_chart', $data);
}

// Render a horizontal stacked "controls pass/fail/not-tested by <attribute>" bar
// chart — one bar per attribute group (domain/family, class, phase, priority,
// maturity), each a green-passing / red-failing / gray-not-tested stack.
// Framework-scoped via the governance dashboard's single-select. Mirrors
// compliance_controls_pass_fail_by_chart(). $title_key is a $lang key.
function governance_controls_status_by_chart($attribute, $element_id, $title_key) {
    global $lang;

    $rows = get_control_status_counts_by_attribute($attribute, governance_dashboard_framework_filter());

    $labels = $passing = $failing = $not_tested = [];
    foreach ($rows as $r) {
        $p  = (int) $r['passing'];
        $f  = (int) $r['failing'];
        $nt = (int) ($r['not_tested'] ?? 0);
        // For the domain (family) breakdown, omit domains that contain no controls
        // at all — they'd only add empty bars to the (scrollable) chart. Other
        // attributes (e.g. maturity) keep every defined level.
        if ($attribute === 'family' && ($p + $f + $nt) === 0) {
            continue;
        }
        $labels[]     = $r['group_name'];
        $passing[]    = $p;
        $failing[]    = $f;
        $not_tested[] = $nt;
    }
    $datasets = [
        ['label' => $lang['Pass'],      'data' => $passing,    'backgroundColor' => '#51A351'],
        ['label' => $lang['Fail'],      'data' => $failing,    'backgroundColor' => '#ed3139'],
        ['label' => $lang['NotTested'], 'data' => $not_tested, 'backgroundColor' => '#b0b7be'],
    ];
    // Horizontal (indexAxis 'y') + stacked; scroll only when there are enough
    // bars to need it (matches the compliance by-attribute bar behavior).
    $scrollable = count($labels) > 12;
    create_chartjs_bar_code($lang[$title_key] ?? $title_key, $element_id, $labels, $datasets,
        $lang['NumberOfControls'] ?? 'Number of Controls', '', null, null, true, true, $scrollable, false, true, 'y');
}

/*******************************************
* FUNCTION: DISPLAY EXCEPTION REPORT TABLE *
********************************************/
function display_exception_report() {

    global $lang, $escaper;
    
    // If User has permission for governance menu and view exception, shows EXCEPTION REPORT report
    if(check_permission("governance") && check_permission_exception('view')) {

        echo "
            <div class='card-body border my-2'>
                <div class='row'>
                    <div class='col-10'></div>
                    <div class='col-2'>
                        <div style='float: right;'>
        ";
                            render_column_selection_widget('document_exception');
        echo "
                        </div>
                    </div>
                </div>
                <div class='row'>
                    <div class='col-12'>
        ";
                        render_view_table('document_exception');
        echo "
                    </div>
                </div>
            </div>
            
            <script>
                $(function () {

                    initializeMultiselect('.header_filter .multiselect', {
                        allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
                        includeSelectAllOption: true,
                        buttonWidth: '100%',
                        maxHeight: 400,
                        enableCaseInsensitiveFiltering: true,
                    });

                    $('.header_filter [name=creation_date].datepicker').initAsDatePicker();
                    $('.header_filter [name=next_review_date].datepicker').initAsDatePicker();
                    $('.header_filter [name=approval_date].datepicker').initAsDatePicker();

                });    
            </script>
        ";
    }
}

/*********************************************
 * FUNCTION: DISPLAY DOCUMENT PROGRAM REPORT *
 *********************************************/
function display_document_program_report() {
    global $lang, $escaper;
    
    // If User has permission for governance menu, shows Document Program report
    if (has_permission('governance')) {
        echo "
            <div class='card-body border my-2'>
                <div class='row'>
                    <div class='col-10'></div>
                    <div class='col-2'>
                        <div style='float: right;'>
        ";
                            render_column_selection_widget('document_program');
        echo "
                        </div>
                    </div>
                </div>
                <div class='row'>
                    <div class='col-12'>
        ";
                        render_view_table('document_program');
        echo "
                    </div>
                </div>
            </div>
            
            <script>
                $(function () {

                    initializeMultiselect('.header_filter .multiselect', {
                        allSelectedText: '{$escaper->escapeHtml($lang['ALL'])}',
                        includeSelectAllOption: true,
                        buttonWidth: '100%',
                        maxHeight: 400,
                        enableCaseInsensitiveFiltering: true,
                    });

                    $('.header_filter [name=creation_date].datepicker').initAsDatePicker();
                    $('.header_filter [name=approval_date].datepicker').initAsDatePicker();
                    $('.header_filter [name=last_review_date].datepicker').initAsDatePicker();
                    $('.header_filter [name=next_review_date].datepicker').initAsDatePicker();

                });    
            </script>
        ";
    }
}

/****************************
 * FUNCTION: RENDER KPI TILE *
 ****************************/
// Echo a single Home KPI stat-tile. $value is pre-formatted (caller casts int
// or builds a "%"). $label_key / $cta_url are trusted internal values; escape
// on output regardless.
// Real "this month" delta for a home risk KPI, computed from existing data (no
// snapshot needed). Returns a delta array for render_kpi_tile(), or null when
// there is nothing to show. More open / unreviewed risks is bad — the value
// stays charcoal and the delta reads red. $which: 'open' | 'unreviewed'.
function home_risk_kpi_delta($which)
{
    global $lang;

    // Scope to the user's teams when Team Separation is active — the delta must
    // never count risks the user can't see (same guard as the tile's value).
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $review_clause = ($which === 'unreviewed') ? "AND `rsk`.`mgmt_review` = 0" : "";

    $db = db_open();
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT `rsk`.`id`)
        FROM `risks` rsk
        {$sep_from}
        WHERE `rsk`.`status` != 'Closed'
        {$review_clause}
        AND `rsk`.`submission_date` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        {$sep_where};
    ");
    $stmt->execute();
    $n = (int) $stmt->fetchColumn();
    db_close($db);

    if ($n <= 0) {
        return null;
    }

    return [
        'label'    => "\u{25B2} " . $n,   // ▲ N  (more = worse)
        'context'  => $lang['HomeKpiThisMonth'],
        'goodness' => 'bad',
    ];
}

// Control pass rate (%), computed the same way the compliance dashboard does —
// the latest Pass/Fail result per control across active frameworks. When $prior
// is true it reconstructs the rate "as of 30 days ago" by only considering
// results submitted on/before that cutoff. Returns null when there are no
// tested controls (no baseline to compare).
function home_control_pass_rate_percent($prior = false)
{
    $db = db_open();

    $cut  = $prior ? "AND tr1.submission_date <= DATE_SUB(NOW(), INTERVAL 30 DAY)" : "";
    $cut2 = $prior ? "AND tr2.submission_date <= DATE_SUB(NOW(), INTERVAL 30 DAY)" : "";

    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN latest.test_result = 'Pass' THEN 1 ELSE 0 END) AS pass_count,
            SUM(CASE WHEN latest.test_result = 'Fail' THEN 1 ELSE 0 END) AS fail_count
        FROM framework_controls fc
        INNER JOIN framework_control_mappings fcm ON fc.id = fcm.control_id
        INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1
        INNER JOIN (
            SELECT acm1.framework_control_id, tr1.test_result
            FROM audit_control_map acm1
            INNER JOIN framework_control_test_audits ta1 ON ta1.id = acm1.audit_id
            INNER JOIN framework_control_test_results tr1 ON ta1.id = tr1.test_audit_id
            WHERE tr1.test_result IN ('Pass', 'Fail') {$cut}
            AND tr1.submission_date = (
                SELECT MAX(tr2.submission_date)
                FROM audit_control_map acm2
                INNER JOIN framework_control_test_audits ta2 ON ta2.id = acm2.audit_id
                INNER JOIN framework_control_test_results tr2 ON ta2.id = tr2.test_audit_id
                WHERE acm2.framework_control_id = acm1.framework_control_id
                AND tr2.test_result IN ('Pass', 'Fail') {$cut2}
            )
        ) latest ON fc.id = latest.framework_control_id
        WHERE fc.deleted = 0
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);

    $pass  = (int) ($row['pass_count'] ?? 0);
    $fail  = (int) ($row['fail_count'] ?? 0);
    $total = $pass + $fail;

    return $total > 0 ? (int) round(($pass / $total) * 100) : null;
}

// Real "this month" delta for the control pass-rate KPI: current rate vs. the
// rate as it stood 30 days ago. Up is good (higher pass rate). Null when there's
// no 30-day-ago baseline or no change.
function home_pass_rate_delta()
{
    global $lang;

    $now   = home_control_pass_rate_percent(false);
    $prior = home_control_pass_rate_percent(true);
    if ($now === null || $prior === null) {
        return null;
    }

    $diff = $now - $prior;
    if ($diff === 0) {
        // Baseline exists and the rate held steady — show an explicit neutral
        // "no change" marker. This is distinct from the no-baseline case above
        // (prior === null), which stays blank because there's nothing to
        // compare against and claiming "no change" would be a guess.
        return [
            'label'    => $lang['HomeKpiNoChange'],
            'context'  => $lang['HomeKpiThisMonth'],
            'goodness' => 'flat',
        ];
    }

    return [
        'label'    => ($diff > 0 ? "\u{25B2}" : "\u{25BC}") . ' ' . abs($diff) . '%',
        'context'  => $lang['HomeKpiThisMonth'],
        'goodness' => $diff > 0 ? 'good' : 'bad',   // a higher pass rate is good
    ];
}

// Count of open (status = 1) exceptions — the governance "Open Exceptions" KPI.
// $framework_ids: null = All Frameworks (unscoped, includes exceptions linked
// to no framework), [] = none selected (short-circuits to 0 before any DB
// call), [id,...] = only exceptions linked to one of those frameworks via any
// of the three exception→framework paths (direct framework_id, control-linked
// control_framework_id via framework_control_mappings, or policy-linked
// policy_document_id via document_framework_mappings). COUNT(DISTINCT de.value)
// so an exception matching multiple paths isn't double-counted (`value` is
// document_exceptions' primary key — the table has no `id` column).
function get_open_exceptions_count($framework_ids = null)
{
    if ($framework_ids !== null && empty($framework_ids)) { return 0; }
    $db = db_open();
    $fw_clause = '';
    $params = [];
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND (
            de.framework_id IN ({$ph})
            OR EXISTS (SELECT 1 FROM framework_control_mappings fcm WHERE fcm.control_id = de.control_framework_id AND fcm.framework IN ({$ph}))
            OR EXISTS (SELECT 1 FROM document_framework_mappings dfm WHERE dfm.document_id = de.policy_document_id AND dfm.framework_id IN ({$ph}))
        )";
        $params = array_merge(array_values($framework_ids), array_values($framework_ids), array_values($framework_ids));
    }
    $stmt = $db->prepare("SELECT COUNT(DISTINCT de.value) FROM `document_exceptions` de WHERE de.status = 1 {$fw_clause}");
    $stmt->execute($params);
    $n = (int) $stmt->fetchColumn();
    db_close($db);
    return $n;
}

// Count of policy documents — the governance "Policies" KPI. $framework_ids:
// null = All Frameworks (unscoped, includes policies with no framework link),
// [] = none selected (short-circuits to 0 before any DB call), [id,...] =
// only policies linked to one of those frameworks via
// document_framework_mappings (COUNT(DISTINCT ...) so a policy mapped to
// multiple selected frameworks isn't double-counted).
function get_policies_count($framework_ids = null)
{
    if ($framework_ids !== null && empty($framework_ids)) { return 0; }
    $db = db_open();
    $fw_join = '';
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_join = "INNER JOIN `document_framework_mappings` dfm ON dfm.document_id = documents.id AND dfm.framework_id IN ({$ph})";
    }
    $stmt = $db->prepare("SELECT COUNT(DISTINCT documents.id) FROM `documents` {$fw_join} WHERE documents.`document_type` = 'policies'");
    if (!empty($framework_ids)) { $stmt->execute(array_values($framework_ids)); } else { $stmt->execute(); }
    $n = (int) $stmt->fetchColumn();
    db_close($db);
    return $n;
}

// Count of ALL governance documents (every document_type — policies, standards,
// procedures, guidelines, ...), framework-scoped like get_policies_count().
// Backs the governance dashboard's "Documents" KPI, which sits beside the
// Documents for Review list (also all types). $framework_ids: null = All
// (unscoped), [] = none (returns 0 before any DB call), [id,...] = only
// documents mapped to one of those frameworks via document_framework_mappings.
function get_documents_count($framework_ids = null)
{
    if ($framework_ids !== null && empty($framework_ids)) { return 0; }
    $db = db_open();
    $fw_join = '';
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_join = "INNER JOIN `document_framework_mappings` dfm ON dfm.document_id = documents.id AND dfm.framework_id IN ({$ph})";
    }
    $stmt = $db->prepare("SELECT COUNT(DISTINCT documents.id) FROM `documents` {$fw_join}");
    if (!empty($framework_ids)) { $stmt->execute(array_values($framework_ids)); } else { $stmt->execute(); }
    $n = (int) $stmt->fetchColumn();
    db_close($db);
    return $n;
}

/*******************************************************************************
 * KPI SNAPSHOTS — period-over-period deltas for date-less metrics             *
 *******************************************************************************/
// Metrics snapshotted daily so KPIs whose value has no date column of their own
// (Active Frameworks, Total Controls, Open Exceptions, Policies) can still show
// a real ~30-day delta.
function home_kpi_snapshot_metrics()
{
    return [
        'active_frameworks' => (float) get_frameworks_count(1),
        'total_controls'    => (float) get_framework_controls_count(false),
        'open_exceptions'   => (float) get_open_exceptions_count(),
        'policies'          => (float) get_policies_count(),
    ];
}

// Upsert today's KPI snapshot (one row per metric per day). Driven by the
// core_kpi_snapshot queue job (daily cadence gated there); the UNIQUE
// (metric_key, snapshot_date) makes it safe to repeat. No-op until the table
// exists. Accepts the worker's $db, or opens its own.
function record_kpi_snapshots($db = null)
{
    if (!table_exists('kpi_snapshots')) {
        return;
    }

    $metrics = home_kpi_snapshot_metrics(); // each opens/closes its own connection

    $own = false;
    if ($db === null) {
        $db = db_open();
        $own = true;
    }

    $stmt = $db->prepare("
        INSERT INTO `kpi_snapshots` (`metric_key`, `value`, `snapshot_date`)
        VALUES (:k, :v, CURDATE())
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
    ");
    foreach ($metrics as $k => $v) {
        $stmt->bindValue(':k', $k);
        $stmt->bindValue(':v', $v);
        $stmt->execute();
    }

    if ($own) {
        db_close($db);
    }
}

// Delta for a snapshotted KPI: current value vs the snapshot closest to (and on
// or before) 30 days ago. $up_is_good flips the good/bad colouring per metric.
// Null when there's no ~30-day baseline yet; a neutral "no change" when flat.
function home_snapshot_delta($metric_key, $current_value, $up_is_good = true)
{
    global $lang;

    if (!table_exists('kpi_snapshots')) {
        return null;
    }

    $db = db_open();
    $stmt = $db->prepare("
        SELECT `value` FROM `kpi_snapshots`
        WHERE `metric_key` = :k AND `snapshot_date` <= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY `snapshot_date` DESC LIMIT 1;
    ");
    $stmt->bindValue(':k', $metric_key);
    $stmt->execute();
    $prior = $stmt->fetchColumn();
    db_close($db);

    if ($prior === false) {
        return null;
    }

    $diff = (float) $current_value - (float) $prior;
    if (abs($diff) < 0.005) {
        return ['label' => $lang['HomeKpiNoChange'], 'context' => $lang['HomeKpiThisMonth'], 'goodness' => 'flat'];
    }

    $mag = abs($diff);
    $n = ($mag == floor($mag)) ? (string) (int) $mag : (string) round($mag, 1);
    return [
        'label'    => ($diff > 0 ? "\u{25B2}" : "\u{25BC}") . ' ' . $n,
        'context'  => $lang['HomeKpiThisMonth'],
        'goodness' => (($diff > 0) === $up_is_good) ? 'good' : 'bad',
    ];
}

// Period-over-period delta derived from a live KPI series (first day vs last day)
// instead of the kpi_snapshots table — for the risk KPIs that reconstruct their
// own history (they have no snapshot warm-up). Same output shape/semantics as
// home_snapshot_delta: null when there's no series, a neutral "no change" when
// flat, and good/bad coloured by whether the direction is good FOR THIS METRIC.
function home_series_delta(array $series, $up_is_good = true)
{
    global $lang;

    if (count($series) < 2) {
        return null;
    }

    $prior   = (float) ($series[0]['value'] ?? 0);              // ~30 days ago
    $current = (float) ($series[count($series) - 1]['value'] ?? 0); // today
    $diff    = $current - $prior;

    if (abs($diff) < 0.005) {
        return ['label' => $lang['HomeKpiNoChange'], 'context' => $lang['HomeKpiThisMonth'], 'goodness' => 'flat'];
    }

    $mag = abs($diff);
    $n   = ($mag == floor($mag)) ? (string) (int) $mag : (string) round($mag, 1);
    return [
        'label'    => ($diff > 0 ? "\u{25B2}" : "\u{25BC}") . ' ' . $n,
        'context'  => $lang['HomeKpiThisMonth'],
        'goodness' => (($diff > 0) === $up_is_good) ? 'good' : 'bad',
    ];
}

/*******************************************************************************
 * KPI SPARKLINES — a ~30-day trend line drawn in the KPI tile's bottom-right   *
 *******************************************************************************/
// The tile's single value + delta only tell you "now" and "vs a month ago". The
// sparkline fills the tile's empty bottom-right quadrant with the shape of the
// last N days so the trend reads at a glance. Data comes from real history, per
// metric: the two risk KPIs are reconstructed live from risk lifecycle dates
// (team-scoped, so they never leak risks the user can't see — and they work
// retroactively, no snapshot warm-up), the pass-rate is reconstructed from test
// result dates, and metrics with no date column of their own (Active Frameworks)
// read the daily kpi_snapshots history.

// Build a chronological axis of the last $days calendar days as 'Y-m-d' strings,
// ending today. $today is injectable so the pure series builders below are
// deterministic under test.
function kpi_build_day_axis($days = 30, $today = null)
{
    $end = ($today !== null) ? strtotime($today) : strtotime(date('Y-m-d'));
    $axis = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $axis[] = date('Y-m-d', $end - $i * 86400);
    }
    return $axis;
}

// Pure: given risk lifecycle rows [['start_date'=>'Y-m-d','end_date'=>'Y-m-d'|null], ...]
// count how many were open on each day of $axis. A risk is open on day D if it
// was submitted on/before D and either never closed or closed strictly after D.
// 'Y-m-d' strings compare correctly with <= / >.
function kpi_compute_open_series(array $rows, array $axis)
{
    $series = [];
    foreach ($axis as $d) {
        $count = 0;
        foreach ($rows as $r) {
            $start = $r['start_date'] ?? null;
            $end   = $r['end_date'] ?? null;
            if ($start !== null && $start <= $d && ($end === null || $end > $d)) {
                $count++;
            }
        }
        $series[] = ['date' => $d, 'value' => $count];
    }
    return $series;
}

// Pure: like kpi_compute_open_series, but also requires the risk to have had no
// management review yet as of day D. $rows carry an extra 'first_review' ('Y-m-d'
// or null). "Needs review on D" = open on D AND (never reviewed OR first review
// happened after D).
function kpi_compute_needs_review_series(array $rows, array $axis)
{
    $series = [];
    foreach ($axis as $d) {
        $count = 0;
        foreach ($rows as $r) {
            $start  = $r['start_date'] ?? null;
            $end    = $r['end_date'] ?? null;
            $review = $r['first_review'] ?? null;
            $open   = ($start !== null && $start <= $d && ($end === null || $end > $d));
            if ($open && ($review === null || $review > $d)) {
                $count++;
            }
        }
        $series[] = ['date' => $d, 'value' => $count];
    }
    return $series;
}

// Pure: given all Pass/Fail control test results [['control'=>id,'date'=>'Y-m-d','pass'=>1|0], ...]
// (ordered oldest-first), compute the pass-rate % on each day of $axis using the
// latest result per control as of that day. A day with no results yet yields a
// null value (rendered as a gap / dropped from the spark).
function kpi_compute_pass_rate_series(array $results, array $axis)
{
    $series = [];
    foreach ($axis as $d) {
        $latest = []; // control_id => ['date'=>, 'pass'=>]
        foreach ($results as $r) {
            if ($r['date'] <= $d) {
                $c = $r['control'];
                if (!isset($latest[$c]) || $r['date'] >= $latest[$c]['date']) {
                    $latest[$c] = $r;
                }
            }
        }
        $tested = count($latest);
        if ($tested === 0) {
            $series[] = ['date' => $d, 'value' => null];
            continue;
        }
        $pass = 0;
        foreach ($latest as $l) {
            if (!empty($l['pass'])) { $pass++; }
        }
        $series[] = ['date' => $d, 'value' => (int) round(($pass / $tested) * 100)];
    }
    return $series;
}

// DB: failing-control count per day for the last $days — a control counts as
// failing on day D when its most recent Pass/Fail result as of D is 'Fail'.
// Reuses the same result set as the pass-rate series (active frameworks, global).
function kpi_series_failing_controls($days = 30)
{
    $db = db_open();
    $stmt = $db->prepare("
        SELECT
            fc.id AS control,
            DATE(tr.submission_date) AS d,
            CASE WHEN tr.test_result = 'Pass' THEN 1 ELSE 0 END AS pass
        FROM framework_controls fc
        INNER JOIN framework_control_mappings fcm ON fc.id = fcm.control_id
        INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1
        INNER JOIN audit_control_map acm ON acm.framework_control_id = fc.id
        INNER JOIN framework_control_test_audits ta ON ta.id = acm.audit_id
        INNER JOIN framework_control_test_results tr ON tr.test_audit_id = ta.id
        WHERE fc.deleted = 0 AND tr.test_result IN ('Pass', 'Fail')
        ORDER BY tr.submission_date, tr.id;
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $results = [];
    foreach ($rows as $r) {
        $results[] = ['control' => $r['control'], 'date' => $r['d'], 'pass' => (int) $r['pass']];
    }
    return kpi_compute_failing_count_series($results, kpi_build_day_axis($days));
}

// Pure: given Pass/Fail control results [['control'=>id,'date'=>'Y-m-d','pass'=>1|0], ...]
// count the controls whose latest result as of each day of $axis is a Fail.
function kpi_compute_failing_count_series(array $results, array $axis)
{
    $series = [];
    foreach ($axis as $d) {
        $latest = []; // control_id => ['date'=>, 'pass'=>]
        foreach ($results as $r) {
            if ($r['date'] <= $d) {
                $c = $r['control'];
                if (!isset($latest[$c]) || $r['date'] >= $latest[$c]['date']) {
                    $latest[$c] = $r;
                }
            }
        }
        $fail = 0;
        foreach ($latest as $l) {
            if (empty($l['pass'])) { $fail++; }
        }
        $series[] = ['date' => $d, 'value' => $fail];
    }
    return $series;
}

// DB: open-risk count per day for the last $days, scoped to the user's teams
// when Team Separation is active (same guard as the tile's value/delta).
function kpi_series_open_risks($days = 30)
{
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT
            `rsk`.`id` AS id,
            DATE(`rsk`.`submission_date`) AS start_date,
            CASE WHEN `rsk`.`status` = 'Closed' THEN DATE(`c`.`closure_date`) ELSE NULL END AS end_date
        FROM `risks` rsk
        LEFT JOIN `closures` c ON `rsk`.`close_id` = `c`.`id`
        {$sep_from}
        WHERE 1 = 1 {$sep_where};
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    return kpi_compute_open_series($rows, kpi_build_day_axis($days));
}

// DB: unreviewed-open-risk count per day for the last $days, team-scoped.
function kpi_series_needs_review($days = 30)
{
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT
            `rsk`.`id` AS id,
            DATE(`rsk`.`submission_date`) AS start_date,
            CASE WHEN `rsk`.`status` = 'Closed' THEN DATE(`c`.`closure_date`) ELSE NULL END AS end_date,
            (SELECT DATE(MIN(`mr`.`submission_date`)) FROM `mgmt_reviews` mr WHERE `mr`.`risk_id` = `rsk`.`id`) AS first_review
        FROM `risks` rsk
        LEFT JOIN `closures` c ON `rsk`.`close_id` = `c`.`id`
        {$sep_from}
        WHERE 1 = 1 {$sep_where};
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    return kpi_compute_needs_review_series($rows, kpi_build_day_axis($days));
}

// DB: unmitigated-open-risk count per day for the last $days, team-scoped.
// "Unmitigated" mirrors get_unmitigated_open_risk_count() (open + mitigation_id 0);
// the "became mitigated" date is the risk's mitigation submission_date. Shares the
// needs-review compute by aliasing that date as 'first_review' (same shape: open on
// D AND (no mitigation OR mitigation started after D)).
function kpi_series_unmitigated($days = 30)
{
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT
            `rsk`.`id` AS id,
            DATE(`rsk`.`submission_date`) AS start_date,
            CASE WHEN `rsk`.`status` = 'Closed' THEN DATE(`c`.`closure_date`) ELSE NULL END AS end_date,
            CASE WHEN `rsk`.`mitigation_id` <> 0 THEN DATE(`mit`.`submission_date`) ELSE NULL END AS first_review
        FROM `risks` rsk
        LEFT JOIN `closures` c ON `rsk`.`close_id` = `c`.`id`
        LEFT JOIN `mitigations` mit ON `rsk`.`mitigation_id` = `mit`.`id`
        {$sep_from}
        WHERE 1 = 1 {$sep_where};
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    return kpi_compute_needs_review_series($rows, kpi_build_day_axis($days));
}

// DB: cumulative closed-risk count per day for the last $days, team-scoped — a
// risk counts on day D once its closure_date is on/before D. A risk can be
// closed, reopened, and closed again (multiple `closures` rows), so this keys on
// the LAST closure date per unique risk (MAX(closure_date)), matching the current
// "currently closed" cohort that get_closed_risks() counts.
function kpi_series_closed_risks($days = 30)
{
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT
            `rsk`.`id` AS id,
            (SELECT DATE(MAX(`cc`.`closure_date`)) FROM `closures` cc WHERE `cc`.`risk_id` = `rsk`.`id`) AS closed_date
        FROM `risks` rsk
        {$sep_from}
        WHERE `rsk`.`status` = 'Closed' {$sep_where};
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    return kpi_compute_closed_series($rows, kpi_build_day_axis($days));
}

// Pure: given closed-risk rows [['closed_date'=>'Y-m-d'|null], ...], count how
// many were closed on/before each day of $axis (a monotonic, cumulative series).
function kpi_compute_closed_series(array $rows, array $axis)
{
    $series = [];
    foreach ($axis as $d) {
        $count = 0;
        foreach ($rows as $r) {
            $cd = $r['closed_date'] ?? null;
            if ($cd !== null && $cd <= $d) {
                $count++;
            }
        }
        $series[] = ['date' => $d, 'value' => $count];
    }
    return $series;
}

// DB: control pass-rate % per day for the last $days. Global (compliance-wide;
// not team-scoped). Mirrors the joins in home_control_pass_rate_percent().
function kpi_series_pass_rate($days = 30)
{
    $db = db_open();
    $stmt = $db->prepare("
        SELECT
            fc.id AS control,
            DATE(tr.submission_date) AS d,
            CASE WHEN tr.test_result = 'Pass' THEN 1 ELSE 0 END AS pass
        FROM framework_controls fc
        INNER JOIN framework_control_mappings fcm ON fc.id = fcm.control_id
        INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1
        INNER JOIN audit_control_map acm ON acm.framework_control_id = fc.id
        INNER JOIN framework_control_test_audits ta ON ta.id = acm.audit_id
        INNER JOIN framework_control_test_results tr ON tr.test_audit_id = ta.id
        WHERE fc.deleted = 0 AND tr.test_result IN ('Pass', 'Fail')
        ORDER BY tr.submission_date, tr.id;
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $results = [];
    foreach ($rows as $r) {
        $results[] = ['control' => $r['control'], 'date' => $r['d'], 'pass' => (int) $r['pass']];
    }
    return kpi_compute_pass_rate_series($results, kpi_build_day_axis($days));
}

// DB: read a snapshotted metric's last $days of daily values from kpi_snapshots.
// Used for metrics with no date column of their own (Active Frameworks). Sparse
// by nature — only days the snapshot job ran are present. Empty until the table
// exists / accumulates history.
function kpi_series_snapshot($metric_key, $days = 30)
{
    if (!table_exists('kpi_snapshots')) {
        return [];
    }
    $db = db_open();
    $stmt = $db->prepare("
        SELECT `snapshot_date` AS date, `value`
        FROM `kpi_snapshots`
        WHERE `metric_key` = :k AND `snapshot_date` >= DATE_SUB(CURDATE(), INTERVAL :d DAY)
        ORDER BY `snapshot_date` ASC;
    ");
    $stmt->bindValue(':k', $metric_key);
    $stmt->bindValue(':d', (int) $days, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $series = [];
    foreach ($rows as $r) {
        $series[] = ['date' => $r['date'], 'value' => (float) $r['value']];
    }
    return $series;
}

// Build the inline-SVG sparkline for a $series of ['value'=>…] points. The whole
// trend carries the state colour: a line + a faint area wash both tinted by the
// window's goodness (green when the N-day move is good FOR THIS metric, red when
// bad, neutral when flat — $up_is_good sets the polarity). The goodness lands as
// a modifier class on the <svg>; the actual colours live in SCSS tokens (see
// .sr-kpi__spark--good/--bad/--flat). Returns '' when there aren't at least two
// real points to draw. All coordinates are generated numerically, so no user
// input reaches the markup.
function render_kpi_sparkline_svg(array $series, $up_is_good = true)
{
    // Drop gap points (null values) — need ≥2 real points to draw a line.
    $pts = [];
    foreach ($series as $p) {
        if (isset($p['value']) && $p['value'] !== null) {
            $pts[] = (float) $p['value'];
        }
    }
    $n = count($pts);
    if ($n < 2) {
        return '';
    }

    $min = min($pts);
    $max = max($pts);
    $range = ($max - $min) ?: 1.0;

    $W = 76; $H = 30; $padX = 1; $padY = 3;
    $innerW = $W - 2 * $padX;
    $innerH = $H - 2 * $padY;
    $baseline = $H - $padY;

    $coords = [];
    foreach ($pts as $i => $v) {
        $x = $padX + ($i / ($n - 1)) * $innerW;
        $y = ($max === $min)
            ? $padY + $innerH / 2                       // flat series → centre line
            : $padY + (1 - ($v - $min) / $range) * $innerH;
        $coords[] = [round($x, 2), round($y, 2)];
    }

    // Trend goodness: net move across the window, in this metric's polarity.
    $diff = $pts[$n - 1] - $pts[0];
    $goodness = (abs($diff) < 0.0001)
        ? 'flat'
        : ((($diff > 0) === (bool) $up_is_good) ? 'good' : 'bad');

    // Line vertices, then close the area down to the baseline for the fill wash.
    $line = implode(' ', array_map(fn($c) => $c[0] . ',' . $c[1], $coords));
    $area = $line . ' ' . $coords[$n - 1][0] . ',' . $baseline
                  . ' ' . $coords[0][0] . ',' . $baseline;

    return "<svg class='sr-kpi__spark sr-kpi__spark--{$goodness}' viewBox='0 0 {$W} {$H}' preserveAspectRatio='none' aria-hidden='true' focusable='false'>"
        . "<polygon class='sr-kpi__spark-area' points='{$area}'/>"
        . "<polyline class='sr-kpi__spark-line' points='{$line}'/>"
        . "</svg>";
}

// Convenience: resolve a metric key to its series source and render the spark.
// $up_is_good sets the endpoint-dot polarity for the trend. Any unknown key
// falls through to the daily-snapshot history.
function kpi_sparkline_for($metric, $up_is_good = true, $days = 30)
{
    switch ($metric) {
        case 'open_risks':   $series = kpi_series_open_risks($days);   break;
        case 'needs_review': $series = kpi_series_needs_review($days); break;
        case 'unmitigated':  $series = kpi_series_unmitigated($days);  break;
        case 'closed_risks': $series = kpi_series_closed_risks($days); break;
        case 'pass_rate':    $series = kpi_series_pass_rate($days);    break;
        default:             $series = kpi_series_snapshot($metric, $days); break;
    }
    return render_kpi_sparkline_svg($series, $up_is_good);
}

// Renders a home KPI stat-tile: an eyebrow label, a large charcoal value, an
// optional delta vs. the prior period, and an optional trend sparkline. $delta
// (when supplied) is ['label' => '+12 this month', 'goodness' => 'good'|'bad'] —
// the value stays charcoal and only the delta is coloured, by whether the change
// is good or bad FOR THIS METRIC (never by arrow direction). Callers pass a delta
// only when a prior-period value exists; omit it otherwise rather than show a
// mis-coloured one. $sparkline is a pre-rendered SVG string (from
// kpi_sparkline_for()) or '' for none; it sits bottom-right, sharing the foot row
// with the delta. $value_tone is an optional trailing flag ('danger', 'success',
// or '') that colours the NUMBER itself: 'danger' = App Red for tiles that signal
// "needs attention" (the Define Tests band's Overdue/Failing/Untested Controls
// tiles); 'success' = the $success green for a "good" counterpart (the Passing
// tile). A deliberate, scoped spend confined to the value, not the whole tile
// (see .sr-kpi__value--danger / --success in _home.scss). Trailing + defaulted to
// '' so every existing caller is unaffected.
function render_kpi_tile($value, $label_key, $cta_url, $delta = null, $domain_key = null, $sparkline = '', $unit_key = '', $subtitle = '', $value_tone = '')
{
    global $lang, $escaper;

    $label = isset($lang[$label_key]) ? $lang[$label_key] : $label_key;

    // Optional unit qualifier rendered beneath the number (e.g. "incidents",
    // "days"). Keeps the value itself short — a bare "42.3" instead of "42.3d" —
    // so the number + sparkline fit the narrow KPI tiles without the value
    // crowding the spark.
    $unit_html = '';
    if ($unit_key !== '') {
        $unit_label = isset($lang[$unit_key]) ? $lang[$unit_key] : $unit_key;
        $unit_html = "<span class='sr-kpi__unit'>" . $escaper->escapeHtml($unit_label) . "</span>";
    }

    // Provenance tag (top-right): which module this metric belongs to. Home is a
    // cross-domain digest, so the tag tells the reader at a glance whether a tile
    // is a Risk / Compliance / Governance metric.
    $domain_html = '';
    if ($domain_key !== null && isset($lang[$domain_key])) {
        $domain_html = "<span class='sr-domain sr-kpi__domain'>" . $escaper->escapeHtml($lang[$domain_key]) . "</span>";
    }

    $delta_html = '';
    if (is_array($delta) && isset($delta['label']) && $delta['label'] !== '') {
        // good = green, bad = red, flat = neutral grey ("no change"); anything
        // else falls back to bad so an unlabelled delta can't render un-styled.
        $goodness = in_array($delta['goodness'] ?? '', ['good', 'bad', 'flat'], true)
            ? $delta['goodness'] : 'bad';
        $context  = (isset($delta['context']) && $delta['context'] !== '')
            ? "<span class='sr-kpi__delta-context'>" . $escaper->escapeHtml($delta['context']) . "</span>"
            : '';
        $delta_html = "<span class='sr-kpi__delta sr-kpi__delta--" . $goodness . "'>"
            . $escaper->escapeHtml($delta['label']) . $context . "</span>";
    }

    // Optional descriptive subtitle (Define Tests insights band). Renders in the
    // foot slot in place of a period-over-period delta; muted, single line.
    $sub_html = '';
    if (is_string($subtitle) && $subtitle !== '') {
        $sub_html = "<span class='sr-kpi__sub'>" . $escaper->escapeHtml($subtitle) . "</span>";
    }

    // The sparkline always sits beside the value (bottom-aligned to it), and the
    // delta — when present — drops to its own foot row below. This keeps the
    // spark's bottom lined up with the number to its left and gives the delta the
    // full tile width (so a longer delta never has to fight the spark for room).
    $spark_html = is_string($sparkline) ? $sparkline : '';

    $foot_html = '';
    if ($delta_html !== '' || $sub_html !== '') {
        $foot_html = "<span class='sr-kpi__foot'>" . $delta_html . $sub_html . "</span>";
    }

    // App-Red-spent-once: only the numeric value gets the danger class, never
    // the tile/label -- $value_tone is a fixed internal 'danger'|'' flag, not
    // user input, so no escaping is needed for the class list itself.
    $value_class = 'sr-kpi__value';
    if ($value_tone === 'danger') {
        $value_class .= ' sr-kpi__value--danger';
    } elseif ($value_tone === 'success') {
        $value_class .= ' sr-kpi__value--success';
    }

    echo "
        <a class='sr-kpi' href='" . $escaper->escapeHtmlAttr($cta_url) . "'>
            <span class='sr-kpi__top'>
                <span class='sr-kpi__label'>" . $escaper->escapeHtml($label) . "</span>
                " . $domain_html . "
            </span>
            <span class='sr-kpi__value-row'>
                <span class='sr-kpi__value-block'>
                    <span class='" . $value_class . "'>" . $escaper->escapeHtml($value) . "</span>
                    " . $unit_html . "
                </span>
                " . $spark_html . "
            </span>
            " . $foot_html . "
        </a>
    ";
}

/*************************************
 * FUNCTION: RENDER WHATS NEXT WIDGET *
 *************************************/
// Echo the "What's Next?" action feed. Items come pre-sorted from
// get_whats_next_items(); an empty feed renders a warm "all caught up" state.
function render_whats_next_widget($domain = null)
{
    global $lang, $escaper;

    $items = get_whats_next_items($domain);

    // The widget frame — header + body — is rendered whether or not there are
    // items, so the tile always reads as a framed widget (matches the KPI tiles).
    echo "<div class='sr-widget'>";
    echo   "<div class='sr-widget__head'><span class='sr-widget__title'>"
         . $escaper->escapeHtml($lang['WhatsNext']) . "</span></div>";
    echo   "<div class='sr-widget__body'>";

    if (empty($items)) {
        echo "<div class='sr-whatsnext sr-whatsnext--empty'>"
           . "<span class='sr-whatsnext__empty-title'>" . $escaper->escapeHtml($lang['WhatsNextAllCaughtUp']) . "</span>"
           . "</div>";
    } else {
        echo "<ul class='sr-whatsnext'>";
        foreach ($items as $item) {
            $label = isset($lang[$item['label_key']]) ? $lang[$item['label_key']] : $item['label_key'];
            // Right-hand soft state pill: urgency by band. Work items show the
            // count; one-time setup items show a "Set up" tag.
            switch ($item['band']) {
                case 'overdue': $pill_mod = 'danger'; $pill_text = (string)(int)$item['count']; break;
                case 'setup':   $pill_mod = 'info';   $pill_text = $lang['Setup'];              break;
                default:        $pill_mod = 'warn';   $pill_text = (string)(int)$item['count']; break; // due_soon
            }
            echo "
                <li class='sr-whatsnext__item'>
                    <a href='" . $escaper->escapeHtmlAttr($item['cta_url']) . "'>
                        <span class='sr-whatsnext__text'>" . $escaper->escapeHtml($label) . "</span>
                        <span class='sr-wn-pill sr-wn-pill--" . $pill_mod . "'><span class='sr-wn-pill__dot'></span>"
                        . $escaper->escapeHtml($pill_text) . "</span>
                    </a>
                </li>
            ";
        }
        echo "</ul>";
    }

    echo   "</div>";
    echo "</div>";
}

/*******************************************************************************
 * FUNCTION: RENDER LIST WIDGET                                                 *
 *******************************************************************************/
// Generic framed list widget (design-system .sr-widget + .sr-whatsnext): a
// header (title + optional domain tag) over a short list of name + pill rows.
// Powers the dashboard list widgets (My Highest Risks, Past-Due Reviews,
// Upcoming Tests, Recent Failures, Policies Up for Review, Expiring Exceptions).
//
// $items: array of [
//   'name'  => string,          // row label
//   'href'  => string,          // row link
//   'pill'  => string,          // pill text
//   'band'  => 'warn'|'danger'|'info'|'level',
//   'color' => hex,             // only when band === 'level' (solid severity pill)
// ]
function render_list_widget($title, $domain_key, $items, $empty_text = null)
{
    global $lang, $escaper;

    $domain_html = ($domain_key !== null && isset($lang[$domain_key]))
        ? "<span class='sr-domain sr-widget__domain'>" . $escaper->escapeHtml($lang[$domain_key]) . "</span>"
        : '';

    echo "<div class='sr-widget'>";
    echo   "<div class='sr-widget__head'>"
         . "<span class='sr-widget__title'>" . $escaper->escapeHtml($title) . "</span>"
         . $domain_html
         . "</div>";
    echo   "<div class='sr-widget__body'>";

    if (empty($items)) {
        $empty = $empty_text !== null ? $empty_text : $lang['WhatsNextAllCaughtUp'];
        echo "<div class='sr-whatsnext sr-whatsnext--empty'>"
           . "<span class='sr-whatsnext__empty-title'>" . $escaper->escapeHtml($empty) . "</span>"
           . "</div>";
    } else {
        echo "<ul class='sr-whatsnext'>";
        foreach ($items as $item) {
            $name = (string)($item['name'] ?? '');
            $href = (string)($item['href'] ?? '#');
            $band = (string)($item['band'] ?? 'warn');
            $pill = (string)($item['pill'] ?? '');

            if ($band === 'level') {
                // Solid severity pill in the configured level colour (allow-listed).
                $color = $escaper->escapeCssColor($item['color'] ?? 'transparent');
                $pill_html = "<span class='sr-wn-level' style='background-color:" . $color . "'>"
                    . $escaper->escapeHtml($pill) . "</span>";
            } else {
                $mod = in_array($band, ['warn', 'danger', 'info'], true) ? $band : 'warn';
                $pill_html = "<span class='sr-wn-pill sr-wn-pill--" . $mod . "'><span class='sr-wn-pill__dot'></span>"
                    . $escaper->escapeHtml($pill) . "</span>";
            }

            // Optional second pill (e.g. a severity pill alongside a status pill),
            // rendered to the right of the first inside the row link.
            $pill2_html = '';
            if (!empty($item['pill2'])) {
                $band2 = (string)($item['band2'] ?? 'warn');
                $pill2 = (string)$item['pill2'];
                if ($band2 === 'level') {
                    $color2 = $escaper->escapeCssColor($item['color2'] ?? 'transparent');
                    $pill2_html = "<span class='sr-wn-level' style='background-color:" . $color2 . "'>"
                        . $escaper->escapeHtml($pill2) . "</span>";
                } else {
                    $mod2 = in_array($band2, ['warn', 'danger', 'info'], true) ? $band2 : 'warn';
                    $pill2_html = "<span class='sr-wn-pill sr-wn-pill--" . $mod2 . "'><span class='sr-wn-pill__dot'></span>"
                        . $escaper->escapeHtml($pill2) . "</span>";
                }
            }

            // Trailing action icon, rendered OUTSIDE the row link and in a slot
            // that is ALWAYS present (even when empty) so the day chips line up in
            // a fixed column across every row:
            //   - 'run' + audit_id → green "Go to Test": navigates to the existing
            //     open audit (never starts a duplicate).
            //   - 'run' + test_id  → "Start the Test": creates a new audit record
            //     + opens it.
            //   - 'auto'           → non-clickable "Test Starts Automatically" icon.
            $action_inner = '';
            $action = $item['action'] ?? null;
            if (is_array($action)) {
                $atype     = $action['type'] ?? '';
                $title_attr = $escaper->escapeHtmlAttr((string) ($action['title'] ?? ''));
                $title_txt  = $escaper->escapeHtml((string) ($action['title'] ?? ''));
                if ($atype === 'run') {
                    // Existing audit → green "goto" variant with a go-to (arrow)
                    // icon; otherwise the neutral initiate variant with a play icon.
                    $is_goto   = isset($action['audit_id']);
                    $run_class = 'sr-wn-run' . ($is_goto ? ' sr-wn-run--goto' : '');
                    $run_icon  = $is_goto ? 'fa-arrow-right' : 'fa-play';
                    $data_attr = $is_goto
                        ? " data-audit-id='" . (int) $action['audit_id'] . "'"
                        : (isset($action['test_id']) ? " data-test-id='" . (int) $action['test_id'] . "'" : '');
                    $action_inner = "<button type='button' class='{$run_class}' title='{$title_attr}'{$data_attr}>"
                        . "<i class='fas {$run_icon}' aria-hidden='true'></i>"
                        . "<span class='visually-hidden'>{$title_txt}</span></button>";
                } elseif ($atype === 'auto') {
                    $action_inner = "<span class='sr-wn-auto' title='{$title_attr}'>"
                        . "<i class='fas fa-robot' aria-hidden='true'></i>"
                        . "<span class='visually-hidden'>{$title_txt}</span></span>";
                }
            }

            // Optional muted control sub-label under the name (e.g. which control a
            // test covers). Falls back to a plain single-line name when absent.
            if (!empty($item['context'])) {
                $name_html = "<span class='sr-whatsnext__namewrap'>"
                    . "<span class='sr-whatsnext__text'>" . $escaper->escapeHtml($name) . "</span>"
                    . "<span class='sr-whatsnext__context'>" . $escaper->escapeHtml((string) $item['context']) . "</span>"
                    . "</span>";
            } else {
                $name_html = "<span class='sr-whatsnext__text'>" . $escaper->escapeHtml($name) . "</span>";
            }

            echo "
                <li class='sr-whatsnext__item'>
                    <a href='" . $escaper->escapeHtmlAttr($href) . "'>
                        " . $name_html . "
                        " . $pill_html . "
                        " . $pill2_html . "
                    </a>
                    <span class='sr-wn-action'>" . $action_inner . "</span>
                </li>
            ";
        }
        echo "</ul>";
    }

    echo   "</div>";
    echo "</div>";
}

/*******************************************************************************
 * GETTING STARTED WIDGET (home dashboard onboarding)                           *
 * One card per granular permission the user holds; completion derived live;    *
 * only dismissals persisted (getting_started_dismissed). Distinct from What's  *
 * Next (running work) — this teaches first actions and gets out of the way.    *
 * Spec: docs/superpowers/specs/2026-07-13-getting-started-widget.md            *
 *******************************************************************************/

// Ordering buckets, most-onboarding-first.
function getting_started_area_order() {
    // 'ai' is its own bucket at the end so "Configure AI" is always the last step
    // (after Register / Load SCF / Invite and all the practitioner actions).
    return ['setup', 'risk', 'compliance', 'assets', 'ai'];
}

// The step catalog — the server-side source of truth. Adding a step here is all
// it takes; the client never invents steps. 'gate' is the granular permission
// the step needs; 'cta' is relative to /reports/ (where home renders).
function getting_started_catalog() {
    // Each card's "Learn more" is routed through simplerisk.com/support/<topic> so
    // the targets can be redirected later without an app change.
    return [
        'register'         => ['area'=>'setup',      'gate'=>'admin',            'cta'=>'../admin/register.php',                       'title'=>'GSRegisterTitle',      'desc'=>'GSRegisterDesc',      'cta_label'=>'GSRegisterCta',      'doc'=>'https://www.simplerisk.com/support/admin-guide/register'],
        // SCF is a two-step onboarding pipeline: first Install (download the extra
        // files from the Register/Upgrade page), then Activate (turn it on from the
        // SCF admin page). Each step's visibility gate opens only in its window.
        'install_scf'      => ['area'=>'setup',      'gate'=>'admin',            'cta'=>'../admin/register.php',                       'title'=>'GSScfTitle',           'desc'=>'GSScfDesc',           'cta_label'=>'GSScfCta',           'doc'=>'https://www.simplerisk.com/support/scf'],
        'activate_scf'     => ['area'=>'setup',      'gate'=>'admin',            'cta'=>'../admin/securecontrolsframework.php',        'title'=>'GSActivateScfTitle',   'desc'=>'GSActivateScfDesc',   'cta_label'=>'GSActivateScfCta',   'doc'=>'https://www.simplerisk.com/support/scf'],
        // Final SCF step: with the extra active, enable the frameworks that apply.
        'enable_frameworks'=> ['area'=>'setup',      'gate'=>'admin',            'cta'=>'../admin/securecontrolsframework.php',        'title'=>'GSEnableFrameworksTitle','desc'=>'GSEnableFrameworksDesc','cta_label'=>'GSEnableFrameworksCta','doc'=>'https://www.simplerisk.com/support/scf'],
        // With the SCF active, take a self-assessment against a framework (generates
        // risks from failed controls). Gated on the 'assessments' permission.
        'self_assessment'  => ['area'=>'setup',      'gate'=>'assessments',      'cta'=>'../assessments/index.php',                    'title'=>'GSSelfAssessTitle',    'desc'=>'GSSelfAssessDesc',    'cta_label'=>'GSSelfAssessCta',    'doc'=>'https://www.simplerisk.com/support/self-assessment'],
        'invite'           => ['area'=>'setup',      'gate'=>'admin',            'cta'=>'../admin/user_management.php',                 'title'=>'GSInviteTitle',        'desc'=>'GSInviteDesc',        'cta_label'=>'GSInviteCta',        'doc'=>'https://www.simplerisk.com/support/users'],
        'submit_risks'     => ['area'=>'risk',       'gate'=>'submit_risks',     'cta'=>'../management/index.php',                     'title'=>'GSSubmitRiskTitle',    'desc'=>'GSSubmitRiskDesc',    'cta_label'=>'GSSubmitRiskCta',    'doc'=>'https://www.simplerisk.com/support/risk'],
        'plan_mitigations' => ['area'=>'risk',       'gate'=>'plan_mitigations', 'cta'=>'../management/plan_mitigations.php',          'title'=>'GSMitigateTitle',      'desc'=>'GSMitigateDesc',      'cta_label'=>'GSMitigateCta',      'doc'=>'https://www.simplerisk.com/support/mitigation'],
        'risk_review'      => ['area'=>'risk',       'gate'=>'review_any',       'cta'=>'../management/management_review.php',         'title'=>'GSReviewTitle',        'desc'=>'GSReviewDesc',        'cta_label'=>'GSReviewCta',        'doc'=>'https://www.simplerisk.com/support/review'],
        'define_tests'     => ['area'=>'compliance', 'gate'=>'define_tests',     'cta'=>'../compliance/index.php',                     'title'=>'GSDefineTestTitle',    'desc'=>'GSDefineTestDesc',    'cta_label'=>'GSDefineTestCta',    'doc'=>'https://www.simplerisk.com/support/test'],
        'initiate_audits'  => ['area'=>'compliance', 'gate'=>'initiate_audits',  'cta'=>'../compliance/audit_initiation.php',          'title'=>'GSInitiateAuditTitle', 'desc'=>'GSInitiateAuditDesc', 'cta_label'=>'GSInitiateAuditCta', 'doc'=>'https://www.simplerisk.com/support/audit'],
        'asset'            => ['area'=>'assets',     'gate'=>'asset',            'cta'=>'../assets/index.php',                         'title'=>'GSAssetTitle',         'desc'=>'GSAssetDesc',         'cta_label'=>'GSAssetCta',         'doc'=>'https://www.simplerisk.com/support/assets'],
        // Configure AI is intentionally last (its own 'ai' area, ordered after
        // everything else).
        'ai'               => ['area'=>'ai',         'gate'=>'admin',            'cta'=>'../admin/artificial_intelligence_core.php',   'title'=>'GSAiTitle',            'desc'=>'GSAiDesc',            'cta_label'=>'GSAiCta',            'doc'=>'https://www.simplerisk.com/support/ai'],
    ];
}

// Does the current user hold the permission a step requires?
function getting_started_step_gated($gate) {
    if ($gate === 'admin') return is_admin();
    if ($gate === 'review_any') {
        foreach (['review_veryhigh','review_high','review_medium','review_low','review_insignificant'] as $p) {
            if (check_permission($p)) return true;
        }
        return false;
    }
    return check_permission($gate);
}

// Guarded COUNT(*) — returns 0 if the table is absent, so a missing core/extra
// table degrades to "not done" rather than fataling.
function getting_started_count($db, $table, $sql, $params = []) {
    if (!table_exists($table)) return 0;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

// Derived completion — computed live from existing data, never stored. Per-user
// where a creator column exists (risks.submitted_by, mgmt_reviews.reviewer);
// instance-wide "any exist" otherwise (spec decision 2).
function getting_started_step_complete($key, $db, $uid) {
    switch ($key) {
        case 'register':         return get_setting('registration_registered') == 1;
        // Install = the SCF extra's code files are present (downloaded from the
        // Register/Upgrade page). is_extra_installed() checks exactly that.
        case 'install_scf':      return is_extra_installed('complianceforgescf');
        // Activate = the installed extra has been turned on. complianceforge_scf_extra()
        // is the activation predicate (the SCF admin page's Activate button sets it).
        case 'activate_scf':     return function_exists('complianceforge_scf_extra') && complianceforge_scf_extra();
        // Enable frameworks = at least one framework is active (status = 1).
        case 'enable_frameworks': return getting_started_count($db, 'frameworks', "SELECT COUNT(*) FROM `frameworks` WHERE `status` = 1") > 0;
        // Self-assessment = the user has started at least one self-assessment.
        case 'self_assessment':  return getting_started_count($db, 'self_assessments', "SELECT COUNT(*) FROM `self_assessments` WHERE `started_by` = :uid", [':uid'=>$uid]) > 0;
        case 'invite':           return getting_started_count($db, 'user', "SELECT COUNT(*) FROM `user` WHERE `enabled` = 1") > 1;
        case 'ai':               return !empty(get_setting('ai_context_last_saved'));
        case 'submit_risks':     return getting_started_count($db, 'risks', "SELECT COUNT(*) FROM `risks` WHERE `submitted_by` = :uid", [':uid'=>$uid]) > 0;
        case 'plan_mitigations': return getting_started_count($db, 'mitigations', "SELECT COUNT(*) FROM `mitigations`") > 0;
        case 'risk_review':      return getting_started_count($db, 'mgmt_reviews', "SELECT COUNT(*) FROM `mgmt_reviews` WHERE `reviewer` = :uid", [':uid'=>$uid]) > 0;
        case 'define_tests':     return getting_started_count($db, 'framework_control_tests', "SELECT COUNT(*) FROM `framework_control_tests`") > 0;
        case 'initiate_audits':  return getting_started_count($db, 'framework_control_test_audits', "SELECT COUNT(*) FROM `framework_control_test_audits`") > 0;
        case 'asset':            return getting_started_count($db, 'assets', "SELECT COUNT(*) FROM `assets`") > 0;
        default:                 return false;
    }
}

// Extra "is there work for this step right now" gate. Only risk_review uses it:
// show the teach-once review card only while an open risk actually awaits review
// (team-scoped). v1 approximates "a reviewable risk exists" with the team-scoped
// unreviewed-open count; an exact per-level match is a documented fast-follow.
function getting_started_step_visible($key, $db, $uid) {
    // Install the SCF: only downloadable once the instance is registered, and
    // only worth showing until the extra's files are present.
    if ($key === 'install_scf') return get_setting('registration_registered') == 1 && !is_extra_installed('complianceforgescf');
    // Activate the SCF: only shows once the extra is installed but not yet active.
    if ($key === 'activate_scf') return is_extra_installed('complianceforgescf') && !(function_exists('complianceforge_scf_extra') && complianceforge_scf_extra());
    // Enable applicable frameworks: only worth showing once the SCF is active.
    if ($key === 'enable_frameworks') return function_exists('complianceforge_scf_extra') && complianceforge_scf_extra();
    // Take a self-assessment: only once the SCF is active and its control data is
    // present (the same prerequisite the Self-Assessments page enforces).
    if ($key === 'self_assessment') return get_setting('registration_registered') == 1
        && function_exists('complianceforge_scf_extra') && complianceforge_scf_extra()
        && table_exists('scf_controls') && table_exists('scf_frameworks');
    // Show the teach-once review card only while an open risk actually awaits review.
    if ($key === 'risk_review') return get_unreviewed_open_risk_count() > 0;
    return true;
}

// The current user's dismissed step keys (empty if the table isn't present yet,
// so the widget works before the migration runs).
function getting_started_dismissed_keys($db, $uid) {
    if (!table_exists('getting_started_dismissed')) return [];
    $stmt = $db->prepare("SELECT `step_key` FROM `getting_started_dismissed` WHERE `user_id` = :uid");
    $stmt->execute([':uid'=>$uid]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

// Dismiss a step for a user (idempotent; the PK makes a repeat a no-op refresh).
function getting_started_dismiss($db, $uid, $step_key) {
    if (!table_exists('getting_started_dismissed')) return;
    $stmt = $db->prepare("
        INSERT INTO `getting_started_dismissed` (`user_id`, `step_key`)
        VALUES (:uid, :key)
        ON DUPLICATE KEY UPDATE `dismissed` = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':uid'=>$uid, ':key'=>$step_key]);
}

// Restore (un-dismiss) a step for a user; a no-op if it wasn't dismissed.
function getting_started_restore($db, $uid, $step_key) {
    if (!table_exists('getting_started_dismissed')) return;
    $stmt = $db->prepare("DELETE FROM `getting_started_dismissed` WHERE `user_id` = :uid AND `step_key` = :key");
    $stmt->execute([':uid'=>$uid, ':key'=>$step_key]);
}

// Assemble the widget state for the current user: the open (actionable) steps in
// area order, plus done/held counts for the progress bar. Filter order is
// gate → dismiss → visibility → completion; done steps fall off (counted, not shown).
function get_getting_started_state($uid = null) {
    $db = db_open();
    $uid = $uid ?? ($_SESSION['uid'] ?? 0);

    $dismissed = getting_started_dismissed_keys($db, $uid);
    $order = array_flip(getting_started_area_order());

    $open = [];
    $held = 0; $done = 0;
    foreach (getting_started_catalog() as $key => $def) {
        if (!getting_started_step_gated($def['gate'])) continue;                        // permission
        if (in_array($key, $dismissed, true)) continue;                                 // dismissed
        $is_done = getting_started_step_complete($key, $db, $uid);
        if (!$is_done && !getting_started_step_visible($key, $db, $uid)) continue;       // no work yet
        $held++;
        if ($is_done) { $done++; continue; }                                            // done → falls off
        $open[] = ['key'=>$key] + $def;
    }
    db_close($db);

    usort($open, function($a, $b) use ($order) {
        return ($order[$a['area']] ?? 99) <=> ($order[$b['area']] ?? 99);
    });

    return ['open'=>$open, 'held'=>$held, 'done'=>$done];
}

// Permission-aware "Explore" section links (icon chips) for the widget footer.
function getting_started_explore_links() {
    $links = [];
    if (check_permission('riskmanagement')) $links[] = ['label'=>'RiskManagement',  'url'=>'../management/index.php',  'icon'=>'fa-solid fa-triangle-exclamation'];
    if (check_permission('compliance'))     $links[] = ['label'=>'Compliance',      'url'=>'../compliance/index.php',  'icon'=>'fa-solid fa-clipboard-check'];
    if (check_permission('governance'))     $links[] = ['label'=>'Governance',      'url'=>'../governance/index.php',  'icon'=>'fa-solid fa-scale-balanced'];
    if (check_permission('asset'))          $links[] = ['label'=>'AssetManagement', 'url'=>'../assets/index.php',      'icon'=>'fa-solid fa-server'];
    return $links;
}

// Render the Getting Started widget HTML (echoed, matching the other widgets).
// The server renders every open card; the client caps the display to 3 with a
// "Show more" toggle and rotates the next card in when one is dismissed.
function render_getting_started_widget() {
    global $lang, $escaper;
    $L = function($k) use ($lang) { return $lang[$k] ?? $k; };

    $state = get_getting_started_state();
    $open = $state['open']; $held = $state['held']; $done = $state['done'];

    // All applicable steps complete → tell the client to remove + persist (decision 4).
    $complete_flag = ($held > 0 && empty($open)) ? " data-gs-complete='1'" : "";

    echo "<div class='sr-widget sr-gs' data-gs-widget" . $complete_flag . ">";

    // No standalone hide control: the widget is removed like any other tile via
    // Edit Layout (and re-added from the picker's "General" group), so there's a
    // single, recoverable removal path. It also self-removes once every applicable
    // step is complete.

    echo "<div class='sr-gs__head'>";
    echo   "<span class='sr-gs__ico'><i class='fa-solid fa-rocket'></i></span>";
    echo   "<span class='sr-gs__htext'>"
         . "<span class='sr-gs__title'>" . $escaper->escapeHtml($L('GettingStartedTitle')) . "</span>"
         . "<span class='sr-gs__sub'>" . $escaper->escapeHtml($L('GettingStartedSubtitle')) . "</span>"
         . "</span>";
    if ($held > 0) {
        $pct = (int)round($done / $held * 100);
        $count_txt = str_replace(['{done}', '{total}'], [$done, $held], $L('GSProgressCount'));
        echo "<span class='sr-gs__progress'>"
           . "<span class='sr-gs__count'>" . $escaper->escapeHtml($count_txt) . "</span>"
           . "<span class='sr-gs__bar'><i style='width:" . $pct . "%'></i></span>"
           . "</span>";
    }
    echo "</div>";

    if (!empty($open)) {
        echo "<div class='sr-gs__cards'>";
        $i = 0;
        foreach ($open as $card) {
            $is_next   = ($i === 0);
            $state_cls = $is_next ? ' sr-gs__card--next' : '';
            $eyebrow   = $is_next ? $L('GSNextUp') : $L('GSArea_' . $card['area']);
            $cta_cls   = $is_next ? 'sr-gs__cta--primary' : 'sr-gs__cta--ghost';
            echo "<div class='sr-gs__card" . $state_cls . "'>";
            echo   "<button type='button' class='sr-gs__x' data-gs-dismiss='" . $escaper->escapeHtmlAttr($card['key']) . "' title='" . $escaper->escapeHtmlAttr($L('GSDismissStep')) . "' aria-label='" . $escaper->escapeHtmlAttr($L('GSDismissStep')) . "'>&#10005;</button>";
            echo   "<div class='sr-gs__card-top'><span class='sr-gs__mark'></span><span class='sr-gs__eyebrow'>" . $escaper->escapeHtml($eyebrow) . "</span></div>";
            echo   "<div class='sr-gs__card-title'>" . $escaper->escapeHtml($L($card['title'])) . "</div>";
            echo   "<div class='sr-gs__card-desc'>" . $escaper->escapeHtml($L($card['desc'])) . "</div>";
            echo   "<div class='sr-gs__actions'>";
            echo     "<a class='sr-gs__cta " . $cta_cls . "' href='" . $escaper->escapeHtmlAttr($card['cta']) . "'>" . $escaper->escapeHtml($L($card['cta_label'])) . "</a>";
            echo     "<a class='sr-gs__doc' href='" . $escaper->escapeHtmlAttr($card['doc']) . "' target='_blank' rel='noopener'>" . $escaper->escapeHtml($L('LearnMore')) . " &#8599;</a>";
            echo   "</div>";
            echo "</div>";
            $i++;
        }
        echo "</div>";
        echo "<div class='sr-gs__more' data-gs-more></div>";
    } elseif ($held > 0) {
        echo "<div class='sr-gs__done'>" . $escaper->escapeHtml($L('GSAllSet')) . "</div>";
    }

    // A small link-chip: icon + label. $icon is a constant FontAwesome class,
    // $external adds new-tab attrs.
    $chip = function($url, $icon, $label, $external = false) use ($escaper) {
        $attrs = $external ? " target='_blank' rel='noopener'" : "";
        return "<a class='sr-gs__link' href='" . $escaper->escapeHtmlAttr($url) . "'" . $attrs . ">"
             . "<i class='" . $escaper->escapeHtmlAttr($icon) . "'></i>" . $escaper->escapeHtml($label) . "</a>";
    };

    echo "<div class='sr-gs__foot'>";
    $explore = getting_started_explore_links();
    if (!empty($explore)) {
        echo "<div class='sr-gs__foot-group'>";
        echo   "<span class='sr-gs__foot-label'>" . $escaper->escapeHtml($L('Explore')) . "</span>";
        echo   "<div class='sr-gs__foot-chips'>";
        foreach ($explore as $x) {
            echo $chip($x['url'], $x['icon'], $L($x['label']));
        }
        echo   "</div>";
        echo "</div>";
    }
    echo "<div class='sr-gs__foot-group'>";
    echo   "<span class='sr-gs__foot-label'>" . $escaper->escapeHtml($L('Learn')) . "</span>";
    echo   "<div class='sr-gs__foot-chips'>";
    echo     $chip('https://www.simplerisk.com/support/user-guide', 'fa-solid fa-book', $L('UserGuide'), true);
    if (is_admin()) {
        echo $chip('https://www.simplerisk.com/support/admin-guide', 'fa-solid fa-screwdriver-wrench', $L('AdminGuide'), true);
    }
    echo     $chip('https://www.simplerisk.com/support/video-walkthrough', 'fa-solid fa-circle-play', $L('GSWalkthrough'), true);
    echo   "</div>";
    echo "</div>";
    echo "</div>";

    echo "</div>";
}

// Top open risks by inherent score (team-separation aware) → list items with the
// risk's configured level as a solid severity pill.
function get_home_highest_risks_items($limit = 6)
{
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT rsk.id, rsk.subject, scoring.calculated_risk
        FROM risks rsk
        INNER JOIN risk_scoring scoring ON rsk.id = scoring.id
        {$sep_from}
        WHERE rsk.status != 'Closed'
        {$sep_where}
        GROUP BY rsk.id
        ORDER BY scoring.calculated_risk DESC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    // Configured level display name + colour.
    $levels = [];
    foreach (get_risk_levels() as $rl) {
        $levels[$rl['name']] = ['display' => $rl['display_name'], 'color' => $rl['color']];
    }

    $items = [];
    foreach ($rows as $r) {
        $level_name = get_risk_level_name($r['calculated_risk']);
        $has_level  = ($level_name !== '' && isset($levels[$level_name]));
        $items[] = [
            'name'  => $r['subject'],
            'href'  => '../management/view.php?id=' . convert_to_risk_id($r['id']),
            'pill'  => $has_level ? $levels[$level_name]['display'] : ($level_name !== '' ? $level_name : '—'),
            'band'  => 'level',
            'color' => $has_level && $levels[$level_name]['color'] !== '' ? $levels[$level_name]['color'] : '#a1aab2',
        ];
    }
    return $items;
}

// Open risks whose latest management review is past its next-review date
// (team-separation aware) → list items with a days-overdue danger pill.
function get_home_pastdue_reviews_items($limit = 6)
{
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT rsk.id, rsk.subject, mr.next_review
        FROM risks rsk
        INNER JOIN mgmt_reviews mr ON mr.id = (
            SELECT MAX(mr2.id) FROM mgmt_reviews mr2 WHERE mr2.risk_id = rsk.id
        )
        {$sep_from}
        WHERE rsk.status != 'Closed'
        AND mr.next_review IS NOT NULL
        AND mr.next_review != '0000-00-00'
        AND mr.next_review < CURDATE()
        {$sep_where}
        ORDER BY mr.next_review ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        $days = (int) floor((time() - strtotime($r['next_review'] . ' 00:00:00')) / 86400);
        $items[] = [
            'name' => $r['subject'],
            'href' => '../management/view.php?id=' . convert_to_risk_id($r['id']) . '&type=2&action=editreview#review',
            'pill' => $days . 'd',
            'band' => 'danger',
        ];
    }
    return $items;
}

// Open risks with no management review yet → list items with an age (days since
// submission) warn pill, oldest first (longest-waiting). Team-scoped. Mirrors the
// "unreviewed" definition in get_unreviewed_open_risk_count() (mgmt_review = 0).
// Sibling of get_home_pastdue_reviews_items(): past-due = overdue reviews (red),
// unreviewed = never-reviewed (amber).
function get_home_unreviewed_items($limit = 6)
{
    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT rsk.id, rsk.subject, rsk.submission_date
        FROM risks rsk
        {$sep_from}
        WHERE rsk.status != 'Closed'
        AND rsk.mgmt_review = 0
        {$sep_where}
        ORDER BY rsk.submission_date ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        $days = (int) floor((time() - strtotime($r['submission_date'])) / 86400);
        $items[] = [
            'name' => $r['subject'],
            'href' => '../management/view.php?id=' . convert_to_risk_id($r['id']) . '&type=2&action=editreview#review',
            'pill' => $days . 'd',
            'band' => 'warn',
        ];
    }
    return $items;
}

// Upcoming/overdue control TESTS (definitions), soonest first, with their
// initiation state so each row is actionable:
//   - an audit is already open for the test  → link to it (go perform it)
//   - the test auto-initiates                → an "Auto" badge (the cron starts it)
//   - manual, not started, user may initiate → a one-click "Start" action
//     (initiates the audit and opens it; gated on the initiate_audits permission)
// Overdue definitions get a red "days late" pill; upcoming ones an amber pill.
// Framework-scoped; team-scoped to the tests the user may access when the Team
// Separation extra is active.
function get_home_upcoming_tests_items($limit = 6, $framework_ids = null)
{
    global $lang;

    // [] = explicit empty framework selection → nothing in scope.
    if ($framework_ids !== null && empty($framework_ids)) {
        return [];
    }

    // Optional framework scope: only tests whose control maps to one of the
    // selected frameworks. Null (e.g. the Home dashboard, no selector) = all.
    $fw_join = $fw_clause = '';
    $params = [];
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_join = "INNER JOIN framework_control_mappings fcm ON fcm.control_id = fc.id
                    INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1";
        $fw_clause = "AND f.value IN ({$ph})";
        $params = array_values($framework_ids);
    }

    // Team Separation (optional): restrict to the tests the user may access.
    $team_clause = '';
    if (team_separation_extra()) {
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        if (!should_skip_test_and_audit_permission_check()) {
            $access = get_compliance_separation_access_info();
            $accessible = $access['framework_control_tests'] ?? [];
            if (empty($accessible)) {
                return [];
            }
            $tph = implode(',', array_fill(0, count($accessible), '?'));
            $team_clause = "AND t.id IN ({$tph})";
            $params = array_merge($params, array_map('intval', $accessible));
        }
    }

    // A control test status matching this setting means the audit is closed; any
    // other status is an in-progress ("open") audit. Cast to int — it's a trusted
    // config value inlined into the correlated subquery.
    $closed_status = (int) get_setting('closed_audit_status');

    $db = db_open();
    // No CURDATE() lower bound — include overdue tests. ORDER BY next_date ASC
    // puts the most overdue first, so the urgent ones win the limited slots.
    $stmt = $db->prepare("
        SELECT DISTINCT t.id AS test_id, t.name, t.next_date,
               fc.short_name AS control_short_name,
               (t.audit_initiation_offset IS NOT NULL) AS auto_initiate,
               (SELECT ta.id FROM framework_control_test_audits ta
                  WHERE ta.test_id = t.id AND ta.status <> {$closed_status}
                  ORDER BY ta.id DESC LIMIT 1) AS open_audit_id
        FROM framework_control_tests t
        INNER JOIN framework_controls fc ON t.framework_control_id = fc.id AND fc.deleted = 0
        {$fw_join}
        WHERE t.next_date IS NOT NULL
        AND t.next_date != '0000-00-00'
        {$fw_clause}
        {$team_clause}
        ORDER BY t.next_date ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    // The one-click Start action is only offered to users who may initiate audits.
    $can_initiate = (($_SESSION['initiate_audits'] ?? 0) == 1);

    $items = [];
    foreach ($rows as $r) {
        $days = (int) floor((strtotime($r['next_date'] . ' 00:00:00') - time()) / 86400);
        $item = [
            'name' => (string) ($r['name'] ?? ''),
            // The control the test belongs to, shown as a muted sub-label so the
            // row says which control (e.g. "CRY-05: …") the test covers.
            'context' => (string) ($r['control_short_name'] ?? ''),
            'pill' => ($days < 0 ? abs($days) : $days) . 'd',
            'band' => $days < 0 ? 'danger' : 'warn',
            // The name always links to the Define Tests page to edit the test entry
            // itself (e.g. to set it to auto-initiate). The action icon handles
            // running the test / going to its audit, so these never collide. The
            // test_id is carried now for forward compatibility: Define Tests
            // currently ignores it, but is slated to focus/open that test by id.
            'href' => '../compliance/index.php?test_id=' . (int) $r['test_id'],
        ];

        if (!empty($r['open_audit_id'])) {
            // An audit already exists — the icon goes straight to it (never starts
            // a duplicate). Green "Go to Test"; viewing needs no initiate permission.
            $item['action'] = [
                'type'     => 'run',
                'audit_id' => (int) $r['open_audit_id'],
                'title'    => $lang['GoToTest'] ?? 'Go to Test',
            ];
        } elseif (!empty($r['auto_initiate'])) {
            // The cron initiates this automatically near its due date — an
            // informational (non-clickable) icon, no manual action.
            $item['action'] = [
                'type'  => 'auto',
                'title' => $lang['TestStartsAutomatically'] ?? 'Test Starts Automatically',
            ];
        } elseif ($can_initiate) {
            // Manual and not yet started — one-click run icon (initiate + open),
            // only for users who may initiate audits.
            $item['action'] = [
                'type'    => 'run',
                'test_id' => (int) $r['test_id'],
                'title'   => $lang['StartTheTest'] ?? 'Start the Test',
            ];
        }
        $items[] = $item;
    }
    return $items;
}

// Controls whose most recent test result is a failure → list items with a Fail
// danger pill.
function get_home_recent_failures_items($limit = 6, $framework_ids = null)
{
    global $lang;

    // [] = explicit empty framework selection → nothing in scope.
    if ($framework_ids !== null && empty($framework_ids)) {
        return [];
    }

    // Optional framework scope: only controls mapped to one of the selected
    // frameworks. Null (e.g. the Home dashboard, no selector) = all.
    $fw_join = $fw_clause = '';
    $params = [];
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_join = "INNER JOIN framework_control_mappings fcm ON fcm.control_id = fc.id
                    INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1";
        $fw_clause = "AND f.value IN ({$ph})";
        $params = array_values($framework_ids);
    }

    // Team Separation (optional): only audits the user is allowed to access. When
    // active we over-fetch and filter in PHP so the ACL doesn't shrink the list
    // below the requested count. Mirrors home_risk_separation_sql()'s load pattern.
    $separation = team_separation_extra();
    $skip_separation = true;
    if ($separation) {
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $skip_separation = should_skip_test_and_audit_permission_check();
    }
    $fetch_limit = ($separation && !$skip_separation) ? max(100, (int) $limit) : (int) $limit;

    $db = db_open();
    // Carry the audit id + status of the latest failing result so we can deep-link
    // to that specific audit (view_test.php when closed, testing.php when open).
    $stmt = $db->prepare("
        SELECT fc.id, fc.short_name, latest.submission_date, latest.audit_id, latest.audit_status, latest.audit_approval_state
        FROM framework_controls fc
        INNER JOIN (
            SELECT acm.framework_control_id, ta.id AS audit_id, ta.status AS audit_status,
                   ta.approval_state AS audit_approval_state, tr.test_result, tr.submission_date
            FROM audit_control_map acm
            INNER JOIN framework_control_test_audits ta ON ta.id = acm.audit_id
            INNER JOIN framework_control_test_results tr ON ta.id = tr.test_audit_id
            WHERE tr.submission_date = (
                SELECT MAX(tr2.submission_date)
                FROM audit_control_map acm2
                INNER JOIN framework_control_test_audits ta2 ON ta2.id = acm2.audit_id
                INNER JOIN framework_control_test_results tr2 ON ta2.id = tr2.test_audit_id
                WHERE acm2.framework_control_id = acm.framework_control_id
            )
        ) latest ON fc.id = latest.framework_control_id
        {$fw_join}
        WHERE fc.deleted = 0 AND latest.test_result = 'Fail'
        {$fw_clause}
        GROUP BY fc.id
        ORDER BY latest.submission_date DESC
        LIMIT " . $fetch_limit . "
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    // A closed audit is one whose status matches the configured closed-audit
    // status; those open in the read-only past view, active ones in the editor.
    $closed_status = (string) get_setting('closed_audit_status');
    $uid = (int) ($_SESSION['uid'] ?? 0);

    $items = [];
    foreach ($rows as $r) {
        $audit_id = (int) $r['audit_id'];

        // Hide audits the user isn't allowed to see (the deep-link target would
        // redirect them anyway, but don't leak the control name/failure either).
        if ($separation && !$skip_separation && !is_user_allowed_to_access($uid, $audit_id, 'audit')) {
            continue;
        }

        // Which page an audit deep-links to -- read-only view_test.php only when
        // it is BOTH closed and settled -- is one rule with three call sites, so
        // it lives in one pure function rather than being restated here.
        $page = audit_history_link_page($r['audit_status'], $r['audit_approval_state'] ?? 'none', $closed_status);
        $items[] = [
            'name' => $r['short_name'] ?? '',
            'href' => '../compliance/' . $page . '?id=' . $audit_id,
            'pill' => $lang['Fail'] ?? 'Fail',
            'band' => 'danger',
        ];

        if (count($items) >= (int) $limit) {
            break;
        }
    }
    return $items;
}

// Controls currently failing (framework_controls.control_status = 0) → deep-linked
// list items straight to the control in Governance. Note: unlike
// get_home_recent_failures_items() above, controls are NOT team-scoped —
// is_user_allowed_to_access() only supports 'test'/'audit' $type values, so
// there is no per-row Team Separation filter here; the list reflects all
// failing controls in the (optional) framework scope.
function get_governance_failing_controls_items($limit = 6, $framework_ids = null)
{
    global $lang;

    // [] = explicit empty framework selection → nothing in scope.
    if ($framework_ids !== null && empty($framework_ids)) {
        return [];
    }

    // Optional framework scope: only controls mapped to one of the selected
    // frameworks. Null = all.
    $fw_join = $fw_clause = '';
    $params = [];
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_join = "INNER JOIN framework_control_mappings fcm ON fcm.control_id = fc.id
                    INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1";
        $fw_clause = "AND f.value IN ({$ph})";
        $params = array_values($framework_ids);
    }

    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT fc.id, fc.short_name
        FROM framework_controls fc
        {$fw_join}
        WHERE fc.deleted = 0 AND fc.control_status = 0
        {$fw_clause}
        ORDER BY fc.short_name ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        $cid = (int) $r['id'];
        $items[] = [
            'name' => $r['short_name'] ?? '',
            'href' => '../governance/index.php?control_id=' . $cid,
            'pill' => $lang['Fail'] ?? 'Fail',
            'band' => 'danger',
        ];
    }
    return $items;
}

// Documents whose next review date is overdue or approaching → list items with a
// days pill (danger when overdue or within a week, warn otherwise).
// $framework_ids: null = All Frameworks (unscoped, includes policies with no
// framework link), [] = none selected (short-circuits to [] before any DB
// call), [id,...] = only policies linked to one of those frameworks via
// document_framework_mappings.
function get_home_policies_review_items($limit = 6, $framework_ids = null)
{
    if ($framework_ids !== null && empty($framework_ids)) { return []; }
    $db = db_open();
    $fw_join = '';
    $params = [];
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_join = "INNER JOIN `document_framework_mappings` dfm ON dfm.document_id = documents.id AND dfm.framework_id IN ({$ph})";
        $params = array_values($framework_ids);
    }
    $stmt = $db->prepare("
        SELECT DISTINCT documents.id, documents.document_name, documents.next_review_date
        FROM documents
        {$fw_join}
        WHERE documents.next_review_date IS NOT NULL
        AND documents.next_review_date != '0000-00-00'
        AND documents.next_review_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
        ORDER BY documents.next_review_date ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        $days = (int) floor((strtotime($r['next_review_date'] . ' 00:00:00') - time()) / 86400);
        $items[] = [
            'name' => $r['document_name'],
            'href' => '../governance/documentation.php?document_id=' . (int) $r['id'],
            'pill' => abs($days) . 'd',
            'band' => $days <= 7 ? 'danger' : 'warn',
        ];
    }
    return $items;
}

// Exceptions whose next review (expiry) date is overdue or approaching → list
// items with a days pill (danger when overdue or within a week, warn otherwise).
// $framework_ids: null = All Frameworks (unscoped, includes exceptions linked
// to no framework), [] = none selected (short-circuits to [] before any DB
// call), [id,...] = only exceptions linked to one of those frameworks via any
// of the three exception→framework paths (see get_open_exceptions_count()).
function get_home_expiring_exceptions_items($limit = 6, $framework_ids = null)
{
    if ($framework_ids !== null && empty($framework_ids)) { return []; }
    $db = db_open();
    $fw_clause = '';
    $params = [];
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND (
            de.framework_id IN ({$ph})
            OR EXISTS (SELECT 1 FROM framework_control_mappings fcm WHERE fcm.control_id = de.control_framework_id AND fcm.framework IN ({$ph}))
            OR EXISTS (SELECT 1 FROM document_framework_mappings dfm WHERE dfm.document_id = de.policy_document_id AND dfm.framework_id IN ({$ph}))
        )";
        $params = array_merge(array_values($framework_ids), array_values($framework_ids), array_values($framework_ids));
    }
    $stmt = $db->prepare("
        SELECT DISTINCT de.value, de.name, de.next_review_date
        FROM document_exceptions de
        WHERE de.next_review_date IS NOT NULL
        AND de.next_review_date != '0000-00-00'
        AND de.next_review_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
        {$fw_clause}
        ORDER BY de.next_review_date ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        $days = (int) floor((strtotime($r['next_review_date'] . ' 00:00:00') - time()) / 86400);
        $items[] = [
            'name' => $r['name'],
            'href' => '../governance/document_exceptions.php?exception_id=' . (int) $r['value'],
            'pill' => abs($days) . 'd',
            'band' => $days <= 7 ? 'danger' : 'warn',
        ];
    }
    return $items;
}

/*****************************************
 * FUNCTION: RENDER HOME RISK-BY-LEVEL   *
 *****************************************/
// Home dashboard widget: open (non-Closed) risks grouped by risk level as a bar
// chart, each bar in that level's configured colour (risk_levels.color).
// "Insignificant" — the implicit bucket below the Low threshold — has no
// risk_levels row, so it gets a green default. Wrapped in the .sr-widget frame,
// like the What's-Next widget.
function render_home_risk_by_level_chart()
{
    global $lang, $escaper;

    // Most-severe first. Names MUST match get_risk_count_of_risk_level()'s cases.
    $levels = ['Very High', 'High', 'Medium', 'Low', 'Insignificant'];

    // Configured display name + colour per level (risk_levels); Insignificant absent.
    $configured = [];
    foreach (get_risk_levels() as $rl) {
        $configured[$rl['name']] = ['display' => $rl['display_name'], 'color' => $rl['color']];
    }
    // Fallbacks (design-system severity scale) when a level isn't configured.
    $fallback_color = [
        'Very High' => '#c0392b', 'High' => '#ed3139', 'Medium' => '#fb8c00',
        'Low' => '#f0c419', 'Insignificant' => '#51A351',
    ];

    $labels = [];
    $counts = [];
    $colors = [];
    $total  = 0;
    foreach ($levels as $name) {
        $labels[] = $configured[$name]['display'] ?? ($lang[str_replace(' ', '', $name)] ?? $name);
        $count    = (int) get_risk_count_of_risk_level($name);
        $counts[] = $count;
        $total   += $count;
        // escapeCssColor allow-lists hex / CSS keyword, else 'transparent'
        $colors[] = $escaper->escapeCssColor($configured[$name]['color'] ?? $fallback_color[$name]);
    }

    echo "<div class='sr-widget'>";
    echo   "<div class='sr-widget__head'>"
         . "<span class='sr-widget__title'>" . $escaper->escapeHtml($lang['HomeChartRiskByLevel']) . "</span>"
         . "<span class='sr-domain sr-widget__domain'>" . $escaper->escapeHtml($lang['Risk']) . "</span>"
         . "</div>";
    echo   "<div class='sr-widget__body sr-widget__body--chart'>";

    if ($total === 0) {
        echo "<div class='sr-whatsnext sr-whatsnext--empty'>"
           . "<span class='sr-whatsnext__empty-title'>" . $escaper->escapeHtml($lang['NoDataAvailable']) . "</span>"
           . "</div></div></div>";
        return;
    }

    echo "<canvas id='home_risk_by_level'></canvas>";
    echo   "</div>";
    echo "</div>";

    // Data is server-encoded via json_encode (safe JS embedding); colours are
    // already allow-listed by escapeCssColor above.
    $labels_json = json_encode(array_values($labels));
    $counts_json = json_encode($counts); // already a sequential list (built via $counts[] = …)
    $colors_json = json_encode(array_values($colors));

    echo "
        <script>
            $(function() {
                var el = document.getElementById('home_risk_by_level');
                if (!el || typeof Chart === 'undefined') return;
                new Chart(el.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: {$labels_json},
                        datasets: [{
                            data: {$counts_json},
                            backgroundColor: {$colors_json},
                            borderRadius: 4,
                            maxBarThickness: 48
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                displayColors: false,
                                padding: 10,
                                backgroundColor: 'rgba(58,58,58,.92)',
                                titleFont: { family: 'Nunito Sans', weight: '700' },
                                bodyFont: { family: 'Nunito Sans' }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: '#6c757d', font: { family: 'Nunito Sans', size: 12, weight: '600' } }
                            },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                ticks: { precision: 0, color: '#a1aab2', font: { family: 'Nunito Sans', size: 11 } },
                                grid: { color: 'rgba(0,0,0,.05)' }
                            }
                        }
                    }
                });
            });
        </script>
    ";
}

?>