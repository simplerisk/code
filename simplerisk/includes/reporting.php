<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/HighchartsPHP/Highchart.php'));


require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

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
                    ROUND(scoring.calculated_risk - (scoring.calculated_risk * GREATEST(IFNULL(mitg.mitigation_percent,0), IFNULL(MAX(ctrl.mitigation_percent), 0)) / 100), 2) AS residual_risk
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
function get_risk_trend($title = null)
{
    $chart = new Highchart();
    $chart->includeExtraScripts();

    // Set the timezone to the one configured for SimpleRisk
    $chart->chart->time->useUTC = false;
    $chart->chart->time->timezone = get_setting("default_timezone");

    $chart->chart->type = "arearange";
    $chart->chart->zoomType = "x";
    $chart->title->text = $title;
    $chart->xAxis->type = "datetime";
    $chart->yAxis->title->text = null;
    $chart->yAxis->min = 0;
    $chart->tooltip = array(
        'crosshairs' => true,
        'shared' => true,
        'valueSuffix' => ' risk(s)'
    );
    $chart->legend->enabled = false;
        $chart->chart->renderTo = "risk_trend_chart";
        $chart->credits->enabled = false;
    $chart->plotOptions->series->marker->enabled = false;
    $chart->plotOptions->series->marker->lineWidth = "2";

    // These set the marker symbol when selected
    $chart->plotOptions->series->marker->symbol = "circle";
    $chart->plotOptions->series->marker->states->hover->enabled = true;
    $chart->plotOptions->series->marker->states->hover->fillColor = "white";
    $chart->plotOptions->series->marker->states->hover->lineColor = "black";
    $chart->plotOptions->series->marker->states->hover->lineWidth = "2";

    // Get the opened risks array by month
    $opened_risks = get_opened_risks_array("day");
    $open_date = empty($opened_risks[0]) ? [] : $opened_risks[0];
    $open_count = empty($opened_risks[1]) ? [] : $opened_risks[1];

    // Get the closed risks array by month
    $closed_risks = get_closed_risks_array("day");
    $close_date = empty($closed_risks[0]) ? [] : $closed_risks[0];
    $close_count = empty($closed_risks[1]) ? [] : $closed_risks[1];

    // If the opened risks array is empty
    if (empty($opened_risks[0]))
    {
        $opened_risk_data[] = array("No Data Available", 0);
    }
    // Otherwise
    else
    {
        // Set the initial values
        $date = strtotime($open_date[0]);
        $opened_sum = 0;
        $closed_sum = 0;

        // For each date from the start date until today
        while ($date <= time())
        {
            // If the PHP version is >= 5.5.0
            // array_column is new as of PHP 5.5
            if (strnatcmp(phpversion(),'5.5.0') >= 0)
            {
                // Search the open risks array
                $opened_search = array_search(date("Y-m-d", $date), $open_date);
            }
            else $opened_search = false;
            

            // If the current date is in the opened array
            if ($opened_search !== false)
            {
                $count = $open_count[$opened_search];
                $opened_sum += $count;
            }

            // If the PHP version is >= 5.5.0
            // array_column is new as of PHP 5.5
            if (strnatcmp(phpversion(),'5.5.0') >= 0)
            {
                // Search the closed array for the value
                $closed_search = array_search(date("Y-m-d", $date), $close_date);
            }
            else $closed_search = false;

            // If the current date is in the closed array
            if ($closed_search !== false)
            {
                $count = $close_count[$closed_search];
                $closed_sum += $count;
            }

            // Create the data arrays
            $opened_risk_data[] = array($date * 1000, $opened_sum);
            $closed_risk_data[] = array($date * 1000, $closed_sum);
            $trend_data[] = array($date * 1000, $opened_sum - $closed_sum);

            // Increment the date one day
            $date = strtotime("+1 day", $date);
        }

        // Draw the open risks line
        $chart->series[] = array(
            'type' => "line",
            'name' => "Opened Risks",
            'color' => "red",
            'lineWidth' => "2",
            'data' => empty($opened_risk_data) ? [] : $opened_risk_data
        );

        // Draw the closed risks line
        $chart->series[] = array(
            'type' => "line",
            'name' => "Closed Risks",
            'color' => "blue",
            'lineWidth' => "2",
            'data' => empty($closed_risk_data) ? [] : $closed_risk_data
        );

        // Draw the trend line
        $chart->series[] = array(
            'type' => "line",
            'name' => "Trend",
            'color' => "#000000",
            'lineWidth' => "2",
            'data' => empty($trend_data) ? [] : $trend_data
        );
    }

    $chart->printScripts();
    echo "<div id=\"risk_trend_chart\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("risk_trend_chart");
    echo "</script>\n";

    // Get the version of PHP
    if (!defined('PHP_VERSION_ID'))
    {
        $version = explode('.', PHP_VERSION);

        define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
    }

    // If the PHP version is < 5.5
    if (PHP_VERSION_ID < 50500)
    {
        echo "<br /><p><font size=\"1\">* This report requires PHP >= 5.5 in order to run properly.</font></p>\n";
    }
}

/******************************
 * FUNCTION: GET RISK PYRAMID *
 ******************************/
function get_risk_pyramid($title = null)
{
    $chart = new Highchart();

    $chart->chart->type = "pyramid";
    $chart->chart->marginRight = "100";
    $chart->title->text = $title;
    $chart->chart->renderTo = "risk_pyramid_chart";
    $chart->credits->enabled = false;
    $chart->plotOptions->series->dataLabels->enabled = true;
    $chart->plotOptions->series->dataLabels->format = "<b>{point.name}</b> ({point.y:,.0f})";
    $chart->plotOptions->series->dataLabels->color = "(Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'";
    $chart->plotOptions->series->dataLabels->softConnector = true;
    $chart->legend->enabled = false;

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("SELECT * from `risk_levels` ORDER BY value DESC");
    $stmt->execute();
    $array = $stmt->fetchAll();
    $veryhigh = $array[0]['value'];
    $high = $array[1]['value'];
    $medium = $array[2]['value'];
    $low = $array[3]['value'];

    $very_high_display_name = get_risk_level_display_name('Very High');
    $high_display_name      = get_risk_level_display_name('High');
    $medium_display_name    = get_risk_level_display_name('Medium');
    $low_display_name       = get_risk_level_display_name('Low');
    $insignificant_display_name = get_risk_level_display_name('Insignificant');

    // If the team separation extra is not enabled
    if (!team_separation_extra())
    {
        // Query the database
    $stmt = $db->prepare("select a.residual_risk, COUNT(*) AS num, CASE WHEN residual_risk >= :veryhigh THEN :very_high_display_name WHEN residual_risk < :veryhigh AND residual_risk >= :high THEN :high_display_name WHEN residual_risk < :high AND residual_risk >= :medium THEN :medium_display_name WHEN residual_risk < :medium AND residual_risk >= :low THEN :low_display_name WHEN residual_risk < :low AND residual_risk >= 0 THEN :insignificant_display_name END AS level from (select ROUND(a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(c.mitigation_percent,0), IFNULL(MAX(d.mitigation_percent), 0)) / 100), 2) as residual_risk 
    FROM `risk_scoring` a 
        JOIN `risks` b ON a.id = b.id 
        LEFT JOIN mitigations c ON b.id = c.risk_id
        LEFT JOIN `mitigation_to_controls` mtc ON c.id = mtc.mitigation_id
        LEFT JOIN framework_controls d ON mtc.control_id=d.id AND d.deleted=0 
    WHERE b.status != \"Closed\" GROUP BY b.id) as a GROUP BY level ORDER BY a.residual_risk DESC");
        $stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
        $stmt->bindParam(":medium", $medium, PDO::PARAM_STR, 4);
        $stmt->bindParam(":low", $low, PDO::PARAM_STR, 4);
        $stmt->bindParam(":very_high_display_name", $very_high_display_name, PDO::PARAM_STR);
        $stmt->bindParam(":high_display_name", $high_display_name, PDO::PARAM_STR);
        $stmt->bindParam(":medium_display_name", $medium_display_name, PDO::PARAM_STR);
        $stmt->bindParam(":low_display_name", $low_display_name, PDO::PARAM_STR);
        $stmt->bindParam(":insignificant_display_name", $insignificant_display_name, PDO::PARAM_STR);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }
    else
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Query the database
        $array = strip_no_access_open_risk_summary($veryhigh, $high, $medium, $low);
    }

    // Close the database connection
    db_close($db);

    // Reverse the order of the array
    $array = array_reverse($array);

    // If the array is empty
    if (empty($array))
    {
            $data[] = array("No Data Available", 0);
    }
    // Otherwise
    else
    {
        // Initialize veryhigh, high, medium, low, and insignificant
        $veryhigh = false;
        $high = false;
        $medium = false;
        $low = false;
        $insignificant = false;
        $color_array = array();
        
        $risk_levels = get_risk_levels();
        $risk_levels_by_color = array();
        foreach($risk_levels as $risk_level){
            $risk_levels_by_color[$risk_level['name']] = $risk_level;
        }
        // Create the data array
        foreach ($array as $row)
        {
            $data[] = array($row['level'], (int)$row['num']);

            // If we have at least one very high risk
            if ($row['level'] == $very_high_display_name && $veryhigh != true)
            {
                $veryhigh = true;

                // Add red to the color array
                $color_array[] = $risk_levels_by_color["Very High"]["color"];
            }
            // If we have at least one high risk
            else if ($row['level'] == $high_display_name && $high != true)
            {
                $high = true;

                // Add red to the color array
                $color_array[] = $risk_levels_by_color["High"]["color"];
            }
            // If we have at least one medium risk
            else if ($row['level'] == $medium_display_name && $medium != true)
            {
                $medium = true;

                // Add orange to the color array
                $color_array[] = $risk_levels_by_color["Medium"]["color"];
            }
            // If we have at least one low risk
            else if ($row['level'] == $low_display_name && $low != true)
            {
                $low = true;

                // Add yellow to the color array
                $color_array[] = $risk_levels_by_color["Low"]["color"];
            }
            else if ($row['level'] == $insignificant_display_name && $insignificant != true)
            {
                $insignificant = true;

                // Add lightgrey to the color array
                $color_array[] = "lightgrey";
            }
        }

        $chart->plotOptions->pyramid->colors = $color_array;

        $chart->series[] = array(
                'name' => "Risk Pyramid",
                'data' => $data);
    }

//    $chart->printScripts();
    echo "<div id=\"risk_pyramid_chart\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("risk_pyramid_chart");
    echo "</script>\n";
}

/**********************************
 * FUNCTION: OPEN RISK LEVEL PIE *
 * $teams: ex: 1:2:3:4
 **********************************/
function open_risk_level_pie($title = null, $teams = false, $score_used='inherent') {

    global $lang, $escaper;

    $chart = new Highchart();

    $chart->chart->renderTo = "open_risk_level_pie";
    $chart->chart->plotBackgroundColor = null;
    $chart->chart->plotBorderWidth = null;
    $chart->chart->plotShadow = false;
    $chart->title->text = $title;

    $chart->tooltip->formatter = new HighchartJsExpr("function() {
    return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

    $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
    location.href = 'dynamic_risk_report.php?status=0&group=1&sort=0'; }");

    $chart->plotOptions->pie->allowPointSelect = 1;
    $chart->plotOptions->pie->cursor = "pointer";
    $chart->plotOptions->pie->dataLabels->enabled = false;
    $chart->plotOptions->pie->showInLegend = 1;
    $chart->credits->enabled = false;

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("SELECT * from `risk_levels` ORDER BY value DESC");
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
            FROM `risk_scoring` scoring
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
            FROM `risk_scoring` scoring
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
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the array is empty
    if (empty($array)) {
        $data[] = array("No Data Available", 0);
    }
    // Otherwise
    else {
        // Initialize veryhigh, high, medium, low, and insignificant
        $veryhigh = false;
        $high = false;
        $medium = false;
        $low = false;
        $insignificant = false;
        $color_array = array();

        $risk_levels = get_risk_levels();
        $colors = array();
        foreach($risk_levels as $risk_level){
            $colors[$risk_level['name']] = $risk_level['color'];
        }

        // Create the data array
        foreach ($array as $row) {
            $data[] = array($row['level'], (int)$row['num']);

            // If we have at least one very high risk
            if ($row['level'] == $very_high_display_name && $veryhigh != true) {
                $veryhigh = true;

                // Add red to the color array
                $color_array[] = $colors["Very High"];
            }
            // If we have at least one high risk
            else if ($row['level'] == $high_display_name && $high != true) {
                $high = true;

                // Add red to the color array
                $color_array[] = $colors["High"];
            }
            // If we have at least one medium risk
            else if ($row['level'] == $medium_display_name && $medium != true) {
                $medium = true;

                // Add orange to the color array
                $color_array[] = $colors["Medium"];
            }
            // If we have at least one low risk
            else if ($row['level'] == $low_display_name && $low != true) {
                $low = true;

                // Add yellow to the color array
                $color_array[] = $colors["Low"];
            } else if ($row['level'] == $insignificant_display_name && $insignificant != true) {
                $insignificant = true;

                // Add lightgrey to the color array
                $color_array[] = "lightgrey";
            }
        }

        // Add black to color array for insignificant
        $color_array[] = "lightgrey";

        $chart->plotOptions->pie->colors = $color_array;

        $data = encode_data_before_display($data);

        $chart->series[] = array('type' => "pie",
                'name' => "Level",
                'data' => $data);
    }

    echo "<div id=\"open_risk_level_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_level_pie");
    echo "</script>\n";
}

/**********************************
 * FUNCTION: OPEN RISK STATUS PIE *
 **********************************/
