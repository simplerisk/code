<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/services.php'));

	// If the API Extra is installed
	if (api_extra())
	{
		// Include the API Extra
		require_once(realpath(__DIR__ . '/../extras/api/index.php'));

		process_api_request();
	}
	// Otherwise, if the API Extra is not installed
	else
	{
		// Send the JSON response that the API Extra is not installed
		json_response(400, "The API Extra is either not installed or not activated.", NULL);
	}
?>
