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

    private $final, $host_addr;

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

                    $this->final[$this->host_addr]['vulns'][$vuln_id] = $vuln;
                } elseif (count($curr_item) == 2) // add a param to the current vuln
                {
                    if (isset($this->final[$this->host_addr]['vulns'][$vuln_id][$curr_item['name']]))
                        $this->final[$thsi->host_addr]['vulns'][$vuln_id][$curr_item['name']] .= "\n" . $curr_item['value'];
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

    // TODO: This is still broken !!! FIX it.
    function save()
    {

        //$this->final['report_name']

        //$all_cols = array('port', 'service', 'protocol', 'name', 'risk_factor', 'severity', 'synopsis', 'description', 'id', 'family',
        //    'vuln_publication_date', 'exploitability_ease', 'exploit_available', 'solution', 'plugin_version', 'plugin_publication_date',
        //    'plugin_modification_date', 'patch_publication_date', 'see_also', 'cvss_base_score', 'cve', 'bid', 'xref');


        foreach ($this->final AS $ip => $host)
        {
            var_dump($ip);
            var_dump($host); exit;

            $host_done = 0;
            $props = &$host['properties'];
            $rowspan = $this->get_num_vulns($host['vulns']);

            foreach ($host['vulns'] AS $vuln)
            {
                if (!$this->match_filters($vuln))
                    continue ;


                if ($host_done == 0)
                {
                    echo '<td valign="top" colspan="1" rowspan="'. $rowspan .'">';

                    echo '<b>'. $ip .'</b><br>';
                    echo '<i>'. ($props['host-fqdn'] ? $props['host-fqdn'] : $props['netbios-name'])  .'</i><br>';
                    echo $props['operating-system'];
                    //echo "<br>Netbios name: ". $props['netbios-name'] . "</td>";

                    $host_done = 1;
                }

                foreach ($all_cols AS $col)
                    if (in_array($col, $whitelist_cols))
                        echo "<td valign=\"top\">".
                            (empty($vuln[$col]) ? '&nbsp;' :
                                nl2br(htmlentities($vuln[$col]))) .
                            "</td>\n";

                echo "</tr>\n";
            }
            flush();
        }

    }

    function match_filters(&$vuln)
    {
        $whitelist_sevr = array_keys($_POST['sev']);
        $ms_filter = $_POST['ms'];

        $is_ms = (preg_match("/MS[0-9]{2}-[0-9]{3}: /", substr($vuln['name'], 0, 10)) > 0) ? true : false;

        if (!isset($vuln['risk_factor']) or
            (isset($vuln['risk_factor']) and
                !in_array($vuln['risk_factor'], $whitelist_sevr)))

            return false;

        if ((!$is_ms and $ms_filter == 'only_ms') or
            ($is_ms and $ms_filter == 'no_ms'))

            return false;

        return true;
    }

    function get_num_vulns(&$vulns)
    {
        $ret = 0;

        foreach ($vulns as $v) if ($this->match_filters($v)) $ret++;

        return $ret;
    }


    function import($file)
    {

        $this->parse_xml_file($file['tmp_name']);

    }
}

$nimp = new \lessrisk\nessusImporter();
$nimp->register();


