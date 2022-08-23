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
        $stmt = $db->prepare("SELECT id, CASE WHEN mitigation_id = 0 THEN 'Unplanned' WHEN mitigation_id != 0 THEN 'Planned' END AS name FROM `risks` WHERE status != \"Closed\" ORDER BY name");
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
                    if(!isset($grouped_array[$counter]['num'])) $grouped_array[$counter]['num'] = 0;

                        // Add the value to the grouped array
                        $grouped_array[$counter]['name'] = $row['name'];
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

			if ($row['name'] == "Planned")
			{
				$color_array[] = "green";
			}
			else if ($row['name'] == "Unplanned")
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
                    if(!isset($grouped_array[$counter]['num'])) $grouped_array[$counter]['num'] = 0;
                        // Add the value to the grouped array
                        $grouped_array[$counter]['name'] = $row['name'];
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
            if(!isset($grouped_array[$counter]['num'])) $grouped_array[$counter]['num'] = 0;
            // Add the value to the grouped array
            $grouped_array[$counter]['name'] = $row['name'];
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

    // Start with an empty review status;
    $review_status = "";

    foreach ($risks as $key => $risk){
        $risk_id = $risk['id'];
        $subject = $risk['subject'];
        $status = $risk['status'];
        $calculated_risk = $risk['calculated_risk'];
        $color = get_risk_color($risk['calculated_risk']);
        $risk_level = get_risk_level_name($risk['calculated_risk']);
        $residual_risk_level = get_risk_level_name($risk['residual_risk']);
        $dayssince = $risk['days_open'];

        // If next_review_date_uses setting is Residual Risk.
        if(get_setting('next_review_date_uses') == "ResidualRisk")
        {
            $next_review = next_review($residual_risk_level, $risk_id, $risk['next_review'], false);
            $next_review_html = next_review($residual_risk_level, $risk_id, $risk['next_review']);
        }
        // If next_review_date_uses setting is Inherent Risk.
        else
        {
            $next_review = next_review($risk_level, $risk_id, $risk['next_review'], false);
            $next_review_html = next_review($risk_level, $risk_id, $risk['next_review']);
        }
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
                echo "<table class=\"table table-bordered table-condensed sortable risk-table\">\n";
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
    echo "
    <script>
        $(document).ready(function(){
            $('.risk-table').each(function(i){
                $(this).find('thead tr:eq(1)').clone(true).appendTo($(this).find('thead'));
                $(this).find('thead tr:eq(2) th').each( function (i) {
                    var title = $(this).text();
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    $( 'input, select', this ).on( 'keyup change', function () {
                        if ( riskTable.column(i).search() !== this.value ) {
                            riskTable.column(i).search( this.value ).draw();
                        }
                    });
                });
                var riskTable = $(this).DataTable( {
                    paging: false,
                    orderCellsTop: true,
                    fixedHeader: true,
                    dom : 'lrti'
                });
            });

         });
    </script>
    ";
}

/************************************
 * FUNCTION: RISKS AND ASSETS TABLE *
 ************************************/
function risks_and_assets_table($report, $sort_by, $asset_tags_in_array, $projects_in_array)
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

    $rows = get_risks_and_assets_rows($report, $sort_by, $asset_tags_in_array, $projects_in_array);

    // If risks by asset
    if ($report == 0) {
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
            
            $total_calculated_risk = 0;
            $total_residual_risk = 0;
            $array_residual_risk = [];

            $risk_html = "";
            foreach($group as $row) {

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
                $risk_html .= "<tr>\n";
                $risk_html .= "<td align=\"left\" width=\"50px\"><a target='_blank' href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                $risk_html .= "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($status) . "</td>\n";
                $risk_html .= "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                $risk_html .= "<td align=\"left\" width=\"200px\">" . $escaper->escapeHtml($risk_location) . "</td>\n";
                $risk_html .= "<td align=\"left\" width=\"200px\">" . $escaper->escapeHtml($risk_teams) . "</td>\n";
                $risk_html .= "<td align=\"center\" class=\"risk-cell\" bgcolor=\"" . $escaper->escapeHtml($color1) . "\" width=\"100px\">" . $escaper->escapeHtml($calculated_risk) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color1) . "\"></span></td>\n";
                $risk_html .= "<td align=\"center\" class=\"risk-cell\" bgcolor=\"" . $escaper->escapeHtml($color2) . "\" width=\"100px\">" . $escaper->escapeHtml($residual_risk) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color2) . "\"></span></td>\n";
                $risk_html .= "<td align=\"center\" width=\"100px\">" . $escaper->escapeHtml($mitigation_percent) . " %</td>\n";
                $risk_html .= "<td align=\"center\" width=\"100px\">" . $escaper->escapeHtml($dayssince) . "</td>\n";
                $risk_html .= "</tr>\n";
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
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            if ($type == 'asset') {
                $asset_value = $group[0]['asset_value'];
                $asset_location = isset($group[0]['asset_location']) ? $group[0]['asset_location'] : "N/A";
                $asset_teams = isset($group[0]['asset_teams']) ? $group[0]['asset_teams'] : "N/A";
                echo "<th style=\"background-color: " .$escaper->escapeHtml($color). "\" colspan=\"9\">
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
                    </th>\n";
            } else {
                $max_value = $group[0]['max_value'];
                echo "<th style=\"background-color: " .$escaper->escapeHtml($color). "\" colspan=\"9\">
                        <center>
                            " . $escaper->escapeHtml($lang['AssetGroupName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($name) . "<br />
                            " . $escaper->escapeHtml($lang['AssetTags']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($tags) . "<br />
                            " . $escaper->escapeHtml($lang['GroupMaximumQuantitativeLoss']) . ":&nbsp;&nbsp;$" . $escaper->escapeHtml(number_format($max_value)) . "<br />
                            " . $escaper->escapeHtml($lang['HighestInherentRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($calculated_risk) ."<br />
                            " . $escaper->escapeHtml($lang['AverageInherentRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($average_calculated_risk) ."<br />
                            " . $escaper->escapeHtml($lang['HighestResidualRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml(max($array_residual_risk)) ."<br />
                            " . $escaper->escapeHtml($lang['AverageResidualRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($average_residual_risk) ."<br />
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
            echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['ResidualRisk']) ."</th>\n";
            echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['MitigationPercent']) ."</th>\n";
            echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            echo $risk_html;            

            echo "</tbody>\n";
            echo "</table>\n";
        }
    }
    // If assets by risk
    elseif ($report == 1) {
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
            echo "<th style=\"background-color:" . $escaper->escapeHtml($color) . "\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" colspan=\"7\">
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
            echo "<th align=\"left\" width='12%'>". $escaper->escapeHtml($lang['SiteLocation']) ."</th>\n";
            echo "<th align=\"left\" width='12%'>". $escaper->escapeHtml($lang['Teams']) ."</th>\n";
            echo "<th align=\"left\" width='12%'>". $escaper->escapeHtml($lang['AssetTags']) ."</th>\n";
            echo "<th align=\"left\" width='12%'>". $escaper->escapeHtml($lang['AssetGroups']) ."</th>\n";
            echo "<th align=\"left\" width='12%'>". $escaper->escapeHtml($lang['AssetValuation']) ."</th>\n";
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
                $tags = isset($row['tags']) ? $row['tags'] : "N/A";
                $asset_groups = isset($row['asset_groups']) ? $row['asset_groups'] : "N/A";

                // Display the individual asset information
                echo "<tr>\n";
                echo "<td align='left'>" . $escaper->escapeHtml($asset_name) . "</td>\n";
                echo "<td align='left'>" . $escaper->escapeHtml($asset_ip) . "</td>\n";
                echo "<td align='left'>" . $escaper->escapeHtml($asset_location) . "</td>\n";
                echo "<td align='left'>" . $escaper->escapeHtml($asset_teams) . "</td>\n";
                echo "<td align='left'>" . $escaper->escapeHtml($tags) . "</td>\n";
                echo "<td align='left'>" . $escaper->escapeHtml($asset_groups) . "</td>\n";
                echo "<td align='left'>" . $escaper->escapeHtml(get_asset_value_by_id($asset_value)) . "</td>\n";
                echo "</tr>\n";
            }

            echo "<tr><td style=\"background-color:" . $escaper->escapeHtml($color) . "\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" colspan=\"7\"></td></tr>\n";
            echo "<tr>\n";
            echo "<td style=\"background-color: lightgrey\" align=\"left\" width=\"50px\" colspan=\"6\"><b>" . $escaper->escapeHtml($lang['MaximumQuantitativeLoss']) . "</b></td>\n";
            echo "<td style=\"background-color: lightgrey\" align=\"left\" width=\"50px\"><b>$" . $escaper->escapeHtml(number_format($asset_valuation)) . "</b></td>\n";
            echo "</tr>\n";
            echo "</tbody>\n";
            echo "</table>\n";
        }
    }
}
/************************************************
 * FUNCTION: RETURN RISKS AND ASSETS REPORT SQL *
 ************************************************/
