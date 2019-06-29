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

/*************************************
 * FUNCTION: CHECK PERMISSION ASSET *
 *************************************/
function check_permission_asset()
{
        // Check if asset is authorized
        if (!isset($_SESSION["asset"]) || $_SESSION["asset"] != 1)
        {
                return false;
        }
        else return true;
}

/*******************************************
 * FUNCTION: ENFORCE PERMISSION ASSET *
 *******************************************/
function enforce_permission_asset()
{
        // If asset is not authorized
        if (!check_permission_asset())
        {
                header("Location: ../index.php");
                exit(0);
        }
}

/******************************************
 * FUNCTION: CHECK PERMISSION ASSESSMENTS *
 ******************************************/
function check_permission_assessments()
{
    // Check if assessments is authorized
    if (!isset($_SESSION["assessments"]) || $_SESSION["assessments"] != 1)
    {
        return false;
    }
    else return true;
}

/********************************************
 * FUNCTION: ENFORCE PERMISSION ASSESSMENTS *
 ********************************************/
function enforce_permission_assessments()
{
    // If asset is not authorized
    if (!check_permission_assessments())
    {
        header("Location: ../index.php");
        exit(0);
    }
}

/*************************************
 * FUNCTION: CHECK PERMISSION ASSET *
 *************************************/
$exception_permissions = ['view' ,'create' ,'update' ,'delete' ,'approve'];
function check_permission_exception($function)
{
    global $exception_permissions;
    return in_array($function, $exception_permissions)
            && isset($_SESSION["{$function}_exception"])
            && $_SESSION["{$function}_exception"] == 1;
}

function enforce_permission_exception($function)
{
    // If exception is not authorized
    if (!check_permission_exception($function))
    {
        header("Location: ../index.php");
        exit(0);
    }
}
?>
