<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/../../includes/functions.php'));

define('SIMPLERISK_BASE_URL', get_setting("simplerisk_base_url"));
define('API_PATH', "/api/v2");
define('SIMPLERISK_API_URL', SIMPLERISK_BASE_URL.API_PATH);

$swagger = \OpenApi\scan(realpath(__DIR__));
header('Content-Type: application/json');
echo $swagger->toJson();


?>