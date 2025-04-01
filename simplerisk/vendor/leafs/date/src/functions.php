<?php

declare(strict_types=1);

if (!function_exists('tick')) {
    /**
     * Return the leaf date instance
     * @return Leaf\Date
     */
    function tick(?string $userDate = null, ?string $userTimeZone = null)
    {
        if (!class_exists('Leaf\App')) {
            return (new Leaf\Date())->tick($userDate, $userTimeZone);
        }

        if (!(\Leaf\Config::getStatic('date'))) {
            \Leaf\Config::singleton('date', function () {
                return new \Leaf\Date();
            });
        }

        $date = \Leaf\Config::get('date');

        if ($userDate) {
            $date->tick($userDate, $userTimeZone);
        }

        return $date;
    }
}
