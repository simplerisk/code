<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 06/12/14
 * Time: 23:07
 */

namespace lessrisk;

interface riskImporter {
    function register();
    function getName();
    function import($string);
} 