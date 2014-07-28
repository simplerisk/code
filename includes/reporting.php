<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/HighchartsPHP/Highchart.php'));
require_once(language_file());

/****************************
 * FUNCTION: GET OPEN RISKS *
 ****************************/
function get_open_risks()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT * FROM `risks` WHERE status != \"Closed\"");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return count($array);
}

/******************************
 * FUNCTION: GET CLOSED RISKS *
 ******************************/
function get_closed_risks()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT * FROM `risks` WHERE status = \"Closed\"");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

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
        $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'High'");
        $stmt->execute();
        $array = $stmt->fetch();
        $high = $array['value'];

        // Query the database
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND a.calculated_risk >= :high");
        $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

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

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT DATE(submission_date) date, COUNT(DISTINCT id) count FROM `risks` GROUP BY DATE(submission_date) ORDER BY DATE(submission_date)");
        $stmt->execute();

        // Store the list in the array
        $opened_risks = $stmt->fetchAll();

        // Query the database
	$stmt = $db->prepare("SELECT DATE(a.closure_date) date, COUNT(DISTINCT b.id) count FROM `closures` a JOIN `risks` b ON a.risk_id = b.id WHERE b.status = \"Closed\" GROUP BY DATE(a.closure_date)");
        $stmt->execute();

        // Store the list in the array
        $closed_risks = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the opened risks array is empty
        if (empty($opened_risks))
        {
                $opened_risk_data[] = array("No Data Available", 0);
        }
        // Otherwise
        else
        {
		// Set the sum to 0
		$opened_sum = 0;
		$closed_sum = 0;

		// Set the start date
		$date = $opened_risks[0]['date'];

		// For each date from the start date until today
		while (strtotime($date) <= time())
		{
			// If the PHP version is >= 5.5.0
			// array_column is new as of PHP 5.5
			if (strnatcmp(phpversion(),'5.5.0') >= 0) 
			{
				// Search the opened array for the value
				$opened_search = array_search($date, array_column($opened_risks, 'date'));
			}
			else $opened_search = false;

			// If the current date is in the opened array
			if ($opened_search !== false)
			{
				$count = $opened_risks[$opened_search]['count'];
				$opened_sum += $count;
			}

                        // If the PHP version is >= 5.5.0
                        // array_column is new as of PHP 5.5
                        if (strnatcmp(phpversion(),'5.5.0') >= 0) 
                        {
                        	// Search the closed array for the value
                        	$closed_search = array_search($date, array_column($closed_risks, 'date'));
			}
			else $closed_search = false;

			// If the current date is in the closed array
			if ($opened_search !== false)
                        {
                                $count = $closed_risks[$closed_search]['count'];
                                $closed_sum += $count;
                        }

			// Create the data arrays
			$opened_risk_data[] = array((strtotime($date) + 2*86400) * 1000, $opened_sum);
			$closed_risk_data[] = array((strtotime($date) + 2*86400) * 1000, $closed_sum);
			$trend_data[] = array((strtotime($date) + 2*86400) * 1000, $opened_sum - $closed_sum);

			// Increment the date one day
			$date = date("Y-m-d", strtotime("+1 day", strtotime($date)));
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

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

	// Get the risk levels
	$stmt = $db->prepare("SELECT * from `risk_levels`");
	$stmt->execute();
	$array = $stmt->fetchAll();
	$high = $array[0][0];
	$medium = $array[1][0];
	$low = $array[2][0];

        // Query the database
        $stmt = $db->prepare("select a.calculated_risk, COUNT(*) AS num, CASE WHEN a.calculated_risk >= " . $high . " THEN 'High' WHEN a.calculated_risk < " . $high . " AND a.calculated_risk >= " . $medium . " THEN 'Medium' WHEN a.calculated_risk < " . $medium . " AND a.calculated_risk >= " . $low . " THEN 'Low' WHEN a.calculated_risk < " . $low . " AND a.calculated_risk >= 0 THEN 'Insignificant' END AS level from `risk_scoring` a JOIN `risks` b ON a.id = b.id WHERE b.status != \"Closed\" GROUP BY level ORDER BY a.calculated_risk DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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
		// Initialize high, medium, and low
		$high = false;
		$medium = false;
		$low = false;
		$color_array = array();

                // Create the data array
                foreach ($array as $row)
                {
                        $data[] = array($row['level'], (int)$row['num']);

			// If we have at least one high risk
			if ($row['level'] == "High" && $high != true)
			{
				$high = true;

				// Add red to the color array
				$color_array[] = "red";
			}
			// If we have at least one medium risk
			else if ($row['level'] == "Medium" && $medium != true)
			{
				$medium = true;

				// Add orange to the color array
				$color_array[] = "orange";
			}
			// If we have at least one low risk
			else if ($row['level'] == "Low" && $low != true)
			{
				$low = true;

				// Add yellow to the color array
				$color_array[] = "yellow";
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
function open_risk_status_pie($title = null)
{
	$chart = new Highchart();

	$chart->chart->renderTo = "open_risk_status_pie";
	$chart->chart->plotBackgroundColor = null;
	$chart->chart->plotBorderWidth = null;
	$chart->chart->plotShadow = false;
	$chart->title->text = $title;

	$chart->tooltip->formatter = new HighchartJsExpr("function() {
    	return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

	$chart->plotOptions->pie->allowPointSelect = 1;
	$chart->plotOptions->pie->cursor = "pointer";
	$chart->plotOptions->pie->dataLabels->enabled = false;
	$chart->plotOptions->pie->showInLegend = 1;
	$chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT status, COUNT(*) AS num FROM `risks` WHERE status != \"Closed\" GROUP BY status ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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
                	$data[] = array($row['status'], (int)$row['num']);
        	}

		$chart->series[] = array('type' => "pie",
			'name' => "Status",
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

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
	$stmt = $db->prepare("SELECT a.close_reason, b.id, b.status, c.name, COUNT(*) AS num FROM `closures` a JOIN `risks` b ON a.risk_id = b.id JOIN `close_reason` c ON a.close_reason= c.value WHERE b.status = \"Closed\" GROUP BY c.name ORDER BY COUNT(*) DESC;");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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
function open_risk_location_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_location_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `location` b ON a.location = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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

    echo "<div id=\"open_risk_location_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_location_pie");
    echo "</script>\n";
}

/************************************
 * FUNCTION: OPEN RISK CATEGORY PIE *
 ************************************/
function open_risk_category_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_category_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `category` b ON a.category = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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

    echo "<div id=\"open_risk_category_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_category_pie");
    echo "</script>\n";
}

/********************************
 * FUNCTION: OPEN RISK TEAM PIE *
 ********************************/
function open_risk_team_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_team_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `team` b ON a.team = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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

    echo "<div id=\"open_risk_team_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_team_pie");
    echo "</script>\n";
}

/**************************************
 * FUNCTION: OPEN RISK TECHNOLOGY PIE *
 **************************************/
function open_risk_technology_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_technology_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `technology` b ON a.technology = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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

    echo "<div id=\"open_risk_technology_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_technology_pie");
    echo "</script>\n";
}

