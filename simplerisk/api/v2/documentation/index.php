<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

spl_autoload_register('autoloader');
function autoloader(string $name)
{
    // Load the documentation files
    require_once realpath(__DIR__ . '/general.php');
    if (file_exists(realpath(__DIR__ . '/general.php')))
    {
        require_once realpath(__DIR__ . '/general.php');
    }

    if (file_exists(realpath(__DIR__ . '/security.php')))
    {
        require_once realpath(__DIR__ . '/security.php');
    }

    if (file_exists(realpath(__DIR__ . '/admin.php')))
    {
        require_once realpath(__DIR__ . '/admin.php');
    }

    if (file_exists(realpath(__DIR__ . '/assets.php')))
    {
        require_once realpath(__DIR__ . '/assets.php');
    }
}

// Include required functions file
require_once(realpath(__DIR__ . '/../../../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));

$scan_directories = [
    realpath(__DIR__ . '/general.php'),
    realpath(__DIR__ . '/security.php'),
    realpath(__DIR__ . '/admin.php'),
    realpath(__DIR__ . '/assets.php'),
];
$openapi = \OpenApi\Generator::scan($scan_directories);
header('Content-Type: application/json');
echo $openapi->toJson();


?>