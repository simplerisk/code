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

    // Escape the title for javascript display
    $title = str_replace("'", "\'", $title);

    // If the array is empty
    if (empty($array)) {

        $labels = [];
        $data = [];

    // Otherwise
    } else {

        // Create the individual arrays
        foreach ($array as $row) {

            // Replace any single quote characters in the label
            $label = str_replace("'", "\'", $row['label']);
            $labels[] = $label;
            $data[] = js_string_escape($row['data']);

        }
    }

    // If the labels and data are not empty
    if (!empty($labels) && !empty($data)) {

        // Convert the values to CSV strings
        $labels = "'" . implode("','", $labels) . "'";
        $data = "'" . implode("','", $data) . "'";

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

        echo "
            <canvas id='{$element_id}'></canvas>
            <div class='save_as_image'>
                <i class='far fa-save' id='{$element_id}_save'></i>
            </div>
            <script>
                $(function() {
                    data = {
                        labels: [{$labels}],
                        datasets: [{
                            data: [{$data}],
                            {$backgroundColor}
                        }],
                    };
                    config = {
                        type: 'pie',
                        data: data,
                        options: {
                            plugins: {
                                title: {
                                    display: true,
                                    text: '{$title}',
                                },
                            },
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
                <strong class='mb-3'>{$title}</strong>
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
    // Escape the title for javascript display
    $title = js_string_escape($title);

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
                    $slice_labels[] = js_string_escape($row['label']);
                    $data[] = js_string_escape($row['data']);
                    $colors[] = $row['color'];
                }

                // If the data is not empty
                if (!empty($data))
                {
                    // Convert the values to CSV strings
                    $slice_labels = "'" . implode("','", $slice_labels) . "'";
                    $data = "'" . implode("','", $data) . "'";
                    $color = "'" . implode("','", $colors) . "'";

                    // Add the data and colors
                    $dataset_json = "{\n";
                    $dataset_json .= "data: [{$data}],\n";
                    $dataset_json .= "backgroundColor: [{$color}],\n";
                    $dataset_json .= "}\n";
                }

                // Add the json to an array of dataset json
                $datasets_json_array[] = $dataset_json;

                // If this is the inside pie
                if ($index === "inside")
                {
                    echo "
                    var insidePieLabels = [{$slice_labels}];
                    ";
                }
                // If this is the outside pie
                else if ($index === "outside")
                {
                    echo "
                    var outsidePieLabels = [{$slice_labels}];
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
                                    text: '{$title}',
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
                <strong class='mb-3'>{$title}</strong>
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

    // Escape the title for javascript display
    $title = str_replace("'", "\'", $title);

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

        // Convert the labels to a CSV string
        $labels = "'" . implode("','", $labels) . "'";

        // Begin the data
        echo "
                    data = {
                        labels: [{$labels}],
                        datasets: [
        ";

        // For each of the datasets provided
        foreach ($datasets as $dataset) {

            // Get the values for the dataset
            $label = (isset($dataset['label']) ? "label: '{$dataset['label']}'," : "");
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
                                    text: '{$title}',
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
                                        text: '{$x_axis_title}'
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
                <strong class='mb-3'>{$title}</strong>
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
function create_chartjs_bar_code($title = "", $element_id = "", $labels = [], $datasets = [], $x_axis_title = null, $y_axis_title = null, $width = null, $height = null)
{
    // Escape the title for javascript display
    $title = str_replace("'", "\'", $title);

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

        // Convert the labels to a CSV string
        $labels = "'" . implode("','", $labels) . "'";

        // Begin the data
        echo "
                    data = {
                        labels: [{$labels}],
                        datasets: [
        ";

        // For each of the datasets provided
        foreach ($datasets as $dataset)
        {
            // Get the values for the dataset
            $label = $dataset['label'];
            $data = implode(",", $dataset['data']);

            echo "
                            {
                                label: '{$label}',
                                data: [{$data}],
                                barThickness: 5,
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
                            plugins: {
                                title: {
                                    display: true,
                                    text: '{$title}',
                                },
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
                                        text: '{$x_axis_title}'
                                    }
                                },
                                y: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: '{$y_axis_title}'
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
                <strong class='mb-3'>{$title}</strong>
                <strong>No Data Available</strong>
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

    // Escape the title for javascript display
    $title = str_replace("'", "\'", $title);

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
            echo "
                        {$maturity_level['value']}: '{$maturity_level['name']}', 
            ";
        }
        echo "
                    }
        ";

        // Convert the labels to a CSV string
        $labels = "'" . implode("','", $labels) . "'";

        // Begin the data
        echo "
                    data = {
                        labels: [{$labels}],
                        datasets: [
        ";

        // For each of the datasets provided
        foreach ($datasets as $dataset) {
            // Get the values for the dataset
            $label = $dataset['label'];
            $data = implode(",", $dataset['data']);

            echo "
                            {
                                label: '{$label}',
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
                                    text: '{$title}',
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
                <strong class='mb-3'>{$title}</strong>
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

    // Escape the title for javascript display
    $title = str_replace("'", "\'", $title);

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

            // Add the subjects to the subjects array as a string
            $subjects[] = "['" . implode("','", $dataset['subjects']) . "']";

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

        // Create javascript variables for the extra data
        echo "
                    var scores = ['" . implode("','", $scores) . "'];
                    var colors = ['" . implode("','", $colors) . "'];
                    var counts = [" . implode(",", $counts) . "];
                    var ids = [" . implode(",", $ids) . "];
                    var subjects = [" . implode(",", $subjects) . "];
        ";

        // Get the likelihood options
        $likelihoods = get_options_from_table("likelihood");
        foreach ($likelihoods as $key=>$likelihood) {
            // Escape single quotes
            $likelihoods[$key] = str_replace("'", "\'", $likelihood['name']);
        }
        echo "
                    var likelihoods = ['" . implode("','", $likelihoods) . "'];
        ";

        // Get the impact options
        $impacts = get_options_from_table("impact");
        foreach ($impacts as $key=>$impact) {
            // Escape single quotes
            $impacts[$key] = str_replace("'", "\'", $impact['name']);
        }
        echo "
                    var impacts = ['" . implode("','", $impacts) . "'];

                    config = {
                        type: 'bubble',
                        data: data,
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: '{$title}',
                                },
                                legend: false,
                                {$tooltip}
                            },
                            scales: {
                                x: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: '{$x_axis_title}'
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
                                        text: '{$y_axis_title}'
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
                <strong class='mb-3'>{$title}</strong>
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

    // Create the y axis code
    $y_axis_code = "
    y: {
        display: true,
            title: {
                display: true,
                text: '{$y_axis_title}'
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
                // Get the label and url
                $label = str_replace("'", "\'", $row['label']);
                $url = $row['url'];

                // Create the case statement
                $url_switch_code .= "  case '{$label}':\n";

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
    $x_axis_title = $escaper->escapeHtml($lang['Date']);
    $y_axis_title = $escaper->escapeHtml($lang['Count']);
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
                $array[$index]['color'] = '#FF0000';
                $array[$index]['url'] = 'dynamic_risk_report.php?status=2&group=2&sort=0';
                break;
            case "Unplanned":
                $array[$index]['color'] = '#66CC00';
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
                $array[$index]['color'] = '#FF0000';
                $array[$index]['url'] = 'dynamic_risk_report.php?status=2&group=2&sort=0';
                break;
            case "Unreviewed":
                $array[$index]['color'] = '#66CC00';
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
                $array[$index]['color'] = '#FF0000';
                $array[$index]['url'] = 'dynamic_risk_report.php?status=2&group=2&sort=0';
                break;
            case "Closed":
                $array[$index]['color'] = '#66CC00';
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
                    bSort: true,
                    orderCellsTop: true,
                    ajax: {
                        url: BASE_URL + '/api/reports/my_open_risk',
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
                            <td align='left' width='50px'><a class='open-in-new-tab' href='../management/view.php?id={$escaper->escapeHtml(convert_id($risk_id))}'>{$escaper->escapeHtml(convert_id($risk_id))}</a></td>
                            <td align='left' width='150px'>{$escaper->escapeHtml($status)}</td>
                            <td align='left' width='300px'>{$escaper->escapeHtml($subject)}</td>
                            <td align='center' class='risk-cell' bgcolor='{$escaper->escapeHtml($color)}' width='100px'>
                                <div class='risk-cell-holder'>{$escaper->escapeHtml($calculated_risk)}<span class='risk-color' style='background-color:{$color}'></span></div>
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
                    bSort: true,
                    orderCellsTop: true,
                    createdRow: function(row, data, index){
                        var background = $('.background-class', $(row)).data('background');
                        $(row).find('td').addClass(background)
                    },
                    order: [[3, 'DESC']],
                    ajax: {
                        url: BASE_URL + '/api/reports/high_risk?score_used={$score_used}',
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
                    bSort: true,
                    orderCellsTop: true,
                    ajax: {
                        url: BASE_URL + '/api/reports/recent_commented_risk',
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
                            <a class='open-in-new-tab' target='_blank' href='../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "'>" . $escaper->escapeHtml(convert_id($risk_id)) . "</a>
                        </td>
                        <td align='left' width='150px'>" . $escaper->escapeHtml($status) . "</td>
                        <td align='left' width='300px'>" . $escaper->escapeHtml($subject) . "</td>
                        <td align='left' width='200px'>" . $escaper->escapeHtml($risk_location) . "</td>
                        <td align='left' width='200px'>" . $escaper->escapeHtml($risk_teams) . "</td>
                        <td align='center' class='risk-cell' bgcolor='" . $escaper->escapeHtml($color1) . "' width='100px'>
                            <div class='risk-cell-holder'>" . 
                                $escaper->escapeHtml($calculated_risk) . "<span class='risk-color' style='background-color:" . $escaper->escapeHtml($color1) . "'></span>
                            </div>
                        </td>
                        <td align='center' class='risk-cell' bgcolor='" . $escaper->escapeHtml($color2) . "' width='100px'>
                            <div class='risk-cell-holder'>" . 
                                $escaper->escapeHtml($residual_risk) . "<span class='risk-color' style='background-color:" . $escaper->escapeHtml($color2) . "'></span>
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
                            <th style='background-color: " . $escaper->escapeHtml($color) . "' colspan='9'>
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
                            <th style='background-color: " .$escaper->escapeHtml($color). "' colspan='9'>
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
                            <th style='background-color:" . $escaper->escapeHtml($color) . "' bgcolor='" . $escaper->escapeHtml($color) . "' colspan='7'>
                                <center>
                                    <font color='#000000'>
                                        " . $escaper->escapeHtml($lang['RiskId']) . ":&nbsp;&nbsp;<a class='open-in-new-tab' target='_blank' href='../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "' style='color:#000000'>" . $escaper->escapeHtml(convert_id($risk_id)) . "</a>
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
                            <td style='background-color:" . $escaper->escapeHtml($color) . "' bgcolor='" . $escaper->escapeHtml($color) . "' colspan='7'></td>
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
                <span class='risk-color1' style='width:20px; height: 20px; position: relative; display:block; float:left; border: 1px solid; background-color:{$level['color']}'></span>
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
        if (count($comments) > 0) {
            foreach ($comments as $comment) {
                $details .= "
                            <li>" . format_date($comment['date']) . " [ {$comment['name']} ] : " . $escaper->purifyHtml(try_decrypt($comment['comment'])) . "</li>
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
        echo "
                    <td style='background-color:{$escaper->escapeHtml($color)}'></td>
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
                        $group_values_including_empty = str_getcsv($initial_group_value);
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
                        case "team";
                        case "technology";
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
                        
                        // Display the table header
                        echo "
        <table data-group='{$escaper->escapeHtml($group_value_from_db)}' class='table risk-datatable table-bordered table-striped table-condensed  table-margin-top' style='width: 100%'>
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
            foreach(str_getcsv($row['risk_tags']) as $tag) {
                $tags .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin: 1px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
            }
        }

        $data_row = [];

        foreach ($display_columns as $column) {
            if(stripos($column, "custom_field_") === false){
                switch ($column) {
                    default:
                        if(array_key_exists($column, $row)) {
                            $data_row[] = $escaper->escapeHtml($row[$column]);
                        } else {
                            $data_row[] = "";
                        }
                        break;
                    case 'id':
                        $data_row[] = "<a class='text-info' href=\"../management/view.php?id=" . $escaper->escapeHtml($row['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($row['id'])."</a>";
                        break;
                    case 'risk_status':
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
                        $data_row[] = "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($row[$column]) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>";
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

    echo "
        <table class='table table-hover border-bottom border-top mb-0'>
            <thead>
                <tr bgcolor='white'>
                    <th>&nbsp;</th>
    ";

    // For each of the past 12 months
    for ($i = 12; $i >= 0; $i--) {

        // Get the month
        $month = date('Y M', strtotime("first day of -$i month"));

        echo "
                    <th align='center' width='50px'>{$escaper->escapeHtml($month)}</th>
        ";

    }

    echo "
                </tr>
            </thead>
            <tbody>
                <tr bgcolor='white'>
                    <td align='center'>{$escaper->escapeHtml($lang['OpenedRisks'])}</td>
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
                    <td align='center' width='50px'>{$escaper->escapeHtml($open[$i])}</td>
        ";
    }

    echo "
                </tr>
                <tr bgcolor='white'>
                    <td align='center'>{$escaper->escapeHtml($lang['ClosedRisks'])}</td>
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
                    <td align='center' width='50px'>{$escaper->escapeHtml($close[$i])}</td>
        ";

    }

    echo "
                </tr>
                <tr bgcolor='white'>
                    <td align='center'>{$escaper->escapeHtml($lang['RiskTrend'])}</td>
    ";

    // For each of the past 12 months
    for ($i = 12; $i >= 0; $i--) {

        // Subtract the open number from the closed number
        $total[$i] = $open[$i] - $close[$i];

        // If the total is positive
        if ($total[$i] > 0) {

            // Display it in red
            $total_string = "<font color='red'>+{$total[$i]}</font>";

        // If the total is negative
        } else if ($total[$i] < 0) {

            // Display it in green
            $total_string = "<font color='green'>{$total[$i]}</font>";

        // Otherwise the total is 0
        } else {
            $total_string = $total[$i];
        }

        echo "
                    <td align='center' width='50px'>{$total_string}</td>
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
                <tr bgcolor='white'>
                    <td align='center'>{$escaper->escapeHtml($lang['TotalOpenRisks'])}</td>
    ";

    // For each of the past 12 months
    for ($i = 0; $i <= 12; $i++) {

        // Get the total number of risks
        $total = $total_open_risks[$i];

        echo "
                    <td align='center' width='50px'>{$escaper->escapeHtml($total)}</td>
        ";

    }

    echo "
                </tr>
            </tbody>
        </table>
    ";

}

/*************************************
 * FUNCTION: RETURN REISKS QUERY SQL *
 *************************************/
function risks_query_select($column_filters=[])
{
    global $lang;

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
            CONCAT('\$', s.min_value, ' to \$', s.max_value),
            CONCAT('\$', s.min_value, ' to \$', s.max_value, '(', s.valuation_level_name, ')')
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
            CONCAT('\$', s.min_value, ' to \$', s.max_value, '{$delimiter}', s.min_value, '-', s.max_value),
            CONCAT('\$', s.min_value, ' to \$', s.max_value, '(', s.valuation_level_name, ')', '{$delimiter}', s.min_value, '-', s.max_value)
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
            LEFT JOIN threat_catalog tc ON FIND_IN_SET(tc.id, a.threat_catalog_mapping) > 0
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
function make_full_risks_sql($query_type, $status, $sort, $group, $column_filters=[], &$group_value_from_db="", &$custom_query="", &$bind_params=[], $having_query="", $orderColumnName="", $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[])
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
        $separation_query = get_user_teams_query("a", false, true);

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
            LEFT JOIN `risk_catalog` rc ON FIND_IN_SET(`rc`.`id`, `rsk`.`risk_catalog_mapping`)
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
            LEFT JOIN `risks` rsk ON FIND_IN_SET(`rc`.`id`, `rsk`.`risk_catalog_mapping`)
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
function get_risks_only_dynamic($need_total_count, $status, $sort, $group, $column_filters, &$rowCount, $start=0, $length=10, $group_value_from_db="", $custom_query="", $bind_params=[], $orderColumnName=null, $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[])
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
                    case "submitted_by":
                        $wheres[] = " FIND_IN_SET(a.{$name}, :{$bind_param_name}) ";
                        if($empty_filter) $column_filter .= ",0";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "reviewer":
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
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(tc.id, :{$bind_param_name}) OR tc.id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(tc.id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;

                    /*
                     * Had to solve the filtering for the Risk Category fields this way as they're trying to find multiple IDs in a list of IDs.
                     * Probably can be reworked once the risk's 'risk_catalog_mapping' field will be replaced by a junction table.
                     **/
                    case "risk_mapping":
                    case "risk_mapping_risk":
                        $wheres[] = "(" . ($empty_filter ? "`a`.`risk_catalog_mapping` IS NULL OR `a`.`risk_catalog_mapping` = '' OR " : "") . "
                            (
                                SELECT
                                    COUNT(5)
                                FROM `risk_catalog` rc
                                WHERE
                                    `rc`.`id` IN (" . implode(',', array_map('intval', explode(',', $column_filter))) . ")
                                    AND FIND_IN_SET(`rc`.`id`, `a`.`risk_catalog_mapping`)
                            ) > 0)";
                    break;

                    case "risk_mapping_risk_grouping":
                        
                        $wheres[] = "(" . ($empty_filter ? "`a`.`risk_catalog_mapping` IS NULL OR `a`.`risk_catalog_mapping` = '' OR " : "") . "
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
                        $wheres[] = "(" . ($empty_filter ? "`a`.`risk_catalog_mapping` IS NULL OR `a`.`risk_catalog_mapping` = '' OR " : "") . "
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
    
    list($query, $group_name, $create_temporary_tables, $drop_temporary_tables) = make_full_risks_sql($query_type, $status, $sort, $group, $column_filters, $group_value_from_db, $custom_query, $bind_params, $having_query, $orderColumnName, $orderDir, $risks_by_team, $teams, $owners, $ownersmanagers);

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
            
            $management_review = management_review(convert_id($risk['id']), $risk['mgmt_review'], $next_review, $is_html = false);
            
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
                
                $management_review = management_review(convert_id($risk['id']), $risk['mgmt_review'], $next_review, $is_html = false);
                
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
                    $row['mitigation_accepted'] = $risk['mitigation_accepted'] ? $escaper->escapeHtml($lang['Yes']) : $escaper->escapeHtml($lang['No']);
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

    // For each row
    foreach ($array as $key=>$row) {

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

                $open_total[$counter] = $open_total[$counter-1] + 1;

            }
        
        // Otherwise, if the date is the same
        } else {

            // Increment the open count
            $open_count[$counter] = $open_count[$counter] + 1;

            // Update the open total
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
    
    // For each row
    foreach ($array as $key=>$row) {

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

                $close_total[$counter] = $close_total[$counter-1] + 1;

            }
        
        // Otherwise, if the date is the same
        } else {

            // Increment the closed count
            $close_count[$counter] = $close_count[$counter] + 1;

            // Update the close total
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
        $name = js_string_escape($element[0]);
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
                    <table border='1px' class='table table-bordered mb-2' style='background-color:{$escaper->escapeHtml($header_color)}'>
                        <tr>
                            <th width='50%' style='background-color:{$escaper->escapeHtml($header_color)}'>{$escaper->escapeHtml($lang['Framework'])}</th>
                            <th width='35%' style='background-color:{$escaper->escapeHtml($header_color)}'>{$escaper->escapeHtml($lang['Control'])}</th>
                        </tr>
                ";
                foreach ($control_frameworks as $framework) {
                    $cf_table .= "
                        <tr>
                            <td style='background-color:{$escaper->escapeHtml($header_color)}'>{$escaper->escapeHtml($framework['framework_name'])}</td>
                            <td style='background-color:{$escaper->escapeHtml($header_color)}'>{$escaper->escapeHtml($framework['reference_name'])}</td>
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
                                <th colspan='7' style='background-color:" . $escaper->escapeHtml($header_color) . "'>
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

                $risk_id = convert_id($risk['id']);
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
                                        $escaper->escapeHtml($calculated_risk) . "<span class='risk-color' style='background-color:" . $escaper->escapeHtml($color) . "'></span>
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
            $risk_id = convert_id($origin_risk_id);
            $status = $risks[0]['status'];
            $subject = try_decrypt($risks[0]['subject']);
            $calculated_risk = $risks[0]['calculated_risk'];
            
            // Get the risk color
            $color = get_risk_color($calculated_risk);

            echo "
                <table width='100%' class='table table-bordered table-condensed mb-2' role='grid' style='width: 100%;'>
                    <tbody>
                        <tr>
                            <th style='background-color:{$escaper->escapeHtml($color)};' bgcolor='{$escaper->escapeHtml($color)}' colspan='5'>
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
                            <th class='sorting_asc' aria-controls='mitigation-controls-table140955b56e1c6c5879' rowspan='1' colspan='1' style='width: 0px; padding-top: 0px; padding-bottom: 0px; border-top-width: 0px; border-bottom-width: 0px; height: 0px;' aria-sort='ascending' aria-label='&amp;nbsp;: activate to sort column descending'>
                                <div class='dataTables_sizing' style='height:0;overflow:hidden;'>&nbsp;
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
                    url: BASE_URL + "/api/mitigation_controls/get_mitigation_control_info",
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
        $where = [0];
        $where_ids = [];
        foreach($projects as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "(b.project_id is NULL OR b.project_id='')";
                }
                else
                {
                    $where_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(b.project_id, '".implode(",", $where_ids)."')";
        $where_sql .= " AND (". implode(" OR ", $where) . ")";
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
            $where = [0];
            $where_ids = [];
            foreach($control_class as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $where[] = "(cc.value is NULL OR cc.value='')";
                    }
                    else
                    {
                        $where_ids[] = $val;
                    }
                }
            }
            $where[] = "FIND_IN_SET(cc.value, '".implode(",", $where_ids)."')";
            
            $where_sql .= " AND (". implode(" OR ", $where) . ")";
        }
        elseif($control_class == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }

        // If control phase ID is requested.
        if($control_phase && is_array($control_phase)){
            $where = [0];
            $where_ids = [];
            foreach($control_phase as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $where[] = "(cph.value is NULL OR cph.value='')";
                    }
                    else
                    {
                        $where_ids[] = $val;
                    }
                }
            }
            $where[] = "FIND_IN_SET(cph.value, '".implode(",", $where_ids)."')";
            $where_sql .= " AND (". implode(" OR ", $where) . ")";
        }
        elseif($control_class == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }

        // If control priority ID is requested.
        if($control_priority && is_array($control_priority)){
            $where = [0];
            $where_ids = [];
            foreach($control_priority as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $where[] = "(cpr.value is NULL OR cpr.value='')";
                    }
                    else
                    {
                        $where_ids[] = $val;
                    }
                }
            }
            $where[] = "FIND_IN_SET(cpr.value, '".implode(",", $where_ids)."')";
            $where_sql .= " AND (". implode(" OR ", $where) . ")";
        }
        elseif($control_priority == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }
        
        // If control family ID is requested.
        if($control_family && is_array($control_family)){
            $where = [0];
            $where_ids = [];
            foreach($control_family as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $where[] = "(cf.value is NULL OR cf.value='')";
                    }
                    else
                    {
                        $where_ids[] = $val;
                    }
                }
            }
            $where[] = "FIND_IN_SET(cf.value, '".implode(",", $where_ids)."')";
            $where_sql .= " AND (". implode(" OR ", $where) . ")";
        }
        elseif($control_family == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }
        
        // If control owner ID is requested.
        if($control_owner && is_array($control_owner)){
            $where = [0];
            $where_or_ids = [];
            foreach($control_owner as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $where[] = "(cu.value is NULL OR cu.value='')";
                    }
                    else
                    {
                        $where_or_ids[] = $val;
                    }
                }
            }
            $where[] = "FIND_IN_SET(cu.value, '".implode(",", $where_or_ids)."')";
            
            $where_sql .= " AND (". implode(" OR ", $where) . ")";
        }
        elseif($control_owner == "all"){
            $where_sql .= " AND 1 ";
        }
        else{
            $where_sql .= " AND 0 ";
        }
        
        // If control framework ID is requested.
        if($control_framework && is_array($control_framework)){
            $where = [0];
            $where_or_ids = [];
            foreach($control_framework as $val){
                $val = (int)$val;
                if($val)
                {
                    // If unassigned option.
                    if($val == -1)
                    {
                        $where[] = "m.control_id is NULL";
                    }
                    else
                    {
                        $where_or_ids[] = $val;
                    }
                }
            }
            $where[] = "FIND_IN_SET(m_1.framework, '".implode(",", $where_or_ids)."')";
            $where_sql .= " AND (". implode(" OR ", $where) . ")";
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

        $control_number = str_getcsv($control_numbers);
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
                    bSort: true,
                    orderCellsTop: true,
                    ajax: {
                        url: BASE_URL + '/api/reports/appetite?type=' + appetite_type,
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

/*****************************************
 * FUNCTION: GET CONNECTIVITY VISUALIZER *
 *****************************************/
function get_connectivity_visualizer() {

    global $lang, $escaper;

    echo "
        <div class='card-body my-2 border'>
    ";

    // Begin the filter by form
    echo "
            <form name='filter' method='post' action=''>
    ";

    // Create a filter by
    echo "
                <label>" . $escaper->escapeHtml($lang['FilterBy']) . " :</label>
    ";
    // If no filter was posted
    if (!isset($_POST['filter'])) {
        // Set the filter option to None Selected
        $filter = 0;
    } else {
        $filter = (int)$_POST['filter'];
    }

    // If no selected was posted
    if (!isset($_POST['selected'])) {
        // Set the selected option to None Selected
        $selected = 0;
    } else {
        $selected = (int)$_POST['selected'];
    }

    // Create the dropdown
    echo "
                <select name='filter' onchange='javascript: submit()' class='form-select'>
                    <option value='0'" . ($filter == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['NoneSelected']) . "</option>
                    <option value='1'" . ($filter == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Risk']) . "</option>
                    <option value='2'" . ($filter == 2 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Asset']) . "</option>
                    <option value='3'" . ($filter == 3 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Framework']) . "</option>
                    <option value='4'" . ($filter == 4 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Control']) . "</option>
                    <option value='5'" . ($filter == 5 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Test']) . "</option>
                    <option value='6'" . ($filter == 6 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Document']) . "</option>
                </select>
    ";

    // If the filter is not zero
    if ($filter != 0) {

        // Create an empty array
        $array = [];

        // Script to make dropdown searchable
        echo "
                <script>
                    $(document).ready(function() {
                        $('.searchable-single-select-dropdown').select2();
                    });
                </script>
                
                <label class='mt-3'>" . $escaper->escapeHtml($lang['Selected']) . " :</label>
        ";

        // Create the dropdown
        echo "
                <select style='height: 235px;' class='searchable-single-select-dropdown form-select' name='selected' onchange='javascript: submit()'>
                    <option value='0'" . ($selected == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['NoneSelected']) . "</option>
        ";

        // Get the query based on the filter
        switch ($filter) {

            // If the filter is risks
            case 1:
                // Get the risks
                $type = "risk";
                $endpoint = "/api/v2/risks";
                $risks = call_simplerisk_api_endpoint($endpoint);
                $risks = $risks['risks'];

                // For each of the risks
                foreach ($risks as $risk) {

                    // Get the risk id and calculated risk score
                    $risk_id = $risk['id']+1000;
                    $calculated_risk = $risk['calculated_risk'];
                    $color = get_risk_color($calculated_risk);
                    echo "
                    <option value='" . $escaper->escapeHtml($risk_id) . "'" . ($selected == $risk_id ? " selected" : "") . ">[" . $escaper->escapeHtml($risk_id) . "] " . $escaper->escapeHtml($risk['subject']) . "</option>
                    ";
                    $array[] = [
                        "id" => $escaper->escapeHtml($risk_id),
                        "node_id" => "risk_id_" . $escaper->escapeHtml($risk['id']),
                        "node_name" => "[" . $escaper->escapeHtml($risk_id) . "] " . $escaper->escapeHtml($risk['subject']),
                        "color" => $color,
                    ];
                }
                break;
            case 2:
                // Get the verified assets
                $type = "asset";
                $endpoint = "/api/v2/assets?verified=true";
                $assets = call_simplerisk_api_endpoint($endpoint);
                $assets = $assets['assets'];

                // For each of the assets
                foreach ($assets as $asset) {
                    echo "
                    <option value='" . $escaper->escapeHtml($asset['id']) . "'" . ($selected == $asset['id'] ? " selected" : "") . ">" . $escaper->escapeHtml($asset['name']) . "</option>
                    ";
                    $array[] = [
                        "id" => $escaper->escapeHtml($asset['id']),
                        "node_id" => "asset_id_" . $escaper->escapeHtml($asset['id']),
                        "node_name" => $escaper->escapeHtml($asset['name']),
                        "color" => "#f7dc6f",
                    ];
                }
                break;
            case 3:
                // Get the frameworks
                $type = "framework";
                $endpoint = "/api/v2/governance/frameworks";
                $frameworks = call_simplerisk_api_endpoint($endpoint);
                $frameworks = $frameworks['frameworks'];

                // For each of the frameworks
                foreach ($frameworks as $framework) {
                    echo "
                    <option value='" . $escaper->escapeHtml($framework['value']) . "'" . ($selected == $framework['value'] ? " selected" : "") . ">" . $escaper->escapeHtml($framework['name']) . "</option>
                    ";
                    $array[] = [
                        "id" => $escaper->escapeHtml($framework['value']),
                        "node_id" => "framework_id_" . $escaper->escapeHtml($framework['value']),
                        "node_name" => $escaper->escapeHtml($framework['name']),
                        "color" => "#4a235a",
                    ];
                }
                break;
            case 4:
                // Get the controls
                $type = "control";
                $endpoint = "/api/v2/governance/controls";
                $controls = call_simplerisk_api_endpoint($endpoint);
                $controls = $controls['controls'];

                // For each of the controls
                foreach ($controls as $control) {
                    echo "
                    <option value='" . $escaper->escapeHtml($control['id']) . "'" . ($selected == $control['id'] ? " selected" : "") . ">" . $escaper->escapeHtml($control['long_name']) . "</option>
                    ";
                    $array[] = [
                        "id" => $escaper->escapeHtml($control['id']),
                        "node_id" => "control_id_" . $escaper->escapeHtml($control['id']),
                        "node_name" => $escaper->escapeHtml($control['long_name']),
                        "color" => "#154360",
                    ];
                }
                break;
            case 5:
                // Get the tests
                $type = "test";
                $endpoint = "/api/v2/compliance/tests";
                $tests = call_simplerisk_api_endpoint($endpoint);
                $tests = $tests['tests'];

                // For each of the tests
                foreach ($tests as $test) {
                    echo "
                    <option value='" . $escaper->escapeHtml($test['id']) . "'" . ($selected == $test['id'] ? " selected" : "") . ">" . $escaper->escapeHtml($test['name']) . "</option>
                    ";
                    $array[] = [
                        "id" => $escaper->escapeHtml($test['id']),
                        "node_id" => "test_id_" . $escaper->escapeHtml($test['id']),
                        "node_name" => $escaper->escapeHtml($test['name']),
                        "color" => "#2e86c1",
                    ];
                }
                break;
            case 6:
                // Get the documents
                $type = "document";
                $endpoint = "/api/v2/governance/documents";
                $documents = call_simplerisk_api_endpoint($endpoint);
                $documents = $documents['documents'];

                // For each of the documents
                foreach ($documents as $document) {
                    echo "
                    <option value='" . $escaper->escapeHtml($document['id']) . "'" . ($selected == $document['id'] ? " selected" : "") . ">" . $escaper->escapeHtml($document['document_name']) . "</option>
                    ";
                    $array[] = [
                        "id" => $escaper->escapeHtml($document['id']),
                        "node_id" => "document_id_" . $escaper->escapeHtml($document['id']),
                        "node_name" => $escaper->escapeHtml($document['document_name']),
                        "color" => "#a2d9ce",
                    ];
                }
                break;
            default:
                $type = "unknown";
                $selected = 0;
                break;
        }
    }
    echo "
                </select>
            </form>
        </div>
    ";
    if ($filter != 0) {
        // Display the connectivity information
        connectivity_visualizer($type, $selected, $array);
    }
}

/*************************************
 * FUNCTION: CONNECTIVITY VISUALIZER *
 *************************************/
function connectivity_visualizer($type, $id, $array) {

    global $escaper, $lang;

    // If the id provided is not 0 (None Selected)
    if ($id != 0) {

        // Get the associations based on the type
        switch ($type) {
            case "risk":
                $associations = connectivity_visualizer_associations_risk($id);
                $found = (!empty($associations['frameworks']) || !empty($associations['controls']) || !empty($associations['documents']) || !empty($associations['tests']) || !empty($associations['test_results']) || !empty($associations['assets']));
                break;
            case "asset":
                $associations = connectivity_visualizer_associations_asset($id);
                $found = (!empty($associations['frameworks']) || !empty($associations['controls']) || !empty($associations['documents']) || !empty($associations['tests']) || !empty($associations['test_results']) || !empty($associations['risks']));
                break;
            case "framework":
                $associations = connectivity_visualizer_associations_framework($id);
                $found = (!empty($associations['risks']) || !empty($associations['controls']) || !empty($associations['documents']) || !empty($associations['tests']) || !empty($associations['test_results']) || !empty($associations['assets']));
                break;
            case "control":
                $associations = connectivity_visualizer_associations_control($id);
                $found = (!empty($associations['frameworks']) || !empty($associations['risks']) || !empty($associations['documents']) || !empty($associations['tests']) || !empty($associations['test_results']) || !empty($associations['assets']));
                break;
            case "test":
                $associations = connectivity_visualizer_associations_test($id);
                $found = (!empty($associations['frameworks']) || !empty($associations['controls']) || !empty($associations['documents']) || !empty($associations['risks']) || !empty($associations['test_results']) || !empty($associations['assets']));
                break;
            case "document":
                $associations = connectivity_visualizer_associations_document($id);
                $found = (!empty($associations['frameworks']) || !empty($associations['controls']) || !empty($associations['risks']) || !empty($associations['tests']) || !empty($associations['test_results']) || !empty($associations['assets']));
                break;
            default:
                $associations = [];
                $found = false;
                break;
        }

        // If we found associations
        if ($found) {
            // Get the array values that goes with the id
            $key = array_search($id, array_column($array, "id"));
            $selected_array = $array[$key];

            // Display the connectivity visualizer
            connectivity_visualizer_display($type, $id, $selected_array, $associations);
            
        // If no associations were found
        } else {
            echo "
                <div class='card-body my-2 border'>
                    <font style='font-weight: bold; color: red;'>" . $escaper->escapeHtml($lang['ThereAreNoConnectionsAssociatedWithTheSelectedValue']) . "</font>
                </div>
            ";
        }
    }
}

/*********************************************
 * FUNCTION: CONNECTIVITY VISUALIZER DISPLAY *
 *********************************************/
function connectivity_visualizer_display($type, $id, $selected_array, $associations) {

    global $escaper, $lang;

    // Create an array of nodes and edges we have already added to prevent duplicates
    $added_nodes = [];
    $added_edges = [];

    // Add the primary node to the graph
    $node_id = $selected_array['node_id'];
    $node_name = $selected_array['node_name'];
    $color = $selected_array['color'];

    // If the name is longer than 50 characters
    if (strlen($node_name) > 50) {
        // Truncate the name to 50 characters
        $node_name = substr($node_name, 0, 50) . "...";
    }

    $added_nodes[] = [
        "node_id" => $node_id,
        "node_name" => $node_name,
        "size" => 20,
        "color" => $color,
    ];

    // Create an accordion div
    echo "
        <div class='accordion my-2'>
            <div class='accordion-item' id='filter-selections-container'>
                <h2 class='accordion-header'>
                    <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#filter-selections-accordion-body'>
                        " . $escaper->escapeHtml($lang['ShowAssociationData']) . "
                    </button>
                </h2>
                <div id='filter-selections-accordion-body' class='accordion-collapse collapse'>
                    <div class='accordion-body card-body'>
                        <div class='row'>
                            <div class='col-4'><h6>" . $escaper->escapeHtml($lang['Type']) . "</h6></div>
                            <div class='col-4'><h6>" . $escaper->escapeHtml($lang['Name']) . "</h6></div>
                            <div class='col-4'><h6>" . $escaper->escapeHtml($lang['Association']) . "</h6></div>
                        </div>
    ";

    // Iterate through the associations
    foreach ($associations as $key => $association) {
        // Iterate through the nodes to add the nodes
        foreach ($association as $node) {
            // Get the node details
            $node_id = $node['node_id'];
            $node_name = $node['node_name'];
            $connected_node_id = $node['connected_node_id'];
            $color = $node['color'];

            // If we haven't already created this node
            if (!in_array($node_id, array_column($added_nodes, "node_id"))) {
                // Add the node to the graph
                $added_nodes[] = [
                    "node_id" => $node_id,
                    "node_name" => $node_name,
                    "size" => 10,
                    "color" => $color,
                ];

                // Add the node to the table
                echo "
                        <div class='row'>
                            <div class='col-4'>{$key}</div>
                            <div class='col-4'>{$node_name}</div>
                            <div class='col-4'>{$node_id} => {$connected_node_id}</div>
                        </div>
                ";
            }

            // If the current edge does not overlap with an existing edge
            $edge1 = in_array($connected_node_id, array_column($added_edges, $node_id));
            $edge2 = in_array($node_id, array_column($added_edges, $connected_node_id));
            if (!$edge1 && !$edge2) {
                // Add the edge to the added_edges array
                $added_edges[] = [
                    "node_id" => $node_id,
                    "connected_node_id" => $connected_node_id,
                ];
            }
        }
    }
    
    echo "
                    </div>
                </div>
            </div>
        </div>
    ";

    // Display the graphology graph
    echo "
        <div class='card-body my-2 border'>
            <div id='connectivity_visualizer' style='height: 500px;'></div>
            <script type='module'>
                import {circular} from 'https://cdn.jsdelivr.net/npm/graphology-layout@0.6.1/+esm';

                // Create a graphology graph
                const graph = new graphology.Graph();
    ";

    // For each of the added nodes
    foreach ($added_nodes as $node) {
        // Display the node
        $node_id = $node['node_id'];
        $node_name = $node['node_name'];
        $size = $node['size'];
        $color = $node['color'];
        echo "
                graph.addNode(\"{$node_id}\", { label: \"{$node_name}\", x: Math.random(), y: Math.random(), size: {$size}, color: \"{$color}\" });
        ";
    }

    // For each of the added edges
    foreach ($added_edges as $edge) {
        // Display the edge
        $node_id = $edge['node_id'];
        $connected_node_id = $edge['connected_node_id'];
        echo "
                graph.addEdge(\"{$node_id}\", \"{$connected_node_id}\", { size: 1, color: \"black\" });
        ";
    }

    echo "              
                // Set a circular layout of the graph
                circular.assign(graph);
                
                // Instantiate sigma.js and render the graph
                const sigmaInstance = new Sigma(graph, document.getElementById(\"connectivity_visualizer\"));
                
            </script>
        </div>
    ";
}

/*******************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS RISK *
 *******************************************************/
function connectivity_visualizer_associations_risk($id)
{
    // Create the default empty arrays
    $asset_associations = [];
    $control_associations = [];
    $framework_associations = [];
    $test_associations = [];
    $document_associations = [];
    $test_result_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        // Get the associations for the risk
        $endpoint = "/api/v2/risks/associations?id={$id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $asset_associations = $associations['assets'];
        $control_associations = $associations['controls'];
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        // Get the control id
        $control_id = $value['control_id'];

        // Get the associations for the control
        $endpoint = "/api/v2/governance/controls/associations?id={$control_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $framework_associations = array_merge((array)$framework_associations, (array)$associations['frameworks']);
        $test_associations = array_merge((array)$test_associations, (array)$associations['tests']);
        $document_associations = array_merge((array)$document_associations, (array)$associations['documents']);
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        // Get the test id
        $test_id = $value['test_id'];

        // Get the associations for the test result
        $endpoint = "/api/v2/compliance/tests/associations?id={$test_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $test_result_associations = array_merge((array)$test_result_associations, (array)$associations['test_results']);
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "controls" => $control_associations,
        "frameworks" => $framework_associations,
        "tests" => $test_associations,
        "documents" => $document_associations,
        "test_results" => $test_result_associations,
    ];

    // Return the associations
    return $associations;
}

/********************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS ASSET *
 ********************************************************/
function connectivity_visualizer_associations_asset($id)
{
    // Create the default empty arrays
    $risk_associations = [];
    $control_associations = [];
    $framework_associations = [];
    $test_associations = [];
    $document_associations = [];
    $test_result_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        // Get the associations for the risk
        $endpoint = "/api/v2/assets/associations?id={$id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $risk_associations = $associations['risks'];
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        // Get the risk id
        $risk_id = $value['risk_id'];

        // Get the associations for the control
        $endpoint = "/api/v2/risks/associations?id={$risk_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $control_associations = array_merge((array)$control_associations, (array)$associations['controls']);
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        // Get the control id
        $control_id = $value['control_id'];

        // Get the associations for the control
        $endpoint = "/api/v2/governance/controls/associations?id={$control_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $framework_associations = array_merge((array)$framework_associations, (array)$associations['frameworks']);
        $test_associations = array_merge((array)$test_associations, (array)$associations['tests']);
        $document_associations = array_merge((array)$document_associations, (array)$associations['documents']);
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        // Get the test id
        $test_id = $value['test_id'];

        // Get the associations for the test result
        $endpoint = "/api/v2/compliance/tests/associations?id={$test_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $test_result_associations = array_merge((array)$test_result_associations, (array)$associations['test_results']);
    }

    // Merge the association arrays
    $associations = [
        "risks" => $risk_associations,
        "controls" => $control_associations,
        "frameworks" => $framework_associations,
        "tests" => $test_associations,
        "documents" => $document_associations,
        "test_results" => $test_result_associations,
    ];

    // Return the associations
    return $associations;
}

/************************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS FRAMEWORK *
 ************************************************************/
function connectivity_visualizer_associations_framework($id)
{
    // Create the default empty arrays
    $control_associations = [];
    $test_associations = [];
    $document_associations = [];
    $risk_associations = [];
    $test_result_associations = [];
    $asset_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        // Get the associations for the framework
        $endpoint = "/api/v2/governance/frameworks/associations?id={$id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $control_associations = $associations['controls'];
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        // Get the control id
        $control_id = $value['control_id'];

        // Get the associations for the control
        $endpoint = "/api/v2/governance/controls/associations?id={$control_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $test_associations = array_merge((array)$test_associations, (array)$associations['tests']);
        $document_associations = array_merge((array)$document_associations, (array)$associations['documents']);
        $risk_associations = array_merge((array)$risk_associations, (array)$associations['risks']);
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        // Get the test id
        $test_id = $value['test_id'];

        // Get the associations for the test result
        $endpoint = "/api/v2/compliance/tests/associations?id={$test_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $test_result_associations = array_merge((array)$test_result_associations, (array)$associations['test_results']);
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        // Get the risk id
        $risk_id = $value['risk_id'];

        // Get the associations for the risk
        $endpoint = "/api/v2/risks/associations?id={$risk_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $asset_associations = array_merge((array)$asset_associations, (array)$associations['assets']);
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "controls" => $control_associations,
        "tests" => $test_associations,
        "documents" => $document_associations,
        "test_results" => $test_result_associations,
        "risks" => $risk_associations,
    ];

    // Return the associations
    return $associations;
}

/**********************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS CONTROL *
 **********************************************************/
function connectivity_visualizer_associations_control($id)
{
    // Create the default empty arrays
    $framework_associations = [];
    $test_associations = [];
    $document_associations = [];
    $risk_associations = [];
    $test_result_associations = [];
    $asset_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        // Get the associations for the control
        $endpoint = "/api/v2/governance/controls/associations?id={$id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $framework_associations = $associations['frameworks'];
        $test_associations = $associations['tests'];
        $document_associations = $associations['documents'];
        $risk_associations = $associations['risks'];
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        // Get the test id
        $test_id = $value['test_id'];

        // Get the associations for the test result
        $endpoint = "/api/v2/compliance/tests/associations?id={$test_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $test_result_associations = array_merge((array)$test_result_associations, (array)$associations['test_results']);
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        // Get the risk id
        $risk_id = $value['risk_id'];

        // Get the associations for the risk
        $endpoint = "/api/v2/risks/associations?id={$risk_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $asset_associations = array_merge((array)$asset_associations, (array)$associations['assets']);
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "frameworks" => $framework_associations,
        "tests" => $test_associations,
        "documents" => $document_associations,
        "test_results" => $test_result_associations,
        "risks" => $risk_associations,
    ];

    // Return the associations
    return $associations;
}

/*******************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS TEST *
 *******************************************************/
function connectivity_visualizer_associations_test($id)
{
    // Create the default empty arrays
    $framework_associations = [];
    $control_associations = [];
    $document_associations = [];
    $risk_associations = [];
    $test_result_associations = [];
    $asset_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        // Get the associations for the test
        $endpoint = "/api/v2/compliance/tests/associations?id={$id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $test_result_associations = $associations['test_results'];
        $control_associations = $associations['controls'];
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        // Get the control id
        $control_id = $value['control_id'];

        // Get the associations for the control
        $endpoint = "/api/v2/governance/controls/associations?id={$control_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $framework_associations = array_merge((array)$framework_associations, (array)$associations['frameworks']);
        $document_associations = array_merge((array)$document_associations, (array)$associations['documents']);
        $risk_associations = array_merge((array)$risk_associations, (array)$associations['risks']);
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        // Get the risk id
        $risk_id = $value['risk_id'];

        // Get the associations for the risk
        $endpoint = "/api/v2/risks/associations?id={$risk_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $asset_associations = array_merge((array)$asset_associations, (array)$associations['assets']);
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "frameworks" => $framework_associations,
        "test_results" => $test_result_associations,
        "documents" => $document_associations,
        "controls" => $control_associations,
        "risks" => $risk_associations,
    ];

    // Return the associations
    return $associations;
}

/***********************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS DOCUMENT *
 ***********************************************************/
function connectivity_visualizer_associations_document($id)
{
    // Create the default empty arrays
    $framework_associations = [];
    $control_associations = [];
    $test_associations = [];
    $risk_associations = [];
    $test_result_associations = [];
    $asset_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        // Get the associations for the document
        $endpoint = "/api/v2/governance/documents/associations?id={$id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $control_associations = $associations['controls'];
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        // Get the control id
        $control_id = $value['control_id'];

        // Get the associations for the control
        $endpoint = "/api/v2/governance/controls/associations?id={$control_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $framework_associations = array_merge((array)$framework_associations, (array)$associations['frameworks']);
        $test_associations = array_merge((array)$test_associations, (array)$associations['tests']);
        $risk_associations = array_merge((array)$risk_associations, (array)$associations['risks']);
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        // Get the risk id
        $risk_id = $value['risk_id'];

        // Get the associations for the risk
        $endpoint = "/api/v2/risks/associations?id={$risk_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $asset_associations = array_merge((array)$asset_associations, (array)$associations['assets']);
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        // Get the test id
        $test_id = $value['test_id'];

        // Get the associations for the test result
        $endpoint = "/api/v2/compliance/tests/associations?id={$test_id}";
        $associations = call_simplerisk_api_endpoint($endpoint);
        $test_result_associations = array_merge((array)$test_result_associations, (array)$associations['test_results']);
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "frameworks" => $framework_associations,
        "test_results" => $test_result_associations,
        "tests" => $test_associations,
        "controls" => $control_associations,
        "risks" => $risk_associations,
    ];

    // Return the associations
    return $associations;
}

/*********************************************
 * FUNCTION: GET ASSET CONNECTIVITY FOR RISK *
 *********************************************/
function get_asset_connectivity_for_risk($risk_id)
{
    global $lang, $escaper;

    // Get the id
    $id = $risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Create an empty array to track assets we have already added
    $assets_added = [];

    // Get the assets
    $stmt = $db->prepare("SELECT DISTINCT id, name FROM assets a LEFT JOIN risks_to_assets rta ON a.id = rta.asset_id WHERE rta.risk_id = :risk_id AND verified=1;");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item in the array
    foreach ($array as $value)
    {
        // Get the asset name and id
        $asset_id = $value['id'];
        $asset_name = try_decrypt($value['name']);

        // Create the asset to risk association
        $associations[] =[
            "risk_id" => $id,
            "asset_id" => $asset_id,
            "asset_name" => $asset_name,
            "node_id" => "asset_id_{$asset_id}",
            "node_name" => $asset_name,
            "connected_node_id" => "risk_id_{$id}",
            "color" => "#f7dc6f",
        ];

        // Track the added asset id
        $assets_added[] = $asset_id;
    }

    // Get the asset groups
    $stmt = $db->prepare("SELECT DISTINCT a.id, a.name FROM risks_to_asset_groups rtag LEFT JOIN risks r ON rtag.risk_id = r.id LEFT JOIN assets_asset_groups aag ON aag.asset_group_id = rtag.asset_group_id LEFT JOIN assets a ON a.id = aag.asset_id WHERE rtag.risk_id = :risk_id;");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item in the array
    foreach ($array as $value)
    {
        // Get the asset name and id
        $asset_id = $value['id'];
        $asset_name = try_decrypt($value['name']);

        // If the asset name is not empty or null
        if ($asset_name != null && $asset_name != '')
        {
            // If the asset has not already been added
            if (!in_array($asset_id, $assets_added))
            {
                // Add the association
                $associations[] = [
                    "risk_id" => $id,
                    "asset_id" => $asset_id,
                    "asset_name" => $asset_name,
                    "node_id" => "asset_id_{$asset_id}",
                    "node_name" => $asset_name,
                    "connected_node_id" => "risk_id_{$id}",
                    "color" => "#f7dc6f",
                ];

                // Track the added asset id
                $assets_added[] = $asset_id;
            }
        }
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/***********************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR RISK *
 ***********************************************/
function get_control_connectivity_for_risk($risk_id)
{
    global $lang, $escaper;

    // Get the id
    $id = $risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT DISTINCT fc.id, fc.short_name FROM mitigations m LEFT JOIN mitigation_to_controls mtc ON m.id = mtc.mitigation_id LEFT JOIN framework_controls fc ON mtc.control_id = fc.id WHERE m.risk_id = :risk_id;");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item in the array
    foreach ($array as $value)
    {
        // Get the control name and id
        $control_name = $value['short_name'];
        $control_id = $value['id'];

        // If the control name is not empty or null
        if ($control_name != null && $control_name != '')
        {
            $associations[] = [
                "risk_id" => $risk_id,
                "control_id" => $control_id,
                "control_name" => $control_name,
                "node_id" => "control_id_{$control_id}",
                "node_name" => $control_name,
                "connected_node_id" => "risk_id_{$id}",
                "color" => "#154360",
            ];
        }
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/*********************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR ASSET *
 *********************************************/
function get_risk_connectivity_for_asset($asset_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the assets
    $stmt = $db->prepare("SELECT DISTINCT r.id, r.subject, rs.calculated_risk FROM risks r LEFT JOIN risk_scoring rs ON r.id = rs.id LEFT JOIN risks_to_assets rta ON r.id = rta.risk_id WHERE rta.asset_id = :asset_id;");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Strip risks that the user shouldn't have access to
        $array = strip_no_access_risks($array, null, "id");
    }

    // For each item in the array
    foreach ($array as $value)
    {
        // Get the risk id and subject
        $risk_id = $value['id']+1000;
        $subject = try_decrypt($value['subject']);
        $color = get_risk_color($value['calculated_risk']);

        // If the subject is more than 50 characters
        if (strlen($subject) > 50)
        {
            // Truncate the selected subject to 50 characters
            $subject = "[" . $risk_id . "] " . substr($subject, 0, 50) . "...";
        }
        else $subject = "[" . $risk_id . "] " . $subject;

        // Create the risk to asset association
        $associations[] = [
            "asset_id" => $asset_id,
            "risk_id" => $risk_id,
            "risk_name" => $subject,
            "node_id" => "risk_id_{$value['id']}",
            "node_name" => $subject,
            "connected_node_id" => "asset_id_{$asset_id}",
            "color" => $color,
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/****************************************************
 * FUNCTION: GET FRAMEWORK CONNECTIVITY FOR CONTROL *
 ****************************************************/
function get_framework_connectivity_for_control($control_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the frameworks for this control
    $stmt = $db->prepare("SELECT DISTINCT f.value, f.name FROM framework_control_mappings fcm LEFT JOIN frameworks f ON f.value = fcm.framework WHERE fcm.control_id = :control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each framework
    foreach ($frameworks as $value)
    {
        // Get the framework name and id
        $framework_id = $value['value'];
        $framework_name = try_decrypt($value['name']);

        // Display the connectivity to frameworks
        $associations[] = [
            "control_id" => $control_id,
            "framework_id" => $framework_id,
            "framework_name" => $framework_name,
            "node_id" => "framework_id_{$framework_id}",
            "node_name" => $framework_name,
            "connected_node_id" => "control_id_{$control_id}",
            "color" => "#4a235a",
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/***********************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR CONTROL *
 ***********************************************/
function get_risk_connectivity_for_control($control_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the risks for this control
    $stmt = $db->prepare("SELECT DISTINCT r.id, r.subject, rs.calculated_risk FROM risks r LEFT JOIN risk_scoring rs ON r.id = rs.id LEFT JOIN mitigations m ON r.id = m.risk_id LEFT JOIN mitigation_to_controls mtc ON m.id = mtc.mitigation_id WHERE mtc.control_id = :control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Strip risks that the user shouldn't have access to
        $risks = strip_no_access_risks($risks, null, "id");
    }

    // For each risk
    foreach ($risks as $value)
    {
        // Get the risk id and subject
        $risk_id = $value['id']+1000;
        $subject = try_decrypt($value['subject']);
        $color = get_risk_color($value['calculated_risk']);

        // If the subject is more than 50 characters
        if (strlen($subject) > 50)
        {
            // Truncate the selected subject to 50 characters
            $subject = "[" . $risk_id . "] " . substr($subject, 0, 50) . "...";
        }
        else $subject = "[" . $risk_id . "] " . $subject;

        // Display the connectivity to risks
        $associations[] = [
            "control_id" => $control_id,
            "risk_id" => $risk_id,
            "risk_name" => $subject,
            "node_id" => "risk_id_{$value['id']}",
            "node_name" => $subject,
            "connected_node_id" => "control_id_{$control_id}",
            "color" => $color,
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/***********************************************
 * FUNCTION: GET TEST CONNECTIVITY FOR CONTROL *
 ***********************************************/
function get_test_connectivity_for_control($control_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the tests for this control
    $stmt = $db->prepare("SELECT DISTINCT fct.id, fct.name FROM framework_control_tests fct WHERE fct.framework_control_id = :control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each test
    foreach ($tests as $value)
    {
        // Get the test name and id
        $test_id = $value['id'];
        $test_name = $value['name'];

        // Display the connectivity to tests
        $associations[] = [
            "control_id" => $control_id,
            "test_id" => $test_id,
            "test_name" => $test_name,
            "node_id" => "test_id_{$test_id}",
            "node_name" => $test_name,
            "connected_node_id" => "control_id_{$control_id}",
            "color" => "#2e86c1",
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/****************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR FRAMEWORK *
 ****************************************************/
function get_control_connectivity_for_framework($framework_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the controls for this framework
    $stmt = $db->prepare("SELECT DISTINCT fc.id, fc.short_name FROM framework_controls fc LEFT JOIN framework_control_mappings fcm ON fc.id = fcm.control_id WHERE fcm.framework = :framework_id;");
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each control
    foreach ($controls as $value)
    {
        // Get the control name and id
        $control_id = $value['id'];
        $control_name = $value['short_name'];

        // Display the connectivity to frameworks
        $associations[] = [
            "framework_id" => $framework_id,
            "control_id" => $control_id,
            "control_name" => $control_name,
            "node_id" => "control_id_{$control_id}",
            "node_name" => $control_name,
            "connected_node_id" => "framework_id_{$framework_id}",
            "color" => "#154360",
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/***********************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR TEST *
 ***********************************************/
function get_control_connectivity_for_test($test_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the controls for this framework
    $stmt = $db->prepare("SELECT DISTINCT fc.id, fc.short_name FROM framework_controls fc LEFT JOIN framework_control_tests fct ON fc.id = fct.framework_control_id WHERE fct.id = :test_id;");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each control
    foreach ($controls as $value)
    {
        // Get the control name and id
        $control_id = $value['id'];
        $control_name = $value['short_name'];

        // Display the connectivity to tests
        $associations[] = [
            "test_id" => $test_id,
            "control_id" => $control_id,
            "control_name" => $control_name,
            "node_id" => "control_id_{$control_id}",
            "node_name" => $control_name,
            "connected_node_id" => "test_id_{$test_id}",
            "color" => "#154360",
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/***************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR DOCUMENT *
 ***************************************************/
function get_control_connectivity_for_document($document_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the list of control ids for the document
    $stmt = $db->prepare("SELECT DISTINCT control_ids FROM documents WHERE id = :document_id;");
    $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
    $stmt->execute();
    $control_ids_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $control_ids = $control_ids_array[0]['control_ids'];

    // If the control_ids is not null
    if ($control_ids != null)
    {
        // Get the controls for this document
        $stmt = $db->prepare("SELECT DISTINCT fc.id, fc.short_name FROM framework_controls fc WHERE FIND_IN_SET(fc.id, '" . $control_ids . "');");
        $stmt->execute();

        // Store the list in the array
        $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each control
        foreach ($controls as $value)
        {
            // Get the control name and id
            $control_id = $value['id'];
            $control_name = $value['short_name'];

            // Display the connectivity to documents
            $associations[] = [
                "document_id" => $document_id,
                "control_id" => $control_id,
                "control_name" => $control_name,
                "node_id" => "control_id_{$control_id}",
                "node_name" => $control_name,
                "connected_node_id" => "document_id_{$document_id}",
                "color" => "#154360",
            ];
        }
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/***************************************************
 * FUNCTION: GET DOCUMENT CONNECTIVITY FOR CONTROL *
 ***************************************************/
function get_document_connectivity_for_control($control_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the list of documents with this control id
    $stmt = $db->prepare("SELECT DISTINCT d.id, d.document_name FROM documents d WHERE FIND_IN_SET(:control_id, d.control_ids);");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each document
    foreach ($documents as $value)
    {
        // Get the document name and id
        $document_id = $value['id'];
        $document_name = $value['document_name'];

        // Display the connectivity to documents
        $associations[] = [
            "control_id" => $control_id,
            "document_id" => $document_id,
            "document_name" => $document_name,
            "node_id" => "document_id_{$document_id}",
            "node_name" => $document_name,
            "connected_node_id" => "control_id_{$control_id}",
            "color" => "#a2d9ce",
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/***********************************************
 * FUNCTION: GET RESULTS CONNECTIVITY FOR TEST *
 ***********************************************/
function get_results_connectivity_for_test($test_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the controls for this framework
    $stmt = $db->prepare("SELECT DISTINCT fctr.id, fctr.test_result, fctr.test_date FROM framework_control_test_results fctr LEFT JOIN framework_control_test_audits fcta ON fctr.test_audit_id = fcta.id LEFT JOIN framework_control_tests fct ON fct.id = fcta.test_id WHERE fct.id = :test_id;");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each test result
    foreach ($results as $value)
    {
        // Get the result name and id
        $result_id = $value['id'];
        $result_name = $value['test_result'];
        $result_date = $value['test_date'];

        // Get the color based on the test result
        switch ($result_name)
        {
            case "Pass":
                $color = "#66CC00";
                break;
            case "Fail":
                $color = "#FF0000";
                break;
            default:
                $color = "#D3D3D3";
                break;
        }

        // Display the connectivity to results
        $associations[] = [
            "test_id" => $test_id,
            "test_result_id" => $result_id,
            "test_result_name" => $result_date . " [" . $result_name . "]",
            "node_id" => "test_result_id_{$result_id}",
            "node_name" => $result_date . " [" . $result_name . "]",
            "connected_node_id" => "test_id_{$test_id}",
            "color" => $color,
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
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

	    // Escaping it here as it's used later both as key and value and wanted to make sure that they match
        // Also, passing null to the third parameter of str_replace is deprecated, so using an empty string instead
	    $value['family_short_name'] = str_replace("'", "\'", $value['family_short_name'] ?? '');

		// If this is not the current category
		if ($value['family_short_name'] != $current_category) {

			// Add the family to the category array
			$categories[] = $value['family_short_name'];

			// Set the count for this family to one
			$categories_count[$value['family_short_name']] = 1;

			// Put the first value in the categories current maturity sum array
			$categories_current_maturity_sum[$value['family_short_name']] = $value['control_maturity'];

			// Put the first value in the categories desired maturity sum array
			$categories_desired_maturity_sum[$value['family_short_name']] = $value['desired_maturity'];

			// Set the new current category
			$current_category = $value['family_short_name'];

        // If the category hasn't changed
		} else {

			// Increment the count
			$categories_count[$value['family_short_name']] = $categories_count[$value['family_short_name']] + 1;

			// Increment the current maturity sum
			$categories_current_maturity_sum[$value['family_short_name']] = $categories_current_maturity_sum[$value['family_short_name']] + $value['control_maturity'];

			// Increment the desired maturity sum
			$categories_desired_maturity_sum[$value['family_short_name']] = $categories_desired_maturity_sum[$value['family_short_name']] + $value['desired_maturity'];

		}

	}

	// Create the empty data arrays
	$categories_current_maturity_average = [];
	$categories_desired_maturity_average = [];

	// For each category
	foreach ($categories as $key => $value) {

		// Average = sum / value
		$current_maturity_average = $categories_current_maturity_sum[$value] / $categories_count[$value];
		$desired_maturity_average = $categories_desired_maturity_sum[$value] / $categories_count[$value];
		$categories_current_maturity_average[] = round($current_maturity_average, 1);
		$categories_desired_maturity_average[] = round($desired_maturity_average, 1);

	}

	// Create the Current Maturity dataset
    $current_maturity_label = str_replace("'", "\'", $lang['CurrentControlMaturity']);
    $current_maturity_dataset = [
        "label" => $current_maturity_label,
        "data" => empty($categories_current_maturity_average) ? [] : $categories_current_maturity_average,
    ];

	// Create the Desired Maturity dataset
    $desired_maturity_label = str_replace("'", "\'", $lang['DesiredControlMaturity']);
    $desired_maturity_dataset = [
        "label" => $desired_maturity_label,
        "data" => empty($categories_desired_maturity_average) ? [] : $categories_desired_maturity_average,
    ];

    // Create the combined datasets array
    $datasets = [
        $current_maturity_dataset,
        $desired_maturity_dataset
    ];

    $title = $escaper->escapeHtml($lang['CurrentVsDesiredMaturity']);
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

?>