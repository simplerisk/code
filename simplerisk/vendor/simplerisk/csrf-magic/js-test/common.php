<?php

function print_javascript($name, $web) {
    if (!file_exists($loc = "$name.js")) {
        $loc = $web;
    }
    echo '<script src="'.$loc.'" type="text/javascript"></script>';
    echo '<style>h1 {font-size:12pt;}</style>';
    return $loc;
}

function csrf_startup() {
    csrf_conf('rewrite-js', '../csrf-magic.js');
    csrf_conf('frame-breaker', false);
}
require_once '../csrf-magic.php';

// Handle an AJAX request
if (isset($_POST['ajax'])) {
    header('Content-type: text/xml;charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8" ?><response>Good!</response>'.PHP_EOL;
    exit;
}
