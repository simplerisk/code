#!/usr/bin/env php
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/lib/_autoload.php');

if ($argc < 2) {
    fwrite(STDERR, "Missing aggregator id.\n");
    exit(1);
}
$id = $argv[1];

error_reporting(E_ALL ^ E_NOTICE);
try {
    $aggregator = sspmod_aggregator2_Aggregator::getAggregator($id);
    $xml = $aggregator->getMetadata();
    $xml = SimpleSAML\Utils\XML::formatXMLString($xml);
    echo $xml;
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage()."\n");
}
