<?php

if (!function_exists('request')) {
    /**
     * Return request or request data
     *
     * @param array|string $data — Get data from request
     * 
     * @return \Leaf\Http\Request
     */
    function request()
    {
        if (class_exists('\Leaf\Config')) {
            if (!\Leaf\Config::getStatic('request')) {
                \Leaf\Config::singleton('request', function () {
                    return new \Leaf\Http\Request;
                });
            }

            return \Leaf\Config::get('request');
        }

        return new \Leaf\Http\Request();
    }
}

if (!function_exists('response')) {
    /**
     * Return response or set response data
     *
     * @param array|string $data — The JSON response to set
     * 
     * @return \Leaf\Http\Response
     */
    function response()
    {
        if (class_exists('\Leaf\Config')) {
            if (!\Leaf\Config::getStatic('response')) {
                \Leaf\Config::singleton('response', function () {
                    return new \Leaf\Http\Response;
                });
            }

            return \Leaf\Config::get('response');
        }

        return new \Leaf\Http\Response();
    }
}
