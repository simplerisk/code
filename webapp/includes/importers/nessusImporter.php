<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 08/12/14
 * Time: 22:27
 */

namespace lessrisk;

class nessusImporter implements riskImporter {

    function register()
    {
        $importerManager = riskImporterManager::get_instance();
        $importerManager->addRiskImporter($this);
    }

    function getName()
    {
        return "Nessus";
    }

    function import($string)
    {
        // TODO: Implement import() method.
    }
}

$nimp = new \lessrisk\nessusImporter();
$nimp->register();


