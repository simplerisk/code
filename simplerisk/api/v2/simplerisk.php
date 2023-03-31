<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));

/**********************************
 * FUNCTION: API V2 ADMIN VERSION *
 * ********************************/
/**
 * @OA\Get(
 *     path="/admin/version",
 *     summary="List SimpleRisk version information",
 *     operationId="version",
 *     tags={"admin"},
 *     @OA\Parameter(
 *       ref="#/components/parameters/key",
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk version information",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not having admin privileges.",
 *     ),
 * )
 */
function api_v2_admin_version()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Get the current version of the SimpleRisk application
    $current_version_app = current_version("app");

    // Get the current version of the SimpleRisk database
    $current_version_db = current_version("db");

    // Create the data array
    $data = [
        "app" => $current_version_app,
        "db" => $current_version_db,
    ];

    // Set the status
    $status_code = 200;
    $status_message = "SUCCESS";

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/**************************************
 * FUNCTION: API V2 ADMIN VERSION APP *
 **************************************/
/**
 * @OA\Get(
 *     path="/admin/version/app",
 *     summary="List SimpleRisk application version information",
 *     operationId="appVersion",
 *     tags={"admin"},
 *     @OA\Parameter(
 *       ref="#/components/parameters/key",
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk application version information",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not having admin privileges.",
 *     ),
 * )
 */
function api_v2_admin_version_app()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Get the current version of the SimpleRisk application
    $current_version_app = current_version("app");

    // Create the data array
    $data = [
        "app" => $current_version_app,
    ];

    // Set the status
    $status_code = 200;
    $status_message = "SUCCESS";

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/*************************************
 * FUNCTION: API V2 ADMIN VERSION DB *
 *************************************/
/**
 * @OA\Get(
 *     path="/admin/version/db",
 *     summary="List SimpleRisk database version information",
 *     operationId="dbVersion",
 *     tags={"admin"},
 *     @OA\Parameter(
 *       ref="#/components/parameters/key",
 *     ),
 *     @OA\Response(
 *       response=200,
 *       description="SimpleRisk database version information",
 *     ),
 *     @OA\Response(
 *       response=403,
 *       description="FORBIDDEN: The user does not having admin privileges.",
 *     ),
 * )
 */
function api_v2_admin_version_db()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Get the current version of the SimpleRisk database
    $current_version_db = current_version("db");

    // Create the data array
    $data = [
        "db" => $current_version_db,
    ];

    // Set the status
    $status_code = 200;
    $status_message = "SUCCESS";

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

?>