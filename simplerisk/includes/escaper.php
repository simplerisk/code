<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/****************************************************
 * FUNCTION: CUSTOM ESCAPER FOR PHP 8 COMPATIBILITY *
 ****************************************************/
class simpleriskEscaper
{
    public $escaper;
    public function __construct(){
        // Include Laminas Escaper for HTML Output Encoding
        $escaper = new Laminas\Escaper\Escaper('utf-8');
        $this->escaper = $escaper;
    }
    public function escapeHtml($string)
    {
        $string = strval($string);
        return $this->escaper->escapeHtml($string);
    }
    public function escapeHtmlAttr($string)
    {
        $string = strval($string);
        return $this->escaper->escapeHtmlAttr($string);
    }
    public function escapeJs($string)
    {
        $string = strval($string);
        return $this->escaper->escapeJs($string);
    }
    public function escapeUrl($string)
    {
        $string = strval($string);
        return $this->escaper->escapeUrl($string);
    }
    public function escapeCss($string)
    {
        $string = strval($string);
        return $this->escaper->escapeCss($string);
    }
}

?>
