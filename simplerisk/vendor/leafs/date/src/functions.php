<?php

declare(strict_types=1);

if (!function_exists('tick')) {
    /**
     * Initialize a new date instance
     * @param string|DateTime|\Leaf\Date $userDate The date to initialize with
     * @param string|null $userTimeZone The timezone to initialize with
     * @return \Leaf\Date
     */
    function tick($userDate = 'now', ?string $userTimeZone = null): \Leaf\Date
    {
        return (new Leaf\Date())->tick($userDate, $userTimeZone);
    }
}