/**************************************
 * FUNCTION: OPEN RISK OWNER PIE *
 **************************************/
function open_risk_owner_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_owner_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `user` b ON a.owner = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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

    echo "<div id=\"open_risk_owner_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_owner_pie");
    echo "</script>\n";
}

/******************************************
 * FUNCTION: OPEN RISK OWNERS MANAGER PIE *
 ******************************************/
function open_risk_owners_manager_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_owners_manager_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT b.name, COUNT(*) AS num FROM `risks` a INNER JOIN `user` b ON a.manager = b.value WHERE status != \"Closed\" GROUP BY b.name ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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

    echo "<div id=\"open_risk_owners_manager_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_owners_manager_pie");
    echo "</script>\n";
}

/******************************************
 * FUNCTION: OPEN RISK SCORING METHOD PIE *
 ******************************************/
function open_risk_scoring_method_pie($title = null)
{
        $chart = new Highchart();

        $chart->chart->renderTo = "open_risk_scoring_method_pie";
        $chart->chart->plotBackgroundColor = null;
        $chart->chart->plotBorderWidth = null;
        $chart->chart->plotShadow = false;
        $chart->title->text = $title;

        $chart->tooltip->formatter = new HighchartJsExpr("function() {
        return '<b>'+ this.point.name +'</b>: '+ this.point.y; }");

        $chart->plotOptions->pie->allowPointSelect = 1;
        $chart->plotOptions->pie->cursor = "pointer";
        $chart->plotOptions->pie->dataLabels->enabled = false;
        $chart->plotOptions->pie->showInLegend = 1;
        $chart->credits->enabled = false;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT CASE WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS name, COUNT(*) AS num FROM `risks` a INNER JOIN `risk_scoring` b ON a.id = b.id WHERE status != \"Closed\" GROUP BY b.scoring_method ORDER BY COUNT(*) DESC");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

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

    echo "<div id=\"open_risk_scoring_method_pie\"></div>\n";
    echo "<script type=\"text/javascript\">";
    echo $chart->render("open_risk_scoring_method_pie");
    echo "</script>\n";
}

