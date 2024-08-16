<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));

require_once(language_file());

/**************************
 * FUNCTION: API V2 RISKS *
 * ************************/
function api_v2_risks()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("riskmanagement");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // Get just the risk with that id
        $risk = get_risk_by_id($id);

        // If the risk value returned is empty then we are unable to find a risk with that id
        if (empty($risk))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a risk with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "risk" => $risk,
            ];
        }
    }
    // Otherwise, return all risks
    else
    {
        // Get the risks array
        $risks = get_risks(0, "id", "asc");

        // Create the data array
        $data = [
            "risks" => $risks,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/***************************************
 * FUNCTION: API V2 RISKS ASSOCIATIONS *
 * *************************************/
function api_v2_risks_associations()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("riskmanagement");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // If the user should have access to this risk id
        if (check_access_for_risk($id))
        {
            // Get the connectivity for the risk
            $asset_associations = get_asset_connectivity_for_risk($id);
            $control_associations = get_control_connectivity_for_risk($id);

            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "assets" => $asset_associations,
                "controls" => $control_associations,
            ];
        }
        // If the user should not have access to this risk id
        else
        {
            // Set the status
            $status_code = 403;
            $status_message = "FORBIDDEN: The user does not have the required permission to perform this action.";
            $data = null;
        }
    }
    // Otherwise, return an empty data array
    else
    {
        // Create the data array
        $data = [];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/***********************************
 * FUNCTION: API V2 RISKS TAGS GET *
 * *********************************/
function api_v2_risks_tags_get()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("riskmanagement");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // Open a database connection
    $db = db_open();

    // If we received an id
    if (!empty($id))
    {
        // Get just the tag with that id
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT r.id+1000 ORDER BY r.id ASC) as risk_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `risks` r ON r.id=tt.taggee_id WHERE tt.type='risk' AND t.id=:id GROUP BY t.id;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a tag with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the risk_id string into an array
                $tags[$key]['risk_ids'] = explode(',', $tag['risk_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each risk id
                    foreach ($tags[$key]['risk_ids'] as $risk_id)
                    {
                        // If the user should not have access to this risk id
                        if (!extra_grant_access($_SESSION['uid'], $risk_id))
                        {
                            // Remove it from the array
                            $tags[$key]['risk_ids'] = array_diff($tags[$key]['risk_ids'], [$risk_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }
    // Otherwise, return all tags
    else
    {
        // Get the list of tags and associated risks
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT r.id+1000 ORDER BY r.id ASC) as risk_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `risks` r ON r.id=tt.taggee_id WHERE tt.type='risk' GROUP BY t.id;");
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: No tags found.";
            $data = null;
        }
        else
        {
            // Return the result
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the risk_id string into an array
                $tags[$key]['risk_ids'] = explode(',', $tag['risk_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each risk id
                    foreach ($tags[$key]['risk_ids'] as $risk_id)
                    {
                        // If the user should not have access to this risk id
                        if (!extra_grant_access($_SESSION['uid'], $risk_id))
                        {
                            // Remove it from the array
                            $tags[$key]['risk_ids'] = array_diff($tags[$key]['risk_ids'], [$risk_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }

    // Close the database connection
    db_close($db);

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

?>