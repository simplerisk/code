<?php

//include_once "../../classes/dbConnectionManager.class.php";
include_once __DIR__."/../../interfaces/dbAddapter.interface.php";
include_once "lessriskDbAddapter.config.php";

class lessriskDbAddapter implements \lessrisk\dbAddapter{


    function register($priority)
    {
        $dcm = \lessrisk\dbConnectionManager::get_instance();
        $dcm->addAddapter($this, $priority);
    }

    function getDbPassword()
    {
        return LR_DB_PASSWORD;
    }

    function getDbLogin()
    {
        return LR_DB_USERNAME;
    }

    function getDbSchema()
    {
        return LR_DB_DATABASE;
    }

    function dbAdStatus()
    {
        return true;
    }
}


$dbAdpt = new lessriskDbAddapter();
$dbAdpt->register(10);

