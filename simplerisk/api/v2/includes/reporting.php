<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath( __DIR__ . '/../../../includes/reporting.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));

require_once(language_file());

/*****************************************
 * FUNCTION: API V2 REPORTS RISK AVERAGE *
 * ***************************************/
function api_v2_reports_risk_average()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("riskmanagement");

    // Get the risk id, type and timeframe
    $risk_id = get_param("GET", "risk_id", null);
    $type = get_param("GET", "type", "inherent");
    $timeframe = get_param("GET", "timeframe", "day");

    // Check the desired timeframe period
    switch ($timeframe)
    {
        // By day
        case "day":
            $programmatic_formatting = "Y-m-d";
            $formatting = get_default_date_format();
            break;
        // By month
        case "month":
            $programmatic_formatting = "Y-m-01";
            $formatting = "F Y";
            break;
        // By year
        case "year":
            $programmatic_formatting = "Y-01-01";
            $formatting = "Y";
            break;
        // By day
        default:
            $programmatic_formatting = "Y-m-d";
            $formatting = get_default_date_format();
            $timeframe = "day";
            break;
    }

    // Open a database connection
    $db = db_open();

    // If we received a risk id
    if ($risk_id !== null)
    {
        // Subtract 1000 to get the id from the provided risk id
        $id = $risk_id - 1000;

        // Check whether we want inherent or residual risk
        switch ($type)
        {
            case "inherent":
                $sql = "SELECT rsh.risk_id as id, rsh.calculated_risk, rsh.last_update FROM risk_scoring_history rsh WHERE risk_id = :id ORDER BY rsh.last_update;";
                break;
            case "residual":
                $sql = "SELECT rrsh.risk_id as id, rrsh.residual_risk as calculated_risk, rrsh.last_update FROM residual_risk_scoring_history rrsh WHERE risk_id = :id ORDER BY rrsh.last_update;";
                break;
            default:
                $sql = "SELECT null;";
                break;
        }

        // Get the risk scoring history for the provided id and type
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If team separation is enabled
        if (team_separation_extra())
        {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

            // Strip out risks the user should not have access to
            $array = strip_no_access_risks($array);
        }
    }
    // If we did not receive a risk id
    else
    {
        // Check whether we want inherent or residual risk
        switch ($type)
        {
            case "inherent":
                $sql = "SELECT rsh.risk_id as id, rsh.calculated_risk, rsh.last_update FROM risk_scoring_history rsh ORDER BY rsh.last_update;";
                break;
            case "residual":
                $sql = "SELECT rrsh.risk_id as id, rrsh.residual_risk as calculated_risk, rrsh.last_update FROM residual_risk_scoring_history rrsh ORDER BY rrsh.last_update;";
                break;
            default:
                $sql = "SELECT null;";
                break;
        }

        // Get the risk scoring history for the provided  type
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If team separation is enabled
        if (team_separation_extra())
        {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

            // Strip out risks the user should not have access to
            $array = strip_no_access_risks($array);
        }
    }

    // Close the database connection
    db_close($db);

    // If we did not find any data
    if (empty($array))
    {
        // Set the status
        $status_code = 204;
        $status_message = "NO CONTENT: Unable to find the requested data.";
        $data = null;
    }
    // Otherwise, prepare the results
    else
    {

        // Create the data arrays
        $risk_ids = [];
        $risk_scores = [];
        $dates = [];
        $total = 0;
        $selected_last_update = null;
        $array_index = -1;
        $labels = [];
        $data = [];

        // For each item in the array
        foreach ($array as $row)
        {
            // Get the values
            $id = $row['id'];
            $calculated_risk = $row['calculated_risk'];
            $last_update = $row['last_update'];

            // Format the last_update date
            $formatted_last_update = date($programmatic_formatting, strtotime($last_update));

            // Search the array for the risk id
            $found = array_search($id, $risk_ids);

            // If we have already seen this risk id
            if ($found !== false)
            {
                // Get the current calculated risk value at the index
                $current_calculated_risk = $risk_scores[$found];

                // Subtract it from the total
                $total -= $current_calculated_risk;

                // Add the new value to the total
                $total += $calculated_risk;

                // Replace the value at the index with the new value
                $risk_scores[$found] = $calculated_risk;
            }
            // If this is a new risk id
            else
            {
                // Put the risk ID and score in the arrays
                $risk_ids[] = $id;
                $risk_scores[] = $calculated_risk;

                // Increment the total
                $total += $calculated_risk;
            }

            // If the formatted date is new
            if ($selected_last_update != $formatted_last_update)
            {
                // Get the average
                $average = round(($total / count($risk_ids)), 2);

                // Increment the array index
                $array_index++;

                // Insert the date and average into the arrays
                $dates[$array_index] = $formatted_last_update;
                $averages[$array_index] = $average;

                // Set the selected date to the formatted date
                $selected_last_update = $formatted_last_update;
            }
            // If this is an existing formatted date
            else
            {
                // Get the average
                $average = round(($total / count($risk_ids)), 2);

                // Update the average in the data array
                $averages[$array_index] = $average;
            }
        }

        // Set the starting average
        $average = $averages[0];

        // For each date from the first date in the dates array until today
        for ($date = $dates[0]; $date <= date($programmatic_formatting, time()); $date = date($programmatic_formatting, strtotime("+1 {$timeframe}", strtotime($date))))
        {
            // Add it to the labels array
            $labels[] = date($formatting, strtotime($date));

            // If the date matches a date that we have data for
            $found = array_search($date, $dates);
            if ($found !== false)
            {
                // Get the new average on that date
                $average = $averages[$found];
            }

            // Insert the average into the data array
            $data[] = $average;
        }

        // Create the data array
        $data = [
            "dates" => $labels,
            "averages" => $data,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/********************************************
 * FUNCTION: API V2 REPORTS RISK OPEN COUNT *
 * ******************************************/
function api_v2_reports_risk_open_count()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("riskmanagement");

    // Get the timeframe
    $timeframe = get_param("GET", "timeframe", "day");

    // Check the desired timeframe period
    switch ($timeframe)
    {
        // By day
        case "day":
            $programmatic_formatting = "Y-m-d";
            $formatting = get_default_date_format();
            break;
        // By month
        case "month":
            $programmatic_formatting = "Y-m-01";
            $formatting = "F Y";
            break;
        // By year
        case "year":
            $programmatic_formatting = "Y-01-01";
            $formatting = "Y";
            break;
        // By day
        default:
            $programmatic_formatting = "Y-m-d";
            $formatting = get_default_date_format();
            $timeframe = "day";
            break;
    }

    // Open a database connection
    $db = db_open();

    // Get the audit log
    $sql = "SELECT risk_id as id, message, timestamp FROM audit_log ORDER BY timestamp;";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

        // Strip out risks the user should not have access to
        $array = strip_no_access_risks($array);
    }

    // Close the database connection
    db_close($db);

    // If we did not find any data
    if (empty($array))
    {
        // Set the status
        $status_code = 204;
        $status_message = "NO CONTENT: Unable to find the requested data.";
        $data = null;
    }
    // Otherwise, prepare the results
    else
    {
        // Create the patterns to match for new, closed and reopened risks
        $new_pattern = "/^A new risk ID \"(?P<id>\d+)\" was submitted by username \"(?P<username>.*)\".*/";
        $closed_pattern = "/^Risk ID \"(?P<id>\d+)\" was marked as closed by username \"(?P<username>.*)\".*/";
        $reopened_pattern = "/^Risk ID \"(?P<id>\d+)\" was reopened by username \"(?P<username>.*)\".*/";

        // Set the starting values
        $count = 0;
        $selected_timestamp = null;
        $array_index = -1;
        $dates = [];
        $counts = [];

        // For each audit log entry
        foreach ($array as $log)
        {
            // Get the audit log message and timestamp
            $timestamp = $log['timestamp'];
            $message = try_decrypt($log['message']);

            // Format the timestamp date
            $formatted_timestamp = date($programmatic_formatting, strtotime($timestamp));

            // If the message is for opening a new risk
            if (preg_match($new_pattern, $message))
            {
                // Increment the count by 1
                $count++;
            }
            // If the message is for closing a risk
            else if (preg_match($closed_pattern, $message))
            {
                // Decrement the count by 1
                $count--;
            }
            // If the message is for reopening a risk
            else if (preg_match($reopened_pattern, $message))
            {
                // Increment the count by 1
                $count++;
            }

            // If the formatted date is new and we have at least one risk
            if ($selected_timestamp != $formatted_timestamp && $count > 0)
            {
                // Increment the array index
                $array_index++;

                // Insert the date and count into the arrays
                $dates[$array_index] = $formatted_timestamp;
                $counts[$array_index] = $count;

                // Set the selected date to the formatted date
                $selected_timestamp = $formatted_timestamp;
            }
            // If this is an existing formatted date
            else
            {
                // Update the count
                $counts[$array_index] = $count;
            }
        }

        // Set the starting count
        $count = $counts[0];

        // For each date from the first date in the dates array until today
        for ($date = $dates[0]; $date <= date($programmatic_formatting, time()); $date = date($programmatic_formatting, strtotime("+1 {$timeframe}", strtotime($date))))
        {
            // Add it to the labels array
            $labels[] = date($formatting, strtotime($date));

            // If the date matches a date that we have data for
            $found = array_search($date, $dates);
            if ($found !== false)
            {
                // Get the new count on that date
                $count = $counts[$found];
            }

            // Insert the average into the data array
            $data[] = $count;
        }

        // Create the data array
        $data = [
            "dates" => $labels,
            "counts" => $data,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

?>