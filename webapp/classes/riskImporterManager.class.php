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

require_once "../interfaces/riskImporter.interface.php";


class riskImporterManager extends singleton {

    /* @var $riskImporters riskImporter */
    private  $riskImporters;

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

    public function addRiskImporter(riskImporter $ri){
        $this->initialize();
        $this->riskImporters[$ri->getName()] = $ri;
    }

    public function getRiskImporter($name){
        $this->initialize();
        return $this->riskImporters[$name];
    }

    public function getRiskImporterNameList(){
        $this->initialize();
        $risks = array();
        $i = 0;
        foreach($this->riskImporters as $name => $risk ){
            $risks[$i] = $name;
            $i++;
        }
        return $risks;
    }

} 