/*************************************
 * FUNCTION: GET REVIEW NEEDED TABLE *
 *************************************/
function get_review_needed_table()
{
        global $lang;

        // Get risks marked as consider for projects
        $risks = get_risks(3);

        // Initialize the reviews array
        $reviews = array();

	// Parse through each row in the array
	foreach ($risks as $key => $row)
	{
		// Create arrays for each value
		$risk_id[$key] = (int)$row['id'];
		$subject[$key] = htmlentities(stripslashes($row['subject']), ENT_QUOTES, 'UTF-8', false);
                $status[$key] = htmlentities($row['status'], ENT_QUOTES, 'UTF-8', false);
                $calculated_risk[$key] = htmlentities($row['calculated_risk'], ENT_QUOTES, 'UTF-8', false);
                $color[$key] = get_risk_color($row['calculated_risk']);
                $dayssince[$key] = dayssince($row['submission_date']);
                $next_review[$key] = next_review($color[$key], $risk_id[$key], $row['next_review'], false);
                $next_review_html[$key] = next_review($color[$key], $row['id'], $row['next_review']);

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
        			echo "<th bgcolor=\"#0088CC\" colspan=\"6\"><center><font color=\"#FFFFFF\">". $review_status ."</font></center></th>\n";
        			echo "</tr>\n";
        			echo "<tr>\n";
        			echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        			echo "<th align=\"left\" width=\"150px\">". $lang['Status'] ."</th>\n";
        			echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        			echo "<th align=\"center\" width=\"100px\">". $lang['Risk'] ."</th>\n";
        			echo "<th align=\"center\" width=\"100px\">". $lang['DaysOpen'] ."</th>\n";
        			echo "<th align=\"center\" width=\"150px\">". $lang['NextReviewDate'] ."</th>\n";
        			echo "</tr>\n";
        			echo "</thead>\n";
        			echo "<tbody>\n";
			}
		}

		// If the review status is not a date
		if (!preg_match('/\d{4}/', $review_status))
                {
                	echo "<tr>\n";
                	echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
			echo "<td align=\"left\" width=\"150px\">" . $status . "</td>\n";
                	echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                	echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . $calculated_risk . "</td>\n";
			echo "<td align=\"center\" width=\"100px\">" . $dayssince . "</td>\n";
                	echo "<td align=\"center\" width=\"150px\">" . $next_review_html . "</td>\n";
                	echo "</tr>\n";
		}
        }
}

?>
