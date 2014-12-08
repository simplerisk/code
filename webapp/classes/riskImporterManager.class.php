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


class riskImporterManager extends singleton {

    private $riskImporters;

    private $initialized = false;

    private function initialize (){
        if( !$this->initialized ){
            $this->initialized = true;

            $files = scandir(__DIR__ ."/../includes/importers/");

            $this->riskImporters = array();

            foreach ($files as $filename) {
                if(endsWith($filename , ".php"))
                require_once __DIR__ ."/../includes/importers/".$filename;
            }
        }
    }

    public function addRiskImporter($ri){
        $this->connectionAdpaters[$ri->getName()] = $ri;
    }

    public function getRiskImporter($name){
        $this->initialize();
        return $this->connectionAdpaters[$name];
    }



} 