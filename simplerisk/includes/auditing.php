<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));

// Include the language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/*******************************************
 * FUNCTION: AUDIT LOG COMPARE USER VALUES *
 *******************************************/
function audit_log_compare_user_values($user_id, $value_name, $new_value, $existing_value)
{
	// If the values are the same
	if ($new_value == $existing_value)
	{
		$message = "No change necessary for " . $value_name . ".";
	}
	// The values are different
	else
	{
		$message = "Updated existing value for " . $value_name . " from \"" . $existing_value . " to " . $new_value . "\".";
	}

	// Write the log entry
	write_log($user_id, $_SESSION['uid'], $message, "users");
}

?>