function open_risk_status_pie($array, $title = null, $teams = false)
{
    $chart = new Highchart();

    $chart->chart->renderTo = "open_risk_status_pie";
    $chart->chart->plotBackgroundColor = null;
    $chart->chart->plotBorderWidth = null;
    $chart->chart->plotShadow = false;
    $chart->title->text = $title;

    $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=2&sort=0'; }");

    $chart->plotOptions->pie->allowPointSelect = 1;
    $chart->plotOptions->pie->cursor = "pointer";
    $chart->plotOptions->pie->dataLabels->enabled = false;
    $chart->plotOptions->pie->showInLegend = 1;
    $chart->credits->enabled = false;

    // If the array is empty
    if (empty($array))
    {
        $data[] = array("No Data Available", 0);
    }
    // Otherwise
    else
    {
        // Set the sort value
        $sort = "status";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

        // Create the pie chart
        $chart->series[] = array('type' => "pie",
            'name' => $sort,
            'data' => $data);
    }

    echo "<div id=\"open_risk_status_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_status_pie");
    echo "</script>\n";
}

/************************************
 * FUNCTION: CLOSED RISK REASON PIE *
 ************************************/
function closed_risk_reason_pie($title = null, $teams = false)
{
    $teams_query = generate_teams_query($teams, "rtt.team_id");

    $chart = new Highchart();

    $chart->chart->renderTo = "closed_risk_reason_pie";
    $chart->chart->plotBackgroundColor = null;
    $chart->chart->plotBorderWidth = null;
    $chart->chart->plotShadow = false;
    $chart->title->text = $title;

    $chart->tooltip->formatter = new HighchartJsExpr("function() {
    return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

    $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
    location.href = 'dynamic_risk_report.php?status=1&group=0&sort=0'; }");

    $chart->plotOptions->pie->allowPointSelect = 1;
    $chart->plotOptions->pie->cursor = "pointer";
    $chart->plotOptions->pie->dataLabels->enabled = false;
    $chart->plotOptions->pie->showInLegend = 1;
    $chart->credits->enabled = false;

    // Open the database connection
    $db = db_open();

    // If the team separation extra is not enabled
    if (!team_separation_extra())
    {
        // Query the database
        $stmt = $db->prepare(" SELECT name, COUNT(*) as num FROM (SELECT a.close_reason, b.name, MAX(closure_date) FROM `closures` a JOIN `close_reason` b ON a.close_reason = b.value JOIN `risks` c ON a.risk_id = c.id LEFT JOIN risk_to_team rtt ON c.id=rtt.risk_id WHERE c.status = \"Closed\" AND {$teams_query} GROUP BY a.risk_id ORDER BY b.name DESC) AS close GROUP BY name ORDER BY COUNT(*) DESC; ");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Query the database
        $array = strip_no_access_risk_pie('close_reason', $teams);
    }

    // Close the database connection
    db_close($db);

    // If the array is empty
    if (empty($array))
    {
            $data[] = array("No Data Available", 0);
    }
    // Otherwise
    else
    {
        // Create the data array
        foreach ($array as $row)
        {
            $data[] = array($row['name'], (int)$row['num']);
        }

        $chart->series[] = array('type' => "pie",
                    'name' => "Status",
                    'data' => $data);
    }

    echo "<div id=\"closed_risk_reason_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("closed_risk_reason_pie");
    echo "</script>\n";
}

/************************************
 * FUNCTION: OPEN RISK LOCATION PIE *
 ************************************/
function open_risk_location_pie($array, $title = null)
{
    global $escaper, $lang;
    
    $chart = new Highchart();

    $chart->chart->renderTo = "open_risk_location_pie";
    $chart->chart->plotBackgroundColor = null;
    $chart->chart->plotBorderWidth = null;
    $chart->chart->plotShadow = false;
    $chart->title->text = $title;

    $chart->tooltip->formatter = new HighchartJsExpr("function() {
    return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

    $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function(e) {
    location.href = 'dynamic_risk_report.php?status=0&sort=0&locations_filter[]=' + e.point.options.value; }");

    $chart->plotOptions->pie->allowPointSelect = 1;
    $chart->plotOptions->pie->cursor = "pointer";
    $chart->plotOptions->pie->dataLabels->enabled = false;
    $chart->plotOptions->pie->showInLegend = 1;
    $chart->credits->enabled = false;

    // If the array is empty
    if (empty($array))
    {
            $data[] = array("No Data Available", 0);
    }
    // Otherwise
    else
    {
        // Set the sort value
        $sort = "location";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $rows = count_array_values($array, $sort);
        
        $data = [];
        foreach($rows as $nameAndCountArr)
        {
            $value = get_value_by_name("location", $nameAndCountArr[0], "");
            $value || $value="";
            $data[] = array(
                "name" => $escaper->escapeHtml($nameAndCountArr[0]),
                "y" => $nameAndCountArr[1],
                "value" => $value,
            );
        }

        // Create the pie chart
        $chart->series[] = array(
            'type' => "pie",
            'name' => $sort,
            'data' => $data
        );
    }

    echo "<div id=\"open_risk_location_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_location_pie");
    echo "</script>\n";
}

/**********************************
 * FUNCTION: OPEN RISK SOURCE PIE *
 **********************************/
function open_risk_source_pie($array, $title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_source_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=4&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Set the sort value
                $sort = "source";

                // Sort the array
                $array = sort_array($array, $sort);

                // Count the array by status
                $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

                // Create the pie chart
                $chart->series[] = array('type' => "pie",
                        'name' => $sort,
                        'data' => $data);
        }

    echo "<div id=\"open_risk_source_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_source_pie");
    echo "</script>\n";
}

/************************************
 * FUNCTION: OPEN RISK CATEGORY PIE *
 ************************************/
function open_risk_category_pie($array, $title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_category_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=5&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Set the sort value
                $sort = "category";

                // Sort the array
                $array = sort_array($array, $sort);

                // Count the array by status
                $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

                // Create the pie chart
                $chart->series[] = array('type' => "pie",
                        'name' => $sort,
                        'data' => $data);
        }

    echo "<div id=\"open_risk_category_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_category_pie");
    echo "</script>\n";
}

/********************************
 * FUNCTION: OPEN RISK TEAM PIE *
 ********************************/
function open_risk_team_pie($array, $title = null)
{
    $chart = new Highchart();

    $chart->chart->renderTo = "open_risk_team_pie";
    $chart->chart->plotBackgroundColor = null;
    $chart->chart->plotBorderWidth = null;
    $chart->chart->plotShadow = false;
    $chart->title->text = $title;

    $chart->tooltip->formatter = new HighchartJsExpr("function() {
    return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

    $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
    location.href = 'dynamic_risk_report.php?status=0&group=6&sort=0'; }");

    $chart->plotOptions->pie->allowPointSelect = 1;
    $chart->plotOptions->pie->cursor = "pointer";
    $chart->plotOptions->pie->dataLabels->enabled = false;
    $chart->plotOptions->pie->showInLegend = 1;
    $chart->credits->enabled = false;

    // If the array is empty
    if (empty($array))
    {
        $data[] = array("No Data Available", 0);
    }
    // Otherwise
    else
    {
        // Set the sort value
        $sort = "team";

        // Sort the array
        $array = sort_array($array, $sort);

        // Count the array by status
        $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

        // Create the pie chart
        $chart->series[] = array(
            'type' => "pie",
            'name' => $sort,
            'data' => $data
        );
    }

    echo "<div id=\"open_risk_team_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_team_pie");
    echo "</script>\n";
}

/**************************************
 * FUNCTION: OPEN RISK TECHNOLOGY PIE *
 **************************************/
function open_risk_technology_pie($array, $title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_technology_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=7&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Set the sort value
                $sort = "technology";

                // Sort the array
                $array = sort_array($array, $sort);

                // Count the array by status
                $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

                // Create the pie chart
                $chart->series[] = array('type' => "pie",
                        'name' => $sort,
                        'data' => $data);
        }

    echo "<div id=\"open_risk_technology_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_technology_pie");
    echo "</script>\n";
}

/**************************************
 * FUNCTION: OPEN RISK OWNER PIE *
 **************************************/
function open_risk_owner_pie($array, $title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_owner_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=8&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Set the sort value
                $sort = "owner";

                // Sort the array
                $array = sort_array($array, $sort);

                // Count the array by status
                $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

                // Create the pie chart
                $chart->series[] = array('type' => "pie",
                        'name' => $sort,
                        'data' => $data);
        }

    echo "<div id=\"open_risk_owner_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_owner_pie");
    echo "</script>\n";
}

/******************************************
 * FUNCTION: OPEN RISK OWNERS MANAGER PIE *
 ******************************************/
function open_risk_owners_manager_pie($array, $title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_owners_manager_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=9&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // If the array is empty
        if (empty($array))
        {
            $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
            // Set the sort value
            $sort = "manager";

            // Sort the array
            $array = sort_array($array, $sort);

            // Count the array by status
            $data = count_array_values($array, $sort);

            $data = encode_data_before_display($data);

            // Create the pie chart
            $chart->series[] = array('type' => "pie",
                    'name' => $sort,
                    'data' => $data);
        }

    echo "<div id=\"open_risk_owners_manager_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_owners_manager_pie");
    echo "</script>\n";
}

/******************************************
 * FUNCTION: OPEN RISK SCORING METHOD PIE *
 ******************************************/
function open_risk_scoring_method_pie($array, $title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_scoring_method_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=10&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Set the sort value
                $sort = "scoring_method";

                // Sort the array
                $array = sort_array($array, $sort);

                // Count the array by status
                $data = count_array_values($array, $sort);

        $data = encode_data_before_display($data);

                // Create the pie chart
                $chart->series[] = array('type' => "pie",
                        'name' => $sort,
                        'data' => $data);
        }

    echo "<div id=\"open_risk_scoring_method_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_scoring_method_pie");
    echo "</script>\n";
}

/*********************************
 * FUNCTION: OPEN MITIGATION PIE *
 *********************************/
function open_mitigation_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_mitigation_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=2&group=2&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // If team separation is not enabled
        if (!team_separation_extra())
        {
                // Open the database connection
                $db = db_open();

                // Query the database
        $stmt = $db->prepare("SELECT id, CASE WHEN mitigation_id = 0 THEN 'Unmitigated' WHEN mitigation_id != 0 THEN 'Mitigated' END AS name FROM `risks` WHERE status != \"Closed\" ORDER BY name");
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

                // Get the open mitigation pie with risks stripped
                $array = strip_open_mitigation_pie();
        }

        // Set the defaults
        $current_type = "";
        $grouped_array = array();
        $counter = -1;

        foreach ($array as $row)
        {
                // If the row name is not the current row
                if ($row['name'] != $current_type)
                {
                        // Increment the counter
                        $counter = $counter + 1;

                        // Add the value to the grouped array
                        $grouped_array[$counter]['name'] = $row['name'];
                        $grouped_array[$counter]['num'] = 1;

                        // Set the current type
                        $current_type = $row['name'];
                }
                else
                {
                        // Add the value to the grouped array
                        $grouped_array[$counter]['num'] = $grouped_array[$counter]['num'] + 1;
                }
        }

        $array = $grouped_array;

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Create the data array
                foreach ($array as $row)
                {
                        $data[] = array($row['name'], (int)$row['num']);

            if ($row['name'] == "Mitigated")
            {
                $color_array[] = "green";
            }
            else if ($row['name'] == "Unmitigated")
            {
                $color_array[] = "red";
            }
                }

                $chart->plotOptions->pie->colors = $color_array;

        $data = encode_data_before_display($data);

                $chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
        }

    echo "<div id=\"open_mitigation_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_mitigation_pie");
    echo "</script>\n";
}

/*****************************
 * FUNCTION: OPEN REVIEW PIE *
 *****************************/
function open_review_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_review_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=2&group=2&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // If team separation is not enabled
        if (!team_separation_extra())
        {
                // Open the database connection
                $db = db_open();

                // Query the database
        $stmt = $db->prepare("SELECT id, CASE WHEN mgmt_review = 0 THEN 'Unreviewed' WHEN mgmt_review != 0 THEN 'Reviewed' END AS name FROM `risks` WHERE status != \"Closed\" ORDER BY name");
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

                // Get the open review pie with risks stripped
                $array = strip_open_review_pie();
        }

        // Set the defaults
        $current_type = "";
        $grouped_array = array();
        $counter = -1;

        foreach ($array as $row)
        {
                // If the row name is not the current row
                if ($row['name'] != $current_type)
                {
                        // Increment the counter
                        $counter = $counter + 1;

                        // Add the value to the grouped array
                        $grouped_array[$counter]['name'] = $row['name'];
                        $grouped_array[$counter]['num'] = 1;

                        // Set the current type
                        $current_type = $row['name'];
                }
                else
                {
                        // Add the value to the grouped array
                        $grouped_array[$counter]['num'] = $grouped_array[$counter]['num'] + 1;
                }
        }

        $array = $grouped_array;

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Create the data array
                foreach ($array as $row)
                {
                        $data[] = array($row['name'], (int)$row['num']);

            if ($row['name'] == "Reviewed")
            {
                $color_array[] = "green";
            }
            else if ($row['name'] == "Unreviewed")
            {
                $color_array[] = "red";
            }
                }

        $chart->plotOptions->pie->colors = $color_array;

        $data = encode_data_before_display($data);

                $chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
        }

    echo "<div id=\"open_review_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_review_pie");
    echo "</script>\n";
}

/*****************************
 * FUNCTION: OPEN CLOSED PIE *
 *****************************/
