<?php
/**
 * Created by felipe. for lessrisk
 * Date: 03/12/14
 * Time: 11:50
 *
 * @author felipe
 * 
 * @version 1.0
 */


include_once __DIR__."/../classes/dbConnectionManager.class.php";

$dbm = \lessrisk\dbConnectionManager::get_instance();
$dba = $dbm->getDefaultAddapter();

// MySQL Database Host Name
define('DB_HOSTNAME', '127.0.0.1');

// MySQL Database Port Number
define('DB_PORT', '3306');

// MySQL Database User Name
if($dba->dbAdStatus()) define('DB_USERNAME', $dba->getDbLogin()); else define('DB_USERNAME', 'lessrisk');

// MySQL Database Password
if($dba->dbAdStatus()) define('DB_PASSWORD', $dba->getDbPassword()); else define('DB_PASSWORD', 'lessrisk');

// MySQL Database Name
if($dba->dbAdStatus()) define('DB_DATABASE', $dba->getDbSchema()); else define('DB_DATABASE', 'lessrisk');