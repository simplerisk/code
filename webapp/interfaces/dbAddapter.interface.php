<?php
/**
 * Created by felipe. for lessrisk
 * Date: 26/11/14
 * Time: 21:10
 *
 * @author felipe
 * 
 * @version 1.0
 */

namespace lessrisk;


interface dbAddapter {

    function register($priority);
    function getDbPassword();
    function getDbLogin();
    function getDbSchema();
    function dbAdStatus();

} 