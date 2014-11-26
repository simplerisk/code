<?php

//include_once "../../classes/dbConnectionManager.class.php";
include_once __DIR__."/../../interfaces/dbAddapter.interface.php";


class lessriskDbAddapter implements \lessrisk\dbAddapter{


    function register($priority)
    {
        $dcm = \lessrisk\dbConnectionManager::get_instance();
        $dcm->addAddapter($this, $priority);
    }

    function getDbPassword()
    {
        return false;
    }

    function getDbLogin()
    {
        return false;
    }

    function getDbSchema()
    {
        return false;
    }

    function dbAdStatus()
    {
        return false;
    }
}


$dbAdpt = new lessriskDbAddapter();
$dbAdpt->register(10);

