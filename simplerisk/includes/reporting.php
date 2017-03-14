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
function get_open_risks()
{
        // If team separation is not enabled
        if (!team_separation_extra())
        {
                // Open the database connection
                $db = db_open();

                // Query the database
		$stmt = $db->prepare("SELECT id FROM `risks` WHERE status != \"Closed\"");
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
                $array = strip_get_open_risks();
        }

        return count($array);
}

/******************************
 * FUNCTION: GET CLOSED RISKS *
 ******************************/
function get_closed_risks()
{
        // If team separation is not enabled
        if (!team_separation_extra())
        {
                // Open the database connection
                $db = db_open();
        
                // Query the database
		$stmt = $db->prepare("SELECT id FROM `risks` WHERE status = \"Closed\"");
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
                $array = strip_get_closed_risks();
        }

        return count($array);
}

/****************************
 * FUNCTION: GET HIGH RISKS *
 ****************************/
function get_high_risks()
{
        // Open the database connection
        $db = db_open();

        // Get the high risk level
        $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'High' OR name = 'Very High' ORDER BY value ASC");
        $stmt->execute();
        $array = $stmt->fetchAll();
        $high = $array[0]['value'];
	$veryhigh = $array[1]['value'];

        // Query the database
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND a.calculated_risk >= :high AND a.calculated_risk < :veryhigh");
        $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
	$stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        $stmt->execute();

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

        return count($array);
}

/*********************************
 * FUNCTION: GET VERY HIGH RISKS *
 *********************************/
function get_veryhigh_risks()
{
        // Open the database connection
        $db = db_open();

        // Get the high risk level
        $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'Very High'");
        $stmt->execute();
        $array = $stmt->fetch();
        $veryhigh = $array['value'];

        // Query the database
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND a.calculated_risk >= :veryhigh");
        $stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        $stmt->execute();

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

        return count($array);
}

/****************************
 * FUNCTION: GET RISK TREND *
 ****************************/
function get_risk_trend($title = null)
{
        $chart = new Highchart();
	$chart->includeExtraScripts();

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
        $open_date = $opened_risks[0];
        $open_count = $opened_risks[1];

        // Get the closed risks array by month
        $closed_risks = get_closed_risks_array("day");
        $close_date = $closed_risks[0];
        $close_count = $closed_risks[1];

        // If the opened risks array is empty
        if (empty($opened_risks))
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
                        'data' => $opened_risk_data
		);

		// Draw the closed risks line
                $chart->series[] = array(
                        'type' => "line",
                        'name' => "Closed Risks",
			'color' => "blue",
			'lineWidth' => "2",
                        'data' => $closed_risk_data
		);

		// Draw the trend line
		$chart->series[] = array(
                        'type' => "line",
                        'name' => "Trend",
			'color' => "#000000",
			'lineWidth' => "2",
                        'data' => $trend_data
                );
        }

	$chart->printScripts();
	echo "<div id=\"risk_trend_chart\"></div>\n";
	echo "<script type=\"text/javascript\">";
	echo $chart->render("risk_trend_chart");
	echo "</script>\n";
	echo "<br /><p><font size=\"1\">* This report requires PHP >= 5.5 in order to run properly.</font></p>\n";
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

    // If the team separation extra is not enabled
    if (!team_separation_extra())
    {
        // Query the database
        $stmt = $db->prepare("select a.calculated_risk, COUNT(*) AS num, CASE WHEN a.calculated_risk >= :veryhigh THEN 'Very High' WHEN a.calculated_risk < :veryhigh AND a.calculated_risk >= :high THEN 'High' WHEN a.calculated_risk < :high AND a.calculated_risk >= :medium THEN 'Medium' WHEN a.calculated_risk < :medium AND a.calculated_risk >= :low THEN 'Low' WHEN a.calculated_risk < :low AND a.calculated_risk >= 0 THEN 'Insignificant' END AS level from `risk_scoring` a JOIN `risks` b ON a.id = b.id WHERE b.status != \"Closed\" GROUP BY level ORDER BY a.calculated_risk DESC");
        $stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
        $stmt->bindParam(":medium", $medium, PDO::PARAM_STR, 4);
        $stmt->bindParam(":low", $low, PDO::PARAM_STR, 4);
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
            if ($row['level'] == "Very High" && $veryhigh != true)
            {
                $veryhigh = true;

                // Add red to the color array
                $color_array[] = $risk_levels_by_color["Very High"]["color"];
            }
            // If we have at least one high risk
            else if ($row['level'] == "High" && $high != true)
            {
                $high = true;

                // Add red to the color array
                $color_array[] = $risk_levels_by_color["High"]["color"];
            }
            // If we have at least one medium risk
            else if ($row['level'] == "Medium" && $medium != true)
            {
                $medium = true;

                // Add orange to the color array
                $color_array[] = $risk_levels_by_color["Medium"]["color"];
            }
            // If we have at least one low risk
            else if ($row['level'] == "Low" && $low != true)
            {
                $low = true;

                // Add yellow to the color array
                $color_array[] = $risk_levels_by_color["Low"]["color"];
            }
	        else if ($row['level'] == "Insignificant" && $insignificant != true)
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

    $chart->printScripts();
    echo "<div id=\"risk_pyramid_chart\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("risk_pyramid_chart");
    echo "</script>\n";
}

/**********************************
 * FUNCTION: OPEN RISK LEVEL PIE *
 **********************************/