function open_closed_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_closed_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=2&group=2&sort=0'; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

    // If team separation is not enabled
    if (!team_separation_extra())
    {
        // Open the database connection
        $db = db_open();

            // Query the database
        $stmt = $db->prepare("SELECT id, CASE WHEN status = \"Closed\" THEN 'Closed' WHEN status != \"Closed\" THEN 'Open' END AS name FROM `risks` ORDER BY name");
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

        // Get the open pie with risks stripped
        $array = strip_open_closed_pie();
    }

    // Set the defaults
    $current_type = "";
    $grouped_array = array();
    $counter = -1;

    foreach ($array as $row)
    {
        // If the row name is not the current row
        if ($row['name'] != $current_type)
        {
            // Increment the counter
            $counter = $counter + 1;

            // Add the value to the grouped array
            $grouped_array[$counter]['name'] = $row['name'];
            $grouped_array[$counter]['num'] = 1;

            // Set the current type
            $current_type = $row['name'];
        }
        else
        {
            // Add the value to the grouped array
            $grouped_array[$counter]['num'] = $grouped_array[$counter]['num'] + 1;
        }
    }

    $array = $grouped_array;

        // If the array is empty
        if (empty($array))
        {
                $data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
                // Create the data array
                foreach ($array as $row)
                {
                        $data[] = array($row['name'], (int)$row['num']);

                        if ($row['name'] == "Closed")
                        {
                                $color_array[] = "green";
                        }
                        else if ($row['name'] == "Open")
                        {
                                $color_array[] = "red";
                        }
                }

                $chart->plotOptions->pie->colors = $color_array;

        $data = encode_data_before_display($data);

                $chart->series[] = array('type' => "pie",
                        'name' => "Status",
                        'data' => $data);
        }

    echo "<div id=\"open_closed_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_closed_pie");
    echo "</script>\n";
}

/*************************************
 * FUNCTION: GET REVIEW NEEDED TABLE *
 *************************************/
function get_review_needed_table()
{
    global $lang;
    global $escaper;

    // Get risks marked as consider for projects
    $risks = get_risks(3);

    // Initialize the reviews array
    $reviews = array();

    // Parse through each row in the array
    foreach ($risks as $key => $row)
    {
        // Create arrays for each value
        $risk_id[$key] = (int)$row['id'];
        $subject[$key] = $row['subject'];
        $status[$key] = $row['status'];
        $calculated_risk[$key] = $row['calculated_risk'];
        $color[$key] = get_risk_color($row['calculated_risk']);
        $risk_level = get_risk_level_name($row['calculated_risk']);
        $residual_risk_level = get_risk_level_name($row['residual_risk']);
//        $dayssince[$key] = dayssince($row['submission_date']);
        $dayssince[$key] = $row['days_open'];

        // If next_review_date_uses setting is Residual Risk.
        if(get_setting('next_review_date_uses') == "ResidualRisk")
        {
            $next_review[$key] = next_review($residual_risk_level, $risk_id[$key], $row['next_review'], false);
            $next_review_html[$key] = next_review($residual_risk_level, $row['id'], $row['next_review']);
        }
        // If next_review_date_uses setting is Inherent Risk.
        else
        {
            $next_review[$key] = next_review($risk_level, $risk_id[$key], $row['next_review'], false);
            $next_review_html[$key] = next_review($risk_level, $row['id'], $row['next_review']);
        }
        
        // Create a new array of reviews
        $reviews[] = array('risk_id' => $risk_id[$key], 'subject' => $subject[$key], 'status' => $status[$key], 'calculated_risk' => $calculated_risk[$key], 'color' => $color[$key], 'dayssince' => $dayssince[$key], 'next_review' => $next_review[$key], 'next_review_html' => $next_review_html[$key]);

        // Sort the reviews array by next_review
        array_multisort($next_review, SORT_DESC, SORT_STRING, $calculated_risk, SORT_DESC, SORT_NUMERIC, $reviews);
    }

    // Start with an empty review status;
    $review_status = "";

    // For each risk
    foreach ($reviews as $review)
    {
        $risk_id = $review['risk_id'];
        $subject = $review['subject'];
        $status = $review['status'];
        $calculated_risk = $review['calculated_risk'];
        $color = $review['color'];
        $dayssince = $review['dayssince'];
        $next_review = $review['next_review'];
        $next_review_html = $review['next_review_html'];

        // If we have a new review status and its not a date
        if (($review_status != $next_review) && (!preg_match('/\d{4}/', $review_status)))
        {
            // If its not the first risk
            if ($review_status != "")
            {
                // End the previous table
                echo "</tbody>\n";
                echo "</table>\n";
                echo "<br />\n";

            }

            // Set the new review status
            $review_status = $next_review;

            // If the review status is not a date
            if (!preg_match('/\d{4}/', $review_status))
            {
                // Start the new table
                echo "<table class=\"table table-bordered table-condensed sortable\">\n";
                echo "<thead>\n";
                echo "<tr>\n";
                echo "<th bgcolor=\"#0088CC\" colspan=\"6\"><center>". $escaper->escapeHtml($review_status) ."</center></th>\n";
                echo "</tr>\n";
                echo "<tr>\n";
                echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
                echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
                echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
                echo "<th align=\"center\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
                echo "<th align=\"center\" width=\"100px\">". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
                echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['NextReviewDate']) ."</th>\n";
                echo "</tr>\n";
                echo "</thead>\n";
                echo "<tbody>\n";
            }
        }

        // If the review status is not a date
        if (!preg_match('/\d{4}/', $review_status)){
            echo "<tr>\n";
            echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
            echo "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($status) . "</td>\n";
            echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
            echo "<td align=\"center\" class=\"risk-cell\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($calculated_risk) . "<span class=\"risk-color\" style=\"background-color:{$color}\"></span></td>\n";
            echo "<td align=\"center\" width=\"100px\">" . $escaper->escapeHtml($dayssince) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $next_review_html . "</td>\n";
            echo "</tr>\n";
        }
    }
}

/************************************
 * FUNCTION: RISKS AND ASSETS TABLE *
 ************************************/
function risks_and_assets_table($report)
{
    global $lang;
    global $escaper;

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

    $risk_levels = get_risk_levels();

    // Open the database
    $db = db_open();

    // If risks by asset
    if ($report == 0) {
        $stmt = $db->prepare("
            SELECT
                CONCAT(u.id, '_', u._type) AS gr_id,
                GROUP_CONCAT(DISTINCT `t`.`name` SEPARATOR ', ') AS asset_teams,
                `loc`.`name` AS asset_location,
                rsk_loc.name AS risk_location,
                GROUP_CONCAT(DISTINCT rsk_team.name SEPARATOR ', ') AS risk_teams,
                u.*
            FROM (
                SELECT
                    a.id AS id,
                    r.id AS risk_id,
                    a.name AS name,
                    a.value AS asset_value,
                    av.max_value AS max_value,
                    a.location AS asst_location,
                    a.teams AS asst_teams,
                    r.status,
                    r.subject,
                    r.submission_date,
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
                    ag.name AS name,
                    null AS asset_value,
                    SUM(`av`.`max_value`) as max_value,
                    null AS asst_location,
                    null AS asst_teams,
                    r.status,
                    r.subject,
                    r.submission_date,
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
            GROUP BY
                gr_id, u.risk_id
            ORDER BY
                u.max_value DESC,
                u.name,
                u.calculated_risk DESC,
                u.risk_id;
        ");
        $stmt->execute();

        // Store the results in the rows array
        $rows = $stmt->fetchAll(PDO::FETCH_GROUP);    

        foreach($rows as $gr_id => $group) {

            if ($separation) {
                $tmp = [];
                
                foreach($group as $key => $item)
                    if (extra_grant_access($_SESSION['uid'], (int)$item['risk_id'] + 1000))
                        $tmp[] = $item;

                if (empty($tmp))
                    continue;

                $group = $tmp;
            }
            
            preg_match('/^([\d]+)_(group|asset)$/', $gr_id, $matches);
            list(, $id, $type) = $matches;

            $name = $type == 'asset' ? try_decrypt($group[0]['name']) : $group[0]['name'];
            $calculated_risk = $group[0]['calculated_risk'];
            $color = get_risk_color_from_levels($calculated_risk, $risk_levels);
            
            // Display the table header
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            if ($type == 'asset') {
                $asset_value = $group[0]['asset_value'];
                $asset_location = isset($group[0]['asset_location']) ? $group[0]['asset_location'] : "N/A";
                $asset_teams = isset($group[0]['asset_teams']) ? $group[0]['asset_teams'] : "N/A";
                echo "<th style=\"background-color: " .$escaper->escapeHtml($color). "\" colspan=\"7\">
                        <center>
                            " . $escaper->escapeHtml($lang['AssetName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($name) . "<br />
                            " . $escaper->escapeHtml($lang['AssetValue']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml(get_asset_value_by_id($asset_value)) . "<br />
                            " . $escaper->escapeHtml($lang['AssetRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($calculated_risk) ."<br />
                            " . $escaper->escapeHtml($lang['AssetSiteLocation']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_location) . "<br />
                            " . $escaper->escapeHtml($lang['AssetTeams']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_teams) . "<br />
                        </center>
                    </th>\n";
            } else {
                $max_value = $group[0]['max_value'];
                echo "<th style=\"background-color: " .$escaper->escapeHtml($color). "\" colspan=\"7\">
                        <center>
                            " . $escaper->escapeHtml($lang['AssetGroupName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($name) . "<br />
                            " . $escaper->escapeHtml($lang['GroupMaximumQuantitativeLoss']) . ":&nbsp;&nbsp;$" . $escaper->escapeHtml(number_format($max_value)) . "<br />
                            " . $escaper->escapeHtml($lang['AssetGroupRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($calculated_risk) ."
                        </center>
                    </th>\n";
            }
            echo "</tr>\n";
            echo "<tr>\n";
            echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
            echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
            echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
            echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['SiteLocation']) ."</th>\n";
            echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Teams']) ."</th>\n";
            echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['InherentRisk']) ."</th>\n";
            echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";
            
            foreach($group as $row) {
                
                // Get the variables for the row
                $risk_id = (int)$row['risk_id'];
                $risk_location = (isset($row['risk_location']) ? $row['risk_location'] : "N/A");
                $risk_teams = (isset($row['risk_teams']) ? $row['risk_teams'] : "N/A");
                $status = $row['status'];
                $subject = try_decrypt($row['subject']);
                $calculated_risk = $row['calculated_risk'];
                $color = get_risk_color_from_levels($calculated_risk, $risk_levels);
                $dayssince = $row['days_open'];

                // Display the individual asset/asset group information
                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a target='_blank' href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                echo "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($status) . "</td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                echo "<td align=\"left\" width=\"200px\">" . $escaper->escapeHtml($risk_location) . "</td>\n";
                echo "<td align=\"left\" width=\"200px\">" . $escaper->escapeHtml($risk_teams) . "</td>\n";
                echo "<td align=\"center\" class=\"risk-cell\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($calculated_risk) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></td>\n";
                echo "<td align=\"center\" width=\"100px\">" . $escaper->escapeHtml($dayssince) . "</td>\n";
                echo "</tr>\n";
            }

            echo "</tbody>\n";
            echo "</table>\n";
        }
    }

    // If assets by risk
    elseif ($report == 1) {
        
        $separation_query = $separation ? get_user_teams_query("rsk", true) : "";
        
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
                GROUP_CONCAT(DISTINCT `t`.`name` SEPARATOR ', ') AS asset_teams,
                GROUP_CONCAT(DISTINCT `ag`.`name` SEPARATOR ', ') AS asset_groups
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
            {$separation_query}
            GROUP BY
                `u`.`risk_id`, `u`.`asset_id`
            ORDER BY
                `u`.`calculated_risk` DESC,
                `u`.`risk_id`,
                `u`.`asset_value` DESC,
                `u`.`asset_id`;
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();

        // Store the results in the rows array
        $rows = $stmt->fetchAll(PDO::FETCH_GROUP);    

        foreach($rows as $risk_id => $group){

            $status = $group[0]['status'];
            $subject = try_decrypt($group[0]['subject']);
            $calculated_risk = $group[0]['calculated_risk'];

            // Get the risk's asset valuation
            $asset_valuation = asset_valuation_for_risk_id($risk_id);

            // Get the risk color
            $color = get_risk_color_from_levels($calculated_risk, $risk_levels);
            $level_name = get_risk_level_name_from_levels($calculated_risk, $risk_levels);

            // Display the table header
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th style=\"background-color:" . $escaper->escapeHtml($color) . "\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" colspan=\"6\">
                    <center>
                        <font color=\"#000000\">
                            " . $escaper->escapeHtml($lang['RiskId']) . ":&nbsp;&nbsp;<a target='_blank' href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\" style=\"color:#000000\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a>
                            <br />" . $escaper->escapeHtml($lang['Subject']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($subject) . "
                            <br />" . $escaper->escapeHtml($lang['InherentRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($calculated_risk) . "&nbsp;&nbsp;(" . $escaper->escapeHtml($level_name) . ")
                        </font>
                    </center>
                  </th>\n";
            echo "</tr>\n";              
            echo "<tr>\n";
            echo "<th align=\"left\" width='30%'>". $escaper->escapeHtml($lang['AssetName']) ."</th>\n";
            echo "<th align=\"left\" width='10%'>". $escaper->escapeHtml($lang['IPAddress']) ."</th>\n";
            echo "<th align=\"left\" width='15%'>". $escaper->escapeHtml($lang['SiteLocation']) ."</th>\n";
            echo "<th align=\"left\" width='15%'>". $escaper->escapeHtml($lang['Teams']) ."</th>\n";
            echo "<th align=\"left\" width='15%'>". $escaper->escapeHtml($lang['AssetGroups']) ."</th>\n";
            echo "<th align=\"left\" width='15%'>". $escaper->escapeHtml($lang['AssetValuation']) ."</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            foreach($group as $row){
                // Get the variables for the row
                $asset_id = (int)$row['asset_id'];
                $asset_ip = (isset($row['asset_ip']) ? try_decrypt($row['asset_ip']) : "N/A");
                $asset_ip = ($asset_ip != "" ? $asset_ip : "N/A");
                $asset_name = (isset($row['asset_name']) ? try_decrypt($row['asset_name']) : "N/A");
                $asset_value = $row['asset_value'];
                $asset_location = isset($row['asset_location']) ? $row['asset_location'] : "N/A";
                $asset_teams = isset($row['asset_teams']) ? $row['asset_teams'] : "N/A";
                $asset_groups = isset($row['asset_groups']) ? $row['asset_groups'] : "N/A";

                // Display the individual asset information
                echo "<tr>\n";
                echo "<td align='left' width='50px'>" . $escaper->escapeHtml($asset_name) . "</td>\n";
                echo "<td align='left' width='50px'>" . $escaper->escapeHtml($asset_ip) . "</td>\n";
                echo "<td align='left' width='50px'>" . $escaper->escapeHtml($asset_location) . "</td>\n";
                echo "<td align='left' width='50px'>" . $escaper->escapeHtml($asset_teams) . "</td>\n";
                echo "<td align='left' width='50px'>" . $escaper->escapeHtml($asset_groups) . "</td>\n";
                echo "<td align='left' width='50px'>" . $escaper->escapeHtml(get_asset_value_by_id($asset_value)) . "</td>\n";
                echo "</tr>\n";
            }

            echo "<tr><td style=\"background-color:" . $escaper->escapeHtml($color) . "\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" colspan=\"6\"></td></tr>\n";
            echo "<tr>\n";
            echo "<td style=\"background-color: lightgrey\" align=\"left\" width=\"50px\" colspan=\"5\"><b>" . $escaper->escapeHtml($lang['MaximumQuantitativeLoss']) . "</b></td>\n";
            echo "<td style=\"background-color: lightgrey\" align=\"left\" width=\"50px\"><b>$" . $escaper->escapeHtml(number_format($asset_valuation)) . "</b></td>\n";
            echo "</tr>\n";
            echo "</tbody>\n";
            echo "</table>\n";
        }
    }

    // Close the database
    db_close($db);
}

