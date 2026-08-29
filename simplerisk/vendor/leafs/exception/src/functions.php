<?php

if (!function_exists('crash')) {
    /**
     * The shared crash hub: journey crumbs, context, capture.
     *
     * crash()->leaveCrumb('coupon applied', 'action', ['total' => $total]);
     * crash()->capture('total is 0 but cart has items', ['level' => 'warning']);
     *
     * The same instance is used by Leaf's error handler, so crumbs you
     * leave here show up on crash pages and in reports.
     *
     * @return \Leaf\Crash\Hub
     */
    function crash(): \Leaf\Crash\Hub
    {
        if (class_exists('Leaf\Config')) {
            if (!(\Leaf\Config::getStatic('crash'))) {
                \Leaf\Config::singleton('crash', function () {
                    return new \Leaf\Crash\Hub();
                });
            }

            return \Leaf\Config::get('crash');
        }

        // outside a leaf app: one shared hub for the process
        static $hub = null;

        return $hub ?? ($hub = new \Leaf\Crash\Hub());
    }
}
