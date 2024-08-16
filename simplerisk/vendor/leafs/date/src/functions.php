<?php

declare(strict_types=1);

if (!function_exists('tick')) {
    /**
     * Return the leaf date instance
     *
     * @return Leaf\Date
     */
    function tick(string $userDate = null, string $userTimeZone = null)
    {
        if (!class_exists('Leaf\App')) {
            return (new Leaf\Date())->tick($userDate, $userTimeZone);
        }

        $date = Leaf\Config::get('date')['instance'] ?? null;

        if (!$date) {
            $date = new Leaf\Date();
            Leaf\Config::set('date', ['instance' => $date]);
        }

        if ($userDate) {
            $date->tick($userDate, $userTimeZone);
        }

        return $date;
    }
}
