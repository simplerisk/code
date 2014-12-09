<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 08/12/14
 * Time: 22:27
 */

namespace lessrisk;

require_once (realpath(__DIR__ . '/../../libs/xml2array.php'));


class nessusImporter implements riskImporter {

    private $final, $host_addr, $subjectPrefix, $parent_id;

    function register()
    {
        $importerManager = riskImporterManager::get_instance();
        $importerManager->addRiskImporter($this);
    }

    function getName()
    {
        return "Nessus";
    }

    function loop_host(&$report_host)
    {
        foreach ($report_host AS $report_item)
        {
            if(is_array($report_item)) {
                if ($report_item['name'] == 'HostProperties')
                    $this->loop_property($report_item);

                if ($report_item['name'] == 'ReportItem')
                    $this->loop_item($report_item);
            }
        }
    }

    function loop_property(&$report_item)
    {
        foreach ($report_item AS $host_property) {
            if (is_array($host_property)) {
                if ($host_property['name'] == 'tag')
                    $this->final[$this->host_addr]['properties'][$host_property['attributes']['name']] = $host_property['value'];

            }
        }
    }

    function loop_item(&$report_item)
    {

        foreach ($report_item AS $curr_item)
        {
            if(is_array($curr_item)) {
                if (count($curr_item) == 7) // new vuln
                {
                    $vuln_id += 1;
                    $vuln = array();
                    $vuln['port'] = $curr_item['port'];
                    $vuln['service'] = $curr_item['svc_name'];
                    $vuln['protocol'] = $curr_item['protocol'];
                    $vuln['severity'] = $curr_item['severity'];
                    $vuln['id'] = $curr_item['pluginID'];
                    $vuln['name'] = $curr_item['pluginName'];
                    $vuln['family'] = $curr_item['pluginFamily'];

                    if (!isset($this->final[$this->host_addr]['vulns'])) // 1st vuln of this host
                        $this->final[$this->host_addr]['vulns'] = array();

                    //$this->final[$this->host_addr]['vulns'][$vuln_id] = $vuln;
                    array_push($this->final[$this->host_addr]['vulns'] , $vuln);
                } elseif (count($curr_item) == 2) // add a param to the current vuln
                {
                    if (isset($this->final[$this->host_addr]['vulns'][$vuln_id][$curr_item['name']]))
                        $this->final[$this->host_addr]['vulns'][$vuln_id][$curr_item['name']] .= "\n" . $curr_item['value'];
                    else
                        $this->final[$this->host_addr]['vulns'][$vuln_id][$curr_item['name']] = $curr_item['value'];
                }
            }
        }
    }

    function parse_xml_file($filename)
    {

        $full = my_xml2array($filename);
        $report = get_value_by_path($full, 'NessusClientData_v2/Report');


        $this->final = array();
        $this->host_addr = '';
        $vuln_id = -1;
        $this->final['report_name'] = $report['attributes']['name'];



        foreach ($report AS $report_host)
        {
            if(is_array($report_host)) {
                if ($report_host['name'] == 'ReportHost') {
                    $this->host_addr = $report_host['attributes']['name'];
                    $this->final[$host_addr] = array();

                    $this->loop_host($report_host);
                }
            }
        }

        $this->save();
        //render();
    }

    function avalrisk($val, $val2){
        $a = -1;
        $b = -1;

        if($val == "N") $a = 0;
        if($val == "L") $a = 1;
        if($val == "M") $a = 2;
        if($val == "H") $a = 3;
        if($val == "P") $a = 1;
        if($val == "C") $a = 2;

        if($val2 == "N") $b = 0;
        if($val2 == "L") $b = 1;
        if($val2 == "M") $b = 2;
        if($val2 == "H") $b = 3;
        if($val2 == "P") $b = 1;
        if($val2 == "C") $b = 2;

        return $a - $b;
    }

    function avalriskAC($val, $val2){
        $a = -1;
        $b = -1;

        if($val == "L") $a = 0;
        if($val == "A") $a = 1;
        if($val == "N") $a = 2;


        if($val2 == "L") $b = 0;
        if($val2 == "A") $b = 1;
        if($val2 == "N") $b = 2;


        return $a - $b;
    }

