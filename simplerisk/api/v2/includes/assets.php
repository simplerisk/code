<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath( __DIR__ . '/../../../includes/assets.php'));

/**********************************
 * FUNCTION: API V2 ADMIN VERSION *
 * ********************************/
function api_v2_assets()
{
    // Check that this user has the ability to view assets
    api_v2_check_permission("asset");

    // Get the asset id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // Get just the asset with that id
        $asset = get_asset_by_id($id);

        // If the asset value returned is empty then we are unable to find an asset with that id
        if (empty($asset))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find an asset with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "asset" => $asset,
            ];
        }
    }
    // Otherwise, return all assets
    else
    {
        // Get the verified value()
        $verified = get_param("GET", "verified", null);

        // Check the verified value
        switch ($verified)
        {
            case "true":
            case "1":
                $assets = get_verified_assets();
                break;
            case "false":
            case "0":
                $assets = get_unverified_assets();
                break;
            default:
                $assets = get_entered_assets();
                break;
        }

        // Create the data array
        $data = [
            "assets" => $assets,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

?>