/*********************************************
 * FUNCTION: GET GROUP_NAME FOR DYNAMIC RISK *
 *********************************************/
function get_group_name_for_dynamic_risk($group, $sort_name)
{
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
function get_risks_by_table($status, $sort, $group, $column_id=true, $column_status=false, $column_subject=true, $column_reference_id=false, $column_regulation=false, $column_control_number=false, $column_location=false, $column_source=false, $column_category=false, $column_team=false, $column_additional_stakeholders=false, $column_technology=false, $column_owner=false, $column_manager=false, $column_submitted_by=false, $column_scoring_method=false, $column_calculated_risk=true, $column_residual_risk=true, $column_submission_date=true, $column_review_date=false, $column_project=false, $column_mitigation_planned=true, $column_management_review=true, $column_days_open=false, $column_next_review_date=false, $column_next_step=false, $column_affected_assets=false, $column_planning_strategy=false, $column_planning_date=false, $column_mitigation_effort=false, $column_mitigation_cost=false, $column_mitigation_owner=false, $column_mitigation_team=false, $column_mitigation_accepted=false, $column_mitigation_date=false, $column_mitigation_controls=false, $column_risk_assessment=false, $column_additional_notes=false, $column_current_solution=false, $column_security_recommendations=false, $column_security_requirements=false, $column_risk_tags=false, $column_closure_date=false)
{
    global $lang;
    global $escaper;
    
    $rowCount = 0;
    
    // Get group name from $group
    list($group_name, $order_query) = get_group_name_for_dynamic_risk($group, "");
    
    echo "
        <style>
            #risk-table-container .multiselect-native-select{
                max-width: 600px;
                display: block;
            }
        </style>
    ";
    
    // If Group By is not selected or Import/Export extra is disabled, hide download button by group
    if ($group_name == "none" || !import_export_extra())
    {
        echo "
            <style>
                .download-by-group{
                    display: none;
                }
            </style>
        ";
    }
    
    // If the group name is none
//    if ($group_name == "none" || !$rowCount)
    if ($group_name == "none")
    {
        // Display the table header
        echo "<table name=\"risks\" id=\"risks\" data-group='' class=\"table risk-datatable table-bordered table-striped table-condensed  table-margin-top\" style='width: 100%'>\n";
        echo "<thead>\n";
        echo "<tr class='main'>\n";

        // Header columns go here
        get_header_columns(false, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_additional_stakeholders, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_residual_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_planning_date, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_mitigation_accepted, $column_mitigation_date, $column_mitigation_controls, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements, $column_risk_tags, $column_closure_date);

        echo "</tr>\n";
        echo "<tr class='filter'>\n";
        // Header columns go here
        get_header_columns(false, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_additional_stakeholders, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_residual_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_planning_date, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_mitigation_accepted, $column_mitigation_date, $column_mitigation_controls, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements, $column_risk_tags, $column_closure_date);
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";
    }
    else
    {
        // In getting table structures, disregard column_filters
        $risks = get_risks_only_dynamic($need_total_count=false, $status, $sort, 0, [], $rowCount, 0, -1);
        $displayed_group_names = [];

        // For each risk in the risks array
        foreach ($risks as $risk)
        {

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
            if (!$risk['submission_date'] || stripos($risk['submission_date'], "0000-00-00") !== false)
            {
                // Set the review date to empty
                $month_submitted = $lang['Unassigned'];
            }
            else
            {
                $month_submitted = date('Y F', strtotime($risk['submission_date']));
            }

            // If the group name is not none
            if ($group_name != "none")
            {
                $initial_group_value = trim(${$group_name});
                
                // Check comma splitted group
                if($group_name == "team" || $group_name == "technology")
                {
                    if($initial_group_value)
                    {
                        $group_values_including_empty = array_map("trim", explode(",", $initial_group_value));
                        $group_values = [];
                        foreach($group_values_including_empty as $val){
                            // Remove empty values from group_values
                            if($val) $group_values[] = $val;
                        }
                    }
                    else
                    {
                        $group_values = [""];
                    }
                }
                else
                {
                    $group_values = [$initial_group_value];
                }

                foreach($group_values as $group_value)
                {
                    switch($group_name){
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
                    if ($group_value == "")
                    {
                        // Current group is Unassigned
                        $group_value = $lang['Unassigned'];
                    }

                    // If the group is not the current group
//                    if ($group_value != $current_group && !in_array($group_value, $displayed_group_names))
                    if (!in_array($group_value, $displayed_group_names))
                    {
                        // If this is not the first group
//                        if ($current_group != "")
//                        {
//                                echo "</tbody>\n";
//                            echo "</table>\n";
//                            echo "<br />\n";
//                        }

                        $displayed_group_names[] = $group_value;
                        
                        // Display the table header
                        echo "<table data-group='".$escaper->escapeHtml($group_value_from_db)."' class=\"table risk-datatable table-bordered table-striped table-condensed  table-margin-top\" style='width: 100%'>\n";
                        echo "<thead>\n";
                        echo "<tr>\n";
                        
                        // If customization extra is enabled, add custom fields
                        if(customization_extra())
                        {
                            // Include the extra
                            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                            
                            $active_fields = get_active_fields();
                            $custom_fields = [];
                            foreach($active_fields as $active_field)
                            {
                                if($active_field['is_basic'] == 0)
                                {
                                    $custom_fields[] = $active_field;
                                }
                            }
                            
                            $custom_fields_length = count($custom_fields);
                        }
                        else{
                            $custom_fields_length = 0;
                        }
                        
                        $length = 43 + $custom_fields_length;
                        
                        echo "<th bgcolor=\"#0088CC\" colspan=\"{$length}\"><center>". $escaper->escapeHtml($group_value) ."</center></th>\n";
                        echo "</tr>\n";
                        echo "<tr class='main'>\n";

                        // Header columns go here
                        get_header_columns(false, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_additional_stakeholders, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_residual_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_planning_date, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_mitigation_accepted, $column_mitigation_date, $column_mitigation_controls, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements, $column_risk_tags, $column_closure_date);

                        echo "</tr>\n";
                        echo "<tr class='filter'>\n";
                        // Header columns go here
                        get_header_columns(false, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_additional_stakeholders, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_residual_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_planning_date, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_mitigation_accepted, $column_mitigation_date, $column_mitigation_controls, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements, $column_risk_tags, $column_closure_date);
                        echo "</tr>\n";
                        echo "</thead>\n";
                        echo "<tbody>\n";
                    }
                    
                }
            }
        }
    }

    // If the group name is none
        // End the table
    echo "</tbody>\n";
    echo "</table>\n";
    echo "<br />\n";
}

/********************************
 * FUNCTION: GET HEADER COLUMNS *
 ********************************/
function get_header_columns($hide, $id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $residual_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $planning_date, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_accepted, $mitigation_date, $mitigation_controls, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements, $risk_tags, $closure_date, $custom_columns=[])
{
    global $lang;
    global $escaper;

    if($hide){
        $display = "display: none;";
    }else{
        $display = "display: table-cell;";
    }
    
    echo "<th class=\"id\" data-name='id' " . ($id == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['ID']) ."</th>\n";
    echo "<th class=\"status\" data-name='risk_status' " . ($risk_status == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['Status']) ."</th>\n";
    echo "<th class=\"subject\" data-name='subject' " . ($subject == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
    echo "<th class=\"reference_id\" data-name='reference_id' " . ($reference_id == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['ExternalReferenceId']) ."</th>\n";
    echo "<th class=\"regulation\" data-name='regulation' " . ($regulation == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['ControlRegulation']) ."</th>\n";
    echo "<th class=\"control_number\" data-name='control_number' " . ($control_number == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['ControlNumber']) ."</th>\n";
    echo "<th class=\"location\" data-name='location' " . ($location == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['SiteLocation']) ."</th>\n";
    echo "<th class=\"source\" data-name='source' " . ($source == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['RiskSource']) ."</th>\n";
    echo "<th class=\"category\" data-name='category' " . ($category == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['Category']) ."</th>\n";
    echo "<th class=\"team\" data-name='team' " . ($team == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['Team']) ."</th>\n";
    echo "<th class=\"team\" data-name='additional_stakeholders' " . ($additional_stakeholders == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['AdditionalStakeholders']) ."</th>\n";
    echo "<th class=\"technology\" data-name='technology' " . ($technology == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['Technology']) ."</th>\n";
    echo "<th class=\"owner\" data-name='owner' " . ($owner == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['Owner']) ."</th>\n";
    echo "<th class=\"manager\" data-name='manager' " . ($manager == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['OwnersManager']) ."</th>\n";
    echo "<th class=\"submitted_by\" data-name='submitted_by' " . ($submitted_by == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['SubmittedBy']) ."</th>\n";
    echo "<th class=\"scoring_method\" data-name='scoring_method' " . ($scoring_method == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['RiskScoringMethod']) ."</th>\n";
    echo "<th class=\"calculated_risk\" data-name='calculated_risk' " . ($calculated_risk == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['InherentRisk']) ."</th>\n";
    echo "<th class=\"residual_risk\" data-name='residual_risk' " . ($residual_risk == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['ResidualRisk']) ."</th>\n";
    echo "<th class=\"submission_date\" data-name='submission_date' " . ($submission_date == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
    echo "<th class=\"review_date\" data-name='review_date' " . ($review_date == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['ReviewDate']) ."</th>\n";
    echo "<th class=\"project\" data-name='project' " . ($project == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['Project']) ."</th>\n";
    echo "<th class=\"mitigation_planned\" data-name='mitigation_planned' " . ($mitigation_planned == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['MitigationPlanned']) ."</th>\n";
    echo "<th class=\"management_review\" data-name='management_review' " . ($management_review == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['ManagementReview']) ."</th>\n";
    echo "<th class=\"days_open\" data-name='days_open' " . ($days_open == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
    echo "<th class=\"next_review_date\" data-name='next_review_date' " . ($next_review_date == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['NextReviewDate']) ."</th>\n";
    echo "<th class=\"next_step\" data-name='next_step' " . ($next_step == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['NextStep']) ."</th>\n";
    echo "<th class=\"affected_assets\" data-name='affected_assets' " . ($affected_assets == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['AffectedAssets']) ."</th>\n";
    echo "<th class=\"risk_assessment\" data-name='risk_assessment' " . ($risk_assessment == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['RiskAssessment']) ."</th>\n";
    echo "<th class=\"additional_notes\" data-name='additional_notes' " . ($additional_notes == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['AdditionalNotes']) ."</th>\n";
    echo "<th class=\"current_solution\" data-name='current_solution' " . ($current_solution == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['CurrentSolution']) ."</th>\n";
    echo "<th class=\"security_recommendations\" data-name='security_recommendations' " . ($security_recommendations == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['SecurityRecommendations']) ."</th>\n";
    echo "<th class=\"security_requirements\" data-name='security_requirements' " . ($security_requirements == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['SecurityRequirements']) ."</th>\n";
    echo "<th class=\"planning_strategy\" data-name='planning_strategy' " . ($planning_strategy == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['PlanningStrategy']) ."</th>\n";
    echo "<th class=\"planning_date\" data-name='planning_date' " . ($planning_date == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['MitigationPlanning']) ."</th>\n";
    echo "<th class=\"mitigation_effort\" data-name='mitigation_effort' " . ($mitigation_effort == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['MitigationEffort']) ."</th>\n";
    echo "<th class=\"mitigation_cost\" data-name='mitigation_cost' " . ($mitigation_cost== true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['MitigationCost']) ."</th>\n";
    echo "<th class=\"mitigation_owner\" data-name='mitigation_owner' " . ($mitigation_owner== true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['MitigationOwner']) ."</th>\n";
    echo "<th class=\"mitigation_team\" data-name='mitigation_team' " . ($mitigation_team == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['MitigationTeam']) ."</th>\n";
    echo "<th class=\"mitigation_accepted\" data-name='mitigation_accepted' " . ($mitigation_accepted == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['MitigationAccepted']) ."</th>\n";
    echo "<th class=\"mitigation_date\" data-name='mitigation_date' " . ($mitigation_date == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['MitigationDate']) ."</th>\n";
    echo "<th class=\"mitigation_controls\" data-name='mitigation_controls' " . ($mitigation_controls == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['MitigationControls']) ."</th>\n";
    echo "<th class=\"risk_tags\" data-name='risk_tags' " . ($risk_tags == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['Tags']) ."</th>\n";
    echo "<th class=\"closure_date\" data-name='closure_date' " . ($closure_date == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $escaper->escapeHtml($lang['DateClosed']) ."</th>\n";
    

    // If customization extra is enabled, add custom fields
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $active_fields = get_active_fields();
        foreach($active_fields as $active_field)
        {
            if($active_field['is_basic'] == 0)
            {
                echo "<th class=\"custom_field_{$active_field['id']}\" data-name='custom_field_{$active_field['id']}' " . ( !empty($custom_columns["custom_field_{$active_field['id']}"]) ? "" : "style=\"{$display}\" ") . " align=\"left\" >". $escaper->escapeHtml( $active_field['name'] ) ."</th>\n";
            }
        }
        
    }
}

/**********************************
 * FUNCTION: TABLE OF RISK BY TEAM *
 *********************************/
function risk_table_open_by_team($column_id=true, $column_status=false, $column_subject=true, $column_reference_id=false, $column_regulation=false, $column_control_number=false, $column_location=false, $column_source=false, $column_category=false, $column_team=false, $column_additional_stakeholders=false, $column_technology=false, $column_owner=false, $column_manager=false, $column_submitted_by=false, $column_scoring_method=false, $column_calculated_risk=true, $column_residual_risk=false, $column_submission_date=true, $column_review_date=false, $column_project=false, $column_mitigation_planned=true, $column_management_review=true, $column_days_open=false, $column_next_review_date=false, $column_next_step=false, $column_affected_assets=false, $column_planning_strategy=false, $column_planning_date=false, $column_mitigation_effort=false, $column_mitigation_cost=false, $column_mitigation_owner=false, $column_mitigation_team=false, $column_mitigation_accepted=false, $column_mitigation_date=false, $column_mitigation_controls=false, $column_risk_assessment=false, $column_additional_notes=false, $column_current_solution=false, $column_security_recommendations=false, $column_security_requirements=false, $column_risk_tags=false, $column_closure_date=false, $column_custom_values=[]){

    global $lang;
    global $escaper;

    // Display the table header
    echo "<table data-group='' class=\"table risk-datatable table-bordered table-striped table-condensed  table-margin-top\" style='width: 100%'>\n";
    echo "<thead>\n";
    echo "<tr class='main'>\n";

    // Header columns go here
    get_header_columns(false, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_additional_stakeholders, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_residual_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_planning_date, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_mitigation_accepted, $column_mitigation_date, $column_mitigation_controls, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements, $column_risk_tags, $column_closure_date);

    echo "</tr>\n";
    echo "<tr class='filter'>\n";
    // Header columns go here
    get_header_columns(false, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_additional_stakeholders, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_residual_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_planning_date, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_mitigation_accepted, $column_mitigation_date, $column_mitigation_controls, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements, $column_risk_tags, $column_closure_date);
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    
    // End the table
    echo "</tbody>\n";
    echo "<tfoot>\n";
    echo "<tr class='footer'>\n";
    // Footer columns go here
    get_header_columns(false, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_additional_stakeholders, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_residual_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_planning_date, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_mitigation_accepted, $column_mitigation_date, $column_mitigation_controls, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements, $column_risk_tags, $column_closure_date);
    echo "</tr>\n";
    echo "</tfoot>\n";
    echo "</table>\n";
    echo "<br />\n";
}

/**********************************
 * FUNCTION: RISKS BY MONTH TABLE *
 **********************************/
function risks_by_month_table()
{
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

    echo "<table name=\"risks_by_month\" width=\"100%\" height=\"100%\" border=\"1\">\n";
    echo "<thead>\n";
    echo "<tr bgcolor=\"white\">\n";
    echo "<th>&nbsp;</th>\n";

    // For each of the past 12 months
    for ($i=12; $i>=0; $i--)
    {
        // Get the month
        $month = date('Y M', strtotime("first day of -$i month"));

        echo "<th align=\"center\" width=\"50px\">" . $escaper->escapeHtml($month) . "</th>\n";
    }

    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    echo "<tr bgcolor=\"white\">\n";
    echo "<td align=\"center\">" . $escaper->escapeHtml($lang['OpenedRisks']) . "</td>\n";

    // For each of the past 12 months
    for ($i=12; $i>=0; $i--)
    {
        // Get the month
        $month = date('Y-m', strtotime("first day of -$i month"));
        
        // Search the open risks array
        $key = array_search($month, $open_date);

        // If no result was found or the key is null
        if ($key === false || is_null($key))
        {
            // Set the value to 0
            $open[$i] = 0;
        }
        // Otherwise, use the value found
        else $open[$i] = $open_count[$key];

        echo "<td align=\"center\" width=\"50px\">" . $escaper->escapeHtml($open[$i]) . "</td>\n";
    }

    echo "</tr>\n";
    echo "<tr bgcolor=\"white\">\n";
    echo "<td align=\"center\">" . $escaper->escapeHtml($lang['ClosedRisks']) . "</td>\n";

    // For each of the past 12 months
    for ($i=12; $i>=0; $i--)
    {
        // Get the month
        $month = date('Y-m', strtotime("first day of -$i month"));

        // Search the closed risks array
        $key = array_search($month, $close_date);

        // If no result was found or the key is null
        if ($key === false || is_null($key))
        {
            // Set the value to 0
            $close[$i] = 0;
        }
        // Otherwise, use the value found
        else $close[$i] = $close_count[$key];

        echo "<td align=\"center\" width=\"50px\">" . $escaper->escapeHtml($close[$i]) . "</td>\n";
    }

    echo "</tr>\n";
    echo "<tr bgcolor=\"white\">\n";
    echo "<td align=\"center\">" . $escaper->escapeHtml($lang['RiskTrend']) . "</td>\n";

    // For each of the past 12 months
    for ($i=12; $i>=0; $i--)
    {
        // Subtract the open number from the closed number
        $total[$i] = $open[$i] - $close[$i];

        // If the total is positive
        if ($total[$i] > 0)
        {
            // Display it in red
            $total_string = "<font color=\"red\">+" . $total[$i] . "</font>";
        }
        // If the total is negative
        else if ($total[$i] < 0)
        {
            // Display it in green
            $total_string = "<font color=\"green\">" . $total[$i] . "</font>
";
        }
        // Otherwise the total is 0
        else $total_string = $total[$i];

        echo "<td align=\"center\" width=\"50px\">" . $total_string . "</td>\n";
    }

    // Reverse the total array
    $total = array_reverse($total);

    // Get the number of open risks
    $open_risks_today = get_open_risks();

    // Start the total open risks array with the open risks today
    $total_open_risks[] = $open_risks_today;

    // For each of the past 12 months
    for ($i=1; $i<=12; $i++)
    {
        $total_open_risks[$i] = $total_open_risks[$i-1] - $total[$i-1];
    }

    // Reverse the total open risks array
    $total_open_risks = array_reverse($total_open_risks);
    
    echo "</tr>\n";
    echo "<tr bgcolor=\"white\">\n";
    echo "<td align=\"center\">" . $escaper->escapeHtml($lang['TotalOpenRisks']) . "</td>\n";

    // For each of the past 12 months
    for ($i=0; $i<=12; $i++)
    {
        // Get the total number of risks
        $total = $total_open_risks[$i];

        echo "<td align=\"center\" width=\"50px\">" . $escaper->escapeHtml($total) . "</td>\n";
    }

    echo "</tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";
}

/*************************************
 * FUNCTION: RETURN REISKS QUERY SQL *
 *************************************/
function risks_query_select($column_filters)
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
        p.mitigation_percent,
        ROUND((b.calculated_risk - (b.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) AS residual_risk,
        (
            SELECT
                GROUP_CONCAT(DISTINCT location.name SEPARATOR '; ')
            FROM
                location, risk_to_location rtl
            WHERE
                rtl.risk_id=a.id AND rtl.location_id=location.value
        ) AS location,

        d.name AS category, 

        (
            SELECT
                GROUP_CONCAT(DISTINCT team.name  SEPARATOR ', ')
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
                GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ')
            FROM
                user u, risk_to_additional_stakeholder rtas
            WHERE
                rtas.risk_id=a.id AND rtas.user_id=u.value
        ) AS additional_stakeholders,


        (
            SELECT
                GROUP_CONCAT(DISTINCT tech.name SEPARATOR ', ')
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
        a.project_id,
        l.next_review, 
        m.name AS next_step, 
        (
            SELECT
                GROUP_CONCAT(DISTINCT rta.asset_id SEPARATOR ', ')
            FROM
                risks_to_assets rta
            WHERE
                rta.risk_id=a.id
        ) AS affected_assets,
        (
            SELECT
                GROUP_CONCAT(DISTINCT rtag.asset_group_id SEPARATOR ', ')
            FROM
                risks_to_asset_groups rtag
            WHERE
                rtag.risk_id=a.id
        ) AS affected_asset_groups,
        
        o.closure_date, 
        q.name AS planning_strategy,
        p.planning_date, 
        r.name AS mitigation_effort, 
        s.min_value AS mitigation_min_cost, 
        s.max_value AS mitigation_max_cost, 
        s.valuation_level_name, 
        t.name AS mitigation_owner,
        (
            SELECT
                GROUP_CONCAT(DISTINCT team.name SEPARATOR ', ')
            FROM
                team, mitigation_to_team mtt 
            WHERE
                mtt.mitigation_id=p.id AND mtt.team_id=team.value
        ) AS mitigation_team,


        NOT(ISNULL(mau.id)) mitigation_accepted, 
        p.submission_date AS mitigation_date, 
        
        (
            SELECT
                GROUP_CONCAT(DISTINCT team.name SEPARATOR ', ')
            FROM
                team, mitigation_to_team mtt 
            WHERE
                mtt.mitigation_id=p.id AND mtt.team_id=team.value
        ) AS mitigation_team,
        
        
        (
            SELECT
                GROUP_CONCAT(DISTINCT fc.short_name SEPARATOR ', ')
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
                GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR '; ')
            FROM
                tags t, tags_taggees tt 
            WHERE
                tt.tag_id = t.id AND tt.taggee_id=a.id AND tt.type='risk'
        ) AS risk_tags,
        DATEDIFF(IF(a.status != 'Closed', NOW(), o.closure_date) , a.submission_date) days_open
    ";

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
        p.mitigation_percent,
        ROUND((b.calculated_risk - (b.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) AS residual_risk,
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(location.name, '{$delimiter}', location.value) SEPARATOR '; ')
            FROM
                location, risk_to_location rtl
            WHERE
                rtl.risk_id=a.id AND rtl.location_id=location.value
        ) AS location,

        d.name AS category, 
        CONCAT(d.name, '{$delimiter}', d.value) AS category_for_dropdown, 

        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(team.name, '{$delimiter}', team.value)  SEPARATOR ', ')
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
                GROUP_CONCAT(DISTINCT CONCAT(u.name, '{$delimiter}', u.value) SEPARATOR ', ')
            FROM
                user u, risk_to_additional_stakeholder rtas
            WHERE
                rtas.risk_id=a.id AND rtas.user_id=u.value
        ) AS additional_stakeholders,

        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(tech.name, '{$delimiter}', tech.value) SEPARATOR ', ')
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
        CONCAT(g.name, '{$delimiter}', g.value) AS owner_for_dropdown, 

        h.name AS manager, 
        CONCAT(h.name, '{$delimiter}', h.value) AS manager_for_dropdown, 

        i.name AS submitted_by, 
        CONCAT(i.name, '{$delimiter}', i.value) AS submitted_by_for_dropdown, 

        j.name AS regulation, 
        CONCAT(j.name, '{$delimiter}', j.value) AS regulation_for_dropdown, 

        k.name AS project, 
        CONCAT(k.name, '{$delimiter}', k.value) AS project_for_dropdown, 
        
        l.next_review, 
        m.name AS next_step, 
        CONCAT(m.name, '{$delimiter}', m.value) AS next_step_for_dropdown, 
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

        o.closure_date, 
        
        q.name AS planning_strategy,
        CONCAT(q.name, '{$delimiter}', q.value) AS planning_strategy_for_dropdown,
        
        p.planning_date, 
        
        r.name AS mitigation_effort,
        CONCAT(r.name, '{$delimiter}', r.value) AS mitigation_effort_for_dropdown, 
        
        IF(s.valuation_level_name IS NULL OR s.valuation_level_name='', 
            CONCAT('\$', s.min_value, ' to \$', s.max_value, '{$delimiter}', s.min_value, '-', s.max_value),
            CONCAT('\$', s.min_value, ' to \$', s.max_value, '(', s.valuation_level_name, ')', '{$delimiter}', s.min_value, '-', s.max_value)
          ) mitigation_cost,
        
        s.min_value AS mitigation_min_cost, 
        s.max_value AS mitigation_max_cost, 
        
        s.valuation_level_name, 
        
        t.name AS mitigation_owner,
        CONCAT(t.name, '{$delimiter}', t.value) AS mitigation_owner_for_dropdown,
        
        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(team.name, '{$delimiter}', team.value) SEPARATOR ', ')
            FROM
                team, mitigation_to_team mtt 
            WHERE
                mtt.mitigation_id=p.id AND mtt.team_id=team.value
        ) AS mitigation_team,

        NOT(ISNULL(mau.id)) mitigation_accepted, 
        p.submission_date AS mitigation_date, 
        GROUP_CONCAT(DISTINCT CONCAT(fc.short_name, '{$delimiter}', fc.id) SEPARATOR ', ') mitigation_control_names, 
        
        v.name AS source, 
        CONCAT(v.name, '{$delimiter}', v.value) AS source_for_dropdown, 
        p.id mitigation_id, 
        p.current_solution,
        p.security_recommendations, 
        p.security_requirements, 
        ifnull((SELECT IF(display_name='', name, display_name) FROM `risk_levels` WHERE value-b.calculated_risk<=0.00001 ORDER BY value DESC LIMIT 1), '{$lang['Insignificant']}') as risk_level_name,

        (
            SELECT
                GROUP_CONCAT(DISTINCT CONCAT(t.tag, '{$delimiter}', t.id) ORDER BY t.tag ASC SEPARATOR ';')
            FROM
                tags t, tags_taggees tt 
            WHERE
                tt.tag_id = t.id AND tt.taggee_id=a.id AND tt.type='risk'
        ) AS risk_tags
    ";
    return $query;
}