function open_risk_level_pie($title = null)
{
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
	$array = $stmt->fetchAll();
	$veryhigh = $array[0]['value'];
	$high = $array[1]['value'];
	$medium = $array[2]['value'];
	$low = $array[3]['value'];

	// If the team separation extra is not enabled
	if (!team_separation_extra())
	{
        	// Query the database
        	$stmt = $db->prepare("select a.calculated_risk, COUNT(*) AS num, CASE WHEN a.calculated_risk >= :veryhigh THEN 'Very High' WHEN a.calculated_risk < :veryhigh AND a.calculated_risk >= :high THEN 'High' WHEN a.calculated_risk < :high AND a.calculated_risk >= :medium THEN 'Medium' WHEN a.calculated_risk < :medium AND a.calculated_risk >= :low THEN 'Low' WHEN a.calculated_risk < :low AND a.calculated_risk >= 0 THEN 'Insignificant' END AS level from `risk_scoring` a JOIN `risks` b ON a.id = b.id WHERE b.status != \"Closed\" GROUP BY level ORDER BY a.calculated_risk DESC");
        	$stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
        	$stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
        	$stmt->bindParam(":medium", $medium, PDO::PARAM_STR, 4);
        	$stmt->bindParam(":low", $low, PDO::PARAM_STR, 4);
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
			    if ($row['level'] == "Very High" && $veryhigh != true)
			    {
				    $veryhigh = true;

				    // Add red to the color array
				    $color_array[] = $risk_levels_by_color["Very High"]["color"];
			    }
			    // If we have at least one high risk
			    else if ($row['level'] == "High" && $high != true)
			    {
				    $high = true;

				    // Add red to the color array
				    $color_array[] = $risk_levels_by_color["High"]["color"];
			    }
			    // If we have at least one medium risk
			    else if ($row['level'] == "Medium" && $medium != true)
			    {
				    $medium = true;

				    // Add orange to the color array
				    $color_array[] = $risk_levels_by_color["Medium"]["color"];
			    }
			    // If we have at least one low risk
			    else if ($row['level'] == "Low" && $low != true)
			    {
				    $low = true;

				    // Add yellow to the color array
				    $color_array[] = $risk_levels_by_color["Low"]["color"];
			    }
                else if ($row['level'] == "Insignificant" && $insignificant != true)
                {
                    $insignificant = true;

                    // Add lightgrey to the color array
                    $color_array[] = "lightgrey";
                }
            }

		    // Add black to color array for insignificant
		    $color_array[] = "lightgrey";

		    $chart->plotOptions->pie->colors = $color_array;

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
function open_risk_status_pie($array, $title = null)
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
function closed_risk_reason_pie($title = null)
{
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
		$stmt = $db->prepare("SELECT name, COUNT(*) as num FROM (SELECT a.close_reason, b.name, MAX(closure_date) FROM `closures` a JOIN `close_reason` b ON a.close_reason = b.value JOIN `risks` c ON a.risk_id = c.id WHERE c.status = \"Closed\" GROUP BY risk_id ORDER BY name DESC) AS close GROUP BY name ORDER BY COUNT(*) DESC;");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }
        else
        {
                //Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                // Query the database
                $array = strip_no_access_risk_pie('close_reason');
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
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_location_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->point->events->click = new HighchartJsExpr("function() {
        location.href = 'dynamic_risk_report.php?status=0&group=3&sort=0'; }");

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
                $data = count_array_values($array, $sort);

                // Create the pie chart
                $chart->series[] = array('type' => "pie",
                        'name' => $sort,
                        'data' => $data);
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

                // Create the pie chart
                $chart->series[] = array('type' => "pie",
                        'name' => $sort,
                        'data' => $data);
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
		$subject[$key] = try_decrypt($row['subject']);
        $status[$key] = $row['status'];
        $calculated_risk[$key] = $row['calculated_risk'];
        $color[$key] = get_risk_color($row['calculated_risk']);
        $risk_level = get_risk_level_name($row['calculated_risk']);
        $dayssince[$key] = dayssince($row['submission_date']);
        $next_review[$key] = next_review($risk_level, $risk_id[$key], $row['next_review'], false);
        $next_review_html[$key] = next_review($risk_level, $row['id'], $row['next_review']);

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

    // Open the database
    $db = db_open();

	// Check the report
	switch ($report)
	{
		// Risks by Asset
		case 0:
			$query = "SELECT a.risk_id AS id, a.asset, b.id AS asset_id, b.ip AS asset_ip, b.name AS asset_name, b.value AS asset_value, b.location AS asset_location, b.team AS asset_team, c.status, c.subject, c.submission_date, d.calculated_risk, e.next_review FROM risks_to_assets a LEFT JOIN assets b ON a.asset = b.name LEFT JOIN risks c ON a.risk_id = c.id LEFT JOIN risk_scoring d ON a.risk_id = d.id LEFT JOIN mgmt_reviews e ON c.mgmt_review = e.id WHERE status != \"Closed\" ORDER BY asset_value DESC, asset_name, calculated_risk DESC, id";
			break;
		// Assets by Risk
		case 1:
			$query = "SELECT a.risk_id AS id, a.asset, b.id AS asset_id, b.ip AS asset_ip, b.name AS asset_name, b.value AS asset_value, b.location AS asset_location, b.team AS asset_team, c.status, c.subject, d.calculated_risk FROM risks_to_assets a LEFT JOIN assets b ON a.asset = b.name LEFT JOIN risks c ON a.risk_id = c.id LEFT JOIN risk_scoring d ON a.risk_id = d.id WHERE status != \"Closed\" ORDER BY calculated_risk DESC, id, asset_value DESC, asset_name";
			break;
	}

    $stmt = $db->prepare($query);
    $stmt->execute();

    // Store the results in the rows array
    $rows = $stmt->fetchAll();

    // If team separation is enabled
    if (team_separation_extra())
    {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Strip out risks the user should not have access to
            $rows = strip_no_access_risks($rows);
    }

    // Set the current group to empty
    $current_group = "";

	// If risks by asset
	if ($report == 0)
	{
		// For each row
		foreach ($rows as $row)
		{
            // Get the variables for the row
            $risk_id = (int)$row['id'];
            $asset = $row['asset'];
            $asset_id = (isset($row['asset_id']) ? (int)$row['asset_id'] : "N/A");
            $asset_ip = (isset($row['asset_ip']) ? $row['asset_ip'] : "N/A");
            $asset_name = (isset($row['asset_name']) ? $row['asset_name'] : $asset);
            $asset_value = $row['asset_value'];
			$asset_location = (isset($row['asset_location']) ? get_name_by_value("location",$row['asset_location']) : "N/A");
            $asset_team = (isset($row['asset_team']) ? get_name_by_value("team",$row['asset_team']) : "N/A");
            $status = $row['status'];
            $subject = try_decrypt($row['subject']);
			$calculated_risk = $row['calculated_risk'];
			$color = get_risk_color($calculated_risk);
			$dayssince = dayssince($row['submission_date']);

            // If the current group is not the asset id
            if ($current_group != $asset_id)
            {
                // If this is not the first group
                if ($current_group != "")
                {
                        // End the table
                        echo "</tbody>\n";
                        echo "</table>\n";
                }

                // Set the current group to the asset id
                $current_group = $asset_id;

                // Display the table header
                echo "<table class=\"table table-bordered table-condensed sortable\">\n";
                echo "<thead>\n";
                echo "<tr>\n";
                echo "<th bgcolor=\"#0088CC\" colspan=\"5\"><center>" . $escaper->escapeHtml($lang['AssetName']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($asset_name) . "<br />" . $escaper->escapeHtml($lang['AssetValue']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml(get_asset_value_by_id($asset_value)) . "</center></th>\n";
echo "</tr>\n";
                echo "<tr>\n";
echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
                echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
                echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
                echo "</tr>\n";
                echo "</thead>\n";
                echo "<tbody>\n";
            }

            // Display the individual asset information
            echo "<tr>\n";
			echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
			echo "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($status) . "</td>\n";
			echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
			echo "<td align=\"center\" class=\"risk-cell\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($calculated_risk) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></td>\n";
			echo "<td align=\"center\" width=\"100px\">" . $escaper->escapeHtml($dayssince) . "</td>\n";
                        echo "</tr>\n";
		}

        // End the last table
        echo "</tbody>\n";
        echo "</table>\n";
    }

	// If assets by risk
	if ($report == 1)
	{
		// For each row
		foreach ($rows as $row)
		{
			// Get the variables for the row
			$risk_id = (int)$row['id'];
			$asset = $row['asset'];
			$asset_id = (int)$row['asset_id'];
            $asset_ip = (isset($row['asset_ip']) ? $row['asset_ip'] : "N/A");
			$asset_ip = ($asset_ip != "" ? $asset_ip : "N/A");
            $asset_name = (isset($row['asset_name']) ? $row['asset_name'] : $asset);
            $asset_value = $row['asset_value'];
            $asset_location = (isset($row['asset_location']) ? get_name_by_value("location",$row['asset_location']) : "N/A");
			$asset_location = ($asset_location != "" ? $asset_location : "N/A");
            $asset_team = (isset($row['asset_team']) ? get_name_by_value("team",$row['asset_team']) : "N/A");
			$asset_team = ($asset_team != "" ? $asset_team : "N/A");
			$status = $row['status'];
			$subject = try_decrypt($row['subject']);
			$calculated_risk = $row['calculated_risk'];

			// If the current group is not the risk_id
			if ($current_group != $risk_id)
			{
				// If this is not the first group
				if ($current_group != "")
				{
					// End the table
					echo "<tr><td style=\"background-color:" . $escaper->escapeHtml($color) . "\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" colspan=\"5\"></td></tr>\n";
					echo "<tr>\n";
					echo "<td style=\"background-color: lightgrey\" align=\"left\" width=\"50px\" colspan=\"4\"><b>" . $escaper->escapeHtml($lang['MaximumQuantitativeLoss']) . "</b></td>\n";
					echo "<td style=\"background-color: lightgrey\" align=\"left\" width=\"50px\"><b>$" . $escaper->escapeHtml(number_format($asset_valuation)) . "</b></td>\n";
					echo "</tr>\n";
					echo "</tbody>\n";
					echo "</table>\n";
				}

				// Set the current group to the risk id
				$current_group = $risk_id;

				// Get the risk id's asset valuation
				$asset_valuation = asset_valuation_for_risk_id($risk_id);

				// Get the risk color
				$color = get_risk_color($calculated_risk);

				// Display the table header
				echo "<table class=\"table table-bordered table-condensed sortable\">\n";
				echo "<thead>\n";
				echo "<tr>\n";
				echo "<th style=\"background-color:" . $escaper->escapeHtml($color) . "\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" colspan=\"5\"><center><font color=\"#000000\">" . $escaper->escapeHtml($lang['RiskId']) . ":&nbsp;&nbsp;<a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\" style=\"color:#000000\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a><br />" . $escaper->escapeHtml($lang['Subject']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($subject) . "<br />" . $escaper->escapeHtml($lang['CalculatedRisk']) . ":&nbsp;&nbsp;" . $escaper->escapeHtml($calculated_risk) . "&nbsp;&nbsp;(" . $escaper->escapeHtml(get_risk_level_name($calculated_risk)) . ")</font></center></th>\n";
				echo "</tr>\n";
				echo "<tr>\n";
				echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['AssetName']) ."</th>\n";
				echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['IPAddress']) ."</th>\n";
				echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['SiteLocation']) ."</th>\n";
				echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Team']) ."</th>\n";
				echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['AssetValuation']) ."</th>\n";
				echo "</tr>\n";
				echo "</thead>\n";
				echo "<tbody>\n";
			}

			// Display the individual asset information
			echo "<tr>\n";
			echo "<td align=\"left\" width=\"50px\">" . $escaper->escapeHtml($asset_name) . "</td>\n";
			echo "<td align=\"left\" width=\"50px\">" . $escaper->escapeHtml($asset_ip) . "</td>\n";
			echo "<td align=\"left\" width=\"50px\">" . $escaper->escapeHtml($asset_location) . "</td>\n";
			echo "<td align=\"left\" width=\"50px\">" . $escaper->escapeHtml($asset_team) . "</td>\n";
			echo "<td align=\"left\" width=\"50px\">" . $escaper->escapeHtml(get_asset_value_by_id($asset_value)) . "</td>\n";
			echo "</tr>\n";
		}

		// If this is not the first group
		if ($current_group != "")
		{
			// End the last table
			echo "<tr><td style=\"background-color:" . $escaper->escapeHtml($color) . "\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" colspan=\"5\"></td></tr>\n";
                	echo "<tr>\n";
			echo "<td style=\"background-color: lightgrey\" align=\"left\" width=\"50px\" colspan=\"4\"><b>" . $escaper->escapeHtml($lang['MaximumQuantitativeLoss']) . "</b></td>\n";
			echo "<td style=\"background-color: lightgrey\" align=\"left\" width=\"50px\"><b>$" . $escaper->escapeHtml(number_format($asset_valuation)) . "</b></td>\n";
			echo "</tr>\n";
			echo "</tbody>\n";
			echo "</table>\n";
		}
	}

	// Close the database
        db_close($db);
}