    // TODO: This is still broken !!! FIX it.
    function save()
    {

        //$this->final['report_name']

        //$all_cols = array('port', 'service', 'protocol', 'name', 'risk_factor', 'severity', 'synopsis', 'description', 'id', 'family',
        //    'vuln_publication_date', 'exploitability_ease', 'exploit_available', 'solution', 'plugin_version', 'plugin_publication_date',
        //    'plugin_modification_date', 'patch_publication_date', 'see_also', 'cvss_base_score', 'cve', 'bid', 'xref');


        $workset = array_slice($this->final,  2);


        foreach ($workset AS $ip => $host)
        {

            $props = &$host['properties'];

            foreach ($host['vulns'] AS $vuln)
            {


                if(array_key_exists('synopsis', $vuln)){
                    $risk = new \Risks();
                    $risk->setNew(true);
                    $risk->setSubject($this->subjectPrefix."-Vuln-".$props['host-fqdn']."-".$vul['synopsis']);
                    $risk->setParentId($this->parent_id);
                    $risk->setAssessment($vuln['description']);
                    $risk->setNotes("CVE: ".$vuln['cve'] );
                    $risk->setStatus("New");


                    $risk->save();

                    $cvss = explode("\n", $vuln['cvss_vector']);

                    foreach($cvss as $item){
                        $item = substr($item, 6);

                        $vals = explode("/", $item);

                        foreach($vals as $val){
                            $val = explode(":", $val);
                            switch($val[0]){
                                case 'AV':
                                    if($this->avalrisk($AV, $val[1]) < 0)
                                    $AV = $val[1];
                                    break;
                                case 'AC':
                                    if($this->avalriskAC($AC, $val[1]) < 0)
                                    $AC = $val[1];
                                    break;
                                case 'Au':
                                    if($this->avalrisk($Au, $val[1]) < 0)
                                    $Au = $val[1];
                                    break;
                                case 'C':
                                    if($this->avalrisk($C, $val[1]) < 0)
                                    $C = $val[1];
                                    break;
                                case 'I':
                                    if($this->avalrisk($I, $val[1]) < 0)
                                    $I = $val[1];
                                    break;
                                case 'A':
                                    if($this->avalrisk($A, $val[1]) < 0)
                                    $A = $val[1];
                            }
                        }

                    }

                    $scoring = new \RiskScoring();

                    $scoring->setId($risk->getId());
                    $scoring->setScoringMethod(2);

                    $scoring->setCvssAccesscomplexity($AC);
                    $scoring->setCvssAccessvector($AV);
                    $scoring->setCvssAvailimpact($A);
                    $scoring->setCvssIntegimpact($I);
                    $scoring->setCvssConfimpact($C);
                    $scoring->setCvssAuthentication($Au);

                    /* @var $cvq \CvssScoringQuery */
                    $cvq =  new \CvssScoringQuery();


                    /* @var $cvsi \CvssScoring*/
                    $cvsi = $cvq->filterByAbrvMetricName('AV')->filterByAbrvMetricValue($AV)->findOne();
                    $AVn = $cvsi->getNumericValue();
                    $cvq =  new \CvssScoringQuery();
                    $cvsi = $cvq->filterByAbrvMetricName('AC')->filterByAbrvMetricValue($AC)->findOne();
                    $ACn = $cvsi->getNumericValue();
                    $cvq->clear();
                    $cvsi = $cvq->filterByAbrvMetricName('Au')->filterByAbrvMetricValue($Au)->findOne();
                    $Aun = $cvsi->getNumericValue();
                    $cvq->clear();
                    $cvsi = $cvq->filterByAbrvMetricName('C')->filterByAbrvMetricValue($C)->findOne();
                    $Cn = $cvsi->getNumericValue();
                    $cvq->clear();
                    $cvsi = $cvq->filterByAbrvMetricName('I')->filterByAbrvMetricValue($I)->findOne();
                    $In = $cvsi->getNumericValue();
                    $cvq->clear();
                    $cvsi = $cvq->filterByAbrvMetricName('A')->filterByAbrvMetricValue($A)->findOne();
                    $An = $cvsi->getNumericValue();

                    $scoring->setCalculatedRisk(calculate_cvss_score($AVn,$ACn,$Aun,$Cn,$In,$An,1,1,0.66,1,1,1,1,1));

                    $scoring->save();



                }


            }

        }

    }

    function get_num_vulns(&$vulns)
    {
        $ret = 0;

        foreach ($vulns as $v)  $ret++;

        return $ret;
    }


    function import($file)
    {

        $this->parse_xml_file($file['tmp_name']);

    }

    function setSubjectPrefix($string)
    {
        $this->subjectPrefix = $string;
    }

    function setParentId($id)
    {
        $this->parent_id = $id;
    }
}

$nimp = new \lessrisk\nessusImporter();
$nimp->register();


