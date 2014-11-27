<?php
/**
 * Created by felipe. for lessrisk
 * Date: 26/11/14
 * Time: 20:53
 *
 * @author felipe
 * 
 * @version 1.0
 */

namespace lessrisk;

require_once "singleton.class.php";

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}


class dbConnectionManager extends singleton {

    private $connectionAdpaters, $lowestPriority;

    private $initialized = false;

    private $token = "";

    private function initialize (){
        if( !$this->initialized ){
            $this->initialized = true;

            $files = scandir(__DIR__ ."/../includes/adapters/");

            $this->connectionAdpaters = array();
            $this->lowestPriority = 10;

            foreach ($files as $filename) {
                if(endsWith($filename , ".php"))
                // echo __DIR__ ."/../includes/adapters/".$filename;
                require_once __DIR__ ."/../includes/adapters/".$filename;
            }
            //exit();
        }
    }

    public function addAddapter($addapter, $priority){
        $this->connectionAdpaters[$priority] = $addapter;
        if($this->lowestPriority > $priority) $this->lowestPriority = $priority;
    }

    public function getDefaultAddapter(){
        $this->initialize();
        if(count($this->connectionAdpaters) == 0 ) throw new \Exception("Impossible to find the default addapter");

        return $this->connectionAdpaters[$this->lowestPriority];
    }


    public function setToken($token){
        $this->$token = $token;
    }


    public function getToken(){
        return $this->token;
    }

} 