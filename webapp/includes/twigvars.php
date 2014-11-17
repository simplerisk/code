<?php

$base_twigvars = $lang;

$base_twigvars['base_url'] = BASE_URL;

if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1") {
    $base_twigvars['admin'] = 1;
}

if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted") {
    $base_twigvars['session_access'] = 1;
    $base_twigvars['session_name'] = $_SESSION['name'];
}