function get_risks_and_assets_rows($report=0, $sort_by=0, $asset_tags_in_array, $projects_in_array)
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
                $wheres[] = " u.project_id IS NULL ";
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
                GREATEST(IFNULL(m.mitigation_percent, 0), IFNULL(MAX(fc.mitigation_percent), 0)) AS mitigation_percent,
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
        foreach($bind_params as $key => $value){
            $stmt->bindParam($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

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
                $wheres[] = " u.project_id IS NULL ";
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
        foreach($bind_params as $key => $value){
            $stmt->bindParam($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

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
function risks_and_issues_table($risk_tags, $start_date, $end_date)
{
    global $lang;
    global $escaper;

    $risk_levels = get_risk_levels();
    echo "<table class=\"\" style='table-layout:fixed;'>\n";
    echo "<tr>\n";
    echo "<td style='width:100px; font-weight:bold;'>".$escaper->escapeHtml($lang['Trend'])." : </td>";
    $trend_lavel = $escaper->escapeHtml($lang['Increasing'])." &#8593; ; ";
    $trend_lavel .= $escaper->escapeHtml($lang['Decreasing'])." &#8595; ; ";
    $trend_lavel .= $escaper->escapeHtml($lang['NoChange'])." &#8596; ; ";
    echo "<td >".$trend_lavel."</td>";
    echo "<tr>\n";
    echo "<td style='width:100px; font-weight:bold;'>".$escaper->escapeHtml($lang['Status'])." : </td>";
    $status_lavel = "";
    foreach (array_reverse($risk_levels) as $level) {
        $status_lavel .= "<span class='risk-color1' style='width:20px; height: 20px; position: relative; display:block; float:left; border: 1px solid; background-color:".$level['color']."'></span>";
        $status_lavel .= "<span style='position: static; display:block; float:left; margin: 0 20px 0 10px'>(".$escaper->escapeHtml($level['display_name']). ");</span>";
    }
    $status_lavel .= "<span class='risk-color1' style='width:20px; height: 20px; position: relative; display:block; float:left; border: 1px solid; background-color: white'></span>";
    $status_lavel .= "<span style='position: static; display:block; float:left; margin: 0 20px 0 10px'>(".$escaper->escapeHtml($lang['Insignificant']). ");</span>";
    echo "<td >".$status_lavel."</td>";
    echo "<tr>\n";
    echo "</table>\n";

    $rows = get_risks_and_issues_rows($risk_tags, $start_date, $end_date);
    echo "<table class=\"table table-bordered table-condensed\" style='table-layout:fixed;'>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th width='10%'>".$escaper->escapeHtml($lang['Category'])."</th>";
    echo "<th width='8%'>".$escaper->escapeHtml($lang['Status'])."</th>";
    echo "<th width='8%'>".$escaper->escapeHtml($lang['Trend'])."</th>";
    echo "<th width='74%'>".$escaper->escapeHtml($lang['Details'])."</th>";
    echo "</thead>\n";
    $categories = [];
    foreach($rows as $risk) {
        $categories[$risk['category']][] = $risk;
    }
    foreach($rows as $index => $risk) {
        $color = get_risk_color($risk['residual_risk']);
        $risk_id = $risk['id'] + 1000;
        $trend = $risk['residual_risk_start'] . " == " . $risk['residual_risk_end'];
        if($risk['residual_risk_start'] == $risk['residual_risk_end']) $trend = "&#8596;";
        else if($risk['residual_risk_start'] < $risk['residual_risk_end']) $trend = "&#8593;";
        else $trend = "&#8595;";
        $details = "";
        $title = "<h3>". $risk_id . " : " . $escaper->escapeHtml(try_decrypt($risk['subject'])) . "</h3>";
        $title = "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\" target=\"_blank\">".$title."</a>";
        $details = $title;
        $details .= "<ul>";
        if($risk['assessment']) {
            $details .= "<li>".$escaper->escapeHtml(try_decrypt($risk['assessment']))."</li>";
        }
        if($risk['notes']) {
            $details .= "<li>".$escaper->escapeHtml(try_decrypt($risk['notes']))."</li>";
        }
        $comments = get_comments($risk_id, false);
        if(count($comments) > 0){
            foreach ($comments as $comment) {
                $details .= "<li>".format_date($comment['date'])." [ ".$comment['name']." ] : ".$escaper->escapeHtml(try_decrypt($comment['comment']))."</li>";
            }
        }
        $details .= "</ul>";
        echo "<tr>\n";
        if($index == 0 || $rows[$index-1]['category'] != $risk['category']){
            echo "<td rowspan='".count($categories[$risk['category']])."'>".$escaper->escapeHtml($risk['category_name'])."</td>";
        } 
        echo "<td style='background-color:" .$escaper->escapeHtml($color). "'></td>";
        echo "<td style='text-align:center; font-weight:bold; font-size: 30px;'>".$trend."</td>";
        echo "<td style='word-wrap: break-word;'>".$details."</td>";
        echo "</tr>\n";
    }
    echo "</tr>\n";
    echo "</table>\n";
    exit;
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
            ROUND((b.calculated_risk - (b.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) AS residual_risk,
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
        ORDER By a.category, ROUND((b.calculated_risk - (b.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) DESC, a.submission_date
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
function get_risks_by_table($status, $sort=0, $group=0, $table_columns=[])

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
    // If Import/Export extra is disabled, hide print button by group
    if (!import_export_extra())
    {
        echo "
            <style>
                .print-by-group{
                    display: none;
                }
            </style>
        ";
    }
    
    // If the group name is none
    if ($group_name == "none")
    {
        // Display the table header
        echo "<table name=\"risks\" id=\"risks\" data-group='' class=\"table risk-datatable table-bordered table-striped table-condensed  table-margin-top\" style='width: 100%'>\n";
        echo "<thead>\n";
        echo "<tr class='main'>\n";

        // Header columns go here
        get_header_columns(false, $table_columns);

        echo "</tr>\n";
        echo "<tr class='filter'>\n";
        // Header columns go here
        get_header_columns(false, $table_columns);
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";
        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";
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
                        $group_values_including_empty = str_getcsv($initial_group_value);
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
                //$group_value = $group_values[0];

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
                        
                        $length = count($table_columns);

                        echo "<th bgcolor=\"#0088CC\" colspan=\"{$length}\"><center>". $escaper->escapeHtml($group_value) ."</center></th>\n";
                        echo "</tr>\n";
                        echo "<tr class='main'>\n";

                        // Header columns go here
                        get_header_columns(false, $table_columns);

                        echo "</tr>\n";
                        echo "<tr class='filter'>\n";
                        // Header columns go here
                        get_header_columns(false, $table_columns);
                        echo "</tr>\n";
                        echo "</thead>\n";
                        echo "<tbody>\n";
                        echo "</tbody>\n";
                        echo "</table>\n";
                        echo "<br />\n";
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
                        $data_row[] = "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($row['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($row['id'])."</a>";
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
function get_header_columns($hide, $selected_columns=[])
{
    global $lang;
    global $escaper;

    if($hide){
        $display = "display: none;";
    }else{
        $display = "display: table-cell;";
    }

    foreach($selected_columns as $column=>$status){
        if(stripos($column, "custom_field_") === false){
            $name = get_label_by_risk_field_name($column);
            echo "<th class='{$column}' data-name='{$column}' " . ($status == true ? "" : "style=\"{$display}\" ") . "align=\"left\" >". $name ."</th>\n"; 
        } else {
            // If customization extra is enabled, includes customization fields 
            if(customization_extra()){
                $custom_cols = "";
                
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                $field_id = str_replace("custom_field_", "", $column);
                $custom_field = get_field_by_id($field_id);
                $label = $escaper->escapeHtml($custom_field['name']);
                echo  "<th class=\"custom_field_{$field_id}\" data-name='".$column."' align=\"left\" width=\"50px\" valign=\"top\">".$label."</th>";
            }
        }
    }
}

/**********************************
 * FUNCTION: TABLE OF RISK BY TEAM *
 *********************************/
function risk_table_open_by_team($selected_columns=[]){

    global $lang;
    global $escaper;

    // Display the table header
    echo "<table data-group='' class=\"table risk-datatable table-bordered table-striped table-condensed  table-margin-top\" style='width: 100%'>\n";
    echo "<thead>\n";
    echo "<tr class='main'>\n";

    // Header columns go here
    get_header_columns(false, $selected_columns);

    echo "</tr>\n";
    echo "<tr class='filter'>\n";
    // Header columns go here
    get_header_columns(false, $selected_columns);
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    
    // End the table
    echo "</tbody>\n";
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
            $total_string = "<font color=\"green\">" . $total[$i] . "</font>";
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
        ROUND((b.calculated_risk - (b.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) AS residual_risk,

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
            ELSE ROUND((`b`.`calculated_risk` - (`b`.`calculated_risk` * GREATEST(IFNULL(`p`.`mitigation_percent`, 0), IFNULL(MAX(`fc`.`mitigation_percent`), 0))  / 100)), 2)
        END AS residual_risk_30,
        CASE 
            WHEN DATEDIFF(NOW(), `a`.`submission_date`) < 60 THEN '--'
            WHEN NOT(ISNULL(`rrsh_lua_60`.`residual_risk`)) THEN `rrsh_lua_60`.`residual_risk`
            WHEN NOT(ISNULL(`rrsh_lua_90`.`residual_risk`)) THEN `rrsh_lua_90`.`residual_risk`
            ELSE ROUND((`b`.`calculated_risk` - (`b`.`calculated_risk` * GREATEST(IFNULL(`p`.`mitigation_percent`, 0), IFNULL(MAX(`fc`.`mitigation_percent`), 0))  / 100)), 2)
        END AS residual_risk_60,
        CASE 
            WHEN DATEDIFF(NOW(), `a`.`submission_date`) < 90 THEN '--'
            WHEN NOT(ISNULL(`rrsh_lua_90`.`residual_risk`)) THEN `rrsh_lua_90`.`residual_risk`
            ELSE ROUND((`b`.`calculated_risk` - (`b`.`calculated_risk` * GREATEST(IFNULL(`p`.`mitigation_percent`, 0), IFNULL(MAX(`fc`.`mitigation_percent`), 0))  / 100)), 2)
        END AS residual_risk_90,

        GROUP_CONCAT(DISTINCT `rc`.`name` SEPARATOR ',') AS risk_mapping,
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

        NOT(ISNULL(mau.id)) mitigation_accepted, 
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
                GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR ',')
            FROM
                tags t, tags_taggees tt 
            WHERE
                tt.tag_id = t.id AND tt.taggee_id=a.id AND tt.type='risk'
        ) AS risk_tags,
        DATEDIFF(IF(a.status != 'Closed', NOW(), o.closure_date) , a.submission_date) days_open,
        rc.number risk_mapping_risk,
        rc.name risk_mapping_risk_event,
        rc.description risk_mapping_description,
        rg.name risk_mapping_risk_grouping,
        rf.name risk_mapping_function,
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
        GROUP_CONCAT(DISTINCT CONCAT(rc.name, '{$delimiter}', rc.id)  SEPARATOR ', ') AS risk_mapping,
        GROUP_CONCAT(DISTINCT CONCAT(tc.name, '{$delimiter}', tc.id)  SEPARATOR ', ') AS threat_mapping,

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
        cu.name AS closed_by, 
        CONCAT(cu.name, '{$delimiter}', cu.value) AS closed_by_for_dropdown,
        cr.name as close_reason,
        CONCAT(cr.name, '{$delimiter}', cr.value) AS close_reason_for_dropdown, 
        
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
                GROUP_CONCAT(DISTINCT CONCAT(t.tag, '{$delimiter}', t.id) ORDER BY t.tag ASC SEPARATOR '|')
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
function risks_query_from($column_filters=[], $risks_by_team=0, $orderColumnName=null)
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
            LEFT JOIN user cu FORCE INDEX(PRIMARY) ON o.user_id = cu.value
            LEFT JOIN close_reason cr ON cr.value = o.close_reason
            LEFT JOIN mitigations p ON a.id = p.risk_id
            LEFT JOIN `mitigation_to_controls` mtc ON p.id = mtc.mitigation_id
            LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            LEFT JOIN planning_strategy q FORCE INDEX(PRIMARY) ON p.planning_strategy = q.value
            LEFT JOIN mitigation_effort r FORCE INDEX(PRIMARY) ON p.mitigation_effort = r.value
            LEFT JOIN asset_values s ON p.mitigation_cost = s.id
            LEFT JOIN user t FORCE INDEX(PRIMARY) ON p.mitigation_owner = t.value
            LEFT JOIN mitigation_accept_users mau ON a.id=mau.risk_id
            LEFT JOIN source v FORCE INDEX(PRIMARY) ON a.source = v.value

            LEFT JOIN `temp_rsh_last_update_age` rsh_lua_30 ON `rsh_lua_30`.`risk_id` = `a`.`id` AND `rsh_lua_30`.`age_range` = '30-60'
            LEFT JOIN `temp_rsh_last_update_age` rsh_lua_60 ON `rsh_lua_60`.`risk_id` = `a`.`id` AND `rsh_lua_60`.`age_range` = '60-90'
            LEFT JOIN `temp_rsh_last_update_age` rsh_lua_90 ON `rsh_lua_90`.`risk_id` = `a`.`id` AND `rsh_lua_90`.`age_range` = '90+'

            LEFT JOIN `temp_rrsh_last_update_age` rrsh_lua_30 ON `rrsh_lua_30`.`risk_id` = `a`.`id` AND `rrsh_lua_30`.`age_range` = '30-60'
            LEFT JOIN `temp_rrsh_last_update_age` rrsh_lua_60 ON `rrsh_lua_60`.`risk_id` = `a`.`id` AND `rrsh_lua_60`.`age_range` = '60-90'
            LEFT JOIN `temp_rrsh_last_update_age` rrsh_lua_90 ON `rrsh_lua_90`.`risk_id` = `a`.`id` AND `rrsh_lua_90`.`age_range` = '90+'

            LEFT JOIN risk_catalog rc ON FIND_IN_SET(rc.id, a.risk_catalog_mapping) > 0
            LEFT JOIN threat_catalog tc ON FIND_IN_SET(tc.id, a.threat_catalog_mapping) > 0
            LEFT JOIN risk_grouping rg ON rc.grouping = rg.value
            LEFT JOIN risk_function rf ON rc.function = rf.value
    ";
    // If the team separation extra is enabled
    $team_separation_extra = team_separation_extra();
    if(!empty($column_filters['location'])){
        $query .= " LEFT JOIN risk_to_location rtl ON a.id=rtl.risk_id ";
    }
    if(!empty($column_filters['team']) || $team_separation_extra || $risks_by_team){
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

    $contributing_risks = get_contributing_risks();
    foreach($contributing_risks as $contributing_risk) {
        $id = $contributing_risk['id'];
        $query .= "
            LEFT JOIN `temp_contributing_risk_impact_data` cri_data_{$id} FORCE INDEX(PRIMARY) ON `cri_data_{$id}`.`risk_scoring_id` = `a`.`id` AND `cri_data_{$id}`.`contributing_risks_id` = {$id}
        ";
    }

    $query .= " LEFT JOIN contributing_risks_likelihood cr_likelihood FORCE INDEX(crl_index) ON cr_likelihood.value = b.Contributing_Likelihood \n";
    $query .= " LEFT JOIN likelihood FORCE INDEX(likelihood_index) ON likelihood.value = b.CLASSIC_likelihood \n";
    $query .= " LEFT JOIN impact FORCE INDEX(impact_index) ON impact.value = b.CLASSIC_impact \n";
    
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

                    $query .= " LEFT JOIN custom_risk_data {$table_alias} ON a.id={$table_alias}.risk_id AND {$table_alias}.field_id={$custom_field_id} AND ( {$table_alias}.review_id=0 OR {$table_alias}.review_id=a.mgmt_review ) ";

                    if($table_alias == $orderColumnName) $join_custom_table = true;
                }
            }
        }
        if(!$join_custom_table && stripos($orderColumnName, "custom_field_") !== false){
            $custom_field_id = (int)str_replace("custom_field_", "", $orderColumnName);
            if($custom_field_id)
            {
                $table_alias = "custom_field_".$custom_field_id;

                $query .= " LEFT JOIN custom_risk_data {$table_alias} ON a.id={$table_alias}.risk_id AND {$table_alias}.field_id={$custom_field_id} AND ( {$table_alias}.review_id=0 OR {$table_alias}.review_id=a.mgmt_review ) ";

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
                    $sort_name = "`{$orderColumnName}` {$orderDir} ";
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
        /*(4) Create the 'temporary' table for data from the `residual_risk_scoring_history` table*/
        CREATE TABLE `temp_rrsh_last_update_age{$unique_key}`(
            PRIMARY KEY(`id`),
            INDEX (`risk_id`),
            INDEX (`age_range`)
        )
        /*(2b) Create the CTE to be used in the table's select*/
        WITH `rrsh_last_update_age_base` AS(
            /*(2a) Add the information on what age range it's in*/
            SELECT
                CASE
                    WHEN `lua`.`age` < 30 THEN '-30'
                    WHEN `lua`.`age` >= 30 AND `lua`.`age` < 60 THEN '30-60'
                    WHEN `lua`.`age` >= 60 AND `lua`.`age` < 90 THEN '60-90'
                    WHEN `lua`.`age` > 90 THEN '90+'
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
                ) lua
        )
        /*(3) Select the latest entry for each age range*/
        SELECT
            `lua`.*
        FROM
            `rrsh_last_update_age_base` lua
            LEFT JOIN `rrsh_last_update_age_base` lua2 ON `lua`.`risk_id` = `lua2`.`risk_id` AND `lua`.`age_range` = `lua2`.`age_range` AND `lua`.`last_update` < `lua2`.`last_update`
        WHERE
            `lua2`.`id` IS NULL;
        
        /*(4) Create the 'temporary' table for data from the `risk_scoring_history` table*/
        CREATE TABLE `temp_rsh_last_update_age{$unique_key}`(
            PRIMARY KEY(`id`),
            INDEX (`risk_id`),
            INDEX (`age_range`)
        )
        /*(2b) Create the CTE to be used in the table's select*/
        WITH `rsh_last_update_age_base` AS (
            /*(2a) Add the information on what age range it's in*/
            SELECT
                CASE
                    WHEN `lua`.`age` < 30 THEN '-30'
                    WHEN `lua`.`age` >= 30 AND `lua`.`age` < 60 THEN '30-60'
                    WHEN `lua`.`age` >= 60 AND `lua`.`age` < 90 THEN '60-90'
                    WHEN `lua`.`age` > 90 THEN '90+'
                END AS age_range,
                `lua`.*
            FROM 
                (/*(1) Select the base data from the `residual_risk_scoring_history` table plus calculate the age of each entry*/
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
                ) lua
        )
        /*(3) Select the latest entry for each age range*/
        SELECT
            `lua`.*
        FROM
            `rsh_last_update_age_base` lua
            LEFT JOIN `rsh_last_update_age_base` lua2 ON `lua`.`risk_id` = `lua2`.`risk_id` AND `lua`.`age_range` = `lua2`.`age_range` AND `lua`.`last_update` < `lua2`.`last_update`
        WHERE
            `lua2`.`id` IS NULL;

        /*Create the 'temporary' table for data from the `risk_scoring_contributing_impacts` and `contributing_risks_impact` tables*/
        CREATE TABLE `temp_contributing_risk_impact_data{$unique_key}`(
            PRIMARY KEY(`risk_scoring_id`, `contributing_risks_id`),
            INDEX (`risk_scoring_id`),
            INDEX (`contributing_risks_id`)
        )
        SELECT
            `rs_impacts`.`risk_scoring_id`,
            `cs_impacts`.`contributing_risks_id`,
            `cs_impacts`.`value`,
            `cs_impacts`.`name`
        FROM
        	`risk_scoring_contributing_impacts` rs_impacts
          	LEFT JOIN `contributing_risks_impact` cs_impacts ON `cs_impacts`.`value` = `rs_impacts`.`impact` AND `cs_impacts`.`contributing_risks_id` = `rs_impacts`.`contributing_risk_id`;
    ";
    
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

    $query .= " FROM ".risks_query_from($column_filters, $risks_by_team, $orderColumnName)."\n"
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

    $drop_temporary_tables = "
        /*Drop pre-existing 'temporary' tables.*/
        DROP TABLE IF EXISTS `temp_rrsh_last_update_age{$unique_key}`;
        DROP TABLE IF EXISTS `temp_rsh_last_update_age{$unique_key}`;
        DROP TABLE IF EXISTS `temp_contributing_risk_impact_data{$unique_key}`;
    ";

    return [
        $query,
        $group_name,
        $create_temporary_tables,
        $drop_temporary_tables
    ];
}

// A function to clean up tables that might have stayed behind
function drr_temp_table_cleanup() {
    
    // Open the database connection
    $db = db_open();
    
    // Get the DRR temp tables that aren't deleted yet
    $database = DB_DATABASE; //Have to make a variable as bindParam can't take parameter by reference
    $stmt = $db->prepare("SELECT `table_name` FROM `information_schema`.`tables` WHERE `table_schema` = :database AND (`table_name` LIKE 'temp_rrsh_last_update_age_%' OR `table_name` LIKE 'temp_rsh_last_update_age_%' OR `table_name` LIKE 'temp_contributing_risk_impact_data_%');");
    $stmt->bindParam(":database", $database, PDO::PARAM_STR);
    $stmt->execute();

    // Fetch the results
    $table_names = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // If there're tables left behind
    if (!empty($table_names)) {
        // Save the current time so we're not getting it multiple times
        $now = time();

        // Iterate through the list of table names
        foreach ($table_names as $name) {
            // Temp table names are like tableName_creationTime_uniqueKey and we need to get the time from it
            preg_match('/temp_[a-z_]+_([\d]+)_[a-zA-Z0-9]{5}/i', $name, $matches);
            // Time is added to the temp table's name. We get the creation time and if 10 minutes passed we're safe to clean it up
            if (isset($matches[1]) && (int)$matches[1] + 600 < $now) {
                $stmt = $db->prepare("DROP TABLE `{$name}`;");
                $stmt->execute();
            }
        }
    }

    // Close the database connection
    db_close($db);
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
function get_risks_only_dynamic($need_total_count, $status, $sort, $group, $column_filters=[], &$rowCount, $start=0, $length=10, $group_value_from_db="", $custom_query="", $bind_params=[], $orderColumnName=null, $orderDir="asc", $risks_by_team=0, $teams=[], $owners=[], $ownersmanagers=[])
{
    global $lang;

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
                    $column_filter = implode(",", $column_filter);
                }

                $bind_param_name = "column_filter_". md5($name);

                switch($name){
                    // case "id":
                    //     $wheres[] = " a.id+1000 = :{$bind_param_name} ";
                    //     $bind_params[$bind_param_name] = $column_filter;
                    // break;
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
                    case "mitigation_percent":
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
                    case "calculated_risk":
                    case "residual_risk":
                    case "days_open":
                        $operator = get_operator_from_value($column_filters[$name."_operator"]);
                        $havings[] = " {$name} {$operator} :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "comments":
                        $wheres[] = " l.comments like :{$bind_param_name} ";
                        $bind_params[$bind_param_name] = "%{$column_filter}%";
                    break;
                    case "calculated_risk_30":
                    case "calculated_risk_60":
                    case "calculated_risk_90":
                    case "residual_risk_30":
                    case "residual_risk_60":
                    case "residual_risk_90":
                        $operator = get_operator_from_value($column_filters[$name."_operator"]);
                        $havings[] = " CONVERT({$name} ,DECIMAL(10,2)) {$operator} :{$bind_param_name} ";
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
                    case "risk_mapping":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(rc.id, :{$bind_param_name}) OR rc.id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(rc.id, :{$bind_param_name}) ";
                        $bind_params[$bind_param_name] = $column_filter;
                    break;
                    case "threat_mapping":
                        if($empty_filter) $wheres[] = "(FIND_IN_SET(tc.id, :{$bind_param_name}) OR tc.id IS NULL)";
                        else $wheres[] = " FIND_IN_SET(tc.id, :{$bind_param_name}) ";
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
                if(stripos($name, "risk_mapping_") !== false) {
                    $havings[] = " {$name} like :{$bind_param_name} ";
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
                return strcasecmp($a['encryption_order'], $b['encryption_order']);
            else 
                return strcasecmp($b['encryption_order'], $a['encryption_order']);
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
    drr_temp_table_cleanup();

    // Have to separately drop the required temporary tables
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
    list($query, $group_name, $create_temporary_tables, $drop_temporary_tables) = make_full_risks_sql($query_type=3, $status, -1, $group, [], $group_value_from_db, $custom_query, $bind_params, "");

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

    // Have to separately drop the required temporary tables
    $stmt = $db->prepare($drop_temporary_tables);
    $stmt->execute();

    db_close($db);
    
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
            "closed_by" => "closed_by_for_dropdown",
            "close_reason" => "close_reason_for_dropdown",
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
            "risk_mapping" =>  $risk['risk_mapping'], 
            "threat_mapping" =>  $risk['threat_mapping'], 
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
            "closed_by" => $risk["closed_by"],
            "close_reason" => $risk["close_reason"],
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
function risks_and_control_table($report, $sort_by=0, $projects)
{
    global $lang;
    global $escaper;

    if(count($_POST) > 3) {
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

    $rows = get_risks_and_controls_rows($report, $sort_by, $projects, $filters);
    

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
            $header_color = get_risk_color($risks[0]['calculated_risk']);
            $control_frameworks = get_mapping_control_frameworks($gr_id);
            if(count($control_frameworks)) {
                $cf_table = "<table border='1px' class='table table-bordered' style=\"background-color:" . $escaper->escapeHtml($header_color) . "\">\n";
                $cf_table .= "<tr>\n";
                $cf_table .= "<th width='50%' style=\"background-color:" . $escaper->escapeHtml($header_color) . "\">".$escaper->escapeHtml($lang['Framework'])."</th>\n";
                $cf_table .= "<th width='35%' style=\"background-color:" . $escaper->escapeHtml($header_color) . "\">".$escaper->escapeHtml($lang['Control'])."</th>\n";
                $cf_table .= "</tr>\n";
                foreach ($control_frameworks as $framework){
                    $cf_table .= "<tr>\n";
                        $cf_table .= "<td style=\"background-color:" . $escaper->escapeHtml($header_color) . "\">".$escaper->escapeHtml($framework['framework_name'])."</td>\n";
                        $cf_table .= "<td style=\"background-color:" . $escaper->escapeHtml($header_color) . "\">".$escaper->escapeHtml($framework['reference_name'])."</td>\n";
                    $cf_table .= "</tr>\n";
                }
                $cf_table .= "</table>\n";
            } else {
                $cf_table = "";
            }
            $control_detail = "<div class='moreellipses hide'>
                " . $escaper->escapeHtml($lang['ControlNumber']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_number']) . "
                </br>" . $escaper->escapeHtml($lang['ControlFrameworks']) . ":&nbsp;&nbsp;" . $cf_table. "
                </br>" . $escaper->escapeHtml($lang['ControlFamily']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_family_name']) . "
                </br>" . $escaper->escapeHtml($lang['ControlClass']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_class_name']) . "
                </br>" . $escaper->escapeHtml($lang['ControlPhase']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_phase_name']) . "
                </br>" . $escaper->escapeHtml($lang['ControlPriority']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_priority_name']) . "
                </br>" . $escaper->escapeHtml($lang['MitigationPercent']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['mitigation_percent']) . " %
                </br>" . $escaper->escapeHtml($lang['ControlOwner']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_owner_name']) . "
                </br>" . $escaper->escapeHtml($lang['Description']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_number']) . "
                </br>" . $escaper->escapeHtml($lang['SupplementalGuidance']) . ":&nbsp;&nbsp;". $escaper->escapeHtml($risks[0]['supplemental_guidance']) . "
                </div>
                </br><a href='javascript:void(0)' class='morelink'>".$escaper->escapeHtml($lang['ShowMore'])."</a>";
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
                echo "<th colspan=\"7\" style=\"background-color:" . $escaper->escapeHtml($header_color) . "\">
                    <center>" . $escaper->escapeHtml($lang['ControlLongName'])  . ":&nbsp;&nbsp;" . $escaper->escapeHtml($risks[0]['control_long_name']) ."
                    </br>" . $escaper->escapeHtml($lang['ControlShortName']) . ":&nbsp;&nbsp;". $escaper->escapeHtml($risks[0]['control_short_name']) ."
                    </br>" . $escaper->escapeHtml($lang['ControlRisk']) . ":&nbsp;&nbsp;". $escaper->escapeHtml($risks[0]['calculated_risk']) . $control_detail ."
                    </center></th>\n";
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
function get_risks_and_controls_rows($report, $sort_by, $projects, $filters)
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
    $where_sql = " ";

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
                , fc.control_number, fc.mitigation_percent, fc.supplemental_guidance, GROUP_CONCAT(DISTINCT f.name) framework_names, cc.name control_class_name
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
            ROUND(b.calculated_risk - (b.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100), 2) as residual_risk
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

/************************************
 * FUNCTION: GET CONNECTIVITY GRAPH *
 ************************************/
function get_connectivity_graph()
{
	global $lang, $escaper;

	// Begin the filter by form
	echo "<form name=\"filter\" method=\"post\" action=\"\">\n";

	// Create a filter by 
	echo $escaper->escapeHtml($lang['FilterBy']) . ":&nbsp;&nbsp;";

	// If no filter was posted
	if (!isset($_POST['filter']))
	{
		// Set the filter option to None Selected
		$filter = 0;
	}
	else $filter = (int)$_POST['filter'];

	// If no selected was posted
	if (!isset($_POST['selected']))
	{
		// Set the selected option to None Selected
		$selected = 0;
	}
	else $selected = (int)$_POST['selected'];

	// Create the dropdown
	echo "<select name=\"filter\" onchange=\"javascript: submit()\">\n";
	echo "  <option value=\"0\"" . ($filter == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['NoneSelected']) . "</option>\n";
	echo "  <option value=\"1\"" . ($filter == 1 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Risk']) . "</option>\n";
	echo "  <option value=\"2\"" . ($filter == 2 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Asset']) . "</option>\n";
    echo "  <option value=\"3\"" . ($filter == 3 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Framework']) . "</option>\n";
    echo "  <option value=\"4\"" . ($filter == 4 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Control']) . "</option>\n";
    echo "  <option value=\"5\"" . ($filter == 5 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Test']) . "</option>\n";
    echo "  <option value=\"6\"" . ($filter == 6 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['Document']) . "</option>\n";
    echo "</select>\n";

	// If the filter is not zero
	if ($filter != 0)
	{
		echo "<br />\n";

		// Script to make dropdown searchable
		echo "<script>\n";
		echo "$(document).ready(function() {\n";
		echo "  $('.searchable-single-select-dropdown').select2();\n";
		echo "});\n";
		echo "</script>\n";

		echo $escaper->escapeHtml($lang['Selected']) . ":&nbsp;&nbsp;";

		// Create the dropdown
		echo "<select class=\"searchable-single-select-dropdown\" name=\"selected\" onchange=\"javascript: submit()\">\n";
		echo "  <option value=\"0\"" . ($selected == 0 ? " selected" : "") . ">" . $escaper->escapeHtml($lang['NoneSelected']) . "</option>\n";

		// Get the query based on the filter
		switch ($filter)
		{
			// If the filter is risks
			case 1:
				$sql = "SELECT id, subject FROM risks ORDER BY id;";
				break;
			case 2:
                // If the encrypted database extra is enabled
                if (encryption_extra())
                {
                    // Get the list of verified assets ordered by the order_by_name field
                    $sql = "SELECT id, name FROM assets WHERE verified=1 ORDER BY order_by_name;";
                }
                // Get the list of verified assets orderd by the name field
                else $sql = "SELECT id, name FROM assets WHERE verified=1 ORDER BY name;";

				break;
			case 3:
				$sql = "SELECT value, name FROM frameworks ORDER BY name;";
				break;
            case 4:
                $sql = "SELECT id, short_name FROM framework_controls ORDER BY short_name;";
                break;
            case 5:
                $sql = "SELECT id, name FROM framework_control_tests ORDER BY name;";
                break;
            case 6:
                $sql = "SELECT id, document_name FROM documents ORDER BY document_name;";
                break;
			default:
				$sql = null;
				break;
		}

		// If the sql is not null
		if ($sql != null)
		{
			// Open the database connection
			$db = db_open();

			// Query the database
			$stmt = $db->prepare($sql);
			$stmt->execute();

			// Store the list in the array
			$array = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Close the database connection
			db_close($db);

            // Set the default color values
            $risk_color = '#85929e';
            $asset_color = '#f7dc6f';
            $framework_color = '#4a235a';
            $control_color = '#154360';
            $test_color = '#2e86c1';
            $result_pass_color = '#90EE90';
            $result_fail_color = '#FFCCCB';
            $result_other_color = '#FFFFE0';
            $document_color = '#a2d9ce';

            // Set the default radius values
            $risk_radius = 10;
            $asset_radius = 10;
            $control_radius = 10;
            $framework_radius = 10;
            $test_radius = 10;
            $result_radius = 10;
            $document_radius = 10;

			// If we are filtering by risk
			if ($filter == 1)
			{
                // Set the risk radius and color values
                $risk_radius = 20;
                $risk_color = '#d35400';

				// If team separation is enabled
				if (team_separation_extra())
				{
					//Include the team separation extra
					require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

					// Strip risks that the user shouldn't have access to
					$array = strip_no_access_risks($array, null, "id");
				}

				// For each value in the array
				foreach ($array as $key => $value)
				{
					// Get the risk ID and subject
					$risk_id = $value['id']+1000;
					$subject = try_decrypt($value['subject']);

					// If this is the selected value
					if ($selected == $risk_id)
					{
						// Set the selected values
						$selected_risk_id = $risk_id;
						$selected_subject = $subject;

						// If the subject is more than 50 characters
						if (strlen($selected_subject) > 50)
						{
							// Truncate the selected subject to 50 characters
							$selected_subject = "[" . $selected_risk_id . "] " . substr($selected_subject, 0, 50) . "...";
						}
						else $selected_subject = "[" . $selected_risk_id . "] " . $selected_subject;
					}

					// Display the value
					echo "<option value=\"" . $escaper->escapeHtml($risk_id) . "\"" . ($selected == $risk_id ? " selected" : "") . ">[" . $escaper->escapeHtml($risk_id) . "] " . $escaper->escapeHtml($subject) . "</option>\n";
				}

                // Get the connectivity for the risk
                $asset_associations = get_asset_connectivity_for_risk($selected_risk_id, $selected_subject);
                $control_associations = get_control_connectivity_for_risk($selected_risk_id, $selected_subject);

                // For each control association
                foreach ($control_associations as $key => $value)
                {
                    // If this is a control
                    if ($value['association_header_1'] == $lang['Control'])
                    {
                        // Get the framework connectivity for the control
                        $framework_associations = array_merge((array)$framework_associations, (array)get_framework_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the test connectivity for the control
                        $test_associations = array_merge((array)$test_associations, (array)get_test_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the document connectivity for the control
                        $document_associations = array_merge((array)$document_associations, (array)get_document_connectivity_for_control($value['association_id_1'], $value['association_name_1']));
                    }
                }

                // For each test association
                foreach ($test_associations as $key => $value)
                {
                    // If this is a test
                    if ($value['association_header_1'] == $lang['Test'])
                    {
                        // Get the result connectivity for the test
                        $result_associations = array_merge((array)$result_associations, (array)get_results_connectivity_for_test($value['association_id_1'], $value['association_name_1']));
                    }
                }

				// Merge the association arrays
				$all_associations = array_merge((array)$asset_associations, (array)$control_associations, (array)$framework_associations, (array)$test_associations, (array)$result_associations, (array)$document_associations);
			}
			// If we are filtering by asset
			else if ($filter == 2)
			{
                // Set the asset radius and color values
                $asset_radius = 20;
                $asset_color = '#d35400';

				// For each value in the array
				foreach ($array as $key => $value)
				{
					// Get the asset id and name
					$asset_id = $value['id'];
					$asset_name = try_decrypt($value['name']);

					// If this is the selected value
					if ($selected == $asset_id)
					{
						// Set the selected values
						$selected_asset_id = $asset_id;
						$selected_asset_name = $asset_name;
					}

					// Display the value
					echo "<option value=\"" . $escaper->escapeHtml($asset_id) . "\"" . ($selected == $asset_id ? " selected" : "") . ">" . $escaper->escapeHtml($asset_name) . "</option>\n";
				}

                // Get the the connectivity for the asset
                $risk_associations = get_risk_connectivity_for_asset($selected_asset_id, $selected_asset_name);

				//  For each risk association
				foreach ($risk_associations as $key => $value)
				{
					// If this is a risk
					if ($value['association_header_1'] == $lang['Risk'])
					{
						// Get the control associations for that risk
						$control_associations = array_merge((array)$control_associations, (array)get_control_connectivity_for_risk($value['association_id_1'], $value['association_name_1']));
					}
				}

				// For each control association
				foreach ($control_associations as $key => $value)
				{
					// If this is a control
					if ($value['association_header_1'] == $lang['Control'])
					{
						// Get the connectivity for the control
						$framework_associations = array_merge((array)$framework_associations, (array)get_framework_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the test connectivity for the control
                        $test_associations = array_merge((array)$test_associations, (array)get_test_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the document connectivity for the control
                        $document_associations = array_merge((array)$document_associations, (array)get_document_connectivity_for_control($value['association_id_1'], $value['association_name_1']));
                    }
				}

                // For each test association
                foreach ($test_associations as $key => $value)
                {
                    // If this is a test
                    if ($value['association_header_1'] == $lang['Test'])
                    {
                        // Get the result connectivity for the test
                        $result_associations = array_merge((array)$result_associations, (array)get_results_connectivity_for_test($value['association_id_1'], $value['association_name_1']));
                    }
                }

				// Merge the associations array
				$all_associations = array_merge((array)$risk_associations, (array)$control_associations, (array)$framework_associations, (array)$test_associations, (array)$result_associations, (array)$document_associations);
			}
			// If we are filtering by framework
			else if ($filter == 3)
			{
                // Set the framework radius and color values
                $framework_radius = 20;
                $framework_color = '#d35400';

                // For each value in the array
                foreach ($array as $key => $value)
                {
                    // Get the control id and name
                    $framework_id = $value['value'];
                    $framework_name = try_decrypt($value['name']);

                    // If this is the selected value
                    if ($selected == $framework_id)
                    {
                        // Set the selected values
                        $selected_framework_id = $framework_id;
                        $selected_framework_name = $framework_name;
                    }

                    // Display the value
                    echo "<option value=\"" . $escaper->escapeHtml($framework_id) . "\"" . ($selected == $framework_id ? " selected" : "") . ">" . $escaper->escapeHtml($framework_name) . "</option>\n";
                }

				// Get the control connectivity for the framework
				$control_associations = get_control_connectivity_for_framework($selected_framework_id, $selected_framework_name);

				// For each control association
				foreach ($control_associations as $key => $value)
				{
					// If this is a control
					if ($value['association_header_1'] == $lang['Control'])
					{
						// Get the risk connectivity for the control
						$risk_associations = array_merge((array)$risk_associations, (array)get_risk_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the test connectivity for the control
                        $test_associations = array_merge((array)$test_associations, (array)get_test_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the document connectivity for the control
                        $document_associations = array_merge((array)$document_associations, (array)get_document_connectivity_for_control($value['association_id_1'], $value['association_name_1']));
                    }
				}

                // For each test association
                foreach ($test_associations as $key => $value)
                {
                    // If this is a test
                    if ($value['association_header_1'] == $lang['Test'])
                    {
                        // Get the result connectivity for the test
                        $result_associations = array_merge((array)$result_associations, (array)get_results_connectivity_for_test($value['association_id_1'], $value['association_name_1']));
                    }
                }

				// For each risk association
                foreach ($risk_associations as $key => $value)
                {
                    // If this is a risk
                    if ($value['association_header_1'] == $lang['Risk'])
                    {
                        // Get the asset connectivity for the risk
                        $asset_associations = array_merge((array)$asset_associations, (array)get_asset_connectivity_for_risk($value['association_id_1'], $value['association_name_1']));
                    }
                }

                // Merge the associations array
                $all_associations = array_merge((array)$control_associations, (array)$risk_associations, (array)$asset_associations, (array)$test_associations, (array)$result_associations, (array)$document_associations);
			}
            // If we are filtering by control
            else if ($filter == 4)
            {
                // Set the control radius and color values
                $control_radius = 20;
                $control_color = '#d35400';

                // For each value in the array
                foreach ($array as $key => $value)
                {
                    // Get the control id and name
                    $control_id = $value['id'];
                    $control_name = $value['short_name'];

                    // If this is the selected value
                    if ($selected == $control_id)
                    {
                        // Set the selected values
                        $selected_control_id = $control_id;
                        $selected_control_name = $control_name;
                    }

                    // Display the value
                    echo "<option value=\"" . $escaper->escapeHtml($control_id) . "\"" . ($selected == $control_id ? " selected" : "") . ">" . $escaper->escapeHtml($control_name) . "</option>\n";
                }

                // Get the framework connectivity for the control
                $framework_associations = get_framework_connectivity_for_control($selected_control_id, $selected_control_name);

                // Get the test connectivity for the control
                $test_associations = get_test_connectivity_for_control($selected_control_id, $selected_control_name);

                // Get the risk connectivity for the control
                $risk_associations = get_risk_connectivity_for_control($selected_control_id, $selected_control_name);

                // Get the document connectivity for the control
                $document_associations = get_document_connectivity_for_control($selected_control_id, $selected_control_name);

                // For each risk association
                foreach ($risk_associations as $key => $value)
                {
                    // If this is a risk
                    if ($value['association_header_1'] == $lang['Risk'])
                    {
                        // Get the asset connectivity for the risk
                        $asset_associations = array_merge((array)$asset_associations, (array)get_asset_connectivity_for_risk($value['association_id_1'], $value['association_name_1']));
                    }
                }

                // For each test association
                foreach ($test_associations as $key => $value)
                {
                    // If this is a test
                    if ($value['association_header_1'] == $lang['Test'])
                    {
                        // Get the result connectivity for the test
                        $result_associations = array_merge((array)$result_associations, (array)get_results_connectivity_for_test($value['association_id_1'], $value['association_name_1']));
                    }
                }

                // Merge the associations array
                $all_associations = array_merge((array)$framework_associations, (array)$risk_associations, (array)$asset_associations, (array)$test_associations, (array)$result_associations, (array)$document_associations);
            }
            // If we are filtering by test
            else if ($filter == 5)
            {
                // Set the test radius and color values
                $test_radius = 20;
                $test_color = '#d35400';

                // For each value in the array
                foreach ($array as $key => $value)
                {
                    // Get the test id and name
                    $test_id = $value['id'];
                    $test_name = try_decrypt($value['name']);

                    // If this is the selected value
                    if ($selected == $test_id)
                    {
                        // Set the selected values
                        $selected_test_id = $test_id;
                        $selected_test_name = $test_name;
                    }

                    // Display the value
                    echo "<option value=\"" . $escaper->escapeHtml($test_id) . "\"" . ($selected == $test_id ? " selected" : "") . ">" . $escaper->escapeHtml($test_name) . "</option>\n";
                }

                // Get the control connectivity for the test
                $control_associations = get_control_connectivity_for_test($selected_test_id, $selected_test_name);

                // Get the result connectivity for the test
                $result_associations = get_results_connectivity_for_test($selected_test_id, $selected_test_name);

                // For each control association
                foreach ($control_associations as $key => $value)
                {
                    // If this is a control
                    if ($value['association_header_1'] == $lang['Control'])
                    {
                        // Get the risk connectivity for the control
                        $risk_associations = array_merge((array)$risk_associations, (array)get_risk_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the document connectivity for the control
                        $document_associations = array_merge((array)$document_associations, (array)get_document_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the connectivity for the control
                        $framework_associations = array_merge((array)$framework_associations, (array)get_framework_connectivity_for_control($value['association_id_1'], $value['association_name_1']));
                    }
                }

                // For each risk association
                foreach ($risk_associations as $key => $value)
                {
                    // If this is a risk
                    if ($value['association_header_1'] == $lang['Risk'])
                    {
                        // Get the asset connectivity for the risk
                        $asset_associations = array_merge((array)$asset_associations, (array)get_asset_connectivity_for_risk($value['association_id_1'], $value['association_name_1']));
                    }
                }

                // Merge the associations array
                $all_associations = array_merge((array)$control_associations, (array)$result_associations, (array)$risk_associations, (array)$asset_associations, (array)$framework_associations, (array)$document_associations);
            }
            // If we are filtering by document
            else if ($filter == 6)
            {
                // Set the document radius and color values
                $document_radius = 20;
                $document_color = '#d35400';

                // For each value in the array
                foreach ($array as $key => $value)
                {
                    // Get the document id and name
                    $document_id = $value['id'];
                    $document_name = $value['document_name'];

                    // If this is the selected value
                    if ($selected == $document_id)
                    {
                        // Set the selected values
                        $selected_document_id = $document_id;
                        $selected_document_name = $document_name;
                    }

                    // Display the value
                    echo "<option value=\"" . $escaper->escapeHtml($document_id) . "\"" . ($selected == $document_id ? " selected" : "") . ">" . $escaper->escapeHtml($document_name) . "</option>\n";
                }

                // Get the control connectivity for the document
                $control_associations = get_control_connectivity_for_document($selected_document_id, $selected_document_name);

                // For each control association
                foreach ($control_associations as $key => $value)
                {
                    // If this is a control
                    if ($value['association_header_1'] == $lang['Control'])
                    {
                        // Get the framework connectivity for the control
                        $framework_associations = array_merge((array)$framework_associations, (array)get_framework_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the test connectivity for the control
                        $test_associations = array_merge((array)$test_associations, (array)get_test_connectivity_for_control($value['association_id_1'], $value['association_name_1']));

                        // Get the risk connectivity for the control
                        $risk_associations = array_merge((array)$risk_associations, (array)get_risk_connectivity_for_control($value['association_id_1'], $value['association_name_1']));
                    }
                }

                // For each risk association
                foreach ($risk_associations as $key => $value)
                {
                    // If this is a risk
                    if ($value['association_header_1'] == $lang['Risk'])
                    {
                        // Get the asset connectivity for the risk
                        $asset_associations = array_merge((array)$asset_associations, (array)get_asset_connectivity_for_risk($value['association_id_1'], $value['association_name_1']));
                    }
                }

                // For each test association
                foreach ($test_associations as $key => $value)
                {
                    // If this is a test
                    if ($value['association_header_1'] == $lang['Test'])
                    {
                        // Get the result connectivity for the test
                        $result_associations = array_merge((array)$result_associations, (array)get_results_connectivity_for_test($value['association_id_1'], $value['association_name_1']));
                    }
                }

                // Merge the associations array
                $all_associations = array_merge((array)$control_associations, (array)$result_associations, (array)$risk_associations, (array)$asset_associations, (array)$framework_associations, (array)$test_associations);
            }
		}

		echo "</select>\n";

		// If the selected value is not 0 and we have associations
		if ($selected != 0 && !empty($all_associations))
		{
			echo "<figure class=\"highcharts-figure\">\n";
			echo "  <div id=\"container\"></div>\n";
			echo "</figure>\n";

			echo "<script>
		        Highcharts.addEvent(
                          Highcharts.Series,
                          'afterSetOptions',
                          function (e) {
                            var colors = Highcharts.getOptions().colors,
                            i = 0,
                            nodes = {};

                            if (
                              this instanceof Highcharts.seriesTypes.networkgraph &&
                              e.options.id === 'connectivity-graph'
                            ) {
                              e.options.data.forEach(function (link) {

                                if (link[0].startsWith('" . js_string_escape($lang['Risk']) . ":')) {
                                  nodes[link[0]] = {
                                    id: link[0],
                                    marker: {
                                      radius: " . js_string_escape($risk_radius) . "
                                    },
                                    color: '" . js_string_escape($risk_color) . "'
                                  }
                                } else if (link[0].startsWith('" . js_string_escape($lang['Asset']) . ":')) {
                                  nodes[link[0]] = {
                                    id: link[0],
                                    marker: {
                                      radius: " . js_string_escape($asset_radius) . "
                                    },
                                    color: '" . js_string_escape($asset_color) . "'
                                  }
                                } else if (link[0].startsWith('" . js_string_escape($lang['Control']) . ":')) {
                                  nodes[link[0]] = {
                                    id: link[0],
                                    marker: {
                                      radius: " . js_string_escape($control_radius) . "
                                    },
                                    color: '" . js_string_escape($control_color) . "'
                                  }
                                } else if (link[0].startsWith('" . js_string_escape($lang['Framework']) . ":')) {
                                  nodes[link[0]] = {
                                    id: link[0],
                                    marker: {
                                      radius: " . js_string_escape($framework_radius) . "
                                    },
                                    color: '" . js_string_escape($framework_color) . "'
                                  } 
                                } else if (link[0].startsWith('" . js_string_escape($lang['Test']) . ":')) {
                                  nodes[link[0]] = {
                                    id: link[0],
                                    marker: {
                                      radius: " . js_string_escape($test_radius) . "
                                    },
                                    color: '" . js_string_escape($test_color) . "'
                                  }
                                } else if (link[0].startsWith('" . js_string_escape($lang['TestResult']) . ":')) {
                                  if (link[0].endsWith('[Pass]')) {
                                    nodes[link[0]] = {
                                      id: link[0],
                                      marker: {
                                        radius: " . js_string_escape($result_radius) . "
                                      },
                                      color: '" . js_string_escape($result_pass_color) . "'
                                    }
                                  } else if (link[0].endsWith('[Fail]')) {
                                    nodes[link[0]] = {
                                      id: link[0],
                                      marker: {
                                        radius: " . js_string_escape($result_radius) . "
                                      },
                                      color: '" . js_string_escape($result_fail_color) . "'
                                    }
                                  } else {
                                    nodes[link[0]] = {
                                      id: link[0],
                                      marker: {
                                        radius: " . js_string_escape($result_radius) . "
                                      },
                                      color: '" . js_string_escape($result_other_color) . "'
                                    } 
                                  }
                                } else if (link[0].startsWith('" . js_string_escape($lang['Document']) . ":')) {
                                  nodes[link[0]] = {
                                    id: link[0],
                                    marker: {
                                      radius: " . js_string_escape($document_radius) . "
                                    },
                                    color: '" . js_string_escape($document_color) . "'
                                  }
                                } 
                              });

                              e.options.nodes = Object.keys(nodes).map(function (id) {
                                return nodes[id];
                              });
                            }
                          }
                        );

                        Highcharts.chart('container', {
                          chart: {
                            type: 'networkgraph',
                          },
                          title: {
                            text: null
                          },
                          credits: {
                            enabled: false
                          },
                          plotOptions: {
                            networkgraph: {
                              keys: ['from', 'to'],
                              layoutAlgorithm: {
                                linkLength: 50,
                                enableSimulation: true,
                                //friction: -0.9
                              }
                            }
                          },
                          series: [{
                          dataLabels: {
                            enabled: true,
                            linkFormat: ''
                          },
                          id: 'connectivity-graph',
                            data: [\n";

			// For each association
			foreach ($all_associations as $value)
			{
				// Display the association in the data
			    echo "['" . (isset($value['association_header_1']) ? $escaper->escapeHtml($value['association_header_1']) . ": " : "") . $escaper->escapeHtml($value['association_name_1']) . "', '" . (isset($value['association_header_2']) ? $escaper->escapeHtml($value['association_header_2']) . ": " : "") . $escaper->escapeHtml($value['association_name_2']) . "'],\n";
			}

                      echo "]
                          }]
                        });
			</script>\n";
		}
                // If the selected value is not 0 and we don't  have any associations
                else if (empty($all_associations))
                {
			echo "<br><br><br>\n";
			echo "<font style=\"font-weight: bold; color: red;\">" . $lang['ThereAreNoConnectionsAssociatedWithTheSelectedValue'] . "</font>";
		}
	}
	
	echo "</form>\n";
}

/*********************************************
 * FUNCTION: GET ASSET CONNECTIVITY FOR RISK *
 *********************************************/
function get_asset_connectivity_for_risk($risk_id, $subject)
{
    global $lang, $escaper;

    // Get the id
    $id = $risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Get the assets
    $stmt = $db->prepare("SELECT id, name FROM assets a LEFT JOIN risks_to_assets rta ON a.id = rta.asset_id WHERE rta.risk_id = :risk_id AND verified=1;");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item in the array
    foreach ($array as $key => $value)
    {
        // Get the asset name and id
        $asset_id = $value['id'];
        $asset_name = try_decrypt($value['name']);

        // Create the asset to risk association
        $associations[] = array(
            "association_id_1" => $risk_id,
            "association_header_1" => $lang['Risk'],
            "association_name_1" => $subject,
            "association_id_2" => $asset_id,
            "association_header_2" => $lang['Asset'],
            "association_name_2" => $asset_name
        );
        $associations[] = array(
            "association_id_1" => $asset_id,
            "association_header_1" => $lang['Asset'],
            "association_name_1" => $asset_name,
            "association_id_2" => $risk_id,
            "association_header_2" => $lang['Risk'],
            "association_name_2" => $subject
        );
    }

    // Get the asset groups
    $stmt = $db->prepare("SELECT a.id, a.name FROM risks_to_asset_groups rtag LEFT JOIN risks r ON rtag.risk_id = r.id LEFT JOIN assets_asset_groups aag ON aag.asset_group_id = rtag.asset_group_id LEFT JOIN assets a ON a.id = aag.asset_id WHERE rtag.risk_id = :risk_id;");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item in the array
    foreach ($array as $key => $value)
    {
        // Get the asset name and id
        $asset_id = $value['id'];
        $asset_name = try_decrypt($value['name']);

        // If the asset name is not empty or null
        if ($asset_name != null && $asset_name != '')
        {
            // Create the asset to risk association
            $associations[] = array(
                "association_id_1" => $risk_id,
                "association_header_1" => $lang['Risk'],
                "association_name_1" => $subject,
                "association_id_2" => $asset_id,
                "association_header_2" => $lang['Asset'],
                "association_name_2" => $asset_name
            );
            $associations[] = array(
                "association_id_1" => $asset_id,
                "association_header_1" => $lang['Asset'],
                "association_name_1" => $asset_name,
                "association_id_2" => $risk_id,
                "association_header_2" => $lang['Risk'],
                "association_name_2" => $subject
            );
        }
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/***********************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR RISK *
 ***********************************************/
function get_control_connectivity_for_risk($risk_id, $subject)
{
    global $lang, $escaper;

    // Get the id
    $id = $risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT fc.id, fc.short_name FROM mitigations m LEFT JOIN mitigation_to_controls mtc ON m.id = mtc.mitigation_id LEFT JOIN framework_controls fc ON mtc.control_id = fc.id WHERE m.risk_id = :risk_id;");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item in the array
    foreach ($array as $key => $value)
    {
        // Get the control name and id
        $control_name = $value['short_name'];
        $control_id = $value['id'];

        // If the control name is not empty or null
        if ($control_name != null && $control_name != '')
        {
            // Create the control to risk association
            $associations[] = array(
                "association_id_1" => $risk_id,
                "association_header_1" => $lang['Risk'],
                "association_name_1" => $subject,
                "association_id_2" => $control_id,
                "association_header_2" => $lang['Control'],
                "association_name_2" => $control_name
            );
            $associations[] = array(
                "association_id_1" => $control_id,
                "association_header_1" => $lang['Control'],
                "association_name_1" => $control_name,
                "association_id_2" => $risk_id,
                "association_header_2" => $lang['Risk'],
                "association_name_2" => $subject
            );
        }
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/*********************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR ASSET *
 *********************************************/
function get_risk_connectivity_for_asset($asset_id, $asset_name)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the assets
    $stmt = $db->prepare("SELECT id, subject FROM risks r LEFT JOIN risks_to_assets rta ON r.id = rta.risk_id WHERE rta.asset_id = :asset_id;");
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
    foreach ($array as $key => $value)
    {
        // Get the risk id and subject
        $risk_id = $value['id']+1000;
        $subject = try_decrypt($value['subject']);

        // If the subject is more than 50 characters
        if (strlen($subject) > 50)
        {
            // Truncate the selected subject to 50 characters
            $subject = "[" . $risk_id . "] " . substr($subject, 0, 50) . "...";
        }
        else $subject = "[" . $risk_id . "] " . $subject;

        // Create the risk to asset association
        $associations[] = array(
            "association_id_1" => $asset_id,
            "association_header_1" => $lang['Asset'],
            "association_name_1" => $asset_name,
            "association_id_2" => $risk_id,
            "association_header_2" => $lang['Risk'],
            "association_name_2" => $subject
        );
        $associations[] = array(
            "association_id_1" => $risk_id,
            "association_header_1" => $lang['Risk'],
            "association_name_1" => $subject,
            "association_id_2" => $asset_id,
            "association_header_2" => $lang['Asset'],
            "association_name_2" => $asset_name
        );
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/****************************************************
 * FUNCTION: GET FRAMEWORK CONNECTIVITY FOR CONTROL *
 ****************************************************/
function get_framework_connectivity_for_control($control_id, $control_name)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the frameworks for this control
    $stmt = $db->prepare("SELECT f.value, f.name FROM framework_control_mappings fcm LEFT JOIN frameworks f ON f.value = fcm.framework WHERE fcm.control_id = :control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each framework
    foreach ($frameworks as $key => $value)
    {
        // Get the framework name and id
        $framework_id = $value['value'];
        $framework_name = try_decrypt($value['name']);

        // Display the connectivity to frameworks
        $associations[] = array(
            "association_id_1" => $control_id,
            "association_header_1" => $lang['Control'],
            "association_name_1" => $control_name,
            "association_id_2" => $framework_id,
            "association_header_2" => $lang['Framework'],
            "association_name_2" => $framework_name
        );
        $associations[] = array(
            "association_id_1" => $framework_id,
            "association_header_1" => $lang['Framework'],
            "association_name_1" => $framework_name,
            "association_id_2" => $control_id,
            "association_header_2" => $lang['Control'],
            "association_name_2" => $control_name
        );
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/***********************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR CONTROL *
 ***********************************************/
function get_risk_connectivity_for_control($control_id, $control_name)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the risks for this control
    $stmt = $db->prepare("SELECT r.id, r.subject FROM risks r LEFT JOIN mitigations m ON r.id = m.risk_id LEFT JOIN mitigation_to_controls mtc ON m.id = mtc.mitigation_id WHERE mtc.control_id = :control_id;");
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
    foreach ($risks as $key => $value)
    {
        // Get the risk id and subject
        $risk_id = $value['id']+1000;
        $subject = try_decrypt($value['subject']);

        // If the subject is more than 50 characters
        if (strlen($subject) > 50)
        {
            // Truncate the selected subject to 50 characters
            $subject = "[" . $risk_id . "] " . substr($subject, 0, 50) . "...";
        }
        else $subject = "[" . $risk_id . "] " . $subject;

        // Display the connectivity to risks
        $associations[] = array(
            "association_id_1" => $control_id,
            "association_header_1" => $lang['Control'],
            "association_name_1" => $control_name,
            "association_id_2" => $risk_id,
            "association_header_2" => $lang['Risk'],
            "association_name_2" => $subject
        );
        $associations[] = array(
            "association_id_1" => $risk_id,
            "association_header_1" => $lang['Risk'],
            "association_name_1" => $subject,
            "association_id_2" => $control_id,
            "association_header_2" => $lang['Control'],
            "association_name_2" => $control_name
        );
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/***********************************************
 * FUNCTION: GET TEST CONNECTIVITY FOR CONTROL *
 ***********************************************/
function get_test_connectivity_for_control($control_id, $control_name)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the tests for this control
    $stmt = $db->prepare("SELECT fct.id, fct.name FROM framework_control_tests fct WHERE fct.framework_control_id = :control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each test
    foreach ($tests as $key => $value)
    {
        // Get the test name and id
        $test_id = $value['id'];
        $test_name = $value['name'];

        // Display the connectivity to tests
        $associations[] = array(
            "association_id_1" => $control_id,
            "association_header_1" => $lang['Control'],
            "association_name_1" => $control_name,
            "association_id_2" => $test_id,
            "association_header_2" => $lang['Test'],
            "association_name_2" => $test_name
        );
        $associations[] = array(
            "association_id_1" => $test_id,
            "association_header_1" => $lang['Test'],
            "association_name_1" => $test_name,
            "association_id_2" => $control_id,
            "association_header_2" => $lang['Control'],
            "association_name_2" => $control_name
        );
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/****************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR FRAMEWORK *
 ****************************************************/
function get_control_connectivity_for_framework($framework_id, $framework_name)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the controls for this framework
    $stmt = $db->prepare("SELECT fc.id, fc.short_name FROM framework_controls fc LEFT JOIN framework_control_mappings fcm ON fc.id = fcm.control_id WHERE fcm.framework = :framework_id;");
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each control
    foreach ($controls as $key => $value)
    {
        // Get the control name and id
        $control_id = $value['id'];
        $control_name = $value['short_name'];

        // Display the connectivity to frameworks
        $associations[] = array(
            "association_id_1" => $control_id,
            "association_header_1" => $lang['Control'],
            "association_name_1" => $control_name,
            "association_id_2" => $framework_id,
            "association_header_2" => $lang['Framework'],
            "association_name_2" => $framework_name
        );
        $associations[] = array(
            "association_id_1" => $framework_id,
            "association_header_1" => $lang['Framework'],
            "association_name_1" => $framework_name,
            "association_id_2" => $control_id,
            "association_header_2" => $lang['Control'],
            "association_name_2" => $control_name
        );
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/***********************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR TEST *
 ***********************************************/
function get_control_connectivity_for_test($test_id, $test_name)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the controls for this framework
    $stmt = $db->prepare("SELECT fc.id, fc.short_name FROM framework_controls fc LEFT JOIN framework_control_tests fct ON fc.id = fct.framework_control_id WHERE fct.id = :test_id;");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each control
    foreach ($controls as $key => $value)
    {
        // Get the control name and id
        $control_id = $value['id'];
        $control_name = $value['short_name'];

        // Display the connectivity to tests
        $associations[] = array(
            "association_id_1" => $control_id,
            "association_header_1" => $lang['Control'],
            "association_name_1" => $control_name,
            "association_id_2" => $test_id,
            "association_header_2" => $lang['Test'],
            "association_name_2" => $test_name
        );
        $associations[] = array(
            "association_id_1" => $test_id,
            "association_header_1" => $lang['Test'],
            "association_name_1" => $test_name,
            "association_id_2" => $control_id,
            "association_header_2" => $lang['Control'],
            "association_name_2" => $control_name
        );
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/***************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR DOCUMENT *
 ***************************************************/
function get_control_connectivity_for_document($document_id, $document_name)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the list of control ids for the document
    $stmt = $db->prepare("SELECT control_ids FROM documents WHERE id = :document_id;");
    $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
    $stmt->execute();
    $control_ids_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $control_ids = $control_ids_array[0]['control_ids'];

    // If the control_ids is not null
    if ($control_ids != null)
    {
        // Get the controls for this document
        $stmt = $db->prepare("SELECT fc.id, fc.short_name FROM framework_controls fc WHERE FIND_IN_SET(fc.id, '" . $control_ids . "');");
        $stmt->execute();

        // Store the list in the array
        $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // For each control
    foreach ($controls as $key => $value)
    {
        // Get the control name and id
        $control_id = $value['id'];
        $control_name = $value['short_name'];

        // Display the connectivity to documents
        $associations[] = array(
            "association_id_1" => $control_id,
            "association_header_1" => $lang['Control'],
            "association_name_1" => $control_name,
            "association_id_2" => $document_id,
            "association_header_2" => $lang['Document'],
            "association_name_2" => $document_name
        );
        $associations[] = array(
            "association_id_1" => $document_id,
            "association_header_1" => $lang['Document'],
            "association_name_1" => $document_name,
            "association_id_2" => $control_id,
            "association_header_2" => $lang['Control'],
            "association_name_2" => $control_name
        );
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/***************************************************
 * FUNCTION: GET DOCUMENT CONNECTIVITY FOR CONTROL *
 ***************************************************/
function get_document_connectivity_for_control($control_id, $control_name)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the list of documents with this control id
    $stmt = $db->prepare("SELECT d.id, d.document_name FROM documents d WHERE FIND_IN_SET(:control_id, d.control_ids);");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each document
    foreach ($documents as $key => $value)
    {
        // Get the document name and id
        $document_id = $value['id'];
        $document_name = $value['document_name'];

        // Display the connectivity to documents
        $associations[] = array(
            "association_id_1" => $control_id,
            "association_header_1" => $lang['Control'],
            "association_name_1" => $control_name,
            "association_id_2" => $document_id,
            "association_header_2" => $lang['Document'],
            "association_name_2" => $document_name
        );
        $associations[] = array(
            "association_id_1" => $document_id,
            "association_header_1" => $lang['Document'],
            "association_name_1" => $document_name,
            "association_id_2" => $control_id,
            "association_header_2" => $lang['Control'],
            "association_name_2" => $control_name
        );
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/***********************************************
 * FUNCTION: GET RESULTS CONNECTIVITY FOR TEST *
 ***********************************************/
function get_results_connectivity_for_test($test_id, $test_name)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    // Get the controls for this framework
    $stmt = $db->prepare("SELECT fctr.id, fctr.test_result, fctr.test_date FROM framework_control_test_results fctr LEFT JOIN framework_control_test_audits fcta ON fctr.test_audit_id = fcta.id LEFT JOIN framework_control_tests fct ON fct.id = fcta.test_id WHERE fct.id = :test_id;");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each test result
    foreach ($results as $key => $value)
    {
        // Get the result name and id
        $result_id = $value['id'];
        $result_name = $value['test_result'];
        $result_date = $value['test_date'];

        // Display the connectivity to results
        $associations[] = array(
            "association_id_1" => $result_id,
            "association_header_1" => $lang['TestResult'],
            "association_name_1" => $result_date . " [" . $result_name . "]",
            "association_id_2" => $test_id,
            "association_header_2" => $lang['Test'],
            "association_name_2" => $test_name
        );
        $associations[] = array(
            "association_id_1" => $test_id,
            "association_header_1" => $lang['Test'],
            "association_name_1" => $test_name,
            "association_id_2" => $result_id,
            "association_header_2" => $lang['TestResult'],
            "association_name_2" => $result_date . " [" . $result_name . "]"
        );
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations;
}

/***************************************************
 * FUNCTION: DISPLAY CONTROL MATURITY SPIDER CHART *
 ***************************************************/
function display_control_maturity_spider_chart($framework_id)
{
	global $escaper, $lang;

	// Get the control gap information for this framework
	$control_gaps = get_control_gaps($framework_id, "all_maturity", "control_family", "asc");

	// Create an empty current category
	$current_category = "null";

	// Create an empty categories array
	$categories = array();

	// Create an empty categories count array
	$categories_count = array();

	// Create an empty categories current maturity sum array
	$categories_current_maturity_sum = array();

	// Create an empty categories desired maturity sum array
	$categories_desired_maturity_sum = array();

	// Get the list of control gaps
	foreach ($control_gaps as $value) {

	    // Escaping it here as it's used later both as key and value and wanted to make sure that they match
	    $value['family_short_name'] = $escaper->escapeHtml($value['family_short_name']);

		// Get the numeric value for the current control maturity
		switch ($value['control_maturity_name'])
		{
			case "Not Performed":
				$current_control_maturity = 0;
				break;
			case "Performed":
                $current_control_maturity = 1;
                break;
			case "Documented":
                $current_control_maturity = 2;
                break;
			case "Managed":
                $current_control_maturity = 3;
                break;
			case "Reviewed":
                $current_control_maturity = 4;
                break;
			case "Optimizing":
                $current_control_maturity = 5;
                break;
		}

        // Get the numeric value for the desired control maturity
        switch ($value['desired_maturity_name'])
        {
            case "Not Performed":
                $desired_control_maturity = 0;
                break;
            case "Performed":
                $desired_control_maturity = 1;
                break;
            case "Documented":
                $desired_control_maturity = 2;
                break;
            case "Managed":
                $desired_control_maturity = 3;
                break;
            case "Reviewed":
                $desired_control_maturity = 4;
                break;
            case "Optimizing":
                $desired_control_maturity = 5;
                break;
        }

		// If this is not the current category
		if ($value['family_short_name'] != $current_category)
		{
			// Add the family to the category array
			$categories[] = $value['family_short_name'];

			// Set the count for this family to one
			$categories_count[$value['family_short_name']] = 1;

			// Put the first value in the categories current maturity sum array
			$categories_current_maturity_sum[$value['family_short_name']] = $current_control_maturity;

			// Put the first value in the categories desired maturity sum array
			$categories_desired_maturity_sum[$value['family_short_name']] = $desired_control_maturity;

			// Set the new current category
			$current_category = $value['family_short_name'];
		}
		// If the category hasn't changed
		else
		{
			// Increment the count
			$categories_count[$value['family_short_name']] = $categories_count[$value['family_short_name']] + 1;

			// Increment the current maturity sum
			$categories_current_maturity_sum[$value['family_short_name']] = $categories_current_maturity_sum[$value['family_short_name']] + $current_control_maturity;

			// Increment the desired maturity sum
			$categories_desired_maturity_sum[$value['family_short_name']] = $categories_desired_maturity_sum[$value['family_short_name']] + $desired_control_maturity;
		}
	}

	// Create the empty data arrays
	$categories_current_maturity_average = array();
	$categories_desired_maturity_average = array();

	// For each category
	foreach ($categories as $key => $value)
	{
		// Averaage = sum / value
		$current_maturity_average = $categories_current_maturity_sum[$value] / $categories_count[$value];
		$desired_maturity_average = $categories_desired_maturity_sum[$value] / $categories_count[$value];
		$categories_current_maturity_average[] = round($current_maturity_average, 1);
		$categories_desired_maturity_average[] = round($desired_maturity_average, 1);
	}

	// Create a new Highchart
	$chart = new Highchart();
	$chart->includeExtraScripts();

	$chart->chart->renderTo = "control_maturity_spider_chart";
	$chart->chart->polar = true;
	$chart->chart->type = "line";
    $chart->chart->width = 1000;
    $chart->chart->height = 1000;
	$chart->title->text = "Current vs Desired Maturity by Control Family";
	$chart->title->x = -80;
	$chart->pane->size = "80%";
	$chart->xAxis->categories = $categories;
	$chart->xAxis->tickmarkPlacement = "on";
	$chart->xAxis->lineWidth = 0;
	$chart->yAxis->gridLineInterpolation = "polygon";
	$chart->yAxis->lineWidth = 0;
	$chart->yAxis->min = 0;
	$chart->yAxis->max = 5;
	$chart->yAxis->tickInterval = 1;
	// $chart->tooltip->shared = true;
	// $chart->tooltip->pointFormat = '<span style="color:{series.color}">{series.name}: <b>{point.y}</b><br/>';
	$chart->legend->align = "center";
	$chart->legend->verticalAlign = "top";
	$chart->legend->layout = "vertical";

	// Draw the Current Maturity series
	$chart->series[0]->name = $escaper->escapeHtml($lang['CurrentControlMaturity']);
	$chart->series[0]->data = empty($categories_current_maturity_average) ? [] : $categories_current_maturity_average;
	$chart->series[0]->pointPlacement = "on";

	// Draw the Desired Maturity series
	$chart->series[1]->name = $escaper->escapeHtml($lang['DesiredControlMaturity']);
	$chart->series[1]->data = empty($categories_desired_maturity_average) ? [] : $categories_desired_maturity_average;
	$chart->series[1]->pointPlacement = "on";

	$chart->credits->enabled = false;

	echo "<figure class=\"highcharts-figure\">\n";
    echo "  <div id=\"control_maturity_spider_chart\"></div>\n";
	echo "</figure>\n";

    echo "<script type=\"text/javascript\">";
    echo $chart->render("control_maturity_spider_chart");
    echo "</script>\n";
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