/*************************************
 * FUNCTION: RETURN REISKS QUERY SQL *
 *************************************/
function risks_query_from($column_filters=[])
{
    global $lang;
    
    $query = "
            risks a
            LEFT JOIN risk_scoring b ON a.id = b.id
            LEFT JOIN category d FORCE INDEX(PRIMARY) ON a.category = d.value
            LEFT JOIN user g FORCE INDEX(PRIMARY) ON a.owner = g.value
            LEFT JOIN user h FORCE INDEX(PRIMARY) ON a.manager = h.value
            LEFT JOIN user i FORCE INDEX(PRIMARY) ON a.submitted_by = i.value
            LEFT JOIN frameworks j FORCE INDEX(PRIMARY) ON a.regulation = j.value
            LEFT JOIN projects k FORCE INDEX(PRIMARY) ON a.project_id = k.value
            LEFT JOIN mgmt_reviews l ON a.mgmt_review = l.id
            LEFT JOIN next_step m FORCE INDEX(PRIMARY) ON l.next_step = m.value
            LEFT JOIN closures o ON a.close_id = o.id
            LEFT JOIN mitigations p ON a.id = p.risk_id
            LEFT JOIN `mitigation_to_controls` mtc ON p.id = mtc.mitigation_id
            LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            LEFT JOIN planning_strategy q FORCE INDEX(PRIMARY) ON p.planning_strategy = q.value
            LEFT JOIN mitigation_effort r FORCE INDEX(PRIMARY) ON p.mitigation_effort = r.value
            LEFT JOIN asset_values s ON p.mitigation_cost = s.id
            LEFT JOIN user t FORCE INDEX(PRIMARY) ON p.mitigation_owner = t.value
            LEFT JOIN mitigation_accept_users mau ON a.id=mau.risk_id
            LEFT JOIN source v FORCE INDEX(PRIMARY) ON a.source = v.value
    ";
    // If the team separation extra is enabled
    $team_separation_extra = team_separation_extra();
    if(!empty($column_filters['location'])){
        $query .= " LEFT JOIN risk_to_location rtl ON a.id=rtl.risk_id ";
    }
    if(!empty($column_filters['team']) || $team_separation_extra){
        $query .= " LEFT JOIN risk_to_team rtt ON a.id=rtt.risk_id ";
    }
    if(!empty($column_filters['technology'])){
        $query .= " LEFT JOIN risk_to_technology rttg ON a.id=rttg.risk_id ";
    }
    if(!empty($column_filters['additional_stakeholders']) || $team_separation_extra){
        $query .= " LEFT JOIN risk_to_additional_stakeholder rtas ON a.id=rtas.risk_id ";
    }
    if(!empty($column_filters['mitigation_team'])){
        $query .= " LEFT JOIN mitigation_to_team mtt ON p.id=mtt.mitigation_id ";
    }
    if(!empty($column_filters['risk_tags'])){
        $query .= " LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id AND tt.type = 'risk' ";
    }
    if(!empty($column_filters['affected_assets'])){
        $query .= " LEFT JOIN risks_to_assets rta ON a.id = rta.risk_id ";
        $query .= " LEFT JOIN risks_to_asset_groups rtag ON a.id = rtag.risk_id ";
    }
    
    // If customization extra is enabled, set join tables for custom filters
    if(customization_extra())
    {
        foreach($column_filters as $key => $column_filter)
        {
            if($column_filter && stripos($key, "custom_field_") !== false)
            {
                $custom_field_id = (int)str_replace("custom_field_", "", $key);
                
                if($custom_field_id)
                {
                    $table_alias = "custom_field_".$custom_field_id;
                    
                    $query .= " LEFT JOIN custom_risk_data {$table_alias} ON a.id={$table_alias}.risk_id AND {$table_alias}.field_id={$custom_field_id} AND ( {$table_alias}.review_id=0 OR {$table_alias}.review_id=a.mgmt_review ) ";
                }
            }
        }
    }
    

    return $query;
}