/********************************
 * FUNCTION: GET RISKS BY TABLE *
 ********************************/
function get_risks_by_table($status, $group, $sort, $column_id=true, $column_status=false, $column_subject=true, $column_reference_id=false, $column_regulation=false, $column_control_number=false, $column_location=false, $column_source=false, $column_category=false, $column_team=false, $column_technology=false, $column_owner=false, $column_manager=false, $column_submitted_by=false, $column_scoring_method=false, $column_calculated_risk=true, $column_submission_date=true, $column_review_date=false, $column_project=false, $column_mitigation_planned=true, $column_management_review=true, $column_days_open=false, $column_next_review_date=false, $column_next_step=false, $column_affected_assets=false, $column_planning_strategy=false, $column_mitigation_effort=false, $column_mitigation_cost=false, $column_mitigation_owner=false, $column_mitigation_team=false, $column_risk_assessment=false, $column_additional_notes=false, $column_current_solution=false, $column_security_recommendations=false, $column_security_requirements=false)
{
	global $lang;
	global $escaper;

	// Check the status
	switch ($status)
	{
		// Open risks
		case 0:
			$status_query = " WHERE a.status != \"Closed\" ";
			break;
		// Closed risks
		case 1:
			$status_query = " WHERE a.status = \"Closed\" ";
			break;
		case 2:
		// All risks
			$status_query = " ";
			break;
		// Default to open risks
		default:
			$status_query = " WHERE a.status != \"Closed\" ";
			break;
	}


    // Check the sort
    switch ($sort)
    {
            // Calculated Risk
            case 0:
		$sort_name = " calculated_risk DESC ";
                    break;
	// ID
	case 1:
		$sort_name = " a.id ASC ";
		break;
	// Subject
	case 2:
		$sort_name = " a.subject ASC ";
		break;
            // Default to calculated risk
            default:
		$sort_name = " calculated_risk DESC ";
                    break;
    }

	// Check the group
	switch ($group)
	{
		// None
		case 0:
			$order_query = "GROUP BY id ORDER BY" . $sort_name;
			$group_name = "none";
			break;
		// Risk Level
		case 1:
			$order_query = "GROUP BY id ORDER BY calculated_risk DESC, " . $sort_name;
			$group_name = "risk_level";
			break;
		// Status
		case 2:
			$order_query = "GROUP BY id ORDER BY a.status," . $sort_name;
			$group_name = "status";
			break;
		// Site/Location
		case 3:
			$order_query = "GROUP BY id ORDER BY location," . $sort_name;
			$group_name = "location";
			break;
		// Source
		case 4:
			$order_query = "GROUP BY id ORDER BY source," . $sort_name;
			$group_name = "source";
			break;
		// Category
		case 5:
			$order_query = "GROUP BY id ORDER BY category," . $sort_name;
			$group_name = "category";
			break;
		// Team
		case 6:
			$order_query = "GROUP BY id ORDER BY team," . $sort_name;
			$group_name = "team";
			break;
		// Technology
		case 7:
			$order_query = "GROUP BY id ORDER BY technology," . $sort_name;
			$group_name = "technology";
			break;
		// Owner
		case 8:
			$order_query = "GROUP BY id ORDER BY owner," . $sort_name;
			$group_name = "owner";
			break;
		// Owners Manager
		case 9:
			$order_query = "GROUP BY id ORDER BY manager," . $sort_name;
			$group_name = "manager";
			break;
		// Risk Scoring Method
		case 10:
			$order_query = "GROUP BY id ORDER BY scoring_method," . $sort_name;
			$group_name = "scoring_method";
			break;
		// Regulation
		case 11:
			$order_query = "GROUP BY id ORDER BY regulation," . $sort_name;
			$group_name = "regulation";
			break;
		// Project
		case 12:
			$order_query = "GROUP BY id ORDER BY project," . $sort_name;
			$group_name = "project";
			break;
		// Next Step
		case 13:
			$order_query = "GROUP BY id ORDER BY next_step," . $sort_name;
			$group_name = "next_step";
			break;
		// Month Submitted
		case 14:
			$order_query = "GROUP BY id ORDER BY submission_date DESC," . $sort_name;
			$group_name = "month_submitted";
			break;
		// Default to calculated risk
		default:
			$order_query = "GROUP BY id ORDER BY" . $sort_name;
			$group_name = "none";
			break;
	}

	// If the team separation extra is not enabled
	if (!team_separation_extra())
	{
		// Make the big query
		$query = "SELECT a.id, a.status, a.subject, a.reference_id, a.control_number, a.submission_date, a.last_update, a.review_date, a.mitigation_id, a.mgmt_review, a.assessment as risk_assessment, a.notes as additional_notes, b.scoring_method, b.calculated_risk, c.name AS location, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, i.name AS submitted_by, j.name AS regulation, k.name AS project, l.next_review, m.name AS next_step, GROUP_CONCAT(DISTINCT n.asset SEPARATOR ', ') AS affected_assets, o.closure_date, q.name AS planning_strategy, r.name AS mitigation_effort, s.min_value AS mitigation_min_cost, s.max_value AS mitigation_max_cost, t.name AS mitigation_owner, u.name AS mitigation_team, v.name AS source, p.current_solution, p.security_recommendations, p.security_requirements
    		FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN location c ON a.location = c.value LEFT JOIN category d ON a.category = d.value LEFT JOIN team e ON a.team = e.value LEFT JOIN technology f ON a.technology = f.value LEFT JOIN user g ON a.owner = g.value LEFT JOIN user h ON a.manager = h.value LEFT JOIN user i ON a.submitted_by = i.value LEFT JOIN regulation j ON a.regulation = j.value LEFT JOIN projects k ON a.project_id = k.value LEFT JOIN mgmt_reviews l ON a.mgmt_review = l.id LEFT JOIN next_step m ON l.next_step = m.value LEFT JOIN risks_to_assets n ON a.id = n.risk_id LEFT JOIN closures o ON a.close_id = o.id LEFT JOIN mitigations p ON a.id = p.risk_id LEFT JOIN planning_strategy q ON p.planning_strategy = q.value LEFT JOIN mitigation_effort r ON p.mitigation_effort = r.value LEFT JOIN asset_values s ON p.mitigation_cost = s.id LEFT JOIN user t ON p.mitigation_owner = h.value LEFT JOIN team u ON p.mitigation_team = u.value LEFT JOIN source v ON a.source = v.value " . $status_query . $order_query;
	}
	// Otherwise
	else
	{
		// Include the team separation extra
		require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

		// Get the separation query string
		$separation_query = get_user_teams_query("a", false, true);

		// Make the big query
		$query = "SELECT a.id, a.status, a.subject, a.reference_id, a.control_number, a.submission_date, a.last_update, a.review_date, a.mitigation_id, a.mgmt_review, a.assessment as risk_assessment, a.notes as additional_notes, b.scoring_method, b.calculated_risk, c.name AS location, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, i.name AS submitted_by, j.name AS regulation, k.name AS project, l.next_review, m.name AS next_step, GROUP_CONCAT(DISTINCT n.asset SEPARATOR ', ') AS affected_assets, o.closure_date, q.name AS planning_strategy, r.name AS mitigation_effort, s.min_value AS mitigation_min_cost, s.max_value AS mitigation_max_cost, t.name AS mitigation_owner, u.name AS mitigation_team, v.name AS source, p.current_solution, p.security_recommendations, p.security_requirements
		FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN location c ON a.location = c.value LEFT JOIN category d ON a.category = d.value LEFT JOIN team e ON a.team = e.value LEFT JOIN technology f ON a.technology = f.value LEFT JOIN user g ON a.owner = g.value LEFT JOIN user h ON a.manager = h.value LEFT JOIN user i ON a.submitted_by = i.value LEFT JOIN regulation j ON a.regulation = j.value LEFT JOIN projects k ON a.project_id = k.value LEFT JOIN mgmt_reviews l ON a.mgmt_review = l.id LEFT JOIN next_step m ON l.next_step = m.value LEFT JOIN risks_to_assets n ON a.id = n.risk_id LEFT JOIN closures o ON a.close_id = o.id LEFT JOIN mitigations p ON a.id = p.risk_id LEFT JOIN planning_strategy q ON p.planning_strategy = q.value LEFT JOIN mitigation_effort r ON p.mitigation_effort = r.value LEFT JOIN asset_values s ON p.mitigation_cost = s.id LEFT JOIN user t ON p.mitigation_owner = h.value LEFT JOIN team u ON p.mitigation_team = u.value LEFT JOIN source v ON a.source = v.value " . $status_query . $separation_query . $order_query;
	}

	// Query the database
	$db = db_open();
	$stmt = $db->prepare($query);
	$stmt->execute();
	db_close($db);

	// Store the results in the risks array
	$risks = $stmt->fetchAll();

	// Set the current group to empty
	$current_group = "";

	// If the group name is none
	if ($group_name == "none")
	{
		// Display the table header
		echo "<table data-group='' class=\"table risk-datatable table-bordered table-striped table-condensed  table-margin-top\" style='width: 100%'>\n";
		echo "<thead>\n";
		echo "<tr class='main'>\n";

		// Header columns go here
		get_header_columns($column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements);

		echo "</tr>\n";
		echo "</thead>\n";
		echo "<tbody>\n";
	}
	// For each risk in the risks array
	foreach ($risks as $risk)
	{
		$risk_id = (int)$risk['id'];
		$status = $risk['status'];
		$subject = $risk['subject'];
		$reference_id = $risk['reference_id'];
		$control_number = $risk['control_number'];
		$submission_date = $risk['submission_date'];
		$last_update = $risk['last_update'];
		$review_date = $risk['review_date'];
		$scoring_method = get_scoring_method_name($risk['scoring_method']);
		$calculated_risk = (float)$risk['calculated_risk'];
		$color = get_risk_color($risk['calculated_risk']);
		$risk_level = get_risk_level_name($risk['calculated_risk']);
		$location = $risk['location'];
		$source = $risk['source'];
		$category = $risk['category'];
		$team = $risk['team'];
		$technology = $risk['technology'];
		$owner = $risk['owner'];
		$manager = $risk['manager'];
		$submitted_by = $risk['submitted_by'];
		$regulation = $risk['regulation'];
		$project = $risk['project'];
		$mitigation_id = $risk['mitigation_id'];
		$mgmt_review = $risk['mgmt_review'];
		$days_open = dayssince($risk['submission_date']);
		$next_review_date = next_review($risk_level, $risk_id, $risk['next_review'], false);
		$next_review_date_html = next_review($risk_level, $risk_id, $risk['next_review']);
		$next_step = $risk['next_step'];
        $affected_assets = $risk['affected_assets'];
        $risk_assessment = $risk['risk_assessment'];
        $additional_notes = $risk['additional_notes'];
        $current_solution = $risk['current_solution'];
        $security_recommendations = $risk['security_recommendations'];
		$security_requirements = $risk['security_requirements'];
		$month_submitted = date('Y F', strtotime($risk['submission_date']));
		$planning_strategy = $risk['planning_strategy'];
		$mitigation_effort = $risk['mitigation_effort'];
		$mitigation_min_cost = $risk['mitigation_min_cost'];
		$mitigation_max_cost = $risk['mitigation_max_cost'];
		//$mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;
		$mitigation_cost = $risk['mitigation_min_cost'];
		$mitigation_owner = $risk['mitigation_owner'];
		$mitigation_team = $risk['mitigation_team'];

		// If the group name is not none
		if ($group_name != "none")
		{
            $group_value = ${$group_name};
            
            switch($group_name){
                case "risk_level":
                    $group_value_from_db = $risk['calculated_risk'];
                break;
                case "month_submitted":
                    $group_value_from_db = $risk['submission_date'];
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
			if ($group_value != $current_group)
			{
				// If this is not the first group
				if ($current_group != "")
				{
					echo "</tbody>\n";
					echo "</table>\n";
					echo "<br />\n";
				}

				// If the group is not empty
				if ($group_value != "")
				{
					// Set the group to the current group
					$current_group = $group_value;
				}
				else $current_group = $lang['Unassigned'];

				// Display the table header
				echo "<table data-group='".$escaper->escapeHtml($group_value_from_db)."' class=\"table risk-datatable table-bordered table-striped table-condensed  table-margin-top\" style='width: 100%'>\n";
				echo "<thead>\n";
				echo "<tr>\n";
				echo "<th bgcolor=\"#0088CC\" colspan=\"35\"><center>". $escaper->escapeHtml($current_group) ."</center></th>\n";
				echo "</tr>\n";
				echo "<tr class='main'>\n";

				// Header columns go here
				get_header_columns($column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements);

				echo "</tr>\n";
				echo "</thead>\n";
				echo "<tbody>\n";
			}
		}

		// Display the risk information
//		echo "<tr>\n";

		// Risk information goes here
//		get_risk_columns($risk, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements);

//		echo "</tr>\n";
	
	}

	// If the group name is none
	if ($group_name == "none")
	{
		// End the table
		echo "</tbody>\n";
		echo "</table>\n";
		echo "<br />\n";
	}
}

/********************************
 * FUNCTION: GET HEADER COLUMNS *
 ********************************/
function get_header_columns($id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements)
{
	global $lang;
	global $escaper;

	echo "<th class=\"id\" data-name='id' " . ($id == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"25px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
	echo "<th class=\"status\" data-name='risk_status' " . ($risk_status == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
        echo "<th class=\"subject\" data-name='subject' " . ($subject == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th class=\"reference_id\" data-name='reference_id' " . ($reference_id == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ExternalReferenceId']) ."</th>\n";
        echo "<th class=\"regulation\" data-name='regulation' " . ($regulation == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ControlRegulation']) ."</th>\n";
        echo "<th class=\"control_number\" data-name='control_number' " . ($control_number == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ControlNumber']) ."</th>\n";
        echo "<th class=\"location\" data-name='location' " . ($location == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['SiteLocation']) ."</th>\n";
	echo "<th class=\"source\" data-name='source' " . ($source == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['RiskSource']) ."</th>\n";
        echo "<th class=\"category\" data-name='category' " . ($category == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Category']) ."</th>\n";
        echo "<th class=\"team\" data-name='team' " . ($team == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Team']) ."</th>\n";
        echo "<th class=\"technology\" data-name='technology' " . ($technology == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Technology']) ."</th>\n";
        echo "<th class=\"owner\" data-name='owner' " . ($owner == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Owner']) ."</th>\n";
        echo "<th class=\"manager\" data-name='manager' " . ($manager == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['OwnersManager']) ."</th>\n";
        echo "<th class=\"submitted_by\" data-name='submitted_by' " . ($submitted_by == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['SubmittedBy']) ."</th>\n";
        echo "<th class=\"scoring_method\" data-name='scoring_method' " . ($scoring_method == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['RiskScoringMethod']) ."</th>\n";
        echo "<th class=\"calculated_risk\" data-name='calculated_risk' " . ($calculated_risk == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"25px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
        echo "<th class=\"submission_date\" data-name='submission_date' " . ($submission_date == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
        echo "<th class=\"review_date\" data-name='review_date' " . ($review_date == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ReviewDate']) ."</th>\n";
	echo "<th class=\"project\" data-name='project' " . ($project == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['Project']) ."</th>\n";
	echo "<th class=\"mitigation_planned\" data-name='mitigation_planned' " . ($mitigation_planned == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['MitigationPlanned']) ."</th>\n";
	echo "<th class=\"management_review\" data-name='management_review' " . ($management_review == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ManagementReview']) ."</th>\n";
	echo "<th class=\"days_open\" data-name='days_open' " . ($days_open == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
	echo "<th class=\"next_review_date\" data-name='next_review_date' " . ($next_review_date == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['NextReviewDate']) ."</th>\n";
	echo "<th class=\"next_step\" data-name='next_step' " . ($next_step == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['NextStep']) ."</th>\n";
    echo "<th class=\"affected_assets\" data-name='affected_assets' " . ($affected_assets == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['AffectedAssets']) ."</th>\n";
    echo "<th class=\"risk_assessment\" data-name='risk_assessment' " . ($risk_assessment == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['RiskAssessment']) ."</th>\n";
    echo "<th class=\"additional_notes\" data-name='additional_notes' " . ($additional_notes == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['AdditionalNotes']) ."</th>\n";
    echo "<th class=\"current_solution\" data-name='current_solution' " . ($current_solution == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['CurrentSolution']) ."</th>\n";
    echo "<th class=\"security_recommendations\" data-name='security_recommendations' " . ($security_recommendations == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['SecurityRecommendations']) ."</th>\n";
	echo "<th class=\"security_requirements\" data-name='security_requirements' " . ($security_requirements == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['SecurityRequirements']) ."</th>\n";
	echo "<th class=\"planning_strategy\" data-name='planning_strategy' " . ($planning_strategy == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['PlanningStrategy']) ."</th>\n";
	echo "<th class=\"mitigation_effort\" data-name='mitigation_effort' " . ($mitigation_effort == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['MitigationEffort']) ."</th>\n";
	echo "<th class=\"mitigation_cost\" data-name='mitigation_cost' " . ($mitigation_cost== true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['MitigationCost']) ."</th>\n";
	echo "<th class=\"mitigation_owner\" data-name='mitigation_owner' " . ($mitigation_owner== true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['MitigationOwner']) ."</th>\n";
	echo "<th class=\"mitigation_team\" data-name='mitigation_team' " . ($mitigation_team == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['MitigationTeam']) ."</th>\n";
}

/******************************
 * FUNCTION: GET RISK COLUMNS *
 ******************************/
function get_risk_columns($risk, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements)
{
    global $lang;
    global $escaper;

	$risk_id = (int)$risk['id'];
	$status = $risk['status'];
	$subject = try_decrypt($risk['subject']);
	$reference_id = $risk['reference_id'];
	$control_number = $risk['control_number'];
	$submission_date = $risk['submission_date'];
	$last_update = $risk['last_update'];
	$review_date = $risk['review_date'];
	$scoring_method = get_scoring_method_name($risk['scoring_method']);
	$calculated_risk = $risk['calculated_risk'];
	$color = get_risk_color($risk['calculated_risk']);
	$risk_level = get_risk_level_name($risk['calculated_risk']);
	$location = $risk['location'];
	$source = $risk['source'];
	$category = $risk['category'];
	$team = $risk['team'];
	$technology = $risk['technology'];
	$owner = $risk['owner'];
	$manager = $risk['manager'];
	$submitted_by = $risk['submitted_by'];
	$regulation = $risk['regulation'];
	$project = try_decrypt($risk['project']);
	$mitigation_id = $risk['mitigation_id'];
	$mgmt_review = $risk['mgmt_review'];

	// If the status is not closed
	if ($status != "Closed")
	{
		// Compare submission date to now
		$days_open = dayssince($risk['submission_date']);
	}
	// Otherwise the status is closed
	else
	{
		// Compare the submission date to the closure date
		$days_open = dayssince($risk['submission_date'], $risk['closure_date']);
	}

	$next_review_date = next_review($risk_level, $risk_id, $risk['next_review'], false);
	$next_review_date_html = next_review($risk_level, $risk_id, $risk['next_review']);
	$next_step = $risk['next_step'];
    $affected_assets = $risk['affected_assets'];
	$risk_assessment = try_decrypt($risk['risk_assessment']);
	$additional_notes = try_decrypt($risk['additional_notes']);
	$current_solution = try_decrypt($risk['current_solution']);
	$security_recommendations = try_decrypt($risk['security_recommendations']);
	$security_requirements = try_decrypt($risk['security_requirements']);
	$planning_strategy = $risk['planning_strategy'];
	$mitigation_effort = $risk['mitigation_effort'];
	$mitigation_min_cost = $risk['mitigation_min_cost'];
	$mitigation_max_cost = $risk['mitigation_max_cost'];

	// If the mitigation costs are empty
	if (empty($mitigation_min_cost) && empty($mitigation_max_cost))
	{
		// Return no value
		$mitigation_cost = "";
	}
	else $mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;

	$mitigation_owner = $risk['mitigation_owner'];
	$mitigation_team = $risk['mitigation_team'];

	// If the risk hasn't been reviewed yet
	if ($review_date == "0000-00-00 00:00:00")
	{
		// Set the review date to empty
		$review_date = "";
	}
	// Otherwise set the review date to the proper format
	else $review_date = date(DATETIMESIMPLE, strtotime($review_date));

	echo "<td class=\"id\" " . ($column_id == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"25px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\" target=\"_blank\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
	echo "<td class=\"status\" " . ($column_status == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($status) . "</td>\n";
	echo "<td class=\"subject\" " . ($column_subject == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
	echo "<td class=\"reference_id\" " . ($column_reference_id == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($reference_id) . "</td>\n";
	echo "<td class=\"regulation\" " . ($column_regulation == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($regulation) . "</td>\n";
	echo "<td class=\"control_number\" " . ($column_control_number == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($control_number) . "</td>\n";
	echo "<td class=\"location\" " . ($column_location == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($location) . "</td>\n";
	echo "<td class=\"source\" " . ($column_source == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($source) . "</td>\n";
	echo "<td class=\"category\" " . ($column_category == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($category) . "</td>\n";
	echo "<td class=\"team\" " . ($column_team == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($team) . "</td>\n";
	echo "<td class=\"technology\" " . ($column_technology == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($technology) . "</td>\n";
	echo "<td class=\"owner\" " . ($column_owner == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($owner) . "</td>\n";
	echo "<td class=\"manager\" " . ($column_manager == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($manager) . "</td>\n";
	echo "<td class=\"submitted_by\" " . ($column_submitted_by == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($submitted_by) . "</td>\n";
	echo "<td class=\"scoring_method\" " . ($column_scoring_method == true ? "" : "style=\"display:tnone;\" ") . "align=\"left\" width=\"50px\">" . $escaper->escapeHtml($scoring_method) . "</td>\n";
	echo "<td class=\"calculated_risk risk-cell ".$escaper->escapeHtml($color)." \" " . ($column_calculated_risk == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"25px\"><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div>"."</td>\n";
	echo "<td class=\"submission_date\" " . ($column_submission_date == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(DATETIMESIMPLE, strtotime($submission_date))) . "</td>\n";
	echo "<td class=\"review_date\" " . ($column_review_date == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($review_date) . "</td>\n";
	echo "<td class=\"project\" " . ($column_project == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($project) . "</td>\n";
	echo "<td class=\"mitigation_planned\" " . ($column_mitigation_planned == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . planned_mitigation(convert_id($risk_id), $mitigation_id) . "</td>\n";
	echo "<td class=\"management_review\" " . ($column_management_review == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . management_review(convert_id($risk_id), $mgmt_review) . "</td>\n";
	echo "<td class=\"days_open\" " . ($column_days_open == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($days_open) . "</td>\n";
	echo "<td class=\"next_review_date\" " . ($column_next_review_date == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $next_review_date_html . "</td>\n";
	echo "<td class=\"next_step\" " . ($column_next_step == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($next_step) . "</td>\n";
	echo "<td class=\"affected_assets\" " . ($column_affected_assets == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($affected_assets) . "</td>\n";
        echo "<td class=\"risk_assessment\" " . ($column_risk_assessment == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk_assessment) . "</td>\n";
    echo "<td class=\"additional_notes\" " . ($column_additional_notes == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($additional_notes) . "</td>\n";
    echo "<td class=\"current_solution\" " . ($column_current_solution == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($current_solution) . "</td>\n";
    echo "<td class=\"security_recommendations\" " . ($column_security_recommendations == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($security_recommendations) . "</td>\n";
	echo "<td class=\"security_requirements\" " . ($column_security_requirements == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($security_requirements) . "</td>\n";
	echo "<td class=\"planning_strategy\" " . ($column_planning_strategy == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($planning_strategy) . "</td>\n";
	echo "<td class=\"mitigation_effort\" " . ($column_mitigation_effort == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($mitigation_effort) . "</td>\n";
	echo "<td class=\"mitigation_cost\" " . ($column_mitigation_cost == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($mitigation_cost) . "</td>\n";
	echo "<td class=\"mitigation_owner\" " . ($column_mitigation_owner == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($mitigation_owner) . "</td>\n";
	echo "<td class=\"mitigation_team\" " . ($column_mitigation_team == true ? "" : "style=\"display:tnone;\" ") . "align=\"center\" width=\"150px\">" . $escaper->escapeHtml($mitigation_team) . "</td>\n";
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

/*************************
 * FUNCTION: RISKS QUERY *
 *************************/
function risks_query($status, $sort, $group, &$rowCount, $start=0, $length=10, $group_value_from_db="")
{
        global $lang;
        // Check the status
        switch ($status)
        {
                // Open risks
                case 0:
                        $status_query = " WHERE a.status != \"Closed\" ";
                        break;
                // Closed risks
                case 1:
                        $status_query = " WHERE a.status = \"Closed\" ";
                        break;
                case 2:
                // All risks
                        $status_query = " WHERE 1 ";
                        break;
                // Default to open risks
                default:
                        $status_query = " WHERE a.status != \"Closed\" ";
                        break;
        }

        // Check the sort
        switch ($sort)
        {
                // Calculated Risk
                case 0:
                        $sort_name = " calculated_risk DESC ";
                        break;
                // ID
                case 1:
                        $sort_name = " a.id ASC ";
                        break;
                // Subject
                case 2:
                        $sort_name = " a.subject ASC ";
                        break;
                // Default to calculated risk
                default:
                        $sort_name = " calculated_risk DESC ";
                        break;
        }

        // Check the group
        switch ($group)
        {
                // None
                case 0:
                        $order_query = "GROUP BY id ORDER BY" . $sort_name;
                        $group_name = "none";
                        break;
                // Risk Level
                case 1:
                        $order_query = "GROUP BY id ORDER BY calculated_risk DESC, " . $sort_name;
                        $group_name = "risk_level";
                        break;
                // Status
                case 2:
                        $order_query = "GROUP BY id ORDER BY a.status," . $sort_name;
                        $group_name = "status";
                        break;
                // Site/Location
                case 3:
                        $order_query = "GROUP BY id ORDER BY location," . $sort_name;
                        $group_name = "location";
                        break;
                // Source
                case 4:
                        $order_query = "GROUP BY id ORDER BY source," . $sort_name;
                        $group_name = "source";
                        break;
                // Category
                case 5:
                        $order_query = "GROUP BY id ORDER BY category," . $sort_name;
                        $group_name = "category";
                        break;
                // Team
                case 6:
                        $order_query = "GROUP BY id ORDER BY team," . $sort_name;
                        $group_name = "team";
                        break;
                // Technology
                case 7:
                        $order_query = "GROUP BY id ORDER BY technology," . $sort_name;
                        $group_name = "technology";
                        break;
                // Owner
                case 8:
                        $order_query = "GROUP BY id ORDER BY owner," . $sort_name;
                        $group_name = "owner";
                        break;
                // Owners Manager
                case 9:
                        $order_query = "GROUP BY id ORDER BY manager," . $sort_name;
                        $group_name = "manager";
                        break;
                // Risk Scoring Method
                case 10:
                        $order_query = "GROUP BY id ORDER BY scoring_method," . $sort_name;
                        $group_name = "scoring_method";
                        break;
                // Regulation
                case 11:
                        $order_query = "GROUP BY id ORDER BY regulation," . $sort_name;
                        $group_name = "regulation";
                        break;
                // Project
                case 12:
                        $order_query = "GROUP BY id ORDER BY project," . $sort_name;
                        $group_name = "project";
                        break;
                // Next Step
                case 13:
                        $order_query = "GROUP BY id ORDER BY next_step," . $sort_name;
                        $group_name = "next_step";
                        break;
                // Month Submitted
                case 14:
                        $order_query = "GROUP BY id ORDER BY submission_date DESC," . $sort_name;
                        $group_name = "month_submitted";
                        break;
                // Default to calculated risk
                default:
                        $order_query = "GROUP BY id ORDER BY" . $sort_name;
                        $group_name = "none";
                        break;
	}
    $group_field_name = "";
    $whereQuery = " where 1 ";
    if($group_name != "none"){
        if($group_name == "month_submitted"){
            $group_value_from_db = date('Y-m', strtotime($group_value_from_db))."%"; 
            $whereQuery .= " and t1.submission_date like :group_value ";
        }else{
            switch($group_name){
                case "risk_level":
                    $group_value_from_db = get_risk_level_name($group_value_from_db);
                    $group_field_name = " t1.risk_level_name";
                break;
                default:
                    $group_field_name = " t1.{$group_name} ";
                break;
            }
            $whereQuery .= " and ({$group_field_name} = :group_value or :group_value = '' and {$group_field_name} is null) ";
        }
    }

	// If the team separation extra is not enabled
	if (!team_separation_extra())
	{
    $query = "SELECT a.id AS id, a.status, a.subject, a.reference_id, a.control_number, a.submission_date, a.last_update, a.review_date, a.mgmt_review, a.assessment as risk_assessment, a.notes as additional_notes, b.scoring_method, b.calculated_risk, c.name AS location, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, i.name AS submitted_by, j.name AS regulation, k.name AS project, l.next_review, m.name AS next_step, GROUP_CONCAT(DISTINCT n.asset SEPARATOR ', ') AS affected_assets, o.closure_date, q.name AS planning_strategy, r.name AS mitigation_effort, s.min_value AS mitigation_min_cost, s.max_value AS mitigation_max_cost, t.name AS mitigation_owner, u.name AS mitigation_team, v.name AS source, p.id mitigation_id, p.current_solution, p.security_recommendations, p.security_requirements, ifnull((SELECT name FROM risk_levels WHERE value<=b.calculated_risk ORDER BY value DESC LIMIT 1), '{$lang['Insignificant']}') as risk_level_name
    FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN location c ON a.location = c.value LEFT JOIN category d ON a.category = d.value LEFT JOIN team e ON a.team = e.value LEFT JOIN technology f ON a.technology = f.value LEFT JOIN user g ON a.owner = g.value LEFT JOIN user h ON a.manager = h.value LEFT JOIN user i ON a.submitted_by = i.value LEFT JOIN regulation j ON a.regulation = j.value LEFT JOIN projects k ON a.project_id = k.value LEFT JOIN mgmt_reviews l ON a.mgmt_review = l.id LEFT JOIN next_step m ON l.next_step = m.value LEFT JOIN risks_to_assets n ON a.id = n.risk_id LEFT JOIN closures o ON a.close_id = o.id LEFT JOIN mitigations p ON a.id = p.risk_id LEFT JOIN planning_strategy q ON p.planning_strategy = q.value LEFT JOIN mitigation_effort r ON p.mitigation_effort = r.value LEFT JOIN asset_values s ON p.mitigation_cost = s.id LEFT JOIN user t ON p.mitigation_owner = h.value LEFT JOIN team u ON p.mitigation_team = u.value LEFT JOIN source v ON a.source = v.value " . $status_query . $order_query ;
	}
	// Otherwise
	else
	{
		// Include the team separation extra
		require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

		// Get the separation query string
		$separation_query = get_user_teams_query("a", false, true);

		$query = "SELECT a.id AS id, a.status, a.subject, a.reference_id, a.control_number, a.submission_date, a.last_update, a.review_date, a.mgmt_review, a.assessment as risk_assessment, a.notes as additional_notes, b.scoring_method, b.calculated_risk, c.name AS location, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, i.name AS submitted_by, j.name AS regulation, k.name AS project, l.next_review, m.name AS next_step, GROUP_CONCAT(DISTINCT n.asset SEPARATOR ', ') AS affected_assets, o.closure_date, q.name AS planning_strategy, r.name AS mitigation_effort, s.min_value AS mitigation_min_cost, s.max_value AS mitigation_max_cost, t.name AS mitigation_owner, u.name AS mitigation_team, v.name AS source, p.id mitigation_id, p.current_solution, p.security_recommendations, p.security_requirements, ifnull((SELECT name FROM risk_levels WHERE value<=b.calculated_risk ORDER BY value DESC LIMIT 1), '{$lang['Insignificant']}') as risk_level_name
		FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN location c ON a.location = c.value LEFT JOIN category d ON a.category = d.value LEFT JOIN team e ON a.team = e.value LEFT JOIN technology f ON a.technology = f.value LEFT JOIN user g ON a.owner = g.value LEFT JOIN user h ON a.manager = h.value LEFT JOIN user i ON a.submitted_by = i.value LEFT JOIN regulation j ON a.regulation = j.value LEFT JOIN projects k ON a.project_id = k.value LEFT JOIN mgmt_reviews l ON a.mgmt_review = l.id LEFT JOIN next_step m ON l.next_step = m.value LEFT JOIN risks_to_assets n ON a.id = n.risk_id LEFT JOIN closures o ON a.close_id = o.id LEFT JOIN mitigations p ON a.id = p.risk_id LEFT JOIN planning_strategy q ON p.planning_strategy = q.value LEFT JOIN mitigation_effort r ON p.mitigation_effort = r.value LEFT JOIN asset_values s ON p.mitigation_cost = s.id LEFT JOIN user t ON p.mitigation_owner = h.value LEFT JOIN team u ON p.mitigation_team = u.value LEFT JOIN source v ON a.source = v.value " . $status_query . $separation_query . $order_query ;
	}
    
    $query = "
        select t1.*
        from (
            {$query}
        ) t1
        {$whereQuery}
    ";

    // Query the database
    $db = db_open();
    $stmt = $db->prepare($query);
    if($group_name != "none"){
        $stmt->bindParam(":group_value", $group_value_from_db, PDO::PARAM_STR, 250);
    }
//    echo $group_value_from_db;exit;
//    print_r($stmt);exit;
    $stmt->execute();
    db_close($db);

    // Store the results in the risks array
    $risks = $stmt->fetchAll();
    
    // Initialize the data array
    $data = array();
    $rowCount = count($risks);
    if($length == -1){
        $length = $rowCount;
        $start = 0;
    }
    
    $risk_levels = get_risk_levels();
    $review_levels = get_review_levels();


    // For each risk in the risks array
    for( $i = $start; $i < $start + $length && $i<$rowCount && $risks[$i]; $i++ ){
            $risk = $risks[$i];
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
            else $review_date = date(DATETIMESIMPLE, strtotime($review_date));
            
            $scoring_method = get_scoring_method_name($risk['scoring_method']);
            $calculated_risk = (float)$risk['calculated_risk'];
            $color = get_risk_color_from_levels($risk['calculated_risk'], $risk_levels);
            $risk_level = get_risk_level_name_from_levels($risk['calculated_risk'], $risk_levels);
            $location = $risk['location'];
            $source = $risk['source'];
            $category = $risk['category'];
            $team = $risk['team'];
            $technology = $risk['technology'];
            $owner = $risk['owner'];
            $manager = $risk['manager'];
            $submitted_by = $risk['submitted_by'];
            $regulation = $risk['regulation'];
            $project = try_decrypt($risk['project']);
            $mitigation_id = $risk['mitigation_id'];
            $mgmt_review = $risk['mgmt_review'];
            $days_open = dayssince($risk['submission_date']);
            $next_review_date = next_review($risk_level, $risk_id, $risk['next_review'], false, $review_levels);
            $next_review_date_html = next_review($risk_level, $risk_id, $risk['next_review'], true, $review_levels);
            $next_step = $risk['next_step'];
            $affected_assets = $risk['affected_assets'];
            $risk_assessment = try_decrypt($risk['risk_assessment']);
            $additional_notes = try_decrypt($risk['additional_notes']);
            $current_solution = try_decrypt($risk['current_solution']);
            $security_recommendations = try_decrypt($risk['security_recommendations']);
            $security_requirements = try_decrypt($risk['security_requirements']);
            $month_submitted = date('Y F', strtotime($risk['submission_date']));
            $planning_strategy = $risk['planning_strategy'];
            $mitigation_effort = $risk['mitigation_effort'];
            $mitigation_min_cost = $risk['mitigation_min_cost'];
            $mitigation_max_cost = $risk['mitigation_max_cost'];
            $mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;;
            $mitigation_owner = $risk['mitigation_owner'];
            $mitigation_team = $risk['mitigation_team'];

            // If the group name is not none
            if ($group_name != "none")
            {
                    $group_value = ${$group_name};

                    // If the selected group value is empty
                    if ($group_value == "")
                    {
                            // Current group is Unassigned
                            $group_vaue = $lang['Unassigned'];
                    }
            }
		    else $group_value = $group_name;

            // Create the new data array
            $data[] = array("id" => $risk_id, "status" => $status, "subject" => $subject, "reference_id" => $reference_id, "control_number" => $control_number, "submission_date" => $submission_date, "last_update" => $last_update, "review_date" => $review_date, "scoring_method" => $scoring_method, "calculated_risk" => $calculated_risk, "color" => $color, "risk_level" => $risk_level, "location" => $location, "source" => $source, "category" => $category, "team" => $team, "technology" => $technology, "owner" => $owner, "manager" => $manager, "submitted_by" => $submitted_by, "regulation" => $regulation, "project" => $project, "mgmt_review" => $mgmt_review, "days_open" => $days_open, "next_review_date" => $next_review_date, "next_review_date_html" => $next_review_date_html, "next_step" => $next_step, "affected_assets" => $affected_assets, "risk_assessment" => $risk_assessment, "additional_notes" => $additional_notes, "current_solution" => $current_solution, "security_recommendations" => $security_recommendations, "security_requirements" => $security_requirements, "month_submitted" => $month_submitted, "planning_strategy" => $planning_strategy, "mitigation_id" => $mitigation_id, "mitigation_effort" => $mitigation_effort, "mitigation_min_cost" => $mitigation_min_cost, "mitigation_max_cost" => $mitigation_max_cost, "mitigation_cost" => $mitigation_cost, "mitigation_owner" => $mitigation_owner, "mitigation_team" => $mitigation_team, "group_name" => $group_name, "group_value" => $group_value);
	}

	// Return the data array
	return $data;
}

/***************************
 * FUNCTION: GET PIE ARRAY *
 ***************************/
function get_pie_array($filter = null)
{
        // Open the database connection
        $db = db_open();

	// Check the filter for the query to use
	switch($filter)
	{
               case 'status':
                        $field = "status";
                        $stmt = $db->prepare("SELECT id, status FROM `risks` WHERE status != \"Closed\" ORDER BY status DESC");
                        $stmt->execute();
                        break;
                case 'location':
                        $field = "name";
                        $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `location` b ON a.location = b.value WHERE status != \"Closed\" ORDER BY b.name DESC");
                        $stmt->execute();
                        break;
                case 'source':
                        $field = "name";
                        $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `source` b ON a.source = b.value WHERE status != \"Closed\" ORDER BY b.name DESC");
                        $stmt->execute();
                        break;
                case 'category':
                        $field = "name";
                        $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `category` b ON a.category = b.value WHERE status != \"Closed\" ORDER BY b.name DESC");
                        $stmt->execute();
                        break;
                case 'team':
                        $field = "name";
                        $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `team` b ON a.team = b.value WHERE status != \"Closed\" ORDER BY b.name DESC");
                        $stmt->execute();
                        break;
                case 'technology':
                        $field = "name";
                        $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `technology` b ON a.technology = b.value WHERE status != \"Closed\" ORDER BY b.name DESC");
                        $stmt->execute();
                        break;
                case 'owner':
                        $field = "name";
                        $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `user` b ON a.owner = b.value WHERE status != \"Closed\" ORDER BY b.name DESC");
                        $stmt->execute();
                        break;
                case 'manager':
                        $field = "name";
                        $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `user` b ON a.manager = b.value WHERE status != \"Closed\" ORDER BY b.name DESC");
                        $stmt->execute();
                        break;
                case 'scoring_method':
                        $field = "name";
                        $stmt = $db->prepare("SELECT a.id, CASE WHEN scoring_method = 5 THEN 'Custom' WHEN scoring_method = 4 THEN 'OWASP' WHEN scoring_method = 3 THEN 'DREAD' WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS name, COUNT(*) AS num FROM `risks` a LEFT JOIN `risk_scoring` b ON a.id = b.id WHERE status != \"Closed\" ORDER BY b.scoring_method DESC");
                        $stmt->execute();
                        break;
                case 'close_reason':
                        $field = "name";
                        $stmt = $db->prepare("SELECT a.close_reason, a.risk_id as id, b.name, MAX(closure_date) FROM `closures` a JOIN `close_reason` b ON a.close_reason = b.value JOIN `risks` c ON a.risk_id = c.id WHERE c.status = \"Closed\" GROUP BY risk_id ORDER BY name DESC;");
                        $stmt->execute();
                        break;
                default:
			$stmt = $db->prepare("SELECT a.id, a.status, b.name AS location, c.name AS source, d.name AS category, e.name AS team, f.name AS technology, g.name AS owner, h.name AS manager, CASE WHEN scoring_method = 5 THEN 'Custom' WHEN scoring_method = 4 THEN 'OWASP' WHEN scoring_method = 3 THEN 'DREAD' WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS scoring_method FROM `risks` a LEFT JOIN `location` b ON a.location = b.value LEFT JOIN `source` c ON a.source = c.value LEFT JOIN `category` d ON a.category = d.value LEFT JOIN `team` e ON a.team = e.value LEFT JOIN `technology` f ON a.technology = f.value LEFT JOIN `user` g ON a.owner = g.value LEFT JOIN `user` h ON a.manager = h.value LEFT JOIN `risk_scoring` i ON a.id = i.id WHERE a.status != \"Closed\"");
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

	// Return the data
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
		$stmt = $db->prepare("SELECT a.risk_id as id, a.closure_date, c.status FROM closures a LEFT JOIN risks c ON a.risk_id=c.id WHERE a.closure_date=(SELECT max(b.closure_date) FROM closures b WHERE a.risk_id=b.risk_id) AND c.status='Closed' order by closure_date;");
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
                        else $close_total[$counter] = $close_total[$counter-1] + 1;
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

?>
