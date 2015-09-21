<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/************************************
 * FUNCTION: PASSWORD ERROR MESSAGE *
 ************************************/
function password_error_message($error_code)
{
	// Check the error code
	switch($error_code)
	{
		case 100:
			return "The new password entered does not match the confirm password entered.  Please try again.";
		case 101:
			return "The password entered does not adhere to the password policy.  Please try again.";
		default:
			return "There was an error with the password provided.  Please try again.";
	}
}

?>
