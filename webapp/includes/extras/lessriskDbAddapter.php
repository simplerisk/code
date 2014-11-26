<?php

include_once "gubd-connect.php";

function dbAdStatus(){
    return true;
}

function getDbPassword(){
    getPassword("lessrisk", "eP30NLJl6GBdzGU", "lessrisk");
}

function getDbLogin(){
    getLogin("lessrisk", "eP30NLJl6GBdzGU", "lessrisk");
}

function getDbSchema(){
    getSchema("lessrisk", "eP30NLJl6GBdzGU", "lessrisk");
}