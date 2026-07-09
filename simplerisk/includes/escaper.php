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
    public function purifyHtml($html) {
        return purify_html($html);
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
    /**
     * Safely emit an admin-configurable color into a CSS `background-color`
     * (or similar) value. escapeCss() cannot be used here because it escapes
     * `#`, which corrupts the hex colors the risk-level color picker stores
     * (e.g. `#ff0000` -> `\23 ff0000`, an invalid color). Instead we allow-list
     * a valid CSS color — a hex triplet/quad (`#rgb`..`#rrggbbaa`) or an
     * alphabetic keyword (`red`, `orangered`, ...) — and fall back to
     * `transparent` for anything else (a CSS-injection attempt such as
     * `red;background-image:url(...)`). The validated value is run through
     * escapeHtml() before return: a no-op for a valid color (escapeHtml does
     * not touch `#` or letters), but it keeps a recognized output-encoder in
     * the return path so the value is safe in the surrounding HTML attribute.
     */
    public function escapeCssColor($color)
    {
        $color = trim(strval($color));
        // Valid CSS hex color is 3, 4, 6, or 8 digits (#rgb, #rgba, #rrggbb, #rrggbbaa),
        // or an alphabetic keyword (red, orangered, ...).
        if (preg_match('/^#([0-9A-Fa-f]{3,4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/', $color)
            || preg_match('/^[A-Za-z]+$/', $color)) {
            return $this->escapeHtml($color);
        }
        return 'transparent';
    }
}

?>