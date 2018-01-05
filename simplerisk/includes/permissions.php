<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/alerts.php'));

// Include the language file
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/*****************************************
 * FUNCTION: CHECK PERMISSION GOVERNANCE *
 *****************************************/
function check_permission_governance()
{
        // Check if governance is authorized
        if (!isset($_SESSION["governance"]) || $_SESSION["governance"] != 1)
        {
                return false;
        }
        else return true;
}

/*******************************************
 * FUNCTION: ENFORCE PERMISSION GOVERNANCE *
 *******************************************/
function enforce_permission_governance()
{
        // If governance is not authorized
        if (!check_permission_governance())
        {
                header("Location: ../index.php");
                exit(0);
        }
}

/*********************************************
 * FUNCTION: CHECK PERMISSION RISKMANAGEMENT *
 *********************************************/
function check_permission_riskmanagement()
{
	// Check if riskmanagement is authorized
	if (!isset($_SESSION["riskmanagement"]) || $_SESSION["riskmanagement"] != 1)
	{
		return false;
	}
	else return true;
}

/***********************************************
 * FUNCTION: ENFORCE PERMISSION RISKMANAGEMENT *
 ***********************************************/
function enforce_permission_riskmanagement()
{
        // If riskmanagement is not authorized
	if (!check_permission_riskmanagement())
        {
                header("Location: ../index.php");
                exit(0);
        }
}

/*****************************************
 * FUNCTION: CHECK PERMISSION COMPLIANCE *
 *****************************************/
function check_permission_compliance()
{
        // Check if compliance is authorized
        if (!isset($_SESSION["compliance"]) || $_SESSION["compliance"] != 1)
        {
                return false;
        }
        else return true;
}

/*******************************************
 * FUNCTION: ENFORCE PERMISSION COMPLIANCE *
 *******************************************/
function enforce_permission_compliance()
{
        // If compliance is not authorized
        if (!check_permission_compliance())
        {
                header("Location: ../index.php");
                exit(0);
        }
}

?>