/**************************************
 * FUNCTION: RETURN DYNAMIC RISKS SQL *
 * query_type: 
 *      1: dynamic risk
 *      2: unique column
 **************************************/
function make_full_risks_sql($query_type, $status, $sort, $group, $column_filters=[], &$group_value_from_db="", &$custom_query="", &$bind_params=[], $having_query="", $orderColumnName=null, $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[])
{
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
        
        if($teams)
        {
            $teamsArray = array();
            if(in_array(0, $teams))
            {
                $teamsArray[] = "rtt.team_id IS NULL";
            }
            
            $bind_name = "param".count($params);
            $params[$bind_name] = implode(",", $teams);
            $teamsArray[] = "FIND_IN_SET(rtt.team_id, :{$bind_name})";

            $team_query_string = implode(" OR ", $teamsArray);
            array_push($team_querys, $team_query_string);
        }
        

        // If at least one owner was selected
        if($owners){
            $teamsArray = array();
            foreach($owners as $owner){
                $bind_name = "param".count($params);
                $params[$bind_name] = $owner;
                $teamsArray[] = "a.owner = :". $bind_name;
            }
            $team_query_string = implode(" OR ", $teamsArray);
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
            $team_query_string = implode(" OR ", $teamsArray);
            array_push($team_querys, $team_query_string);
        }
        $team_query = implode(" OR ", $team_querys);
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
        case "reference_id":
            $sort_name = " reference_id {$orderDir} ";
            break;
        case "regulation":
            $sort_name = " regulation {$orderDir} ";
            break;
        case "control_number":
            $sort_name = " control_number {$orderDir} ";
            break;
        case "location":
            $sort_name = " location {$orderDir} ";
            break;
        case "source":
            $sort_name = " source {$orderDir} ";
            break;
        case "category":
            $sort_name = " category {$orderDir} ";
            break;
        case "team":
            $sort_name = " team {$orderDir} ";
            break;
        case "technology":
            $sort_name = " technology {$orderDir} ";
            break;
        case "owner":
            $sort_name = " owner {$orderDir} ";
            break;
        case "manager":
            $sort_name = " manager {$orderDir} ";
            break;
        case "submitted_by":
            $sort_name = " submitted_by {$orderDir} ";
            break;
        case "scoring_method":
            $sort_name = " scoring_method {$orderDir} ";
            break;
        case "calculated_risk":
            $sort_name = " calculated_risk {$orderDir} ";
            break;
        case "residual_risk":
            $sort_name = " residual_risk {$orderDir} ";
            break;
        case "submission_date":
            $sort_name = " submission_date {$orderDir} ";
            break;
        case "review_date":
            $sort_name = " review_date {$orderDir} ";
            break;
        case "project":
            $sort_name = " project {$orderDir} ";
            break;
        case "mitigation_planned":
            $sort_name = " mitigation_id {$orderDir} ";
            break;
        case "next_step":
            $sort_name = " next_step {$orderDir} ";
            break;
        case "affected_assets":
            $sort_name = " affected_assets {$orderDir} ";
            break;
        case "planning_strategy":
            $sort_name = " planning_strategy {$orderDir} ";
            break;
        case "mitigation_effort":
            $sort_name = " mitigation_effort {$orderDir} ";
            break;
        case "mitigation_cost":
            $sort_name = " mitigation_min_cost {$orderDir} ";
            break;
        case "mitigation_owner":
            $sort_name = " mitigation_min_cost {$orderDir} ";
            break;
        case "mitigation_team":
            $sort_name = " mitigation_team {$orderDir} ";
            break;
        case "mitigation_date":
            $sort_name = " mitigation_date {$orderDir} ";
            break;
        case "mitigation_controls":
            $sort_name = " mitigation_control_names {$orderDir} ";
            break;
        case "planning_date":
            $sort_name = " planning_date {$orderDir} ";
            break;
        case "closure_date":
            $sort_name = " closure_date {$orderDir} ";
            break;
        default:
            $sort_name = "none";
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

    $query .= " FROM ".risks_query_from($column_filters)
        ." WHERE 1 "
        .$filter_query 
        .$status_query
        ." GROUP BY a.id "
        ." HAVING 1 "
        .$having_query
        .$order_query
    ;
    
    return [
         $query,
         $group_name
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
function get_risks_only_dynamic($need_total_count, $status, $sort, $group, $column_filters=[], &$rowCount, $start=0, $length=10, $group_value_from_db="", $custom_query="", $bind_params=array(), $orderColumnName=null, $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[])
{
    global $lang;
    
    // Constants for encrypt column names
    $encrypt_column_names = ["subject", "risk_assessment", "additional_notes", "current_solution", "security_requirements", "security_recommendations"];
    
    // Requested encrypt column names
    $requested_manual_column_filters = [];

    $havings = [];
    $having_query = "";
    $custom_date_filter = [];
    // If Column filters exist, make where query
    if($column_filters)
    {
        $wheres = [];
        foreach($column_filters as $name => $column_filter)
        {
            if(!$column_filter) continue;
            $empty_filter = false;
            $date_fields = array("submission_date", "review_date", "planning_date", "mitigation_date", "closure_date");
            // If encryption extra is enabled and Column is a encrypted field
            if((encryption_extra() && in_array($name, $encrypt_column_names)) || $name == "next_review_date" || $name == "management_review" || in_array($name, $date_fields))
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
                    $column_filter = implode(",", $column_filter);
                }

                $bind_param_name = "column_filter_". md5($name);

                switch($name){
                    case "id":
                        $wheres[] = " a.id+1000 = :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "risk_status":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(a.status, :{$bind_param_name}) OR a.status IS NULL)";
                        else $wheres[] = " FIND_IN_SET(a.status, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
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
                        $wheres[] = " p.{$name} like :".$bind_param_name;
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "regulation":
                    case "source":
                    case "category":
                    case "owner":
                    case "manager":
                    case "submitted_by":
                        $wheres[] = " FIND_IN_SET(a.{$name}, :{$bind_param_name}) ";
                        if($empty_filter) $column_filter .= ",0";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "next_step":
                        if($empty_filter) $wheres[] = " (FIND_IN_SET(l.{$name}, :{$bind_param_name}) OR  l.{$name} IS NULL)";
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
                    case "team":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(rtt.team_id, :{$bind_param_name}) OR rtt.team_id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(rtt.team_id, :{$bind_param_name}) ";
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
                        if($empty_filter) $wheres[] = "( FIND_IN_SET(mtc.control_id, :{$bind_param_name}) OR mtc.team_id IS NULL) ";
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
                    case "calculated_risk":
                        $operator = get_operator_from_value($column_filters[$name."_operator"]);
                        $wheres[] = " b.{$name} {$operator} :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "residual_risk":
                        $operator = get_operator_from_value($column_filters[$name."_operator"]);
                        $havings[] = " {$name} {$operator} :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "days_open":
                        $operator = get_operator_from_value($column_filters[$name."_operator"]);
                        $havings[] = " {$name} {$operator} :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    default:
//                        $wheres[]
                    break;
                }
            }
        }
        
        // If customization extra is enabled, add queries for custom fields
        if(customization_extra())
        {
            // Include the extra
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
            
            $active_fields = get_active_fields();
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
    
    $query_type = get_query_type($need_total_count);
    
    list($query, $group_name) = make_full_risks_sql($query_type, $status, $sort, $group, $column_filters, $group_value_from_db, $custom_query, $bind_params, $having_query, $orderColumnName, $orderDir, $risks_by_team, $teams, $owners, $ownersmanagers);

    $start = (int)$start;
    $length = (int)$length;
    
    // If encrypt filter no exists, get page data by sql
    if(!$requested_manual_column_filters)
    {
        if($length == -1)
        {
            $limitQuery = "";
        }
        else
        {
            $limitQuery = "Limit {$start}, {$length}";
        }
        
        $query .= "
            {$limitQuery}
        ";
    }

    // Query the database
    $db = db_open();

    $stmt = $db->prepare($query);
    
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
    db_close($db);

    // Store the results in the risks array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filtered_risks = [];
    
    // If encrypted columns were filtered, get filtered risks and filtered total count
    if($requested_manual_column_filters)
    {
        $review_levels = get_review_levels();
        
        foreach($risks as $risk)
        {
            $success = true;
            foreach($requested_manual_column_filters as $column_name => $val){
                if(stripos($column_name, "custom_field") !== false)
                {
                    if($custom_date_filter[$column_name] == true){
                        $date_str = format_datetime($risk[$column_name],"","");
                        if( stripos($date_str, $val) === false ){
                            $success = false;
                            break;
                        }
                    } 
                    elseif( stripos(try_decrypt($risk[$column_name]), $val) === false ){
                        $success = false;
                        break;
                    }
                }
                elseif($column_name == "subject")
                {
                    if( stripos(try_decrypt($risk['subject']), $val) === false ){
                        $success = false;
                        break;
                    }
                }
                elseif($column_name == "risk_assessment")
                {
                    if( stripos(try_decrypt($risk['risk_assessment']), $val) === false ){
                        $success = false;
                        break;
                    }
                }
                elseif($column_name == "additional_notes")
                {
                    if( stripos(try_decrypt($risk['additional_notes']), $val) === false ){
                        $success = false;
                        break;
                    }
                }
                elseif($column_name == "current_solution" || $column_name == "security_recommendations" || $column_name == "security_requirements")
                {
                    if( stripos(try_decrypt($risk[$column_name]), $val) === false ){
                        $success = false;
                        break;
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
                    
                    if( stripos($next_review, $val) === false ){
                        $success = false;
                        break;
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
            }
            if($success) $filtered_risks[] = $risk;
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
    }
    else
    {
        $stmt = $db->prepare("SELECT FOUND_ROWS();");
        $stmt->execute();
        $rowCount = $stmt->fetchColumn();
        $filtered_risks = $risks;
    }
    
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
//    for( $i = $start; $i < $start + $length && $i<$rowCount && $risks[$i]; $i++ ){
    foreach($risks as $risk){
        $risk_id = (int)$risk['id'];
        $status = $risk['status'];
        $subject = try_decrypt($risk['subject']);
        $reference_id = $risk['reference_id'];
        $control_number = $risk['control_number'];
        $submission_date = $risk['submission_date'];
        $last_update = $risk['last_update'];
        $review_date = $risk['review_date'];
        // If the risk hasn't been reviewed yet
        if ($review_date == "0000-00-00 00:00:00")
        {
            // Set the review date to empty
            $review_date = "";
        }
        // Otherwise set the review date to the proper format
        else $review_date = date(get_default_datetime_format("H:i"), strtotime($review_date));
        
        $scoring_method = get_scoring_method_name($risk['scoring_method']);
        $calculated_risk = (float)$risk['calculated_risk'];
        
        $residual_risk = (float)$risk['residual_risk'];
        $color = get_risk_color_from_levels($risk['calculated_risk'], $risk_levels);
        $residual_color = get_risk_color_from_levels($risk['residual_risk'], $risk_levels);
        $risk_level = get_risk_level_name_from_levels($risk['calculated_risk'], $risk_levels);
        $residual_risk_level = get_risk_level_name_from_levels($risk['residual_risk'], $risk_levels);
        $risk_tags = $risk['risk_tags'];
        $location = $risk['location'];
        $source = $risk['source'];
        $category = $risk['category'];
        $team = $risk['team'];
        $additional_stakeholders = $risk['additional_stakeholders'];
        $technology = $risk['technology'];
        $owner = $risk['owner'];
        $manager = $risk['manager'];
        $submitted_by = $risk['submitted_by'];
        $regulation = try_decrypt($risk['regulation']);
        $closure_date = $risk['closure_date'];
        $project = try_decrypt($risk['project']);
        $mitigation_id = $risk['mitigation_id'];
        $mgmt_review = $risk['mgmt_review'];

        // If the status is not closed
//        if ($status != "Closed")
//        {
            // Compare submission date to now
//            $days_open = dayssince($risk['submission_date']);
//        }
        // Otherwise the status is closed
//        else
//        {
            // Compare the submission date to the closure date
//            $days_open = dayssince($risk['submission_date'], $risk['closure_date']);
//        }
        $days_open = $risk['days_open'];
        
        // If next_review_date_uses setting is Residual Risk.
        if(get_setting('next_review_date_uses') == "ResidualRisk")
        {
            $next_review_date = next_review($residual_risk_level, $risk_id, $risk['next_review'], false, $review_levels);
            $next_review_date_html = next_review($residual_risk_level, $risk_id, $risk['next_review'], true, $review_levels);
        }
        // If next_review_date_uses setting is Inherent Risk.
        else
        {
            $next_review_date = next_review($risk_level, $risk_id, $risk['next_review'], false, $review_levels);
            $next_review_date_html = next_review($risk_level, $risk_id, $risk['next_review'], true, $review_levels);
        }
        $next_step = $risk['next_step'];

        // If the affected assets or affected asset groups is not empty
        if ($risk['affected_assets'] || $risk['affected_asset_groups'])
        {
            // Do a lookup for the list of affected assets
            $affected_assets = implode('', get_list_of_asset_and_asset_group_names($risk_id + 1000, true));
        }
        else $affected_assets = "";

        $risk_assessment = try_decrypt($risk['risk_assessment']);
        $additional_notes = try_decrypt($risk['additional_notes']);
        $current_solution = try_decrypt($risk['current_solution']);
        $security_recommendations = try_decrypt($risk['security_recommendations']);
        $security_requirements = try_decrypt($risk['security_requirements']);
        if (!$risk['submission_date'] || stripos($risk['submission_date'], "0000-00-00") !== false)
        {
            // Set the review date to empty
            $month_submitted = $lang['Unassigned'];
        }
        else
        {
            $month_submitted = date('Y F', strtotime($risk['submission_date']));
        }
        
        $planning_strategy = $risk['planning_strategy'];
        $planning_date  =  format_date($risk['planning_date']);
        $mitigation_effort = $risk['mitigation_effort'];
        $mitigation_min_cost = $risk['mitigation_min_cost'];
        $mitigation_max_cost = $risk['mitigation_max_cost'];
        $mitigation_owner = $risk['mitigation_owner'];
        $mitigation_team = $risk['mitigation_team'];
        $mitigation_accepted = $risk['mitigation_accepted'] ? $escaper->escapeHtml($lang['Yes']) : $escaper->escapeHtml($lang['No']);
        $mitigation_date = format_date($risk['mitigation_date']);
        $mitigation_control_names = $risk['mitigation_control_names'];

        // If the mitigation costs are empty
        if (empty($mitigation_min_cost) && empty($mitigation_max_cost))
        {
                // Return no value
                $mitigation_cost = "";
        }
        else 
        {
            $mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;
            if (!empty($risk['valuation_level_name']))
                $mitigation_cost .= " ({$risk['valuation_level_name']})";
        }

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

        // Create the new data array
        $data[] = array("id" => $risk_id, "status" => $status, "subject" => $subject, "reference_id" => $reference_id, "control_number" => $control_number, "submission_date" => $submission_date, "last_update" => $last_update, "review_date" => $review_date, "scoring_method" => $scoring_method, "calculated_risk" => $calculated_risk, "residual_risk" => $residual_risk, "color" => $color, "residual_color" => $residual_color, "risk_level" => $risk_level, "residual_risk_level" => $residual_risk_level, "location" => $location, "source" => $source, "category" => $category, "team" => $team, "additional_stakeholders" => $additional_stakeholders, "technology" => $technology, "owner" => $owner, "manager" => $manager, "submitted_by" => $submitted_by, "regulation" => $regulation, "project" => $project, "mgmt_review" => $mgmt_review, "days_open" => $days_open, "next_review_date" => $next_review_date, "next_review_date_html" => $next_review_date_html, "next_step" => $next_step, "affected_assets" => $affected_assets, "risk_assessment" => $risk_assessment, "additional_notes" => $additional_notes, "current_solution" => $current_solution, "security_recommendations" => $security_recommendations, "security_requirements" => $security_requirements, "month_submitted" => $month_submitted, "planning_strategy" => $planning_strategy, "mitigation_id" => $mitigation_id, "planning_date" => $planning_date, "mitigation_effort" => $mitigation_effort, "mitigation_min_cost" => $mitigation_min_cost, "mitigation_max_cost" => $mitigation_max_cost, "mitigation_cost" => $mitigation_cost, "mitigation_owner" => $mitigation_owner, "mitigation_team" => $mitigation_team, "mitigation_accepted" => $mitigation_accepted, "mitigation_date" => $mitigation_date, "mitigation_control_names" => $mitigation_control_names, "group_name" => $group_name, "group_value" => $group_value, 'closure_date' => $closure_date, 'risk_tags' => $risk_tags);
    }

    // Return the data array
    return $data;
}

/************************************************
 * FUNCTION: GET DYANMICRISK UNIQUE COLUMN DATA *
 ************************************************/
function get_dynamicrisk_unique_column_data($status, $group, $group_value_from_db="", $custom_query="", $bind_params=array(), $orderColumnName=null, $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[])
{
    global $lang;

    list($query, $group_name) = make_full_risks_sql($query_type=3, $status, -1, $group, [], $group_value_from_db, $custom_query, $bind_params, "");

    // Query the database
    $db = db_open();

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
    db_close($db);

    // Store the results in the risks array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize the data array
    $data = array();

    // For each risk in the risks array
    foreach($risks as $risk){
        $key_relation_arr = [
            "category" => "category_for_dropdown",
            "owner" => "owner_for_dropdown",
            "manager" => "manager_for_dropdown",
            "submitted_by" => "submitted_by_for_dropdown",
            "regulation" => "regulation_for_dropdown",
            "project" => "project_for_dropdown",
            "next_step" => "next_step_for_dropdown",
            "planning_strategy" => "planning_strategy_for_dropdown",
            "mitigation_effort" => "mitigation_effort_for_dropdown",
            "mitigation_owner" => "mitigation_owner_for_dropdown",
            "source" => "source_for_dropdown",
        ];
        
        foreach($key_relation_arr as $key => $related_key)
        {
            if(isset($risk[$related_key]))
            {
                $risk[$key] = $risk[$related_key];
            }
        }


        // Create the new data array
        $data[] = array(
            "id" => $risk['id'], 
            "risk_status" => $risk['status'], 
            "scoring_method" => $risk['scoring_method'], 
            "location" => $risk['location'], 
            "source" =>  $risk['source'], 
            "category" => $risk['category'], 
            "team" => $risk['team'], 
            "additional_stakeholders" => $risk['additional_stakeholders'], 
            "technology" => $risk["technology"], 
            "owner" => $risk["owner"], 
            "manager" => $risk["manager"], 
            "submitted_by" => $risk["submitted_by"], 
            "regulation" => $risk["regulation"], 
            "project" => $risk["project"], 
            "next_step" => $risk["next_step"], 
            "affected_assets" => "", 
            "planning_strategy" => $risk["planning_strategy"], 
            "mitigation_effort" => $risk["mitigation_effort"], 
            "mitigation_cost" => $risk["mitigation_cost"], 
            "mitigation_owner" => $risk["mitigation_owner"], 
            "mitigation_team" => $risk["mitigation_team"], 
            "mitigation_controls" => $risk["mitigation_control_names"], 
            "risk_tags" => $risk["risk_tags"],
            "affected_assets" => $risk["affected_assets"],
            "affected_asset_groups" => $risk["affected_asset_groups"],
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
            $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `source` b ON a.source = b.value WHERE status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.name DESC");
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
            $stmt = $db->prepare("SELECT a.id, CASE WHEN scoring_method = 5 THEN 'Custom' WHEN scoring_method = 4 THEN 'OWASP' WHEN scoring_method = 3 THEN 'DREAD' WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS name, COUNT(*) AS num FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `risk_scoring` b ON a.id = b.id WHERE a.status != \"Closed\" AND {$teams_query} GROUP BY a.id ORDER BY b.scoring_method DESC");
            $stmt->execute();
            break;
        case 'close_reason':
            
            $field = "name";
            $stmt = $db->prepare("SELECT a.close_reason, a.risk_id as id, b.name, MAX(closure_date) FROM `closures` a JOIN `close_reason` b ON a.close_reason = b.value JOIN `risks` c ON a.risk_id = c.id LEFT JOIN `risk_to_team` rtt ON c.id=rtt.risk_id WHERE c.status = \"Closed\" AND {$teams_query} GROUP BY a.risk_id ORDER BY name DESC;");
            $stmt->execute();
            break;
        default:
            $stmt = $db->prepare("SELECT a.id, a.status, GROUP_CONCAT(DISTINCT b.name separator '; ') AS location, c.name AS source, d.name AS category, GROUP_CONCAT(DISTINCT e.name SEPARATOR ', ') AS team, GROUP_CONCAT(DISTINCT f.name SEPARATOR ', ') AS technology, g.name AS owner, h.name AS manager, CASE WHEN scoring_method = 5 THEN 'Custom' WHEN scoring_method = 4 THEN 'OWASP' WHEN scoring_method = 3 THEN 'DREAD' WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS scoring_method FROM `risks` a LEFT JOIN `risk_to_team` rtt ON a.id=rtt.risk_id LEFT JOIN `team` e ON rtt.team_id=e.value LEFT JOIN `risk_to_location` rtl ON a.id=rtl.risk_id LEFT JOIN `location` b ON rtl.location_id=b.value LEFT JOIN `source` c ON a.source = c.value LEFT JOIN `category` d ON a.category = d.value LEFT JOIN risk_to_technology rttg ON a.id=rttg.risk_id LEFT JOIN `technology` f ON rttg.technology_id=f.value LEFT JOIN `user` g ON a.owner = g.value LEFT JOIN `user` h ON a.manager = h.value LEFT JOIN `risk_scoring` i ON a.id = i.id WHERE a.status != \"Closed\" AND {$teams_query} GROUP BY a.id; ");
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
function get_opened_risks_array($timeframe)
{
    // If team separation is not enabled
    if (!team_separation_extra())
    {
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT id, submission_date FROM risks ORDER BY submission_date;");
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

        // Get the opened risks stripped
        $array = strip_get_opened_risks_array();
    }

    // Set the defaults
    $counter = -1;
    $current_date = "";
    $open_date = array();
    $open_count = array();

    // For each row
    foreach ($array as $key=>$row)
    {
        // If the timeframe is by day
        if ($timeframe === "day")
        {
            // Set the date to the day
            $date = date('Y-m-d', strtotime($row['submission_date']));
        }
        // If the timeframe is by month
        else if ($timeframe === "month")
        {
            // Set the date to the month
            $date = date('Y-m', strtotime($row['submission_date']));
        }
        // If the timeframe is by year
        else if ($timeframe === "year")
        {
            // Set the date to the year
            $date = date('Y', strtotime($row['submission_date']));
        }

        // If the date is different from the current date
        if ($current_date != $date)
        {
            // Increment the counter
            $counter = $counter + 1;

            // Set the current date
            $current_date = $date;

            // Add the date
            $open_date[$counter] = $current_date;

            // Set the open count to 1
            $open_count[$counter] = 1;

            // If this is the first entry
            if ($counter == 0)
            {
                // Set the open total to 1
                $open_total[$counter] = 1;
            }
            // Otherwise, add the value of this row to the previous value
            else $open_total[$counter] = $open_total[$counter-1] + 1;
        }
        // Otherwise, if the date is the same
        else
        {
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
function get_closed_risks_array($timeframe)
{
    // If team separation is not enabled
    if (!team_separation_extra())
    {
        // Open the database connection
        $db = db_open();

        // Query the database
//$stmt = $db->prepare("SELECT a.risk_id as id, a.closure_date, c.status FROM closures a LEFT JOIN risks c ON a.risk_id=c.id WHERE a.closure_date=(SELECT max(b.closure_date) FROM closures b WHERE a.risk_id=b.risk_id) AND c.status='Closed' GROUP BY a.risk_id ORDER BY closure_date;");
        $stmt = $db->prepare("
            SELECT t1.id, IFNULL(t2.closure_date, NOW()) closure_date, t1.status 
            FROM `risks` t1 LEFT JOIN `closures` t2 ON t1.close_id=t2.id
            WHERE t1.status='Closed' 
            ORDER BY IFNULL(t2.closure_date, NOW());
        ");
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
        $array = strip_get_closed_risks_array();
    }

    // Set the defaults
    $counter = -1;
    $current_date = "";
    $close_date = array();
    $close_count = array();
    
    // For each row
    foreach ($array as $key=>$row)
    {
            // If the timeframe is by day
            if ($timeframe === "day")
            {
                    // Set the date to the day
                    $date = date('Y-m-d', strtotime($row['closure_date']));
            }
            // If the timeframe is by month
            else if ($timeframe === "month")
            {
                    // Set the date to the month
                    $date = date('Y-m', strtotime($row['closure_date']));
            }
            // If the timeframe is by year
            else if ($timeframe === "year")
            {
                    // Set the date to the year
                    $date = date('Y', strtotime($row['closure_date']));
            }

            // If the date is different from the current date
            if ($current_date != $date)
            {
                    // Increment the counter
                    $counter = $counter + 1;

                    // Set the current date
                    $current_date = $date;

                    // Add the date
                    $close_date[$counter] = $current_date;

                    // Set the close count to 1
                    $close_count[$counter] = 1;

                    // If this is the first entry
                    if ($counter == 0)
                    {
                        // Set the close total to 1
                        $close_total[$counter] = 1;
                    }
                    // Otherwise, add the value of this row to the previous value
                    else 
                    {
                        $close_total[$counter] = $close_total[$counter-1] + 1;
                    }
            }
            // Otherwise, if the date is the same
            else
            {
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
            $name = $escaper->escapeHtml($element[0]);
                $count = $element[1];
                $data[] = array($name, $count);
        }

    // Return the data array
    return $data;
}

/************************************
 * FUNCTION: RISKS AND CONTROLS TABLE *
 ************************************/
function risks_and_control_table($report)
{
    global $lang;
    global $escaper;

    $data = array();

    // Open the database
    $db = db_open();
    
    // Risks by Controls
    if($report == 0)
    {
        $select = "SELECT fc.id gr_id, b.*, c.calculated_risk, fc.short_name control_short_name, fc.long_name control_long_name, fc.id control_id
               , GROUP_CONCAT(DISTINCT l.name) location
               , GROUP_CONCAT(DISTINCT t.name) team
               , DATEDIFF(IF(b.status != 'Closed', NOW(), o.closure_date) , b.submission_date) days_open
        ";
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
        GROUP BY 
            b.id, fc.id
        ;
    ";

//    if ( $report == 0 ) {
//        $query .= "ORDER BY a.risk_id DESC";
//    } else {
//        $query .= "ORDER BY a.risk_id ASC";
//    }

    $stmt = $db->prepare($query);
    $stmt->execute();

    // Store the results in the rows array
    $rows = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
    

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
    
    foreach($rows as $gr_id => $row){
        if($separation)
        {
            $risks = strip_no_access_risks($row);
        }
        else
        {
            $risks = $row;
        }
        
        // Risks by Controls
        if ( $report == 0 ) {
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
                echo "<th colspan=\"5\"><center>" . $escaper->escapeHtml($lang['ControlLongName'])  . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_long_name']) ."</br>" . $escaper->escapeHtml($lang['ControlShortName']) . ":&nbsp;&nbsp;". $escaper->escapeHtml($risks[0]['control_short_name']) ."</center></th>\n";
                echo "</tr>\n";
                echo "<tr>\n";
                echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
                echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
                echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
                echo "<th align=\"left\" width=\"200px\">". $escaper->escapeHtml($lang['SiteLocation']) ."</th>\n";
                echo "<th align=\"left\" width=\"200px\">". $escaper->escapeHtml($lang['Team']) ."</th>\n";
                echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['InherentRisk']) ."</th>\n";
                echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";

            foreach($risks as $risk)
            {
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
                echo "<tbody>\n";
                echo "<tr>\n";
                    echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=".$risk_id."\">".$risk_id."</a></td>\n";
                    echo "<td align=\"left\" width=\"150px\">". $escaper->escapeHtml($status) ."</td>\n";
                    echo "<td align=\"left\" width=\"300px\">". $escaper->escapeHtml($subject) ."</td>\n";
                    echo "<td align=\"left\" width=\"200px\">". $escaper->escapeHtml($location) ."</td>\n";
                    echo "<td align=\"left\" width=\"200px\">". $escaper->escapeHtml($team) ."</td>\n";
                    echo "<td align=\"center\" class=\"risk-cell\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($calculated_risk) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></td>\n";
                    echo "<td align=\"center\" width=\"100px\">". $dayssince ."</td>\n";
                echo "</tr>\n";
            }

            // End the last table
            echo "</tbody>\n";
            echo "</table>\n";
        } 
        // Controls by Risks
        elseif ($report == 1){
            // Get the variables for the row
            $origin_risk_id = $risks[0]['id'];
            $risk_id = convert_id($origin_risk_id);
            $status = $risks[0]['status'];
            $subject = try_decrypt($risks[0]['subject']);
            $calculated_risk = $risks[0]['calculated_risk'];
            
            // Get the risk color
            $color = get_risk_color($calculated_risk);

            echo '<table width="100%" class="table table-bordered table-condensed" role="grid" style="width: 100%;">
                    <tbody>
                        <tr>
                            <th style="background-color:' . $escaper->escapeHtml($color) . '" bgcolor="' . $escaper->escapeHtml($color) . '" colspan="5">
                                <center>
                                    <font color="#000000">'. $escaper->escapeHtml($lang['RiskId']) . ':&nbsp;&nbsp;
                                    <a href="../management/view.php?id='. $escaper->escapeHtml($risk_id) . '" style="color:#000000">'. $escaper->escapeHtml($risk_id) .'</a>
                                    <br>'. $escaper->escapeHtml($lang['Subject']) .':&nbsp;&nbsp;' . $escaper->escapeHtml($subject) . '
                                    <br>'. $escaper->escapeHtml($lang['InherentRisk']) .':&nbsp;&nbsp;'. $escaper->escapeHtml($calculated_risk) .'&nbsp;&nbsp;('. $escaper->escapeHtml(get_risk_level_name($calculated_risk)) .')
                                    </font>
                                </center>
                            </th>
                        </tr>
                        <tr role="row" style="height: 0px;">
                            <th class="sorting_asc" aria-controls="mitigation-controls-table140955b56e1c6c5879" rowspan="1" colspan="1" style="width: 0px; padding-top: 0px; padding-bottom: 0px; border-top-width: 0px; border-bottom-width: 0px; height: 0px;" aria-sort="ascending" aria-label="&amp;nbsp;: activate to sort column descending">
                                <div class="dataTables_sizing" style="height:0;overflow:hidden;">&nbsp;
                                </div>
                            </th>
                        </tr>
                            
                    ';
                    
                    
                    foreach($risks as $gr_id => $control){
                        $control_id = $control['control_id'];
                        $control_long_name = $control['control_long_name'];
                        $control_long_name = $control['control_long_name'];
                        echo '<tr role="row" class="odd">
                                <td class="sorting_1">
                                    <div class="control-block item-block clearfix">
                                        <div class="control-block--header clearfix" data-project="">
                                            <a href="#" id="show-' . $origin_risk_id . '-' . $control_id . '" class="show-score" data-control-id="'. $escaper->escapeHtml($control_id) .'" data-risk-id="'. (int)$origin_risk_id .'"  onclick="" style="color: #3f3f3f;"> 
                                                    <i class="fa fa-caret-right"></i>&nbsp; 
                                            <strong>' . $escaper->escapeHtml($lang['ControlLongName']) . '</strong>: &nbsp; &nbsp;'. $escaper->escapeHtml($control_long_name) .'
                                            </a>
                                            <a href="#" id="hide-' . $origin_risk_id . '-' . $control_id . '" class="hide-score" style="display: none;color: #3f3f3f; float: left;" data-control-id="'. $escaper->escapeHtml($control_id) .'" data-risk-id="'. (int)$origin_risk_id .'" > 
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

            echo "</tbody>
                </table>\n";
            
        }
    }

    echo '
        <script>
            $(document).ready( function(){
                $(".hide-score").css("display","none");
                $(".show-score").click(function(e){
                    e.preventDefault()
                    var control_id = $(this).data("control-id")
                    var risk_id = $(this).data("risk-id")
                    showControlDetails(control_id, risk_id)
                })
                
                $(".hide-score").click(function(e){
                    e.preventDefault()
                    var control_id = $(this).data("control-id")
                    var risk_id = $(this).data("risk-id")
                    hideControlDetails(control_id, risk_id)
                })
            });
            
            function showControlDetails( control_id , risk_id ){
            
                $("#show-"+risk_id + "-" +control_id).hide();
                $("#hide-"+risk_id + "-" +control_id).css("display","block");
                $("#control-content-"+risk_id + "-" +control_id).css("display","block");
                var height = $(window).scrollTop();
                
                $.ajax({
                    url: "/api/mitigation_controls/get_mitigation_control_info",
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

    // Close the database
    db_close($db);
}

/*******************************
 * FUNCTION: GET CONTROLS NAME *
 *******************************/
function get_control_number( $control_numbers )
{
    if ( $control_numbers ) {

        $control_number = explode(',', $control_numbers);
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

    return $array[0]['count'];
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

    return $array[0]['count'];
}

function get_risks_by_appetite($type, $start, $length, $orderColumn, $orderDir) {

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

    $orderColumns = ['id', 'subject', 'calculated_risk', 'residual_risk'];
    $orderColumn = $orderColumns[$orderColumn];

    // Make the big query
    $query = "
        SELECT
            a.id,
            a.subject,
            b.calculated_risk,
            ROUND(b.calculated_risk - (b.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100), 2) as residual_risk
        FROM
            risks a
            LEFT JOIN risk_scoring b ON a.id = b.id
            LEFT JOIN mitigations p ON a.id = p.risk_id
            LEFT JOIN `mitigation_to_controls` mtc ON p.id = mtc.mitigation_id 
            LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0 
            LEFT JOIN `risk_to_team` rtt on a.id=rtt.risk_id
            LEFT JOIN `risk_to_additional_stakeholder` rtas on a.id=rtas.risk_id
        WHERE 1=1
            {$separation_query}
        GROUP BY
            a.id
        HAVING
            " . ($type === 'out' ? "residual_risk > :risk_appetite" : "residual_risk <= :risk_appetite") . "
        ORDER BY
           {$orderColumn} {$orderDir} 
        ";

    $limitQuery = $length == -1 ? "" : "Limit {$start}, {$length}";

    $query = "
        SELECT SQL_CALC_FOUND_ROWS t1.*
        FROM (
            {$query}
        ) t1
        {$limitQuery}
    ";

    $risk_appetite = get_setting("risk_appetite", 0);

    // Query the database
    $db = db_open();

    $stmt = $db->prepare($query);
    $stmt->bindParam(":risk_appetite", $risk_appetite, PDO::PARAM_STR);
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

    // Return the data array
    return array(
        "data" => $data,
        "recordsTotal" => $rowCount,
        "recordsFiltered" => count($data),
    );
}

function display_appetite_datatable($within=true) {

    global $lang, $escaper;

    $type = ($within ? "in" : "out");
    $tableID = ($within ? "within" : "outside") . "-appetite-table";

    echo "
        <div class='table-container' data-id=\"{$tableID}\">
            <table id=\"{$tableID}\" width=\"100%\" data-type='$type' class=\"risk-datatable table table-bordered table-striped table-condensed\">
                <thead>
                    <tr>
                        <th data-name='id' align=\"left\" valign=\"top\">".$escaper->escapeHtml($lang['ID'])."</th>
                        <th data-name='subject' align=\"left\" valign=\"top\">".$escaper->escapeHtml($lang['Subject'])."</th>
                        <th data-name='calculated_risk' align=\"center\" valign=\"top\">".$escaper->escapeHtml($lang['InherentRisk'])."</th>
                        <th data-name='residual_risk' align=\"center\" valign=\"top\">".$escaper->escapeHtml($lang['ResidualRisk'])."</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    ";
}

?>
