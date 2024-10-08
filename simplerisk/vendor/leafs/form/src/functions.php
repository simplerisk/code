<?php

if (!function_exists('form') && class_exists('Leaf\App')) {
    /**
     * Return leaf form object
     *
     * @return Leaf\Form
     */
    function form(): \Leaf\Form
    {
        if (!(\Leaf\Config::getStatic('form'))) {
            \Leaf\Config::singleton('form', function () {
                return new \Leaf\Form();
            });
        }

        return \Leaf\Config::get('form');
    }
}
